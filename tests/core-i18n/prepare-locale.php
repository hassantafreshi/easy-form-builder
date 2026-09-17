<?php
/**
 * Prepare one locale for translation: everything that needs no judgement.
 *
 * For every phrase in temp/core-i18n/source.json, in this order:
 *   1. the approved wordpress.org translation of easy-form-builder (stable, then dev,
 *      then this site's installed wordpress.org pack), used verbatim - always, so an
 *      approved string replaces an earlier machine translation;
 *   2. the translation this locale already has (an unfinished run, else the published pack);
 *   3. the same msgid shipped by a migrated add-on (vendor/<slug>/languages/efb-<slug>-<locale>.po);
 *   4. otherwise the phrase goes to the to-do list the translator works through.
 * An approved or existing string that breaks a placeholder, tag, entity or line break
 * is not used; it goes to the to-do list with a note.
 *
 * Writes temp/core-i18n/translations/<locale>.json and temp/core-i18n/locale/<locale>/todo.json.
 *
 * Run:
 *   php tests/core-i18n/prepare-locale.php --locale=de_AT [--no-download] [--max-age=43200] [--local-po-dir=...]
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale'       => '',
		'no-download'  => false,
		'max-age'      => 43200,
		'local-po-dir' => '',
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
if ( ! is_array( $source ) || empty( $source['entries'] ) ) {
	efb_ci18n_fail( 'Run extract.php first: ' . $args['work'] . 'source.json is missing' );
}

// ---- approved wordpress.org strings ----
$wporg_dir = $args['work'] . 'wporg/';
if ( ! is_dir( $wporg_dir ) ) {
	mkdir( $wporg_dir, 0755, true );
}
$exports = array();
foreach ( array( 'stable', 'dev' ) as $branch ) {
	$file = $wporg_dir . $locale . '-' . $branch . '.po';
	$url  = 'https://translate.wordpress.org/projects/wp-plugins/' . rawurlencode( $args['product'] ) . '/' . $branch . '/' . $row['wporg'] . '/' . $row['set'] . '/export-translations/?format=po';
	$note = 'cached';
	if ( ! $args['no-download'] && ( ! is_file( $file ) || ( time() - filemtime( $file ) ) > (int) $args['max-age'] ) ) {
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'       => 60,
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
		if ( 200 === $status && is_string( $body ) && false !== strpos( $body, 'msgid' ) ) {
			file_put_contents( $file, $body );
			$note = 'downloaded';
		} else {
			$note = 'HTTP ' . $status . ' (not used; nothing guessed)';
		}
	} elseif ( ! is_file( $file ) ) {
		$note = 'not available';
	}
	$exports[ 'wporg-' . $branch ] = array(
		'url'     => $url,
		'status'  => $note,
		'entries' => efb_ci18n_read_po( $file ),
	);
}
$local_dir = '' !== (string) $args['local-po-dir'] ? rtrim( efb_ci18n_resolve( $args['local-po-dir'], $args['root'] ), '/' ) . '/' : $args['wp'] . 'wp-content/languages/plugins/';
$local_po  = $local_dir . 'easy-form-builder-' . $locale . '.po';
$exports['local-po'] = array(
	'url'     => $local_po,
	'status'  => is_file( $local_po ) ? 'present' : 'not present',
	'entries' => efb_ci18n_read_po( $local_po ),
);

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

/** Problems that are warnings, not errors, for an approved string kept verbatim. */
function efb_ci18n_approved_problems( array $problems ) {
	return array_values(
		array_filter(
			$problems,
			function ( $p ) {
				return 0 !== strpos( $p, 'leading/trailing whitespace' ) && 0 !== strpos( $p, 'entities differ' );
			}
		)
	);
}

$entries        = array();
$origin         = array();
$approved_forms = array();
$todo           = array();
$stats          = array();
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
		$problems  = $complete ? efb_ci18n_approved_problems( efb_ci18n_problems( $e, $candidate, $nplurals, $locale ) ) : array( 'approved plural has only ' . count( $known ) . " of {$nplurals} forms" );
		if ( empty( $problems ) ) {
			$item = array( $candidate, $name );
		} else {
			$note = "approved {$name} string not usable as is: " . implode( '; ', $problems );
			$todo_extra = array(
				'approved_candidate' => $candidate,
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
efb_ci18n_json_write(
	$args['work'] . 'locale/' . $locale . '/todo.json',
	array(
		'locale'   => $locale,
		'nplurals' => $nplurals,
		'parent'   => isset( $row['parent'] ) ? $row['parent'] : null,
		'todo'     => $todo,
	)
);

echo "{$locale}: " . count( $source['entries'] ) . ' phrases, ' . count( $entries ) . ' ready, ' . count( $todo ) . " to translate\n";
echo '  seeded from: ' . $seeded_from . "\n";
foreach ( $exports as $name => $export ) {
	echo "  {$name}: {$export['status']}, " . count( $export['entries'] ) . " translated entries\n";
}
ksort( $stats );
echo '  ready by origin: ' . json_encode( $stats ) . "\n";
