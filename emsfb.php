<?php
/**
 * Plugin Name:         Easy Form Builder
 * Plugin URI:          https://whitestudio.team
 * Description:         Easily create multi-step forms included Confirmation Code and Notification email by using Easy Form Builder's drag & drop form wizard. This is the free version with limits.
 * Version:             3.2.4
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team
 * Text Domain:         easy-form-builder
 * Domain Path:         /languages
 */

/** Prevent this file from being accessed directly */
if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}
 
/** Define EMSFB_PLUGIN_FILE */
if (!defined('EMSFB_PLUGIN_FILE')) {
    define('EMSFB_PLUGIN_FILE', __FILE__);
}

/** Constant pointing to the root directory path of the plugin */
if (!defined("EMSFB_PLUGIN_DIRECTORY")) {
    define("EMSFB_PLUGIN_DIRECTORY", plugin_dir_path(__FILE__));
}

/** Constant pointing to the root directory URL of the plugin */
if (!defined("EMSFB_PLUGIN_URL")) {
    define("EMSFB_PLUGIN_URL", plugin_dir_url(__FILE__));
}

/** Constant defining the textdomain for localization */
if (!defined("EMSFB_PLUGIN_TEXTDOMAIN")) {
    define("EMSFB_PLUGIN_TEXTDOMAIN", "easy-form-builder");
}
if (!defined("WP_PLUGIN_DIR")) {
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '\plugins' );
}

/** Load main class */
require 'includes/class-Emsfb.php';

/** Main instance of plugin */
$emsfb = new Emsfb();
