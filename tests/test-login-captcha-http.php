<?php
/**
 * F12b D1 (docs/feature-gaps/F12b-captcha-providers.fa.md §D1) on the real WordPress: the CAPTCHA of login and
 * registration forms is checked on the server (it never was: BUGS-FOUND F12), and the soft checks fit them.
 *
 *  - login with a solved token signs in; without a token / a refused token / a token used before → the CAPTCHA
 *    message (efb_captcha_*), nobody signed in; a wrong password with a good token → WordPress' own error;
 *  - "Also check the CAPTCHA on login and registration" off (the emergency switch) → no token needed;
 *  - a site without keys (no service usable) → the login works (never locked out), no forced minimum time;
 *  - v3: a low score is refused (never "spam" for an account), a token for another action is refused;
 *  - register: the hidden field filled → refused, nothing created; sent in under the minimum time → refused; a
 *    solved token → the account is made; login sent within a second (autofill) passes: no minimum time on login;
 *  - recovery: limited per address; asked for its token only for an invisible service (v3); a visible widget is
 *    not on the recovery view, so not asked there.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-login-captcha-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctlb('mu');
$H->ctlb('mock', '1');
$H->ctlb('rl-reset');
$base = 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&rc3SiteKey=e2e-v3-site-placeholder&rc3SecretKey=e2e-v3-secret-placeholder&rc3Threshold=0.5&rc3_low_action=spam&spam_honeypot=1&spam_min_time=3&spam_action=spam&captcha_login=del&captcha_provider=recaptcha_v2';
$H->ctl('f12-setting', $base);
$user = 'efb_f12b_d1_' . substr(md5((string) mt_rand()), 0, 6);
$U = $H->ctlb('user', $user);
$L = $H->ctlb('auth-form', 'login');
$L3 = $H->ctlb('auth-form', 'login', 'captcha_provider=recaptcha_v3&rc3_action=efb_login');
$R = $H->ctlb('auth-form', 'register');
$forms = array($L['form_id'], $L3['form_id'], $R['form_id']);

$login = function ($fid, $token, $pass = null, $extra = array()) use ($H, $U) {
	$H->fresh_jar();
	$r = $H->submit((int) $fid, array(array('id_' => 'emaillogin', 'name' => 'Email', 'type' => 'text', 'value' => $U['login']), array('id_' => 'passwordlogin', 'name' => 'Password', 'type' => 'password', 'value' => $pass === null ? $U['password'] : $pass)), array_merge(array('type' => 'login', 'valid' => $token, 'efb_hp' => ''), $extra));
	$a = EfbF11Http::answer($r);
	$a['_cookie'] = (bool) preg_match('/wordpress_logged_in_/i', $r['headers']);
	return $a;
};
$v2ok = function () { return EfbF12bHttp::tok(); };
$v3 = function ($score, $action = 'efb_login') { return EfbF12bHttp::tok(array('score' => $score, 'action' => $action)); };

echo "--- login, reCAPTCHA v2 ---\n";
$a = $login($L['form_id'], $v2ok());
t('a solved token: signed in', array(!empty($a['success']), $a['_cookie']), array(true, true));
$a = $login($L['form_id'], '');
t('no token: the CAPTCHA message, nobody signed in', array(empty($a['success']), $a['code'] ?? '', $a['_cookie']), array(true, 'efb_captcha_missing', false));
$a = $login($L['form_id'], EfbF12bHttp::tok(array('success' => false, 'error-codes' => array('invalid-input-response'))));
t('a refused token: efb_captcha_failed, nobody signed in', array($a['code'] ?? '', $a['_cookie']), array('efb_captcha_failed', false));
$spent = $v2ok();
$login($L['form_id'], $spent);
$a = $login($L['form_id'], $spent);
t('the same token in another request: efb_captcha_replay', array($a['code'] ?? '', $a['_cookie']), array('efb_captcha_replay', false));
$a = $login($L['form_id'], EfbF12bHttp::slow());
t('the service does not answer (timeout): closed (efb_captcha_network)', array($a['code'] ?? '', $a['_cookie']), array('efb_captcha_network', false));
$a = $login($L['form_id'], $v2ok(), 'wrong-password-xyz');
t('a good token and a wrong password: not signed in, WordPress\' own error (not the CAPTCHA message)', array($a['_cookie'], strpos((string) ($a['code'] ?? ''), 'efb_captcha') === false, strpos(json_encode($a['m'] ?? ''), 'captcha') === false), array(false, true, true));
$a = $login($L['form_id'], $v2ok(), null, array('type' => 'register'));
t('the type of another submission at a login form: refused before anything (type confusion stays closed)', array(empty($a['success']), $a['_cookie']), array(true, false));
$a = $login($L['form_id'], $v2ok());
t('the minimum time (3 s) does not apply to a login sent at once (autofill)', $a['_cookie'], true);

echo "--- switches and a site without keys ---\n";
$H->ctl('f12-setting', 'captcha_login=');
t('captcha_login off: no token needed', $login($L['form_id'], '')['_cookie'], true);
$H->ctl('f12-setting', 'captcha_login=del&secretKey=del&siteKey=del&rc3SiteKey=del&rc3SecretKey=del');
$a = $login($L['form_id'], '');
t('no keys at all: the login works (never locked out)', $a['_cookie'], true);
$eff = $H->ctlb('effective', (string) $L['form_id']);
t('… and no forced minimum time on a login form', array($eff['id'], $eff['cfg']['min_time'], $eff['cfg']['forced']), array(null, 0, false));
$H->ctl('f12-setting', $base);

echo "--- login, reCAPTCHA v3 ---\n";
$a = $login($L3['form_id'], $v3(0.9));
t('v3 0.9 with the form\'s action (efb_login): signed in', $a['_cookie'], true);
$a = $login($L3['form_id'], $v3(0.2));
t('v3 0.2: refused (low_score), never saved as spam for an account', array($a['code'] ?? '', $a['_cookie']), array('efb_captcha_low_score', false));
$a = $login($L3['form_id'], $v3(0.9, 'efb_submit'));
t('v3 token of another action (a form\'s): efb_captcha_action', array($a['code'] ?? '', $a['_cookie']), array('efb_captcha_action', false));

echo "--- register ---\n";
$reg = function ($token, $hp = '', $wait = 4) use ($H, $R) {
	$H->fresh_jar();
	$prep = $H->prepare((int) $R['form_id']);
	if ($wait) sleep($wait);
	$n = 'efbreg' . substr(md5((string) mt_rand()), 0, 8);
	$r = $H->submit((int) $R['form_id'], array(array('id_' => 'usernameRegisterEFB', 'name' => 'Username', 'type' => 'text', 'value' => $n), array('id_' => 'emailRegisterEFB', 'name' => 'Email', 'type' => 'email', 'value' => $n . '@example.test'), array('id_' => 'passwordRegisterEFB', 'name' => 'Password', 'type' => 'password', 'value' => 'S3cur3-Pass-' . $n)), array('type' => 'register', 'valid' => $token, 'efb_hp' => $hp), $prep);
	$a = EfbF11Http::answer($r);
	$a['_user'] = $n;
	return $a;
};
$a = $reg($v2ok(), 'http://bot.example');
t('the hidden field filled: refused, nothing created', array(empty($a['success']), $a['code'] ?? '', (bool) $H->ctlb('user-exists', $a['_user'])), array(true, 'efb_spam_rejected', false));
$a = $reg($v2ok(), '', 0);
t('sent under the minimum time (3 s): refused (too_fast), nothing created', array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_spam_rejected'));
$a = $reg('', '', 4);
t('no token: the CAPTCHA message', $a['code'] ?? '', 'efb_captcha_missing');
$a = $reg($v2ok(), '', 4);
t('a solved token after 4 s: the account is made', !empty($a['success']), true);
$H->ctlb('user-del', $a['_user']);

echo "--- recovery ---\n";
$rec = function ($fid, $token) use ($H) {
	$H->fresh_jar();
	return EfbF11Http::answer($H->submit((int) $fid, array(array('value' => 'nobody@example.invalid')), array('type' => 'recovery', 'valid' => $token, 'efb_hp' => '', 'value' => json_encode(array('email' => 'nobody@example.invalid')))));
};
$H->ctlb('rl-reset', 'rcvr');
$a = $rec($L['form_id'], '');
t('v2 (a visible widget, not on the recovery view): recovery is not asked for a token', !empty($a['success']) || ($a['code'] ?? '') === '', true);
$a = $rec($L3['form_id'], '');
t('v3 (invisible): recovery without a token → the CAPTCHA message, no e-mail', array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_captcha_missing'));
$a = $rec($L3['form_id'], $v3(0.9));
t('v3: recovery with a token of the login form\'s action passes', ($a['code'] ?? ''), '');
for ($i = 0; $i < 4; $i++) $rec($L['form_id'], '');
$a = $rec($L['form_id'], '');
t('the 6th recovery request of an address within an hour: efb_recovery_rate', $a['code'] ?? '', 'efb_recovery_rate');
$H->ctlb('rl-reset', 'rcvr');

foreach ($forms as $id) $H->ctl('f12-delete-form', (string) $id);
$H->ctlb('user-del', $user);
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&rc3SiteKey=del&rc3SecretKey=del&rc3Threshold=del&rc3_low_action=del&spam_min_time=0&captcha_provider=del');
$H->ctlb('mock', '0');
efb_done();
