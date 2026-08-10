<?php

class CookieConsent
{

    private static $disable_cookie_consent = false;
    private static $disable_default_js = false;
    private static $disable_default_css = false;
    private static $enable_google_consent_mode = false;
    private static $enable_consent_logging = false;
    private static $cookie_registry_path = 'cookie-consent/open-cookie-database.json';
    private static $categories = [
        'functional' => [
            'readOnly' => true
        ]
    ];
    private static $cookie_info_cache = null;


    public static function getCurrentSubsite()
    {
        if (self::isSubsitesEnabled()) {
            return Subsite::currentSubsite();
        }
        return null;
    }

    public static function getCurrentSubsiteId()
    {
        if (self::isSubsitesEnabled()) {
            return (int) Subsite::currentSubsiteID();
        }
        return 0;
    }

    public static function isCookieConsentDisabled()
    {
        return Config::inst()->get('CookieConsent', 'disable_cookie_consent');
    }    

    public static function isDefaultJsDisabled()
    {
        return Config::inst()->get('CookieConsent', 'disable_default_js');
    }

    public static function isDefaultCssDisabled()
    {
        return Config::inst()->get('CookieConsent', 'disable_default_css');
    }
    
    public static function isGoogleConsentModeEnabled()
    {
        return Config::inst()->get('CookieConsent', 'enable_google_consent_mode');
    }

    public static function isSubsitesEnabled()
    {
        return class_exists('Subsite');
    }    

    public static function getCategoryLabelsConfig()
    {
        $categories = Config::inst()->get('CookieConsent', 'categories');
        return is_array($categories) ? $categories : [];
    }

    public static function getCategoryTranslationsMap()
    {
        $options = [];
        $categories = self::getCategoryLabelsConfig();

        foreach ($categories as $categoryId => $categoryConfig) {
            if (!is_string($categoryId) || $categoryId === '') {
                continue;
            }
            $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
            $options[$categoryId] = _t($translationKey);
        }

        return $options;
    }

    public static function isConsentRegistrationEnabled()
    {
        return class_exists('ConsentRecord') && !Config::inst()->get('CookieConsent', 'enable_consent_logging') == false;
    }

    public static function getCookieRegistryPath()
    {
        $path = Config::inst()->get('CookieConsent', 'cookie_registry_path');
        if (!is_string($path) || trim($path) === '') {
            $path = self::$cookie_registry_path;
        }

        return $path;
    }

    public static function resolveCookieRegistryPath()
    {
        $path = self::getCookieRegistryPath();
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return BASE_PATH . '/' . ltrim($path, '/');
    }

    public static function getCookie()
    {
        return Cookie::get('cc_cookie');
    }

    public static function getCookieConsentValues()
    {
        if (self::$cookie_info_cache !== null) {
            return self::$cookie_info_cache;
        }

        $cookieValue = self::getCookie();
        if (!$cookieValue) {
            self::$cookie_info_cache = null;
            return null;
        }

        $decodedValue = rawurldecode($cookieValue);
        $decodedData = json_decode($decodedValue, true);

        self::$cookie_info_cache = is_array($decodedData) ? $decodedData : null;

        return self::$cookie_info_cache;
    }

    private static function getConsentCookieValue($key)
    {
        $decodedData = self::getCookieConsentValues();

        if (is_array($decodedData) && isset($decodedData[$key])) {
            return $decodedData[$key];
        }

        return null;
    }

    public static function getLastConsentTimestamp()
    {
        return self::getConsentCookieValue('lastConsentTimestamp');
    }

    public static function getConsentId()
    {
        return self::getConsentCookieValue('consentId');
    }

    public static function getCategoryLabels()
    {
        $categories = self::getConsentCookieValue('categories');

        if (is_array($categories)) {
            $translationsMap = self::getCategoryTranslationsMap();
            $translated = array_map(function ($key) use ($translationsMap) {
                return isset($translationsMap[$key]) ? $translationsMap[$key] : $key;
            }, $categories);
            return implode(', ', $translated);
        }
        return $categories;
    }
}
