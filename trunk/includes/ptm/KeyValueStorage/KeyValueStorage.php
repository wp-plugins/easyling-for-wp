<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 17:20
 */

require_once dirname(__FILE__) . '/Key.php';
require_once dirname(__FILE__) . '/KeyNotFoundException.php';

abstract class KeyValueStorage {

	const ITEMTYPE_OPTION = "OPTION";
	const ITEMTYPE_PROJECTPAGE = "PROJECTPAGE";
	const ITEMTYPE_TMPEP = "TMPEP";

	private $itemType = null;

	/**
	 * @param Key|string $key
	 * @return Key|
	 */
	private function fixKey($key) {

		if (is_string($key))
			return new StringKey($key);

		return $key;
	}

	public function __construct($itemType) {
		$this->itemType = $itemType;
	}

	/**
	 * @param Key|string $key
	 * @param mixed $value
	 */
	public function put($key, $value) {
		$key = $this->fixKey($key);
		$this->_put($key, $value);
	}

	/**
	 * @param Key|string $key
	 * @return mixed
	 * @throws KeyNotFoundException
	 */
	public function get($key) {
		$key = $this->fixKey($key);
		if (!$this->_has($key))
			throw new KeyNotFoundException();
		return $this->_get($key);
	}

	/**
	 * @param Key|string $key
	 * @return bool
	 */
	public function has($key) {
		$key = $this->fixKey($key);
		return $this->_has($key);
	}

	/**
	 * @param Key|string $key
	 * @param bool $ignoreMissing
	 * @return mixed
	 * @throws KeyNotFoundException
	 */
	public function remove($key, $ignoreMissing = false) {
		$key = $this->fixKey($key);
		if (!$this->_has($key) && !$ignoreMissing)
			throw new KeyNotFoundException();
		return $this->_remove($key);
	}

	public function lock()
	{
		$this->_lock();
	}

	public function unlock()
	{
		$this->_unlock();
	}

	public function removeAll()
	{
		$this->_removeAll();
	}

	// @codeCoverageIgnoreStart

	/**
	 * @param Key $key
	 * @param string $value
	 * @return mixed
	 */
	abstract protected function _put($key, $value);

	/**
	 * @param Key $key
	 * @return mixed
	 */
	abstract protected function _get($key);

	/**
	 * @param Key $key
	 * @return bool
	 */
	abstract protected function _has($key);

	/**
	 * @param Key $key
	 * @return mixed
	 */
	abstract protected function _remove($key);

	/**
	 * @return mixed
	 */
	abstract protected function _removeAll();

	abstract protected function _lock();

	abstract protected function _unlock();

	// @codeCoverageIgnoreEnd
}