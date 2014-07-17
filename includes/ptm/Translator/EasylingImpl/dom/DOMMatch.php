<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:14
 */

class DOMMatch {

	/**
	 * @param DOMDocument $document
	 * @param int $groupIndex
	 * @param string[] $slicedMatches
	 * @param MatchResult $match
	 */
	public function __construct($document, $groupIndex, $slicedMatches, $match) {
		$this->document = $document;
		$this->slicedMatches = $slicedMatches;
		$this->match = $match;
		$this->groupIndex = $groupIndex;
	}

	/**
	 * @return int
	 */
	public function getGroupIndex() {
		return $this->groupIndex;
	}

	/**
	 * @return MatchResult
	 */
	public function getMatch() {
		return $this->match;
	}

	/**
	 * @return string[]
	 */
	public function getSlicedMatches() {
		return $this->slicedMatches;
	}

	/**
	 * @param string $tagName
	 * @return DOMElement
	 */
	public function createElement($tagName) {
		return $this->document->createElement($tagName);
	}

	/**
	 * @param string $data
	 * @return DOMText
	 */
	public function createTextNode($data) {
		return $this->document->createTextNode($data);
	}

	/**
	 * @return DOMDocumentFragment
	 */
	public function createDocumentFragment() {
		return $this->document->createDocumentFragment();
	}

	/** @var string[] */
	private $slicedMatches;

	// TODO: regexp MatchResult
	/** @var MatchResult  */
	private $match;

	/** @var  DOMDocument */
	private $document;

	/** @var  int */
	private $groupIndex;
} 