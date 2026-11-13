<?php
/**
 * F06-C: the server's date rules (\Emsfb\Calendar::validate_value and friends) on the shared fixture
 * tests/fixtures/calendar-constraint-cases.json, without WordPress; the same cases as
 * tests/test-calendar-constraints.js. Also the contract functions (efb_date_validate_value with
 * WP_Error messages, efb_date_is_allowed, efb_date_resolve_constraints) through small WordPress stubs,
 * the efb_date_constraints / efb_date_availability filters and the message text (dates in the field's
 * calendar inside FSI / PDI).
 *
 * Run: php tests/test-calendar-constraints.php   (and php -n)
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_filters'] = array();
function apply_filters($tag, $value) {
	$args = func_get_args(); array_shift($args);
	if (!empty($GLOBALS['efb_filters'][$tag])) foreach ($GLOBALS['efb_filters'][$tag] as $cb) { $args[0] = call_user_func_array($cb, $args); }
	return $args[0];
}
function has_filter($tag) { return !empty($GLOBALS['efb_filters'][$tag]); }
function add_filter($tag, $cb) { $GLOBALS['efb_filters'][$tag][] = $cb; }
class WP_Error { public $code; public $message; public $data; function __construct($c = '', $m = '', $d = array()) { $this->code = $c; $this->message = $m; $this->data = $d; } function get_error_code() { return $this->code; } function get_error_message() { return $this->message; } }
require_once __DIR__ . '/../includes/class-Emsfb-calendar.php';
require_once __DIR__ . '/../includes/calendar-functions-efb.php';
use Emsfb\Calendar as C;

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
	$failed++; echo "[FAIL] {$name}\n   expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n   actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$FX = json_decode(file_get_contents(__DIR__ . '/fixtures/calendar-constraint-cases.json'), true);
foreach ($FX['cases'] as $k) {
	$r = C::validate_value($k['field'], $k['input'], $k['ctx']);
	$got = $r['ok'] ? 'ok' : $r['errors'][0]['code'];
	t($k['name'] . ' -> ' . $k['expect'], $got, $k['expect']);
	if (isset($k['row']) && $r['ok']) {
		$sub = array(); foreach (array_keys($k['row']) as $key) $sub[$key] = isset($r['row'][$key]) ? $r['row'][$key] : null;
		t($k['name'] . ' (row)', $sub, $k['row']);
	}
	if (isset($k['warnings'])) t($k['name'] . ' (warnings)', array_map(function ($w) { return $w['code']; }, $r['warnings']), $k['warnings']);
}

/* contract functions */
$f = array('type' => 'advdate', 'cal_default' => 'persian', 'date_min' => array('type' => 'relative', 'value' => 1), 'disabled_weekdays' => array(5), 'required' => 1);
$ctx = array('locale' => 'fa_IR', 'now' => '2026-11-13T10:00:00Z', 'site_tz' => 'Asia/Tehran', 'plan' => 'pro', 'form_id' => 7);
$v = efb_date_validate_value($f, '2026-11-13', $ctx);
t('efb_date_validate_value: WP_Error with the code', array($v['ok'], $v['errors'][0]->get_error_code()), array(false, 'before_min'));
t('message: the limit in the field calendar inside FSI/PDI', $v['errors'][0]->get_error_message(), "Please choose \u{2068}۲۳ آبان ۱۴۰۵\u{2069} or a later date.");
t('message: data date / limit', $v['errors'][0]->data, array('date' => '2026-11-13', 'limit' => '2026-11-14'));
$v = efb_date_validate_value($f, '2026-11-14', $ctx);
t('efb_date_validate_value ok: row recomputed', array($v['ok'], $v['row']['disp'], $v['row']['local']), array(true, '۲۳ آبان ۱۴۰۵', '1405-08-23'));
$v = efb_date_validate_value($f, '2026-11-20', $ctx + array('lang' => array('cal_err_weekday_disabled' => 'Closed on %s')));
t('own phrase used', $v['errors'][0]->get_error_message(), 'Closed on ');
t('efb_date_is_allowed true', efb_date_is_allowed($f, '2026-11-14', $ctx), true);
$e = efb_date_is_allowed($f, '2026-11-20', $ctx);
t('efb_date_is_allowed WP_Error', $e instanceof WP_Error ? $e->get_error_code() : $e, 'weekday_disabled');
$rc = efb_date_resolve_constraints($f, $ctx);
t('efb_date_resolve_constraints: §4.2 keys', array_keys($rc), array('today', 'tz', 'min', 'max', 'min_source', 'max_source', 'min_empty', 'max_empty', 'disabled_weekdays', 'disabled', 'enabled', 'holidays', 'holiday_mode', 'mode', 'range', 'multi_max', 'time', 'age', 'calendars'));
add_filter('efb_date_constraints', function ($r, $field, $form_id) { if ($form_id === 7) $r['max'] = '2026-11-30'; return $r; });
$v = efb_date_validate_value($f, '2026-12-01', $ctx);
t('efb_date_constraints narrows the server limit', $v['errors'][0]->get_error_code(), 'after_max');
add_filter('efb_date_availability', function ($s, $iso) { return $iso === '2026-11-24' ? array('available' => false, 'code' => 'full') : $s; });
$v = efb_date_validate_value($f, '2026-11-24', $ctx);
t('efb_date_availability full', $v['errors'][0]->get_error_code(), 'full');
t('full message', $v['errors'][0]->get_error_message(), 'This date is fully booked.');
$v = efb_date_validate_value($f, '2026-11-25', $ctx);
t('efb_date_availability no opinion', $v['ok'], true);
$m = C::message(array('code' => 'range_too_short', 'limit' => 2), C::normalize_field(array('date_mode' => 'range'), array('locale' => 'en_US', 'plan' => 'pro')));
t('message range nights', $m, 'Please choose at least 2 nights.');
$m = C::message(array('code' => 'invalid_format'), C::normalize_field(array('cal_default' => 'persian'), array('locale' => 'fa_IR', 'plan' => 'pro')));
t('message format hint', $m, "Please enter the date as \u{2068}۱۴۰۳/۱۲/۳۰\u{2069}.");
t('every code has a message', array_values(array_diff(C::CODES, array_keys(C::MESSAGES))), array());

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
