<?php
/**
 * User: Atesz
 * Date: 2012.12.14.
 * Time: 12:46
 */
class StringBuilder
{
	private $str;

	public function __construct($str)
	{
		$this->str = $str;
	}

	/**
	 * @param $str
	 * @return StringBuilder
	 */
	public function append($str)
	{
		return new StringBuilder($this->str . $str);
	}

	/**
	 * @return string
	 */
	public function toString()
	{
		return $this->str;
	}

	/**
	 * @param $str
	 * @return StringBuilder
	 */
	public static function create($str)
	{
		return new StringBuilder($str);
	}
}
