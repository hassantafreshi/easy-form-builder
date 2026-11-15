<?php
/**
 * F12 (spec §1, §2, §6, B): Emsfb\Spam on its own, with WordPress stubbed — the rules a submission is judged by.
 *
 *  - settings: a site without the keys has every check off (4.3.1); the values are allow-listed (Persian /
 *    Arabic-Indic digits accepted, 0 … 60 seconds, 0 … 3650 days, a score 0.0 … 1.0, an action spam | reject);
 *  - cfg(): spam_off switches the soft checks off (not the CAPTCHA); payment, register and login forms always reject;
 *    efb_core_spam_checks (Human Shield) may switch the duplicates off;
 *  - soft_checks(): the honeypot filled (or not a string) → honeypot; a missing efb_hp is neutral; one second after
 *    prepare with 3 required → too_fast, five → nothing; no runtime token → neutral unless spam_require_js (no_js);
 *    a token without t0 (issued before 4.3.2) is neutral;
 *  - verdict(): the reasons of every part, efb_spam_verdict may decide (ham / spam / reject), a spam verdict without
 *    reasons says "filter", hostile reasons are dropped;
 *  - the honeypot's markup: hidden from screen readers and the keyboard (aria-hidden, inert, tabindex -1), out of view
 *    without display:none, autocomplete off and the password managers' ignore marks, a name no browser fills, no
 *    emsFormBuilder_v class or data-vid (never in sendBack);
 *  - the content of an answer for Akismet and the disallowed words (author from first / last name, the form's e-mail
 *    field, the first url field, text and textarea);
 *  - the form keys' save rules (sanitize_form_key) and forms/{id}/schema (no secret, no honeypot name);
 *  - the side-effect gate: false while the request's verdict is spam, the value it got otherwise.
 *
 * Run: php tests/test-spam-soft-checks.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_t_filters'] = array();
$GLOBALS['efb_t_settings'] = array();
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['efb_t_filters'][$h]) ? call_user_func_array($GLOBALS['efb_t_filters'][$h], array_slice($a, 1)) : $v; }
function get_setting_Emsfb($m = 'decoded') { return json_decode(json_encode($GLOBALS['efb_t_settings'])); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d = '') { return esc_html($s); }
function home_url() { return 'https://site.test'; }
require __DIR__ . '/../includes/class-Emsfb-captcha.php';
require __DIR__ . '/../includes/class-Emsfb-spam.php';
use Emsfb\Spam;
use Emsfb\Captcha;

$pass = 0; $fail = 0;
function t($name, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] {$name}\n"; }
	else { $fail++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
}
$set = function (array $s) { $GLOBALS['efb_t_settings'] = $s; };

echo "--- settings ---\n";
$set(array());
$c = Spam::cfg(array('type' => 'form'));
t('a site without the keys: every check off, action spam (4.3.1 behaviour)', array($c['honeypot'], $c['min_time'], $c['require_js'], $c['akismet'], $c['disallowed'], $c['action']), array(false, 0, false, false, false, 'spam'));
t('min time: Persian digits ۵ → 5, capped at 60', array(Spam::sanitize_setting('spam_min_time', '۵'), Spam::sanitize_setting('spam_min_time', '٩٩')), array('5', '60'));
t('retention: digits only, at most 3650', array(Spam::sanitize_setting('spam_retention_days', '30 days'), Spam::sanitize_setting('spam_retention_days', '99999')), array('30', '3650'));
t('action and low-score action: allow-listed', array(Spam::sanitize_setting('spam_action', 'reject'), Spam::sanitize_setting('spam_action', 'x'), Spam::sanitize_setting('rc3_low_action', 'block'), Spam::sanitize_setting('rc3_low_action', '')), array('reject', 'spam', 'block', 'spam'));
t('threshold: ۰٫۷ → 0.7, 3 → 1, junk → 0.5', array(Spam::sanitize_setting('rc3Threshold', '۰٫۷'), Spam::sanitize_setting('rc3Threshold', '3'), Spam::sanitize_setting('rc3Threshold', 'abc')), array('0.7', '1', '0.5'));
t('provider: allow-listed, else reCAPTCHA v2', array(Spam::sanitize_setting('captcha_provider', 'turnstile'), Spam::sanitize_setting('captcha_provider', '<x>')), array('turnstile', 'recaptcha_v2'));
// F12b-E: the Turnstile keys are the AdnCTS add-on's (its settings schema checks their shape); not a core setting any more.
t('tsSecretKey is not a core setting any more (the add-on\'s schema checks Turnstile keys)', array(Spam::sanitize_setting('tsSecretKey', '0x4AAAAAAAplaceholder00'), Spam::is_setting_key('tsSecretKey')), array(null, false));

echo "--- the rules of a form ---\n";
$set(array('spam_honeypot' => '1', 'spam_min_time' => '3', 'spam_require_js' => '', 'spam_action' => 'spam'));
$c = Spam::cfg(array('type' => 'form'));
t('a fresh install: honeypot and 3 seconds, save as spam', array($c['honeypot'], $c['min_time'], $c['action']), array(true, 3, 'spam'));
$c = Spam::cfg(array('type' => 'form', 'spam_off' => '1'));
t('spam_off: no soft check at all', array($c['honeypot'], $c['min_time'], $c['require_js'], $c['off']), array(false, 0, false, true));
t('payment, register and login forms always reject; a form with a payment field too', array(Spam::cfg(array('type' => 'payment'))['action'], Spam::cfg(array('type' => 'register'))['action'], Spam::cfg(array('type' => 'login'))['action'], Spam::cfg(array('type' => 'form'), null, array('payment' => true))['action']), array('reject', 'reject', 'reject', 'reject'));
$GLOBALS['efb_t_filters']['efb_core_spam_checks'] = function ($c) { $c['honeypot'] = false; return $c; };
t('efb_core_spam_checks (Human Shield) switches the core honeypot off', Spam::cfg(array('type' => 'form'))['honeypot'], false);
unset($GLOBALS['efb_t_filters']['efb_core_spam_checks']);

echo "--- soft checks ---\n";
$now = 1700000000;
$proof = function ($ago) use ($now) { return array('sid' => 's', 'owner' => 'o', 't0' => $now - $ago); };
$sc = function ($req, $p, $s0 = array('type' => 'form')) use ($now) { return Spam::soft_checks($req, $s0, null, $p, array('now' => $now)); };
t('the hidden field filled → honeypot', $sc(array('efb_hp' => 'http://spam.example', 'runtime_token' => 'x'), $proof(10))['reasons'], array('honeypot'));
t('the hidden field as an array (a bot) → honeypot', $sc(array('efb_hp' => array('a'), 'runtime_token' => 'x'), $proof(10))['reasons'], array('honeypot'));
t('an empty hidden field → nothing', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), $proof(10))['spam'], false);
t('no efb_hp key (an old client or a direct REST call) → neutral', $sc(array('runtime_token' => 'x'), $proof(10))['spam'], false);
t('1 second after prepare with 3 required → too_fast', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), $proof(1))['reasons'], array('too_fast'));
t('5 seconds after → nothing', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), $proof(5))['spam'], false);
t('exactly 3 seconds → nothing (at least the minimum)', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), $proof(3))['spam'], false);
t('a token issued before 4.3.2 (no t0) → neutral', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), array('sid' => 's', 'owner' => 'o'))['spam'], false);
t('no runtime token → the time cannot be measured: neutral', $sc(array('efb_hp' => ''), null)['spam'], false);
t('both: honeypot and too_fast, in this order', $sc(array('efb_hp' => 'x', 'runtime_token' => 'x'), $proof(0))['reasons'], array('honeypot', 'too_fast'));
$set(array('spam_honeypot' => '1', 'spam_min_time' => '3', 'spam_require_js' => '1'));
t('spam_require_js: no runtime token → no_js', $sc(array('efb_hp' => ''), null)['reasons'], array('no_js'));
t('spam_require_js: with a token → nothing', $sc(array('efb_hp' => '', 'runtime_token' => 'x'), $proof(9))['spam'], false);
t('spam_off on the form: nothing even with everything wrong', $sc(array('efb_hp' => 'x'), null, array('type' => 'form', 'spam_off' => '1'))['spam'], false);
$set(array());
t('a site without the keys: the hidden field filled is nothing (no field on its pages)', $sc(array('efb_hp' => 'x', 'runtime_token' => 'x'), $proof(0))['spam'], false);

echo "--- verdict ---\n";
t('no reason → ham', Spam::verdict(array(array('reasons' => array()), array('reasons' => array())), array('action' => 'spam')), array('spam' => false, 'action' => 'none', 'reasons' => array()));
t('reasons of every part, once each', Spam::verdict(array(array('reasons' => array('honeypot')), array('reasons' => array('akismet', 'honeypot'))), array('action' => 'spam')), array('spam' => true, 'action' => 'spam', 'reasons' => array('honeypot', 'akismet')));
t('the form rejects → reject', Spam::verdict(array(array('reasons' => array('too_fast'))), array('action' => 'reject'))['action'], 'reject');
$GLOBALS['efb_t_filters']['efb_spam_verdict'] = function () { return array('verdict' => 'spam', 'reasons' => array('filter')); };
t('efb_spam_verdict decides spam (spec A acceptance)', Spam::verdict(array(array('reasons' => array())), array('action' => 'spam')), array('spam' => true, 'action' => 'spam', 'reasons' => array('filter')));
$GLOBALS['efb_t_filters']['efb_spam_verdict'] = function ($v) { return array('verdict' => 'ham'); };
t('efb_spam_verdict may clear a verdict (ham)', Spam::verdict(array(array('reasons' => array('honeypot'))), array())['spam'], false);
$GLOBALS['efb_t_filters']['efb_spam_verdict'] = function ($v) { return array('verdict' => 'reject', 'reasons' => array('<script>', 'too_fast', 'Too_Fast2')); };
t('a reject verdict; hostile reasons dropped', Spam::verdict(array(array('reasons' => array())), array('action' => 'spam')), array('spam' => true, 'action' => 'reject', 'reasons' => array('too_fast')));
$GLOBALS['efb_t_filters']['efb_spam_verdict'] = function ($v) { return array('verdict' => 'spam'); };
t('spam without reasons → "filter"', Spam::verdict(array(array('reasons' => array())), array())['reasons'], array('filter'));
$GLOBALS['efb_t_filters']['efb_spam_verdict'] = function ($v) { return 'nonsense'; };
t('a filter that returns nonsense changes nothing', Spam::verdict(array(array('reasons' => array('honeypot'))), array('action' => 'spam'))['reasons'], array('honeypot'));
unset($GLOBALS['efb_t_filters']['efb_spam_verdict']);

echo "--- the side-effect gate ---\n";
Spam::reset();
t('no verdict: the gate passes the value it got', array(Spam::allow_side_effect(true), Spam::allow_side_effect(false)), array(true, false));
Spam::mark(array('honeypot'));
t('a spam verdict: false for every channel', array(Spam::allow_side_effect(true, array('channel' => 'email')), Spam::allow_side_effect(true, array('channel' => 'webhook'))), array(false, false));
Spam::reset();

echo "--- the honeypot markup ---\n";
$set(array('spam_honeypot' => '1'));
$hp = Spam::honeypot_html(12, array('type' => 'form'));
t('aria-hidden and inert (no screen reader, no keyboard)', (bool) preg_match('/^<div class="efb efb-hp" aria-hidden="true" inert /', $hp), true);
t('out of view without display:none (logical properties)', strpos($hp, 'inset-inline-start:-10000px') !== false && stripos($hp, 'display:none') === false && stripos($hp, 'display: none') === false, true);
t('the input: tabindex -1, autocomplete off, the password managers\' marks', strpos($hp, 'tabindex="-1"') !== false && strpos($hp, 'autocomplete="off"') !== false && strpos($hp, 'data-lpignore="true"') !== false && strpos($hp, 'data-1p-ignore') !== false && strpos($hp, 'data-bwignore') !== false, true);
t('a name no browser fills (efb_url_12), id efb-hp-12, a label', strpos($hp, 'name="efb_url_12"') !== false && strpos($hp, 'id="efb-hp-12"') !== false && strpos($hp, '<label for="efb-hp-12">Leave this field empty</label>') !== false, true);
t('never in sendBack: no emsFormBuilder_v class, no data-vid', strpos($hp, 'emsFormBuilder_v') === false && strpos($hp, 'data-vid') === false, true);
t('none with spam_off or on a site without the setting', array(Spam::honeypot_html(12, array('type' => 'form', 'spam_off' => '1')), (function () use ($set) { $set(array()); return Spam::honeypot_html(12, array('type' => 'form')); })()), array('', ''));

echo "--- the answer for Akismet and the disallowed words ---\n";
$fields = array(array('type' => 'form', 'email_to' => 'em2'), array('id_' => 'f', 'type' => 'text', 'elementId' => 'firstName', 'name' => 'First'), array('id_' => 'l', 'type' => 'text', 'elementId' => 'lastName', 'name' => 'Last'),
	array('id_' => 'em1', 'type' => 'email', 'name' => 'Other e-mail'), array('id_' => 'em2', 'type' => 'email', 'name' => 'E-mail'), array('id_' => 'u', 'type' => 'url', 'name' => 'Site'), array('id_' => 'm', 'type' => 'textarea', 'name' => 'Message'), array('id_' => 'p', 'type' => 'password', 'name' => 'Password'));
$items = array(array('id_' => 'f', 'value' => 'Sara'), array('id_' => 'l', 'value' => 'Ahmadi'), array('id_' => 'em1', 'value' => 'a@x.test'), array('id_' => 'em2', 'value' => 'sara@x.test'), array('id_' => 'u', 'value' => 'https://x.test'), array('id_' => 'm', 'value' => "Hello\n<b>there</b>"), array('id_' => 'p', 'value' => 'secret'), array('type' => 'w_link', 'value' => 'https://site.test/'));
$p = Spam::payload($items, $fields);
t('author from first + last name, the form\'s own e-mail field, the first url, text and textarea only (never a password)', $p, array('author' => 'Sara Ahmadi', 'email' => 'sara@x.test', 'url' => 'https://x.test', 'content' => "Sara\nAhmadi\nHello\nthere"));
$p2 = Spam::payload(array(array('id_' => 'n', 'value' => 'علی'), array('id_' => 'm', 'value' => 'hi')), array(array('type' => 'form'), array('id_' => 'n', 'type' => 'text', 'name' => 'نام'), array('id_' => 'm', 'type' => 'textarea', 'name' => 'Msg')));
t('without name fields: the first text field labelled "name" (any language)', $p2['author'], 'علی');

echo "--- form keys and the schema ---\n";
// F12b: any well-formed id is kept (an add-on switched off must not wipe the form's choice); a malformed one is dropped.
t('captcha_provider: a well-formed id ("" = the site\'s)', array(Spam::sanitize_form_key('captcha_provider', 'hcaptcha'), Spam::sanitize_form_key('captcha_provider', 'evil'), Spam::sanitize_form_key('captcha_provider', '<x>'), Spam::sanitize_form_key('captcha_provider', 'Turnstile')), array('hcaptcha', 'evil', '', ''));
t('rc3_threshold: ۰٫۳ → 0.3; junk → ""', array(Spam::sanitize_form_key('rc3_threshold', '۰٫۳'), Spam::sanitize_form_key('rc3_threshold', 'x')), array('0.3', ''));
t('spam_off / spam_akismet flags', array(Spam::sanitize_form_key('spam_off', true), Spam::sanitize_form_key('spam_off', '1'), Spam::sanitize_form_key('spam_off', 'no'), Spam::sanitize_form_key('spam_akismet', '0'), Spam::sanitize_form_key('spam_akismet', '1')), array('1', '1', '', '0', ''));
$set(array('spam_honeypot' => '1', 'spam_min_time' => '4', 'captcha_provider' => 'turnstile'));
$sch = Spam::form_schema(array('type' => 'form', 'captcha' => '1'));
t('schema without the Turnstile add-on: asked turnstile, no Google keys → soft checks (honeypot, 4 s), no widget', array($sch['honeypot'], $sch['min_time'], $sch['captcha']['provider'], $sch['captcha']['ready'], $sch['captcha']['widget'], $sch['captcha']['fallback']), array(true, 4, 'turnstile', false, 'none', 'soft_checks'));
// F12b-E: with the add-on (AdnCTS) and its keys (Cloudflare's public test keys).
$efb_v = is_dir(__DIR__ . '/../vendor/turnstile') ? __DIR__ . '/../vendor' : dirname(__DIR__, 2) . '/vendores-efb';
require_once $efb_v . '/turnstile/class-Emsfb-turnstile-provider.php';
\Emsfb\Captcha\Registry::add(new \Emsfb\Captcha\Turnstile_Provider());
\Emsfb\Captcha\Registry::set_stored('turnstile', array('public' => array('site_key' => '1x00000000000000000000AA'), 'secret' => array('secret' => '1x0000000000000000000000000000000AA')));
$sch = Spam::form_schema(array('type' => 'form', 'captcha' => '1'));
t('schema: what protects the form (honeypot, 4 s, Turnstile ready, visible widget)', array($sch['honeypot'], $sch['min_time'], $sch['captcha']['provider'], $sch['captcha']['ready'], $sch['captcha']['widget']), array(true, 4, 'turnstile', true, 'visible'));
$js = json_encode($sch);
t('schema: no secret and no honeypot name', strpos($js, '0000000000AA') === false && strpos($js, 'efb_url_') === false && strpos($js, 'efb_hp') === false, true);
t('schema: a form without CAPTCHA says so', Spam::form_schema(array('type' => 'form'))['captcha'], array('on' => false, 'provider' => null, 'effective_provider' => null, 'kind' => null, 'ready' => null, 'widget' => null, 'fallback' => null, 'why' => null));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
