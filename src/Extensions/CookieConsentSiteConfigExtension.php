<?php

namespace Mouseketeers\CookieConsent\Extensions;

use Mouseketeers\CookieConsent\Models\CookieSection;
use Mouseketeers\CookieConsent\Services\CookieConsentConfigCache;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\Forms\TextField;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\i18n\i18n;
use SilverStripe\Subsites\Model\Subsite;

class CookieConsentSiteConfigExtension extends Extension
{
    private static $db = [
        'CookieConsentTitle' => 'Varchar(255)',
        'CookieConsentContent' => 'HTMLText'
    ];

    private static $has_many = [
        'CookieSections' => CookieSection::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $cookieCategoriesGrid = GridField::create(
            'CookieSections',
            'Cookie categories',
            $this->owner->CookieSections(),
            GridFieldConfig_RecordEditor::create()
        );

        $cookieCategoriesGrid->getConfig()
            ->removeComponentsByType(GridFieldPaginator::class)
            ->removeComponentsByType(GridFieldPageCount::class)
            ->addComponent(new GridFieldOrderableRows('SortOrder'));

        $fields->addFieldsToTab('Root.CookieConsent', [
            TextField::create('CookieConsentTitle', $this->owner->fieldLabel('CookieConsentTitle')),
            HtmlEditorField::create('CookieConsentContent', $this->owner->fieldLabel('CookieConsentContent'))->setRows(5),
            $cookieCategoriesGrid
        ]);
    }
    public function requireDefaultRecords()
    {
        // parent::requireDefaultRecords();

        // Define the record-updating logic inside a reusable callback function
        $updateConfigs = function () {
            $originalLocale = i18n::get_locale();
            $configs = SiteConfig::get();

            foreach ($configs as $config) {
                $changed = false;

                // Switch the i18n locale to match the current SiteConfig language
                $configLocale = !empty($config->Language) ? $config->Language : $originalLocale;
                i18n::set_locale($configLocale);

                if (empty($config->CookieConsentTitle)) {
                    $config->CookieConsentTitle = _t('CookieConsent.CookieConsentTitle', 'This website uses cookies');
                    $changed = true;
                }

                if (empty($config->CookieConsentContent)) {
                    $config->CookieConsentContent = _t('CookieConsent.CookieConsentContent', '<p>We use cookies to personalise content, to provide social media features and to analyse our traffic. We also share information about your use of our site with our social media and analytics partners who may combine it with other information that you’ve provided to them or that they’ve collected from your use of their services. You consent to our cookies if you continue to use our website.</p>');
                    $changed = true;
                }

                if ($changed) {
                    $config->write();
                }
            }

            // Restore the original system locale context
            i18n::set_locale($originalLocale);
        };

        // Execute using the safest workflow depending on if Subsites module is installed
        if (class_exists(Subsite::class)) {
            Subsite::withDisabledSubsiteFilter($updateConfigs);
        } else {
            $updateConfigs();
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
