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
 *   wipe                      remove everything Easy Form Builder stored (tables, options, caps,
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
		foreach ( array( 'emsfb_form', 'emsfb_msg_', 'emsfb_rsp_', 'emsfb_setting', 'emsfb_stts_' ) as $t ) {
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

	default:
		efb_e2e_out( array( 'error' => 'unknown command ' . $efb_e2e_cmd ) );
}
