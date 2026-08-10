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
        /*
         * Return configured categories from config.yml, but only include
         * categories that are actually added as sections in CMS.
         * This avoids collecting consent for categories not used on a site.
         */

        $configCategories = CookieConsent::getCategoriesConfig();
        if (!is_array($configCategories)) {
            $configCategories = [];
        }

        $includedCategories = [];

        $sections = $this->buildCategorySections($siteConfig);
        foreach ($sections as $section) {
            $linkedCategory = isset($section['linkedCategory']) ? $section['linkedCategory'] : null;
            $normalizedLinkedCategory = $this->normalizeCategoryKey($linkedCategory);

            if (empty($normalizedLinkedCategory)) {
                continue;
            }

            $includedCategories[$normalizedLinkedCategory] = true;
        }

        $categories = [];

        foreach ($configCategories as $categoryId => $categoryConfig) {
            $normalizedCategoryId = $this->normalizeCategoryKey($categoryId);
            if (!isset($includedCategories[$normalizedCategoryId])) {
                continue;
            }

            $categories[$categoryId] = $categoryConfig;
        }

        return $categories;
    }

    protected function buildCategorySections($siteConfig)
    {
        $sections = [];
        $cookieCategories = $siteConfig->CookieSections();
        $categoryTitles = $this->buildCategoryTitles($cookieCategories);
        $categoryDescriptions = $this->buildCategoryDescriptions($cookieCategories);
        $cookieDescriptions = $this->buildCookieDescriptions($cookieCategories, $siteConfig);

        $cookieTableHeaders = [
            'name' => _t('CookieConsent.CookieTableName', 'Name'),
            'domain' => _t('CookieConsent.CookieTableVendor ', 'Vendor'),
            'description' => _t('CookieConsent.CookieTableDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieTableExpiration', 'Expiration')
        ];

        foreach ($cookieCategories as $cookieCategory) {
            $normalizedCategoryName = $this->normalizeCategoryKey($cookieCategory->ConsentCategory);
            $cookies = isset($cookieDescriptions[$normalizedCategoryName])
                ? $cookieDescriptions[$normalizedCategoryName]
                : [];
            $title = isset($categoryTitles[$normalizedCategoryName]) ? $categoryTitles[$normalizedCategoryName] : '';
            $description = isset($categoryDescriptions[$normalizedCategoryName]) ? $categoryDescriptions[$normalizedCategoryName] : '';

            if (empty($title) || (empty($description) && empty($cookies))) {
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

    protected function buildCategoryTitles($cookieCategories)
    {
        $titles = [];

        foreach ($cookieCategories as $group) {
            $ConsentCategory = $this->normalizeCategoryKey($group->ConsentCategory);
            $titles[$ConsentCategory] = $group->Title;
        }

        return $titles;
    }

    protected function normalizeCategoryKey($category)
    {
        if (!is_string($category)) {
            return null;
        }

        return strtolower(trim($category));
    }

    protected function buildCookieDescriptions($cookieCategories, $siteConfig)
    {
        $cookieDescriptions = [];
        $services = $siteConfig->CookieServices();
        $validCategories = [];

        foreach ($cookieCategories as $cookieCategory) {
            $validCategories[$this->normalizeCategoryKey($cookieCategory->ConsentCategory)] = true;
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

    protected function buildCategoryDescriptions($cookieCategories)
    {
        $descriptions = [];

        foreach ($cookieCategories as $group) {
            $ConsentCategory = $this->normalizeCategoryKey($group->ConsentCategory);
            $descriptions[$ConsentCategory] = empty($descriptions[$ConsentCategory])
                ? $group->Description
                : $descriptions[$ConsentCategory] . "\n\n" . $group->Description;
        }

        return $descriptions;
    }
}
