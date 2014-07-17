<?php

/**
 * User: Atesz
 * Date: 2014.06.24.
 * Time: 12:46
 */

class BlockXMLBuilder {

	/** @var  Pattern */
	private $dontTranslatePattern;

	/** @var  Pattern */
	private $ignorePattern;

	/** @var StringSet */
	private $blockElements;

	/** @var  DOMDocument */
	private $document;

	/** @var  Pattern */
	private $noTranslateClassMatcher;

	/** @var NodeSet */
	private $handledMissingNodes;

	/** @var IntToNodeMap  */
	private $idToHTMLNode;

	const MARK_TEXT_NODES_AS_HANDLED = false;

	public function __construct(/* @param DOMDocument */ $document, /* @param Pattern */ $dontTranslatePattern,
	                            /* @param Pattern */ $noTranslateClassMatcher, /* @param Pattern */ $ignorePattern,
								/* @param StringSet */ $blockElements) {
		$this->handledMissingNodes = new NodeSet();
		$this->idToHTMLNode = new IntToNodeMap();
		$this->document = $document;
		$this->dontTranslatePattern = $dontTranslatePattern;
		$this->ignorePattern = $ignorePattern;
		$this->blockElements = $blockElements;
		$this->noTranslateClassMatcher = $noTranslateClassMatcher;
	}

	/**
	 * @param DOMElement $htmlNode
	 * @return DOMElement
	 */
	public function buildBlockXML($htmlNode)
	{
		$this->resetXML();
		$this->handledMissingNodes->clear();
		$this->idToHTMLNode->clear();

		/* @var DOMElement */ $xmlNode = $this->document->documentElement;

		$index = $this->buildBlockXMLFromIndex($htmlNode, $xmlNode, 0);

		if ($this->ignorePattern != null){
			/* @var RegexpDOMReplacer $regexpDomReplacer */
			$regexpDomReplacer = new RegexpDOMReplacer($index, $htmlNode->ownerDocument, $this->idToHTMLNode);

			DOMSearch::replaceAll($xmlNode, $this->ignorePattern, $regexpDomReplacer);

			$replacementAnnotation = $regexpDomReplacer->getReplacementAnnotation();
			if ($replacementAnnotation!="") {
				$htmlNode->setAttribute("regexp-easyling-matches", $replacementAnnotation);
			}
		}
		return $xmlNode;
	}

	/**
	 * @param NodeSet $handledMissingNodes
	 * @param IntToNodeMap $idToHTMLNode
	 */
	public function mergeNodes($handledMissingNodes, $idToHTMLNode) {
		$handledMissingNodes->addAll($this->handledMissingNodes);
		$idToHTMLNode->putAll($this->idToHTMLNode);
	}

	/**
	 * @param DOMElement $htmlNode
	 * @param DOMElement $xmlParent
	 * @param int $idIndex
	 * @return int
	 */
	private function buildBlockXMLFromIndex($htmlNode, $xmlParent, $idIndex)
	{
		// this element should be skipped (it was marked __ptNoTranslate, or it is
		// a block that should be ignored, e.g. a script element)
		if(!Translator::shouldProcessElement($htmlNode, $this->noTranslateClassMatcher))
		{
			$this->appendNodeWithIdAndAttrs($xmlParent, $htmlNode, "x", $idIndex++, "ctype", "x-placeholder");
			return $idIndex;
		}

		if(self::MARK_TEXT_NODES_AS_HANDLED)
		{
			// mark the text node as handled missing
			for(/*DOMNode */ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling)
			{
				if($n->nodeType == Node::TEXT_NODE)
					$this->handledMissingNodes->add($n);
			}
		}

		// check if contents are not translatable anyway
		/** @var bool $translatable */
		$translatable = false;
		for(/*DOMNode */ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling)
		{
			if($n->nodeType == Node::ELEMENT_NODE && Translator::shouldProcessElement($n, $this->noTranslateClassMatcher))
			{
				// whoops, we should process this node
				$translatable = true;
				break;
			}
			else if($n->nodeType == Node::TEXT_NODE)
			{
				if(!$this->dontTranslatePattern->matcher($n->nodeValue)->matches() &&
					Translator::shouldProcessContent($htmlNode))
				{
					// we should definitely translate this text node
					$translatable = true;
					break;
				}
			}
		}

		// we haven't found anything to translate
		// the node will become an empty <x/>
		if(!$translatable)
		{
			$this->appendNodeWithIdAndAttrs($xmlParent, $htmlNode, "x", $idIndex++, "ctype", "x-prune");
			return $idIndex;
		}

		// mark this idIndex, because we might back down,
		// and replace the whole thing with an <x/>,
		// and we want the id generation to depend only
		// on translatable content
		/** @var int $thisIndex */
		$thisIndex = $idIndex++;

		/** @var DOMElement $parent */
		$parent = $this->appendNodeWithId($xmlParent, $htmlNode, "g", $thisIndex);

		for(/*DOMNode */ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling)
		{
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				// we won't process block level elements, because those will be processed elsewhere
				if($this->blockElements->contains(strtolower($n->nodeName)))
					$this->appendNodeWithIdAndAttrs($parent, $n, "x", $idIndex++, "ctype", "x-block");
				else
					$idIndex = $this->buildBlockXMLFromIndex($n, $parent, $idIndex);
			} else if($n->nodeType == Node::TEXT_NODE)
			{
				// if it isn't something translatable, replace it with an <x/>
				$content = $n->nodeValue;
				if($this->dontTranslatePattern->matcher($content)->matches() || !Translator::shouldProcessContent($htmlNode))
					$this->appendNodeWithIdAndAttrs($parent, $n, "x", $idIndex++, "ctype", "x-number", "equiv-text", $content);
				else
					$parent->appendChild($xmlParent->ownerDocument->createTextNode($content));
			}
		}

		$translatable = false;

		// prune if it doesn't contain any translatable elements
		for(/*DOMNode */ $n = $parent->firstChild; $n != null; $n = $n->nextSibling)
		{
			if($n->nodeType == Node::TEXT_NODE || "x"!=$n->nodeName)
			{
				$translatable = true;
				break;
			}
		}

		if($translatable)
		{
			// keep
			return $idIndex;
		} else
		{
			// prune, reset id counter
			$xmlParent->removeChild($parent);
			if($this->idToHTMLNode != null)
				$this->idToHTMLNode->clearRange($thisIndex, $idIndex);
			$this->appendNodeWithId($xmlParent, $htmlNode, "x", $thisIndex++);
			return $thisIndex;
		}
	}

	/**
	 * @param DOMElement $parentNode
	 * @param DOMNode $htmlRelatedNode
	 * @param string $nodeName
	 * @param int $idIndex
	 * @return DOMElement
	 */
	private function appendNodeWithId($parentNode, $htmlRelatedNode, $nodeName,	$idIndex) {
		return $this->appendNodeWithIdAndAttrs($parentNode, $htmlRelatedNode, $nodeName, $idIndex, null);
	}


	/**
	 * @param DOMElement $parentNode
	 * @param DOMNode $htmlRelatedNode
	 * @param string $nodeName
	 * @param int $idIndex
	 * @return DOMElement
	 */
	private function appendNodeWithIdAndAttrs($parentNode, $htmlRelatedNode, $nodeName, $idIndex) {
		/** @var DOMElement */ $e = $parentNode->ownerDocument->createElement($nodeName);
		$e->setAttribute("id", StringBuilder::create("_")->append($idIndex)->toString());
		$attrs = array_slice(func_get_args(), 4);
		if($attrs != null && $attrs[0] != null)
		{
			for($i=0; $i<count($attrs); $i+=2)
				$e->setAttribute($attrs[$i], $attrs[$i+1]);
		}

		$parentNode->appendChild($e);

		if($this->idToHTMLNode != null)
			$this->idToHTMLNode->put($idIndex, $htmlRelatedNode);

		return $e;
	}

	private function resetXML() {
		$this->resetXMLWithRoot($this->document->createElement("block"));
	}

	/**
	 * @param DOMElement $newRoot
	 */
	private function resetXMLWithRoot($newRoot) {
		if($this->document->firstChild != null)
			$this->document->removeChild($this->document->firstChild);
		$this->document->appendChild($newRoot);
	}

}
