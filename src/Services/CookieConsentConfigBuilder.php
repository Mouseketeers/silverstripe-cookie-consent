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

            if (empty($linkedCategory)) {
                continue;
            }

            $includedCategories[$linkedCategory] = true;
        }

        $categories = [];

        foreach ($configCategories as $categoryId => $categoryConfig) {
            if (!isset($includedCategories[$categoryId])) {
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
        $cookieDescriptions = $this->buildCookieDescriptions($cookieCategories);

        $cookieTableHeaders = [
            'name' => _t('CookieConsent.CookieTableName', 'Name'),
            'domain' => _t('CookieConsent.CookieTableProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieTableDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieTableExpiration', 'Expiration')
        ];

        foreach ($cookieDescriptions as $categoryName => $cookies) {
            $title = isset($categoryTitles[$categoryName]) ? $categoryTitles[$categoryName] : '';
            $description = isset($categoryDescriptions[$categoryName]) ? $categoryDescriptions[$categoryName] : '';

            if (empty($title) || (empty($description) && empty($cookies))) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'description' => $description,
                'linkedCategory' => $categoryName,
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
            $ConsentCategory = $group->ConsentCategory;
            $titles[$ConsentCategory] = $group->Title;
        }

        return $titles;
    }

    protected function buildCookieDescriptions($cookieCategories)
    {
        $cookieDescriptions = [];

        foreach ($cookieCategories as $group) {
            $ConsentCategory = $group->ConsentCategory;

            if (!isset($cookieDescriptions[$ConsentCategory])) {
                $cookieDescriptions[$ConsentCategory] = [];
            }

            foreach ($group->CookieDescriptions() as $cookie) {
                $cookieDescriptions[$ConsentCategory][] = [
                    'name' => $cookie->Title,
                    'domain' => $cookie->Provider,
                    'description' => $cookie->Description,
                    'expiration' => $cookie->Expiration
                ];
            }
        }

        return $cookieDescriptions;
    }

    protected function buildCategoryDescriptions($cookieCategories)
    {
        $descriptions = [];

        foreach ($cookieCategories as $group) {
            $ConsentCategory = $group->ConsentCategory;
            $descriptions[$ConsentCategory] = empty($descriptions[$ConsentCategory])
                ? $group->Description
                : $descriptions[$ConsentCategory] . "\n\n" . $group->Description;
        }

        return $descriptions;
    }
}
