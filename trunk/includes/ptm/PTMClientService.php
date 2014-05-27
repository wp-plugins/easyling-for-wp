<?php
/**
 * User: Atesz
 * Date: 2012.12.15.
 * Time: 21:15
 */

class PTMClientService extends PTMService
{
	/**
	 * @return string JSON-ed string associative array('prCode'=>prName)
	 */
	public function getAvailableProjectNames()
	{
		$projectNames = array();
		foreach ($this->getAvailableProjects() as $project)
			$projectNames[$project->getProjectCode()] = $project->getName();

		asort($projectNames);

		return json_encode($projectNames);
	}

	/**
	 * @param string $projectCode
	 * @return string JSON-ed string array
	 */
	public function getProjectPublishedLanguages($projectCode)
	{
		$languages = array();
		foreach ($this->getProjectLanguages($projectCode) as $prLanguage)
		{
			if ($prLanguage->isPublished())
				$languages[] = $prLanguage->getLanguageCode();
		}

		sort($languages);

		return json_decode($languages);
	}
}