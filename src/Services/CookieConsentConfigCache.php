<?php

class CookieConsentConfigCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_config';
    const REGISTRY_MTIME_CACHE_KEY = 'cookie_registry_mtime';

    public static function getCache($checkRegistryVersion = true)
    {

        $cache = SS_Cache::factory(self::CACHE_NAME);
        if ($checkRegistryVersion) {
            self::clearIfRegistryFileUpdated($cache);
        }

        return $cache;
    }

    public static function getCacheKey()
    {
        $locale = i18n::get_locale();
        $siteConfig = SiteConfig::current_site_config();
        $siteConfigId = $siteConfig ? (int) $siteConfig->ID : 0;
        
        $registryVersion = CookieConsent::shouldClearCookiesOnCookieRegistryUpdate()
            ? self::getRegistryVersion()
            : '0';

        return sprintf('cookie_consent_config_%s_site_%s_registry_%s', $locale, $siteConfigId, $registryVersion);
    }

    protected static function getRegistryVersion()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return '0';
        }

        $fileMtime = @filemtime($jsonPath);

        return $fileMtime !== false ? (string) $fileMtime : '0';
    }

    public static function clear()
    {
        $cache = self::getCache(false);
        $cache->clean(Zend_Cache::CLEANING_MODE_ALL);

        $registryVersion = self::getRegistryVersion();
        $cache->save($registryVersion, self::REGISTRY_MTIME_CACHE_KEY);
    }

    public static function flush()
    {
        self::clear();
    }

    protected static function clearIfRegistryFileUpdated($cache)
    {
        if (!CookieConsent::shouldClearCookiesOnCookieRegistryUpdate()) {
            return;
        }

        $currentVersion = self::getRegistryVersion();
        $storedVersion = $cache->load(self::REGISTRY_MTIME_CACHE_KEY);

        if ($storedVersion === false) {
            $cache->save($currentVersion, self::REGISTRY_MTIME_CACHE_KEY);
            return;
        }

        if ((string) $storedVersion !== (string) $currentVersion) {
            $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
            $cache->save($currentVersion, self::REGISTRY_MTIME_CACHE_KEY);
        }
    }
}