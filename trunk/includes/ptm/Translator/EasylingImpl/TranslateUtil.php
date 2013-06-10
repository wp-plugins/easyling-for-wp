<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 17:02
 */

require_once 'EncodeUtil.php';

class TranslateUtil
{
	public static /*NormalizedText*/ function normalizePlainText(/*String*/ $plainText)
	{
		return new NormalizedText(EncodeUtil::normalizeSpaces($plainText), null);
	}
//
//	public static /*NormalizedText*/ function normalizeXML(/*String*/ $xml)
//	{
//		CompatibleXMLSerializer serializer = new CompatibleXMLSerializer();
//		return normalizeXML(serializer, xml);
//	}
//
//	public static /*NormalizedText*/ function normalizeXML(/*Node*/ xml)
//	{
//		CompatibleXMLSerializer serializer = new CompatibleXMLSerializer();
//		return normalizeXML(serializer, xml);
//	}
//
//	public static /*NormalizedText*/ function normalizeXML(/*CompatibleXMLSerializer*/ serializer, /*String*/ xml)
//	{
//		Node root = TranslateUtil.xmlRoot(xml);
//		return normalizeXML(serializer, root);
//	}
//
//	public static /*NormalizedText*/ function normalizeXML(/*CompatibleXMLSerializer*/ serializer, /*Node*/ root)
//	{
//		if(root.getNodeType() != Node.DOCUMENT_NODE)
//			root = root.getOwnerDocument();
//		String normalized = serializer.serializeText(root);
//		normalized = EncodeUtil.normalizeSpaces(normalized);
//		return new NormalizedText(normalized, getXMLSignature(root));
//	}

	public static function getXMLSignature(DOMNode $root)
	{
		$builder = new XMLSignatureBuilder($root);
		return $builder->getSignature();
	}

	/**
	 * @param CompatibleXMLSerializer $serializer
	 * @param DOMNode $root
	 * @return NormalizedText
	 */
	public static function normalizeXML($serializer, $root)
	{
		if($root->nodeType != XML_DOCUMENT_NODE)
			$root = $root->ownerDocument;
		$normalized = $serializer->serializeText($root);
		$normalized = EncodeUtil::normalizeSpaces($normalized);
		return new NormalizedText($normalized, self::getXMLSignature($root));
	}

	/**
	 * @param string $xmlValue
	 * @return DOMElement|null
	 */
	public static function xmlRoot($xmlValue)
	{
		$doc = XMLUtil::createDocument("<?xml version=\"1.0\" encoding=\"UTF-8\"?><block>".
			$xmlValue."</block>");
		return $doc == null ? null : $doc->documentElement->firstChild;
	}

	public static /*int*/ function getXMLNodeCount(DOMNode $xml)
	{
		if($xml == null)
			return 0;
		/** @var DOMDocument $xml */

		if($xml->nodeType == Node::DOCUMENT_NODE)
			$xml = $xml->documentElement;
		if($xml->nodeType != Node::ELEMENT_NODE)
			return 0;

		$maxValue = 0;
		/** @var DOMElement $xe  */
		$xe = $xml;

		foreach ($xe->childNodes as $n)
		{
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				/** @var DOMElement $n */
				$id = $n->getAttribute("id");
				if($id != null)
				{
					$i = substr($id,1);
					if (!ctype_digit($i))
						continue;
					$maxValue = Math::max($maxValue, $i);
				}
				$maxValue = Math::max($maxValue, self::getXMLNodeCount($n));
			}
		}

		return $maxValue+1;
	}

	protected static /*Map<String, String>*/ function getXMLNodeTypes(DOMNode $xml, Map $result)
	{
		if($xml == null)
			return $result;
		/** @var DOMDocument $xml */
		if($xml->nodeType == Node::DOCUMENT_NODE)
			$xml = $xml->documentElement;
		if($xml->nodeType != Node::ELEMENT_NODE)
			return $result;

		/** @var DOMElement $xe  */
		$xe = $xml;
		$id = $xe->getAttribute("id");
		if($id != null)
			$result->put($id, $xe->nodeName);
		foreach ($xe->childNodes as $n)
		{
			/** @var DOMNode $n */
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				self::getXMLNodeTypes($n, $result);
			}
		}

		return $result;
	}

	public static function checkXMLNodeCount(DOMNode $xml, $count)
	{
		$isRoot = null;
		$xml = XMLSignatureBuilder::getDocumentRoot($xml, $isRoot);

		if (is_bool($xml))
			return $xml;

		$xe=$xml;
		foreach ($xe->childNodes as $n)
		{
			/** @var $n DOMElement*/
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				$id = $n->getAttribute("id");
				if(($n != $xml->ownerDocument->documentElement || $n->nodeName != "block") && $id == null)
					return false;

				if($id !== null)
				{
					$i = substr($id, 1);
					if (!ctype_digit($i))
						return false;

					if ($i<=0 || $i>=$count)
						return false;
				}

				$result = self::checkXMLNodeCount($n, $count);
				if(!$result)
					return false;
			}
		}

		return true;
	}

	public static function checkXMLNodeTypes(DOMNode $xml, Map $map)
	{
		$isRoot = null;
		$xml = XMLSignatureBuilder::getDocumentRoot($xml, $isRoot);

		if (is_bool($xml))
			return $xml;

		if ($isRoot)
		{
			if (!($map->get("_0")=="g"))
				return false;
			/* assert "g".equals(map.get("_0")) : "Root is a <g id=\"_0\"/>";
			map.remove("_0");*/
			$map->remove("_0");
		}

		foreach ($xml->childNodes as $n)
		{
			/** @var $n DOMElement */
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				$id = $n->getAttribute("id");
				if($id === null || $n->nodeName != $map->get($id))
					return false;

				$map->remove($id);

				$result = self::checkXMLNodeTypes($n, $map);
				if(!$result)
					return false;
			}
		}

		if($isRoot)
		{
			// this was the root, map should be empty
			if(!$map->isEmpty())
				return false;
		}

		return true;
	}

	public static /*boolean*/ function isCompatible(DOMNode $originalXML, DOMNode $xml) {

		/*boolean */$result = false;
		if($xml != null)
		{
			//$xmlSignature = new XMLSignatureBuilder($originalXML);
			$idCount = self::getXMLNodeCount($originalXML);
			if(self::checkXMLNodeCount($xml, $idCount))
			{
				//Map<String, String>
				$nodeTypes = self::getXMLNodeTypes($originalXML, new Map());
				$result = self::checkXMLNodeTypes($xml, $nodeTypes);
			}

		}

		return $result;
	}

	/**
	 * @param string $source
	 * @return NormalizedText
	 */
	public static function normalizeXMLByString($source)
	{
		$serializer = new CompatibleXMLSerializer();
		$root = self::xmlRoot($source);
		return self::normalizeXML($serializer, $root);
	}
}
