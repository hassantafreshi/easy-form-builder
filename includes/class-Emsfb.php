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

        // Hook for file access check after activation
        add_action('emsfb_file_access_check_after_activation', 'emsfb_check_file_access_efb');

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
            $ac = self::get_setting_Emsfb('decoded');

            $payment_exists = isset($ac->AdnPAP) ? (int) $ac->AdnPAP : 0;
            if ($payment_exists === 1) {
                $payment_file_path = $this->plugin_path . 'vendor/paypal/class-Emsfb-paypal-payment.php';
                if (file_exists($payment_file_path)) {
                    require_once $payment_file_path;
                    new \Emsfb\PaypalPayment();
                } else {
                    error_log('Payment file does not exist: ' . $payment_file_path);
                }
            }

            $stripe_exists = isset($ac->AdnSPF) ? (int) $ac->AdnSPF : 0;
            if ($stripe_exists === 1) {
                $stripe_file_path = $this->plugin_path . 'vendor/stripe/class-Emsfb-stripe-payment.php';
                if (file_exists($stripe_file_path)) {
                    require_once $stripe_file_path;
                    new \Emsfb\StripePayment();
                } else {
                    error_log('Stripe payment file does not exist: ' . $stripe_file_path);
                }
            }
           // $sms_exists =get_option('emsfb_addon_AdnSS',false);

            $sms_exists = isset($ac->AdnSS) ? (int) $ac->AdnSS : 0;
            if ($sms_exists === 1) {
                $sms_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/class-Emsfb-sms.php';
                if (file_exists($sms_file_path)) {
                    require_once $sms_file_path;
                } else {
                    error_log('SMS file does not exist: ' . $sms_file_path);
                }
            }
            $auto_fill_exists = isset($ac->AdnATF) ? (int) $ac->AdnATF : 0;
           // $auto_fill_exists =get_option('emsfb_addon_AdnATF',false);
            if ($auto_fill_exists === 1) {
                $auto_fill_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/autofill/class-Emsfb-autofill.php';
                if (file_exists($auto_fill_file_path)) {
                    require_once $auto_fill_file_path;
                } else {
                    error_log('Auto Fill file does not exist: ' . $auto_fill_file_path);
                }
            }
            $telegram_exists = isset($ac->AdnTLG) ? (int) $ac->AdnTLG : 0;
            error_log('Telegram addon check: AdnTLG=' . (isset($ac->AdnTLG) ? $ac->AdnTLG : 'NOT SET') . ' => telegram_exists=' . $telegram_exists);
              if ($telegram_exists >= 1) {
                  $telegram_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/class-Emsfb-telegram.php';
                  if (file_exists($telegram_file_path)) {
                      require_once $telegram_file_path;
                      new \Emsfb\telegramlistefb();
                  } else {
                      error_log('Telegram file does not exist: ' . $telegram_file_path);
                  }

                  // Load telegram sending class (hooks registration for both admin & public)
                  // بارگذاری کلاس ارسال تلگرام (ثبت هوک‌ها برای ادمین و عمومی)
                  $telegram_send_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php';
                  if (file_exists($telegram_send_path)) {
                      require_once $telegram_send_path;
                  }
              }
            // Check if EMSFB_PLUGIN_DIRECTORY . '/vendor/autofill/class-Emsfb-autofill.php' exists


		}
        /* ──────────────────────────────────────────────────────────
		 * addon REST route registration (runs on ALL requests).
		 *
		 * Each addon has a small routes-efb.php file that hooks into
		 * 'efb_register_payment_rest_routes'.  The action is fired by
		 * _Public during rest_api_init, so routes are only registered
		 * when the WP REST API initialises.
		 *
		 * Route files are loaded conditionally based on addon settings
		 * and file_exists() checks, so disabled or missing addons
		 * never cause errors.
		 *
		 * @since 4.3.0
		 * ────────────────────────────────────────────────────────── */
		$ac_routes = self::get_setting_Emsfb( 'decoded' );


        if (is_object($ac_routes)) {
            // --- Telegram notification hooks (needed for REST API form submit) ---
            $telegram_public = isset($ac_routes->AdnTLG) ? (int) $ac_routes->AdnTLG : 0;
            if ($telegram_public >= 1) {
                // فقط فایل ارسال تلگرام بارگذاری شود (بدون UI ادمین)
                // Only load telegram sending class (without admin UI class)
                // تابع efb_telegram_debug_log اگر وجود نداشته باشد در telegram-new-efb.php تعریف می‌شود
                $telegram_send_path_public = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php';
                if (file_exists($telegram_send_path_public)) {
                    require_once $telegram_send_path_public;
                    error_log('[EFB DIAG] Telegram send class loaded for non-admin (REST API) context');
                }
            }

            // --- SMS notification hooks (needed for REST API form submit) ---
            $sms_public = isset($ac_routes->AdnSS) ? (int) $ac_routes->AdnSS : 0;
            if ($sms_public === 1) {
                $sms_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/class-Emsfb-sms.php';
                if (file_exists($sms_file_path)) {
                    require_once $sms_file_path;
                    error_log('[EFB DIAG] SMS class loaded for non-admin (REST API) context');
                }
            }
            // PayPal routes (AdnPAP)
			if ( ! empty( $ac_routes->AdnPAP ) ) {
				$f = $this->plugin_path . 'vendor/paypal/routes-efb.php';
				if ( file_exists( $f ) ) {
					require_once $f;
				}
			}

			// Stripe routes (AdnSPF)
			if ( ! empty( $ac_routes->AdnSPF ) ) {
				$f = $this->plugin_path . 'vendor/stripe/routes-efb.php';
				if ( file_exists( $f ) ) {
					require_once $f;
				}
			}

			// PersiaPay / Zarinpal routes (AdnPPF)
			if ( ! empty( $ac_routes->AdnPPF ) ) {
				$f = $this->plugin_path . 'vendor/persiapay/routes-efb.php';
				if ( file_exists( $f ) ) {
					require_once $f;
				}
			}
        }


		$shield_file = $this->plugin_path . 'includes/integrations/class-Emsfb-shield-silentcaptcha.php';
		if (file_exists($shield_file)) {
			require_once $shield_file;
			new Emsfb_Shield_SilentCaptcha_Integration();
		}




		require_once $this->plugin_path . 'includes/class-Emsfb-public.php';
       // require_once $this->plugin_path . 'includes/class-Emsfb-webhook.php';

       // Load page builder integrations (available for both admin and frontend)
       $this->load_page_builder_integrations();

       //write a filter for activate new plugin after that call the function activated_plugin
       // add_filter('activate_new_plugin', [$this, 'handle_new_plugin_activation_efb'], 10, 2);
    }

    /**
     * Load page builder integrations (Gutenberg, Elementor, WPBakery, Divi, Beaver Builder, Brizy, Oxygen)
     *
     * @since 4.0.0
     */
    private function load_page_builder_integrations(): void {
        // Load shared widgets helper class first
        require_once $this->plugin_path . 'includes/class-Emsfb-widgets-helper.php';

        // Load Gutenberg block (always available as it's core WordPress)
        if (function_exists('register_block_type')) {
            require_once $this->plugin_path . 'includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php';
        }

        // Load Elementor integration (if Elementor is active)
        if (did_action('elementor/loaded') || class_exists('\Elementor\Plugin')) {
            require_once $this->plugin_path . 'includes/page-builders/elementor/class-Emsfb-elementor.php';
        } else {
            // Hook for later loading if Elementor is loaded after this plugin
            add_action('elementor/loaded', function() {
                if (!class_exists('Emsfb_Elementor_Integration')) {
                    require_once EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/elementor/class-Emsfb-elementor.php';
                }
            });
        }

        // Load WPBakery integration (if WPBakery is active)
        if (defined('WPB_VC_VERSION') || class_exists('Vc_Manager')) {
            require_once $this->plugin_path . 'includes/page-builders/wpbakery/class-Emsfb-wpbakery.php';
        } else {
            // Hook for later loading if WPBakery is loaded after this plugin
            add_action('vc_before_init', function() {
                if (!class_exists('Emsfb_WPBakery_Integration')) {
                    require_once EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/wpbakery/class-Emsfb-wpbakery.php';
                }
            }, 5);
        }

        // Load Visual Composer Website Builder integration (if Visual Composer is active)
        // Note: This is different from WPBakery (formerly Visual Composer)
        if (defined('VCV_VERSION')) {
            require_once $this->plugin_path . 'includes/page-builders/visual-composer/class-Emsfb-visual-composer.php';
        } else {
            add_action('vcv:api', function() {
                if (!class_exists('Emsfb_Visual_Composer_Integration')) {
                    require_once EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/visual-composer/class-Emsfb-visual-composer.php';
                }
            }, 5);
        }
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
                $message =  esc_html__('The Easy Form Builder had Important update and require to deactivate and activate the plugin manually. Notice: Please do this act immediately so forms of your site will be available again.','easy-form-builder');
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
        $message = esc_html__( 'The Easy Form Builder had Important update and require to deactivate and activate the plugin manually. Notice: Please do this act immediately so forms of your site will be available again.', 'easy-form-builder' );

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

        // Allow clearing the static cache (called by set_setting_Emsfb)
        if ($mode === '_clear_cache') {
            $staticCache = [];
            return true;
        }

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
                if ($mode === 'pub') return [0, []];
                if ($mode === 'raw') return '';
                return new \stdClass();
            }

            // Save to option and transient
            update_option('emsfb_settings', $raw);
            set_transient('emsfb_settings_transient', $raw, 1800); // 30 minutes
        } else {
            $raw = $transient;
        }

        // â”€â”€ Clean raw string before parsing â”€â”€
        // Remove BOM, NULL bytes, invalid UTF-8, HTML entities, etc.
        $raw = self::clean_raw_json_efb($raw);

        // â”€â”€ Truncation detection â”€â”€
        // If JSON doesn't end with } or ] it was likely truncated by a TEXT column
        $trimmedEnd = rtrim($raw);
        if (!empty($trimmedEnd) && !preg_match('/[}\]]$/', $trimmedEnd)) {
        }

        // Decode JSON â€” try direct parse first (new clean format),
        // then stripslashes for backward compatibility (old \" escaped format)
        $decoded = json_decode($raw);
        if ($decoded === null) {
            // Try removing escape layers (could be multi-layered: \", \\", etc.)
            $clean = $raw;
            $max_attempts = 5;
            for ($i = 0; $i < $max_attempts; $i++) {
                $clean = stripslashes($clean);
                $decoded = json_decode($clean);
                if ($decoded !== null) {
                    break;
                }
            }
            // Auto-repair: if we managed to decode, save the clean version back
            if ($decoded !== null) {
                $cleanJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                update_option('emsfb_settings', $cleanJson);
                set_transient('emsfb_settings_transient', $cleanJson, 1800);
                $raw = $cleanJson;
                // Also fix the DB row
                global $wpdb;
                $table_name = $wpdb->prefix . "emsfb_setting";
                $latest_id = $wpdb->get_var("SELECT id FROM $table_name ORDER BY id DESC LIMIT 1");
                if ($latest_id) {
                    $wpdb->update($table_name, ['setting' => $cleanJson], ['id' => $latest_id], ['%s'], ['%d']);
                }
            }
        }
        if ($decoded === null) {
            // Fallback to defaults so the plugin remains functional
            $decoded = self::get_default_settings_efb();
        }

        // Handle different return modes
        $result = null;

        switch ($mode) {
            case 'pub':
                // Public settings with addons info
                $pro = absint(get_option('emsfb_pro'));
                $pro = $pro == 1 || $pro == 2 ? true : false;
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
                    // Response box color settings
                    'respPrimary' => $decoded->respPrimary ?? '#3644d2',
                    'respPrimaryDark' => $decoded->respPrimaryDark ?? '#202a8d',
                    'respAccent' => $decoded->respAccent ?? '#ffc107',
                    'respText' => $decoded->respText ?? '#1a1a2e',
                    'respTextMuted' => $decoded->respTextMuted ?? '#657096',
                    'respBgCard' => $decoded->respBgCard ?? '#ffffff',
                    'respBgMeta' => $decoded->respBgMeta ?? '#f6f7fb',
                    'respBgTrack' => $decoded->respBgTrack ?? '#ffffff',
                    'respBgResp' => $decoded->respBgResp ?? '#f8f9fd',
                    'respBgEditor' => $decoded->respBgEditor ?? '#ffffff',
                    'respEditorText' => $decoded->respEditorText ?? '#1a1a2e',
                    'respEditorPh' => $decoded->respEditorPh ?? '#a0aec0',
                    'respBtnText' => $decoded->respBtnText ?? '#ffffff',
                    'respFontFamily' => $decoded->respFontFamily ?? 'inherit',
                    'respFontSize' => $decoded->respFontSize ?? '0.9rem',
                    'respCustomFont' => $decoded->respCustomFont ?? '',
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
                // Append package type
                // 0 = expired, 1 = pro, 2 = free plan, 3 = free plus
                $package_type = get_option('emsfb_pro', 10);
                $decoded->package_type = $package_type;
                $result = $decoded;
                break;
        }

        // Save to all cache layers

        $staticCache[$mode] = $result;
        wp_cache_set($cacheKey, $result, 'emsfb', 3600); // 1 hour

        return $result;
    }


    public static function get_efbFunction(): efbFunction {

        static $instances = [];
        $cache_key = 'efb_function_' . (function_exists('get_current_blog_id') ? get_current_blog_id() : '1');


        if (isset($instances[$cache_key]) && $instances[$cache_key] instanceof efbFunction) {
            return $instances[$cache_key];
        }


        try {
            if (!class_exists('efbFunction', false)) {
                $functions_file = EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php';
                if (!is_readable($functions_file)) {
                    throw new \Exception('Functions file not readable: ' . $functions_file);
                }
                require_once $functions_file;
            }


            if (!class_exists('efbFunction')) {
                throw new \Exception('efbFunction class not found after require');
            }

            $instances[$cache_key] = new efbFunction();
            return $instances[$cache_key];

        } catch (\Exception $e) {
            // Log error Ø¨Ø±Ø§ÛŒ debugging
            if (function_exists('error_log')) {
                error_log('EFB get_efbFunction error: ' . $e->getMessage());
            }

            throw $e; // Ø¯Ø± ØµÙˆØ±Øª Ø´Ú©Ø³Øª Ú©Ø§Ù…Ù„
        }
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
                	/*
            AdnSPF == stripe payment
            AdnOF == offline form
            AdnPPF == persia payment
            AdnATC == advance tracking code
            AdnSS == sms service
            AdnCPF == crypto payment
            AdnESZ == zone picker
            AdnSE == email service
            AdnWHS == webhook
            AdnPAP == paypal
            AdnWSP == whitestudio pay
            AdnSMF == smart form
            AdnPLF == passwordless form
            AdnMSF == membership form
            AdnBEF == booking and event form
            'AdnPDP'=> persian data picker,
			'AdnADP'=> arabic data picker
        */
        // Check each addon
        $addonKeys = [
            'AdnSS' => 'SMS',
            'AdnATF' => 'AutoFill',
            'AdnTLG' => 'Telegram',
            'AdnPAP' => 'PayPal',
            'AdnSPF' => 'Stripe',
            'AdnPPF' => 'Persia Payment',
            'AdnOF' => 'offline form',

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
	    if (!is_admin()) {
			return;
		}
        // If version has changed, run upgrade tasks
        if (version_compare($installed_version, $current_version, '<')) {
            error_log(sprintf('EFB: Detected version change from %s to %s. Running upgrade tasks.', $installed_version, $current_version));
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

        // â”€â”€ Migration: Upgrade setting column from TEXT to LONGTEXT â”€â”€
        // TEXT is ~65KB which can truncate large emailTemp settings.
        // LONGTEXT supports up to 4GB.
        $table_setting = $wpdb->prefix . 'emsfb_setting';
        $wpdb->query("ALTER TABLE `{$table_setting}` MODIFY `setting` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL");

        // â”€â”€ Migration: Fix double-escaped JSON in emsfb_setting table â”€â”€
        // Previous versions used str_replace('"','\"') after json_encode,
        // which stored {\"key\":\"val\"} instead of {"key":"val"}.
        // This migration fixes ALL rows in one pass during upgrade.
        $this->migrate_fix_double_escaped_settings_efb($wpdb);

        // Log upgrade completion
        error_log(sprintf(
            'Easy Form Builder upgraded from %s to %s - All caches cleared',
            $old_version,
            $new_version
        ));

                    // Migrate activeCode users to pro when upgrading from version < 4
            if (version_compare($old_version, '4', '<')) {
                $activeCode = get_option('emsfb_pro_activeCode', '');
                if (empty($activeCode)) {
                    $settings = self::get_setting_Emsfb('decoded');
                    if (isset($settings->activeCode)) {
                        $activeCode = $settings->activeCode;
                    }
                }
                if (!empty($activeCode) && strlen($activeCode) > 5) {
                    update_option('emsfb_pro', 1);
                }
            }


    }

    /**
     * Migration: Fix double-escaped JSON in emsfb_setting table
     *
     * Previous versions incorrectly used str_replace('"','\"') after json_encode,
     * which stored {\"key\":\"val\"} instead of {"key":"val"}.
     * This can also be multi-layered: {\\\"key\\\"...} from repeated saves.
     *
     * This method:
     * 1. Reads ALL rows from emsfb_setting
     * 2. For each row, attempts json_decode â†’ if fails, applies stripslashes
     *    repeatedly until valid JSON is obtained
     * 3. Updates the row with clean JSON
     * 4. Also clears the wp_options cache (emsfb_settings) and transient
     *
     * @since 4.0.0
     * @param \wpdb $wpdb WordPress database object
     * @return int Number of rows repaired
     */
    private function migrate_fix_double_escaped_settings_efb($wpdb) {
        $table_name = $wpdb->prefix . "emsfb_setting";

        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        if (!$table_exists) {
            return 0;
        }

        $rows = $wpdb->get_results("SELECT id, setting FROM $table_name");
        if (empty($rows)) {
            return 0;
        }

        $repaired = 0;
        foreach ($rows as $row) {
            $raw = $row->setting;

            // Clean common corruption artifacts (BOM, NULL bytes, invalid UTF-8, etc.)
            $cleaned = self::clean_raw_json_efb($raw);

            // Skip if already valid JSON (with or without cleaning)
            if (json_decode($cleaned) !== null) {
                // Still save if cleaning changed the string
                if ($cleaned !== $raw) {
                    $cleanJson = json_encode(json_decode($cleaned), JSON_UNESCAPED_UNICODE);
                    $wpdb->update($table_name, ['setting' => $cleanJson], ['id' => $row->id], ['%s'], ['%d']);
                    $repaired++;
                }
                continue;
            }

            // Try stripslashes (possibly multiple layers of escaping)
            $clean = $cleaned;
            $max_attempts = 5; // prevent infinite loop
            for ($i = 0; $i < $max_attempts; $i++) {
                $clean = stripslashes($clean);
                if (json_decode($clean) !== null) {
                    break;
                }
            }

            // Validate the result
            $decoded = json_decode($clean);
            if ($decoded === null) {
                continue;
            }

            // Re-encode to ensure perfectly clean JSON
            $cleanJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            // Update the row
            $wpdb->update(
                $table_name,
                ['setting' => $cleanJson],
                ['id' => $row->id],
                ['%s'],
                ['%d']
            );
            $repaired++;
        }

        // Clear all caches so the clean data is loaded
        if ($repaired > 0) {
            delete_option('emsfb_settings');
            delete_transient('emsfb_settings_transient');
            wp_cache_delete('settings:decoded', 'emsfb');
            wp_cache_delete('settings:pub', 'emsfb');
            wp_cache_delete('settings:raw', 'emsfb');
            self::get_setting_Emsfb('_clear_cache');
        }

        return $repaired;
    }

    /**
     * Clean a raw JSON string by removing common corruption artifacts
     *
     * Handles: UTF-8 BOM, NULL bytes, invisible Unicode characters,
     * invalid UTF-8 sequences, HTML entities, and control characters.
     *
     * @since 4.0.0
     * @param string $raw The raw string from database
     * @return string Cleaned string ready for json_decode
     */
    private static function clean_raw_json_efb($raw) {
        if (empty($raw) || !is_string($raw)) {
            return '';
        }

        // 1. Remove UTF-8 BOM (Byte Order Mark) â€” \xEF\xBB\xBF
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }

        // 2. Remove NULL bytes
        $raw = str_replace("\0", '', $raw);

        // 3. Remove invisible Unicode characters (ZWNJ, ZWJ, ZWNBSP, BOM in UTF-8, etc.)
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{2060}]/u', '', $raw);

        // 4. Trim whitespace and control characters
        $raw = trim($raw);

        // 5. Fix invalid UTF-8 sequences
        if (function_exists('mb_convert_encoding')) {
            // This strips invalid sequences and replaces with valid UTF-8
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        // 6. If JSON is HTML-encoded (&quot; â†’ ", &amp; â†’ &, etc.)
        if (strpos($raw, '&quot;') !== false || strpos($raw, '&#34;') !== false) {
            $candidate = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($candidate) !== null) {
                $raw = $candidate;
            }
        }

        // 7. Remove control characters (except tab, newline, carriage return which are valid in JSON strings)
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw);

        return $raw;
    }

    /**
     * Get default plugin settings
     *
     * Provides a safe fallback when settings cannot be recovered from the database.
     * The plugin can work (in limited mode) with these defaults.
     *
     * @since 4.0.0
     * @return \stdClass Default settings object
     */
    public static function get_default_settings_efb() {
        $defaults = new \stdClass();
        $defaults->activeCode        = '';
        $defaults->siteKey           = '';
        $defaults->secretKey         = '';
        $defaults->emailSupporter    = get_option('admin_email', '');
        $defaults->apiKeyMap         = '';
        $defaults->smtp              = false;
        $defaults->text              = '';
        $defaults->bootstrap         = '';
        $defaults->emailTemp         = '';
        $defaults->paypalPKey        = '';
        $defaults->paypalSKey        = '';
        $defaults->stripePKey        = '';
        $defaults->stripeSKey        = '';
        $defaults->payToken          = '';
        $defaults->act_local_efb     = '';
        $defaults->scaptcha          = '';
        $defaults->shield_silent_captcha = '';
        $defaults->activeDlBtn       = '';
        $defaults->dsupfile          = '1';
        $defaults->sms_config        = 'null';
        $defaults->AdnSPF            = '0';
        $defaults->AdnOF             = '0';
        $defaults->AdnPPF            = '0';
        $defaults->AdnATC            = '0';
        $defaults->AdnSS             = '0';
        $defaults->AdnCPF            = '0';
        $defaults->AdnESZ            = '0';
        $defaults->AdnSE             = '0';
        $defaults->AdnWHS            = '0';
        $defaults->AdnPAP            = '0';
        $defaults->AdnWSP            = '0';
        $defaults->AdnSMF            = '0';
        $defaults->AdnPLF            = '0';
        $defaults->AdnMSF            = '0';
        $defaults->AdnBEF            = '0';
        $defaults->AdnPDP            = '0';
        $defaults->AdnADP            = '0';
        $defaults->AdnTLG            = '0';
        $defaults->phnNo             = '';
        $defaults->femail            = '';
        $defaults->email_key         = '';
        $defaults->showIp            = '';
        $defaults->adminSN           = '1';
        $defaults->osLocationPicker  = '';
        $defaults->sessionDuration   = '5';
        $defaults->respPrimary       = '#3644d2';
        $defaults->respPrimaryDark   = '#202a8d';
        $defaults->respAccent        = '#ffc107';
        $defaults->respText          = '#1a1a2e';
        $defaults->respTextMuted     = '#657096';
        $defaults->respBgCard        = '#ffffff';
        $defaults->respBgMeta        = '#f6f7fb';
        $defaults->respBgTrack       = '#ffffff';
        $defaults->respBgResp        = '#f8f9fd';
        $defaults->respBgEditor      = '#ffffff';
        $defaults->respEditorText    = '#1a1a2e';
        $defaults->respEditorPh      = '#a0aec0';
        $defaults->respBtnText       = '#ffffff';
        $defaults->respFontFamily    = 'inherit';
        $defaults->respFontSize      = '0.9rem';
        $defaults->respCustomFont    = '';
        $defaults->efb_version       = defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '4.0.0';
        return $defaults;
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
