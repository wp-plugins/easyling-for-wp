<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 19:47
 */

class TranslationEntriesMap extends Map
{
	/**
	 * @param $key
	 * @return TranslationEntry[]
	 */
	public function get($key) {
		return parent::get($key);
	}
}
