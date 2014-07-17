<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:31
 */

class XMLNodeInterval {

	/**
	 * @param int $start
	 * @param int $end
	 * @param DOMNode $node
	 */
	public function __construct($start, $end, $node) {
		$this->start = $start;
		$this->end = $end;
		$this->node = $node;
	}

	/**
	 * @return int
	 */
	public function getEnd() {
		return $this->end;
	}

	/**
	 * @return DOMNode
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * @return int
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @param XMLNodeInterval $other
	 * @return bool
	 */
	public function contains($other) {
		return $other->getStart() >= $this->start && $other->getEnd() <= $this->end;
	}

	/**
	 * @param XMLNodeInterval $other
	 * @return bool
	 */
	public function overlaps($other) {
		return $this->start < $other->getEnd() && $this->end > $other->getStart();
	}

	/**
	 * @return bool
	 */
	public function isEmpty() {
		return $this->start >= $this->end;
	}

	/**
	 * @param XMLNodeInterval $other
	 * @param XMLNodeInterval $newNode
	 * @return XMLNodeInterval
	 */
	public function getEnclosingInterval($other, $newNode = null) {
		if ($newNode == null) {
			$newNode = $this->node;
		}

		if ($this->contains($other) && $this->node === $newNode)
			return $this;

		return new XMLNodeInterval(
			min($this->start, $other->getStart()),
			max($this->end, $other->getEnd()),
			$newNode);
	}

	/** @var  int */
	private $start;

	/** @var  int */
	private $end;

	/** @var  DOMNode */
	private $node;
} 