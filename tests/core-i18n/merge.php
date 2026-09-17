<?php
/**
 * Merge a chunk of translations into a locale, keeping only valid ones.
 *
 * Chunk file:
 *   {
 *     "entries": { "<id>": "<msgstr>" | ["<form 0>", "<form 1>"] },
 *     "origin":  { "<id>": "glossary" }            optional, default "sonnet (<locale>)"
 *   }
 * <id> is the "id" printed by todo.php ("<context><msgid>" for a phrase with context).
 *
 * A translation is merged when it keeps every placeholder, tag, entity, edge
 * whitespace and line break of the msgid, has every plural form, and keeps the
 * approved forms of a partly approved plural verbatim. The rest stays on the
 * to-do list with the reason, printed here.
 *
 * Only phrases on the to-do list are accepted, unless --allow-existing (a fix-up
 * of a phrase check-translations.php reported).
 *
 * Run:
 *   php tests/core-i18n/merge.php --locale=de_AT --file=temp/core-i18n/locale/de_AT/chunk-001.json [--allow-existing]
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale'         => '',
		'file'           => '',
		'allow-existing' => false,
	)
);

$locales = efb_ci18n_locales( $args );
$locale  = (string) $args['locale'];
if ( ! isset( $locales[ $locale ] ) ) {
	efb_ci18n_fail( "Locale {$locale} is not in " . $args['locales'] );
}
$nplurals  = (int) $locales[ $locale ]['nplurals'];
$source    = efb_ci18n_json_read( $args['work'] . 'source.json' );
$tr_file   = $args['work'] . 'translations/' . $locale . '.json';
$todo_file = $args['work'] . 'locale/' . $locale . '/todo.json';
$tr        = efb_ci18n_json_read( $tr_file );
$todo_data = efb_ci18n_json_read( $todo_file );
$chunk     = efb_ci18n_json_read( efb_ci18n_resolve( $args['file'], $args['root'] ) );

if ( ! is_array( $source ) || ! is_array( $tr ) || ! is_array( $todo_data ) ) {
	efb_ci18n_fail( "Run prepare-locale.php --locale={$locale} first" );
}
if ( ! is_array( $chunk ) || ! isset( $chunk['entries'] ) || ! is_array( $chunk['entries'] ) ) {
	efb_ci18n_fail( 'Unreadable chunk ' . $args['file'] . ' (expects {"entries": {...}})' );
}

$by_id = array();
foreach ( $source['entries'] as $e ) {
	$by_id[ $e['key'] ] = $e;
}
$todo = array();
foreach ( $todo_data['todo'] as $index => $task ) {
	$todo[ $task['id'] ] = $index;
}

$merged   = 0;
$rejected = array();
foreach ( $chunk['entries'] as $id => $msgstr ) {
	$id = (string) $id;
	if ( ! isset( $by_id[ $id ] ) ) {
		$rejected[ $id ] = array( 'not a phrase of this version' );
		continue;
	}
	$in_todo = isset( $todo[ $id ] );
	if ( ! $in_todo && ! $args['allow-existing'] ) {
		$rejected[ $id ] = array( 'not on the to-do list (already translated; use --allow-existing for a fix-up)' );
		continue;
	}
	$problems = efb_ci18n_problems( $by_id[ $id ], $msgstr, $nplurals, $locale );

	$task = $in_todo ? $todo_data['todo'][ $todo[ $id ] ] : array();
	if ( empty( $problems ) && ! empty( $task['approved_forms'] ) && is_array( $msgstr ) && isset( $task['approved_candidate'] ) && is_array( $task['approved_candidate'] ) && false !== strpos( (string) $task['note'], 'approved plural has only' ) ) {
		foreach ( $task['approved_forms'] as $form ) {
			if ( ! isset( $msgstr[ $form ] ) || $msgstr[ $form ] !== $task['approved_candidate'][ $form ] ) {
				$problems[] = "approved form {$form} must stay verbatim";
			}
		}
	}

	if ( ! empty( $problems ) ) {
		$rejected[ $id ] = $problems;
		if ( $in_todo ) {
			$todo_data['todo'][ $todo[ $id ] ]['error'] = $problems;
		}
		continue;
	}

	$tr['entries'][ $id ] = $msgstr;
	$tr['origin'][ $id ]  = isset( $chunk['origin'][ $id ] ) ? (string) $chunk['origin'][ $id ] : 'sonnet (' . $locale . ')';
	if ( ! empty( $task['approved_forms'] ) && is_array( $msgstr ) ) {
		$tr['approved_forms'][ $id ] = $task['approved_forms'];
	} else {
		unset( $tr['approved_forms'][ $id ] );
	}
	if ( $in_todo ) {
		unset( $todo_data['todo'][ $todo[ $id ] ] );
	}
	$merged++;
}

$todo_data['todo'] = array_values( $todo_data['todo'] );
efb_ci18n_json_write( $tr_file, $tr );
efb_ci18n_json_write( $todo_file, $todo_data );

echo "{$locale}: merged {$merged}, rejected " . count( $rejected ) . ', still to translate ' . count( $todo_data['todo'] ) . "\n";
foreach ( $rejected as $id => $problems ) {
	echo '  [REJECTED] ' . json_encode( $id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ': ' . implode( '; ', $problems ) . "\n";
}
exit( 0 );
