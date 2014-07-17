<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 20:59
 */

require_once dirname(__FILE__) . '/ProjectLanguage.php';

class Project implements HasMapKey
{
	const DEFAULT_CONFIG_VERSION = 3;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $projectCode;

	/**
	 * @var ProjectLanguage[]
	 */
	private $projectLanguages = array();

	/**
	 * @var string[]
	 */
	private $ignoreParams = array();

	/**
	 * @var string[]
	 */
	private $hostAliases = array();

	/**
	 * @var string[]
	 */
	private $ignoreClasses = array();

	/**
	 * @var string[]
	 */
	private $pathIgnoreRules = array();

	/**
	 * @var string[]
	 */
	private $ignoreRegexp = array();

	public function __construct($name, $projectCode, $prLanguages = array(), $hostAliases = array())
	{
		$this->name = $name;
		$this->projectCode = $projectCode;
		$this->setLanguages($prLanguages);
		$this->hostAliases = $hostAliases;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getProjectCode()
	{
		return $this->projectCode;
	}

	/**
	 * @return ProjectLanguage[]
	 */
	public function getProjectLanguages()
	{
		return $this->projectLanguages;
	}

	/**
	 * @return string[]
	 */
	public function getProjectLanguageArray()
	{
		return array_keys($this->projectLanguages);
	}

	/**
	 * @return array|string[]
	 */
	public function getHostAliases()
	{
		return $this->hostAliases;
	}

	/**
	 * @return string[]
	 */
	public function getIgnoreClasses()
	{
		if ($this->ignoreClasses === null) {
			$this->ignoreClasses = array();
		}
		return $this->ignoreClasses;
	}

	/**
	 * @return string[]
	 */
	public function getPathIgnoreRules()
	{
		if ($this->pathIgnoreRules === null) {
			$this->pathIgnoreRules = array();
		}

		return $this->pathIgnoreRules;
	}

	/**
	 * @return string[]
	 */
	public function getIgnoreRegexp()
	{
		if ($this->ignoreRegexp === null) {
			$this->ignoreRegexp = array();
		}

		return $this->ignoreRegexp;
	}

	/**
	 * @return string
	 */
	private function getCanonicalHost()
	{
		// TODO: find out canonical host from
		return $this->name;
	}

	/**
	 * @param $url
	 * @return string
	 */
	public function simplifyURL($url)
	{
		return EncodeUtil::simplifyURL($url, $this->ignoreParams, true,
			$this->getCanonicalHost(), $this->hostAliases);
	}

	public function setIgnoredParams($ignoredParams)
	{
		$this->ignoreParams = $ignoredParams;
	}

	public function setHostAliases($aliases)
	{
		$this->hostAliases = $aliases;
	}

	public function setIgnoreClasses($ignoreClasses) {
		$this->ignoreClasses = $ignoreClasses;
	}

	public function setPathIgnoreRules($ignoreRules) {
		$this->pathIgnoreRules = $ignoreRules;
	}

	public function setIgnoreRegexp($ignoreRegexp) {
		$this->ignoreRegexp = $ignoreRegexp;
	}

	/**
	 * @return ProjectConfig_Base
	 */
	public function getProjectConfig() {
		/** @var ProjectConfig_Base $configClassName */
		$configClassName = 'ProjectConfig_v'.self::DEFAULT_CONFIG_VERSION;
		if (!class_exists($configClassName))
			$configClassName = 'ProjectConfig';
		return new $configClassName();
	}

	public function setLanguages($languages)
	{
		foreach ($languages as $language)
		{
			if (is_string($language))
				$this->projectLanguages[$language] = new ProjectLanguage($language);
			else if ($language instanceof ProjectLanguage)
				$this->projectLanguages[$language->getLanguageCode()] = $language;
			else throw new InvalidArgumentException("prLanguage type is invalid: ".gettype($language));
		}
	}

	public function getRootURL() {
		return 'http://'.$this->name.'/';
	}

	public function getPathURL($path) {
		return $this->getRootURL().ltrim($path,'/');
	}

	public function getMapKey()
	{
		return $this->projectCode;
	}

	/**
	 * Compatibility for java Translator
	 * @param string $projectCode
	 * @return string
	 */
	static public function keyForCode($projectCode) {
		return $projectCode;
	}

	/**
	 * Compatibility for java Translator
	 * @param string $key
	 * @return Project
	 */
	static public function getProject($key) {
		return PTM::get()->getFrameworkService()->getProjectByCode($key);
	}
}
