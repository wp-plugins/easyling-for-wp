<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 21:00
 */

require_once dirname(__FILE__).'/EasylingService.php';

class PTMService
{
	const OPTION_AVAILABLE_PROJECTS = 'availableProjects';
	const OPTION_APPKEY = 'appKey';

	/**
	 * @var KeyValueStorage
	 */
	private $optionStorage = null;

	/**
	 * @var KeyValueStorage
	 */
	private $projectPageStorage = null;

	/**
	 * @var EasylingService
	 */
	private $easylingService = null;

	/**
	 * @var Map|Project[]
	 */
	private $availableProjects = null;

	public function __construct($optionStorage, $projectPageStorage)
	{
		$this->optionStorage = $optionStorage;
		$this->projectPageStorage = $projectPageStorage;
		$this->easylingService = EasylingService::getInstance();
	}

	public function deleteOptions()
	{
		$this->optionStorage->remove(self::OPTION_APPKEY, true);
		$this->optionStorage->remove(self::OPTION_AVAILABLE_PROJECTS, true);
	}

	/**
	 * @return string
	 */
	public function getAppKey()
	{
		return $this->optionStorage->get(self::OPTION_APPKEY);
	}

	/**
	 * @param $appKey
	 */
	public function setAppKey($appKey)
	{
		$this->optionStorage->put(self::OPTION_APPKEY, $appKey);
	}

	/**
	 * @param Map $projects
	 */
	public function setAvailableProjects($projects)
	{
		if (is_array($projects))
			$projects = new Map($projects);
		$this->saveAvailableProjects($projects);
	}

	/**
	 * @param string $easylingResponse
	 * @return \Map|\Project[]|null
	 */
	public function setAvailableProjectsByELResponse($easylingResponse)
	{
		$this->saveAvailableProjects(
			$this->easylingService->setAvailableProjectsByResponse($easylingResponse));

		return $this->availableProjects;
	}

	/**
	 * @param Project $p
	 * @param string $targetLanguage
	 * @param TranslationEntriesMap[] $tem
	 */
	private function mergeProjectTranslations(Project $p, $targetLanguage, $tem)
	{
		$projectPageStorage = $this->projectPageStorage;
		$projectPageStorage->lock();
		foreach ($tem as $simpleURL=>$newTranslationEntriesMap)
		{
			$simpleURL = preg_replace('/@(\d+)$/', '', $simpleURL);

			$modified = false;

			// fetch simple URL from existing db
			$projectPageKey = new ProjectPageKey($p->getProjectCode(),$targetLanguage, $simpleURL);

			$projectPageKeyString = $projectPageKey->serialize();
			if ($projectPageStorage->has($projectPageKeyString))
			{
				/** @var TranslationEntriesMap $currentTranslationEntriesMap */
				$currentTranslationEntriesMap = $projectPageStorage->get($projectPageKeyString);

				foreach ($newTranslationEntriesMap as $normalizedSource=>$newTranslationEntries) {
					if ($currentTranslationEntriesMap->contains($normalizedSource)) {
						/** @var Map $currentTranslationEntriesForNS  */
						$currentTranslationEntriesForNS = $currentTranslationEntriesMap->get($normalizedSource);
						/*if ($currentTranslationEntriesForNS == null)
							assert('invalid previous ns: '.$normalizedSource);*/
						/** @var TranslationEntry[] $newTranslationEntries */
						foreach ($newTranslationEntries as $newTranslationEntry)
						{
							$ntek = $newTranslationEntry->getKey();
							$ntet = $newTranslationEntry->getTarget();
							if ($currentTranslationEntriesForNS->contains($ntek))
							{
								/** @var TranslationEntry $oldTranslationEntry  */
								$oldTranslationEntry = $currentTranslationEntriesForNS->get($ntek);

								if ($oldTranslationEntry->getTarget() != $ntet) {
									$oldTranslationEntry->setTarget($ntet);
									$modified = true;
								}
							}
							else {
								$currentTranslationEntriesForNS->put($ntek, $newTranslationEntry);
								$modified = true;
							}
						}
					}
					else {
						$currentTranslationEntriesMap->put($normalizedSource, $newTranslationEntries);
						$modified = true;
					}
				}
			}
			else
			{
				$currentTranslationEntriesMap = $newTranslationEntriesMap;
				$modified = true;
			}

			if ($modified)
				$projectPageStorage->put($projectPageKeyString, $currentTranslationEntriesMap);
		}
		$projectPageStorage->unlock();
	}

	/**
	 * @param string $projectCode
	 * @param string $targetLanguage
	 * @param string $easylingResponse
	 */
	public function setProjectTranslationByELResponse($projectCode, $targetLanguage, $easylingResponse)
	{
		try {
			$p = $this->getProjectByCode($projectCode);
			$tem = $this->easylingService->getTranslationEntriesMapsByResponse($easylingResponse, $p);
			$this->mergeProjectTranslations($p, $targetLanguage, $tem);
		}
		catch (Exception $e)
		{
			PTM::sendErrorReport($e);
		}
	}

	/**
	 * @param Project $project
	 * @param string $easylingResponse
	 */
	public function setProjectAttributesByELResponse($project, $easylingResponse) {
		//$projectAttributes = $easylingResponse['project'];

		$this->easylingService->setProjectAttributesByResponse($project, $easylingResponse);
		$this->saveAvailableProjects();
	}

	/**
	 * @param Project $project
	 */
	public function addAvailableProject($project)
	{
		$projects = $this->getAvailableProjects();
		$projects->put($project->getProjectCode(), $project);
		$this->saveAvailableProjects();
	}

	/**
	 * @param string $projectCode
	 */
	public function removeAvailableProjectByCode($projectCode)
	{
		$projects = $this->getAvailableProjects();
		$projects->remove($projectCode);
		$this->saveAvailableProjects();
	}

	/**
	 * @param bool $forceOption
	 * @return Project[]|Map
	 */
	public function getAvailableProjects($forceOption = false)
	{
		if (!$forceOption && $this->availableProjects instanceof Map) {
			return $this->availableProjects;
		}
		if (!$this->optionStorage->has(self::OPTION_AVAILABLE_PROJECTS))
		{
			$this->availableProjects = new Map();
		}
		else $this->availableProjects = $this->optionStorage->get(self::OPTION_AVAILABLE_PROJECTS);

		return $this->availableProjects;
	}

	/**
	 * @param string $projectCode
	 * @param string $easylingResponse
	 * @return ProjectLanguage[]
	 */
	public function setProjectLanguagesByELResponse($projectCode, $easylingResponse)
	{
		$project = $this->getProjectByCode($projectCode);
		$this->easylingService->setProjectLanguagesByResponse($project, $easylingResponse);
		$this->saveAvailableProjects();

		return $project->getProjectLanguages();
	}

	/**
	 * @param string $projectCode
	 * @return ProjectLanguage[]
	 */
	public function getProjectLanguages($projectCode)
	{
		$project = $this->getProjectByCode($projectCode);

		if ($project === null)
			return array();

		return $project->getProjectLanguages();
	}

	/**
	 * @param $projectCode
	 * @return Project|null
	 */
	public function getProjectByCode($projectCode)
	{
		return $this->getAvailableProjects()->get($projectCode);
	}

	/**
	 * @param Map|Project[] $p
	 */
	private function saveAvailableProjects($p = null)
	{
		if ($p != null) $this->availableProjects = $p;
		$this->optionStorage->put(self::OPTION_AVAILABLE_PROJECTS, $this->availableProjects);
	}

}