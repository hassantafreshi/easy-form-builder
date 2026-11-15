<?php
/**
 * F12b D3 (§D3) on the real WordPress: the reply box (forms/response/add) gets its own CAPTCHA and limits; it used to
 * read "valid" and check nothing.
 *
 *  - "CAPTCHA in the response box" on: a guest's reply without a token / with a refused one → the CAPTCHA message,
 *    nothing stored; with a solved token → stored; the same reply session again within 10 minutes → no new solve;
 *    another session, a forged session token or another conversation → asked again; a token used before → replay;
 *  - the hidden field filled → refused; the 11th reply in 10 minutes from one address → efb_reply_rate (the limit
 *    holds without any CAPTCHA too); the administrator signed in is exempt;
 *  - a site without keys: no CAPTCHA (no box either), the limits stay; the setting off: no CAPTCHA;
 *  - the lookup (forms/response/get) hands a guest the reply box's own CAPTCHA (context reply_{id}, its own id),
 *    an administrator none.
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
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&captcha_provider=recaptcha_v2&scaptcha=1&spam_min_time=0');
$F = $H->ctl('f03-form', 'F12b reply', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
$E1 = $H->ctl('f14-seed', (string) $F['form_id'], json_encode(array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'Ticket one'))), '');
$E2 = $H->ctl('f14-seed', (string) $F['form_id'], json_encode(array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'Ticket two'))), '');
$count = function ($E) use ($H) { $r = $H->ctl('replies', (string) $E['msg_id']); return is_array($r) ? count($r) : -1; };

/** A reply session: a nonce and a runtime token (fid 0, as EFBRuntime.decorate(data, 0, 'reply') does). */
$session = function () use ($H, $F) { $H->fresh_jar(); $p = $H->prepare(0); $j = is_array($p['json']) ? $p['json'] : array(); if (empty($j['nonce'])) { $p = $H->prepare((int) $F['form_id']); $j = (array) $p['json']; } return $j; };
$reply = function ($S, $E, $token, $extra = array()) use ($H) {
	$msg = json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => 'Reply ' . bin2hex(random_bytes(3)), 'by' => '', 'session' => '')));
	$body = array_merge(array('id' => (string) $E['msg_id'], 'track' => $E['track'], 'message' => $msg, 'sid' => $S['sid'] ?? '', 'user_type' => 'user', 'sc' => '', 'page_id' => 0, 'valid' => $token, 'efb_hp' => '', 'type' => 'form',
		'request_id' => EfbF11Http::uuid(), 'runtime_token' => $S['runtime_token'] ?? ''), $extra);
	return EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/add', $body, array('X-WP-Nonce' => $S['nonce'] ?? '', 'sid' => $S['sid'] ?? '', 'form-id' => '0')));
};
$ok = function () { return EfbF12bHttp::tok(); };

echo "--- CAPTCHA of the reply box ---\n";
$S1 = $session();
$n0 = $count($E1);
$a = $reply($S1, $E1, '');
t('no token: the CAPTCHA message, nothing stored', array(empty($a['success']), $a['code'] ?? '', $count($E1)), array(true, 'efb_captcha_missing', $n0));
$a = $reply($S1, $E1, EfbF12bHttp::tok(array('success' => false, 'error-codes' => array('invalid-input-response'))));
t('a refused token: efb_captcha_failed, nothing stored', array($a['code'] ?? '', $count($E1)), array('efb_captcha_failed', $n0));
$used = $ok();
$a = $reply($S1, $E1, $used);
t('a solved token: stored', array(!empty($a['success']), $count($E1)), array(true, $n0 + 1));
$a = $reply($S1, $E1, '');
t('the same reply session within 10 minutes: no new solve, stored', array(!empty($a['success']), $count($E1)), array(true, $n0 + 2));
$S2 = $session();
$a = $reply($S2, $E1, '');
t('another session: asked again', $a['code'] ?? '', 'efb_captcha_missing');
$a = $reply($S2, $E1, $used);
t('the token of the first reply in another session: replay', $a['code'] ?? '', 'efb_captcha_replay');
$forged = $S1; $forged['runtime_token'] = substr($S1['runtime_token'] ?? 'x.y', 0, -3) . 'abc';
$a = $reply($forged, $E1, '');
t('a forged session token gets no pass', empty($a['success']), true);
$a = $reply($S1, $E2, '');
t('the pass of one conversation does not open another', $a['code'] ?? '', 'efb_captcha_missing');
$a = $reply($S1, $E2, EfbF12bHttp::slow());
t('the service down (timeout): refused, nothing stored', array($a['code'] ?? '', empty($a['success'])), array('efb_captcha_network', true));

echo "--- hidden field and limits ---\n";
$a = $reply($session(), $E2, $ok(), array('efb_hp' => 'http://bot.example'));
t('the hidden field filled: refused (efb_spam_honeypot)', array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_spam_honeypot'));
$H->ctlb('rl-reset');
$S3 = $session();
$codes = array();
for ($i = 0; $i < 11; $i++) $codes[] = ($r = $reply($S3, $E2, $i === 0 ? $ok() : '')) && !empty($r['success']) ? 'ok' : ($r['code'] ?? '?');
t('10 replies in 10 minutes pass, the 11th is efb_reply_rate (the session pass does not lift it)', array(count(array_filter($codes, function ($c) { return $c === 'ok'; })), end($codes)), array(10, 'efb_reply_rate'));
$H->ctlb('rl-reset');

echo "--- exemptions and settings ---\n";
$nonce = $H->admin();
$p = $H->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => 0, 'runtime_token' => ''), array('X-WP-Nonce' => $nonce));
$SA = is_array($p['json']) ? $p['json'] : array();
$SA['nonce'] = !empty($SA['nonce']) ? $SA['nonce'] : $nonce;
// The administrator's own screen sends no runtime session (an older client's request: no request id either).
$a = $reply($SA, $E2, '', array('user_type' => 'admin', 'request_id' => '', 'runtime_token' => ''));
t('the administrator signed in: no CAPTCHA', !empty($a['success']), true); if (empty($a['success'])) echo json_encode($a), "\n";
$H->fresh_jar();
$H->ctl('f12-setting', 'secretKey=del&siteKey=del');
$a = $reply($session(), $E2, '');
t('a site without keys: no CAPTCHA (no box), the reply is stored', !empty($a['success']), true);
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&scaptcha=');
$a = $reply($session(), $E2, '');
t('"CAPTCHA in the response box" off: no CAPTCHA', !empty($a['success']), true);
$H->ctl('f12-setting', 'scaptcha=1');

echo "--- the lookup hands out the reply box's CAPTCHA ---\n";
$S4 = $session();
$g = EfbF11Http::answer($H->http('POST', '/wp-json/Emsfb/v1/forms/response/get', array('value' => $E1['track'], 'valid' => $ok(), 'sid' => $S4['sid'] ?? '', 'name' => 'tracker', 'id' => 0), array('X-WP-Nonce' => $S4['nonce'] ?? '', 'sid' => $S4['sid'] ?? '')));
$rc = (string) ($g['reply_captcha'] ?? '');
t('guest: reply_captcha is a box of context reply_' . $E1['msg_id'] . ' (data-formid -1, its own id, the v2 site key, no secret)', array(!empty($g['success']), strpos($rc, 'data-efb-captcha-ctx="reply_' . $E1['msg_id'] . '"') !== false, (bool) preg_match('/id="efb-cp-reply_' . $E1['msg_id'] . '-\d+"/', $rc), strpos($rc, 'e2e-v2-site-placeholder') !== false, strpos($rc, 'secret') === false), array(true, true, true, true, true));

$H->ctl('f12-delete-form', (string) $F['form_id']);
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&captcha_provider=del&scaptcha=del');
$H->ctlb('mock', '0');
$H->ctlb('rl-reset');
efb_done();
