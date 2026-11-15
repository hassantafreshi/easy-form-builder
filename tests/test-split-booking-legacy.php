<?php
/**
 * SPLIT §9 (subtask F): a booking form keeps working without the Booking add-on (AdnBEF).
 *  - core's frozen rule: a choice whose end date (dateExp) has passed is refused with bkXpM_ (the choice named); a
 *    later date or no date is open; the add-on may decide instead (efb_choice_option_state); every submit branch that
 *    checked dateExp goes through it; the capacity of a choice stays core's atomic Stock reservation;
 *  - the dead code is gone (case "reservation"; the unreachable "Booking form" template is the add-on's);
 *  - the registry: setting:advdate_range (core) and setting:advdate_availability (AdnBEF), the old name an alias;
 *  - AdnBEF is no longer a key without an add-on (SPLIT §20 item 6): it has its folder, routes and contract.
 *
 * Run: php tests/test-split-booking-legacy.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
$GLOBALS['efb_f'] = array();
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_f'][$h])) foreach ($GLOBALS['efb_f'][$h] as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_f'][$h][] = $cb; return true; }
function wp_date($f) { return gmdate($f); }

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function choice_option_state_efb\(.*?\n\t}\n/s', $src, $m);
eval('class SplitBookingFixture { public $id = 5; public $lanText = array("bkXpM_" => "The %s option is no longer available."); ' . $m[0] . ' }');
$fx = new SplitBookingFixture();
$yesterday = gmdate('Y-m-d', time() - 86400 * 2);
$tomorrow = gmdate('Y-m-d', time() + 86400 * 2);
$st = $fx->choice_option_state_efb(array('id_' => 'o1', 'value' => 'Morning', 'dateExp' => $yesterday), array());
t('without the add-on: an expired choice is refused with bkXpM_ naming it', $st, array('open' => false, 'code' => 'efb_booking_expired', 'm' => 'The Morning option is no longer available.'));
t('a later end date is open', $fx->choice_option_state_efb(array('value' => 'Evening', 'dateExp' => $tomorrow), array())['open'], true);
t('no end date (every other form) is open, nothing else read', $fx->choice_option_state_efb(array('value' => 'x'), array()), array('open' => true, 'code' => '', 'm' => ''));
t('a malformed date never closes a choice', $fx->choice_option_state_efb(array('value' => 'x', 'dateExp' => 'soon'), array())['open'], true);
add_filter('efb_choice_option_state', function ($st, $opt) { return isset($opt['value']) && $opt['value'] === 'Full' ? array('open' => false, 'code' => 'efb_booking_full', 'm' => 'Full.') : $st; });
t('the Booking add-on may decide (efb_choice_option_state)', $fx->choice_option_state_efb(array('value' => 'Full'), array()), array('open' => false, 'code' => 'efb_booking_full', 'm' => 'Full.'));
t('every submit branch that read dateExp goes through choice_option_state_efb() (3), none reads it directly', array(substr_count($src, '$this->choice_option_state_efb('), preg_match_all("/strtotime\\(\\\$(fr|f)\\['dateExp'\\]\\)/", $src)), array(3, 0));
t('the capacity of a choice is still core\'s atomic Stock reservation', strpos(file_get_contents(__DIR__ . '/../includes/class-Emsfb-stock.php'), '$f[\'inv_mode\'] === \'booking\'') !== false, true);

$admin = file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
t('dead code gone: case "reservation" (server and builder); the template only through the add-on', array(strpos($src, 'case "reservation"'), strpos($admin, 'id == "reservation"'), strpos($admin, '"formName":\'booking\''), strpos($admin, "typeof efbBookingTemplate === 'function'") !== false), array(false, false, false, true));
$val = file_get_contents(__DIR__ . '/../includes/admin/assets/js/val-efb.js');
t('builder: the end date and places are read-only without the add-on, with its Install notice', strpos($val, "window.efbBookingEditable === true") !== false && strpos($val, "efb_addon_needed_html_efb('AdnBEF')") !== false, true);

$cal = file_get_contents(__DIR__ . '/../includes/class-Emsfb-calendar-rest.php');
t('registry: advdate_range (core) and advdate_availability; advdate_booking an alias until API v2', array(strpos($cal, "'setting:advdate_range'") !== false, strpos($cal, "'setting:advdate_availability' => array('pro', array('availability', 'bk_*')") !== false, strpos($cal, "'alias_of' => array('setting:advdate_range', 'setting:advdate_availability')") !== false), array(true, true, true));
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
t('capabilities.split: advdate_availability and the booking form are AdnBEF\'s', array(\Emsfb\Addon_Catalog::MOVED['setting:advdate_availability'], \Emsfb\Addon_Catalog::MOVED['setting:booking_form']), array('AdnBEF', 'AdnBEF'));

$dir = getenv('EFB_BOOKING_DIR') ?: __DIR__ . '/../vendor/booking';
t('AdnBEF has an add-on now (SPLIT §20 item 6): routes-efb.php, Pro, contract booking', array(is_file($dir . '/routes-efb.php'), \Emsfb\Addon_Catalog::package('AdnBEF'), \Emsfb\Addon_Catalog::get('AdnBEF')['contract']), array(true, 1, 'booking'));
$js = file_get_contents($dir . '/assets/js/booking-builder-efb.js');
t('the add-on carries the template and unlocks the editing', strpos($js, 'root.efbBookingEditable = true') !== false && strpos($js, "\"formName\":'booking'") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
