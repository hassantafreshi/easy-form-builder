<?php
/**
 * F02: verify a mobile number or an e-mail address with a one-time code before the form is sent.
 * Spec: docs/feature-gaps/F02-verification-otp.fa.md.
 *
 * One provider-neutral engine, two channels:
 *  - email: wp_mail() through the plugin's e-mail template (state otpCode); no add-on needed;
 *  - sms:   through the WP SMS plugin only (owner rule): the SMS add-on (AdnSS) registers a bridge to
 *           wp_sms_send() through the efb_verify_providers filter; the gateway and its credentials
 *           live in WP SMS. Core ships no sender. Without the add-on or WP SMS the SMS channel is
 *           unavailable, and a form that asks for an SMS code refuses the submission with a clear
 *           sentence (fail-closed, never a silent skip).
 *
 * Flow: POST Emsfb/v1/verify/send (a live runtime token is required: a cached page's sid is shared
 * between visitors) → the code is made with random_int(), only HMAC(challenge_id|code) is stored,
 * the destination only as an HMAC plus a mask → POST verify/check (attempts counted atomically before
 * the compare) → a signed token bound to the form, the field, the destination's hash, the sid and an
 * expiry → the submit handler checks it against exactly the value being stored
 * (validate_submission(), nothing changes) and insert_message_db() consumes it once, atomically
 * (consume(): pending → verified → consumed; release() gives it back when the insert fails).
 *
 * Limits are F11's atomic counters (Rate_Limit, table emsfb_vrfy_rl_): per destination, per IP (the
 * form's F13 IP mode), per session, per form, per number prefix and a daily budget for the site;
 * a refused counter answers "no" and a database error answers "no" (fail-closed).
 *
 * Settings: option emsfb_verify_settings (not emsfb_setting: that object is localized in full to
 * the admin pages and rebuilt by its own save path; here every value is clamped in one place).
 * EFB stores no SMS credential: WP SMS keeps its own.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Verification {

	const TABLE = 'emsfb_vrfy_';
	const OPTION = 'emsfb_verify_settings';
	const STATUSES = array('pending', 'verified', 'consumed', 'expired', 'locked', 'superseded', 'failed');
	const CHANNELS = array('email', 'sms');

	/** Field keys (spec §4.1); all optional, absent = today's behaviour. */
	const MOBILE_KEYS = array('otp_enabled', 'otp_channel', 'otp_required_when', 'otp_countries', 'otp_btn_label');
	const EMAIL_KEYS = array('email_confirm_mode', 'email_retype_label', 'email_otp_required_when', 'email_otp_subject', 'email_otp_body');
	const EMAIL_MODES = array('none', 'retype', 'otp', 'retype_otp');

	/** Plan of each part (decision: retype free, e-mail code Free Plus, SMS code Pro like SMS notifications; filter efb_verify_plan). */
	const PLAN = array('retype' => 'free', 'email' => 'free_plus', 'sms' => 'pro');

	/** English texts (the phrases live in functions.php; these are the fallbacks of a stripped environment). */
	const TEXTS = array(
		'vrfSend'         => 'Send code',
		'vrfSending'      => 'Sending code…',
		'vrfSentSms'      => 'We sent a %1$s-digit code to %2$s.',
		'vrfSentEmail'    => 'We emailed a %1$s-digit code to %2$s. Check your spam folder too.',
		'vrfCodeLabel'    => 'Verification code',
		'vrfVerify'       => 'Verify',
		'vrfVerified'     => 'Verified',
		'vrfResendIn'     => 'Resend in %s',
		'vrfResend'       => 'Resend code',
		'vrfEdit'         => 'Change',
		'vrfWrong'        => 'That code is not correct. %s attempts left.',
		'vrfExpired'      => 'The code has expired. Please request a new one.',
		'vrfLocked'       => 'Too many attempts. Please try again later.',
		'vrfCountry'      => 'Verification codes cannot be sent to this country.',
		'vrfLimit'        => 'Too many code requests. Please try again in %s.',
		'vrfFailed'       => 'We could not send the code. Please try again shortly.',
		'vrfRequired'     => 'Please verify %s before continuing.',
		'vrfUnavailable'  => 'Verification is not available right now. Please contact the site owner.',
		'vrfEmailRetype'  => 'Confirm email',
		'vrfEmailMismatch' => 'The email addresses do not match.',
		'vrfDestination'  => 'Please enter a valid number or address first.',
		'vrfBlocked'      => 'This request was blocked. Please try again later.',
		'vrfRefresh'      => 'This form was updated. Please refresh the page.',
		'vrfSmsBody'      => '%1$s is your %2$s verification code.',
		'vrfEmailSubject' => 'Your verification code for %s',
		'vrfEmailBody'    => 'Enter this code to continue:',
		'vrfEmailIgnore'  => 'If you did not request this, you can ignore this email.',
		'vrfEmailExpires' => 'The code expires in %s minutes.',
	);

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	/** Test hook: a callable($to, $subject, $html, $code) used instead of the e-mail handler. */
	public static $mailer = null;

	private static $ready = null;
	private static $settings_cache = null;
	private static $providers_cache = null;

	public static function now() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function utc($offset = 0) {
		return gmdate('Y-m-d H:i:s', self::now() + (int) $offset);
	}

	/* ================================================================ wiring */

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'), 20);
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'cleanup'), 44, 0);
		add_action('emsfb_operation_cleanup', array(__CLASS__, 'cleanup_light'), 20, 0);
		add_action('efb_entries_purged', array(__CLASS__, 'on_entries_purged'), 10, 4);
		add_action('efb_form_deleted', array(__CLASS__, 'on_form_deleted'), 10, 1);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 22, 2);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'admin_vars'), 20, 1);
		add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
		add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
		add_action('wp_ajax_efb_verify_settings_Emsfb', array(__CLASS__, 'ajax_settings'));
		add_action('wp_ajax_efb_verify_test_send_Emsfb', array(__CLASS__, 'ajax_test_send'));
	}

	public static function routes() {
		$perm = class_exists('\\Emsfb\\_Public') && _Public::$instance_efb ? array(_Public::$instance_efb, 'check_nonce_permission_efb') : '__return_false';
		register_rest_route('Emsfb/v1', 'verify/send', array('methods' => 'POST', 'callback' => array(__CLASS__, 'route_send'), 'permission_callback' => $perm));
		register_rest_route('Emsfb/v1', 'verify/check', array('methods' => 'POST', 'callback' => array(__CLASS__, 'route_check'), 'permission_callback' => $perm));
		do_action('efb_register_verification_rest_routes', class_exists('\\Emsfb\\_Public') ? _Public::$instance_efb : null);
	}

	/* ================================================================ settings */

	public static function defaults() {
		return array(
			'sms' => array(
				'provider' => 'wpsms', 'code_length' => 6, 'ttl' => 300, 'resend_cooldowns' => array(60, 120, 300, 600),
				'max_sends_dest_hour' => 3, 'max_sends_dest_day' => 5, 'max_attempts' => 5,
				'allowed_countries' => self::default_countries(), 'message' => '',
				'webotp' => 1, 'daily_budget' => 300,
			),
			'email' => array(
				'code_length' => 6, 'ttl' => 600, 'resend_cooldowns' => array(60, 120, 300, 600),
				'max_sends_dest_hour' => 3, 'max_sends_dest_day' => 10, 'max_attempts' => 5,
				'code_in_subject' => 0, 'blocked_domains' => array(), 'daily_budget' => 1000,
			),
			'limits' => array(
				'ip_hour_sms' => 5, 'ip_day_sms' => 10, 'ip_hour_email' => 10, 'ip_day_email' => 30,
				'sid_destinations' => 3, 'sid_sends' => 6, 'form_hour_sms' => 60, 'prefix_hour_sms' => 10,
				'fail_dest_day' => 10, 'fail_ip_hour' => 30,
			),
			'token_ttl' => 1800,
		);
	}

	/** The site language's country (fa_IR → ir, en_US → us, de_DE → de); [] when the locale names none. */
	public static function default_countries() {
		$loc = function_exists('get_locale') ? (string) get_locale() : 'en_US';
		if (preg_match('/^[a-z]{2,3}_([A-Z]{2})/', $loc, $m)) {
			$cc = strtolower($m[1]);
			return isset(self::dial_codes()[$cc]) ? array($cc) : array();
		}
		return array();
	}

	private static function int_in($v, $min, $max, $dflt) {
		if (is_string($v)) $v = self::digits_only($v);
		if (!is_numeric($v)) return $dflt;
		return max($min, min($max, (int) $v));
	}

	private static function iso_list($v) {
		if (is_string($v)) $v = preg_split('/[\s,]+/', $v);
		$out = array();
		foreach ((array) $v as $c) {
			$c = strtolower(trim((string) $c));
			if (isset(self::dial_codes()[$c])) $out[] = $c;
		}
		return array_values(array_unique($out));
	}

	/** Every value clamped to its range (spec §4.2); unknown keys are dropped. */
	public static function clamp($in) {
		$d = self::defaults();
		$in = is_array($in) ? $in : array();
		$out = $d;
		foreach (array('sms', 'email') as $ch) {
			$s = isset($in[$ch]) && is_array($in[$ch]) ? $in[$ch] : array();
			$o = $d[$ch];
			$o['code_length'] = self::int_in($s['code_length'] ?? $o['code_length'], 6, 8, $o['code_length']);
			$o['ttl'] = self::int_in($s['ttl'] ?? $o['ttl'], 120, 600, $o['ttl']);
			$cd = isset($s['resend_cooldowns']) ? (is_string($s['resend_cooldowns']) ? preg_split('/[\s,]+/', $s['resend_cooldowns']) : (array) $s['resend_cooldowns']) : $o['resend_cooldowns'];
			$cd = array_values(array_filter(array_map(function ($x) { return self::int_in($x, 30, 3600, 0); }, $cd)));
			$o['resend_cooldowns'] = $cd ? array_slice($cd, 0, 8) : $d[$ch]['resend_cooldowns'];
			$o['max_sends_dest_hour'] = self::int_in($s['max_sends_dest_hour'] ?? $o['max_sends_dest_hour'], 1, 20, $o['max_sends_dest_hour']);
			$o['max_sends_dest_day'] = self::int_in($s['max_sends_dest_day'] ?? $o['max_sends_dest_day'], 1, 50, $o['max_sends_dest_day']);
			$o['max_attempts'] = self::int_in($s['max_attempts'] ?? $o['max_attempts'], 1, 10, $o['max_attempts']);
			$o['daily_budget'] = self::int_in($s['daily_budget'] ?? $o['daily_budget'], 1, 100000, $o['daily_budget']);
			if ($ch === 'sms') {
				// The sender: 'wpsms' (the WP SMS bridge of the SMS add-on) unless another add-on registered one.
				$p = isset($s['provider']) && is_string($s['provider']) ? strtolower(preg_replace('/[^a-z0-9_]/i', '', $s['provider'])) : 'wpsms';
				$o['provider'] = $p === '' ? 'wpsms' : substr($p, 0, 20);
				$o['allowed_countries'] = array_key_exists('allowed_countries', $s) ? self::iso_list($s['allowed_countries']) : $o['allowed_countries'];
				$o['message'] = isset($s['message']) && is_string($s['message']) ? mb_substr(str_replace(array('<', '>'), '', trim($s['message'])), 0, 300) : '';
				$o['webotp'] = self::int_in($s['webotp'] ?? 1, 0, 1, 1);
			} else {
				$o['code_in_subject'] = self::int_in($s['code_in_subject'] ?? 0, 0, 1, 0);
				$bd = isset($s['blocked_domains']) ? (is_string($s['blocked_domains']) ? preg_split('/[\s,]+/', $s['blocked_domains']) : (array) $s['blocked_domains']) : array();
				$o['blocked_domains'] = array_values(array_unique(array_filter(array_map(function ($x) { return strtolower(preg_replace('/[^a-z0-9.\-*]/i', '', (string) $x)); }, $bd), 'strlen')));
			}
			$out[$ch] = $o;
		}
		$l = isset($in['limits']) && is_array($in['limits']) ? $in['limits'] : array();
		foreach ($d['limits'] as $k => $v) {
			$out['limits'][$k] = self::int_in($l[$k] ?? $v, 1, 10000, $v);
		}
		$out['token_ttl'] = self::int_in($in['token_ttl'] ?? $d['token_ttl'], 300, 7200, $d['token_ttl']);
		return $out;
	}

	public static function settings() {
		if (self::$settings_cache !== null) return self::$settings_cache;
		$raw = function_exists('get_option') ? get_option(self::OPTION, array()) : array();
		if (is_string($raw)) $raw = json_decode($raw, true);
		return self::$settings_cache = self::clamp(is_array($raw) ? $raw : array());
	}

	public static function save_settings(array $in) {
		$c = self::clamp($in);
		if (function_exists('update_option')) update_option(self::OPTION, $c, false);
		self::$settings_cache = null;
		self::$providers_cache = null;
		return $c;
	}

	public static function reset_cache() {
		self::$settings_cache = null;
		self::$providers_cache = null;
		self::$ready = null;
	}

	/** One limit value, overridable: apply_filters('efb_verify_limit', $value, $name, $ctx). */
	public static function limit($name, $value, array $ctx = array()) {
		$v = function_exists('apply_filters') ? apply_filters('efb_verify_limit', (int) $value, (string) $name, $ctx) : $value;
		return is_numeric($v) ? (int) $v : (int) $value;
	}

	/* ================================================================ texts */

	public static function t($key, $lanText = null) {
		if (is_array($lanText) && isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '') return $lanText[$key];
		static $cache = array();
		if (!isset($cache[$key])) {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			$got = ($fn && method_exists($fn, 'text_efb')) ? (array) $fn->text_efb(array($key)) : array();
			$cache[$key] = isset($got[$key]) && is_string($got[$key]) && $got[$key] !== '' ? $got[$key] : (isset(self::TEXTS[$key]) ? self::TEXTS[$key] : $key);
		}
		return $cache[$key];
	}

	/** The phrase keys a visitor's page and the submit handler need. */
	public static function public_keys() {
		return array('vrfSend', 'vrfSending', 'vrfSentSms', 'vrfSentEmail', 'vrfCodeLabel', 'vrfVerify', 'vrfVerified', 'vrfResendIn', 'vrfResend', 'vrfEdit',
			'vrfWrong', 'vrfExpired', 'vrfLocked', 'vrfCountry', 'vrfLimit', 'vrfFailed', 'vrfRequired', 'vrfUnavailable', 'vrfEmailRetype', 'vrfEmailMismatch',
			'vrfDestination', 'vrfBlocked', 'vrfRefresh', 'vrfNoCode', 'vrfNoCodeTips', 'vrfCodeSentA11y');
	}

	public static function server_keys() {
		return array('vrfExpired', 'vrfRequired', 'vrfEmailMismatch', 'vrfUnavailable', 'vrfRefresh');
	}

	private static function plain($v, $max) {
		if (!is_scalar($v)) return '';
		$v = (string) $v;
		$v = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(strip_tags($v));
		$v = str_replace(array('"', '<', '>'), '', $v);
		return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
	}

	/* ================================================================ keys, codes, tokens */

	/** A key per purpose, separate from the nonce and Form_Runtime keys (spec §5.5). */
	public static function key($purpose) {
		$salt = function_exists('wp_salt') ? wp_salt('auth') : 'efb';
		return hash_hmac('sha256', 'efb-verify-v1|' . (string) $purpose, $salt);
	}

	/** $len random digits (CSPRNG), leading zeros kept. */
	public static function make_code($len = 6) {
		$len = max(4, min(10, (int) $len));
		$s = '';
		for ($i = 0; $i < $len; $i++) $s .= (string) random_int(0, 9);
		return $s;
	}

	public static function hash_code($challenge_id, $code) {
		return hash_hmac('sha256', (string) $challenge_id . '|' . (string) $code, self::key('code'));
	}

	/** Persian / Arabic-Indic digits to 0-9. */
	public static function ascii_digits($s) {
		return strtr((string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		));
	}

	/** Only the digits of a typed or pasted code ("کد شما: ۱۲۳۴۵۶" → "123456"). */
	public static function digits_only($s) {
		return preg_replace('/[^0-9]/', '', self::ascii_digits((string) $s));
	}

	/**
	 * The destination as it is compared everywhere (send, check, submit): E.164 for sms
	 * (^\+[1-9][0-9]{7,14}$, Persian digits, direction marks and spaces removed), the whole address
	 * in lower case for email (the submit handler's normalization first); null when invalid.
	 */
	public static function normalize_destination($channel, $raw) {
		if (!is_scalar($raw)) return null;
		$raw = (string) $raw;
		if (strlen($raw) > 320) return null;
		if ($channel === 'sms') {
			$v = self::ascii_digits($raw);
			$v = preg_replace('/[\x{00A0}\x{061C}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $v);
			$v = trim((string) $v);
			$plus = strpos($v, '+') === 0;
			if (!$plus && strpos($v, '00') === 0) { $v = substr($v, 2); $plus = true; }
			$v = preg_replace('/[^0-9]/', '', $v);
			if (!$plus) return null;
			$v = '+' . $v;
			return preg_match('/^\+[1-9][0-9]{7,14}$/', $v) ? $v : null;
		}
		if ($channel === 'email') {
			// The submit handler's normalize_email_input_efb(): Persian / Arabic digits, fullwidth @ and
			// dots, direction marks and spaces (kept here so the engine does not need the public class).
			$v = strtr(self::ascii_digits($raw), array("\u{FF20}" => '@', "\u{FF0E}" => '.', "\u{3002}" => '.', "\u{066B}" => '.', "\u{06D4}" => '.'));
			$v = trim((string) preg_replace('/[\x{00A0}\x{061C}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $v));
			$v = function_exists('sanitize_email') ? sanitize_email($v) : trim($v);
			if ($v === '' || strlen($v) > 254) return null;
			if (function_exists('is_email') ? !is_email($v) : !filter_var($v, FILTER_VALIDATE_EMAIL)) return null;
			return strtolower($v);
		}
		return null;
	}

	public static function dest_hash($channel, $normalized) {
		return hash_hmac('sha256', (string) $channel . '|' . (string) $normalized, self::key('dest'));
	}

	public static function ip_hash($ip_key) {
		return $ip_key === '' ? '' : hash_hmac('sha256', (string) $ip_key, self::key('ip'));
	}

	/** "+98 912 ***-4567" / "ma***@example.com": enough to recognise it, not to read it. */
	public static function mask($channel, $normalized) {
		$v = (string) $normalized;
		if ($channel === 'sms') {
			$d = substr($v, 1);
			$cc = self::dial_of($v);
			$rest = substr($d, strlen($cc));
			$head = substr($rest, 0, min(3, max(0, strlen($rest) - 4)));
			return '+' . $cc . ' ' . $head . ' ***-' . substr($rest, -4);
		}
		$at = strrpos($v, '@');
		if ($at === false) return '***';
		$local = substr($v, 0, $at);
		$keep = function_exists('mb_substr') ? mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1))) : substr($local, 0, 2);
		return $keep . '***' . substr($v, $at);
	}

	/** iso2 → dial code of the countries the mobile field offers (intl-tel-input's list, no territories that share a code noted twice). */
	public static function dial_codes() {
		static $d = null;
		if ($d !== null) return $d;
		$d = array(
			'af' => '93', 'al' => '355', 'dz' => '213', 'ad' => '376', 'ao' => '244', 'ar' => '54', 'am' => '374', 'au' => '61', 'at' => '43', 'az' => '994',
			'bh' => '973', 'bd' => '880', 'by' => '375', 'be' => '32', 'bz' => '501', 'bj' => '229', 'bt' => '975', 'bo' => '591', 'ba' => '387', 'bw' => '267',
			'br' => '55', 'bn' => '673', 'bg' => '359', 'bf' => '226', 'bi' => '257', 'kh' => '855', 'cm' => '237', 'ca' => '1', 'cv' => '238', 'cf' => '236',
			'td' => '235', 'cl' => '56', 'cn' => '86', 'co' => '57', 'km' => '269', 'cg' => '242', 'cd' => '243', 'cr' => '506', 'ci' => '225', 'hr' => '385',
			'cu' => '53', 'cy' => '357', 'cz' => '420', 'dk' => '45', 'dj' => '253', 'ec' => '593', 'eg' => '20', 'sv' => '503', 'gq' => '240', 'er' => '291',
			'ee' => '372', 'sz' => '268', 'et' => '251', 'fj' => '679', 'fi' => '358', 'fr' => '33', 'ga' => '241', 'gm' => '220', 'ge' => '995', 'de' => '49',
			'gh' => '233', 'gr' => '30', 'gt' => '502', 'gn' => '224', 'gw' => '245', 'gy' => '592', 'ht' => '509', 'hn' => '504', 'hk' => '852', 'hu' => '36',
			'is' => '354', 'in' => '91', 'id' => '62', 'ir' => '98', 'iq' => '964', 'ie' => '353', 'il' => '972', 'it' => '39', 'jp' => '81', 'jo' => '962',
			'kz' => '7', 'ke' => '254', 'kw' => '965', 'kg' => '996', 'la' => '856', 'lv' => '371', 'lb' => '961', 'ls' => '266', 'lr' => '231', 'ly' => '218',
			'li' => '423', 'lt' => '370', 'lu' => '352', 'mo' => '853', 'mg' => '261', 'mw' => '265', 'my' => '60', 'mv' => '960', 'ml' => '223', 'mt' => '356',
			'mr' => '222', 'mu' => '230', 'mx' => '52', 'md' => '373', 'mc' => '377', 'mn' => '976', 'me' => '382', 'ma' => '212', 'mz' => '258', 'mm' => '95',
			'na' => '264', 'np' => '977', 'nl' => '31', 'nz' => '64', 'ni' => '505', 'ne' => '227', 'ng' => '234', 'kp' => '850', 'mk' => '389', 'no' => '47',
			'om' => '968', 'pk' => '92', 'ps' => '970', 'pa' => '507', 'pg' => '675', 'py' => '595', 'pe' => '51', 'ph' => '63', 'pl' => '48', 'pt' => '351',
			'qa' => '974', 'ro' => '40', 'ru' => '7', 'rw' => '250', 'sa' => '966', 'sn' => '221', 'rs' => '381', 'sc' => '248', 'sl' => '232', 'sg' => '65',
			'sk' => '421', 'si' => '386', 'so' => '252', 'za' => '27', 'kr' => '82', 'ss' => '211', 'es' => '34', 'lk' => '94', 'sd' => '249', 'sr' => '597',
			'se' => '46', 'ch' => '41', 'sy' => '963', 'tw' => '886', 'tj' => '992', 'tz' => '255', 'th' => '66', 'tl' => '670', 'tg' => '228', 'tn' => '216',
			'tr' => '90', 'tm' => '993', 'ug' => '256', 'ua' => '380', 'ae' => '971', 'gb' => '44', 'us' => '1', 'uy' => '598', 'uz' => '998', 'vu' => '678',
			've' => '58', 'vn' => '84', 'ye' => '967', 'zm' => '260', 'zw' => '263',
		);
		return $d;
	}

	/** The dial code an E.164 number starts with (longest match), '' when none of the list. */
	public static function dial_of($e164) {
		$digits = ltrim((string) $e164, '+');
		$codes = array_unique(array_values(self::dial_codes()));
		usort($codes, function ($a, $b) { return strlen($b) - strlen($a); });
		foreach ($codes as $c) {
			if (strpos($digits, $c) === 0) return $c;
		}
		return '';
	}

	/** NANP (+1) area codes of the Caribbean (high toll-fraud risk), refused unless the filter allows them. */
	public static function nanp_blocked() {
		$codes = array('242', '246', '264', '268', '284', '345', '441', '473', '649', '658', '664', '721', '758', '767', '784', '809', '829', '849', '868', '869', '876');
		return function_exists('apply_filters') ? (array) apply_filters('efb_verify_nanp_blocked_area_codes', $codes) : $codes;
	}

	/**
	 * The countries a field may send codes to: the field's otp_countries, else its display list
	 * (c_c), else the global list; always intersected with the global list (never wider).
	 */
	public static function field_countries($f, $settings = null) {
		$s = $settings === null ? self::settings() : $settings;
		$global = isset($s['sms']['allowed_countries']) ? (array) $s['sms']['allowed_countries'] : array();
		$own = isset($f['otp_countries']) ? self::iso_list($f['otp_countries']) : array();
		if (!$own && isset($f['c_c'])) $own = self::iso_list($f['c_c']);
		return $own ? array_values(array_intersect($own, $global)) : $global;
	}

	/** Whether an E.164 number belongs to one of $iso2s (by dial code; +1 Caribbean area codes refused). */
	public static function country_allowed($e164, array $iso2s) {
		if (!$iso2s) return false;
		$dial = self::dial_of($e164);
		if ($dial === '') return false;
		if ($dial === '1' && in_array(substr(ltrim((string) $e164, '+'), 1, 3), self::nanp_blocked(), true)) return false;
		$codes = self::dial_codes();
		foreach ($iso2s as $c) {
			if (isset($codes[$c]) && $codes[$c] === $dial) return true;
		}
		return false;
	}

	private static function b64u($s) {
		return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
	}

	/** Signed proof that a destination was verified (spec §5.5). */
	public static function token_issue(array $c) {
		$ttl = isset($c['ttl']) ? (int) $c['ttl'] : (int) self::settings()['token_ttl'];
		$payload = array('v' => 1, 'cid' => (string) $c['cid'], 'fid' => (int) $c['fid'], 'fld' => (string) $c['fld'], 'ch' => (string) $c['ch'], 'dh' => (string) $c['dh'], 'sid' => (string) $c['sid'], 'exp' => self::now() + $ttl);
		$json = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
		$body = self::b64u($json);
		return $body . '.' . hash_hmac('sha256', $body, self::key('token'));
	}

	/** The payload when the signature holds and the token has not expired; false otherwise. */
	public static function token_verify($token) {
		if (!is_string($token) || strlen($token) > 1024 || substr_count($token, '.') !== 1) return false;
		list($body, $sig) = explode('.', $token);
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $body) || !preg_match('/^[a-f0-9]{64}$/', $sig)) return false;
		if (!hash_equals(hash_hmac('sha256', $body, self::key('token')), $sig)) return false;
		$p = json_decode(base64_decode(strtr($body, '-_', '+/'), true), true);
		if (!is_array($p) || ($p['v'] ?? 0) !== 1 || !isset($p['cid'], $p['fid'], $p['fld'], $p['ch'], $p['dh'], $p['sid'], $p['exp'])) return false;
		if ((int) $p['exp'] < self::now()) return false;
		return $p;
	}

	/* ================================================================ fields */

	public static function on($v) {
		return in_array($v, array(1, '1', true, 'true'), true);
	}

	/** e-mail mode of a field ('none' when absent or unknown). */
	public static function email_mode($f) {
		$m = is_array($f) && isset($f['email_confirm_mode']) && is_string($f['email_confirm_mode']) ? $f['email_confirm_mode'] : 'none';
		return in_array($m, self::EMAIL_MODES, true) ? $m : 'none';
	}

	public static function email_needs_retype($f) {
		return in_array(self::email_mode($f), array('retype', 'retype_otp'), true);
	}

	public static function email_needs_otp($f) {
		return in_array(self::email_mode($f), array('otp', 'retype_otp'), true);
	}

	/**
	 * What a field asks for: null when nothing, else array('channel' => 'sms'|'email', 'otp' => bool,
	 * 'retype' => bool, 'when' => 'filled'|'always').
	 */
	public static function field_spec($f) {
		if (!is_array($f) || !isset($f['type'])) return null;
		if ($f['type'] === 'mobile' && isset($f['otp_enabled']) && self::on($f['otp_enabled'])) {
			$when = isset($f['otp_required_when']) && $f['otp_required_when'] === 'always' ? 'always' : 'filled';
			return array('channel' => 'sms', 'otp' => true, 'retype' => false, 'when' => $when);
		}
		if ($f['type'] === 'email' && self::email_mode($f) !== 'none') {
			$when = isset($f['email_otp_required_when']) && $f['email_otp_required_when'] === 'always' ? 'always' : 'filled';
			return array('channel' => 'email', 'otp' => self::email_needs_otp($f), 'retype' => self::email_needs_retype($f), 'when' => $when);
		}
		return null;
	}

	/** The fields of a form that ask for something (id_ => spec). */
	public static function form_fields($rows) {
		$out = array();
		foreach ((array) $rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['id_'])) continue;
			$s = self::field_spec($r);
			if ($s) $out[(string) $r['id_']] = $s;
		}
		return $out;
	}

	/**
	 * The save rule of one field key (sanitize_obj_msg_efb, the builder, efbBuilderApi): enums keep a
	 * known value or '' (= not set), otp_countries is a list of ISO codes, texts are plain (no " < >).
	 */
	public static function sanitize_key($k, $v) {
		switch ($k) {
			case 'otp_enabled':
				return is_scalar($v) && self::on(is_string($v) ? strtolower(trim($v)) : $v) ? '1' : '0';
			case 'otp_channel':
				return 'sms';
			case 'otp_required_when':
			case 'email_otp_required_when':
				return is_string($v) && in_array($v, array('filled', 'always'), true) ? $v : 'filled';
			case 'email_confirm_mode':
				return is_string($v) && in_array($v, self::EMAIL_MODES, true) ? $v : 'none';
			case 'otp_countries':
				return array_slice(self::iso_list(is_array($v) || is_string($v) ? $v : array()), 0, 60);
			case 'otp_btn_label':
			case 'email_retype_label':
				return self::plain($v, 60);
			case 'email_otp_subject':
				return self::plain($v, 150);
			case 'email_otp_body':
				if (!is_scalar($v)) return '';
				$t = str_replace(array("\r\n", "\r", "\n"), '@n#', (string) $v);
				$t = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($t) : strip_tags($t);
				$t = str_replace(array('"', '<', '>'), '', $t);
				$t = preg_replace('/[ \t]+/', ' ', $t);
				return trim(function_exists('mb_substr') ? mb_substr($t, 0, 1000) : substr($t, 0, 1000));
		}
		return '';
	}

	/** Cheap test on the raw form JSON (page assets): does any field carry a verification key that is on? */
	public static function json_has_verification($json) {
		return is_string($json) && (preg_match('/otp_enabled\\\\?"\s*:\s*\\\\?"?(?:1|true)/', $json) || preg_match('/email_confirm_mode\\\\?"\s*:\s*\\\\?"(?:retype|otp)/', $json));
	}

	/* ================================================================ plan and readiness */

	public static function plan_need($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		return function_exists('apply_filters') ? (string) apply_filters('efb_verify_plan', $need, $part) : $need;
	}

	public static function plan_ok($part) {
		$need = self::plan_need($part);
		if ($need === 'free') return true;
		if (function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		if (class_exists('\\Emsfb\\Builder_Api')) return (bool) Builder_Api::plan_at_least($need);
		return false;
	}

	/** Registered adapters (id => Provider), from the efb_verify_providers filter. */
	public static function providers() {
		if (self::$providers_cache !== null) return self::$providers_cache;
		$list = function_exists('apply_filters') ? apply_filters('efb_verify_providers', array()) : array();
		$out = array();
		foreach ((array) $list as $k => $p) {
			if (!is_object($p) || !($p instanceof \Emsfb\Verification\Provider)) continue;
			$id = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string) $p->id()));
			if ($id === '' || $id === 'none' || strlen($id) > 20) continue;
			$out[$id] = $p;
		}
		return self::$providers_cache = $out;
	}

	/** The configured SMS sender when it is registered (SMS add-on on) and can send (WP SMS active). */
	public static function sms_provider($settings = null) {
		$s = $settings === null ? self::settings() : $settings;
		$all = self::providers();
		$id = $s['sms']['provider'];
		if (!isset($all[$id]) && count($all) === 1) $id = key($all); // the only sender there is
		if (!isset($all[$id])) return null;
		$p = $all[$id];
		if (!in_array('sms', (array) $p->channels(), true)) return null;
		try { return $p->ready() ? $p : null; } catch (\Throwable $e) { return null; }
	}

	/**
	 * Whether a channel can deliver now: array('ready' => bool, 'reason' => '' | 'plan' | 'no_provider'
	 * (the SMS add-on is off: nothing registered a sender) | 'sender_not_ready' (WP SMS is not installed
	 * or not active) | 'no_countries' | 'email_off'). Never names a provider to visitors.
	 */
	public static function channel_state($channel) {
		if (!in_array($channel, self::CHANNELS, true)) return array('ready' => false, 'reason' => 'channel');
		if (!self::plan_ok($channel)) return array('ready' => false, 'reason' => 'plan');
		if ($channel === 'email') {
			$st = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
			$on = function_exists('emsfb_is_email_sending_enabled_efb') ? (bool) emsfb_is_email_sending_enabled_efb($st) : true;
			return $on ? array('ready' => true, 'reason' => '') : array('ready' => false, 'reason' => 'email_off');
		}
		$s = self::settings();
		if (!self::providers()) return array('ready' => false, 'reason' => 'no_provider');
		if (!self::sms_provider($s)) return array('ready' => false, 'reason' => 'sender_not_ready');
		if (!$s['sms']['allowed_countries']) return array('ready' => false, 'reason' => 'no_countries');
		return array('ready' => true, 'reason' => '');
	}

	public static function channel_ready($channel) {
		$st = self::channel_state($channel);
		return $st['ready'];
	}

	/* ================================================================ storage */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (spec §5.3). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fid` int(11) NOT NULL,
  `field_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dest_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dest_mask` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sid` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `code_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `provider` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `provider_ref` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime NULL DEFAULT NULL,
  `consumed_at` datetime NULL DEFAULT NULL,
  `msg_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY challenge_id (challenge_id),
  KEY session_field (fid,sid,field_id),
  KEY dest_time (dest_hash,created_at),
  KEY expires (expires_at)
) {$charset_collate};";
	}

	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		$t = self::table();
		self::$ready = is_object($wpdb) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		return self::$ready;
	}

	public static function reset_ready() {
		self::$ready = null;
	}

	/** A challenge row by its public id within one form, field and session; null when none. */
	public static function find($challenge_id, $fid, $field_id, $sid) {
		global $wpdb;
		if (!is_string($challenge_id) || !preg_match('/^[a-f0-9]{32}$/', $challenge_id)) return null;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE challenge_id = %s AND fid = %d AND field_id = %s AND sid = %s", $challenge_id, (int) $fid, (string) $field_id, (string) $sid), ARRAY_A);
		return is_array($r) ? $r : null;
	}

	/** Sends of a destination in the last 24 hours and the time of the last one (cooldown). */
	public static function dest_history($dest_hash) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = (array) $wpdb->get_col($wpdb->prepare("SELECT created_at FROM `{$t}` WHERE dest_hash = %s AND created_at > %s ORDER BY created_at DESC", (string) $dest_hash, self::utc(-86400)));
		$last = $rows ? strtotime($rows[0] . ' UTC') : 0;
		return array('count' => count($rows), 'last' => $last);
	}

	/** Seconds still to wait before the next send to a destination (60 → 120 → 300 → 600). */
	public static function cooldown_left(array $hist, array $cooldowns) {
		if ($hist['count'] < 1 || !$cooldowns) return 0;
		$gap = (int) $cooldowns[min($hist['count'] - 1, count($cooldowns) - 1)];
		$left = $hist['last'] + $gap - self::now();
		return $left > 0 ? $left : 0;
	}

	/** Earlier pending challenges of the same form, field, session and destination are superseded. */
	public static function supersede($fid, $field_id, $sid, $dest_hash) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'superseded' WHERE fid = %d AND field_id = %s AND sid = %s AND dest_hash = %s AND status = 'pending'", (int) $fid, (string) $field_id, (string) $sid, (string) $dest_hash));
	}

	/** Inserts a pending challenge; returns its row or null on a database error. */
	public static function create_challenge(array $c) {
		global $wpdb;
		$cid = bin2hex(random_bytes(16));
		$row = array(
			'challenge_id' => $cid, 'fid' => (int) $c['fid'], 'field_id' => substr((string) $c['field_id'], 0, 64), 'channel' => (string) $c['channel'],
			'dest_hash' => (string) $c['dest_hash'], 'dest_mask' => substr((string) $c['dest_mask'], 0, 64), 'sid' => substr((string) $c['sid'], 0, 64),
			'ip_hash' => (string) ($c['ip_hash'] ?? ''), 'code_hash' => isset($c['code']) && $c['code'] !== '' ? self::hash_code($cid, $c['code']) : '',
			'provider' => substr((string) ($c['provider'] ?? ''), 0, 20), 'provider_ref' => '', 'attempts' => 0, 'max_attempts' => (int) $c['max_attempts'],
			'status' => 'pending', 'created_at' => self::utc(), 'expires_at' => self::utc((int) $c['ttl']),
		);
		$ok = $wpdb->insert(self::table(), $row);
		if ($ok === false) return null;
		$row['id'] = (int) $wpdb->insert_id;
		return $row;
	}

	public static function set_status($challenge_id, $status, $from = null) {
		global $wpdb;
		$t = self::table();
		if ($from === null) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			return (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = %s WHERE challenge_id = %s", (string) $status, (string) $challenge_id));
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = %s WHERE challenge_id = %s AND status = %s", (string) $status, (string) $challenge_id, (string) $from));
	}

	/** One more attempt, counted before the compare; false when the challenge has none left (or is no longer pending). */
	public static function attempt($challenge_id) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET attempts = attempts + 1 WHERE challenge_id = %s AND status = 'pending' AND attempts < max_attempts", (string) $challenge_id));
		return $n === 1;
	}

	/** pending → verified, once (two concurrent right answers: only one wins). */
	public static function mark_verified($challenge_id) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'verified', verified_at = %s WHERE challenge_id = %s AND status = 'pending'", self::utc(), (string) $challenge_id)) === 1;
	}

	/**
	 * verified → consumed for every claim, all or nothing: the ones this call took are given back
	 * when one is missing. Returns the number taken (count($claims) on success, 0 after a rollback).
	 */
	public static function consume(array $claims) {
		global $wpdb;
		$t = self::table();
		$taken = array();
		foreach ($claims as $c) {
			$cid = is_array($c) ? (string) ($c['cid'] ?? '') : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n = $cid !== '' ? $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'consumed', consumed_at = %s WHERE challenge_id = %s AND status = 'verified' AND consumed_at IS NULL", self::utc(), $cid)) : 0;
			if ($n !== 1) {
				self::release($taken);
				return 0;
			}
			$taken[] = $c;
		}
		return count($taken);
	}

	/** consumed → verified again (the insert that consumed them failed): the visitor need not verify again. */
	public static function release(array $claims) {
		global $wpdb;
		$t = self::table();
		$n = 0;
		foreach ($claims as $c) {
			$cid = is_array($c) ? (string) ($c['cid'] ?? '') : '';
			if ($cid === '') continue;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'verified', consumed_at = NULL WHERE challenge_id = %s AND status = 'consumed' AND msg_id IS NULL", $cid));
		}
		return $n;
	}

	/** The entry the consumed challenges went into (admin tracing; the eraser follows it). */
	public static function attach(array $claims, $msg_id) {
		global $wpdb;
		$t = self::table();
		foreach ($claims as $c) {
			$cid = is_array($c) ? (string) ($c['cid'] ?? '') : '';
			if ($cid === '') continue;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$wpdb->query($wpdb->prepare("UPDATE `{$t}` SET msg_id = %d WHERE challenge_id = %s AND status = 'consumed'", (int) $msg_id, $cid));
		}
	}

	/* ================================================================ limits */

	private static function ip_key($fid) {
		return class_exists('\\Emsfb\\Client_Ip') ? (string) Client_Ip::limit_key((int) $fid) : '';
	}

	private static function slot_left($window) {
		$window = max(1, (int) $window);
		$end = ((int) floor(self::now() / $window) + 1) * $window;
		return max(1, $end - self::now());
	}

	/** The count of a hit() counter of the current window (0 when absent). */
	public static function count_of($scope, $key, $window) {
		if (!class_exists('\\Emsfb\\Rate_Limit')) return 0;
		$prev = Rate_Limit::$now;
		Rate_Limit::$now = self::now();
		$v = Rate_Limit::peek($scope, $key, (string) (int) floor(self::now() / max(1, (int) $window)));
		Rate_Limit::$now = $prev;
		return (int) $v;
	}

	/** One counted hit on the test clock of this class. */
	public static function hit($scope, $key, $window, $limit) {
		if (!class_exists('\\Emsfb\\Rate_Limit')) return false;
		$prev = Rate_Limit::$now;
		Rate_Limit::$now = self::now();
		$ok = Rate_Limit::hit($scope, $key, $window, $limit);
		Rate_Limit::$now = $prev;
		return $ok;
	}

	/** A destination or an IP that failed too often is locked (NIST: resending does not reset it). */
	public static function locked_out($channel, $dest_hash, $ip_key, $s = null) {
		$s = $s === null ? self::settings() : $s;
		$c = $channel === 'sms' ? 's' : 'e';
		$ctx = array('channel' => $channel);
		if (self::count_of('vf' . $c . 'd', $dest_hash, 86400) >= self::limit('fail_dest_day', $s['limits']['fail_dest_day'], $ctx)) return 86400;
		if ($ip_key !== '' && self::count_of('vf' . $c . 'i', $ip_key, 3600) >= self::limit('fail_ip_hour', $s['limits']['fail_ip_hour'], $ctx)) return self::slot_left(3600);
		return 0;
	}

	public static function record_failure($channel, $dest_hash, $ip_key) {
		$c = $channel === 'sms' ? 's' : 'e';
		self::hit('vf' . $c . 'd', $dest_hash, 86400, 1000000);
		if ($ip_key !== '') self::hit('vf' . $c . 'i', $ip_key, 3600, 1000000);
	}

	/**
	 * The atomic send limits (spec §5.4, §8): null when every one allows the send (each counted),
	 * else array('code' => 'limit'|'budget', 'retry_after' => seconds). A database error refuses.
	 */
	public static function send_limits($channel, $fid, $sid, $dest_hash, $e164, $ip_key, $s = null, $new_dest = true) {
		$s = $s === null ? self::settings() : $s;
		$c = $channel === 'sms' ? 's' : 'e';
		$cs = $s[$channel];
		$L = $s['limits'];
		$ctx = array('channel' => $channel, 'form_id' => (int) $fid);
		$checks = array(
			array('v' . $c . 'dh', $dest_hash, 3600, self::limit('max_sends_dest_hour', $cs['max_sends_dest_hour'], $ctx)),
			array('v' . $c . 'dd', $dest_hash, 86400, self::limit('max_sends_dest_day', $cs['max_sends_dest_day'], $ctx)),
		);
		if ($ip_key !== '') {
			$checks[] = array('v' . $c . 'ih', $ip_key, 3600, self::limit('ip_hour_' . $channel, $L['ip_hour_' . $channel], $ctx));
			$checks[] = array('v' . $c . 'id', $ip_key, 86400, self::limit('ip_day_' . $channel, $L['ip_day_' . $channel], $ctx));
		}
		$checks[] = array('vss', (int) $fid . '|' . $sid, 86400, self::limit('sid_sends', $L['sid_sends'], $ctx));
		if ($new_dest) $checks[] = array('vsn', (int) $fid . '|' . $sid, 86400, self::limit('sid_destinations', $L['sid_destinations'], $ctx));
		if ($channel === 'sms') {
			$checks[] = array('vsfh', (string) (int) $fid, 3600, self::limit('form_hour_sms', $L['form_hour_sms'], $ctx));
			$checks[] = array('vsph', substr(ltrim((string) $e164, '+'), 0, 6), 3600, self::limit('prefix_hour_sms', $L['prefix_hour_sms'], $ctx));
		}
		foreach ($checks as $k) {
			if (!self::hit($k[0], $k[1], $k[2], $k[3])) return array('code' => 'limit', 'retry_after' => self::slot_left($k[2]));
		}
		if (!self::hit('v' . $c . 'bd', 'site', 86400, self::limit('daily_budget_' . $channel, $cs['daily_budget'], $ctx))) {
			self::alert('budget_full', $channel);
			return array('code' => 'budget', 'retry_after' => self::slot_left(86400));
		}
		return null;
	}

	/* ================================================================ owner alerts (§5.8) */

	/** Budget at 80 % and a low conversion rate e-mail the support address, at most once in 6 hours per kind. */
	public static function after_send($channel) {
		$s = self::settings();
		$c = $channel === 'sms' ? 's' : 'e';
		$used = self::count_of('v' . $c . 'bd', 'site', 86400);
		$budget = max(1, self::limit('daily_budget_' . $channel, $s[$channel]['daily_budget'], array('channel' => $channel)));
		if ($used * 100 >= 80 * $budget) self::alert('budget_80', $channel, array('used' => $used, 'budget' => $budget));
		$conv = self::conversion($channel, 3600);
		if ($conv['sent'] >= 20 && $conv['verified'] * 100 < 20 * $conv['sent']) self::alert('conversion', $channel, $conv);
	}

	/** Sends and verifications of the last $seconds (the settings card shows the last 24 hours). */
	public static function conversion($channel, $seconds = 86400) {
		global $wpdb;
		if (!self::ready()) return array('sent' => 0, 'verified' => 0, 'rate' => 0);
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$sent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE channel = %s AND created_at > %s AND status <> 'failed'", (string) $channel, self::utc(-(int) $seconds)));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$ver = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE channel = %s AND created_at > %s AND status IN ('verified','consumed')", (string) $channel, self::utc(-(int) $seconds)));
		return array('sent' => $sent, 'verified' => $ver, 'rate' => $sent ? (int) round(100 * $ver / $sent) : 0);
	}

	public static function alert($kind, $channel, array $data = array()) {
		$key = 'efb_verify_alert_sent_' . preg_replace('/[^a-z0-9_]/', '', $kind . '_' . $channel);
		if (function_exists('get_transient') && get_transient($key)) return false;
		if (function_exists('set_transient')) set_transient($key, 1, 6 * 3600);
		$st = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		$to = is_object($st) && isset($st->emailSupporter) && is_email($st->emailSupporter) ? $st->emailSupporter : (function_exists('get_option') ? (string) get_option('admin_email') : '');
		if ($to === '' || !function_exists('wp_mail')) return false;
		$site = function_exists('get_bloginfo') ? get_bloginfo('name') : '';
		$ch = $channel === 'sms' ? 'SMS' : 'e-mail';
		if ($kind === 'budget_80') $body = sprintf(__('Verification codes by %1$s on %2$s: %3$s of the daily budget of %4$s are used. At the budget, forms that ask for a code stop accepting submissions until the next day.', 'easy-form-builder'), $ch, $site, (int) $data['used'], (int) $data['budget']);
		elseif ($kind === 'budget_full') $body = sprintf(__('Verification codes by %1$s on %2$s: the daily budget is used up. Forms that ask for a code cannot be sent until it renews.', 'easy-form-builder'), $ch, $site);
		elseif ($kind === 'send_failed') $body = $channel === 'sms'
			? sprintf(__('Verification codes on %s: an SMS could not be sent through WP SMS. Check the gateway and its credit in the WP SMS settings, then send a test code from Settings > Verification. Visitors were asked to try again.', 'easy-form-builder'), $site)
			: sprintf(__('Verification codes on %s: an e-mail could not be sent. Check the e-mail settings of the site (Email Monitor). Visitors were asked to try again.', 'easy-form-builder'), $site);
		else $body = sprintf(__('Verification codes by %1$s on %2$s: %3$s codes were sent in the last hour but only %4$s were entered. This can be SMS pumping (fraud). Check the limits and the allowed countries.', 'easy-form-builder'), $ch, $site, (int) $data['sent'], (int) $data['verified']);
		$sub = sprintf(__('[%s] Verification codes need your attention', 'easy-form-builder'), $site);
		if (function_exists('do_action')) do_action('efb_verify_alert', $kind, $channel, $data);
		return (bool) wp_mail($to, $sub, $body);
	}

	/* ================================================================ REST */

	private static function err($code, $message, $status, array $data = array()) {
		return new \WP_Error($code, $message, array_merge(array('status' => (int) $status), $data));
	}

	/** Shared start of both routes: origin, body, form, runtime proof, session, access. */
	private static function start($r) {
		if (function_exists('nocache_headers')) nocache_headers();
		if (class_exists('\\Emsfb\\Request_Health') && !Request_Health::same_origin()) return self::err('efb_origin_denied', 'Same origin required.', 403);
		$j = is_object($r) && method_exists($r, 'get_json_params') ? $r->get_json_params() : null;
		if (!is_array($j)) return self::err('efb_verify_invalid', 'Invalid request.', 400);
		foreach (array('form_id', 'field_id', 'runtime_token', 'destination', 'challenge_id', 'code', 'lang', 'channel') as $k) {
			if (isset($j[$k]) && !is_scalar($j[$k])) return self::err('efb_verify_invalid', 'Invalid request.', 400);
		}
		$fid = isset($j['form_id']) ? (int) $j['form_id'] : 0;
		$field_id = isset($j['field_id']) ? substr((string) $j['field_id'], 0, 64) : '';
		if ($fid <= 0 || $field_id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $field_id)) return self::err('efb_verify_invalid', 'Invalid request.', 400);
		if (!self::ready() || !class_exists('\\Emsfb\\Rate_Limit') || !Rate_Limit::ready()) return self::err('efb_verify_unavailable', self::t('vrfUnavailable'), 503);
		$proof = class_exists('\\Emsfb\\Form_Runtime') ? Form_Runtime::verify(isset($j['runtime_token']) ? (string) $j['runtime_token'] : '', $fid) : false;
		if (is_wp_error($proof)) return $proof;
		if (!is_array($proof)) return self::err('efb_context_expired', __('This form has expired. Please refresh the page and send it again.', 'easy-form-builder'), 409);
		$sid = (string) $proof['sid'];
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'efb_code_validate_check') && !$fn->efb_code_validate_check($sid, $fid)) return self::err('efb_context_expired', __('This form has expired. Please refresh the page and send it again.', 'easy-form-builder'), 409);
		$rows = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows($fid) : null;
		if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return self::err('efb_verify_invalid', 'Form not found.', 400);
		if (in_array($rows[0]['stateForm'] ?? false, array(true, 1, '1'), true) && !is_user_logged_in()) return self::err('efb_login_required', __('Please log in to use this form.', 'easy-form-builder'), 403);
		if (class_exists('\\Emsfb\\Form_Gate') && class_exists('\\Emsfb\\Drafts')) {
			$gerr = Form_Gate::check(Drafts::access_only($rows[0]), Form_Gate::context($fid, array('t0' => isset($proof['t0']) ? (int) $proof['t0'] : null)));
			if ($gerr) return $gerr;
		}
		$field = null;
		foreach ($rows as $i => $row) {
			if ($i > 0 && is_array($row) && isset($row['id_']) && (string) $row['id_'] === $field_id) { $field = $row; break; }
		}
		if (!$field) return self::err('efb_verify_invalid', 'Invalid request.', 400);
		$spec = self::field_spec($field);
		$type = isset($rows[0]['type']) ? (string) $rows[0]['type'] : '';
		if (!$spec || !$spec['otp'] || in_array($type, array('register', 'login'), true)) return self::err('efb_verify_disabled', self::t('vrfUnavailable'), 400);
		// The server trusts the stored field only: a channel the client names must match it.
		if (isset($j['channel']) && (string) $j['channel'] !== '' && (string) $j['channel'] !== $spec['channel']) return self::err('efb_verify_invalid', 'Invalid request.', 400);
		return array('j' => $j, 'fid' => $fid, 'field_id' => $field_id, 'field' => $field, 'spec' => $spec, 'rows' => $rows, 'sid' => $sid, 'proof' => $proof);
	}

	private static function trace($stage, array $data) {
		if (class_exists('\\Emsfb\\Email_Trace')) Email_Trace::log($stage, $data);
	}

	public static function route_send($r) {
		return self::envelope(self::rest_send($r));
	}

	public static function route_check($r) {
		return self::envelope(self::rest_check($r));
	}

	/**
	 * The plugin's answer envelope (like forms/message/add): a refusal is HTTP 200 with
	 * {success: false, code, message, status, retry_after?, attempts_left?}; `status` is the meaning
	 * (422 wrong code, 429 limit, 503 unavailable, …). A wrong code is the visitor's answer, not a
	 * failed request: the browser logs no network error for it and Request Health counts none.
	 */
	public static function envelope($res) {
		if (!($res instanceof \WP_Error)) return $res;
		$d = $res->get_error_data();
		$d = is_array($d) ? $d : array();
		$out = array('success' => false, 'code' => $res->get_error_code(), 'message' => $res->get_error_message(), 'status' => isset($d['status']) ? (int) $d['status'] : 400);
		foreach (array('retry_after', 'attempts_left') as $k) if (isset($d[$k])) $out[$k] = (int) $d[$k];
		if (class_exists('\\WP_REST_Response')) return new \WP_REST_Response($out, 200);
		return $out;
	}

	public static function rest_send($r) {
		$st = self::start($r);
		if (is_wp_error($st)) return $st;
		$ch = $st['spec']['channel'];
		$s = self::settings();
		$cs = $s[$ch];
		$lang = isset($st['j']['lang']) ? preg_replace('/[^A-Za-z_]/', '', (string) $st['j']['lang']) : '';
		$state = self::channel_state($ch);
		if (!$state['ready']) {
			self::trace('verify.unavailable', array('form_id' => $st['fid'], 'channel' => $ch, 'reason' => $state['reason']));
			return self::err('efb_verify_unavailable', self::t('vrfUnavailable'), 503);
		}
		// 1. Destination.
		$dest = self::normalize_destination($ch, isset($st['j']['destination']) ? $st['j']['destination'] : '');
		if ($dest === null) return self::err('efb_verify_destination', self::t('vrfDestination'), 422);
		$ctx = array('form_id' => $st['fid'], 'field_id' => $st['field_id'], 'channel' => $ch);
		// 2. Country (sms) / domain (email).
		if ($ch === 'sms') {
			if (!self::country_allowed($dest, self::field_countries($st['field'], $s))) return self::err('efb_verify_country', self::t('vrfCountry'), 422);
		} else {
			$domain = substr($dest, strrpos($dest, '@') + 1);
			foreach ($cs['blocked_domains'] as $bd) {
				$bd = ltrim($bd, '*.');
				if ($bd !== '' && ($domain === $bd || substr($domain, -strlen('.' . $bd)) === '.' . $bd)) return self::err('efb_verify_destination', self::t('vrfDestination'), 422);
			}
			if (function_exists('apply_filters') && !apply_filters('efb_verify_email_allowed', true, $dest, $ctx)) return self::err('efb_verify_destination', self::t('vrfDestination'), 422);
		}
		$dh = self::dest_hash($ch, $dest);
		$masked = self::mask($ch, $dest);
		// 3. Bots, before anything costs: Human Shield's submit decision and side-effect gate.
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$ip = class_exists('\\Emsfb\\Client_Ip') ? Client_Ip::get() : '';
		$bot = function_exists('apply_filters') ? apply_filters('efb_submit_bot_decision', false, array('setting' => array(), 'form' => $st['rows'][0], 'ip' => $ip, 'efbFunction' => $fn, 'context' => 'verify_send', 'form_id' => $st['fid'])) : false;
		$side = function_exists('apply_filters') ? apply_filters('efb_shield_allow_side_effect', true, array('channel' => 'otp_' . $ch, 'event' => 'verify_send', 'form_id' => $st['fid'], 'recipients' => array($masked), 'source' => 'verify_send')) : true;
		$extra = function_exists('apply_filters') ? apply_filters('efb_verify_allow_send', true, array_merge($ctx, array('dest_mask' => $masked))) : true;
		if ($bot === true || !$side || !$extra) {
			self::trace('verify.blocked', array('form_id' => $st['fid'], 'channel' => $ch, 'dest' => $masked));
			return self::err('efb_verify_blocked', self::t('vrfBlocked'), 403);
		}
		// 4. Limits: a locked destination / IP, the cooldown, then the atomic counters.
		$ip_key = self::ip_key($st['fid']);
		if (($wait = self::locked_out($ch, $dh, $ip_key, $s)) > 0) return self::err('efb_verify_locked', self::t('vrfLocked'), 429, array('retry_after' => $wait));
		$hist = self::dest_history($dh);
		if (($wait = self::cooldown_left($hist, $cs['resend_cooldowns'])) > 0) return self::err('efb_verify_cooldown', str_replace('%s', self::duration($wait), self::t('vrfLimit')), 429, array('retry_after' => $wait));
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$known = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE fid = %d AND sid = %s AND dest_hash = %s", $st['fid'], $st['sid'], $dh)) > 0;
		$lim = self::send_limits($ch, $st['fid'], $st['sid'], $dh, $dest, $ip_key, $s, !$known);
		if ($lim) {
			self::trace('verify.limited', array('form_id' => $st['fid'], 'channel' => $ch, 'kind' => $lim['code']));
			if ($lim['code'] === 'budget') return self::err('efb_verify_unavailable', self::t('vrfUnavailable'), 503);
			return self::err('efb_verify_limit', str_replace('%s', self::duration($lim['retry_after']), self::t('vrfLimit')), 429, array('retry_after' => $lim['retry_after']));
		}
		// 5. A new code supersedes the pending one (the failure count is not reset).
		self::supersede($st['fid'], $st['field_id'], $st['sid'], $dh);
		$provider = $ch === 'sms' ? self::sms_provider($s) : null;
		$code = self::make_code($cs['code_length']);
		$row = self::create_challenge(array(
			'fid' => $st['fid'], 'field_id' => $st['field_id'], 'channel' => $ch, 'dest_hash' => $dh, 'dest_mask' => $masked, 'sid' => $st['sid'],
			'ip_hash' => self::ip_hash($ip_key), 'code' => $code, 'provider' => $provider ? $provider->id() : ($ch === 'email' ? 'wp_mail' : ''),
			'max_attempts' => $cs['max_attempts'], 'ttl' => $cs['ttl'],
		));
		if (!$row) return self::err('efb_verify_unavailable', self::t('vrfUnavailable'), 503);
		// 6. Deliver.
		if ($ch === 'email') {
			$ok = self::send_email($dest, $code, $st['field'], $cs, $lang);
			$res = $ok ? array('ok' => true) : array('ok' => false, 'error' => 'wp_mail');
		} else {
			$res = self::send_sms($provider, $dest, $code, $cs, $lang);
		}
		$hook = array('fid' => $st['fid'], 'field_id' => $st['field_id'], 'channel' => $ch, 'dest_mask' => $masked, 'provider' => (string) $row['provider']);
		if (empty($res['ok'])) {
			self::set_status($row['challenge_id'], 'failed');
			self::trace('verify.failed', array_merge($hook, array('error' => substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) ($res['error'] ?? '')), 0, 40))));
			if (function_exists('do_action')) do_action('efb_verify_failed', $hook);
			// The owner learns that sending failed (once in 6 hours); the visitor reads a retry sentence only.
			self::alert('send_failed', $ch);
			return self::err('efb_verify_provider', self::t('vrfFailed'), 502);
		}
		self::trace('verify.sent', $hook);
		if (function_exists('do_action')) do_action('efb_verify_sent', $hook);
		self::after_send($ch);
		$n = count($cs['resend_cooldowns']);
		return new \WP_REST_Response(array(
			'success' => true, 'challenge_id' => $row['challenge_id'], 'channel' => $ch, 'masked' => $masked,
			'code_length' => (int) $cs['code_length'],
			'expires_in' => (int) $cs['ttl'], 'resend_after' => (int) $cs['resend_cooldowns'][min($hist['count'], $n - 1)], 'attempts_left' => (int) $cs['max_attempts'],
		), 200);
	}

	public static function rest_check($r) {
		$st = self::start($r);
		if (is_wp_error($st)) return $st;
		$ch = $st['spec']['channel'];
		$s = self::settings();
		$code = self::digits_only(isset($st['j']['code']) ? (string) $st['j']['code'] : '');
		if (strlen($code) < 4 || strlen($code) > 10) return self::err('efb_verify_invalid', self::t('vrfWrong'), 400);
		$row = self::find(isset($st['j']['challenge_id']) ? (string) $st['j']['challenge_id'] : '', $st['fid'], $st['field_id'], $st['sid']);
		if (!$row || $row['channel'] !== $ch) return self::err('efb_verify_expired', self::t('vrfExpired'), 404);
		if ($row['status'] !== 'pending' || strtotime($row['expires_at'] . ' UTC') < self::now()) {
			if ($row['status'] === 'pending') self::set_status($row['challenge_id'], 'expired', 'pending');
			if ($row['status'] === 'locked') return self::err('efb_verify_locked', self::t('vrfLocked'), 429);
			return self::err('efb_verify_expired', self::t('vrfExpired'), 410);
		}
		$ip_key = self::ip_key($st['fid']);
		if (($wait = self::locked_out($ch, $row['dest_hash'], $ip_key, $s)) > 0) {
			self::set_status($row['challenge_id'], 'locked', 'pending');
			return self::err('efb_verify_locked', self::t('vrfLocked'), 429, array('retry_after' => $wait));
		}
		if (!self::attempt($row['challenge_id'])) {
			self::set_status($row['challenge_id'], 'locked', 'pending');
			return self::err('efb_verify_locked', self::t('vrfLocked'), 429);
		}
		$left = max(0, (int) $row['max_attempts'] - (int) $row['attempts'] - 1);
		$hook = array('fid' => $st['fid'], 'field_id' => $st['field_id'], 'channel' => $ch, 'dest_mask' => $row['dest_mask'], 'provider' => $row['provider']);
		$right = $row['code_hash'] !== '' && hash_equals($row['code_hash'], self::hash_code($row['challenge_id'], $code));
		if (!$right) {
			self::record_failure($ch, $row['dest_hash'], $ip_key);
			if ($left <= 0) self::set_status($row['challenge_id'], 'locked', 'pending');
			if (function_exists('do_action')) do_action('efb_verify_failed', array_merge($hook, array('reason' => 'wrong_code')));
			if ($left <= 0) return self::err('efb_verify_locked', self::t('vrfLocked'), 429);
			return self::err('efb_verify_wrong', str_replace('%s', (string) $left, self::t('vrfWrong')), 422, array('attempts_left' => $left));
		}
		if (!self::mark_verified($row['challenge_id'])) return self::err('efb_verify_expired', self::t('vrfExpired'), 410);
		if (function_exists('do_action')) do_action('efb_verify_verified', $hook);
		$ttl = (int) $s['token_ttl'];
		$token = self::token_issue(array('cid' => $row['challenge_id'], 'fid' => $st['fid'], 'fld' => $st['field_id'], 'ch' => $ch, 'dh' => $row['dest_hash'], 'sid' => $st['sid'], 'ttl' => $ttl));
		return new \WP_REST_Response(array('success' => true, 'token' => $token, 'expires_in' => $ttl), 200);
	}

	/** "1:05", "45 s" style wait for the visitor (plain digits; the page localises them). */
	public static function duration($sec) {
		$sec = max(0, (int) $sec);
		if ($sec >= 3600) return sprintf('%d:%02d:%02d', floor($sec / 3600), floor(($sec % 3600) / 60), $sec % 60);
		return sprintf('%d:%02d', floor($sec / 60), $sec % 60);
	}

	/* ================================================================ delivery */

	/** The free text of an SMS (free-text providers): "{code} is your {site} verification code." + "@domain #code" (WebOTP). */
	public static function sms_text($code, array $cs, $lang = '') {
		$site = function_exists('get_bloginfo') ? wp_strip_all_tags(get_bloginfo('name')) : '';
		$domain = function_exists('home_url') ? (string) wp_parse_url(home_url(), PHP_URL_HOST) : '';
		if (isset($cs['message']) && trim($cs['message']) !== '') {
			$text = strtr($cs['message'], array('{code}' => $code, '{site}' => $site, '{domain}' => $domain));
		} else {
			$text = sprintf(self::t('vrfSmsBody'), $code, $site);
		}
		if (!empty($cs['webotp']) && $domain !== '' && strpos($text, '@' . $domain . ' #' . $code) === false) $text .= "\n\n@" . $domain . ' #' . $code;
		return function_exists('apply_filters') ? (string) apply_filters('efb_verify_message', $text, array('code_length' => strlen($code), 'lang' => $lang)) : $text;
	}

	/** Hands the text to the sender (the WP SMS bridge); any exception is a failure, never a pass. */
	public static function send_sms($provider, $to, $code, array $cs, $lang) {
		if (!$provider) return array('ok' => false, 'error' => 'no_sender');
		$msg = array('to' => $to, 'code' => $code, 'text' => self::sms_text($code, $cs, $lang), 'channel' => 'sms', 'lang' => $lang);
		try {
			$res = $provider->send($msg);
		} catch (\Throwable $e) {
			return array('ok' => false, 'error' => 'exception');
		}
		return is_array($res) ? $res : array('ok' => false, 'error' => 'bad_answer');
	}

	/** Subject of the code e-mail: the field's own (only [website_name] and [otp_code]) or vrfEmailSubject. */
	public static function email_subject($f, $code, array $cs) {
		$site = function_exists('get_bloginfo') ? wp_strip_all_tags(get_bloginfo('name')) : '';
		$own = is_array($f) && isset($f['email_otp_subject']) && is_string($f['email_otp_subject']) ? trim($f['email_otp_subject']) : '';
		$sub = $own !== '' ? strtr($own, array('[website_name]' => $site, '[otp_code]' => !empty($cs['code_in_subject']) ? $code : '')) : sprintf(self::t('vrfEmailSubject'), $site);
		if (!empty($cs['code_in_subject']) && strpos($sub, $code) === false) $sub = $code . ' · ' . $sub;
		return trim(preg_replace('/\s+/', ' ', $sub));
	}

	/**
	 * The message area of the code e-mail: the intro, the code (large, monospace, left to right), the
	 * expiry, "ignore it", the site name. No link, no button, no value of the form, not the address.
	 */
	public static function email_html($f, $code, array $cs) {
		$site = function_exists('get_bloginfo') ? wp_strip_all_tags(get_bloginfo('name')) : '';
		$min = (int) ceil($cs['ttl'] / 60);
		$own = is_array($f) && isset($f['email_otp_body']) && is_string($f['email_otp_body']) ? trim(str_replace('@n#', "\n", $f['email_otp_body'])) : '';
		$code_html = '<p dir="ltr" style="font-family:monospace;font-size:28px;font-weight:700;letter-spacing:6px;direction:ltr;unicode-bidi:isolate;text-align:center;margin:18px 0">' . esc_html($code) . '</p>';
		if ($own !== '' && strpos($own, '[otp_code]') !== false) {
			$parts = explode('[otp_code]', esc_html(str_replace('[website_name]', $site, $own)));
			$html = '';
			foreach ($parts as $i => $p) {
				$html .= ($p !== '' ? '<p>' . nl2br(trim($p)) . '</p>' : '') . ($i < count($parts) - 1 ? $code_html : '');
			}
		} else {
			$html = '<p>' . esc_html(self::t('vrfEmailBody')) . '</p>' . $code_html;
		}
		$html .= '<p>' . esc_html(sprintf(self::t('vrfEmailExpires'), $min)) . '</p>'
			. '<p style="color:#6b7280;font-size:13px">' . esc_html(self::t('vrfEmailIgnore')) . '</p>'
			. '<p style="color:#6b7280;font-size:13px">' . esc_html($site) . '</p>';
		return $html;
	}

	/** The plain-text part of the same e-mail (multipart/alternative). */
	public static function email_text($f, $code, array $cs) {
		$min = (int) ceil($cs['ttl'] / 60);
		$site = function_exists('get_bloginfo') ? wp_strip_all_tags(get_bloginfo('name')) : '';
		return self::t('vrfEmailBody') . "\n\n" . $code . "\n\n" . sprintf(self::t('vrfEmailExpires'), $min) . "\n" . self::t('vrfEmailIgnore') . "\n\n" . $site;
	}

	/** Sends the code e-mail through the plugin's template (state otpCode); true only when wp_mail said yes. */
	public static function send_email($to, $code, $f, array $cs, $lang = '') {
		$switched = false;
		if ($lang !== '' && function_exists('switch_to_locale') && function_exists('get_available_languages') && in_array($lang, (array) get_available_languages(), true)) {
			$switched = switch_to_locale($lang);
		}
		$sub = self::email_subject($f, $code, $cs);
		$html = self::email_html($f, $code, $cs);
		$alt = self::email_text($f, $code, $cs);
		if (is_callable(self::$mailer)) {
			$r = call_user_func(self::$mailer, $to, $sub, $html, $code, $alt);
		} else {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			$alt_cb = function ($mailer) use ($alt) { if (is_object($mailer) && property_exists($mailer, 'AltBody')) $mailer->AltBody = $alt; };
			if (function_exists('add_action')) add_action('phpmailer_init', $alt_cb);
			$r = ($fn && method_exists($fn, 'send_email_state_new')) ? $fn->send_email_state_new($to, $sub, $html, $fn->is_efb_pro(1), 'otpCode', 'null', 'null') : false;
			if (function_exists('remove_action')) remove_action('phpmailer_init', $alt_cb);
		}
		if ($switched && function_exists('restore_previous_locale')) restore_previous_locale();
		return $r === true || $r === 1 || $r === '1';
	}

	/* ================================================================ submit gate */

	/**
	 * The authoritative check of a submission (nothing changes here). $ctx: fid, sid, runtime_sid,
	 * form_type, fields (the stored form after logic), items (the normalized rows), ignored (ids),
	 * tokens (field id => token), email_confirms (field id => text), lanText.
	 *
	 * @return array|\WP_Error claims: field id => array('cid', 'field_id', 'channel', 'value')
	 */
	public static function validate_submission(array $ctx) {
		$fields = isset($ctx['fields']) && is_array($ctx['fields']) ? $ctx['fields'] : array();
		$lt = isset($ctx['lanText']) && is_array($ctx['lanText']) ? $ctx['lanText'] : null;
		$type = isset($fields[0]['type']) ? (string) $fields[0]['type'] : '';
		if (in_array($type, array('register', 'login'), true)) return array();
		$items = array();
		foreach ((array) ($ctx['items'] ?? array()) as $it) {
			if (is_array($it) && isset($it['id_']) && is_scalar($it['id_'])) $items[(string) $it['id_']] = $it;
		}
		$ignored = array_flip(array_map('strval', (array) ($ctx['ignored'] ?? array())));
		$tokens = isset($ctx['tokens']) && is_array($ctx['tokens']) ? $ctx['tokens'] : array();
		$confirms = isset($ctx['email_confirms']) && is_array($ctx['email_confirms']) ? $ctx['email_confirms'] : array();
		$fid = (int) ($ctx['fid'] ?? 0);
		$sid = (string) ($ctx['sid'] ?? '');
		$claims = array();
		foreach ($fields as $i => $f) {
			if ($i < 1 || !is_array($f) || !isset($f['id_'])) continue;
			$spec = self::field_spec($f);
			if (!$spec) continue;
			$id = (string) $f['id_'];
			if (isset($ignored[$id]) || (isset($f['disabled']) && self::on($f['disabled']))) continue;
			$name = isset($f['name']) && is_scalar($f['name']) ? (string) $f['name'] : $id;
			$fail = function ($code, $m) use ($id) { return new \WP_Error($code, $m, array('field_id' => $id)); };
			$required_msg = sprintf(self::t('vrfRequired', $lt), '<b>' . esc_html($name) . '</b>');
			$value = isset($items[$id]['value']) && is_scalar($items[$id]['value']) ? trim((string) $items[$id]['value']) : '';
			if ($value === '') {
				// "Always": an empty value is not verified (a required field is caught earlier anyway).
				if ($spec['otp'] && $spec['when'] === 'always') return $fail('efb_verify_required', $required_msg);
				continue;
			}
			if ($spec['retype']) {
				$c = isset($confirms[$id]) && is_scalar($confirms[$id]) ? (string) $confirms[$id] : null;
				if ($c === null) return $fail('efb_verify_mismatch', self::t('vrfRefresh', $lt));
				$a = self::normalize_destination('email', $value);
				$b = self::normalize_destination('email', $c);
				$mis = class_exists('\\Emsfb\\Field_Rules') ? Field_Rules::mismatch_message($f, self::t('vrfEmailMismatch', $lt)) : self::t('vrfEmailMismatch', $lt);
				if ($a === null || $b === null || !hash_equals($a, $b)) return $fail('efb_verify_mismatch', $mis);
			}
			if (!$spec['otp']) continue;
			// Downgraded plan or a channel that cannot deliver: fail-closed (never skip the check).
			if (!self::plan_ok($spec['channel']) || !self::channel_ready($spec['channel'])) return $fail('efb_verify_unavailable', self::t('vrfUnavailable', $lt));
			$norm = self::normalize_destination($spec['channel'], $value);
			$tok = isset($tokens[$id]) && is_string($tokens[$id]) ? self::token_verify($tokens[$id]) : false;
			if ($norm === null || !$tok) return $fail('efb_verify_required', $required_msg);
			if ((int) $tok['fid'] !== $fid || (string) $tok['fld'] !== $id || (string) $tok['ch'] !== $spec['channel'] || !hash_equals((string) $tok['sid'], $sid)
				|| (isset($ctx['runtime_sid']) && $ctx['runtime_sid'] !== null && !hash_equals((string) $tok['sid'], (string) $ctx['runtime_sid']))
				|| !hash_equals((string) $tok['dh'], self::dest_hash($spec['channel'], $norm))) {
				return $fail('efb_verify_required', $required_msg);
			}
			global $wpdb;
			$t = self::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$row = self::ready() ? $wpdb->get_row($wpdb->prepare("SELECT status, consumed_at, dest_hash FROM `{$t}` WHERE challenge_id = %s AND fid = %d AND field_id = %s", (string) $tok['cid'], $fid, $id), ARRAY_A) : null;
			if (!is_array($row) || $row['status'] !== 'verified' || !empty($row['consumed_at']) || !hash_equals((string) $row['dest_hash'], (string) $tok['dh'])) {
				return $fail('efb_verify_required', $required_msg);
			}
			$claims[$id] = array('cid' => (string) $tok['cid'], 'field_id' => $id, 'channel' => $spec['channel'], 'value' => $value);
		}
		return $claims;
	}

	/* ================================================================ cleanup, privacy */

	/** Hourly: verified challenges past the token lifetime and pending ones past their code lifetime expire. */
	public static function cleanup_light() {
		global $wpdb;
		if (!self::ready()) return 0;
		$t = self::table();
		$ttl = (int) self::settings()['token_ttl'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'expired' WHERE status = 'verified' AND consumed_at IS NULL AND verified_at < %s", self::utc(-$ttl)));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET status = 'expired' WHERE status = 'pending' AND expires_at < %s", self::utc()));
		return $n;
	}

	/** Daily: unfinished challenges after 30 days, consumed ones after 90 (filter efb_verify_retention_days). */
	public static function cleanup() {
		global $wpdb;
		if (!self::ready()) return 0;
		self::cleanup_light();
		$t = self::table();
		$open = (int) (function_exists('apply_filters') ? apply_filters('efb_verify_retention_days', 30, 'open') : 30);
		$used = (int) (function_exists('apply_filters') ? apply_filters('efb_verify_retention_days', 90, 'consumed') : 90);
		$n = 0;
		for ($i = 0; $i < 10; $i++) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ids = (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$t}` WHERE (status IN ('pending','superseded','failed','locked','expired','verified') AND created_at < %s) OR (status = 'consumed' AND created_at < %s) LIMIT 1000", self::utc(-max(1, $open) * 86400), self::utc(-max(1, $used) * 86400)));
			if (!$ids) break;
			$ids = array_map('intval', $ids);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, integers
			$n += (int) $wpdb->query("DELETE FROM `{$t}` WHERE id IN (" . implode(',', $ids) . ')');
			if (count($ids) < 1000) break;
		}
		return $n;
	}

	/** An entry deleted, anonymized or erased: the challenges that pointed to it go with it. */
	public static function on_entries_purged($ids, $mode = 'delete', $out = array(), $form_ids = array()) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', (array) $ids)));
		if (!$ids || !self::ready()) return 0;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, integers
		return (int) $wpdb->query("DELETE FROM `{$t}` WHERE msg_id IN (" . implode(',', $ids) . ')');
	}

	public static function on_form_deleted($fid) {
		global $wpdb;
		if ((int) $fid <= 0 || !self::ready()) return 0;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE fid = %d", (int) $fid));
	}

	public static function register_exporter($exporters) {
		$exporters['easy-form-builder-verification'] = array('exporter_friendly_name' => __('Easy Form Builder: verification codes', 'easy-form-builder'), 'callback' => array(__CLASS__, 'export'));
		return $exporters;
	}

	public static function register_eraser($erasers) {
		$erasers['easy-form-builder-verification'] = array('eraser_friendly_name' => __('Easy Form Builder: verification codes', 'easy-form-builder'), 'callback' => array(__CLASS__, 'erase'));
		return $erasers;
	}

	/** The challenge rows of an e-mail address (found by its hash; the address itself is not stored). */
	public static function rows_of_email($email) {
		global $wpdb;
		$n = self::normalize_destination('email', $email);
		if ($n === null || !self::ready()) return array();
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (array) $wpdb->get_results($wpdb->prepare("SELECT id, fid, field_id, dest_mask, status, created_at, verified_at, msg_id FROM `{$t}` WHERE dest_hash = %s ORDER BY id", self::dest_hash('email', $n)), ARRAY_A);
	}

	public static function export($email, $page = 1) {
		$items = array();
		foreach (self::rows_of_email($email) as $r) {
			$items[] = array('group_id' => 'efb-verification', 'group_label' => __('Verification codes', 'easy-form-builder'), 'item_id' => 'efb-vrfy-' . $r['id'], 'data' => array(
				array('name' => __('Form', 'easy-form-builder'), 'value' => (string) $r['fid']),
				array('name' => __('Address (masked)', 'easy-form-builder'), 'value' => (string) $r['dest_mask']),
				array('name' => __('Status', 'easy-form-builder'), 'value' => (string) $r['status']),
				array('name' => __('Requested', 'easy-form-builder'), 'value' => (string) $r['created_at'] . ' UTC'),
				array('name' => __('What is kept', 'easy-form-builder'), 'value' => __('A one-way fingerprint of the address and a masked copy; never the code.', 'easy-form-builder')),
			));
		}
		return array('data' => $items, 'done' => true);
	}

	/** Removes the address's challenges and its current limit counters (the address can ask again). */
	public static function erase($email, $page = 1) {
		global $wpdb;
		$rows = self::rows_of_email($email);
		$n = 0;
		if ($rows) {
			$t = self::table();
			$ids = array_map('intval', array_column($rows, 'id'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, integers
			$n = (int) $wpdb->query("DELETE FROM `{$t}` WHERE id IN (" . implode(',', $ids) . ')');
		}
		$norm = self::normalize_destination('email', $email);
		if ($norm !== null && class_exists('\\Emsfb\\Rate_Limit')) {
			$dh = self::dest_hash('email', $norm);
			$b = array();
			foreach (array(array('vedh', 3600), array('vedd', 86400), array('vfed', 86400)) as $sc) {
				$slot = (int) floor(self::now() / $sc[1]);
				$b[] = Rate_Limit::bucket($sc[0], $dh, (string) $slot);
				$b[] = Rate_Limit::bucket($sc[0], $dh, (string) ($slot - 1));
			}
			Rate_Limit::delete_buckets($b);
		}
		return array('items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true);
	}

	/* ================================================================ builder, registry, admin */

	/**
	 * Save warnings (never refuses the save; the submit is fail-closed): a code that cannot be sent on
	 * this site (plan, add-on, provider, e-mail off), a registration or login form, a custom e-mail text
	 * without [otp_code].
	 */
	public static function form_warnings($rows) {
		$w = array();
		if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return $w;
		$type = isset($rows[0]['type']) ? (string) $rows[0]['type'] : '';
		foreach (self::form_fields($rows) as $id => $spec) {
			$f = null;
			foreach ($rows as $i => $r) { if ($i > 0 && is_array($r) && isset($r['id_']) && (string) $r['id_'] === $id) { $f = $r; break; } }
			if (in_array($type, array('register', 'login'), true)) {
				$w[] = array('code' => 'verify_register_form', 'field_id' => $id, 'm' => self::t('vrfNotOnRegister'));
				continue;
			}
			if ($spec['otp']) {
				$state = self::channel_state($spec['channel']);
				if (!$state['ready']) $w[] = array('code' => 'verify_unavailable', 'field_id' => $id, 'channel' => $spec['channel'], 'reason' => $state['reason'], 'm' => self::t($spec['channel'] === 'sms' ? 'vrfSmsNotReady' : 'vrfEmailNotReady'));
			}
			if ($spec['channel'] === 'email' && $spec['otp'] && is_array($f) && isset($f['email_otp_body']) && trim((string) $f['email_otp_body']) !== '' && strpos((string) $f['email_otp_body'], '[otp_code]') === false) {
				$w[] = array('code' => 'verify_body_without_code', 'field_id' => $id, 'm' => self::t('vrfBodyNeedsCode'));
			}
		}
		return $w;
	}

	/** Builder state: plan and readiness per channel, the providers (ids and labels only). */
	public static function builder_state() {
		$out = array('email' => self::channel_state('email'), 'sms' => self::channel_state('sms'), 'retype_plan' => self::plan_ok('retype'),
			'plan' => array('retype' => self::plan_need('retype'), 'email' => self::plan_need('email'), 'sms' => self::plan_need('sms')));
		$out['countries'] = self::settings()['sms']['allowed_countries'];
		return $out;
	}

	public static function admin_vars($vars) {
		if (!is_array($vars)) return $vars;
		$st = self::builder_state();
		$vars['verify'] = $st;
		if (isset($vars['setting']) && is_object($vars['setting'])) {
			$vars['setting']->verify_sms_ready = $st['sms']['ready'] ? 1 : 0;
			$vars['setting']->verify_email_ready = $st['email']['ready'] ? 1 : 0;
		} elseif (isset($vars['setting']) && is_array($vars['setting'])) {
			$vars['setting']['verify_sms_ready'] = $st['sms']['ready'] ? 1 : 0;
			$vars['setting']['verify_email_ready'] = $st['email']['ready'] ? 1 : 0;
		}
		return $vars;
	}

	/** Registry entries and the top-level verification block (SitePilot contract; no provider name, no credential). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:verify_email_retype' => array('retype', null, array('email_confirm_mode=retype', 'email_retype_label'), 'vrfModeRetype', 'email'),
			'setting:verify_email_otp'    => array('email', null, array('email_confirm_mode=otp', 'email_confirm_mode=retype_otp', 'email_otp_required_when', 'email_otp_subject', 'email_otp_body'), 'vrfModeOtp', 'email'),
			'setting:verify_sms_otp'      => array('sms', 'AdnSS', array('otp_enabled', 'otp_channel', 'otp_required_when', 'otp_countries', 'otp_btn_label'), 'vrfEnableSms', 'sms'),
		);
		foreach ($entries as $key => $e) {
			$need = self::plan_need($e[0]);
			$plan_ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
			$ready = $e[0] === 'retype' ? true : self::channel_ready($e[4]);
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => 'field', 'package' => $need, 'addon' => $e[1], 'available_now' => $plan_ok && $ready,
				'reason' => $plan_ok ? ($ready ? null : ($e[1] ? 'addon' : 'channel')) : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[2], 'label_key' => $e[3],
			);
		}
		$out['verification'] = array(
			'keys'     => array('mobile' => self::MOBILE_KEYS, 'email' => self::EMAIL_KEYS),
			'modes'    => self::EMAIL_MODES,
			'channels' => array(
				'email' => array('ready' => self::channel_ready('email'), 'package' => self::plan_need('email')),
				'sms'   => array('ready' => self::channel_ready('sms'), 'package' => self::plan_need('sms'), 'addon' => 'AdnSS'),
			),
			'retype_package' => self::plan_need('retype'),
			'downgrade' => 'blocks_submit',
			'routes'   => array('send' => 'Emsfb/v1/verify/send', 'check' => 'Emsfb/v1/verify/check'),
			'request_keys' => array('verifications' => '{<field id_>: token}', 'email_confirms' => '{<field id_>: text}'),
			'codes'    => array('efb_verify_required', 'efb_verify_mismatch', 'efb_verify_unavailable', 'efb_verify_invalid', 'efb_verify_disabled', 'efb_verify_destination', 'efb_verify_country', 'efb_verify_cooldown', 'efb_verify_limit', 'efb_verify_blocked', 'efb_verify_provider', 'efb_verify_expired', 'efb_verify_locked', 'efb_verify_wrong'),
			'dom_event' => 'efb:verify-state',
			'spec'     => 'F02',
		);
		return $out;
	}

	/** The senders for the settings card: id, label, whether it can send now (no secret exists in EFB). */
	public static function providers_public() {
		$out = array();
		foreach (self::providers() as $id => $p) {
			$ready = false;
			try { $ready = (bool) $p->ready(); } catch (\Throwable $e) { $ready = false; }
			$out[] = array('id' => $id, 'label' => (string) $p->label(), 'ready' => $ready, 'channels' => array_values((array) $p->channels()));
		}
		return $out;
	}

	/** Everything the settings card shows. */
	public static function admin_state() {
		return array(
			'settings' => self::settings(), 'providers' => self::providers_public(), 'state' => array('email' => self::channel_state('email'), 'sms' => self::channel_state('sms')),
			'stats' => array('sms' => self::conversion('sms'), 'email' => self::conversion('email')),
			'tables' => self::ready(), 'sms_addon' => self::sms_addon_active(), 'wp_sms' => function_exists('wp_sms_send'),
			'links' => self::setup_links(),
		);
	}

	/** Where the owner sets SMS up: WP SMS (install / its settings) and the plugin's add-ons page. */
	public static function setup_links() {
		$admin = function_exists('admin_url') ? admin_url() : '/wp-admin/';
		return array(
			'wp_sms_install' => $admin . 'plugin-install.php?s=wp-sms&tab=search&type=term',
			'wp_sms_settings' => $admin . 'admin.php?page=wp-sms-settings',
			'addons' => $admin . 'admin.php?page=Emsfb_addons',
			'docs' => 'https://wp-sms-pro.com/resources/wp_sms_send/',
		);
	}

	public static function sms_addon_active() {
		if (class_exists('\\Emsfb\\Builder_Api')) {
			$st = Builder_Api::notifier_state(function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null);
			return !empty($st['sms']);
		}
		return false;
	}

	private static function admin_guard() {
		$ok = function_exists('check_ajax_referer') && check_ajax_referer('wp_rest', 'nonce', false) && function_exists('current_user_can') && current_user_can('manage_options');
		if (!$ok) wp_send_json_success(array('success' => false, 'm' => __('You do not have permission to do this.', 'easy-form-builder')), 200);
	}

	/** wp_ajax_efb_verify_settings_Emsfb: op=get | save (settings JSON + the provider's fields). Administrators only. */
	public static function ajax_settings() {
		self::admin_guard();
		$op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : 'get';
		if ($op === 'save') {
			$raw = isset($_POST['settings']) ? json_decode(wp_unslash((string) $_POST['settings']), true) : null;
			if (!is_array($raw)) wp_send_json_success(array('success' => false, 'm' => __('Invalid request.', 'easy-form-builder')), 200);
			$cur = self::settings();
			$merged = array_replace_recursive($cur, $raw);
			// Lists replace (not merge) the stored ones.
			foreach (array(array('sms', 'allowed_countries'), array('sms', 'resend_cooldowns'), array('email', 'resend_cooldowns'), array('email', 'blocked_domains')) as $p) {
				if (isset($raw[$p[0]][$p[1]])) $merged[$p[0]][$p[1]] = $raw[$p[0]][$p[1]];
			}
			self::save_settings($merged);
		}
		wp_send_json_success(array('success' => true, 'state' => self::admin_state()), 200);
	}

	/** wp_ajax_efb_verify_test_send_Emsfb: a test code to the administrator's number or address (counted in the budget). */
	public static function ajax_test_send() {
		self::admin_guard();
		$ch = isset($_POST['channel']) && $_POST['channel'] === 'email' ? 'email' : 'sms';
		$dest = self::normalize_destination($ch, isset($_POST['to']) ? wp_unslash((string) $_POST['to']) : '');
		if ($dest === null) wp_send_json_success(array('success' => false, 'm' => self::t('vrfDestination')), 200);
		$state = self::channel_state($ch);
		if (!$state['ready']) wp_send_json_success(array('success' => false, 'm' => self::t($ch === 'sms' ? 'vrfSmsNotReady' : 'vrfEmailNotReady'), 'reason' => $state['reason']), 200);
		$s = self::settings();
		$c = $ch === 'sms' ? 's' : 'e';
		if (!self::hit('v' . $c . 'bd', 'site', 86400, self::limit('daily_budget_' . $ch, $s[$ch]['daily_budget'], array('channel' => $ch))) || !self::hit('vtst', (string) get_current_user_id(), 3600, 10)) {
			wp_send_json_success(array('success' => false, 'm' => str_replace('%s', self::duration(self::slot_left(3600)), self::t('vrfLimit'))), 200);
		}
		$code = self::make_code($s[$ch]['code_length']);
		if ($ch === 'email') {
			$ok = self::send_email($dest, $code, array(), $s['email']);
			$res = array('ok' => $ok, 'error' => $ok ? '' : 'wp_mail');
		} else {
			$res = self::send_sms(self::sms_provider($s), $dest, $code, $s['sms'], get_locale());
		}
		wp_send_json_success(array('success' => !empty($res['ok']), 'm' => !empty($res['ok']) ? self::t('vrfTestSent') : self::t('vrfFailed'), 'error' => !empty($res['ok']) ? '' : substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) ($res['error'] ?? '')), 0, 40)), 200);
	}
}
