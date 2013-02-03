<?php
/**
 * User: Atesz
 * Date: 2012.12.11.
 * Time: 19:38
 */
class TranslationEntry implements Serializable
{
	private $key;
	private $exactURL;
	private $original;
	private $translation;
	private $path;

	/**
	 * @var NormalizedText
	 */
	private $normalizedSource = null;

	private $meta = array();

	public function __construct($e)
	{
		//$this->simpleURL = $simpleURL;
		$this->exactURL = $e['eu'];
		$this->original = $e['o'];
		$this->translation = $e['t'];
		$this->path = $e['p'];
		$this->key = $this->generateKey();
	}

	public function generateKey()
	{
		return md5($this->path.$this->original);
	}

	/**
	 * @return string
	 */
	public function getSource()
	{
		return $this->original;
	}

	/**
	 * @return string
	 */
	public function getTarget()
	{
		return $this->translation;
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	public function getKey()
	{
		if ($this->key == null)
			$this->key = $this->generateKey();
		return $this->key;
	}

	const LAST_FRAGMENT_NAME = '/([^\/:]+):[^\/]+$/';

	public function isBlock()
	{
		$matches = array();
		preg_match(self::LAST_FRAGMENT_NAME, $this->path, $matches);
		if ($matches && count($matches) >= 2 && strlen($matches[1])>0)
			return ctype_alpha($matches[1]{0});
		return false;
		/*var m = this.p.match(LAST_FRAGMENT_NAME);
		if(m && m.length >= 2 && m[1].length > 0)
			return /[a-z]/.test(m[1].charAt(0));
		return false;*/

	}

	public function hasMeta($key)
	{
		return isset($this->meta[$key]);
	}

	public function setMeta($key, $value)
	{
		$this->meta[$key] = $value;
	}

	public function getMeta($key)
	{
		return $this->meta[$key];
	}

	public function isCompatibleWith($xmlRoot)
	{
		if(!$this->isBlock())
			return false;
		return TranslateUtil::isCompatible(TranslateUtil::xmlRoot($this->getSource()), $xmlRoot);
	}

	public function setTarget($t)
	{
		$this->translation = $t;
	}

	/**
	 * @return NormalizedText
	 */
	public function getNormalizedSource() {
		if($this->normalizedSource == null && $this->original != null)
		{
			$source = $this->getSource();

			if($this->isBlock())
			{
				$this->normalizedSource = TranslateUtil::normalizeXMLByString($source);
			}
			else
			{
				$this->normalizedSource = TranslateUtil::normalizePlainText(EncodeUtil::normalizeSpaces($source));
			}
		}
		return $this->normalizedSource;
	}

	public function serialize()
	{
		return serialize(array(
			'o'=>$this->original, 't'=>$this->translation,
			'p'=>$this->path, 'eu'=>$this->exactURL,
			'n'=>$this->getNormalizedSource()->toString(),
		//	'k'=>$this->getKey()
		));
	}

	public function unserialize($serialized)
	{
		$data = unserialize($serialized);
		$this->original = $data['o'];
		$this->translation = $data['t'];
		$this->path = $data['p'];
		$this->normalizedSource = NormalizedText::createByEncoded($data['n']);
		$this->exactURL = $data['eu'];
		//$this->key = $data['k'];
	}
}
