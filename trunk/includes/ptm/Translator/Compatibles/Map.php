<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 19:41
 */
class Map implements IteratorAggregate, ArrayAccess
{
	private $map = array();

	private function isAssocArray($array)
	{
		return array_keys($array) !== range(0, count($array) - 1);
	}

	public function __construct($map = null)
	{
		// valid associative array
		if ($map !== null && is_array($map) && $this->isAssocArray($map)) {
			$this->map = $map;
		}
		// numeric-indexed array
		else if (is_array($map))
		{
			foreach ($map as $item)
			{
				if ($item instanceof HasMapKey)
				{
					$this->map[$item->getMapKey()] = $item;
				}
				else throw new Exception("numeric indexed array without implementing HasMapKey interface");
			}
		}
	}

	public function get($key)
	{
		if (!isset($this->map[$key]))
			return null;

		return $this->map[$key];
	}

	public function put($key, $value)
	{
		$this->map[$key] = $value;
	}

	public function remove($key)
	{
		unset($this->map[$key]);
	}

	public function contains($key)
	{
		return isset($this->map[$key]);
	}

	public function isEmpty()
	{
		return empty($this->map);
	}

	public function getIterator()
	{
		return new ArrayIterator($this->map);
	}

	public function offsetExists($offset)
	{
		return $this->contains($offset);
	}

	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	public function offsetSet($offset, $value)
	{
		$this->put($offset, $value);
	}
	public function offsetUnset($offset)
	{
		unset($this->map[$offset]);
	}
}
