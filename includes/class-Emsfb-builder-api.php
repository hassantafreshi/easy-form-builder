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
