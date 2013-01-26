<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 21:29
 */

class ProjectLanguage
{
	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var bool
	 */
	private $published;

	public function __construct($languageCode, $public = true)
	{
		$this->languageCode = $languageCode;
		$this->published = $public;
	}

	public function isPublished()
	{
		return $this->published;
	}

	public function getLanguageCode()
	{
		return $this->languageCode;
	}
        
        public function __toString() {
            return $this->getLanguageCode();
        }
}