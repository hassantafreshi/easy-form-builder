<?php
/**
 * emsfb_email_next_steps_efb(): the data behind the "what you can do now" box
 * of the email test. Standalone - WordPress functions are stubbed.
 * Run: php tests/test-email-next-steps.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/efb-next-steps-' . getmypid() );
@mkdir( WP_PLUGIN_DIR );
@mkdir( WP_PLUGIN_DIR . '/easy-wp-smtp' ); // "already installed"

$GLOBALS['efb_test_caps']    = array( 'install_plugins' => true, 'activate_plugins' => true );
$GLOBALS['efb_test_filters'] = array();

function __( $t, $d = '' ) { return $t; }
function apply_filters( $hook, $value ) { return isset( $GLOBALS['efb_test_filters'][ $hook ] ) ? call_user_func( $GLOBALS['efb_test_filters'][ $hook ], $value ) : $value; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['efb_test_caps'][ $cap ] ); }
function admin_url( $path = '' ) { return 'https://site.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://site.test/wp-admin/network/' . $path; }
$GLOBALS['efb_test_multisite'] = false;
function is_multisite() { return ! empty( $GLOBALS['efb_test_multisite'] ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( (string) $t ) ), '-' ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function esc_url_raw( $u, $protocols = null ) { return preg_match( '#^https://#', $u ) ? $u : ''; }

require_once __DIR__ . '/../includes/email-next-steps-efb.php';

$pass = 0; $fail = 0;
function check( $label, $ok ) { global $pass, $fail; if ( $ok ) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n"; } }

$d = emsfb_email_next_steps_efb();
check( 'admin who may install gets the installer base', $d['can_install'] && $d['install_base'] === 'https://site.test/wp-admin/plugin-install.php?tab=plugin-information&plugin=' );
check( 'five services by default', count( $d['services'] ) === 5 );
check( 'every service has a wordpress.org slug and an https site', array_reduce( $d['services'], function ( $ok, $s ) { return $ok && preg_match( '/^[a-z0-9-]+$/', $s['slug'] ) && 0 === strpos( $s['site'], 'https://' ); }, true ) );
check( 'a plugin folder that exists is reported as installed', $d['services'][0]['slug'] === 'easy-wp-smtp' && $d['services'][0]['installed'] === true && $d['services'][1]['installed'] === false );
check( 'six guides, one per record plus overview, services and smtp', count( $d['docs'] ) === 6 && isset( $d['docs']['spf'], $d['docs']['dkim'], $d['docs']['dmarc'], $d['docs']['overview'], $d['docs']['services'], $d['docs']['smtp'] ) );
check( 'guide slugs are plain slugs', array_reduce( $d['docs'], function ( $ok, $s ) { return $ok && preg_match( '/^[a-z0-9-]+$/', $s ); }, true ) );

// Multisite: a subsite's plugin-install.php redirects to the network screen and
// drops the query string, so the box links the network screen directly.
$GLOBALS['efb_test_multisite'] = true;
$d = emsfb_email_next_steps_efb();
check( 'multisite links the network plugin installer', $d['install_base'] === 'https://site.test/wp-admin/network/plugin-install.php?tab=plugin-information&plugin=' );
$GLOBALS['efb_test_multisite'] = false;

// An editor cannot install plugins: no installer link, no Plugins link.
$GLOBALS['efb_test_caps'] = array();
$d = emsfb_email_next_steps_efb();
check( 'a user without install_plugins gets no installer base', ! $d['can_install'] && '' === $d['install_base'] && '' === $d['plugins_url'] );

// DISALLOW_FILE_MODS wins over the capability.
$GLOBALS['efb_test_caps'] = array( 'install_plugins' => true, 'activate_plugins' => true );
define( 'DISALLOW_FILE_MODS', true );
$d = emsfb_email_next_steps_efb();
check( 'DISALLOW_FILE_MODS disables the installer even for an admin', ! $d['can_install'] && '' === $d['install_base'] );

// A host filters the list: adds its own service, drops one, and a broken entry is ignored.
$GLOBALS['efb_test_filters']['emsfb_email_sending_services'] = function ( $list ) {
	$list   = array_filter( $list, function ( $s ) { return 'postmark' !== $s['id']; } );
	$list[] = array( 'id' => 'host-mail', 'name' => 'Host <b>Mail</b>', 'slug' => 'Host Mail!!', 'note' => 'ours', 'site' => 'http://insecure.example' );
	$list[] = array( 'name' => 'no slug' );
	return $list;
};
$d = emsfb_email_next_steps_efb();
$ids = array_map( function ( $s ) { return $s['id']; }, $d['services'] );
check( 'filter removed postmark and added the host service', ! in_array( 'postmark', $ids, true ) && in_array( 'host-mail', $ids, true ) && 5 === count( $d['services'] ) );
$host = $d['services'][ count( $d['services'] ) - 1 ];
check( 'filtered entry is sanitised (name, slug) and an http site is dropped', $host['name'] === 'Host Mail' && $host['slug'] === 'hostmail' && $host['site'] === '' );

$GLOBALS['efb_test_filters']['emsfb_email_next_steps_docs'] = function ( $docs ) { $docs['spf'] = 'My SPF Guide'; $docs['bad'] = array( 'x' ); return $docs; };
$d = emsfb_email_next_steps_efb();
check( 'docs filter: slug normalised, non-string entry dropped', $d['docs']['spf'] === 'my-spf-guide' && ! isset( $d['docs']['bad'] ) );

@rmdir( WP_PLUGIN_DIR . '/easy-wp-smtp' ); @rmdir( WP_PLUGIN_DIR );
echo "RESULTS: $pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
