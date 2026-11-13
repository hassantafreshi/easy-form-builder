<?php
/**
 * Seed demo forms + front-end pages for the tutorial pipeline.
 *
 * Run through WP-CLI (see seed.sh):   wp eval-file seed-forms.php
 *
 * Reads forms/*.json (structures exported from forms the plugin itself builds from its own
 * templates: "Contact us", "Customer Feedback", "Event Registration"), upserts them into
 * {prefix}emsfb_form by name, creates one published page per form holding the plugin shortcode and
 * writes $EFB_WORK/site-map.json so the capture script knows ids and URLs.
 * Modelled on tests/seed-*.php of the plugin repo (same table, same shortcode), but it does not need
 * to live inside wp-content/plugins/easy-form-builder/tests.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run with: wp eval-file seed-forms.php\n" );
}

global $wpdb;
$table       = $wpdb->prefix . 'emsfb_form';
$admin_email = get_option( 'admin_email' );
$dir         = trailingslashit( dirname( __FILE__ ) ) . 'forms/';
$site_map    = array( 'site_url' => home_url(), 'forms' => array() );

foreach ( (array) glob( $dir . '*.json' ) as $file ) {
	$def = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $def ) || empty( $def['structure'] ) ) {
		WP_CLI::warning( 'skipping unreadable ' . basename( $file ) );
		continue;
	}
	$structure = $def['structure'];
	// first element is the form-settings object; point notifications at the demo admin address.
	if ( isset( $structure[0]['type'] ) && 'form' === $structure[0]['type'] ) {
		$structure[0]['email'] = $admin_email;
	}
	$json = wp_json_encode( $structure, JSON_UNESCAPED_UNICODE );

	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM {$table} WHERE form_name = %s ORDER BY form_id ASC LIMIT 1", $def['name'] ) );
	$row      = array(
		'form_name'       => $def['name'],
		'form_structer'   => $json,
		'form_email'      => $admin_email,
		'form_created_by' => 1,
		'form_type'       => isset( $def['form_type'] ) ? $def['form_type'] : 'form',
	);
	if ( $existing > 0 ) {
		$wpdb->update( $table, $row, array( 'form_id' => $existing ) );
		$form_id = $existing;
		$action  = 'updated';
	} else {
		$row['form_create_date'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );
		$form_id = (int) $wpdb->insert_id;
		$action  = 'inserted';
	}
	if ( $form_id < 1 ) {
		WP_CLI::error( 'could not save form ' . $def['name'] . ': ' . $wpdb->last_error );
	}
	wp_cache_delete( 'efb_form_' . md5( 'form_' . $form_id ), 'emsfb' );
	wp_cache_delete( 'efb_form_' . $form_id, 'emsfb' );

	$slug = $def['slug'];
	$page = get_page_by_path( $slug );
	$data = array(
		'post_title'   => $def['name'],
		'post_name'    => $slug,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '[EMS_Form_Builder id="' . $form_id . '"]',
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		$page_id    = wp_update_post( $data, true );
	} else {
		$page_id = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $page_id ) ) {
		WP_CLI::error( 'page for ' . $def['name'] . ': ' . $page_id->get_error_message() );
	}
	$site_map['forms'][ $slug ] = array(
		'form_id' => $form_id,
		'name'    => $def['name'],
		'page_id' => (int) $page_id,
		'url'     => get_permalink( $page_id ),
	);
	WP_CLI::log( sprintf( '  form %-20s id=%d %s  page=%s', $def['name'], $form_id, $action, get_permalink( $page_id ) ) );
}

$work = getenv( 'EFB_WORK' );
if ( $work && is_dir( $work ) ) {
	file_put_contents( $work . '/site-map.json', wp_json_encode( $site_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
}
WP_CLI::success( count( $site_map['forms'] ) . ' forms seeded' );
