<?php
/**
 * Easy Form Builder - Gutenberg Block Registration
 *
 * Registers the Easy Form Builder block for the WordPress block editor (Gutenberg)
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit; // No direct access allowed
}

// Ensure helper class is loaded
if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

/**
 * Class Emsfb_Gutenberg_Block
 *
 * Handles Gutenberg block registration and rendering
 */
class Emsfb_Gutenberg_Block {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Emsfb_Gutenberg_Block
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }

    /**
     * Register the block
     */
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

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        // Editor script dependencies
        $asset_file = [
            'dependencies' => ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'],
            'version' => EMSFB_PLUGIN_VERSION
        ];

        // Register and enqueue editor script
        wp_register_script(
            'efb-gutenberg-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/gutenberg/editor.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Localize script with forms data
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

        // Register and enqueue editor styles
        wp_register_style(
            'efb-gutenberg-editor-style',
            EMSFB_PLUGIN_URL . 'includes/page-builders/gutenberg/editor.css',
            [],
            EMSFB_PLUGIN_VERSION
        );
        wp_enqueue_style('efb-gutenberg-editor-style');
    }

    /**
     * Register REST API routes for the block
     */
    public function register_rest_routes() {
        // Route to get all forms
        register_rest_route('efb/v1', '/forms', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_forms'],
            'permission_callback' => [$this, 'check_edit_permission']
        ]);

        // Route to get form preview
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

    /**
     * Check if user can edit posts
     *
     * @return bool
     */
    public function check_edit_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * REST endpoint: Get all forms
     *
     * @return WP_REST_Response
     */
    public function rest_get_forms() {
        $forms = Emsfb_Widgets_Helper::get_all_forms(true);

        return new WP_REST_Response([
            'success' => true,
            'forms' => $forms
        ], 200);
    }

    /**
     * REST endpoint: Get form preview
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_get_preview($request) {
        $form_id = $request->get_param('id');

        // Get form name
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

    /**
     * Get forms formatted for JavaScript
     *
     * @return array
     */
    private function get_forms_for_js() {
        return Emsfb_Widgets_Helper::get_all_forms(true);
    }

    /**
     * Render the block on frontend
     *
     * @param array $attributes Block attributes
     * @return string HTML output
     */
    public function render_block($attributes) {
        $form_id = isset($attributes['formId']) ? $attributes['formId'] : '';
        $class_name = isset($attributes['className']) ? ' ' . esc_attr($attributes['className']) : '';
        $align = isset($attributes['align']) ? ' align' . esc_attr($attributes['align']) : '';

        if (empty($form_id)) {
            // Don't show anything if no form selected (only show placeholder in editor)
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

// Initialize the block
Emsfb_Gutenberg_Block::get_instance();
