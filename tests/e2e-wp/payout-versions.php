<?php
/**
 * Not a test. The e2e scenario "payout" (tests/e2e-wp/scenario-payout.js) puts a version of core and of the Stripe,
 * PayPal and Persia Pay add-ons in place on the e2e site through tests/payout-mixed-lib.php, from the command line:
 *
 *   php tests/e2e-wp/payout-versions.php use <new|old> [new|old]   core, then all three add-ons (default new)
 *   php tests/e2e-wp/payout-versions.php restore                  the working copies back
 *
 * Prints one JSON line. Unlike the PHP tests (which put the working copies back when they end), "use" leaves the
 * versions in place until "restore": the scenario restores them in its finally block.
 */
require_once dirname(__DIR__) . '/payout-mixed-lib.php';

$cmd = isset($argv[1]) ? $argv[1] : '';
$H = new EfbF11Http();
$M = new EfbPayoutMixed($H);
$keep = function () use ($M) {
	$p = new ReflectionProperty($M, 'restored');
	$p->setAccessible(true);
	$p->setValue($M, true); // nothing to put back when this process ends
};
try {
	if ($why = $M->unavailable()) { echo json_encode(array('ok' => false, 'error' => $why)) . "\n"; $keep(); exit(1); }
	if ($cmd === 'use') {
		$core = isset($argv[2]) && $argv[2] === 'old' ? 'old' : 'new';
		$add = isset($argv[3]) && $argv[3] === 'old' ? 'old' : 'new';
		$dir = $M->setup($core, array_fill_keys(EfbPayoutMixed::ADDONS, $add));
		$keep();
		echo json_encode(array('ok' => true, 'core' => $core, 'addons' => $add, 'dir' => $dir)) . "\n";
	} elseif ($cmd === 'restore') {
		$p = new ReflectionProperty($M, 'restored');
		$p->setAccessible(true);
		$p->setValue($M, false);
		$M->restore();
		echo json_encode(array('ok' => true)) . "\n";
	} else {
		$keep();
		echo json_encode(array('ok' => false, 'error' => 'use <new|old> [new|old] | restore')) . "\n";
		exit(1);
	}
} catch (Exception $e) {
	echo json_encode(array('ok' => false, 'error' => $e->getMessage())) . "\n";
	exit(1);
}
