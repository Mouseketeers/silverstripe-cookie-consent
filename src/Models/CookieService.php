<?php

class CookieService extends DataObject
{

    private static $singular_name = 'Cookie Service';

    private static $plural_name = 'Cookie Services';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Text'
    ];

    private static $has_many = [
        'CookieDescriptions' => 'CookieDescription'
    ];

    private static $belongs_many = [
        'CookieSections' => 'CookieSection'
    ];

    

    // private static $belongs_many_many = [
    //     'CookieSections' => 'CookieSection'
    // ];

    // {
    //     $fields = parent::getCMSFields();

    //     $fields->removeByName('CookieSections');

    //     $fields->addFieldsToTab('Root.Main', [
    //         TextField::create('Title', $this->fieldLabel('Title')),
    //         TextField::create('Provider', $this->fieldLabel('Provider')),
    //         TextAreaField::create('Description', $this->fieldLabel('Description')),
    //         TextField::create('Expiration', $this->fieldLabel('Expiration')),
    //         DropdownField::create('Locale', $this->fieldLabel('Locale'), i18n::get_common_locales())
    //             ->setEmptyString('Select...')
    //     ]);

    //     return $fields;
    // }

    public function populateDefaults()
    {
        parent::populateDefaults();
        // $this->syncCookieServicesFromDescriptions();
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $this->syncCookieServicesFromDescriptions();
    }

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
            ->filter('Title', array_values($services))
            ->toArray();

        $servicesByTitle = [];
        foreach ($existingServices as $service) {
            $servicesByTitle[$service->Title] = $service;
        }

        foreach ($services as $serviceName) {
            $service = isset($servicesByTitle[$serviceName]) ? $servicesByTitle[$serviceName] : null;
            if (!$service) {
                $service = CookieService::create();
                $service->Title = $serviceName;
                $service->write();
                $servicesByTitle[$serviceName] = $service;
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
