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
		'pot'        => 'docs/core-i18n/easy-form-builder.pot',
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
	foreach ( array( 'catalog', 'pot', 'locales' ) as $file ) {
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

/**
 * Download a translate.wordpress.org export into $file, unless a copy younger than
 * $max_age already exists.
 *
 * translate.wordpress.org answers a burst of exports with 429 (nginx, no Retry-After),
 * so a refused or failed request is tried again after a growing pause; if it still
 * fails, an older copy is used rather than nothing.
 *
 * @param string $url      Export URL.
 * @param string $file     Cache file.
 * @param int    $max_age  Seconds a cached copy stays fresh.
 * @param bool   $download False: use only what is cached.
 * @param string $expect   Pattern a real export matches (an error page is not saved).
 * @param int[]  $waits    Pause in seconds before each attempt.
 * @return array{state: string, usable: bool, failed: bool} failed = no copy and
 *         wordpress.org did not answer (a 404 is not a failure: there is nothing to get).
 */
function efb_ci18n_fetch( $url, $file, $max_age, $download = true, $expect = '/^msgid /m', array $waits = array( 0, 20, 45, 90 ) ) {
	$cached = is_file( $file );
	if ( ! $download || ( $cached && ( time() - filemtime( $file ) ) <= $max_age ) ) {
		return array(
			'state'  => $cached ? 'cached' : 'not available',
			'usable' => $cached,
			'failed' => false,
		);
	}
	if ( ! is_dir( dirname( $file ) ) ) {
		mkdir( dirname( $file ), 0755, true );
	}
	$status = 0;
	foreach ( $waits as $wait ) {
		if ( $wait > 0 ) {
			sleep( $wait );
		}
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'       => 90,
					'ignore_errors' => true,
					'user_agent'    => 'EFB core-i18n',
				),
			)
		);
		$body   = @file_get_contents( $url, false, $context );
		$status = 0;
		foreach ( isset( $http_response_header ) ? (array) $http_response_header : array() as $header ) {
			if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $m ) ) {
				$status = (int) $m[1];
			}
		}
		if ( 200 === $status && is_string( $body ) && preg_match( $expect, $body ) ) {
			file_put_contents( $file, $body );
			return array(
				'state'  => 'downloaded',
				'usable' => true,
				'failed' => false,
			);
		}
		if ( 404 === $status ) {
			break;
		}
	}
	if ( 404 === $status ) {
		return array(
			'state'  => 'HTTP 404 (nothing on wordpress.org; nothing guessed)' . ( $cached ? ', older copy used' : '' ),
			'usable' => $cached,
			'failed' => false,
		);
	}
	return array(
		'state'  => 'HTTP ' . $status . ' after ' . count( $waits ) . ' attempts' . ( $cached ? ', older copy used' : ', no copy' ),
		'usable' => $cached,
		'failed' => ! $cached,
	);
}

/**
 * The export URL of a translate.wordpress.org project for a locale row.
 *
 * @param string $project Project path, e.g. "wp-plugins/easy-form-builder/stable" or "wp/dev/admin".
 * @param array  $row     Locale row.
 * @return string
 */
function efb_ci18n_export_url( $project, array $row ) {
	return 'https://translate.wordpress.org/projects/' . $project . '/' . $row['wporg'] . '/' . $row['set'] . '/export-translations/?format=po';
}

/**
 * Read a locale's official WordPress glossary (translate.wordpress.org CSV export:
 * en, translation, pos, description).
 *
 * @param string $file CSV file.
 * @return array<int, array{0: string, 1: string, 2: string, 3: string}> Longest English term first.
 */
function efb_ci18n_read_glossary( $file ) {
	$rows = array();
	if ( ! is_file( $file ) || false === ( $handle = fopen( $file, 'r' ) ) ) {
		return $rows;
	}
	$first = true;
	while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
		if ( $first ) {
			$first = false;
			if ( isset( $row[0] ) && 'en' === trim( (string) $row[0] ) ) {
				continue;
			}
		}
		$en = isset( $row[0] ) ? trim( (string) $row[0] ) : '';
		$tr = isset( $row[1] ) ? trim( (string) $row[1] ) : '';
		if ( '' === $en || '' === $tr ) {
			continue;
		}
		$rows[] = array( $en, $tr, isset( $row[2] ) ? trim( (string) $row[2] ) : '', isset( $row[3] ) ? trim( (string) $row[3] ) : '' );
	}
	fclose( $handle );
	usort(
		$rows,
		function ( $a, $b ) {
			return strlen( $b[0] ) - strlen( $a[0] ) ?: strcmp( $a[0], $b[0] );
		}
	);
	return $rows;
}

/**
 * Glossary terms a phrase contains, as whole words, longest first.
 *
 * @param string $text     English text (msgid and plural).
 * @param array  $glossary efb_ci18n_read_glossary() result.
 * @param int    $limit    Most terms to return.
 * @return array<int, string[]> [term, translation, pos(, note)].
 */
function efb_ci18n_glossary_hits( $text, array $glossary, $limit = 8 ) {
	$hits = array();
	$text = preg_replace( '/<[^>]+>|&[a-zA-Z#0-9]+;|%(?:\d+\$)?[sdfu%]/', ' ', $text );
	foreach ( $glossary as $row ) {
		if ( false === stripos( $text, $row[0] ) || ! preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $row[0], '/' ) . '(?![\p{L}\p{N}])/iu', $text ) ) {
			continue;
		}
		$hit = array( $row[0], $row[1], $row[2] );
		if ( '' !== $row[3] ) {
			$hit[] = function_exists( 'mb_substr' ) ? mb_substr( $row[3], 0, 60 ) : substr( $row[3], 0, 60 );
		}
		$hits[] = $hit;
		if ( count( $hits ) >= $limit ) {
			break;
		}
	}
	return $hits;
}

/**
 * Restore, in an approved translation, the tokens a translator on wordpress.org could
 * not see: the msgid's leading/trailing whitespace, an entity written as its character
 * ("…" or "..." for &hellip;), and an entity the msgid does not have written back as its
 * character. Every other character stays as approved.
 *
 * @param array        $entry  Source entry (msgid, plural).
 * @param string|array $msgstr Approved translation, shaped to the entry.
 * @return string|array
 */
function efb_ci18n_normalise_approved( array $entry, $msgstr ) {
	$is_plural = is_array( $msgstr );
	$forms     = $is_plural ? array_values( $msgstr ) : array( (string) $msgstr );
	foreach ( $forms as $index => $form ) {
		// A byte-order mark or word joiner that came along with a copy-paste is never part of the text
		// (approved pt_PT "Image" and nl_NL "Settings" start with one).
		$form = preg_replace( '/[\x{FEFF}\x{2060}]/u', '', (string) $form );
		if ( '' === trim( $form ) ) {
			continue;
		}
		$reference = ( ! empty( $entry['plural'] ) && $index > 0 ) ? $entry['plural'] : $entry['msgid'];

		list( $lead, $trail ) = efb_ci18n_edges( $reference );
		$form                 = $lead . preg_replace( array( '/^\s+/u', '/\s+$/u' ), '', $form ) . $trail;

		$want = array_count_values( efb_ci18n_entities( $reference ) );
		$have = array_count_values( efb_ci18n_entities( $form ) );
		foreach ( $want as $entity => $count ) {
			$missing = $count - ( isset( $have[ $entity ] ) ? $have[ $entity ] : 0 );
			$char    = html_entity_decode( $entity, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// A bare "&" only: the "&" that starts another entity is not the character.
			$pattern = '&' === $char ? '/&(?![a-zA-Z][a-zA-Z0-9]*;|#\d+;|#x[0-9a-fA-F]+;)/' : '/' . preg_quote( $char, '/' ) . '/u';
			for ( ; $missing > 0; $missing-- ) {
				if ( $char !== $entity && preg_match( $pattern, $form, $m, PREG_OFFSET_CAPTURE ) ) {
					$form = substr_replace( $form, $entity, $m[0][1], strlen( $char ) );
				} elseif ( '&hellip;' === $entity && false !== ( $pos = strpos( $form, '...' ) ) ) {
					$form = substr_replace( $form, $entity, $pos, 3 );
				} else {
					break;
				}
			}
		}
		foreach ( array_keys( $have ) as $entity ) {
			if ( isset( $want[ $entity ] ) || in_array( $entity, array( '&lt;', '&gt;' ), true ) || ( '&larr;' === $entity && isset( $want['&rarr;'] ) ) ) {
				continue;
			}
			$form = str_replace( $entity, html_entity_decode( $entity, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $form );
		}
		$forms[ $index ] = $form;
	}
	return $is_plural ? $forms : $forms[0];
}

/**
 * Which numbers take which plural form, e.g. for ru_RU: 0 => "1, 21, 101, …", 1 => "2-4, 22-24, …".
 * The order of the forms is the locale's, and it is not always singular first (lv: form 0 is 0 and 10-20).
 *
 * @param string $plural_forms "nplurals=3; plural=...;".
 * @return array<int, string> Form index => sample numbers.
 */
function efb_ci18n_plural_examples( $plural_forms ) {
	if ( ! preg_match( '/plural\s*=\s*([^;]+)/', (string) $plural_forms, $m ) || ! class_exists( 'Plural_Forms' ) ) {
		return array();
	}
	try {
		$plural = new Plural_Forms( trim( $m[1] ) );
	} catch ( Exception $e ) {
		return array();
	}
	$forms = array();
	foreach ( array_merge( range( 0, 30 ), range( 100, 112 ), array( 121, 122, 125, 1000, 1001, 1002, 1005, 1011, 1021 ) ) as $n ) {
		$forms[ $plural->get( $n ) ][] = $n;
	}
	ksort( $forms );
	$out = array();
	foreach ( $forms as $index => $numbers ) {
		$parts = array();
		$start = null;
		$prev  = null;
		foreach ( $numbers as $n ) {
			if ( null !== $prev && $n === $prev + 1 ) {
				$prev = $n;
				continue;
			}
			if ( null !== $start ) {
				$parts[] = $start === $prev ? (string) $start : $start . '-' . $prev;
			}
			$start = $n;
			$prev  = $n;
		}
		$parts[]       = $start === $prev ? (string) $start : $start . '-' . $prev;
		$out[ $index ] = implode( ', ', $parts );
	}
	return $out;
}

/**
 * Headers every file built from source.json carries.
 *
 * @param string $version Plugin version.
 * @param string $date    POT creation date (YYYY-MM-DD).
 * @return array<string, string>
 */
function efb_ci18n_headers( $version, $date ) {
	return array(
		'Project-Id-Version'        => 'Easy Form Builder ' . $version,
		'Report-Msgid-Bugs-To'      => 'https://whitestudio.team/',
		'MIME-Version'              => '1.0',
		'Content-Type'              => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'POT-Creation-Date'         => $date . ' 00:00+0000',
		'X-Generator'               => 'EFB tests/core-i18n/build.php',
		'X-Domain'                  => EFB_CI18N_DOMAIN,
	);
}

/**
 * @param array             $e            Source entry.
 * @param string|array|null $translation  Translation, or null for the template.
 * @param string            $origin       Origin comment.
 * @return Translation_Entry
 */
function efb_ci18n_entry( array $e, $translation = null, $origin = '' ) {
	$data = array(
		'singular'           => $e['msgid'],
		'extracted_comments' => '' !== (string) $e['translators'] ? 'translators: ' . $e['translators'] : '',
		'references'         => (array) $e['refs'],
		'flags'              => preg_match( '/%(\d+\$)?[sdfu]/', $e['msgid'] ) ? array( 'php-format' ) : array(),
	);
	if ( null !== $e['context'] ) {
		$data['context'] = $e['context'];
	}
	if ( null !== $e['plural'] ) {
		$data['plural']    = $e['plural'];
		$data['is_plural'] = true;
	}
	if ( null !== $translation ) {
		$data['translations'] = (array) $translation;
	}
	if ( '' !== $origin ) {
		$data['translator_comments'] = 'origin: ' . $origin;
	}
	return new Translation_Entry( $data );
}

/**
 * Write the template (.pot) of the packs: every phrase of source.json, in English.
 *
 * @param array  $source source.json.
 * @param string $file   Output file.
 * @param string $date   POT creation date (YYYY-MM-DD).
 * @return void
 */
function efb_ci18n_write_pot( array $source, $file, $date ) {
	$pot = new PO();
	$pot->set_headers(
		array_merge(
			efb_ci18n_headers( (string) $source['version'], $date ),
			array(
				'PO-Revision-Date' => 'YEAR-MO-DA HO:MI+ZONE',
				'Last-Translator'  => 'FULL NAME <EMAIL@ADDRESS>',
				'Language-Team'    => 'LANGUAGE <LL@li.org>',
			)
		)
	);
	foreach ( $source['entries'] as $e ) {
		$pot->add_entry( efb_ci18n_entry( $e ) );
	}
	if ( ! is_dir( dirname( $file ) ) ) {
		mkdir( dirname( $file ), 0755, true );
	}
	$pot->export_to_file( $file );
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
 * The phrases the builder's JavaScript composes around a translation with the "%s" left empty (the
 * desktop tab: val-efb.js, pro_els-efb.js, new-efb.js) or replaced by the translated "Mobile" (the
 * mobile tab). A translation has to read cleanly both ways, which "(%s)" and "de %s" do not.
 *
 * @return string[]
 */
function efb_ci18n_blanked_msgids() {
	return array(
		'%s width',
		'%s Label Position',
		'%s Label size',
		'%s Label | Align',
		'%s Description | Align',
		'%s Buttons | Align',
		'%s Checked Color',
		'%s copied!',
		'%s Location',
	);
}

/**
 * Words that cannot begin or end a label: what is left of "Color de %s" when the %s is empty.
 *
 * @return string[]
 */
function efb_ci18n_dangling_words() {
	return array(
		// English, Romance, Germanic and Nordic prepositions, conjunctions and articles.
		'of', 'the', 'a', 'an', 'for', 'to', 'with', 'and',
		'de', 'del', 'della', 'dello', 'dei', 'degli', 'delle', 'di', 'da', 'do', 'dos', 'das', 'du', 'des', 'et', 'y', 'en',
		'para', 'por', 'con', 'sin', 'al', 'em', 'per', 'su', 'pour', 'avec', 'sur', 'dans', 'par', 'à', 'au', 'aux',
		'el', 'la', 'las', 'los', 'lo', 'le', 'les', 'il',
		'van', 'von', 'vom', 'zu', 'zur', 'zum', 'und', 'der', 'die', 'den', 'dem', 'het', 'een', 'ein', 'eine',
		'für', 'mit', 'auf', 'bei', 'nach', 'aus', 'voor', 'naar', 'met', 'bij',
		'og', 'och', 'af', 'av', 'på', 'för', 'til', 'till', 'ved', 'vid', 'med', 'om',
		// Slavic, Baltic, Finno-Ugric, Turkic, Indonesian, Vietnamese, Thai, Hebrew, Persian.
		'ir', 'na', 'z', 'ze', 'w', 'we', 'v', 've', 'k', 'o', 'u', 'i', 'dla', 'pro', 'pre', 'pri', 'od', 'za', 'pod', 'nad',
		'в', 'с', 'к', 'о', 'у', 'и', 'а', 'на', 'по', 'за', 'из', 'от', 'до', 'для', 'при', 'від', 'з', 'із',
		'pentru', 'cu', 'ale', 'az', 'ja',
		'için', 'ile', 'gibi', 'kadar', 'göre',
		'untuk', 'dari', 'pada', 'ke', 'yang', 'dengan',
		'của', 'và', 'cho', 'với', 'ของ', 'สำหรับ', 'של', 'برای', 'از',
	);
}

/**
 * Problems of a translation of a phrase in efb_ci18n_blanked_msgids(): what it leaves on the desktop
 * tab, where the "%s" is empty.
 *
 * @param string $form Translation.
 * @return string[]
 */
function efb_ci18n_blanked_problems( $form ) {
	$problems = array();
	if ( preg_match( '/[(\[{（［｛【「『«‹]\s*%s\s*[)\]}）］｝】」』»›]/u', $form ) ) {
		$problems[] = 'the "%s" sits alone in brackets: the builder empties it on the desktop tab and shows "()" (write "Width %s", not "Width (%s)")';
	}
	if ( preg_match( '/%s[-‐–\'’]|[-‐–\'’]%s/u', $form ) ) {
		$problems[] = 'the "%s" is joined to a word with a hyphen or apostrophe: empty, it leaves a stray "-" or apostrophe';
	}
	$blank = trim( preg_replace( '/\s+/u', ' ', str_replace( '%s', '', $form ) ) );
	if ( '' === $blank ) {
		return array_merge( $problems, array( 'nothing is left when the "%s" is empty' ) );
	}
	$lower    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $blank ) : strtolower( $blank );
	$words    = preg_split( '/[\s\'’]+/u', $lower );
	$dangling = efb_ci18n_dangling_words();
	if ( preg_match( '/^(?:[,.:;!?)\]}\-–—、。，：]|[の的之의])/u', $blank ) ) {
		$problems[] = 'with the "%s" empty it starts with a particle or punctuation mark ("' . $blank . '"); write it as a compound ("%sWidth") or put the "%s" last ("Width %s")';
	} elseif ( in_array( $words[0], $dangling, true ) ) {
		$problems[] = 'with the "%s" empty it starts with the dangling word "' . $words[0] . '" ("' . $blank . '")';
	}
	if ( preg_match( '/[:(\[{\-–—,\/、，：]$/u', $blank ) ) {
		$problems[] = 'with the "%s" empty it ends with a colon, bracket or dash ("' . $blank . '"); drop the colon and put the "%s" last';
	} elseif ( in_array( end( $words ), $dangling, true ) ) {
		$problems[] = 'with the "%s" empty it ends with the dangling word "' . end( $words ) . '" ("' . $blank . '"); drop the word ("Color %s", not "Color of %s")';
	} elseif ( preg_match( '/(?<![\p{L}\p{N}])(' . implode( '|', $dangling ) . ')[\'’]?\s*%s/iu', $form, $m ) ) {
		$problems[] = 'the word "' . $m[1] . '" stands right before the "%s", so it hangs when the "%s" is empty';
	}
	return $problems;
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
		// Thai, Lao, Khmer and Burmese put U+200B between words on purpose; nowhere else is it wanted.
		if ( preg_match( '/[\x{FEFF}\x{2060}]/u', $form ) || ( ! preg_match( '/^(th|lo|km|my)(_|$)/', $locale ) && false !== strpos( $form, "\xE2\x80\x8B" ) ) ) {
			$problems[] = 'invisible character in the text (U+FEFF, U+2060 or U+200B)';
		}
		if ( empty( $entry['context'] ) && in_array( $entry['msgid'], efb_ci18n_blanked_msgids(), true ) ) {
			$problems = array_merge( $problems, efb_ci18n_blanked_problems( $form ) );
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
