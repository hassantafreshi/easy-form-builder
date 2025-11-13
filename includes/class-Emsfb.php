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


        if(is_admin()==false){
            $this->webhooks();
        }else{
           // Initialize Elementor compatibility for all admin pages
            $this->init_elementor_compatibility();
       }

    }

    /**
     * Initial plugin setup.
     */
    private function init_hooks(): void {
        register_activation_hook(
            EMSFB_PLUGIN_FILE,
            ['\Emsfb\Install', 'install']
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
            require_once $this->plugin_path . 'includes/admin/class-Emsfb-addon.php';
            if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {

                require_once EMSFB_PLUGIN_DIRECTORY. '/vendor/smssended/class-Emsfb-sms.php';
            }

        }


        require_once $this->plugin_path . 'includes/class-Emsfb-public.php';


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

    /**
     * Initialize Elementor compatibility for all EFB admin pages
     */
    public function init_elementor_compatibility() {
        // Only apply if Elementor is actually installed
        if (!$this->is_elementor_admin_active()) {
            return;
        }

        // Check if we're on any EFB admin page
        if (isset($_GET['page']) && (
            $_GET['page'] === 'Emsfb' ||
            $_GET['page'] === 'Emsfb_create' ||
            $_GET['page'] === 'Emsfb_addon' ||
            $_GET['page'] === 'Emsfb_sms_efb'
        )) {
            add_action('admin_enqueue_scripts', array($this, 'apply_elementor_admin_fixes'), 1);
        }
    }

    /**
     * Apply Elementor admin compatibility fixes to prevent conflicts
     */
    public function apply_elementor_admin_fixes() {
        // Add JavaScript to prevent Elementor admin conflicts
        add_action('admin_footer', array($this, 'elementor_admin_conflict_prevention'));
    }

    /**
     * Check if Elementor is active in admin context
     */
    public function is_elementor_admin_active() {
        // Check if Elementor plugin is active
        if (class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
            return true;
        }

        // Check via WordPress plugin functions
        if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
            return true;
        }

        return false;
    }

    /**
     * Add JavaScript to prevent Elementor admin conflicts
     */
    public function elementor_admin_conflict_prevention() {
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        ?>
        <script type="text/javascript">
        // Prevent Elementor admin conflicts with EFB Admin Pages
        (function($) {
            'use strict';

            // Store original methods before any modifications
            if (typeof window.efb_global_elementor_protection === 'undefined') {
                window.efb_global_elementor_protection = true;

                console.log('EFB Global: Initializing Elementor compatibility layer for <?php echo esc_js($current_page); ?>');

                // Prevent Elementor admin errors
                if (typeof elementorFrontend !== 'undefined') {
                    try {
                        // Safely check and initialize elementorFrontend.tools
                        if (!elementorFrontend.tools) {
                            elementorFrontend.tools = {};
                            console.log('EFB Global: Initialized missing elementorFrontend.tools');
                        }
                    } catch (e) {
                        console.log('EFB Global: Prevented Elementor frontend error:', e.message);
                    }
                }

                // Global error handling for dispatchEvent issues
                $(document).ready(function() {
                    // Prevent jQuery Deferred errors
                    $(window).on('error', function(e) {
                        if (e.originalEvent && e.originalEvent.message) {
                            var errorMessage = e.originalEvent.message.toLowerCase();
                            if (errorMessage.includes('dispatchevent') ||
                                errorMessage.includes('elementor') ||
                                errorMessage.includes('tools') ||
                                errorMessage.includes('cannot read properties of undefined')) {
                                console.log('EFB Global: Suppressed Elementor admin error on <?php echo esc_js($current_page); ?>:', errorMessage);
                                e.preventDefault();
                                return false;
                            }
                        }
                    });

                    // Protect Event.dispatchEvent calls
                    if (window.Event && Event.prototype.dispatchEvent) {
                        var originalDispatchEvent = Event.prototype.dispatchEvent;
                        Event.prototype.dispatchEvent = function(event) {
                            try {
                                if (typeof this.dispatchEvent === 'function') {
                                    return originalDispatchEvent.call(this, event);
                                }
                            } catch (e) {
                                console.log('EFB Global: Prevented dispatchEvent error on <?php echo esc_js($current_page); ?>:', e.message);
                                return false;
                            }
                        };
                    }
                });
            }
        })(jQuery);
        </script>
        <?php
    }

}
