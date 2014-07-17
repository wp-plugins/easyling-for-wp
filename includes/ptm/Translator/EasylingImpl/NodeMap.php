<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 18:42
 */
class NodeMap
{
	protected $map = array();

	/**
	 * @param DOMNode|string $key
	 * @param $value
	 */
	public function put($key, $value)
	{
		$this->map[$this->getKeyString($key)] = $value;
	}

	/**
	 * @param DOMNode|string $key
	 * @return bool
	 */
	public function contains($key)
	{
		return isset($this->map[$this->getKeyString($key)]);
	}

	/**
	 * @param DOMNode|string $key
	 * @return null
	 */
	public function get($key)
	{
		if (!$this->contains($key)) {
			/*var_dump($this->map);
			$msg = "map with key ".$key->getNodePath()." not found";
			echo $msg;
			trigger_error($msg,E_ERROR);*/
			return null;
		}

		return $this->map[$this->getKeyString($key)];
	}

	/**
	 * @param DOMNode|string $key
	 * @throws InvalidArgumentException
	 * @return string
	 */
	private function getKeyString($key)
	{
		if (is_string($key))
			return $key;
		else if (is_object($key) && $key instanceof DOMNode)
			return DOMUtil::getNodeUniqueKey($key);

		throw new InvalidArgumentException("getKeyString got ".gettype($key)." instead of string or DOMNode");
	}

	public function clear() {
		$this->map = array();
	}

	public function putAll(NodeMap $nodeMap) {
		foreach ($nodeMap as $key=>$value) {
			$this->put($key, $value);
		}
	}

}
