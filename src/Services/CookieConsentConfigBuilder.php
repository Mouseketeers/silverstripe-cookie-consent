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
        $consentTitle = !empty($siteConfig->CookieConsentModalTitle)
            ? $siteConfig->CookieConsentModalTitle
            : _t('CookieConsent.CookieConsentModalTitle');

        $consentDescription = !empty($siteConfig->CookieConsentModalContent)
            ? $siteConfig->CookieConsentModalContent
            : _t('CookieConsent.CookieConsentModalContent');

        return [
            'consentModal' => [
                'title' => $consentTitle,
                'description' => $consentDescription,
                'acceptAllBtn' => _t('CookieConsent.ButtonsAcceptAll', 'Accept all'),
                'acceptNecessaryBtn' => _t('CookieConsent.ButtonsRejectAll', 'Reject all'),
                'showPreferencesBtn' => _t('CookieConsent.ButtonsManagePreferences', 'Manage preferences')
            ],
            'preferencesModal' => [
                'title' => _t('CookieConsent.PreferencesCookieConsentModalTitle', 'Manage cookie preferences'),
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
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        if (!is_array($configCategories)) {
            return [];
        }

        return $configCategories;
    }

    protected function buildCategorySections($siteConfig)
    {
        $sections = [];
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        $cookieDescriptions = $this->buildCookieDescriptions($siteConfig);

        $CookieHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
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
                    'headers' => $CookieHeaders,
                    'body' => $cookies
                ]
            ];
        }

        return $sections;
    }

    protected function getCategoryTitle($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
        return _t($translationKey, '');
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

        foreach (CookieConsent::getCategoryLabelsConfig() as $categoryId => $categoryConfig) {
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
                        'name' => $cookie->Name,
                        'provider' => $cookie->Service,
                        'description' => $cookie->Description,
                        'expiration' => $cookie->Expiration
                    ];
                }
            }
        }

        return $cookieDescriptions;
    }
}
