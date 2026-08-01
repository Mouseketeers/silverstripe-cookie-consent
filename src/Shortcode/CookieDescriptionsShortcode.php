<?php

namespace Mouseketeers\CookieConsent\Shortcode;

use Mouseketeers\CookieConsent\CookieConsent;
use SilverStripe\Model\ArrayData;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Parsers\ShortcodeParser;

class CookieDescriptionsShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            
            $categories = [];

            $siteConfig = SiteConfig::current_site_config();

            foreach ($siteConfig->CookieSections() as $category) {
                if ($category->CookieDescriptions()->exists()) {
                    $categories[] = $category;
                }
            }

            if (empty($categories)) {
                return '';
            }

            $data = ArrayData::create([
                'ConsentID' => CookieConsent::getConsentId(),
                'ConsentDate' => CookieConsent::getLastConsentTimestamp(),
                'AcceptedCategories' => CookieConsent::getCategories(),
                'Categories' => $categories
            ]);

            return $data->renderWith('CookieDeclarationShortcode')->getValue();
        });
    }
}
