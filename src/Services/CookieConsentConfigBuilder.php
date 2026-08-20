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

        $cookieItems = $this->buildCookies($siteConfig);
        $categories = $this->buildCategories($cookieItems);

        $isExternalMediaManagementEnabled = CookieConsent::isExternalMediaManagementEnabled();

        $config = [
            'defaultLanguage' => $languageCode,
            'categories' => $categories,
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig, $categories, $cookieItems)
            ],
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'isExternalMediaManagementEnabled' => $isExternalMediaManagementEnabled,
        ];
        if($isExternalMediaManagementEnabled) {
            $config['externalMediaCategory'] = CookieConsent::getExternalMediaCategory();
            $config['externalMediaServices'] = [
                'services' => $this->buildExternalMediaServices($languageCode)
            ];
        }
        $cache->save(serialize($config), $cacheKey);
        return $config;
    }
    public function buildCategories($cookieItems)
    {
        $categoryConfig = CookieConsent::getCategoryConfig();
        $externalMediaCategory = CookieConsent::getExternalMediaCategory();
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();
        $externalMediaConfig = CookieConsent::getExternalMediaConfig();

        $externalMediaCategory = CookieConsent::getExternalMediaCategory();
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();     

        $categoryServices = $categoryConfig['services'] ?? [];

        if (!empty($selectedExternalMedia) && !empty($externalMediaConfig)) {
            $categoryServices = array_intersect_key(
                $externalMediaConfig,
                array_flip($selectedExternalMedia)
            );
        }

        $categories = [];
        foreach ($categoryConfig as $categoryKey => $categoryData) {

            if($categoryKey === $externalMediaCategory && empty($selectedExternalMedia)) {
                continue;
            }        
            $cookies = isset($cookieItems[$categoryKey]) ? $cookieItems[$categoryKey] : [];
            if ($categoryKey !== $externalMediaCategory && empty($cookies)) {
                continue;
            }             
            $categories[$categoryKey] = $categoryData;
            if ($categoryKey !== $externalMediaCategory) {
                continue;
            }

            $categories[$categoryKey]['services'] = $categoryServices;
        }
        return $categories;
    }
    public function buildDeclarationData()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getDeclarationCacheKey();
        $cachedDeclaration = $cache->load($cacheKey);

        if ($cachedDeclaration !== false && is_string($cachedDeclaration)) {
            $decodedDeclaration = @unserialize($cachedDeclaration);
            if (is_array($decodedDeclaration)) {
                return $this->buildTemplateDeclarationData($decodedDeclaration);
            }
        }

        $siteConfig = SiteConfig::current_site_config();
        $categories = CookieConsent::getCategoryConfig();

        $declaration = [
            'categories' => $this->buildDeclarationCategories($siteConfig, $categories)
        ];

        $cache->save(serialize($declaration), $cacheKey);

        return $this->buildTemplateDeclarationData($declaration);
    }

    protected function buildTemplateDeclarationData(array $declaration)
    {
        $categories = new ArrayList();
        $declarationCategories = isset($declaration['categories']) && is_array($declaration['categories'])
            ? $declaration['categories']
            : [];

        foreach ($declarationCategories as $categoryData) {
            if (!is_array($categoryData)) {
                continue;
            }

            $cookieDescriptions = new ArrayList();
            $cookies = isset($categoryData['CookieDescriptions']) && is_array($categoryData['CookieDescriptions'])
                ? $categoryData['CookieDescriptions']
                : [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $cookieDescriptions->push(ArrayData::create($cookieData));
            }

            if (!$cookieDescriptions->exists()) {
                continue;
            }

            $categories->push(ArrayData::create([
                'Title' => isset($categoryData['Title']) ? $categoryData['Title'] : '',
                'Content' => isset($categoryData['Content']) ? $categoryData['Content'] : '',
                'CookieDescriptions' => $cookieDescriptions
            ]));
        }

        return [
            'categories' => $categories
        ];
    }

    protected function buildTranslations($siteConfig, $categories, $cookieItems)
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
                'sections' => $this->buildSectionTranslations($categories, $cookieItems)
            ]
        ];
    }

    protected function buildSectionTranslations($categories, $cookieItems)
    {
        $sections = [];

        $CookieHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
        ];
        
        foreach ($categories as $categoryKey => $categoryData) {

            $title = $this->getSectionTitle($categoryKey);
            $description = $this->getSectionDescription($categoryKey);
            $cookies = isset($cookieItems[$categoryKey]) ? $cookieItems[$categoryKey] : [];

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
        $declarationCategories = [];
        $cookieItems = $this->buildCookies($siteConfig);

        foreach ($categories as $categoryKey => $categoryData) {
            $cookies = isset($cookieItems[$categoryKey]) ? $cookieItems[$categoryKey] : [];

            if (empty($cookies) && empty($categoryData['services'])) {
                continue;
            }

            usort($cookies, function ($left, $right) {
                return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
            });

            $cookieDescriptions = [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $cookieDescriptions[] = [
                    'ID' => (int) ($cookieData['id'] ?? 0),
                    'Name' => $cookieData['name'] ?? '',
                    'Vendor' => $cookieData['vendor'] ?? '',
                    'Service' => $cookieData['service'] ?? '',
                    'Domain' => $cookieData['domain'] ?? '',
                    'PrivacyPolicyURL' => $cookieData['privacyPolicyURL'] ?? '',
                    'Description' => $cookieData['description'] ?? '',
                    'Expiration' => $cookieData['expiration'] ?? ''
                ];
            }

            if (empty($cookieDescriptions)) {
                continue;
            }

            $declarationCategories[] = [
                'Title' => $this->getSectionTitle($categoryKey),
                'Content' => $this->getSectionDescription($categoryKey),
                'CookieDescriptions' => $cookieDescriptions
            ];
        }

        return $declarationCategories;
    }

    protected function getSectionTitle($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
        return _t($translationKey, '');
    }

    protected function getSectionDescription($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s.Description', $categoryId);

        return _t($translationKey, '');
    }

    protected function buildCookies($siteConfig)
    {
        $cookieItems = [];
        $services = $siteConfig->CookieServices();
        $customCookies = $siteConfig->CustomCookies();
        $categories = CookieConsent::getCategoryConfig();

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
    public function buildExternalMediaServices($languageCode)
    {

        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();
       
        if(!$selectedExternalMedia) {
            return [];
        }
        $externalMediaServices = [];

        $loadBtnTranslation = _t('CookieConsent.IframeManager.LoadBtn', 'Load Once');
        $loadAllBtnTranslation = _t('CookieConsent.IframeManager.LoadAllBtn', 'Don\'t ask again');

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