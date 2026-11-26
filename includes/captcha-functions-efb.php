<?php
/**
 * F12 / F20 P20: the CAPTCHA check outside a form, a stable core contract (version 1,
 * docs/spam/captcha-contract.en.md). For add-ons (the customer portal's sign-in screen, …) and the tracking box.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_captcha_api')) {
	/**
	 * ['version' => 1, 'contract' => 2, 'providers' => [registered ids usable on this plan], 'provider' => the site's
	 * effective one, 'ready' => bool]. F12b: providers is the registry's list now (consumers use in_array()).
	 */
	function efb_captcha_api() {
		$eff = \Emsfb\Captcha\Registry::effective_for(array('captcha' => 1));
		$ids = array();
		foreach (\Emsfb\Captcha\Registry::all() as $id => $p) if (\Emsfb\Captcha\Registry::available($p)) $ids[] = $id;
		return array('version' => \Emsfb\Captcha::API_VERSION, 'contract' => \Emsfb\Captcha\Provider::API, 'providers' => $ids, 'provider' => $eff['id'] !== null ? $eff['id'] : $eff['want'], 'ready' => $eff['provider'] !== null);
	}
}

if (!function_exists('efb_captcha_verify')) {
	/** \Emsfb\Spam::verify_captcha(): ['ok', 'code', 'provider', 'score', 'spam']. */
	function efb_captcha_verify($token, $context, $args = array()) {
		return \Emsfb\Spam::verify_captcha($token, $context, is_array($args) ? $args : array());
	}
}

if (!function_exists('efb_captcha_widget')) {
	/** The widget's markup for $context (scripts enqueued), '' when the site has no ready CAPTCHA. */
	function efb_captcha_widget($context, $args = array()) {
		return \Emsfb\Spam::captcha_widget($context, is_array($args) ? $args : array());
	}
}

/* QA2-010: Google's published reCAPTCHA test secret is answered with the hostname testkey.google.com: v2 invisible and
 * v3 do not check the hostname for it (the CAPTCHA add-ons skip it for their test secrets the same way). */
if (function_exists('add_filter')) {
	add_filter('efb_captcha_check_hostname', function ($check, $id) {
		$p = $id === 'recaptcha_v2i' || $id === 'recaptcha_v3' ? \Emsfb\Captcha\Registry::get($id) : null;
		return $p && $p->setting($id === 'recaptcha_v3' ? 'rc3SecretKey' : 'rciSecretKey') === '6LeIxAcTAAAAAGG-vFI1TkRWxMZNFuojJ4WifJWe' ? false : $check;
	}, 10, 2);
}
