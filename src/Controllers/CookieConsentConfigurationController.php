<?php

class CookieConsentConfigurationController extends Controller
{
    private static $allowed_actions = array(
        'configuration'
    );

    public function configuration()
    {
        $config = (new CookieConsentConfigBuilder())->build();
        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ($json === false) {
            $json = '{}';
        }

        $response = new SS_HTTPResponse($json, 200);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->addHeader('Cache-Control', 'public, max-age=3600');
        $response->addHeader('Vary', 'Accept-Language');

        return $response;
    }
}