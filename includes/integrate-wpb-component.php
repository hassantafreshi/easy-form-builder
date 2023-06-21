<?php 
class Easy_Form_Builder_WPBakery_Component_View extends WPBakeryShortCode {
  public function __construct() {
    // Call the parent constructor
    parent::__construct();
    
    // Additional initialization if needed
  }
  
  public function contentAdmin($atts, $content = null) {
    // Render the shortcode in the WPBakery backend editor
    return parent::contentAdmin($atts, $content);
  }
  
  public function content($atts, $content = null) {
    // Render the shortcode on the frontend
    return parent::content($atts, $content);
  }
}