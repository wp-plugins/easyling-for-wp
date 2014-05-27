<?php

/**
 * Java compatible-ish Matcher class
 * @version 0.1
 */
abstract class Matcher_Abstract {

    /**
     * Matches array
     * @var array
     */
    protected $_matches;

    /**
     * String to match
     * @var string
     */
    protected $_str;

    /**
     * Pattern
     * @var Pattern_Abstract
     */
    protected $_pattern;

    /**
     * Pointer to the array element that is currently being used
     * @var int
     */
    protected $_matchesPointer = null;

    /**
     * @param string $str String 2 match
     * @param Pattern_Abstract $pattern pattern to match against
     */
    public abstract function __construct($str, Pattern_Abstract &$pattern);

    /**
     * @return int offset of the last char of current match
     */
    public abstract function end($group = null);

    /**
     * @return int offset of the first char of current match
     */
    public abstract function start($group = null);

    /**
     * Step internal match pointer to the next match
     * @returns bool TRUE if stepping was successful FALSE otherwise
     */
    public abstract function find();

    /**
     * Returns the capture group for current match
     * @param int $group Offset of the group | if not specified defaults to 0
     * @return string the capture group of the regex match from the input source
     */
    public abstract function group($group = null);

    /**
     * Replace all regex matches with replacement
     * @param string $replacement Replacement to replace all matches with
     * @return string replaced string
     */
    public abstract function replaceAll($replacement = "");

    /**
     * Replaces the first subsequence of the input sequence that matches the pattern with the given replacement string.
     * @param string $replacement The replacement string
     * @return string The string constructed by replacing the first matching subsequence by the replacement string, substituting captured subsequences as needed
     */
    public abstract function replaceFirst($replacement = "");

    /**
     * You got matches or not
     * @return bool TRUE | FALSE
     */
    public abstract function matches();
}

class Matcher extends Matcher_Abstract {

    public function __construct($str, Pattern_Abstract &$pattern) {
        $this->_str = $str;
        $this->_pattern = $pattern;
        $p = $pattern->pattern();
        if (!empty($p))
            preg_match_all($p, $str, $this->_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    }

    public function find() {
        $p = $this->_pattern->pattern();
        if (empty($p))
            return false;
        if ($this->_matchesPointer === null) {
            $this->_matchesPointer = 0;
        } else {
            $this->_matchesPointer++;
        }
        if (isset($this->_matches[$this->_matchesPointer]))
            return true;
        return false;
    }

    public function end($group = null) {
        if ($this->_matchesPointer === null)
            throw new Exception("You need to call find() before end()");
        if ($group === null)
            return $this->_matches[$this->_matchesPointer][0][1] + strlen($this->group());
        return $this->_matches[$this->_matchesPointer][$group][1] + strlen($this->group($group));
    }

    public function start($group = null) {
        if ($this->_matchesPointer === null)
            throw new Exception("You need to call find() before start()");
        if ($group === null)
            return $this->_matches[$this->_matchesPointer][0][1];
        return $this->_matches[$this->_matchesPointer][$group][1];
    }

    public function replaceAll($replacement = "") {
        return preg_replace($this->_pattern->pattern(), $replacement, $this->_str);
    }

    public function replaceFirst($replacement = "") {
        return preg_replace($this->_pattern->pattern(), $replacement, $this->_str, 1);
    }

    public function reset($str = null) {
        if ($str != null)
            $this->_str = $str;
        preg_match_all($this->_pattern->pattern(), $this->_str, $this->_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $this->_matchesPointer = null;
    }

    public function group($group = null) {
        if ($group === null)
            return $this->_matches[$this->_matchesPointer][0][0];
        return $this->_matches[$this->_matchesPointer][$group][0];
    }

    public function matches() {
        return !empty($this->_matches);
    }

}

