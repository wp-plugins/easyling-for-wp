<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 17:51
 */

require_once dirname(__FILE__) . '/KeyValueStorage.php';

class FileStorage extends KeyValueStorage
{
	public function __construct($itemType, $dirName = null)
	{
		parent::__construct($itemType);
		if ($dirName === null)
			$dirName = getcwd() . DIRECTORY_SEPARATOR . strtolower($itemType);
		$this->createStorageDirectory($dirName);
		$this->realPath = realpath($dirName);
	}

	/**
	 * @param Key $key
	 * @return string
	 */
	private function getRealKeyPath($key)
	{
		return $this->realPath . DIRECTORY_SEPARATOR . $this->cleanupKey($key);
	}

	public function getStoragePath()
	{
		return $this->realPath;
	}

	public function removeStorage()
	{
		$this->removeAll();
		@rmdir($this->getStoragePath());
	}

	protected function _removeAll()
	{
		$files = glob($this->getStoragePath() . '/*'); // get all file names
		foreach ($files as $file) {
			if (is_file($file))
				unlink($file);
		}
	}

	protected function _put($key, $value)
	{
		file_put_contents($this->getRealKeyPath($key), $this->serializeValue($value));
	}

	protected function _get($key)
	{
		return $this->unserializeValue(file_get_contents($this->getRealKeyPath($key)));
	}

	protected function _has($key)
	{
		return file_exists($this->getRealKeyPath($key));
	}

	protected function _remove($key)
	{
		@unlink($this->getRealKeyPath($key));
	}

	protected function _lock()
	{
		// TODO: implement for file system (.lck file)
	}

	protected function _unlock()
	{

	}

	private function serializeValue($val)
	{
		return serialize($val);
	}

	private function unserializeValue($val)
	{
		return unserialize($val);
	}

	/**
	 * @param Key $key
	 * @return mixed
	 */
	private function cleanupKey($key)
	{
		$fixFileName = preg_replace('/[^a-z0-9_\-\.]/i', '_', $key->serialize());
		return strtolower($fixFileName);
	}

	/**
	 * Create the directory
	 * @param $dir
	 */
	private function createStorageDirectory($dir)
	{
		$dir = trim($dir, DIRECTORY_SEPARATOR);
		$pathParts = explode(DIRECTORY_SEPARATOR, $dir);
		for ($i=0;$i<count($pathParts);$i++) {
			$createDirName = implode(DIRECTORY_SEPARATOR, array_slice($pathParts, 0, $i+1));
			if (!file_exists($createDirName))
				mkdir($createDirName);
		}
	}

	/**
	 * The storage directory
	 * @var string|null
	 */
	private $realPath = null;

}