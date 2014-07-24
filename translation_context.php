<?php
/**
 * User: Atesz
 * Date: 2014.07.23.
 * Time: 17:23
 */

abstract class Easyling_TranslationContext {

	/** @var PTMLocale */
	protected $targetLocale = null;

	/** @var string */
	protected $sourceURI = null;

	/** @var string the full original language URL */
	protected $sourceURL = null;

	/** @var Easyling_Settings */
	private $settings = null;

	/** @var string  */
	private $canonicalURL = null;

	/** @var PTMLocale */
	private $sourceLocale = null;

	/** @var bool */
	protected $admin = null;

	/**
	 * @param Easyling_Settings $settings
	 * @return Easyling_TranslationContext
	 */
	static public function get($settings) {
		if ($settings->isMultiDomain()) {
			$context = new Easyling_MultiDomainContext($settings);
		} else {
			$context = new Easyling_RewriteRuleContext($settings);
		}

		return $context;
	}

	public function addWPFilters() {

	}

	public function removeWPFilters() {

	}

	/**
	 * @return string
	 */
	protected function getCanonicalURL() {
		return $this->canonicalURL;
	}

	/**
	 * @param Easyling_Settings $settings
	 * @throws RuntimeException
	 */
	public function __construct($settings) {

		$this->settings = $settings;
		if ($settings->getPluginSettings())
			$this->canonicalURL = $settings->getPluginSettings()->getCanonicalURL();
		else
			// temporary for activating
			$this->canonicalURL = get_bloginfo('url');
		$this->admin = is_admin();
	}

	abstract protected function initialize();

	public function initializeForTranslate() {
		$sourceLocale = $this->settings->getLinkedSourceLocale();

		if ($sourceLocale != null) {
			$this->sourceLocale = $sourceLocale;
		} else {
			// 0.9.10
			throw new RuntimeException("Please update the project list on the admin UI to correct this error message.");
		}

		$this->initialize();
	}

	abstract protected function setRequestData();

	abstract public function getResourceMap();

	abstract protected function getURLForTargetLocale($locale);

	public function isTranslatable() {
		return $this->targetLocale != null;
	}

	/**
	 * @return PTMLocale
	 */
	public function getTargetLocale() {
		return $this->targetLocale;
	}

	/**
	 * @return string
	 */
	public function getSourceURL() {
		return $this->sourceURL;
	}

	public function parseRequest() {
		$this->setRequestData();
		// get the current URL for source language
		$this->sourceURL = $this->getURLForTargetLocale(null);
	}

	/**
	 * Retrieve the flag coordinates of language
	 *
	 * @param string $filter 2 letter country code of flag such as: de/hu. Careful with english: US/GB etc is used
	 * @since 0.9.10
	 * @return array Array of all flag coords or filtered coordinates | NULL if nothing has been found
	 */
	function getFlagCoordinates($filter = null) {
		$coords = unserialize('a:234:{s:9:"_abkhazia";a:2:{s:1:"x";i:0;s:1:"y";i:0;}s:13:"_commonwealth";a:2:{s:1:"x";i:0;s:1:"y";i:32;}s:15:"_european-union";a:2:{s:1:"x";i:0;s:1:"y";i:64;}s:7:"_kosovo";a:2:{s:1:"x";i:0;s:1:"y";i:96;}s:17:"_nagorno-karabakh";a:2:{s:1:"x";i:0;s:1:"y";i:128;}s:16:"_northern-cyprus";a:2:{s:1:"x";i:0;s:1:"y";i:160;}s:9:"_scotland";a:2:{s:1:"x";i:0;s:1:"y";i:192;}s:11:"_somaliland";a:2:{s:1:"x";i:0;s:1:"y";i:224;}s:14:"_south-ossetia";a:2:{s:1:"x";i:0;s:1:"y";i:256;}s:6:"_wales";a:2:{s:1:"x";i:0;s:1:"y";i:288;}s:2:"ad";a:2:{s:1:"x";i:0;s:1:"y";i:320;}s:2:"ae";a:2:{s:1:"x";i:0;s:1:"y";i:352;}s:2:"af";a:2:{s:1:"x";i:0;s:1:"y";i:384;}s:2:"ag";a:2:{s:1:"x";i:32;s:1:"y";i:0;}s:2:"ai";a:2:{s:1:"x";i:32;s:1:"y";i:32;}s:2:"al";a:2:{s:1:"x";i:32;s:1:"y";i:64;}s:2:"am";a:2:{s:1:"x";i:32;s:1:"y";i:96;}s:2:"an";a:2:{s:1:"x";i:32;s:1:"y";i:128;}s:2:"ao";a:2:{s:1:"x";i:32;s:1:"y";i:160;}s:2:"aq";a:2:{s:1:"x";i:32;s:1:"y";i:192;}s:2:"ar";a:2:{s:1:"x";i:32;s:1:"y";i:224;}s:2:"as";a:2:{s:1:"x";i:32;s:1:"y";i:256;}s:2:"at";a:2:{s:1:"x";i:32;s:1:"y";i:288;}s:2:"au";a:2:{s:1:"x";i:32;s:1:"y";i:320;}s:2:"aw";a:2:{s:1:"x";i:32;s:1:"y";i:352;}s:2:"ax";a:2:{s:1:"x";i:32;s:1:"y";i:384;}s:2:"az";a:2:{s:1:"x";i:64;s:1:"y";i:0;}s:2:"ba";a:2:{s:1:"x";i:64;s:1:"y";i:32;}s:2:"bb";a:2:{s:1:"x";i:64;s:1:"y";i:64;}s:2:"bd";a:2:{s:1:"x";i:64;s:1:"y";i:96;}s:2:"be";a:2:{s:1:"x";i:64;s:1:"y";i:128;}s:2:"bf";a:2:{s:1:"x";i:64;s:1:"y";i:160;}s:2:"bg";a:2:{s:1:"x";i:64;s:1:"y";i:192;}s:2:"bh";a:2:{s:1:"x";i:64;s:1:"y";i:224;}s:2:"bi";a:2:{s:1:"x";i:64;s:1:"y";i:256;}s:2:"bj";a:2:{s:1:"x";i:64;s:1:"y";i:288;}s:2:"bl";a:2:{s:1:"x";i:64;s:1:"y";i:320;}s:2:"bm";a:2:{s:1:"x";i:64;s:1:"y";i:352;}s:2:"bn";a:2:{s:1:"x";i:64;s:1:"y";i:384;}s:2:"bo";a:2:{s:1:"x";i:96;s:1:"y";i:0;}s:2:"br";a:2:{s:1:"x";i:96;s:1:"y";i:32;}s:2:"bs";a:2:{s:1:"x";i:96;s:1:"y";i:64;}s:2:"bt";a:2:{s:1:"x";i:96;s:1:"y";i:96;}s:2:"bw";a:2:{s:1:"x";i:96;s:1:"y";i:128;}s:2:"by";a:2:{s:1:"x";i:96;s:1:"y";i:160;}s:2:"bz";a:2:{s:1:"x";i:96;s:1:"y";i:192;}s:2:"ca";a:2:{s:1:"x";i:96;s:1:"y";i:224;}s:2:"cd";a:2:{s:1:"x";i:96;s:1:"y";i:256;}s:2:"cf";a:2:{s:1:"x";i:96;s:1:"y";i:288;}s:2:"cg";a:2:{s:1:"x";i:96;s:1:"y";i:320;}s:2:"ch";a:2:{s:1:"x";i:96;s:1:"y";i:352;}s:2:"ci";a:2:{s:1:"x";i:96;s:1:"y";i:384;}s:2:"cl";a:2:{s:1:"x";i:128;s:1:"y";i:0;}s:2:"cm";a:2:{s:1:"x";i:128;s:1:"y";i:32;}s:2:"cn";a:2:{s:1:"x";i:128;s:1:"y";i:64;}s:2:"co";a:2:{s:1:"x";i:128;s:1:"y";i:96;}s:2:"cr";a:2:{s:1:"x";i:128;s:1:"y";i:128;}s:2:"cu";a:2:{s:1:"x";i:128;s:1:"y";i:160;}s:2:"cv";a:2:{s:1:"x";i:128;s:1:"y";i:192;}s:2:"cy";a:2:{s:1:"x";i:128;s:1:"y";i:224;}s:2:"cz";a:2:{s:1:"x";i:128;s:1:"y";i:256;}s:2:"de";a:2:{s:1:"x";i:128;s:1:"y";i:288;}s:2:"dj";a:2:{s:1:"x";i:128;s:1:"y";i:320;}s:2:"dk";a:2:{s:1:"x";i:128;s:1:"y";i:352;}s:2:"dm";a:2:{s:1:"x";i:128;s:1:"y";i:384;}s:2:"do";a:2:{s:1:"x";i:160;s:1:"y";i:0;}s:2:"dz";a:2:{s:1:"x";i:192;s:1:"y";i:0;}s:2:"ec";a:2:{s:1:"x";i:224;s:1:"y";i:0;}s:2:"ee";a:2:{s:1:"x";i:256;s:1:"y";i:0;}s:2:"eg";a:2:{s:1:"x";i:288;s:1:"y";i:0;}s:2:"eh";a:2:{s:1:"x";i:320;s:1:"y";i:0;}s:2:"er";a:2:{s:1:"x";i:352;s:1:"y";i:0;}s:2:"es";a:2:{s:1:"x";i:384;s:1:"y";i:0;}s:2:"et";a:2:{s:1:"x";i:416;s:1:"y";i:0;}s:2:"fi";a:2:{s:1:"x";i:448;s:1:"y";i:0;}s:2:"fj";a:2:{s:1:"x";i:480;s:1:"y";i:0;}s:2:"fk";a:2:{s:1:"x";i:512;s:1:"y";i:0;}s:2:"fm";a:2:{s:1:"x";i:544;s:1:"y";i:0;}s:2:"fo";a:2:{s:1:"x";i:160;s:1:"y";i:32;}s:2:"fr";a:2:{s:1:"x";i:160;s:1:"y";i:64;}s:2:"ga";a:2:{s:1:"x";i:160;s:1:"y";i:96;}s:2:"gb";a:2:{s:1:"x";i:160;s:1:"y";i:128;}s:2:"gd";a:2:{s:1:"x";i:160;s:1:"y";i:160;}s:2:"ge";a:2:{s:1:"x";i:160;s:1:"y";i:192;}s:2:"gg";a:2:{s:1:"x";i:160;s:1:"y";i:224;}s:2:"gh";a:2:{s:1:"x";i:160;s:1:"y";i:256;}s:2:"gl";a:2:{s:1:"x";i:160;s:1:"y";i:288;}s:2:"gm";a:2:{s:1:"x";i:160;s:1:"y";i:320;}s:2:"gn";a:2:{s:1:"x";i:160;s:1:"y";i:352;}s:2:"gq";a:2:{s:1:"x";i:160;s:1:"y";i:384;}s:2:"gr";a:2:{s:1:"x";i:192;s:1:"y";i:32;}s:2:"gs";a:2:{s:1:"x";i:224;s:1:"y";i:32;}s:2:"gt";a:2:{s:1:"x";i:256;s:1:"y";i:32;}s:2:"gu";a:2:{s:1:"x";i:288;s:1:"y";i:32;}s:2:"gw";a:2:{s:1:"x";i:320;s:1:"y";i:32;}s:2:"gy";a:2:{s:1:"x";i:352;s:1:"y";i:32;}s:2:"hk";a:2:{s:1:"x";i:384;s:1:"y";i:32;}s:2:"hn";a:2:{s:1:"x";i:416;s:1:"y";i:32;}s:2:"hr";a:2:{s:1:"x";i:448;s:1:"y";i:32;}s:2:"ht";a:2:{s:1:"x";i:480;s:1:"y";i:32;}s:2:"hu";a:2:{s:1:"x";i:512;s:1:"y";i:32;}s:2:"id";a:2:{s:1:"x";i:544;s:1:"y";i:32;}s:2:"ie";a:2:{s:1:"x";i:192;s:1:"y";i:64;}s:2:"il";a:2:{s:1:"x";i:192;s:1:"y";i:96;}s:2:"im";a:2:{s:1:"x";i:192;s:1:"y";i:128;}s:2:"in";a:2:{s:1:"x";i:192;s:1:"y";i:160;}s:2:"iq";a:2:{s:1:"x";i:192;s:1:"y";i:192;}s:2:"ir";a:2:{s:1:"x";i:192;s:1:"y";i:224;}s:2:"is";a:2:{s:1:"x";i:192;s:1:"y";i:256;}s:2:"it";a:2:{s:1:"x";i:192;s:1:"y";i:288;}s:2:"je";a:2:{s:1:"x";i:192;s:1:"y";i:320;}s:2:"jm";a:2:{s:1:"x";i:192;s:1:"y";i:352;}s:2:"jo";a:2:{s:1:"x";i:192;s:1:"y";i:384;}s:2:"jp";a:2:{s:1:"x";i:224;s:1:"y";i:64;}s:2:"ke";a:2:{s:1:"x";i:256;s:1:"y";i:64;}s:2:"kg";a:2:{s:1:"x";i:288;s:1:"y";i:64;}s:2:"kh";a:2:{s:1:"x";i:320;s:1:"y";i:64;}s:2:"ki";a:2:{s:1:"x";i:352;s:1:"y";i:64;}s:2:"km";a:2:{s:1:"x";i:384;s:1:"y";i:64;}s:2:"kn";a:2:{s:1:"x";i:416;s:1:"y";i:64;}s:2:"kp";a:2:{s:1:"x";i:448;s:1:"y";i:64;}s:2:"kr";a:2:{s:1:"x";i:480;s:1:"y";i:64;}s:2:"kw";a:2:{s:1:"x";i:512;s:1:"y";i:64;}s:2:"ky";a:2:{s:1:"x";i:544;s:1:"y";i:64;}s:2:"kz";a:2:{s:1:"x";i:224;s:1:"y";i:96;}s:2:"la";a:2:{s:1:"x";i:224;s:1:"y";i:128;}s:2:"lb";a:2:{s:1:"x";i:224;s:1:"y";i:160;}s:2:"lc";a:2:{s:1:"x";i:224;s:1:"y";i:192;}s:2:"li";a:2:{s:1:"x";i:224;s:1:"y";i:224;}s:2:"lk";a:2:{s:1:"x";i:224;s:1:"y";i:256;}s:2:"lr";a:2:{s:1:"x";i:224;s:1:"y";i:288;}s:2:"ls";a:2:{s:1:"x";i:224;s:1:"y";i:320;}s:2:"lt";a:2:{s:1:"x";i:224;s:1:"y";i:352;}s:2:"lu";a:2:{s:1:"x";i:224;s:1:"y";i:384;}s:2:"lv";a:2:{s:1:"x";i:256;s:1:"y";i:96;}s:2:"ly";a:2:{s:1:"x";i:288;s:1:"y";i:96;}s:2:"ma";a:2:{s:1:"x";i:320;s:1:"y";i:96;}s:2:"mc";a:2:{s:1:"x";i:352;s:1:"y";i:96;}s:2:"md";a:2:{s:1:"x";i:384;s:1:"y";i:96;}s:2:"me";a:2:{s:1:"x";i:416;s:1:"y";i:96;}s:2:"mg";a:2:{s:1:"x";i:448;s:1:"y";i:96;}s:2:"mh";a:2:{s:1:"x";i:480;s:1:"y";i:96;}s:2:"mk";a:2:{s:1:"x";i:512;s:1:"y";i:96;}s:2:"ml";a:2:{s:1:"x";i:544;s:1:"y";i:96;}s:2:"mm";a:2:{s:1:"x";i:256;s:1:"y";i:128;}s:2:"mn";a:2:{s:1:"x";i:256;s:1:"y";i:160;}s:2:"mo";a:2:{s:1:"x";i:256;s:1:"y";i:192;}s:2:"mp";a:2:{s:1:"x";i:256;s:1:"y";i:224;}s:2:"mr";a:2:{s:1:"x";i:256;s:1:"y";i:256;}s:2:"ms";a:2:{s:1:"x";i:256;s:1:"y";i:288;}s:2:"mt";a:2:{s:1:"x";i:256;s:1:"y";i:320;}s:2:"mu";a:2:{s:1:"x";i:256;s:1:"y";i:352;}s:2:"mv";a:2:{s:1:"x";i:256;s:1:"y";i:384;}s:2:"mw";a:2:{s:1:"x";i:288;s:1:"y";i:128;}s:2:"mx";a:2:{s:1:"x";i:320;s:1:"y";i:128;}s:2:"my";a:2:{s:1:"x";i:352;s:1:"y";i:128;}s:2:"mz";a:2:{s:1:"x";i:384;s:1:"y";i:128;}s:2:"na";a:2:{s:1:"x";i:416;s:1:"y";i:128;}s:2:"ne";a:2:{s:1:"x";i:448;s:1:"y";i:128;}s:2:"nf";a:2:{s:1:"x";i:480;s:1:"y";i:128;}s:2:"ng";a:2:{s:1:"x";i:512;s:1:"y";i:128;}s:2:"ni";a:2:{s:1:"x";i:544;s:1:"y";i:128;}s:2:"nl";a:2:{s:1:"x";i:288;s:1:"y";i:160;}s:2:"no";a:2:{s:1:"x";i:288;s:1:"y";i:192;}s:2:"np";a:2:{s:1:"x";i:288;s:1:"y";i:224;}s:2:"nr";a:2:{s:1:"x";i:288;s:1:"y";i:256;}s:2:"nz";a:2:{s:1:"x";i:288;s:1:"y";i:288;}s:2:"om";a:2:{s:1:"x";i:288;s:1:"y";i:320;}s:2:"pa";a:2:{s:1:"x";i:288;s:1:"y";i:352;}s:2:"pe";a:2:{s:1:"x";i:288;s:1:"y";i:384;}s:2:"pg";a:2:{s:1:"x";i:320;s:1:"y";i:160;}s:2:"ph";a:2:{s:1:"x";i:352;s:1:"y";i:160;}s:2:"pk";a:2:{s:1:"x";i:384;s:1:"y";i:160;}s:2:"pl";a:2:{s:1:"x";i:416;s:1:"y";i:160;}s:2:"pn";a:2:{s:1:"x";i:448;s:1:"y";i:160;}s:2:"pr";a:2:{s:1:"x";i:480;s:1:"y";i:160;}s:2:"ps";a:2:{s:1:"x";i:512;s:1:"y";i:160;}s:2:"pt";a:2:{s:1:"x";i:544;s:1:"y";i:160;}s:2:"pw";a:2:{s:1:"x";i:320;s:1:"y";i:192;}s:2:"py";a:2:{s:1:"x";i:320;s:1:"y";i:224;}s:2:"qa";a:2:{s:1:"x";i:320;s:1:"y";i:256;}s:2:"ro";a:2:{s:1:"x";i:320;s:1:"y";i:288;}s:2:"rs";a:2:{s:1:"x";i:320;s:1:"y";i:320;}s:2:"ru";a:2:{s:1:"x";i:320;s:1:"y";i:352;}s:2:"rw";a:2:{s:1:"x";i:320;s:1:"y";i:384;}s:2:"sa";a:2:{s:1:"x";i:352;s:1:"y";i:192;}s:2:"sb";a:2:{s:1:"x";i:384;s:1:"y";i:192;}s:2:"sc";a:2:{s:1:"x";i:416;s:1:"y";i:192;}s:2:"sd";a:2:{s:1:"x";i:448;s:1:"y";i:192;}s:2:"se";a:2:{s:1:"x";i:480;s:1:"y";i:192;}s:2:"sg";a:2:{s:1:"x";i:512;s:1:"y";i:192;}s:2:"sh";a:2:{s:1:"x";i:544;s:1:"y";i:192;}s:2:"si";a:2:{s:1:"x";i:352;s:1:"y";i:224;}s:2:"sk";a:2:{s:1:"x";i:352;s:1:"y";i:256;}s:2:"sl";a:2:{s:1:"x";i:352;s:1:"y";i:288;}s:2:"sm";a:2:{s:1:"x";i:352;s:1:"y";i:320;}s:2:"sn";a:2:{s:1:"x";i:352;s:1:"y";i:352;}s:2:"so";a:2:{s:1:"x";i:352;s:1:"y";i:384;}s:2:"sr";a:2:{s:1:"x";i:384;s:1:"y";i:224;}s:2:"st";a:2:{s:1:"x";i:416;s:1:"y";i:224;}s:2:"sv";a:2:{s:1:"x";i:448;s:1:"y";i:224;}s:2:"sy";a:2:{s:1:"x";i:480;s:1:"y";i:224;}s:2:"sz";a:2:{s:1:"x";i:512;s:1:"y";i:224;}s:2:"tc";a:2:{s:1:"x";i:544;s:1:"y";i:224;}s:2:"td";a:2:{s:1:"x";i:384;s:1:"y";i:256;}s:2:"tg";a:2:{s:1:"x";i:384;s:1:"y";i:288;}s:2:"th";a:2:{s:1:"x";i:384;s:1:"y";i:320;}s:2:"tj";a:2:{s:1:"x";i:384;s:1:"y";i:352;}s:2:"tl";a:2:{s:1:"x";i:384;s:1:"y";i:384;}s:2:"tm";a:2:{s:1:"x";i:416;s:1:"y";i:256;}s:2:"tn";a:2:{s:1:"x";i:448;s:1:"y";i:256;}s:2:"to";a:2:{s:1:"x";i:480;s:1:"y";i:256;}s:2:"tr";a:2:{s:1:"x";i:512;s:1:"y";i:256;}s:2:"tt";a:2:{s:1:"x";i:544;s:1:"y";i:256;}s:2:"tv";a:2:{s:1:"x";i:416;s:1:"y";i:288;}s:2:"tw";a:2:{s:1:"x";i:416;s:1:"y";i:320;}s:2:"tz";a:2:{s:1:"x";i:416;s:1:"y";i:352;}s:2:"ua";a:2:{s:1:"x";i:416;s:1:"y";i:384;}s:2:"ug";a:2:{s:1:"x";i:448;s:1:"y";i:288;}s:2:"us";a:2:{s:1:"x";i:480;s:1:"y";i:288;}s:2:"uy";a:2:{s:1:"x";i:512;s:1:"y";i:288;}s:2:"uz";a:2:{s:1:"x";i:544;s:1:"y";i:288;}s:2:"vc";a:2:{s:1:"x";i:448;s:1:"y";i:320;}s:2:"ve";a:2:{s:1:"x";i:448;s:1:"y";i:352;}s:2:"vg";a:2:{s:1:"x";i:448;s:1:"y";i:384;}s:2:"vi";a:2:{s:1:"x";i:480;s:1:"y";i:320;}s:2:"vn";a:2:{s:1:"x";i:512;s:1:"y";i:320;}s:2:"vu";a:2:{s:1:"x";i:544;s:1:"y";i:320;}s:2:"ws";a:2:{s:1:"x";i:480;s:1:"y";i:352;}s:2:"ye";a:2:{s:1:"x";i:480;s:1:"y";i:384;}s:2:"za";a:2:{s:1:"x";i:512;s:1:"y";i:352;}s:2:"zm";a:2:{s:1:"x";i:544;s:1:"y";i:352;}s:2:"zw";a:2:{s:1:"x";i:512;s:1:"y";i:384;}s:2:"ct";a:2:{s:1:"x";i:384;s:1:"y";i:0;}}');
		if ($filter == null)
			return $coords;
		if (isset($coords[$filter]))
			return $coords[$filter];
		return null;
	}

	/**
	 * @return Easyling_ProjectLocaleSettings[]|Easyling_ProjectLocalesSettings
	 */
	public function getAvailableLocalesSettings() {
		return $this->settings->getUsedProjectLocales();
	}

	/**
	 * @return Easyling_ProjectLocaleSettings[]|Easyling_ProjectLocalesSettings
	 */
	public function getAllAvailableLocales() {
		$allAvailableLocales = $this->getAvailableLocalesSettings();
		// source locale key is set
		$allAvailableLocales[$this->sourceLocale->getLocale()] = null;

		return $allAvailableLocales;
	}

	/**
	 * Retrieve the translation URLs and flag coordinates
	 *
	 * @param bool $setCoordinates
	 * @since 0.9.10
	 * @return array Multi-dimensional array of data for translations
	 */
	function getTranslationURLs($setCoordinates = true) {

		$locales = $this->getAllAvailableLocales();

		if ($setCoordinates)
			$coordinates = $this->getFlagCoordinates();
		else
			$coordinates = array();

		$translationURLs = array(
			'translations' => array()
		);

		foreach ($locales as $localeString => $lSettings) {

			$url = $this->getURLForTargetLocale($lSettings);

			$locale = new PTMLocale($localeString);

			$countryCode = strtolower($locale->getCountryCode());
			$localeData = array('url' => $url);
			if ($setCoordinates) {
				$localeData['coords'] = $coordinates[$countryCode];
			}

			$translationURLs['translations'][$localeString] = $localeData;
		}
		return $translationURLs;
	}

	public function getAlternativeLangURLs() {
		$langURLs = $this->getTranslationURLs(false);
		$localeURLs = array();
		$langCodeURLs = array();
		foreach ($langURLs['translations'] as $localeString=>$langData) {
			$url = $langData['url'];

			$locale = new PTMLocale($localeString);
			$localeURLs[$localeString] = $url;
			$countryCode = $locale->getCountryCode();
			if (!empty($countryCode)) {
				$langCodeURLs[$locale->getLanguageCode()] = $url;
			}
		}

		// add the default locale
		$sourceLocale = $this->settings->getLinkedSourceLocale()->getLocale();
		$urlMap = array_merge($localeURLs, $langCodeURLs);
		$urlMap['x-default'] = $urlMap[$sourceLocale];

		return $urlMap;
	}

}

class Easyling_MultiDomainContext extends Easyling_TranslationContext {

	/** @var MultiDomain */
	private $multiDomain = null;

	protected function initialize() {
		// build config
		$mdConfig = array();
		foreach ($this->getAvailableLocalesSettings() as $settings) {
			$homePath = 'http://' . $settings->getDomain();

			$mdConfig[] = array(
				'domain' => $settings->getDomain(),
				'siteurl' => $homePath,
				'home' => $homePath);
		}
		// include multisite component
		require_once dirname(__FILE__ ) . '/includes/Multidomain/Multidomain.php';
		$this->multiDomain = new MultiDomain($mdConfig);
	}

	protected function setRequestData() {
		$this->sourceURI = $_SERVER['REQUEST_URI'];

		$domain = $_SERVER['HTTP_HOST'];
		foreach ($this->getAvailableLocalesSettings() as $locale=>$settings) {
			if ($settings->getDomain() == $domain) {
				$this->targetLocale = new PTMLocale($locale);
				return ;
			}
		}
	}

	/**
	 * @param Easyling_ProjectLocaleSettings $localeSettings
	 * @return string
	 */
	public function getURLForTargetLocale($localeSettings) {
		// target locale
		if ($localeSettings != null) {
			return ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $localeSettings->getDomain() . $this->sourceURI;
		} else {
			return $this->getCanonicalURL() . $this->sourceURI;
		}
	}

	public function getResourceMap() {
		return array();
	}
}

class Easyling_RewriteRuleContext extends Easyling_TranslationContext {

	private $langPathPrefix = null;

	protected function setRequestData() {
		/** @global WP_Query $wp_query */
		global $wp_query;

		$this->sourceURI = $_SERVER['REQUEST_URI'];

		$language = $wp_query->get('easyling');
		if (empty($language))
			return ;

		$language = trim($language, '/');
		foreach ($this->getAvailableLocalesSettings() as $locale=>$settings) {
			if ($settings->getPathPrefix() == $language) {
				$this->targetLocale = new PTMLocale($locale);
				$this->langPathPrefix = $language;
				$this->sourceURI = str_ireplace('/' . $language . '/', '/', $_SERVER['REQUEST_URI']);
				return ;
			}
		}
	}

	/**
	 * @param Easyling_ProjectLocaleSettings $localeSettings
	 * @return string
	 */
	public function getURLForTargetLocale($localeSettings) {
		$url = null;

		// the original language is displayed
		if ($localeSettings == null)
			$pathPrefix = '';
		else
			$pathPrefix = $localeSettings->getPathPrefix();

		// url prefixes are used such as /hu/ or /de/
		return $this->getCanonicalURL() . ( !empty($pathPrefix) ? ('/'.$pathPrefix) : '' ) . $this->sourceURI;
	}

	public function getResourceMap() {
		$home = home_url();
		return array( $home => $home . $this->langPathPrefix . "/");
	}

	private function setLinkFilters() {
		// 'the_permalink' is skipped against duplicated rewrite
		$link_types = array('page_link','post_link','category_link','year_link','month_link','tag_link',
			'post_type_link','attachment_link','author_feed_link','author_link','comment_reply_link',
			'day_link','feed_link','get_comment_author_link','get_comment_author_url_link',/*'the_permalink',*/
			'term_link '
		);

		foreach ($link_types as $link_type) {
			add_filter($link_type, array(&$this, 'filterLinks'));
		}
	}

	protected function initialize() {

	}

	public function addWPFilters() {
		add_filter('rewrite_rules_array', array(&$this, 'filterRewriteRulesAll'));

		if (!$this->admin)
			$this->setLinkFilters();
	}

	public function removeWPFilters() {
		remove_filter('rewrite_rules_array', array(&$this, 'filterRewriteRulesAll'));
	}

	public function filterLinks($permalink) {
		if ($this->langPathPrefix == null)
			return $permalink;
		$url = get_bloginfo('url');
		$permaTmp = str_replace($url, '', $permalink);
		return $url . '/' . $this->langPathPrefix . $permaTmp;
	}

	private $pathPrefixPattern = null;

	public function getPathPrefixPattern() {

		if ($this->pathPrefixPattern !== null)
			return $this->pathPrefixPattern;

		$pathPrefixes = $this->getPathPrefixes();

		if (empty($pathPrefixes))
			return $this->pathPrefixPattern="";

		$prefixPattern = "";

		foreach ($pathPrefixes as $pathPrefix) {
			if (!empty($pathPrefix))
				$prefixPattern .= $pathPrefix . "/|";
		}

		if (empty($prefixPattern))
			return $this->pathPrefixPattern="";

		$this->pathPrefixPattern = $prefixPattern;

		return $this->pathPrefixPattern;
	}

	/**
	 * @return string[]
	 */
	private function getPathPrefixes() {
		$prefixes = array();
		foreach ($this->getAvailableLocalesSettings() as $lSettings) {
			$prefixes[] = $lSettings->getPathPrefix();
		}

		return $prefixes;
	}

	public function filterRewriteRulesAll($rules) {

		$lang_pattern = $this->getPathPrefixPattern();

		if (empty($lang_pattern))
			return $rules;

		$newRules = array();

		$pathPrefixes = $this->getPathPrefixes();

		// rule for root
		$newRules['(' . implode('|', $pathPrefixes) . ')/{0,1}$'] = 'index.php?easyling=$matches[1]';

		foreach ($rules as $pattern=>$rule) {
			$this->filterRewriteRule($pattern, $rule, $lang_pattern);
			$newRules[$pattern] = $rule;
		}

		return $newRules;
	}

	public function filterRewriteRule(&$pattern, &$rule, $lang_pattern = null) {
		if ($lang_pattern === null)
			$lang_pattern = $this->getPathPrefixPattern();

		if (strpos($rule, "attachment") !== FALSE) {
			return;
		}
		$new_pattern = "($lang_pattern)" . $pattern;
		$new_rewrite_rule = preg_replace_callback('/matches\[(\d*?)\]/', array(
				&$this,
				"modifyRewriteRule"), $rule) . '&easyling=$matches[1]';

		$pattern = $new_pattern;
		$rule = $new_rewrite_rule;
	}
	/*
			public function filterRewriteRules($rewrite_rules) {

				$new_rewrite_rules = array();

				$lang_pattern = $this->getPathPrefixPattern();

				if (empty($lang_pattern)) {
					return $rewrite_rules;
				}

				foreach ($rewrite_rules as $pattern => $rewrite_rule) {
					$this->filterRewriteRule($pattern, $rewrite_rule, $lang_pattern);
					$new_rewrite_rules[$pattern] = $rewrite_rule;
				}

				return $new_rewrite_rules;
			}
	*/
	public function modifyRewriteRule($match) {
		return 'matches[' . ($match[1] + 1) . ']';
	}
}
