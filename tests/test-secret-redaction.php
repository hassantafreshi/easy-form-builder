<?php
/**
 * F12b D4 (§D4) on the real WordPress: secret keys are write-only. Before F12b the whole settings row (the reCAPTCHA
 * v2 / v3 secrets, the Stripe and PayPal secrets) was printed into ajax_object_efm.setting / efb_var.setting and the
 * v2 secret into a text box with its value.
 *
 *  - every EFB screen (forms list and settings, builder, add-ons), the dashboard, the block editor, a public form
 *    page and the tracking box, forms/{id}/schema and a form package: none of the test secrets appears; the screens
 *    get {"set":true,"last4":…} instead (last four only from 16 characters);
 *  - the settings save (admin-ajax set_settings_Emsfb, what the screen sends): the redacted objects, an empty box and
 *    a mask keep the stored secret; a typed key replaces it; "__clear__" removes it; a CAPTCHA still verifies after
 *    a save; another user cannot save.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-secret-redaction.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '1');
$H->ctlb('mu');
$H->ctlb('mock', '1');
$H->ctlb('rl-reset', 'cptk');
$mark = 'E2ESECRET' . strtoupper(bin2hex(random_bytes(3)));
$sec = array('secretKey' => $mark . 'V2xxxxxxxxxxAAAA', 'rc3SecretKey' => $mark . 'V3xxxxxxxxxxBBBB', 'rciSecretKey' => 'short-key-12', 'stripeSKey' => 'sk_test_' . $mark . 'STRIPExxxxCCCC', 'paypalSKey' => $mark . 'PAYPALxxxxxDDDD');
$q = 'siteKey=e2e-v2-site-placeholder&rc3SiteKey=e2e-v3-site-placeholder&rciSiteKey=e2e-v2i-site-placeholder&captcha_provider=recaptcha_v2&scaptcha=1&spam_min_time=0&stripePKey=pk_test_e2e_public_placeholder&paypalPKey=e2e-paypal-public-placeholder';
foreach ($sec as $k => $v) $q .= '&' . $k . '=' . urlencode($v);
$H->ctl('f12-setting', $q);
$F = $H->ctl('f03-form', 'F12b secrets', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1');
$tp = $H->ctl('f12-page-tracker');

echo "--- no secret anywhere ---\n";
$nonce = $H->admin();
$pages = array('forms list' => '/wp-admin/admin.php?page=Emsfb', 'settings' => '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', 'builder' => '/wp-admin/admin.php?page=Emsfb_create', 'add-ons' => '/wp-admin/admin.php?page=Emsfb_addon',
	'dashboard' => '/wp-admin/index.php', 'block editor' => '/wp-admin/post-new.php?post_type=page', 'schema' => '/wp-json/Emsfb/v1/forms/' . (int) $F['form_id'] . '/schema');
$bodies = array();
foreach ($pages as $name => $url) {
	$r = $H->http('GET', $url, null, $name === 'schema' ? array('X-WP-Nonce' => $nonce) : array(), false);
	$bodies[$name] = $r['body'];
	t("{$name} (HTTP {$r['status']}): no secret in it", array($r['status'] < 400, strpos($r['body'], $mark) === false), array(true, true));
}
t('settings: the screen gets {"set":true,"last4":"AAAA"} for the v2 secret and the Stripe secret', array(strpos($bodies['settings'], '\\"secretKey\\":{\\"set\\":true,\\"last4\\":\\"AAAA\\"}') !== false || strpos($bodies['settings'], '"secretKey":{"set":true,"last4":"AAAA"}') !== false, strpos($bodies['settings'], 'CCCC') !== false), array(true, true));
t('settings: a secret under 16 characters gets {"set":true} only (no last four)', array(strpos($bodies['settings'], 'short-key-12') === false, (bool) preg_match('/\\\\?"rciSecretKey\\\\?":\{\\\\?"set\\\\?":true\}/', $bodies['settings'])), array(true, true));
t('settings: the public keys are still there', strpos($bodies['settings'], 'e2e-v2-site-placeholder') !== false && strpos($bodies['settings'], 'pk_test_e2e_public_placeholder') !== false, true);
$H->fresh_jar();
foreach (array('public form' => $H->page((int) $F['form_id']), 'tracking box' => parse_url($tp['url'], PHP_URL_PATH) . (parse_url($tp['url'], PHP_URL_QUERY) ? '?' . parse_url($tp['url'], PHP_URL_QUERY) : '')) as $name => $url) {
	$r = $H->http('GET', $url, null, array(), false);
	t("{$name}: no secret; the site key is there", array(strpos($r['body'], $mark) === false, strpos($r['body'], 'e2e-v2-site-placeholder') !== false), array(true, true));
}
$pkg = $H->ctl('f15-pkg-export', (string) $F['form_id']);
t('form package: no secret, no secret key name', array(strpos(json_encode($pkg), $mark) === false, strpos(json_encode($pkg), 'secretKey') === false), array(true, true));

echo "--- the save keeps, replaces, removes ---\n";
$nonce = $H->admin();
$save = function ($patch) use ($H, &$nonce) {
	$row = $H->ctlb('settings-redacted');
	foreach ($patch as $k => $v) $row[$k] = $v;
	$r = $H->ajax(array('action' => 'set_settings_Emsfb', 'nonce' => $nonce, 'message' => json_encode($row)));
	$j = json_decode($r['body'], true);
	return is_array($j) && !empty($j['data']['success']);
};
t('the screen\'s own data sent back (secrets as {"set":true,…}): saved, every secret kept', array($save(array()), $H->ctlb('secret', 'secretKey', $sec['secretKey'])['equals'], $H->ctlb('secret', 'stripeSKey', $sec['stripeSKey'])['equals'], $H->ctlb('secret', 'paypalSKey', $sec['paypalSKey'])['equals'], $H->ctlb('secret', 'rc3SecretKey', $sec['rc3SecretKey'])['equals']), array(true, true, true, true, true));
$save(array('secretKey' => '', 'stripeSKey' => '•••• CCCC'));
t('an empty box and a mask keep the stored secret', array($H->ctlb('secret', 'secretKey', $sec['secretKey'])['equals'], $H->ctlb('secret', 'stripeSKey', $sec['stripeSKey'])['equals']), array(true, true));
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit((int) $F['form_id'], array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'x')), array('valid' => EfbF12bHttp::tok())));
t('a CAPTCHA still verifies after the save (the secret is the stored one)', !empty($a['success']), true);
$nonce = $H->admin();
$new = $mark . 'NEWxxxxxxxxxxEEEE';
$save(array('secretKey' => $new));
t('a typed key replaces it', $H->ctlb('secret', 'secretKey', $new)['equals'], true);
$save(array('paypalSKey' => '__clear__'));
t('"__clear__" removes it', $H->ctlb('secret', 'paypalSKey')['set'], false);
$H->ctl('user', 'efb_f12b_editor', 'editor');
$H->fresh_jar();
$H->login('efb_f12b_editor', 'efb_f12b_editor-pass-123');
$en = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
$row = $H->ctlb('settings-redacted'); $row['secretKey'] = 'editor-tries-this-key';
$r = $H->ajax(array('action' => 'set_settings_Emsfb', 'nonce' => $en, 'message' => json_encode($row)));
t('an editor cannot save the settings (the secret stays)', $H->ctlb('secret', 'secretKey', $new)['equals'], true);

$H->ctl('f12-delete-form', (string) $F['form_id']);
$del = 'captcha_provider=del&scaptcha=del&rciSiteKey=del&rc3SiteKey=del&siteKey=del&stripePKey=del&paypalPKey=del';
foreach (array_keys($sec) as $k) $del .= '&' . $k . '=del';
$H->ctl('f12-setting', $del);
$H->ctlb('mock', '0');
efb_done();
