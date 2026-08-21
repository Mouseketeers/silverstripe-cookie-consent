<?php

/**
 * Shared cache helper for cookie consent caches.
 *
 * Provides the common registry-version invalidation logic used by
 * CookieConsentConfigCache and CookieConsentServiceOptionsCache.
 *
 * Implemented as a concrete helper class (rather than an abstract base class)
 * to avoid SilverStripe 3.x issues with abstract classes implementing the
 * Flushable interface.
 */
class CookieConsentCacheHelper
{
    const REGISTRY_MTIME_CACHE_KEY = 'cookie_registry_mtime';

    /**
     * Get a cache instance, optionally checking whether the cookie registry
     * file has been updated since the cache was last populated.
     *
     * @param string $cacheName
     * @param bool $checkRegistryVersion
     * @return Zend_Cache_Core
     */
    public static function getCache($cacheName, $checkRegistryVersion = true)
    {
        return SS_Cache::factory($cacheName);
        if ($checkRegistryVersion) {
            self::clearIfRegistryFileUpdated($cache);
        }

        return $cache;
    }

    /**
     * Clear the given cache and store the current registry version.
     *
     * @param string $cacheName
     */
    public static function clear($cacheName)
    {
        $cache = self::getCache($cacheName, false);
        $cache->clean(Zend_Cache::CLEANING_MODE_ALL);

        $registryVersion = self::getRegistryVersion();
        $cache->save($registryVersion, self::REGISTRY_MTIME_CACHE_KEY);
    }

    /**
     * Get the current version of the cookie registry file (its mtime).
     *
     * @return string
     */
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