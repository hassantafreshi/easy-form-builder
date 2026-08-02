<?php
/**
 * Plugin Name:         Easy Form Builder
 * Plugin URI:          https://whitestudio.team
 * Description:         Create multi-step WordPress forms with drag and drop, confirmation codes, notification emails, and free custom email notification templates with a built-in visual editor. Build professional contact, survey, payment, login, and registration forms without coding.
 * Version:             4.1.3
 * Author:              WhiteStudio
 * Author URI:          https://whitestudio.team
 * Text Domain:         easy-form-builder
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:         /languages
 */

if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}

if (!defined('EMSFB_PLUGIN_FILE')) {
    define('EMSFB_PLUGIN_FILE', __FILE__);
}

if (!defined("EMSFB_PLUGIN_DIRECTORY")) {
    define("EMSFB_PLUGIN_DIRECTORY", plugin_dir_path(__FILE__));
}
if (!defined("EMSFB_PLUGIN_VERSION")) {
    define("EMSFB_PLUGIN_VERSION", "4.1.30");
}
if (!defined("EMSFB_DB_VERSION")) {
    // 1.2 adds the emsfb_stts_ indexes (sid, lookup, date). Bumping this is what
    // triggers Install::upgrade_schema() on sites that already have the tables.
    define("EMSFB_DB_VERSION", 1.2);
}

if (!defined("EFB_DEBUG")) {
    // Single global switch for Easy Form Builder's own diagnostic logging
    // (Human Shield, Telegram, the email tester, …). Defaults to true; override
    // in wp-config.php with define('EFB_DEBUG', false); to turn EFB logging off.
    define("EFB_DEBUG", false);
}

if (!defined("EMSFB_PLUGIN_URL")) {
    define("EMSFB_PLUGIN_URL", plugin_dir_url(__FILE__));
}

if (!defined("WP_PLUGIN_DIR")) {
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if (!defined("EMSFB_DEV_MODE")) {

    $dev_mode = get_option('emsfb_dev_mode', '2');
    if($dev_mode === '2') {
        update_option('emsfb_dev_mode', '0');
        define("EMSFB_DEV_MODE", false);
    }else{
        define("EMSFB_DEV_MODE", $dev_mode === '1' || $dev_mode === true ? true : false);
    }
}


if (!defined("EMSFB_SERVER_URL")) {
    if (EMSFB_DEV_MODE) {
       define("EMSFB_SERVER_URL", "https://demo.whitestudio.team");

    } else {
        define("EMSFB_SERVER_URL", "https://whitestudio.team");
    }
}

if (!defined("EMSFB_LICENSE_SERVER_URL")) {
    // License validation must always hit the production server; the sandbox
    // (devMode) demo server must never decide activation state.
    define("EMSFB_LICENSE_SERVER_URL", "https://whitestudio.team");
}
define("EMSFB_IR_CDN_URL", "https://cdn.easyformbuilder.ir/gh/Json-List-of-countries-states-and-cities-in-the-world/");
define("EMSFB_JSDELIVR_CDN_URL", "https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/");

if (!defined("CDN_ZONE_AREA")) {
    if (get_locale() == 'fa_IR') {
        // This block runs at plugin-load time, so it is on the critical path of
        // every request including the frontend. It used to perform a
        // wp_remote_head() with a 5s timeout whenever the transient was cold,
        // and cached a failure for only an hour - so on the exact market this
        // serves, a real visitor paid up to five seconds once an hour, and every
        // page load if the object cache was not persistent.
        //
        // Nothing is probed here any more: we read the cached verdict, fall back
        // to the global CDN when it is missing, and let the hourly
        // emsfb_refresh_ir_cdn_status cron event do the network work.
        $emsfb_ir_cdn_status = get_transient('emsfb_ir_cdn_status');

        define("EFB_Path_IR", 'up' === $emsfb_ir_cdn_status);
        define("CDN_ZONE_AREA", EFB_Path_IR ? EMSFB_IR_CDN_URL : EMSFB_JSDELIVR_CDN_URL);
    } else {
        define("CDN_ZONE_AREA", EMSFB_JSDELIVR_CDN_URL);
    }
}

/**
 * Probe the Iran CDN and cache the verdict. Scheduled work only - never call
 * this from a path that renders a page for a visitor.
 *
 * @return string 'up' or 'down'
 */
function emsfb_probe_ir_cdn_status_efb() {
    $response = wp_remote_head(EMSFB_IR_CDN_URL . 'js/wp/countries.js', array('timeout' => 5));
    $status   = (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) ? 'up' : 'down';

    // Both outcomes are cached for the same span; the cron event refreshes it
    // hourly, so a short failure TTL no longer buys anything except more probes.
    set_transient('emsfb_ir_cdn_status', $status, 6 * HOUR_IN_SECONDS);

    return $status;
}

require 'includes/class-Emsfb.php';

$emsfb = new Emsfb();

register_activation_hook(__FILE__, 'emsfb_schedule_file_access_check');

add_action('emsfb_check_file_access_after_activation', 'emsfb_perform_file_access_check_efb');

function emsfb_schedule_file_access_check() {
    if (!wp_next_scheduled('emsfb_check_file_access_after_activation')) {
        wp_schedule_single_event(time() + 20, 'emsfb_check_file_access_after_activation');
    }
}

function emsfb_perform_file_access_check_efb() {
    $result = emsfb_check_file_access_efb();

}

/**
 * Safely read the free disk space for a directory.
 *
 * Returns the number of free bytes, or false when it cannot be determined
 * (function disabled via disable_functions, restricted by open_basedir, or
 * an error occurred). Guards against hosts where disk_free_space is disabled,
 * which would otherwise throw a fatal Error.
 *
 * @param string $directory
 * @return float|false
 */
function emsfb_get_free_disk_space_efb($directory) {
    if (!emsfb_is_php_function_available_efb('disk_free_space')) {
        return false;
    }

    try {
        $free = @disk_free_space($directory);
    } catch (\Throwable $e) {
        return false;
    }

    if ($free === false || $free === null) {
        return false;
    }

    return $free;
}

function emsfb_check_file_access_efb() {
    $vendor_path = EMSFB_PLUGIN_DIRECTORY . 'vendor';
    $temp_path = EMSFB_PLUGIN_DIRECTORY . 'temp';

    $status = true;
    $error_codes = [];
    $details = [];

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

    if (!is_writable(EMSFB_PLUGIN_DIRECTORY)) {
        $status = false;
        $error_codes[] = 'PLUGIN_DIR_NOT_WRITABLE';
    } else {
        $details['plugin_writable'] = true;
    }

    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!WP_Filesystem()) {
        $status = false;
        $error_codes[] = 'WP_FILESYSTEM_FAILED';
    } else {
        $details['wp_filesystem'] = true;
    }

    $free_bytes = emsfb_get_free_disk_space_efb(EMSFB_PLUGIN_DIRECTORY);
    if ($free_bytes === false) {
        // Cannot determine free space (disk_free_space disabled or restricted).
        // Do not block installation for a check we are unable to perform.
        $details['free_space_check'] = 'unavailable';
    } elseif ($free_bytes < (10 * 1024 * 1024)) {
        $status = false;
        $error_codes[] = 'INSUFFICIENT_DISK_SPACE';
    } else {
        $details['sufficient_space'] = true;
        $details['free_space_mb'] = round($free_bytes / (1024 * 1024), 2);
    }

    if ($status && file_exists($vendor_path)) {
        $test_file = $vendor_path . '/test_write_efb.txt';
        $test_content = 'EFB add-on test';

        if (!emsfb_is_php_function_available_efb('file_put_contents')) {
            $status = false;
            $error_codes[] = 'PHP_FUNCTION_UNAVAILABLE:file_put_contents';
        } elseif (@file_put_contents($test_file, $test_content) === false) {
            $status = false;
            $error_codes[] = 'WRITE_TEST_FAILED';
        } else {
            if (emsfb_read_file_efb($test_file) !== $test_content) {
                $status = false;
                $error_codes[] = 'READ_TEST_FAILED';
            } else {
                $details['write_test'] = true;
            }
            if (emsfb_is_php_function_available_efb('unlink')) {
                @unlink($test_file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }
    }

    $success_message = esc_html__('Add-on directory is ready for file operations', 'easy-form-builder');
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

    update_option('emsfb_file_access_status', $result);

    return $result;
}

function emsfb_get_file_access_status_efb() {
    $state= get_option('emsfb_file_access_status', null);
    if (!$state) {
        $state = emsfb_check_file_access_efb();
    }
    return $state;
}

function emsfb_is_addon_install_ready_efb() {
    $status = emsfb_get_file_access_status_efb();
    return $status && $status['status'] === true;
}

if (!function_exists('emsfb_is_addon_active_efb')) {
    /**
     * Determine whether an add-on is active in a settings or public payload.
     *
     * @param array|object $settings  Add-on settings or a public settings payload.
     * @param string       $addon_key Add-on identifier, such as AdnSMF.
     * @return bool
     */
    function emsfb_is_addon_active_efb($settings, $addon_key) {
        $addon = null;

        if (is_array($settings)) {
            if (isset($settings['addons']) && is_array($settings['addons'])) {
                $settings = $settings['addons'];
            }
            if (array_key_exists($addon_key, $settings)) {
                $addon = $settings[$addon_key];
            }
        } elseif (is_object($settings) && property_exists($settings, $addon_key)) {
            $addon = $settings->{$addon_key};
        }

        if (is_array($addon)) {
            if (array_key_exists('active', $addon)) {
                return (bool) $addon['active'];
            }
            return !empty($addon['version']);
        }

        if (is_object($addon)) {
            if (property_exists($addon, 'active')) {
                return (bool) $addon->active;
            }
            return !empty($addon->version);
        }

        return absint($addon) >= 1;
    }
}

if (!function_exists('get_setting_Emsfb')) {
    function get_setting_Emsfb($mode = 'decoded') {
        return Emsfb::get_setting_Emsfb($mode);
    }
}

if (!function_exists('get_efbFunction')) {
    function get_efbFunction() {
        return Emsfb::get_efbFunction();
    }
}

if (!function_exists('get_locale_script_chars_efb')) {
    function get_locale_script_chars_efb() {
        return Emsfb::get_locale_script_chars_efb();
    }
}

if (!function_exists('emsfb_is_email_sending_enabled_efb')) {
    /**
     * Whether "This site can send emails" is on. Gates every notification email.
     *
     * @param object|array|null $settings Decoded settings object or array.
     * @return bool
     */
    function emsfb_is_email_sending_enabled_efb($settings) {
        return Emsfb::is_email_sending_enabled_efb($settings);
    }
}
