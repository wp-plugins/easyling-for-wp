<?php
/**
 * User: Atesz
 * Date: 2014.07.09.
 * Time: 12:57
 */

class LoggerFactory {

	static private $loggers = array();

	public static function getLogger($name) {
		if (isset(self::$loggers[$name])) {
			return self::$loggers[$name];
		}

		$logger = new Logger($name);
		self::$loggers[$name] = $logger;
		return $logger;
	}
}

class Logger {

	private $name;

	public function __construct($name) {
		$this->name = $name;
	}

	/**
	 * @param string $message
	 * @param Exception $ex
	 */
	public function warn($message, $ex = null) {
		error_log($message." thrown by ".$this->name);
		if ($ex != null) {
			error_log(get_class($ex)." thrown with message ".$ex->getMessage());
		}
	}
}