<?php
/**
 * F12b D2 (§D2) and §5 on the real WordPress: notices of the anti-spam layer on the plugin's screens.
 *
 *  - a site updated from 4.3.1 (no spam keys in its settings row): an administrator sees "Turn on recommended
 *    protection" on EFB screens, not on Posts; a user without manage_options does not; "Turn on" (AJAX
 *    efb_spam_notice, nonce wp_rest) writes the fresh-install defaults (hidden field, 3 s, save as spam, 30 days),
 *    after which a bot's answer is spam and the notice is gone; a bad nonce → 403; "dismiss" is per user (a second
 *    administrator still sees it); a fresh install never shows it;
 *  - §5: a form whose CAPTCHA add-on is not there → "1 forms use ALTCHA, which is not available now. They are
 *    protected by Google reCAPTCHA v2." (values in <bdi>), with the Add-ons link; dismissed until the list changes.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-spam-notice.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctlb('mu');
$H->ctlb('notice-reset');
$H->ctl('f12-setting', 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&captcha_provider=recaptcha_v2');
$H->ctl('f12-setting', 'spam_honeypot=del&spam_min_time=del&spam_require_js=del&spam_action=del&spam_akismet=del&spam_disallowed_keys=del&spam_retention_days=del'); // a site updated from 4.3.1
$has = function ($body) { return strpos($body, 'id="efb-spam-notice"') !== false; };

$nonce = $H->admin();
$efb = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false);
t('updated site, administrator, EFB screen: the notice with its button, link and a live region', array($has($efb['body']), strpos($efb['body'], 'data-efb-spam-do="enable"') !== false, strpos($efb['body'], 'tab=captchas') !== false, strpos($efb['body'], 'class="efb-spam-notice-status" role="status" aria-live="polite"') !== false), array(true, true, true, true));
t('… not on Posts', $has($H->http('GET', '/wp-admin/edit.php', null, array(), false)['body']), false);
$H->ctl('user', 'efb_f12b_admin2', 'administrator');
$H->ctl('user', 'efb_f12b_editor', 'editor');
$r = $H->ajax(array('action' => 'efb_spam_notice', 'do' => 'enable', 'nonce' => 'bad0nonce0'));
t('a bad nonce: 403, nothing written', array($r['status'], $H->ctl('setting', 'spam_honeypot')), array(403, null));
$r = $H->ajax(array('action' => 'efb_spam_notice', 'do' => 'dismiss', 'nonce' => $nonce));
t('dismiss: this administrator does not see it again', array($r['status'], $has($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body'])), array(200, false));
$H->fresh_jar();
$H->login('efb_f12b_admin2', 'efb_f12b_admin2-pass-123');
t('… a second administrator still sees it', $has($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body']), true);
$n2 = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
$H->fresh_jar();
$H->login('efb_f12b_editor', 'efb_f12b_editor-pass-123');
$en = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
t('an editor (no manage_options) does not see it and cannot turn it on', array($has($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body']), $H->ajax(array('action' => 'efb_spam_notice', 'do' => 'enable', 'nonce' => $en))['status']), array(false, 403));
$H->fresh_jar();
$H->login('efb_f12b_admin2', 'efb_f12b_admin2-pass-123');
$n2 = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']); // a new session: a new nonce
$r = $H->ajax(array('action' => 'efb_spam_notice', 'do' => 'enable', 'nonce' => $n2));
$j = json_decode($r['body'], true);
t('"Turn on recommended protection": the four keys are written', array(!empty($j['success']), $H->ctl('setting', 'spam_honeypot'), $H->ctl('setting', 'spam_min_time'), $H->ctl('setting', 'spam_action'), $H->ctl('setting', 'spam_retention_days')), array(true, '1', '3', 'spam', '30'));
t('… and the notice is gone (the keys are there now)', $has($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body']), false);
$F = $H->ctl('f03-form', 'F12b D2 bot', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))));
$H->fresh_jar();
$a = EfbF11Http::answer($H->submit((int) $F['form_id'], array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'bot')), array('efb_hp' => 'http://spam.example')));
$e = $H->ctl('f14-entries', (string) $F['form_id']);
t('… a bot\'s answer is spam now (the usual success, entry_status 1)', array(!empty($a['success']), (int) ($e[0]['entry_status'] ?? -1)), array(true, 1));
$H->ctl('f12-delete-form', (string) $F['form_id']);

echo "--- §5: forms whose CAPTCHA add-on is gone ---\n";
$A = $H->ctl('f03-form', 'F12b altcha form', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1&captcha_provider=altcha');
$H->fresh_jar();
$nonce = $H->admin();
$b = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body'];
t('the notice names the service and what protects the forms, values in <bdi>, a link to the Add-ons page', array(strpos($b, 'id="efb-cp-notice"') !== false, (bool) preg_match('/<bdi>\d+<\/bdi> forms use <bdi>ALTCHA<\/bdi>, which is not available now\. They are protected by <bdi>Google reCAPTCHA v2<\/bdi>/', $b), strpos($b, 'page=Emsfb_addon') !== false), array(true, true, true));
preg_match('/id="efb-cp-notice" data-efb-notice="cp" data-sig="([a-f0-9]+)"/', $b, $m);
$H->ajax(array('action' => 'efb_spam_notice', 'do' => 'dismiss_cp', 'nonce' => $nonce, 'sig' => $m[1] ?? ''));
t('dismissed: gone until the list changes', strpos($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body'], 'id="efb-cp-notice"') !== false, false);
$B = $H->ctl('f03-form', 'F12b question form', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'captcha=1&captcha_provider=question');
t('… another form joins the list: shown again', strpos($H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false)['body'], 'id="efb-cp-notice"') !== false, true);
$H->ctl('f12-delete-form', (string) $A['form_id']);
$H->ctl('f12-delete-form', (string) $B['form_id']);

$H->ctlb('user-del', 'efb_f12b_admin2', 'efb_f12b_editor');
$H->ctlb('notice-reset');
$H->ctl('f12-setting', 'siteKey=del&secretKey=del&captcha_provider=del&spam_min_time=0');
efb_done();
