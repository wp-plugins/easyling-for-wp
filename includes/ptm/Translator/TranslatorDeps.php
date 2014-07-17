<?php
/**
 * User: Atesz
 * Date: 2012.12.11.
 * Time: 16:34
 */

class WebProxy {
	/**
	 * @var StringSet
	 */
	public static $IGNORED_TAGS;

	/**
	 * @var StringSet
	 */
	public static $IGNORED_TAG_CONTENT;
}

WebProxy::$IGNORED_TAGS = immutableTightSet("script", "style", "noscript", "easyling:x");
WebProxy::$IGNORED_TAG_CONTENT = immutableTightSet("textarea");

class XMLUtil {

	/**
	 * @param string|null $source
	 * @return DOMDocument
	 */
	public static function createDocument($source = null) {
		$domDocument = new DOMDocument();
		if ($source != null)
			$domDocument->loadXML($source);
		return $domDocument;
	}
}

class StringSet
{
	private $container = array();

	public function __construct($set = null)
	{
		if ($set !== null)
			$this->container = $set;
	}

	public function contains($str)
	{
		return in_array($str, $this->container);
	}

	public function add($str)
	{
		if (!$this->contains($str))
			$this->container[] = $str;
	}
}

class Lists {
	/**
	 * @return JList
	 */
	static public function newArrayList() {
		return new JList(func_get_args());
	}
}

/**
 * @return StringSet
 */
function immutableTightSet()
{
	return new StringSet(func_get_args());
}

function parseInt($str)
{
	return (int)$str;
}

function PTMAssert($assertion, $description)
{
	assert($assertion);
}