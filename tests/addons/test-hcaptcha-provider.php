<?php
/**
 * F12b-G: the hCaptcha add-on (AdnHCP) on the provider contract 2, standalone (no network; only hCaptcha's
 * documented public test keys).
 *
 *  - registers on a core with contract captcha_provider 2, Free Plus, widget; the key shapes;
 *  - siteverify: api.hcaptcha.com, 8 s, secret + response + sitekey, never remoteip; the test secret passes with its
 *    dummy hostname; another hostname with a real-shaped secret → hostname; expired / duplicate → expired; replay;
 *    timeout and not-JSON closed;
 *  - every option reaches the config (theme, size incl. invisible → executed on submit, language, alignment, text);
 *  - §6 migration of hcSiteKey / hcSecretKey; the adapter under 2 KB; its own text domain and .pot.
 *
 * Run: php tests/addons/test-hcaptcha-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;

$D = efb_addon_dir('hcaptcha');
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
require $D . '/routes-efb.php';
Registry::boot();
$p = Registry::get('hcaptcha');
t('registered: id, add-on, plan, kind', array($p ? $p->id() : null, $p ? $p->addon_key() : null, $p ? $p->plan() : null, $p ? $p->kind() : null), array('hcaptcha', 'AdnHCP', 'free_plus', 'widget'));
$k = $p->test_keys();
t('settings: the test keys are fine, a key with spaces is refused', array($p->sanitize_setting('site_key', $k['site_key']), $p->sanitize_setting('secret', 'has spaces in it')), array($k['site_key'], null));
Admin::save('hcaptcha', array('site_key' => $k['site_key']), array('secret' => $k['secret']));
$v = function ($token, $reply, $ctx = array()) { $GLOBALS['T']['calls'] = array(); $GLOBALS['T']['reply'] = $reply; return Registry::verify('hcaptcha', $token, $ctx); };
$r = $v(efb_tok('a'), efb_ok(array('hostname' => 'dummy-key-pass')));
$c = $GLOBALS['T']['calls'][0];
t('siteverify: api.hcaptcha.com, 8 s, secret + response + sitekey, never remoteip', array($c['url'], $c['args']['timeout'], array_keys($c['args']['body']), $c['args']['body']['sitekey']), array('https://api.hcaptcha.com/siteverify', 8, array('secret', 'response', 'sitekey'), $k['site_key']));
t('the test secret passes with its dummy hostname', $r['ok'], true);
Admin::save('hcaptcha', array(), array('secret' => '0xA1b2C3d4E5f6PlaceholderNotReal7788'));
t('a real-shaped secret: another hostname → hostname, the site\'s passes', array($v(efb_tok('b'), efb_ok(array('hostname' => 'evil.test')))['code'], $v(efb_tok('c'), efb_ok())['ok']), array('hostname', true));
t('expired / already seen / duplicate → expired', array($v(efb_tok('d'), array('body' => '{"success":false,"error-codes":["expired-input-response"]}'))['code'], $v(efb_tok('e'), array('body' => '{"success":false,"error-codes":["already-seen-response"]}'))['code'], $v(efb_tok('f'), array('body' => '{"success":false,"error-codes":["timeout-or-duplicate"]}'))['code']), array('expired', 'expired', 'expired'));
$t1 = efb_tok('g');
$v($t1, efb_ok());
t('replay: the same token in another request', $v($t1, efb_ok())['code'], 'replay');
t('timeout → network; not JSON → invalid_response', array($v(efb_tok('h'), new WP_Error('x'))['code'], $v(efb_tok('i'), array('body' => 'oops'))['code']), array('network', 'invalid_response'));
$w = \Emsfb\Captcha::box_html('hcaptcha', 3, 'form', 1, array('cp_theme' => 'dark', 'cp_size' => 'invisible', 'cp_lang' => 'ar', 'cp_align' => 'center', 'cp_msg_failed' => 'Try again'));
preg_match('/data-efb-captcha="([^"]+)"/', $w, $m);
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
t('options reach the config: theme, size invisible (executed on submit), language, alignment, text', array($cfg['theme'], $cfg['size'], $cfg['ex'], $cfg['lang'], $cfg['align'], $cfg['mf']), array('dark', 'invisible', 1, 'ar', 'center', 'Try again'));
t('the script asks for the page language, explicit render, no reCAPTCHA compatibility', $p->scripts(array('lang' => 'fa'))[0]['src'], 'https://js.hcaptcha.com/1/api.js?render=explicit&hl=fa&recaptchacompat=off');
$GLOBALS['T']['options'] = array();
Registry::forget('hcaptcha');
$GLOBALS['T']['settings'] = array('hcSiteKey' => $k['site_key'], 'hcSecretKey' => $k['secret']);
t('§6: migrate() moves the F12 keys once and empties them in the row', array(\Emsfb\Captcha\Hcaptcha_Provider::migrate(), \Emsfb\Captcha\Hcaptcha_Provider::migrate(), $GLOBALS['T']['settings']['hcSecretKey'], strpos(get_option('emsfb_cpv_hcaptcha')['secret']['secret'], 'enc:v1:') === 0), array(true, false, '', true));
t('the adapter is under 2 KB; text domain efb-hcaptcha with a .pot', array(filesize($D . '/assets/hcaptcha-adapter-efb.js') < 2048, is_file($D . '/languages/efb-hcaptcha.pot')), array(true, true));
efb_done();
