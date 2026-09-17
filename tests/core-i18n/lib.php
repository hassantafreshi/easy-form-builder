<?php
/**
 * Shared helpers for the core translation tools (tests/core-i18n/*.php).
 *
 * The tools run from the command line without WordPress; only its POMO and
 * l10n classes are loaded. Every path can be overridden, so the tools can be
 * pointed at fixtures (tests/core-i18n/test-core-i18n-tools.php does).
 */

if ( defined( 'EFB_CI18N_LIB' ) ) {
	return;
}
define( 'EFB_CI18N_LIB', true );

/** WordPress's own locale format. */
const EFB_CI18N_LOCALE_PATTERN = '/^[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?$/';

const EFB_CI18N_DOMAIN = 'easy-form-builder';

/**
 * Parse --key=value arguments over defaults, then resolve the standard paths.
 *
 * @param array $argv     Command line.
 * @param array $defaults Tool-specific defaults.
 * @return array
 */
function efb_ci18n_args( array $argv, array $defaults = array() ) {
	$root = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/';
	$base = array(
		'root'       => $root,
		'wp'         => str_replace( '\\', '/', dirname( $root, 4 ) ) . '/wp/',
		'work'       => $root . 'temp/core-i18n/',
		'catalog'    => $root . 'docs/core-i18n/catalog.json',
		'locales'    => $root . 'tests/core-i18n/locales.json',
		'bundled'    => $root . 'languages/',
		'ws-storage' => str_replace( '\\', '/', dirname( $root, 4 ) ) . '/ws/source/files/lang/',
		'product'    => 'easy-form-builder',
	);
	$args = array_merge( $base, $defaults );
	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( preg_match( '/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m ) ) {
			$args[ $m[1] ] = isset( $m[2] ) ? $m[2] : true;
		}
	}
	foreach ( array( 'root', 'wp', 'work', 'bundled', 'ws-storage' ) as $dir ) {
		$args[ $dir ] = rtrim( efb_ci18n_resolve( $args[ $dir ], $args['root'] ), '/' ) . '/';
	}
	foreach ( array( 'catalog', 'locales' ) as $file ) {
		$args[ $file ] = efb_ci18n_resolve( $args[ $file ], $args['root'] );
	}
	return $args;
}

/**
 * @param string $path Absolute, or relative to the plugin root.
 * @param string $root Plugin root with a trailing slash.
 * @return string
 */
function efb_ci18n_resolve( $path, $root ) {
	$path = str_replace( '\\', '/', (string) $path );
	return preg_match( '#^([A-Za-z]:/|/)#', $path ) ? $path : $root . $path;
}

/**
 * Load WordPress's POMO and translation-file classes without WordPress.
 *
 * @param string $wp WordPress root with a trailing slash.
 * @return void
 */
function efb_ci18n_load_pomo( $wp ) {
	if ( class_exists( 'PO' ) && class_exists( 'WP_Translation_File' ) ) {
		return;
	}
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $wp );
	}
	if ( ! defined( 'WPINC' ) ) {
		define( 'WPINC', 'wp-includes' );
	}
	require_once $wp . 'wp-includes/compat.php';
	require_once $wp . 'wp-includes/pomo/po.php';
	require_once $wp . 'wp-includes/pomo/mo.php';
	require_once $wp . 'wp-includes/l10n/class-wp-translation-file.php';
	require_once $wp . 'wp-includes/l10n/class-wp-translation-file-mo.php';
	require_once $wp . 'wp-includes/l10n/class-wp-translation-file-php.php';
}

/**
 * @param string|null $context Context.
 * @param string      $msgid   Msgid.
 * @return string The key gettext uses: "<context>\4<msgid>" or the msgid.
 */
function efb_ci18n_id( $context, $msgid ) {
	return ( null !== $context && '' !== $context ? $context . "\4" : '' ) . $msgid;
}

/**
 * @param string $file JSON file.
 * @return array|null
 */
function efb_ci18n_json_read( $file ) {
	if ( ! is_file( $file ) ) {
		return null;
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * @param string $file File.
 * @param mixed  $data Data.
 * @return void
 */
function efb_ci18n_json_write( $file, $data ) {
	if ( ! is_dir( dirname( $file ) ) ) {
		mkdir( dirname( $file ), 0755, true );
	}
	file_put_contents( $file, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
}

/**
 * @param array $args efb_ci18n_args() result.
 * @return array<string, array> Locale => row.
 */
function efb_ci18n_locales( array $args ) {
	$data = efb_ci18n_json_read( $args['locales'] );
	if ( ! is_array( $data ) || empty( $data['locales'] ) ) {
		efb_ci18n_fail( 'Unreadable ' . $args['locales'] );
	}
	$rows = array();
	foreach ( $data['locales'] as $row ) {
		$rows[ $row['locale'] ] = $row;
	}
	return $rows;
}

/**
 * Where a locale's published .po lives: the WhiteStudio server's storage, or the
 * plugin's own languages/ folder for a locale that ships with the plugin.
 *
 * @param array $args efb_ci18n_args() result.
 * @param array $row  Locale row.
 * @return string Directory with a trailing slash.
 */
function efb_ci18n_published_dir( array $args, array $row ) {
	return 'bundled' === $row['destination']
		? $args['bundled']
		: $args['ws-storage'] . $args['product'] . '/' . $row['locale'] . '/';
}

/**
 * Read a .po into id => entry data. Fuzzy and empty translations are skipped.
 *
 * @param string $file PO file.
 * @return array<string, array{msgstr: string|array, plural: bool, origin: string}>
 */
function efb_ci18n_read_po( $file ) {
	$out = array();
	if ( ! is_file( $file ) ) {
		return $out;
	}
	$po = new PO();
	if ( ! $po->import_from_file( $file ) ) {
		return $out;
	}
	foreach ( $po->entries as $entry ) {
		if ( in_array( 'fuzzy', (array) $entry->flags, true ) || empty( $entry->translations ) ) {
			continue;
		}
		$forms = array_values( $entry->translations );
		if ( '' === trim( implode( '', $forms ) ) ) {
			continue;
		}
		$origin = '';
		if ( preg_match( '/^origin: (.+)$/m', (string) $entry->translator_comments, $m ) ) {
			$origin = trim( $m[1] );
		}
		$id         = efb_ci18n_id( $entry->context, $entry->singular );
		$out[ $id ] = array(
			'msgstr' => $entry->is_plural ? $forms : $forms[0],
			'plural' => (bool) $entry->is_plural,
			'origin' => $origin,
		);
	}
	return $out;
}

/*
 * Invariants a translation must keep. The same rules as
 * tests/addon-i18n/check-translations.php, which stays the final gate.
 */

/**
 * @param string $s String.
 * @return string[]
 */
function efb_ci18n_placeholders( $s ) {
	preg_match_all( '/%(?:\d+\$)?[sdfu%]/', $s, $m );
	sort( $m[0] );
	return $m[0];
}

/**
 * @param string $s String.
 * @return string[]
 */
function efb_ci18n_tags( $s ) {
	preg_match_all( '/<\/?[a-zA-Z][^>]*>/', $s, $m );
	sort( $m[0] );
	return $m[0];
}

/**
 * @param string $s String.
 * @return string[]
 */
function efb_ci18n_entities( $s ) {
	preg_match_all( '/&(?:[a-zA-Z][a-zA-Z0-9]*|#\d+|#x[0-9a-fA-F]+);/', $s, $m );
	sort( $m[0] );
	return $m[0];
}

/**
 * @param string $s String.
 * @return array{0: string, 1: string}
 */
function efb_ci18n_edges( $s ) {
	preg_match( '/^\s*/u', $s, $lead );
	preg_match( '/\s*$/u', $s, $trail );
	return array( $lead[0], $trail[0] );
}

/**
 * Problems with a translation of one source entry. Empty array = valid.
 *
 * @param array        $entry    Source entry (msgid, plural).
 * @param string|array $msgstr   Translation.
 * @param int          $nplurals Plural forms of the locale.
 * @param string       $locale   Locale.
 * @return string[]
 */
function efb_ci18n_problems( array $entry, $msgstr, $nplurals, $locale ) {
	$problems = array();
	if ( ! empty( $entry['plural'] ) ) {
		if ( ! is_array( $msgstr ) || count( $msgstr ) !== (int) $nplurals ) {
			return array( "plural needs exactly {$nplurals} forms" );
		}
		$forms = array_values( $msgstr );
	} else {
		if ( ! is_string( $msgstr ) ) {
			return array( 'msgstr must be a string' );
		}
		$forms = array( $msgstr );
	}
	$rtl = (bool) preg_match( '/^(ar|ary|fa|he|ur|ckb|ps|ug)(_|$)/', $locale );
	foreach ( $forms as $index => $form ) {
		$form      = (string) $form;
		$reference = ( ! empty( $entry['plural'] ) && $index > 0 ) ? $entry['plural'] : $entry['msgid'];
		if ( '' === trim( $form ) ) {
			$problems[] = 'empty msgstr';
			continue;
		}
		if ( efb_ci18n_placeholders( $reference ) !== efb_ci18n_placeholders( $form ) ) {
			$problems[] = 'placeholders differ (' . implode( ' ', efb_ci18n_placeholders( $form ) ) . ' vs ' . implode( ' ', efb_ci18n_placeholders( $reference ) ) . ')';
		}
		if ( efb_ci18n_tags( $reference ) !== efb_ci18n_tags( $form ) ) {
			$problems[] = 'HTML tags differ';
		}
		$ref_entities  = efb_ci18n_entities( $reference );
		$form_entities = efb_ci18n_entities( $form );
		if ( $ref_entities !== $form_entities ) {
			$allowed = $ref_entities;
			if ( $rtl && in_array( '&rarr;', $allowed, true ) ) {
				$allowed[] = '&larr;';
			}
			$added = array_diff( $form_entities, $allowed );
			$strip = function ( $list ) use ( $rtl ) {
				$kept = array();
				foreach ( $list as $e ) {
					if ( '&amp;' !== $e ) {
						$kept[] = ( $rtl && '&larr;' === $e ) ? '&rarr;' : $e;
					}
				}
				sort( $kept );
				return $kept;
			};
			if ( ! empty( $added ) ) {
				$problems[] = 'entity not in the msgid (' . implode( ' ', array_unique( $added ) ) . ')';
			} elseif ( $strip( $ref_entities ) !== $strip( $form_entities ) ) {
				$problems[] = 'entities differ (keep &hellip; and friends as entities)';
			}
		}
		if ( efb_ci18n_edges( $reference ) !== efb_ci18n_edges( $form ) ) {
			$problems[] = 'leading/trailing whitespace differs';
		}
		if ( substr_count( $reference, "\n" ) !== substr_count( $form, "\n" ) ) {
			$problems[] = 'number of line breaks differs';
		}
	}
	return array_values( array_unique( $problems ) );
}

/**
 * @param string $message Message.
 * @param int    $code    Exit code.
 * @return void
 */
function efb_ci18n_fail( $message, $code = 2 ) {
	fwrite( STDERR, $message . "\n" );
	exit( $code );
}
