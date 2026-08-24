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
        $configuredMedia = CookieConsent::getExternalMediaConfig();

        if (!is_array($configuredMedia)) {
            return [];
        }

        $options = [];
        foreach ($configuredMedia as $key => $config) {
            $optionKey = trim((string) $key);
            if ($optionKey === '') {
                continue;
            }

            $label = is_array($config) && isset($config['label'])
                ? trim((string) $config['label'])
                : '';

            $options[$optionKey] = $label !== '' ? $label : $optionKey;
        }

        return $options;
    }

    protected function getExternalMediaValueArray()
    {
        $rawValue = $this->owner->ExternalMedia;

        if (is_array($rawValue)) {
            return array_values(array_filter(array_map('trim', $rawValue)));
        }

        $stringValue = trim((string) $rawValue);
        if ($stringValue === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $stringValue))));
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
                        $normalizedName = trim((string) $serviceName);
                        if ($normalizedName === '') {
                            continue;
                        }

                        $options[$normalizedName] = $normalizedName;
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
            $serviceName = trim((string) $selectedService->Name);
            if ($serviceName === '') {
                continue;
            }

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
        $rawValue = $this->owner->ExternalMedia;

        if (is_array($rawValue)) {
            $normalizedValues = array_values(array_filter(array_map('trim', $rawValue)));
            $this->owner->ExternalMedia = implode(',', $normalizedValues);
            return;
        }

        $stringValue = trim((string) $rawValue);
        if ($stringValue === '') {
            $this->owner->ExternalMedia = '';
            return;
        }

        $normalizedValues = array_values(array_filter(array_map('trim', explode(',', $stringValue))));
        $this->owner->ExternalMedia = implode(',', $normalizedValues);
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
