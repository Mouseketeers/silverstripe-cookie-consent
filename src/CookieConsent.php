<?php

namespace Mouseketeers\CookieConsent;

use Mouseketeers\CookieConsent\Services\CookieConsentDataBuilder;
use SilverStripe\Core\Config\Config;
use SilverStripe\SiteConfig\SiteConfig;

class CookieConsent
{

    public static function isModuleDisabled()
    {
        return Config::inst()->get(self::class, 'disable_module')
            || self::getSiteConfig()->DeactivateCookieConsentManager;
    }

    public static function isDefaultJsDisabled()
    {
        return Config::inst()->get(self::class, 'disable_default_js');
    }

    public static function isDefaultCssDisabled()
    {
        return Config::inst()->get(self::class, 'disable_default_css');
    }

    public static function isGoogleConsentModeEnabled()
    {
        return Config::inst()->get(self::class, 'enable_google_consent_mode');
    }

    public static function isIframeManagerDisabled()
    {
        return (bool) Config::inst()->get(self::class, 'disable_iframe_manager');
    }

    public static function isConsentRegistrationEnabled()
    {
        return class_exists(\Mouseketeers\ConsentRecords\ConsentRecord::class) && (bool) Config::inst()->get(self::class, 'enable_consent_logging');
    }

    public static function getCookieRegistryPath()
    {
        return Config::inst()->get(self::class, 'cookie_registry_path');
    }

    public static function getCategoryConfig()
    {
        $categories = Config::inst()->get(self::class, 'categories');
        return is_array($categories) ? $categories : [];
    }

    public static function getGuiOptions()
    {
        $guiOptions = Config::inst()->get(self::class, 'gui_options');
        return is_array($guiOptions) ? $guiOptions : [];
    }

    public static function getExternalMediaCategoryConfig()
    {
        return Config::inst()->get(self::class, 'external_media_category');
    }

    public static function getExternalMediaConfig()
    {
        $externalMediaConfig = Config::inst()->get(self::class, 'external_media_service_options');
        return is_array($externalMediaConfig) ? $externalMediaConfig : [];
    }

    public static function getSiteConfig()
    {
        return SiteConfig::current_site_config();
    }

    public static function getCookieServices()
    {
        return self::getSiteConfig()->CookieServices();
    }

    public static function getCustomCookies()
    {
        return self::getSiteConfig()->CustomCookies();
    }

    public static function getExternalMedia()
    {
        return self::getSiteConfig()->ExternalMedia();
    }

    public static function getSelectedExternalMedia()
    {
        return self::getExternalMedia()->column('Name');
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
            $defaultLabel = ucwords(str_replace(['-', '_'], ' ', $categoryId));
            $options[$categoryId] = _t($translationKey, $defaultLabel);
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

    public static function createDataBuilder()
    {
        return new CookieConsentDataBuilder();
    }

}
