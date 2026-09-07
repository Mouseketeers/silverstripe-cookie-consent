<?php

class CookieDescription extends DataObject
{

    private static $singular_name = 'Custom Cookie';

    private static $plural_name = 'Custom Cookies';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Category' => 'Varchar(100)',
        'Provider' => 'Varchar(255)',
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
        'DisplayName' => 'Name',
        'DisplayCategoryName' => 'Category',
        'Provider',
        'Expiration'
    ];

    private static $default_sort = 'Name ASC';

    public function getDisplayName()
    {
        return $this->Wildcard ? $this->getField('Name') . '*' : $this->getField('Name');
    }

    public function getDisplayCategoryName()
    {
        $categoryTranslationsMap = CookieConsent::getCategoryTranslationsMap();
        return $categoryTranslationsMap[$this->Category] ?? $this->Category;
    }    

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['SiteConfigID']);

        $fields->replaceField('Category', DropdownField::create('Category', 'Category', CookieConsent::getCategoryTranslationsMap()));

        $fields->replaceField('Wildcard', CheckboxField::create('Wildcard', 'Wildcard (cookie name is followed by an unique ID, e.g. cookie_name_123456)'));

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
