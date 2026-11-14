<?php
/**
 * F12-B on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser and as a bot:
 *
 *  1  honeypot: efb_hp filled → the usual success, stored as spam (reason honeypot); empty → normal; no efb_hp key
 *     (an old page, a direct REST call) → normal;
 *  2  minimum time (spam_min_time 3): sent 1 second after forms/session/prepare → spam (too_fast); 5 seconds after →
 *     normal; t0 survives a token refresh (prepare with the old token keeps the first start);
 *  3  JavaScript: no runtime token → normal; with spam_require_js → spam (no_js);
 *  4  the token: a forged one → 409 efb_context_expired, nothing stored; a replayed one (the session already sent)
 *     → the first answer again (Form_Runtime::replay), never a second entry;
 *  5  spam_action reject → nothing stored, efb_spam_rejected, the uploads it sent are deleted;
 *  6  spam_off on the form → no soft check;
 *  7  a cached page: the HTML has no time or token in it; the time is measured from prepare after load;
 *  8  payments/create and payments/quote of a payment form refuse spam (pay_spam) before anything is stored or started;
 *  9  F02 verify/send refuses a filled honeypot (no code sent);
 * 10  a site without the F12 settings: everything above is a normal answer.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-spam-runtime-http.php
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
$made = array();
$F = $H->ctl('f03-form', 'F12 runtime', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
$fid = (int) $F['form_id']; $made[] = $fid;
$rows = function ($v = 'Visitor') use ($F) { return array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $v)); };
$last = function ($id) use ($H) { $e = $H->ctl('f14-entries', (string) $id); if (!is_array($e) || !$e) return null; usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e[0]; };
$why = function ($e) use ($H) { if (!$e) return ''; $l = $H->ctl('f14-log', (string) $e['msg_id']); foreach ((array) $l as $r) if ($r['action'] === 'spam') return $r['meta']; return ''; };
$n = function ($id) use ($H) { $e = $H->ctl('f14-entries', (string) $id); return is_array($e) ? count($e) : -1; };
$send = function ($extra = array(), $wait = 0, $prep = null, $id = null) use ($H, $fid, $rows) {
	$H->fresh_jar();
	$prep = $prep ?: $H->prepare($id ?: $fid);
	if ($wait) sleep($wait);
	return EfbF11Http::answer($H->submit($id ?: $fid, $rows(), $extra, $prep));
};

/* 10 first: a site without the F12 settings */
$H->ctl('f12-setting', 'spam_honeypot=del&spam_min_time=del&spam_require_js=del&spam_action=del');
$a = $send(array('efb_hp' => 'http://bot.example'));
t('10 a site without the settings: a filled hidden field is a normal answer (4.3.1)', array(!empty($a['success']), (int) ($last($fid)['entry_status'] ?? -1)), array(true, 0));
$page = $H->http('GET', $H->page($fid), null, array(), false);
t('10 …and its page has no hidden field', strpos($page['body'], 'efb-hp-' . $fid) === false, true);

$H->ctl('f12-setting', 'spam_honeypot=1&spam_min_time=3&spam_action=spam');
/* 7 cached page */
$page = $H->http('GET', $H->page($fid), null, array(), false);
t('7 the page has the hidden field (out of view, aria-hidden, inert, tabindex -1, autocomplete off)', (bool) preg_match('/<div class="efb efb-hp" aria-hidden="true" inert [^>]*><label for="efb-hp-' . $fid . '">[^<]+<\/label><input type="text" id="efb-hp-' . $fid . '" name="efb_url_' . $fid . '" value="" tabindex="-1" autocomplete="off"/', $page['body']), true);
t('7 …and no time or runtime token in the HTML (a cached copy cannot carry them)', strpos($page['body'], 'runtime_token') === false && !preg_match('/"t0"/', $page['body']), true);

/* 1 honeypot */
$a = $send(array('efb_hp' => 'http://bot.example'), 4);
$e = $last($fid);
t('1 honeypot filled: the usual success with a code, stored as spam (honeypot)', array(!empty($a['success']), !empty($a['track']), (int) $e['entry_status'], $why($e)), array(true, true, 1, 'honeypot'));
$a = $send(array('efb_hp' => ''), 4);
t('1 honeypot empty: a normal answer', array(!empty($a['success']), (int) $last($fid)['entry_status']), array(true, 0));
$a = $send(array(), 4);
t('1 no efb_hp key (an old page or a direct REST call): a normal answer', (int) $last($fid)['entry_status'], 0);

/* 2 minimum time */
$a = $send(array('efb_hp' => ''), 1);
$e = $last($fid);
t('2 sent 1 s after prepare with 3 s required: spam (too_fast), the usual success', array(!empty($a['success']), (int) $e['entry_status'], $why($e)), array(true, 1, 'too_fast'));
$a = $send(array('efb_hp' => ''), 5);
t('2 sent 5 s after: a normal answer', (int) $last($fid)['entry_status'], 0);
$H->fresh_jar();
$p1 = $H->prepare($fid);
sleep(4);
$p2 = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => $fid, 'runtime_token' => $p1['json']['runtime_token']));
$tok = function ($t) { $b = explode('.', (string) $t)[0]; $d = json_decode(base64_decode(strtr($b, '-_', '+/')), true); return is_array($d) && isset($d['t0']) ? (int) $d['t0'] : 0; };
t('2 a token refresh keeps the first start (t0)', $tok($p2['json']['runtime_token'] ?? '') === $tok($p1['json']['runtime_token'] ?? '') && $tok($p1['json']['runtime_token'] ?? '') > 0, true);
$a = EfbF11Http::answer($H->submit($fid, $rows(), array('efb_hp' => ''), $p2));
t('2 …so a send right after the refresh is not "too fast" (4 s since the start)', (int) $last($fid)['entry_status'], 0);

/* 3 JavaScript */
$H->fresh_jar();
$p0 = $H->prepare($fid);
$a = EfbF11Http::answer($H->submit($fid, $rows(), array('runtime_token' => '', 'request_id' => ''), $p0));
t('3 no runtime token (a page cached before 4.3.2): neutral, a normal answer', array(!empty($a['success']), (int) $last($fid)['entry_status']), array(true, 0));
$H->ctl('f12-setting', 'spam_require_js=1');
$H->fresh_jar();
$p0 = $H->prepare($fid);
$a = EfbF11Http::answer($H->submit($fid, $rows(), array('runtime_token' => '', 'request_id' => ''), $p0));
$e = $last($fid);
t('3 with spam_require_js: no runtime token → spam (no_js)', array(!empty($a['success']), (int) $e['entry_status'], $why($e)), array(true, 1, 'no_js'));
$H->ctl('f12-setting', 'spam_require_js=del');

/* 4 the token */
$H->fresh_jar();
$p0 = $H->prepare($fid);
$n0 = $n($fid);
$forged = $p0['json']['runtime_token'];
$forged = substr($forged, 0, -4) . (substr($forged, -4) === 'aaaa' ? 'bbbb' : 'aaaa');
$r = $H->submit($fid, $rows(), array('runtime_token' => $forged), $p0);
t('4 a forged token: 409 efb_context_expired, nothing stored', array($r['status'], $r['json']['data']['code'] ?? ($r['json']['code'] ?? ''), $n($fid) === $n0), array(409, 'efb_context_expired', true));
sleep(4);
$rid = EfbF11Http::uuid();
$first = EfbF11Http::answer($H->submit($fid, $rows('Once'), array('request_id' => $rid), $p0));
$again = $H->submit($fid, $rows('Once'), array('request_id' => $rid), $p0);
t('4 a replayed request: the first answer again, one entry', array(!empty($first['success']), $again['status'], ($again['json']['data']['track'] ?? '') === ($first['track'] ?? 'x'), $n($fid) === $n0 + 1), array(true, 200, true, true));

/* 6 spam_off */
$O = $H->ctl('f03-form', 'F12 spam off', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'spam_off=1');
$made[] = (int) $O['form_id'];
$t0 = $H->ctl('form', (string) $O['form_id']);
t('6 the stored form keeps spam_off "1"', $t0['form_structer'][0]['spam_off'] ?? '', '1');
$H->fresh_jar();
$pO = $H->prepare((int) $O['form_id']);
$a = EfbF11Http::answer($H->submit((int) $O['form_id'], array(array('id_' => $O['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'x')), array('efb_hp' => 'bot'), $pO));
t('6 spam_off: a filled hidden field and no wait are a normal answer', array(!empty($a['success']), (int) $last((int) $O['form_id'])['entry_status']), array(true, 0));

/* 5 reject, with an upload */
$H->ctl('f12-setting', 'spam_action=reject');
$U = $H->ctl('f03-form', 'F12 reject upload', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'up', 'type' => 'file', 'name' => 'File', 'file' => 'image'))));
$made[] = (int) $U['form_id'];
$H->fresh_jar();
$pU = $H->prepare((int) $U['form_id']);
$png = (function () { $im = imagecreatetruecolor(4, 4); imagefill($im, 0, 0, imagecolorallocate($im, 10, 30, 60)); ob_start(); imagepng($im); return ob_get_clean(); })();
$boundary = 'EFB' . bin2hex(random_bytes(8));
$body = '';
foreach (array('id' => $U['ids']['up'], 'pl' => 'msg', 'fid' => (string) $U['form_id'], 'sid' => $pU['json']['sid'], 'page_id' => '0', 'nonce_msg' => $pU['json']['nonce']) as $k => $v) $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"async-upload\"; filename=\"x.png\"\r\nContent-Type: image/png\r\n\r\n" . $png . "\r\n--$boundary--\r\n";
$upr = $H->http('POST', '/wp-json/Emsfb/v1/forms/file/upload', $body, array('Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'X-WP-Nonce' => $pU['json']['nonce'], 'X-EFB-Form-Id' => (string) $U['form_id'], 'sid' => $pU['json']['sid']), false);
$up = is_array($upr['json']) && isset($upr['json']['data']) ? $upr['json']['data'] : (array) $upr['json'];
$url = $up['file']['url'] ?? '';
if ($url === "") echo "[INFO] upload answered: " . substr($upr["body"], 0, 300) . "\n";
$info = $url !== '' ? $H->ctl('f07-file', $url) : null;
$exists_before = is_array($info) ? (bool) $info['exists'] : null;
sleep(4);
$a = EfbF11Http::answer($H->submit((int) $U['form_id'], array(array('id_' => $U['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'x'), array('id_' => $U['ids']['up'], 'name' => 'File', 'type' => 'file', 'value' => '@file@', 'url' => $url, 'id_ob' => $up['name'] ?? '', 'ut' => $up['ut'] ?? '', 'fnm' => 'x.png', 'fsz' => '1')), array('efb_hp' => 'bot'), $pU));
t('5 reject: nothing stored, the robot message (efb_spam_rejected)', array(empty($a['success']), $a['code'] ?? '', $n((int) $U['form_id'])), array(true, 'efb_spam_rejected', 0));
t('5 the test upload went through (the file is on disk, pending)', $exists_before, true);
t('5 reject: the file it uploaded is deleted now (not after the sweeper\'s day)', is_array($info) ? $H->ctl('file-exists', $info['path']) : null, false);
$H->ctl('f12-setting', 'spam_action=spam');

/* 8 payments */
$H->ctl('pay-mock', '1');
$H->ctl('pay-keys', 'test');
$P = $H->ctl('pay-form', 'fixed', 'F12 payment');
$made[] = (int) $P['form_id'];
$prow = array(array('id_' => $P['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => $P['radio'], 'id_ob' => $P['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult'));
$pays0 = count((array) $H->ctl('payments', (string) $P['form_id']));
$H->fresh_jar();
$pp = $H->prepare((int) $P['form_id']);
sleep(4);
$q = EfbF11Http::answer($H->submit((int) $P['form_id'], $prow, array('type' => 'payment', 'efb_hp' => 'bot', 'pay' => array('field_id' => $P['pf'], 'locale' => 'en_US')), $pp, 'payments/quote'));
t('8 payments/quote with a filled hidden field: pay_spam, no quote', array(empty($q['success']), $q['code'] ?? '', isset($q['quote'])), array(true, 'pay_spam', false));
$c = EfbF11Http::answer($H->submit((int) $P['form_id'], $prow, array('type' => 'payment', 'efb_hp' => 'bot', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $pp, 'payments/create'));
t('8 payments/create with a filled hidden field: pay_spam before anything is stored or started', array(empty($c['success']), $c['code'] ?? '', $n((int) $P['form_id']), count((array) $H->ctl('payments', (string) $P['form_id'])) === $pays0), array(true, 'pay_spam', 0, true));
$H->fresh_jar();
$pp = $H->prepare((int) $P['form_id']);
$c = EfbF11Http::answer($H->submit((int) $P['form_id'], $prow, array('type' => 'payment', 'efb_hp' => '', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $pp, 'payments/create'));
t('8 payments/create sent 1 s after prepare: too fast → pay_spam (a payment form always refuses)', array(empty($c['success']), $c['code'] ?? ''), array(true, 'pay_spam'));
$H->fresh_jar();
$pp = $H->prepare((int) $P['form_id']);
sleep(4);
$c = EfbF11Http::answer($H->submit((int) $P['form_id'], $prow, array('type' => 'payment', 'efb_hp' => '', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $pp, 'payments/create'));
t('8 a person: payments/create starts the payment (a paid entry is never marked spam afterwards)', array(!empty($c['success']), (int) ($last((int) $P['form_id'])['entry_status'] ?? -1)), array(true, 0));

/* 9 F02 verify/send */
$H->ctl('f02-mu');
$H->ctl('f02-wpsms', '1', 'ok');
$V = $H->ctl('f03-form', 'F12 otp', json_encode(array(array('key' => 'mb', 'type' => 'mobile', 'name' => 'Mobile', 'otp_enabled' => '1', 'otp_channel' => 'sms', 'otp_required_when' => 'always'))));
$made[] = (int) $V['form_id'];
$H->fresh_jar();
$pv = $H->prepare((int) $V['form_id']);
$vs = $H->http('POST', '/wp-json/Emsfb/v1/verify/send', array('form_id' => (int) $V['form_id'], 'field_id' => $V['ids']['mb'], 'runtime_token' => $pv['json']['runtime_token'], 'destination' => '+989121234567', 'channel' => 'sms', 'efb_hp' => 'http://bot.example'), array('X-WP-Nonce' => $pv['json']['nonce'], 'sid' => $pv['json']['sid']));
t('9 F02 verify/send with a filled hidden field: refused (efb_spam_rejected), no code sent', array($vs['json']['success'] ?? null, $vs['json']['code'] ?? ''), array(false, 'efb_spam_rejected'));

foreach ($made as $id) $H->ctl('f12-delete-form', (string) $id);
$H->ctl('f12-setting', 'spam_honeypot=del&spam_min_time=del&spam_action=del&spam_require_js=del');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
