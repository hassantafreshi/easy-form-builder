<?php
/*
 * Regression test: Human Shield must NOT behaviourally block the file-upload
 * route (recorder / file fields), while the form submit stays fully gated.
 *
 * The /forms/file/upload request is separate from the submit and is produced by
 * clicking record/upload, not by typing across the form, so the behavioural
 * detector scores it low (no keyboard cadence) and browser-autofill can trip the
 * honeypot. Uploads are therefore gated on a valid session+form-bound token plus
 * the request-count rate limit only; the anti-spam decision lives at the submit.
 *
 * Run:  php tests/test-human-shield-upload-gate.php    (from any dir; adjust the
 *       wp-load path below if your docroot differs)
 */

define( 'WP_USE_THEMES', false );

$wp_load = getenv( 'EFB_WP_LOAD' ) ?: 'c:/xampp/htdocs/wp/wp-load.php';
require $wp_load;

global $wpdb;

$vendor = dirname( __DIR__ ) . '/vendor/human-shield/';
require_once $vendor . 'class-Emsfb-human-shield.php';
require_once $vendor . 'class-Emsfb-human-shield-rate-limiter.php';
require_once $vendor . 'class-Emsfb-human-shield-detector.php';
require_once $vendor . 'class-Emsfb-human-shield-rest.php';
require_once $vendor . 'class-Emsfb-human-shield-notification-gate.php';

$_SERVER['REMOTE_ADDR']    = '203.0.113.77';
$_SERVER['HTTP_USER_AGENT'] = 'EFB-Upload-Gate-Test/1.0';

$shield = Emsfb\Emsfb_Human_Shield::instance();

$sref     = new ReflectionObject( $shield );
$restProp = $sref->getProperty( 'rest' );
$restProp->setAccessible( true );
$rest = $restProp->getValue( $shield );
if ( ! $rest ) {
	echo "SKIP: Human Shield runtime is off (enable the add-on / AdnHSH).\n";
	exit( 0 );
}
$rref = new ReflectionObject( $rest );
$call = function ( $name, $args ) use ( $rest, $rref ) {
	$m = $rref->getMethod( $name );
	$m->setAccessible( true );
	return $m->invoke( $rest, ...$args );
};

// --- pick a real form id that exists, else fall back to a synthetic one ---
$FID = (int) $wpdb->get_var( "SELECT form_id FROM {$wpdb->prefix}emsfb_form ORDER BY form_id DESC LIMIT 1" );
if ( $FID < 1 ) { $FID = 1; }

// --- seed a live session (read_date in the future, SITE-local tz) ---
$SID  = 'gatetest' . substr( md5( uniqid( '', true ) ), 0, 12 );
$stts = $wpdb->prefix . 'emsfb_stts_';
$wpdb->insert(
	$stts,
	array(
		'sid' => $SID, 'fid' => $FID, 'type_' => 1, 'status' => 'open',
		'ip' => '203.0.113.77', 'os' => 'x', 'browser' => 'x',
		'read_date' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 3600 ),
		'uid' => 0, 'tc' => '', 'active' => 1,
	)
);

// Bad metrics: no typing, minimal pointer, honeypot filled -> low score + hard_fail.
$bad_metrics = array(
	'durationMs' => 4000, 'firstInteractionDelayMs' => 300, 'focusCount' => 1,
	'inputCount' => 0, 'keyCount' => 0, 'pointerMoveCount' => 1, 'pointerDistance' => 5,
	'touchCount' => 0, 'clickCount' => 2, 'scrollCount' => 0, 'pasteCount' => 0,
	'fieldsTouched' => 0, 'fieldCount' => 6, 'formFieldCount' => 6, 'visibilityChanges' => 0,
	'touchCapable' => false, 'webdriver' => false, 'honeypotFilled' => true,
);

$json_req = function ( $route, $body ) {
	$r = new WP_REST_Request( 'POST', $route );
	$r->set_header( 'Content-Type', 'application/json' );
	$r->set_body( wp_json_encode( $body ) ); // challenge/attest read get_json_params() only
	return $r;
};

$mint = function ( $route ) use ( $rest, $json_req, $FID, $SID, $bad_metrics ) {
	$ch = $rest->challenge( $json_req( '/EmsfbShield/v1/challenge', array( 'formId' => $FID, 'route' => $route, 'sid' => $SID ) ) )->get_data();
	if ( empty( $ch['success'] ) ) { return array( 'err' => 'challenge:' . wp_json_encode( $ch ) ); }
	$at = $rest->attest( $json_req( '/EmsfbShield/v1/attest', array( 'challengeId' => $ch['challengeId'], 'formId' => $FID, 'route' => $route, 'sid' => $SID, 'metrics' => $bad_metrics ) ) )->get_data();
	if ( empty( $at['success'] ) ) { return array( 'err' => 'attest:' . wp_json_encode( $at ) ); }
	return array( 'token' => $at['token'], 'score' => (int) $at['score'] );
};

$server  = rest_get_server();
$results = array();

foreach ( array( 'soft_block', 'strict' ) as $mode ) {
	$s         = get_option( 'emsfb_human_shield_settings' );
	$s['mode'] = $mode;
	update_option( 'emsfb_human_shield_settings', $s );

	// file_upload must be ALLOWED (guard returns null) despite score+honeypot.
	$up = $mint( '/Emsfb/v1/forms/file/upload' );
	if ( isset( $up['err'] ) ) {
		$results[] = array( "[$mode] upload token mint", false, $up['err'] );
	} else {
		$req = new WP_REST_Request( 'POST', '/Emsfb/v1/forms/file/upload' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( array( 'id' => '37ab3xk9q', 'fid' => $FID, 'sid' => $SID ) ) );
		$req->set_header( 'x_efb_human_token', $up['token'] );
		$req->set_header( 'x_efb_form_id', (string) $FID );
		$guard   = $call( 'guard_efb_rest', array( null, $server, $req ) );
		$allowed = ( null === $guard );
		$results[] = array( "[$mode] file_upload ALLOWED (score={$up['score']}, honeypot)", $allowed, $allowed ? 'allowed' : 'blocked:' . wp_json_encode( $guard->get_data() ) );
	}

	// submit must STILL be blocked with the same bad metrics.
	$sub = $mint( '/Emsfb/v1/forms/message/add' );
	if ( isset( $sub['err'] ) ) {
		$results[] = array( "[$mode] submit token mint", false, $sub['err'] );
	} else {
		$req = new WP_REST_Request( 'POST', '/Emsfb/v1/forms/message/add' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( array( 'id' => $FID, 'sid' => $SID ) ) );
		$req->set_header( 'x_efb_human_token', $sub['token'] );
		$guard   = $call( 'guard_efb_rest', array( null, $server, $req ) );
		$blocked = ( null !== $guard && is_object( $guard ) );
		$results[] = array( "[$mode] submit STILL blocked", $blocked, $blocked ? 'blocked' : 'WRONGLY ALLOWED' );
	}
}

// cleanup + restore
$wpdb->delete( $stts, array( 'sid' => $SID ) );
$s         = get_option( 'emsfb_human_shield_settings' );
$s['mode'] = 'soft_block';
update_option( 'emsfb_human_shield_settings', $s );

$fail = 0;
foreach ( $results as $r ) {
	list( $label, $ok, $detail ) = $r;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $label -> $detail\n";
}
echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit( $fail === 0 ? 0 : 1 );
