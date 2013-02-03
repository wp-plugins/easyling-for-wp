<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 12:47
 */

class PathBuilder
{
	/**
	 * @var NodeMap
	 */
	private $index;

	public function __construct(DOMDocument $doc) {
		$this->index = new NodeMap();
		$this->index->put($doc->documentElement, -1);
		$this->markNodes($doc->documentElement);
	}

	public function /*String*/ nodePath(DOMNode $n)
	{
		if($n == null)
			return "";

		$elements = array();
		while($n != null && $n->nodeType != XML_DOCUMENT_NODE)
		{
			$s = $this->nodeName($n).":".$this->nodeIndex($n);
			$elements[]=$s;
			if($n instanceof DOMAttr)
				$n = $n->ownerElement;
			else
				$n = $n->parentNode;
		}

		$elements = array_reverse($elements);
		$result = "";
		foreach ($elements as $element) {
			$result .= $element."/";
		}
		$result = substr($result, 0, -1);
		/*ListIterator<String> it = elements.listIterator(elements.size());
		while(it.hasPrevious())
		{
			if(result.length() > 0)
				result.append('/');
			result.append(it.previous());
		}*/

		return $result;
	}

	private function /*int */nodeIndex(DOMNode $n)
	{
		/*$key = $this->getNodeIndexKey($n);
		if (isset($this->index[$key]))
			return $this->index[$key];*/

		$keyString = DOMUtil::getNodeUniqueKey($n);

		if ($this->index->contains($keyString))
			return $this->index->get($keyString);

		if($n->nodeType == XML_TEXT_NODE)
		{
			/*int*/ $offset = 0;
			//for(Node s = n.getPreviousSibling(); s != null; s = s.getPreviousSibling())
			/** @var DOMNode $s  */
			for($s = $n->previousSibling; $s != null; $s = $s->previousSibling)
				$offset++;

			return $offset;
		}

		return -1;
	}

	private function /*String */ nodeName(DOMNode $n)
	{
		if($n->nodeType == XML_TEXT_NODE)
			return "#text";
		if($n->nodeType == XML_ATTRIBUTE_NODE)
			return "%".strtolower($n->nodeName);
		return strtolower($n->nodeName);
	}

	/**
	 * @param DOMElement $e
	 * @param $enumerate
	 */
	private function /*void */markNodes($e, /*boolean*/ $enumerate = false) {

		/*String */ $nodeName = strtolower($e->nodeName);

		if($nodeName == "body")
			$enumerate = true;

		if(WebProxy::$IGNORED_TAGS->contains($nodeName))
			return;

		/*int*/ $count = $enumerate ? 0 : -1;

		/** @var DOMNode $n  */
		//for(Node n=e.getFirstChild(); n != null; n = n.getNextSibling())
		for($n=$e->firstChild; $n != null; $n = $n->nextSibling)
		{
			if($n->nodeType == XML_ELEMENT_NODE)
			{
				$name = strtolower($n->nodeName);
				if(!WebProxy::$IGNORED_TAGS->contains($name))
				{
					//$this->index[$this->getNodeIndexKey($n)] = $count;
					$this->index->put($n, $count);

					if($enumerate)
						$count++;

					$this->markNodes($n, true);
				}
			}
		}
	}
}
