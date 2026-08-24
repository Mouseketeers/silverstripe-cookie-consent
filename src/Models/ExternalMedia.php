<?php

class ExternalMedia extends DataObject
{

    private static $singular_name = 'External Media';

    private static $plural_name = 'External Media';

    private static $db = [
        'Name' => 'Varchar(255)'
    ];

    private static $has_one = [
        'SiteConfig' => 'SiteConfig'
    ];

    private static $default_sort = 'Name ASC';

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        CookieConsentConfigCache::clear();
    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();
        CookieConsentConfigCache::clear();
    }
}
