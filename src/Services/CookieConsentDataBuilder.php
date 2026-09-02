<?php

class CookieConsentDataBuilder
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
        $cachedConfig = $this->loadJsonFromCache($cacheKey);

        if ($cachedConfig !== null) {
            return $cachedConfig;
        }

        $currentLocale = i18n::get_locale();
        $languageCode = $currentLocale !== null && $currentLocale !== ''
            ? strtolower(substr($currentLocale, 0, 2))
            : 'en';

        $categories = $this->buildCategories();

        $config = [
            'defaultLanguage' => $languageCode,
            'guiOptions' => CookieConsent::getGuiOptions(),
            'categories' => $this->categoriesToArray($categories),
            'translations' => [
                $languageCode => $this->buildCookieConsentTranslations($categories, $languageCode)
            ],
            'isGoogleConsentModeEnabled' => CookieConsent::isGoogleConsentModeEnabled(),
            'isConsentRegistrationEnabled' => CookieConsent::isConsentRegistrationEnabled(),
            'externalMediaCategory' => $this->externalMediaCategory,
            'externalMediaServices' => [
                'services' => $this->buildExternalMediaServices($languageCode)
            ]
        ];
        CookieConsentConfigCache::getCache()->save(json_encode($config, JSON_UNESCAPED_SLASHES), $cacheKey);
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
            $cookieViewModels = [];

            // get cookies defined in yml config
            $defaultCookies = is_array($categoryData['cookies'] ?? null)
                ? $categoryData['cookies']
                : [];

            // no further processing needed if it's the external media category and no external media services are selected
            if ($categoryKey === $externalMediaCategory && $selectedExternalMedia === [] && $defaultCookies === []) {
                continue;
            }

            foreach ($defaultCookies as $cookieName => $cookieConfig) {
                $vm = CookieDescriptionViewModel::fromConfig($cookieName, $host, $cookieConfig);
                $cookies->push($vm->forTemplate());
                $cookieViewModels[] = $vm;
            }

            // add cookies from selected services
            if ($services) {
                foreach ($services as $service) {
                    foreach ($service->getCookieViewModelsByCategoryKey($categoryKey) as $cookieViewModel) {
                        $cookies->push($cookieViewModel->forTemplate());
                        $cookieViewModels[] = $cookieViewModel;
                    }
                }
            }

            // add custom cookies defined in the CMS
            if ($customCookies) {
                foreach ($customCookies as $customCookie) {
                    if ($customCookie->Category === $categoryKey) {
                        $vm = CookieDescriptionViewModel::fromDataObject($customCookie);
                        $cookies->push($vm->forTemplate());
                        $cookieViewModels[] = $vm;
                    }
                }
            }

            if ($categoryKey === $externalMediaCategory) {
                // external media category is kept even if no cookies, but we add services
                $categoryViewModel = CookieCategoryViewModel::create_instance($categoryKey, $categoryData, $cookies, $cookieViewModels);
                $categories[$categoryKey] = $categoryViewModel;

                foreach ($selectedExternalMedia as $key) {
                    $categories[$categoryKey]->addService($key, ExternalMediaServiceViewModel::fromString($key)->toCategoryServiceArray());
                }
                continue;
            }

            // no further processing needed if there are no cookies
            if (!$cookies->exists()) {
                continue;
            }
            $categories[$categoryKey] = CookieCategoryViewModel::create_instance($categoryKey, $categoryData, $cookies, $cookieViewModels);
        }
        return $categories;
    }

    protected function categoriesToArray($categories)
    {
        $result = [];
        foreach ($categories as $key => $categoryViewModel) {
            $result[$key] = $categoryViewModel->toJsCategoryArray();
        }
        return $result;
    }

    protected function buildCookieConsentTranslations($categories, $languageCode)
    {
        $siteConfig = CookieConsent::getSiteConfig();

        $consentTitle = $siteConfig->CookieConsentModalTitle !== null && $siteConfig->CookieConsentModalTitle !== ''
            ? $siteConfig->CookieConsentModalTitle
            : _t('CookieConsent.CookieConsentModalTitle', 'Your Cookie Preferences');

        $consentDescription = $siteConfig->CookieConsentModalContent !== null && $siteConfig->CookieConsentModalContent !== ''
            ? $siteConfig->CookieConsentModalContent
            : _t(
                'CookieConsent.CookieConsentModalContent',
                '<p>We use cookies to improve your experience and understand how the website is used.</p>'
            );

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

        if ($selectedExternalMedia === []) {
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
        $cachedDeclaration = $this->loadJsonFromCache($cacheKey);

        if ($cachedDeclaration !== null) {
            return $cachedDeclaration;
        }

        $categories = $this->buildCategories();
        $templateData = $this->buildDeclarationTemplateData($categories);

        CookieConsentConfigCache::getCache()->save(json_encode($templateData, JSON_UNESCAPED_SLASHES), $cacheKey);

        return $templateData;
    }

    public function buildDeclarationTemplateData($categories)
    {
        $resultCategories = [];

        foreach ($categories as $categoryViewModel) {
            if (!($categoryViewModel instanceof CookieCategoryViewModel)) {
                continue;
            }

            if (!$categoryViewModel->CookieDescriptions->exists()) {
                continue;
            }

            $cookieDescriptions = [];
            foreach ($categoryViewModel->CookieDescriptions as $cookieData) {
                $cookieDescriptions[] = [
                    'Name' => $cookieData->Name,
                    'Provider' => $cookieData->Provider,
                    'PrivacyPolicyURL' => $cookieData->PrivacyPolicyURL,
                    'Description' => $cookieData->Description,
                    'Expiration' => $cookieData->Expiration,
                ];
            }

            $resultCategories[] = [
                'Title' => $categoryViewModel->Title,
                'Content' => $categoryViewModel->Content,
                'CookieDescriptions' => $cookieDescriptions,
            ];
        }

        return [
            'categories' => $resultCategories
        ];
    }

    protected function loadJsonFromCache($cacheKey)
    {
        $cachedConfig = CookieConsentConfigCache::getCache()->load($cacheKey);

        if ($cachedConfig === false || !is_string($cachedConfig)) {
            return null;
        }

        $decodedConfig = json_decode($cachedConfig, true);

        return is_array($decodedConfig) ? $decodedConfig : null;
    }
}
