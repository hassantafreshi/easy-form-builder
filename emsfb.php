<?php
/**
 * Plugin Name:         Easy Form Builder
 * Plugin URI:          https://whitestudio.team
 * Description:         Easily create multi-step forms with a unique Confirmation Code feature and notification emails, all without any coding knowledge required, using the easy-to-use drag and drop form wizard of Easy Form Builder. This is the free version and provides an intuitive interface and functionality to create professional forms in minutes. With the unique Confirmation Code feature, you can easily associate each submission with a specific request or user.
 * Version:             4.0.0
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team
 * Text Domain:         easy-form-builder
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
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
    define("EMSFB_PLUGIN_VERSION", "4.0.0");
}
if (!defined("EMSFB_DB_VERSION")) {
    define("EMSFB_DB_VERSION", 1.1);
}
/** Constant pointing to the root directory URL of the plugin */
if (!defined("EMSFB_PLUGIN_URL")) {
    define("EMSFB_PLUGIN_URL", plugin_dir_url(__FILE__));
}

if (!defined("WP_PLUGIN_DIR")) {
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '\plugins' );
}

//development mode
if (!defined("EMSFB_DEV_MODE")) {
    define("EMSFB_DEV_MODE", true);
}

//define server
//https://demo.whitestudio.team
if (!defined("EMSFB_SERVER_URL")) {
    if (EMSFB_DEV_MODE) {
        define("EMSFB_SERVER_URL", "https://demo.whitestudio.team");
    } else {
        define("EMSFB_SERVER_URL", "https://whitestudio.team");
    }
}
//check language is fa_IR
if (!defined("EMSFB_IS_FARSI")) {
    if (get_locale() == 'fa_IR') {
        define("CDN_ZONE_AREA", "https://cdn.easyformbuilder.ir/gh/Json-List-of-countries-states-and-cities-in-the-world/");
    } else {
        define("CDN_ZONE_AREA", "https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/");
    }
}
/** Load main class */
require 'includes/class-Emsfb.php';
/** Main instance of plugin */
$emsfb = new Emsfb();

/** Register activation hook for file access check */
register_activation_hook(__FILE__, 'emsfb_schedule_file_access_check');

/** Register action hook for scheduled file access check */
add_action('emsfb_check_file_access_after_activation', 'emsfb_perform_file_access_check_efb');

/**
 * Schedule file access check after plugin activation
 *
 * @since 4.0.0
 */
function emsfb_schedule_file_access_check() {
    if (!wp_next_scheduled('emsfb_check_file_access_after_activation')) {
        wp_schedule_single_event(time() + 20, 'emsfb_check_file_access_after_activation');
    }
}

/**
 * Perform file access check for scheduled event
 *
 * @since 4.0.0
 */
function emsfb_perform_file_access_check_efb() {
    $result = emsfb_check_file_access_efb();
    // Data is automatically saved by emsfb_check_file_access_efb
}

/**
 * Check file access for addons directory
 * This function runs once after plugin activation
 *
 * @since 4.0.0
 */
function emsfb_check_file_access_efb() {
    $vendor_path = EMSFB_PLUGIN_DIRECTORY . 'vendor';
    $temp_path = EMSFB_PLUGIN_DIRECTORY . 'temp';

    $status = true;
    $error_codes = [];
    $details = [];

    // Check vendor directory
    if (!file_exists($vendor_path)) {
        $status = false;
        $error_codes[] = 'VENDOR_NOT_EXIST';
    } else {
        $details['vendor_exists'] = true;
        if (!is_writable($vendor_path)) {
            $status = false;
            $error_codes[] = 'VENDOR_NOT_WRITABLE';
        } else {
            $details['vendor_writable'] = true;
        }
    }

    // Check temp directory
    if (!file_exists($temp_path)) {
        if (is_writable(dirname($temp_path))) {
            $create_temp = wp_mkdir_p($temp_path);
            if (!$create_temp) {
                $status = false;
                $error_codes[] = 'TEMP_CREATE_FAILED';
            } else {
                $details['temp_created'] = true;
            }
        } else {
            $status = false;
            $error_codes[] = 'TEMP_PARENT_NOT_WRITABLE';
        }
    } else {
        $details['temp_exists'] = true;
        if (!is_writable($temp_path)) {
            $status = false;
            $error_codes[] = 'TEMP_NOT_WRITABLE';
        } else {
            $details['temp_writable'] = true;
        }
    }

    // Check plugin directory write permissions
    if (!is_writable(EMSFB_PLUGIN_DIRECTORY)) {
        $status = false;
        $error_codes[] = 'PLUGIN_DIR_NOT_WRITABLE';
    } else {
        $details['plugin_writable'] = true;
    }

    // Check WordPress Filesystem
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!WP_Filesystem()) {
        $status = false;
        $error_codes[] = 'WP_FILESYSTEM_FAILED';
    } else {
        $details['wp_filesystem'] = true;
    }

    // Check disk space (minimum 10MB)
    $free_bytes = disk_free_space(EMSFB_PLUGIN_DIRECTORY);
    if (!$free_bytes || $free_bytes < (10 * 1024 * 1024)) {
        $status = false;
        $error_codes[] = 'INSUFFICIENT_DISK_SPACE';
    } else {
        $details['sufficient_space'] = true;
        $details['free_space_mb'] = round($free_bytes / (1024 * 1024), 2);
    }

    // Test actual file write
    if ($status && file_exists($vendor_path)) {
        $test_file = $vendor_path . '/test_write_efb.txt';
        $test_content = 'EFB addon test';

        if (@file_put_contents($test_file, $test_content) === false) {
            $status = false;
            $error_codes[] = 'WRITE_TEST_FAILED';
        } else {
            if (@file_get_contents($test_file) !== $test_content) {
                $status = false;
                $error_codes[] = 'READ_TEST_FAILED';
            } else {
                $details['write_test'] = true;
            }
            @unlink($test_file);
        }
    }

    // Create multilingual messages using WordPress translation functions
    $success_message = esc_html__('Addon directory is ready for file operations', 'easy-form-builder');
    $error_message = sprintf(
        esc_html__('Cannot install addons: %s', 'easy-form-builder'),
        implode(', ', $error_codes)
    );

    $result = [
        'status' => $status,
        'checked_at' => current_time('mysql'),
        'plugin_version' => EMSFB_PLUGIN_VERSION,
        'error_codes' => $error_codes,
        'details' => $details,
        'success_message' => $success_message,
        'error_message' => $error_message,
        'current_message' => $status ? $success_message : $error_message
    ];

    // Save result
    update_option('emsfb_file_access_status', $result);

    return $result;
}

/**
 * Get file access status
 *
 * @return array|null Status data or null if not checked
 * @since 4.0.0
 */
function emsfb_get_file_access_status_efb() {
    return get_option('emsfb_file_access_status', null);
}

/**
 * Check if addons can be installed
 *
 * @return bool True if ready for addon installation
 * @since 4.0.0
 */
function emsfb_is_addon_install_ready_efb() {
    $status = emsfb_get_file_access_status_efb();
    return $status && $status['status'] === true;
}

/**
 * Global function for Easy Form Builder settings access
 * Simple and fast direct call to static method
 */
if (!function_exists('get_setting_Emsfb')) {
    /**
     * Get Easy Form Builder settings globally
     * @param string $mode Return mode: 'decoded', 'pub', 'raw'
     * @return mixed Settings data
     */
    function get_setting_Emsfb($mode = 'decoded') {
        return Emsfb::get_setting_Emsfb($mode);
    }

    /**
     * Global function get_efbFunction
     * Simple and fast direct call to instance method
     */
    if (!function_exists('get_efbFunction')) {
            /**
             * Get instance of EfbFunction class globally
             * @return EfbFunction Instance of EfbFunction
             */
            function get_efbFunction() {
                return Emsfb::get_efbFunction();
            }
    }
}
