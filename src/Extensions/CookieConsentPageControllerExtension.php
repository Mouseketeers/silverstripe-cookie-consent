<?php

class CookieConsentPageControllerExtension extends Extension
{
    public function onAfterInit()
    {
        
        if(CookieConsent::isModuleDisabled()) {
            return;
        }

        if (CookieConsent::isGoogleConsentModeEnabled()) {
            // Google's tag must see this before it runs, so it goes in the head.
            Requirements::insertHeadTags(
                "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
                . "gtag('consent','default',{functionality_storage:'denied',personalization_storage:'denied',"
                . "analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',"
                . "security_storage:'granted'});</script>",
                'cookie-consent-gtag-default'
            );
        }

        // Always provide the config the client JS depends on, even when the
        // theme loads the JS itself (disable_default_js: true). Without this
        // window.cookieConsentConfig stays undefined.
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
            SS_Log::log('Failed to encode cookie consent configuration', SS_Log::WARN);
        }

        if (!CookieConsent::isDefaultJsDisabled()) {
            Requirements::javascript('cookie-consent/client/dist/javascript/cookie-consent.min.js');
        }
        if (!CookieConsent::isDefaultCssDisabled()) {
            Requirements::css('cookie-consent/client/dist/css/cookie-consent.css');
        }
    }
}