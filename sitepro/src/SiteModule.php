<?php

class SiteModule {
	
	/** @var SiteInfo */
	public static $siteInfo;
	/** @var string|null */
	public static $lang;
	/** @var string */
	private static $preferredLang;
	/** @var array */
	private static $translations;
	
	public static function init($none, SiteInfo $siteInfo) {
		self::$siteInfo = $siteInfo;
	}
	
	public static function setLang($lang) {
		self::$lang = $lang;
	}
	
	public static function getPreferredLang() {
		if (!self::$preferredLang) {
			/* @var $siteInfo SiteInfo */
			$siteInfo = self::$siteInfo ? self::$siteInfo : new SiteInfo();
			self::$preferredLang = self::$lang ? self::$lang : ($siteInfo->defLang ? $siteInfo->defLang : $siteInfo->baseLang);
		}
		return self::$preferredLang;
	}
	
	public static function initTranslations(array $translations) {
		self::$translations = $translations;
	}
	
	public static function __($key, $langKey = null) {
		if (!$langKey) $langKey = self::$lang ? self::$lang : '-';
		$translated = $key;
		if (self::$translations && isset(self::$translations[$langKey][$key]) && self::$translations[$langKey][$key]) {
			$translated = self::$translations[$langKey][$key];
		} else if ($langKey != '-' && self::$translations && isset(self::$translations['-'][$key]) && self::$translations['-'][$key]) {
			$translated = self::$translations['-'][$key];
		}
		return $translated;
	}
	
}
