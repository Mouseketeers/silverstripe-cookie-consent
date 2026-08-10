<?php
class CookieConsentAdmin extends ModelAdmin {

	private static $menu_icon = '/cookie-consent/images/cookie.svg';

	private static $dealersGroupID = 77;
	
	private static $managed_models = [
		'CookieService',
		'CookieDescription'
	];
	private static $url_segment = 'cookie-consent';

	private static $menu_title = 'Cookies';

	public function subsiteCMSShowInMenu() {
		return true;
	}
}