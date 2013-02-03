<?php
/**
 * User: Atesz
 * Date: 2012.12.16.
 * Time: 18:01
 */
class DOMUtil
{
	 public static function getNodeUniqueKey(DOMNode $n)
	 {
		 if (version_compare(PHP_VERSION, '5.2.0', '<'))
			 return spl_object_hash($n);
		 else {
			 if (method_exists($n, 'getNodePath'))
			    return $n->getNodePath();
			 else throw new Exception("no valid implementation for DOMNode::getNodeUniqueKey for PHP ".PHP_VERSION);
		 }
	 }
}
