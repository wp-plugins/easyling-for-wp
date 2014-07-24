<?php
/**
 * User: Atesz
 * Date: 2014.07.21.
 * Time: 18:00
 */

class PTMLocale {

	/** @var string $langCode lowercase language code */
	private $langCode;

	/** @var string $countryCode uppercase country code  */
	private $countryCode;

	public function __construct($locale) {
		list($langCode, $countryCode ) = explode("-", $locale, 2);

		$this->langCode = strtolower($langCode);
		$this->countryCode = strtoupper($countryCode);
	}

	public function __toString() {
		return $this->getLocale();
	}

	public function getLocale() {
		return $this->langCode."-".$this->countryCode;
	}

	public function getLanguageCode() {
		return $this->langCode;
	}

	public function getCountryCode() {
		return $this->countryCode;
	}
}

class PTMLocales implements IteratorAggregate, ArrayAccess {

	private $locales;

	public function __construct($locales = array()) {

		foreach ($locales as $idx=>$locale) {
			if (is_string($locale)) {
				$this->locales[$idx] = new PTMLocale($locale);
			} else if ($locale instanceof PTMLocale) {
				$this->locales[$idx] = $locale;
			} else {
				throw new InvalidArgumentException('Invalid argument type for locale');
			}
		}
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasKey($key) {
		return isset($this->locales[$key]);
	}

	/**
	 * @param string $key
	 * @return PTMLocale|null
	 */
	public function getByKey($key) {
		if ($this->hasKey($key))
			return $this->locales[$key];

		return null;
	}

	public function offsetExists($offset) {
		return $this->hasKey($offset);
	}

	public function offsetGet($offset) {
		return $this->getByKey($offset);
	}

	public function offsetSet($offset, $value) {
		$this->locales[$offset] = $value;
	}

	public function offsetUnset($offset) {
		unset($this->locales[$offset]);
	}

	public function getAsArray() {
		return $this->locales;
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		return empty($this->locales);
	}

	/**
	 * @return ArrayIterator
	 */
	public function getIterator() {
		return new ArrayIterator($this->locales);
	}

}