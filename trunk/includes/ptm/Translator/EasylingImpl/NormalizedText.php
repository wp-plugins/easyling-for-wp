<?php
/**
 * User: Atesz
 * Date: 2012.12.13.
 * Time: 14:35
 */

class NormalizedText {

	private $normalizedString;
	private $signature;

	public static function createByEncoded(/*String */$encoded) {
		$colon = mb_strpos($encoded,':');
		if(mb_strlen($encoded) > 0 && $encoded{0} == ' ' && $colon !== FALSE && $colon >= 0)
		{
			$signature = mb_substr($encoded, 1, $colon - 1);
			$normalizedString = mb_substr($encoded,$colon+1);
		} else
		{
			$normalizedString = $encoded;
			$signature = null;
		}
		return new NormalizedText($normalizedString, $signature);
	}

	public function __construct(/*String */$normalizedString, /*String */$signature /*= null*/) {
		/*		if ($signature === null) {
					$this->setByEncoded($normalizedString);
					return;
				}
		*/
		$this->normalizedString = $normalizedString;
		$this->signature = $signature;
	}

	public function getNormalizedString() {
		return $this->normalizedString;
	}

	public function getSignature() {
		return $this->signature;
	}

	public function equals($obj) {
		if(!($obj instanceof NormalizedText))
			return false;

		/** @var NormalizedText $other  */
		$other = $obj;

		return $this->normalizedString === $other->normalizedString && $this->signature === $other->signature;
	}

	// TODO: skippable???
	/*public function hashCode() {
		return Objects.hashCode(normalizedString, signature);
	}*/

	public function __toString() {
		if ($this->signature === null)
			return $this->normalizedString;
		return " ".$this->signature.":".$this->normalizedString;
	}

	/**
	 * Returns the NormalizedText encoded as a string
	 */
	public function toString() {
		return $this->__toString();
	}
}
