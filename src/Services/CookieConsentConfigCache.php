<?php

class CookieConsentConfigCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_config';

    public static function getCache($checkRegistryVersion = true)
    {
        return CookieConsentCacheHelper::getCache(self::CACHE_NAME, $checkRegistryVersion);
    }

    public static function getCacheKey()
    {
        return self::getJsCacheKey();
    }

    public static function getJsCacheKey()
    {
        $locale = i18n::get_locale();
        $siteConfig = SiteConfig::current_site_config();
        $siteConfigId = $siteConfig ? (int) $siteConfig->ID : 0;
        
        $registryVersion = CookieConsentCacheHelper::getRegistryVersion();

        return sprintf('cookie_consent_config_js_%s_site_%s_registry_%s', $locale, $siteConfigId, $registryVersion);
    }

    public static function getDeclarationCacheKey()
    {
        $locale = i18n::get_locale();
        $siteConfig = SiteConfig::current_site_config();
        $siteConfigId = $siteConfig ? (int) $siteConfig->ID : 0;

        $registryVersion = CookieConsentCacheHelper::getRegistryVersion();

        return sprintf('cookie_consent_config_declaration_%s_site_%s_registry_%s', $locale, $siteConfigId, $registryVersion);
    }

    public static function clear()
    {
        CookieConsentCacheHelper::clear(self::CACHE_NAME);
    }

    public static function flush()
    {
        self::clear();
    }
}