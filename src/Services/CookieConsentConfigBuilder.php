<?php

class CookieConsentConfigBuilder
{

    protected $configCategories;
    protected $externalMediaCategory;

    public function __construct()
    {
        $this->configCategories = CookieConsent::getCategoryConfig();
        $this->externalMediaCategory = CookieConsent::getExternalMediaCategoryConfig();
    }

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

        $categories = $this->buildCategories();

        $isExternalMediaManagementEnabled = CookieConsent::isExternalMediaManagementEnabled();

        $config = [
            'defaultLanguage' => $languageCode,
            'guiOptions' => CookieConsent::getGuiOptions(),
            'categories' => $this->categoriesToArray($categories),
            'translations' => [
                $languageCode => $this->buildCookieConsentTranslations($categories, $languageCode)
            ],
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'isExternalMediaManagementEnabled' => $isExternalMediaManagementEnabled,
        ];
        if ($isExternalMediaManagementEnabled) {
            $config['externalMediaCategory'] = $this->externalMediaCategory;
            $config['externalMediaServices'] = [
                'services' => $this->buildExternalMediaServices($languageCode)
            ];
        }
        CookieConsentConfigCache::getCache()->save(serialize($config), $cacheKey);
        return $config;
    }

    protected function buildCategories()
    {
        $configCategories = $this->configCategories;
        $externalMediaCategory = $this->externalMediaCategory;
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();
        $services = CookieConsent::getCookieServices();
        $customCookies = CookieConsent::getCustomCookies();
        $host = $_SERVER['HTTP_HOST'] ?? '';

        $categories = [];

        foreach ($configCategories as $categoryKey => $categoryData) {
            $cookies = new ArrayList();

            // add cookies defined in yml config
            $defaultCookies = isset($categoryData['cookies']) && is_array($categoryData['cookies'])
                ? $categoryData['cookies']
                : [];

            // no further processing needed if it's the external media category and no external media services are selected
            if ($categoryKey === $externalMediaCategory && empty($selectedExternalMedia) && empty($defaultCookies)) {
                continue;
            }

            foreach ($defaultCookies as $cookieName) {
                $cookies->push(CookieDescriptionViewModel::fromConfig($cookieName, $host)->forTemplate());
            }

            // add cookies from selected services
            if ($services) {
                foreach ($services as $service) {
                    foreach ($service->getCookieViewModelsByCategoryKey($categoryKey) as $cookieVM) {
                        $cookies->push($cookieVM->forTemplate());
                    }
                }
            }

            // add custom cookies defined in the CMS
            if ($customCookies) {
                foreach ($customCookies as $customCookie) {
                    if ($customCookie->Category === $categoryKey) {
                        $cookies->push(CookieDescriptionViewModel::fromDataObject($customCookie)->forTemplate());
                    }
                }
            }

            if ($categoryKey === $externalMediaCategory) {
                // external media category is kept even if no cookies, but we add services
                $categoryVM = CookieCategoryViewModel::create_instance($categoryKey, $categoryData, $cookies);
                $categories[$categoryKey] = $categoryVM;

                foreach ($selectedExternalMedia as $key) {
                    $categories[$categoryKey]->addService($key, ExternalMediaServiceViewModel::fromString($key)->toCategoryServiceArray());
                }
                continue;
            }

            // no further processing needed if there are no cookies
            if (!$cookies->exists()) {
                continue;
            }

            $categories[$categoryKey] = CookieCategoryViewModel::create_instance($categoryKey, $categoryData, $cookies);
        }

        return $categories;
    }

    protected function categoriesToArray($categories)
    {
        $result = [];
        foreach ($categories as $key => $categoryVM) {
            $result[$key] = $categoryVM->toJsCategoryArray();
        }
        return $result;
    }

    protected function buildCookieConsentTranslations($categories, $languageCode)
    {
        $siteConfig = CookieConsent::getSiteConfig();

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
                'sections' => CookieCategoryViewModel::buildSections($categories, $languageCode)
            ]
        ];
    }

    protected function buildExternalMediaServices($languageCode)
    {
        $selectedExternalMedia = CookieConsent::getSelectedExternalMedia();

        if (!$selectedExternalMedia) {
            return [];
        }
        $externalMediaServices = [];

        foreach ($selectedExternalMedia as $externalMediaKey) {
            $vm = ExternalMediaServiceViewModel::fromString($externalMediaKey);
            $externalMediaServices[$externalMediaKey] = $vm->toIframeManagerLanguageArray($languageCode);
        }

        return $externalMediaServices;
    }

    public function buildCookieDeclarationData()
    {
        $cacheKey = CookieConsentConfigCache::getDeclarationCacheKey();
        $cachedDeclaration = $this->loadFromCache($cacheKey);

        if ($cachedDeclaration !== null) {
            return $cachedDeclaration;
        }

        $categories = $this->buildCategories();
        $templateData = $this->buildDeclarationTemplateData($categories);

        CookieConsentConfigCache::getCache()->save(serialize($templateData), $cacheKey);

        return $templateData;
    }

    public function buildDeclarationTemplateData($categories)
    {
        $resultCategories = new ArrayList();

        foreach ($categories as $categoryVM) {
            if (!($categoryVM instanceof CookieCategoryViewModel)) {
                continue;
            }

            if (!$categoryVM->CookieDescriptions->exists()) {
                continue;
            }

            $resultCategories->push($categoryVM->forTemplate());
        }

        return [
            'categories' => $resultCategories
        ];
    }

    protected function loadFromCache($cacheKey)
    {
        $cachedConfig = CookieConsentConfigCache::getCache()->load($cacheKey);

        if ($cachedConfig === false || !is_string($cachedConfig)) {
            return null;
        }

        $decodedConfig = @unserialize($cachedConfig);

        return is_array($decodedConfig) ? $decodedConfig : null;
    }
}
