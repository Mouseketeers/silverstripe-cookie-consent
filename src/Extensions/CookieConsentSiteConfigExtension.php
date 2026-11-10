<?php

class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentModalTitle' => 'Varchar(255)',
        'CookieConsentModalContent' => 'HTMLText',
        'DeactivateCookieConsentManager' => 'Boolean'
    ];

    private static $has_many = [
        'CookieServices' => 'CookieService.SiteConfig',
        'ExternalMedia' => 'ExternalMedia.SiteConfig',
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

        $externalMediaField = CookieServiceListboxField::create(
            'SelectedExternalMedia',
            'External Media',
            $this->getExternalMediaOptionsMap()
        )
            ->setRelationName('ExternalMedia')
            ->setDataObjectClass('ExternalMedia')
            ->setMultiple(true)
            ->setValue(array_values($this->owner->ExternalMedia()->column('Name')));


        $fields->addFieldsToTab('Root.CookieConsent', [
            HeaderField::create('CookieConsentHeader', 'Cookie Consent Settings'),
            TextField::create('CookieConsentModalTitle'),
            HtmlEditorField::create('CookieConsentModalContent')->setRows(5),
            HeaderField::create('CookieServicesHeader', 'Third-Party Services'),
            $cookieServicesField,
            $externalMediaField,
            HeaderField::create('CustomCookiesHeader', 'Custom Cookies'),
            GridField::create('CustomCookies', 'Custom Cookies', $this->owner->CustomCookies(), GridFieldConfig_RecordEditor::create()),
            CheckboxField::create('DeactivateCookieConsentManager', 'Deactivate Cookie Consent Manager for this Site')
        ]);
    }

    protected function getExternalMediaOptionsMap()
    {
        $availableMediaServices = CookieConsent::getExternalMediaConfig();
        $options = [];
        foreach ($availableMediaServices as $serviceKey => $serviceConfig) {
            $options[$serviceKey] = $serviceConfig['label'];
        }
        return $options;
    }

    protected function getServicesOptionsMap()
    {
        $options = [];
        foreach (array_keys(CookieService::getCookieRegistryData()) as $serviceName) {
            if ($serviceName) {
                $options[CookieService::normalizeServiceName($serviceName)] = $serviceName;
            }
        }

        return $options;
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
