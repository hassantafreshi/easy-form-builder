<?php
/**
 * C1 loader, C2 registry, C3 text_efb() merge and C4 admin-only freshness scope.
 *
 * The main process bootstraps WordPress and changes no setting: the two options the
 * loader may write (emsfb_addon_i18n_refetch, emsfb_addon_i18n_refetch_log) are kept
 * in memory with pre_option_/pre_update_option_ filters. Loader fixtures live under
 * temp/addon-i18n/, never the real vendor/.
 *
 * C4 runs in child processes without WordPress (--c4=<scenario>), one per scenario,
 * because recover_missing_addons_efb() caches its result for the whole process.
 *
 * Run: php tests/addon-i18n/test-addon-i18n-loader.php
 */

$efb_plugin = str_replace( '\\', '/', dirname( __DIR__, 2 ) );
$efb_c4     = null;
foreach ( array_slice( $argv, 1 ) as $efb_arg ) {
	if ( 0 === strpos( $efb_arg, '--c4=' ) ) {
		$efb_c4 = substr( $efb_arg, 5 );
	}
}

$passed = 0;
$failed = 0;
function efb_i18n_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}
function efb_i18n_rmtree( $path ) {
	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}
	if ( ! is_dir( $path ) ) {
		return;
	}
	foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
		efb_i18n_rmtree( $path . '/' . $entry );
	}
	rmdir( $path );
}

/* =========================================================================
 * C4 child process: no WordPress.
 * ====================================================================== */
if ( null !== $efb_c4 ) {
	$efb_root = $efb_plugin . '/temp/addon-i18n/loader-c4-' . preg_replace( '/[^a-z_]/', '', $efb_c4 ) . '-' . getmypid();
	define( 'ABSPATH', $efb_root . '/' );
	define( 'EMSFB_PLUGIN_DIRECTORY', $efb_root . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	register_shutdown_function( function () use ( $efb_root ) {
		efb_i18n_rmtree( $efb_root );
	} );

	$GLOBALS['efb_opts']       = array();
	$GLOBALS['efb_transients'] = array();
	$GLOBALS['efb_settings']   = (object) array( 'AdnTLG' => 1, 'emailSupporter' => '' );

	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['efb_opts'] ) ? $GLOBALS['efb_opts'][ $k ] : $d; }
	function update_option( $k, $v ) { $GLOBALS['efb_opts'][ $k ] = $v; return true; }
	function delete_option( $k ) { unset( $GLOBALS['efb_opts'][ $k ] ); return true; }
	function get_transient( $k ) { return isset( $GLOBALS['efb_transients'][ $k ] ) ? $GLOBALS['efb_transients'][ $k ] : false; }
	function set_transient( $k, $v, $t = 0 ) { $GLOBALS['efb_transients'][ $k ] = $v; return true; }
	function delete_transient( $k ) { unset( $GLOBALS['efb_transients'][ $k ] ); return true; }
	function current_time( $t ) { return '2026-09-16 12:00:00'; }
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
	function esc_html__( $t ) { return $t; }
	function __( $t ) { return $t; }
	function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
	function apply_filters( $tag, $value ) { return $value; }
	function add_action() { return true; }
	function get_setting_Emsfb( $mode = 'decoded' ) { return $GLOBALS['efb_settings']; }
	function emsfb_is_php_function_available_efb( $f ) { return function_exists( $f ); }
	function emsfb_is_email_sending_enabled_efb( $s = null ) { return false; }

	require_once $efb_plugin . '/includes/functions.php';

	function efb_c4_put( $relative, $body = "<?php\n" ) {
		$full = EMSFB_PLUGIN_DIRECTORY . $relative;
		if ( ! is_dir( dirname( $full ) ) ) {
			mkdir( dirname( $full ), 0777, true );
		}
		file_put_contents( $full, $body );
	}

	class Efb_I18n_C4_Test extends efbFunction {
		public $server_requests = array();
		public $scope_seen      = array();
		public $force_seen      = array();
		public $deliver         = true;
		public function __construct() {}
		/* Stands in for the add-on server: a delivered package is the new zip, with its translations. */
		public function addon_add_efb( $value ) {
			$this->server_requests[] = $value;
			$this->scope_seen[]      = $this->addon_health_scope_efb;
			$this->force_seen[]      = isset( $this->addon_force_refetch_efb[ $value ] );
			if ( ! $this->deliver ) {
				return array( 'status' => false, 'message' => 'server down' );
			}
			$runtime = $this->get_addon_required_files_efb();
			$i18n    = $this->get_addon_i18n_required_files_efb();
			foreach ( array_merge( $runtime[ $value ], isset( $i18n[ $value ] ) ? $i18n[ $value ] : array() ) as $file ) {
				efb_c4_put( $file, substr( $file, -5 ) === '.json' ? '{"domain":"efb-telegram","locales":["fa_IR","ja"]}' : "<?php\n" );
			}
			return array( 'status' => true, 'message' => 'installed' );
		}
	}

	// Every scenario starts from a site holding the pre-migration telegram package.
	efb_c4_put( 'vendor/telegram/telegram-new-efb.php' );
	efb_c4_put( 'vendor/telegram/class-Emsfb-telegram.php' );
	$fn  = new Efb_I18n_C4_Test();
	$s   = $GLOBALS['efb_settings'];
	$i18n_missing = array( 'AdnTLG' => array( 'vendor/telegram/telegram-phrases-efb.php', 'vendor/telegram/languages/locales.json' ) );

	switch ( $efb_c4 ) {
		case 'health':
			$manifest = $fn->get_addon_required_files_efb();
			efb_i18n_assert( 'C4 runtime manifest keeps only the runtime file', $manifest['AdnTLG'], array( 'vendor/telegram/telegram-new-efb.php' ) );
			$runtime = $fn->get_addon_local_health_efb( $s );
			efb_i18n_assert( 'C4 runtime scope ignores missing translation files', $runtime['missing'], array() );
			efb_i18n_assert( 'C4 runtime scope still checks the add-on', $runtime['checked'], array( 'AdnTLG' ) );
			$admin = $fn->get_addon_local_health_efb( $s, 'admin' );
			efb_i18n_assert( 'C4 admin scope (Create/Panel) detects missing translation files', $admin['missing'], $i18n_missing );
			efb_i18n_assert( 'C4 is_addon_installed_locally_efb() unchanged by translation files', $fn->is_addon_installed_locally_efb( 'AdnTLG' ), true );
			$gate = $fn->check_addons_for_public_request_efb( $s, array( 'AdnTLG' ) );
			efb_i18n_assert( 'C4 public gate: form renders', $gate['success'], true );
			efb_i18n_assert( 'C4 public gate: nothing missing, nothing queued', array( $gate['missing'], $gate['queued'] ), array( array(), false ) );
			$gate_all = $fn->check_addons_for_public_request_efb( $s, null );
			efb_i18n_assert( 'C4 public gate with every add-on required still renders', $gate_all['success'], true );
			$GLOBALS['efb_opts']['emsfb_addons_reinstall_required'] = time();
			efb_i18n_assert( 'C4 Create/Panel state after an update: block', $fn->addon_recovery_state_efb( $s, 'admin' ), 'block' );
			efb_i18n_assert( 'C4 admin state keeps the update flag', isset( $GLOBALS['efb_opts']['emsfb_addons_reinstall_required'] ), true );
			$GLOBALS['efb_opts'] = array();
			efb_i18n_assert( 'C4 Create/Panel state without an update: inline', $fn->addon_recovery_state_efb( $s, 'admin' ), 'inline' );
			$GLOBALS['efb_opts']['emsfb_addons_reinstall_required'] = time();
			efb_i18n_assert( 'C4 Add-ons page / runtime state: none', $fn->addon_recovery_state_efb( $s ), 'none' );
			efb_c4_put( 'vendor/telegram/telegram-phrases-efb.php' );
			efb_c4_put( 'vendor/telegram/languages/locales.json', '{"domain":"efb-telegram","locales":["fa_IR"]}' );
			efb_i18n_assert( 'C4 admin scope clears once the new package is present', $fn->addon_recovery_state_efb( $s, 'admin' ), 'none' );
			$src = file_get_contents( $GLOBALS['efb_plugin'] . '/includes/functions.php' );
			efb_i18n_assert( 'C4 addon_add_efb() re-extracts a package missing its scope files (source)', substr_count( $src, 'addon_scope_files_present_efb( $value )' ), 2 );
			break;

		case 'recover_create':
		case 'recover_panel':
		case 'recover_manual':
			$source = substr( $efb_c4, 8 );
			$r = $fn->recover_missing_addons_efb( $s, $source );
			efb_i18n_assert( "C4 {$source}: stale package counts as missing", $r['initial_missing'], array( 'AdnTLG' ) );
			efb_i18n_assert( "C4 {$source}: downloaded again through the existing installer", $fn->server_requests, array( 'AdnTLG' ) );
			efb_i18n_assert( "C4 {$source}: download ran in admin scope", $fn->scope_seen, array( 'admin' ) );
			efb_i18n_assert( "C4 {$source}: recovered", $r['recovered'], true );
			efb_i18n_assert( "C4 {$source}: scope restored afterwards", $fn->addon_health_scope_efb, 'runtime' );
			break;

		case 'recover_addons':
		case 'recover_public_inline':
		case 'recover_automatic':
			$source = substr( $efb_c4, 8 );
			$r = $fn->recover_missing_addons_efb( $s, $source );
			efb_i18n_assert( "C4 {$source}: translation files are not a reason to recover", $r['needed'], false );
			efb_i18n_assert( "C4 {$source}: no download", $fn->server_requests, array() );
			break;

		case 'background':
			$GLOBALS['efb_opts']['emsfb_addon_recovery_pending'] = array( 'attempt' => 0 );
			$fn->run_addon_recovery_event_efb( array( 'attempt' => 0 ) );
			efb_i18n_assert( 'C4 background runner: no download for translation files', $fn->server_requests, array() );
			break;

		case 'refetch':
			efb_c4_put( 'vendor/telegram/telegram-phrases-efb.php' );
			efb_c4_put( 'vendor/telegram/languages/locales.json', '{"domain":"efb-telegram","locales":["fa_IR","ja"]}' );
			$GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'] = array( 'telegram' => array( 'ja' ) );
			$state_before = $fn->addon_recovery_state_efb( $s, 'admin' );
			$r = $fn->process_addon_i18n_refetch_efb();
			efb_i18n_assert( 'C6 refetch downloads the queued add-on', $fn->server_requests, array( 'AdnTLG' ) );
			efb_i18n_assert( 'C6 refetch forces a download although files exist', $fn->force_seen, array( true ) );
			efb_i18n_assert( 'C6 refetch result', array( $r['attempted'], $r['restored'] ), array( array( 'telegram' ), array( 'telegram' ) ) );
			efb_i18n_assert( 'C6 slug cleared on success', isset( $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'] ), false );
			efb_i18n_assert( 'C6 attempt logged', isset( $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch_log']['telegram|ja'] ), true );
			efb_i18n_assert( 'C6 recovery state unchanged', $fn->addon_recovery_state_efb( $s, 'admin' ), $state_before );
			efb_i18n_assert( 'C6 force flag removed afterwards', $fn->addon_force_refetch_efb, array() );
			efb_i18n_assert( 'C6 no report is emailed (suppression restored)', $fn->suppress_addon_report_efb, false );
			break;

		case 'refetch_backoff':
			$GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'] = array( 'telegram' => array( 'ja' ) );
			foreach ( array( 'emsfb_addons_dl_backoff', 'emsfb_addons_renew_backoff', 'emsfb_addon_i18n_refetch_backoff' ) as $backoff ) {
				$GLOBALS['efb_transients'] = array( $backoff => 1 );
				$r = $fn->process_addon_i18n_refetch_efb();
				efb_i18n_assert( "C6 refetch honours {$backoff}", array( $r['skipped'], $fn->server_requests ), array( 'backoff', array() ) );
			}
			efb_i18n_assert( 'C6 queued slug kept while backing off', $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'], array( 'telegram' => array( 'ja' ) ) );
			break;

		case 'refetch_failure':
			$fn->deliver = false;
			$GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'] = array( 'telegram' => array( 'ja' ) );
			$r = $fn->process_addon_i18n_refetch_efb();
			efb_i18n_assert( 'C6 failed refetch keeps the slug queued', $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'], array( 'telegram' => array( 'ja' ) ) );
			efb_i18n_assert( 'C6 failed refetch waits before the next attempt', isset( $GLOBALS['efb_transients']['emsfb_addon_i18n_refetch_backoff'] ), true );
			$GLOBALS['efb_settings'] = (object) array( 'AdnTLG' => 0 );
			$fn2 = new Efb_I18n_C4_Test();
			$fn2->process_addon_i18n_refetch_efb();
			break;

		case 'flag':
			efb_c4_put( 'vendor/telegram/languages/locales.json', '{"domain":"efb-telegram","locales":["fa_IR","ja","de_DE"]}' );
			efb_c4_put( 'vendor/telegram/languages/efb-telegram-fa_IR.mo', 'x' );
			efb_i18n_assert( 'C6 listed locale with a missing file is queued', emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'ja' ), array( 'telegram' ) );
			efb_i18n_assert( 'C6 queue content', $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'], array( 'telegram' => array( 'ja' ) ) );
			emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'fa_IR' );
			emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'ko_KR' );
			emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'en_US' );
			efb_i18n_assert( 'C6 present, unlisted and English locales are not queued', $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'], array( 'telegram' => array( 'ja' ) ) );
			emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'de_AT' );
			efb_i18n_assert( 'C6 a locale the package does not list is never queued through another locale', $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'], array( 'telegram' => array( 'ja' ) ) );
			$GLOBALS['efb_opts'] = array( 'emsfb_addon_i18n_refetch_log' => array( 'telegram|ja' => time() - 3600 ) );
			emsfb_flag_addon_i18n_refetch_efb( 'telegram', 'ja' );
			efb_i18n_assert( 'C6 a locale refetched in the last 7 days is not queued again', isset( $GLOBALS['efb_opts']['emsfb_addon_i18n_refetch'] ), false );
			efb_i18n_assert( 'C6 an add-on without locales.json is never queued', emsfb_flag_addon_i18n_refetch_efb( 'autofill', 'ja' ), array() );
			break;

		default:
			echo "[FAIL] unknown scenario {$efb_c4}\n";
			$failed++;
	}
	echo "CHILD {$passed} {$failed}\n";
	exit( $failed > 0 ? 1 : 0 );
}

/* =========================================================================
 * Main process: WordPress.
 * ====================================================================== */
define( 'DISABLE_WP_CRON', true );
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
add_filter( 'pre_http_request', function () { return new WP_Error( 'blocked', 'blocked by test-addon-i18n-loader' ); }, 1 );
add_filter( 'pre_wp_mail', '__return_false', 1 );

$GLOBALS['efb_mem'] = array();
foreach ( array( 'emsfb_addon_i18n_refetch', 'emsfb_addon_i18n_refetch_log' ) as $efb_opt ) {
	add_filter( "pre_option_{$efb_opt}", function () use ( $efb_opt ) {
		return array_key_exists( $efb_opt, $GLOBALS['efb_mem'] ) ? $GLOBALS['efb_mem'][ $efb_opt ] : array();
	} );
	add_filter( "pre_update_option_{$efb_opt}", function ( $value, $old ) use ( $efb_opt ) {
		$GLOBALS['efb_mem'][ $efb_opt ] = $value;
		return $old; // equal to the old value: WordPress writes nothing
	}, 10, 2 );
}

$efb_fn = get_efbFunction();

echo "--- C3. a front-end subset never loads add-on files ---\n";
$efb_provider_loaded_early = class_exists( 'Emsfb_Addon_Phrases_Telegram', false );
$efb_subset = $efb_fn->text_efb( array( 'create', 'status', 'test_message' ) );
if ( $efb_provider_loaded_early ) {
	echo "[SKIP] provider was already loaded during bootstrap; subset isolation not observable\n";
} else {
	efb_i18n_assert( 'C3 subset returned the base key', isset( $efb_subset['create'] ), true );
	efb_i18n_assert( 'C3 subset has no add-on keys', array( isset( $efb_subset['status'] ), isset( $efb_subset['test_message'] ) ), array( false, false ) );
	efb_i18n_assert( 'C3 subset did not load the provider file', class_exists( 'Emsfb_Addon_Phrases_Telegram', false ), false );
}

echo "\n--- C1. loader ---\n";
$efb_fixture = $efb_plugin . '/temp/addon-i18n/loader-fixture-' . getmypid();
register_shutdown_function( function () use ( $efb_fixture ) {
	efb_i18n_rmtree( $efb_fixture );
} );
$efb_lang_dir = $efb_fixture . '/i18ntest/languages';
mkdir( $efb_lang_dir, 0777, true );
$efb_fixture_strings = array( 'de_DE' => 'Testnachricht (Fixture de_DE)', 'fa_IR' => 'پیام آزمایشی (fixture fa_IR)' );
foreach ( $efb_fixture_strings as $efb_locale => $efb_msgstr ) {
	$mo = new MO();
	$mo->set_header( 'Language', $efb_locale );
	$mo->add_entry( new Translation_Entry( array( 'singular' => 'Test Message', 'translations' => array( $efb_msgstr ) ) ) );
	$mo->export_to_file( $efb_lang_dir . '/efb-i18ntest-' . $efb_locale . '.mo' );
	file_put_contents( $efb_lang_dir . '/efb-i18ntest-' . $efb_locale . '.l10n.php', WP_Translation_File::transform( $efb_lang_dir . '/efb-i18ntest-' . $efb_locale . '.mo', 'php' ) );
}
file_put_contents( $efb_lang_dir . '/locales.json', json_encode( array( 'domain' => 'efb-i18ntest', 'locales' => array( 'de_DE', 'fa_IR', 'ja' ) ) ) );
add_filter( 'emsfb_addon_languages_dir_efb', function ( $dir, $slug ) use ( $efb_fixture ) {
	return 'i18ntest' === $slug ? $efb_fixture . '/i18ntest/languages/' : $dir;
}, 10, 2 );
$GLOBALS['efb_force_locale'] = null;
add_filter( 'determine_locale', function ( $locale ) {
	return null === $GLOBALS['efb_force_locale'] ? $locale : $GLOBALS['efb_force_locale'];
}, 999 );
// phpcs:disable WordPress.WP.I18n -- the domain is the fixture's.
$GLOBALS['efb_force_locale'] = 'de_DE';
efb_i18n_assert( 'C1 exact locale file loads', emsfb_load_addon_textdomain_efb( 'i18ntest' ), true );
efb_i18n_assert( 'C1 exact locale translates', __( 'Test Message', 'efb-i18ntest' ), $efb_fixture_strings['de_DE'] );
efb_i18n_assert( 'C1 is_textdomain_loaded short-circuit', emsfb_load_addon_textdomain_efb( 'i18ntest' ), true );

unload_textdomain( 'efb-i18ntest', true );
$GLOBALS['efb_force_locale'] = 'de_CH';
efb_i18n_assert( 'C1 a locale with no file of its own never loads another locale', emsfb_load_addon_textdomain_efb( 'i18ntest' ), false );
efb_i18n_assert( 'C1 that locale shows English', __( 'Test Message', 'efb-i18ntest' ), 'Test Message' );
efb_i18n_assert( 'C1 a locale the package does not list queues nothing', isset( $GLOBALS['efb_mem']['emsfb_addon_i18n_refetch'] ) ? $GLOBALS['efb_mem']['emsfb_addon_i18n_refetch'] : array(), array() );

unload_textdomain( 'efb-i18ntest', true );
$GLOBALS['efb_force_locale'] = 'ja';
efb_i18n_assert( 'C1 missing listed file returns false', emsfb_load_addon_textdomain_efb( 'i18ntest' ), false );
efb_i18n_assert( 'C1 missing file shows English', __( 'Test Message', 'efb-i18ntest' ), 'Test Message' );
efb_i18n_assert( 'C1 missing listed file queues a refetch (C6)', isset( $GLOBALS['efb_mem']['emsfb_addon_i18n_refetch'] ) ? $GLOBALS['efb_mem']['emsfb_addon_i18n_refetch'] : null, array( 'i18ntest' => array( 'ja' ) ) );
$GLOBALS['efb_force_locale'] = 'ko_KR';
efb_i18n_assert( 'C1 unlisted locale returns false', emsfb_load_addon_textdomain_efb( 'i18ntest' ), false );
$GLOBALS['efb_force_locale'] = 'en_US';
efb_i18n_assert( 'C1 English returns false', emsfb_load_addon_textdomain_efb( 'i18ntest' ), false );
efb_i18n_assert( 'C1 unlisted and English locales queue nothing', $GLOBALS['efb_mem']['emsfb_addon_i18n_refetch'], array( 'i18ntest' => array( 'ja' ) ) );

$GLOBALS['efb_force_locale'] = 'de_DE';
emsfb_load_addon_textdomain_efb( 'i18ntest' );
efb_i18n_assert( 'C1 loaded before change_locale', is_textdomain_loaded( 'efb-i18ntest' ), true );
$GLOBALS['efb_force_locale'] = 'fa_IR';
do_action( 'change_locale', 'fa_IR' );
efb_i18n_assert( 'C1 change_locale unloads add-on domains', is_textdomain_loaded( 'efb-i18ntest' ), false );
efb_i18n_assert( 'C1 reload after change_locale', emsfb_load_addon_textdomain_efb( 'i18ntest' ), true );
efb_i18n_assert( 'C1 new locale translates', __( 'Test Message', 'efb-i18ntest' ), $efb_fixture_strings['fa_IR'] );

$GLOBALS['efb_force_locale'] = null;
unload_textdomain( 'efb-i18ntest', true );
efb_i18n_assert( 'C1 real switch_to_locale(fa_IR)', switch_to_locale( 'fa_IR' ), true );
emsfb_load_addon_textdomain_efb( 'i18ntest' );
efb_i18n_assert( 'C1 translates after switch_to_locale', __( 'Test Message', 'efb-i18ntest' ), $efb_fixture_strings['fa_IR'] );
restore_previous_locale();
efb_i18n_assert( 'C1 restore_previous_locale unloads the domain', is_textdomain_loaded( 'efb-i18ntest' ), false );
emsfb_load_addon_textdomain_efb( 'i18ntest' );
efb_i18n_assert( 'C1 English again after restore', __( 'Test Message', 'efb-i18ntest' ), 'Test Message' );
// phpcs:enable

echo "\n--- C2. registry ---\n";
// Loads includes/phrases.php: nothing above needed it, by design.
$efb_providers = emsfb_get_addon_i18n_providers_efb();
efb_i18n_assert( 'C2 registry helper loaded EfbAddonPhrases', class_exists( 'EfbAddonPhrases', false ), true );
efb_i18n_assert( 'C2 telegram provider registered', isset( $efb_providers['telegram'] ) ? $efb_providers['telegram'] : null, array( 'group' => 'telegram', 'slug' => 'telegram', 'file' => 'vendor/telegram/telegram-phrases-efb.php', 'class' => 'Emsfb_Addon_Phrases_Telegram' ) );
$efb_i18n_manifest = $efb_fn->get_addon_i18n_required_files_efb();
$efb_agree = true;
foreach ( $efb_i18n_manifest as $efb_key => $efb_files ) {
	$efb_slug = preg_match( '#^vendor/([^/]+)/#', $efb_files[0], $m ) ? $m[1] : '';
	$efb_agree = $efb_agree && isset( $efb_providers[ $efb_slug ] ) && $efb_providers[ $efb_slug ]['file'] === $efb_files[0] && $efb_files[1] === 'vendor/' . $efb_slug . '/languages/locales.json';
}
efb_i18n_assert( 'C2 C4 manifest agrees with the registry', $efb_agree && count( $efb_i18n_manifest ) === count( $efb_providers ), true );
efb_i18n_assert( 'C2 provider file exists in this checkout', file_exists( EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/telegram-phrases-efb.php' ), true );

// The merge loads the provider file lazily; nothing earlier needed it.
$efb_merged         = efb_get_addon_phrases( 'telegram' );
efb_i18n_assert( 'C2 group merge loaded the provider class', class_exists( 'Emsfb_Addon_Phrases_Telegram', false ), true );
$efb_core_group     = EfbAddonPhrases::get_telegram_phrases();
$efb_provider_group = Emsfb_Addon_Phrases_Telegram::group();
$efb_provider_base  = Emsfb_Addon_Phrases_Telegram::base();
efb_i18n_assert( 'C2 group merge = remaining core keys + provider keys', array_keys( $efb_merged ), array_keys( array_merge( $efb_core_group, $efb_provider_group ) ) );
efb_i18n_assert( 'C2 remaining core telegram keys', array_keys( $efb_core_group ), array( 'enabled', 'botfather_link', 'chatid_finder_link', 'date', 'invalidToken', 'unknownError', 'messageSent' ) );
efb_i18n_assert( 'C2 provider supplies 82 keys', count( $efb_provider_group ), 82 );

echo "\n--- C3. merge, collisions, per-locale caches ---\n";
$efb_functions_src = file_get_contents( EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php' );
$efb_base_keys     = array();
foreach ( array( array( 'public function text_efb(', 'public function send_email_state_new(' ), array( 'public function efb_sentence_forms(', "\n\t}" ) ) as $efb_range ) {
	$efb_start = strpos( $efb_functions_src, $efb_range[0] );
	$efb_end   = strpos( $efb_functions_src, $efb_range[1], $efb_start + strlen( $efb_range[0] ) );
	if ( preg_match_all( '/^\s*["\']([A-Za-z0-9_]+)["\']\s*=>/m', substr( $efb_functions_src, $efb_start, $efb_end - $efb_start ), $efb_m ) ) {
		$efb_base_keys = array_merge( $efb_base_keys, $efb_m[1] );
	}
}
efb_i18n_assert( 'C3 parsed the base $lang array', count( $efb_base_keys ) > 1000, true );
$efb_addon_keys = array_merge( array_keys( $efb_provider_group ), array_keys( $efb_provider_base ) );
efb_i18n_assert( 'C3 no add-on key collides with a core base or sentence-form key', array_values( array_intersect( $efb_addon_keys, $efb_base_keys ) ), array() );
efb_i18n_assert( 'C3 no add-on key collides with a remaining core group key', array_values( array_intersect( $efb_addon_keys, array_keys( $efb_core_group ) ) ), array() );

$efb_tg_en = $efb_fn->text_efb( 1, 'telegram' );
efb_i18n_assert( 'C3 text_efb(1, telegram) holds every provider key', array_values( array_diff( array_keys( $efb_provider_group ), array_keys( $efb_tg_en ) ) ), array() );
efb_i18n_assert( 'C3 text_efb(1) without a group has no telegram group key', isset( $efb_fn->text_efb( 1 )['test_message'] ), false );
efb_i18n_assert( 'C3 English status', $efb_tg_en['status'], 'Status' );
efb_i18n_assert( 'C3 add-on signature is set', emsfb_addon_i18n_signature_efb() !== '0' && strlen( emsfb_addon_i18n_signature_efb() ) === 12, true );

switch_to_locale( 'fa_IR' );
$efb_tg_fa    = $efb_fn->text_efb( 1, 'telegram' );
$efb_group_fa = efb_get_addon_phrases( 'telegram' );
efb_i18n_assert( 'C3 same request, fa_IR: text_efb() is not served from the English cache', $efb_tg_fa['test_message'] !== 'Test Message', true );
efb_i18n_assert( 'C3 fa_IR comes from efb-telegram', $efb_tg_fa['status'], 'وضعیت' );
efb_i18n_assert( 'C3 phrase group cache is per locale', $efb_group_fa['test_message'] !== 'Test Message', true );
restore_previous_locale();
efb_i18n_assert( 'C3 back to English after restore', $efb_fn->text_efb( 1, 'telegram' )['test_message'], 'Test Message' );

echo "\n--- C2. partial install never fatals ---\n";
EfbAddonPhrases::register_addon_provider_efb( 'i18ntest_group', 'i18ntest', 'vendor/i18ntest/i18ntest-phrases-efb.php', 'Emsfb_Addon_Phrases_I18ntest' );
efb_i18n_assert( 'C2 missing provider file: group returns core phrases only', efb_get_addon_phrases( 'i18ntest_group' ), array() );
efb_i18n_assert( 'C2 missing provider file: base merge unaffected', EfbAddonPhrases::get_addon_base_phrases_efb(), array() );

echo "\n--- C4 / C6. child processes (no WordPress) ---\n";
foreach ( array( 'health', 'recover_create', 'recover_panel', 'recover_manual', 'recover_addons', 'recover_public_inline', 'recover_automatic', 'background', 'refetch', 'refetch_backoff', 'refetch_failure', 'flag' ) as $efb_scenario ) {
	$efb_out = array();
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --c4=' . $efb_scenario . ' 2>&1', $efb_out, $efb_code );
	$efb_child_done = false;
	foreach ( $efb_out as $efb_line ) {
		if ( 0 === strpos( $efb_line, '[PASS]' ) ) {
			echo $efb_line . "\n";
			$passed++;
		} elseif ( 0 === strpos( $efb_line, '[FAIL]' ) ) {
			echo $efb_line . "\n";
			$failed++;
		} elseif ( 0 === strpos( $efb_line, 'CHILD ' ) ) {
			$efb_child_done = true;
		} elseif ( '' !== trim( $efb_line ) ) {
			echo "[FAIL] {$efb_scenario}: unexpected output: {$efb_line}\n";
			$failed++;
		}
	}
	if ( ! $efb_child_done ) {
		echo "[FAIL] {$efb_scenario}: child did not finish (exit {$efb_code})\n";
		$failed++;
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
