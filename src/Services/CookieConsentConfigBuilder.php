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

        $buildCookieDescriptions = $this->buildCookieDescriptions($siteConfig);
        $buildCategories = $this->buildCategories($buildCookieDescriptions);

        $isExternalMediaManagementEnabled = CookieConsent::isExternalMediaManagementEnabled();

        $config = [
            'defaultLanguage' => $languageCode,
            'guiOptions' => CookieConsent::getGuiOptions(),
            'categories' => $buildCategories,
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig, $buildCategories, $buildCookieDescriptions)
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
    public function buildCategories($buildCookieDescriptions)
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

        $buildCategories = [];
        foreach ($categoryConfig as $categoryKey => $categoryData) {

            if($categoryKey === $externalMediaCategory && empty($selectedExternalMedia)) {
                continue;
            }        
            $cookies = isset($buildCookieDescriptions[$categoryKey]) ? $buildCookieDescriptions[$categoryKey] : [];
            if ($categoryKey !== $externalMediaCategory && empty($cookies)) {
                continue;
            }             
            $buildCategories[$categoryKey] = $categoryData;
            if ($categoryKey !== $externalMediaCategory) {
                continue;
            }

            $buildCategories[$categoryKey]['services'] = $categoryServices;
        }
        return $buildCategories;
    }

    protected function buildTranslations($siteConfig, $buildCategories, $buildCookieDescriptions)
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
                'sections' => $this->buildSectionTranslations($buildCategories, $buildCookieDescriptions)
            ]
        ];
    }

    protected function buildSectionTranslations($buildCategories, $buildCookieDescriptions)
    {
        $sections = [];

        $CookieHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
        ];
        
        foreach ($buildCategories as $categoryKey => $categoryData) {

            $title = $this->getSectionTitle($categoryKey);
            $description = $this->getSectionDescription($categoryKey);
            $cookies = isset($buildCookieDescriptions[$categoryKey]) ? $buildCookieDescriptions[$categoryKey] : [];

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
    protected function buildCookieDescriptions($siteConfig)
    {
        $buildCookieDescriptions = [];

        if (!$siteConfig || !is_object($siteConfig)) {
            return $buildCookieDescriptions;
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        $services = $siteConfig->CookieServices();
        $customCookies = $siteConfig->CustomCookies();
        $buildCategories = CookieConsent::getCategoryConfig();

        if (!is_array($buildCategories) || empty($buildCategories)) {
            return $buildCookieDescriptions;
        }

        foreach ($buildCategories as $categoryKey => $categoryData) {
            $defaultCookies = isset($categoryData['cookies']) && is_array($categoryData['cookies'])
                ? $categoryData['cookies']
                : [];

            foreach ($defaultCookies as $cookieName) {
                $buildCookieDescriptions[$categoryKey][] = [
                    'name' => $cookieName,
                    'provider' => $host,
                    'service' => $host,
                    'description' => _t('CookieConsent.Cookies.' . $cookieName . '.description', ''),
                    'domain' => $host,
                    'expiration' => _t('CookieConsent.Cookies.' . $cookieName . '.expiration', '')
                ];
            }
            if (!is_iterable($services)) {
                continue;
            }

            foreach ($services as $service) {
                

                $cookieTranslations = $service->getCookieTranslationsForCategory($categoryKey);

                if (!is_iterable($cookieTranslations)) {
                    continue;
                }

                foreach ($cookieTranslations as $cookie) {

                    if (!isset($buildCookieDescriptions[$categoryKey])) {
                        $buildCookieDescriptions[$categoryKey] = [];
                    }

                    $buildCookieDescriptions[$categoryKey][] = [
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
        if (is_iterable($customCookies)) {
            foreach ($customCookies as $customCookie) {
                if (empty($customCookie->Category) || !isset($buildCookieDescriptions[$customCookie->Category])) {
                    $buildCookieDescriptions[$customCookie->Category] = [];
                }

                $buildCookieDescriptions[$customCookie->Category][] = [
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
        }

        return $buildCookieDescriptions;
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
    public function buildCookieDeclarationData()
    {
        $cache = CookieConsentConfigCache::getCache();
        $cacheKey = CookieConsentConfigCache::getDeclarationCacheKey();
        $cachedDeclaration = $cache->load($cacheKey);

        if ($cachedDeclaration !== false) {
            if (is_string($cachedDeclaration)) {
                $decodedDeclaration = @unserialize($cachedDeclaration);
                if (is_array($decodedDeclaration)) {
                    return $this->buildDeclarationTemplateData($decodedDeclaration);
                }
            }
        }

        $siteConfig = SiteConfig::current_site_config();
        $categoriesConfig = CookieConsent::getCategoryConfig();

        $buildDeclarationCategories = $this->buildDeclarationCategories($siteConfig, $categoriesConfig);

        $cookieDeclarationData = [
            'categories' => $buildDeclarationCategories
        ];

        $cache->save(serialize($cookieDeclarationData), $cacheKey);

        return $this->buildDeclarationTemplateData($cookieDeclarationData);
    }

    public function buildDeclarationTemplateData(array $cookieDeclarationData)
    {
        $categories = new ArrayList();
        $cookieDeclarationCategories = isset($cookieDeclarationData['categories']) && is_array($cookieDeclarationData['categories'])
            ? $cookieDeclarationData['categories']
            : [];

        foreach ($cookieDeclarationCategories as $categoryData) {
            if (!is_array($categoryData)) {
                continue;
            }

            $buildCookieDescriptions = new ArrayList();
            $cookies = isset($categoryData['CookieDescriptions']) && is_array($categoryData['CookieDescriptions'])
                ? $categoryData['CookieDescriptions']
                : [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $buildCookieDescriptions->push(ArrayData::create($cookieData));
            }

            if (!$buildCookieDescriptions->exists()) {
                continue;
            }

            $categories->push(ArrayData::create([
                'Title' => isset($categoryData['Title']) ? $categoryData['Title'] : '',
                'Content' => isset($categoryData['Content']) ? $categoryData['Content'] : '',
                'CookieDescriptions' => $buildCookieDescriptions
            ]));
        }

        return [
            'categories' => $categories
        ];
    }    

    protected function buildDeclarationCategories($siteConfig, $buildCategories)
    {
        $declarationCategories = [];
        $buildCookieDescriptions = $this->buildCookieDescriptions($siteConfig);

        foreach ($buildCategories as $categoryKey => $categoryData) {
            $cookies = isset($buildCookieDescriptions[$categoryKey]) ? $buildCookieDescriptions[$categoryKey] : [];

            if (empty($cookies) && empty($categoryData['services'])) {
                continue;
            }

            usort($cookies, function ($left, $right) {
                return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
            });

            $buildCookieDescriptions = [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $buildCookieDescriptions[] = [
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

            if (empty($buildCookieDescriptions)) {
                continue;
            }

            $declarationCategories[] = [
                'Title' => $this->getSectionTitle($categoryKey),
                'Content' => $this->getSectionDescription($categoryKey),
                'CookieDescriptions' => $buildCookieDescriptions
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
}