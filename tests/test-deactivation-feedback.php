<?php
/**
 * End-to-end tests for the deactivation feedback flow.
 *
 * Covers both halves of the feature:
 *
 *   Client (this plugin)  - includes/class-Emsfb-deactivation-feedback.php
 *   Service (White Studio) - plugins/ws-efb-feedback/
 *
 * and the contract between them: the signature, the ownership proof, the
 * reason allow-list and the coupon rules.
 *
 * The security assertions are the point of this file. Each one reproduces a
 * concrete attack against the public endpoint - forged signature, replayed
 * request, stale timestamp, oversized body, honeypot, SSRF through the
 * verification callback, spreadsheet formula injection in the export - and
 * asserts it is refused.
 *
 * Every row, option and plugin state this test touches is restored at the end,
 * pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests/test-deactivation-feedback.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$pass     = 0;
$fail     = 0;
$failures = array();

/**
 * Assert one condition.
 *
 * @param string $name  Test name.
 * @param bool   $cond  Result.
 * @param string $extra Context printed when it fails.
 * @return bool
 */
function efb_t( $name, $cond, $extra = '' ) {
	global $pass, $fail, $failures;

	if ( $cond ) {
		$pass++;
		echo "  [PASS] {$name}\n";
		return true;
	}

	$fail++;
	$failures[] = $name . ( '' !== $extra ? ' -- ' . $extra : '' );
	echo "  [FAIL] {$name}" . ( '' !== $extra ? " -- {$extra}" : '' ) . "\n";
	return false;
}

echo "\n=== Deactivation feedback: client + White Studio service ===\n";

/* ---------------------------------------------------------------------------
 * 0. Bootstrap
 * ------------------------------------------------------------------------ */

echo "\n[0] Environment\n";

$service_file      = WP_PLUGIN_DIR . '/ws-efb-feedback/ws-efb-feedback.php';
$service_basename  = 'ws-efb-feedback/ws-efb-feedback.php';
$service_was_active = is_plugin_active( $service_basename );

if ( ! is_readable( $service_file ) ) {
	echo "[SKIP] The WS Feedback Hub plugin is not installed at {$service_file}\n";
	exit( 0 );
}

if ( ! $service_was_active ) {
	// Activating gives the HTTP leg of the test a live endpoint. It is switched
	// back off in the teardown below.
	$activated = activate_plugin( $service_basename );
	if ( is_wp_error( $activated ) ) {
		echo '[SKIP] Could not activate the service plugin: ' . $activated->get_error_message() . "\n";
		exit( 0 );
	}
}

if ( ! class_exists( 'WS_EFB_Feedback_REST' ) ) {
	require_once $service_file;
}

// activate_plugin() includes the file but plugins_loaded has already fired, so
// the service's own bootstrap never ran in this process. Run it by hand;
// calling it twice is harmless.
ws_efb_feedback_bootstrap();

efb_t( 'client class is available', class_exists( '\\Emsfb\\Deactivation_Feedback' ) );
efb_t( 'service classes are available', class_exists( 'WS_EFB_Feedback_REST' ) && class_exists( 'WS_EFB_Feedback_Security' ) );

$t_sites   = WS_EFB_Feedback_Install::table( 'sites' );
$t_reports = WS_EFB_Feedback_Install::table( 'reports' );
$t_coupons = WS_EFB_Feedback_Install::table( 'coupons' );
$t_nonces  = WS_EFB_Feedback_Install::table( 'nonces' );
$t_limits  = WS_EFB_Feedback_Install::table( 'limits' );
$t_events  = WS_EFB_Feedback_Install::table( 'events' );

foreach ( array( $t_sites, $t_reports, $t_coupons, $t_nonces, $t_limits, $t_events ) as $table ) {
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	efb_t( "table exists: {$table}", $exists === $table );
}

// Baselines for the teardown.
$base_site   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$t_sites}" );
$base_report = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$t_reports}" );
$base_coupon = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$t_coupons}" );
$base_event  = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$t_events}" );
$prev_identity = get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
$prev_state    = get_option( \Emsfb\Deactivation_Feedback::OPTION_STATE );

$created_site_ids = array();

/**
 * Drop the rate-limit buckets this test fills, so one phase cannot starve the
 * next. Only the buckets belonging to this test are removed.
 */
function efb_clear_limits( $site_ids ) {
	global $wpdb;

	$table = WS_EFB_Feedback_Install::table( 'limits' );

	// Two identities matter: the CLI process (no REMOTE_ADDR) for the in-process
	// REST phase, and the loopback address Apache sees for the HTTP phase.
	$ip_hashes = array();
	foreach ( array( WS_EFB_Feedback_Security::client_ip(), '127.0.0.1', '::1' ) as $ip ) {
		$ip_hashes[] = WS_EFB_Feedback_Security::hash_ip( $ip );
	}

	$buckets = array(
		array( 'register_domain', 'efb-test.example', DAY_IN_SECONDS ),
	);

	foreach ( array_unique( $ip_hashes ) as $ip_hash ) {
		$buckets[] = array( 'public', $ip_hash, HOUR_IN_SECONDS );
		$buckets[] = array( 'report_ip', $ip_hash, DAY_IN_SECONDS );
	}

	$home_host = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	$buckets[] = array( 'register_domain', $home_host, DAY_IN_SECONDS );

	foreach ( (array) $site_ids as $site_id ) {
		$buckets[] = array( 'report_site', $site_id, DAY_IN_SECONDS );
		$buckets[] = array( 'verify_site', $site_id, HOUR_IN_SECONDS );
	}

	foreach ( $buckets as $bucket ) {
		list( $action, $identifier, $window ) = $bucket;
		// Two windows are cleared: the current one and the previous one, so a
		// test that starts near a boundary still gets a clean slate.
		for ( $back = 0; $back <= 1; $back++ ) {
			$start = (int) ( floor( ( time() - ( $back * $window ) ) / $window ) * $window );
			$key   = hash( 'sha256', $action . '|' . $identifier . '|' . $window . '|' . $start );
			$wpdb->delete( $table, array( 'bucket' => $key ), array( '%s' ) );
		}
	}
}

/* ---------------------------------------------------------------------------
 * 1. Wiring
 * ------------------------------------------------------------------------ */

echo "\n[1] Wiring\n";

$bootstrap = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
efb_t(
	'plugin bootstrap requires the feedback class',
	false !== strpos( $bootstrap, 'includes/class-Emsfb-deactivation-feedback.php' )
);
efb_t(
	'plugin bootstrap instantiates the feedback class',
	false !== strpos( $bootstrap, 'new \\Emsfb\\Deactivation_Feedback()' )
);
efb_t(
	'the class is loaded outside the is_admin() block, so the public proof route exists',
	strpos( $bootstrap, 'new \\Emsfb\\Deactivation_Feedback()' ) < strpos( $bootstrap, "includes/admin/class-Emsfb-admin.php" )
);

efb_t(
	'modal stylesheet ships',
	is_readable( dirname( __DIR__ ) . '/includes/admin/assets/css/deactivation-feedback-efb.css' )
);
efb_t(
	'modal script ships',
	is_readable( dirname( __DIR__ ) . '/includes/admin/assets/js/deactivation-feedback-efb.js' )
);

$client = new \Emsfb\Deactivation_Feedback();

/* ---------------------------------------------------------------------------
 * 2. The contract between the two halves
 * ------------------------------------------------------------------------ */

echo "\n[2] Client/service contract\n";

$client_reasons  = $client->reasons_efb();
$service_reasons = WS_EFB_Feedback_REST::reasons();

efb_t(
	'both sides offer the same reasons',
	array_keys( $client_reasons ) === array_keys( $service_reasons ),
	'client: ' . implode( ',', array_keys( $client_reasons ) ) . ' | service: ' . implode( ',', array_keys( $service_reasons ) )
);

$detail_mismatch = array();
foreach ( $client_reasons as $key => $reason ) {
	if ( ! isset( $service_reasons[ $key ] ) || (bool) $reason['detail'] !== (bool) $service_reasons[ $key ] ) {
		$detail_mismatch[] = $key;
	}
}
efb_t( 'both sides agree on which reasons need a written detail', empty( $detail_mismatch ), implode( ',', $detail_mismatch ) );
efb_t( '"bug" is offered and demands a description', ! empty( $client_reasons['bug']['detail'] ) );
efb_t( '"bug" is the only reason that advertises the reward', ! empty( $client_reasons['bug']['reward'] ) && empty( $client_reasons['other']['reward'] ) );

$proof_site      = str_repeat( 'a', 32 );
$proof_challenge = str_repeat( 'b', 64 );
efb_t(
	'ownership proof is computed identically on both sides',
	hash_hmac( 'sha256', 'ws-efb-proof|' . $proof_site, $proof_challenge ) === WS_EFB_Feedback_Security::challenge_proof( $proof_site, $proof_challenge )
);

$sig_body   = '{"reason":"bug"}';
$sig_secret = 'test-secret';
$sig_ts     = 1700000000;
$sig_nonce  = str_repeat( 'c', 32 );
$client_sig = hash_hmac(
	'sha256',
	implode( "\n", array( 'v1', $proof_site, (string) $sig_ts, $sig_nonce, hash( 'sha256', $sig_body ) ) ),
	$sig_secret
);
efb_t(
	'request signature is computed identically on both sides',
	$client_sig === WS_EFB_Feedback_Security::signature( $proof_site, $sig_ts, $sig_nonce, $sig_body, $sig_secret )
);

/* ---------------------------------------------------------------------------
 * 3. What the modal says, and what it sends
 * ------------------------------------------------------------------------ */

echo "\n[3] Wording and payload\n";

$strings = $client->strings_efb();
$empty   = array();
foreach ( $strings as $key => $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		$empty[] = $key;
	}
}
efb_t( 'every modal string has a value', empty( $empty ), implode( ',', $empty ) );
efb_t( 'the reward promise names the 100% first year', false !== strpos( $strings['rewardTitle'], '100%' ) );

$fa_filter = function () {
	return 'fa_IR';
};
add_filter( 'locale', $fa_filter );
$fa = $client->strings_efb();
remove_filter( 'locale', $fa_filter );

efb_t( 'a Persian admin reads the reward line in Persian', false !== strpos( $fa['rewardTitle'], 'تخفیف' ), $fa['rewardTitle'] );
efb_t( 'Persian keeps every key filled', count( array_filter( $fa, 'strlen' ) ) === count( $fa ) );

$env       = $client->collect_env_efb();
$allowed   = array( 'plugin_version', 'wp_version', 'php_version', 'mysql_version', 'locale', 'theme', 'is_multisite', 'plugins_count', 'forms_count', 'pro_state', 'installed_days' );
$unexpected = array_diff( array_keys( $env ), $allowed );
efb_t( 'the environment payload carries nothing beyond the documented keys', empty( $unexpected ), implode( ',', $unexpected ) );
efb_t( 'the environment payload carries no address, user or form content', ! isset( $env['email'] ) && ! isset( $env['admin_email'] ) && ! isset( $env['users'] ) );
efb_t( 'the environment payload survives the service sanitiser unchanged in shape', count( WS_EFB_Feedback_Security::sanitize_env( $env ) ) === count( $env ) );

$injected = WS_EFB_Feedback_Security::sanitize_env( array_merge( $env, array( 'evil' => str_repeat( 'x', 9000 ) ) ) );
efb_t( 'the service drops environment keys it did not ask for', ! isset( $injected['evil'] ) );

// The form count is only useful if it is real: it was pointed at a table name
// that does not exist ("emsfb_forms" rather than "emsfb_form") and silently
// reported zero for every site.
$forms_table = $wpdb->prefix . 'emsfb_form';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $forms_table ) ) === $forms_table ) {
	$real_forms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" );
	efb_t( 'the reported form count is the real one', (int) $env['forms_count'] === $real_forms, "reported {$env['forms_count']}, actual {$real_forms}" );
} else {
	echo "  [NOTE] no forms table on this install, form count not asserted\n";
}
efb_t( 'the reported plugin version is this plugin', $env['plugin_version'] === EMSFB_PLUGIN_VERSION );

/* ---------------------------------------------------------------------------
 * 4. Service input hardening
 * ------------------------------------------------------------------------ */

echo "\n[4] Service input hardening\n";

$dirty = "<script>alert('x')</script>Real bug: <b>the form</b> breaks\x00\x07 on step 2";
$clean = WS_EFB_Feedback_Security::sanitize_details( $dirty );
efb_t( 'script tags and their contents are removed from a report', false === strpos( $clean, '<script' ) && false === strpos( $clean, 'alert(' ), $clean );
efb_t( 'no markup survives sanitising', false === strpos( $clean, '<' ) && false === strpos( $clean, '>' ), $clean );
efb_t( 'the readable part of the message survives', false !== strpos( $clean, 'Real bug' ) && false !== strpos( $clean, 'step 2' ), $clean );
efb_t( 'control characters are stripped', false === strpos( $clean, "\x00" ) && false === strpos( $clean, "\x07" ) );

$encoded = WS_EFB_Feedback_Security::sanitize_details( '&lt;script&gt;alert(1)&lt;/script&gt;' );
efb_t( 'entity-encoded markup cannot be resurrected', false === strpos( $encoded, '<script' ), $encoded );

$long = WS_EFB_Feedback_Security::sanitize_details( str_repeat( 'a', 9000 ) );
efb_t( 'an over-long message is capped', strlen( $long ) <= WS_EFB_Feedback_Security::MAX_DETAILS );

efb_t(
	'a real bug report scores as legitimate',
	WS_EFB_Feedback_Security::spam_score( 'bug', 'The multi step form loses uploaded files when I go back to step 1.', 'a@example.com', true ) < WS_EFB_Feedback_Security::SPAM_THRESHOLD
);
efb_t(
	'a link farm scores as spam',
	WS_EFB_Feedback_Security::spam_score( 'bug', 'buy backlinks http://a.tld http://b.tld http://c.tld http://d.tld', 'x@mailinator.com', false ) >= WS_EFB_Feedback_Security::SPAM_THRESHOLD
);
efb_t(
	'an empty "bug" claim cannot farm a coupon',
	WS_EFB_Feedback_Security::spam_score( 'bug', 'bug', '', false ) >= WS_EFB_Feedback_Security::SPAM_THRESHOLD
);
efb_t(
	'a one word "bug" cannot farm a coupon even from a verified site',
	WS_EFB_Feedback_Security::spam_score( 'bug', 'bug', 'real@example.com', true ) >= WS_EFB_Feedback_Security::SPAM_THRESHOLD
);
efb_t(
	'the client and the service agree on the shortest usable bug report',
	\Emsfb\Deactivation_Feedback::MIN_BUG_DETAILS === WS_EFB_Feedback_Security::MIN_BUG_DETAILS
);

efb_t( 'a fresh timestamp is accepted', WS_EFB_Feedback_Security::timestamp_is_fresh( time() ) );
efb_t( 'a ten minute old timestamp is refused', ! WS_EFB_Feedback_Security::timestamp_is_fresh( time() - 600 ) );
efb_t( 'a timestamp from the future is refused', ! WS_EFB_Feedback_Security::timestamp_is_fresh( time() + 600 ) );
efb_t( 'a nonsense timestamp is refused', ! WS_EFB_Feedback_Security::timestamp_is_fresh( 0 ) );

$nonce_a = bin2hex( random_bytes( 16 ) );
efb_t( 'a fresh nonce is accepted once', WS_EFB_Feedback_Security::consume_nonce( $nonce_a, 'test' ) );
efb_t( 'the same nonce is refused the second time', ! WS_EFB_Feedback_Security::consume_nonce( $nonce_a, 'test' ) );
efb_t( 'a malformed nonce is refused', ! WS_EFB_Feedback_Security::consume_nonce( 'not-a-nonce', 'test' ) );
$wpdb->delete( $t_nonces, array( 'nonce' => $nonce_a ), array( '%s' ) );

$limit_id = 'test-' . bin2hex( random_bytes( 6 ) );
$allowed_hits = 0;
for ( $i = 0; $i < 5; $i++ ) {
	if ( WS_EFB_Feedback_Security::rate_limit( 'unit_test', $limit_id, 3, HOUR_IN_SECONDS ) ) {
		$allowed_hits++;
	}
}
efb_t( 'the rate limiter allows exactly the configured number of hits', 3 === $allowed_hits, "allowed {$allowed_hits}" );

echo "\n[4b] SSRF guard on the verification callback\n";

$ssrf_targets = array(
	'http://127.0.0.1:3306/'         => 'loopback',
	'http://169.254.169.254/latest/' => 'cloud metadata',
	'http://192.168.1.1/'            => 'private range',
	'file:///c:/windows/win.ini'     => 'file scheme',
	'gopher://127.0.0.1:6379/_FLUSH' => 'gopher scheme',
);

foreach ( $ssrf_targets as $target => $label ) {
	$result = WS_EFB_Feedback_Security::verify_site_ownership( $target, $proof_site, $proof_challenge );
	// Reaching a host is not the same as trusting it: what matters is that no
	// probe of an internal service is ever reported as a verified site.
	efb_t( "verification refuses {$label}", empty( $result['ok'] ), wp_json_encode( $result ) );
}

echo "\n[4c] Export hardening\n";

efb_t( 'a formula cell is neutralised for spreadsheets', "'=cmd|'/c calc'!A1" === WS_EFB_Feedback_Admin::csv_cell( "=cmd|'/c calc'!A1" ) );
efb_t( 'a plus-prefixed cell is neutralised', "'+1234" === WS_EFB_Feedback_Admin::csv_cell( '+1234' ) );
efb_t( 'an ordinary cell is untouched', 'the form broke' === WS_EFB_Feedback_Admin::csv_cell( 'the form broke' ) );

/* ---------------------------------------------------------------------------
 * 5. The REST endpoints, end to end
 * ------------------------------------------------------------------------ */

echo "\n[5] REST endpoints\n";

efb_clear_limits( array() );

// Silence outgoing mail for the in-process phase, and remember what would have
// been sent so the coupon email can be asserted.
$sent_mail = array();
$mail_trap = function ( $short_circuit, $atts ) use ( &$sent_mail ) {
	$sent_mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $mail_trap, 10, 2 );

/**
 * Build one signed /report request.
 */
function efb_signed_report_request( $site_id, $secret, $payload, $overrides = array() ) {
	$body      = wp_json_encode( $payload );
	$timestamp = isset( $overrides['timestamp'] ) ? $overrides['timestamp'] : time();
	$nonce     = isset( $overrides['nonce'] ) ? $overrides['nonce'] : bin2hex( random_bytes( 16 ) );
	$sign_body = isset( $overrides['sign_body'] ) ? $overrides['sign_body'] : $body;

	$signature = WS_EFB_Feedback_Security::signature( $site_id, $timestamp, $nonce, $sign_body, $secret );
	if ( isset( $overrides['signature'] ) ) {
		$signature = $overrides['signature'];
	}

	$request = new WP_REST_Request( 'POST', '/ws-efb/v1/report' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'X-WSF-Site', isset( $overrides['site_header'] ) ? $overrides['site_header'] : $site_id );
	$request->set_header( 'X-WSF-Timestamp', (string) $timestamp );
	$request->set_header( 'X-WSF-Nonce', $nonce );
	$request->set_header( 'X-WSF-Signature', $signature );
	$request->set_body( isset( $overrides['body'] ) ? $overrides['body'] : $body );

	return $request;
}

$register = new WP_REST_Request( 'POST', '/ws-efb/v1/register' );
$register->set_header( 'Content-Type', 'application/json' );
$register->set_body(
	wp_json_encode(
		array(
			'site_url'       => home_url( '/' ),
			'plugin_version' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '0',
			'locale'         => get_locale(),
		)
	)
);

$response = rest_do_request( $register );
$data     = $response->get_data();

efb_t( 'registration succeeds', 200 === $response->get_status() && ! empty( $data['site_id'] ), wp_json_encode( $data ) );

$site_id   = isset( $data['site_id'] ) ? $data['site_id'] : '';
$secret    = isset( $data['secret'] ) ? $data['secret'] : '';
$challenge = isset( $data['challenge'] ) ? $data['challenge'] : '';
$created_site_ids[] = $site_id;

efb_t( 'the identity is a 32 character hex id', (bool) preg_match( '/^[a-f0-9]{32}$/', (string) $site_id ) );
efb_t( 'the challenge is a 64 character hex string', (bool) preg_match( '/^[a-f0-9]{64}$/', (string) $challenge ) );
efb_t(
	'the secret is derived from the master key, not stored in the sites table',
	$secret === WS_EFB_Feedback_Security::site_secret( $site_id, strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) )
);

$stored_secret_column = $wpdb->get_results( "SHOW COLUMNS FROM {$t_sites} LIKE 'secret'" );
efb_t( 'the sites table has no secret column at all', empty( $stored_secret_column ) );

$bad_register = new WP_REST_Request( 'POST', '/ws-efb/v1/register' );
$bad_register->set_header( 'Content-Type', 'application/json' );
$bad_register->set_body( wp_json_encode( array( 'site_url' => 'javascript:alert(1)' ) ) );
efb_t( 'registration refuses a non-http site URL', 400 === rest_do_request( $bad_register )->get_status() );

// Publish the challenge the way the plugin does, then let the service fetch it.
update_option(
	\Emsfb\Deactivation_Feedback::OPTION_IDENTITY,
	array(
		'site_id'   => $site_id,
		'secret'    => $secret,
		'challenge' => $challenge,
		'endpoint'  => untrailingslashit( home_url() ),
		'created'   => time(),
	),
	false
);

$verify = new WP_REST_Request( 'POST', '/ws-efb/v1/verify' );
$verify->set_header( 'Content-Type', 'application/json' );
$verify->set_body( wp_json_encode( array( 'site_id' => $site_id ) ) );
$verify_response = rest_do_request( $verify );
$verify_data     = $verify_response->get_data();
$is_verified     = isset( $verify_data['status'] ) && 'verified' === $verify_data['status'];

efb_t( 'the verification round trip answers', 200 === $verify_response->get_status(), wp_json_encode( $verify_data ) );
if ( ! $is_verified ) {
	echo "  [NOTE] this site could not be reached by the service (expected on a firewalled or offline install); the coupon path is asserted as 'pending' below\n";
}

echo "\n[5b] Forged, replayed and malformed reports\n";

efb_clear_limits( $created_site_ids );

$good_payload = array(
	'reason'     => 'bug',
	'details'    => 'The multi step form loses uploaded files when I go back to step 1 and forward again.',
	'email'      => 'reporter@example.com',
	'contact_ok' => 1,
	'hp'         => '',
	'env'        => $client->collect_env_efb(),
);

$forged = efb_signed_report_request( $site_id, 'the-wrong-secret', $good_payload );
efb_t( 'a report signed with the wrong secret is refused', 401 === rest_do_request( $forged )->get_status() );

$tampered_body = wp_json_encode( array_merge( $good_payload, array( 'details' => 'tampered in flight' ) ) );
$tampered      = efb_signed_report_request( $site_id, $secret, $good_payload, array( 'body' => $tampered_body ) );
efb_t( 'a report whose body was changed after signing is refused', 401 === rest_do_request( $tampered )->get_status() );

$stale = efb_signed_report_request( $site_id, $secret, $good_payload, array( 'timestamp' => time() - 3600 ) );
efb_t( 'a report with an hour old timestamp is refused', 401 === rest_do_request( $stale )->get_status() );

$unknown = efb_signed_report_request( str_repeat( 'f', 32 ), $secret, $good_payload );
efb_t( 'a report from an unregistered site is refused', 401 === rest_do_request( $unknown )->get_status() );

$malformed = efb_signed_report_request( 'not-a-site-id', $secret, $good_payload, array( 'site_header' => 'not-a-site-id' ) );
efb_t( 'a malformed site header is refused', 401 === rest_do_request( $malformed )->get_status() );

$oversized_payload = array_merge( $good_payload, array( 'details' => str_repeat( 'a', 20000 ) ) );
$oversized         = efb_signed_report_request( $site_id, $secret, $oversized_payload );
efb_t( 'an oversized body is refused before it is parsed', 413 === rest_do_request( $oversized )->get_status() );

$honeypot = efb_signed_report_request( $site_id, $secret, array_merge( $good_payload, array( 'hp' => 'http://spam.tld' ) ) );
efb_t( 'a filled honeypot is refused', 400 === rest_do_request( $honeypot )->get_status() );

$bad_reason = efb_signed_report_request( $site_id, $secret, array_merge( $good_payload, array( 'reason' => 'made_up_reason' ) ) );
efb_t( 'an unknown reason is refused', 400 === rest_do_request( $bad_reason )->get_status() );

$no_details = efb_signed_report_request( $site_id, $secret, array_merge( $good_payload, array( 'details' => '' ) ) );
efb_t( 'a bug report with no description is refused', 400 === rest_do_request( $no_details )->get_status() );

echo "\n[5c] A genuine bug report\n";

efb_clear_limits( $created_site_ids );
$sent_mail = array();

$accepted_request = efb_signed_report_request( $site_id, $secret, $good_payload );
$accepted_nonce   = $accepted_request->get_header( 'x_wsf_nonce' );
$accepted         = rest_do_request( $accepted_request );
$accepted_data    = $accepted->get_data();

efb_t( 'a genuine bug report is accepted', 201 === $accepted->get_status(), wp_json_encode( $accepted_data ) );

$report_id = isset( $accepted_data['report_id'] ) ? (int) $accepted_data['report_id'] : 0;
$row       = $report_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_reports} WHERE id = %d", $report_id ), ARRAY_A ) : null;

efb_t( 'the report is stored', is_array( $row ) );
if ( is_array( $row ) ) {
	efb_t( 'the stored reason is the one that was sent', 'bug' === $row['reason'] );
	efb_t( 'the stored message is the one that was written', false !== strpos( $row['details'], 'loses uploaded files' ) );
	efb_t( 'the reporter address is stored only because consent was given', 'reporter@example.com' === $row['email'] && 1 === (int) $row['contact_ok'] );
	efb_t( 'the raw IP address is never stored', 64 === strlen( $row['ip_hash'] ) && false === strpos( $row['ip_hash'], '.' ) );
	efb_t( 'the report is not filed as spam', 'spam' !== $row['status'] );
}

$coupon_state = isset( $accepted_data['coupon']['state'] ) ? $accepted_data['coupon']['state'] : '';
$coupon_code  = isset( $accepted_data['coupon']['code'] ) ? $accepted_data['coupon']['code'] : '';

if ( $is_verified ) {
	efb_t( 'a verified site gets its coupon immediately', 'issued' === $coupon_state, $coupon_state );
	efb_t( 'the coupon code has the expected shape', (bool) preg_match( '/^EFBBUG-[A-Z2-9]{10}$/', (string) $coupon_code ), $coupon_code );

	$coupon_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_coupons} WHERE code = %s", $coupon_code ), ARRAY_A );
	efb_t( 'the coupon is recorded once, single use, at 100%', is_array( $coupon_row ) && 100 === (int) $coupon_row['percent'] && 'issued' === $coupon_row['state'] );
	efb_t( 'the coupon expires', is_array( $coupon_row ) && ! empty( $coupon_row['expires_at'] ) );

	$coupon_mail = array_filter(
		$sent_mail,
		function ( $mail ) use ( $coupon_code ) {
			return isset( $mail['message'] ) && false !== strpos( $mail['message'], $coupon_code );
		}
	);
	efb_t( 'the coupon is emailed to the reporter', ! empty( $coupon_mail ) );
} else {
	efb_t( 'an unverified site is promised the coupon rather than handed one', 'pending' === $coupon_state, $coupon_state );
	efb_t( 'no code is leaked to an unverified site', '' === $coupon_code );
}

efb_t( 'the team is notified', count( $sent_mail ) >= 1 );

echo "\n[5d] Replay and duplicate handling\n";

efb_clear_limits( $created_site_ids );

$replay = efb_signed_report_request( $site_id, $secret, $good_payload, array( 'nonce' => $accepted_nonce ) );
efb_t( 'replaying a captured request is refused', 401 === rest_do_request( $replay )->get_status() );

efb_clear_limits( $created_site_ids );
$sent_mail = array();

$retry      = efb_signed_report_request( $site_id, $secret, $good_payload );
$retry_data = rest_do_request( $retry )->get_data();

efb_t( 'an honest retry is recognised as the same report', ! empty( $retry_data['duplicate'] ) && (int) $retry_data['report_id'] === $report_id, wp_json_encode( $retry_data ) );

$report_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_reports} WHERE site_id = %s", $site_id ) );
efb_t( 'a retry does not create a second report', 1 === $report_count, "rows: {$report_count}" );

$coupon_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_coupons} WHERE site_id = %s", $site_id ) );
efb_t( 'a retry does not mint a second coupon', $coupon_count <= 1, "coupons: {$coupon_count}" );
efb_t( 'a retry sends no second email', empty( $sent_mail ) );

echo "\n[5e] Volume limits\n";

efb_clear_limits( $created_site_ids );

$statuses = array();
for ( $i = 0; $i < 5; $i++ ) {
	$payload    = array_merge( $good_payload, array( 'details' => 'Distinct report number ' . $i . ' about a broken upload field.' ) );
	$statuses[] = rest_do_request( efb_signed_report_request( $site_id, $secret, $payload ) )->get_status();
}
efb_t( 'a site cannot flood the endpoint', in_array( 429, $statuses, true ), implode( ',', $statuses ) );

/* ---------------------------------------------------------------------------
 * 6. Coupon rules
 * ------------------------------------------------------------------------ */

echo "\n[6] Coupon rules\n";

$non_bug = WS_EFB_Feedback_Coupons::maybe_issue(
	array(
		'report_id' => 0,
		'site_id'   => $site_id,
		'reason'    => 'no_longer_needed',
		'email'     => 'x@example.com',
		'verified'  => true,
		'is_spam'   => false,
	)
);
efb_t( 'only bug reports earn a coupon', 'none' === $non_bug['state'], $non_bug['state'] );

$spam_bug = WS_EFB_Feedback_Coupons::maybe_issue(
	array(
		'report_id' => 0,
		'site_id'   => $site_id,
		'reason'    => 'bug',
		'email'     => 'x@example.com',
		'verified'  => true,
		'is_spam'   => true,
	)
);
efb_t( 'a spam report earns nothing', 'none' === $spam_bug['state'], $spam_bug['state'] );

$unverified_bug = WS_EFB_Feedback_Coupons::maybe_issue(
	array(
		'report_id' => 0,
		'site_id'   => $site_id,
		'reason'    => 'bug',
		'email'     => 'x@example.com',
		'verified'  => false,
		'is_spam'   => false,
	)
);
efb_t( 'an unverified bug report waits for a human', 'pending' === $unverified_bug['state'], $unverified_bug['state'] );

$codes = array();
for ( $i = 0; $i < 25; $i++ ) {
	$codes[] = WS_EFB_Feedback_Coupons::generate_code();
}
efb_t( 'generated codes are unique', count( array_unique( $codes ) ) === count( $codes ) );
efb_t( 'generated codes avoid look-alike characters', ! preg_match( '/[O0I1]/', implode( '', array_map( function ( $c ) { return substr( $c, 7 ); }, $codes ) ) ) );

remove_filter( 'pre_wp_mail', $mail_trap, 10 );

/* ---------------------------------------------------------------------------
 * 7. The client half, over real HTTP
 * ------------------------------------------------------------------------ */

echo "\n[7] Client AJAX handler over HTTP\n";

/**
 * Exposes the protected transport so the whole client path can be exercised.
 */
class EFB_Feedback_Test_Client extends \Emsfb\Deactivation_Feedback {
	public function test_send_efb( $payload ) {
		return $this->send_report_efb( $payload );
	}
}

$admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin_id = ! empty( $admins ) ? (int) $admins[0] : 0;

if ( ! $admin_id ) {
	echo "  [NOTE] no administrator account on this site, skipping the AJAX phase\n";
} else {
	$previous_user = get_current_user_id();
	wp_set_current_user( $admin_id );

	// Talk to this very site, where the service plugin is now active.
	$local_endpoint = function () {
		return array( untrailingslashit( home_url() ) );
	};
	add_filter( 'emsfb_feedback_endpoints_efb', $local_endpoint );

	efb_clear_limits( $created_site_ids );

	// Outside a real admin-ajax request wp_send_json() ends in a bare die(),
	// which would take the whole test process with it. Pretending to be AJAX
	// routes it through wp_die() instead, where the handler below can intercept.
	add_filter( 'wp_doing_ajax', '__return_true' );

	// wp_send_json_* ends in wp_die(); turn that into an exception so the test
	// can read the JSON and carry on.
	if ( ! class_exists( 'EFB_Ajax_Exit' ) ) {
		class EFB_Ajax_Exit extends Exception {}
	}
	$die_handler = function () {
		return function () {
			throw new EFB_Ajax_Exit();
		};
	};
	add_filter( 'wp_die_ajax_handler', $die_handler );
	add_filter( 'wp_die_handler', $die_handler );

	/**
	 * Run the AJAX handler and return its decoded JSON.
	 */
	$call_ajax = function ( $post ) {
		$client = new EFB_Feedback_Test_Client();
		$_POST  = $post;
		$_REQUEST = $post;

		ob_start();
		try {
			$client->ajax_submit_efb();
		} catch ( EFB_Ajax_Exit $e ) {
			// expected
		}
		$output = ob_get_clean();

		$_POST    = array();
		$_REQUEST = array();

		return json_decode( $output, true );
	};

	$bad_nonce = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => 'not-the-nonce',
			'reason'  => 'bug',
			'details' => 'something broke',
		)
	);
	efb_t( 'the AJAX handler refuses a bad nonce', isset( $bad_nonce['success'] ) && false === $bad_nonce['success'], wp_json_encode( $bad_nonce ) );

	$nonce = wp_create_nonce( \Emsfb\Deactivation_Feedback::ACTION );

	$missing_details = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => $nonce,
			'reason'  => 'bug',
			'details' => '   ',
		)
	);
	efb_t(
		'the AJAX handler asks for a description before sending a bug report',
		isset( $missing_details['data']['ok'] ) && false === $missing_details['data']['ok'],
		wp_json_encode( $missing_details )
	);

	$too_short = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => $nonce,
			'reason'  => 'bug',
			'details' => 'broken',
		)
	);
	efb_t(
		'the AJAX handler asks for a real sentence before a bug report is sent',
		isset( $too_short['data']['ok'] ) && false === $too_short['data']['ok'],
		wp_json_encode( $too_short )
	);

	$honeypot_hit = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => $nonce,
			'reason'  => 'bug',
			'details' => 'A real looking description of a real looking problem.',
			'hp'      => 'spam',
		)
	);
	efb_t(
		'the AJAX handler drops a filled honeypot',
		isset( $honeypot_hit['data']['ok'] ) && false === $honeypot_hit['data']['ok'],
		wp_json_encode( $honeypot_hit )
	);

	efb_clear_limits( $created_site_ids );
	delete_option( \Emsfb\Deactivation_Feedback::OPTION_STATE );

	$real = $call_ajax(
		array(
			'action'     => 'emsfb_deactivation_feedback',
			'nonce'      => $nonce,
			'reason'     => 'hard_to_use',
			'details'    => 'The step editor was confusing: I could not tell which step a condition belonged to.',
			'email'      => 'reporter2@example.com',
			'contact_ok' => '1',
		)
	);

	$http_ok = isset( $real['data']['ok'] ) && true === $real['data']['ok'];
	efb_t( 'a real submission travels client to service over HTTP', $http_ok, wp_json_encode( $real ) );

	if ( $http_ok ) {
		$stored = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_reports} WHERE site_id = %s AND reason = %s ORDER BY id DESC", $site_id, 'hard_to_use' ), ARRAY_A );
		efb_t( 'the HTTP submission is stored on the service side', is_array( $stored ) );
		efb_t( 'a non-bug report gets no coupon', is_array( $stored ) && 'none' === $stored['coupon_state'], is_array( $stored ) ? $stored['coupon_state'] : '' );
	}

	// A site whose identity the service no longer honours (records restored from
	// a backup, master key rotated) must recover on its own rather than being
	// unable to report anything ever again.
	$before_identity = get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
	$broken_identity = is_array( $before_identity ) ? $before_identity : array();
	$broken_identity['secret'] = 'no-longer-the-right-secret';
	update_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY, $broken_identity, false );

	efb_clear_limits( $created_site_ids );
	delete_option( \Emsfb\Deactivation_Feedback::OPTION_STATE );

	$recovered = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => $nonce,
			'reason'  => 'found_better',
			'details' => 'I switched to another plugin because of the import options.',
		)
	);
	$after_identity = get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );

	efb_t(
		'a rejected identity is replaced and the report still lands',
		isset( $recovered['data']['ok'] ) && true === $recovered['data']['ok'],
		wp_json_encode( $recovered )
	);
	efb_t(
		'the replacement identity is a new one',
		is_array( $after_identity ) && ! empty( $after_identity['site_id'] ) && $after_identity['site_id'] !== $broken_identity['site_id'],
		is_array( $after_identity ) ? (string) $after_identity['site_id'] : 'none'
	);

	if ( is_array( $after_identity ) && ! empty( $after_identity['site_id'] ) ) {
		$created_site_ids[] = $after_identity['site_id'];
	}

	$blocked = $call_ajax(
		array(
			'action'  => 'emsfb_deactivation_feedback',
			'nonce'   => $nonce,
			'reason'  => 'made_up',
			'details' => 'x',
		)
	);
	efb_t(
		'the AJAX handler refuses a reason it does not offer',
		isset( $blocked['data']['ok'] ) && false === $blocked['data']['ok'],
		wp_json_encode( $blocked )
	);

	remove_filter( 'wp_die_ajax_handler', $die_handler );
	remove_filter( 'wp_die_handler', $die_handler );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'emsfb_feedback_endpoints_efb', $local_endpoint );
	wp_set_current_user( $previous_user );
}

/* ---------------------------------------------------------------------------
 * 8. Ownership proof endpoint
 * ------------------------------------------------------------------------ */

echo "\n[8] Ownership proof endpoint\n";

// The identity in the option is what the site will answer for, and phase 7 may
// have replaced it, so it is read fresh rather than assumed.
$live_identity  = get_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
$live_site_id   = is_array( $live_identity ) && ! empty( $live_identity['site_id'] ) ? $live_identity['site_id'] : $site_id;
$live_challenge = is_array( $live_identity ) && ! empty( $live_identity['challenge'] ) ? $live_identity['challenge'] : $challenge;

$proof_url = add_query_arg( 'ws_efb_verify', $live_site_id, home_url( '/' ) );
$proof     = wp_remote_get( $proof_url, array( 'timeout' => 10 ) );

if ( is_wp_error( $proof ) ) {
	echo '  [NOTE] the site could not fetch itself: ' . $proof->get_error_message() . "\n";
} else {
	$body     = trim( (string) wp_remote_retrieve_body( $proof ) );
	$expected = WS_EFB_Feedback_Security::challenge_proof( $live_site_id, $live_challenge );
	efb_t( 'the site answers the challenge with the expected proof', $body === $expected, substr( $body, 0, 120 ) );
	efb_t( 'the raw challenge is never published', false === strpos( $body, $live_challenge ) );

	$wrong = wp_remote_get( add_query_arg( 'ws_efb_verify', str_repeat( 'd', 32 ), home_url( '/' ) ), array( 'timeout' => 10 ) );
	$wrong_body = is_wp_error( $wrong ) ? '' : trim( (string) wp_remote_retrieve_body( $wrong ) );
	efb_t( 'a stranger asking for another id gets no proof', $wrong_body !== $expected && false === strpos( $wrong_body, $expected ) );
}

/* ---------------------------------------------------------------------------
 * 9. Teardown
 * ------------------------------------------------------------------------ */

echo "\n[9] Teardown\n";

$wpdb->query( $wpdb->prepare( "DELETE FROM {$t_reports} WHERE id > %d", $base_report ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$t_coupons} WHERE id > %d", $base_coupon ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$t_sites} WHERE id > %d", $base_site ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$t_events} WHERE id > %d", $base_event ) );

foreach ( $created_site_ids as $created ) {
	$wpdb->delete( $t_nonces, array( 'site_id' => $created ), array( '%s' ) );
}
$wpdb->delete( $t_nonces, array( 'site_id' => 'test' ), array( '%s' ) );
efb_clear_limits( $created_site_ids );

if ( false === $prev_identity ) {
	delete_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY );
} else {
	update_option( \Emsfb\Deactivation_Feedback::OPTION_IDENTITY, $prev_identity, false );
}

if ( false === $prev_state ) {
	delete_option( \Emsfb\Deactivation_Feedback::OPTION_STATE );
} else {
	update_option( \Emsfb\Deactivation_Feedback::OPTION_STATE, $prev_state, false );
}

delete_transient( 'emsfb_feedback_register_backoff' );

if ( ! $service_was_active ) {
	deactivate_plugins( $service_basename, true );
}

$left_behind = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_reports} WHERE id > %d", $base_report ) );
efb_t( 'the test left no reports behind', 0 === $left_behind );
efb_t( 'the service plugin is back in its original state', is_plugin_active( $service_basename ) === $service_was_active );

echo "\n=== {$pass} passed, {$fail} failed ===\n";

if ( $failures ) {
	echo "\nFailures:\n";
	foreach ( $failures as $line ) {
		echo "  - {$line}\n";
	}
}

exit( $fail > 0 ? 1 : 0 );
