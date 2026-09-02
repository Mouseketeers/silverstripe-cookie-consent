<?php

namespace Mouseketeers\CookieConsent\Services;

use Mouseketeers\CookieConsent\CookieConsent;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Injector\Injector;

class CookieConsentCacheHelper
{
    const REGISTRY_MTIME_CACHE_KEY = 'cookie_registry_mtime';

    public static function getCache($cacheName, $checkRegistryVersion = true)
    {
        $serviceName = CacheInterface::class . '.' . $cacheName;
        $injector = Injector::inst();

        if ($injector->getServiceSpec($serviceName, false)) {
            $cache = $injector->get($serviceName);
        } else {
            $cache = $injector->get(CacheFactory::class)->create($serviceName, [
                'namespace' => $cacheName,
                'defaultLifetime' => 0,
            ]);
        }

        if ($checkRegistryVersion) {
            self::clearIfRegistryFileUpdated($cache);
        }

        return $cache;
    }

    public static function clear($cacheName)
    {
        $cache = self::getCache($cacheName, false);
        $cache->clear();

        $registryVersion = self::getRegistryVersion();
        $cache->set(self::REGISTRY_MTIME_CACHE_KEY, $registryVersion);
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

    protected static function clearIfRegistryFileUpdated(CacheInterface $cache)
    {
        $registryVersion = self::getRegistryVersion();
        $cachedVersion = $cache->get(self::REGISTRY_MTIME_CACHE_KEY);

        if ($cachedVersion !== $registryVersion) {
            $cache->clear();
            $cache->set(self::REGISTRY_MTIME_CACHE_KEY, $registryVersion);
        }
    }
}
