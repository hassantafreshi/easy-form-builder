<?php
/**
 * A stand-in for the WhiteStudio language server that fails on purpose, for the end-to-end run.
 * The real server (ws-language-plugins-handler) is used for everything that should work.
 *
 *   php -S 127.0.0.1:8099 tests/e2e-language-packs/mock-server.php
 *
 * The first path segment picks how it fails; the rest is the real route of the language server:
 *
 *   http://127.0.0.1:8099/<mode>/wp-json/wl/v1/translations/easy-form-builder[/package/<locale>]
 *
 *   firewall   403 HTML "you have been blocked" page, without the X-WS-Language-Handler header
 *   norest     404 {"code":"rest_no_route"} without the header (the server plugin is not deployed yet)
 *   error500   500 with the header (the language server itself is broken)
 *   damaged    catalog and package are fine, but the package does not match the advertised checksum
 *   dlblocked  the catalog is fine, every package download is answered by a firewall page
 *   slow       answers after 15 s, longer than the client waits for a catalog (10 s)
 *
 * Every request is appended to tests/e2e-language-packs/mock-server.log.
 */

const WPTAI_HEADER = 'X-WS-Language-Handler: 1.0.0';

$path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
$query = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_QUERY );
parse_str( $query, $params );

if ( ! preg_match( '#^/([a-z0-9]+)/wp-json/wl/v1/translations/easy-form-builder(?:/package/([A-Za-z0-9_]+))?/?$#', $path, $m ) ) {
	http_response_code( 404 );
	header( 'Content-Type: text/plain' );
	echo "mock: unknown path\n";
	return true;
}
$mode    = $m[1];
$package = isset( $m[2] ) ? $m[2] : '';

file_put_contents( __DIR__ . '/mock-server.log', gmdate( 'H:i:s' ) . ' ' . $mode . ' ' . ( '' === $package ? 'catalog ' . ( isset( $params['locales'] ) ? $params['locales'] : '' ) : 'package ' . $package ) . "\n", FILE_APPEND );

function wptai_firewall_page() {
	http_response_code( 403 );
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo '<!DOCTYPE html><html><head><title>Attention Required! | Cloudflare</title></head><body><h1>Sorry, you have been blocked</h1><p>You are unable to access this website.</p></body></html>';
}

/** A valid language pack zip for one locale, built on the fly. */
function wptai_zip( $locale ) {
	$tmp = tempnam( sys_get_temp_dir(), 'wsz' );
	$zip = new ZipArchive();
	$zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'easy-form-builder-' . $locale . '.po', "msgid \"\"\nmsgstr \"\"\n\"Language: " . $locale . "\\n\"\n\nmsgid \"Save\"\nmsgstr \"Save (" . $locale . ")\"\n" );
	$zip->close();
	$bytes = (string) file_get_contents( $tmp );
	@unlink( $tmp );
	return $bytes;
}

function wptai_catalog( $mode, $params, $checksum_ok ) {
	$locales = array();
	foreach ( explode( ',', isset( $params['locales'] ) ? (string) $params['locales'] : '' ) as $locale ) {
		if ( preg_match( '/^[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?$/', $locale ) ) {
			$locales[] = $locale;
		}
	}
	$host    = $_SERVER['HTTP_HOST'];
	$entries = array();
	foreach ( $locales as $locale ) {
		$bytes     = wptai_zip( $locale );
		$entries[] = array(
			'type'       => 'plugin',
			'slug'       => 'easy-form-builder',
			'language'   => $locale,
			'version'    => '4.2.0',
			'updated'    => gmdate( 'Y-m-d H:i:s' ),
			'package'    => 'http://' . $host . '/' . $mode . '/wp-json/wl/v1/translations/easy-form-builder/package/' . $locale,
			'sha256'     => $checksum_ok ? hash( 'sha256', $bytes ) : str_repeat( '0', 64 ),
			'size'       => strlen( $bytes ),
			'autoupdate' => true,
		);
	}
	header( WPTAI_HEADER );
	header( 'Content-Type: application/json; charset=UTF-8' );
	echo json_encode( array( 'product' => 'easy-form-builder', 'known' => true, 'translations' => $entries ) );
}

switch ( $mode ) {
	case 'firewall':
		wptai_firewall_page();
		break;

	case 'norest':
		http_response_code( 404 );
		header( 'Content-Type: application/json; charset=UTF-8' );
		echo json_encode( array( 'code' => 'rest_no_route', 'message' => 'No route was found matching the URL and request method.', 'data' => array( 'status' => 404 ) ) );
		break;

	case 'error500':
		http_response_code( 500 );
		header( WPTAI_HEADER );
		header( 'Content-Type: application/json; charset=UTF-8' );
		echo json_encode( array( 'code' => 'wslph_internal', 'message' => 'The language server failed.', 'data' => array( 'status' => 500 ) ) );
		break;

	case 'damaged':
		if ( '' === $package ) {
			wptai_catalog( $mode, $params, false );
		} else {
			header( WPTAI_HEADER );
			header( 'Content-Type: application/zip' );
			echo wptai_zip( $package );
		}
		break;

	case 'dlblocked':
		if ( '' === $package ) {
			wptai_catalog( $mode, $params, true );
		} else {
			wptai_firewall_page();
		}
		break;

	case 'slow':
		sleep( 15 );
		wptai_catalog( $mode, $params, true );
		break;

	default:
		http_response_code( 404 );
		echo "mock: unknown mode\n";
}
return true;
