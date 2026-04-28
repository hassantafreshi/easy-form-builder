<?php
namespace Emsfb;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Panel_edit  {
	public $nounce;
	protected $db;
	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		if ( is_admin() ) {
			$rtl = is_rtl();
			$plugins =['wpsms' => 0,'wpbaker' => 0,'elemntor'=> 0 , 'cache'=>0];
			$plugins_get = get_plugins();
			if (is_plugin_active('wp-sms/wp-sms.php')) {
				$plugins['wpsms']=1;
			}
			$plugins_get =null;
			wp_register_script('gchart-js', 'https://www.gstatic.com/charts/loader.js', null, null, true);
			wp_enqueue_script('gchart-js');
			$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
			"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
			"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
			"emailTemplate1"=>''.EMSFB_PLUGIN_URL . 'public/assets/images/email_template1.png',
			"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
			'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
			'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif',
			'plugin_url'=>EMSFB_PLUGIN_URL,
			];
			$efbFunction = get_efbFunction();
			$pro =$efbFunction->is_efb_pro(1);;

			$ac = get_setting_Emsfb('decoded');

			$lang = $efbFunction->text_efb(2);
			$smtp =false;
			$captcha =false;
			$maps=false;
			$mdtest = "15f57cc603c2ea64721ae0d0b5983136";
			$addons = $efbFunction->fun_get_addons_list_efb($ac);
			if(is_object($ac) && isset($ac->osLocationPicker) && $ac->osLocationPicker==1){
				$efbFunction->openstreet_map_required_efb(0);
		    }
			if(is_object($ac) ){
				$server_name = str_replace("www.", "", isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '');

				if(isset($ac->siteKey)){$captcha="true";}
				if(isset($ac->smtp) && (bool)$ac->smtp){$smtp=1;}else{$smtp_m =$lang['sMTPNotWork'];}

				$lng = get_locale();
			$k ="";
			$noti_pro = intval(get_option('emsfb_pro' ,-1));
			if ($noti_pro === 0  ){
				$noti_pro = "<script>const noti_exp_efb='".$efbFunction->noti_expire_efb()."';</script>";

			}else{
				$noti_pro = '<script>const noti_exp_efb="";</script>';
			}
			$is_rtl = is_rtl();
			if(is_object($ac) && isset($ac->siteKey))$k= $ac->siteKey;
			if ( strlen( $lng ) > 0 ) {
				$lng = explode( '_', $lng )[0];
				}
			$download_addons = null;
			if(isset($ac->AdnPAP) && $ac->AdnPAP==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/paypal")) {
						$download_addons = true;
					}else{
						require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/paypal/paypalefb.php");
						$paypalefb = new paypalefb() ;
					}
			}
			if(isset($ac->AdnPDP) && $ac->AdnPDP==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {
						$download_addons = true;
					}else{
						require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
						$persianDatePicker = new persianDatePickerEFB() ;
					}
			}
			if(isset($ac->AdnADP) && $ac->AdnADP==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {
						$download_addons = true;
					}else{
						require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
						$arabicDatePicker = new arabicDatePickerEfb() ;
					}
			}
			if(isset($ac->AdnOF) && $ac->AdnOF==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/offline") || !file_exists(EMSFB_PLUGIN_DIRECTORY."/vendor/offline/json/countries.js")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnSPF) && $ac->AdnSPF==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnTLG) && $ac->AdnTLG==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/telegram")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnSS) && $ac->AdnSS==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnATF) && $ac->AdnATF==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/autofill")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnGoS) && $ac->AdnGoS==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/googlesheet")) {
						$download_addons = true;
					}
			}
			if(isset($ac->AdnPPF) && $ac->AdnPPF==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay")) {
						$download_addons = true;
					}
			}

				if($download_addons==true){
					print $efbFunction->update_message_admin_side_efb();
					$efbFunction->download_all_addons_efb();
				 	return;

				}

				?>
				<style>
					.efb {font-family: 'Roboto', sans-serif!important;}
				</style>

				<!--sideMenu--> <div class="efb sideMenuFEfb efbDW-0" id="sideMenuFEfb">
				<div class="efb side-menu-efb bg-light bg-gradient border text-dark fade efbDW-0 "  id="sideBoxEfb">
					<div class="efb head sidemenu bg-light bg-gradient py-2 my-1">
					<span> </span>
						<a class="efb BtnSideEfb efb close sidemenu  text-danger ec-efb"  data-eventform='sideMenuEfb' onclick="sideMenuEfb(0)" title="<?php echo esc_html__('Close', 'easy-form-builder' )?>"><i class="efb bi-x-lg" ></i></a>
						<a class="efb BtnSideEfb efb close sidemenu px-1  text-success ec-efb"  data-eventform='sideMenuEfbSave' onclick="sideMenuEfb(2)" title="<?php echo esc_html__('Save', 'easy-form-builder' )?>"><i class="bi bi-check2" ></i></a>
					</div>
					<div class="efb mb-5 mx-2 sideMenu" id="sideMenuConEfb"></div>
					</div></div>
				<div id="body_emsFormBuilder" class="efb my-2 <?php echo $is_rtl ? 'ms-3' : 'me-3' ?>">
					<div id="msg_emsFormBuilder" class="efb mx-2">
				</div>
				<div class="efb top_circle-efb-1"></div>
				<script>let sitekye_emsFormBuilder="<?php echo esc_js($k); ?>";</script>
						<?php echo $noti_pro ?>
					<nav class="efb navbar navbar-expand-lg navbar-light efb" id="navbar">
						<div class="efb container">
							<a class="efb navbar-brand efb" href="admin.php?page=Emsfb_create" >
								<img src="<?php echo EMSFB_PLUGIN_URL.'/includes/admin/assets/image/logo-easy-form-builder.svg' ?>" class="efb logo efb">
								<?php echo esc_html__('Easy Form Builder','easy-form-builder') ?></a>
							<button class="efb navbar-toggler efb" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
								<span class="efb navbar-toggler-icon efb"></span>
							</button>
							<div class="efb collapse navbar-collapse" id="navbarSupportedContent">
								<ul class="efb navbar-nav me-auto mb-2 mb-lg-0">
									<li class="efb nav-item"><a class="efb nav-link efb active ec-efb" data-eventform='forms' id="efb-nav-panel" aria-current="page"  role="button"><?php echo $lang["forms"] ?></a></li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" id="efb-nav-setting" data-eventform='setting'  role="button"><?php echo $lang["settings"] ?></a>
									</li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" href="admin.php?page=Emsfb_create" role="button"><?php echo $lang["create"]  ?></a>
									</li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" id="efb-nav-help" data-eventform='help' role="button"><?php echo $lang["help"] ?></a>
									</li>
								</ul>
								<div class="efb d-flex">
									<form class="efb d-flex">
									<?php echo !$is_rtl ? '<i class="efb  bi-search search-icon"></i>' : '' ?>
									<input class="efb form-control efb search-form-control efb-rounded efb mx-2" type="search" id="track_code_emsFormBuilder" placeholder="<?php echo $lang["search"]  ?> ..."  aria-label="<?php echo $lang["search"]  ?>">
										<a class="efb btn efb btn-outline-pink mx-2 ec-efb" type="submit" id="track_code_btn_emsFormBuilder" data-eventform='searchCC'><?php echo   $lang["search"] ?></a>
									</form>
									<div class="efb nav-icon efb mx-2">
										<a class="efb nav-link efb" href="https://whitestudio.team/login" target="blank"><i class="efb  bi-person"></i></a>
									</div>
									<div class="efb nav-icon efb">
										<a class="efb nav-link efb ec-efb" href="https://whitestudio.team/documents" target="blank"><i class="efb  bi-file-earmark-richtext"></i></a>
									</div>
								</div>
							</div>
						</div>
					</nav>
					<div id="alert_efb" class="efb mx-5"></div>
					<!-- end  new nav  -->
						<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
							<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
								<div class="efb modal-content efb " id="settingModalEfb-sections">
										<div class="efb modal-header efb">
											<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo  is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button"><i class="efb bi-x-lg"></i></a>
										</div>
										<div class="efb modal-body" id="settingModalEfb-body">
											<div class="efb card-body text-center">
											<?php  do_action('efb_loading_card'); ?>
										</div></div><!-- settingModalEfb-body-->
						</div></div></div>
						<div class="efb row mb-2">
						<button type="button" class="efb btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="efb fa fa-home"></i></button>
						</div>

						<div class="efb row m-0 p-0" id ="content-efb">
						<div class="efb card-body text-center my-5">
							<?php  do_action('efb_loading_card'); ?>
						</div>
						</div>
						<div class="efb mt-3 d-flex justify-content-center align-items-center ">
						<button type="button" id="more_emsFormBuilder" class="efb  btn btn-delete btn-sm" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="efb bi-chevron-double-down"></i></button>
						</div></div>
						<datalist id="color_list_efb">
							<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
						</datalist>
				<?php
				if(is_object($ac) && (!isset($ac->efb_version) || version_compare(EMSFB_PLUGIN_VERSION,$ac->efb_version)!=0)){
					$efbFunction->setting_version_efb_update($ac ,$pro);
				}


			}else{$smtp_m =$lang['goToEFBAddEmailM'];}

			$colors =[];
			$location ='';

			$current_locale = get_locale();
			if (strpos($current_locale, 'de_') === 0) {
				$wsteam_domain = 'de.whitestudio.team';
			} elseif (strpos($current_locale, 'ar') === 0) {
				$wsteam_domain = 'ar.whitestudio.team';
			} elseif (strpos($current_locale, 'fa_') === 0) {
				$wsteam_domain = 'whitestudio.team';
			} else {
				$wsteam_domain = 'whitestudio.team';
			}

			$sid = $efbFunction->efb_code_validate_create(0, 1, 'admin' , 0);
			$plugins['cache'] = $efbFunction->check_for_active_plugins_cache();
			wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
			$efb_var_data = apply_filters('efb_admin_localize_vars', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'=> wp_create_nonce("wp_rest"),
				'pro' => $pro ? 1 : 0,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang,
				'siteName' => get_bloginfo('name'),
				'siteUrl' => home_url(),
				'adminEmail' => get_option('admin_email'),
				'images' => $img,
				'captcha'=>$captcha,
				'smtp'=>$smtp,
				'maps'=> $maps,
				'bootstrap' =>$this->check_temp_is_bootstrap(),
				"language"=> get_locale(),
				"addons"=>$addons,
				'wp_lan'=>get_locale(),
				'location'=>$location,
				'setting'=>$ac,
				'v_efb'=>EMSFB_PLUGIN_VERSION,
				'colors'=>$colors,
				'sid'=>$sid,
				'rest_url'=>get_rest_url(null),
				'plugins'=>$plugins,
				'wsteam'=> $wsteam_domain,
			), 'panel');
			wp_localize_script('Emsfb-admin-js','efb_var',$efb_var_data);
			wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
			wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
			$lng_ = get_locale();
			if ( strlen( $lng_ ) > 0 ) {
			$lng_ = explode( '_', $lng_ )[0];
			}
			if("fa_IR"==get_locale()){

				do_action('efb_enqueue_persia');
			}
			wp_register_script('stripe_js',  EMSFB_PLUGIN_URL .'/public/assets/js/stripe_pay-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION , true);
			wp_enqueue_script('stripe_js');
			 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("wp_rest"),
					'check' => 0
					));
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
			wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);

			wp_enqueue_style('efb-conditional-logic-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/conditional-logic-efb.css', array(), EMSFB_PLUGIN_VERSION);
			wp_enqueue_script('efb-conditional-logic-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/conditional-logic-efb.js', array('Emsfb-admin-js'), EMSFB_PLUGIN_VERSION, true);

				wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),  true,EMSFB_PLUGIN_VERSION);
				wp_enqueue_script('jquery-ui-efb');
				wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),  true,EMSFB_PLUGIN_VERSION);
				wp_enqueue_script('jquery-dd-efb');

			$url =CDN_ZONE_AREA.'js/wp/countries.js';
			if(isset($ac->AdnOF) && $ac->AdnOF==1){
				$url = EMSFB_PLUGIN_URL . 'vendor/offline/json/countries.js';
			}
			wp_register_script('countries-js', $url, null, null, true);
			wp_enqueue_script('countries-js');
			wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);
			wp_enqueue_script('intlTelInput-js');
			wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,EMSFB_PLUGIN_VERSION);
			wp_enqueue_style('intlTelInput-css');
			if( false){
				wp_register_script('logic-efb',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', null, null, true);
				wp_enqueue_script('logic-efb');
			}
			$value = $efbFunction->efb_list_form();
			$table_name = $this->db->prefix . "emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );

			if (!empty($stng) && isset($stng[0]->setting)) {
				$decoded = json_decode($stng[0]->setting);
				if ($decoded === null) {
					$decoded = json_decode(stripslashes($stng[0]->setting));
				}
				if ($decoded !== null) {

					if (isset($decoded->emailTemp)) {
						$decoded->emailTemp = str_replace('"', "'", $decoded->emailTemp);
					}
					$stng[0]->setting = json_encode($decoded, JSON_UNESCAPED_UNICODE);
				}
			}
			$lng = get_locale();
			$ip =0;
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {

				$ip = isset($_SERVER['HTTP_CLIENT_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) ) : '0.0.0.0';
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {

				$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '0.0.0.0';
			} else {
				$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
			}
			wp_register_script('Emsfb-list_form-efb-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/list_form-efb.js', array('efb-main-js'),EMSFB_PLUGIN_VERSION, true);
			wp_enqueue_script('Emsfb-list_form-efb-js');

			wp_register_script('Emsfb-email-template-builder-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/email-template-builder-efb.js', array('Emsfb-list_form-efb-js'), EMSFB_PLUGIN_VERSION, true);
			wp_enqueue_script('Emsfb-email-template-builder-js');
			wp_localize_script( 'Emsfb-list_form-efb-js', 'ajax_object_efm',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajax_value' => $value,
					'language' => $lng_,
					'text' => $lang,
					'nonce'=>wp_create_nonce("wp_rest"),
					'user_name'=> wp_get_current_user()->display_name,
					'user_ip'=> $ip,
					'setting'=>$stng,
					'messages_state' =>$this->get_not_read_message(),
					'response_state' =>$this->get_not_read_response(),
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
					'zone_area'=>CDN_ZONE_AREA,
					'bootstrap'=>$this->check_temp_is_bootstrap(),
					'pro'=>$pro ? 1 : 0,
					'devMode'=> get_option('emsfb_dev_mode', '0') === '1' ? 1 : 0,
				));

		}else{
			echo "Easy Form Builder: You don't access this section";
		}
	}
	public function get_not_read_message(){
		if(empty($this->db)){
			global $wpdb;
			$this->db = $wpdb;
		}
		$table_name = $this->db->prefix . "emsfb_msg_";
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0 OR read_=3" );
		return $value;
	}
	public function get_not_read_response(){
		if(empty($this->db)){
			global $wpdb;
			$this->db = $wpdb;
		}
		$table_name_msg = $this->db->prefix . "emsfb_msg_";
		$table_name_rsp = $this->db->prefix . "emsfb_rsp_";

		$value = $this->db->get_results( "SELECT t.msg_id, t.form_id
		FROM `$table_name_msg` AS t
		 INNER JOIN `$table_name_rsp` AS tr
		 ON t.msg_id = tr.msg_id AND tr.read_ = 0" );
		return $value;
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
                $f = file_get_contents($path);
                if(preg_match("/col-md-12/i", $f)){
                    $s= true;
                    break;
                }
            }
        }

		set_transient('emsfb_theme_has_bootstrap', $s ? 'yes' : 'no', DAY_IN_SECONDS);
        return  $s;
    }
	public function test_smart_zone (){

            $fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/smartzone/smartzone.php";
            if(file_exists($fl_ex)){
                $name ='smartzone';
                $name ='\Emsfb\\'.$name;
                require_once $fl_ex;
                $t = new $name();
            }

	}

}
