<?php

if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}

if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

class Emsfb_Visual_Composer_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        add_shortcode('efb_vc_form', [$this, 'render_shortcode']);

        add_action('vcv:api', [$this, 'register_vc_element'], 10);

        add_filter('vcv:helpers:localizations:i18n', [$this, 'add_localization']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        add_action('widgets_init', [$this, 'register_widget']);

        add_action('admin_footer', [$this, 'output_forms_data']);
        add_action('wp_footer', [$this, 'output_forms_data']);
    }

    public function register_vc_element($api) {
        if (!$api) {
            return;
        }

        $forms = $this->get_forms_for_dropdown();

        $element = [
            'tag' => 'efb_vc_form',
            'name' => __('Easy Form Builder', 'easy-form-builder'),
            'description' => __('Add forms created with Easy Form Builder', 'easy-form-builder'),
            'icon' => $this->get_icon_url(),
            'category' => __('Content', 'easy-form-builder'),
            'metaDescription' => __('Display Easy Form Builder forms on your page', 'easy-form-builder'),
            'editFormSettings' => [
                [
                    'type' => 'dropdown',
                    'heading' => __('Select Form', 'easy-form-builder'),
                    'param_name' => 'form_id',
                    'value' => $forms,
                    'description' => __('Choose the form you want to display', 'easy-form-builder'),
                ]
            ],
        ];

        if (method_exists($api, 'elements') && is_callable([$api->elements(), 'register'])) {
            $api->elements()->register($element);
        }
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'form_id' => '',
        ], $atts, 'efb_vc_form');

        $form_id = $atts['form_id'];

        $is_editor = $this->is_editor_mode();

        if (empty($form_id)) {
            if ($is_editor) {
                return $this->get_editor_placeholder(__('Please select a form from element settings', 'easy-form-builder'));
            }
            return '';
        }

        if ($is_editor) {
            $form_name = $this->get_form_name($form_id);
            return $this->get_editor_placeholder(
                sprintf(__('Form: %s (ID: %s)', 'easy-form-builder'), $form_name, $form_id)
            );
        }

        if ($form_id === 'tracking') {
            return do_shortcode('[Easy_Form_Builder_confirmation_code_finder]');
        }

        return do_shortcode('[EMS_Form_Builder id="' . intval($form_id) . '"]');
    }

    private function get_forms_for_dropdown() {
        $forms = ['' => __('— Select a Form —', 'easy-form-builder')];

        if (class_exists('Emsfb_Widgets_Helper')) {
            $all_forms = Emsfb_Widgets_Helper::get_all_forms(true);
            foreach ($all_forms as $form) {
                $forms[$form['id']] = $form['name'];
            }
        } else {
            global $wpdb;
            $table_name = $wpdb->prefix . "emsfb_form";

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
                $results = $wpdb->get_results("SELECT form_id, form_name FROM {$table_name} ORDER BY form_name ASC");
                if ($results) {
                    foreach ($results as $form) {
                        $forms[$form->form_id] = $form->form_name;
                    }
                }
            }
            $forms['tracking'] = __('📍 Confirmation Code Finder (Tracking)', 'easy-form-builder');
        }

        return $forms;
    }

    private function get_form_name($form_id) {
        if ($form_id === 'tracking') {
            return __('Confirmation Code Finder', 'easy-form-builder');
        }

        if (class_exists('Emsfb_Widgets_Helper')) {
            $all_forms = Emsfb_Widgets_Helper::get_all_forms(false);
            foreach ($all_forms as $form) {
                if ($form['id'] == $form_id) {
                    return $form['name'];
                }
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . "emsfb_form";
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT form_name FROM {$table_name} WHERE form_id = %d",
            intval($form_id)
        ));

        return $result ? $result : __('Form', 'easy-form-builder') . ' #' . $form_id;
    }

    private function get_icon_url() {
        if (defined('EMSFB_PLUGIN_URL')) {
            $icon_path = EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/image/efb-icon.png';
            if (file_exists($icon_path)) {
                return EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-icon.png';
            }
        }
        return '';
    }

    private function is_editor_mode() {
        return (
            isset($_GET['vcv-action']) ||
            isset($_GET['vcv-ajax']) ||
            (defined('VCV_AJAX_REQUEST') && VCV_AJAX_REQUEST) ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            wp_doing_ajax()
        );
    }

    private function get_editor_placeholder($message) {
        return sprintf(
            '<div style="background: linear-gradient(135deg, #202a8d 0%%, #ff4b93 100%%); padding: 30px; border-radius: 12px; text-align: center; color: #fff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; margin: 10px 0;">
                <div style="font-size: 32px; margin-bottom: 10px;">📝</div>
                <h3 style="margin: 15px 0 10px; font-size: 18px; font-weight: 600;">Easy Form Builder</h3>
                <p style="opacity: 0.9; margin: 0; font-size: 14px;">%s</p>
            </div>',
            esc_html($message)
        );
    }

    public function add_localization($localizations) {
        $localizations['easyFormBuilder'] = __('Easy Form Builder', 'easy-form-builder');
        return $localizations;
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        if (!defined('VCV_VERSION')) {
            return;
        }
    }

    public function output_forms_data() {
        if (!is_admin() && !isset($_GET['vcv-action'])) {
            return;
        }

        $forms = $this->get_forms_for_dropdown();
        ?>
        <script type="text/javascript">
            window.efbVcForms = <?php echo wp_json_encode($forms); ?>;
        </script>
        <?php
    }

    public function register_widget() {
        register_widget('EFB_Visual_Composer_Widget');
    }
}

class EFB_Visual_Composer_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'efb_form_widget',
            __('🎨 Easy Form Builder', 'easy-form-builder'),
            [
                'description' => __('Add Easy Form Builder forms to your page', 'easy-form-builder'),
                'classname' => 'efb-form-widget',
            ]
        );
    }

    public function widget($args, $instance) {
        $form_id = !empty($instance['form_id']) ? $instance['form_id'] : '';
        $show_title = !empty($instance['show_title']) ? $instance['show_title'] : 'no';

        if (empty($form_id)) {
            return;
        }

        echo $args['before_widget'];

        if ($show_title === 'yes' && !empty($instance['title'])) {
            echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        }

        if ($form_id === 'tracking') {
            echo do_shortcode('[Easy_Form_Builder_confirmation_code_finder]');
        } else {
            echo do_shortcode('[EMS_Form_Builder id="' . intval($form_id) . '"]');
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $form_id = !empty($instance['form_id']) ? $instance['form_id'] : '';
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $show_title = !empty($instance['show_title']) ? $instance['show_title'] : 'no';

        $forms = $this->get_forms_list();

        ?>
        <div style="padding: 10px 0;">
            <div style="background: linear-gradient(135deg, #202a8d, #ff4b93); color: #fff; padding: 12px; border-radius: 8px; margin-bottom: 15px; text-align: center;">
                <strong style="font-size: 14px;">🎨 Easy Form Builder</strong>
            </div>

            <p>
                <label for="<?php echo esc_attr($this->get_field_id('form_id')); ?>" style="font-weight: 600;">
                    <?php esc_html_e('Select Form:', 'easy-form-builder'); ?>
                </label>
                <select
                    class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('form_id')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('form_id')); ?>"
                    style="margin-top: 5px;"
                >
                    <option value=""><?php esc_html_e('— Select a Form —', 'easy-form-builder'); ?></option>
                    <?php foreach ($forms as $id => $name) : ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($form_id, $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                    <?php esc_html_e('Custom Title (optional):', 'easy-form-builder'); ?>
                </label>
                <input
                    class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                    type="text"
                    value="<?php echo esc_attr($title); ?>"
                    style="margin-top: 5px;"
                >
            </p>

            <p>
                <input
                    type="checkbox"
                    id="<?php echo esc_attr($this->get_field_id('show_title')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('show_title')); ?>"
                    value="yes"
                    <?php checked($show_title, 'yes'); ?>
                >
                <label for="<?php echo esc_attr($this->get_field_id('show_title')); ?>">
                    <?php esc_html_e('Show Form Title', 'easy-form-builder'); ?>
                </label>
            </p>

            <?php if (!empty($form_id)) : ?>
                <div style="background: #f0f0f1; padding: 10px; border-radius: 6px; margin-top: 10px;">
                    <small style="color: #666;">
                        <?php esc_html_e('Shortcode:', 'easy-form-builder'); ?>
                        <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">
                            <?php
                            if ($form_id === 'tracking') {
                                echo '[Easy_Form_Builder_confirmation_code_finder]';
                            } else {
                                echo '[EMS_Form_Builder id="' . esc_attr($form_id) . '"]';
                            }
                            ?>
                        </code>
                    </small>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['form_id'] = !empty($new_instance['form_id']) ? sanitize_text_field($new_instance['form_id']) : '';
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['show_title'] = !empty($new_instance['show_title']) ? 'yes' : 'no';
        return $instance;
    }

    private function get_forms_list() {
        if (class_exists('Emsfb_Widgets_Helper')) {
            $all_forms = Emsfb_Widgets_Helper::get_all_forms(true);
            $forms = [];
            foreach ($all_forms as $form) {
                $forms[$form['id']] = $form['name'];
            }
            return $forms;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . "emsfb_form";
        $forms = [];

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
            $results = $wpdb->get_results("SELECT form_id, form_name FROM {$table_name} ORDER BY form_name ASC");
            if ($results) {
                foreach ($results as $form) {
                    $forms[$form->form_id] = $form->form_name;
                }
            }
        }

        $forms['tracking'] = __('📍 Confirmation Code Finder (Tracking)', 'easy-form-builder');

        return $forms;
    }

    private function is_editor_mode() {
        return (
            isset($_GET['vcv-action']) ||
            isset($_GET['elementor-preview']) ||
            is_customize_preview() ||
            (defined('REST_REQUEST') && REST_REQUEST)
        );
    }

    private function get_editor_placeholder($message) {
        $logo_url = '';
        if (class_exists('Emsfb_Widgets_Helper')) {
            $logo_url = Emsfb_Widgets_Helper::get_logo_url();
        }

        return sprintf(
            '<div style="background: linear-gradient(135deg, #202a8d 0%%, #ff4b93 100%%); padding: 30px; border-radius: 12px; text-align: center; color: #fff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                %s
                <h3 style="margin: 15px 0 10px; font-size: 18px; font-weight: 600;">Easy Form Builder</h3>
                <p style="opacity: 0.9; margin: 0; font-size: 14px;">%s</p>
            </div>',
            $logo_url ? '<img src="' . esc_url($logo_url) . '" alt="EFB" style="width: 48px; height: 48px; border-radius: 8px;">' : '',
            esc_html($message)
        );
    }
}

Emsfb_Visual_Composer_Integration::get_instance();
