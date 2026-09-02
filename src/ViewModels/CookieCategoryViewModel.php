<?php

class CookieCategoryViewModel extends ViewableData
{
    public $Key;
    public $Title;
    public $Content;
    public $Config;
    public $CookieDescriptions;
    protected $cookieViewModels;

    public function __construct()
    {
        parent::__construct();
        $this->CookieDescriptions = new ArrayList();
        $this->cookieViewModels = [];
    }

    public static function create_instance($key, $config, ArrayList $cookies, $cookieViewModels = [])
    {
        $vm = new self();

        $vm->Key = $key;
        $vm->Config = $config;
        $defaultTitle = ucwords(str_replace(['-', '_'], ' ', $key));
        $vm->Title = isset($config['title']) && $config['title'] !== ''
            ? $config['title']
            : self::translateWithEnglishFallback(CookieConsent::getCategoryTranslationKey($key), $defaultTitle);
        $vm->Content = self::translateWithEnglishFallback(
            sprintf('CookieConsent.Category.%s.Description', $key),
            sprintf('%s cookies.', $vm->Title)
        );
        $vm->CookieDescriptions = $cookies;
        $vm->cookieViewModels = $cookieViewModels;

        return $vm;
    }

    public function forTemplate()
    {
        return ArrayData::create([
            'Title' => $this->Title,
            'Content' => $this->Content,
            'CookieDescriptions' => $this->CookieDescriptions,
        ]);
    }

    public function addService($key, $serviceData)
    {
        $this->Config['services'][$key] = $serviceData;
    }

    public function toArray()
    {
        $categoryArray = [];
        if ($this->CookieDescriptions->exists()) {
            $categoryArray['cookies'] = [];
            foreach ($this->CookieDescriptions as $cookieData) {
                $categoryArray['cookies'][] = [
                    'name' => $cookieData->Name,
                    'provider' => $cookieData->Provider,
                    'service' => $cookieData->Service,
                    'domain' => $cookieData->Domain,
                    'description' => $cookieData->Description,
                    'expiration' => $cookieData->Expiration,
                ];
            }
        }
        return $categoryArray;
    }

    public function toJsCategoryArray()
    {
        $categoryArray = array_merge(
            $this->Config,
            $this->toArray()
        );

        return $categoryArray;
    }

    /**
     * Translate an entity for the current locale, falling back to the English
     * translation when the current locale has no lang yml. An empty title in a
     * preferences modal section crashes vanilla-cookieconsent (it attaches a
     * toggle listener to a heading element that is only created for non-empty
     * titles), so an empty result must never reach the JS config.
     */
    public static function translateWithEnglishFallback($entity, $default)
    {
        $translated = _t($entity, $default);

        if ($translated !== null && $translated !== '') {
            return $translated;
        }

        $currentLocale = i18n::get_locale();
        i18n::set_locale('en_US');
        $english = _t($entity, $default);
        i18n::set_locale($currentLocale);

        return $english;
    }

    public static function buildSections($categoryVMs, $locale)
    {
        $sections = [];
        foreach ($categoryVMs as $vm) {
            $sections[] = $vm->toSection($locale);
        }
        return $sections;
    }

    public function toSection($languageCode)
    {
        $cookieTableHeaders = [
            'name' => _t('CookieConsent.CookieName', 'Name'),
            'provider' => _t('CookieConsent.CookieProvider', 'Provider'),
            'description' => _t('CookieConsent.CookieDescription', 'Description'),
            'expiration' => _t('CookieConsent.CookieExpiration', 'Expiration')
        ];

        $cookieTableBody = [];
        foreach ($this->CookieDescriptions as $cookieData) {
            $cookieTableBody[] = [
                'name' => $cookieData->Name,
                'provider' => $cookieData->Provider,
                'description' => $cookieData->Description,
                'expiration' => $cookieData->Expiration,
            ];
        }

        return [
            'title' => $this->Title,
            'description' => $this->Content,
            'linkedCategory' => $this->Key,
            'cookieTable' => [
                'headers' => $cookieTableHeaders,
                'body' => $cookieTableBody
            ]
        ];
    }
}
