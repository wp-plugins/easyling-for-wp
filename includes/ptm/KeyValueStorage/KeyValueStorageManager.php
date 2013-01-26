<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 17:26
 */

require_once dirname(__FILE__) . '/KeyValueStorage.php';
require_once dirname(__FILE__) . '/StorageNotFoundException.php';

class KeyValueStorageManager {

	/**
	 * @var KeyValueStorage[]
	 */
	private $storages = array();

	/**
	 * @param $itemType
	 * @param KeyValueStorage $storage
	 */
	public function setStorageForItemType($itemType, $storage) {
		$this->storages[$itemType] = $storage;
	}

	/**
	 * @param $itemType
	 * @return KeyValueStorage
	 * @throws StorageNotFoundException
	 */
	public function getStorageForItemType($itemType) {
		if (isset($this->storages[$itemType])) {
			return $this->storages[$itemType];
		}

		throw new StorageNotFoundException($itemType);
	}
}