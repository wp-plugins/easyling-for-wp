<?php
/**
 * User: Atesz
 * Date: 2014.06.25.
 * Time: 16:07
 */

class JList implements IteratorAggregate {

	private $list = array();

	public function getIterator()
	{
		return new ArrayIterator($this->list);
	}

	/**
	 * @param array|JList $list
	 */
	public function addAll($list) {
		foreach ($list as $element)  {
			$this->list[] = $element;
		}
	}

	/**
	 * @return array
	 */
	public function getElement() {
		return $this->list;
	}
}