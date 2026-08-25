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

        foreach ($registryData as $cookieData) {
            $viewModels[] = CookieDescriptionViewModel::fromRegistry($cookieData, $this->Name);
        }

        return $viewModels;
    }

    protected static function getCookieRegistryData()
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

        foreach ($data as $jsonServiceName => $cookieEntries) {
            if ($jsonServiceName === $serviceName) {
                return $cookieEntries;
            }
        }

        return null;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        CookieConsentConfigCache::clear();
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        CookieConsentConfigCache::clear();
    }
}
