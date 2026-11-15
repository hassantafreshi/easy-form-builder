<?php
/**
 * F12b-E (§5, §6) on the real WordPress: a form whose CAPTCHA service cannot be used now is never left unprotected,
 * never hidden, and its stored choice is never changed.
 *
 *  - Turnstile add-on on with keys: the page draws Turnstile; switched off: the page draws the site's Google v2 box,
 *    an administrator reads why above the form, a visitor does not; the stored form still says turnstile; a Google
 *    token is accepted; switched on again: Turnstile again;
 *  - no Google keys either: no box, the hidden field and the minimum time are forced on for that form (a bot sending
 *    at once is spam, a person waiting 3 s is stored), forms/{id}/schema says fallback soft_checks;
 *  - plan down to Free (the add-on is Free Plus and Pro): the same fallback; back to Free Plus: Turnstile;
 *  - §6 migration: an F12 site's tsSiteKey / tsSecretKey in the settings row move into the add-on's settings (F12b-L: captcha_turnstile; the
 *    secret encrypted) when the add-on loads, and leave the row; the form works with Turnstile at once;
 *  - an add-on whose contract is older or newer than core's is refused (Captcha\Registry errors) — unit side in
 *    tests/test-captcha-registry.php.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-captcha-fallback.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctlb('mu');
$H->ctlb('mock', '1');
$H->ctlb('rl-reset', 'cptk');
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&captcha_provider=recaptcha_v2&spam_min_time=0&spam_honeypot=del');
$H->ctl('addon-flag', 'AdnCTS', '1');
$H->ctlb('cpv', 'turnstile', json_encode(array('site_key' => '1x00000000000000000000AA')), json_encode(array('secret' => '1x0000000000000000000000000000000AA')));
$F = $H->ctl('f03-form', 'F12b fallback', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1&captcha_provider=turnstile');
$fid = (int) $F['form_id'];
$page = function ($admin = false) use ($H, $fid) { if (!$admin) $H->fresh_jar(); return $H->http('GET', $H->page($fid), null, array(), false)['body']; };
$send = function ($token, $hp = '', $wait = 0) use ($H, $F, $fid) {
	$H->fresh_jar();
	$prep = $H->prepare($fid);
	if ($wait) sleep($wait);
	return EfbF11Http::answer($H->submit($fid, array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'x')), array('valid' => $token, 'efb_hp' => $hp), $prep));
};
$tsTok = function () { return EfbF12bHttp::tok(array('action' => 'efb_submit')); };
$prov = function ($b) { return preg_match('/data-provider="([a-z0-9_]+)"/', $b, $m) ? $m[1] : ''; };

$b = $page();
t('add-on on: the page draws Turnstile (its script from challenges.cloudflare.com and the adapter)', array($prov($b), strpos($b, 'challenges.cloudflare.com/turnstile/v0/api.js') !== false, strpos($b, 'vendor/turnstile/assets/turnstile-adapter-efb.js') !== false), array('turnstile', true, true));
t('… a Turnstile token is accepted', !empty($send($tsTok())['success']), true);

$H->ctl('addon-flag', 'AdnCTS', '0');
$b = $page();
t('add-on off: the form is still shown, protected by the site\'s Google v2 box', array($prov($b), strpos($b, 'e2e-v2-site-placeholder') !== false, strpos($b, 'challenges.cloudflare.com') === false), array('recaptcha_v2', true, true));
t('… a visitor does not see the administrator\'s note', strpos($b, 'efb-cp-admin-note') === false, true);
$H->admin();
$ba = $page(true);
t('… a signed-in administrator does', strpos($ba, 'efb-cp-admin-note') !== false, true);
$H->fresh_jar();
t('… the stored form still asks for turnstile (re-activation restores it)', $H->ctl('form', (string) $fid)['form_structer'][0]['captcha_provider'] ?? '', 'turnstile');
t('… a Google token is accepted; a bare request is refused', array(!empty($send(EfbF12bHttp::tok())['success']), $send('')['code'] ?? ''), array(true, 'efb_captcha_missing'));
$H->ctl('addon-flag', 'AdnCTS', '1');
t('add-on on again: Turnstile again', $prov($page()), 'turnstile');

echo "--- no Google keys either: soft checks forced ---\n";
$H->ctl('addon-flag', 'AdnCTS', '0');
$H->ctl('f12-setting', 'siteKey=del&secretKey=del');
$b = $page();
t('no box, but the hidden field is on the page (forced; the site never set it)', array($prov($b), strpos($b, 'efb-hp-' . $fid) !== false), array('', true));
$a = $send('', 'http://bot.example');
$e = $H->ctl('f14-entries', (string) $fid);
usort($e, function ($x, $y) { return (int) $y['msg_id'] - (int) $x['msg_id']; });
t('a bot filling the hidden field: stored as spam (never unprotected)', array(!empty($a['success']), (int) ($e[0]['entry_status'] ?? -1)), array(true, 1));
$a = $send('', '', 0);
$e = $H->ctl('f14-entries', (string) $fid);
usort($e, function ($x, $y) { return (int) $y['msg_id'] - (int) $x['msg_id']; });
t('sent at once (under the forced 3 s): spam (too_fast)', (int) ($e[0]['entry_status'] ?? -1), 1);
$a = $send('', '', 4);
$e = $H->ctl('f14-entries', (string) $fid);
usort($e, function ($x, $y) { return (int) $y['msg_id'] - (int) $x['msg_id']; });
t('a person after 4 s: stored as usual', array(!empty($a['success']), (int) ($e[0]['entry_status'] ?? -1)), array(true, 0));
$nonce = $H->admin();
$sc = $H->http('GET', '/wp-json/Emsfb/v1/forms/' . $fid . '/schema', null, array('X-WP-Nonce' => $nonce), false)['json'];
$cp = $sc['spam']['captcha'] ?? array();
t('forms/{id}/schema: asked turnstile, nothing usable, fallback soft_checks, why addon', array($cp['provider'] ?? null, array_key_exists('effective_provider', $cp) ? $cp['effective_provider'] : 'missing', $cp['fallback'] ?? null, $cp['why'] ?? null), array('turnstile', null, 'soft_checks', 'addon'));
$H->fresh_jar();

echo "--- plan ---\n";
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder');
$H->ctl('addon-flag', 'AdnCTS', '1');
$H->ctl('plan', '2');
t('plan Free (the add-on is Free Plus and Pro): Google instead', $prov($page()), 'recaptcha_v2');
$H->ctl('plan', '3');
t('back to Free Plus: Turnstile', $prov($page()), 'turnstile');

echo "--- §6: an F12 site's Turnstile keys move into the add-on ---\n";
$H->ctlb('row-set', 'captcha_turnstile', 'null'); // F12b-L: the add-on has no settings yet (its key in the row)
$H->ctl('f12-setting', 'tsSiteKey=1x00000000000000000000AA&tsSecretKey=1x0000000000000000000000000000000AA');
$b = $page();
$raw = $H->ctlb('cpv-raw', 'turnstile');
t('the add-on loaded: its settings (captcha_turnstile of the row) have the site key and the secret, encrypted', array($raw['exists'], $raw['public']['site_key'] ?? '', $raw['secret']['secret']['encrypted'] ?? false), array(true, '1x00000000000000000000AA', true));
$row = (array) $H->ctlb('settings-redacted');
t('… and the keys left the settings row (emptied)', array($row['tsSiteKey'] ?? '', $row['tsSecretKey'] ?? ''), array('', ''));
t('… the form draws Turnstile', $prov($page()), 'turnstile');

$H->ctl('f12-delete-form', (string) $fid);
$H->ctl('addon-flag', 'AdnCTS', '0');
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&captcha_provider=del');
$H->ctlb('mock', '0');
efb_done();
