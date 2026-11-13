<?php
/**
 * F02-S5 / §7.5 / §8: the anti-fraud caps with their default numbers, simulated with 100 sends.
 *  - mail-bombing: one victim's address gets at most 3 codes an hour and 10 a day, whatever IP and
 *    session ask (the per-destination cap is the control that matters);
 *  - SMS pumping: one visitor rotating numbers stops at 3 numbers per session, 5 sends per IP an hour;
 *    numbers of one prefix stop at 10 an hour; one form at 60 SMS an hour;
 *  - the site's daily budget: an alert at 80 % (once in 6 hours), 503 at 100 % (fail-closed);
 *  - a low conversion rate (≥ 20 sent, < 20 % entered) e-mails the owner.
 *
 * Run: php tests/test-verify-antifraud.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

/** n sends; each from its own IP and session unless fixed; the clock moves past every cooldown. */
function vt_burst($fid, $field, $n, $dest_fn, $sid_fn, $ip_fn, $step = 0) {
	$ok = 0; $codes = array();
	for ($i = 0; $i < $n; $i++) {
		$GLOBALS['vt_ip'] = $ip_fn($i);
		list($st, $d) = vt_send($fid, $field, $dest_fn($i), $sid_fn($i));
		if ($st === 200) $ok++;
		$codes[] = $st === 200 ? 200 : $d['code'];
		V::$now += $step;
	}
	return array($ok, $codes);
}

/* ---------------------------------------------------------------- mail-bombing */
vt_env();
$fid = vt_form(array(vt_email('eml1', 'otp')));
V::save_settings(array('email' => array('resend_cooldowns' => array(30))));
list($ok, $codes) = vt_burst($fid, 'eml1', 100, function () { return 'victim@example.com'; }, function ($i) { return 'sid-' . $i; }, function ($i) { return '198.51.100.' . ($i % 250); }, 31);
vt('one address, 100 requests from 100 IPs and sessions within an hour: 3 codes', $ok, 3);
vt('… the rest are refused with efb_verify_limit (one general sentence)', count(array_filter($codes, function ($c) { return $c === 'efb_verify_limit'; })), 97);
vt('… and only 3 e-mails left the site', count($GLOBALS['vt_mails']), 3);
$day_ok = 0;
for ($h = 0; $h < 24; $h++) {
	V::$now = 1800000000 - (1800000000 % 86400) + 86400 + 3600 * $h + 5; // the 24 hours of one day window
	list($o) = vt_burst($fid, 'eml1', 5, function () { return 'victim@example.com'; }, function ($i) use ($h) { return 'sid-d' . $h . $i; }, function ($i) use ($h) { return '192.0.2.' . (($h * 5 + $i) % 250); }, 31);
	$day_ok += $o;
}
vt('the same address over a whole day, 120 requests: exactly 10 codes (the daily cap)', $day_ok, 10);

/* ---------------------------------------------------------------- e-mail per IP */
vt_env();
$fid = vt_form(array(vt_email('eml1', 'otp')));
V::save_settings(array('limits' => array('sid_destinations' => 100, 'sid_sends' => 100)));
list($ok) = vt_burst($fid, 'eml1', 30, function ($i) { return "u{$i}@example.com"; }, function ($i) { return 's' . $i; }, function () { return '203.0.113.50'; });
vt('one IP, 30 different addresses in an hour: 10 e-mails (ip_hour_email)', $ok, 10);

/* ---------------------------------------------------------------- SMS pumping */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
list($ok, $codes) = vt_burst($fid, 'mob1', 20, function ($i) { return '+98912' . str_pad((string) (1000000 + $i * 37), 7, '0', STR_PAD_LEFT); }, function () { return 'same-sid'; }, function ($i) { return '203.0.113.' . (10 + $i); });
vt('one session rotating numbers: 3 numbers (sid_destinations)', $ok, 3);
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
list($ok) = vt_burst($fid, 'mob1', 20, function ($i) { return '+98912' . str_pad((string) (2000000 + $i * 41), 7, '0', STR_PAD_LEFT); }, function ($i) { return 'sid-' . $i; }, function () { return '203.0.113.99'; });
vt('one IP, new session each time, new number each time: 5 an hour (ip_hour_sms)', $ok, 5);
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
list($ok) = vt_burst($fid, 'mob1', 30, function ($i) { return '+98912' . str_pad((string) (3000000 + $i * 53), 7, '0', STR_PAD_LEFT); }, function ($i) { return 'sid-' . $i; }, function ($i) { return '100.64.' . intdiv($i, 250) . '.' . ($i % 250); });
vt('many IPs and sessions, numbers of one prefix (+98912…): 10 an hour (prefix_hour_sms)', $ok, 10);
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
V::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => array('ir')), 'limits' => array('prefix_hour_sms' => 1000)));
list($ok) = vt_burst($fid, 'mob1', 100, function ($i) { return '+989' . str_pad((string) (100000000 + $i * 7919), 9, '0', STR_PAD_LEFT); }, function ($i) { return 'sid-' . $i; }, function ($i) { return '100.65.' . intdiv($i, 250) . '.' . ($i % 250); });
vt('100 visitors on one form in an hour: 60 SMS (form_hour_sms)', $ok, 60);

/* ---------------------------------------------------------------- budget */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
V::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => array('ir'), 'daily_budget' => 10), 'limits' => array('prefix_hour_sms' => 1000, 'form_hour_sms' => 1000)));
$GLOBALS['efb_mails'] = array();
list($ok, $codes) = vt_burst($fid, 'mob1', 14, function ($i) { return '+989' . str_pad((string) (200000000 + $i * 7919), 9, '0', STR_PAD_LEFT); }, function ($i) { return 'sid-' . $i; }, function ($i) { return '100.66.0.' . $i; });
vt('budget 10: 10 sent, then 503 efb_verify_unavailable (fail-closed, never a bypass)', array($ok, $codes[10], $codes[13]), array(10, 'efb_verify_unavailable', 'efb_verify_unavailable'));
$alerts = array_values(array_filter($GLOBALS['efb_mails'], function ($m) { return $m['to'] === 'owner@example.test'; }));
vt('the owner got one 80 % alert and one "budget used up" alert (each once in 6 hours)', count($alerts), 2);
vt('the alert names no number and no code', preg_match('/\+98|\d{6}/', implode(' ', array_column($alerts, 'body'))), 0);

/* ---------------------------------------------------------------- conversion alert */
vt_env();
$fid = vt_form(array(vt_mobile('mob1')));
vt_provider(array('ir'));
V::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => array('ir')), 'limits' => array('prefix_hour_sms' => 1000, 'form_hour_sms' => 1000, 'ip_hour_sms' => 1000, 'ip_day_sms' => 1000)));
$GLOBALS['efb_mails'] = array();
vt_burst($fid, 'mob1', 25, function ($i) { return '+989' . str_pad((string) (300000000 + $i * 7919), 9, '0', STR_PAD_LEFT); }, function ($i) { return 'sid-' . $i; }, function ($i) { return '100.67.0.' . $i; });
$conv = V::conversion('sms', 3600);
vt('25 codes sent, none entered', array($conv['sent'], $conv['verified']), array(25, 0));
vt('the owner was told once (possible SMS pumping)', count(array_filter($GLOBALS['efb_mails'], function ($m) { return strpos($m['body'], 'pumping') !== false; })), 1);

vt_done();
