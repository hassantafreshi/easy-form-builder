<?php
/**
 * Control commands for the language-pack end-to-end site (wp-test-ai) built by setup.php.
 *
 * Every command prints one JSON document, so run.js can call it between steps.
 *
 *   reset <locale> [scenario-json]   a customer site that never saw a translation: site language = locale,
 *                                    the plugin's packs, options and logs cleared, the scenario written
 *                                    to wptai-scenario.json ({"server": url, "constants": {NAME: value}})
 *   state                            pack files on disk, plugin state and runtime, mails, language-pack HTTP log
 *   sync                             what WP-Cron would do: Language_Packs::sync('manual'), summary printed
 *   outage <days>                    as if the language server had been unreachable for <days> days (2 failures so far)
 *   editor                           make sure a user "editor" exists (password editor), for the capability check
 *   can <capability>                 whether the administrator has it under the current scenario
 *   page                             URL of the page with a form
 *
 * Run: C:\xampp\php\php.exe tests/e2e-language-packs/ctl.php <command> [args]
 */

$wptai_site = rtrim( strtr( getenv( 'WPTAI_SITE_DIR' ) ? getenv( 'WPTAI_SITE_DIR' ) : 'C:/xampp/htdocs/wp-test-ai', chr( 92 ), '/' ), '/' );
$command    = isset( $argv[1] ) ? $argv[1] : 'state';
$args       = array_slice( $argv, 2 );

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/wp-test-ai/';

require $wptai_site . '/wp-load.php';

function wptai_out( $data ) {
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

function wptai_packs() {
	return \Emsfb\Language_Packs::instance( 'emsfb_language_packs' );
}

function wptai_pack_files() {
	$files = array();
	foreach ( (array) glob( WP_CONTENT_DIR . '/languages/plugins/easy-form-builder-*' ) as $file ) {
		$files[] = array(
			'name' => basename( $file ),
			'size' => (int) filesize( $file ),
		);
	}
	return $files;
}

if ( 'reset' === $command ) {
	$locale   = isset( $args[0] ) ? $args[0] : 'ru_RU';
	$scenario = isset( $args[1] ) ? json_decode( $args[1], true ) : array();
	$scenario = is_array( $scenario ) ? $scenario : array();

	$removed = 0;
	foreach ( wptai_pack_files() as $file ) {
		if ( @unlink( WP_CONTENT_DIR . '/languages/plugins/' . $file['name'] ) ) {
			$removed++;
		}
	}
	@unlink( WP_CONTENT_DIR . '/wptai-blockfile' );

	// WordPress core language packs: only the one under test is installed. The plugin serves every language
	// installed on the site, so a leftover would be installed too and blur what a scenario shows.
	$lang_dir = WP_CONTENT_DIR . '/languages';
	$stash    = WP_CONTENT_DIR . '/wptai-core-languages';
	if ( ! is_dir( $stash ) ) {
		mkdir( $stash, 0777, true );
	}
	foreach ( (array) glob( $lang_dir . '/*' ) as $file ) {
		if ( is_file( $file ) ) {
			// Anything that is not in the stash yet is kept there before it is removed.
			if ( ! is_file( $stash . '/' . basename( $file ) ) ) {
				copy( $file, $stash . '/' . basename( $file ) );
			}
			unlink( $file );
		}
	}
	if ( 'en_US' !== $locale ) {
		foreach ( (array) glob( $stash . '/*' ) as $file ) {
			if ( preg_match( '/^(admin-|admin-network-|continents-cities-)?' . preg_quote( $locale, '/' ) . '[.-]/', basename( $file ) ) ) {
				copy( $file, $lang_dir . '/' . basename( $file ) );
			}
		}
	}

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'emsfb\\_language\\_packs%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_emsfb\\_language%' OR option_name LIKE '\\_transient\\_timeout\\_emsfb\\_language%'" );
	wp_cache_flush();

	update_option( 'WPLANG', 'en_US' === $locale ? '' : $locale );
	delete_user_meta( 1, 'locale' );
	update_option( 'emsfb_text_version', time() );
	update_option( 'wptai_mail_log', array(), false );
	update_option( 'wptai_http_log', array(), false );

	// A file that stands where a folder should be: the languages folder cannot be created.
	if ( isset( $scenario['constants']['WP_LANG_DIR'] ) ) {
		file_put_contents( WP_CONTENT_DIR . '/wptai-blockfile', 'not a folder' );
	}
	file_put_contents( ABSPATH . 'wptai-scenario.json', wp_json_encode( $scenario, JSON_UNESCAPED_SLASHES ) );

	wptai_out( array( 'reset' => $locale, 'removed_files' => $removed, 'scenario' => $scenario ) );
}

if ( 'state' === $command ) {
	$state = get_option( 'emsfb_language_packs_state', array() );
	wptai_out( array(
		'wplang'   => get_option( 'WPLANG' ),
		'scenario' => json_decode( (string) @file_get_contents( ABSPATH . 'wptai-scenario.json' ), true ),
		'files'    => wptai_pack_files(),
		'runtime'  => get_option( 'emsfb_language_packs_runtime', array() ),
		'lock'     => get_option( 'emsfb_language_packs_lock', null ),
		'ws'       => isset( $state['ws'] ) ? $state['ws'] : null,
		'wporg'    => isset( $state['wporg'] ) ? $state['wporg'] : null,
		'notice'   => isset( $state['notice'] ) ? $state['notice'] : null,
		'packs'    => isset( $state['packs'] ) ? $state['packs'] : null,
		'last_run' => isset( $state['last_run'] ) ? $state['last_run'] : null,
		'mails'    => array_values( (array) get_option( 'wptai_mail_log', array() ) ),
		'http'     => array_values( (array) get_option( 'wptai_http_log', array() ) ),
		'text_version' => get_option( 'emsfb_text_version' ),
	) );
}

if ( 'sync' === $command ) {
	$summary = wptai_packs()->sync( 'manual' );
	wptai_out( array( 'summary' => $summary ) );
}

if ( 'outage' === $command ) {
	$days  = isset( $args[0] ) ? (float) $args[0] : 4;
	$state = get_option( 'emsfb_language_packs_state', array() );
	$state = is_array( $state ) ? $state : array();
	$state['ws'] = array_merge(
		isset( $state['ws'] ) && is_array( $state['ws'] ) ? $state['ws'] : array(),
		array(
			'status'           => 'unreachable',
			'failures'         => 2,
			'first_failure_at' => time() - (int) ( $days * 86400 ),
			'last_attempt_at'  => time() - 3600,
			'last_error'       => 'cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received',
		)
	);
	$state['notice'] = array_merge(
		isset( $state['notice'] ) && is_array( $state['notice'] ) ? $state['notice'] : array(),
		array( 'episode_sent_at' => 0, 'episode_attempts' => 0, 'last_sent_at' => 0 )
	);
	update_option( 'emsfb_language_packs_state', $state, false );
	delete_option( 'emsfb_language_packs_lock' );
	wptai_out( array( 'outage_days' => $days, 'ws' => $state['ws'] ) );
}

if ( 'editor' === $command ) {
	if ( ! username_exists( 'editor' ) ) {
		wp_insert_user( array( 'user_login' => 'editor', 'user_pass' => 'editor', 'user_email' => 'editor@wp-test-ai.test', 'role' => 'editor' ) );
	}
	wptai_out( array( 'editor' => true ) );
}

if ( 'can' === $command ) {
	$cap = isset( $args[0] ) ? $args[0] : 'install_languages';
	wptai_out( array( 'cap' => $cap, 'administrator_has_it' => user_can( 1, $cap ) ) );
}

if ( 'page' === $command ) {
	$page = get_option( 'wptai_form_page', array() );
	wptai_out( $page );
}

wptai_out( array( 'error' => 'unknown command ' . $command ) );
