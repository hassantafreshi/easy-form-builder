<?php
/**
 * F12-E Akismet on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP. Akismet's servers are never
 * called: the mu-plugin efb-e2e-f12.php answers rest.akismet.com (pre_http_request) and loads either Akismet's own
 * class from the inactive plugin ("real", a placeholder key) or a stand-in class ("fake"):
 *
 *  - no Akismet (class missing): spam_akismet on changes nothing, no request, no error;
 *  - with Akismet: comment_author "akismet-guaranteed-spam" → stored as spam (reason akismet), the usual success;
 *    a normal author → stored normally; X-akismet-pro-tip: discard → reason akismet (akismet_discard kept in the
 *    history); is_test sent while the site's test filter says so;
 *  - the request: comment_type contact-form, author from the name fields, the form's e-mail field, the text and
 *    textarea answers, blog_lang, the honeypot field's name; the address only as the form's F13 IP mode allows
 *    (store: as is; anon: anonymized; none: no user_ip, no user_agent);
 *  - the form's own switch (spam_akismet "0") skips it; an answer that fails another check never reaches Akismet;
 *  - "Not spam" on an Akismet spam entry reports submit-ham; "Mark as spam" reports submit-spam;
 *  - Akismet unreachable → fail-open (a normal answer).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-spam-akismet.php
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
$H->ctl('set-option', 'efb_e2e_f12_mock', '1');
$H->ctl('f12-setting', 'spam_akismet=1&spam_min_time=0&spam_honeypot=1&prv_ip_mode=store');
$F = $H->ctl('f03-form', 'F12 akismet', json_encode(array(array('key' => 'fn', 'type' => 'text', 'elementId' => 'firstName', 'name' => 'First name'), array('key' => 'ln', 'type' => 'text', 'elementId' => 'lastName', 'name' => 'Last name'),
	array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'), array('key' => 'msg', 'type' => 'textarea', 'name' => 'Message'))));
$fid = (int) $F['form_id'];
$rows = function ($first, $msg = 'Hello there') use ($F) {
	return array(array('id_' => $F['ids']['fn'], 'name' => 'First name', 'type' => 'text', 'value' => $first), array('id_' => $F['ids']['ln'], 'name' => 'Last name', 'type' => 'text', 'value' => 'Doe'),
		array('id_' => $F['ids']['em'], 'name' => 'E-mail', 'type' => 'email', 'value' => 'jane@example.test'), array('id_' => $F['ids']['msg'], 'name' => 'Message', 'type' => 'textarea', 'value' => $msg));
};
$send = function ($first, $msg = 'Hello there', $id = null, $extra = array()) use ($H, $fid, $rows) { $H->fresh_jar(); return EfbF11Http::answer($H->submit($id ?: $fid, $rows($first, $msg), array_merge(array('efb_hp' => ''), $extra))); };
$last = function ($id) use ($H) { $e = $H->ctl('f14-entries', (string) $id); if (!is_array($e) || !$e) return null; usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e[0]; };
$why = function ($e) use ($H) { if (!$e) return ''; foreach ((array) $H->ctl('f14-log', (string) $e['msg_id']) as $r) if ($r['action'] === 'spam') return $r['meta']; return ''; };
$calls = function () use ($H) { return array_values(array_filter((array) $H->ctl('f12-calls'), function ($c) { return strpos($c['url'], 'akismet') !== false; })); };

/* no Akismet */
$H->ctl('set-option', 'efb_e2e_f12_akismet', 'del');
$H->ctl('f12-calls', 'clear');
$a = $send('akismet-guaranteed-spam');
t('no Akismet: spam_akismet on changes nothing (a normal answer, no request, no error)', array(!empty($a['success']), (int) $last($fid)['entry_status'], count($calls())), array(true, 0, 0));

foreach (array('fake', 'real') as $mode) {
	$H->ctl('set-option', 'efb_e2e_f12_akismet', $mode);
	$H->ctl('f12-calls', 'clear');
	$a = $send('akismet-guaranteed-spam');
	$e = $last($fid);
	t("{$mode}: comment_author akismet-guaranteed-spam → the usual success, stored as spam (akismet)", array(!empty($a['success']), (int) $e['entry_status'], $why($e)), array(true, 1, 'akismet'));
	$c = $calls();
	$c0 = $c ? $c[0] : array();
	t("{$mode}: one comment-check with contact-form, the author from the name fields, the form's e-mail, the textarea, blog_lang, the honeypot's name",
		array(count($c), substr($c0['url'] ?? '', -13), $c0['comment_type'] ?? '', $c0['comment_author'] ?? '', $c0['comment_author_email'] ?? '', strpos($c0['comment_content'] ?? '', 'Hello there') !== false, ($c0['blog_lang'] ?? '') !== '', $c0['honeypot_field_name'] ?? ''),
		array(1, 'comment-check', 'contact-form', 'akismet-guaranteed-spam Doe', 'jane@example.test', true, true, 'efb_hp'));
	t("{$mode}: IP mode store → user_ip and user_agent sent", array(isset($c0['user_ip']) && $c0['user_ip'] !== '', isset($c0['user_agent'])), array(true, true));
	$a = $send('Jane');
	t("{$mode}: a normal author → stored normally", array(!empty($a['success']), (int) $last($fid)['entry_status']), array(true, 0));
}

/* discard */
$a = $send('akismet-guaranteed-spam', 'discard-me please');
$e = $last($fid);
t('pro-tip discard → spam, the history keeps akismet_discard', array((int) $e['entry_status'], $why($e)), array(1, 'akismet_discard'));

/* the IP mode (F13) */
$H->ctl('f12-setting', 'prv_ip_mode=anon');
$H->ctl('f12-calls', 'clear');
$send('Jane');
$c = $calls();
t('IP mode anon → the anonymized address (last octet 0 / a /48)', isset($c[0]['user_ip']) && (bool) preg_match('/(\.0$|::$|:0:0:0:0:0$)/', $c[0]['user_ip']), true);
$H->ctl('f12-setting', 'prv_ip_mode=none');
$H->ctl('f12-calls', 'clear');
$send('Jane');
$c = $calls();
t('IP mode none → no user_ip and no user_agent are sent', array(count($c), isset($c[0]['user_ip']), isset($c[0]['user_agent'])), array(1, false, false));
$H->ctl('f12-setting', 'prv_ip_mode=store');

/* the form's own switch, and an answer refused by another check */
$O = $H->ctl('f03-form', 'F12 akismet off', json_encode(array(array('key' => 'fn', 'type' => 'text', 'elementId' => 'firstName', 'name' => 'First'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail', 'required' => 1))), 'spam_akismet=0');
$H->ctl('f12-calls', 'clear');
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit((int) $O['form_id'], array(array('id_' => $O['ids']['fn'], 'name' => 'First', 'type' => 'text', 'value' => 'akismet-guaranteed-spam'), array('id_' => $O['ids']['em'], 'name' => 'E-mail', 'type' => 'email', 'value' => 'a@example.test')), array('efb_hp' => '')));
t('a form with spam_akismet "0": Akismet is not asked, a normal answer', array(!empty($a['success']), count($calls()), (int) $last((int) $O['form_id'])['entry_status']), array(true, 0, 0));
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit($fid, array(array('id_' => $F['ids']['fn'], 'name' => 'First name', 'type' => 'text', 'value' => 'akismet-guaranteed-spam'), array('id_' => $F['ids']['em'], 'name' => 'E-mail', 'type' => 'email', 'value' => 'not-an-address')), array('efb_hp' => '')));
t('an answer refused by its own field check never reaches Akismet', array(empty($a['success']), count($calls())), array(true, 0));

/* feedback */
$H->ctl('f12-calls', 'clear');
$send('akismet-guaranteed-spam');
$sp = $last($fid);
$H->ctl('f12-op', (string) $fid, 'unspam', (string) $sp['msg_id']);
$fb = array_values(array_filter($calls(), function ($c) { return substr($c['url'], -10) === 'submit-ham'; }));
t('"Not spam" on an Akismet spam entry → submit-ham (once)', count($fb), 1);
$H->ctl('f12-op', (string) $fid, 'spam', (string) $sp['msg_id']);
$fb = array_values(array_filter($calls(), function ($c) { return substr($c['url'], -11) === 'submit-spam'; }));
t('"Mark as spam" on a form Akismet checks → submit-spam', count($fb), 1);

/* unreachable */
$H->ctl('set-option', 'efb_e2e_f12_mock', 'del');
$H->ctl('set-option', 'efb_e2e_f12_akismet', 'fake');
$H->ctl('set-option', 'efb_e2e_f12_akismet_down', '1');
$a = $send('akismet-guaranteed-spam');
t('Akismet unreachable → fail-open: a normal answer', array(!empty($a['success']), (int) $last($fid)['entry_status']), array(true, 0));

foreach (array($fid, (int) $O['form_id']) as $id) $H->ctl('f12-delete-form', (string) $id);
foreach (array('efb_e2e_f12_akismet', 'efb_e2e_f12_akismet_down', 'efb_e2e_f12_mock') as $k) $H->ctl('set-option', $k, 'del');
$H->ctl('f12-setting', 'spam_akismet=del&spam_min_time=del&spam_honeypot=del');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
