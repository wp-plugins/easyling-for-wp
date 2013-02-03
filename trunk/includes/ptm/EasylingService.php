<?php
/**
 * User: Atesz
 * Date: 2012.12.17.
 * Time: 13:06
 */
class EasylingService
{
	const DEFAULT_ENDPOINT_HOST = "app.easyling.com";
	const DEFAULT_ENDPOINT_PATH = "_el/ext/ptm";

	/*private $endPointHost;
	private $endPointPath;*/

	private $endPoint;

	public function getEndPointURL()
	{
		return $this->endPoint;
		//return "http://".$this->endPointHost."/".$this->endPointPath;
	}

	private function __construct($endPointHost,
	                             $endPointPath) {
		//$this->endPoint = $endPoint;
		$this->endPoint = "http://".$endPointHost."/".$endPointPath;
	}

	/**
	 * @param string $response
	 * @return TranslationEntriesMap[]
	 */
	public function getTranslationEntriesMapsByResponse($response)
	{
            $response = json_decode($response, true);
//		$project = $response['project'];
		//$projectName = $project['name'];
//		$ignoredParams = $project['ignored'];
//		$aliases = $project['aliases'];
//		$cursor = $response['cursor'];
		$dictionary = $response['dictionary'];
                file_put_contents('/tmp/dic.txt', json_encode($response));

//		$p->setIgnoredParams($ignoredParams);
//		$p->setHostAliases($aliases);

		/** @var TranslationEntriesMap[] $pageTranslations  */
		$pageTranslations = array();
		foreach ($dictionary as $simpleURL=>$transEntries)
		{
			$tem = new TranslationEntriesMap();

			foreach ($transEntries as $transEntry)
			{
				$te = new TranslationEntry($transEntry);
				$ns = $te->getNormalizedSource();
				$nst = $ns->toString();//getNormalizedString();
				$tes = null;
				if (!$tem->contains($nst)) {
					$tes = new Map();//ArrayObject();
					$tem->put($nst, $tes);
				}
				else
					$tes = $tem->get($nst);

				//$tes->append($te);
				$tes->put($te->getKey(), $te);
			}

			$pageTranslations[$simpleURL] = $tem;
		}

		return $pageTranslations;
	}

	/**
	 * @param Project $p
	 * @param $targetLanguage
	 * @param null $cursor
	 * @return TranslationEntriesMap[]
	 */
	public function getProjectTranslationSlice(Project $p, $targetLanguage, &$cursor = null)
	{
		$jsonResponse = $this->callService("slice", array("projectCode"=>$p->getProjectCode(),
			"targetLanguage"=>$targetLanguage, "cursor"=>$cursor));

		$response = $this->getResponseFromJSONResponse($jsonResponse);
		return $this->getTranslationEntriesMapsByResponse($response);
	}

	/**
	 * @param Project $project
	 * @param string $response
	 */
	public function setProjectLanguagesByResponse($project, $response)
	{
		$languagesArray = $this->getResponseFromJSONResponse($response);
		$prLanguages = array();
		foreach ($languagesArray as $language) {
			if (is_array($language)) {
				$prLang = new ProjectLanguage($language["name"], $language["published"]);
			} else {
				$prLang = new ProjectLanguage($language);
			}

			$prLanguages[] = $prLang;
		}

		$project->setLanguages($prLanguages);
	}

	public function setAvailableProjectsByResponse($response)
	{
		$projectsArray = $this->getResponseFromJSONResponse($response);

		// TODO: implement

//		return new Map();

		return null;
	}

	private function getResponseFromJSONResponse($easylingJSONResponse)
	{
		$response = json_decode($easylingJSONResponse, true);
		if ($response == null || !isset($response['response']))
		{
			throw new Exception("no valid response format");
		}
		return $response['response'];
	}


	/*	public function getProjectTranslations(Project $p, $targetLanguage)
		{
			$cursor = null;
			$aggregatedPageTranslations = array();
			$startTime = time();
			do {
				$pageTranslations = $this->getProjectTranslationSlice($p, $targetLanguage, $cursor);
				if (count($pageTranslations)==0)
					break;
				$aggregatedPageTranslations = $pageTranslations;
			}while (count($pageTranslations)>0 && time()-$startTime<30);
			return $aggregatedPageTranslations;
		}
	*/
	/**
	 * @param string $endPointHost
	 * @param string $endPointPath
	 * @return EasylingService
	 */
	public static function getInstance($endPointHost = null, $endPointPath = null) {
		if ($endPointHost == null)
			$endPointHost = self::DEFAULT_ENDPOINT_HOST;
		if ($endPointPath == null)
			$endPointPath = self::DEFAULT_ENDPOINT_PATH;

		return new EasylingService($endPointHost, $endPointPath);
	}

	private function callService($cmd, $params)
	{
		$query = http_build_query($params);
		return file_get_contents($this->getEndPointURL()."/".$cmd."?".$query);
	}

}
