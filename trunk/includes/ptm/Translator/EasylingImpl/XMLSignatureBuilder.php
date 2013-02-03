<?php
/**
 * User: Atesz
 * Date: 2012.12.13.
 * Time: 10:31
 */
class XMLSignatureBuilder
{
	/**
	 * @var bool
	 */
	private $valid;

	/**
	 * @var string
	 */
	private $signature;


	//private Map<Integer, String> nodeTypes = new HashMap<Integer, String>();
	private $nodeTypes = array();

	private $count = 0;
	private $nodesFound = 0;

	static $validNodeNames = array("g","x");

	public function __construct(DOMNode $root) {
		$this->valid = $this->checkNode($root) && $this->count == $this->nodesFound;
		$this->signature = $this->valid ? $this->buildSignature() : null;
		$this->nodeTypes = array();
	}

	public /*String*/ function getSignature() {
		if(!$this->valid)
			return null;

		return $this->signature;
	}

	private /*String*/ function buildSignature() {
		foreach($this->nodeTypes as $index=>$node)
		{
			if($index < 0 || $index >= $this->count)
			{
				$this->valid = false;
				return null;
			}
		}

		return implode("",$this->nodeTypes);
	}

	public static function getDocumentRoot(DOMNode $xml, &$isRoot = null)
	{
		if($xml === null) {
			$xml = null;
			return false;
		}

		/** @var DOMDocument $xml */
		if($xml->nodeType == Node::DOCUMENT_NODE)
			$xml = $xml->documentElement;

		if($xml == $xml->ownerDocument->documentElement && $xml->nodeName == "block")
			$xml = $xml->firstChild;

		/*boolean*/
		$isRoot = $xml->parentNode == null ||
			$xml->parentNode == $xml->ownerDocument ||
			"block"==$xml->parentNode->nodeName;

		if($xml->nodeType != Node::ELEMENT_NODE)
			return true;

		/** @var DOMElement $xe  */
		$xe = $xml;
		if($isRoot && ("_0"!=$xe->getAttribute("id") || "g"!=$xml->nodeName)) {
			$isRoot = true;
			return false;
		}

		return $xe;
	}

	private /*boolean*/ function checkNode(DOMNode $xml) {

		$isRoot = null;
		$xml = self::getDocumentRoot($xml, $isRoot);
		if (is_bool($xml)) {
			$isRoot = false;
			return $xml;
		}

		$xe = $xml;
		if($isRoot)
		{
			/*String */$id = $xe->getAttribute("id");

			if($id === null || strlen($id) <= 1)
			{
				$id = 4;
				return false;
			}

			$index = substr($id, 1);
			if (!ctype_digit($index)) {
				$index = 5;
				return false;
			}

			if(!isset($this->nodeTypes[$index]))
			{
				$this->nodeTypes[$index] = $xml->nodeName;
				++$this->nodesFound;
			}

			$this->count = Math::max($index+1, $this->count);
		}

		//for(Node n=xe.getFirstChild(); n != null; n = n.getNextSibling())
		for ($n=$xe->firstChild;$n!=null;$n=$n->nextSibling)
		{
			if($n->nodeType == Node::ELEMENT_NODE)
			{
				/** @var DOMElement $ne */
				$ne = $n;
				$id = $ne->getAttribute("id");
				if($id != null)
				{
					if(!in_array($ne->nodeName, self::$validNodeNames) || strlen($id) <= 1) {
						$id =7;
						return false;
					}

					$i = substr($id, 1);
					if (!ctype_digit($i)) {
						$i = 8;
						return false;
					}

					if(isset($this->nodeTypes[$i])) {
						$i = 4;
						return false;
					}

					if($this->count <= $i)
						$this->count = $i+1;
					$this->nodeTypes[$i] = $ne->nodeName;
					++$this->nodesFound;
				}
				else
				{
					if(in_array($ne->nodeName, self::$validNodeNames)) {
						$i = 2;
						return false;
					}
				}

				/*boolean */ $result = $this->checkNode($n);
				if(!$result) {
					$result = false;
					return false;
				}
			}
		}

		return true;
	}

}
