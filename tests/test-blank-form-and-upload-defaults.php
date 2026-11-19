<?php
/**
 * An untouched upload field is not data, and a form with nothing in it says so.
 *
 * core-efb.js seeds one row per file / drag-and-drop / recorder / signature
 * field the moment the form renders - { value: "@file@", url: "", state: 0 } -
 * and endMessage_emsFormBuilder_view() posts whatever is left in that list when
 * the visitor submits. Two things followed from the server treating that seed
 * as the visitor's data:
 *
 *   1. A form carrying an OPTIONAL upload field could never be submitted with
 *      that field left alone. The placeholder fell into the "claims an
 *      attachment but has no usable URL" branch and answered
 *      "Please enter valid value for the <b>File upload</b> field."
 *   2. A submission with nothing filled in at all was never literally empty on
 *      such a form, so the blank-submission branch was unreachable and the
 *      visitor got that same misleading upload error instead.
 *
 * Part 2 uses a form of its own (a text field and an optional file field), created through the builder's sanitizer
 * and removed at the end, so forms other tests leave on the site (the newest one may have a schedule, a limit or a
 * required upload) do not change what is tested.
 *
 * Run: php tests/test-blank-form-and-upload-defaults.php   (EFB_WP_LOAD=/path/to/wp-load.php, or the e2e env)
 */

// The plugin inside a site (wp-content/plugins/…), or the site named by EFB_WP_LOAD / EFB_E2E_SITE_DIR (the e2e site).
require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; }
	if ( '' !== $detail ) { echo "       {$detail}\n"; }
}

/* ---------------------------------------------------------------------------
 * Part 1 - the placeholder filter itself, over every shape a row can take.
 * ------------------------------------------------------------------------ */

echo "=== which rows count as the seeded default ===\n";

$public = new \Emsfb\_Public();
$ref    = new ReflectionClass( $public );

$is_default = $ref->getMethod( 'is_untouched_upload_row_efb' );
$is_default->setAccessible( true );
$strip = $ref->getMethod( 'strip_untouched_upload_rows_efb' );
$strip->setAccessible( true );
$required = $ref->getMethod( 'validate_required_fields_present_efb' );
$required->setAccessible( true );

$seed = function ( $overrides = array() ) {
	return array_merge( array(
		'id_'     => 'ucx3ndji6',
		'value'   => '@file@',
		'state'   => 0,
		'url'     => '',
		'type'    => 'file',
		'name'    => 'File upload',
		'form_id' => '205',
	), $overrides );
};

// Exactly what core-efb.js pushes for a field nobody touched.
foreach ( array( 'file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder' ) as $type ) {
	t( "the seeded {$type} row is the default",
		true === $is_default->invoke( $public, $seed( array( 'type' => $type ) ) ) );
}
t( 'a seeded row that lost its state flag is still the default',
	true === $is_default->invoke( $public, array( 'id_' => 'a', 'type' => 'file', 'value' => '@file@', 'url' => '' ) ) );
t( 'a seeded row with no url key at all is still the default',
	true === $is_default->invoke( $public, array( 'id_' => 'a', 'type' => 'file', 'value' => '@file@' ) ) );
t( 'an empty-valued upload row is the default',
	true === $is_default->invoke( $public, $seed( array( 'value' => '' ) ) ) );

// Anything the visitor actually did must survive the filter and be validated.
t( 'a completed upload is not the default',
	false === $is_default->invoke( $public, $seed( array( 'url' => home_url( '/wp-content/uploads/x.png' ), 'state' => null ) ) ) );
t( 'any non-empty url makes the row non-default',
	false === $is_default->invoke( $public, $seed( array( 'url' => 'abc' ) ) ),
	'a bogus url has to be reported, not silently dropped' );
t( 'a changed value makes the row non-default',
	false === $is_default->invoke( $public, $seed( array( 'value' => 'my-photo.png' ) ) ) );
t( 'an in-flight upload (state 1) is not the default',
	false === $is_default->invoke( $public, $seed( array( 'state' => 1 ) ) ) );
t( 'a rejected upload (state 3) is not the default',
	false === $is_default->invoke( $public, $seed( array( 'state' => 3 ) ) ) );
t( 'a state-2 row with no url is not the default',
	false === $is_default->invoke( $public, $seed( array( 'state' => 2 ) ) ),
	'state 2 claims the upload finished, so a missing url is an error worth reporting' );

// Ordinary fields must never be mistaken for upload placeholders.
t( 'an empty text row is left alone',
	false === $is_default->invoke( $public, array( 'id_' => 'a', 'type' => 'text', 'value' => '' ) ) );
t( 'a filled text row is left alone',
	false === $is_default->invoke( $public, array( 'id_' => 'a', 'type' => 'text', 'value' => 'hello' ) ) );
t( 'a non-array row is left alone',
	false === $is_default->invoke( $public, 'not-a-row' ) );

echo "\n=== stripping keeps the visitor's rows ===\n";

$mixed = array(
	array( 'id_' => 't1', 'type' => 'text', 'value' => 'hello' ),
	$seed(),
	$seed( array( 'id_' => '207ekcg0g', 'type' => 'dadfile' ) ),
	$seed( array( 'id_' => 'up2', 'url' => home_url( '/wp-content/uploads/real.png' ) ) ),
);
$kept = $strip->invoke( $public, $mixed );
t( 'both placeholders dropped, both real rows kept', 2 === count( $kept ), 'kept ' . count( $kept ) . ' of 4' );
t( 'the text row survived', isset( $kept[0]['id_'] ) && 't1' === $kept[0]['id_'] );
t( 'the completed upload survived', isset( $kept[1]['id_'] ) && 'up2' === $kept[1]['id_'] );
t( 'a submission of nothing but placeholders becomes empty',
	array() === $strip->invoke( $public, array( $seed(), $seed( array( 'id_' => 'b' ) ) ) ) );
t( 'the keys are renumbered so empty() sees an empty array',
	empty( $strip->invoke( $public, array( $seed() ) ) ) );

// get_form_public_efb() branches on $submitted_values['logout'] / ['recovery'],
// so a keyed payload must come back keyed.
$keyed = array( 'logout' => true, 'up' => $seed(), 'note' => array( 'id_' => 'n', 'type' => 'text', 'value' => 'x' ) );
$keyed_out = $strip->invoke( $public, $keyed );
t( 'a keyed payload keeps its keys when a placeholder is dropped',
	isset( $keyed_out['logout'] ) && isset( $keyed_out['note'] ) && ! isset( $keyed_out['up'] ),
	'got keys: ' . implode( ',', array_keys( (array) $keyed_out ) ) );
t( 'a payload with nothing to drop is returned untouched',
	$strip->invoke( $public, $only = array( array( 'id_' => 't', 'type' => 'text', 'value' => 'x' ) ) ) === $only );

echo "\n=== a required upload field is still enforced ===\n";

$structure = array(
	array( 'type' => 'form' ),
	array( 'type' => 'step', 'id_' => '1' ),
	array( 'type' => 'file', 'id_' => 'ucx3ndji6', 'name' => 'File upload', 'required' => true ),
	array( 'type' => 'text', 'id_' => 't1', 'name' => 'Text', 'required' => false ),
);

$only_text = array( array( 'id_' => 't1', 'type' => 'text', 'value' => 'hello' ) );
$res = $required->invoke( $public, $structure, $strip->invoke( $public, array_merge( $only_text, array( $seed() ) ) ) );
t( 'a required upload left untouched is reported as missing', empty( $res['valid'] ) );
t( 'and it is named as the culprit', isset( $res['missing_name'] ) && 'File upload' === $res['missing_name'],
	'got: ' . var_export( isset( $res['missing_name'] ) ? $res['missing_name'] : null, true ) );

$with_file = array_merge( $only_text, array( $seed( array( 'url' => home_url( '/wp-content/uploads/real.png' ) ) ) ) );
$res_ok = $required->invoke( $public, $structure, $strip->invoke( $public, $with_file ) );
t( 'a required upload that actually arrived passes', ! empty( $res_ok['valid'] ) );

$res_placeholder = $required->invoke( $public, $structure, array( $seed() ) );
t( 'the presence check alone never accepts "@file@" as a file', empty( $res_placeholder['valid'] ),
	'strip_untouched_upload_rows_efb() runs first, but the two must agree' );

/* ---------------------------------------------------------------------------
 * Part 2 - end to end through the real endpoint, on a real form with an
 * optional upload field.
 * ------------------------------------------------------------------------ */

echo "\n=== end to end ===\n";

$fn = get_efbFunction();

// A form of its own: a text field and an optional file field (saved through the builder's sanitizer).
$rid = function () { return 'efbbl' . substr( md5( uniqid( '', true ) ), 0, 6 ); };
$tid = $rid();
$uid = $rid();
$style = array( 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
$fixture = $fn->sanitize_obj_msg_efb( array(
	array( 'type' => 'form', 'steps' => 1, 'formName' => 'efb blank-form regression', 'email' => '', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => '', 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '', 'stateForm' => 0, 'thank_you' => 'msg', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' ),
	array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step 1', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'classes' => '', 'message' => '', 'visible' => 1 ),
	array_merge( $style, array( 'id_' => $tid, 'dataId' => $tid . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'Name', 'step' => 1, 'amount' => 2 ) ),
	array_merge( $style, array( 'id_' => $uid, 'dataId' => $uid . '-id', 'type' => 'file', 'elementId' => 'file', 'name' => 'File upload', 'file' => 'allformat', 'step' => 1, 'amount' => 3 ) ),
) );
$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => 'efb blank-form regression', 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $fixture, JSON_UNESCAPED_UNICODE ) ), 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
$own_fid = (int) $wpdb->insert_id;
register_shutdown_function( function () use ( $wpdb, $own_fid ) {
	if ( $own_fid > 0 ) {
		$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $own_fid ) );
		$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => $own_fid ) );
	}
} );
$target = null;
if ( $own_fid > 0 ) {
	foreach ( $fixture as $i => $f ) {
		if ( $i >= 2 && isset( $f['type'] ) && 'file' === $f['type'] ) $target = array( 'form_id' => $own_fid, 'fields' => $fixture, 'upload' => $f );
	}
}

if ( null === $target ) {
	echo "[SKIP] the test form could not be created on this site\n";
	echo "\n========================================\n";
	echo "RESULTS: {$pass} passed, {$fail} failed\n";
	exit( $fail > 0 ? 1 : 0 );
}

$fid = $target['form_id'];
echo "       using form {$fid}, upload field '{$target['upload']['id_']}' ({$target['upload']['type']})\n";

$endpoint = get_rest_url( null ) . 'Emsfb/v1/forms/message/add';

$submit = function ( $rows ) use ( $fid, $fn, $endpoint, $wpdb ) {
	$sid = $fn->efb_code_validate_create( $fid, 0, 'visit', 0 );
	$res = wp_remote_post( $endpoint, array(
		'timeout' => 30, 'sslverify' => false,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
			'sid'          => $sid,
			'form-id'      => (string) $fid,
		),
		'body' => wp_json_encode( array(
			'id' => (string) $fid, 'sid' => $sid, 'page_id' => '0',
			// `name` is the form name the client posts; the endpoint rejects the
			// payload outright without it, well before any of this is reached.
			'name' => 'efb-regression-test',
			'url' => home_url( '/' ), 'value' => wp_json_encode( $rows ), 'type' => 'form',
		) ),
	) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE sid = %s", $sid ) );
	if ( is_wp_error( $res ) ) return array( 'transport' => $res->get_error_message() );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array( 'raw' => wp_remote_retrieve_body( $res ) );
};

$upload_row = function ( $overrides = array() ) use ( $target ) {
	return array_merge( array(
		'id_'     => $target['upload']['id_'],
		'value'   => '@file@',
		'state'   => 0,
		'url'     => '',
		'type'    => $target['upload']['type'],
		'name'    => isset( $target['upload']['name'] ) ? $target['upload']['name'] : 'File upload',
		'session' => 'reciveFromClient',
		'form_id' => (string) $target['form_id'],
	), $overrides );
};

// A. nothing filled in at all - exactly what the browser posts.
$a = $submit( array( $upload_row() ) );
$lan = $fn->text_efb( array( 'PleaseFillForm' ) );
t( 'a blank submission is rejected', isset( $a['success'] ) && false === $a['success'], wp_json_encode( $a ) );
t( 'and it asks the visitor to complete the form',
	isset( $a['m'] ) && isset( $lan['PleaseFillForm'] ) && $a['m'] === $lan['PleaseFillForm'],
	'got: ' . ( isset( $a['m'] ) ? $a['m'] : '(no message)' ) );
t( 'that phrase is the one core-efb.js already shows for the same condition',
	isset( $lan['PleaseFillForm'] ) && '' !== $lan['PleaseFillForm'],
	'PleaseFillForm: ' . ( isset( $lan['PleaseFillForm'] ) ? $lan['PleaseFillForm'] : '(missing)' ) );

// B. something real filled in, upload field left alone - must go through.
$filled = null;
foreach ( $target['fields'] as $i => $f ) {
	if ( $i < 2 || empty( $f['type'] ) || empty( $f['id_'] ) ) continue;
	if ( in_array( $f['type'], array( 'text', 'textarea' ), true ) ) { $filled = $f; break; }
}
if ( null === $filled ) {
	echo "[SKIP] form {$fid} has no text field to fill, cannot test the accepted case\n";
} else {
	$b = $submit( array(
		array( 'id_' => $filled['id_'], 'type' => $filled['type'], 'value' => 'efb regression test', 'name' => $filled['name'], 'form_id' => (string) $fid ),
		$upload_row(),
	) );
	t( 'an untouched optional upload no longer blocks the submission',
		! empty( $b['success'] ), wp_json_encode( $b ) );
	if ( ! empty( $b['track'] ) ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_msg_ WHERE track = %s", $b['track'] ) );
		echo "       cleaned up test submission {$b['track']}\n";
	}
}

// C. the row carries something other than the default but no usable file.
$lan_field = $fn->text_efb( array( 'mnvvXXX_' ) );
$expected  = str_replace( '%s', '<b>' . $upload_row()['name'] . '</b>', $lan_field['mnvvXXX_'] );

foreach ( array(
	'a changed value with no url' => $upload_row( array( 'value' => 'my-photo.png' ) ),
	'state 2 with no url'         => $upload_row( array( 'state' => 2 ) ),
) as $label => $row ) {
	$c = $submit( array( $row ) );
	t( "{$label} is reported against the upload field",
		isset( $c['m'] ) && $c['m'] === $expected,
		'got: ' . ( isset( $c['m'] ) ? $c['m'] : wp_json_encode( $c ) ) );
	t( "{$label} names the right field id",
		isset( $c['field_id'] ) && $c['field_id'] === $target['upload']['id_'],
		'got: ' . ( isset( $c['field_id'] ) ? $c['field_id'] : '(none)' ) );
}

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
