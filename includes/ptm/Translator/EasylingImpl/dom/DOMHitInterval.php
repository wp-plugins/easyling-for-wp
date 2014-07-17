<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 17:32
 */

class DOMHitInterval {

	/**
	 * @param int $start
	 * @param int $end
	 */
	public function __construct($start, $end) {
		$this->start = $start;
		$this->end = $end;
	}

	/**
	 * @param DOMHitInterval $other
	 * @return bool
	 */
	public function overlapping($other) {
		if ($this->end <= $other->start || $other->start <= $this->start) {
			return false;
		}

		return true;
	}

	private $start;
	private $end;
} 