<?php
/**
 * Tests for runtime language packs.
 *
 *   Client (this plugin)   - includes/class-Emsfb-language-packs.php
 *   Server (WhiteStudio)   - the ws-language-plugins-handler plugin
 *
 * Part one drives the client against a mocked WhiteStudio server and a mocked
 * wordpress.org through pre_http_request: which languages are chosen, where a
 * pack comes from, what is never replaced, what counts as "cannot connect",
 * and when the admin is emailed. Packs are installed into a temporary folder,
 * never into this site's wp-content/languages.
 *
 * Part two, only when the local WhiteStudio site answers, installs a real pack
 * through the real server plugin over HTTP. It publishes a throwaway pack into
 * the server's storage and removes it again.
 *
 * Every option, event, filter and file this test creates is removed at the
 * end, pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests/test-language-packs.php
 *
 * Part two reads EFB_LP_WS_URL (default http://127.0.0.1/ws) and
 * EFB_LP_WS_STORAGE (default <htdocs>/ws/source/files/lang/).
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . WPINC . '/pomo/po.php';

if ( ! class_exists( '\Emsfb\Language_Packs' ) ) {
	require_once dirname( __DIR__ ) . '/includes/class-Emsfb-language-packs.php';
}

$pass     = 0;
$fail     = 0;
$failures = array();

/**
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

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const EFB_LP_PREFIX = 'emsfb_lp_test';
const EFB_LP_WS     = 'https://lang.example.test';
const EFB_LP_PROBE  = 'EFB language pack probe';

$efb_lp_root = trailingslashit( get_temp_dir() ) . 'efb-language-packs-test-' . wp_generate_password( 8, false, false ) . '/';

$efb_lp = array(
	'config'      => array(
		'product'           => 'easy-form-builder',
		'text_domain'       => 'easy-form-builder',
		'wporg_slug'        => 'easy-form-builder',
		'version'           => '4.2.0',
		'plugin_file'       => defined( 'EMSFB_PLUGIN_FILE' ) ? EMSFB_PLUGIN_FILE : dirname( __DIR__ ) . '/emsfb.php',
		'plugin_name'       => 'Easy Form Builder',
		'prefix'            => EFB_LP_PREFIX,
		'servers'           => array( EFB_LP_WS ),
		'bundled_locales'   => array( 'fa_IR', 'ar', 'en_US', 'de_DE' ),
		'bundled_dir'       => $efb_lp_root . 'bundled/',
		'languages_dir'     => $efb_lp_root . 'languages/plugins/',
		'guide_url'         => 'https://example.test/manual-install-guide',
		'notify'            => true,
	),
	'locales'     => array(),
	'ws_mode'     => 'ok',
	'ws_packs'    => array(),
	'wporg_mode'  => 'ok',
	'wporg_packs' => array(),
	'http'        => array(),
	'mail'        => array(),
);

wp_mkdir_p( $efb_lp['config']['bundled_dir'] );
wp_mkdir_p( $efb_lp['config']['languages_dir'] );

/**
 * A .po and .mo holding one translated probe string.
 *
 * @param string $locale      Locale.
 * @param string $translation Translation of the probe string.
 * @param string $revision    PO-Revision-Date.
 * @param string $generator   X-Generator, '' for none.
 * @return array{po: string, mo: string}
 */
function efb_lp_po_mo( $locale, $translation, $revision, $generator ) {
	$headers = array(
		'Project-Id-Version'        => 'Easy Form Builder 4.2.0',
		'PO-Revision-Date'          => $revision,
		'MIME-Version'              => '1.0',
		'Content-Type'              => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'Plural-Forms'              => 'nplurals=2; plural=n != 1;',
		'Language'                  => $locale,
	);
	if ( '' !== $generator ) {
		$headers['X-Generator'] = $generator;
	}
	$entry = new Translation_Entry(
		array(
			'singular'     => EFB_LP_PROBE,
			'translations' => array( $translation ),
		)
	);

	$po = new PO();
	$po->set_headers( $headers );
	$po->add_entry( $entry );

	$mo = new MO();
	$mo->set_headers( $headers );
	$mo->add_entry( $entry );
	$tmp = wp_tempnam( 'efb-lp-mo' );
	$mo->export_to_file( $tmp );
	$mo_bytes = file_get_contents( $tmp );
	unlink( $tmp );

	return array(
		'po' => $po->export(),
		'mo' => $mo_bytes,
	);
}

/**
 * @param array<string, string> $files Entry name => contents.
 * @return string Zip bytes.
 */
function efb_lp_zip( array $files ) {
	$tmp = wp_tempnam( 'efb-lp-zip' );
	$zip = new ZipArchive();
	$zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $files as $name => $contents ) {
		$zip->addFromString( $name, $contents );
	}
	$zip->close();
	$bytes = file_get_contents( $tmp );
	unlink( $tmp );
	return $bytes;
}

/**
 * @param string $locale      Locale.
 * @param string $translation Probe translation.
 * @param string $updated     Y-m-d H:i:s (UTC).
 * @param string $generator   X-Generator of the .po.
 * @param array  $extra       More zip entries.
 * @return array{zip: string, updated: string}
 */
function efb_lp_pack( $locale, $translation, $updated, $generator = 'GlotPress/4.1.0', array $extra = array() ) {
	$files = efb_lp_po_mo( $locale, $translation, $updated . '+0000', $generator );
	return array(
		'zip'     => efb_lp_zip(
			array_merge(
				array(
					'easy-form-builder-' . $locale . '.po' => $files['po'],
					'easy-form-builder-' . $locale . '.mo' => $files['mo'],
				),
				$extra
			)
		),
		'updated' => $updated,
	);
}

/**
 * @param int    $code    Status.
 * @param string $body    Body.
 * @param array  $headers Lower-case headers.
 * @param array  $args    Request arguments (a streamed request gets its body in a file).
 * @return array
 */
function efb_lp_response( $code, $body, array $headers, array $args ) {
	if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
		file_put_contents( $args['filename'], $body );
		$body = '';
	}
	return array(
		'headers'  => $headers,
		'body'     => $body,
		'response' => array(
			'code'    => $code,
			'message' => '',
		),
		'cookies'  => array(),
		'filename' => isset( $args['filename'] ) ? $args['filename'] : null,
	);
}

/**
 * @return string
 */
function efb_lp_ws_catalog_body() {
	global $efb_lp;
	$translations = array();
	foreach ( $efb_lp['ws_packs'] as $locale => $pack ) {
		$translations[] = array_merge(
			array(
				'type'     => 'plugin',
				'slug'     => 'easy-form-builder',
				'language' => $locale,
				'version'  => '4.2.0',
				'updated'  => $pack['updated'],
				'package'  => EFB_LP_WS . '/wp-json/wl/v1/translations/easy-form-builder/package/' . $locale . '?fp=' . md5( $pack['zip'] ),
				'sha256'   => hash( 'sha256', $pack['zip'] ),
				'size'     => strlen( $pack['zip'] ),
			),
			isset( $pack['override'] ) ? $pack['override'] : array()
		);
	}
	return wp_json_encode(
		array(
			'product'      => 'easy-form-builder',
			'known'        => true,
			'translations' => $translations,
		)
	);
}

/**
 * @return string
 */
function efb_lp_wporg_catalog_body() {
	global $efb_lp;
	$translations = array();
	foreach ( $efb_lp['wporg_packs'] as $locale => $pack ) {
		$translations[] = array_merge(
			array(
				'language'     => $locale,
				'version'      => '4.1.3',
				'updated'      => $pack['updated'],
				'english_name' => $locale,
				'native_name'  => $locale,
				'package'      => 'https://downloads.wordpress.org/translation/plugin/easy-form-builder/4.1.3/' . $locale . '.zip',
				'iso'          => array( '1' => substr( $locale, 0, 2 ) ),
			),
			isset( $pack['override'] ) ? $pack['override'] : array()
		);
	}
	return wp_json_encode( array( 'translations' => $translations ) );
}

/**
 * pre_http_request: the mocked WhiteStudio server and wordpress.org.
 *
 * @param mixed  $pre  Short-circuit value.
 * @param array  $args Request arguments.
 * @param string $url  URL.
 * @return array|WP_Error
 */
function efb_lp_http( $pre, $args, $url ) {
	global $efb_lp;
	$efb_lp['http'][] = $url;

	$ours    = array( 'x-ws-language-handler' => '1.0.0' );
	$catalog = EFB_LP_WS . '/wp-json/wl/v1/translations/easy-form-builder';

	if ( 0 === strpos( $url, $catalog . '/package/' ) ) {
		$locale = explode( '?', substr( $url, strlen( $catalog . '/package/' ) ) )[0];
		if ( 'intercept' === $efb_lp['ws_mode'] ) {
			return efb_lp_response( 403, '<html>Blocked by the hosting firewall</html>', array(), $args );
		}
		if ( isset( $efb_lp['ws_packs'][ $locale ] ) ) {
			return efb_lp_response( 200, $efb_lp['ws_packs'][ $locale ]['zip'], $ours, $args );
		}
		return efb_lp_response( 404, '{"code":"wslph_not_found"}', $ours, $args );
	}

	if ( 0 === strpos( $url, $catalog . '?' ) ) {
		switch ( $efb_lp['ws_mode'] ) {
			case 'transport':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out after 10001 milliseconds' );
			case 'html403':
				return efb_lp_response( 403, '<html>Attention Required! | Cloudflare</html>', array( 'content-type' => 'text/html' ), $args );
			case 'rest404':
				return efb_lp_response( 404, '{"code":"rest_no_route","message":"No route was found matching the URL and request method.","data":{"status":404}}', array( 'content-type' => 'application/json' ), $args );
			case 'server500':
				return efb_lp_response( 500, '{"code":"internal_server_error"}', $ours, $args );
		}
		return efb_lp_response( 200, efb_lp_ws_catalog_body(), $ours, $args );
	}

	if ( 0 === strpos( $url, 'https://api.wordpress.org/translations/plugins/1.0/' ) ) {
		if ( 'transport' === $efb_lp['wporg_mode'] ) {
			return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: api.wordpress.org' );
		}
		return efb_lp_response( 200, efb_lp_wporg_catalog_body(), array(), $args );
	}

	if ( 0 === strpos( $url, 'https://downloads.wordpress.org/translation/plugin/easy-form-builder/' ) ) {
		$locale = basename( explode( '?', $url )[0], '.zip' );
		if ( isset( $efb_lp['wporg_packs'][ $locale ] ) ) {
			return efb_lp_response( 200, $efb_lp['wporg_packs'][ $locale ]['zip'], array(), $args );
		}
		return efb_lp_response( 404, 'Not Found', array(), $args );
	}

	return new WP_Error( 'http_request_failed', 'test: unexpected request to ' . $url );
}

/**
 * @return \Emsfb\Language_Packs
 */
function efb_lp_instance() {
	global $efb_lp;
	return new \Emsfb\Language_Packs( $efb_lp['config'] );
}

/**
 * @return array Sync summary.
 */
function efb_lp_sync() {
	global $efb_lp;
	$efb_lp['http'] = array();
	return efb_lp_instance()->sync( 'manual' );
}

/**
 * @return array
 */
function efb_lp_state() {
	return efb_lp_instance()->get_state();
}

/**
 * @param array $changes Top-level key => array merged into the stored state.
 * @return void
 */
function efb_lp_patch_state( array $changes ) {
	$state = efb_lp_state();
	foreach ( $changes as $key => $value ) {
		$state[ $key ] = is_array( $value ) && is_array( $state[ $key ] ) ? array_merge( $state[ $key ], $value ) : $value;
	}
	update_option( EFB_LP_PREFIX . '_state', $state, false );
}

/**
 * Translate the probe with a file installed in the test folder.
 *
 * @param string $locale Locale.
 * @param string $format php|mo - which file WordPress should use.
 * @return string|null
 */
function efb_lp_translation( $locale, $format = 'php' ) {
	global $efb_lp;
	$domain = 'efb-lp-probe-' . md5( $locale . $format . microtime() );
	$prefer = function () use ( $format ) {
		return $format;
	};
	add_filter( 'translation_file_format', $prefer );
	$loaded = load_textdomain( $domain, $efb_lp['config']['languages_dir'] . 'easy-form-builder-' . $locale . '.mo', $locale );
	remove_filter( 'translation_file_format', $prefer );
	$text = $loaded ? translate( EFB_LP_PROBE, $domain ) : null;
	unload_textdomain( $domain );
	WP_Translation_Controller::get_instance()->set_locale( determine_locale() );
	return $text;
}

/**
 * @param string $locale Locale.
 * @param string $ext    po|mo|l10n.php.
 * @return string
 */
function efb_lp_file( $locale, $ext ) {
	global $efb_lp;
	return $efb_lp['config']['languages_dir'] . 'easy-form-builder-' . $locale . '.' . $ext;
}

/**
 * @param string[] $urls   Requested URLs.
 * @param string   $needle Part of a URL.
 * @return int
 */
function efb_lp_count_requests( array $urls, $needle ) {
	return count(
		array_filter(
			$urls,
			function ( $url ) use ( $needle ) {
				return false !== strpos( $url, $needle );
			}
		)
	);
}

/**
 * @param string $dir Directory.
 * @return void
 */
function efb_lp_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) scandir( $dir ) as $name ) {
		if ( '.' === $name || '..' === $name ) {
			continue;
		}
		$path = rtrim( $dir, '/\\' ) . '/' . $name;
		is_dir( $path ) ? efb_lp_rmdir( $path ) : unlink( $path );
	}
	rmdir( $dir );
}

/**
 * @return void
 */
function efb_lp_clear_events() {
	foreach ( array( 'activation', 'version', 'locales', 'retry', 'continue', 'manual' ) as $reason ) {
		wp_clear_scheduled_hook( EFB_LP_PREFIX . '_sync', array( $reason ) );
	}
	wp_clear_scheduled_hook( EFB_LP_PREFIX . '_daily' );
}

// Isolation: this site's mail capture and any other pre_wp_mail filter stay out of it.
global $wp_filter;
$efb_lp_saved_mail_filters = isset( $wp_filter['pre_wp_mail'] ) ? clone $wp_filter['pre_wp_mail'] : null;
remove_all_filters( 'pre_wp_mail' );

$efb_lp_filters = array(
	array( 'pre_http_request', 'efb_lp_http', 10, 3 ),
	array(
		'pre_wp_mail',
		function ( $null, $atts ) {
			$GLOBALS['efb_lp']['mail'][] = $atts;
			return true;
		},
		1,
		2,
	),
	array(
		EFB_LP_PREFIX . '_locales',
		function () {
			return $GLOBALS['efb_lp']['locales'];
		},
		10,
		1,
	),
	array(
		'pre_option_admin_email',
		function () {
			return 'site-admin@example.test';
		},
		10,
		1,
	),
);
foreach ( $efb_lp_filters as $filter ) {
	add_filter( $filter[0], $filter[1], $filter[2], $filter[3] );
}

try {

	// -----------------------------------------------------------------------
	echo "\nConfiguration\n";
	// -----------------------------------------------------------------------

	efb_t( 'boot() with an empty configuration returns null instead of throwing', null === \Emsfb\Language_Packs::boot( array() ) );
	efb_t( 'boot() without a server returns null', null === \Emsfb\Language_Packs::boot( array_merge( $efb_lp['config'], array( 'servers' => array( 'not a url' ) ) ) ) );

	$lp = efb_lp_instance();
	foreach ( array( 'fa_IR', 'ar', 'en_US', 'de_DE' ) as $locale ) {
		efb_t( "{$locale} is shipped with the plugin", $lp->is_bundled_locale( $locale ) );
	}
	foreach ( array( 'fa_AF', 'ary', 'en_GB', 'en_AU', 'de_AT', 'de_DE_formal', 'de_CH_informal', 'fr_FR' ) as $locale ) {
		efb_t( "{$locale} is downloaded, not shipped", ! $lp->is_bundled_locale( $locale ) );
	}

	// -----------------------------------------------------------------------
	echo "\nWhich languages\n";
	// -----------------------------------------------------------------------

	$efb_lp['locales'] = array( 'fa_IR', 'ar', 'en_US', 'de_DE', 'en_GB', 'de_AT', 'fr_FR', 'de_AT', 'not a locale', 'ary', 'fa_AF', 'de_DE_formal', 'DE_de', '../x' );
	efb_t(
		'every used locale is kept once, without the four shipped locales or malformed ones',
		array( 'ary', 'de_AT', 'de_DE_formal', 'en_GB', 'fa_AF', 'fr_FR' ) === efb_lp_instance()->target_locales(),
		wp_json_encode( efb_lp_instance()->target_locales() )
	);

	$efb_lp['locales'] = array( 'fa_IR', 'ar', 'en_US', 'de_DE' );
	$summary           = efb_lp_sync();
	efb_t( 'a site using only fa_IR, ar, en_US and de_DE has nothing to download', 'no_locales' === $summary['status'], $summary['status'] );
	efb_t( '...and makes no request at all', 0 === count( $efb_lp['http'] ), wp_json_encode( $efb_lp['http'] ) );

	$efb_lp['locales'] = array( 'de_AT' );
	add_filter( EFB_LP_PREFIX . '_enabled', '__return_false' );
	$summary = efb_lp_sync();
	remove_filter( EFB_LP_PREFIX . '_enabled', '__return_false' );
	efb_t( 'the enabled filter turns every download off', 'disabled' === $summary['status'] && 0 === count( $efb_lp['http'] ) );

	add_filter( 'file_mod_allowed', '__return_false' );
	$summary = efb_lp_sync();
	remove_filter( 'file_mod_allowed', '__return_false' );
	efb_t( 'with file changes disallowed nothing is requested or written', 'file_mods_disallowed' === $summary['status'] && 0 === count( $efb_lp['http'] ), $summary['status'] );

	add_option( EFB_LP_PREFIX . '_lock', time(), '', false );
	$summary = efb_lp_sync();
	efb_t( 'a run holding the lock keeps a second run out', 'locked' === $summary['status'] && 0 === count( $efb_lp['http'] ) );
	update_option( EFB_LP_PREFIX . '_lock', time() - 3600, false );
	$efb_lp['locales'] = array( 'fa_IR' );
	$summary           = efb_lp_sync();
	efb_t( 'a lock left behind by a run that died expires', 'no_locales' === $summary['status'], $summary['status'] );
	efb_t( '...and is released again', false === get_option( EFB_LP_PREFIX . '_lock' ) );

	// -----------------------------------------------------------------------
	echo "\nWhiteStudio first, wordpress.org second\n";
	// -----------------------------------------------------------------------

	$efb_lp['locales']     = array( 'de_AT', 'fr_FR' );
	$efb_lp['ws_packs']    = array( 'de_AT' => efb_lp_pack( 'de_AT', 'Hallo v1', '2026-09-01 10:00:00' ) );
	$efb_lp['wporg_packs'] = array(
		'fr_FR' => efb_lp_pack( 'fr_FR', 'Bonjour', '2026-08-01 10:00:00' ),
		'de_AT' => efb_lp_pack( 'de_AT', 'Hallo wordpress.org', '2026-09-15 10:00:00' ),
	);

	$summary = efb_lp_sync();
	efb_t( 'first run completes', 'complete' === $summary['status'] && 'ok' === $summary['ws'], wp_json_encode( $summary ) );
	efb_t( 'de_AT comes from WhiteStudio even though wordpress.org has a newer one', 'installed_ws' === $summary['results']['de_AT'] );
	efb_t( 'fr_FR, which WhiteStudio does not have, comes from wordpress.org', 'installed_wporg' === $summary['results']['fr_FR'] );
	efb_t( 'the WhiteStudio .po carries the WhiteStudio marker', 0 === strpos( wp_get_pomo_file_data( efb_lp_file( 'de_AT', 'po' ) )['X-Generator'], \Emsfb\Language_Packs::MARKER ) );
	efb_t( 'the wordpress.org .po keeps its GlotPress marker', 0 === strpos( wp_get_pomo_file_data( efb_lp_file( 'fr_FR', 'po' ) )['X-Generator'], 'GlotPress' ) );
	efb_t( 'a .l10n.php is generated for each pack', is_file( efb_lp_file( 'de_AT', 'l10n.php' ) ) && is_file( efb_lp_file( 'fr_FR', 'l10n.php' ) ) );
	efb_t( 'WordPress loads the WhiteStudio translation (PHP file)', 'Hallo v1' === efb_lp_translation( 'de_AT' ), (string) efb_lp_translation( 'de_AT' ) );
	efb_t( 'WordPress loads the WhiteStudio translation (MO file)', 'Hallo v1' === efb_lp_translation( 'de_AT', 'mo' ) );
	efb_t( 'WordPress loads the wordpress.org translation', 'Bonjour' === efb_lp_translation( 'fr_FR' ) );
	$lp = efb_lp_instance();
	efb_t( 'installed packs are recognised by origin', 'ws' === $lp->inspect_installed( 'de_AT' )['source'] && 'wporg' === $lp->inspect_installed( 'fr_FR' )['source'] );
	efb_t( 'the WhiteStudio install is recorded with its checksum', hash( 'sha256', $efb_lp['ws_packs']['de_AT']['zip'] ) === efb_lp_state()['packs']['de_AT']['sha256'] );
	efb_t( 'no temporary files are left in the languages folder', array() === glob( $efb_lp['config']['languages_dir'] . '*.tmp' ) );

	$summary = efb_lp_sync();
	efb_t( 'a second run finds everything up to date', 'up_to_date_ws' === $summary['results']['de_AT'] && 'up_to_date_wporg' === $summary['results']['fr_FR'], wp_json_encode( $summary['results'] ) );
	efb_t( '...and downloads nothing', 0 === efb_lp_count_requests( $efb_lp['http'], '/package/' ) + efb_lp_count_requests( $efb_lp['http'], 'downloads.wordpress.org' ) );

	$efb_lp['ws_packs']['de_AT'] = efb_lp_pack( 'de_AT', 'Hallo v2', '2026-09-10 10:00:00' );
	$summary                     = efb_lp_sync();
	efb_t( 'a new WhiteStudio build replaces the installed one', 'installed_ws' === $summary['results']['de_AT'] && 'Hallo v2' === efb_lp_translation( 'de_AT' ) );

	$efb_lp['ws_packs']['de_AT'] = efb_lp_pack(
		'de_AT',
		'Hallo v3',
		'2026-09-11 10:00:00',
		'GlotPress/4.1.0',
		array(
			'easy-form-builder-de_AT.l10n.php' => '<?php return array( "messages" => array( "' . EFB_LP_PROBE . '" => "PWNED" ) );',
			'evil.php'                         => '<?php echo "pwned";',
			'../escape.php'                    => '<?php echo "pwned";',
			'sub/easy-form-builder-de_AT.mo'   => 'not an mo',
			'easy-form-builder-de_AT-0123456789abcdef0123456789abcdef.json' => '{"locale_data":{"messages":{"":{"domain":"messages"},"x":["</script><script>alert(1)</script>"]}}}',
			'easy-form-builder-de_AT-fedcba9876543210fedcba9876543210.json' => 'not json',
		)
	);
	$summary = efb_lp_sync();
	efb_t( 'a pack carrying PHP and path tricks still installs its translation', 'installed_ws' === $summary['results']['de_AT'] && 'Hallo v3' === efb_lp_translation( 'de_AT' ), (string) efb_lp_translation( 'de_AT' ) );
	efb_t( '...the .l10n.php from the download is never used', false === strpos( (string) file_get_contents( efb_lp_file( 'de_AT', 'l10n.php' ) ), 'PWNED' ) );
	efb_t( '...no other file is written anywhere', ! file_exists( $efb_lp['config']['languages_dir'] . 'evil.php' ) && ! file_exists( dirname( $efb_lp['config']['languages_dir'] ) . '/escape.php' ) && ! is_dir( $efb_lp['config']['languages_dir'] . 'sub' ) );
	$json = (string) @file_get_contents( $efb_lp['config']['languages_dir'] . 'easy-form-builder-de_AT-0123456789abcdef0123456789abcdef.json' );
	efb_t( '...a script translation is re-encoded, so it cannot close a script tag', '' !== $json && false === strpos( $json, '</script>' ) && is_array( json_decode( $json, true ) ) );
	efb_t( '...a broken script translation is dropped', ! file_exists( $efb_lp['config']['languages_dir'] . 'easy-form-builder-de_AT-fedcba9876543210fedcba9876543210.json' ) );

	$installed_v3                            = $efb_lp['ws_packs']['de_AT'];
	$before                                  = hash_file( 'sha256', efb_lp_file( 'de_AT', 'mo' ) );
	$efb_lp['ws_packs']['de_AT']             = efb_lp_pack( 'de_AT', 'Hallo v4', '2026-09-12 10:00:00' );
	$efb_lp['ws_packs']['de_AT']['override'] = array( 'sha256' => str_repeat( 'a', 64 ) );
	$summary                                 = efb_lp_sync();
	efb_t( 'a download that does not match its checksum is refused', 'failed_ws:checksum_mismatch' === $summary['results']['de_AT'], (string) $summary['results']['de_AT'] );
	efb_t( '...and the installed translation is untouched', $before === hash_file( 'sha256', efb_lp_file( 'de_AT', 'mo' ) ) && 'Hallo v3' === efb_lp_translation( 'de_AT' ) );
	$efb_lp['ws_packs']['de_AT'] = $installed_v3;

	$efb_lp['ws_packs']['fr_FR'] = efb_lp_pack( 'fr_FR', 'Bonjour WhiteStudio', '2026-09-12 10:00:00' );
	$custom                      = efb_lp_po_mo( 'fr_FR', 'Bonjour fait main', '2026-01-01 10:00:00+0000', 'Poedit 3.4.2' );
	file_put_contents( efb_lp_file( 'fr_FR', 'po' ), $custom['po'] );
	file_put_contents( efb_lp_file( 'fr_FR', 'mo' ), $custom['mo'] );
	@unlink( efb_lp_file( 'fr_FR', 'l10n.php' ) );
	$summary = efb_lp_sync();
	efb_t( 'a hand-made translation is never replaced', 'kept_custom' === $summary['results']['fr_FR'] && 'Bonjour fait main' === efb_lp_translation( 'fr_FR' ), wp_json_encode( $summary['results'] ) );

	$manual = efb_lp_po_mo( 'fr_FR', 'Bonjour manuel', '2026-09-12 10:00:00+0000', '' );
	file_put_contents( efb_lp_file( 'fr_FR', 'po' ), \Emsfb\Language_Packs::stamp_po_generator( $manual['po'] ) );
	file_put_contents( efb_lp_file( 'fr_FR', 'mo' ), $manual['mo'] );
	efb_t( 'a WhiteStudio pack installed by hand is recognised as WhiteStudio', 'ws' === efb_lp_instance()->inspect_installed( 'fr_FR' )['source'] );
	unset( $efb_lp['ws_packs']['fr_FR'] );
	foreach ( array( 'po', 'mo', 'l10n.php' ) as $ext ) {
		@unlink( efb_lp_file( 'fr_FR', $ext ) );
	}

	$efb_lp['ws_packs']['nl_NL']             = efb_lp_pack( 'nl_NL', 'Hallo NL', '2026-09-12 10:00:00' );
	$efb_lp['ws_packs']['nl_NL']['override'] = array( 'sha256' => '' );
	$efb_lp['wporg_packs']['nl_NL']          = efb_lp_pack( 'nl_NL', 'Hallo NL wordpress.org', '2026-09-01 10:00:00' );
	$efb_lp['wporg_packs']['fr_BE']          = efb_lp_pack( 'fr_BE', 'Bonjour BE', '2026-09-01 10:00:00' );
	$efb_lp['wporg_packs']['fr_BE']['override'] = array( 'package' => 'https://evil.example/fr_BE.zip' );
	$efb_lp['locales']                       = array( 'de_AT', 'fr_FR', 'nl_NL', 'fr_BE' );
	$summary                                 = efb_lp_sync();
	efb_t( 'a WhiteStudio entry without a checksum is ignored (wordpress.org is used)', 'installed_wporg' === $summary['results']['nl_NL'], (string) $summary['results']['nl_NL'] );
	efb_t( 'a package on an unexpected host is never downloaded', 'not_available' === $summary['results']['fr_BE'] && 0 === efb_lp_count_requests( $efb_lp['http'], 'evil.example' ) );
	unset( $efb_lp['ws_packs']['nl_NL'], $efb_lp['wporg_packs']['fr_BE'] );

	$stale = '<?php return array( "messages" => array( "' . EFB_LP_PROBE . '" => "STALE" ) );';
	file_put_contents( efb_lp_file( 'nl_NL', 'l10n.php' ), $stale );
	$efb_lp['wporg_packs']['nl_NL'] = efb_lp_pack( 'nl_NL', 'Hallo NL v2', '2026-09-14 10:00:00' );
	$summary                        = efb_lp_sync();
	efb_t( 'an old .l10n.php never hides a newly installed translation', 'installed_wporg' === $summary['results']['nl_NL'] && 'Hallo NL v2' === efb_lp_translation( 'nl_NL' ), (string) efb_lp_translation( 'nl_NL' ) );

	// -----------------------------------------------------------------------
	echo "\nLiving next to WordPress's own translation updates\n";
	// -----------------------------------------------------------------------

	$offers               = new stdClass();
	$offers->translations = array(
		array(
			'type'     => 'plugin',
			'slug'     => 'easy-form-builder',
			'language' => 'de_AT',
		),
		array(
			'type'     => 'plugin',
			'slug'     => 'easy-form-builder',
			'language' => 'nl_NL',
		),
		array(
			'type'     => 'plugin',
			'slug'     => 'another-plugin',
			'language' => 'de_AT',
		),
	);
	$filtered  = efb_lp_instance()->filter_update_offers( $offers );
	$languages = array_map(
		function ( $offer ) {
			return $offer['slug'] . ':' . $offer['language'];
		},
		$filtered->translations
	);
	efb_t( 'wordpress.org may not replace a WhiteStudio pack', ! in_array( 'easy-form-builder:de_AT', $languages, true ) );
	efb_t( 'wordpress.org still updates its own packs and other plugins', in_array( 'easy-form-builder:nl_NL', $languages, true ) && in_array( 'another-plugin:de_AT', $languages, true ) );
	efb_t( 'the stored transient object itself is not modified', 3 === count( $offers->translations ) );
	efb_t( 'a missing transient passes through', false === efb_lp_instance()->filter_update_offers( false ) );

	$lp = efb_lp_instance();
	efb_t( 'with no bundled file the folder WordPress found is kept', '/wp-lang/' === $lp->filter_lang_dir( '/wp-lang/', 'easy-form-builder', 'fa_IR' ) );
	file_put_contents( $efb_lp['config']['bundled_dir'] . 'easy-form-builder-fa_IR.mo', 'x' );
	$lp = efb_lp_instance();
	efb_t( 'a shipped fa_IR translation wins over WP_LANG_DIR', $efb_lp['config']['bundled_dir'] === $lp->filter_lang_dir( '/wp-lang/', 'easy-form-builder', 'fa_IR' ) );
	efb_t( '...even when WordPress found no file at all', $efb_lp['config']['bundled_dir'] === $lp->filter_lang_dir( false, 'easy-form-builder', 'fa_IR' ) );
	efb_t( '...but not for a language that is not bundled', '/wp-lang/' === $lp->filter_lang_dir( '/wp-lang/', 'easy-form-builder', 'de_AT' ) );
	efb_t( '...nor for another text domain', '/wp-lang/' === $lp->filter_lang_dir( '/wp-lang/', 'another-plugin', 'fa_IR' ) );
	$offers->translations[] = array(
		'type'     => 'plugin',
		'slug'     => 'easy-form-builder',
		'language' => 'fa_IR',
	);
	$languages = array_map(
		function ( $offer ) {
			return $offer['slug'] . ':' . $offer['language'];
		},
		$lp->filter_update_offers( $offers )->translations
	);
	efb_t( 'wordpress.org packs of a shipped language are not downloaded', ! in_array( 'easy-form-builder:fa_IR', $languages, true ) );
	unlink( $efb_lp['config']['bundled_dir'] . 'easy-form-builder-fa_IR.mo' );

	// -----------------------------------------------------------------------
	echo "\nCannot connect: fallbacks, retries and the email\n";
	// -----------------------------------------------------------------------

	delete_option( EFB_LP_PREFIX . '_state' );
	efb_lp_clear_events();
	foreach ( array( 'po', 'mo', 'l10n.php' ) as $ext ) {
		@unlink( efb_lp_file( 'nl_NL', $ext ) );
	}
	$efb_lp['ws_packs']['de_AT'] = efb_lp_pack( 'de_AT', 'Hallo v5', '2026-09-13 10:00:00' );
	$efb_lp['locales']           = array( 'de_AT', 'nl_NL', 'it_IT' );
	$efb_lp['wporg_packs']       = array(
		'nl_NL' => efb_lp_pack( 'nl_NL', 'Hallo NL', '2026-09-01 10:00:00' ),
		'de_AT' => efb_lp_pack( 'de_AT', 'Hallo wordpress.org', '2026-09-15 10:00:00' ),
	);
	$efb_lp['ws_mode'] = 'transport';
	$efb_lp['mail']    = array();

	$summary = efb_lp_sync();
	$state   = efb_lp_state();
	efb_t( 'a connection timeout counts as unreachable', 'unreachable' === $summary['ws'] && 1 === (int) $state['ws']['failures'], wp_json_encode( $summary ) );
	efb_t( 'the WhiteStudio pack already installed is kept, not swapped for wordpress.org', 'kept_ws' === $summary['results']['de_AT'] && 'Hallo v3' === efb_lp_translation( 'de_AT' ), (string) $summary['results']['de_AT'] );
	efb_t( 'a language with nothing installed falls back to wordpress.org', 'installed_wporg' === $summary['results']['nl_NL'] );
	efb_t( 'a language nobody has stays without a pack, silently', 'not_available' === $summary['results']['it_IT'] );
	efb_t( 'a retry is scheduled', false !== wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'retry' ) ) );
	efb_t( 'one failure does not email anyone', false === $summary['notified'] && 0 === count( $efb_lp['mail'] ) );

	efb_lp_patch_state(
		array(
			'ws' => array(
				'failures'         => 2,
				'first_failure_at' => time() - 71 * HOUR_IN_SECONDS,
			),
		)
	);
	$summary = efb_lp_sync();
	efb_t( 'three failures within 72 hours still email nobody', false === $summary['notified'] && 0 === count( $efb_lp['mail'] ) );

	efb_lp_patch_state( array( 'ws' => array( 'first_failure_at' => time() - 73 * HOUR_IN_SECONDS ) ) );
	$efb_lp['ws_mode'] = 'html403';
	$summary           = efb_lp_sync();
	efb_t( 'a firewall page instead of the server also counts as unreachable', 'unreachable' === $summary['ws'] );
	efb_t( 'unreachable for 72 hours: the admin is emailed', true === $summary['notified'] && 1 === count( $efb_lp['mail'] ), (string) count( $efb_lp['mail'] ) );
	$mail = end( $efb_lp['mail'] );
	efb_t( '...at the site admin address', 'site-admin@example.test' === $mail['to'] );
	efb_t( '...naming the plugin in the subject', false !== strpos( $mail['subject'], 'Easy Form Builder' ) );
	efb_t( '...linking the manual installation guide', false !== strpos( $mail['message'], $efb_lp['config']['guide_url'] ) );
	efb_t( '...naming the server that could not be reached', false !== strpos( $mail['message'], 'lang.example.test' ) );
	efb_t( '...listing the languages', false !== strpos( $mail['message'], 'de_AT, it_IT, nl_NL' ) );
	efb_t( '...as plain text', in_array( 'Content-Type: text/plain; charset=UTF-8', (array) $mail['headers'], true ) );

	$summary = efb_lp_sync();
	efb_t( 'the same outage never emails twice', false === $summary['notified'] && 1 === count( $efb_lp['mail'] ) );

	$email = efb_lp_instance()->unreachable_email( efb_lp_state(), array( 'de_AT' ), 'unreachable' );
	efb_t( 'when wordpress.org is unreachable too, the email says outgoing connections look blocked', false !== strpos( $email['body'], 'WordPress.org either' ) );

	$efb_lp['ws_mode'] = 'rest404';
	$summary           = efb_lp_sync();
	$state             = efb_lp_state();
	efb_t( 'WordPress on the server answering (plugin not deployed) is not "cannot connect"', 'server_error' === $summary['ws'] && 0 === (int) $state['ws']['failures'], wp_json_encode( $state['ws'] ) );
	efb_t( '...and ends the outage', 0 === (int) $state['notice']['episode_sent_at'] );

	$efb_lp['ws_mode'] = 'server500';
	efb_lp_patch_state(
		array(
			'ws'     => array(
				'failures'         => 9,
				'first_failure_at' => time() - 200 * HOUR_IN_SECONDS,
			),
			'notice' => array( 'last_sent_at' => 0 ),
		)
	);
	$summary = efb_lp_sync();
	efb_t( 'an error answered by the language server never emails', 'server_error' === $summary['ws'] && false === $summary['notified'] && 1 === count( $efb_lp['mail'] ) );

	$efb_lp['ws_mode'] = 'transport';
	efb_lp_patch_state(
		array(
			'ws'     => array(
				'failures'         => 5,
				'first_failure_at' => time() - 100 * HOUR_IN_SECONDS,
			),
			'notice' => array(
				'episode_sent_at' => 0,
				'last_sent_at'    => time() - DAY_IN_SECONDS,
			),
		)
	);
	$summary = efb_lp_sync();
	efb_t( 'a new outage within 30 days of the last email stays quiet', false === $summary['notified'] && 1 === count( $efb_lp['mail'] ) );
	efb_lp_patch_state( array( 'notice' => array( 'last_sent_at' => time() - 31 * DAY_IN_SECONDS ) ) );
	$summary = efb_lp_sync();
	efb_t( '...after 30 days it emails again', true === $summary['notified'] && 2 === count( $efb_lp['mail'] ) );

	add_filter( EFB_LP_PREFIX . '_send_unreachable_email', '__return_false' );
	efb_lp_patch_state( array( 'notice' => array( 'episode_sent_at' => 0, 'last_sent_at' => 0 ) ) );
	$summary = efb_lp_sync();
	remove_filter( EFB_LP_PREFIX . '_send_unreachable_email', '__return_false' );
	efb_t( 'the email can be turned off with a filter', false === $summary['notified'] && 2 === count( $efb_lp['mail'] ) );

	delete_option( EFB_LP_PREFIX . '_state' );
	$efb_lp['ws_mode']           = 'intercept';
	$efb_lp['ws_packs']['de_AT'] = efb_lp_pack( 'de_AT', 'Hallo v6', '2026-09-16 10:00:00' );
	$summary                     = efb_lp_sync();
	efb_t( 'a catalog that loads while every download is blocked is still "cannot connect"', 'unreachable' === $summary['ws'] && 'failed_ws:download_intercepted' === $summary['results']['de_AT'], wp_json_encode( $summary ) );
	efb_t( '...and the installed translation stays', 'Hallo v3' === efb_lp_translation( 'de_AT' ) );

	$efb_lp['ws_mode'] = 'ok';
	unset( $efb_lp['ws_packs']['de_AT'] );
	$summary = efb_lp_sync();
	efb_t( 'a language WhiteStudio no longer has is handed back to wordpress.org', 'installed_wporg' === $summary['results']['de_AT'] && 'Hallo wordpress.org' === efb_lp_translation( 'de_AT' ), (string) $summary['results']['de_AT'] );
	efb_t( '...and the outage counter is back to zero', 0 === (int) efb_lp_state()['ws']['failures'] );

	// -----------------------------------------------------------------------
	echo "\nFirst visit without WP-Cron\n";
	// -----------------------------------------------------------------------

	delete_option( EFB_LP_PREFIX . '_state' );
	delete_option( EFB_LP_PREFIX . '_runtime' );
	efb_lp_clear_events();
	$efb_lp['config']['blocking_screens']     = array( 'Emsfb_create' );
	$efb_lp['config']['front_script_handles'] = array( 'efb-lp-test-front' );
	$efb_lp['locales']     = array( 'it_IT', 'sv_SE', 'pl_PL', 'cs_CZ', 'da_DK', 'hu_HU', 'fi', 'de_DE' );
	$efb_lp['ws_mode']     = 'ok';
	$efb_lp['wporg_mode']  = 'ok';
	$efb_lp['ws_packs']    = array(
		'it_IT' => efb_lp_pack( 'it_IT', 'Ciao', '2026-09-16 10:00:00' ),
		'fi'    => efb_lp_pack( 'fi', 'Hei', '2026-09-16 10:00:00' ),
	);
	$efb_lp['wporg_packs'] = array();
	$efb_lp['mail']        = array();

	$efb_lp_admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	$efb_lp_admin  = (int) reset( $efb_lp_admins );
	$efb_lp_locale = 'it_IT';
	$efb_lp_locale_filter = function () {
		return $GLOBALS['efb_lp_locale'];
	};
	add_filter( 'determine_locale', $efb_lp_locale_filter, 999 );
	$efb_lp_installed_actions = array();
	$efb_lp_installed_hook    = function ( $locales ) {
		$GLOBALS['efb_lp_installed_actions'][] = $locales;
	};
	add_action( EFB_LP_PREFIX . '_installed', $efb_lp_installed_hook );

	/**
	 * Capture what a hook prints.
	 *
	 * @param callable $callback Callback.
	 * @return string
	 */
	function efb_lp_output( $callback ) {
		ob_start();
		call_user_func( $callback );
		return (string) ob_get_clean();
	}

	$lp = efb_lp_instance();
	efb_t( 'a used locale without a pack, never tried: show the installing screen', 'install' === $lp->inline_need( 'it_IT' ) );
	efb_t( 'a locale the plugin ships needs nothing', '' === $lp->inline_need( 'de_DE' ) && '' === $lp->inline_need( 'en_US' ) );

	file_put_contents( efb_lp_file( 'hu_HU', 'mo' ), 'x' );
	efb_t( 'a pack on disk after a plugin update: refresh silently', 'refresh' === efb_lp_instance()->inline_need( 'hu_HU' ) );
	update_option( EFB_LP_PREFIX . '_runtime', array( 'version' => '4.2.0', 'synced_at' => time(), 'attempts' => array(), 'bg_at' => 0 ), true );
	efb_t( '...nothing once this version has synced', '' === efb_lp_instance()->inline_need( 'hu_HU' ) );
	update_option( EFB_LP_PREFIX . '_runtime', array( 'version' => '4.2.0', 'synced_at' => time() - 3 * DAY_IN_SECONDS, 'attempts' => array(), 'bg_at' => 0 ), true );
	efb_t( '...refresh again when nothing ran for two days (no WP-Cron)', 'refresh' === efb_lp_instance()->inline_need( 'hu_HU' ) );
	update_option( EFB_LP_PREFIX . '_runtime', array( 'version' => '4.2.0', 'synced_at' => time() - 3 * DAY_IN_SECONDS, 'attempts' => array(), 'bg_at' => time() ), true );
	efb_t( '...but at most once an hour', '' === efb_lp_instance()->inline_need( 'hu_HU' ) );
	unlink( efb_lp_file( 'hu_HU', 'mo' ) );
	delete_option( EFB_LP_PREFIX . '_runtime' );

	$efb_lp['http'] = array();
	$result         = efb_lp_instance()->install_now( 'it_IT', 'admin' );
	$runtime        = get_option( EFB_LP_PREFIX . '_runtime' );
	efb_t( 'installing now puts the pack on disk', 'installed' === $result['status'] && 'Ciao' === efb_lp_translation( 'it_IT' ), wp_json_encode( $result ) );
	efb_t( '...remembers the attempt for this version', 'installed' === $runtime['attempts']['it_IT']['outcome'] && '4.2.0' === $runtime['attempts']['it_IT']['version'] );
	efb_t( '...tells the plugin to drop its cached phrases', ! empty( $efb_lp_installed_actions ) && in_array( 'it_IT', (array) end( $efb_lp_installed_actions ), true ) );
	efb_t( '...and after that the page needs nothing', '' === efb_lp_instance()->inline_need( 'it_IT' ) );
	efb_t( '...and emails nobody', 0 === count( $efb_lp['mail'] ) );

	$result = efb_lp_instance()->install_now( 'sv_SE', 'admin' );
	efb_t( 'a language nobody has translated: not available, no email', 'not_available' === $result['status'] && 0 === count( $efb_lp['mail'] ), wp_json_encode( $result ) );
	efb_t( '...and the screen is not shown again for this version', '' === efb_lp_instance()->inline_need( 'sv_SE' ) || 'background' === efb_lp_instance()->inline_need( 'sv_SE' ) );

	$efb_lp['ws_mode']    = 'transport';
	$efb_lp['wporg_mode'] = 'transport';
	$result               = efb_lp_instance()->install_now( 'pl_PL', 'front' );
	$mail                 = end( $efb_lp['mail'] );
	efb_t( 'this site cannot connect: failed, and the admin is emailed at once', 'failed' === $result['status'] && 'unreachable' === $result['reason'] && true === $result['mailed'] && 1 === count( $efb_lp['mail'] ), wp_json_encode( $result ) );
	efb_t( '...the screen says so and names the address', false !== strpos( $result['message'], 'site-admin@example.test' ) );
	efb_t( '...the email says what went wrong', false !== strpos( $mail['message'], 'cannot connect to lang.example.test' ) && false !== strpos( $mail['message'], 'cURL error 28' ), $mail['message'] );
	efb_t( '...and that wordpress.org could not be reached either', false !== strpos( $mail['message'], 'WordPress.org either' ) );
	efb_t( '...where it happened (a visitor, the form in English)', false !== strpos( $mail['message'], 'form was shown in English' ) );
	efb_t( '...how to fix the connection', false !== strpos( $mail['message'], 'allow outgoing HTTPS connections' ) );
	efb_t( '...and the manual installation steps: download link, folder, guide', false !== strpos( $mail['message'], EFB_LP_WS . '/wp-json/wl/v1/translations/easy-form-builder/package/pl_PL' ) && false !== strpos( $mail['message'], 'upload the files to' ) && false !== strpos( $mail['message'], $efb_lp['config']['guide_url'] ) );
	efb_t( '...as plain text', in_array( 'Content-Type: text/plain; charset=UTF-8', (array) $mail['headers'], true ) );
	efb_t( 'after a failed try the screen is not shown again; retries are silent', 'install' !== efb_lp_instance()->inline_need( 'pl_PL' ) );

	$result = efb_lp_instance()->install_now( 'cs_CZ', 'admin' );
	efb_t( 'the same problem again within 30 days: no second email', 'failed' === $result['status'] && false === $result['mailed'] && 1 === count( $efb_lp['mail'] ) && false !== strpos( $result['message'], 'tried again automatically' ) );

	$efb_lp['ws_mode']    = 'ok';
	$efb_lp['wporg_mode'] = 'ok';
	add_filter( 'file_mod_allowed', '__return_false' );
	$result = efb_lp_instance()->install_now( 'da_DK', 'admin' );
	remove_filter( 'file_mod_allowed', '__return_false' );
	$mail = end( $efb_lp['mail'] );
	efb_t( 'the site cannot write files: failed, and a separate email explains it', 'failed' === $result['status'] && 'write' === $result['reason'] && true === $result['mailed'] && 2 === count( $efb_lp['mail'] ), wp_json_encode( $result ) );
	efb_t( '...naming DISALLOW_FILE_MODS and what to do about it', false !== strpos( $mail['message'], 'DISALLOW_FILE_MODS' ) && false !== strpos( $mail['message'], 'opened in the WordPress dashboard' ) );

	$efb_lp['ws_mode'] = 'server500';
	$result            = efb_lp_instance()->install_now( 'hu_HU', 'admin' );
	efb_t( 'the language server answering with an error: failed, but no email (retried automatically)', 'failed' === $result['status'] && 'server' === $result['reason'] && false === $result['mailed'] && 2 === count( $efb_lp['mail'] ), wp_json_encode( $result ) );
	$efb_lp['ws_mode'] = 'ok';

	// The AJAX endpoint behind the installing screen.
	add_filter( 'wp_doing_ajax', '__return_true' );
	$efb_lp_die = function () {
		return function () {
			throw new RuntimeException( 'wp_die' );
		};
	};
	add_filter( 'wp_die_ajax_handler', $efb_lp_die );
	$efb_lp_saved_user = get_current_user_id();

	wp_set_current_user( $efb_lp_admin );
	$_POST = array(
		'nonce'  => 'wrong',
		'locale' => 'fi',
	);
	$_REQUEST = $_POST;
	$json     = json_decode( efb_lp_output( array( efb_lp_instance(), 'ajax_install' ) ), true );
	efb_t( 'the install endpoint refuses a request without a valid nonce', is_array( $json ) && false === $json['success'] );

	$_POST['nonce']    = wp_create_nonce( EFB_LP_PREFIX . '_install' );
	$_REQUEST          = $_POST;
	$json              = json_decode( efb_lp_output( array( efb_lp_instance(), 'ajax_install' ) ), true );
	efb_t( '...installs for an administrator', is_array( $json ) && true === $json['success'] && 'installed' === $json['data']['status'], wp_json_encode( $json ) );

	$_POST['locale'] = 'xx_XX';
	$_REQUEST        = $_POST;
	$json            = json_decode( efb_lp_output( array( efb_lp_instance(), 'ajax_install' ) ), true );
	efb_t( '...and ignores a locale the site does not use', is_array( $json ) && 'nothing' === $json['data']['status'] );

	wp_set_current_user( 0 );
	$_POST['nonce'] = wp_create_nonce( EFB_LP_PREFIX . '_install' );
	$_REQUEST       = $_POST;
	$json           = json_decode( efb_lp_output( array( efb_lp_instance(), 'ajax_install' ) ), true );
	efb_t( '...and refuses someone who may not install languages', is_array( $json ) && false === $json['success'] );
	$_POST    = array();
	$_REQUEST = array();
	remove_filter( 'wp_die_ajax_handler', $efb_lp_die );
	remove_filter( 'wp_doing_ajax', '__return_true' );

	// What each screen prints.
	delete_option( EFB_LP_PREFIX . '_runtime' );
	foreach ( array( 'it_IT', 'fi' ) as $locale ) {
		foreach ( array( 'po', 'mo', 'l10n.php' ) as $ext ) {
			@unlink( efb_lp_file( $locale, $ext ) );
		}
	}
	wp_set_current_user( $efb_lp_admin );
	$efb_lp_locale = 'it_IT';

	$_GET['page'] = 'Emsfb_create';
	$lp           = efb_lp_instance();
	$notice       = efb_lp_output( array( $lp, 'admin_notice' ) );
	$footer       = efb_lp_output( array( $lp, 'admin_footer' ) );
	efb_t( 'a blocking screen prints the installing layer, from the first paint', false !== strpos( $footer, 'role="alertdialog"' ) && false !== strpos( $footer, 'Installing language files' ) );
	efb_t( '...with the script that installs and reloads', false !== strpos( $footer, EFB_LP_PREFIX . '_install' ) && false !== strpos( $footer, 'window.location.reload' ) && false !== strpos( $footer, '"blocking":true' ) );
	efb_t( '...and no notice on top of it', '' === $notice );

	$_GET['page'] = 'some-other-screen';
	$lp           = efb_lp_instance();
	$notice       = efb_lp_output( array( $lp, 'admin_notice' ) );
	$footer       = efb_lp_output( array( $lp, 'admin_footer' ) );
	efb_t( 'any other screen shows a notice', false !== strpos( $notice, 'is installing its language files' ) && false === strpos( $footer, 'role="alertdialog"' ) );
	efb_t( '...and never reloads by itself', false !== strpos( $footer, '"blocking":false' ) );

	wp_set_current_user( 0 );
	$_GET['page'] = 'Emsfb_create';
	$lp           = efb_lp_instance();
	$footer       = efb_lp_output( array( $lp, 'admin_footer' ) );
	efb_t( 'someone who may not install languages sees nothing, the install runs in the background', false === strpos( $footer, 'alertdialog' ) && false !== strpos( $footer, EFB_LP_PREFIX . '_background' ) && false !== strpos( $footer, 'sendBeacon' ) );
	unset( $_GET['page'] );
	wp_set_current_user( $efb_lp_saved_user );

	$efb_lp_locale = 'fi';
	$lp            = efb_lp_instance();
	efb_t( 'a front-end page without the plugin\'s script prints nothing', '' === efb_lp_output( array( $lp, 'front_footer' ) ) );
	wp_register_script( 'efb-lp-test-front', 'https://example.test/front.js', array(), '1', true );
	wp_enqueue_script( 'efb-lp-test-front' );
	$front = efb_lp_output( array( $lp, 'front_footer' ) );
	wp_dequeue_script( 'efb-lp-test-front' );
	wp_deregister_script( 'efb-lp-test-front' );
	efb_t( 'a page with a form starts a background install and shows nothing', false !== strpos( $front, 'sendBeacon' ) && false !== strpos( $front, 'locale=fi' ) && false !== strpos( $front, 'origin=front' ) && false === strpos( $front, 'alertdialog' ) );
	efb_t( '...a trigger that stops firing in a cached copy of the page', 1 === preg_match( '/"expires":(\d+)/', $front, $m ) && (int) $m[1] > time() * 1000 && (int) $m[1] <= ( time() + 1800 ) * 1000 );

	update_option( EFB_LP_PREFIX . '_runtime', array( 'version' => '4.2.0', 'synced_at' => time(), 'attempts' => array(), 'bg_at' => 0 ), true );
	$efb_lp['http'] = array();
	efb_t( 'a background request for a locale the site does not use does nothing', null === efb_lp_instance()->run_background( 'xx_XX' ) && 0 === count( $efb_lp['http'] ), wp_json_encode( $efb_lp['http'] ) );
	update_option( EFB_LP_PREFIX . '_runtime', array( 'version' => '4.2.0', 'synced_at' => time(), 'attempts' => array(), 'bg_at' => time() ), true );
	$result = efb_lp_instance()->run_background( 'fi', 'front' );
	efb_t( 'the first background request for a missing pack installs it, whatever the throttle', is_array( $result ) && 'installed' === $result['status'] && 'Hei' === efb_lp_translation( 'fi' ), wp_json_encode( $result ) );
	$runtime                      = get_option( EFB_LP_PREFIX . '_runtime' );
	$runtime['attempts']['pl_PL'] = array( 'version' => '4.2.0', 'at' => time(), 'outcome' => 'failed', 'reason' => 'unreachable' );
	$runtime['bg_at']             = time();
	update_option( EFB_LP_PREFIX . '_runtime', $runtime, true );
	$efb_lp['http'] = array();
	efb_t( 'a retry within the hour does nothing, however often it is requested', null === efb_lp_instance()->run_background( 'pl_PL' ) && null === efb_lp_instance()->run_background( 'pl_PL' ) && 0 === count( $efb_lp['http'] ) );
	$runtime['version'] = '4.1.0';
	$runtime['bg_at']   = time() - 2 * HOUR_IN_SECONDS;
	update_option( EFB_LP_PREFIX . '_runtime', $runtime, true );
	$efb_lp['locales'] = array( 'fi' );
	$result            = efb_lp_instance()->run_background( 'fi' );
	efb_t( 'after a plugin update a page view runs the sync WP-Cron would have run', is_array( $result ) && 'complete' === $result['status'] && count( $efb_lp['http'] ) > 0, wp_json_encode( $result ) );
	$efb_lp['http'] = array();
	efb_t( '...once', null === efb_lp_instance()->run_background( 'fi' ) && 0 === count( $efb_lp['http'] ) );

	remove_filter( 'determine_locale', $efb_lp_locale_filter, 999 );
	remove_action( EFB_LP_PREFIX . '_installed', $efb_lp_installed_hook );

	$real = \Emsfb\Language_Packs::instance( 'emsfb_language_packs' );
	if ( $real ) {
		efb_t( 'EFB drops its phrase cache after an install', false !== has_action( 'emsfb_language_packs_installed' ) );
		efb_t( 'EFB blocks Panel and Create, and triggers from pages with its front-end script', array( 'Emsfb', 'Emsfb_create' ) === $real->get_config()['blocking_screens'] && array( 'efb-main-js' ) === $real->get_config()['front_script_handles'] );
	}

	// -----------------------------------------------------------------------
	echo "\nScheduling\n";
	// -----------------------------------------------------------------------

	efb_lp_clear_events();
	$lp = efb_lp_instance();
	$lp->on_activation();
	efb_t( 'activation schedules a run instead of downloading in the activation request', false !== wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'activation' ) ) );
	$lp->maybe_schedule();
	efb_t( 'the daily check is scheduled', false !== wp_next_scheduled( EFB_LP_PREFIX . '_daily' ) );
	$lp->on_deactivation();
	efb_t( 'deactivation removes the schedule', false === wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'activation' ) ) && false === wp_next_scheduled( EFB_LP_PREFIX . '_daily' ) );

	delete_site_transient( EFB_LP_PREFIX . '_watched' );
	efb_lp_patch_state( array( 'synced_version' => '4.1.0' ) );
	efb_lp_instance()->watch();
	efb_t( 'an admin page load after a plugin update schedules a run', false !== wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'version' ) ) );

	delete_site_transient( EFB_LP_PREFIX . '_watched' );
	efb_lp_patch_state(
		array(
			'synced_version' => '4.2.0',
			'locales'        => array( 'de_AT' ),
		)
	);
	$efb_lp['locales'] = array( 'de_AT', 'sv_SE' );
	efb_lp_instance()->watch();
	efb_t( 'a site that starts using a new language schedules a run', false !== wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'locales' ) ) );
	wp_clear_scheduled_hook( EFB_LP_PREFIX . '_sync', array( 'locales' ) );
	efb_lp_instance()->watch();
	efb_t( 'admin page loads are throttled', false === wp_next_scheduled( EFB_LP_PREFIX . '_sync', array( 'locales' ) ) );

	// -----------------------------------------------------------------------
	echo "\nThe .po marker\n";
	// -----------------------------------------------------------------------

	$po      = efb_lp_po_mo( 'de_AT', 'x', '2026-09-01 10:00:00+0000', 'GlotPress/4.1.0' )['po'];
	$tmp_po  = $efb_lp_root . 'marker.po';
	file_put_contents( $tmp_po, \Emsfb\Language_Packs::stamp_po_generator( $po ) );
	efb_t( 'an existing X-Generator is replaced', 0 === strpos( wp_get_pomo_file_data( $tmp_po )['X-Generator'], \Emsfb\Language_Packs::MARKER ) && 1 === substr_count( (string) file_get_contents( $tmp_po ), 'X-Generator' ) );
	$po = efb_lp_po_mo( 'de_AT', 'x', '2026-09-01 10:00:00+0000', '' )['po'];
	file_put_contents( $tmp_po, \Emsfb\Language_Packs::stamp_po_generator( $po ) );
	efb_t( 'a missing X-Generator is added to the header', 0 === strpos( wp_get_pomo_file_data( $tmp_po )['X-Generator'], \Emsfb\Language_Packs::MARKER ) );
	$parsed = new PO();
	efb_t( '...and the file is still a valid PO file', $parsed->import_from_file( $tmp_po ) && isset( $parsed->entries[ EFB_LP_PROBE ] ) );

	// -----------------------------------------------------------------------
	echo "\nEnd to end against the local WhiteStudio site\n";
	// -----------------------------------------------------------------------

	remove_filter( 'pre_http_request', 'efb_lp_http', 10 );

	$ws_url     = getenv( 'EFB_LP_WS_URL' ) ? rtrim( getenv( 'EFB_LP_WS_URL' ), '/' ) : 'http://127.0.0.1/ws';
	$ws_storage = getenv( 'EFB_LP_WS_STORAGE' ) ? rtrim( getenv( 'EFB_LP_WS_STORAGE' ), '/\\' ) . '/' : dirname( rtrim( ABSPATH, '/\\' ) ) . '/ws/source/files/lang/';
	$ws_pack    = $ws_storage . 'easy-form-builder/de_AT/';
	$probe      = wp_remote_get( $ws_url . '/wp-json/wl/v1/translations/easy-form-builder', array( 'timeout' => 10 ) );

	if ( is_wp_error( $probe ) || '' === (string) wp_remote_retrieve_header( $probe, 'x-ws-language-handler' ) ) {
		echo "  [SKIP] the language server does not answer at {$ws_url}\n";
	} elseif ( ! is_dir( $ws_storage . 'easy-form-builder' ) ) {
		echo "  [SKIP] storage folder not found at {$ws_storage}\n";
	} elseif ( file_exists( $ws_pack ) ) {
		echo "  [SKIP] a real de_AT pack is published on the server; not touching it\n";
	} else {
		$ws_files = efb_lp_po_mo( 'de_AT', 'Hallo vom WhiteStudio-Server', '2026-09-16 09:00:00+0000', 'GlotPress/4.1.0' );
		wp_mkdir_p( $ws_pack );
		file_put_contents( $ws_pack . 'easy-form-builder-de_AT.po', $ws_files['po'] );
		file_put_contents( $ws_pack . 'easy-form-builder-de_AT.mo', $ws_files['mo'] );
		file_put_contents( $ws_pack . 'easy-form-builder-de_AT-0123456789abcdef0123456789abcdef.json', '{"locale_data":{"messages":{"":{"domain":"messages"}}}}' );
		file_put_contents( $ws_pack . 'readme.txt', 'ignored' );

		try {
			$catalog = wp_remote_get( $ws_url . '/wp-json/wl/v1/translations/easy-form-builder?version=4.2.0&locales=de_AT,fa_IR', array( 'timeout' => 20 ) );
			$body    = json_decode( wp_remote_retrieve_body( $catalog ), true );
			$entry   = isset( $body['translations'][0] ) ? $body['translations'][0] : array();
			efb_t( 'the server lists the published pack', 1 === count( (array) $body['translations'] ) && 'de_AT' === ( $entry['language'] ?? '' ), wp_remote_retrieve_body( $catalog ) );
			efb_t( '...with its version and date read from the .po', '4.2.0' === ( $entry['version'] ?? '' ) && '2026-09-16 09:00:00' === ( $entry['updated'] ?? '' ) );

			$download = wp_remote_get( $entry['package'], array( 'timeout' => 20 ) );
			$zip_body = wp_remote_retrieve_body( $download );
			efb_t( 'the package matches the advertised checksum and size', hash( 'sha256', $zip_body ) === $entry['sha256'] && strlen( $zip_body ) === (int) $entry['size'] );
			efb_t( '...and says so in a header', $entry['sha256'] === wp_remote_retrieve_header( $download, 'x-content-sha256' ) );
			$tmp_zip = $efb_lp_root . 'server.zip';
			file_put_contents( $tmp_zip, $zip_body );
			$zip   = new ZipArchive();
			$names = array();
			if ( true === $zip->open( $tmp_zip ) ) {
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$names[] = $zip->getNameIndex( $i );
				}
				$stamped = (string) $zip->getFromName( 'easy-form-builder-de_AT.po' );
				$zip->close();
			}
			sort( $names );
			efb_t(
				'the zip is flat and holds only the pack (plus a .l10n.php for manual installs)',
				array( 'easy-form-builder-de_AT-0123456789abcdef0123456789abcdef.json', 'easy-form-builder-de_AT.l10n.php', 'easy-form-builder-de_AT.mo', 'easy-form-builder-de_AT.po' ) === $names,
				wp_json_encode( $names )
			);
			efb_t( '...with the server marker in its .po', false !== strpos( $stamped ?? '', 'X-Generator: ' . \Emsfb\Language_Packs::MARKER ) );

			$bundled_request = wp_remote_get( $ws_url . '/wp-json/wl/v1/translations/easy-form-builder/package/fa_IR', array( 'timeout' => 10 ) );
			efb_t( 'the server never serves a bundled language', 404 === wp_remote_retrieve_response_code( $bundled_request ) && '' !== (string) wp_remote_retrieve_header( $bundled_request, 'x-ws-language-handler' ) );

			$ws_bundled = $ws_storage . 'easy-form-builder/de_DE/';
			if ( ! file_exists( $ws_bundled ) ) {
				wp_mkdir_p( $ws_bundled );
				copy( $ws_pack . 'easy-form-builder-de_AT.po', $ws_bundled . 'easy-form-builder-de_DE.po' );
				copy( $ws_pack . 'easy-form-builder-de_AT.mo', $ws_bundled . 'easy-form-builder-de_DE.mo' );
				$listed = json_decode( wp_remote_retrieve_body( wp_remote_get( $ws_url . '/wp-json/wl/v1/translations/easy-form-builder?locales=de_DE,de_AT', array( 'timeout' => 20 ) ) ), true );
				efb_lp_rmdir( $ws_bundled );
				$languages = array_map(
					function ( $t ) {
						return $t['language'];
					},
					isset( $listed['translations'] ) ? (array) $listed['translations'] : array()
				);
				efb_t( '...not even de_DE uploaded by mistake, while its variant de_AT is served', array( 'de_AT' ) === $languages, wp_json_encode( $languages ) );
			}

			$e2e_config                  = $efb_lp['config'];
			$e2e_config['prefix']        = EFB_LP_PREFIX . '_e2e';
			$e2e_config['servers']       = array( $ws_url );
			$e2e_config['wporg_slug']    = '';
			$e2e_config['languages_dir'] = $efb_lp_root . 'e2e-languages/plugins/';
			$efb_lp['locales']           = array( 'de_AT', 'fr_FR' );
			add_filter(
				$e2e_config['prefix'] . '_locales',
				function () {
					return $GLOBALS['efb_lp']['locales'];
				}
			);
			$e2e     = new \Emsfb\Language_Packs( $e2e_config );
			$summary = $e2e->sync( 'manual' );
			efb_t( 'a site installs the pack from the real server over HTTP', 'installed_ws' === ( $summary['results']['de_AT'] ?? '' ) && 'ok' === $summary['ws'], wp_json_encode( $summary ) );
			efb_t( '...a language the server does not have is reported, not an error', 'not_available' === ( $summary['results']['fr_FR'] ?? '' ) );
			$domain = 'efb-lp-e2e-probe';
			load_textdomain( $domain, $e2e_config['languages_dir'] . 'easy-form-builder-de_AT.mo', 'de_AT' );
			efb_t( '...and WordPress loads it', 'Hallo vom WhiteStudio-Server' === translate( EFB_LP_PROBE, $domain ) );
			unload_textdomain( $domain );
			WP_Translation_Controller::get_instance()->set_locale( determine_locale() );
			$summary = $e2e->sync( 'manual' );
			efb_t( '...and the next run finds it up to date', 'up_to_date_ws' === ( $summary['results']['de_AT'] ?? '' ) );
			delete_option( $e2e_config['prefix'] . '_state' );
			delete_option( $e2e_config['prefix'] . '_lock' );
		} finally {
			efb_lp_rmdir( $ws_pack );
			foreach ( (array) glob( $ws_storage . '.packages/easy-form-builder-de_AT-*.zip' ) as $zip_file ) {
				@unlink( $zip_file );
			}
			efb_t( 'the throwaway pack is removed from the server again', ! file_exists( $ws_pack ) && array() === glob( $ws_storage . '.packages/easy-form-builder-de_AT-*.zip' ) );
		}
	}
} catch ( Throwable $e ) {
	efb_t( 'no exception escapes the test', false, get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
} finally {
	foreach ( $efb_lp_filters as $filter ) {
		remove_filter( $filter[0], $filter[1], $filter[2] );
	}
	remove_all_filters( 'pre_wp_mail' );
	if ( null !== $efb_lp_saved_mail_filters ) {
		$wp_filter['pre_wp_mail'] = $efb_lp_saved_mail_filters;
	}
	efb_lp_clear_events();
	delete_option( EFB_LP_PREFIX . '_state' );
	delete_option( EFB_LP_PREFIX . '_lock' );
	delete_option( EFB_LP_PREFIX . '_runtime' );
	delete_option( EFB_LP_PREFIX . '_e2e_runtime' );
	delete_site_transient( EFB_LP_PREFIX . '_watched' );
	efb_lp_rmdir( $efb_lp_root );
}

echo "\n{$pass} passed, {$fail} failed\n";
if ( $fail > 0 ) {
	echo "\nFailures:\n  - " . implode( "\n  - ", $failures ) . "\n";
	exit( 1 );
}
exit( 0 );
