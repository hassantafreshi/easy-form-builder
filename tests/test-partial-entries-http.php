<?php
/**
 * F11-F partial (abandoned) answers on a real WordPress (the e2e site; tests/f11-http-lib.php):
 *
 *  - nothing is stored without consent (checkbox mode): 403 efb_consent_required;
 *  - with partial_need_contact (default) nothing is stored before an e-mail or mobile is filled in;
 *  - with consent and a contact: one row per tab (upsert), progress, last step, a masked contact,
 *    no raw IP (ip_hash only, '' with the form's IP mode "none"), no password;
 *  - the owner's list (AJAX get_partial_entries_Emsfb) shows it; a subscriber is refused; delete works;
 *  - a complete submit from the same tab completes it (gone from the list, content emptied);
 *  - the notice mode needs no checkbox; Free Plus (below Pro) refuses;
 *  - retention and WordPress' exporter / eraser include partial answers.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-partial-entries-http.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$H->ctl('f11-mu');
$H->ctl('plan', '3');
$H->ctl('set-option', 'efb_e2e_f11_plan', '1'); // Pro part opened through the plugin's filter (see the mu-plugin)
$RUN_IP = '198.18.' . mt_rand(1, 250) . '.' . mt_rand(1, 250);
$F = $H->ctl('f11-form', 'F11 partial', 'partial_on=1&partial_days=30', '2');
$fid = $F['form_id'];
$rows = function ($withEmail) use ($F) {
	$r = array(
		array('id_' => $F['name'], 'id_ob' => $F['name'] . '_', 'name' => 'Name', 'type' => 'text', 'value' => 'Half Way'),
		array('id_' => $F['password'], 'id_ob' => $F['password'] . '_', 'name' => 'Secret', 'type' => 'password', 'value' => 'pw-secret-9'),
	);
	if ($withEmail) $r[] = array('id_' => $F['email'], 'id_ob' => $F['email'] . '_', 'name' => 'Email', 'type' => 'email', 'value' => 'half.way@example.test');
	return $r;
};
$H->fresh_jar();
$prep = $H->prepare($fid);
$p = $prep['json'];
$save = function ($body, $form = null) use ($H, $fid, $p, $RUN_IP) {
	$body = array_merge(array('form_id' => $form ?: $fid, 'runtime_token' => $p['runtime_token']), $body);
	return $H->http('POST', '/wp-json/Emsfb/v1/forms/partial/save', $body, array('X-WP-Nonce' => $p['nonce'], 'sid' => $p['sid'], 'X-Forwarded-For' => $RUN_IP));
};
$r = $save(array('rows' => $rows(true), 'step' => 1));
t('no consent (checkbox mode): 403 efb_consent_required, nothing stored', array($r['status'], $r['json']['code'] ?? '', count((array) $H->ctl('f11-drafts', (string) $fid))), array(403, 'efb_consent_required', 0));
$r = $save(array('rows' => $rows(false), 'step' => 1, 'consent' => 1));
t('consent but no e-mail / mobile yet: not stored (efb_partial_no_contact)', array($r['status'], $r['json']['code'] ?? '', count((array) $H->ctl('f11-drafts', (string) $fid))), array(200, 'efb_partial_no_contact', 0));
$r = $save(array('rows' => $rows(true), 'step' => 1, 'consent' => 1));
$r2 = $save(array('rows' => $rows(true), 'step' => 2, 'consent' => 1));
$d = $H->ctl('f11-drafts', (string) $fid);
$dump = json_encode($d);
t('consent and an e-mail: stored once per tab (second save updates it)', array(!empty($r['json']['saved']), !empty($r2['json']['saved']), count($d), $d[0]['kind'] ?? '', (int) ($d[0]['step'] ?? 0)), array(true, true, 1, 'partial', 2));
t('masked contact, progress, no password, no raw IP', array($d[0]['contact_mask'] ?? '', (int) ($d[0]['progress'] ?? 0) > 0, strpos($dump, 'pw-secret-9'), strpos($dump, $RUN_IP), (bool) preg_match('/^[a-f0-9]{64}$/', $d[0]['ip_hash'] ?? '')), array('h***@e***.test', true, false, false, true));

// The owner's list.
$H->fresh_jar();
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$an = $H->prepare(0)['json']['nonce'] ?? '';
$ajax = function ($action, $args) use ($H, &$an) { return $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array_merge(array('action' => $action, 'nonce' => $an), $args)), array('Content-Type' => 'application/x-www-form-urlencoded'), false); };
$list = $ajax('get_partial_entries_Emsfb', array('form_id' => $fid));
$lr = $list['json']['data']['rows'] ?? array();
t('owner list: one row with step, progress, contact and the answers', array(count($lr), (int) ($lr[0]['step'] ?? 0), $lr[0]['contact_mask'] ?? '', in_array('Half Way', array_map(function ($x) { return $x['value'] ?? ''; }, (array) ($lr[0]['rows'] ?? array())), true)), array(1, 2, 'h***@e***.test', true));
$H->fresh_jar();
$H->ctl('user', 'efb_f11_sub2', 'subscriber');
$H->login('efb_f11_sub2', 'efb_f11_sub2-pass-123');
$sn = $H->prepare(0)['json']['nonce'] ?? '';
$deny = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'get_partial_entries_Emsfb', 'nonce' => $sn, 'form_id' => $fid)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
t('a subscriber cannot read the list', array(empty($deny['json']['data']['success']), isset($deny['json']['data']['rows'])), array(true, false));

// A complete submit from the same tab completes the partial entry.
$H->fresh_jar();
$H->http('GET', '/', null, array(), false);
// Reuse the first tab's session (cookie jar does not matter for a guest; the runtime token carries the owner).
$s = $H->submit($fid, array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'half.way@example.test'), array('id_' => $F['name'], 'name' => 'Name', 'type' => 'text', 'value' => 'Half Way')), array(), $prep);
$d2 = $H->ctl('f11-drafts', (string) $fid);
t('submit from the same tab: completed, emptied, linked to the entry', array(!empty(EfbF11Http::answer($s)['success']), $d2[0]['status'] ?? '', $d2[0]['content'] ?? 'x', (int) ($d2[0]['completed_msg_id'] ?? 0) > 0), array(true, 'completed', '', true));
$H->fresh_jar();
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$an = $H->prepare(0)['json']['nonce'] ?? '';
t('…and no longer listed', count($ajax('get_partial_entries_Emsfb', array('form_id' => $fid))['json']['data']['rows'] ?? array(1)), 0);

// Notice mode, delete, plan.
$G = $H->ctl('f11-form', 'F11 partial notice', 'partial_on=1&partial_consent=notice&partial_need_contact=0&prv_ip=none');
$H->fresh_jar();
$pg = $H->prepare($G['form_id'])['json'];
$rn = $H->http('POST', '/wp-json/Emsfb/v1/forms/partial/save', array('form_id' => $G['form_id'], 'runtime_token' => $pg['runtime_token'], 'rows' => array(array('id_' => $G['name'], 'type' => 'text', 'value' => 'No contact')), 'step' => 1), array('X-WP-Nonce' => $pg['nonce'], 'sid' => $pg['sid'], 'X-Forwarded-For' => $RUN_IP));
$dg = $H->ctl('f11-drafts', (string) $G['form_id']);
t('notice mode, no contact needed: stored without a checkbox; IP mode "none" keeps no IP hash', array(!empty($rn['json']['saved']), count($dg), $dg[0]['ip_hash'] ?? 'x'), array(true, 1, ''));
$H->fresh_jar();
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$an = $H->prepare(0)['json']['nonce'] ?? '';
$del = $ajax('delete_partial_entries_Emsfb', array('form_id' => $G['form_id'], 'ids' => (string) $dg[0]['id']));
t('owner deletes it', array((int) ($del['json']['data']['deleted'] ?? 0), count((array) $H->ctl('f11-drafts', (string) $G['form_id']))), array(1, 0));
$H->ctl('set-option', 'efb_e2e_f11_plan', 'del');
$H->fresh_jar();
$pg = $H->prepare($G['form_id'])['json'];
t('Free Plus (below Pro): refused', $H->http('POST', '/wp-json/Emsfb/v1/forms/partial/save', array('form_id' => $G['form_id'], 'runtime_token' => $pg['runtime_token'], 'rows' => array(), 'step' => 1), array('X-WP-Nonce' => $pg['nonce'], 'sid' => $pg['sid'], 'X-Forwarded-For' => $RUN_IP))['json']['code'] ?? '', 'efb_feature_unavailable');
$H->ctl('set-option', 'efb_e2e_f11_plan', '1');

// Privacy.
$H->fresh_jar();
$p3 = $H->prepare($fid)['json'];
$H->http('POST', '/wp-json/Emsfb/v1/forms/partial/save', array('form_id' => $fid, 'runtime_token' => $p3['runtime_token'], 'rows' => $rows(true), 'step' => 1, 'consent' => 1), array('X-WP-Nonce' => $p3['nonce'], 'sid' => $p3['sid'], 'X-Forwarded-For' => $RUN_IP));
$ex = $H->ctl('f11-drafts-op', 'export', 'half.way@example.test');
t('exporter: the partial answers of the address', count($ex['data'] ?? array()) >= 1, true);
$H->ctl('f11-drafts-op', 'erase', 'half.way@example.test');
t('eraser: removed', count($H->ctl('f11-drafts-op', 'export', 'half.way@example.test')['data'] ?? array(1)), 0);
$H->ctl('set-option', 'efb_e2e_f11_plan', 'del');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
