<?php

class CookieDescription extends DataObject
{

    private static $singular_name = 'Cookie';

    private static $plural_name = 'Cookies';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Category' => 'Varchar(100)',
        'Service' => 'Varchar(255)',
        'Vendor' => 'Varchar(255)',
        'Description' => 'Text',
        'Domain' => 'Varchar(255)',
        'Expiration' => 'Varchar(255)',
        'PrivacyPolicyURL' => 'Varchar(255)',
        'Wildcard' => 'Boolean',
        'Locale' => 'Varchar(5)',
        'CookieRegistryID' => 'Varchar(255)'
    ];

    private static $has_one = [
        'CookieService' => 'CookieService'
    ];

    private static $summary_fields = [
        'Name',
        'Vendor',
        'Description',
        'Category',
        'Expiration',
        'LocaleName' => 'Language'
    ];

    private static $default_sort = 'Name ASC';

    private static $field_labels = [
        'Locale' => 'Language'
    ];

    public function getListTitle()
    {
        return $this->Name . ' ' . $this->getLocaleName();
    }

    public function getLocaleName()
    {
        if (!$this->Locale) {
            return '';
        }

        $locales = i18n::get_common_locales();
        if (isset($locales[$this->Locale])) {
            return $locales[$this->Locale];
        }

        return $this->Locale;
    }

    public function populateDefaults()
    {
        parent::populateDefaults();
        $subsite = CookieConsent::getCurrentSubsite(); 
        if ($subsite) {
            $this->Locale = $subsite->Language;
        }
        else {
            $this->Locale = i18n::get_locale();
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['CookieRegistryID', 'CookieServiceID']);

        $categoryOptions = CookieConsent::getCategoryTranslationsMap();
        if ($this->Category && !isset($categoryOptions[$this->Category])) {
            $categoryOptions[$this->Category] = $this->Category;
        }

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Name', $this->fieldLabel('Name')),
            TextField::create('Vendor', $this->fieldLabel('Vendor')),
            TextAreaField::create('Description', $this->fieldLabel('Description')),
            DropdownField::create('Category', $this->fieldLabel('Category'), $categoryOptions)
                ->setEmptyString(_t('CookieConsent.SelectCategory', 'Select...'))
                ->setAttribute('required', 'required'),
            TextField::create('Expiration', $this->fieldLabel('Expiration')),
            DropdownField::create('Locale', $this->fieldLabel('Locale'), i18n::get_common_locales())
                ->setEmptyString(_t('CookieConsent.SelectLanguage', 'Select...'))
        ]);

        return $fields;
    }

    public function getCMSValidator()
    {
        return RequiredFields::create(['Category']);
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
