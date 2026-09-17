<?php
/**
 * Where every locale stands against temp/core-i18n/source.json, without any download:
 * what is published, what is missing, what a run in progress has left to translate.
 *
 * Run:
 *   php tests/core-i18n/status.php [--json]
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args( $argv, array( 'json' => false ) );
efb_ci18n_load_pomo( $args['wp'] );

$locales = efb_ci18n_locales( $args );
$source  = efb_ci18n_json_read( $args['work'] . 'source.json' );
if ( ! is_array( $source ) || empty( $source['entries'] ) ) {
	efb_ci18n_fail( 'Run extract.php first' );
}
$ids = array();
foreach ( $source['entries'] as $e ) {
	$ids[ $e['key'] ] = $e;
}

$rows  = array();
$waves = array();
foreach ( $locales as $locale => $row ) {
	$published = efb_ci18n_read_po( efb_ci18n_published_dir( $args, $row ) . EFB_CI18N_DOMAIN . '-' . $locale . '.po' );
	$valid     = 0;
	foreach ( $ids as $id => $e ) {
		if ( isset( $published[ $id ] ) && empty( efb_ci18n_problems( $e, $published[ $id ]['msgstr'], $row['nplurals'], $locale ) ) ) {
			$valid++;
		}
	}
	$todo = efb_ci18n_json_read( $args['work'] . 'locale/' . $locale . '/todo.json' );

	// Translation order: a variant after the locale it is adapted from.
	$depth  = 0;
	$cursor = $row;
	while ( ! empty( $cursor['parent'] ) && isset( $locales[ $cursor['parent'] ] ) && $depth < 5 ) {
		$depth++;
		$cursor = $locales[ $cursor['parent'] ];
	}

	$rows[ $locale ] = array(
		'destination' => $row['destination'],
		'parent'      => isset( $row['parent'] ) ? $row['parent'] : null,
		'wave'        => $depth + 1,
		'published'   => count( $published ),
		'missing'     => count( $ids ) - $valid,
		'stale'       => count( array_diff_key( $published, $ids ) ),
		'todo'        => is_array( $todo ) ? count( $todo['todo'] ) : null,
	);
	$waves[ $depth + 1 ][] = $locale;
}

if ( $args['json'] ) {
	echo json_encode(
		array(
			'version' => $source['version'],
			'phrases' => count( $ids ),
			'waves'   => $waves,
			'locales' => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
	exit( 0 );
}

printf( "version %s, %d phrases\n%-16s %-8s %-6s %9s %8s %6s %6s\n", $source['version'], count( $ids ), 'locale', 'dest', 'wave', 'published', 'missing', 'stale', 'todo' );
foreach ( $rows as $locale => $r ) {
	printf( "%-16s %-8s %-6d %9d %8d %6d %6s\n", $locale, $r['destination'], $r['wave'], $r['published'], $r['missing'], $r['stale'], null === $r['todo'] ? '-' : $r['todo'] );
}
