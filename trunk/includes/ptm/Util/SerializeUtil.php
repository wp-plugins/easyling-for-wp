<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 21:34
 */

class SerializeException extends Exception
{

}

class SerializeUtil
{
	public static function serialize($obj)
	{
		return serialize($obj);
	}

	/**
	 * @param $str
	 * @return mixed
	 * @throws SerializeException
	 */
	public static function unserialize($str)
	{
		set_error_handler(array('SerializeUtil', 'unserializeErrorHandler'), E_NOTICE);
		try {
			$obj = unserialize($str);
			restore_error_handler();
			return $obj;
		} catch (SerializeException $se) {
			restore_error_handler();
			throw $se;
		}
	}

	public static function unserializeErrorHandler()
	{
		throw new SerializeException("Cannot unserialize string");
	}
}
