<?php

class ExternalMediaServiceViewModel extends ViewableData
{
    public $Key;

    public static function fromDataObject(ExternalMedia $media)
    {
        $vm = new self();
        $vm->Key = $media->Name;
        return $vm;
    }

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
            'label' => $mediaConfig[$this->Key]['label']
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
