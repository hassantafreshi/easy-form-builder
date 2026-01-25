<?php
/**
 * Easy Form Builder - Beaver Builder Integration
 *
 * Provides a custom Beaver Builder module for Easy Form Builder forms
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure helper class is loaded
if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

/**
 * Class Emsfb_Beaver_Integration
 *
 * Main class for Beaver Builder integration
 */
class Emsfb_Beaver_Integration {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
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
        // Check if Beaver Builder is active
        if (!self::is_beaver_active()) {
            return;
        }

        // Register module
        add_action('init', [$this, 'register_module']);
    }

    /**
     * Check if Beaver Builder is active
     */
    public static function is_beaver_active() {
        return class_exists('FLBuilder') || defined('FL_BUILDER_VERSION');
    }

    /**
     * Register the module
     */
    public function register_module() {
        if (!class_exists('FLBuilderModule')) {
            return;
        }

        // Register module
        FLBuilder::register_module('Emsfb_Beaver_Module', [
            'general' => [
                'title'    => __('Form Settings', 'easy-form-builder'),
                'sections' => [
                    'form_selection' => [
                        'title'  => __('Select Form', 'easy-form-builder'),
                        'fields' => [
                            'form_id' => [
                                'type'    => 'select',
                                'label'   => __('Form', 'easy-form-builder'),
                                'default' => '',
                                'options' => $this->get_forms_options(),
                                'help'    => __('Choose a form to display.', 'easy-form-builder'),
                            ],
                            'show_title' => [
                                'type'    => 'select',
                                'label'   => __('Show Form Title', 'easy-form-builder'),
                                'default' => 'no',
                                'options' => [
                                    'no'  => __('No', 'easy-form-builder'),
                                    'yes' => __('Yes', 'easy-form-builder'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'style' => [
                'title'    => __('Style', 'easy-form-builder'),
                'sections' => [
                    'container_style' => [
                        'title'  => __('Container', 'easy-form-builder'),
                        'fields' => [
                            'container_padding' => [
                                'type'       => 'dimension',
                                'label'      => __('Padding', 'easy-form-builder'),
                                'responsive' => true,
                                'slider'     => true,
                                'units'      => ['px', 'em', '%'],
                                'preview'    => [
                                    'type'     => 'css',
                                    'selector' => '.efb-beaver-form-wrapper',
                                    'property' => 'padding',
                                ],
                            ],
                            'container_bg_color' => [
                                'type'       => 'color',
                                'label'      => __('Background Color', 'easy-form-builder'),
                                'show_reset' => true,
                                'show_alpha' => true,
                                'preview'    => [
                                    'type'     => 'css',
                                    'selector' => '.efb-beaver-form-wrapper',
                                    'property' => 'background-color',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get forms as options
     */
    private function get_forms_options() {
        $options = ['' => __('— Select a Form —', 'easy-form-builder')];

        if (class_exists('Emsfb_Widgets_Helper')) {
            $forms = Emsfb_Widgets_Helper::get_all_forms(true);
            foreach ($forms as $form) {
                $options[strval($form['id'])] = $form['name'];
            }
        }

        return $options;
    }
}

/**
 * Beaver Builder Module Class
 */
if (class_exists('FLBuilderModule')) {

    class Emsfb_Beaver_Module extends FLBuilderModule {

        public function __construct() {
            parent::__construct([
                'name'            => __('Easy Form Builder', 'easy-form-builder'),
                'description'     => __('Display an Easy Form Builder form.', 'easy-form-builder'),
                'category'        => __('Forms', 'easy-form-builder'),
                'group'           => __('Easy Form Builder', 'easy-form-builder'),
                'dir'             => EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/beaver-builder/',
                'url'             => EMSFB_PLUGIN_URL . 'includes/page-builders/beaver-builder/',
                'icon'            => 'format-aside.svg',
                'editor_export'   => true,
                'enabled'         => true,
                'partial_refresh' => true,
            ]);
        }

        /**
         * Enqueue scripts
         */
        public function enqueue_scripts() {
            if (FLBuilderModel::is_builder_active()) {
                wp_enqueue_style(
                    'efb-beaver-editor',
                    EMSFB_PLUGIN_URL . 'includes/page-builders/beaver-builder/assets/css/beaver-editor.css',
                    [],
                    EMSFB_PLUGIN_VERSION
                );
            }
        }
    }
}

// Initialize
Emsfb_Beaver_Integration::get_instance();
