<?php
/**
 * Plugin Name:         Easy Form Builder
 * Plugin URI:          https://whitestudio.team
 * Description:         Easily create multi-step forms with a unique Confirmation Code feature and notification emails, all without any coding knowledge required, using the easy-to-use drag and drop form wizard of Easy Form Builder. This is the free version and provides an intuitive interface and functionality to create professional forms in minutes. With the unique Confirmation Code feature, you can easily associate each submission with a specific request or user.
 * Version:             3.8.6
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team
 * Text Domain:         easy-form-builder
 * Domain Path:         /languages
 */

/** t Prevent this file from being accessed directly */
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

if (!defined("EMSFB_PLUGIN_VERSION")) {
    define("EMSFB_PLUGIN_VERSION", "3.8.6");
}

/** Constant pointing to the root directory URL of the plugin */
if (!defined("EMSFB_PLUGIN_URL")) {
    define("EMSFB_PLUGIN_URL", plugin_dir_url(__FILE__));
}



if (!defined("WP_PLUGIN_DIR")) {
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '\plugins' );
}

/** Load main class */
require 'includes/class-Emsfb.php';

/** Main instance of plugin */
$emsfb = new Emsfb();
