<?php
/**
 * Connectivity, endpoint drift, and the sites this service can never call back.
 *
 * Three behaviours that the main deactivation-feedback suite cannot reach,
 * because it runs the client and the service on the same host and so always
 * takes the happy path through both:
 *
 *   1. A site the service cannot route to - localhost, a LAN address, a
 *      staging box behind a firewall - registers and reports all the same,
 *      unverified. Refusing it outright made the "unverified but accepted"
 *      path unreachable for exactly the installs it was written for.
 *   2. "Your server could not connect" and "our server answered badly" are
 *      different sentences. On a host whose outbound traffic is filtered,
 *      nothing is wrong with White Studio, and saying otherwise sends the
 *      administrator to check the wrong thing.
 *   3. An identity is addressed to the host that issued it. Point the plugin
 *      at a different server and the stored one must be thrown away, or every
 *      later report goes silently to the old address.
 *
 * Run: C:\xampp\php\php.exe tests/test-feedback-connectivity.php
 *
 * @package Easy_Form_Builder
 */

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$service = 'ws-efb-feedback/ws-efb-feedback.php';
$passed  = 0;
$failed  = 0;
$notes   = array();

/**
 * Assert.
 *
 * @param string $label  What is being checked.
 * @param bool   $ok     Whether it held.
 * @param string $detail Shown when it did not.
 * @return void
 */
function efbc_t( $label, $ok, $detail = '' ) {
	global $passed, $failed, $notes;

	if ( $ok ) {
		++$passed;
		echo "  [PASS] {$label}\n";
		return;
	}

	++$failed;
	$notes[] = $label . ( '' !== $detail ? ' -- ' . $detail : '' );
	echo "  [FAIL] {$label}" . ( '' !== $detail ? "   {$detail}" : '' ) . "\n";
}

/**
 * The service's table name.
 *
 * @param string $name Short name.
 * @return string
 */
function efbc_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'ws_efb_' . $name;
}

/**
 * Reach into a protected method.
 *
 * @param object $object Instance.
 * @param string $method Method name.
 * @param array  $args   Arguments.
 * @return mixed
 */
function efbc_call( $object, $method, $args = array() ) {
	$ref = new ReflectionMethod( get_class( $object ), $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( $object, $args );
}

if ( ! is_plugin_active( $service ) ) {
	echo "The feedback service is not active on this site.\n";
	echo "Run: php tests/seed-deactivation-feedback-env.php setup\n";
	exit( 1 );
}

// Everything this run creates is removed at the end.
$base_site   = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . efbc_table( 'sites' ) );
$base_report = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . efbc_table( 'reports' ) );
$base_event  = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . efbc_table( 'events' ) );
$saved_id    = get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );

$wpdb->query( 'TRUNCATE TABLE ' . efbc_table( 'limits' ) );

$client = new \Emsfb\Deactivation_Feedback();
$home   = untrailingslashit( home_url() );

/* ---------------------------------------------------------------------------
 * 1. Which addresses the service will take
 * ------------------------------------------------------------------------ */

echo "\n[1] Addresses the service will and will not accept\n";

/**
 * Register one site_url straight against the local service.
 *
 * @param string $site_url Address to claim.
 * @return array {code, body}
 */
function efbc_register( $site_url ) {
	$response = wp_remote_post(
		untrailingslashit( home_url() ) . '/wp-json/ws-efb/v1/register',
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'site_url'       => $site_url,
					'plugin_version' => 'test',
					'locale'         => 'en_US',
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'code' => 0,
			'body' => array(),
		);
	}

	return array(
		'code' => (int) wp_remote_retrieve_response_code( $response ),
		'body' => (array) json_decode( (string) wp_remote_retrieve_body( $response ), true ),
	);
}

// Malformed shapes stay refused: no real home_url() looks like any of these.
$malformed = array(
	'a scheme that is not http'   => 'ftp://example.com/',
	'a port outside 80 and 443'   => 'https://example.com:8080/',
	'credentials in the authority' => 'https://user:pass@example.com/',
	'no host at all'              => 'https:///nowhere',
);

foreach ( $malformed as $label => $url ) {
	$result = efbc_register( $url );
	efbc_t( "refused: {$label}", 400 === $result['code'], 'got ' . $result['code'] );
}

// A site behind a firewall is an ordinary install that cannot be called back.
$unroutable = array(
	'a loopback name'      => 'http://localhost/wp/',
	'a LAN address'        => 'http://192.168.10.20/',
	'the metadata address' => 'http://169.254.169.254/',
	'a .local name'        => 'http://studio.local/',
);

$unroutable_ids = array();

foreach ( $unroutable as $label => $url ) {
	$result = efbc_register( $url );
	$ok     = 200 === $result['code'] && ! empty( $result['body']['site_id'] );
	efbc_t( "registered anyway: {$label}", $ok, 'got ' . $result['code'] );

	if ( $ok ) {
		$unroutable_ids[ $label ] = $result['body']['site_id'];
	}
}

if ( ! empty( $unroutable_ids ) ) {
	$one    = reset( $unroutable_ids );
	$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . efbc_table( 'sites' ) . ' WHERE site_id = %s', $one ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	efbc_t( 'and stored as unverifiable, not as a normal new site', \WS_EFB_Feedback_REST::STATUS_UNVERIFIABLE === $status, 'status ' . $status );

	$logged = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . efbc_table( 'events' ) . ' WHERE id > %d AND event = %s', $base_event, 'local_site_registered' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	efbc_t( 'and the decision is written to the security log', $logged >= count( $unroutable_ids ), 'events ' . $logged );
}

// The one address in private space this server may still call back is its own.
$self = efbc_register( untrailingslashit( home_url() ) . '/' );
if ( ! empty( $self['body']['site_id'] ) ) {
	$self_status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . efbc_table( 'sites' ) . ' WHERE site_id = %s', $self['body']['site_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	efbc_t( "the service's own host stays verifiable even on a private address", 'new' === $self_status, 'status ' . $self_status );
} else {
	efbc_t( "the service's own host stays verifiable even on a private address", false, 'register returned ' . $self['code'] );
}

/* ---------------------------------------------------------------------------
 * 2. Such a site is never fetched, and can still report
 * ------------------------------------------------------------------------ */

echo "\n[2] What an unverifiable site may do\n";

if ( ! empty( $unroutable_ids ) ) {
	$id = reset( $unroutable_ids );

	$verify = wp_remote_post(
		$home . '/wp-json/ws-efb/v1/verify',
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'site_id' => $id ) ),
		)
	);

	$verify_body = is_wp_error( $verify ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $verify ), true );
	efbc_t( 'verify answers unverified without an error', isset( $verify_body['status'] ) && 'unverified' === $verify_body['status'], wp_json_encode( $verify_body ) );

	$after = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . efbc_table( 'sites' ) . ' WHERE site_id = %s', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	efbc_t( 'and the stored status still says it was never attempted', \WS_EFB_Feedback_REST::STATUS_UNVERIFIABLE === $after, 'status ' . $after );

	// The one thing that must never happen: an outbound fetch of that address.
	efbc_t(
		'the ownership fetch refuses the address on its own too',
		false === \WS_EFB_Feedback_Security::verify_site_ownership( 'http://192.168.10.20/', $id, 'x' )['ok'],
		'the fetch guard would have run'
	);

	$domain = $wpdb->get_var( $wpdb->prepare( 'SELECT domain FROM ' . efbc_table( 'sites' ) . ' WHERE site_id = %s', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$secret = \WS_EFB_Feedback_Security::site_secret( $id, $domain );

	$body      = wp_json_encode(
		array(
			'reason'     => 'bug',
			'details'    => 'connectivity suite: a report from a site that cannot be called back.',
			'email'      => 'diag@example.com',
			'contact_ok' => 1,
			'env'        => array( 'locale' => 'en_US' ),
		)
	);
	$timestamp = time();
	$nonce     = bin2hex( random_bytes( 16 ) );
	$canonical = implode( "\n", array( 'v1', $id, (string) $timestamp, $nonce, hash( 'sha256', $body ) ) );

	$report = wp_remote_post(
		$home . '/wp-json/ws-efb/v1/report',
		array(
			'timeout' => 20,
			'headers' => array(
				'Content-Type'    => 'application/json',
				'X-WSF-Site'      => $id,
				'X-WSF-Timestamp' => (string) $timestamp,
				'X-WSF-Nonce'     => $nonce,
				'X-WSF-Signature' => hash_hmac( 'sha256', $canonical, $secret ),
			),
			'body'    => $body,
		)
	);

	$report_code = is_wp_error( $report ) ? 0 : (int) wp_remote_retrieve_response_code( $report );
	$report_body = is_wp_error( $report ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $report ), true );

	efbc_t( 'its bug report is accepted', in_array( $report_code, array( 200, 201 ), true ), 'got ' . $report_code );
	efbc_t( 'and the coupon waits for a human rather than being issued', isset( $report_body['coupon']['state'] ) && 'pending' === $report_body['coupon']['state'], wp_json_encode( isset( $report_body['coupon'] ) ? $report_body['coupon'] : null ) );
}

/* ---------------------------------------------------------------------------
 * 3. Two failures, two sentences
 * ------------------------------------------------------------------------ */

echo "\n[3] Telling 'you cannot connect' from 'we answered badly'\n";
$wpdb->query( 'TRUNCATE TABLE ' . efbc_table( 'limits' ) );

$text    = $client->strings_efb();
$offline = efbc_call( $client, 'failure_message_efb', array( \Emsfb\Deactivation_Feedback::FAILURE_OFFLINE ) );
$server  = efbc_call( $client, 'failure_message_efb', array( \Emsfb\Deactivation_Feedback::FAILURE_SERVER ) );

efbc_t( 'a blocked outbound connection has its own sentence', $offline === $text['sendFailedOffline'] );
efbc_t( 'a bad answer from us has its own sentence', $server === $text['sendFailedServer'] );
efbc_t( 'the two are not the same sentence', $offline !== $server );
efbc_t( 'neither is empty', '' !== trim( $offline ) && '' !== trim( $server ) );
efbc_t( 'an unknown reason still says something', '' !== trim( efbc_call( $client, 'failure_message_efb', array( '' ) ) ) );

// The offline sentence must not blame White Studio, and the server one must
// not blame the administrator's network. That is the entire point of having
// two of them.
efbc_t(
	'the offline sentence does not claim our server is broken',
	false === stripos( $offline, 'our server did not answer' ),
	$offline
);
efbc_t(
	'the server sentence tells the administrator their site is fine',
	false !== stripos( $server, 'nothing is wrong with your site' ),
	$server
);

// A host that cannot resolve anything is the Iranian-server case: no HTTP
// conversation happens, and that must classify as offline, not as our outage.
$unreachable = function () {
	return array( 'https://this-host-does-not-resolve.invalid' );
};
add_filter( 'emsfb_feedback_endpoints_efb', $unreachable );

delete_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
delete_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );

$offline_client = new \Emsfb\Deactivation_Feedback();
$result         = $offline_client->send_report_efb(
	array(
		'reason'  => 'bug',
		'details' => 'connectivity suite: this should never leave the machine.',
		'env'     => array(),
	)
);

efbc_t( 'a send with nowhere to go fails', empty( $result['ok'] ) );
efbc_t( 'and is reported as a connection problem, not an outage', isset( $result['failure'] ) && \Emsfb\Deactivation_Feedback::FAILURE_OFFLINE === $result['failure'], wp_json_encode( $result ) );

$backoff = get_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );
efbc_t( 'the backoff remembers why, so the next click says the same thing', \Emsfb\Deactivation_Feedback::FAILURE_OFFLINE === $backoff, var_export( $backoff, true ) );

$second = $offline_client->send_report_efb(
	array(
		'reason'  => 'bug',
		'details' => 'connectivity suite: the second click, while backed off.',
		'env'     => array(),
	)
);
efbc_t( 'the second click keeps the connection wording', isset( $second['failure'] ) && \Emsfb\Deactivation_Feedback::FAILURE_OFFLINE === $second['failure'], wp_json_encode( $second ) );

remove_filter( 'emsfb_feedback_endpoints_efb', $unreachable );
delete_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );

// A host that answers with an HTTP error is the other case entirely.
$wrong_route = function () {
	return array( untrailingslashit( home_url() ) . '/not-the-feedback-service' );
};
add_filter( 'emsfb_feedback_endpoints_efb', $wrong_route );
delete_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );

$server_client = new \Emsfb\Deactivation_Feedback();
$server_result = $server_client->send_report_efb(
	array(
		'reason'  => 'bug',
		'details' => 'connectivity suite: reached a host that answered badly.',
		'env'     => array(),
	)
);

efbc_t( 'a host that answers with an error is not called a connection problem', isset( $server_result['failure'] ) && \Emsfb\Deactivation_Feedback::FAILURE_SERVER === $server_result['failure'], wp_json_encode( $server_result ) );

remove_filter( 'emsfb_feedback_endpoints_efb', $wrong_route );
delete_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );

/* ---------------------------------------------------------------------------
 * 4. An identity belongs to the host that issued it
 * ------------------------------------------------------------------------ */

echo "\n[4] Moving the plugin to a different server\n";
/*
 * The service allows five registrations per domain per day and this suite has
 * spent several already. Clearing the buckets keeps the run repeatable; the
 * caps themselves are what the main matrix covers.
 */
$wpdb->query( 'TRUNCATE TABLE ' . efbc_table( 'limits' ) );

delete_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
delete_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );

$drift_client = new \Emsfb\Deactivation_Feedback();
$first        = $drift_client->ensure_identity_efb();
";
";
efbc_t( 'the client registers against the configured host', ! empty( $first['site_id'] ), wp_json_encode( $first ) );

if ( ! empty( $first['site_id'] ) ) {
	// Pretend the identity was issued by somebody else entirely.
	$stale             = $first;
	$stale['endpoint'] = 'https://an-old-server.example';
	update_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY, $stale, false );

	$again = $drift_client->ensure_identity_efb();

	efbc_t( 'an identity from another host is thrown away', ! empty( $again['site_id'] ) && $again['site_id'] !== $stale['site_id'], wp_json_encode( $again ) );
	efbc_t( 'and the replacement points at the host now configured', isset( $again['endpoint'] ) && untrailingslashit( $again['endpoint'] ) === $home, isset( $again['endpoint'] ) ? $again['endpoint'] : '(none)' );

	// The same identity, unchanged, must survive - re-registering on every
	// call would burn the service's five-a-day registration budget.
	$third = $drift_client->ensure_identity_efb();
	efbc_t( 'an identity from the right host is kept', isset( $third['site_id'] ) && $third['site_id'] === $again['site_id'] );
}

/* ---------------------------------------------------------------------------
 * 5. Put everything back
 * ------------------------------------------------------------------------ */

echo "\n[5] Teardown\n";

$new_sites = $wpdb->get_col( $wpdb->prepare( 'SELECT site_id FROM ' . efbc_table( 'sites' ) . ' WHERE id > %d', $base_site ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
foreach ( (array) $new_sites as $site_id ) {
	$wpdb->delete( efbc_table( 'nonces' ), array( 'site_id' => $site_id ), array( '%s' ) );
}

$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . efbc_table( 'reports' ) . ' WHERE id > %d', $base_report ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . efbc_table( 'sites' ) . ' WHERE id > %d', $base_site ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . efbc_table( 'events' ) . ' WHERE id > %d', $base_event ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( 'TRUNCATE TABLE ' . efbc_table( 'limits' ) );

if ( false === $saved_id ) {
	delete_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
} else {
	update_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY, $saved_id, false );
}
delete_transient( \Emsfb\Deactivation_Feedback::REGISTER_BACKOFF );

$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . efbc_table( 'reports' ) . ' WHERE id > %d', $base_report ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
efbc_t( 'the run left no reports behind', 0 === $left, (string) $left );
efbc_t( 'the stored identity is back as it was', get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY ) === $saved_id );

printf( "\n=== %d passed, %d failed ===\n", $passed, $failed );

if ( $failed ) {
	echo "\nFailures:\n";
	foreach ( $notes as $note ) {
		echo "  - {$note}\n";
	}
}

exit( $failed ? 1 : 0 );
