<?php

class CookieConsent
{

    private static $disable_module = false;
    private static $disable_iframe_manager = false;
    private static $disable_default_js = false;
    private static $disable_default_css = false;
    private static $enable_consent_logging = true;
    private static $enable_google_consent_mode = false;
    private static $cookie_registry_path = 'cookie-consent/open-cookie-database.json';

    private static $cookie_consent_values_cache = null;
    private static $site_config_cache = null;
    private static $cookie_services_cache = null;
    private static $custom_cookies_cache = null;
    private static $external_media_cache = null;
    private static $selected_external_media_cache = null;


    public static function isModuleDisabled()
    {
        $isDisabled = Config::inst()->get('CookieConsent', 'disable_module');
        if(!$isDisabled) {
            $siteConfig = self::getSiteConfig();
            return $siteConfig->DeactivateCookieConsentManager;
        }
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

    public static function getExternalMediaCategoryConfig()
    {
        return Config::inst()->get('CookieConsent', 'external_media_category');
    }

    public static function getExternalMediaConfig()
    {
        $externalMediaConfig = Config::inst()->get('CookieConsent', 'external_media_service_options');
        return is_array($externalMediaConfig) ? $externalMediaConfig : [];
    }

    // public static function hasCookies()
    // {
    //     $siteConfig = self::getSiteConfig();

    //     if (!$siteConfig || empty($siteConfig->CookieConsentModalTitle) || empty($siteConfig->CookieConsentModalContent)) {
    //         return false;
    //     }

    //     // Check for selected cookie services
    //     $cookieServices = self::getCookieServices();
    //     if ($cookieServices && $cookieServices->exists()) {
    //         return true;
    //     }

    //     // Check for custom cookies
    //     $customCookies = self::getCustomCookies();
    //     if ($customCookies && $customCookies->exists()) {
    //         return true;
    //     }

    //     // Check for selected external media
    //     $selectedExternalMedia = self::getSelectedExternalMedia();
    //     if (!empty($selectedExternalMedia)) {
    //         return true;
    //     }

    //     // Check if any categories have default cookies configured
    //     $categories = self::getCategoryConfig();
    //     foreach ($categories as $categoryData) {
    //         if (!empty($categoryData['cookies'])) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }

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

    public static function getExternalMedia()
    {
        if (self::$external_media_cache === null) {
            $siteConfig = self::getSiteConfig();
            self::$external_media_cache = $siteConfig ? $siteConfig->ExternalMedia() : null;
        }
        return self::$external_media_cache;
    }

    public static function getSelectedExternalMedia()
    {
        if (self::$selected_external_media_cache === null) {
            $externalMedia = self::getExternalMedia();
            if (!$externalMedia || !$externalMedia->exists()) {
                self::$selected_external_media_cache = [];
            } else {
                self::$selected_external_media_cache = $externalMedia->column('Name');
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

    public static function createDataBuilder()
    {
        return new CookieConsentDataBuilder();
    }

}
