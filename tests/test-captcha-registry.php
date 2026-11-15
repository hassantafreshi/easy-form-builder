<?php
/**
 * F12b-A/B (docs/feature-gaps/F12b-captcha-providers.fa.md §2, §1, §5): the CAPTCHA provider contract v2, standalone
 * (no WordPress, no network: every service answer is a stub, never a real key).
 *
 *  - registration: Google's three modes are core's; a fake add-on provider appears in the catalogue (builder select,
 *    settings schema, capabilities, schema); a malformed id, a duplicate, contract 3 (core too old), contract 1 (add-on
 *    too old), an unknown add-on code and an add-on that takes the recaptcha_ prefix are refused and reported;
 *  - effective provider (§5): the form's own, else the site's; its add-on gone / plan / keys → the site's Google mode
 *    when ready, else none, and then the hidden field and the minimum time are forced on (not on a login form);
 *  - the common checks of Registry::verify(): hostname (filter), action (the form's rc3_action), token age, replay
 *    (the same request_id again passes), http_post() host allow-list, 8 s, no redirect, never remoteip;
 *  - Google modes (B): v2 invisible keys of its own, rc_host recaptcha.net (script and siteverify), badge hidden ⇒
 *    Google's notice, v3 → v2 fallback (challenge, the v2 token only on a form that offers it);
 *  - D4: redact_settings() (object, array, JSON), last four only from 16 characters, secret_on_save();
 *  - the box: unique ids for two boxes of one form, its config has no secret; cp_* options by schema, kept as text
 *    while the provider is absent.
 *
 * Run: php tests/test-captcha-registry.php
 */
namespace {
define('ABSPATH', __DIR__ . '/');
class WP_Error { public $c; public function __construct($c = '', $m = '') { $this->c = $c; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
$GLOBALS['T'] = array('calls' => array(), 'reply' => null, 'filters' => array(), 'settings' => array(), 'transients' => array(), 'actions' => array());
function wp_remote_post($url, $args) { $GLOBALS['T']['calls'][] = array('url' => $url, 'args' => $args); $r = $GLOBALS['T']['reply']; return is_callable($r) ? $r($url, $args) : $r; }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['T']['filters'][$h]) ? call_user_func_array($GLOBALS['T']['filters'][$h], array_slice($a, 1)) : $v; }
function do_action($h) { $a = func_get_args(); if (isset($GLOBALS['T']['actions'][$h])) call_user_func_array($GLOBALS['T']['actions'][$h], array_slice($a, 1)); }
function home_url() { return 'https://www.site.test'; }
function get_setting_Emsfb($m = 'decoded') { return json_decode(json_encode($GLOBALS['T']['settings'])); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function get_locale() { return 'fa_IR'; }
function set_transient($k, $v, $t) { $GLOBALS['T']['transients'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['T']['transients'][$k] ?? false; }
}

namespace Emsfb {
/** In-memory counters: reserve() is the atomic "one place" of the real table. */
class Rate_Limit {
	public static $rows = array();
	public static function ready() { return true; }
	public static function reserve($scope, $key, $slot, $end, $limit, $seed = 0) {
		$b = $scope . '|' . $key . '|' . $slot;
		$n = self::$rows[$b] ?? 0;
		if ($n >= $limit) return false;
		self::$rows[$b] = $n + 1;
		return true;
	}
}
}

namespace {
require __DIR__ . '/../includes/class-Emsfb-captcha.php';
require __DIR__ . '/../includes/class-Emsfb-spam.php';
use Emsfb\Captcha;
use Emsfb\Spam;
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;
use Emsfb\Captcha\Provider;

$pass = 0; $fail = 0;
function t($name, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] {$name}\n"; }
	else { $fail++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected) . "\n  actual:   " . json_encode($actual) . "\n"; }
}

/** A fake add-on provider (contract 2) with every kind of setting and option. */
class Fake_Provider extends Provider {
	public $pid = 'fakecap'; public $key = 'AdnCTS';
	public function id() { return $this->pid; }
	public function label() { return 'Fake CAPTCHA'; }
	public function addon_key() { return $this->key; }
	public function has_action() { return true; }
	public function token_ttl() { return 300; }
	public function settings_schema() { return array(array('key' => 'site_key', 'type' => 'text', 'required' => true, 'public' => true), array('key' => 'secret', 'type' => 'secret', 'required' => true), array('key' => 'mode', 'type' => 'select', 'values' => array('a', 'b'), 'default' => 'a')); }
	public function form_options_schema() { return array(array('key' => 'cp_theme', 'type' => 'enum', 'values' => array('light', 'dark', 'auto'), 'default' => 'auto'), array('key' => 'cp_action', 'type' => 'text', 'maxlen' => 20, 'pattern' => '^[a-z_]+$'), array('key' => 'cp_retry', 'type' => 'toggle'), array('key' => 'cp_difficulty', 'type' => 'number', 'min' => 1, 'max' => 5)); }
	public function widget(array $c) { return array('config' => array('k' => $this->setting('site_key'), 'theme' => $this->opt($c['form'] ?? array(), 'cp_theme'))); }
	public function scripts(array $c) { return array(array('handle' => 'efb-cp-fakecap', 'src' => 'https://fake.example/api.js', 'adapter' => 'https://site.test/fake-adapter.js')); }
	public function verify_hosts() { return array('verify.fake.example'); }
	public function verify($token, array $c) {
		$r = \Emsfb\Captcha::http_post($this, 'https://verify.fake.example/siteverify', array('secret' => $this->setting('secret'), 'response' => $token, 'remoteip' => '203.0.113.9'));
		if (!isset($r['json'])) return $r;
		$j = $r['json'];
		return array('ok' => $j['success'] === true, 'code' => $j['success'] === true ? '' : 'failed', 'hostname' => $j['hostname'] ?? null, 'action' => $j['action'] ?? null, 'ts' => isset($j['ts']) ? (int) $j['ts'] : null);
	}
}
class Bad_Api3 extends Fake_Provider { const API = 3; public $pid = 'newer'; }
class Bad_Api1 extends Fake_Provider { const API = 1; public $pid = 'older'; }

$GLOBALS['T']['actions']['efb_register_captcha_providers'] = function ($reg) {
	$reg->register(new Fake_Provider());
	$d = new Fake_Provider(); $reg->register($d);                              // duplicate
	$b = new Fake_Provider(); $b->pid = 'Bad Id'; $reg->register($b);           // malformed
	$reg->register(new Bad_Api3());
	$reg->register(new Bad_Api1());
	$g = new Fake_Provider(); $g->pid = 'recaptcha_x'; $reg->register($g);      // core's prefix
	$u = new Fake_Provider(); $u->pid = 'nokey'; $u->key = 'Bogus!'; $reg->register($u); // unknown add-on code
};
$S = array('siteKey' => 'v2-site-key-placeholder', 'secretKey' => 'v2-secret-placeholder-0001',
	'rciSiteKey' => 'v2i-site-key-placeholder', 'rciSecretKey' => 'v2i-secret-placeholder-002',
	'rc3SiteKey' => 'v3-site-key-placeholder', 'rc3SecretKey' => 'v3-secret-placeholder-0003', 'rc3Threshold' => '0.5');
$GLOBALS['T']['settings'] = $S;
Registry::set_stored('fakecap', array('public' => array('site_key' => 'fake-site-key-1234'), 'secret' => array('secret' => 'fake-secret-value-99')));

echo "--- registration ---\n";
$ids = array_keys(Registry::all());
t('core registers the three Google modes; the fake add-on provider is listed', array_values(array_intersect($ids, array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'fakecap'))), array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'fakecap'));
$errs = array_map(function ($e) { return $e['id'] . ':' . $e['code']; }, Registry::errors());
t('refused: duplicate, malformed id, contract 3 (core too old), contract 1 (add-on too old), recaptcha_ prefix, unknown add-on code',
	$errs, array('fakecap:duplicate', 'Bad Id:bad_id', 'newer:core_too_old', 'older:addon_too_old', 'recaptcha_x:bad_id', 'nokey:unknown_addon'));
$cat = Admin::catalog($S);
$fake = array_values(array_filter($cat, function ($e) { return $e['id'] === 'fakecap'; }))[0];
t('catalogue: plan, add-on, kind, options schema, settings schema; the secret only as "saved"', array($fake['plan'], $fake['addon'], $fake['kind'], count($fake['options']), $fake['settings'][1]['saved'], $fake['settings'][0]['value']), array('free_plus', 'AdnCTS', 'widget', 4, array('set' => true, 'last4' => 'e-99'), 'fake-site-key-1234'));
t('catalogue: never a secret value', strpos(json_encode($cat), 'fake-secret-value-99') === false && strpos(json_encode($cat), 'v3-secret-placeholder') === false, true);
t('catalogue: add-ons core knows but that are not installed say so (Install the add-on)', array_values(array_map(function ($e) { return $e['id']; }, array_filter($cat, function ($e) { return !$e['installed']; }))), array('turnstile', 'hcaptcha', 'altcha', 'question', 'friendly')); // F12b-E: hCaptcha and Turnstile are add-ons too
$caps = Spam::capabilities(array('features' => array()));
t('capabilities: the fake provider is a value of setting:captcha_provider with its plan', array(in_array('fakecap', $caps['features']['setting:captcha_provider']['values'], true), $caps['features']['setting:captcha_provider']['providers']['fakecap']['plan']), array(true, 'free_plus'));
t('capabilities: no secret anywhere', strpos(json_encode($caps), 'secret-value') === false && strpos(json_encode($caps), 'placeholder-000') === false, true);
t('efb_captcha_api-like list: Google + the fake one, in_array() works for old consumers', in_array('recaptcha_v3', array_keys(Registry::all()), true), true);

echo "--- effective provider (§5) ---\n";
$f = function ($extra) { return array_merge(array('type' => 'form', 'captcha' => '1'), $extra); };
t('the form\'s own ready provider', Registry::effective_for($f(array('captcha_provider' => 'fakecap')), $S)['id'], 'fakecap');
t('the site\'s provider when the form has none', Registry::effective_for($f(array()), array_merge($S, array('captcha_provider' => 'recaptcha_v3')))['id'], 'recaptcha_v3');
$e = Registry::effective_for($f(array('captcha_provider' => 'altcha')), $S);
t('an add-on that is gone → the site\'s Google mode (v2 ready), fallback, why addon', array($e['id'], $e['fallback'], $e['why'], $e['want']), array('recaptcha_v2', true, 'addon', 'altcha'));
$e = Registry::effective_for($f(array('captcha_provider' => 'altcha')), array_merge($S, array('captcha_provider' => 'recaptcha_v3')));
t('… the site\'s own Google mode first (v3)', $e['id'], 'recaptcha_v3');
$none = array('captcha_provider' => 'turnstile');
$e = Registry::effective_for($f(array()), $none);
t('no Google keys and the site\'s add-on gone → no provider (soft checks forced)', array($e['provider'], $e['fallback']), array(null, true));
$c = Spam::cfg($f(array('spam_off' => '1')), $none);
t('forced: hidden field and minimum time 3 s on, even with spam_off and no site setting', array($c['forced'], $c['honeypot'], $c['min_time']), array(true, true, 3));
$c = Spam::cfg(array('type' => 'login', 'captcha' => '1'), array_merge($none, array('spam_honeypot' => '1', 'spam_min_time' => '5')));
t('login form: never forced, no minimum time (autofill), the hidden field as set (D1)', array($c['forced'], $c['honeypot'], $c['min_time']), array(false, true, 0));
$c = Spam::cfg($f(array()), $S);
t('a form whose CAPTCHA is ready: nothing forced (4.3.1 site: every check off)', array($c['forced'], $c['honeypot'], $c['min_time']), array(false, false, 0));
$e = Registry::effective_for($f(array('captcha_provider' => 'fakecap')), $S);
Registry::set_stored('fakecap', array('public' => array('site_key' => 'fake-site-key-1234'), 'secret' => array()));
t('the form\'s add-on without its secret → Google', Registry::effective_for($f(array('captcha_provider' => 'fakecap')), $S)['why'], 'keys');
Registry::set_stored('fakecap', array('public' => array('site_key' => 'fake-site-key-1234'), 'secret' => array('secret' => 'fake-secret-value-99')));
$sc = Spam::form_schema($f(array('captcha_provider' => 'altcha')), $S)['captcha'];
t('schema: asked altcha, protected by recaptcha_v2 (fallback google), widget visible', array($sc['provider'], $sc['effective_provider'], $sc['fallback'], $sc['widget'], $sc['ready']), array('altcha', 'recaptcha_v2', 'google', 'visible', false));
$sc = Spam::form_schema($f(array()), $none)['captcha'];
t('schema: no service usable → fallback soft_checks, widget none', array($sc['effective_provider'], $sc['fallback'], $sc['widget']), array(null, 'soft_checks', 'none'));

echo "--- common checks of verify() ---\n";
$ok = function ($x = array()) { return array('body' => json_encode(array_merge(array('success' => true, 'hostname' => 'site.test', 'action' => 'efb_submit', 'ts' => time()), $x))); };
$tok = function ($s) { return str_pad($s, 40, 'x'); };
$v = function ($token, $reply, $ctx = array()) use ($S) { $GLOBALS['T']['calls'] = array(); $GLOBALS['T']['reply'] = $reply; return Registry::verify('fakecap', $token, array_merge(array('settings' => $S), $ctx)); };
$r = $v($tok('a1'), $ok());
$c0 = $GLOBALS['T']['calls'][0];
t('http_post: the allow-listed host, POST, 8 s, no redirect, never remoteip', array($c0['url'], $c0['args']['timeout'], $c0['args']['redirection'], isset($c0['args']['body']['remoteip']), $r['ok']), array('https://verify.fake.example/siteverify', 8, 0, false, true));
t('another hostname → hostname', $v($tok('a2'), $ok(array('hostname' => 'evil.test')))['code'], 'hostname');
$GLOBALS['T']['filters']['efb_captcha_check_hostname'] = function () { return false; };
t('efb_captcha_check_hostname false: another hostname passes', $v($tok('a3'), $ok(array('hostname' => 'evil.test')))['ok'], true);
unset($GLOBALS['T']['filters']['efb_captcha_check_hostname']);
t('another action → action', $v($tok('a4'), $ok(array('action' => 'login')))['code'], 'action');
t('the form\'s own action (cp_action) is the one checked', $v($tok('a5'), $ok(array('action' => 'contact')), array('form' => array('cp_action' => 'contact')))['ok'], true);
t('a token older than its life (300 s + 60) → expired', $v($tok('a6'), $ok(array('ts' => time() - 400)))['code'], 'expired');
$rid = '0b9a3c3e-1f2d-4c5b-9a8e-7d6c5b4a3f21';
t('replay: the first use passes', $v($tok('r1'), $ok(), array('request_id' => $rid))['ok'], true);
t('replay: the same request id again (a network retry) passes', $v($tok('r1'), $ok(), array('request_id' => $rid))['ok'], true);
t('replay: the same token in another request → replay', $v($tok('r1'), $ok(), array('request_id' => '11111111-2222-4333-8444-555555555555'))['code'], 'replay');
t('replay: the same token without a request id → replay', $v($tok('r1'), $ok())['code'], 'replay');
t('a network error → network (closed)', $v($tok('n1'), new WP_Error('x'))['code'], 'network');
t('an answer that is not JSON → invalid_response (closed)', $v($tok('n2'), array('body' => '<html>'))['code'], 'invalid_response');
t('a short token → missing, nothing sent', array($v('abc', $ok())['code'], count($GLOBALS['T']['calls'])), array('missing', 0));
$p = Registry::get('fakecap');
t('http_post to a host that is not allow-listed → config, nothing sent', array(Captcha::http_post($p, 'https://evil.example/x', array())['code'], count($GLOBALS['T']['calls'])), array('config', 0));
t('http_post over http:// → config', Captcha::http_post($p, 'http://verify.fake.example/siteverify', array())['code'], 'config');

echo "--- Google modes (B) ---\n";
$g = function ($id, $token, $reply, $ctx = array()) use ($S) { $GLOBALS['T']['calls'] = array(); $GLOBALS['T']['reply'] = $reply; return Registry::verify($id, $token, array_merge(array('settings' => $S), $ctx)); };
$r = $g('recaptcha_v2i', $tok('g1'), $ok());
t('v2 invisible: its own secret (rciSecretKey), Google siteverify', array($GLOBALS['T']['calls'][0]['url'], $GLOBALS['T']['calls'][0]['args']['body']['secret'], $r['ok']), array('https://www.google.com/recaptcha/api/siteverify', 'v2i-secret-placeholder-002', true));
t('v2 invisible: another hostname → hostname (only v2 checkbox is exempt, as 4.3.1)', $g('recaptcha_v2i', $tok('g2'), $ok(array('hostname' => 'evil.test')))['code'], 'hostname');
$S2 = array_merge($S, array('rc_host' => 'recaptcha.net'));
$GLOBALS['T']['reply'] = $ok(array('score' => 0.9)); $GLOBALS['T']['calls'] = array();
Registry::verify('recaptcha_v3', $tok('g3'), array('settings' => $S2));
t('rc_host recaptcha.net: siteverify on www.recaptcha.net', $GLOBALS['T']['calls'][0]['url'], 'https://www.recaptcha.net/recaptcha/api/siteverify');
t('rc_host recaptcha.net: the script from www.recaptcha.net (v3 key, page language)', Captcha\Google::script_url('v3k', 'fa', $S2), 'https://www.recaptcha.net/recaptcha/api.js?hl=fa&render=v3k');
t('rc_host: only google.com or recaptcha.net', array(Spam::sanitize_setting('rc_host', 'recaptcha.net'), Spam::sanitize_setting('rc_host', 'evil.com')), array('recaptcha.net', 'google.com'));
$v3 = array('type' => 'form', 'captcha' => '1', 'captcha_provider' => 'recaptcha_v3', 'rc3_action' => 'contact_us');
$GLOBALS['T']['reply'] = $ok(array('score' => 0.9, 'action' => 'contact_us'));
t('v3: the form\'s rc3_action is the action checked', Spam::verify_captcha($tok('g4'), 'form', array('form' => $v3, 'settings' => $S))['ok'], true);
$GLOBALS['T']['reply'] = $ok(array('score' => 0.9, 'action' => 'efb_submit'));
t('v3: a token for another action (even the default) → action', Spam::verify_captcha($tok('g5'), 'form', array('form' => $v3, 'settings' => $S))['code'], 'action');
$fb = array_merge($v3, array('rc3_action' => '', 'rc3_fallback' => '1'));
$GLOBALS['T']['reply'] = $ok(array('score' => 0.3));
$q = Spam::verify_captcha($tok('g6'), 'form', array('form' => $fb, 'settings' => $S));
t('v3 → v2: a low score on a form with the fallback → challenge (nothing stored)', array($q['ok'], $q['code']), array(false, 'challenge'));
$GLOBALS['T']['reply'] = $ok(array('hostname' => 'site.test')); $GLOBALS['T']['calls'] = array();
$q = Spam::verify_captcha($tok('g7'), 'form', array('form' => $fb, 'settings' => $S, 'kind' => 'v2'));
t('v3 → v2: the checkbox token is checked with the v2 secret and passes', array($q['ok'], $q['provider'], $GLOBALS['T']['calls'][0]['args']['body']['secret']), array(true, 'recaptcha_v2', 'v2-secret-placeholder-0001'));
$GLOBALS['T']['calls'] = array();
$q = Spam::verify_captcha($tok('g8'), 'form', array('form' => array_merge($v3, array('rc3_action' => '')), 'settings' => $S, 'kind' => 'v2'));
t('v3 without the fallback: a v2 token is refused, nothing sent', array($q['ok'], $q['code'], count($GLOBALS['T']['calls'])), array(false, 'failed', 0));
$GLOBALS['T']['reply'] = $ok(array('score' => 0.3));
$q = Spam::verify_captcha($tok('g9'), 'form', array('form' => array_merge($v3, array('rc3_action' => '')), 'settings' => $S));
t('v3 without the fallback: a low score is spam as in F12 (low_score soft)', array($q['ok'], $q['spam']), array(true, true));
$S3 = array_merge($S, array('siteKey' => '', 'secretKey' => ''));
$q = Spam::verify_captcha($tok('h1'), 'form', array('form' => $fb, 'settings' => $S3));
t('v3 with the fallback but no v2 keys: no challenge (spam as F12)', array($q['ok'], $q['spam'], $q['code']), array(true, true, ''));
$w = Captcha::box_html('recaptcha_v3', 7, 'form', 1, $v3, $S);
t('v3 box: default badge hidden ⇒ Google\'s notice with its links', strpos($w, 'policies.google.com/privacy') !== false && strpos($w, 'policies.google.com/terms') !== false, true);
$w = Captcha::box_html('recaptcha_v2i', 7, 'form', 1, array('cp_badge' => 'bottomleft'), $S);
t('v2 invisible box with a visible badge: no notice; badge in its config', strpos($w, 'policies.google.com') === false && strpos($w, '&quot;badge&quot;:&quot;bottomleft&quot;') !== false, true);
$w = Captcha::box_html('recaptcha_v2i', 7, 'form', 1, array('cp_badge' => 'hidden'), $S);
t('v2 invisible box with the badge hidden: Google\'s notice is always there', strpos($w, 'policies.google.com/privacy') !== false, true);
$w = Captcha::box_html('recaptcha_v3', 7, 'form', 1, $fb, $S);
t('v3 box with the fallback: the v2 site key (public) in its config as fb', strpos($w, '&quot;fb&quot;:&quot;v2-site-key-placeholder&quot;') !== false, true);
t('Google options by schema: theme, size, badge, lang, align; rc3_action pattern', array(
	Registry::sanitize_form_option('recaptcha_v2', 'cp_theme', 'dark'), Registry::sanitize_form_option('recaptcha_v2', 'cp_size', 'invisible'), Registry::sanitize_form_option('recaptcha_v3', 'cp_badge', 'inline'),
	Registry::sanitize_form_option('recaptcha_v2', 'cp_lang', 'fa'), Registry::sanitize_form_option('recaptcha_v2', 'cp_align', 'center'), Spam::sanitize_form_key('rc3_action', 'shop/checkout'), Spam::sanitize_form_key('rc3_action', 'no spaces')),
	array('dark', '', '', 'fa', 'center', 'shop/checkout', ''));
t('the widget language: fa_IR → fa, ar → ar', array(Captcha::lang('fa_IR'), Captcha::lang('ar')), array('fa', 'ar'));

echo "--- the box (C) ---\n";
$b1 = Captcha::box_html('fakecap', 9, 'form', 2, array('cp_theme' => 'dark'), $S);
$b2 = Captcha::box_html('fakecap', 9, 'form', 2, array(), $S);
preg_match('/id="(efb-cp-9-\d+)"/', $b1, $m1); preg_match('/id="(efb-cp-9-\d+)"/', $b2, $m2);
t('two boxes of one form: two ids, label and live region of their own', array(!empty($m1[1]) && !empty($m2[1]) && $m1[1] !== $m2[1], strpos($b1, 'aria-labelledby="' . $m1[1] . '-l"') !== false, strpos($b1, 'id="' . $m1[1] . '-m" role="alert" aria-live="assertive"') !== false), array(true, true, true));
t('the config: provider, kind, site key, the form\'s option, script and adapter URLs; never the secret', strpos($b1, '&quot;p&quot;:&quot;fakecap&quot;') !== false && strpos($b1, '&quot;theme&quot;:&quot;dark&quot;') !== false && strpos($b1, 'fake-adapter.js') !== false && strpos($b1, 'fake-secret') === false, true);
t('outside a form: data-formid -1 and its context', strpos(Captcha::box_html('fakecap', 0, 'reply_12', 0, array(), $S), 'data-formid="-1" data-efb-captcha-ctx="reply_12"') !== false, true);
t('cp_* options by the schema: enum, pattern, toggle, number clamp; unknown key dropped', array(
	Registry::sanitize_form_option('fakecap', 'cp_theme', 'dark'), Registry::sanitize_form_option('fakecap', 'cp_theme', 'neon'), Registry::sanitize_form_option('fakecap', 'cp_action', 'Bad!'),
	Registry::sanitize_form_option('fakecap', 'cp_retry', true), Registry::sanitize_form_option('fakecap', 'cp_difficulty', '۹'), Registry::sanitize_form_option('fakecap', 'cp_nope', 'x'), Registry::sanitize_form_option('fakecap', 'theme', 'dark')),
	array('dark', '', '', '1', '5', '', ''));
t('cp_* of an absent add-on: kept as plain text (≤ 200), tags stripped', array(Registry::sanitize_form_option('altcha', 'cp_start', '<b>onfocus</b>'), strlen(Registry::sanitize_form_option('altcha', 'cp_msg_failed', str_repeat('x', 300)))), array('onfocus', 200));
t('captcha_provider of a form: any well-formed id is kept (the add-on may come back)', array(Spam::sanitize_form_key('captcha_provider', 'altcha'), Spam::sanitize_form_key('captcha_provider', 'ALTCHA')), array('altcha', ''));

echo "--- D4: secrets write-only ---\n";
$row = (object) array('secretKey' => '6LcAAAAAAAAAAAAAAAAAAAAAAA1234', 'rc3SecretKey' => 'short-secret', 'stripeSKey' => 'sk_test_PLACEHOLDER_0000000000abcd', 'paypalSKey' => '', 'siteKey' => 'public-site-key-value');
$red = Admin::redact_settings($row);
t('object: secrets → {set, last4} (16+ characters), a short one → {set} only, empty → "", public keys untouched', array($red->secretKey, $red->rc3SecretKey, $red->stripeSKey, $red->paypalSKey, $red->siteKey), array(array('set' => true, 'last4' => '1234'), array('set' => true), array('set' => true, 'last4' => 'abcd'), '', 'public-site-key-value'));
t('object: the original row is not changed', $row->secretKey, '6LcAAAAAAAAAAAAAAAAAAAAAAA1234');
$j = Admin::redact_settings(json_encode(array('secretKey' => '6LcAAAAAAAAAAAAAAAAAAAAAAA1234', 'x' => 'y')));
t('JSON string: redacted and still JSON', strpos($j, '6LcAAAA') === false && json_decode($j, true)['x'] === 'y', true);
$GLOBALS['T']['filters']['efb_secret_setting_keys'] = function ($k) { $k[] = 'myAddonToken'; return $k; };
t('efb_secret_setting_keys adds a key', Admin::redact_settings(array('myAddonToken' => 'tok-value-0000000000'))['myAddonToken'], array('set' => true, 'last4' => '0000'));
unset($GLOBALS['T']['filters']['efb_secret_setting_keys']);
t('secret_on_save: empty, a mask, an object, NotFoundEl keep; __clear__ removes; text replaces', array(
	Admin::secret_on_save('', 'old'), Admin::secret_on_save('•••• 1234', 'old'), Admin::secret_on_save(array('set' => true), 'old'), Admin::secret_on_save('NotFoundEl', 'old'), Admin::secret_on_save('__clear__', 'old'), Admin::secret_on_save(' new-key ', 'old')),
	array('old', 'old', 'old', 'old', '', 'new-key'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
}
