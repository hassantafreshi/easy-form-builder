<?php
/**
 * F06-G: the advanced date field through a real save and the upgrade of the old date fields (§6.4).
 *
 *  1. Every advdate key (contract §4.1) survives the save round trip of the builder (JSON.stringify ->
 *     transport -> wp_unslash -> emsfb_json_normalize_efb -> sanitize_obj_msg_efb -> stored column ->
 *     reader), arrays and objects included; hostile values come back as the key's default; bk_* keys of the
 *     Booking add-on keep their JSON; the same keys on a text field keep today's behaviour (wiped arrays).
 *  2. Calendar::validate_rows(): contradicting limits are save errors with a path, a missing field limit
 *     source is a warning.
 *  3. Calendar::upgrade_row() on the rows of tests/e2e-addon-recovery/fixtures/pdate.json and on date
 *     samples: the mapping table of §6.4 (milen 1 -> relative 0, an ISO date -> absolute, 0 -> none,
 *     a Jalali default value -> ISO, unreadable -> empty), the old keys kept, keepLocal ->
 *     store_format legacy_local; rollback_row() gives back the old field exactly.
 *
 * Run: php tests/test-advdate-upgrade.php   (and php -n)
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

/* WordPress-faithful stubs for the sanitiser path (arrays become '', as WordPress does). */
function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) {
		return '';
	}
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);          // WordPress strips URL-encoded octets
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);            // and collapses whitespace
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/class-Emsfb-masks.php';
require $EFB . '/includes/class-Emsfb-field-rules.php';
require $EFB . '/includes/class-Emsfb-calendar.php';
require $EFB . '/includes/functions.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) {
		$pass++;
		echo "[PASS] $label\n";
		return;
	}
	$fail++;
	echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();

/** The builder's JSON.stringify (no slash or unicode escaping), then its transport replace. */
function builder_payload($form) {
	$json = json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	return str_replace('\\"', '"', $json);           // actionSendData_emsFormBuilder: .replace(/\\\"/g, '"')
}
/** update_form_id_Emsfb + a reader. Returns the decoded rows, or null when the payload is not JSON. */
function save_and_read($fn, $payload) {
	$post = wp_unslash(wp_slash($payload));            // WordPress adds slashes to $_POST; the handler removes them
	$valp = json_decode(emsfb_json_normalize_efb($post), true);
	if (!is_array($valp)) {
		return null;
	}
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
}
function form_with($rows) {
	return array_merge(array(
		array('type' => 'form', 'steps' => '1', 'formName' => 'Rules', 'email' => ''),
		array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'name' => 'Step 1', 'step' => '1'),
	), $rows);
}
function fld($id, $extra) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'F ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'placeholder' => '', 'value' => ''), $extra);
}
/** One key on one field, through the round trip: [key, value as sent, expected stored value, extra row keys]. */
function roundtrip_keys($fn, array $cases) {
	$rows = array();
	foreach ($cases as $i => $c) $rows[] = fld('k' . $i, array_merge(isset($c[3]) ? $c[3] : array(), array($c[0] => $c[1])));
	$out = save_and_read($fn, builder_payload(form_with($rows)));
	t('the form decodes after the round trip (' . count($cases) . ' keys)', is_array($out), true);
	foreach ($cases as $i => $c) {
		$got = is_array($out) && isset($out[$i + 2]) && array_key_exists($c[0], $out[$i + 2]) ? $out[$i + 2][$c[0]] : '(missing)';
		t($c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($c[2], JSON_UNESCAPED_UNICODE), $got, $c[2]);
	}
}


function adv($id, $extra) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'advdate', 'elementId' => 'advdate', 'name' => 'D ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'value' => ''), $extra);
}
/* 1 */
$all = array(
	'cal_v' => 1, 'cal_default' => 'persian', 'cal_allowed' => array('persian', 'gregory', 'islamic-umalqura'), 'cal_switch' => 1, 'cal_secondary' => 'islamic-umalqura',
	'cal_hijri_adjust' => -1, 'cal_locale' => 'fa_IR', 'cal_digits' => 'arabext', 'week_start' => 6, 'date_mode' => 'range',
	'date_min' => array('type' => 'relative', 'value' => 1, 'unit' => 'day'), 'date_max' => array('type' => 'field', 'field' => 'other1', 'offset' => 2, 'unit' => 'week'),
	'disabled_weekdays' => array(5), 'disabled_dates' => array('2026-12-25', '2027-01-01/2027-01-03'), 'enabled_dates' => array('2026-11-01/2027-06-30'),
	'holiday_sets' => array('ir-official', 'islamic-major'), 'holidays_custom' => array(array('cal' => 'persian', 'md' => '01-01', 'days' => 4, 'label' => 'نوروز'), array('date' => '2026-12-25', 'label' => array('en' => 'Christmas', 'fa' => 'کریسمس'))),
	'holiday_mode' => 'mark', 'range_unit' => 'days', 'range_min_days' => 1, 'range_max_days' => 14, 'multi_max' => 3, 'time_enabled' => 1, 'time_format' => '24', 'time_step' => 30,
	'time_min' => '09:00', 'time_max' => '17:00', 'time_by_weekday' => array('5' => array(), '6' => array(array('10:00', '14:00'))), 'age_min' => 18, 'age_max' => 65,
	'tz' => 'Asia/Tehran', 'display_format' => 'YYYY/MM/DD', 'store_format' => 'legacy_local', 'input_mode' => 'typed', 'availability' => 'booking',
	'bk_resource' => 'room-1', 'bk_capacity' => 3, 'bk_rules' => array(array('days' => array(1, 2), 'cap' => 2)),
);
$out = save_and_read($fn, builder_payload(form_with(array(adv('a1', $all), adv('other1', array())))));
t('1 the form decodes', is_array($out), true);
foreach ($all as $k => $v) t('1 kept: ' . $k, isset($out[2][$k]) ? $out[2][$k] : '(missing)', $v);
$hostile = array('cal_default' => 'klingon', 'cal_allowed' => array('persian', '<b>x</b>'), 'cal_hijri_adjust' => 9, 'date_mode' => 'weekly', 'date_min' => array('type' => 'relative', 'value' => 'soon'),
	'disabled_weekdays' => array(0, 8, '5'), 'disabled_dates' => array('2026-02-30', '2026-03-01'), 'holiday_sets' => array('../x', 'ir-official'), 'time_min' => '25:00', 'time_step' => 0,
	'age_min' => 'eighteen', 'tz' => 'Mars/Olympus', 'display_format' => 'YYYY<script>', 'store_format' => 'local', 'input_mode' => 'voice', 'time_by_weekday' => array('9' => array(), '6' => 'x'),
	'holidays_custom' => array(array('cal' => 'persian', 'md' => '13-40', 'label' => 'x'), array('date' => '2026-12-25', 'label' => '<i>Xmas</i>  day')));
$out = save_and_read($fn, builder_payload(form_with(array(adv('h1', $hostile)))));
$exp = array('cal_default' => 'auto', 'cal_allowed' => array('persian'), 'cal_hijri_adjust' => 2, 'date_mode' => 'single', 'date_min' => array('type' => 'none'), 'disabled_weekdays' => array(5),
	'disabled_dates' => array('2026-03-01'), 'holiday_sets' => array('ir-official'), 'time_min' => '', 'time_step' => 1, 'age_min' => '', 'tz' => 'site', 'display_format' => 'auto',
	'store_format' => 'iso', 'input_mode' => 'both', 'time_by_weekday' => array(), 'holidays_custom' => array(array('date' => '2026-12-25', 'label' => 'Xmas day')));
foreach ($exp as $k => $v) t('1 hostile ' . $k . ' -> default', isset($out[2][$k]) ? $out[2][$k] : '(missing)', $v);
$out = save_and_read($fn, builder_payload(form_with(array(array('id_' => 't1', 'dataId' => 't1-id', 'type' => 'text', 'name' => 'T', 'step' => '1', 'amount' => '2', 'cal_allowed' => array('persian'), 'tz' => 'Asia/Tehran')))));
t('1 the same keys on a text field: today\'s behaviour (arrays wiped)', array($out[2]['cal_allowed'], $out[2]['tz']), array('', 'Asia/Tehran'));

/* 2 */
$rows = form_with(array(adv('a', array('date_min' => array('type' => 'field', 'field' => 'b'))), adv('b', array('date_max' => array('type' => 'field', 'field' => 'a'))),
	adv('c', array('date_min' => array('type' => 'absolute', 'value' => '2026-05-01'), 'date_max' => array('type' => 'absolute', 'value' => '2026-04-01'))),
	adv('d', array('date_min' => array('type' => 'field', 'field' => 'gone')))));
$v = \Emsfb\Calendar::validate_rows($rows);
t('2 errors: a circle of field limits, min after max', array_map(function ($e) { return $e['code'] . ' ' . $e['path']; }, $v['errors']), array('date_limit_cycle rows[2].date_min', 'date_limit_cycle rows[3].date_max', 'date_date_min_after_max rows[4].date_min'));
t('2 warning: a limit naming a field that is not there', array_map(function ($e) { return $e['code'] . ' ' . $e['path']; }, $v['warnings']), array('date_dangling_reference rows[5].date_min'));
t('2 the sentence', $v['errors'][2]['m'], 'The earliest date is after the latest date.');
t('2 a clean form', \Emsfb\Calendar::validate_rows(form_with(array(adv('x', array('date_min' => array('type' => 'relative', 'value' => 0)))))), array('errors' => array(), 'warnings' => array()));

/* 3 */
$fx = json_decode(file_get_contents(__DIR__ . '/e2e-addon-recovery/fixtures/pdate.json'), true);
$frows = json_decode(emsfb_json_normalize_efb($fx['structure']), true);
$prow = null;
foreach ($frows as $r) if (is_array($r) && isset($r['type']) && $r['type'] === 'pdate') { $prow = $r; break; }
t('3 the fixture has a pdate field', is_array($prow), true);
$prow['value'] = '۱۴۰۳/۱۲/۳۰';
$u = \Emsfb\Calendar::upgrade_row($prow);
t('3 pdate -> advdate, Persian, one calendar, single', array($u['type'], $u['elementId'], $u['cal_default'], $u['cal_allowed'], $u['date_mode'], $u['cal_v']), array('advdate', 'advdate', 'persian', array('persian'), 'single', 1));
t('3 the Jalali default value -> ISO, the old one kept', array($u['value'], $u['upg_from'], $u['upg_value']), array('2025-03-20', 'pdate', '۱۴۰۳/۱۲/۳۰'));
t('3 pdate had no limits', array($u['date_min'], $u['date_max']), array(array('type' => 'none'), array('type' => 'none')));
$keep = array();
foreach (array('id_', 'name', 'required', 'message', 'classes', 'hidden', 'disabled', 'step', 'amount', 'el_border_color', 'label_text_color') as $k) if (array_key_exists($k, $prow)) $keep[$k] = $prow[$k] === $u[$k];
t('3 every other key kept as it was', array_unique(array_values($keep)), array(true));
t('3 keepLocal -> store_format legacy_local', \Emsfb\Calendar::upgrade_row($prow, true)['store_format'], 'legacy_local');
$prow['value'] = '1404-12-30';
t('3 an unreadable default (1404-12-30 does not exist) -> empty', \Emsfb\Calendar::upgrade_row($prow)['value'], '');
$a = \Emsfb\Calendar::upgrade_row(array('id_' => 'q', 'type' => 'ardate', 'value' => '1446-10-01'));
t('3 ardate -> Umm al-Qura', array($a['cal_default'], $a['value']), array('islamic-umalqura', '2025-03-30'));
$d = \Emsfb\Calendar::upgrade_row(array('id_' => 'd', 'type' => 'date', 'milen' => '1', 'mlen' => '2027-01-31', 'value' => '2026-12-01'));
t('3 date: milen 1 -> relative 0, an ISO mlen -> absolute, value kept, old keys kept', array($d['date_min'], $d['date_max'], $d['value'], $d['milen'], $d['mlen'], $d['cal_default']),
	array(array('type' => 'relative', 'value' => 0, 'unit' => 'day'), array('type' => 'absolute', 'value' => '2027-01-31'), '2026-12-01', '1', '2027-01-31', 'gregory'));
$d0 = \Emsfb\Calendar::upgrade_row(array('id_' => 'd', 'type' => 'date', 'milen' => '0', 'mlen' => ''));
t('3 date: milen 0 / empty -> none', array($d0['date_min'], $d0['date_max']), array(array('type' => 'none'), array('type' => 'none')));
$back = \Emsfb\Calendar::rollback_row(\Emsfb\Calendar::upgrade_row($prow));
$orig = $prow;
t('3 rollback gives the old row back exactly', $back, $orig);
t('3 a text row is not touched', \Emsfb\Calendar::upgrade_row(array('type' => 'text', 'value' => 'x')), array('type' => 'text', 'value' => 'x'));
$up = save_and_read($fn, builder_payload(form_with(array(\Emsfb\Calendar::upgrade_row(array_merge($frows[2] ?? array(), $prow))))));
t('3 an upgraded row survives the save (upg_from, upg_value, cal_allowed)', array($up[2]['type'], $up[2]['upg_from'], $up[2]['cal_allowed']), array('advdate', 'pdate', array('persian')));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
