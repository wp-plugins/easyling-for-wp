<?php

include_once(dirname(__FILE__). '/../config.php');

/**
 * Easyling_Admin class is responsible for rendering, displaying and managing
 * Easyling specific options on the admin
 */
class Easyling_Admin {

    private $_page_suffix_easyling;
    private $_oauth_endpoint;
    private $_redirURL = null;

    const OAUTH_CREATEIDENT = "ptm/createIdentity";
    const OAUTH_REQUESTTOKEN = "oauth/getRequestToken";
    const DEFAULT_TRANSLATION_PROXY_ENDPOINT = "https://app.easyling.com/_el/ext/";
	const IN_URL_NAME = 'translationproxy';

    /**
     * Easyling Instance
     * @var Easyling
     */
    private $easylingInstance;
    private $updateNotices = array();
	/** @var  PTM $ptm */
	private $ptm;

	/** @var  Easyling_Settings $settings */
	private $settings;

    /** @var  string */
    private $linkErrorMessage;

	/**
	 * @param Easyling $easylingInstance
	 */
	public function __construct($easylingInstance) {
        $this->easylingInstance = $easylingInstance;
	    $this->ptm = $easylingInstance->getPtm();
		$this->settings = $easylingInstance->getSettings();
        if (defined('TRANSLATION_PROXY_ENDPOINT')) {
            $this->_oauth_endpoint = TRANSLATION_PROXY_ENDPOINT;
        } else {
            $this->_oauth_endpoint = self::DEFAULT_TRANSLATION_PROXY_ENDPOINT;
        }

        add_action('admin_menu', array(&$this, 'admin_menu_hook'));

        // admin notice
        add_action('load-plugins.php', array(&$this, 'prepare_admin_notices'));
        // register settings
        add_action('admin_init', array(&$this, 'register_options'));
        // register plugin update stuff
        add_action('admin_init', array($this, 'run_plugin_update_callbacks'));

	    if (!session_id())
            session_start();

        // access token is valid for a while, let's load it as an option
        $access_tokens = $this->settings->getOAuthAccessToken();
        if ($access_tokens != null) {
            $_SESSION['oauth'] = $access_tokens->getAsArray();
        }

        // set the internal redirect
        $this->_redirURL = isset($_SESSION['oauth_internal_redirect'])
                ? $_SESSION['oauth_internal_redirect']
                : null;
        // start transfer
        if (isset($_REQUEST['transfer'])) {

	        /** @noinspection PhpIncludeInspection */
            require_once EASYLING_PATH . 'includes/OAuth/OAuth.php';
            // check if we have access token
            if (!isset($_SESSION['oauth']['access_token'])) {
                $_SESSION['oauth_internal_redirect'] = $this->get_plugin_admin_url('transfer=1');
                $this->oauth_authorization();
                die();
            }

            $hmac_method = new ELOAuthSignatureMethod_HMAC_SHA1();
            $oauthServer = $this->_oauth_endpoint;
            $endpoint = $oauthServer . 'ptm/startTransfer';

            $linked_project = $this->settings->getLinkedProject();
            $project = $this->easylingInstance->getPtm()->getFrameworkService()->getProjectByCode($linked_project);
            $lang = $project->getProjectLanguageArray();

            $req = null;
            $res = null;
            try {

                foreach ($lang as $l) {
                    $params = array(
                        'projectCode'    => $project->getProjectCode(),
                        'targetLanguage' => $l,
                        'callback'       => get_site_url() . "/wp-admin/admin-ajax.php?action=".self::IN_URL_NAME."_oauth_push&projectCode={$project->getProjectCode()}&targetLanguage={$l}"
                    );
                    $consumer = $this->getStoredOAuthConsumer();
                    $token = new ELOAuthToken($_SESSION['oauth']['access_token'], $_SESSION['oauth']['access_token_secret']);
                    $req = ELOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
                    $req->sign_request($hmac_method, $consumer, $token);
                    $res = $req->curlit($req->to_url());
                    $this->checkOAuthInvalidToken($res, 'transfer=1');
                    $this->easylingInstance->getPtm()->getFrameworkService()->setProjectAttributesByELResponse($project, $res['response']);
                }
            } catch (Exception $e) {
                $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR,
	                array(
		                'method:'=> 'startTransfer',
		                'oauth_request' => $req,
		                'oauth_resp' => $res));
            }
            header('Location: ' . $this->get_plugin_admin_url());
        } else {
            $this->oauth_action();
        }
    }

    public function checkOAuthInvalidToken($oauthResponse, $redirectParams) {

        if ($oauthResponse['code'] == 400 || $oauthResponse['code'] == 500) {
            $rawResponse = $oauthResponse['response'];
            $response = @json_decode($rawResponse, true);

            if ($response != null && isset($response['error'])) {
                if ($response['mnemonic'] == "invalidToken") {
                    $_SESSION['oauth_internal_redirect'] = $this->get_plugin_admin_url($redirectParams);
                    $this->oauth_authorization();
                    die();
                }
            }

            throw new Exception("Unrecoverable error by accessing OAuth resource");
        }
    }

    public function oauth_action() {
        if (isset($_REQUEST['wipe'])) {
            unset($_SESSION['oauth']);
            unset($_SESSION['oauth_internal_redirect']);


	        delete_option('easyling_id');
            delete_option('easyling_available_locales');
            delete_option('easyling_project_languages');
            delete_option('easyling_linked_project');
            delete_option('easyling_consent');
            delete_option('easyling_access_tokens');
            delete_option('easyling_language_selector');
            delete_option('easyling_multidomain');
            delete_option('easyling_source_langs');
	        // PTM put this option
            delete_option('easyling_availableProjects');

            // reset the plugin status
	        $ps = $this->settings->getPluginSettings();
	        $ps->setStatus(Easyling_PluginSettings::STATUS_INSTALLED);
	        $this->settings->savePluginSettings();
            header("Location: " . $this->get_plugin_admin_url());
            die();
        }
        if (isset($_REQUEST['oauth_action'])) {
            try {
                if (is_callable(array(&$this, "oauth_" . $_REQUEST['oauth_action']))) {
	                /** @noinspection PhpIncludeInspection */
	                require_once EASYLING_PATH . 'includes/OAuth/OAuth.php';
                    call_user_func(array(&$this, "oauth_" . $_REQUEST['oauth_action']));
                } else {
                    throw new Exception("OAuth method not found:" . $_REQUEST['oauth_action']);
                }
            } catch (Exception $e) {
                $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array('method:' => $_REQUEST['oauth_action']));
            }
        }
    }

	public function oauth_consumer_key_n_secret() {
        if ($this->settings->getOAuthId(true) !== null) {
            header('Location: ' . $this->get_plugin_admin_url('oauth_action=authorization'));
            die();
        }

        // step 1: generate consumer key and secret
        $blogurl = get_site_url();
        $parsedUrl = parse_url($blogurl);
        $req = new ELOAuthRequest('GET', $this->_oauth_endpoint . self::OAUTH_CREATEIDENT . "?siteName=" .
                $parsedUrl['host']);
        $res = $req->curlit();
        try {
            if ($res['code'] == 200) {
                $response = json_decode($res['response'], true);
                if (isset($response['response']['identity']) && isset($response['response']['identity']['consumerKey'])) {
                    $key = $response['response']['identity']['consumerKey'];
                    $secret = $response['response']['identity']['consumerSecret'];
	                $this->settings->saveOAuthId(new Easyling_OAuthSettings($key, $secret));
                    header('Location: ' . $this->get_plugin_admin_url('oauth_action=authorization'));
                    die();
                } else {
                    throw new Exception("Error occured while getting identity");
                }
            } else if ($res['code'] != 0) {
                $this->linkErrorMessage = "HTTP Error ".$res['code'];
                throw new Exception('Error while trying to get identity');
            } else {
                if ($res['errno'] != 0) {
                    if ($res['errno'] == 35) {
                        $this->linkErrorMessage = "SSL Error: no HTTPS channel could be created, please enable SSL or TLS on your Wordpress installation";
                    } else {
                        $this->linkErrorMessage = "General network error: ".$res['error'];
                    }
                    throw new Exception('Error while trying to get identity');
                }
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

	public function get_plugin_admin_url($query_params = null) {
		$path = 'admin.php?page='.self::IN_URL_NAME;
		if (is_string($query_params)) {
			if (isset($query_params[0]) && $query_params[0] != "&") {
				$path .= "&";
			}
			$path .= $query_params;

		}
		else if (is_array($query_params)) {
			foreach ($query_params as $name=>$value) {
				$path .= "&".urlencode($name)."=".urlencode($value);
			}
		}

		return get_admin_url(null, $path, 'admin');
	}

	public function getStoredOAuthConsumer() {
		$oauthSettings = $this->settings->getOAuthId();
		return new ELOAuthConsumer($oauthSettings->getKey(), $oauthSettings->getSecret(), NULL);
	}

    public function oauth_authorization() {
        $hmac_method = new ELOAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . self::OAUTH_REQUESTTOKEN;
        $params = array(
            'oauth_callback' => $this->get_plugin_admin_url('oauth_action=accesstoken')
        );

	    $consumer = $this->getStoredOAuthConsumer();
        $req = ELOAuthRequest::from_consumer_and_token($consumer, NULL, "GET", $endpoint, $params);
        $req->sign_request($hmac_method, $consumer, NULL);
        $req->curlit($req->to_url());
        try {
            if ($req->response['code'] == 200) {
                $response = $req->extract_params($req->response['response']);
                $_SESSION['oauth_token_secret'] = $response['oauth_token_secret'];
                $parsedUrl = parse_url(get_site_url());
                header("Location: {$oauthServer}oauth/authorizeUser?oauth_token=" . $response['oauth_token'] . '&siteName=' . $parsedUrl['host']);
            } else {
                throw new Exception('OAuth Error while getting Request Token');
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

    public function oauth_accesstoken() {
        $hmac_method = new ELOAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . 'oauth/getAccessToken';
        $params = array(
            'oauth_verifier' => $_REQUEST['oauth_verifier'],
//        'oauth_token' => $_REQUEST['oauth_token']
        );
        $oauthToken = $_REQUEST['oauth_token'];
        $consumer = $this->getStoredOAuthConsumer();
        $token = new ELOAuthToken($oauthToken, $_SESSION['oauth_token_secret']);
        $req = ELOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
        $req->sign_request($hmac_method, $consumer, $token);
        $res = $req->curlit($req->to_url());
        $answer = $req->extract_params($res['response']);
        try {
            if ($req->response['code'] == 200 && $answer['status'] != 'error') {
                $response = $req->extract_params($req->response['response']);
                $accessToken = $response['oauth_token'];
                $accessTokenSecret = $response['oauth_token_secret'];
                unset($_SESSION['oauth']);

                $_SESSION['oauth']['access_token'] = $accessToken;
                $_SESSION['oauth']['access_token_secret'] = $accessTokenSecret;

                // update easyling options
	            $accessTokenSettings = new Easyling_OAuthAccessTokenSettings($accessToken, $accessTokenSecret);
	            $this->settings->saveOAuthAccessToken($accessTokenSettings);

                if ($this->_redirURL !== null) {
                    unset($_SESSION['oauth_internal_redirect']);
                    header('Location: ' . $this->_redirURL);
                }
                else
                    header('Location: ' . $this->getRetrieveProjectsURL());
            } else {
                throw new Exception("Could not retrieve Access Token - " . $answer['message']);
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

	public function getRetrieveProjectsURL() {
		return $this->get_plugin_admin_url('oauth_action=retrieveprojects');
	}

    public function oauth_retrieveprojects() {
        // check if we have access_token
        if (!isset($_SESSION['oauth']['access_token'])) {
            // try to
            $_SESSION['oauth_internal_redirect'] = $this->getRetrieveProjectsURL();
            $this->oauth_authorization();
            die();
        }
        $hmac_method = new ELOAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . 'ptm/projectList';
        $params = array(
        );
        $consumer = $this->getStoredOAuthConsumer();
        $token = new ELOAuthToken($_SESSION['oauth']['access_token'], $_SESSION['oauth']['access_token_secret']);
        $req = ELOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
        $req->sign_request($hmac_method, $consumer, $token);
        $res = $req->curlit($req->to_url());
	    $ptmService = $this->easylingInstance->getPtm()->getFrameworkService();

	    $usedProjects = new Map();

        try {
            $this->checkOAuthInvalidToken($res, 'oauth_action=retrieveprojects');
            if ($res['code'] == 200) {
                $decoded = json_decode($res['response'], true);
                foreach ($decoded['response']['projects'] as $p) {

                    // not add, if not accessible
                    if (!$p['accessible']) {
                        continue;
                    }

	                $projectCode = $p['code'];
	                $usedProjects[$projectCode] = true;

                    // get available languages for project
                    $endpoint = $oauthServer . 'ptm/languageList';
                    $params = array(
                        'projectCode' => $projectCode
                    );
                    $reqLang = ELOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
                    $reqLang->sign_request($hmac_method, $consumer, $token);
                    $resLang = $reqLang->curlit($reqLang->to_url());
                    if ($resLang['code'] == 200) {
                        $langsDecoded = json_decode($resLang['response'], true);
                        $languages = array();
                        foreach ($langsDecoded['response']['projects'] as $lang) {
                            // no checking for now
//                            if($lang['published'] == true)
                            $languages[] = $lang['name'];
                        }
                        $project = new Project($p['name'], $p['code'], $languages);
                        $ptmService->addAvailableProject($project);
                        $projectSourceLocales = $this->settings->getSourceLocales();
	                    $projectSourceLocales[$p['code']] = new PTMLocale($p['sourceLanguage']);
	                    $this->settings->saveSourceLocales($projectSourceLocales);
                        // save project
                    } elseif ($resLang['code'] == 403) {
                        // no access to the project - ask for it
                        $_SESSION['oauth_internal_redirect'] = $this->getRetrieveProjectsURL();
                        $this->oauth_authorization();
                        $this->easylingInstance->getPtm()->sendErrorReport(new Exception('403 from server for request: languageList'), PTMException::LEVEL_NOTICE, array_merge($_SESSION, $req->response));
                    } else {
                        throw new Exception("Could not retrieve language list for project: " . $p['name']);
                    }
                }

	            // remove the _not_ received projects
	            $storedProjectList = $ptmService->getAvailableProjects();
	            foreach ($storedProjectList as $storedProject) {
		            $stProjectCode = $storedProject->getProjectCode();
		            // do not remove the currently used project

		            if (!isset($usedProjects[$stProjectCode])) {
			            // do not delete the currently linked project
			            if ($this->settings->getLinkedProject() == $stProjectCode ) {
				            // TODO: display error message
			            } else {
			                $ptmService->removeAvailableProjectByCode($stProjectCode);
			            }
		            }
	            }

                // by now we should have all languages and also all languages
                // set the status
	            $ps = $this->settings->getPluginSettings();
	            $ps->setStatus(Easyling_PluginSettings::STATUS_AUTHED);
	            $this->settings->savePluginSettings();
                header('Location: ' . $this->get_plugin_admin_url());
            } else {
                throw new Exception('Could not access restricted OAuth resource');
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

    public function oauth_updateprojectlist() {
        unset($_SESSION['oauth']);
        $this->oauth_authorization();
    }

    public function prepare_admin_notices() {
        add_action('admin_notices', array(&$this, 'admin_notices'));
    }

    public function admin_notices() {
        if (false !== strpos($_SERVER['QUERY_STRING'], 'page='.self::IN_URL_NAME))
            return;
        if (!current_user_can('manage_options'))
            return;

	    $templateVars = $this->updateNotices;
	    $templateVars['admin'] = $this;
	    $templateVars['settings'] = $this->settings;
	    $templateVars['productName'] = PRODUCT_NAME;
	    $templateVars['whitelabel'] = defined('WHITELABELED') && WHITELABELED;
        echo $this->renderTemplate('notice_activation', $templateVars);
    }

	/**
	 * Runs the update callback functions
	 *
	 * @internal param array $opt_easyling
	 * @internal param array $updates
	 */
    public function run_plugin_update_callbacks() {

	    $pluginSettings = $this->settings->getPluginSettings();

        $updates = $pluginSettings->getUpdateArray();

        $vars = array('messages' => array());
        if (!empty($updates)) {
            foreach ($updates as $update) {
                $vars['messages'][] = $update['message'];
            }
            $this->updateNotices = $vars;

            // make sure to set the update messages as shown
            foreach ($updates as $k => $update) {
	            $pluginSettings->setUpdateActedUpon($k);
	            $callback = $update['callback'];
	            if ($callback != null)
                    call_user_func_array(array($this->easylingInstance, $callback), array($pluginSettings));
                $this->settings->savePluginSettings();
            }
        }
    }

    public function register_options() {
        register_setting('easyling_linking', 'easyling_linked_project');
        register_setting('easyling_linking', 'easyling_project_languages');
        register_setting('easyling_linking', 'easyling_multidomain');
        register_setting('easyling_linking', 'easyling_language_selector');
        register_setting('easyling_consent', 'easyling_consent');
        wp_register_style('modal-basic', EASYLING_URL . 'admin/css/basic.css');
        wp_enqueue_style('modal-basic');
        wp_register_script('simplemodal', EASYLING_URL . 'admin/js/jquery.simplemodal.js', array('jquery'));
        wp_enqueue_script('simplemodal');
    }

    /**
     * Hook ran on admin_menu action
     */
    public function admin_menu_hook() {
        $this->_page_suffix_easyling = add_menu_page(PRODUCT_NAME, PRODUCT_NAME, 'manage_options', self::IN_URL_NAME, array(&$this, 'admin_menu_page'));
        // when it loads add the help tabs to this page
        add_action("load-{$this->_page_suffix_easyling}", array(&$this, 'admin_contextual_help_tabs_easyling'));
    }

    /**
     * Hook to display the admin menu page
     */
    public function admin_menu_page() {
        $ptm = $this->easylingInstance->getPTM();
        $projects = $ptm->getFrameworkService()->getAvailableProjects();

        $language_selector = $this->settings->isLanguageSelector();
        $linked = $this->settings->getLinkedProject();

	    $pluginSettings = $this->settings->getPluginSettings();

        // see if we have the user's consent to report errors to us automatically
        $consent = $this->settings->isSendConsent();

        $linked = $linked == null ? false: true;

        echo $this->renderTemplate('easyling', array(
            'projects'          => $projects,
	        'admin'             => $this,
            'project_languages' => $this->settings->getProjectLocales(),
            'linked'            => $linked,
            'md'                => $this->settings->isMultiDomain(),
            'pcode'             => $this->settings->getLinkedProject(),
            'consent'           => $consent,
            'language_selector' => $language_selector?'on':'off',
	        'productName'       => PRODUCT_NAME,
	        'productLogo'       => PRODUCT_LOGO_URL,
	        'settings'          => $this->settings,
            'update_messages'   => $pluginSettings->getUpdateArray(),
		    'whitelabel'        => defined('WHITELABELED') && WHITELABELED,
            'linkErrorMessage'  => $this->linkErrorMessage
        )
	    );
    }

    public function admin_contextual_help_tabs_easyling() {
        $screen = get_current_screen();
        if ($screen->id == $this->_page_suffix_easyling) {

	        $this->easylingInstance->on_admin_save_changes();

            // remove overview tab
            $screen->remove_help_tabs();
            // add general tab
            $screen->add_help_tab(array(
                'id'      => 'help_easyling_test',
                'title'   => PRODUCT_NAME.' for Wordpress',
                'content' => $this->renderTemplate('help_intro')
            ));
        }
    }

	/**
	 * Very simple way to separate business logic froma templates...
	 * @param string $template template name
	 * @param array $templateVars assoc array
	 * @return string
	 */
    protected function renderTemplate($template, array $templateVars = array()) {
        ob_start();
        extract($templateVars, EXTR_SKIP);
	    /** @noinspection PhpIncludeInspection */
	    require_once EASYLING_PATH . 'admin/templates/' . $template . '.php';
        return ob_get_clean();
    }

}

