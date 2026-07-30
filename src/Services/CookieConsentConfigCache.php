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

        return sprintf('cookie_consent_config_%s_site_%s', $locale, $subsiteId);
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