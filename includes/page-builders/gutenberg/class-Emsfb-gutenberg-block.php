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

        $block_json = EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/gutenberg/block.json';
        $block_args = [
            'render_callback' => [$this, 'render_block']
        ];
        // COMP-01: WordPress 6.3 or later reads block.json (apiVersion 3) through register_block_type().
        register_block_type($block_json, $block_args);
    }

    /** The handle WordPress gave block.json's editorScript ("file:./editor.js"). */
    public static function editor_handle() {
        return function_exists('generate_block_asset_handle') ? generate_block_asset_handle('easy-form-builder/form', 'editorScript') : 'easy-form-builder-form-editor-script';
    }

    /**
     * The editor's data (forms, logo, the strings it shows). QA1-007: the script and its style come from block.json
     * (editorScript, editorStyle): WordPress registers both with the block, loads the script's translations through
     * the block's text domain and adds the style to the editor's iframe. QA1-026: nothing is enqueued here a second
     * time (the style was added twice, and WordPress warned that it reached the iframe the wrong way).
     */
    public function enqueue_editor_assets() {
        $handle = self::editor_handle();
        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        // 'strings' is nested, so core leaves its entities untouched, and the
        // editor renders these as React text nodes rather than as HTML.
        wp_localize_script($handle, 'efbBlockData', emsfb_decode_typographic_entities_efb([
            'forms' => $this->get_forms_for_js(),
            'pluginUrl' => EMSFB_PLUGIN_URL,
            'logoUrl' => Emsfb_Widgets_Helper::get_logo_url(),
            'strings' => [
                'selectForm' => __('— Select a Form —', 'easy-form-builder'),
                'blockTitle' => __('Easy Form Builder', 'easy-form-builder'),
                'selectFormHelp' => __('Select a form to display from the dropdown below.', 'easy-form-builder'),
                // For the editor notice, which has no dropdown below it.
                'selectFormNotice' => __('Please select a form to display.', 'easy-form-builder'),
                'selectedForm' => __('Selected Form:', 'easy-form-builder'),
                'formPreviewText' => __('Form will be displayed here on the frontend.', 'easy-form-builder'),
                'loadingForms' => __('Loading forms&hellip;', 'easy-form-builder'),
                'trackingForm' => __('📍 Confirmation Code Finder (Tracking Form)', 'easy-form-builder')
            ]
        ]));
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
            // Visitors see nothing; the owner previewing the page is told why
            // the form is missing instead of finding an empty spot.
            if (current_user_can('edit_posts')) {
                return sprintf(
                    '<div class="wp-block-easy-form-builder-form efb-block-empty" style="padding:12px 16px;border:1px dashed #c7d2fe;border-radius:8px;color:#3730a3;background:#f5f7ff">%s</div>',
                    // The published page has no dropdown, so no "below".
                    esc_html(__('Easy Form Builder', 'easy-form-builder') . ': ' . __('Please select a form to display.', 'easy-form-builder'))
                );
            }
            return '';
        }

        // F11-H: displayMode "popup" (popupLabel, popupStyle) becomes the shortcode's popup attributes.
        $form_html = Emsfb_Widgets_Helper::render_form($form_id, array(
            'displayMode' => isset($attributes['displayMode']) ? $attributes['displayMode'] : 'inline',
            'popupLabel'  => isset($attributes['popupLabel']) ? $attributes['popupLabel'] : '',
            'popupStyle'  => isset($attributes['popupStyle']) ? $attributes['popupStyle'] : 'button',
        ));

        return sprintf(
            '<div class="wp-block-easy-form-builder-form%s%s">%s</div>',
            $class_name,
            $align,
            $form_html
        );
    }
}

Emsfb_Gutenberg_Block::get_instance();
