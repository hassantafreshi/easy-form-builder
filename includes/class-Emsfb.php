<?php

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
       //if(is_admin()==true) $this->checkDbchange();
    }

    /**
     * Initial plugin setup.
     */
    private function init_hooks(): void {
        register_activation_hook(
            EMSFB_PLUGIN_FILE,
            ['\Emsfb\Install', 'install']
        );
  
        //register_activation_hook(__FILE__ ,[$this, 'test_fun']);

    
    }


    /**
     * Includes classes and functions.
     */
    public function includes(): void {
        require_once $this->plugin_path . 'includes/class-Emsfb-install.php';

        if (is_admin()) {
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-admin.php';
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-create.php';
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-addon.php';
            if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
                
                require_once EMSFB_PLUGIN_DIRECTORY. '/vendor/smssended/class-Emsfb-sms.php';
            }
            
        }
        

        require_once $this->plugin_path . 'includes/class-Emsfb-public.php';
       // require_once $this->plugin_path . 'includes/class-Emsfb-webhook.php';
        
    }


    public function webhooks(){
    
       /* add_action('rest_api_init',  @function(){
    
          
              register_rest_route('efb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=> 'test_fun'
              ]); 
          }); */
    }


    public function checkDbchange(){
        global $wpdb;
        $test_tabale = $wpdb->prefix . "Emsfb_form";
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $test_tabale ) );
		$check_test_table = $wpdb->get_var( $query );
        $table_name = $wpdb->prefix . "emsfb_form";

        if(strlen($check_test_table)>0){
			if ( strcmp($table_name,$check_test_table)!=0) {
                $message =  esc_html__('The Easy Form Builder had Important update and require to deactivate and activate the plugin manually </br> Notice:Please do this act in immediately so forms of your site will available again.','easy-form-builder'); 
                ?>                    
                    <div class="notice notice-warning is-dismissible">
                        <p> <?php echo '<b>'.esc_html__('Warning').':</b> '. $message.''; ?> </p>
                    </div>
                <?php
            $this->email_send();
            }
        }
    }

    public static  function email_send(){
		$message=esc_html__('The Easy Form Builder had Important update and require to deactivate and activate the plugin manually </br> Notice:Please do this act in immediately so forms of your site will available again.','easy-form-builder'); 
		$usr=get_userdata(1);
		
		$users = get_super_admins();
		foreach ($users as $key => $value) {
			$user =get_user_by('login',$value);
			$to = $usr ->data->user_email;
			
			$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
			$headers = array(
				'MIME-Version: 1.0\r\n',
				'"Content-Type: text/html; charset=ISO-8859-1\r\n"',
				'From:'.$from.''
				);
			$subject = "Important Warning form ".get_bloginfo('name');			
			$to = wp_mail($to, $subject, strip_tags($message), $headers);
            

		}

	}

    



}
