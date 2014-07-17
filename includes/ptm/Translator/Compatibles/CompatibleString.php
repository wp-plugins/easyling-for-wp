<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 17:26
 */
class CompatibleString
{
	private $str;

	/**
	 * @var Map
	 */
	static private $storage;

	public function __construct($str)
	{
		$this->str = $str;
	}

	public function __toString()
	{
		return $this->str;
	}

	/**
	 * @return int
	 */
	public function length()
	{
		return mb_strlen($this->str);
	}

	/**
	 * @param $idx
	 * @return string
	 */
	public function charAt($idx)
	{
		return $this->str{$idx};
	}

	/**
	 * @param $start
	 * @param int|null $endIndex
	 * @return CompatibleString
	 */
	public function substring($start, $endIndex = null)
	{
		if ($endIndex === null)
			return mb_substr($this->str, $start);
		$length = $endIndex - $start;
		return mb_substr($this->str, $start, $length);
	}

	/**
	 * @return string
	 */
	public function toLowerCase()
	{
		return mb_strtolower($this->str);
	}

	/**
	 * @param $str
	 * @return string
	 */
	public function concat($str)
	{
		return $this->str.$str;
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		return empty($this->str);
	}

	/**
	 * @return bool
	 */
	public function isNull() {
		return $this->str === null;
	}

	/**
	 * @param $str
	 * @return CompatibleString
	 */
	public static function getStr($str) {
		if (!isset(self::$storage[$str]))
			self::$storage[$str] = new CompatibleString($str);

		return self::$storage[$str];
	}
}
