<?php
/**
 * F12b scenario row 19 on the real WordPress: the F02 send-code request (Emsfb/v1/verify/send) of a form protected by
 * an invisible CAPTCHA carries its token for the action efb_otp, checked before any SMS is sent. The SMS path itself
 * is untouched (the WP SMS stand-in records what it was asked to send).
 *
 *  - reCAPTCHA v3: no token → efb_captcha_missing, nothing sent; a token for another action (efb_submit) →
 *    efb_captcha_action; a low score → efb_captcha_low_score (never "spam" here); a good efb_otp token → the code is
 *    sent; the same token again from another request → efb_captcha_replay;
 *  - reCAPTCHA v2 invisible: no token → refused; a token → sent;
 *  - a visible widget (v2 checkbox): not asked for on the code request (it is solved for the form's own send);
 *  - CAPTCHA off on the form: not asked; the hidden field still refuses as before.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-otp-captcha-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$plan0 = (string) ($H->ctl('state')['emsfb_pro'] ?? '3');
$H->ctl('plan', '1');
$H->ctlb('mu');
$H->ctlb('mock', '1');
$H->ctlb('rl-reset');
$H->ctl('f02-mu');
$H->ctl('addon-flag', 'AdnSS', '1');
$H->ctl('f02-wpsms', '1', 'ok');
$H->ctl('f02-settings', json_encode(array('sms' => array('allowed_countries' => array('ir')))));
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&rciSiteKey=e2e-v2i-site-placeholder&rciSecretKey=e2e-v2i-secret-placeholder&rc3SiteKey=e2e-v3-site-placeholder&rc3SecretKey=e2e-v3-secret-placeholder&rc3Threshold=0.5&spam_min_time=0&captcha_provider=recaptcha_v2');
$mob = array(array('key' => 'mb', 'type' => 'mobile', 'name' => 'Mobile', 'otp_enabled' => '1', 'otp_channel' => 'sms', 'otp_required_when' => 'always'));
$F = array(
	'v3' => $H->ctl('f03-form', 'F12b otp v3', json_encode($mob), 'captcha=1&captcha_provider=recaptcha_v3'),
	'v2i' => $H->ctl('f03-form', 'F12b otp v2i', json_encode($mob), 'captcha=1&captcha_provider=recaptcha_v2i'),
	'v2' => $H->ctl('f03-form', 'F12b otp v2', json_encode($mob), 'captcha=1&captcha_provider=recaptcha_v2'),
	'off' => $H->ctl('f03-form', 'F12b otp off', json_encode($mob), ''),
);
$sent = function () use ($H) { $l = $H->ctl('f02-sms'); return is_array($l) ? count($l) : -1; };
$send = function ($k, $extra = array()) use ($H, $F) {
	$fid = (int) $F[$k]['form_id'];
	$H->ctl('f02-rows', (string) $fid, 'reset');
	$H->fresh_jar();
	$p = $H->prepare($fid);
	$body = array_merge(array('form_id' => $fid, 'field_id' => $F[$k]['ids']['mb'], 'runtime_token' => $p['json']['runtime_token'], 'destination' => '+98912' . random_int(1000000, 9999999), 'channel' => 'sms', 'efb_hp' => ''), $extra);
	$r = $H->http('POST', '/wp-json/Emsfb/v1/verify/send', $body, array('X-WP-Nonce' => $p['json']['nonce'], 'sid' => $p['json']['sid']));
	return is_array($r['json']) ? $r['json'] : array();
};
$tok = function ($ans = array()) { return EfbF12bHttp::tok($ans); };
$H->ctl('f02-sms', 'clear');

echo "--- reCAPTCHA v3 ---\n";
$a = $send('v3');
t('no token: efb_captcha_missing, no SMS sent', array($a['success'] ?? null, $a['code'] ?? '', $sent()), array(false, 'efb_captcha_missing', 0));
$a = $send('v3', array('captcha' => $tok(array('score' => 0.9, 'action' => 'efb_submit'))));
t('a token of the form\'s own action (efb_submit): efb_captcha_action, nothing sent', array($a['code'] ?? '', $sent()), array('efb_captcha_action', 0));
$a = $send('v3', array('captcha' => $tok(array('score' => 0.1, 'action' => 'efb_otp'))));
t('a low score: efb_captcha_low_score (refused, never "spam"), nothing sent', array($a['code'] ?? '', $sent()), array('efb_captcha_low_score', 0));
$good = $tok(array('score' => 0.9, 'action' => 'efb_otp'));
$a = $send('v3', array('captcha' => $good, 'request_id' => EfbF11Http::uuid()));
t('a good efb_otp token: the code is sent (one SMS through the WP SMS bridge)', array($a['success'] ?? null, $sent()), array(true, 1));
$a = $send('v3', array('captcha' => $good, 'request_id' => EfbF11Http::uuid()));
t('the same token from another request: efb_captcha_replay', array($a['code'] ?? '', $sent()), array('efb_captcha_replay', 1));
$calls = $H->ctlb('calls');
$last = is_array($calls) && $calls ? end($calls) : array();
t('siteverify was asked on Google (keys only, never a secret value in the log)', array(strpos((string) ($last['url'] ?? ''), 'google.com/recaptcha/api/siteverify') !== false, in_array('secret', (array) ($last['keys'] ?? array()), true)), array(true, true));

echo "--- reCAPTCHA v2 invisible ---\n";
$a = $send('v2i');
t('no token: refused (efb_captcha_missing)', array($a['code'] ?? '', $sent()), array('efb_captcha_missing', 1));
$a = $send('v2i', array('captcha' => $tok()));
t('a token: sent', array($a['success'] ?? null, $sent()), array(true, 2));

echo "--- visible widget / CAPTCHA off ---\n";
$a = $send('v2');
t('a visible v2 checkbox is not asked for on the code request: sent', array($a['success'] ?? null, $sent()), array(true, 3));
$a = $send('off');
t('CAPTCHA off: sent', array($a['success'] ?? null, $sent()), array(true, 4));
$a = $send('off', array('efb_hp' => 'http://bot.example'));
t('the hidden field filled still refuses (efb_spam_rejected)', array($a['code'] ?? '', $sent()), array('efb_spam_rejected', 4));

foreach ($F as $f) $H->ctl('f12-delete-form', (string) $f['form_id']);
$H->ctl('f02-wpsms', '0');
$H->ctl('addon-flag', 'AdnSS', '0');
$H->ctlb('mock', '0');
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&rciSiteKey=del&rciSecretKey=del&rc3SiteKey=del&rc3SecretKey=del&rc3Threshold=del&captcha_provider=del');
$H->ctl('plan', $plan0 !== '' ? $plan0 : '3');
efb_done();
