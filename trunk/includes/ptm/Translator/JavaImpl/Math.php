<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 18:53
 */
class Math
{
	public static function min()
	{
		return min(func_get_args());
	}

	public static function max()
	{
		return max(func_get_args());
	}
}
