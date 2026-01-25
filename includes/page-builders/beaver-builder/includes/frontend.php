<?php
/**
 * Easy Form Builder - Beaver Builder Frontend Template
 *
 * @package EasyFormBuilder
 * @since 4.0.0
 */

// Exit if accessed directly
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

$form_id = $settings->form_id;
$show_title = $settings->show_title;

if (empty($form_id)) {
    // Show placeholder in builder
    if (FLBuilderModel::is_builder_active()) {
        ?>
        <div class="efb-beaver-placeholder" style="
            padding: 40px 30px;
            background: linear-gradient(135deg, #202a8d 0%, #ff4b93 100%);
            border-radius: 12px;
            text-align: center;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        ">
            <div style="margin-bottom: 15px;">
                <img src="<?php echo esc_url(Emsfb_Widgets_Helper::get_logo_url()); ?>" alt="Easy Form Builder" style="width: 60px; height: 60px;" onerror="this.style.display='none'">
            </div>
            <div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Easy Form Builder</div>
            <div style="font-size: 14px; opacity: 0.9;"><?php echo esc_html__('Select a form from the module settings.', 'easy-form-builder'); ?></div>
        </div>
        <?php
    }
    return;
}

// Output wrapper
echo '<div class="efb-beaver-form-wrapper">';

// Form title
if ($show_title === 'yes' && class_exists('Emsfb_Widgets_Helper')) {
    $forms = Emsfb_Widgets_Helper::get_all_forms(true);
    foreach ($forms as $form) {
        if (strval($form['id']) === strval($form_id)) {
            echo '<h3 class="efb-beaver-form-title">' . esc_html($form['name']) . '</h3>';
            break;
        }
    }
}

// Render form
if (class_exists('Emsfb_Widgets_Helper')) {
    echo Emsfb_Widgets_Helper::render_form($form_id);
}

echo '</div>';
