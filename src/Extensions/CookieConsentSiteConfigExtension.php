<?php

class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentModalTitle' => 'Varchar(255)',
        'CookieConsentModalContent' => 'HTMLText',
        'ExternalMedia' => 'Varchar(255)'
    ];

    private static $has_many = [
        'CookieServices' => 'CookieService.SiteConfig',
        'CustomCookies' => 'CookieDescription.SiteConfig'
    ];

    public function updateCMSFields(FieldList $fields)
    {

        $cookieServicesField = CookieServiceListboxField::create(
            'SelectedCookieServices',
            'Services',
            $this->getServicesOptionsMap()
        )
            ->setMultiple(true)
            ->setValue(array_values($this->owner->CookieServices()->column('Name')));

        $externalMediaField = ListboxField::create(
            'ExternalMedia',
            $this->owner->fieldLabel('ExternalMedia'),
            $this->getExternalMediaOptionsMap()
        )
            ->setMultiple(true)
            ->setValue($this->getExternalMediaValueArray());


        $fields->addFieldsToTab('Root.CookieConsent', [
            HeaderField::create('CookieConsentHeader', 'Cookie Consent Settings'),
            TextField::create('CookieConsentModalTitle'),
            HtmlEditorField::create('CookieConsentModalContent')->setRows(5),
            HeaderField::create('CookieServicesHeader', 'Third-Party Services'),
            $cookieServicesField,
            $externalMediaField,
            HeaderField::create('CustomCookiesHeader', 'Custom Cookies'),
            GridField::create('CustomCookies', 'Custom Cookies', $this->owner->CustomCookies(), GridFieldConfig_RecordEditor::create()),
            CheckboxField::create('DeactivateCookieConsent', 'Deactivate Cookie Consent for this Site')
        ]);
    }

    protected function getExternalMediaOptionsMap()
    {
        $availableMediaServices = CookieConsent::getExternalMediaConfig();
        $options = [];
        foreach ($availableMediaServices as $serviceKey) {
            $options[$serviceKey] = _t('CookieConsent.ExternalMediaServices.' . $serviceKey, $serviceKey);
        }
        return $options;
    }

    protected function getExternalMediaValueArray()
    {
        return explode(',', $this->owner->ExternalMedia);
    }

    protected function getServicesOptionsFromCookieRegistry()
    {
        $cachedOptions = CookieConsentServiceOptionsCache::load();
        if ($cachedOptions !== null) {
            return $cachedOptions;
        }

        $options = [];
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath !== null && file_exists($jsonPath)) {
            $raw = @file_get_contents($jsonPath);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $names = array_keys($data);
                    sort($names);

                    foreach ($names as $serviceName) {
                        $options[$serviceName] = $serviceName;
                    }
                }
            }
        }

        CookieConsentServiceOptionsCache::save($options);

        return $options;
    }

    protected function getServicesOptionsMap()
    {
        $siteConfigId = isset($this->owner->ID) ? (int) $this->owner->ID : 0;
        $cacheKey = CookieConsentServiceOptionsCache::getOptionsMapCacheKey($siteConfigId);

        $cachedOptionsMap = CookieConsentServiceOptionsCache::load($cacheKey);
        if ($cachedOptionsMap !== null) {
            return $cachedOptionsMap;
        }

        $serviceOptionsMap = $this->getServicesOptionsFromCookieRegistry();
        foreach ($this->owner->CookieServices() as $selectedService) {
            $serviceName = $selectedService->Name;
            $serviceOptionsMap[$serviceName] = $serviceName;
        }

        CookieConsentServiceOptionsCache::save($serviceOptionsMap, $cacheKey);

        return $serviceOptionsMap;
    }

    public function requireDefaultRecords()
    {
        if ($config = SiteConfig::current_site_config()) {
            if (empty($config->CookieConsentModalTitle)) {
                $config->CookieConsentModalTitle = _t('CookieConsent.CookieConsentModalTitle');
            }

            if (empty($config->CookieConsentModalContent)) {
                $config->CookieConsentModalContent = _t('CookieConsent.CookieConsentModalContent');
            }

            $config->write();
        }
    }

    public function onBeforeWrite()
    {
        $dbValue = $this->owner->ExternalMedia;

        if (is_array($dbValue)) {
            $this->owner->ExternalMedia = implode(',', $dbValue);
            return;
        }
    }

    public function onAfterWrite()
    {
        CookieConsentConfigCache::clear();
        CookieConsentServiceOptionsCache::clear();
    }

    public function onAfterDelete()
    {
        CookieConsentConfigCache::clear();
        CookieConsentServiceOptionsCache::clear();
    }
}
