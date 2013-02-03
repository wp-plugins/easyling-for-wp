<?php
/**
 * User: Atesz
 * Date: 2013.01.22.
 * Time: 19:16
 */

class PTMException extends Exception {

	const LEVEL_INFO = 'INFO';
	const LEVEL_NOTICE = 'NOTICE';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_ERROR = 'ERROR';
	const LEVEL_FATAL_ERROR = 'FATAL_ERROR';

	/**
	 * @param Exception $previous
	 * @param int $errorLevel
	 * @param Exception $data
	 */
	public function __construct($previous, $errorLevel, $data) {
		parent::__construct($previous->message, $previous->code, $previous);
	}
}