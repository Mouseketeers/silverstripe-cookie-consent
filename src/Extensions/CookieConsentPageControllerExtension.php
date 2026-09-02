<?php

namespace Mouseketeers\CookieConsent\Extensions;

use Mouseketeers\CookieConsent\CookieConsent;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\Requirements;

class CookieConsentPageControllerExtension extends Extension
{
    public function onAfterInit()
    {

        if(CookieConsent::isModuleDisabled()) {
            return;
        }

        if (!CookieConsent::isDefaultJsDisabled()) {
            $config = CookieConsent::createDataBuilder()->buildConsentConfig();
            $configJson = json_encode(
                $config,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($configJson !== false) {
                Requirements::customScript(
                    "window.cookieConsentConfig = {$configJson};",
                    'cookie-consent-config'
                );
            } else {
                Injector::inst()->get(LoggerInterface::class)->warning('Failed to encode cookie consent configuration');
            }

            Requirements::javascript('mouseketeers/silverstripe-cookie-consent:client/dist/javascript/cookie-consent.min.js');
        }
        if (!CookieConsent::isDefaultCssDisabled()) {
            Requirements::css('mouseketeers/silverstripe-cookie-consent:client/dist/css/cookie-consent.css');
        }
    }
}
