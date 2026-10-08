<?php
/**
 * What changed in the English source since the last extraction, in a few lines, and which
 * locales therefore have work. Reads only temp/core-i18n/changes.json, source.json and the
 * published packs (through status.php's logic) - never the whole catalog into anyone's context.
 *
 * A reworded English sentence is a new msgid plus an obsolete old one: every new phrase is
 * matched with the obsolete phrase it most resembles (the "replaces" hint), so the translator
 * can keep the old translation's wording instead of starting from nothing.
 *
 * Writes temp/core-i18n/delta.json:
 *   { version, new: [{id, msgid, replaces: {id, msgid, similarity}|null}], returned: [id], changed: [id],
 *     obsolete: [id], locales: {<locale>: missing+stale count} }
 * Prints one summary line, then one line per locale with work (nothing when all are current).
 *
 * Run:
 *   php tests/core-i18n/delta.php [--min-similarity=55]
 * Exit code: 0 = nothing to translate, 10 = at least one locale has work.
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args( $argv, array( 'min-similarity' => 55 ) );
efb_ci18n_load_pomo( $args['wp'] );

$source  = efb_ci18n_json_read( $args['work'] . 'source.json' );
$changes = efb_ci18n_json_read( $args['work'] . 'changes.json' );
if ( ! is_array( $source ) || empty( $source['entries'] ) || ! is_array( $changes ) ) {
	efb_ci18n_fail( 'Run extract.php first' );
}
$by_id = array();
foreach ( $source['entries'] as $e ) {
	$by_id[ $e['key'] ] = $e;
}

$obsolete = array();
foreach ( (array) $changes['obsolete'] as $id ) {
	if ( isset( $by_id[ $id ] ) ) {
		$obsolete[ $id ] = $by_id[ $id ]['msgid'];
	}
}

$new = array();
foreach ( (array) $changes['new'] as $id ) {
	if ( ! isset( $by_id[ $id ] ) ) {
		continue;
	}
	$msgid   = $by_id[ $id ]['msgid'];
	$best    = null;
	$best_pc = (float) $args['min-similarity'];
	foreach ( $obsolete as $old_id => $old_msgid ) {
		$pc = 0.0;
		similar_text( $msgid, $old_msgid, $pc );
		if ( $pc >= $best_pc ) {
			$best_pc = $pc;
			$best    = array(
				'id'         => $old_id,
				'msgid'      => $old_msgid,
				'similarity' => (int) round( $pc ),
			);
		}
	}
	$new[] = array(
		'id'       => $id,
		'msgid'    => $msgid,
		'replaces' => $best,
	);
}

// Locales with work: what the published pack lacks or holds in a shape that no longer fits.
$locales = efb_ci18n_locales( $args );
$work    = array();
$unpublished = array();
foreach ( $locales as $locale => $row ) {
	$published = efb_ci18n_read_po( efb_ci18n_published_dir( $args, $row ) . EFB_CI18N_DOMAIN . '-' . $locale . '.po' );
	if ( ! $published ) {
		// No pack yet: a first full translation is a full run (efb-core-i18n), not a delta.
		$unpublished[] = $locale;
		continue;
	}
	$count = 0;
	foreach ( $by_id as $id => $e ) {
		if ( ! isset( $published[ $id ] ) || ! empty( efb_ci18n_problems( $e, $published[ $id ]['msgstr'], $row['nplurals'], $locale ) ) ) {
			$count++;
		}
	}
	$todo = efb_ci18n_json_read( $args['work'] . 'locale/' . $locale . '/todo.json' );
	if ( is_array( $todo ) && ! empty( $todo['todo'] ) ) {
		$count = max( $count, count( $todo['todo'] ) );
	}
	if ( $count > count( $by_id ) / 2 ) {
		// Mostly untranslated (a leftover stub, a locale held back on purpose): that is a full run, not a delta.
		$unpublished[] = $locale;
	} elseif ( $count > 0 ) {
		$work[ $locale ] = $count;
	}
}

efb_ci18n_json_write(
	$args['work'] . 'delta.json',
	array(
		'version'  => $changes['version'],
		'new'      => $new,
		'returned' => (array) $changes['returned'],
		'changed'  => array_map(
			function ( $c ) {
				return $c['id'];
			},
			(array) $changes['changed']
		),
		'obsolete' => array_keys( $obsolete ),
		'locales'  => $work,
		'unpublished' => $unpublished,
	)
);

printf(
	"version %s: new %d (%d reword a removed phrase), returned %d, changed %d, obsolete %d; %d of %d published locales have work; %d locales have no complete pack (not part of a delta run)\n",
	$changes['version'],
	count( $new ),
	count( array_filter( $new, function ( $n ) {
		return null !== $n['replaces'];
	} ) ),
	count( $changes['returned'] ),
	count( $changes['changed'] ),
	count( $obsolete ),
	count( $work ),
	count( $locales ) - count( $unpublished ),
	count( $unpublished )
);
foreach ( $work as $locale => $count ) {
	echo "  {$locale}: {$count}\n";
}
exit( $work ? 10 : 0 );
