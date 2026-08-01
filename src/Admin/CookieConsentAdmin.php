<?php

namespace Mouseketeers\CookieConsent\Admin;

use Mouseketeers\CookieConsent\Models\CookieDescription;
use SilverStripe\Admin\ModelAdmin;

class CookieConsentAdmin extends ModelAdmin
{
	private static $menu_icon = '/resources/vendor/mouseketeers/silverstripe-cookie-consent/images/cookie.svg';

	private static $dealersGroupID = 77;

	private static $managed_models = [
		CookieDescription::class,
	];

	private static $url_segment = 'cookie-consent';

	private static $menu_title = 'Cookies';

	public function subsiteCMSShowInMenu()
	{
		return true;
	}
}