<?php
/**
 * F15-C on the real WordPress of tests/e2e-wp (skips without it): the versions through the real admin AJAX save
 * (update_form_Emsfb) and the two read actions.
 *
 *  - DB 1.11: the table dropped and the upgrade run twice: created once, the second run applies nothing;
 *  - the first save of a form made before (no version) keeps the replaced state ("initial") and the save; the
 *    same form saved again keeps nothing; a save with rev_source=autopilot is marked so;
 *  - form_rev_keep = 2 → the current version and two earlier ones;
 *  - efb_get_revision gives the rows; saving them with rev_source=restore keeps a new version (the note), the
 *    form's entries are untouched;
 *  - no nonce, or a user without the plugin's capability → refused (forbidden); an unknown version → no_revision;
 *  - deleting the form (remove_id_Emsfb) deletes its versions.
 *
 * Run (under the e2e lock): EFB_E2E_SITE_DIR=… EFB_E2E_WPCLI=… php tests/test-form-revisions-http.php
 */
$passed = 0; $failed = 0;
function t($name, $ok, $detail = null) { global $passed, $failed; if ($ok) { echo "[PASS] {$name}\n"; $passed++; } else { echo "[FAIL] {$name}" . ($detail !== null ? '  ' . substr(json_encode($detail), 0, 600) : '') . "\n"; $failed++; } }
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$sc = $H->ctl('f15-schema', 'drop');
t('DB 1.11: the versions table dropped, the upgrade creates it; a second run applies nothing', is_array($sc) && in_array('emsfb_form_rev_: CREATE TABLE', $sc['first'], true) && !in_array('emsfb_form_rev_: CREATE TABLE', $sc['second'], true) && (string) $sc['const'] === '1.11' && (string) $sc['db_version'] === '1.11' && (bool) $sc['table'], $sc);

$H->ctl('plan', 3);
$H->ctl('f15-setting', 'del');
$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->prepare(0)['json']['nonce'] ?? '';
$ajax = function ($data, $n = null) use ($H, &$nonce) {
	$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array_merge(array('nonce' => $n === null ? $nonce : $n), $data)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	return is_array($r['json']) && isset($r['json']['data']) ? $r['json']['data'] : array('raw' => $r['body'], 'status' => $r['status']);
};
$F = $H->ctl('f03-form', 'F15 http versions', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'), array('key' => 'em', 'type' => 'email', 'name' => 'E-mail'))), '', '1');
$fid = (int) $F['form_id'];
$rows = $H->ctl('form', (string) $fid)['form_structer'];
$H->ctl('f14-seed', (string) $fid, json_encode(array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'Kept answer'))), '');
$entries0 = $H->ctl('entries', (string) $fid);
$save = function ($rows, $extra = array()) use ($ajax, $fid) {
	return $ajax(array_merge(array('action' => 'update_form_Emsfb', 'id' => $fid, 'name' => $rows[0]['formName'] ?? 'F15', 'value' => json_encode($rows, JSON_UNESCAPED_UNICODE)), $extra));
};
$revs = function () use ($H, $fid) { return $H->ctl('f15-revs', (string) $fid); };

t('a form made before versions existed has none (the e2e helper\'s own save kept one: cleared)', $H->ctl('f15-revs', (string) $fid, 'clear')['n'] === 0);
$rows[2]['name'] = 'Full name';
$s1 = $save($rows);
$v = $revs();
t('first save: the replaced state ("initial") and the save ("builder") are kept', ($s1['success'] ?? false) === true && $v['n'] === 2 && $v['revs'][0]['source'] === 'builder' && $v['revs'][1]['source'] === 'initial' && $v['revs'][0]['current'] === true, array($s1, $v));
$save($rows);
t('the same form saved again keeps no version', $revs()['n'] === 2);
$rows[3]['name'] = 'E-mail address';
$save($rows, array('rev_source' => 'autopilot'));
$v = $revs();
t('a save with rev_source=autopilot is kept as "autopilot" with the user', $v['n'] === 3 && $v['revs'][0]['source'] === 'autopilot' && $v['revs'][0]['user'] !== '', $v['revs'][0]);
$bad = $save(array_merge($rows, array()), array('rev_source' => 'evil<script>'));
t('an unknown rev_source is ignored (no change → nothing kept)', $revs()['n'] === 3);

$H->ctl('f15-setting', '2');
foreach (array('A', 'B') as $x) { $rows[2]['name'] = 'Name ' . $x; $save($rows); }
$v = $revs();
t('form_rev_keep = 2: the current version and two earlier ones', $v['n'] === 3 && $v['keep'] === 2, array($v['n'], $v['keep']));

$list = $ajax(array('action' => 'efb_list_revisions', 'form_id' => $fid));
t('efb_list_revisions: success, newest first, dates for the site, no form JSON in the list', ($list['success'] ?? false) === true && count($list['revisions']) === 3 && !isset($list['revisions'][0]['rows']) && !isset($list['revisions'][0]['form_structer']) && $list['revisions'][0]['date'] !== '', $list);
$oldest = end($list['revisions']);
$g = $ajax(array('action' => 'efb_get_revision', 'rev_id' => $oldest['rev_id']));
t('efb_get_revision: the rows of that version (the label it had then)', ($g['success'] ?? false) === true && (int) $g['revision']['form_id'] === $fid && $g['revision']['rows'][2]['name'] === 'Full name', $g['revision']['rows'][2] ?? $g);
$back = $g['revision']['rows'];
$s = $save($back, array('rev_source' => 'restore', 'rev_note' => 'Restored from the version of ' . $oldest['date']));
$v = $revs();
$stored = $H->ctl('form', (string) $fid)['form_structer'];
t('the restored version saved: the form is that version, a new version "restore" with the note, three kept', ($s['success'] ?? false) === true && $stored[2]['name'] === 'Full name' && $v['revs'][0]['source'] === 'restore' && strpos($v['revs'][0]['note'], 'Restored from the version of') === 0 && $v['n'] === 3, array($s, $v['revs'][0]));
t('the form\'s entries are untouched by versions and a restore', json_encode($H->ctl('entries', (string) $fid)) === json_encode($entries0));

$r = $ajax(array('action' => 'efb_list_revisions', 'form_id' => $fid), 'bad-nonce');
t('efb_list_revisions without a valid nonce: refused', ($r['success'] ?? null) === false && ($r['code'] ?? '') === 'forbidden', $r);
$r = $ajax(array('action' => 'efb_get_revision', 'rev_id' => 999999999));
t('an unknown version: no_revision', ($r['success'] ?? null) === false && ($r['code'] ?? '') === 'no_revision', $r);
$H->ctl('user', 'efb_f15_member', 'subscriber');
$H->fresh_jar();
$H->login('efb_f15_member', 'efb_f15_member-pass-123');
$n2 = $H->prepare(0)['json']['nonce'] ?? '';
$r = $ajax(array('action' => 'efb_list_revisions', 'form_id' => $fid), $n2);
t('a signed-in user without the plugin\'s capability: refused', ($r['success'] ?? null) === false && ($r['code'] ?? '') === 'forbidden', $r);
$r = $ajax(array('action' => 'efb_get_revision', 'rev_id' => $oldest['rev_id']), $n2);
t('…and cannot read a version', ($r['success'] ?? null) === false && !isset($r['revision']), $r);
$H->fresh_jar();
$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'efb_list_revisions', 'form_id' => $fid)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
t('a visitor (not signed in): no such action (no nopriv)', trim($r['body']) === '0' || $r['status'] === 400, array($r['status'], substr($r['body'], 0, 80)));

$H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
$nonce = $H->prepare(0)['json']['nonce'] ?? '';
$d = $ajax(array('action' => 'remove_id_Emsfb', 'id' => $fid));
t('deleting the form deletes its versions', $revs()['n'] === 0, $d);
$H->ctl('f15-setting', 'del');
$H->ctl('f12-delete-form', (string) $fid);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
