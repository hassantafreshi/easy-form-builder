<?php
/**
 * F12b-L on the real WordPress (the e2e site): each CAPTCHA add-on's own settings screen and its storage in the main
 * settings JSON, through HTTP as an administrator.
 *
 *  - the screen (admin.php?page=Emsfb_captcha&provider=<id>): title, back link, the add-on's fields from its schema,
 *    the saved secret as an empty password box with its mask; Save (admin-post.php, nonce) → "Settings saved." and
 *    only captcha_<id> of the row changes (secret encrypted); a value of the wrong shape → "Not saved: …"; a bad
 *    nonce is refused; no secret and no cipher in the page;
 *  - not installed / switched off / a Google mode / unknown → HTTP 404 with "not installed or not active";
 *  - the main Settings window's save (admin-ajax set_settings_Emsfb) keeps every add-on key, also when the request
 *    sends something under them;
 *  - the old AJAX save (efb_captcha_provider_save) writes the same key and answers without a secret;
 *  - the one-time migration from emsfb_cpv_<id> runs on the next request (row written, option and page map gone);
 *  - uninstall (Admin::remove) clears only its own key; switching an add-on off keeps its key.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-captcha-addon-settings-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$TS = array('site_key' => '1x00000000000000000000AA', 'secret' => '1x0000000000000000000000000000000AA');
$H->ctl('plan', '3');
foreach (array('AdnCTS', 'AdnALT') as $a) $H->ctl('addon-flag', $a, '1');
$H->ctl('addon-flag', 'AdnHCP', '0');
$H->ctlb('cpv', 'turnstile', json_encode(array('site_key' => $TS['site_key'])), json_encode(array('secret' => $TS['secret'])));
$keys = function () use ($H) { return $H->ctlb('row-keys'); };
$H->admin();
$screen = function ($id) use ($H) { return $H->http('GET', '/wp-admin/admin.php?page=Emsfb_captcha&provider=' . rawurlencode($id), null, array(), false); };

echo "--- the screen ---\n";
$r = $screen('turnstile');
$b = $r['body'];
t('200; the title with the add-on\'s name isolated (bdi), the back link to Settings › Captchas, Save', array($r['status'], (bool) preg_match('#<h1><bdi>Cloudflare Turnstile</bdi> settings</h1>#', $b), (bool) preg_match('#<a href="[^"]*page=Emsfb(&|&amp;|&\#038;)state=setting(&|&amp;|&\#038;)tab=captchas">Back to Settings</a>#', $b), (bool) preg_match('#<button type="submit" class="button button-primary" id="efbCpsSave">Save</button>#', $b)), array(200, true, true, true));
t('the fields from the schema, each labelled: Site Key (text, ltr) and Secret Key (password, empty, its mask, "Remove the saved key")', array((bool) preg_match('#<label for="efbcps_turnstile_site_key">Site Key</label>#', $b), (bool) preg_match('#id="efbcps_turnstile_site_key" name="pub\[site_key\]" value="1x00000000000000000000AA"#', $b), (bool) preg_match('#<input type="password"[^>]*id="efbcps_turnstile_secret" name="sec\[secret\]" value="" placeholder="Saved \(•••• 00AA\)"#u', $b), strpos($b, 'name="clear[secret]"') !== false), array(true, true, true, true));
t('no secret and no cipher anywhere in the page', array(strpos($b, $TS['secret']), strpos($b, 'enc:v1:')), array(false, false));
t('status Ready', (bool) preg_match('#data-status="ready">Ready<#', $b), true);
preg_match('#name="_wpnonce" value="([a-f0-9]{10})"#', $b, $m);
$nonce = $m[1] ?? '';
$before = $keys();
$post = function (array $f) use ($H) { return $H->http('POST', '/wp-admin/admin-post.php', http_build_query($f), array('Content-Type' => 'application/x-www-form-urlencoded'), false); };
$r = $post(array('action' => 'efb_captcha_settings', 'provider' => 'turnstile', '_wpnonce' => $nonce, 'pub' => array('site_key' => '3x00000000000000000000FF'), 'sec' => array('secret' => '')));
$loc = preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $mm) ? $mm[1] : '';
$after = $keys();
t('Save: back to the screen with efb_msg=saved', array($r['status'], strpos($loc, 'page=Emsfb_captcha&provider=turnstile&efb_msg=saved') !== false), array(302, true));
t('… captcha_turnstile has the new site key, the secret kept (encrypted); every other key of the row is as it was', array($after['captcha_turnstile']['public']['site_key'], $after['captcha_turnstile']['secrets'], $after['captcha_turnstile']['encrypted'], array_diff_key($after, array('captcha_turnstile' => 1)) == array_diff_key($before, array('captcha_turnstile' => 1))), array('3x00000000000000000000FF', array('secret'), true, true));
$b = $screen('turnstile')['body'];
$b2 = $H->http('GET', '/wp-admin/admin.php?page=Emsfb_captcha&provider=turnstile&efb_msg=saved', null, array(), false)['body'];
t('… "Settings saved." (efb-notice, role status)', (bool) preg_match('#<div class="notice notice-success inline efb-notice" role="status"><p>Settings saved\.</p></div>#', $b2), true);
$r = $post(array('action' => 'efb_captcha_settings', 'provider' => 'turnstile', '_wpnonce' => $nonce, 'pub' => array('site_key' => 'not a key!'), 'sec' => array('secret' => 'also bad')));
$loc = preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $mm) ? $mm[1] : '';
$b3 = $H->http('GET', parse_url($loc, PHP_URL_PATH) . '?' . parse_url($loc, PHP_URL_QUERY), null, array(), false)['body'];
t('values of the wrong shape: refused, said by name ("Not saved: Site Key, Secret Key"), the stored ones kept', array(strpos($loc, 'refused=site_key%2Csecret') !== false || strpos($loc, 'refused=site_key,secret') !== false, strpos($b3, 'Not saved: Site Key, Secret Key') !== false, $keys()['captcha_turnstile']['public']['site_key']), array(true, true, '3x00000000000000000000FF'));
$r = $post(array('action' => 'efb_captcha_settings', 'provider' => 'turnstile', '_wpnonce' => 'bad0000000', 'pub' => array('site_key' => '2x00000000000000000000AB')));
t('a bad nonce is refused, nothing written', array($r['status'] >= 400, $keys()['captcha_turnstile']['public']['site_key']), array(true, '3x00000000000000000000FF'));
$r = $post(array('action' => 'efb_captcha_settings', 'provider' => 'turnstile', '_wpnonce' => $nonce, 'pub' => array('site_key' => $TS['site_key']), 'clear' => array('secret' => '1')));
$k = $keys();
t('"Remove the saved key": the secret is gone, status Needs keys', array($k['captcha_turnstile']['secrets'], (bool) preg_match('#data-status="keys">Needs keys<#', $screen('turnstile')['body'])), array(array(), true));
$H->ctlb('cpv', 'turnstile', json_encode(array('site_key' => $TS['site_key'])), json_encode(array('secret' => $TS['secret'])));

echo "--- not here: 404 ---\n";
foreach (array('hcaptcha' => 'switched off', 'nope' => 'unknown', 'recaptcha_v2' => 'a Google mode', 'friendly' => 'not installed') as $id => $why) {
	$r = $screen($id);
	t("{$id} ({$why}): HTTP 404, \"not installed or not active\", no form", array($r['status'], strpos($r['body'], 'This CAPTCHA add-on is not installed or not active.') !== false, strpos($r['body'], 'efb_captcha_settings') === false), array(404, true, true));
}

echo "--- the main Settings window's save ---\n";
$before = $keys();
$page = $H->ctlb('settings-redacted');
t('the row the main window gets has no add-on key', array_values(array_filter(array_keys((array) $page), function ($k) { return preg_match('/^captcha_/', $k) && !in_array($k, array('captcha_provider', 'captcha_login'), true); })), array());
$nonce_rest = $H->admin();
$main = function (array $m) use ($H, $nonce_rest) { return $H->ajax(array('action' => 'set_settings_Emsfb', 'nonce' => $nonce_rest, 'message' => json_encode($m))); };
$m = (array) $page;
$m['spam_retention_days'] = '31';
$r = $main($m);
$after = $keys();
t('a save of the window (no add-on key in it): success; captcha_turnstile and captcha_altcha as they were; its own change saved', array(!empty($r['json']['data']['success']), $after['captcha_turnstile'] == $before['captcha_turnstile'], $after['captcha_altcha'] == $before['captcha_altcha'], $H->ctl('f12-setting', '')['spam_retention_days'] ?? null), array(true, true, true, '31'));
$m['captcha_turnstile'] = '';
$m['captcha_altcha'] = array('v' => 2, 'public' => array('complexity' => 'low'), 'secret' => array());
$m['spam_retention_days'] = '30';
$main($m);
$after = $keys();
t('a save that sends something under the add-on keys: both kept as they were', array($after['captcha_turnstile'] == $before['captcha_turnstile'], $after['captcha_altcha'] == $before['captcha_altcha']), array(true, true));

echo "--- the AJAX save ---\n";
$r = $H->ajax(array('action' => 'efb_captcha_provider_save', 'nonce' => $nonce_rest, 'id' => 'altcha', 'public' => json_encode(array('complexity' => 'high')), 'secret' => json_encode(array('hmac_key' => ''))));
$after = $keys();
t('efb_captcha_provider_save: captcha_altcha changed, its signing key kept; the answer has no secret and no cipher', array(!empty($r['json']['success']), $after['captcha_altcha']['public']['complexity'], $after['captcha_altcha']['secrets'], strpos($r['body'], 'enc:v1:'), $after['captcha_turnstile'] == $before['captcha_turnstile']), array(true, 'high', array('hmac_key'), false, true));
$H->ajax(array('action' => 'efb_captcha_provider_save', 'nonce' => $nonce_rest, 'id' => 'altcha', 'public' => json_encode(array('complexity' => 'medium')), 'secret' => '{}'));

echo "--- migration, switch off, uninstall ---\n";
$H->ctl('addon-flag', 'AdnQCP', '1');
$pre = $H->ctlb('cpv-legacy', 'question', json_encode(array('mode' => 'math', 'level' => 'hard')), json_encode(array('hmac_key' => str_repeat('k', 48))));
t('an F12b site: the old option, no key in the row, not marked', array($pre['legacy_option'], $pre['exists'], $pre['moved']), array(true, false, 0));
$H->http('GET', '/wp-json/', null, array(), false); // any request: init moves it
$post_m = $H->ctlb('cpv-raw', 'question');
t('the next request moved it: captcha_question {mode math, level hard, the key encrypted}; option and page map gone; marked', array($post_m['exists'], $post_m['public'], $post_m['secret'], $post_m['legacy_option'], $post_m['autoload_public'], $post_m['moved']), array(true, array('mode' => 'math', 'level' => 'hard'), array('hmac_key' => array('set' => true, 'encrypted' => true)), false, null, 1));
$H->ctl('addon-flag', 'AdnALT', '0');
t('an add-on switched off keeps its key (captcha_altcha)', $keys()['captcha_altcha']['secrets'], array('hmac_key'));
$H->ctl('addon-flag', 'AdnALT', '1');
$before = $keys();
$H->ctlb('cpv-uninstall', 'question');
$after = $keys();
t('uninstall (Admin::remove): captcha_question is null, every other key as it was', array(array_key_exists('captcha_question', $after) ? $after['captcha_question'] : 'missing', array_diff_key($after, array('captcha_question' => 1)) == array_diff_key($before, array('captcha_question' => 1))), array(null, true));
$H->ctl('addon-flag', 'AdnQCP', '0');
$H->ctl('addon-flag', 'AdnALT', '0');
$H->ctl('addon-flag', 'AdnCTS', '0');
efb_done();
