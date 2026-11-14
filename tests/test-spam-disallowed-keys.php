<?php
/**
 * F12-E WordPress' disallowed words (Settings › Discussion › Disallowed Comment Keys) on a real WordPress (the e2e
 * site; tests/f11-http-lib.php), over HTTP:
 *
 *  - spam_disallowed_keys off (a site from 4.3.1): a listed word changes nothing;
 *  - on: a listed word in a textarea → the usual success, stored as spam (reason disallowed_keys), nothing sent;
 *    in the sender's name or e-mail field too; a word that is not listed (or part of a longer word as WordPress
 *    matches it) → stored normally; Persian text is matched as WordPress matches it;
 *  - an empty list never matches; spam_off on the form skips the check;
 *  - with spam_action reject → nothing stored (efb_spam_rejected).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-spam-disallowed-keys.php
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
$old_list = $H->ctl('option', 'disallowed_keys');
$H->ctl('set-option', 'disallowed_keys', "cheap-pills\nکازینو\nspammer@bad.example");
$H->ctl('f12-setting', 'spam_disallowed_keys=del&spam_min_time=0');
$F = $H->ctl('f03-form', 'F12 words', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'), array('key' => 'msg', 'type' => 'textarea', 'name' => 'Message'))));
$fid = (int) $F['form_id'];
$send = function ($msg, $name = 'Jane', $email = 'jane@example.test', $id = null, $ids = null) use ($H, $fid, $F) {
	$ids = $ids ?: $F['ids'];
	$H->fresh_jar();
	return EfbF11Http::answer($H->submit($id ?: $fid, array(array('id_' => $ids['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name), array('id_' => $ids['em'], 'name' => 'E-mail', 'type' => 'email', 'value' => $email), array('id_' => $ids['msg'], 'name' => 'Message', 'type' => 'textarea', 'value' => $msg))));
};
$last = function ($id = null) use ($H, $fid) { $e = $H->ctl('f14-entries', (string) ($id ?: $fid)); if (!is_array($e) || !$e) return null; usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e[0]; };
$why = function ($e) use ($H) { if (!$e) return ''; foreach ((array) $H->ctl('f14-log', (string) $e['msg_id']) as $r) if ($r['action'] === 'spam') return $r['meta']; return ''; };

$a = $send("Buy cheap-pills now");
t('off (a site from 4.3.1): a listed word changes nothing', array(!empty($a['success']), (int) $last()['entry_status']), array(true, 0));
$H->ctl('f12-setting', 'spam_disallowed_keys=1');
$a = $send("Hello,\nbuy cheap-pills now");
$e = $last();
t('on: a listed word in the textarea → the usual success, stored as spam (disallowed_keys)', array(!empty($a['success']), (int) $e['entry_status'], $why($e)), array(true, 1, 'disallowed_keys'));
$a = $send('Hi', 'Jane', 'spammer@bad.example');
t('on: a listed address in the e-mail field → spam', (int) $last()['entry_status'], 1);
$a = $send('سلام، کازینو آنلاین');
t('on: a listed Persian word → spam', (int) $last()['entry_status'], 1);
$a = $send('A perfectly normal message about pills');
t('on: words that are not listed → stored normally', array(!empty($a['success']), (int) $last()['entry_status']), array(true, 0));
$H->ctl('set-option', 'disallowed_keys', '');
$a = $send('Buy cheap-pills now');
t('an empty list never matches', (int) $last()['entry_status'], 0);
$H->ctl('set-option', 'disallowed_keys', "cheap-pills");
$O = $H->ctl('f03-form', 'F12 words off', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'), array('key' => 'msg', 'type' => 'textarea', 'name' => 'Message'))), 'spam_off=1');
$a = $send('Buy cheap-pills now', 'Jane', 'jane@example.test', (int) $O['form_id'], $O['ids']);
t('spam_off on the form skips the check', (int) $last((int) $O['form_id'])['entry_status'], 0);
$H->ctl('f12-setting', 'spam_action=reject');
$n0 = count((array) $H->ctl('f14-entries', (string) $fid));
$a = $send('Buy cheap-pills now');
t('spam_action reject: nothing stored, efb_spam_rejected', array(empty($a['success']), $a['code'] ?? '', count((array) $H->ctl('f14-entries', (string) $fid)) === $n0), array(true, 'efb_spam_rejected', true));

foreach (array($fid, (int) $O['form_id']) as $id) $H->ctl('f12-delete-form', (string) $id);
$H->ctl('set-option', 'disallowed_keys', is_string($old_list) ? $old_list : '');
$H->ctl('f12-setting', 'spam_disallowed_keys=del&spam_min_time=del&spam_action=del');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
