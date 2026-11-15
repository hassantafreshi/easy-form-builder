<?php
/**
 * F12b-J: the Friendly Captcha add-on (AdnFRC), standalone (siteverify stubbed; placeholder keys, never real).
 *
 *  - registers (widget, Free Plus); keys checked by shape; the API key encrypted in the option;
 *  - siteverify v2: POST JSON {response, sitekey} to global.frcapi.com (eu.frcapi.com with the eu endpoint), the
 *    API key in X-API-Key, 8 s, no redirect; success with this site's origin passes; another origin → hostname;
 *    response_timeout / response_duplicate → expired; auth_invalid → config; others failed; timeout and not-JSON
 *    closed; replay;
 *  - the options (theme, start, language, alignment, endpoint) reach the config; never the API key; the adapter is
 *    under 2 KB; the SDK script is Friendly Captcha's own.
 *
 * Run: php tests/addons/test-friendly-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;

$D = efb_addon_dir('friendly-captcha');
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
require $D . '/routes-efb.php';
Registry::boot();
$p = Registry::get('friendly');
t('registered: widget, Free Plus, not ready without keys', array($p ? $p->kind() : null, $p->plan(), $p->ready()), array('widget', 'free_plus', false));
$api = 'A1B2C3D4E5F6placeholderNotReal';
$r = Admin::save('friendly', array('site_key' => 'FCMPLACEHOLDER0001', 'endpoint' => 'global'), array('api_key' => $api));
t('saved: the API key encrypted, ready', array($r['ok'], strpos(json_encode(get_option('emsfb_cpv_friendly')), $api) === false, $p->ready()), array(true, true, true));
t('a key of the wrong shape is refused', Admin::save('friendly', array('site_key' => 'has spaces!'), array())['refused'], array('site_key'));
$ok = function ($origin = 'https://www.site.test', $ts = null) { return array('body' => json_encode(array('success' => true, 'data' => array('event_id' => 'ev_1', 'challenge' => array('timestamp' => gmdate('c', $ts ?: time()), 'origin' => $origin))))); };
$v = function ($token, $reply, $ctx = array()) { $GLOBALS['T']['calls'] = array(); $GLOBALS['T']['reply'] = $reply; return Registry::verify('friendly', $token, $ctx); };
$res = $v(efb_tok('a'), $ok());
$c = $GLOBALS['T']['calls'][0];
$body = json_decode($c['args']['body'], true);
t('siteverify v2: global.frcapi.com, JSON body {response, sitekey}, X-API-Key, 8 s, no redirect; passes', array($c['url'], array_keys($body), $body['sitekey'], $c['args']['headers']['X-API-Key'], $c['args']['headers']['Content-Type'], $c['args']['timeout'], $c['args']['redirection'], $res['ok']),
	array('https://global.frcapi.com/api/v2/captcha/siteverify', array('response', 'sitekey'), 'FCMPLACEHOLDER0001', $api, 'application/json', 8, 0, true));
t('another origin → hostname', $v(efb_tok('b'), $ok('https://evil.test'))['code'], 'hostname');
$err = function ($code) { return array('body' => json_encode(array('success' => false, 'error' => array('error_code' => $code, 'detail' => 'x')))); };
t('response_timeout / response_duplicate → expired; auth_invalid → config; response_invalid → failed', array($v(efb_tok('c'), $err('response_timeout'))['code'], $v(efb_tok('d'), $err('response_duplicate'))['code'], $v(efb_tok('e'), $err('auth_invalid'))['code'], $v(efb_tok('f'), $err('response_invalid'))['code']), array('expired', 'expired', 'config', 'failed'));
t('a timeout → network; not JSON → invalid_response', array($v(efb_tok('g'), new WP_Error('x'))['code'], $v(efb_tok('h'), array('body' => '<html>'))['code']), array('network', 'invalid_response'));
t('a challenge older than 10 min + 60 s → expired', $v(efb_tok('i'), $ok('https://www.site.test', time() - 700))['code'], 'expired');
$t1 = efb_tok('j');
$v($t1, $ok());
t('replay', $v($t1, $ok())['code'], 'replay');
Admin::save('friendly', array('endpoint' => 'eu'), array());
Registry::forget('friendly');
$v(efb_tok('k'), $ok());
t('the eu endpoint: eu.frcapi.com', $GLOBALS['T']['calls'][0]['url'], 'https://eu.frcapi.com/api/v2/captcha/siteverify');
$w = \Emsfb\Captcha::box_html('friendly', 2, 'form', 1, array('cp_theme' => 'dark', 'cp_start' => 'none', 'cp_lang' => 'fa', 'cp_align' => 'end'));
preg_match('/data-efb-captcha="([^"]+)"/', $w, $mm);
$cfg = json_decode(html_entity_decode($mm[1], ENT_QUOTES), true);
t('options reach the config (theme, start, language, alignment, endpoint); never the API key', array($cfg['theme'], $cfg['start'], $cfg['lang'], $cfg['align'], $cfg['api'], strpos($w, $api) === false), array('dark', 'none', 'fa', 'end', 'eu', true));
t('the SDK is Friendly Captcha\'s own script; the adapter is under 2 KB', array(strpos($p->scripts(array())[0]['src'], 'https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@') === 0, filesize($D . '/assets/friendly-adapter-efb.js') < 2048), array(true, true));
efb_done();
