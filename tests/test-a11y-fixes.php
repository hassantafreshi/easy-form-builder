<?php
/**
 * F16: the bugs fixed in passing (docs/feature-gaps/BUGS-FOUND.fa.md, F16 section).
 *
 *  1  the error box is in the flow on a phone and on blur-timed forms (it covered the label, right to left too);
 *  2  a repeater group's help text starts where its legend starts (F09: the field description's mx-4 indent);
 *  3  on a right-to-left page labels sit on the right even when English (plaintext keeps the order, not the side);
 *  4  the F15 versions dialog: the date in the administrator's calendar and digits (fa_IR: Solar Hijri, Persian digits);
 *  5  the F12 spam reasons joined the language's way (Intl.ListFormat), not with an Arabic comma between English words.
 * The browser side of 1, 3 and 5 is in tests/test-a11y-field-errors.js, tests/test-entries-list-render.js and the e2e
 * scenario f16-e; this checks the rules and the server's date.
 *
 * Run: php tests/test-a11y-fixes.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ': ' . (is_string($info) ? $info : json_encode($info, JSON_UNESCAPED_UNICODE)) : '') . "\n"; $failed++;
}
$root = dirname(__DIR__);
$css = file_get_contents($root . '/includes/admin/assets/css/style-efb.css');
t('1 a phone: the box in the flow, stronger than the old negative-margin rules (and the RTL file)', strpos($css, '.body_efb .efb.efb.ttiptext.ttiptext[id$="-message"]') !== false && strpos($css, 'position: static !important') !== false);
t('1 a blur-timed form: the box in the flow at every width', strpos($css, '.efb-vt-blur .efb.efb.ttiptext.ttiptext[id$="-message"] { position: static !important;') !== false);
$rpt = file_get_contents($root . '/public/assets/css/repeater-efb.css');
t('2 the repeater group\'s help text: no inline indent', strpos($rpt, '.efb.efb-rpt > .efb-field-description > small[id$="-des"] { margin-inline: 0 !important;') !== false);
t('3 right to left: labels and messages aligned right (a label left by an English-speaking admin too)', (bool) preg_match('/:is\(\[dir="rtl"\], \.rtl\) \.body_efb :is\(\.efb\.col-form-label\.txt-left, legend\.txt-left, \.efb-pf-label[^{]*\{ text-align: right !important; \}/s', $css));

// 4: Revisions::when_efb() with the real calendar engines.
$GLOBALS['efb_t_locale'] = 'fa_IR';
function get_user_locale() { return $GLOBALS['efb_t_locale']; }
function get_locale() { return $GLOBALS['efb_t_locale']; }
function get_option($k, $d = false) { return array('date_format' => 'F j, Y', 'time_format' => 'g:i a')[$k] ?? $d; }
function apply_filters($h, $v) { return $v; }
function wp_date($f, $ts) { return gmdate($f, $ts); }
function __($s) { return $s; }
function esc_html__($s) { return $s; }
require_once $root . '/includes/class-Emsfb-calendar.php';
require_once $root . '/includes/calendar-functions-efb.php';
require_once $root . '/includes/class-Emsfb-revisions.php';
$m = new ReflectionMethod('\\Emsfb\\Revisions', 'when_efb');
$m->setAccessible(true);
$ts = gmmktime(14, 30, 0, 11, 15, 2026);
$fa = $m->invoke(null, $ts, 'raw');
t('4 fa_IR: "۲۴ آبان ۱۴۰۵ ۱۴:۳۰" (Solar Hijri, Persian digits, the time too)', $fa === '۲۴ آبان ۱۴۰۵ ۱۴:۳۰', $fa);
$GLOBALS['efb_t_locale'] = 'ar_EG';
$ar = $m->invoke(null, $ts, 'raw');
t('4 ar_EG: Arabic digits', (bool) preg_match('/[٠-٩]/u', $ar) && !preg_match('/[0-9]/', $ar), $ar);
$GLOBALS['efb_t_locale'] = 'en_US';
t('4 en_US: the site\'s own formats as before', $m->invoke(null, $ts, 'raw') === 'November 15, 2026 2:30 pm', $m->invoke(null, $ts, 'raw'));
t('4 no time stamp: the stored text', $m->invoke(null, 0, '2026-11-15 14:30:00') === '2026-11-15 14:30:00');

$js = file_get_contents($root . '/includes/admin/assets/js/entries-efb.js');
t('5 entries: reasons and addresses joined with Intl.ListFormat (no fixed "، ")', strpos($js, 'new Intl.ListFormat(') !== false && strpos($js, "? '، ' : ', ';\n    return words.join(comma)") === false);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
