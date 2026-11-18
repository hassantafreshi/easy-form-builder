<?php
/**
 * Site control for the PAY-OUT tests (tests/test-payout-*-http.php, e2e scenario "payout"): the legacy gateway
 * fields of Stripe, PayPal and Persia Pay on the e2e site, whichever core or add-on version is in place.
 *
 *   wp eval-file tests/e2e-wp/ctl-payout.php <command> [args]
 *
 *   mu                         copy mu-plugins/efb-e2e-payout.php (the Stripe SDK stand-in) into the site
 *   fake <0|1>                 the stand-in on / off; its state reset
 *   mode <status> [pi_id]      the status the stand-in reports (succeeded, requires_payment_method, processing, canceled),
 *                              for every intent or one
 *   stripe                     the stand-in's intents and request log
 *   legacy-form <type> [name] [k=v&…]   a payment form with a legacy gateway field (stripe | paypal | persiaPay), an
 *                              e-mail and a priced choice (Adult 25 / Child 12.5; for persiaPay 250000 / 125000),
 *                              saved through the builder's sanitizer; row-0 overrides as k=v&…
 *   keys <old|clear>           placeholder keys (built here, never a real key) in the settings row the way core's
 *                              Settings › Payments tab saved them (stripePKey, stripeSKey, paypalPKey, paypalSKey,
 *                              payToken); clear removes them there. Both remove the add-ons' own keys (pay_*) and
 *                              their "moved" marks first
 *   key-value <addon> <key> <expected>  whether an add-on's key resolves to <expected> (never prints a key)
 *   where                      where the keys are now: the old row keys (set or not), the add-ons' own keys
 *                              (pay_stripe, pay_paypal, pay_persiapay: public values, secrets as encrypted or not),
 *                              never a secret's value
 *   pay-rows [form_id]         rows of the emsfb_pay_ table (the Stripe / PayPal payments list); table: exists or not
 *   pay-table [reset]          whether that table exists, its rows, its version option and the pending count the Stripe
 *                              add-on published; reset drops it first (a site that never opened the payments page)
 *   versions                   core version, the legacy_gateway contract (version, min, providers) and each legacy add-on's state
 *   gw-fake <0|1>              (step 5) the fake Stripe / PayPal / ZarinPal APIs of the add-ons' framework gateways
 *                              (tests/payout-fake-providers.php, copied by "mu") on / off; their state reset
 *   gw-last <stripe|paypal|zarinpal>   the fake's newest payment there (amount, status, return addresses)
 *   rate-reset                 the payment routes' minute counters cleared (the tests' requests all come from 127.0.0.1)
 *   dev-mode <0|1>             Easy Form Builder's Development Mode (the PayPal and ZarinPal sandboxes)
 *   gw-set <gw> <id> <k=v&…>   a fake payment's state (e.g. zarinpal <authority> status=REVERSED)
 *   gw-down <gw> <0|1>         the fake answers 503 for that provider
 *   gw-log                     the fake's request log (method, path, idempotency key; no body)
 *   pgw <slug>                 a framework gateway: registered, mode, configured per mode (never a key)
 *   migration [reset]          (step 5) Payments\Legacy_Migration::status(); reset forgets the log, the backups and the
 *                              three gateways' settings first
 *   migration-run              one batch now (as the cron event)
 *   readiness <form_id>        the payment readiness of a stored form (codes, the owner's words)
 *   form-json <form_id>        the form's types (row 0 getway, currency, the gateway field) and the sha256 of its stored text
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

global $wpdb;
$efb_po_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_po_cmd  = isset( $efb_po_args[0] ) ? $efb_po_args[0] : 'versions';
$efb_po_rest = array_slice( $efb_po_args, 1 );

function efb_po_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

/** The settings row as an array (read from the table itself). */
function efb_po_row() {
	global $wpdb;
	$raw = (string) $wpdb->get_var( 'SELECT setting FROM `' . $wpdb->prefix . 'emsfb_setting` ORDER BY id DESC LIMIT 1' ); // phpcs:ignore
	$d = function_exists( 'emsfb_json_decode_efb' ) ? emsfb_json_decode_efb( $raw, true ) : json_decode( $raw, true );
	return is_array( $d ) ? $d : array();
}

function efb_po_write( array $patch ) {
	$fn = get_efbFunction();
	$s = get_setting_Emsfb( 'decoded' );
	return (bool) $fn->set_setting_Emsfb( (object) $patch, is_object( $s ) && isset( $s->emailSupporter ) ? (string) $s->emailSupporter : '' );
}

switch ( $efb_po_cmd ) {
	case 'mu':
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-payout.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = __DIR__ . '/mu-plugins/efb-e2e-payout.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		// Step 5: the fake provider APIs, in a sub-folder (not loaded by WordPress itself; the mu-plugin requires it).
		wp_mkdir_p( WPMU_PLUGIN_DIR . '/efb-e2e' );
		$fsrc = dirname( __DIR__ ) . '/payout-fake-providers.php';
		$fdst = WPMU_PLUGIN_DIR . '/efb-e2e/payout-fake-providers.php';
		$fsame = is_readable( $fdst ) && md5_file( $fdst ) === md5_file( $fsrc );
		efb_po_out( array( 'copied' => ( $same || copy( $src, $dst ) ) && ( $fsame || copy( $fsrc, $fdst ) ), 'changed' => ! $same || ! $fsame ) );
		break;

	case 'gw-fake':
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		if ( isset( $efb_po_rest[0] ) && '1' === $efb_po_rest[0] ) Efb_Test_Payout_Fake::reset(); else delete_option( Efb_Test_Payout_Fake::OPTION );
		efb_po_out( array( 'ok' => true ) );
		break;

	case 'gw-last':
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		$gw = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : 'stripe';
		$id = Efb_Test_Payout_Fake::last_id( $gw );
		$c = $id !== '' ? Efb_Test_Payout_Fake::get( $gw, $id ) : null;
		if ( is_array( $c ) ) unset( $c['request'] );
		efb_po_out( array( 'id' => $id, 'record' => $c, 'count' => count( Efb_Test_Payout_Fake::state()[ $gw ] ?? array() ) ) );
		break;

	case 'rate-reset':
		// The payment routes' per-IP / per-form minute counters (every request of these tests comes from 127.0.0.1).
		$n = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_efb_pmt_' ) . '%', $wpdb->esc_like( '_transient_timeout_efb_pmt_' ) . '%' ) ); // phpcs:ignore
		wp_cache_flush();
		efb_po_out( array( 'deleted' => (int) $n ) );
		break;

	case 'dev-mode':
		// Easy Form Builder's Development Mode (PayPal and ZarinPal: their sandboxes).
		update_option( 'emsfb_dev_mode', isset( $efb_po_rest[0] ) && '1' === $efb_po_rest[0] ? '1' : '0' );
		efb_po_out( array( 'dev_mode' => get_option( 'emsfb_dev_mode' ) ) );
		break;

	case 'gw-set':
		// gw-set <gw> <id> k=v&… : a fake payment's state (e.g. zarinpal … status=REVERSED).
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		parse_str( (string) ( $efb_po_rest[2] ?? '' ), $kv );
		efb_po_out( array( 'ok' => Efb_Test_Payout_Fake::update( (string) ( $efb_po_rest[0] ?? '' ), (string) ( $efb_po_rest[1] ?? '' ), (array) $kv ) ) );
		break;

	case 'gw-down':
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		$st = Efb_Test_Payout_Fake::state();
		if ( isset( $efb_po_rest[1] ) && '1' === $efb_po_rest[1] ) $st['down'][ $efb_po_rest[0] ] = 1; else unset( $st['down'][ $efb_po_rest[0] ?? '' ] );
		Efb_Test_Payout_Fake::save( $st );
		efb_po_out( array( 'down' => $st['down'] ) );
		break;

	case 'gw-log':
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		efb_po_out( array_map( function ( $l ) { return array( 'gw' => $l['gw'], 'method' => $l['method'], 'path' => $l['path'], 'idem' => $l['idem'] !== '' ); }, Efb_Test_Payout_Fake::state()['log'] ) );
		break;

	case 'pgw':
		$slug = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : '';
		$g = class_exists( '\Emsfb\Payments\Registry' ) ? \Emsfb\Payments\Registry::get( $slug ) : null;
		$s = $g ? \Emsfb\Payments\Settings::load( $slug ) : null;
		efb_po_out( array( 'registered' => (bool) $g, 'addon' => $g ? $g->addon_key() : '', 'mode' => $s ? $s->mode() : '', 'configured' => $g ? array( 'test' => $g->is_configured( $s, 'test' ), 'live' => $g->is_configured( $s, 'live' ) ) : null ) );
		break;

	case 'migration':
		if ( ! class_exists( '\Emsfb\Payments\Legacy_Migration' ) ) efb_po_out( array( 'error' => 'no migration in this core' ) );
		if ( isset( $efb_po_rest[0] ) && 'reset' === $efb_po_rest[0] ) {
			delete_option( \Emsfb\Payments\Legacy_Migration::LOG );
			delete_transient( \Emsfb\Payments\Legacy_Migration::LOCK );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( \Emsfb\Payments\Legacy_Migration::BAK ) . '%' ) ); // phpcs:ignore
			foreach ( array( 'stripecheckout', 'paypalcheckout', 'zarinpalgate' ) as $sl ) delete_option( \Emsfb\Payments\Settings::option_name( $sl ) );
			wp_clear_scheduled_hook( \Emsfb\Payments\Legacy_Migration::HOOK );
			wp_cache_flush();
		}
		efb_po_out( \Emsfb\Payments\Legacy_Migration::status() );
		break;

	case 'migration-run':
		if ( ! class_exists( '\Emsfb\Payments\Legacy_Migration' ) ) efb_po_out( array( 'error' => 'no migration in this core' ) );
		efb_po_out( \Emsfb\Payments\Legacy_Migration::run_batch( 20, 'cron' ) );
		break;

	case 'readiness':
		// The payment readiness of a stored form (codes and the owner's words).
		$r = class_exists( '\\Emsfb\\Payments\\Readiness' ) ? \Emsfb\Payments\Readiness::for_form_id( (int) ( $efb_po_rest[0] ?? 0 ) ) : null;
		efb_po_out( is_array( $r ) ? array( 'ready' => $r['ready'], 'issues' => array_map( function ( $i ) { return array( 'code' => $i['code'], 'm' => $i['m'], 'addon' => $i['addon'] ?? '' ); }, $r['issues'] ) ) : null );
		break;

	case 'form-json':
		$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", isset( $efb_po_rest[0] ) ? (int) $efb_po_rest[0] : 0 ) ); // phpcs:ignore
		$rows = json_decode( str_replace( '\\"', '"', $raw ), true );
		$types = array();
		foreach ( (array) $rows as $i => $r ) if ( $i > 0 && is_array( $r ) && isset( $r['type'] ) ) $types[] = $r['type'];
		efb_po_out( array( 'sha' => hash( 'sha256', $raw ), 'getway' => $rows[0]['getway'] ?? null, 'currency' => $rows[0]['currency'] ?? null, 'type0' => $rows[0]['type'] ?? null, 'types' => $types,
			'form_type' => $wpdb->get_var( $wpdb->prepare( "SELECT form_type FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", (int) ( $efb_po_rest[0] ?? 0 ) ) ) ) ); // phpcs:ignore
		break;

	case 'fake':
		update_option( 'efb_e2e_payout_fake', isset( $efb_po_rest[0] ) && '1' === $efb_po_rest[0] ? 1 : 0, false );
		update_option( 'efb_e2e_payout_stripe', array( 'intents' => array(), 'mode' => 'succeeded', 'log' => array() ), false );
		efb_po_out( array( 'ok' => true ) );
		break;

	case 'mode':
		$s = get_option( 'efb_e2e_payout_stripe', array() );
		$s = is_array( $s ) ? $s : array();
		if ( isset( $efb_po_rest[1] ) && isset( $s['intents'][ $efb_po_rest[1] ] ) ) $s['intents'][ $efb_po_rest[1] ]['mode'] = $efb_po_rest[0];
		else $s['mode'] = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : 'succeeded';
		update_option( 'efb_e2e_payout_stripe', $s, false );
		efb_po_out( array( 'ok' => true ) );
		break;

	case 'stripe':
		$s = get_option( 'efb_e2e_payout_stripe', array() );
		efb_po_out( is_array( $s ) ? $s : array() );
		break;

	case 'legacy-form':
		$type = isset( $efb_po_rest[0] ) && in_array( $efb_po_rest[0], array( 'stripe', 'paypal', 'persiaPay' ), true ) ? $efb_po_rest[0] : 'stripe';
		$name = isset( $efb_po_rest[1] ) && '' !== $efb_po_rest[1] ? $efb_po_rest[1] : 'Legacy ' . $type;
		$rid  = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $pf = $rid(); $rad = $rid(); $o1 = $rid(); $o2 = $rid();
		$persia = 'persiaPay' === $type;
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => 'payment', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_state' => 'single', 'show_pro_bar' => '1', 'font' => '1', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '0', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'getway' => $type, 'currency' => $persia ? 'irr' : 'usd', 'paymentmethod' => 'charge', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		if ( $persia ) $row0['persiaPay'] = 'zarinPal';
		$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $rad, 'dataId' => $rad . '-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'name' => 'Ticket', 'amount' => 3, 'required' => 1 ) );
		$rows[] = array( 'id_' => $o1, 'dataId' => $o1 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Adult', 'id_op' => $o1, 'step' => 1, 'amount' => 4, 'price' => $persia ? '250000' : '25' );
		$rows[] = array( 'id_' => $o2, 'dataId' => $o2 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Child', 'id_op' => $o2, 'step' => 1, 'amount' => 4, 'price' => $persia ? '125000' : '12.5' );
		$rows[] = array_merge( $base, array( 'id_' => $pf, 'dataId' => $pf . '-id', 'type' => $type, 'elementId' => $type, 'name' => ucfirst( $type ), 'amount' => 5 ) );
		if ( isset( $efb_po_rest[2] ) ) {
			foreach ( explode( '&', $efb_po_rest[2] ) as $kv ) {
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$rows[0][ $k ] = urldecode( $v );
			}
		}
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'payment', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		efb_po_out( array( 'form_id' => (int) $wpdb->insert_id, 'email' => $em, 'pf' => $pf, 'radio' => $rad, 'o1' => $o1, 'o2' => $o2 ) );
		break;

	case 'keys':
		$which = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : 'old';
		// Shapes of the providers' keys, built here: never a real key.
		// PayPal and ZarinPal: the fake providers' own placeholders (step 5 moves these keys to the framework gateways,
		// whose fake APIs accept only them).
		if ( ! class_exists( 'Efb_Test_Payout_Fake', false ) ) require_once dirname( __DIR__ ) . '/payout-fake-providers.php';
		$k = array( 'stripePKey' => 'pk_test_' . str_repeat( 'EfbE2e', 4 ), 'stripeSKey' => 'sk_test_' . str_repeat( 'EfbE2e', 4 ), 'paypalPKey' => Efb_Test_Payout_Fake::paypal_id(), 'paypalSKey' => Efb_Test_Payout_Fake::paypal_secret(), 'payToken' => Efb_Test_Payout_Fake::zarinpal_merchant() );
		if ( 'clear' === $which ) $k = array_fill_keys( array_keys( $k ), null );
		// Every run starts from core's old layout: the add-ons' own keys and their "moved" marks are removed first.
		$reset = efb_po_write( array( 'pay_stripe' => null, 'pay_paypal' => null, 'pay_persiapay' => null ) );
		foreach ( array( 'stripe', 'paypal', 'persiapay' ) as $t ) delete_option( 'emsfb_pay_' . $t . '_moved' );
		efb_po_out( array( 'ok' => $reset && efb_po_write( $k ), 'stripe_secret_tail' => substr( (string) ( $k['stripeSKey'] ?? '' ), -4 ) ) );
		break;

	case 'where':
		$row = efb_po_row();
		$out = array( 'old' => array(), 'own' => array() );
		foreach ( array( 'stripePKey', 'stripeSKey', 'paypalPKey', 'paypalSKey', 'payToken' ) as $k ) $out['old'][ $k ] = isset( $row[ $k ] ) && is_string( $row[ $k ] ) && strlen( $row[ $k ] ) > 5 && 'null' !== $row[ $k ];
		foreach ( array( 'pay_stripe', 'pay_paypal', 'pay_persiapay' ) as $k ) {
			if ( ! isset( $row[ $k ] ) || ! is_array( $row[ $k ] ) ) { $out['own'][ $k ] = null; continue; }
			$sec = array();
			foreach ( (array) ( $row[ $k ]['secret'] ?? array() ) as $sk => $sv ) $sec[ $sk ] = is_string( $sv ) && 0 === strpos( $sv, 'enc:v1:' ) ? 'encrypted' : 'plain';
			$out['own'][ $k ] = array( 'public' => (array) ( $row[ $k ]['public'] ?? array() ), 'secret' => $sec, 'v' => $row[ $k ]['v'] ?? null );
		}
		$out['moved'] = array();
		foreach ( array( 'stripe', 'paypal', 'persiapay' ) as $t ) { $mv = get_option( 'emsfb_pay_' . $t . '_moved', null ); $out['moved'][ $t ] = null === $mv ? null : (string) $mv; }
		efb_po_out( $out );
		break;

	case 'pay-rows':
		$t = $wpdb->prefix . 'emsfb_pay_';
		$has = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
		$fid = isset( $efb_po_rest[0] ) ? (int) $efb_po_rest[0] : 0;
		$rows = ! $has ? array() : $wpdb->get_results( $fid ? $wpdb->prepare( "SELECT * FROM `$t` WHERE form_id = %d ORDER BY id", $fid ) : "SELECT * FROM `$t` ORDER BY id", ARRAY_A ); // phpcs:ignore
		efb_po_out( array( 'table' => $has, 'rows' => $rows ) );
		break;

	case 'builder':
		// What the form builder gets: efb_var.legacy_gw (core 1.1+) and the add-ons' builder scripts.
		wp_set_current_user( 1 );
		$vars = apply_filters( 'efb_admin_localize_vars', array() );
		do_action( 'efb_admin_enqueue_builder', 'create' );
		$js = array();
		foreach ( array( 'stripe' => 'efb-stripe-builder-js', 'paypal' => 'efb-paypal-builder-js', 'persiapay' => 'efb-persiapay-builder-js' ) as $a => $h ) $js[ $a ] = wp_script_is( $h, 'enqueued' );
		efb_po_out( array( 'legacy_gw' => $vars['legacy_gw'] ?? null, 'scripts' => $js ) );
		break;

	case 'key-value':
		// Which value a key of the add-on resolves to now (for the tests: compared with the placeholder they set, never printed).
		$cls = array( 'stripe' => '\\Emsfb\\Stripe_Keys_Efb', 'paypal' => '\\Emsfb\\Paypal_Keys_Efb', 'persiapay' => '\\Emsfb\\PersiaPay_Keys_Efb' );
		$c = isset( $efb_po_rest[0], $cls[ $efb_po_rest[0] ] ) ? $cls[ $efb_po_rest[0] ] : '';
		$want = isset( $efb_po_rest[2] ) ? (string) $efb_po_rest[2] : '';
		$got = $c && class_exists( $c ) ? $c::get( isset( $efb_po_rest[1] ) ? $efb_po_rest[1] : '' ) : null;
		efb_po_out( array( 'class' => $c && class_exists( $c ), 'same' => null !== $got && hash_equals( $want, (string) $got ), 'empty' => '' === (string) $got ) );
		break;

	case 'pay-table':
		// reset: the table and its version option gone (a site that never opened the payments page), or count its rows.
		$t = $wpdb->prefix . 'emsfb_pay_';
		if ( isset( $efb_po_rest[0] ) && 'reset' === $efb_po_rest[0] ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$t`" ); // phpcs:ignore -- test site only
			delete_option( 'emsfb_pay_table_version' );
		}
		$has = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
		efb_po_out( array( 'table' => $has, 'rows' => $has ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ) : 0, 'version' => get_option( 'emsfb_pay_table_version', null ), 'pending_published' => get_option( 'emsfb_addon_pending_work', null ) ) ); // phpcs:ignore
		break;

	case 'versions':
		$c = function_exists( 'efb_addon_contracts' ) ? efb_addon_contracts() : array();
		$states = array();
		if ( class_exists( '\Emsfb\Split' ) ) foreach ( array( 'AdnSPF', 'AdnPAP', 'AdnPPF' ) as $code ) $states[ $code ] = \Emsfb\Split::addon_state( $code );
		efb_po_out( array( 'core' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '', 'dir' => defined( 'EMSFB_PLUGIN_DIRECTORY' ) ? realpath( EMSFB_PLUGIN_DIRECTORY ) : '', 'legacy_gateway' => $c['legacy_gateway'] ?? null, 'states' => $states ) );
		break;

	default:
		efb_po_out( array( 'error' => 'unknown command ' . $efb_po_cmd ) );
}
