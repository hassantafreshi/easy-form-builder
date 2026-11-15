<?php
/**
 * F12b-F: the Cloudflare Turnstile add-on (AdnCTS) on the provider contract 2, standalone (no network: siteverify is
 * stubbed; only Cloudflare's documented public test keys, never a real key).
 *
 *  - registers through its routes file on a core with contract captcha_provider 2 (nothing on an older core),
 *    announces efb_addon_contracts, Free Plus, widget; its settings schema refuses a key of the wrong shape;
 *  - siteverify: POST, 8 s, secret + response + idempotency_key = request id, never remoteip; Cloudflare's test
 *    secret passes with its dummy hostname; "always fails" test secret refused; timeout-or-duplicate → expired;
 *    hostname, action, token age, replay (a retry of the same request passes), timeout and not-JSON fail closed;
 *  - every form option reaches the box's config (theme, size, appearance execute → ex, language, alignment, action,
 *    retry, the form's failure text); the secret never;
 *  - §6: an F12 site's tsSiteKey / tsSecretKey move into the add-on's option (secret encrypted) and leave the row;
 *  - the adapter is under 2 KB; the phrases have their own text domain and a .pot.
 *
 * Run: php tests/addons/test-turnstile-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;

$D = efb_addon_dir('turnstile');
function efb_addon_contract_versions() { return $GLOBALS['T']['contract'] ?? array('captcha_provider' => array('version' => 2)); }
$GLOBALS['T']['contract'] = array('captcha_provider' => array('version' => 1));
require $D . '/routes-efb.php';
t('a core whose contract is not captcha_provider 2: the add-on does nothing', class_exists('\\Emsfb\\Captcha\\Turnstile_Provider', false), false);
$GLOBALS['T']['contract'] = array('captcha_provider' => array('version' => 2));
require $D . '/routes-efb.php';
Registry::reset();
Registry::boot();
$p = Registry::get('turnstile');
t('registered on efb_register_captcha_providers: id, add-on, plan, kind, contract', array($p ? $p->id() : null, $p ? $p->addon_key() : null, $p ? $p->plan() : null, $p ? $p->kind() : null, apply_filters('efb_addon_contracts', array())['captcha_provider']['AdnCTS'] ?? null), array('turnstile', 'AdnCTS', 'free_plus', 'widget', 2));
t('no registration error', Registry::errors(), array());

$GLOBALS['T']['settings'] = array();
$k = $p->test_keys();
t('settings: a key of the wrong shape is refused, the test keys are fine', array($p->sanitize_setting('site_key', 'not a key!'), $p->sanitize_setting('site_key', $k['site_key']), $p->sanitize_setting('secret', $k['secret'])), array(null, $k['site_key'], $k['secret']));
$r = Admin::save('turnstile', array('site_key' => $k['site_key']), array('secret' => $k['secret']));
$raw = get_option('emsfb_cpv_turnstile');
t('Admin::save: the public key as is, the secret encrypted (enc:v1:), the public map updated', array($r['ok'], $raw['public']['site_key'], strpos($raw['secret']['secret'], 'enc:v1:') === 0, strpos(json_encode($raw), $k['secret']) === false, get_option('emsfb_cpv_public')['turnstile']['site_key'] ?? ''), array(true, $k['site_key'], true, true, $k['site_key']));
t('ready with both keys', $p->ready(), true);

$v = function ($token, $reply, $ctx = array()) { $GLOBALS['T']['calls'] = array(); $GLOBALS['T']['reply'] = $reply; return Registry::verify('turnstile', $token, $ctx); };
$rid = '0b9a3c3e-1f2d-4c5b-9a8e-7d6c5b4a3f21';
$tok = efb_tok('a');
$r = $v($tok, efb_ok(array('hostname' => 'example.com', 'action' => '')), array('request_id' => $rid));
$c = $GLOBALS['T']['calls'][0];
t('siteverify: challenges.cloudflare.com, 8 s, no redirect, secret + response + idempotency_key, never remoteip', array($c['url'], $c['args']['timeout'], $c['args']['redirection'], array_keys($c['args']['body']), $c['args']['body']['idempotency_key']), array('https://challenges.cloudflare.com/turnstile/v0/siteverify', 8, 0, array('secret', 'response', 'idempotency_key'), $rid));
t('the official test secret passes with its dummy hostname and no action', $r['ok'], true);
t('… a network retry of the same request passes the replay guard; another request is a replay', array($v($tok, efb_ok(), array('request_id' => $rid))['ok'], $v($tok, efb_ok(), array('request_id' => '11111111-2222-4333-8444-555555555555'))['code']), array(true, 'replay'));
Admin::save('turnstile', array(), array('secret' => '0x4AAAAAAAplaceholderSecretNotReal00'));
t('a real-shaped secret: another hostname → hostname', $v(efb_tok('b'), efb_ok(array('hostname' => 'evil.test', 'action' => 'efb_submit')))['code'], 'hostname');
t('… another action → action', $v(efb_tok('c'), efb_ok(array('action' => 'login')))['code'], 'action');
t('… the form\'s own action (cp_action) is the one checked', $v(efb_tok('d'), efb_ok(array('action' => 'contact')), array('form' => array('cp_action' => 'contact')))['ok'], true);
t('… a token older than 300 s + 60 → expired', $v(efb_tok('e'), efb_ok(array('action' => 'efb_submit', 'challenge_ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 400))))['code'], 'expired');
t('… timeout-or-duplicate → expired', $v(efb_tok('f'), array('body' => '{"success":false,"error-codes":["timeout-or-duplicate"]}'))['code'], 'expired');
t('… "always fails" (invalid-input-response) → failed', $v(efb_tok('g'), array('body' => '{"success":false,"error-codes":["invalid-input-response"]}'))['code'], 'failed');
t('… a timeout → network, not JSON → invalid_response (closed)', array($v(efb_tok('h'), new WP_Error('http_request_failed'))['code'], $v(efb_tok('i'), array('body' => '<html>'))['code']), array('network', 'invalid_response'));
t('… no token → missing, nothing sent', array($v('', efb_ok())['code'], count($GLOBALS['T']['calls'])), array('missing', 0));

$s0 = array('captcha' => '1', 'captcha_provider' => 'turnstile', 'cp_theme' => 'dark', 'cp_size' => 'compact', 'cp_appearance' => 'execute', 'cp_lang' => 'fa', 'cp_align' => 'end', 'cp_action' => 'contact', 'cp_retry' => 'never', 'cp_msg_failed' => 'دوباره تلاش کنید');
$w = \Emsfb\Captcha::box_html('turnstile', 5, 'form', 1, $s0);
preg_match('/data-efb-captcha="([^"]+)"/', $w, $m);
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
t('every option reaches the config: theme, size, appearance, execute on submit, language, alignment, action, retry, failure text', array($cfg['theme'], $cfg['size'], $cfg['appearance'], $cfg['ex'], $cfg['lang'], $cfg['align'], $cfg['action'], $cfg['retry'], $cfg['mf']), array('dark', 'compact', 'execute', 1, 'fa', 'end', 'contact', 'never', 'دوباره تلاش کنید'));
t('the box is end-aligned (logical: the start side in RTL), the script and adapter are in it, no secret', array(strpos($w, 'align-items:flex-end') !== false, strpos($w, 'turnstile-adapter-efb.js') !== false, strpos($w, 'placeholderSecret') === false), array(true, true, true));
t('options by schema: an unknown value is dropped', array($p->sanitize_form_option('cp_appearance', 'sometimes'), $p->sanitize_form_option('cp_action', 'bad action!'), $p->sanitize_form_option('cp_size', 'flexible')), array('', '', 'flexible'));

echo "--- §6: an F12 site's keys move in ---\n";
$GLOBALS['T']['options'] = array();
Registry::forget('turnstile');
$GLOBALS['T']['settings'] = array('tsSiteKey' => $k['site_key'], 'tsSecretKey' => $k['secret'], 'emailSupporter' => 'owner@example.test');
t('migrate(): true once', array(\Emsfb\Captcha\Turnstile_Provider::migrate(), \Emsfb\Captcha\Turnstile_Provider::migrate()), array(true, false));
$raw = get_option('emsfb_cpv_turnstile');
t('… the option has them (secret encrypted), the row has them no more', array($raw['public']['site_key'], strpos($raw['secret']['secret'], 'enc:v1:') === 0, $GLOBALS['T']['settings']['tsSiteKey'], $GLOBALS['T']['settings']['tsSecretKey']), array($k['site_key'], true, '', ''));
Registry::forget('turnstile');
t('… and the provider is ready with them', $p->ready(), true);

echo "--- files ---\n";
t('the adapter is under 2 KB', filesize($D . '/assets/turnstile-adapter-efb.js') < 2048, true);
t('phrases in the text domain efb-turnstile with a .pot; no locale yet (not translated: owner)', array(strpos(file_get_contents($D . '/turnstile-phrases-efb.php'), "'efb-turnstile'") !== false, is_file($D . '/languages/efb-turnstile.pot'), json_decode(file_get_contents($D . '/languages/locales.json'), true)['locales']), array(true, true, array()));
t('uninstall() removes its settings and its public values', (function () { \Emsfb\Captcha\Turnstile_Provider::uninstall(); return array(get_option('emsfb_cpv_turnstile', null), isset(get_option('emsfb_cpv_public', array())['turnstile'])); })(), array(null, false));
efb_done();
