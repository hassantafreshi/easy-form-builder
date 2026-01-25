<?php
/**
 * Easy Form Builder - Widgets Helper Class
 *
 * Provides shared functionality for all page builder widgets
 * (Gutenberg, Elementor, WPBakery, etc.)
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit; // No direct access allowed
}

/**
 * Class Emsfb_Widgets_Helper
 *
 * Helper class providing shared functions for page builder integrations
 */
class Emsfb_Widgets_Helper {

    /**
     * Plugin brand colors
     */
    const BRAND_COLOR_PRIMARY = '#ff4b93';
    const BRAND_COLOR_SECONDARY = '#202a8d';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache for forms list
     */
    private static $forms_cache = null;

    /**
     * Get singleton instance
     *
     * @return Emsfb_Widgets_Helper
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Initialize hooks
        add_action('save_post', [$this, 'clear_forms_cache']);
        add_action('deleted_post', [$this, 'clear_forms_cache']);
    }

    /**
     * Clear forms cache when forms are modified
     */
    public function clear_forms_cache() {
        self::$forms_cache = null;
        delete_transient('emsfb_forms_list');
    }

    /**
     * Get all available forms from database
     *
     * @param bool $include_tracking Whether to include tracking form option
     * @return array Array of forms with id and name
     */
    public static function get_all_forms($include_tracking = true) {
        global $wpdb;

        // Check cache first
        if (self::$forms_cache !== null) {
            return $include_tracking ? self::add_tracking_option(self::$forms_cache) : self::$forms_cache;
        }

        // Check transient cache
        $cached = get_transient('emsfb_forms_list');
        if ($cached !== false) {
            self::$forms_cache = $cached;
            return $include_tracking ? self::add_tracking_option($cached) : $cached;
        }

        $table_name = $wpdb->prefix . 'emsfb_form';
        $forms = [];

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if ($table_exists) {
            $results = $wpdb->get_results(
                "SELECT form_id, form_name, form_type, status
                 FROM {$table_name}
                 WHERE status = 1
                 ORDER BY form_id DESC",
                ARRAY_A
            );

            if ($results) {
                foreach ($results as $row) {
                    $forms[] = [
                        'id' => intval($row['form_id']),
                        'name' => sanitize_text_field($row['form_name']),
                        'type' => sanitize_text_field($row['form_type'])
                    ];
                }
            }
        }

        // Cache the results
        self::$forms_cache = $forms;
        set_transient('emsfb_forms_list', $forms, HOUR_IN_SECONDS);

        return $include_tracking ? self::add_tracking_option($forms) : $forms;
    }

    /**
     * Add tracking form option to forms list
     *
     * @param array $forms Forms list
     * @return array Forms list with tracking option
     */
    private static function add_tracking_option($forms) {
        // Add tracking form as first option
        array_unshift($forms, [
            'id' => 'tracking',
            'name' => __('📍 Confirmation Code Finder (Tracking Form)', 'easy-form-builder'),
            'type' => 'tracking'
        ]);
        return $forms;
    }

    /**
     * Get forms as options array for select controls
     *
     * @param bool $include_tracking Whether to include tracking form option
     * @param bool $include_empty Whether to include empty "Select Form" option
     * @return array Associative array [id => name]
     */
    public static function get_forms_for_select($include_tracking = true, $include_empty = true) {
        $forms = self::get_all_forms($include_tracking);
        $options = [];

        if ($include_empty) {
            $options[''] = __('— Select a Form —', 'easy-form-builder');
        }

        foreach ($forms as $form) {
            $options[$form['id']] = $form['name'];
        }

        return $options;
    }

    /**
     * Generate shortcode for a form
     *
     * @param int|string $form_id Form ID or 'tracking'
     * @return string Shortcode string
     */
    public static function generate_shortcode($form_id) {
        if (empty($form_id)) {
            return '';
        }

        if ($form_id === 'tracking') {
            return '[Easy_Form_Builder_confirmation_code_finder]';
        }

        return sprintf('[EMS_Form_Builder id="%d"]', intval($form_id));
    }

    /**
     * Render form by ID
     *
     * @param int|string $form_id Form ID or 'tracking'
     * @return string Rendered form HTML
     */
    public static function render_form($form_id) {
        if (empty($form_id)) {
            return self::render_placeholder_message(__('Please select a form to display.', 'easy-form-builder'));
        }

        $shortcode = self::generate_shortcode($form_id);
        return do_shortcode($shortcode);
    }

    /**
     * Render placeholder message for editor preview
     *
     * @param string $message Message to display
     * @return string HTML output
     */
    public static function render_placeholder_message($message) {
        return sprintf(
            '<div style="padding: 30px; background: linear-gradient(135deg, %s 0%%, %s 100%%); border-radius: 10px; text-align: center; color: #fff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;">
                <div style="margin-bottom: 15px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <div style="font-size: 16px; font-weight: 600;">Easy Form Builder</div>
                <div style="font-size: 14px; margin-top: 8px; opacity: 0.9;">%s</div>
            </div>',
            self::BRAND_COLOR_SECONDARY,
            self::BRAND_COLOR_PRIMARY,
            esc_html($message)
        );
    }

    /**
     * Get editor preview HTML for form
     *
     * @param int|string $form_id Form ID
     * @param string $form_name Form name for display
     * @return string HTML output
     */
    public static function get_editor_preview($form_id, $form_name = '') {
        if (empty($form_id)) {
            return self::render_placeholder_message(__('Please select a form to display.', 'easy-form-builder'));
        }

        if (empty($form_name)) {
            $forms = self::get_all_forms(true);
            foreach ($forms as $form) {
                if ($form['id'] == $form_id) {
                    $form_name = $form['name'];
                    break;
                }
            }
        }

        $shortcode = self::generate_shortcode($form_id);

        return sprintf(
            '<div style="padding: 25px; background: linear-gradient(135deg, %s 0%%, %s 100%%); border-radius: 12px; text-align: center; color: #fff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif; box-shadow: 0 4px 15px rgba(32, 42, 141, 0.2);">
                <div style="margin-bottom: 12px;">
                    <img src="%s" alt="Easy Form Builder" style="width: 48px; height: 48px; border-radius: 8px;" onerror="this.style.display=\'none\'">
                </div>
                <div style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Easy Form Builder</div>
                <div style="font-size: 14px; background: rgba(255,255,255,0.15); padding: 10px 15px; border-radius: 6px; margin: 12px 0;">
                    <span style="opacity: 0.8;">%s</span><br>
                    <strong>%s</strong>
                </div>
                <div style="font-size: 12px; opacity: 0.7; margin-top: 10px;">
                    <code style="background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 4px;">%s</code>
                </div>
            </div>',
            self::BRAND_COLOR_SECONDARY,
            self::BRAND_COLOR_PRIMARY,
            esc_url(EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.svg'),
            esc_html__('Selected Form:', 'easy-form-builder'),
            esc_html($form_name),
            esc_html($shortcode)
        );
    }

    /**
     * Get plugin logo URL
     *
     * @return string Logo URL
     */
    public static function get_logo_url() {
        return EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.svg';
    }

    /**
     * Get plugin icon (for Gutenberg block, etc.)
     *
     * @return string SVG icon URL
     */
    public static function get_icon_svg() {
        return '<img src="' . esc_url(self::get_logo_url()) . '" alt="Easy Form Builder" width="24" height="24" style="display:block;">';
    }

    /**
     * Check if a page builder is active
     *
     * @param string $builder Builder name: 'elementor', 'wpbakery', 'gutenberg'
     * @return bool
     */
    public static function is_builder_active($builder) {
        switch ($builder) {
            case 'elementor':
                return defined('ELEMENTOR_VERSION') && class_exists('\Elementor\Plugin');

            case 'wpbakery':
                return defined('WPB_VC_VERSION') && function_exists('vc_map');

            case 'gutenberg':
                // Gutenberg is part of WordPress core since 5.0
                return function_exists('register_block_type');

            default:
                return false;
        }
    }

    /**
     * Check if currently editing in a page builder
     *
     * @return bool|string False or builder name
     */
    public static function get_current_editor() {
        // Elementor editor
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return 'elementor';
        }
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return 'elementor';
        }

        // WPBakery editor
        if (function_exists('vc_is_inline') && vc_is_inline()) {
            return 'wpbakery';
        }
        if (isset($_GET['vc_editable']) && $_GET['vc_editable'] === 'true') {
            return 'wpbakery';
        }

        // Gutenberg editor
        if (function_exists('is_block_editor') && is_block_editor()) {
            return 'gutenberg';
        }

        return false;
    }
}

// Initialize the helper
Emsfb_Widgets_Helper::get_instance();
