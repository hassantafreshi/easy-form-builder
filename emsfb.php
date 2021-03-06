<?php
/**
 * Plugin Name:         Easy Form Builder
 * Plugin URI:          https://whitestudio.team/
 * Description:         This plugin Create from (multi step form) by drag and drop form wizard and produce form with tracking code ability , Free[Limited] version
 * Version:             1.31.6
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team/
 * Text Domain:         easy-form-builder
 * Domain Path:         /languages
 */

// Define EMSFB_PLUGIN_FILE.
if ( ! defined( 'EMSFB_PLUGIN_FILE' ) ) {
	define( 'EMSFB_PLUGIN_FILE', __FILE__ );
}

/**
 * Load main class.
 */
require 'includes/class-Emsfb.php';

/**
 * Main instance of plugin.
 */
new Emsfb();