<?php
/**
 * Add-on install plan-gate regression tests.
 *
 * Reproduces the report from a licensed fa_IR Pro site (efb_var.pro = "1"):
 * clicking an add-on's install button answered with the "update the plugin"
 * notice or the Free Plus / Pro upsell modal.
 *
 * Both messages came from add_addons_Emsfb() trusting the remote add-on
 * endpoint as the sole authority on plan access, and returning from those two
 * branches before the fallback endpoint was ever tried. fa_IR licences are
 * validated offline on purpose (make_post_request_efb()), so the Iranian
 * mirror has no licence record to confirm and answers plan_required for
 * paying customers.
 *
 * Run: php tests/test-addon-install-plan-gate.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.1.3' );

$test_options = array();
$test_is_pro  = false;

function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}

class Efb_Plan_Gate_Function_Stub {
	public function is_efb_pro( $s = 1 ) {
		global $test_is_pro;
		return $test_is_pro;
	}
}

function get_efbFunction() {
	return new Efb_Plan_Gate_Function_Stub();
}

$passed = 0;
$failed = 0;
function check( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS  {$label}\n";
	} else {
		$failed++;
		echo "  FAIL  {$label}\n";
	}
}

/* ------------------------------------------------------------------ *
 * 1. The entitlement decision itself.
 * ------------------------------------------------------------------ */

$admin_src = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-Emsfb-admin.php' );

/*
 * The class file instantiates Emsfb\Admin at the bottom and its constructor
 * hooks into WordPress, so it cannot be required here. The decision method is
 * lifted verbatim out of the shipped source instead, which keeps the assertions
 * below pointed at the real code rather than at a copy that can drift.
 */
if ( ! preg_match( '/private function addon_local_entitlement_efb.*?\n    \}/s', $admin_src, $m ) ) {
	echo "  FAIL  addon_local_entitlement_efb() not found in the admin class\n";
	echo "RESULTS: 0 passed, 1 failed\n";
	exit( 1 );
}
eval( 'class Efb_Plan_Gate_Subject { public ' . substr( $m[0], strlen( 'private ' ) ) . ' }' );

$subject = new Efb_Plan_Gate_Subject();

$entitlement = function ( $emsfb_pro, $licence_ok, $required_package ) use ( $subject ) {
	global $test_options, $test_is_pro;
	$test_options['emsfb_pro'] = $emsfb_pro;
	$test_is_pro               = $licence_ok;
	return $subject->addon_local_entitlement_efb( $required_package );
};

echo "\nEntitlement matrix\n";

// The reported site: emsfb_pro = 1, activation code valid for the domain.
$r = $entitlement( 1, true, 1 );
check( 'Pro site is entitled to a Pro add-on', true === $r['entitled'] );
check( 'Pro site reports its local package', 1 === $r['local_package'] );

$r = $entitlement( 1, true, 3 );
check( 'Pro site is entitled to a Free Plus add-on', true === $r['entitled'] );

$r = $entitlement( 3, true, 3 );
check( 'Free Plus site is entitled to a Free Plus add-on', true === $r['entitled'] );

$r = $entitlement( 3, true, 1 );
check( 'Free Plus site is NOT entitled to a Pro add-on', false === $r['entitled'] );

$r = $entitlement( 2, false, 1 );
check( 'Free site is NOT entitled to a Pro add-on', false === $r['entitled'] );

$r = $entitlement( 2, false, 3 );
check( 'Free site is NOT entitled to a Free Plus add-on', false === $r['entitled'] );

// A stored package of 1 whose activation code no longer matches this domain
// must not grant access: is_efb_pro() is the authority, not the raw option.
$r = $entitlement( 1, false, 1 );
check( 'Pro option without a valid licence is NOT entitled', false === $r['entitled'] );

// Garbage in the option falls back to Free rather than to an unknown plan.
$r = $entitlement( 99, false, 1 );
check( 'Unknown package value degrades to Free', 2 === $r['local_package'] && false === $r['entitled'] );

/* ------------------------------------------------------------------ *
 * 2. The two branches that produced the reported messages.
 * ------------------------------------------------------------------ */

echo "\nBranch wiring in add_addons_Emsfb()\n";

$plan_branch = '';
if ( preg_match( "/plan_required', 'package_required'.*?\n            \}/s", $admin_src, $m ) ) {
	$plan_branch = $m[0];
}
check( 'plan_required branch was located', '' !== $plan_branch );
check(
	'plan_required consults local entitlement before answering',
	strpos( $plan_branch, 'addon_local_entitlement_efb' ) !== false
);
check(
	'plan_required shows the upsell only when NOT entitled',
	strpos( $plan_branch, "if (!\$entitlement['entitled'])" ) !== false
);
check(
	'plan_required can fall back to the other endpoint',
	strpos( $plan_branch, '$switch_to_fallback(' ) !== false
);

$version_branch = '';
if ( preg_match( "/\/\/ Remote metadata includes the minimum compatible.*?\n            \}/s", $admin_src, $m ) ) {
	$version_branch = $m[0];
}
check( 'version-mismatch branch was located', '' !== $version_branch );
check(
	'version mismatch can fall back to the other endpoint',
	strpos( $version_branch, '$switch_to_fallback(' ) !== false
);
check(
	'version mismatch guards a payload with no "v"',
	strpos( $version_branch, "isset(\$data->v)" ) !== false
		&& strpos( $version_branch, "'' !== \$remote_required_version" ) !== false
);

/* ------------------------------------------------------------------ *
 * 3. Diagnosability: the instrumentation must actually write.
 * ------------------------------------------------------------------ */

echo "\nInstall logging\n";

$logger = '';
if ( preg_match( '/private function addon_install_log_efb.*?\n    \}/s', $admin_src, $m ) ) {
	$logger = $m[0];
}
check( 'logger was located', '' !== $logger );
check( 'logger writes somewhere', strpos( $logger, 'error_log(' ) !== false );
check( 'logger is off unless debugging is enabled', strpos( $logger, 'EMSFB_ADDON_DEBUG' ) !== false );
check(
	'logger respects disable_functions',
	strpos( $logger, 'emsfb_is_php_function_available_efb' ) !== false
);
check(
	'logger still sanitises its context',
	strpos( $logger, 'addon_install_sanitize_log_context_efb' ) !== false
);

/* ------------------------------------------------------------------ *
 * 4. The domain handed to the licensing server.
 * ------------------------------------------------------------------ */

echo "\nDomain sent to the licensing server\n";

check(
	'host is lowercased before it is sent',
	strpos( $admin_src, "strtolower(\$_server_name)" ) !== false
);
check(
	'only the leading www. label is stripped',
	strpos( $admin_src, "preg_replace('/^www\\./', ''" ) !== false
);
check(
	'licence host candidates are recorded for comparison',
	strpos( $admin_src, 'license_domain_candidates_efb()' ) !== false
		&& strpos( $admin_src, "'host_matches_license'" ) !== false
);

// The old spelling stripped "www." anywhere in the host.
check(
	'the unanchored www. strip is gone',
	strpos( $admin_src, 'str_replace("www.", "", $_server_name)' ) === false
);

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit( $failed > 0 ? 1 : 0 );
