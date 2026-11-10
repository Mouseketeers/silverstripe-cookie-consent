<?php

namespace Mouseketeers\CookieConsent\ViewModels;

use Mouseketeers\CookieConsent\CookieConsent;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\i18n\i18n;
use SilverStripe\View\ViewableData;

class CookieCategoryViewModel extends ViewableData
{
    public $Key;
    public $Title;
    public $Config;
    public $CookieDescriptions;
    protected $cookieViewModels;

    public function __construct()
    {
        parent::__construct();
        $this->CookieDescriptions = ArrayList::create();
        $this->cookieViewModels = [];
    }

    public static function create_instance($key, $config, ArrayList $cookies, $cookieViewModels = [])
    {
        $vm = new self();

        $vm->Key = $key;
        $vm->Config = $config;
        $defaultTitle = ucwords($key);
        $vm->Title = isset($config['title']) && $config['title'] !== ''
            ? $config['title']
            : self::translateWithEnglishFallback(CookieConsent::getCategoryTranslationKey($key), $defaultTitle);
        $vm->CookieDescriptions = $cookies;
        $vm->cookieViewModels = $cookieViewModels;

        return $vm;
    }

    public function addService($key, $serviceData)
    {
        $this->Config['services'][$key] = $serviceData;
    }

    public function toJsCategoryArray()
    {
        // The YAML cookie list is shown through the preferences sections, not read from here.
        $categoryArray = array_diff_key($this->Config, ['cookies' => true]);

        if (($this->Config['readOnly'] ?? false) !== true && $this->cookieViewModels !== []) {
            $categoryArray['autoClear']['cookies'] = array_map(
                fn($cookieViewModel) => $cookieViewModel->toAutoClearArray(),
                $this->cookieViewModels
            );
        }

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
            // The banner library inserts table cells as HTML.
            $cookieTableBody[] = array_map(fn($value) => Convert::raw2xml((string) $value), [
                'name' => $cookieData->Name,
                'provider' => $cookieData->Provider,
                'description' => $cookieData->Description,
                'expiration' => $cookieData->Expiration,
            ]);
        }

        return [
            'title' => $this->Title,
            'linkedCategory' => $this->Key,
            'cookieTable' => [
                'headers' => $cookieTableHeaders,
                'body' => $cookieTableBody
            ]
        ];
    }
}
