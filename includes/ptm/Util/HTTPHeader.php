<?php
/**
 * User: Atesz
 * Date: 2014.07.10.
 * Time: 13:13
 */

abstract class HTTPHeaders {
	protected $rawHeaders = null;
	protected $parsedHeaders = array();
}

class HTTPResponseHeaders extends HTTPHeaders {

	const CONTENT_TYPE = 'content-type';
	const LOCATION = 'location';

	private $responseCode = 200;

	public function __construct($headers) {

		if (is_string($headers)) {
			$headers = explode("\n", $headers);
		}

		$this->rawHeaders = array();

		if (is_array($headers)) {
			foreach ($headers as $k=>$value) {

				$value = trim($value);
				if (empty($value))
					continue;

				if (is_numeric($k)) {
					$line = explode(":", $value, 2);
					$k = $line[0];
					$value = $line[1];
				}

				$k = trim($k);
				$value = trim($value);
				$k = strtolower($k);

				$this->rawHeaders[$k] = $value;
			}
		}
	}

	/**
	 * @param string $header
	 * @return HTTPHeader|null
	 */
	public function getHeader($header) {
		if (!isset($this->rawHeaders[$header]))
			return null;

		if (isset($this->parsedHeaders[$header]))
			return $this->parsedHeaders;

		$parsedHeader = HTTPHeader::create($header, $this->rawHeaders[$header]);
		$this->parsedHeaders[$header] = $parsedHeader;
		return $parsedHeader;
	}

	/**
	 * @return string[]
	 */
	public function getRawHeaders() {
		return $this->rawHeaders;
	}

	/**
	 * @return HTTPContentType
	 */
	public function getContentType() {
		return $this->getHeader(self::CONTENT_TYPE);
	}

	/**
	 * @return int
	 */
	public function getResponseCode() {
		return $this->responseCode;
	}

}

class HTTPHeader {

	protected $header;
	protected $rawValue;

	protected function parse() {

	}

	/**
	 * @param string $header
	 * @param string $rawValue
	 */
	private function __construct($header, $rawValue) {
		$this->rawValue = $rawValue;
		$this->parse();
	}

	/**
	 * @param string $header
	 * @return string
	 */
	private static function getClassForHeader($header) {
		$header  = str_replace("-"," ",$header);
		$header  = ucfirst($header);
		$className = "HTTP".str_replace(" ","",$header);
		if (class_exists($className)) {
			return $className;
		} else
			return "HTTPHeader";
	}

	/**
	 * @return string
	 */
	public function getValue() {
		return $this->rawValue;
	}

	/**
	 * @param string $header
	 * @param string $rawValue
	 * @return HTTPHeader
	 */
	public static function create($header, $rawValue) {
		$className = self::getClassForHeader($header);
		return new $className($header, $rawValue);
	}

	/**
	 * @param string[] $params
	 * @param string $delimiter
	 * @return string[]
	 */
	protected function parseParams($params, $delimiter = "=") {
		$parsedParams = array();
		foreach ($params as $param) {
			$param = explode($delimiter, trim($param));
			if (isset($param[1])) {
				$pKey = strtolower(trim($param[0]));
				$pValue = trim($param[1]);
				$parsedParams[$pKey] = $pValue;
			}
		}

		return $parsedParams;
	}
}

class HTTPContentType extends HTTPHeader {

	const MIME_HTML = 'text/html';
	const MIME_XML = 'text/xml';

	const CHARSET_ISO_8859_1 = "iso-8859-1";
	const CHARSET_UTF_8 = "utf-8";

	private $mimeType;
	private $charset = self::CHARSET_ISO_8859_1;

	protected function parse() {
		$line = explode(";", $this->rawValue);
		$this->mimeType = strtolower(trim($line[0]));

		$params = $this->parseParams(array_slice($line, 1));

		if (isset($params["charset"])) {
			$this->charset = strtolower($params["charset"]);
		}
	}

	public function getMimeType() {
		return $this->mimeType;
	}

	public function getCharset() {
		return $this->charset;
	}

	/**
	 * @return bool
	 */
	public function isHTML() {
		return $this->mimeType == self::MIME_HTML;
	}
}