<?php
/**
 * Loads every published core pack (ws storage, or languages/ for the bundled locales) into a real WordPress and checks
 * what the plugin itself would show with it:
 *
 *   1. every singular phrase of the pack, read back through __() / _x(), equals temp/core-i18n/translations/<locale>.json;
 *   2. text_efb(), the plugin's own phrase builder, returns those strings, and the nine builder phrases whose %s the
 *      JavaScript empties (efb_ci18n_blanked_msgids()) read cleanly on the desktop tab (%s empty), on the mobile tab
 *      (%s = the locale's "Mobile") and inside "%s not found";
 *   3. no U+FEFF or U+2060 anywhere in the pack.
 *
 * Nothing is written or installed: each pack is loaded in memory and the site's language is not touched.
 *
 * Needs WordPress at the path of --wp (default C:/xampp/htdocs/wp/) with this plugin active.
 *
 * Run:
 *   php tests/core-i18n/verify-in-wordpress.php                 every locale with a published pack and a complete translations file
 *   php tests/core-i18n/verify-in-wordpress.php es_ES tr_TR     only these
 *   php tests/core-i18n/verify-in-wordpress.php --show es_ES    also print the composed labels
 * Exit 0 only when every locale passes.
 */

require __DIR__ . '/lib.php';

$only = array();
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 !== strpos( $arg, '--' ) ) {
		$only[] = $arg;
	}
}
$args    = efb_ci18n_args( $argv, array( 'show' => false ) );
$locales = efb_ci18n_locales( $args );
$source  = efb_ci18n_json_read( $args['work'] . 'source.json' );
if ( ! is_array( $source ) || ! is_file( $args['wp'] . 'wp-load.php' ) ) {
	efb_ci18n_fail( 'Needs ' . $args['work'] . 'source.json (run extract.php) and WordPress at ' . $args['wp'] );
}

define( 'WP_USE_THEMES', false );
require $args['wp'] . 'wp-load.php';
if ( ! function_exists( 'get_efbFunction' ) ) {
	efb_ci18n_fail( 'Easy Form Builder is not active on the WordPress at ' . $args['wp'] );
}

// switch_to_locale() refuses a locale the site has not installed, and text_efb() caches per determine_locale(): force
// the locale with the filters instead, so every locale gets its own array.
$GLOBALS['efb_verify_locale'] = null;
add_filter(
	'pre_determine_locale',
	function () {
		return $GLOBALS['efb_verify_locale'];
	}
);
add_filter(
	'locale',
	function ( $locale ) {
		return null !== $GLOBALS['efb_verify_locale'] ? $GLOBALS['efb_verify_locale'] : $locale;
	}
);

$keys = array(
	'swidth'         => '%s width',
	'slabelPosition' => '%s Label Position',
	'slabelSize'     => '%s Label size',
	'slabelAlign'    => '%s Label | Align',
	'sdescAlign'     => '%s Description | Align',
	'sbtnsAlign'     => '%s Buttons | Align',
	'checkedClr'     => '%s Checked Color',
	'copied'         => '%s copied!',
	'slocation'      => '%s Location',
	'snotfound'      => '%s not found',
	'mobile'         => 'Mobile',
);
$fn   = get_efbFunction();
$bad  = 0;
$done = 0;
$skip = array();

foreach ( $locales as $locale => $row ) {
	if ( ! empty( $only ) && ! in_array( $locale, $only, true ) ) {
		continue;
	}
	$mo = efb_ci18n_published_dir( $args, $row ) . EFB_CI18N_DOMAIN . '-' . $locale . '.mo';
	$tr = efb_ci18n_json_read( $args['work'] . 'translations/' . $locale . '.json' );
	if ( ! is_file( $mo ) || ! is_array( $tr ) || empty( $tr['entries'] ) ) {
		if ( ! empty( $only ) ) {
			echo "{$locale}: FAIL (no published pack or no translations file)\n";
			$bad++;
		}
		continue;
	}
	$entries = $tr['entries'];
	if ( empty( $only ) && count( array_intersect_key( $entries, array_column( $source['entries'], 'key', 'key' ) ) ) < count( $source['entries'] ) ) {
		$skip[] = $locale;
		continue;
	}

	$GLOBALS['efb_verify_locale'] = $locale;
	unload_textdomain( EFB_CI18N_DOMAIN, true );
	if ( ! load_textdomain( EFB_CI18N_DOMAIN, $mo, $locale ) ) {
		echo "{$locale}: FAIL (WordPress cannot load {$mo})\n";
		$bad++;
		continue;
	}
	$problems = array();

	// 1. The pack through WordPress equals the translations file.
	$compared = 0;
	$differ   = 0;
	$hidden   = 0;
	foreach ( $source['entries'] as $e ) {
		if ( ! empty( $e['plural'] ) || ! isset( $entries[ $e['key'] ] ) || ! is_string( $entries[ $e['key'] ] ) ) {
			continue;
		}
		$got = null !== $e['context'] ? _x( $e['msgid'], $e['context'], EFB_CI18N_DOMAIN ) : __( $e['msgid'], EFB_CI18N_DOMAIN ); // phpcs:ignore WordPress.WP.I18n
		$compared++;
		if ( $got !== $entries[ $e['key'] ] ) {
			$differ++;
			if ( $differ <= 3 ) {
				$problems[] = 'the pack says ' . json_encode( $got, JSON_UNESCAPED_UNICODE ) . ' for ' . json_encode( $e['msgid'], JSON_UNESCAPED_UNICODE ) . ', the translations file ' . json_encode( $entries[ $e['key'] ], JSON_UNESCAPED_UNICODE );
			}
		}
		if ( preg_match( '/[\x{FEFF}\x{2060}]/u', $got ) ) {
			$hidden++;
		}
	}
	if ( $differ > 3 ) {
		$problems[] = ( $differ - 3 ) . ' more phrases differ';
	}
	if ( $hidden > 0 ) {
		$problems[] = "{$hidden} phrases contain U+FEFF or U+2060";
	}

	// 2. What the builder receives from text_efb() and composes from it.
	$text = $fn->text_efb( array_keys( $keys ) );
	$dec  = function ( $key ) use ( $text ) {
		return isset( $text[ $key ] ) ? html_entity_decode( $text[ $key ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : null;
	};
	$mobile = (string) $dec( 'mobile' );
	$shown  = array();
	foreach ( $keys as $key => $msgid ) {
		$raw = $dec( $key );
		if ( null === $raw ) {
			$problems[] = "text_efb() has no {$key}";
			continue;
		}
		if ( isset( $entries[ $msgid ] ) && $raw !== $entries[ $msgid ] ) {
			$problems[] = "text_efb({$key}) returns " . json_encode( $raw, JSON_UNESCAPED_UNICODE ) . ', not what the pack holds';
		}
		if ( in_array( $msgid, efb_ci18n_blanked_msgids(), true ) ) {
			foreach ( efb_ci18n_blanked_problems( $raw ) as $problem ) {
				$problems[] = "{$msgid}: {$problem}";
			}
		}
		if ( 'snotfound' === $key || 'mobile' === $key ) {
			continue;
		}
		$desktop = trim( str_replace( '%s', '', $raw ) );
		$other   = str_replace( '%s', $mobile, $raw );
		if ( 'copied' === $key ) {
			$other = str_replace( '%s', 'Tracking code', $raw );
		} elseif ( 'slocation' === $key ) {
			// pro_els-efb.js: the emptied (and trimmed) "%s Location" is the noun inside "%s not found".
			$desktop = str_replace( '%s', $desktop, (string) $dec( 'snotfound' ) );
			$other   = '';
			if ( preg_match( '/\(\s*\)|\[\s*\]|（\s*）|«\s*»|“\s*”|„\s*“|\s»|«\s|\s”|“\s/u', $desktop ) ) {
				$problems[] = "the composed \"not found\" message has empty or padded quotes: [{$desktop}]";
			}
		}
		if ( preg_match( '/\(\s*\)|\[\s*\]|（\s*）/u', $desktop . ' ' . $other ) ) {
			$problems[] = "{$msgid} shows empty brackets: [{$desktop}] [{$other}]";
		}
		$shown[] = sprintf( '   %-16s desktop: [%s]%s', $key, $desktop, '' !== $other ? "   mobile: [{$other}]" : '' );
	}

	$done++;
	if ( ! empty( $problems ) ) {
		$bad++;
	}
	echo sprintf( "%-14s %-9s %s (%d phrases read back through WordPress, %d differ)\n", $locale, 'bundled' === $row['destination'] ? 'languages/' : 'ws', empty( $problems ) ? 'OK  ' : 'FAIL', $compared, $differ );
	foreach ( $problems as $problem ) {
		echo "      ! {$problem}\n";
	}
	if ( $args['show'] ) {
		echo "   Mobile = {$mobile}\n" . implode( "\n", $shown ) . "\n";
	}
}

echo "\n{$done} locales checked, {$bad} with problems" . ( ! empty( $skip ) ? '; skipped as incomplete: ' . implode( ' ', $skip ) : '' ) . "\n";
exit( $bad > 0 ? 1 : 0 );
