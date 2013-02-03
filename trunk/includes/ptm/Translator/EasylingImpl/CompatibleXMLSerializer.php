<?php
/**
 * User: Atesz
 * Date: 2012.12.13.
 * Time: 14:36
 */
class CompatibleXMLSerializer
{
	const NONSERIALIZABLE_PREFIX = "_elns_";

	public function serializeContents(DOMNode $node)
	{
		$retVal = "";
		foreach($node->childNodes as $n)
		{
			/** @var DOMNode $n */
			switch($n->nodeType)
			{
				case Node::ELEMENT_NODE:
					$retVal .= $this->serializeElement($n);
				break;
				case Node::TEXT_NODE:
					$retVal .= EncodeUtil::htmlEscape($n->nodeValue);
				default:
					// we skip over these
			}
		}

		return $retVal;
	}

	public function serializeElement(DOMElement $e)
	{
		$retVal = "<".EncodeUtil::htmlEscape($e->nodeName);

		/** @var DOMNamedNodeMap $attrs */
		$attrs = $e->attributes;

		$attrArray = array();
		for ($i=0;$i<$attrs->length;$i++)
		{
			/** @var DOMAttr $attr  */
			$attr = $attrs->item($i);
			$attrArray[$attr->name] = $attr->value;
		}

		ksort($attrArray);

		foreach ($attrArray as $attrName => $attrValue)
		{
			if (strncmp($attrName, self::NONSERIALIZABLE_PREFIX, strlen(self::NONSERIALIZABLE_PREFIX))!=0)
				$retVal .= " ".EncodeUtil::htmlEscape($attrName)."=\"".EncodeUtil::htmlEscape($attrValue)."\"";
		}

		if($e->firstChild != null)
		{
			$retVal .= ">";
			$retVal .= $this->serializeContents($e);
			$retVal .= "</".EncodeUtil::htmlEscape($e->nodeName).">";
		} else
		{
			$retVal .= "/>";
		}

		return $retVal;
	}

	public function serializeDocument(DOMDocument $doc)
	{
		return $this->serializeElement($doc->documentElement);
	}

	public function serializeDocumentText(DOMDocument $doc)
	{
		return $this->serializeElementText($doc->documentElement);
	}

	public function serializeElementText(DOMElement $e)
	{
		$retVal = "";
		foreach($e->childNodes as $n)
		{
			/** @var DOMNode $n */
			switch($n->nodeType)
			{
				case Node::ELEMENT_NODE:
					$retVal .= $this->serializeElementText($n);
					break;
				case Node::TEXT_NODE:
					$retVal .= $n->nodeValue;
				default:
					// we skip over these
			}
		}

		return $retVal;
	}

	public function serializeText(DOMNode $node)
	{
		if($node->nodeType == Node::DOCUMENT_NODE)
			return $this->serializeDocumentText($node);
		if($node->nodeType == Node::ELEMENT_NODE)
			return $this->serializeElementText($node);
		if($node->nodeType == Node::TEXT_NODE)
			return $node->nodeValue;

		return "";
	}
}
