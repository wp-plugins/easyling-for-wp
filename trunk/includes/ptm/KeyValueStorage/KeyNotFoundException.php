<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 17:23
 */

class KeyNotFoundException extends Exception {
	public function __construct($message = "", $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}