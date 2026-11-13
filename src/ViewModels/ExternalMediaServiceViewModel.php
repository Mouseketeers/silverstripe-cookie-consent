<?php

namespace Mouseketeers\CookieConsent\ViewModels;

use Mouseketeers\CookieConsent\CookieConsent;
use SilverStripe\Model\ModelData;

class ExternalMediaServiceViewModel extends ModelData
{
    public $Key;

    public static function fromString($key)
    {
        $vm = new self();
        $vm->Key = $key;
        return $vm;
    }

    public function toCategoryServiceArray()
    {
        $mediaConfig = CookieConsent::getExternalMediaConfig();
        return [
            'label' => $mediaConfig[$this->Key]['label'] ?? $this->Key
        ];
    }

    public function toIframeManagerLanguageArray($languageCode)
    {
        return [
            'languages' => [
                $languageCode => [
                    'loadBtn' => _t('CookieConsent.IframeManager.LoadBtn', 'Load Once'),
                    'loadAllBtn' => _t('CookieConsent.IframeManager.LoadAllBtn', 'Don\'t ask again'),
                    'notice' => _t(
                        'CookieConsent.IframeManager.Notice_' . $this->Key,
                        'This content is hosted by an external service.'
                    )
                ]
            ]
        ];
    }
}
