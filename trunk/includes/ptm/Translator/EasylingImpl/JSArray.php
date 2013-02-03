<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 18:47
 */
class JSArray implements IteratorAggregate
{
	private $list;

	public function push($obj)
	{
		$this->list[] = $obj;
	}

	public function length()
	{
		return count($this->list);
	}

	public function getIterator()
	{
		return new ArrayIterator($this->list);
	}
}
