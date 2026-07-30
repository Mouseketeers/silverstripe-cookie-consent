<?php

class CookieDeclarationShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            
            $categories = new ArrayList();

            $siteConfig = SiteConfig::current_site_config();

            foreach ($siteConfig->CookieSections() as $category) {
                if ($category->CookieDescriptions()->exists()) {
                    $categories->push($category);
                }
            }

            if (!$categories->exists()) {
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
