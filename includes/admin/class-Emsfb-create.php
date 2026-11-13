<?php
namespace Emsfb;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Create {
	public $setting_name;
	public $options = array();
	public $id_;
	public $name;
	public $email;
	public $value;
	public $userId;
	public $formtype;
	protected $db;
	public function __construct() {
		$this->setting_name = 'Emsfb_create';
		global $wpdb;
		$this->db = $wpdb;
		$this->get_settings();
		$this->options = get_option( $this->setting_name );
		if ( empty( $this->options ) ) {
			update_option( $this->setting_name, array() );
		}

		if ( get_option( 'emsfb_pro' ) === false ) {
			add_option( 'emsfb_pro', -1 );
		}
		add_action( 'admin_menu', array( $this, 'add_Create_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'register_create' ) );
		add_action('fun_Emsfb_creator', array( $this, 'fun_Emsfb_creator'));
		add_action('wp_ajax_add_form_Emsfb', array( $this,'add_form_structure'));

	}
	public function add_Create_menu() {
		add_submenu_page( 'Emsfb', esc_html__('Create', 'easy-form-builder' ), esc_html__('Create', 'easy-form-builder' ), 'Emsfb_create', 'Emsfb_create', array(
			$this,
			'render_settings'
		) );
	}
	public function get_settings() {
		$settings = get_option( $this->setting_name );
		if ( ! $settings ) {
			update_option( $this->setting_name, array(
				'rest_api_status' => 1,
			) );
		}
		return apply_filters( 'Emsfb_get_settings', $settings );
	}
	public function register_create() {
		if ( false == get_option( $this->setting_name ) ) {
			add_option( $this->setting_name );
		}
	}
	public function render_settings() {
		$efbFunction = get_efbFunction();

		$noti_pro = intval(get_option('emsfb_pro' ,-1));
		if ($noti_pro === 0  ){
			$noti_pro ="<script>const noti_exp_efb='".$efbFunction->noti_expire_efb()."';</script>";

		}else{
			$noti_pro = '<script>const noti_exp_efb="";</script>';
		}

		$lang = $efbFunction->text_efb(1);

		$settings = get_setting_Emsfb('decoded');


		$pro = $efbFunction->is_efb_pro(1);
		$efbFunction->setting_version_efb_update($settings, $pro, true);
		$addon_recovery = $efbFunction->recover_missing_addons_efb( $settings, 'create' );
		if ( ! empty( $addon_recovery['recovered'] ) ) {
			echo $efbFunction->render_addon_recovery_reload_ui_efb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		// Scope 'admin': a package from before the add-on shipped its own translations
		// counts as missing here (and only here and on Panel), so recovery replaces it.
		$addon_health = $efbFunction->get_addon_local_health_efb( $settings, 'admin' );
		$download_addons = ! empty( $addon_health['missing'] );

		// After a plugin update, do not load the builder until missing add-on
		// files have been reinstalled — show a blocking recovery screen instead.
		$addon_recovery_state = $efbFunction->addon_recovery_state_efb( $settings, 'admin' );
		if ( 'block' === $addon_recovery_state ) {
			echo $efbFunction->render_addon_recovery_ui_efb( 'block', 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		// A locale now in use may lack an add-on language file pruned at install.
		$efbFunction->process_addon_i18n_refetch_efb();

		if(isset($settings->AdnPAP) && $settings->AdnPAP==1){
			if(!file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/paypal/paypalefb.php")) {
				$download_addons = true;
			}else{
				require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/paypal/paypalefb.php");
				$paypalefb = new paypalefb() ;
			}
		}
		if(isset($settings->AdnPDP) && $settings->AdnPDP==1){
			if(!file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php")) {
				$download_addons = true;
			}else{
				require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
				$persianDatePicker = new persianDatePickerEFB() ;
			}
		}
		if(isset($settings->AdnADP) && $settings->AdnADP==1){
			if(!file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php")) {
				$download_addons = true;
			}else{
				require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
				$arabicDatePicker = new arabicDatePickerEfb() ;
			}
		}
		if(isset($settings->AdnOF) && $settings->AdnOF==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/offline") || !file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/offline/json/countries.js")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnSPF) && $settings->AdnSPF==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnTLG) && $settings->AdnTLG==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/telegram")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnSS) && $settings->AdnSS==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnATF) && $settings->AdnATF==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/autofill")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnGoS) && $settings->AdnGoS==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/googlesheet")) {
				$download_addons = true;
			}
		}
		if(isset($settings->AdnPPF) && $settings->AdnPPF==1){
			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay")) {
				$download_addons = true;
			}
		}

		$is_persian_locale = get_locale() === 'fa_IR';
		$renew_required = !$is_persian_locale && (int) get_option('emsfb_addons_renew_required', 0) !== 0;
		$download_backoff = !$is_persian_locale && (int) get_option('emsfb_addons_dl_failures', 0) >= 3;
		// Missing files have already been recovered above in this request. If that
		// failed, the recovery UI below shows the exact cause and a retry button.
	?>
	<!-- new code ddd -->
	<style>
				.efb {font-family: 'Roboto', sans-serif!important;}
			</style>
	<div class="efb" id="sideMenuFEfb">
	</div>
	<!-- end new code dd -->
	<!--sideMenu--> <div class="efb sideMenuConEfb efbDW-0" id="sideMenuFEfb">
				<div class="efb side-menu-efb bg-light bg-gradient border text-dark fade efbDW-0 pb-5" id="sideBoxEfb">
				<div class="efb head sidemenu bg-light bg-gradient py-2 my-1">
				<span> </span>
					<a class="efb BtnSideEfb efb close sidemenu text-danger" id="BtnCSideEfb" onclick="sideMenuEfb(0)" title="<?php echo esc_html__('Close', 'easy-form-builder' )?>"><i class="efb bi-x-lg" ></i></a>
					<a class="efb BtnSideEfb efb close sidemenu px-1  text-success ec-efb"  onclick="sideMenuEfb(2)" title="<?php echo esc_html__('Save', 'easy-form-builder' )?>" ><i class="bi bi-check2" ></i></a>
				</div>
				<div class="efb  mx-3 sideMenu" id="sideMenuConEfb"></div>
				</div></div>
			<script>
				let bdy =document.getElementsByTagName('body');
				bdy[0].classList.add("bg-color");
				const sitekye_emsFormBuilder= ""
				</script>
				<?php echo $noti_pro; ?>
			<div id="alert_efb" class="efb mx-5"></div>
			<?php if ( 'inline' === $addon_recovery_state ) : ?>
				<?php echo $efbFunction->render_addon_recovery_ui_efb( 'inline', 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="efb modal-content efb " id="settingModalEfb-sections">
									<div class="efb modal-header efb">
										<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button"><i class="efb bi-x-lg"></i></a>
									</div>
									<div class="efb modal-body row" id="settingModalEfb-body">
									<?php do_action('efb_loading_card'); ?>
									</div>
					</div></div></div>
            <div id="tab_container_efb">
				<div class="efb card-body text-center efb mt-5 pt-3">
				<?php do_action('efb_loading_card'); ?>
				</div>
        	</div>
			<datalist id="color_list_efb">
			<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
			</datalist>
			<script>
					setTimeout(() => {
						if(typeof efb_var == 'undefined' || efb_var == null) {
							console.error('efb_var not found!')
							document.getElementById('tab_container_efb').innerHTML ='<div class="efb bg-danger m-5 fs-6 p-5 text-white" ><p><?php echo esc_html__('If you are seeing this message, it is likely for one of these reasons: If you have a caching plugin installed, its settings may need to be reviewed.','easy-form-builder') . ' ' . esc_html__('Please also ensure that you have a stable internet connection and try again.','easy-form-builder') ?></p><p class="efb fs-7 text-darkb mt-3"><?php echo  esc_html__('Easy Form Builder','easy-form-builder') ?></p></div>';
						}
					}, 90000);
			</script>
			<script>
				document.addEventListener('DOMContentLoaded', function(){
					if(typeof efb_powered_by === 'function' && !document.getElementById('wpfooter')){
						var _f = document.createElement('div');
						_f.innerHTML = efb_powered_by();
						document.body.appendChild(_f.firstChild);
					}
				});
			</script>
		<?php

		$maps =false;

		$addons = $efbFunction->fun_get_addons_list_efb($settings);

		if(isset($settings->osLocationPicker)==true && $settings->osLocationPicker==1){
			$efbFunction->openstreet_map_required_efb(0);
		}


		$url =CDN_ZONE_AREA.'js/wp/countries.js';
		if(isset($settings->AdnOF) && $settings->AdnOF==1){
			$url = EMSFB_PLUGIN_URL . 'vendor/offline/json/countries.js';
		}
		wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION,true);
		wp_enqueue_script('jquery-ui-efb');
		wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION,true);
		wp_enqueue_script('jquery-dd-efb');
		// async: a CDN that hangs (jsDelivr is often unreachable from Iran) must
		// not hold back the builder scripts printed after it.
		wp_register_script('countries-js', $url, null, null, array('in_footer' => true, 'strategy' => 'async'));
		wp_enqueue_script('countries-js');
		wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);
		wp_enqueue_script('intlTelInput-js');
		wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('intlTelInput-css');
		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
		"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
		'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
		'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif',
		'plugin_url'=>EMSFB_PLUGIN_URL,
		];
		$smtp =-1;
		$captcha =false;
		$smtp_m = "";
		$stng_pdate = true;
		if(gettype($settings)!="string"){
			if( isset($settings->siteKey)&& strlen($settings->siteKey)>5){$captcha="true";}
			/* 0 (not -1) when the switch is off, including the fresh-install case
			 * where the key is missing - the builder must be able to tell "off"
			 * apart from "settings unreadable" to show the setup hint. */
			if(emsfb_is_email_sending_enabled_efb($settings)){$smtp=1;}else{$smtp=0;$smtp_m =$lang['sMTPNotWork'];}
		}else{$smtp_m =$lang['goToEFBAddEmailM'];}
		if("fa_IR"==get_locale()){
			do_action('efb_enqueue_persia');
		}
		wp_register_script('stripe_js',  EMSFB_PLUGIN_URL .'/public/assets/js/stripe_pay-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION,true);
		wp_enqueue_script('stripe_js');

		$colors =[];

		$plugins =['wpsms' => 0,'wpbaker' => 0,'elemntor'=> 0 , 'cache'=>0];
			$plugins_get = get_plugins();
			if (is_plugin_active('wp-sms/wp-sms.php')) {
				$plugins['wpsms']=1;
			}
		$plugins['cache'] =$efbFunction->check_for_active_plugins_cache();
		$location ='';
		wp_register_script('efb-recorder-js', EMSFB_PLUGIN_URL . 'public/assets/js/recorder-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-recorder-js');
		wp_register_style('efb-recorder-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/recorder-efb.css', array(), EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('efb-recorder-css');
		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js', array('jquery','efb-recorder-js'), EMSFB_PLUGIN_VERSION, true);
		$email_health = $this->get_email_health_status_for_builder();
		$efb_var_data = apply_filters('efb_admin_localize_vars', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'=> wp_create_nonce("wp_rest"),
			'check' => 1,
			'pro' => $pro ? 1 : 0,
			'rtl' => is_rtl() ,
			'text' => $lang	,
			'siteName' => get_bloginfo('name'),
			'siteUrl' => home_url(),
			'adminEmail' => get_option('admin_email'),
			'images' => $img,
			'captcha'=>$captcha,
			'smtp'=>$smtp,
			"smtp_message"=>$smtp,
			'maps'=> $maps,
			'bootstrap' =>$this->check_temp_is_bootstrap(),
			"language"=> get_locale(),
			"addons"=>$addons,
			'wp_lan'=>get_locale(),
			'location'=>$location,
			'v_efb'=>EMSFB_PLUGIN_VERSION,
			'setting'=>$settings,
			'colors'=>$colors,
			'zone_area'=>CDN_ZONE_AREA,
			'plugins'=>$plugins,
			'emailHealth'=>$email_health,
			// What the owner did about delivery (unchecked / confirmed / tested)
			// and the data the builder's email question and check need: the
			// score thresholds and the guide links of the next-steps box.
			'emailDelivery' => class_exists('\Emsfb\Email_Monitor') ? \Emsfb\Email_Monitor::get_delivery_state() : array(),
			'emailMonitor' => class_exists('\Emsfb\Email_Monitor') ? \Emsfb\Email_Monitor::get_public_status() : array(),
			'onboarding_pending' => emsfb_onboarding_pending_efb(),
			// Hosting upload ceiling in MB - the builder warns when a field's
			// max file size is configured above it (uploads would fail).
			'upload_max'=>(int) floor(wp_max_upload_size() / MB_IN_BYTES)
		), 'create');
		wp_localize_script('Emsfb-admin-js','efb_var',$efb_var_data);
		// F18: plan gate, upgrade dialog and in-page plan change (reads efb_var.capabilities).
		wp_enqueue_script('efb-plan-gate-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/plan-gate-efb.js', array('Emsfb-admin-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/plan-gate-efb.js'), true);
		$efb_val_js_path = EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/val-efb.js';
		$efb_val_js_version = is_readable($efb_val_js_path) ? (string) filemtime($efb_val_js_path) : EMSFB_PLUGIN_VERSION;
		wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js', array('jquery', 'efb-email-test-ui'), $efb_val_js_version, true);
		// F11: the form settings' "Access and limits" section and the per-form CSS cleaner (twin of Form_Css).
		wp_enqueue_script('efb-form-limits-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/form-limits-efb.js', array('efb-val-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/form-limits-efb.js'), true);
		// F03: the field-rule engine (twin of Field_Rules) and its settings-panel controls.
		wp_enqueue_script('efb-field-rules-js', EMSFB_PLUGIN_URL . 'public/assets/js/field-rules-efb.js', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/field-rules-efb.js'), true);
		wp_enqueue_script('efb-field-rules-builder-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/field-rules-builder-efb.js', array('efb-val-js', 'efb-field-rules-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/field-rules-builder-efb.js'), true);
		wp_enqueue_style('efb-field-rules-css', EMSFB_PLUGIN_URL . 'public/assets/css/field-rules-efb.css', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/css/field-rules-efb.css'));
		// F02: the one-time code rules (twin of Verification) and the mobile / e-mail field controls.
		wp_enqueue_script('efb-verify-js', EMSFB_PLUGIN_URL . 'public/assets/js/verify-efb.js', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/verify-efb.js'), true);
		wp_enqueue_script('efb-verify-builder-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/verify-builder-efb.js', array('efb-val-js', 'efb-verify-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/verify-builder-efb.js'), true);
		// F07: the upload rules (twin of Uploads::sanitize_key) and the file / dadfile controls (several files, camera, resizing, storage).
		wp_enqueue_script('efb-upload-js', EMSFB_PLUGIN_URL . 'public/assets/js/upload-efb.js', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/upload-efb.js'), true);
		wp_enqueue_script('efb-upload-builder-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/upload-builder-efb.js', array('efb-val-js', 'efb-upload-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/upload-builder-efb.js'), true);
		// F08: smart tags (the resolver twin), the hidden field's panel, prefill controls and the insert list.
		wp_enqueue_script('efb-smart-tags-js', EMSFB_PLUGIN_URL . 'public/assets/js/smart-tags-efb.js', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/smart-tags-efb.js'), true);
		wp_enqueue_script('efb-smart-tags-builder-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/smart-tags-builder-efb.js', array('efb-val-js', 'efb-smart-tags-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/smart-tags-builder-efb.js'), true);
		wp_enqueue_style('efb-upload-css', EMSFB_PLUGIN_URL . 'public/assets/css/upload-efb.css', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/css/upload-efb.css'));
		wp_enqueue_style('efb-verify-css', EMSFB_PLUGIN_URL . 'public/assets/css/verify-efb.css', array(), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'public/assets/css/verify-efb.css'));
		// F06: the calendar engines, the picker (canvas and preview) and the advanced date field's panel.
		if (class_exists('\\Emsfb\\Calendar')) \Emsfb\Calendar::enqueue_admin(true);
		wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-forms-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/forms-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js', array('jquery','efb-recorder-js'), EMSFB_PLUGIN_VERSION, true);
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("wp_rest"),
			'check' => 1		));
		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		// F01 mask engine (window.EfbMasks): custom-mask check and preview in the builder, masks in the preview.
		wp_enqueue_script('efb-masks-js', EMSFB_PLUGIN_URL . 'public/assets/js/masks-efb.js', array(), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		// window.efbBuilderApi for integrations (docs/autopilot-integration.md, B1/B2/B4).
		wp_enqueue_script('efb-builder-api-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/builder-api-efb.js', array('Emsfb-admin-js', 'efb-val-js', 'efb-pro-els', 'efb-main-js'), EMSFB_PLUGIN_VERSION . '.' . (int) @filemtime(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/builder-api-efb.js'), true);

		if ( isset( $addons['AdnSMF'] ) && (int) $addons['AdnSMF'] >= 1 ) {
			wp_enqueue_style('efb-conditional-logic-css', EMSFB_PLUGIN_URL . 'vendor/logic/logic/assets/admin/css/conditional-logic-efb.css', array(), EMSFB_PLUGIN_VERSION);
			wp_enqueue_script('efb-conditional-logic-js', EMSFB_PLUGIN_URL . 'vendor/logic/logic/assets/admin/js/conditional-logic-efb.js', array('Emsfb-admin-js'), EMSFB_PLUGIN_VERSION, true);
			wp_enqueue_script('efb-conditional-logic-preview-js', EMSFB_PLUGIN_URL . 'vendor/logic/logic/assets/public/js/conditional-logic-efb.js', array('Emsfb-core-js'), EMSFB_PLUGIN_VERSION, true);
		}
	}
	/*
	 * The delivery score the builder warns about, from the check the owner ran.
	 * It used to be found by a recursive search over the saved status that
	 * could return a SpamAssassin figure on another scale, against a threshold
	 * of 75 that nothing else in the plugin uses; the line is now
	 * Email_Monitor::HEALTHY_SCORE, the same one the panel and the wizard draw.
	 */
	private function get_email_health_status_for_builder() {
		$score = null;
		$threshold = 70;
		if (class_exists('\Emsfb\Email_Monitor')) {
			$delivery = \Emsfb\Email_Monitor::get_delivery_state();
			$score = ('tested' === $delivery['state'] && isset($delivery['score'])) ? $delivery['score'] : null;
			$threshold = \Emsfb\Email_Monitor::HEALTHY_SCORE;
		}

		return array(
			'score' => $score,
			'threshold' => $threshold,
		);
	}
	private function extract_email_score_from_status($value) {
		if (!is_array($value)) {
			return null;
		}

		foreach (array('score', 'spam_score', 'spamScore') as $key) {
			if (isset($value[$key]) && is_numeric($value[$key])) {
				return (float) $value[$key];
			}
		}

		foreach ($value as $item) {
			if (is_array($item)) {
				$score = $this->extract_email_score_from_status($item);
				if ($score !== null) {
					return $score;
				}
			}
		}

		return null;
	}
	public function fun_Emsfb_creator()
	{
	}
	public function add_form_structure(){
		$efbFunction = get_efbFunction();
		$creat=["errorCheckInputs","NAllowedscriptTag","formNcreated","newMessageReceived","newResponse","WeRecivedUrM","trackNo","url",'error403',"smsOffAddonMissing","telegramOffAddonMissing","authFieldsMissing","login","register","smsAdminPhoneInvalid","smsFieldWithoutForm"];
		$lang = $efbFunction->text_efb($creat);
		$this->userId =get_current_user_id();

		$nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( !wp_verify_nonce( $nonce, 'wp_rest' ) || !current_user_can('Emsfb') ) {
            $response = ['success' => false, 'm' =>  $lang['error403']];
            wp_send_json_success($response, 200);
		}

		$email = '';
		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m =$lang['errorCheckInputs'];
			$response = array( 'success' => false , "m"=>$m);
			wp_send_json_success($response, 200);
		}
		if(isset($_POST['email']) ){$email =sanitize_email($_POST['email']);}
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;

		$valp =\emsfb_json_normalize_efb(wp_unslash($_POST['value']));
		$valp = json_decode($valp,true);
		// F03: a pattern the sanitizer would silently empty is refused with its path (API clients, AutoPilot).
		if (is_array($valp) && class_exists('\Emsfb\Field_Rules')) {
		    $rules_err_efb = \Emsfb\Field_Rules::validate_rows($valp);
		    if ($rules_err_efb) wp_send_json_success(['success' => false, 'm' => sprintf(__('This pattern cannot be used: %s', 'easy-form-builder'), $rules_err_efb[0]['reason']), 'code' => 'pattern_invalid', 'path' => $rules_err_efb[0]['path'], 'errors' => $rules_err_efb], 200);
		}
		// F06: advanced date limits that contradict each other refuse the save (builder, AutoPilot, API clients).
		$date_chk_efb = class_exists('\\Emsfb\\Calendar') && is_array($valp) ? \Emsfb\Calendar::validate_rows($valp) : array('errors' => array(), 'warnings' => array());
		if ($date_chk_efb['errors']) wp_send_json_success(['success' => false, 'm' => $date_chk_efb['errors'][0]['m'], 'code' => $date_chk_efb['errors'][0]['code'], 'path' => $date_chk_efb['errors'][0]['path'], 'errors' => $date_chk_efb['errors']], 200);
		$valp = $efbFunction->sanitize_obj_msg_efb($valp);
		// F13: privacy keys follow the server rules (allow-lists, form type, existing email field).
		if (is_array($valp) && class_exists('\Emsfb\Privacy')) {
			$valp = \Emsfb\Privacy::normalize_form_settings($valp);
		}
		// A5: a notification whose add-on is not active is stored switched off, with a warning.
		$save_warnings_efb = array();
		// F11: the same server rules as an update (no previous CSS on a new form).
		if (is_array($valp) && class_exists('\Emsfb\Form_Gate')) {
			$valp = \Emsfb\Form_Gate::normalize_form_settings($valp, null);
			$gate_checks_efb = \Emsfb\Form_Gate::save_checks($valp, 0);
			if (!empty($gate_checks_efb['errors'])) {
				wp_send_json_success(array('success' => false, 'm' => $gate_checks_efb['errors'][0]['m'], 'code' => $gate_checks_efb['errors'][0]['code']), 200);
			}
			$save_warnings_efb = array_merge($save_warnings_efb, $gate_checks_efb['warnings']);
		}
		if (is_array($valp) && class_exists('\Emsfb\Builder_Api')) {
			// (F07, in passing: the F11 warnings gathered just above used to be overwritten here.)
			list($valp, $notifier_warn_efb) = Builder_Api::disable_unavailable_notifiers($valp, get_setting_Emsfb('decoded', array()), $lang);
			$save_warnings_efb = array_merge($save_warnings_efb, $notifier_warn_efb);
			// PAY00: what the form's payment gateways need (currency, mapped fields, the gateway's own checks); never refuses the save.
			if (class_exists('\\Emsfb\\Payments\\Registry')) $save_warnings_efb = array_merge($save_warnings_efb, \Emsfb\Payments\Registry::form_warnings($valp));
			// F02: a field asking for a code this site cannot send now (plan, SMS add-on, provider, e-mail off), on a
			// registration / login form, or a custom e-mail text without [otp_code]; never refuses the save.
			if (class_exists('\\Emsfb\\Verification')) $save_warnings_efb = array_merge($save_warnings_efb, \Emsfb\Verification::form_warnings($valp));
			if (!empty($date_chk_efb['warnings'])) $save_warnings_efb = array_merge($save_warnings_efb, $date_chk_efb['warnings']); // F06
			if (Builder_Api::sms_field_without_form($valp)) {
				$save_warnings_efb[] = array('code' => 'sms_field_without_form', 'm' => $lang['smsFieldWithoutForm']);
			}
			$auth_missing_efb = Builder_Api::auth_fields_missing($valp);
			if (!empty($auth_missing_efb)) {
				$type_label_efb = isset($lang[$valp[0]['type']]) ? $lang[$valp[0]['type']] : $valp[0]['type'];
				$m = str_replace(array('%1$s', '%2$s'), array($type_label_efb, implode(', ', $auth_missing_efb)), isset($lang['authFieldsMissing']) ? $lang['authFieldsMissing'] : 'A %1$s form needs these fields: %2$s.');
				wp_send_json_success(array('success' => false, 'm' => $m, 'code' => 'auth_fields_missing', 'missing' => $auth_missing_efb), 200);
			}
		}
		// F07: upload keys checked against each other (minimum ≤ maximum, total ≥ per-file size, camera
		// and resizing only for their presets, the Media Library for users who may upload); never refuses.
		if (is_array($valp) && class_exists('\Emsfb\Uploads')) {
			list($valp, $upload_warn_efb) = \Emsfb\Uploads::normalize_rows($valp, null);
			$save_warnings_efb = array_merge($save_warnings_efb, $upload_warn_efb);
		}
		// F08: a tag in the redirect host, an answer from a later step, a hidden value that stores personal data; never refuses.
		if (is_array($valp) && class_exists('\\Emsfb\\Smart_Tags')) {
			$save_warnings_efb = array_merge(isset($save_warnings_efb) ? $save_warnings_efb : array(), \Emsfb\Smart_Tags::form_warnings_text($valp, isset($lang) && is_array($lang) ? $lang : array()));
		}
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){
			$response = array( 'success' => false , "m"=> $lang['NAllowedscriptTag']);
			wp_send_json_success($response, 200);
		}

		$sms_msg_new_noti="";
		$sms_msg_responsed_noti="";
		$sms_msg_recived_user="";
		$sms_admins_phoneno="";
		if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1){
			// B5: empty texts get the defaults; admin numbers are checked on the server.
			$sms_msg_new_noti = !empty($valp[0]['sms_msg_new_noti']) ?$valp[0]['sms_msg_new_noti'] :$lang['newMessageReceived'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_msg_responsed_noti = !empty($valp[0]['sms_msg_responsed_noti']) ? $valp[0]['sms_msg_responsed_noti'] :  $lang['newResponse']."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_msg_recived_user = !empty($valp[0]['sms_msg_recived_usr']) ? $valp[0]['sms_msg_recived_usr'] : $lang['WeRecivedUrM'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_admins_phoneno = isset($valp[0]['sms_admins_phone_no']) ? $valp[0]['sms_admins_phone_no'] : "";
			if (class_exists('\Emsfb\Builder_Api')) {
				list($sms_ok_efb, $sms_bad_efb) = Builder_Api::sms_admin_numbers($sms_admins_phoneno, function_exists('get_locale') ? get_locale() : '');
				$sms_admins_phoneno = implode(',', $sms_ok_efb);
				if (!empty($sms_bad_efb)) {
					$save_warnings_efb[] = array('code' => 'sms_admin_phone_invalid', 'm' => str_replace('%s', implode(', ', array_map('sanitize_text_field', $sms_bad_efb)), $lang['smsAdminPhoneInvalid']), 'numbers' => array_map('sanitize_text_field', $sms_bad_efb));
				}
			}
			unset($valp[0]['sms_msg_new_noti']);
			unset($valp[0]['sms_msg_responsed_noti']);
			unset($valp[0]['sms_msg_recived_user']);
			unset($valp[0]['sms_msg_recived_usr']);
			if(isset($valp[0]['sms_admins_phone_no'])){unset($valp[0]['sms_admins_phone_no']);}
		}
		/* Telegram texts are stored like on a form update (class-Emsfb-admin.php):
		   a new form had no Telegram row, so its notifications went out with the
		   built-in English text instead of what the admin wrote. */
		$telegram_contact_efb = null;
		if(isset($valp[0]['telegramnoti']) && intval($valp[0]['telegramnoti'])==1){
			$telegram_contact_efb = array(
				'new'      => isset($valp[0]['telegram_msg_new_noti']) ? $valp[0]['telegram_msg_new_noti'] : $lang['newMessageReceived'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]",
				'response' => isset($valp[0]['telegram_msg_responsed_noti']) ? $valp[0]['telegram_msg_responsed_noti'] : $lang['newResponse']."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]",
				'received' => isset($valp[0]['telegram_msg_recived_usr']) ? $valp[0]['telegram_msg_recived_usr'] : $lang['WeRecivedUrM'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]",
			);
			foreach (array('telegram_msg_new_noti','telegram_msg_responsed_noti','telegram_msg_recived_user','telegram_msg_recived_usr','telegram_bot_token','telegram_admin_chat_ids') as $telegram_key_efb) {
				unset($valp[0][$telegram_key_efb]);
			}
		}
		$valx =json_encode($valp,JSON_UNESCAPED_UNICODE);
		$this->value=str_replace('"', '\\"', $valx);
		$this->insert_db();
		if($telegram_contact_efb !== null && file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' )){
			require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
			if (class_exists('\\Emsfb\\telegramsendefb') || class_exists('telegramsendefb')) {
				$telegramsendefb = class_exists('\\Emsfb\\telegramsendefb') ? new \Emsfb\telegramsendefb() : new \telegramsendefb();
				if (method_exists($telegramsendefb, 'add_telegram_contact_efb')) {
					// Token and chat id come from the Telegram settings page.
					$telegramsendefb->add_telegram_contact_efb(
						$this->id_,
						get_option('emsfb_telegram_chat_id', ''),
						get_option('emsfb_telegram_bot_token', ''),
						$telegram_contact_efb['received'],
						$telegram_contact_efb['new'],
						$telegram_contact_efb['new'],
						$telegram_contact_efb['response']
					);
				}
			}
		}
		if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1 ){

			$sms_exists = get_option('emsfb_addon_AdnSS', false);
			$sms_files_exists = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
			if($sms_exists !== false && $sms_exists != 0 && $sms_files_exists){
				require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
				$smsefb = new smssendefb();
				$smsefb->add_sms_contact_efb(
					$this->id_,
					$sms_admins_phoneno,
					$sms_msg_recived_user,
					$sms_msg_new_noti,
					$sms_msg_new_noti,
					$sms_msg_responsed_noti);
			}
		}
		if($this->formtype=='login' || $this->formtype=='register'){
			do_action('create_temporary_links_table_Emsfb');
		}
		if($this->id_ !=0){
			// Side effects of a new form (F13 emsfb_prv_forms map; SitePilot contract §5.8).
			do_action('efb_form_created', (int) $this->id_, $valp, array('form_id' => (int) $this->id_, 'source' => 'builder', 'user_id' => get_current_user_id()));
		}
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_);
			if (!empty($save_warnings_efb)) $response['warnings'] = $save_warnings_efb;
			// PAY00 readiness (computed, never stored): whether visitors can pay with this form as saved, and why not.
			if (is_array($valp) && class_exists('\\Emsfb\\Payments\\Readiness')) $response = \Emsfb\Payments\Readiness::save_answer($response, $valp, (int) $this->id_);
		}else{$response = array( 'success' => false , "m"=> $lang['formNcreated']);}
		wp_send_json_success($response, 200);
	}
	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		if(empty($this->db)){
			global $wpdb;
			$this->db = $wpdb;
		}
		$table_name = $this->db->prefix . "emsfb_form";
		$r =$this->db->insert($table_name, array(
			'form_name' => $this->name,
			'form_structer' => $this->value,
			'form_email' => $this->email,
			'form_created_by' => $this->userId,
			'form_type'=>$this->formtype,
			'form_create_date' => wp_date('Y-m-d H:i:s'),
		));
		// A refused insert keeps whatever id an earlier insert in this request
		// left behind; only a real insert may report an id.
		$this->id_  = (false === $r) ? 0 : $this->db->insert_id;
	}
	public function check_temp_is_bootstrap (){

		$cached = get_transient('emsfb_theme_has_bootstrap');
		if ($cached !== false) {
			return $cached === 'yes';
		}

        $it = list_files(get_template_directory());
        $s = false;
        foreach($it as $path) {
            if (preg_match("/\bbootstrap+.+.css+/i", $path))
            {
                $f = emsfb_read_file_efb($path);
                if(preg_match("/col-md-12/i", $f)){
                    $s= true;
                    break;
                }
            }
        }

		set_transient('emsfb_theme_has_bootstrap', $s ? 'yes' : 'no', DAY_IN_SECONDS);
        return  $s;
    }
}
new Create();
