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

	/** F18: the phrase that names each add-on (the titles of the Add-ons page). */
	const ADDON_LABEL_KEYS = array(
		'AdnSS' => 'smsTAddon', 'AdnSPF' => 'stripeTAddon', 'AdnPPF' => 'persiaPayTAddon', 'AdnATC' => 'trackCTAddon',
		'AdnPDP' => 'datePTAddon', 'AdnADP' => 'dateATAddon', 'AdnOF' => 'offlineTAddon', 'AdnATF' => 'TAdnAtF',
		'AdnPAP' => 'payPalTAddon', 'AdnTLG' => 'tlgmAddon', 'AdnGoS' => 'TAdnGoS', 'AdnSMF' => 'condATAddon', 'AdnHSH' => 'TAdnHSH',
	);

	/** B9: row-0 keys that no JS or PHP reads (checked by tests/test-ap-b9-deprecated-keys.js). */
	const DEPRECATED_FORM_KEYS = array(
		'private'    => 'Not read. A private form (logged-in visitors only) is stateForm = 1.',
		'email_temp' => 'Not read. The notification email template is the global setting emailTemp.',
	);

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
			'setting:custom_required_msg'    => array('free_plus', null, array('customRequiredMsg'), 'upgFtrRequiredMsg'),
			'setting:custom_colors'          => array('free_plus', null, array('checked_color', 'range_thumb_color', 'range_value_color', 'switch_on_color', 'switch_off_color', 'switch_handle_color'), 'upgFtrColors'),
			'setting:hidden_disabled'        => array('free_plus', null, array('hidden', 'disabled'), 'upgFtrHideDisable'),
			'setting:mobile_layout'          => array('pro', null, array('mobile_*'), 'upgFtrMobileLayout'),
			// F19: per-step background (step rows, and row 0 as the form default). mobile_step_bg is under mobile_layout.
			'setting:step_bg_color'          => array('free', null, array('step_bg_type=solid', 'step_bg_color', 'step_bg_opacity', 'step_bg_text'), 'upgFtrStepBgColor'),
			'setting:step_bg_gradient'       => array('free_plus', null, array('step_bg_type=linear', 'step_bg_type=radial', 'step_bg_stops', 'step_bg_angle', 'step_bg_pos'), 'upgFtrStepBgGradient'),
			// F13: privacy keys of the form (free: legal requirements, never behind a plan).
			'setting:privacy_ip'             => array('free', null, array('prv_ip'), 'prvIpMode'),
			'setting:privacy_retention'      => array('free', null, array('prv_days'), 'prvDays'),
			'setting:privacy_nostore'        => array('free', null, array('prv_nostore'), 'prvNoStore'),
			'setting:privacy_requests'       => array('free', null, array('prv_id_field', 'prv_pii'), 'prvIdField'),
			'setting:sms_notifications'      => array('pro', 'AdnSS', array('smsnoti', 'sms_admins_phone_no', 'sms_msg_new_noti', 'sms_msg_recived_usr', 'sms_msg_responsed_noti'), 'sms_noti'),
			'setting:telegram_notifications' => array('pro', 'AdnTLG', array('telegramnoti', 'telegram_msg_new_noti', 'telegram_msg_responsed_noti'), 'etelegramno'),
			'setting:logic_rules'            => array('free_plus', 'AdnSMF', array('logic_rules', 'notification_rules'), 'conlog'),
			'setting:logic_calculate'        => array('pro', 'AdnSMF', array('logic_rules[].actions[].type=calculate'), 'conlog'),
			'setting:confirmation_rules'     => array('pro', 'AdnSMF', array('confirmation_rules'), 'conlog'),
			'setting:webhook_rules'          => array('pro', 'AdnSMF', array('webhook_rules'), 'conlog'),
			'setting:payment_stripe'         => array('free_plus', 'AdnSPF', array('getway=stripe', 'currency', 'paymentmethod'), 'stripe'),
			'setting:payment_paypal'         => array('free_plus', 'AdnPAP', array('getway=paypal', 'currency', 'paymentmethod'), 'paypal'),
			'setting:payment_persiapay'      => array('pro', 'AdnPPF', array('getway=persiaPay', 'persiaPay'), 'persiaPayment'),
		);
		foreach ($settings_list as $key => $s) $add($key, 'setting', $s[0], $s[1], array('form_keys' => $s[2], 'label_key' => $s[3]));

		// F18: site settings and screens outside the form JSON that the upgrade dialog names. No form
		// key (scope "site"); the level is the gate the code uses today (pro_efb = Free Plus and Pro,
		// package_type 1 = Pro only).
		$site_list = array(
			'setting:reply_attachments'    => array('free_plus', 'upgFtrReplyAttach'),
			'setting:sender_email'         => array('free_plus', 'upgFtrSenderEmail'),
			'setting:weekly_report'        => array('free_plus', 'weeklyEmailReport'),
			'setting:email_stats_off'      => array('pro', 'emailStatsReport'),
			'setting:email_footer'         => array('free_plus', 'upgFtrEmailCredit'),
			'setting:bulk_read'            => array('free_plus', 'upgFtrBulkRead'),
			'setting:delete_response'      => array('free_plus', 'upgFtrDeleteResponse'),
			'setting:payment_keys'         => array('free_plus', 'upgFtrPaymentKeys'),
			'setting:map_key'              => array('free_plus', 'upgFtrMapKey'),
			'setting:response_colors'      => array('free_plus', 'respColors'),
			'setting:response_captcha'     => array('free_plus', 'scaptcha'),
			'setting:response_upload'      => array('free_plus', 'dsupfile'),
			'setting:response_download'    => array('free_plus', 'sdlbtn'),
			'setting:response_admin_login' => array('free_plus', 'admines'),
		);
		foreach ($site_list as $key => $s) $add($key, 'setting', $s[0], null, array('form_keys' => array(), 'scope' => 'site', 'label_key' => $s[1]));
		// F13: the site's privacy settings (Settings > Privacy), free. settings_keys are the
		// emsfb_setting keys; only an administrator (manage_options) may change them.
		$add('setting:privacy_site', 'setting', 'free', null, array('form_keys' => array(), 'scope' => 'site', 'label_key' => 'prvTitle',
			'settings_keys' => array('prv_ip_mode', 'prv_days', 'prv_action', 'prv_files', 'prv_nostore_file_days', 'prv_erase_mode')));
		// The "payment form" template starts a Stripe form: Free Plus, and the Stripe add-on.
		$add('template:payment', 'template', 'free_plus', 'AdnSPF', array('label_key' => 'upgFtrPaymentForm'));
		// Saving a form that holds fields the plan does not include (imported, or after a downgrade).
		// The dialog asks for Pro instead when one of those fields is Pro only.
		$add('form:locked_fields', 'form', 'free_plus', null, array('label_key' => 'upgFtrLockedFields'));

		// Limits.
		$add('limit:steps', 'limit', 'free_plus', null, array('limits' => array('free' => 2, 'free_plus' => null, 'pro' => null), 'label_key' => 'step'));
		$add('limit:logic', 'limit', 'pro', 'AdnSMF', array('limits' => array(
			'free'      => array('field_rules' => 0, 'notification_rules' => 0, 'conditions_per_group' => 0),
			'free_plus' => array('field_rules' => 3, 'notification_rules' => 2, 'conditions_per_group' => 2),
			'pro'       => null,
		), 'label_key' => 'conlog'));

		// Add-ons.
		$addons = array();
		foreach ($addon_keys as $code) {
			$pkg = self::normalize_plan($addon_level($code));
			$is = $installed($code);
			$add('addon:' . $code, 'addon', $pkg, $code, array('installed' => $is, 'can_install' => self::plan_at_least($pkg, $package), 'label_key' => isset(self::ADDON_LABEL_KEYS[$code]) ? self::ADDON_LABEL_KEYS[$code] : $code));
			$addons[$code] = $is;
		}

		$key_set = function ($name) use ($settings) {
			return isset($settings->$name) && is_string($settings->$name) && strlen(trim($settings->$name)) > 5 && $settings->$name !== 'NotFoundEl';
		};
		$out = array(
			'api_version'  => defined('EMSFB_API_VERSION') ? EMSFB_API_VERSION : 1,
			// B9: form keys nothing reads any more; kept on old forms, never needed on new ones.
			'deprecated_keys' => self::DEPRECATED_FORM_KEYS,
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
		// F18: the upgrade dialog shows "ask an administrator" (S7) when this is false.
		$vars['can_change_plan'] = function_exists('efb_can_change_plan_efb') ? efb_can_change_plan_efb() : true;
		return $vars;
	}

	/* ---------------------------------------------------------------- B8 */

	/** Settings without secrets: gateway secret keys, the licence, tokens, passwords. */
	public static function redact_settings($settings) {
		$s = is_object($settings) ? clone $settings : (is_array($settings) ? (object) $settings : new \stdClass());
		foreach (array_keys(get_object_vars($s)) as $k) {
			if (in_array($k, array('stripeSKey', 'paypalSKey', 'secretKey', 'activeCode', 'payToken', 'email_key', 'apiKeyMap'), true)
				|| preg_match('/(secret|token|password|passwd|apikey|_key$|SKey$)/i', $k)) {
				unset($s->$k);
			}
		}
		return $s;
	}

	/**
	 * B8: the answer of add_addons_Emsfb after an install. Keeps the old keys
	 * (r = done, value, new) and adds the fresh add-on flags (efb_var.addons), the
	 * capability registry and requires_reload, so the page (or an integration) can
	 * update itself without reloading first. `new` no longer carries secrets.
	 */
	public static function addon_install_response($addon, $settings, $fn = null) {
		$fn = $fn ?: (function_exists('get_efbFunction') ? get_efbFunction() : null);
		$addons = ($fn && method_exists($fn, 'fun_get_addons_list_efb')) ? $fn->fun_get_addons_list_efb(is_object($settings) ? $settings : (object) $settings) : array();
		return array(
			'success'         => true,
			'r'               => 'done',
			'value'           => 'add_addons_Emsfb',
			'addon'           => (string) $addon,
			'addons'          => $addons,
			'capabilities'    => self::capabilities(array('settings' => $settings)),
			// The add-on's own scripts are enqueued on the next page load; nothing in core
			// can load them safely in place, so the page is reloaded unless the listener of
			// efb:addon-installed cancels it (and reloads later, e.g. after a Save).
			'requires_reload' => true,
			'new'             => json_encode(self::redact_settings($settings), JSON_UNESCAPED_UNICODE),
		);
	}

	/* ---------------------------------------------------------------- B7 */

	/**
	 * B7: the price of a price field (prcfld) that a logic rule computes (calculate,
	 * set_value, copy_value, clear_value) comes from the server, never from the browser.
	 * The add-on's engine runs on the submitted answers WITHOUT the browser's rows of those
	 * fields, so it starts from an empty field, exactly like a fresh form in the browser:
	 * an answer the request leaves out is unanswered (empty, 0 in a formula), a rule that
	 * does not match writes nothing and the field is not charged. Every browser row of such
	 * a field is replaced by one row carrying the computed value as `value` and `price`, or
	 * removed when the field is hidden / disabled by the rules or computes to nothing. A rule
	 * that matches but cannot produce a number (a formula that divides by 0 or names an
	 * unknown field, a text written into the price) or rules that never settle refuse the
	 * payment (price_not_computable). Forms without such a rule are returned unchanged (a
	 * price field the visitor types stays as it was, bounded by its min/max in
	 * fun_validation_pay_elements_efb()).
	 *
	 * @param array $rows Submitted rows (list of associative arrays).
	 * @param array $form The stored form (associative rows).
	 * @return array{0:array,1:string|null} [rows, error code or null]
	 */
	public static function apply_server_prices(array $rows, array $form) {
		$fields = array();
		foreach ($form as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_'], $r['type'])) $fields[(string) $r['id_']] = $r;
		}
		$targets = array();
		$calculated_by = array(); // target => ids of the rules that calculate it
		$rules = isset($form[0]['logic_rules']) && is_array($form[0]['logic_rules']) ? $form[0]['logic_rules'] : array();
		$engine_form = $form;
		foreach ($rules as $position => $rule) {
			if (!is_array($rule) || (array_key_exists('enabled', $rule) && empty($rule['enabled'])) || empty($rule['actions']) || !is_array($rule['actions'])) continue;
			// Only rules the engines run: both skip a rule without conditions.
			if (empty($rule['conditions']['items']) || !is_array($rule['conditions']['items'])) continue;
			// The browser runs a rule saved without `enabled`; the server engine must too.
			if (!array_key_exists('enabled', $rule)) $engine_form[0]['logic_rules'][$position]['enabled'] = true;
			$rule_id = isset($rule['id']) ? (string) $rule['id'] : 'rule_' . $position;
			foreach ($rule['actions'] as $a) {
				$t = is_array($a) && isset($a['target']) ? (string) $a['target'] : '';
				$type = is_array($a) && isset($a['type']) ? $a['type'] : '';
				if ($t !== '' && isset($fields[$t]) && $fields[$t]['type'] === 'prcfld' && in_array($type, array('calculate', 'set_value', 'copy_value', 'clear_value'), true)) {
					$targets[$t] = true;
					if ($type === 'calculate') $calculated_by[$t][$rule_id] = true;
					else $calculated_by[$t]['#other'] = true;
				}
			}
		}
		if (empty($targets)) return array($rows, null);
		$validator = self::logic_validator();
		// Without the add-on no rule runs in the browser either: the field is typed, as before.
		if ($validator === null) return array($rows, null);
		// The answers, without what the browser says about the computed fields.
		$answers = array();
		foreach ($rows as $row) {
			if (is_array($row) && isset($row['id_']) && isset($targets[(string) $row['id_']])) continue;
			$answers[] = $row;
		}
		try {
			$result = $validator->evaluate($engine_form, $answers);
		} catch (\Throwable $e) {
			return array($rows, 'price_not_computable');
		}
		if (!is_array($result) || empty($result['is_conditional'])) return array($rows, 'price_not_computable');
		if (isset($result['stabilized']) && $result['stabilized'] === false) return array($rows, 'price_not_computable');
		$ignored = array_flip(isset($result['ignored_fields']) ? (array) $result['ignored_fields'] : array());
		$matched = array_flip(array_map('strval', isset($result['matched_rules']) ? (array) $result['matched_rules'] : array()));
		$values = isset($result['values_map']) && is_array($result['values_map']) ? $result['values_map'] : array();
		$out = $answers;
		foreach (array_keys($targets) as $id) {
			if (isset($ignored[$id])) continue;
			$v = array_key_exists($id, $values) ? $values[$id] : '';
			if ($v === '' || $v === null || $v === array()) {
				/* Empty: no rule wrote the field, or one cleared it - nothing to charge.
				 * Unless the only rules that matched for it calculate it: then the
				 * formula failed (division by 0, unknown field) and there is no price. */
				$by = isset($calculated_by[$id]) ? $calculated_by[$id] : array();
				$failed = false;
				if (!isset($by['#other'])) {
					foreach (array_keys($by) as $rid) { if (isset($matched[(string) $rid])) { $failed = true; break; } }
				}
				if ($failed) return array($rows, 'price_not_computable');
				continue;
			}
			if (is_array($v) || !is_numeric($v)) return array($rows, 'price_not_computable');
			$v = (string) (0 + $v);
			$out[] = array(
				'id_' => $id, 'id_ob' => $id . '_', 'type' => 'prcfld',
				'name' => isset($fields[$id]['name']) ? (string) $fields[$id]['name'] : '',
				'amount' => isset($fields[$id]['amount']) ? $fields[$id]['amount'] : '',
				'value' => $v, 'price' => $v,
			);
		}
		return array($out, null);
	}

	/**
	 * B7: the browser sent a price for a server-priced field that differs from what the
	 * server computed (also when the server charges nothing for it). Compared per field,
	 * so a computed row the add-on sends without a price next to a typed price field does
	 * not count as a difference.
	 */
	public static function server_price_differs(array $before, array $after) {
		$sum = function ($rows, $id) {
			$s = 0.0;
			foreach ($rows as $r) {
				if (is_array($r) && isset($r['type'], $r['id_']) && $r['type'] === 'prcfld' && (string) $r['id_'] === $id && isset($r['price']) && is_numeric($r['price'])) $s += abs((float) $r['price']);
			}
			return $s;
		};
		$sent = array();
		foreach ($before as $r) {
			if (is_array($r) && isset($r['type'], $r['id_']) && $r['type'] === 'prcfld' && isset($r['price'])) $sent[(string) $r['id_']] = true;
		}
		foreach (array_keys($sent) as $id) {
			if (abs($sum($before, (string) $id) - $sum($after, (string) $id)) > 0.000001) return true;
		}
		return false;
	}

	/* ---------------------------------------------------------------- B6 */

	const LOGIC_ACTIONS = array('show_field', 'hide_field', 'set_required', 'set_optional', 'enable_field', 'disable_field', 'show_step', 'hide_step', 'jump_to_step', 'set_value', 'copy_value', 'calculate', 'clear_value', 'show_message', 'set_placeholder', 'set_help', 'set_label', 'focus_field', 'scroll_to_field', 'block_submit', 'end_form');
	const LOGIC_COMPARES = array('is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty', 'is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt', 'date_before', 'date_after', 'date_between');
	const LOGIC_SOURCES = array('field', 'query_param', 'user', 'current_step');
	const LOGIC_SCOPES = array('field', 'step', 'notification', 'confirmation', 'webhook', 'pricing');

	/**
	 * The Conditional Logic add-on's validator, when the add-on is active (class-Emsfb.php
	 * loads its file then). The file is never loaded from here: loading it registers the
	 * add-on's submission filters, which must stay off while the add-on is switched off.
	 */
	public static function logic_validator() {
		return class_exists('\Emsfb\Emsfb_Logic_Validator') ? new \Emsfb\Emsfb_Logic_Validator() : null;
	}

	/**
	 * B6: check the conditional-logic rules of a form before they are written, so nothing
	 * EFB would drop or refuse reaches the form. The structural checks mirror what
	 * sanitize_obj_msg_efb() keeps (functions.php: sanitize_logic_rules() and siblings,
	 * which silently drop what is wrong); the evaluation runs through the add-on's own
	 * Emsfb_Logic_Validator (formula, loops) when it is installed, otherwise the result
	 * carries the error logic_addon_missing. Plan limits come from the capability registry.
	 * Contract and codes: docs/logic-rules-schema.md.
	 *
	 * @param array $form_items The whole form (row 0 holds the rule collections).
	 * @param array $args       package (plan override, 0|1|2|3).
	 * @return array{ok:bool, available:bool, errors:array, warnings:array, limits:array, api_version:int}
	 */
	public static function logic_validate_rules(array $form_items, array $args = array()) {
		$errors = array();
		$warnings = array();
		$err = function ($code, $collection, $rule_id, $path, $extra = array()) use (&$errors) {
			$errors[] = array_merge(array('code' => $code, 'collection' => $collection, 'rule_id' => ($rule_id === '' ? null : $rule_id), 'path' => $path), $extra);
		};
		$warn = function ($code, $collection, $rule_id, $path, $extra = array()) use (&$warnings) {
			$warnings[] = array_merge(array('code' => $code, 'collection' => $collection, 'rule_id' => ($rule_id === '' ? null : $rule_id), 'path' => $path), $extra);
		};
		$row0 = isset($form_items[0]) && is_array($form_items[0]) ? $form_items[0] : array();
		$fields = array();
		$steps = array();
		foreach ($form_items as $i => $r) {
			if ($i === 0 || !is_array($r) || !isset($r['id_']) || $r['id_'] === '') continue;
			$t = isset($r['type']) ? (string) $r['type'] : '';
			if ($t === 'step') $steps[(string) $r['id_']] = $r;
			elseif (!in_array($t, array('form', 'option', 'r_matrix', 'buttonNav'), true)) $fields[(string) $r['id_']] = $r;
		}
		$package = isset($args['package']) ? (int) $args['package'] : self::current_package();
		$plan = self::plan($package);
		$caps = self::capabilities(array('package' => $package, 'settings' => isset($args['settings']) ? $args['settings'] : null));
		$logic_limits = isset($caps['features']['limit:logic']['limits']) ? $caps['features']['limit:logic']['limits'] : array();
		$limits = ($plan === 'pro') ? null : (isset($logic_limits[$plan]) ? $logic_limits[$plan] : (isset($logic_limits['free']) ? $logic_limits['free'] : null));

		$check_group = function ($group, $collection, $rule_id, $path, $depth = 0) use (&$check_group, $err, $warn, $fields, $limits) {
			if (!is_array($group)) { $err('invalid_conditions', $collection, $rule_id, $path); return 0; }
			$op = strtoupper((string) (isset($group['operator']) ? $group['operator'] : 'AND'));
			if (!in_array($op, array('AND', 'OR'), true)) $err('invalid_group_operator', $collection, $rule_id, $path . '.operator');
			$items = isset($group['items']) && is_array($group['items']) ? $group['items'] : array();
			// The add-on's builder counts direct conditions (not nested groups), on field rules only.
			$direct = count(array_filter($items, function ($it) { return is_array($it) && !((isset($it['type']) && $it['type'] === 'group') || isset($it['items'])); }));
			if ($collection === 'logic_rules' && is_array($limits) && isset($limits['conditions_per_group']) && $direct > $limits['conditions_per_group']) {
				$err('limit_conditions_per_group', $collection, $rule_id, $path, array('max' => $limits['conditions_per_group']));
			}
			$count = 0;
			foreach ($items as $k => $it) {
				$p = $path . '.items[' . $k . ']';
				if (!is_array($it)) { $err('invalid_condition', $collection, $rule_id, $p); continue; }
				if ((isset($it['type']) && $it['type'] === 'group') || isset($it['items'])) {
					if ($depth >= 5) { $err('conditions_too_deep', $collection, $rule_id, $p); continue; }
					$count += $check_group($it, $collection, $rule_id, $p, $depth + 1);
					continue;
				}
				$count++;
				$source = isset($it['source']) ? (string) $it['source'] : 'field';
				$fid = isset($it['field_id']) ? (string) $it['field_id'] : '';
				$cmp = isset($it['compare']) ? (string) $it['compare'] : 'is';
				if (!in_array($source, self::LOGIC_SOURCES, true)) $err('invalid_source', $collection, $rule_id, $p . '.source');
				elseif ($source === 'field' && ($fid === '' || !isset($fields[$fid]))) $err('unknown_field', $collection, $rule_id, $p . '.field_id', array('field_id' => $fid));
				elseif ($source === 'user' && !in_array($fid, array('logged_in', 'role'), true)) $err('invalid_user_condition', $collection, $rule_id, $p . '.field_id');
				elseif ($source === 'query_param' && preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', (string) (isset($it['param']) ? $it['param'] : $fid)) === '') $err('missing_param', $collection, $rule_id, $p . '.param');
				if (!in_array($cmp, self::LOGIC_COMPARES, true)) $err('invalid_compare', $collection, $rule_id, $p . '.compare', array('compare' => $cmp));
				$v = isset($it['value']) ? $it['value'] : '';
				if (in_array($cmp, array('between', 'not_between', 'date_between'), true) && !(is_array($v) && count($v) === 2) && !(is_string($v) && count(explode(',', $v)) === 2)) {
					$err('invalid_range', $collection, $rule_id, $p . '.value');
				}
				if (in_array($cmp, array('is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt'), true) && isset($fields[$fid]) && !in_array(strtolower((string) $fields[$fid]['type']), array('payment', 'stripe', 'paypal', 'persiapay'), true)) {
					$warn('payment_compare_on_non_payment_field', $collection, $rule_id, $p);
				}
			}
			return $count;
		};

		$collections = array('logic_rules', 'notification_rules', 'confirmation_rules', 'webhook_rules');
		$counts = array();
		foreach ($collections as $col) {
			if (!isset($row0[$col])) { $counts[$col] = 0; continue; }
			if (!is_array($row0[$col])) { $err('invalid_collection', $col, null, $col); $counts[$col] = 0; continue; }
			$rules = $row0[$col];
			$counts[$col] = count($rules);
			$ids = array();
			foreach ($rules as $n => $rule) {
				$path = $col . '[' . $n . ']';
				if (!is_array($rule)) { $err('invalid_rule', $col, null, $path); continue; }
				$rid = isset($rule['id']) && is_scalar($rule['id']) ? (string) $rule['id'] : '';
				if ($rid === '') $err('missing_rule_id', $col, null, $path . '.id');
				elseif (isset($ids[$rid])) $err('duplicate_rule_id', $col, $rid, $path . '.id');
				$ids[$rid] = true;
				if (isset($rule['scope']) && !in_array($rule['scope'], self::LOGIC_SCOPES, true)) $err('invalid_scope', $col, $rid, $path . '.scope');
				$conds = $check_group(isset($rule['conditions']) ? $rule['conditions'] : array(), $col, $rid, $path . '.conditions');
				if ($col === 'logic_rules') {
					$actions = isset($rule['actions']) && is_array($rule['actions']) ? $rule['actions'] : array();
					if ($conds === 0 || empty($actions)) $warn('rule_inactive', $col, $rid, $path); // the validator ignores it
					if (array_key_exists('enabled', $rule) && empty($rule['enabled'])) $warn('rule_disabled', $col, $rid, $path);
					foreach ($actions as $a => $act) {
						$ap = $path . '.actions[' . $a . ']';
						$type = is_array($act) && isset($act['type']) ? (string) $act['type'] : '';
						if (!in_array($type, self::LOGIC_ACTIONS, true)) { $err('invalid_action', $col, $rid, $ap . '.type', array('type' => $type)); continue; }
						$target = isset($act['target']) ? (string) $act['target'] : '';
						$is_step = in_array($type, array('show_step', 'hide_step', 'jump_to_step'), true);
						if (!in_array($type, array('block_submit', 'end_form'), true)) {
							if ($target === '' || ($is_step ? !isset($steps[$target]) : !isset($fields[$target]))) {
								$err($is_step ? 'unknown_step' : 'unknown_target', $col, $rid, $ap . '.target', array('target' => $target));
							}
						}
						if ($type === 'copy_value' && (!isset($act['value']) || !is_string($act['value']) || !isset($fields[$act['value']]))) $err('unknown_source_field', $col, $rid, $ap . '.value');
						if ($type === 'set_value' && isset($act['value_type']) && !in_array($act['value_type'], array('static', 'autofill_key'), true)) $err('invalid_value_type', $col, $rid, $ap . '.value_type');
						if ($type === 'calculate') {
							$f = self::check_formula(isset($act['value']) ? $act['value'] : '', $fields);
							if ($f !== true) $err($f, $col, $rid, $ap . '.value');
							if (isset($act['decimals']) && (!is_numeric($act['decimals']) || (int) $act['decimals'] < 0 || (int) $act['decimals'] > 6)) $err('invalid_decimals', $col, $rid, $ap . '.decimals');
							if (!self::plan_at_least('pro', $package)) $err('plan_required', $col, $rid, $ap, array('feature' => 'setting:logic_calculate', 'required_plan' => 'pro'));
						}
						if (in_array($type, array('show_message', 'block_submit', 'end_form', 'set_placeholder', 'set_help', 'set_label'), true) && isset($act['value']) && !is_scalar($act['value'])) {
							$err('invalid_value', $col, $rid, $ap . '.value');
						}
					}
				} elseif ($col === 'notification_rules') {
					$to = isset($rule['recipient']) ? trim((string) $rule['recipient']) : '';
					if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) $err('invalid_recipient', $col, $rid, $path . '.recipient');
				} elseif ($col === 'confirmation_rules') {
					$action = isset($rule['action']) ? (string) $rule['action'] : 'message';
					if (!in_array($action, array('message', 'redirect'), true)) $err('invalid_confirmation_action', $col, $rid, $path . '.action');
					if ($action === 'redirect' && !preg_match('#^https?://[^\s]+$#i', isset($rule['url']) ? (string) $rule['url'] : '')) $err('invalid_url', $col, $rid, $path . '.url');
				} elseif ($col === 'webhook_rules') {
					$action = isset($rule['action']) && $rule['action'] === 'stop' ? 'stop' : 'trigger';
					if ($action === 'trigger' && !preg_match('#^https?://[^\s]+$#i', isset($rule['url']) ? (string) $rule['url'] : '')) $err('invalid_url', $col, $rid, $path . '.url');
					if (isset($rule['method']) && !in_array(strtoupper((string) $rule['method']), array('POST', 'GET'), true)) $err('invalid_method', $col, $rid, $path . '.method');
				}
			}
		}

		// Plan limits (the add-on's builder enforces them; checked here so nothing is written that its UI refuses).
		$total = array_sum($counts);
		if ($total > 0 && !self::plan_at_least('free_plus', $package)) {
			$err('plan_required', 'logic_rules', null, 'logic_rules', array('feature' => 'setting:logic_rules', 'required_plan' => 'free_plus'));
		} elseif (is_array($limits)) {
			if (isset($limits['field_rules']) && $counts['logic_rules'] > $limits['field_rules']) $err('limit_field_rules', 'logic_rules', null, 'logic_rules', array('max' => $limits['field_rules']));
			if (isset($limits['notification_rules']) && $counts['notification_rules'] > $limits['notification_rules']) $err('limit_notification_rules', 'notification_rules', null, 'notification_rules', array('max' => $limits['notification_rules']));
			foreach (array('confirmation_rules' => 'setting:confirmation_rules', 'webhook_rules' => 'setting:webhook_rules') as $col => $feature) {
				if ($counts[$col] > 0 && !self::plan_at_least('pro', $package)) $err('plan_required', $col, null, $col, array('feature' => $feature, 'required_plan' => 'pro'));
			}
		}

		// The add-on's own engine: it must run the rules without failing, and settle.
		$validator = self::logic_validator();
		$available = $validator !== null;
		if (!$available) {
			if ($total > 0 || !empty($args['require_addon'])) $err('logic_addon_missing', 'logic_rules', null, 'logic_rules', array('addon' => 'AdnSMF'));
		} elseif (!empty($row0['logic_rules']) && is_array($row0['logic_rules'])) {
			try {
				if (method_exists($validator, 'set_environment')) $validator->set_environment(array('query' => array(), 'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 1));
				$result = $validator->evaluate($form_items, array());
				if (is_array($result) && isset($result['stabilized']) && $result['stabilized'] === false) $warn('rules_do_not_settle', 'logic_rules', null, 'logic_rules');
			} catch (\Throwable $e) {
				$err('evaluation_failed', 'logic_rules', null, 'logic_rules', array('message' => substr($e->getMessage(), 0, 200)));
			}
		}

		return array(
			'ok'          => empty($errors),
			'available'   => $available,
			'api_version' => defined('EMSFB_API_VERSION') ? EMSFB_API_VERSION : 1,
			'plan'        => $plan,
			'limits'      => $limits,
			'counts'      => $counts,
			'errors'      => $errors,
			'warnings'    => $warnings,
		);
	}

	/**
	 * The calculate formula grammar of Emsfb_Logic_Validator::tokenize_calculation_formula()
	 * and parse_calculation_*(): numbers, + - * / ( ), unary +/-, field references as {id},
	 * [id] or a bare id; at most 500 characters. Returns true or an error code.
	 */
	public static function check_formula($formula, array $fields) {
		if (!is_string($formula) || trim($formula) === '') return 'empty_formula';
		if (strlen($formula) > 500) return 'formula_too_long';
		$tokens = array();
		$n = strlen($formula);
		for ($i = 0; $i < $n;) {
			$ch = $formula[$i];
			if (ctype_space($ch)) { $i++; continue; }
			if ($ch === '{' || $ch === '[') {
				$end = strpos($formula, $ch === '{' ? '}' : ']', $i + 1);
				if ($end === false) return 'formula_syntax';
				$ref = trim(substr($formula, $i + 1, $end - $i - 1));
				if ($ref === '') return 'formula_syntax';
				$tokens[] = array('ref', $ref);
				$i = $end + 1;
				continue;
			}
			if (preg_match('/[0-9.]/', $ch)) {
				$s = $i;
				while ($i < $n && preg_match('/[0-9.]/', $formula[$i])) $i++;
				if (!is_numeric(substr($formula, $s, $i - $s))) return 'formula_syntax';
				$tokens[] = array('num', 0);
				continue;
			}
			if (preg_match('/[A-Za-z_]/', $ch)) {
				$s = $i;
				while ($i < $n && preg_match('/[A-Za-z0-9_-]/', $formula[$i])) $i++;
				$tokens[] = array('ref', substr($formula, $s, $i - $s));
				continue;
			}
			if (strpos('+-*/()', $ch) !== false) { $tokens[] = array($ch === '(' || $ch === ')' ? $ch : 'op', $ch); $i++; continue; }
			return 'formula_syntax';
		}
		foreach ($tokens as $t) {
			if ($t[0] === 'ref' && !isset($fields[$t[1]])) return 'formula_unknown_field';
		}
		// Expression shape: operand (op operand)*, with unary signs and balanced parentheses.
		$expect_operand = true;
		$depth = 0;
		foreach ($tokens as $t) {
			if ($expect_operand) {
				if ($t[0] === 'num' || $t[0] === 'ref') { $expect_operand = false; continue; }
				if ($t[0] === '(') { $depth++; continue; }
				if ($t[0] === 'op' && ($t[1] === '+' || $t[1] === '-')) continue;
				return 'formula_syntax';
			}
			if ($t[0] === 'op') { $expect_operand = true; continue; }
			if ($t[0] === ')') { if (--$depth < 0) return 'formula_syntax'; continue; }
			return 'formula_syntax';
		}
		return (!$expect_operand && $depth === 0) ? true : 'formula_syntax';
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
