<?php


class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentTitle' => 'Varchar(255)',
        'CookieConsentContent' => 'HTMLText'
    ];

    private static $many_many = [
        'CookieServices' => 'CookieService'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $cookieServicesGrid = GridField::create(
            'CookieServices',
            'Services using Cookies',
            $this->owner->CookieServices(),
            GridFieldConfig_RelationEditor::create()
        );

        $cookieServicesGridConfig = $cookieServicesGrid->getConfig();
        $linkExistingServices = $cookieServicesGridConfig->getComponentByType('GridFieldAddExistingAutocompleter');
        if ($linkExistingServices instanceof GridFieldAddExistingAutocompleter) {
            $serviceOptions = $this->getAvailableServiceOptions();
            $cookieServicesGridConfig->removeComponent($linkExistingServices);

            $customAutocompleter = new CookieServiceGridFieldAddExistingAutocompleter('buttons-before-right', $serviceOptions);
            $customAutocompleter->setSearchFields(['Title']);
            $customAutocompleter->setResultsFormat('$Title');
            $cookieServicesGridConfig->addComponent($customAutocompleter);
        }

        $fields->addFieldsToTab('Root.CookieConsent', [
            TextField::create('CookieConsentTitle', $this->owner->fieldLabel('CookieConsentTitle')),
            HtmlEditorField::create('CookieConsentContent', $this->owner->fieldLabel('CookieConsentContent'))->setRows(5),
            $cookieServicesGrid
        ]);
    }

    protected function getAvailableServiceOptions()
    {
        $jsonPath = CookieConsent::resolveCookieRegistryPath();
        if ($jsonPath === null || !file_exists($jsonPath)) {
            return [];
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $names = array_keys($data);
        sort($names);

        return array_combine($names, $names);
    }

    // public function requireDefaultRecords()
    // {
    //     if ($config = SiteConfig::current_site_config()) {
    //         if (empty($config->CookieConsentTitle)) {
    //             $config->CookieConsentTitle = _t('CookieConsent.CookieConsentTitle', 'This website uses cookies');
    //         }

    //         if (empty($config->CookieConsentContent)) {
    //             $config->CookieConsentContent = _t('CookieConsent.CookieConsentContent', '<p>We use cookies to personalise content, to provide social media features and to analyse our traffic. We also share information about your use of our site with our social media and analytics partners who may combine it with other information that you’ve provided to them or that they’ve collected from your use of their services. You consent to our cookies if you continue to use our website.</p>');
    //         }

    //         $config->write();
    //     }
    // }

    public function onAfterWrite()
    {
        CookieConsentConfigCache::clear();
    }

    public function onAfterDelete()
    {
        CookieConsentConfigCache::clear();
    }
}
