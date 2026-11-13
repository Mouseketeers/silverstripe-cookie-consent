<?php

namespace Mouseketeers\CookieConsent\Shortcode;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use Mouseketeers\CookieConsent\CookieConsent;
use SilverStripe\View\Parsers\ShortcodeParser;

class CookieDescriptionsShortcode
{
    public static function register()
    {
        ShortcodeParser::get('default')->register('cookie_declaration', function () {
            if (CookieConsent::isModuleDisabled()) {
                return '';
            }

            $cookieDeclarationData = CookieConsent::createDataBuilder()->buildCookieDeclarationData();

            $categories = ArrayList::create();
            foreach ($cookieDeclarationData['categories'] ?? [] as $categoryData) {
                $cookieDescriptions = ArrayList::create();
                foreach ($categoryData['CookieDescriptions'] ?? [] as $cookieData) {
                    $cookieDescriptions->push(ArrayData::create($cookieData));
                }

                $categories->push(ArrayData::create([
                    'Title' => $categoryData['Title'],
                    'CookieDescriptions' => $cookieDescriptions,
                ]));
            }

            if (!$categories->exists()) {
                return '';
            }

            $data = ArrayData::create([
                'Categories' => $categories
            ]);

            return $data->renderWith('CookieDeclarationShortcode')->getValue();
        });
    }
}
