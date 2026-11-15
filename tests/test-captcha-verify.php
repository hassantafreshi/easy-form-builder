<?php
/**
 * F12 (spec §3, C, D) and P20: the CAPTCHA services' server check, Emsfb\Captcha::verify() and the public contract
 * Emsfb\Spam::verify_captcha() / efb_captcha_verify(), with every provider answer mocked (never the network, never a
 * real key: the official test keys of hCaptcha and Turnstile are public placeholders, documented by their vendors).
 *
 * Part 1 (standalone, wp_remote_post stubbed):
 *  - the request: POST to the provider's siteverify, 8-second timeout, secret + response, hCaptcha's sitekey,
 *    Turnstile's idempotency_key (the request id: a network retry does not spend the token twice), never remoteip;
 *  - fails closed: no secret (config), no / short token (missing, nothing sent), network error, an answer that is not
 *    JSON, success false, "timeout-or-duplicate" (expired);
 *  - hostname and action checked (v3, hCaptcha, Turnstile; reCAPTCHA v2 as in 4.3.1: not), skipped for the official
 *    test secrets (their answers carry a dummy hostname); efb_captcha_check_hostname turns the hostname check off;
 *  - reCAPTCHA v3: the score; under the threshold "spam" (low_score soft) or refused (block / outside a form);
 *  - the provider of a form: its own, else the site's, else v2; site keys are public, secrets never in widget markup;
 *  - one Google script for v2 and v3 (google_url), the widget language (fa, ar, pt-BR …).
 * Part 2 (the e2e site, when EFB_E2E_SITE_DIR is set; mu-plugin efb-e2e-f12.php answers siteverify): a form with
 *  hCaptcha / Turnstile / v3 sent with pass-, fail-, dup-, host-, act-, net-, bad-, low- tokens over HTTP.
 *
 * Run: php tests/test-captcha-verify.php   (part 2 under the e2e lock with the e2e env)
 */
define('ABSPATH', __DIR__ . '/');
class WP_Error { public $c; public function __construct($c = '', $m = '') { $this->c = $c; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
$GLOBALS['efb_t'] = array('calls' => array(), 'reply' => null, 'filters' => array(), 'settings' => array());
function wp_remote_post($url, $args) { $GLOBALS['efb_t']['calls'][] = array('url' => $url, 'args' => $args); $r = $GLOBALS['efb_t']['reply']; return is_callable($r) ? $r($url, $args) : $r; }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['efb_t']['filters'][$h]) ? call_user_func_array($GLOBALS['efb_t']['filters'][$h], array_slice($a, 1)) : $v; }
function do_action() {}
function home_url() { return 'https://www.site.test'; }
function get_setting_Emsfb($m = 'decoded') { return json_decode(json_encode($GLOBALS['efb_t']['settings'])); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function get_locale() { return $GLOBALS['efb_t']['locale'] ?? 'en_US'; }
require __DIR__ . '/../includes/class-Emsfb-captcha.php';
require __DIR__ . '/../includes/class-Emsfb-spam.php';
use Emsfb\Captcha;
use Emsfb\Spam;

$pass = 0; $fail = 0;
function t($name, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] {$name}\n"; }
	else { $fail++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected) . "\n  actual:   " . json_encode($actual) . "\n"; }
}
$ok = function ($extra = array()) { return array('body' => json_encode(array_merge(array('success' => true, 'hostname' => 'site.test', 'challenge_ts' => '2026-11-14T10:00:00Z'), $extra))); };
$S = array(
	'siteKey' => 'v2-site-key-placeholder', 'secretKey' => 'v2-secret-placeholder',
	'rc3SiteKey' => 'v3-site-key-placeholder', 'rc3SecretKey' => 'v3-secret-placeholder', 'rc3Threshold' => '0.5',
	'hcSiteKey' => 'hc-site-key-placeholder', 'hcSecretKey' => 'hc-secret-placeholder',
	'tsSiteKey' => '0x4AAAAAAA-site-placeholder', 'tsSecretKey' => '0x4AAAAAAA-secret-placeholder',
);
$GLOBALS['efb_t']['settings'] = $S;
$tok = str_repeat('t', 40);
$v = function ($p, $token, $reply, $ctx = array()) { $GLOBALS['efb_t']['calls'] = array(); $GLOBALS['efb_t']['reply'] = $reply; return Captcha::verify($p, $token, $ctx); };

echo "--- the request ---\n";
$r = $v('hcaptcha', $tok, $ok());
$c = $GLOBALS['efb_t']['calls'][0];
t('hCaptcha: POST to api.hcaptcha.com/siteverify, 8 s, secret + response + sitekey, no remoteip', array($c['url'], $c['args']['timeout'], $c['args']['body']['secret'], $c['args']['body']['response'], $c['args']['body']['sitekey'], isset($c['args']['body']['remoteip']), $r['ok']), array('https://api.hcaptcha.com/siteverify', 8, 'hc-secret-placeholder', $tok, 'hc-site-key-placeholder', false, true));
$rid = '0b9a3c3e-1f2d-4c5b-9a8e-7d6c5b4a3f21';
$r = $v('turnstile', $tok, $ok(array('action' => 'efb_submit')), array('idempotency_key' => $rid));
$c = $GLOBALS['efb_t']['calls'][0];
t('Turnstile: challenges.cloudflare.com siteverify with idempotency_key = the request id', array($c['url'], $c['args']['body']['idempotency_key'], $r['ok']), array('https://challenges.cloudflare.com/turnstile/v0/siteverify', $rid, true));
$v('turnstile', $tok, $ok(), array('idempotency_key' => 'not-a-uuid'));
t('Turnstile: an idempotency key that is not a UUID is not sent', isset($GLOBALS['efb_t']['calls'][0]['args']['body']['idempotency_key']), false);
$r = $v('recaptcha_v2', $tok, $ok());
t('reCAPTCHA v2: Google siteverify, no hostname / action check (4.3.1)', array($GLOBALS['efb_t']['calls'][0]['url'], $r['ok']), array('https://www.google.com/recaptcha/api/siteverify', true));
$r = $v('recaptcha_v2', $tok, $ok(array('hostname' => 'elsewhere.test')));
t('reCAPTCHA v2: another hostname still passes (as before)', $r['ok'], true);

echo "--- fails closed ---\n";
$GLOBALS['efb_t']['settings'] = array_merge($S, array('hcSecretKey' => ''));
t('no secret → config, nothing sent', array($v('hcaptcha', $tok, $ok())['code'], count($GLOBALS['efb_t']['calls'])), array('config', 0));
$GLOBALS['efb_t']['settings'] = $S;
t('no token → missing, nothing sent', array($v('turnstile', '', $ok())['code'], count($GLOBALS['efb_t']['calls'])), array('missing', 0));
t('a short token → missing', $v('turnstile', 'abc', $ok())['code'], 'missing');
t('a network error (timeout) → network', $v('hcaptcha', $tok, new WP_Error('http_request_failed'))['code'], 'network');
t('an answer that is not JSON → invalid_response', $v('hcaptcha', $tok, array('body' => '<html>502</html>'))['code'], 'invalid_response');
t('success false → failed, the provider\'s codes kept', $v('hcaptcha', $tok, array('body' => '{"success":false,"error-codes":["invalid-input-response"]}')), array('ok' => false, 'code' => 'failed', 'score' => null, 'codes' => array('invalid-input-response'), 'provider' => 'hcaptcha'));
t('"timeout-or-duplicate" (a spent token) → expired', $v('turnstile', $tok, array('body' => '{"success":false,"error-codes":["timeout-or-duplicate"]}'))['code'], 'expired');
t('"success":"true" as a string is not success', $v('hcaptcha', $tok, array('body' => '{"success":"true"}'))['ok'], false);

echo "--- hostname and action ---\n";
t('Turnstile: another hostname → hostname', $v('turnstile', $tok, $ok(array('hostname' => 'evil.test', 'action' => 'efb_submit')))['code'], 'hostname');
t('hCaptcha: another hostname → hostname', $v('hcaptcha', $tok, $ok(array('hostname' => 'evil.test')))['code'], 'hostname');
t('www. is the same host', $v('hcaptcha', $tok, $ok(array('hostname' => 'www.site.test')))['ok'], true);
t('Turnstile: another action → action', $v('turnstile', $tok, $ok(array('action' => 'login')))['code'], 'action');
t('v3: another action → action', $v('recaptcha_v3', $tok, $ok(array('action' => 'homepage', 'score' => 0.9)))['code'], 'action');
$GLOBALS['efb_t']['filters']['efb_captcha_check_hostname'] = function () { return false; };
t('efb_captcha_check_hostname false (several domains): another hostname passes', $v('hcaptcha', $tok, $ok(array('hostname' => 'evil.test')))['ok'], true);
unset($GLOBALS['efb_t']['filters']['efb_captcha_check_hostname']);
$GLOBALS['efb_t']['settings'] = array_merge($S, array('tsSecretKey' => '1x0000000000000000000000000000000AA', 'hcSecretKey' => '0x0000000000000000000000000000000000000000'));
t('official test secrets: their dummy hostname / empty action pass (spec C acceptance)', array($v('turnstile', $tok, $ok(array('hostname' => 'example.com', 'action' => '')))['ok'], $v('hcaptcha', $tok, $ok(array('hostname' => 'dummy-key-pass')))['ok']), array(true, true));
t('official test secrets recognised; a real-looking key is not', array(Captcha::is_test_secret('turnstile', '2x0000000000000000000000000000000AA'), Captcha::is_test_secret('turnstile', '0x4AAAAAAA-secret-placeholder')), array(true, false));
$GLOBALS['efb_t']['settings'] = $S;

echo "--- reCAPTCHA v3 score ---\n";
$r = $v('recaptcha_v3', $tok, $ok(array('action' => 'efb_submit', 'score' => 0.9)));
t('v3: 0.9 passes, the score is returned', array($r['ok'], $r['score']), array(true, 0.9));
t('v3: an answer without a score → invalid_response', $v('recaptcha_v3', $tok, $ok(array('action' => 'efb_submit')))['code'], 'invalid_response');
$GLOBALS['efb_t']['reply'] = $ok(array('action' => 'efb_submit', 'score' => 0.3));
$form = array('type' => 'form', 'captcha' => '1', 'captcha_provider' => 'recaptcha_v3');
$q = Spam::verify_captcha($tok, 'form', array('form' => $form));
t('v3 in a form: 0.3 with threshold 0.5 → ok but spam (low_score soft, the default)', array($q['ok'], $q['spam'], $q['score']), array(true, true, 0.3));
$q = Spam::verify_captcha($tok, 'form', array('form' => $form, 'low_score' => 'block'));
t('v3 in a form with rc3_low_action block → refused low_score', array($q['ok'], $q['code']), array(false, 'low_score'));
$q = Spam::verify_captcha($tok, 'form', array('form' => array_merge($form, array('rc3_threshold' => '0.2'))));
t('the form\'s own rc3_threshold 0.2: 0.3 passes clean', array($q['ok'], $q['spam']), array(true, false));
$GLOBALS['efb_t']['settings'] = array_merge($S, array('captcha_provider' => 'recaptcha_v3'));
$q = Spam::verify_captcha($tok, 'portal_signin');
t('P20 outside a form (portal_signin): a low score is refused, never "spam"', array($q['ok'], $q['code'], $q['provider']), array(false, 'low_score', 'recaptcha_v3'));
$GLOBALS['efb_t']['reply'] = $ok(array('action' => 'efb_submit', 'score' => 0.8));
t('P20 outside a form: a good score passes', Spam::verify_captcha($tok, 'portal_signin')['ok'], true);
$GLOBALS['efb_t']['filters']['efb_captcha_verify_result'] = function ($r, $ctx) { if ($ctx === 'tracker') { $r['ok'] = false; $r['code'] = 'site_rule'; } return $r; };
t('P20: efb_captcha_verify_result may change the answer for a context', Spam::verify_captcha($tok, 'tracker')['code'], 'site_rule');
unset($GLOBALS['efb_t']['filters']['efb_captcha_verify_result']);
$GLOBALS['efb_t']['settings'] = $S;

echo "--- provider of a form, keys, markup ---\n";
// F12b §5: an id that is not registered (an add-on switched off) is kept as asked; what protects the form is effective_for().
t('provider: the form\'s own, else the site\'s, else reCAPTCHA v2; an unknown id is kept as asked, a malformed one is not', array(Captcha::provider_for(array('captcha_provider' => 'hcaptcha')), Captcha::provider_for(array()), Captcha::provider_for((object) array('captcha_provider' => 'evil')), Captcha::provider_for(array('captcha_provider' => 'Bad Id!'))), array('hcaptcha', 'recaptcha_v2', 'evil', 'recaptcha_v2'));
t('effective provider of an unknown id: the site\'s Google mode that is ready (v2 here)', \Emsfb\Captcha\Registry::effective_for(array('captcha' => 1, 'captcha_provider' => 'evil'), $S)['id'], 'recaptcha_v2');
$GLOBALS['efb_t']['settings'] = array_merge($S, array('captcha_provider' => 'turnstile'));
t('provider: the site\'s when the form has none', Captcha::provider_for(array('captcha_provider' => '')), 'turnstile');
$GLOBALS['efb_t']['settings'] = $S;
t('ready map: a provider without both keys is not ready', Captcha::ready_map(array('siteKey' => 'v2-site-key-placeholder', 'secretKey' => '', 'hcSiteKey' => 'hc-site-key-placeholder', 'hcSecretKey' => 'hc-secret-placeholder')), array('recaptcha_v2' => false, 'recaptcha_v2i' => false, 'recaptcha_v3' => false, 'hcaptcha' => true, 'turnstile' => false));
$w = Captcha::widget_html('hcaptcha', 9, 'form', 2);
t('widget: the site key, the provider, the form, a label for screen readers and a live region; never the secret', strpos($w, 'data-sitekey="hc-site-key-placeholder"') !== false && strpos($w, 'data-provider="hcaptcha"') !== false && strpos($w, 'data-formid="9"') !== false
	&& (bool) preg_match('/id="(efb-cp-9-\\d+)"[^>]*aria-labelledby="\\1-l"/', $w) && strpos($w, 'role="alert" aria-live="assertive"') !== false && strpos($w, 'secret') === false, true);
$w2 = Captcha::widget_html('recaptcha_v2', 9, 'form', 2);
// F12b-C: no #gRecaptcha / recaptcha-message-{fid} any more (two forms on a page repeated them): .g-recaptcha and data-efb-legacy stay for old CSS.
t('widget v2: .g-recaptcha with data-efb-legacy="gRecaptcha", a unique id, label and live region; no #gRecaptcha', strpos($w2, 'class="efb efb-captcha g-recaptcha') !== false && strpos($w2, 'data-efb-legacy="gRecaptcha"') !== false && strpos($w2, 'id="gRecaptcha"') === false && strpos($w2, 'efb-sr-only') !== false, true);
$w3 = Captcha::widget_html('recaptcha_v3', 9);
t('widget v3: no widget, a hidden marker and Google\'s notice with its links', strpos($w3, 'data-provider="recaptcha_v3"') !== false && strpos($w3, ' hidden></div>') !== false && strpos($w3, 'https://policies.google.com/privacy') !== false && strpos($w3, 'https://policies.google.com/terms') !== false, true);
$w4 = Captcha::widget_html('turnstile', 0, 'portal_signin');
t('P20 widget outside a form: data-formid -1, data-ctx, its own ids', strpos($w4, 'data-formid="-1"') !== false && strpos($w4, 'data-ctx="portal_signin"') !== false && (bool) preg_match('/id="efb-cp-portal_signin-\\d+"/', $w4), true);
t('no site key → no widget', Captcha::widget_html('turnstile', 1, 'form', 0, array('tsSiteKey' => '')), '');

echo "--- scripts and language ---\n";
t('one Google script: v3 key wins (v2 widgets render on it), else explicit', array(Captcha::google_url('v3key', 'fa'), Captcha::google_url('', 'ar')), array('https://www.google.com/recaptcha/api.js?hl=fa&render=v3key', 'https://www.google.com/recaptcha/api.js?hl=ar&render=explicit'));
t('hCaptcha: explicit render, the page language, no reCAPTCHA compatibility', Captcha::provider_url('hcaptcha', 'fa'), 'https://js.hcaptcha.com/1/api.js?render=explicit&hl=fa&recaptchacompat=off');
t('widget language: fa_IR → fa, ar → ar, pt_BR → pt-BR, zh_CN → zh-CN, de_DE_formal → de', array(Captcha::lang('fa_IR'), Captcha::lang('ar'), Captcha::lang('pt_BR'), Captcha::lang('zh_CN'), Captcha::lang('de_DE_formal')), array('fa', 'ar', 'pt-BR', 'zh-CN', 'de'));

/* ------------------------------------------------------------------ part 2: the e2e site */
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) {
	echo "[SKIP] site part: {$why}\n";
} else {
	echo "--- the e2e site (mocked providers) ---\n";
	$H->ctl('plan', '3');
	$H->ctl('f12-mu');
	$H->ctl('set-option', 'efb_e2e_f12_mock', '1');
	$H->ctl('f12-setting', 'hcSiteKey=10000000-ffff-ffff-ffff-000000000001&hcSecretKey=0x0000000000000000000000000000000000000000&tsSiteKey=1x00000000000000000000AA&tsSecretKey=1x0000000000000000000000000000000AA&rc3SiteKey=e2e-v3-site-placeholder&rc3SecretKey=e2e-v3-secret-placeholder&rc3Threshold=0.5&rc3_low_action=spam&spam_min_time=0&spam_honeypot=del');
	$mk = function ($prov) use ($H) { return $H->ctl('f03-form', 'F12 ' . $prov, json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1&captcha_provider=' . $prov); };
	// F12b: a token is accepted once (replay guard): every mocked token gets a suffix of its own.
	$send = function ($F, $token) use ($H) { $H->fresh_jar(); return EfbF11Http::answer($H->submit((int) $F['form_id'], array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'Visitor')), array('valid' => $token !== '' ? $token . bin2hex(random_bytes(6)) : ''))); };
	$made = array();
	foreach (array('hcaptcha', 'turnstile') as $prov) {
		$F = $mk($prov); $made[] = $F['form_id'];
		t("site {$prov}: the stored form keeps captcha_provider", ($H->ctl('form', (string) $F['form_id'])['form_structer'][0]['captcha_provider'] ?? ''), $prov);
		t("site {$prov}: a solved token is accepted", !empty($send($F, 'pass-' . str_repeat('a', 30))['success']), true);
		$a = $send($F, 'fail-' . str_repeat('a', 30));
		t("site {$prov}: a refused token → errorCaptcha (efb_captcha_failed), nothing stored", array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_captcha_failed'));
		t("site {$prov}: a spent token → efb_captcha_expired with the \"expired\" text", ($send($F, 'dup-' . str_repeat('a', 30))['code'] ?? ''), 'efb_captcha_expired');
		t("site {$prov}: the provider unreachable → refused (efb_captcha_network)", ($send($F, 'net-' . str_repeat('a', 30))['code'] ?? ''), 'efb_captcha_network');
		t("site {$prov}: an answer that is not JSON → refused", ($send($F, 'bad-' . str_repeat('a', 30))['code'] ?? ''), 'efb_captcha_invalid_response');
		t("site {$prov}: no token → refused", ($send($F, '')['code'] ?? ''), 'efb_captcha_missing');
		$e = $H->ctl('f14-entries', (string) $F['form_id']);
		t("site {$prov}: one entry (the solved one)", is_array($e) ? count($e) : -1, 1);
	}
	$H->ctl('f12-calls', 'clear');
	$F = $mk('turnstile'); $made[] = $F['form_id'];
	$send($F, 'pass-' . str_repeat('b', 30));
	$calls = $H->ctl('f12-calls');
	$last = is_array($calls) && $calls ? end($calls) : array();
	t('site Turnstile: the request id is the idempotency key; no remote IP; the secret never recorded', array(isset($last['idempotency_key']) && (bool) preg_match('/^[a-f0-9-]{36}$/', $last['idempotency_key']), in_array('remoteip', $last['keys'] ?? array(), true), in_array('secret', $last['keys'] ?? array(), true), strpos(json_encode($calls), '0000000000000000000000000AA') === false), array(true, false, true, true));
	$V = $mk('recaptcha_v3'); $made[] = $V['form_id'];
	t('site v3: a good score is stored as usual', (int) (($H->ctl('f14-entries', (string) $V['form_id']) ?: array())[0]['entry_status'] ?? -1) === -1 && !empty($send($V, 'pass-' . str_repeat('c', 30))['success']), true);
	$a = $send($V, 'low-' . str_repeat('c', 30));
	$e = $H->ctl('f14-entries', (string) $V['form_id']);
	usort($e, function ($x, $y) { return (int) $y['msg_id'] - (int) $x['msg_id']; });
	$lg = $H->ctl('f14-log', (string) $e[0]['msg_id']);
	t('site v3: score 0.3 under 0.5 → the usual success, stored as spam with reason low_score', array(!empty($a['success']), (int) $e[0]['entry_status'], is_array($lg) && $lg ? $lg[count($lg) - 1]['meta'] : ''), array(true, 1, 'low_score'));
	t('site v3: another action → refused', ($send($V, 'act-' . str_repeat('c', 30))['code'] ?? ''), 'efb_captcha_action');
	t('site v3: another hostname → refused', ($send($V, 'host-' . str_repeat('c', 30))['code'] ?? ''), 'efb_captcha_hostname');
	$H->ctl('f12-setting', 'rc3_low_action=block');
	t('site v3: rc3_low_action block → a low score is refused', ($send($V, 'low-' . str_repeat('c', 30))['code'] ?? ''), 'efb_captcha_low_score');
	$P = $mk('hcaptcha'); $made[] = $P['form_id'];
	$H->ctl('f12-setting', 'hcSecretKey=');
	// F12b §5: the form's service has no secret → the site's Google mode that is ready (v3 here) protects it; never unprotected.
	$H->ctl('f12-setting', 'rc3_low_action=spam');
	t('site: the form\'s service has no secret → protected by the ready Google mode (a Google token passes, a bad one does not)', array(!empty($send($P, 'pass-' . str_repeat('d', 30))['success']), ($send($P, 'fail-' . str_repeat('d', 30))['code'] ?? '')), array(true, 'efb_captcha_failed'));
	$page = $H->http('GET', $H->page((int) $V['form_id']), null, array(), false);
	t('site: the page of a v3 form has the site key and Google\'s notice, never a secret', strpos($page['body'], 'e2e-v3-site-placeholder') !== false && strpos($page['body'], 'policies.google.com/privacy') !== false && strpos($page['body'], 'e2e-v3-secret-placeholder') === false && strpos($page['body'], '0x0000000000000000000000000000000000000000') === false, true);
	foreach ($made as $id) $H->ctl('f12-delete-form', (string) $id);
	$H->ctl('f12-setting', 'hcSiteKey=del&hcSecretKey=del&tsSiteKey=del&tsSecretKey=del&rc3SiteKey=del&rc3SecretKey=del&rc3Threshold=del&rc3_low_action=del&spam_min_time=del');
	$H->ctl('set-option', 'efb_e2e_f12_mock', 'del');
}

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
