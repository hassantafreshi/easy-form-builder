<?php
/** Prevent this file from being accessed directly */
if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}

/**
 * Class Emsfb
 */
class Emsfb {
    public $plugin_path = "";

    public $plugin_url = "";

    /**
     * Emsfb constructor.
     */
    public function __construct() {
        $this->plugin_path = EMSFB_PLUGIN_DIRECTORY;
        $this->plugin_url  = EMSFB_PLUGIN_URL;

        $this->includes();
        $this->init_hooks();
       if(is_admin()==false) $this->webhooks();
    }

    /**
     * Initial plugin setup.
     */
    private function init_hooks(): void {
        register_activation_hook(
            EMSFB_PLUGIN_FILE,
            ['\Emsfb\Install', 'install']
        );

        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain(): void {
       
        load_plugin_textdomain(
            EMSFB_PLUGIN_TEXTDOMAIN,
            false,
            EMSFB_PLUGIN_DIRECTORY . "/languages"
        );
    }

    /**
     * Includes classes and functions.
     */
    public function includes(): void {
        require_once $this->plugin_path . 'includes/class-Emsfb-install.php';

        if (is_admin()) {
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-admin.php';
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-create.php';
        }
        else {
            require_once $this->plugin_path . 'includes/class-Emsfb-public.php';
        }

        require_once $this->plugin_path . 'includes/class-Emsfb-public.php';
        //require_once $this->plugin_path . 'includes/class-Emsfb-webhook.php';
    }


    public function webhooks(){
        error_log('webook');
        add_action('rest_api_init',  @function(){
    
          
              register_rest_route('efb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=> 'test_fun'
              ]); 
          });
    }


    private function test_fun($slug){
        error_log($slug['name']);
        error_log($slug['id']);
        return $slug['id'];
       // return $fs;
    
      
    } 



}
