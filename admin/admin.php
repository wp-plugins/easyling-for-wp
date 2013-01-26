<?php

include_once(WP_PLUGIN_DIR . '/easyling/config.php');

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
    const DEFAULT_EASYLING_ENDPOINT = "http://akeszi.skawa-easyling.appspot.com/_el/ext/";

    /**
     * Easyling Instance
     * @var Easyling 
     */
    private $easylingInstance;

    public function __construct($easylingInstance) {
        $this->easylingInstance = $easylingInstance;
        if (defined('EASYLING_ENDPOINT')) {
            $this->_oauth_endpoint = EASYLING_ENDPOINT;
        } else {
            $this->_oauth_endpoint = self::DEFAULT_EASYLING_ENDPOINT;
        }

        add_action('admin_menu', array(&$this, 'admin_menu_hook'));

        // admin notice
        add_action('load-plugins.php', array(&$this, 'prepare_admin_notices'));
        // register settings
        add_action('admin_init', array(&$this, 'register_options'));

        if (!session_id())
            session_start();

        // set the internal redirect
        $this->_redirURL = isset($_SESSION['oauth_internal_redirect']) ? $_SESSION['oauth_internal_redirect'] : null;
        // start transfer
        if (isset($_REQUEST['transfer'])) {

            require_once EASYLING_PATH . '/includes/OAuth/OAuth.php';
            // check if we have access token
            if (!isset($_SESSION['oauth']['access_token'])) {
                $_SESSION['oauth_internal_redirect'] = get_admin_url(null, 'admin.php?page=easyling&transfer=1', 'admin');
                $this->oauth_authorization();
                die();
            }

            $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
            $oauthServer = $this->_oauth_endpoint;
            $endpoint = $oauthServer . 'ptm/startTransfer';

            $linked_project = get_option('easyling_linked_project');
            $project = $this->easylingInstance->getPtm()->getFrameworkService()->getProjectByCode($linked_project);
            $lang = $project->getProjectLanguageArray();
            foreach ($lang as $l) {
                $params = array(
                    'projectCode' => $project->getProjectCode(),
                    'targetLanguage' => $l,
                    'callback' => get_site_url() . "/wp-admin/admin-ajax.php?action=easyling_oauth_push&projectCode={$project->getProjectCode()}&targetLanguage={$l}"
                );
                extract(get_option('easyling_id'));
                $consumer = new OAuthConsumer($consumer_key, $consumer_secret, NULL);
                $token = new OAuthToken($_SESSION['oauth']['access_token'], $_SESSION['oauth']['access_token_secret']);
                $req = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
                $req->sign_request($hmac_method, $consumer, $token);
                $res = $req->curlit($req->to_url());
            }
            header('Location: ' . get_admin_url() . 'admin.php?page=easyling');
        } else {
            $this->oauth_action();
        }
    }

    public function oauth_action() {
        if (isset($_REQUEST['wipe'])) {
            unset($_SESSION['oauth']);
            unset($_SESSION['oauth_internal_redirect']);

            delete_option('easyling_available_locales');
            delete_option('easyling_project_languages');
            delete_option('easyling_linked_project');
            delete_option('easyling_consent');
            // set the status
            $optEasyling = get_option('easyling');
            $optEasyling['status'] = Easyling::STATUS_INSTALLED;
            update_option('easyling', $optEasyling);
            header("Location: " . get_admin_url(null, 'admin.php?page=easyling', 'admin'));
            die();
        }
        if (isset($_REQUEST['oauth_action'])) {
            try {
                if (is_callable(array(&$this, "oauth_" . $_REQUEST['oauth_action']))) {
                    require_once EASYLING_PATH . '/includes/OAuth/OAuth.php';
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
        if (get_option('easyling_id', false)) {
            header('Location: ' . get_admin_url(null, '', 'admin') . 'admin.php?page=easyling&oauth_action=authorization');
            die();
        }

        // step 1: generate consumer key and secret
        $blogurl = get_site_url();
        $parsedUrl = parse_url($blogurl);
        $req = new OAuthRequest('GET', $this->_oauth_endpoint . self::OAUTH_CREATEIDENT . "?siteName=" .
                        $parsedUrl['host']);
        $res = $req->curlit();
        try {
            if ($res['code'] == 200) {
                $response = json_decode($res['response'], true);
                if (isset($response['response']['identity'])
                        && isset($response['response']['identity']['consumerKey'])) {
                    $key = $response['response']['identity']['consumerKey'];
                    $secret = $response['response']['identity']['consumerSecret'];
                    update_option('easyling_id', array(
                        'consumer_key' => $key,
                        'consumer_secret' => $secret
                    ));
                    header('Location: ' . get_admin_url(null, '', 'admin') . 'admin.php?page=easyling&oauth_action=authorization');
                    die();
                } else {
                    throw new Exception("Error occured while getting identity");
                }
            } else {
                throw new Exception('Error while trying to get identity for blog');
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

    public function oauth_authorization() {
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . self::OAUTH_REQUESTTOKEN;
        $params = array(
            'oauth_callback' => get_admin_url(null, 'admin.php?page=easyling&oauth_action=accesstoken', 'admin')
        );
        extract(get_option('easyling_id'));

        $consumer = new OAuthConsumer($consumer_key, $consumer_secret, NULL);
        $req = OAuthRequest::from_consumer_and_token($consumer, NULL, "GET", $endpoint, $params);
        $req->sign_request($hmac_method, $consumer, NULL);
        $res = $req->curlit($req->to_url());
        try {
            if ($req->response['code'] == 200) {
                $response = $req->extract_params($req->response['response']);
                $_SESSION['oauth'] = $response;
                $parsedUrl = parse_url(get_site_url());
                header("Location: {$oauthServer}oauth/authorizeUser?oauth_token=" . $_SESSION['oauth']['oauth_token'] . '&siteName=' . $parsedUrl['host']);
            } else {
                throw new Exception('OAuth Error while getting Request Token');
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

    public function oauth_accesstoken() {
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . 'oauth/getAccessToken';
        $params = array(
            'oauth_verifier' => $_REQUEST['oauth_verifier'],
//        'oauth_token' => $_REQUEST['oauth_token']
        );
        extract(get_option('easyling_id'));
        $consumer = new OAuthConsumer($consumer_key, $consumer_secret, NULL);
        $token = new OAuthToken($_SESSION['oauth']['oauth_token'], $_SESSION['oauth']['oauth_token_secret']);
        $req = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
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
                if ($this->_redirURL !== null) {
                    unset($_SESSION['oauth_internal_redirect']);
                    header('Location: ' . $this->_redirURL);
                }else
                    header('Location: ' . get_admin_url(null, '', 'admin') . 'admin.php?page=easyling&oauth_action=retrieveprojects');
            } else {
                throw new Exception("Could not retrieve Access Token - " . $answer['message']);
            }
        } catch (Exception $e) {
            $this->easylingInstance->getPtm()->sendErrorReport($e, PTMException::LEVEL_ERROR, array_merge($_SESSION, $req->response));
        }
    }

    public function oauth_retrieveprojects() {
        // check if we have access_token
        if (!isset($_SESSION['oauth']['access_token'])) {
            // try to             
            $_SESSION['oauth_internal_redirect'] = get_admin_url(null, 'admin.php?page=easyling&oauth_action=retrieveprojects', 'admin');
            $this->oauth_authorization();
            die();
        }
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
        $oauthServer = $this->_oauth_endpoint;
        $endpoint = $oauthServer . 'ptm/projectList';
        $params = array(
        );
        extract(get_option('easyling_id'));
        $consumer = new OAuthConsumer($consumer_key, $consumer_secret, NULL);
        $token = new OAuthToken($_SESSION['oauth']['access_token'], $_SESSION['oauth']['access_token_secret']);
        $req = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
        $req->sign_request($hmac_method, $consumer, $token);
        $res = $req->curlit($req->to_url());
        try {
            if ($res['code'] == 200) {
                $decoded = json_decode($res['response'], true);
                foreach ($decoded['response']['projects'] as $p) {
                    // get available languages for project
                    $endpoint = $oauthServer . 'ptm/languageList';
                    $params = array(
                        'projectCode' => $p['code']
                    );
                    $reqLang = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $endpoint, $params);
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
                        $this->easylingInstance->getPtm()->getFrameworkService()->addAvailableProject($project);
                        // save project
                    } elseif ($resLang['code'] == 403) {
                        // no access to the project - ask for it
                        $_SESSION['oauth_internal_redirect'] = get_admin_url(null, 'admin.php?page=easyling&oauth_action=retrieveprojects', 'admin');
                        $this->oauth_authorization();
                        $this->easylingInstance->getPtm()->sendErrorReport(new Exception('403 from server for request: languageList'), PTMException::LEVEL_NOTICE, array_merge($_SESSION, $req->response));
                    } else {
                        throw new Exception("Could not retrieve language list for project: " . $p['name']);
                    }
                }
                // by now we should have all languages and also all languages
                // set the status
                $optEasyling = get_option('easyling');
                $optEasyling['status'] = Easyling::STATUS_AUTHED;
                update_option('easyling', $optEasyling);
                header('Location: ' . get_admin_url(null, '', 'admin') . 'admin.php?page=easyling');
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
        if (false !== strpos($_SERVER['QUERY_STRING'], 'page=easyling'))
            return;
        if (!current_user_can('manage_options'))
            return;

        echo $this->renderTemplate('notice_activation');
    }

    public function register_options() {
        register_setting('easyling_linking', 'easyling_linked_project');
        register_setting('easyling_linking', 'easyling_project_languages');
        register_setting('easyling_linking', 'easyling_multidomain');
        register_setting('easyling_consent', 'easyling_consent');
        wp_register_style('modal-basic', EASYLING_URL . '/admin/css/basic.css');
        wp_enqueue_style('modal-basic');
        wp_register_script('simplemodal', EASYLING_URL . '/admin/js/jquery.simplemodal.js', array('jquery'));
        wp_enqueue_script('simplemodal');

//        register_setting('easyling_settings', 'option_etc');
    }

    /**
     * Hook ran on admin_menu action
     */
    public function admin_menu_hook() {
        $this->_page_suffix_easyling = add_menu_page('Easyling', 'Easyling', 'manage_options', 'easyling', array(&$this, 'admin_menu_page'));
        // when it loads add the help tabs to this page
        add_action("load-{$this->_page_suffix_easyling}", array(&$this, 'admin_contextual_help_tabs_easyling'));
    }

    /**
     * Hook to display the admin menu page
     */
    public function admin_menu_page() {
        $ptm = $this->easylingInstance->getPTM();
        $projects = $ptm->getFrameworkService()->getAvailableProjects();
//        $locale = get_option('easyling_available_locales', array());
        $project_languages = get_option('easyling_project_languages', array());
        $linked = get_option('easyling_linked_project', false);
        $optEasyling = get_option('easyling');

        // see if we have the user's consent to report errors to us automatically
        $consent = get_option('easyling_consent', null);

        if (!$linked || empty($linked))
            $linked = false;
        else
            $linked = true;
        if (!is_array($project_languages)) {
            $project_languages = array();
        }
        echo $this->renderTemplate('easyling', array(
            'projects' => $projects,
//            'locale' => $locale,
            'project_languages' => $project_languages,
            'linked' => $linked,
            'md' => get_option('easyling_multidomain', false),
            'pcode' => get_option('easyling_linked_project', false),
            'easyling_status' => $optEasyling['status'],
            'consent' => $consent));
    }

    public function admin_contextual_help_tabs_easyling() {
        $screen = get_current_screen();
        if ($screen->id == $this->_page_suffix_easyling) {
            // remove overview tab
            $screen->remove_help_tabs();
            // add general tab
            $screen->add_help_tab(array(
                'id' => 'help_easyling_test',
                'title' => 'Easyling for Wordpress',
                'content' => $this->renderTemplate('help_intro')
            ));                      
        }
    }

    /**
     * Very simple way to separate business logic froma templates...
     * @param string $template template name
     * @param array $templateVars assoc array
     */
    protected function renderTemplate($template, array $templateVars = array()) {
        ob_start();
        extract($templateVars, EXTR_SKIP);
        require_once EASYLING_PATH . '/admin/templates/' . $template . '.php';
        return ob_get_clean();
    }

    /////////////////////
    // OAUTH METHODS   //
    /////////////////////
}