<?php

namespace Mouseketeers\CookieConsent\Extensions;

use SilverStripe\Core\Extension;
use Mouseketeers\CookieConsent\CookieConsent;
use Mouseketeers\CookieConsent\Forms\CookieServiceListboxField;
use Mouseketeers\CookieConsent\Models\CookieDescription;
use Mouseketeers\CookieConsent\Models\CookieService;
use Mouseketeers\CookieConsent\Models\ExternalMedia;
use Mouseketeers\CookieConsent\Services\CookieConsentConfigCache;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;

class CookieConsentSiteConfigExtension extends Extension
{
    private static $db = [
        'CookieConsentModalTitle' => 'Varchar(255)',
        'CookieConsentModalContent' => 'HTMLText',
        'DeactivateCookieConsentManager' => 'Boolean'
    ];

    private static $has_many = [
        'CookieServices' => CookieService::class . '.SiteConfig',
        'ExternalMedia' => ExternalMedia::class . '.SiteConfig',
        'CustomCookies' => CookieDescription::class . '.SiteConfig'
    ];

    public function updateCMSFields(FieldList $fields)
    {

        $cookieServicesField = CookieServiceListboxField::create(
            'SelectedCookieServices',
            'Services',
            $this->getServicesOptionsMap()
        )
            ->setValue(array_values($this->getOwner()->CookieServices()->column('Name')));

        $externalMediaField = CookieServiceListboxField::create(
            'SelectedExternalMedia',
            'External Media',
            $this->getExternalMediaOptionsMap()
        )
            ->setRelationName('ExternalMedia')
            ->setDataObjectClass(ExternalMedia::class)
            ->setValue(array_values($this->getOwner()->ExternalMedia()->column('Name')));


        $fields->addFieldsToTab('Root.CookieConsent', [
            HeaderField::create('CookieConsentHeader', 'Cookie Consent Settings'),
            TextField::create('CookieConsentModalTitle'),
            HtmlEditorField::create('CookieConsentModalContent')->setRows(5),
            HeaderField::create('CookieServicesHeader', 'Third-Party Services'),
            $cookieServicesField,
            $externalMediaField,
            HeaderField::create('CustomCookiesHeader', 'Custom Cookies'),
            GridField::create('CustomCookies', 'Custom Cookies', $this->getOwner()->CustomCookies(), GridFieldConfig_RecordEditor::create()),
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
