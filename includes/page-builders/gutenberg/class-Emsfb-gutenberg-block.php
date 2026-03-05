<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

class Emsfb_Gutenberg_Block {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }

    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/gutenberg/block.json',
            [
                'render_callback' => [$this, 'render_block']
            ]
        );
    }

    public function enqueue_editor_assets() {

        $asset_file = [
            'dependencies' => ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'],
            'version' => EMSFB_PLUGIN_VERSION
        ];

        wp_register_script(
            'efb-gutenberg-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/gutenberg/editor.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_localize_script('efb-gutenberg-editor', 'efbBlockData', [
            'forms' => $this->get_forms_for_js(),
            'pluginUrl' => EMSFB_PLUGIN_URL,
            'logoUrl' => Emsfb_Widgets_Helper::get_logo_url(),
            'strings' => [
                'selectForm' => __('— Select a Form —', 'easy-form-builder'),
                'blockTitle' => __('Easy Form Builder', 'easy-form-builder'),
                'selectFormHelp' => __('Select a form to display from the dropdown below.', 'easy-form-builder'),
                'selectedForm' => __('Selected Form:', 'easy-form-builder'),
                'formPreviewText' => __('Form will be displayed here on the frontend.', 'easy-form-builder'),
                'loadingForms' => __('Loading forms...', 'easy-form-builder'),
                'trackingForm' => __('📍 Confirmation Code Finder (Tracking Form)', 'easy-form-builder')
            ]
        ]);

        wp_enqueue_script('efb-gutenberg-editor');

        wp_register_style(
            'efb-gutenberg-editor-style',
            EMSFB_PLUGIN_URL . 'includes/page-builders/gutenberg/editor.css',
            [],
            EMSFB_PLUGIN_VERSION
        );
        wp_enqueue_style('efb-gutenberg-editor-style');
    }

    public function register_rest_routes() {

        register_rest_route('efb/v1', '/forms', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_forms'],
            'permission_callback' => [$this, 'check_edit_permission']
        ]);

        register_rest_route('efb/v1', '/preview/(?P<id>[\w-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_preview'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return $param === 'tracking' || is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    public function check_edit_permission() {
        return current_user_can('edit_posts');
    }

    public function rest_get_forms() {
        $forms = Emsfb_Widgets_Helper::get_all_forms(true);

        return new WP_REST_Response([
            'success' => true,
            'forms' => $forms
        ], 200);
    }

    public function rest_get_preview($request) {
        $form_id = $request->get_param('id');

        $forms = Emsfb_Widgets_Helper::get_all_forms(true);
        $form_name = '';
        foreach ($forms as $form) {
            if (strval($form['id']) === strval($form_id)) {
                $form_name = $form['name'];
                break;
            }
        }

        $preview = Emsfb_Widgets_Helper::get_editor_preview($form_id, $form_name);

        return new WP_REST_Response([
            'success' => true,
            'preview' => $preview,
            'shortcode' => Emsfb_Widgets_Helper::generate_shortcode($form_id)
        ], 200);
    }

    private function get_forms_for_js() {
        return Emsfb_Widgets_Helper::get_all_forms(true);
    }

    public function render_block($attributes) {
        $form_id = isset($attributes['formId']) ? $attributes['formId'] : '';
        $class_name = isset($attributes['className']) ? ' ' . esc_attr($attributes['className']) : '';
        $align = isset($attributes['align']) ? ' align' . esc_attr($attributes['align']) : '';

        if (empty($form_id)) {

            return '';
        }

        $form_html = Emsfb_Widgets_Helper::render_form($form_id);

        return sprintf(
            '<div class="wp-block-easy-form-builder-form%s%s">%s</div>',
            $class_name,
            $align,
            $form_html
        );
    }
}

Emsfb_Gutenberg_Block::get_instance();
