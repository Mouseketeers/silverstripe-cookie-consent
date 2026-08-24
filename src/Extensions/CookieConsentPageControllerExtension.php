<?php

class CookieConsentPageControllerExtension extends Extension
{
    public function getIsCookieConsentDisabled()
    {
        return CookieConsent::isCookieConsentDisabled();
    }
    public function onAfterInit()
    {
        if (!CookieConsent::isCookieConsentDisabled() && CookieConsent::hasDataToRender()) {
            if (!CookieConsent::isDefaultJsDisabled()) {
                $config = CookieConsent::createConfigBuilder()->buildConsentConfig();
                $configJson = json_encode(
                    $config,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                );
                
                if ($configJson !== false) {
                    Requirements::customScript(
                        "window.cookieConsentConfig = {$configJson};",
                        'cookie-consent-config'
                    );
                } else {
                    SS_Log::log('Failed to encode cookie consent configuration', SS_Log::WARN);
                }
                
                Requirements::javascript('cookie-consent/client/dist/javascript/cookie-consent.min.js');
            }
            if (!CookieConsent::isDefaultCssDisabled()) {
                Requirements::css('cookie-consent/client/dist/css/cookie-consent.css');
            }
        }
    }
}