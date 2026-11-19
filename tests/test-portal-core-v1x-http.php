<?php
/**
 * The customer portal contract's minor versions (1.1 …; docs/portal/portal-core-contract.en.md "Versions") end to end on
 * the real WordPress (tests/e2e-wp, EFB_E2E_SITE_DIR), through the mock portal (mu-plugin efb-e2e-f20.php, never
 * shipped), what the free plugin EFB Customer Portal calls:
 *
 *  - 1.1 every plan: on Free, Free Plus and Pro the contract answers (active, sign-in by e-mail code on Free, the list);
 *    capabilities.portal_core names the plugin (efb-customer-portal), never an add-on or a package; no AdnPRT in the
 *    add-on registry; without the plugin: reason "plugin", every function efb_portal_unavailable; v1 callers unchanged.
 *
 *  - 1.2 channels: efb_portal_core_api()['channels'] says whether an e-mail and an SMS code can be sent now: SMS only
 *    with the SMS add-on (no_provider) and the WP SMS plugin (sender_not_ready) and allowed countries; e-mail off
 *    (email_off); today's budget used up (budget).
 *
 *  - 1.3 forms: efb_portal_forms() for an administrator: every form with its name, type, booking (advanced date),
 *    payment, portal flag and the published pages that show it (shortcode, block, WPBakery, Elementor, a blog post; not a
 *    draft); a page deleted leaves the list; a subscriber or a visitor: efb_portal_forbidden; the plugin off: unavailable.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-portal-core-v1x-http.php
 */
require __DIR__ . '/portal-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$X = new EfbPortalHttp($H);
$run = substr(md5((string) microtime(true)), 0, 6);
$mail_a = "v1x.{$run}@example.test";
$plan0 = $H->ctl('option', 'emsfb_pro');

try {
	echo "--- setup ---\n";
	$smtp0 = $H->ctl('setting', 'smtp');
	$H->ctl('f11-mu'); $H->ctl('f02-mu'); $H->ctl('f06-mu');
	$X->c20('mu');
	$H->ctl('set-option', 'efb_e2e_capture_mail', '1');
	$H->ctl('f12-setting', 'smtp=1&secretKey=del&siteKey=del');
	$X->c20('on', '0');
	$X->c20('reset');
	$F = $H->ctl('f03-form', 'Portal v1x ' . $run, json_encode(array(
		array('key' => 'nm', 'type' => 'text', 'name' => 'Name'),
		array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
	)), 'prv_id_field=@@em@@', '1');
	$fid = (int) $F['form_id']; $i = $F['ids'];
	$sub = function ($name, $email) use ($H, $fid, $i) {
		return EfbF11Http::answer($H->submit($fid, array(array('id_' => $i['nm'], 'name' => 'Name', 'type' => 'text', 'value' => $name), array('id_' => $i['em'], 'name' => 'Email', 'type' => 'email', 'value' => $email))));
	};

	echo "--- 1.1: every plan ---\n";
	$H->ctl('plan', '2');
	$caps = $X->c20('caps');
	t('Free, without the plugin: capabilities.portal_core not ready, reason "plugin"; no addon / package keys; the plugin efb-customer-portal named', array($caps['plan'] ?? '', $caps['portal_core']['ready'] ?? null, $caps['portal_core']['reason'] ?? '', isset($caps['portal_core']['addon']), isset($caps['portal_core']['package']), $caps['portal_core']['plugin']['slug'] ?? '', $caps['portal_core']['plugin']['file'] ?? ''), array('free', false, 'plugin', false, false, 'efb-customer-portal', 'efb-customer-portal/efb-customer-portal.php'));
	t('… no AdnPRT anywhere in the add-on registry (catalogue, features, split.addons): no "buy Pro" gate for the portal', array($caps['catalog'] ?? 1, $caps['addon_feature'] ?? 1, $caps['split_addon'] ?? 1), array(false, false, false));
	$off = $X->c20('call', 'efb_portal_entries', json_encode(array(array(array('k' => 'e', 'h' => str_repeat('a', 64))))));
	t('… every function answers efb_portal_unavailable', $off['error'] ?? '', 'efb_portal_unavailable');
	$X->c20('on', '1');
	$X->c20('index', 'restart');
	$X->c20('policy', $fid, json_encode(array('on' => true, 'reply' => true)));
	$a1 = $sub('Ana', $mail_a);
	$api = $X->c20('call', 'efb_portal_core_api', '[]');
	$caps = $X->c20('caps');
	t('Free with the plugin on: active, version 1 (v1 callers unchanged), contract 1.x, ready, reason null', array($api['active'] ?? null, $api['version'] ?? null, $api['contract'] ?? '', $caps['portal_core']['ready'] ?? null, array_key_exists('reason', (array) ($caps['portal_core'] ?? array())) ? $caps['portal_core']['reason'] : 'x', $caps['portal_core']['plans'] ?? array()), array(true, 1, '1.' . ($api['minor'] ?? 0), true, null, array('free', 'free_plus', 'pro')));
	$P = new EfbPortalHttpClient($H);
	$si = $X->signin($P, $mail_a);
	$list = $P->op('entries');
	t('Free: sign-in by e-mail code works (no Free Plus gate of a form\'s code) and the list shows the entry', array(!empty($a1['success']), !empty($si['start']['success']), $si['code'] !== '', !empty($si['verify']['success']), array_column($list['data']['rows'] ?? array(), 'track')), array(true, true, true, true, array($a1['track'] ?? '')));
	foreach (array('3' => 'free_plus', '1' => null) as $pkg => $want) {
		$H->ctl('plan', $pkg);
		$c = $X->c20('caps');
		$l = $P->op('entries');
		t("plan {$pkg} (" . ($c['plan'] ?? '') . '): active, the same list', array($c['portal_core']['ready'] ?? null, array_column($l['data']['rows'] ?? array(), 'track')), array(true, array($a1['track'] ?? '')));
	}

	echo "--- 1.2: channels ---\n";
	$H->ctl('plan', '3');
	$f02 = $H->ctl('f02-settings', '');
	$H->ctl('addon-flag', 'AdnSS', '0'); $H->ctl('f02-wpsms', '0');
	$ch = $X->c20('call', 'efb_portal_core_api', '[]')['channels'] ?? array();
	t('no SMS add-on: e-mail ready, SMS not (no_provider)', array($ch['email'] ?? null, $ch['sms'] ?? null), array(array('ready' => true, 'reason' => ''), array('ready' => false, 'reason' => 'no_provider')));
	$H->ctl('addon-flag', 'AdnSS', '1');
	$ch = $X->c20('call', 'efb_portal_core_api', '[]')['channels'] ?? array();
	t('the SMS add-on without the WP SMS plugin: SMS not ready (sender_not_ready)', $ch['sms'] ?? null, array('ready' => false, 'reason' => 'sender_not_ready'));
	$H->ctl('f02-wpsms', '1', 'ok');
	$H->ctl('f02-settings', json_encode(array('sms' => array('allowed_countries' => array('DE')))));
	$ch = $X->c20('call', 'efb_portal_core_api', '[]')['channels'] ?? array();
	t('… with the WP SMS plugin (the bridge) and an allowed country: SMS ready', $ch['sms'] ?? null, array('ready' => true, 'reason' => ''));
	$api = $P->op('api');
	t('… the same answer through the portal\'s own route (what the plugin shows: the SMS tab)', $api['data']['channels']['sms']['ready'] ?? null, true);
	$H->ctl('f12-setting', 'smtp=0');
	$ch = $X->c20('call', 'efb_portal_core_api', '[]')['channels'] ?? array();
	t('the site sends no e-mail: e-mail not ready (email_off)', $ch['email'] ?? null, array('ready' => false, 'reason' => 'email_off'));
	$H->ctl('f12-setting', 'smtp=1');
	$X->c20('reset');
	$H->ctl('f02-settings', json_encode(array('email' => array('daily_budget' => 1))));
	$X->signin(new EfbPortalHttpClient($H), $mail_a);
	$ch = $X->c20('call', 'efb_portal_core_api', '[]')['channels'] ?? array();
	t('today\'s budget of e-mail codes used up: e-mail not ready (budget)', $ch['email'] ?? null, array('ready' => false, 'reason' => 'budget'));
	$H->ctl('f02-settings', json_encode($f02['settings']));
	$X->c20('reset');
	$H->ctl('addon-flag', 'AdnSS', '0'); $H->ctl('f02-wpsms', '0');

	echo "--- 1.3: forms ---\n";
	$B = $H->ctl('f03-form', 'Portal v1x booking ' . $run, json_encode(array(
		array('key' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('key' => 'when', 'type' => 'advdate', 'name' => 'When'),
		array('key' => 'pay', 'type' => 'stripe', 'name' => 'Pay'),
	)), '', '1');
	$bid = (int) $B['form_id'];
	$placed = array();
	foreach (array('shortcode', 'block', 'wpbakery', 'elementor', 'post', 'draft') as $k) $placed[$k] = $X->c20('place', $fid, $k);
	$admin = new EfbPortalHttpClient($H);
	$keep = $H->jar; $H->jar = $admin->jar;
	$nonce = $H->admin();
	$H->jar = $keep;
	$fl = $admin->op('forms', array(), false, array('X-WP-Nonce' => $nonce));
	$by = array(); foreach ((array) ($fl['data'] ?? array()) as $f) $by[$f['id']] = $f;
	$A = $by[$fid] ?? array(); $Bk = $by[$bid] ?? array();
	$urls = array_column($A['pages'] ?? array(), 'url');
	$want = array(); foreach (array('shortcode', 'block', 'wpbakery', 'elementor', 'post') as $k) $want[] = $placed[$k]['url'];
	sort($urls); sort($want);
	t('an administrator: every form, with its name and type', array(!empty($fl['success']), $A['name'] ?? '', $A['type'] ?? '', $Bk['name'] ?? ''), array(true, 'Portal v1x ' . $run, 'form', 'Portal v1x booking ' . $run));
	t('… the pages that show it: shortcode, block, WPBakery, Elementor, a blog post — not a draft page', array($urls === $want, in_array($placed['draft']['url'], $urls, true), ($A['pages'][0]['title'] ?? '') !== ''), array(true, false, true));
	t('… booking (an advanced date) and payment (a payment field); the portal flag from the plugin\'s policy', array($A['has_booking'] ?? null, $A['has_payment'] ?? null, $Bk['has_booking'] ?? null, $Bk['has_payment'] ?? null, $A['portal'] ?? null, $Bk['portal'] ?? null), array(false, false, true, true, true, false));
	$X->c20('unplace', $placed['block']['id']);
	$fl2 = $admin->op('forms', array(), false, array('X-WP-Nonce' => $nonce));
	$A2 = array(); foreach ((array) ($fl2['data'] ?? array()) as $f) if ($f['id'] === $fid) $A2 = $f;
	t('a page deleted: no longer listed (the cache is forgotten)', array(count($A2['pages'] ?? array()), in_array($placed['block']['url'], array_column($A2['pages'] ?? array(), 'url'), true)), array(4, false));
	$sl = 'efb_v1x_' . $run;
	$H->ctlb('user', $sl);
	$sub_c = new EfbPortalHttpClient($H);
	$keep = $H->jar; $H->jar = $sub_c->jar;
	$H->login($sl, $sl . '-pass-123');
	$sn = trim($H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
	$H->jar = $keep;
	$fs = $sub_c->op('forms', array(), false, array('X-WP-Nonce' => $sn));
	$fv = (new EfbPortalHttpClient($H))->op('forms', array(), false);
	t('a subscriber and a visitor: efb_portal_forbidden (403 inside the answer), no form named', array($fs['code'] ?? '', $fs['status'] ?? 0, $fv['code'] ?? '', strpos(json_encode($fs), 'Portal v1x')), array('efb_portal_forbidden', 403, 'efb_portal_forbidden', false));
	$H->ctlb('user-del', $sl);
	$X->c20('on', '0');
	$fo = $X->c20('call', 'efb_portal_forms', '[]');
	t('the plugin off: efb_portal_unavailable', $fo['error'] ?? '', 'efb_portal_unavailable');
	$X->c20('on', '1');
	$X->c20('policy', $fid, json_encode(array('on' => true, 'reply' => true)));
	foreach ($placed as $k => $pl) if ($k !== 'block') $X->c20('unplace', $pl['id']);
} finally {
	$X->c20('on', '0'); $X->c20('bef', '0'); $X->c20('limits', '');
	$H->ctl('addon-flag', 'AdnSS', '0'); $H->ctl('f02-wpsms', '0');
	$H->ctl('plan', (string) ($plan0 === null ? 3 : $plan0));
	$H->ctl('f12-setting', 'smtp=' . ($smtp0 === null ? 'del' : rawurlencode((string) $smtp0)));
}
efb_done();
