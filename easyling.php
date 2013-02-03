<?php

/*
  Plugin Name: Easyling for Wordpress
  Description: Easyling is a Website translation tool, suitable for DIY work; or order the professional translation service from  www.easyling.com.
  Version: 0.9.5
  Plugin URI: http://easyling.com
 */

if (!class_exists('Easyling')) {

    define('EASYLING_PATH', WP_PLUGIN_DIR . '/easyling-for-wp');
    define('EASYLING_URL', WP_PLUGIN_URL . '/easyling-for-wp');
    define('EASYLING_VERSION', '0.1.1');

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

	        // add non pivileged ajax
            add_action('wp_ajax_nopriv_easyling_oauth_push', array(&$this, 'ajax_oauth_push'));

            // admin only things
            if (is_admin()) {
                require_once EASYLING_PATH . '/admin/admin.php';
                $a = new Easyling_Admin($this);
            }

            // plugin activation / deletion
            $path = WP_PLUGIN_DIR . '/easyling-for-wp/easyling.php';
            register_activation_hook($path, array(&$this, 'activation_hook'));
            register_deactivation_hook($path, array(&$this, 'deactivation_hook'));
            register_uninstall_hook($path, array(&$this, 'uninstall_hook'));

            // get settings
            $this->settings = get_option('easyling');

	        // hooks
            if (!is_admin()) {
                if($this->settings['status'] == self::STATUS_AUTHED){
                // very low prio
                add_action('parse_query', array($this, 'detect_language'), 9999);
                }
                // create a new OB with callback
                add_action('init', array(&$this, 'init_ob_start'));
            }

            // add custom URL structure
            add_filter('admin_init', array(&$this, 'flush_rewrite_rules'));

            // some rewrite rules
            add_filter('page_rewrite_rules', array(&$this, 'filter_rewrite_rules'));
            add_filter('post_rewrite_rules', array(&$this, 'filter_rewrite_rules_home'));
            add_filter('category_rewrite_rules', array(&$this, 'filter_rewrite_rules'));
            add_filter('date_rewrite_rules', array(&$this, 'filter_rewrite_rules'));
            add_filter('author_rewrite_rules', array(&$this, 'filter_rewrite_rules'));
            add_filter('tag_rewrite_rules', array(&$this, 'filter_rewrite_rules'));
            add_filter('rewrite_rules_array', array(&$this, 'filter_rewrite_rules_array'));

            // some filters to display the proper links
            add_action('wp', array(&$this, 'wp'));
            add_filter('page_link', array(&$this, 'filter_links'));
            add_filter('post_link', array(&$this, 'filter_links'));
            add_filter('category_link', array(&$this, 'filter_links'));
            add_filter('year_link', array(&$this, 'filter_links'));
            add_filter('month_link', array(&$this, 'filter_links'));
            add_filter('tag_link', array(&$this, 'filter_links'));

            add_filter('query_vars', array(&$this, 'queryVars'));

if($this->settings['status'] == self::STATUS_AUTHED){
            // check for multi domain support
            $multidomain = get_option('easyling_multidomain', false);
            if ($multidomain && $multidomain['status'] == 'on') {
                $this->useMultidomain = true;
                // build config
                $mdConfig = array();
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
                        'home' => $lang
                    );
                }
                // include multisite component
                require_once EASYLING_PATH . '/includes/Multidomain/Multidomain.php';
                $this->multidomain = new MultiDomain($mdConfig);
            }
}
        }

        public function filter_rewrite_rules_array($rules) {
            // add paged nav for home 
            $new_rules = array();
            $available_languages = $this->get_available_languages();
            global $wp_rewrite;
            if (!empty($available_languages)) {
                $new_rules['(' . implode('|', $available_languages) . ')/' . $wp_rewrite->pagination_base . '/([0-9]{1,})/{0,1}$'] = 'index.php?easyling=$matches[1]&paged=$matches[2]';
            }
            $new_rules += $rules;
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
                $response = array('success' => true);

                if (isset($data))
                    $response['data'] = $data;

                $this->send_json($response);
            }else {
                wp_send_json_success($data);
            }
        }

        public function send_json_error($data = null) {
            if ((float) get_bloginfo('version') < 3.5) {
                $response = array('success' => false);
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
            ob_start(array(&$this, 'ob_callback'));
        }

        public function log($str) {
            trigger_error($str, E_USER_WARNING);
        }

        /**
         * Determines the language of the request
         * @global WP_Query $wp_query
         */
        public function detect_language() {
            global $wp_query;

            if (!$this->multidomain) {

                // get language from URL or easyling query_var
                if (($language = $wp_query->get('easyling')) !== '') {
                    $language = trim($language, '/');
                    // we got easyling query var defined
                    $this->targetLanguage = $language;
                    $this->targetLocale = $this->matchLanguageToLocale($language);
                    $this->originalRequestURI = str_ireplace('/' . $this->targetLanguage . '/', '/', $_SERVER['REQUEST_URI']);
                } else {
                    // TODO: something is screwed up
                }
            } else {
                $this->targetLocale = $this->matchDomainToLocale();
                $this->originalRequestURI = $_SERVER['REQUEST_URI'];
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
            $available_languages = array();
            $linked_project_languages = get_option('easyling_project_languages');
            if (!is_array($linked_project_languages))
                return array();
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

            $new_rewrite_rules['(' . implode('|', $available_languages) . ')/{0,1}$'] = 'index.php?easyling=$matches[1]';
            $new_rewrite_rules += $this->filter_rewrite_rules($rewrite_rules);

            //print_r($new_rewrite_rules);
            return $new_rewrite_rules;
        }

        public function filter_rewrite_rules($rewrite_rules) {
            $new_rewrite_rules = array();

            $lang_pattern = "";
            $available_languages = $this->get_available_languages();

            if (empty($available_languages))
                return $rewrite_rules;

            foreach ($available_languages as $lang) {
                $lang_pattern .= $lang . "/|";
            }

//		    print_r($rewrite_rules);

            foreach ($rewrite_rules as $pattern => $rewrite_rule) {
                /* if (strpos($rewrite_rule, "name") === FALSE &&
                  strpos($rewrite_rule, "pagename") === FALSE &&
                  strpos($rewrite_rule, "category_name") === FALSE &&
                  strpos($rewrite_rule, "year") === FALSE) {
                  $new_rewrite_rules[$pattern] = $rewrite_rule;
                  continue;
                  } */
                if (strpos($rewrite_rule, "attachment") !== FALSE) {
                    $new_rewrite_rules[$pattern] = $rewrite_rule;
                    continue;
                }
                $new_pattern = "($lang_pattern)" . $pattern;
                $new_rewrite_rules[$new_pattern] = preg_replace_callback('/matches\[(\d*?)\]/', array(&$this, "_preg_replace_callback"), $rewrite_rule) . '&easyling=$matches[1]';
            }
//print_r($new_rewrite_rules);
            return $new_rewrite_rules;
//		    return $rewrite_rules;
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
            if (!$this->useMultidomain)
                return array($home => $home . $this->targetLanguage);
            return array($home => $home);
        }

        public function translate($projectCode, $remoteURL, $targetLanguage, $htmlContent = null) {
            $ptm = $this->getPtm();
            $p = $ptm->getFrameworkService()->getProjectByCode($projectCode);
            $translated = $ptm->translateProjectPage($p, $remoteURL, $htmlContent, $targetLanguage, $this->get_resource_map($remoteURL));
            return $translated;
        }

        public function ob_callback($buffer) {
//            return $buffer;
            // this is the place to modify the markup
            if ($this->targetLocale !== null) {
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
        public function activation_hook() {

            // first run a few checks if we have extensions loaded
            $extensions_check = false;
            if (extension_loaded('openssl') &&
                    extension_loaded('curl') &&
                    (
                    extension_loaded('iconv') &&
                    extension_loaded('mbstring')
                    )
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
                echo extension_loaded('curl') ? '' : '<li>openssl</li>';
                echo extension_loaded('iconv') ? '' : '<li>openssl</li>';
                echo extension_loaded('mbstring') ? '' : '<li>openssl</li>';
                echo '</ul></div>';
                die();
            }


            // add wp_option for easyling
            $opt_easyling = get_option('easyling', null);
            if ($opt_easyling === null) {
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

                // create table
                /** @var wpdb $wpdb */
                global $wpdb;

                $query = 'CREATE TABLE IF NOT EXISTS `wp_easyling` ( ' .
                        '`easyling_key` varchar(300) CHARACTER SET utf8 NOT NULL, ' .
                        '`easyling_value` mediumblob NOT NULL, ' .
//                        '`easyling_project` varchar(32) CHARACTER SET utf8 NOT NULL, ' .
                        'UNIQUE KEY `uq_project_key` (`easyling_key`) ' .
                        ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
                $res = $wpdb->query($query);
                if ($res === false) {
                    // handle error for creating table
                    throw new Exception("Error while creating table");
                }
            } else {
                
            }

            // flush rewrite rules
            /** @var WP_Rewrite $wp_rewrite */
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }

        /**
         * Hook ran when the plugin is deactivated
         */
        public function deactivation_hook() {

            $ptm = $this->getPtm();
            $ptm->uninstall();

            delete_option('easyling');

            delete_option('easyling');
            delete_option('easyling_available_locales');
            delete_option('easyling_project_languages');
            delete_option('easyling_linked_project');
            delete_option('easyling_multidomain');
            delete_option('easyling_consent');
            // option to store oauth consumer key and secret
            delete_option('easyling_id');

            // remove our sessions
            if (!session_id())
                session_start();
            unset($_SESSION['oauth']);
            unset($_SESSION['oauth_internal_redirect']);
        }

        /**
         * Hook ran when the plugin is removed
         */
        public function uninstall_hook() {
            
        }

	    /**
	     * @return PTM
	     */
	    public function getPtm() {
            if ($this->ptm === null) {
                $this->ptm = new PTM();
	            $this->ptm->enableErrorReporting($this->consent);
                $projectPageStorage = new WPDbStorage(KeyValueStorage::ITEMTYPE_PROJECTPAGE);
                $optionStorage = new WPOptionStorage(KeyValueStorage::ITEMTYPE_OPTION);
                $sm = $this->ptm->getStorageManager();
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_PROJECTPAGE, $projectPageStorage);
                $sm->setStorageForItemType(KeyValueStorage::ITEMTYPE_OPTION, $optionStorage);
            }
            return $this->ptm;
        }    

    }

}

global $wp_version;
if (version_compare($wp_version, '3.2') >= 0) {
    // min. requirements for the plugin are
    // PHP 5.2.4
    // MySQL 5.x
    // same as for the wordpress version 3.2
    global $easyling;
    $easyling = Easyling::getInstance();
}


