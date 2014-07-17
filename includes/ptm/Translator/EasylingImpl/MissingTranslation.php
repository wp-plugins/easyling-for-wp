<?php
/**
 * User: Atesz
 * Date: 2012.12.12.
 * Time: 17:01
 */

class MissingTranslation {

	public function getOriginal() {
		return $this->original;
	}

	public function setOriginal($original) {
		$this->original = $original;
	}

	public function getPath() {
		return $this->path;
	}

	public function setPath($path) {
		$this->path = $path;
	}

	public function __toString() {
		return sprintf("original: \"%s\", normalized: \"%s\", path: \"%s\"", $this->original, $this->path);
	}

	/**
	 * @var string
	 */
	private $original;

	/**
	 * @var string
	 */
	private $path;
}
