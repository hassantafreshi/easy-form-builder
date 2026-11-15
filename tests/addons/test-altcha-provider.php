<?php
/**
 * F12b-H: the ALTCHA add-on (AdnALT), standalone. Nothing leaves the site: no wp_remote_post at all.
 *
 *  - registers (kind endpoint, Free Plus), ready without any key: the signing key is made on first use and kept
 *    encrypted; the challenge route is public, no-store, 60 a minute per address (the 61st: 429);
 *  - a challenge: SHA-256, a salt with its expiry (20 minutes by default), the signature; a form may ask for more work
 *    than the site's difficulty, never for less (the URL is the visitor's);
 *  - verify: a solved payload passes; a tampered signature, a wrong number, another algorithm, garbage fail; an
 *    expired one says expired; the same payload again is a replay (core's guard); still no remote request;
 *  - the box carries the challenge URL, never a key; the options (start, difficulty, footer, floating, alignment,
 *    texts) reach the config; the adapter is under 2 KB.
 *
 * Run: php tests/addons/test-altcha-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;

$D = efb_addon_dir('altcha');
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
function rest_url($p = '') { return 'https://www.site.test/wp-json/' . $p; }
function nocache_headers() {}
class WP_REST_Response { public $data; public $status; public $headers = array(); public function __construct($d, $s) { $this->data = $d; $this->status = $s; } public function header($k, $v) { $this->headers[$k] = $v; } }
class Efb_Req { public $p; public function __construct($p) { $this->p = $p; } public function get_param($k) { return $this->p[$k] ?? null; } }
require $D . '/routes-efb.php';
Registry::boot();
$p = Registry::get('altcha');
t('registered: endpoint kind, Free Plus, ready without any key', array($p ? $p->kind() : null, $p ? $p->plan() : null, $p ? $p->ready() : null), array('endpoint', 'free_plus', true));
$k = \Emsfb\Captcha\Altcha_Provider::key();
$raw = get_option('emsfb_cpv_altcha');
t('the signing key is made on first use: 48 characters, kept encrypted, the same next time', array(strlen($k), strpos($raw['secret']['hmac_key'], 'enc:v1:') === 0, strpos(json_encode($raw), $k) === false, \Emsfb\Captcha\Altcha_Provider::key() === $k), array(48, true, true, true));
Registry::rest();
t('the challenge route is registered (public)', array(isset($GLOBALS['T']['routes']['Emsfb/v1/captcha/altcha/challenge']), $GLOBALS['T']['routes']['Emsfb/v1/captcha/altcha/challenge']['permission_callback'] ?? ''), array(true, '__return_true'));
$ch = $p->challenge();
t('a challenge: SHA-256, 100000 by default, the expiry in the salt (20 min), a signature', array($ch['algorithm'], $ch['maxnumber'], (bool) preg_match('/\?expires=(\d+)$/', $ch['salt'], $m) && abs((int) $m[1] - (time() + 1200)) < 5, $ch['signature'] === hash_hmac('sha256', $ch['challenge'], $k)), array('SHA-256', 100000, true, true));
t('a form may ask for more work, never for less', array($p->challenge(500000)['maxnumber'], $p->challenge(2000)['maxnumber']), array(500000, 100000));
$solve = function ($c) { for ($n = 0; $n <= $c['maxnumber']; $n++) if (hash('sha256', $c['salt'] . $n) === $c['challenge']) return $n; return -1; };
$pay = function ($c, $n, $over = array()) { return base64_encode(json_encode(array_merge(array('algorithm' => 'SHA-256', 'challenge' => $c['challenge'], 'number' => $n, 'salt' => $c['salt'], 'signature' => $c['signature']), $over))); };
$GLOBALS['T']['calls'] = array();
$n = $solve($ch);
$ok = $pay($ch, $n);
t('a solved payload passes', Registry::verify('altcha', $ok, array('request_id' => '11111111-2222-4333-8444-555555555555'))['ok'], true);
t('the same payload again (another request): replay', Registry::verify('altcha', $ok, array('request_id' => '21111111-2222-4333-8444-555555555555'))['code'], 'replay');
$c2 = $p->challenge();
$n2 = $solve($c2);
t('a tampered signature fails', Registry::verify('altcha', $pay($c2, $n2, array('signature' => str_repeat('0', 64))))['code'], 'failed');
t('a wrong number fails', Registry::verify('altcha', $pay($c2, $n2 + 1))['code'], 'failed');
t('another algorithm or garbage fails', array(Registry::verify('altcha', $pay($c2, $n2, array('algorithm' => 'SHA-1')))['code'], Registry::verify('altcha', str_repeat('x', 40))['code']), array('failed', 'failed'));
t('an expired challenge says expired', Registry::verify('altcha', $pay($c2, $n2), array('now' => time() + 3600))['code'], 'expired');
t('a forged challenge with its own salt and no signature of ours fails', Registry::verify('altcha', base64_encode(json_encode(array('algorithm' => 'SHA-256', 'challenge' => hash('sha256', 'x?expires=9999999999' . '5'), 'number' => 5, 'salt' => 'x?expires=9999999999', 'signature' => hash_hmac('sha256', hash('sha256', 'x?expires=99999999995'), 'guess')))))['code'], 'failed');
t('nothing was sent anywhere', count($GLOBALS['T']['calls']), 0);
$r = $p->rest_challenge(new Efb_Req(array()));
t('the route: 200, no-store, a challenge (never the key)', array($r->status, $r->headers['Cache-Control'] ?? '', isset($r->data['challenge']), strpos(json_encode($r->data), $k) === false), array(200, 'no-store, max-age=0', true, true));
for ($i = 0; $i < 59; $i++) $p->rest_challenge(new Efb_Req(array()));
$r = $p->rest_challenge(new Efb_Req(array()));
t('the 61st challenge in a minute from one address: 429', $r instanceof WP_Error ? $r->get_error_code() : 'none', 'efb_captcha_rate');
$w = \Emsfb\Captcha::box_html('altcha', 4, 'form', 1, array('cp_start' => 'onsubmit', 'cp_difficulty' => '200000', 'cp_hide_footer' => '1', 'cp_floating' => '1', 'cp_align' => 'center'));
preg_match('/data-efb-captcha="([^"]+)"/', $w, $mm);
$cfg = json_decode(html_entity_decode($mm[1], ENT_QUOTES), true);
t('the box: the challenge URL, start onsubmit (executed when sent), difficulty, no footer, floating, centred; never the key', array($cfg['ep'], $cfg['start'], $cfg['ex'], $cfg['mx'], $cfg['nf'], $cfg['fl'], strpos($w, 'align-items:center') !== false, strpos($w, $k) === false), array('https://www.site.test/wp-json/Emsfb/v1/captcha/altcha/challenge', 'onsubmit', 1, '200000', 1, 1, true, true));
t('the widget texts are the add-on\'s phrases', array_keys($cfg['str']), array('label', 'verifying', 'verified', 'failed', 'expired'));
t('the adapter is under 2 KB; the widget is the add-on\'s own file (no CDN)', array(filesize($D . '/assets/altcha-adapter-efb.js') < 2048, strpos($p->scripts(array())[0]['src'], 'vendor/altcha/assets/altcha-widget-efb.js') !== false), array(true, true));
efb_done();
