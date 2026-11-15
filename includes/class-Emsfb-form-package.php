<?php
/**
 * F15-D: the form package — a versioned PHP contract to carry one form between sites or tools
 * (docs/feature-gaps/F15-builder-productivity.fa.md §5.5, §9; F20 P23; docs/form-package-contract.en.md).
 *
 * Core keeps ONLY the contract, no screen: buttons, previews, bulk files and importers from other form plugins live
 * in the separate plugin "Easy Form Builder Data Tools", which finds this API through efb_form_package_api().
 *
 *   Form_Package::export($form_id, $args)   → envelope {schema, generator, plugin_version, exported_at, form, extras}
 *   Form_Package::validate($package, $args) → {ok, errors[], notices[], dropped[], form, id_map} (writes nothing)
 *   Form_Package::import($package, $args)   → {form_id, dropped[], notices[], warnings[], id_map, payment} | WP_Error
 *
 * Rules: import always creates a NEW form; field ids are kept unless one is not [A-Za-z0-9_-]{1,40} or is used twice
 * (then a new id and an old → new map; every reference is rewritten by the filter efb_form_package_remap_ids, where
 * core registers its own handlers: structural keys and {…} references of smart tags and calculations, so a module
 * that moves to an add-on brings its own handler); the rows go through the save path of the builder / AutoPilot
 * (Field_Rules, Calendar, sanitize_obj_msg_efb, Privacy, Form_Gate, Uploads, Layout); secrets and site-only keys are
 * never exported and never imported; a payment form is checked by Payments\Readiness, never trusted.
 *
 * Add-on settings kept outside the form JSON travel as "extras" blocks (P23): filters efb_form_export_extras
 * ($extras, $form_id, $ctx) and efb_form_import_extras ($report, $form_id, $blocks, $ctx). A block is
 * {v, addon, data}; on import a block whose add-on is not active on this site is dropped with a notice. Secrets
 * (tokens, keys, passwords, admin numbers, chat ids) are removed from every block of an export. Duplicating a form
 * and saving a restored version carry the same blocks (context duplicate / revision).
 */
namespace Emsfb;

defined('ABSPATH') || exit;

class Form_Package {

	/** 1.1 (SPLIT-G): delete() of a form an import created (import_token) and import_entries(); backward compatible. */
	const API_VERSION = 1.1;
	const SCHEMA = 1;
	const EXTRAS_VERSION = 1;
	const MAX_BYTES = 8388608;
	const MAX_DEPTH = 64;
	const MAX_ROWS = 5000;
	const ROW0_TYPES = array('form', 'payment', 'survey', 'login', 'register', 'subscribe', 'smartForm');
	/** Row-0 keys that are never in a package (secrets). */
	const SECRET_ROW0 = array('telegram_bot_token', 'telegram_admin_chat_ids', 'sms_admins_phone_no');
	/** SMS / Telegram texts a form keeps in its JSON while the notification is off (A5): kept as they are (not secrets). */
	const ADDON_TEXT_ROW0 = array('sms_msg_new_noti', 'sms_msg_responsed_noti', 'sms_msg_recived_usr', 'sms_msg_recived_user', 'telegram_msg_new_noti', 'telegram_msg_responsed_noti', 'telegram_msg_recived_usr', 'telegram_msg_recived_user');
	/** Keys that only mean something on the site that made the form. */
	const SITE_ROW0 = array('autofill_id' => '', 'st_uses' => null);
	/** Keys the sanitizer walks as arrays: anything else in them is removed before it runs (PHP warnings otherwise). */
	const ARRAY_KEYS = array('autofill_conditions', 'c_c', 'c_n', 'conditions', 'logic_rules', 'notification_rules', 'confirmation_rules', 'webhook_rules', 'thank_you_message');
	/** Free-text keys: an old id is never replaced inside them as a whole string. */
	const TEXT_KEYS = array('name', 'message', 'placeholder', 'label', 'title', 'formName', 'thankYou', 'done', 'trackingCode', 'pleaseFillInRequiredFields', 'error', 'button_single_text', 'button_Next_text', 'button_Previous_text', 'customRequiredMsg', 'note');
	const SECRET_KEY_RE = '/(secret|token|password|passwd|api_?key|apikey|chat_?ids?|admins?_?phone|admins?_?numbers?|phone_no|private_?key|_key$|SKey$)/i';

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_form_package_remap_ids', array(__CLASS__, 'remap_structural'), 5, 3);
		add_filter('efb_form_package_remap_ids', array(__CLASS__, 'remap_tags'), 10, 3);
		add_filter('efb_form_export_extras', array(__CLASS__, 'export_core_extras'), 5, 3);
		add_filter('efb_form_import_extras', array(__CLASS__, 'import_core_extras'), 5, 4);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 33, 2);
	}

	/** Discovery: what this site's core offers (efb_form_package_api() / filter efb_form_package_api). */
	public static function api() {
		$a = array(
			'version'  => self::API_VERSION, 'schema' => self::SCHEMA, 'extras_version' => self::EXTRAS_VERSION,
			'class'    => __CLASS__, 'functions' => array('export', 'validate', 'import', 'export_extras', 'import_extras', 'delete', 'import_entries'),
			'import_token' => 'import() returns import_token; delete($form_id, $import_token) removes only a form that import created',
			'filters'  => array('efb_form_export_extras', 'efb_form_import_extras', 'efb_form_package_remap_ids', 'efb_form_package_known_types', 'efb_form_package_addon_active'),
			'actions'  => array('efb_form_created (context source "import" / "duplicate")'),
			'limits'   => array('bytes' => self::MAX_BYTES, 'depth' => self::MAX_DEPTH, 'rows' => self::MAX_ROWS),
			'plugin_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
		);
		return function_exists('apply_filters') ? apply_filters('efb_form_package_api', $a) : $a;
	}

	/* ------------------------------------------------------------------ helpers */

	private static function err($code, $msg, $extra = array()) {
		return class_exists('\\WP_Error') ? new \WP_Error($code, $msg, $extra) : array('error' => $code, 'message' => $msg) + $extra;
	}

	public static function depth($v, $d = 0) {
		if (!is_array($v)) return $d;
		$m = $d + 1;
		foreach ($v as $x) { if (is_array($x)) { $m = max($m, self::depth($x, $d + 1)); if ($m > self::MAX_DEPTH + 1) return $m; } }
		return $m;
	}

	/** A string PHP would unserialize into an object (O:8:"stdClass":…, C:…). */
	public static function is_php_object_string($s) {
		return is_string($s) && preg_match('/(^|[;{])\s*[OC]:\+?\d+:"[^"]*":\d+:\{/', $s) === 1;
	}

	/** Removes PHP object strings anywhere; returns the paths dropped. */
	private static function drop_php_objects(&$v, $path, &$dropped) {
		if (is_array($v)) {
			foreach ($v as $k => &$x) {
				if (self::is_php_object_string($x)) { $dropped[] = array('code' => 'php_object', 'path' => $path . '.' . $k); unset($v[$k]); continue; }
				if (is_array($x)) self::drop_php_objects($x, $path . '.' . $k, $dropped);
			}
			unset($x);
		}
	}

	public static function addon_active($code) {
		$code = (string) $code;
		if ($code === '') return true;
		$on = false;
		$s = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		if (is_object($s) && isset($s->$code)) $on = (int) $s->$code >= 1;
		elseif (function_exists('get_option')) $on = (int) get_option('emsfb_addon_' . $code, 0) >= 1;
		return function_exists('apply_filters') ? (bool) apply_filters('efb_form_package_addon_active', $on, $code) : $on;
	}

	/** Field and structural types a package may hold (palette, saved types, gateways of this site; filterable). */
	public static function known_types() {
		$t = array('form', 'payment', 'survey', 'login', 'register', 'subscribe', 'smartForm', 'step', 'option', 'r_matrix',
			'text', 'name', 'firstName', 'lastName', 'password', 'email', 'number', 'textarea', 'checkbox', 'radio', 'select', 'multiselect', 'tel', 'mobile', 'range',
			'date', 'advdate', 'file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder', 'payCheckbox', 'payRadio', 'paySelect', 'payMultiselect', 'prcfld',
			'ttlprc', 'maps', 'stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal', 'url', 'conturyList', 'country', 'stateProvince', 'statePro', 'cityList', 'city',
			'address_line', 'postalcode', 'esign', 'switch', 'chlCheckBox', 'chlRadio', 'heading', 'section', 'color', 'rating', 'yesNo', 'link', 'html', 'pointr5', 'pointr10',
			'imgRadio', 'pdate', 'ardate', 'trmCheckbox', 'ranking', 'calc', 'repeater', 'hiddenField', 'table_matrix', 'smartcr', 'sample', 'booking', 'steps');
		if (class_exists('\\Emsfb\\Payments\\Registry')) foreach ((array) Payments\Registry::slugs() as $slug) $t[] = (string) $slug;
		return function_exists('apply_filters') ? (array) apply_filters('efb_form_package_known_types', $t) : $t;
	}

	/** Removes secret-looking keys anywhere in a value (extras of an export). */
	public static function strip_secrets($v) {
		if (!is_array($v)) return $v;
		foreach ($v as $k => $x) {
			if (is_string($k) && preg_match(self::SECRET_KEY_RE, $k)) { unset($v[$k]); continue; }
			if (is_array($x)) $v[$k] = self::strip_secrets($x);
		}
		return $v;
	}

	private static function read_rows($form_id) {
		global $wpdb;
		$r = $wpdb->get_row($wpdb->prepare("SELECT form_id, form_name, form_type, form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", (int) $form_id), ARRAY_A);
		if (!$r) return null;
		$json = function_exists('emsfb_json_normalize_efb') ? \emsfb_json_normalize_efb($r['form_structer']) : str_replace('\\"', '"', $r['form_structer']);
		$rows = json_decode($json, true);
		if (!is_array($rows)) return null;
		$r['rows'] = $rows;
		return $r;
	}

	/* ------------------------------------------------------------------ export */

	/**
	 * The package of a stored form. args: with_emails (bool, default false: the notification address of row 0 is
	 * emptied), context (export | duplicate | revision; only "export" leaves the site).
	 */
	public static function export($form_id, array $args = array()) {
		$f = self::read_rows($form_id);
		if (!$f) return self::err('efb_package_no_form', 'No form with this id.', array('status' => 404));
		$rows = self::clean_row0($f['rows'], !empty($args['with_emails']));
		$ctx = array('context' => isset($args['context']) ? (string) $args['context'] : 'export') + $args;
		return array(
			'schema' => self::SCHEMA, 'generator' => 'easy-form-builder',
			'plugin_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
			'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
			'form' => array('form_name' => (string) $f['form_name'], 'form_type' => (string) $f['form_type'], 'efb_version' => isset($rows[0]['EfbVersion']) ? $rows[0]['EfbVersion'] : 2, 'form_structer' => $rows),
			'extras' => self::export_extras((int) $form_id, $ctx),
		);
	}

	/** Row 0 without secrets and site-only keys; `email` only when asked for. */
	public static function clean_row0(array $rows, $with_emails = false) {
		if (!isset($rows[0]) || !is_array($rows[0])) return $rows;
		foreach (self::SECRET_ROW0 as $k) unset($rows[0][$k]);
		foreach (self::SITE_ROW0 as $k => $v) { if (array_key_exists($k, $rows[0])) { if ($v === null) unset($rows[0][$k]); else $rows[0][$k] = $v; } }
		foreach (array_keys($rows[0]) as $k) if (is_string($k) && preg_match('/(secret|token|password|api_?key)/i', $k)) unset($rows[0][$k]);
		if (!$with_emails && isset($rows[0]['email'])) $rows[0]['email'] = '';
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['registered_count'])) $rows[$i]['registered_count'] = '0';
		return $rows;
	}

	/** The extras blocks of a form (P23). Secrets are removed unless the blocks stay on this site (duplicate). */
	public static function export_extras($form_id, array $ctx = array()) {
		$ctx += array('context' => 'export');
		$x = function_exists('apply_filters') ? apply_filters('efb_form_export_extras', array(), (int) $form_id, $ctx) : array();
		$out = array();
		foreach ((array) $x as $name => $b) {
			if (!is_string($name) || !preg_match('/^[a-z0-9_\-]{1,40}$/', $name) || !is_array($b) || !isset($b['data'])) continue;
			$b = array('v' => isset($b['v']) ? (int) $b['v'] : self::EXTRAS_VERSION, 'addon' => isset($b['addon']) ? (string) $b['addon'] : '', 'data' => $b['data']);
			if ($ctx['context'] !== 'duplicate') $b['data'] = self::strip_secrets($b['data']);
			$out[$name] = $b;
		}
		return $out;
	}

	/**
	 * Applies extras blocks to a form. ctx: context (import | duplicate | revision), id_map. A block whose add-on is
	 * not active here is dropped with a notice; a block nobody applied is reported too.
	 * @return array{applied: string[], notices: array[]}
	 */
	public static function import_extras($form_id, $blocks, array $ctx = array()) {
		$ctx += array('context' => 'import', 'id_map' => array());
		$report = array('applied' => array(), 'notices' => array());
		if (!is_array($blocks)) return $report;
		$ok = array();
		foreach ($blocks as $name => $b) {
			if (!is_string($name) || !is_array($b) || !isset($b['data'])) { $report['notices'][] = array('code' => 'extras_invalid', 'block' => (string) $name); continue; }
			$addon = isset($b['addon']) ? (string) $b['addon'] : '';
			if (!self::addon_active($addon)) { $report['notices'][] = array('code' => 'extras_addon_inactive', 'block' => $name, 'addon' => $addon); continue; }
			if (isset($b['v']) && (int) $b['v'] > self::EXTRAS_VERSION && $addon === '') { $report['notices'][] = array('code' => 'extras_newer', 'block' => $name); continue; }
			$ok[$name] = $b;
		}
		if ($ok && function_exists('apply_filters')) {
			$r = apply_filters('efb_form_import_extras', $report, (int) $form_id, $ok, $ctx);
			if (is_array($r)) $report = array('applied' => isset($r['applied']) ? array_values(array_unique((array) $r['applied'])) : array(), 'notices' => isset($r['notices']) ? (array) $r['notices'] : $report['notices']);
		}
		foreach (array_keys($ok) as $name) if (!in_array($name, $report['applied'], true)) $report['notices'][] = array('code' => 'extras_not_applied', 'block' => $name);
		return $report;
	}

	/** Duplicating a form on this site: the same blocks, secrets included (they never leave the server). */
	public static function duplicate_extras($from_id, $to_id) {
		return self::import_extras((int) $to_id, self::export_extras((int) $from_id, array('context' => 'duplicate')), array('context' => 'duplicate'));
	}

	/* ------------------------------------------------------------------ core extras: SMS and Telegram texts */

	private static function sms_addon() {
		if (!defined('EMSFB_PLUGIN_DIRECTORY') || !file_exists(EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php') || !self::addon_active('AdnSS')) return null;
		require_once EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php';
		return class_exists('\\Emsfb\\smssendefb') ? new smssendefb() : (class_exists('\\smssendefb') ? new \smssendefb() : null);
	}
	private static function tlg_addon() {
		if (!defined('EMSFB_PLUGIN_DIRECTORY') || !file_exists(EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php') || !self::addon_active('AdnTLG')) return null;
		require_once EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php';
		return class_exists('\\Emsfb\\telegramsendefb') ? new telegramsendefb() : (class_exists('\\telegramsendefb') ? new \telegramsendefb() : null);
	}

	/** efb_form_export_extras (core): the SMS / Telegram texts the add-ons keep per form; numbers / token only on a duplicate. */
	public static function export_core_extras($extras, $form_id, $ctx) {
		$dup = is_array($ctx) && isset($ctx['context']) && $ctx['context'] === 'duplicate';
		$sms = self::sms_addon();
		if ($sms && method_exists($sms, 'get_sms_contact_efb')) {
			$c = $sms->get_sms_contact_efb((int) $form_id);
			if ($c) {
				$d = array('new_noti' => (string) ($c->new_message_noti_user ?? ''), 'responsed_noti' => (string) ($c->new_response_noti ?? ''), 'recived_usr' => (string) ($c->recived_message_noti_user ?? ''));
				if ($dup) $d['admin_numbers'] = (string) ($c->admin_numbers ?? '');
				$extras['sms'] = array('v' => 1, 'addon' => 'AdnSS', 'data' => $d);
			}
		}
		$tlg = self::tlg_addon();
		if ($tlg && method_exists($tlg, 'get_telegram_contact_efb')) {
			$c = $tlg->get_telegram_contact_efb((int) $form_id);
			if ($c) {
				$d = array('new_noti' => (string) ($c->new_message_noti_user ?? ''), 'responsed_noti' => (string) ($c->new_response_noti ?? ''), 'recived_usr' => (string) ($c->received_message_noti_user ?? ''));
				if ($dup) { $d['bot_token'] = (string) ($c->bot_token ?? ''); $d['admin_chat_ids'] = (string) ($c->admin_chat_ids ?? ''); }
				$extras['telegram'] = array('v' => 1, 'addon' => 'AdnTLG', 'data' => $d);
			}
		}
		return $extras;
	}

	/** efb_form_import_extras (core): the texts written to the add-ons' tables; numbers / token kept (revision), copied (duplicate) or empty (import). */
	public static function import_core_extras($report, $form_id, $blocks, $ctx) {
		$context = is_array($ctx) && isset($ctx['context']) ? $ctx['context'] : 'import';
		$txt = function ($v) { return function_exists('sanitize_textarea_field') ? sanitize_textarea_field((string) $v) : trim(strip_tags((string) $v)); };
		if (isset($blocks['sms']['data']) && is_array($blocks['sms']['data']) && ($sms = self::sms_addon()) && method_exists($sms, 'add_sms_contact_efb')) {
			$d = $blocks['sms']['data'];
			$old = method_exists($sms, 'get_sms_contact_efb') ? $sms->get_sms_contact_efb((int) $form_id) : null;
			$admins = $context === 'duplicate' ? (string) ($d['admin_numbers'] ?? '') : ($context === 'revision' && $old ? (string) ($old->admin_numbers ?? '') : '');
			$admins = preg_replace('/[^0-9+,]/', '', $admins);
			$sms->add_sms_contact_efb((int) $form_id, $admins, $txt($d['recived_usr'] ?? ''), $txt($d['new_noti'] ?? ''), $txt($d['new_noti'] ?? ''), $txt($d['responsed_noti'] ?? ''));
			$report['applied'][] = 'sms';
			if ($context === 'import') $report['notices'][] = array('code' => 'sms_numbers_needed', 'block' => 'sms');
		}
		if (isset($blocks['telegram']['data']) && is_array($blocks['telegram']['data']) && ($tlg = self::tlg_addon()) && method_exists($tlg, 'add_telegram_contact_efb')) {
			$d = $blocks['telegram']['data'];
			$old = method_exists($tlg, 'get_telegram_contact_efb') ? $tlg->get_telegram_contact_efb((int) $form_id) : null;
			if ($context === 'duplicate') { $token = (string) ($d['bot_token'] ?? ''); $chats = (string) ($d['admin_chat_ids'] ?? ''); }
			elseif ($old) { $token = (string) ($old->bot_token ?? ''); $chats = (string) ($old->admin_chat_ids ?? ''); }
			else { $token = function_exists('get_option') ? (string) get_option('emsfb_telegram_bot_token', '') : ''; $chats = function_exists('get_option') ? (string) get_option('emsfb_telegram_chat_id', '') : ''; }
			$tlg->add_telegram_contact_efb((int) $form_id, $chats, $token, $txt($d['recived_usr'] ?? ''), $txt($d['new_noti'] ?? ''), $txt($d['new_noti'] ?? ''), $txt($d['responsed_noti'] ?? ''));
			$report['applied'][] = 'telegram';
		}
		return $report;
	}

	/* ------------------------------------------------------------------ validate */

	/** The rows of the package as they would be stored, with what was dropped. Writes nothing. */
	public static function validate($package, array $args = array()) {
		$out = array('ok' => false, 'errors' => array(), 'notices' => array(), 'dropped' => array(), 'form' => null, 'extras' => array(), 'id_map' => array());
		$fail = function ($code, $m) use (&$out) { $out['errors'][] = array('code' => $code, 'm' => $m); return $out; };
		if (is_string($package)) {
			if (strlen($package) > self::MAX_BYTES) return $fail('package_too_large', 'The package is larger than 8 MB.');
			$package = json_decode($package, true, self::MAX_DEPTH);
			if (json_last_error() === JSON_ERROR_DEPTH) return $fail('package_too_deep', 'The package is nested too deeply.');
			if (!is_array($package)) return $fail('package_invalid_json', 'This is not a valid Easy Form Builder form package.');
		} elseif (!is_array($package)) {
			return $fail('package_invalid', 'This is not a valid Easy Form Builder form package.');
		} else {
			$enc = function_exists('wp_json_encode') ? wp_json_encode($package) : json_encode($package);
			if (is_string($enc) && strlen($enc) > self::MAX_BYTES) return $fail('package_too_large', 'The package is larger than 8 MB.');
			if (self::depth($package) > self::MAX_DEPTH) return $fail('package_too_deep', 'The package is nested too deeply.');
		}
		$schema = isset($package['schema']) ? $package['schema'] : null;
		if (!is_int($schema) && !(is_string($schema) && ctype_digit($schema))) return $fail('package_invalid', 'This is not a valid Easy Form Builder form package.');
		if ((int) $schema > self::SCHEMA) return $fail('package_newer', 'This package was made by a newer version of Easy Form Builder. Update the plugin and try again.');
		if ((int) $schema < 1 || !isset($package['form']) || !is_array($package['form'])) return $fail('package_invalid', 'This is not a valid Easy Form Builder form package.');
		$form = $package['form'];
		$rows = isset($form['form_structer']) ? $form['form_structer'] : null;
		if (is_string($rows)) $rows = json_decode(function_exists('emsfb_json_normalize_efb') ? \emsfb_json_normalize_efb($rows) : $rows, true, self::MAX_DEPTH);
		if (!is_array($rows) || array_values($rows) !== $rows) return $fail('package_no_rows', 'The form in the package has no rows.');
		if (count($rows) > self::MAX_ROWS) return $fail('package_too_many_rows', 'The form has more than 5000 rows.');
		self::drop_php_objects($rows, 'form_structer', $out['dropped']);
		$rows = array_values($rows);
		// Rows must be objects with a string type.
		$clean = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r) || !isset($r['type']) || !is_string($r['type']) || $r['type'] === '') { $out['dropped'][] = array('code' => 'row_without_type', 'row' => $i); continue; }
			$clean[] = $r;
		}
		$rows = $clean;
		if (count($rows) < 2 || !in_array($rows[0]['type'], self::ROW0_TYPES, true)) return $fail('package_bad_settings_row', 'The first row of the form must hold its settings.');
		if ($rows[1]['type'] !== 'step') {
			// A form saved without its first step row (an older efbBuilderApi.insertItems() on a blank form): the step is
			// added, as the builder adds it with the first palette click.
			if (count(array_filter($rows, function ($r) { return $r['type'] === 'step'; })) > 0) return $fail('package_no_step', 'The form must start with a step.');
			array_splice($rows, 1, 0, array(array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => '', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'visible' => 1)));
			$out['notices'][] = array('code' => 'step_added');
		}
		// Unknown types (and the options of a field that was dropped).
		$known = array_flip(self::known_types());
		$gone = array();
		$kept = array($rows[0]);
		foreach ($rows as $i => $r) {
			if ($i === 0) continue;
			if (!isset($known[$r['type']])) { $out['dropped'][] = array('code' => 'unknown_type', 'row' => $i, 'type' => substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $r['type']), 0, 40)); if (isset($r['id_']) && is_scalar($r['id_'])) $gone[(string) $r['id_']] = 1; continue; }
			if (($r['type'] === 'option' || $r['type'] === 'r_matrix') && isset($r['parent']) && is_scalar($r['parent']) && isset($gone[(string) $r['parent']])) { $out['dropped'][] = array('code' => 'orphan_option', 'row' => $i); continue; }
			$kept[] = $r;
		}
		$rows = $kept;
		// Keys the sanitizer reads as arrays.
		foreach ($rows as $i => $r) foreach (self::ARRAY_KEYS as $k) if (array_key_exists($k, $r) && !is_array($r[$k])) { unset($rows[$i][$k]); $out['dropped'][] = array('code' => 'not_an_array', 'row' => $i, 'key' => $k); }
		$rows = self::clean_row0($rows, true);
		$steps = count(array_filter($rows, function ($r) { return $r['type'] === 'step'; }));
		$rows[0]['steps'] = max(1, $steps);
		// Ids: kept, unless invalid or used twice.
		list($rows, $map, $local) = self::assign_ids($rows);
		if ($local) $out['notices'][] = array('code' => 'duplicate_ids', 'ids' => array_keys($local));
		if ($map) {
			$rows = function_exists('apply_filters') ? apply_filters('efb_form_package_remap_ids', $rows, $map, array('context' => isset($args['context']) ? $args['context'] : 'import')) : self::remap_tags(self::remap_structural($rows, $map, array()), $map, array());
			$out['notices'][] = array('code' => 'ids_remapped', 'count' => count($map));
		}
		$out['id_map'] = $map + $local;
		// The save path of the builder / AutoPilot.
		$p = self::prepare_rows($rows);
		if ($p['error']) { $out['errors'][] = $p['error']; return $out; }
		// SPLIT §4.2: the keys of an add-on that is not active on this site are not imported (dropped, addon_inactive).
		if (class_exists('\\Emsfb\\Addon_Keys')) {
			list($p['rows'], $ak_dropped) = Addon_Keys::drop_for_import($p['rows']);
			$out['dropped'] = array_merge($out['dropped'], $ak_dropped);
		}
		$out['form'] = array('form_name' => isset($form['form_name']) && is_scalar($form['form_name']) ? self::text((string) $form['form_name'], 250) : '', 'form_type' => (string) $p['rows'][0]['type'], 'form_structer' => $p['rows']);
		$out['notices'] = array_merge($out['notices'], self::import_warnings($p['rows']), $p['warnings']);
		$out['extras'] = isset($package['extras']) && is_array($package['extras']) ? $package['extras'] : array();
		self::drop_php_objects($out['extras'], 'extras', $out['dropped']);
		$out['ok'] = true;
		return $out;
	}

	private static function text($s, $max) {
		$s = function_exists('sanitize_text_field') ? sanitize_text_field($s) : trim(strip_tags($s));
		return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
	}

	/** New 9-character ids for invalid ids (old → new, rewritten everywhere by the hook) and for a second use of an id (only that row and its options). */
	public static function assign_ids(array $rows) {
		$used = array();
		foreach ($rows as $i => $r) if ($i > 0 && isset($r['id_']) && is_scalar($r['id_'])) $used[(string) $r['id_']] = 1;
		$new = function () use (&$used) { do { $id = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9); } while (isset($used[$id])); $used[$id] = 1; return $id; };
		$map = array(); $local = array(); $seen = array();
		foreach ($rows as $i => $r) {
			if ($i === 0 || in_array($r['type'], array('option', 'r_matrix', 'step'), true)) continue;
			$id = isset($r['id_']) && is_scalar($r['id_']) ? (string) $r['id_'] : '';
			if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,40}$/D', $id)) {
				$n = $new();
				if ($id !== '') $map[$id] = $n;
				$rows[$i]['id_'] = $n; $rows[$i]['dataId'] = $n . '-id';
			} elseif (isset($seen[$id])) {
				$n = $new();
				$local[$id . '#' . $i] = $n;
				$rows[$i]['id_'] = $n; $rows[$i]['dataId'] = $n . '-id';
				// Its options are the option rows right after it that name the old id.
				for ($j = $i + 1; $j < count($rows); $j++) {
					if (!in_array($rows[$j]['type'], array('option', 'r_matrix'), true)) break;
					if (isset($rows[$j]['parent']) && (string) $rows[$j]['parent'] === $id) $rows[$j]['parent'] = $n;
				}
			}
			$seen[$id === '' ? $rows[$i]['id_'] : $id] = 1;
		}
		return array($rows, $map, $local);
	}

	/** efb_form_package_remap_ids (core, 5): a value that is exactly an old id (dataId "old-id" too), outside free-text keys; array keys that are ids. */
	public static function remap_structural($rows, $map, $ctx) {
		if (!is_array($rows) || !$map) return $rows;
		$walk = function ($v, $key) use (&$walk, $map) {
			if (is_array($v)) {
				$o = array();
				foreach ($v as $k => $x) { $nk = (is_string($k) && isset($map[$k])) ? $map[$k] : $k; $o[$nk] = $walk($x, $k); }
				return $o;
			}
			if (!is_string($v) || (is_string($key) && in_array($key, self::TEXT_KEYS, true))) return $v;
			if (isset($map[$v])) return $map[$v];
			if (substr($v, -3) === '-id' && isset($map[substr($v, 0, -3)])) return $map[substr($v, 0, -3)] . '-id';
			return $v;
		};
		foreach ($rows as $i => $r) $rows[$i] = $walk($r, null);
		return $rows;
	}

	/** efb_form_package_remap_ids (core, 10): {field:old}, {old}, {old.part}, {field:old|fallback} in any text (smart tags, calculations). */
	public static function remap_tags($rows, $map, $ctx) {
		if (!is_array($rows) || !$map) return $rows;
		$re = '/\{(field:)?(' . implode('|', array_map(function ($k) { return preg_quote((string) $k, '/'); }, array_keys($map))) . ')(?=[.|}])/u';
		$walk = function ($v) use (&$walk, $re, $map) {
			if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $walk($x); return $v; }
			if (!is_string($v) || strpos($v, '{') === false) return $v;
			$r = preg_replace_callback($re, function ($m) use ($map) { return '{' . $m[1] . $map[$m[2]]; }, $v);
			return $r === null ? $v : $r;
		};
		return $walk($rows);
	}

	/**
	 * The save path of a new form (the same steps and order as Create::add_form_structure(), which the builder and
	 * AutoPilot use): pattern refusal, date limits, sanitize_obj_msg_efb, Privacy, Form_Gate, notifiers, payments,
	 * verification, login / register fields, Uploads, Layout, smart-tag warnings.
	 * @return array{rows: array, warnings: array, error: array|null}
	 */
	public static function prepare_rows(array $valp, array $lang = array()) {
		$warn = array();
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if (!$lang && $fn) $lang = $fn->text_efb(array('smsOffAddonMissing', 'telegramOffAddonMissing', 'authFieldsMissing', 'login', 'register', 'smsFieldWithoutForm'));
		if (class_exists('\\Emsfb\\Field_Rules')) { $e = Field_Rules::validate_rows($valp); if ($e) return array('rows' => $valp, 'warnings' => $warn, 'error' => array('code' => 'pattern_invalid', 'm' => $e[0]['reason'], 'path' => $e[0]['path'])); }
		$dc = class_exists('\\Emsfb\\Calendar') ? Calendar::validate_rows($valp) : array('errors' => array(), 'warnings' => array());
		if (!empty($dc['errors'])) return array('rows' => $valp, 'warnings' => $warn, 'error' => array('code' => $dc['errors'][0]['code'], 'm' => $dc['errors'][0]['m']));
		if ($fn) $valp = $fn->sanitize_obj_msg_efb($valp);
		if (!is_array($valp) || !isset($valp[0]['type'])) return array('rows' => array(), 'warnings' => $warn, 'error' => array('code' => 'package_invalid', 'm' => 'The form could not be read.'));
		if (class_exists('\\Emsfb\\Privacy')) $valp = Privacy::normalize_form_settings($valp);
		if (class_exists('\\Emsfb\\Form_Gate')) {
			$valp = Form_Gate::normalize_form_settings($valp, null);
			$g = Form_Gate::save_checks($valp, 0);
			if (!empty($g['errors'])) return array('rows' => $valp, 'warnings' => $warn, 'error' => array('code' => $g['errors'][0]['code'], 'm' => $g['errors'][0]['m']));
			$warn = array_merge($warn, (array) $g['warnings']);
		}
		if (class_exists('\\Emsfb\\Builder_Api')) {
			list($valp, $nw) = Builder_Api::disable_unavailable_notifiers($valp, function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded', array()) : array(), $lang);
			$warn = array_merge($warn, $nw);
			if (class_exists('\\Emsfb\\Payments\\Registry')) $warn = array_merge($warn, Payments\Registry::form_warnings($valp));
			if (class_exists('\\Emsfb\\Verification')) $warn = array_merge($warn, Verification::form_warnings($valp));
			$miss = Builder_Api::auth_fields_missing($valp);
			if ($miss) return array('rows' => $valp, 'warnings' => $warn, 'error' => array('code' => 'auth_fields_missing', 'm' => 'A login or registration form needs these fields: ' . implode(', ', $miss), 'missing' => $miss));
		}
		if (!empty($dc['warnings'])) $warn = array_merge($warn, $dc['warnings']);
		if (class_exists('\\Emsfb\\Uploads')) { list($valp, $uw) = Uploads::normalize_rows($valp, null); $warn = array_merge($warn, $uw); }
		if (class_exists('\\Emsfb\\Layout')) { list($valp, $lw) = Layout::normalize_rows($valp, null); $warn = array_merge($warn, $lw); }
		if (class_exists('\\Emsfb\\Smart_Tags')) $warn = array_merge($warn, Smart_Tags::form_warnings_text($valp, $lang));
		return array('rows' => $valp, 'warnings' => $warn, 'error' => null);
	}

	/* ------------------------------------------------------------------ 1.1: undo an import, import entries */

	const IMPORTS_OPTION = 'efb_form_package_imports';

	/** Records the forms an import created under a token (the caller's own token to group a batch, or a new one). */
	private static function remember_import($form_id, $token, $user_id) {
		$all = function_exists('get_option') ? get_option(self::IMPORTS_OPTION, array()) : array();
		$all = is_array($all) ? $all : array();
		$now = time();
		foreach ($all as $k => $r) if (!is_array($r) || !isset($r['at']) || (int) $r['at'] < $now - 30 * DAY_IN_SECONDS) unset($all[$k]);
		if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $token) || !isset($all[$token]) || (int) $all[$token]['user'] !== (int) $user_id) {
			$token = function_exists('wp_generate_password') ? wp_generate_password(32, false, false) : bin2hex(random_bytes(16));
			$all[$token] = array('forms' => array(), 'user' => (int) $user_id, 'at' => $now);
		}
		$all[$token]['forms'][] = (int) $form_id;
		$all[$token]['at'] = $now;
		if (count($all) > 100) $all = array_slice($all, -100, null, true);
		if (function_exists('update_option')) update_option(self::IMPORTS_OPTION, $all, false);
		return $token;
	}

	/** Who may undo an import or bring entries in: an administrator, or a user of the plugin's dashboard. */
	private static function can_manage() {
		if (!function_exists('current_user_can')) return false;
		if (current_user_can('manage_options')) return true;
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		return $fn && method_exists($fn, 'user_permission_efb_admin_dashboard') && $fn->user_permission_efb_admin_dashboard();
	}

	/**
	 * Undo an import: deletes a form only when the import identified by $import_token created it (and the token is the
	 * current user's, or the user is an administrator). Its entries, versions and caches go with it, as the builder's
	 * own delete does (efb_form_deleted). @return true|\WP_Error
	 */
	public static function delete($form_id, $import_token) {
		$form_id = (int) $form_id;
		if (!self::can_manage()) return self::err('package_forbidden', 'You are not allowed to do this.', array('status' => 403));
		$all = function_exists('get_option') ? get_option(self::IMPORTS_OPTION, array()) : array();
		$rec = is_array($all) && is_string($import_token) && isset($all[$import_token]) ? $all[$import_token] : null;
		$uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		if (!$rec || !in_array($form_id, array_map('intval', (array) $rec['forms']), true)) return self::err('package_not_imported', 'This form was not created by that import.', array('status' => 404));
		if ((int) $rec['user'] !== $uid && !(function_exists('current_user_can') && current_user_can('manage_options'))) return self::err('package_forbidden', 'You are not allowed to do this.', array('status' => 403));
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		$r = $wpdb->delete($t, array('form_id' => $form_id), array('%d'));
		if ($r === false) return self::err('package_not_deleted', 'The form could not be deleted.', array('status' => 500));
		if (class_exists('\\Emsfb\\Privacy')) Privacy::delete_form_entries($form_id);
		else $wpdb->delete($wpdb->prefix . 'emsfb_msg_', array('form_id' => $form_id), array('%d'));
		if (class_exists('\\Emsfb\\Revisions')) Revisions::delete_for_form($form_id);
		// The form's caches, the keys the builder's own delete clears (Admin::clear_form_cache_efb()).
		if (function_exists('wp_cache_delete')) {
			foreach (array(array('form_structer', 'form_type'), array('form_structer'), array('form_type'), array('form_name'), array('form_name', 'form_structer')) as $set) wp_cache_delete('efb_form_' . $form_id . '_' . md5(implode('_', $set)), 'emsfb');
		}
		if (function_exists('do_action')) do_action('efb_form_deleted', $form_id);
		$all[$import_token]['forms'] = array_values(array_diff(array_map('intval', (array) $rec['forms']), array($form_id)));
		if (!$all[$import_token]['forms']) unset($all[$import_token]);
		update_option(self::IMPORTS_OPTION, $all, false);
		return true;
	}

	/**
	 * Entries for a form, through the submit's own rules (_Public::validate_rows_efb with admin_edit, as an
	 * administrator's edit of an entry: sanitizing, the saved options, numbers, dates, the F03 field rules), stored in
	 * core's format. Each row: {answers: [{id_, value, …}], date?: "Y-m-d H:i:s", read?: 0-9, track?: string,
	 * entry_status?: 0-9, starred?: 0|1 (F14 columns, when the site has them)}. A row
	 * that does not pass is skipped and reported; nothing else of the site is touched (no e-mail, no payment, no
	 * capacity taken). @return array{imported: int, skipped: array}|\WP_Error
	 */
	public static function import_entries($form_id, array $rows) {
		$form_id = (int) $form_id;
		if (!self::can_manage()) return self::err('package_forbidden', 'You are not allowed to do this.', array('status' => 403));
		$form = class_exists('\\Emsfb\\Entries') ? Entries::form_rows($form_id) : null;
		if (!is_array($form)) return self::err('package_form_missing', 'The form was not found.', array('status' => 404));
		if (!class_exists('\\Emsfb\\_Public') || !_Public::$instance_efb) return self::err('package_unavailable', 'Entries cannot be imported right now.', array('status' => 503));
		$pub = _Public::$instance_efb->prepare_validation_efb($form_id);
		global $wpdb;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$name = (string) $wpdb->get_var($wpdb->prepare("SELECT form_name FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", $form_id));
		$out = array('imported' => 0, 'skipped' => array(), 'msg_ids' => array());
		// F14 columns (entry status, star) when this site has them: a backup restored keeps them.
		$cols = array_map('strtolower', (array) $wpdb->get_col("SHOW COLUMNS FROM `{$msg}`"));
		foreach (array_values($rows) as $i => $row) {
			if (count($out['msg_ids']) + count($out['skipped']) >= self::MAX_ROWS) { $out['skipped'][] = array('index' => $i, 'code' => 'package_too_many', 'm' => 'Too many entries in one call.'); continue; }
			$answers = is_array($row) && isset($row['answers']) && is_array($row['answers']) ? array_values(array_filter($row['answers'], 'is_array')) : null;
			if (!$answers) { $out['skipped'][] = array('index' => $i, 'code' => 'package_entry_invalid', 'm' => 'No answers.'); continue; }
			$v = $pub->validate_rows_efb($form, $answers, array('admin_edit' => true, 'form_condition' => ''));
			if (empty($v['ok'])) { $out['skipped'][] = array('index' => $i, 'code' => 'package_entry_refused', 'm' => isset($v['m']) ? wp_strip_all_tags((string) $v['m']) : '', 'field_id' => isset($v['field_id']) ? (string) $v['field_id'] : ''); continue; }
			$content = $pub->encode_rows_efb(isset($v['rows']) ? $v['rows'] : array());
			$date = isset($row['date']) && is_string($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['date']) ? $row['date'] : (function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s'));
			$track = isset($row['track']) && is_string($row['track']) && preg_match('/^[A-Za-z0-9_-]{4,40}$/', $row['track']) && !$wpdb->get_var($wpdb->prepare("SELECT msg_id FROM `{$msg}` WHERE track = %s", $row['track'])) ? $row['track'] : strtoupper(substr(md5(uniqid('efb', true) . $i), 0, 10));
			$rec = array('form_id' => $form_id, 'track' => $track, 'ip' => '', 'form_title_x' => function_exists('sanitize_text_field') ? sanitize_text_field($name) : $name, 'content' => $content, 'date' => $date, 'read_' => isset($row['read']) ? min(9, max(0, (int) $row['read'])) : 0, 'read_by' => '');
			if (in_array('entry_status', $cols, true) && isset($row['entry_status'])) $rec['entry_status'] = min(9, max(0, (int) $row['entry_status']));
			if (in_array('starred', $cols, true) && isset($row['starred'])) $rec['starred'] = empty($row['starred']) ? 0 : 1;
			if ($wpdb->insert($msg, $rec) === false) { $out['skipped'][] = array('index' => $i, 'code' => 'package_entry_not_saved', 'm' => 'The entry could not be saved.'); continue; }
			$out['msg_ids'][] = (int) $wpdb->insert_id;
			$out['imported']++;
			if (function_exists('do_action')) do_action('efb_entry_imported', (int) $wpdb->insert_id, $form_id);
		}
		return $out;
	}

	/** What the importing site should know (not refusals): Pro fields, add-ons, media and redirects of another host, CAPTCHA service. */
	public static function import_warnings(array $rows) {
		$w = array();
		$host = function_exists('home_url') ? (string) parse_url(home_url(), PHP_URL_HOST) : '';
		foreach ($rows as $i => $r) {
			foreach (array('src', 'rePage') as $k) {
				if (!isset($r[$k]) || !is_string($r[$k]) || $r[$k] === '') continue;
				$u = str_replace('@efb@', '/', $r[$k]);
				$h = (string) parse_url($u, PHP_URL_HOST);
				if ($h !== '' && $host !== '' && strcasecmp($h, $host) !== 0) $w[] = array('code' => $k === 'src' ? 'media_other_host' : 'redirect_other_host', 'row' => $i, 'host' => $h);
			}
		}
		if (!empty($rows[0]['captcha_provider']) && class_exists('\\Emsfb\\Captcha')) {
			$ready = Captcha::ready_map();
			$p = (string) $rows[0]['captcha_provider'];
			if (isset($ready[$p]) && !$ready[$p]) $w[] = array('code' => 'captcha_not_ready', 'provider' => $p);
		}
		if (!empty($rows[0]['logic_rules']) && !self::addon_active('AdnSMF')) $w[] = array('code' => 'logic_addon_inactive', 'addon' => 'AdnSMF');
		return $w;
	}

	/* ------------------------------------------------------------------ import */

	/**
	 * Creates a NEW form from a package. args: name (optional), user_id. Fires efb_form_created (source "import"),
	 * applies the extras (P23), and returns the payment readiness of the new form.
	 */
	public static function import($package, array $args = array()) {
		$v = self::validate($package, $args);
		if (!$v['ok']) {
			$e = $v['errors'][0];
			return self::err($e['code'], $e['m'], array('status' => 400, 'errors' => $v['errors'], 'dropped' => $v['dropped']));
		}
		global $wpdb;
		$rows = $v['form']['form_structer'];
		$name = isset($args['name']) && is_scalar($args['name']) && $args['name'] !== '' ? self::text((string) $args['name'], 250) : $v['form']['form_name'];
		if ($name === '') $name = isset($rows[0]['formName']) && is_scalar($rows[0]['formName']) ? self::text((string) $rows[0]['formName'], 250) : 'Imported form';
		$t = $wpdb->prefix . 'emsfb_form';
		if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE form_name = %s", $name)) > 0) {
			$name = self::text(sprintf(function_exists('__') ? __('%s (imported)', 'easy-form-builder') : '%s (imported)', $name), 250);
		}
		$type = in_array($rows[0]['type'], self::ROW0_TYPES, true) ? $rows[0]['type'] : 'form';
		$json = function_exists('wp_json_encode') ? wp_json_encode($rows, JSON_UNESCAPED_UNICODE) : json_encode($rows, JSON_UNESCAPED_UNICODE);
		$uid = isset($args['user_id']) ? (int) $args['user_id'] : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
		$ok = $wpdb->insert($t, array('form_name' => $name, 'form_structer' => str_replace('"', '\\"', $json), 'form_email' => '', 'form_created_by' => $uid, 'form_type' => $type, 'form_create_date' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s')));
		if ($ok === false) return self::err('package_not_saved', 'The form could not be saved.', array('status' => 500));
		$fid = (int) $wpdb->insert_id;
		if ($type === 'login' || $type === 'register') do_action('create_temporary_links_table_Emsfb');
		do_action('efb_form_created', $fid, $rows, array('form_id' => $fid, 'source' => 'import', 'user_id' => $uid));
		$x = self::import_extras($fid, $v['extras'], array('context' => 'import', 'id_map' => $v['id_map']));
		$token = self::remember_import($fid, isset($args['import_token']) ? (string) $args['import_token'] : '', $uid);
		$res = array('form_id' => $fid, 'import_token' => $token, 'form_name' => $name, 'form_type' => $type, 'shortcode' => '[EMS_Form_Builder id=' . $fid . ']', 'dropped' => $v['dropped'],
			'notices' => array_merge($v['notices'], $x['notices']), 'extras_applied' => $x['applied'], 'id_map' => $v['id_map']);
		if (class_exists('\\Emsfb\\Payments\\Readiness')) {
			$a = Payments\Readiness::save_answer(array(), $rows, $fid);
			if (isset($a['payment_ready'])) $res['payment'] = array('ready' => (bool) $a['payment_ready'], 'issues' => isset($a['payment_issues']) ? $a['payment_issues'] : array());
		}
		return $res;
	}

	/* ------------------------------------------------------------------ registry */

	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$a = self::api();
		$out['form_package'] = array(
			'v' => self::API_VERSION, 'spec' => 'F15-D / F20 P23', 'schema' => self::SCHEMA, 'php' => 'efb_form_package_api(); Emsfb\\Form_Package::export / validate / import',
			'filters' => $a['filters'], 'limits' => $a['limits'], 'rules' => array('import always creates a new form', 'ids kept unless invalid or duplicated (then remapped through efb_form_package_remap_ids)', 'the builder / AutoPilot save path runs on every import', 'secrets and site-only keys never exported', 'an add-on block is dropped with a notice when its add-on is not active'),
			'ui' => 'none in core (the plugin Easy Form Builder Data Tools)', 'plan' => 'free',
		);
		return $out;
	}
}
