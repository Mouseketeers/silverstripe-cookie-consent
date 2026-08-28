<?php

class CookieDeclarationShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            $cookieDeclarationData = CookieConsent::createConfigBuilder()->buildCookieDeclarationData();

            $categories = new ArrayList();
            foreach ($cookieDeclarationData['categories'] ?? [] as $categoryData) {
                $cookieDescriptions = new ArrayList();
                foreach ($categoryData['CookieDescriptions'] ?? [] as $cookieData) {
                    $cookieDescriptions->push(ArrayData::create($cookieData));
                }

                $categories->push(ArrayData::create([
                    'Title' => $categoryData['Title'],
                    'Content' => $categoryData['Content'],
                    'CookieDescriptions' => $cookieDescriptions,
                ]));
            }

            if (!$categories->exists()) {
                return '';
            }

            $data = ArrayData::create([
                'ConsentID' => CookieConsent::getConsentId(),
                'ConsentDate' => CookieConsent::getLastConsentTimestamp(),
                'AcceptedCategories' => CookieConsent::getCategoryLabels(),
                'Categories' => $categories
            ]);

            return $data->renderWith('CookieDeclarationShortcode')->getValue();
        });
    }
}
