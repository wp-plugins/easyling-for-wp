<?php

/**
 * Java Compatible-ish Regex Class
 * @version 0.1
 */
abstract class Pattern_Abstract {

    protected $_pattern;
    protected $_matcher;

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

    public static function compile($regex, $flags = null) {

	    $regex = str_replace('/', '\/', $regex);
        $regex = "/$regex/i";
        if ($flags !== null) {
            trigger_error('Flags are not support', E_WARNING);
        }
        $instance = new Pattern;
        $instance->_pattern = $regex;
        return $instance;
    }

    public function pattern() {
        return $this->_pattern;
    }

    public static function quote($str) {
        trigger_error('Quote not implemented', E_WARNING);
        //return self;
    }

    public function matcher($str) {
        $this->_matcher = new Matcher($str, $this);
        return $this->_matcher;
    }

    public function __toString() {
        return $this->pattern();
    }

}

//$p = Pattern::compile('/a*b/');
//$m = $p->matcher('aabfooaabfooabfoob');
//var_dump($m->replaceAll('-'));
//$p = Pattern::compile('/dog/');
//$m = $p->matcher('zzzdogzzzdogzzz');
//var_dump($m->replaceFirst('cat'));
//var_dump($m->group());
//var_dump($m->group(1));
//var_dump($m->start(1));
//var_dump($m->end(1));



