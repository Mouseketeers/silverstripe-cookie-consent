<?php

class CookieConsentConfigCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_config';

    public static function getCache()
    {

        return SS_Cache::factory(self::CACHE_NAME);
    }

    public static function getCacheKey()
    {
        $locale = i18n::get_locale();
        $subsiteId = CookieConsent::getCurrentSubsiteId();
        $registryVersion = self::getRegistryVersion();

        return sprintf('cookie_consent_config_%s_site_%s_registry_%s', $locale, $subsiteId, $registryVersion);
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
        $cache = self::getCache();
        $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public static function flush()
    {
        self::clear();
    }
}