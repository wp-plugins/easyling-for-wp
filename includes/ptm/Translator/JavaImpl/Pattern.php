<?php

/**
 * Java Compatible-ish Regex Class
 * @version 0.1
 */
abstract class Pattern_Abstract {

	/**
	 * @var string store the original set pattern
	 */
	protected $_pattern;

    protected $_matcher;

	protected $_preg_pattern;

    /**
     * Compiles a pattern which is a lie, but hey, who cares, right?
     */
//    public abstract static function compile($regex, $flags = null);

    /**
     * Runs the regex and returns a Class to match things
     * @return Matcher_Abstract Matcher Object
     */
    public abstract function matcher($str);

    /**
     * Gets the regex pattern
     * @return string regex pattern
     */
    public abstract function pattern();

	/**
	 * Gets the PCRE compatible pattern
	 * @return mixed
	 */
	public abstract function preg_pattern();

    /**
     * @deprecated since version 0.1
     */
    // public abstract static function quote($str);

    /**
     * Gets the regex pattern
     * @return string regex pattern
     */
    public abstract function __toString();
}

class Pattern extends Pattern_Abstract {

	const CASE_INSENSITIVE = 0x02;
	const MULTILINE = 0x08;
	const UNICODE_CHARACTER_CLASS = 0x100;


	public static function compile($regex, $flags = null) {

		$original_regex = $regex;
	    $regex = str_replace('/', '\/', $regex);
        $regex = "/$regex/";

		$knownFlags = array(
			self::CASE_INSENSITIVE=>'i',
			self::MULTILINE=>'m',
			self::UNICODE_CHARACTER_CLASS=>'u'
		);

        if ($flags !== null) {

	        foreach ($knownFlags as $flag=>$modifier) {
		        if (($flags & $flag) != 0) {
			        $flags -= $flag;
			        $regex .= $modifier;
		        }
	        }

	        if ($flags != 0) {
                trigger_error('Flags '.$flags.' is not supported', E_WARNING);
	        }
        }

		// TODO: check the pattern is valid, and throw PatternSyntaxException

        $instance = new Pattern;
        $instance->_pattern = $original_regex;
		$instance->_preg_pattern = $regex;
        return $instance;
    }

    public function pattern() {
        return $this->_pattern;
    }

    public static function quote($str) {
	    $specialChars = array('.','^','$','*','+','?','{',"\\",'[','|','(',')');
	    $specPatterns = '([\\'.implode("\\",$specialChars).'])';
	    $quoted = preg_replace('/'.$specPatterns.'/','\\\\${1}',$str);
	    return $quoted;
    }

	/**
	 * @param string $str
	 * @return Matcher|Matcher_Abstract
	 */
	public function matcher($str) {
        $this->_matcher = new Matcher($str, $this);
        return $this->_matcher;
    }

	/**
	 * @return string
	 */
	public function preg_pattern() {
		return $this->_preg_pattern;
	}

    public function __toString() {
        return $this->pattern();
    }

}

class PatternSyntaxException extends Exception {

}

