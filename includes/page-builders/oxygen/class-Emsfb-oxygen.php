<?php
/**
 * Oxygen Builder Element for Easy Form Builder
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
/**
 * Class Emsfb_Oxygen_Integration
 *
 * Integrates Easy Form Builder with Oxygen Builder
 */
class Emsfb_Oxygen_Integration {

    /**
     * Singleton instance
     *
     * @var Emsfb_Oxygen_Integration
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Emsfb_Oxygen_Integration
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if Oxygen is active
        if ( ! $this->is_oxygen_active() ) {
            return;
        }

        add_action( 'init', array( $this, 'register_element' ) );
        add_action( 'oxygen_add_plus_sections', array( $this, 'add_plus_section' ) );
        add_action( 'oxygen_add_plus_subsections', array( $this, 'add_plus_subsection' ) );
    }

    /**
     * Check if Oxygen Builder is active
     *
     * @return bool
     */
    private function is_oxygen_active() {
        return defined( 'CT_VERSION' ) || class_exists( 'OxygenElement' );
    }

    /**
     * Register Oxygen element
     */
    public function register_element() {
        if ( ! function_exists( 'oxygen_add_element' ) ) {
            return;
        }

        $element = array(
            'name'    => __( 'Easy Form Builder', 'easy-form-builder' ),
            'slug'    => 'efb-form',
            'icon'    => Emsfb_Widgets_Helper::get_logo_url(),
            'tag'     => 'div',
            'class'   => 'efb-oxygen-form',
            'options' => array(
                'efb_form' => array(
                    'heading' => __( 'Form Settings', 'easy-form-builder' ),
                    'options' => array(
                        'form_id' => array(
                            'type'    => 'select',
                            'heading' => __( 'Select Form', 'easy-form-builder' ),
                            'default' => '',
                            'options' => $this->get_forms_options(),
                            'css'     => false,
                        ),
                        'show_title' => array(
                            'type'    => 'buttons-list',
                            'heading' => __( 'Show Title', 'easy-form-builder' ),
                            'default' => 'yes',
                            'options' => array(
                                'yes' => __( 'Yes', 'easy-form-builder' ),
                                'no'  => __( 'No', 'easy-form-builder' ),
                            ),
                            'css'     => false,
                        ),
                    ),
                ),
            ),
        );

        oxygen_add_element( $element );

        // Add render function
        add_action( 'oxygen_render_efb-form', array( $this, 'render_element' ), 10, 3 );

        // Add Oxygen element class
        if ( class_exists( 'OxyEl' ) ) {
            require_once dirname( __FILE__ ) . '/class-Emsfb-oxygen-el.php';
        }
    }

    /**
     * Add section in Oxygen Plus panel
     */
    public function add_plus_section() {
        if ( function_exists( 'oxygen_vsb_add_plus_section' ) ) {
            oxygen_vsb_add_plus_section(
                'efb_forms',
                __( 'Easy Form Builder', 'easy-form-builder' )
            );
        }
    }

    /**
     * Add subsection in Oxygen Plus panel
     */
    public function add_plus_subsection() {
        if ( function_exists( 'oxygen_vsb_add_plus_subsection' ) ) {
            oxygen_vsb_add_plus_subsection(
                'efb_forms',
                'efb_forms_section',
                __( 'Forms', 'easy-form-builder' )
            );
        }
    }

    /**
     * Render element
     *
     * @param array $options Element options.
     * @param array $defaults Default options.
     * @param string $content Element content.
     */
    public function render_element( $options, $defaults, $content ) {
        $form_id    = isset( $options['form_id'] ) ? absint( $options['form_id'] ) : 0;
        $show_title = isset( $options['show_title'] ) ? $options['show_title'] : 'yes';

        // Check if in builder mode
        if ( defined( 'OXYGEN_BUILDER_ACTIVE' ) && OXYGEN_BUILDER_ACTIVE ) {
            $this->render_placeholder( $form_id );
            return;
        }

        // Render form on frontend
        if ( $form_id ) {
            echo Emsfb_Widgets_Helper::render_form( $form_id, array(
                'show_title' => $show_title === 'yes',
            ) );
        } else {
            echo '<p class="efb-no-form">' . esc_html__( 'Please select a form.', 'easy-form-builder' ) . '</p>';
        }
    }

    /**
     * Render placeholder for builder
     *
     * @param int $form_id Form ID.
     */
    private function render_placeholder( $form_id ) {
        $logo_url = Emsfb_Widgets_Helper::get_logo_url();
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
                    <?php esc_html_e( 'Please select a form from settings', 'easy-form-builder' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get forms options for Oxygen select
     *
     * @return array
     */
    private function get_forms_options() {
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
}

// Initialize
Emsfb_Oxygen_Integration::get_instance();
