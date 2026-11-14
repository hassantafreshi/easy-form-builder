<?php
/**
 * F14-B on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as the admin page does it:
 *
 *  1. the DB 1.10 schema is there (columns, keys, tables);
 *  2. 20,000 entries (tests/seed-entries-bulk.php): page 50 of 20 answers in time (the server reads one page of
 *     content, never all of them), the totals of every tab, the dates of a page in order;
 *  3. a field condition over the big form stops at 5000 rows and says "more than";
 *  4. no nonce → efb_forbidden; a Subscriber with a valid nonce → efb_forbidden; a form id 0 → efb_invalid.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-entries-query-wp.php
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

$s = $H->ctl('f14-schema');
t('the site is on DB 1.10 with the F14 columns, keys and tables', $s['ready'] === true && in_array('entry_status', $s['columns'], true) && in_array('form_status_date', $s['keys'], true) && !in_array(false, $s['tables'], true), true);

$F = $H->ctl('f03-form', 'F14 bulk list', json_encode(array(array('key' => 'name', 'type' => 'text'), array('key' => 'city', 'type' => 'text'))), '', '1');
$fid = (int) $F['form_id'];
$seed = $H->ctl('f14-seed-bulk', $fid, 20000);
echo "  seeded {$seed['inserted']} entries in {$seed['seconds']} s\n";
t('20,000 entries seeded', $seed['inserted'], 20000);

$admin_nonce = function ($H) {
	$p = $H->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false);
	// efb_var.nonce / ajax_object_efm_core.nonce: the wp_rest nonce the panel posts as "nonce".
	return preg_match('/efb_var\s*=\s*\{[^;]*?"nonce":"([a-f0-9]+)"/', $p['body'], $m) ? $m[1] : '';
};
$ajax = function ($H, $data) {
	$r = $H->http('POST', '/wp-admin/admin-ajax.php', http_build_query($data), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	$j = json_decode($r['body'], true);
	return is_array($j) && isset($j['data']) ? $j['data'] : $j;
};
t('the administrator logs in', $H->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123'), true);
$nonce = $admin_nonce($H);
t('the panel gives the wp_rest nonce', strlen($nonce) > 5, true);

$t0 = microtime(true);
$r = $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $nonce, 'form_id' => $fid, 'page' => 50, 'per_page' => 20));
$ms = (int) round((microtime(true) - $t0) * 1000);
echo "  page 50 of 20,000 entries: {$ms} ms (HTTP round trip, SQLite)\n";
$active = 20000 - 400 - (int) floor(20000 / 60) + (int) floor(20000 / 300); // every 50th spam, every 60th trash unless it is also a 50th
t('page 50: 20 rows, the total of active entries', array($r['success'], count($r['rows']), $r['total']), array(true, 20, $active));
t('page 50 answers within 3 s on SQLite (one page of content read; MySQL target 300 ms with 200k)', $ms < 3000, true);
t('the tab counts', array($r['counts']['spam'], $r['counts']['trash']), array(400, (int) floor(20000 / 60) - (int) floor(20000 / 300)));
$dates = array_map(function ($x) { return $x['date_iso']; }, $r['rows']);
$sorted = $dates; rsort($sorted);
t('the page is in date order (newest first)', $dates, $sorted);
$r2 = $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $nonce, 'form_id' => $fid, 'page' => 51, 'per_page' => 20));
t('pages 50 and 51 share no entry', array_intersect(array_column($r['rows'], 'msg_id'), array_column($r2['rows'], 'msg_id')), array());

$c = $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $nonce, 'form_id' => $fid, 'conds' => json_encode(array(array('id' => 'blk_city', 'op' => 'eq', 'v' => 'Tehran')))));
t('a field condition over the big form stops at 5000 rows: "more than", 20 rows', array($c['total_is_estimate'], count($c['rows'])), array(true, 20));
$sq = $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $nonce, 'form_id' => $fid, 'q' => 'BLK1234'));
t('the exact tracking code finds one entry', array($sq['total'], $sq['rows'][0]['track'] ?? ''), array(1, 'BLK1234'));

t('no nonce → efb_forbidden', $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'form_id' => $fid))['code'] ?? '', 'efb_forbidden');
t('form id 0 → efb_invalid', $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $nonce, 'form_id' => 0))['code'] ?? '', 'efb_invalid');
$H->ctl('user', 'efb_e2e_f14sub', 'subscriber');
$H->fresh_jar();
$H->login('efb_e2e_f14sub', 'efb_e2e_f14sub-pass-123');
// WordPress' own rest-nonce action gives a logged-in user their wp_rest nonce.
$p = $H->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false);
$sub_nonce = trim($p['body']);
t('a Subscriber with a valid wp_rest nonce → efb_forbidden', $ajax($H, array('action' => 'efb_entries_query_Emsfb', 'nonce' => $sub_nonce, 'form_id' => $fid))['code'] ?? '', 'efb_forbidden');

$H->ctl('f14-clear', $fid); // the site stays light for the scenarios after this test

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
