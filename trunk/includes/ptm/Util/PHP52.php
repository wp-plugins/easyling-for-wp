<?php
/**
 * User: Atesz
 * Date: 2012.12.16.
 * Time: 16:43
 */

if (!function_exists('spl_object_hash'))
{
	function spl_object_hash($obj)
	{
		if (is_object($obj)) {
			ob_start(); var_dump($obj); $dump = ob_get_contents(); ob_end_clean();
			if (preg_match('/^object\(([a-z0-9_]+)\)\#(\d)+/i', $dump, $match)) {
				return md5($match[1] . $match[2]);
			}
		}
		trigger_error(__FUNCTION__ . "() expects parameter 1 to be object", E_USER_WARNING);
		return null;
	}
}

if (!function_exists('json_decode'))
{
	/**
	 * @param string $str
	 * @param bool $assoc
	 * @return mixed
	 */
	function json_decode($str, $assoc = false)
	{
		$jsonService = new Services_JSON($assoc ? SERVICES_JSON_LOOSE_TYPE : 0);
		return $jsonService->decode($str);
	}
}

if (!function_exists('json_encode'))
{
	/**
	 * @param mixed $var
	 * @return string
	 */
	function json_encode($var)
	{
		$jsonService = new Services_JSON();
		return $jsonService->encode($var);
	}
}