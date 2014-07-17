<?php
/**
 * User: Atesz
 * Date: 2014.06.25.
 * Time: 11:03
 */

class HtmlMissingTranslation extends MissingTranslation {

	/**
	 * @param DOMNode $n
	 */
	public function setHtmlNode(DOMNode $n) {
		$this->htmlNode = $n;
	}

	/**
	 * @return DOMNode
	 */
	public function getHtmlNode() {
		return $this->htmlNode;
	}

	/**
	 * @var DOMNode
	 */
	private $htmlNode;
} 