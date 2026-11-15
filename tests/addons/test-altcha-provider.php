<?php
/**
 * F12b-H (owner decision 2026-11-15: the official ALTCHA widget): the ALTCHA add-on (AdnALT), standalone. Nothing
 * leaves the site: no wp_remote_post at all.
 *
 *  - registers (kind endpoint, Free Plus), ready without any key: the signing key is made when the add-on starts
 *    (boot) and kept encrypted; rotating it (a new key) makes the old challenges fail;
 *  - a challenge in the ALTCHA v2 protocol the 3.5.0 widget expects: parameters {algorithm SHA-256, cost 1,
 *    keyLength 32, nonce, salt, keyPrefix (16 bytes of the derived key), keySignature, expiresAt (20 minutes)}, sorted,
 *    and the signature = HMAC-SHA256 of their JSON; the counter in the range of the complexity (Medium by default);
 *    a box may ask for more (High), never for less;
 *  - verify: a solved payload (as the widget sends it) passes; the same challenge again → replay, a network retry with
 *    the same request id passes; a tampered signature / counter / key / prefix, another algorithm, garbage fail; an
 *    expired one says expired; the old key's challenge fails after a rotation;
 *  - migration from the add-on's first version: maxnumber → complexity, a form's cp_difficulty / cp_floating →
 *    cp_complexity / cp_display (read time and stored forms);
 *  - the box carries the challenge URL, never a key; the options reach the config; the widget's texts come from the
 *    phrase list; the widget is the bundled official file (no CDN), the adapter under 2 KB.
 *
 * Run: php tests/addons/test-altcha-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Altcha_Provider as A;

$D = efb_addon_dir('altcha');
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
function rest_url($p = '') { return 'https://www.site.test/wp-json/' . $p; }
function nocache_headers() {}
function did_action($h) { return 1; }
class WP_REST_Response { public $data; public $status; public $headers = array(); public function __construct($d, $s) { $this->data = $d; $this->status = $s; } public function header($k, $v) { $this->headers[$k] = $v; } }
class Efb_Req { public $p; public function __construct($p) { $this->p = $p; } public function get_param($k) { return $this->p[$k] ?? null; } }
// The add-on's first version on this site: maxnumber 300000, no complexity yet.
$GLOBALS['T']['options']['emsfb_cpv_altcha'] = array('public' => array('maxnumber' => '300000', 'expires' => '20'), 'secret' => array());
require $D . '/routes-efb.php';
Registry::boot();
$p = Registry::get('altcha');
t('registered: endpoint kind, Free Plus, ready without any key', array($p ? $p->kind() : null, $p ? $p->plan() : null, $p ? $p->ready() : null), array('endpoint', 'free_plus', true));
$raw = $GLOBALS['T']['settings']['captcha_altcha'] ?? array(); // F12b-L: the settings row (the first version's option was read once)
$k = A::key();
t('boot (the add-on turned on): the signing key exists, 48 characters, encrypted, the same next time', array(strlen($k), strpos($raw['secret']['hmac_key'] ?? '', 'enc:v1:') === 0, strpos(json_encode($raw), $k) === false, A::key() === $k), array(48, true, true, true));
t('migration: maxnumber 300000 of the first version → complexity high (once, marked)', array($raw['public']['complexity'] ?? null, get_option('emsfb_altcha_v')), array('high', 2));
\Emsfb\Captcha\Admin::save('altcha', array('complexity' => 'medium'), array());
Registry::forget('altcha');
$p = Registry::get('altcha');

$ch = $p->challenge();
$pp = $ch['parameters'];
$keys = array_keys($pp);
$sorted = $keys; sort($sorted);
t('a challenge: SHA-256, cost 1, keyLength 32, 16-byte nonce and salt, a 16-byte keyPrefix, keySignature, sorted keys', array($pp['algorithm'], $pp['cost'], $pp['keyLength'], strlen($pp['nonce']), strlen($pp['salt']), strlen($pp['keyPrefix']), strlen($pp['keySignature']), $keys === $sorted), array('SHA-256', 1, 32, 32, 32, 32, 64, true));
t('… expires in 20 minutes; signed with HMAC-SHA256 over the JSON of the parameters (as JSON.stringify writes it)', array(abs($pp['expiresAt'] - (time() + 1200)) < 5, strlen($ch['signature'])), array(true, 64));
/** The widget's solver: sha256(salt ‖ nonce ‖ uint32 counter) until the key starts with keyPrefix. */
$solve = function ($c, $max = 400000) {
	$s = hex2bin($c['parameters']['salt']); $n = hex2bin($c['parameters']['nonce']); $pre = $c['parameters']['keyPrefix'];
	for ($i = 0; $i <= $max; $i++) { $dk = hash('sha256', $s . $n . pack('N', $i)); if (strpos($dk, $pre) === 0) return array('counter' => $i, 'derivedKey' => $dk); }
	return null;
};
$pay = function ($c, $sol, $over = array()) { $j = array('challenge' => array('parameters' => $c['parameters'], 'signature' => $c['signature']), 'solution' => $sol); foreach ($over as $k2 => $v) { $ref = &$j; foreach (explode('.', $k2) as $part) $ref = &$ref[$part]; $ref = $v; unset($ref); } return base64_encode(json_encode($j, JSON_UNESCAPED_SLASHES)); };
$sol = $solve($ch);
t('Medium: the counter is in 25,000 … 75,000 (about a second on a mid-range phone)', $sol !== null && $sol['counter'] >= 25000 && $sol['counter'] <= 75000, true);
$hi = $solve($p->challenge('high'), 300000);
$lo = $solve($p->challenge('low'), 300000);
t('a box may ask for more (High: 100,000 … 300,000), never for less (Low asked → Medium)', array($hi['counter'] >= 100000, $lo['counter'] >= 25000), array(true, true));
$GLOBALS['T']['calls'] = array();
$ok = $pay($ch, $sol);
t('a solved payload (the widget\'s format) passes', Registry::verify('altcha', $ok, array('request_id' => '11111111-2222-4333-8444-555555555555'))['ok'], true);
t('a network retry of that request (same request id) passes', A::key() !== '' && $p->verify($ok, array('request_id' => '11111111-2222-4333-8444-555555555555'))['ok'], true);
t('the same challenge again from another request: replay', $p->verify($ok, array('request_id' => '21111111-2222-4333-8444-555555555555'))['code'], 'replay');
$c2 = $p->challenge();
$s2 = $solve($c2);
$codes = array(
	$p->verify($pay($c2, $s2, array('challenge.signature' => str_repeat('0', 64))), array())['codes'][0] ?? '',
	$p->verify($pay($c2, array('counter' => $s2['counter'] + 1, 'derivedKey' => $s2['derivedKey'])), array())['codes'][0] ?? '',
	$p->verify($pay($c2, array('counter' => $s2['counter'], 'derivedKey' => str_repeat('a', 64))), array())['codes'][0] ?? '',
	$p->verify($pay($c2, $s2, array('challenge.parameters.keyPrefix' => str_repeat('0', 32))), array())['codes'][0] ?? '',
	$p->verify($pay($c2, $s2, array('challenge.parameters.algorithm' => 'PBKDF2/SHA-256')), array())['codes'][0] ?? '',
	$p->verify(str_repeat('x', 40), array())['code'],
);
t('tampered signature, counter, key, prefix, another algorithm, garbage: all fail', $codes, array('signature', 'solution', 'solution', 'signature', 'shape', 'failed'));
t('an expired challenge says expired', $p->verify($pay($c2, $s2), array('now' => time() + 3600))['code'], 'expired');
\Emsfb\Captcha\Admin::save('altcha', array(), array('hmac_key' => 'rotated-' . str_repeat('k', 40)));
Registry::forget('altcha');
$p = Registry::get('altcha');
t('rotation: after a new signing key, a challenge of the old key fails', $p->verify($pay($c2, $s2), array())['codes'][0] ?? '', 'signature');
t('nothing was sent anywhere', count($GLOBALS['T']['calls']), 0);
$r = $p->rest_challenge(new Efb_Req(array('c' => 'high')));
t('the route: 200, no-store, a challenge (never the key)', array($r->status, $r->headers['Cache-Control'] ?? '', isset($r->data['parameters']['keyPrefix']), strpos(json_encode($r->data), A::key()) === false), array(200, 'no-store, max-age=0', true, true));
for ($i = 0; $i < 59; $i++) $p->rest_challenge(new Efb_Req(array()));
$r = $p->rest_challenge(new Efb_Req(array()));
t('the 61st challenge in a minute from one address: 429', $r instanceof WP_Error ? $r->get_error_code() : 'none', 'efb_captcha_rate');

// Forms of the first version: read time and stored.
t('a form of the first version: cp_difficulty 200000 → medium, cp_floating 1 → floating', A::map_form(array('cp_difficulty' => '200000', 'cp_floating' => '1')), array('cp_complexity' => 'medium', 'cp_display' => 'floating'));
$w = \Emsfb\Captcha::box_html('altcha', 4, 'form', 1, array('cp_start' => 'onsubmit', 'cp_difficulty' => '900000', 'cp_hide_footer' => '1', 'cp_floating' => '1', 'cp_hide_logo' => '1', 'cp_align' => 'center'));
preg_match('/data-efb-captcha="([^"]+)"/', $w, $mm);
$cfg = json_decode(html_entity_decode($mm[1], ENT_QUOTES), true);
t('the box: the challenge URL, start onsubmit (executed when sent), complexity high, floating, no footer, no logo, centred; never the key', array($cfg['ep'], $cfg['start'], $cfg['ex'], $cfg['cx'], $cfg['display'], $cfg['nf'], $cfg['nl'], strpos($w, 'align-items:center') !== false, strpos($w, A::key()) === false), array('https://www.site.test/wp-json/Emsfb/v1/captcha/altcha/challenge', 'onsubmit', 1, 'high', 'floating', 1, 1, true, true));
$w2 = \Emsfb\Captcha::box_html('altcha', 5, 'form', 1, array());
preg_match('/data-efb-captcha="([^"]+)"/', $w2, $mm);
$cfg2 = json_decode(html_entity_decode($mm[1], ENT_QUOTES), true);
t('defaults: starts when the visitor starts the form (onfocus), standard display, the site\'s complexity', array($cfg2['start'], $cfg2['ex'], $cfg2['display'], $cfg2['cx']), array('onfocus', 0, 'standard', 'medium'));
t('the widget\'s texts come from the phrase list (label, states, footer with the link)', array(array_keys($cfg['str']) === array('label', 'verifying', 'verified', 'error', 'expired', 'verificationRequired', 'waitAlert', 'loading', 'verify', 'reload', 'ariaLinkLabel', 'footer'), strpos($cfg['str']['footer'], 'href="https://altcha.org/"') !== false), array(true, true));
$src = $p->scripts(array())[0];
t('the widget is the bundled official file altcha 3.5.0 (no CDN); the adapter is under 3 KB (it sets the widget\'s texts and when it starts)', array(strpos($src['src'], 'vendor/altcha/assets/altcha-3.5.0.min.js') !== false, is_file($D . '/assets/altcha-3.5.0.min.js') && filesize($D . '/assets/altcha-3.5.0.min.js') === 118907, filesize($D . '/assets/altcha-adapter-efb.js') < 3072, strpos($src['adapter'], 'altcha-adapter-efb.js') !== false), array(true, true, true, true));
t('the old widget file is only a loader of the official one (pages cached before the update)', filesize($D . '/assets/altcha-widget-efb.js') < 1024 && strpos(file_get_contents($D . '/assets/altcha-widget-efb.js'), 'altcha-3.5.0.min.js') !== false, true);
t('the MIT licence of the bundled widget ships with it', is_file($D . '/assets/altcha-LICENSE.txt') && strpos(file_get_contents($D . '/assets/altcha-LICENSE.txt'), 'MIT') !== false, true);
efb_done();
