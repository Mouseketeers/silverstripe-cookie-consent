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
    private static $cookie_consent_values_cache = null;
    private static $site_config_cache = null;
    private static $cookie_services_cache = null;
    private static $custom_cookies_cache = null;
    private static $selected_external_media_cache = null;

    public static function createConfigBuilder()
    {
        return new CookieConsentConfigBuilder();
    }    

    public static function isCookieConsentDisabled()
    {
        return Config::inst()->get('CookieConsent', 'disable_cookie_consent') || !self::hasDataToRender();
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

    public static function getCookieRegistryPath()
    {
        $path = Config::inst()->get('CookieConsent', 'cookie_registry_path');
        if (!is_string($path) || trim($path) === '') {
            $path = self::$cookie_registry_path;
        }

        return $path;
    }
    
    public static function getCategoryConfig()
    {
        $categories = Config::inst()->get('CookieConsent', 'categories');  
        return is_array($categories) ? $categories : [];
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

    public static function hasDataToRender()
    {
        $siteConfig = self::getSiteConfig();

        if (!$siteConfig || empty($siteConfig->CookieConsentModalTitle) || empty($siteConfig->CookieConsentModalContent)) {
            return false;
        }

        // Check for selected cookie services
        $cookieServices = self::getCookieServices();
        if ($cookieServices && $cookieServices->exists()) {
            return true;
        }

        // Check for custom cookies
        $customCookies = self::getCustomCookies();
        if ($customCookies && $customCookies->exists()) {
            return true;
        }

        // Check for selected external media
        $selectedExternalMedia = self::getSelectedExternalMedia();
        if (!empty($selectedExternalMedia)) {
            return true;
        }

        // Check if any categories have default cookies configured
        $categories = self::getCategoryConfig();
        foreach ($categories as $categoryData) {
            if (!empty($categoryData['cookies'])) {
                return true;
            }
        }

        return false;
    }    

    public static function getSiteConfig()
    {
        if (self::$site_config_cache === null) {
            self::$site_config_cache = SiteConfig::current_site_config();
        }
        return self::$site_config_cache;
    }

    public static function getCookieServices()
    {
        if (self::$cookie_services_cache === null) {
            $siteConfig = self::getSiteConfig();
            self::$cookie_services_cache = $siteConfig ? $siteConfig->CookieServices() : null;
        }
        return self::$cookie_services_cache;
    }

    public static function getCustomCookies()
    {
        if (self::$custom_cookies_cache === null) {
            $siteConfig = self::getSiteConfig();
            self::$custom_cookies_cache = $siteConfig ? $siteConfig->CustomCookies() : null;
        }
        return self::$custom_cookies_cache;
    }

    public static function getSelectedExternalMedia()
    {
        if (self::$selected_external_media_cache === null) {
            $siteConfig = self::getSiteConfig();
            if (!$siteConfig || !$siteConfig->ExternalMedia) {
                self::$selected_external_media_cache = [];
            } else {
                self::$selected_external_media_cache = explode(',', $siteConfig->ExternalMedia);
            }
        }
        return self::$selected_external_media_cache;
    }

    public static function getCategoryTranslationsMap()
    {
        $options = [];
        $categories = self::getCategoryConfig();

        foreach ($categories as $categoryId => $categoryConfig) {
            if (!is_string($categoryId) || $categoryId === '') {
                continue;
            }
            $translationKey = self::getCategoryTranslationKey($categoryId);
            $options[$categoryId] = _t($translationKey);
        }

        return $options;
    }

    public static function getCategoryTranslationKey($categoryId)
    {
        return sprintf('CookieConsent.Category.%s', $categoryId);
    }

    public static function resolveCookieRegistryPath()
    {
        $path = self::getCookieRegistryPath();
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return BASE_PATH . '/' . ltrim($path, '/');
    }

    public static function getConsentCookie()
    {
        return Cookie::get('cc_cookie');
    }

    public static function getConsentCookieValues()
    {
        if (self::$cookie_consent_values_cache !== null) {
            return self::$cookie_consent_values_cache;
        }

        $cookieValue = self::getConsentCookie();
        if (!$cookieValue) {
            self::$cookie_consent_values_cache = null;
            return null;
        }

        $decodedValue = rawurldecode($cookieValue);
        $decodedData = json_decode($decodedValue, true);

        self::$cookie_consent_values_cache = is_array($decodedData) ? $decodedData : null;

        return self::$cookie_consent_values_cache;
    }

    public static function getLastConsentTimestamp()
    {
        return self::getCosentCookieValue('lastConsentTimestamp');
    }

    public static function getConsentId()
    {
        return self::getCosentCookieValue('consentId');
    }

    public static function getCategoryLabels()
    {
        $categories = self::getCosentCookieValue('categories');

        if (is_array($categories)) {
            $translationsMap = self::getCategoryTranslationsMap();
            $translated = array_map(function ($key) use ($translationsMap) {
                return isset($translationsMap[$key]) ? $translationsMap[$key] : $key;
            }, $categories);
            return implode(', ', $translated);
        }
        return $categories;
    }
    
    private static function getCosentCookieValue($key)
    {
        
        $decodedData = self::getConsentCookieValues();

        if (is_array($decodedData) && isset($decodedData[$key])) {
            return $decodedData[$key];
        }

        return null;
    }    
}
