<?php
/**
 * User: Atesz
 * Date: 2014.07.22.
 * Time: 11:28
 */

class Easyling_Settings {

	const OPTION_PREFIX = "easyling_";

	const PLUGIN_SETTINGS = "easyling";

	const OAUTH_ID = "id";
	const CONSENT = "consent";
	const LINKED_PROJECT = "linked_project";
	const MULTIDOMAIN  = "multidomain";
	const SOURCE_LOCALES  = "source_langs";
	const LANGUAGE_SELECTOR  = "language_selector";
	const ACCESS_TOKENS  = "access_tokens";
	const PROJECT_LOCALES  = "project_languages";

	/** @var  Easyling */
	private $plugin;

	private function getOption($name, $defaultValue = null, $prefix = self::OPTION_PREFIX) {
		$value = get_option($prefix.$name, $defaultValue);
		return $value;
	}

	private function setOption($name, $value, $prefix = self::OPTION_PREFIX) {
		return update_option($prefix.$name, $value);
	}

	public function hasOption($name, $prefix = self::OPTION_PREFIX) {
		return get_option($prefix.$name, null) !== null;
	}

	/**
	 * @param bool $multidomain
	 */
	public function saveMultiDomain($multidomain) {
		$md = $this->getOption(self::MULTIDOMAIN);
		$md['status'] = $multidomain?'on':'off';
		$this->setOption(self::MULTIDOMAIN, $md);
	}

	/**
	 * @return bool
	 */
	public function isMultiDomain() {
		$md = $this->getOption(self::MULTIDOMAIN);
		if ($md === null)
			return false;

		return @$md['status'] == 'on';
	}

	/**
	 * @param bool $sendConsent
	 * @return bool
	 */
	public function saveSendConsent($sendConsent) {
		$consent = $sendConsent ? "1" : "0";
		return $this->getOption(self::CONSENT, $consent);
	}

	/**
	 * Return null if undefined
	 * @return bool|null
	 */
	public function isSendConsent() {
		$consent = $this->getOption(self::CONSENT);
		if ($consent === null)
			return null;

		return $consent == "1";
	}

	/**
	 * @return bool
	 */
	public function isLanguageSelector() {
		$ls = $this->getOption(self::LANGUAGE_SELECTOR);
		return @$ls == "on";
	}

	/**
	 * @return string|null
	 */
	public function getLinkedProject() {
		$linkedProject = $this->getOption(self::LINKED_PROJECT);
		if (empty($linkedProject)) {
			return null;
		}

		return $linkedProject;
	}

	/**
	 * @return PTMLocales
	 */
	public function getSourceLocales() {
		$locales = $this->getOption(self::SOURCE_LOCALES, array());
		return new PTMLocales($locales);
	}

	/**
	 * @param PTMLocales|PTMLocale[] $locales
	 */
	public function saveSourceLocales($locales) {
		$storeLocales = array();

		foreach ($locales as $projectCode=>$locale) {
			$storeLocales[$projectCode] = $locale->getLocale();
		}

		$this->setOption(self::SOURCE_LOCALES, $storeLocales);
	}

	/**
	 * @param string $projectCode
	 * @return PTMLocale|null
	 */
	private function getSourceLocale($projectCode) {
		$sourceLanguages = $this->getSourceLocales();
		if (!$sourceLanguages->hasKey($projectCode))
			return null;

		return $sourceLanguages[$projectCode];
	}

	/**
	 * @return PTMLocale|null
	 */
	public function getLinkedSourceLocale() {
		$linkedProject = $this->getLinkedProject();
		if ($linkedProject == null)
			return null;
		return $this->getSourceLocale($linkedProject);
	}

	/**
	 * @return bool
	 */
	public function isLinkedProject() {
		return $this->getLinkedProject() != null;
	}

	/**
	 * @param bool $emptyToNull
	 * @return Easyling_OAuthSettings
	 */
	public function getOAuthId($emptyToNull = false) {
		$id = $this->getOption(self::OAUTH_ID);
		if (empty($id)) {
			if ($emptyToNull)
				return null;
			return new Easyling_OAuthSettings(null, null);
		}
		return new Easyling_OAuthSettings($id['consumer_key'], $id['consumer_secret']);
	}

	/**
	 * @param Easyling_OAuthSettings $oauthId
	 */
	public function saveOAuthId($oauthId) {
		$id = array(
			'consumer_key'=>$oauthId->getKey(),
			'consumer_secret'=>$oauthId->getSecret()
		);

		$this->setOption(self::OAUTH_ID, $id);
	}

	/**
	 * @return Easyling_OAuthAccessTokenSettings|null
	 */
	public function getOAuthAccessToken() {
		$at = $this->getOption(self::ACCESS_TOKENS);
		if ($at === null)
			return null;

		return new Easyling_OAuthAccessTokenSettings($at['access_token'], $at['access_token_secret']);
	}

	/**
	 * @param Easyling_OAuthAccessTokenSettings $accessToken
	 */
	public function saveOAuthAccessToken($accessToken) {
		$secret = $accessToken->getSecret();
		$token = $accessToken->getToken();

		$at = array('access_token'=>$token, 'access_token_secret'=>$secret);
		$this->setOption(self::ACCESS_TOKENS, $at);
	}

	/**
	 * @var Easyling_PluginSettings null
	 */
	private $pluginSettings = null;

	/**
	 * @param bool $create
	 * @return \Easyling_PluginSettings|null
	 */
	public function getPluginSettings($create = false) {
		$ps = $this->getOption(self::PLUGIN_SETTINGS, null, "");
		if ($ps === null && $create == false) {
			return null;
		}

		if ($this->pluginSettings != null)
			return $this->pluginSettings;

		$this->pluginSettings = new Easyling_PluginSettings($this->plugin, $ps);
		return $this->pluginSettings;
	}

	public function savePluginSettings() {
		$this->setOption(self::PLUGIN_SETTINGS, $this->pluginSettings->getAsArray(), "");
	}

	/**
	 * @var Easyling_ProjectLocalesSettings
	 */
	private $projectLocales = null;

	/**
	 * @return Easyling_ProjectLocalesSettings|Easyling_ProjectLocaleSettings[]
	 */
	public function getProjectLocales() {

		if ($this->projectLocales !== null)
			return $this->projectLocales;

		$pl = $this->getOption(self::PROJECT_LOCALES);
		if ($pl == null) {
			$this->projectLocales = new Easyling_ProjectLocalesSettings(array());
		} else {
			$this->projectLocales = new Easyling_ProjectLocalesSettings($pl);
		}

		return $this->projectLocales;
	}

	/**
	 * @return Easyling_ProjectLocalesSettings|Easyling_ProjectLocaleSettings[]
	 */
	public function getUsedProjectLocales() {
		$locales = $this->getProjectLocales();
		$usedLocales = new Easyling_ProjectLocalesSettings(array());
		foreach ($locales as $locale=>$lSettings) {
			if ($lSettings->isUsed())
				$usedLocales->offsetSet($locale, $lSettings);
		}

		return $usedLocales;
	}

	public function saveProjectLocales() {
		$this->setOption(self::PROJECT_LOCALES, $this->projectLocales->getAsArray());
	}

	/**
	 * @param Easyling $plugin
	 */
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
}

class Easyling_PluginSettings {

	/**
	 * Status for when the plugin is installed but not yet hooked with
	 * easyling
	 */
	const STATUS_INSTALLED = 1;

	/**
	 * Has been installed and the handshake is done with easyling
	 */
	const STATUS_AUTHED = 2;

	/** @var  string */
	private $version;
	/** @var  int */
	private $status;
	/** @var  string */
	private $key;
	/** @var  string */
	private $defaultLanguage;
	/** @var  string */
	private $canonicalURL;
	/** @var  bool */
	private $popupShown;
	/** @var  bool */
	private $tutorialShown;

	private $updates;

	/**
	 * @param Easyling $plugin
	 * @param $settingArray
	 */
	public function __construct($plugin, $settingArray) {
		if (empty($settingArray)) {
			$settingArray = array(
				'version' => $plugin->get_current_plugin_version(),
				'status' => self::STATUS_INSTALLED,
				'key' => sha1(date('Y-m-d h:m', time())),
				'default_lang' => 'en',
				'canonical_url' => get_bloginfo('url'),
				'popup_shown' => false,
				'tutorial_shown' => false
			);
		}

		$this->version = $settingArray['version'];
		$this->status = $settingArray['status'];
		$this->key = $settingArray['key'];
		$this->defaultLanguage = $settingArray['default_lang'];
		$this->canonicalURL = $settingArray['canonical_url'];
		$this->popupShown = @$settingArray['popup_shown'];
		$this->tutorialShown = @$settingArray['tutorial_shown'];

		$this->updates = @$settingArray['updates'];

		if ($this->updates === null)
			$this->updates = array();
	}

	/**
	 * @return bool
	 */
	public function isAuthenticated() {
		return $this->status == self::STATUS_AUTHED;
	}

	/**
	 * @return bool
	 */
	public function isInstalled() {
		return $this->status == self::STATUS_INSTALLED;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $version
	 */
	public function setVersion($version) {
		$this->version = $version;
	}

	/**
	 * @return string
	 */
	public function getCanonicalURL() {
		return $this->canonicalURL;
	}

	/**
	 * @param int $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}

	/**
	 * @return bool
	 */
	public function isTutorialShown() {
		return $this->tutorialShown;
	}

	/**
	 * @param bool $shown
	 */
	public function setTutorialShown($shown) {
		$this->tutorialShown = $shown;
	}

	/**
	 * @return bool
	 */
	public function isActivationPopupShown() {
		return $this->popupShown;
	}

	/**
	 * @param bool $shown
	 */
	public function setActivationPopupShown($shown) {
		$this->popupShown = $shown;
	}

	/**
	 * @param string $updateVer
	 * @param string $message
	 * @param bool $actedUpon
	 * @param string|null $callback
	 */
	public function setUpdate($updateVer, $message, $actedUpon = false, $callback = null) {
		$this->updates[$updateVer] = array(
			'message'=>$message,
			'acted_upon'=>$actedUpon,
			'callback'=>$callback
		);
	}

	/**
	 * @param string $updateVer
	 */
	public function setUpdateActedUpon($updateVer) {
		$this->updates[$updateVer]['acted_upon'] = true;
	}

	/**
	 * @param string $updateVer
	 * @return string|null
	 */
	public function getUpdateCallback($updateVer) {
		return @$this->updates[$updateVer]['callback'];
	}

	/**
	 * @return array
	 */
	public function getUpdateArray() {
		return $this->updates;
	}

	/**
	 * @param string $updateVer
	 */
	public function removeUpdateNotification($updateVer) {
		unset($this->updates[$updateVer]);
	}

	/**
	 * @return array
	 */
	public function getAsArray() {
		return array(
			'version'=>$this->version,
			'status'=>$this->status,
			'key'=>$this->key,
			'default_lang'=>$this->defaultLanguage,
			'canonical_url'=>$this->canonicalURL,
			'popup_shown'=>$this->popupShown,
			'tutorial_shown'=>$this->tutorialShown,
			'updates'=>$this->updates
		);
	}
}

class Easyling_ProjectLocalesSettings implements IteratorAggregate, ArrayAccess {

	/** @var  Easyling_ProjectLocaleSettings[] */
	private $localesSettings = array();

	public function __construct($settingArray) {

		foreach ($settingArray as $localeString=>$settings) {
			$locale = new PTMLocale($localeString);
			$this->localesSettings[$localeString] = new Easyling_ProjectLocaleSettings($locale, $settings);
		}
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		return empty($this->localesSettings);
	}

	public function getAsArray() {
		$array = array();
		foreach ($this->localesSettings as $locales=>$settings) {
			$array[$locales] = $settings->getAsArray();
		}

		return $array;
	}

	public function getIterator() {
		return new ArrayIterator($this->localesSettings);
	}

	public function offsetExists($locale) {
		return isset($this->localesSettings[$locale]);
	}

	public function offsetGet($locale) {
		return $this->localesSettings[$locale];
	}

	public function offsetSet($locale, $value) {
		$this->localesSettings[$locale] = $value;
	}

	public function offsetUnset($locale) {
		unset($this->localesSettings[$locale]);
	}
}

class Easyling_ProjectLocaleSettings {
	/** @var PTMLocale */
	private $locale;

	/** @var  bool */
	private $used;

	/** @var  string for path prefix (rewrite rules) */
	private $pathPrefix;

	/** @var  string for multidomain support*/
	private $domain;

	public function __construct($locale, $settings) {
		$this->locale = $locale;
		$this->used = $settings['used'] == 'on' ? true : false;

		$this->domain = $this->fixDomain(@$settings['domain']);
		$this->pathPrefix = @$settings['lngcode'];
	}

	/**
	 * Fix user input domain
	 * @param string $domain
	 * @return string|null
	 */
	private function fixDomain($domain) {
		if (empty($domain))
			return null;

		// remove unnecessary protocol
		$domain = preg_replace('%^(http|https)://%', '', $domain);

		// remove trailing path
		$domain = preg_replace('%/.*$%', '', $domain);

		return $domain;
	}

	/**
	 * @return bool
	 */
	public function isUsed() {
		return $this->used;
	}

	/**
	 * @return string
	 */
	public function getDomain() {
		return $this->domain;
	}

	/**
	 * @return string
	 */
	public function getPathPrefix() {
		return $this->pathPrefix;
	}

	public function getAsArray() {
		return array(
			'used'=>$this->used?'on':'off',
			'lngcode'=>$this->pathPrefix,
			'domain'=>$this->domain
		);
	}
}

class Easyling_OAuthSettings {
	private $consumerKey;
	private $consumerSecret;

	public function __construct($key, $secret) {
		$this->consumerKey = $key;
		$this->consumerSecret = $secret;
	}

	public function getKey() {
		return $this->consumerKey;
	}

	public function getSecret() {
		return $this->consumerSecret;
	}
}

class Easyling_OAuthAccessTokenSettings {
	private $token;
	private $secret;

	public function __construct($token, $secret) {
		$this->token = $token;
		$this->secret = $secret;
	}

	public function getToken() {
		return $this->token;
	}

	public function getSecret() {
		return $this->secret;
	}

	// use for put to session
	public function getAsArray() {
		return array(
			'access_token'=>$this->token,
			'access_token_secret'=>$this->secret
		);
	}
}
