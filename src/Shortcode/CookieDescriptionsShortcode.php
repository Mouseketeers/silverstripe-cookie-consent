<?php

class CookieDeclarationShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            $declarationData = (new CookieConsentConfigBuilder())->buildDeclarationData();
            $categories = self::buildCategoryList($declarationData);

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

    protected static function buildCategoryList(array $declarationData)
    {
        $categories = new ArrayList();
        $declarationCategories = isset($declarationData['categories']) && is_array($declarationData['categories'])
            ? $declarationData['categories']
            : [];

        foreach ($declarationCategories as $categoryData) {
            if (!is_array($categoryData)) {
                continue;
            }

            $cookieDescriptions = new ArrayList();
            $cookies = isset($categoryData['cookies']) && is_array($categoryData['cookies'])
                ? $categoryData['cookies']
                : [];

            foreach ($cookies as $cookieData) {
                if (!is_array($cookieData)) {
                    continue;
                }

                $cookieDescriptions->push(ArrayData::create([
                    'Name' => isset($cookieData['name']) ? $cookieData['name'] : '',
                    'Vendor' => isset($cookieData['vendor']) ? $cookieData['vendor'] : '',
                    'Service' => isset($cookieData['service']) ? $cookieData['service'] : '',
                    'Domain' => isset($cookieData['domain']) ? $cookieData['domain'] : '',
                    'PrivacyPolicyURL' => isset($cookieData['privacyPolicyURL']) ? $cookieData['privacyPolicyURL'] : '',
                    'Description' => isset($cookieData['description']) ? $cookieData['description'] : '',
                    'Expiration' => isset($cookieData['expiration']) ? $cookieData['expiration'] : ''
                ]));
            }

            if (!$cookieDescriptions->exists()) {
                continue;
            }

            $categories->push(ArrayData::create([
                'Title' => isset($categoryData['title']) ? $categoryData['title'] : '',
                'Content' => isset($categoryData['content']) ? $categoryData['content'] : '',
                'CookieDescriptions' => $cookieDescriptions
            ]));
        }

        return $categories;
    }
}
