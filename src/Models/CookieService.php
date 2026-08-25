<?php

class CookieService extends DataObject
{

    private static $singular_name = 'Service';

    private static $plural_name = 'Services';

    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    private static $has_one = [
        'SiteConfig' => 'SiteConfig'
    ];

    private static $default_sort = 'Name ASC';

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Name = self::normalizeServiceName($this->Name);
    }

    public function getDisplayName()
    {
        return self::getRegistryServiceDisplayName($this->Name);
    }

    public static function getRegistryServiceDisplayName($normalizedName)
    {
        if (empty($normalizedName)) {
            return $normalizedName;
        }

        $registryData = self::getCookieRegistryData();
        if (empty($registryData)) {
            return $normalizedName;
        }

        foreach ($registryData as $jsonServiceName => $cookieEntries) {
            if (self::normalizeServiceName($jsonServiceName) === $normalizedName) {
                return $jsonServiceName;
            }
        }

        return $normalizedName;
    }

    public static function normalizeServiceName($name)
    {
        $normalized = strtolower(trim((string) $name));
        $normalized = preg_replace('/[^a-z0-9]+/u', '_', $normalized);
        $normalized = trim($normalized, '_');

        return $normalized;
    }

    public function getCookieRegistryDataByCategoryKey($configCategory)
    {

        if (!$this->Name) {
            return [];
        }      
 
        if (empty($configCategory) || !$this->Name) {
            return [];
        }

        $registryData = self::getCookieRegistryData();
        
        if (empty($registryData)) {
            return [];
        }

        $registryDataServiceCookies = self::getCookieRegistryDataByServiceKey($registryData, $this->Name);

        if (!is_array($registryDataServiceCookies)) {
            return [];
        }

        $registryDataCookieDescriptions = [];
        foreach ($registryDataServiceCookies as $registryCookieData) {

            if (!is_array($registryCookieData)) {
                continue;
            }

            $normalizedRegistryCookieCategory = strtolower($registryCookieData['category'] ?? '');
            if ($normalizedRegistryCookieCategory === '' || $normalizedRegistryCookieCategory !== $configCategory) {
                continue;
            }

            $registryDataCookieDescriptions[] = (object) $registryCookieData;
        }

        return $registryDataCookieDescriptions;
    }

    public function getCookieViewModelsByCategoryKey($configCategory)
    {
        $registryData = $this->getCookieRegistryDataByCategoryKey($configCategory);
        $viewModels = [];
        $displayName = $this->getDisplayName();

        foreach ($registryData as $cookieData) {
            $viewModels[] = CookieDescriptionViewModel::fromRegistry($cookieData, $displayName);
        }

        return $viewModels;
    }

    public static function getCookieRegistryData()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if (!$jsonPath || !file_exists($jsonPath)) {
            return [];
        }

        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = self::getCookieRegistryCacheKey($jsonPath);
        $cached = $cache->load($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if (is_string($cached) && is_array($data = @unserialize($cached))) {
            return $data;
        }

        $raw = @file_get_contents($jsonPath);
        if (!is_string($raw) || !is_array($data = json_decode($raw, true))) {
            return [];
        }

        $cache->save(serialize($data), $cacheKey);

        return $data;
    }

    protected static function getCookieRegistryCacheKey($jsonPath)
    {
        $fileMtime = @filemtime($jsonPath);
        $version = $fileMtime !== false ? (string) $fileMtime : '0';

        return sprintf('cookie_registry_data_%s', sha1($jsonPath . '|' . $version));
    }

    protected static function getCookieRegistryDataByServiceKey(array $data, $serviceName)
    {
        
        if (empty($serviceName)) {
            return null;
        }

        $normalizedSearch = self::normalizeServiceName($serviceName);

        foreach ($data as $jsonServiceName => $cookieEntries) {
            if (self::normalizeServiceName($jsonServiceName) === $normalizedSearch) {
                return $cookieEntries;
            }
        }

        return null;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        CookieConsentConfigCache::clear();
        CookieConsentServiceOptionsCache::clear();
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        CookieConsentConfigCache::clear();
        CookieConsentServiceOptionsCache::clear();
    }
}
