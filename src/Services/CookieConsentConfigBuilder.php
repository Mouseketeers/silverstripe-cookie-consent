<?php

class CookieConsentConfigBuilder
{
    public function build()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getCacheKey();
        $cachedConfig = $cache->load($cacheKey);

        if ($cachedConfig !== false) {
            $decodedConfig = json_decode($cachedConfig, true);
            if (is_array($decodedConfig)) {
                return $decodedConfig;
            }
        }

        $currentLocale = i18n::get_locale();
        $languageCode = !empty($currentLocale)
            ? strtolower(substr($currentLocale, 0, 2))
            : 'en';

        $siteConfig = SiteConfig::current_site_config();

        $config = [
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'categories' => $this->buildCategories($siteConfig),
            'defaultLanguage' => $languageCode,
            'translations' => [
                $languageCode => $this->buildLanguageData($siteConfig)
            ]
        ];

        $cache->save(json_encode($config), $cacheKey);

        return $config;
    }
    
    protected function buildLanguageData($siteConfig)
    {
        $consentTitle = !empty($siteConfig->CookieConsentTitle)
            ? $siteConfig->CookieConsentTitle
            : _t('CookieConsent.CookieConsentTitle', 'We use cookies');

        $consentDescription = !empty($siteConfig->CookieConsentContent)
            ? $siteConfig->CookieConsentContent
            : _t('CookieConsent.CookieConsentContent', 'We use cookies to personalise content and ads, to provide social media features and to analyse our traffic.');

        return [
            'consentModal' => [
                'title' => $consentTitle,
                'description' => $consentDescription,
                'acceptAllBtn' => _t('CookieConsent.ButtonsAcceptAll', 'Accept all'),
                'acceptNecessaryBtn' => _t('CookieConsent.ButtonsRejectAll', 'Reject all'),
                'showPreferencesBtn' => _t('CookieConsent.ButtonsManagePreferences', 'Manage preferences')
            ],
            'preferencesModal' => [
                'title' => _t('CookieConsent.PreferencesModalTitle', 'Manage cookie preferences'),
                'acceptAllBtn' => _t('CookieConsent.ButtonsAcceptAll', 'Accept all'),
                'acceptNecessaryBtn' => _t('CookieConsent.ButtonsRejectAll', 'Reject all'),
                'savePreferencesBtn' => _t('CookieConsent.ButtonsSavePreferences', 'Save preferences'),
                'closeIconLabel' => _t('CookieConsent.PreferencesCloseIconLabel', 'Close'),
                'sections' => $this->buildCategorySections($siteConfig)
            ]
        ];
    }

    protected function buildCategories($siteConfig)
    {
        $configCategories = CookieConsent::getCategoriesConfig();
        if (!is_array($configCategories)) {
            return [];
        }

        return $configCategories;
    }

    protected function buildCategorySections($siteConfig)
    {
        $sections = [];
        $configCategories = CookieConsent::getCategoriesConfig();
        $cookieDescriptions = $this->buildCookieDescriptions($siteConfig);

        $cookieTableHeaders = [
            'name' => _t('CookieConsent.CookieTableName', 'Name'),
            'domain' => _t('CookieConsent.CookieTableVendor ', 'Vendor'),
            'description' => _t('CookieConsent.CookieTableDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieTableExpiration', 'Expiration')
        ];

        foreach ($configCategories as $categoryId => $categoryConfig) {
            $normalizedCategoryName = $this->normalizeCategoryKey($categoryId);
            if (empty($normalizedCategoryName)) {
                continue;
            }

            $cookies = isset($cookieDescriptions[$normalizedCategoryName])
                ? $cookieDescriptions[$normalizedCategoryName]
                : [];
            $title = $this->getCategoryTitle($categoryId);
            $description = $this->getCategoryDescription($categoryId);

            if (empty($cookies)) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'description' => $description,
                'linkedCategory' => $normalizedCategoryName,
                'cookieTable' => [
                    'headers' => $cookieTableHeaders,
                    'body' => $cookies
                ]
            ];
        }

        return $sections;
    }

    protected function getCategoryTitle($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
        $defaultLabel = ucwords(str_replace(['-', '_'], ' ', $categoryId));

        return _t($translationKey, $defaultLabel);
    }

    protected function getCategoryDescription($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s.Description', $categoryId);

        return _t($translationKey, '');
    }

    protected function normalizeCategoryKey($category)
    {
        if (!is_string($category)) {
            return null;
        }

        return strtolower(trim($category));
    }

    protected function buildCookieDescriptions($siteConfig)
    {
        $cookieDescriptions = [];
        $services = $siteConfig->CookieServices();
        $validCategories = [];

        foreach (CookieConsent::getCategoriesConfig() as $categoryId => $categoryConfig) {
            $validCategories[$this->normalizeCategoryKey($categoryId)] = true;
        }

        foreach ($services as $service) {
            foreach ($validCategories as $categoryKey => $isValid) {
                foreach ($service->getCookieDescriptionsForCategory($categoryKey) as $cookie) {
                    $targetCategory = $this->normalizeCategoryKey($cookie->Category);

                    if (empty($targetCategory) || !isset($validCategories[$targetCategory])) {
                        continue;
                    }

                    if (!isset($cookieDescriptions[$targetCategory])) {
                        $cookieDescriptions[$targetCategory] = [];
                    }

                    $cookieDescriptions[$targetCategory][] = [
                        'name' => $cookie->Title,
                        'domain' => $cookie->Vendor,
                        'description' => $cookie->Description,
                        'expiration' => $cookie->Expiration
                    ];
                }
            }
        }

        return $cookieDescriptions;
    }
}
