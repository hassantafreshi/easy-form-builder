<?php
/**
 * Builder API for integrations (AI Site Assistant AutoPilot and others).
 *
 * One place for what an integration needs to know about the form builder without
 * copying EFB's own knowledge: which notification add-ons can run on save, the
 * capability (plan and add-on) registry, the field templates and the
 * conditional-logic rule check. See docs/autopilot-integration.md.
 *
 * Every public answer here is free of secrets: add-on and gateway keys are only
 * ever reported as "configured: true/false".
 */
namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Builder_Api {

	/**
	 * Which notification add-ons a saved form can actually use on this site.
	 *
	 * @param object|array|null $settings Decoded EFB settings (get_setting_Emsfb('decoded')).
	 * @return array{sms:bool,telegram:bool}
	 */
	public static function notifier_state($settings) {
		$s = is_array($settings) ? (object) $settings : (is_object($settings) ? $settings : new \stdClass());
		$dir = defined('EMSFB_PLUGIN_DIRECTORY') ? EMSFB_PLUGIN_DIRECTORY : dirname(__DIR__) . '/';
		$sms_on = (isset($s->AdnSS) && intval($s->AdnSS) === 1)
			|| (function_exists('get_option') && intval(get_option('emsfb_addon_AdnSS', 0)) === 1);
		$tlg_on = (isset($s->AdnTLG) && intval($s->AdnTLG) === 1)
			|| (function_exists('get_option') && intval(get_option('emsfb_addon_AdnTLG', 0)) === 1);
		return array(
			'sms'      => $sms_on && file_exists($dir . '/vendor/smssended/smsefb.php'),
			'telegram' => $tlg_on && file_exists($dir . '/vendor/telegram/telegram-new-efb.php'),
		);
	}

	/**
	 * A5: a form that asks for SMS or Telegram notifications on a site where that
	 * add-on is not active is still saved. The notification is switched off (row 0
	 * and every field) and a warning tells the admin why, instead of refusing the
	 * whole save and losing every other change.
	 *
	 * Secrets that only the add-on may store (Telegram bot token and chat ids, the
	 * admins' phone numbers) are removed from the form JSON, which visitors can read.
	 *
	 * @param array             $rows     Decoded form (list of associative rows).
	 * @param object|array|null $settings Decoded EFB settings.
	 * @param array             $lang     text_efb() subset with smsOffAddonMissing / telegramOffAddonMissing.
	 * @return array{0:array,1:array} [rows, warnings]; a warning is ['code'=>..,'m'=>..].
	 */
	public static function disable_unavailable_notifiers(array $rows, $settings, array $lang = array()) {
		$warnings = array();
		if (!isset($rows[0]) || !is_array($rows[0])) return array($rows, $warnings);
		$state = self::notifier_state($settings);

		if (isset($rows[0]['smsnoti']) && intval($rows[0]['smsnoti']) === 1 && !$state['sms']) {
			$rows[0]['smsnoti'] = 0;
			unset($rows[0]['sms_admins_phone_no']);
			foreach ($rows as $i => $row) {
				if ($i > 0 && is_array($row) && isset($row['smsnoti'])) $rows[$i]['smsnoti'] = 0;
			}
			$warnings[] = array(
				'code' => 'sms_disabled_addon_missing',
				'm'    => isset($lang['smsOffAddonMissing']) ? $lang['smsOffAddonMissing'] : 'SMS notifications were turned off because the SMS add-on is not active. Everything else was saved.',
			);
		}
		if (isset($rows[0]['telegramnoti']) && intval($rows[0]['telegramnoti']) === 1 && !$state['telegram']) {
			$rows[0]['telegramnoti'] = 0;
			unset($rows[0]['telegram_bot_token'], $rows[0]['telegram_admin_chat_ids']);
			$warnings[] = array(
				'code' => 'telegram_disabled_addon_missing',
				'm'    => isset($lang['telegramOffAddonMissing']) ? $lang['telegramOffAddonMissing'] : 'Telegram notifications were turned off because the Telegram add-on is not active. Everything else was saved.',
			);
		}
		return array($rows, $warnings);
	}

	/* ---------------------------------------------------------------- B5 */

	/**
	 * B5: the admins' SMS numbers, checked on the server as the builder checks them
	 * (admin-efb.js, case smsAdminsPhoneNoEl): Persian/Arabic digits to 0-9, spaces,
	 * dashes, dots, brackets and direction marks removed, 00 -> +, and on a Persian site
	 * 09xxxxxxxxx -> +989xxxxxxxxx; then ^\+[0-9]{8,15}$.
	 *
	 * @return array{0:string[],1:string[]} [valid, invalid]
	 */
	public static function sms_admin_numbers($raw, $locale = '') {
		$ok = array();
		$bad = array();
		$fa = stripos((string) $locale, 'fa') === 0;
		foreach (explode(',', is_scalar($raw) ? (string) $raw : '') as $p) {
			$p = strtr($p, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
			$p = preg_replace('/[\s\-().\x{200C}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $p);
			if ($p === '' || $p === null) continue;
			if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
			if ($fa && preg_match('/^09[0-9]{9}$/', $p)) $p = '+98' . substr($p, 1);
			if (preg_match('/^\+[0-9]{8,15}$/', $p)) $ok[] = $p; else $bad[] = $p;
		}
		return array(array_values(array_unique($ok)), $bad);
	}

	/** B5: a mobile field asks for the visitor's SMS while the form's SMS is off (no SMS would go out). */
	public static function sms_field_without_form(array $rows) {
		if (!isset($rows[0]) || (isset($rows[0]['smsnoti']) && intval($rows[0]['smsnoti']) === 1)) return false;
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['type'], $r['smsnoti']) && $r['type'] === 'mobile' && intval($r['smsnoti']) === 1) return true;
		}
		return false;
	}

	/* ---------------------------------------------------------------- B4 */

	/** The fixed field ids the public login / registration handlers read. */
	public static function auth_field_ids($type) {
		if ($type === 'login') return array('emaillogin', 'passwordlogin');
		if ($type === 'register') return array('usernameRegisterEFB', 'passwordRegisterEFB', 'emailRegisterEFB');
		return array();
	}

	/** B4: ids a login / register form lacks (an empty list for every other form type). */
	public static function auth_fields_missing(array $rows) {
		$type = isset($rows[0]['type']) ? (string) $rows[0]['type'] : '';
		$have = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_'])) $have[(string) $r['id_']] = true;
		}
		$missing = array();
		foreach (self::auth_field_ids($type) as $id) {
			if (!isset($have[$id])) $missing[] = $id;
		}
		return $missing;
	}

	/* ---------------------------------------------------------------- B3 */

	const PLAN_RANK = array('free' => 0, 'free_plus' => 1, 'pro' => 2);

	/** emsfb_pro as 0|1|2|3 (-1, 10 or missing = 2). Reads the option only: no network, no write. */
	public static function current_package() {
		$v = function_exists('get_option') ? get_option('emsfb_pro', -1) : -1;
		$v = is_numeric($v) ? (int) $v : -1;
		return in_array($v, array(0, 1, 2, 3), true) ? $v : 2;
	}

	/** free | free_plus | pro | pro_pending (a Pro licence that is not active: 0). */
	public static function plan($package = null) {
		$p = $package === null ? self::current_package() : (int) $package;
		if ($p === 1) return 'pro';
		if ($p === 3) return 'free_plus';
		if ($p === 0) return 'pro_pending';
		return 'free';
	}

	/** 'free'|'free_plus'|'pro', or the numbers EFB uses (0 = every plan, 3 = Free Plus, 1 = Pro). */
	public static function normalize_plan($p) {
		if (is_int($p) || (is_string($p) && is_numeric($p))) {
			$p = (int) $p;
			return $p === 1 ? 'pro' : ($p === 3 ? 'free_plus' : 'free');
		}
		$p = (string) $p;
		return isset(self::PLAN_RANK[$p]) ? $p : 'pro';
	}

	public static function plan_at_least($required, $package = null) {
		$have = self::plan($package);
		$rank = isset(self::PLAN_RANK[$have]) ? self::PLAN_RANK[$have] : 0; // pro_pending counts as free
		return $rank >= self::PLAN_RANK[self::normalize_plan($required)];
	}

	/**
	 * B3: the single plan and add-on registry. One entry per field type, form setting and
	 * add-on: which plan unlocks it, which add-on it needs, whether it can be used on this
	 * site now, and the phrase keys and dialog state the builder uses for its lock (so the
	 * text and the gate cannot drift, A4). Exposed to JS as efb_var.capabilities and in
	 * PHP as get_efbFunction()->capabilities_efb(). It is also the registry F18 builds on
	 * (efb_feature_registry_efb(), efb_feature_required_package_efb()).
	 *
	 * Keys: field:<palette id>, setting:<name>, addon:<code>, limit:<name>.
	 * No secret is returned: payment keys are reported as configured true/false only.
	 *
	 * @param array $args package (int, default emsfb_pro), settings (decoded settings object).
	 */
	public static function capabilities(array $args = array()) {
		$package = isset($args['package']) ? (int) $args['package'] : self::current_package();
		$settings = isset($args['settings']) ? $args['settings'] : (function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null);
		$settings = is_array($settings) ? (object) $settings : (is_object($settings) ? $settings : new \stdClass());
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;

		$addon_keys = ($fn && method_exists($fn, 'get_all_addon_keys_efb')) ? $fn->get_all_addon_keys_efb()
			: array('AdnSPF', 'AdnOF', 'AdnPPF', 'AdnATC', 'AdnSS', 'AdnCPF', 'AdnESZ', 'AdnSE', 'AdnWHS', 'AdnPAP', 'AdnWSP', 'AdnSMF', 'AdnPLF', 'AdnMSF', 'AdnBEF', 'AdnPDP', 'AdnADP', 'AdnATF', 'AdnTLG', 'AdnGoS', 'AdnHSH');
		$addon_level = function ($code) use ($fn) {
			if ($fn && method_exists($fn, 'addon_required_package_efb')) return (int) $fn->addon_required_package_efb($code);
			$t = array('AdnOF' => 0, 'AdnSMF' => 3, 'AdnSPF' => 3, 'AdnPAP' => 3);
			return isset($t[$code]) ? $t[$code] : 1;
		};
		$installed = function ($code) use ($settings) {
			if (isset($settings->$code)) return intval($settings->$code) === 1;
			return function_exists('get_option') && intval(get_option('emsfb_addon_' . $code, 0)) === 1;
		};

		$features = array();
		$add = function ($key, $kind, $pkg, $addon, $extra = array()) use (&$features, $package, $installed) {
			$plan_ok = self::plan_at_least($pkg, $package);
			$addon_ok = $addon === null || $installed($addon);
			$entry = array(
				'kind'          => $kind,
				'package'       => $pkg,
				'addon'         => $addon,
				'available_now' => $plan_ok && $addon_ok,
				'reason'        => $plan_ok ? ($addon_ok ? null : 'addon') : 'plan',
				// pro_show_efb(n) state and the phrase it shows: 3 = Free Plus and Pro, 1 = Pro only.
				'prompt_state'  => $pkg === 'free_plus' ? 3 : ($pkg === 'pro' ? 1 : 0),
				'message_key'   => $pkg === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($pkg === 'pro' ? 'proUnlockMsg' : null),
			);
			$features[$key] = array_merge($entry, $extra);
		};

		// Fields: fields_efb[].pro (val-efb.js) through the generated template file; a type whose
		// add-on is Pro stays Pro whatever the palette says.
		$field_addons = array('pdate' => 'AdnPDP', 'ardate' => 'AdnADP', 'persiaPay' => 'AdnPPF', 'stripe' => 'AdnSPF', 'paypal' => 'AdnPAP');
		$file = dirname(__FILE__) . '/data/field-templates-efb.json';
		$data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
		$palette = is_array($data) && isset($data['palette']) ? $data['palette'] : array();
		foreach (array('persiaPay' => array('pro' => true), 'paySelect' => array('pro' => true), 'payMultiselect' => array('pro' => true), 'chlRadio' => array('pro' => true)) as $k => $v) {
			if (!isset($palette[$k])) $palette[$k] = $v;
		}
		foreach ($palette as $type => $info) {
			$addon = isset($field_addons[$type]) ? $field_addons[$type] : null;
			$pkg = empty($info['pro']) ? 'free' : 'free_plus';
			if ($addon !== null) $pkg = self::normalize_plan($addon_level($addon)) === 'pro' ? 'pro' : 'free_plus';
			$add('field:' . $type, 'field', $pkg, $addon, array('label_key' => $type));
		}

		// Form settings (brief B3 list; code evidence in docs/autopilot-integration.md).
		$settings_list = array(
			'setting:email_sub'              => array('free_plus', null, array('email_sub'), 'mlsbjt'),
			'setting:email_noti_type'        => array('free_plus', null, array('email_noti_type'), 'emlc'),
			'setting:thank_you_redirect'     => array('free_plus', null, array('thank_you=rdrct', 'rePage'), 'redirectPage'),
			'setting:loading_type'           => array('free_plus', null, array('loading_type', 'loading_color'), 'loadingType'),
			'setting:custom_required_msg'    => array('free_plus', null, array('customRequiredMsg'), 'customMessage'),
			'setting:custom_colors'          => array('free_plus', null, array('checked_color', 'range_thumb_color', 'range_value_color', 'switch_on_color', 'switch_off_color', 'switch_handle_color'), 'colors'),
			'setting:hidden_disabled'        => array('free_plus', null, array('hidden', 'disabled'), 'hide'),
			'setting:mobile_layout'          => array('pro', null, array('mobile_*'), 'mobileView'),
			'setting:sms_notifications'      => array('pro', 'AdnSS', array('smsnoti', 'sms_admins_phone_no', 'sms_msg_new_noti', 'sms_msg_recived_usr', 'sms_msg_responsed_noti'), 'sms_noti'),
			'setting:telegram_notifications' => array('pro', 'AdnTLG', array('telegramnoti', 'telegram_msg_new_noti', 'telegram_msg_responsed_noti'), 'etelegramno'),
			'setting:logic_rules'            => array('free_plus', 'AdnSMF', array('logic_rules', 'notification_rules'), 'conditionalLogic'),
			'setting:logic_calculate'        => array('pro', 'AdnSMF', array('logic_rules[].actions[].type=calculate'), 'conditionalLogic'),
			'setting:confirmation_rules'     => array('pro', 'AdnSMF', array('confirmation_rules'), 'conditionalLogic'),
			'setting:webhook_rules'          => array('pro', 'AdnSMF', array('webhook_rules'), 'conditionalLogic'),
			'setting:payment_stripe'         => array('free_plus', 'AdnSPF', array('getway=stripe', 'currency', 'paymentmethod'), 'stripe'),
			'setting:payment_paypal'         => array('free_plus', 'AdnPAP', array('getway=paypal', 'currency', 'paymentmethod'), 'paypal'),
			'setting:payment_persiapay'      => array('pro', 'AdnPPF', array('getway=persiaPay', 'persiaPay'), 'persiaPayment'),
		);
		foreach ($settings_list as $key => $s) $add($key, 'setting', $s[0], $s[1], array('form_keys' => $s[2], 'label_key' => $s[3]));

		// Limits.
		$add('limit:steps', 'limit', 'free_plus', null, array('limits' => array('free' => 2, 'free_plus' => null, 'pro' => null), 'label_key' => 'step'));
		$add('limit:logic', 'limit', 'pro', 'AdnSMF', array('limits' => array(
			'free'      => array('field_rules' => 0, 'notification_rules' => 0, 'conditions_per_group' => 0),
			'free_plus' => array('field_rules' => 3, 'notification_rules' => 2, 'conditions_per_group' => 2),
			'pro'       => null,
		), 'label_key' => 'conditionalLogic'));

		// Add-ons.
		$addons = array();
		foreach ($addon_keys as $code) {
			$pkg = self::normalize_plan($addon_level($code));
			$is = $installed($code);
			$add('addon:' . $code, 'addon', $pkg, $code, array('installed' => $is, 'can_install' => self::plan_at_least($pkg, $package)));
			$addons[$code] = $is;
		}

		$key_set = function ($name) use ($settings) {
			return isset($settings->$name) && is_string($settings->$name) && strlen(trim($settings->$name)) > 5 && $settings->$name !== 'NotFoundEl';
		};
		$out = array(
			'api_version'  => defined('EMSFB_API_VERSION') ? EMSFB_API_VERSION : 1,
			'efb_version'  => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
			'plan'         => array('package_type' => $package, 'name' => self::plan($package)),
			'features'     => $features,
			'addons'       => $addons,
			// Presence only, never the keys (brief rule 4).
			'payment_keys' => array(
				'stripe' => $key_set('stripeSKey') && $key_set('stripePKey'),
				'paypal' => $key_set('paypalSKey') && $key_set('paypalPKey'),
			),
		);
		return function_exists('apply_filters') ? apply_filters('efb_capabilities', $out, $args) : $out;
	}

	/** efb_admin_localize_vars: efb_var.api_version and efb_var.capabilities on every EFB admin screen. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['api_version'] = defined('EMSFB_API_VERSION') ? EMSFB_API_VERSION : 1;
		$vars['capabilities'] = self::capabilities();
		return $vars;
	}

	/* ---------------------------------------------------------------- B2 */

	private static $templates_cache = null;

	/** The raw template catalog (includes/data/field-templates-efb.json, built from the builder's own code). */
	public static function field_template_catalog() {
		if (self::$templates_cache === null) {
			$file = dirname(__FILE__) . '/data/field-templates-efb.json';
			$raw = is_readable($file) ? file_get_contents($file) : '';
			$data = $raw !== '' ? json_decode($raw, true) : null;
			self::$templates_cache = (is_array($data) && isset($data['templates']) && is_array($data['templates'])) ? $data['templates'] : array();
		}
		return self::$templates_cache;
	}

	/**
	 * B2: the rows the builder creates when a palette item is dropped, for server-side
	 * form building. Same keys and defaults as efbBuilderApi.newItems() (the data file is
	 * generated from it and a test fails when they drift), phrases in the site language,
	 * fresh ids on every call. Options not given here behave like the builder.
	 *
	 * @param string|null $palette_id e.g. 'email', 'address' (5 fields), 'name' (2 fields); null = every template.
	 * @param array       $args       label, options (list of strings or {value, price, id_op}), required, size,
	 *                                step (for 'steps'), rtl (default is_rtl()), text (phrase map; default text_efb(1)).
	 * @return array|\WP_Error|null
	 */
	public static function field_templates($palette_id = null, array $args = array()) {
		$catalog = self::field_template_catalog();
		$version = array(
			'efb_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
			'api_version' => defined('EMSFB_API_VERSION') ? EMSFB_API_VERSION : 1,
		);
		if (!isset($args['text']) || !is_array($args['text'])) {
			$args['text'] = function_exists('get_efbFunction') ? (array) get_efbFunction()->text_efb(1) : array();
		}
		if (!isset($args['rtl'])) $args['rtl'] = function_exists('is_rtl') ? is_rtl() : false;

		if ($palette_id === null) {
			$all = array();
			foreach (array_keys($catalog) as $id) {
				$one = self::build_template($catalog[$id], $args, $id);
				$all[$id] = array('rows' => $one['rows'], 'form_patch' => $one['form_patch']);
			}
			$out = $version + array('templates' => $all);
		} else {
			$palette_id = (string) $palette_id;
			if (!isset($catalog[$palette_id])) {
				return class_exists('\WP_Error') ? new \WP_Error('unknown_palette_id', 'Unknown palette id.') : null;
			}
			$one = self::build_template($catalog[$palette_id], $args, $palette_id);
			if (isset($one['error'])) return class_exists('\WP_Error') ? new \WP_Error($one['error'], $one['error']) : null;
			$out = $version + array('palette_id' => $palette_id) + $one;
		}
		return function_exists('apply_filters') ? apply_filters('efb_field_templates', $out, $palette_id, $args) : $out;
	}

	/** Default countries (241, ISO 3166 alpha-2), and the states and cities a new address starts with. */
	public static function geo_defaults(array $args = array()) {
		$t = self::field_templates('address', $args);
		$geo = array('countries' => array(), 'default_states' => array(), 'default_cities' => array(), 'default_country' => '', 'default_state' => '');
		if (!is_array($t) || empty($t['rows'])) return $geo;
		$by_id = array();
		foreach ($t['rows'] as $r) { if (isset($r['id_'])) $by_id[$r['id_']] = $r; }
		foreach ($t['rows'] as $r) {
			if (!isset($r['parent'], $by_id[$r['parent']])) continue;
			$parent = $by_id[$r['parent']];
			$entry = array('value' => isset($r['value']) ? $r['value'] : '', 'code' => isset($r['id_op']) ? $r['id_op'] : '');
			if (isset($r['s2'])) $entry['code'] = $r['s2'];
			if ($parent['type'] === 'conturyList') $geo['countries'][] = $entry;
			elseif ($parent['type'] === 'stateProvince') { $geo['default_states'][] = $entry; $geo['default_country'] = isset($parent['country']) ? $parent['country'] : ''; }
			elseif ($parent['type'] === 'cityList') { $geo['default_cities'][] = $entry; $geo['default_state'] = isset($parent['statePov']) ? $parent['statePov'] : ''; }
		}
		return $geo;
	}

	private static function new_id() {
		$id = '';
		try { $bytes = random_bytes(12); } catch (\Exception $e) { $bytes = uniqid('', true); }
		foreach (str_split($bytes) as $b) $id .= base_convert((string) (ord($b) % 36), 10, 36);
		return substr($id, 0, 9);
	}

	private static function build_template($tpl, array $args, $palette_id) {
		$ids = array();
		$text = $args['text'];
		$lower = array();
		foreach ($text as $k => $v) { if (is_string($k) && is_string($v)) $lower[strtolower($k)] = $v; }
		$head = defined('EMSFB_PLUGIN_URL') ? EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png' : '';
		$head = preg_replace('#/+#', '@efb@', preg_replace('#^(https?)://#', '$1:@efb@', $head));
		$step = isset($args['step']) ? (string) intval($args['step']) : '2';
		$resolve = function ($s) use (&$ids, $text, $lower, $head, $step) {
			return preg_replace_callback('/\x{27E6}([^\x{27E7}]*)\x{27E7}/u', function ($m) use (&$ids, $text, $lower, $head, $step) {
				$tok = $m[1];
				if (strpos($tok, 'id:') === 0) {
					if (!isset($ids[$tok])) $ids[$tok] = self::new_id();
					return $ids[$tok];
				}
				if ($tok === 'step') return $step;
				if ($tok === 'img:head') return $head;
				$parts = explode("\u{00A6}", $tok);
				$key = array_shift($parts);
				if (isset($text[$key]) && is_string($text[$key])) {
					$value = $text[$key];
				} elseif (isset($lower[strtolower($key)])) {
					// The builder upper-cased this phrase (step names).
					$value = function_exists('mb_strtoupper') ? mb_strtoupper($lower[strtolower($key)], 'UTF-8') : strtoupper($lower[strtolower($key)]);
				} else {
					$value = '';
				}
				foreach ($parts as $i => $arg) {
					$ph = '%' . ($i + 1) . '$s';
					if ($arg !== $ph) $value = str_replace($ph, $arg, $value);
				}
				return $value;
			}, $s);
		};
		$walk = function ($v) use (&$walk, $resolve) {
			if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $walk($x); return $v; }
			return is_string($v) ? $resolve($v) : $v;
		};
		$rows = $walk(isset($tpl['rows']) ? $tpl['rows'] : array());
		$patch = isset($tpl['form_patch']) && is_array($tpl['form_patch']) ? $walk($tpl['form_patch']) : null;

		$fields = array();
		foreach ($rows as $i => $r) {
			if (!empty($args['rtl']) && isset($r['label_align']) && $r['label_align'] === 'txt-left') $rows[$i]['label_align'] = 'txt-right';
			if (!isset($r['type']) || in_array($r['type'], array('option', 'r_matrix'), true)) continue;
			$fields[] = $i;
		}
		if (count($fields) === 1) {
			$f = $fields[0];
			if (isset($args['label'])) $rows[$f]['name'] = (string) $args['label'];
		}
		foreach ($fields as $f) {
			if (isset($args['required']) && array_key_exists('required', $rows[$f])) $rows[$f]['required'] = $args['required'] ? 1 : 0;
			if (isset($args['size']) && array_key_exists('size', $rows[$f])) $rows[$f]['size'] = $args['size'];
		}
		if (isset($args['options']) && is_array($args['options']) && count($fields) === 1) {
			$parent = $rows[$fields[0]]['id_'];
			$tplrow = null;
			$kept = array();
			foreach ($rows as $r) {
				if (isset($r['parent']) && $r['parent'] === $parent) { if ($tplrow === null) $tplrow = $r; continue; }
				$kept[] = $r;
			}
			if ($tplrow === null) return array('error' => 'no_options');
			foreach ($args['options'] as $o) {
				$row = $tplrow;
				$id = self::new_id();
				$row['id_'] = $id;
				$row['dataId'] = $id . '-id';
				$row['id_op'] = (is_array($o) && isset($o['id_op'])) ? (string) $o['id_op'] : $id;
				$row['value'] = (string) (is_array($o) ? (isset($o['value']) ? $o['value'] : '') : $o);
				if (is_array($o) && isset($o['price']) && array_key_exists('price', $tplrow)) $row['price'] = (float) $o['price'];
				$kept[] = $row;
			}
			$rows = $kept;
		}
		return array('rows' => array_values($rows), 'form_patch' => $patch);
	}

	/**
	 * A7: "NotFoundEl" is what the settings screen reports for a control it could not
	 * read. It must never be stored as a value (it replaced a working Stripe secret on
	 * an old build). For every key that arrives as the marker the stored value is
	 * kept; a key with no usable stored value is left out. Only the key name is logged.
	 *
	 * @param array             $m      Settings as posted by the screen.
	 * @param object|array|null $stored Settings currently saved.
	 * @return array
	 */
	public static function drop_unread_setting_markers(array $m, $stored) {
		$stored = is_array($stored) ? (object) $stored : (is_object($stored) ? $stored : null);
		foreach ($m as $key => $value) {
			if ($value !== 'NotFoundEl') continue;
			if ($stored !== null && isset($stored->$key) && $stored->$key !== 'NotFoundEl') {
				$m[$key] = $stored->$key;
			} else {
				unset($m[$key]);
			}
			error_log('[EFB] settings: "' . preg_replace('/[^A-Za-z0-9_]/', '', (string) $key) . '" arrived unread (NotFoundEl); the stored value was kept.');
		}
		return $m;
	}
}

if (function_exists('add_filter')) {
	add_filter('efb_admin_localize_vars', array('\Emsfb\Builder_Api', 'localize_vars'), 10, 2);
}

// Global plan helpers (efb_plan_efb(), efb_plan_at_least_efb(), ...): one registry for every gate.
require_once __DIR__ . '/builder-api-functions-efb.php';
