<?php

class CookieConsent
{

    private static $disable_cookie_consent = false;
    private static $disable_default_js = false;
    private static $disable_default_css = false;
    private static $enable_google_consent_mode = false;
    private static $enable_consent_logging = false;
    private static $cookie_registry_path = 'cookie-consent/open-cookie-database.json';
    private static $clear_cookies_on_cookie_registry_update = true;
    private static $categories = [
        'functional' => [
            'readOnly' => true
        ]
    ];
    private static $cookie_consent_values_cache = null;

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

    public static function isConsentRegistrationEnabled()
    {
        return class_exists('ConsentRecord') && !Config::inst()->get('CookieConsent', 'enable_consent_logging') == false;
    }

    public static function isExternalMediaManagementEnabled() {
        return Config::inst()->get('CookieConsent', 'enable_external_media_management');
    }

    public static function getGuiOptions() 
    {
        $guiOptions = Config::inst()->get('CookieConsent', 'gui_options');
        return is_array($guiOptions) ? $guiOptions : [];
    }

    public static function getExternalMediaCategory()
    {
        return Config::inst()->get('CookieConsent', 'external_media_category');
    }

    public static function getExternalMediaConfig()
    {
        $externalMediaConfig = Config::inst()->get('CookieConsent', 'external_media_services');
        return is_array($externalMediaConfig) ? $externalMediaConfig : [];
    }
    public static function getSelectedExternalMedia()
    {
        $siteConfig = SiteConfig::current_site_config();        
        if(!$siteConfig->ExternalMedia) {
            return [];
        }        
        return explode(',', $siteConfig->ExternalMedia);
    }
    public static function getCategoryConfig()
    {
        $categories = Config::inst()->get('CookieConsent', 'categories');
        return is_array($categories) ? $categories : [];
    }

    public static function getCategoryTranslationsMap()
    {
        $options = [];
        $categories = self::getCategoryConfig();

        foreach ($categories as $categoryId => $categoryConfig) {
            if (!is_string($categoryId) || $categoryId === '') {
                continue;
            }
            $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
            $options[$categoryId] = _t($translationKey);
        }

        return $options;
    }
    public static function shouldClearCookiesOnCookieRegistryUpdate()
    {
        return (bool) Config::inst()->get('CookieConsent', 'clear_cookies_on_cookie_registry_update');
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
        if (self::$cookie_consent_values_cache !== null) {
            return self::$cookie_consent_values_cache;
        }

        $cookieValue = self::getCookie();
        if (!$cookieValue) {
            self::$cookie_consent_values_cache = null;
            return null;
        }

        $decodedValue = rawurldecode($cookieValue);
        $decodedData = json_decode($decodedValue, true);

        self::$cookie_consent_values_cache = is_array($decodedData) ? $decodedData : null;

        return self::$cookie_consent_values_cache;
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
