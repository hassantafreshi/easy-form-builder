<?php

/* add_action( 'vc_before_init', 'ems_Form_Builders_visual_composer_shortcode' );
add_shortcode('EMS_Form_Builder', 'render_ems_Form_Builder');


function ems_Form_Builders_visual_composer_shortcode() {
  if ( ! is_user_logged_in() ) {
    return;
  }

  vc_map(array(
    'name' => 'Easy Form Builder',
    'base' => 'EMS_Form_Builder',
    'category' => 'Content',
    'icon' => EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
    'description' => 'Choose a form to display',
    'params' => array(
      array(
        'type' => 'dropdown',
        'heading' => 'Select a Form',
        'param_name' => 'id',
        'value' => get_available_forms(),
        'description' => 'Choose a form to display',
        'save_always' => true,
        'admin_label' => true,
      ),
    ),
  ));
}


function ems_Form_Builders_visual_composer_shortcode_css() {
  wp_enqueue_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css', array(), '3.8.8');
  // Load CSS per global setting or any additional styles if required.
}
add_action( 'vc_load_iframe_jscss', 'ems_Form_Builders_visual_composer_shortcode_css' );

function get_available_forms() {
  // Your logic to retrieve the available forms
  // You can fetch form names and IDs from your database or any other source
  
  // For example, assuming you have an array of forms with IDs and names
  $forms = array(
    array('id' => 1, 'name' => 'Form 1'),
    array('id' => 2, 'name' => 'Form 2'),
    array('id' => 3, 'name' => 'Form 3'),
  );
  
  $options = array();
  
  foreach ($forms as $form) {
    $options[$form['name']] = $form['id'];
  }
  
  return $options;
}


function render_ems_Form_Builder($atts) {
  $atts = shortcode_atts(
    array(
      'id' => '',
    ),
    $atts
  );

  // Get the selected form ID from the shortcode attributes
  $form_id = $atts['id'];

  // Render the form using your preferred method or shortcode handler
  $form_output = ''; // Add your form rendering logic here

  return $form_output;
}
 */