<?php


class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentTitle' => 'Varchar(255)',
        'CookieConsentContent' => 'HTMLText'
    ];

    private static $has_many = [
        'CookieSections' => 'CookieSection'
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
            ->removeComponentsByType('GridFieldPaginator')
            ->removeComponentsByType('GridFieldPageCount')
            ->addComponent(new GridFieldOrderableRows('SortOrder'));

        $fields->addFieldsToTab('Root.CookieConsent', [
            TextField::create('CookieConsentTitle', $this->owner->fieldLabel('CookieConsentTitle')),
            HtmlEditorField::create('CookieConsentContent', $this->owner->fieldLabel('CookieConsentContent'))->setRows(5),
            $cookieCategoriesGrid
        ]);
    }

    public function requireDefaultRecords()
    {
        if ($config = SiteConfig::current_site_config()) {
            if (empty($config->CookieConsentTitle)) {
                $config->CookieConsentTitle = _t('CookieConsent.CookieConsentTitle', 'This website uses cookies');
            }

            if (empty($config->CookieConsentContent)) {
                $config->CookieConsentContent = _t('CookieConsent.CookieConsentContent', '<p>We use cookies to personalise content, to provide social media features and to analyse our traffic. We also share information about your use of our site with our social media and analytics partners who may combine it with other information that you’ve provided to them or that they’ve collected from your use of their services. You consent to our cookies if you continue to use our website.</p>');
            }

            $config->write();
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
