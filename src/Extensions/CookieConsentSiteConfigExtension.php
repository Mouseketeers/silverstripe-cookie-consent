<?php

namespace Mouseketeers\CookieConsent\Extensions;

use Mouseketeers\CookieConsent\CookieConsent;
use Mouseketeers\CookieConsent\Forms\CookieServiceListboxField;
use Mouseketeers\CookieConsent\Models\CookieDescription;
use Mouseketeers\CookieConsent\Models\CookieService;
use Mouseketeers\CookieConsent\Models\ExternalMedia;
use Mouseketeers\CookieConsent\Services\CookieConsentConfigCache;
use Mouseketeers\CookieConsent\Services\CookieConsentServiceOptionsCache;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Model\Subsite;

class CookieConsentSiteConfigExtension extends DataExtension
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
            ->setValue(array_values($this->owner->CookieServices()->column('Name')));

        $externalMediaField = CookieServiceListboxField::create(
            'SelectedExternalMedia',
            'External Media',
            $this->getExternalMediaOptionsMap()
        )
            ->setRelationName('ExternalMedia')
            ->setDataObjectClass(ExternalMedia::class)
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
                    // sort($names);
                    foreach ($names as $serviceName) {
                        if(!$serviceName) {
                            continue;
                        }
                        $normalizedKey = CookieService::normalizeServiceName($serviceName);
                        $options[$normalizedKey] = $serviceName;
                    }
                }
            }
        }

        CookieConsentServiceOptionsCache::save($options);

        return $options;
    }

    protected function getServicesOptionsMap()
    {
        $siteConfigId = $this->owner->ID !== null ? (int) $this->owner->ID : 0;
        $cacheKey = CookieConsentServiceOptionsCache::getOptionsMapCacheKey($siteConfigId);

        $cachedOptionsMap = CookieConsentServiceOptionsCache::load($cacheKey);
        if ($cachedOptionsMap !== null) {
            return $cachedOptionsMap;
        }

        $serviceOptionsMap = $this->getServicesOptionsFromCookieRegistry();

        CookieConsentServiceOptionsCache::save($serviceOptionsMap, $cacheKey);

        return $serviceOptionsMap;
    }

    // Set the defaults using requireDefaultRecords instead of populateDefaults
    // because the SiteConfig records are likely already created
    // Loops though SiteConfig records to support Subsites module
    public function requireDefaultRecords()
    {

        $defaultTitle = _t('CookieConsent.CookieConsentModalTitle', 'Your Cookie Preferences');
        $defaultContent = _t(
            'CookieConsent.CookieConsentModalContent',
            '<p>We use cookies to improve your experience and understand how the website is used.</p>'
        );

        $updateConfigs = function () use ($defaultTitle, $defaultContent) {
            foreach (SiteConfig::get() as $config) {
                $hasChanges = false;

                if ($config->CookieConsentModalTitle === null || $config->CookieConsentModalTitle === '') {
                    $config->CookieConsentModalTitle = $defaultTitle;
                    $hasChanges = true;
                }

                if ($config->CookieConsentModalContent === null || $config->CookieConsentModalContent === '') {
                    $config->CookieConsentModalContent = $defaultContent;
                    $hasChanges = true;
                }

                if ($hasChanges) {
                    $config->write();
                }
            }
        };

        if (class_exists(Subsite::class)) {
            $previousFilterState = Subsite::$disable_subsite_filter;
            Subsite::disable_subsite_filter(true);

            try {
                $updateConfigs();
            } finally {
                Subsite::disable_subsite_filter($previousFilterState);
            }
        } else {
            $updateConfigs();
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
