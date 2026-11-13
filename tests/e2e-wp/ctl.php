<?php
/**
 * Site control for the real-WordPress end-to-end suite (tests/e2e-wp/run.js).
 *
 * Runs inside WordPress through WP-CLI, so every read below is the real database (SQLite or
 * MySQL) through $wpdb, and every write goes through the WordPress API:
 *
 *   wp eval-file tests/e2e-wp/ctl.php <command> [args]
 *
 * Every command prints one JSON document on its last line.
 *
 *   wipe                      remove everything Easy Form Builder stored (tables incl. F03 emsfb_uniq_, options, caps,
 *                             transients) plus the pages and users this suite created. Run between
 *                             `wp plugin deactivate` and `wp plugin activate` = a fresh install.
 *   state                     plan option, package_type in the settings, onboarding flags, locale
 *   forms                     every form: id, name, type
 *   form <id>                 one form, form_structer decoded
 *   entries <form_id>         stored entries of a form, content decoded
 *   page <form_id> [block]    publish a page with [EMS_Form_Builder id=N] (or the block); prints the URL
 *   user <login> <role> [cap,cap]   create (or reset) a user, password = login + "-pass-123"
 *   locale <locale|''>        site language (WPLANG); '' = English (United States)
 *   restrict-plan <0|1>       with 1, the e2e mu-plugin filters efb_can_change_plan to false for
 *                             users without manage_options (what a site owner can do with the filter)
 *   plan <n>                  set emsfb_pro and settings package_type (for running one scenario alone)
 *   constants                 EMSFB_API_VERSION, EMSFB_PLUGIN_VERSION, database driver
 *   option <name>             raw option value
 *   setting <key>             one key of the plugin's decoded settings (F13 prv_*)
 *   sessions <form_id>        visit rows of a form (emsfb_stts_): ip, os, browser, status
 *   f13-seed <form_id> <days_ago> [reply_days_ago] [file 0|1] [email]
 *                             an entry dated <days_ago> (email + name + an uploaded file stored with
 *                             wp_upload_bits() in uploads/YYYY/MM like a real upload), optionally a
 *                             reply; prints msg_id, file path and URL
 *   replies <msg_id>          replies of an entry
 *   file-exists <path>        whether a file is on disk
 *   PAY00 (scenario 9):
 *   pay-mock <0|1>            load the test gateway efbmock (mu-plugin, option efb_e2e_payments_mock)
 *   pay-keys <test|live>      store the test gateway's keys (test placeholders) and the mode
 *   pay-form <kind> [name]    a payment form (kind: fixed | donation | computed) saved through the
 *                             builder's sanitizer; prints form id and field ids
 *   payments [form_id]        payment rows (newest first)
 *   pay-events <payment_id>   the event log of a payment
 *   pay-provider <ref> <status> [amount]   the fake provider's state of a payment
 *   pay-webhook-secret        the test webhook secret (to sign a webhook like the provider)
 *   mails [clear]             e-mails recorded by the mu-plugin (efb_e2e_capture_mail)
 *   reconcile                 run the reconcile job now
 *   addon-flag <key> <0|1>    switch an add-on in the settings (its files must be in vendor/)
 *   F02 (scenarios f02*): f02-mu (copy the mu-plugin), f02-wpsms <0|1> [ok|error|false] (the WP SMS
 *                             stand-in), f02-sms [clear] (messages it got), f02-settings [json] (verification
 *                             settings), f02-rows <form_id> [reset] (challenges; reset clears the F02 counters and every
 *                             challenge of earlier scenarios, i.e. the resend waits)
 *
 * Local test site only. Nothing here is meant for a production site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file tests/e2e-wp/ctl.php <command>\n" );
	exit( 1 );
}

$efb_e2e_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_e2e_cmd  = isset( $efb_e2e_args[0] ) ? $efb_e2e_args[0] : 'state';
$efb_e2e_rest = array_slice( $efb_e2e_args, 1 );

function efb_e2e_out( $data ) {
	echo "\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

function efb_e2e_decode_json( $s ) {
	if ( ! is_string( $s ) ) {
		return $s;
	}
	$d = json_decode( $s, true );
	if ( null === $d ) {
		// The plugin stores some JSON with escaped quotes; try the unescaped form as the plugin reads it.
		$d = json_decode( str_replace( '\\"', '"', $s ), true );
	}
	return null === $d ? $s : $d;
}

global $wpdb;

switch ( $efb_e2e_cmd ) {
	case 'wipe':
		$dropped = array();
		foreach ( array( 'emsfb_form', 'emsfb_msg_', 'emsfb_rsp_', 'emsfb_setting', 'emsfb_stts_', 'emsfb_pmt_', 'emsfb_pmt_evt_', 'emsfb_pmt_rfd_', 'emsfb_coupon_', 'emsfb_vrfy_rl_', 'emsfb_draft_', 'emsfb_uniq_' ) as $t ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$t}" ); // phpcs:ignore
			$dropped[] = $wpdb->prefix . $t;
		}
		$opts = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE LOWER(option_name) LIKE '%emsfb%' OR option_name LIKE 'efb\\_%' OR option_name LIKE '\\_transient%efb%' OR option_name LIKE '\\_site\\_transient%efb%'" );
		foreach ( $opts as $o ) {
			delete_option( $o );
		}
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( array_keys( $role->capabilities ) as $cap ) {
				if ( 0 === strpos( $cap, 'Emsfb' ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_efb_e2e', 'fields' => 'ids' ) );
		foreach ( $pages as $p ) {
			wp_delete_post( $p, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$users = get_users( array( 'search' => 'efb_e2e_*', 'search_columns' => array( 'user_login' ) ) );
		foreach ( $users as $u ) {
			wp_delete_user( $u->ID );
		}
		delete_user_meta( 1, 'locale' );
		update_option( 'WPLANG', '' );
		efb_e2e_out( array( 'ok' => true, 'tables' => $dropped, 'options' => count( $opts ), 'pages' => count( $pages ), 'users' => count( $users ) ) );
		break;

	case 'state':
		$settings = efb_e2e_decode_json( get_option( 'emsfb_settings' ) );
		efb_e2e_out( array(
			'emsfb_pro'          => get_option( 'emsfb_pro', null ),
			'package_type'       => is_array( $settings ) && isset( $settings['package_type'] ) ? $settings['package_type'] : null,
			'onboarding_pending' => get_option( 'emsfb_onboarding_pending', null ),
			'locale'             => get_locale(),
			'is_rtl'             => is_rtl(),
		) );
		break;

	case 'forms':
		$rows = $wpdb->get_results( "SELECT form_id, form_name, form_type FROM {$wpdb->prefix}emsfb_form ORDER BY form_id", ARRAY_A ); // phpcs:ignore
		efb_e2e_out( $rows ? $rows : array() );
		break;

	case 'form':
		$id  = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			efb_e2e_out( null );
		}
		$row['raw']           = $row['form_structer'];
		$row['form_structer'] = efb_e2e_decode_json( $row['form_structer'] );
		efb_e2e_out( $row );
		break;

	case 'entries':
		$id   = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$cols = 'msg_id, form_id, track, content, read_, ip, date';
		foreach ( array( 'uid', 'prv_state' ) as $c ) { // F13 columns (DB 1.4)
			if ( $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}emsfb_msg_ LIKE %s", $c ) ) ) { // phpcs:ignore
				$cols .= ', ' . $c;
			}
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$cols} FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id", $id ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $i => $r ) {
			$rows[ $i ]['raw']     = $r['content'];
			$rows[ $i ]['content'] = efb_e2e_decode_json( $r['content'] );
		}
		efb_e2e_out( $rows ? $rows : array() );
		break;

	case 'page':
		$id    = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$block = isset( $efb_e2e_rest[1] ) && 'block' === $efb_e2e_rest[1];
		$content = $block
			? '<!-- wp:easy-form-builder/form {"formId":"' . $id . '"} /-->'
			: '<!-- wp:shortcode -->[EMS_Form_Builder id=' . $id . ']<!-- /wp:shortcode -->';
		$post_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'EFB e2e ' . ( $block ? 'block' : 'shortcode' ) . ' ' . $id,
			'post_content' => $content,
			'meta_input'   => array( '_efb_e2e' => 1 ),
		) );
		efb_e2e_out( array( 'id' => $post_id, 'url' => get_permalink( $post_id ) ) );
		break;

	case 'user':
		$login = isset( $efb_e2e_rest[0] ) ? sanitize_user( $efb_e2e_rest[0] ) : 'efb_e2e_editor';
		$role  = isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : 'editor';
		$caps  = isset( $efb_e2e_rest[2] ) && '' !== $efb_e2e_rest[2] ? explode( ',', $efb_e2e_rest[2] ) : array();
		$uid   = username_exists( $login );
		if ( ! $uid ) {
			$uid = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $login . '-pass-123', 'user_email' => $login . '@example.test', 'role' => $role ) );
		} else {
			wp_set_password( $login . '-pass-123', $uid );
		}
		$u = new WP_User( $uid );
		$u->set_role( $role );
		foreach ( $caps as $c ) {
			$u->add_cap( trim( $c ) );
		}
		efb_e2e_out( array( 'id' => $uid, 'login' => $login, 'password' => $login . '-pass-123', 'caps' => array_keys( array_filter( $u->allcaps ) ) ) );
		break;

	case 'locale':
		$loc = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '';
		update_option( 'WPLANG', 'en_US' === $loc ? '' : $loc );
		efb_e2e_out( array( 'WPLANG' => get_option( 'WPLANG' ) ) );
		break;

	case 'restrict-plan':
		update_option( 'efb_e2e_restrict_plan', isset( $efb_e2e_rest[0] ) && '1' === $efb_e2e_rest[0] ? 1 : 0 );
		efb_e2e_out( array( 'restrict' => (int) get_option( 'efb_e2e_restrict_plan' ), 'mu_plugin' => function_exists( 'efb_e2e_mu_loaded' ) ) );
		break;

	case 'plan':
		// Only so a scenario can run on its own; the suite itself changes plans through the UI.
		$pkg = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 2;
		update_option( 'emsfb_pro', $pkg );
		$settings = efb_e2e_decode_json( get_option( 'emsfb_settings' ) );
		if ( is_array( $settings ) ) {
			$settings['package_type'] = $pkg;
			update_option( 'emsfb_settings', wp_json_encode( $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		}
		efb_e2e_out( array( 'emsfb_pro' => get_option( 'emsfb_pro' ) ) );
		break;

	case 'constants':
		efb_e2e_out( array(
			'EMSFB_API_VERSION'    => defined( 'EMSFB_API_VERSION' ) ? EMSFB_API_VERSION : null,
			'EMSFB_PLUGIN_VERSION' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : null,
			'db'                   => defined( 'DB_ENGINE' ) ? DB_ENGINE : ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ? 'sqlite' : 'mysql' ),
		) );
		break;

	case 'option':
		efb_e2e_out( get_option( isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '', null ) );
		break;

	case 'set-option':
		// set-option <name> <value> | set-option <name> del
		$n = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '';
		if ( isset( $efb_e2e_rest[1] ) && 'del' === $efb_e2e_rest[1] ) delete_option( $n ); else update_option( $n, isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : '1', false );
		efb_e2e_out( array( $n => get_option( $n, null ) ) );
		break;

	case 'f11-mu':
		// Copy tests/e2e-wp/mu-plugins/efb-e2e-f11.php into the site's mu-plugins (F11 tests).
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-f11.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = dirname( __FILE__ ) . '/mu-plugins/efb-e2e-f11.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		$ok = $same || copy( $src, $dst );
		// A changed file: give the server's opcache (revalidate_freq) time to see it.
		if ( ! $same ) sleep( 3 );
		efb_e2e_out( array( 'copied' => $ok, 'changed' => ! $same, 'path' => $dst ) );
		break;

	case 'f06-mu':
		// Copy tests/e2e-wp/mu-plugins/efb-e2e-f06.php into the site's mu-plugins (F06 tests).
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-f06.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = dirname( __FILE__ ) . '/mu-plugins/efb-e2e-f06.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		$ok = $same || copy( $src, $dst );
		if ( ! $same ) sleep( 3 );
		efb_e2e_out( array( 'copied' => $ok, 'changed' => ! $same, 'path' => $dst ) );
		break;

	case 'f06-reset':
		// F06: plan back to the site's own, no full dates, no bookings, an empty hook log.
		foreach ( array( 'efb_e2e_f06_plan', 'efb_e2e_f06_full', 'efb_e2e_f06_booking', 'efb_e2e_f06_taken', 'efb_e2e_f06_log' ) as $o ) delete_option( $o );
		efb_e2e_out( array( 'reset' => true ) );
		break;

	case 'f06-today':
		efb_e2e_out( array( 'today' => function_exists( 'efb_calendar_today' ) ? efb_calendar_today() : '', 'tz' => class_exists( '\Emsfb\Calendar' ) ? \Emsfb\Calendar::site_tz() : '' ) );
		break;

	case 'f06-set':
		// F06: f06-set <plan|full|booking|taken> <value>; full / taken take a comma list of ISO dates.
		$k = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '';
		$v = isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : '';
		if ( in_array( $k, array( 'full', 'taken' ), true ) ) update_option( 'efb_e2e_f06_' . $k, array_values( array_filter( explode( ',', $v ) ) ), false );
		elseif ( in_array( $k, array( 'plan', 'booking' ), true ) ) update_option( 'efb_e2e_f06_' . $k, $v, false );
		efb_e2e_out( array( $k => get_option( 'efb_e2e_f06_' . $k ), 'taken' => get_option( 'efb_e2e_f06_taken', array() ), 'log' => get_option( 'efb_e2e_f06_log', array() ) ) );
		break;

	case 'setting':
		$s = get_setting_Emsfb( 'decoded' );
		$k = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '';
		efb_e2e_out( is_object( $s ) && isset( $s->$k ) ? $s->$k : null );
		break;

	case 'f13-seed':
		$fid   = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$ago   = isset( $efb_e2e_rest[1] ) ? (int) $efb_e2e_rest[1] : 100;
		$rago  = isset( $efb_e2e_rest[2] ) && '' !== $efb_e2e_rest[2] ? (int) $efb_e2e_rest[2] : null;
		$file  = isset( $efb_e2e_rest[3] ) && '1' === $efb_e2e_rest[3];
		$email = isset( $efb_e2e_rest[4] ) ? $efb_e2e_rest[4] : 'old-guest@example.test';
		$form  = efb_e2e_decode_json( $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $fid ) ) ); // phpcs:ignore
		$id_of = function ( $t ) use ( $form ) { foreach ( (array) $form as $r ) { if ( is_array( $r ) && isset( $r['type'] ) && $r['type'] === $t ) { return $r['id_']; } } return $t; };
		$rows  = array(
			array( 'id_' => $id_of( 'email' ), 'name' => 'Email', 'type' => 'email', 'value' => $email, 'amount' => 2 ),
			array( 'id_' => $id_of( 'text' ), 'name' => 'Name', 'type' => 'text', 'value' => 'Old Guest', 'amount' => 3 ),
		);
		$up = null;
		if ( $file ) {
			$name = 'efb-PLG-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 8, false, false ) ) . '.pdf';
			$up   = wp_upload_bits( $name, null, '%PDF-1.4 e2e' );
			$rows[] = array( 'id_' => 'f13file', 'name' => 'CV', 'type' => 'file', 'value' => '@file@', 'url' => $up['url'], 'amount' => 4 );
		}
		$rows[] = array( 'type' => 'w_link', 'id_' => 'w_link', 'value' => home_url( '/?utm_source=e2e&email=' . rawurlencode( $email ) ), 'amount' => -1 );
		$date = wp_date( 'Y-m-d H:i:s', time() - $ago * DAY_IN_SECONDS );
		$wpdb->insert( $wpdb->prefix . 'emsfb_msg_', array( 'form_title_x' => 'E2E F13 privacy', 'content' => str_replace( '"', '\\"', wp_json_encode( $rows, JSON_UNESCAPED_UNICODE ) ), 'form_id' => $fid, 'track' => 'E2E' . wp_rand( 10000, 99999 ), 'ip' => '203.0.113.9', 'read_' => 0, 'read_by' => '', 'date' => $date ) );
		$msg_id = (int) $wpdb->insert_id;
		if ( null !== $rago ) {
			$wpdb->insert( $wpdb->prefix . 'emsfb_rsp_', array( 'msg_id' => $msg_id, 'ip' => '203.0.113.9', 'content' => str_replace( '"', '\\"', wp_json_encode( array( array( 'id_' => 'message', 'type' => 'text', 'value' => 'a reply with personal details' ) ) ) ), 'date' => wp_date( 'Y-m-d H:i:s', time() - $rago * DAY_IN_SECONDS ), 'read_by' => '', 'read_' => 0, 'reader_ip' => '', 'rsp_by' => 0 ) );
		}
		efb_e2e_out( array( 'msg_id' => $msg_id, 'file' => $up ? $up['file'] : null, 'url' => $up ? $up['url'] : null, 'date' => $date ) );
		break;

	case 'replies':
		$id = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		efb_e2e_out( $wpdb->get_results( $wpdb->prepare( "SELECT rsp_id, msg_id, ip, reader_ip, content FROM {$wpdb->prefix}emsfb_rsp_ WHERE msg_id = %d", $id ), ARRAY_A ) ); // phpcs:ignore
		break;

	case 'file-exists':
		efb_e2e_out( isset( $efb_e2e_rest[0] ) && file_exists( $efb_e2e_rest[0] ) );
		break;

	case 'sessions':
		$id = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		efb_e2e_out( $wpdb->get_results( $wpdb->prepare( "SELECT id, sid, fid, status, ip, os, browser, tc FROM {$wpdb->prefix}emsfb_stts_ WHERE fid = %d ORDER BY id", $id ), ARRAY_A ) ); // phpcs:ignore
		break;

	case 'pay-mock':
		update_option( 'efb_e2e_payments_mock', ( isset( $efb_e2e_rest[0] ) && '1' === $efb_e2e_rest[0] ) ? 1 : 0 );
		// The site's copy of the suite's mu-plugin registers the test gateway (tests/payments-mock): keep it current.
		if ( defined( 'WPMU_PLUGIN_DIR' ) && is_dir( WPMU_PLUGIN_DIR ) ) {
			copy( __DIR__ . '/mu-plugins/efb-e2e.php', WPMU_PLUGIN_DIR . '/efb-e2e.php' );
		}
		efb_e2e_out( array( 'ok' => true ) );
		break;

	case 'pay-keys':
		if ( ! class_exists( '\Emsfb\Payments\Mock_Gateway' ) ) efb_e2e_out( array( 'error' => 'mock gateway not loaded (pay-mock 1 first)' ) );
		$gw = new \Emsfb\Payments\Mock_Gateway();
		$st = \Emsfb\Payments\Settings::load( 'efbmock' );
		// Local test placeholders of the fake provider, not real keys.
		$st->apply( $gw->settings_schema(), array( 'api_key__test' => 'mock_test_E2Ekey000', 'webhook_secret__test' => 'e2e-mock-webhook-secret', 'api_key__live' => 'mock_live_E2Ekey000', 'webhook_secret__live' => 'e2e-mock-webhook-secret-live' ), isset( $efb_e2e_rest[0] ) && 'live' === $efb_e2e_rest[0] ? 'live' : 'test' );
		efb_e2e_out( array( 'ok' => true, 'mode' => \Emsfb\Payments\Settings::load( 'efbmock' )->mode() ) );
		break;

	case 'pay-form':
		$kind = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : 'fixed';
		$name = isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : 'Payment ' . $kind;
		$rid  = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $pf = $rid(); $rad = $rid(); $o1 = $rid(); $o2 = $rid(); $num = $rid(); $tot = $rid();
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => 'payment', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'getway' => 'efbmock', 'currency' => 'EUR', 'paymentmethod' => 'charge', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1, 'noti' => 1 ) );
		$pf_row = array_merge( $base, array( 'id_' => $pf, 'dataId' => $pf . '-id', 'type' => 'efbmock', 'elementId' => 'efbmock', 'name' => 'Payment', 'amount' => 5 ) );
		if ( 'fixed' === $kind ) {
			$rows[] = array_merge( $base, array( 'id_' => $rad, 'dataId' => $rad . '-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'name' => 'Ticket', 'amount' => 3, 'required' => 1 ) );
			$rows[] = array( 'id_' => $o1, 'dataId' => $o1 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Adult', 'id_op' => $o1, 'step' => 1, 'amount' => 4, 'price' => '25' );
			$rows[] = array( 'id_' => $o2, 'dataId' => $o2 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Child', 'id_op' => $o2, 'step' => 1, 'amount' => 4, 'price' => '12.5' );
			$pf_row['pf_fixed'] = '2';
			$pf_row['pf_fixed_label'] = 'Booking fee';
		} elseif ( 'donation' === $kind ) {
			$pf_row['pf_amount'] = 'donation';
			$pf_row['pf_presets'] = '10,25,50';
			$pf_row['pf_other'] = '1';
			$pf_row['pf_min'] = '5';
			$pf_row['pf_max'] = '500';
			$pf_row['pf_default'] = '25';
			$pf_row['pf_btn_text'] = 'Donate %amount%';
			$pf_row['pf_btn_bg'] = '#14532d';
			$pf_row['pf_btn_icon'] = 'bi-heart';
		} elseif ( 'date' === $kind ) {
			// F06: an advanced date that changes the price: a weekend night costs 20, a weekday 10 (Conditional Logic).
			$rows[] = array_merge( $base, array( 'id_' => $num, 'dataId' => $num . '-id', 'type' => 'advdate', 'elementId' => 'advdate', 'name' => 'Night', 'amount' => 3, 'required' => 1, 'cal_v' => 1, 'cal_default' => 'gregory', 'date_min' => array( 'type' => 'relative', 'value' => 1 ) ) );
			$rows[] = array_merge( $base, array( 'id_' => $tot, 'dataId' => $tot . '-id', 'type' => 'prcfld', 'elementId' => 'prcfld', 'name' => 'Total', 'amount' => 4, 'milen' => '0' ) );
			$rows[0]['logic'] = '1';
			$cond = function ( $days ) use ( $num ) { return array( 'type' => 'group', 'operator' => 'AND', 'items' => array( array( 'type' => 'condition', 'source' => 'field', 'field_id' => $num, 'compare' => 'date_weekday_is', 'value' => $days ) ) ); };
			$rows[0]['logic_rules'] = array(
				array( 'id' => 'weekend', 'enabled' => true, 'priority' => 1, 'conditions' => $cond( '6,7' ), 'actions' => array( array( 'type' => 'calculate', 'target' => $tot, 'value' => '20', 'decimals' => 2 ) ) ),
				array( 'id' => 'weekday', 'enabled' => true, 'priority' => 2, 'conditions' => $cond( '1,2,3,4,5' ), 'actions' => array( array( 'type' => 'calculate', 'target' => $tot, 'value' => '10', 'decimals' => 2 ) ) ),
			);
		} elseif ( 'computed' === $kind ) {
			$rows[] = array_merge( $base, array( 'id_' => $num, 'dataId' => $num . '-id', 'type' => 'number', 'elementId' => 'number', 'name' => 'Guests', 'amount' => 3, 'required' => 1 ) );
			$rows[] = array_merge( $base, array( 'id_' => $tot, 'dataId' => $tot . '-id', 'type' => 'prcfld', 'elementId' => 'prcfld', 'name' => 'Total', 'amount' => 4, 'milen' => '0' ) );
			$rows[0]['logic'] = '1';
			$rows[0]['logic_rules'] = array( array( 'id' => 'calc', 'enabled' => true, 'priority' => 1, 'conditions' => array( 'type' => 'group', 'operator' => 'AND', 'items' => array( array( 'type' => 'condition', 'source' => 'field', 'field_id' => $num, 'compare' => 'is_not_empty', 'value' => '' ) ) ), 'actions' => array( array( 'type' => 'calculate', 'target' => $tot, 'value' => '{' . $num . '} * 5', 'decimals' => 2 ) ) ) );
		}
		if ( isset( $efb_e2e_rest[2] ) ) {
			foreach ( explode( '&', $efb_e2e_rest[2] ) as $kv ) {
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$rows[0][ $k ] = urldecode( $v );
			}
		}
		$rows[] = $pf_row;
		$fn = get_efbFunction();
		$clean = $fn->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'payment', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		$fid = (int) $wpdb->insert_id;
		do_action( 'efb_form_created', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_e2e_out( array( 'form_id' => $fid, 'email' => $em, 'pf' => $pf, 'radio' => $rad, 'o1' => $o1, 'o2' => $o2, 'guests' => $num, 'total' => $tot ) );
		break;

	case 'f11-form':
		wp_set_current_user( 1 ); // custom_css is kept only for a user who may write unfiltered HTML
		// F11: a plain form (email, name, optionally a password and a second step) saved through the
		// builder's sanitizer and Form_Gate's save rules. Args: [name] [row-0 overrides k=v&k=v] [steps 1|2] [type]
		$name  = isset( $efb_e2e_rest[0] ) && '' !== $efb_e2e_rest[0] ? $efb_e2e_rest[0] : 'F11 form';
		$steps = isset( $efb_e2e_rest[2] ) ? max( 1, (int) $efb_e2e_rest[2] ) : 1;
		$ftype = isset( $efb_e2e_rest[3] ) && '' !== $efb_e2e_rest[3] ? $efb_e2e_rest[3] : 'form';
		$rid   = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $nm = $rid(); $pw = $rid(); $ph = $rid(); $tx = $rid(); $fl = $rid();
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => $ftype, 'steps' => $steps, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		$step = function ( $n ) { return array( 'id_' => (string) $n, 'type' => 'step', 'dataId' => (string) $n, 'classes' => '', 'id' => (string) $n, 'name' => 'Step ' . $n, 'icon' => 'bi-ui-checks-grid', 'step' => $n, 'amount' => $n === 1 ? 1 : 10, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ); };
		$rows = array( $row0, $step( 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $nm, 'dataId' => $nm . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'Name', 'amount' => 3 ) );
		if ( $steps > 1 ) {
			$rows[] = $step( 2 );
			$rows[] = array_merge( $base, array( 'id_' => $ph, 'dataId' => $ph . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'City', 'amount' => 11, 'step' => 2 ) );
			$rows[] = array_merge( $base, array( 'id_' => $pw, 'dataId' => $pw . '-id', 'type' => 'password', 'elementId' => 'password', 'name' => 'Secret', 'amount' => 12, 'step' => 2 ) );
			$rows[] = array_merge( $base, array( 'id_' => $tx, 'dataId' => $tx . '-id', 'type' => 'textarea', 'elementId' => 'textarea', 'name' => 'Notes', 'amount' => 13, 'step' => 2 ) );
			$rows[] = array_merge( $base, array( 'id_' => $fl, 'dataId' => $fl . '-id', 'type' => 'file', 'elementId' => 'file', 'name' => 'Attachment', 'amount' => 14, 'step' => 2, 'file' => 'Document', 'max_fsize' => '2' ) );
		}
		if ( isset( $efb_e2e_rest[1] ) && '' !== $efb_e2e_rest[1] ) {
			foreach ( explode( '&', $efb_e2e_rest[1] ) as $kv ) {
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$v = urldecode( $v );
				$rows[0][ $k ] = '{email}' === $v ? $em : $v;
			}
		}
		$fn = get_efbFunction();
		$clean = $fn->sanitize_obj_msg_efb( $rows );
		if ( class_exists( '\Emsfb\Form_Gate' ) ) $clean = \Emsfb\Form_Gate::normalize_form_settings( $clean, null );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => $ftype, 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		$fid = (int) $wpdb->insert_id;
		do_action( 'efb_form_created', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_e2e_out( array( 'form_id' => $fid, 'email' => $em, 'name' => $nm, 'city' => $ph, 'password' => $pw, 'notes' => $tx, 'file' => $fl, 'row0' => $clean[0] ) );
		break;

	case 'f03-form':
		// F03: a form whose fields are given as JSON, saved through the builder's sanitizer (and the
		// F03 save rules). Args: <name> <json: [{key, type, name, ...field keys}]> [row-0 k=v&k=v] [steps]
		// The field ids are fresh; the answer maps each key to its id_.
		wp_set_current_user( 1 );
		$name   = isset( $efb_e2e_rest[0] ) && '' !== $efb_e2e_rest[0] ? $efb_e2e_rest[0] : 'F03 form';
		$fields = isset( $efb_e2e_rest[1] ) ? json_decode( $efb_e2e_rest[1], true ) : array();
		$steps  = isset( $efb_e2e_rest[3] ) ? max( 1, (int) $efb_e2e_rest[3] ) : 1;
		$rid    = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => 'form', 'steps' => $steps, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => '', 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		$step = function ( $n ) { return array( 'id_' => (string) $n, 'type' => 'step', 'dataId' => (string) $n, 'classes' => '', 'id' => (string) $n, 'name' => 'Step ' . $n, 'icon' => 'bi-ui-checks-grid', 'step' => $n, 'amount' => $n === 1 ? 1 : 100, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ); };
		$rows = array( $row0, $step( 1 ) );
		$ids  = array();
		$amount = 2;
		foreach ( (array) $fields as $fd ) {
			if ( ! is_array( $fd ) ) continue;
			$id = $rid();
			$key = isset( $fd['key'] ) ? (string) $fd['key'] : $id;
			unset( $fd['key'] );
			$st = isset( $fd['step'] ) ? (int) $fd['step'] : 1;
			if ( $st > 1 && ! in_array( $st, array_column( array_filter( $rows, function ( $r ) { return isset( $r['type'] ) && 'step' === $r['type']; } ), 'step' ), true ) ) $rows[] = $step( $st );
			$type = isset( $fd['type'] ) ? $fd['type'] : 'text';
			$rows[] = array_merge( $base, array( 'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $type, 'name' => $key, 'amount' => $amount++ ), $fd );
			$ids[ $key ] = $id;
			if ( 'email' === $type && '' === $rows[0]['email_to'] ) $rows[0]['email_to'] = $id;
		}
		if ( isset( $efb_e2e_rest[2] ) && '' !== $efb_e2e_rest[2] ) {
			foreach ( explode( '&', $efb_e2e_rest[2] ) as $kv ) {
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$rows[0][ $k ] = urldecode( $v );
			}
		}
		$fn = get_efbFunction();
		$clean = $fn->sanitize_obj_msg_efb( $rows );
		if ( class_exists( '\Emsfb\Form_Gate' ) ) $clean = \Emsfb\Form_Gate::normalize_form_settings( $clean, null );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		$fid = (int) $wpdb->insert_id;
		do_action( 'efb_form_created', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_e2e_out( array( 'form_id' => $fid, 'ids' => $ids, 'rows' => $clean ) );
		break;

	case 'f03-uniq':
		// F03: the unique-value claims of a form (hashes only) and the refusal counters reset with "reset".
		$fid = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		if ( isset( $efb_e2e_rest[1] ) && 'reset' === $efb_e2e_rest[1] ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_rl_ WHERE scope = %s", 'uqf' ) ); // phpcs:ignore
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, form_id, field_id, vhash, msg_id, date FROM {$wpdb->prefix}emsfb_uniq_ WHERE form_id = %d ORDER BY id", $fid ), ARRAY_A ); // phpcs:ignore
		efb_e2e_out( array( 'rows' => $rows ? $rows : array(), 'backfill' => get_option( 'emsfb_uniq_bf_' . $fid, null ) ) );
		break;

	case 'f03-field':
		// F03: change keys of one field of a stored form as a builder save would (sanitizer + efb_form_saved). Args: <form_id> <field id_> <k=v&k=v>
		wp_set_current_user( 1 );
		$fid  = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$rows = efb_e2e_decode_json( $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $fid ) ) ); // phpcs:ignore
		foreach ( $rows as $i => $r ) {
			if ( $i < 1 || ! is_array( $r ) || ! isset( $r['id_'] ) || $r['id_'] !== ( $efb_e2e_rest[1] ?? '' ) ) continue;
			foreach ( explode( '&', isset( $efb_e2e_rest[2] ) ? $efb_e2e_rest[2] : '' ) as $kv ) {
				if ( '' === $kv ) continue;
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$rows[ $i ][ $k ] = urldecode( $v );
			}
		}
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->update( $wpdb->prefix . 'emsfb_form', array( 'form_structer' => $json ), array( 'form_id' => $fid ) );
		wp_cache_flush();
		do_action( 'efb_form_saved', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_e2e_out( array( 'form_id' => $fid, 'backfill' => get_option( 'emsfb_uniq_bf_' . $fid, null ) ) );
		break;

	case 'f11-set':
		// F11: change row-0 keys of a stored form (as a builder save would, through the sanitizer). Args: <form_id> <k=v&k=v>
		wp_set_current_user( 1 );
		$fid  = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $fid ) ); // phpcs:ignore
		$rows = efb_e2e_decode_json( $raw );
		foreach ( explode( '&', isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : '' ) as $kv ) {
			if ( '' === $kv ) continue;
			list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
			$rows[0][ $k ] = urldecode( $v );
		}
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		if ( class_exists( '\Emsfb\Form_Gate' ) ) $clean = \Emsfb\Form_Gate::normalize_form_settings( $clean, $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->update( $wpdb->prefix . 'emsfb_form', array( 'form_structer' => $json ), array( 'form_id' => $fid ) );
		wp_cache_flush();
		efb_e2e_out( array( 'form_id' => $fid, 'row0' => $clean[0] ) );
		break;

	case 'f11-counters':
		// F11: the counters of a form (scopes lt/lu/li/lc/le:<id>); never a raw key (buckets are HMACs).
		$fid  = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT bucket, scope, hits, window_end FROM {$wpdb->prefix}emsfb_vrfy_rl_ WHERE scope IN (%s,%s,%s,%s,%s) ORDER BY scope", 'lt:' . $fid, 'lu:' . $fid, 'li:' . $fid, 'lc:' . $fid, 'le:' . $fid ), ARRAY_A ); // phpcs:ignore
		efb_e2e_out( $rows ? $rows : array() );
		break;

	case 'f11-drafts':
		$fid  = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emsfb_draft_ WHERE fid = %d ORDER BY id", $fid ), ARRAY_A ); // phpcs:ignore
		efb_e2e_out( $rows ? $rows : array() );
		break;

	case 'f11-drafts-op':
		// F11-E/F: expire <form_id> (every draft of the form ends now) | cleanup (the daily job) |
		// export <email> | erase <email> (WordPress' personal-data tools, the drafts part).
		$op  = isset( $efb_e2e_rest[0] ) ? $efb_e2e_rest[0] : '';
		$arg = isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : '';
		$out = null;
		if ( 'expire' === $op ) $out = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}emsfb_draft_ SET expires_at = %s WHERE fid = %d", gmdate( 'Y-m-d H:i:s', time() - 60 ), (int) $arg ) ); // phpcs:ignore
		if ( 'cleanup' === $op ) $out = \Emsfb\Drafts::cleanup();
		if ( 'export' === $op ) $out = \Emsfb\Drafts::export( $arg, 1 );
		if ( 'erase' === $op ) $out = \Emsfb\Drafts::erase( $arg, 1 );
		efb_e2e_out( $out );
		break;

	case 'f11-delete-entry':
		$mid = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$out = class_exists( '\Emsfb\Privacy' ) ? \Emsfb\Privacy::delete_entries( array( $mid ) ) : null;
		efb_e2e_out( $out );
		break;

	case 'payments':
		$fid = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$sql = $fid ? $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}emsfb_pmt_ WHERE form_id = %d ORDER BY id DESC", $fid ) : "SELECT * FROM {$wpdb->prefix}emsfb_pmt_ ORDER BY id DESC"; // phpcs:ignore
		efb_e2e_out( $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore
		break;

	case 'pay-events':
		efb_e2e_out( $wpdb->get_results( $wpdb->prepare( "SELECT id, source, type, from_status, to_status, http_code, note FROM {$wpdb->prefix}emsfb_pmt_evt_ WHERE payment_id = %d ORDER BY id", isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0 ), ARRAY_A ) ); // phpcs:ignore
		break;

	case 'pay-provider':
		if ( ! class_exists( '\Emsfb\Payments\Mock_Provider' ) ) efb_e2e_out( array( 'error' => 'mock gateway not loaded' ) );
		$ok = \Emsfb\Payments\Mock_Provider::set_status( $efb_e2e_rest[0], $efb_e2e_rest[1], isset( $efb_e2e_rest[2] ) ? $efb_e2e_rest[2] : null );
		efb_e2e_out( array( 'ok' => $ok ) );
		break;

	case 'pay-webhook-secret':
		$s = \Emsfb\Payments\Settings::load( 'efbmock' );
		efb_e2e_out( array( 'secret' => $s->secret( 'webhook_secret', $s->mode() ) ) );
		break;

	case 'mails':
		if ( isset( $efb_e2e_rest[0] ) && 'clear' === $efb_e2e_rest[0] ) {
			update_option( 'efb_e2e_mails', array(), false );
			update_option( 'efb_e2e_capture_mail', 1, false );
		}
		efb_e2e_out( get_option( 'efb_e2e_mails', array() ) );
		break;

	case 'addon-flag':
		// An add-on switch in the plugin settings (the add-on's files must already be in vendor/).
		$s = get_setting_Emsfb( 'decoded' );
		$key = isset( $efb_e2e_rest[0] ) ? preg_replace( '/[^A-Za-z]/', '', $efb_e2e_rest[0] ) : '';
		if ( ! is_object( $s ) || '' === $key ) efb_e2e_out( array( 'error' => 'no settings' ) );
		$s->$key = ( isset( $efb_e2e_rest[1] ) && '1' === $efb_e2e_rest[1] ) ? 1 : 0;
		\efbFunction::set_setting_Emsfb( $s );
		efb_e2e_out( array( 'ok' => true, $key => get_setting_Emsfb( 'decoded' )->$key ) );
		break;

	case 'pay-clear-keys':
		// PAY00 readiness: the test gateway without any key (its settings option removed).
		delete_option( 'emsfb_pgw_efbmock' );
		efb_e2e_out( array( 'ok' => true, 'option' => get_option( 'emsfb_pgw_efbmock', null ) ) );
		break;

	case 'pay-form-retype':
		// PAY00 readiness: the payment field of a stored form becomes the field of another gateway
		// (e.g. one whose add-on is off), as a form built while that add-on was on.
		$id   = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		$to   = isset( $efb_e2e_rest[1] ) ? preg_replace( '/[^a-z0-9]/', '', $efb_e2e_rest[1] ) : '';
		$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $id ) ); // phpcs:ignore
		$rows = efb_e2e_decode_json( $raw );
		if ( ! is_array( $rows ) || '' === $to ) efb_e2e_out( array( 'error' => 'no form' ) );
		foreach ( $rows as $i => $r ) {
			if ( is_array( $r ) && isset( $r['type'] ) && 'efbmock' === $r['type'] ) { $rows[ $i ]['type'] = $to; $rows[ $i ]['elementId'] = $to; }
		}
		if ( is_array( $rows[0] ) ) $rows[0]['getway'] = $to;
		$wpdb->update( $wpdb->prefix . 'emsfb_form', array( 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $rows, JSON_UNESCAPED_UNICODE ) ) ), array( 'form_id' => $id ) );
		efb_e2e_out( array( 'ok' => true, 'types' => array_values( array_filter( array_map( function ( $r ) { return is_array( $r ) && isset( $r['type'] ) ? $r['type'] : null; }, $rows ) ) ) ) );
		break;

	case 'f02-mu':
		// Copy tests/e2e-wp/mu-plugins/efb-e2e-f02.php into the site's mu-plugins (F02 scenarios).
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-f02.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = dirname( __FILE__ ) . '/mu-plugins/efb-e2e-f02.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		$ok = $same || copy( $src, $dst );
		if ( ! $same ) sleep( 3 );
		efb_e2e_out( array( 'copied' => $ok, 'changed' => ! $same ) );
		break;

	case 'f02-wpsms':
		// The WP SMS stand-in on / off and its answer: f02-wpsms <0|1> [ok|error|false]
		if ( isset( $efb_e2e_rest[0] ) && '1' === $efb_e2e_rest[0] ) update_option( 'efb_e2e_wpsms', 1, false ); else delete_option( 'efb_e2e_wpsms' );
		update_option( 'efb_e2e_wpsms_mode', isset( $efb_e2e_rest[1] ) ? $efb_e2e_rest[1] : 'ok', false );
		efb_e2e_out( array( 'wpsms' => (bool) get_option( 'efb_e2e_wpsms' ), 'mode' => get_option( 'efb_e2e_wpsms_mode' ) ) );
		break;

	case 'f02-sms':
		// Messages the WP SMS stand-in received: f02-sms [clear]
		if ( isset( $efb_e2e_rest[0] ) && 'clear' === $efb_e2e_rest[0] ) update_option( 'efb_e2e_sms', array(), false );
		efb_e2e_out( get_option( 'efb_e2e_sms', array() ) );
		break;

	case 'f02-settings':
		// Verification settings: f02-settings [json patch] (merged, clamped by the engine); prints them and the channels' state.
		if ( isset( $efb_e2e_rest[0] ) && '' !== $efb_e2e_rest[0] ) {
			$patch = json_decode( $efb_e2e_rest[0], true );
			if ( is_array( $patch ) ) \Emsfb\Verification::save_settings( array_replace_recursive( \Emsfb\Verification::settings(), $patch ) );
		}
		\Emsfb\Verification::reset_cache();
		efb_e2e_out( array( 'settings' => \Emsfb\Verification::settings(), 'email' => \Emsfb\Verification::channel_state( 'email' ), 'sms' => \Emsfb\Verification::channel_state( 'sms' ) ) );
		break;

	case 'f02-rows':
		// The challenge rows of a form (no code: only its HMAC exists) and, with "reset", every F02 counter cleared first.
		$fid = isset( $efb_e2e_rest[0] ) ? (int) $efb_e2e_rest[0] : 0;
		if ( isset( $efb_e2e_rest[1] ) && 'reset' === $efb_e2e_rest[1] ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_rl_ WHERE scope LIKE 'v%'" ); // phpcs:ignore
			$wpdb->query( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_" ); // phpcs:ignore -- the cooldown history of earlier scenarios and runs
			foreach ( array( 'budget_80', 'budget_full', 'conversion', 'send_failed' ) as $k ) foreach ( array( 'sms', 'email' ) as $c ) delete_transient( 'efb_verify_alert_sent_' . $k . '_' . $c );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT challenge_id, field_id, channel, dest_hash, dest_mask, ip_hash, code_hash, attempts, status, msg_id, sid FROM {$wpdb->prefix}emsfb_vrfy_ WHERE fid = %d ORDER BY id", $fid ), ARRAY_A ); // phpcs:ignore
		efb_e2e_out( array( 'rows' => $rows ? $rows : array() ) );
		break;

	case 'reconcile':
		efb_e2e_out( \Emsfb\Payments\Service::reconcile() );
		break;

	default:
		efb_e2e_out( array( 'error' => 'unknown command ' . $efb_e2e_cmd ) );
}
