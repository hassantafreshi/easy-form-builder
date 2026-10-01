<?php
/**
 * End-to-end: every field type of the public submit endpoint, driven the way
 * an attacker drives it - raw JSON rows posted straight to
 * Emsfb/v1/forms/message/add with only the public nonce - and the way the
 * bundled client drives it.
 *
 * Background: a Map marker's `address` was stored verbatim (the reported
 * stored XSS), and the same review found the row `type` was stored as the
 * browser sent it, the upload row `value` was free text, a colour only had
 * to start with '#', and an array where a string was expected fataled the
 * request. This test pins the contract for every branch of the validator:
 *
 *   1. A legitimate submission of every field type is accepted and each row
 *      is stored under the saved field's type with the expected value.
 *   2. Hostile rows - markup in every free-text slot, type confusion, extra
 *      marker keys, non-string shapes - are either rejected or stored with
 *      nothing executable left, never with a 500.
 *
 * Every form, message row and stats row this test creates is deleted at the
 * end, pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests\test-submission-row-hardening-e2e.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
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

$fn = get_efbFunction();
$form_table = $wpdb->prefix . 'emsfb_form';
$msg_table  = $wpdb->prefix . 'emsfb_msg_';
$stts_table = $wpdb->prefix . 'emsfb_stts_';
$rest_url   = get_rest_url( null ) . 'Emsfb/v1/forms/message/add';
$run_tag    = 'efb-row-hardening-' . wp_generate_password( 6, false, false );

/* Human Shield rate-limits rapid submissions from one IP, which this run
 * does on purpose. Disable it for the run and restore the prior value. */
$hsh_orig_raw = get_setting_Emsfb( 'raw' );
$hsh_settings = is_string( $hsh_orig_raw ) ? json_decode( str_replace( '\\', '', $hsh_orig_raw ) ) : $hsh_orig_raw;
if ( is_object( $hsh_settings ) && isset( $hsh_settings->AdnHSH ) ) {
	$hsh_orig_value = $hsh_settings->AdnHSH;
	$hsh_settings->AdnHSH = 0;
	$fn->set_setting_Emsfb( $hsh_settings, $hsh_settings->emailSupporter ?? '' );
	echo "[setup] Human Shield temporarily disabled for this run (was {$hsh_orig_value})\n";
	register_shutdown_function( function () use ( $fn, $hsh_orig_value ) {
		$raw = get_setting_Emsfb( 'raw' );
		$s = is_string( $raw ) ? json_decode( str_replace( '\\', '', $raw ) ) : $raw;
		if ( is_object( $s ) ) {
			$s->AdnHSH = $hsh_orig_value;
			$fn->set_setting_Emsfb( $s, $s->emailSupporter ?? '' );
			echo "[cleanup] Human Shield restored to {$hsh_orig_value}\n";
		}
	} );
}

/* ---------------------------------------------------------------- helpers */

function rh_field( $id, $type, $extra = array() ) {
	return array_merge( array(
		'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'id' => '',
		'name' => 'Field ' . $id, 'required' => false, 'amount' => 2, 'step' => '1',
	), $extra );
}
function rh_option( $id, $parent, $value ) {
	return array( 'id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option',
		'value' => $value, 'id_op' => $id, 'step' => '1', 'amount' => 2 );
}

$created_form_ids = array();
$created_sids     = array();
register_shutdown_function( function () use ( $wpdb, &$created_form_ids, &$created_sids, $form_table, $msg_table, $stts_table ) {
	foreach ( $created_form_ids as $fid ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$msg_table` WHERE form_id = %d", $fid ) );
		$wpdb->delete( $form_table, array( 'form_id' => $fid ) );
	}
	foreach ( $created_sids as $sid ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$stts_table` WHERE sid = %s", $sid ) );
	}
	echo "\n[cleanup] removed " . count( $created_form_ids ) . " form(s)\n";
} );

/**
 * @return array{code:int, body:string, data:array|null, stored:array|null, msg_id:int}
 */
function rh_submit( $rows, $fid, $fn, $url, $type = 'form', $extra = array() ) {
	global $wpdb, $created_sids, $msg_table;
	$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(msg_id),0) FROM `$msg_table` WHERE form_id = %d", $fid ) );
	$sid = $fn->efb_code_validate_create( $fid, 0, 'visit', 0 );
	$created_sids[] = $sid;
	$res = wp_remote_post( $url, array(
		'timeout' => 30, 'sslverify' => false,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
			'sid'          => $sid,
			'form-id'      => (string) $fid,
		),
		'body' => wp_json_encode( array_merge( array(
			'id' => (string) $fid, 'sid' => $sid, 'page_id' => '0',
			'url' => home_url( '/' ), 'value' => wp_json_encode( $rows ), 'type' => $type, 'name' => 'test',
		), $extra ) ),
	) );
	if ( is_wp_error( $res ) ) {
		return array( 'code' => 0, 'body' => $res->get_error_message(), 'data' => null, 'stored' => null, 'msg_id' => 0 );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $body, true );
	$data = isset( $json['data'] ) ? $json['data'] : null;

	$stored = null;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT msg_id, content FROM `$msg_table` WHERE form_id = %d AND msg_id > %d ORDER BY msg_id DESC LIMIT 1", $fid, $before ) );
	$msg_id = $row ? (int) $row->msg_id : 0;
	if ( $row ) {
		$stored = json_decode( str_replace( '\\', '', $row->content ), true );
	}
	return array( 'code' => $code, 'body' => $body, 'data' => $data, 'stored' => is_array( $stored ) ? $stored : null, 'msg_id' => $msg_id );
}

function rh_row( $stored, $id, $id_ob = null ) {
	if ( ! is_array( $stored ) ) return null;
	foreach ( $stored as $r ) {
		if ( ! is_array( $r ) || ( $r['id_'] ?? null ) !== $id ) continue;
		if ( $id_ob !== null && ( $r['id_ob'] ?? null ) !== $id_ob ) continue;
		return $r;
	}
	return null;
}

function rh_not_fatal( $r ) {
	return 500 !== $r['code'] && false === stripos( $r['body'], 'critical error' ) && false === stripos( $r['body'], 'Uncaught' ) && false === stripos( $r['body'], 'Fatal error' );
}

function rh_no_markup( $stored ) {
	$json = wp_json_encode( $stored );
	return false === stripos( $json, '<img' ) && false === stripos( $json, 'onerror' ) && false === stripos( $json, '<script' ) && false === stripos( $json, 'javascript:' ) && false === stripos( $json, '<svg' );
}

/* --------------------------------------------------------------- fixture */

$esign_ok = 'data:image/png;base64,' . base64_encode( hex2bin( '89504e470d0a1a0a0000000d49484452' ) );

$fid = null;
{
	$wpdb->insert( $form_table, array(
		'form_name'        => "{$run_tag}-all-types",
		'form_structer'    => wp_json_encode( array(
			array( 'type' => 'form', 'steps' => 1, 'formName' => 'All types', 'captcha' => false, 'thank_you' => 'msg',
				'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ),
				'sendEmail' => false, 'trackingCode' => true ),
			array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1 ),
			rh_field( 't1', 'text', array( 'name' => 'Full name' ) ),
			rh_field( 'ta1', 'textarea' ),
			rh_field( 'e1', 'email' ),
			rh_field( 'u1', 'url' ),
			rh_field( 'm1', 'mobile' ),
			rh_field( 'c1', 'color' ),
			rh_field( 'map1', 'maps', array( 'mark' => 1, 'lat' => 35.7, 'lng' => 51.4, 'zoom' => 10 ) ),
			rh_field( 'r1', 'rating' ),
			rh_field( 'p5', 'pointr5' ),
			rh_field( 'n1', 'number' ),
			rh_field( 'rg1', 'range' ),
			rh_field( 'd1', 'date' ),
			rh_field( 's1', 'select' ),
			rh_option( 's1o1', 's1', 'One' ),
			rh_option( 's1o2', 's1', 'Two' ),
			rh_field( 'ms1', 'multiselect' ),
			rh_option( 'ms1o1', 'ms1', 'Alpha' ),
			rh_option( 'ms1o2', 'ms1', 'Beta' ),
			rh_field( 'cb1', 'checkbox', array( 'name' => 'Colors' ) ),
			rh_option( 'cb1o1', 'cb1', 'Blue' ),
			rh_option( 'cb1o2', 'cb1', 'Green' ),
			rh_field( 'rd1', 'radio' ),
			rh_option( 'rd1o1', 'rd1', 'Red' ),
			rh_option( 'rd1o2', 'rd1', 'Yellow' ),
			rh_field( 'sw1', 'switch', array( 'on' => 'Enabled', 'off' => 'Disabled' ) ),
			rh_field( 'yn1', 'yesNo' ),
			rh_field( 'smp1', 'sample' ),
			rh_field( 'es1', 'esign' ),
			rh_field( 'tm1', 'table_matrix', array( 'name' => 'Matrix' ) ),
			array( 'id_' => 'tm1r1', 'dataId' => 'tm1r1-id', 'type' => 'r_matrix', 'value' => 'Quality', 'step' => '1', 'amount' => 2 ),
			rh_field( 'pc1', 'postalcode' ),
		), JSON_UNESCAPED_UNICODE ),
		'form_email'       => get_option( 'admin_email' ),
		'form_created_by'  => get_current_user_id(),
		'form_type'        => 'form',
		'form_create_date' => current_time( 'mysql' ),
	) );
	$fid = (int) $wpdb->insert_id;
	wp_cache_delete( 'efb_form_' . md5( 'form_' . $fid ), 'emsfb' );
	wp_cache_delete( 'efb_form_' . $fid, 'emsfb' );
	$created_form_ids[] = $fid;
}
t( 'fixture form created', $fid > 0, "form_id={$fid}" );

/* ------------------------------------------- 1. legitimate: every type */

echo "\n=== 1. one legitimate submission carrying every field type ===\n";

$legit = array(
	array( 'id_' => 't1',  'name' => 'Full name', 'type' => 'text',      'value' => 'Hassan' ),
	array( 'id_' => 'ta1', 'name' => 'ta1',       'type' => 'textarea',  'value' => 'line one@efb@nq#line two' ),
	array( 'id_' => 'e1',  'name' => 'e1',        'type' => 'email',     'value' => 'visitor@example.com' ),
	array( 'id_' => 'u1',  'name' => 'u1',        'type' => 'url',       'value' => 'https://example.com/page?a=1' ),
	array( 'id_' => 'm1',  'name' => 'm1',        'type' => 'mobile',    'value' => '+989121234567' ),
	array( 'id_' => 'c1',  'name' => 'c1',        'type' => 'color',     'value' => '#FF8800' ),
	array( 'id_' => 'map1','name' => 'map1',      'type' => 'maps',      'value' => array( array( 'lat' => 35.7, 'lng' => 51.4, 'address' => 'Tehran, Valiasr St' ) ) ),
	array( 'id_' => 'r1',  'name' => 'r1',        'type' => 'rating',    'value' => '4' ),
	array( 'id_' => 'p5',  'name' => 'p5',        'type' => 'pointr5',   'value' => 3 ),
	array( 'id_' => 'n1',  'name' => 'n1',        'type' => 'number',    'value' => '42' ),
	array( 'id_' => 'rg1', 'name' => 'rg1',       'type' => 'range',     'value' => '7' ),
	array( 'id_' => 'd1',  'name' => 'd1',        'type' => 'date',      'value' => '2026-09-23' ),
	array( 'id_' => 's1',  'name' => 's1',        'type' => 'select',    'value' => 'One',  'id_ob' => 's1o1' ),
	array( 'id_' => 'ms1', 'name' => 'ms1',       'type' => 'multiselect','value' => 'Alpha@efb!Beta@efb!' ),
	array( 'id_' => 'cb1', 'name' => 'Colors',    'type' => 'checkbox',  'value' => 'Blue',  'id_ob' => 'cb1o1' ),
	array( 'id_' => 'cb1', 'name' => 'Colors',    'type' => 'checkbox',  'value' => 'Green', 'id_ob' => 'cb1o2' ),
	// The bundled client sends the DOM type "radio" for a pre-checked radio.
	array( 'id_' => 'rd1', 'name' => 'rd1',       'type' => 'radio',     'value' => 'Red',   'id_ob' => 'rd1o1' ),
	array( 'id_' => 'sw1', 'name' => 'sw1',       'type' => 'switch',    'value' => '1' ),
	array( 'id_' => 'yn1', 'name' => 'yn1',       'type' => 'yesNo',     'value' => 'Yes' ),
	array( 'id_' => 'smp1','name' => 'smp1',      'type' => 'sample',    'value' => 'sample text' ),
	array( 'id_' => 'es1', 'name' => 'es1',       'type' => 'esign',     'value' => $esign_ok ),
	array( 'id_' => 'tm1', 'name' => 'Matrix',    'type' => 'r_matrix',  'value' => '4', 'id_ob' => 'tm1r1' ),
	array( 'id_' => 'pc1', 'name' => 'pc1',       'type' => 'postalcode','value' => '12345' ),
);
$r = rh_submit( $legit, $fid, $fn, $rest_url );
t( 'legitimate all-types submission is accepted', ( $r['data']['success'] ?? null ) === true, substr( wp_json_encode( $r['data'] ), 0, 200 ) );
t( 'legitimate submission was stored', is_array( $r['stored'] ), "msg_id={$r['msg_id']}" );
$s = $r['stored'];

$expect_type = array(
	't1' => 'text', 'ta1' => 'textarea', 'e1' => 'email', 'u1' => 'url', 'm1' => 'mobile', 'c1' => 'color',
	'map1' => 'maps', 'r1' => 'rating', 'p5' => 'pointr5', 'n1' => 'number', 'rg1' => 'range', 'd1' => 'date',
	's1' => 'select', 'ms1' => 'multiselect', 'rd1' => 'radio', 'sw1' => 'switch', 'yn1' => 'yesNo',
	'smp1' => 'sample', 'es1' => 'esign', 'pc1' => 'postalcode',
);
foreach ( $expect_type as $id => $type ) {
	$row = rh_row( $s, $id );
	t( "{$id} stored under its saved type '{$type}'", $row !== null && ( $row['type'] ?? '' ) === $type, $row ? 'type=' . ( $row['type'] ?? '(none)' ) : '(row missing)' );
}
$row = rh_row( $s, 'cb1', 'cb1o1' );
t( 'checkbox option stored under the parent type checkbox', $row !== null && $row['type'] === 'checkbox' && $row['value'] === 'Blue' );
$row = rh_row( $s, 'cb1', 'cb1o2' );
t( 'second checkbox option kept its own row', $row !== null && $row['value'] === 'Green' );
$row = rh_row( $s, 'tm1', 'tm1r1' );
t( 'matrix row stored as r_matrix with the saved row name and table label',
	$row !== null && $row['type'] === 'r_matrix' && $row['name'] === 'Quality' && $row['label'] === 'Matrix' && (string) $row['value'] === '4',
	wp_json_encode( $row ) );

$row = rh_row( $s, 't1' );  t( 'text value round-trips', $row && $row['value'] === 'Hassan' );
$row = rh_row( $s, 'ta1' ); t( 'textarea line-break token survives sanitizing', $row && $row['value'] === 'line one@efb@nq#line two', $row['value'] ?? '' );
$row = rh_row( $s, 'e1' );  t( 'email value round-trips', $row && $row['value'] === 'visitor@example.com' );
$row = rh_row( $s, 'u1' );  t( 'url value round-trips', $row && $row['value'] === 'https://example.com/page?a=1', $row['value'] ?? '' );
$row = rh_row( $s, 'm1' );  t( 'mobile value round-trips', $row && $row['value'] === '+989121234567' );
$row = rh_row( $s, 'c1' );  t( 'colour is stored as normalised #rrggbb', $row && $row['value'] === '#ff8800', $row['value'] ?? '' );
$row = rh_row( $s, 'map1' );
t( 'map marker stored with exactly lat, lng and address',
	$row && is_array( $row['value'] ) && count( $row['value'] ) === 1 && array_keys( $row['value'][0] ) === array( 'lat', 'lng', 'address' ),
	wp_json_encode( $row['value'] ?? null ) );
t( 'map address text round-trips', $row && ( $row['value'][0]['address'] ?? '' ) === 'Tehran, Valiasr St' );
t( 'map coordinates round-trip', $row && (string) $row['value'][0]['lat'] === '35.7' && (string) $row['value'][0]['lng'] === '51.4' );
$row = rh_row( $s, 'r1' );  t( 'rating stored as 4', $row && (int) $row['value'] === 4 );
$row = rh_row( $s, 'p5' );  t( 'numeric (non-string) point value accepted and stored as 3', $row && (int) $row['value'] === 3 );
$row = rh_row( $s, 'n1' );  t( 'number round-trips', $row && (string) $row['value'] === '42' );
$row = rh_row( $s, 'rg1' ); t( 'range round-trips', $row && (string) $row['value'] === '7' );
$row = rh_row( $s, 'd1' );  t( 'date round-trips', $row && $row['value'] === '2026-09-23' );
$row = rh_row( $s, 's1' );  t( 'select stores the saved option label', $row && $row['value'] === 'One' );
$row = rh_row( $s, 'ms1' ); t( 'multiselect stores the matched options', $row && $row['value'] === 'Alpha@efb!Beta@efb!', $row['value'] ?? '' );
$row = rh_row( $s, 'rd1' ); t( 'radio stores the saved option label', $row && $row['value'] === 'Red' );
$row = rh_row( $s, 'sw1' ); t( 'switch stores the saved "on" label', $row && $row['value'] === 'Enabled', $row['value'] ?? '' );
$row = rh_row( $s, 'yn1' ); t( 'yesNo round-trips', $row && $row['value'] === 'Yes' );
$row = rh_row( $s, 'smp1' );t( 'sample round-trips', $row && $row['value'] === 'sample text' );
$row = rh_row( $s, 'es1' ); t( 'signature data URL round-trips', $row && $row['value'] === $esign_ok );
$row = rh_row( $s, 'pc1' ); t( 'postal code round-trips', $row && $row['value'] === '12345' );
t( 'legitimate content contains no markup', rh_no_markup( $s ) );
t( 'no row carries a key outside the allow-list',
	! array_filter( $s, function ( $r ) { return is_array( $r ) && array_intersect_key( $r, array( 'evil' => 1, 'onerror' => 1, 'state' => 1 ) ); } ) );

/* --------------------------------------- 2. hostile rows, one per probe */

echo "\n=== 2. hostile rows: never a 500, never stored markup, never a browser-chosen type ===\n";

$IMG = '<img src=x onerror=alert(document.domain)>';
$benign = array( 'id_' => 'pc1', 'name' => 'pc1', 'type' => 'postalcode', 'value' => '99999' );

/**
 * label => [ row, expectations ]
 *   accept: true|false|null (null = either is fine)
 *   type:   expected stored type when accepted
 *   id/id_ob: which stored row to inspect
 *   value:  expected stored value when accepted (optional)
 *   check:  callable(row) extra assertion (optional)
 */
$probes = array(
	'map address with markup' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'value' => array( array( 'lat' => 40.7, 'lng' => -74.0, 'address' => $IMG ) ) ),
		array( 'accept' => true, 'id' => 'map1', 'type' => 'maps',
			'check' => function ( $row ) { return false === strpos( $row['value'][0]['address'], '<' ); } ),
	),
	'map marker with extra keys' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'value' => array( array( 'lat' => 1, 'lng' => 2, 'address' => 'ok', 'evil' => $IMG, 'popup' => '<b>x' ) ) ),
		array( 'accept' => true, 'id' => 'map1', 'type' => 'maps',
			'check' => function ( $row ) { return array_keys( $row['value'][0] ) === array( 'lat', 'lng', 'address' ); } ),
	),
	'map marker with non-numeric lat' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'value' => array( array( 'lat' => $IMG, 'lng' => 2, 'address' => 'x' ) ) ),
		array( 'accept' => false ),
	),
	'map marker that is a string, not an object' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'value' => array( $IMG ) ),
		array( 'accept' => false ),
	),
	'map value that is a string' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'value' => $IMG ),
		array( 'accept' => false ),
	),
	'map row with an extra top-level key' => array(
		array( 'id_' => 'map1', 'name' => 'map1', 'type' => 'maps', 'evil' => $IMG, 'value' => array( array( 'lat' => 1, 'lng' => 2, 'address' => 'ok' ) ) ),
		array( 'accept' => true, 'id' => 'map1', 'type' => 'maps', 'check' => function ( $row ) { return ! isset( $row['evil'] ); } ),
	),
	'text row claiming type color (style-attribute sink)' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'color', 'value' => 'red;background:url(https://evil.example/x)' ),
		array( 'accept' => true, 'id' => 't1', 'type' => 'text' ),
	),
	'text row claiming type esign (img-src sink)' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'esign', 'value' => 'data:text/html,<script>alert(1)</script>' ),
		array( 'accept' => true, 'id' => 't1', 'type' => 'text' ),
	),
	'text row claiming type maps with an array value' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'maps', 'value' => array( array( 'lat' => 1, 'lng' => 2, 'address' => $IMG ) ) ),
		array( 'accept' => null, 'id' => 't1', 'type' => 'text', 'check' => function ( $row ) { return is_string( $row['value'] ); } ),
	),
	'text row claiming type r_matrix' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'r_matrix', 'value' => $IMG ),
		array( 'accept' => true, 'id' => 't1', 'type' => 'text' ),
	),
	'text row claiming type checkbox' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'checkbox', 'value' => $IMG ),
		array( 'accept' => null, 'id' => 't1', 'type' => 'text' ),
	),
	'text value with markup' => array(
		array( 'id_' => 't1', 'name' => $IMG, 'type' => 'text', 'value' => $IMG ),
		array( 'accept' => true, 'id' => 't1', 'type' => 'text', 'check' => function ( $row ) { return false === strpos( $row['name'], '<' ); } ),
	),
	'textarea value with markup' => array(
		array( 'id_' => 'ta1', 'name' => 'ta1', 'type' => 'textarea', 'value' => 'a@efb@nq#' . $IMG ),
		array( 'accept' => true, 'id' => 'ta1', 'type' => 'textarea' ),
	),
	'url value with a javascript: scheme' => array(
		array( 'id_' => 'u1', 'name' => 'u1', 'type' => 'url', 'value' => 'javascript:alert(1)' ),
		array( 'accept' => null, 'id' => 'u1', 'type' => 'url', 'check' => function ( $row ) { return false === stripos( $row['value'], 'javascript' ); } ),
	),
	'mobile value with markup' => array(
		array( 'id_' => 'm1', 'name' => 'm1', 'type' => 'mobile', 'value' => '+1' . $IMG ),
		array( 'accept' => null, 'id' => 'm1', 'type' => 'mobile' ),
	),
	'colour with six arbitrary characters' => array(
		array( 'id_' => 'c1', 'name' => 'c1', 'type' => 'color', 'value' => '#;url(' ),
		array( 'accept' => false ),
	),
	'colour with markup' => array(
		array( 'id_' => 'c1', 'name' => 'c1', 'type' => 'color', 'value' => '#<b>ab' ),
		array( 'accept' => false ),
	),
	'colour that is an array (used to fatal in strlen)' => array(
		array( 'id_' => 'c1', 'name' => 'c1', 'type' => 'color', 'value' => array( '#ffffff' ) ),
		array( 'accept' => false ),
	),
	'rating with markup' => array(
		array( 'id_' => 'r1', 'name' => 'r1', 'type' => 'rating', 'value' => $IMG ),
		array( 'accept' => false ),
	),
	'select with an option that does not exist' => array(
		array( 'id_' => 's1', 'name' => 's1', 'type' => 'select', 'value' => $IMG, 'id_ob' => 's1o1' ),
		array( 'accept' => false ),
	),
	'checkbox option value with markup' => array(
		array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'checkbox', 'value' => $IMG, 'id_ob' => 'cb1o1' ),
		array( 'accept' => true, 'id' => 'cb1', 'id_ob' => 'cb1o1', 'type' => 'checkbox' ),
	),
	'checkbox option claiming type payCheckbox' => array(
		array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'payCheckbox', 'value' => 'Blue', 'id_ob' => 'cb1o1', 'price' => '999' ),
		array( 'accept' => true, 'id' => 'cb1', 'id_ob' => 'cb1o1', 'type' => 'checkbox', 'check' => function ( $row ) { return ! isset( $row['price'] ); } ),
	),
	'select option smuggled through the checkbox branch' => array(
		array( 'id_' => 's1', 'name' => 's1', 'type' => 'checkbox', 'value' => $IMG, 'id_ob' => 's1o1' ),
		array( 'accept' => false ),
	),
	'radio claiming type imgRadio with a src' => array(
		array( 'id_' => 'rd1', 'name' => 'rd1', 'type' => 'imgRadio', 'value' => 'Red', 'id_ob' => 'rd1o1', 'src' => 'javascript:alert(1)' ),
		array( 'accept' => true, 'id' => 'rd1', 'type' => 'radio', 'check' => function ( $row ) { return ! isset( $row['src'] ) && $row['value'] === 'Red'; } ),
	),
	'switch with markup instead of 1/0' => array(
		array( 'id_' => 'sw1', 'name' => 'sw1', 'type' => 'switch', 'value' => $IMG ),
		array( 'accept' => true, 'id' => 'sw1', 'type' => 'switch', 'value' => 'Disabled' ),
	),
	'sample with markup' => array(
		array( 'id_' => 'smp1', 'name' => 'smp1', 'type' => 'sample', 'value' => $IMG ),
		array( 'accept' => true, 'id' => 'smp1', 'type' => 'sample' ),
	),
	'signature with markup after the prefix' => array(
		array( 'id_' => 'es1', 'name' => 'es1', 'type' => 'esign', 'value' => 'data:image/png;base64,' . $IMG ),
		array( 'accept' => false ),
	),
	'matrix rating with markup around a digit' => array(
		array( 'id_' => 'tm1', 'name' => 'Matrix', 'type' => 'r_matrix', 'value' => '<b>4</b>', 'id_ob' => 'tm1r1' ),
		array( 'accept' => null, 'id' => 'tm1', 'id_ob' => 'tm1r1', 'type' => 'r_matrix' ),
	),
	'matrix row claiming type esign' => array(
		array( 'id_' => 'tm1', 'name' => 'Matrix', 'type' => 'esign', 'value' => '3', 'id_ob' => 'tm1r1' ),
		array( 'accept' => null, 'id' => 'tm1', 'id_ob' => 'tm1r1', 'type' => 'r_matrix' ),
	),
	'email value that is an array (used to fatal in sanitize_email)' => array(
		array( 'id_' => 'e1', 'name' => 'e1', 'type' => 'email', 'value' => array( 'a@b.co' ) ),
		array( 'accept' => false ),
	),
	'url value that is an array (used to fatal in sanitize_url)' => array(
		array( 'id_' => 'u1', 'name' => 'u1', 'type' => 'url', 'value' => array( 'https://a.b' ) ),
		array( 'accept' => null, 'id' => 'u1', 'type' => 'url', 'check' => function ( $row ) { return is_string( $row['value'] ); } ),
	),
	'row whose type is an array' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => array( 'text' ), 'value' => 'x' ),
		array( 'accept' => null, 'id' => 't1', 'type' => 'text' ),
	),
	'row whose id_ is an array' => array(
		array( 'id_' => array( 't1' ), 'name' => 't1', 'type' => 'text', 'value' => 'x' ),
		array( 'accept' => null ),
	),
	'row whose id_ob is an array' => array(
		array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'checkbox', 'value' => 'Blue', 'id_ob' => array( 'cb1o1' ) ),
		array( 'accept' => null ),
	),
	'row whose amount and price are arrays' => array(
		array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'checkbox', 'value' => 'Blue', 'id_ob' => 'cb1o1', 'amount' => array( 9 ), 'price' => array( 9 ) ),
		array( 'accept' => null, 'id' => 'cb1', 'id_ob' => 'cb1o1', 'type' => 'checkbox', 'check' => function ( $row ) { return ! isset( $row['price'] ) && ! is_array( $row['amount'] ?? null ); } ),
	),
	'checkbox option with a qty on a plain checkbox' => array(
		array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'checkbox', 'value' => 'Blue', 'id_ob' => 'cb1o1', 'qty' => $IMG ),
		array( 'accept' => true, 'id' => 'cb1', 'id_ob' => 'cb1o1', 'type' => 'checkbox', 'check' => function ( $row ) { return ! isset( $row['qty'] ); } ),
	),
	'radio claiming payRadio with a browser price' => array(
		array( 'id_' => 'rd1', 'name' => 'rd1', 'type' => 'payRadio', 'value' => 'Red', 'id_ob' => 'rd1o1', 'price' => '1' ),
		array( 'accept' => true, 'id' => 'rd1', 'type' => 'radio', 'check' => function ( $row ) { return ! isset( $row['price'] ); } ),
	),
	'row whose name is an array' => array(
		array( 'id_' => 't1', 'name' => array( $IMG ), 'type' => 'text', 'value' => 'x' ),
		array( 'accept' => null, 'id' => 't1', 'type' => 'text', 'check' => function ( $row ) { return is_string( $row['name'] ); } ),
	),
	'row that is a bare string' => array(
		$IMG,
		array( 'accept' => null ),
	),
	'row with a value of boolean true' => array(
		array( 'id_' => 't1', 'name' => 't1', 'type' => 'text', 'value' => true ),
		array( 'accept' => null, 'id' => 't1', 'type' => 'text' ),
	),
);

foreach ( $probes as $label => $probe ) {
	list( $row_in, $ex ) = $probe;
	$r = rh_submit( array( $row_in, $benign ), $fid, $fn, $rest_url );
	$accepted = ( $r['data']['success'] ?? null ) === true;
	$detail = 'HTTP ' . $r['code'] . ' accepted=' . var_export( $accepted, true ) . ' ' . substr( wp_json_encode( $r['data'] ), 0, 120 );

	t( "[{$label}] does not fatal", rh_not_fatal( $r ), $detail );

	if ( $ex['accept'] === true ) {
		t( "[{$label}] is accepted", $accepted, $detail );
	} elseif ( $ex['accept'] === false ) {
		t( "[{$label}] is rejected", ! $accepted, $detail );
	}

	if ( $accepted ) {
		t( "[{$label}] stored nothing executable", is_array( $r['stored'] ) && rh_no_markup( $r['stored'] ), substr( wp_json_encode( $r['stored'] ), 0, 200 ) );
		if ( isset( $ex['id'] ) ) {
			$row = rh_row( $r['stored'], $ex['id'], $ex['id_ob'] ?? null );
			if ( $row === null ) {
				// The hostile row was dropped and only the benign one stored: acceptable.
				t( "[{$label}] hostile row dropped, benign row kept", rh_row( $r['stored'], 'pc1' ) !== null );
			} else {
				if ( isset( $ex['type'] ) ) {
					t( "[{$label}] stored type is the saved field type '{$ex['type']}'", ( $row['type'] ?? '' ) === $ex['type'], 'type=' . ( $row['type'] ?? '(none)' ) );
				}
				if ( array_key_exists( 'value', $ex ) ) {
					t( "[{$label}] stored value is as expected", $row['value'] === $ex['value'], 'value=' . wp_json_encode( $row['value'] ) );
				}
				if ( isset( $ex['check'] ) ) {
					t( "[{$label}] extra invariant holds", (bool) call_user_func( $ex['check'], $row ), wp_json_encode( $row ) );
				}
			}
		}
	} else {
		t( "[{$label}] nothing was stored", $r['stored'] === null, "msg_id={$r['msg_id']}" );
	}
}

/* ------------------------------------------------ 3. the other form types */

echo "\n=== 3. survey, subscribe and payment forms run the same contract ===\n";

function rh_make_form( $type, $fields, $head_extra = array() ) {
	global $wpdb, $form_table, $created_form_ids, $run_tag;
	$wpdb->insert( $form_table, array(
		'form_name'        => "{$run_tag}-{$type}",
		'form_structer'    => wp_json_encode( array_merge( array(
			array_merge( array( 'type' => $type, 'steps' => 1, 'formName' => ucfirst( $type ), 'captcha' => false, 'thank_you' => 'msg',
				'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ),
				'sendEmail' => false, 'trackingCode' => true ), $head_extra ),
			array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1 ),
		), $fields ), JSON_UNESCAPED_UNICODE ),
		'form_email'       => get_option( 'admin_email' ),
		'form_created_by'  => get_current_user_id(),
		'form_type'        => $type,
		'form_create_date' => current_time( 'mysql' ),
	) );
	$fid = (int) $wpdb->insert_id;
	wp_cache_delete( 'efb_form_' . md5( 'form_' . $fid ), 'emsfb' );
	wp_cache_delete( 'efb_form_' . $fid, 'emsfb' );
	$created_form_ids[] = $fid;
	return $fid;
}

/* survey: rating + checkbox + text, posted as type=survey */
$survey_fid = rh_make_form( 'survey', array(
	rh_field( 'r1', 'rating' ),
	rh_field( 'cb1', 'checkbox', array( 'name' => 'Colors' ) ),
	rh_option( 'cb1o1', 'cb1', 'Blue' ),
	rh_field( 't1', 'text' ),
) );
$r = rh_submit( array(
	array( 'id_' => 'r1', 'name' => 'r1', 'type' => 'rating', 'value' => '5' ),
	array( 'id_' => 'cb1', 'name' => 'Colors', 'type' => 'payCheckbox', 'value' => $IMG, 'id_ob' => 'cb1o1', 'price' => '77' ),
	array( 'id_' => 't1', 'name' => 't1', 'type' => 'color', 'value' => $IMG ),
), $survey_fid, $fn, $rest_url, 'survey' );
t( '[survey] hostile-but-valid submission is accepted', ( $r['data']['success'] ?? null ) === true, substr( wp_json_encode( $r['data'] ), 0, 160 ) );
t( '[survey] nothing executable stored', is_array( $r['stored'] ) && rh_no_markup( $r['stored'] ) );
$row = rh_row( $r['stored'], 'cb1', 'cb1o1' );
t( '[survey] checkbox row pinned to checkbox, no browser price', $row && $row['type'] === 'checkbox' && ! isset( $row['price'] ), wp_json_encode( $row ) );
$row = rh_row( $r['stored'], 't1' );
t( '[survey] text row pinned to text', $row && $row['type'] === 'text', wp_json_encode( $row ) );
$row = rh_row( $r['stored'], 'r1' );
t( '[survey] rating kept', $row && (int) $row['value'] === 5 );
$r = rh_submit( array( array( 'id_' => 'r1', 'name' => 'r1', 'type' => 'rating', 'value' => '5' ) ), $survey_fid, $fn, $rest_url, 'form' );
t( '[survey] wrong submission type is still rejected', ( $r['data']['success'] ?? null ) === false );

/* subscribe: email + text, posted as type=subscribe */
$sub_fid = rh_make_form( 'subscribe', array(
	rh_field( 'e1', 'email' ),
	rh_field( 't1', 'text' ),
) );
$r = rh_submit( array(
	array( 'id_' => 'e1', 'name' => 'e1', 'type' => 'email', 'value' => 'sub@example.com' ),
	array( 'id_' => 't1', 'name' => $IMG, 'type' => 'esign', 'value' => $IMG ),
), $sub_fid, $fn, $rest_url, 'subscribe' );
t( '[subscribe] submission accepted', ( $r['data']['success'] ?? null ) === true, substr( wp_json_encode( $r['data'] ), 0, 160 ) );
t( '[subscribe] nothing executable stored', is_array( $r['stored'] ) && rh_no_markup( $r['stored'] ) );
$row = rh_row( $r['stored'], 't1' );
t( '[subscribe] text row pinned to text with a tag-free name', $row && $row['type'] === 'text' && strpos( $row['name'], '<' ) === false, wp_json_encode( $row ) );
$row = rh_row( $r['stored'], 'e1' );
t( '[subscribe] email kept', $row && $row['value'] === 'sub@example.com' );
$r = rh_submit( array( array( 'id_' => 'e1', 'name' => 'e1', 'type' => 'email', 'value' => array( 'x@y.z' ) ) ), $sub_fid, $fn, $rest_url, 'subscribe' );
t( '[subscribe] array email does not fatal', rh_not_fatal( $r ), 'HTTP ' . $r['code'] );

/* payment: the final submit merges raw rows with the intent-time rows */
$pay_fid = rh_make_form( 'payment', array(
	rh_field( 't1', 'text', array( 'name' => 'Full name' ) ),
	rh_field( 'map1', 'maps', array( 'mark' => 1, 'lat' => 1, 'lng' => 1, 'zoom' => 5 ) ),
	rh_field( 'pc1', 'payCheckbox', array( 'name' => 'Plan' ) ),
	array_merge( rh_option( 'pc1o1', 'pc1', 'Gold' ), array( 'price' => '10' ) ),
), array( 'getway' => 'stripe', 'currency' => 'usd' ) );
$pay_track = '2609' . strtoupper( wp_generate_password( 7, false, false ) );
$intent_rows = array(
	array( 'id_' => 'pc1', 'name' => 'Plan', 'type' => 'payCheckbox', 'value' => 'Gold', 'id_ob' => 'pc1o1', 'price' => '10' ),
	array( 'id_' => 'payment', 'name' => 'payment', 'amount' => 0, 'total' => 10, 'type' => 'payment', 'paymentGateway' => 'stripe',
		'paymentIntent' => 'pi_test_' . $pay_track, 'paymentmethod' => 'charge', 'paymentCreated' => current_time( 'mysql' ), 'paymentcurrency' => 'usd' ),
);
$wpdb->insert( $msg_table, array(
	'form_id' => $pay_fid, 'track' => $pay_track, 'ip' => '127.0.0.1', 'form_title_x' => 'Payment',
	'content' => str_replace( '"', '\\"', wp_json_encode( $intent_rows, JSON_UNESCAPED_UNICODE ) ),
	'date' => current_time( 'mysql' ), 'read_' => 2, 'read_by' => 0,
) );
t( '[payment] intent-time row seeded', (int) $wpdb->insert_id > 0 );
$r = rh_submit( array(
	array( 'id_' => 't1', 'name' => 'Full name', 'type' => 'color', 'value' => $IMG, 'price' => '999', 'evil' => $IMG ),
	array( 'id_' => 'map1', 'name' => 'Field map1', 'type' => 'maps', 'value' => array( array( 'lat' => 1, 'lng' => 2, 'address' => $IMG, 'evil' => 1 ) ) ),
	array( 'id_' => 'pc1', 'name' => 'Plan', 'type' => 'payCheckbox', 'value' => 'Gold', 'id_ob' => 'pc1o1', 'price' => '1' ),
), $pay_fid, $fn, $rest_url, 'payment', array( 'payid' => $pay_track, 'payment' => 'stripe' ) );
t( '[payment] final submit accepted', ( $r['data']['success'] ?? null ) === true, 'HTTP ' . $r['code'] . ' ' . substr( wp_json_encode( $r['data'] ), 0, 160 ) );
$pay_row = $wpdb->get_row( $wpdb->prepare( "SELECT content FROM `$msg_table` WHERE track = %s", $pay_track ) );
$pay_stored = $pay_row ? json_decode( str_replace( '\\', '', $pay_row->content ), true ) : null;
t( '[payment] merged record is parseable', is_array( $pay_stored ), $pay_row ? substr( $pay_row->content, 0, 160 ) : '(none)' );
t( '[payment] nothing executable stored', is_array( $pay_stored ) && rh_no_markup( $pay_stored ) );
$row = rh_row( $pay_stored, 't1' );
t( '[payment] text row pinned to text, no browser price, no extra key',
	$row && $row['type'] === 'text' && ! isset( $row['price'] ) && ! isset( $row['evil'] ), wp_json_encode( $row ) );
$row = rh_row( $pay_stored, 'map1' );
t( '[payment] map marker rebuilt as lat/lng/address', $row && isset( $row['value'][0] ) && array_keys( $row['value'][0] ) === array( 'lat', 'lng', 'address' ), wp_json_encode( $row ) );
$row = rh_row( $pay_stored, 'pc1', 'pc1o1' );
t( '[payment] intent-time priced row kept with the saved price (browser price ignored)', $row && (string) $row['price'] === '10', wp_json_encode( $row ) );
$row = rh_row( $pay_stored, 'payment' );
t( '[payment] payment row kept', $row && ( $row['paymentGateway'] ?? '' ) === 'stripe' );

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
echo "========================================\n";
exit( $fail > 0 ? 1 : 0 );
