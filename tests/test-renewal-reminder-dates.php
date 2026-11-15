<?php
/**
 * F21 B: dates, stages and the clock.
 *
 *  - the window: exactly 7 days → t7, one second more → nothing, the last 24 hours → t1 ("today" / "tomorrow" in the
 *    site's time zone), the end with the server still saying active → nothing (its grace), deactive → t0;
 *  - the shown date is the last day Pro works (expires_at − 1 s) in the site's time zone: UTC, Asia/Tehran,
 *    America/New_York across the March and November DST changes, Pacific/Auckland, Asia/Riyadh; days_left never
 *    depends on the time zone;
 *  - the server's clock, not the site's: a site clock 3 days fast or slow gives the same days_left;
 *  - silence: a stale answer (8 days), auto_renew, lifetime, Free / Free Plus, fa_IR, another code;
 *  - Arabic digits in the date for an Arabic administrator; needs_daily_check().
 *
 * Run: php tests/test-renewal-reminder-dates.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$at = function ($ts) { $GLOBALS['efb_rt']['now'] = $ts; };
$S = function () { return RR::state(); };

echo "--- the window, to the second ---\n";
$cases = array(
	array('8 days left: nothing yet', 8 * 86400, null, 'later', 8),
	array('7 days and 1 second: nothing yet', 7 * 86400 + 1, null, 'later', 8),
	array('exactly 7 days: t7, 7 days', 7 * 86400, 't7', '', 7),
	array('7 days less 1 second: t7, 7 days', 7 * 86400 - 1, 't7', '', 7),
	array('6 days: t7, 6 days', 6 * 86400, 't7', '', 6),
	array('1 day and 1 second: t7, 2 days', 86400 + 1, 't7', '', 2),
	array('exactly 1 day: t1, 1 day', 86400, 't1', '', 1),
	array('1 second: t1, 1 day', 1, 't1', '', 1),
);
foreach ($cases as $c) {
	efb_rt_reset(); $at($T0);
	efb_rt_pro($c[1] / 86400);
	$s = $S();
	t($c[0], array($s['stage'], $s['reason'], $s['days_left']), array($c[2], $c[3], $c[4]));
}
efb_rt_reset(); $at($T0);
efb_rt_pro(1);
$at($T0 + 86400);
t('the end reached, the server not asked yet (Pro still on): nothing (the server decides the grace)', array($S()['stage'], $S()['reason']), array(null, 'awaiting_server'));
$at($T0 + 86400 + 3600);
t('… an hour later: still nothing', $S()['stage'], null);
efb_rt_reset(); $at($T0);
efb_rt_pro(-1, array(), 'deactive');
t('the server said deactive (Pro off): t0', array($S()['stage'], $S()['days_left']), array('t0', 0));
update_option('emsfb_pro', 1);
t('deactive stored but Pro on again (e.g. an outage grace): nothing', $S()['stage'], null);

echo "--- the date shown = the last day Pro works, in the site's time zone ---\n";
$exp = gmmktime(0, 0, 0, 11, 21, 2026); // 2026-11-21T00:00:00Z
$zones = array('UTC' => 'November 20, 2026', 'Asia/Tehran' => 'November 21, 2026', 'America/New_York' => 'November 20, 2026', 'Pacific/Auckland' => 'November 21, 2026', 'Asia/Riyadh' => 'November 21, 2026');
$days = array();
foreach ($zones as $tz => $want) {
	efb_rt_reset(); $at($exp - 5 * 86400 - 3600);
	update_option('timezone_string', $tz);
	update_option('date_format', 'F j, Y');
	efb_rt_pro(($exp - $GLOBALS['efb_rt']['now']) / 86400);
	$s = $S();
	t("{$tz}: {$want}", $s['date'], $want);
	$days[] = $s['days_left'];
}
t('days_left is the same in every time zone (6)', array_unique($days), array(6));
// America/New_York DST: 2027-03-14 02:00 EST → EDT; 2026-11-01 02:00 EDT → EST.
efb_rt_reset(); update_option('timezone_string', 'America/New_York'); update_option('date_format', 'F j, Y');
$e1 = gmmktime(7, 0, 0, 3, 14, 2027); // 03:00 EDT, 14 March
$at($e1 - 3 * 86400); efb_rt_pro(3);
t('New York, the March DST change: the last day is 14 March (01:59:59 EST)', array($S()['date'], $S()['days_left']), array('March 14, 2027', 3));
efb_rt_reset(); update_option('timezone_string', 'America/New_York'); update_option('date_format', 'F j, Y');
$e2 = gmmktime(5, 0, 0, 11, 1, 2026); // 01:00 EDT, 1 November (before the change at 06:00Z)
$at($e2 - 2 * 86400); efb_rt_pro(2);
t('New York, the November DST change: the last day is 1 November (00:59:59 EDT)', array($S()['date'], $S()['days_left']), array('November 1, 2026', 2));
efb_rt_reset(); update_option('timezone_string', 'Asia/Tehran'); update_option('date_format', 'j F Y');
$at($exp - 86400 * 3); efb_rt_pro(3);
t('the site\'s date format is used (j F Y)', $S()['date'], '21 November 2026');

echo "--- the last 24 hours: today or tomorrow, in the site's time zone ---\n";
efb_rt_reset(); update_option('timezone_string', 'UTC');
$at($exp - 20 * 3600); efb_rt_pro(20 / 24);
t('UTC, 20 h left on 20 Nov (the last day is today): t1 "today"', array($S()['stage'], $S()['when']), array('t1', 'today'));
efb_rt_reset(); update_option('timezone_string', 'Asia/Tehran');
$at($exp - 20 * 3600); efb_rt_pro(20 / 24);
t('Tehran, the same moment (07:30 on 20 Nov, the last day is 21 Nov): t1 "tomorrow"', array($S()['stage'], $S()['when']), array('t1', 'tomorrow'));

echo "--- the server's clock, not the site's ---\n";
foreach (array(3, -3) as $d) {
	efb_rt_reset(); update_option('timezone_string', 'UTC'); $at($T0);
	// the site clock is $d days wrong: the server's time is the site's − $d days
	efb_rt_pro(7, array(), 'active', -$d * 86400);
	$s = $S();
	t("site clock {$d} days " . ($d > 0 ? 'fast' : 'slow') . ': still t7 with 7 days', array($s['stage'], $s['days_left']), array('t7', 7));
	$at($T0 + 2 * 86400);
	t("… two days later: 5 days", $S()['days_left'], 5);
}

echo "--- silence ---\n";
efb_rt_reset(); $at($T0); efb_rt_pro(10);
$at($T0 + 8 * 86400 - 1);
t('the answer is 8 days less 1 second old: still shown (t7, 3 days)', array($S()['stage'], $S()['days_left']), array('t7', 3));
efb_rt_reset(); $at($T0); efb_rt_pro(30);
$at($T0 + 8 * 86400);
t('an answer 8 days old: nothing (stale)', array($S()['stage'], $S()['reason']), array(null, 'stale'));
$at($T0 - 2 * 86400);
t('an answer from the future (clock set back 2 days): nothing', array($S()['stage'], $S()['reason']), array(null, 'stale'));
efb_rt_reset(); $at($T0); efb_rt_pro(5, array('auto_renew' => true));
t('auto_renew: nothing', array($S()['stage'], $S()['reason']), array(null, 'auto_renew'));
efb_rt_reset(); efb_rt_pro(5, array('expires_at' => null));
t('lifetime: nothing', array($S()['stage'], $S()['reason']), array(null, 'lifetime'));
efb_rt_reset(); efb_rt_pro(5); update_option('emsfb_pro', 3);
t('Free Plus (3): nothing', array($S()['stage'], $S()['reason']), array(null, 'plan'));
update_option('emsfb_pro', 2);
t('Free (2): nothing', $S()['stage'], null);
efb_rt_reset(); efb_rt_pro(5); update_option('emsfb_pro_activeCode', md5('site.example') . '@OTHERPLACEHOLDER0');
t('another code than the one the date belongs to: nothing', array($S()['stage'], $S()['reason']), array(null, 'other_code'));
efb_rt_reset(); efb_rt_pro(5); delete_option('emsfb_pro_activeCode');
t('no code: nothing', $S()['stage'], null);
efb_rt_reset(); efb_rt_pro(5); $GLOBALS['efb_rt']['locale'] = 'fa_IR';
t('fa_IR: nothing (owner decision: one-time purchases)', array($S()['stage'], $S()['reason']), array(null, 'fa_IR'));
$GLOBALS['efb_rt']['locale'] = 'en_US';
efb_rt_reset();
t('no field stored (older server): nothing', array($S()['stage'], $S()['reason']), array(null, 'unknown'));

echo "--- digits ---\n";
efb_rt_reset(); update_option('timezone_string', 'UTC'); update_option('date_format', 'F j, Y');
$at($exp - 3 * 86400); efb_rt_pro(3);
$GLOBALS['efb_rt']['user_locale'] = 'ar';
t('an Arabic administrator: Arabic-Indic digits in the date', $S()['date'], 'November ٢٠, ٢٠٢٦');
t('… the e-mail (site locale en_US) keeps Latin digits', RR::state('en_US')['date'], 'November 20, 2026');
$GLOBALS['efb_rt']['user_locale'] = 'he_IL';
t('Hebrew: Latin digits', $S()['date'], 'November 20, 2026');
$GLOBALS['efb_rt']['user_locale'] = null;

echo "--- the daily check near the end ---\n";
foreach (array(array(9, true, false), array(8, true, true), array(1, true, true), array(-2, true, true), array(5, false, false)) as $c) {
	efb_rt_reset(); $at($T0); efb_rt_pro($c[0], $c[1] ? array() : array('auto_renew' => true));
	t("{$c[0]} days left" . ($c[1] ? '' : ', auto_renew') . ': daily check ' . ($c[2] ? 'yes' : 'no'), RR::needs_daily_check(), $c[2]);
}
efb_rt_reset();
t('nothing stored: weekly as before', RR::needs_daily_check(), false);

efb_rt_done();
