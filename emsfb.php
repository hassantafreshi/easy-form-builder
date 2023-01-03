<?php
/**
 * Plugin Name:         Easy Form Builder
 * Version:             3.5.7111
 * Plugin URI:          https://whitestudio.team
 * Description:         Easily create multistep forms that support confirmation codes and notifications via email by using the intuitive and easy to master Easy Form Builder's drag & drop form wizard. If you like this plugin, please consider purchasing the pro version with even more features.
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team
 * Text Domain:         easy-form-builder
 * Domain Path:         /languages
 */

// TODO: WP only pulls in the first 8 KB of the main theme file to get the header information, so make sure the version part is listed somewhere in the top

/** Prevent this file from being accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	die( "Direct access of plugin files is not allowed." );
}

/** All cool, we're in WordPress, let's load some vendor files */
require_once __DIR__ . '/vendor/autoload.php';

/** Define EMSFB_PLUGIN_FILE */
if ( ! defined( 'EMSFB_PLUGIN_FILE' ) ) {
	define( 'EMSFB_PLUGIN_FILE', __FILE__ );
}

/** Constant pointing to the root directory path of the plugin */
if ( ! defined( "EMSFB_PLUGIN_DIRECTORY" ) ) {
	define( "EMSFB_PLUGIN_DIRECTORY", plugin_dir_path( __FILE__ ) );
}

/** Constant pointing to the root directory URL of the plugin */
if ( ! defined( "EMSFB_PLUGIN_URL" ) ) {
	define( "EMSFB_PLUGIN_URL", plugin_dir_url( __FILE__ ) );
}

/** Constant defining the textdomain for localization */
if ( ! defined( "EMSFB_PLUGIN_TEXTDOMAIN" ) ) {
	define( "EMSFB_PLUGIN_TEXTDOMAIN", "easy-form-builder" );
}

if ( ! defined( "WP_PLUGIN_DIR" ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '\plugins' );
}

/**
 * Because programmers are lazy, we parse the current plugin version number from the readme.txt header
 *
 * @return string|float|int
 * @since 3.5.8
 */
if ( ! defined( 'EMSFB_PLUGIN_VERSION' ) && ! function_exists( "get_plugin_data" ) ) {

	/** For that, we need this file which normally only loads on `is_admin() === true` */
	require_once( ABSPATH . "wp-admin/includes/plugin.php" );

	/** @var string $plugin_data The data */
	$plugin_data = get_plugin_data( EMSFB_PLUGIN_FILE );

	/** One last check if the parsing was successful, then set the version globally as a constant */
	if ( isset( $plugin_data['Version'] ) ) {
		define( "EMSFB_PLUGIN_VERSION", $plugin_data['Version'] );
	} else {
		define( "EMSFB_PLUGIN_VERSION", ( string) $plugin_data['Version'] );
	}
}

/** Load main class */
require_once EMSFB_PLUGIN_DIRECTORY . '/includes/class-Emsfb.php';

/** Calling the main instance of the plugin */
new Emsfb();
