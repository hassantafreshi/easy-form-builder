<?php
/**
 * Print the next phrases a locale still needs, one compact JSON object per line, then a
 * "# next chunk: <file>" line: a chunk file name no earlier spawn has used.
 *
 * Run:
 *   php tests/core-i18n/todo.php --locale=de_AT [--limit=100] [--offset=0] [--count]
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale' => '',
		'limit'  => 100,
		'offset' => 0,
		'count'  => false,
	)
);

$file = $args['work'] . 'locale/' . $args['locale'] . '/todo.json';
$data = efb_ci18n_json_read( $file );
if ( ! is_array( $data ) || ! isset( $data['todo'] ) ) {
	efb_ci18n_fail( "No to-do list for {$args['locale']}: run prepare-locale.php first ({$file})" );
}

if ( $args['count'] ) {
	echo count( $data['todo'] ) . "\n";
	exit( 0 );
}

foreach ( array_slice( $data['todo'], max( 0, (int) $args['offset'] ), max( 1, (int) $args['limit'] ) ) as $task ) {
	echo json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
}

$next = 0;
foreach ( (array) glob( dirname( $file ) . '/chunk-*.json' ) as $chunk ) {
	if ( preg_match( '/chunk-(\d+)\.json$/', (string) $chunk, $m ) ) {
		$next = max( $next, (int) $m[1] );
	}
}
printf( "# next chunk: %s/chunk-%03d.json (%d phrases left)\n", dirname( $file ), $next + 1, count( $data['todo'] ) );
