<?php
/**
 * F06: global functions of the calendar contract (docs/feature-gaps/reference/calendar-engine-contract.en.md §5),
 * so add-ons (Booking, the AI site assistant) do not depend on class names. All answered by \Emsfb\Calendar.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_calendar_convert')) {
	/**
	 * efb_calendar_convert('1403-12-30', 'persian', 'gregory') → ['y'=>2025,'m'=>3,'d'=>20,'iso'=>'2025-03-20','approx'=>false]
	 * @param string|array $date 'YYYY-MM-DD' or ['y'=>,'m'=>,'d'=>]; $args['hijri_adjust'] int.
	 * @return array|\WP_Error
	 */
	function efb_calendar_convert($date, $from_cal, $to_cal, $args = array()) {
		return \Emsfb\Calendar::convert($date, (string) $from_cal, (string) $to_cal, is_array($args) ? $args : array());
	}
}
if (!function_exists('efb_calendar_engine')) {
	/** Engine object (to_jdn(), from_jdn(), days_in_month(), is_leap(), month_name($m, $locale)) or null. */
	function efb_calendar_engine($cal_id) { return \Emsfb\Calendar::engine((string) $cal_id); }
}
if (!function_exists('efb_calendar_format')) {
	/**
	 * A canonical date as text in a calendar. $pattern: 'auto' (the typed pattern of that calendar and
	 * locale), 'medium' ('30 Esfand 1403'), 'long' (with the weekday) or a pattern (contract §3 tokens).
	 * $args: digits (latn|arabext|arab|auto), hijri_adjust, time ('HH:mm').
	 */
	function efb_calendar_format($iso, $cal, $pattern = 'auto', $locale = '', $args = array()) {
		$locale = $locale !== '' ? $locale : (function_exists('get_locale') ? get_locale() : 'en_US');
		$args = is_array($args) ? $args : array();
		$e = \Emsfb\Calendar::engine((string) $cal);
		$l = \Emsfb\Calendar::iso_to_local((string) $iso, (string) $cal, isset($args['hijri_adjust']) ? (int) $args['hijri_adjust'] : 0);
		if (!$e || !$l) return '';
		$ld = \Emsfb\Calendar::locale_defaults($locale);
		$digits = isset($args['digits']) && $args['digits'] !== 'auto' ? $args['digits'] : $ld['digits'];
		if ($pattern === 'auto' || $pattern === '') $pattern = \Emsfb\Calendar::typed_pattern((string) $cal, $locale);
		elseif ($pattern === 'medium') $pattern = \Emsfb\Calendar::medium_pattern($locale);
		elseif ($pattern === 'long') $pattern = 'dddd ' . \Emsfb\Calendar::medium_pattern($locale);
		return \Emsfb\Calendar::format_local($e, array($l[0], $l[1], $l[2]), $pattern, $locale, array('digits' => $digits, 'time' => isset($args['time']) ? $args['time'] : null));
	}
}
if (!function_exists('efb_calendar_today')) {
	/** ISO date "now" in that zone ('site' = wp_timezone(), never date(), which is UTC in WordPress). */
	function efb_calendar_today($tz = 'site') { return \Emsfb\Calendar::today((string) $tz); }
}
if (!function_exists('efb_calendar_supported')) {
	/** ['gregory'=>1,'persian'=>1,...,'hebrew'=>3] for this server. */
	function efb_calendar_supported() { return \Emsfb\Calendar::supported(); }
}
if (!function_exists('efb_date_resolve_constraints')) {
	/**
	 * Resolved constraints (contract §4.2) after efb_date_constraints.
	 * $context: form_id, values [field_id => canonical value], now (DateTimeImmutable), locale, visitor_tz.
	 */
	function efb_date_resolve_constraints($field, $context = array()) {
		$context = is_array($context) ? $context : array();
		$c = \Emsfb\Calendar::normalize_field($field, $context);
		$r = \Emsfb\Calendar::resolve($c, $context);
		$r['holidays'] = (object) \Emsfb\Calendar::list_holidays($c, $r, $context);
		$fid = isset($context['form_id']) ? (int) $context['form_id'] : 0;
		return function_exists('apply_filters') ? apply_filters('efb_date_constraints', $r, is_array($field) ? $field : (array) $field, $fid, $context) : $r;
	}
}
if (!function_exists('efb_date_is_allowed')) {
	/** @return true|\WP_Error code = one of contract §4.3, data = ['date'=>..,'limit'=>..] */
	function efb_date_is_allowed($field, $iso, $context = array()) {
		$context = is_array($context) ? $context : array();
		$c = \Emsfb\Calendar::normalize_field($field, $context);
		$r = efb_date_resolve_constraints($field, $context);
		$context['availability'] = efb_date_availability_cb_efb($field, $context);
		$e = \Emsfb\Calendar::refuse($c, $r, (string) $iso, $context);
		if ($e === null) return true;
		return new \WP_Error($e['code'], \Emsfb\Calendar::message($e, $c), array('date' => isset($e['date']) ? $e['date'] : $iso, 'limit' => isset($e['limit']) ? $e['limit'] : null));
	}
}
if (!function_exists('efb_date_availability_cb_efb')) {
	/** The availability check of a field: efb_date_availability per date (null = no opinion). Internal. */
	function efb_date_availability_cb_efb($field, array $context) {
		if (!function_exists('has_filter') || !has_filter('efb_date_availability')) return null;
		$fid = isset($context['form_id']) ? (int) $context['form_id'] : 0;
		$row = is_array($field) ? $field : (array) $field;
		return function ($iso) use ($row, $fid, $context) {
			$s = apply_filters('efb_date_availability', null, $iso, $row, $fid, $context);
			return is_array($s) ? $s : null;
		};
	}
}
if (!function_exists('efb_date_validate_value')) {
	/**
	 * Full check of a submitted value (all modes, time, age, availability) with the server's own rules.
	 * @return array{ok:bool, row:array, errors:\WP_Error[]} row = the normalised submission row (contract §6)
	 */
	function efb_date_validate_value($field, $raw, $context = array()) {
		$context = is_array($context) ? $context : array();
		$c = \Emsfb\Calendar::normalize_field($field, $context);
		$context['normalized'] = $c;
		$context['resolved'] = efb_date_resolve_constraints($field, $context);
		$context['availability'] = efb_date_availability_cb_efb($field, $context);
		$res = \Emsfb\Calendar::validate_value($field, $raw, $context);
		$lang = isset($context['lang']) && is_array($context['lang']) ? $context['lang'] : array();
		$errors = array();
		foreach ($res['errors'] as $e) {
			$errors[] = new \WP_Error($e['code'], \Emsfb\Calendar::message($e, $c, isset($context['cal']) ? (string) $context['cal'] : '', $lang),
				array('date' => isset($e['date']) ? $e['date'] : null, 'limit' => isset($e['limit']) ? $e['limit'] : null));
		}
		return array('ok' => $res['ok'], 'row' => is_array($res['row']) ? $res['row'] : array(), 'errors' => $errors, 'warnings' => $res['warnings']);
	}
}
