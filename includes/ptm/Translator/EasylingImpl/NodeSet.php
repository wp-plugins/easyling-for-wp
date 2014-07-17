<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 18:31
 */

class NodeSet extends NodeMap implements IteratorAggregate
{
	public function add(DOMNode $node)
	{
		parent::put($node, $node);
	}

	public function addAll(NodeSet $nodeSet) {
		parent::putAll($nodeSet);
	}

	public function getIterator()
	{
		return new ArrayIterator($this->map);
	}
}
