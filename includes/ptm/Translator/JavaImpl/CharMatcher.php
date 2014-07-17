<?php
/**
 * User: Atesz
 * Date: 2014.06.25.
 * Time: 11:18
 */

class CharMatcher {

	/**
	 * @var CharMatcherRelation[]
	 */
	private $chainedRelations = array();

	/**
	 * @var CharMatcherValue
	 */
	private $matchedValue = null;

	public function __construct($value) {
		$this->matchedValue = $value;
	}

	/**
	 * @param CharMatcher $matcher
	 * @return CharMatcher
	 */
	public function orMatcher(CharMatcher $matcher) {
		$relation = new CharMatcherRelation(CharMatcherRelation::RELATION_OR, $matcher);
		$this->chainedRelations[] = $relation;
		return $this;
	}

	/**
	 * @param int $ch1
	 * @param int $ch2
	 * @return CharMatcher
	 */
	public static function inRange($ch1, $ch2) {
		$range = new CharMatcherRange($ch1, $ch2);
		return new CharMatcher($range);
	}

	/**
	 * @param int $ch
	 * @return bool
	 */
	public function matchedInChain($ch) {
		$matched = $this->matchedValue->hasValue($ch);
		foreach ($this->chainedRelations as $relation) {
			if ($relation->getRelation() == CharMatcherRelation::RELATION_AND) {
				if (!$matched)
					return false;
				else
					$matched = $matched && $relation->getMatcher()->matchedInChain($ch);
			}
			else if ($relation->getRelation() == CharMatcherRelation::RELATION_OR) {
				if ($matched)
					return true;
				else $matched = $matched || $relation->getMatcher()->matchedInChain($ch);
			}
		}

		return $matched;
	}

	/**
	 * @param string $str
	 * @return string
	 */
	public function removeFrom($str) {
		$retString = "";
		$len = strlen($str);
		for ($i=0;$i<$len;$i++) {
			$chr = $str[$i];
			$chCode = ord($chr);
			if (!$this->matchedInChain($chCode)) {
				$retString .= $chr;
			}
		}

		return $retString;
	}
}

abstract class CharMatcherValue {

	/**
	 * @param string|int $ch
	 * @return int
	 */
	public function getCodeValue($ch) {
		if (!is_int($ch))
			return ord($ch);

		return $ch;
	}

	abstract public function hasValue($char);
}

class CharMatcherRange extends CharMatcherValue {

	private $codeFrom;
	private $codeTo;

	public function __construct($from, $to) {
		$this->codeFrom = $this->getCodeValue($from);
		$this->codeTo = $this->getCodeValue($to);
	}

	public function hasValue($char) {
		$chCode = $this->getCodeValue($char);

		return $chCode > $this->codeFrom && $chCode < $this->codeTo;
	}
}
/*
class CharMatcherExact extends CharMatcherValue {

}*/

class CharMatcherRelation {
	const RELATION_OR = 'or';
	const RELATION_AND = 'and';

	public function __construct($relation, $matcher) {
		$this->matcher = $matcher;
		$this->relation = $relation;
	}

	/**
	 * @return CharMatcher
	 */
	public function getMatcher() {
		return $this->matcher;
	}

	/**
	 * @return CharMatcherRelation
	 */
	public function getRelation() {
		return $this->relation;
	}

	private $relation;
	private $matcher;
}