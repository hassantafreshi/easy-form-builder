<?php
/**
 * Control commands for the add-on recovery end-to-end site built by setup.php.
 *
 * Every command prints one JSON line, so run.js can call it between steps.
 *
 *   state                     recovery flags, scheduled retries, request, mail and PHP error logs
 *   reset                     every add-on back in place, recovery state cleared
 *   server ok|down|expired    how the stubbed add-on server answers
 *   cron on|off               off = DISABLE_WP_CRON with no heartbeat
 *   remove AdnXXX ...         delete add-on folders, the way a plugin update does
 *   simulate-update           the next admin request runs the plugin's upgrade tasks
 *   due                       scheduled recovery retries become due now
 *   set-flag AdnXXX 0|1       switch an add-on on or off in the settings
 *   clear-backoff             as if the day-long download backoff had expired
 *   clear-report-throttle     as if a day had passed since the last report email
 *   clear-logs                empty the request, mail and PHP error logs
 *   page slug                 URL of a test page
 *
 * Run: C:\xampp\php\php.exe tests/e2e-addon-recovery/ctl.php <command> [args]
 */

$efb_e2e_site = rtrim( str_replace( '\\', '/', getenv( 'EFB_E2E_SITE_DIR' ) ?: 'C:/xampp/htdocs/efb-e2e' ), '/' );
$command      = isset( $argv[1] ) ? $argv[1] : 'state';
$args         = array_slice( $argv, 2 );

// This process inspects and edits state; it must never run the retries itself.
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';

require $efb_e2e_site . '/wp-load.php';

const EFB_E2E_FOLDERS = array(
	'AdnHSH' => 'human-shield',
	'AdnSMF' => 'logic',
	'AdnSPF' => 'stripe',
	'AdnPAP' => 'paypal',
	'AdnPDP' => 'persiadatepicker',
	'AdnADP' => 'arabicdatepicker',
	'AdnATF' => 'autofill',
	'AdnTLG' => 'telegram',
	'AdnGoS' => 'googlesheet',
	'AdnOF'  => 'offline',
	'AdnPPF' => 'persiapay',
);

function efb_e2e_out( $data ) {
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . "\n";
	exit( 0 );
}

function efb_e2e_fail( $message ) {
	echo wp_json_encode( array( 'error' => $message ) ) . "\n";
	exit( 1 );
}

function efb_e2e_path( $path ) {
	return strtolower( rtrim( str_replace( '\\', '/', $path ), '/' ) );
}

/** Deletes a folder under the test site's plugin vendor directory, and nothing else. */
function efb_e2e_delete_vendor_folder( $folder ) {
	$vendor = efb_e2e_path( EMSFB_PLUGIN_DIRECTORY . 'vendor' );
	$dir    = EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $folder;
	if ( 0 !== strpos( efb_e2e_path( ABSPATH ), efb_e2e_path( $GLOBALS['efb_e2e_site'] ) )
		|| 0 !== strpos( efb_e2e_path( $dir ), $vendor . '/' )
		|| ! is_dir( $dir ) ) {
		return false;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	return rmdir( $dir );
}

function efb_e2e_restore_folder( $addon_key ) {
	$folder = EFB_E2E_FOLDERS[ $addon_key ];
	if ( is_dir( EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $folder ) ) {
		return true;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( WP_CONTENT_DIR . '/efb-e2e-zips/' . $folder . '.zip' ) ) {
		return false;
	}
	$done = $zip->extractTo( EMSFB_PLUGIN_DIRECTORY . 'vendor/' );
	$zip->close();
	return $done;
}

function efb_e2e_settings() {
	delete_transient( 'emsfb_settings_transient' );
	get_setting_Emsfb( '_clear_cache' );
	return get_setting_Emsfb( 'decoded' );
}

function efb_e2e_set_flags( $flags ) {
	$settings = efb_e2e_settings();
	foreach ( $flags as $addon_key => $value ) {
		$settings->{$addon_key} = (string) (int) $value;
		update_option( 'emsfb_addon_' . $addon_key, (int) $value );
	}
	efbFunction::set_setting_Emsfb( $settings, $settings->emailSupporter );
	efb_e2e_settings();
}

function efb_e2e_recovery_events() {
	$events = array();
	foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
		if ( ! empty( $hooks['emsfb_addon_recovery_event'] ) ) {
			foreach ( $hooks['emsfb_addon_recovery_event'] as $event ) {
				$events[] = array( 'in_seconds' => $timestamp - time(), 'args' => $event['args'] );
			}
		}
	}
	return $events;
}

function efb_e2e_clear_recovery_state() {
	foreach ( array( 'emsfb_addons_reinstall_required', 'emsfb_addon_recovery_pending', 'emsfb_addon_recovery_result', 'emsfb_addons_dl_failures', 'emsfb_addons_renew_required', 'emsfb_cron_last_run' ) as $option ) {
		delete_option( $option );
	}
	foreach ( array( 'emsfb_addons_dl_backoff', 'emsfb_addons_renew_backoff', 'emsfb_addon_recovery_lock', 'emsfb_addons_report_sent', 'doing_cron', 'efb_addon_pub_check' ) as $transient ) {
		delete_transient( $transient );
	}
	wp_unschedule_hook( 'emsfb_addon_recovery_event' );
}

/** PHP errors raised from inside the plugin, from the site's debug.log. */
function efb_e2e_plugin_php_errors() {
	$log = WP_CONTENT_DIR . '/debug.log';
	if ( ! is_file( $log ) ) {
		return array();
	}
	return array_values( array_filter( file( $log, FILE_IGNORE_NEW_LINES ), function ( $line ) {
		return preg_match( '/PHP (Fatal error|Parse error|Warning|Notice)/', $line ) && false !== stripos( $line, 'easy-form-builder' );
	} ) );
}

$flag_file = $efb_e2e_site . '/efb-e2e-no-cron.flag';

switch ( $command ) {
	case 'state':
		$settings = efb_e2e_settings();
		$health   = get_efbFunction()->get_addon_local_health_efb( $settings );
		$http     = (array) get_option( 'efb_e2e_http_log', array() );
		$mail     = (array) get_option( 'efb_e2e_mail_log', array() );
		$result   = get_option( 'emsfb_addon_recovery_result', array() );
		$errors   = efb_e2e_plugin_php_errors();
		$flags    = array();
		$present  = array();
		foreach ( EFB_E2E_FOLDERS as $addon_key => $folder ) {
			$flags[ $addon_key ] = isset( $settings->{$addon_key} ) ? (int) $settings->{$addon_key} : 0;
			if ( is_dir( EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $folder ) ) {
				$present[] = $addon_key;
			}
		}
		efb_e2e_out( array(
			'server'             => get_option( 'efb_e2e_server_mode', 'ok' ),
			'cron_disabled'      => file_exists( $flag_file ),
			'version'            => get_option( 'emsfb_version' ),
			'flags'              => $flags,
			'present'            => $present,
			'missing'            => array_keys( $health['missing'] ),
			'reinstall_required' => (bool) get_option( 'emsfb_addons_reinstall_required' ),
			'pending'            => get_option( 'emsfb_addon_recovery_pending', null ),
			'result_errors'      => is_array( $result ) && ! empty( $result['errors'] ) ? array_map( 'wp_strip_all_tags', $result['errors'] ) : array(),
			'backoff'            => false !== get_transient( 'emsfb_addons_dl_backoff' ),
			'renew_backoff'      => false !== get_transient( 'emsfb_addons_renew_backoff' ),
			'renew_required'     => (bool) get_option( 'emsfb_addons_renew_required' ),
			'lock'               => false !== get_transient( 'emsfb_addon_recovery_lock' ),
			'report_throttled'   => false !== get_transient( 'emsfb_addons_report_sent' ),
			'failures'           => (int) get_option( 'emsfb_addons_dl_failures', 0 ),
			'events'             => efb_e2e_recovery_events(),
			'addon_requests'     => count( array_filter( $http, function ( $row ) {
				return false !== strpos( $row['url'], 'addons-link' ) || false !== strpos( $row['url'], '/source/files/zip/' );
			} ) ),
			'http_log'           => array_slice( $http, -6 ),
			'mails'              => count( $mail ),
			'reports'            => count( array_filter( $mail, function ( $row ) {
				return ! empty( $row['addon_report'] );
			} ) ),
			'mail_log'           => array_slice( $mail, -4 ),
			'php_errors'         => count( $errors ),
			'php_error_lines'    => array_slice( $errors, -5 ),
			'pages'              => get_option( 'efb_e2e_pages', array() ),
		) );

	case 'reset':
		$restored = array();
		foreach ( array_keys( EFB_E2E_FOLDERS ) as $addon_key ) {
			$restored[ $addon_key ] = efb_e2e_restore_folder( $addon_key );
		}
		if ( file_exists( $flag_file ) ) {
			unlink( $flag_file );
		}
		efb_e2e_clear_recovery_state();
		update_option( 'efb_e2e_server_mode', 'ok' );
		update_option( 'efb_e2e_http_log', array(), false );
		update_option( 'efb_e2e_mail_log', array(), false );
		update_option( 'emsfb_version', EMSFB_PLUGIN_VERSION );
		efb_e2e_set_flags( get_option( 'efb_e2e_baseline_flags', array() ) );
		// The first-run wizard would cover the admin pages the scenarios click through.
		if ( function_exists( 'emsfb_complete_onboarding_efb' ) ) {
			emsfb_complete_onboarding_efb();
		}
		efb_e2e_out( array( 'reset' => true, 'restored' => $restored ) );

	case 'server':
		if ( ! in_array( $args[0] ?? '', array( 'ok', 'down', 'expired' ), true ) ) {
			efb_e2e_fail( 'server ok|down|expired' );
		}
		update_option( 'efb_e2e_server_mode', $args[0] );
		efb_e2e_out( array( 'server' => $args[0] ) );

	case 'cron':
		if ( 'off' === ( $args[0] ?? '' ) ) {
			file_put_contents( $flag_file, "DISABLE_WP_CRON for the EFB e2e site\n" );
			delete_option( 'emsfb_cron_last_run' );
		} elseif ( file_exists( $flag_file ) ) {
			unlink( $flag_file );
		}
		efb_e2e_out( array( 'cron_disabled' => file_exists( $flag_file ) ) );

	case 'remove':
		$removed = array();
		foreach ( $args as $addon_key ) {
			if ( ! isset( EFB_E2E_FOLDERS[ $addon_key ] ) ) {
				efb_e2e_fail( 'unknown add-on ' . $addon_key );
			}
			$removed[ $addon_key ] = efb_e2e_delete_vendor_folder( EFB_E2E_FOLDERS[ $addon_key ] );
		}
		efb_e2e_out( array( 'removed' => $removed ) );

	case 'simulate-update':
		update_option( 'emsfb_version', '4.1.3' );
		efb_e2e_out( array( 'emsfb_version' => '4.1.3', 'plugin_version' => EMSFB_PLUGIN_VERSION ) );

	case 'due':
		$crons = (array) _get_cron_array();
		$now   = time() - 1;
		$moved = 0;
		foreach ( $crons as $timestamp => $hooks ) {
			if ( $timestamp <= $now || empty( $hooks['emsfb_addon_recovery_event'] ) ) {
				continue;
			}
			foreach ( $hooks['emsfb_addon_recovery_event'] as $signature => $event ) {
				$crons[ $now ]['emsfb_addon_recovery_event'][ $signature ] = $event;
				$moved++;
			}
			unset( $crons[ $timestamp ]['emsfb_addon_recovery_event'] );
			if ( empty( $crons[ $timestamp ] ) ) {
				unset( $crons[ $timestamp ] );
			}
		}
		ksort( $crons );
		_set_cron_array( $crons );
		delete_transient( 'doing_cron' );
		efb_e2e_out( array( 'moved' => $moved, 'pending' => count( efb_e2e_recovery_events() ) ) );

	case 'set-flag':
		if ( ! isset( EFB_E2E_FOLDERS[ $args[0] ?? '' ] ) ) {
			efb_e2e_fail( 'set-flag AdnXXX 0|1' );
		}
		efb_e2e_set_flags( array( $args[0] => (int) ( $args[1] ?? 0 ) ) );
		efb_e2e_out( array( 'flag' => $args[0], 'value' => (int) ( $args[1] ?? 0 ) ) );

	case 'clear-backoff':
		delete_transient( 'emsfb_addons_dl_backoff' );
		delete_transient( 'emsfb_addon_recovery_lock' );
		delete_option( 'emsfb_addons_dl_failures' );
		efb_e2e_out( array( 'backoff' => false ) );

	case 'clear-report-throttle':
		delete_transient( 'emsfb_addons_report_sent' );
		efb_e2e_out( array( 'report_throttled' => false ) );

	case 'clear-logs':
		update_option( 'efb_e2e_http_log', array(), false );
		update_option( 'efb_e2e_mail_log', array(), false );
		if ( is_file( WP_CONTENT_DIR . '/debug.log' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', '' );
		}
		efb_e2e_out( array( 'logs' => 'cleared' ) );

	case 'page':
		$pages = get_option( 'efb_e2e_pages', array() );
		if ( empty( $pages[ $args[0] ?? '' ] ) ) {
			efb_e2e_fail( 'unknown page ' . ( $args[0] ?? '' ) );
		}
		efb_e2e_out( array( 'url' => get_permalink( $pages[ $args[0] ]['page_id'] ) ) );

	default:
		efb_e2e_fail( 'unknown command ' . $command );
}
