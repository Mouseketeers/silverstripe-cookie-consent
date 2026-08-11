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
            $customAutocompleter->setSearchFields(['Name']);
            $customAutocompleter->setResultsFormat('$Name');
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

    public function requireDefaultRecords()
    {
        if ($config = SiteConfig::current_site_config()) {
            if (empty($config->CookieConsentTitle)) {
                $config->CookieConsentTitle = _t('CookieConsent.CookieConsentTitle');
            }

            if (empty($config->CookieConsentContent)) {
                $config->CookieConsentContent = _t('CookieConsent.CookieConsentContent');
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
