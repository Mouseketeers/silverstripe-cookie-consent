<?php

namespace Mouseketeers\CookieConsent\Models;

use Mouseketeers\CookieConsent\CookieConsent;
use Mouseketeers\CookieConsent\Services\CookieConsentConfigCache;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\TextAreaField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

class CookieSection extends DataObject
{
    private static $table_name = 'CookieConsentSection';

    private static $singular_name = 'Cookie Section';

    private static $plural_name = 'Cookie Sections';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Varchar(255)',
        'SortOrder' => 'Int',
        'ConsentCategory' => 'Varchar(100)'
    ];

    private static $has_one = [
        'SiteConfig' => SiteConfig::class
    ];    

    private static $many_many = [
        'CookieDescriptions' => CookieDescription::class
    ];

    private static $summary_fields = [
        'Title'
    ];

    private static $default_sort = 'SortOrder';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('SortOrder');
        $fields->removeByName('SiteConfigID');
        $fields->replaceField('Description', TextAreaField::create('Description', $this->fieldLabel('Description')));

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('ConsentCategory', 'Consent Category', $this->getUnusedConsentCategoriesMap())
        ]);

        $cookiesGrid = $fields->dataFieldByName('CookieDescriptions');
        if ($cookiesGrid instanceof GridField) {
            $linkExisting = $cookiesGrid->getConfig()->getComponentByType(GridFieldAddExistingAutocompleter::class);
            if ($linkExisting instanceof GridFieldAddExistingAutocompleter) {
                $linkExisting->setSearchFields(['Title']);
                $linkExisting->setResultsFormat('$ListTitle');
            }
        }

        return $fields;
    }

    public function getUnusedConsentCategoriesMap()
    {
        $allCategories = CookieConsent::getCategoryOptionMap();
        if (empty($allCategories)) {
            return [];
        }

        $sections = CookieSection::get();

        if ((int) $this->SiteConfigID > 0) {
            $sections = $sections->filter('SiteConfigID', (int) $this->SiteConfigID);
        }

        if ((int) $this->ID > 0) {
            $sections = $sections->exclude('ID', (int) $this->ID);
        }

        $usedCategories = [];
        foreach ($sections as $section) {
            if (!empty($section->ConsentCategory)) {
                $usedCategories[$section->ConsentCategory] = true;
            }
        }

        $unusedCategories = [];
        foreach ($allCategories as $categoryKey => $categoryLabel) {
            if (!isset($usedCategories[$categoryKey]) || $categoryKey === $this->ConsentCategory) {
                $unusedCategories[$categoryKey] = $categoryLabel;
            }
        }

        return $allCategories;
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

    public function canCreate($member = null, $context = [])
    {
        return !empty($this->getUnusedConsentCategoriesMap());
    }

}
