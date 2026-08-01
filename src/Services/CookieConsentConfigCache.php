<?php

namespace Mouseketeers\CookieConsent\Services;

use Mouseketeers\CookieConsent\CookieConsent;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;

class CookieConsentConfigCache implements Flushable
{
    const CACHE_NAME = 'cookie_consent_config';

    public static function getCache(): CacheInterface
    {
        $injector = Injector::inst();
        $serviceName = CacheInterface::class . '.' . self::CACHE_NAME;

        // Allow flush to continue during manifest transitions where the named service is not loaded yet.
        if ($injector->getServiceSpec($serviceName, false)) {
            return $injector->get($serviceName);
        }

        /** @var CacheFactory $factory */
        $factory = $injector->get(CacheFactory::class);
        return $factory->create($serviceName, [
            'namespace' => self::CACHE_NAME,
            'defaultLifetime' => 0,
        ]);
    }

    public static function getCacheKey()
    {
        $locale = i18n::get_locale();
        $subsiteId = CookieConsent::getCurrentSubsiteId();

        return sprintf('cookie_consent_config_%s_site_%s', $locale, $subsiteId);
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