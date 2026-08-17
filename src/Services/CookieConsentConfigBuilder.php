<?php

class CookieConsentConfigBuilder
{
    public function build()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getCacheKey();
        $cachedConfig = $cache->load($cacheKey);

        if ($cachedConfig !== false) {
            if (is_string($cachedConfig)) {
                $decodedConfig = @unserialize($cachedConfig);
                if (is_array($decodedConfig)) {
                    return $decodedConfig;
                }
            }
        }

        $currentLocale = i18n::get_locale();
        $languageCode = !empty($currentLocale)
            ? strtolower(substr($currentLocale, 0, 2))
            : 'en';

        $siteConfig = SiteConfig::current_site_config();

        // debug::dump($this->buildCategories($siteConfig));die();

        // debug::dump($this->buildCategoryTranslations($siteConfig));die();

        $config = [
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'categories' => $this->buildCategories($siteConfig),
            'defaultLanguage' => $languageCode,
            'declaration' => [
                'categories' => $this->buildDeclarationCategories($siteConfig)
            ],
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig)
            ]
        ];

        $cache->save(serialize($config), $cacheKey);

        return $config;
    }

    public function buildDeclarationData()
    {
        $config = $this->build();
        if (!isset($config['declaration']) || !is_array($config['declaration'])) {
            return ['categories' => []];
        }

        return $config['declaration'];
    }
    
    protected function buildTranslations($siteConfig)
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
                'sections' => $this->buildCategoryTranslations($siteConfig)
            ]
        ];
    }

    protected function buildCategories()
    {
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        
        if (!is_array($configCategories)) {
            return [];
        }

        return $configCategories;
    }

    protected function buildCategoryTranslations($siteConfig)
    {
        $sections = [];
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        $cookieItems = $this->buildCookieTranslations($siteConfig);

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

            $cookies = isset($cookieItems[$normalizedCategoryName])
                ? $cookieItems[$normalizedCategoryName]
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

    protected function buildDeclarationCategories($siteConfig)
    {
        $categories = [];
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        $cookieItems = $this->buildCookieTranslations($siteConfig);

        foreach ($configCategories as $categoryId => $categoryConfig) {
            $normalizedCategoryName = $this->normalizeCategoryKey($categoryId);
            if (empty($normalizedCategoryName)) {
                continue;
            }

            $cookies = isset($cookieItems[$normalizedCategoryName])
                ? $cookieItems[$normalizedCategoryName]
                : [];
            if (empty($cookies)) {
                continue;
            }

            $categories[] = [
                'title' => $this->getCategoryTitle($categoryId),
                'content' => $this->getCategoryDescription($categoryId),
                'cookies' => $cookies
            ];
        }

        return $categories;
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

    protected function buildCookieTranslations($siteConfig)
    {
        $cookieItems = [];
        $services = $siteConfig->CookieServices();
        $customCookies = $siteConfig->CustomCookies();
        $validCategories = [];

        foreach (CookieConsent::getCategoryLabelsConfig() as $categoryId => $categoryConfig) {
            $validCategories[$this->normalizeCategoryKey($categoryId)] = true;
        }

        foreach ($services as $service) {
            foreach ($validCategories as $categoryKey => $isValid) {
                foreach ($service->getCookieTranslationsForCategory($categoryKey) as $cookie) {
                    $targetCategory = $this->normalizeCategoryKey($cookie->Category);

                    if (empty($targetCategory) || !isset($validCategories[$targetCategory])) {
                        continue;
                    }

                    if (!isset($cookieItems[$targetCategory])) {
                        $cookieItems[$targetCategory] = [];
                    }

                    $cookieItems[$targetCategory][] = [
                        'id' => (int) $cookie->ID,
                        'name' => $cookie->Name,
                        'provider' => $cookie->Service,
                        'service' => $cookie->Service,
                        'vendor' => $cookie->Vendor,
                        'description' => $cookie->Description,
                        'domain' => $cookie->Domain,
                        'privacyPolicyURL' => $cookie->PrivacyPolicyURL,
                        'expiration' => $cookie->Expiration

                    ];
                }
            }
        }

        foreach ($customCookies as $customCookie) {
            $targetCategory = $this->normalizeCategoryKey($customCookie->Category);
            if (empty($targetCategory) || !isset($validCategories[$targetCategory])) {
                continue;
            }

            if (!isset($cookieItems[$targetCategory])) {
                $cookieItems[$targetCategory] = [];
            }

            $cookieItems[$targetCategory][] = [
                'id' => (int) $customCookie->ID,
                'name' => $customCookie->Name,
                'provider' => $customCookie->Service,
                'service' => $customCookie->Service,
                'vendor' => $customCookie->Vendor,
                'description' => $customCookie->Description,
                'domain' => $customCookie->Domain,
                'privacyPolicyURL' => $customCookie->PrivacyPolicyURL,
                'expiration' => $customCookie->Expiration
            ];
        }

        return $cookieItems;
    }
}
