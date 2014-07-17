<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:29
 */

class XMLAttributedString {

	public function __construct($rootElement = null) {
		if ($rootElement != null) {
			$builder = new XMLNodeIntervalListBuilder($rootElement);
			$this->intervals = $builder->getIntervals();
			$this->text = $builder->getText();
		}
	}

	/**
	 * @param string $text
	 * @param XMLNodeIntervalList $intervals
	 */
	static public function createByValues($text, $intervals) {
		$instance = new XMLAttributedString();
		$instance->text = $text;
		$instance->intervals = $intervals->shallowCopy();
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * @param int $start
	 * @param int $end
	 * @return XMLNodeInterval[]
	 */
	public function getNodesInRange($start, $end) {
		$firstIndex = $this->intervals->find($start);
		$lastIndex = ($start != $end - 1) ? $this->intervals->find($end - 1) : $firstIndex;

		if ($end < $this->intervals->getInterval($firstIndex)->getStart() || $this->intervals->getInterval($lastIndex)->getEnd() < $start) {
			return array();
		}

		return $this->intervals->getIntervals($firstIndex, $lastIndex+1);
	}

	/**
	 * @var string
	 */
	private $text;

	/** @var XMLNodeIntervalList  */
	private $intervals;
} 