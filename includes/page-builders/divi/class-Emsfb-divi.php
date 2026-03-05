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

class Emsfb_Divi_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!self::is_divi_active()) {
            return;
        }

        add_action('et_builder_ready', [$this, 'register_module']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function is_divi_active() {
        return defined('ET_BUILDER_VERSION') || function_exists('et_setup_theme');
    }

    public function register_module() {
        if (class_exists('ET_Builder_Module')) {
            new Emsfb_Divi_Module();
        }
    }

    public function enqueue_assets() {
        if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
            wp_enqueue_style(
                'efb-divi-editor',
                EMSFB_PLUGIN_URL . 'includes/page-builders/divi/assets/css/divi-editor.css',
                [],
                EMSFB_PLUGIN_VERSION
            );
        }
    }
}

if (class_exists('ET_Builder_Module')) {

    class Emsfb_Divi_Module extends ET_Builder_Module {

        public $slug = 'efb_form';
        public $vb_support = 'on';

        protected $module_credits = [
            'module_uri' => 'https://whitestudio.team',
            'author'     => 'WhiteStudio',
            'author_uri' => 'https://whitestudio.team',
        ];

        public function init() {
            $this->name = esc_html__('Easy Form Builder', 'easy-form-builder');
            $this->plural = esc_html__('Easy Form Builder', 'easy-form-builder');
            $this->icon_path = EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/image/logo.svg';

            $this->settings_modal_toggles = [
                'general' => [
                    'toggles' => [
                        'main_content' => esc_html__('Form Settings', 'easy-form-builder'),
                    ],
                ],
            ];

            $this->main_css_element = '%%order_class%%.efb_form';
        }

        public function get_fields() {
            $forms = $this->get_forms_options();

            return [
                'form_id' => [
                    'label'           => esc_html__('Select Form', 'easy-form-builder'),
                    'type'            => 'select',
                    'option_category' => 'basic_option',
                    'options'         => $forms,
                    'default'         => '',
                    'description'     => esc_html__('Choose a form to display.', 'easy-form-builder'),
                    'toggle_slug'     => 'main_content',
                ],
                'show_title' => [
                    'label'           => esc_html__('Show Form Title', 'easy-form-builder'),
                    'type'            => 'yes_no_button',
                    'option_category' => 'configuration',
                    'options'         => [
                        'off' => esc_html__('No', 'easy-form-builder'),
                        'on'  => esc_html__('Yes', 'easy-form-builder'),
                    ],
                    'default'         => 'off',
                    'toggle_slug'     => 'main_content',
                ],
            ];
        }

        private function get_forms_options() {
            $options = ['' => esc_html__('— Select a Form —', 'easy-form-builder')];

            if (class_exists('Emsfb_Widgets_Helper')) {
                $forms = Emsfb_Widgets_Helper::get_all_forms(true);
                foreach ($forms as $form) {
                    $options[strval($form['id'])] = $form['name'];
                }
            }

            return $options;
        }

        public function render($attrs, $content = null, $render_slug = null) {
            $form_id = $this->props['form_id'];
            $show_title = $this->props['show_title'];

            if (empty($form_id)) {
                if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
                    return $this->render_placeholder();
                }
                return '';
            }

            $output = '<div class="efb-divi-form-wrapper">';

            if ($show_title === 'on' && class_exists('Emsfb_Widgets_Helper')) {
                $forms = Emsfb_Widgets_Helper::get_all_forms(true);
                foreach ($forms as $form) {
                    if (strval($form['id']) === strval($form_id)) {
                        $output .= '<h3 class="efb-divi-form-title">' . esc_html($form['name']) . '</h3>';
                        break;
                    }
                }
            }

            if (class_exists('Emsfb_Widgets_Helper')) {
                $output .= Emsfb_Widgets_Helper::render_form($form_id);
            }

            $output .= '</div>';

            return $output;
        }

        private function render_placeholder() {
            $logo_url = class_exists('Emsfb_Widgets_Helper')
                ? Emsfb_Widgets_Helper::get_logo_url()
                : EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.svg';

            return sprintf(
                '<div class="efb-divi-placeholder" style="
                    padding: 40px 30px;
                    background: linear-gradient(135deg,
                    border-radius: 12px;
                    text-align: center;
                    color:
                    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
                ">
                    <div style="margin-bottom: 15px;">
                        <img src="%s" alt="Easy Form Builder" style="width: 60px; height: 60px;" onerror="this.style.display=\'none\'">
                    </div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Easy Form Builder</div>
                    <div style="font-size: 14px; opacity: 0.9;">%s</div>
                </div>',
                esc_url($logo_url),
                esc_html__('Select a form from the module settings.', 'easy-form-builder')
            );
        }
    }
}

Emsfb_Divi_Integration::get_instance();
