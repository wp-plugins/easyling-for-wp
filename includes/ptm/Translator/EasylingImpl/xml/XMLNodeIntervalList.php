<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:30
 */

class XMLNodeIntervalList {

	public function __construct($list = array()) {
		$this->list = $list;
	}

	public function getLength() {
		return count($this->list);
	}

	/**
	 * @param int $idx
	 * @return XMLNodeInterval
	 */
	public function getInterval($idx) {
		return $this->list[$idx];
	}

	/**
	 * @param int $start
	 * @param int $end
	 * @return XMLNodeInterval[]
	 */
	public function getIntervals($start, $end) {

		$length = $this->getLength();

		$start = min($start, $length);
		$end = min($end, $length);

		// java List.subList is exclusive for end
		// the original implementation uses the original list (set in constructor)
		return array_slice($this->list, $start, $end-$start);
	}

	/**
	 * @param int $pos
	 * @return int
	 */
	public function find($pos) {
		$insertionPoint = $this->search(new XMLNodeInterval($pos, $pos+1, null));
		if ($insertionPoint < 0)
			return -$insertionPoint-1;

		return $insertionPoint;
	}

	/**
	 * @param XMLNodeInterval $newInterval
	 */
	public function add($newInterval) {
		if ($this->interval == null) {
			$this->interval = $newInterval;
			$this->list[] = $newInterval;
		} else {
			/** @var XMLNodeInterval $oldInterval */
			$oldInterval = $this->interval;

			$this->interval = $this->interval->getEnclosingInterval($newInterval);
			if ($oldInterval->getEnd() <= $newInterval->getStart()) {
				$this->list[] = $newInterval;
				return ;
			}

			$insertIndex = $this->search($newInterval);

			// insert to $insertIndex position
			$this->list = array_merge(array_slice($this->list, 0, $insertIndex),array($newInterval),array_slice($this->list, $insertIndex + 1));
		}
	}

	/**
	 * @return XMLNodeIntervalList
	 */
	public function shallowCopy() {
		return new XMLNodeIntervalList($this->list);
	}

	/**
	 * @param XMLNodeInterval $interval
	 * @return int
	 */
	private function search($interval) {

		// binary search by comparing end positions

		$start = 0;
		$end = count($this->list) - 1;

		while ($start <= $end) {
			$middle = (int) (($start + $end) >> 1);
			$current = $this->list[$middle];
			if ($interval->getEnd() < $current->getEnd()) {
				$end = $middle - 1;
			} else if ($interval->getEnd() > $current->getEnd()) {
				$start = $middle + 1;
			} else {
				return $middle;
			}
		}

		return -($start + 1);
	}

	/**
	 * @var XMLNodeInterval[]
	 */
	private $list;

	/**
	 * @var XMLNodeInterval
	 */
	private $interval;
}

class XMLNodeIntervalListBuilder {
	public function __construct($rootElement) {
		$this->intervals = new XMLNodeIntervalList();
		$this->allText = new CompatibleString("");

		if (self::$IGNORED_NODES == null)
			self::$IGNORED_NODES = immutableTightSet("script", "style");

		$this->append($rootElement);
	}

	/**
	 * @param DOMElement $element
	 */
	private function append($element) {

		for ($child = $element->firstChild; $child != null; $child = $child->nextSibling) {
			if ($child->nodeType == Node::ELEMENT_NODE) {
				if (!self::$IGNORED_NODES->contains(strtolower($child->nodeName))) {
					/** @var DOMElement $child */
					if ($child->hasAttribute('equiv-text')) {
						$text = $child->getAttribute('equiv-text');
						$this->allText = new CompatibleString($this->allText->concat($text));
					} else {
						$this->append($child);
					}
				}
			} else {
				if ($child->nodeType == NODE::TEXT_NODE) {
					$text = new CompatibleString($child->nodeValue);
					$startPos = $this->allText->length();
					$endPos = $startPos + $text->length();
					$this->allText = new CompatibleString($this->allText->concat($text));
					$this->intervals->add(new XMLNodeInterval($startPos, $endPos, $child));
				}
			}
		}
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->allText;
	}

	/**
	 * @return XMLNodeIntervalList
	 */
	public function getIntervals() {
		return $this->intervals;
	}

	/**
	 * @var CompatibleString
	 */
	private $allText = null;

	/**
	 * @var XMLNodeIntervalList
	 */
	private $intervals = null;

	/** @var StringSet  */
	public static $IGNORED_NODES = null;
}
