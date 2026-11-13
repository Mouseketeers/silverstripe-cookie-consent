<?php

namespace Mouseketeers\CookieConsent\Services;

use Mouseketeers\CookieConsent\CookieConsent;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\i18n\i18n;

class CookieConsentConfigCache implements Flushable
{
    public static function getCache()
    {
        return Injector::inst()->get(CacheInterface::class . '.cookie_consent_config');
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
        return sprintf(
            'cookie_consent_config_%s_%s_site_%d_registry_%s',
            $type,
            i18n::get_locale(),
            SiteConfig::current_site_config()->ID,
            self::getRegistryVersion()
        );
    }

    protected static function getRegistryVersion()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        $fileMtime = $jsonPath !== null ? @filemtime($jsonPath) : false;

        return $fileMtime !== false ? (string) $fileMtime : '0';
    }

    public static function clear()
    {
        self::getCache()->clear();
    }

    public static function flush()
    {
        self::clear();
    }
}
