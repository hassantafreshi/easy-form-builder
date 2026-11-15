<?php
/**
 * F12-F coexistence and script loading (spec §7, F):
 *
 * Standalone (source and stubs):
 *  - check_and_enqueue_google_captcha_efb() no longer asks Google while a page is built (no wp_remote_head) and is a
 *    wrapper of Captcha::enqueue('recaptcha_v2');
 *  - the CAPTCHA services' script handles are kept out of optimizers (Cache_Compat::exclude_handles);
 *  - Spam registers its side-effect gate at priority 1, before Human Shield's stop-loss (10), which returns early on
 *    false and spends nothing (the add-on's code is read from vendores-efb when it is there);
 *  - the hooks a security add-on uses exist: efb_core_spam_checks, efb_spam_verdict.
 * On the e2e site (when EFB_E2E_SITE_DIR is set):
 *  - Shield Security silentCAPTCHA (shield_test_ip_is_bot stand-in says "bot"): refused with errorMRobot even with a
 *    solved CAPTCHA; "human": accepted; both gates must pass;
 *  - efb_core_spam_checks switching the honeypot off (what Human Shield may do): no hidden field on the page and a
 *    filled efb_hp is not spam;
 *  - a CAPTCHA page renders with the provider's script enqueued (deferred, Google's URL as it is) without any request
 *    to Google while it is built; the plugin's script and the provider's are not delayed.
 *
 * Run: php tests/test-spam-coexistence.php   (site part under the e2e lock with the e2e env)
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$root = dirname(__DIR__);
$fn = file_get_contents($root . '/includes/functions.php');
preg_match('/public function check_and_enqueue_google_captcha_efb\(\$lang\) \{.*?\n\t\}\n/s', $fn, $m);
$body = $m ? preg_replace('#/\*.*?\*/#s', '', $m[0]) : ''; // the code, not its comment
t('check_and_enqueue_google_captcha_efb(): no wp_remote_head (no request to Google while a page is built)', $body !== '' && strpos($body, 'wp_remote_head') === false && strpos($body, 'wp_remote_get') === false, true);
t('…it hands over to Captcha::enqueue(\'recaptcha_v2\')', strpos($body, "\\Emsfb\\Captcha::enqueue('recaptcha_v2'") !== false, true);
$cc = file_get_contents($root . '/includes/class-Emsfb-cache-compat.php');
// F12b: every provider's own script handle (efb-recaptcha, efb-cp-<id> of the add-ons), not a fixed list.
t('Cache_Compat keeps efb-recaptcha and every efb-cp-<id> provider script out of optimizers', strpos($cc, "\$cap_handle === 'efb-recaptcha' || strpos((string) \$cap_handle, 'efb-cp-') === 0") !== false, true);
$sp = file_get_contents($root . '/includes/class-Emsfb-spam.php');
t('Spam gates side effects at priority 1 (before Human Shield\'s stop-loss)', strpos($sp, "add_filter('efb_shield_allow_side_effect', array(__CLASS__, 'allow_side_effect'), 1, 2);") !== false, true);
t('the hooks for a security add-on: efb_core_spam_checks, efb_spam_verdict', strpos($sp, "apply_filters('efb_core_spam_checks'") !== false && strpos($sp, "apply_filters('efb_spam_verdict'") !== false, true);
$pub = file_get_contents($root . '/includes/class-Emsfb-public.php');
preg_match('/static \$attribute_map_efb = \[(.*?)\];/s', $pub, $am);
t('filter_attributes_by_type_efb(): no CAPTCHA row type and no efb_hp key survive into a stored answer', isset($am[1]) && stripos($am[1], 'captcha') === false && strpos($am[1], 'efb_hp') === false, true);
$hs = '/home/user/vendores-efb/human-shield/class-Emsfb-human-shield-notification-gate.php';
if (is_readable($hs)) {
	$g = file_get_contents($hs);
	t('Human Shield\'s gate (priority 10) returns early when a gate before it said false: its daily stop-loss is not spent on spam', strpos($g, "add_filter( 'efb_shield_allow_side_effect', array( \$this, 'allow_side_effect' ), 10, 2 );") !== false && (bool) preg_match('/if \( ! \$allowed \|\| ! \$this->shield->is_enabled\(\) \) \{\s*return \$allowed;/', $g), true);
} else {
	echo "[SKIP] Human Shield's code is not here\n";
}

require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) {
	echo "[SKIP] site part: {$why}\n";
} else {
	$H->ctl('plan', '3');
	$H->ctl('f12-mu');
	$H->ctl('set-option', 'efb_e2e_f12_mock', '1');
	// F12b-E: hCaptcha is the AdnHCP add-on now: switched on, hCaptcha's official test keys in its option.
	$H->ctl('addon-flag', 'AdnHCP', '1');
	$H->ctlb('cpv', 'hcaptcha', json_encode(array('site_key' => '10000000-ffff-ffff-ffff-000000000001')), json_encode(array('secret' => '0x0000000000000000000000000000000000000000')));
	$H->ctl('f12-setting', 'spam_min_time=0&spam_honeypot=1&siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder');
	$F = $H->ctl('f03-form', 'F12 shield', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1&captcha_provider=hcaptcha&shield_silent_captcha=1');
	$fid = (int) $F['form_id'];
	$send = function ($tok, $extra = array(), $id = null, $key = 'nm', $ids = null) use ($H, $fid, $F) { $ids = $ids ?: $F['ids']; $H->fresh_jar(); return EfbF11Http::answer($H->submit($id ?: $fid, array(array('id_' => $ids[$key], 'name' => 'Name', 'type' => 'text', 'value' => 'x')), array_merge(array('valid' => $tok !== '' ? $tok . bin2hex(random_bytes(6)) : '', 'efb_hp' => ''), $extra))); }; // F12b: one use per token
	$H->ctl('set-option', 'efb_e2e_f12_shield', 'bot');
	$a = $send('pass-' . str_repeat('s', 30));
	t('site: Shield Security says bot → refused with the robot message even with a solved CAPTCHA, nothing stored', array(empty($a['success']), count((array) $H->ctl('f14-entries', (string) $fid))), array(true, 0));
	$H->ctl('set-option', 'efb_e2e_f12_shield', 'human');
	$a = $send('fail-' . str_repeat('s', 30));
	t('site: Shield says human but the CAPTCHA failed → refused (both gates must pass)', array(empty($a['success']), $a['code'] ?? ''), array(true, 'efb_captcha_failed'));
	$a = $send('pass-' . str_repeat('s', 30));
	t('site: Shield says human and the CAPTCHA passed → accepted', !empty($a['success']), true);
	$H->ctl('set-option', 'efb_e2e_f12_shield', 'del');

	/* Human Shield may switch the core honeypot off */
	$N = $H->ctl('f03-form', 'F12 hs', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
	$H->ctl('set-option', 'efb_e2e_f12_hs_off', '1');
	$page = $H->http('GET', $H->page((int) $N['form_id']), null, array(), false);
	t('site: efb_core_spam_checks without the honeypot → no hidden field on the page', strpos($page['body'], 'efb-hp-' . $N['form_id']) === false, true);
	$a = $send('', array('efb_hp' => 'bot'), (int) $N['form_id'], 'nm', $N['ids']);
	$e = $H->ctl('f14-entries', (string) $N['form_id']);
	t('site: …and a filled efb_hp is not spam then', array(!empty($a['success']), (int) ($e[0]['entry_status'] ?? -1)), array(true, 0));
	$H->ctl('set-option', 'efb_e2e_f12_hs_off', 'del');
	$page = $H->http('GET', $H->page((int) $N['form_id']), null, array(), false);
	t('site: without the filter the hidden field is back', strpos($page['body'], 'efb-hp-' . $N['form_id']) !== false, true);

	/* scripts */
	$V2 = $H->ctl('f03-form', 'F12 v2 page', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1');
	$H->ctl('f12-calls', 'clear'); // only what rendering does (earlier tests leave their siteverify calls in the log)
	$t0 = microtime(true);
	$page = $H->http('GET', $H->page((int) $V2['form_id']), null, array(), false);
	$ms = (int) ((microtime(true) - $t0) * 1000);
	$tag = function ($html, $needle) { return preg_match('/<script\b[^>]*src="' . preg_quote($needle, '/') . '[^"]*"[^>]*>/', $html, $mm) ? $mm[0] : ''; };
	$g = $tag($page['body'], 'https://www.google.com/recaptcha/api.js');
	t('site: a reCAPTCHA v2 page enqueues Google\'s script (render=explicit, the page language, deferred, no version added)', $g !== '' && (bool) preg_match('/\?hl=[A-Za-z\-]+&(#038;|amp;)?render=explicit"/', $g) && (bool) preg_match('/\sdefer[\s>]/', $g) && strpos($g, 'ver=') === false, true);
	// F12b-C: .g-recaptcha (data-efb-legacy="gRecaptcha") instead of the id two forms on a page repeated.
	t('site: …the widget keeps .g-recaptcha (data-efb-legacy="gRecaptcha") and gets a label and a live region', strpos($page['body'], 'class="efb efb-captcha g-recaptcha') !== false && strpos($page['body'], 'data-efb-legacy="gRecaptcha"') !== false && strpos($page['body'], 'aria-live="assertive"') !== false, true);
	t('site: …and the plugin\'s captcha runtime', strpos($page['body'], 'public/assets/js/captcha-efb.js') !== false, true);
	$page = $H->http('GET', $H->page($fid), null, array(), false);
	$hc = $tag($page['body'], 'https://js.hcaptcha.com/1/api.js');
	t('site: an hCaptcha page enqueues js.hcaptcha.com (explicit render, the page language, deferred) and not Google\'s script', $hc !== '' && (bool) preg_match('/\?render=explicit&(#038;|amp;)?hl=[a-z]+/', $hc) && (bool) preg_match('/\sdefer[\s>]/', $hc) && strpos($page['body'], 'google.com/recaptcha/api.js') === false, true);
	$calls = (array) $H->ctl('f12-calls');
	t('site: rendering asked no CAPTCHA service anything (no siteverify, no HEAD)', count(array_filter($calls, function ($c) { return isset($c['url']) && strpos($c['url'], 'google') !== false; })), 0);
	foreach (array($fid, (int) $N['form_id'], (int) $V2['form_id']) as $id) $H->ctl('f12-delete-form', (string) $id);
	$H->ctl('set-option', 'efb_e2e_f12_mock', 'del');
	$H->ctl('f12-setting', 'spam_min_time=del&spam_honeypot=del&siteKey=del&secretKey=del');
	$H->ctl('addon-flag', 'AdnHCP', '0');
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
