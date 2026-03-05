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

class Emsfb_Elementor_Integration {

    private static $instance = null;

    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        if (!did_action('elementor/loaded')) {
            return;
        }

        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            return;
        }

        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        add_action('elementor/elements/categories_registered', [$this, 'register_widget_category']);

        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
    }

    public function register_widget_category($elements_manager) {
        $elements_manager->add_category(
            'easy-form-builder',
            [
                'title' => __('Easy Form Builder', 'easy-form-builder'),
                'icon' => 'eicon-form-horizontal'
            ]
        );
    }

    public function register_widgets($widgets_manager) {

        require_once __DIR__ . '/class-Emsfb-elementor-widget.php';

        $widgets_manager->register(new Emsfb_Elementor_Widget());
    }

    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'efb-elementor-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/elementor/assets/css/elementor-editor.css',
            [],
            EMSFB_PLUGIN_VERSION
        );
    }
}

Emsfb_Elementor_Integration::get_instance();
