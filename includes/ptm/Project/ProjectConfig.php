<?php
/**
 * User: Atesz
 * Date: 2014.06.24.
 * Time: 19:24
 */

class ProjectConfig_Base {

	/**
	 * @return Pattern
	 */
	public function getSwapElementPattern() {
		return Pattern::compile('\b(?:SL_swap|EL_swap)\b');
	}

	/**
	 * @return Pattern
	 */
	public function getNoReferenceRemapPattern() {
		return Pattern::compile('\b__ptNoRemap\b');
	}

	/**
	 * @return Pattern
	 */
	public function getLeadingSpacesPattern() {
		return Pattern::compile('^\s*');
	}

	/**
	 * @return Pattern
	 */
	public function getTrailingSpacesPattern() {
		return Pattern::compile('\s*$');
	}

	/**
	 * @return Pattern
	 */
	public function getDontTranslatePattern() {
		return Pattern::compile('^[-\s,\.\[\]]*[0-9][-0-9\s,\.\[\]]*$');
	}

	/**
	 * @return Pattern
	 */
	public function getWhiteSpacePattern() {
		return Pattern::compile('^\s*$');
	}

	/**
	 * @return StringSet
	 */
	public function getBlockElements() {
		return new StringSet(array(
			"button","address","article","aside","audio","blockquote","canvas","dt","dd","div","dl",
			"fieldset","figcaption","figure","footer","form","h1","h2","h3","h4","h5","h6","header",
			"hgroup","hr","noscript","ol","output","p","pre","section","table","td","th","caption",
			"ul","li","video","body"));
	}

	/**
	 * @return StringSet
	 */
	public function getTranslatedMetas() {
		return new StringSet(array(
			"description","keywords","author","copyright","contact"
		));
	}

	/**
	 * @return StringSet
	 */
	public function getTranslatedInputs() {
		return new StringSet(array(
			"button", "submit", "reset"
		));
	}

	/**
	 * @return CharMatcher
	 */
	public function getInvalidCharacterMatcher() {
		return CharMatcher::inRange(1, 6)->orMatcher(CharMatcher::inRange(14, 26))->orMatcher(CharMatcher::inRange(28, 31));
	}
}

class ProjectConfig_v0 extends ProjectConfig_Base {

}

class ProjectConfig_v1 extends ProjectConfig_v0 {

	/**
	 * @return StringSet
	 */
	public function getBlockElements() {
		$blockElements = parent::getBlockElements();
		$blockElements->add('option');
		return $blockElements;
	}
}

// same config
class ProjectConfig_v2 extends ProjectConfig_v1 {

}

class ProjectConfig_v3 extends ProjectConfig_v2 {

	/**
	 * @return Pattern
	 */
	public function getLeadingSpacesPattern() {
		return Pattern::compile('^\s*', Pattern::UNICODE_CHARACTER_CLASS);
	}

	/**
	 * @return Pattern
	 */
	public function getTrailingSpacesPattern() {
		return Pattern::compile('\s*$', Pattern::UNICODE_CHARACTER_CLASS);
	}

	/**
	 * @return Pattern
	 */
	public function getDontTranslateIsolatedPattern() {
		return Pattern::compile('^[-0-9\s,\.\[\]]*$', Pattern::UNICODE_CHARACTER_CLASS);
	}

	/**
	 * @return Pattern
	 */
	public function getDontTranslatePattern() {
		return Pattern::compile('^[-\s,\.\[\]]*[0-9][-0-9\s,\.\[\]]*$', Pattern::UNICODE_CHARACTER_CLASS);

	}

	/**
	 * @return Pattern
	 */
	public function getWhiteSpacePattern() {
		return Pattern::compile('^\s*$', Pattern::UNICODE_CHARACTER_CLASS);
	}

	/**
	 * @return StringSet
	 */
	public function getTranslatedMetas() {
		$metas = parent::getTranslatedMetas();
		$metas->add("og:title");
		$metas->add("og:description");
		$metas->add("og:site_name");
		return $metas;
	}

}

class ProjectConfig extends ProjectConfig_v3 {

}