<?php

/*
  Plugin Name: Easyling for Wordpress
  Description: Easyling is a Website translation tool, suitable for DIY work.
  Version: 0.9.17
  Plugin URI: http://easyling.com
 */

if (!class_exists('Easyling')) {

    define('EASYLING_PATH', WP_PLUGIN_DIR . '/easyling-for-wp');
    define('EASYLING_URL', WP_PLUGIN_URL . '/easyling-for-wp');
    define('EASYLING_VERSION', '0.9.17');

    require_once dirname(__FILE__) . "/includes/ptm/KeyValueStorage/FileStorage.php";
    require_once dirname(__FILE__) . '/includes/ptm/PTM.php';
    require_once dirname(__FILE__) . '/includes/KeyValueStore/WPDbStorage.php';
    require_once dirname(__FILE__) . '/includes/KeyValueStore/WPOptionStorage.php';

    class Easyling {

        private static $_instance = null;
        protected $markup = null;

        /**
         * Status for when the plugin is installed but not yet hooked with
         * easyling
         */

        const STATUS_INSTALLED = 1;
        /**
         * Has been installed and the handshake is done with easyling
         */
        const STATUS_AUTHED = 2;

        /**
         * 5 letter target language locale
         * @var string
         */
        private $targetLocale = null;

        /**
         * 2 letter target language acronym for locale
         * This var is displayed eg. in the URLs
         * @var string
         */
        private $targetLanguage = '';

        /**
         * PTM
         * @var PTM
         */
        private $ptm;

        /**
         * the current url without language
         * @var string
         */
        private $originalRequestURI;
        private $useMultidomain = false;

        /**
         * Multidomain instance
         * @var Multidomain
         */
        public $multidomain = null;

        /**
         * Easyling settings
         * @var array
         */
        public $settings = null;

        /**
         * User allow us to send error reporting
         * @var bool
         */
        private $consent;

        /**
         * Upgrades to run
         * @var array
         */
        private $upgrades = array(
            '0.1.1' => '0.9.10',
            '0.9.10' => '0.9.11',
            '0.9.11' => '0.9.12',
        );

        /**
         * Get the instance of Easyling Plugin - Singleton pattern
         * @return Easyling
         */
        public static function getInstance() {
            if (self::$_instance === null) {
                self::$_instance = new Easyling();
            }
            return self::$_instance;
        }

        private function __construct() {

            // first get consent to correctly set in getPTM
            $this->consent = get_option('easyling_consent', false);

            // get settings
            $this->settings = get_option('easyling');

            // add non pivileged ajax
            add_action('wp_ajax_nopriv_easyling_oauth_push', array(
                &$this,
                'ajax_oauth_push'));
            add_action('wp_ajax_easyling_oauth_push', array(
                &$this,
                'ajax_oauth_push'));

            // admin only things
            if (is_admin()) {
                require_once EASYLING_PATH . '/admin/admin.php';
                // update detection
                add_action('admin_init', array($this, 'run_update_detection'), 0);
                $a = new Easyling_Admin($this);
            }

            // plugin activation / deletion
            $path = WP_PLUGIN_DIR . '/easyling-for-wp/easyling.php';
            register_activation_hook($path, array(
                &$this,
                'activation_hook'));
            register_deactivation_hook($path, array(
                &$this,
                'deactivation_hook'));

            // hooks
            if (!is_admin()) {
                if ($this->settings['status'] == self::STATUS_AUTHED) {
                    // very low prio
                    add_action('parse_query', array(
                        $this,
                        'detect_language'), 9999);
                }
                // create a new OB with callback
                add_action('init', array(
                    &$this,
                    'init_ob_start'));
            }

            // add custom URL structure
            add_filter('admin_init', array(&$this, 'flush_rewrite_rules'));

            // set all rewrite rules
	        add_filter('rewrite_rules_array', array(&$this, 'filter_rewrite_rules_all'));

            // some filters to display the proper links
            add_action('wp', array(&$this, 'wp'));

	        // add into head link rel="alternate" hreflang="es" href="http://es.example.com/" />
	        add_action('wp_head', array(&$this, 'add_alt_lang_html_links'));

	        // 'the_permalink' is skipped against duplicated rewrite
	        $link_types = array('page_link','post_link','category_link','year_link','month_link','tag_link',
		        'post_type_link','attachment_link','author_feed_link','author_link','comment_reply_link',
		        'day_link','feed_link','get_comment_author_link','get_comment_author_url_link',/*'the_permalink',*/
		        'term_link '
	        );
	        foreach ($link_types as $link_type) {
		        add_filter($link_type, array(&$this, 'filter_links'));
	        }
            add_filter('query_vars', array(&$this, 'queryVars'));

            if ($this->settings['status'] == self::STATUS_AUTHED) {
                // check for multi domain support
                $multidomain = get_option('easyling_multidomain', false);
                if ($multidomain && $multidomain['status'] == 'on') {
                    $this->useMultidomain = true;
                    // build config
                    $mdConfig = array(
                    );
                    foreach ($this->get_available_languages() as $lang) {
                        // strip http, https and www + /
                        if (strspn($lang, 'htps:/', 0, 8) < 7) {
                            $lang = 'http://' . $lang;
                        }
                        $p = parse_url($lang);
                        $uri = $p['host'];
                        $mdConfig[] = array(
                            'domain' => $uri,
                            'siteurl' => $lang,
                            'home' => $lang);
                    }
                    // include multisite component
                    require_once EASYLING_PATH . '/includes/Multidomain/Multidomain.php';
                    $this->multidomain = new MultiDomain($mdConfig);
                }
            }

            // enqueue style for frontend for language selector
            if (!is_admin()) {
                add_action('wp_enqueue_scripts', array($this, 'easyling_language_selector_floater'));
            }
        }

        public function easyling_language_selector_floater() {
            $enabled = get_option('easyling_language_selector', 'off') == 'on' ? true : false;

            if (!$enabled && !wp_style_is('easyling_register_stylesheet', 'enqueued'))
                return;

            wp_register_style('easyling-language-selector', WP_PLUGIN_URL . '/easyling-for-wp/css/easyling.css');
            wp_enqueue_style('easyling-language-selector');
            wp_register_script('easyling', EASYLING_URL . '/js/easyling.js');
            wp_enqueue_script('easyling');

            $localizationScript = easyling_get_translation_urls();
            $localizationScript['baseurl'] = EASYLING_URL;
            wp_localize_script('easyling', 'easyling_languages', $localizationScript);
        }

        public function filter_rewrite_rules_all($rules) {

	        $lang_pattern = $this->get_rewrite_rule_lang_pattern();

	        if (empty($lang_pattern))
		        return $rules;

	        $new_rules = array();

	        $available_languages = $this->get_available_languages();

	        // rule for root
	        $new_rules['(' . implode('|', $available_languages) . ')/{0,1}$'] = 'index.php?easyling=$matches[1]';

	        foreach ($rules as $pattern=>$rule) {
		        $this->filter_rewrite_rule($pattern, $rule, $lang_pattern);
		        $new_rules[$pattern] = $rule;
	        }

	        return $new_rules;
        }

        public function ajax_oauth_push() {
            $projectCode = $_REQUEST['projectCode'];
            $targetLanguage = $_REQUEST['targetLanguage'];
            $easylingResponse = file_get_contents("php://input");

            $this->getPtm()->getFrameworkService()->setProjectTranslationByELResponse($projectCode, $targetLanguage, $easylingResponse);
        }

        public function send_json($response) {
            // clean buffer
            ob_clean();
            if ((float) get_bloginfo('version') < 3.5) {
                @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
                echo json_encode($response);
                if (defined('DOING_AJAX') && DOING_AJAX)
                    wp_die();
                else
                    die;
            }else {
                wp_send_json($response);
            }
        }

        public function send_json_success($data = null) {
            if ((float) get_bloginfo('version') < 3.5) {
                $response = array(
                    'success' => true);
                if (isset($data))
                    $response['data'] = $data;

                $this->send_json($response);
            }else {
                wp_send_json_success($data);
            }
        }

        public function send_json_error($data = null) {
            if ((float) get_bloginfo('version') < 3.5) {
                $response = array(
                    'success' => false);
                if (isset($data))
                    $response['data'] = $data;
            }else {
                wp_send_json_error($data);
            }
        }

        public function queryVars($wpvar) {
            $wpvar[] = 'easyling';
            return $wpvar;
        }

        public function init_ob_start() {
            ob_start(array(
                &$this,
                'ob_callback'));
        }

        public function log($str) {
            trigger_error($str, E_USER_WARNING);
        }

	    /**
	     * Determines the language of the request
	     * @param WP_Query $wp_query
	     * @return void [] array of `targetLanguage`, `targetLocale`, `originalRequestURI`
	     */
        public function detect_language($wp_query) {

	        $checkResourceURL = false;

            if (!$this->multidomain) {

                // get language from URL or easyling query_var
                if (($language = $wp_query->get('easyling')) !== '') {
                    $language = trim($language, '/');
                    // we got easyling query var defined
                    $this->targetLanguage = $language;
                    $this->targetLocale = $this->matchLanguageToLocale($language);
                    $this->originalRequestURI = str_ireplace('/' . $this->targetLanguage . '/', '/', $_SERVER['REQUEST_URI']);
                } else {
	                // something screwed up
                }
            } else {
                $this->targetLocale = $this->matchDomainToLocale();
                $this->originalRequestURI = $_SERVER['REQUEST_URI'];
	            $checkResourceURL = true;
            }

	        if ($checkResourceURL) {
		        $pcode = get_option('easyling_linked_project');
		        if (!empty($pcode)) {
			        $p = $this->getPtm()->getFrameworkService()->getProjectByCode($pcode);
			        $fullOriginalURL = get_bloginfo('url').$this->originalRequestURI;
			        $redirURL = $this->getPtm()->getURLRedirection($p, $this->targetLocale, $fullOriginalURL);
			        if ($redirURL) {
			            wp_redirect($redirURL);
				        exit();
			        }
		        }
	        }
            return;
        }

        public function matchLanguageToLocale($language) {
            if (get_option('easyling_project_languages', false) !== false) {
                $linked_project_languages = get_option('easyling_project_languages');
                foreach ($linked_project_languages as $locale => $settings) {
                    if ($settings['used'] != 'on')
                        continue;
                    if ($settings['lngcode'] == $language) {
                        return $locale;
                    }
                }
            }
            return null;
        }

        public function matchDomainToLocale() {
            $domain = $_SERVER['HTTP_HOST'];
            $linked_project_languages = get_option('easyling_project_languages');
            foreach ($linked_project_languages as $locale => $settings) {
                if ($settings['used'] != 'on')
                    continue;
                // transform the domain a bit
                if (strspn($settings['domain'], 'htps:/', 0, 8) < 7) {
                    $settings['domain'] = 'http://' . $settings['domain'];
                }
                $p = parse_url($settings['domain']);
                $uri = $p['host'];
                if ($uri == $domain) {
                    return $locale;
                }
            }
            return null;
        }

        public function wp() {
            $this->flush_rewrite_rules();
        }

        public function flush_rewrite_rules() {
            /** @var WP_Rewrite $wp_rewrite */
            global $wp_rewrite;
            $wp_rewrite->flush_rules(true);
        }

        public function get_available_languages() {
            $available_languages = array(
            );
            $linked_project_languages = get_option('easyling_project_languages');
            if (!is_array($linked_project_languages))
                return array(
                );
            foreach ($linked_project_languages as $locale => $settings) {
                if ($settings['used'] != 'on')
                    continue;
                if (!$settings['lngcode'])
                    $available_languages[$locale] = $settings['domain'];
                else
                    $available_languages[$locale] = $settings['lngcode'];
            }
            return $available_languages;
        }

        public function filter_rewrite_rules_home($rewrite_rules) {

            $available_languages = $this->get_available_languages();
            if (empty($available_languages))
                return $rewrite_rules;

            global $wp_rewrite;

//	        error_log(json_encode($rewrite_rules));

            $new_rewrite_rules['(' . implode('|', $available_languages) . ')/{0,1}$'] = 'index.php?easyling=$matches[1]';
            $new_rewrite_rules += $this->filter_rewrite_rules($rewrite_rules);

            //print_r($new_rewrite_rules);
            return $new_rewrite_rules;
        }

	    private $rewrite_lang_pattern = null;

	    public function get_rewrite_rule_lang_pattern() {

		    if ($this->rewrite_lang_pattern !== null)
			    return $this->rewrite_lang_pattern;

		    $lang_pattern = "";
		    $available_languages = $this->get_available_languages();

		    if (empty($available_languages))
			    return $this->rewrite_lang_pattern="";

		    foreach ($available_languages as $lang) {
			    $lang_pattern .= $lang . "/|";
		    }

		    $this->rewrite_lang_pattern = $lang_pattern;

		    return $this->rewrite_lang_pattern;
	    }

	    public function filter_rewrite_rule(&$pattern, &$rule, $lang_pattern = null) {
		    if ($lang_pattern === null)
			    $lang_pattern = $this->get_rewrite_rule_lang_pattern();

		    /* if (strpos($rewrite_rule, "name") === FALSE &&
                  strpos($rewrite_rule, "pagename") === FALSE &&
                  strpos($rewrite_rule, "category_name") === FALSE &&
                  strpos($rewrite_rule, "year") === FALSE) {
                  $new_rewrite_rules[$pattern] = $rewrite_rule;
                  continue;
                  } */


		    if (strpos($rule, "attachment") !== FALSE) {
			    return;
		    }
		    $new_pattern = "($lang_pattern)" . $pattern;
		    $new_rewrite_rule = preg_replace_callback('/matches\[(\d*?)\]/', array(
				    &$this,
				    "_preg_replace_callback"), $rule) . '&easyling=$matches[1]';

		    $pattern = $new_pattern;
		    $rule = $new_rewrite_rule;
	    }

        public function filter_rewrite_rules($rewrite_rules) {

//	        error_log(json_encode($rewrite_rules));

            $new_rewrite_rules = array();

	        $lang_pattern = $this->get_rewrite_rule_lang_pattern();

	        if (empty($lang_pattern)) {
		        return $rewrite_rules;
	        }

            foreach ($rewrite_rules as $pattern => $rewrite_rule) {
                $this->filter_rewrite_rule($pattern, $rewrite_rule, $lang_pattern);
	            $new_rewrite_rules[$pattern] = $rewrite_rule;
            }

            return $new_rewrite_rules;
        }

        public function _preg_replace_callback($match) {
            return 'matches[' . ($match[1] + 1) . ']';
        }

        public function filter_links($permalink) {
            if ($this->targetLanguage == null)
                return $permalink;
            $url = get_bloginfo('url');
            $permaTmp = str_replace($url, '', $permalink);
            return $url . '/' . $this->targetLanguage . $permaTmp;
        }

        public function get_resource_map($remoteURL) {
            $home = home_url() . "/";
            // add start page, if we use directory mapping
            if (!$this->useMultidomain)
                return array(
                    $home => $home . $this->targetLanguage . "/");
            return array(
            );
        }

        public function translate($projectCode, $remoteURL, $targetLanguage, $htmlContent = null) {
            $ptm = $this->getPtm();
            $p = $ptm->getFrameworkService()->getProjectByCode($projectCode);

            // TODO: add translation cache to speed up page load time
            $translated = $ptm->translateProjectPage($p, $remoteURL, $htmlContent, $targetLanguage, $this->get_resource_map($remoteURL));
            return $translated;
        }

	    public function isResponseTranslatable($buffer) {
		    $ptm = $this->getPtm();

		    return $ptm->isResponseTranslatable(headers_list(), $buffer);
	    }

	    public function getAlternativeLangURLs() {
		    // TODO: skip currently displayed language
		    $langURLs = easyling_get_translation_urls(false);
		    $localeURLs = array();
		    $langCodeURLs = array();
		    foreach ($langURLs['translations'] as $locale=>$langData) {
			    $url = $langData['url'];

			    list($langCode, $countryCode) = explode('-', $locale, 2);
			    $localeURLs[$locale] = $url;
			    if (!empty($countryCode)) {
				    $langCodeURLs[$langCode] = $url;
			    }
		    }

		    $sourceLocales = get_option('easyling_source_langs');
		    $pcode = get_option('easyling_linked_project');

		    $urlMap = array_merge($localeURLs, $langCodeURLs);

		    $urlMap['x-default'] = $urlMap[$sourceLocales[$pcode]];

		    return $urlMap;
	    }

        public function add_alt_lang_html_links() {
	        $urlMap = $this->getAlternativeLangURLs();

	        foreach ($urlMap as $langCode=>$url) {
		        echo '<link rel="alternate" hreflang="'.htmlentities($langCode).'" href="'.htmlentities($url).'" />'."\n";
	        }
        }

        public function ob_callback($buffer) {
            // this is the place to modify the markup
            if ($this->isResponseTranslatable($buffer) && $this->targetLocale !== null) {
                $pcode = get_option('easyling_linked_project');

                // TODO, replace the multidomain domain with original
                $original_url = site_url($this->originalRequestURI);
                if ($this->useMultidomain) {
                    // we need to get the original URL
                    $original_url = str_replace($_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['HTTP_HOST'], $this->settings['canonical_url'], $original_url);
                } else {
                    
                }

                $buffer = $this->translate($pcode, $original_url, $this->targetLocale, $buffer);
            }
            return $buffer;
        }

        /**
         * Hook ran when the plugin is activated
         */
        public function activation_hook($network_wide) {

            if ($network_wide) {
                echo '<div style="font-family: Arial; font-size: 12px;">The Easyling Plugin terminated the activation because the PHP installation misses the following extensions:<br />';
                echo 'Easyling for WP cannot be activated network wide.';
                echo '</div>';
                die();
            }

            // first run a few checks if we have extensions loaded
            $extensions_check = false;
            if (extension_loaded('openssl') &&
                    extension_loaded('curl') &&
                    (extension_loaded('iconv') && extension_loaded('mbstring') )
            ) {
                $extensions_check = true;
                // check encoding conversion
                // on mac iconv screws up, mbstring works
                $str = 'űáé';
                $converted = iconv('UTF-8', 'UTF-8//TRANSLIT', $str);
                if (empty($converted)) {
                    $converted = mb_convert_encoding($str, 'UTF-8');
                    if (strlen($converted) != strlen($str)) {
                        $extensions_check = false;
                    }
                }
            }

            if ($extensions_check === false) {
                echo '<div style="font-family: Arial; font-size: 12px;">The Easyling Plugin terminated the activation because the PHP installation misses the following extensions:<br />';
                echo '<ul>';
                echo extension_loaded('openssl') ? '' : '<li>openssl</li>';
                echo extension_loaded('curl') ? '' : '<li>curl</li>';
                echo extension_loaded('iconv') ? '' : '<li>iconv</li>';
                echo extension_loaded('mbstring') ? '' : '<li>mbstring</li>';
                echo '</ul></div>';
                die();
            }

            // create table
            /** @var wpdb $wpdb */
            global $wpdb;

            $query = 'CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . 'easyling` ( ' .
                    '`easyling_key` varchar(300) CHARACTER SET utf8 NOT NULL, ' .
                    '`easyling_value` mediumblob NOT NULL, ' .
//                    ', `easyling_project` varchar(32) CHARACTER SET utf8 NOT NULL, ' .
                    'UNIQUE KEY `uq_project_key` (`easyling_key`) ' .
                    ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
            $res = $wpdb->query($query);
            if ($res === false) {
                // handle error for creating table                
                throw new Exception("Error while creating table: " . mysql_error());
            }

            // add wp_option for easyling
            if ($this->settings === null || $this->settings === false) {
                // plugin has not yet been installed ever or was completly removed
                // clean install
                update_option('easyling', array(
                    'version' => EASYLING_VERSION,
                    'status' => self::STATUS_INSTALLED,
                    'key' => sha1(date('Y-m-d h:m', time())),
                    'default_lang' => 'en',
                    'canonical_url' => get_bloginfo('url'),
                    'popup_shown' => false
                ));
            } else {
                /*
                 * This only runs if the user manually deactivates the plugins
                 * updates the files and then reactivates the plugin
                 *
                 * So to say manual update
                 */
                add_action('load-plugins.php', array(&$this, 'prepare_upgrade_admin_notices'));
                $this->run_updates();
                // end of manual update code
            }

            // flush rewrite rules
            /** @var WP_Rewrite $wp_rewrite */
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }

        public function run_update_detection() {
            $plugins = get_plugins();
            // find the easyling plugin
            // no need to for checks as the plugin's name and this file's name won't
            // change and this code won't run if the plugin is not active
            $easyling_plugin = $plugins['easyling-for-wp/easyling.php'];

            $active_version = $easyling_plugin['Version'];
            $db_version = $this->settings['version'];
            // seems like that we did an update but for some reason the update code
            // did not run, so make sure we run it now
            if (version_compare($active_version, $db_version, '>')) {
                // alright, update happened, but DB is old, run the upgrade
                $this->run_updates();
            }
        }

        protected function run_updates() {
            $opt_easyling = $this->settings;
            // plugin was already installed and it's time to upgrade
            $old_version = $opt_easyling['version'];

            foreach ($this->upgrades as $old => $new) {
                if (version_compare($old_version, $old) <= 0) {
                    $method_name = "update_" . str_replace('.', '', $old) . "_" . str_replace('.', '', $new);
                    if (method_exists($this, $method_name)) {
                        $message = call_user_func(array($this, $method_name));
                        $opt_easyling['version'] = $new;
                        $opt_easyling['updates'][$new] = array(
                            'message' => $message,
                            'acted_upon' => false);
                        if (method_exists($this, $method_name . '_callback')) {
                            $opt_easyling['updates'][$new]['callback'] = $method_name . '_callback';
                        }
                        update_option('easyling', $opt_easyling);
                        $old_version = $new;
                    }
                }
            }
            // make sure to get the most current DB version of the settings
            $this->settings = get_option('easyling');
        }

        /**
         * Run the update of 011-0910 version and return the message that should be displayed on plugins page
         * @return string
         */
        public function update_011_0910() {
            $markup = 'Easyling for WP has been upgarded to <strong>0.9.10</strong><br />';
            $markup.= '<div style="padding: 0px 30px;">It is <strong>Important</strong> to ' .
                    'update the project list of Easyling projects to ensure proper functioning of the plugin!';
            if ($this->settings['status'] == self::STATUS_AUTHED) {
                $markup.= '<br />You can also do this by clicking here: <a href="' . get_admin_url() . 'admin.php?page=easyling&oauth_action=updateprojectlist">Update project list</a>';
            }
            $markup.="</div>";
            return $markup;
        }

        /**
         * Callback to check if required user action has been executed and if so, remove the admin notice
         * @param array $easyling
         * @return array
         */
        public function update_011_0910_callback($easyling) {
            // check if the user has already 'updated' the project list
            $langs = get_option('easyling_source_langs', false);
            // if so remove the option notification
            if ($langs !== false) {
                unset($easyling['updates']['0.9.10']);
            }

            return $easyling;
        }

        /**
         * Run the update of 0910-0911 version and return the message that should be displayed on plugins page
         * @return string
         */
        public function update_0910_0911() {
            $markup = 'Easyling for WP has been upgarded to <strong>0.9.11</strong>';
            return $markup;
        }

        /**
         * @param array $easyling
         * @return array
         */
        public function update_0910_0911_callback($easyling) {
            unset($easyling['updates']['0.9.11']);
            return $easyling;
        }
        /**
         * Run the update of 0911-0912 version and return the message that should be displayed on plugins page
         * @return string
         */
        public function update_0911_0912() {
            $markup = 'Easyling for WP has been upgarded to <strong>0.9.12</strong>';
            return $markup;
        }

        /**
         * @param array $easyling
         * @return array
         */
        public function update_0911_0912_callback($easyling) {
            unset($easyling['updates']['0.9.12']);
            return $easyling;
        }

        /**
         * Hook ran when the plugin is deactivated
         */
        public function deactivation_hook() {
            // as of version 0.9.10 all "clean up" calls have been moved to
            // uninstall to make sure that manual updates do not touch the DB
            // which is desired
        }

        /**
         * Hook ran when the plugin is completly removed
         * The uninstall.php file is used instead
         * @deprecated since version 0.9.10
         */
        public function uninstall_hook() {
            
        }

        /**
         * @return PTM
         */
        public function getPtm() {
            if ($this->ptm === null) {
                $this->ptm = PTM::get();
                $this->ptm->enableErrorReporting($this->consent);
                $projectPageStorage = new WPDbStorage(KeyValueStorage::ITEMTYPE_PROJECTPAGE);
                $optionStorage = new WPOptionStorage(KeyValueStorage::ITEMTYPE_OPTION);
                $sm = $this->ptm->getStorageManager();
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_PROJECTPAGE, $projectPageStorage);
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_OPTION, $optionStorage);
            }
            return $this->ptm;
        }

        /**
         * Get the original URL of the page that is being translatd
         *
         * @return string Original site's url that is translated
         */
        public function getOriginalTranslateURL() {
            return $this->originalRequestURI;
        }

        /**
         * Whether multidomain is used or not
         *
         * @return bool TRUE or FALSE
         */
        public function isMultiDomainUsed() {
            return $this->useMultidomain;
        }

        /**
         * Retrieves a list of languages from locales in ISO639-1 (2 letter codes such as en,de,hu)
         * @param array $locales
         * @since 0.9.11 
         * @return array array of languages available 
         * @throws RuntimeException 
         */
        public function filter_available_locales($locales) {
            $ret = $locales;
            $pcode = get_option('easyling_linked_project');
            $sources = get_option('easyling_source_langs', array());
            if (isset($sources[$pcode])) {
	            $ret[$sources[$pcode]] = '';
/*                if (strpos($sources[$pcode], 'en') === 0) {
                    $ret[strtolower(substr($sources[$pcode], 3, 2))] = '';
                } else {
                    $ret[substr($sources[$pcode], 0, 2)] = '';
                }
  */          } else {
                // 0.9.10
                throw new RuntimeException("Please update the project list on the admin UI to correct this error message.");
            }
            return $ret;
        }

    }

    /**
     * Retrieve the flag coordinates of language
     *
     * @param string $filter 2 letter country code of flag such as: de/hu. Careful with english: US/GB etc is used
     * @since 0.9.10
     * @return array Array of all flag coords or filtered coordinates | NULL if nothing has been found
     */
    function easyling_flag_coordinates($filter = null) {
        $coords = unserialize('a:234:{s:9:"_abkhazia";a:2:{s:1:"x";i:0;s:1:"y";i:0;}s:13:"_commonwealth";a:2:{s:1:"x";i:0;s:1:"y";i:32;}s:15:"_european-union";a:2:{s:1:"x";i:0;s:1:"y";i:64;}s:7:"_kosovo";a:2:{s:1:"x";i:0;s:1:"y";i:96;}s:17:"_nagorno-karabakh";a:2:{s:1:"x";i:0;s:1:"y";i:128;}s:16:"_northern-cyprus";a:2:{s:1:"x";i:0;s:1:"y";i:160;}s:9:"_scotland";a:2:{s:1:"x";i:0;s:1:"y";i:192;}s:11:"_somaliland";a:2:{s:1:"x";i:0;s:1:"y";i:224;}s:14:"_south-ossetia";a:2:{s:1:"x";i:0;s:1:"y";i:256;}s:6:"_wales";a:2:{s:1:"x";i:0;s:1:"y";i:288;}s:2:"ad";a:2:{s:1:"x";i:0;s:1:"y";i:320;}s:2:"ae";a:2:{s:1:"x";i:0;s:1:"y";i:352;}s:2:"af";a:2:{s:1:"x";i:0;s:1:"y";i:384;}s:2:"ag";a:2:{s:1:"x";i:32;s:1:"y";i:0;}s:2:"ai";a:2:{s:1:"x";i:32;s:1:"y";i:32;}s:2:"al";a:2:{s:1:"x";i:32;s:1:"y";i:64;}s:2:"am";a:2:{s:1:"x";i:32;s:1:"y";i:96;}s:2:"an";a:2:{s:1:"x";i:32;s:1:"y";i:128;}s:2:"ao";a:2:{s:1:"x";i:32;s:1:"y";i:160;}s:2:"aq";a:2:{s:1:"x";i:32;s:1:"y";i:192;}s:2:"ar";a:2:{s:1:"x";i:32;s:1:"y";i:224;}s:2:"as";a:2:{s:1:"x";i:32;s:1:"y";i:256;}s:2:"at";a:2:{s:1:"x";i:32;s:1:"y";i:288;}s:2:"au";a:2:{s:1:"x";i:32;s:1:"y";i:320;}s:2:"aw";a:2:{s:1:"x";i:32;s:1:"y";i:352;}s:2:"ax";a:2:{s:1:"x";i:32;s:1:"y";i:384;}s:2:"az";a:2:{s:1:"x";i:64;s:1:"y";i:0;}s:2:"ba";a:2:{s:1:"x";i:64;s:1:"y";i:32;}s:2:"bb";a:2:{s:1:"x";i:64;s:1:"y";i:64;}s:2:"bd";a:2:{s:1:"x";i:64;s:1:"y";i:96;}s:2:"be";a:2:{s:1:"x";i:64;s:1:"y";i:128;}s:2:"bf";a:2:{s:1:"x";i:64;s:1:"y";i:160;}s:2:"bg";a:2:{s:1:"x";i:64;s:1:"y";i:192;}s:2:"bh";a:2:{s:1:"x";i:64;s:1:"y";i:224;}s:2:"bi";a:2:{s:1:"x";i:64;s:1:"y";i:256;}s:2:"bj";a:2:{s:1:"x";i:64;s:1:"y";i:288;}s:2:"bl";a:2:{s:1:"x";i:64;s:1:"y";i:320;}s:2:"bm";a:2:{s:1:"x";i:64;s:1:"y";i:352;}s:2:"bn";a:2:{s:1:"x";i:64;s:1:"y";i:384;}s:2:"bo";a:2:{s:1:"x";i:96;s:1:"y";i:0;}s:2:"br";a:2:{s:1:"x";i:96;s:1:"y";i:32;}s:2:"bs";a:2:{s:1:"x";i:96;s:1:"y";i:64;}s:2:"bt";a:2:{s:1:"x";i:96;s:1:"y";i:96;}s:2:"bw";a:2:{s:1:"x";i:96;s:1:"y";i:128;}s:2:"by";a:2:{s:1:"x";i:96;s:1:"y";i:160;}s:2:"bz";a:2:{s:1:"x";i:96;s:1:"y";i:192;}s:2:"ca";a:2:{s:1:"x";i:96;s:1:"y";i:224;}s:2:"cd";a:2:{s:1:"x";i:96;s:1:"y";i:256;}s:2:"cf";a:2:{s:1:"x";i:96;s:1:"y";i:288;}s:2:"cg";a:2:{s:1:"x";i:96;s:1:"y";i:320;}s:2:"ch";a:2:{s:1:"x";i:96;s:1:"y";i:352;}s:2:"ci";a:2:{s:1:"x";i:96;s:1:"y";i:384;}s:2:"cl";a:2:{s:1:"x";i:128;s:1:"y";i:0;}s:2:"cm";a:2:{s:1:"x";i:128;s:1:"y";i:32;}s:2:"cn";a:2:{s:1:"x";i:128;s:1:"y";i:64;}s:2:"co";a:2:{s:1:"x";i:128;s:1:"y";i:96;}s:2:"cr";a:2:{s:1:"x";i:128;s:1:"y";i:128;}s:2:"cu";a:2:{s:1:"x";i:128;s:1:"y";i:160;}s:2:"cv";a:2:{s:1:"x";i:128;s:1:"y";i:192;}s:2:"cy";a:2:{s:1:"x";i:128;s:1:"y";i:224;}s:2:"cz";a:2:{s:1:"x";i:128;s:1:"y";i:256;}s:2:"de";a:2:{s:1:"x";i:128;s:1:"y";i:288;}s:2:"dj";a:2:{s:1:"x";i:128;s:1:"y";i:320;}s:2:"dk";a:2:{s:1:"x";i:128;s:1:"y";i:352;}s:2:"dm";a:2:{s:1:"x";i:128;s:1:"y";i:384;}s:2:"do";a:2:{s:1:"x";i:160;s:1:"y";i:0;}s:2:"dz";a:2:{s:1:"x";i:192;s:1:"y";i:0;}s:2:"ec";a:2:{s:1:"x";i:224;s:1:"y";i:0;}s:2:"ee";a:2:{s:1:"x";i:256;s:1:"y";i:0;}s:2:"eg";a:2:{s:1:"x";i:288;s:1:"y";i:0;}s:2:"eh";a:2:{s:1:"x";i:320;s:1:"y";i:0;}s:2:"er";a:2:{s:1:"x";i:352;s:1:"y";i:0;}s:2:"es";a:2:{s:1:"x";i:384;s:1:"y";i:0;}s:2:"et";a:2:{s:1:"x";i:416;s:1:"y";i:0;}s:2:"fi";a:2:{s:1:"x";i:448;s:1:"y";i:0;}s:2:"fj";a:2:{s:1:"x";i:480;s:1:"y";i:0;}s:2:"fk";a:2:{s:1:"x";i:512;s:1:"y";i:0;}s:2:"fm";a:2:{s:1:"x";i:544;s:1:"y";i:0;}s:2:"fo";a:2:{s:1:"x";i:160;s:1:"y";i:32;}s:2:"fr";a:2:{s:1:"x";i:160;s:1:"y";i:64;}s:2:"ga";a:2:{s:1:"x";i:160;s:1:"y";i:96;}s:2:"gb";a:2:{s:1:"x";i:160;s:1:"y";i:128;}s:2:"gd";a:2:{s:1:"x";i:160;s:1:"y";i:160;}s:2:"ge";a:2:{s:1:"x";i:160;s:1:"y";i:192;}s:2:"gg";a:2:{s:1:"x";i:160;s:1:"y";i:224;}s:2:"gh";a:2:{s:1:"x";i:160;s:1:"y";i:256;}s:2:"gl";a:2:{s:1:"x";i:160;s:1:"y";i:288;}s:2:"gm";a:2:{s:1:"x";i:160;s:1:"y";i:320;}s:2:"gn";a:2:{s:1:"x";i:160;s:1:"y";i:352;}s:2:"gq";a:2:{s:1:"x";i:160;s:1:"y";i:384;}s:2:"gr";a:2:{s:1:"x";i:192;s:1:"y";i:32;}s:2:"gs";a:2:{s:1:"x";i:224;s:1:"y";i:32;}s:2:"gt";a:2:{s:1:"x";i:256;s:1:"y";i:32;}s:2:"gu";a:2:{s:1:"x";i:288;s:1:"y";i:32;}s:2:"gw";a:2:{s:1:"x";i:320;s:1:"y";i:32;}s:2:"gy";a:2:{s:1:"x";i:352;s:1:"y";i:32;}s:2:"hk";a:2:{s:1:"x";i:384;s:1:"y";i:32;}s:2:"hn";a:2:{s:1:"x";i:416;s:1:"y";i:32;}s:2:"hr";a:2:{s:1:"x";i:448;s:1:"y";i:32;}s:2:"ht";a:2:{s:1:"x";i:480;s:1:"y";i:32;}s:2:"hu";a:2:{s:1:"x";i:512;s:1:"y";i:32;}s:2:"id";a:2:{s:1:"x";i:544;s:1:"y";i:32;}s:2:"ie";a:2:{s:1:"x";i:192;s:1:"y";i:64;}s:2:"il";a:2:{s:1:"x";i:192;s:1:"y";i:96;}s:2:"im";a:2:{s:1:"x";i:192;s:1:"y";i:128;}s:2:"in";a:2:{s:1:"x";i:192;s:1:"y";i:160;}s:2:"iq";a:2:{s:1:"x";i:192;s:1:"y";i:192;}s:2:"ir";a:2:{s:1:"x";i:192;s:1:"y";i:224;}s:2:"is";a:2:{s:1:"x";i:192;s:1:"y";i:256;}s:2:"it";a:2:{s:1:"x";i:192;s:1:"y";i:288;}s:2:"je";a:2:{s:1:"x";i:192;s:1:"y";i:320;}s:2:"jm";a:2:{s:1:"x";i:192;s:1:"y";i:352;}s:2:"jo";a:2:{s:1:"x";i:192;s:1:"y";i:384;}s:2:"jp";a:2:{s:1:"x";i:224;s:1:"y";i:64;}s:2:"ke";a:2:{s:1:"x";i:256;s:1:"y";i:64;}s:2:"kg";a:2:{s:1:"x";i:288;s:1:"y";i:64;}s:2:"kh";a:2:{s:1:"x";i:320;s:1:"y";i:64;}s:2:"ki";a:2:{s:1:"x";i:352;s:1:"y";i:64;}s:2:"km";a:2:{s:1:"x";i:384;s:1:"y";i:64;}s:2:"kn";a:2:{s:1:"x";i:416;s:1:"y";i:64;}s:2:"kp";a:2:{s:1:"x";i:448;s:1:"y";i:64;}s:2:"kr";a:2:{s:1:"x";i:480;s:1:"y";i:64;}s:2:"kw";a:2:{s:1:"x";i:512;s:1:"y";i:64;}s:2:"ky";a:2:{s:1:"x";i:544;s:1:"y";i:64;}s:2:"kz";a:2:{s:1:"x";i:224;s:1:"y";i:96;}s:2:"la";a:2:{s:1:"x";i:224;s:1:"y";i:128;}s:2:"lb";a:2:{s:1:"x";i:224;s:1:"y";i:160;}s:2:"lc";a:2:{s:1:"x";i:224;s:1:"y";i:192;}s:2:"li";a:2:{s:1:"x";i:224;s:1:"y";i:224;}s:2:"lk";a:2:{s:1:"x";i:224;s:1:"y";i:256;}s:2:"lr";a:2:{s:1:"x";i:224;s:1:"y";i:288;}s:2:"ls";a:2:{s:1:"x";i:224;s:1:"y";i:320;}s:2:"lt";a:2:{s:1:"x";i:224;s:1:"y";i:352;}s:2:"lu";a:2:{s:1:"x";i:224;s:1:"y";i:384;}s:2:"lv";a:2:{s:1:"x";i:256;s:1:"y";i:96;}s:2:"ly";a:2:{s:1:"x";i:288;s:1:"y";i:96;}s:2:"ma";a:2:{s:1:"x";i:320;s:1:"y";i:96;}s:2:"mc";a:2:{s:1:"x";i:352;s:1:"y";i:96;}s:2:"md";a:2:{s:1:"x";i:384;s:1:"y";i:96;}s:2:"me";a:2:{s:1:"x";i:416;s:1:"y";i:96;}s:2:"mg";a:2:{s:1:"x";i:448;s:1:"y";i:96;}s:2:"mh";a:2:{s:1:"x";i:480;s:1:"y";i:96;}s:2:"mk";a:2:{s:1:"x";i:512;s:1:"y";i:96;}s:2:"ml";a:2:{s:1:"x";i:544;s:1:"y";i:96;}s:2:"mm";a:2:{s:1:"x";i:256;s:1:"y";i:128;}s:2:"mn";a:2:{s:1:"x";i:256;s:1:"y";i:160;}s:2:"mo";a:2:{s:1:"x";i:256;s:1:"y";i:192;}s:2:"mp";a:2:{s:1:"x";i:256;s:1:"y";i:224;}s:2:"mr";a:2:{s:1:"x";i:256;s:1:"y";i:256;}s:2:"ms";a:2:{s:1:"x";i:256;s:1:"y";i:288;}s:2:"mt";a:2:{s:1:"x";i:256;s:1:"y";i:320;}s:2:"mu";a:2:{s:1:"x";i:256;s:1:"y";i:352;}s:2:"mv";a:2:{s:1:"x";i:256;s:1:"y";i:384;}s:2:"mw";a:2:{s:1:"x";i:288;s:1:"y";i:128;}s:2:"mx";a:2:{s:1:"x";i:320;s:1:"y";i:128;}s:2:"my";a:2:{s:1:"x";i:352;s:1:"y";i:128;}s:2:"mz";a:2:{s:1:"x";i:384;s:1:"y";i:128;}s:2:"na";a:2:{s:1:"x";i:416;s:1:"y";i:128;}s:2:"ne";a:2:{s:1:"x";i:448;s:1:"y";i:128;}s:2:"nf";a:2:{s:1:"x";i:480;s:1:"y";i:128;}s:2:"ng";a:2:{s:1:"x";i:512;s:1:"y";i:128;}s:2:"ni";a:2:{s:1:"x";i:544;s:1:"y";i:128;}s:2:"nl";a:2:{s:1:"x";i:288;s:1:"y";i:160;}s:2:"no";a:2:{s:1:"x";i:288;s:1:"y";i:192;}s:2:"np";a:2:{s:1:"x";i:288;s:1:"y";i:224;}s:2:"nr";a:2:{s:1:"x";i:288;s:1:"y";i:256;}s:2:"nz";a:2:{s:1:"x";i:288;s:1:"y";i:288;}s:2:"om";a:2:{s:1:"x";i:288;s:1:"y";i:320;}s:2:"pa";a:2:{s:1:"x";i:288;s:1:"y";i:352;}s:2:"pe";a:2:{s:1:"x";i:288;s:1:"y";i:384;}s:2:"pg";a:2:{s:1:"x";i:320;s:1:"y";i:160;}s:2:"ph";a:2:{s:1:"x";i:352;s:1:"y";i:160;}s:2:"pk";a:2:{s:1:"x";i:384;s:1:"y";i:160;}s:2:"pl";a:2:{s:1:"x";i:416;s:1:"y";i:160;}s:2:"pn";a:2:{s:1:"x";i:448;s:1:"y";i:160;}s:2:"pr";a:2:{s:1:"x";i:480;s:1:"y";i:160;}s:2:"ps";a:2:{s:1:"x";i:512;s:1:"y";i:160;}s:2:"pt";a:2:{s:1:"x";i:544;s:1:"y";i:160;}s:2:"pw";a:2:{s:1:"x";i:320;s:1:"y";i:192;}s:2:"py";a:2:{s:1:"x";i:320;s:1:"y";i:224;}s:2:"qa";a:2:{s:1:"x";i:320;s:1:"y";i:256;}s:2:"ro";a:2:{s:1:"x";i:320;s:1:"y";i:288;}s:2:"rs";a:2:{s:1:"x";i:320;s:1:"y";i:320;}s:2:"ru";a:2:{s:1:"x";i:320;s:1:"y";i:352;}s:2:"rw";a:2:{s:1:"x";i:320;s:1:"y";i:384;}s:2:"sa";a:2:{s:1:"x";i:352;s:1:"y";i:192;}s:2:"sb";a:2:{s:1:"x";i:384;s:1:"y";i:192;}s:2:"sc";a:2:{s:1:"x";i:416;s:1:"y";i:192;}s:2:"sd";a:2:{s:1:"x";i:448;s:1:"y";i:192;}s:2:"se";a:2:{s:1:"x";i:480;s:1:"y";i:192;}s:2:"sg";a:2:{s:1:"x";i:512;s:1:"y";i:192;}s:2:"sh";a:2:{s:1:"x";i:544;s:1:"y";i:192;}s:2:"si";a:2:{s:1:"x";i:352;s:1:"y";i:224;}s:2:"sk";a:2:{s:1:"x";i:352;s:1:"y";i:256;}s:2:"sl";a:2:{s:1:"x";i:352;s:1:"y";i:288;}s:2:"sm";a:2:{s:1:"x";i:352;s:1:"y";i:320;}s:2:"sn";a:2:{s:1:"x";i:352;s:1:"y";i:352;}s:2:"so";a:2:{s:1:"x";i:352;s:1:"y";i:384;}s:2:"sr";a:2:{s:1:"x";i:384;s:1:"y";i:224;}s:2:"st";a:2:{s:1:"x";i:416;s:1:"y";i:224;}s:2:"sv";a:2:{s:1:"x";i:448;s:1:"y";i:224;}s:2:"sy";a:2:{s:1:"x";i:480;s:1:"y";i:224;}s:2:"sz";a:2:{s:1:"x";i:512;s:1:"y";i:224;}s:2:"tc";a:2:{s:1:"x";i:544;s:1:"y";i:224;}s:2:"td";a:2:{s:1:"x";i:384;s:1:"y";i:256;}s:2:"tg";a:2:{s:1:"x";i:384;s:1:"y";i:288;}s:2:"th";a:2:{s:1:"x";i:384;s:1:"y";i:320;}s:2:"tj";a:2:{s:1:"x";i:384;s:1:"y";i:352;}s:2:"tl";a:2:{s:1:"x";i:384;s:1:"y";i:384;}s:2:"tm";a:2:{s:1:"x";i:416;s:1:"y";i:256;}s:2:"tn";a:2:{s:1:"x";i:448;s:1:"y";i:256;}s:2:"to";a:2:{s:1:"x";i:480;s:1:"y";i:256;}s:2:"tr";a:2:{s:1:"x";i:512;s:1:"y";i:256;}s:2:"tt";a:2:{s:1:"x";i:544;s:1:"y";i:256;}s:2:"tv";a:2:{s:1:"x";i:416;s:1:"y";i:288;}s:2:"tw";a:2:{s:1:"x";i:416;s:1:"y";i:320;}s:2:"tz";a:2:{s:1:"x";i:416;s:1:"y";i:352;}s:2:"ua";a:2:{s:1:"x";i:416;s:1:"y";i:384;}s:2:"ug";a:2:{s:1:"x";i:448;s:1:"y";i:288;}s:2:"us";a:2:{s:1:"x";i:480;s:1:"y";i:288;}s:2:"uy";a:2:{s:1:"x";i:512;s:1:"y";i:288;}s:2:"uz";a:2:{s:1:"x";i:544;s:1:"y";i:288;}s:2:"vc";a:2:{s:1:"x";i:448;s:1:"y";i:320;}s:2:"ve";a:2:{s:1:"x";i:448;s:1:"y";i:352;}s:2:"vg";a:2:{s:1:"x";i:448;s:1:"y";i:384;}s:2:"vi";a:2:{s:1:"x";i:480;s:1:"y";i:320;}s:2:"vn";a:2:{s:1:"x";i:512;s:1:"y";i:320;}s:2:"vu";a:2:{s:1:"x";i:544;s:1:"y";i:320;}s:2:"ws";a:2:{s:1:"x";i:480;s:1:"y";i:352;}s:2:"ye";a:2:{s:1:"x";i:480;s:1:"y";i:384;}s:2:"za";a:2:{s:1:"x";i:512;s:1:"y";i:352;}s:2:"zm";a:2:{s:1:"x";i:544;s:1:"y";i:352;}s:2:"zw";a:2:{s:1:"x";i:512;s:1:"y";i:384;}s:2:"ct";a:2:{s:1:"x";i:384;s:1:"y";i:0;}}');
        if ($filter == null)
            return $coords;
        if (isset($coords[$filter]))
            return $coords[$filter];
        return null;
    }

    /**
     * Retrieve the translation URLs and flag coordinates
     *
     * @global Easyling $easyling_instance
     * @since 0.9.10
     * @return array Multi-dimensional array of data for translations
     */
    function easyling_get_translation_urls($setCoordinates = true) {
	    /** @var Easyling $easyling_instance */
        global $easyling_instance;
        $locales = easyling_get_locales();
        $origURL = $easyling_instance->getOriginalTranslateURL();
	    if ($setCoordinates)
            $coordinates = easyling_flag_coordinates();
	    else
		    $coordinates = array();

        $translationURLs = array(
            'translations' => array()
        );

        foreach ($locales as $locale => $v) {
            $canonical = $easyling_instance->settings['canonical_url'];
            if ($easyling_instance->isMultiDomainUsed()) {
                // multidomain is used such as de.example.com or hu.example.com
                $url = null;
                if (!empty($v)) {
                    $url = ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $v . $origURL;
                } else {
                    $url = $canonical . $origURL;
                }
            } else {
                $url = null;
	            // the original language is displayed
                if ($origURL == null) {
	                if (empty($v))
		                $url = $canonical . $_SERVER['REQUEST_URI'];
                    else
	                    $url = $canonical . '/' . $v . $_SERVER['REQUEST_URI'];
                }
                // the translated site displayed
                else {
                    // url prefixes are used such as /hu/ or /de/
                    $url = empty($v) ? ($canonical . $origURL) : ($canonical . '/' . $v . $origURL);
                }
            }

	        list($langCode, $countryCode) = explode("-", $locale, 2);
	        $countryCode = strtolower($countryCode);
	        $localeData = array('url' => $url);
	        if ($setCoordinates) {
		        $localeData['coords'] = $coordinates[$countryCode];
	        }

            $translationURLs['translations'][$locale] = $localeData;
        }
        return $translationURLs;
    }

	/**
	 * Retrieves the available locales from Easyling (eg. en-US)
	 *
	 * @throws Exception
	 * @global Easyling $easyling
	 * @since 0.9.10
	 * @return array Array of language => URL Part
	 */
    function easyling_get_locales() {
        // does not work on admin
        if (is_admin())
            return;

        global $easyling_instance;
        // see if Easyling is inited
        if (!($easyling_instance instanceof Easyling)) {
            throw new Exception('Easyling class is not initialized yet. Please make sure you use `easyling_get_languages` after the plugins have been loaded.');
        }

        return $easyling_instance->filter_available_locales($easyling_instance->get_available_languages());
    }

}

global $wp_version;
if (version_compare($wp_version, '3.2') >= 0) {
    // min. requirements for the plugin are
    // PHP 5.2.4
    // MySQL 5.x
    // same as for the wordpress version 3.2
    global $easyling_instance;
    $easyling_instance = Easyling::getInstance();
}
