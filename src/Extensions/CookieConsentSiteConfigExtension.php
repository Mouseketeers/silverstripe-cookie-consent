<?php

class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentModalTitle' => 'Varchar(255)',
        'CookieConsentModalContent' => 'HTMLText'
    ];

    private static $has_many = [
        'CookieServices' => 'CookieService.SiteConfig',
        'CustomCookies' => 'CookieDescription.SiteConfig'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $customCookiesField = GridField::create('CustomCookies', 'Custom Cookies', $this->owner->CustomCookies(), GridFieldConfig_RecordEditor::create());

        $cookieServicesField = Injector::inst()->create(
            'CookieServiceListboxField',
            'SelectedCookieServices',
            'Services',
            $this->getServicesOptionsMapFromCookieRegistry()
        )
            ->setRelationName('CookieServices')
            ->setMultiple(true)
            ->setSize(12)
            ->setValue(array_values($this->owner->CookieServices()->column('Name')));

        $fields->addFieldsToTab('Root.CookieConsent', [
            HeaderField::create('CookieConsentHeader', 'Cookie Modal Settings'),
            TextField::create('CookieConsentModalTitle', $this->owner->fieldLabel('CookieConsentModalTitle')),
            HtmlEditorField::create('CookieConsentModalContent', $this->owner->fieldLabel('CookieConsentModalContent'))->setRows(5),
            HeaderField::create('CookieServicesHeader', 'Third-Party Services'),
            $cookieServicesField,
            HeaderField::create('CustomCookiesHeader', 'Custom Cookies'),
            $customCookiesField
        ]);
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

    protected function getServicesOptionsMapFromCookieRegistry()
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
