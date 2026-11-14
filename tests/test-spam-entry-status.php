<?php
/**
 * F12-A on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP: the spam path without any check of its
 * own, driven by a third-party verdict (filter efb_spam_verdict through the mu-plugin efb-e2e-f12.php, spec §A):
 *
 *  - a normal answer (control): stored active, e-mails and the form's webhook go out, the visit is "send";
 *  - verdict "spam": the sender reads the usual success with a confirmation code; the entry is stored with F14's
 *    spam status (entry_status 1, no second column), its history says why (action spam, meta "filter"); no e-mail,
 *    no webhook (pre_wp_mail / pre_http_request recorded nothing); not in the default list (tab all), in the spam
 *    tab with its reasons; the visit is "spam"; today's statistics count it as spam; the F11 total cap is not taken;
 *  - "Not spam" (F14's list operation) brings it back to the list; e-mails are not sent again;
 *  - verdict "reject": nothing stored, errorMRobot, code efb_spam_rejected;
 *  - the daily purge deletes spam older than spam_retention_days only (0 = kept: a site without the setting);
 *  - DB stays 1.10 (no schema change for F12) and a second upgrade is a no-op.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-spam-entry-status.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$H->ctl('plan', '3');
$H->ctl('f12-mu');
$H->ctl('f12-setting', 'spam_retention_days=del&spam_honeypot=del&spam_min_time=del&spam_action=del');
foreach (array('efb_e2e_f12_http_log' => '1', 'efb_e2e_capture_mail' => '1') as $k => $v) $H->ctl('set-option', $k, $v);
$H->ctl('set-option', 'efb_e2e_f12_verdict', 'del');
$H->ctl('set-option', 'efb_e2e_f12_http', 'del');
$H->ctl('mails', 'clear');

$F = $H->ctl('f03-form', 'F12 status', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'))), 'sendEmail=1&lim_total=3');
$fid = (int) $F['form_id'];
$H->ctl('f14-form-key', (string) $fid, 'webhook_rules', json_encode(array(array('id' => 'w1', 'name' => 'F12', 'enabled' => true, 'priority' => 10, 'action' => 'trigger', 'webhook_id' => 'f12', 'url' => 'https://hooks.example.test/f12', 'method' => 'POST',
	'conditions' => array('items' => array(array('field_id' => $F['ids']['nm'], 'compare' => 'is_not_empty')))))));
$rows = function ($name) use ($F) { return array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name), array('id_' => $F['ids']['em'], 'name' => 'E-mail', 'type' => 'email', 'value' => 'visitor@example.test')); };
$entries = function () use ($H, $fid) { $e = $H->ctl('f14-entries', (string) $fid); return is_array($e) ? $e : array(); };
$http_calls = function () use ($H) { $h = $H->ctl('option', 'efb_e2e_f12_http'); return is_array($h) ? count($h) : 0; };
$mails = function () use ($H) { $m = $H->ctl('mails'); return is_array($m) ? count($m) : 0; };

/* control: a normal answer */
$H->fresh_jar();
$r = $H->submit($fid, $rows('Ham Person'));
$a = EfbF11Http::answer($r);
sleep(1);
$e = $entries();
t('control: a normal answer is stored active', array(!empty($a['success']), count($e), isset($e[0]['entry_status']) ? (int) $e[0]['entry_status'] : -1), array(true, 1, 0));
t('control: its e-mail and its webhook went out', array($mails() > 0, $http_calls() > 0), array(true, true));
$H->ctl('mails', 'clear');
$H->ctl('set-option', 'efb_e2e_f12_http', 'del');

/* verdict spam */
$H->ctl('set-option', 'efb_e2e_f12_verdict', 'spam');
$H->fresh_jar();
$r = $H->submit($fid, $rows('Spam Person'));
$a = EfbF11Http::answer($r);
sleep(1);
$e = $entries();
usort($e, function ($x, $y) { return (int) $y['msg_id'] - (int) $x['msg_id']; });
$spam = $e[0];
t('spam: the sender reads the usual success with a confirmation code', array(!empty($a['success']), !empty($a['track']), isset($a['code'])), array(true, true, false));
t('spam: stored with F14\'s spam status (entry_status 1)', array(count($e), (int) $spam['entry_status']), array(2, 1));
$log = $H->ctl('f14-log', (string) $spam['msg_id']);
$log = is_array($log) ? $log : array();
$why = array_values(array_filter($log, function ($l) { return $l['action'] === 'spam'; }));
t('spam: the history says why (action spam, meta "filter")', array(count($why), $why ? $why[0]['meta'] : ''), array(1, 'filter'));
t('spam: no e-mail (pre_wp_mail) and no webhook (pre_http_request)', array($mails(), $http_calls()), array(0, 0));
$sess = $H->ctl('sessions', (string) $fid);
$st = is_array($sess) ? array_values(array_map(function ($s) { return $s['status']; }, $sess)) : array();
t('spam: its visit is "spam", the normal one "send"', array(in_array('spam', $st, true), in_array('send', $st, true)), array(true, true));
$stats = $H->ctl('f14-stats', (string) $fid);
$today = is_array($stats) && $stats ? end($stats) : array();
t('spam: today\'s statistics count 2 submissions, 1 of them spam', array((int) ($today['submits'] ?? -1), (int) ($today['spam'] ?? -1)), array(2, 1));

/* F11 total cap 3: the spam answer took no place (two more normal answers fit) */
$H->ctl('set-option', 'efb_e2e_f12_verdict', 'del');
$H->fresh_jar(); $r2 = EfbF11Http::answer($H->submit($fid, $rows('Ham Two')));
$H->fresh_jar(); $r3 = EfbF11Http::answer($H->submit($fid, $rows('Ham Three')));
$H->fresh_jar(); $r4 = EfbF11Http::answer($H->submit($fid, $rows('Ham Four')));
t('spam: the F11 total cap (3) counts normal answers only: two more fit, the fourth is refused', array(!empty($r2['success']), !empty($r3['success']), !empty($r4['success'])), array(true, true, false));

/* the F14 list: default tab without it, spam tab with its reasons; "Not spam" */
$H->ctl('mails', 'clear');
$admin_q = function ($tab) use ($H, $fid) {
	$res = $H->ctl('f12-query', (string) $fid, $tab);
	return is_array($res) ? $res : array();
};
$all = $admin_q('all');
$sp = $admin_q('spam');
$ids = function ($rs) { return array_map(function ($r) { return (int) $r['msg_id']; }, isset($rs['rows']) ? $rs['rows'] : array()); };
t('list: the default tab does not show it, the spam tab does', array(in_array((int) $spam['msg_id'], $ids($all), true), in_array((int) $spam['msg_id'], $ids($sp), true)), array(false, true));
$sr = isset($sp['rows'][0]['spam_reasons']) ? $sp['rows'][0]['spam_reasons'] : null;
t('list: the spam row carries its reasons', $sr, array('filter'));
$op = $H->ctl('f12-op', (string) $fid, 'unspam', (string) $spam['msg_id']);
$e = $entries();
$back = array_values(array_filter($e, function ($x) use ($spam) { return (int) $x['msg_id'] === (int) $spam['msg_id']; }));
t('"Not spam" brings it back (entry_status 0), e-mails are not sent again', array($back ? (int) $back[0]['entry_status'] : -1, $mails()), array(0, 0));

/* verdict reject (another form: this one is full now) */
$R = $H->ctl('f03-form', 'F12 reject', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'))), 'sendEmail=1');
$H->ctl('set-option', 'efb_e2e_f12_verdict', 'reject');
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit((int) $R['form_id'], array(array('id_' => $R['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'Rejected'))));
$re = $H->ctl('f14-entries', (string) $R['form_id']);
t('reject: nothing stored, the robot message and code efb_spam_rejected', array(empty($a['success']), $a['code'] ?? '', is_array($re) ? count($re) : -1, ($a['m'] ?? '') !== ''), array(true, 'efb_spam_rejected', 0, true));
$H->ctl('set-option', 'efb_e2e_f12_verdict', 'del');
$H->ctl('f12-delete-form', (string) $R['form_id']);

/* purge */
$H->ctl('f12-op', (string) $fid, 'spam', (string) $spam['msg_id']);
$H->ctl('f12-date', (string) $spam['msg_id'], '40');
$p0 = $H->ctl('f12-purge', (string) $fid);
t('purge: a site without spam_retention_days keeps spam', array((int) ($p0['deleted'] ?? -1), count(array_filter($entries(), function ($x) use ($spam) { return (int) $x['msg_id'] === (int) $spam['msg_id']; }))), array(0, 1));
$H->ctl('f12-setting', 'spam_retention_days=30');
$p1 = $H->ctl('f12-purge', (string) $fid);
t('purge: with 30 days, the 40-day-old spam entry is deleted (and nothing else)', array((int) ($p1['deleted'] ?? -1), count(array_filter($entries(), function ($x) use ($spam) { return (int) $x['msg_id'] === (int) $spam['msg_id']; })), count($entries())), array(1, 0, 3));
$H->ctl('f12-setting', 'spam_retention_days=del');

/* schema */
$sc = $H->ctl('f14-schema');
t('schema: DB 1.10 (F12 adds no column or table)', isset($sc['db_version']) ? (string) $sc['db_version'] : '', '1.10');

/* clean-up */
$H->ctl('f12-delete-form', (string) $fid);
foreach (array('efb_e2e_f12_http_log', 'efb_e2e_capture_mail', 'efb_e2e_f12_http', 'efb_e2e_f12_verdict') as $k) $H->ctl('set-option', $k, 'del');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
