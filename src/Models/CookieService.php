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

    public function getCookieTranslationsForCategory($category)
    {
        if (!$this->Name) {
            return new ArrayList();
        }

        $normalizedCategory = strtolower(trim((string) $category));
        if ($normalizedCategory === '') {
            return new ArrayList();
        }

        $registryData = self::getCookieRegistryData();
        if (empty($registryData)) {
            return new ArrayList();
        }

        $serviceCookies = self::resolveJsonServiceData($registryData, $this->Name);
        if (!is_array($serviceCookies)) {
            return new ArrayList();
        }

        $cookieDescriptions = new ArrayList();
        foreach ($serviceCookies as $cookieData) {
            if (!is_array($cookieData)) {
                continue;
            }

            $cookieCategory = strtolower(trim((string) (isset($cookieData['category']) ? $cookieData['category'] : '')));
            if ($cookieCategory === '' || $cookieCategory !== $normalizedCategory) {
                continue;
            }

            $cookieName = trim((string) (isset($cookieData['cookie']) ? $cookieData['cookie'] : ''));
            if ($cookieName === '') {
                continue;
            }

            $isWildcard = (bool) (int) (isset($cookieData['wildcardMatch']) ? $cookieData['wildcardMatch'] : 0);

            $cookieDescriptions->push(ArrayData::create([
                'Name' => $isWildcard ? $cookieName . '*' : $cookieName,
                'Service' => $this->Name,
                'Vendor' => isset($cookieData['dataController']) ? $cookieData['dataController'] : '',
                'PrivacyPolicyURL' => isset($cookieData['privacyLink']) ? $cookieData['privacyLink'] : '',
                'Domain' => isset($cookieData['domain']) ? $cookieData['domain'] : '',
                'Category' => $cookieCategory,
                'Description' => isset($cookieData['description']) ? $cookieData['description'] : '',
                'Expiration' => isset($cookieData['retentionPeriod']) ? $cookieData['retentionPeriod'] : ''
            ]));
        }

        return $cookieDescriptions;
    }

    protected static function getCookieRegistryData()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return [];
        }

        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = self::getCookieRegistryCacheKey($jsonPath);
        $cachedRegistryData = $cache->load($cacheKey);

        if (is_array($cachedRegistryData)) {
            return $cachedRegistryData;
        }

        if (is_string($cachedRegistryData)) {
            $decodedRegistryData = @unserialize($cachedRegistryData);
            if (is_array($decodedRegistryData)) {
                return $decodedRegistryData;
            }
        }

        $rawRegistryData = file_get_contents($jsonPath);
        if ($rawRegistryData === false) {
            return [];
        }

        $registryData = json_decode($rawRegistryData, true);
        if (!is_array($registryData)) {
            return [];
        }

        $cache->save(serialize($registryData), $cacheKey);

        return $registryData;
    }

    protected static function getCookieRegistryCacheKey($jsonPath)
    {
        $fileMtime = @filemtime($jsonPath);
        $version = $fileMtime !== false ? (string) $fileMtime : '0';

        return sprintf('cookie_registry_data_%s', sha1($jsonPath . '|' . $version));
    }

    protected static function resolveJsonServiceData(array $data, $serviceName)
    {
        $serviceName = trim((string) $serviceName);
        if ($serviceName === '') {
            return null;
        }

        $normalizedServiceName = strtolower($serviceName);
        foreach ($data as $jsonServiceName => $cookieEntries) {
            if (strtolower(trim((string) $jsonServiceName)) === $normalizedServiceName) {
                return $cookieEntries;
            }
        }

        return null;
    }

    public static function importFromJSON(array $selectedServices)
    {
        $importedServices = [];

        foreach ($selectedServices as $serviceName) {
            $serviceName = trim((string) $serviceName);
            if ($serviceName === '') {
                continue;
            }

            $service = CookieService::get()->filter('Name', $serviceName)->first();
            if (!$service) {
                $service = CookieService::create();
                $service->Name = $serviceName;
                $service->write();
            }

            $importedServices[] = $service;
        }

        return $importedServices;
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
