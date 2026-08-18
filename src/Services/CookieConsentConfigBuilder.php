<?php

class CookieConsentConfigBuilder
{
    public function buildConsentConfig()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getJsCacheKey();
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

        $categories = CookieConsent::getCategoryLabelsConfig();

        $config = [
            'defaultLanguage' => $languageCode,
            'categories' => $categories,
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig, $categories)
            ],
            'iframeManager' => [
                'services' => $this->buildExternalMediaServices($siteConfig, $languageCode)
            ],
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled()            
        ];
        $cache->save(serialize($config), $cacheKey);
        return $config;
    }
    public function buildDeclarationData()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getDeclarationCacheKey();
        $cachedDeclaration = $cache->load($cacheKey);

        if ($cachedDeclaration !== false && is_string($cachedDeclaration)) {
            $decodedDeclaration = @unserialize($cachedDeclaration);
            if (is_array($decodedDeclaration)) {
                return $decodedDeclaration;
            }
        }

        $siteConfig = SiteConfig::current_site_config();
        $categories = CookieConsent::getCategoryLabelsConfig();

        $declaration = [
            'categories' => $this->buildDeclarationCategories($siteConfig, $categories)
        ];

        $cache->save(serialize($declaration), $cacheKey);

        return $declaration;
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

    protected function buildDeclarationCategories($siteConfig, $categories)
    {

        $cookieItems = $this->buildCookieTranslations($siteConfig);

        foreach ($categories as $categoryKey => $categoryData) {

            $cookies = isset($cookieItems[$categoryKey]) ? $cookieItems[$categoryKey] : [];
            if (empty($cookies) && empty($categoryData['services'])) {
                continue;
            }

            $categories[] = [
                'title' => $this->getCategoryTitle($categoryKey),
                'content' => $this->getCategoryDescription($categoryKey),
                'cookies' => $cookies
            ];
        }
        debug::dump($categories);
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
    public function buildExternalMediaServices($siteConfig, $languageCode)
    {

        $externalMediaServices = [];

        $loadBtnTranslation = _t('CookieConsent.IframeManager.LoadBtn', 'Load Once');
        $loadAllBtnTranslation = _t('CookieConsent.IframeManager.LoadAllBtn', 'Don\'t ask again');

        $selectedExternalMedia = explode(',', $siteConfig->ExternalMedia);

        foreach ($selectedExternalMedia as $externalMediaKey) {
            $externalMediaServices[$externalMediaKey]['languages'][$languageCode] = [
                'loadBtn' => $loadBtnTranslation,
                'loadAllBtn' => $loadAllBtnTranslation,
                'notice' => _t('CookieConsent.IframeManager.Notice_' . $externalMediaKey, '')
            ];
        }
        return $externalMediaServices;
    }    
}
