<?php

class CookieDescription extends DataObject
{

    private static $singular_name = 'Custom Cookie';

    private static $plural_name = 'Custom Cookies';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Category' => 'Varchar(100)',
        'Service' => 'Varchar(255)',
        'Vendor' => 'Varchar(255)',
        'Description' => 'Text',
        'Domain' => 'Varchar(255)',
        'Expiration' => 'Varchar(255)',
        'PrivacyPolicyURL' => 'Varchar(255)',
        'Wildcard' => 'Boolean'
    ];

    private static $has_one = [
        'SiteConfig' => 'SiteConfig'
    ];

    private static $summary_fields = [
        'Name',
        'Category',
        'Service',
        'Vendor',
        'Expiration'
    ];

    private static $default_sort = 'Name ASC';

    public function getName()
    {
        return $this->Wildcard ? $this->getField('Name') . '*' : $this->getField('Name');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['SiteConfigID']);

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
            TextField::create('Expiration', $this->fieldLabel('Expiration'))
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
