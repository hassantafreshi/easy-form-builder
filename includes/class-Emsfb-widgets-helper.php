<?php

if (!defined('ABSPATH')) {
    exit;
}

class Emsfb_Widgets_Helper {

    const BRAND_COLOR_PRIMARY = '#ff4b93';
    const BRAND_COLOR_SECONDARY = '#202a8d';

    private static $instance = null;

    private static $forms_cache = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        add_action('save_post', [$this, 'clear_forms_cache']);
        add_action('deleted_post', [$this, 'clear_forms_cache']);
    }

    public function clear_forms_cache() {
        self::$forms_cache = null;
        delete_transient('emsfb_forms_list');
    }

    public static function get_all_forms($include_tracking = true) {
        global $wpdb;

        if (self::$forms_cache !== null) {
            return $include_tracking ? self::add_tracking_option(self::$forms_cache) : self::$forms_cache;
        }

        $cached = get_transient('emsfb_forms_list');
        if ($cached !== false) {
            self::$forms_cache = $cached;
            return $include_tracking ? self::add_tracking_option($cached) : $cached;
        }

        $table_name = $wpdb->prefix . 'emsfb_form';
        $forms = [];

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if ($table_exists) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix
            $results = $wpdb->get_results(
                "SELECT form_id, form_name, form_type
                 FROM `{$table_name}`
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

        self::$forms_cache = $forms;
        set_transient('emsfb_forms_list', $forms, HOUR_IN_SECONDS);

        return $include_tracking ? self::add_tracking_option($forms) : $forms;
    }

    private static function add_tracking_option($forms) {

        array_unshift($forms, [
            'id' => 'tracking',
            'name' => __('📍 Confirmation Code Finder (Tracking Form)', 'easy-form-builder'),
            'type' => 'tracking'
        ]);
        return $forms;
    }

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

    public static function generate_shortcode($form_id) {
        if (empty($form_id)) {
            return '';
        }

        if ($form_id === 'tracking') {
            return '[Easy_Form_Builder_confirmation_code_finder]';
        }

        return sprintf('[EMS_Form_Builder id="%d"]', intval($form_id));
    }

    public static function render_form($form_id) {
        if (empty($form_id)) {
            return self::render_placeholder_message(__('Please select a form to display.', 'easy-form-builder'));
        }

        $shortcode = self::generate_shortcode($form_id);
        return do_shortcode($shortcode);
    }

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

    public static function get_logo_url() {
        return EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.svg';
    }

    public static function get_icon_svg() {
        return '<img src="' . esc_url(self::get_logo_url()) . '" alt="Easy Form Builder" width="24" height="24" style="display:block;">';
    }

    public static function is_builder_active($builder) {
        switch ($builder) {
            case 'elementor':
                try {
                    return defined('ELEMENTOR_VERSION') && class_exists('\Elementor\Plugin');
                } catch (\Exception $e) {
                    return false;
                } catch (\Error $e) {
                    return false;
                }

            case 'wpbakery':
                try {
                    return defined('WPB_VC_VERSION') && function_exists('vc_map');
                } catch (\Exception $e) {
                    return false;
                } catch (\Error $e) {
                    return false;
                }

            case 'gutenberg':

                return function_exists('register_block_type');

            default:
                return false;
        }
    }

    public static function get_current_editor() {

        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return 'elementor';
        }

        if (class_exists('\Elementor\Plugin')) {
            try {
                $elementor = \Elementor\Plugin::$instance;
                if ($elementor && isset($elementor->editor) && $elementor->editor && method_exists($elementor->editor, 'is_edit_mode') && $elementor->editor->is_edit_mode()) {
                    return 'elementor';
                }
            } catch (\Exception $e) {

            } catch (\Error $e) {

            }
        }

        if (function_exists('vc_is_inline') && vc_is_inline()) {
            return 'wpbakery';
        }
        if (isset($_GET['vc_editable']) && $_GET['vc_editable'] === 'true') {
            return 'wpbakery';
        }

        if (function_exists('is_block_editor') && is_block_editor()) {
            return 'gutenberg';
        }

        return false;
    }
}

Emsfb_Widgets_Helper::get_instance();
