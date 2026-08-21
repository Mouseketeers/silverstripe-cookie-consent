<?php

class CookieConsentServiceOptionsCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_service_options';
    const OPTIONS_CACHE_KEY = 'service_options_registry_map';

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
        return CookieConsentCacheHelper::getCache(self::CACHE_NAME, $checkRegistryVersion);
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