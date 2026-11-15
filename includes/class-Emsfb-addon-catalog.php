<?php
/**
 * SPLIT §4.4: one table of every add-on core knows (code, folder, name, plan, contract, the files that prove it
 * is installed). get_all_addon_keys_efb(), addon_required_package_efb(), get_addon_required_files_efb(),
 * Emsfb_Addon_Compatibility::addon_requirements() and the builder's JS mirror (efb_var.addon_catalog) all read
 * it, so the five lists cannot drift. Filter efb_addon_catalog adds an entry (F20 AdnPRT, F12b CAPTCHA add-ons).
 *
 * Add-ons with loader "routes" (the SPLIT add-ons) are loaded here, the way Payments\Registry::load_addons()
 * loads the PAY00 gateways: vendor/<dir>/routes-efb.php of every add-on whose flag is on, that is compatible
 * with this PHP and whose plan the site has. The file checks the contract version itself (SPLIT §12 rule 1).
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Addon_Catalog {

	const VERSION = 1;

	/**
	 * code => dir (vendor/<dir>), name (shown to the owner, never translated: a product name), package
	 * (0 every plan, 3 Free Plus and Pro, 1 Pro: the plan that may install it), contract (efb_addon_contracts id or
	 * null), files (prove the add-on is installed), i18n (its phrase files), loader (manual | payments | routes).
	 * The order is the historical order of get_all_addon_keys_efb(); new add-ons are appended.
	 */
	const ADDONS = array(
		'AdnSPF' => array('dir' => 'stripe', 'name' => 'Stripe Payment', 'package' => 3, 'contract' => 'legacy_gateway', 'files' => array('vendor/stripe/class-Emsfb-stripe-payment.php'), 'loader' => 'manual'),
		'AdnOF'  => array('dir' => 'offline', 'name' => 'Offline Forms', 'package' => 0, 'contract' => null, 'files' => array('vendor/offline/json/countries.js', 'vendor/offline/json/states/ir.json', 'vendor/offline/json/states/zw.json', 'vendor/offline/json/cites/ir/teh.json', 'vendor/offline/json/cites/zw/mbn.json'), 'loader' => 'manual'),
		'AdnPPF' => array('dir' => 'persiapay', 'name' => 'Persia Payment', 'package' => 1, 'contract' => 'legacy_gateway', 'files' => array('vendor/persiapay/zarinpal.php'), 'loader' => 'manual'),
		'AdnATC' => array('dir' => '', 'name' => 'Advanced Tracking Code', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnSS'  => array('dir' => 'smssended', 'name' => 'SMS Notifications', 'package' => 1, 'contract' => null, 'files' => array('vendor/smssended/smsefb.php'), 'loader' => 'manual'),
		'AdnCPF' => array('dir' => '', 'name' => 'AdnCPF', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnESZ' => array('dir' => '', 'name' => 'AdnESZ', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnSE'  => array('dir' => '', 'name' => 'Search Entry', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnWHS' => array('dir' => '', 'name' => 'Webhook', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnPAP' => array('dir' => 'paypal', 'name' => 'PayPal Payment', 'package' => 3, 'contract' => 'legacy_gateway', 'files' => array('vendor/paypal/paypalefb.php'), 'loader' => 'manual'),
		'AdnWSP' => array('dir' => '', 'name' => 'WhatsApp', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnSMF' => array('dir' => 'logic', 'name' => 'Conditional Logic', 'package' => 3, 'contract' => null, 'files' => array('vendor/logic/logic/class-Emsfb-logic-validator.php'), 'loader' => 'manual'),
		'AdnPLF' => array('dir' => '', 'name' => 'AdnPLF', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnMSF' => array('dir' => '', 'name' => 'AdnMSF', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		// SPLIT §9.2: the booking add-on (Pro). Before SPLIT the key was listed without any add-on (§20 item 6).
		'AdnBEF' => array('dir' => 'booking', 'name' => 'Booking', 'package' => 1, 'contract' => 'booking', 'files' => array('vendor/booking/routes-efb.php'), 'i18n' => array('vendor/booking/booking-phrases-efb.php', 'vendor/booking/languages/locales.json'), 'loader' => 'routes'),
		'AdnPDP' => array('dir' => 'persiadatepicker', 'name' => 'Persian Date Picker', 'package' => 1, 'contract' => null, 'files' => array('vendor/persiadatepicker/persiandate.php'), 'loader' => 'manual'),
		'AdnADP' => array('dir' => 'arabicdatepicker', 'name' => 'Arabic Date Picker', 'package' => 1, 'contract' => null, 'files' => array('vendor/arabicdatepicker/arabicdate.php'), 'loader' => 'manual'),
		'AdnATF' => array('dir' => 'autofill', 'name' => 'Auto-Populate', 'package' => 1, 'contract' => null, 'files' => array('vendor/autofill/autofillefb.php'), 'loader' => 'manual'),
		'AdnTLG' => array('dir' => 'telegram', 'name' => 'Telegram Notifications', 'package' => 1, 'contract' => null, 'files' => array('vendor/telegram/telegram-new-efb.php'), 'loader' => 'manual'),
		'AdnGoS' => array('dir' => 'googlesheet', 'name' => 'Google Sheets', 'package' => 1, 'contract' => null, 'files' => array('vendor/googlesheet/class-Emsfb-googlesheet.php'), 'loader' => 'manual'),
		'AdnHSH' => array('dir' => 'human-shield', 'name' => 'Form Security & Spam Protection', 'package' => 1, 'contract' => null, 'files' => array('vendor/human-shield/human-shield-efb.php'), 'loader' => 'manual'),
		// PAY00 gateway add-ons: Payments\Registry::load_addons() loads them (efb_register_payment_gateways).
		'AdnMOL' => array('dir' => 'mollie', 'name' => 'Mollie', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/mollie/routes-efb.php'), 'loader' => 'payments'),
		'AdnPPG' => array('dir' => 'payplug', 'name' => 'Payplug', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/payplug/routes-efb.php'), 'loader' => 'payments'),
		'AdnTAP' => array('dir' => 'tappay', 'name' => 'Tap Payments', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/tappay/routes-efb.php'), 'loader' => 'payments'),
		'AdnOMS' => array('dir' => 'omise', 'name' => 'Omise', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/omise/routes-efb.php'), 'loader' => 'payments'),
		'AdnPTB' => array('dir' => 'paytabs', 'name' => 'PayTabs', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/paytabs/routes-efb.php'), 'loader' => 'payments'),
		'AdnPMB' => array('dir' => 'paymob', 'name' => 'Paymob', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/paymob/routes-efb.php'), 'loader' => 'payments'),
		'AdnXND' => array('dir' => 'xendit', 'name' => 'Xendit', 'package' => 3, 'contract' => 'payments', 'files' => array('vendor/xendit/routes-efb.php'), 'loader' => 'payments'),
		// SPLIT add-ons (§2): features moved out of core. Plans approved by owner (2026-11-14).
		'AdnCLC' => array('dir' => 'calc', 'name' => 'Numbers & Calculations', 'package' => 3, 'contract' => 'calc', 'files' => array('vendor/calc/routes-efb.php'), 'i18n' => array('vendor/calc/calc-phrases-efb.php', 'vendor/calc/languages/locales.json'), 'loader' => 'routes'),
		'AdnTSG' => array('dir' => 'typed-signature', 'name' => 'Typed Signature', 'package' => 3, 'contract' => 'typed_signature', 'files' => array('vendor/typed-signature/routes-efb.php'), 'i18n' => array('vendor/typed-signature/typed-signature-phrases-efb.php', 'vendor/typed-signature/languages/locales.json'), 'loader' => 'routes'),
		'AdnADR' => array('dir' => 'address', 'name' => 'Address & Postal', 'package' => 0, 'contract' => 'address', 'files' => array('vendor/address/routes-efb.php'), 'i18n' => array('vendor/address/address-phrases-efb.php', 'vendor/address/languages/locales.json'), 'loader' => 'routes'),
		'AdnGEO' => array('dir' => 'geo', 'name' => 'Visitor Country', 'package' => 3, 'contract' => 'smart_tags_ext', 'files' => array('vendor/geo/routes-efb.php'), 'i18n' => array('vendor/geo/geo-phrases-efb.php', 'vendor/geo/languages/locales.json'), 'loader' => 'routes'),
	);

	/** Features that left core (capabilities.split.moved): registry key => add-on code. */
	const MOVED = array(
		'field:calc' => 'AdnCLC', 'setting:number_format' => 'AdnCLC', 'setting:range_dual' => 'AdnCLC', 'setting:calc_option' => 'AdnCLC', 'setting:calc_price' => 'AdnCLC',
		'setting:esign_mode' => 'AdnTSG',
		'field:maps' => 'AdnADR', 'setting:addr_lookup' => 'AdnADR', 'setting:mask_postal' => 'AdnADR',
		'setting:advdate_availability' => 'AdnBEF', 'setting:booking_form' => 'AdnBEF',
		'field:stripe' => 'AdnSPF', 'field:paypal' => 'AdnPAP', 'field:persiaPay' => 'AdnPPF',
		'setting:smart_geo' => 'AdnGEO',
	);

	/** The catalogue with the filter applied (malformed entries dropped). @return array<string, array> */
	public static function all() {
		$map = self::ADDONS;
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_addon_catalog', $map);
			if (is_array($f)) {
				$map = array();
				foreach ($f as $code => $e) {
					if (!is_string($code) || !preg_match('/^Adn[A-Za-z]{2,5}$/', $code) || !is_array($e)) continue;
					$dir = isset($e['dir']) && is_string($e['dir']) && preg_match('/^[a-z0-9_-]{0,30}$/', $e['dir']) ? $e['dir'] : '';
					$pkg = isset($e['package']) ? (int) $e['package'] : 1;
					$map[$code] = array(
						'dir'      => $dir,
						'name'     => isset($e['name']) && is_string($e['name']) && $e['name'] !== '' ? $e['name'] : $code,
						'package'  => in_array($pkg, array(0, 1, 3), true) ? $pkg : 1,
						'contract' => isset($e['contract']) && is_string($e['contract']) && preg_match('/^[a-z_]{2,30}$/', $e['contract']) ? $e['contract'] : null,
						'files'    => self::paths(isset($e['files']) ? $e['files'] : array()),
						'i18n'     => self::paths(isset($e['i18n']) ? $e['i18n'] : array()),
						'loader'   => isset($e['loader']) && in_array($e['loader'], array('manual', 'payments', 'routes'), true) ? $e['loader'] : 'manual',
					);
				}
			}
		}
		foreach ($map as $code => $e) {
			if (!isset($map[$code]['i18n'])) $map[$code]['i18n'] = array();
		}
		return $map;
	}

	/** vendor/ paths only (no "..", no absolute path). */
	private static function paths($list) {
		$out = array();
		foreach ((array) $list as $p) {
			if (is_string($p) && preg_match('#^vendor/[A-Za-z0-9_./-]{1,200}$#', $p) && strpos($p, '..') === false) $out[] = $p;
		}
		return $out;
	}

	/** @return array<int, string> every add-on code, catalogue order. */
	public static function keys() {
		return array_keys(self::all());
	}

	/** @return array|null one entry. */
	public static function get($code) {
		$all = self::all();
		return isset($all[(string) $code]) ? $all[(string) $code] : null;
	}

	/** The plan that may install an add-on (0, 3, 1); an unknown code is Pro, as before. */
	public static function package($code) {
		$e = self::get($code);
		return $e ? (int) $e['package'] : 1;
	}

	/** The add-on's name for a message (a product name, not translated). */
	public static function name($code) {
		$e = self::get($code);
		return $e ? $e['name'] : (string) $code;
	}

	/** @return array<string, array<int, string>> code => files that prove it is installed (add-ons with files only). */
	public static function required_files() {
		$out = array();
		foreach (self::all() as $code => $e) if (!empty($e['files'])) $out[$code] = $e['files'];
		return $out;
	}

	/** @return array<string, array<int, string>> code => phrase files of the SPLIT add-ons (their own text domain). */
	public static function i18n_files() {
		$out = array();
		foreach (self::all() as $code => $e) if (!empty($e['i18n'])) $out[$code] = $e['i18n'];
		return $out;
	}

	/** The add-on code whose contract id this is (first match), or ''. */
	public static function addon_of_contract($contract) {
		foreach (self::all() as $code => $e) if ($e['contract'] === $contract && $e['loader'] === 'routes') return $code;
		return '';
	}

	/** Whether the flag of an add-on is on in the decoded settings (or its own option). */
	public static function flag_on($code, $settings = null) {
		if (is_object($settings) && isset($settings->$code)) return (int) $settings->$code >= 1;
		if (is_array($settings) && isset($settings[$code])) return (int) $settings[$code] >= 1;
		return function_exists('get_option') && (int) get_option('emsfb_addon_' . $code, 0) >= 1;
	}

	/** Whether this site's plan lets it use the add-on (install rule; a grandfathered site counts, SPLIT open question 2). */
	public static function plan_allows($code) {
		$pkg = self::package($code);
		if ($pkg === 0) return true;
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'addon_plan_allows_install_efb')) return (bool) $fn->addon_plan_allows_install_efb($code);
		$local = function_exists('get_option') ? (int) get_option('emsfb_pro', 2) : 2;
		return $local === 1 || ($local === 3 && $pkg === 3) || self::grandfathered($code);
	}

	/** SPLIT open question 2 (approved by owner 2026-11-14): sites that used a now-moved feature on Free keep it. */
	public static function grandfathered($code) {
		if (!function_exists('get_option')) return false;
		$scan = get_option('emsfb_split_scan', array());
		return is_array($scan) && isset($scan['grandfather']) && is_array($scan['grandfather']) && in_array((string) $code, $scan['grandfather'], true);
	}

	/** Why an add-on is not usable now: null (usable), 'addon' (not installed / off / files missing), 'plan', 'update' (contract too old). */
	public static function state($code, $settings = null) {
		$e = self::get($code);
		if (!$e) return 'addon';
		if (!self::flag_on($code, $settings)) return 'addon';
		if (!self::plan_allows($code)) return 'plan';
		if ($e['contract'] && $e['loader'] === 'routes' && function_exists('efb_addon_contracts')) {
			$c = efb_addon_contracts();
			if (isset($c[$e['contract']])) {
				if (!empty($c[$e['contract']]['outdated'])) return 'update';
				if (empty($c[$e['contract']]['active'])) return 'addon';
			}
		}
		return null;
	}

	/** Loaded add-ons of this request (code => file). */
	private static $loaded = array();

	/**
	 * Loads vendor/<dir>/routes-efb.php of every "routes" add-on whose flag is on, that this PHP can run and whose
	 * plan the site has. The file itself checks efb_addon_contracts() and registers everything on its hooks.
	 */
	public static function load($settings, $plugin_dir) {
		if (!is_object($settings) && !is_array($settings)) return array();
		foreach (self::all() as $code => $e) {
			if ($e['loader'] !== 'routes' || $e['dir'] === '' || isset(self::$loaded[$code])) continue;
			if (!self::flag_on($code, $settings)) continue;
			if (function_exists('emsfb_is_addon_compatible_efb') && !emsfb_is_addon_compatible_efb($code)) continue;
			if (!self::plan_allows($code)) continue;
			$f = rtrim((string) $plugin_dir, '/') . '/vendor/' . $e['dir'] . '/routes-efb.php';
			if (!is_readable($f)) continue;
			self::$loaded[$code] = $f;
			require_once $f;
		}
		return self::$loaded;
	}

	/** @return array<string, string> add-ons loaded by load() in this request. */
	public static function loaded() {
		return self::$loaded;
	}

	/** Tests only. */
	public static function reset_loaded() {
		self::$loaded = array();
	}

	/** efb_admin_localize_vars: the JS mirror (efb_addon_required_package_efb() reads efb_var.addon_catalog). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['addon_catalog'] = self::js_mirror();
		// SPLIT §4.2: the keys each add-on owns (efbBuilderApi refuses them with addon_required while it is absent).
		if (class_exists('\\Emsfb\\Addon_Keys')) $vars['addon_keys'] = Addon_Keys::manifest();
		return $vars;
	}

	/** code => {package, name, contract} for the builder and the Add-ons page. */
	public static function js_mirror() {
		$out = array();
		foreach (self::all() as $code => $e) $out[$code] = array('package' => (int) $e['package'], 'name' => $e['name'], 'contract' => $e['contract']);
		return $out;
	}

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 9, 2);
	}
}
