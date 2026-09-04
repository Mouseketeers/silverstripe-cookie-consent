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
    public $Wildcard;

    public static function fromRegistry($registryData, $serviceName)
    {
        $vm = new self();

        $provider = $serviceName;
        $vm->Name = $registryData->cookie ?? '';
        $vm->Provider = $provider;
        $vm->Service = $serviceName;
        $vm->Domain = $registryData->domain ?? '';
        $vm->PrivacyPolicyURL = $registryData->privacyLink ?? '';
        $vm->Description = _t(
            'CookieConsent.RegistryCookies.' . $registryData->id . '.description',
            $registryData->description ?? 'No description available.'
        );
        $vm->Expiration = _t(
            'CookieConsent.RegistryCookies.' . $registryData->id . '.retentionPeriod',
            $registryData->retentionPeriod ?? 'Unknown'
        );
        $vm->Wildcard = $registryData->wildcardMatch ?? false;

        return $vm;
    }

    public static function fromDataObject(CookieDescription $cookie)
    {
        $vm = new self();

        $vm->Name = $cookie->getField('Name');
        $vm->Provider = $cookie->Provider;
        $vm->Service = $cookie->Service;
        $vm->Domain = $cookie->Domain;
        $vm->PrivacyPolicyURL = $cookie->PrivacyPolicyURL;
        $vm->Description = $cookie->Description;
        $vm->Expiration = $cookie->Expiration;
        $vm->Wildcard = (bool) $cookie->Wildcard;

        return $vm;
    }

    public static function fromConfig($cookieName, $host, $config = [])
    {
        $vm = new self();
        $defaultDescription = $config['description'] ?? 'No description available.';
        $defaultExpiration = $config['expiration'] ?? 'Unknown';
        $provider = $config['provider'] ?? $host;

        $vm->Name = $cookieName;
        $vm->Provider = $provider;
        $vm->Service = $provider;
        $vm->Domain = $config['domain'] ?? $host;
        $vm->PrivacyPolicyURL = 'privacy_url';
        $vm->Description = _t('CookieConsent.Cookies.' . $cookieName . '.description', $defaultDescription);
        $vm->Expiration = _t('CookieConsent.Cookies.' . $cookieName . '.expiration', $defaultExpiration);
        $vm->Wildcard = ($config['wildcard'] ?? false) === true;

        return $vm;
    }

    public function forTemplate()
    {
        $displayName = $this->Wildcard ? $this->Name . '*' : $this->Name;

        return ArrayData::create([
            'Name' => $displayName,
            'Provider' => $this->Provider,
            'Service' => $this->Service,
            'Domain' => $this->Domain,
            'PrivacyPolicyURL' => $this->PrivacyPolicyURL,
            'Description' => $this->Description,
            'Expiration' => $this->Expiration,
            'Wildcard' => $this->Wildcard,
        ]);
    }

    public function toArray()
    {
        $displayName = $this->Wildcard ? $this->Name . '*' : $this->Name;

        return [
            'name' => $displayName,
            'provider' => $this->Provider,
            'service' => $this->Service,
            'domain' => $this->Domain,
            'privacyPolicyURL' => $this->PrivacyPolicyURL,
            'description' => $this->Description,
            'expiration' => $this->Expiration,
        ];
    }
}
