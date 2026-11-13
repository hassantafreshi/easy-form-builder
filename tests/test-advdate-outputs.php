<?php
/**
 * F06-H: an advanced date in the outputs and in Conditional Logic, without WordPress.
 *
 *  1. Calendar::display_text() per channel and admin setting: email / viewer / csv / pdf in the site
 *     calendar with the ISO date in brackets (fa_IR: "۲۸ اسفند ۱۴۰۴ (2026-03-19)", ar_SA in Umm al-Qura with
 *     Arabic-Indic digits, en_US in Gregorian), "visitor" = what the visitor saw (disp), "iso"; sms / telegram
 *     = disp; sheet / webhook = the stored value; a range, a list, a time, a legacy_local row (its iso key),
 *     an old row without the keys; the filter efb_date_display_value with the channel.
 *  2. The real advdate branch of email_get_content_efb() (extracted from class-Emsfb-public.php): escaped
 *     text, the ISO part isolated left to right, a hostile disp never printed raw.
 *  3. Calendar::logic_compare(): date_before / after / between on the start of a range or list and with
 *     "today+N", date_weekday_is, date_age_gte / lte (29 February), date_within_next,
 *     date_range_nights_gte / lte; a value that is not a date never matches.
 *  4. The fallback evaluator of class-Emsfb-public.php (efb_evaluate_conditional_condition, extracted) answers
 *     the same, and efb_conditional_date_ts() reads a range by its first date; the save allow-list
 *     (functions.php) and Builder_Api::LOGIC_COMPARES know the new operators; the logic add-on (vendor/logic)
 *     knows them in its validator, its page runtime and its builder.
 *
 * Run: php tests/test-advdate-outputs.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_filters'] = array();
function apply_filters($tag, $value) { $a = func_get_args(); array_shift($a); if (!empty($GLOBALS['efb_filters'][$tag])) foreach ($GLOBALS['efb_filters'][$tag] as $cb) $a[0] = call_user_func_array($cb, $a); return $a[0]; }
function add_filter($tag, $cb) { $GLOBALS['efb_filters'][$tag][] = $cb; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function get_locale() { return $GLOBALS['efb_locale']; }
$GLOBALS['efb_locale'] = 'fa_IR';
require_once __DIR__ . '/../includes/class-Emsfb-calendar.php';
use Emsfb\Calendar as C;
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$name}\n"; return; }
	$failed++; echo "[FAIL] {$name}\n   expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n   actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$row = array('id_' => 'd1', 'type' => 'advdate', 'name' => 'Date', 'value' => '2026-03-19', 'cal' => 'gregory', 'local' => '2026-03-19', 'disp' => 'March 19, 2026', 'tz' => 'Asia/Tehran', 'mode' => 'single', 'v' => 1);

/* 1 */
t('1 fa_IR site: Persian text with the ISO date', C::display_text($row, 'email', 'site', 'fa_IR'), '۲۸ اسفند ۱۴۰۴ (2026-03-19)');
t('1 ar_SA site: Umm al-Qura, Arabic-Indic digits', C::display_text(array('value' => '2025-03-30', 'mode' => 'single'), 'viewer', 'site', 'ar_SA'), '١ شوال ١٤٤٦ (2025-03-30)');
t('1 en_US site: Gregorian', C::display_text($row, 'csv', 'site', 'en_US'), 'March 19, 2026 (2026-03-19)');
t('1 visitor: what the visitor saw', C::display_text($row, 'email', 'visitor', 'fa_IR'), 'March 19, 2026');
t('1 iso', C::display_text($row, 'pdf', 'iso', 'fa_IR'), '2026-03-19');
t('1 sms / telegram: disp', array(C::display_text($row, 'sms'), C::display_text($row, 'telegram')), array('March 19, 2026', 'March 19, 2026'));
t('1 sheet / webhook: the stored value', array(C::display_text($row, 'sheet'), C::display_text($row, 'webhook')), array('2026-03-19', '2026-03-19'));
t('1 a range in the site calendar', C::display_text(array('value' => '2026-03-19/2026-03-22', 'mode' => 'range'), 'email', 'site', 'fa_IR'), '۲۸ اسفند ۱۴۰۴ – ۲ فروردین ۱۴۰۵ (2026-03-19/2026-03-22)');
t('1 a list', C::display_text(array('value' => '2026-03-19,2026-03-22', 'mode' => 'multiple'), 'email', 'site', 'en_US'), 'March 19, 2026, March 22, 2026 (2026-03-19,2026-03-22)');
t('1 a time (12 h on en_US)', C::display_text(array('value' => '2026-03-19T14:30', 'mode' => 'single', 'time' => '14:30'), 'email', 'site', 'en_US'), 'March 19, 2026 02:30 PM (2026-03-19T14:30)');
t('1 legacy_local: its ISO key', C::display_text(array('value' => '1404-12-28', 'iso' => '2026-03-19', 'mode' => 'single', 'disp' => '۲۸ اسفند ۱۴۰۴'), 'email', 'site', 'fa_IR'), '۲۸ اسفند ۱۴۰۴ (2026-03-19)');
t('1 an empty value', C::display_text(array('value' => ''), 'email', 'site', 'fa_IR'), '');
t('1 visitor mode without disp falls back to the site text', C::display_text(array('value' => '2026-03-19'), 'email', 'visitor', 'en_US'), 'March 19, 2026 (2026-03-19)');
add_filter('efb_date_display_value', function ($text, $r, $field, $channel) { return $channel === 'csv' ? '[' . $text . ']' : $text; });
t('1 efb_date_display_value with the channel', array(C::display_text($row, 'csv', 'iso'), C::display_text($row, 'email', 'iso')), array('[2026-03-19]', '2026-03-19'));
$GLOBALS['efb_filters'] = array();

/* 2 */
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('2 the advdate branch is in email_get_content_efb', preg_match("/\t\t\t\tif \(isset\(\\\$c\['type'\]\) && \\\$c\['type'\]==='advdate'.*?\n\t\t\t\t\}\n/s", $src, $m) === 1, true);
$branch = isset($m[0]) ? $m[0] : '';
$run = function ($c) use ($branch) {
	$out = array();
	$title = $c['name'];
	$addPair = function ($t, $v) use (&$out) { $out[] = array($t, $v); };
	$code = str_replace(array('\\Emsfb\\Calendar', 'continue;'), array('Emsfb\\Calendar', 'return;'), $branch);
	$f = eval('return function () use (&$addPair, $c, $title) { ' . $code . ' };');
	$f();
	return $out;
};
$GLOBALS['efb_locale'] = 'fa_IR';
$o = $run($row);
t('2 email: the site text, the ISO part left to right', $o, array(array('Date', '<b style="unicode-bidi:plaintext">۲۸ اسفند ۱۴۰۴ <span dir="ltr" style="unicode-bidi:isolate">(2026-03-19)</span></b>')));
$GLOBALS['efb_mode_override'] = true;
add_filter('efb_date_display_value', function ($text, $r) { return isset($r['disp']) ? $r['disp'] : $text; });
$o = $run(array_merge($row, array('disp' => '<img src=x onerror=alert(1)>')));
t('2 a hostile disp is escaped', strpos($o[0][1], '<img') === false && strpos($o[0][1], '&lt;img') !== false, true);
$GLOBALS['efb_filters'] = array();

/* 3 */
$today = '2026-11-13';
$L = function ($op, $v, $e) use ($today) { return C::logic_compare($op, $v, $e, $today); };
t('3 date_before on the start of a range', $L('date_before', '2026-12-01/2026-12-05', '2026-12-03'), true);
t('3 date_after on the first of a list', $L('date_after', '2026-12-01,2026-12-30', '2026-12-10'), false);
t('3 date_after "today+7"', array($L('date_after', '2026-11-21', 'today+7'), $L('date_after', '2026-11-20', 'today+7')), array(true, false));
t('3 date_between with a time value', $L('date_between', '2026-12-02T10:30', '2026-12-01,2026-12-03'), true);
t('3 date_weekday_is (Friday = 5)', array($L('date_weekday_is', '2026-11-20', '5,6'), $L('date_weekday_is', '2026-11-19', '5,6')), array(true, false));
t('3 date_age_gte 18 on the birthday and a day before', array($L('date_age_gte', '2008-11-13', '18'), $L('date_age_gte', '2008-11-14', '18')), array(true, false));
t('3 date_age_lte 17 (29 February birthday on 28 February)', C::logic_compare('date_age_lte', '2008-02-29', '17', '2026-02-28'), true);
t('3 date_within_next 7', array($L('date_within_next', '2026-11-20', '7'), $L('date_within_next', '2026-11-21', '7'), $L('date_within_next', '2026-11-12', '7')), array(true, false, false));
t('3 date_range_nights_gte / lte', array($L('date_range_nights_gte', '2026-12-01/2026-12-04', '3'), $L('date_range_nights_lte', '2026-12-01/2026-12-04', '2'), $L('date_range_nights_gte', '2026-12-01', '1')), array(true, false, false));
t('3 not a date never matches', array($L('date_before', 'tomorrow', '2026-12-01'), $L('date_weekday_is', '', '5')), array(false, false));
t('3 not a date operator: null', C::logic_compare('is', '2026-11-20', '2026-11-20', $today), null);

/* 4 */
preg_match('/\tprivate function efb_evaluate_conditional_condition\(.*?\n\t\}\n/s', $src, $ev);
preg_match('/\tprivate function efb_conditional_date_ts\(.*?\n\t\}\n/s', $src, $ts);
eval('class LogicFixture { public $efb_conditional_max_step = 1; private function efb_conditional_environment() { return array("query" => array(), "user" => array("roles" => array(), "logged_in" => false)); } private function efb_conditional_option_spellings($f, $e) { return null; } private function efb_conditional_payment_state($f, $s) { return false; }'
	. str_replace("\tprivate function efb_evaluate_conditional_condition", "\tpublic function ev", $ev[0]) . $ts[0] . ' public function ts($v) { return $this->efb_conditional_date_ts($v); } }');
$fx = new LogicFixture();
$cond = function ($op, $e) { return array('field_id' => 'd1', 'compare' => $op, 'value' => $e); };
$vals = array('d1' => '2026-12-01/2026-12-04');
$todayS = C::today('site');
t('4 fallback evaluator: nights >= 3 on a range', $fx->ev($cond('date_range_nights_gte', '3'), $vals), true);
t('4 fallback evaluator: date_before on the start of the range', $fx->ev($cond('date_before', '2026-12-02'), $vals), true);
t('4 fallback evaluator: weekday of the start (Tuesday = 2)', $fx->ev($cond('date_weekday_is', '2'), $vals), true);
t('4 fallback evaluator: within the next N days uses the site today', $fx->ev($cond('date_within_next', '3'), array('d1' => C::add_days($todayS, 2))), true);
t('4 efb_conditional_date_ts reads a range by its first date', $fx->ts('2026-12-01/2026-12-04'), strtotime('2026-12-01 00:00:00'));
$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
$ba = file_get_contents(__DIR__ . '/../includes/class-Emsfb-builder-api.php');
foreach (array('date_weekday_is', 'date_age_gte', 'date_age_lte', 'date_within_next', 'date_range_nights_gte', 'date_range_nights_lte') as $op) {
	t('4 the save keeps ' . $op . ' (allow-list) and Builder_Api::LOGIC_COMPARES knows it', array(strpos($fn, "'" . $op . "'") !== false, strpos($ba, "'" . $op . "'") !== false), array(true, true));
}
$addon = dirname(__DIR__) . '/vendor/logic';
if (is_dir($addon)) {
	$v = @file_get_contents($addon . '/logic/class-Emsfb-logic-validator.php') ?: @file_get_contents($addon . '/class-Emsfb-logic-validator.php');
	$pj = @file_get_contents($addon . '/logic/assets/public/js/conditional-logic-efb.js');
	$aj = @file_get_contents($addon . '/logic/assets/admin/js/conditional-logic-efb.js');
	t('4 add-on validator delegates the date operators to Calendar::logic_compare', strpos((string) $v, 'Calendar::logic_compare') !== false && strpos((string) $v, "'date_range_nights_lte'") !== false, true);
	t('4 add-on page runtime delegates to EFBCalendar.rules.logicCompare', strpos((string) $pj, 'EFBCalendar.rules.logicCompare') !== false, true);
	t('4 add-on builder offers the advdate operators', strpos((string) $aj, "advdate: ['date_before'") !== false && strpos((string) $aj, "advdate: 'advdate'") !== false, true);
} else {
	echo "[SKIP] 4 vendor/logic is not in this checkout\n";
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
