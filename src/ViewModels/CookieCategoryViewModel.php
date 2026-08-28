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
        $vm->Title = isset($config['title']) ? $config['title'] : _t(CookieConsent::getCategoryTranslationKey($key), '');
        $vm->Content = _t(sprintf('CookieConsent.Category.%s.Description', $key), '');
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

        $autoClearCookies = $this->buildAutoClearCookies();
        if ($autoClearCookies !== []) {
            $categoryArray['autoClear']['cookies'] = $autoClearCookies;
        }

        return $categoryArray;
    }

    protected function buildAutoClearCookies()
    {
        if (($this->Config['readOnly'] ?? false) === true) {
            return [];
        }

        $cookies = [];
        foreach ($this->cookieViewModels as $cookieViewModel) {
            $cookies[] = $cookieViewModel->toAutoClearArray();
        }
        return $cookies;
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
