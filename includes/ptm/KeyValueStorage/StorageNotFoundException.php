<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 17:46
 */
class StorageNotFoundException extends Exception
{
	/**
	 * @var string
	 */
	private $storage;

	/**
	 * @param string $storage
	 * @param int $code
	 * @param Exception $previous
	 */
	public function __construct($storage, $code = 0, $previous = null) {
		parent::__construct($storage." itemType not found in StorageManager", $code/*, $previous*/);
		$this->storage = $storage;
	}

	/**
	 * @return string
	 */
	public function getStorage()
	{
		return $this->storage;
	}
}
