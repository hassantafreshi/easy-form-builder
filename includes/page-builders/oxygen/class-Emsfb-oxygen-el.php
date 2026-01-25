<?php
/**
 * Oxygen Element Class for Easy Form Builder
 *
 * @package Easy_Form_Builder
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure helper class is loaded
if (!class_exists('Emsfb_Widgets_Helper') && defined('EMSFB_PLUGIN_DIRECTORY')) {
    $helper_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-widgets-helper.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

if ( ! class_exists( 'OxyEl' ) ) {
    return;
}

/**
 * Class Emsfb_Oxygen_El
 *
 * Extends OxyEl for Oxygen Builder integration
 */
class Emsfb_Oxygen_El extends OxyEl {

    /**
     * Element name
     *
     * @return string
     */
    function name() {
        return __( 'Easy Form Builder', 'easy-form-builder' );
    }

    /**
     * Element slug
     *
     * @return string
     */
    function slug() {
        return 'efb-form-element';
    }

    /**
     * Editor button text
     *
     * @return string
     */
    function button_place() {
        return 'efb_forms::efb_forms_section';
    }

    /**
     * Button priority
     *
     * @return int
     */
    function button_priority() {
        return 1;
    }

    /**
     * Element icon
     *
     * @return string
     */
    function icon() {
        return Emsfb_Widgets_Helper::get_logo_url();
    }

    /**
     * Element controls
     */
    function controls() {
        // Form Selection Section
        $form_section = $this->addControlSection(
            'efb_form_settings',
            __( 'Form Settings', 'easy-form-builder' ),
            'assets/icon.png',
            $this
        );

        // Form ID dropdown
        $forms = $this->get_forms_array();
        $form_section->addOptionControl(
            array(
                'type'    => 'dropdown',
                'name'    => __( 'Select Form', 'easy-form-builder' ),
                'slug'    => 'efb_form_id',
                'default' => '',
            )
        )->setValue( $forms );

        // Show title toggle
        $form_section->addOptionControl(
            array(
                'type'    => 'buttons-list',
                'name'    => __( 'Show Form Title', 'easy-form-builder' ),
                'slug'    => 'efb_show_title',
                'default' => 'yes',
            )
        )->setValue(
            array(
                'yes' => __( 'Yes', 'easy-form-builder' ),
                'no'  => __( 'No', 'easy-form-builder' ),
            )
        );

        // Style Section
        $style_section = $this->addControlSection(
            'efb_style_settings',
            __( 'Form Style', 'easy-form-builder' ),
            'assets/icon.png',
            $this
        );

        // Container padding
        $style_section->addPreset(
            'padding',
            'efb_container_padding',
            __( 'Container Padding', 'easy-form-builder' ),
            '.efb-form-wrapper'
        );

        // Container background
        $style_section->addStyleControl(
            array(
                'name'     => __( 'Background Color', 'easy-form-builder' ),
                'selector' => '.efb-form-wrapper',
                'property' => 'background-color',
            )
        );

        // Border radius
        $style_section->addStyleControl(
            array(
                'name'         => __( 'Border Radius', 'easy-form-builder' ),
                'selector'     => '.efb-form-wrapper',
                'property'     => 'border-radius',
                'control_type' => 'measurebox',
                'unit'         => 'px',
            )
        );
    }

    /**
     * Element render
     */
    function render( $options, $defaults, $content ) {
        $form_id    = isset( $options['efb_form_id'] ) ? absint( $options['efb_form_id'] ) : 0;
        $show_title = isset( $options['efb_show_title'] ) ? $options['efb_show_title'] : 'yes';

        // Check if in builder mode
        if ( defined( 'OXYGEN_BUILDER_ACTIVE' ) && OXYGEN_BUILDER_ACTIVE ) {
            $this->render_builder_placeholder( $form_id );
            return;
        }

        // Render form
        if ( $form_id ) {
            echo '<div class="efb-form-wrapper">';
            echo Emsfb_Widgets_Helper::render_form( $form_id, array(
                'show_title' => $show_title === 'yes',
            ) );
            echo '</div>';
        } else {
            echo '<div class="efb-no-form-notice">';
            echo '<p>' . esc_html__( 'Please select a form from the element settings.', 'easy-form-builder' ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render placeholder for builder
     *
     * @param int $form_id Form ID.
     */
    private function render_builder_placeholder( $form_id ) {
        $logo_url  = Emsfb_Widgets_Helper::get_logo_url();
        $form_name = '';

        if ( $form_id ) {
            $forms = Emsfb_Widgets_Helper::get_all_forms();
            foreach ( $forms as $form ) {
                if ( absint( $form->form_id ) === $form_id ) {
                    $form_name = $form->form_name;
                    break;
                }
            }
        }

        ?>
        <div class="efb-oxygen-placeholder" style="
            background: linear-gradient(135deg, #ff4b93 0%, #202a8d 100%);
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            color: #ffffff;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        ">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="Easy Form Builder" style="width: 40px; height: 40px;">
            <div style="font-size: 18px; font-weight: bold;">
                <?php esc_html_e( 'Easy Form Builder', 'easy-form-builder' ); ?>
            </div>
            <?php if ( $form_name ) : ?>
                <div style="font-size: 14px; opacity: 0.9;">
                    <?php echo esc_html( sprintf( __( 'Form: %s', 'easy-form-builder' ), $form_name ) ); ?>
                </div>
            <?php else : ?>
                <div style="font-size: 14px; opacity: 0.9;">
                    <?php esc_html_e( 'Select a form from the settings panel', 'easy-form-builder' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get forms array for dropdown
     *
     * @return array
     */
    private function get_forms_array() {
        $options = array(
            '' => __( '— Select Form —', 'easy-form-builder' ),
        );

        $forms = Emsfb_Widgets_Helper::get_all_forms();

        if ( ! empty( $forms ) ) {
            foreach ( $forms as $form ) {
                $options[ $form->form_id ] = $form->form_name;
            }
        }

        return $options;
    }

    /**
     * Enqueue scripts for builder
     */
    function builder_js() {
        return 'console.log("Easy Form Builder element loaded in Oxygen Builder");';
    }
}

// Register element
new Emsfb_Oxygen_El();
