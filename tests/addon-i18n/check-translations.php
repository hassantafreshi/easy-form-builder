<?php
/**
 * Phase 3 invariants for one add-on's translations. Zero errors = exit 0.
 *
 * Run:
 *   php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/telegram/source.json \
 *       --translations=temp/addon-i18n/telegram/translations [--languages=vendor/telegram/languages] [--no-wp]
 *
 * Checks per locale (tests/addon-i18n/locales.json):
 *  - every msgid present with a non-empty msgstr;
 *  - identical placeholder multiset (%s, %d, %1$s, %%; numbered ones may reorder);
 *  - identical HTML tags and entities (&hellip; stays an entity);
 *  - same leading/trailing whitespace and number of \n;
 *  - plural entries have exactly nplurals forms;
 *  - WARNING only (never an error): a msgstr equal to its msgid, unless the msgid is only
 *    never-translate tokens. The natural word is often the English one (de "Status");
 *  - WARNING only: how many strings a variant shares with the locale it was adapted from
 *    (locales.json "parent"), because a variant is its own language;
 * and, with --languages (WordPress bootstrapped unless --no-wp):
 *  - the .mo loads through load_textdomain() and __() returns the msgstr for three samples;
 *  - the .l10n.php returns an array with the same entry count as the .mo.
 */

$plugin_root = str_replace( '\\', '/', dirname( __DIR__, 2 ) ) . '/';
$args        = array(
	'source'       => '',
	'translations' => '',
	'languages'    => '',
	'locales'      => __DIR__ . '/locales.json',
	'wp'           => 'C:/xampp/htdocs/wp',
	'no-wp'        => false,
);
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--([a-z-]+)(?:=(.*))?$/', $arg, $m ) ) {
		$args[ $m[1] ] = isset( $m[2] ) ? $m[2] : true;
	}
}
$resolve = function ( $path ) use ( $plugin_root ) {
	$path = str_replace( '\\', '/', (string) $path );
	return preg_match( '#^([A-Za-z]:/|/)#', $path ) ? $path : $plugin_root . $path;
};
if ( '' === $args['source'] || '' === $args['translations'] ) {
	fwrite( STDERR, "Usage: --source=... --translations=... [--languages=...]\n" );
	exit( 2 );
}

$source  = json_decode( file_get_contents( $resolve( $args['source'] ) ), true );
$locales = json_decode( file_get_contents( $resolve( $args['locales'] ) ), true );
$tr_dir  = rtrim( $resolve( $args['translations'] ), '/' ) . '/';

/* Tokens that are never translated. A msgstr may equal its msgid only when the
 * msgid is made of these (plus placeholders, @handles, code and punctuation). */
$never_translate = array( 'Google Sheets', 'Stripe', 'PayPal', 'Telegram', 'Google', 'WordPress', 'reCAPTCHA', 'API', 'REST', 'JSON', 'CSV', 'URL', 'PHP', 'php.ini', 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'Easy Form Builder' );

$unique = array();
foreach ( $source['entries'] as $e ) {
	$id = ( isset( $e['context'] ) && null !== $e['context'] ? $e['context'] . "\4" : '' ) . $e['msgid'];
	$unique[ $id ] = $e;
}

function efb_check_placeholders( $s ) {
	preg_match_all( '/%(?:\d+\$)?[sdfu%]/', $s, $m );
	$list = $m[0];
	sort( $list );
	return $list;
}
function efb_check_tags( $s ) {
	preg_match_all( '/<\/?[a-zA-Z][^>]*>/', $s, $m );
	$list = $m[0];
	sort( $list );
	return $list;
}
function efb_check_entities( $s ) {
	preg_match_all( '/&(?:[a-zA-Z][a-zA-Z0-9]*|#\d+|#x[0-9a-fA-F]+);/', $s, $m );
	$list = $m[0];
	sort( $list );
	return $list;
}
function efb_check_edges( $s ) {
	preg_match( '/^\s*/u', $s, $lead );
	preg_match( '/\s*$/u', $s, $trail );
	return array( $lead[0], $trail[0] );
}
function efb_check_only_tokens( $msgid, $tokens ) {
	$rest = str_replace( $tokens, '', $msgid );
	$rest = preg_replace( '/%(?:\d+\$)?[sdfu%]|@[A-Za-z0-9_]+|\/[a-z_]+|<[^>]+>|&[a-zA-Z#0-9]+;/', '', $rest );
	return '' === trim( preg_replace( '/[\p{P}\p{S}\p{N}\s]+/u', '', $rest ) );
}

$errors   = array();
$warnings = array();
$per_file = array();
foreach ( $locales['locales'] as $row ) {
	$locale = $row['locale'];
	$file   = $tr_dir . $locale . '.json';
	if ( ! is_file( $file ) ) {
		$errors[] = "{$locale}: translations file missing";
		continue;
	}
	$tr = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $tr ) || ! isset( $tr['entries'] ) || ! is_array( $tr['entries'] ) ) {
		$errors[] = "{$locale}: unreadable translations file";
		continue;
	}
	$per_file[ $locale ] = $tr['entries'];
	foreach ( $unique as $id => $e ) {
		$label = $locale . ' ' . json_encode( $e['msgid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! array_key_exists( $id, $tr['entries'] ) ) {
			$errors[] = "{$label}: missing";
			continue;
		}
		$msgstr = $tr['entries'][ $id ];
		if ( ! empty( $e['plural'] ) ) {
			if ( ! is_array( $msgstr ) || count( $msgstr ) !== (int) $row['nplurals'] ) {
				$errors[] = "{$label}: plural needs exactly {$row['nplurals']} forms";
				continue;
			}
			$forms = $msgstr;
		} else {
			if ( ! is_string( $msgstr ) ) {
				$errors[] = "{$label}: msgstr must be a string";
				continue;
			}
			$forms = array( $msgstr );
		}
		foreach ( $forms as $form ) {
			if ( '' === trim( (string) $form ) ) {
				$errors[] = "{$label}: empty msgstr";
				continue;
			}
			$reference = ( ! empty( $e['plural'] ) && $form !== $forms[0] ) ? $e['plural'] : $e['msgid'];
			if ( efb_check_placeholders( $reference ) !== efb_check_placeholders( $form ) ) {
				$errors[] = "{$label}: placeholders differ (" . implode( ' ', efb_check_placeholders( $form ) ) . ')';
			}
			if ( efb_check_tags( $reference ) !== efb_check_tags( $form ) ) {
				$errors[] = "{$label}: HTML tags differ";
			}
			if ( efb_check_entities( $reference ) !== efb_check_entities( $form ) ) {
				$errors[] = "{$label}: entities differ (" . implode( ' ', efb_check_entities( $form ) ) . ')';
			}
			if ( efb_check_edges( $reference ) !== efb_check_edges( $form ) ) {
				$errors[] = "{$label}: leading/trailing whitespace differs";
			}
			if ( substr_count( $reference, "\n" ) !== substr_count( $form, "\n" ) ) {
				$errors[] = "{$label}: number of line breaks differs";
			}
			// Not an error: the natural UI word is often the English one (de "Status", tr "Test").
			// Reported so a reviewer can confirm it was intended.
			if ( $form === $reference && ! efb_check_only_tokens( $reference, $never_translate ) ) {
				$warnings[] = "{$label}: msgstr equals msgid";
			}
		}
	}
	foreach ( array_keys( $tr['entries'] ) as $id ) {
		if ( ! isset( $unique[ $id ] ) ) {
			$errors[] = "{$locale}: stale entry not in source " . json_encode( $id, JSON_UNESCAPED_UNICODE );
		}
	}
}

/* A regional or formality variant is its own language: strings identical to the locale it was
 * adapted from are allowed (many really are the same), but they are counted and reported. */
$parent_identical = array();
foreach ( $locales['locales'] as $row ) {
	if ( empty( $row['parent'] ) || ! isset( $per_file[ $row['locale'] ], $per_file[ $row['parent'] ] ) ) {
		continue;
	}
	$same = 0;
	foreach ( $unique as $id => $e ) {
		if ( isset( $per_file[ $row['locale'] ][ $id ], $per_file[ $row['parent'] ][ $id ] ) && $per_file[ $row['locale'] ][ $id ] === $per_file[ $row['parent'] ][ $id ] ) {
			$same++;
		}
	}
	$parent_identical[ $row['locale'] ] = $same;
	if ( $same > 0 ) {
		$warnings[] = "{$row['locale']}: {$same} of " . count( $unique ) . " strings identical to {$row['parent']}";
	}
}

/* Compiled files. */
$compiled_checked = 0;
if ( '' !== $args['languages'] ) {
	$lang_dir = rtrim( $resolve( $args['languages'] ), '/' ) . '/';
	$efb_wp_root       = rtrim( str_replace( '\\', '/', $args['wp'] ), '/' ) . '/';
	if ( ! $args['no-wp'] ) {
		if ( ! defined( 'DISABLE_WP_CRON' ) ) {
			define( 'DISABLE_WP_CRON', true );
		}
		require_once $efb_wp_root . 'wp-load.php';
		add_filter( 'pre_http_request', function () { return new WP_Error( 'blocked', 'blocked by check-translations' ); }, 1 );
	} else {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $efb_wp_root );
		}
		if ( ! defined( 'WPINC' ) ) {
			define( 'WPINC', 'wp-includes' );
		}
		require_once $efb_wp_root . 'wp-includes/compat.php';
		require_once $efb_wp_root . 'wp-includes/pomo/mo.php';
	}
	$domain = $source['domain'];
	foreach ( $locales['locales'] as $row ) {
		$locale = $row['locale'];
		$base   = $lang_dir . $domain . '-' . $locale;
		foreach ( array( '.po', '.mo', '.l10n.php' ) as $ext ) {
			if ( ! is_file( $base . $ext ) ) {
				$errors[] = "{$locale}: compiled file missing {$ext}";
			}
		}
		if ( ! is_file( $base . '.mo' ) || ! is_file( $base . '.l10n.php' ) || ! isset( $per_file[ $locale ] ) ) {
			continue;
		}
		$mo = new MO();
		if ( ! $mo->import_from_file( $base . '.mo' ) ) {
			$errors[] = "{$locale}: .mo does not parse";
			continue;
		}
		$php = include $base . '.l10n.php';
		if ( ! is_array( $php ) || ! isset( $php['messages'] ) || count( $php['messages'] ) !== count( $mo->entries ) ) {
			$errors[] = "{$locale}: .l10n.php entry count " . ( is_array( $php ) && isset( $php['messages'] ) ? count( $php['messages'] ) : 'n/a' ) . ' != .mo ' . count( $mo->entries );
		}
		if ( count( $mo->entries ) !== count( $unique ) ) {
			$errors[] = "{$locale}: .mo has " . count( $mo->entries ) . ' entries, source has ' . count( $unique );
		}
		if ( ! $args['no-wp'] ) {
			$ids     = array_keys( $unique );
			$samples = array_values( array_unique( array( $ids[0], $ids[ (int) floor( count( $ids ) / 2 ) ], $ids[ count( $ids ) - 1 ] ) ) );
			$check_domain = 'efb-i18n-check-' . strtolower( $locale );
			foreach ( array( 'mo', 'php' ) as $format ) {
				$force = function ( $preferred, $d ) use ( $check_domain, $format ) {
					return $d === $check_domain ? $format : $preferred;
				};
				add_filter( 'translation_file_format', $force, 10, 2 );
				unload_textdomain( $check_domain );
				$loaded = load_textdomain( $check_domain, $base . '.mo', determine_locale() );
				if ( ! $loaded ) {
					$errors[] = "{$locale}: load_textdomain() failed ({$format})";
				} else {
					foreach ( $samples as $id ) {
						$e      = $unique[ $id ];
						$want   = $per_file[ $locale ][ $id ];
						$got    = ( isset( $e['context'] ) && null !== $e['context'] ) ? _x( $e['msgid'], $e['context'], $check_domain ) : __( $e['msgid'], $check_domain ); // phpcs:ignore WordPress.WP.I18n
						if ( $got !== ( is_array( $want ) ? $want[0] : $want ) ) {
							$errors[] = "{$locale}: __() via {$format} returned " . json_encode( $got, JSON_UNESCAPED_UNICODE ) . ' for ' . json_encode( $e['msgid'] );
						}
					}
				}
				unload_textdomain( $check_domain );
				remove_filter( 'translation_file_format', $force, 10 );
			}
		}
		$compiled_checked++;
	}
}

foreach ( $warnings as $warning ) {
	echo "[WARNING] {$warning}\n";
}
foreach ( $errors as $error ) {
	echo "[ERROR] {$error}\n";
}
if ( ! empty( $parent_identical ) ) {
	echo 'identical to parent: ' . json_encode( $parent_identical ) . "\n";
}
echo 'locales: ' . count( $per_file ) . ', msgids: ' . count( $unique ) . ', compiled locales checked: ' . $compiled_checked . ', warnings: ' . count( $warnings ) . ', errors: ' . count( $errors ) . "\n";
exit( empty( $errors ) ? 0 : 1 );
