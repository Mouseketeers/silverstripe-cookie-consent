<?php

class CookieConsentConfigCache implements Flushable
{
    public static function getCache()
    {
        return SS_Cache::factory('cookie_consent_config');
    }

    public static function getJsCacheKey()
    {
        return self::buildCacheKey('js');
    }

    public static function getDeclarationCacheKey()
    {
        return self::buildCacheKey('declaration');
    }

    protected static function buildCacheKey($type)
    {
        // Zend_Cache ids allow only [a-zA-Z0-9_].
        return preg_replace('/[^a-zA-Z0-9_]/', '_', sprintf(
            'cookie_consent_config_%s_%s_site_%d_registry_%s',
            $type,
            i18n::get_locale(),
            SiteConfig::current_site_config()->ID,
            self::getRegistryVersion()
        ));
    }

    protected static function getRegistryVersion()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        $fileMtime = $jsonPath !== null ? @filemtime($jsonPath) : false;

        return $fileMtime !== false ? (string) $fileMtime : '0';
    }

    public static function clear()
    {
        self::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public static function flush()
    {
        self::clear();
    }
}
