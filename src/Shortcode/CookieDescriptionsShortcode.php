<?php

class CookieDeclarationShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            $categories = new ArrayList();
            $siteConfig = SiteConfig::current_site_config();

            if (!$siteConfig) {
                return '';
            }

            foreach ($siteConfig->CookieSections() as $category) {
                $normalizedCategory = strtolower(trim((string) $category->ConsentCategory));
                if ($normalizedCategory === '') {
                    continue;
                }

                $cookieDescriptions = new ArrayList();
                foreach ($siteConfig->CookieServices() as $service) {
                    foreach ($service->getCookieDescriptionsForCategory($normalizedCategory) as $cookieDescription) {
                        $cookieDescriptions->push($cookieDescription);
                    }
                }

                if (!$cookieDescriptions->exists() && !$category->CookieDescriptions()->exists()) {
                    continue;
                }

                $categories->push(ArrayData::create([
                    'Title' => $category->Title,
                    'Content' => $category->Description,
                    'CookieDescriptions' => $cookieDescriptions->exists()
                        ? $cookieDescriptions
                        : $category->CookieDescriptions()
                ]));
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
