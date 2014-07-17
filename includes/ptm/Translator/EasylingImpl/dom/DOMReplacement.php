<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:14
 */

class DOMReplacement {

	/**
	 * @param DOMNode $node
	 * @param string $text
	 */
	public function __construct($node, $text) {
		$this->node = $node;
		$this->text = $text;
	}

	/**
	 * @return DOMNode
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/** @var DOMNode */
	private $node;

	/** @var String */
	private $text;
} 