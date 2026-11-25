<?php
/**
 * SPLIT §4.4: one table of every add-on core knows (code, folder, name, plan, contract, the files that prove it
 * is installed). get_all_addon_keys_efb(), addon_required_package_efb(), get_addon_required_files_efb(),
 * Emsfb_Addon_Compatibility::addon_requirements() and the builder's JS mirror (efb_var.addon_catalog) all read
 * it, so the five lists cannot drift. Filter efb_addon_catalog adds an entry (F12b CAPTCHA add-ons).
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
		'AdnSMF' => array('dir' => 'logic', 'name' => 'Conditional Logic', 'package' => 3, 'contract' => 'logic', 'files' => array('vendor/logic/logic/class-Emsfb-logic-validator.php'), 'loader' => 'manual'),
		'AdnPLF' => array('dir' => '', 'name' => 'AdnPLF', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		'AdnMSF' => array('dir' => '', 'name' => 'AdnMSF', 'package' => 1, 'contract' => null, 'files' => array(), 'loader' => 'manual'),
		// SPLIT §9.2: the booking add-on (Pro). Before SPLIT the key was listed without any add-on (§20 item 6).
		'AdnBEF' => array('dir' => 'booking', 'name' => 'Booking', 'package' => 1, 'contract' => 'booking', 'files' => array('vendor/booking/routes-efb.php'), 'i18n' => array('vendor/booking/booking-phrases-efb.php', 'vendor/booking/languages/locales.json'), 'loader' => 'routes'),
		'AdnPDP' => array('dir' => 'persiadatepicker', 'name' => 'Persian Date Picker', 'package' => 1, 'contract' => null, 'files' => array('vendor/persiadatepicker/persiandate.php'), 'loader' => 'manual'),
		'AdnADP' => array('dir' => 'arabicdatepicker', 'name' => 'Arabic Date Picker', 'package' => 1, 'contract' => null, 'files' => array('vendor/arabicdatepicker/arabicdate.php'), 'loader' => 'manual'),
		'AdnATF' => array('dir' => 'autofill', 'name' => 'Auto-Populate', 'package' => 1, 'contract' => null, 'files' => array('vendor/autofill/autofillefb.php'), 'loader' => 'manual'),
		'AdnTLG' => array('dir' => 'telegram', 'name' => 'Telegram Notifications', 'package' => 1, 'contract' => null, 'files' => array('vendor/telegram/telegram-new-efb.php'), 'loader' => 'manual'),
		'AdnGoS' => array('dir' => 'googlesheet', 'name' => 'Google Sheets', 'package' => 1, 'contract' => null, 'files' => array('vendor/googlesheet/class-Emsfb-googlesheet.php'), 'loader' => 'manual'),
		'AdnHSH' => array('dir' => 'human-shield', 'name' => 'Human Shield', 'package' => 1, 'contract' => null, 'files' => array('vendor/human-shield/human-shield-efb.php'), 'loader' => 'manual'),
		// SET-01 phase 1: AdnHSH is "Human Shield" (its menu and screen too; "Form Security & Spam Protection" overlapped
		// core's Captchas and spam settings by name).
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
		// F12b: the CAPTCHA providers (contract captcha_provider 2, Free Plus and Pro). Codes approved by owner (2026-11-14).
		'AdnCTS' => array('dir' => 'turnstile', 'name' => 'Cloudflare Turnstile', 'package' => 3, 'contract' => 'captcha_provider', 'files' => array('vendor/turnstile/routes-efb.php'), 'i18n' => array('vendor/turnstile/turnstile-phrases-efb.php', 'vendor/turnstile/languages/locales.json'), 'loader' => 'routes'),
		'AdnHCP' => array('dir' => 'hcaptcha', 'name' => 'hCaptcha', 'package' => 3, 'contract' => 'captcha_provider', 'files' => array('vendor/hcaptcha/routes-efb.php'), 'i18n' => array('vendor/hcaptcha/hcaptcha-phrases-efb.php', 'vendor/hcaptcha/languages/locales.json'), 'loader' => 'routes'),
		'AdnALT' => array('dir' => 'altcha', 'name' => 'ALTCHA', 'package' => 3, 'contract' => 'captcha_provider', 'files' => array('vendor/altcha/routes-efb.php'), 'i18n' => array('vendor/altcha/altcha-phrases-efb.php', 'vendor/altcha/languages/locales.json'), 'loader' => 'routes'),
		'AdnQCP' => array('dir' => 'question-captcha', 'name' => 'Question CAPTCHA', 'package' => 3, 'contract' => 'captcha_provider', 'files' => array('vendor/question-captcha/routes-efb.php'), 'i18n' => array('vendor/question-captcha/question-captcha-phrases-efb.php', 'vendor/question-captcha/languages/locales.json'), 'loader' => 'routes'),
		'AdnFRC' => array('dir' => 'friendly-captcha', 'name' => 'Friendly Captcha', 'package' => 3, 'contract' => 'captcha_provider', 'files' => array('vendor/friendly-captcha/routes-efb.php'), 'i18n' => array('vendor/friendly-captcha/friendly-captcha-phrases-efb.php', 'vendor/friendly-captcha/languages/locales.json'), 'loader' => 'routes'),
		// F20: the customer portal is not an add-on: it is the separate, free plugin EFB Customer Portal (efb-customer-portal),
		// on every plan (portal contract 1.1; capabilities.portal_core.plugin describes it). No catalogue entry, no plan gate.
	);

	/**
	 * HELP-01: the help page of each add-on (help_url). 'en' is the page on whitestudio.team, where {lang} becomes the
	 * site's language subdomain (de. on German sites, ar. on Arabic ones, nothing otherwise; the same rule as
	 * lan_subdomain_wsteam_efb() in admin-efb.js); 'fa' is the page on easyformbuilder.ir for fa_IR sites, which get the
	 * English page when there is none. An add-on missing here has no page yet: its Help button is hidden (owner decision
	 * 8, 2026-11-19), never the documentation index. Every address was checked to answer 200 with its own page
	 * (tests/release-help-links.js checks them again before a release).
	 */
	const HELP = array(
		'AdnSPF' => array('en' => 'https://{lang}whitestudio.team/document/how-to-setup-and-use-the-stripe-on-easy-form-builder/'),
		'AdnOF'  => array('en' => 'https://{lang}whitestudio.team/advanced-addons/offline-forms-addon/', 'fa' => 'https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/%d9%81%d8%b9%d8%a7%d9%84-%da%a9%d8%b1%d8%af%d9%86-%d8%ad%d8%a7%d9%84%d8%aa-%d8%a2%d9%81%d9%84%d8%a7%db%8c%d9%86-%d9%81%d8%b1%d9%85/'),
		// Persia Payment works with Iranian gateways only: a Persian page and no English one.
		'AdnPPF' => array('fa' => 'https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/%d8%b3%d8%a7%d8%ae%d8%aa-%d9%81%d8%b1%d9%85-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d8%a7%d8%aa%d8%b5%d8%a7%d9%84-%d8%af%d8%b1%da%af%d8%a7%d9%87-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3/'),
		'AdnSS'  => array('en' => 'https://{lang}whitestudio.team/document/settingup-sms-notifications-wordpress-easy-form-builder/', 'fa' => 'https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/%d8%aa%d9%86%d8%b8%db%8c%d9%85-%d8%a7%d8%b7%d9%84%d8%a7%d8%b9-%d8%b1%d8%b3%d8%a7%d9%86%db%8c-%d9%be%db%8c%d8%a7%d9%85%da%a9-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2/'),
		'AdnPAP' => array('en' => 'https://{lang}whitestudio.team/document/how-to-setup-paypal-payment-in-easy-form-builder/'),
		'AdnSMF' => array('en' => 'https://{lang}whitestudio.team/advanced-addons/easy-form-builder-conditional-logic-complete-guide/'),
		'AdnADP' => array('en' => 'https://{lang}whitestudio.team/advanced-addons/how-to-install-islamic-date-in-easy-form-builder-plugin/'),
		'AdnATF' => array('en' => 'https://{lang}whitestudio.team/advanced-addons/wordpress-easy-form-builder-auto-populate/'),
		'AdnTLG' => array('en' => 'https://{lang}whitestudio.team/document/wordpress-form-telegram-notifications/'),
	);

	/** HELP-01: help topics of the builder that are not an add-on: topic => add-on code (its page) or a page of its own. */
	const HELP_TOPICS = array(
		'condi'        => 'AdnSMF',
		'pickupByUser' => array('en' => 'https://{lang}whitestudio.team/document/how-to-install-and-use-the-location-picker-geolocation-with-easy-form-builder/', 'fa' => 'https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/%da%86%da%af%d9%88%d9%86%d9%87-%d9%85%da%a9%d8%a7%d9%86%db%8c%d8%a7%d8%a8-%d8%a7%d9%86%d8%aa%d8%ae%d8%a7%d8%a8-%da%af%d8%b1-%d9%86%d9%82%d8%b4%d9%87-%d9%81%d8%b1%d9%85-%d8%a7%d9%81%d8%b2%d9%88%d9%86/'),
	);

	/**
	 * SET-01 phase 1: the add-on settings screens core links to (add-on code => its admin page slug under Easy Form
	 * Builder's menu). efb_var.addon_catalog[code].screen is that screen's address while the add-on registered it (it is
	 * installed and on), else ''. Settings › Captchas links to Human Shield's screen this way.
	 */
	const SCREENS = array(
		'AdnHSH' => 'Emsfb_human_shield_efb',
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
						'help'     => self::help_map(isset($e['help']) ? $e['help'] : (isset(self::HELP[$code]) ? self::HELP[$code] : array())),
					);
				}
			}
		}
		foreach ($map as $code => $e) {
			if (!isset($map[$code]['i18n'])) $map[$code]['i18n'] = array();
			if (!isset($map[$code]['help'])) $map[$code]['help'] = isset(self::HELP[$code]) ? self::HELP[$code] : array();
		}
		return $map;
	}

	/** HELP-01: a help entry of the filter kept only as https addresses on the two documentation sites ({lang} allowed). */
	private static function help_map($h) {
		$out = array();
		if (is_string($h)) $h = array('en' => $h);
		if (!is_array($h)) return $out;
		foreach (array('en', 'fa') as $k) {
			if (isset($h[$k]) && is_string($h[$k]) && preg_match('#^https://(\{lang\})?(whitestudio\.team|easyformbuilder\.ir)/[A-Za-z0-9%_./?=&\#-]*$#D', $h[$k])) $out[$k] = $h[$k];
		}
		return $out;
	}

	/** The language subdomain of whitestudio.team for a locale (lan_subdomain_wsteam_efb() in admin-efb.js). */
	private static function lang_subdomain($locale) {
		if (in_array($locale, array('de_DE', 'de_AT'), true)) return 'de.';
		if (in_array($locale, array('ar', 'ary', 'arq'), true)) return 'ar.';
		return '';
	}

	/** The site's locale (efb_var.language is get_locale() too). */
	private static function locale() {
		return function_exists('get_locale') ? (string) get_locale() : 'en_US';
	}

	/** HELP-01: the address of a help entry for a locale: fa_IR → the Persian page, else (or without one) the English page. */
	private static function resolve_help(array $h, $locale) {
		if ($locale === 'fa_IR' && !empty($h['fa'])) return $h['fa'];
		if (empty($h['en'])) return '';
		return str_replace('{lang}', $locale === 'fa_IR' ? '' : self::lang_subdomain($locale), $h['en']);
	}

	/**
	 * HELP-01: the help page of an add-on for this site's language (or $locale), '' when it has none (Help is hidden then).
	 * @return string
	 */
	public static function help_url($code, $locale = null) {
		$e = self::get($code);
		if (!$e || empty($e['help'])) return '';
		return self::resolve_help($e['help'], $locale === null ? self::locale() : (string) $locale);
	}

	/** HELP-01: the builder's help topics that are not add-ons (efb_var.help_topics): topic => address ('' when none). */
	public static function help_topics($locale = null) {
		$locale = $locale === null ? self::locale() : (string) $locale;
		$out = array();
		foreach (self::HELP_TOPICS as $topic => $t) {
			$out[$topic] = is_string($t) ? self::help_url($t, $locale) : self::resolve_help(self::help_map($t), $locale);
		}
		return $out;
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

	/**
	 * QA4-005: whether the files that prove the add-on is installed are on disk (an add-on without such files, true).
	 * A core update or a reinstall replaces vendor/; the flag stays on while the files are gone, and such an add-on is
	 * missing ("Install"), not outdated ("Update").
	 */
	public static function files_present($code) {
		$e = self::get($code);
		if (!$e || empty($e['files'])) return true;
		$dir = defined('EMSFB_PLUGIN_DIRECTORY') ? rtrim((string) EMSFB_PLUGIN_DIRECTORY, '/') . '/' : '';
		if ($dir === '') return true;
		foreach ($e['files'] as $f) {
			if (!is_file($dir . $f)) return false;
		}
		return true;
	}

	/** Why an add-on is not usable now: null (usable), 'addon' (not installed / off / files missing), 'plan', 'update' (contract too old). */
	public static function state($code, $settings = null) {
		$e = self::get($code);
		if (!$e) return 'addon';
		if (!self::flag_on($code, $settings)) return 'addon';
		if (!self::files_present($code)) return 'addon'; // QA4-005
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
		// HELP-01: link_url_emsFormBuilder() reads the help pages from here (an add-on without one has help '').
		$vars['help_topics'] = self::help_topics();
		// SPLIT §4.2: the keys each add-on owns (efbBuilderApi refuses them with addon_required while it is absent).
		if (class_exists('\\Emsfb\\Addon_Keys')) $vars['addon_keys'] = Addon_Keys::manifest();
		return $vars;
	}

	/** code => {package, name, contract, help} for the builder and the Add-ons page (help: this site's language, '' = none). */
	public static function js_mirror($locale = null) {
		$out = array();
		$locale = $locale === null ? self::locale() : (string) $locale;
		foreach (self::all() as $code => $e) $out[$code] = array('package' => (int) $e['package'], 'name' => $e['name'], 'contract' => $e['contract'], 'help' => empty($e['help']) ? '' : self::resolve_help($e['help'], $locale), 'screen' => self::screen_url($code));
		return $out;
	}

	/** SET-01: the address of an add-on's own settings screen while it is registered (the add-on is on), else ''. */
	public static function screen_url($code) {
		if (!isset(self::SCREENS[$code]) || !function_exists('admin_url')) return '';
		global $submenu;
		foreach (isset($submenu['Emsfb']) && is_array($submenu['Emsfb']) ? $submenu['Emsfb'] : array() as $item) {
			if (isset($item[2]) && $item[2] === self::SCREENS[$code]) return admin_url('admin.php?page=' . self::SCREENS[$code]);
		}
		return '';
	}

	/* ------------------------------------------------------------------ SET-01: the add-on screens' shared parts */

	private static $screen_css = false;

	/** The shared parts' look, printed once (an add-on screen does not always load the plugin's stylesheets). */
	private static function screen_css() {
		if (self::$screen_css) return '';
		self::$screen_css = true;
		return '<style id="efb-addon-screen-css">'
			. '.efb-addon-screen .efb-addon-back{margin:8px 0 0}.efb-addon-screen .efb-addon-back a{min-height:44px;display:inline-flex;align-items:center;gap:4px;text-decoration:none}'
			. '.efb-addon-screen .efb-addon-back a:hover,.efb-addon-screen .efb-addon-back a:focus{text-decoration:underline}'
			. '.efb-addon-screen h1,.efb-addon-screen .efb-addon-desc{unicode-bidi:plaintext;text-align:start}.efb-addon-screen .efb-addon-desc{max-width:52rem;margin-block:4px 16px}'
			. '.efb-addon-screen .page-title-action{min-height:30px;display:inline-flex;align-items:center;gap:4px}'
			. '.efb-addon-savebar{position:sticky;bottom:0;z-index:5;display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-start;gap:12px;margin-block:16px 0;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 -2px 8px rgba(0,0,0,.06)}'
			. '.efb-addon-savebar .button-primary{min-height:36px}.efb-addon-savebar-note{unicode-bidi:plaintext;color:#50575e}'
			. '@media (max-width:782px){.efb-addon-savebar .button-primary{min-height:44px}}'
			. '</style>';
	}

	/**
	 * SET-01 phase 1 (ADD-01 uses it): the header of an add-on's settings screen in Easy Form Builder's screen form, the
	 * WordPress one core's Payments and CAPTCHA screens have: a link back, the title (h1 + wp-header-end, so WordPress puts
	 * notices under it), its actions (page-title-action), Help (the add-on's own page from HELP; none when it has none)
	 * and one line on what the screen does. Open it inside the screen's <div class="wrap efb-addon-screen">.
	 *
	 * args: title (required), code (add-on code: Help), back_url, back_label, desc, actions (array of {url, label, attrs}).
	 * @return string escaped HTML
	 */
	public static function screen_header(array $a) {
		$t = function ($s) { return function_exists('esc_html') ? esc_html($s) : htmlspecialchars((string) $s, ENT_QUOTES); };
		$u = function ($s) { return function_exists('esc_url') ? esc_url($s) : htmlspecialchars((string) $s, ENT_QUOTES); };
		$h = self::screen_css();
		if (!empty($a['back_url'])) {
			$back = isset($a['back_label']) && $a['back_label'] !== '' ? $a['back_label'] : (function_exists('__') ? __('Back to Settings', 'easy-form-builder') : 'Back to Settings');
			$h .= '<p class="efb-addon-back"><a href="' . $u($a['back_url']) . '"><span aria-hidden="true">' . (function_exists('is_rtl') && is_rtl() ? '&rarr;' : '&larr;') . '</span> ' . $t($back) . '</a></p>';
		}
		$h .= '<h1 class="wp-heading-inline">' . $t(isset($a['title']) ? $a['title'] : '') . '</h1>';
		foreach (isset($a['actions']) && is_array($a['actions']) ? $a['actions'] : array() as $act) {
			if (empty($act['url']) || !isset($act['label'])) continue;
			$h .= ' <a class="page-title-action" href="' . $u($act['url']) . '">' . $t($act['label']) . '</a>';
		}
		$help = !empty($a['code']) ? self::help_url($a['code']) : '';
		if ($help !== '') {
			/* translators: SET-01: the Help link in the header of an add-on's settings screen */
			$label = function_exists('__') ? __('Help', 'easy-form-builder') : 'Help';
			/* translators: SET-01: for screen readers, after a link that opens a new tab */
			$tab = function_exists('__') ? __('(opens in a new tab)', 'easy-form-builder') : '(opens in a new tab)';
			$h .= ' <a class="page-title-action efb-addon-help" href="' . $u($help) . '" target="_blank" rel="noopener noreferrer">' . $t($label) . '<span class="screen-reader-text"> ' . $t($tab) . '</span></a>';
		}
		$h .= '<hr class="wp-header-end">';
		if (!empty($a['desc'])) $h .= '<p class="description efb-addon-desc">' . $t($a['desc']) . '</p>';
		return $h;
	}

	/**
	 * SET-01 phase 1: the save bar of an add-on's settings screen: WordPress' primary button, sticky at the bottom of
	 * the screen while the form is longer than the window (Save stays in sight), and a status line (role status) for
	 * the screen's own script ("Saved", an error). Put it inside the screen's <form>.
	 *
	 * args: label (default "Save Changes"), name / value of the button, id, note (a line beside it).
	 * @return string escaped HTML
	 */
	public static function save_bar(array $a = array()) {
		$t = function ($s) { return function_exists('esc_html') ? esc_html($s) : htmlspecialchars((string) $s, ENT_QUOTES); };
		$at = function ($s) { return function_exists('esc_attr') ? esc_attr($s) : htmlspecialchars((string) $s, ENT_QUOTES); };
		/* translators: SET-01: the save button at the bottom of an add-on's settings screen */
		$label = isset($a['label']) && $a['label'] !== '' ? $a['label'] : (function_exists('__') ? __('Save Changes', 'easy-form-builder') : 'Save Changes');
		$id = isset($a['id']) && $a['id'] !== '' ? $a['id'] : 'efb-addon-save';
		$h = self::screen_css() . '<div class="efb-addon-savebar">';
		$h .= '<button type="submit" class="button button-primary" id="' . $at($id) . '"' . (!empty($a['name']) ? ' name="' . $at($a['name']) . '" value="' . $at(isset($a['value']) ? $a['value'] : '1') . '"' : '') . '>' . $t($label) . '</button>';
		$h .= '<span class="efb-addon-savebar-note" id="' . $at($id) . '-status" role="status" aria-live="polite">' . (isset($a['note']) ? $t($a['note']) : '') . '</span>';
		return $h . '</div>';
	}

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 9, 2);
		// B4: the admin screens' shared parts (Emsfb\Notices: phrases of the builder panel, the notice manager). Loaded
		// from here because this file is always loaded, whatever the order of class-Emsfb.php.
		if (!class_exists('\\Emsfb\\Notices') && is_readable(__DIR__ . '/class-Emsfb-notices.php')) require_once __DIR__ . '/class-Emsfb-notices.php';
		if (class_exists('\\Emsfb\\Notices')) Notices::register();
		// SET-01: the add-on screens' shared look (efb-addon-screen, efb-addon-back, efb-addon-desc, efb-addon-savebar) on
		// every screen of the plugin's menu, so an add-on screen that prints those classes looks like core's.
		if (function_exists('add_action')) add_action('admin_head', array(__CLASS__, 'print_screen_css'));
	}

	/** admin_head: the shared parts' style on the plugin's screens (once; screen_header() then prints none). */
	public static function print_screen_css() {
		$s = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$s || stripos((string) $s->id, 'emsfb') === false) return;
		echo self::screen_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed stylesheet
	}
}
