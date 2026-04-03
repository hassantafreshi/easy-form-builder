<?php
namespace Emsfb;
use WP_REST_Response;

require_once('functions.php');
class _Public {
	public $value;
	public $id;
	public $ip;
	public $name;
	public $setting;
	protected $db;
	public $efbFunction;
	public $lanText;
	public $text_;
	public $pro_efb;
	public $pub_stting;
	public $location;
	public $url;
	public $efb_uid  ;
	public $value_forms =[];
	private $form_cache = [];

	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		$this->id =-1;
		$this->pro_efb =false;
		add_action('rest_api_init',  function(){
			$this->efb_uid  = get_current_user_id();
			$this->efbFunction = get_efbFunction();
			$settings = get_setting_Emsfb('raw');
			register_rest_route('Emsfb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
				'method'=> 'POST',
				'callback'=>  [$this,'test_fun'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);
			register_rest_route('Emsfb/v1','forms/message/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'get_form_public_efb'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);

			do_action( 'efb_register_payment_rest_routes', $this );

			register_rest_route('Emsfb/v1','forms/response/get', [
				'methods' => 'POST',
				'callback'=>  [$this,'get_track_public_api'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);
			register_rest_route('Emsfb/v1','forms/response/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'set_rMessage_id_Emsfb_api'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);
			register_rest_route('Emsfb/v1','autofill/get', [
				'methods' => 'POST',
				'callback'=>  [$this,'get_autofilled_list_efb'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);
			register_rest_route('Emsfb/v1','forms/file/upload', [
				'methods' => 'POST',
				'callback'=>  [$this,'file_upload_api'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);

			register_rest_route('Emsfb/v1','forms/recovery/efb_set_password', [
				'methods' => 'POST',
				'callback'=>  [$this,'set_password_efb_api'],
				'permission_callback' => [$this, 'check_nonce_permission_efb']
			]);

		});

		add_shortcode( 'Easy_Form_Builder_confirmation_code_finder',  array( $this, 'EFB_Form_Builder' ) );

		add_shortcode( 'EMS_Form_Builder',  array( $this, 'EFB_Form_Builder' ) );
		add_shortcode( 'ems_form_builder',  array( $this, 'EFB_Form_Builder' ) );
		add_action('init',  array($this, 'hide_toolmenu'));
		add_action('wp_ajax_form_preview_efb', [$this, 'form_preview_efb']);
		add_action('delete_preview_page_efb', [$this,'delete_preview_page_efb'], 10, 1);

		add_action('wp_ajax_efb_process_background', [$this, 'process_background_task']);
		add_action('wp_ajax_nopriv_efb_process_background', [$this, 'process_background_task']);

		add_action('efb_process_background_cron', [$this, 'process_background_cron'], 10, 1);

		if (!is_admin()) {
			add_action('wp_enqueue_scripts', [$this, 'init_elementor_compatibility'], 1);
		}
	}

public function check_nonce_permission_efb($request) {

	$allowed_origins = apply_filters('efb_allowed_cors_origins', array(
		home_url(),
		site_url()
	));

	$origin = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

	if ($origin && in_array($origin, $allowed_origins)) {
		header('Access-Control-Allow-Origin: ' . $origin);
	} else {

		$parsed_origin = wp_parse_url($origin);
		$parsed_home = wp_parse_url(home_url());

		if (isset($parsed_origin['host']) && isset($parsed_home['host']) &&
		    $parsed_origin['host'] === $parsed_home['host']) {
			header('Access-Control-Allow-Origin: ' . $origin);
		}
	}

	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, Authorization, sid, form_id');
	header('Access-Control-Max-Age: 86400');

	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		status_header(200);
		exit();
	}

	if (!isset($_SERVER['HTTP_X_WP_NONCE'])) {
		return new \WP_Error('rest_forbidden', __('X-WP-Nonce header is missing', 'easy-form-builder'), array('status' => 403));
	}

	$verify = wp_verify_nonce( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ), 'wp_rest');

	if (!$verify) {

		$sid = sanitize_text_field( wp_unslash($_SERVER['HTTP_SID'] ?? ''));
		$fid = sanitize_text_field( wp_unslash($_SERVER['HTTP_FORM_ID'] ?? ''));

		if (!empty($sid) && $fid !== '') {
			if (!$this->efbFunction) {
				$this->efbFunction = get_efbFunction();
			}

			$sid_valid = $this->efbFunction->efb_code_validate_select($sid, $fid);
			if ($sid_valid) {
					return true;
			}
		} else {
			return new \WP_Error('rest_forbidden', __('Invalid or expired nonce', 'easy-form-builder'), array('status' => 403));
		}

		return new \WP_Error('rest_forbidden', __('Invalid or expired nonce', 'easy-form-builder'), array('status' => 403));
	}

	return true;
}

	public function init_elementor_compatibility() {

		if (is_admin()) {
			return;
		}

		$elementor_active = $this->is_elementor_active();

		if ($elementor_active) {

			add_action('wp_head', [$this, 'simple_elementor_fix'], 1);
			add_action('wp_footer', [$this, 'simple_elementor_fix_footer'], 1);

		}
	}

	private function safe_wp_script_is($handle, $list = 'enqueued') {

		if (!did_action('wp_enqueue_scripts') && !did_action('admin_enqueue_scripts') && !did_action('login_enqueue_scripts')) {
			return false;
		}

		if (function_exists('wp_script_is')) {
			return wp_script_is($handle, $list);
		}

		return false;
	}

	public function is_elementor_active() {

		if (is_admin()) {
			return false;
		}

		if (class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
			return true;
		}

		if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
			return true;
		}

		global $post;
		if (is_object($post) && isset($post->post_content)) {
			if (strpos($post->post_content, 'elementor') !== false ||
			    strpos($post->post_content, 'data-elementor-type') !== false) {
				return true;
			}
		}

		if ($this->safe_wp_script_is('elementor-frontend', 'enqueued') ||
		    $this->safe_wp_script_is('elementor-frontend', 'registered')) {
			return true;
		}

		return false;
	}

	public function enqueue_jquery(){

		if (is_admin()) {
			return;
		}

		$elementor_active = false;

		if (class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION')) {
			$elementor_active = true;
		}

		if (function_exists('is_plugin_active') && is_plugin_active('elementor/elementor.php')) {
			$elementor_active = true;
		}

		if ($this->safe_wp_script_is('elementor-frontend', 'enqueued') ||
		    $this->safe_wp_script_is('elementor-frontend', 'registered') ||
		    $this->safe_wp_script_is('elementor-frontend', 'to_do')) {
		$elementor_active = true;
	}

	if (isset($_SERVER['REQUEST_URI']) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'elementor') !== false) {
		$elementor_active = true;
	}
		global $post;
		if (is_object($post) && method_exists($post, 'get_content')) {
			if (strpos($post->post_content, 'elementor') !== false) {
				$elementor_active = true;
			}
		}

		global $post;
		$has_elementor_content = false;
		if (is_object($post) && isset($post->post_content)) {
			$has_elementor_content = strpos($post->post_content, 'elementor') !== false;
		}

		if ($elementor_active || $has_elementor_content) {

			return;
		}

		if (!isset(wp_scripts()->registered['jquery']) || version_compare(wp_scripts()->registered['jquery']->ver , '3.6.0' , '<')) {
			$wp_version = get_bloginfo('version');
			if (version_compare($wp_version, '6.0', '>')) {
				wp_enqueue_script('jquery', includes_url('/js/jquery/jquery.js') , false, '3.7.1', true);
			}else {
				wp_enqueue_script('jquery', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery.min-efb.js', false, '3.6.2', true);
			}
		}
	}

	public function hide_toolmenu(){

		if(is_user_logged_in()){
			$user = wp_get_current_user();
			if ( in_array( 'subscriber', (array) $user->roles ) ) {

					show_admin_bar( false );
			}
		}
	}

	public function simple_elementor_fix() {

		if (!is_admin() && !current_user_can('edit_posts') && $this->is_elementor_active()) {
			?>
			<script>

			window.elementorFrontendConfig = window.elementorFrontendConfig || {};
			window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || {};
			window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || {};
			</script>
			<?php
		}
	}

	public function simple_elementor_fix_footer() {

		if (!is_admin() && !current_user_can('edit_posts') && $this->is_elementor_active()) {
			?>
			<script>

			(function() {

				var safeConfig = {
					tools: {
						hash: {},
						ajax: {},
						request: {},
						utils: {}
					},
					settings: {
						page: {},
						general: {},
						editorPreferences: {}
					}
				};

				window.elementorFrontendConfig = window.elementorFrontendConfig || safeConfig;
				window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || safeConfig.tools;
				window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || safeConfig.settings;

				var attempts = 0;
				var checkElementor = setInterval(function() {
					attempts++;

					if (window.elementorFrontend && typeof window.elementorFrontend === 'object') {

						Object.defineProperty(window.elementorFrontend, 'config', {
							get: function() {
								return window.elementorFrontendConfig || safeConfig;
							},
							set: function(value) {
								if (value && typeof value === 'object') {
									window.elementorFrontendConfig = value;
									window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || safeConfig.tools;
									window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || safeConfig.settings;
								}
							},
							configurable: true,
							enumerable: true
						});

						if (window.elementorFrontend.initOnReadyComponents) {
							var originalInitOnReadyComponents = window.elementorFrontend.initOnReadyComponents;
							window.elementorFrontend.initOnReadyComponents = function() {
								try {

									this.config = window.elementorFrontendConfig || safeConfig;

									this.config.tools = safeConfig.tools;
									this.config.settings = safeConfig.settings;

									try {

										var result = originalInitOnReadyComponents.call(this);

										return result;
									} catch (innerError) {
										console.warn('ðŸ›¡ï¸ EFB: Inner method error, using safe fallback:', innerError);

										return {};
									}
								} catch (e) {
									console.warn('ðŸ›¡ï¸ EFB: Caught initOnReadyComponents error:', e);

									return {};
								}
							};
						}

						if (window.elementorFrontend.init) {
							var originalInit = window.elementorFrontend.init;
							window.elementorFrontend.init = function() {
								try {
									this.config = this.config || safeConfig;
									this.config.tools = this.config.tools || safeConfig.tools;
									this.config.settings = this.config.settings || safeConfig.settings;

									return originalInit.apply(this, arguments);
								} catch (e) {
									console.warn('ðŸ›¡ï¸ EFB: Caught init error:', e);
									return {};
								}
							};
						}

						clearInterval(checkElementor);
					}

					if (attempts > 500) {
						clearInterval(checkElementor);
					}
				}, 10);
			})();
			</script>
			<?php
		}
	}
	public function EFB_Form_Builder($id){

			$page_builder="";
			$action_post = isset($_GET['action']) ? sanitize_key( wp_unslash( $_GET['action'] ) ) :'';

			$is_beaver_active = false;
			if (class_exists('\FLBuilderModel') && method_exists('\FLBuilderModel', 'is_builder_active')) {
				try {
					$is_beaver_active = \FLBuilderModel::is_builder_active();
				} catch (\Exception $e) {
					$is_beaver_active = false;
				} catch (\Error $e) {
					$is_beaver_active = false;
				}
			}

			$is_divi_enabled = false;
			if (defined('ET_FB_ENABLED')) {
				try {
					$is_divi_enabled = ET_FB_ENABLED;
				} catch (\Exception $e) {
					$is_divi_enabled = false;
				} catch (\Error $e) {
					$is_divi_enabled = false;
				}
			}

			$is_oxygen_enabled = false;
			if (defined('SHOW_CT_BUILDER')) {
				try {
					$is_oxygen_enabled = SHOW_CT_BUILDER;
				} catch (\Exception $e) {
					$is_oxygen_enabled = false;
				} catch (\Error $e) {
					$is_oxygen_enabled = false;
				}
			}

			$is_editor_mode = (
				is_admin() ||
				isset($_GET['vc_editable']) ||
				isset($_GET['vcv-ajax']) ||
				isset($_GET['vcv-action']) ||
				$action_post == 'elementor' ||
				isset($_GET['elementor-preview']) ||

				isset($_GET['et_fb']) ||
				isset($_GET['et_bfb']) ||
				$is_divi_enabled ||

				isset($_GET['fl_builder']) ||
				$is_beaver_active ||

				isset($_GET['brizy-edit']) ||
				isset($_GET['brizy-edit-iframe']) ||

				isset($_GET['ct_builder']) ||
				isset($_GET['oxygen_iframe']) ||
				$is_oxygen_enabled
			);

			if($is_editor_mode){

				if(isset($_GET['vc_editable']) || isset($_GET['vcv-ajax']) || isset($_GET['vcv-action'])) {
					$page_builder = 'Visual Composer';
				} else if ($action_post == 'elementor' || isset($_GET['elementor-preview'])) {
					$page_builder = 'Elementor';
				} else if (isset($_GET['et_fb']) || isset($_GET['et_bfb']) || $is_divi_enabled) {
					$page_builder = 'Divi Builder';
				} else if (isset($_GET['fl_builder']) || $is_beaver_active) {
					$page_builder = 'Beaver Builder';
				} else if (isset($_GET['brizy-edit']) || isset($_GET['brizy-edit-iframe'])) {
					$page_builder = 'Brizy';
				} else if (isset($_GET['ct_builder']) || isset($_GET['oxygen_iframe']) || $is_oxygen_enabled) {
					$page_builder = 'Oxygen';
				} else {
					$page_builder = 'Editor';
				}

				$form_id = is_array($id) ? end($id) : $id;
				$form_info = '';
				if (!empty($form_id)) {
					$form_info = '<div style="margin-top: 15px; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 6px; display: inline-block; font-size: 13px;">
						<span style="opacity: 0.8;">'.esc_html__('Form ID:', 'easy-form-builder').'</span>
						<strong>' . esc_html($form_id) . '</strong>
					</div>';
				}

				$content = '
				<div style="
					padding: 40px 30px;
					background: linear-gradient(135deg, #202a8d 0%, #ff4b93 100%);
					border-radius: 12px;
					text-align: center;
					color: #fff;
					font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;
					margin: 10px 0;
				">
					<div style="margin-bottom: 15px;">
						<img src="'. esc_url(EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg') .'" alt="Easy Form Builder" style="width: 60px; height: 60px; border-radius: 10px;" onerror="this.style.display=\'none\'">
					</div>
					<div style="font-size: 20px; font-weight: 700; margin-bottom: 10px;">Easy Form Builder</div>
					<div style="font-size: 14px; opacity: 0.9;">'.esc_html__('The form will be displayed on the frontend.', 'easy-form-builder').'</div>
					'.$form_info.'
					<div style="margin-top: 15px; font-size: 12px; opacity: 0.7;">
						<span style="background: rgba(255,255,255,0.15); padding: 4px 10px; border-radius: 4px;">ðŸ“ ' . esc_html($page_builder) . '</span>
					</div>
				</div>
				';

				return $content;
			}
			$this->enqueue_jquery();
			$state_form = 'not';
			$admin_form = false;
			$admin_sc = null;
			$admin_verified = false;
			$is_track = null;
			$state="form";
			$rgister_captcha_url = false;
			$this->efbFunction = get_efbFunction();
			if(isset($_GET['track'])){
				$state_form =  sanitize_text_field(wp_unslash($_GET['track']) );
				$state="track";

				if(isset($_GET['user'])  && sanitize_text_field( wp_unslash( $_GET['user'] ) ) == "admin" ) $admin_form = true;
				if(isset($_GET['sc'])) $admin_sc = sanitize_text_field(wp_unslash($_GET['sc']));
			}elseif (isset($_GET['state'])){
				$admin_sc = isset($_GET['sc']) ? sanitize_text_field(wp_unslash($_GET['sc'])) : null;
				$username =isset($_GET['username']) ?  sanitize_text_field(wp_unslash($_GET['username'])) : 'null';
				$state = sanitize_text_field(wp_unslash($_GET['state']));
				$fid = sanitize_text_field(wp_unslash($_GET['fid']));

				$val = $this->fun_present_others_action_efb( $state, $username, $admin_sc, $fid);
				return $val;
			}

			// Verify admin sc (secure code from email link)
			if ($admin_sc !== null && $state === 'track' && strlen($state_form) > 5) {
				$sc_setting = get_setting_Emsfb('decoded');
				if (isset($sc_setting->email_key) && strlen($sc_setting->email_key) > 3) {
					$expected_sc = md5($state_form . $sc_setting->email_key);
					if (hash_equals($expected_sc, $admin_sc)) {
						$admin_verified = true;
						$admin_form = true;
					}
				}
			}

			// Determine admin access
			$adminSN_enabled = false;
			if (!isset($sc_setting)) $sc_setting = get_setting_Emsfb('decoded');
			if (isset($sc_setting->adminSN)) $adminSN_enabled = (bool) $sc_setting->adminSN;


			// If user=admin without valid sc → must be logged in as admin
			$is_legacy_admin_link = ($admin_form && ($admin_sc === null || !$admin_verified));
			if ($admin_form && !$admin_verified) {
				if (is_user_logged_in() && current_user_can('administrator')) {
					$admin_verified = true;
				} else if (!is_user_logged_in()) {
				$overrides = $this->efb_build_inline_style_overrides();
				$pl_warn = get_setting_Emsfb('pub');
				$ps_warn = $pl_warn[1] ?? [];

				$warn_text_color  = !empty($ps_warn['respText'])       ? $ps_warn['respText']       : '#1a1a2e';
				$warn_bg_color    = !empty($ps_warn['respBgCard'])     ? $ps_warn['respBgCard']     : '#ffffff';
				$warn_primary     = !empty($ps_warn['respPrimary'])    ? $ps_warn['respPrimary']    : '#3644d2';
				$warn_muted       = !empty($ps_warn['respTextMuted'])  ? $ps_warn['respTextMuted']  : '#657096';
				$warn_font_family = !empty($ps_warn['respFontFamily']) ? $ps_warn['respFontFamily'] : 'inherit';
				$warn_font_size   = !empty($ps_warn['respFontSize'])  ? $ps_warn['respFontSize']   : '0.9rem';

				$legacy_notice = '';
				if ($is_legacy_admin_link) {
					$legacy_notice = "
					<div style='margin-top:16px; padding:10px 18px; border-radius:8px;
					            background-color: rgba(54,68,210,0.07);
					            display:inline-flex; align-items:center; gap:8px;'>
						<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='" . esc_attr($warn_muted) . "' viewBox='0 0 16 16' style='flex-shrink:0;'>
							<path d='M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.399l-.502 0 .07-.332C7.005 6.584 7.912 6.196 8.454 6h.37l-.82 4.588zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z'/>
						</svg>
						<span style='color:" . esc_attr($warn_muted) . "; font-family:" . esc_attr($warn_font_family) . ";
						             font-size: calc(" . esc_attr($warn_font_size) . " * 0.93);'>"
						. esc_html__('This link uses an older format. For improved security, new email notifications include updated links.', 'easy-form-builder') .
						"</span>
					</div>";
				}

				return $overrides['font_link'] . $overrides['inline_style'] . "
				<div id='body_efb' class='efb card-public efb'
				     style='display:flex; flex-direction:column; align-items:center; justify-content:center;
				            color:" . esc_attr($warn_text_color) . "; background-color:" . esc_attr($warn_bg_color) . ";
				            font-family:" . esc_attr($warn_font_family) . "; font-size:" . esc_attr($warn_font_size) . ";
				            padding: 40px 20px; border-radius: 12px;
				            box-shadow: 0 2px 16px rgba(0,0,0,0.07); text-align:center;'>
					<div style='margin-bottom:18px; text-align:center;'>
						<svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='" . esc_attr($warn_primary) . "' viewBox='0 0 16 16' style='display:inline-block;'>
							<path d='M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zM8 4a.905.905 0 0 1 .9.995l-.35 3.507a.553.553 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z'/>
						</svg>
					</div>
					<h3 style='color:" . esc_attr($warn_text_color) . "; font-family:" . esc_attr($warn_font_family) . ";
					           font-size: calc(" . esc_attr($warn_font_size) . " * 1.35); font-weight:600;
					           margin:0 0 10px 0; text-align:center;'>"
					    . esc_html__('It seems that you are the admin of this form. Please log in and try again.', 'easy-form-builder') .
					"</h3>" . $legacy_notice . "
				</div>";
				} else {
					$admin_form = false;
				}
			}

			// If adminSN is enabled and admin verified via sc but not logged in → require login
			if ($adminSN_enabled && $admin_verified && !is_user_logged_in()) {
				$admin_verified = false;
				$admin_form = false;

				$overrides = $this->efb_build_inline_style_overrides();
				$pl_warn = get_setting_Emsfb('pub');
				$ps_warn = $pl_warn[1] ?? [];
				$warn_text_color  = !empty($ps_warn['respText'])       ? $ps_warn['respText']       : '#1a1a2e';
				$warn_bg_color    = !empty($ps_warn['respBgCard'])     ? $ps_warn['respBgCard']     : '#ffffff';
				$warn_primary     = !empty($ps_warn['respPrimary'])    ? $ps_warn['respPrimary']    : '#3644d2';
				$warn_muted       = !empty($ps_warn['respTextMuted'])  ? $ps_warn['respTextMuted']  : '#657096';
				$warn_font_family = !empty($ps_warn['respFontFamily']) ? $ps_warn['respFontFamily'] : 'inherit';
				$warn_font_size   = !empty($ps_warn['respFontSize'])  ? $ps_warn['respFontSize']   : '0.9rem';

				return $overrides['font_link'] . $overrides['inline_style'] . "
				<div id='body_efb' class='efb card-public efb'
				     style='display:flex; flex-direction:column; align-items:center; justify-content:center;
				            color:" . esc_attr($warn_text_color) . "; background-color:" . esc_attr($warn_bg_color) . ";
				            font-family:" . esc_attr($warn_font_family) . "; font-size:" . esc_attr($warn_font_size) . ";
				            padding: 40px 20px; border-radius: 12px;
				            box-shadow: 0 2px 16px rgba(0,0,0,0.07); text-align:center;'>
					<div style='margin-bottom:18px; text-align:center;'>
						<svg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='" . esc_attr($warn_primary) . "' viewBox='0 0 16 16' style='display:inline-block;'>
							<path d='M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zM8 4a.905.905 0 0 1 .9.995l-.35 3.507a.553.553 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z'/>
						</svg>
					</div>
					<h3 style='color:" . esc_attr($warn_text_color) . "; font-family:" . esc_attr($warn_font_family) . ";
					           font-size: calc(" . esc_attr($warn_font_size) . " * 1.35); font-weight:600;
					           margin:0 0 10px 0; text-align:center;'>"
					    . esc_html__('It seems that you are the admin of this form. Please log in and try again', 'easy-form-builder') .
					"</h3>
					<p style='color:" . esc_attr($warn_muted) . "; font-family:" . esc_attr($warn_font_family) . ";
					          font-size:" . esc_attr($warn_font_size) . "; margin:0; text-align:center;'></p>
				</div>";
			}

			if(empty($this->db)){
				global $wpdb;
				$this->db = $wpdb;
			}

			$this->id = end($id);
			$this->id = intval($this->id);
			$sid = $this->efbFunction->efb_code_validate_create( $this->id , 0, 'visit' , 0);
			$value_form_data = $this->get_form_data_efb($this->id, array('form_structer', 'form_type'));
			if($value_form_data != null){
				$typeOfForm = $value_form_data->form_type;
				if($state_form!='not' && strlen($state_form)>7
				&& ($typeOfForm!="register" || $typeOfForm!="login")){
					$this->id =-1;

					$is_track= $this->EMS_Form_Builder_track();

				}
			}else if(isset($this->id)){
				$is_track= $this->EMS_Form_Builder_track();

			}else{
				return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'> <div class='efb text-center my-5'><div class='efb text-danger efb text-center display-1 my-2'>
					<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-exclamation-triangle-fill' viewBox='0 0 16 16'>
					<path d='M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2'/>
					</svg>
				</div>
				<h3 style='color:#202a8d;text-align: center;'>".esc_html__('Form does not exist !!','easy-form-builder')."</h3>
				<h4 style='color:#ff4b93;text-align: center;'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</h4></div></div>";
			}
			$this->text_ = ["somethingWentWrongPleaseRefresh","atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","mcplen","mmxplen","mxcplen","clcdetls","vmgs","required","mmplen","offlineSend","amount","allformat","videoDownloadLink","downloadViedo","removeTheFile","pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist",
			"settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot",
			"enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document",
			"error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media",
			"zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync",
			"enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue",
			"name","latitude","longitude","previous","next","invalidEmail","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork",
			"aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank","easyFormBuilder","createdBy","rnfn","fil",'stf','total','fetf','search','jqinl','eln' ,'servpss','slocation',
			'snotfound','sfmcfop','notFound','file','copied','nonceExpired','fileUploadNetworkError','id','updated','methodPayment','ttlprc','fillrequiredfields'];

			$this->public_scripts_and_css_head('');

			$state="";

			$pro = $this->efbFunction->is_efb_pro(1);
			$this->pro_efb = $pro ;
			$lanText= $this->efbFunction->text_efb($this->text_);

			$ar_core = array( 'sid'=>$sid);

			$icons=[[
				'bi-clipboard-check',
				"bi-shield-lock-fill",
				'bi-exclamation-triangle-fill',
				"bi-exclamation-diamond-fill",
				"bi-check2-square",
				"bi-hourglass-split",
				"bi-chat-square-text",
				"bi-download",
				"bi-star-fill",
				"bi-hand-thumbs-up",
				"bi-envelope",
				"bi-arrow-right",
				"bi-arrow-left",
				"bi-upload",
				"bi-x-lg",
				"bi-file-earmark-richtext",
				"bi-check-square",
				"bi-square",
				"bi-chevron-down",
				"bi-chevron-up",
				"bi-check-lg",
				"bi-crosshair",
				"bi-search",
				"bi-trash",
				'bi-shield-check',
				'bi-chat-square-text',
				'bi-paperclip',
				'bi-type-bold',
				'bi-type-italic',
				'bi-type-underline',
				'bi-eraser',
				'bi-person',
				'bi-reply',
				'bi-hash',
				'bi-calendar3',
				'bi-globe2',
				'bi-credit-card',
				'bi-calculator',
				'bi-palette',
				'bi-pen',
				'bi-star',
				'bi-reply',
				'bi-x'
			]];
			$bootstrap_icons ='';
			 $iconst_html_preload ='<div style="display:none;">';
			if($is_track==null){
				$value = $value_form_data->form_structer;
				$pattern = '/bi-[a-zA-Z0-9-]+/';

				preg_match_all($pattern, $value, $icons_ );

				$iconsd = array_merge($icons_[0] , $icons[0]);

				$icons_ = array_unique($iconsd);
				$value = preg_replace('/\\\"email\\\":\\\"(.*?)\\\"/', '\"email\":\"\"', $value);

					foreach($iconsd as $icon){
						$iconst_html_preload .= "<i class='bi $icon'></i>";
					}

				$bootstrap_icons = $this->bootstrap_icon_efb($icons_);
			}else{

				$bootstrap_icons = $this->bootstrap_icon_efb( $icons[0]);
				foreach($icons[0] as $icon){
						$iconst_html_preload .= "<i class='bi $icon'></i>";
				}

				$is_track['content'] = $bootstrap_icons . $is_track['content'];
			}
			$iconst_html_preload .='</div>';

			$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';

			$lang = get_locale();
			$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;

			$typeOfForm =$value_form_data->form_type ?? 'track';
			$value = $value_form_data->form_structer ?? 'track';
			$state="form";
			$multi_exist = strpos($value , '"type\":\"multiselect\"');
			if($multi_exist==true || strpos($value , '"type":"multiselect"') || strpos($value , '"type\":\"payMultiselect\"') || strpos($value , '"type":"payMultiselect"')){
				wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js',false,EMSFB_PLUGIN_VERSION, true );
				wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select-efb.css', true,EMSFB_PLUGIN_VERSION );
				wp_enqueue_style('Emsfb-bootstrap-select-css');
			}
			$rp= get_setting_Emsfb('pub');
			$efb_m = "<p class='efb fs-7 text-center my-1'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</p> ";
			if(gettype($rp)=="integer" && $rp==0){
				$stng=$lanText['settingsNfound'];
				$state="form";
				return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('Easy Form Builder couldn\'t locate the form settings. Please check your settings or contact support for assistance.','easy-form-builder')."</h3>".$efb_m;
			}

			$stng= $rp[0];

			$this->comper_version_efb($rp[1]['version']);

			$paymentType="null";
			$paymentKey="null";
			$refid = isset($_GET['Authority'])  ? sanitize_text_field(wp_unslash($_GET['Authority'])) : 'not';
			$Status_pay = isset($_GET['Status'])  ? sanitize_text_field(wp_unslash($_GET['Status'])) : 'NOK';
			$img['plugin_url'] = EMSFB_PLUGIN_URL;

			if($pro==1 || $pro==true){
					$efb_m= "<!--efb-->" ;

					$sms_exists = get_option('emsfb_addon_AdnSS',false);
					$sms_files_exists = is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended");
					if($sms_exists !== false && $sms_files_exists){
						require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php");
						$smssendefb = new smssendefb() ;
					}

					$setting;
					if($typeOfForm=="payment"){
						$this->setting= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  get_setting_Emsfb('raw');
						$r = $this->setting;
						if(gettype($r)=="string"){
							$setting =str_replace('\\', '', $r);
							$setting =json_decode($setting);

						}
						$ar_core = array_merge($ar_core , array(
							'paymentGateway' =>$paymentType,
							'paymentKey' => $paymentKey
						));
					}
				}

					if(strpos($value , '\"logic\":\"1\"') || strpos($value , '"logic":"1"')){
						wp_register_script('logic-efb',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', array(), EMSFB_PLUGIN_VERSION, true);
						wp_enqueue_script('logic-efb');
					}

				$send=array();
			$content_new="";
			$values ="";
			$is_user = 'guest';
			if ($admin_verified) {
				$is_user = 'admin';
			} else if (is_user_logged_in()) {
				$is_user = current_user_can('administrator') ? 'admin' : 'user';
			}
			$username = is_user_logged_in() ? wp_get_current_user()->user_login : 'guest';
			if ($is_track==null){

					$fs =str_replace('\\', '', $value_form_data->form_structer);
					$formObj= json_decode($fs,true);
					$valj_efb = json_decode($fs, false, 512, JSON_UNESCAPED_UNICODE);
					if(($valj_efb[0]->stateForm==true || $valj_efb[0]->stateForm==1) &&  is_user_logged_in()==false ){
						$typeOfForm="";
						$value="";
						$stng="";
						$fs= "";
					}
					if($valj_efb[0]->thank_you=="rdrct"){
						$valj_efb[0]->rePage="";
						$val_ = json_encode($valj_efb,JSON_UNESCAPED_UNICODE);
						$value = str_replace('"', '\\"', $val_);
					}

				$k="";
				if(($value_form_data->form_type=="login" || $value_form_data->form_type=="register"))$value= $value_form_data->form_structer;
				$stng = $this->pub_stting;
				if(gettype($stng)!=="integer" && $lanText['settingsNfound']){

				$s_m ='<!--efb-->';

				if( is_string($value) && (strpos($value , '\"type\":\"maps\"') !== false || strpos($value , '"type":"maps"') !== false)){
					$sm = $this->efbFunction->openstreet_map_required_efb(1);
					if($sm==false){
						$s_m =" <script>alert('OpenStreetMap Error:".$lanText['tfnapca']."')</script>";
					}
				}else{
					$new_string_value = json_encode($value);
					if(strpos($new_string_value , 'type":"maps"') || strpos($new_string_value , 'type\":\"maps\"')){
						$sm = $this->efbFunction->openstreet_map_required_efb(1);
						if($sm==false){
							$s_m =" <script>alert('OpenStreetMap Error:".$lanText['tfnapca']."')</script>";
						}
					}

				}
			}

		 	$width =0;
			$value =str_replace('\\', '', $value);
			$values = $value;

			   require_once(EMSFB_PLUGIN_DIRECTORY . '/includes/class-Emsfb-formbuilder.php');
			   $efbFormBuilder = new Formbuilder($valj_efb , $this->pro_efb);
			   $content="<!--efb-->";
			   $head ='<!--start head -->';
			   $form_id = $this->id;
			   if($valj_efb[0]->stateForm==true  && is_user_logged_in()==false ){
					$content ="
					".$bootstrap_icons."
					<div id='body_efb' class='efb row pb-3 efb px-2 v4'> <div class='efb text-center my-5'>
					<div class='efb bi-shield-lock-fill efb text-center display-1 my-2'></div><h3 class='efb  text-center fs-5'>". $lanText['formPrivateM']."</h3>
					".$efb_m."
					".$s_m."
					</div> </div>";
					return $content;
				}else if(($value_form_data->form_type=="login" || $value_form_data->form_type=="register") && is_user_logged_in()){

					$content = $efbFormBuilder->show_user_profile_emsFormBuilder( $lanText['logout'], $this->id);

					if(empty( $this->pub_stting)){
						$this->pub_stting = get_setting_Emsfb('pub')[1];
					}
					$this->value_forms[] = ['id' => $this->id, 'type' => $typeOfForm, 'form_structer' => $fs, 'sid' => $sid];
					$this->ajax_object_efm_efb($ar_core ,$values ,$typeOfForm ,$state ,$lang ,$poster ,$img ,$pro ,$page_builder ,$is_user ,$username,$lanText);
					return $content;
				}

			   $count = count($valj_efb);
				$step_no= 0;
				$head ='<!--start head efb-->';

				$msgs = [$lanText['pleaseWaiting'],$lanText['stf']];
				$wv = sprintf(
					'<div class="efb text-center">
						%1$s

					</div>',
				$efbFormBuilder->loading_message_efb($this->pro_efb,$msgs,1),

			);

			$style ='<style>#teststyleefb{display:none;}';
			$jss ='<script> //efbJs';
			$icons_els =[];
			$pro_element_exists = false;
			$auto_filled = false;

			$loading_type = isset($valj_efb[0]->loading_type) ? $valj_efb[0]->loading_type : 'dots';
			$loading_color = isset($valj_efb[0]->loading_color) ? $valj_efb[0]->loading_color : '#abb8c3';
			$loading_svg = $efbFormBuilder->efb_selected_loading_svg($loading_type, $loading_color);

			$efb_loading_ui_script = '<script>window.efb_loading_ui_' . intval($form_id) . ' = ' . json_encode($loading_svg) . ';</script>';

			$loading_svg_encoded = rawurlencode($loading_svg);
			$form_id_int = intval($form_id);
			$style .= ' .efb-waiting-' . $form_id_int . ' { position: relative; }';
			$style .= ' .efb-waiting-' . $form_id_int . ' * { pointer-events: none; opacity: 0.9; }';
			$style .= ' .efb-waiting-' . $form_id_int . '::after { content: ""; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 120px; height: 30px; background: url("data:image/svg+xml,' . $loading_svg_encoded . '") no-repeat center center; background-size: contain; z-index: 9999; }';

			$is_file_element_exist = false;
			$list_pro_elements = ['prcfld','dadfile','ttlprc','table_matrix','smartcr','pointr5','pointr10','booking','heading','zarinPal','persiaPay','stripe','paypal','link','yesNo','html','cityList','city','statePro','stateProvince','country','conturyList','paySelect','rating','esign','switch','trmCheckbox','imgRadio','chlRadio','chlCheckBox','payRadio','payCheckbox','mobile','maps','ardate','pdate'];
			for( $i=0; $i<$count; $i++){
				$randomId = wp_unique_id('efb_');

				 foreach ($valj_efb[$i] as $key => $value) {

					if(is_string($value)){
						if(strpos($value, 'colorDEfb') !== false){

							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}else if(strpos($value, 'bi-') !== false && strpos($key, 'icon') !== false){

							$icons_els[] = $value;
						}
						// Handle checked_color for radio/checkbox elements
						if($key === 'checked_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
						// Handle range_thumb_color for range elements
						if($key === 'range_thumb_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
						// Handle range_value_color for range elements
						if($key === 'range_value_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
						// Handle switch_on_color for switch elements
						if($key === 'switch_on_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
						// Handle switch_handle_color for switch elements
						if($key === 'switch_handle_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
						// Handle switch_off_color for switch elements
						if($key === 'switch_off_color' && !empty($value)){
							$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value, $key, $valj_efb[$i]);
						}
					}else{
						foreach ($value as $key2 => $value2) {
							if(is_string($value2)){
								if(strpos($value2, 'colorDEfb') !== false){

									$style .= ' '.$efbFormBuilder->fun_addStyle_customize_efb($value2, $key2, $valj_efb[$i]);
								}else if(strpos($value2, 'bi-') !== false && strpos($key2, 'icon') !== false){

									$icons_els[] = $value2;
								}
							}
						}
					}

				}

				if($valj_efb[$i]->type=="step" ){
					$valj_efb_first = $valj_efb[0];
					$value = $valj_efb[$i];
					$step_no = intval($valj_efb[$i]->step);
					$fieldset= '';
					if($step_no == 1){
						$fieldset=sprintf(
							'<fieldset data-step="step-%d-efb" id="step-%d-efb" class="efb my-2 mx-0 px-0 steps-efb efb row fieldset" data-formid="%s">',
							$step_no, $step_no, $form_id
						);
					}else{
						$fieldset= sprintf(
							'<div id="step-%d-efb-msg"></div></fieldset><!-- end fieldset --><fieldset data-step="step-%d-efb" id="step-%d-efb" class="efb my-2 mx-0 px-0 steps-efb efb row d-none fieldset" data-formid="%s">',
							$step_no - 1 , $step_no, $step_no, $form_id
						);

					}
					$content .= $fieldset;

					$head .= sprintf(
						'<li id="%1$s-f-step-efb-%3$s" data-step="icon-s-%2$d-efb" data-formid="%3$s" class="efb %4$s %5$s %6$s %7$s %8$s"><strong class="efb fs-5 %9$s">%10$s</strong></li>',
						$value->id_,
						$step_no,
						$form_id,
						$valj_efb_first->steps <= 6 ? 'step-w-' . $valj_efb_first->steps : 'step-w-6',
						$value->icon_color,
						$value->icon,
						$value->step == 1 ? 'active' : '',
						'',
						$value->label_text_color,
						$value->name
					);
					continue;
				}

				if($i>1){
					if(in_array($valj_efb[$i]->type, $list_pro_elements) && $pro_element_exists == false && $pro == true){
						wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,EMSFB_PLUGIN_VERSION);
						$pro_element_exists = true;
					}
					if(in_array($valj_efb[$i]->type, ["option","r_matrix"])) {continue;
					}else if($valj_efb[$i]->type =='mobile'){
						$img ['utilsJs']= EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js';

					}
					$img['logo']= EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg';
					$img['head']= EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png';

					if(in_array($valj_efb[$i]->type, ["file","dadfile"],true)){
						$is_file_element_exist = true;
					}

					$r = $efbFormBuilder->addNewElement_efb($i, $randomId, $form_id, $lanText);
					if($pro==true ){

						if($auto_filled == false &&  isset($valj_efb[0]->autofill_id) ){
							$autofill_id = intval($valj_efb[0]->autofill_id);

							if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/autofill")) {
								$this->efbFunction->download_all_addons_efb();
								return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have made some updates. Please wait a few minutes before trying again.','easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
							}

							if($autofill_id >0){
								wp_enqueue_script('efb-autofill', EMSFB_PLUGIN_URL . 'vendor/autofill/assets/js/autofill-public-efb.js',false,EMSFB_PLUGIN_VERSION);
							}else if($autofill_id == 0){

								$autofill_api = isset($valj_efb[0]->autofill_api) ? $valj_efb[0]->autofill_api : false;
								$autofill_api_id = isset($valj_efb[0]->autofill_api_id) ? $valj_efb[0]->autofill_api_id : '';
								if($autofill_api && !empty($autofill_api_id)){
									wp_enqueue_script('efb-autofill-api', EMSFB_PLUGIN_URL . 'vendor/autofill/assets/js/autofill-api-public-efb.js',false,EMSFB_PLUGIN_VERSION);
								}
							}
						}

						else if($auto_filled == false && !isset($valj_efb[0]->autofill_id) && isset($valj_efb[0]->autofill_api) && $valj_efb[0]->autofill_api){
							if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/autofill")) {
								$this->efbFunction->download_all_addons_efb();
							}
							$autofill_api_id = isset($valj_efb[0]->autofill_api_id) ? $valj_efb[0]->autofill_api_id : '';
							if(!empty($autofill_api_id)){
								wp_enqueue_script('efb-autofill-api', EMSFB_PLUGIN_URL . 'vendor/autofill/assets/js/autofill-api-public-efb.js',false,EMSFB_PLUGIN_VERSION);
							}
						}

						if($typeOfForm=="payment"){

							if ($valj_efb[$i]->type =='stripe' ){

									wp_register_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);
									wp_enqueue_script('stripe-js');

									!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe") ? $this->efbFunction->download_all_addons_efb() : '';
									wp_register_script('stripe_js',  EMSFB_PLUGIN_URL .'/public/assets/js/stripe_pay-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION,true);
									wp_enqueue_script('stripe_js');
									$paymentKey=isset($setting->stripePKey) && strlen($setting->stripePKey)>5 ? $setting->stripePKey:'null';
									$ar_core = array_merge($ar_core , array(
									'paymentGateway' =>'stripe',
									'paymentKey' => $paymentKey
								));
							}
							if($valj_efb[$i]->type =='paypal'){
								$paymentType="paypal";
								$paymentKey=isset($setting->paypalPKey)  ? $setting->paypalPKey:'null';
								$currency ='USD';

								!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/paypal") ? $this->efbFunction->download_all_addons_efb() : '';
								wp_register_script('paypalefb-js', EMSFB_PLUGIN_URL . 'vendor/paypal/assets/js/paypal_efb.js',array('jquery'), EMSFB_PLUGIN_VERSION, true);
								wp_enqueue_script('paypalefb-js');
								$ar_core = array_merge($ar_core , array(
									'paymentGateway' =>'paypal',
									'paymentKey_paypal' => $paymentKey
								));
							}
						}
					}

					if($typeOfForm=="survey" ){

						wp_register_script('poll-chart-efb-js', EMSFB_PLUGIN_URL . 'public/assets/js/poll-chart-efb.js',array('jquery'), EMSFB_PLUGIN_VERSION, true);
						wp_enqueue_script('poll-chart-efb-js');

					}
					$content .= $r[0];
					$style .= $r[1];
					$r[2]!='' ? $jss .= $r[2] : 0;

				}
			}
			$captcha_content = '';
			if (empty($this->pub_stting) || !is_array($this->pub_stting)) {

				if (isset($rp) && is_array($rp) && isset($rp[1]) && is_array($rp[1])) {
					$this->pub_stting = $rp[1];
				} else {
					$pub = get_setting_Emsfb('pub');
					$this->pub_stting = (is_array($pub) && isset($pub[1]) && is_array($pub[1])) ? $pub[1] : [];
				}
			}
			if($valj_efb[0]->captcha==true && isset($valj_efb[0]->logic)==false ){
				$error_msg = "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('The form is not shown because Google reCAPTCHA has not been added to the Easy Form Builder plugin settings.','easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";

				$siteKey = '';
				if (is_array($this->pub_stting) && isset($this->pub_stting['siteKey'])) {
					$siteKey = (string) $this->pub_stting['siteKey'];
				}
				if(strlen($siteKey) <= 5){
					return $error_msg;
				}
				$rgister_captcha_url = $this->efbFunction->check_and_enqueue_google_captcha_efb($lang);

				$captcha_content =$efbFormBuilder->fun_captcha_load_efb($siteKey, $form_id,$step_no - 1);
			}

			if (strlen((string) $content) > 10) {
				$step_no++;
				$loading = $efbFormBuilder->loading_message_efb($this->pro_efb, $msgs, 1);

				$content .= "
					" . $captcha_content . "
					</fieldset>
					<fieldset data-step='step-{$step_no}-efb' class='efb my-5 steps-efb efb row d-none text-center' id='efb-final-step' data-formid='".$form_id."'>
						<!-- fieldset finall -->
						".$loading."
						<div step-{$step_no}-efb></div>
					</fieldset>";

				$head_final_step = "<li id='f-step-efb-{$form_id}' data-step='icon-s-{$step_no}-efb' data-formid='{$form_id}' class='efb {$valj_efb[1]->icon_color} " . (($valj_efb[0]->steps <= 6) ? "step-w-{$valj_efb[0]->steps}" : "step-w-6") . " bi-check-lg mx-0'>
					<strong class='efb fs-5 {$valj_efb[1]->label_text_color}'>".$lanText['finish']."</strong>
				</li>";

				$bgc = isset($valj_efb[0]->prg_bar_color) ? $valj_efb[0]->prg_bar_color : 'btn-primary';

				$percent = (1 / ($step_no)) * 100;
				$percent = round($percent, 2);
				$head = (intval($valj_efb[0]->show_icon) != 1 ? '<ul id="steps-efb" class="efb mb-2 px-2" data-formid="'.$form_id.'">' . $head . $head_final_step.'</ul>' : '') .
						(intval($valj_efb[0]->show_pro_bar)!= 1 ?
							'<div class="efb d-flex justify-content-center" id="f-progress-efb">
								<div class="efb progress mx-3 w-100 ' . $bgc . '">
									<div class="efb progress-bar-efb progress-bar-striped progress-bar-animated" role="progressbar" aria-valuemin="0" aria-valuemax="100"  style="width: '.$percent.'%;" data-formid="'.$this->id.'"></div>
								</div>
							</div><br>' : '');

				$step_no--;
			}
			$row_form_info= ['id' => $this->id, 'type' => $typeOfForm, 'form_structer' => $fs, 'sid' => $sid];
			if($is_file_element_exist){

				$code = 'efb'.$this->id;
				$row_form_info['nonce_msg'] = wp_create_nonce($code);
			}
			$this->value_forms[] = $row_form_info;
			$style = $style.'</style>';
			$jss = $jss.'</script>';

			$script = '';
			if (current_user_can('manage_options')) {
				$console_checker = $efbFormBuilder->check_error_console_efb();
				$script = '<script>'.$console_checker.'</script>';
			}

			$stps_state = $step_no>1 ? 1 : 0;
			$navButton = $efbFormBuilder->add_buttons_zone_efb($stps_state, $this->id, $valj_efb, $lanText, $this->id);

			$dShow = isset($valj_efb[0]->dShowBg) && intval($valj_efb[0]->dShowBg) != 1 ? 'card' : '';
			$direction_attr = is_rtl() ? ' dir="rtl"' : '';

			$mobile_css_efb = $efbFormBuilder->generate_mobile_css_efb();
			if($valj_efb[0]->type=="login" || $valj_efb[0]->type=="register"){

				$ps_form = $this->pub_stting ?? (get_setting_Emsfb('pub')[1] ?? []);
				$overrides_form = $this->efb_build_inline_style_overrides($ps_form);
				$inline_style_form = $overrides_form['inline_style'];
				$font_link_form = $overrides_form['font_link'];
				$mobile_css_efb = $mobile_css_efb.$font_link_form.$inline_style_form;

			}
                        $content_new = $style.$mobile_css_efb.$efb_loading_ui_script.$script.$bootstrap_icons.''.$iconst_html_preload.'
				<!-- start body_efb-->

				<div id="body_efb_'.$form_id.'" class="efb row pb-3 efb px-2 pre-efb body_efb efb-waiting-'.$this->id.' '.$dShow.'" data-currentstep="1" data-steps="'.$valj_efb[0]->steps.'" data-formid="'.$this->id.'"'.$direction_attr.'>
					<form id="efbform" class="mx-0 px-0 efb" data-formid="'.$this->id.'"'.$direction_attr.'>
						<div class="efb px-0 pt-2 pb-0 my-1 col-12 mb-2 view-efb" id="view-efb" data-formid="'.$this->id.'">
						' . (intval($valj_efb[0]->show_icon) != 1
							? '<h4 id="title_efb" class="efb fs-3 ' . $valj_efb[1]->label_text_color . ' text-center mt-3 mb-0 title_efb" data-formid="'.$this->id.'">' . $valj_efb[1]->name . '</h4>
							<p id="desc_efb" class="efb ' . $valj_efb[1]->message_text_color . ' text-center fs-6 mb-2 desc_efb" data-formid="'.$this->id.'">' . $valj_efb[1]->message . '</p>'
							: '') . '
							' . $head . '
							<div class="efb mt-1 px-2">' . $content . '</div>
						</div>
						<!-- end view-efb-->
						' . $navButton . '
					</form>
					<!-- end form-->
					</div>
					<!-- end body_efb-->
					<div id="alert_efb" class="efb mx-5 alert_efb" data-formid="'.$this->id.'"></div>
					<!-- style efb -->
					 '.$k . $jss;
		}else{
			if(  $is_track['captcha'] == true && $rgister_captcha_url==false){
				$rgister_captcha_url = $this->efbFunction->check_and_enqueue_google_captcha_efb($lang);
			}
			$content_new =$is_track['content'];
		}
					if(empty( $this->pub_stting)){
					$this->pub_stting = get_setting_Emsfb('pub')[1];
					}

					$this->ajax_object_efm_efb($ar_core ,$values ,$typeOfForm ,$state ,$lang,$poster ,$img ,$pro ,$page_builder ,$is_user ,$username,$lanText);

					return $content_new;

	}

	private function efb_build_inline_style_overrides($ps = null) {
		if ($ps === null) {
			$pl = get_setting_Emsfb('pub');
			$ps = $pl[1] ?? [];
		}

		$css_var_map = [
			'respPrimary'     => ['--efb-resp-primary',      '#3644d2'],
			'respPrimaryDark' => ['--efb-resp-primary-dark', '#202a8d'],
			'respAccent'      => ['--efb-resp-accent',       '#ffc107'],
			'respText'        => ['--efb-resp-text',         '#1a1a2e'],
			'respTextMuted'   => ['--efb-resp-text-muted',   '#657096'],
			'respBgCard'      => ['--efb-resp-bg-card',      '#ffffff'],
			'respBgMeta'      => ['--efb-resp-bg-meta',      '#f6f7fb'],
			'respBgTrack'     => ['--efb-resp-bg-track',     '#ffffff'],
			'respBgResp'      => ['--efb-resp-bg-resp',      '#f8f9fd'],
			'respBgEditor'    => ['--efb-resp-bg-editor',    '#ffffff'],
			'respEditorText'  => ['--efb-resp-editor-text',  '#1a1a2e'],
			'respEditorPh'    => ['--efb-resp-editor-ph',    '#a0aec0'],
			'respBtnText'     => ['--efb-resp-btn-text',     '#ffffff'],
			'respFontFamily'  => ['--efb-resp-font-family',  'inherit'],
			'respFontSize'    => ['--efb-resp-font-size',    '0.9rem'],
		];

		$css_overrides = '';
		$primary_hex   = '';
		foreach ($css_var_map as $key => $info) {
			$val_s = isset($ps[$key]) && $ps[$key] !== '' ? $ps[$key] : $info[1];
			if ($val_s !== $info[1]) {
				$safe_val = preg_replace('/[<>&{}]/', '', $val_s);
				$css_overrides .= $info[0] . ':' . $safe_val . ';';
			}
			if ($key === 'respPrimary') $primary_hex = $val_s;
		}

		if ($primary_hex !== '#3644d2' && preg_match('/^#[0-9a-fA-F]{6}$/', $primary_hex)) {
			$r = hexdec(substr($primary_hex, 1, 2));
			$g = hexdec(substr($primary_hex, 3, 2));
			$b = hexdec(substr($primary_hex, 5, 2));
			$css_overrides .= "--efb-resp-primary-08:rgba({$r},{$g},{$b},0.08);";
			$css_overrides .= "--efb-resp-primary-10:rgba({$r},{$g},{$b},0.10);";
			$css_overrides .= "--efb-resp-primary-06:rgba({$r},{$g},{$b},0.06);";
			$css_overrides .= "--efb-resp-border:rgba({$r},{$g},{$b},0.12);";
			$css_overrides .= "--efb-resp-shadow:0 2px 16px rgba({$r},{$g},{$b},0.07);";
			$css_overrides .= "--efb-resp-shadow-hover:0 4px 24px rgba({$r},{$g},{$b},0.13);";
		}

		$inline_style = $css_overrides !== '' ? '<style>:root{' . $css_overrides . '}</style>' : '';

		$font_link = '';

		if (!empty($ps['respCustomFont'])) {
			$cf = json_decode($ps['respCustomFont'], true);
			if (is_array($cf) && !empty($cf['url'])) {
				$font_link = '<link rel="stylesheet" href="' . esc_url($cf['url']) . '">';
			}
		}

		if (empty($font_link) && !empty($ps['respFontFamily']) && $ps['respFontFamily'] !== 'inherit') {
			$font_css_map = [
				"Vazirmatn, Tahoma, sans-serif"                     => "https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap",
				"Vazir, Tahoma, sans-serif"                          => "https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@latest/dist/font-face.css",
				"Sahel, Tahoma, sans-serif"                          => "https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@latest/dist/font-face.css",
				"Samim, Tahoma, sans-serif"                          => "https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@latest/dist/font-face.css",
				"'Shabnam', Tahoma, sans-serif"                      => "https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@latest/dist/font-face.css",
				"Parastoo, Tahoma, sans-serif"                       => "https://cdn.jsdelivr.net/gh/rastikerdar/parastoo-font@latest/dist/font-face.css",
				"Gandom, Tahoma, sans-serif"                         => "https://cdn.jsdelivr.net/gh/rastikerdar/gandom-font@latest/dist/font-face.css",
				"Lalezar, Tahoma, sans-serif"                        => "https://fonts.googleapis.com/css2?family=Lalezar&display=swap",
				"Cairo, Tahoma, sans-serif"                          => "https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap",
				"Tajawal, Tahoma, sans-serif"                        => "https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap",
				"'Noto Sans Arabic', Tahoma, sans-serif"             => "https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100..900&display=swap",
				"'IBM Plex Sans Arabic', Tahoma, sans-serif"         => "https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100;200;300;400;500;600;700&display=swap",
				"Amiri, Tahoma, serif"                               => "https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap",
				"'Noto Kufi Arabic', Tahoma, sans-serif"             => "https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@100..900&display=swap",
				"'Inter', sans-serif"                                => "https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap",
				"'Roboto', sans-serif"                               => "https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap",
				"'Open Sans', sans-serif"                            => "https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..800&display=swap",
			];
			if (isset($font_css_map[$ps['respFontFamily']])) {
				$font_link = '<link rel="stylesheet" href="' . esc_url($font_css_map[$ps['respFontFamily']]) . '">';
			}
		}

		return ['inline_style' => $inline_style, 'font_link' => $font_link];
	}

	public function EMS_Form_Builder_track(){
		$this->enqueue_jquery();

		$this->id=0;
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
		$text=['pleaseEnterTheTracking','pleaseWaiting','fil','trackingCode','entrTrkngNo','search','easyFormBuilder','createdBy','tfnapca'];
		$text= $this->efbFunction->text_efb($text) ;
		$state="tracker";
		$pl= get_setting_Emsfb('pub');
		$stng= $pl[0];
		$s_m ='<!--efb-->';
		if(gettype($stng)=="integer" && $stng==0){
			$stng=$text['settingsNfound'];
			$state="tracker";
		}else{
			   $valstng= json_decode($stng);
			   if(isset($valstng->siteKey) && isset($valstng->scaptcha) && $valstng->scaptcha==true){

				}

				if(isset($valstng->osLocationPicker) && $valstng->osLocationPicker==true){
					$sm = $this->efbFunction->openstreet_map_required_efb(1);
					if($sm==false){
						$s_m =" <script>alert('OpenStreetMap Error:".$text['tfnapca']."')</script>";
					}
				}
		}

		$pro = intval(get_option('emsfb_pro'));

		$pro = $pro==1 || $pro == 3 ? true : false;
		$this->pro_efb = $pro;

		$this->comper_version_efb($pl[1]['version']);
		if($pro==true){
			wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,EMSFB_PLUGIN_VERSION);
		}

		$location = '';

		$sid = $this->efbFunction->efb_code_validate_create( 0 , 0, 'visit' , 0);
		$sc = isset($_GET['sc']) ? sanitize_text_field($_GET['sc']) : 'null';

		$get_track ='';
		$captcha_exist = false;
		if(isset($_GET['track'])){
			$get_track = sanitize_text_field($_GET['track']);
		}
			$script_call_captcha = '';
			if (isset($valstng->siteKey) && isset($valstng->scaptcha) && $valstng->scaptcha==true ){
				$script_call_captcha =sprintf(
						'<div class="efb efb-tracker-captcha">
									<div id="gRecaptcha" class="efb g-recaptcha" data-sitekey="%1$s" data-formid="-1" ></div>
									<small class="efb text-danger" id="recaptcha-message"></small>
								</div>	<script>
						document.addEventListener("DOMContentLoaded", function() {
							loadCaptcha_efb(20);
						});
						</script>' ,
								$valstng->siteKey
				);
				$captcha_exist = true;
			}

		$track_content =  sprintf(
			'<div class="efb %1$s">
				<div class="efb efb-tracker-card" id="body_efb-track" data-formid="0">
					<div class="efb efb-tracker-icon-wrap">
						<i class="efb bi-shield-check efb-tracker-icon-circle"></i>
					</div>
					<h4 class="efb efb-tracker-title">%2$s</h4>
					<p class="efb efb-tracker-subtitle">%3$s</p>
					<div class="efb efb-tracker-input-group">
						<i class="efb bi-hash efb-tracker-input-icon"></i>
						<input type="text" class="efb input-efb efb-tracker-input"
							   placeholder="%4$s" id="trackingCodeEfb" value="%5$s" autocomplete="off" spellcheck="false">
					</div>
					%6$s
					<button type="submit" class="efb btn btn-pinkEfb efb-tracker-btn" id="vaid_check_emsFormBuilder" onclick="fun_vaid_tracker_check_emsFormBuilder()">
						<i class="efb bi-search efb-tracker-btn-icon"></i> %7$s
					</button>
				</div>
			</div>
			<div id="alert_efb" class="efb mx-5"></div>',
			is_rtl() ? 'rtl-text' : '',
			$text['pleaseEnterTheTracking'],
			$text['trackingCode'],
			$text['entrTrkngNo'],
			$get_track,
			$script_call_captcha,
			$text['search']
		);
		 $val = $pro==true ? '<!--efb.app-->' : '<div class="efb d-none"><a href="https://whitestudio.team"  class="efb text-decoration-none" target="_blank"><p class="efb fs-7 text-darkb mb-4" style="text-align: center;">'.$text['easyFormBuilder'].'<p></a></div>';

		$ps = $pl[1] ?? [];
		$overrides_track = $this->efb_build_inline_style_overrides($ps);
		$inline_style      = $overrides_track['inline_style'];
		$builtin_font_link = $overrides_track['font_link'];

	 	$content="<script> sitekye_emsFormBuilder='' </script>".$s_m . $builtin_font_link . $inline_style ."
		<div id='body_tracker_emsFormBuilder' class='efb '><div id='alert_efb' class='efb mx-5 text-center'></div>
		".$track_content."</div>" . $val ;

		return  ['content'=>$content, 'captcha'=>$captcha_exist];
		return $content;
	}
	function public_scripts_and_css_head($state=''){

		wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style-efb.css', true,EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('Emsfb-style-css');

		if(is_rtl()){
			wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl-efb.css', true ,EMSFB_PLUGIN_VERSION);
			wp_enqueue_style('Emsfb-css-rtl');
		}
		$googleCaptcha=false;
		wp_register_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css', true,EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('Emsfb-bootstrap-css');

		if($state=='css') return;

		wp_register_style('Emsfb-response-viewer-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/response-viewer-efb.css', true, EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('Emsfb-response-viewer-css');
		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js',array('jquery'), EMSFB_PLUGIN_VERSION, true);
		wp_register_script('efb-response-viewer-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/response-viewer-efb.js', array('efb-main-js'), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-response-viewer-js');
		wp_register_script('Emsfb-core_js', plugins_url('../public/assets/js/core-efb.js',__FILE__), array('jquery', 'efb-main-js', 'efb-response-viewer-js'), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('Emsfb-core_js');

		$ar_core = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wp_rest'),
		);
		wp_localize_script( 'Emsfb-core_js', 'efb_var', $ar_core);
	  }

	private function efb_send_json_and_continue($response, $status_code = 200) {

		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);
		ignore_user_abort(true);
		set_time_limit(300);

		$environment_method = 'Unknown';
		$start_time = microtime(true);

		if (session_id()) {
			session_write_close();
		}

		if ($status_code >= 200 && $status_code < 300) {
			$json_response = array(
				'success' => true,
				'data'    => $response,
			);
		} else {
			$json_response = array(
				'success' => false,
				'data'    => $response,
			);
		}

		$output = wp_json_encode($json_response);
		$content_length = strlen($output);

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		ob_start();

		header('Content-Type: application/json; charset=utf-8');
		header('Content-Length: ' . $content_length);
		header('Connection: close');
		header('Content-Encoding: none');

		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $output;

		if (ob_get_level() > 0) {
			ob_end_flush();
		}

		@ob_flush();
		flush();

		if (function_exists('fastcgi_finish_request')) {
			$environment_method = 'PHP-FPM (fastcgi_finish_request)';
			fastcgi_finish_request();
			$this->log_background_method($environment_method, $start_time);
			return true;
		}

		if (function_exists('apache_setenv')) {
			@apache_setenv('no-gzip', '1');
		}

		if (function_exists('litespeed_finish_request')) {
			$environment_method = 'LiteSpeed (litespeed_finish_request)';
			litespeed_finish_request();
			$this->log_background_method($environment_method, $start_time);
			return true;
		}

		if (ob_get_level() == 0) {
			ob_start();
		}

		echo str_repeat(' ', 4096);

		if (ob_get_level() > 0) {
			ob_end_flush();
		}
		flush();

		if (function_exists('apache_setenv')) {
			$environment_method = 'Apache (fallback with padding)';
		} else {
			$environment_method = 'Generic (padding fallback)';
		}

		$this->log_background_method($environment_method, $start_time);
		return true;
	}

	private function log_background_method($method, $start_time) {
		$elapsed = round((microtime(true) - $start_time) * 1000, 2);
		$log_message = sprintf(
			'[EFB Background] Method: %s | Response Time: %sms | Server: %s | PHP: %s',
			$method,
			$elapsed,
			isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
			PHP_SAPI
		);
	}

	private function trigger_background_processing($data) {

		$transient_key = 'efb_bg_' . $data['track_id'];
		set_transient($transient_key, $data, 300);

		if (function_exists('wp_schedule_single_event')) {
			wp_schedule_single_event(time(), 'efb_process_background_cron', [$data['track_id']]);
			spawn_cron();
			return;
		}

		$url = admin_url('admin-ajax.php');

		wp_remote_post($url, [
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => false,
			'body'      => [
				'action'   => 'efb_process_background',
				'track_id' => $data['track_id']
			]
		]);

	}

	public function process_background_task() {

		$track_id = isset($_POST['track_id']) ? sanitize_text_field($_POST['track_id']) : '';

		if (empty($track_id)) {
			exit;
		}

		$transient_key = 'efb_bg_' . $track_id;
		$data = get_transient($transient_key);

		if (!$data) {
			exit;
		}

		delete_transient($transient_key);

		$timing_start = microtime(true);

		$timing_sms_start = microtime(true);
		if ($data['send_sms'] && !empty($data['phone_numbers'])) {
			$smsSendResult = $this->efbFunction->sms_ready_for_send_efb(
				$data['form_id'],
				$data['phone_numbers'],
				$data['url'],
				'fform',
				'wpsms',
				$data['track_id']
			);

			if ($smsSendResult !== true) {
			}
		}
		$timing_sms = round((microtime(true) - $timing_sms_start) * 1000, 2);

		$timing_email_start = microtime(true);
		if ($data['send_email']) {
			$this->email_list_efb($data['email_user'], 0, $data['email_fa'], true);

			$state_email_user = $data['trackingCode_state'] == 1
				? 'notiToUserFormFilled_TrackingCode'
				: 'notiToUserFormFilled';

			$msg_content = 'null';
			if (isset($data['formObj'][0]['email_noti_type']) && $data['formObj'][0]['email_noti_type'] == 'msg') {
				$msg_content = $this->email_get_content_efb($data['valobj'], $data['track_id']);
				$msg_content = str_replace("\"", "'", $msg_content);
			}

			$status_email = $this->email_status_efb($data['formObj'], $data['valobj'], $data['track_id']);
			$state_of_email = ['newMessage', $state_email_user, $status_email['type']];

			$this->send_email_Emsfb_(
				$data['email_user'],
				$data['track_id'],
				$data['pro'],
				$state_of_email,
				$data['url'],
				$status_email['content'],
				$status_email['subject']
			);
		}
		$timing_email = round((microtime(true) - $timing_email_start) * 1000, 2);

		$timing_total = round((microtime(true) - $timing_start) * 1000, 2);

		exit;
	}

	  public function get_form_public_efb($data_POST_) {
		$request_data = $data_POST_->get_json_params();

		$translation_keys = [
			'somethingWentWrongPleaseRefresh', 'pleaseMakeSureAllFields', 'bkXpM_', 'bkFlM_', 'mnvvXXX_', 'ptrnMmm_', 'ptrnMmx_', 'payment', 'error403', 'errorSiteKeyM',
			'errorCaptcha', 'pleaseEnterVaildValue', 'createAcountDoneM', 'incorrectUP', 'sentBy', 'newPassM', 'done', 'surveyComplatedM', 'error405', 'errorSettingNFound', 'errorMRobot',
			'clcdetls', 'vmgs', 'youRecivedNewMessage', 'WeRecivedUrM', 'thankRegistering', 'welcome', 'thankSubscribing', 'thankDonePoll', 'thankFillForm', 'trackNo', 'fernvtf', 'msgdml', 'newMessageReceived','sxnlex','snotfound','response','fform','msgSndBut','smsWPN',
			'surveyResults', 'responses'
		];
		$efbFunction = get_efbFunction();

		$session_id = sanitize_text_field($request_data['sid']);
		$this->id = sanitize_text_field($request_data['id']);
		$page_id = sanitize_text_field($request_data['page_id']);
		$request_data['url'] = $url = sanitize_url($request_data['url']);

		if(empty($this->efbFunction)) {
			$this->efbFunction = $efbFunction;
		}

		$session_is_valid = $this->efbFunction->efb_code_validate_select($session_id, $this->id);
		$this->lanText = $this->efbFunction->text_efb($translation_keys);
		$plugin_settings;
		$cache_plugins = get_option('emsfb_cache_plugins','0');
		if($cache_plugins!='0') $this->cache_cleaner_Efb($page_id,$cache_plugins);

		$user_id = 1;
		$admin_email_list = [];

		if (false === ($plugin_settings = wp_cache_get('emsfb_settings' , 'emsfb'))) {
			$r = $this->setting != NULL && !empty($this->setting) ? $this->setting : get_setting_Emsfb('raw');
			$plugin_settings = is_string($r) ? json_decode(str_replace("\\", "", $r), true) : $r;
			wp_cache_set('emsfb_settings', $plugin_settings , 'emsfb');
		} else {

			$r = $this->setting != NULL && !empty($this->setting) ? $this->setting : get_setting_Emsfb('raw');
		}

		if (is_string($r)) {
			$r = str_replace('\\', '', $r);
			$this->setting = json_decode($r);
		} else if (is_array($plugin_settings)) {
			$this->setting = json_decode(json_encode($plugin_settings), false);
		} else if (is_object($r)) {
			$this->setting = $r;
		}

		if (isset($plugin_settings['emailSupporter'])) {
			array_push($admin_email_list, $plugin_settings['emailSupporter']);
		}
		if(isset($plugin_settings['smtp']) && (bool)$plugin_settings['smtp'] ){

						$should_send_email = true;
		}
		$is_pro = intval(get_option('emsfb_pro'));
		$is_pro = $is_pro == 1 || $is_pro == 3 ? true : false;
		$this->pro_efb = $is_pro;
		$submission_type = sanitize_text_field($request_data['type']);
		$email = get_option('admin_email');
		$redirect_url = "null";
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$this->id = intval($this->id);
		$form_db_data = $this->get_form_data_efb($this->id, array('form_structer', 'form_type'));

		if (empty($form_db_data)) {
			$msg = 'Form not found.';
			if (isset($this->lanText) && isset($this->lanText['snotfound']) && isset($this->lanText['fform'])) {
				$msg = sprintf($this->lanText['snotfound'], ucfirst($this->lanText['fform']));
			}
			$response = ['success' => false, 'm' => $msg];
			wp_send_json_success($response, 200);
		}

		$form_structure_json = isset($form_db_data) ? str_replace('\\', '', $form_db_data->form_structer) : '';
		$skip_captcha = $form_fields_array = $has_tracking_code  = $track_code = "";
		$should_send_email=false;
		$email_recipients = [];
		$this->value = str_replace('\\', '', $request_data['value']);
		$submitted_values = json_decode($this->value, true);

		if ( empty($submitted_values)) {

			$msg = 'Form data not found.';
			if (isset($this->lanText) && isset($this->lanText['snotfound']) && isset($this->lanText['fform'])) {
				$msg = sprintf($this->lanText['snotfound'], ucfirst($this->lanText['fform']));
			}

			$response = ['success' => false, 'm' =>$msg];
			wp_send_json_success($response, 200);
		}else{

			$submitted_values = $this->dedupe_by_id_and_ob_efb($submitted_values);
		}
		$sms_notification_enabled = 0;
		$phone_numbers = [[], []];
		$has_multiple_emails = false;
		$should_send_email = false;
		if (isset($plugin_settings['sms_config']) && $plugin_settings['sms_config'] == "wpsms") {
			$numbers = isset($plugin_settings['phnNo']) ? $plugin_settings['phnNo'] : [];
			if (strlen($numbers) > 5) $phone_numbers[0] = explode(',', $numbers);
			$sms_notification_enabled = 1;
		}
		$sms_notification_enabled = strpos($form_structure_json, '\"smsnoti\":\"1\"') !== false || $sms_notification_enabled == 1 ? 1 : 0;
		if ($form_structure_json != '') {
			$form_fields_array = json_decode($form_structure_json, true);
			$form_structure_json = null;
			$has_multiple_emails = isset($form_fields_array[0]["email_send_type"]) ? $form_fields_array[0]["email_send_type"] : false;

			$form_type = $form_fields_array[0]['type'] ?? 'form';
			if (!isset($submitted_values['logout']) && !isset($submitted_values['recovery']) && $form_type!='register' && $form_type!='login') {
				if(isset($plugin_settings['smtp']) && (bool)$plugin_settings['smtp'] ){
						$should_send_email = true;
				}
				$form_admin_email = $form_fields_array[0]['email'];
				if($should_send_email && !empty($form_admin_email)){
					$is_multipleEmail = strpos($form_admin_email, ',') !== false;
					$this->email_list_efb($email_recipients , 0 , $form_admin_email ,$is_multipleEmail);
				}
				$has_tracking_code = $form_fields_array[0]['trackingCode'] == true || $form_fields_array[0]['trackingCode'] == "true" || $form_fields_array[0]['trackingCode'] == 1 ? 1 : 0;
				if ($submission_type != $form_fields_array[0]['type']) {
					$response = ['success' => false, 'm' => $this->lanText['fernvtf']];
					wp_send_json_success($response, 200);
				}
				if ($form_fields_array[0]['thank_you'] == "rdrct") {
					$redirect_url = $this->string_to_url($form_fields_array[0]['rePage']);
				}
				$validated_items = [];
				$is_valid = 0;
				$validated_item;
				if (isset($request_data['url']) && strlen($request_data['url']) > 5) {
					$d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) :'';
					$ar = ['http://wwww.' .$d, 'https://wwww.' . $d, 'http://' . $d, 'https://' . $d];
					foreach ($ar as $r) {
						$c = strpos($request_data['url'], $r);
						if (gettype($c) != 'boolean' && $c == 0) {
							$is_valid = 1;
						}
					}
					if ($is_valid == 1) {
						$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
					}
				}
				if ($is_valid == 0) {
					$response = ['success' => false, 'm' => $this->lanText['error403']];
					wp_send_json_success($response, 200);
				}
				$error_message = '';
				$is_valid = 1;
				$form_condition = '';
				if (isset($form_fields_array[0]['booking']) && $form_fields_array[0]['booking'] == 1) $form_condition = 'booking';
				$currency = '';
				if(isset($form_fields_array[0]['currency']) && strlen($form_fields_array[0]['currency'])>1) $currency = $form_fields_array[0]['currency'];

				foreach ($form_fields_array as $key => $f) {
					$validated_item = null;
					$still_processing = true;
					if ($key < 2 && !isset($f['id_'])){ continue;}
					if ($is_valid == 0) {break;}
					$it = array_filter($submitted_values, function ($item) use ($f, $key, &$is_valid, &$email_recipients, &$validated_item, &$form_fields_array, &$still_processing, &$error_message, $form_condition, &$sms_notification_enabled, &$phone_numbers) {
						if ($still_processing == false) {
							return;
						}
						if (((isset($f['disabled']) == true &&  $f['disabled'] == 1  && isset($f['hidden']) == false)
								|| (isset($f['disabled']) == true && $f['disabled'] == 1 && isset($f['hidden']) == true && $f['hidden'] == false))
							&& ($item['id_'] == $f['id_'] || $f['id_'] == $item['id_'])
							&& strlen($item['value']) > 1
						) {
							$is_valid = 0;
							$still_processing == false;
							return;
						}
						$t = strpos(strtolower($item['type']), 'checkbox');
						if (
							isset($f['id_']) && isset($item['id_']) && ($f['id_'] == $item['id_']
								||  gettype($t) == "integer" && $f['id_'] == $item['id_ob'])
							|| (($f['type'] == "persiaPay" || $f['type'] == "persiapay" || $f['type'] == "payment") && $form_fields_array[0]['type'] == 'payment')
							|| ($item['type'] == 'r_matrix' && $f['id_'] == $item['id_ob'])
						) {
							if (isset($f['name'])) {
								$error_message = $this->lanText['mnvvXXX_'];
								$error_message = str_replace('%s', "<b>" . $f['name'] . "</b>", $error_message);
							}
							switch ($f['type']) {
								case 'email':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_email($item['value']);
										$is_valid = 1;
										$validated_item = $item;
										$l = strlen($item['value']);
										if (!filter_var($item['value'], FILTER_VALIDATE_EMAIL)) {
											$error_message = str_replace('%s', $f['name'], $error_message);
											$is_valid = 0;
										}

										$e_ar = isset($form_fields_array[0]['email_send_type']) ? $form_fields_array[0]['email_send_type'] : false;
										if ((isset($f['milen']) && $f['milen'] > $l) || (isset($f['mlen']) && $f['mlen'] < $l)) {
											$is_valid = 0;
										}
										if (isset($f['noti']) == true && intval($f['noti']) == 1)  $this->email_list_efb($email_recipients, 1, $item['value'], $e_ar);

									}
									$still_processing = false;
									break;
								case "date":
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_text_field($item['value']);
										$v = explode("-", $item['value']);
										if (count($v) == 3 && checkdate($v[1], $v[2], $v[0])) {
											$is_valid = 1;
											$validated_item = $item;
											$current_date = date('Y-m-d');
											if (isset($f['milen']) && $f['milen'] != '') {
												$f['milen'] = intval($f['milen']) == 1 ? $current_date : $f['milen'];
												if ($f['milen'] != '' && (strtotime($f['milen']) > strtotime($item['value']) || strtotime($f['milen']) > strtotime($item['value']))) {
													$is_valid = 0;
												}
											}
											if (isset($f['mlen']) && $f['mlen'] != '') {
												$f['mlen'] = intval($f['mlen']) == 1 ? $current_date : $f['mlen'];
												if ($f['mlen'] != '' && (strtotime($f['mlen']) < strtotime($item['value']) || strtotime($f['mlen']) < strtotime($item['value']))) {
													$is_valid = 0;
												}
											}
										} else {
											$validated_item = "";
											$is_valid = 0;
										}
									}
									$still_processing = false;
									break;
								case 'url':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_url($item['value']);
										$is_valid = 1;
										$l = strlen($item['value']);
										if ((isset($f['milen']) && $f['milen'] > $l) || (isset($f['mlen']) && $f['mlen'] < $l)) {
											$is_valid = 0;
										}
									}

									$validated_item = $item;
									$still_processing = false;
									break;
								case 'mobile':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_text_field($item['value']);
										$is_valid = 0;
										$item['value'] = preg_replace('/\s+/', '', $item['value']);
										if (isset($f['smsnoti']) && intval($f['smsnoti']) == 1) {
											$sms_notification_enabled = 1;
											array_push($phone_numbers[1], $item['value']);
										}
										$l = isset($f['c_n']) && count($f['c_n']) >= 1 ? $f['c_n'] : ['all'];
										array_filter($l, function ($no) use ($item, &$is_valid) {
											$pos = strrpos($item['value'], '+');
											if ($pos !== false) {
												$item['value'] = substr($item['value'], $pos);
											}

											$v = strpos($item['value'], '+' . $no);
											if (strpos($item['value'], '+' . $no) === 0 || $no == 'all') $is_valid = 1;
										});
									}
									$validated_item = $item;
									$still_processing = false;
									break;
								case 'radio':
								case 'payRadio':
								case 'chlRadio':
								case 'imgRadio':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_text_field($item['value']);
										array_filter($form_fields_array, function ($fr, $ki) use (&$item, &$validated_item, &$is_valid, &$form_fields_array, $form_condition, &$error_message) {
											if (isset($fr['id_']) && isset($item['id_ob']) && $fr['id_'] == $item['id_ob']) {
												$item['value'] = $fr['value'];
												$is_valid = 1;
												$t = strpos($item['type'], 'pay');
												if ($t != false) {
													$item['price'] = $fr['price'];
												}
												$t = strpos($item['type'], 'img');
												if (isset($fr['src'])) {
													$item['src'] = $fr['src'];
													$item['sub_value'] = $fr['sub_value'];
												}
												if ($form_condition == 'booking') {
													if (isset($fr['dateExp']) == true) {
														if (strtotime($fr['dateExp']) < strtotime(wp_date('Y-m-d'))) {
															$is_valid = 0;
															$error_message = $this->lanText['bkXpM_'];
															$error_message = str_replace('%s', $fr['value'], $error_message);
														}
													}
													if (isset($fr['mlen']) == true) {
														if ($fr['mlen'] <= $fr['registered_count']) {
															$is_valid = 0;
															$error_message = $this->lanText['bkFlM_'];
															$error_message = str_replace('%s', $fr['value'], $error_message);
														} else {
															$form_fields_array[$ki]['registered_count'] = (int) $form_fields_array[$ki]['registered_count'] + 1;
														}
													}
												}
												$validated_item = $item;
												return;
											}
										}, ARRAY_FILTER_USE_BOTH);
									}
									$still_processing = false;
									break;
								case 'switch':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$item['value'] = sanitize_text_field($item['value']);
										array_filter($form_fields_array, function ($fr) use ($item, &$validated_item, &$is_valid) {
											if (isset($fr['id_']) && isset($item['id_']) && $fr['id_'] == $item['id_']) {
												$item['value'] = $item['value'] == '1' ?   $fr['on'] : $fr['off'];
												$validated_item = $item;
												$is_valid = 1;
												return;
											}
										});
									}
									$still_processing = false;
									break;
								case 'option':
									$t = strpos(strtolower($item['type']), 'checkbox');
									if (gettype($t) != 'boolean') {
									}
									$is_valid = 0;
									if (isset($item['value'])) {

										$item['value'] = sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										if ((isset($f['id_']) && isset($item['id_ob']) && $f['id_'] == $item['id_ob'])
											|| (isset($f['id_']) && isset($item['id_']) && $f['type'] == "chlCheckBox"  && $f['id_'] == $item['id_ob'])
										) {
											$item['value'] = $this->sanitize_value_efb($item['value'],'option');
											$validated_item = $item;
											$is_valid = 1;
											$t = strpos($item['type'], 'pay');
											if (gettype($t) != 'boolean') {
												$item['price'] = $f['price'];
											}
											if ($form_condition == 'booking') {
												if (isset($f['dateExp']) == true) {
													if (strtotime($f['dateExp']) < strtotime(wp_date('Y-m-d'))) {
														$is_valid = 0;
														$error_message = $this->lanText['bkXpM_'];
														$error_message = str_replace('%s', $f['value'], $error_message);
													}
												}
												if (isset($f['mlen']) == true) {
													if ($f['mlen'] <= $f['registered_count']) {
														$is_valid = 0;
														$error_message = $this->lanText['bkFlM_'];
														$error_message = str_replace('%s', $f['value'], $error_message);
													} else {
														$form_fields_array[$key]['registered_count'] = (int) $form_fields_array[$key]['registered_count'] + 1;
													}
												}
											}
										}
									}
									$still_processing = false;
									break;
								case 'r_matrix':
									$is_valid = 0;
									$item['value'] = sanitize_text_field($item['value']);
									$item = $this->filter_attributes_by_type_efb($item,$f['type']);
									if ($item['value'] < 1 || $item['value'] > 5) {
										$m =  $this->lanText['somethingWentWrongPleaseRefresh'] . '<br>' . esc_html__('Error Code', 'easy-form-builder') . ': 600';
										$response = array('success' => false, 'm' => $m);
										wp_send_json_success($response, 200);
									}
									$is_valid = 1;
									$item['name'] = $f['value'];
									$item['label'] = "";
									foreach ($form_fields_array as $k => $v) {
										if ($v['type'] == 'table_matrix' && $v['id_'] == $item['id_']) {
											$item['label'] = $v['name'];
											break;
										}
									}
									$validated_item = $item;
									$still_processing = false;
									break;
								case 'pointr5':
									$is_valid = 0;
									if (isset($item['value']) && is_numeric($item['value'])) {
										$item['value'] = intval(sanitize_text_field($item['value']));
										$item = $this->filter_attributes_by_type_efb($item, $f['type']);

										if ($item['value'] >= 1 && $item['value'] <= 5) {
											$is_valid = 1;
											$validated_item = $item;
										}
									}
									$still_processing = false;
									break;
								case 'pointr10':
									$is_valid = 0;
									if (isset($item['value']) && is_numeric($item['value'])) {
										$item['value'] = intval(sanitize_text_field($item['value']));
										$item = $this->filter_attributes_by_type_efb($item, $f['type']);

										if ($item['value'] >= 0 && $item['value'] <= 10) {
											$is_valid = 1;
											$validated_item = $item;
										}
									}
									$still_processing = false;
									break;
								case 'rating':
									$is_valid = 0;
									if (isset($item['value']) && is_numeric($item['value'])) {
										$item['value'] = intval(sanitize_text_field($item['value']));
										$item = $this->filter_attributes_by_type_efb($item, $f['type']);

										if ($item['value'] >= 1 && $item['value'] <= 5) {
											$is_valid = 1;
											$validated_item = $item;
										}
									}
									$still_processing = false;
									break;
								case 'multiselect':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item['value'] = sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$validated_item = null;
										$rs = explode("@efb!", $item['value']);
										array_filter($form_fields_array, function ($fr) use ($item, &$validated_item, $rs) {
											foreach ($rs as $k => $v) {
												if (isset($item['type'])  && $fr['type'] == "option" && isset($fr['value']) && isset($v) && $fr['value'] == $v &&  $fr['parent'] == $item['id_']) {
													$validated_item == null ? $validated_item = $v . '@efb!' : $validated_item = $validated_item . $v . '@efb!';
												}
											}
										});
										if ($validated_item != null) $is_valid = 1;
										$item['value'] = $validated_item;
										$validated_item = $item;
									}
									$still_processing = false;
									break;
								case 'select':
								case 'paySelect':
									$is_valid = 0;
									if (isset($item['value'])) {
										$item['value'] = sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										array_filter($form_fields_array, function ($fr, $ki) use ($item, &$validated_item, &$is_valid, &$form_fields_array, $form_condition, &$error_message) {
											if (isset($item['type'])  && $fr['type'] == "option" && isset($fr['value']) && isset($item['value']) && $fr['value'] == $item['value'] &&  $fr['parent'] == $item['id_']) {
												$is_valid = 1;
												$item['value'] = $fr['value'];
												$validated_item = $item;
												$still_processing = false;
												if ($form_condition == 'booking') {
													if (isset($fr['dateExp']) == true) {
														if (strtotime($fr['dateExp']) < strtotime(wp_date('Y-m-d'))) {
															$is_valid = 0;
															$error_message = $this->lanText['bkXpM_'];
															$error_message = str_replace('%s', $fr['value'], $error_message);
														}
													}
													if (isset($fr['mlen']) == true) {
														if ($fr['mlen'] <= $fr['registered_count']) {
															$is_valid = 0;
															$error_message = $this->lanText['bkFlM_'];
															$error_message = str_replace('%s', $fr['value'], $error_message);
														} else {
															$form_fields_array[$ki]['registered_count'] = (int) $form_fields_array[$ki]['registered_count'] + 1;
														}
													}
												}
												return;
											}
										}, ARRAY_FILTER_USE_BOTH);
									}
									$still_processing = false;
									break;
								case 'stateProvince':
								case 'statePro':
								case 'conturyList':
								case 'country':
								case 'city':
								case 'cityList':
									$is_valid = 0;
									if (isset($item['value'])) {
										$is_valid = 1;
										$item['value']= sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$validated_item = $item;
									}
									$still_processing = false;
									break;
								case 'sample':
									$validated_item = $item;
									$still_processing = false;
									break;
								case 'persiaPay':
								case 'persiapay':
								case 'payment':
									if ($form_fields_array[0]['type'] == 'payment') {
										$item['amount'] = sanitize_text_field($item['amount']);
										$item['id_'] = sanitize_text_field($item['id_']);
										$item['name'] = sanitize_text_field($item['name']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);

										$validated_item = $item;
										$still_processing = false;
										$is_valid = 1;
									} else {
										$is_valid = 0;
									}
									break;
								case 'file':
								case 'dadfile':
									$d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) :'';
									$item = $this->filter_attributes_by_type_efb($item,$f['type']);
									if (isset($item['url']) && strlen($item['url']) > 5) {
										$is_valid = 0;
										$ar = ['http://wwww.' . $d, 'https://wwww.' . $d, 'http://' . $d, 'https://' . $d];
										$s = 0;
										foreach ($ar as  $r) {
											$c = strpos($item['url'], $r);
											if (gettype($c) != 'boolean' && $c == 0) {
												$s = 1;
											}
										}
										if ($s == 1) {
											$item['url'] = sanitize_url($item['url']);
											$validated_item = $item;
											$is_valid = 1;
										} else {
											$item = null;
											$validated_item = null;
											$is_valid = 0;
										}
									}
									$still_processing = false;
									break;
								case 'esign':
									$is_valid = 0;
									if (isset($item['value']) && strpos($item['value'], 'data:image/png;base64,') == 0) {
										$is_valid = 1;
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$validated_item = $item;
									}
									$still_processing = false;
									break;
								case 'maps':
									$is_valid = 1;
									$validated_item = $item;
									$c = 0;
									$item = $this->filter_attributes_by_type_efb($item,$f['type']);
									foreach ($item['value'] as $key => $value) {
										$c += 1;
										if (is_numeric($value['lat']) == false || is_numeric($value['lng']) == false) {
											$is_valid = 0;
											$validated_item = null;
										};
									}
									if ($c != $f['mark']) {
										$is_valid = 0;
										$validated_item = null;
										$error_message = $this->lanText['mnvvXXX_'];
										$error_message = str_replace('%s', "<b>" . $f['name'] . "</b>", $error_message);
									}
									$still_processing = false;
									break;
								case 'color':
									$is_valid = 0;
									$item = $this->filter_attributes_by_type_efb($item,$f['type']);
									$l = strlen($item['value']);
									if (isset($item['value']) && strpos($item['value'], '#') == 0 && $l == 7) {
										$item['value'] = sanitize_text_field($item['value']);
										$is_valid = 1;
										$validated_item = $item;
									}
									$still_processing = false;
									break;
								case 'range':
								case 'number':
								case 'prcfld':
									$is_valid = 0;
									if (isset($item['value']) && is_numeric($item['value'])) {
										$item['value'] = sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$is_valid = 1;
										$validated_item = $item;
										$l = strlen($item['value']);
										if (strcmp($f['type'], "range") !== 0 && ((isset($f['milen']) && $f['milen'] > $l) || (isset($f['mlen']) && $f['mlen'] < $l))) {
											$is_valid = 0;
										} else if (((isset($f['milen']) && $f['milen'] > $item['value']) || (isset($f['mlen']) && $f['mlen'] < $item['value'])) && strcmp($f['type'], "range") == 0) {
											$is_valid = 0;
										}
									}
									$still_processing = false;
									break;
								default:
									$is_valid = 0;
									$t	= strtolower($item['type']);
									$t = strpos(strtolower($f['type']), 'checkbox');
									$b = strpos(strtolower($f['type']), 'chlcheckbox');
									if (gettype($t) == "integer" || (isset($f['type']) && $f['type'] == 'table_matrix')) {
										$is_valid = 1;
										break;
									}
									if (isset($item['value'])) {
										$is_valid = 1;
										$item['value'] = sanitize_text_field($item['value']);
										$item = $this->filter_attributes_by_type_efb($item,$f['type']);
										$l = mb_strlen($item['value'], 'UTF-8');
										$min_len = isset($f['milen']) ? (int) $f['milen'] : 0;
										$max_len = isset($f['mlen']) ? (int) $f['mlen'] : 0;

										if ($min_len > 0 && $l < $min_len) {
											$error_message = strtr($this->lanText['ptrnMmm_'], [
												'%1$s' => "<b>" . $f['name'] . "</b>",
												'%2$s' => "<b>" . $min_len . "</b>",
											]);
											$is_valid = 0;
										} else if ($max_len > 0 && $l > $max_len) {
											$error_message = strtr($this->lanText['ptrnMmx_'], [
												'%1$s' => "<b>" . $f['name'] . "</b>",
												'%2$s' => "<b>" . $max_len . "</b>",
											]);
											$is_valid = 0;
										}
									}
									$validated_item = $item;
									$still_processing = false;
									break;
							}
						}
					});
					if (isset($validated_item)) {
						array_push($validated_items, $validated_item);
					};
				}

				$count = count($validated_items);
				if ($count == 0) {
					$is_valid = 0;
					if ($error_message == '') $error_message = $this->lanText['pleaseMakeSureAllFields'];
				}

				array_push($validated_items, ['type' => 'w_link', 'value' => $url, 'amount' => -1]);
				if($currency!=''){
				 	foreach ($validated_items as $key => $value) {
						$t=strpos($value['type'],'pay');
						if(gettype($t)!='boolean'){
							$validated_items[$key]['currency'] = $currency;
						}
					}
				}
				$this->id = $submission_type == "payment" ? sanitize_text_field($request_data['payid']) : $this->id;
				$skip_captcha = $submission_type != "payment" ? $form_fields_array[0]['captcha'] : "";
				if ($is_valid == 0) {
					$response = ['success' => false, 'm' => $error_message];
					wp_send_json_success($response, 200);
				}
				$this->value = json_encode($validated_items, JSON_UNESCAPED_UNICODE);
				$this->value = str_replace('"', '\\"', $this->value);
				if ($form_condition == 'booking') {
					$table_name = $this->db->prefix . "emsfb_form";
					$id = sanitize_text_field($request_data['id']);
					$value = json_encode($form_fields_array, JSON_UNESCAPED_UNICODE);
					$r = $this->db->update($table_name, ['form_structer' => $value], ['form_id' => $id]);
				}
			}elseif($form_type=='register' || $form_type=='login'){

				if($submission_type=='logout'){
					$this->efbFunction->efb_code_validate_update($session_id ,'logout' ,'logout' );
					wp_logout();
					$response = array( 'success' => true , 'm' =>'logout');
					wp_send_json_success($response,200);
					return;
				}
				if($submission_type=='recovery'){
					// Try to get email from original raw data or numeric array
					$raw_recovery_data = json_decode($this->value, true);
					$email = null;

					// First try associative key (original format)
					if (isset($raw_recovery_data['email'])) {
						$email = sanitize_email($raw_recovery_data['email']);
					}
					// Fallback to numeric array (after dedupe)
					elseif (isset($submitted_values[0])) {
						$email = sanitize_email($submitted_values[0]);
					}


					$response = ['success' => false, 'm' =>'Email is not valid'];
					if ($email!==null && is_email($email)) {

						$state= get_user_by( 'email', $email);

						$texts = ['imvpwsy'];
						$lanTextReg =$this->efbFunction->text_efb($texts);
						if(is_object($state)){
							$userid =(int) $state->data->ID;
							$username = $state->data->user_login;

							// Prepare recovery data - returns array with url and username
							$recovery_data = $this->fun_get_content_email_register_recovery_efb($userid, $username, $email, $this->id, 'recovery', $page_id);
							$recovery_url = $recovery_data['url'];

							$subject = esc_html__("Password recovery", 'easy-form-builder') . " [" . get_bloginfo('name') . "]";

							// Use the plugin's centralized email sender with the full recovery URL
							$pro = $this->efbFunction->is_efb_pro(1);
							$sent = $this->efbFunction->send_email_state_new(
								$email,
								$subject,
								$username,
								$pro,
								'recovery',
								$recovery_url,
								$plugin_settings
							);

							$this->efbFunction->efb_code_validate_update($session_id, 'recovery', 'recovery');
						}

						$response = array( 'success' => true, 'm' => $lanTextReg['imvpwsy']);
					}
					wp_send_json_success($response,200);

				}

			} else if ($form_structure_json == '') {
				$m = "Error 404";
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, 200);
			}

				$captcha_verification_result = "null";
				$form_admin_email = $plugin_settings['emailSupporter'] ?? null;

					if(isset($plugin_settings['smtp']) && (bool)$plugin_settings['smtp'] ){

						$should_send_email = true;
					}

					if($should_send_email && !empty($form_admin_email)){
							$this->email_list_efb($email_recipients, 0, $form_admin_email, true);
					}

					if(isset($setttting['femail']) && is_email($plugin_settings['femail'])){
						$email_recipients[2] = $plugin_settings->femail ;
					}

				$recaptcha_secret_key = isset($plugin_settings['secretKey']) && strlen($plugin_settings['secretKey']) > 5 ? $plugin_settings['secretKey'] : null;
				$d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) :'';
				$server_name = str_replace("www.", "", $d);
				$response = isset($request_data['valid']) ? sanitize_text_field($request_data['valid']) : null;

				$args = ['secret' => $recaptcha_secret_key, 'response' => $response];

				if (is_array($form_fields_array) && isset($form_fields_array[0]['type'], $form_fields_array[0]['captcha'])  && intval($form_fields_array[0]['captcha']) == 1 && $form_fields_array[0]['type'] != 'payment' && strlen($response) > 5) {

					if ($recaptcha_secret_key) {
						$verify = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$response}");
						$captcha_verification_result = json_decode($verify['body']);
					} else {
						$response = ['success' => false, 'm' => $this->lanText['errorSiteKeyM']];
						wp_send_json_success($response, 200);
						return;
					}
				}
				$shield_should_block = apply_filters('efb_submit_bot_decision', false, [
					'setting' => is_array($plugin_settings) ? $plugin_settings : [],
					'form' => (is_array($form_fields_array) && isset($form_fields_array[0]) && is_array($form_fields_array[0])) ? $form_fields_array[0] : [],
					'ip' => $this->get_ip_address(),
					'efbFunction' => $this->efbFunction,
				]);
				if ($shield_should_block === true) {
					$response = ['success' => false, 'm' => $this->lanText['errorMRobot']];
					wp_send_json_success($response, 200);
					return;
				}

				if ($submission_type == "logout" || $submission_type == "recovery") {
					$skip_captcha = true;
					if($submission_type!="recovery") $should_send_email=false;
				}
				if ( ($submission_type != "logout" && $submission_type != "recovery") && $skip_captcha && ($captcha_verification_result == "null" || $captcha_verification_result->success != true)) {

					$response = ['success' => false, 'm' => $this->lanText['errorCaptcha']];
					wp_send_json_success($response, 200);
					die();
				} else if (!$skip_captcha || ($skip_captcha &&  isset($captcha_verification_result->success) && $captcha_verification_result->success == true)) {
					if (empty($request_data['value']) || empty($request_data['name']) || empty($request_data['id'])) {
						$response = ['success' => false, "m" => $this->lanText['pleaseEnterVaildValue']];
						wp_send_json_success($response, 200);
						die();
					}
					$this->name = sanitize_text_field($request_data['name']);
					$this->id = sanitize_text_field($request_data['id']);
					if ($should_send_email) {
						array_filter($submitted_values, function ($item) use ($form_fields_array, &$user_email_address) {
							if (isset($item['id_']) && $item['id_'] == $form_fields_array[0]['email_to']) {
								$user_email_address = $item['value'];

							}
						});

						$this->email_list_efb($email_recipients, 1, $user_email_address, true);
					}
					$ip = $this->ip = $this->get_ip_address();
					$style_trackingCode = "date_en_mix";
					if (is_object($this->setting) && isset($this->setting->trackCodeStyle)) {
							$style_trackingCode = $this->setting->trackCodeStyle;
					}

					switch ($submission_type) {
						case "form":
							$track_code = $this->insert_message_db(0, false, $style_trackingCode);
							$nonce_token = wp_create_nonce($track_code);
							$this->efbFunction->efb_code_validate_update($session_id, 'send', $track_code);
							$response = ['success' => true, 'ID' => $request_data['id'], 'track' => $track_code, 'ip' => $ip, 'nonce' => $nonce_token];
							if ($redirect_url != "null") {
								$response = ['success' => true, 'm' => $redirect_url];
							}

							$this->efb_send_json_and_continue($response, 200);
							$this->efb_intgrate_with_3rd_party_services_efb($track_code, $submitted_values, $form_fields_array);

							if (isset($form_fields_array[0]['smsnoti']) && $form_fields_array[0]['smsnoti'] == 1) {
								$smsSendResult = $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers, $url, 'fform', 'wpsms', $track_code);
								if($smsSendResult !== true) {

									$m =  $this->lanText['msgSndBut'];
									$m = sprintf($m,  '<b>'.$this->lanText['smsWPN'] .'<b>' , ''.$this->lanText['trackNo'] . '(' .$track_code.')' );
									$response = ['success' => false, 'm' => $m];
									wp_send_json_success($response, 200);
								}
							}
							if ($should_send_email) {

								$this->email_list_efb($email_recipients, 0, $form_admin_email, true);
								$state_email_user = $has_tracking_code == 1 ? 'notiToUserFormFilled_TrackingCode' : 'notiToUserFormFilled';
								$msg_content = 'null';
								if (isset($form_fields_array[0]['email_noti_type']) && $form_fields_array[0]['email_noti_type'] == 'msg') {
									$msg_content = $this->email_get_content_efb($submitted_values, $track_code);
									$msg_content = str_replace("\"", "'", $msg_content);
								}
								$status_email = $this->email_status_efb($form_fields_array,$submitted_values,$track_code);
								$state_of_email = ['newMessage',$state_email_user,$status_email['type']];
								$this->send_email_Emsfb_( $email_recipients,$track_code ,$is_pro,$state_of_email,$url,$status_email['content'], $status_email['subject'] );

							}

						exit;
					break;
					case "payment":
							$id = sanitize_text_field($request_data['payid']);
							$table_name_ = $this->db->prefix . "emsfb_msg_";
							$currentDateTime = date('Y-m-d H');
							$payment_gateway = isset($request_data['payment']) ? sanitize_text_field($request_data['payment']) : 'stripe';
							if (strlen($id) < 7 && $payment_gateway == "zarinPal") {
								$response = array('success' => false, "m" => "خطای داده های پرداختی ، صفحه را رفرش کنید");
								wp_send_json_success($response, 200);
							}
							$sql = $this->db->prepare(
								"SELECT content, form_id FROM `$table_name_` WHERE track = %s AND read_ = %d",
								$id,
								2
							);

							$value = $this->db->get_results($sql);
							$payment_track_id = $id;
							if ($value != null) {
								$saved_payment_content = json_decode(str_replace('\\', '', $value[0]->content), true);
								$submitted_values = $submitted_values;
								$filtered = array_filter($submitted_values, function ($item) use ($saved_payment_content) {
									return strpos($item['type'], 'pay') === false;
								});
								$amount = array_reduce($saved_payment_content, function ($carry, $item) {
									return $carry + ($item['price'] ?? 0);
								}, 0);
								if ($payment_gateway == "persiaPay") {
									$payment_merchant_id = $plugin_settings['payToken'] ?? null;
									$data = array("merchant_id" => $payment_merchant_id, "authority" => sanitize_text_field($request_data['auth']), "amount" => $amount);
									$jsonData = json_encode($data);
									if (!is_dir(EMSFB_PLUGIN_DIRECTORY . "/vendor/persiapay/")) {
										$msg = " خطای تنظیمات : با مدیر وبسایت تماس بگیرید . نیاز به نصب مجدد درگاه می باشد";
									} else {
										include(EMSFB_PLUGIN_DIRECTORY . "/vendor/persiapay/zarinpal.php");
										$persiaPay = new zarinPalEFB();
										$result = $persiaPay->validate_payment_zarinPal($jsonData);
										$msg = $result['errors']['message'] ?? "ok";
									}
									if ($msg != "ok") {
										$response = array('success' => false, "m" => $msg);
										wp_send_json_success($response, 200);
										die();
									}
									date_default_timezone_set('Iran');
									$result = [
										'id_' => "payment",
										'name' => "payment",
										'amount' => 0,
										'total' => $amount,
										'type' => "payment",
										"paymentGateway" => $payment_gateway,
										"paymentCreated" => wp_date(__('Y/m/d \a\t g:ia', 'easy-form-builder')),
										"paymentmethod" => 'کارت',
										"paymentIntent" => sanitize_text_field($request_data['auth']),
										"paymentCard" => $result['data']['card_pan'],
										"refId" => $result['data']['ref_id'],
										"paymentcurrency" => 'IRR'
									];
								}
								$form_id = $value[0]->form_id;
								$table_name = $this->db->prefix . "emsfb_form";
								$form_structure_json = $this->db->get_results(
									$this->db->prepare(
										"SELECT form_structer, form_type FROM `$table_name` WHERE form_id = %d",
										$form_id
									)
								);
								$form_structure_json = isset($form_structure_json[0]->form_structer) ? json_decode(str_replace('\\', '', $form_structure_json[0]->form_structer), true) : '';
								if ($form_structure_json == '') {
									$response = array('success' => false, 'm' => 'Error 406');
									wp_send_json_success($response, 200);
									die();
								}
								if ($form_structure_json[0]['thank_you'] == "rdrct") {
									$redirect_url = $this->string_to_url($form_structure_json[0]['rePage']);
								}
								$validated_items = [];
								foreach ($form_structure_json as $f) {
									$it = array_filter($filtered, function ($item) use ($f) {
										return isset($f['id_'], $item['id_']) && $f['id_'] == $item['id_'] && $f['name'] == $item['name'];
									});
									$validated_items = empty($validated_items) ? $it : array_merge($validated_items, $it);
									if ($payment_gateway == "persiaPay") array_push($validated_items, $result);
								}
								$filtered = array_unique(array_merge($validated_items, $saved_payment_content), SORT_REGULAR);
								$filtered[] = array('type' => 'w_link', 'id_' => 'w_link', 'id' => 'w_link', 'value' => $url, 'amount' => -1);
								$this->value = sanitize_text_field(json_encode($filtered, JSON_UNESCAPED_UNICODE));
								$this->id = sanitize_text_field($request_data['payid']);
								$db_update_result = $this->update_message_db();
							} else {
								$response = array('success' => false, 'm' => esc_html__('Error Code', 'easy-form-builder') . '</br>' . esc_html__('Payment Form', 'easy-form-builder'));
								wp_send_json_success($response, 200);
							}
							$m = "Error 500";

							$response = $db_update_result == 1 ? array('success' => true, 'ID' => $request_data['id'], 'track' => $this->id, 'nonce' => wp_create_nonce($this->id), 'ip' => $ip) : array('success' => false, 'm' => $m);
							$this->efbFunction->efb_code_validate_update($session_id, 'pay', $payment_track_id);
							if ($redirect_url != "null" && $db_update_result == 1) {
								$response = array('success' => true, 'm' => $redirect_url);
							}

							$this->efb_send_json_and_continue($response, 200);
							$this->efb_intgrate_with_3rd_party_services_efb($payment_track_id, $submitted_values, $form_fields_array, 'payment');

							if ($should_send_email) {
								$state_email_user = $has_tracking_code==1 ? 'notiToUserFormFilled_TrackingCode' : 'notiToUserFormFilled';
								$status_email = $this->email_status_efb($form_fields_array,$validated_items,$payment_track_id);
								$state_of_email = ['newMessage',$state_email_user,$status_email['type']];
								$this->send_email_Emsfb_( $email_recipients,$payment_track_id ,$is_pro,$state_of_email,$url,$status_email['content'],$status_email['subject'] );
							}

							if (isset($form_fields_array[0]['smsnoti']) && $form_fields_array[0]['smsnoti'] == 1) {
								$smsSendResult = $this->efbFunction->sms_ready_for_send_efb($form_id, $phone_numbers, $url, 'fform', 'wpsms',$payment_track_id);
								if($smsSendResult !== true) {
								}
							}
							exit;
								break;
					case "register":
								$username = '';
								$password = '';
								$email = 'null';
								foreach ($submitted_values as &$rv) {
									if (isset($rv['id_'])) {
										switch ($rv['id_']) {
											case 'passwordRegisterEFB':
												$password = $rv['value'];
												$rv['value'] = str_repeat('*', strlen($rv['value']));
												break;
											case 'usernameRegisterEFB':
												$username = $rv['value'];
												break;
											case 'emailRegisterEFB':
												$email = $rv['value'];
												break;
										}
									}
								}
								$r = $this->new_user_validate_efb($username, $email, $password);
								if (is_string($r)) {
									$response = ['success' => false, 'm' => $r];
									wp_send_json_success($response, 200);
								}

								$creds = [
									'user_login' => esc_sql($username),
									'user_pass' => esc_sql($password),
									'user_email' => esc_sql($email),
									'role' => '',
									'rich_editing' => 'false',
									'user_registered' => wp_date('Y-m-d H:i:s')

								];
								$state = wp_insert_user($creds);
								$response;
								$m = $this->lanText['createAcountDoneM'];
								if (is_wp_error($state)) {
									foreach ($state->errors as $key => $value) {
										$m = $value[0];
									}
									$response = ['success' => false, 'm' => $m];
								} else {

									if ($email != "null") {

										$this->ip = $this->get_ip_address();
										$track_code = $this->insert_message_db(0, false, $style_trackingCode);
										$to = $email;

										$this->email_list_efb($email_recipients, 1, $email, true);

										// Prepare registration verification data
										$register_data = $this->fun_get_content_email_register_recovery_efb($state, $username, $email, $this->id, 'register', $page_id);
										$verification_url = $register_data['url'];

										$state_of_email = ['newUser', 'register'];
										$this->efbFunction->efb_code_validate_update($session_id, 'register', $track_code);
									}
									$response = ['success' => true, 'm' => $m];
									if(!is_wp_error($state) && isset($form_fields_array[0]['rePage']) && isset($form_fields_array[0]['thank_you'] ) && $form_fields_array[0]['thank_you'] == "rdrct"){
										$redirect_url = $this->string_to_url($form_fields_array[0]['rePage']);
										$response['redirect_url'] = $redirect_url;
									}
								}

								$this->efb_send_json_and_continue($response, 200);

								if (!is_wp_error($state) && $email != "null" && isset($track_code)) {
									$this->efb_intgrate_with_3rd_party_services_efb($track_code, $submitted_values, $form_fields_array, 'register');

									if ($should_send_email) {
										$msg_sub = isset($form_fields_array[0]['email_sub']) && $form_fields_array[0]['email_sub'] != '' ? $form_fields_array[0]['email_sub'] : 'null';
										// Pass username for email content and verification_url for the button link
										$this->send_email_Emsfb_($email_recipients, $username, $is_pro, $state_of_email, $verification_url, 'null', $msg_sub);
									}

									if (isset($form_fields_array[0]['smsnoti']) && $form_fields_array[0]['smsnoti'] == 1) {
										$smsSendResult = $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers, $url, 'fform', 'wpsms', $track_code);
										if($smsSendResult !== true) {
										}
									}
								}
								exit;
								break;
						case "login":

									$username = '';
									$password = '';

									foreach ($submitted_values as $value) {
										if (isset($value['id_']) && isset($value['value'])) {
											switch ($value['id_']) {
												case 'emaillogin':
													$username = $value['value'];
													break;
												case 'passwordlogin':
													$password = $value['value'];
													break;
											}
										}
									}
									$creds = [
										'user_login' => esc_sql($username),
										'user_password' => esc_sql($password),
										'remember' => true
									];
									$user = wp_signon($creds, false);
									if(is_array($form_fields_array) && isset($form_fields_array[0]['rePage']) && isset($form_fields_array[0]['thank_you'] ) && $form_fields_array[0]['thank_you'] == "rdrct"){
										$redirect_url = $this->string_to_url($form_fields_array[0]['rePage']);
									}
									if (isset($user->ID)) {
										$userID = $user->ID;
										do_action('wp_login', $creds['user_login'], $user);
										wp_set_current_user($user->ID);
										wp_set_auth_cookie($user->ID, true, false);
										$send = [
											'state' => true,
											'display_name' => $user->data->display_name,
											'user_email' => $user->data->user_email,
											'user_login' => $user->data->user_login,
											'user_nicename' => $user->data->user_nicename,
											'user_registered' => $user->data->user_registered,
											'user_image' => get_avatar_url($user->data->ID),
											'redirect_url' => $redirect_url
										];
										$response = ['success' => true, 'm' => $send];
										$this->efbFunction->efb_code_validate_update($session_id, 'login', 'login');

										$this->efb_send_json_and_continue($response, 200);
										$this->efb_intgrate_with_3rd_party_services_efb('login', $submitted_values, $form_fields_array, 'login');

										if (isset($form_fields_array[0]['smsnoti']) && $form_fields_array[0]['smsnoti'] == 1) {
											$smsSendResult = $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers, $url, 'fform', 'wpsms', '');
											if($smsSendResult !== true) {
											}
										}
										exit;
									} else {

										$send = [
											'state' => false,
											'pro' => $is_pro,
											'error' => $this->lanText['incorrectUP']
										];
										$response = ['success' => true, 'm' => $send];
										wp_send_json_success($response, 200);
									}
							break;

						case "subscribe":
									$track_code=	$this->insert_message_db(0,false,$style_trackingCode);
									$response = array( 'success' => true , 'm' =>$this->lanText['done']);
									if($redirect_url!="null"){$response = array( 'success' => true  ,'m'=>$redirect_url); }
									$this->efbFunction->efb_code_validate_update($session_id ,'nwltr' ,'nwltr' );

									$this->efb_send_json_and_continue($response, 200);
									$this->efb_intgrate_with_3rd_party_services_efb($track_code, $submitted_values, $form_fields_array, 'subscribe');

									if($should_send_email){
										$status_email = $this->email_status_efb($form_fields_array,$submitted_values,$track_code);
										$state_of_email = ['newMessage','subscribe',$status_email['type']];
										$this->send_email_Emsfb_( $email_recipients,$track_code ,$is_pro,$state_of_email,$url,$status_email['content'],$status_email['subject'] );
									}
									exit;
							break;
						case "survey":

									$track_code=	$this->insert_message_db(0,false,$style_trackingCode);
									$response = array( 'success' => true , 'm' =>$this->lanText['surveyComplatedM']);
									if($redirect_url!="null"){$response = array( 'success' => true  ,'m'=>$redirect_url); }

									$survey_chart_type = isset($form_fields_array[0]['survey_chart_type']) ? $form_fields_array[0]['survey_chart_type'] : 'none';
									$survey_results = [];
									if ($survey_chart_type !== 'none') {
										$survey_results = $this->efb_get_survey_results_data($this->id, $form_fields_array);

										if (!empty($survey_results)) {
											$response['survey_chart_type'] = $survey_chart_type;
											$response['survey_results'] = $survey_results;
											$response['survey_labels'] = [
												'title' => $this->lanText['surveyResults'] ?? 'Survey Results',
												'responses' => $this->lanText['responses'] ?? 'Responses'
											];
										}
									}
									$this->efbFunction->efb_code_validate_update($session_id ,'poll' ,'poll' );
									$this->efb_send_json_and_continue($response, 200);
									$this->efb_intgrate_with_3rd_party_services_efb($track_code, $submitted_values, $form_fields_array, 'survey');

									if($should_send_email){
										$status_email = $this->email_status_efb($form_fields_array,$submitted_values,$track_code);
										$state_of_email = ['newMessage',"survey",$status_email['type']];
										$this->send_email_Emsfb_( $email_recipients,$track_code ,$is_pro,$state_of_email,$url,$status_email['content'],$status_email['subject'] );
									}

									if(isset($form_fields_array[0]['smsnoti']) && $form_fields_array[0]['smsnoti']==1 ) {
										$smsSendResult = $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,$track_code);
										if($smsSendResult !== true) {
										}
									}
									exit;
							break;
						case "reservation":
							break;
						default:
								$response = array( 'success' => false  ,'m'=>$this->lanText['somethingWentWrongPleaseRefresh']);
								wp_send_json_success($response, 200);
							break;
					}
				}

		}else{
			$response = array( 'success' => false , "m"=>$this->lanText['errorSettingNFound']);
			wp_send_json_success($response, 200);
		}
	  }
	  public function get_track_public_api($data_POST_) {

		$data_POST = $data_POST_->get_json_params();
		$this->efbFunction = get_efbFunction();
		$text_ = ['spprt','sxnlex','error403','errorMRobot','enterVValue','guest','cCodeNFound'];
		$lanText= $this->efbFunction->text_efb($text_);

		$response = isset($data_POST['valid']) ? sanitize_text_field($data_POST['valid']) : '';
		$captcha_success =[];
		$not_captcha=true;
		$r = $this->setting != NULL && !empty($this->setting) ? $this->setting : get_setting_Emsfb('raw');
		$setting = is_string($r) ? json_decode(str_replace("\\", "", $r), true) : $r;
		if(gettype($this->setting)=="string"){
			$r=str_replace('\\', '', $this->setting);
			 $setting =json_decode($r);
			 $r=null;
		}

		 $strR = json_encode($captcha_success);
		 if (!empty($captcha_success) &&$captcha_success->success==false &&  $not_captcha==false ) {
		  $response = array( 'success' => false  , 'm'=> $lanText['errorMRobot']);
		  wp_send_json_success($response, 200);
		 }
		 else if ((!empty($captcha_success) && $captcha_success->success==true) ||  $not_captcha==true) {
			if(empty($data_POST['value']) ){
				$response = array( 'success' => false , "m"=>$lanText['enterVValue']);
				wp_send_json_success($response, 200);
				die();
			}
			$id = sanitize_text_field($data_POST['value']);
			$this->ip=$this->get_ip_address();
			$ip = $this->ip;
			if(empty($this->db)){
				global $wpdb;
				$this->db = $wpdb;
			}
			$table_name = $this->db->prefix . "emsfb_msg_";
			$value = $this->db->get_results(
				$this->db->prepare(
					"SELECT content, msg_id, track, date FROM `$table_name` WHERE track = %s",
					$id
				)
			);
			if($value!=null){
				$id=$value[0]->msg_id;
				$id = preg_replace('/[,]+/','',$id);
				$this->id =intval($id);
				$id = intval($id);
				$table_name = $this->db->prefix . "emsfb_rsp_";
				$sql = $this->db->prepare(
					"SELECT * FROM `$table_name` WHERE msg_id = %d",
					$id
				);
				$content = $this->db->get_results($sql);
				foreach($content as $key=>$val){
					$r = (int)$val->rsp_by;
					if ($r>0){
						$usr =get_user_by('id',$r);
						$val->rsp_by= $usr->display_name;
					}else if ($r==-1){
						$val->rsp_by=$lanText['spprt'];
					}else{
						$val->rsp_by=$lanText['guest'];
					}
				}
			}
			$r = false;
			$code = 'efb'.$this->id;
			$code =wp_create_nonce($code);
			if($value!=null){
				$r=true;
				$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				if ( ! empty( $value[0]->date ) ) {
					$ts = strtotime( $value[0]->date );
					if ( $ts !== false ) {
						$value[0]->date = wp_date( $date_format, $ts );
					}
				}
				foreach ( $content as $c ) {
					if ( ! empty( $c->date ) ) {
						$ts = strtotime( $c->date );
						if ( $ts !== false ) {
							$c->date = wp_date( $date_format, $ts );
						}
					}
				}
				$response = array( 'success' => true  , "value" =>$value[0] , "content"=>$content,'nonce_msg'=> $code , 'id'=>$this->id);
			}else{
				$response = array( 'success' => false  , "m" =>$lanText['cCodeNFound']);
			}
			wp_send_json_success($response, 200);
			}

	  }
	public function insert_message_db($read,$uniqid,$style_trackingCode){
		if(isset($read)==false) $read=0;

		if($uniqid==false){
			$uniqid = $this->generate_track_code_efb($style_trackingCode);
		}
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . "emsfb_msg_";
		$this->db->insert($table_name, array(
			'form_title_x' => $this->name,
			'content' => $this->value,
			'form_id' => $this->id,
			'track' => $uniqid,
			'ip' => $this->ip,
			'read_' => $read,
			'date'=>wp_date('Y-m-d H:i:s')
		));
		return $uniqid;
	}

	private function generate_track_code_efb($style = 'date_en_mix') {

		$dp = wp_date('ymd');
		$len = 5;
		$local ='';
		$en_styles = ['date_en_mix','unique_num','date_num'];
		if(!in_array($style, $en_styles)){
			$local = get_locale_script_chars_efb();
		}

		$en = str_split('ASDFGHJKLQWERTYUIOPZXCVBNM');

		switch ($style) {
			case 'date_num':
				return $dp . '-' . str_pad((string) wp_rand(10000, 99999), 5, '0', STR_PAD_LEFT);

			case 'date_local_mix':
				if (!$local) return $dp . substr(str_shuffle('0123456789ASDFGHJKLQWERTYUIOPZXCVBNM'), 0, $len);
				$ld = $local['digits'] ? strtr($dp, array_combine(range(0,9), $local['digits'])) : $dp;
				$pool = $local['alpha'];
				$pool = array_merge($pool, $local['digits'] ?: str_split('0123456789'));
				shuffle($pool);
				return $ld . implode('', array_slice($pool, 0, $len));

			case 'date_local_alpha':
				if (!$local) return $dp . substr(str_shuffle('ASDFGHJKLQWERTYUIOPZXCVBNM'), 0, $len);
				$pool = $local['alpha'];
				shuffle($pool);
				return $dp . implode('', array_slice($pool, 0, $len));

			case 'date_local_num':
				$rand = str_pad((string) wp_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
				if (!$local || !$local['digits']) return $dp . '-' . $rand;
				$ld = strtr($dp, array_combine(range(0,9), $local['digits']));
				$lr = strtr($rand, array_combine(range(0,9), $local['digits']));
				return $ld . '-' . $lr;

			case 'unique_num':
				return (string)(intval($dp) * 100000 + wp_rand(10000, 99999));

			case 'local_mix':
				if (!$local) return substr(str_shuffle('0123456789ASDFGHJKLQWERTYUIOPZXCVBNM'), 0, 11);
				$pool = array_merge($local['alpha'], $local['digits'] ?: str_split('0123456789'));
				shuffle($pool);
				return implode('', array_slice($pool, 0, 11));

			case 'date_en_mix':
			default:
				return $dp . substr(str_shuffle('0123456789ASDFGHJKLQWERTYUIOPZXCVBNM'), 0, $len);
		}
	}

	public function update_message_db(){
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . "emsfb_msg_";
		return $this->db->update( $table_name, array( 'content' => $this->value , 'read_' =>0,  'ip'=>$this->ip , 'read_date'=>wp_date('Y-m-d H:i:s') ), array( 'track' => $this->id ) );

	}
	public function get_ip_address() {

        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } else {$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }
	public function file_upload_public(){
        $_POST['id']=intval( wp_unslash( $_POST['id'] ) );
        $_POST['pl']=sanitize_text_field($_POST['pl']);
        $_POST['nonce_msg']=sanitize_text_field($_POST['nonce_msg']);
		$page_id = sanitize_text_field($_POST['page_id']);
        $vl=null;

		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{
            $id = $_POST['id'];
            $vl_data = $this->get_form_data_efb($id, array('form_structer'));
            $vl = isset($vl_data->form_structer) ? $vl_data->form_structer : null;
            if($vl!=null){
                if(strpos($vl , '\"type\":\"dadfile\"') !== false || strpos($vl , '\"type\":\"file\"') !== false){
                    $vl ='efb'.$id;
                }
            }
        }
		if (check_ajax_referer('public-nonce','nonce')!=1 && check_ajax_referer($vl,"nonce_msg")!=1){
			$response = array( 'success' => false  , 'm'=>$this->lanText['error403']);
			wp_send_json_success($response, 200);
			die();
		}
		$this->text_ = empty($this->text_)==false ? $this->text_ :['error403',"errorMRobot","errorFilePer"];
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
		$this->lanText= $this->efbFunction->text_efb($this->text_);
		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,'image/heic',
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' , 'video/mpeg', 'video/mpg', 'audio/mpg','video/mov','video/quicktime',
		 'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'
		);
		$file_type = isset($_FILES['file']['type']) ? sanitize_text_field( wp_unslash( $_FILES['file']['type'] ) ) : '';
		if (in_array($file_type, $arr_ext)) {
			$file_name_raw = isset($_FILES['file']['name']) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
			$file_tmp = isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : '';

			if (empty($file_tmp) || !is_uploaded_file($file_tmp) || !is_readable($file_tmp)) {
				$response = array( 'success' => false, 'error' => $this->lanText['errorFilePer']);
				wp_send_json_success($response, 200);
			}

			$name = 'efb-PLG-'. wp_date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($file_name_raw, PATHINFO_EXTENSION) ;

			$blocked_ext = array('php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','asp','aspx','jsp','sh','bash','bat','cmd','com','exe','dll','msi','shtml','htaccess','svg');
			$file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (in_array($file_ext, $blocked_ext)) {
				$response = array( 'success' => false, 'error' => $this->lanText['errorFilePer']);
				wp_send_json_success($response, 200);
			}

			$file_contents = file_get_contents($file_tmp);
			if ($file_contents === false) {
				$response = array( 'success' => false, 'error' => $this->lanText['errorFilePer']);
				wp_send_json_success($response, 200);
			}
			$upload = wp_upload_bits($name, null, $file_contents);
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			 $response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$file_type);
			  wp_send_json_success($response, 200);
		}else{
			$response = array( 'success' => false  ,'error'=>$this->lanText['errorFilePer']);
			wp_send_json_success($response, 200);
			die('invalid file '.$file_type);
		}
	}

	private function get_form_data_efb($form_id, $fields = array('form_structer', 'form_type')) {
		$form_id = intval($form_id);
		$cache_key = $form_id . '_' . md5(implode('_', $fields));

		if(empty($this->db)){
			global $wpdb;
			$this->db = $wpdb;
		}

		if (isset($this->form_cache[$cache_key])) {
			return $this->form_cache[$cache_key];
		}

		$cache_data = wp_cache_get('efb_form_' . $cache_key, 'emsfb');
		if ($cache_data !== false) {
			$this->form_cache[$cache_key] = $cache_data;
			return $cache_data;
		}

		$table_name = $this->db->prefix . "emsfb_form";
		$fields_str = implode(', ', array_map('esc_sql', $fields));

		$result = $this->db->get_results(
			$this->db->prepare(
				"SELECT {$fields_str} FROM `{$table_name}` WHERE form_id = %d ORDER BY form_id DESC LIMIT 1",
				$form_id
			)
		);

		if (!$result || empty($result)) {
			return null;
		}

		$this->form_cache[$cache_key] = $result[0];
		wp_cache_set('efb_form_' . $cache_key, $result[0], 'emsfb', 3600);

		return $result[0];
	}

	public function file_upload_api(){

		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
		$_POST['id']= isset($_POST['id']) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
        $_POST['pl']= isset($_POST['pl']) ? sanitize_text_field(wp_unslash($_POST['pl'])) : '';
        $fid= isset($_POST['fid']) ? intval( wp_unslash( $_POST['fid'] ) ) : 0;
		$sid = '';
		$page_id = isset($_POST['page_id']) ? sanitize_text_field(wp_unslash($_POST['page_id'])) : '';

		$this->cache_cleaner_Efb($page_id);

        $vl=null;
		$have_validate =0;
		$temp=0;
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{

            $id = isset($_POST['id']) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
            $fid = intval($fid);
            $vl_data = $this->get_form_data_efb($fid, array('form_structer'));
            $vl = isset($vl_data->form_structer) ? $vl_data->form_structer : null;
            if($vl!=null){
				if(gettype($vl)=="string"){
					$temp = (strpos($vl , '\"type\":\"dadfile\"') !== false || strpos($vl , '\"type\":\"file\"') !== false) ? true : false;
				}

                if($temp==false){

                    $response = array( 'success' => false  , 'm'=>esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder') .'<br>'. esc_html__('Error Code','easy-form-builder') . ": 601");
					wp_send_json_success($response,200);
                }

				if(strpos($vl , '\"value\":\"customize\"')!==false){
					$val_ = str_replace('\\', '', $vl);
					$vl = json_decode($val_);
					foreach($vl as $key=>$val){
						if(isset($val->id_) && $val->id_==$id && isset($val->value) && isset($val->type)){
							$have_validate=  $val->value == "customize" ? 1 : 0;
							$temp = $val->type == "dadfile" || $val->type == "file"   ? 1 : 0;
							break;
						}
					}

				}else{
					$have_validate=0;
				}

            }
        }
		$valid=false;
		$_FILES['async-upload']['name'] = sanitize_file_name( wp_unslash( $_FILES['async-upload']['name'] ) );

			$this->text_ = empty($this->text_)==false ? $this->text_ :['error403',"errorMRobot","errorFilePer"];
			$this->lanText= $this->efbFunction->text_efb($this->text_);
			if($have_validate!=1){
				$arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,'image/heic',
				'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' , 'video/mpeg', 'video/mpg', 'audio/mpg','video/mov','video/quicktime',
				'text/plain' ,
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
				'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
				'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
				'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip', 'rar', 'zip', 'tar', 'gzip', 'gz', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'mp3', 'wav', 'gif', 'png', 'jpg', 'jpeg', 'rar',
			     'gz', 'tgz', 'tar.gz', 'tar.gzip', 'tar.z', 'tar.Z', 'tar.bz2', 'tar.bz', 'tar.bzip2', 'tar.bzip', 'tbz2', 'tbz', 'bz2', 'bz', 'bzip2', 'bzip', 'tz2', 'tz', 'z', 'war', 'jar', 'ear', 'sar'

				);
				$async_file_type = isset($_FILES['async-upload']['type']) ? sanitize_text_field( wp_unslash( $_FILES['async-upload']['type'] ) ) : '';
				$valid = in_array($async_file_type, $arr_ext);
			}

		if($have_validate==1){
			if(gettype($vl)=="string"){
				$val_ = str_replace('\\', '', $vl);
				$vl = json_decode($val_);}

			foreach($vl as $key=>$val){

				if($key>1 && ($val->type=="dadfile" || $val->type=="file") && $val->id_==$_POST['id']){

					$val->file_ctype = strtolower($val->file_ctype);

					$valid_types = explode(',', str_replace(' ', '', $val->file_ctype));

					$file_name = isset($_FILES['async-upload']['name']) ? sanitize_file_name( wp_unslash( $_FILES['async-upload']['name'] ) ) : '';

					$ext = strtolower(substr($file_name, strrpos($file_name, '.') + 1));

					foreach($valid_types as $val){

						if($val==$ext){
							$valid=true;
							break;
						}
					}

					break;
				}
			}

		}

		if ($valid) {
			$async_file_name = isset($_FILES['async-upload']['name']) ? sanitize_file_name( wp_unslash( $_FILES['async-upload']['name'] ) ) : '';

			$async_file_tmp = isset($_FILES['async-upload']['tmp_name']) ? $_FILES['async-upload']['tmp_name'] : '';

			if (empty($async_file_tmp) || !is_uploaded_file($async_file_tmp) || !is_readable($async_file_tmp)) {
				$response = array( 'success' => false, 'error' => $this->lanText["errorFilePer"]);
				wp_send_json_success($response,200);
			}

			$name = 'efb-PLG-'. wp_date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($async_file_name, PATHINFO_EXTENSION) ;

			$blocked_ext = array('php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','asp','aspx','jsp','sh','bash','bat','cmd','com','exe','dll','msi','shtml','htaccess','svg');
			$file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (in_array($file_ext, $blocked_ext)) {
				$response = array( 'success' => false, 'error' => $this->lanText["errorFilePer"]);
				wp_send_json_success($response,200);
			}

			$file_contents = file_get_contents($async_file_tmp);
			if ($file_contents === false) {
				$response = array( 'success' => false, 'error' => $this->lanText["errorFilePer"]);
				wp_send_json_success($response,200);
			}
			$upload = wp_upload_bits($name, null, $file_contents);
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$async_file_type);
			  wp_send_json_success($response,200);
		}else{
			$response = array( 'success' => false  ,'error'=>$this->lanText["errorFilePer"]);
			wp_send_json_success($response,200);
			die('invalid file ' . esc_html( $async_file_type ) );
		}
	}
	public function set_rMessage_id_Emsfb_api($data_POST_) {
		$data_POST = $data_POST_->get_json_params();
		$this->text_ = empty($this->text_)==false ? $this->text_ = ['error400','atcfle','tfnapca','clcdetls','vmgs','required','mcplen','mmxplen','mxcplen','mmplen','offlineSend','settingsNfound','error405','error403','videoDownloadLink','downloadViedo','pleaseEnterVaildValue','errorSomthingWrong','nAllowedUseHtml','guest','messageSent','MMessageNSendEr',
        'youRecivedNewMessage','trackNo','WeRecivedUrM','thankFillForm','msgdml','spprt','newMessageReceived','sxnlex','msgSndBut','smsWPN']: $this->text_;
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
		$this->lanText= $this->efbFunction->text_efb($this->text_);
		$rsp_by = isset($data_POST['user_type']) ?  sanitize_text_field($data_POST['user_type']) :'guest';
		$sc = isset($data_POST['sc']) ? sanitize_text_field($data_POST['sc']) : 'null';
		$track = sanitize_text_field($data_POST['track']);

		$this->id =sanitize_text_field($data_POST['id']);
		$by ="";
		if(empty($data_POST['message']) ){
			$response = array( 'success' => false , "m"=>$this->lanText['pleaseEnterVaildValue']);
			wp_send_json_success($response,200);
		}
		if(empty($this->id) ){
			$response = array( 'success' => false , "m"=>$this->lanText['errorSomthingWrong']);
			wp_send_json_success($response,200);
		}
		if($this->isHTML($data_POST['message'])){
			$response = array( 'success' => false , "m"=>$this->lanText['nAllowedUseHtml']);
			wp_send_json_success($response,200);
		}
		$cache_plugins = get_option('emsfb_cache_plugins','0');
		if($cache_plugins!='0')$this->cache_cleaner_Efb($page_id ,$cache_plugins);
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: get_setting_Emsfb('raw');

		if(gettype($r)=="string"){
			$r =str_replace('\\', '', $r);
			$setting =json_decode($r);
			$this->setting = $setting;

			if(isset($setting->smtp) && (bool)$setting->smtp )  $email_actived = true;
			$secretKey=isset($setting->secretKey) && strlen($setting->secretKey)>5 ?$setting->secretKey:null ;
			$email = isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5 ?$setting->emailSupporter :null  ;
			$pro = intval(get_option('emsfb_pro'));
			$pro = $pro==1 || $pro == 3 ? true : false;
			$email_key = isset($setting->email_key) && strlen($setting->email_key)>5 ?$setting->email_key:null ;
			if($sc!='null' && $email_key==null){
				$response = array( 'success' => false , "m"=>$this->lanText['error400']);
				wp_send_json_success($response,200);
			}
			$response = isset($data_POST['valid']) ? sanitize_text_field($data_POST['valid']) : '';
			$id;
				$id=number_format(sanitize_text_field($data_POST['id']));
				$m=sanitize_text_field($data_POST['message']);
				$m = str_replace("\\","",$m);
				$message =json_decode($m);
				$valobj=[];
				$stated=1;
				foreach ($message as $k =>$f){
					$in_loop=true;
					if($stated==0){break;}
					if($f->id_=='captcha_v2'){

						unset($message[$k]);
						continue;

					}
						switch ($f->type) {
							case 'allformat':
								$d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) :'';

								$stated=1;
								$stated=$setting->dsupfile==false ? 0:1;
								if(isset($f->url) && strlen($f->url)>5 && ($setting->dsupfile==true)){
									$stated=0;
									$ar = ['http://wwww.'.$d , 'https://wwww.'.$d ,'http://'.$d, 'https://'.$d ];
									$s = 0 ;
									foreach ($ar as  $r) {
										$c=strpos($f->url,$r);
										if(gettype($c)!='boolean' && $c==0){
											$s=1;
										}
									}
										if($s==1 ){
											$stated=1;
											$f->url = sanitize_url($f->url);
										}else{
											$f->url="";
											$stated=0;
										}
								}
									$in_loop=false;
							break;
							default:
								$stated=0;
								if(isset($f->value) && $f->id_=="message"){
									$stated=1;
									$f->value = sanitize_text_field($f->value);
								}
								$in_loop=false;
							break;
						}
						if($stated==0){
							$response = array( 'success' => false  , 'm'=>$this->lanText['error405']);
							wp_send_json_success($response,200);
						}
				}
				$message = array_values($message);
				$m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);
				$ip =$this->ip= $this->get_ip_address();
				$id = preg_replace('/[,]+/','',$id);
				if(empty($this->db)){
					global $wpdb;
					$this->db = $wpdb;
				}

				$id = intval($id);

				$table_msg = $this->db->prefix . "emsfb_msg_";
				$sql = $this->db->prepare(
					"SELECT content, track, form_id FROM `$table_msg` WHERE msg_id = %d AND track = %s LIMIT 1",
					$id,
					$track
				);
				$value = $this->db->get_results($sql);

				if (empty($value) || !isset($value[0]) || !isset($value[0]->content)) {
					$response = array('success' => false, 'm' => esc_html__('Not allowed to respond to this message.', 'easy-form-builder') . ' E400');
					wp_send_json_success($response, 200);
				}

				$valn = str_replace('\\', '', $value[0]->content);
				$msg_obj = json_decode($valn, true);

				$vv_ = "";
				if (empty($msg_obj) || !is_array($msg_obj)) {
					$lst = null;
					$link_w = 'null';
				} else {
					$lst = end($msg_obj);
					$link_w = (is_array($lst) && isset($lst['type']) && $lst['type'] == "w_link") ? $lst['value'] : 'null';
				}
				$table_name = $this->db->prefix . "emsfb_rsp_";

				// Server-side admin verification: don't trust client user_type
				$admin_verified_rsp = false;
				if (is_user_logged_in() && current_user_can('administrator')) {
					$admin_verified_rsp = true;
					$rsp_by = 'admin';
				} else if ($sc !== 'null' && !empty($sc) && isset($this->setting->email_key) && strlen($this->setting->email_key) > 3) {
					$expected_sc_rsp = md5($track . $this->setting->email_key);
					if (hash_equals($expected_sc_rsp, $sc)) {
						$admin_verified_rsp = true;
						$rsp_by = 'admin';
					} else {
						$response = array('success' => false, 'm' => $this->lanText['error405']);
						wp_send_json_success($response, 200);
					}
				} else {
					$rsp_by = is_user_logged_in() ? 'user' : 'guest';
				}

				$read_s = $admin_verified_rsp ? 1 : 0;
				$by=$this->lanText['guest'];
				$table_emsfb_msg_ = $this->db->prefix . "emsfb_msg_";

				$exists = (int) $this->db->get_var(
					$this->db->prepare(
						"SELECT EXISTS(SELECT 1 FROM `$table_emsfb_msg_` WHERE msg_id = %d AND track = %s LIMIT 1)",
						$id,
						$track
					)
				);

				if (!$exists) {
					wp_send_json_success(
						array('success' => false, 'm' => esc_html__('Not allowed to respond to this message.' . ' E500', 'easy-form-builder')),
						200
					);
				}
				if($read_s==1){
					if($this->efb_uid > 0) {
						$by = get_user_by('id',$this->efb_uid);
					} else {
						$this->efb_uid = -1;
						$by = $this->lanText['spprt'];
					}
				}
				$this->db->insert($table_name, array(
					'ip' => $ip,
					'content' => $m,
					'msg_id' => $id,
					'rsp_by' => $this->efb_uid,
					'read_' => $read_s,
					'date'=>wp_date('Y-m-d H:i:s'),
				));

				$track = isset($value[0]->track) ? $value[0]->track : null;
				if (empty($track)) {
					$response = array('success' => false, 'm' => 'Track not found');
					wp_send_json_success($response, 200);
				}

				$table_name = $this->db->prefix . "emsfb_msg_";
				$this->db->update($table_name,array('read_'=>$read_s), array('msg_id' => $id) );
				$email_usr ="";
				if($this->efb_uid!=0 && $this->efb_uid!==-1){
					$usr= wp_get_current_user();
					$by = $usr->user_nicename;
					$email_usr = $usr->user_email;
				}

				$form_id = isset($value[0]->form_id) ? intval($value[0]->form_id) : 0;
				if (empty($form_id)) {
					$response = array('success' => false, 'm' => 'Form ID not found');
					wp_send_json_success($response, 200);
				}
				$table_name = $this->db->prefix . "emsfb_form";
				$vald = $this->db->get_results(
					$this->db->prepare(
						"SELECT form_structer ,form_type FROM `$table_name` WHERE form_id = %d",
						$form_id
					)
				);
				$valb =str_replace('\\', '', $vald[0]->form_structer);
				$valn= json_decode($valb,true);
				$usr;
				$email_noti_ids=[] ;
				$users_email =array();
				$valb=null;
				$email_to = isset($valn[0]["email_to"]) ? $valn[0]["email_to"] : '';
				$emailsId = [];
				if($email_actived){
						foreach($valn as $key=>$val){
							if($val['type']=="email" && isset($val['noti']) && in_array($val['noti'] ,[1,'1',true,'true'],true) ){
								$emailsId[]=$val['id_'];
							}else if ($val['type']=="email" &&  $val['id_']==$email_to ){
								$emailsId[]=$val['id_'];
							}
						}
						if(!empty($emailsId)){
							foreach ($msg_obj as $value) {
								if(isset($value['id_']) && in_array($value['id_'],$emailsId)){
									array_push($users_email,$value["value"]);
								}
							}
						}

				}
				$smsnoti = (isset($valn[0]['smsnoti']) && intval($valn[0]['smsnoti'])==1) ? 1 :0;
				if($smsnoti){
					$phone_numbers=[[],[]];
					if(isset($setting->sms_config) && isset($setting->phnNo) && strlen($setting->phnNo)>5){
						$phone_numbers[0] =explode(',',$setting->phnNo);
					}
					$have_noti_id=[];

					foreach($valn as $val){
						if($val['type']=="mobile" && isset($val['smsnoti']) && intval($val['smsnoti'])==1){
							array_push($have_noti_id,$val->id_);
						}
					}

					if(!empty($have_noti_id)){
						foreach ($msg_obj as $value) {
							if($value['type']=="mobile" && in_array($value['id_'],$have_noti_id)){
								array_push($phone_numbers[1],$value['value']);
							}
						}
					}
				$tt = $rsp_by=='admin' ? 'respadmin' : 'resppa';
				if(isset($setting->sms_config) && ($setting->sms_config=="wpsms" || $setting->sms_config=='ws.team') ) {
					$smsSendResult = $efbFunction->sms_ready_for_send_efb($form_id, $phone_numbers,$link_w,$tt ,$setting->sms_config ,$track);
					if($smsSendResult !== true) {
						$m =  $this->lanText['msgSndBut'];
						$m = sprintf($m,  '<b>'.$this->lanText['smsWPN'] .'<b>' , '' );
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, 200);
					}
				}

				}
				$user_eamil=[[],[],null];
				if (isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5){
					$this->email_list_efb($user_eamil , 0 , $setting->emailSupporter ,true);
				}
				if(isset($setting->femail)) $user_eamil[2]=$setting->femail;
				$email_fa = $valn[0]['email'];
				if (isset($email_fa) && strlen($email_fa)>5){
					$this->email_list_efb($user_eamil , 0 , $email_fa ,true);
				}
				$links=$link_w;
				$email_status =["",""];
			    !empty($users_email) ? $user_eamil[1]= $users_email : 0;
				if($rsp_by=='admin'){
					$email_status[1]= "newMessage";
					$email_status[0] ='respRecivedMessage';
					$user_eamil[0]=[null];
				}else{
					$email_status[1]= "respRecivedMessage";
					$email_status[0] ='newMessage';
				}
				if(isset($setting->smtp) && (bool)$setting->smtp ) $this->send_email_Emsfb_($user_eamil,$track,$pro,$email_status,$links ,'null','null');

				$reply_event_type = ($rsp_by == 'admin') ? 'admin_reply' : 'received_reply';
				$this->id = $form_id;
				$this->efb_intgrate_with_3rd_party_services_efb($track, $valobj ?? [], $valn, $reply_event_type);

				$response = array(
				'success' => true , "m"=>$this->lanText['messageSent'] , "by"=>$by,
				'track'=>$track,
				'nonce_msg'=>wp_create_nonce($track));
				wp_send_json_success($response,200);
		}else{
			$m = $this->lanText['settingsNfound'] . '</br>' . $this->lanText['MMessageNSendEr'] ;
			$response = array( 'success' => false , "m"=>$m, "by"=>$by);
			wp_send_json_success($response,200);
		}
	}

	public function get_autofilled_list_efb($data_POST_) {
		$data_POST = $data_POST_->get_json_params();
		$fid = sanitize_text_field($data_POST['id']);
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
		$sid = sanitize_text_field($data_POST['sid']);
		$s_sid = $this->efbFunction->efb_code_validate_select($sid, $fid);
		if ($s_sid !=1 || $sid==null){
			$this->efbFunction->send_email_noti_sid_plugins_efb;('replyMessageAction');
			$m = $this->lanText['sxnlex'];
			$response = array( 'success' => false  , 'm'=>$m );
			wp_send_json_success($response,200);
		}
		$page_id = sanitize_text_field($data_POST['page_id']);
		$cache_plugins = get_option('emsfb_cache_plugins','0');
		if ($cache_plugins != '0') $this->cache_cleaner_Efb($page_id, $cache_plugins);

		$path =  EMSFB_PLUGIN_DIRECTORY . 'vendor/autofill/autofillefb.php';
		$path_exists = file_exists($path);
		if($path_exists){

			require_once $path;
			$autofill = new autofillefb();
		}else{
			$response = array('success' => false, 'm' => 'autofilled add-on not found');
			wp_send_json_success($response, 200);
		}

		$autofill->get_autofill_api_efb($data_POST);
	}
	public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content = 'null', $sub = 'null') {
		$homeUrl = home_url();
		$blogName = get_bloginfo('name');
		$micr = microtime(true);

		$link_w = ['',''];
		$cont = ['',''];
		$subject = ['',''];
		$message = ['',''];

		$micr = microtime(true);
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();

    $modern_button_template = "
        <!--[if mso]>
        <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word' href='%s' style='height:50px;v-text-anchor:middle;width:220px;' arcsize='12%%' strokecolor='#202a8d' fillcolor='#202a8d'>
            <w:anchorlock/>
            <center style='color:#ffffff;font-family:sans-serif;font-size:18px;font-weight:bold;'>%s</center>
        </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-->
        <div style='text-align:center; margin: 30px 0;'>
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' style='margin: 0 auto;'>
                <tr>
                    <td style='background: linear-gradient(135deg, #202a8d 0%%, #1e3a8a 100%%); border-radius: 8px; text-align: center; box-shadow: 0 4px 15px rgba(32, 42, 141, 0.3);'>
                        <a href='%s' target='_blank' style='display: inline-block; padding: 16px 32px; background: transparent; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 18px; line-height: 1; text-align: center; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, Arial, sans-serif; border: none; cursor: pointer;'>
                            %s
                        </a>
                    </td>
                </tr>
            </table>
        </div>
        <!--<![endif]-->
    ";
    $default_message = "<h2>%s</h2>" . $modern_button_template;

    $dt = str_replace('%s', $track, $this->lanText['msgdml']);
    $thankFillForm = $this->lanText['thankFillForm'];
    $trackNo = $this->lanText['trackNo'];
    $vmgs = $this->lanText['vmgs'];
    $weRecivedUrM = $this->lanText['WeRecivedUrM'];
    $thankRegistering = $this->lanText['thankRegistering'];
    $welcome = $this->lanText['welcome'];
    $thankSubscribing = $this->lanText['thankSubscribing'];
    $thankDonePoll = $this->lanText['thankDonePoll'];
    $newUserRegistration = esc_html__('New user registration', 'easy-form-builder');
	$newMassageReciver = $this->lanText['newMessageReceived'];

    for ($i = 0; $i < 2; $i++) {
		if(strlen($link)>5){

			$isRegistrationState = in_array($state[$i], ['newUser', 'register']) || (isset($state[0]) && $state[0] === 'newUser');
			$trackParam = $isRegistrationState ? '' : urlencode($track);
			$link_w[$i] = strpos($link,'?')!=false ? $link . ($trackParam ? '&track='.$trackParam : '') : $link . ($trackParam ? '?track='.$trackParam : '');
			if($i==0 && !$isRegistrationState){
				$sc = $this->genrate_sacure_code_admin_email($track);
				$link_w[$i] .= (strpos($link_w[$i],'?')!==false ? '&' : '?') . 'sc='.$sc;
			}
		}else{
			$link_w[$i] = $homeUrl;
		}

        $cont[$i] = $track;

        $will_have_custom_content = ($content != "null" && $i < 2);
        $isRegistrationState = in_array($state[$i], ['newUser', 'register']);

        switch ($state[$i]) {
			case "newMessage":
				$subject[$i] = $this->lanText['youRecivedNewMessage'] .' ['.$track.']';

				$message[$i] = "<h2>$newMassageReciver</h2><p>$trackNo:<br> $track </p><p>$dt </p>";
				break;
            case "notiToUserFormFilled_TrackingCode":
                $subject[$i] = $weRecivedUrM;

                $message[$i] = "<h2>$thankFillForm</h2><p>$trackNo:<br> $track </p><p>$dt </p>";
                break;
            case "notiToUserFormFilled":
                $subject[$i] = $weRecivedUrM;
                $message[$i] = sprintf($default_message, $thankFillForm, $homeUrl, $blogName, $homeUrl, $blogName);
                break;
            case "respRecivedMessage":
                $subject[$i] = "$weRecivedUrM [$track]";

                $message[$i] = "<h2>$weRecivedUrM</h2><p>$trackNo:<br> $track </p><p>$dt </p>";
                break;
            case "register":
                $subject[$i] = $thankRegistering;
                // Don't generate message here - let email_template_efb handle it with generate_register_content
                $message[$i] = $track; // Pass username, email_template_efb will generate the content
                break;
            case "subscribe":
            case "survey":
                $subject[$i] = $welcome;
                $message[$i] = sprintf($default_message, ($state[$i] == "subscribe") ? $thankSubscribing : $thankDonePoll, $homeUrl, $blogName, $homeUrl, $blogName);
                break;
            case "newUser":
                $subject[$i] = $newUserRegistration;
                // Don't generate message here - let email_template_efb handle it with generate_register_content
                $message[$i] = $track; // Pass username, email_template_efb will generate the content
                break;
        }
        // For registration states, keep username as content for email_template_efb
        $cont[$i] = $isRegistrationState ? $track : $message[$i];
        if ($content != "null") {
            $cont[$i] = [$track, $content];
        }
        if ($sub != "null") {
            $rp = [
                '[confirmation_code]' => $track,
                '[link_page]' => $link_w[$i],
                '[link_domain]' => get_site_url(),
                '[link_response]' => $link_w[$i],
                '[website_name]' => get_bloginfo('name')
            ];
            $subject[$i] = strtr($sub, $rp);
        }
    }


    $check = $this->efbFunction->send_email_state_new($to, $subject, $cont, $pro, $state, $link_w, $this->setting);

	}
	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function pay_stripe_sub_Emsfb_api($data_POST_) {
		$data_POST = $data_POST_->get_json_params();
		$user = wp_get_current_user();
		$uid= $user->exists() ? $user->user_nicename :  esc_html__('Guest','easy-form-builder') ;
		$this->id =sanitize_text_field($data_POST['id']);
		if($this->efbFunction===null) $this->efbFunction = get_efbFunction();

		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  get_setting_Emsfb('raw');
		$Sk ='null';
		if(gettype($r)=="string"){
			$setting =str_replace('\\', '', $r);
			$setting =json_decode($setting);
			$Sk = isset($setting->stripeSKey) && strlen($setting->stripeSKey)>5  ? $setting->stripeSKey :'null';
		}
		if ($Sk=="null"){
				$m = esc_html__('Stripe', 'easy-form-builder').'->'.	esc_html__('error', 'easy-form-builder') . ' 402';
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, 200);
				die("secure!");
		}
		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe")) {
			 $efbFunction->download_all_addons_efb();
			 return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have made some updates. Please wait a few minutes before trying again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
		}
		require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/autoload.php");
		$this->id = intval($data_POST['id']);
		$val_ = sanitize_text_field($data_POST['value']);
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results(
			$this->db->prepare(
				"SELECT form_structer ,form_type FROM `$table_name` WHERE form_id = %d",
				$this->id
			)
		);
		$fs =str_replace('\\', '', $value_form[0]->form_structer);
		$fs_ = json_decode($fs,true);
		$val =str_replace('\\', '', $val_);
		$val_ = json_decode($val,true);
		$paymentmethod = $fs_[0]['paymentmethod'];
		$price_c =0;
		$price_f=0;
		$email ='';
		$valobj=[];
		$obj = $this->fun_validation_pay_elements_efb($val_ , $fs_);

		$price_f = $obj['price_total'];
		$email = $obj['email'];
		$valobj = $obj['valobj'];
		$price_f = $price_f*100;
		$description =  get_bloginfo('name') . ' >' . $fs_[0]['formName'];
		if($price_f>0){
			$currency= $fs_[0]['currency'] ;

			$stripe = new \Stripe\StripeClient($Sk);
			$newPay = [
				'amount' => $price_f,
				'currency' => $currency,
				'payment_method_types' =>['card'],
				'description' =>$description,
			];
			 $subPay;
			 $amount;
			 $paymentIntent;
			 $amount;$created;$val ;
			 if($paymentmethod=='charge'){
				if(strlen($email)>1){$newPay=array_merge($newPay , array('receipt_email'=>$email));}
				$paymentIntent = $stripe->paymentIntents->create($newPay);
				$amount = $paymentIntent->amount/100;
				$created= date("Y-m-d-h:i:s",$paymentIntent->created);
				$val = $paymentIntent->amount/100 . ' ' . $paymentIntent->currency;
			}else{
				$token= sanitize_text_field($data_POST['token']);

				$product = $stripe->products->create([
					'name' => $description,
					]);

					$price= $stripe->prices->create([
						'unit_amount' => $price_f,
						'currency' => $currency,
						'recurring' => ['interval' => $paymentmethod],
						'product' => $product->id,
					]);
					$customerData= [
						'description' => $description,
						'source'=>$token,
					];
					if(strlen($email)>1){$customerData=array_merge($customerData , array('email'=>$email));}
					$customer =$stripe->customers->create($customerData);
					  $paymentIntent =	$stripe->subscriptions->create([
						'customer' => $customer,
						'items' => [
						  ['price' => $price],
						],
					  ]);
					  $amount = $paymentIntent->plan->amount/100;
					  $created= date("Y-m-d-h:i:s",$paymentIntent->created);
					  $val =  $amount . ' ' . $paymentIntent->currency;
			}
			$filtered = array_filter($valobj, function($item) {
				if(isset($item['price']))	return $item;
			});
			$created= date("Y-m-d-h:i:s",$paymentIntent->created);
			$response;
			if($paymentmethod!='charge'){
				$amount = $price->unit_amount/100;
				$payA =  $amount  . ' '. $price->currency;
				$nextdate = date("Y-m-d-h:i:s",$paymentIntent->current_period_end);
				$ar = (object)['id_'=>'payment','amount'=>0,'name'=> esc_html__('Payment','easy-form-builder') ,'type'=>'payment',
				'value'=> $payA , 'paymentIntent'=>$paymentIntent->id , 'paymentGateway'=>'stripe' ,
				'paymentAmount'=>$amount,'paymentCreated'=>$created ,'paymentcurrency' =>$price->currency, 'gateway'=>'stripe',
				'interval'=>$paymentIntent->plan->interval,'nextDate'=> $nextdate, 'paymentmethod'=>$paymentmethod
				,'uid'=>$uid ,'status'=>'active' ,'updatetime'=>$created , 'description'=>$description,'total'=>$amount ];
				 $filtered=array_merge($filtered , array($ar));
				$response = array( 'success' => true  ,  'transStat'=>$ar , 'uid'=> $uid);
			}else{
				$amount = $paymentIntent->amount/100;
				$payA =  $amount  . ' '. $paymentIntent->currency;
				$ar = (object)['id_'=>'payment','amount'=>0,'name'=> esc_html__('Payment','easy-form-builder') ,'type'=>'payment',
				'value'=> $payA , 'paymentIntent'=>$paymentIntent->id , 'paymentGateway'=>'stripe' , 'paymentmethod'=>$paymentmethod,
				'paymentAmount'=>$amount ,'paymentCreated'=>$created ,'paymentcurrency' =>$paymentIntent->currency , 'gateway'=>'stripe'
				,'uid'=>$uid ,'status'=>'active','updatetime'=>$created,'description'=>$description,'total'=>$amount ];
				 $filtered=array_merge($filtered , array($ar));
				$response = array( 'success' => true  , 'client_secret'=>$paymentIntent->client_secret ,'transStat'=>$ar, 'uid'=> $uid);
			}

			$this->ip=$this->get_ip_address();
			$ip = $this->ip;
			$val_ = json_encode($filtered ,JSON_UNESCAPED_UNICODE);
			$this->value = str_replace('"', '\\"', $val_);
			$this->name = sanitize_text_field($data_POST['name']);
			$style_trackingCode = 'date_en_mix';
			if (is_object($this->setting) && isset($this->setting->trackCodeStyle)) {
				$style_trackingCode = $this->setting->trackCodeStyle;
			}
			$check=	$this->insert_message_db(2,false,$style_trackingCode);

			$stripe_payment_file = EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php';
			if ( ! class_exists( '\Emsfb\StripePayment' ) && file_exists( $stripe_payment_file ) ) {
				require_once $stripe_payment_file;
			}
			if ( class_exists( '\Emsfb\StripePayment' ) ) {
				$pay_data = [
					'form_id'        => (int) $this->id,
					'track'          => $check,
					'gateway'        => 'stripe',
					'amount'         => (float) $amount,
					'currency'       => strtoupper( $paymentmethod !== 'charge' ? $price->currency : $paymentIntent->currency ),
					'payer_email'    => $email,
					'uid'            => $uid,
					'ip'             => $this->ip,
					'form_name'      => isset( $fs_[0]['formName'] ) ? $fs_[0]['formName'] : '',
				];
				if ( $paymentmethod !== 'charge' ) {

					$pay_data['payment_type']    = 'subscription';
					$pay_data['transaction_id']  = $paymentIntent->id;
					$pay_data['subscription_id'] = $paymentIntent->id;
					$pay_data['plan_id']         = $price->id ?? '';
					$pay_data['product_id']      = $product->id ?? '';
					$pay_data['status']          = 'active';
					$pay_data['interval_unit']   = strtoupper( $paymentmethod );
				} else {

					$pay_data['payment_type']    = 'one-time';
					$pay_data['transaction_id']  = $paymentIntent->id;
					$pay_data['status']          = 'pending';
				}
				StripePayment::insert_payment( $pay_data );
			}

			$response=array_merge($response , ['id'=>$check]);
			wp_send_json_success($response, 200);
		}else{
			$msg = esc_html__('No payment amount detected. Please review your selected items and try again. If the problem persists, contact support.', 'easy-form-builder');
			$response = array( 'success' => false  , 'm'=>$msg);
			wp_send_json_success($response, 200);
		}
	}

	public function pay_stripe_confirm_Emsfb_api( $request ) {
		$data_POST       = $request->get_json_params();
		$payment_intent  = sanitize_text_field( $data_POST['paymentIntentId'] ?? '' );
		$trackid         = sanitize_text_field( $data_POST['trackid'] ?? '' );

		if ( empty( $payment_intent ) ) {
			wp_send_json_success( [ 'success' => false, 'm' => esc_html__( 'Payment Intent ID is missing', 'easy-form-builder' ) ], 400 );
			return;
		}

		$stripe_payment_file = EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php';
		if ( ! class_exists( '\Emsfb\StripePayment' ) && file_exists( $stripe_payment_file ) ) {
			require_once $stripe_payment_file;
		}

		if ( ! class_exists( '\Emsfb\StripePayment' ) ) {
			wp_send_json_success( [ 'success' => false, 'm' => 'Stripe payment class not available' ], 500 );
			return;
		}

		global $wpdb;
		$pay_table = $wpdb->prefix . 'emsfb_pay_';

		$pay_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, status FROM {$pay_table} WHERE transaction_id = %s AND gateway = 'stripe' LIMIT 1",
			$payment_intent
		) );

		if ( ! $pay_row ) {

			if ( ! empty( $trackid ) ) {
				$pay_row = $wpdb->get_row( $wpdb->prepare(
					"SELECT id, status FROM {$pay_table} WHERE track = %s AND gateway = 'stripe' LIMIT 1",
					$trackid
				) );
			}
		}

		if ( $pay_row && $pay_row->status === 'pending' ) {
			StripePayment::update_payment( $pay_row->id, [
				'status'     => 'completed',
				'capture_id' => $payment_intent,
			] );
		}

		if ( ! empty( $trackid ) ) {
			if ( empty( $this->db ) ) {
				global $wpdb;
				$this->db = $wpdb;
			}
			$table_name = $this->db->prefix . 'emsfb';
			$this->db->update(
				$table_name,
				[ 'status' => 1 ],
				[ 'tracking' => $trackid ]
			);
		}

		wp_send_json_success( [
			'success'         => true,
			'paymentIntentId' => $payment_intent,
			'trackid'         => $trackid,
		], 200 );
	}

	public function pay_persia_sub_Emsfb_api($data_POST_){

		require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/zarinpal.php");
		$persiapay = new zarinPalEFB() ;
		if(gettype($persiapay)=="object"){
			$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  get_setting_Emsfb('raw');
			$persiapay->pay_persia_sub_Emsfb_api($data_POST_ ,$this);
		}else{
			$m = esc_html__('persiaPayment', 'easy-form-builder').'->'.	esc_html__('error', 'easy-form-builder') . ' 406';
			$response = ['success' => false, 'm' => $m];
			wp_send_json_success($response, 200);
		}

	}

	public function string_to_url($string) {
			$rePage= preg_replace('/(http:@efb@)+/','http://',$string);
			$rePage= preg_replace('/(https:@efb@)+/','https://',$rePage);
			$rePage =preg_replace('/(@efb@)+/','/',$rePage);
		return $rePage;
	}
	public function new_user_validate_efb($username,$email,$password){
		if(!is_email($email)){
			return esc_html__("The Email Address Is Not Valid" , 'easy-form-builder');
		}
		 if(preg_match('/^[a-z0-9._]*$/',$username)!=true ){
			return esc_html__("The Username Must Contain Only Letters, Numbers And Lowercase letters" , 'easy-form-builder');
		}else if(strlen($username)<3){
			return esc_html__("The Username Must Contain At Least 3 Characters." , 'easy-form-builder');
		}
		if (strlen($password) <  8) {
			return esc_html__("The Password Must Contain At Least 8 Characters!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[0-9]+#",$password)) {
			return esc_html__("The Password Must Contain At Least 1 Number!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[A-Z]+#",$password)) {
			return esc_html__("The Password Must Contain At Least 1 Capital Letter!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[a-z]+#",$password)) {
			return  esc_html__("The Password Must Contain At Least 1 Lowercase Letter!" , 'easy-form-builder');
		}
		return 0;
	}
	public function test_fun($data_POST_){
		$data_POST = $data_POST_->get_json_params();
        $response = array(
            'success' => true,
            'value' => $slug['name'],
            'content' => "content",
            'nonce_msg' => "code",
            'id' => $slug['id']
          );
        return new WP_REST_Response($response, 200);

    }
	public function replaceContentMessageEfb($value) {
		$value = preg_replace('/[\\\\]/', '', $value);
		$value = preg_replace('/(\\"|"\\\\)/', '"', $value);
		$value = preg_replace('/(\\\\\\\\n|\\\\\\\\r)/', '<br>', $value);
		$value = str_replace('@efb@sq#', "'", $value);

		$value = str_replace('@efb@vq#', "`", $value);
		$value = str_replace('@efb@dq#', "''", $value);
		$value = str_replace('@efb@nq#', "<br>", $value);
		return $value;
	}
	public function email_get_content_efb($content, $track){
		$m  = '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="container containerEmailEfb" >';

			$text_     = ['msgemlmp','paymentCreated','videoDownloadLink','downloadViedo','payment','id','payAmount','ddate','updated','methodPayment','interval'];
			$list      = [];
			$checboxs  = [];
			$total_amount = 0;

			$lst    = end($content);
			$link_w = (isset($lst['type']) && $lst['type']==="w_link") ? ($lst['value'] ?? '') : '';
			if (strlen($link_w)>5){
				$link_w = (strpos($link_w,'?')!==false) ? ($link_w.'&track='.$track) : ($link_w.'?track='.$track);
			} else {
				$link_w = home_url();
			}

			$currency = (isset($content[0]['paymentcurrency'])) ? $content[0]['paymentcurrency'] : 'usd';

			if($this->efbFunction===null) $this->efbFunction = get_efbFunction();
			$lanText = $this->efbFunction->text_efb($text_);

			usort($content, function($a,$b){
				$aa = isset($a['amount']) ? $a['amount'] : 0;
				$bb = isset($b['amount']) ? $b['amount'] : 0;
				return $aa <=> $bb;
			});

			$addPair = function($title, $value) use (&$m){
				$title = $this->efbFunction->ensure_trailing_colon_efb($title);
				if($title==='' && $value===''){ return; }
				$m .= '<tr>';
				$m .= '<td valign="top" width="50%" class="columnEmailEfb" style="padding:5px; line-height:20px;">';
				$m .= '<p style="margin:0 0 10px 0;font-weight: bold;font-size:16px;">'.$title.'</p>';
				$m .= '</td>';
				$m .= '<td valign="top" width="50%" class="columnEmailEfb" style="padding:5px; line-height:20px;">';
				$m .= '<p style="margin:0 0 10px 0;font-size:14px;">'.$value.'</p>';
				$m .= '</td>';
				$m .= '</tr>';

			};

			foreach ($content as $c){

				if (isset($c['type']) && $c['type']==="w_link"){ continue; }

				if (isset($c['currency'])) { $currency = $c['currency']; }

				if (isset($c['value']) && $c['type']!=="maps") {
					$c['value'] = $this->replaceContentMessageEfb($c['value']);
				}
				if (isset($c['qty'])) {
					$c['qty']  = $this->replaceContentMessageEfb($c['qty']);
				}

				$title = isset($c['name']) ? $c['name'] : '';
				$q     = '';

				if (isset($c['value']) && is_string($c['value'])) {
					$q = str_replace('@efb!', ',', $c['value']);
					$q = str_replace('@n#', '<br>', $q);
					if ($q !== '@file@') { $q = '<b>'.$q.'</b>'; }
				}
				if (isset($c['qty'])) {
					$q .= ($q ? ' ' : '') . ': <b>'.$c['qty'].'</b>';
				}

				if (isset($c['value']) && $c['value']==='@file@' && !in_array(($c['url'] ?? ''), $list)) {
					$url = $c['url'] ?? '';
					$nm  = $c['name'] ?? (substr($url, strrpos($url,'/')+1));
					$t   = strtolower($c['type'] ?? '');

					$list[] = $url;

					if ($t==='image') {
						$q = '<img src="'.$url.'" alt="'.htmlspecialchars($nm).'" style="display:block;max-width:100%;height:auto;border:0;">';
					} elseif ($t==='document' || $t==='allformat') {
						$q = '<a href="'.$url.'" target="_blank" style="text-decoration:none;">'.$nm.'</a>';
					} elseif ($t==='media') {

						$audios = ['mp3','wav','ogg'];
						$isAudio = false;
						foreach($audios as $a){ if(strpos($url,$a)!==false){ $isAudio=true; break; } }
						if ($isAudio){
							$q = '<a href="'.$url.'" target="_blank" style="text-decoration:none;">'.$nm.'</a>';
						} else {
							$q = '<a href="'.$url.'" target="_blank" style="text-decoration:none;">'.$lanText['videoDownloadLink'].'</a>';
						}
					} else {
						$q = strlen($url)>1 ? '<a href="'.$url.'" target="_blank" style="text-decoration:none;">'.$nm.'</a>' : '<span>💤</span>';
					}
					$addPair($title ?: 'file', $q);
					continue;
				}

				if (isset($c['type']) && $c['type']==='esign'){
					$q = '<img src="'.($c['value'] ?? '').'" alt="'.htmlspecialchars($title).'" style="display:block;max-width:100%;height:auto;border:0;">';
					$addPair($title, $q);
					continue;
				}

				if (isset($c['type']) && $c['type']==='color'){
					$q = '<span style="display:inline-block;width:50px;height:20px;vertical-align:middle;background:'.($c['value'] ?? '#000').'"></span> '
					. '<span style="vertical-align:middle;">'.($c['value'] ?? '').'</span>';
					$addPair($title, $q);
					continue;
				}

				if (isset($c['type']) && $c['type']==='maps'){
					if (is_array($c['value'] ?? null)){
						$q = '<a href="'.$link_w.'" style="text-decoration:none;">'.$lanText['msgemlmp'].'</a>';
						$addPair($title ?: 'Location', $q);
					}
					continue;
				}

				if (isset($c['type']) && $c['type']==='rating'){
					$stars = intval($c['value'] ?? 0);
					$q = str_repeat('⭐', $stars);
					$addPair($title ?: 'Rating', $q);
					continue;
				}

				if (isset($c['type']) && ($c['type']==='payCheckbox' || $c['type']==='payRadio')){
					$price = intval($c['price'] ?? 0);
					$total_amount += $price;
					$numberformat = $this->formatPrice_efb(number_format($price,0,'.',','), $currency);
					$addPair($c['name'] ?? 'Item', '<b>'.$numberformat.'</b>');
					$checboxs[] = $c['id_'] ?? '';
					continue;
				}

				if (isset($c['type']) && $c['type']==='prcfld'){
					$numberformat = $this->formatPrice_efb(number_format(intval($c['price'] ?? 0),0,'.',','), $currency);
					$addPair($c['name'] ?? 'Price', '<b>'.$numberformat.'</b>');
					continue;
				}

				if (isset($c['type']) && $c['type']==='r_matrix' && !in_array(($c['id_'] ?? ''), $checboxs)){
					$checboxs[] = $c['id_'] ?? '';
					$vals = [];
					foreach($content as $op){
						if (($op['type'] ?? '')==='r_matrix' && ($op['id_'] ?? '')===($c['id_'] ?? '')){
							$vals[] = '<b>'.($op['value'] ?? '').'</b>';
						}
					}
					$addPair($title ?: 'Options', implode('<br>', $vals));
					continue;
				}

				if (isset($c['type']) && $c['type']==='payment'){
					if (($c['paymentGateway'] ?? '')==='stripe'){
						$numberformat = $this->formatPrice_efb(number_format(intval($c['paymentAmount'] ?? 0),0,'.',','), ($c['paymentcurrency'] ?? $currency));
						$addPair($lanText['payment'].' '.$lanText['id'], '<span>'.($c['paymentIntent'] ?? '').'</span>');
						$addPair($lanText['methodPayment'], '<span>'.($c['paymentmethod'] ?? '').'</span>');
						if (($c['paymentmethod'] ?? '')!=='charge'){
							$addPair($lanText['interval'], '<span>'.($c['interval'] ?? '').'</span>');
						}
						$addPair($lanText['payAmount'], '<span>'.$numberformat.'</span>');
						$addPair($lanText['ddate'], '<span>'.($c['paymentCreated'] ?? '').'</span>');
					} else {
						$addPair($lanText['payment'].' '.$lanText['id'], '<span>'.($c['paymentIntent'] ?? '').'</span>');
						$addPair($lanText['payAmount'], '<span>'.number_format(intval($c['total'] ?? 0),0,'.',',').' ریال</span>');
					}
					continue;
				}

				if (isset($c['id_']) && $c['id_']==='passwordRegisterEFB'){
					$q = '**********';
				}

				if (
					(!isset($c['type']) || $c['type']!=='checkbox') &&
					(!isset($c['value']) || $c['value']!=='@file@') &&
					(!isset($c['id_'])   || $c['id_']!=='payment')
				){

					if (isset($c['type']) && strpos($c['type'],'pay')!==false && isset($c['price'])){
						$total_amount += intval($c['price']);
						$title = $c['value'] ?? ($title ?: 'Item');
						$q = '<b>'.number_format(intval($c['price']),0,'.',',').' '.$currency.'</b>';
						$addPair($title, $q);
					}

					if (isset($c['type']) && strpos($c['type'],'imgRadio')!==false){
						$q = '<b>'.($c['value'] ?? '').'</b>';
					}else if (isset($c['value']) && strpos($c['type'],'imgRadio')){

						$q = $this->fun_imgRadio_efb($c['id_'], $c['src'] ?? '', $c);
						$addPair('', $q);
					}

					if ($title==='file'){ $title = 'atcfle'; }

					if ($title!=='' || $q!==''){
						$addPair($title, $q);
					}
				}else if (isset($c['type']) && $c['type']==='checkbox' && isset($c['value']) && $c['value']!=='@file@') {
					$addPair($title, '<b>'.$c['value'].'</b>');
				}
			}

			$m .= '</table>';
			return $m;
		}

	function fun_imgRadio_efb($id ,$link,$row){

		$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';
		$u = function($url){
			$patterns = [
				'/http:@efb@/',
				'/https:@efb@/',
				'/@efb@/'
			];
			$replacements = [
				'http://',
				'https://',
				'/'
			];

			$processedLink = preg_replace($patterns, $replacements, $url);

			return $processedLink;
			};
		$value = $row->value ?? '';
		$sub_value = $row->sub_value ?? '';
		$link =strpos($link,'http')===false  ?  $poster : $row->src;
		$link = $u($link);
		return '
			<label class="efb  " id="'.$id.'_lab" for="'.$id.'">
			<div class="efb card col-md-3 mx-0 my-1 w-100" style="">
			<img src="'.$link.'" alt="'.$value.'" style="width: 100%"  id="'.$id.'_img">
			<div class="efb card-body">
				<h5 class="efb card-title text-dark" id="'.$id.'_value">'.$value.'</h5>
				<p class="efb card-text" id="'.$id.'_value_sub">'.$sub_value.'</p>
			</div>
			</div>
			</label>';
	}

	public function bootstrap_icon_efb($w){
		if($w==null || sizeof($w)==0) return;
		$st = ' .bi-123::before {content:"\f67f";} .bi-alarm-fill::before {content:"\f101";} .bi-alarm::before {content:"\f102";} .bi-align-bottom::before {content:"\f103";} .bi-align-center::before {content:"\f104";} .bi-align-end::before {content:"\f105";} .bi-align-middle::before {content:"\f106";} .bi-align-start::before {content:"\f107";} .bi-align-top::before {content:"\f108";} .bi-alt::before {content:"\f109";} .bi-app-indicator::before {content:"\f10a";} .bi-app::before {content:"\f10b";} .bi-archive-fill::before {content:"\f10c";} .bi-archive::before {content:"\f10d";} .bi-arrow-90deg-down::before {content:"\f10e";} .bi-arrow-90deg-left::before {content:"\f10f";} .bi-arrow-90deg-right::before {content:"\f110";} .bi-arrow-90deg-up::before {content:"\f111";} .bi-arrow-bar-down::before {content:"\f112";} .bi-arrow-bar-left::before {content:"\f113";} .bi-arrow-bar-right::before {content:"\f114";} .bi-arrow-bar-up::before {content:"\f115";} .bi-arrow-clockwise::before {content:"\f116";} .bi-arrow-counterclockwise::before {content:"\f117";} .bi-arrow-down-circle-fill::before {content:"\f118";} .bi-arrow-down-circle::before {content:"\f119";} .bi-arrow-down-left-circle-fill::before {content:"\f11a";} .bi-arrow-down-left-circle::before {content:"\f11b";} .bi-arrow-down-left-square-fill::before {content:"\f11c";} .bi-arrow-down-left-square::before {content:"\f11d";} .bi-arrow-down-left::before {content:"\f11e";} .bi-arrow-down-right-circle-fill::before {content:"\f11f";} .bi-arrow-down-right-circle::before {content:"\f120";} .bi-arrow-down-right-square-fill::before {content:"\f121";} .bi-arrow-down-right-square::before {content:"\f122";} .bi-arrow-down-right::before {content:"\f123";} .bi-arrow-down-short::before {content:"\f124";} .bi-arrow-down-square-fill::before {content:"\f125";} .bi-arrow-down-square::before {content:"\f126";} .bi-arrow-down-up::before {content:"\f127";} .bi-arrow-down::before {content:"\f128";} .bi-arrow-left-circle-fill::before {content:"\f129";} .bi-arrow-left-circle::before {content:"\f12a";} .bi-arrow-left-right::before {content:"\f12b";} .bi-arrow-left-short::before {content:"\f12c";} .bi-arrow-left-square-fill::before {content:"\f12d";} .bi-arrow-left-square::before {content:"\f12e";} .bi-arrow-left::before {content:"\f12f";} .bi-arrow-repeat::before {content:"\f130";} .bi-arrow-return-left::before {content:"\f131";} .bi-arrow-return-right::before {content:"\f132";} .bi-arrow-right-circle-fill::before {content:"\f133";} .bi-arrow-right-circle::before {content:"\f134";} .bi-arrow-right-short::before {content:"\f135";} .bi-arrow-right-square-fill::before {content:"\f136";} .bi-arrow-right-square::before {content:"\f137";} .bi-arrow-right::before {content:"\f138";} .bi-arrow-up-circle-fill::before {content:"\f139";} .bi-arrow-up-circle::before {content:"\f13a";} .bi-arrow-up-left-circle-fill::before {content:"\f13b";} .bi-arrow-up-left-circle::before {content:"\f13c";} .bi-arrow-up-left-square-fill::before {content:"\f13d";} .bi-arrow-up-left-square::before {content:"\f13e";} .bi-arrow-up-left::before {content:"\f13f";} .bi-arrow-up-right-circle-fill::before {content:"\f140";} .bi-arrow-up-right-circle::before {content:"\f141";} .bi-arrow-up-right-square-fill::before {content:"\f142";} .bi-arrow-up-right-square::before {content:"\f143";} .bi-arrow-up-right::before {content:"\f144";} .bi-arrow-up-short::before {content:"\f145";} .bi-arrow-up-square-fill::before {content:"\f146";} .bi-arrow-up-square::before {content:"\f147";} .bi-arrow-up::before {content:"\f148";} .bi-arrows-angle-contract::before {content:"\f149";} .bi-arrows-angle-expand::before {content:"\f14a";} .bi-arrows-collapse::before {content:"\f14b";} .bi-arrows-expand::before {content:"\f14c";} .bi-arrows-fullscreen::before {content:"\f14d";} .bi-arrows-move::before {content:"\f14e";} .bi-aspect-ratio-fill::before {content:"\f14f";} .bi-aspect-ratio::before {content:"\f150";} .bi-asterisk::before {content:"\f151";} .bi-at::before {content:"\f152";} .bi-award-fill::before {content:"\f153";} .bi-award::before {content:"\f154";} .bi-back::before {content:"\f155";} .bi-backspace-fill::before {content:"\f156";} .bi-backspace-reverse-fill::before {content:"\f157";} .bi-backspace-reverse::before {content:"\f158";} .bi-backspace::before {content:"\f159";} .bi-badge-3d-fill::before {content:"\f15a";} .bi-badge-3d::before {content:"\f15b";} .bi-badge-4k-fill::before {content:"\f15c";} .bi-badge-4k::before {content:"\f15d";} .bi-badge-8k-fill::before {content:"\f15e";} .bi-badge-8k::before {content:"\f15f";} .bi-badge-ad-fill::before {content:"\f160";} .bi-badge-ad::before {content:"\f161";} .bi-badge-ar-fill::before {content:"\f162";} .bi-badge-ar::before {content:"\f163";} .bi-badge-cc-fill::before {content:"\f164";} .bi-badge-cc::before {content:"\f165";} .bi-badge-hd-fill::before {content:"\f166";} .bi-badge-hd::before {content:"\f167";} .bi-badge-tm-fill::before {content:"\f168";} .bi-badge-tm::before {content:"\f169";} .bi-badge-vo-fill::before {content:"\f16a";} .bi-badge-vo::before {content:"\f16b";} .bi-badge-vr-fill::before {content:"\f16c";} .bi-badge-vr::before {content:"\f16d";} .bi-badge-wc-fill::before {content:"\f16e";} .bi-badge-wc::before {content:"\f16f";} .bi-bag-check-fill::before {content:"\f170";} .bi-bag-check::before {content:"\f171";} .bi-bag-dash-fill::before {content:"\f172";} .bi-bag-dash::before {content:"\f173";} .bi-bag-fill::before {content:"\f174";} .bi-bag-plus-fill::before {content:"\f175";} .bi-bag-plus::before {content:"\f176";} .bi-bag-x-fill::before {content:"\f177";} .bi-bag-x::before {content:"\f178";} .bi-bag::before {content:"\f179";} .bi-bar-chart-fill::before {content:"\f17a";} .bi-bar-chart-line-fill::before {content:"\f17b";} .bi-bar-chart-line::before {content:"\f17c";} .bi-bar-chart-steps::before {content:"\f17d";} .bi-bar-chart::before {content:"\f17e";} .bi-basket-fill::before {content:"\f17f";} .bi-basket::before {content:"\f180";} .bi-basket2-fill::before {content:"\f181";} .bi-basket2::before {content:"\f182";} .bi-basket3-fill::before {content:"\f183";} .bi-basket3::before {content:"\f184";} .bi-battery-charging::before {content:"\f185";} .bi-battery-full::before {content:"\f186";} .bi-battery-half::before {content:"\f187";} .bi-battery::before {content:"\f188";} .bi-bell-fill::before {content:"\f189";} .bi-bell::before {content:"\f18a";} .bi-bezier::before {content:"\f18b";} .bi-bezier2::before {content:"\f18c";} .bi-bicycle::before {content:"\f18d";} .bi-binoculars-fill::before {content:"\f18e";} .bi-binoculars::before {content:"\f18f";} .bi-blockquote-left::before {content:"\f190";} .bi-blockquote-right::before {content:"\f191";} .bi-book-fill::before {content:"\f192";} .bi-book-half::before {content:"\f193";} .bi-book::before {content:"\f194";} .bi-bookmark-check-fill::before {content:"\f195";} .bi-bookmark-check::before {content:"\f196";} .bi-bookmark-dash-fill::before {content:"\f197";} .bi-bookmark-dash::before {content:"\f198";} .bi-bookmark-fill::before {content:"\f199";} .bi-bookmark-heart-fill::before {content:"\f19a";} .bi-bookmark-heart::before {content:"\f19b";} .bi-bookmark-plus-fill::before {content:"\f19c";} .bi-bookmark-plus::before {content:"\f19d";} .bi-bookmark-star-fill::before {content:"\f19e";} .bi-bookmark-star::before {content:"\f19f";} .bi-bookmark-x-fill::before {content:"\f1a0";} .bi-bookmark-x::before {content:"\f1a1";} .bi-bookmark::before {content:"\f1a2";} .bi-bookmarks-fill::before {content:"\f1a3";} .bi-bookmarks::before {content:"\f1a4";} .bi-bookshelf::before {content:"\f1a5";} .bi-bootstrap-fill::before {content:"\f1a6";} .bi-bootstrap-reboot::before {content:"\f1a7";} .bi-bootstrap::before {content:"\f1a8";} .bi-border-all::before {content:"\f1a9";} .bi-border-bottom::before {content:"\f1aa";} .bi-border-center::before {content:"\f1ab";} .bi-border-inner::before {content:"\f1ac";} .bi-border-left::before {content:"\f1ad";} .bi-border-middle::before {content:"\f1ae";} .bi-border-outer::before {content:"\f1af";} .bi-border-right::before {content:"\f1b0";} .bi-border-style::before {content:"\f1b1";} .bi-border-top::before {content:"\f1b2";} .bi-border-width::before {content:"\f1b3";} .bi-border::before {content:"\f1b4";} .bi-bounding-box-circles::before {content:"\f1b5";} .bi-bounding-box::before {content:"\f1b6";} .bi-box-arrow-down-left::before {content:"\f1b7";} .bi-box-arrow-down-right::before {content:"\f1b8";} .bi-box-arrow-down::before {content:"\f1b9";} .bi-box-arrow-in-down-left::before {content:"\f1ba";} .bi-box-arrow-in-down-right::before {content:"\f1bb";} .bi-box-arrow-in-down::before {content:"\f1bc";} .bi-box-arrow-in-left::before {content:"\f1bd";} .bi-box-arrow-in-right::before {content:"\f1be";} .bi-box-arrow-in-up-left::before {content:"\f1bf";} .bi-box-arrow-in-up-right::before {content:"\f1c0";} .bi-box-arrow-in-up::before {content:"\f1c1";} .bi-box-arrow-left::before {content:"\f1c2";} .bi-box-arrow-right::before {content:"\f1c3";} .bi-box-arrow-up-left::before {content:"\f1c4";} .bi-box-arrow-up-right::before {content:"\f1c5";} .bi-box-arrow-up::before {content:"\f1c6";} .bi-box-seam::before {content:"\f1c7";} .bi-box::before {content:"\f1c8";} .bi-braces::before {content:"\f1c9";} .bi-bricks::before {content:"\f1ca";} .bi-briefcase-fill::before {content:"\f1cb";} .bi-briefcase::before {content:"\f1cc";} .bi-brightness-alt-high-fill::before {content:"\f1cd";} .bi-brightness-alt-high::before {content:"\f1ce";} .bi-brightness-alt-low-fill::before {content:"\f1cf";} .bi-brightness-alt-low::before {content:"\f1d0";} .bi-brightness-high-fill::before {content:"\f1d1";} .bi-brightness-high::before {content:"\f1d2";} .bi-brightness-low-fill::before {content:"\f1d3";} .bi-brightness-low::before {content:"\f1d4";} .bi-broadcast-pin::before {content:"\f1d5";} .bi-broadcast::before {content:"\f1d6";} .bi-brush-fill::before {content:"\f1d7";} .bi-brush::before {content:"\f1d8";} .bi-bucket-fill::before {content:"\f1d9";} .bi-bucket::before {content:"\f1da";} .bi-bug-fill::before {content:"\f1db";} .bi-bug::before {content:"\f1dc";} .bi-building::before {content:"\f1dd";} .bi-bullseye::before {content:"\f1de";} .bi-calculator-fill::before {content:"\f1df";} .bi-calculator::before {content:"\f1e0";} .bi-calendar-check-fill::before {content:"\f1e1";} .bi-calendar-check::before {content:"\f1e2";} .bi-calendar-date-fill::before {content:"\f1e3";} .bi-calendar-date::before {content:"\f1e4";} .bi-calendar-day-fill::before {content:"\f1e5";} .bi-calendar-day::before {content:"\f1e6";} .bi-calendar-event-fill::before {content:"\f1e7";} .bi-calendar-event::before {content:"\f1e8";} .bi-calendar-fill::before {content:"\f1e9";} .bi-calendar-minus-fill::before {content:"\f1ea";} .bi-calendar-minus::before {content:"\f1eb";} .bi-calendar-month-fill::before {content:"\f1ec";} .bi-calendar-month::before {content:"\f1ed";} .bi-calendar-plus-fill::before {content:"\f1ee";} .bi-calendar-plus::before {content:"\f1ef";} .bi-calendar-range-fill::before {content:"\f1f0";} .bi-calendar-range::before {content:"\f1f1";} .bi-calendar-week-fill::before {content:"\f1f2";} .bi-calendar-week::before {content:"\f1f3";} .bi-calendar-x-fill::before {content:"\f1f4";} .bi-calendar-x::before {content:"\f1f5";} .bi-calendar::before {content:"\f1f6";} .bi-calendar2-check-fill::before {content:"\f1f7";} .bi-calendar2-check::before {content:"\f1f8";} .bi-calendar2-date-fill::before {content:"\f1f9";} .bi-calendar2-date::before {content:"\f1fa";} .bi-calendar2-day-fill::before {content:"\f1fb";} .bi-calendar2-day::before {content:"\f1fc";} .bi-calendar2-event-fill::before {content:"\f1fd";} .bi-calendar2-event::before {content:"\f1fe";} .bi-calendar2-fill::before {content:"\f1ff";} .bi-calendar2-minus-fill::before {content:"\f200";} .bi-calendar2-minus::before {content:"\f201";} .bi-calendar2-month-fill::before {content:"\f202";} .bi-calendar2-month::before {content:"\f203";} .bi-calendar2-plus-fill::before {content:"\f204";} .bi-calendar2-plus::before {content:"\f205";} .bi-calendar2-range-fill::before {content:"\f206";} .bi-calendar2-range::before {content:"\f207";} .bi-calendar2-week-fill::before {content:"\f208";} .bi-calendar2-week::before {content:"\f209";} .bi-calendar2-x-fill::before {content:"\f20a";} .bi-calendar2-x::before {content:"\f20b";} .bi-calendar2::before {content:"\f20c";} .bi-calendar3-event-fill::before {content:"\f20d";} .bi-calendar3-event::before {content:"\f20e";} .bi-calendar3-fill::before {content:"\f20f";} .bi-calendar3-range-fill::before {content:"\f210";} .bi-calendar3-range::before {content:"\f211";} .bi-calendar3-week-fill::before {content:"\f212";} .bi-calendar3-week::before {content:"\f213";} .bi-calendar3::before {content:"\f214";} .bi-calendar4-event::before {content:"\f215";} .bi-calendar4-range::before {content:"\f216";} .bi-calendar4-week::before {content:"\f217";} .bi-calendar4::before {content:"\f218";} .bi-camera-fill::before {content:"\f219";} .bi-camera-reels-fill::before {content:"\f21a";} .bi-camera-reels::before {content:"\f21b";} .bi-camera-video-fill::before {content:"\f21c";} .bi-camera-video-off-fill::before {content:"\f21d";} .bi-camera-video-off::before {content:"\f21e";} .bi-camera-video::before {content:"\f21f";} .bi-camera::before {content:"\f220";} .bi-camera2::before {content:"\f221";} .bi-capslock-fill::before {content:"\f222";} .bi-capslock::before {content:"\f223";} .bi-card-checklist::before {content:"\f224";} .bi-card-heading::before {content:"\f225";} .bi-card-image::before {content:"\f226";} .bi-card-list::before {content:"\f227";} .bi-card-text::before {content:"\f228";} .bi-caret-down-fill::before {content:"\f229";} .bi-caret-down-square-fill::before {content:"\f22a";} .bi-caret-down-square::before {content:"\f22b";} .bi-caret-down::before {content:"\f22c";} .bi-caret-left-fill::before {content:"\f22d";} .bi-caret-left-square-fill::before {content:"\f22e";} .bi-caret-left-square::before {content:"\f22f";} .bi-caret-left::before {content:"\f230";} .bi-caret-right-fill::before {content:"\f231";} .bi-caret-right-square-fill::before {content:"\f232";} .bi-caret-right-square::before {content:"\f233";} .bi-caret-right::before {content:"\f234";} .bi-caret-up-fill::before {content:"\f235";} .bi-caret-up-square-fill::before {content:"\f236";} .bi-caret-up-square::before {content:"\f237";} .bi-caret-up::before {content:"\f238";} .bi-cart-check-fill::before {content:"\f239";} .bi-cart-check::before {content:"\f23a";} .bi-cart-dash-fill::before {content:"\f23b";} .bi-cart-dash::before {content:"\f23c";} .bi-cart-fill::before {content:"\f23d";} .bi-cart-plus-fill::before {content:"\f23e";} .bi-cart-plus::before {content:"\f23f";} .bi-cart-x-fill::before {content:"\f240";} .bi-cart-x::before {content:"\f241";} .bi-cart::before {content:"\f242";} .bi-cart2::before {content:"\f243";} .bi-cart3::before {content:"\f244";} .bi-cart4::before {content:"\f245";} .bi-cash-stack::before {content:"\f246";} .bi-cash::before {content:"\f247";} .bi-cast::before {content:"\f248";} .bi-chat-dots-fill::before {content:"\f249";} .bi-chat-dots::before {content:"\f24a";} .bi-chat-fill::before {content:"\f24b";} .bi-chat-left-dots-fill::before {content:"\f24c";} .bi-chat-left-dots::before {content:"\f24d";} .bi-chat-left-fill::before {content:"\f24e";} .bi-chat-left-quote-fill::before {content:"\f24f";} .bi-chat-left-quote::before {content:"\f250";} .bi-chat-left-text-fill::before {content:"\f251";} .bi-chat-left-text::before {content:"\f252";} .bi-chat-left::before {content:"\f253";} .bi-chat-quote-fill::before {content:"\f254";} .bi-chat-quote::before {content:"\f255";} .bi-chat-right-dots-fill::before {content:"\f256";} .bi-chat-right-dots::before {content:"\f257";} .bi-chat-right-fill::before {content:"\f258";} .bi-chat-right-quote-fill::before {content:"\f259";} .bi-chat-right-quote::before {content:"\f25a";} .bi-chat-right-text-fill::before {content:"\f25b";} .bi-chat-right-text::before {content:"\f25c";} .bi-chat-right::before {content:"\f25d";} .bi-chat-square-dots-fill::before {content:"\f25e";} .bi-chat-square-dots::before {content:"\f25f";} .bi-chat-square-fill::before {content:"\f260";} .bi-chat-square-quote-fill::before {content:"\f261";} .bi-chat-square-quote::before {content:"\f262";} .bi-chat-square-text-fill::before {content:"\f263";} .bi-chat-square-text::before {content:"\f264";} .bi-chat-square::before {content:"\f265";} .bi-chat-text-fill::before {content:"\f266";} .bi-chat-text::before {content:"\f267";} .bi-chat::before {content:"\f268";} .bi-check-all::before {content:"\f269";} .bi-check-circle-fill::before {content:"\f26a";} .bi-check-circle::before {content:"\f26b";} .bi-check-square-fill::before {content:"\f26c";} .bi-check-square::before {content:"\f26d";} .bi-check::before {content:"\f26e";} .bi-check2-all::before {content:"\f26f";} .bi-check2-circle::before {content:"\f270";} .bi-check2-square::before {content:"\f271";} .bi-check2::before {content:"\f272";} .bi-chevron-bar-contract::before {content:"\f273";} .bi-chevron-bar-down::before {content:"\f274";} .bi-chevron-bar-expand::before {content:"\f275";} .bi-chevron-bar-left::before {content:"\f276";} .bi-chevron-bar-right::before {content:"\f277";} .bi-chevron-bar-up::before {content:"\f278";} .bi-chevron-compact-down::before {content:"\f279";} .bi-chevron-compact-left::before {content:"\f27a";} .bi-chevron-compact-right::before {content:"\f27b";} .bi-chevron-compact-up::before {content:"\f27c";} .bi-chevron-contract::before {content:"\f27d";} .bi-chevron-double-down::before {content:"\f27e";} .bi-chevron-double-left::before {content:"\f27f";} .bi-chevron-double-right::before {content:"\f280";} .bi-chevron-double-up::before {content:"\f281";} .bi-chevron-down::before {content:"\f282";} .bi-chevron-expand::before {content:"\f283";} .bi-chevron-left::before {content:"\f284";} .bi-chevron-right::before {content:"\f285";} .bi-chevron-up::before {content:"\f286";} .bi-circle-fill::before {content:"\f287";} .bi-circle-half::before {content:"\f288";} .bi-circle-square::before {content:"\f289";} .bi-circle::before {content:"\f28a";} .bi-clipboard-check::before {content:"\f28b";} .bi-clipboard-data::before {content:"\f28c";} .bi-clipboard-minus::before {content:"\f28d";} .bi-clipboard-plus::before {content:"\f28e";} .bi-clipboard-x::before {content:"\f28f";} .bi-clipboard::before {content:"\f290";} .bi-clock-fill::before {content:"\f291";} .bi-clock-history::before {content:"\f292";} .bi-clock::before {content:"\f293";} .bi-cloud-arrow-down-fill::before {content:"\f294";} .bi-cloud-arrow-down::before {content:"\f295";} .bi-cloud-arrow-up-fill::before {content:"\f296";} .bi-cloud-arrow-up::before {content:"\f297";} .bi-cloud-check-fill::before {content:"\f298";} .bi-cloud-check::before {content:"\f299";} .bi-cloud-download-fill::before {content:"\f29a";} .bi-cloud-download::before {content:"\f29b";} .bi-cloud-drizzle-fill::before {content:"\f29c";} .bi-cloud-drizzle::before {content:"\f29d";} .bi-cloud-fill::before {content:"\f29e";} .bi-cloud-fog-fill::before {content:"\f29f";} .bi-cloud-fog::before {content:"\f2a0";} .bi-cloud-fog2-fill::before {content:"\f2a1";} .bi-cloud-fog2::before {content:"\f2a2";} .bi-cloud-hail-fill::before {content:"\f2a3";} .bi-cloud-hail::before {content:"\f2a4";} .bi-cloud-haze-fill::before {content:"\f2a6";} .bi-cloud-haze::before {content:"\f2a7";} .bi-cloud-haze2-fill::before {content:"\f2a8";} .bi-cloud-lightning-fill::before {content:"\f2a9";} .bi-cloud-lightning-rain-fill::before {content:"\f2aa";} .bi-cloud-lightning-rain::before {content:"\f2ab";} .bi-cloud-lightning::before {content:"\f2ac";} .bi-cloud-minus-fill::before {content:"\f2ad";} .bi-cloud-minus::before {content:"\f2ae";} .bi-cloud-moon-fill::before {content:"\f2af";} .bi-cloud-moon::before {content:"\f2b0";} .bi-cloud-plus-fill::before {content:"\f2b1";} .bi-cloud-plus::before {content:"\f2b2";} .bi-cloud-rain-fill::before {content:"\f2b3";} .bi-cloud-rain-heavy-fill::before {content:"\f2b4";} .bi-cloud-rain-heavy::before {content:"\f2b5";} .bi-cloud-rain::before {content:"\f2b6";} .bi-cloud-slash-fill::before {content:"\f2b7";} .bi-cloud-slash::before {content:"\f2b8";} .bi-cloud-sleet-fill::before {content:"\f2b9";} .bi-cloud-sleet::before {content:"\f2ba";} .bi-cloud-snow-fill::before {content:"\f2bb";} .bi-cloud-snow::before {content:"\f2bc";} .bi-cloud-sun-fill::before {content:"\f2bd";} .bi-cloud-sun::before {content:"\f2be";} .bi-cloud-upload-fill::before {content:"\f2bf";} .bi-cloud-upload::before {content:"\f2c0";} .bi-cloud::before {content:"\f2c1";} .bi-clouds-fill::before {content:"\f2c2";} .bi-clouds::before {content:"\f2c3";} .bi-cloudy-fill::before {content:"\f2c4";} .bi-cloudy::before {content:"\f2c5";} .bi-code-slash::before {content:"\f2c6";} .bi-code-square::before {content:"\f2c7";} .bi-code::before {content:"\f2c8";} .bi-collection-fill::before {content:"\f2c9";} .bi-collection-play-fill::before {content:"\f2ca";} .bi-collection-play::before {content:"\f2cb";} .bi-collection::before {content:"\f2cc";} .bi-columns-gap::before {content:"\f2cd";} .bi-columns::before {content:"\f2ce";} .bi-command::before {content:"\f2cf";} .bi-compass-fill::before {content:"\f2d0";} .bi-compass::before {content:"\f2d1";} .bi-cone-striped::before {content:"\f2d2";} .bi-cone::before {content:"\f2d3";} .bi-controller::before {content:"\f2d4";} .bi-cpu-fill::before {content:"\f2d5";} .bi-cpu::before {content:"\f2d6";} .bi-credit-card-2-back-fill::before {content:"\f2d7";} .bi-credit-card-2-back::before {content:"\f2d8";} .bi-credit-card-2-front-fill::before {content:"\f2d9";} .bi-credit-card-2-front::before {content:"\f2da";} .bi-credit-card-fill::before {content:"\f2db";} .bi-credit-card::before {content:"\f2dc";} .bi-crop::before {content:"\f2dd";} .bi-cup-fill::before {content:"\f2de";} .bi-cup-straw::before {content:"\f2df";} .bi-cup::before {content:"\f2e0";} .bi-cursor-fill::before {content:"\f2e1";} .bi-cursor-text::before {content:"\f2e2";} .bi-cursor::before {content:"\f2e3";} .bi-dash-circle-dotted::before {content:"\f2e4";} .bi-dash-circle-fill::before {content:"\f2e5";} .bi-dash-circle::before {content:"\f2e6";} .bi-dash-square-dotted::before {content:"\f2e7";} .bi-dash-square-fill::before {content:"\f2e8";} .bi-dash-square::before {content:"\f2e9";} .bi-dash::before {content:"\f2ea";} .bi-diagram-2-fill::before {content:"\f2eb";} .bi-diagram-2::before {content:"\f2ec";} .bi-diagram-3-fill::before {content:"\f2ed";} .bi-diagram-3::before {content:"\f2ee";} .bi-diamond-fill::before {content:"\f2ef";} .bi-diamond-half::before {content:"\f2f0";} .bi-diamond::before {content:"\f2f1";} .bi-dice-1-fill::before {content:"\f2f2";} .bi-dice-1::before {content:"\f2f3";} .bi-dice-2-fill::before {content:"\f2f4";} .bi-dice-2::before {content:"\f2f5";} .bi-dice-3-fill::before {content:"\f2f6";} .bi-dice-3::before {content:"\f2f7";} .bi-dice-4-fill::before {content:"\f2f8";} .bi-dice-4::before {content:"\f2f9";} .bi-dice-5-fill::before {content:"\f2fa";} .bi-dice-5::before {content:"\f2fb";} .bi-dice-6-fill::before {content:"\f2fc";} .bi-dice-6::before {content:"\f2fd";} .bi-disc-fill::before {content:"\f2fe";} .bi-disc::before {content:"\f2ff";} .bi-discord::before {content:"\f300";} .bi-display-fill::before {content:"\f301";} .bi-display::before {content:"\f302";} .bi-distribute-horizontal::before {content:"\f303";} .bi-distribute-vertical::before {content:"\f304";} .bi-door-closed-fill::before {content:"\f305";} .bi-door-closed::before {content:"\f306";} .bi-door-open-fill::before {content:"\f307";} .bi-door-open::before {content:"\f308";} .bi-dot::before {content:"\f309";} .bi-download::before {content:"\f30a";} .bi-droplet-fill::before {content:"\f30b";} .bi-droplet-half::before {content:"\f30c";} .bi-droplet::before {content:"\f30d";} .bi-earbuds::before {content:"\f30e";} .bi-easel-fill::before {content:"\f30f";} .bi-easel::before {content:"\f310";} .bi-egg-fill::before {content:"\f311";} .bi-egg-fried::before {content:"\f312";} .bi-egg::before {content:"\f313";} .bi-eject-fill::before {content:"\f314";} .bi-eject::before {content:"\f315";} .bi-emoji-angry-fill::before {content:"\f316";} .bi-emoji-angry::before {content:"\f317";} .bi-emoji-dizzy-fill::before {content:"\f318";} .bi-emoji-dizzy::before {content:"\f319";} .bi-emoji-expressionless-fill::before {content:"\f31a";} .bi-emoji-expressionless::before {content:"\f31b";} .bi-emoji-frown-fill::before {content:"\f31c";} .bi-emoji-frown::before {content:"\f31d";} .bi-emoji-heart-eyes-fill::before {content:"\f31e";} .bi-emoji-heart-eyes::before {content:"\f31f";} .bi-emoji-laughing-fill::before {content:"\f320";} .bi-emoji-laughing::before {content:"\f321";} .bi-emoji-neutral-fill::before {content:"\f322";} .bi-emoji-neutral::before {content:"\f323";} .bi-emoji-smile-fill::before {content:"\f324";} .bi-emoji-smile-upside-down-fill::before {content:"\f325";} .bi-emoji-smile-upside-down::before {content:"\f326";} .bi-emoji-smile::before {content:"\f327";} .bi-emoji-sunglasses-fill::before {content:"\f328";} .bi-emoji-sunglasses::before {content:"\f329";} .bi-emoji-wink-fill::before {content:"\f32a";} .bi-emoji-wink::before {content:"\f32b";} .bi-envelope-fill::before {content:"\f32c";} .bi-envelope-open-fill::before {content:"\f32d";} .bi-envelope-open::before {content:"\f32e";} .bi-envelope::before {content:"\f32f";} .bi-eraser-fill::before {content:"\f330";} .bi-eraser::before {content:"\f331";} .bi-exclamation-circle-fill::before {content:"\f332";} .bi-exclamation-circle::before {content:"\f333";} .bi-exclamation-diamond-fill::before {content:"\f334";} .bi-exclamation-diamond::before {content:"\f335";} .bi-exclamation-octagon-fill::before {content:"\f336";} .bi-exclamation-octagon::before {content:"\f337";} .bi-exclamation-square-fill::before {content:"\f338";} .bi-exclamation-square::before {content:"\f339";} .bi-exclamation-triangle-fill::before {content:"\f33a";} .bi-exclamation-triangle::before {content:"\f33b";} .bi-exclamation::before {content:"\f33c";} .bi-exclude::before {content:"\f33d";} .bi-eye-fill::before {content:"\f33e";} .bi-eye-slash-fill::before {content:"\f33f";} .bi-eye-slash::before {content:"\f340";} .bi-eye::before {content:"\f341";} .bi-eyedropper::before {content:"\f342";} .bi-eyeglasses::before {content:"\f343";} .bi-facebook::before {content:"\f344";} .bi-file-arrow-down-fill::before {content:"\f345";} .bi-file-arrow-down::before {content:"\f346";} .bi-file-arrow-up-fill::before {content:"\f347";} .bi-file-arrow-up::before {content:"\f348";} .bi-file-bar-graph-fill::before {content:"\f349";} .bi-file-bar-graph::before {content:"\f34a";} .bi-file-binary-fill::before {content:"\f34b";} .bi-file-binary::before {content:"\f34c";} .bi-file-break-fill::before {content:"\f34d";} .bi-file-break::before {content:"\f34e";} .bi-file-check-fill::before {content:"\f34f";} .bi-file-check::before {content:"\f350";} .bi-file-code-fill::before {content:"\f351";} .bi-file-code::before {content:"\f352";} .bi-file-diff-fill::before {content:"\f353";} .bi-file-diff::before {content:"\f354";} .bi-file-earmark-arrow-down-fill::before {content:"\f355";} .bi-file-earmark-arrow-down::before {content:"\f356";} .bi-file-earmark-arrow-up-fill::before {content:"\f357";} .bi-file-earmark-arrow-up::before {content:"\f358";} .bi-file-earmark-bar-graph-fill::before {content:"\f359";} .bi-file-earmark-bar-graph::before {content:"\f35a";} .bi-file-earmark-binary-fill::before {content:"\f35b";} .bi-file-earmark-binary::before {content:"\f35c";} .bi-file-earmark-break-fill::before {content:"\f35d";} .bi-file-earmark-break::before {content:"\f35e";} .bi-file-earmark-check-fill::before {content:"\f35f";} .bi-file-earmark-check::before {content:"\f360";} .bi-file-earmark-code-fill::before {content:"\f361";} .bi-file-earmark-code::before {content:"\f362";} .bi-file-earmark-diff-fill::before {content:"\f363";} .bi-file-earmark-diff::before {content:"\f364";} .bi-file-earmark-easel-fill::before {content:"\f365";} .bi-file-earmark-easel::before {content:"\f366";} .bi-file-earmark-excel-fill::before {content:"\f367";} .bi-file-earmark-excel::before {content:"\f368";} .bi-file-earmark-fill::before {content:"\f369";} .bi-file-earmark-font-fill::before {content:"\f36a";} .bi-file-earmark-font::before {content:"\f36b";} .bi-file-earmark-image-fill::before {content:"\f36c";} .bi-file-earmark-image::before {content:"\f36d";} .bi-file-earmark-lock-fill::before {content:"\f36e";} .bi-file-earmark-lock::before {content:"\f36f";} .bi-file-earmark-lock2-fill::before {content:"\f370";} .bi-file-earmark-lock2::before {content:"\f371";} .bi-file-earmark-medical-fill::before {content:"\f372";} .bi-file-earmark-medical::before {content:"\f373";} .bi-file-earmark-minus-fill::before {content:"\f374";} .bi-file-earmark-minus::before {content:"\f375";} .bi-file-earmark-music-fill::before {content:"\f376";} .bi-file-earmark-music::before {content:"\f377";} .bi-file-earmark-person-fill::before {content:"\f378";} .bi-file-earmark-person::before {content:"\f379";} .bi-file-earmark-play-fill::before {content:"\f37a";} .bi-file-earmark-play::before {content:"\f37b";} .bi-file-earmark-plus-fill::before {content:"\f37c";} .bi-file-earmark-plus::before {content:"\f37d";} .bi-file-earmark-post-fill::before {content:"\f37e";} .bi-file-earmark-post::before {content:"\f37f";} .bi-file-earmark-ppt-fill::before {content:"\f380";} .bi-file-earmark-ppt::before {content:"\f381";} .bi-file-earmark-richtext-fill::before {content:"\f382";} .bi-file-earmark-richtext::before {content:"\f383";} .bi-file-earmark-ruled-fill::before {content:"\f384";} .bi-file-earmark-ruled::before {content:"\f385";} .bi-file-earmark-slides-fill::before {content:"\f386";} .bi-file-earmark-slides::before {content:"\f387";} .bi-file-earmark-spreadsheet-fill::before {content:"\f388";} .bi-file-earmark-spreadsheet::before {content:"\f389";} .bi-file-earmark-text-fill::before {content:"\f38a";} .bi-file-earmark-text::before {content:"\f38b";} .bi-file-earmark-word-fill::before {content:"\f38c";} .bi-file-earmark-word::before {content:"\f38d";} .bi-file-earmark-x-fill::before {content:"\f38e";} .bi-file-earmark-x::before {content:"\f38f";} .bi-file-earmark-zip-fill::before {content:"\f390";} .bi-file-earmark-zip::before {content:"\f391";} .bi-file-earmark::before {content:"\f392";} .bi-file-easel-fill::before {content:"\f393";} .bi-file-easel::before {content:"\f394";} .bi-file-excel-fill::before {content:"\f395";} .bi-file-excel::before {content:"\f396";} .bi-file-fill::before {content:"\f397";} .bi-file-font-fill::before {content:"\f398";} .bi-file-font::before {content:"\f399";} .bi-file-image-fill::before {content:"\f39a";} .bi-file-image::before {content:"\f39b";} .bi-file-lock-fill::before {content:"\f39c";} .bi-file-lock::before {content:"\f39d";} .bi-file-lock2-fill::before {content:"\f39e";} .bi-file-lock2::before {content:"\f39f";} .bi-file-medical-fill::before {content:"\f3a0";} .bi-file-medical::before {content:"\f3a1";} .bi-file-minus-fill::before {content:"\f3a2";} .bi-file-minus::before {content:"\f3a3";} .bi-file-music-fill::before {content:"\f3a4";} .bi-file-music::before {content:"\f3a5";} .bi-file-person-fill::before {content:"\f3a6";} .bi-file-person::before {content:"\f3a7";} .bi-file-play-fill::before {content:"\f3a8";} .bi-file-play::before {content:"\f3a9";} .bi-file-plus-fill::before {content:"\f3aa";} .bi-file-plus::before {content:"\f3ab";} .bi-file-post-fill::before {content:"\f3ac";} .bi-file-post::before {content:"\f3ad";} .bi-file-ppt-fill::before {content:"\f3ae";} .bi-file-ppt::before {content:"\f3af";} .bi-file-richtext-fill::before {content:"\f3b0";} .bi-file-richtext::before {content:"\f3b1";} .bi-file-ruled-fill::before {content:"\f3b2";} .bi-file-ruled::before {content:"\f3b3";} .bi-file-slides-fill::before {content:"\f3b4";} .bi-file-slides::before {content:"\f3b5";} .bi-file-spreadsheet-fill::before {content:"\f3b6";} .bi-file-spreadsheet::before {content:"\f3b7";} .bi-file-text-fill::before {content:"\f3b8";} .bi-file-text::before {content:"\f3b9";} .bi-file-word-fill::before {content:"\f3ba";} .bi-file-word::before {content:"\f3bb";} .bi-file-x-fill::before {content:"\f3bc";} .bi-file-x::before {content:"\f3bd";} .bi-file-zip-fill::before {content:"\f3be";} .bi-file-zip::before {content:"\f3bf";} .bi-file::before {content:"\f3c0";} .bi-files-alt::before {content:"\f3c1";} .bi-files::before {content:"\f3c2";} .bi-film::before {content:"\f3c3";} .bi-filter-circle-fill::before {content:"\f3c4";} .bi-filter-circle::before {content:"\f3c5";} .bi-filter-left::before {content:"\f3c6";} .bi-filter-right::before {content:"\f3c7";} .bi-filter-square-fill::before {content:"\f3c8";} .bi-filter-square::before {content:"\f3c9";} .bi-filter::before {content:"\f3ca";} .bi-flag-fill::before {content:"\f3cb";} .bi-flag::before {content:"\f3cc";} .bi-flower1::before {content:"\f3cd";} .bi-flower2::before {content:"\f3ce";} .bi-flower3::before {content:"\f3cf";} .bi-folder-check::before {content:"\f3d0";} .bi-folder-fill::before {content:"\f3d1";} .bi-folder-minus::before {content:"\f3d2";} .bi-folder-plus::before {content:"\f3d3";} .bi-folder-symlink-fill::before {content:"\f3d4";} .bi-folder-symlink::before {content:"\f3d5";} .bi-folder-x::before {content:"\f3d6";} .bi-folder::before {content:"\f3d7";} .bi-folder2-open::before {content:"\f3d8";} .bi-folder2::before {content:"\f3d9";} .bi-fonts::before {content:"\f3da";} .bi-forward-fill::before {content:"\f3db";} .bi-forward::before {content:"\f3dc";} .bi-front::before {content:"\f3dd";} .bi-fullscreen-exit::before {content:"\f3de";} .bi-fullscreen::before {content:"\f3df";} .bi-funnel-fill::before {content:"\f3e0";} .bi-funnel::before {content:"\f3e1";} .bi-gear-fill::before {content:"\f3e2";} .bi-gear-wide-connected::before {content:"\f3e3";} .bi-gear-wide::before {content:"\f3e4";} .bi-gear::before {content:"\f3e5";} .bi-gem::before {content:"\f3e6";} .bi-geo-alt-fill::before {content:"\f3e7";} .bi-geo-alt::before {content:"\f3e8";} .bi-geo-fill::before {content:"\f3e9";} .bi-geo::before {content:"\f3ea";} .bi-gift-fill::before {content:"\f3eb";} .bi-gift::before {content:"\f3ec";} .bi-github::before {content:"\f3ed";} .bi-globe::before {content:"\f3ee";} .bi-globe2::before {content:"\f3ef";} .bi-google::before {content:"\f3f0";} .bi-graph-down::before {content:"\f3f1";} .bi-graph-up::before {content:"\f3f2";} .bi-grid-1x2-fill::before {content:"\f3f3";} .bi-grid-1x2::before {content:"\f3f4";} .bi-grid-3x2-gap-fill::before {content:"\f3f5";} .bi-grid-3x2-gap::before {content:"\f3f6";} .bi-grid-3x2::before {content:"\f3f7";} .bi-grid-3x3-gap-fill::before {content:"\f3f8";} .bi-grid-3x3-gap::before {content:"\f3f9";} .bi-grid-3x3::before {content:"\f3fa";} .bi-grid-fill::before {content:"\f3fb";} .bi-grid::before {content:"\f3fc";} .bi-grip-horizontal::before {content:"\f3fd";} .bi-grip-vertical::before {content:"\f3fe";} .bi-hammer::before {content:"\f3ff";} .bi-hand-index-fill::before {content:"\f400";} .bi-hand-index-thumb-fill::before {content:"\f401";} .bi-hand-index-thumb::before {content:"\f402";} .bi-hand-index::before {content:"\f403";} .bi-hand-thumbs-down-fill::before {content:"\f404";} .bi-hand-thumbs-down::before {content:"\f405";} .bi-hand-thumbs-up-fill::before {content:"\f406";} .bi-hand-thumbs-up::before {content:"\f407";} .bi-handbag-fill::before {content:"\f408";} .bi-handbag::before {content:"\f409";} .bi-hash::before {content:"\f40a";} .bi-hdd-fill::before {content:"\f40b";} .bi-hdd-network-fill::before {content:"\f40c";} .bi-hdd-network::before {content:"\f40d";} .bi-hdd-rack-fill::before {content:"\f40e";} .bi-hdd-rack::before {content:"\f40f";} .bi-hdd-stack-fill::before {content:"\f410";} .bi-hdd-stack::before {content:"\f411";} .bi-hdd::before {content:"\f412";} .bi-headphones::before {content:"\f413";} .bi-headset::before {content:"\f414";} .bi-heart-fill::before {content:"\f415";} .bi-heart-half::before {content:"\f416";} .bi-heart::before {content:"\f417";} .bi-heptagon-fill::before {content:"\f418";} .bi-heptagon-half::before {content:"\f419";} .bi-heptagon::before {content:"\f41a";} .bi-hexagon-fill::before {content:"\f41b";} .bi-hexagon-half::before {content:"\f41c";} .bi-hexagon::before {content:"\f41d";} .bi-hourglass-bottom::before {content:"\f41e";} .bi-hourglass-split::before {content:"\f41f";} .bi-hourglass-top::before {content:"\f420";} .bi-hourglass::before {content:"\f421";} .bi-house-door-fill::before {content:"\f422";} .bi-house-door::before {content:"\f423";} .bi-house-fill::before {content:"\f424";} .bi-house::before {content:"\f425";} .bi-hr::before {content:"\f426";} .bi-hurricane::before {content:"\f427";} .bi-image-alt::before {content:"\f428";} .bi-image-fill::before {content:"\f429";} .bi-image::before {content:"\f42a";} .bi-images::before {content:"\f42b";} .bi-inbox-fill::before {content:"\f42c";} .bi-inbox::before {content:"\f42d";} .bi-inboxes-fill::before {content:"\f42e";} .bi-inboxes::before {content:"\f42f";} .bi-info-circle-fill::before {content:"\f430";} .bi-info-circle::before {content:"\f431";} .bi-info-square-fill::before {content:"\f432";} .bi-info-square::before {content:"\f433";} .bi-info::before {content:"\f434";} .bi-input-cursor-text::before {content:"\f435";} .bi-input-cursor::before {content:"\f436";} .bi-instagram::before {content:"\f437";} .bi-intersect::before {content:"\f438";} .bi-journal-album::before {content:"\f439";} .bi-journal-arrow-down::before {content:"\f43a";} .bi-journal-arrow-up::before {content:"\f43b";} .bi-journal-bookmark-fill::before {content:"\f43c";} .bi-journal-bookmark::before {content:"\f43d";} .bi-journal-check::before {content:"\f43e";} .bi-journal-code::before {content:"\f43f";} .bi-journal-medical::before {content:"\f440";} .bi-journal-minus::before {content:"\f441";} .bi-journal-plus::before {content:"\f442";} .bi-journal-richtext::before {content:"\f443";} .bi-journal-text::before {content:"\f444";} .bi-journal-x::before {content:"\f445";} .bi-journal::before {content:"\f446";} .bi-journals::before {content:"\f447";} .bi-joystick::before {content:"\f448";} .bi-justify-left::before {content:"\f449";} .bi-justify-right::before {content:"\f44a";} .bi-justify::before {content:"\f44b";} .bi-kanban-fill::before {content:"\f44c";} .bi-kanban::before {content:"\f44d";} .bi-key-fill::before {content:"\f44e";} .bi-key::before {content:"\f44f";} .bi-keyboard-fill::before {content:"\f450";} .bi-keyboard::before {content:"\f451";} .bi-ladder::before {content:"\f452";} .bi-lamp-fill::before {content:"\f453";} .bi-lamp::before {content:"\f454";} .bi-laptop-fill::before {content:"\f455";} .bi-laptop::before {content:"\f456";} .bi-layer-backward::before {content:"\f457";} .bi-layer-forward::before {content:"\f458";} .bi-layers-fill::before {content:"\f459";} .bi-layers-half::before {content:"\f45a";} .bi-layers::before {content:"\f45b";} .bi-layout-sidebar-inset-reverse::before {content:"\f45c";} .bi-layout-sidebar-inset::before {content:"\f45d";} .bi-layout-sidebar-reverse::before {content:"\f45e";} .bi-layout-sidebar::before {content:"\f45f";} .bi-layout-split::before {content:"\f460";} .bi-layout-text-sidebar-reverse::before {content:"\f461";} .bi-layout-text-sidebar::before {content:"\f462";} .bi-layout-text-window-reverse::before {content:"\f463";} .bi-layout-text-window::before {content:"\f464";} .bi-layout-three-columns::before {content:"\f465";} .bi-layout-wtf::before {content:"\f466";} .bi-life-preserver::before {content:"\f467";} .bi-lightbulb-fill::before {content:"\f468";} .bi-lightbulb-off-fill::before {content:"\f469";} .bi-lightbulb-off::before {content:"\f46a";} .bi-lightbulb::before {content:"\f46b";} .bi-lightning-charge-fill::before {content:"\f46c";} .bi-lightning-charge::before {content:"\f46d";} .bi-lightning-fill::before {content:"\f46e";} .bi-lightning::before {content:"\f46f";} .bi-link-45deg::before {content:"\f470";} .bi-link::before {content:"\f471";} .bi-linkedin::before {content:"\f472";} .bi-list-check::before {content:"\f473";} .bi-list-nested::before {content:"\f474";} .bi-list-ol::before {content:"\f475";} .bi-list-stars::before {content:"\f476";} .bi-list-task::before {content:"\f477";} .bi-list-ul::before {content:"\f478";} .bi-list::before {content:"\f479";} .bi-lock-fill::before {content:"\f47a";} .bi-lock::before {content:"\f47b";} .bi-mailbox::before {content:"\f47c";} .bi-mailbox2::before {content:"\f47d";} .bi-map-fill::before {content:"\f47e";} .bi-map::before {content:"\f47f";} .bi-markdown-fill::before {content:"\f480";} .bi-markdown::before {content:"\f481";} .bi-mask::before {content:"\f482";} .bi-megaphone-fill::before {content:"\f483";} .bi-megaphone::before {content:"\f484";} .bi-menu-app-fill::before {content:"\f485";} .bi-menu-app::before {content:"\f486";} .bi-menu-button-fill::before {content:"\f487";} .bi-menu-button-wide-fill::before {content:"\f488";} .bi-menu-button-wide::before {content:"\f489";} .bi-menu-button::before {content:"\f48a";} .bi-menu-down::before {content:"\f48b";} .bi-menu-up::before {content:"\f48c";} .bi-mic-fill::before {content:"\f48d";} .bi-mic-mute-fill::before {content:"\f48e";} .bi-mic-mute::before {content:"\f48f";} .bi-mic::before {content:"\f490";} .bi-minecart-loaded::before {content:"\f491";} .bi-minecart::before {content:"\f492";} .bi-moisture::before {content:"\f493";} .bi-moon-fill::before {content:"\f494";} .bi-moon-stars-fill::before {content:"\f495";} .bi-moon-stars::before {content:"\f496";} .bi-moon::before {content:"\f497";} .bi-mouse-fill::before {content:"\f498";} .bi-mouse::before {content:"\f499";} .bi-mouse2-fill::before {content:"\f49a";} .bi-mouse2::before {content:"\f49b";} .bi-mouse3-fill::before {content:"\f49c";} .bi-mouse3::before {content:"\f49d";} .bi-music-note-beamed::before {content:"\f49e";} .bi-music-note-list::before {content:"\f49f";} .bi-music-note::before {content:"\f4a0";} .bi-music-player-fill::before {content:"\f4a1";} .bi-music-player::before {content:"\f4a2";} .bi-newspaper::before {content:"\f4a3";} .bi-node-minus-fill::before {content:"\f4a4";} .bi-node-minus::before {content:"\f4a5";} .bi-node-plus-fill::before {content:"\f4a6";} .bi-node-plus::before {content:"\f4a7";} .bi-nut-fill::before {content:"\f4a8";} .bi-nut::before {content:"\f4a9";} .bi-octagon-fill::before {content:"\f4aa";} .bi-octagon-half::before {content:"\f4ab";} .bi-octagon::before {content:"\f4ac";} .bi-option::before {content:"\f4ad";} .bi-outlet::before {content:"\f4ae";} .bi-paint-bucket::before {content:"\f4af";} .bi-palette-fill::before {content:"\f4b0";} .bi-palette::before {content:"\f4b1";} .bi-palette2::before {content:"\f4b2";} .bi-paperclip::before {content:"\f4b3";} .bi-paragraph::before {content:"\f4b4";} .bi-patch-check-fill::before {content:"\f4b5";} .bi-patch-check::before {content:"\f4b6";} .bi-patch-exclamation-fill::before {content:"\f4b7";} .bi-patch-exclamation::before {content:"\f4b8";} .bi-patch-minus-fill::before {content:"\f4b9";} .bi-patch-minus::before {content:"\f4ba";} .bi-patch-plus-fill::before {content:"\f4bb";} .bi-patch-plus::before {content:"\f4bc";} .bi-patch-question-fill::before {content:"\f4bd";} .bi-patch-question::before {content:"\f4be";} .bi-pause-btn-fill::before {content:"\f4bf";} .bi-pause-btn::before {content:"\f4c0";} .bi-pause-circle-fill::before {content:"\f4c1";} .bi-pause-circle::before {content:"\f4c2";} .bi-pause-fill::before {content:"\f4c3";} .bi-pause::before {content:"\f4c4";} .bi-peace-fill::before {content:"\f4c5";} .bi-peace::before {content:"\f4c6";} .bi-pen-fill::before {content:"\f4c7";} .bi-pen::before {content:"\f4c8";} .bi-pencil-fill::before {content:"\f4c9";} .bi-pencil-square::before {content:"\f4ca";} .bi-pencil::before {content:"\f4cb";} .bi-pentagon-fill::before {content:"\f4cc";} .bi-pentagon-half::before {content:"\f4cd";} .bi-pentagon::before {content:"\f4ce";} .bi-people-fill::before {content:"\f4cf";} .bi-people::before {content:"\f4d0";} .bi-percent::before {content:"\f4d1";} .bi-person-badge-fill::before {content:"\f4d2";} .bi-person-badge::before {content:"\f4d3";} .bi-person-bounding-box::before {content:"\f4d4";} .bi-person-check-fill::before {content:"\f4d5";} .bi-person-check::before {content:"\f4d6";} .bi-person-circle::before {content:"\f4d7";} .bi-person-dash-fill::before {content:"\f4d8";} .bi-person-dash::before {content:"\f4d9";} .bi-person-fill::before {content:"\f4da";} .bi-person-lines-fill::before {content:"\f4db";} .bi-person-plus-fill::before {content:"\f4dc";} .bi-person-plus::before {content:"\f4dd";} .bi-person-square::before {content:"\f4de";} .bi-person-x-fill::before {content:"\f4df";} .bi-person-x::before {content:"\f4e0";} .bi-person::before {content:"\f4e1";} .bi-phone-fill::before {content:"\f4e2";} .bi-phone-landscape-fill::before {content:"\f4e3";} .bi-phone-landscape::before {content:"\f4e4";} .bi-phone-vibrate-fill::before {content:"\f4e5";} .bi-phone-vibrate::before {content:"\f4e6";} .bi-phone::before {content:"\f4e7";} .bi-pie-chart-fill::before {content:"\f4e8";} .bi-pie-chart::before {content:"\f4e9";} .bi-pin-angle-fill::before {content:"\f4ea";} .bi-pin-angle::before {content:"\f4eb";} .bi-pin-fill::before {content:"\f4ec";} .bi-pin::before {content:"\f4ed";} .bi-pip-fill::before {content:"\f4ee";} .bi-pip::before {content:"\f4ef";} .bi-play-btn-fill::before {content:"\f4f0";} .bi-play-btn::before {content:"\f4f1";} .bi-play-circle-fill::before {content:"\f4f2";} .bi-play-circle::before {content:"\f4f3";} .bi-play-fill::before {content:"\f4f4";} .bi-play::before {content:"\f4f5";} .bi-plug-fill::before {content:"\f4f6";} .bi-plug::before {content:"\f4f7";} .bi-plus-circle-dotted::before {content:"\f4f8";} .bi-plus-circle-fill::before {content:"\f4f9";} .bi-plus-circle::before {content:"\f4fa";} .bi-plus-square-dotted::before {content:"\f4fb";} .bi-plus-square-fill::before {content:"\f4fc";} .bi-plus-square::before {content:"\f4fd";} .bi-plus::before {content:"\f4fe";} .bi-power::before {content:"\f4ff";} .bi-printer-fill::before {content:"\f500";} .bi-printer::before {content:"\f501";} .bi-puzzle-fill::before {content:"\f502";} .bi-puzzle::before {content:"\f503";} .bi-question-circle-fill::before {content:"\f504";} .bi-question-circle::before {content:"\f505";} .bi-question-diamond-fill::before {content:"\f506";} .bi-question-diamond::before {content:"\f507";} .bi-question-octagon-fill::before {content:"\f508";} .bi-question-octagon::before {content:"\f509";} .bi-question-square-fill::before {content:"\f50a";} .bi-question-square::before {content:"\f50b";} .bi-question::before {content:"\f50c";} .bi-rainbow::before {content:"\f50d";} .bi-receipt-cutoff::before {content:"\f50e";} .bi-receipt::before {content:"\f50f";} .bi-reception-0::before {content:"\f510";} .bi-reception-1::before {content:"\f511";} .bi-reception-2::before {content:"\f512";} .bi-reception-3::before {content:"\f513";} .bi-reception-4::before {content:"\f514";} .bi-record-btn-fill::before {content:"\f515";} .bi-record-btn::before {content:"\f516";} .bi-record-circle-fill::before {content:"\f517";} .bi-record-circle::before {content:"\f518";} .bi-record-fill::before {content:"\f519";} .bi-record::before {content:"\f51a";} .bi-record2-fill::before {content:"\f51b";} .bi-record2::before {content:"\f51c";} .bi-reply-all-fill::before {content:"\f51d";} .bi-reply-all::before {content:"\f51e";} .bi-reply-fill::before {content:"\f51f";} .bi-reply::before {content:"\f520";} .bi-rss-fill::before {content:"\f521";} .bi-rss::before {content:"\f522";} .bi-rulers::before {content:"\f523";} .bi-save-fill::before {content:"\f524";} .bi-save::before {content:"\f525";} .bi-save2-fill::before {content:"\f526";} .bi-save2::before {content:"\f527";} .bi-scissors::before {content:"\f528";} .bi-screwdriver::before {content:"\f529";} .bi-search::before {content:"\f52a";} .bi-segmented-nav::before {content:"\f52b";} .bi-server::before {content:"\f52c";} .bi-share-fill::before {content:"\f52d";} .bi-share::before {content:"\f52e";} .bi-shield-check::before {content:"\f52f";} .bi-shield-exclamation::before {content:"\f530";} .bi-shield-fill-check::before {content:"\f531";} .bi-shield-fill-exclamation::before {content:"\f532";} .bi-shield-fill-minus::before {content:"\f533";} .bi-shield-fill-plus::before {content:"\f534";} .bi-shield-fill-x::before {content:"\f535";} .bi-shield-fill::before {content:"\f536";} .bi-shield-lock-fill::before {content:"\f537";} .bi-shield-lock::before {content:"\f538";} .bi-shield-minus::before {content:"\f539";} .bi-shield-plus::before {content:"\f53a";} .bi-shield-shaded::before {content:"\f53b";} .bi-shield-slash-fill::before {content:"\f53c";} .bi-shield-slash::before {content:"\f53d";} .bi-shield-x::before {content:"\f53e";} .bi-shield::before {content:"\f53f";} .bi-shift-fill::before {content:"\f540";} .bi-shift::before {content:"\f541";} .bi-shop-window::before {content:"\f542";} .bi-shop::before {content:"\f543";} .bi-shuffle::before {content:"\f544";} .bi-signpost-2-fill::before {content:"\f545";} .bi-signpost-2::before {content:"\f546";} .bi-signpost-fill::before {content:"\f547";} .bi-signpost-split-fill::before {content:"\f548";} .bi-signpost-split::before {content:"\f549";} .bi-signpost::before {content:"\f54a";} .bi-sim-fill::before {content:"\f54b";} .bi-sim::before {content:"\f54c";} .bi-skip-backward-btn-fill::before {content:"\f54d";} .bi-skip-backward-btn::before {content:"\f54e";} .bi-skip-backward-circle-fill::before {content:"\f54f";} .bi-skip-backward-circle::before {content:"\f550";} .bi-skip-backward-fill::before {content:"\f551";} .bi-skip-backward::before {content:"\f552";} .bi-skip-end-btn-fill::before {content:"\f553";} .bi-skip-end-btn::before {content:"\f554";} .bi-skip-end-circle-fill::before {content:"\f555";} .bi-skip-end-circle::before {content:"\f556";} .bi-skip-end-fill::before {content:"\f557";} .bi-skip-end::before {content:"\f558";} .bi-skip-forward-btn-fill::before {content:"\f559";} .bi-skip-forward-btn::before {content:"\f55a";} .bi-skip-forward-circle-fill::before {content:"\f55b";} .bi-skip-forward-circle::before {content:"\f55c";} .bi-skip-forward-fill::before {content:"\f55d";} .bi-skip-forward::before {content:"\f55e";} .bi-skip-start-btn-fill::before {content:"\f55f";} .bi-skip-start-btn::before {content:"\f560";} .bi-skip-start-circle-fill::before {content:"\f561";} .bi-skip-start-circle::before {content:"\f562";} .bi-skip-start-fill::before {content:"\f563";} .bi-skip-start::before {content:"\f564";} .bi-slack::before {content:"\f565";} .bi-slash-circle-fill::before {content:"\f566";} .bi-slash-circle::before {content:"\f567";} .bi-slash-square-fill::before {content:"\f568";} .bi-slash-square::before {content:"\f569";} .bi-slash::before {content:"\f56a";} .bi-sliders::before {content:"\f56b";} .bi-smartwatch::before {content:"\f56c";} .bi-snow::before {content:"\f56d";} .bi-snow2::before {content:"\f56e";} .bi-snow3::before {content:"\f56f";} .bi-sort-alpha-down-alt::before {content:"\f570";} .bi-sort-alpha-down::before {content:"\f571";} .bi-sort-alpha-up-alt::before {content:"\f572";} .bi-sort-alpha-up::before {content:"\f573";} .bi-sort-down-alt::before {content:"\f574";} .bi-sort-down::before {content:"\f575";} .bi-sort-numeric-down-alt::before {content:"\f576";} .bi-sort-numeric-down::before {content:"\f577";} .bi-sort-numeric-up-alt::before {content:"\f578";} .bi-sort-numeric-up::before {content:"\f579";} .bi-sort-up-alt::before {content:"\f57a";} .bi-sort-up::before {content:"\f57b";} .bi-soundwave::before {content:"\f57c";} .bi-speaker-fill::before {content:"\f57d";} .bi-speaker::before {content:"\f57e";} .bi-speedometer::before {content:"\f57f";} .bi-speedometer2::before {content:"\f580";} .bi-spellcheck::before {content:"\f581";} .bi-square-fill::before {content:"\f582";} .bi-square-half::before {content:"\f583";} .bi-square::before {content:"\f584";} .bi-stack::before {content:"\f585";} .bi-star-fill::before {content:"\f586";} .bi-star-half::before {content:"\f587";} .bi-star::before {content:"\f588";} .bi-stars::before {content:"\f589";} .bi-stickies-fill::before {content:"\f58a";} .bi-stickies::before {content:"\f58b";} .bi-sticky-fill::before {content:"\f58c";} .bi-sticky::before {content:"\f58d";} .bi-stop-btn-fill::before {content:"\f58e";} .bi-stop-btn::before {content:"\f58f";} .bi-stop-circle-fill::before {content:"\f590";} .bi-stop-circle::before {content:"\f591";} .bi-stop-fill::before {content:"\f592";} .bi-stop::before {content:"\f593";} .bi-stoplights-fill::before {content:"\f594";} .bi-stoplights::before {content:"\f595";} .bi-stopwatch-fill::before {content:"\f596";} .bi-stopwatch::before {content:"\f597";} .bi-subtract::before {content:"\f598";} .bi-suit-club-fill::before {content:"\f599";} .bi-suit-club::before {content:"\f59a";} .bi-suit-diamond-fill::before {content:"\f59b";} .bi-suit-diamond::before {content:"\f59c";} .bi-suit-heart-fill::before {content:"\f59d";} .bi-suit-heart::before {content:"\f59e";} .bi-suit-spade-fill::before {content:"\f59f";} .bi-suit-spade::before {content:"\f5a0";} .bi-sun-fill::before {content:"\f5a1";} .bi-sun::before {content:"\f5a2";} .bi-sunglasses::before {content:"\f5a3";} .bi-sunrise-fill::before {content:"\f5a4";} .bi-sunrise::before {content:"\f5a5";} .bi-sunset-fill::before {content:"\f5a6";} .bi-sunset::before {content:"\f5a7";} .bi-symmetry-horizontal::before {content:"\f5a8";} .bi-symmetry-vertical::before {content:"\f5a9";} .bi-table::before {content:"\f5aa";} .bi-tablet-fill::before {content:"\f5ab";} .bi-tablet-landscape-fill::before {content:"\f5ac";} .bi-tablet-landscape::before {content:"\f5ad";} .bi-tablet::before {content:"\f5ae";} .bi-tag-fill::before {content:"\f5af";} .bi-tag::before {content:"\f5b0";} .bi-tags-fill::before {content:"\f5b1";} .bi-tags::before {content:"\f5b2";} .bi-telegram::before {content:"\f5b3";} .bi-telephone-fill::before {content:"\f5b4";} .bi-telephone-forward-fill::before {content:"\f5b5";} .bi-telephone-forward::before {content:"\f5b6";} .bi-telephone-inbound-fill::before {content:"\f5b7";} .bi-telephone-inbound::before {content:"\f5b8";} .bi-telephone-minus-fill::before {content:"\f5b9";} .bi-telephone-minus::before {content:"\f5ba";} .bi-telephone-outbound-fill::before {content:"\f5bb";} .bi-telephone-outbound::before {content:"\f5bc";} .bi-telephone-plus-fill::before {content:"\f5bd";} .bi-telephone-plus::before {content:"\f5be";} .bi-telephone-x-fill::before {content:"\f5bf";} .bi-telephone-x::before {content:"\f5c0";} .bi-telephone::before {content:"\f5c1";} .bi-terminal-fill::before {content:"\f5c2";} .bi-terminal::before {content:"\f5c3";} .bi-text-center::before {content:"\f5c4";} .bi-text-indent-left::before {content:"\f5c5";} .bi-text-indent-right::before {content:"\f5c6";} .bi-text-left::before {content:"\f5c7";} .bi-text-paragraph::before {content:"\f5c8";} .bi-text-right::before {content:"\f5c9";} .bi-textarea-resize::before {content:"\f5ca";} .bi-textarea-t::before {content:"\f5cb";} .bi-textarea::before {content:"\f5cc";} .bi-thermometer-half::before {content:"\f5cd";} .bi-thermometer-high::before {content:"\f5ce";} .bi-thermometer-low::before {content:"\f5cf";} .bi-thermometer-snow::before {content:"\f5d0";} .bi-thermometer-sun::before {content:"\f5d1";} .bi-thermometer::before {content:"\f5d2";} .bi-three-dots-vertical::before {content:"\f5d3";} .bi-three-dots::before {content:"\f5d4";} .bi-toggle-off::before {content:"\f5d5";} .bi-toggle-on::before {content:"\f5d6";} .bi-toggle2-off::before {content:"\f5d7";} .bi-toggle2-on::before {content:"\f5d8";} .bi-toggles::before {content:"\f5d9";} .bi-toggles2::before {content:"\f5da";} .bi-tools::before {content:"\f5db";} .bi-tornado::before {content:"\f5dc";} .bi-trash-fill::before {content:"\f5dd";} .bi-trash::before {content:"\f5de";} .bi-trash2-fill::before {content:"\f5df";} .bi-trash2::before {content:"\f5e0";} .bi-tree-fill::before {content:"\f5e1";} .bi-tree::before {content:"\f5e2";} .bi-triangle-fill::before {content:"\f5e3";} .bi-triangle-half::before {content:"\f5e4";} .bi-triangle::before {content:"\f5e5";} .bi-trophy-fill::before {content:"\f5e6";} .bi-trophy::before {content:"\f5e7";} .bi-tropical-storm::before {content:"\f5e8";} .bi-truck-flatbed::before {content:"\f5e9";} .bi-truck::before {content:"\f5ea";} .bi-tsunami::before {content:"\f5eb";} .bi-tv-fill::before {content:"\f5ec";} .bi-tv::before {content:"\f5ed";} .bi-twitch::before {content:"\f5ee";} .bi-twitter::before {content:"\f5ef";} .bi-type-bold::before {content:"\f5f0";} .bi-type-h1::before {content:"\f5f1";} .bi-type-h2::before {content:"\f5f2";} .bi-type-h3::before {content:"\f5f3";} .bi-type-italic::before {content:"\f5f4";} .bi-type-strikethrough::before {content:"\f5f5";} .bi-type-underline::before {content:"\f5f6";} .bi-type::before {content:"\f5f7";} .bi-ui-checks-grid::before {content:"\f5f8";} .bi-ui-checks::before {content:"\f5f9";} .bi-ui-radios-grid::before {content:"\f5fa";} .bi-ui-radios::before {content:"\f5fb";} .bi-umbrella-fill::before {content:"\f5fc";} .bi-umbrella::before {content:"\f5fd";} .bi-union::before {content:"\f5fe";} .bi-unlock-fill::before {content:"\f5ff";} .bi-unlock::before {content:"\f600";} .bi-upc-scan::before {content:"\f601";} .bi-upc::before {content:"\f602";} .bi-upload::before {content:"\f603";} .bi-vector-pen::before {content:"\f604";} .bi-view-list::before {content:"\f605";} .bi-view-stacked::before {content:"\f606";} .bi-vinyl-fill::before {content:"\f607";} .bi-vinyl::before {content:"\f608";} .bi-voicemail::before {content:"\f609";} .bi-volume-down-fill::before {content:"\f60a";} .bi-volume-down::before {content:"\f60b";} .bi-volume-mute-fill::before {content:"\f60c";} .bi-volume-mute::before {content:"\f60d";} .bi-volume-off-fill::before {content:"\f60e";} .bi-volume-off::before {content:"\f60f";} .bi-volume-up-fill::before {content:"\f610";} .bi-volume-up::before {content:"\f611";} .bi-vr::before {content:"\f612";} .bi-wallet-fill::before {content:"\f613";} .bi-wallet::before {content:"\f614";} .bi-wallet2::before {content:"\f615";} .bi-watch::before {content:"\f616";} .bi-water::before {content:"\f617";} .bi-whatsapp::before {content:"\f618";} .bi-wifi-1::before {content:"\f619";} .bi-wifi-2::before {content:"\f61a";} .bi-wifi-off::before {content:"\f61b";} .bi-wifi::before {content:"\f61c";} .bi-wind::before {content:"\f61d";} .bi-window-dock::before {content:"\f61e";} .bi-window-sidebar::before {content:"\f61f";} .bi-window::before {content:"\f620";} .bi-wrench::before {content:"\f621";} .bi-x-circle-fill::before {content:"\f622";} .bi-x-circle::before {content:"\f623";} .bi-x-diamond-fill::before {content:"\f624";} .bi-x-diamond::before {content:"\f625";} .bi-x-octagon-fill::before {content:"\f626";} .bi-x-octagon::before {content:"\f627";} .bi-x-square-fill::before {content:"\f628";} .bi-x-square::before {content:"\f629";} .bi-x::before {content:"\f62a";} .bi-youtube::before {content:"\f62b";} .bi-zoom-in::before {content:"\f62c";} .bi-zoom-out::before {content:"\f62d";} .bi-bank::before {content:"\f62e";} .bi-bank2::before {content:"\f62f";} .bi-bell-slash-fill::before {content:"\f630";} .bi-bell-slash::before {content:"\f631";} .bi-cash-coin::before {content:"\f632";} .bi-check-lg::before {content:"\f633";} .bi-coin::before {content:"\f634";} .bi-currency-bitcoin::before {content:"\f635";} .bi-currency-dollar::before {content:"\f636";} .bi-currency-euro::before {content:"\f637";} .bi-currency-exchange::before {content:"\f638";} .bi-currency-pound::before {content:"\f639";} .bi-currency-yen::before {content:"\f63a";} .bi-dash-lg::before {content:"\f63b";} .bi-exclamation-lg::before {content:"\f63c";} .bi-file-earmark-pdf-fill::before {content:"\f63d";} .bi-file-earmark-pdf::before {content:"\f63e";} .bi-file-pdf-fill::before {content:"\f63f";} .bi-file-pdf::before {content:"\f640";} .bi-gender-ambiguous::before {content:"\f641";} .bi-gender-female::before {content:"\f642";} .bi-gender-male::before {content:"\f643";} .bi-gender-trans::before {content:"\f644";} .bi-headset-vr::before {content:"\f645";} .bi-info-lg::before {content:"\f646";} .bi-mastodon::before {content:"\f647";} .bi-messenger::before {content:"\f648";} .bi-piggy-bank-fill::before {content:"\f649";} .bi-piggy-bank::before {content:"\f64a";} .bi-pin-map-fill::before {content:"\f64b";} .bi-pin-map::before {content:"\f64c";} .bi-plus-lg::before {content:"\f64d";} .bi-question-lg::before {content:"\f64e";} .bi-recycle::before {content:"\f64f";} .bi-reddit::before {content:"\f650";} .bi-safe-fill::before {content:"\f651";} .bi-safe2-fill::before {content:"\f652";} .bi-safe2::before {content:"\f653";} .bi-sd-card-fill::before {content:"\f654";} .bi-sd-card::before {content:"\f655";} .bi-skype::before {content:"\f656";} .bi-slash-lg::before {content:"\f657";} .bi-translate::before {content:"\f658";} .bi-x-lg::before {content:"\f659";} .bi-safe::before {content:"\f65a";} .bi-apple::before {content:"\f65b";} .bi-microsoft::before {content:"\f65d";} .bi-windows::before {content:"\f65e";} .bi-behance::before {content:"\f65c";} .bi-dribbble::before {content:"\f65f";} .bi-line::before {content:"\f660";} .bi-medium::before {content:"\f661";} .bi-paypal::before {content:"\f662";} .bi-pinterest::before {content:"\f663";} .bi-signal::before {content:"\f664";} .bi-snapchat::before {content:"\f665";} .bi-spotify::before {content:"\f666";} .bi-stack-overflow::before {content:"\f667";} .bi-strava::before {content:"\f668";} .bi-wordpress::before {content:"\f669";} .bi-vimeo::before {content:"\f66a";} .bi-activity::before {content:"\f66b";} .bi-easel2-fill::before {content:"\f66c";} .bi-easel2::before {content:"\f66d";} .bi-easel3-fill::before {content:"\f66e";} .bi-easel3::before {content:"\f66f";} .bi-fan::before {content:"\f670";} .bi-fingerprint::before {content:"\f671";} .bi-graph-down-arrow::before {content:"\f672";} .bi-graph-up-arrow::before {content:"\f673";} .bi-hypnotize::before {content:"\f674";} .bi-magic::before {content:"\f675";} .bi-person-rolodex::before {content:"\f676";} .bi-person-video::before {content:"\f677";} .bi-person-video2::before {content:"\f678";} .bi-person-video3::before {content:"\f679";} .bi-person-workspace::before {content:"\f67a";} .bi-radioactive::before {content:"\f67b";} .bi-webcam-fill::before {content:"\f67c";} .bi-webcam::before {content:"\f67d";} .bi-yin-yang::before {content:"\f67e";} .bi-bandaid-fill::before {content:"\f680";} .bi-bandaid::before {content:"\f681";} .bi-bluetooth::before {content:"\f682";} .bi-body-text::before {content:"\f683";} .bi-boombox::before {content:"\f684";} .bi-boxes::before {content:"\f685";} .bi-dpad-fill::before {content:"\f686";} .bi-dpad::before {content:"\f687";} .bi-ear-fill::before {content:"\f688";} .bi-ear::before {content:"\f689";} .bi-envelope-check-fill::before {content:"\f68b";} .bi-envelope-check::before {content:"\f68c";} .bi-envelope-dash-fill::before {content:"\f68e";} .bi-envelope-dash::before {content:"\f68f";} .bi-envelope-exclamation-fill::before {content:"\f691";} .bi-envelope-exclamation::before {content:"\f692";} .bi-envelope-plus-fill::before {content:"\f693";} .bi-envelope-plus::before {content:"\f694";} .bi-envelope-slash-fill::before {content:"\f696";} .bi-envelope-slash::before {content:"\f697";} .bi-envelope-x-fill::before {content:"\f699";} .bi-envelope-x::before {content:"\f69a";} .bi-explicit-fill::before {content:"\f69b";} .bi-explicit::before {content:"\f69c";} .bi-git::before {content:"\f69d";} .bi-infinity::before {content:"\f69e";} .bi-list-columns-reverse::before {content:"\f69f";} .bi-list-columns::before {content:"\f6a0";} .bi-meta::before {content:"\f6a1";} .bi-nintendo-switch::before {content:"\f6a4";} .bi-pc-display-horizontal::before {content:"\f6a5";} .bi-pc-display::before {content:"\f6a6";} .bi-pc-horizontal::before {content:"\f6a7";} .bi-pc::before {content:"\f6a8";} .bi-playstation::before {content:"\f6a9";} .bi-plus-slash-minus::before {content:"\f6aa";} .bi-projector-fill::before {content:"\f6ab";} .bi-projector::before {content:"\f6ac";} .bi-qr-code-scan::before {content:"\f6ad";} .bi-qr-code::before {content:"\f6ae";} .bi-quora::before {content:"\f6af";} .bi-quote::before {content:"\f6b0";} .bi-robot::before {content:"\f6b1";} .bi-send-check-fill::before {content:"\f6b2";} .bi-send-check::before {content:"\f6b3";} .bi-send-dash-fill::before {content:"\f6b4";} .bi-send-dash::before {content:"\f6b5";} .bi-send-exclamation-fill::before {content:"\f6b7";} .bi-send-exclamation::before {content:"\f6b8";} .bi-send-fill::before {content:"\f6b9";} .bi-send-plus-fill::before {content:"\f6ba";} .bi-send-plus::before {content:"\f6bb";} .bi-send-slash-fill::before {content:"\f6bc";} .bi-send-slash::before {content:"\f6bd";} .bi-send-x-fill::before {content:"\f6be";} .bi-send-x::before {content:"\f6bf";} .bi-send::before {content:"\f6c0";} .bi-steam::before {content:"\f6c1";} .bi-terminal-dash::before {content:"\f6c3";} .bi-terminal-plus::before {content:"\f6c4";} .bi-terminal-split::before {content:"\f6c5";} .bi-ticket-detailed-fill::before {content:"\f6c6";} .bi-ticket-detailed::before {content:"\f6c7";} .bi-ticket-fill::before {content:"\f6c8";} .bi-ticket-perforated-fill::before {content:"\f6c9";} .bi-ticket-perforated::before {content:"\f6ca";} .bi-ticket::before {content:"\f6cb";} .bi-tiktok::before {content:"\f6cc";} .bi-window-dash::before {content:"\f6cd";} .bi-window-desktop::before {content:"\f6ce";} .bi-window-fullscreen::before {content:"\f6cf";} .bi-window-plus::before {content:"\f6d0";} .bi-window-split::before {content:"\f6d1";} .bi-window-stack::before {content:"\f6d2";} .bi-window-x::before {content:"\f6d3";} .bi-xbox::before {content:"\f6d4";} .bi-ethernet::before {content:"\f6d5";} .bi-hdmi-fill::before {content:"\f6d6";} .bi-hdmi::before {content:"\f6d7";} .bi-usb-c-fill::before {content:"\f6d8";} .bi-usb-c::before {content:"\f6d9";} .bi-usb-fill::before {content:"\f6da";} .bi-usb-plug-fill::before {content:"\f6db";} .bi-usb-plug::before {content:"\f6dc";} .bi-usb-symbol::before {content:"\f6dd";} .bi-usb::before {content:"\f6de";} .bi-boombox-fill::before {content:"\f6df";} .bi-displayport::before {content:"\f6e1";} .bi-gpu-card::before {content:"\f6e2";} .bi-memory::before {content:"\f6e3";} .bi-modem-fill::before {content:"\f6e4";} .bi-modem::before {content:"\f6e5";} .bi-motherboard-fill::before {content:"\f6e6";} .bi-motherboard::before {content:"\f6e7";} .bi-optical-audio-fill::before {content:"\f6e8";} .bi-optical-audio::before {content:"\f6e9";} .bi-pci-card::before {content:"\f6ea";} .bi-router-fill::before {content:"\f6eb";} .bi-router::before {content:"\f6ec";} .bi-thunderbolt-fill::before {content:"\f6ef";} .bi-thunderbolt::before {content:"\f6f0";} .bi-usb-drive-fill::before {content:"\f6f1";} .bi-usb-drive::before {content:"\f6f2";} .bi-usb-micro-fill::before {content:"\f6f3";} .bi-usb-micro::before {content:"\f6f4";} .bi-usb-mini-fill::before {content:"\f6f5";} .bi-usb-mini::before {content:"\f6f6";} .bi-cloud-haze2::before {content:"\f6f7";} .bi-device-hdd-fill::before {content:"\f6f8";} .bi-device-hdd::before {content:"\f6f9";} .bi-device-ssd-fill::before {content:"\f6fa";} .bi-device-ssd::before {content:"\f6fb";} .bi-displayport-fill::before {content:"\f6fc";} .bi-mortarboard-fill::before {content:"\f6fd";} .bi-mortarboard::before {content:"\f6fe";} .bi-terminal-x::before {content:"\f6ff";} .bi-arrow-through-heart-fill::before {content:"\f700";} .bi-arrow-through-heart::before {content:"\f701";} .bi-badge-sd-fill::before {content:"\f702";} .bi-badge-sd::before {content:"\f703";} .bi-bag-heart-fill::before {content:"\f704";} .bi-bag-heart::before {content:"\f705";} .bi-balloon-fill::before {content:"\f706";} .bi-balloon-heart-fill::before {content:"\f707";} .bi-balloon-heart::before {content:"\f708";} .bi-balloon::before {content:"\f709";} .bi-box2-fill::before {content:"\f70a";} .bi-box2-heart-fill::before {content:"\f70b";} .bi-box2-heart::before {content:"\f70c";} .bi-box2::before {content:"\f70d";} .bi-braces-asterisk::before {content:"\f70e";} .bi-calendar-heart-fill::before {content:"\f70f";} .bi-calendar-heart::before {content:"\f710";} .bi-calendar2-heart-fill::before {content:"\f711";} .bi-calendar2-heart::before {content:"\f712";} .bi-chat-heart-fill::before {content:"\f713";} .bi-chat-heart::before {content:"\f714";} .bi-chat-left-heart-fill::before {content:"\f715";} .bi-chat-left-heart::before {content:"\f716";} .bi-chat-right-heart-fill::before {content:"\f717";} .bi-chat-right-heart::before {content:"\f718";} .bi-chat-square-heart-fill::before {content:"\f719";} .bi-chat-square-heart::before {content:"\f71a";} .bi-clipboard-check-fill::before {content:"\f71b";} .bi-clipboard-data-fill::before {content:"\f71c";} .bi-clipboard-fill::before {content:"\f71d";} .bi-clipboard-heart-fill::before {content:"\f71e";} .bi-clipboard-heart::before {content:"\f71f";} .bi-clipboard-minus-fill::before {content:"\f720";} .bi-clipboard-plus-fill::before {content:"\f721";} .bi-clipboard-pulse::before {content:"\f722";} .bi-clipboard-x-fill::before {content:"\f723";} .bi-clipboard2-check-fill::before {content:"\f724";} .bi-clipboard2-check::before {content:"\f725";} .bi-clipboard2-data-fill::before {content:"\f726";} .bi-clipboard2-data::before {content:"\f727";} .bi-clipboard2-fill::before {content:"\f728";} .bi-clipboard2-heart-fill::before {content:"\f729";} .bi-clipboard2-heart::before {content:"\f72a";} .bi-clipboard2-minus-fill::before {content:"\f72b";} .bi-clipboard2-minus::before {content:"\f72c";} .bi-clipboard2-plus-fill::before {content:"\f72d";} .bi-clipboard2-plus::before {content:"\f72e";} .bi-clipboard2-pulse-fill::before {content:"\f72f";} .bi-clipboard2-pulse::before {content:"\f730";} .bi-clipboard2-x-fill::before {content:"\f731";} .bi-clipboard2-x::before {content:"\f732";} .bi-clipboard2::before {content:"\f733";} .bi-emoji-kiss-fill::before {content:"\f734";} .bi-emoji-kiss::before {content:"\f735";} .bi-envelope-heart-fill::before {content:"\f736";} .bi-envelope-heart::before {content:"\f737";} .bi-envelope-open-heart-fill::before {content:"\f738";} .bi-envelope-open-heart::before {content:"\f739";} .bi-envelope-paper-fill::before {content:"\f73a";} .bi-envelope-paper-heart-fill::before {content:"\f73b";} .bi-envelope-paper-heart::before {content:"\f73c";} .bi-envelope-paper::before {content:"\f73d";} .bi-filetype-aac::before {content:"\f73e";} .bi-filetype-ai::before {content:"\f73f";} .bi-filetype-bmp::before {content:"\f740";} .bi-filetype-cs::before {content:"\f741";} .bi-filetype-css::before {content:"\f742";} .bi-filetype-csv::before {content:"\f743";} .bi-filetype-doc::before {content:"\f744";} .bi-filetype-docx::before {content:"\f745";} .bi-filetype-exe::before {content:"\f746";} .bi-filetype-gif::before {content:"\f747";} .bi-filetype-heic::before {content:"\f748";} .bi-filetype-html::before {content:"\f749";} .bi-filetype-java::before {content:"\f74a";} .bi-filetype-jpg::before {content:"\f74b";} .bi-filetype-js::before {content:"\f74c";} .bi-filetype-jsx::before {content:"\f74d";} .bi-filetype-key::before {content:"\f74e";} .bi-filetype-m4p::before {content:"\f74f";} .bi-filetype-md::before {content:"\f750";} .bi-filetype-mdx::before {content:"\f751";} .bi-filetype-mov::before {content:"\f752";} .bi-filetype-mp3::before {content:"\f753";} .bi-filetype-mp4::before {content:"\f754";} .bi-filetype-otf::before {content:"\f755";} .bi-filetype-pdf::before {content:"\f756";} .bi-filetype-php::before {content:"\f757";} .bi-filetype-png::before {content:"\f758";} .bi-filetype-ppt::before {content:"\f75a";} .bi-filetype-psd::before {content:"\f75b";} .bi-filetype-py::before {content:"\f75c";} .bi-filetype-raw::before {content:"\f75d";} .bi-filetype-rb::before {content:"\f75e";} .bi-filetype-sass::before {content:"\f75f";} .bi-filetype-scss::before {content:"\f760";} .bi-filetype-sh::before {content:"\f761";} .bi-filetype-svg::before {content:"\f762";} .bi-filetype-tiff::before {content:"\f763";} .bi-filetype-tsx::before {content:"\f764";} .bi-filetype-ttf::before {content:"\f765";} .bi-filetype-txt::before {content:"\f766";} .bi-filetype-wav::before {content:"\f767";} .bi-filetype-woff::before {content:"\f768";} .bi-filetype-xls::before {content:"\f76a";} .bi-filetype-xml::before {content:"\f76b";} .bi-filetype-yml::before {content:"\f76c";} .bi-heart-arrow::before {content:"\f76d";} .bi-heart-pulse-fill::before {content:"\f76e";} .bi-heart-pulse::before {content:"\f76f";} .bi-heartbreak-fill::before {content:"\f770";} .bi-heartbreak::before {content:"\f771";} .bi-hearts::before {content:"\f772";} .bi-hospital-fill::before {content:"\f773";} .bi-hospital::before {content:"\f774";} .bi-house-heart-fill::before {content:"\f775";} .bi-house-heart::before {content:"\f776";} .bi-incognito::before {content:"\f777";} .bi-magnet-fill::before {content:"\f778";} .bi-magnet::before {content:"\f779";} .bi-person-heart::before {content:"\f77a";} .bi-person-hearts::before {content:"\f77b";} .bi-phone-flip::before {content:"\f77c";} .bi-plugin::before {content:"\f77d";} .bi-postage-fill::before {content:"\f77e";} .bi-postage-heart-fill::before {content:"\f77f";} .bi-postage-heart::before {content:"\f780";} .bi-postage::before {content:"\f781";} .bi-postcard-fill::before {content:"\f782";} .bi-postcard-heart-fill::before {content:"\f783";} .bi-postcard-heart::before {content:"\f784";} .bi-postcard::before {content:"\f785";} .bi-search-heart-fill::before {content:"\f786";} .bi-search-heart::before {content:"\f787";} .bi-sliders2-vertical::before {content:"\f788";} .bi-sliders2::before {content:"\f789";} .bi-trash3-fill::before {content:"\f78a";} .bi-trash3::before {content:"\f78b";} .bi-valentine::before {content:"\f78c";} .bi-valentine2::before {content:"\f78d";} .bi-wrench-adjustable-circle-fill::before {content:"\f78e";} .bi-wrench-adjustable-circle::before {content:"\f78f";} .bi-wrench-adjustable::before {content:"\f790";} .bi-filetype-json::before {content:"\f791";} .bi-filetype-pptx::before {content:"\f792";} .bi-filetype-xlsx::before {content:"\f793";} .bi-1-circle-fill::before {content:"\f796";} .bi-1-circle::before {content:"\f797";} .bi-1-square-fill::before {content:"\f798";} .bi-1-square::before {content:"\f799";} .bi-2-circle-fill::before {content:"\f79c";} .bi-2-circle::before {content:"\f79d";} .bi-2-square-fill::before {content:"\f79e";} .bi-2-square::before {content:"\f79f";} .bi-3-circle-fill::before {content:"\f7a2";} .bi-3-circle::before {content:"\f7a3";} .bi-3-square-fill::before {content:"\f7a4";} .bi-3-square::before {content:"\f7a5";} .bi-4-circle-fill::before {content:"\f7a8";} .bi-4-circle::before {content:"\f7a9";} .bi-4-square-fill::before {content:"\f7aa";} .bi-4-square::before {content:"\f7ab";} .bi-5-circle-fill::before {content:"\f7ae";} .bi-5-circle::before {content:"\f7af";} .bi-5-square-fill::before {content:"\f7b0";} .bi-5-square::before {content:"\f7b1";} .bi-6-circle-fill::before {content:"\f7b4";} .bi-6-circle::before {content:"\f7b5";} .bi-6-square-fill::before {content:"\f7b6";} .bi-6-square::before {content:"\f7b7";} .bi-7-circle-fill::before {content:"\f7ba";} .bi-7-circle::before {content:"\f7bb";} .bi-7-square-fill::before {content:"\f7bc";} .bi-7-square::before {content:"\f7bd";} .bi-8-circle-fill::before {content:"\f7c0";} .bi-8-circle::before {content:"\f7c1";} .bi-8-square-fill::before {content:"\f7c2";} .bi-8-square::before {content:"\f7c3";} .bi-9-circle-fill::before {content:"\f7c6";} .bi-9-circle::before {content:"\f7c7";} .bi-9-square-fill::before {content:"\f7c8";} .bi-9-square::before {content:"\f7c9";} .bi-airplane-engines-fill::before {content:"\f7ca";} .bi-airplane-engines::before {content:"\f7cb";} .bi-airplane-fill::before {content:"\f7cc";} .bi-airplane::before {content:"\f7cd";} .bi-alexa::before {content:"\f7ce";} .bi-alipay::before {content:"\f7cf";} .bi-android::before {content:"\f7d0";} .bi-android2::before {content:"\f7d1";} .bi-box-fill::before {content:"\f7d2";} .bi-box-seam-fill::before {content:"\f7d3";} .bi-browser-chrome::before {content:"\f7d4";} .bi-browser-edge::before {content:"\f7d5";} .bi-browser-firefox::before {content:"\f7d6";} .bi-browser-safari::before {content:"\f7d7";} .bi-c-circle-fill::before {content:"\f7da";} .bi-c-circle::before {content:"\f7db";} .bi-c-square-fill::before {content:"\f7dc";} .bi-c-square::before {content:"\f7dd";} .bi-capsule-pill::before {content:"\f7de";} .bi-capsule::before {content:"\f7df";} .bi-car-front-fill::before {content:"\f7e0";} .bi-car-front::before {content:"\f7e1";} .bi-cassette-fill::before {content:"\f7e2";} .bi-cassette::before {content:"\f7e3";} .bi-cc-circle-fill::before {content:"\f7e6";} .bi-cc-circle::before {content:"\f7e7";} .bi-cc-square-fill::before {content:"\f7e8";} .bi-cc-square::before {content:"\f7e9";} .bi-cup-hot-fill::before {content:"\f7ea";} .bi-cup-hot::before {content:"\f7eb";} .bi-currency-rupee::before {content:"\f7ec";} .bi-dropbox::before {content:"\f7ed";} .bi-escape::before {content:"\f7ee";} .bi-fast-forward-btn-fill::before {content:"\f7ef";} .bi-fast-forward-btn::before {content:"\f7f0";} .bi-fast-forward-circle-fill::before {content:"\f7f1";} .bi-fast-forward-circle::before {content:"\f7f2";} .bi-fast-forward-fill::before {content:"\f7f3";} .bi-fast-forward::before {content:"\f7f4";} .bi-filetype-sql::before {content:"\f7f5";} .bi-fire::before {content:"\f7f6";} .bi-google-play::before {content:"\f7f7";} .bi-h-circle-fill::before {content:"\f7fa";} .bi-h-circle::before {content:"\f7fb";} .bi-h-square-fill::before {content:"\f7fc";} .bi-h-square::before {content:"\f7fd";} .bi-indent::before {content:"\f7fe";} .bi-lungs-fill::before {content:"\f7ff";} .bi-lungs::before {content:"\f800";} .bi-microsoft-teams::before {content:"\f801";} .bi-p-circle-fill::before {content:"\f804";} .bi-p-circle::before {content:"\f805";} .bi-p-square-fill::before {content:"\f806";} .bi-p-square::before {content:"\f807";} .bi-pass-fill::before {content:"\f808";} .bi-pass::before {content:"\f809";} .bi-prescription::before {content:"\f80a";} .bi-prescription2::before {content:"\f80b";} .bi-r-circle-fill::before {content:"\f80e";} .bi-r-circle::before {content:"\f80f";} .bi-r-square-fill::before {content:"\f810";} .bi-r-square::before {content:"\f811";} .bi-repeat-1::before {content:"\f812";} .bi-repeat::before {content:"\f813";} .bi-rewind-btn-fill::before {content:"\f814";} .bi-rewind-btn::before {content:"\f815";} .bi-rewind-circle-fill::before {content:"\f816";} .bi-rewind-circle::before {content:"\f817";} .bi-rewind-fill::before {content:"\f818";} .bi-rewind::before {content:"\f819";} .bi-train-freight-front-fill::before {content:"\f81a";} .bi-train-freight-front::before {content:"\f81b";} .bi-train-front-fill::before {content:"\f81c";} .bi-train-front::before {content:"\f81d";} .bi-train-lightrail-front-fill::before {content:"\f81e";} .bi-train-lightrail-front::before {content:"\f81f";} .bi-truck-front-fill::before {content:"\f820";} .bi-truck-front::before {content:"\f821";} .bi-ubuntu::before {content:"\f822";} .bi-unindent::before {content:"\f823";} .bi-unity::before {content:"\f824";} .bi-universal-access-circle::before {content:"\f825";} .bi-universal-access::before {content:"\f826";} .bi-virus::before {content:"\f827";} .bi-virus2::before {content:"\f828";} .bi-wechat::before {content:"\f829";} .bi-yelp::before {content:"\f82a";} .bi-sign-stop-fill::before {content:"\f82b";} .bi-sign-stop-lights-fill::before {content:"\f82c";} .bi-sign-stop-lights::before {content:"\f82d";} .bi-sign-stop::before {content:"\f82e";} .bi-sign-turn-left-fill::before {content:"\f82f";} .bi-sign-turn-left::before {content:"\f830";} .bi-sign-turn-right-fill::before {content:"\f831";} .bi-sign-turn-right::before {content:"\f832";} .bi-sign-turn-slight-left-fill::before {content:"\f833";} .bi-sign-turn-slight-left::before {content:"\f834";} .bi-sign-turn-slight-right-fill::before {content:"\f835";} .bi-sign-turn-slight-right::before {content:"\f836";} .bi-sign-yield-fill::before {content:"\f837";} .bi-sign-yield::before {content:"\f838";} .bi-ev-station-fill::before {content:"\f839";} .bi-ev-station::before {content:"\f83a";} .bi-fuel-pump-diesel-fill::before {content:"\f83b";} .bi-fuel-pump-diesel::before {content:"\f83c";} .bi-fuel-pump-fill::before {content:"\f83d";} .bi-fuel-pump::before {content:"\f83e";} .bi-0-circle-fill::before {content:"\f83f";} .bi-0-circle::before {content:"\f840";} .bi-0-square-fill::before {content:"\f841";} .bi-0-square::before {content:"\f842";} .bi-rocket-fill::before {content:"\f843";} .bi-rocket-takeoff-fill::before {content:"\f844";} .bi-rocket-takeoff::before {content:"\f845";} .bi-rocket::before {content:"\f846";} .bi-stripe::before {content:"\f847";} .bi-subscript::before {content:"\f848";} .bi-superscript::before {content:"\f849";} .bi-trello::before {content:"\f84a";} .bi-envelope-at-fill::before {content:"\f84b";} .bi-envelope-at::before {content:"\f84c";} .bi-regex::before {content:"\f84d";} .bi-text-wrap::before {content:"\f84e";} .bi-sign-dead-end-fill::before {content:"\f84f";} .bi-sign-dead-end::before {content:"\f850";} .bi-sign-do-not-enter-fill::before {content:"\f851";} .bi-sign-do-not-enter::before {content:"\f852";} .bi-sign-intersection-fill::before {content:"\f853";} .bi-sign-intersection-side-fill::before {content:"\f854";} .bi-sign-intersection-side::before {content:"\f855";} .bi-sign-intersection-t-fill::before {content:"\f856";} .bi-sign-intersection-t::before {content:"\f857";} .bi-sign-intersection-y-fill::before {content:"\f858";} .bi-sign-intersection-y::before {content:"\f859";} .bi-sign-intersection::before {content:"\f85a";} .bi-sign-merge-left-fill::before {content:"\f85b";} .bi-sign-merge-left::before {content:"\f85c";} .bi-sign-merge-right-fill::before {content:"\f85d";} .bi-sign-merge-right::before {content:"\f85e";} .bi-sign-no-left-turn-fill::before {content:"\f85f";} .bi-sign-no-left-turn::before {content:"\f860";} .bi-sign-no-parking-fill::before {content:"\f861";} .bi-sign-no-parking::before {content:"\f862";} .bi-sign-no-right-turn-fill::before {content:"\f863";} .bi-sign-no-right-turn::before {content:"\f864";} .bi-sign-railroad-fill::before {content:"\f865";} .bi-sign-railroad::before {content:"\f866";} .bi-building-add::before {content:"\f867";} .bi-building-check::before {content:"\f868";} .bi-building-dash::before {content:"\f869";} .bi-building-down::before {content:"\f86a";} .bi-building-exclamation::before {content:"\f86b";} .bi-building-fill-add::before {content:"\f86c";} .bi-building-fill-check::before {content:"\f86d";} .bi-building-fill-dash::before {content:"\f86e";} .bi-building-fill-down::before {content:"\f86f";} .bi-building-fill-exclamation::before {content:"\f870";} .bi-building-fill-gear::before {content:"\f871";} .bi-building-fill-lock::before {content:"\f872";} .bi-building-fill-slash::before {content:"\f873";} .bi-building-fill-up::before {content:"\f874";} .bi-building-fill-x::before {content:"\f875";} .bi-building-fill::before {content:"\f876";} .bi-building-gear::before {content:"\f877";} .bi-building-lock::before {content:"\f878";} .bi-building-slash::before {content:"\f879";} .bi-building-up::before {content:"\f87a";} .bi-building-x::before {content:"\f87b";} .bi-buildings-fill::before {content:"\f87c";} .bi-buildings::before {content:"\f87d";} .bi-bus-front-fill::before {content:"\f87e";} .bi-bus-front::before {content:"\f87f";} .bi-ev-front-fill::before {content:"\f880";} .bi-ev-front::before {content:"\f881";} .bi-globe-americas::before {content:"\f882";} .bi-globe-asia-australia::before {content:"\f883";} .bi-globe-central-south-asia::before {content:"\f884";} .bi-globe-europe-africa::before {content:"\f885";} .bi-house-add-fill::before {content:"\f886";} .bi-house-add::before {content:"\f887";} .bi-house-check-fill::before {content:"\f888";} .bi-house-check::before {content:"\f889";} .bi-house-dash-fill::before {content:"\f88a";} .bi-house-dash::before {content:"\f88b";} .bi-house-down-fill::before {content:"\f88c";} .bi-house-down::before {content:"\f88d";} .bi-house-exclamation-fill::before {content:"\f88e";} .bi-house-exclamation::before {content:"\f88f";} .bi-house-gear-fill::before {content:"\f890";} .bi-house-gear::before {content:"\f891";} .bi-house-lock-fill::before {content:"\f892";} .bi-house-lock::before {content:"\f893";} .bi-house-slash-fill::before {content:"\f894";} .bi-house-slash::before {content:"\f895";} .bi-house-up-fill::before {content:"\f896";} .bi-house-up::before {content:"\f897";} .bi-house-x-fill::before {content:"\f898";} .bi-house-x::before {content:"\f899";} .bi-person-add::before {content:"\f89a";} .bi-person-down::before {content:"\f89b";} .bi-person-exclamation::before {content:"\f89c";} .bi-person-fill-add::before {content:"\f89d";} .bi-person-fill-check::before {content:"\f89e";} .bi-person-fill-dash::before {content:"\f89f";} .bi-person-fill-down::before {content:"\f8a0";} .bi-person-fill-exclamation::before {content:"\f8a1";} .bi-person-fill-gear::before {content:"\f8a2";} .bi-person-fill-lock::before {content:"\f8a3";} .bi-person-fill-slash::before {content:"\f8a4";} .bi-person-fill-up::before {content:"\f8a5";} .bi-person-fill-x::before {content:"\f8a6";} .bi-person-gear::before {content:"\f8a7";} .bi-person-lock::before {content:"\f8a8";} .bi-person-slash::before {content:"\f8a9";} .bi-person-up::before {content:"\f8aa";} .bi-scooter::before {content:"\f8ab";} .bi-taxi-front-fill::before {content:"\f8ac";} .bi-taxi-front::before {content:"\f8ad";} .bi-amd::before {content:"\f8ae";} .bi-database-add::before {content:"\f8af";} .bi-database-check::before {content:"\f8b0";} .bi-database-dash::before {content:"\f8b1";} .bi-database-down::before {content:"\f8b2";} .bi-database-exclamation::before {content:"\f8b3";} .bi-database-fill-add::before {content:"\f8b4";} .bi-database-fill-check::before {content:"\f8b5";} .bi-database-fill-dash::before {content:"\f8b6";} .bi-database-fill-down::before {content:"\f8b7";} .bi-database-fill-exclamation::before {content:"\f8b8";} .bi-database-fill-gear::before {content:"\f8b9";} .bi-database-fill-lock::before {content:"\f8ba";} .bi-database-fill-slash::before {content:"\f8bb";} .bi-database-fill-up::before {content:"\f8bc";} .bi-database-fill-x::before {content:"\f8bd";} .bi-database-fill::before {content:"\f8be";} .bi-database-gear::before {content:"\f8bf";} .bi-database-lock::before {content:"\f8c0";} .bi-database-slash::before {content:"\f8c1";} .bi-database-up::before {content:"\f8c2";} .bi-database-x::before {content:"\f8c3";} .bi-database::before {content:"\f8c4";} .bi-houses-fill::before {content:"\f8c5";} .bi-houses::before {content:"\f8c6";} .bi-nvidia::before {content:"\f8c7";} .bi-person-vcard-fill::before {content:"\f8c8";} .bi-person-vcard::before {content:"\f8c9";} .bi-sina-weibo::before {content:"\f8ca";} .bi-tencent-qq::before {content:"\f8cb";} .bi-wikipedia::before {content:"\f8cc";} .bi-alphabet-uppercase::before {content:"\f2a5";} .bi-alphabet::before {content:"\f68a";} .bi-amazon::before {content:"\f68d";} .bi-arrows-collapse-vertical::before {content:"\f690";} .bi-arrows-expand-vertical::before {content:"\f695";} .bi-arrows-vertical::before {content:"\f698";} .bi-arrows::before {content:"\f6a2";} .bi-ban-fill::before {content:"\f6a3";} .bi-ban::before {content:"\f6b6";} .bi-bing::before {content:"\f6c2";} .bi-cake::before {content:"\f6e0";} .bi-cake2::before {content:"\f6ed";} .bi-cookie::before {content:"\f6ee";} .bi-copy::before {content:"\f759";} .bi-crosshair::before {content:"\f769";} .bi-crosshair2::before {content:"\f794";} .bi-emoji-astonished-fill::before {content:"\f795";} .bi-emoji-astonished::before {content:"\f79a";} .bi-emoji-grimace-fill::before {content:"\f79b";} .bi-emoji-grimace::before {content:"\f7a0";} .bi-emoji-grin-fill::before {content:"\f7a1";} .bi-emoji-grin::before {content:"\f7a6";} .bi-emoji-surprise-fill::before {content:"\f7a7";} .bi-emoji-surprise::before {content:"\f7ac";} .bi-emoji-tear-fill::before {content:"\f7ad";} .bi-emoji-tear::before {content:"\f7b2";} .bi-envelope-arrow-down-fill::before {content:"\f7b3";} .bi-envelope-arrow-down::before {content:"\f7b8";} .bi-envelope-arrow-up-fill::before {content:"\f7b9";} .bi-envelope-arrow-up::before {content:"\f7be";} .bi-feather::before {content:"\f7bf";} .bi-feather2::before {content:"\f7c4";} .bi-floppy-fill::before {content:"\f7c5";} .bi-floppy::before {content:"\f7d8";} .bi-floppy2-fill::before {content:"\f7d9";} .bi-floppy2::before {content:"\f7e4";} .bi-gitlab::before {content:"\f7e5";} .bi-highlighter::before {content:"\f7f8";} .bi-marker-tip::before {content:"\f802";} .bi-nvme-fill::before {content:"\f803";} .bi-nvme::before {content:"\f80c";} .bi-opencollective::before {content:"\f80d";} .bi-pci-card-network::before {content:"\f8cd";} .bi-pci-card-sound::before {content:"\f8ce";} .bi-radar::before {content:"\f8cf";} .bi-send-arrow-down-fill::before {content:"\f8d0";} .bi-send-arrow-down::before {content:"\f8d1";} .bi-send-arrow-up-fill::before {content:"\f8d2";} .bi-send-arrow-up::before {content:"\f8d3";} .bi-sim-slash-fill::before {content:"\f8d4";} .bi-sim-slash::before {content:"\f8d5";} .bi-sourceforge::before {content:"\f8d6";} .bi-substack::before {content:"\f8d7";} .bi-threads-fill::before {content:"\f8d8";} .bi-threads::before {content:"\f8d9";} .bi-transparency::before {content:"\f8da";} .bi-twitter-x::before {content:"\f8db";} .bi-type-h4::before {content:"\f8dc";} .bi-type-h5::before {content:"\f8dd";} .bi-type-h6::before {content:"\f8de";} .bi-backpack-fill::before {content:"\f8df";} .bi-backpack::before {content:"\f8e0";} .bi-backpack2-fill::before {content:"\f8e1";} .bi-backpack2::before {content:"\f8e2";} .bi-backpack3-fill::before {content:"\f8e3";} .bi-backpack3::before {content:"\f8e4";} .bi-backpack4-fill::before {content:"\f8e5";} .bi-backpack4::before {content:"\f8e6";} .bi-brilliance::before {content:"\f8e7";} .bi-cake-fill::before {content:"\f8e8";} .bi-cake2-fill::before {content:"\f8e9";} .bi-duffle-fill::before {content:"\f8ea";} .bi-duffle::before {content:"\f8eb";} .bi-exposure::before {content:"\f8ec";} .bi-gender-neuter::before {content:"\f8ed";} .bi-highlights::before {content:"\f8ee";} .bi-luggage-fill::before {content:"\f8ef";} .bi-luggage::before {content:"\f8f0";} .bi-mailbox-flag::before {content:"\f8f1";} .bi-mailbox2-flag::before {content:"\f8f2";} .bi-noise-reduction::before {content:"\f8f3";} .bi-passport-fill::before {content:"\f8f4";} .bi-passport::before {content:"\f8f5";} .bi-person-arms-up::before {content:"\f8f6";} .bi-person-raised-hand::before {content:"\f8f7";} .bi-person-standing-dress::before {content:"\f8f8";} .bi-person-standing::before {content:"\f8f9";} .bi-person-walking::before {content:"\f8fa";} .bi-person-wheelchair::before {content:"\f8fb";} .bi-shadows::before {content:"\f8fc";} .bi-suitcase-fill::before {content:"\f8fd";} .bi-suitcase-lg-fill::before {content:"\f8fe";} .bi-suitcase-lg::before {content:"\f8ff";} .bi-suitcase::before {content:"\f900";} .bi-suitcase2-fill::before {content:"\f901";} .bi-suitcase2::before {content:"\f902";} .bi-vignette::before {content:"\f903";}';
		$icons = "";
			foreach ($w as  $value) {
				$reg = "/".$value.'::\s*(.*?)\;}/';
				preg_match_all($reg, $st, $r);
				if(isset($r) && isset($r[0][0])){
					$icons .= " .".$r[0][0];
				}
			}
		return  '
		<!-- styleEfB -->
	 <style>
		@font-face {  font-family: "bootstrap-icons";  src: url("'.EMSFB_PLUGIN_URL.'includes/admin/assets/css/fonts/bootstrap-icons.woff2?856008caa5eb66df68595e734e59580d")
	   format("woff2"),url("'.EMSFB_PLUGIN_URL.'includes/admin/assets/css/fonts/bootstrap-icons.woff?856008caa5eb66df68595e734e59580d") format("woff");}[class^="bi-"]::before,[class*=" bi-"]::before {  display: inline-block;  font-family: bootstrap-icons !important;  font-style: normal;  font-weight: normal !important;  font-variant: normal;  text-transform: none;  line-height: 1;  vertical-align: -.125em;  -webkit-font-smoothing: antialiased;  -moz-osx-font-smoothing: grayscale;}
	   '.$icons.'
			</style>
	';
	}
	public function bootstrap_style_efb($w){
		return "
		<style>
		@charset 'UTF-8';:root{--bs-blue:#0d6efd;--bs-indigo:#6610f2;--bs-purple:#6f42c1;--bs-pink:#d63384;--bs-red:#dc3545;--bs-orange:#fd7e14;--bs-yellow:#ffc107;--bs-green:#198754;--bs-teal:#20c997;--bs-cyan:#0dcaf0;--bs-white:#fff;--bs-gray:#6c757d;--bs-gray-dark:#343a40;--bs-primary:#0d6efd;--bs-secondary:#6c757d;--bs-success:#198754;--bs-info:#0dcaf0;--bs-warning:#ffc107;--bs-danger:#dc3545;--bs-light:#f8f9fa;--bs-dark:#212529;--bs-font-sans-serif:system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans','Liberation Sans',sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol','Noto Color Emoji';--bs-font-monospace:SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;--bs-gradient:linear-gradient(180deg,rgba(255,255,255,.15),rgba(255,255,255,0))}.efb,.efb::after,.efb::before{box-sizing:border-box}@media (prefers-reduced-motion:no-preference){:root.efb{scroll-behavior:smooth}}hr .efb{margin:1rem 0;color:inherit;background-color:currentColor;border:0;opacity:.25}hr .efb:not([size]){height:1px}.efb.h1,.efb.h2,.efb.h3,.efb.h4,.efb.h5,.efb.h6,h1.efb,h2.efb,h3.efb,h4.efb,h5.efb,h6.efb{margin-top:0;margin-bottom:.5rem;font-weight:500;line-height:1.2}.efb.h1,h1.efb{font-size:calc(1.375rem + 1.5vw)}@media (min-width:1200px){.efb.h1,h1.efb{font-size:2.5rem}}.efb.h2,h2.efb{font-size:calc(1.325rem + .9vw)}@media (min-width:1200px){.h2.efb,h2.efb{font-size:2rem}}.efb.h3,h3.efb{font-size:calc(1.3rem + .6vw)}@media (min-width:1200px){.efb.h3,h3.efb{font-size:1.75rem}}.efb.h4,h4.efb{font-size:calc(1.275rem + .3vw)}@media (min-width:1200px){.h4.efb,h4.efb{font-size:1.5rem}}.efb.h5,h5.efb{font-size:1.25rem}.efb.h6,h6.efb{font-size:1rem}p.efb{margin-top:0;margin-bottom:1rem}abbr.efb[data-bs-original-title],abbr[title].efb{-webkit-text-decoration:underline dotted;text-decoration:underline dotted;cursor:help;-webkit-text-decoration-skip-ink:none;text-decoration-skip-ink:none}address.efb{margin-bottom:1rem;font-style:normal;line-height:inherit}ol.efb,ul.efb{padding-left:2rem}dl.efb,ol.efb,ul.efb{margin-top:0;margin-bottom:1rem}ol.efb ol.efb,ol.efb ul.efb,ul.efb ol.efb,ul.efb ul.efb{margin-bottom:0}dt.efb{font-weight:700}dd.efb{margin-bottom:.5rem;margin-left:0}blockquote.efb{margin:0 0 1rem}b.efb,strong.efb{font-weight:bolder}.efb.small,small.efb{font-size:.875em}.efb.mark,mark.efb{padding:.2em;background-color:#fcf8e3}sub.efb,sup.efb{position:relative;font-size:.75em;line-height:0;vertical-align:baseline}sub.efb{bottom:-.25em}sup.efb{top:-.5em}a.efb{color:#0d6efd;text-decoration:underline}a.efb:hover{color:#0a58ca}a.efb:not([href]):not([class]),a.efb:not([href]):not([class]):hover{color:inherit;text-decoration:none}code.efb,kbd.efb,pre.efb,samp.efb{font-family:var(--bs-font-monospace);font-size:1em;direction:ltr;unicode-bidi:bidi-override}pre.efb{display:block;margin-top:0;margin-bottom:1rem;overflow:auto;font-size:.875em}pre code.efb{font-size:inherit;color:inherit;word-break:normal}code.efb{font-size:.875em;color:#d63384;word-wrap:break-word}a.efb>code{color:inherit}kbd.efb{padding:.2rem .4rem;font-size:.875em;color:#fff;background-color:#212529;border-radius:.2rem}kbd.efb kbd{padding:0;font-size:1em;font-weight:700}figure.efb{margin:0 0 1rem}img.efb,svg.efb{vertical-align:middle}table.efb{caption-side:bottom;border-collapse:collapse}caption.efb{padding-top:.5rem;padding-bottom:.5rem;color:#6c757d;text-align:left}th.efb{text-align:inherit;text-align:-webkit-match-parent}tbody.efb,td.efb,tfoot.efb,th.efb,thead.efb,tr.efb{border-color:inherit;border-style:solid;border-width:0}label.efb{display:inline-block}button.efb{border-radius:0}button.efb:focus:not(:focus-visible){outline:0}button.efb,input.efb,optgroup.efb,select.efb,textarea.efb{margin:0;font-family:inherit;font-size:inherit;line-height:inherit;color:#a5a3d1}textarea.efb:focus{box-shadow:0 2px 10px rgba(84,131,207,.25)!important;color:#a5a3d1}button.efb,select.efb{text-transform:none}[role=button]{cursor:pointer}select.efb{word-wrap:normal}select.efb:disabled{opacity:1}[list].efb::-webkit-calendar-picker-indicator{display:none}[type=button],[type=reset],[type=submit],button.efb{-webkit-appearance:button}[type=button]:not(:disabled) .efb,[type=reset]:not(:disabled) .efb,[type=submit]:not(:disabled) .efb,button:not(:disabled) .efb{cursor:pointer}.efb::-moz-focus-inner{padding:0;border-style:none}textarea.efb{resize:vertical}fieldset.efb{min-width:0;padding:0;margin:0;border:0}legend.efb{float:left;width:100%;padding:0;margin-bottom:.5rem;font-size:calc(1.275rem + .3vw);line-height:inherit}@media (min-width:1200px){legend.efb{font-size:1.5rem}}legend.efb+*{clear:left}.efb::-webkit-datetime-edit-day-field,.efb::-webkit-datetime-edit-fields-wrapper,.efb::-webkit-datetime-edit-hour-field,.efb::-webkit-datetime-edit-minute,.efb::-webkit-datetime-edit-month-field,.efb::-webkit-datetime-edit-text,.efb::-webkit-datetime-edit-year-field{padding:0}.efb::-webkit-inner-spin-button{height:auto}[type=search] .efb{outline-offset:-2px;-webkit-appearance:textfield}.efb::-webkit-search-decoration{-webkit-appearance:none}.efb::-webkit-color-swatch-wrapper{padding:0}.efb::file-selector-button{font:inherit}.efb::-webkit-file-upload-button{font:inherit;-webkit-appearance:button}output.efb{display:inline-block}iframe.efb{border:0}summary.efb{display:list-item;cursor:pointer}progress.efb{vertical-align:baseline}[hidden]{display:none!important}.efb.lead{font-size:1.25rem;font-weight:300}.efb.display-1{font-size:calc(1.625rem + 4.5vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-1{font-size:5rem}}.efb.display-2{font-size:calc(1.575rem + 3.9vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-2{font-size:4.5rem}}.efb.display-3{font-size:calc(1.525rem + 3.3vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-3{font-size:4rem}}.efb.display-4{font-size:calc(1.475rem + 2.7vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-4{font-size:3.5rem}}.efb.display-5{font-size:calc(1.425rem + 2.1vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-5{font-size:3rem}}.efb.display-6{font-size:calc(1.375rem + 1.5vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-6{font-size:2.5rem}}.efb.list-unstyled{padding-left:0;list-style:none}.efb.list-inline{padding-left:0;list-style:none}.efb.list-inline-item{display:inline-block}.efb.list-inline-item:not(:last-child){margin-right:.5rem}.efb.initialism{font-size:.875em;text-transform:uppercase}.efb.blockquote{margin-bottom:1rem;font-size:1.25rem}.efb.blockquote>:last-child{margin-bottom:0}.efb.blockquote-footer{margin-top:-1rem;margin-bottom:1rem;font-size:.875em;color:#6c757d}.efb.blockquote-footer::before{content:'— '}.efb.img-fluid{max-width:100%;height:auto}.efb.img-thumbnail{padding:.25rem;background-color:#fff;border:1px solid #dee2e6;border-radius:.25rem;max-width:100%;height:auto}.efb.figure{display:inline-block}.efb.figure-img{margin-bottom:.5rem;line-height:1}.efb.figure-caption{font-size:.875em;color:#6c757d}.efb.container,.efb.container-fluid,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl,.efb.container-xxl{width:100%;padding-right:var(--bs-gutter-x,.75rem);padding-left:var(--bs-gutter-x,.75rem);margin-right:auto;margin-left:auto}@media (min-width:576px){.efb.container,.efb.container-sm{max-width:540px}}@media (min-width:768px){.efb.container,.efb.container-md,.efb.container-sm{max-width:720px}}@media (min-width:992px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm{max-width:960px}}@media (min-width:1200px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl{max-width:1140px}}@media (min-width:1400px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl,.efb.container-xxl{max-width:1320px}}.row.efb{--bs-gutter-x:1.5rem;--bs-gutter-y:0;display:flex;flex-wrap:wrap;margin-top:calc(var(--bs-gutter-y) * -1);margin-right:calc(var(--bs-gutter-x)/ -2);margin-left:calc(var(--bs-gutter-x)/ -2)}.efb.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)/ 2);padding-left:calc(var(--bs-gutter-x)/ 2);margin-top:var(--bs-gutter-y)}.efb.col{flex:1 0 0%}.efb.row-cols-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-1>*{flex:0 0 auto;width:100%}.efb.row-cols-2>*{flex:0 0 auto;width:50%}.efb.row-cols-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-4>*{flex:0 0 auto;width:25%}.efb.row-cols-5>*{flex:0 0 auto;width:20%}.efb.row-cols-6>*{flex:0 0 auto;width:16.6666666667%}.col-auto{flex:0 0 auto;width:auto}.efb.col-1{flex:0 0 auto;width:8.3333333333%}.efb.col-2{flex:0 0 auto;width:16.6666666667%}.efb.efb-col-3{flex:0 0 auto;width:25%}.efb.col-4{flex:0 0 auto;width:33.3333333333%}.efb.col-5{flex:0 0 auto;width:41.6666666667%}.efb.col-6{flex:0 0 auto;width:50%}.efb.col-7{flex:0 0 auto;width:58.3333333333%}.efb.col-8{flex:0 0 auto;width:66.6666666667%}.efb.col-9{flex:0 0 auto;width:75%}.efb.col-10{flex:0 0 auto;width:83.3333333333%}.efb.col-11{flex:0 0 auto;width:91.6666666667%}.efb.col-12{flex:0 0 auto;width:100%}.efb.offset-1{margin-left:8.3333333333%}.efb.offset-2{margin-left:16.6666666667%}.efb.offset-3{margin-left:25%}.efb.offset-4{margin-left:33.3333333333%}.efb.offset-5{margin-left:41.6666666667%}.efb.offset-6{margin-left:50%}.efb.offset-7{margin-left:58.3333333333%}.efb.offset-8{margin-left:66.6666666667%}.efb.offset-9{margin-left:75%}.efb.offset-10{margin-left:83.3333333333%}.efb.offset-11{margin-left:91.6666666667%}.efb.g-0,.efb.gx-0{--bs-gutter-x:0}.efb.g-0,.efb.gy-0{--bs-gutter-y:0}.efb.g-1,.efb.gx-1{--bs-gutter-x:.25rem}.efb.g-1,.efb.gy-1{--bs-gutter-y:.25rem}.efb.g-2,.efb.gx-2{--bs-gutter-x:.5rem}.efb.g-2,.efb.gy-2{--bs-gutter-y:.5rem}.efb.g-3,.efb.gx-3{--bs-gutter-x:1rem}.efb.g-3,.efb.gy-3{--bs-gutter-y:1rem}.efb.g-4,.efb.gx-4{--bs-gutter-x:1.5rem}.efb.g-4,.efb.gy-4{--bs-gutter-y:1.5rem}.efb.g-5,.efb.gx-5{--bs-gutter-x:3rem}.efb.g-5,.efb.gy-5{--bs-gutter-y:3rem}@media (min-width:576px){.efb.col-sm{flex:1 0 0%}.efb.row-cols-sm-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-sm-1>*{flex:0 0 auto;width:100%}.efb.row-cols-sm-2>*{flex:0 0 auto;width:50%}.efb.row-cols-sm-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-sm-4>*{flex:0 0 auto;width:25%}.efb.row-cols-sm-5>*{flex:0 0 auto;width:20%}.efb.row-cols-sm-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-sm-auto{flex:0 0 auto;width:auto}.efb.col-sm-1{flex:0 0 auto;width:8.3333333333%}.efb.col-sm-2{flex:0 0 auto;width:16.6666666667%}.efb.col-sm-3{flex:0 0 auto;width:25%}.efb.col-sm-4{flex:0 0 auto;width:33.3333333333%}.efb.col-sm-5{flex:0 0 auto;width:41.6666666667%}.efb.col-sm-6{flex:0 0 auto;width:50%}.efb.col-sm-7{flex:0 0 auto;width:58.3333333333%}.efb.col-sm-8{flex:0 0 auto;width:66.6666666667%}.efb.col-sm-9{flex:0 0 auto;width:75%}.efb.col-sm-10{flex:0 0 auto;width:83.3333333333%}.efb.col-sm-11{flex:0 0 auto;width:91.6666666667%}.efb.col-sm-12{flex:0 0 auto;width:100%}.efb.offset-sm-0{margin-left:0}.efb.offset-sm-1{margin-left:8.3333333333%}.efb.offset-sm-2{margin-left:16.6666666667%}.efb.offset-sm-3{margin-left:25%}.efb.offset-sm-4{margin-left:33.3333333333%}.efb.offset-sm-5{margin-left:41.6666666667%}.efb.offset-sm-6{margin-left:50%}.efb.offset-sm-7{margin-left:58.3333333333%}.efb.offset-sm-8{margin-left:66.6666666667%}.efb.offset-sm-9{margin-left:75%}.efb.offset-sm-10{margin-left:83.3333333333%}.efb.offset-sm-11{margin-left:91.6666666667%}.efb.g-sm-0,.efb.gx-sm-0{--bs-gutter-x:0}.efb.g-sm-0,.efb.gy-sm-0{--bs-gutter-y:0}.efb.g-sm-1,.efb.gx-sm-1{--bs-gutter-x:.25rem}.efb.g-sm-1,.efb.gy-sm-1{--bs-gutter-y:.25rem}.efb.g-sm-2,.efb.gx-sm-2{--bs-gutter-x:.5rem}.efb.g-sm-2,.efb.gy-sm-2{--bs-gutter-y:.5rem}.efb.g-sm-3,.efb.gx-sm-3{--bs-gutter-x:1rem}.efb.g-sm-3,.efb.gy-sm-3{--bs-gutter-y:1rem}.efb.g-sm-4,.efb.gx-sm-4{--bs-gutter-x:1.5rem}.efb.g-sm-4,.efb.gy-sm-4{--bs-gutter-y:1.5rem}.efb.g-sm-5,.efb.gx-sm-5{--bs-gutter-x:3rem}.efb.g-sm-5,.efb.gy-sm-5{--bs-gutter-y:3rem}}@media (min-width:768px){.efb.col-md{flex:1 0 0%}.efb.row-cols-md-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-md-1>*{flex:0 0 auto;width:100%}.efb.row-cols-md-2>*{flex:0 0 auto;width:50%}.efb.row-cols-md-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-md-4>*{flex:0 0 auto;width:25%}.efb.row-cols-md-5>*{flex:0 0 auto;width:20%}.efb.row-cols-md-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-md-auto{flex:0 0 auto;width:auto}.efb.col-md-1{flex:0 0 auto;width:8.3333333333%}.efb.col-md-2{flex:0 0 auto;width:16.6666666667%}.efb.col-md-3{flex:0 0 auto;width:25%}.efb.col-md-4{flex:0 0 auto;width:33.3333333333%}.efb.col-md-5{flex:0 0 auto;width:41.6666666667%}.efb.col-md-6{flex:0 0 auto;width:50%}.efb.col-md-7{flex:0 0 auto;width:58.3333333333%}.efb.col-md-8{flex:0 0 auto;width:66.6666666667%}.efb.col-md-9{flex:0 0 auto;width:75%}.efb.col-md-10{flex:0 0 auto;width:83.3333333333%}.efb.col-md-11{flex:0 0 auto;width:91.6666666667%}.efb.col-md-12{flex:0 0 auto;width:100%}.efb.offset-md-0{margin-left:0}.efb.offset-md-1{margin-left:8.3333333333%}.efb.offset-md-2{margin-left:16.6666666667%}.efb.offset-md-3{margin-left:25%}.efb.offset-md-4{margin-left:33.3333333333%}.efb.offset-md-5{margin-left:41.6666666667%}.efb.offset-md-6{margin-left:50%}.efb.offset-md-7{margin-left:58.3333333333%}.efb.offset-md-8{margin-left:66.6666666667%}.efb.offset-md-9{margin-left:75%}.efb.offset-md-10{margin-left:83.3333333333%}.efb.offset-md-11{margin-left:91.6666666667%}.efb.g-md-0,.efb.gx-md-0{--bs-gutter-x:0}.efb.g-md-0,.efb.gy-md-0{--bs-gutter-y:0}.efb.g-md-1,.efb.gx-md-1{--bs-gutter-x:.25rem}.efb.g-md-1,.efb.gy-md-1{--bs-gutter-y:.25rem}.efb.g-md-2,.efb.gx-md-2{--bs-gutter-x:.5rem}.efb.g-md-2,.efb.gy-md-2{--bs-gutter-y:.5rem}.efb.g-md-3,.efb.gx-md-3{--bs-gutter-x:1rem}.efb.g-md-3,.efb.gy-md-3{--bs-gutter-y:1rem}.efb.g-md-4,.efb.gx-md-4{--bs-gutter-x:1.5rem}.efb.g-md-4,.efb.gy-md-4{--bs-gutter-y:1.5rem}.efb.g-md-5,.efb.gx-md-5{--bs-gutter-x:3rem}.efb.g-md-5,.efb.gy-md-5{--bs-gutter-y:3rem}}@media (min-width:992px){.efb.col-lg{flex:1 0 0%}.efb.row-cols-lg-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-lg-1>*{flex:0 0 auto;width:100%}.efb.row-cols-lg-2>*{flex:0 0 auto;width:50%}.efb.row-cols-lg-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-lg-4>*{flex:0 0 auto;width:25%}.efb.row-cols-lg-5>*{flex:0 0 auto;width:20%}.efb.row-cols-lg-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-lg-auto{flex:0 0 auto;width:auto}.efb.col-lg-1{flex:0 0 auto;width:8.3333333333%}.efb.col-lg-2{flex:0 0 auto;width:16.6666666667%}.efb.col-lg-3{flex:0 0 auto;width:25%}.efb.col-lg-4{flex:0 0 auto;width:33.3333333333%}.efb.col-lg-5{flex:0 0 auto;width:41.6666666667%}.efb.col-lg-6{flex:0 0 auto;width:50%}.efb.col-lg-7{flex:0 0 auto;width:58.3333333333%}.efb.col-lg-8{flex:0 0 auto;width:66.6666666667%}.efb.col-lg-9{flex:0 0 auto;width:75%}.efb.col-lg-10{flex:0 0 auto;width:83.3333333333%}.efb.col-lg-11{flex:0 0 auto;width:91.6666666667%}.efb.col-lg-12{flex:0 0 auto;width:100%}.efb.offset-lg-0{margin-left:0}.efb.offset-lg-1{margin-left:8.3333333333%}.efb.offset-lg-2{margin-left:16.6666666667%}.efb.offset-lg-3{margin-left:25%}.efb.offset-lg-4{margin-left:33.3333333333%}.efb.offset-lg-5{margin-left:41.6666666667%}.efb.offset-lg-6{margin-left:50%}.efb.offset-lg-7{margin-left:58.3333333333%}.efb.offset-lg-8{margin-left:66.6666666667%}.efb.offset-lg-9{margin-left:75%}.efb.offset-lg-10{margin-left:83.3333333333%}.efb.offset-lg-11{margin-left:91.6666666667%}.efb.g-lg-0,.efb.gx-lg-0{--bs-gutter-x:0}.efb.g-lg-0,.efb.gy-lg-0{--bs-gutter-y:0}.efb.g-lg-1,.efb.gx-lg-1{--bs-gutter-x:.25rem}.efb.g-lg-1,.efb.gy-lg-1{--bs-gutter-y:.25rem}.efb.g-lg-2,.efb.gx-lg-2{--bs-gutter-x:.5rem}.efb.g-lg-2,.efb.gy-lg-2{--bs-gutter-y:.5rem}.efb.g-lg-3,.efb.gx-lg-3{--bs-gutter-x:1rem}.efb.g-lg-3,.efb.gy-lg-3{--bs-gutter-y:1rem}.efb.g-lg-4,.efb.gx-lg-4{--bs-gutter-x:1.5rem}.efb.g-lg-4,.efb.gy-lg-4{--bs-gutter-y:1.5rem}.efb.g-lg-5,.efb.gx-lg-5{--bs-gutter-x:3rem}.efb.g-lg-5,.efb.gy-lg-5{--bs-gutter-y:3rem}}@media (min-width:1200px){.efb.col-xl{flex:1 0 0%}.efb.row-cols-xl-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-xl-1>*{flex:0 0 auto;width:100%}.efb.row-cols-xl-2>*{flex:0 0 auto;width:50%}.efb.row-cols-xl-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-xl-4>*{flex:0 0 auto;width:25%}.efb.row-cols-xl-5>*{flex:0 0 auto;width:20%}.efb.row-cols-xl-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-xl-auto{flex:0 0 auto;width:auto}.efb.col-xl-1{flex:0 0 auto;width:8.3333333333%}.efb.col-xl-2{flex:0 0 auto;width:16.6666666667%}.efb.col-xl-3{flex:0 0 auto;width:25%}.efb.col-xl-4{flex:0 0 auto;width:33.3333333333%}.efb.col-xl-5{flex:0 0 auto;width:41.6666666667%}.efb.col-xl-6{flex:0 0 auto;width:50%}.efb.col-xl-7{flex:0 0 auto;width:58.3333333333%}.efb.col-xl-8{flex:0 0 auto;width:66.6666666667%}.efb.col-xl-9{flex:0 0 auto;width:75%}.efb.col-xl-10{flex:0 0 auto;width:83.3333333333%}.efb.col-xl-11{flex:0 0 auto;width:91.6666666667%}.efb.col-xl-12{flex:0 0 auto;width:100%}.efb.offset-xl-0{margin-left:0}.efb.offset-xl-1{margin-left:8.3333333333%}.efb.offset-xl-2{margin-left:16.6666666667%}.efb.offset-xl-3{margin-left:25%}.efb.offset-xl-4{margin-left:33.3333333333%}.efb.offset-xl-5{margin-left:41.6666666667%}.efb.offset-xl-6{margin-left:50%}.efb.offset-xl-7{margin-left:58.3333333333%}.efb.offset-xl-8{margin-left:66.6666666667%}.efb.offset-xl-9{margin-left:75%}.efb.offset-xl-10{margin-left:83.3333333333%}.efb.offset-xl-11{margin-left:91.6666666667%}.efb.g-xl-0,.efb.gx-xl-0{--bs-gutter-x:0}.efb.g-xl-0,.efb.gy-xl-0{--bs-gutter-y:0}.efb.g-xl-1,.efb.gx-xl-1{--bs-gutter-x:.25rem}.efb.g-xl-1,.efb.gy-xl-1{--bs-gutter-y:.25rem}.efb.g-xl-2,.efb.gx-xl-2{--bs-gutter-x:.5rem}.efb.g-xl-2,.efb.gy-xl-2{--bs-gutter-y:.5rem}.efb.g-xl-3,.efb.gx-xl-3{--bs-gutter-x:1rem}.efb.g-xl-3,.efb.gy-xl-3{--bs-gutter-y:1rem}.efb.g-xl-4,.efb.gx-xl-4{--bs-gutter-x:1.5rem}.efb.g-xl-4,.efb.gy-xl-4{--bs-gutter-y:1.5rem}.efb.g-xl-5,.efb.gx-xl-5{--bs-gutter-x:3rem}.efb.g-xl-5,.efb.gy-xl-5{--bs-gutter-y:3rem}}@media (min-width:1400px){.efb.col-xxl{flex:1 0 0%}.efb.row-cols-xxl-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-xxl-1>*{flex:0 0 auto;width:100%}.efb.row-cols-xxl-2>*{flex:0 0 auto;width:50%}.efb.row-cols-xxl-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-xxl-4>*{flex:0 0 auto;width:25%}.efb.row-cols-xxl-5>*{flex:0 0 auto;width:20%}.efb.row-cols-xxl-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-xxl-auto{flex:0 0 auto;width:auto}.efb.col-xxl-1{flex:0 0 auto;width:8.3333333333%}.efb.col-xxl-2{flex:0 0 auto;width:16.6666666667%}.efb.col-xxl-3{flex:0 0 auto;width:25%}.efb.col-xxl-4{flex:0 0 auto;width:33.3333333333%}.efb.col-xxl-5{flex:0 0 auto;width:41.6666666667%}.efb.col-xxl-6{flex:0 0 auto;width:50%}.efb.col-xxl-7{flex:0 0 auto;width:58.3333333333%}.efb.col-xxl-8{flex:0 0 auto;width:66.6666666667%}.efb.col-xxl-9{flex:0 0 auto;width:75%}.efb.col-xxl-10{flex:0 0 auto;width:83.3333333333%}.efb.col-xxl-11{flex:0 0 auto;width:91.6666666667%}.efb.col-xxl-12{flex:0 0 auto;width:100%}.efb.offset-xxl-0{margin-left:0}.efb.offset-xxl-1{margin-left:8.3333333333%}.efb.offset-xxl-2{margin-left:16.6666666667%}.efb.offset-xxl-3{margin-left:25%}.efb.offset-xxl-4{margin-left:33.3333333333%}.efb.offset-xxl-5{margin-left:41.6666666667%}.efb.offset-xxl-6{margin-left:50%}.efb.offset-xxl-7{margin-left:58.3333333333%}.efb.offset-xxl-8{margin-left:66.6666666667%}.efb.offset-xxl-9{margin-left:75%}.efb.offset-xxl-10{margin-left:83.3333333333%}.efb.offset-xxl-11{margin-left:91.6666666667%}.efb.g-xxl-0,.efb.gx-xxl-0{--bs-gutter-x:0}.efb.g-xxl-0,.efb.gy-xxl-0{--bs-gutter-y:0}.efb.g-xxl-1,.efb.gx-xxl-1{--bs-gutter-x:.25rem}.efb.g-xxl-1,.efb.gy-xxl-1{--bs-gutter-y:.25rem}.efb.g-xxl-2,.efb.gx-xxl-2{--bs-gutter-x:.5rem}.efb.g-xxl-2,.efb.gy-xxl-2{--bs-gutter-y:.5rem}.efb.g-xxl-3,.efb.gx-xxl-3{--bs-gutter-x:1rem}.efb.g-xxl-3,.efb.gy-xxl-3{--bs-gutter-y:1rem}.efb.g-xxl-4,.efb.gx-xxl-4{--bs-gutter-x:1.5rem}.efb.g-xxl-4,.efb.gy-xxl-4{--bs-gutter-y:1.5rem}.efb.g-xxl-5,.efb.gx-xxl-5{--bs-gutter-x:3rem}.efb.g-xxl-5,.efb.gy-xxl-5{--bs-gutter-y:3rem}}.efb.table{--bs-table-bg:transparent;--bs-table-accent-bg:transparent;--bs-table-striped-color:#212529;--bs-table-striped-bg:rgba(0,0,0,.05);--bs-table-active-color:#212529;--bs-table-active-bg:rgba(0,0,0,.1);--bs-table-hover-color:#212529;--bs-table-hover-bg:rgba(0,0,0,.075);width:100%;margin-bottom:1rem;color:#212529;vertical-align:top;border-color:#dee2e6;border-left:none;border-right:none;border-bottom:none}.efb.table>:not(caption)>*>*{padding:.5rem .5rem;background-color:var(--bs-table-bg);border-bottom-width:1px;box-shadow:inset 0 0 0 9999px var(--bs-table-accent-bg)}.efb.table>tbody{vertical-align:inherit}.efb.table>thead{vertical-align:bottom}.efb.table>:not(:last-child)>:last-child>*{border-bottom-color:currentColor}.efb.caption-top{caption-side:top}.efb.table-sm>:not(caption)>*>*{padding:.25rem .25rem}.efb.table-bordered>:not(caption)>*{border-width:1px 0}.efb.table-bordered>:not(caption)>*>*{border-width:0 1px}.efb.table-borderless>:not(caption)>*>*{border-bottom-width:0}.efb.table-striped>tbody>tr:nth-of-type(odd){--bs-table-accent-bg:var(--bs-table-striped-bg);color:var(--bs-table-striped-color)}.efb.table-active{--bs-table-accent-bg:var(--bs-table-active-bg);color:var(--bs-table-active-color)}.efb.table-hover>tbody>tr:hover{--bs-table-accent-bg:var(--bs-table-hover-bg);color:var(--bs-table-hover-color)}.efb.table-primary{--bs-table-bg:#cfe2ff;--bs-table-striped-bg:#c5d7f2;--bs-table-striped-color:#000;--bs-table-active-bg:#bacbe6;--bs-table-active-color:#000;--bs-table-hover-bg:#bfd1ec;--bs-table-hover-color:#000;color:#000;border-color:#bacbe6}.efb.table-secondary{--bs-table-bg:#e2e3e5;--bs-table-striped-bg:#d7d8da;--bs-table-striped-color:#000;--bs-table-active-bg:#cbccce;--bs-table-active-color:#000;--bs-table-hover-bg:#d1d2d4;--bs-table-hover-color:#000;color:#000;border-color:#cbccce}.efb.table-success{--bs-table-bg:#d1e7dd;--bs-table-striped-bg:#c7dbd2;--bs-table-striped-color:#000;--bs-table-active-bg:#bcd0c7;--bs-table-active-color:#000;--bs-table-hover-bg:#c1d6cc;--bs-table-hover-color:#000;color:#000;border-color:#bcd0c7}.efb.table-info{--bs-table-bg:#cff4fc;--bs-table-striped-bg:#c5e8ef;--bs-table-striped-color:#000;--bs-table-active-bg:#badce3;--bs-table-active-color:#000;--bs-table-hover-bg:#bfe2e9;--bs-table-hover-color:#000;color:#000;border-color:#badce3}.efb.table-warning{--bs-table-bg:#fff3cd;--bs-table-striped-bg:#f2e7c3;--bs-table-striped-color:#000;--bs-table-active-bg:#e6dbb9;--bs-table-active-color:#000;--bs-table-hover-bg:#ece1be;--bs-table-hover-color:#000;color:#000;border-color:#e6dbb9}.efb.table-danger{--bs-table-bg:#f8d7da;--bs-table-striped-bg:#eccccf;--bs-table-striped-color:#000;--bs-table-active-bg:#dfc2c4;--bs-table-active-color:#000;--bs-table-hover-bg:#e5c7ca;--bs-table-hover-color:#000;color:#000;border-color:#dfc2c4}.efb.table-light{--bs-table-bg:#f8f9fa;--bs-table-striped-bg:#ecedee;--bs-table-striped-color:#000;--bs-table-active-bg:#dfe0e1;--bs-table-active-color:#000;--bs-table-hover-bg:#e5e6e7;--bs-table-hover-color:#000;color:#000;border-color:#dfe0e1}.efb.table-dark{--bs-table-bg:#212529;--bs-table-striped-bg:#2c3034;--bs-table-striped-color:#fff;--bs-table-active-bg:#373b3e;--bs-table-active-color:#fff;--bs-table-hover-bg:#323539;--bs-table-hover-color:#fff;color:#fff;border-color:#373b3e}.efb.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}@media (max-width:575.98px){.efb.table-responsive-sm{overflow-x:auto;-webkit-overflow-scrolling:touch}}@media (max-width:767.98px){.efb.table-responsive-md{overflow-x:auto;-webkit-overflow-scrolling:touch}}@media (max-width:991.98px){.efb.table-responsive-lg{overflow-x:auto;-webkit-overflow-scrolling:touch}}@media (max-width:1199.98px){.efb.table-responsive-xl{overflow-x:auto;-webkit-overflow-scrolling:touch}}@media (max-width:1399.98px){.efb.table-responsive-xxl{overflow-x:auto;-webkit-overflow-scrolling:touch}}.efb.form-label{margin-bottom:.5rem}.efb.col-form-label{padding-top:calc(.375rem + 1px);padding-bottom:calc(.375rem + 1px);margin-bottom:0;font-size:inherit;line-height:1.5}.efb.col-form-label-lg{padding-top:calc(.5rem + 1px);padding-bottom:calc(.5rem + 1px);font-size:1.25rem}.efb.col-form-label-sm{padding-top:calc(.25rem + 1px);padding-bottom:calc(.25rem + 1px);font-size:.875rem}.efb.form-control{display:block;width:100%;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-clip:padding-box;border:1px solid #ced4da;-webkit-appearance:none;-moz-appearance:none;appearance:none;border-radius:.25rem;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control{transition:none}}.efb.form-control[type=file]{overflow:hidden}.efb.form-control[type=file]:not(:disabled):not([readonly]){cursor:pointer}.efb.form-control:focus{color:#212529;background-color:#fff;border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-control::-webkit-date-and-time-value{height:1.5em}.efb.form-control::-moz-placeholder{color:#6c757d;opacity:1}.efb.form-control::placeholder{color:#6c757d;opacity:1}.efb.form-control:disabled,.efb.form-control[readonly]{background-color:#e9ecef;opacity:1}.efb.form-control::file-selector-button{padding:.375rem .75rem;margin:-.375rem -.75rem;-webkit-margin-end:.75rem;margin-inline-end:.75rem;color:#212529;background-color:#e9ecef;pointer-events:none;border-color:inherit;border-style:solid;border-width:0;border-inline-end-width:1px;border-radius:0;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control::file-selector-button{transition:none}}.efb.form-control:hover:not(:disabled):not([readonly])::file-selector-button{background-color:#dde0e3}.efb.form-control::-webkit-file-upload-button{padding:.375rem .75rem;margin:-.375rem -.75rem;-webkit-margin-end:.75rem;margin-inline-end:.75rem;color:#212529;background-color:#e9ecef;pointer-events:none;border-color:inherit;border-style:solid;border-width:0;border-inline-end-width:1px;border-radius:0;-webkit-transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control::-webkit-file-upload-button{-webkit-transition:none;transition:none}}.efb.form-control:hover:not(:disabled):not([readonly])::-webkit-file-upload-button{background-color:#dde0e3}.efb.form-control-plaintext{display:block;width:100%;padding:.375rem 0;margin-bottom:0;line-height:1.5;color:#212529;background-color:transparent;border:solid transparent;border-width:1px 0}.efb.form-control-plaintext.efb.form-control-lg,.efb.form-control-plaintext.efb.form-control-sm{padding-right:0;padding-left:0}.efb.form-control-sm{min-height:calc(1.5em + .5rem + 2px);padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.efb.form-control-sm::file-selector-button{padding:.25rem .5rem;margin:-.25rem -.5rem;-webkit-margin-end:.5rem;margin-inline-end:.5rem}.efb.form-control-sm::-webkit-file-upload-button{padding:.25rem .5rem;margin:-.25rem -.5rem;-webkit-margin-end:.5rem;margin-inline-end:.5rem}.efb.form-control-lg{min-height:calc(1.5em + 1rem + 2px);padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.form-control-lg::file-selector-button{padding:.5rem 1rem;margin:-.5rem -1rem;-webkit-margin-end:1rem;margin-inline-end:1rem}.efb.form-control-lg::-webkit-file-upload-button{padding:.5rem 1rem;margin:-.5rem -1rem;-webkit-margin-end:1rem;margin-inline-end:1rem}textarea.efb.form-control{min-height:calc(1.5em + .75rem + 2px)}textarea.efb.form-control-sm{min-height:calc(1.5em + .5rem + 2px)}textarea.efb.form-control-lg{min-height:calc(1.5em + 1rem + 2px)}.efb.form-control-color{max-width:3rem;height:auto;padding:.375rem}.efb.form-control-color:not(:disabled):not([readonly]){cursor:pointer}.efb.form-control-color::-moz-color-swatch{height:1.5em;border-radius:.25rem}.efb.form-control-color::-webkit-color-swatch{height:1.5em;border-radius:.25rem}.efb.form-select{display:block;width:100%;padding:.375rem 2.25rem .375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e');background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px;border:1px solid #ced4da;border-radius:.25rem;-webkit-appearance:none;-moz-appearance:none;appearance:none}.efb.form-select:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-select[multiple],.efb.form-select[size]:not([size='1']){padding-right:.75rem;background-image:none}.efb.form-select:disabled{background-color:#e9ecef}.efb.form-select:-moz-focusring{color:transparent;text-shadow:0 0 0 #212529}.efb.form-select-sm{padding-top:.25rem;padding-bottom:.25rem;padding-left:.5rem;font-size:.875rem}.efb.form-select-lg{padding-top:.5rem;padding-bottom:.5rem;padding-left:1rem;font-size:1.25rem}.efb.form-check{display:flex;min-height:1.5rem;margin-bottom:.125rem;align-items:center;}.efb.form-check .efb.form-check-input{float:left}.efb.form-check-input{width:1em;height:1em;margin-top:.25em;vertical-align:top;background-color:#fff;background-repeat:no-repeat;background-position:center;background-size:contain;border:1px solid rgba(0,0,0,.25);-webkit-appearance:none;-moz-appearance:none;appearance:none;-webkit-print-color-adjust:exact;color-adjust:exact}.efb.form-check-input[type=checkbox]{border-radius:.25em}.efb.form-check-input[type=radio]{border-radius:50%}.efb.form-check-input:active{filter:brightness(90%)}.efb.form-check-input:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-check-input:checked{background-color:#0d6efd;border-color:#0d6efd}.efb.form-check-input:checked[type=checkbox]{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e')}.efb.form-check-input:checked[type=radio]{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='2' fill='%23fff'/%3e%3c/svg%3e')}.efb.form-check-input[type=checkbox]:indeterminate{background-color:#0d6efd;border-color:#0d6efd;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10h8'/%3e%3c/svg%3e')}.efb.form-check-input:disabled{pointer-events:none;filter:none;opacity:.5}.efb.form-check-input:disabled~.form-check-label,.efb.form-check-input[disabled]~.form-check-label{opacity:.5}.efb.form-switch{padding-left:2.5em}.efb.form-switch .efb.form-check-input{width:2em;margin-left:-2.5em;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280,0,0,.25%29'/%3e%3c/svg%3e');background-position:left center;border-radius:2em;transition:background-position .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-switch .efb.form-check-input{transition:none}}.efb.form-switch .efb.form-check-input:focus{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%2386b7fe'/%3e%3c/svg%3e')}.efb.form-switch .efb.form-check-input:checked{background-position:right center;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e')}.efb.btn-check{position:absolute;clip:rect(0,0,0,0);pointer-events:none}.efb.btn-check:disabled+.efb.btn,.efb.btn-check[disabled]+.efb.btn{pointer-events:none;filter:none;opacity:.65}.efb.form-range{width:100%;height:1.5rem;padding:0;background-color:transparent;-webkit-appearance:none;-moz-appearance:none;appearance:none}.efb.form-range:focus{outline:0}.efb.form-range:focus::-webkit-slider-thumb{box-shadow:0 0 0 1px #fff,0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-range:focus::-moz-range-thumb{box-shadow:0 0 0 1px #fff,0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-range::-moz-focus-outer{border:0}.efb.form-range::-webkit-slider-thumb{width:1rem;height:1rem;margin-top:-.25rem;background-color:#0d6efd;border:0;border-radius:1rem;-webkit-transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;-webkit-appearance:none;appearance:none}@media (prefers-reduced-motion:reduce){.efb.form-range::-webkit-slider-thumb{-webkit-transition:none;transition:none}}.efb.form-range::-webkit-slider-thumb:active{background-color:#b6d4fe}.efb.form-range::-webkit-slider-runnable-track{width:100%;height:.5rem;color:transparent;cursor:pointer;background-color:#dee2e6;border-color:transparent;border-radius:1rem}.efb.form-range::-moz-range-thumb{width:1rem;height:1rem;background-color:#0d6efd;border:0;border-radius:1rem;-moz-transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;-moz-appearance:none;appearance:none}@media (prefers-reduced-motion:reduce){.efb.form-range::-moz-range-thumb{-moz-transition:none;transition:none}}.efb.form-range::-moz-range-thumb:active{background-color:#b6d4fe}.efb.form-range::-moz-range-track{width:100%;height:.5rem;color:transparent;cursor:pointer;background-color:#dee2e6;border-color:transparent;border-radius:1rem}.efb.form-range:disabled{pointer-events:none}.efb.form-range:disabled::-webkit-slider-thumb{background-color:#adb5bd}.efb.form-range:disabled::-moz-range-thumb{background-color:#adb5bd}.efb.form-floating{position:relative}.efb.form-floating>.efb.form-control,.efb.form-floating>.efb.form-select{height:calc(3.5rem + 2px);padding:1rem .75rem}.efb.form-floating>label{position:absolute;top:0;left:0;height:100%;padding:1rem .75rem;pointer-events:none;border:1px solid transparent;transform-origin:0 0;transition:opacity .1s ease-in-out,transform .1s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-floating>label{transition:none}}.efb.form-floating>.efb.form-control::-moz-placeholder{color:transparent}.efb.form-floating>.efb.form-control::placeholder{color:transparent}.efb.form-floating>.efb.form-control:not(:-moz-placeholder-shown){padding-top:1.625rem;padding-bottom:.625rem}.efb.form-floating>.efb.form-control:focus,.efb.form-floating>.efb.form-control:not(:placeholder-shown){padding-top:1.625rem;padding-bottom:.625rem}.efb.form-floating>.efb.form-control:-webkit-autofill{padding-top:1.625rem;padding-bottom:.625rem}.efb.form-floating>.efb.form-select{padding-top:1.625rem;padding-bottom:.625rem}.efb.form-floating>.efb.form-control:not(:-moz-placeholder-shown)~label{opacity:.65;transform:scale(.85) translateY(-.5rem) translateX(.15rem)}.efb.form-floating>.efb.form-control:focus~label,.efb.form-floating>.efb.form-control:not(:placeholder-shown)~label,.efb.form-floating>.efb.form-select~label{opacity:.65;transform:scale(.85) translateY(-.5rem) translateX(.15rem)}.efb.form-floating>.efb.form-control:-webkit-autofill~label{opacity:.65;transform:scale(.85) translateY(-.5rem) translateX(.15rem)}.efb.input-group{position:relative;display:flex;flex-wrap:wrap;align-items:stretch;width:100%}.efb.input-group>.efb.form-control,.efb.input-group>.efb.form-select{position:relative;flex:1 1 auto;width:1%;min-width:0}.efb.input-group>.efb.form-control:focus,.efb.input-group>.efb.form-select:focus{z-index:3}.efb.input-group .efb.btn{position:relative;z-index:2}.efb.input-group .efb.btn:focus{z-index:3}.efb.input-group-text{display:flex;align-items:center;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;text-align:center;white-space:nowrap;background-color:#e9ecef;border:1px solid #ced4da;border-radius:.25rem}.efb.input-group-lg>.efb.btn,.efb.input-group-lg>.efb.form-control,.efb.input-group-lg>.efb.form-select,.efb.input-group-lg>.efb.input-group-text{padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.input-group-sm>.efb.btn,.efb.input-group-sm>.efb.form-control,.efb.input-group-sm>.efb.form-select,.efb.input-group-sm>.efb.input-group-text{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.efb.input-group-lg>.efb.form-select,.efb.input-group-sm>.efb.form-select{padding-right:3rem}.efb.input-group:not(.has-validation)>.efb.dropdown-toggle:nth-last-child(n+3),.efb.input-group:not(.has-validation)>:not(:last-child):not(.efb.dropdown-toggle):not(.efb.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.efb.input-group.has-validation>.efb.dropdown-toggle:nth-last-child(n+4),.efb.input-group.has-validation>:nth-last-child(n+3):not(.efb.dropdown-toggle):not(.efb.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.efb.input-group>:not(:first-child):not(.efb.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.efb.invalid-tooltip):not(.efb.invalid-feedback){margin-left:-1px;border-top-left-radius:0;border-bottom-left-radius:0}.efb.valid-feedback{display:none;width:100%;margin-top:.25rem;font-size:.875em;color:#198754}.efb.valid-tooltip{position:absolute;top:100%;z-index:5;display:none;max-width:100%;padding:.25rem .5rem;margin-top:.1rem;font-size:.875rem;color:#fff;background-color:rgba(25,135,84,.9);border-radius:.25rem}.efb.is-valid~.efb.valid-feedback,.efb.was-validated:valid~.efb.valid-feedback,.efb.was-validated:valid~{display:block}.efb.form-control.is-valid,.efb.was-validated .efb.form-control:valid{border-color:#198754;padding-right:calc(1.5em + .75rem);background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e');background-repeat:no-repeat;background-position:right calc(.375em + .1875rem) center;background-size:calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-control.is-valid:focus,.efb.was-validated .efb.form-control:valid:focus{border-color:#198754;box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.was-validated textarea.efb.form-control:valid,textarea.efb.form-control.is-valid{padding-right:calc(1.5em + .75rem);background-position:top calc(.375em + .1875rem) right calc(.375em + .1875rem)}.efb.form-select.is-valid,.efb.was-validated .efb.form-select:valid{border-color:#198754}.efb.form-select.is-valid:not([multiple]):not([size]),.efb.form-select.is-valid:not([multiple])[size='1'],.efb.was-validated .efb.form-select:valid:not([multiple]):not([size]),.efb.was-validated .efb.form-select:valid:not([multiple])[size='1']{padding-right:4.125rem;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e'),url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e');background-position:right .75rem center,center right 2.25rem;background-size:16px 12px,calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-select.is-valid:focus,.efb.was-validated .efb.form-select:valid:focus{border-color:#198754;box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.form-check-input.is-valid,.efb.was-validated .efb.form-check-input:valid{border-color:#198754}.efb.form-check-input.is-valid:checked,.efb.was-validated .efb.form-check-input:valid:checked{background-color:#198754}.efb.form-check-input.is-valid:focus,.efb.was-validated .efb.form-check-input:valid:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.form-check-input.is-valid~.form-check-label,.efb.was-validated .efb.form-check-input:valid{color:#198754}.form-check-inline .efb.form-check-input~.efb.valid-feedback{margin-left:.5em}.efb.input-group .efb.form-control.is-valid,.efb.input-group .efb.form-select.is-valid,.efb.was-validated .efb.input-group .efb.form-control:valid,.efb.was-validated .efb.input-group .efb.form-select:valid{z-index:1}.efb.input-group .efb.form-control.is-valid:focus,.efb.input-group .efb.form-select.is-valid:focus,.efb.was-validated .efb.input-group .efb.form-control:valid:focus,.efb.was-validated .efb.input-group .efb.form-select:valid:focus{z-index:3}.efb.invalid-feedback{display:none;width:100%;margin-top:.25rem;font-size:.875em;color:#dc3545}.efb.invalid-tooltip{position:absolute;top:100%;z-index:5;display:none;max-width:100%;padding:.25rem .5rem;margin-top:.1rem;font-size:.875rem;color:#fff;background-color:rgba(220,53,69,.9);border-radius:.25rem}.efb.is-invalid~.efb.invalid-feedback,.efb.is-invalid~.efb.invalid-tooltip,.efb.was-validated:invalid~.efb.invalid-feedback,.efb.was-validated:invalid~.efb.invalid-tooltip{display:block}.efb.form-control.efb.is-invalid,.efb.was-validated .efb.form-control:invalid{border-color:#dc3545;padding-right:calc(1.5em + .75rem);background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e');background-repeat:no-repeat;background-position:right calc(.375em + .1875rem) center;background-size:calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-control.efb.is-invalid:focus,.efb.was-validated .efb.form-control:invalid:focus{border-color:#dc3545;box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.was-validated textarea.efb.form-control:invalid,textarea.efb.form-control.efb.is-invalid{padding-right:calc(1.5em + .75rem);background-position:top calc(.375em + .1875rem) right calc(.375em + .1875rem)}.efb.form-select.efb.is-invalid,.efb.was-validated .efb.form-select:invalid{border-color:#dc3545}.efb.form-select.efb.is-invalid:not([multiple]):not([size]),.efb.form-select.efb.is-invalid:not([multiple])[size='1'],.efb.was-validated .efb.form-select:invalid:not([multiple]):not([size]),.efb.was-validated .efb.form-select:invalid:not([multiple])[size='1']{padding-right:4.125rem;background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e'),url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e');background-position:right .75rem center,center right 2.25rem;background-size:16px 12px,calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-select.efb.is-invalid:focus,.efb.was-validated .efb.form-select:invalid:focus{border-color:#dc3545;box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.form-check-input.efb.is-invalid,.efb.was-validated .efb.form-check-input:invalid{border-color:#dc3545}.efb.form-check-input.efb.is-invalid:checked,.efb.was-validated .efb.form-check-input:invalid:checked{background-color:#dc3545}.efb.form-check-input.efb.is-invalid:focus,.efb.was-validated .efb.form-check-input:invalid:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.form-check-input.efb.is-invalid~.form-check-label,.efb.was-validated .efb.form-check-input:invalid~.form-check-label{color:#dc3545}.efb.form-check-inline .efb.form-check-input~.efb.invalid-feedback{margin-left:.5em}.efb.input-group .efb.form-control.efb.is-invalid,.efb.input-group .efb.form-select.efb.is-invalid,.efb.was-validated .efb.input-group .efb.form-control:invalid,.efb.was-validated .efb.input-group .efb.form-select:invalid{z-index:2}.efb.input-group .efb.form-control.efb.is-invalid:focus,.efb.input-group .efb.form-select.efb.is-invalid:focus,.efb.was-validated .efb.input-group .efb.form-control:invalid:focus,.efb.was-validated .efb.input-group .efb.form-select:invalid:focus{z-index:3}.efb.btn{display:inline-block;font-weight:400;line-height:1.5;color:#212529;text-align:center;text-decoration:none;vertical-align:middle;cursor:pointer;-webkit-user-select:none;-moz-user-select:none;user-select:none;background-color:transparent;border:1px solid transparent;padding:.375rem .75rem;font-size:1rem;border-radius:.25rem;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.btn{transition:none}}.efb.btn:hover{color:#212529}.efb.btn-check:focus+.efb.btn,.efb.btn:focus{outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.btn.disabled,.efb.btn:disabled,fieldset:disabled .efb.btn{pointer-events:none;opacity:.65}.efb.btn-primary{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-primary:hover{color:#fff;background-color:#0b5ed7;border-color:#0a58ca}.efb.btn-check:focus+.efb.btn-primary,.efb.btn-primary:focus{color:#fff;background-color:#0b5ed7;border-color:#0a58ca;box-shadow:0 0 0 .25rem rgba(49,132,253,.5)}.efb.btn-check:active+.efb.btn-primary,.efb.btn-check:checked+.efb.btn-primary,.efb.btn-primary.active,.efb.btn-primary:active,.show>.efb.btn-primary.efb.dropdown-toggle{color:#fff;background-color:#0a58ca;border-color:#0a53be}.efb.btn-check:active+.efb.btn-primary:focus,.efb.btn-check:checked+.efb.btn-primary:focus,.efb.btn-primary.active:focus,.efb.btn-primary:active:focus,.show>.efb.btn-primary.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(49,132,253,.5)}.efb.btn-primary.disabled,.efb.btn-primary:disabled{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-secondary{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-secondary:hover{color:#fff;background-color:#5c636a;border-color:#565e64}.efb.btn-check:focus+.efb.btn-secondary,.efb.btn-secondary:focus{color:#fff;background-color:#5c636a;border-color:#565e64;box-shadow:0 0 0 .25rem rgba(130,138,145,.5)}.efb.btn-check:active+.efb.btn-secondary,.efb.btn-check:checked+.efb.btn-secondary,.efb.btn-secondary.active,.efb.btn-secondary:active,.show>.efb.btn-secondary.efb.dropdown-toggle{color:#fff;background-color:#565e64;border-color:#51585e}.efb.btn-check:active+.efb.btn-secondary:focus,.efb.btn-check:checked+.efb.btn-secondary:focus,.efb.btn-secondary.active:focus,.efb.btn-secondary:active:focus,.show>.efb.btn-secondary.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(130,138,145,.5)}.efb.btn-secondary.disabled,.efb.btn-secondary:disabled{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-success{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-success:hover{color:#fff;background-color:#157347;border-color:#146c43}.efb.btn-check:focus+.efb.btn-success,.efb.btn-success:focus{color:#fff;background-color:#157347;border-color:#146c43;box-shadow:0 0 0 .25rem rgba(60,153,110,.5)}.efb.btn-check:active+.efb.btn-success,.efb.btn-check:checked+.efb.btn-success,.efb.btn-success.active,.efb.btn-success:active,.show>.efb.btn-success.efb.dropdown-toggle{color:#fff;background-color:#146c43;border-color:#13653f}.efb.btn-check:active+.efb.btn-success:focus,.efb.btn-check:checked+.efb.btn-success:focus,.efb.btn-success.active:focus,.efb.btn-success:active:focus,.show>.efb.btn-success.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(60,153,110,.5)}.efb.btn-success.disabled,.efb.btn-success:disabled{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-info{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-info:hover{color:#000;background-color:#31d2f2;border-color:#25cff2}.efb.btn-check:focus+.efb.btn-info,.efb.btn-info:focus{color:#000;background-color:#31d2f2;border-color:#25cff2;box-shadow:0 0 0 .25rem rgba(11,172,204,.5)}.efb.btn-check:active+.efb.btn-info,.efb.btn-check:checked+.efb.btn-info,.efb.btn-info.active,.efb.btn-info:active,.show>.efb.btn-info.efb.dropdown-toggle{color:#000;background-color:#3dd5f3;border-color:#25cff2}.efb.btn-check:active+.efb.btn-info:focus,.efb.btn-check:checked+.efb.btn-info:focus,.efb.btn-info.active:focus,.efb.btn-info:active:focus,.show>.efb.btn-info.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(11,172,204,.5)}.efb.btn-info.disabled,.efb.btn-info:disabled{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-warning{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-warning:hover{color:#000;background-color:#ffca2c;border-color:#ffc720}.efb.btn-check:focus+.efb.btn-warning,.efb.btn-warning:focus{color:#000;background-color:#ffca2c;border-color:#ffc720;box-shadow:0 0 0 .25rem rgba(217,164,6,.5)}.efb.btn-check:active+.efb.btn-warning,.efb.btn-check:checked+.efb.btn-warning,.efb.btn-warning.active,.efb.btn-warning:active,.show>.efb.btn-warning.efb.dropdown-toggle{color:#000;background-color:#ffcd39;border-color:#ffc720}.efb.btn-check:active+.efb.btn-warning:focus,.efb.btn-check:checked+.efb.btn-warning:focus,.efb.btn-warning.active:focus,.efb.btn-warning:active:focus,.show>.efb.btn-warning.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(217,164,6,.5)}.efb.btn-warning.disabled,.efb.btn-warning:disabled{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-danger{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-danger:hover{color:#fff;background-color:#bb2d3b;border-color:#b02a37}.efb.btn-check:focus+.efb.btn-danger,.efb.btn-danger:focus{color:#fff;background-color:#bb2d3b;border-color:#b02a37;box-shadow:0 0 0 .25rem rgba(225,83,97,.5)}.efb.btn-check:active+.efb.btn-danger,.efb.btn-check:checked+.efb.btn-danger,.efb.btn-danger.active,.efb.btn-danger:active,.show>.efb.btn-danger.efb.dropdown-toggle{color:#fff;background-color:#b02a37;border-color:#a52834}.efb.btn-check:active+.efb.btn-danger:focus,.efb.btn-check:checked+.efb.btn-danger:focus,.efb.btn-danger.active:focus,.efb.btn-danger:active:focus,.show>.efb.btn-danger.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(225,83,97,.5)}.efb.btn-danger.disabled,.efb.btn-danger:disabled{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-light{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-light:hover{color:#000;background-color:#f9fafb;border-color:#f9fafb}.efb.btn-check:focus+.efb.btn-light,.efb.btn-light:focus{color:#000;background-color:#f9fafb;border-color:#f9fafb;box-shadow:0 0 0 .25rem rgba(211,212,213,.5)}.efb.btn-check:active+.efb.btn-light,.efb.btn-check:checked+.efb.btn-light,.efb.btn-light.active,.efb.btn-light:active,.show>.efb.btn-light.efb.dropdown-toggle{color:#000;background-color:#f9fafb;border-color:#f9fafb}.efb.btn-check:active+.efb.btn-light:focus,.efb.btn-check:checked+.efb.btn-light:focus,.efb.btn-light.active:focus,.efb.btn-light:active:focus,.show>.efb.btn-light.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(211,212,213,.5)}.efb.btn-light.disabled,.efb.btn-light:disabled{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-dark{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-dark:hover{color:#fff;background-color:#1c1f23;border-color:#1a1e21}.efb.btn-check:focus+.efb.btn-dark,.efb.btn-dark:focus{color:#fff;background-color:#1c1f23;border-color:#1a1e21;box-shadow:0 0 0 .25rem rgba(66,70,73,.5)}.efb.btn-check:active+.efb.btn-dark,.efb.btn-check:checked+.efb.btn-dark,.efb.btn-dark.active,.efb.btn-dark:active,.show>.efb.btn-dark.efb.dropdown-toggle{color:#fff;background-color:#1a1e21;border-color:#191c1f}.efb.btn-check:active+.efb.btn-dark:focus,.efb.btn-check:checked+.efb.btn-dark:focus,.efb.btn-dark.active:focus,.efb.btn-dark:active:focus,.show>.efb.btn-dark.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(66,70,73,.5)}.efb.btn-dark.disabled,.efb.btn-dark:disabled{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-outline-primary{color:#0d6efd;border-color:#0d6efd}.efb.btn-outline-primary:hover{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-check:focus+.efb.btn-outline-primary,.efb.btn-outline-primary:focus{box-shadow:0 0 0 .25rem rgba(13,110,253,.5)}.efb.btn-check:active+.efb.btn-outline-primary,.efb.btn-check:checked+.efb.btn-outline-primary,.efb.btn-outline-primary.active,.efb.btn-outline-primary.efb.dropdown-toggle.show,.efb.btn-outline-primary:active{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-check:active+.efb.btn-outline-primary:focus,.efb.btn-check:checked+.efb.btn-outline-primary:focus,.efb.btn-outline-primary.active:focus,.efb.btn-outline-primary.efb.dropdown-toggle.show:focus,.efb.btn-outline-primary:active:focus{box-shadow:0 0 0 .25rem rgba(13,110,253,.5)}.efb.btn-outline-primary.disabled,.efb.btn-outline-primary:disabled{color:#0d6efd;background-color:transparent}.efb.btn-outline-secondary{color:#6c757d;border-color:#6c757d}.efb.btn-outline-secondary:hover{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-check:focus+.efb.btn-outline-secondary,.efb.btn-outline-secondary:focus{box-shadow:0 0 0 .25rem rgba(108,117,125,.5)}.efb.btn-check:active+.efb.btn-outline-secondary,.efb.btn-check:checked+.efb.btn-outline-secondary,.efb.btn-outline-secondary.active,.efb.btn-outline-secondary.efb.dropdown-toggle.show,.efb.btn-outline-secondary:active{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-check:active+.efb.btn-outline-secondary:focus,.efb.btn-check:checked+.efb.btn-outline-secondary:focus,.efb.btn-outline-secondary.active:focus,.efb.btn-outline-secondary.efb.dropdown-toggle.show:focus,.efb.btn-outline-secondary:active:focus{box-shadow:0 0 0 .25rem rgba(108,117,125,.5)}.efb.btn-outline-secondary.disabled,.efb.btn-outline-secondary:disabled{color:#6c757d;background-color:transparent}.efb.btn-outline-success{color:#198754;border-color:#198754}.efb.btn-outline-success:hover{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-check:focus+.efb.btn-outline-success,.efb.btn-outline-success:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.5)}.efb.btn-check:active+.efb.btn-outline-success,.efb.btn-check:checked+.efb.btn-outline-success,.efb.btn-outline-success.active,.efb.btn-outline-success.efb.dropdown-toggle.show,.efb.btn-outline-success:active{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-check:active+.efb.btn-outline-success:focus,.efb.btn-check:checked+.efb.btn-outline-success:focus,.efb.btn-outline-success.active:focus,.efb.btn-outline-success.efb.dropdown-toggle.show:focus,.efb.btn-outline-success:active:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.5)}.efb.btn-outline-success.disabled,.efb.btn-outline-success:disabled{color:#198754;background-color:transparent}.efb.btn-outline-info{color:#0dcaf0;border-color:#0dcaf0}.efb.btn-outline-info:hover{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-check:focus+.efb.btn-outline-info,.efb.btn-outline-info:focus{box-shadow:0 0 0 .25rem rgba(13,202,240,.5)}.efb.btn-check:active+.efb.btn-outline-info,.efb.btn-check:checked+.efb.btn-outline-info,.efb.btn-outline-info.active,.efb.btn-outline-info.efb.dropdown-toggle.show,.efb.btn-outline-info:active{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-check:active+.efb.btn-outline-info:focus,.efb.btn-check:checked+.efb.btn-outline-info:focus,.efb.btn-outline-info.active:focus,.efb.btn-outline-info.efb.dropdown-toggle.show:focus,.efb.btn-outline-info:active:focus{box-shadow:0 0 0 .25rem rgba(13,202,240,.5)}.efb.btn-outline-info.disabled,.efb.btn-outline-info:disabled{color:#0dcaf0;background-color:transparent}.efb.btn-outline-warning{color:#ffc107;border-color:#ffc107}.efb.btn-outline-warning:hover{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-check:focus+.efb.btn-outline-warning,.efb.btn-outline-warning:focus{box-shadow:0 0 0 .25rem rgba(255,193,7,.5)}.efb.btn-check:active+.efb.btn-outline-warning,.efb.btn-check:checked+.efb.btn-outline-warning,.efb.btn-outline-warning.active,.efb.btn-outline-warning.efb.dropdown-toggle.show,.efb.btn-outline-warning:active{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-check:active+.efb.btn-outline-warning:focus,.efb.btn-check:checked+.efb.btn-outline-warning:focus,.efb.btn-outline-warning.active:focus,.efb.btn-outline-warning.efb.dropdown-toggle.show:focus,.efb.btn-outline-warning:active:focus{box-shadow:0 0 0 .25rem rgba(255,193,7,.5)}.efb.btn-outline-warning.disabled,.efb.btn-outline-warning:disabled{color:#ffc107;background-color:transparent}.efb.btn-outline-danger{color:#dc3545;border-color:#dc3545}.efb.btn-outline-danger:hover{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-check:focus+.efb.btn-outline-danger,.efb.btn-outline-danger:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.5)}.efb.btn-check:active+.efb.btn-outline-danger,.efb.btn-check:checked+.efb.btn-outline-danger,.efb.btn-outline-danger.active,.efb.btn-outline-danger.efb.dropdown-toggle.show,.efb.btn-outline-danger:active{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-check:active+.efb.btn-outline-danger:focus,.efb.btn-check:checked+.efb.btn-outline-danger:focus,.efb.btn-outline-danger.active:focus,.efb.btn-outline-danger.efb.dropdown-toggle.show:focus,.efb.btn-outline-danger:active:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.5)}.efb.btn-outline-danger.disabled,.efb.btn-outline-danger:disabled{color:#dc3545;background-color:transparent}.efb.btn-outline-light{color:#f8f9fa;border-color:#f8f9fa}.efb.btn-outline-light:hover{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-check:focus+.efb.btn-outline-light,.efb.btn-outline-light:focus{box-shadow:0 0 0 .25rem rgba(248,249,250,.5)}.efb.btn-check:active+.efb.btn-outline-light,.efb.btn-check:checked+.efb.btn-outline-light,.efb.btn-outline-light.active,.efb.btn-outline-light.efb.dropdown-toggle.show,.efb.btn-outline-light:active{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-check:active+.efb.btn-outline-light:focus,.efb.btn-check:checked+.efb.btn-outline-light:focus,.efb.btn-outline-light.active:focus,.efb.btn-outline-light.efb.dropdown-toggle.show:focus,.efb.btn-outline-light:active:focus{box-shadow:0 0 0 .25rem rgba(248,249,250,.5)}.efb.btn-outline-light.disabled,.efb.btn-outline-light:disabled{color:#f8f9fa;background-color:transparent}.efb.btn-outline-dark{color:#212529;border-color:#212529}.efb.btn-outline-dark:hover{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-check:focus+.efb.btn-outline-dark,.efb.btn-outline-dark:focus{box-shadow:0 0 0 .25rem rgba(33,37,41,.5)}.efb.btn-check:active+.efb.btn-outline-dark,.efb.btn-check:checked+.efb.btn-outline-dark,.efb.btn-outline-dark.active,.efb.btn-outline-dark.efb.dropdown-toggle.show,.efb.btn-outline-dark:active{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-check:active+.efb.btn-outline-dark:focus,.efb.btn-check:checked+.efb.btn-outline-dark:focus,.efb.btn-outline-dark.active:focus,.efb.btn-outline-dark.efb.dropdown-toggle.show:focus,.efb.btn-outline-dark:active:focus{box-shadow:0 0 0 .25rem rgba(33,37,41,.5)}.efb.btn-outline-dark.disabled,.efb.btn-outline-dark:disabled{color:#212529;background-color:transparent}.efb.btn-link{font-weight:400;color:#0d6efd;text-decoration:underline}.efb.btn-link:hover{color:#0a58ca}.efb.btn-link.disabled,.efb.btn-link:disabled{color:#6c757d}.efb.btn-group-lg>.efb.btn,.efb.btn-lg{padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.btn-group-sm>.efb.btn,.efb.btn-sm{padding:.2rem .3rem;font-size:.875rem;border-radius:.2rem}.efb.fade{transition:opacity .15s linear}@media (prefers-reduced-motion:reduce){.efb.fade{transition:none}}.efb.fade:not(.show){opacity:0}.efb.collapse:not(.show){display:none}.efb.collapsing{height:0;overflow:hidden;transition:height .35s ease}@media (prefers-reduced-motion:reduce){.efb.collapsing{transition:none}}.efb.dropdown,.efb.dropend,.efb.dropstart,.efb.dropup{position:relative}.efb.dropdown-toggle{white-space:nowrap}.efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:'';border-top:.3em solid;border-right:.3em solid transparent;border-bottom:0;border-left:.3em solid transparent}.efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropdown-menu{position:absolute;z-index:1000;display:none;min-width:10rem;padding:.5rem 0;margin:0;font-size:1rem;color:#212529;text-align:left;list-style:none;background-color:#fff;background-clip:padding-box;border:1px solid rgba(0,0,0,.15);border-radius:.25rem}.efb.dropdown-menu[data-bs-popper]{top:100%;left:0;margin-top:.125rem}.efb.dropdown-menu-start{--bs-position:start}.efb.dropdown-menu-start[data-bs-popper]{right:auto;left:0}.efb.dropdown-menu-end{--bs-position:end}.efb.dropdown-menu-end[data-bs-popper]{right:0;left:auto}.efb.dropup .efb.dropdown-menu[data-bs-popper]{top:auto;bottom:100%;margin-top:0;margin-bottom:.125rem}.efb.dropup .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:'';border-top:0;border-right:.3em solid transparent;border-bottom:.3em solid;border-left:.3em solid transparent}.efb.dropup .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropend .efb.dropdown-menu[data-bs-popper]{top:0;right:auto;left:100%;margin-top:0;margin-left:.125rem}.efb.dropend .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:'';border-top:.3em solid transparent;border-right:0;border-bottom:.3em solid transparent;border-left:.3em solid}.efb.dropend .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropend .efb.dropdown-toggle::after{vertical-align:0}.efb.dropstart .efb.dropdown-menu[data-bs-popper]{top:0;right:100%;left:auto;margin-top:0;margin-right:.125rem}.efb.dropstart .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:''}.efb.dropstart .efb.dropdown-toggle::after{display:none}.efb.dropstart .efb.dropdown-toggle::before{display:inline-block;margin-right:.255em;vertical-align:.255em;content:'';border-top:.3em solid transparent;border-right:.3em solid;border-bottom:.3em solid transparent}.efb.dropstart .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropstart .efb.dropdown-toggle::before{vertical-align:0}.efb.dropdown-divider{height:0;margin:.5rem 0;overflow:hidden;border-top:1px solid rgba(0,0,0,.15)}.efb.dropdown-item{display:block;width:100%;padding:.25rem 1rem;clear:both;font-weight:400;color:#212529;text-align:inherit;text-decoration:none;white-space:nowrap;background-color:transparent;border:0}.efb.dropdown-item:focus,.efb.dropdown-item:hover{color:#1e2125;background-color:#e9ecef}.efb.dropdown-item.active,.efb.dropdown-item:active{color:#fff;text-decoration:none;background-color:#0d6efd}.efb.dropdown-item.disabled,.efb.dropdown-item:disabled{color:#adb5bd;pointer-events:none;background-color:transparent}.efb.dropdown-menu.show{display:block}.efb.dropdown-header{display:block;padding:.5rem 1rem;margin-bottom:0;font-size:.875rem;color:#6c757d;white-space:nowrap}.efb.dropdown-item-text{display:block;padding:.25rem 1rem;color:#212529}.efb.dropdown-menu-dark{color:#dee2e6;background-color:#343a40;border-color:rgba(0,0,0,.15)}.efb.dropdown-menu-dark .efb.dropdown-item{color:#dee2e6}.efb.dropdown-menu-dark .efb.dropdown-item:focus,.efb.dropdown-menu-dark .efb.dropdown-item:hover{color:#fff;background-color:rgba(255,255,255,.15)}.efb.dropdown-menu-dark .efb.dropdown-item.active,.efb.dropdown-menu-dark .efb.dropdown-item:active{color:#fff;background-color:#0d6efd}.efb.dropdown-menu-dark .efb.dropdown-item.disabled,.efb.dropdown-menu-dark .efb.dropdown-item:disabled{color:#adb5bd}.efb.dropdown-menu-dark .efb.dropdown-divider{border-color:rgba(0,0,0,.15)}.efb.dropdown-menu-dark .efb.dropdown-item-text{color:#dee2e6}.efb.dropdown-menu-dark .efb.dropdown-header{color:#adb5bd}.efb.btn-group{position:relative;display:inline-flex;vertical-align:middle}.efb.btn-group>.efb.btn{position:relative;flex:1 1 auto}.efb.btn-group>.efb.btn-check:checked+.efb.btn,.efb.btn-group>.efb.btn-check:focus+.efb.btn,.efb.btn-group>.efb.btn.active,.efb.btn-group>.efb.btn:active,.efb.btn-group>.efb.btn:focus,.efb.btn-group>.efb.btn:hover{z-index:1}.efb.btn-toolbar{display:flex;flex-wrap:wrap;justify-content:flex-start}.efb.btn-toolbar .efb.input-group{width:auto}.efb.btn-group>.efb.btn-group:not(:first-child),.efb.btn-group>.efb.btn:not(:first-child){margin-left:-1px}.efb.btn-group>.efb.btn-group:not(:last-child)>.efb.btn,.efb.btn-group>.efb.btn:not(:last-child):not(.efb.dropdown-toggle){border-top-right-radius:0;border-bottom-right-radius:0}.efb.btn-group>.efb.btn-group:not(:first-child)>.efb.btn,.efb.btn-group>.efb.btn:nth-child(n+3),.efb.btn-group>:not(.efb.btn-check)+.efb.btn{border-top-left-radius:0;border-bottom-left-radius:0}.efb.dropdown-toggle-split{padding-right:.5625rem;padding-left:.5625rem}.efb.dropdown-toggle-split::after,.efb.dropend .efb.dropdown-toggle-split::after,.efb.dropup .efb.dropdown-toggle-split::after{margin-left:0}.efb.dropstart .efb.dropdown-toggle-split::before{margin-right:0}.efb.btn-group-sm>.efb.btn+.efb.dropdown-toggle-split,.efb.btn-sm+.efb.dropdown-toggle-split{padding-right:.375rem;padding-left:.375rem}.efb.btn-group-lg>.efb.btn+.efb.dropdown-toggle-split,.efb.btn-lg+.efb.dropdown-toggle-split{padding-right:.75rem;padding-left:.75rem}.efb.nav{display:flex;flex-wrap:wrap;padding-left:0;margin-bottom:0;list-style:none}.efb.nav-link{display:block;padding:.5rem 1rem;color:#0d6efd;text-decoration:none;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.nav-link{transition:none}}.efb.nav-link:focus,.efb.nav-link:hover{color:#0a58ca}.efb.nav-link.disabled{color:#6c757d;pointer-events:none;cursor:default}.efb.nav-tabs{border-bottom:1px solid #dee2e6}.efb.nav-tabs .efb.nav-link{margin-bottom:-1px;background:0 0;border:1px solid transparent;border-top-left-radius:.25rem;border-top-right-radius:.25rem}.efb.nav-tabs .efb.nav-link:focus,.efb.nav-tabs .efb.nav-link:hover{border-color:#e9ecef #e9ecef #dee2e6;isolation:isolate}.efb.nav-tabs .efb.nav-link.disabled{color:#6c757d;background-color:transparent;border-color:transparent}.efb.nav-tabs .efb.nav-item.show .efb.nav-link,.efb.nav-tabs .efb.nav-link.active{color:#495057;background-color:#fff;border-color:#dee2e6 #dee2e6 #fff}.efb.nav-tabs .efb.dropdown-menu{margin-top:-1px;border-top-left-radius:0;border-top-right-radius:0}.efb.nav-pills .efb.nav-link{background:0 0;border:0;border-radius:.25rem}.efb.nav-pills .efb.nav-link.active,.efb.nav-pills .show>.efb.nav-link{color:#fff;background-color:#0d6efd}.efb.nav-fill .efb.nav-item,.efb.nav-fill>.efb.nav-link{flex:1 1 auto;text-align:center}.efb.nav-justified .efb.nav-item,.efb.nav-justified>.efb.nav-link{flex-basis:0;flex-grow:1;text-align:center}.efb.nav-fill .efb.nav-item .efb.nav-link,.efb.nav-justified .efb.nav-item .efb.nav-link{width:100%}.efb.tab-content>.tab-pane{display:none}.efb.tab-content>.active{display:block}.efb.navbar{position:relative;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;padding-top:.5rem;padding-bottom:.5rem}.efb.navbar>.efb.container,.efb.navbar>.efb.container-fluid,.efb.navbar>.efb.container-lg,.efb.navbar>.efb.container-md,.efb.navbar>.efb.container-sm,.efb.navbar>.efb.container-xl,.efb.navbar>.efb.container-xxl{display:flex;flex-wrap:inherit;align-items:center;justify-content:space-between}.efb.navbar-brand{padding-top:.3125rem;padding-bottom:.3125rem;margin-right:1rem;font-size:1.25rem;text-decoration:none;white-space:nowrap}.efb.navbar-nav{display:flex;flex-direction:column;padding-left:0;margin-bottom:0;list-style:none}.efb.navbar-nav .efb.nav-link{padding-right:0;padding-left:0}.efb.navbar-nav .efb.dropdown-menu{position:static}.efb.navbar-text{padding-top:.5rem;padding-bottom:.5rem}.efb.navbar-collapse{flex-basis:100%;flex-grow:1;align-items:center}.efb.navbar-toggler{padding:.25rem .75rem;font-size:1.25rem;line-height:1;background-color:transparent;border:1px solid transparent;border-radius:.25rem;transition:box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.navbar-toggler{transition:none}}.efb.navbar-toggler:hover{text-decoration:none}.efb.navbar-toggler:focus{text-decoration:none;outline:0;box-shadow:0 0 0 .25rem}.efb.navbar-toggler-icon{display:inline-block;width:1.5em;height:1.5em;vertical-align:middle;background-repeat:no-repeat;background-position:center;background-size:100%}.efb.navbar-nav-scroll{max-height:var(--bs-scroll-height,75vh);overflow-y:auto}@media (min-width:992px){.efb.navbar-expand-lg{flex-wrap:nowrap;justify-content:flex-start}.efb.navbar-expand-lg .efb.navbar-nav{flex-direction:row}.efb.navbar-expand-lg .efb.navbar-nav .efb.dropdown-menu{position:absolute}.efb.navbar-expand-lg .efb.navbar-nav .efb.nav-link{padding-right:.5rem;padding-left:.5rem}.efb.navbar-expand-lg .efb.navbar-nav-scroll{overflow:visible}.efb.navbar-expand-lg .efb.navbar-collapse{display:flex!important;flex-basis:auto}.efb.navbar-expand-lg .efb.navbar-toggler{display:none}}@media (min-width:1400px){.efb.card-img{flex-wrap:nowrap;justify-content:flex-start}.efb.card-img .efb.navbar-nav{flex-direction:row}.efb.card-img .efb.navbar-nav .efb.dropdown-menu{position:absolute}.efb.card-img .efb.navbar-nav .efb.nav-link{padding-right:.5rem;padding-left:.5rem}.efb.card-img .efb.navbar-nav-scroll{overflow:visible}.efb.card-img .efb.navbar-collapse{display:flex!important;flex-basis:auto}.efb.card-img .efb.navbar-toggler{display:none}}.efb.navbar-expand{flex-wrap:nowrap;justify-content:flex-start}.efb.navbar-expand .efb.navbar-nav{flex-direction:row}.efb.navbar-expand .efb.navbar-nav .efb.dropdown-menu{position:absolute}.efb.navbar-expand .efb.navbar-nav .efb.nav-link{padding-right:.5rem;padding-left:.5rem}.efb.navbar-expand .efb.navbar-nav-scroll{overflow:visible}.efb.navbar-expand .efb.navbar-collapse{display:flex!important;flex-basis:auto}.efb.navbar-expand .efb.navbar-toggler{display:none}.efb.navbar-light .efb.navbar-brand{color:rgba(0,0,0,.9)}.efb.navbar-light .efb.navbar-brand:focus,.efb.navbar-light .efb.navbar-brand:hover{color:rgba(0,0,0,.9)}.efb.navbar-light .efb.navbar-nav .efb.nav-link{color:rgba(0,0,0,.55)}.efb.navbar-light .efb.navbar-nav .efb.nav-link:focus,.efb.navbar-light .efb.navbar-nav .efb.nav-link:hover{color:rgba(0,0,0,.7)}.efb.navbar-light .efb.navbar-nav .efb.nav-link.disabled{color:rgba(0,0,0,.3)}.efb.navbar-light .efb.navbar-nav .efb.nav-link.active,.efb.navbar-light .efb.navbar-nav .show>.efb.nav-link{color:rgba(0,0,0,.9)}.efb.navbar-light .efb.navbar-toggler{color:rgba(0,0,0,.55);border-color:rgba(0,0,0,.1)}.efb.navbar-light .efb.navbar-toggler-icon{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%280,0,0,.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e')}.efb.navbar-light .efb.navbar-text{color:rgba(0,0,0,.55)}.efb.navbar-light .efb.navbar-text a,.efb.navbar-light .efb.navbar-text a:focus,.efb.navbar-light .efb.navbar-text a:hover{color:rgba(0,0,0,.9)}.efb.navbar-dark .efb.navbar-brand{color:#fff}.efb.navbar-dark .efb.navbar-brand:focus,.efb.navbar-dark .efb.navbar-brand:hover{color:#fff}.efb.navbar-dark .efb.navbar-nav .efb.nav-link{color:rgba(255,255,255,.55)}.efb.navbar-dark .efb.navbar-nav .efb.nav-link:focus,.efb.navbar-dark .efb.navbar-nav .efb.nav-link:hover{color:rgba(255,255,255,.75)}.efb.navbar-dark .efb.navbar-nav .efb.nav-link.disabled{color:rgba(255,255,255,.25)}.efb.navbar-dark .efb.navbar-nav .efb.nav-link.active,.efb.navbar-dark .efb.navbar-nav .show>.efb.nav-link{color:#fff}.efb.navbar-dark .efb.navbar-toggler{color:rgba(255,255,255,.55);border-color:rgba(255,255,255,.1)}.efb.navbar-dark .efb.navbar-toggler-icon{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255,255,255,.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e')}.efb.navbar-dark .efb.navbar-text{color:rgba(255,255,255,.55)}.efb.navbar-dark .efb.navbar-text a,.efb.navbar-dark .efb.navbar-text a:focus,.efb.navbar-dark .efb.navbar-text a:hover{color:#fff}.efb.card{position:relative;display:flex;flex-direction:column;min-width:0;word-wrap:break-word;background-color:#fff;background-clip:border-box;border:1px solid rgba(0,0,0,.125);border-radius:.25rem}.efb.card>hr{margin-right:0;margin-left:0}.efb.card>.efb.list-group{border-top:inherit;border-bottom:inherit}.efb.card>.efb.list-group:first-child{border-top-width:0;border-top-left-radius:calc(.25rem - 1px);border-top-right-radius:calc(.25rem - 1px)}.card>.efb.list-group:last-child{border-bottom-width:0;border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}.efb.card>.efb.card-header+.efb.list-group,.efb.card>.efb.list-group+.efb.card-footer{border-top:0}.efb.card-body{flex:1 1 auto;padding:1rem 1rem}.efb.card-title{margin-bottom:.5rem}.efb.card-text:last-child{margin-bottom:0}.efb.card-header{padding:.5rem 1rem;margin-bottom:0;background-color:rgba(0,0,0,.03);border-bottom:1px solid rgba(0,0,0,.125)}.efb.card-header:first-child{border-radius:calc(.25rem - 1px) calc(.25rem - 1px) 0 0}.efb.card-footer{padding:.5rem 1rem;background-color:rgba(0,0,0,.03);border-top:1px solid rgba(0,0,0,.125)}.efb.card-footer:last-child{border-radius:0 0 calc(.25rem - 1px) calc(.25rem - 1px)}.efb.card-header-tabs{margin-right:-.5rem;margin-bottom:-.5rem;margin-left:-.5rem;border-bottom:0}.efb.card-header-pills{margin-right:-.5rem;margin-left:-.5rem}.efb.card-img-overlay{position:absolute;top:0;right:0;bottom:0;left:0;padding:1rem;border-radius:calc(.25rem - 1px)}.efb.card-img,.efb.card-img-bottom,.efb.card-img-top{width:100%}.efb.card-img,.efb.card-img-top{border-top-left-radius:calc(.25rem - 1px);border-top-right-radius:calc(.25rem - 1px)}.efb.card-img,.efb.card-img-bottom{border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}.efb.card-group>.card{margin-bottom:.75rem}@media (min-width:576px){.efb.card-group{display:flex;flex-flow:row wrap}.efb.card-group>.card{flex:1 0 0%;margin-bottom:0}.efb.card-group>.card+.card{margin-left:0;border-left:0}.efb.card-group>.card:not(:last-child){border-top-right-radius:0;border-bottom-right-radius:0}.efb.card-group>.card:not(:last-child) .efb.card-header,.efb.card-group>.card:not(:last-child) .efb.card-img-top{border-top-right-radius:0}.efb.card-group>.card:not(:last-child) .efb.card-footer,.efb.card-group>.card:not(:last-child) .efb.card-img-bottom{border-bottom-right-radius:0}.efb.card-group>.card:not(:first-child){border-top-left-radius:0;border-bottom-left-radius:0}.efb.card-group>.card:not(:first-child) .efb.card-header,.efb.card-group>.card:not(:first-child) .efb.card-img-top{border-top-left-radius:0}.efb.card-group>.card:not(:first-child) .efb.card-footer,.efb.card-group>.card:not(:first-child) .efb.card-img-bottom{border-bottom-left-radius:0}}.efb.page-link{position:relative;display:block;color:#0d6efd;text-decoration:none;background-color:#fff;border:1px solid #dee2e6;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.page-link{transition:none}}.efb.page-link:hover{z-index:2;color:#0a58ca;background-color:#e9ecef;border-color:#dee2e6}.efb.page-link:focus{z-index:3;color:#0a58ca;background-color:#e9ecef;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.page-item:not(:first-child) .efb.page-link{margin-left:-1px}.efb.page-item.active .efb.page-link{z-index:3;color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.page-item.disabled .efb.page-link{color:#6c757d;pointer-events:none;background-color:#fff;border-color:#dee2e6}.efb.page-link{padding:.375rem .75rem}.efb.page-item:first-child .efb.page-link{border-top-left-radius:.25rem;border-bottom-left-radius:.25rem}.efb.page-item:last-child .efb.page-link{border-top-right-radius:.25rem;border-bottom-right-radius:.25rem}.efb.pagination-lg .efb.page-link{padding:.75rem 1.5rem;font-size:1.25rem}.efb.pagination-lg .efb.page-item:first-child .efb.page-link{border-top-left-radius:.3rem;border-bottom-left-radius:.3rem}.efb.pagination-lg .efb.page-item:last-child .efb.page-link{border-top-right-radius:.3rem;border-bottom-right-radius:.3rem}.efb.pagination-sm .efb.page-link{padding:.25rem .5rem;font-size:.875rem}.efb.pagination-sm .efb.page-item:first-child .efb.page-link{border-top-left-radius:.2rem;border-bottom-left-radius:.2rem}.pagination-sm .efb.page-item:last-child .efb.page-link{border-top-right-radius:.2rem;border-bottom-right-radius:.2rem}.efb.badge{display:inline-block;padding:.35em .65em;font-size:.75em;font-weight:700;line-height:1;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.25rem}.efb.badge:empty{display:none}.efb.btn .efb.badge{position:relative;top:-1px}.efb.alert{position:relative;padding:1rem 1rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.25rem}.efb.alert-heading{color:inherit}.efb.alert-link{font-weight:700}.efb.alert-dismissible{padding-right:3rem}.efb.alert-dismissible .efb.btn-close{position:absolute;top:0;right:0;z-index:2;padding:1.25rem 1rem}.efb.alert-primary{color:#084298;background-color:#cfe2ff;border-color:#b6d4fe}.efb.alert-primary .efb.alert-link{color:#06357a}.efb.alert-secondary{color:#41464b;background-color:#e2e3e5;border-color:#d3d6d8}.efb.alert-secondary .efb.alert-link{color:#34383c}.efb.alert-success{color:#0f5132;background-color:#d1e7dd;border-color:#badbcc}.efb.alert-success .efb.alert-link{color:#0c4128}.efb.alert-info{color:#055160;background-color:#cff4fc;border-color:#b6effb}.efb.alert-info .efb.alert-link{color:#04414d}.efb.alert-warning{color:#664d03;background-color:#fff3cd;border-color:#ffecb5}.efb.alert-warning .efb.alert-link{color:#523e02}.efb.alert-danger{color:#842029;background-color:#f8d7da;border-color:#f5c2c7}.efb.alert-danger .efb.alert-link{color:#6a1a21}.efb.alert-light{color:#636464;background-color:#fefefe;border-color:#fdfdfe}.efb.alert-light .efb.alert-link{color:#4f5050}.efb.alert-dark{color:#141619;background-color:#d3d3d4;border-color:#bcbebf}.efb.alert-dark .efb.alert-link{color:#101214}@-webkit-keyframes progress-bar-stripes{0%{background-position-x:1rem}}@keyframes progress-bar-stripes{0%{background-position-x:1rem}}.efb.progress{display:flex;height:1rem;overflow:hidden;font-size:.75rem;background-color:#e9ecef;border-radius:.25rem}.efb.progress-bar{display:flex;flex-direction:column;justify-content:center;overflow:hidden;color:#fff;text-align:center;white-space:nowrap;background-color:#0d6efd;transition:width .6s ease}@media (prefers-reduced-motion:reduce){.efb.progress-bar{transition:none}}.efb.progress-bar-striped{background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent)!important;background-size:1rem 1rem}.efb.progress-bar-animated{-webkit-animation:1s linear infinite progress-bar-stripes;animation:1s linear infinite progress-bar-stripes}@media (prefers-reduced-motion:reduce){.efb.progress-bar-animated{-webkit-animation:none;animation:none}}.efb.list-group{display:flex;flex-direction:column;padding-left:0;margin-bottom:0;border-radius:.25rem}.efb.list-group-numbered{list-style-type:none;counter-reset:section}.efb.list-group-numbered>li::before{content:counters(section,'.') '. ';counter-increment:section}.efb.list-group-item-action{width:100%;color:#495057;text-align:inherit}.efb.list-group-item-action:focus,.efb.list-group-item-action:hover{z-index:1;color:#495057;text-decoration:none;background-color:#f8f9fa}.efb.list-group-item-action:active{color:#212529;background-color:#e9ecef}.efb.list-group-item{position:relative;display:block;padding:.5rem 1rem;color:#212529;text-decoration:none;background-color:#fff;border:1px solid rgba(0,0,0,.125)}.efb.list-group-item:first-child{border-top-left-radius:inherit;border-top-right-radius:inherit}.efb.list-group-item:last-child{border-bottom-right-radius:inherit;border-bottom-left-radius:inherit}.efb.list-group-item.disabled,.efb.list-group-item:disabled{color:#6c757d;pointer-events:none;background-color:#fff}.efb.list-group-item.active{z-index:2;color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.list-group-item+.efb.list-group-item{border-top-width:0}.efb.list-group-item+.efb.list-group-item.active{margin-top:-1px;border-top-width:1px}.efb.list-group-horizontal{flex-direction:row}.efb.list-group-horizontal>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}@media (min-width:576px){.efb.list-group-horizontal-sm{flex-direction:row}.efb.list-group-horizontal-sm>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-sm>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-sm>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-sm>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-sm>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:768px){.efb.list-group-horizontal-md{flex-direction:row}.efb.list-group-horizontal-md>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-md>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-md>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-md>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-md>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:992px){.efb.list-group-horizontal-lg{flex-direction:row}.efb.list-group-horizontal-lg>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-lg>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-lg>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-lg>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-lg>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:1200px){.efb.list-group-horizontal-xl{flex-direction:row}.efb.list-group-horizontal-xl>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-xl>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-xl>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-xl>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-xl>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:1400px){.efb.list-group-horizontal-xxl{flex-direction:row}.efb.list-group-horizontal-xxl>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-xxl>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-xxl>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-xxl>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-xxl>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}.efb.list-group-flush{border-radius:0}.efb.list-group-flush>.efb.list-group-item{border-width:0 0 1px}.efb.list-group-flush>.efb.list-group-item:last-child{border-bottom-width:0}.efb.list-group-item-primary{color:#084298;background-color:#cfe2ff}.efb.list-group-item-primary.efb.list-group-item-action:focus,.efb.list-group-item-primary.efb.list-group-item-action:hover{color:#084298;background-color:#bacbe6}.efb.list-group-item-primary.efb.list-group-item-action.active{color:#fff;background-color:#084298;border-color:#084298}.efb.list-group-item-secondary{color:#41464b;background-color:#e2e3e5}.efb.list-group-item-secondary.efb.list-group-item-action:focus,.efb.list-group-item-secondary.efb.list-group-item-action:hover{color:#41464b;background-color:#cbccce}.efb.list-group-item-secondary.efb.list-group-item-action.active{color:#fff;background-color:#41464b;border-color:#41464b}.efb.list-group-item-success{color:#0f5132;background-color:#d1e7dd}.efb.list-group-item-success.efb.list-group-item-action:focus,.efb.list-group-item-success.efb.list-group-item-action:hover{color:#0f5132;background-color:#bcd0c7}.efb.list-group-item-success.efb.list-group-item-action.active{color:#fff;background-color:#0f5132;border-color:#0f5132}.efb.list-group-item-info{color:#055160;background-color:#cff4fc}.efb.list-group-item-info.efb.list-group-item-action:focus,.efb.list-group-item-info.efb.list-group-item-action:hover{color:#055160;background-color:#badce3}.efb.list-group-item-info.efb.list-group-item-action.active{color:#fff;background-color:#055160;border-color:#055160}.efb.list-group-item-warning{color:#664d03;background-color:#fff3cd}.efb.list-group-item-warning.efb.list-group-item-action:focus,.efb.list-group-item-warning.efb.list-group-item-action:hover{color:#664d03;background-color:#e6dbb9}.efb.list-group-item-warning.efb.list-group-item-action.active{color:#fff;background-color:#664d03;border-color:#664d03}.efb.list-group-item-danger{color:#842029;background-color:#f8d7da}.efb.list-group-item-danger.efb.list-group-item-action:focus,.efb.list-group-item-danger.efb.list-group-item-action:hover{color:#842029;background-color:#dfc2c4}.efb.list-group-item-danger.efb.list-group-item-action.active{color:#fff;background-color:#842029;border-color:#842029}.efb.list-group-item-light{color:#636464;background-color:#fefefe}.efb.list-group-item-light.efb.list-group-item-action:focus,.efb.list-group-item-light.efb.list-group-item-action:hover{color:#636464;background-color:#e5e5e5}.efb.list-group-item-light.efb.list-group-item-action.active{color:#fff;background-color:#636464;border-color:#636464}.efb.list-group-item-dark{color:#141619;background-color:#d3d3d4}.efb.list-group-item-dark.efb.list-group-item-action:focus,.efb.list-group-item-dark.efb.list-group-item-action:hover{color:#141619;background-color:#bebebf}.efb.list-group-item-dark.efb.list-group-item-action.active{color:#fff;background-color:#141619;border-color:#141619}.efb.btn-close{box-sizing:content-box;width:1em;height:1em;padding:.25em .25em;color:#000;background:transparent url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e') center/1em auto no-repeat;border:0;border-radius:.25rem;opacity:.5}.efb.btn-close:hover{color:#000;text-decoration:none;opacity:.75}.efb.btn-close:focus{outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25);opacity:1}.efb.btn-close.disabled,.efb.btn-close:disabled{pointer-events:none;-webkit-user-select:none;-moz-user-select:none;user-select:none;opacity:.25}.efb.btn-close-white{filter:invert(1) grayscale(100%) brightness(200%)}.efb.modal{position:fixed;top:0;left:0;z-index:1060;display:none;width:100%;height:100%;overflow-x:hidden;overflow-y:auto;outline:0}.efb.modal-dialog{position:relative;width:auto;margin:.5rem;pointer-events:none}.efb.modal.efb.fade .efb.modal-dialog{transition:transform .3s ease-out;transform:translate(0,-50px)}@media (prefers-reduced-motion:reduce){.efb.modal.efb.fade .efb.modal-dialog{transition:none}}.efb.modal.show .modal-dialog{transform:none}.efb.modal.modal-static .efb.modal-dialog{transform:scale(1.02)}.efb.modal-dialog-scrollable{height:calc(100% - 1rem)}.efb.modal-dialog-scrollable .efb.modal-content{max-height:100%;overflow:hidden}.efb.modal-dialog-scrollable .efb.modal-body{overflow-y:auto}.efb.modal-dialog-centered{display:flex;align-items:center;min-height:calc(100% - 1rem)}.efb.modal-content{position:relative;display:flex;flex-direction:column;width:100%;pointer-events:auto;background-color:#fff;background-clip:padding-box;border:1px solid rgba(0,0,0,.2);border-radius:.3rem;outline:0}.efb.modal-backdrop{position:fixed;top:0;left:0;z-index:1040;width:100vw;height:100vh;background-color:#000}.efb.modal-backdrop.efb.fade{opacity:0}.efb.modal-backdrop.show{opacity:.5}.efb.modal-header{display:flex;flex-shrink:0;align-items:center;justify-content:space-between;padding:1rem 1rem;border-bottom:1px solid #dee2e6;border-top-left-radius:calc(.3rem - 1px);border-top-right-radius:calc(.3rem - 1px)}.efb.modal-header .efb.btn-close{padding:.5rem .5rem;margin:-.5rem -.5rem -.5rem auto}.efb.modal-title{margin-bottom:0;line-height:1.5}.efb.modal-body{position:relative;flex:1 1 auto;padding:1rem}.efb.modal-footer{display:flex;flex-wrap:wrap;flex-shrink:0;align-items:center;justify-content:flex-end;padding:.75rem;border-top:1px solid #dee2e6;border-bottom-right-radius:calc(.3rem - 1px);border-bottom-left-radius:calc(.3rem - 1px)}.efb.modal-footer>*{margin:.25rem}@media (min-width:576px){.efb.modal-dialog{max-width:500px;margin:1.75rem auto}.efb.modal-dialog-scrollable{height:calc(100% - 3.5rem)}.efb.modal-dialog-centered{min-height:calc(100% - 3.5rem)}.efb.modal-sm{max-width:300px}}.efb.modal-content{height:100%;border:0;border-radius:0}.efb.modal-header{border-radius:0}.efb.modal-body{overflow-y:auto}.efb.modal-footer{border-radius:0}.efb.tooltip{position:absolute;z-index:1080;display:block;margin:0;font-family:var(--bs-font-sans-serif);font-style:normal;font-weight:400;line-height:1.5;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;letter-spacing:normal;word-break:normal;word-spacing:normal;white-space:normal;line-break:auto;font-size:.875rem;word-wrap:break-word;opacity:0}.efb.tooltip.show{opacity:.9}.efb.tooltip .efb.tooltip-arrow{position:absolute;display:block;width:.8rem;height:.4rem}.efb.tooltip .efb.tooltip-arrow::before{position:absolute;content:'';border-color:transparent;border-style:solid}.efb.bs-tooltip-auto[data-popper-placement^=top],.efb.bs-tooltip-top{padding:.4rem 0}.efb.bs-tooltip-auto[data-popper-placement^=top] .efb.tooltip-arrow,.efb.bs-tooltip-top .efb.tooltip-arrow{bottom:0}.efb.bs-tooltip-auto[data-popper-placement^=top] .efb.tooltip-arrow::before,.efb.bs-tooltip-top .efb.tooltip-arrow::before{top:-1px;border-width:.4rem .4rem 0;border-top-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=right],.efb.bs-tooltip-end{padding:0 .4rem}.efb.bs-tooltip-auto[data-popper-placement^=right] .efb.tooltip-arrow,.efb.bs-tooltip-end .efb.tooltip-arrow{left:0;width:.4rem;height:.8rem}.efb.bs-tooltip-auto[data-popper-placement^=right] .efb.tooltip-arrow::before,.efb.bs-tooltip-end .efb.tooltip-arrow::before{right:-1px;border-width:.4rem .4rem .4rem 0;border-right-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=bottom],.efb.bs-tooltip-bottom{padding:.4rem 0}.efb.bs-tooltip-auto[data-popper-placement^=bottom] .efb.tooltip-arrow,.efb.bs-tooltip-bottom .efb.tooltip-arrow{top:0}.efb.bs-tooltip-auto[data-popper-placement^=bottom] .efb.tooltip-arrow::before,.efb.bs-tooltip-bottom .efb.tooltip-arrow::before{bottom:-1px;border-width:0 .4rem .4rem;border-bottom-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=left],.efb.bs-tooltip-start{padding:0 .4rem}.efb.bs-tooltip-auto[data-popper-placement^=left] .efb.tooltip-arrow,.efb.bs-tooltip-start .efb.tooltip-arrow{right:0;width:.4rem;height:.8rem}.efb.bs-tooltip-auto[data-popper-placement^=left] .efb.tooltip-arrow::before,.efb.bs-tooltip-start .efb.tooltip-arrow::before{left:-1px;border-width:.4rem 0 .4rem .4rem;border-left-color:#000}.efb.tooltip-inner{max-width:200px;padding:.25rem .5rem;color:#fff;text-align:center;background-color:#000;border-radius:.25rem}.efb.popover{position:absolute;top:0;left:0;z-index:1070;display:block;max-width:276px;font-family:var(--bs-font-sans-serif);font-style:normal;font-weight:400;line-height:1.5;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;letter-spacing:normal;word-break:normal;word-spacing:normal;white-space:normal;line-break:auto;font-size:.875rem;word-wrap:break-word;background-color:#fff;background-clip:padding-box;border:1px solid rgba(0,0,0,.2);border-radius:.3rem}.efb.popover .efb.popover-arrow{position:absolute;display:block;width:1rem;height:.5rem}.efb.popover .efb.popover-arrow::after,.efb.popover .efb.popover-arrow::before{position:absolute;display:block;content:'';border-color:transparent;border-style:solid}.efb.bs-popover-auto[data-popper-placement^=top]>.efb.popover-arrow,.efb.bs-popover-top>.efb.popover-arrow{bottom:calc(-.5rem - 1px)}.efb.bs-popover-auto[data-popper-placement^=top]>.efb.popover-arrow::before,.efb.bs-popover-top>.efb.popover-arrow::before{bottom:0;border-width:.5rem .5rem 0;border-top-color:rgba(0,0,0,.25)}.efb.bs-popover-auto[data-popper-placement^=top]>.efb.popover-arrow::after,.efb.bs-popover-top>.efb.popover-arrow::after{bottom:1px;border-width:.5rem .5rem 0;border-top-color:#fff}.efb.bs-popover-auto[data-popper-placement^=right]>.efb.popover-arrow,.efb.bs-popover-end>.efb.popover-arrow{left:calc(-.5rem - 1px);width:.5rem;height:1rem}.efb.bs-popover-auto[data-popper-placement^=right]>.efb.popover-arrow::before,.efb.bs-popover-end>.efb.popover-arrow::before{left:0;border-width:.5rem .5rem .5rem 0;border-right-color:rgba(0,0,0,.25)}.efb.bs-popover-auto[data-popper-placement^=right]>.efb.popover-arrow::after,.efb.bs-popover-end>.efb.popover-arrow::after{left:1px;border-width:.5rem .5rem .5rem 0;border-right-color:#fff}.efb.bs-popover-auto[data-popper-placement^=bottom]>.efb.popover-arrow,.efb.bs-popover-bottom>.efb.popover-arrow{top:calc(-.5rem - 1px)}.efb.bs-popover-auto[data-popper-placement^=bottom]>.efb.popover-arrow::before,.efb.bs-popover-bottom>.efb.popover-arrow::before{top:0;border-width:0 .5rem .5rem .5rem;border-bottom-color:rgba(0,0,0,.25)}.efb.bs-popover-auto[data-popper-placement^=bottom]>.efb.popover-arrow::after,.efb.bs-popover-bottom>.efb.popover-arrow::after{top:1px;border-width:0 .5rem .5rem .5rem;border-bottom-color:#fff}.efb.bs-popover-auto[data-popper-placement^=bottom] .efb.popover-header::before,.efb.bs-popover-bottom .efb.popover-header::before{position:absolute;top:0;left:50%;display:block;width:1rem;margin-left:-.5rem;content:'';border-bottom:1px solid #f0f0f0}.efb.bs-popover-auto[data-popper-placement^=left]>.efb.popover-arrow,.efb.bs-popover-start>.efb.popover-arrow{right:calc(-.5rem - 1px);width:.5rem;height:1rem}.efb.bs-popover-auto[data-popper-placement^=left]>.efb.popover-arrow::before,.efb.bs-popover-start>.efb.popover-arrow::before{right:0;border-width:.5rem 0 .5rem .5rem;border-left-color:rgba(0,0,0,.25)}.efb.bs-popover-auto[data-popper-placement^=left]>.efb.popover-arrow::after,.efb.bs-popover-start>.efb.popover-arrow::after{right:1px;border-width:.5rem 0 .5rem .5rem;border-left-color:#fff}.efb.popover-header{padding:.5rem 1rem;margin-bottom:0;font-size:1rem;background-color:#f0f0f0;border-bottom:1px solid #d8d8d8;border-top-left-radius:calc(.3rem - 1px);border-top-right-radius:calc(.3rem - 1px)}.efb.popover-header:empty{display:none}.efb.popover-body{padding:1rem 1rem;color:#212529}.efb.carousel{position:relative}.efb.carousel.pointer-event{touch-action:pan-y}.efb.carousel-inner{position:relative;width:100%;overflow:hidden}.efb.carousel-inner::after{display:block;clear:both;content:''}.efb.carousel-item{position:relative;display:none;float:left;width:100%;margin-right:-100%;-webkit-backface-visibility:hidden;backface-visibility:hidden;transition:transform .6s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.carousel-item{transition:none}}.efb.carousel-item-next,.efb.carousel-item-prev,.efb.carousel-item.active{display:block}.active.efb.carousel-item-end,.efb.carousel-item-next:not(.efb.carousel-item-start){transform:translateX(100%)}.active.efb.carousel-item-start,.efb.carousel-item-prev:not(.efb.carousel-item-end){transform:translateX(-100%)}.efb.carousel-fade .efb.carousel-item{opacity:0;transition-property:opacity;transform:none}.efb.carousel-fade .efb.carousel-item-next.efb.carousel-item-start,.efb.carousel-fade .efb.carousel-item-prev.efb.carousel-item-end,.efb.carousel-fade .efb.carousel-item.active{z-index:1;opacity:1}.efb.carousel-fade .active.efb.carousel-item-end,.efb.carousel-fade .active.efb.carousel-item-start{z-index:0;opacity:0;transition:opacity 0s .6s}@media (prefers-reduced-motion:reduce){.efb.carousel-fade .active.efb.carousel-item-end,.efb.carousel-fade .active.efb.carousel-item-start{transition:none}}.efb.carousel-control-next,.efb.carousel-control-prev{position:absolute;top:0;bottom:0;z-index:1;display:flex;align-items:center;justify-content:center;width:15%;padding:0;color:#fff;text-align:center;background:0 0;border:0;opacity:.5;transition:opacity .15s ease}@media (prefers-reduced-motion:reduce){.efb.carousel-control-next,.efb.carousel-control-prev{transition:none}}.efb.carousel-control-next:focus,.efb.carousel-control-next:hover,.efb.carousel-control-prev:focus,.efb.carousel-control-prev:hover{color:#fff;text-decoration:none;outline:0;opacity:.9}.efb.carousel-control-prev{left:0}.efb.carousel-control-next{right:0}.efb.carousel-control-next-icon,.efb.carousel-control-prev-icon{display:inline-block;width:2rem;height:2rem;background-repeat:no-repeat;background-position:50%;background-size:100% 100%}.efb.carousel-control-prev-icon{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z'/%3e%3c/svg%3e')}.efb.carousel-control-next-icon{background-image:url('data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e')}.efb.carousel-indicators{position:absolute;right:0;bottom:0;left:0;z-index:2;display:flex;justify-content:center;padding:0;margin-right:15%;margin-bottom:1rem;margin-left:15%;list-style:none}.efb.carousel-indicators [data-bs-target]{box-sizing:content-box;flex:0 1 auto;width:30px;height:3px;padding:0;margin-right:3px;margin-left:3px;text-indent:-999px;cursor:pointer;background-color:#fff;background-clip:padding-box;border:0;border-top:10px solid transparent;border-bottom:10px solid transparent;opacity:.5;transition:opacity .6s ease}@media (prefers-reduced-motion:reduce){.efb.carousel-indicators [data-bs-target]{transition:none}}.efb.carousel-indicators .active{opacity:1}.efb.carousel-caption{position:absolute;right:15%;bottom:1.25rem;left:15%;padding-top:1.25rem;padding-bottom:1.25rem;color:#fff;text-align:center}.efb.carousel-dark .efb.carousel-control-next-icon,.efb.carousel-dark .efb.carousel-control-prev-icon{filter:invert(1) grayscale(100)}.efb.carousel-dark .efb.carousel-indicators [data-bs-target]{background-color:#000}.efb.carousel-dark .efb.carousel-caption{color:#000}@-webkit-keyframes spinner-border{to{transform:rotate(360deg)}}@keyframes spinner-border{to{transform:rotate(360deg)}}.efb.spinner-border{display:inline-block;width:2rem;height:2rem;vertical-align:-.125em;border:.25em solid currentColor;border-right-color:transparent;border-radius:50%;-webkit-animation:.75s linear infinite spinner-border;animation:.75s linear infinite spinner-border}.efb.spinner-border-sm{width:1rem;height:1rem;border-width:.2em}@-webkit-keyframes spinner-grow{0%{transform:scale(0)}50%{opacity:1;transform:none}}@keyframes spinner-grow{0%{transform:scale(0)}50%{opacity:1;transform:none}}.efb.spinner-grow{display:inline-block;width:2rem;height:2rem;vertical-align:-.125em;background-color:currentColor;border-radius:50%;opacity:0;-webkit-animation:.75s linear infinite spinner-grow;animation:.75s linear infinite spinner-grow}.efb.spinner-grow-sm{width:1rem;height:1rem}@media (prefers-reduced-motion:reduce){.efb.spinner-border,.efb.spinner-grow{-webkit-animation-duration:1.5s;animation-duration:1.5s}}.efb.offcanvas{position:fixed;bottom:0;z-index:1050;display:flex;flex-direction:column;max-width:100%;visibility:hidden;background-color:#fff;background-clip:padding-box;outline:0;transition:transform .3s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.offcanvas{transition:none}}.efb.offcanvas-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1rem}.efb.offcanvas-header .efb.btn-close{padding:.5rem .5rem;margin:-.5rem -.5rem -.5rem auto}.efb.offcanvas-title{margin-bottom:0;line-height:1.5}.efb.offcanvas-body{flex-grow:1;padding:1rem 1rem;overflow-y:auto}.efb.offcanvas-start{top:0;left:0;width:400px;border-right:1px solid rgba(0,0,0,.2);transform:translateX(-100%)}.efb.offcanvas-end{top:0;right:0;width:400px;border-left:1px solid rgba(0,0,0,.2);transform:translateX(100%)}.efb.offcanvas-top{top:0;right:0;left:0;height:30vh;max-height:100%;border-bottom:1px solid rgba(0,0,0,.2);transform:translateY(-100%)}.efb.offcanvas-bottom{right:0;left:0;height:30vh;max-height:100%;border-top:1px solid rgba(0,0,0,.2);transform:translateY(100%)}.efb.offcanvas.show{transform:none}.efb.clearfix::after{display:block;clear:both;content:''}.efb.link-primary{color:#0d6efd}.efb.link-primary:focus,.efb.link-primary:hover{color:#0a58ca}.efb.link-secondary{color:#6c757d}.efb.link-secondary:focus,.efb.link-secondary:hover{color:#565e64}.efb.link-success{color:#198754}.efb.link-success:focus,.efb.link-success:hover{color:#146c43}.efb.link-info{color:#0dcaf0}.efb.link-info:focus,.efb.link-info:hover{color:#3dd5f3}.efb.link-warning{color:#ffc107}.efb.link-warning:focus,.efb.link-warning:hover{color:#ffcd39}.efb.link-danger{color:#dc3545}.efb.link-danger:focus,.efb.link-danger:hover{color:#b02a37}.efb.link-light{color:#f8f9fa}.efb.link-light:focus,.efb.link-light:hover{color:#f9fafb}.efb.link-dark{color:#212529}.efb.link-dark:focus,.efb.link-dark:hover{color:#1a1e21}.efb.ratio{position:relative;width:100%}.efb.ratio::before{display:block;padding-top:var(--bs-aspect-ratio);content:''}.efb.ratio>*{position:absolute;top:0;left:0;width:100%;height:100%}.efb.ratio-1x1{--bs-aspect-ratio:100%}.efb.ratio-4x3{--bs-aspect-ratio:calc(3 / 4 * 100%)}.efb.ratio-16x9{--bs-aspect-ratio:calc(9 / 16 * 100%)}.efb.ratio-21x9{--bs-aspect-ratio:calc(9 / 21 * 100%)}.efb.fixed-top{position:fixed;top:0;right:0;left:0;z-index:1030}.efb.fixed-bottom{position:fixed;right:0;bottom:0;left:0;z-index:1030}.efb.sticky-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}@media (min-width:576px){.efb.sticky-sm-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}}@media (min-width:768px){.efb.sticky-md-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}}@media (min-width:992px){.efb.sticky-lg-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}}@media (min-width:1200px){.efb.sticky-xl-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}}@media (min-width:1400px){.efb.sticky-xxl-top{position:-webkit-sticky;position:sticky;top:0;z-index:1020}}.efb.visually-hidden,.efb.visually-hidden-focusable:not(:focus):not(:focus-within){position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.efb.stretched-link::after{position:absolute;top:0;right:0;bottom:0;left:0;z-index:1;content:''}.efb.text-truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.efb.align-baseline{vertical-align:baseline!important}.efb.align-top{vertical-align:top!important}.efb.align-middle{vertical-align:middle!important}.efb.align-bottom{vertical-align:bottom!important}.efb.align-text-bottom{vertical-align:text-bottom!important}.efb.align-text-top{vertical-align:text-top!important}.efb.float-start{float:left!important}.efb.float-end{float:right!important}.efb.float-none{float:none!important}.efb.overflow-auto{overflow:auto!important}.efb.overflow-hidden{overflow:hidden!important}.efb.overflow-visible{overflow:visible!important}.efb.overflow-scroll{overflow:scroll!important}.efb.d-inline{display:inline!important}.efb.d-inline-block{display:inline-block!important}.efb.d-block{display:block!important}.efb.d-grid{display:grid!important}.efb.d-table{display:table!important}.efb.d-table-row{display:table-row!important}.efb.d-table-cell{display:table-cell!important}.efb.d-flex{display:flex!important}.efb.d-inline-flex{display:inline-flex!important}.efb.d-none{display:none!important}.efb.shadow{box-shadow:0 .5rem 1rem rgba(0,0,0,.15)!important}.efb.shadow-sm{box-shadow:0 .125rem .25rem rgba(0,0,0,.075)!important}.efb.shadow-lg{box-shadow:0 1rem 3rem rgba(0,0,0,.175)!important}.efb.shadow-none{box-shadow:none!important}.efb.position-static{position:static!important}.efb.position-relative{position:relative!important}.efb.position-absolute{position:absolute!important}.efb.position-fixed{position:fixed!important}.efb.position-sticky{position:-webkit-sticky!important;position:sticky!important}.efb.top-0{top:0!important}.efb.top-50{top:50%!important}.efb.top-100{top:100%!important}.efb.bottom-0{bottom:0!important}.efb.bottom-50{bottom:50%!important}.efb.bottom-100{bottom:100%!important}.efb.start-0{left:0!important}.efb.start-50{left:50%!important}.efb.start-100{left:100%!important}.efb.end-0{right:0!important}.efb.end-50{right:50%!important}.efb.end-100{right:100%!important}.efb.translate-middle{transform:translate(-50%,-50%)!important}.efb.translate-middle-x{transform:translateX(-50%)!important}.efb.translate-middle-y{transform:translateY(-50%)!important}.efb.border{border:1px solid #dee2e6!important}.efb.border-0{border:0!important}.efb.border-top{border-top:1px solid #dee2e6!important}.efb.border-top-0{border-top:0!important}.efb.border-end{border-right:1px solid #dee2e6!important}.efb.border-end-0{border-right:0!important}.efb.border-bottom{border-bottom:1px solid #dee2e6!important}.efb.border-bottom-0{border-bottom:0!important}.efb.border-start{border-left:1px solid #dee2e6!important}.efb.border-start-0{border-left:0!important}.efb.border-primary{border-color:#0d6efd!important}.efb.border-secondary{border-color:#6c757d!important}.efb.border-success{border-color:#198754!important}.efb.border-info{border-color:#0dcaf0!important}.efb.border-warning{border-color:#ffc107!important}.efb.border-danger{border-color:#dc3545!important}.efb.border-light{border-color:#f8f9fa!important}.efb.border-dark{border-color:#212529!important}.efb.border-white{border-color:#fff!important}.efb.border-1{border-width:1px!important}.efb.border-2{border-width:2px!important}.efb.border-3{border-width:3px!important}.efb.border-4{border-width:4px!important}.efb.border-5{border-width:5px!important}.efb.w-25{width:25%!important}.efb.w-50{width:50%!important}.efb.w-75{width:75%!important}.efb.w-100{width:100%!important}.efb.w-auto{width:auto!important}.efb.mw-100{max-width:100%!important}.efb.vw-100{width:100vw!important}.efb.min-vw-100{min-width:100vw!important}.efb.h-25{height:25%!important}.efb.h-50{height:50%!important}.efb.h-75{height:75%!important}.efb.h-100{height:100%!important}.efb.h-auto{height:auto!important}.efb.mh-100{max-height:100%!important}.efb.vh-100{height:100vh!important}.efb.min-vh-100{min-height:100vh!important}.efb.flex-fill{flex:1 1 auto!important}.efb.flex-row{flex-direction:row!important}.efb.flex-column{flex-direction:column!important}.efb.flex-row-reverse{flex-direction:row-reverse!important}.efb.flex-column-reverse{flex-direction:column-reverse!important}.efb.flex-grow-0{flex-grow:0!important}.efb.flex-grow-1{flex-grow:1!important}.efb.flex-shrink-0{flex-shrink:0!important}.efb.flex-shrink-1{flex-shrink:1!important}.efb.flex-wrap{flex-wrap:wrap!important}.efb.flex-nowrap{flex-wrap:nowrap!important}.efb.flex-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-0{gap:0!important}.efb.gap-1{gap:.25rem!important}.efb.gap-2{gap:.5rem!important}.efb.gap-3{gap:1rem!important}.efb.gap-4{gap:1.5rem!important}.efb.gap-5{gap:3rem!important}.efb.justify-content-start{justify-content:flex-start!important}.efb.justify-content-end{justify-content:flex-end!important}.efb.justify-content-center{justify-content:center!important}.efb.justify-content-between{justify-content:space-between!important}.efb.justify-content-around{justify-content:space-around!important}.efb.justify-content-evenly{justify-content:space-evenly!important}.efb.align-items-start{align-items:flex-start!important}.efb.align-items-end{align-items:flex-end!important}.efb.align-items-center{align-items:center!important}.efb.align-items-baseline{align-items:baseline!important}.efb.align-items-stretch{align-items:stretch!important}.efb.align-content-start{align-content:flex-start!important}.efb.align-content-end{align-content:flex-end!important}.efb.align-content-center{align-content:center!important}.efb.align-content-between{align-content:space-between!important}.efb.align-content-around{align-content:space-around!important}.efb.align-content-stretch{align-content:stretch!important}.efb.align-self-auto{align-self:auto!important}.efb.align-self-start{align-self:flex-start!important}.efb.align-self-end{align-self:flex-end!important}.efb.align-self-center{align-self:center!important}.efb.align-self-baseline{align-self:baseline!important}.efb.align-self-stretch{align-self:stretch!important}.efb.order-first{order:-1!important}.efb.order-0{order:0!important}.efb.order-1{order:1!important}.efb.order-2{order:2!important}.efb.order-3{order:3!important}.efb.order-4{order:4!important}.efb.order-5{order:5!important}.efb.order-last{order:6!important}.efb.m-0{margin:0!important}.efb.m-1{margin:.25rem!important}.efb.m-2{margin:.5rem!important}.efb.m-3{margin:1rem!important}.efb.m-4{margin:1.5rem!important}.efb.m-5{margin:3rem!important}.efb.m-auto{margin:auto!important}.efb.mx-0{margin-right:0!important;margin-left:0!important}.efb.mx-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-0{margin-top:0!important;margin-bottom:0!important}.efb.my-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-0{margin-top:0!important}.efb.mt-1{margin-top:.25rem!important}.efb.mt-2{margin-top:.5rem!important}.efb.mt-3{margin-top:1rem!important}.efb.mt-4{margin-top:1.5rem!important}.efb.mt-5{margin-top:3rem!important}.efb.mt-auto{margin-top:auto!important}.efb.me-0{margin-right:0!important}.efb.me-1{margin-right:.25rem!important}.efb.me-2{margin-right:.5rem!important}.efb.me-3{margin-right:1rem!important}.efb.me-4{margin-right:1.5rem!important}.efb.me-5{margin-right:3rem!important}.efb.me-auto{margin-right:auto!important}.efb.mb-0{margin-bottom:0!important}.efb.mb-1{margin-bottom:.25rem!important}.efb.mb-2{margin-bottom:.5rem!important}.efb.mb-3{margin-bottom:1rem!important}.efb.mb-4{margin-bottom:1.5rem!important}.efb.mb-5{margin-bottom:3rem!important}.efb.mb-auto{margin-bottom:auto!important}.efb.ms-0{margin-left:0!important}.efb.ms-1{margin-left:.25rem!important}.efb.ms-2{margin-left:.5rem!important}.efb.ms-3{margin-left:1rem!important}.efb.ms-4{margin-left:1.5rem!important}.efb.ms-5{margin-left:3rem!important}.efb.ms-auto{margin-left:auto!important}.efb.p-0{padding:0!important}.efb.p-1{padding:.25rem!important}.efb.p-2{padding:.5rem!important}.efb.p-3{padding:1rem!important}.efb.p-4{padding:1.5rem!important}.efb.p-5{padding:3rem!important}.efb.px-0{padding-right:0!important;padding-left:0!important}.efb.px-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-0{padding-top:0!important;padding-bottom:0!important}.efb.py-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-0{padding-top:0!important}.efb.pt-1{padding-top:.25rem!important}.efb.pt-2{padding-top:.5rem!important}.efb.pt-3{padding-top:1rem!important}.efb.pt-4{padding-top:1.5rem!important}.efb.pt-5{padding-top:3rem!important}.efb.pe-0{padding-right:0!important}.efb.pe-1{padding-right:.25rem!important}.efb.pe-2{padding-right:.5rem!important}.efb.pe-3{padding-right:1rem!important}.efb.pe-4{padding-right:1.5rem!important}.efb.pe-5{padding-right:3rem!important}.efb.pb-0{padding-bottom:0!important}.efb.pb-1{padding-bottom:.25rem!important}.efb.pb-2{padding-bottom:.5rem!important}.efb.pb-3{padding-bottom:1rem!important}.efb.pb-4{padding-bottom:1.5rem!important}.efb.pb-5{padding-bottom:3rem!important}.efb.ps-0{padding-left:0!important}.efb.ps-1{padding-left:.25rem!important}.efb.ps-2{padding-left:.5rem!important}.efb.ps-3{padding-left:1rem!important}.efb.ps-4{padding-left:1.5rem!important}.efb.ps-5{padding-left:3rem!important}.efb.font-monospace{font-family:var(--bs-font-monospace)!important}.efb.fst-italic{font-style:italic!important}.efb.fst-normal{font-style:normal!important}.efb.fw-light{font-weight:300!important}.efb.fw-lighter{font-weight:lighter!important}.efb.fw-normal{font-weight:400!important}.efb.fw-bold{font-weight:700!important}.efb.fw-bolder{font-weight:bolder!important}.efb.lh-1{line-height:1!important}.efb.lh-sm{line-height:1.25!important}.efb.lh-base{line-height:1.5!important}.efb.lh-lg{line-height:2!important}.efb.text-start{text-align:left!important}.efb.text-end{text-align:right!important}.efb.text-center{text-align:center!important}.efb.text-decoration-none{text-decoration:none!important}.efb.text-decoration-underline{text-decoration:underline!important}.efb.text-decoration-line-through{text-decoration:line-through!important}.efb.text-lowercase{text-transform:lowercase!important}.efb.text-uppercase{text-transform:uppercase!important}.efb.text-capitalize{text-transform:capitalize!important}.efb.text-wrap{white-space:normal!important}.efb.text-nowrap{white-space:nowrap!important}.efb.text-break{word-wrap:break-word!important;word-break:break-word!important}.efb.text-primary{color:#0d6efd!important}.efb.text-secondary{color:#6c757d!important}.efb.text-success{color:#198754!important}.efb.text-info{color:#0dcaf0!important}.efb.text-warning{color:#ffc107!important}.efb.text-danger{color:#dc3545!important}.efb.text-light{color:#f8f9fa!important}.efb.text-dark{color:#212529!important}.efb.text-white{color:#fff!important}.efb.text-body{color:#212529!important}.efb.text-muted{color:#6c757d!important}.efb.text-black-50{color:rgba(0,0,0,.5)!important}.efb.text-white-50{color:rgba(255,255,255,.5)!important}.efb.text-reset{color:inherit!important}.efb.bg-primary{background-color:#0d6efd!important}.efb.bg-secondary{background-color:#6c757d!important}.efb.bg-success{background-color:#198754!important}.efb.bg-info{background-color:#0dcaf0!important}.efb.bg-warning{background-color:#ffc107!important}.efb.bg-danger{background-color:#dc3545!important}.efb.bg-light{background-color:#f8f9fa!important}.efb.bg-dark{background-color:#212529!important}.efb.bg-body{background-color:#fff!important}.efb.bg-white{background-color:#fff!important}.efb.bg-transparent{background-color:transparent!important}.efb.bg-gradient{background-image:var(--bs-gradient)!important}.efb.user-select-all{-webkit-user-select:all!important;-moz-user-select:all!important;user-select:all!important}.efb.user-select-auto{-webkit-user-select:auto!important;-moz-user-select:auto!important;user-select:auto!important}.efb.user-select-none{-webkit-user-select:none!important;-moz-user-select:none!important;user-select:none!important}.efb.pe-none{pointer-events:none!important}.efb.pe-auto{pointer-events:auto!important}.efb.rounded{border-radius:.25rem!important}.efb.rounded-0{border-radius:0!important}.efb.rounded-1{border-radius:.2rem!important}.efb.rounded-2{border-radius:.25rem!important}.efb.rounded-3{border-radius:.3rem!important}.efb.rounded-circle{border-radius:50%!important}.efb.rounded-pill{border-radius:50rem!important}.efb.rounded-top{border-top-left-radius:.25rem!important;border-top-right-radius:.25rem!important}.efb.rounded-end{border-top-right-radius:.25rem!important;border-bottom-right-radius:.25rem!important}.efb.rounded-bottom{border-bottom-right-radius:.25rem!important;border-bottom-left-radius:.25rem!important}.efb.rounded-start{border-bottom-left-radius:.25rem!important;border-top-left-radius:.25rem!important}.efb.visible{visibility:visible!important}.efb.invisible{visibility:hidden!important}@media (min-width:576px){.efb.float-sm-start{float:left!important}.efb.float-sm-end{float:right!important}.efb.float-sm-none{float:none!important}.efb.d-sm-inline{display:inline!important}.efb.d-sm-inline-block{display:inline-block!important}.efb.d-sm-block{display:block!important}.efb.d-sm-grid{display:grid!important}.efb.d-sm-table{display:table!important}.efb.d-sm-table-row{display:table-row!important}.efb.d-sm-table-cell{display:table-cell!important}.efb.d-sm-flex{display:flex!important}.efb.d-sm-inline-flex{display:inline-flex!important}.efb.d-sm-none{display:none!important}.efb.flex-sm-fill{flex:1 1 auto!important}.efb.flex-sm-row{flex-direction:row!important}.efb.flex-sm-column{flex-direction:column!important}.efb.flex-sm-row-reverse{flex-direction:row-reverse!important}.efb.flex-sm-column-reverse{flex-direction:column-reverse!important}.efb.flex-sm-grow-0{flex-grow:0!important}.efb.flex-sm-grow-1{flex-grow:1!important}.efb.flex-sm-shrink-0{flex-shrink:0!important}.efb.flex-sm-shrink-1{flex-shrink:1!important}.efb.flex-sm-wrap{flex-wrap:wrap!important}.efb.flex-sm-nowrap{flex-wrap:nowrap!important}.efb.flex-sm-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-sm-0{gap:0!important}.efb.gap-sm-1{gap:.25rem!important}.efb.gap-sm-2{gap:.5rem!important}.efb.gap-sm-3{gap:1rem!important}.efb.gap-sm-4{gap:1.5rem!important}.efb.gap-sm-5{gap:3rem!important}.efb.justify-content-sm-start{justify-content:flex-start!important}.efb.justify-content-sm-end{justify-content:flex-end!important}.efb.justify-content-sm-center{justify-content:center!important}.efb.justify-content-sm-between{justify-content:space-between!important}.efb.justify-content-sm-around{justify-content:space-around!important}.efb.justify-content-sm-evenly{justify-content:space-evenly!important}.efb.align-items-sm-start{align-items:flex-start!important}.efb.align-items-sm-end{align-items:flex-end!important}.efb.align-items-sm-center{align-items:center!important}.efb.align-items-sm-baseline{align-items:baseline!important}.efb.align-items-sm-stretch{align-items:stretch!important}.efb.align-content-sm-start{align-content:flex-start!important}.efb.align-content-sm-end{align-content:flex-end!important}.efb.align-content-sm-center{align-content:center!important}.efb.align-content-sm-between{align-content:space-between!important}.efb.align-content-sm-around{align-content:space-around!important}.efb.align-content-sm-stretch{align-content:stretch!important}.efb.align-self-sm-auto{align-self:auto!important}.efb.align-self-sm-start{align-self:flex-start!important}.efb.align-self-sm-end{align-self:flex-end!important}.efb.align-self-sm-center{align-self:center!important}.efb.align-self-sm-baseline{align-self:baseline!important}.efb.align-self-sm-stretch{align-self:stretch!important}.efb.order-sm-first{order:-1!important}.efb.order-sm-0{order:0!important}.efb.order-sm-1{order:1!important}.efb.order-sm-2{order:2!important}.efb.order-sm-3{order:3!important}.efb.order-sm-4{order:4!important}.efb.order-sm-5{order:5!important}.efb.order-sm-last{order:6!important}.efb.m-sm-0{margin:0!important}.efb.m-sm-1{margin:.25rem!important}.efb.m-sm-2{margin:.5rem!important}.efb.m-sm-3{margin:1rem!important}.efb.m-sm-4{margin:1.5rem!important}.efb.m-sm-5{margin:3rem!important}.efb.m-sm-auto{margin:auto!important}.efb.mx-sm-0{margin-right:0!important;margin-left:0!important}.efb.mx-sm-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-sm-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-sm-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-sm-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-sm-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-sm-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-sm-0{margin-top:0!important;margin-bottom:0!important}.efb.my-sm-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-sm-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-sm-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-sm-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-sm-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-sm-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-sm-0{margin-top:0!important}.efb.mt-sm-1{margin-top:.25rem!important}.efb.mt-sm-2{margin-top:.5rem!important}.efb.mt-sm-3{margin-top:1rem!important}.efb.mt-sm-4{margin-top:1.5rem!important}.efb.mt-sm-5{margin-top:3rem!important}.efb.mt-sm-auto{margin-top:auto!important}.efb.me-sm-0{margin-right:0!important}.efb.me-sm-1{margin-right:.25rem!important}.efb.me-sm-2{margin-right:.5rem!important}.efb.me-sm-3{margin-right:1rem!important}.efb.me-sm-4{margin-right:1.5rem!important}.efb.me-sm-5{margin-right:3rem!important}.efb.me-sm-auto{margin-right:auto!important}.efb.mb-sm-0{margin-bottom:0!important}.efb.mb-sm-1{margin-bottom:.25rem!important}.efb.mb-sm-2{margin-bottom:.5rem!important}.efb.mb-sm-3{margin-bottom:1rem!important}.efb.mb-sm-4{margin-bottom:1.5rem!important}.efb.mb-sm-5{margin-bottom:3rem!important}.efb.mb-sm-auto{margin-bottom:auto!important}.efb.ms-sm-0{margin-left:0!important}.efb.ms-sm-1{margin-left:.25rem!important}.efb.ms-sm-2{margin-left:.5rem!important}.efb.ms-sm-3{margin-left:1rem!important}.efb.ms-sm-4{margin-left:1.5rem!important}.efb.ms-sm-5{margin-left:3rem!important}.efb.ms-sm-auto{margin-left:auto!important}.efb.p-sm-0{padding:0!important}.efb.p-sm-1{padding:.25rem!important}.efb.p-sm-2{padding:.5rem!important}.efb.p-sm-3{padding:1rem!important}.efb.p-sm-4{padding:1.5rem!important}.efb.p-sm-5{padding:3rem!important}.efb.px-sm-0{padding-right:0!important;padding-left:0!important}.efb.px-sm-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-sm-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-sm-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-sm-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-sm-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-sm-0{padding-top:0!important;padding-bottom:0!important}.efb.py-sm-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-sm-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-sm-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-sm-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-sm-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-sm-0{padding-top:0!important}.efb.pt-sm-1{padding-top:.25rem!important}.efb.pt-sm-2{padding-top:.5rem!important}.efb.pt-sm-3{padding-top:1rem!important}.efb.pt-sm-4{padding-top:1.5rem!important}.efb.pt-sm-5{padding-top:3rem!important}.efb.pe-sm-0{padding-right:0!important}.efb.pe-sm-1{padding-right:.25rem!important}.efb.pe-sm-2{padding-right:.5rem!important}.efb.pe-sm-3{padding-right:1rem!important}.efb.pe-sm-4{padding-right:1.5rem!important}.efb.pe-sm-5{padding-right:3rem!important}.efb.pb-sm-0{padding-bottom:0!important}.efb.pb-sm-1{padding-bottom:.25rem!important}.efb.pb-sm-2{padding-bottom:.5rem!important}.efb.pb-sm-3{padding-bottom:1rem!important}.efb.pb-sm-4{padding-bottom:1.5rem!important}.efb.pb-sm-5{padding-bottom:3rem!important}.efb.ps-sm-0{padding-left:0!important}.efb.ps-sm-1{padding-left:.25rem!important}.efb.ps-sm-2{padding-left:.5rem!important}.efb.ps-sm-3{padding-left:1rem!important}.efb.ps-sm-4{padding-left:1.5rem!important}.efb.ps-sm-5{padding-left:3rem!important}.efb.text-sm-start{text-align:left!important}.efb.text-sm-end{text-align:right!important}.efb.text-sm-center{text-align:center!important}}@media (min-width:768px){.efb.float-md-start{float:left!important}.efb.float-md-end{float:right!important}.efb.float-md-none{float:none!important}.efb.d-md-inline{display:inline!important}.efb.d-md-inline-block{display:inline-block!important}.efb.d-md-block{display:block!important}.efb.d-md-grid{display:grid!important}.efb.d-md-table{display:table!important}.efb.d-md-table-row{display:table-row!important}.efb.d-md-table-cell{display:table-cell!important}.efb.d-md-flex{display:flex!important}.efb.d-md-inline-flex{display:inline-flex!important}.efb.d-md-none{display:none!important}.efb.flex-md-fill{flex:1 1 auto!important}.efb.flex-md-row{flex-direction:row!important}.efb.flex-md-column{flex-direction:column!important}.efb.flex-md-row-reverse{flex-direction:row-reverse!important}.efb.flex-md-column-reverse{flex-direction:column-reverse!important}.efb.flex-md-grow-0{flex-grow:0!important}.efb.flex-md-grow-1{flex-grow:1!important}.efb.flex-md-shrink-0{flex-shrink:0!important}.efb.flex-md-shrink-1{flex-shrink:1!important}.efb.flex-md-wrap{flex-wrap:wrap!important}.efb.flex-md-nowrap{flex-wrap:nowrap!important}.efb.flex-md-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-md-0{gap:0!important}.efb.gap-md-1{gap:.25rem!important}.efb.gap-md-2{gap:.5rem!important}.efb.gap-md-3{gap:1rem!important}.efb.gap-md-4{gap:1.5rem!important}.efb.gap-md-5{gap:3rem!important}.efb.justify-content-md-start{justify-content:flex-start!important}.efb.justify-content-md-end{justify-content:flex-end!important}.efb.justify-content-md-center{justify-content:center!important}.efb.justify-content-md-between{justify-content:space-between!important}.efb.justify-content-md-around{justify-content:space-around!important}.efb.justify-content-md-evenly{justify-content:space-evenly!important}.efb.align-items-md-start{align-items:flex-start!important}.efb.align-items-md-end{align-items:flex-end!important}.efb.align-items-md-center{align-items:center!important}.efb.align-items-md-baseline{align-items:baseline!important}.efb.align-items-md-stretch{align-items:stretch!important}.efb.align-content-md-start{align-content:flex-start!important}.efb.align-content-md-end{align-content:flex-end!important}.efb.align-content-md-center{align-content:center!important}.efb.align-content-md-between{align-content:space-between!important}.efb.align-content-md-around{align-content:space-around!important}.efb.align-content-md-stretch{align-content:stretch!important}.efb.align-self-md-auto{align-self:auto!important}.efb.align-self-md-start{align-self:flex-start!important}.efb.align-self-md-end{align-self:flex-end!important}.efb.align-self-md-center{align-self:center!important}.efb.align-self-md-baseline{align-self:baseline!important}.efb.align-self-md-stretch{align-self:stretch!important}.efb.order-md-first{order:-1!important}.efb.order-md-0{order:0!important}.efb.order-md-1{order:1!important}.efb.order-md-2{order:2!important}.efb.order-md-3{order:3!important}.efb.order-md-4{order:4!important}.efb.order-md-5{order:5!important}.efb.order-md-last{order:6!important}.efb.m-md-0{margin:0!important}.efb.m-md-1{margin:.25rem!important}.efb.m-md-2{margin:.5rem!important}.efb.m-md-3{margin:1rem!important}.efb.m-md-4{margin:1.5rem!important}.efb.m-md-5{margin:3rem!important}.efb.m-md-auto{margin:auto!important}.efb.mx-md-0{margin-right:0!important;margin-left:0!important}.efb.mx-md-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-md-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-md-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-md-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-md-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-md-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-md-0{margin-top:0!important;margin-bottom:0!important}.efb.my-md-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-md-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-md-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-md-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-md-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-md-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-md-0{margin-top:0!important}.efb.mt-md-1{margin-top:.25rem!important}.efb.mt-md-2{margin-top:.5rem!important}.efb.mt-md-3{margin-top:1rem!important}.efb.mt-md-4{margin-top:1.5rem!important}.efb.mt-md-5{margin-top:3rem!important}.efb.mt-md-auto{margin-top:auto!important}.efb.me-md-0{margin-right:0!important}.efb.me-md-1{margin-right:.25rem!important}.efb.me-md-2{margin-right:.5rem!important}.efb.me-md-3{margin-right:1rem!important}.efb.me-md-4{margin-right:1.5rem!important}.efb.me-md-5{margin-right:3rem!important}.efb.me-md-auto{margin-right:auto!important}.efb.mb-md-0{margin-bottom:0!important}.efb.mb-md-1{margin-bottom:.25rem!important}.efb.mb-md-2{margin-bottom:.5rem!important}.efb.mb-md-3{margin-bottom:1rem!important}.efb.mb-md-4{margin-bottom:1.5rem!important}.efb.mb-md-5{margin-bottom:3rem!important}.efb.mb-md-auto{margin-bottom:auto!important}.efb.ms-md-0{margin-left:0!important}.efb.ms-md-1{margin-left:.25rem!important}.efb.ms-md-2{margin-left:.5rem!important}.efb.ms-md-3{margin-left:1rem!important}.efb.ms-md-4{margin-left:1.5rem!important}.efb.ms-md-5{margin-left:3rem!important}.efb.ms-md-auto{margin-left:auto!important}.efb.p-md-0{padding:0!important}.efb.p-md-1{padding:.25rem!important}.efb.p-md-2{padding:.5rem!important}.efb.p-md-3{padding:1rem!important}.efb.p-md-4{padding:1.5rem!important}.efb.p-md-5{padding:3rem!important}.efb.px-md-0{padding-right:0!important;padding-left:0!important}.efb.px-md-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-md-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-md-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-md-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-md-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-md-0{padding-top:0!important;padding-bottom:0!important}.efb.py-md-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-md-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-md-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-md-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-md-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-md-0{padding-top:0!important}.efb.pt-md-1{padding-top:.25rem!important}.efb.pt-md-2{padding-top:.5rem!important}.efb.pt-md-3{padding-top:1rem!important}.efb.pt-md-4{padding-top:1.5rem!important}.efb.pt-md-5{padding-top:3rem!important}.efb.pe-md-0{padding-right:0!important}.efb.pe-md-1{padding-right:.25rem!important}.efb.pe-md-2{padding-right:.5rem!important}.efb.pe-md-3{padding-right:1rem!important}.efb.pe-md-4{padding-right:1.5rem!important}.efb.pe-md-5{padding-right:3rem!important}.efb.pb-md-0{padding-bottom:0!important}.efb.pb-md-1{padding-bottom:.25rem!important}.efb.pb-md-2{padding-bottom:.5rem!important}.efb.pb-md-3{padding-bottom:1rem!important}.efb.pb-md-4{padding-bottom:1.5rem!important}.efb.pb-md-5{padding-bottom:3rem!important}.efb.ps-md-0{padding-left:0!important}.efb.ps-md-1{padding-left:.25rem!important}.efb.ps-md-2{padding-left:.5rem!important}.efb.ps-md-3{padding-left:1rem!important}.efb.ps-md-4{padding-left:1.5rem!important}.efb.ps-md-5{padding-left:3rem!important}.efb.text-md-start{text-align:left!important}.efb.text-md-end{text-align:right!important}.efb.text-md-center{text-align:center!important}}@media (min-width:992px){.efb.float-lg-start{float:left!important}.efb.float-lg-end{float:right!important}.efb.float-lg-none{float:none!important}.efb.d-lg-inline{display:inline!important}.efb.d-lg-inline-block{display:inline-block!important}.efb.d-lg-block{display:block!important}.efb.d-lg-grid{display:grid!important}.efb.d-lg-table{display:table!important}.efb.d-lg-table-row{display:table-row!important}.efb.d-lg-table-cell{display:table-cell!important}.efb.d-lg-flex{display:flex!important}.efb.d-lg-inline-flex{display:inline-flex!important}.efb.d-lg-none{display:none!important}.efb.flex-lg-fill{flex:1 1 auto!important}.efb.flex-lg-row{flex-direction:row!important}.efb.flex-lg-column{flex-direction:column!important}.efb.flex-lg-row-reverse{flex-direction:row-reverse!important}.efb.flex-lg-column-reverse{flex-direction:column-reverse!important}.efb.flex-lg-grow-0{flex-grow:0!important}.efb.flex-lg-grow-1{flex-grow:1!important}.efb.flex-lg-shrink-0{flex-shrink:0!important}.efb.flex-lg-shrink-1{flex-shrink:1!important}.efb.flex-lg-wrap{flex-wrap:wrap!important}.efb.flex-lg-nowrap{flex-wrap:nowrap!important}.efb.flex-lg-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-lg-0{gap:0!important}.efb.gap-lg-1{gap:.25rem!important}.efb.gap-lg-2{gap:.5rem!important}.efb.gap-lg-3{gap:1rem!important}.efb.gap-lg-4{gap:1.5rem!important}.efb.gap-lg-5{gap:3rem!important}.efb.justify-content-lg-start{justify-content:flex-start!important}.efb.justify-content-lg-end{justify-content:flex-end!important}.efb.justify-content-lg-center{justify-content:center!important}.efb.justify-content-lg-between{justify-content:space-between!important}.efb.justify-content-lg-around{justify-content:space-around!important}.efb.justify-content-lg-evenly{justify-content:space-evenly!important}.efb.align-items-lg-start{align-items:flex-start!important}.efb.align-items-lg-end{align-items:flex-end!important}.efb.align-items-lg-center{align-items:center!important}.efb.align-items-lg-baseline{align-items:baseline!important}.efb.align-items-lg-stretch{align-items:stretch!important}.efb.align-content-lg-start{align-content:flex-start!important}.efb.align-content-lg-end{align-content:flex-end!important}.efb.align-content-lg-center{align-content:center!important}.efb.align-content-lg-between{align-content:space-between!important}.efb.align-content-lg-around{align-content:space-around!important}.efb.align-content-lg-stretch{align-content:stretch!important}.efb.align-self-lg-auto{align-self:auto!important}.efb.align-self-lg-start{align-self:flex-start!important}.efb.align-self-lg-end{align-self:flex-end!important}.efb.align-self-lg-center{align-self:center!important}.efb.align-self-lg-baseline{align-self:baseline!important}.efb.align-self-lg-stretch{align-self:stretch!important}.efb.order-lg-first{order:-1!important}.efb.order-lg-0{order:0!important}.efb.order-lg-1{order:1!important}.efb.order-lg-2{order:2!important}.efb.order-lg-3{order:3!important}.efb.order-lg-4{order:4!important}.efb.order-lg-5{order:5!important}.efb.order-lg-last{order:6!important}.efb.m-lg-0{margin:0!important}.efb.m-lg-1{margin:.25rem!important}.efb.m-lg-2{margin:.5rem!important}.efb.m-lg-3{margin:1rem!important}.efb.m-lg-4{margin:1.5rem!important}.efb.m-lg-5{margin:3rem!important}.efb.m-lg-auto{margin:auto!important}.efb.mx-lg-0{margin-right:0!important;margin-left:0!important}.efb.mx-lg-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-lg-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-lg-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-lg-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-lg-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-lg-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-lg-0{margin-top:0!important;margin-bottom:0!important}.efb.my-lg-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-lg-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-lg-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-lg-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-lg-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-lg-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-lg-0{margin-top:0!important}.efb.mt-lg-1{margin-top:.25rem!important}.efb.mt-lg-2{margin-top:.5rem!important}.efb.mt-lg-3{margin-top:1rem!important}.efb.mt-lg-4{margin-top:1.5rem!important}.efb.mt-lg-5{margin-top:3rem!important}.efb.mt-lg-auto{margin-top:auto!important}.efb.me-lg-0{margin-right:0!important}.efb.me-lg-1{margin-right:.25rem!important}.efb.me-lg-2{margin-right:.5rem!important}.efb.me-lg-3{margin-right:1rem!important}.efb.me-lg-4{margin-right:1.5rem!important}.efb.me-lg-5{margin-right:3rem!important}.efb.me-lg-auto{margin-right:auto!important}.efb.mb-lg-0{margin-bottom:0!important}.efb.mb-lg-1{margin-bottom:.25rem!important}.efb.mb-lg-2{margin-bottom:.5rem!important}.efb.mb-lg-3{margin-bottom:1rem!important}.efb.mb-lg-4{margin-bottom:1.5rem!important}.efb.mb-lg-5{margin-bottom:3rem!important}.efb.mb-lg-auto{margin-bottom:auto!important}.efb.ms-lg-0{margin-left:0!important}.efb.ms-lg-1{margin-left:.25rem!important}.efb.ms-lg-2{margin-left:.5rem!important}.efb.ms-lg-3{margin-left:1rem!important}.efb.ms-lg-4{margin-left:1.5rem!important}.efb.ms-lg-5{margin-left:3rem!important}.efb.ms-lg-auto{margin-left:auto!important}.efb.p-lg-0{padding:0!important}.efb.p-lg-1{padding:.25rem!important}.efb.p-lg-2{padding:.5rem!important}.efb.p-lg-3{padding:1rem!important}.efb.p-lg-4{padding:1.5rem!important}.efb.p-lg-5{padding:3rem!important}.efb.px-lg-0{padding-right:0!important;padding-left:0!important}.efb.px-lg-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-lg-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-lg-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-lg-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-lg-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-lg-0{padding-top:0!important;padding-bottom:0!important}.efb.py-lg-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-lg-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-lg-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-lg-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-lg-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-lg-0{padding-top:0!important}.efb.pt-lg-1{padding-top:.25rem!important}.efb.pt-lg-2{padding-top:.5rem!important}.efb.pt-lg-3{padding-top:1rem!important}.efb.pt-lg-4{padding-top:1.5rem!important}.efb.pt-lg-5{padding-top:3rem!important}.efb.pe-lg-0{padding-right:0!important}.efb.pe-lg-1{padding-right:.25rem!important}.efb.pe-lg-2{padding-right:.5rem!important}.efb.pe-lg-3{padding-right:1rem!important}.efb.pe-lg-4{padding-right:1.5rem!important}.efb.pe-lg-5{padding-right:3rem!important}.efb.pb-lg-0{padding-bottom:0!important}.efb.pb-lg-1{padding-bottom:.25rem!important}.efb.pb-lg-2{padding-bottom:.5rem!important}.efb.pb-lg-3{padding-bottom:1rem!important}.efb.pb-lg-4{padding-bottom:1.5rem!important}.efb.pb-lg-5{padding-bottom:3rem!important}.efb.ps-lg-0{padding-left:0!important}.efb.ps-lg-1{padding-left:.25rem!important}.efb.ps-lg-2{padding-left:.5rem!important}.efb.ps-lg-3{padding-left:1rem!important}.efb.ps-lg-4{padding-left:1.5rem!important}.efb.ps-lg-5{padding-left:3rem!important}.efb.text-lg-start{text-align:left!important}.efb.text-lg-end{text-align:right!important}.efb.text-lg-center{text-align:center!important}}@media (min-width:1200px){.efb.float-xl-start{float:left!important}.efb.float-xl-end{float:right!important}.efb.float-xl-none{float:none!important}.efb.d-xl-inline{display:inline!important}.efb.d-xl-inline-block{display:inline-block!important}.efb.d-xl-block{display:block!important}.efb.d-xl-grid{display:grid!important}.efb.d-xl-table{display:table!important}.efb.d-xl-table-row{display:table-row!important}.efb.d-xl-table-cell{display:table-cell!important}.efb.d-xl-flex{display:flex!important}.efb.d-xl-inline-flex{display:inline-flex!important}.efb.d-xl-none{display:none!important}.efb.flex-xl-fill{flex:1 1 auto!important}.efb.flex-xl-row{flex-direction:row!important}.efb.flex-xl-column{flex-direction:column!important}.efb.flex-xl-row-reverse{flex-direction:row-reverse!important}.efb.flex-xl-column-reverse{flex-direction:column-reverse!important}.efb.flex-xl-grow-0{flex-grow:0!important}.efb.flex-xl-grow-1{flex-grow:1!important}.efb.flex-xl-shrink-0{flex-shrink:0!important}.efb.flex-xl-shrink-1{flex-shrink:1!important}.efb.flex-xl-wrap{flex-wrap:wrap!important}.efb.flex-xl-nowrap{flex-wrap:nowrap!important}.efb.flex-xl-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-xl-0{gap:0!important}.efb.gap-xl-1{gap:.25rem!important}.efb.gap-xl-2{gap:.5rem!important}.efb.gap-xl-3{gap:1rem!important}.efb.gap-xl-4{gap:1.5rem!important}.efb.gap-xl-5{gap:3rem!important}.efb.justify-content-xl-start{justify-content:flex-start!important}.efb.justify-content-xl-end{justify-content:flex-end!important}.efb.justify-content-xl-center{justify-content:center!important}.efb.justify-content-xl-between{justify-content:space-between!important}.efb.justify-content-xl-around{justify-content:space-around!important}.efb.justify-content-xl-evenly{justify-content:space-evenly!important}.efb.align-items-xl-start{align-items:flex-start!important}.efb.align-items-xl-end{align-items:flex-end!important}.efb.align-items-xl-center{align-items:center!important}.efb.align-items-xl-baseline{align-items:baseline!important}.efb.align-items-xl-stretch{align-items:stretch!important}.efb.align-content-xl-start{align-content:flex-start!important}.efb.align-content-xl-end{align-content:flex-end!important}.efb.align-content-xl-center{align-content:center!important}.efb.align-content-xl-between{align-content:space-between!important}.efb.align-content-xl-around{align-content:space-around!important}.efb.align-content-xl-stretch{align-content:stretch!important}.efb.align-self-xl-auto{align-self:auto!important}.efb.align-self-xl-start{align-self:flex-start!important}.efb.align-self-xl-end{align-self:flex-end!important}.efb.align-self-xl-center{align-self:center!important}.efb.align-self-xl-baseline{align-self:baseline!important}.efb.align-self-xl-stretch{align-self:stretch!important}.efb.order-xl-first{order:-1!important}.efb.order-xl-0{order:0!important}.efb.order-xl-1{order:1!important}.efb.order-xl-2{order:2!important}.efb.order-xl-3{order:3!important}.efb.order-xl-4{order:4!important}.efb.order-xl-5{order:5!important}.efb.order-xl-last{order:6!important}.efb.m-xl-0{margin:0!important}.efb.m-xl-1{margin:.25rem!important}.efb.m-xl-2{margin:.5rem!important}.efb.m-xl-3{margin:1rem!important}.efb.m-xl-4{margin:1.5rem!important}.efb.m-xl-5{margin:3rem!important}.efb.m-xl-auto{margin:auto!important}.efb.mx-xl-0{margin-right:0!important;margin-left:0!important}.efb.mx-xl-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-xl-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-xl-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-xl-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-xl-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-xl-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-xl-0{margin-top:0!important;margin-bottom:0!important}.efb.my-xl-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-xl-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-xl-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-xl-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-xl-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-xl-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-xl-0{margin-top:0!important}.efb.mt-xl-1{margin-top:.25rem!important}.efb.mt-xl-2{margin-top:.5rem!important}.efb.mt-xl-3{margin-top:1rem!important}.efb.mt-xl-4{margin-top:1.5rem!important}.efb.mt-xl-5{margin-top:3rem!important}.efb.mt-xl-auto{margin-top:auto!important}.efb.me-xl-0{margin-right:0!important}.efb.me-xl-1{margin-right:.25rem!important}.efb.me-xl-2{margin-right:.5rem!important}.efb.me-xl-3{margin-right:1rem!important}.efb.me-xl-4{margin-right:1.5rem!important}.efb.me-xl-5{margin-right:3rem!important}.efb.me-xl-auto{margin-right:auto!important}.efb.mb-xl-0{margin-bottom:0!important}.efb.mb-xl-1{margin-bottom:.25rem!important}.efb.mb-xl-2{margin-bottom:.5rem!important}.efb.mb-xl-3{margin-bottom:1rem!important}.efb.mb-xl-4{margin-bottom:1.5rem!important}.efb.mb-xl-5{margin-bottom:3rem!important}.efb.mb-xl-auto{margin-bottom:auto!important}.efb.ms-xl-0{margin-left:0!important}.efb.ms-xl-1{margin-left:.25rem!important}.efb.ms-xl-2{margin-left:.5rem!important}.efb.ms-xl-3{margin-left:1rem!important}.efb.ms-xl-4{margin-left:1.5rem!important}.efb.ms-xl-5{margin-left:3rem!important}.efb.ms-xl-auto{margin-left:auto!important}.efb.p-xl-0{padding:0!important}.efb.p-xl-1{padding:.25rem!important}.efb.p-xl-2{padding:.5rem!important}.efb.p-xl-3{padding:1rem!important}.efb.p-xl-4{padding:1.5rem!important}.efb.p-xl-5{padding:3rem!important}.efb.px-xl-0{padding-right:0!important;padding-left:0!important}.efb.px-xl-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-xl-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-xl-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-xl-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-xl-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-xl-0{padding-top:0!important;padding-bottom:0!important}.efb.py-xl-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-xl-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-xl-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-xl-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-xl-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-xl-0{padding-top:0!important}.efb.pt-xl-1{padding-top:.25rem!important}.efb.pt-xl-2{padding-top:.5rem!important}.efb.pt-xl-3{padding-top:1rem!important}.efb.pt-xl-4{padding-top:1.5rem!important}.efb.pt-xl-5{padding-top:3rem!important}.efb.pe-xl-0{padding-right:0!important}.efb.pe-xl-1{padding-right:.25rem!important}.efb.pe-xl-2{padding-right:.5rem!important}.efb.pe-xl-3{padding-right:1rem!important}.efb.pe-xl-4{padding-right:1.5rem!important}.efb.pe-xl-5{padding-right:3rem!important}.efb.pb-xl-0{padding-bottom:0!important}.efb.pb-xl-1{padding-bottom:.25rem!important}.efb.pb-xl-2{padding-bottom:.5rem!important}.efb.pb-xl-3{padding-bottom:1rem!important}.efb.pb-xl-4{padding-bottom:1.5rem!important}.efb.pb-xl-5{padding-bottom:3rem!important}.efb.ps-xl-0{padding-left:0!important}.efb.ps-xl-1{padding-left:.25rem!important}.efb.ps-xl-2{padding-left:.5rem!important}.efb.ps-xl-3{padding-left:1rem!important}.efb.ps-xl-4{padding-left:1.5rem!important}.efb.ps-xl-5{padding-left:3rem!important}.efb.text-xl-start{text-align:left!important}.efb.text-xl-end{text-align:right!important}.efb.text-xl-center{text-align:center!important}}@media (min-width:1400px){.efb.float-xxl-start{float:left!important}.efb.float-xxl-end{float:right!important}.efb.float-xxl-none{float:none!important}.efb.d-xxl-inline{display:inline!important}.efb.d-xxl-inline-block{display:inline-block!important}.efb.d-xxl-block{display:block!important}.efb.d-xxl-grid{display:grid!important}.efb.d-xxl-table{display:table!important}.efb.d-xxl-table-row{display:table-row!important}.efb.d-xxl-table-cell{display:table-cell!important}.efb.d-xxl-flex{display:flex!important}.efb.d-xxl-inline-flex{display:inline-flex!important}.efb.d-xxl-none{display:none!important}.efb.flex-xxl-fill{flex:1 1 auto!important}.efb.flex-xxl-row{flex-direction:row!important}.efb.flex-xxl-column{flex-direction:column!important}.efb.flex-xxl-row-reverse{flex-direction:row-reverse!important}.efb.flex-xxl-column-reverse{flex-direction:column-reverse!important}.efb.flex-xxl-grow-0{flex-grow:0!important}.efb.flex-xxl-grow-1{flex-grow:1!important}.efb.flex-xxl-shrink-0{flex-shrink:0!important}.efb.flex-xxl-shrink-1{flex-shrink:1!important}.efb.flex-xxl-wrap{flex-wrap:wrap!important}.efb.flex-xxl-nowrap{flex-wrap:nowrap!important}.efb.flex-xxl-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.gap-xxl-0{gap:0!important}.efb.gap-xxl-1{gap:.25rem!important}.efb.gap-xxl-2{gap:.5rem!important}.efb.gap-xxl-3{gap:1rem!important}.efb.gap-xxl-4{gap:1.5rem!important}.efb.gap-xxl-5{gap:3rem!important}.efb.justify-content-xxl-start{justify-content:flex-start!important}.efb.justify-content-xxl-end{justify-content:flex-end!important}.efb.justify-content-xxl-center{justify-content:center!important}.efb.justify-content-xxl-between{justify-content:space-between!important}.efb.justify-content-xxl-around{justify-content:space-around!important}.efb.justify-content-xxl-evenly{justify-content:space-evenly!important}.efb.align-items-xxl-start{align-items:flex-start!important}.efb.align-items-xxl-end{align-items:flex-end!important}.efb.align-items-xxl-center{align-items:center!important}.efb.align-items-xxl-baseline{align-items:baseline!important}.efb.align-items-xxl-stretch{align-items:stretch!important}.efb.align-content-xxl-start{align-content:flex-start!important}.efb.align-content-xxl-end{align-content:flex-end!important}.efb.align-content-xxl-center{align-content:center!important}.efb.align-content-xxl-between{align-content:space-between!important}.efb.align-content-xxl-around{align-content:space-around!important}.efb.align-content-xxl-stretch{align-content:stretch!important}.efb.align-self-xxl-auto{align-self:auto!important}.efb.align-self-xxl-start{align-self:flex-start!important}.efb.align-self-xxl-end{align-self:flex-end!important}.efb.align-self-xxl-center{align-self:center!important}.efb.align-self-xxl-baseline{align-self:baseline!important}.efb.align-self-xxl-stretch{align-self:stretch!important}.efb.order-xxl-first{order:-1!important}.efb.order-xxl-0{order:0!important}.efb.order-xxl-1{order:1!important}.efb.order-xxl-2{order:2!important}.efb.order-xxl-3{order:3!important}.efb.order-xxl-4{order:4!important}.efb.order-xxl-5{order:5!important}.efb.order-xxl-last{order:6!important}.efb.m-xxl-0{margin:0!important}.efb.m-xxl-1{margin:.25rem!important}.efb.m-xxl-2{margin:.5rem!important}.efb.m-xxl-3{margin:1rem!important}.efb.m-xxl-4{margin:1.5rem!important}.efb.m-xxl-5{margin:3rem!important}.efb.m-xxl-auto{margin:auto!important}.efb.mx-xxl-0{margin-right:0!important;margin-left:0!important}.efb.mx-xxl-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-xxl-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-xxl-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-xxl-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-xxl-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-xxl-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-xxl-0{margin-top:0!important;margin-bottom:0!important}.efb.my-xxl-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-xxl-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-xxl-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-xxl-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-xxl-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-xxl-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-xxl-0{margin-top:0!important}.efb.mt-xxl-1{margin-top:.25rem!important}.efb.mt-xxl-2{margin-top:.5rem!important}.efb.mt-xxl-3{margin-top:1rem!important}.efb.mt-xxl-4{margin-top:1.5rem!important}.efb.mt-xxl-5{margin-top:3rem!important}.efb.mt-xxl-auto{margin-top:auto!important}.efb.me-xxl-0{margin-right:0!important}.efb.me-xxl-1{margin-right:.25rem!important}.efb.me-xxl-2{margin-right:.5rem!important}.efb.me-xxl-3{margin-right:1rem!important}.efb.me-xxl-4{margin-right:1.5rem!important}.efb.me-xxl-5{margin-right:3rem!important}.efb.me-xxl-auto{margin-right:auto!important}.efb.mb-xxl-0{margin-bottom:0!important}.efb.mb-xxl-1{margin-bottom:.25rem!important}.efb.mb-xxl-2{margin-bottom:.5rem!important}.efb.mb-xxl-3{margin-bottom:1rem!important}.efb.mb-xxl-4{margin-bottom:1.5rem!important}.efb.mb-xxl-5{margin-bottom:3rem!important}.efb.mb-xxl-auto{margin-bottom:auto!important}.efb.ms-xxl-0{margin-left:0!important}.efb.ms-xxl-1{margin-left:.25rem!important}.efb.ms-xxl-2{margin-left:.5rem!important}.efb.ms-xxl-3{margin-left:1rem!important}.efb.ms-xxl-4{margin-left:1.5rem!important}.efb.ms-xxl-5{margin-left:3rem!important}.efb.ms-xxl-auto{margin-left:auto!important}.efb.p-xxl-0{padding:0!important}.efb.p-xxl-1{padding:.25rem!important}.efb.p-xxl-2{padding:.5rem!important}.efb.p-xxl-3{padding:1rem!important}.efb.p-xxl-4{padding:1.5rem!important}.efb.p-xxl-5{padding:3rem!important}.efb.px-xxl-0{padding-right:0!important;padding-left:0!important}.efb.px-xxl-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-xxl-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-xxl-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-xxl-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-xxl-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-xxl-0{padding-top:0!important;padding-bottom:0!important}.efb.py-xxl-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-xxl-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-xxl-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-xxl-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-xxl-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-xxl-0{padding-top:0!important}.efb.pt-xxl-1{padding-top:.25rem!important}.efb.pt-xxl-2{padding-top:.5rem!important}.efb.pt-xxl-3{padding-top:1rem!important}.efb.pt-xxl-4{padding-top:1.5rem!important}.efb.pt-xxl-5{padding-top:3rem!important}.efb.pe-xxl-0{padding-right:0!important}.efb.pe-xxl-1{padding-right:.25rem!important}.efb.pe-xxl-2{padding-right:.5rem!important}.efb.pe-xxl-3{padding-right:1rem!important}.efb.pe-xxl-4{padding-right:1.5rem!important}.efb.pe-xxl-5{padding-right:3rem!important}.efb.pb-xxl-0{padding-bottom:0!important}.efb.pb-xxl-1{padding-bottom:.25rem!important}.efb.pb-xxl-2{padding-bottom:.5rem!important}.efb.pb-xxl-3{padding-bottom:1rem!important}.efb.pb-xxl-4{padding-bottom:1.5rem!important}.efb.pb-xxl-5{padding-bottom:3rem!important}.efb.ps-xxl-0{padding-left:0!important}.efb.ps-xxl-1{padding-left:.25rem!important}.efb.ps-xxl-2{padding-left:.5rem!important}.efb.ps-xxl-3{padding-left:1rem!important}.efb.ps-xxl-4{padding-left:1.5rem!important}.efb.ps-xxl-5{padding-left:3rem!important}.efb.text-xxl-start{text-align:left!important}.efb.text-xxl-end{text-align:right!important}.efb.text-xxl-center{text-align:center!important}}@media (min-width:1200px){}@media print{.efb.d-print-inline{display:inline!important}.efb.d-print-inline-block{display:inline-block!important}.efb.d-print-block{display:block!important}.efb.d-print-grid{display:grid!important}.efb.d-print-table{display:table!important}.efb.d-print-table-row{display:table-row!important}.efb.d-print-table-cell{display:table-cell!important}.efb.d-print-flex{display:flex!important}.efb.d-print-inline-flex{display:inline-flex!important}.efb.d-print-none{display:none!important}}label input[type='radio'].efb{visibility:hidden}
		</style>
		";
	}
	public function bootstrap_style_efb_($w){
				return  '
				<!-- styleEfB bootstrap -->
			<style>
			@charset "UTF-8";:root{--bs-blue:#0d6efd;--bs-indigo:#6610f2;--bs-purple:#6f42c1;--bs-pink:#d63384;--bs-red:#dc3545;--bs-orange:#fd7e14;--bs-yellow:#ffc107;--bs-green:#198754;--bs-teal:#20c997;--bs-cyan:#0dcaf0;--bs-white:#fff;--bs-gray:#6c757d;--bs-gray-dark:#343a40;--bs-primary:#0d6efd;--bs-secondary:#6c757d;--bs-success:#198754;--bs-info:#0dcaf0;--bs-warning:#ffc107;--bs-danger:#dc3545;--bs-light:#f8f9fa;--bs-dark:#212529;--bs-font-sans-serif:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans","Liberation Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";--bs-font-monospace:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;--bs-gradient:linear-gradient(180deg,rgba(255,255,255,.15),rgba(255,255,255,0))}.efb,.efb::after,.efb::before{box-sizing:border-box}@media (prefers-reduced-motion:no-preference){:root.efb{scroll-behavior:smooth}}hr .efb{margin:1rem 0;color:inherit;background-color:currentColor;border:0;opacity:.25}hr .efb:not([size]){height:1px}.efb.h1,.efb.h2,.efb.h3,.efb.h4,.efb.h5,.efb.h6,h1.efb,h2.efb,h3.efb,h4.efb,h5.efb,h6.efb{margin-top:0;margin-bottom:.5rem;font-weight:500;line-height:1.2}.efb.h1,h1.efb{font-size:calc(1.375rem + 1.5vw)}@media (min-width:1200px){.efb.h1,h1.efb{font-size:2.5rem}}.efb.h2,h2.efb{font-size:calc(1.325rem + .9vw)}@media (min-width:1200px){.h2.efb,h2.efb{font-size:2rem}}.efb.h3,h3.efb{font-size:calc(1.3rem + .6vw)}@media (min-width:1200px){.efb.h3,h3.efb{font-size:1.75rem}}.efb.h4,h4.efb{font-size:calc(1.275rem + .3vw)}@media (min-width:1200px){.h4.efb,h4.efb{font-size:1.5rem}}.efb.h5,h5.efb{font-size:1.25rem}.efb.h6,h6.efb{font-size:1rem}p.efb{margin-top:0;margin-bottom:1rem}abbr.efb[data-bs-original-title],abbr[title].efb{-webkit-text-decoration:underline dotted;text-decoration:underline dotted;cursor:help;-webkit-text-decoration-skip-ink:none;text-decoration-skip-ink:none}address.efb{margin-bottom:1rem;font-style:normal;line-height:inherit}ol.efb,ul.efb{padding-left:2rem}dl.efb,ol.efb,ul.efb{margin-top:0;margin-bottom:1rem}ol.efb ol.efb,ol.efb ul.efb,ul.efb ol.efb,ul.efb ul.efb{margin-bottom:0}dt.efb{font-weight:700}dd.efb{margin-bottom:.5rem;margin-left:0}blockquote.efb{margin:0 0 1rem}b.efb,strong.efb{font-weight:bolder}.efb.small,small.efb{font-size:.875em}.efb.mark,mark.efb{padding:.2em;background-color:#fcf8e3}sub.efb,sup.efb{position:relative;font-size:.75em;line-height:0;vertical-align:baseline}sub.efb{bottom:-.25em}sup.efb{top:-.5em}a.efb{color:#0d6efd;text-decoration:underline}a.efb:hover{color:#0a58ca}a.efb:not([href]):not([class]),a.efb:not([href]):not([class]):hover{color:inherit;text-decoration:none}code.efb,kbd.efb,pre.efb,samp.efb{font-family:var(--bs-font-monospace);font-size:1em;direction:ltr;unicode-bidi:bidi-override}pre.efb{display:block;margin-top:0;margin-bottom:1rem;overflow:auto;font-size:.875em}pre code.efb{font-size:inherit;color:inherit;word-break:normal}code.efb{font-size:.875em;color:#d63384;word-wrap:break-word}a.efb>code{color:inherit}kbd.efb{padding:.2rem .4rem;font-size:.875em;color:#fff;background-color:#212529;border-radius:.2rem}kbd.efb kbd{padding:0;font-size:1em;font-weight:700}figure.efb{margin:0 0 1rem}img.efb,svg.efb{vertical-align:middle}table.efb{caption-side:bottom;border-collapse:collapse}caption.efb{padding-top:.5rem;padding-bottom:.5rem;color:#6c757d;text-align:left}th.efb{text-align:inherit;text-align:-webkit-match-parent}tbody.efb,td.efb,tfoot.efb,th.efb,thead.efb,tr.efb{border-color:inherit;border-style:solid;border-width:0}label.efb{display:inline-block}button.efb{border-radius:0}button.efb:focus:not(:focus-visible){outline:0}button.efb,input.efb,optgroup.efb,select.efb,textarea.efb{margin:0;font-family:inherit;font-size:inherit;line-height:inherit;color:#a5a3d1}textarea.efb:focus{box-shadow:0 2px 10px rgba(84,131,207,.25)!important;color:#a5a3d1}button.efb,select.efb{text-transform:none}[role=button]{cursor:pointer}select.efb{word-wrap:normal}select.efb:disabled{opacity:1}[list].efb::-webkit-calendar-picker-indicator{display:none}[type=button],[type=reset],[type=submit],button.efb{-webkit-appearance:button}[type=button]:not(:disabled) .efb,[type=reset]:not(:disabled) .efb,[type=submit]:not(:disabled) .efb,button:not(:disabled) .efb{cursor:pointer}.efb::-moz-focus-inner{padding:0;border-style:none}textarea.efb{resize:vertical}fieldset.efb{min-width:0;padding:0;margin:0;border:0}legend.efb{float:left;width:100%;padding:0;margin-bottom:.5rem;font-size:calc(1.275rem + .3vw);line-height:inherit}@media (min-width:1200px){legend.efb{font-size:1.5rem}}legend.efb+*{clear:left}.efb::-webkit-datetime-edit-day-field,.efb::-webkit-datetime-edit-fields-wrapper,.efb::-webkit-datetime-edit-hour-field,.efb::-webkit-datetime-edit-minute,.efb::-webkit-datetime-edit-month-field,.efb::-webkit-datetime-edit-text,.efb::-webkit-datetime-edit-year-field{padding:0}.efb::-webkit-inner-spin-button{height:auto}[type=search] .efb{outline-offset:-2px;-webkit-appearance:textfield}.efb::-webkit-search-decoration{-webkit-appearance:none}.efb::-webkit-color-swatch-wrapper{padding:0}.efb::file-selector-button{font:inherit}.efb::-webkit-file-upload-button{font:inherit;-webkit-appearance:button}output.efb{display:inline-block}iframe.efb{border:0}summary.efb{display:list-item;cursor:pointer}progress.efb{vertical-align:baseline}[hidden]{display:none!important}.efb.lead{font-size:1.25rem;font-weight:300}.efb.display-1{font-size:calc(1.625rem + 4.5vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-1{font-size:5rem}}.efb.display-2{font-size:calc(1.575rem + 3.9vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-2{font-size:4.5rem}}.efb.display-3{font-size:calc(1.525rem + 3.3vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-3{font-size:4rem}}.efb.display-4{font-size:calc(1.475rem + 2.7vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-4{font-size:3.5rem}}.efb.display-5{font-size:calc(1.425rem + 2.1vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-5{font-size:3rem}}.efb.display-6{font-size:calc(1.375rem + 1.5vw);font-weight:300;line-height:1.2}@media (min-width:1200px){.efb.display-6{font-size:2.5rem}}.efb.list-unstyled{padding-left:0;list-style:none}.efb.list-inline{padding-left:0;list-style:none}.efb.list-inline-item{display:inline-block}.efb.list-inline-item:not(:last-child){margin-right:.5rem}.efb.initialism{font-size:.875em;text-transform:uppercase}.efb.blockquote{margin-bottom:1rem;font-size:1.25rem}.efb.blockquote>:last-child{margin-bottom:0}.efb.blockquote-footer{margin-top:-1rem;margin-bottom:1rem;font-size:.875em;color:#6c757d}.efb.blockquote-footer::before{content:"— "}.efb.img-fluid{max-width:100%;height:auto}.efb.img-thumbnail{padding:.25rem;background-color:#fff;border:1px solid #dee2e6;border-radius:.25rem;max-width:100%;height:auto}.efb.figure{display:inline-block}.efb.figure-img{margin-bottom:.5rem;line-height:1}.efb.figure-caption{font-size:.875em;color:#6c757d}.efb.container,.efb.container-fluid,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl,.efb.container-xxl{width:100%;padding-right:var(--bs-gutter-x,.75rem);padding-left:var(--bs-gutter-x,.75rem);margin-right:auto;margin-left:auto}@media (min-width:576px){.efb.container,.efb.container-sm{max-width:540px}}@media (min-width:768px){.efb.container,.efb.container-md,.efb.container-sm{max-width:720px}}@media (min-width:992px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm{max-width:960px}}@media (min-width:1200px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl{max-width:1140px}}@media (min-width:1400px){.efb.container,.efb.container-lg,.efb.container-md,.efb.container-sm,.efb.container-xl,.efb.container-xxl{max-width:1320px}}.row.efb{--bs-gutter-x:1.5rem;--bs-gutter-y:0;display:flex;flex-wrap:wrap;margin-top:calc(var(--bs-gutter-y) * -1);margin-right:calc(var(--bs-gutter-x)/ -2);margin-left:calc(var(--bs-gutter-x)/ -2)}.efb.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)/ 2);padding-left:calc(var(--bs-gutter-x)/ 2);margin-top:var(--bs-gutter-y)}.efb.col{flex:1 0 0%}.efb.row-cols-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-1>*{flex:0 0 auto;width:100%}.efb.row-cols-2>*{flex:0 0 auto;width:50%}.efb.row-cols-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-4>*{flex:0 0 auto;width:25%}.efb.row-cols-5>*{flex:0 0 auto;width:20%}.efb.row-cols-6>*{flex:0 0 auto;width:16.6666666667%}.col-auto{flex:0 0 auto;width:auto}.efb.col-1{flex:0 0 auto;width:8.3333333333%}.efb.col-2{flex:0 0 auto;width:16.6666666667%}.efb.efb-col-3{flex:0 0 auto;width:25%}.efb.col-4{flex:0 0 auto;width:33.3333333333%}.efb.col-5{flex:0 0 auto;width:41.6666666667%}.efb.col-6{flex:0 0 auto;width:50%}.efb.col-7{flex:0 0 auto;width:58.3333333333%}.efb.col-8{flex:0 0 auto;width:66.6666666667%}.efb.col-9{flex:0 0 auto;width:75%}.efb.col-10{flex:0 0 auto;width:83.3333333333%}.efb.col-11{flex:0 0 auto;width:91.6666666667%}.efb.col-12{flex:0 0 auto;width:100%}.efb.offset-1{margin-left:8.3333333333%}.efb.offset-2{margin-left:16.6666666667%}.efb.offset-3{margin-left:25%}.efb.offset-4{margin-left:33.3333333333%}.efb.offset-5{margin-left:41.6666666667%}.efb.offset-6{margin-left:50%}.efb.offset-7{margin-left:58.3333333333%}.efb.offset-8{margin-left:66.6666666667%}.efb.offset-9{margin-left:75%}.efb.offset-10{margin-left:83.3333333333%}.efb.offset-11{margin-left:91.6666666667%}.efb.g-0,.efb.gx-0{--bs-gutter-x:0}.efb.g-0,.efb.gy-0{--bs-gutter-y:0}.efb.g-1,.efb.gx-1{--bs-gutter-x:.25rem}.efb.g-1,.efb.gy-1{--bs-gutter-y:.25rem}.efb.g-2,.efb.gx-2{--bs-gutter-x:.5rem}.efb.g-2,.efb.gy-2{--bs-gutter-y:.5rem}.efb.g-3,.efb.gx-3{--bs-gutter-x:1rem}.efb.g-3,.efb.gy-3{--bs-gutter-y:1rem}.efb.g-4,.efb.gx-4{--bs-gutter-x:1.5rem}.efb.g-4,.efb.gy-4{--bs-gutter-y:1.5rem}.efb.g-5,.efb.gx-5{--bs-gutter-x:3rem}.efb.g-5,.efb.gy-5{--bs-gutter-y:3rem}@media (min-width:576px){.efb.col-sm{flex:1 0 0%}.efb.row-cols-sm-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-sm-1>*{flex:0 0 auto;width:100%}.efb.row-cols-sm-2>*{flex:0 0 auto;width:50%}.efb.row-cols-sm-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-sm-4>*{flex:0 0 auto;width:25%}.efb.row-cols-sm-5>*{flex:0 0 auto;width:20%}.efb.row-cols-sm-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-sm-auto{flex:0 0 auto;width:auto}.efb.col-sm-1{flex:0 0 auto;width:8.3333333333%}.efb.col-sm-2{flex:0 0 auto;width:16.6666666667%}.efb.col-sm-3{flex:0 0 auto;width:25%}.efb.col-sm-4{flex:0 0 auto;width:33.3333333333%}.efb.col-sm-5{flex:0 0 auto;width:41.6666666667%}.efb.col-sm-6{flex:0 0 auto;width:50%}.efb.col-sm-7{flex:0 0 auto;width:58.3333333333%}.efb.col-sm-8{flex:0 0 auto;width:66.6666666667%}.efb.col-sm-9{flex:0 0 auto;width:75%}.efb.col-sm-10{flex:0 0 auto;width:83.3333333333%}.efb.col-sm-11{flex:0 0 auto;width:91.6666666667%}.efb.col-sm-12{flex:0 0 auto;width:100%}}@media (min-width:768px){.efb.col-md{flex:1 0 0%}.efb.row-cols-md-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-md-1>*{flex:0 0 auto;width:100%}.efb.row-cols-md-2>*{flex:0 0 auto;width:50%}.efb.row-cols-md-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-md-4>*{flex:0 0 auto;width:25%}.efb.row-cols-md-5>*{flex:0 0 auto;width:20%}.efb.row-cols-md-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-md-auto{flex:0 0 auto;width:auto}.efb.col-md-1{flex:0 0 auto;width:8.3333333333%}.efb.col-md-2{flex:0 0 auto;width:16.6666666667%}.efb.col-md-3{flex:0 0 auto;width:25%}.efb.col-md-4{flex:0 0 auto;width:33.3333333333%}.efb.col-md-5{flex:0 0 auto;width:41.6666666667%}.efb.col-md-6{flex:0 0 auto;width:50%}.efb.col-md-7{flex:0 0 auto;width:58.3333333333%}.efb.col-md-8{flex:0 0 auto;width:66.6666666667%}.efb.col-md-9{flex:0 0 auto;width:75%}.efb.col-md-10{flex:0 0 auto;width:83.3333333333%}.efb.col-md-11{flex:0 0 auto;width:91.6666666667%}.efb.col-md-12{flex:0 0 auto;width:100%}}@media (min-width:992px){.efb.col-lg{flex:1 0 0%}.efb.row-cols-lg-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-lg-1>*{flex:0 0 auto;width:100%}.efb.row-cols-lg-2>*{flex:0 0 auto;width:50%}.efb.row-cols-lg-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-lg-4>*{flex:0 0 auto;width:25%}.efb.row-cols-lg-5>*{flex:0 0 auto;width:20%}.efb.row-cols-lg-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-lg-auto{flex:0 0 auto;width:auto}.efb.col-lg-1{flex:0 0 auto;width:8.3333333333%}.efb.col-lg-2{flex:0 0 auto;width:16.6666666667%}.efb.col-lg-3{flex:0 0 auto;width:25%}.efb.col-lg-4{flex:0 0 auto;width:33.3333333333%}.efb.col-lg-5{flex:0 0 auto;width:41.6666666667%}.efb.col-lg-6{flex:0 0 auto;width:50%}.efb.col-lg-7{flex:0 0 auto;width:58.3333333333%}.efb.col-lg-8{flex:0 0 auto;width:66.6666666667%}.efb.col-lg-9{flex:0 0 auto;width:75%}.efb.col-lg-10{flex:0 0 auto;width:83.3333333333%}.efb.col-lg-11{flex:0 0 auto;width:91.6666666667%}.efb.col-lg-12{flex:0 0 auto;width:100%}}@media (min-width:1200px){.efb.col-xl{flex:1 0 0%}.efb.row-cols-xl-auto>*{flex:0 0 auto;width:auto}.efb.row-cols-xl-1>*{flex:0 0 auto;width:100%}.efb.row-cols-xl-2>*{flex:0 0 auto;width:50%}.efb.row-cols-xl-3>*{flex:0 0 auto;width:33.3333333333%}.efb.row-cols-xl-4>*{flex:0 0 auto;width:25%}.efb.row-cols-xl-5>*{flex:0 0 auto;width:20%}.efb.row-cols-xl-6>*{flex:0 0 auto;width:16.6666666667%}.efb.col-xl-auto{flex:0 0 auto;width:auto}.efb.col-xl-1{flex:0 0 auto;width:8.3333333333%}.efb.col-xl-2{flex:0 0 auto;width:16.6666666667%}.efb.col-xl-3{flex:0 0 auto;width:25%}.efb.col-xl-4{flex:0 0 auto;width:33.3333333333%}.efb.col-xl-5{flex:0 0 auto;width:41.6666666667%}.efb.col-xl-6{flex:0 0 auto;width:50%}.efb.col-xl-7{flex:0 0 auto;width:58.3333333333%}.efb.col-xl-8{flex:0 0 auto;width:66.6666666667%}.efb.col-xl-9{flex:0 0 auto;width:75%}.efb.col-xl-10{flex:0 0 auto;width:83.3333333333%}.efb.col-xl-11{flex:0 0 auto;width:91.6666666667%}.efb.col-xl-12{flex:0 0 auto;width:100%}}.efb.table{--bs-table-bg:transparent;--bs-table-accent-bg:transparent;--bs-table-striped-color:#212529;--bs-table-striped-bg:rgba(0,0,0,.05);--bs-table-active-color:#212529;--bs-table-active-bg:rgba(0,0,0,.1);--bs-table-hover-color:#212529;--bs-table-hover-bg:rgba(0,0,0,.075);width:100%;margin-bottom:1rem;color:#212529;vertical-align:top;border-color:#dee2e6;border-left:none;border-right:none;border-bottom:none}.efb.table>:not(caption)>*>*{padding:.5rem .5rem;background-color:var(--bs-table-bg);border-bottom-width:1px;box-shadow:inset 0 0 0 9999px var(--bs-table-accent-bg)}.efb.table>tbody{vertical-align:inherit}.efb.table>thead{vertical-align:bottom}.efb.table>:not(:last-child)>:last-child>*{border-bottom-color:currentColor}.efb.caption-top{caption-side:top}.efb.form-label{margin-bottom:.5rem}.efb.col-form-label{padding-top:calc(.375rem + 1px);padding-bottom:calc(.375rem + 1px);margin-bottom:0;font-size:inherit;line-height:1.5}.efb.col-form-label-lg{padding-top:calc(.5rem + 1px);padding-bottom:calc(.5rem + 1px);font-size:1.25rem}.efb.col-form-label-sm{padding-top:calc(.25rem + 1px);padding-bottom:calc(.25rem + 1px);font-size:.875rem}.efb.form-control{display:block;width:100%;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-clip:padding-box;border:1px solid #ced4da;-webkit-appearance:none;-moz-appearance:none;appearance:none;border-radius:.25rem;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control{transition:none}}.efb.form-control[type=file]{overflow:hidden}.efb.form-control[type=file]:not(:disabled):not([readonly]){cursor:pointer}.efb.form-control:focus{color:#212529;background-color:#fff;border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-control::-webkit-date-and-time-value{height:1.5em}.efb.form-control::-moz-placeholder{color:#6c757d;opacity:1}.efb.form-control::placeholder{color:#6c757d;opacity:1}.efb.form-control:disabled,.efb.form-control[readonly]{background-color:#e9ecef;opacity:1}.efb.form-control::file-selector-button{padding:.375rem .75rem;margin:-.375rem -.75rem;-webkit-margin-end:.75rem;margin-inline-end:.75rem;color:#212529;background-color:#e9ecef;pointer-events:none;border-color:inherit;border-style:solid;border-width:0;border-inline-end-width:1px;border-radius:0;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control::file-selector-button{transition:none}}.efb.form-control:hover:not(:disabled):not([readonly])::file-selector-button{background-color:#dde0e3}.efb.form-control::-webkit-file-upload-button{padding:.375rem .75rem;margin:-.375rem -.75rem;-webkit-margin-end:.75rem;margin-inline-end:.75rem;color:#212529;background-color:#e9ecef;pointer-events:none;border-color:inherit;border-style:solid;border-width:0;border-inline-end-width:1px;border-radius:0;-webkit-transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-control::-webkit-file-upload-button{-webkit-transition:none;transition:none}}.efb.form-control:hover:not(:disabled):not([readonly])::-webkit-file-upload-button{background-color:#dde0e3}.efb.form-control-plaintext{display:block;width:100%;padding:.375rem 0;margin-bottom:0;line-height:1.5;color:#212529;background-color:transparent;border:solid transparent;border-width:1px 0}.efb.form-control-plaintext.efb.form-control-lg,.efb.form-control-plaintext.efb.form-control-sm{padding-right:0;padding-left:0}.efb.form-control-sm{min-height:calc(1.5em + .5rem + 2px);padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.efb.form-control-sm::file-selector-button{padding:.25rem .5rem;margin:-.25rem -.5rem;-webkit-margin-end:.5rem;margin-inline-end:.5rem}.efb.form-control-sm::-webkit-file-upload-button{padding:.25rem .5rem;margin:-.25rem -.5rem;-webkit-margin-end:.5rem;margin-inline-end:.5rem}.efb.form-control-lg{min-height:calc(1.5em + 1rem + 2px);padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.form-control-lg::file-selector-button{padding:.5rem 1rem;margin:-.5rem -1rem;-webkit-margin-end:1rem;margin-inline-end:1rem}.efb.form-control-lg::-webkit-file-upload-button{padding:.5rem 1rem;margin:-.5rem -1rem;-webkit-margin-end:1rem;margin-inline-end:1rem}textarea.efb.form-control{min-height:calc(1.5em + .75rem + 2px)}textarea.efb.form-control-sm{min-height:calc(1.5em + .5rem + 2px)}textarea.efb.form-control-lg{min-height:calc(1.5em + 1rem + 2px)}.efb.form-control-color{max-width:3rem;height:auto;padding:.375rem}.efb.form-control-color:not(:disabled):not([readonly]){cursor:pointer}.efb.form-control-color::-moz-color-swatch{height:1.5em;border-radius:.25rem}.efb.form-control-color::-webkit-color-swatch{height:1.5em;border-radius:.25rem}.efb.form-select{display:block;width:100%;padding:.375rem 2.25rem .375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px;border:1px solid #ced4da;border-radius:.25rem;-webkit-appearance:none;-moz-appearance:none;appearance:none}.efb.form-select:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-select[multiple],.efb.form-select[size]:not([size="1"]){padding-right:.75rem;background-image:none}.efb.form-select:disabled{background-color:#e9ecef}.efb.form-select:-moz-focusring{color:transparent;text-shadow:0 0 0 #212529}.efb.form-select-sm{padding-top:.25rem;padding-bottom:.25rem;padding-left:.5rem;font-size:.875rem}.efb.form-select-lg{padding-top:.5rem;padding-bottom:.5rem;padding-left:1rem;font-size:1.25rem}.efb.form-check{display:flex;min-height:1.5rem;margin-bottom:.125rem;align-items:center;}.efb.form-check .efb.form-check-input{float:left}.efb.form-check-input{width:1em;height:1em;margin-top:.25em;vertical-align:top;background-color:#fff;background-repeat:no-repeat;background-position:center;background-size:contain;border:1px solid rgba(0,0,0,.25);-webkit-appearance:none;-moz-appearance:none;appearance:none;-webkit-print-color-adjust:exact;color-adjust:exact}.efb.form-check-input[type=checkbox]{border-radius:.25em}.efb.form-check-input[type=radio]{border-radius:50%}.efb.form-check-input:active{filter:brightness(90%)}.efb.form-check-input:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-check-input:checked{background-color:#0d6efd;border-color:#0d6efd}.efb.form-check-input:checked[type=checkbox]{background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 20 20\'%3e%3cpath fill=\'none\' stroke=\'%23fff\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'3\' d=\'M6 10l3 3l6-6\'/%3e%3c/svg%3e")}.efb.form-check-input:checked[type=radio]{background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'-4 -4 8 8\'%3e%3ccircle r=\'2\' fill=\'%23fff\'/%3e%3c/svg%3e")}.efb.form-check-input[type=checkbox]:indeterminate{background-color:#0d6efd;border-color:#0d6efd;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 20 20\'%3e%3cpath fill=\'none\' stroke=\'%23fff\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'3\' d=\'M6 10h8\'/%3e%3c/svg%3e")}.efb.form-check-input:disabled{pointer-events:none;filter:none;opacity:.5}.efb.form-check-input:disabled~.form-check-label,.efb.form-check-input[disabled]~.form-check-label{opacity:.5}.efb.form-switch{padding-left:2.5em}.efb.form-switch .efb.form-check-input{width:2em;margin-left:-2.5em;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'-4 -4 8 8\'%3e%3ccircle r=\'3\' fill=\'rgba%280,0,0,.25%29\'/%3e%3c/svg%3e");background-position:left center;border-radius:2em;transition:background-position .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.form-switch .efb.form-check-input{transition:none}}.efb.form-switch .efb.form-check-input:focus{background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'-4 -4 8 8\'%3e%3ccircle r=\'3\' fill=\'%2386b7fe\'/%3e%3c/svg%3e")}.efb.form-switch .efb.form-check-input:checked{background-position:right center;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'-4 -4 8 8\'%3e%3ccircle r=\'3\' fill=\'%23fff\'/%3e%3c/svg%3e")}.efb.btn-check{position:absolute;clip:rect(0,0,0,0);pointer-events:none}.efb.btn-check:disabled+.efb.btn,.efb.btn-check[disabled]+.efb.btn{pointer-events:none;filter:none;opacity:.65}.efb.form-range{width:100%;height:1.5rem;padding:0;background-color:transparent;-webkit-appearance:none;-moz-appearance:none;appearance:none}.efb.form-range:focus{outline:0}.efb.form-range:focus::-webkit-slider-thumb{box-shadow:0 0 0 1px #fff,0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-range:focus::-moz-range-thumb{box-shadow:0 0 0 1px #fff,0 0 0 .25rem rgba(13,110,253,.25)}.efb.form-range::-moz-focus-outer{border:0}.efb.form-range::-webkit-slider-thumb{width:1rem;height:1rem;margin-top:-.25rem;background-color:#0d6efd;border:0;border-radius:1rem;-webkit-transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;-webkit-appearance:none;appearance:none}@media (prefers-reduced-motion:reduce){.efb.form-range::-webkit-slider-thumb{-webkit-transition:none;transition:none}}.efb.form-range::-webkit-slider-thumb:active{background-color:#b6d4fe}.efb.form-range::-webkit-slider-runnable-track{width:100%;height:.5rem;color:transparent;cursor:pointer;background-color:#dee2e6;border-color:transparent;border-radius:1rem}.efb.form-range::-moz-range-thumb{width:1rem;height:1rem;background-color:#0d6efd;border:0;border-radius:1rem;-moz-transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;transition:background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;-moz-appearance:none;appearance:none}@media (prefers-reduced-motion:reduce){.efb.form-range::-moz-range-thumb{-moz-transition:none;transition:none}}.efb.form-range::-moz-range-thumb:active{background-color:#b6d4fe}.efb.form-range::-moz-range-track{width:100%;height:.5rem;color:transparent;cursor:pointer;background-color:#dee2e6;border-color:transparent;border-radius:1rem}.efb.form-range:disabled{pointer-events:none}.efb.form-range:disabled::-webkit-slider-thumb{background-color:#adb5bd}.efb.form-range:disabled::-moz-range-thumb{background-color:#adb5bd}.efb.input-group{position:relative;display:flex;flex-wrap:wrap;align-items:stretch;width:100%}.efb.input-group>.efb.form-control,.efb.input-group>.efb.form-select{position:relative;flex:1 1 auto;width:1%;min-width:0}.efb.input-group>.efb.form-control:focus,.efb.input-group>.efb.form-select:focus{z-index:3}.efb.input-group .efb.btn{position:relative;z-index:2}.efb.input-group .efb.btn:focus{z-index:3}.efb.input-group-text{display:flex;align-items:center;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;text-align:center;white-space:nowrap;background-color:#e9ecef;border:1px solid #ced4da;border-radius:.25rem}.efb.input-group-lg>.efb.btn,.efb.input-group-lg>.efb.form-control,.efb.input-group-lg>.efb.form-select,.efb.input-group-lg>.efb.input-group-text{padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.input-group-sm>.efb.btn,.efb.input-group-sm>.efb.form-control,.efb.input-group-sm>.efb.form-select,.efb.input-group-sm>.efb.input-group-text{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.efb.input-group-lg>.efb.form-select,.efb.input-group-sm>.efb.form-select{padding-right:3rem}.efb.input-group:not(.has-validation)>.efb.dropdown-toggle:nth-last-child(n+3),.efb.input-group:not(.has-validation)>:not(:last-child):not(.efb.dropdown-toggle):not(.efb.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.efb.input-group.has-validation>.efb.dropdown-toggle:nth-last-child(n+4),.efb.input-group.has-validation>:nth-last-child(n+3):not(.efb.dropdown-toggle):not(.efb.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.efb.input-group>:not(:first-child):not(.efb.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.efb.invalid-tooltip):not(.efb.invalid-feedback){margin-left:-1px;border-top-left-radius:0;border-bottom-left-radius:0}.efb.valid-feedback{display:none;width:100%;margin-top:.25rem;font-size:.875em;color:#198754}.efb.valid-tooltip{position:absolute;top:100%;z-index:5;display:none;max-width:100%;padding:.25rem .5rem;margin-top:.1rem;font-size:.875rem;color:#fff;background-color:rgba(25,135,84,.9);border-radius:.25rem}.efb.is-valid~.efb.valid-feedback,.efb.was-validated:valid~.efb.valid-feedback,.efb.was-validated:valid~{display:block}.efb.form-control.is-valid,.efb.was-validated .efb.form-control:valid{border-color:#198754;padding-right:calc(1.5em + .75rem);background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 8 8\'%3e%3cpath fill=\'%23198754\' d=\'M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z\'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right calc(.375em + .1875rem) center;background-size:calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-control.is-valid:focus,.efb.was-validated .efb.form-control:valid:focus{border-color:#198754;box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.was-validated textarea.efb.form-control:valid,textarea.efb.form-control.is-valid{padding-right:calc(1.5em + .75rem);background-position:top calc(.375em + .1875rem) right calc(.375em + .1875rem)}.efb.form-select.is-valid,.efb.was-validated .efb.form-select:valid{border-color:#198754}.efb.form-select.is-valid:not([multiple]):not([size]),.efb.form-select.is-valid:not([multiple])[size="1"],.efb.was-validated .efb.form-select:valid:not([multiple]):not([size]),.efb.was-validated .efb.form-select:valid:not([multiple])[size="1"]{padding-right:4.125rem;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e"),url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 8 8\'%3e%3cpath fill=\'%23198754\' d=\'M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z\'/%3e%3c/svg%3e");background-position:right .75rem center,center right 2.25rem;background-size:16px 12px,calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-select.is-valid:focus,.efb.was-validated .efb.form-select:valid:focus{border-color:#198754;box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.form-check-input.is-valid,.efb.was-validated .efb.form-check-input:valid{border-color:#198754}.efb.form-check-input.is-valid:checked,.efb.was-validated .efb.form-check-input:valid:checked{background-color:#198754}.efb.form-check-input.is-valid:focus,.efb.was-validated .efb.form-check-input:valid:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.25)}.efb.form-check-input.is-valid~.form-check-label,.efb.was-validated .efb.form-check-input:valid{color:#198754}.form-check-inline .efb.form-check-input~.efb.valid-feedback{margin-left:.5em}.efb.input-group .efb.form-control.is-valid,.efb.input-group .efb.form-select.is-valid,.efb.was-validated .efb.input-group .efb.form-control:valid,.efb.was-validated .efb.input-group .efb.form-select:valid{z-index:1}.efb.input-group .efb.form-control.is-valid:focus,.efb.input-group .efb.form-select.is-valid:focus,.efb.was-validated .efb.input-group .efb.form-control:valid:focus,.efb.was-validated .efb.input-group .efb.form-select:valid:focus{z-index:3}.efb.invalid-feedback{display:none;width:100%;margin-top:.25rem;font-size:.875em;color:#dc3545}.efb.invalid-tooltip{position:absolute;top:100%;z-index:5;display:none;max-width:100%;padding:.25rem .5rem;margin-top:.1rem;font-size:.875rem;color:#fff;background-color:rgba(220,53,69,.9);border-radius:.25rem}.efb.is-invalid~.efb.invalid-feedback,.efb.is-invalid~.efb.invalid-tooltip,.efb.was-validated:invalid~.efb.invalid-feedback,.efb.was-validated:invalid~.efb.invalid-tooltip{display:block}.efb.form-control.efb.is-invalid,.efb.was-validated .efb.form-control:invalid{border-color:#dc3545;padding-right:calc(1.5em + .75rem);background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 12\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%23dc3545\'%3e%3ccircle cx=\'6\' cy=\'6\' r=\'4.5\'/%3e%3cpath stroke-linejoin=\'round\' d=\'M5.8 3.6h.4L6 6.5z\'/%3e%3ccircle cx=\'6\' cy=\'8.2\' r=\'.6\' fill=\'%23dc3545\' stroke=\'none\'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right calc(.375em + .1875rem) center;background-size:calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-control.efb.is-invalid:focus,.efb.was-validated .efb.form-control:invalid:focus{border-color:#dc3545;box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.was-validated textarea.efb.form-control:invalid,textarea.efb.form-control.efb.is-invalid{padding-right:calc(1.5em + .75rem);background-position:top calc(.375em + .1875rem) right calc(.375em + .1875rem)}.efb.form-select.efb.is-invalid,.efb.was-validated .efb.form-select:invalid{border-color:#dc3545}.efb.form-select.efb.is-invalid:not([multiple]):not([size]),.efb.form-select.efb.is-invalid:not([multiple])[size="1"],.efb.was-validated .efb.form-select:invalid:not([multiple]):not([size]),.efb.was-validated .efb.form-select:invalid:not([multiple])[size="1"]{padding-right:4.125rem;background-image:url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e"),url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 12\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%23dc3545\'%3e%3ccircle cx=\'6\' cy=\'6\' r=\'4.5\'/%3e%3cpath stroke-linejoin=\'round\' d=\'M5.8 3.6h.4L6 6.5z\'/%3e%3ccircle cx=\'6\' cy=\'8.2\' r=\'.6\' fill=\'%23dc3545\' stroke=\'none\'/%3e%3c/svg%3e");background-position:right .75rem center,center right 2.25rem;background-size:16px 12px,calc(.75em + .375rem) calc(.75em + .375rem)}.efb.form-select.efb.is-invalid:focus,.efb.was-validated .efb.form-select:invalid:focus{border-color:#dc3545;box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.form-check-input.efb.is-invalid,.efb.was-validated .efb.form-check-input:invalid{border-color:#dc3545}.efb.form-check-input.efb.is-invalid:checked,.efb.was-validated .efb.form-check-input:invalid:checked{background-color:#dc3545}.efb.form-check-input.efb.is-invalid:focus,.efb.was-validated .efb.form-check-input:invalid:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.25)}.efb.form-check-input.efb.is-invalid~.form-check-label,.efb.was-validated .efb.form-check-input:invalid~.form-check-label{color:#dc3545}.efb.form-check-inline .efb.form-check-input~.efb.invalid-feedback{margin-left:.5em}.efb.input-group .efb.form-control.efb.is-invalid,.efb.input-group .efb.form-select.efb.is-invalid,.efb.was-validated .efb.input-group .efb.form-control:invalid,.efb.was-validated .efb.input-group .efb.form-select:invalid{z-index:2}.efb.input-group .efb.form-control.efb.is-invalid:focus,.efb.input-group .efb.form-select.efb.is-invalid:focus,.efb.was-validated .efb.input-group .efb.form-control:invalid:focus,.efb.was-validated .efb.input-group .efb.form-select:invalid:focus{z-index:3}.efb.btn{display:inline-block;font-weight:400;line-height:1.5;color:#212529;text-align:center;text-decoration:none;vertical-align:middle;cursor:pointer;-webkit-user-select:none;-moz-user-select:none;user-select:none;background-color:transparent;border:1px solid transparent;padding:.375rem .75rem;font-size:1rem;border-radius:.25rem;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.btn{transition:none}}.efb.btn:hover{color:#212529}.efb.btn-check:focus+.efb.btn,.efb.btn:focus{outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.btn.disabled,.efb.btn:disabled,fieldset:disabled .efb.btn{pointer-events:none;opacity:.65}.efb.btn-primary{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-primary:hover{color:#fff;background-color:#0b5ed7;border-color:#0a58ca}.efb.btn-check:focus+.efb.btn-primary,.efb.btn-primary:focus{color:#fff;background-color:#0b5ed7;border-color:#0a58ca;box-shadow:0 0 0 .25rem rgba(49,132,253,.5)}.efb.btn-check:active+.efb.btn-primary,.efb.btn-check:checked+.efb.btn-primary,.efb.btn-primary.active,.efb.btn-primary:active,.show>.efb.btn-primary.efb.dropdown-toggle{color:#fff;background-color:#0a58ca;border-color:#0a53be}.efb.btn-check:active+.efb.btn-primary:focus,.efb.btn-check:checked+.efb.btn-primary:focus,.efb.btn-primary.active:focus,.efb.btn-primary:active:focus,.show>.efb.btn-primary.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(49,132,253,.5)}.efb.btn-primary.disabled,.efb.btn-primary:disabled{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-secondary{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-secondary:hover{color:#fff;background-color:#5c636a;border-color:#565e64}.efb.btn-check:focus+.efb.btn-secondary,.efb.btn-secondary:focus{color:#fff;background-color:#5c636a;border-color:#565e64;box-shadow:0 0 0 .25rem rgba(130,138,145,.5)}.efb.btn-check:active+.efb.btn-secondary,.efb.btn-check:checked+.efb.btn-secondary,.efb.btn-secondary.active,.efb.btn-secondary:active,.show>.efb.btn-secondary.efb.dropdown-toggle{color:#fff;background-color:#565e64;border-color:#51585e}.efb.btn-check:active+.efb.btn-secondary:focus,.efb.btn-check:checked+.efb.btn-secondary:focus,.efb.btn-secondary.active:focus,.efb.btn-secondary:active:focus,.show>.efb.btn-secondary.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(130,138,145,.5)}.efb.btn-secondary.disabled,.efb.btn-secondary:disabled{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-success{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-success:hover{color:#fff;background-color:#157347;border-color:#146c43}.efb.btn-check:focus+.efb.btn-success,.efb.btn-success:focus{color:#fff;background-color:#157347;border-color:#146c43;box-shadow:0 0 0 .25rem rgba(60,153,110,.5)}.efb.btn-check:active+.efb.btn-success,.efb.btn-check:checked+.efb.btn-success,.efb.btn-success.active,.efb.btn-success:active,.show>.efb.btn-success.efb.dropdown-toggle{color:#fff;background-color:#146c43;border-color:#13653f}.efb.btn-check:active+.efb.btn-success:focus,.efb.btn-check:checked+.efb.btn-success:focus,.efb.btn-success.active:focus,.efb.btn-success:active:focus,.show>.efb.btn-success.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(60,153,110,.5)}.efb.btn-success.disabled,.efb.btn-success:disabled{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-info{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-info:hover{color:#000;background-color:#31d2f2;border-color:#25cff2}.efb.btn-check:focus+.efb.btn-info,.efb.btn-info:focus{color:#000;background-color:#31d2f2;border-color:#25cff2;box-shadow:0 0 0 .25rem rgba(11,172,204,.5)}.efb.btn-check:active+.efb.btn-info,.efb.btn-check:checked+.efb.btn-info,.efb.btn-info.active,.efb.btn-info:active,.show>.efb.btn-info.efb.dropdown-toggle{color:#000;background-color:#3dd5f3;border-color:#25cff2}.efb.btn-check:active+.efb.btn-info:focus,.efb.btn-check:checked+.efb.btn-info:focus,.efb.btn-info.active:focus,.efb.btn-info:active:focus,.show>.efb.btn-info.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(11,172,204,.5)}.efb.btn-info.disabled,.efb.btn-info:disabled{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-warning{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-warning:hover{color:#000;background-color:#ffca2c;border-color:#ffc720}.efb.btn-check:focus+.efb.btn-warning,.efb.btn-warning:focus{color:#000;background-color:#ffca2c;border-color:#ffc720;box-shadow:0 0 0 .25rem rgba(217,164,6,.5)}.efb.btn-check:active+.efb.btn-warning,.efb.btn-check:checked+.efb.btn-warning,.efb.btn-warning.active,.efb.btn-warning:active,.show>.efb.btn-warning.efb.dropdown-toggle{color:#000;background-color:#ffcd39;border-color:#ffc720}.efb.btn-check:active+.efb.btn-warning:focus,.efb.btn-check:checked+.efb.btn-warning:focus,.efb.btn-warning.active:focus,.efb.btn-warning:active:focus,.show>.efb.btn-warning.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(217,164,6,.5)}.efb.btn-warning.disabled,.efb.btn-warning:disabled{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-danger{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-danger:hover{color:#fff;background-color:#bb2d3b;border-color:#b02a37}.efb.btn-check:focus+.efb.btn-danger,.efb.btn-danger:focus{color:#fff;background-color:#bb2d3b;border-color:#b02a37;box-shadow:0 0 0 .25rem rgba(225,83,97,.5)}.efb.btn-check:active+.efb.btn-danger,.efb.btn-check:checked+.efb.btn-danger,.efb.btn-danger.active,.efb.btn-danger:active,.show>.efb.btn-danger.efb.dropdown-toggle{color:#fff;background-color:#b02a37;border-color:#a52834}.efb.btn-check:active+.efb.btn-danger:focus,.efb.btn-check:checked+.efb.btn-danger:focus,.efb.btn-danger.active:focus,.efb.btn-danger:active:focus,.show>.efb.btn-danger.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(225,83,97,.5)}.efb.btn-danger.disabled,.efb.btn-danger:disabled{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-light{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-light:hover{color:#000;background-color:#f9fafb;border-color:#f9fafb}.efb.btn-check:focus+.efb.btn-light,.efb.btn-light:focus{color:#000;background-color:#f9fafb;border-color:#f9fafb;box-shadow:0 0 0 .25rem rgba(211,212,213,.5)}.efb.btn-check:active+.efb.btn-light,.efb.btn-check:checked+.efb.btn-light,.efb.btn-light.active,.efb.btn-light:active,.show>.efb.btn-light.efb.dropdown-toggle{color:#000;background-color:#f9fafb;border-color:#f9fafb}.efb.btn-check:active+.efb.btn-light:focus,.efb.btn-check:checked+.efb.btn-light:focus,.efb.btn-light.active:focus,.efb.btn-light:active:focus,.show>.efb.btn-light.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(211,212,213,.5)}.efb.btn-light.disabled,.efb.btn-light:disabled{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-dark{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-dark:hover{color:#fff;background-color:#1c1f23;border-color:#1a1e21}.efb.btn-check:focus+.efb.btn-dark,.efb.btn-dark:focus{color:#fff;background-color:#1c1f23;border-color:#1a1e21;box-shadow:0 0 0 .25rem rgba(66,70,73,.5)}.efb.btn-check:active+.efb.btn-dark,.efb.btn-check:checked+.efb.btn-dark,.efb.btn-dark.active,.efb.btn-dark:active,.show>.efb.btn-dark.efb.dropdown-toggle{color:#fff;background-color:#1a1e21;border-color:#191c1f}.efb.btn-check:active+.efb.btn-dark:focus,.efb.btn-check:checked+.efb.btn-dark:focus,.efb.btn-dark.active:focus,.efb.btn-dark:active:focus,.show>.efb.btn-dark.efb.dropdown-toggle:focus{box-shadow:0 0 0 .25rem rgba(66,70,73,.5)}.efb.btn-dark.disabled,.efb.btn-dark:disabled{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-outline-primary{color:#0d6efd;border-color:#0d6efd}.efb.btn-outline-primary:hover{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-check:focus+.efb.btn-outline-primary,.efb.btn-outline-primary:focus{box-shadow:0 0 0 .25rem rgba(13,110,253,.5)}.efb.btn-check:active+.efb.btn-outline-primary,.efb.btn-check:checked+.efb.btn-outline-primary,.efb.btn-outline-primary.active,.efb.btn-outline-primary.efb.dropdown-toggle.show,.efb.btn-outline-primary:active{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.btn-check:active+.efb.btn-outline-primary:focus,.efb.btn-check:checked+.efb.btn-outline-primary:focus,.efb.btn-outline-primary.active:focus,.efb.btn-outline-primary.efb.dropdown-toggle.show:focus,.efb.btn-outline-primary:active:focus{box-shadow:0 0 0 .25rem rgba(13,110,253,.5)}.efb.btn-outline-primary.disabled,.efb.btn-outline-primary:disabled{color:#0d6efd;background-color:transparent}.efb.btn-outline-secondary{color:#6c757d;border-color:#6c757d}.efb.btn-outline-secondary:hover{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-check:focus+.efb.btn-outline-secondary,.efb.btn-outline-secondary:focus{box-shadow:0 0 0 .25rem rgba(108,117,125,.5)}.efb.btn-check:active+.efb.btn-outline-secondary,.efb.btn-check:checked+.efb.btn-outline-secondary,.efb.btn-outline-secondary.active,.efb.btn-outline-secondary.efb.dropdown-toggle.show,.efb.btn-outline-secondary:active{color:#fff;background-color:#6c757d;border-color:#6c757d}.efb.btn-check:active+.efb.btn-outline-secondary:focus,.efb.btn-check:checked+.efb.btn-outline-secondary:focus,.efb.btn-outline-secondary.active:focus,.efb.btn-outline-secondary.efb.dropdown-toggle.show:focus,.efb.btn-outline-secondary:active:focus{box-shadow:0 0 0 .25rem rgba(108,117,125,.5)}.efb.btn-outline-secondary.disabled,.efb.btn-outline-secondary:disabled{color:#6c757d;background-color:transparent}.efb.btn-outline-success{color:#198754;border-color:#198754}.efb.btn-outline-success:hover{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-check:focus+.efb.btn-outline-success,.efb.btn-outline-success:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.5)}.efb.btn-check:active+.efb.btn-outline-success,.efb.btn-check:checked+.efb.btn-outline-success,.efb.btn-outline-success.active,.efb.btn-outline-success.efb.dropdown-toggle.show,.efb.btn-outline-success:active{color:#fff;background-color:#198754;border-color:#198754}.efb.btn-check:active+.efb.btn-outline-success:focus,.efb.btn-check:checked+.efb.btn-outline-success:focus,.efb.btn-outline-success.active:focus,.efb.btn-outline-success.efb.dropdown-toggle.show:focus,.efb.btn-outline-success:active:focus{box-shadow:0 0 0 .25rem rgba(25,135,84,.5)}.efb.btn-outline-success.disabled,.efb.btn-outline-success:disabled{color:#198754;background-color:transparent}.efb.btn-outline-info{color:#0dcaf0;border-color:#0dcaf0}.efb.btn-outline-info:hover{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-check:focus+.efb.btn-outline-info,.efb.btn-outline-info:focus{box-shadow:0 0 0 .25rem rgba(13,202,240,.5)}.efb.btn-check:active+.efb.btn-outline-info,.efb.btn-check:checked+.efb.btn-outline-info,.efb.btn-outline-info.active,.efb.btn-outline-info.efb.dropdown-toggle.show,.efb.btn-outline-info:active{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.efb.btn-check:active+.efb.btn-outline-info:focus,.efb.btn-check:checked+.efb.btn-outline-info:focus,.efb.btn-outline-info.active:focus,.efb.btn-outline-info.efb.dropdown-toggle.show:focus,.efb.btn-outline-info:active:focus{box-shadow:0 0 0 .25rem rgba(13,202,240,.5)}.efb.btn-outline-info.disabled,.efb.btn-outline-info:disabled{color:#0dcaf0;background-color:transparent}.efb.btn-outline-warning{color:#ffc107;border-color:#ffc107}.efb.btn-outline-warning:hover{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-check:focus+.efb.btn-outline-warning,.efb.btn-outline-warning:focus{box-shadow:0 0 0 .25rem rgba(255,193,7,.5)}.efb.btn-check:active+.efb.btn-outline-warning,.efb.btn-check:checked+.efb.btn-outline-warning,.efb.btn-outline-warning.active,.efb.btn-outline-warning.efb.dropdown-toggle.show,.efb.btn-outline-warning:active{color:#000;background-color:#ffc107;border-color:#ffc107}.efb.btn-check:active+.efb.btn-outline-warning:focus,.efb.btn-check:checked+.efb.btn-outline-warning:focus,.efb.btn-outline-warning.active:focus,.efb.btn-outline-warning.efb.dropdown-toggle.show:focus,.efb.btn-outline-warning:active:focus{box-shadow:0 0 0 .25rem rgba(255,193,7,.5)}.efb.btn-outline-warning.disabled,.efb.btn-outline-warning:disabled{color:#ffc107;background-color:transparent}.efb.btn-outline-danger{color:#dc3545;border-color:#dc3545}.efb.btn-outline-danger:hover{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-check:focus+.efb.btn-outline-danger,.efb.btn-outline-danger:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.5)}.efb.btn-check:active+.efb.btn-outline-danger,.efb.btn-check:checked+.efb.btn-outline-danger,.efb.btn-outline-danger.active,.efb.btn-outline-danger.efb.dropdown-toggle.show,.efb.btn-outline-danger:active{color:#fff;background-color:#dc3545;border-color:#dc3545}.efb.btn-check:active+.efb.btn-outline-danger:focus,.efb.btn-check:checked+.efb.btn-outline-danger:focus,.efb.btn-outline-danger.active:focus,.efb.btn-outline-danger.efb.dropdown-toggle.show:focus,.efb.btn-outline-danger:active:focus{box-shadow:0 0 0 .25rem rgba(220,53,69,.5)}.efb.btn-outline-danger.disabled,.efb.btn-outline-danger:disabled{color:#dc3545;background-color:transparent}.efb.btn-outline-light{color:#f8f9fa;border-color:#f8f9fa}.efb.btn-outline-light:hover{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-check:focus+.efb.btn-outline-light,.efb.btn-outline-light:focus{box-shadow:0 0 0 .25rem rgba(248,249,250,.5)}.efb.btn-check:active+.efb.btn-outline-light,.efb.btn-check:checked+.efb.btn-outline-light,.efb.btn-outline-light.active,.efb.btn-outline-light.efb.dropdown-toggle.show,.efb.btn-outline-light:active{color:#000;background-color:#f8f9fa;border-color:#f8f9fa}.efb.btn-check:active+.efb.btn-outline-light:focus,.efb.btn-check:checked+.efb.btn-outline-light:focus,.efb.btn-outline-light.active:focus,.efb.btn-outline-light.efb.dropdown-toggle.show:focus,.efb.btn-outline-light:active:focus{box-shadow:0 0 0 .25rem rgba(248,249,250,.5)}.efb.btn-outline-light.disabled,.efb.btn-outline-light:disabled{color:#f8f9fa;background-color:transparent}.efb.btn-outline-dark{color:#212529;border-color:#212529}.efb.btn-outline-dark:hover{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-check:focus+.efb.btn-outline-dark,.efb.btn-outline-dark:focus{box-shadow:0 0 0 .25rem rgba(33,37,41,.5)}.efb.btn-check:active+.efb.btn-outline-dark,.efb.btn-check:checked+.efb.btn-outline-dark,.efb.btn-outline-dark.active,.efb.btn-outline-dark.efb.dropdown-toggle.show,.efb.btn-outline-dark:active{color:#fff;background-color:#212529;border-color:#212529}.efb.btn-check:active+.efb.btn-outline-dark:focus,.efb.btn-check:checked+.efb.btn-outline-dark:focus,.efb.btn-outline-dark.active:focus,.efb.btn-outline-dark.efb.dropdown-toggle.show:focus,.efb.btn-outline-dark:active:focus{box-shadow:0 0 0 .25rem rgba(33,37,41,.5)}.efb.btn-outline-dark.disabled,.efb.btn-outline-dark:disabled{color:#212529;background-color:transparent}.efb.btn-link{font-weight:400;color:#0d6efd;text-decoration:underline}.efb.btn-link:hover{color:#0a58ca}.efb.btn-link.disabled,.efb.btn-link:disabled{color:#6c757d}.efb.btn-group-lg>.efb.btn,.efb.btn-lg{padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.efb.btn-group-sm>.efb.btn,.efb.btn-sm{padding:.2rem .3rem;font-size:.875rem;border-radius:.2rem}.efb.fade{transition:opacity .15s linear}@media (prefers-reduced-motion:reduce){.efb.fade{transition:none}}.efb.fade:not(.show){opacity:0}.efb.collapse:not(.show){display:none}.efb.collapsing{height:0;overflow:hidden;transition:height .35s ease}@media (prefers-reduced-motion:reduce){.efb.collapsing{transition:none}}.efb.dropdown,.efb.dropend,.efb.dropstart,.efb.dropup{position:relative}.efb.dropdown-toggle{white-space:nowrap}.efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:"";border-top:.3em solid;border-right:.3em solid transparent;border-bottom:0;border-left:.3em solid transparent}.efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropdown-menu{position:absolute;z-index:1000;display:none;min-width:10rem;padding:.5rem 0;margin:0;font-size:1rem;color:#212529;text-align:left;list-style:none;background-color:#fff;background-clip:padding-box;border:1px solid rgba(0,0,0,.15);border-radius:.25rem}.efb.dropdown-menu[data-bs-popper]{top:100%;left:0;margin-top:.125rem}.efb.dropdown-menu-start{--bs-position:start}.efb.dropdown-menu-start[data-bs-popper]{right:auto;left:0}.efb.dropdown-menu-end{--bs-position:end}.efb.dropdown-menu-end[data-bs-popper]{right:0;left:auto}.efb.dropup .efb.dropdown-menu[data-bs-popper]{top:auto;bottom:100%;margin-top:0;margin-bottom:.125rem}.efb.dropup .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:"";border-top:0;border-right:.3em solid transparent;border-bottom:.3em solid;border-left:.3em solid transparent}.efb.dropup .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropend .efb.dropdown-menu[data-bs-popper]{top:0;right:auto;left:100%;margin-top:0;margin-left:.125rem}.efb.dropend .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:"";border-top:.3em solid transparent;border-right:0;border-bottom:.3em solid transparent;border-left:.3em solid}.efb.dropend .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropend .efb.dropdown-toggle::after{vertical-align:0}.efb.dropstart .efb.dropdown-menu[data-bs-popper]{top:0;right:100%;left:auto;margin-top:0;margin-right:.125rem}.efb.dropstart .efb.dropdown-toggle::after{display:inline-block;margin-left:.255em;vertical-align:.255em;content:""}.efb.dropstart .efb.dropdown-toggle::after{display:none}.efb.dropstart .efb.dropdown-toggle::before{display:inline-block;margin-right:.255em;vertical-align:.255em;content:"";border-top:.3em solid transparent;border-right:.3em solid;border-bottom:.3em solid transparent}.efb.dropstart .efb.dropdown-toggle:empty::after{margin-left:0}.efb.dropstart .efb.dropdown-toggle::before{vertical-align:0}.efb.dropdown-divider{height:0;margin:.5rem 0;overflow:hidden;border-top:1px solid rgba(0,0,0,.15)}.efb.dropdown-item{display:block;width:100%;padding:.25rem 1rem;clear:both;font-weight:400;color:#212529;text-align:inherit;text-decoration:none;white-space:nowrap;background-color:transparent;border:0}.efb.dropdown-item:focus,.efb.dropdown-item:hover{color:#1e2125;background-color:#e9ecef}.efb.dropdown-item.active,.efb.dropdown-item:active{color:#fff;text-decoration:none;background-color:#0d6efd}.efb.dropdown-item.disabled,.efb.dropdown-item:disabled{color:#adb5bd;pointer-events:none;background-color:transparent}.efb.dropdown-menu.show{display:block}.efb.dropdown-header{display:block;padding:.5rem 1rem;margin-bottom:0;font-size:.875rem;color:#6c757d;white-space:nowrap}.efb.dropdown-item-text{display:block;padding:.25rem 1rem;color:#212529}.efb.dropdown-menu-dark{color:#dee2e6;background-color:#343a40;border-color:rgba(0,0,0,.15)}.efb.dropdown-menu-dark .efb.dropdown-item{color:#dee2e6}.efb.dropdown-menu-dark .efb.dropdown-item:focus,.efb.dropdown-menu-dark .efb.dropdown-item:hover{color:#fff;background-color:rgba(255,255,255,.15)}.efb.dropdown-menu-dark .efb.dropdown-item.active,.efb.dropdown-menu-dark .efb.dropdown-item:active{color:#fff;background-color:#0d6efd}.efb.dropdown-menu-dark .efb.dropdown-item.disabled,.efb.dropdown-menu-dark .efb.dropdown-item:disabled{color:#adb5bd}.efb.dropdown-menu-dark .efb.dropdown-divider{border-color:rgba(0,0,0,.15)}.efb.dropdown-menu-dark .efb.dropdown-item-text{color:#dee2e6}.efb.dropdown-menu-dark .efb.dropdown-header{color:#adb5bd}.efb.btn-group{position:relative;display:inline-flex;vertical-align:middle}.efb.btn-group>.efb.btn{position:relative;flex:1 1 auto}.efb.btn-group>.efb.btn-check:checked+.efb.btn,.efb.btn-group>.efb.btn-check:focus+.efb.btn,.efb.btn-group>.efb.btn.active,.efb.btn-group>.efb.btn:active,.efb.btn-group>.efb.btn:focus,.efb.btn-group>.efb.btn:hover{z-index:1}.efb.btn-toolbar{display:flex;flex-wrap:wrap;justify-content:flex-start}.efb.btn-toolbar .efb.input-group{width:auto}.efb.btn-group>.efb.btn-group:not(:first-child),.efb.btn-group>.efb.btn:not(:first-child){margin-left:-1px}.efb.btn-group>.efb.btn-group:not(:last-child)>.efb.btn,.efb.btn-group>.efb.btn:not(:last-child):not(.efb.dropdown-toggle){border-top-right-radius:0;border-bottom-right-radius:0}.efb.btn-group>.efb.btn-group:not(:first-child)>.efb.btn,.efb.btn-group>.efb.btn:nth-child(n+3),.efb.btn-group>:not(.efb.btn-check)+.efb.btn{border-top-left-radius:0;border-bottom-left-radius:0}.efb.dropdown-toggle-split{padding-right:.5625rem;padding-left:.5625rem}.efb.dropdown-toggle-split::after,.efb.dropend .efb.dropdown-toggle-split::after,.efb.dropup .efb.dropdown-toggle-split::after{margin-left:0}.efb.dropstart .efb.dropdown-toggle-split::before{margin-right:0}.efb.btn-group-sm>.efb.btn+.efb.dropdown-toggle-split,.efb.btn-sm+.efb.dropdown-toggle-split{padding-right:.375rem;padding-left:.375rem}.efb.btn-group-lg>.efb.btn+.efb.dropdown-toggle-split,.efb.btn-lg+.efb.dropdown-toggle-split{padding-right:.75rem;padding-left:.75rem}.efb.tab-content>.tab-pane{display:none}.efb.tab-content>.active{display:block}.efb.card{position:relative;display:flex;flex-direction:column;min-width:0;word-wrap:break-word;background-color:#fff;background-clip:border-box;border:1px solid rgba(0,0,0,.125);border-radius:.25rem}.efb.card>hr{margin-right:0;margin-left:0}.efb.card>.efb.list-group{border-top:inherit;border-bottom:inherit}.efb.card>.efb.list-group:first-child{border-top-width:0;border-top-left-radius:calc(.25rem - 1px);border-top-right-radius:calc(.25rem - 1px)}.card>.efb.list-group:last-child{border-bottom-width:0;border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}.efb.card>.efb.card-header+.efb.list-group,.efb.card>.efb.list-group+.efb.card-footer{border-top:0}.efb.card-body{flex:1 1 auto;padding:1rem 1rem}.efb.card-title{margin-bottom:.5rem}.efb.card-text:last-child{margin-bottom:0}.efb.card-header{padding:.5rem 1rem;margin-bottom:0;background-color:rgba(0,0,0,.03);border-bottom:1px solid rgba(0,0,0,.125)}.efb.card-header:first-child{border-radius:calc(.25rem - 1px) calc(.25rem - 1px) 0 0}.efb.card-footer{padding:.5rem 1rem;background-color:rgba(0,0,0,.03);border-top:1px solid rgba(0,0,0,.125)}.efb.card-footer:last-child{border-radius:0 0 calc(.25rem - 1px) calc(.25rem - 1px)}.efb.card-header-tabs{margin-right:-.5rem;margin-bottom:-.5rem;margin-left:-.5rem;border-bottom:0}.efb.card-header-pills{margin-right:-.5rem;margin-left:-.5rem}.efb.card-img-overlay{position:absolute;top:0;right:0;bottom:0;left:0;padding:1rem;border-radius:calc(.25rem - 1px)}.efb.card-img,.efb.card-img-bottom,.efb.card-img-top{width:100%}.efb.card-img,.efb.card-img-top{border-top-left-radius:calc(.25rem - 1px);border-top-right-radius:calc(.25rem - 1px)}.efb.card-img,.efb.card-img-bottom{border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}.efb.card-group>.card{margin-bottom:.75rem}@media (min-width:576px){.efb.card-group{display:flex;flex-flow:row wrap}.efb.card-group>.card{flex:1 0 0%;margin-bottom:0}.efb.card-group>.card+.card{margin-left:0;border-left:0}.efb.card-group>.card:not(:last-child){border-top-right-radius:0;border-bottom-right-radius:0}.efb.card-group>.card:not(:last-child) .efb.card-header,.efb.card-group>.card:not(:last-child) .efb.card-img-top{border-top-right-radius:0}.efb.card-group>.card:not(:last-child) .efb.card-footer,.efb.card-group>.card:not(:last-child) .efb.card-img-bottom{border-bottom-right-radius:0}.efb.card-group>.card:not(:first-child){border-top-left-radius:0;border-bottom-left-radius:0}.efb.card-group>.card:not(:first-child) .efb.card-header,.efb.card-group>.card:not(:first-child) .efb.card-img-top{border-top-left-radius:0}.efb.card-group>.card:not(:first-child) .efb.card-footer,.efb.card-group>.card:not(:first-child) .efb.card-img-bottom{border-bottom-left-radius:0}}.efb.page-link{position:relative;display:block;color:#0d6efd;text-decoration:none;background-color:#fff;border:1px solid #dee2e6;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}@media (prefers-reduced-motion:reduce){.efb.page-link{transition:none}}.efb.page-link:hover{z-index:2;color:#0a58ca;background-color:#e9ecef;border-color:#dee2e6}.efb.page-link:focus{z-index:3;color:#0a58ca;background-color:#e9ecef;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.efb.page-item:not(:first-child) .efb.page-link{margin-left:-1px}.efb.page-item.active .efb.page-link{z-index:3;color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.page-item.disabled .efb.page-link{color:#6c757d;pointer-events:none;background-color:#fff;border-color:#dee2e6}.efb.page-link{padding:.375rem .75rem}.efb.page-item:first-child .efb.page-link{border-top-left-radius:.25rem;border-bottom-left-radius:.25rem}.efb.page-item:last-child .efb.page-link{border-top-right-radius:.25rem;border-bottom-right-radius:.25rem}.efb.pagination-lg .efb.page-link{padding:.75rem 1.5rem;font-size:1.25rem}.efb.pagination-lg .efb.page-item:first-child .efb.page-link{border-top-left-radius:.3rem;border-bottom-left-radius:.3rem}.efb.pagination-lg .efb.page-item:last-child .efb.page-link{border-top-right-radius:.3rem;border-bottom-right-radius:.3rem}.efb.pagination-sm .efb.page-link{padding:.25rem .5rem;font-size:.875rem}.efb.pagination-sm .efb.page-item:first-child .efb.page-link{border-top-left-radius:.2rem;border-bottom-left-radius:.2rem}.pagination-sm .efb.page-item:last-child .efb.page-link{border-top-right-radius:.2rem;border-bottom-right-radius:.2rem}.efb.badge{display:inline-block;padding:.35em .65em;font-size:.75em;font-weight:700;line-height:1;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.25rem}.efb.badge:empty{display:none}.efb.btn .efb.badge{position:relative;top:-1px}.efb.alert{position:relative;padding:1rem 1rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.25rem}.efb.alert-heading{color:inherit}.efb.alert-link{font-weight:700}.efb.alert-dismissible{padding-right:3rem}.efb.alert-dismissible .efb.btn-close{position:absolute;top:0;right:0;z-index:2;padding:1.25rem 1rem}.efb.alert-primary{color:#084298;background-color:#cfe2ff;border-color:#b6d4fe}.efb.alert-primary .efb.alert-link{color:#06357a}.efb.alert-secondary{color:#41464b;background-color:#e2e3e5;border-color:#d3d6d8}.efb.alert-secondary .efb.alert-link{color:#34383c}.efb.alert-success{color:#0f5132;background-color:#d1e7dd;border-color:#badbcc}.efb.alert-success .efb.alert-link{color:#0c4128}.efb.alert-info{color:#055160;background-color:#cff4fc;border-color:#b6effb}.efb.alert-info .efb.alert-link{color:#04414d}.efb.alert-warning{color:#664d03;background-color:#fff3cd;border-color:#ffecb5}.efb.alert-warning .efb.alert-link{color:#523e02}.efb.alert-danger{color:#842029;background-color:#f8d7da;border-color:#f5c2c7}.efb.alert-danger .efb.alert-link{color:#6a1a21}.efb.alert-light{color:#636464;background-color:#fefefe;border-color:#fdfdfe}.efb.alert-light .efb.alert-link{color:#4f5050}.efb.alert-dark{color:#141619;background-color:#d3d3d4;border-color:#bcbebf}.efb.alert-dark .efb.alert-link{color:#101214}@-webkit-keyframes progress-bar-stripes{0%{background-position-x:1rem}}@keyframes progress-bar-stripes{0%{background-position-x:1rem}}.efb.progress{display:flex;height:1rem;overflow:hidden;font-size:.75rem;background-color:#e9ecef;border-radius:.25rem}.efb.progress-bar{display:flex;flex-direction:column;justify-content:center;overflow:hidden;color:#fff;text-align:center;white-space:nowrap;background-color:#0d6efd;transition:width .6s ease}@media (prefers-reduced-motion:reduce){.efb.progress-bar{transition:none}}.efb.progress-bar-striped{background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent)!important;background-size:1rem 1rem}.efb.progress-bar-animated{-webkit-animation:1s linear infinite progress-bar-stripes;animation:1s linear infinite progress-bar-stripes}@media (prefers-reduced-motion:reduce){.efb.progress-bar-animated{-webkit-animation:none;animation:none}}.efb.list-group{display:flex;flex-direction:column;padding-left:0;margin-bottom:0;border-radius:.25rem}.efb.list-group-numbered{list-style-type:none;counter-reset:section}.efb.list-group-numbered>li::before{content:counters(section,".") ". ";counter-increment:section}.efb.list-group-item-action{width:100%;color:#495057;text-align:inherit}.efb.list-group-item-action:focus,.efb.list-group-item-action:hover{z-index:1;color:#495057;text-decoration:none;background-color:#f8f9fa}.efb.list-group-item-action:active{color:#212529;background-color:#e9ecef}.efb.list-group-item{position:relative;display:block;padding:.5rem 1rem;color:#212529;text-decoration:none;background-color:#fff;border:1px solid rgba(0,0,0,.125)}.efb.list-group-item:first-child{border-top-left-radius:inherit;border-top-right-radius:inherit}.efb.list-group-item:last-child{border-bottom-right-radius:inherit;border-bottom-left-radius:inherit}.efb.list-group-item.disabled,.efb.list-group-item:disabled{color:#6c757d;pointer-events:none;background-color:#fff}.efb.list-group-item.active{z-index:2;color:#fff;background-color:#0d6efd;border-color:#0d6efd}.efb.list-group-item+.efb.list-group-item{border-top-width:0}.efb.list-group-item+.efb.list-group-item.active{margin-top:-1px;border-top-width:1px}.efb.list-group-horizontal{flex-direction:row}.efb.list-group-horizontal>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}@media (min-width:576px){.efb.list-group-horizontal-sm{flex-direction:row}.efb.list-group-horizontal-sm>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-sm>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-sm>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-sm>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-sm>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:768px){.efb.list-group-horizontal-md{flex-direction:row}.efb.list-group-horizontal-md>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-md>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-md>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-md>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-md>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:992px){.efb.list-group-horizontal-lg{flex-direction:row}.efb.list-group-horizontal-lg>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-lg>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-lg>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-lg>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-lg>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:1200px){.efb.list-group-horizontal-xl{flex-direction:row}.efb.list-group-horizontal-xl>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-xl>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-xl>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-xl>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-xl>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}@media (min-width:1400px){.efb.list-group-horizontal-xxl{flex-direction:row}.efb.list-group-horizontal-xxl>.efb.list-group-item:first-child{border-bottom-left-radius:.25rem;border-top-right-radius:0}.efb.list-group-horizontal-xxl>.efb.list-group-item:last-child{border-top-right-radius:.25rem;border-bottom-left-radius:0}.efb.list-group-horizontal-xxl>.efb.list-group-item.active{margin-top:0}.efb.list-group-horizontal-xxl>.efb.list-group-item+.efb.list-group-item{border-top-width:1px;border-left-width:0}.efb.list-group-horizontal-xxl>.efb.list-group-item+.efb.list-group-item.active{margin-left:-1px;border-left-width:1px}}.efb.list-group-flush{border-radius:0}.efb.list-group-flush>.efb.list-group-item{border-width:0 0 1px}.efb.list-group-flush>.efb.list-group-item:last-child{border-bottom-width:0}.efb.list-group-item-primary{color:#084298;background-color:#cfe2ff}.efb.list-group-item-primary.efb.list-group-item-action:focus,.efb.list-group-item-primary.efb.list-group-item-action:hover{color:#084298;background-color:#bacbe6}.efb.list-group-item-primary.efb.list-group-item-action.active{color:#fff;background-color:#084298;border-color:#084298}.efb.list-group-item-secondary{color:#41464b;background-color:#e2e3e5}.efb.list-group-item-secondary.efb.list-group-item-action:focus,.efb.list-group-item-secondary.efb.list-group-item-action:hover{color:#41464b;background-color:#cbccce}.efb.list-group-item-secondary.efb.list-group-item-action.active{color:#fff;background-color:#41464b;border-color:#41464b}.efb.list-group-item-success{color:#0f5132;background-color:#d1e7dd}.efb.list-group-item-success.efb.list-group-item-action:focus,.efb.list-group-item-success.efb.list-group-item-action:hover{color:#0f5132;background-color:#bcd0c7}.efb.list-group-item-success.efb.list-group-item-action.active{color:#fff;background-color:#0f5132;border-color:#0f5132}.efb.list-group-item-info{color:#055160;background-color:#cff4fc}.efb.list-group-item-info.efb.list-group-item-action:focus,.efb.list-group-item-info.efb.list-group-item-action:hover{color:#055160;background-color:#badce3}.efb.list-group-item-info.efb.list-group-item-action.active{color:#fff;background-color:#055160;border-color:#055160}.efb.list-group-item-warning{color:#664d03;background-color:#fff3cd}.efb.list-group-item-warning.efb.list-group-item-action:focus,.efb.list-group-item-warning.efb.list-group-item-action:hover{color:#664d03;background-color:#e6dbb9}.efb.list-group-item-warning.efb.list-group-item-action.active{color:#fff;background-color:#664d03;border-color:#664d03}.efb.list-group-item-danger{color:#842029;background-color:#f8d7da}.efb.list-group-item-danger.efb.list-group-item-action:focus,.efb.list-group-item-danger.efb.list-group-item-action:hover{color:#842029;background-color:#dfc2c4}.efb.list-group-item-danger.efb.list-group-item-action.active{color:#fff;background-color:#842029;border-color:#842029}.efb.list-group-item-light{color:#636464;background-color:#fefefe}.efb.list-group-item-light.efb.list-group-item-action:focus,.efb.list-group-item-light.efb.list-group-item-action:hover{color:#636464;background-color:#e5e5e5}.efb.list-group-item-light.efb.list-group-item-action.active{color:#fff;background-color:#636464;border-color:#636464}.efb.list-group-item-dark{color:#141619;background-color:#d3d3d4}.efb.list-group-item-dark.efb.list-group-item-action:focus,.efb.list-group-item-dark.efb.list-group-item-action:hover{color:#141619;background-color:#bebebf}.efb.list-group-item-dark.efb.list-group-item-action.active{color:#fff;background-color:#141619;border-color:#141619}.efb.btn-close{box-sizing:content-box;width:1em;height:1em;padding:.25em .25em;color:#000;background:transparent url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z\'/%3e%3c/svg%3e") center/1em auto no-repeat;border:0;border-radius:.25rem;opacity:.5}.efb.btn-close:hover{color:#000;text-decoration:none;opacity:.75}.efb.btn-close:focus{outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25);opacity:1}.efb.btn-close.disabled,.efb.btn-close:disabled{pointer-events:none;-webkit-user-select:none;-moz-user-select:none;user-select:none;opacity:.25}.efb.btn-close-white{filter:invert(1) grayscale(100%) brightness(200%)}.efb.modal{position:fixed;top:0;left:0;z-index:1060;display:none;width:100%;height:100%;overflow-x:hidden;overflow-y:auto;outline:0}.efb.modal-dialog{position:relative;width:auto;margin:.5rem;pointer-events:none}.efb.modal.efb.fade .efb.modal-dialog{transition:transform .3s ease-out;transform:translate(0,-50px)}@media (prefers-reduced-motion:reduce){.efb.modal.efb.fade .efb.modal-dialog{transition:none}}.efb.modal.show .modal-dialog{transform:none}.efb.modal.modal-static .efb.modal-dialog{transform:scale(1.02)}.efb.modal-dialog-scrollable{height:calc(100% - 1rem)}.efb.modal-dialog-scrollable .efb.modal-content{max-height:100%;overflow:hidden}.efb.modal-dialog-scrollable .efb.modal-body{overflow-y:auto}.efb.modal-dialog-centered{display:flex;align-items:center;min-height:calc(100% - 1rem)}.efb.modal-content{position:relative;display:flex;flex-direction:column;width:100%;pointer-events:auto;background-color:#fff;background-clip:padding-box;border:1px solid rgba(0,0,0,.2);border-radius:.3rem;outline:0}.efb.modal-backdrop{position:fixed;top:0;left:0;z-index:1040;width:100vw;height:100vh;background-color:#000}.efb.modal-backdrop.efb.fade{opacity:0}.efb.modal-backdrop.show{opacity:.5}.efb.modal-header{display:flex;flex-shrink:0;align-items:center;justify-content:space-between;padding:1rem 1rem;border-bottom:1px solid #dee2e6;border-top-left-radius:calc(.3rem - 1px);border-top-right-radius:calc(.3rem - 1px)}.efb.modal-header .efb.btn-close{padding:.5rem .5rem;margin:-.5rem -.5rem -.5rem auto}.efb.modal-title{margin-bottom:0;line-height:1.5}.efb.modal-body{position:relative;flex:1 1 auto;padding:1rem}.efb.modal-footer{display:flex;flex-wrap:wrap;flex-shrink:0;align-items:center;justify-content:flex-end;padding:.75rem;border-top:1px solid #dee2e6;border-bottom-right-radius:calc(.3rem - 1px);border-bottom-left-radius:calc(.3rem - 1px)}.efb.modal-footer>*{margin:.25rem}@media (min-width:576px){.efb.modal-dialog{max-width:500px;margin:1.75rem auto}.efb.modal-dialog-scrollable{height:calc(100% - 3.5rem)}.efb.modal-dialog-centered{min-height:calc(100% - 3.5rem)}.efb.modal-sm{max-width:300px}}.efb.modal-content{height:100%;border:0;border-radius:0}.efb.modal-header{border-radius:0}.efb.modal-body{overflow-y:auto}.efb.modal-footer{border-radius:0}.efb.tooltip{position:absolute;z-index:1080;display:block;margin:0;font-family:var(--bs-font-sans-serif);font-style:normal;font-weight:400;line-height:1.5;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;letter-spacing:normal;word-break:normal;word-spacing:normal;white-space:normal;line-break:auto;font-size:.875rem;word-wrap:break-word;opacity:0}.efb.tooltip.show{opacity:.9}.efb.tooltip .efb.tooltip-arrow{position:absolute;display:block;width:.8rem;height:.4rem}.efb.tooltip .efb.tooltip-arrow::before{position:absolute;content:"";border-color:transparent;border-style:solid}.efb.bs-tooltip-auto[data-popper-placement^=top],.efb.bs-tooltip-top{padding:.4rem 0}.efb.bs-tooltip-auto[data-popper-placement^=top] .efb.tooltip-arrow,.efb.bs-tooltip-top .efb.tooltip-arrow{bottom:0}.efb.bs-tooltip-auto[data-popper-placement^=top] .efb.tooltip-arrow::before,.efb.bs-tooltip-top .efb.tooltip-arrow::before{top:-1px;border-width:.4rem .4rem 0;border-top-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=right],.efb.bs-tooltip-end{padding:0 .4rem}.efb.bs-tooltip-auto[data-popper-placement^=right] .efb.tooltip-arrow,.efb.bs-tooltip-end .efb.tooltip-arrow{left:0;width:.4rem;height:.8rem}.efb.bs-tooltip-auto[data-popper-placement^=right] .efb.tooltip-arrow::before,.efb.bs-tooltip-end .efb.tooltip-arrow::before{right:-1px;border-width:.4rem .4rem .4rem 0;border-right-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=bottom],.efb.bs-tooltip-bottom{padding:.4rem 0}.efb.bs-tooltip-auto[data-popper-placement^=bottom] .efb.tooltip-arrow,.efb.bs-tooltip-bottom .efb.tooltip-arrow{top:0}.efb.bs-tooltip-auto[data-popper-placement^=bottom] .efb.tooltip-arrow::before,.efb.bs-tooltip-bottom .efb.tooltip-arrow::before{bottom:-1px;border-width:0 .4rem .4rem;border-bottom-color:#000}.efb.bs-tooltip-auto[data-popper-placement^=left],.efb.bs-tooltip-start{padding:0 .4rem}.efb.bs-tooltip-auto[data-popper-placement^=left] .efb.tooltip-arrow,.efb.bs-tooltip-start .efb.tooltip-arrow{right:0;width:.4rem;height:.8rem}.efb.bs-tooltip-auto[data-popper-placement^=left] .efb.tooltip-arrow::before,.efb.bs-tooltip-start .efb.tooltip-arrow::before{left:-1px;border-width:.4rem 0 .4rem .4rem;border-left-color:#000}.efb.tooltip-inner{max-width:200px;padding:.25rem .5rem;color:#fff;text-align:center;background-color:#000;border-radius:.25rem}.efb.clearfix::after{display:block;clear:both;content:""}.efb.stretched-link::after{position:absolute;top:0;right:0;bottom:0;left:0;z-index:1;content:""}.efb.text-truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.efb.align-baseline{vertical-align:baseline!important}.efb.align-top{vertical-align:top!important}.efb.align-middle{vertical-align:middle!important}.efb.align-bottom{vertical-align:bottom!important}.efb.align-text-bottom{vertical-align:text-bottom!important}.efb.align-text-top{vertical-align:text-top!important}.efb.float-start{float:left!important}.efb.float-end{float:right!important}.efb.float-none{float:none!important}.efb.overflow-auto{overflow:auto!important}.efb.overflow-hidden{overflow:hidden!important}.efb.overflow-visible{overflow:visible!important}.efb.overflow-scroll{overflow:scroll!important}.efb.d-inline{display:inline!important}.efb.d-inline-block{display:inline-block!important}.efb.d-block{display:block!important}.efb.d-grid{display:grid!important}.efb.d-flex{display:flex!important}.efb.d-inline-flex{display:inline-flex!important}.efb.d-none{display:none!important}.efb.shadow{box-shadow:0 .5rem 1rem rgba(0,0,0,.15)!important}.efb.shadow-sm{box-shadow:0 .125rem .25rem rgba(0,0,0,.075)!important}.efb.shadow-lg{box-shadow:0 1rem 3rem rgba(0,0,0,.175)!important}.efb.shadow-none{box-shadow:none!important}.efb.position-static{position:static!important}.efb.position-relative{position:relative!important}.efb.position-absolute{position:absolute!important}.efb.position-fixed{position:fixed!important}.efb.position-sticky{position:-webkit-sticky!important;position:sticky!important}.efb.top-0{top:0!important}.efb.top-50{top:50%!important}.efb.top-100{top:100%!important}.efb.bottom-0{bottom:0!important}.efb.bottom-50{bottom:50%!important}.efb.bottom-100{bottom:100%!important}.efb.start-0{left:0!important}.efb.start-50{left:50%!important}.efb.start-100{left:100%!important}.efb.end-0{right:0!important}.efb.end-50{right:50%!important}.efb.end-100{right:100%!important}.efb.translate-middle{transform:translate(-50%,-50%)!important}.efb.translate-middle-x{transform:translateX(-50%)!important}.efb.translate-middle-y{transform:translateY(-50%)!important}.efb.border{border:1px solid #dee2e6!important}.efb.border-0{border:0!important}.efb.border-top{border-top:1px solid #dee2e6!important}.efb.border-top-0{border-top:0!important}.efb.border-end{border-right:1px solid #dee2e6!important}.efb.border-end-0{border-right:0!important}.efb.border-bottom{border-bottom:1px solid #dee2e6!important}.efb.border-bottom-0{border-bottom:0!important}.efb.border-start{border-left:1px solid #dee2e6!important}.efb.border-start-0{border-left:0!important}.efb.border-primary{border-color:#0d6efd!important}.efb.border-secondary{border-color:#6c757d!important}.efb.border-success{border-color:#198754!important}.efb.border-info{border-color:#0dcaf0!important}.efb.border-warning{border-color:#ffc107!important}.efb.border-danger{border-color:#dc3545!important}.efb.border-light{border-color:#f8f9fa!important}.efb.border-dark{border-color:#212529!important}.efb.border-white{border-color:#fff!important}.efb.border-1{border-width:1px!important}.efb.border-2{border-width:2px!important}.efb.border-3{border-width:3px!important}.efb.border-4{border-width:4px!important}.efb.border-5{border-width:5px!important}.efb.w-25{width:25%!important}.efb.w-50{width:50%!important}.efb.w-75{width:75%!important}.efb.w-100{width:100%!important}.efb.w-auto{width:auto!important}.efb.mw-100{max-width:100%!important}.efb.vw-100{width:100vw!important}.efb.min-vw-100{min-width:100vw!important}.efb.h-25{height:25%!important}.efb.h-50{height:50%!important}.efb.h-75{height:75%!important}.efb.h-100{height:100%!important}.efb.h-auto{height:auto!important}.efb.mh-100{max-height:100%!important}.efb.vh-100{height:100vh!important}.efb.min-vh-100{min-height:100vh!important}.efb.flex-fill{flex:1 1 auto!important}.efb.flex-row{flex-direction:row!important}.efb.flex-column{flex-direction:column!important}.efb.flex-row-reverse{flex-direction:row-reverse!important}.efb.flex-column-reverse{flex-direction:column-reverse!important}.efb.flex-grow-0{flex-grow:0!important}.efb.flex-grow-1{flex-grow:1!important}.efb.flex-shrink-0{flex-shrink:0!important}.efb.flex-shrink-1{flex-shrink:1!important}.efb.flex-wrap{flex-wrap:wrap!important}.efb.flex-nowrap{flex-wrap:nowrap!important}.efb.flex-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.justify-content-start{justify-content:flex-start!important}.efb.justify-content-end{justify-content:flex-end!important}.efb.justify-content-center{justify-content:center!important}.efb.justify-content-between{justify-content:space-between!important}.efb.justify-content-around{justify-content:space-around!important}.efb.justify-content-evenly{justify-content:space-evenly!important}.efb.align-items-start{align-items:flex-start!important}.efb.align-items-end{align-items:flex-end!important}.efb.align-items-center{align-items:center!important}.efb.align-items-baseline{align-items:baseline!important}.efb.align-items-stretch{align-items:stretch!important}.efb.m-0{margin:0!important}.efb.m-1{margin:.25rem!important}.efb.m-2{margin:.5rem!important}.efb.m-3{margin:1rem!important}.efb.m-4{margin:1.5rem!important}.efb.m-5{margin:3rem!important}.efb.m-auto{margin:auto!important}.efb.mx-0{margin-right:0!important;margin-left:0!important}.efb.mx-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-0{margin-top:0!important;margin-bottom:0!important}.efb.my-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-0{margin-top:0!important}.efb.mt-1{margin-top:.25rem!important}.efb.mt-2{margin-top:.5rem!important}.efb.mt-3{margin-top:1rem!important}.efb.mt-4{margin-top:1.5rem!important}.efb.mt-5{margin-top:3rem!important}.efb.mt-auto{margin-top:auto!important}.efb.me-0{margin-right:0!important}.efb.me-1{margin-right:.25rem!important}.efb.me-2{margin-right:.5rem!important}.efb.me-3{margin-right:1rem!important}.efb.me-4{margin-right:1.5rem!important}.efb.me-5{margin-right:3rem!important}.efb.me-auto{margin-right:auto!important}.efb.mb-0{margin-bottom:0!important}.efb.mb-1{margin-bottom:.25rem!important}.efb.mb-2{margin-bottom:.5rem!important}.efb.mb-3{margin-bottom:1rem!important}.efb.mb-4{margin-bottom:1.5rem!important}.efb.mb-5{margin-bottom:3rem!important}.efb.mb-auto{margin-bottom:auto!important}.efb.ms-0{margin-left:0!important}.efb.ms-1{margin-left:.25rem!important}.efb.ms-2{margin-left:.5rem!important}.efb.ms-3{margin-left:1rem!important}.efb.ms-4{margin-left:1.5rem!important}.efb.ms-5{margin-left:3rem!important}.efb.ms-auto{margin-left:auto!important}.efb.p-0{padding:0!important}.efb.p-1{padding:.25rem!important}.efb.p-2{padding:.5rem!important}.efb.p-3{padding:1rem!important}.efb.p-4{padding:1.5rem!important}.efb.p-5{padding:3rem!important}.efb.px-0{padding-right:0!important;padding-left:0!important}.efb.px-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-0{padding-top:0!important;padding-bottom:0!important}.efb.py-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-0{padding-top:0!important}.efb.pt-1{padding-top:.25rem!important}.efb.pt-2{padding-top:.5rem!important}.efb.pt-3{padding-top:1rem!important}.efb.pt-4{padding-top:1.5rem!important}.efb.pt-5{padding-top:3rem!important}.efb.pe-0{padding-right:0!important}.efb.pe-1{padding-right:.25rem!important}.efb.pe-2{padding-right:.5rem!important}.efb.pe-3{padding-right:1rem!important}.efb.pe-4{padding-right:1.5rem!important}.efb.pe-5{padding-right:3rem!important}.efb.pb-0{padding-bottom:0!important}.efb.pb-1{padding-bottom:.25rem!important}.efb.pb-2{padding-bottom:.5rem!important}.efb.pb-3{padding-bottom:1rem!important}.efb.pb-4{padding-bottom:1.5rem!important}.efb.pb-5{padding-bottom:3rem!important}.efb.ps-0{padding-left:0!important}.efb.ps-1{padding-left:.25rem!important}.efb.ps-2{padding-left:.5rem!important}.efb.ps-3{padding-left:1rem!important}.efb.ps-4{padding-left:1.5rem!important}.efb.ps-5{padding-left:3rem!important}.efb.font-monospace{font-family:var(--bs-font-monospace)!important}.efb.fst-italic{font-style:italic!important}.efb.fst-normal{font-style:normal!important}.efb.fw-light{font-weight:300!important}.efb.fw-lighter{font-weight:lighter!important}.efb.fw-normal{font-weight:400!important}.efb.fw-bold{font-weight:700!important}.efb.fw-bolder{font-weight:bolder!important}.efb.lh-1{line-height:1!important}.efb.lh-sm{line-height:1.25!important}.efb.lh-base{line-height:1.5!important}.efb.lh-lg{line-height:2!important}.efb.text-start{text-align:left!important}.efb.text-end{text-align:right!important}.efb.text-center{text-align:center!important}.efb.text-decoration-none{text-decoration:none!important}.efb.text-decoration-underline{text-decoration:underline!important}.efb.text-decoration-line-through{text-decoration:line-through!important}.efb.text-lowercase{text-transform:lowercase!important}.efb.text-uppercase{text-transform:uppercase!important}.efb.text-capitalize{text-transform:capitalize!important}.efb.text-wrap{white-space:normal!important}.efb.text-nowrap{white-space:nowrap!important}.efb.text-break{word-wrap:break-word!important;word-break:break-word!important}.efb.text-primary{color:#0d6efd!important}.efb.text-secondary{color:#6c757d!important}.efb.text-success{color:#198754!important}.efb.text-info{color:#0dcaf0!important}.efb.text-warning{color:#ffc107!important}.efb.text-danger{color:#dc3545!important}.efb.text-light{color:#f8f9fa!important}.efb.text-dark{color:#212529!important}.efb.text-white{color:#fff!important}.efb.text-body{color:#212529!important}.efb.text-muted{color:#6c757d!important}.efb.text-black-50{color:rgba(0,0,0,.5)!important}.efb.text-white-50{color:rgba(255,255,255,.5)!important}.efb.text-reset{color:inherit!important}.efb.bg-primary{background-color:#0d6efd!important}.efb.bg-secondary{background-color:#6c757d!important}.efb.bg-success{background-color:#198754!important}.efb.bg-info{background-color:#0dcaf0!important}.efb.bg-warning{background-color:#ffc107!important}.efb.bg-danger{background-color:#dc3545!important}.efb.bg-light{background-color:#f8f9fa!important}.efb.bg-dark{background-color:#212529!important}.efb.bg-body{background-color:#fff!important}.efb.bg-white{background-color:#fff!important}.efb.bg-transparent{background-color:transparent!important}.efb.bg-gradient{background-image:var(--bs-gradient)!important}.efb.user-select-all{-webkit-user-select:all!important;-moz-user-select:all!important;user-select:all!important}.efb.user-select-auto{-webkit-user-select:auto!important;-moz-user-select:auto!important;user-select:auto!important}.efb.user-select-none{-webkit-user-select:none!important;-moz-user-select:none!important;user-select:none!important}.efb.pe-none{pointer-events:none!important}.efb.pe-auto{pointer-events:auto!important}.efb.rounded{border-radius:.25rem!important}.efb.rounded-0{border-radius:0!important}.efb.rounded-1{border-radius:.2rem!important}.efb.rounded-2{border-radius:.25rem!important}.efb.rounded-3{border-radius:.3rem!important}.efb.rounded-circle{border-radius:50%!important}.efb.rounded-pill{border-radius:50rem!important}.efb.rounded-top{border-top-left-radius:.25rem!important;border-top-right-radius:.25rem!important}.efb.rounded-end{border-top-right-radius:.25rem!important;border-bottom-right-radius:.25rem!important}.efb.rounded-bottom{border-bottom-right-radius:.25rem!important;border-bottom-left-radius:.25rem!important}.efb.rounded-start{border-bottom-left-radius:.25rem!important;border-top-left-radius:.25rem!important}.efb.visible{visibility:visible!important}.efb.invisible{visibility:hidden!important}@media (min-width:576px){.efb.float-sm-start{float:left!important}.efb.float-sm-end{float:right!important}.efb.float-sm-none{float:none!important}.efb.d-sm-inline{display:inline!important}.efb.d-sm-inline-block{display:inline-block!important}.efb.d-sm-block{display:block!important}.efb.d-sm-grid{display:grid!important}.efb.d-sm-flex{display:flex!important}.efb.d-sm-inline-flex{display:inline-flex!important}.efb.d-sm-none{display:none!important}.efb.flex-sm-fill{flex:1 1 auto!important}.efb.flex-sm-row{flex-direction:row!important}.efb.flex-sm-column{flex-direction:column!important}.efb.flex-sm-row-reverse{flex-direction:row-reverse!important}.efb.flex-sm-column-reverse{flex-direction:column-reverse!important}.efb.flex-sm-grow-0{flex-grow:0!important}.efb.flex-sm-grow-1{flex-grow:1!important}.efb.flex-sm-shrink-0{flex-shrink:0!important}.efb.flex-sm-shrink-1{flex-shrink:1!important}.efb.flex-sm-wrap{flex-wrap:wrap!important}.efb.flex-sm-nowrap{flex-wrap:nowrap!important}.efb.flex-sm-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.justify-content-sm-start{justify-content:flex-start!important}.efb.justify-content-sm-end{justify-content:flex-end!important}.efb.justify-content-sm-center{justify-content:center!important}.efb.justify-content-sm-between{justify-content:space-between!important}.efb.justify-content-sm-around{justify-content:space-around!important}.efb.justify-content-sm-evenly{justify-content:space-evenly!important}.efb.align-items-sm-start{align-items:flex-start!important}.efb.align-items-sm-end{align-items:flex-end!important}.efb.align-items-sm-center{align-items:center!important}.efb.align-items-sm-baseline{align-items:baseline!important}.efb.align-items-sm-stretch{align-items:stretch!important}.efb.m-sm-0{margin:0!important}.efb.m-sm-1{margin:.25rem!important}.efb.m-sm-2{margin:.5rem!important}.efb.m-sm-3{margin:1rem!important}.efb.m-sm-4{margin:1.5rem!important}.efb.m-sm-5{margin:3rem!important}.efb.m-sm-auto{margin:auto!important}.efb.mx-sm-0{margin-right:0!important;margin-left:0!important}.efb.mx-sm-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-sm-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-sm-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-sm-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-sm-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-sm-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-sm-0{margin-top:0!important;margin-bottom:0!important}.efb.my-sm-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-sm-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-sm-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-sm-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-sm-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-sm-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-sm-0{margin-top:0!important}.efb.mt-sm-1{margin-top:.25rem!important}.efb.mt-sm-2{margin-top:.5rem!important}.efb.mt-sm-3{margin-top:1rem!important}.efb.mt-sm-4{margin-top:1.5rem!important}.efb.mt-sm-5{margin-top:3rem!important}.efb.mt-sm-auto{margin-top:auto!important}.efb.me-sm-0{margin-right:0!important}.efb.me-sm-1{margin-right:.25rem!important}.efb.me-sm-2{margin-right:.5rem!important}.efb.me-sm-3{margin-right:1rem!important}.efb.me-sm-4{margin-right:1.5rem!important}.efb.me-sm-5{margin-right:3rem!important}.efb.me-sm-auto{margin-right:auto!important}.efb.mb-sm-0{margin-bottom:0!important}.efb.mb-sm-1{margin-bottom:.25rem!important}.efb.mb-sm-2{margin-bottom:.5rem!important}.efb.mb-sm-3{margin-bottom:1rem!important}.efb.mb-sm-4{margin-bottom:1.5rem!important}.efb.mb-sm-5{margin-bottom:3rem!important}.efb.mb-sm-auto{margin-bottom:auto!important}.efb.ms-sm-0{margin-left:0!important}.efb.ms-sm-1{margin-left:.25rem!important}.efb.ms-sm-2{margin-left:.5rem!important}.efb.ms-sm-3{margin-left:1rem!important}.efb.ms-sm-4{margin-left:1.5rem!important}.efb.ms-sm-5{margin-left:3rem!important}.efb.ms-sm-auto{margin-left:auto!important}.efb.p-sm-0{padding:0!important}.efb.p-sm-1{padding:.25rem!important}.efb.p-sm-2{padding:.5rem!important}.efb.p-sm-3{padding:1rem!important}.efb.p-sm-4{padding:1.5rem!important}.efb.p-sm-5{padding:3rem!important}.efb.px-sm-0{padding-right:0!important;padding-left:0!important}.efb.px-sm-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-sm-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-sm-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-sm-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-sm-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-sm-0{padding-top:0!important;padding-bottom:0!important}.efb.py-sm-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-sm-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-sm-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-sm-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-sm-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-sm-0{padding-top:0!important}.efb.pt-sm-1{padding-top:.25rem!important}.efb.pt-sm-2{padding-top:.5rem!important}.efb.pt-sm-3{padding-top:1rem!important}.efb.pt-sm-4{padding-top:1.5rem!important}.efb.pt-sm-5{padding-top:3rem!important}.efb.pe-sm-0{padding-right:0!important}.efb.pe-sm-1{padding-right:.25rem!important}.efb.pe-sm-2{padding-right:.5rem!important}.efb.pe-sm-3{padding-right:1rem!important}.efb.pe-sm-4{padding-right:1.5rem!important}.efb.pe-sm-5{padding-right:3rem!important}.efb.pb-sm-0{padding-bottom:0!important}.efb.pb-sm-1{padding-bottom:.25rem!important}.efb.pb-sm-2{padding-bottom:.5rem!important}.efb.pb-sm-3{padding-bottom:1rem!important}.efb.pb-sm-4{padding-bottom:1.5rem!important}.efb.pb-sm-5{padding-bottom:3rem!important}.efb.ps-sm-0{padding-left:0!important}.efb.ps-sm-1{padding-left:.25rem!important}.efb.ps-sm-2{padding-left:.5rem!important}.efb.ps-sm-3{padding-left:1rem!important}.efb.ps-sm-4{padding-left:1.5rem!important}.efb.ps-sm-5{padding-left:3rem!important}.efb.text-sm-start{text-align:left!important}.efb.text-sm-end{text-align:right!important}.efb.text-sm-center{text-align:center!important}}@media (min-width:768px){.efb.float-md-start{float:left!important}.efb.float-md-end{float:right!important}.efb.float-md-none{float:none!important}.efb.d-md-inline{display:inline!important}.efb.d-md-inline-block{display:inline-block!important}.efb.d-md-block{display:block!important}.efb.d-md-grid{display:grid!important}.efb.d-md-flex{display:flex!important}.efb.d-md-inline-flex{display:inline-flex!important}.efb.d-md-none{display:none!important}.efb.flex-md-fill{flex:1 1 auto!important}.efb.flex-md-row{flex-direction:row!important}.efb.flex-md-column{flex-direction:column!important}.efb.flex-md-row-reverse{flex-direction:row-reverse!important}.efb.flex-md-column-reverse{flex-direction:column-reverse!important}.efb.flex-md-grow-0{flex-grow:0!important}.efb.flex-md-grow-1{flex-grow:1!important}.efb.flex-md-shrink-0{flex-shrink:0!important}.efb.flex-md-shrink-1{flex-shrink:1!important}.efb.flex-md-wrap{flex-wrap:wrap!important}.efb.flex-md-nowrap{flex-wrap:nowrap!important}.efb.flex-md-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.justify-content-md-start{justify-content:flex-start!important}.efb.justify-content-md-end{justify-content:flex-end!important}.efb.justify-content-md-center{justify-content:center!important}.efb.justify-content-md-between{justify-content:space-between!important}.efb.justify-content-md-around{justify-content:space-around!important}.efb.justify-content-md-evenly{justify-content:space-evenly!important}.efb.align-items-md-start{align-items:flex-start!important}.efb.align-items-md-end{align-items:flex-end!important}.efb.align-items-md-center{align-items:center!important}.efb.align-items-md-baseline{align-items:baseline!important}.efb.align-items-md-stretch{align-items:stretch!important}.efb.order-md-first{order:-1!important}.efb.order-md-0{order:0!important}.efb.order-md-1{order:1!important}.efb.order-md-2{order:2!important}.efb.order-md-3{order:3!important}.efb.order-md-4{order:4!important}.efb.order-md-5{order:5!important}.efb.order-md-last{order:6!important}.efb.m-md-0{margin:0!important}.efb.m-md-1{margin:.25rem!important}.efb.m-md-2{margin:.5rem!important}.efb.m-md-3{margin:1rem!important}.efb.m-md-4{margin:1.5rem!important}.efb.m-md-5{margin:3rem!important}.efb.m-md-auto{margin:auto!important}.efb.mx-md-0{margin-right:0!important;margin-left:0!important}.efb.mx-md-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-md-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-md-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-md-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-md-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-md-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-md-0{margin-top:0!important;margin-bottom:0!important}.efb.my-md-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-md-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-md-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-md-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-md-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-md-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-md-0{margin-top:0!important}.efb.mt-md-1{margin-top:.25rem!important}.efb.mt-md-2{margin-top:.5rem!important}.efb.mt-md-3{margin-top:1rem!important}.efb.mt-md-4{margin-top:1.5rem!important}.efb.mt-md-5{margin-top:3rem!important}.efb.mt-md-auto{margin-top:auto!important}.efb.me-md-0{margin-right:0!important}.efb.me-md-1{margin-right:.25rem!important}.efb.me-md-2{margin-right:.5rem!important}.efb.me-md-3{margin-right:1rem!important}.efb.me-md-4{margin-right:1.5rem!important}.efb.me-md-5{margin-right:3rem!important}.efb.me-md-auto{margin-right:auto!important}.efb.mb-md-0{margin-bottom:0!important}.efb.mb-md-1{margin-bottom:.25rem!important}.efb.mb-md-2{margin-bottom:.5rem!important}.efb.mb-md-3{margin-bottom:1rem!important}.efb.mb-md-4{margin-bottom:1.5rem!important}.efb.mb-md-5{margin-bottom:3rem!important}.efb.mb-md-auto{margin-bottom:auto!important}.efb.ms-md-0{margin-left:0!important}.efb.ms-md-1{margin-left:.25rem!important}.efb.ms-md-2{margin-left:.5rem!important}.efb.ms-md-3{margin-left:1rem!important}.efb.ms-md-4{margin-left:1.5rem!important}.efb.ms-md-5{margin-left:3rem!important}.efb.ms-md-auto{margin-left:auto!important}.efb.p-md-0{padding:0!important}.efb.p-md-1{padding:.25rem!important}.efb.p-md-2{padding:.5rem!important}.efb.p-md-3{padding:1rem!important}.efb.p-md-4{padding:1.5rem!important}.efb.p-md-5{padding:3rem!important}.efb.px-md-0{padding-right:0!important;padding-left:0!important}.efb.px-md-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-md-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-md-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-md-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-md-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-md-0{padding-top:0!important;padding-bottom:0!important}.efb.py-md-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-md-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-md-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-md-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-md-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-md-0{padding-top:0!important}.efb.pt-md-1{padding-top:.25rem!important}.efb.pt-md-2{padding-top:.5rem!important}.efb.pt-md-3{padding-top:1rem!important}.efb.pt-md-4{padding-top:1.5rem!important}.efb.pt-md-5{padding-top:3rem!important}.efb.pe-md-0{padding-right:0!important}.efb.pe-md-1{padding-right:.25rem!important}.efb.pe-md-2{padding-right:.5rem!important}.efb.pe-md-3{padding-right:1rem!important}.efb.pe-md-4{padding-right:1.5rem!important}.efb.pe-md-5{padding-right:3rem!important}.efb.pb-md-0{padding-bottom:0!important}.efb.pb-md-1{padding-bottom:.25rem!important}.efb.pb-md-2{padding-bottom:.5rem!important}.efb.pb-md-3{padding-bottom:1rem!important}.efb.pb-md-4{padding-bottom:1.5rem!important}.efb.pb-md-5{padding-bottom:3rem!important}.efb.ps-md-0{padding-left:0!important}.efb.ps-md-1{padding-left:.25rem!important}.efb.ps-md-2{padding-left:.5rem!important}.efb.ps-md-3{padding-left:1rem!important}.efb.ps-md-4{padding-left:1.5rem!important}.efb.ps-md-5{padding-left:3rem!important}.efb.text-md-start{text-align:left!important}.efb.text-md-end{text-align:right!important}.efb.text-md-center{text-align:center!important}}@media (min-width:992px){.efb.float-lg-start{float:left!important}.efb.float-lg-end{float:right!important}.efb.float-lg-none{float:none!important}.efb.d-lg-inline{display:inline!important}.efb.d-lg-inline-block{display:inline-block!important}.efb.d-lg-block{display:block!important}.efb.d-lg-grid{display:grid!important}.efb.d-lg-flex{display:flex!important}.efb.d-lg-inline-flex{display:inline-flex!important}.efb.d-lg-none{display:none!important}.efb.flex-lg-fill{flex:1 1 auto!important}.efb.flex-lg-row{flex-direction:row!important}.efb.flex-lg-column{flex-direction:column!important}.efb.flex-lg-row-reverse{flex-direction:row-reverse!important}.efb.flex-lg-column-reverse{flex-direction:column-reverse!important}.efb.flex-lg-grow-0{flex-grow:0!important}.efb.flex-lg-grow-1{flex-grow:1!important}.efb.flex-lg-shrink-0{flex-shrink:0!important}.efb.flex-lg-shrink-1{flex-shrink:1!important}.efb.flex-lg-wrap{flex-wrap:wrap!important}.efb.flex-lg-nowrap{flex-wrap:nowrap!important}.efb.flex-lg-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.justify-content-lg-start{justify-content:flex-start!important}.efb.justify-content-lg-end{justify-content:flex-end!important}.efb.justify-content-lg-center{justify-content:center!important}.efb.justify-content-lg-between{justify-content:space-between!important}.efb.justify-content-lg-around{justify-content:space-around!important}.efb.justify-content-lg-evenly{justify-content:space-evenly!important}.efb.align-items-lg-start{align-items:flex-start!important}.efb.align-items-lg-end{align-items:flex-end!important}.efb.align-items-lg-center{align-items:center!important}.efb.align-items-lg-baseline{align-items:baseline!important}.efb.align-items-lg-stretch{align-items:stretch!important}.efb.order-lg-first{order:-1!important}.efb.order-lg-0{order:0!important}.efb.order-lg-1{order:1!important}.efb.order-lg-2{order:2!important}.efb.order-lg-3{order:3!important}.efb.order-lg-4{order:4!important}.efb.order-lg-5{order:5!important}.efb.order-lg-last{order:6!important}.efb.m-lg-0{margin:0!important}.efb.m-lg-1{margin:.25rem!important}.efb.m-lg-2{margin:.5rem!important}.efb.m-lg-3{margin:1rem!important}.efb.m-lg-4{margin:1.5rem!important}.efb.m-lg-5{margin:3rem!important}.efb.m-lg-auto{margin:auto!important}.efb.mx-lg-0{margin-right:0!important;margin-left:0!important}.efb.mx-lg-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-lg-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-lg-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-lg-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-lg-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-lg-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-lg-0{margin-top:0!important;margin-bottom:0!important}.efb.my-lg-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-lg-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-lg-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-lg-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-lg-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-lg-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-lg-0{margin-top:0!important}.efb.mt-lg-1{margin-top:.25rem!important}.efb.mt-lg-2{margin-top:.5rem!important}.efb.mt-lg-3{margin-top:1rem!important}.efb.mt-lg-4{margin-top:1.5rem!important}.efb.mt-lg-5{margin-top:3rem!important}.efb.mt-lg-auto{margin-top:auto!important}.efb.me-lg-0{margin-right:0!important}.efb.me-lg-1{margin-right:.25rem!important}.efb.me-lg-2{margin-right:.5rem!important}.efb.me-lg-3{margin-right:1rem!important}.efb.me-lg-4{margin-right:1.5rem!important}.efb.me-lg-5{margin-right:3rem!important}.efb.me-lg-auto{margin-right:auto!important}.efb.mb-lg-0{margin-bottom:0!important}.efb.mb-lg-1{margin-bottom:.25rem!important}.efb.mb-lg-2{margin-bottom:.5rem!important}.efb.mb-lg-3{margin-bottom:1rem!important}.efb.mb-lg-4{margin-bottom:1.5rem!important}.efb.mb-lg-5{margin-bottom:3rem!important}.efb.mb-lg-auto{margin-bottom:auto!important}.efb.ms-lg-0{margin-left:0!important}.efb.ms-lg-1{margin-left:.25rem!important}.efb.ms-lg-2{margin-left:.5rem!important}.efb.ms-lg-3{margin-left:1rem!important}.efb.ms-lg-4{margin-left:1.5rem!important}.efb.ms-lg-5{margin-left:3rem!important}.efb.ms-lg-auto{margin-left:auto!important}.efb.p-lg-0{padding:0!important}.efb.p-lg-1{padding:.25rem!important}.efb.p-lg-2{padding:.5rem!important}.efb.p-lg-3{padding:1rem!important}.efb.p-lg-4{padding:1.5rem!important}.efb.p-lg-5{padding:3rem!important}.efb.px-lg-0{padding-right:0!important;padding-left:0!important}.efb.px-lg-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-lg-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-lg-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-lg-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-lg-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-lg-0{padding-top:0!important;padding-bottom:0!important}.efb.py-lg-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-lg-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-lg-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-lg-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-lg-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-lg-0{padding-top:0!important}.efb.pt-lg-1{padding-top:.25rem!important}.efb.pt-lg-2{padding-top:.5rem!important}.efb.pt-lg-3{padding-top:1rem!important}.efb.pt-lg-4{padding-top:1.5rem!important}.efb.pt-lg-5{padding-top:3rem!important}.efb.pe-lg-0{padding-right:0!important}.efb.pe-lg-1{padding-right:.25rem!important}.efb.pe-lg-2{padding-right:.5rem!important}.efb.pe-lg-3{padding-right:1rem!important}.efb.pe-lg-4{padding-right:1.5rem!important}.efb.pe-lg-5{padding-right:3rem!important}.efb.pb-lg-0{padding-bottom:0!important}.efb.pb-lg-1{padding-bottom:.25rem!important}.efb.pb-lg-2{padding-bottom:.5rem!important}.efb.pb-lg-3{padding-bottom:1rem!important}.efb.pb-lg-4{padding-bottom:1.5rem!important}.efb.pb-lg-5{padding-bottom:3rem!important}.efb.ps-lg-0{padding-left:0!important}.efb.ps-lg-1{padding-left:.25rem!important}.efb.ps-lg-2{padding-left:.5rem!important}.efb.ps-lg-3{padding-left:1rem!important}.efb.ps-lg-4{padding-left:1.5rem!important}.efb.ps-lg-5{padding-left:3rem!important}.efb.text-lg-start{text-align:left!important}.efb.text-lg-end{text-align:right!important}.efb.text-lg-center{text-align:center!important}}@media (min-width:1200px){.efb.float-xl-start{float:left!important}.efb.float-xl-end{float:right!important}.efb.float-xl-none{float:none!important}.efb.d-xl-inline{display:inline!important}.efb.d-xl-inline-block{display:inline-block!important}.efb.d-xl-block{display:block!important}.efb.d-xl-grid{display:grid!important}.efb.d-xl-flex{display:flex!important}.efb.d-xl-inline-flex{display:inline-flex!important}.efb.d-xl-none{display:none!important}.efb.flex-xl-fill{flex:1 1 auto!important}.efb.flex-xl-row{flex-direction:row!important}.efb.flex-xl-column{flex-direction:column!important}.efb.flex-xl-row-reverse{flex-direction:row-reverse!important}.efb.flex-xl-column-reverse{flex-direction:column-reverse!important}.efb.flex-xl-grow-0{flex-grow:0!important}.efb.flex-xl-grow-1{flex-grow:1!important}.efb.flex-xl-shrink-0{flex-shrink:0!important}.efb.flex-xl-shrink-1{flex-shrink:1!important}.efb.flex-xl-wrap{flex-wrap:wrap!important}.efb.flex-xl-nowrap{flex-wrap:nowrap!important}.efb.flex-xl-wrap-reverse{flex-wrap:wrap-reverse!important}.efb.justify-content-xl-start{justify-content:flex-start!important}.efb.justify-content-xl-end{justify-content:flex-end!important}.efb.justify-content-xl-center{justify-content:center!important}.efb.justify-content-xl-between{justify-content:space-between!important}.efb.justify-content-xl-around{justify-content:space-around!important}.efb.justify-content-xl-evenly{justify-content:space-evenly!important}.efb.align-items-xl-start{align-items:flex-start!important}.efb.align-items-xl-end{align-items:flex-end!important}.efb.align-items-xl-center{align-items:center!important}.efb.align-items-xl-baseline{align-items:baseline!important}.efb.align-items-xl-stretch{align-items:stretch!important}.efb.order-xl-first{order:-1!important}.efb.order-xl-0{order:0!important}.efb.order-xl-1{order:1!important}.efb.order-xl-2{order:2!important}.efb.order-xl-3{order:3!important}.efb.order-xl-4{order:4!important}.efb.order-xl-5{order:5!important}.efb.order-xl-last{order:6!important}.efb.m-xl-0{margin:0!important}.efb.m-xl-1{margin:.25rem!important}.efb.m-xl-2{margin:.5rem!important}.efb.m-xl-3{margin:1rem!important}.efb.m-xl-4{margin:1.5rem!important}.efb.m-xl-5{margin:3rem!important}.efb.m-xl-auto{margin:auto!important}.efb.mx-xl-0{margin-right:0!important;margin-left:0!important}.efb.mx-xl-1{margin-right:.25rem!important;margin-left:.25rem!important}.efb.mx-xl-2{margin-right:.5rem!important;margin-left:.5rem!important}.efb.mx-xl-3{margin-right:1rem!important;margin-left:1rem!important}.efb.mx-xl-4{margin-right:1.5rem!important;margin-left:1.5rem!important}.efb.mx-xl-5{margin-right:3rem!important;margin-left:3rem!important}.efb.mx-xl-auto{margin-right:auto!important;margin-left:auto!important}.efb.my-xl-0{margin-top:0!important;margin-bottom:0!important}.efb.my-xl-1{margin-top:.25rem!important;margin-bottom:.25rem!important}.efb.my-xl-2{margin-top:.5rem!important;margin-bottom:.5rem!important}.efb.my-xl-3{margin-top:1rem!important;margin-bottom:1rem!important}.efb.my-xl-4{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.efb.my-xl-5{margin-top:3rem!important;margin-bottom:3rem!important}.efb.my-xl-auto{margin-top:auto!important;margin-bottom:auto!important}.efb.mt-xl-0{margin-top:0!important}.efb.mt-xl-1{margin-top:.25rem!important}.efb.mt-xl-2{margin-top:.5rem!important}.efb.mt-xl-3{margin-top:1rem!important}.efb.mt-xl-4{margin-top:1.5rem!important}.efb.mt-xl-5{margin-top:3rem!important}.efb.mt-xl-auto{margin-top:auto!important}.efb.me-xl-0{margin-right:0!important}.efb.me-xl-1{margin-right:.25rem!important}.efb.me-xl-2{margin-right:.5rem!important}.efb.me-xl-3{margin-right:1rem!important}.efb.me-xl-4{margin-right:1.5rem!important}.efb.me-xl-5{margin-right:3rem!important}.efb.me-xl-auto{margin-right:auto!important}.efb.mb-xl-0{margin-bottom:0!important}.efb.mb-xl-1{margin-bottom:.25rem!important}.efb.mb-xl-2{margin-bottom:.5rem!important}.efb.mb-xl-3{margin-bottom:1rem!important}.efb.mb-xl-4{margin-bottom:1.5rem!important}.efb.mb-xl-5{margin-bottom:3rem!important}.efb.mb-xl-auto{margin-bottom:auto!important}.efb.ms-xl-0{margin-left:0!important}.efb.ms-xl-1{margin-left:.25rem!important}.efb.ms-xl-2{margin-left:.5rem!important}.efb.ms-xl-3{margin-left:1rem!important}.efb.ms-xl-4{margin-left:1.5rem!important}.efb.ms-xl-5{margin-left:3rem!important}.efb.ms-xl-auto{margin-left:auto!important}.efb.p-xl-0{padding:0!important}.efb.p-xl-1{padding:.25rem!important}.efb.p-xl-2{padding:.5rem!important}.efb.p-xl-3{padding:1rem!important}.efb.p-xl-4{padding:1.5rem!important}.efb.p-xl-5{padding:3rem!important}.efb.px-xl-0{padding-right:0!important;padding-left:0!important}.efb.px-xl-1{padding-right:.25rem!important;padding-left:.25rem!important}.efb.px-xl-2{padding-right:.5rem!important;padding-left:.5rem!important}.efb.px-xl-3{padding-right:1rem!important;padding-left:1rem!important}.efb.px-xl-4{padding-right:1.5rem!important;padding-left:1.5rem!important}.efb.px-xl-5{padding-right:3rem!important;padding-left:3rem!important}.efb.py-xl-0{padding-top:0!important;padding-bottom:0!important}.efb.py-xl-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.efb.py-xl-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.efb.py-xl-3{padding-top:1rem!important;padding-bottom:1rem!important}.efb.py-xl-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.efb.py-xl-5{padding-top:3rem!important;padding-bottom:3rem!important}.efb.pt-xl-0{padding-top:0!important}.efb.pt-xl-1{padding-top:.25rem!important}.efb.pt-xl-2{padding-top:.5rem!important}.efb.pt-xl-3{padding-top:1rem!important}.efb.pt-xl-4{padding-top:1.5rem!important}.efb.pt-xl-5{padding-top:3rem!important}.efb.pe-xl-0{padding-right:0!important}.efb.pe-xl-1{padding-right:.25rem!important}.efb.pe-xl-2{padding-right:.5rem!important}.efb.pe-xl-3{padding-right:1rem!important}.efb.pe-xl-4{padding-right:1.5rem!important}.efb.pe-xl-5{padding-right:3rem!important}.efb.pb-xl-0{padding-bottom:0!important}.efb.pb-xl-1{padding-bottom:.25rem!important}.efb.pb-xl-2{padding-bottom:.5rem!important}.efb.pb-xl-3{padding-bottom:1rem!important}.efb.pb-xl-4{padding-bottom:1.5rem!important}.efb.pb-xl-5{padding-bottom:3rem!important}.efb.ps-xl-0{padding-left:0!important}.efb.ps-xl-1{padding-left:.25rem!important}.efb.ps-xl-2{padding-left:.5rem!important}.efb.ps-xl-3{padding-left:1rem!important}.efb.ps-xl-4{padding-left:1.5rem!important}.efb.ps-xl-5{padding-left:3rem!important}.efb.text-xl-start{text-align:left!important}.efb.text-xl-end{text-align:right!important}.efb.text-xl-center{text-align:center!important}}@media (min-width:1200px){}@media print{.efb.d-print-inline{display:inline!important}.efb.d-print-inline-block{display:inline-block!important}.efb.d-print-block{display:block!important}.efb.d-print-grid{display:grid!important}.efb.d-print-flex{display:flex!important}.efb.d-print-inline-flex{display:inline-flex!important}.efb.d-print-none{display:none!important}}label input[type="radio"].efb{visibility:hidden}
			</style>
			';
	}
	public function loading_icon_public_efb($classes,$pw ,$fil){
				 $svg = '
				 <style>
				 .efb.circlecontainer {
					display: flex;
					justify-content: space-around;
					height: 15px;
					width: 120px;
					align-items: flex-end;
				}
				.efb.circle {
					background-color: #abb8c3;
					border-radius: 50%;
					width: 15px;
					height: 15px;
					animation: pulseefb 1s linear infinite;
				}
				.efb.delay1 {
					animation-delay: 0.3s;
				}
				.sefb.delay2 {
					animation-delay: 0.6s;
				}
				@keyframes pulseefb {
					0% { transform: scale(1); }
					50% { transform: scale(0.6); }
					100% { transform: scale(1); }
				}
				 </style>
					<div class="efb circlecontainer m-0 p-0 align-bottom">
						<div class="efb circle"></div>
						<div class="efb circle delay1"></div>
						<div class="efb circle delay2"></div>
					</div>
				 ';
		return '
		<h3  class="efb fs-5" style="justify-content: center; align-items: center;  text-align: center;">'. $fil.' <br><span class="efb  text-center fs-7">'.$pw.'</span> </h3>
		';
	}

	public function cache_cleaner_Efb($page_id, $plugins = null) {
		$page_id = intval($page_id);

		if ($page_id <= 0) {
			return false;
		}

		$page_type = get_post_type($page_id);
		$page_url = get_permalink($page_id);
		$page_post = get_post($page_id);

		static $cache_handlers = null;

		if ($cache_handlers === null) {
			$cache_handlers = array(
				'litespeed-cache' => array(
					'check' => function() { return (defined('LSCWP_V') || defined('LSCWP_BASENAME')); },
					'clear' => function($p) { do_action('litespeed_purge_post', $p); }
				),
				'wp-rocket' => array(
					'check' => function() { return function_exists('rocket_clean_post'); },
					'clear' => function($p) { rocket_clean_post($p); }
				),
				'w3-total-cache' => array(
					'check' => function() { return function_exists('w3tc_flush_post'); },
					'clear' => function($p) { w3tc_flush_post($p); }
				),
				'wp-super-cache' => array(
					'check' => function() { return function_exists('wp_cache_post_change'); },
					'clear' => function($p) { $GLOBALS['super_cache_enabled'] = 1; wp_cache_post_change($p); }
				),
				'jetpack' => array(
					'check' => function() { return (class_exists('Jetpack') || defined('JETPACK__VERSION')); },
					'clear' => function($p) { return null; }
				),
				'jetpack-boost' => array(
					'check' => function() { return (class_exists('Automattic\\Jetpack_Boost\\Jetpack_Boost') || defined('JETPACK_BOOST_VERSION')); },
					'clear' => function($p) { return null; }
				),
				'wp-optimize' => array(
					'check' => function() { return class_exists('WPO_Page_Cache'); },
					'clear' => function($p) {
						try {
							if (method_exists('\WPO_Page_Cache', 'delete_single_post_cache')) {
								\WPO_Page_Cache::delete_single_post_cache($p);
							} else {
								do_action('wpo_purge_all');
							}
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'wp-fastest-cache' => array(
					'check' => function() { return function_exists('wpfc_clear_post_cache_by_id'); },
					'clear' => function($p) { wpfc_clear_post_cache_by_id($p); }
				),
				'hummingbird-performance' => array(
					'check' => function() { return has_action('wphb_clear_page_cache'); },
					'clear' => function($p) { do_action('wphb_clear_page_cache', $p); }
				),
				'sg-optimizer' => array(
					'check' => function() { return (function_exists('sg_cachepress_purge_cache') || function_exists('sg_cachepress_purge_post') || class_exists('SiteGround_Optimizer\Supercacher\Supercacher')); },
					'clear' => function($p) {
						try {
							if (function_exists('sg_cachepress_purge_cache')) {
								sg_cachepress_purge_cache(get_permalink($p));
							} elseif (function_exists('sg_cachepress_purge_post')) {
								sg_cachepress_purge_post($p);
							} elseif (class_exists('SiteGround_Optimizer\Supercacher\Supercacher')) {
								\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
							}
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'breeze' => array(
					'check' => function() { return has_action('breeze_clear_all_cache'); },
					'clear' => function($p) { do_action('breeze_clear_all_cache'); }
				),
				'cache-enabler' => array(
					'check' => function() { return class_exists('Cache_Enabler'); },
					'clear' => function($p) {
						try {
							if (method_exists('\Cache_Enabler', 'clear_page_cache_by_post_id')) {
								\Cache_Enabler::clear_page_cache_by_post_id($p);
							} elseif (method_exists('\Cache_Enabler', 'clear_cache')) {
								\Cache_Enabler::clear_cache();
							} elseif (method_exists('\Cache_Enabler', 'clear_total_cache')) {
								\Cache_Enabler::clear_total_cache();
							}
						} catch (\Exception $e) {

						} catch (\Error $e) {

						}
					}
				),
				'swift-performance' => array(
					'check' => function() { return (class_exists('Swift_Performance_Cache') && method_exists('Swift_Performance_Cache', 'clear_all_cache')); },
					'clear' => function($p) {
						try {
							\Swift_Performance_Cache::clear_all_cache();
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'comet-cache' => array(
					'check' => function() { return (class_exists('comet_cache') || function_exists('comet_cache_clear_cache')); },
					'clear' => function($p) {
						try {
							if (class_exists('comet_cache') && method_exists('comet_cache', 'clear')) {
								\comet_cache::clear();
							} elseif (function_exists('comet_cache_clear_cache')) {
								comet_cache_clear_cache();
							}
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'autoptimize' => array(
					'check' => function() { return class_exists('autoptimizeCache'); },
					'clear' => function($p) {
						try {

							add_filter('autoptimize_filter_js_exclude', function($exclude) {
								$efb_excludes = 'jquery.min-efb.js,core-efb.js';
								return $exclude ? $exclude . ',' . $efb_excludes : $efb_excludes;
							});
							\autoptimizeCache::clearall();
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'powered-cache' => array(
					'check' => function() { return function_exists('powered_cache_flush_page_cache'); },
					'clear' => function($p) { powered_cache_flush_page_cache($p); }
				),
				'hyper-cache' => array(
					'check' => function() { return function_exists('hyper_cache_flush'); },
					'clear' => function($p) { hyper_cache_flush(); }
				),
				'big-scoots-cache' => array(
					'check' => function() { return (class_exists('BigScoots_Cache') && method_exists('BigScoots_Cache', 'clear_cache')); },
					'clear' => function($p) {
						try {
							\BigScoots_Cache::clear_cache((int) $p);
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'speedycache' => array(
					'check' => function() { return class_exists('SpeedyCache\\Delete'); },
					'clear' => function($p) {
						try {
							\SpeedyCache\Delete::cache($p);
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'clear-cache-for-widgets' => array(
					'check' => function() { return function_exists('ccfm_clear_cache_for_me'); },
					'clear' => function($p) { ccfm_clear_cache_for_me(); }
				),
				'atec-cache-apcu' => array(
					'check' => function() { return (function_exists('atec_wpca_delete_page') && function_exists('atec_wpca_settings') && defined('ATEC_WPCA_APCU')); },
					'clear' => function($p) {
						try {
							$settings = atec_wpca_settings('cache');
							if ($settings) {
								$suffix = (isset($settings['salt']) ? $settings['salt'] : '') . '_p';
								atec_wpca_delete_page($suffix, $p);
							}
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'atec-cache-info' => array(
					'check' => function() { return (function_exists('atec_wpca_delete_page') && function_exists('atec_wpca_settings') && !defined('ATEC_WPCA_APCU')); },
					'clear' => function($p) {
						try {
							$settings = atec_wpca_settings('cache');
							if ($settings) {
								$suffix = (isset($settings['salt']) ? $settings['salt'] : '') . '_p';
								atec_wpca_delete_page($suffix, $p);
							}
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'wpspeed' => array(
					'check' => function() { return class_exists('WPSpeed\\Platform\\Cache'); },
					'clear' => function($p) {
						try {
							\WPSpeed\Platform\Cache::deleteCache();
						} catch (\Exception $e) {
						} catch (\Error $e) {
						}
					}
				),
				'flying-press' => array(
					'check' => function() { return (function_exists('flying_press_purge_post') || has_action('flying_press_purge_everything')); },
					'clear' => function($p) {
						if (function_exists('flying_press_purge_post')) {
							flying_press_purge_post($p);
						} elseif (has_action('flying_press_purge_everything')) {
							do_action('flying_press_purge_everything');
						}
					}
				),
			);
		}

		$dynamic_handlers = array(
			'wp-cloudflare-page-cache' => array(
				'check' => class_exists('SW_CLOUDFLARE_PAGECACHE'),
				'clear' => $page_url ? function($p) use ($page_url) { do_action('swcfpc_purge_cache', array($page_url)); } : null
			),
			'nitropack' => array(
				'check' => (function_exists('nitropack_sdk_purge') || function_exists('nitropack_clean_post_cache')),
				'clear' => function($p) use ($page_url, $page_post) {
					if (function_exists('nitropack_sdk_purge') && $page_url) {
						nitropack_sdk_purge($page_url);
					} elseif (function_exists('nitropack_clean_post_cache') && $page_post) {
						nitropack_clean_post_cache($page_post);
					}
				}
			),
			'wp-rest-cache' => array(
				'check' => class_exists('\\WP_REST_Cache_Plugin\\Includes\\Caching\\Caching'),
				'clear' => function($p) use ($page_type) {
					try {
						\WP_REST_Cache_Plugin\Includes\Caching\Caching::get_instance()->delete_related_caches($p, $page_type);
					} catch (\Exception $e) {
					} catch (\Error $e) {
					}
				}
			),
		);

		$active_plugins = array();

		if ($plugins !== null) {
			$cache_plugins = json_decode($plugins);
			if (!empty($cache_plugins) && is_array($cache_plugins)) {
				foreach ($cache_plugins as $plugin) {
					if (isset($plugin->slug)) {
						$active_plugins[$plugin->slug] = true;
					}
				}
			}
		}

		if (empty($active_plugins)) {

			foreach ($cache_handlers as $slug => $handler) {
				if ($handler['check']()) {
					$active_plugins[$slug] = true;
				}
			}

			foreach ($dynamic_handlers as $slug => $handler) {
				if ($handler['check'] && $handler['clear']) {
					$active_plugins[$slug] = true;
				}
			}
		}

		if (empty($active_plugins)) {

			return false;
		}

		$cleared = 0;
		$failed = 0;

		foreach ($active_plugins as $slug => $val) {

			if (isset($cache_handlers[$slug]) && is_callable($cache_handlers[$slug]['check']) && $cache_handlers[$slug]['check']()) {
				if (is_callable($cache_handlers[$slug]['clear'])) {
					try {
						$result = $cache_handlers[$slug]['clear']($page_id);
						if ($result === null) {

							$failed++;
						} else {
							$cleared++;
						}
					} catch (Exception $e) {
						$failed++;
					}
				} else {
					$failed++;
				}
			}

			elseif (isset($dynamic_handlers[$slug]) && $dynamic_handlers[$slug]['check'] && is_callable($dynamic_handlers[$slug]['clear'])) {
				try {
					$result = $dynamic_handlers[$slug]['clear']($page_id);
					if ($result === null) {
						$failed++;
					} else {
						$cleared++;
					}
				} catch (Exception $e) {
					$failed++;
				}
			} else {
				$failed++;
			}
		}

		if ($cleared > 0) {

			return true;
		}

		return null;
	}

	public function comper_version_efb($v){
		if(version_compare(EMSFB_PLUGIN_VERSION,$v)!=0 ){
			$efbFunction =  get_efbFunction();
			$efbFunction->setting_version_efb_update('null' ,$this->pro_efb );
		}
	}
	public function form_preview_efb(){

		if (  check_ajax_referer('wp_rest', 'nonce') != 1) {
			die();
		}
		$new_page_id = 0;

		$current_user = get_current_user_id();
		if(!isset($_POST['id'])) return;
		$id = sanitize_text_field($_POST['id']);

		$r = 'preview@'. str_replace([' ', '[', ']', '='], '', $id);
		$r = strtolower($r);

		$v = get_option($r);

		if($v != false ){
			 wp_update_post(array(
				'ID'             => $v,
				'post_content'   => ' '.$id.' ',
				'post_status'    => 'draft',
			));
			$new_page_id =$v;
		}else{

			$new_page_id = wp_insert_post(array(
				'post_title'     => esc_html__('Form Preview', 'easy-form-builder'),
				'post_type'      => 'page',
				'post_name'      => 'easy-form-builder-preview',
				'post_content'   => ' '.$id.' ',
				'post_status'    => 'draft',
				'post_author'    => $current_user,
			));
			$v ='preview@'. str_replace([' ', '[', ']', '='], '', $id);
			$v = strtolower($v);
			update_option( $v, $new_page_id);

			$args = [$new_page_id,$v];

			wp_schedule_single_event(time() + 86400, 'delete_preview_page_efb', array($args));
		}
		$preview_url = get_preview_post_link($new_page_id);
		$response = array( 'success' => true, 'data' => $preview_url , 'page_id' => $new_page_id);
		wp_send_json_success($response, 200);
	}
	function delete_preview_page_efb($args) {
		$page_id = $args[0];
		$id = $args[1];
		$post = get_post($page_id);
		if (isset($post) && $post->post_status == 'draft') wp_delete_post($page_id, true);
		delete_option($id);
	}
	function genrate_sacure_code_admin_email($track){
		function g($track , $key){
			return md5($track.$key);
		}

		if(isset($this->setting->email_key)){
		}else{

			$rand = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);

			$this->setting->email_key = $rand;
			$setting = json_encode($this->setting,JSON_UNESCAPED_UNICODE);
			$setting= str_replace('"', '\"', $setting);
			if(empty($this->db)){
				global $wpdb;
				$this->db = $wpdb;
			}
			$table_name = $this->db->prefix . 'emsfb_setting';
			$email =$this->setting->emailSupporter;
			$this->db->insert(
				$table_name,
				[
					'setting' => $setting,
					'edit_by' => 0,
					'date'    => wp_date('Y-m-d H:i:s'),
					'email'   => $email
				]
			);
			set_transient('emsfb_settings_transient', $setting, 1440);
			update_option('emsfb_settings', $setting);
		}
		return g($track , $this->setting->email_key);
	}

	private function sanitize_value_efb($value, $key) {
		switch ($key) {
			case 'email':
				return sanitize_email($value);
			case 'url':
				return sanitize_url($value);
			default:
				return sanitize_text_field($value) ;
		}
	}

	private function filter_and_sanitize_attributes_efb($item, $allowed_attributes_efb) {
		return array_filter($item, function($key) use ($allowed_attributes_efb) {
			return isset($allowed_attributes_efb[$key]);
		}, ARRAY_FILTER_USE_KEY);
	}

	private function filter_attributes_by_type_efb($data,$type) {
		static $allowed_attributes_efb = ['id_' => true, 'name' => true, 'id_ob' => true, 'amount' => true, 'type' => true, 'value' => true, 'session' => true ,'form_id'=>true];
		static $attribute_map_efb = [
			'email' => true, 'date' => true, 'url' => true, 'mobile' => true, 'radio' => true,
			'payRadio' => ['price' => true], 'chlRadio' => ['src' => true, 'sub_value' => true],
			'chlCheckBox'=>['qty'=>true],
			'imgRadio' => ['src' => true, 'sub_value' => true], 'switch' => true,
			'option' => ['price' => true,'qty'=>true], 'r_matrix' => ['label' => true],'postalcode'=>true,
			'multiselect' => true, 'select' => true, 'paySelect' => true,
			'stateProvince' => true, 'statePro' => true, 'conturyList' => true,
			'country' => true, 'city' => true, 'cityList' => true, 'sample' => true,
			'persiapay' => ['amount' => true],'ardate'=>true,'pdate'=>true ,'textarea'=>true,
			'payment' => ['amount' => true], 'file' => ['url' => true], 'address_line'=>true,
			'dadfile' => ['url' => true], 'esign' => true, 'maps' => true,
			'color' => true, 'range' => true, 'number' => true, 'prcfld' => true,
			'checkbox' => true, 'table_matrix' => true, 'trmCheckbox' => true,
			'ttlprc' => true, 'smartcr' => true, 'pointr5' => true,'tel'=>true,
			'pointr10' => true, 'zarinPal' => true, 'stripe' => ['amount' => true],
			'yesNo' => true, 'payMultiselect' => true, 'rating' => true, 'text'=>true, 'password'=>true
		];

			if (isset($attribute_map_efb[$type])) {
				$allowed_attributes_efb_type = is_array($attribute_map_efb[$type]) ?  array_replace($allowed_attributes_efb, $attribute_map_efb[$type]) :$allowed_attributes_efb;

				$sanitized_item = $this->filter_and_sanitize_attributes_efb($data, $allowed_attributes_efb_type);
				foreach ($sanitized_item as $key => $value) {
					if ($key !== 'value') {
						$sanitized_item[$key] = $this->sanitize_value_efb($value, $key);
					}
				}
				return $sanitized_item;
			}

		return false;
	}

	public function fun_present_others_action_efb($state, $username, $sid,$fid){

		$this->efbFunction = get_efbFunction();
		$s_sid = $this->efbFunction->efb_code_validate_select($sid, $fid);
		$texts =['sxnlex','uraatn'];
		$lan =$this->efbFunction->text_efb($texts);
		function Js_() {
			return "<script>if (window.location.href.indexOf('?') !== -1) {
			var newUrl = window.location.href.split('?')[0];
			window.history.pushState({}, document.title, newUrl);
		    }</script>";
		}
		function Js_setpassword(){
			return "<script>
			const efb_url = '".get_rest_url(null)."Emsfb/v1/forms/recovery/efb_set_password';
			const efb_nonce = '".wp_create_nonce('wp_rest')."';

			const eyeOpenSvg = '<path d=\"M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z\"/><path d=\"M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0\"/>';
			const eyeClosedSvg = '<path d=\"M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z\"/><path d=\"M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829\"/><path d=\"M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z\"/>';

			document.addEventListener('click', function(e) {
				if (e.target && (e.target.id === 'togglePasswordEfb' || e.target.closest('#togglePasswordEfb'))) {
					const passwordInput = document.getElementById('passwordefb');
					const icon = document.getElementById('togglePasswordIcon');
					if (passwordInput.type === 'password') {
						passwordInput.type = 'text';
						icon.innerHTML = eyeClosedSvg;
					} else {
						passwordInput.type = 'password';
						icon.innerHTML = eyeOpenSvg;
					}
				}
			});

			document.addEventListener('click', function(e) {
				if (e.target) {
					if (e.target.id=='submitnewpass' || e.target.closest('#submitnewpass')) {
						let password = document.getElementById('passwordefb').value;
						let alert = document.getElementById('alert-efb');
						if (password.length < 8) {
							alert.innerHTML = '".esc_html__("The Password Must Contain At Least 8 Characters!" , 'easy-form-builder')."';
							return;
						}else if (!password.match(/[a-z]/g)) {
							alert.innerHTML = '".esc_html__("The Password Must Contain At Least 1 Lowercase Letter!" , 'easy-form-builder')."';
							return;
						}else if (!password.match(/[A-Z]/g)) {
							alert.innerHTML = '".esc_html__("The Password Must Contain At Least 1 Capital Letter!" , 'easy-form-builder').
							"';
							return;
						}else if (!password.match(/[0-9]/g)) {
							alert.innerHTML = '".esc_html__("The Password Must Contain At Least 1 Number!" , 'easy-form-builder')."';
							return;
						}else{
							alert.innerHTML = '';
						}
						const st = document.getElementById('stefb').value;
						const fid = document.getElementById('fidfb').value;
						let body_efb_rpass = document.getElementById('body_efb_rpass');

						const data = {
							action: 'efb_set_password',
							password: password,
							st: st,
							fid: fid
						};

						body_efb_rpass.innerHTML = '<div class=\"efb text-center p-4\"><i class=\"efb bi-hourglass-split fs-1 text-primary\"></i><p class=\"efb mt-2\">".esc_html__("Please Wait" , 'easy-form-builder')."</p></div>';
						fetch(efb_url, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': efb_nonce,
							},
							body: JSON.stringify(data)
						}) .then(response => response.json())
						.then(data => {
							if (data.success) {
								body_efb_rpass.innerHTML = data.data;
							} else {
								body_efb_rpass.innerHTML = data.data;
							}
						});
					}
				}
			});
			</script>";
		}
		function create_content_setpassword($st,$fid){
			$html = '<div class="efb card efb my-3 efb p-4" id="body_efb_rpass" style="max-width: 400px; margin: 0 auto;">

					<!-- Header with icon -->
					<div class="efb text-center mb-4">
						<div class="efb mb-3">
							<i class="efb bi-key-fill fs-1 text-primary"></i>
						</div>
						<h5 class="efb card-title efb text-center mb-1">'.esc_html__("Create New Password", "easy-form-builder").'</h5>
						<p class="efb text-muted fs-7">'.esc_html__("Create a secure password for your account", "easy-form-builder").'</p>
					</div>

					<!-- Password field with eye toggle -->
					<div class="efb mb-3 efb">
						<label for="passwordefb" class="efb form-label fs-7 m-0">
							<i class="efb bi-lock me-1"></i>'.esc_html__("New Password", "easy-form-builder").'
						</label>
						<div class="efb input-group">
							<input type="password" class="efb form-control border border-dark rounded-start-2 border-end-0" id="passwordefb" name="password" placeholder="'.esc_html__("Enter your new password", "easy-form-builder").'">
							<button class="efb btn btn-outline-dark border-start-0 rounded-end-2" type="button" id="togglePasswordEfb">
								<svg id="togglePasswordIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
									<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
									<path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
								</svg>
							</button>
						</div>
					</div>

					<input type="hidden" name="stefb" id="stefb" value="'.$st.'">
					<input type="hidden" name="fidfb" id="fidfb" value="'.$fid.'">

					<p class="efb text-danger efb fs-7" id="alert-efb"></p>

					<button type="button" class="efb btn efb w-100 rounded-2 btn-dark h-d-efb" id="submitnewpass">
						<i class="efb bi-check-lg me-2"></i>'.esc_html__("Set Password", "easy-form-builder").'
					</button>
				</div>
					' .Js_() . Js_setpassword() ;
				return $html;

		}
		function recovery_($lan,$username,$st,$fid){
			return create_content_setpassword($st,$fid);
		}

		function register_( $lan,$username){
			$user = get_user_by('login', $username);

			if ($user) {

				$user_id = $user->ID;
				$user->set_role('subscriber');
			}
			return '<p text-align: center;">'.$lan['uraatn'].'</p>' . Js_();
		}
		if ($s_sid !=1 || $sid==null){
			$this->efbFunction->send_email_noti_sid_plugins_efb('userActionEvent');
			return '<p style="color:#ff4b93;text-align: center;">'.$lan['sxnlex'].'</p>'.Js_();
		}
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . 'emsfb_temp_links';
		$sql = $this->db->prepare("SELECT * FROM $table_name WHERE code = %s", $sid);
		$row = $this->db->get_row($sql);
		if ($row) {
			$created_at = strtotime($row->created_at);
			$now = strtotime(current_time('mysql'));
			$diff = $now - $created_at;
			if ($diff < 86400 && $row->username == $username && $row->status_ == $state) {
				$st = $state == 1 ? 'register' : 'recovery';
				if($state==1){
					$this->efbFunction->efb_code_validate_update($sid, $st, 0);
					return register_( $lan,$username);
				}else if($state==0){
					$this->public_scripts_and_css_head('css');
					return recovery_($lan,$username,$sid,$fid);
				}
				return;
			}
		}

			$m= esc_html__('error', 'easy-form-builder') . ': R404';
			return '<p style="color:#ff4b93;text-align: center;">'.$m.'</p>';

	}


	/**
	 * Prepare data for registration verification or password recovery email
	 *
	 * Creates verification code, stores in database, and returns data for email sending.
	 * The actual email content is generated by email_template_efb in class-email-handler.php
	 *
	 * @param int    $userid   User ID
	 * @param string $username Username
	 * @param string $email    User email
	 * @param int    $fid      Form ID
	 * @param string $type_    Type: 'register' or 'recovery'
	 * @param int    $page_id  Page ID for the verification/recovery page
	 * @return array Array with 'url' (verification/recovery URL) and 'username'
	 */
	public function fun_get_content_email_register_recovery_efb($userid, $username, $email, $fid, $type_, $page_id) {
		if (empty($this->db)) {
			global $wpdb;
			$this->db = $wpdb;
		}

		$table_name = $this->db->prefix . 'emsfb_temp_links';
		$ip = !empty($this->ip) ? $this->ip : $this->get_ip_address();

		$sid = $this->efbFunction->efb_code_validate_create($this->id, 0, $type_, 0);
		$status_ = ($type_ === 'register') ? 1 : 0;

		$data = [
			'username'   => $username,
			'created_at' => current_time('mysql'),
			'code'       => $sid,
			'ip_address' => $ip,
			'status_'    => $status_,
		];

		$this->db->insert($table_name, $data, ['%s', '%s', '%s', '%s', '%d']);

		$url = add_query_arg([
			'sc'       => $sid,
			'state'    => $status_,
			'username' => rawurlencode($username),
			'fid'      => $fid,
		], get_permalink($page_id));

		return [
			'url'      => $url,
			'username' => $username,
			'type'     => $type_,
		];
	}

	public function set_password_efb_api(){

		$data = json_decode(file_get_contents('php://input'), true);

		$st = sanitize_text_field($data['st']);
		$fid = sanitize_text_field($data['fid']);
		$this->efbFunction = get_efbFunction();
		 $s_sid = $this->efbFunction->efb_code_validate_select($st, $fid);

		$password = sanitize_text_field($data['password']);
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . 'emsfb_temp_links';
		$sql = $this->db->prepare("SELECT * FROM $table_name WHERE code = %s", $st);
		$row = $this->db->get_row($sql);
		if ($row) {
			$created_at = strtotime($row->created_at);
			$now = strtotime(current_time('mysql'));
			$diff = $now - $created_at;
			if ($diff < 86400 && $row->status_ == 0) {
				$user = get_user_by('login', $row->username);
				if ($user) {
					wp_set_password($password, $user->ID);
					$this->efbFunction->efb_code_validate_update($st, 'recovery', 1);
					return new WP_REST_Response(array('success' => true, 'data' => esc_html__('Password has been changed successfully!', 'easy-form-builder')));
				}
			}
		}
		return new WP_REST_Response(array('success' => false, 'data' => esc_html__('Error! Please try again later.', 'easy-form-builder')));
	}

	public function ColorNameToHexEfbOfElEfb($v, $n) {

		$color_map = [
			"primary" => '#0d6efd',
			"success" => '#198754',
			"secondary" => '#6c757d',
			"danger" => '#ff455f',
			"warning" => '#e9c31a',
			"info" => '#31d2f2',
			"light" => '#fbfbfb',
			"darkb" => '#202a8d',
			"labelEfb" => '#898aa9',
			"d" => '#83859f',
			"pinkEfb" => '#ff4b93',
			"white" => '#ffffff',
			"dark" => '#212529',
			"muted" => '#777777'
		];

		$id_map = [
			"label" => "style_label_color",
			"description" => "style_message_text_color",
			"el" => "style_el_text_color",
			"btn" => "style_btn_text_color",
			"icon" => "style_icon_color",
			"border" => "style_border_color"
		];

		$id = isset($id_map[$n]) ? $id_map[$n] : null;

		if (isset($color_map[$v])) {
			$r = $color_map[$v];
		} else {
			$len = strlen('colorDEfb-');
			if (strpos($v, 'colorDEfb') !== false) {
				$r = "#" . substr($v, $len);
			} else {
				$r = '';
			}
		}
		return $r;
	}

	private function ajax_object_efm_efb($ar_core ,$values ,$typeOfForm ,$state ,$lang,$poster ,$img ,$pro ,$page_builder ,$is_user ,$username,$lanText){
		$page_id = get_the_ID();
		$json_settings= get_setting_Emsfb('pub')[0];
		$pub_settings = get_setting_Emsfb('pub')[1];
		$ar_core = array_merge($ar_core , array(
			'ajax_value_forms' =>$this->value_forms,
			'ajax_value' =>$values,
			'type' => $typeOfForm,
			'id' => $this->id,
			'form_id' => $this->id,
			'sid' => $ar_core['sid'],
			'state' => $state,
			'language' => $lang,
			'form_setting' =>  $this->pub_stting,

			'poster'=> $poster,
			'rtl' => is_rtl(),
			'text' =>$lanText ,
			'pro'=> $pro ? 1 : 0,
			'wp_lan'=>get_locale(),
			'location'=> "",
			'v_efb'=>EMSFB_PLUGIN_VERSION,

			'images' => $img,
			'zone_area'=>CDN_ZONE_AREA,
			'root_url'=>home_url('/'),
			'rest_url'=>get_rest_url(null),
			'page_id'=> $page_id,
			'page_builder'=>$page_builder,
			'is_user'=> $is_user,
			'user_name' => $username,
			'admin_sc' => isset($_GET['sc']) ? sanitize_text_field(wp_unslash($_GET['sc'])) : '',
			'nonce' => wp_create_nonce('wp_rest'),

			'respPrimary' => $pub_settings['respPrimary'] ?? '#3644d2',
			'respPrimaryDark' => $pub_settings['respPrimaryDark'] ?? '#202a8d',
			'respAccent' => $pub_settings['respAccent'] ?? '#ffc107',
			'respText' => $pub_settings['respText'] ?? '#1a1a2e',
			'respTextMuted' => $pub_settings['respTextMuted'] ?? '#657096',
			'respBgCard' => $pub_settings['respBgCard'] ?? '#ffffff',
			'respBgMeta' => $pub_settings['respBgMeta'] ?? '#f6f7fb',
			'respBgTrack' => $pub_settings['respBgTrack'] ?? '#ffffff',
			'respBgResp' => $pub_settings['respBgResp'] ?? '#f8f9fd',
			'respBgEditor' => $pub_settings['respBgEditor'] ?? '#ffffff',
			'respEditorText' => $pub_settings['respEditorText'] ?? '#1a1a2e',
			'respEditorPh' => $pub_settings['respEditorPh'] ?? '#a0aec0',
			'respBtnText' => $pub_settings['respBtnText'] ?? '#ffffff',
			'respFontFamily' => $pub_settings['respFontFamily'] ?? 'inherit',
			'respFontSize' => $pub_settings['respFontSize'] ?? '0.9rem',
			'respCustomFont' => $pub_settings['respCustomFont'] ?? '',
		) );

		$cache_plugins = get_option('emsfb_cache_plugins','0');
		if ( current_user_can('manage_options') && $cache_plugins !== '0' && !empty($cache_plugins)) {
			$ar_core['cache_plugins'] = $cache_plugins;
		}
		wp_localize_script( 'Emsfb-core_js', 'ajax_object_efm',$ar_core);
	}

	public function email_list_efb( &$email_user , $pointer , $email , $state_array){
			$state_array= true;

			if(empty($email)){
			 return false;
			}
			if(!isset($email_user[$pointer])) $email_user[$pointer] = $state_array ? [] : '';
			if($state_array){
				if (strpos($email, ',') != -1){
					$emails = explode(',', $email);
					foreach ($emails as $email_) {
						if(!in_array($email_, $email_user[$pointer])){ array_push($email_user[$pointer] ,$email_); }
					}
					return true;
				}else{
					if(!in_array($email, $email_user[$pointer])){ array_push($email_user[$pointer] ,$email); return true;}
				}
			}else{

				$pos = strpos($email_user[$pointer],$email);
				if($pos===false){
					!empty($email_user[$pointer]) ? $email_user[$pointer] .= ' , '.$email : $email_user[$pointer] =$email;
					return true;
				}
			}
		}

	public function email_status_efb($formObj,$valobj,$check){

			$msg_content='null';
			$msg_type ='traking_link';
			$msg_sub = 'null';

			if(isset($formObj[0]["email_noti_type"]) && ( $formObj[0]["email_noti_type"]=='msg' || $formObj[0]["email_noti_type"]=='just_msg' )){

				if($formObj[0]["email_noti_type"]=='msg'){

					$msg_content_ = $this->email_get_content_efb($valobj ,$check);
					$msg_content = str_replace("\"","'",$msg_content_);
					$msg_type = 'message_link';
				}else if ($formObj[0]["email_noti_type"]=='cc'){

					$msg_type ='traking_link';
					$msg_sub = 'null';
				}else{

					$msg_content_ = $this->email_get_content_efb($valobj ,$check);
					$msg_content = str_replace("\"","'",$msg_content_);
					$msg_type = 'just_message';
				}

			}
			if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
				$msg_sub = $formObj[0]["email_sub"];
			}
			return ['subject'=>$msg_sub,'content'=>$msg_content,'type'=>$msg_type];
	}

	public function fun_validation_pay_elements_efb($val_ ,$fs_){
		$price_c =0;
		$price_f =0;
		$email ='';
		$valobj =[];
		for ($i=0; $i <count($val_) ; $i++) {
			$a=-1;
			if(isset($val_[$i]['price'])){
				if($val_[$i]['price'] ) $price_c += abs($val_[$i]['price']);
				if($val_[$i]['type']=="email" ) $email = $val_[$i]['value'];
				$iv = $val_[$i];
				if($iv['type']=="paySelect" || $iv['type']=="payRadio" || $iv['type']=="payCheckbox"){
					$filtered = array_filter($fs_, function($item) use ($iv) {
						switch ($iv['type']) {
							case 'paySelect':
								if(isset($item['parent']))	return $item['id_'] == $iv['id_ob'] &&  $item['value']==$iv['value'] ? $item['value'] :false ;
							break;
							case 'payRadio':
								if(isset($item['price'])){
									return $item['id_'] == $iv['id_ob'] &&  $item['value']==$iv['value'] ? $item['value'] :false;
								}
							break;
							case 'payCheckbox':
								if(isset($item['price']))	return $item['id_'] == $iv['id_ob'] &&  $item['parent']==$iv['id_'] ? $item['value'] :false;
							break;
						}
					});
					if($filtered==false){
						$msg = esc_html__('Invalid payment selection. Please review your choices and try again. If the problem persists, contact the site administrator.', 'easy-form-builder');
						$response = ['success' => false, 'm' => $msg];
						wp_send_json_success($response, 200);
					}
					 $iv = array_keys($filtered);
					 $a = isset( $iv[0])? $iv[0] :-1;
				}else if ($iv['type']=="payMultiselect" && isset($iv['price'])  && isset($iv['ids']) ){
					$rows = explode( ',', $iv['ids'] );
					foreach ($rows as $key => $value) {
						$filtered = array_filter($fs_, function($item) use ($value) {
							if(isset($item['id_']))return $item['id_'] == $value ;
						});
						$iv = array_keys($filtered);
						$price_f += $fs_[$a]['price'];
					}
					$a=-1;
				}else if($iv['type']=="prcfld" ){
					   $a=-1;
					   $price_f += $iv['price'];
				}
				if($a !=-1){
					if($fs_[$a]['type']!="payMultiselect"){
						$price_f+=$fs_[$a]['price'];
					}
						$fs_[$a]['name'] = $val_[$i]['name'];
						$fs_[$a]['type'] = "option_payment";
						array_push($valobj,$fs_[$a]);
				}
			}
		}
		$ip =$this->get_ip_address();
		$this->ip = $ip;
		if($price_c != $price_f) {
			$t=time();
			$SERVER_NAME = apply_filters('emsfb_get_server_host', 'yourdomain.com');
			$from =get_bloginfo('name')." <Alert@".$SERVER_NAME.">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			$to =get_option('admin_email');
			$message="This message from Easy Form Builder, This IP:".$this->ip.
			" try to enter invalid value like fee of the service of the form id:" .$this->id. " at :".date("Y-m-d-h:i:s",$t) ;
			wp_mail( $to,"Warning Entry[Easy Form Builder]", $message, $headers );
		}
		$obj = array(
			'price_total'=>$price_f,
			'valobj'=>$valobj,
			'email'=>$email
		);
		return $obj;
	}

	public function dedupe_by_id_and_ob_efb(array $rows): array {
    $seen = [];
    $out  = [];

    foreach ($rows as $row) {
        $id  = isset($row['id_'])   ? (string)$row['id_']   : '';
        $ob  = isset($row['id_ob']) ? (string)$row['id_ob'] : '';

        if ($id === '' && $ob === '') {
            $out[] = $row;
            continue;
        }

        $key = $id . '|' . $ob;

        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $row;
        }
    }

    return $out;
}

	public function pay_paypal_sub_Emsfb_api($data_POST_) {
		$handler_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal/class-Emsfb-paypal-handler.php';
		if ( ! file_exists( $handler_path ) ) {
			wp_send_json_error( [ 'success' => false, 'm' => 'PayPal module not available' ], 500 );
			return;
		}
		require_once $handler_path;
		$handler = new PaypalHandler();
		$handler->handle_create_payment( $data_POST_, $this );
	}

	public function pay_paypal_capture_Emsfb_api($data_POST_) {
		$handler_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal/class-Emsfb-paypal-handler.php';
		if ( ! file_exists( $handler_path ) ) {
			wp_send_json_error( [ 'success' => false, 'm' => 'PayPal module not available' ], 500 );
			return;
		}
		require_once $handler_path;
		$handler = new PaypalHandler();
		$handler->handle_capture( $data_POST_ );
	}

	public function pay_paypal_subscription_activate_Emsfb_api($data_POST_) {
		$handler_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal/class-Emsfb-paypal-handler.php';
		if ( ! file_exists( $handler_path ) ) {
			wp_send_json_error( [ 'success' => false, 'm' => 'PayPal module not available' ], 500 );
			return;
		}
		require_once $handler_path;
		$handler = new PaypalHandler();
		$handler->handle_subscription_activate( $data_POST_ );
	}

	public function fix_elementor_ultimate_DEPRECATED() {
		if (!is_admin()) {
			?>
			<script>

			(function() {
				'use strict';

				window.elementorFrontendConfig = window.elementorFrontendConfig || {};
				window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || {};
				window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || {};

				window.elementorFrontendConfig.tools.hash = window.elementorFrontendConfig.tools.hash || {};
				window.elementorFrontendConfig.tools.ajax = window.elementorFrontendConfig.tools.ajax || {};

				var originalConfig = window.elementorFrontendConfig;
				Object.defineProperty(window, 'elementorFrontendConfig', {
					get: function() {
						return originalConfig;
					},
					set: function(value) {
						if (value && typeof value === 'object') {
							value.tools = value.tools || {};
							value.settings = value.settings || {};
						}
						originalConfig = value;
					}
				});

			})();
			</script>
			<?php
		}
	}

	public function fix_elementor_monkey_patch_DEPRECATED() {
		if (!is_admin()) {
			?>
			<script>

			(function() {
				'use strict';

				function patchElementor() {

					window.elementorFrontendConfig = window.elementorFrontendConfig || {};
					window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || {};
					window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || {};

					if (typeof window.elementorFrontend !== 'undefined') {
						var originalInit = window.elementorFrontend.init;
						window.elementorFrontend.init = function() {
							this.config = this.config || window.elementorFrontendConfig;
							this.config.tools = this.config.tools || {};
							this.config.settings = this.config.settings || {};
							return originalInit.apply(this, arguments);
						};
					}

					if (typeof window.elementorFrontend !== 'undefined' && window.elementorFrontend.initOnReadyComponents) {
						var originalInitOnReady = window.elementorFrontend.initOnReadyComponents;
						window.elementorFrontend.initOnReadyComponents = function() {
							this.config = this.config || window.elementorFrontendConfig;
							this.config.tools = this.config.tools || {};
							this.config.settings = this.config.settings || {};
							return originalInitOnReady.apply(this, arguments);
						};
					}
				}

				patchElementor();

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', patchElementor);
				}

				window.addEventListener('load', patchElementor);

			})();
			</script>
			<?php
		}
	}

	public function fix_elementor_direct_DEPRECATED() {
		if (!is_admin()) {
			?>
			<script>

			(function() {
				'use strict';

				function emergencyFix() {

					window.elementorFrontendConfig = window.elementorFrontendConfig || {};
					window.elementorFrontendConfig.tools = window.elementorFrontendConfig.tools || {};
					window.elementorFrontendConfig.settings = window.elementorFrontendConfig.settings || {};

					if (typeof window.elementorFrontend === 'object' && window.elementorFrontend) {
						window.elementorFrontend.config = window.elementorFrontend.config || window.elementorFrontendConfig;
						if (window.elementorFrontend.config) {
							window.elementorFrontend.config.tools = window.elementorFrontend.config.tools || {};
							window.elementorFrontend.config.settings = window.elementorFrontend.config.settings || {};
						}
					}

					if (typeof window.elementorFrontend === 'object' &&
					    window.elementorFrontend &&
					    typeof window.elementorFrontend.init === 'function' &&
					    !window.elementorFrontend.initialized) {
						try {
							window.elementorFrontend.initialized = true;
						} catch (e) {
							console.log('EFB: Emergency fix attempt completed');
						}
					}
				}

				emergencyFix();

				setTimeout(emergencyFix, 100);
				setTimeout(emergencyFix, 500);

			})();
			</script>
			<?php
		}
	}

	private function efb_get_survey_results_data($form_id, $formObj) {
		global $wpdb;

		if (is_array($formObj)) {
			foreach ($formObj as $idx => $field) {
				$ftype = $field['type'] ?? 'NO_TYPE';
				$fid = $field['id_'] ?? 'NO_ID';
				$fname = $field['name'] ?? 'NO_NAME';
				$showInPublic = isset($field['showInPublicResults']) ? $field['showInPublicResults'] : 'NOT_SET';
			}
		}

		$table_name = $wpdb->prefix . 'emsfb_msg_';
		$query = $wpdb->prepare(
			"SELECT content FROM $table_name WHERE form_id = %d",
			$form_id
		);

		$messages = $wpdb->get_results($query);

		if ($wpdb->last_error) {
		}

		if (empty($messages)) {
			return [];
		}

		$test_content = stripslashes($messages[0]->content);
		$test_decode = json_decode($test_content, true);
		if (is_array($test_decode)) {
			foreach ($test_decode as $ti => $titem) {
				$tid = $titem['id_'] ?? 'NO_ID';
				$ttype = $titem['type'] ?? 'NO_TYPE';
				$tval = $titem['value'] ?? 'NO_VALUE';
				$tname = $titem['name'] ?? 'NO_NAME';
				$tidob = $titem['id_ob'] ?? 'NO_ID_OB';
			}
		}

		$field_categories = [
			'radio' => 'choice',
			'checkbox' => 'choice',
			'select' => 'choice',
			'multiselect' => 'choice',
			'payRadio' => 'choice',
			'payCheckbox' => 'choice',
			'paySelect' => 'choice',
			'yesNo' => 'choice',
			'switch' => 'choice',
			'range' => 'scale',
			'rating' => 'scale',
			'pointr5' => 'scale',
			'pointr10' => 'nps',
			'table_matrix' => 'matrix',
			'r_matrix' => 'matrix',
			'text' => 'text',
			'email' => 'text',
			'textarea' => 'text',
			'number' => 'numeric',
			'date' => 'date',
			'pdate' => 'date',
			'ardate' => 'date'
		];

		$public_fields = [];
		$skipped_fields_no_show = 0;
		$skipped_fields_no_category = 0;

		// Backward compatibility: older survey forms may not have per-field visibility flags.
		$has_public_visibility_config = false;
		foreach ($formObj as $field) {
			if (isset($field['showInPublicResults'])) {
				$has_public_visibility_config = true;
				break;
			}
		}

		foreach ($formObj as $field) {
			$ftype = $field['type'] ?? 'NO_TYPE';
			$fid = $field['id_'] ?? 'NO_ID';
			$field_type = $field['type'] ?? '';

			$show_in_public = isset($field['showInPublicResults']) ? intval($field['showInPublicResults']) : null;
			$should_include_field = ($show_in_public === 1);

			if ($show_in_public === null && !$has_public_visibility_config) {
				$should_include_field = isset($field_categories[$field_type]) && !in_array($field_type, ['step', 'option', 'r_matrix'], true);
			}

			if ($should_include_field) {
				if (!isset($field_categories[$field_type])) {
					$skipped_fields_no_category++;
					continue;
				}

				$field_id = $field['id_'];
				$category = $field_categories[$field_type];

				$public_fields[$field_id] = [
					'id' => $field_id,
					'name' => $field['name'] ?? $field_id,
					'type' => $field_type,
					'category' => $category,
					'options' => [],
					'values' => []
				];

				if ($category === 'choice') {
					if ($field_type === 'yesNo') {
						$btn1 = $field['button_1_text'] ?? 'Yes';
						$btn2 = $field['button_2_text'] ?? 'No';
						$public_fields[$field_id]['options'][$btn1] = ['value' => $btn1, 'count' => 0];
						$public_fields[$field_id]['options'][$btn2] = ['value' => $btn2, 'count' => 0];
					} elseif ($field_type === 'switch') {
						$on = $field['on'] ?? 'On';
						$off = $field['off'] ?? 'Off';
						$public_fields[$field_id]['options'][$on] = ['value' => $on, 'count' => 0];
						$public_fields[$field_id]['options'][$off] = ['value' => $off, 'count' => 0];
					} else {
						$opt_count = 0;
						foreach ($formObj as $option) {
							if (isset($option['type']) && $option['type'] === 'option'
								&& isset($option['parent']) && $option['parent'] === $field_id) {
								$opt_value = $option['value'] ?? '';
								$public_fields[$field_id]['options'][$opt_value] = [
									'id' => $option['id_'] ?? '',
									'value' => $opt_value,
									'count' => 0
								];
								$opt_count++;
							}
						}
						if ($opt_count === 0) {
						}
					}
				}
				elseif ($category === 'scale' && $field_type === 'pointr5') {
					for ($i = 1; $i <= 5; $i++) {
						$public_fields[$field_id]['options'][$i] = ['value' => $i, 'count' => 0];
					}
				}
				elseif ($category === 'nps') {
					for ($i = 0; $i <= 10; $i++) {
						$public_fields[$field_id]['options'][$i] = ['value' => $i, 'count' => 0];
					}
				}
				elseif ($field_type === 'rating') {
					for ($i = 1; $i <= 5; $i++) {
						$public_fields[$field_id]['options'][$i] = ['value' => $i, 'count' => 0];
					}
				}
				elseif ($field_type === 'range') {
					$public_fields[$field_id]['min'] = $field['milen'] ?? 0;
					$public_fields[$field_id]['max'] = $field['mlen'] ?? 100;
				}
				elseif ($category === 'matrix') {
					$row_count = 0;
					foreach ($formObj as $row) {
						if (isset($row['type']) && $row['type'] === 'r_matrix'
							&& isset($row['parent']) && $row['parent'] === $field_id) {
							$public_fields[$field_id]['rows'][$row['id_']] = [
								'name' => $row['value'] ?? '',
								'scores' => []
							];
							$row_count++;
						}
					}
				}
			} else {
				$skipped_fields_no_show++;
			}
		}

		if (empty($public_fields)) {
			return [];
		}

		$msg_index = 0;
		$total_items_processed = 0;
		$total_items_matched = 0;
		$total_items_skipped_no_id = 0;
		$total_items_skipped_no_field = 0;
		$total_items_skipped_empty = 0;
		$decode_failures = 0;

		foreach ($messages as $message) {
			$content = stripslashes($message->content);
			$data = json_decode($content, true);
			if (!is_array($data)) {
				$decode_failures++;
				if ($msg_index < 3) {
				}
				$msg_index++;
				continue;
			}

			if ($msg_index < 3) {
			}

			foreach ($data as $item) {
				$total_items_processed++;

				if (!isset($item['id_'])) {
					$total_items_skipped_no_id++;
					continue;
				}

				$field_id = $item['id_'];
				$item_type = $item['type'] ?? '';

				if ($item_type === 'r_matrix') {

					$row_id = $item['id_ob'] ?? null;
					$matrix_parent_id = $item['id_'] ?? null;
					$found_parent = false;

					if ($msg_index < 3) {
					}

					if ($row_id) {
						foreach ($public_fields as $pf_id => $pf) {
							if ($pf['category'] === 'matrix' && isset($pf['rows'][$row_id])) {
								$value = intval($item['value'] ?? 0);
								$public_fields[$pf_id]['rows'][$row_id]['scores'][] = $value;
								$found_parent = true;
								$total_items_matched++;
								if ($msg_index < 3) {
								}
								break;
							}
						}
					}

					if (!$found_parent && $matrix_parent_id && isset($public_fields[$matrix_parent_id]) && $public_fields[$matrix_parent_id]['category'] === 'matrix') {

						$item_name = $item['name'] ?? '';
						$matched_row = false;
						foreach ($public_fields[$matrix_parent_id]['rows'] as $rid => $rdata) {
							if ($rdata['name'] === $item_name) {

								$value = intval($item['value'] ?? 0);
								$public_fields[$matrix_parent_id]['rows'][$rid]['scores'][] = $value;
								$found_parent = true;
								$total_items_matched++;
								$matched_row = true;
								if ($msg_index < 3) {
								}
								break;
							}
						}
						if (!$matched_row && $msg_index < 3) {
						}
					}

					if (!$found_parent && $msg_index < 3) {
					}
					continue;
				}

				if (!isset($public_fields[$field_id])) {
					$total_items_skipped_no_field++;
					if ($msg_index < 3) {
					}
					continue;
				}

				$field = &$public_fields[$field_id];
				$value = $item['value'] ?? '';

				if (empty($value) && $value !== '0' && $value !== 0) {
					$total_items_skipped_empty++;
					if ($msg_index < 3) {
					}
					continue;
				}

				$total_items_matched++;

				if ($msg_index < 3) {
				}

				switch ($field['category']) {
					case 'choice':
						if (strpos($value, '@efb!') !== false) {
							$values = array_filter(explode('@efb!', $value));
						} else {
							$values = [$value];
						}
						foreach ($values as $val) {
							$val = trim($val);
							if (isset($field['options'][$val])) {
								$field['options'][$val]['count']++;
								if ($msg_index < 3) {
								}
							} else {
								if ($msg_index < 3) {
								}
							}
						}
						break;

					case 'scale':
					case 'nps':
						$score = intval($value);
						if (isset($field['options'][$score])) {
							$field['options'][$score]['count']++;
						}
						$field['values'][] = $score;
						if ($msg_index < 3) {
						}
						break;

					case 'numeric':
						$field['values'][] = floatval($value);
						break;

					case 'text':
						$field['values'][] = mb_strlen($value);
						break;

					case 'date':
						$field['values'][] = $value;
						break;
				}
			}
			$msg_index++;
		}

		unset($field);

		foreach ($public_fields as $fid => $fdata) {
			if (!empty($fdata['options'])) {
				foreach ($fdata['options'] as $okey => $oval) {
				}
			}
			if (!empty($fdata['values'])) {
			}
			if (isset($fdata['rows'])) {
				foreach ($fdata['rows'] as $rid => $rdata) {
				}
			}
		}

		$results = [];
		foreach ($public_fields as $field_id => $field) {
			$result = [
				'field_id' => $field_id,
				'field_name' => $field['name'],
				'field_type' => $field['type'],
				'category' => $field['category']
			];

			switch ($field['category']) {
				case 'choice':
					$labels = [];
					$data = [];
					$total = 0;
					foreach ($field['options'] as $opt_value => $option) {
						$labels[] = strval($opt_value);
						$data[] = $option['count'];
						$total += $option['count'];
					}
					$result['labels'] = $labels;
					$result['data'] = $data;
					$result['total'] = $total;
					$result['chart_type'] = 'bar';
					break;

				case 'scale':
					$values = $field['values'] ?? [];
					$avg = count($values) > 0 ? round(array_sum($values) / count($values), 2) : 0;

					if ($field['type'] === 'range') {

						$min_val = intval($field['min'] ?? 0);
						$max_val = intval($field['max'] ?? 100);
						$range_span = $max_val - $min_val;
						$bin_count = min(10, max(1, $range_span));
						$bin_size = ceil($range_span / $bin_count);

						$labels = [];
						$data = [];
						for ($i = 0; $i < $bin_count; $i++) {
							$bin_start = $min_val + ($i * $bin_size);
							$bin_end = min($bin_start + $bin_size, $max_val);
							$labels[] = $bin_start . '-' . $bin_end;
							$count = 0;
							foreach ($values as $v) {
								if ($i === $bin_count - 1) {

									if ($v >= $bin_start && $v <= $bin_end) $count++;
								} else {
									if ($v >= $bin_start && $v < $bin_end) $count++;
								}
							}
							$data[] = $count;
						}

						$result['labels'] = $labels;
						$result['data'] = $data;
						$result['total'] = count($values);
						$result['average'] = $avg;
						$result['min_value'] = count($values) > 0 ? min($values) : 0;
						$result['max_value'] = count($values) > 0 ? max($values) : 0;
						$result['chart_type'] = 'bar';
					} else {

						$labels = [];
						$data = [];
						$total = 0;
						foreach ($field['options'] as $score => $option) {
							$labels[] = strval($score);
							$data[] = $option['count'];
							$total += $option['count'];
						}

						$result['labels'] = $labels;
						$result['data'] = $data;
						$result['total'] = $total;
						$result['average'] = $avg;
						$result['chart_type'] = 'bar';
					}
					break;

				case 'nps':
					$labels = [];
					$data = [];
					$detractors = 0;
					$passives = 0;
					$promoters = 0;
					$total = 0;

					foreach ($field['options'] as $score => $option) {
						$labels[] = strval($score);
						$data[] = $option['count'];
						$total += $option['count'];

						if ($score <= 6) $detractors += $option['count'];
						elseif ($score <= 8) $passives += $option['count'];
						else $promoters += $option['count'];
					}

					$nps_score = $total > 0 ? round((($promoters - $detractors) / $total) * 100) : 0;

					$result['labels'] = $labels;
					$result['data'] = $data;
					$result['total'] = $total;
					$result['nps_score'] = $nps_score;
					$result['detractors'] = $detractors;
					$result['passives'] = $passives;
					$result['promoters'] = $promoters;
					$result['chart_type'] = 'nps';
					break;

				case 'matrix':
					$rows = [];
					foreach ($field['rows'] ?? [] as $row_id => $row) {
						$scores = $row['scores'] ?? [];
						$avg = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
						$rows[] = [
							'name' => $row['name'],
							'average' => $avg,
							'count' => count($scores)
						];
					}
					$result['rows'] = $rows;
					$result['chart_type'] = 'matrix';
					break;

				case 'numeric':
					$values = $field['values'] ?? [];
					$count = count($values);
					if ($count > 0) {
						$result['count'] = $count;
						$result['average'] = round(array_sum($values) / $count, 2);
						$result['min'] = min($values);
						$result['max'] = max($values);
						$result['sum'] = array_sum($values);
					} else {
						$result['count'] = 0;
						$result['average'] = 0;
						$result['min'] = 0;
						$result['max'] = 0;
						$result['sum'] = 0;
					}
					$result['chart_type'] = 'stats';
					break;

				case 'text':
					$values = $field['values'] ?? [];
					$count = count($values);
					$result['count'] = $count;
					$result['avg_length'] = $count > 0 ? round(array_sum($values) / $count) : 0;
					$result['chart_type'] = 'stats';
					break;

				case 'date':
					$values = $field['values'] ?? [];
					$result['count'] = count($values);
					$by_month = [];
					foreach ($values as $date) {
						$month = substr($date, 0, 7);
						$by_month[$month] = ($by_month[$month] ?? 0) + 1;
					}
					ksort($by_month);
					$result['labels'] = array_keys($by_month);
					$result['data'] = array_values($by_month);
					$result['chart_type'] = 'bar';
					break;
			}

			$results[] = $result;
		}

		return $results;
	}

	private function efb_intgrate_with_3rd_party_services_efb($track_code, $submitted_values, $form_fields_array, $event_type = 'form_submit') {

		$context = [
			'track_code'       => $track_code,
			'form_id'          => intval($this->id),
			'page_url'         => isset($_SERVER['HTTP_REFERER']) ? sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])) : get_site_url(),
			'event_type'       => $event_type,
			'submitted_values' => $submitted_values,
			'form_fields'      => $form_fields_array,
		];

		do_action('efb_3rd_party_telegram_notify', $context);

		do_action('efb_after_form_integration', $context);

	}

}
new _Public();
