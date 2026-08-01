<?php

namespace Mouseketeers\CookieConsent\Controllers;

use Mouseketeers\CookieConsent\Services\CookieConsentConfigBuilder;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;

class CookieConsentConfigurationController extends Controller
{
    private static $allowed_actions = [
        'configuration'
    ];

    public function configuration(): HTTPResponse
    {
        $config = (new CookieConsentConfigBuilder())->build();
        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ($json === false) {
            $json = '{}';
        }

        $response = HTTPResponse::create($json, 200);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->addHeader('Cache-Control', 'public, max-age=3600');
        $response->addHeader('Vary', 'Accept-Language');

        return $response;
    }
}