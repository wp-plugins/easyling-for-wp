<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 19:11
 */


class IntToNodeMap {

	private $map = array();

	public function get($key) {
		if (!isset($this->map[$key]))
			return null;
		return $this->map[$key];
	}

	public function clearRange($start, $end) {
		for ($i=$start;$i<$end;$i++) {
			if (isset($this->map[$i]))
				unset($this->map[$i]);
		}
	}

	public function put($key, DOMNode $value) {
		$this->map[$key] = $value;
	}
}
