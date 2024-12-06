<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No direct access allow ;)

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

		add_action( 'admin_menu', array( $this, 'add_Create_menu' ), 11 );
		add_action( 'admin_create_scripts', array( $this, 'admin_create_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_create' ) );
		add_action('fun_Emsfb_creator', array( $this, 'fun_Emsfb_creator'));
		add_action('wp_ajax_add_form_Emsfb', array( $this,'add_form_structure'));//ساخت فرم
		
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
					<a class="efb BtnSideEfb efb close sidemenu text-danger" id="BtnCSideEfb" onClick="sideMenuEfb(0)"><i class="efb bi-x-lg" ></i></a>
				</div>
				<div class="efb  mx-3 sideMenu" id="sideMenuConEfb"></div>
				</div></div>
			<script>		
				let bdy =document.getElementsByTagName('body');
				bdy[0].classList.add("bg-color");
				const sitekye_emsFormBuilder= ""
			</script>
			<div id="alert_efb" class="efb mx-5"></div>
			<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="efb modal-content efb " id="settingModalEfb-sections">
									<div class="efb modal-header efb"> 
										<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button"><i class="efb bi-x-lg"></i></a>
									</div>
									<div class="efb modal-body row" id="settingModalEfb-body">
									<?php echo  do_action('efb_loading_card'); ?>
									</div>
					</div></div></div>
            <div id="tab_container_efb">
				<div class="efb card-body text-center efb mt-5 pt-3">
				<?php echo   do_action('efb_loading_card'); ?>
				</div>	
        	</div>
			<datalist id="color_list_efb">
			<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
			</datalist>
			<script>
		
					setTimeout(() => {
						
						if(typeof efb_var == 'undefined' || efb_var == null) {
							console.log('efb_var not found!')
							document.getElementById('tab_container_efb').innerHTML ='<div class="efb bg-danger m-5 fs-6 p-5 text-white" ><p><?php echo esc_html__('If you are seeing this message, it is likely for one of these reasons: If you have a caching plugin installed, its settings may need to be reviewed.','easy-form-builder') . ' ' . esc_html__('Please also ensure that you have a stable internet connection and try again.','easy-form-builder') ?></p><p class="efb fs-7 text-darkb mt-3"><?php echo  esc_html__('Easy Form Builder','easy-form-builder') ?></p></div>';
						}
					}, 90000);
				
			</script>
			
		<?php
		

		$pro =false;
		$maps =false;
		$efbFunction = $this->get_efbFunction();
		$ac= $efbFunction->get_setting_Emsfb();
		$addons = ['AdnSPF' => 0,
		'AdnOF' => 0,
		'AdnPPF' => 0,
		'AdnATC' => 0,
		'AdnSS' => 0,
		'AdnCPF' => 0,
		'AdnESZ' => 0,
		'AdnSE' => 0,
		'AdnPDP'=>0,
		'AdnADP'=>0];
		//v2 translate

		//write a code for get all colors used in array in template set as active template in wordpress . complate code and use regix to find all colores is used in tamplate

		
		$lang = $efbFunction->text_efb(1);
		if(gettype($ac)!="string"){
			$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
		
			if (isset($ac->activeCode)==true && strlen($ac->activeCode)>5 && md5($server_name)==$ac->activeCode){
				$pro=true;
			}
		


			$efbFunction->openstreet_map_required_efb(0);

			if(isset($ac->AdnSPF)==true){
				//$ac
				
				$addons["AdnSPF"]=$ac->AdnSPF;
				$addons["AdnOF"]=$ac->AdnOF;
				$addons["AdnATC"]=$ac->AdnATC;
				$addons["AdnPPF"]=$ac->AdnPPF;
				$addons["AdnSS"]=$ac->AdnSS;
				$addons["AdnSPF"]=$ac->AdnSPF;
				$addons["AdnESZ"]=$ac->AdnESZ;
				$addons["AdnSE"]=$ac->AdnSE;
				$addons["AdnPDP"]=isset($ac->AdnPDP) ? $ac->AdnPDP : 0;
				$addons["AdnADP"]=isset($ac->AdnADP) ? $ac->AdnADP : 0;
			}
			
			if(isset($ac->efb_version)==false || version_compare(EMSFB_PLUGIN_VERSION,$ac->efb_version)!=0 ){
				$efbFunction->setting_version_efb_update($ac ,$pro);
			}
		}

				if(isset($ac->AdnPDP) && $ac->AdnPDP==1){
					//wmaddon
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {	
						$r = $efbFunction->update_message_admin_side_efb();
						//echo $r; 
						$efbFunction->download_all_addons_efb();
						return 0;
					}
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
					$persianDatePicker = new persianDatePickerEFB() ; 		
				}
				if(isset($ac->AdnPDP) && $ac->AdnADP==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {	
						$r = $efbFunction->update_message_admin_side_efb();
						//echo $r; 
						$efbFunction->download_all_addons_efb();
						return 0;
					}
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
					$arabicDatePicker = new arabicDatePickerEfb() ; 
				}

				if(isset($ac->AdnSS) && $ac->AdnSS==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {	
						$r = $efbFunction->update_message_admin_side_efb();
						//echo $r; 
						$efbFunction->download_all_addons_efb();
						return 0;
					}
					
				}

					


			
			
			wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),'3.8.4',true);	
			wp_enqueue_script('jquery-ui-efb');
			wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),'3.8.4',true);	
			wp_enqueue_script('jquery-dd-efb'); 
			

	
		wp_register_script('countries-js', 'https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/js/wp/countries.js', null, null, true);	
		wp_enqueue_script('countries-js');


		wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);	
		wp_enqueue_script('intlTelInput-js');

		
		wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,'3.8.4');
        wp_enqueue_style('intlTelInput-css');
		
		if( false){
			wp_register_script('logic-efb',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', null, null, true);	
			wp_enqueue_script('logic-efb');
		}
		
		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
		"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
		'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
		'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif',
		
		];
		
		$smtp =-1;
		$captcha =false;
		$smtp_m = "";
		$stng_pdate = true;
		
		if(gettype($ac)!="string"){
			if( isset($ac->siteKey)&& strlen($ac->siteKey)>5){$captcha="true";}
			if($ac->smtp=="true"){$smtp=1;}else if ($ac->smtp=="false"){$smtp=0;$smtp_m =$lang["sMTPNotWork"];}			
		}else{$smtp_m =$lang["goToEFBAddEmailM"];}

		if("fa_IR"==get_locale()){
			wp_register_script('persia_pay-efb.js',  EMSFB_PLUGIN_URL .'/public/assets/js/persia_pay-efb.js', array('jquery'),'3.8.4',true);
			wp_enqueue_script('persia_pay-efb.js');
		}

		wp_register_script('stripe_js',  EMSFB_PLUGIN_URL .'/public/assets/js/stripe_pay-efb.js', array('jquery'),'3.8.4',true);
		wp_enqueue_script('stripe_js');


		//$colors = $efbFunction->get_list_colores_template();
		$colors =[];
		

		//$location =$pro==true  ? $efbFunction->get_geolocation() :'';
		$plugins =['wpsms' => 0,'wpbaker' => 0,'elemntor'=> 0 , 'cache'=>0];
			$plugins_get = get_plugins();
			
			if (is_plugin_active('wp-sms/wp-sms.php')) {
				
				$plugins['wpsms']=1;
			}

			
		$plugins['cache'] =$efbFunction->check_for_active_plugins_cache();
		
		$location ='';
		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js',false,'3.8.4');
		wp_localize_script('Emsfb-admin-js','efb_var',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1,
			'pro' => $pro,
			'rtl' => is_rtl() ,
			'text' => $lang	,
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
			'setting'=>$ac,
			'colors'=>$colors,
			'plugins'=>$plugins
			
		));

		wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js',false,'3.8.4');
		
		
		wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,'3.8.4');
		

		
		wp_enqueue_script('efb-forms-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/forms-efb.js',false,'3.8.4');
		
		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js',false,'3.8.4');
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));

		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js',false,'3.8.4');
		

		wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js',false,'3.8.4');
		

		


		

	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){
		
		$efbFunction = $this->get_efbFunction();
		$creat=["errorCheckInputs","NAllowedscriptTag","formNcreated","newMessageReceived","newResponse","WeRecivedUrM","trackNo","url" ];
		$lang = $efbFunction->text_efb($creat);
		$this->userId =get_current_user_id();
	//	
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';
  
		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'admin-nonce' ) ) {
            $response = ['success' => false, 'm' =>  $lang['error403']];
            wp_send_json_success($response, 200);
		}
		
		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m =$lang["errorCheckInputs"];
			$response = array( 'success' => false , "m"=>$m); 
			wp_send_json_success($response,$_POST);
		} 
		
		if(isset($_POST['email']) ){$email =sanitize_email($_POST['email']);}
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;
		//$this->value = $_POST['value'];

		$valp =str_replace('\\', '', $_POST['value']);
		
		
		$valp = json_decode($valp,true);
		$valp = $efbFunction->sanitize_obj_msg_efb($valp);
		
		
		
		
		


		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){			
			$response = array( 'success' => false , "m"=> $lang["NAllowedscriptTag"]); 
			wp_send_json_success($response,$_POST);
		}
		
		
		
		//check if smsnoti axist then call add_sms_contact_efb
		$sms_msg_new_noti="";
		$sms_msg_responsed_noti="";
		$sms_msg_recived_user="";
		$sms_admins_phoneno="";

		if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1){
			
			$sms_msg_new_noti = isset($valp[0]['sms_msg_new_noti']) ?$valp[0]['sms_msg_new_noti'] :$lang["newMessageReceived"] ."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_msg_responsed_noti = isset($valp[0]['sms_msg_responsed_noti']) ? $valp[0]['sms_msg_responsed_noti'] :  $lang["newResponse"]."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_msg_recived_user = isset($valp[0]['sms_msg_recived_usr']) ? $valp[0]['sms_msg_recived_usr'] : $lang["WeRecivedUrM"] ."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_admins_phoneno = isset($valp[0]['sms_admins_phone_no']) ? $valp[0]['sms_admins_phone_no'] : "";


			unset($valp[0]['sms_msg_new_noti']);
			unset($valp[0]['sms_msg_responsed_noti']);
			unset($valp[0]['sms_msg_recived_user']);
			if(isset($valp[0]['sms_admins_phone_no'])){unset($valp[0]['sms_admins_phone_no']);}

			

		}
		
		$valx =json_encode($valp,JSON_UNESCAPED_UNICODE);
		$this->value=str_replace('"', '\\"', $valx);
		$this->insert_db();
		
		if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1 ){
			//$efbFunction->add_sms_contact_efb($this->id_,$sms_msg_new_noti,$sms_msg_recived_admin,$sms_msg_recived_user);
			//require smsefb.php and call add_sms_contact_efb
			
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
		
		
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_); 
		}else{$response = array( 'success' => false , "m"=> $lang["formNcreated"]);}
		wp_send_json_success($response,$_POST);	
	}
	
	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		
		 
		$table_name = $this->db->prefix . "emsfb_form";
		$r =$this->db->insert($table_name, array(
			'form_name' => $this->name, 
			'form_structer' => $this->value, 
			'form_email' => $this->email, 
			'form_created_by' => $this->userId, 
			'form_type'=>$this->formtype, 			
			'form_create_date' => wp_date('Y-m-d H:i:s'), 
						
		));    $this->id_  = $this->db->insert_id; 
		
	}
	public function check_temp_is_bootstrap (){
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
        return  $s;
    }//end fun


	public function get_efbFunction(){
		if(!class_exists('Emsfb\efbFunction')){
			require_once(EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php');
		}
		return new \Emsfb\efbFunction();				
	}






}

new Create();
