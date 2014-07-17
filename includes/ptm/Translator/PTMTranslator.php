<?php
/**
 * User: Atesz
 * Date: 2014.07.15.
 * Time: 14:08
 */

class PTMTranslator extends Translator {

	public function __construct(Project $p) {
		parent::__construct();
		$this->initVariables($p->getProjectCode(), $p);
	}

	/**
	 * @param DOMDocument $doc
	 * @param $targetLanguage
	 */
	public function setLanguage($doc, $targetLanguage) {

		/** @var DOMElement $htmlElement */
		$htmlElement = $doc->documentElement;

		if ($htmlElement->tagName != "html") {
			// The root element is not html
			return ;
		}

		$htmlElement->setAttribute("lang", $targetLanguage);
	}

	static public function getClassName() {
		return __CLASS__;
	}

}