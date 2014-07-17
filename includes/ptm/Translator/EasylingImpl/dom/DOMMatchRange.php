<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:15
 */

class DOMMatchRange {

	public function __construct($text, $textNode, $rangeStart, $rangeEnd, $offset) {
		$this->text = $text;
		$this->textNode = $textNode;
		$this->rangeStart = $rangeStart;
		$this->rangeEnd = $rangeEnd;
		$this->offset = $offset;
	}

	/**
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * @return int
	 */
	public function getRangeEnd() {
		return $this->rangeEnd;
	}

	/**
	 * @return int
	 */
	public function getRangeStart() {
		return $this->rangeStart;
	}

	/**
	 * @return String
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * @return \DOMNode
	 */
	public function getTextNode() {
		return $this->textNode;
	}

	/** @var  String */
	private $text;

	/** @var  DOMNode */
	private $textNode;

	/** @var  int  */
	private $rangeStart;

	/** @var  int  */
	private $rangeEnd;

	/** @var  int */
	private $offset;
}