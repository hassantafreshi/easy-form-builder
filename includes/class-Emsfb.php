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

        register_deactivation_hook(
            EMSFB_PLUGIN_FILE,
            [$this, 'plugin_deactivation_cleanup_efb']
        );

        // Hook for detecting new plugin installation
        add_action('activated_plugin', [$this, 'handle_new_plugin_activation_efb'], 10, 2);
        add_action('deactivated_plugin', [$this, 'clear_server_host_cache_efb']);

        // Hook for updating cache plugins list
        add_action('emsfb_update_cache_plugins_list', [$this, 'update_cache_plugins_list']);

        // Filter for getting server host with cache
        add_filter('emsfb_get_server_host', [$this, 'get_cached_server_host_efb']);

                 // Hook to run after plugin update
        add_action('upgrader_process_complete', [$this, 'plugin_update_completed_efb'], 10, 2);

        // Check version and run upgrade tasks if needed
        add_action('plugins_loaded', [$this, 'check_version_and_upgrade_efb']);
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

            // Check if EMSFB_PLUGIN_DIRECTORY . '/vendor/autofill/class-Emsfb-autofill.php' exists


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

public static function email_send_efb() {
        $message = esc_html__( 'The Easy Form Builder had Important update and require to deactivate and activate the plugin manually </br> Notice: Please do this act immediately so forms of your site will be available again.', 'easy-form-builder' );

        // Get all super admin users
        $super_admins = get_super_admins();

        if ( empty( $super_admins ) ) {
            return;
        }

        // Collect all valid email addresses
        $recipients = array();

        foreach ( $super_admins as $admin_login ) {
            $user = get_user_by( 'login', $admin_login );

            if ( $user && is_email( $user->user_email ) ) {
                $recipients[] = sanitize_email( $user->user_email );
            }
        }

        // If no valid recipients found, exit
        if ( empty( $recipients ) ) {
            return;
        }

        // Prepare email headers following WordPress standards
        $server_name = apply_filters('emsfb_get_server_host', 'yourdomain.com');
        $from_email  = 'no-reply@' . $server_name;
        $from_name   = get_bloginfo( 'name' );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
        );

        // Prepare subject with proper translation
        $subject = sprintf(
            /* translators: %s: Site name */
            esc_html__( 'Important Warning from %s', 'easy-form-builder' ),
            get_bloginfo( 'name' )
        );

        // Send email to all recipients at once (WordPress will handle BCC automatically)
        // This sends ONE email with all admins as recipients, not multiple emails
        wp_mail( $recipients, $subject, wp_kses_post( $message ), $headers );
    }

    public function handle_new_plugin_activation_efb($plugin, $network_wide = false) {
    error_log('EFB: New plugin activated - ' . $plugin);
        // List of cache plugins
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

        // Extract slug from plugin path (example: wp-rocket/wp-rocket.php -> wp-rocket)
        $plugin_slug = dirname($plugin);

        // If activated plugin is a cache plugin
        if (in_array($plugin_slug, $cache_plugins_slug)) {
           do_action('emsfb_update_cache_plugins_list');
        }
    }


    public function update_cache_plugins_list() {
        error_log('EFB: Updating cache plugins list on demand');
        // List of cache plugins
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

        // Get all plugins
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();

        foreach ($plugins as $plugin_file => $plugin_data) {
            // Only active plugins
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

        // Save or update
        $val = !empty($plugin_list) ? json_encode($plugin_list) : 0;
        $old_val = get_option('emsfb_cache_plugins', 0);

        if ($val != $old_val) {
            update_option('emsfb_cache_plugins', $val);
        }

        return $plugin_list;
    }

    /**
     * Get server host with permanent caching
     *
     * @since 3.9.5
     * @return string Server hostname
     */
    public function get_cached_server_host_efb() {
        // Check cache (using option instead of transient for permanence)
        $cached_host = get_option('emsfb_server_host_cache', false);

        if ($cached_host !== false) {
            return $cached_host;
        }

        // Calculate server host
        $server_host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'yourdomain.com';

        // Save to cache
        update_option('emsfb_server_host_cache', $server_host, false);

        return $server_host;
    }

    /**
     * Clear server host cache
     * Triggered when any plugin is activated/deactivated
     *
     * @since 3.9.5
     * @return void
     */
    public function clear_server_host_cache_efb()
    {
        delete_option('emsfb_server_host_cache');
    }

    /**
     * Centralized settings getter with multi-layer caching
     * Replaces all get_setting_Emsfb() methods across the plugin
     *
     * @since 3.9.5
     * @param string $mode Return mode: 'decoded' (default), 'pub', 'raw'
     * @return mixed Settings object, array, or string based on mode
     */
    public static function get_setting_Emsfb($mode = 'decoded')
    {
        // Layer 1: Static cache (fastest - in-request memory)
        static $staticCache = [];

        if (isset($staticCache[$mode])) {
            return $staticCache[$mode];
        }

        // Layer 2: WordPress object cache (Redis/Memcached compatible)
        $cacheKey = 'settings:' . $mode;
        $cached = wp_cache_get($cacheKey, 'emsfb');
        if ($cached !== false && !empty($cached)) {
            $staticCache[$mode] = $cached;
            return $cached;
        }

        // Layer 3: Transient cache (database, 30 minutes)
        $transient = get_transient('emsfb_settings_transient');

        // Layer 4: Direct database query (slowest fallback)
        if ($transient === false || empty($transient)) {
            global $wpdb;
            $table_name = $wpdb->prefix . "emsfb_setting";
            $raw = $wpdb->get_var("SELECT setting FROM $table_name ORDER BY id DESC LIMIT 1");

            if (empty($raw)) {
                return $mode === 'pub' ? [0, []] : 0;
            }

            // Save to option and transient
            update_option('emsfb_settings', $raw);
            set_transient('emsfb_settings_transient', $raw, 1800); // 30 minutes
        } else {
            $raw = $transient;
        }

        // Decode JSON
        $cleaned = str_replace('\\', '', $raw);
        $decoded = json_decode($cleaned);

        if ($decoded === null) {
            return $mode === 'pub' ? [0, []] : 'null';
        }

        // Handle different return modes
        $result = null;

        switch ($mode) {
            case 'pub':
                // Public settings with addons info
                $pro = intval(get_option('emsfb_pro')) === 1;
                $pubSettings = [
                    'pro' => $pro,
                    'trackingCode' => $decoded->trackingCode ?? '',
                    'siteKey' => $decoded->siteKey ?? '',
                    'mapKey' => $decoded->apiKeyMap ?? '',
                    'paymentKey' => $decoded->stripePKey ?? '',
                    'version' => $decoded->efb_version ?? '1.0.0',
                    'osLocationPicker' => $decoded->osLocationPicker ?? false,
                    'scaptcha' => $decoded->scaptcha ?? false,
                    'dsupfile' => $decoded->dsupfile ?? false,
                    'activeDlBtn' => $decoded->activeDlBtn ?? true,
                    'paypalPkey' => $decoded->paypalPkey ?? '',
                    'addons' => self::get_addons_list_efb($decoded),
                ];
                $result = [json_encode($pubSettings, JSON_UNESCAPED_UNICODE), $pubSettings];
                break;

            case 'raw':
                // Raw JSON string
                $result = $raw;
                break;

            case 'decoded':
            default:
                // Decoded object
                $result = $decoded;
                break;
        }

        // Save to all cache layers
        $staticCache[$mode] = $result;
        wp_cache_set($cacheKey, $result, 'emsfb', 3600); // 1 hour

        return $result;
    }

    /**
     * Get addons list from settings
     *
     * @param object $settings Decoded settings object
     * @return array Addons information
     */
    private static function get_addons_list_efb($settings)
    {
        $addons = [];

        // Check each addon
        $addonKeys = [
            'AdnSS' => 'SMS',
            'AdnAtF' => 'AutoFill',
            'AdnTlg' => 'Telegram',
            'AdnPPl' => 'PayPal',
            'AdnStripe' => 'Stripe',
        ];

        foreach ($addonKeys as $key => $name) {
            $optionValue = get_option('emsfb_addon_' . $key, false);
            if ($optionValue != false && $optionValue != 0) {
                $addons[$key] = [
                    'name' => $name,
                    'active' => true,
                    'version' => $optionValue,
                ];
            }
        }

        return $addons;
    }

    /**
     * Clean up plugin cache options on deactivation
     * Removes temporary cache data when plugin is deactivated
     *
     * @since 3.9.5
     * @return void
     */
    public static function plugin_deactivation_cleanup_efb()
    {
        // Delete cache-related options
        delete_option('emsfb_cache_plugins');
        delete_option('emsfb_server_host_cache');
        delete_option('emsfb_settings');


        // Delete transients
        delete_transient('emsfb_settings_transient');

        // Clear all emsfb transients from database
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_emsfb_%' OR option_name LIKE '_transient_timeout_emsfb_%'"
        );

        // Clear WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear emsfb cache group if using object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('emsfb');
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

    /**
     * Initialize Elementor compatibility for all EFB admin pages
     */
    public function init_elementor_compatibility_efb() {
        // Only apply if Elementor is actually installed
        if (!$this->is_elementor_admin_active_efb()) {
            return;
        }

        // Check if we're on any EFB admin page
        if (isset($_GET['page']) && (
            sanitize_key( $_GET['page'] ) === 'Emsfb' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_create' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_addon' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_sms_efb'
        )) {
            add_action('admin_enqueue_scripts', array($this, 'apply_elementor_admin_fixes_efb'), 1);
        }
    }

    /**
     * Apply Elementor admin compatibility fixes to prevent conflicts
     */
    public function apply_elementor_admin_fixes_efb() {
        // Add JavaScript to prevent Elementor admin conflicts
        add_action('admin_footer', array($this, 'elementor_admin_conflict_prevention_efb'));
    }

    /**
     * Check if Elementor is active in admin context
     */
    public function is_elementor_admin_active_efb() {
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
    public function elementor_admin_conflict_prevention_efb() {
        $current_page = isset($_GET['page']) ? sanitize_key( $_GET['page'] ) : '';
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
                                if (window.console && window.console.log && typeof window.efb_debug !== 'undefined' && window.efb_debug) {
                                    console.log('EFB: Suppressed Elementor error:', errorMessage);
                                }
                                e.preventDefault();
                                return false;
                            }
                        }
                    });

                    // Fix dispatchEvent errors - use EventTarget instead of Event
                    if (window.EventTarget && window.EventTarget.prototype && EventTarget.prototype.dispatchEvent) {
                        var originalDispatchEvent = EventTarget.prototype.dispatchEvent;
                        EventTarget.prototype.dispatchEvent = function(event) {
                            try {
                                return originalDispatchEvent.call(this, event);
                            } catch (e) {
                                if (window.console && typeof window.efb_debug !== 'undefined' && window.efb_debug) {
                                    console.log('EFB: dispatchEvent error caught:', e.message);
                                }
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

    /**
     * Check version and run upgrade tasks if needed
     *
     * @since 3.9.4
     * @return void
     */
    public function check_version_and_upgrade_efb() {
        $installed_version = get_option('emsfb_version', '0.0.0');
        $current_version = EMSFB_PLUGIN_VERSION;

        // If version has changed, run upgrade tasks
        if (version_compare($installed_version, $current_version, '<')) {
            $this->run_upgrade_tasks_efb($installed_version, $current_version);
            update_option('emsfb_version', $current_version);
        }
    }

    /**
     * Run upgrade tasks after plugin update
     *
     * @since 3.9.4
     * @param string $old_version Old plugin version
     * @param string $new_version New plugin version
     * @return void
     */
    private function run_upgrade_tasks_efb($old_version, $new_version) {
        // Clear all WordPress caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear object cache (Redis, Memcached, etc.)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('emsfb');
        }

        // Clear all form-related transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_efb_%' OR option_name LIKE '_transient_timeout_efb_%'"
        );

        // Log upgrade completion
        error_log(sprintf(
            'Easy Form Builder upgraded from %s to %s - All caches cleared',
            $old_version,
            $new_version
        ));
    }

    /**
     * Hook that runs when plugin is updated via WordPress admin
     *
     * @since 3.9.4
     * @param object $upgrader_object Plugin upgrader object
     * @param array $options Update options
     * @return void
     */
    public function plugin_update_completed_efb($upgrader_object, $options) {
        // Check if this is a plugin update
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        // Check if our plugin was updated
        $our_plugin = plugin_basename(EMSFB_PLUGIN_FILE);

        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === $our_plugin) {
                    // Our plugin was updated, clear caches
                    $this->run_upgrade_tasks_efb(
                        get_option('emsfb_version', '0.0.0'),
                        EMSFB_PLUGIN_VERSION
                    );
                    break;
                }
            }
        }
    }





}
