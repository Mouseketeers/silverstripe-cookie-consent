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

        $serviceOptions = $this->getServicesOptionsFromCookieRegistry();
        foreach ($this->owner->CookieServices() as $selectedService) {
            $serviceOptions[$selectedService->Name] = $selectedService->Name;
        }

        $cookieServicesField = Injector::inst()->create(
            'CookieServiceListboxField',
            'SelectedCookieServices',
            'Services',
            $serviceOptions
        )
            ->setRelationName('CookieServices')
            ->setMultiple(true)
            ->setSize(12)
            ->setValue(array_values($this->owner->CookieServices()->column('Name')));

        $fields->addFieldsToTab('Root.CookieConsent', [
            TextField::create('CookieConsentModalTitle', $this->owner->fieldLabel('CookieConsentModalTitle')),
            HtmlEditorField::create('CookieConsentModalContent', $this->owner->fieldLabel('CookieConsentModalContent'))->setRows(5),
            $cookieServicesField,
            $customCookiesField
        ]);
    }

    protected function getServicesOptionsFromCookieRegistry()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return [];
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $names = array_keys($data);
        sort($names);

        $options = [];
        foreach ($names as $serviceName) {
            $normalizedName = trim((string) $serviceName);
            if ($normalizedName === '') {
                continue;
            }

            $options[$normalizedName] = $normalizedName;
        }

        return $options;
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
    }

    public function onAfterDelete()
    {
        CookieConsentConfigCache::clear();
    }
}
