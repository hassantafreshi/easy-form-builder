<?php
/**
 * Easy Form Builder - Elementor Widget Integration
 *
 * Provides a custom Elementor widget for Easy Form Builder forms
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure helper class is loaded
if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

/**
 * Class Emsfb_Elementor_Integration
 *
 * Main class for Elementor integration
 */
class Emsfb_Elementor_Integration {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Minimum Elementor version required
     */
    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';

    /**
     * Get singleton instance
     *
     * @return Emsfb_Elementor_Integration
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
        // Check if Elementor is installed and activated
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Check for required Elementor version
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            return;
        }

        // Register widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_category']);

        // Enqueue editor styles
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
    }

    /**
     * Register widget category
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_widget_category($elements_manager) {
        $elements_manager->add_category(
            'easy-form-builder',
            [
                'title' => __('Easy Form Builder', 'easy-form-builder'),
                'icon' => 'eicon-form-horizontal'
            ]
        );
    }

    /**
     * Register widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager) {
        // Include widget file
        require_once __DIR__ . '/class-Emsfb-elementor-widget.php';

        // Register widget
        $widgets_manager->register(new Emsfb_Elementor_Widget());
    }

    /**
     * Enqueue editor styles
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'efb-elementor-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/elementor/assets/css/elementor-editor.css',
            [],
            EMSFB_PLUGIN_VERSION
        );
    }
}

// Initialize Elementor integration
Emsfb_Elementor_Integration::get_instance();
