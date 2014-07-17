<?php
/**
 * User: Atesz
 * Date: 2012.12.14.
 * Time: 11:38
 */
class TranslatorBase
{
	protected function assignRecordToNode()
	{
		//throw new Exception("not implemented");
	}


	// JS only callback for interactive editing
	protected function assignNodeMapToNode(DOMNode $n, IntToNodeMap $nodeMap)
	{

	}

	protected  function storeMissing(JSArray $ignore)
	{
		return ;
	}

	/**
	 * @param DOMElement $link
	 * @param $attributeName
	 * @return mixed
	 */
	protected function cleanLink($link, $attributeName)
	{
		$original = CompatibleXMLSerializer::NONSERIALIZABLE_PREFIX.$attributeName;

		if($link->hasAttribute($original))
			$value = $link->getAttribute($original);
		else
			$value = $link->getAttribute($attributeName);

		return $value;
	}


	// JavaOnly in Translator.java
	protected function changeBlock(DOMElement $block, TranslationEntry $te) {
		return ;
	}

	// JavaOnly in Translator.java
	protected function startDeferredUpdates() {
		return ;
	}

	// JavaOnly in Translator.java
	protected function finishDeferredUpdates() {
		return ;
	}

	/**
	 * @param String $src
	 * @param DOMAttr $attributeNode
	 * @return boolean
	 */
	protected function deferUpdate($src, $attributeNode) {
		return false;
	}

	protected function swapStyles($element) {
		return ;
	}

	protected function attributeNodeTranslation($attributeNode) {
		return null;
	}

	protected function nodeValueTranslation($node) {
		return null;
	}

	public static function getClassName() {
		return "Translator";
	}

}
