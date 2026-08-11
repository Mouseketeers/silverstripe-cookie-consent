<?php

class CookieService extends DataObject
{

    private static $singular_name = 'Service';

    private static $plural_name = 'Services';

    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    private static $has_many = [
        'CookieDescriptions' => 'CookieDescription'
    ];

    private static $default_sort = 'Name ASC';

    protected function syncCookieServicesFromDescriptions()
    {
        $cookies = CookieDescription::get();
        if (!$cookies->exists()) {
            return;
        }

        $services = [];
        $cookiesByService = [];
        foreach ($cookies as $cookie) {
            if (!$cookie->Service) {
                continue;
            }

            $services[$cookie->Service] = $cookie->Service;
            $cookiesByService[$cookie->Service][] = $cookie;
        }

        $existingServices = CookieService::get()
            ->filter('Name', array_values($services))
            ->toArray();

        $servicesByName = [];
        foreach ($existingServices as $service) {
            $servicesByName[$service->Name] = $service;
        }

        foreach ($services as $serviceName) {
            $service = isset($servicesByName[$serviceName]) ? $servicesByName[$serviceName] : null;
            if (!$service) {
                $service = CookieService::create();
                $service->Name = $serviceName;
                $service->write();
                $servicesByName[$serviceName] = $service;
            }

            if (!isset($cookiesByService[$serviceName])) {
                continue;
            }

            foreach ($cookiesByService[$serviceName] as $cookie) {
                if ((int) $cookie->CookieServiceID !== (int) $service->ID) {
                    $cookie->CookieServiceID = $service->ID;
                    $cookie->write();
                }
            }
        }
    }

    public function getCookieDescriptionsForCategory($category)
    {
        if (!$this->ID) {
            return new ArrayList();
        }

        $normalizedCategory = strtolower(trim((string) $category));
        if ($normalizedCategory === '') {
            return new ArrayList();
        }

        return CookieDescription::get()
            ->filter('CookieServiceID', $this->ID)
            ->filter('Category', $normalizedCategory);
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
                $service->write(); // onAfterWrite will handle the import
            }

            $importedServices[] = $service;
        }

        return $importedServices;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        CookieConsentConfigCache::clear();

        if (!$this->ID || !$this->Name) {
            return;
        }

        $this->importCookieDescriptionsFromJSON();
    }

    protected function importCookieDescriptionsFromJSON()
    {
        if (!$this->ID || !$this->Name) {
            return;
        }

        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return;
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }

        $serviceCookies = self::resolveJsonServiceData($data, $this->Name);
        if ($serviceCookies === null) {
            return;
        }

        foreach ($serviceCookies as $cookieData) {
            $this->importCookieDescriptionFromData($cookieData);
        }
    }

    protected function importCookieDescriptionFromData(array $cookieData)
    {
        $cookieName = isset($cookieData['cookie']) ? trim($cookieData['cookie']) : '';
        if ($cookieName === '') {
            return;
        }

        $description = CookieDescription::get()
            ->filter('Name', $cookieName)
            ->filter('CookieServiceID', $this->ID)
            ->first();

        if (!$description) {
            $description = CookieDescription::create();
        }

        $description->Name = $cookieName;
        $description->CookieServiceID = $this->ID;
        $description->Service = $this->Name;
        $description->Category = strtolower(isset($cookieData['category']) ? $cookieData['category'] : '');
        $description->Vendor = isset($cookieData['dataController']) ? $cookieData['dataController'] : '';
        $description->Domain = isset($cookieData['domain']) ? $cookieData['domain'] : '';
        $description->Description = isset($cookieData['description']) ? $cookieData['description'] : '';
        $description->Expiration = isset($cookieData['retentionPeriod']) ? $cookieData['retentionPeriod'] : '';
        $description->PrivacyPolicyURL = isset($cookieData['privacyLink']) ? $cookieData['privacyLink'] : '';
        $description->Wildcard = (bool)(int)(isset($cookieData['wildcardMatch']) ? $cookieData['wildcardMatch'] : 0);
        $description->CookieRegistryID = isset($cookieData['id']) ? $cookieData['id'] : '';
        $description->write();
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        CookieConsentConfigCache::clear();
    }
}
