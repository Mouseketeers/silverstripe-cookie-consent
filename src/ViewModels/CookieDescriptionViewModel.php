<?php

class CookieDescriptionViewModel extends ViewableData
{
    public $Name;
    public $Provider;
    public $Service;
    public $Domain;
    public $PrivacyPolicyURL;
    public $Description;
    public $Expiration;

    public static function fromRegistry($registryData, $serviceName)
    {
        $vm = new self();

        $provider = $serviceName;
        $vm->Name = $registryData->cookie ?? '';
        if (!empty($registryData->wildcardMatch) && $registryData->wildcardMatch !== '0') {
            $vm->Name .= '*';
        }
        $vm->Provider = $provider;
        $vm->Service = $serviceName;
        $vm->Domain = $registryData->domain ?? '';
        $vm->PrivacyPolicyURL = $registryData->privacyLink ?? '';
        $vm->Description = _t(
            'CookieConsent.RegistryCookies.' . $registryData->id . '.description',
            $registryData->description ?? ''
        );
        $vm->Expiration = _t(
            'CookieConsent.RegistryCookies.' . $registryData->id . '.retentionPeriod',
            $registryData->retentionPeriod ?? ''
        );

        return $vm;
    }

    public static function fromDataObject(CookieDescription $cookie)
    {
        $vm = new self();

        $vm->Name = $cookie->getName();
        $vm->Provider = $cookie->Provider;
        $vm->Service = $cookie->Service;
        $vm->Domain = $cookie->Domain;
        $vm->PrivacyPolicyURL = $cookie->PrivacyPolicyURL;
        $vm->Description = $cookie->Description;
        $vm->Expiration = $cookie->Expiration;

        return $vm;
    }

    public static function fromConfig($cookieName, $host)
    {
        $vm = new self();

        $vm->Name = $cookieName;
        $vm->Provider = $host;
        $vm->Service = $host;
        $vm->Domain = $host;
        $vm->PrivacyPolicyURL = '';
        $vm->Description = _t('CookieConsent.Cookies.' . $cookieName . '.description', '');
        $vm->Expiration = _t('CookieConsent.Cookies.' . $cookieName . '.expiration', '');

        return $vm;
    }

    public function forTemplate()
    {
        return ArrayData::create([
            'Name' => $this->Name,
            'Provider' => $this->Provider,
            'Service' => $this->Service,
            'Domain' => $this->Domain,
            'PrivacyPolicyURL' => $this->PrivacyPolicyURL,
            'Description' => $this->Description,
            'Expiration' => $this->Expiration,
        ]);
    }

    public function toArray()
    {
        return [
            'name' => $this->Name,
            'provider' => $this->Provider,
            'service' => $this->Service,
            'domain' => $this->Domain,
            'privacyPolicyURL' => $this->PrivacyPolicyURL,
            'description' => $this->Description,
            'expiration' => $this->Expiration,
        ];
    }
}
