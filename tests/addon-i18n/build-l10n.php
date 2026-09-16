<?php
/**
 * Build an add-on's gettext files from source.json + translations/<locale>.json.
 *
 * Produces, in --out (normally vendor/<slug>/languages/):
 *   efb-<slug>.pot, efb-<slug>-<locale>.po|.mo|.l10n.php for every locale that has a
 *   translation file, locales.json and index.php.
 *
 * Run:
 *   php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/telegram/source.json \
 *       --translations=temp/addon-i18n/telegram/translations --out=vendor/telegram/languages \
 *       [--pot-only] [--date=YYYY-MM-DD] [--wp=C:/xampp/htdocs/wp] [--wporg=temp/addon-i18n/wporg]
 *       [--only=de_DE,nl_NL]   rebuild just these locales; the .pot, index.php and every other
 *                              locale's files stay untouched, locales.json lists every locale on disk.
 *
 * source.json: {"slug","domain","entries":[{"key","msgid","context","plural","translators"}...]}
 * translations/<locale>.json: {"locale": "fa_IR", "entries": {"<msgid>": "<msgstr>"}}
 *   (an entry with context is keyed "<context>\u0004<msgid>"; a plural msgstr is an array).
 */

$plugin_root = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/';
$args        = array(
	'source'       => '',
	'translations' => '',
	'out'          => '',
	'date'         => date( 'Y-m-d' ),
	'wp'           => 'C:/xampp/htdocs/wp',
	'wporg'        => $plugin_root . 'temp/addon-i18n/wporg',
	'locales'      => __DIR__ . '/locales.json',
	'pot-only'     => false,
	'only'         => '',
);
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--([a-z-]+)(?:=(.*))?$/', $arg, $m ) ) {
		$args[ $m[1] ] = isset( $m[2] ) ? $m[2] : true;
	}
}
foreach ( array( 'source', 'out' ) as $required ) {
	if ( '' === $args[ $required ] ) {
		fwrite( STDERR, "Missing --{$required}\n" );
		exit( 2 );
	}
}
$resolve = function ( $path ) use ( $plugin_root ) {
	$path = str_replace( '\\', '/', $path );
	return preg_match( '#^([A-Za-z]:/|/)#', $path ) ? $path : $plugin_root . $path;
};
$source_file = $resolve( $args['source'] );
$out_dir     = rtrim( $resolve( $args['out'] ), '/' ) . '/';
$tr_dir      = '' !== $args['translations'] ? rtrim( $resolve( $args['translations'] ), '/' ) . '/' : '';
$efb_wp_root          = rtrim( str_replace( '\\', '/', $args['wp'] ), '/' ) . '/';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $efb_wp_root );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
require_once $efb_wp_root . 'wp-includes/compat.php';
require_once $efb_wp_root . 'wp-includes/pomo/po.php';
require_once $efb_wp_root . 'wp-includes/pomo/mo.php';
require_once $efb_wp_root . 'wp-includes/l10n/class-wp-translation-file.php';
require_once $efb_wp_root . 'wp-includes/l10n/class-wp-translation-file-mo.php';
require_once $efb_wp_root . 'wp-includes/l10n/class-wp-translation-file-php.php';

$source  = json_decode( file_get_contents( $source_file ), true );
$locales = json_decode( file_get_contents( $resolve( $args['locales'] ) ), true );
if ( ! is_array( $source ) || empty( $source['entries'] ) || ! is_array( $locales ) ) {
	fwrite( STDERR, "Unreadable source.json or locales.json\n" );
	exit( 2 );
}
$slug     = $source['slug'];
$domain   = $source['domain'];
$provider = dirname( $out_dir ) . '/' . $slug . '-phrases-efb.php';

if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

/** Line numbers of every msgid literal in the provider, for #: references. */
function efb_l10n_references( $provider, $msgid ) {
	$refs = array();
	if ( ! is_file( $provider ) ) {
		return $refs;
	}
	$tokens = token_get_all( file_get_contents( $provider ) );
	foreach ( $tokens as $t ) {
		if ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
			$value = eval( 'return ' . $t[1] . ';' );
			if ( $value === $msgid ) {
				$refs[] = basename( $provider ) . ':' . $t[2];
			}
		}
	}
	return $refs;
}

/** Unique entries keyed by context + msgid, in source order. */
$unique = array();
foreach ( $source['entries'] as $e ) {
	$id = ( isset( $e['context'] ) && null !== $e['context'] ? $e['context'] . "\4" : '' ) . $e['msgid'];
	if ( ! isset( $unique[ $id ] ) ) {
		$unique[ $id ] = array(
			'singular'           => $e['msgid'],
			'plural'             => isset( $e['plural'] ) ? $e['plural'] : null,
			'context'            => isset( $e['context'] ) ? $e['context'] : null,
			'extracted_comments' => array(),
			'keys'               => array(),
		);
	}
	if ( ! empty( $e['translators'] ) ) {
		$unique[ $id ]['extracted_comments'][] = 'translators: ' . $e['translators'];
	}
	$unique[ $id ]['keys'][] = $e['key'];
}

function efb_l10n_entry( $u, $provider, $translations = null ) {
	$args = array(
		'singular'           => $u['singular'],
		'extracted_comments' => implode( "\n", array_unique( $u['extracted_comments'] ) ),
		'references'         => efb_l10n_references( $provider, $u['singular'] ),
	);
	if ( null !== $u['context'] ) {
		$args['context'] = $u['context'];
	}
	if ( null !== $u['plural'] ) {
		$args['plural']      = $u['plural'];
		$args['is_plural']   = true;
	}
	$flags = array();
	if ( preg_match( '/%(\d+\$)?[sdfu]/', $u['singular'] ) ) {
		$flags[] = 'php-format';
	}
	$args['flags'] = $flags;
	if ( null !== $translations ) {
		$args['translations'] = $translations;
	}
	return new Translation_Entry( $args );
}

$common_headers = array(
	'Project-Id-Version'        => 'Easy Form Builder - ' . ucfirst( $slug ) . ' add-on',
	'Report-Msgid-Bugs-To'      => 'https://whitestudio.team/',
	'MIME-Version'              => '1.0',
	'Content-Type'              => 'text/plain; charset=UTF-8',
	'Content-Transfer-Encoding' => '8bit',
	'X-Generator'               => 'EFB tests/addon-i18n/build-l10n.php',
	'X-Domain'                  => $domain,
);

$only = '' === $args['only'] || true === $args['only'] ? array() : array_filter( array_map( 'trim', explode( ',', $args['only'] ) ) );

// ---- POT ---- (a partial rebuild leaves the template and index.php alone)
if ( empty( $only ) ) {
	$pot = new PO();
	$pot->set_headers( array_merge( $common_headers, array(
		'POT-Creation-Date' => $args['date'] . ' 00:00+0000',
		'PO-Revision-Date'  => 'YEAR-MO-DA HO:MI+ZONE',
		'Last-Translator'   => 'FULL NAME <EMAIL@ADDRESS>',
		'Language-Team'     => 'LANGUAGE <LL@li.org>',
	) ) );
	foreach ( $unique as $u ) {
		$pot->add_entry( efb_l10n_entry( $u, $provider ) );
	}
	$pot_file = $out_dir . $domain . '.pot';
	$pot->export_to_file( $pot_file );
	echo 'pot: ' . count( $unique ) . " msgids -> {$pot_file}\n";

	file_put_contents( $out_dir . 'index.php', "<?php\n// Silence is golden.\n" );
}

if ( $args['pot-only'] ) {
	exit( 0 );
}
if ( '' === $tr_dir || ! is_dir( $tr_dir ) ) {
	fwrite( STDERR, "Missing --translations directory\n" );
	exit( 2 );
}

/** Plural-Forms from the wordpress.org export when present, else locales.json. */
function efb_l10n_plural_forms( $wporg_dir, $locale_row ) {
	foreach ( array( 'stable', 'dev' ) as $branch ) {
		$file = rtrim( $wporg_dir, '/' ) . '/' . $locale_row['locale'] . '-' . $branch . '.po';
		if ( is_file( $file ) && filesize( $file ) > 1000 ) {
			$po = new PO();
			if ( $po->import_from_file( $file ) && ! empty( $po->headers['Plural-Forms'] ) ) {
				return $po->headers['Plural-Forms'];
			}
		}
	}
	return $locale_row['plural_forms'];
}

$msgfmt = null;
foreach ( array( 'msgfmt', 'C:/Program Files/Git/usr/bin/msgfmt.exe' ) as $candidate ) {
	$probe = @shell_exec( '"' . $candidate . '" --version 2>&1' );
	if ( is_string( $probe ) && false !== stripos( $probe, 'msgfmt' ) ) {
		$msgfmt = $candidate;
		break;
	}
}

$built = array();
foreach ( $locales['locales'] as $row ) {
	$locale  = $row['locale'];
	$tr_file = $tr_dir . $locale . '.json';
	if ( ! empty( $only ) && ! in_array( $locale, $only, true ) ) {
		continue;
	}
	if ( ! is_file( $tr_file ) ) {
		echo "skip {$locale}: no translation file\n";
		continue;
	}
	$tr = json_decode( file_get_contents( $tr_file ), true );
	if ( ! is_array( $tr ) || ! isset( $tr['entries'] ) ) {
		fwrite( STDERR, "Unreadable {$tr_file}\n" );
		exit( 1 );
	}
	$po = new PO();
	$po->set_headers( array_merge( $common_headers, array(
		'POT-Creation-Date' => $args['date'] . ' 00:00+0000',
		'PO-Revision-Date'  => $args['date'] . ' 00:00+0000',
		'Last-Translator'   => 'WhiteStudio <hi@whitestudio.team>',
		'Language-Team'     => $locale,
		'Language'          => $locale,
		'Plural-Forms'      => efb_l10n_plural_forms( $args['wporg'], $row ),
	) ) );
	$missing = 0;
	foreach ( $unique as $id => $u ) {
		$msgstr = isset( $tr['entries'][ $id ] ) ? $tr['entries'][ $id ] : null;
		if ( null === $msgstr || '' === $msgstr || array() === $msgstr ) {
			$missing++;
			continue;
		}
		$po->add_entry( efb_l10n_entry( $u, $provider, (array) $msgstr ) );
	}
	if ( $missing > 0 ) {
		fwrite( STDERR, "{$locale}: {$missing} msgids without a translation\n" );
		exit( 1 );
	}
	$base    = $out_dir . $domain . '-' . $locale;
	$po->export_to_file( $base . '.po' );

	if ( null !== $msgfmt ) {
		$cmd    = '"' . $msgfmt . '" --check-format -o "' . $base . '.mo" "' . $base . '.po" 2>&1';
		$output = array();
		exec( $cmd, $output, $code );
		if ( 0 !== $code ) {
			fwrite( STDERR, "msgfmt failed for {$locale}:\n" . implode( "\n", $output ) . "\n" );
			exit( 1 );
		}
	} else {
		$mo = new MO();
		$mo->set_headers( $po->headers );
		foreach ( $po->entries as $entry ) {
			$mo->add_entry( $entry );
		}
		$mo->export_to_file( $base . '.mo' );
	}

	$php = WP_Translation_File::transform( $base . '.mo', 'php' );
	if ( false === $php ) {
		fwrite( STDERR, "transform to .l10n.php failed for {$locale}\n" );
		exit( 1 );
	}
	file_put_contents( $base . '.l10n.php', $php );
	$built[] = $locale;
	echo "built {$locale}: " . count( $po->entries ) . " entries" . ( null !== $msgfmt ? ' (msgfmt)' : ' (POMO)' ) . "\n";
}

if ( ! empty( $only ) && count( $built ) !== count( $only ) ) {
	fwrite( STDERR, '--only asked for ' . implode( ',', $only ) . ' but built ' . implode( ',', $built ) . "\n" );
	exit( 1 );
}
// Every locale whose compiled file is on disk, in locales.json order.
$listed = array();
foreach ( $locales['locales'] as $row ) {
	if ( is_file( $out_dir . $domain . '-' . $row['locale'] . '.mo' ) && is_file( $out_dir . $domain . '-' . $row['locale'] . '.l10n.php' ) ) {
		$listed[] = $row['locale'];
	}
}
$manifest_file = $out_dir . 'locales.json';
$previous      = is_file( $manifest_file ) ? json_decode( file_get_contents( $manifest_file ), true ) : null;
if ( ! empty( $only ) && is_array( $previous ) && isset( $previous['locales'] ) && $previous['locales'] === $listed ) {
	echo 'locales.json: unchanged (' . count( $listed ) . " locales)\n";
} else {
	file_put_contents(
		$manifest_file,
		json_encode( array( 'domain' => $domain, 'locales' => $listed, 'generated' => $args['date'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
	);
	echo 'locales.json: ' . count( $listed ) . " locales\n";
}
