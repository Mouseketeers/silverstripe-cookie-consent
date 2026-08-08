<?php

class CookieConsentPageControllerExtension extends Extension
{
    public function getIsCookieConsentDisabled()
    {
        return CookieConsent::isCookieConsentDisabled();
    }
    public function onAfterInit()
    {
        if (!CookieConsent::isCookieConsentDisabled()) {
            if (!CookieConsent::isDefaultJsDisabled()) {
                Requirements::javascript('cookie-consent/client/dist/javascript/cookie-consent.min.js');
            }
            if (!CookieConsent::isDefaultCssDisabled()) {
                Requirements::css('cookie-consent/client/dist/css/cookie-consent.css');
            }
        }
    }
}