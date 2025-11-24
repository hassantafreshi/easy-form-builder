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
        if(is_admin()==false){ $this->webhooks();
        }else{
            $this->init_elementor_compatibility();
        }
        // if(is_admin()==true) $this->checkDbchangeEFB();

          // Initialize Elementor compatibility for all admin pages

    }

    /**
     * Initial plugin setup.
     */
    private function init_hooks(): void {
        register_activation_hook(
            EMSFB_PLUGIN_FILE,
            ['\Emsfb\Install', 'install']
        );

        // Hook برای تشخیص نصب افزونه جدید
        add_action('activated_plugin', [$this, 'handle_new_plugin_activation_efb'], 10, 2);

        // Hook برای به‌روزرسانی لیست افزونه‌های کش
        add_action('emsfb_update_cache_plugins_list', [$this, 'update_cache_plugins_list']);
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
            $sms_exists =get_option('emsfb_addon_AdnSS',false);
            if ($sms_exists != false && $sms_exists != 0) {
                $sms_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/class-Emsfb-sms.php';
                if (file_exists($sms_file_path)) {
                    require_once $sms_file_path;
                } else {
                    error_log('SMS file does not exist: ' . $sms_file_path);
                }
            }
            $auto_fill_exists =get_option('emsfb_addon_AdnAtF',false);
            if ($auto_fill_exists != false && $auto_fill_exists != 0) {
                $auto_fill_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/autofill/class-Emsfb-autofill.php';
                if (file_exists($auto_fill_file_path)) {
                    require_once $auto_fill_file_path;
                } else {
                    error_log('Auto Fill file does not exist: ' . $auto_fill_file_path);
                }
            }

            //check is EMSFB_PLUGIN_DIRECTORY. '/vendor/autofill/class-Emsfb-autofill.php' exist


        }


        require_once $this->plugin_path . 'includes/class-Emsfb-public.php';
       // require_once $this->plugin_path . 'includes/class-Emsfb-webhook.php';

       //write a filter for activate new plugin after that call the function activated_plugin
       // add_filter('activate_new_plugin', [$this, 'handle_new_plugin_activation_efb'], 10, 2);
    }


    public function webhooks(){

       /* add_action('rest_api_init',  @function(){


              register_rest_route('efb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=> 'test_fun'
              ]);
          }); */
    }


    public function checkDbchangeEFB(){
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
            $this->email_send_efb();
            }
        }
    }

    public static  function email_send_efb(){
		$message=esc_html__('The Easy Form Builder had Important update and require to deactivate and activate the plugin manually </br> Notice:Please do this act in immediately so forms of your site will available again.','easy-form-builder');
		$usr=get_userdata(1);

		$users = get_super_admins();
		foreach ($users as $key => $value) {
			$user =get_user_by('login',$value);
			$to = $usr ->data->user_email;

			$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
			$headers = array(
				'MIME-Version: 1.0\r\n',
				'"Content-Type: text/html; charset=UTF-8\r\n"',
				'From:'.$from.''
				);
			$subject = "Important Warning form ".get_bloginfo('name');
			$to = wp_mail($to, $subject, strip_tags($message), $headers);


		}

	}

    public function handle_new_plugin_activation_efb($plugin, $network_wide = false) {
    error_log('EFB: New plugin activated - ' . $plugin);
        // لیست افزونه‌های کش
        $cache_plugins_slug = array(
            'wp-optimize', 'hummingbird-performance', 'big-scoots-cache', 'wp-cloudflare-page-cache',
            'breeze', 'jetpack', 'w3-total-cache', 'wp-fastest-cache',
            'wp-rocket', 'comet-cache', 'hyper-cache', 'cache-enabler',
            'wp-super-cache', 'litespeed-cache', 'nitropack', 'jetpack-boost',
            'autoptimize', 'wp-rest-cache', 'speedycache', 'clear-cache-for-widgets',
            'wp-cache', 'wp-cache-system', 'atec-cache-info', 'atec-cache-apcu',
            'wpspeed', 'wp-speed', 'flying-press',
            'sg-optimizer', 'swift-performance', 'powered-cache'
        );

        // استخراج slug از مسیر افزونه (مثال: wp-rocket/wp-rocket.php -> wp-rocket)
        $plugin_slug = dirname($plugin);

        // اگر افزونه فعال‌شده یک افزونه کش است
        if (in_array($plugin_slug, $cache_plugins_slug)) {
           do_action('emsfb_update_cache_plugins_list');
        }
    }


    public function update_cache_plugins_list() {
        error_log('EFB: Updating cache plugins list on demand');
        // لیست افزونه‌های کش
        $cache_plugins_slug = array(
            'wp-optimize', 'hummingbird-performance', 'big-scoots-cache', 'wp-cloudflare-page-cache',
            'breeze', 'jetpack', 'w3-total-cache', 'wp-fastest-cache',
            'wp-rocket', 'comet-cache', 'hyper-cache', 'cache-enabler',
            'wp-super-cache', 'litespeed-cache', 'nitropack', 'jetpack-boost',
            'autoptimize', 'wp-rest-cache', 'speedycache', 'clear-cache-for-widgets',
            'wp-cache', 'wp-cache-system', 'atec-cache-info', 'atec-cache-apcu',
            'wpspeed', 'wp-speed', 'flying-press',
            'sg-optimizer', 'swift-performance', 'powered-cache'
        );


        $cache_plugins_slug = apply_filters('emsfb_cache_plugins_slug', $cache_plugins_slug);

        // دریافت تمام افزونه‌ها
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();

        foreach ($plugins as $plugin_file => $plugin_data) {
            // فقط افزونه‌های فعال
            if (!in_array($plugin_file, $active_plugins)) {
                continue;
            }

            $slug = explode('/', $plugin_file)[0];
            $exists_cache = in_array($slug, $cache_plugins_slug);

            if ($exists_cache) {
                $plugin_list[] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'slug' => $slug
                );
            }
        }

        // ذخیره یا به‌روزرسانی
        $val = !empty($plugin_list) ? json_encode($plugin_list) : 0;
        $old_val = get_option('emsfb_cache_plugins', 0);

        if ($val != $old_val) {
            update_option('emsfb_cache_plugins', $val);
        }

        return $plugin_list;
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
