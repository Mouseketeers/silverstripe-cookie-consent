<?php

class CookieConsentCacheHelper
{
    const REGISTRY_MTIME_CACHE_KEY = 'cookie_registry_mtime';

    public static function getCache($cacheName, $checkRegistryVersion = true)
    {
        return SS_Cache::factory($cacheName);
        if ($checkRegistryVersion) {
            self::clearIfRegistryFileUpdated($cache);
        }

        return $cache;
    }

    public static function clear($cacheName)
    {
        $cache = self::getCache($cacheName, false);
        $cache->clean(Zend_Cache::CLEANING_MODE_ALL);

        $registryVersion = self::getRegistryVersion();
        $cache->save($registryVersion, self::REGISTRY_MTIME_CACHE_KEY);
    }

    public static function getRegistryVersion()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return '0';
        }

        $fileMtime = @filemtime($jsonPath);

        return $fileMtime !== false ? (string) $fileMtime : '0';
    }
}