<?php

/*
  Plugin Name: Easyling for Wordpress
  Description: Easyling is a Website translation tool, suitable for DIY work.
  Version: 0.9.22
  Plugin URI: http://easyling.com
 */

require_once(dirname(__FILE__). '/config.php');

if (!class_exists('Easyling')) {

    define('EASYLING_PATH', plugin_dir_path(__FILE__));
    define('EASYLING_URL', plugin_dir_url(__FILE__));

	// use internal logo, if not defined in config.php
	if (!defined('PRODUCT_LOGO_URL')) {
		define ('PRODUCT_LOGO_URL', EASYLING_URL."images/easyling-logo.png");
	}

    require_once dirname(__FILE__) . "/includes/ptm/KeyValueStorage/FileStorage.php";
    require_once dirname(__FILE__) . '/includes/ptm/PTM.php';
    require_once dirname(__FILE__) . '/includes/KeyValueStore/WPDbStorage.php';
    require_once dirname(__FILE__) . '/includes/KeyValueStore/WPOptionStorage.php';
	require_once dirname(__FILE__) . '/plugin_settings.php';
	require_once dirname(__FILE__) . '/translation_context.php';

    class Easyling {

	    const IN_URL_NAME = 'translationproxy';

	    private static $_instance = null;
        protected $markup = null;

        /**
         * PTM
         * @var PTM
         */
        private $ptm;

        /**
         * @var Easyling_PluginSettings
         */
        public $pluginSettings = null;

		/**
		 * @var Easyling_TranslationContext
		 */
		public $translationContext = null;

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

	    /**
	     * @return Easyling_Settings
	     */
	    public function getSettings() {
		    return $this->settings;
	    }

	    public function on_admin_save_changes() {
		    $this->flushRewriteRules();
	    }

	    /**
	     * @var Easyling_Settings
	     */
	    private $settings = null;

	    private function __construct() {

		    // create settings object, we can access wordpress option via settings
		    $this->settings = new Easyling_Settings($this);

            // get plugin settings
            $this->pluginSettings = $this->settings->getPluginSettings(false);

            // admin only things
            if (is_admin()) {
                require_once EASYLING_PATH . 'admin/admin.php';
                // update detection
                add_action('admin_init', array($this, 'run_update_detection'), 0);
                $a = new Easyling_Admin($this);
            }

		    // register transfer starting ajax
		    add_action('wp_ajax_'.self::IN_URL_NAME.'_oauth_push', array(&$this, 'ajax_oauth_init_transfer'));
		    add_action('wp_ajax_nopriv_'.self::IN_URL_NAME.'_oauth_push', array(&$this, 'ajax_oauth_init_transfer'));

		    // plugin activation / deletion
            $path = EASYLING_PATH . 'easyling.php';
            register_activation_hook($path, array(&$this, 'activation_hook'));
	        register_deactivation_hook($path, array(&$this, 'deactivation_hook'));

		    $this->translationContext = Easyling_TranslationContext::get($this->settings);

	        if ($this->pluginSettings != null && $this->pluginSettings->isAuthenticated()) {

		        $linkedProject = $this->settings->getLinkedProject();

		        if ($linkedProject != null) {

			        $this->translationContext->initializeForTranslate();

			        add_filter('query_vars', array(&$this, 'queryVars'));

			        if (!is_admin()) {
	                    // very low prio
	                    add_action('parse_query', array($this, 'detect_language'), 9999);

		                // create a new OB with callback
		                add_action('init', array(&$this, 'init_ob_start'));

		                // add into head link rel="alternate" hreflang="es" href="http://es.example.com/" />
		                add_action('wp_head', array(&$this, 'add_alt_lang_html_links'));

				        add_action('wp_enqueue_scripts', array($this, 'easyling_language_selector_floater'));
			        }

			        $this->translationContext->addWPFilters();
		        }
	        }
        }

        public function easyling_language_selector_floater() {
            $enabled = $this->settings->isLanguageSelector();

            if (!$enabled && !wp_style_is('easyling_register_stylesheet', 'enqueued'))
                return;

            wp_register_style('easyling-language-selector', EASYLING_URL . 'css/easyling.css');
            wp_enqueue_style('easyling-language-selector');
            wp_register_script('easyling', EASYLING_URL . 'js/easyling.js');
            wp_enqueue_script('easyling');

            $localizationScript = $this->translationContext->getTranslationURLs();
            $localizationScript['baseurl'] = EASYLING_URL;
            wp_localize_script('easyling', 'easyling_languages', $localizationScript);
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

	    public function ajax_oauth_init_transfer() {
		    $projectCode = $_REQUEST['projectCode'];
		    $targetLanguage = $_REQUEST['targetLanguage'];
		    $easylingResponse = file_get_contents("php://input");

		    $this->ptm->getFrameworkService()->setProjectTranslationByELResponse($projectCode, $targetLanguage, $easylingResponse);
	    }

	    /**
	     * Determines the language of the request
	     * @param WP_Query $wp_query
	     * @return void [] array of `targetLanguage`, `targetLocale`, `originalRequestURI`
	     */
        public function detect_language($wp_query) {

	        $checkResourceURL = false;

	        $this->translationContext->parseRequest();

	        if ($checkResourceURL) {
		        $pcode = $this->settings->getLinkedProject();
		        if (!empty($pcode)) {
			        $p = $this->getPtm()->getFrameworkService()->getProjectByCode($pcode);
			        $fullOriginalURL = $this->translationContext->getSourceURL();
			        $redirURL = $this->getPtm()->getURLRedirection($p, $this->translationContext->getTargetLocale()->getLocale(), $fullOriginalURL);
			        if ($redirURL) {
			            wp_redirect($redirURL);
				        exit();
			        }
		        }
	        }
            return;
        }

        public function translate($projectCode, $remoteURL, $targetLanguage, $htmlContent = null) {
            $ptm = $this->getPtm();
            $p = $ptm->getFrameworkService()->getProjectByCode($projectCode);

            // TODO: add translation cache to speed up page load time
            $translated = $ptm->translateProjectPage(
	            $p, $remoteURL,
	            $htmlContent, $targetLanguage,
	            $this->translationContext->getResourceMap());
            return $translated;
        }

	    public function isResponseTranslatable($buffer) {
		    $ptm = $this->getPtm();
		    return $ptm->isResponseTranslatable(headers_list(), $buffer);
	    }

        public function add_alt_lang_html_links() {
	        $urlMap = $this->translationContext->getAlternativeLangURLs();

	        foreach ($urlMap as $langCode=>$url) {
		        echo '<link rel="alternate" hreflang="'.htmlentities($langCode).'" href="'.htmlentities($url).'" />'."\n";
	        }
        }

        public function ob_callback($buffer) {
            // this is the place to modify the markup
            if ($this->isResponseTranslatable($buffer) && $this->translationContext->isTranslatable()) {
                $pcode = $this->settings->getLinkedProject();
                $sourceURL = $this->translationContext->getSourceURL();
                $buffer = $this->translate($pcode, $sourceURL, $this->translationContext->getTargetLocale()->getLocale(), $buffer);
            }
            return $buffer;
        }

        /**
         * Hook ran when the plugin is activated
         */
        public function activation_hook($network_wide) {

            if ($network_wide) {
                echo '<div style="font-family: Arial, sans-serif; font-size: 12px;">The Easyling Plugin terminated the activation because the PHP installation misses the following extensions:<br />';
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
                echo '<div style="font-family: Arial, sans-serif; font-size: 12px;">The Easyling Plugin terminated the activation because the PHP installation misses the following extensions:<br />';
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
            if ($this->pluginSettings === null) {
                // plugin has not yet been installed ever or was completly removed
                // clean install
	            $this->pluginSettings = $this->settings->getPluginSettings(true);
	            $this->settings->savePluginSettings();
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
            $this->flushRewriteRules();
        }

	    public function get_current_plugin_version() {
		    // get plugin data
		    $easyling_plugin = get_plugin_data(EASYLING_PATH.'easyling.php');
		    return $easyling_plugin['Version'];
	    }

        public function run_update_detection() {
	        $active_version = $this->get_current_plugin_version();
            $db_version = $this->pluginSettings->getVersion();
            // seems like that we did an update but for some reason the update code
            // did not run, so make sure we run it now
            if (version_compare($active_version, $db_version, '>')) {
                // alright, update happened, but DB is old, run the upgrade
                $this->run_updates();
            }
        }

        protected function run_updates() {
            $pluginSettings = $this->pluginSettings;
            // plugin was already installed and it's time to upgrade
            $old_version = $pluginSettings->getVersion();

            foreach ($this->upgrades as $old => $new) {
                if (version_compare($old_version, $old) <= 0) {
                    $method_name = "update_" . str_replace('.', '', $old) . "_" . str_replace('.', '', $new);
                    if (method_exists($this, $method_name)) {
                        $message = call_user_func(array($this, $method_name));

	                    $pluginSettings->setVersion($new);

	                    $callback = null;
	                    if (method_exists($this, $method_name . '_callback')) {
		                    $callback = $method_name . '_callback';
	                    }

	                    $pluginSettings->setUpdate($new, $message, false, $callback);
                        $this->settings->savePluginSettings();

                        $old_version = $new;
                    }
                }
            }
        }

        /**
         * Run the update of 011-0910 version and return the message that should be displayed on plugins page
         * @return string
         */
        public function update_011_0910() {
            $markup = 'Easyling for WP has been upgarded to <strong>0.9.10</strong><br />';
            $markup.= '<div style="padding: 0px 30px;">It is <strong>Important</strong> to ' .
                    'update the project list of Easyling projects to ensure proper functioning of the plugin!';
            if ($this->pluginSettings->isAuthenticated()) {
                $markup.= '<br />You can also do this by clicking here: <a href="' . get_admin_url() . 'admin.php?page='.self::IN_URL_NAME.'&oauth_action=updateprojectlist">Update project list</a>';
            }
            $markup.="</div>";
            return $markup;
        }

        /**
         * Callback to check if required user action has been executed and if so, remove the admin notice
         * @param Easyling_PluginSettings $pluginSettings
         */
        public function update_011_0910_callback($pluginSettings) {
            // check if the user has already 'updated' the project list
            $langs = $this->settings->hasOption(Easyling_Settings::SOURCE_LOCALES);
            // if so remove the option notification
            if ($langs !== false) {
	            $pluginSettings->removeUpdateNotification('0.9.10');
            }
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
         * @param Easyling_PluginSettings $pluginSettings
         */
        public function update_0910_0911_callback($pluginSettings) {
	        $pluginSettings->removeUpdateNotification('0.9.11');
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
         * @param Easyling_PluginSettings $pluginSettings
         */
        public function update_0911_0912_callback($pluginSettings) {
	        $pluginSettings->removeUpdateNotification('0.9.12');
        }

        /**
         * Hook ran when the plugin is deactivated
         */
        public function deactivation_hook() {
            // as of version 0.9.10 all "clean up" calls have been moved to
            // uninstall to make sure that manual updates do not touch the DB
            // which is desired
	        $this->removeRewriteRules();
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
                $this->ptm->enableErrorReporting($this->settings->isSendConsent());
                $projectPageStorage = new WPDbStorage(KeyValueStorage::ITEMTYPE_PROJECTPAGE);
                $optionStorage = new WPOptionStorage(KeyValueStorage::ITEMTYPE_OPTION);
                $sm = $this->ptm->getStorageManager();
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_PROJECTPAGE, $projectPageStorage);
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_OPTION, $optionStorage);
            }
            return $this->ptm;
        }

	    public function removeRewriteRules() {
		    if ($this->translationContext)
			    $this->translationContext->removeWPFilters();

		    $this->flushRewriteRules();
	    }

	    public function flushRewriteRules() {
		    /** @var WP_Rewrite $wp_rewrite */
		    global $wp_rewrite;
		    $wp_rewrite->flush_rules(true);
	    }
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
