<?php
/**
 * The CAPTCHA add-on's own settings screen (admin.php?page=Emsfb_captcha&provider=<id>, F12b-L) shows only its own
 * notices, on the real WordPress (the e2e site), through HTTP as an administrator.
 *
 * BUG (F20-core brief): Easy Form Builder's site-wide notices — the SPLIT add-on notice, "payment setup incomplete"
 * (PAY00 readiness), the Data Tools card … — were printed above the focused screen's form. Now:
 *  - the plugin's panel still shows them (a payment form without keys brings the readiness notice);
 *  - the screen shows none of them, nor a notice of another Easy Form Builder add-on (a closure of the Emsfb namespace,
 *    as the Human Shield add-on hooks it), but keeps its own ("Settings saved.", in its box), and another plugin's
 *    notice (WordPress' convention for every admin screen);
 *  - the not-found screen of an add-on that is not on shows only its own message too.
 *
 * Final test (c): the same rule for any add-on settings screen through core's efb_addon_screen_own_notices(): the
 * Address & Postal add-on's "Address search" screen (admin.php?page=Emsfb_adr_lookup) showed the SPLIT notice and
 * "payment setup incomplete" above its form.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-captcha-screen-notices-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$mu = rtrim($H->site, '/') . '/wp-content/mu-plugins/efb-e2e-cps-notices.php';
// A stand-in of another plugin's notice and of an Easy Form Builder add-on's notice (a closure in the Emsfb namespace).
file_put_contents($mu, '<?php
namespace Emsfb { add_action("admin_notices", function () { echo "<div class=\"notice notice-info efb-notice efb-addon-probe\"><p>An add-on notice</p></div>"; }); }
namespace { function efb_e2e_cps_other_plugin_notice() { echo "<div class=\"notice notice-info other-plugin-probe\"><p>Another plugin</p></div>"; } add_action("admin_notices", "efb_e2e_cps_other_plugin_notice"); }
');
$made = array();
try {
	$H->ctl('plan', '3');
	$H->ctl('addon-flag', 'AdnCTS', '1');
	$H->ctl('addon-flag', 'AdnHCP', '0');
	$H->ctlb('cpv', 'turnstile', json_encode(array('site_key' => '1x00000000000000000000AA')), json_encode(array('secret' => '1x0000000000000000000000000000000AA')));
	$H->ctl('pay-mock', '1');
	$H->ctl('pay-clear-keys');
	$made[] = $H->ctl('pay-form', 'fixed', 'Notices probe');
	$H->admin();
	$get = function ($q) use ($H) { return $H->http('GET', '/wp-admin/admin.php?' . $q, null, array(), false); };
	/* The notices of a page: the class / id of each .notice, and whether it sits inside the screen's own box. */
	$notices = function ($html) {
		$out = array('inside' => array(), 'outside' => array());
		$box = strpos($html, 'id="efbCaptchaScreen"');
		if (preg_match_all('#<div[^>]*class="([^"]*\bnotice\b[^"]*)"[^>]*>#', $html, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[1] as $i => $c) {
				$tag = $m[0][$i][0];
				$name = preg_match('/\bid="([^"]+)"/', $tag, $mm) ? $mm[1] : implode(' ', array_filter(preg_split('/\s+/', trim($c[0])), function ($k) { return !preg_match('/^(notice|notice-[\w-]+|inline|is-dismissible|efb|efb-notice)$/', $k); }));
				$out[($box !== false && $c[1] > $box) ? 'inside' : 'outside'][] = $name;
			}
		}
		return $out;
	};

	$panel = $notices($get('page=Emsfb')['body']);
	t('the plugin\'s panel: the payment readiness notice, the add-on\'s notice and the other plugin\'s notice', array(in_array('efbPayNotReadyNotice', $panel['outside'], true), in_array('efb-addon-probe', $panel['outside'], true), in_array('other-plugin-probe', $panel['outside'], true)), array(true, true, true));

	$r = $get('page=Emsfb_captcha&provider=turnstile&efb_msg=saved');
	$sc = $notices($r['body']);
	t('the add-on\'s screen (200): no site-wide notice of Easy Form Builder or of another of its add-ons above the form', array($r['status'], array_values(array_filter($sc['outside'], function ($n) { return $n !== 'other-plugin-probe'; }))), array(200, array()));
	t('… its own notice is there ("Settings saved.", in its box)', array(count($sc['inside']), strpos($r['body'], 'Settings saved.') !== false), array(1, true));
	t('… another plugin\'s notice stays (as on every admin screen)', in_array('other-plugin-probe', $sc['outside'], true), true);

	$r = $get('page=Emsfb_captcha&provider=hcaptcha');
	$nf = $notices($r['body']);
	t('the screen of an add-on that is not on (404): only its own "not installed or not active"', array($r['status'], array_values(array_filter($nf['outside'], function ($n) { return $n !== 'other-plugin-probe'; })), count($nf['inside'])), array(404, array(), 1));

	// The Address & Postal add-on's "Address search" screen (another add-on's settings screen, its own folder).
	$c17 = function (...$a) use ($H) {
		$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f17.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
		shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null');
	};
	$c17('adr', '1');
	$r = $get('page=Emsfb_adr_lookup');
	$ad = $notices($r['body']);
	t('the "Address search" screen of the Address & Postal add-on (200, its form): no site-wide notice of Easy Form Builder or of another of its add-ons', array($r['status'], strpos($r['body'], 'id="efbAdrForm"') !== false, array_values(array_filter($ad['outside'], function ($n) { return $n !== 'other-plugin-probe'; }))), array(200, true, array()));
	t('… another plugin\'s notice stays there too', in_array('other-plugin-probe', $ad['outside'], true), true);
	t('core\'s helper is one function the CAPTCHA screen and the add-on both call', array(strpos(file_get_contents($H->root . '/includes/captcha/class-Emsfb-captcha-screen.php'), 'efb_addon_screen_own_notices(') !== false, function_exists('efb_addon_screen_own_notices') || strpos(file_get_contents($H->root . '/includes/addon-contracts-functions-efb.php'), "function efb_addon_screen_own_notices(") !== false), array(true, true));

	$panel2 = $notices($get('page=Emsfb')['body']);
	t('the panel afterwards still shows them (nothing removed for good)', in_array('efbPayNotReadyNotice', $panel2['outside'], true) && in_array('efb-addon-probe', $panel2['outside'], true), true);
} finally {
	@unlink($mu);
	foreach ($made as $f) $H->ctl('f12-delete-form', (string) ($f['form_id'] ?? 0));
	$H->ctl('pay-keys', 'test');
	$H->ctl('addon-flag', 'AdnCTS', '0');
}
efb_done();
