<?php

if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}

class Emsfb {
    public $plugin_path = "";

    public $plugin_url = "";

    public function __construct() {
        $this->plugin_path = EMSFB_PLUGIN_DIRECTORY;
        $this->plugin_url  = EMSFB_PLUGIN_URL;

        $this->includes();
        $this->init_hooks();
        if(is_admin()==false){ $this->webhooks();
        }else{
            $this->init_elementor_compatibility();
        }

    }

    private function init_hooks(): void {
        register_activation_hook(
            EMSFB_PLUGIN_FILE,
            ['\Emsfb\Install', 'install']
        );

        register_deactivation_hook(
            EMSFB_PLUGIN_FILE,
            [$this, 'plugin_deactivation_cleanup_efb']
        );

        add_action('activated_plugin', [$this, 'handle_new_plugin_activation_efb'], 10, 2);
        add_action('deactivated_plugin', [$this, 'clear_server_host_cache_efb']);

        add_action('emsfb_update_cache_plugins_list', [$this, 'update_cache_plugins_list']);

        add_filter('emsfb_get_server_host', [$this, 'get_cached_server_host_efb']);

        add_action('emsfb_file_access_check_after_activation', 'emsfb_check_file_access_efb');

        add_action('upgrader_process_complete', [$this, 'plugin_update_completed_efb'], 10, 2);

        add_action('plugins_loaded', [$this, 'check_version_and_upgrade_efb']);
    }

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
                }
            }

            $stripe_exists = isset($ac->AdnSPF) ? (int) $ac->AdnSPF : 0;
            if ($stripe_exists === 1) {
                $stripe_file_path = $this->plugin_path . 'vendor/stripe/class-Emsfb-stripe-payment.php';
                if (file_exists($stripe_file_path)) {
                    require_once $stripe_file_path;
                    new \Emsfb\StripePayment();
                }
            }

            $sms_exists = isset($ac->AdnSS) ? (int) $ac->AdnSS : 0;
            if ($sms_exists === 1) {
                $sms_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/class-Emsfb-sms.php';
                if (file_exists($sms_file_path)) {
                    require_once $sms_file_path;
                }
            }
            $auto_fill_exists = isset($ac->AdnATF) ? (int) $ac->AdnATF : 0;

            if ($auto_fill_exists === 1) {
                $auto_fill_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/autofill/class-Emsfb-autofill.php';
                if (file_exists($auto_fill_file_path)) {
                    require_once $auto_fill_file_path;
                }
            }
            $telegram_exists = isset($ac->AdnTLG) ? (int) $ac->AdnTLG : 0;
              if ($telegram_exists >= 1) {
                  $telegram_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/class-Emsfb-telegram.php';
                  if (file_exists($telegram_file_path)) {
                      require_once $telegram_file_path;
                      new \Emsfb\telegramlistefb();
                  }


                  $telegram_send_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php';
                  if (file_exists($telegram_send_path)) {
                      require_once $telegram_send_path;
                  }
              }

		}

		$ac_routes = self::get_setting_Emsfb( 'decoded' );

        if (is_object($ac_routes)) {

            $telegram_public = isset($ac_routes->AdnTLG) ? (int) $ac_routes->AdnTLG : 0;
            if ($telegram_public >= 1) {

                $telegram_send_path_public = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php';
                if (file_exists($telegram_send_path_public)) {
                    require_once $telegram_send_path_public;
                }
            }

            $sms_public = isset($ac_routes->AdnSS) ? (int) $ac_routes->AdnSS : 0;
            if ($sms_public === 1) {
                $sms_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/class-Emsfb-sms.php';
                if (file_exists($sms_file_path)) {
                    require_once $sms_file_path;
                }
            }

			if ( ! empty( $ac_routes->AdnPAP ) ) {
				$f = $this->plugin_path . 'vendor/paypal/routes-efb.php';
				if ( file_exists( $f ) ) {
					require_once $f;
				}
			}

			if ( ! empty( $ac_routes->AdnSPF ) ) {
				$f = $this->plugin_path . 'vendor/stripe/routes-efb.php';
				if ( file_exists( $f ) ) {
					require_once $f;
				}
			}

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

       $this->load_page_builder_integrations();

    }

    private function load_page_builder_integrations(): void {

        require_once $this->plugin_path . 'includes/class-Emsfb-widgets-helper.php';

        if (function_exists('register_block_type')) {
            require_once $this->plugin_path . 'includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php';
        }

        if (did_action('elementor/loaded') || class_exists('\Elementor\Plugin')) {
            require_once $this->plugin_path . 'includes/page-builders/elementor/class-Emsfb-elementor.php';
        } else {

            add_action('elementor/loaded', function() {
                if (!class_exists('Emsfb_Elementor_Integration')) {
                    require_once EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/elementor/class-Emsfb-elementor.php';
                }
            });
        }

        if (defined('WPB_VC_VERSION') || class_exists('Vc_Manager')) {
            require_once $this->plugin_path . 'includes/page-builders/wpbakery/class-Emsfb-wpbakery.php';
        } else {

            add_action('vc_before_init', function() {
                if (!class_exists('Emsfb_WPBakery_Integration')) {
                    require_once EMSFB_PLUGIN_DIRECTORY . 'includes/page-builders/wpbakery/class-Emsfb-wpbakery.php';
                }
            }, 5);
        }

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
                        <p> <?php echo '<b>'.esc_html__('Warning').':</b> '. wp_kses_post($message); ?> </p>
                    </div>
                <?php
            $this->email_send_efb();
            }
        }
    }

    public static function email_send_efb() {
        $message = esc_html__( 'The Easy Form Builder had Important update and require to deactivate and activate the plugin manually. Notice: Please do this act immediately so forms of your site will be available again.', 'easy-form-builder' );

        $super_admins = get_super_admins();

        if ( empty( $super_admins ) ) {
            return;
        }

        $recipients = array();

        foreach ( $super_admins as $admin_login ) {
            $user = get_user_by( 'login', $admin_login );

            if ( $user && is_email( $user->user_email ) ) {
                $recipients[] = sanitize_email( $user->user_email );
            }
        }

        if ( empty( $recipients ) ) {
            return;
        }

        $server_name = apply_filters('emsfb_get_server_host', 'yourdomain.com');
        $from_email  = 'no-reply@' . $server_name;
        $from_name   = get_bloginfo( 'name' );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
        );

        $subject = sprintf(
            /* translators: %s: Site name */
            esc_html__( 'Important Warning from %s', 'easy-form-builder' ),
            get_bloginfo( 'name' )
        );

        wp_mail( $recipients, $subject, wp_kses_post( $message ), $headers );
    }

    public function handle_new_plugin_activation_efb($plugin, $network_wide = false) {

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

        $plugin_slug = dirname($plugin);

        if (in_array($plugin_slug, $cache_plugins_slug)) {
           do_action('emsfb_update_cache_plugins_list');
        }
    }

    public function update_cache_plugins_list() {

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

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();

        foreach ($plugins as $plugin_file => $plugin_data) {

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

        $val = !empty($plugin_list) ? json_encode($plugin_list) : 0;
        $old_val = get_option('emsfb_cache_plugins', 0);

        if ($val != $old_val) {
            update_option('emsfb_cache_plugins', $val);
        }

        return $plugin_list;
    }

    public function get_cached_server_host_efb() {

        $cached_host = get_option('emsfb_server_host_cache', false);

        if ($cached_host !== false) {
            return $cached_host;
        }

        $server_host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'yourdomain.com';

        update_option('emsfb_server_host_cache', $server_host, false);

        return $server_host;
    }

    public function clear_server_host_cache_efb()
    {
        delete_option('emsfb_server_host_cache');
    }

    public static function get_setting_Emsfb($mode = 'decoded')
    {

        static $staticCache = [];

        if ($mode === '_clear_cache') {
            $staticCache = [];
            return true;
        }

        if (isset($staticCache[$mode])) {
            return $staticCache[$mode];
        }

        $cacheKey = 'settings:' . $mode;
        $cached = wp_cache_get($cacheKey, 'emsfb');
        if ($cached !== false && !empty($cached)) {
            $staticCache[$mode] = $cached;
            return $cached;
        }

        $transient = get_transient('emsfb_settings_transient');

        if ($transient === false || empty($transient)) {
            global $wpdb;
            $table_name = $wpdb->prefix . "emsfb_setting";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix
            $raw = $wpdb->get_var( "SELECT setting FROM `{$table_name}` ORDER BY id DESC LIMIT 1" );

            if (empty($raw)) {
                if ($mode === 'pub') return [0, []];
                if ($mode === 'raw') return '';
                return new \stdClass();
            }

            update_option('emsfb_settings', $raw);
            set_transient('emsfb_settings_transient', $raw, 1800);
        } else {
            $raw = $transient;
        }

        $raw = self::clean_raw_json_efb($raw);

        $trimmedEnd = rtrim($raw);
        if (!empty($trimmedEnd) && !preg_match('/[}\]]$/', $trimmedEnd)) {
        }

        $decoded = json_decode($raw);
        if ($decoded === null) {

            $clean = $raw;
            $max_attempts = 5;
            for ($i = 0; $i < $max_attempts; $i++) {
                $clean = stripslashes($clean);
                $decoded = json_decode($clean);
                if ($decoded !== null) {
                    break;
                }
            }

            if ($decoded !== null) {
                $cleanJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                update_option('emsfb_settings', $cleanJson);
                set_transient('emsfb_settings_transient', $cleanJson, 1800);
                $raw = $cleanJson;

                global $wpdb;
                $table_name = $wpdb->prefix . "emsfb_setting";
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix
                $latest_id = $wpdb->get_var( "SELECT id FROM `{$table_name}` ORDER BY id DESC LIMIT 1" );
                if ($latest_id) {
                    $wpdb->update($table_name, ['setting' => $cleanJson], ['id' => $latest_id], ['%s'], ['%d']);
                }
            }
        }
        if ($decoded === null) {

            $decoded = self::get_default_settings_efb();
        }

        $result = null;

        switch ($mode) {
            case 'pub':

                $pro = absint(get_option('emsfb_pro'));
                $pro = $pro == 1 || $pro == 3 ? true : false;
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

                $result = $raw;
                break;

            case 'decoded':
            default:

                $package_type = get_option('emsfb_pro', 10);
                $stored_pt = isset($decoded->package_type) ? intval($decoded->package_type) : null;

                if (($package_type == 10 || $package_type == -1) && $stored_pt !== null && in_array($stored_pt, [0, 1, 2, 3], true)) {
                    $package_type = $stored_pt;
                    update_option('emsfb_pro', $package_type);
                }

                $decoded->package_type = $package_type;
                $result = $decoded;
                break;
        }

        $staticCache[$mode] = $result;
        wp_cache_set($cacheKey, $result, 'emsfb', 3600);

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

            throw $e;
        }
    }

    private static function get_addons_list_efb($settings)
    {
        $addons = [];

        $addonKeys = [
            'AdnSS' => 'SMS',
            'AdnATF' => 'Auto-Populate',
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

    public static function plugin_deactivation_cleanup_efb()
    {

        delete_option('emsfb_cache_plugins');
        delete_option('emsfb_server_host_cache');
        delete_option('emsfb_settings');

        delete_transient('emsfb_settings_transient');

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_emsfb_%' OR option_name LIKE '_transient_timeout_emsfb_%'"
        );

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('emsfb');
        }
    }

    public function init_elementor_compatibility() {

        if (!$this->is_elementor_admin_active()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ($page === 'Emsfb' ||
            $page === 'Emsfb_create' ||
            $page === 'Emsfb_addon' ||
            $page === 'Emsfb_sms_efb'
        ) {
            add_action('admin_enqueue_scripts', array($this, 'apply_elementor_admin_fixes'), 1);
        }
    }

    public function apply_elementor_admin_fixes() {

        add_action('admin_footer', array($this, 'elementor_admin_conflict_prevention'));
    }

    public function is_elementor_admin_active() {

        if (class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
            return true;
        }

        if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
            return true;
        }

        return false;
    }

    public function elementor_admin_conflict_prevention() {
        $current_page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';

            if (typeof window.efb_global_elementor_protection === 'undefined') {
                window.efb_global_elementor_protection = true;

                console.log('EFB Global: Initializing Elementor compatibility layer for <?php echo esc_js($current_page); ?>');

                if (typeof elementorFrontend !== 'undefined') {
                    try {
                        if (!elementorFrontend.tools) {
                            elementorFrontend.tools = {};
                            console.log('EFB Global: Initialized missing elementorFrontend.tools');
                        }
                    } catch (e) {
                        console.log('EFB Global: Prevented Elementor frontend error:', e.message);
                    }
                }

                $(document).ready(function() {
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

    public function init_elementor_compatibility_efb() {

        if (!$this->is_elementor_admin_active_efb()) {
            return;
        }

        if (isset($_GET['page']) && (
            sanitize_key( $_GET['page'] ) === 'Emsfb' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_create' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_addon' ||
            sanitize_key( $_GET['page'] ) === 'Emsfb_sms_efb'
        )) {
            add_action('admin_enqueue_scripts', array($this, 'apply_elementor_admin_fixes_efb'), 1);
        }
    }

    public function apply_elementor_admin_fixes_efb() {

        add_action('admin_footer', array($this, 'elementor_admin_conflict_prevention_efb'));
    }

    public function is_elementor_admin_active_efb() {

        if (class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
            return true;
        }

        if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
            return true;
        }

        return false;
    }

    public function elementor_admin_conflict_prevention_efb() {
        $current_page = isset($_GET['page']) ? sanitize_key( $_GET['page'] ) : '';
        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';

            if (typeof window.efb_global_elementor_protection === 'undefined') {
                window.efb_global_elementor_protection = true;

                console.log('EFB Global: Initializing Elementor compatibility layer for <?php echo esc_js($current_page); ?>');

                if (typeof elementorFrontend !== 'undefined') {
                    try {
                        if (!elementorFrontend.tools) {
                            elementorFrontend.tools = {};
                            console.log('EFB Global: Initialized missing elementorFrontend.tools');
                        }
                    } catch (e) {
                        console.log('EFB Global: Prevented Elementor frontend error:', e.message);
                    }
                }

                $(document).ready(function() {
                    $(window).on('error', function(e) {
                        if (e.originalEvent && e.originalEvent.message) {
                            var errorMessage = e.originalEvent.message.toLowerCase();
                            if (errorMessage.includes('dispatchevent') ||
                                errorMessage.includes('elementor') ||
                                errorMessage.includes('tools') ||
                                errorMessage.includes('cannot read properties of undefined')) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    });

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

    public function check_version_and_upgrade_efb() {
        $installed_version = get_option('emsfb_version', '0.0.0');
        $current_version = EMSFB_PLUGIN_VERSION;
	    if (!is_admin()) {
			return;
		}

        if (version_compare($installed_version, $current_version, '<')) {
            $this->run_upgrade_tasks_efb($installed_version, $current_version);
            update_option('emsfb_version', $current_version);
        }

    }

    private function run_upgrade_tasks_efb($old_version, $new_version) {
        global $wpdb;
        $table_setting = $wpdb->prefix . 'emsfb_setting';

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('emsfb');
        }
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_efb_%' OR option_name LIKE '_transient_timeout_efb_%'"
        );

        $wpdb->query("ALTER TABLE `{$table_setting}` MODIFY `setting` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL");

        $this->migrate_fix_double_escaped_settings_efb($wpdb);

        if (version_compare($old_version, '4', '<')) {
            $activeCode = get_option('emsfb_pro_activeCode', '');

            if (empty($activeCode)) {
                self::get_setting_Emsfb('_clear_cache');
                delete_transient('emsfb_settings_transient');

                $settings = self::get_setting_Emsfb('decoded');
                if (isset($settings->emailTemp)) {
                    unset($settings->emailTemp);
                }
                if (isset($settings->activeCode)) {
                    $activeCode = $settings->activeCode;
                }
            }

            if (!empty($activeCode) && strlen($activeCode) > 5) {
                update_option('emsfb_pro', 1);
            }
        }
    }

    private function migrate_fix_double_escaped_settings_efb($wpdb) {
        $table_name = $wpdb->prefix . "emsfb_setting";

        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        if (!$table_exists) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix
        $rows = $wpdb->get_results( "SELECT id, setting FROM `{$table_name}`" );
        if (empty($rows)) {
            return 0;
        }

        $repaired = 0;
        foreach ($rows as $row) {
            $raw = $row->setting;

            $cleaned = self::clean_raw_json_efb($raw);

            if (json_decode($cleaned) !== null) {

                if ($cleaned !== $raw) {
                    $cleanJson = json_encode(json_decode($cleaned), JSON_UNESCAPED_UNICODE);
                    $wpdb->update($table_name, ['setting' => $cleanJson], ['id' => $row->id], ['%s'], ['%d']);
                    $repaired++;
                }
                continue;
            }

            $clean = $cleaned;
            $max_attempts = 5;
            for ($i = 0; $i < $max_attempts; $i++) {
                $clean = stripslashes($clean);
                if (json_decode($clean) !== null) {
                    break;
                }
            }

            $decoded = json_decode($clean);
            if ($decoded === null) {
                continue;
            }

            $cleanJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            $wpdb->update(
                $table_name,
                ['setting' => $cleanJson],
                ['id' => $row->id],
                ['%s'],
                ['%d']
            );
            $repaired++;
        }

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

    private static function clean_raw_json_efb($raw) {
        if (empty($raw) || !is_string($raw)) {
            return '';
        }

        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }

        $raw = str_replace("\0", '', $raw);

        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{2060}]/u', '', $raw);

        $raw = trim($raw);

        if (function_exists('mb_convert_encoding')) {

            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        if (strpos($raw, '&quot;') !== false || strpos($raw, '&#34;') !== false) {
            $candidate = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($candidate) !== null) {
                $raw = $candidate;
            }
        }

        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw);

        return $raw;
    }

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
        $defaults->emailBtnBgColor   = '#202a8d';
        $defaults->emailBtnTextColor = '#ffffff';
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
        $defaults->trackCodeStyle    = 'date_en_mix';
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

    public static function get_locale_script_chars_efb() {
        static $cache = null;
        if ($cache !== null) return $cache;

        $lang_full  = strtok(get_locale(), '_');
        $lang_short = substr($lang_full, 0, 2);

        $map = [
            // ── Arabic script ──
            'ar'  => [[[0x0627,0x063A],[0x0641,0x064A]], 0x0660],
            'fa'  => [[[0x0622,0x0622],[0x0627,0x0628],[0x067E,0x067E],[0x062A,0x062C],[0x0686,0x0686],[0x062D,0x0632],[0x0698,0x0698],[0x0633,0x063A],[0x0641,0x0642],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x0644,0x0648],[0x06CC,0x06CC]], 0x06F0],
            'ur'  => [[[0x0627,0x063A],[0x0641,0x064A],[0x067E,0x067E],[0x0686,0x0686],[0x0698,0x0698],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x06CC,0x06CC]], 0x0660],
            'ps'  => [[[0x0627,0x063A],[0x0641,0x064A],[0x067E,0x067E],[0x0686,0x0686],[0x0693,0x0693],[0x0698,0x0698],[0x069A,0x069A],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x06BC,0x06BC],[0x06CC,0x06CC],[0x06D0,0x06D0]], 0x06F0],
            'sd'  => [[[0x0627,0x063A],[0x0641,0x064A],[0x067E,0x067E],[0x0686,0x0686],[0x0698,0x0698],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x06CC,0x06CC]], 0x0660],
            'ug'  => [[[0x0627,0x0628],[0x067E,0x067E],[0x062A,0x062C],[0x0686,0x0686],[0x062E,0x0632],[0x0698,0x0698],[0x0633,0x063A],[0x0641,0x0642],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x0644,0x0648],[0x06CB,0x06CC],[0x06D0,0x06D0],[0x06D5,0x06D5]], 0x0660],
            'ckb' => [[[0x0627,0x063A],[0x0641,0x064A],[0x067E,0x067E],[0x0686,0x0686],[0x0698,0x0698],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x06CC,0x06CC]], 0x0660],
            'azb' => [[[0x0627,0x063A],[0x0641,0x064A],[0x067E,0x067E],[0x0686,0x0686],[0x0698,0x0698],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x06CC,0x06CC]], 0x06F0],
            'haz' => [[[0x0622,0x0622],[0x0627,0x0628],[0x067E,0x067E],[0x062A,0x062C],[0x0686,0x0686],[0x062D,0x0632],[0x0698,0x0698],[0x0633,0x063A],[0x0641,0x0642],[0x06A9,0x06A9],[0x06AF,0x06AF],[0x0644,0x0648],[0x06CC,0x06CC]], 0x06F0],

            // ── Devanagari script ──
            'hi'  => [[[0x0915,0x0939]], 0x0966],
            'mr'  => [[[0x0915,0x0939]], 0x0966],
            'ne'  => [[[0x0915,0x0939]], 0x0966],
            'sa'  => [[[0x0915,0x0939]], 0x0966],
            'bho' => [[[0x0915,0x0939]], 0x0966],
            'mai' => [[[0x0915,0x0939]], 0x0966],
            'doi' => [[[0x0915,0x0939]], 0x0966],

            // ── Bengali script ──
            'bn'  => [[[0x0995,0x09B9]], 0x09E6],
            'as'  => [[[0x0995,0x09B9]], 0x09E6],

            // ── Gurmukhi ──
            'pa'  => [[[0x0A15,0x0A39]], 0x0A66],

            // ── Gujarati ──
            'gu'  => [[[0x0A95,0x0AB9]], 0x0AE6],

            // ── Odia (Oriya) ──
            'or'  => [[[0x0B15,0x0B39]], 0x0B66],
            'ory' => [[[0x0B15,0x0B39]], 0x0B66],

            // ── Tamil ──
            'ta'  => [[[0x0B95,0x0BB9]], 0x0BE6],

            // ── Telugu ──
            'te'  => [[[0x0C15,0x0C39]], 0x0C66],

            // ── Kannada ──
            'kn'  => [[[0x0C95,0x0CB9]], 0x0CE6],

            // ── Malayalam ──
            'ml'  => [[[0x0D15,0x0D39]], 0x0D66],

            // ── Sinhala ──
            'si'  => [[[0x0D9A,0x0DC6]], 0x0DE6],

            // ── Thai ──
            'th'  => [[[0x0E01,0x0E2E]], 0x0E50],

            // ── Lao ──
            'lo'  => [[[0x0E81,0x0EAE]], 0x0ED0],

            // ── Myanmar (Burmese) ──
            'my'  => [[[0x1000,0x1021]], 0x1040],

            // ── Khmer ──
            'km'  => [[[0x1780,0x17A2]], 0x17E0],

            // ── Tibetan ──
            'bo'  => [[[0x0F40,0x0F69]], 0x0F20],

            // ── Georgian ──
            'ka'  => [[[0x10D0,0x10F0]], null],

            // ── Armenian ──
            'hy'  => [[[0x0531,0x0556]], null],

            // ── Greek ──
            'el'  => [[[0x0391,0x03A9],[0x03B1,0x03C9]], null],

            // ── Cyrillic ──
            'ru'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'uk'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'bg'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'sr'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'be'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'mk'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'kk'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'ky'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'mn'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'tg'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'tt'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'ba'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'ce'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'cv'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],
            'os'  => [[[0x0410,0x042F],[0x0430,0x044F]], null],

            // ── Hebrew ──
            'he'  => [[[0x05D0,0x05EA]], null],
            'yi'  => [[[0x05D0,0x05EA]], null],

            // ── CJK ──
            'ja'  => [[[0x30A2,0x30F3]], null],
            'ko'  => [[[0x3131,0x314E]], null],
            'zh'  => [[[0x4E00,0x4E4F]], null],

            // ── Ethiopic ──
            'am'  => [[[0x1200,0x1248]], null],
            'ti'  => [[[0x1200,0x1248]], null],

            // ── Thaana (Dhivehi) ──
            'dv'  => [[[0x0780,0x07A5]], null],

            // ── Cherokee ──
            'chr' => [[[0x13A0,0x13F4]], null],
        ];

        $lang = isset($map[$lang_full]) ? $lang_full : (isset($map[$lang_short]) ? $lang_short : null);
        if ($lang === null) {
            $cache = false;
            return false;
        }

        $info = $map[$lang];
        $alpha = [];
        foreach ($info[0] as $range) {
            for ($i = $range[0]; $i <= $range[1]; $i++) {
                $ch = mb_chr($i, 'UTF-8');
                if ($ch !== false) $alpha[] = $ch;
            }
        }

        $digits = null;
        if ($info[1] !== null) {
            $digits = [];
            for ($i = 0; $i <= 9; $i++) {
                $digits[$i] = mb_chr($info[1] + $i, 'UTF-8');
            }
        }

        $cache = ['alpha' => $alpha, 'digits' => $digits];
        return $cache;
    }

    public function plugin_update_completed_efb($upgrader_object, $options) {

        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        $our_plugin = plugin_basename(EMSFB_PLUGIN_FILE);

        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === $our_plugin) {

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
