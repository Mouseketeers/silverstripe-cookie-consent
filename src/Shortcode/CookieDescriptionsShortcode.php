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

            foreach (CookieConsent::getCategoriesConfig() as $categoryId => $categoryConfig) {
                $normalizedCategory = strtolower(trim((string) $categoryId));
                if ($normalizedCategory === '') {
                    continue;
                }

                $cookieDescriptions = new ArrayList();
                foreach ($siteConfig->CookieServices() as $service) {
                    foreach ($service->getCookieDescriptionsForCategory($normalizedCategory) as $cookieDescription) {
                        $cookieDescriptions->push($cookieDescription);
                    }
                }

                if (!$cookieDescriptions->exists()) {
                    continue;
                }

                $categories->push(ArrayData::create([
                    'Title' => self::getCategoryTitle($categoryId),
                    'Content' => self::getCategoryDescription($categoryId),
                    'CookieDescriptions' => $cookieDescriptions
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

    protected static function getCategoryTitle($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s', $categoryId);
        $defaultLabel = ucwords(str_replace(['-', '_'], ' ', $categoryId));

        return _t($translationKey, $defaultLabel);
    }

    protected static function getCategoryDescription($categoryId)
    {
        $translationKey = sprintf('CookieConsent.Category.%s.Description', $categoryId);

        return _t($translationKey, '');
    }
}
