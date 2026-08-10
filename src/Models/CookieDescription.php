<?php

class CookieDescription extends DataObject
{

    private static $singular_name = 'Cookie';

    private static $plural_name = 'Cookies';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Vendor' => 'Varchar(255)',
        'Service' => 'Varchar(255)',
        'Category' => 'Varchar(100)',
        'Description' => 'Text',
        'Domain' => 'Varchar(255)',
        'Expiration' => 'Varchar(255)',
        'PrivacyPolicyURL' => 'Varchar(255)',
        'Wildcard' => 'Boolean',
        'Locale' => 'Varchar(5)'
    ];

    private static $has_one = [
        'CookieService' => 'CookieService'
    ];

    private static $belongs_many_many = [
        'CookieSections' => 'CookieSection'
    ];

    private static $summary_fields = [
        'Title',
        'Vendor',
        'Description',
        'Category',
        'Expiration',
        'LocaleName' => 'Language'
    ];

    private static $field_labels = [
        'Locale' => 'Language'
    ];

    public function getListTitle()
    {
        return $this->Title . ' ' . $this->getLocaleName();
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

        $fields->removeByName('CookieSections');

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', $this->fieldLabel('Title')),
            TextField::create('Vendor', $this->fieldLabel('Vendor')),
            TextAreaField::create('Description', $this->fieldLabel('Description')),
            TextField::create('Expiration', $this->fieldLabel('Expiration')),
            DropdownField::create('Locale', $this->fieldLabel('Locale'), i18n::get_common_locales())
                ->setEmptyString('Select...')
        ]);

        return $fields;
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
