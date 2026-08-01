<?php

namespace Mouseketeers\CookieConsent\Extensions;

use Mouseketeers\CookieConsent\CookieConsent;
use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class CookieConsentPageControllerExtension extends Extension
{
    public function onAfterInit()
    {
        if (!CookieConsent::isDefaultJsDisabled()) {
            Requirements::javascript('mouseketeers/silverstripe-cookie-consent:client/dist/javascript/cookie-consent.min.js');
        }
        if (!CookieConsent::isDefaultCssDisabled()) {
            Requirements::css('mouseketeers/silverstripe-cookie-consent:client/dist/css/cookie-consent.css');
        }
    }
}
