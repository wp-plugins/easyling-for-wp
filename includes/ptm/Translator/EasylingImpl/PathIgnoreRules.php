<?php
/**
 * User: Atesz
 * Date: 2014.06.30.
 * Time: 15:47
 */

class PathIgnoreRules {

	/**
	 * @var Pattern[]
	 */
	private $rules = array();

	public function __construct($rules) {
		foreach ($rules as $rule) {
			if (empty($rule))
				continue;

			$ruleRegexp = Pattern::compile(self::getRuleRegexp($rule));
			$this->rules[$rule] = $ruleRegexp;
		}
	}

	/**
	 * @param string $path
	 * @return string|null
	 */
	public function getMatchedRule($path) {
		foreach ($this->rules as $rule=>$pattern) {
			if ($pattern->matcher($path)->find()) {
				return $rule;
			}
		}

		return null;
	}

	static public function getRuleRegexp($rule) {

		$pattern = "^";

		/** @var Matcher $m */
		$m = Pattern::compile(self::WILDCARD)->matcher($rule);

		$lastCopied = 0;
		while($m->find()) {
			if($m->start() > $lastCopied) {
				$pattern .= '('.Pattern::quote(substr($rule, $lastCopied, $m->start())).')';
			}

			$match = $m->group();
			switch (strlen($match)) {
				case 1:
					if($m->start() == 0)
						$pattern .= "(/?)";
					$pattern .= "[^/]+";
					break;
				case 2:
					$pattern .= ".+";
					break;
			}

			$lastCopied = $m->end();
		}

		if($lastCopied < strlen($rule)) {
			$end = strlen($rule);
			if($rule[$end-1] == "/")
				--$end;

			$lastPart = substr($rule, $lastCopied, $end);
			$pattern .= '('.Pattern::quote($lastPart).'/?)';
		} else {
			$pattern .= "(/?)";
		}

		return $pattern . "$";
	}

	public function getRulePatterns() {
		return $this->rules;
	}

	const WILDCARD = "\\*\\*?";
}