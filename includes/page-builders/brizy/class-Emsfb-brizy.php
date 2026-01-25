<?php
/**
 * Easy Form Builder - Brizy Builder Integration
 *
 * Provides a custom Brizy widget for Easy Form Builder forms
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
 * Class Emsfb_Brizy_Integration
 *
 * Main class for Brizy Builder integration
 */
class Emsfb_Brizy_Integration {

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
        // Check if Brizy is active
        if (!self::is_brizy_active()) {
            return;
        }

        // Register shortcode widget for Brizy
        add_filter('brizy_shortcode_widgets', [$this, 'register_widget']);

        // Add to Brizy elements
        add_action('brizy_editor_enqueue_scripts', [$this, 'enqueue_editor_scripts']);

        // Register AJAX handler for forms list
        add_action('wp_ajax_efb_brizy_get_forms', [$this, 'ajax_get_forms']);
    }

    /**
     * Check if Brizy is active
     */
    public static function is_brizy_active() {
        return defined('BRIZY_VERSION') || class_exists('Brizy_Editor');
    }

    /**
     * Register widget with Brizy
     */
    public function register_widget($widgets) {
        $widgets[] = [
            'name'        => 'efb_form',
            'title'       => __('Easy Form Builder', 'easy-form-builder'),
            'category'    => 'forms',
            'icon'        => 'nc-icon nc-form-left',
            'description' => __('Display an Easy Form Builder form.', 'easy-form-builder'),
            'position'    => 1000,
            'shortcode'   => 'efb_brizy_form',
            'options'     => $this->get_widget_options(),
        ];

        return $widgets;
    }

    /**
     * Get widget options
     */
    private function get_widget_options() {
        return [
            [
                'id'      => 'form_id',
                'type'    => 'select',
                'title'   => __('Select Form', 'easy-form-builder'),
                'choices' => $this->get_forms_choices(),
            ],
            [
                'id'      => 'show_title',
                'type'    => 'switch',
                'title'   => __('Show Form Title', 'easy-form-builder'),
                'default' => 'off',
            ],
        ];
    }

    /**
     * Get forms as choices for select
     */
    private function get_forms_choices() {
        $choices = [
            ['value' => '', 'title' => __('— Select a Form —', 'easy-form-builder')]
        ];

        if (class_exists('Emsfb_Widgets_Helper')) {
            $forms = Emsfb_Widgets_Helper::get_all_forms(true);
            foreach ($forms as $form) {
                $choices[] = [
                    'value' => strval($form['id']),
                    'title' => $form['name'],
                ];
            }
        }

        return $choices;
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        wp_enqueue_script(
            'efb-brizy-editor',
            EMSFB_PLUGIN_URL . 'includes/page-builders/brizy/assets/js/brizy-editor.js',
            ['jquery'],
            EMSFB_PLUGIN_VERSION,
            true
        );

        wp_localize_script('efb-brizy-editor', 'efbBrizyData', [
            'forms'   => $this->get_forms_choices(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('efb_brizy_nonce'),
            'logoUrl' => Emsfb_Widgets_Helper::get_logo_url(),
            'i18n'    => [
                'selectForm' => __('Select a form from the widget settings.', 'easy-form-builder'),
                'title'      => __('Easy Form Builder', 'easy-form-builder'),
            ],
        ]);
    }

    /**
     * AJAX handler for getting forms
     */
    public function ajax_get_forms() {
        check_ajax_referer('efb_brizy_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        wp_send_json_success(['forms' => $this->get_forms_choices()]);
    }
}

/**
 * Brizy shortcode handler
 */
function efb_brizy_form_shortcode($atts) {
    $atts = shortcode_atts([
        'form_id'    => '',
        'show_title' => 'off',
    ], $atts, 'efb_brizy_form');

    $form_id = $atts['form_id'];
    $show_title = $atts['show_title'];

    if (empty($form_id)) {
        // Check if in Brizy editor
        if (class_exists('Brizy_Editor') && Brizy_Editor::is_editing()) {
            return sprintf(
                '<div class="efb-brizy-placeholder" style="
                    padding: 40px 30px;
                    background: linear-gradient(135deg, #202a8d 0%%, #ff4b93 100%%);
                    border-radius: 12px;
                    text-align: center;
                    color: #fff;
                    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
                ">
                    <div style="margin-bottom: 15px;">
                        <img src="%s" alt="Easy Form Builder" style="width: 60px; height: 60px;" onerror="this.style.display=\'none\'">
                    </div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Easy Form Builder</div>
                    <div style="font-size: 14px; opacity: 0.9;">%s</div>
                </div>',
                esc_url(Emsfb_Widgets_Helper::get_logo_url()),
                esc_html__('Select a form from the widget settings.', 'easy-form-builder')
            );
        }
        return '';
    }

    $output = '<div class="efb-brizy-form-wrapper">';

    // Form title
    if ($show_title === 'on' && class_exists('Emsfb_Widgets_Helper')) {
        $forms = Emsfb_Widgets_Helper::get_all_forms(true);
        foreach ($forms as $form) {
            if (strval($form['id']) === strval($form_id)) {
                $output .= '<h3 class="efb-brizy-form-title">' . esc_html($form['name']) . '</h3>';
                break;
            }
        }
    }

    // Render form
    if (class_exists('Emsfb_Widgets_Helper')) {
        $output .= Emsfb_Widgets_Helper::render_form($form_id);
    }

    $output .= '</div>';

    return $output;
}
add_shortcode('efb_brizy_form', 'efb_brizy_form_shortcode');

// Initialize
Emsfb_Brizy_Integration::get_instance();
