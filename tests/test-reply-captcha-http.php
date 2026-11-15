<?php
/**
 * F12b D3 + follow-up (owner decision 2026-11-15) on the real WordPress: the reply box of a tracked entry
 * (forms/response/add) — no friction for real people, firm against bots.
 *
 *  - a guest with the tracking code: the CAPTCHA once; the pass lasts 30 minutes, renews with each reply (sliding) and is
 *    bound to the tracking code and the visitor's session — another session, a forged session token or another
 *    conversation is asked again; the pass gone (expired) → asked again; a refused / replayed token → nothing stored;
 *  - the signed-in owner of the entry: no CAPTCHA (no box in the lookup either), the visitor limits apply;
 *  - staff (manage_options, or the Emsfb capability of those who answer entries): no CAPTCHA, no visitor limits;
 *  - limits: 5 replies per tracking code in 10 minutes and 20 per address in an hour (defaults, settings
 *    reply_rl_track / reply_rl_ip, 0 = Off): HTTP 429 with Retry-After and retry_after in the answer, nothing stored;
 *    the hidden field filled → refused; a site without keys / the setting off → no CAPTCHA, the limits stay;
 *  - capabilities report the reply settings (no secret).
 *
 * Run (the e2e site, under the e2e lock): php tests/test-reply-captcha-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctlb('mu');
$H->ctlb('mock', '1');
$H->ctlb('rl-reset');
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&captcha_provider=recaptcha_v2&scaptcha=1&spam_min_time=0&reply_pass_min=del&reply_rl_track=del&reply_rl_ip=del');
$F = $H->ctl('f03-form', 'F12b reply', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
$seed = function ($v) use ($H, $F) { return $H->ctl('f14-seed', (string) $F['form_id'], json_encode(array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $v))), ''); };
$E1 = $seed('Ticket one');
$E2 = $seed('Ticket two');
$count = function ($E) use ($H) { $r = $H->ctl('replies', (string) $E['msg_id']); return is_array($r) ? count($r) : -1; };

/** A reply session: a nonce and a runtime token (fid 0, as EFBRuntime.decorate(data, 0, 'reply') does). Keeps the jar unless $fresh. */
$session = function ($fresh = true) use ($H, $F) { if ($fresh) $H->fresh_jar(); $p = $H->prepare(0); $j = is_array($p['json']) ? $p['json'] : array(); if (empty($j['nonce'])) { $p = $H->prepare((int) $F['form_id']); $j = (array) $p['json']; } return $j; };
$raw = function ($S, $E, $token, $extra = array()) use ($H) {
	$msg = json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => 'Reply ' . bin2hex(random_bytes(3)), 'by' => '', 'session' => '')));
	$body = array_merge(array('id' => (string) $E['msg_id'], 'track' => $E['track'], 'message' => $msg, 'sid' => $S['sid'] ?? '', 'user_type' => 'user', 'sc' => '', 'page_id' => 0, 'valid' => $token, 'efb_hp' => '', 'type' => 'form',
		'request_id' => EfbF11Http::uuid(), 'runtime_token' => $S['runtime_token'] ?? ''), $extra);
	return $H->http('POST', '/wp-json/Emsfb/v1/forms/response/add', $body, array('X-WP-Nonce' => $S['nonce'] ?? '', 'sid' => $S['sid'] ?? '', 'form-id' => '0'));
};
$reply = function ($S, $E, $token, $extra = array()) use ($raw) { return EfbF11Http::answer($raw($S, $E, $token, $extra)); };
$ok = function () { return EfbF12bHttp::tok(); };

echo "--- a guest: the CAPTCHA once, a 30-minute sliding pass ---\n";
$H->ctl('f12-setting', 'reply_rl_track=50'); // this section sends more than 5 to one conversation; the limits have their own section
$S1 = $session();
$n0 = $count($E1);
$a = $reply($S1, $E1, '');
t('no token: the CAPTCHA message, nothing stored', array(empty($a['success']), $a['code'] ?? '', $count($E1)), array(true, 'efb_captcha_missing', $n0));
$a = $reply($S1, $E1, EfbF12bHttp::tok(array('success' => false, 'error-codes' => array('invalid-input-response'))));
t('a refused token: efb_captcha_failed, nothing stored', array($a['code'] ?? '', $count($E1)), array('efb_captcha_failed', $n0));
$used = $ok();
$a = $reply($S1, $E1, $used);
$p1 = $H->ctlb('rpcp');
t('a solved token: stored; the answer says until when the pass lasts (30 minutes)', array(!empty($a['success']), $count($E1), abs(((int) ($a['reply_pass_until'] ?? 0)) - (time() + 1800)) < 30, count($p1) === 1 && $p1[0] > 1750), array(true, $n0 + 1, true, true));
sleep(2);
$a = $reply($S1, $E1, '');
$p2 = $H->ctlb('rpcp');
t('the same session again: no new check, stored; the pass renewed (sliding: 30 minutes from this reply)', array(!empty($a['success']), $count($E1), $p2[0] >= $p1[0]), array(true, $n0 + 2, true));
$S2 = $session();
$a = $reply($S2, $E1, '');
t('another session (another tab or browser): asked again', $a['code'] ?? '', 'efb_captcha_missing');
$a = $reply($S2, $E1, $used);
t('the token of the first reply in another session: replay', $a['code'] ?? '', 'efb_captcha_replay');
$forged = $S1; $forged['runtime_token'] = substr($S1['runtime_token'] ?? 'x.y', 0, -3) . 'abc';
$a = $reply($forged, $E1, '');
t('a forged session token gets no pass', empty($a['success']), true);
$a = $reply($S1, $E2, '');
t('the pass of one conversation does not open another', $a['code'] ?? '', 'efb_captcha_missing');
$H->ctlb('rl-reset'); // drops the passes (as if 30 minutes went by) and the counters
$a = $reply($S1, $E1, '');
t('the pass ran out: asked again', $a['code'] ?? '', 'efb_captcha_missing');
$a = $reply($S1, $E2, EfbF12bHttp::slow());
t('the service down (timeout): refused, nothing stored', array($a['code'] ?? '', empty($a['success'])), array('efb_captcha_network', true));

echo "--- limits (5 per conversation in 10 minutes, 20 per address in an hour) ---\n";
$H->ctl('f12-setting', 'reply_rl_track=del');
$H->ctlb('rl-reset');
$a = $reply($session(), $E2, $ok(), array('efb_hp' => 'http://bot.example'));
t('the hidden field filled: refused (efb_spam_honeypot)', array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_spam_honeypot'));
$H->ctlb('rl-reset');
$S3 = $session();
$codes = array();
for ($i = 0; $i < 5; $i++) { $r = $reply($S3, $E2, $i === 0 ? $ok() : ''); $codes[] = !empty($r['success']) ? 'ok' : ($r['code'] ?? '?'); }
$n2 = $count($E2);
$r6 = $raw($S3, $E2, '');
$a6 = EfbF11Http::answer($r6);
$ra = preg_match('/^Retry-After:\s*(\d+)/mi', $r6['headers'], $m) ? (int) $m[1] : 0;
t('5 replies in 10 minutes pass; the 6th: HTTP 429, Retry-After (≤ 600 s), efb_reply_rate with retry_after, nothing stored', array(count(array_filter($codes, function ($c) { return $c === 'ok'; })), $r6['status'], $ra > 0 && $ra <= 600, $a6['code'] ?? '', (int) ($a6['retry_after'] ?? 0) === $ra, $count($E2)), array(5, 429, true, 'efb_reply_rate', true, $n2));
$H->ctlb('rl-reset');
$H->ctl('f12-setting', 'reply_rl_track=100&reply_rl_ip=3');
$ip = array();
foreach (array($E1, $E2, $E1, $E2) as $i => $E) { $r = $raw($session(), $E, $ok()); $ip[] = $r['status'] === 429 ? 429 : (!empty(EfbF11Http::answer($r)['success']) ? 'ok' : '?'); }
t('per address (set to 3 an hour): the 4th reply from this address is 429, across conversations and sessions', $ip, array('ok', 'ok', 'ok', 429));
$H->ctlb('rl-reset');
$H->ctl('f12-setting', 'reply_rl_ip=0');
$ip = array();
for ($i = 0; $i < 4; $i++) { $r = $raw($session(), $E1, $ok()); $ip[] = $r['status'] === 429 ? 429 : (!empty(EfbF11Http::answer($r)['success']) ? 'ok' : '?'); }
t('per address Off (0): no limit per address', $ip, array('ok', 'ok', 'ok', 'ok'));
$H->ctl('f12-setting', 'reply_rl_track=del&reply_rl_ip=del');
$H->ctlb('rl-reset');

echo "--- the signed-in owner, staff ---\n";
$owner = 'efb_f12b_owner_' . substr(md5((string) mt_rand()), 0, 5);
$staff = 'efb_f12b_staff_' . substr(md5((string) mt_rand()), 0, 5);
$H->ctlb('user', $owner);
$H->ctlb('user', $staff);
$H->ctlb('cap', $staff, 'Emsfb', 'editor');
$E3 = $seed('Owner ticket');
$H->ctlb('msg-uid', (string) $E3['msg_id'], $owner);
$H->fresh_jar();
$H->login($owner, $owner . '-pass-123');
$SO = $session(false);
$g = EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/get', array('value' => $E3['track'], 'valid' => $ok(), 'sid' => $SO['sid'] ?? '', 'name' => 'tracker', 'id' => 0), array('X-WP-Nonce' => $SO['nonce'] ?? '', 'sid' => $SO['sid'] ?? '')));
t('the owner\'s lookup: no reply CAPTCHA box', array(!empty($g['success']), (string) ($g['reply_captcha'] ?? '')), array(true, ''));
$own = array();
for ($i = 0; $i < 6; $i++) { $r = $raw($SO, $E3, ''); $own[] = $r['status'] === 429 ? 429 : (!empty(EfbF11Http::answer($r)['success']) ? 'ok' : (EfbF11Http::answer($r)['code'] ?? '?')); }
t('the signed-in owner: no CAPTCHA (5 stored without a token), the visitor limit still applies (6th: 429)', $own, array('ok', 'ok', 'ok', 'ok', 'ok', 429));
$H->ctlb('rl-reset');
$H->fresh_jar();
$H->login($staff, $staff . '-pass-123');
$SS = $session(false);
$st = array();
for ($i = 0; $i < 7; $i++) { $r = $raw($SS, $E1, '', array('user_type' => 'admin')); $st[] = $r['status'] === 429 ? 429 : (!empty(EfbF11Http::answer($r)['success']) ? 'ok' : (EfbF11Http::answer($r)['code'] ?? '?')); }
t('staff (an editor with the Emsfb capability): no CAPTCHA, no visitor limits (7 in a row)', $st, array_fill(0, 7, 'ok'));
$H->fresh_jar();
$nonce = $H->admin();
$p = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => 0, 'runtime_token' => ''), array('X-WP-Nonce' => $nonce));
$SA = is_array($p['json']) ? $p['json'] : array();
$SA['nonce'] = !empty($SA['nonce']) ? $SA['nonce'] : $nonce;
$a = $reply($SA, $E2, '', array('user_type' => 'admin', 'request_id' => '', 'runtime_token' => ''));
t('the administrator signed in: no CAPTCHA', !empty($a['success']), true);
$H->ctlb('user-del', $owner, $staff);

echo "--- settings ---\n";
$H->fresh_jar();
$H->ctlb('rl-reset');
$H->ctl('f12-setting', 'secretKey=del&siteKey=del');
$a = $reply($session(), $E2, '');
t('a site without keys: no CAPTCHA (no box), the reply is stored', !empty($a['success']), true);
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&scaptcha=');
$a = $reply($session(), $E2, '');
t('"CAPTCHA in the response box" off: no CAPTCHA', !empty($a['success']), true);
$H->ctl('f12-setting', 'scaptcha=1&reply_pass_min=45&reply_rl_track=7&reply_rl_ip=0');
$f = $H->ctl('f12-setting', '');
t('the reply settings are site settings, stored as typed (45 minutes, 7, Off = 0)', array($f['reply_pass_min'] ?? null, $f['reply_rl_track'] ?? null, $f['reply_rl_ip'] ?? null), array('45', '7', '0'));
$H->ctlb('rl-reset');
$S5 = $session();
$a = $reply($S5, $E1, $ok());
t('… and the pass follows the setting (45 minutes)', abs(((int) ($a['reply_pass_until'] ?? 0)) - (time() + 2700)) < 30, true);
$H->ctl('f12-setting', 'reply_pass_min=del&reply_rl_track=del&reply_rl_ip=del');

echo "--- the lookup hands a guest the reply box's CAPTCHA ---\n";
$S4 = $session();
$g = EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/get', array('value' => $E1['track'], 'valid' => $ok(), 'sid' => $S4['sid'] ?? '', 'name' => 'tracker', 'id' => 0), array('X-WP-Nonce' => $S4['nonce'] ?? '', 'sid' => $S4['sid'] ?? '')));
$rc = (string) ($g['reply_captcha'] ?? '');
t('guest: reply_captcha is a box of context reply_' . $E1['msg_id'] . ' (data-formid -1, its own id, the v2 site key, no secret)', array(!empty($g['success']), strpos($rc, 'data-efb-captcha-ctx="reply_' . $E1['msg_id'] . '"') !== false, (bool) preg_match('/id="efb-cp-reply_' . $E1['msg_id'] . '-\d+"/', $rc), strpos($rc, 'e2e-v2-site-placeholder') !== false, strpos($rc, 'secret') === false), array(true, true, true, true, true));

$H->ctl('f12-delete-form', (string) $F['form_id']);
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&captcha_provider=del&scaptcha=del');
$H->ctlb('mock', '0');
$H->ctlb('rl-reset');
efb_done();
