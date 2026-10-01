<?php
/**
 * Everything one locale must pass before a translator reports it complete:
 *   1. tests/addon-i18n/check-translations.php on this locale alone (the shared final gate);
 *   2. build.php --only=<locale>: the .po, the .mo (msgfmt --check-format) and the .l10n.php,
 *      built into temp/core-i18n/build/. Never published: publishing is build.php --publish,
 *      run by efb-core-i18n (Opus) after every locale is complete.
 * Exit 0 only when both pass.
 *
 * Every error is printed. Warnings are not errors, and an English variant has one for nearly
 * every phrase (msgstr equals msgid), so they are counted per kind with a few examples
 * (--warnings=N examples per kind, --warnings=all for every one).
 *
 * Run:
 *   php tests/core-i18n/check-locale.php --locale=de_AT [--warnings=20]
 *   php tests/core-i18n/check-locale.php --locale=de_AT --quiet     one line: "de_AT: PASS (0 errors, 3 warnings)"
 */

require __DIR__ . '/lib.php';

$args = efb_ci18n_args(
	$argv,
	array(
		'locale'   => '',
		'quiet'    => false,
		'warnings' => 20,
	)
);

$locales = efb_ci18n_locales( $args );
$locale  = (string) $args['locale'];
if ( ! isset( $locales[ $locale ] ) ) {
	efb_ci18n_fail( "Locale {$locale} is not in " . $args['locales'] );
}
$one = $args['work'] . 'locale/' . $locale . '/locales.json';
if ( ! is_file( $one ) || ! is_file( $args['work'] . 'translations/' . $locale . '.json' ) ) {
	efb_ci18n_fail( "Run prepare-locale.php --locale={$locale} first" );
}

$php = escapeshellarg( PHP_BINARY );
exec(
	$php . ' ' . escapeshellarg( dirname( __DIR__ ) . '/addon-i18n/check-translations.php' )
	. ' --source=' . escapeshellarg( $args['work'] . 'source.json' )
	. ' --translations=' . escapeshellarg( rtrim( $args['work'] . 'translations', '/' ) )
	. ' --locales=' . escapeshellarg( $one ) . ' --no-wp 2>&1',
	$check_out,
	$check_code
);
$errors   = array();
$warnings = array();
foreach ( $check_out as $line ) {
	if ( 0 === strpos( $line, '[ERROR] ' ) ) {
		$errors[] = $line;
	} elseif ( 0 === strpos( $line, '[WARNING] ' ) ) {
		// "[WARNING] <locale> "<msgid as JSON>": <message> (<details>)" -> the message is the kind.
		$kind = preg_match( '/^\[WARNING\] \S+ "(?:[^"\\\\]|\\\\.)*": (.*)$/u', $line, $m ) ? $m[1] : substr( $line, 10 );
		$kind = trim( preg_replace( '/\s*\(.*$/', '', $kind ) );
		$warnings[ $kind ][] = $line;
	}
}
$warning_count = array_sum( array_map( 'count', $warnings ) );

$paths = '';
foreach ( array( 'root', 'work', 'catalog', 'locales', 'bundled', 'ws-storage', 'wp' ) as $name ) {
	$paths .= ' --' . $name . '=' . escapeshellarg( $args[ $name ] );
}
exec( $php . ' ' . escapeshellarg( __DIR__ . '/build.php' ) . $paths . ' --only=' . escapeshellarg( $locale ) . ' 2>&1', $build_out, $build_code );

$ok      = 0 === $check_code && 0 === $build_code;
$verdict = $ok
	? "{$locale}: PASS (0 errors, {$warning_count} warnings)"
	: "{$locale}: FAIL (" . count( $errors ) . " errors, {$warning_count} warnings; check-translations exit {$check_code}, build exit {$build_code})";

if ( ! $args['quiet'] ) {
	echo "== check-translations.php ({$locale}): " . count( $errors ) . " errors, {$warning_count} warnings\n";
	foreach ( $errors as $line ) {
		echo $line . "\n";
	}
	$per_kind = 'all' === $args['warnings'] ? PHP_INT_MAX : max( 0, (int) $args['warnings'] );
	foreach ( $warnings as $kind => $lines ) {
		echo "warnings, {$kind}: " . count( $lines ) . ( count( $lines ) > $per_kind ? " (first {$per_kind}; --warnings=all for every one)" : '' ) . "\n";
		foreach ( array_slice( $lines, 0, $per_kind ) as $line ) {
			echo $line . "\n";
		}
	}
	echo "== build.php --only={$locale} (not published)\n" . implode( "\n", $build_out ) . "\n";
}
echo $verdict . "\n";
exit( $ok ? 0 : 1 );
