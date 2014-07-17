<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 18:07
 */

class RegexpDOMReplacer {

	public function __construct($idIndex, $document, $idToHTMLNode) {
		$this->idIndex = $idIndex;
		$this->document = $document;
		$this->idToHTMLNode = $idToHTMLNode;
	}

	public function getReplacementAnnotation() {
		return $this->replaceAnnotation;
	}

	/**
	 * @param DOMMatch $match
	 * @return DOMReplacement[]
	 */
	public function apply($match) {
		/** @var DOMReplacement[] $replacements */
		$replacements = array();

		foreach ($match->getSlicedMatches() as $m) {
			$tempElementForReplace = $this->document->createTextNode($m);

			$this->idToHTMLNode->put($this->idIndex, $tempElementForReplace);

			$replaceElement = $match->createElement("x");
			$replaceElement->setAttribute("id", "_".$this->idIndex++);
			$replaceElement->setAttribute("equiv-text", $m);
			$replacements[] = new DOMReplacement($replaceElement, null);
		}

		return $replacements;
	}

	/** @var  int */
	private $idIndex;
	/** @var  DOMDocument */
	private $document;

	/** @var string  */
	private $replaceAnnotation = "";

	/** @var  IntToNodeMap */
	private $idToHTMLNode = null;
}