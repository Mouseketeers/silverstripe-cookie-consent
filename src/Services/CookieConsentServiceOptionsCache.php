<?php

class CookieConsentServiceOptionsCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_service_options';
    const OPTIONS_CACHE_KEY = 'service_options_registry_map';
    const REGISTRY_MTIME_CACHE_KEY = 'cookie_registry_mtime';

    public static function load($cacheKey = self::OPTIONS_CACHE_KEY)
    {
        $cache = self::getCache();
        $cachedOptions = $cache->load($cacheKey);

        if ($cachedOptions === false) {
            return null;
        }

        if (!is_string($cachedOptions)) {
            return null;
        }

        $decodedOptions = @unserialize($cachedOptions);

        return is_array($decodedOptions) ? $decodedOptions : null;
    }

    public static function save(array $options, $cacheKey = self::OPTIONS_CACHE_KEY)
    {
        $cache = self::getCache();
        $cache->save(serialize($options), $cacheKey);
    }

    public static function getOptionsMapCacheKey($siteConfigId)
    {
        return sprintf('service_options_map_site_%s', (int) $siteConfigId);
    }

    public static function getCache($checkRegistryVersion = true)
    {
        $cache = SS_Cache::factory(self::CACHE_NAME);
        if ($checkRegistryVersion) {
            self::clearIfRegistryFileUpdated($cache);
        }

        return $cache;
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