<?php
/**
 * Read or set the site locale, for harnesses that need to render a form in RTL.
 *
 * With no argument it prints the current value and changes nothing, which is
 * how a run captures what to put back afterwards. Pass a locale to switch, or
 * an empty string for the site default.
 *
 *   php tests/set-locale-efb.php            print the current locale
 *   php tests/set-locale-efb.php fa_IR      switch to Persian
 *   php tests/set-locale-efb.php ""         back to the default
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

require_once $wp_load;

if ( isset( $argv[1] ) ) {
	update_option( 'WPLANG', sanitize_text_field( $argv[1] ) );
}

echo (string) get_option( 'WPLANG' );
