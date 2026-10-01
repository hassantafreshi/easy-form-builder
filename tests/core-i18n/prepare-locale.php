<?php
/**
 * Prepare one locale for translation: everything that needs no judgement.
 *
 * For every phrase in temp/core-i18n/source.json, in this order:
 *   1. the approved wordpress.org translation of easy-form-builder (stable, then dev,
 *      then this site's installed wordpress.org pack), used verbatim - always, so an
 *      approved string replaces an earlier machine translation. Only the tokens a
 *      translator on wordpress.org could not see are restored from the msgid (edge
 *      whitespace, an entity written as its character); origin "... (tokens restored)";
 *   2. the translation this locale already has (an unfinished run, else the published pack);
 *   3. the same msgid shipped by a migrated add-on (vendor/<slug>/languages/efb-<slug>-<locale>.po);
 *   4. otherwise the phrase goes to the to-do list the translator works through.
 * An approved or existing string that still breaks a placeholder, tag, entity, edge
 * whitespace or line break is not used; it goes to the to-do list with a note.
 *
 * Each to-do entry also carries what the locale's WordPress translators already decided:
 *   wp_core   the approved WordPress core translation of the same msgid (admin, then front end),
 *             when it keeps every token; a hint only - core may use the words in another sense;
 *   glossary  the locale team's official glossary terms the phrase contains:
 *             [English term, translation, part of speech(, note)], longest term first.
 *
 * Downloads (kept in temp/core-i18n/wporg/): the easy-form-builder exports (fresh for
 * --max-age, default 12 h), WordPress core admin + front end and the glossary (fresh for
 * --hints-max-age, default 30 days). translate.wordpress.org answers bursts with 429:
 * every download is retried, and an older copy is used when it still fails. A locale whose
 * easy-form-builder export cannot be read at all is not prepared (exit 3), because its
 * approved strings would be missing; run it again a few minutes later.
 *
 * Writes temp/core-i18n/translations/<locale>.json, temp/core-i18n/locale/<locale>/todo.json
 * and temp/core-i18n/locale/<locale>/locales.json (this locale's row alone, for
 * check-translations.php --locales=).
 *
 * Run:
 *   php tests/core-i18n/prepare-locale.php --locale=de_AT [--no-download] [--max-age=43200]
 *       [--hints-max-age=2592000] [--local-po-dir=...]
 *   php tests/core-i18n/prepare-locale.php --locale=de_AT --download-only
 *       only refresh the downloads; nothing under translations/ or locale/ is touched.
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale'        => '',
		'no-download'   => false,
		'download-only' => false,
		'max-age'       => 43200,
		'hints-max-age' => 2592000,
		'local-po-dir'  => '',
	)
);
efb_ci18n_load_pomo( $args['wp'] );

$locales = efb_ci18n_locales( $args );
$locale  = (string) $args['locale'];
if ( ! isset( $locales[ $locale ] ) ) {
	efb_ci18n_fail( "Locale {$locale} is not in " . $args['locales'] );
}
$row      = $locales[ $locale ];
$nplurals = (int) $row['nplurals'];
$source   = efb_ci18n_json_read( $args['work'] . 'source.json' );
if ( ! $args['download-only'] && ( ! is_array( $source ) || empty( $source['entries'] ) ) ) {
	efb_ci18n_fail( 'Run extract.php first: ' . $args['work'] . 'source.json is missing' );
}

// ---- downloads ----
$wporg_dir = $args['work'] . 'wporg/';
$download  = ! $args['no-download'];
$failed    = array();
$exports   = array();
foreach ( array( 'stable', 'dev' ) as $branch ) {
	$file  = $wporg_dir . $locale . '-' . $branch . '.po';
	$url   = efb_ci18n_export_url( 'wp-plugins/' . rawurlencode( $args['product'] ) . '/' . $branch, $row );
	$fetch = efb_ci18n_fetch( $url, $file, (int) $args['max-age'], $download );
	if ( $fetch['failed'] ) {
		$failed[] = $url . ' - ' . $fetch['state'];
	}
	$exports[ 'wporg-' . $branch ] = array(
		'url'    => $url,
		'status' => $fetch['state'],
		'file'   => $file,
	);
}
$hint_files = array(
	'wp-admin' => array( efb_ci18n_export_url( 'wp/dev/admin', $row ), $wporg_dir . $locale . '-wp-admin.po', '/^msgid /m' ),
	'wp-core'  => array( efb_ci18n_export_url( 'wp/dev', $row ), $wporg_dir . $locale . '-wp-core.po', '/^msgid /m' ),
	'glossary' => array( 'https://translate.wordpress.org/locale/' . $row['wporg'] . '/' . $row['set'] . '/glossary/-export/', $wporg_dir . $locale . '-glossary.csv', '/^en,/' ),
);
$hint_status = array();
foreach ( $hint_files as $name => $hint ) {
	$fetch                = efb_ci18n_fetch( $hint[0], $hint[1], (int) $args['hints-max-age'], $download, $hint[2] );
	$hint_status[ $name ] = $fetch['state'];
}

$report_downloads = function () use ( $exports, $hint_status ) {
	foreach ( $exports as $name => $export ) {
		echo "  {$name}: {$export['status']}\n";
	}
	foreach ( $hint_status as $name => $state ) {
		echo "  {$name} (hints): {$state}\n";
	}
};

if ( ! empty( $failed ) ) {
	echo "{$locale}: NOT prepared - the approved wordpress.org strings could not be read, so nothing was changed.\n";
	$report_downloads();
	efb_ci18n_fail( "  Wait a few minutes and run this command again:\n    " . implode( "\n    ", $failed ), 3 );
}
if ( $args['download-only'] ) {
	echo "{$locale}: downloads ready\n";
	$report_downloads();
	exit( 0 );
}

foreach ( $exports as $name => $export ) {
	$exports[ $name ]['entries'] = efb_ci18n_read_po( $export['file'] );
}
$local_dir = '' !== (string) $args['local-po-dir'] ? rtrim( efb_ci18n_resolve( $args['local-po-dir'], $args['root'] ), '/' ) . '/' : $args['wp'] . 'wp-content/languages/plugins/';
$local_po  = $local_dir . 'easy-form-builder-' . $locale . '.po';
$exports['local-po'] = array(
	'url'     => $local_po,
	'status'  => is_file( $local_po ) ? 'present' : 'not present',
	'entries' => efb_ci18n_read_po( $local_po ),
);

// ---- what this locale's WordPress translators decided (hints only) ----
$wp_core  = efb_ci18n_read_po( $hint_files['wp-admin'][1] ) + efb_ci18n_read_po( $hint_files['wp-core'][1] );
$glossary = efb_ci18n_read_glossary( $hint_files['glossary'][1] );

// ---- what this locale already has ----
$tr_file   = $args['work'] . 'translations/' . $locale . '.json';
$published = efb_ci18n_published_dir( $args, $row ) . EFB_CI18N_DOMAIN . '-' . $locale . '.po';
$existing  = array();
$existing_origin = array();
$tr        = efb_ci18n_json_read( $tr_file );
if ( is_array( $tr ) && isset( $tr['entries'] ) ) {
	$existing        = $tr['entries'];
	$existing_origin = isset( $tr['origin'] ) ? (array) $tr['origin'] : array();
	$seeded_from     = 'unfinished run ' . $tr_file;
} else {
	foreach ( efb_ci18n_read_po( $published ) as $id => $item ) {
		$existing[ $id ]        = $item['msgstr'];
		$existing_origin[ $id ] = '' !== $item['origin'] ? $item['origin'] : 'published';
	}
	$seeded_from = is_file( $published ) ? 'published pack ' . $published : 'nothing (first run)';
}

// ---- migrated add-ons ----
$reuse = array();
foreach ( (array) glob( $args['root'] . 'vendor/*/languages/efb-*-' . $locale . '.po' ) as $file ) {
	if ( ! preg_match( '#/vendor/([^/]+)/languages/#', str_replace( '\\', '/', $file ), $m ) ) {
		continue;
	}
	foreach ( efb_ci18n_read_po( $file ) as $id => $item ) {
		if ( ! isset( $reuse[ $id ] ) ) {
			$reuse[ $id ] = array(
				'msgstr' => $item['msgstr'],
				'origin' => 'reuse ' . $m[1],
			);
		}
	}
}

/**
 * Shape an approved/other translation to the source entry: a singular msgid
 * takes form 0; a plural msgid needs every form (known forms are returned in $known).
 */
function efb_ci18n_shape( array $entry, $msgstr, $nplurals, &$known ) {
	$known = array();
	if ( empty( $entry['plural'] ) ) {
		return is_array( $msgstr ) ? (string) reset( $msgstr ) : (string) $msgstr;
	}
	$forms = array_values( (array) $msgstr );
	$known = array_keys( array_filter( array_slice( $forms, 0, $nplurals ), 'strlen' ) );
	return array_slice( array_pad( $forms, $nplurals, '' ), 0, $nplurals );
}

$entries        = array();
$origin         = array();
$approved_forms = array();
$todo           = array();
$stats          = array();
$restored       = 0;
$with_core      = 0;
$with_glossary  = 0;
$parent_entries = array();
if ( ! empty( $row['parent'] ) ) {
	$parent_tr      = efb_ci18n_json_read( $args['work'] . 'translations/' . $row['parent'] . '.json' );
	$parent_entries = is_array( $parent_tr ) && isset( $parent_tr['entries'] ) ? $parent_tr['entries'] : array();
}

foreach ( $source['entries'] as $e ) {
	$id   = $e['key'];
	$item = null;
	$note = '';

	foreach ( array( 'wporg-stable', 'wporg-dev', 'local-po' ) as $name ) {
		if ( ! isset( $exports[ $name ]['entries'][ $id ] ) ) {
			continue;
		}
		$candidate = efb_ci18n_shape( $e, $exports[ $name ]['entries'][ $id ]['msgstr'], $nplurals, $known );
		$complete  = empty( $e['plural'] ) || count( array_filter( $candidate, 'strlen' ) ) === $nplurals;
		if ( $complete ) {
			$normalised = efb_ci18n_normalise_approved( $e, $candidate );
			$problems   = efb_ci18n_problems( $e, $normalised, $nplurals, $locale );
		} else {
			$normalised = $candidate;
			$problems   = array( 'approved plural has only ' . count( $known ) . " of {$nplurals} forms" );
		}
		if ( empty( $problems ) ) {
			$item = array( $normalised, $normalised === $candidate ? $name : $name . ' (tokens restored)' );
			if ( $normalised !== $candidate ) {
				$restored++;
			}
		} else {
			$note = "approved {$name} string not usable as is: " . implode( '; ', $problems );
			$todo_extra = array(
				'approved_candidate' => $normalised,
				'approved_origin'    => $name,
				'approved_forms'     => $known,
			);
		}
		break;
	}

	if ( null === $item && isset( $existing[ $id ] ) && empty( efb_ci18n_problems( $e, $existing[ $id ], $nplurals, $locale ) ) ) {
		$item = array( $existing[ $id ], isset( $existing_origin[ $id ] ) ? $existing_origin[ $id ] : 'published' );
		if ( is_array( $tr ) && isset( $tr['approved_forms'][ $id ] ) ) {
			$approved_forms[ $id ] = $tr['approved_forms'][ $id ];
		}
		$note = '';
	}

	if ( null === $item && '' === $note && isset( $reuse[ $id ] ) ) {
		$candidate = efb_ci18n_shape( $e, $reuse[ $id ]['msgstr'], $nplurals, $known );
		if ( empty( efb_ci18n_problems( $e, $candidate, $nplurals, $locale ) ) ) {
			$item = array( $candidate, $reuse[ $id ]['origin'] );
		}
	}

	if ( null !== $item ) {
		$entries[ $id ] = $item[0];
		$origin[ $id ]  = $item[1];
		$bucket         = preg_replace( '/ .*/', '', $item[1] );
		$stats[ $bucket ] = isset( $stats[ $bucket ] ) ? $stats[ $bucket ] + 1 : 1;
		unset( $todo_extra );
		continue;
	}

	$task = array(
		'id'          => $id,
		'msgid'       => $e['msgid'],
		'context'     => $e['context'],
		'plural'      => $e['plural'],
		'nplurals'    => $nplurals,
		'translators' => $e['translators'],
		'refs'        => array_slice( (array) $e['refs'], 0, 5 ),
		'scope'       => $e['scope'],
	);
	if ( 'active' !== $e['status'] ) {
		$task['status'] = $e['status'];
	}
	if ( '' !== $note ) {
		$task['note'] = $note;
		$task        += isset( $todo_extra ) ? $todo_extra : array();
	}
	if ( isset( $existing[ $id ] ) ) {
		$task['previous_msgstr'] = $existing[ $id ];
		$task['previous_origin'] = isset( $existing_origin[ $id ] ) ? $existing_origin[ $id ] : '';
	}
	if ( isset( $parent_entries[ $id ] ) ) {
		$task['parent_msgstr'] = $parent_entries[ $id ];
	}
	if ( isset( $wp_core[ $id ] ) ) {
		$core = efb_ci18n_shape( $e, $wp_core[ $id ]['msgstr'], $nplurals, $known );
		if ( empty( efb_ci18n_problems( $e, $core, $nplurals, $locale ) ) ) {
			$task['wp_core'] = $core;
			$with_core++;
		}
	}
	$terms = efb_ci18n_glossary_hits( $e['msgid'] . ( null !== $e['plural'] ? ' ' . $e['plural'] : '' ), $glossary );
	if ( ! empty( $terms ) ) {
		$task['glossary'] = $terms;
		$with_glossary++;
	}
	$todo[] = $task;
	unset( $todo_extra );
}

efb_ci18n_json_write(
	$tr_file,
	array(
		'locale'         => $locale,
		'entries'        => $entries,
		'origin'         => $origin,
		'approved_forms' => $approved_forms,
	)
);
$plural_numbers = efb_ci18n_plural_examples( $row['plural_forms'] );
efb_ci18n_json_write(
	$args['work'] . 'locale/' . $locale . '/todo.json',
	array(
		'locale'         => $locale,
		'nplurals'       => $nplurals,
		'plural_numbers' => $plural_numbers,
		'parent'         => isset( $row['parent'] ) ? $row['parent'] : null,
		'todo'           => $todo,
	)
);
efb_ci18n_json_write(
	$args['work'] . 'locale/' . $locale . '/locales.json',
	array(
		'source'  => 'The ' . $locale . ' row of ' . $args['locales'] . ', written by prepare-locale.php so check-translations.php can check this locale alone.',
		'locales' => array( $row ),
	)
);

echo "{$locale}: " . count( $source['entries'] ) . ' phrases, ' . count( $entries ) . ' ready, ' . count( $todo ) . " to translate\n";
echo '  seeded from: ' . $seeded_from . "\n";
foreach ( $plural_numbers as $index => $numbers ) {
	echo "  plural form {$index} is used for n = {$numbers}\n";
}
foreach ( $exports as $name => $export ) {
	echo "  {$name}: {$export['status']}, " . count( $export['entries'] ) . " translated entries\n";
}
foreach ( $hint_status as $name => $state ) {
	echo "  {$name} (hints): {$state}\n";
}
ksort( $stats );
echo '  ready by origin: ' . json_encode( $stats ) . "\n";
if ( $restored > 0 ) {
	echo "  approved strings with edge whitespace or entities restored from the msgid: {$restored}\n";
}
echo "  to-do hints: wp_core {$with_core}, glossary {$with_glossary} (glossary terms: " . count( $glossary ) . ", WordPress core strings: " . count( $wp_core ) . ")\n";
