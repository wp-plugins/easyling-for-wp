<?php
/**
 * User: Atesz
 * Date: 2012.12.10.
 * Time: 16:07
 */

require_once dirname(__FILE__) . '/KeyValueStorage/StringKey.php';
require_once dirname(__FILE__) . '/KeyValueStorage/ProjectPageKey.php';
require_once dirname(__FILE__) . '/KeyValueStorage/KeyValueStorageManager.php';
require_once dirname(__FILE__) . '/lib/HTML5/Parser.php';
require_once dirname(__FILE__) . '/PTMException.php';
require_once dirname(__FILE__) . '/Translator/includes.php';
require_once dirname(__FILE__) . '/Project/includes.php';
require_once dirname(__FILE__) . '/Util/includes.php';
require_once dirname(__FILE__) . '/EasylingService.php';
require_once dirname(__FILE__) . '/PTMService.php';
require_once dirname(__FILE__) . '/PTMClientService.php';

class PTM
{
	const EASYLING_HOST = null; // don't override EasylingService constants
	const ERROR_REPORTING_URL = "http://plugintest.skawa.hu/plugin-error-report.php";

	private $invalidAttributeNames = array("\"","#\"");

	/**
	 * @var KeyValueStorageManager|null
	 */
	private $storageManager = null;

	/**
	 * @var EasylingService|null
	 */
	private $easylingService = null;

	/**
	 * @var PTMService
	 */
	private $frameworkService = null;

	/**
	 * @var PTMClientService
	 */
	private $clientService = null;

	/**
	 * @var bool
	 */
	private $errorReportEnabled = false;

	/**
	 * @var PTM
	 */
	private static $instance = null;

	public static function get() {
		if (self::$instance === null) {
			self::$instance = new PTM();
		}

		return self::$instance;
	}

	private function __construct()
	{
		$this->storageManager = new KeyValueStorageManager();
	}

	public function install($p)
	{
		$fms = $this->getFrameworkService();
		if (is_array($p) || $p instanceof Map)
			$fms->setAvailableProjects($p);
		else if ($p instanceof Project)
			$fms->addAvailableProject($p);
	}

	public function uninstall()
	{
		$fms = $this->getFrameworkService();
		$fms->deleteOptions();

		$this->getProjectPageStorage()->removeAll();
	}

	/**
	 * @return KeyValueStorageManager|null
	 */
	public function getStorageManager()
	{
		return $this->storageManager;
	}

	/**
	 * @return KeyValueStorage
	 */
	public function getProjectPageStorage()
	{
		return $this->storageManager->getStorageForItemType(KeyValueStorage::ITEMTYPE_PROJECTPAGE);
	}

	/**
	 * @return KeyValueStorage
	 */
	public function getOptionStorage()
	{
		return $this->storageManager->getStorageForItemType(KeyValueStorage::ITEMTYPE_OPTION);
	}

	/**
	 * @param ProjectPageKey $projectPageKey
	 * @param TranslationEntriesMap $tem
	 */
/*	private function storeProjectPageTranslations($projectPageKey, $tem)
	{
		$ppStorage = $this->getProjectPageStorage();
		$ppStorage->put($projectPageKey, $tem);
	}
*/
	/**
	 * @return EasylingService|null
	 */
	public function getEasylingService()
	{
		if ($this->easylingService == null)
			$this->easylingService = EasylingService::getInstance(self::EASYLING_HOST);

		return $this->easylingService;
	}

/*	private function getMissingPageTranslation(Project $p, $targetLanguage)
	{
		$es = $this->getEasylingService();
		//$pts = $es->getProjectTranslations($p, $targetLanguage);
		$pts = $es->getProjectTranslationSlice($p, $targetLanguage);
		$this->mergeProjectTranslations($p, $targetLanguage, $pts);
	}
*/
	/**
	 * @param Project $p
	 * @param string $targetLanguage
	 * @param string $remoteURL
	 * @return TranslationEntriesMap
	 */
	private function getTranslationForPage(Project $p, $targetLanguage, $remoteURL)
	{
		$simpleURL = $p->simplifyURL($remoteURL);
		$ppKey = new ProjectPageKey($p->getProjectCode(), $targetLanguage, $simpleURL);
		if (!$this->getProjectPageStorage()->has($ppKey))
			return null;

		$tem = $this->getProjectPageStorage()->get($ppKey);

		return $tem;
	}

	public function parseImportedData($jsonData)
	{
		$dataArray = json_decode($jsonData, true);
		$projectCode = $dataArray['projectCode'];
		$targetLanguage = $dataArray['targetLanguage'];
		$entryList = $dataArray['entryList'];
		foreach ($entryList as $pageKey => $entries)
		{

		}
	}

	/**
	 * @param $htmlContent
	 * @param string $originalEncoding
	 * @return \DOMDocument
	 */
	public function parseHTMLContent($htmlContent, $originalEncoding = "UTF-8")
	{
		// 1) replace unix line ending for easyling compatibility
		// HTML5_InputStream do this
		//$htmlContent = str_replace("\r\n", "\n", $htmlContent);
		//$htmlContent = str_replace("\r", "\n", $htmlContent);

		// 2) convert to UTF-8 if not available
		// HTML5_InputStream also do this

		// 3) create custom HTML5_TreeBuilder
		$treeBuilder = new HTML5_TreeBuilder();
		$treeBuilder->setInvalidAttributeNames($this->invalidAttributeNames);
		$treeBuilder->unknownNSEnabled = true;

		// 4) create tokenizer
		$tokenizer = new HTML5_Tokenizer($htmlContent, $treeBuilder);
		$tokenizer->enableAsciiCPConversion(false);//strcasecmp($originalEncoding, "UTF-8") != 0);

		// 5) parse HTML content
		$tokenizer->parse();

		// 6) save DOM tree
		return $tokenizer->save();
	}

	/**
	 * @param bool $enabled
	 */
	public function enableErrorReporting($enabled = true)
	{
		// if we got null, doesn't change (default is false)
		if ($enabled === null)
			return ;
		$this->errorReportEnabled = $enabled;
	}

	/**
	 * @param Exception $e
	 * @param string $level
	 * @param mixed $data
	 * @return string
	 */
	public function sendErrorReport(Exception $e, $level = PTMException::LEVEL_WARNING, $data = null)
	{
		// is error reporint not allowed
		if (!$this->errorReportEnabled)
			return "DENY";

		//trigger_error("sadasd", E_USER_ERROR);
		$remoteAddr = $_SERVER['REMOTE_ADDR'];
		$httpHost = $_SERVER['HTTP_HOST'];
		$requestURL = $_SERVER['REQUEST_URI'];
		//$getParams = $_GET;

		$postData = http_build_query(
			array(
				'httpHost' => $httpHost,
				'remoteAddr' => $remoteAddr,
				'requestURL' => $requestURL,
				'stackTrace' => $e->getTraceAsString(),
				'exception' => $e->__toString(),
				'level'=> $level,
				'data'=> json_encode($data)
			)
		);

		$timeout = 2;

		if (in_array($level, array(PTMException::LEVEL_ERROR, PTMException::LEVEL_FATAL_ERROR)))
			$timeout = 5;

		$opts = array('http' =>
			array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'timeout' => $timeout,
				'content' => $postData
			)
		);

		$context  = stream_context_create($opts);

		return file_get_contents(self::ERROR_REPORTING_URL, false, $context);
	}

	/**
	 * @param Project $p
	 * @param $targetLanguage
	 * @return TranslationEntriesMap|null
	 */
	public function getGlobals(Project $p, $targetLanguage) {
		$globalsKey = new ProjectPageKey($p->getProjectCode(), $targetLanguage, "easyling://globals");

		if (!$this->getProjectPageStorage()->has($globalsKey))
			return null;

		return $this->getProjectPageStorage()->get($globalsKey);

	}

	/**
	 * @param Project $p
	 * @param string $targetLanguage
	 * @param string $url
	 * @return string|null
	 */
	public function getURLRedirection(Project $p, $targetLanguage, $url) {
		$globals = $this->getGlobals($p, $targetLanguage);

		if ($globals == null)
			return null;

		/** @var TranslationEntry[]|Map $te */
		$te = $globals->get($url);

		if ($te == null)
			return null;

		// get the first targetEntry
		return $te->getIterator()->current()->getTarget();
	}

	public function translateProjectPage(Project $p, $remoteURL, $htmlContent, $targetLanguage,
	                                     $resourceMap = array())
	{

		try {
			// get translations for page
			$tes = $this->getTranslationForPage($p, $targetLanguage, $remoteURL);

			// if no available translation for page, try group pages
			if ($tes == null) {
				$ignoreRules = new PathIgnoreRules($p->getPathIgnoreRules());
				$simpleURL = $p->simplifyURL($remoteURL);

				// remove protocol and domain if exists
				$simplePath = preg_replace('%^https{0,1}://[^/]*%', '', $simpleURL);

				$matchedRule = $ignoreRules->getMatchedRule($simplePath);
				if ($matchedRule !== null) {
					$fullRule = $p->getPathURL($matchedRule);
					$tes = $this->getTranslationForPage($p, $targetLanguage, $fullRule);
				}
			}

			// if no available translation for page, start with an empty map
			if ($tes == null) {
				$tes = new Map();
			}

			$manuals = array();

			$globals = $this->getGlobals($p, $targetLanguage);

			// get easyling globals
			if ($globals != null) {
				foreach ($globals as $globalOriginal=>$tem) {
					$tes->put($globalOriginal, $tem);
				}
			}

			// add cms dependent translations
			foreach ($resourceMap as $resourceOriginal => $resourceTarget)
			{
				$tes->put($resourceOriginal,array(new TranslationEntry(array('o'=>$resourceOriginal,
					't'=>$resourceTarget,'eu'=>$resourceOriginal,'p'=>''))));
			}

			// if no translation entry added, return the original content
			if ($tes->isEmpty())
				return $htmlContent;

			$dom = $this->parseHTMLContent($htmlContent);

			$translator = new PTMTranslator($p);
			$translator->setManualEntries($manuals);
			$translator->setNormalizedLookup($tes);
			$translator->setIgnoreRegexp($p->getIgnoreRegexp());
			$translator->translateDocument($dom);

			$translator->setLanguage($dom, $targetLanguage, $p);

			$htmlContent = "<!DOCTYPE html>\n".$dom->saveHTML();
			return $htmlContent;
		}
		catch (Exception $e)
		{
			// send report, continue serving...
			self::sendErrorReport($e);
		}

		return $htmlContent;
	}

	/**
	 * @param string|string[] $headers
	 * @param string $response
	 * @return bool
	 */
	public function isResponseTranslatable($headers, $response) {

		$responseHeaders = new HTTPResponseHeaders($headers);

		$contentType = $responseHeaders->getContentType();

		if ($contentType == null)
			return true;

		return $contentType->isHTML();
	}

	/**
	 * @return PTMClientService
	 */
	public function getClientService()
	{
		if ($this->clientService == null)
			$this->clientService = new PTMClientService($this->getOptionStorage(), $this->getProjectPageStorage());
		return $this->clientService;
	}

	/**
	 * @return PTMService
	 */
	public function getFrameworkService()
	{
		if ($this->frameworkService == null)
			$this->frameworkService = new PTMService($this->getOptionStorage(), $this->getProjectPageStorage());
		return $this->frameworkService;
	}
}
