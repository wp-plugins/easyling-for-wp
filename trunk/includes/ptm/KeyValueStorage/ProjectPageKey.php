<?php
/**
 * User: Atesz
 * Date: 2012.12.11.
 * Time: 12:47
 */
class ProjectPageKey implements Key
{
	/**
	 * @var string
	 */
	private $projectCode;

	/**
	 * @var string
	 */
	private $targetLanguage;

	/**
	 * @var string
	 */
	private $simpleURL;

	/**
	 * @param string $projectCode
	 * @param string|null $targetLanguage
	 * @param string|null $simpleURL
	 */
	public function __construct($projectCode, $targetLanguage = null, $simpleURL = null)
	{
		$this->projectCode = $projectCode;
		$this->targetLanguage = $targetLanguage;
		$this->simpleURL = $simpleURL;
	}

	/**
	 * @return string
	 */
	public function serialize()
	{
		$serialized = $this->projectCode;
		if ($this->targetLanguage)
			$serialized .= "_" . $this->targetLanguage;

		if ($this->simpleURL)
			$serialized .= "_" . $this->simpleURL;//str_replace("%","_",urlencode($this->pageKey));

		return $serialized;
	}

	/**
	 * @return string
	 */
	public function getProjectCode()
	{
		return $this->projectCode;
	}

	/**
	 * @return string
	 */
	public function getTargetLanguage()
	{
		return $this->targetLanguage;
	}

	/**
	 * @return string
	 */
	public function getSimpleURL()
	{
		return $this->simpleURL;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->serialize();
	}
}
