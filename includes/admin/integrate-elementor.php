<?php
/* use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Emsfb_Form_Widget extends Widget_Base {
  
    public function get_name() {
        return 'emsfb_form';
    }

    public function get_title() {
        return __( 'Easy Form Builder', 'easy-form-builder' );
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'easy-form-builder' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_id',
            [
                'label' => __( 'Select a form', 'easy-form-builder' ),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_forms(),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode( '[easy_form_builder id="' . $settings['form_id'] . '"]' );
    }

    private function get_forms() {
        // Fetch the forms from the database and return them as an associative array
        // where the keys are the form IDs and the values are the form names.
        // This is a placeholder and should be replaced with actual code.
        return [];
    }
}

add_action( 'elementor/widgets/widgets_registered', function( $widgets_manager ) {
    $widgets_manager->register_widget_type( new Emsfb_Form_Widget() );
} );
 */