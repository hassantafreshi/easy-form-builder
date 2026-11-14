<?php
/**
 * F14-G: resend the notification of an entry on the e2e site (tests/f11-http-lib.php): the entry is made by a
 * real submission, then efb_entry_resend_Emsfb is called as the panel calls it, logged in as the administrator;
 * the mails are what WordPress was asked to send (the mu-plugin records wp_mail without sending).
 *
 *  - the preview: the form's address and the visitor's address (the field the form sends to);
 *  - original: the owner's copy and the visitor's copy, as on submit; admin / visitor: only that copy;
 *  - smart tags in the subject resolve from the entry (its answers), never from the administrator who resends;
 *  - custom: several addresses, separated by commas, semicolons or the Arabic comma; an invalid address or more
 *    than 10 are refused with the address named; only an administrator (manage_options) may use it;
 *  - the form's notification rules are evaluated again on the stored answers: a matching rule replaces the
 *    owner's copy, as on submit;
 *  - five sends of one entry per hour; the sixth is refused (efb_resend_limit);
 *  - the history has one "resend" row with the target and the number of recipients, never an address;
 *  - more than 50 entries, a request without the nonce, a Subscriber are refused; on Free it needs Free Plus.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-entry-resend.php
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
$H->ctl('plan', '3');

$F = $H->ctl('f03-form', 'F14 resend', json_encode(array(
	array('key' => 'nm', 'type' => 'text', 'name' => 'Name'),
	array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
	array('key' => 'dep', 'type' => 'select', 'name' => 'Department'),
	array('key' => 'd1', 'type' => 'option', 'parent_key' => 'dep', 'value' => 'Sales'),
	array('key' => 'd2', 'type' => 'option', 'parent_key' => 'dep', 'value' => 'Support'),
)), 'email_sub=' . rawurlencode('Entry of {field:@@nm@@} by {user:display_name}!'));
$i = $F['ids'];
$fid = (int) $F['form_id'];
$mk = function ($name, $email, $dep) use ($i) {
	return array(
		array('id_' => $i['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name),
		array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email),
		array('id_' => $i['dep'], 'id_ob' => $i[$dep === 'Sales' ? 'd1' : 'd2'], 'name' => 'Department', 'type' => 'select', 'value' => $dep),
	);
};
$a1 = EfbF11Http::answer($H->submit($fid, $mk('Ali', 'ali@example.test', 'Sales')));
t('an entry stored by a submission', !empty($a1['success']), true);
$e1 = (int) $H->ctl('f14-entries', $fid)[0]['msg_id'];

t('the administrator logs in', $H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123'), true);
$p = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false);
$nonce = preg_match('/efb_var\s*=\s*\{[^;]*?"nonce":"([a-f0-9]+)"/', $p['body'], $m) ? $m[1] : '';
$ajax = function ($data) use ($H, &$nonce) {
	$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array_merge(array('nonce' => $nonce), $data)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$j = json_decode($r['body'], true);
	return is_array($j) && isset($j['data']) ? $j['data'] : array('raw' => substr($r['body'], 0, 300));
};
$send = function ($ids, $target, $emails = '') use ($ajax) {
	return $ajax(array('action' => 'efb_entry_resend_Emsfb', 'op' => 'send', 'target' => $target, 'ids' => json_encode((array) $ids), 'emails' => $emails));
};
$mails = function () use ($H) {
	$out = array();
	foreach ((array) $H->ctl('mails') as $m) foreach ((array) $m['to'] as $to) $out[] = array(strtolower((string) $to), (string) $m['subject']);
	sort($out);
	return $out;
};
$to = function ($list) { return array_values(array_unique(array_map(function ($x) { return $x[0]; }, $list))); };

/* preview */
$pv = $ajax(array('action' => 'efb_entry_resend_Emsfb', 'op' => 'preview', 'msg_id' => $e1));
t('preview: the form\'s address, the visitor\'s address, no rules, "Another address" for an administrator', array($pv['success'] ?? null, $pv['preview']['admin'] ?? null, $pv['preview']['visitor'] ?? null, $pv['preview']['rules'] ?? null, $pv['custom'] ?? null), array(true, array('owner@example.test'), 'ali@example.test', false, true));

/* original */
$H->ctl('mails', 'clear');
$r = $send($e1, 'original');
$got = $mails();
t('original: the owner\'s copy and the visitor\'s copy, as on submit', array($r['success'] ?? null, $r['results'][$e1] ?? null, $r['count'] ?? null, $to($got)), array(true, 'sent', 2, array('ali@example.test', 'owner@example.test')));
$owner_subject = '';
foreach ($got as $g) if ($g[0] === 'owner@example.test') $owner_subject = $g[1];
t('the subject\'s smart tags come from the entry ("Ali"), not from the administrator who resends', array(strpos($owner_subject, 'Entry of Ali by') === 0, stripos($owner_subject, 'admin') === false), array(true, true));
t('the answer says how many recipients', (string) ($r['m'] ?? ''), 'Notification sent to 2 recipients.');

/* admin / visitor */
$H->ctl('mails', 'clear');
$send($e1, 'admin');
t('admin: only the owner\'s copy', $to($mails()), array('owner@example.test'));
$H->ctl('mails', 'clear');
$send($e1, 'visitor');
t('visitor: only the visitor\'s copy', $to($mails()), array('ali@example.test'));

/* custom */
$H->ctl('mails', 'clear');
$r = $send($e1, 'custom', "a@example.test، b@example.test; c@example.test");
t('custom: three addresses separated by "،" and ";"', array($r['count'] ?? null, $to($mails())), array(3, array('a@example.test', 'b@example.test', 'c@example.test')));
$r = $send($e1, 'custom', 'a@example.test, nope');
t('custom: an invalid address is refused and named', array($r['code'] ?? null, strpos((string) ($r['m'] ?? ''), 'nope') !== false), array('efb_invalid_email', true));
$many = implode(',', array_map(function ($n) { return "p{$n}@example.test"; }, range(1, 11)));
t('custom: more than 10 addresses are refused', $send($e1, 'custom', $many)['code'] ?? null, 'efb_too_many');

/* the hourly limit: original, admin, visitor, custom = 4; the fifth goes, the sixth does not */
t('the fifth send of the hour goes', $send($e1, 'admin')['success'] ?? null, true);
$r = $send($e1, 'admin');
t('the sixth send of the hour is refused (efb_resend_limit)', array($r['success'] ?? null, $r['code'] ?? null, $r['results'][$e1] ?? null, $r['m'] ?? null), array(false, 'efb_resend_limit', 'limit', 'This notification was resent too many times. Try again later.'));

/* history */
$log = array_values(array_filter((array) $H->ctl('f14-log', $e1), function ($l) { return ($l['action'] ?? '') === 'resend'; }));
$metas = array_map(function ($l) { return (string) $l['meta']; }, $log);
sort($metas);
t('history: one "resend" row per send, target and number of recipients', $metas, array('admin:1', 'admin:1', 'custom:3', 'original:2', 'visitor:1'));
t('history: never an address', strpos(json_encode($log), '@') === false, true);

/* notification rules */
$H->ctl('f14-form-key', $fid, 'notification_rules', json_encode(array(array(
	'id' => 'nr_sales', 'name' => 'Sales', 'enabled' => true, 'priority' => 10, 'recipient' => 'sales@example.test', 'subject' => 'Sales [confirmation_code]', 'template' => 'default',
	'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => $i['dep'], 'compare' => 'is', 'value' => 'Sales'))),
))));
$a2 = EfbF11Http::answer($H->submit($fid, $mk('Mina', 'mina@example.test', 'Sales')));
$a3 = EfbF11Http::answer($H->submit($fid, $mk('Omid', 'omid@example.test', 'Support')));
$es = $H->ctl('f14-entries', $fid);
$e2 = (int) $es[1]['msg_id']; $e3 = (int) $es[2]['msg_id'];
$pv = $ajax(array('action' => 'efb_entry_resend_Emsfb', 'op' => 'preview', 'msg_id' => $e2));
t('preview of a form with rules says so', $pv['preview']['rules'] ?? null, true);
$H->ctl('mails', 'clear');
$send($e2, 'original');
t('original with a matching rule: the rule\'s address replaces the owner\'s, the visitor\'s copy goes', $to($mails()), array('mina@example.test', 'sales@example.test'));
$H->ctl('mails', 'clear');
$send($e3, 'original');
t('original, no rule matches: the owner\'s copy, as on submit', $to($mails()), array('omid@example.test', 'owner@example.test'));
$H->ctl('mails', 'clear');
$r = $send(array($e2, $e3), 'visitor');
t('several entries: each to its own visitor', array($r['results'][$e2] ?? null, $r['results'][$e3] ?? null, $to($mails())), array('sent', 'sent', array('mina@example.test', 'omid@example.test')));

/* refusals */
t('more than 50 entries are refused', $send(range(1, 51), 'admin')['code'] ?? null, 'efb_too_many');
t('an unknown target is refused', $send($e2, 'everyone')['code'] ?? null, 'efb_invalid');
$saved = $nonce;
$nonce = '';
t('without the nonce → efb_forbidden', $send($e2, 'admin')['code'] ?? null, 'efb_forbidden');
$nonce = $saved;
$H->ctl('plan', '2');
t('on Free resend needs Free Plus (setting:entries_resend)', $send($e2, 'admin')['feature'] ?? null, 'setting:entries_resend');
$H->ctl('plan', '3');
$H->ctl('user', 'efb_e2e_f14rs', 'subscriber');
$H->fresh_jar();
$H->login('efb_e2e_f14rs', 'efb_e2e_f14rs-pass-123');
$nonce = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
t('a Subscriber with a valid nonce → efb_forbidden', $send($e2, 'admin')['code'] ?? null, 'efb_forbidden');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
