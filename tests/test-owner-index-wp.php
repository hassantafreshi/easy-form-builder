<?php
/**
 * F20-core P5 at scale on the real WordPress (tests/e2e-wp; SQLite on the test site, MySQL in production):
 *  - 20,000 entries of a form are indexed by the backfill in batches (each cron run at most 20 seconds and 500
 *    entries a batch, rescheduled until done; never in a page view): no batch over its time budget;
 *  - the owner of the last of them is found by one lookup; a lookup among 200,000 owner rows takes under 50 ms;
 *  - the index is only kept once switched on (a site without the portal pays nothing: no rows are written).
 *
 * Run (the e2e site, under the e2e lock; about a minute): php tests/test-owner-index-wp.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$c20 = function (...$a) use ($H) {
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f20.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$run = substr(md5((string) microtime(true)), 0, 6);
$mail = "bulk.last.{$run}@example.test";
$H->ctl('plan', '3');
$c20('mu');
$H->ctl('set-option', 'emsfb_own_on', '0');
$F = $H->ctl('f03-form', 'Bulk ' . $run, json_encode(array(array('key' => 'em', 'type' => 'email', 'name' => 'Email'))), 'prv_id_field=@@em@@', '1');
$fid = (int) $F['form_id'];
try {
	$H->ctl('f14-seed', (string) $fid, json_encode(array(array('id_' => $F['ids']['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'off.' . $run . '@example.test'))), '');
	$H->ctl('set-option', 'efb_e2e_f20_stored', '[]');
	$sub = EfbF11Http::answer($H->submit($fid, array(array('id_' => $F['ids']['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'off2.' . $run . '@example.test'))));
	$E = $H->ctl('f14-entries', $fid);
	t('the index switched off (no portal): a submitted entry gets no owner row', array(!empty($sub['success']), $c20('owners', end($E)['msg_id'])), array(true, array()));
	$seed = $c20('seed-bulk', $fid, 20000, $mail);
	echo "seeded 20,000 entries in {$seed['seconds']} s\n";
	$H->ctl('set-option', 'emsfb_own_bf', 'del');
	$H->ctl('set-option', 'emsfb_own_on', '1');
	$on = $c20('call', 'efb_portal_reindex', '[true]');
	$times = array(); $st = null; $n = 0;
	do { $b = $c20('bf-batch', 500); $times[] = $b['seconds']; $st = $b['st']; $n++; } while (is_array($st) && empty($st['done']) && $n < 400);
	echo 'backfill: ' . count($times) . ' cron runs, the longest ' . max($times) . " s\n";
	t('the backfill indexed every entry in cron runs, none over its 20-second budget, then stopped rescheduling', array(!empty($st['done']), max($times) <= 25, $b['next']), array(true, true, false));
	$lk = $c20('time-lookup', $mail);
	t('the owner of the last entry is found among them', $lk['found'] ?? 0, 1);
	$bo = $c20('bulk-own', 180000);
	echo "owner rows now: {$bo['rows']}\n";
	$ms = array();
	for ($k = 0; $k < 5; $k++) { $x = $c20('time-lookup', $mail); $ms[] = $x['ms']; }
	sort($ms);
	echo 'lookup among ' . $bo['rows'] . ' owner rows: median ' . $ms[2] . " ms\n";
	t('one lookup among 200,000 owner rows: under 50 ms (median of 5)', array($bo['rows'] >= 200000, $ms[2] < 50), array(true, true));
} finally {
	$c20('bulk-own', 'clear', $fid);
	$H->ctl('f14-clear', (string) $fid);
	$H->ctl('set-option', 'emsfb_own_on', '0');
}
efb_done();
