<?php

class CookieDeclarationShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            $cookieDeclarationData = CookieConsent::createConfigBuilder()->buildCookieDeclarationData();
            $categories = isset($cookieDeclarationData['categories']) && $cookieDeclarationData['categories'] instanceof ArrayList
                ? $cookieDeclarationData['categories']
                : new ArrayList();

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
