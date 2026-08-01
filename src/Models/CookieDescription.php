<?php

namespace Mouseketeers\CookieConsent\Models;

use Mouseketeers\CookieConsent\CookieConsent;
use Mouseketeers\CookieConsent\Services\CookieConsentConfigCache;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextAreaField;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;

class CookieDescription extends DataObject
{
    private static $table_name = 'CookieConsentDescription';


    private static $singular_name = 'Cookie Description';

    private static $plural_name = 'Cookie Descriptions';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Provider' => 'Varchar(255)',
        'Description' => 'Varchar(255)',
        'Expiration' => 'Varchar(255)',
        'Locale' => 'Varchar(5)'
    ];

    private static $belongs_many_many = [
        'CookieSections' => CookieSection::class
    ];

    private static $summary_fields = [
        'Title',
        'Provider',
        'Description',
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

        $locales = $this->getLocaleOptions();
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
        } else {
            $this->Locale = i18n::get_locale();
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('CookieSections');

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', $this->fieldLabel('Title')),
            TextField::create('Provider', $this->fieldLabel('Provider')),
            TextAreaField::create('Description', $this->fieldLabel('Description')),
            TextField::create('Expiration', $this->fieldLabel('Expiration')),
            DropdownField::create('Locale', $this->fieldLabel('Locale'), $this->getLocaleOptions())
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

    protected function getLocaleOptions(): array
    {
        $locales = i18n::getSources()->getKnownLocales();
        if (!empty($locales)) {
            return $locales;
        }

        return i18n::getData()->getLocales();
    }
}
