<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:23
 */

class MatchResult {

	private $result = array();

	public function __construct($result) {
		$this->result = $result;
	}

	public function end($group = null) {
		if ($group === null)
			return $this->result[0][1] + strlen($this->group());
		return $this->result[$group][1] + strlen($this->group($group));
	}

	public function start($group = null) {
		if ($group === null)
			return $this->result[0][1];
		return $this->result[$group][1];
	}

	public function group($group = null) {
		if ($group === null)
			return $this->result[0][0];
		return $this->result[$group][0];
	}

	public function groupCount() {
		return count($this->result);
	}
} 