<?php

class CookieConsentConfigBuilder
{
    public function buildConsentConfig()
    {
        $cacheKey = CookieConsentConfigCache::getJsCacheKey();
        $cachedConfig = $this->loadFromCache($cacheKey);

        if ($cachedConfig !== null) {
            return $cachedConfig;
        }

        $currentLocale = i18n::get_locale();
        $languageCode = !empty($currentLocale)
            ? strtolower(substr($currentLocale, 0, 2))
            : 'en';

        $siteConfig = SiteConfig::current_site_config();

        $builtCookieDescriptions = $this->buildCookieDescriptions($siteConfig);
        $builtCategories = $this->buildCategories($builtCookieDescriptions);

        $isExternalMediaManagementEnabled = CookieConsent::isExternalMediaManagementEnabled();

        $config = [
            'defaultLanguage' => $languageCode,
            'guiOptions' => CookieConsent::getGuiOptions(),
            'categories' => $builtCategories,
            'translations' => [
                $languageCode => $this->buildTranslations($siteConfig, $builtCategories, $builtCookieDescriptions)
            ],
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'isExternalMediaManagementEnabled' => $isExternalMediaManagementEnabled,
        ];
        if ($isExternalMediaManagementEnabled) {
            $config['externalMediaCategory'] = CookieConsent::getExternalMediaCategory();
            $config['externalMediaServices'] = [
                'services' => $this->buildExternalMediaServices($languageCode)
            ];
        }
        CookieConsentConfigCache::getCache()->save(serialize($config), $cacheKey);
        return $config;
    }

    public function buildCategories($builtCookieDescriptions)
    {
        $categoryConfig = CookieConsent::getCategoryConfig();
        $externalMediaCategory = CookieConsent::getExternalMediaCategory();
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();

        $builtCategories = [];
        foreach ($categoryConfig as $categoryKey => $categoryData) {

            // no further processing needed if it's the external media category and no external media services are selected
            if ($categoryKey === $externalMediaCategory && empty($selectedExternalMedia)) {
                continue;
            }
            $cookies = $this->getCategoryCookies($builtCookieDescriptions, $categoryKey);
            // no further processing needed if there are no cookies
            if ($categoryKey !== $externalMediaCategory && empty($cookies)) {
                continue;
            }
            
            $builtCategories[$categoryKey] = $categoryData;
            if ($categoryKey !== $externalMediaCategory) {
                continue;
            }

            // add selected external media services to the external media category
            foreach($selectedExternalMedia as $key) {
                $builtCategories[$categoryKey]['services'][$key] = [
                    'label' => _t('CookieConsent.ExternalMediaServices.' . $key, '')
                ];
            }
        }
        return $builtCategories;
    }

    protected function buildTranslations($siteConfig, $builtCategories, $builtCookieDescriptions)
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
                'sections' => $this->buildSectionTranslations($builtCategories, $builtCookieDescriptions)
            ]
        ];
    }

    protected function buildSectionTranslations($builtCategories, $builtCookieDescriptions)
    {
        $sections = [];

        $CookieHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
        ];

        foreach ($builtCategories as $categoryKey => $categoryData) {

            $title = $this->getSectionTitle($categoryKey);
            $description = $this->getSectionDescription($categoryKey);
            $cookies = $this->getCategoryCookies($builtCookieDescriptions, $categoryKey);

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
        $builtCookieDescriptions = [];

        if (!$siteConfig || !is_object($siteConfig)) {
            return $builtCookieDescriptions;
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        $services = $siteConfig->CookieServices();
        $customCookies = $siteConfig->CustomCookies();
        $categoriesConfig = CookieConsent::getCategoryConfig();

        foreach ($categoriesConfig as $categoryKey => $categoryData) {

            // add cookies defined in yml config
            $defaultCookies = isset($categoryData['cookies']) && is_array($categoryData['cookies'])
                ? $categoryData['cookies']
                : [];

            foreach ($defaultCookies as $cookieName) {
                $builtCookieDescriptions[$categoryKey][] = [
                    'name' => $cookieName,
                    'provider' => $host,
                    'service' => $host,
                    'description' => _t('CookieConsent.Cookies.' . $cookieName . '.description', ''),
                    'domain' => $host,
                    'expiration' => _t('CookieConsent.Cookies.' . $cookieName . '.expiration', '')
                ];
            }

            // add cookies from selected services
            foreach ($services as $service) {
                $cookieTranslations = $service->getCookieTranslationsForCategory($categoryKey);

                if (!is_iterable($cookieTranslations)) {
                    continue;
                }

                foreach ($cookieTranslations as $cookie) {
                    if (!isset($builtCookieDescriptions[$categoryKey])) {
                        $builtCookieDescriptions[$categoryKey] = [];
                    }

                    $builtCookieDescriptions[$categoryKey][] = $this->mapCookieToArray($cookie);
                }
            }
        }
        // add custom cookies defined in the CMS
        foreach ($customCookies as $customCookie) {
            if (empty($customCookie->Category) || !isset($builtCookieDescriptions[$customCookie->Category])) {
                $builtCookieDescriptions[$customCookie->Category] = [];
            }

            $builtCookieDescriptions[$customCookie->Category][] = $this->mapCookieToArray($customCookie);
        }

        return $builtCookieDescriptions;
    }

    public function buildExternalMediaServices($languageCode)
    {
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();

        if (!$selectedExternalMedia) {
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
        $cacheKey = CookieConsentConfigCache::getDeclarationCacheKey();
        $cachedDeclaration = $this->loadFromCache($cacheKey);

        if ($cachedDeclaration !== null) {
            return $this->buildDeclarationTemplateData($cachedDeclaration);
        }

        $siteConfig = SiteConfig::current_site_config();
        $categoriesConfig = CookieConsent::getCategoryConfig();

        $buildDeclarationCategories = $this->buildDeclarationCategories($siteConfig, $categoriesConfig);

        $cookieDeclarationData = [
            'categories' => $buildDeclarationCategories
        ];

        CookieConsentConfigCache::getCache()->save(serialize($cookieDeclarationData), $cacheKey);

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

            $builtCookieDescriptions = new ArrayList();
            $cookies = isset($categoryData['CookieDescriptions']) && is_array($categoryData['CookieDescriptions'])
                ? $categoryData['CookieDescriptions']
                : [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $builtCookieDescriptions->push(ArrayData::create($cookieData));
            }

            if (!$builtCookieDescriptions->exists()) {
                continue;
            }

            $categories->push(ArrayData::create([
                'Title' => isset($categoryData['Title']) ? $categoryData['Title'] : '',
                'Content' => isset($categoryData['Content']) ? $categoryData['Content'] : '',
                'CookieDescriptions' => $builtCookieDescriptions
            ]));
        }

        return [
            'categories' => $categories
        ];
    }

    protected function buildDeclarationCategories($siteConfig, $builtCategories)
    {
        $declarationCategories = [];
        $builtCookieDescriptions = $this->buildCookieDescriptions($siteConfig);

        foreach ($builtCategories as $categoryKey => $categoryData) {
            $cookies = $this->getCategoryCookies($builtCookieDescriptions, $categoryKey);

            if (empty($cookies) && empty($categoryData['services'])) {
                continue;
            }

            usort($cookies, function ($left, $right) {
                return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
            });

            $mappedCookies = [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $mappedCookies[] = [
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

            if (empty($mappedCookies)) {
                continue;
            }

            $declarationCategories[] = [
                'Title' => $this->getSectionTitle($categoryKey),
                'Content' => $this->getSectionDescription($categoryKey),
                'CookieDescriptions' => $mappedCookies
            ];
        }

        return $declarationCategories;
    }

    protected function getSectionTitle($categoryId)
    {
        return _t(CookieConsent::getCategoryTranslationKey($categoryId), '');
    }

    protected function getSectionDescription($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s.Description', $categoryId);

        return _t($translationKey, '');
    }

    /**
     * Load and unserialize a cached config value.
     *
     * @param string $cacheKey
     * @return array|null
     */
    protected function loadFromCache($cacheKey)
    {
        $cachedConfig = CookieConsentConfigCache::getCache()->load($cacheKey);

        if ($cachedConfig === false || !is_string($cachedConfig)) {
            return null;
        }

        $decodedConfig = @unserialize($cachedConfig);

        return is_array($decodedConfig) ? $decodedConfig : null;
    }

    /**
     * Map a cookie object to its array representation.
     *
     * @param object $cookie
     * @return array
     */
    protected function mapCookieToArray($cookie)
    {
        return [
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

    /**
     * Get the cookie descriptions for a given category, or an empty array.
     *
     * @param array $builtCookieDescriptions
     * @param string $categoryKey
     * @return array
     */
    protected function getCategoryCookies($builtCookieDescriptions, $categoryKey)
    {
        return isset($builtCookieDescriptions[$categoryKey]) ? $builtCookieDescriptions[$categoryKey] : [];
    }
}
