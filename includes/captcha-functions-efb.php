<?php
/**
 * F12 / F20 P20: the CAPTCHA check outside a form, a stable core contract (version 1,
 * docs/spam/captcha-contract.en.md). For add-ons (the customer portal's sign-in screen, …) and the tracking box.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_captcha_api')) {
	/** ['version' => 1, 'providers' => [...], 'provider' => the site's, 'ready' => bool]. */
	function efb_captcha_api() {
		$p = \Emsfb\Captcha::site_provider();
		return array('version' => \Emsfb\Captcha::API_VERSION, 'providers' => \Emsfb\Captcha::PROVIDERS, 'provider' => $p, 'ready' => \Emsfb\Captcha::ready($p));
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
