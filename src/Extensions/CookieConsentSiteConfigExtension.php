<?php


class CookieConsentSiteConfigExtension extends DataExtension
{
    private static $db = [
        'CookieConsentModalTitle' => 'Varchar(255)',
        'CookieConsentModalContent' => 'HTMLText'
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
            TextField::create('CookieConsentModalTitle', $this->owner->fieldLabel('CookieConsentModalTitle')),
            HtmlEditorField::create('CookieConsentModalContent', $this->owner->fieldLabel('CookieConsentModalContent'))->setRows(5),
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
            if (empty($config->CookieConsentModalTitle)) {
                $config->CookieConsentModalTitle = _t('CookieConsent.CookieConsentModalTitle');
            }

            if (empty($config->CookieConsentModalContent)) {
                $config->CookieConsentModalContent = _t('CookieConsent.CookieConsentModalContent');
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
