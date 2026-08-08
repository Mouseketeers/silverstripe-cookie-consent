<?php

class CookieService extends DataObject
{

    private static $singular_name = 'Cookie Service';

    private static $plural_name = 'Cookie Services';

    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_many = [
        'CookieDescriptions' => 'CookieDescription'
    ];

    private static $belongs_many_many = [
        'CookieSections' => 'CookieSection'
    ];

    // public function getCMSFields()
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
        $this->syncCookieServicesFromDescriptions();
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

        $providers = [];
        foreach ($cookies as $cookie) {
            if ($cookie->Provider) {
                $providers[$cookie->Provider] = $cookie->Provider;
            }
        }

        foreach ($providers as $provider) {
            $service = CookieService::get()->filter('Title', $provider)->first();
            if (!$service) {
                $service = CookieService::create();
                $service->Title = $provider;
                $service->write();
            }

            $providerCookies = CookieDescription::get()->filter('Provider', $provider);
            foreach ($providerCookies as $cookie) {
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
