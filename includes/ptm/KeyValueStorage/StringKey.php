<?php
/**
 * User: Atesz
 * Date: 2012.12.11.
 * Time: 13:10
 */

require_once dirname(__FILE__) . '/Key.php';

class StringKey implements Key
{
	/**
	 * @var string
	 */
	private $key;

	/**
	 * @param string $key
	 */
	public function __construct($key)
	{
		$this->key = $key;
	}

	/**
	 * @return string
	 */
	public function serialize()
	{
		return $this->key;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->serialize();
	}
}
