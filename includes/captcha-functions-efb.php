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
