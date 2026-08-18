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

        $categories = $this->buildCategories($siteConfig);

        // todo: seperate js config and declaration config so that declaration data isn't sent to the browser unnecessarily
        $config = [
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'categories' => $categories,
            'defaultLanguage' => $languageCode,
            'declaration' => [
                'categories' => $this->buildDeclarationCategories($siteConfig)
            ],
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig, $categories)
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
    
    protected function buildTranslations($siteConfig, $categories)
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
                'sections' => $this->buildCategoryTranslations($siteConfig, $categories)
            ]
        ];
    }

    protected function buildCategories($siteConfig)
    {
        $configCategories = CookieConsent::getCategoryLabelsConfig();
        
        if (!is_array($configCategories)) {
            return [];
        }

        $selectedExternalMedia = array_values(array_filter(array_map('trim', explode(',', (string) $siteConfig->ExternalMedia))));
        $externalMediaConfig = CookieConsent::getExternalMediaConfig();

        if (!is_array($externalMediaConfig)) {
            return $configCategories;
        }

        foreach ($selectedExternalMedia as $externalMedia) {
            if (!isset($externalMediaConfig[$externalMedia])) {
                continue;
            }

            $configCategories['embeds']['services'][$externalMedia] = $externalMediaConfig[$externalMedia];
        }
        return $configCategories;
    }

    protected function buildCategoryTranslations($siteConfig, $categories)
    {
        $sections = [];
        $cookieItems = $this->buildCookieTranslations($siteConfig);

        $CookieHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
        ];
                    
        foreach ($categories as $categoryKey => $categoryData) {

            $cookies = isset($cookieItems[$categoryKey]) ? $cookieItems[$categoryKey] : [];
            $title = $this->getCategoryTitle($categoryKey);
            $description = $this->getCategoryDescription($categoryKey);

            if (empty($cookies) && empty($categoryData['services'])) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'description' => $description,
                'linkedCategory' => $categoryKey,
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

            $cookies = isset($cookieItems[$categoryId])
                ? $cookieItems[$categoryId]
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

    protected function buildCookieTranslations($siteConfig)
    {
        $cookieItems = [];
        $services = $siteConfig->CookieServices();
        $customCookies = $siteConfig->CustomCookies();
        $categories = CookieConsent::getCategoryLabelsConfig();

        foreach ($services as $service) {
            foreach (array_keys($categories) as $categoryKey) {
                foreach ($service->getCookieTranslationsForCategory($categoryKey) as $cookie) {

                    if (!isset($cookieItems[$categoryKey])) {
                        $cookieItems[$categoryKey] = [];
                    }

                    $cookieItems[$categoryKey][] = [
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
            $cookieItems[$customCookie->Category][] = [
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
