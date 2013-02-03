<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 20:59
 */

require_once dirname(__FILE__) . '/ProjectLanguage.php';

class Project implements HasMapKey
{
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

	public function getMapKey()
	{
		return $this->projectCode;
	}
}
