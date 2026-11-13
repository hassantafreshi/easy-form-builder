<?php
/**
 * F06-F: the read-only REST routes of the calendar contract (docs/feature-gaps/reference/calendar-engine-contract.en.md
 * §6.1) and the form schema for assistants (§7.2):
 *
 *   GET  Emsfb/v1/calendar/availability?form_id&field_id&from&to[&with_slots]
 *   GET  Emsfb/v1/calendar/convert?date&from&to[&hijri_adjust]
 *   POST Emsfb/v1/calendar/check {form_id, field_id, value[, cal]}  (the server's verdict; reserves nothing)
 *   GET  Emsfb/v1/forms/{id}/schema
 *
 * Shared rules: a published form (status 1) and an advdate field of it, else 404 efb_not_found (also for a
 * hidden field, so ids cannot be probed); a members-only form answers 403 efb_login_required to guests and
 * 403 efb_role_denied to a role outside allowed_roles (F11); no nonce (read-only; cached pages carry stale
 * nonces); an IP rate limit (efb_calendar_rest_per_minute, 60). Capacity numbers never leave the server unless
 * the field opts in (bk_show_remaining). The schema never holds a secret: only what a visitor sees.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Calendar_Rest {

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 22, 2);
	}

	public static function routes() {
		register_rest_route('Emsfb/v1', 'calendar/availability', array('methods' => 'GET', 'callback' => array(__CLASS__, 'availability'), 'permission_callback' => '__return_true'));
		register_rest_route('Emsfb/v1', 'calendar/convert', array('methods' => 'GET', 'callback' => array(__CLASS__, 'convert'), 'permission_callback' => '__return_true'));
		register_rest_route('Emsfb/v1', 'calendar/check', array('methods' => 'POST', 'callback' => array(__CLASS__, 'check'), 'permission_callback' => '__return_true'));
		register_rest_route('Emsfb/v1', 'forms/(?P<id>\d+)/schema', array('methods' => 'GET', 'callback' => array(__CLASS__, 'schema'), 'permission_callback' => '__return_true'));
	}

	/* ---------------------------------------------------------------- shared */

	private static function err($code, $message, $status) {
		return new \WP_Error($code, $message, array('status' => (int) $status));
	}
	private static function rate_ok() {
		$limit = (int) apply_filters('efb_calendar_rest_per_minute', 60);
		if ($limit <= 0) return true;
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$ip = $fn && method_exists($fn, 'get_ip_address') ? (string) $fn->get_ip_address() : '';
		if ($ip === '') return true;
		if (class_exists('\\Emsfb\\Rate_Limit') && Rate_Limit::ready()) return Rate_Limit::hit('cal', $ip, 60, $limit);
		$key = 'efb_cal_' . md5($ip . '|' . gmdate('YmdHi'));
		$n = (int) get_transient($key);
		if ($n >= $limit) return false;
		set_transient($key, $n + 1, 2 * MINUTE_IN_SECONDS);
		return true;
	}
	/** @return array|\WP_Error the published form's rows */
	public static function form_rows($form_id) {
		global $wpdb;
		$form_id = (int) $form_id;
		if ($form_id <= 0) return self::err('efb_not_found', 'Not found.', 404);
		$row = $wpdb->get_row($wpdb->prepare("SELECT form_structer, form_type, status, form_name FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id)); // phpcs:ignore
		if (!$row || (isset($row->status) && $row->status !== null && (int) $row->status !== 1)) return self::err('efb_not_found', 'Not found.', 404);
		$rows = \emsfb_json_decode_efb(\emsfb_json_normalize_efb($row->form_structer), true);
		if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return self::err('efb_not_found', 'Not found.', 404);
		$rows[0]['__name'] = (string) $row->form_name;
		return $rows;
	}
	/** Members-only and role checks (the same answer as the rest of the public surface). */
	public static function access($rows, $form_id) {
		if (in_array($rows[0]['stateForm'] ?? false, array(true, 1, '1'), true) && !is_user_logged_in()) {
			return self::err('efb_login_required', __('Please log in to use this form.', 'easy-form-builder'), 403);
		}
		if (class_exists('\\Emsfb\\Form_Gate')) {
			$st = Form_Gate::state($rows[0], Form_Gate::context((int) $form_id));
			if ($st['state'] === 'role') return self::err('efb_role_denied', __('This form is not available for your account.', 'easy-form-builder'), 403);
		}
		return true;
	}
	/** @return array|\WP_Error [rows, field] */
	private static function field_of($form_id, $field_id) {
		$rows = self::form_rows($form_id);
		if (is_wp_error($rows)) return $rows;
		$a = self::access($rows, $form_id);
		if (is_wp_error($a)) return $a;
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_'], $r['type']) && (string) $r['id_'] === (string) $field_id && $r['type'] === 'advdate'
				&& !(isset($r['hidden']) && in_array($r['hidden'], array(1, '1', true), true))) {
				return array($rows, $r);
			}
		}
		return self::err('efb_not_found', 'Not found.', 404);
	}
	private static function respond($data, $cache) {
		$r = new \WP_REST_Response($data, 200);
		$r->header('Cache-Control', $cache);
		return $r;
	}
	private static function iso_param($v) {
		$s = Calendar::normalize(is_scalar($v) ? $v : '');
		return Calendar::iso_valid($s) ? $s : null;
	}

	/* ---------------------------------------------------------------- availability */

	public static function availability($request) {
		if (!self::rate_ok()) return self::err('efb_rate_limited', __('Too many requests. Please wait a minute.', 'easy-form-builder'), 429);
		$fid = (int) $request->get_param('form_id');
		$found = self::field_of($fid, (string) $request->get_param('field_id'));
		if (is_wp_error($found)) return $found;
		list($rows, $field) = $found;
		$from = self::iso_param($request->get_param('from'));
		$to = self::iso_param($request->get_param('to'));
		if ($from === null || $to === null || $to < $from) return self::err('efb_bad_request', 'from and to must be dates (YYYY-MM-DD), from first.', 400);
		if (Calendar::nights($from, $to) > 92) return self::err('efb_range_too_long', 'At most 92 days at a time.', 400);
		$ctx = array('form_id' => $fid);
		$c = Calendar::normalize_field($field, $ctx);
		$r = efb_date_resolve_constraints($field, $ctx);
		$show_r = !empty($field['bk_show_remaining']) && in_array($field['bk_show_remaining'], array(1, '1', true), true);
		$with_slots = in_array($request->get_param('with_slots'), array(1, '1', 'true'), true);
		$map = array();
		$has_filter = has_filter('efb_date_availability') || has_filter('efb_date_availability_range');
		if (has_filter('efb_date_availability_range')) {
			$m = apply_filters('efb_date_availability_range', array(), $from, $to, $field, $fid, $ctx);
			if (is_array($m)) $map = $m;
		}
		$dates = array();
		for ($j = Calendar::iso_to_jdn($from), $e = Calendar::iso_to_jdn($to); $j <= $e; $j++) {
			$iso = Calendar::jdn_to_iso($j);
			$x = Calendar::refuse($c, $r, $iso, $ctx);
			if ($x) {
				$s = in_array($x['code'], array('before_min', 'after_max'), true) ? 'out_of_range' : ($x['code'] === 'holiday' ? 'holiday' : 'closed');
				$dates[$iso] = array('s' => $s, 'code' => $x['code']);
				continue;
			}
			$st = array_key_exists($iso, $map) ? $map[$iso] : (has_filter('efb_date_availability') ? apply_filters('efb_date_availability', null, $iso, $field, $fid, $ctx) : null);
			if (is_int($st)) $st = array('available' => $st > 0, 'remaining' => $st);
			if (!is_array($st)) continue;
			$o = array();
			if (isset($st['available']) && $st['available'] === false) { $o['s'] = isset($st['code']) && $st['code'] === 'closed' ? 'closed' : 'full'; $o['code'] = $o['s'] === 'full' ? 'full' : 'unavailable'; }
			else $o['s'] = 'open';
			if ($show_r && isset($st['remaining']) && is_numeric($st['remaining'])) $o['r'] = (int) $st['remaining'];
			if ($with_slots && !empty($st['slots']) && is_array($st['slots'])) {
				$o['slots'] = array();
				foreach ($st['slots'] as $sl) {
					if (!is_array($sl) || !isset($sl['start'])) continue;
					$x2 = array('start' => (string) $sl['start'], 'end' => isset($sl['end']) ? (string) $sl['end'] : '', 'available' => !isset($sl['available']) || (bool) $sl['available']);
					if ($show_r && isset($sl['remaining']) && is_numeric($sl['remaining'])) $x2['r'] = (int) $sl['remaining'];
					$o['slots'][] = $x2;
				}
			}
			if ($o !== array('s' => 'open')) $dates[$iso] = $o;
		}
		return self::respond(array('v' => 1, 'form_id' => $fid, 'field_id' => (string) $field['id_'], 'tz' => $r['tz'], 'today' => $r['today'], 'min' => $r['min'], 'max' => $r['max'], 'dates' => (object) $dates),
			$has_filter ? 'private, max-age=15' : 'public, max-age=60');
	}

	/* ---------------------------------------------------------------- convert */

	public static function convert($request) {
		if (!self::rate_ok()) return self::err('efb_rate_limited', __('Too many requests. Please wait a minute.', 'easy-form-builder'), 429);
		$from = (string) $request->get_param('from');
		$to = (string) $request->get_param('to');
		$sup = Calendar::supported();
		if (!isset($sup[$from]) || !isset($sup[$to]) || $sup[$from] > 2 || $sup[$to] > 2) return self::err('efb_bad_request', 'Calendars: ' . implode(', ', array_keys(array_filter($sup, function ($t) { return $t <= 2; }))) . '.', 400);
		$r = Calendar::convert((string) $request->get_param('date'), $from, $to, array('hijri_adjust' => max(-2, min(2, (int) $request->get_param('hijri_adjust')))));
		if (is_wp_error($r) || !is_array($r) || !isset($r['iso'])) return self::err('efb_bad_request', 'Not a date in this calendar (YYYY-MM-DD).', 400);
		return self::respond($r, 'public, max-age=86400');
	}

	/* ---------------------------------------------------------------- check */

	public static function check($request) {
		if (!self::rate_ok()) return self::err('efb_rate_limited', __('Too many requests. Please wait a minute.', 'easy-form-builder'), 429);
		$p = $request->get_json_params();
		$p = is_array($p) ? $p : $request->get_params();
		$fid = isset($p['form_id']) ? (int) $p['form_id'] : 0;
		$found = self::field_of($fid, isset($p['field_id']) ? (string) $p['field_id'] : '');
		if (is_wp_error($found)) return $found;
		list($rows, $field) = $found;
		$value = isset($p['value']) && is_scalar($p['value']) ? (string) $p['value'] : '';
		$values = array();
		if (isset($p['values']) && is_array($p['values'])) foreach ($p['values'] as $k => $v) if (is_scalar($v)) $values[(string) $k] = (string) $v;
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$keys = array_map(function ($c) { return 'cal_err_' . $c; }, Calendar::CODES);
		$lang = $fn && method_exists($fn, 'text_efb') ? (array) $fn->text_efb(array_merge($keys, array('cal_nights_n', 'cal_days_n'))) : array();
		$res = efb_date_validate_value($field, $value, array('form_id' => $fid, 'values' => $values, 'cal' => isset($p['cal']) ? (string) $p['cal'] : '', 'visitor_tz' => isset($p['tz']) ? (string) $p['tz'] : '', 'lang' => $lang));
		if (!$res['ok']) {
			$errors = array();
			foreach ($res['errors'] as $e) {
				$d = (array) $e->get_error_data();
				$o = array('code' => $e->get_error_code(), 'message' => $e->get_error_message());
				if (!empty($d['date'])) $o['date'] = $d['date'];
				if (isset($d['limit']) && $d['limit'] !== null) $o['limit'] = $d['limit'];
				$errors[] = $o;
			}
			return self::respond(array('ok' => false, 'errors' => $errors), 'private, no-store');
		}
		$row = $res['row'];
		$out = array('ok' => true, 'value' => $row ? array('v' => 1, 'mode' => isset($row['mode']) ? $row['mode'] : 'single', 'iso' => isset($row['iso']) ? $row['iso'] : (isset($row['value']) ? $row['value'] : ''),
			'tz' => isset($row['tz']) ? $row['tz'] : '', 'cal' => isset($row['cal']) ? $row['cal'] : '', 'local' => isset($row['local']) ? $row['local'] : '', 'disp' => isset($row['disp']) ? $row['disp'] : '') : null);
		if ($row && isset($row['time'])) { $out['value']['time'] = $row['time']; if (isset($row['utc'])) $out['value']['utc'] = $row['utc']; }
		if (!empty($res['warnings'])) $out['warnings'] = $res['warnings'];
		return self::respond($out, 'private, no-store');
	}

	/* ---------------------------------------------------------------- schema */

	public static function schema($request) {
		if (!self::rate_ok()) return self::err('efb_rate_limited', __('Too many requests. Please wait a minute.', 'easy-form-builder'), 429);
		$s = Form_Schema::build((int) $request['id']);
		if (is_wp_error($s)) return $s;
		return self::respond($s, 'private, no-store');
	}

	/* ---------------------------------------------------------------- registry */

	/** setting:advdate_* entries (F06 §10.1) and the top-level calendar block (SitePilot manifest.features.advdate). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:advdate_basic'        => array('free', array('date_min=relative', 'date_min=absolute', 'date_max', 'disabled_weekdays', 'input_mode', 'cal_digits', 'week_start', 'display_format', 'tz'), 'cal_b_g_limits'),
			'setting:advdate_calendars'    => array('free_plus', array('cal_allowed[1..]', 'cal_switch', 'cal_secondary', 'cal_hijri_adjust'), 'cal_b_g_calendar'),
			'setting:advdate_dates'        => array('free_plus', array('disabled_dates', 'enabled_dates', 'holiday_sets', 'holidays_custom', 'holiday_mode'), 'cal_b_holiday_sets'),
			'setting:advdate_field_limits' => array('free_plus', array('date_min=field', 'date_max=field'), 'cal_b_lim_field'),
			'setting:advdate_age'          => array('free_plus', array('age_min', 'age_max'), 'cal_b_g_age'),
			'setting:advdate_time'         => array('free_plus', array('time_enabled', 'time_format', 'time_step', 'time_min', 'time_max'), 'cal_b_g_time'),
			'setting:advdate_booking'      => array('pro', array('date_mode=range', 'date_mode=multiple', 'range_unit', 'range_min_days', 'range_max_days', 'multi_max', 'time_by_weekday', 'availability'), 'cal_b_g_range'),
		);
		foreach ($entries as $key => $e) {
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($e[0], $package) : true;
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => 'field', 'package' => $e[0], 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $e[0] === 'free_plus' ? 3 : ($e[0] === 'pro' ? 1 : 0),
				'message_key' => $e[0] === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($e[0] === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[1], 'label_key' => $e[2],
			);
		}
		$rank = $package === null ? Calendar::site_plan_rank() : (Builder_Api::plan_at_least('pro', $package) ? 2 : (Builder_Api::plan_at_least('free_plus', $package) ? 1 : 0));
		$gated = array();
		if ($rank < 1) $gated = array_merge($gated, array('cal_allowed', 'cal_switch', 'cal_secondary', 'cal_hijri_adjust', 'disabled_dates', 'enabled_dates', 'holiday_sets', 'holidays_custom', 'date_min=field', 'date_max=field', 'age_min', 'age_max', 'time_enabled'));
		if ($rank < 2) $gated = array_merge($gated, array('date_mode=range', 'date_mode=multiple', 'time_by_weekday', 'availability'));
		$out['calendar'] = array(
			'version'     => Calendar::VERSION,
			'field_type'  => 'advdate',
			'keys'        => Calendar::FIELD_KEYS,
			'gated_keys'  => $gated,
			'codes'       => Calendar::CODES,
			'calendars'   => Calendar::supported(),
			'holiday_sets' => Calendar::holiday_set_ids(),
			'locale'      => function_exists('get_locale') ? get_locale() : 'en_US',
			'defaults'    => Calendar::locale_defaults_filtered(function_exists('get_locale') ? get_locale() : 'en_US'),
			'site_tz'     => Calendar::site_tz(),
			'builder'     => array('efbBuilderApi.setDateConfig', 'efbBuilderApi.upgradeDateField', 'efbBuilderApi.rollbackDateField'),
			'page_api'    => 'window.EFBCalendar',
			'rest'        => array('calendar/availability', 'calendar/convert', 'calendar/check', 'forms/{id}/schema'),
			'error_code_prefix' => 'efb_date_',
			'save_codes'  => array('date_date_min_after_max', 'date_time_min_after_max', 'date_range_min_after_max', 'date_age_min_after_max', 'date_limit_cycle', 'date_limit_self'),
			'spec'        => 'F06',
		);
		return $out;
	}
}

/**
 * F06 §11.2 / contract §7.2: what an assistant needs to fill a form in the page, and nothing a visitor does
 * not already see. Built from a whitelist of keys per row: no notification e-mails, webhooks, SMS or
 * Telegram settings, logic internals, payment keys, F03 patterns or e-mail domain lists, counts or answers.
 */
class Form_Schema {

	const STRUCTURAL = array('step', 'option', 'r_matrix', 'html', 'heading', 'link', 'ttlprc', 'steps');

	public static function build($form_id, array $context = array()) {
		$rows = Calendar_Rest::form_rows($form_id);
		if (is_wp_error($rows)) return $rows;
		$a = Calendar_Rest::access($rows, $form_id);
		if (is_wp_error($a)) return $a;
		$locale = function_exists('get_locale') ? get_locale() : 'en_US';
		$s0 = $rows[0];
		$out = array(
			'efb_schema' => 1, 'form_id' => (int) $form_id, 'title' => isset($s0['formName']) && is_string($s0['formName']) ? $s0['formName'] : (string) $s0['__name'],
			'locale' => $locale, 'dir' => function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr', 'steps' => array(),
			'submit' => array('how' => 'in_page', 'note' => 'fill fields through the page APIs, then let the visitor press the submit button'),
			'fields' => array(),
		);
		$options = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['type'], $r['parent']) && in_array($r['type'], array('option', 'r_matrix'), true)) $options[(string) $r['parent']][] = array('id' => (string) $r['id_'], 'label' => isset($r['value']) && is_scalar($r['value']) ? (string) $r['value'] : '');
		}
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['type'])) continue;
			if ($r['type'] === 'step') { $out['steps'][] = array('step' => (int) ($r['step'] ?? $r['id_'] ?? 0), 'title' => isset($r['name']) && is_scalar($r['name']) ? (string) $r['name'] : ''); continue; }
			if (in_array($r['type'], self::STRUCTURAL, true) || !isset($r['id_'])) continue;
			if (isset($r['hidden']) && in_array($r['hidden'], array(1, '1', true), true)) continue;
			$id = (string) $r['id_'];
			$e = array(
				'id' => $id, 'type' => (string) $r['type'], 'label' => isset($r['name']) && is_scalar($r['name']) ? (string) $r['name'] : '',
				'help' => isset($r['message']) && is_scalar($r['message']) ? wp_strip_all_tags((string) $r['message']) : '',
				'step' => isset($r['step']) ? (int) $r['step'] : 1, 'required' => isset($r['required']) && in_array($r['required'], array(1, '1', true), true),
			);
			if (isset($r['disabled']) && in_array($r['disabled'], array(1, '1', true), true)) $e['disabled'] = true;
			if (!empty($options[$id])) $e['options'] = $options[$id];
			if ($r['type'] === 'advdate') {
				$e = array_merge($e, self::date_entry($r, (int) $form_id, $locale));
			} else {
				$e['fill'] = array('api' => 'dom', 'input' => $id . '_');
			}
			$e = apply_filters('efb_form_schema_field', $e, $r, (int) $form_id);
			if (is_array($e)) $out['fields'][] = $e;
		}
		return apply_filters('efb_form_schema', $out, (int) $form_id, $context);
	}

	/** The advdate entry (contract §7.2): limits as configured (cacheable), calendars, the fill call. */
	public static function date_entry(array $r, $form_id, $locale) {
		$c = Calendar::normalize_field($r, array('locale' => $locale));
		$id = (string) $r['id_'];
		$cals = array('enum' => $c['cal_allowed']);
		$date = array('type' => 'string', 'format' => 'date');
		if ($c['date_mode'] === 'range') {
			$vs = array('type' => 'object', 'oneOf' => array(
				array('properties' => array('start' => $date, 'end' => $date), 'required' => array('start', 'end')),
				array('properties' => array('cal' => $cals, 'start' => array('type' => 'string'), 'end' => array('type' => 'string')), 'required' => array('cal', 'start', 'end')),
			));
		} elseif ($c['date_mode'] === 'multiple') {
			$vs = array('type' => 'object', 'oneOf' => array(
				array('properties' => array('cal' => $cals, 'dates' => array('type' => 'array', 'items' => array('type' => 'string'))), 'required' => array('cal', 'dates')),
				array('type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}(,\\d{4}-\\d{2}-\\d{2})*$'),
			));
		} else {
			$single = array('properties' => array('cal' => $cals, 'date' => array('type' => 'string'), 'time' => array('type' => 'string', 'pattern' => '^([01]\\d|2[0-3]):[0-5]\\d$')), 'required' => array('cal', 'date'));
			if ($c['time_enabled']) $single['required'][] = 'time';
			$vs = array('type' => 'object', 'oneOf' => array($single, array('type' => 'string', 'format' => $c['time_enabled'] ? 'date-time-local' : 'date')));
		}
		$cons = array('min' => $c['date_min'], 'max' => $c['date_max'], 'disabled_weekdays' => $c['disabled_weekdays']);
		if ($c['enabled_dates']) $cons['enabled'] = $c['enabled_dates'];
		if ($c['disabled_dates']) $cons['disabled'] = $c['disabled_dates'];
		if ($c['holiday_sets']) $cons['holiday_sets'] = $c['holiday_sets'];
		if ($c['holidays_custom']) $cons['holidays'] = count($c['holidays_custom']);
		if ($c['date_mode'] === 'range') $cons['range'] = array('unit' => $c['range_unit'], 'min' => $c['range_min_days'], 'max' => $c['range_max_days']);
		if ($c['date_mode'] === 'multiple' && $c['multi_max']) $cons['multi_max'] = $c['multi_max'];
		if ($c['time_enabled']) $cons['time'] = array('step' => $c['time_step'], 'min' => $c['time_min'], 'max' => $c['time_max'], 'by_weekday' => $c['time_by_weekday'] ? $c['time_by_weekday'] : new \stdClass());
		if ($c['age_min'] !== null || $c['age_max'] !== null) $cons['age'] = array('min' => $c['age_min'], 'max' => $c['age_max']);
		return array(
			'fill' => array('api' => 'EFBCalendar', 'call' => "EFBCalendar.get('" . $id . "', " . (int) $form_id . ').setValue(value)'),
			'value_schema' => $vs,
			'x-efb-date' => array(
				'mode' => $c['date_mode'], 'calendars' => array('default' => $c['cal_default'], 'allowed' => $c['cal_allowed'], 'switch' => (bool) $c['cal_switch']),
				'tz' => $c['tz'] === 'site' ? Calendar::site_tz() : $c['tz'], 'constraints' => $cons,
				'availability_url' => function_exists('rest_url') ? rest_url('Emsfb/v1/calendar/availability') . '?form_id=' . (int) $form_id . '&field_id=' . rawurlencode($id) : '',
				'check_url' => function_exists('rest_url') ? rest_url('Emsfb/v1/calendar/check') : '',
				'error_codes' => 'see calendar-engine-contract.en.md §4.3',
			),
		);
	}
}
