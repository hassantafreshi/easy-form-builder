<?php

namespace Emsfb;

use WP_REST_Response;
/**
 * Class _Public
 * @package Emsfb
 */
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
	public function __construct() {
		
		global $wpdb;
		$this->db = $wpdb;
		$this->id =-1;
		$this->pro_efb =false;
		


		add_action('rest_api_init',  @function(){
			$this->efb_uid  = get_current_user_id();
      		
			
			register_rest_route('Emsfb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
				'method'=> 'POST',
				'callback'=>  [$this,'test_fun'],
				'permission_callback' => '__return_true'
			]); 

		
			register_rest_route('Emsfb/v1','forms/message/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'get_form_public_efb'],
				'permission_callback' => '__return_true'
			]); 
			/* register_rest_route('Emsfb/v1','forms/email/send', [
				'methods' => 'POST',
				'callback'=>  [$this,'mail_send_form_api'],
				'permission_callback' => '__return_true'
			]);  */

			register_rest_route('Emsfb/v1','forms/payment/persia/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'pay_persia_sub_Emsfb_api'],
				'permission_callback' => '__return_true'
			]); 

			register_rest_route('Emsfb/v1','forms/payment/stripe/card/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'pay_stripe_sub_Emsfb_api'],
				'permission_callback' => '__return_true'
			]); 
		
			register_rest_route('Emsfb/v1','forms/response/get', [
				'methods' => 'POST',
				'callback'=>  [$this,'get_track_public_api'],
				'permission_callback' => '__return_true'
			]); 

			register_rest_route('Emsfb/v1','forms/response/add', [
				'methods' => 'POST',
				'callback'=>  [$this,'set_rMessage_id_Emsfb_api'],
				'permission_callback' => '__return_true'
			]); 

			register_rest_route('Emsfb/v1','forms/file/upload', [
				'methods' => 'POST',
				'callback'=>  [$this,'file_upload_api'],
				'permission_callback' => '__return_true'
			]); 		
		});
		
		add_shortcode( 'Easy_Form_Builder_confirmation_code_finder',  array( $this, 'EMS_Form_Builder_track' ) ); 
			
		//add_action( 'email_recived_new_message_hook_efb', array($this, 'corn_email_new_message_recived_Emsfb' ) ); //send email by cron wordpress

		$this->get_efbFunction(0);
		add_shortcode( 'EMS_Form_Builder',  array( $this, 'EFB_Form_Builder' ) );
		add_shortcode( 'ems_form_builder',  array( $this, 'EFB_Form_Builder' ) );
		 
		add_action('init',  array($this, 'hide_toolmenu'));
		
		add_action('wp_ajax_form_preview_efb', [$this, 'form_preview_efb']);  
		add_action('delete_preview_page_efb', [$this,'delete_preview_page_efb'], 10, 1);                          
		
	}


	public function enqueue_jquery(){
		
		if (!isset(wp_scripts()->registered['jquery']) || version_compare(wp_scripts()->registered['jquery']->ver , '3.6.0' , '<')) {
			$wp_version = get_bloginfo('version');	
			if (version_compare($wp_version, '6.0', '>')) {				
				wp_enqueue_script('jquery', includes_url('/js/jquery/jquery.js') , false ,'3.7.1');
			}else {
				wp_enqueue_script('jquery', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery.min-efb.js', false, '3.6.2');
			}
			
		}
	}
	
	public function hide_toolmenu(){
		// this function hide admin bar in bublic side for subscribers user
		if(is_user_logged_in()){
			$user = wp_get_current_user();
			if ( in_array( 'subscriber', (array) $user->roles ) ) {
					//hide admin bar in public pages
					show_admin_bar( false );
			}
		}	

	}


	public function EFB_Form_Builder($id){
		
		if(!is_numeric(end($id))){ return "<div id='body_efb' class='efb card-public row pb-3 efb' > <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We are sorry, but there seems to be a security error (400) with your request.','easy-form-builder')."</h3>
			<h4 style='color:#ff4b93;text-align: center;'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</h4><p></div></div>";
		}
		$this->enqueue_jquery();
		$state_form = 'not';
		$admin_form = false;
		$admin_sc = null;
		if(isset($_GET['track'])){
			$state_form =  sanitize_text_field($_GET['track']) ;
			//$admin_form =isset($_GET['user'])  && $_GET['user']=="admin"  ? true : false;
			//$admin_sc = isset($_GET['sc']) ? sanitize_text_field($_GET['sc']) : null;
			if(isset($_GET['user'])  && $_GET['user']=="admin" ) $admin_form = true;
			if(isset($_GET['sc'])) $admin_sc = sanitize_text_field($_GET['sc']);							
		}
		
		
		if(( is_user_logged_in()==false && $admin_form==true && $admin_sc==null)){
			return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('It seems that you are the admin of this form. Please login and try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
		}
		
		$table_name = $this->db->prefix . "emsfb_form";		
		$this->id = end($id);
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		if($value_form!=null){
			$typeOfForm =$value_form[0]->form_type;
			if($state_form!='not' && strlen($state_form)>7 
			&& ($typeOfForm!="register" || $typeOfForm!="login")){
				$this->id =-1;
				return $this->EMS_Form_Builder_track();
			}
		}else{
			return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'> <div class='efb text-center my-5'><div class='efb text-danger bi-exclamation-triangle-fill efb text-center display-1 my-2'></div>
			<h3 style='color:#202a8d;text-align: center;'>".esc_html__('Form does not exist !!','easy-form-builder')."</h3>
			<h4 style='color:#ff4b93;text-align: center;'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</h4></div></div>";
		}
		$this->text_ = ["somethingWentWrongPleaseRefresh","atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","mcplen","mmxplen","mxcplen","clcdetls","vmgs","required","mmplen","offlineSend","amount","allformat","videoDownloadLink","downloadViedo","removeTheFile","pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank","easyFormBuilder","rnfn","fil",'stf','total','fetf','search','jqinl','eln'];

		
		$page_builder="";		
		if((is_admin() || isset($_GET['vc_editable']) ||isset($_GET['vcv-ajax']) )){
				//+isset($_GET['vcv-ajax']) visual composer
				//+isset($_GET['vc_editable']) wpbakery
				//+is_admin for plugin like elementor
				if(isset($_GET['vc_editable'])) $page_builder='vc_editable';
				else if(isset($_GET['vc_editable'])) $page_builder = 'wpbakery';
				else if (isset($_GET['action']) && $_GET['action']=='elementor'){
					$page_builder='elementor';
					
				
					
				}
			$content="	
			
			
			<div id='body_efb' class='efb  row pb-3 efb px-2'>
			<div style='width:100%;text-align: center;'>
				<img src=".EMSFB_PLUGIN_URL . "includes/admin/assets/image/logo-easy-form-builder.svg' alt='Easy Form Builder' style='height: 80px;'>
				</div><h4 style='color:#202a8d;text-align: center;'>".esc_html__('The form will be displayed in publication or preview modes.', 'easy-form-builder')."</h4>
				<h3 style='color:#ff4b93;text-align: center;'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</h3>
			</div>
			</div>
			";

			return $content;
		}
		
		
			
		$this->public_scripts_and_css_head();
		

		//$this->public_scripts_and_css_head();
		$state="";
		$pro=  $this->pro_efb;
		$lanText= $this->efbFunction->text_efb($this->text_);
		$sid = $this->efbFunction->efb_code_validate_create( $this->id , 0, 'visit' , 0);
		$ar_core = array( 'sid'=>$sid);
	
	
		$typeOfForm =$value_form[0]->form_type;
		$value = $value_form[0]->form_structer;
		
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
			"bi-hourglass-split",
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
			"bi-check-lg",
			"bi-crosshair"
			
			
		]];
		
		$pattern = '/bi-[a-zA-Z0-9-]+/';
		 preg_match_all($pattern, $value, $icons_ );
		 //marge icons and icons_
		
		 $iconsd = array_merge($icons_[0] , $icons[0]);
		 $icons_ = array_unique($iconsd);
		$value = preg_replace('/\\\"email\\\":\\\"(.*?)\\\"/', '\"email\":\"\"', $value);
		
		$lang = get_locale();
		$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;
		$state="form";		
		$multi_exist = strpos($value , '"type\":\"multiselect\"');
		if($multi_exist==true || strpos($value , '"type":"multiselect"') || strpos($value , '"type\":\"payMultiselect\"') || strpos($value , '"type":"payMultiselect"')){
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js',false,'3.8.4');
			
			wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select-efb.css', true,'3.8.4' );
			wp_enqueue_style('Emsfb-bootstrap-select-css');
		}
		$rp= $this->get_setting_Emsfb('pub');
		$efb_m = "<p class='efb fs-7 text-center my-1'>".esc_html__('Easy Form Builder', 'easy-form-builder')."</p> ";
		
		if(gettype($rp)=="integer" && $rp==0){
			$stng=$lanText["settingsNfound"];
			$state="form";
			return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('Easy Form Builder couldn\'t locate the form settings. Please check your settings or contact support for assistance.','easy-form-builder')."</h3>".$efb_m;
		}
		$stng= $rp[0];


		$el_pro_load = strpos($value , '\"pro\":\"1\"');
			if($el_pro_load==false){
					$el_pro_load = strpos($value , '"pro":"1"');
				}
		$el_pro_load = $el_pro_load==false ? false : true;
		$this->comper_version_efb($rp[1]["version"]);
	
		$paymentType="null";
		$paymentKey="null";
		$refid = isset($_GET['Authority'])  ? sanitize_text_field($_GET['Authority']) : 'not';
		$Status_pay = isset($_GET['Status'])  ? sanitize_text_field($_GET['Status']) : 'NOK';
		$img =[];
		
		if($this->pro_efb==1){
			$efb_m= "<!--efb-->" ;

			//smssend : after filed forms check if sms send enable and send sms to admin and users
			if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
				require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php");
				$smssendefb = new smssendefb() ; 
			}

			
				if($el_pro_load==true){
					wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,'3.8.4');
					 
				}
		
				if($typeOfForm=="payment"){
					$this->setting= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');
					$r = $this->setting;
					if(gettype($r)=="string"){
						$setting =str_replace('\\', '', $r);
						$setting =json_decode($setting);
						$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
						
						if(isset($setting->activeCode) &&  md5($server_name) ==$setting->activeCode){$pro=true;}
						if(strpos($value , '\"type\":\"stripe\"') || strpos($value , '"type":"stripe"')){$paymentType="stripe";}
						else if(strpos($value , '\"type\":\"persiaPay\"') || strpos($value , '"type":"persiaPay"')){
							$paymentType="zarinPal";
						}else if(strpos($value , '\"type\":\"zarinPal\"') || strpos($value , '"type":"zarinPal"')){$paymentType="zarinPal";}
							if($paymentType!="null" && $pro==true){								
								if($paymentType=="stripe"){ 
									wp_register_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);	
									wp_enqueue_script('stripe-js');
									wp_register_script('stripepay_js', plugins_url('../public/assets/js/stripe_pay-efb.js',__FILE__), array('jquery'), '3.8.4', true);
									wp_enqueue_script('stripepay_js');
									$paymentKey=isset($setting->stripePKey) && strlen($setting->stripePKey)>5 ? $setting->stripePKey:'null';							
								}else if($paymentType=="persiaPay" || $paymentType=="zarinPal"  || $paymentType="payping" ){
									$paymentKey=isset($setting->payToken) && strlen($setting->payToken)>5 ? $setting->stripePKey:'null';
									wp_register_script('parsipay_js', plugins_url('../public/assets/js/persia_pay-efb.js',__FILE__), array('jquery'), '3.8.4', true);
									wp_enqueue_script('parsipay_js');
								}
							}

						
					}//end if payment
					$ar_core = array_merge($ar_core , array(
						'paymentGateway' =>$paymentType,
						'paymentKey' => $paymentKey
					));
				}
				if(strpos($value , '\"type\":\"switch\"') || strpos($value , '"type":"switch')){
					wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min-efb.js', array( 'jquery' ), true,'3.8.4');
				 
				}
				if(strpos($value , '\"type\":\"pdate\"') || strpos($value , '"type":"pdate"')){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {
						$this->efbFunction->download_all_addons_efb();
						return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
					}
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
					$persianDatePicker = new persianDatePickerEFB() ; 	
				}
				if(strpos($value , '\"type\":\"ardate\"') || strpos($value , '"type":"ardate"')){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {
						$this->efbFunction->download_all_addons_efb();
						return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
					}
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
					$arabicDatePicker = new arabicDatePickerEfb() ; 
				}//end if custom date
				if(strpos($value , '\"type\":\"mobile\"') || strpos($value , '"type":"mobile"')){
					$img = [
						'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js'
						];
					wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);	
					wp_enqueue_script('intlTelInput-js');
					wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,'3.8.4');
					wp_enqueue_style('intlTelInput-css');
				}
				if(strpos($value , '\"logic\":\"1\"') || strpos($value , '"logic":"1"')){
					wp_register_script('logic-efb',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', null, null, true);	
					wp_enqueue_script('logic-efb');
				}
		
			}
				$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';
				$send=array();
				//translate v3
				$fs =str_replace('\\', '', $value_form[0]->form_structer);
				$formObj= json_decode($fs,true);
				if(($formObj[0]["stateForm"]==true || $formObj[0]["stateForm"]==1) &&  is_user_logged_in()==false ){
					$typeOfForm="";
					$value="";
					$stng="";
				}
				if($formObj[0]["thank_you"]=="rdrct"){
					$formObj[0]["rePage"]="";
					$val_ = json_encode($formObj,JSON_UNESCAPED_UNICODE);
					$value = str_replace('"', '\\"', $val_);
				}
				if (($value_form[0]->form_type=="login" || $value_form[0]->form_type=="register")){
					if( is_user_logged_in()){
						$typeOfForm ="userIsLogin";
						$user = wp_get_current_user();
						$state="userIsLogin";
						$send['state']=true;
						$send['display_name']=$user->data->display_name;
						$send['user_email']=$user->data->user_email;
						$send['user_login']=$user->data->user_login;
						$send['user_nicename']=$user->data->user_nicename;
						$send['user_registered']=$user->data->user_registered;
						$send['user_image']=get_avatar_url(get_current_user_id());
						$value=$send;
					}
				}
				$ar_core = array_merge($ar_core , array(
					'ajax_value' =>$value,
					'type' => $typeOfForm,
					'state' => $state,
					'language' => $lang,
					'id' => $this->id,			  
					'form_setting' => $stng,
					//'nonce'=> wp_create_nonce("public-nonce"),
					'poster'=> $poster,
					'rtl' => is_rtl(),
					'text' =>$lanText ,
					'pro'=>$this->pro_efb,
					'wp_lan'=>get_locale(),
					'location'=> "",
					'v_efb'=>EMSFB_PLUGIN_VERSION,
					//'nonce_msg'=> wp_create_nonce($code),
					'images' => $img,
					'rest_url'=>get_rest_url(null),
					'page_id'=>get_the_ID(),
					'page_builder'=>$page_builder
				) );
				wp_localize_script( 'Emsfb-core_js', 'ajax_object_efm',$ar_core);  
		 $k="";
		// $pro=false;		
		 //$stng = $this->get_setting_Emsfb('pub');
		 $stng = $this->pub_stting;
		 if(gettype($stng)!=="integer" && $lanText["settingsNfound"]){
			// $valstng= json_decode($stng);			
			 if( ($formObj[0]['captcha']==1) && (isset($this->pub_stting['siteKey'])==true) && strlen($this->pub_stting['siteKey'])>1)
			 {					
				
				 $k =$this->pub_stting['siteKey'];
				 $k = "<script>let sitekye_emsFormBuilder='".$k."'</script>";
				 $r_captcha = $this->efbFunction->check_and_enqueue_google_captcha_efb($lang);
				 if($r_captcha==false){
					 $k ="<script>let sitekye_emsFormBuilder=2; const c_r_efb ='reCAPTCHA Error:".$lanText['tfnapca']."'</script>";
				 }
			 }


			  $s_m ='<!--efb-->';
			  if( is_string($value) && (strpos($value , '\"type\":\"maps\"') !== false || strpos($value , '"type":"maps"') !== false)){
				error_log('maps!!!!');
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
		 $width =0;		// $style =$this->bootstrap_icon_efb();
		
		 if($formObj[0]["stateForm"]==true ){
			$content ="
			".$this->bootstrap_icon_efb($icons_)."		 
			<div id='body_efb' class='efb  row pb-3 efb px-2'> <div class='efb text-center my-5'>
			<div class='efb bi-shield-lock-fill efb text-center display-1 my-2'></div><h3 class='efb  text-center fs-5'>". $lanText["formPrivateM"]."</h3>
			 ".$efb_m."
			 ".$s_m."
			</div> </div>";
			return $content; 
		 }else{

			 $content="	
			
			 ".$this->bootstrap_icon_efb($icons_)."
			 <div id='body_efb' class='efb  row pb-3 efb px-2'>
			 <div class='efb text-center my-5'>			 
			 ".$this->loading_icon_public_efb('',$lanText["pleaseWaiting"] , $lanText["fil"])."			
			 ".$efb_m."
			 ".$s_m."
			 </div>
			 </div><div id='alert_efb' class='efb mx-5'></div>
			 ".$k." 
			 ";
		 }
		return $content;
	}
	public function EMS_Form_Builder_track(){
		
		$this->enqueue_jquery();
		
		//if($this->id!=-1){return esc_html__('Easy Form Builder' , 'easy-form-builder');}
		$this->id=0;
		$this->public_scripts_and_css_head();

		
		
		//Confirmation Code show
		$lang = get_locale();
		$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;
				
		$this->get_efbFunction(0);
				//translate v2
				$text=["spprt","atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","mcplen","mmxplen","mxcplen","mmplen","offlineSend","message","clsdrspn","createdBy","easyFormBuilder","payAmount","payment","id","methodPayment","ddate","updated","methodPayment","interval","file","videoDownloadLink","downloadViedo","pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","somethingWentWrongPleaseRefresh","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank","rnfn","fil",'stf','total','ttlprc','fetf','jqinl','eln'];				
				$text= $this->efbFunction->text_efb($text) ;
		$state="tracker";
		$pl= $this->get_setting_Emsfb('pub');
		$stng= $pl[0];
		
		$s_m ='<!--efb-->';
		if(gettype($stng)=="integer" && $stng==0){
			$stng=$text["settingsNfound"];
			$state="tracker";
		}else{
			   $valstng= json_decode($stng);
			
			
			   
			   
			   if(isset($valstng->siteKey) && isset($valstng->scaptcha) && $valstng->scaptcha==true){
				   wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload', null , null, true);
				   wp_enqueue_script('recaptcha');
				}
				
				
				if(isset($valstng->osLocationPicker) && $valstng->osLocationPicker==true){
					$sm = $this->efbFunction->openstreet_map_required_efb(1);				
					if($sm==false){
						$s_m =" <script>alert('OpenStreetMap Error:".$text['tfnapca']."')</script>";
					}
				}
		}

		$this->pro_efb = $valstng->pro;
		$this->comper_version_efb($pl[1]["version"]);

					
		if($this->pro_efb==1){
 
			wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,'3.8.4');
		 
		}
		//$location = $this->pro_efb==true  ? $this->efbFunction->get_geolocation() :'';
		$location = '';
		//efb_code_validate_create( $fid, $type, $status, $tc)
		$sid = $this->efbFunction->efb_code_validate_create( 0 , 0, 'visit' , 0);
		$sc = isset($_GET['sc']) ? sanitize_text_field($_GET['sc']) : 'null';
		$usr =wp_get_current_user();
		$username = '';
		if(gettype($usr)!='integer') $username = $usr->display_name  ;
		if($username=='') $username = $sc!='null' ? $text["spprt"] :'' ;
		wp_localize_script( 'Emsfb-core_js', 'ajax_object_efm',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'state' => $state,
			   'v_efb'=>EMSFB_PLUGIN_VERSION,
			   'language' => $lang,			  
			   'form_setting' => $stng,
			   'user_name'=> $username,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
			   'rtl' => is_rtl(),
			   'text' =>$text,
			   'pro'=>$this->pro_efb,
			   'wp_lan'=>get_locale(),			   
			   'location'=>$location,
			   'sid'=>$sid,
			   'rest_url'=>get_rest_url(null),
			   'page_id'=>get_the_ID(),
			   'sc'=>$sc
		 ));  

		 $icons_ =[
			'bi-clipboard-check','bi-hand-thumbs-up',
			'bi-exclamation-triangle-fill',
			"bi-exclamation-diamond-fill",		
			"bi-check2-square",
			"bi-hourglass-split",
			"bi-chat-square-text",
			"bi-download",
			"bi-star-fill",
			"bi-hourglass-split",
			"bi-hand-thumbs-up",
			"bi-envelope",
			"bi-arrow-right",
			"bi-arrow-left",
			"bi-search",
			'bi-paperclip',
			"bi-upload",
			"bi-file-earmark-richtext",
			"bi-x-lg"
		];

		


		 $val = $this->pro_efb==true ? '<!--efb.app-->' : '<a href="https://whitestudio.team"  class="efb text-decoration-none" target="_blank"><p class="efb fs-7 text-darkb mb-4" style="text-align: center;">'.$text['easyFormBuilder'].'<p></a>';
	 	$content="<script>let sitekye_emsFormBuilder='' </script>
		 ".$this->bootstrap_icon_efb($icons_)."
		".$s_m."
		<div id='body_tracker_emsFormBuilder' class='efb '><div id='alert_efb' class='efb mx-5 text-center'></div>
		".$this->loading_icon_public_efb('',$text["pleaseWaiting"], $text['fil'])."</div>";	
		return $content; 
	}
	function public_scripts_and_css_head(){
		wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style-efb.css', true,'3.8.4');
		wp_enqueue_style('Emsfb-style-css');
		
		 wp_register_script('Emsfb-core_js', plugins_url('../public/assets/js/core-efb.js',__FILE__), array('jquery'), '3.8.4', true);				
		 wp_enqueue_script('Emsfb-core_js');
		
		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js',array('jquery'), '3.8.4', true);		
		$ar_core = array() ;
		wp_localize_script( 'efb-main-js', 'efb_var',$ar_core);  
		
		if(is_rtl()){
			wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl-efb.css', true ,'3.8.4');
			wp_enqueue_style('Emsfb-css-rtl');
		}
		$googleCaptcha=false;
		
		wp_register_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css', true,'3.8.4');
		wp_enqueue_style('Emsfb-bootstrap-css');

		
	  }
	
	  public function get_form_public_efb($data_POST_){
		$data_POST = $data_POST_->get_json_params();
		
		
		$text_ =["somethingWentWrongPleaseRefresh","pleaseMakeSureAllFields","bkXpM","bkFlM","mnvvXXX","ptrnMmm","ptrnMmx",'payment','error403','errorSiteKeyM',"errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","clcdetls","vmgs","youRecivedNewMessage","WeRecivedUrM","thankRegistering","welcome","thankSubscribing","thankDonePoll","thankFillForm","trackNo",'fernvtf',"msgdml"];

		
		$efbFunction =  $this->get_efbFunction(1);
				
		if(empty($this->efbFunction)) $this->efbFunction =$efbFunction;
		$sid = sanitize_text_field($data_POST['sid']);
		$this->id = sanitize_text_field($data_POST['id']);
		$page_id = sanitize_text_field($data_POST['page_id']);
		$data_POST['url']= $url = sanitize_url($data_POST['url']);	
		
		$s_sid = $this->efbFunction->efb_code_validate_select($sid , $this->id);
		$this->lanText= $this->efbFunction->text_efb($text_);
		$setting;
		
		$this->cache_cleaner_Efb($page_id);
		
		
		if ($s_sid !=1){
			//error_log('s_sid is not valid!!');
			$m =  $this->lanText["somethingWentWrongPleaseRefresh"]. '<br>'. esc_html__('Error Code','easy-form-builder') .': 403';
			$response = array( 'success' => false  , 'm'=>$m); 
			wp_send_json_success($response,$data_POST);
		} 
		$user_id = 1;
		$to_list_admin=[];
		$r=  $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: $this->get_setting_Emsfb('setting');
		
		if(gettype($r)=="string"){
			$r = str_replace("\\","",$r);
			$setting =json_decode($r,true);
		}else{
			
		$setting =$r;
		}
		
		if( isset($setting["emailSupporter"])) array_push($to_list_admin ,$setting["emailSupporter"] );
		
		$pro = false;
		$type =sanitize_text_field($data_POST['type']);
		$email=get_option('admin_email');
		
		$rePage ="null";
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs = isset($value_form) ? str_replace('\\', '', $value_form[0]->form_structer) :'';
		$not_captcha=$formObj= $trackingCode_state = $send_email_to_user_state =  $check = "";
		$email_user= array();
		$this->value = $data_POST['value'];
		$this->value =str_replace('\\', '', $this->value);
		$valo = json_decode($this->value , true);
		$smsnoti=0;
		$phone_numbers=[[],[]];
		$email_array_state = false;
		$send_email_to_user_state = false;
		 function emails_list( &$email_user , $pointer , $email , $state_array){
			//error_log(json_encode($email_user));
			$state_array= true;
			if(empty($email)){	
			 return false;
			}
			if(!isset($email_user[$pointer])) $email_user[$pointer] = $state_array ? [] : '';
			if($state_array){
				if (strpos($email, ',') !== false){
					$emails = explode(',', $email);
					foreach ($emails as $email) {
						if(!in_array($email, $email_user[$pointer])){ array_push($email_user[$pointer] ,$email); return true;}
					}
				}else{
					if(!in_array($email, $email_user[$pointer])){ array_push($email_user[$pointer] ,$email); return true;}
				}
			}else{
				//find in the string
				//error_log(json_encode($email_user[$pointer]));
				
				
				$pos = strpos($email_user[$pointer],$email);
				//error_log($pos);
				if($pos===false){
					!empty($email_user[$pointer]) ? $email_user[$pointer] .= ' , '.$email : $email_user[$pointer] =$email;
					return true;
				}
			}
		}
		if(isset($setting['sms_config']) && $setting['sms_config']=="wpsms"){
			$numbers = isset($setting['phnNo']) ? $setting['phnNo'] :[];
			//seprate string numbers by comma
			if(strlen($numbers)>5)$phone_numbers[0] = explode(',',$numbers);
			$smsnoti=1;
			
			
		}
		$smsnoti = strpos($fs,'\"smsnoti\":\"1\"') !==false || $smsnoti==1 ? 1 : 0;

		
		
		//check if smsnoti is active in string $fs
		
		if($fs!=''){
				$formObj=  json_decode($fs,true);
				$fs = null;
				$email_array_state = isset($formObj[0]["email_send_type"]) ? $formObj[0]["email_send_type"] : false;
				if( !isset($valo['logout']) && !isset($valo['recovery']) ){
				$email_fa = $formObj[0]["email"];
				if(!empty($email_fa)){	
					emails_list($email_user , 0 , $email_fa ,$email_array_state);
					/* if (strpos($email_fa, ',') !== false){
						$emails = explode(',', $formObj[0]["email"]);
						foreach ($emails as $email) {
							if(!in_array($email, $email_user[0])) array_push($email_user[0] ,$email);
						}
					}else{
						array_push($email_user[0] ,$email_fa);
					} */
					$send_email_to_user_state = true;
				}

				//$email_fa = $formObj[0]["email"];

				
				$trackingCode_state = $formObj[0]["trackingCode"]==true || $formObj[0]["trackingCode"]=="true" || $formObj[0]["trackingCode"]==1 ? 1 : 0;
				
				
				//if( $fs_obj[0]["trackingCode"]==true || $fs_obj[0]["trackingCode"]=="true" || $fs_obj[0]["trackingCode"]==1)
				
				//$type = $formObj[0]["type"];
				
				
				if($type!=$formObj[0]["type"]){
					
					$response = array( 'success' => false  , 'm'=>$this->lanText["fernvtf"]); 
					wp_send_json_success($response,$data_POST);
				}
				if($formObj[0]["thank_you"]=="rdrct"){
					$rePage= $this->string_to_url($formObj[0]["rePage"]);
				}
				
				$valobj =[] ;
				$stated = 0;	
				$rt;	
									
				if(isset($data_POST['url']) && strlen($data_POST['url'])>5 ){
					$ar = ['http://wwww.'.$_SERVER['HTTP_HOST'] , 'https://wwww.'.$_SERVER['HTTP_HOST'] ,'http://'.$_SERVER['HTTP_HOST'], 'https://'.$_SERVER['HTTP_HOST']];
					foreach ($ar as  $r) {
						$c=strpos($data_POST['url'],$r);
						if(gettype($c)!='boolean' && $c==0){
						//if(strpos($item['url'],$r)==0){
							$stated=1;
						}
					}
					if($stated==1){
						$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
					}
				}
				if($stated==0){
					$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
					wp_send_json_success($response,$data_POST);
				}
				$mr='';
				$stated = 1;
				$form_condition = '';
				if(isset($formObj[0]['booking']) && $formObj[0]['booking']==1) $form_condition='booking';
				
				foreach ($formObj as $key =>$f){
						$rt =null;	
						$in_loop=true;						
						if($key<2) continue;
						if($stated==0){break;}
						$it= array_filter($valo, function($item) use ($f,$key,&$stated ,&$email_user,&$rt,&$formObj,&$in_loop,&$mr,$form_condition,&$smsnoti,&$phone_numbers) { 

							
							if($in_loop==false){
								return;
							}
							if((( isset($f['disabled'])==true &&  $f['disabled']==1  && isset($f['hidden']) ==false) 
							|| ( isset($f['disabled'])==true && $f['disabled']==1 && isset($f['hidden'])==true && $f['hidden']==false ) )
							&&( $item['id_'] == $f['id_'] || $f['id_']==$item['id_'])
							&& strlen($item['value'])>1 ){
								
								
								$stated=0;
								$in_loop==false;
								return;
							}
							$t =strpos(strtolower($item['type']),'checkbox');
					
							if(isset($f['id_']) && isset($item['id_']) && ( $f['id_']==$item['id_'] 
							||  gettype($t)=="integer" && $f['id_']==$item['id_ob'])
							||( ( $f['type']=="persiaPay" ||$f['type']=="persiapay" || $f['type']=="payment") && $formObj[0]["type"]=='payment')
							|| ( $item['type']=='r_matrix' && $f['id_']==$item['id_ob'])
						
							) {   							
							if(isset($f['name'])){
						    $mr = $this->lanText["mnvvXXX"];
							$mr =str_replace('XXX', "<b>".$f['name']."</b>", $mr );}
							
								
							switch ($f['type']) {
								case 'email':
									$stated=0;
									if(isset($item['value'])){
										$stated=1;
										$item['value'] = sanitize_email($item['value']);
										$rt= $item;
										$l=strlen($item['value']);
										if (!filter_var($item['value'], FILTER_VALIDATE_EMAIL)) {
											
											$mr =str_replace('XXX', $f['name'], $mr );
											$stated=0;
										}
										//don't chenge these line!
										$e_ar = isset($formObj[0]["email_send_type"]) ? $formObj[0]["email_send_type"] : false;
										if((isset($f['milen']) && $f['milen']> $l)||( isset($f['mlen']) && $f['mlen']< $l) ) {$stated=0;}
										if(isset($f['noti'])== true && intval($f['noti'])==1)  emails_list($email_user , 1 , $item['value'] , $e_ar);
										//end 
										
									}
									$in_loop=false;
									break;
								case "date":
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										$v = explode("-", $item['value']);							
										if(count($v)==3 && checkdate($v[1],$v[2],$v[0]) ){
										//wp_checkdate
											$stated=1;
											$rt= $item;
											$current_date = date('Y-m-d');
											if(isset($f['milen']) && $f['milen']!=''){
												$f['milen'] = intval($f['milen'])==1 ? $current_date :$f['milen'];
												if($f['milen']!='' && (strtotime($f['milen'])>strtotime($item['value']) || strtotime($f['milen'])>strtotime($item['value'])) ){
													$stated=0;
												}
											}
											if(isset($f['mlen']) && $f['mlen']!=''){
												$f['mlen'] = intval($f['mlen'])==1 ? $current_date :$f['mlen'];
												if($f['mlen']!='' && (strtotime($f['mlen'])<strtotime($item['value']) || strtotime($f['mlen'])<strtotime($item['value'])) ){
													$stated=0;
												}
											}
										} else {
											$rt= "";
											$stated=0;
										}									
									}
									$in_loop=false;
									break;
								case 'url':
									$stated=0;
									if(isset($item['value'])){
										$stated=1;
										
										$item['value'] = sanitize_url($item['value']);
										$l=strlen($item['value']);
										if((isset($f['milen']) && $f['milen']> $l)||( isset($f['mlen']) && $f['mlen']< $l) ) {$stated=0;}
									}
									# code...
									$rt= $item;
									$in_loop=false;
									break;
								case 'mobile':
									
									
									
									
									$stated=0;
									
									if(isset($item['value'])){
										$stated=0;
										$item['value'] = sanitize_text_field($item['value']);
										$item['value'] = preg_replace('/\s+/', '', $item['value']);	
										
										if(isset($f['smsnoti']) && intval($f['smsnoti'])==1 ){
											$smsnoti=1;

											array_push($phone_numbers[1],$item['value']);
											
										}
										
										
										$l = isset($f["c_n"]) && count($f['c_n'])>=1 ? $f["c_n"] : ['all'];
										 array_filter($l, function($no) use($item , &$stated){
											
											//$stated=0;
											
											
											
											
											$v = strpos($item['value'] , '+'.$no);
											
											
											if ( strpos($item['value'] , '+'.$no) === 0 || $no=='all') $stated=1;
										});		
										
										
									}
									# code...
									$rt= $item;
									$in_loop=false;
									break;
								case 'radio':							
								case 'payRadio':
								case 'chlRadio':
								case 'imgRadio':
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										array_filter($formObj, function($fr,$ki ) use(&$item,&$rt,&$stated,&$formObj,$form_condition ,&$mr) { 
											if(isset($fr['id_']) && isset($item['id_ob']) && $fr['id_']==$item['id_ob']){
												$item['value']=$fr['value'];
												//$rt = $item;
												$stated=1;
												$t=strpos($item['type'],'pay');
												if($t!=false){
													$item['price']=$fr['price'];
												}
												$t=strpos($item['type'],'img');
												if(isset($fr['src'])){
													//array_push($item,array('src'=>$fr['src']));
													$item['src']=$fr['src'];													
													$item['sub_value']=$fr['sub_value'];													
												}
												if($form_condition=='booking')	{
													
													
													
													
													if(isset($fr['dateExp'])==true){
														
														
														
														//$fr['dateExp'] ='04-04-2023';
														if(strtotime($fr['dateExp'])<strtotime(wp_date('Y-m-d'))){
															$stated=0;
															$mr = $this->lanText["bkXpM"];
															$mr =str_replace('XXX', $fr['value'], $mr );
														}
														
													}
													if(isset($fr['mlen'])==true){														
														if($fr['mlen']<=$fr['registered_count']){
															$stated=0;
															$mr = $this->lanText["bkFlM"];
															$mr =str_replace('XXX', $fr['value'], $mr );
														}else{
															
															$formObj[$ki]['registered_count'] =(int) $formObj[$ki]['registered_count'] +1;
															
														}
													}
													//if time exists check
													//if time biger $stated
													//if mlen biger then 1 check registered_count
												}	
												$rt= $item;
												return;
											}
										},ARRAY_FILTER_USE_BOTH );
									}
									
									$in_loop=false;
									break;
								case 'switch':
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										array_filter($formObj, function($fr) use($item,&$rt,&$stated) { 											
											if(isset($fr['id_']) && isset($item['id_']) && $fr['id_']==$item['id_']){											
												$item['value']= $item['value']=='1' ?   $fr['on'] : $fr['off'];
												$rt = $item;
												$stated=1;											
												return;
											}
										});
									}
									$in_loop=false;
									break;
								case 'option':					
									$t = strpos(strtolower($item['type']),'checkbox');
									if(gettype($t)!='boolean'){
									}
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										//array_filter($formObj, function($fr) use($item,&$rt,&$stated) { 											
											if((isset($f['id_']) && isset($item['id_ob']) && $f['id_']==$item['id_ob'] )
											||(isset($f['id_']) && isset($item['id_']) && $f['type']=="chlCheckBox"  && $f['id_']==$item['id_ob']) ){
												$item['value']=$f['value'];
												$rt = $item;
												$stated=1;
												$t=strpos($item['type'],'pay');
												//gettype($t)!='boolean'
												if(gettype($t)!='boolean'){
													$item['price']=$f['price'];
												}
												
												
												if($form_condition=='booking')	{
													
													
													
													
													if(isset($f['dateExp'])==true){
														//error_log($f['dateExp']);
														//$f['dateExp']="04-04-2023";
														//error_log(strtotime($f['dateExp']));
														//error_log(strtotime(wp_date('Y-m-d')));
														if(strtotime($f['dateExp'])<strtotime(wp_date('Y-m-d'))){
															$stated=0;
															$mr = $this->lanText["bkXpM"];
															$mr =str_replace('XXX', $f['value'], $mr );
														}
													}
													if(isset($f['mlen'])==true){
														if($f['mlen']<=$f['registered_count']){
															$stated=0;
															$mr = $this->lanText["bkFlM"];
															$mr =str_replace('XXX', $f['value'], $mr );
														}else{
															
															$formObj[$key]['registered_count'] =(int) $formObj[$key]['registered_count'] +1;
															
														}
													}
													//if time exists check
													//if time biger $stated
													//if mlen biger then 1 check registered_count
												}
											}
										//});
									}
									$in_loop=false;
									break;
								
								case 'r_matrix':
									
									$stated=0;
									$item['value'] = sanitize_text_field($item['value']);
									if($item['value']<1 || $item['value']>5){
										$m =  $this->lanText["somethingWentWrongPleaseRefresh"]. '<br>'. esc_html__('Error Code','easy-form-builder') .': 600';
										$response = array( 'success' => false  , 'm'=>$m); 
										wp_send_json_success($response,$data_POST);
									}
									$stated=1;
									$item['name'] = $f['value'];
									$item['label']="";
									foreach($formObj as $k=>$v){
										if($v['type']=='table_matrix' && $v['id_']==$item['id_']){										
											$item['label']=$v['name'];
											break;
										}
									}
									
									
									$rt= $item;
									$in_loop=false;
									break;

								case 'multiselect':
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										$rt=null;
										$rs = explode("@efb!", $item['value']);
										array_filter($formObj, function($fr) use($item,&$rt,$rs) { 
											foreach ($rs as $k => $v) {
												if(isset($item['type'])  && $fr['type']=="option" && isset($fr['value']) && isset($v) && $fr['value']==$v &&  $fr['parent']==$item['id_']){
													$rt== null ? $rt = $v.'@efb!' : $rt =$rt . $v.'@efb!';
												}
											}											
										});
										if($rt!=null) $stated=1;
										$item['value']=$rt;
										$rt=$item;
									}
										$in_loop=false;
									break;
								case 'select':
								case 'paySelect':
								
									// این بخش از نظر امنیتی تغییر کند
									// find in the obj of forms  id_ == $item["id_ob"] return value;
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										
										
										
										
										
										
											array_filter($formObj, function($fr,$ki) use($item,&$rt,&$stated,&$formObj,$form_condition,&$mr) { 											
												if(isset($item['type'])  && $fr['type']=="option" && isset($fr['value']) && isset($item['value']) && $fr['value']==$item['value'] &&  $fr['parent']==$item['id_']){
													$stated=1;
													$item['value']=$fr['value'];
													$rt = $item;
													$in_loop=false;
													if($form_condition=='booking')	{
														error_log('booking con inside select');
														error_log($ki);
														error_log(wp_date('Y-m-d'));
														if(isset($fr['dateExp'])==true){
															error_log($fr['dateExp']);													
															error_log(strtotime($fr['dateExp']));
															error_log(strtotime(wp_date('Y-m-d')));
															if(strtotime($fr['dateExp'])<strtotime(wp_date('Y-m-d'))){
																$stated=0;
																$mr = $this->lanText["bkXpM"];
																$mr =str_replace('XXX', $fr['value'], $mr );
															}
														}
														if(isset($fr['mlen'])==true){
															if($fr['mlen']<=$fr['registered_count']){
																$stated=0;
																$mr = $this->lanText["bkFlM"];
																$mr =str_replace('XXX', $fr['value'], $mr );
															}else{
																error_log($formObj[$ki]['registered_count']);
																$formObj[$ki]['registered_count'] =(int) $formObj[$ki]['registered_count'] +1;
																error_log($formObj[$ki]['registered_count']);
															}
														}
														//if time exists check
														//if time biger $stated
														//if mlen biger then 1 check registered_count
													}
													return;
												}
											},ARRAY_FILTER_USE_BOTH);
										
											
											

										
									}
									$in_loop=false;
									break;
									case 'stateProvince':
										case 'statePro':
										case 'conturyList':
										case 'country':
										case 'city':
										case 'cityList':
											$stated=0;
											if(isset($item['value'])){
												$stated=1;
											$item['value']= sanitize_text_field($item['value']);
											$rt=$item;
											}
											
											
											$in_loop=false;
										break;
								case 'sample':									
									$rt= $item;
									$in_loop=false;
									break;
								case 'persiaPay':									
								case 'persiapay':									
								case 'payment':		
									if($formObj[0]["type"]=='payment'){
										$item['amount'] = sanitize_text_field($item['amount']);					
										$item['id_'] = sanitize_text_field($item['id_']);					
										$item['name'] = sanitize_text_field($item['name']);					
										$rt= $item;
										$in_loop=false;
										$stated=1;
									}else{
										$stated=0;
									}
									break;
								case 'file':	
								case 'dadfile':	
									$d = $_SERVER['HTTP_HOST'];
									//$p = strpos($item['url'],'http://'.$d);
									//don't change value stated because always file is sending 
									if(isset($item['url']) && strlen($item['url'])>5 ){
										$stated=0;
										$ar = ['http://wwww.'.$d , 'https://wwww.'.$d ,'http://'.$d, 'https://'.$d ];
										$s = 0 ;
										foreach ($ar as  $r) {
											$c=strpos($item['url'],$r);
											if(gettype($c)!='boolean' && $c==0){
											//if(strpos($item['url'],$r)==0){
												$s=1;
											}
										}								
											if($s==1 ){
												$stated=1;
												$item['url'] = sanitize_url($item['url']);								
												$rt= $item;
											}else{
												$item= null;
												$rt=null;
												$stated=0;
											}
									}
										$in_loop=false;
								break;
								case 'esign':
										$stated=0;
										if(isset($item['value']) && strpos($item['value'],'data:image/png;base64,')==0){ 
											$stated=1;
											$item['value'] = sanitize_text_field($item['value']);
											$rt= $item;
										}
										$in_loop=false;
								break;
								case 'maps':
									$stated=1;
									$rt= $item;
									$c = 0;
									//$item['value'] =$item['value'];
									foreach ($item['value'] as $key => $value) {
										$c+=1;										
										if(is_numeric($value["lat"])==false || is_numeric($value["lng"])==false){ 
											//mnvvXXX
											$stated=0;$rt =null;};
									}
									if($c!=$f["mark"]){ 
										$stated=0;
										$rt =null;
										 $mr = $this->lanText["mnvvXXX"];
										 $mr =str_replace('XXX', "<b>".$f['name']."</b>", $mr );
										}
									//mark
									$in_loop=false;
								break;
								case 'color':
										$stated=0;	
										$l=strlen($item['value']);
										if(isset($item['value']) && strpos($item['value'],'#')==0 && $l==7){ 
											$stated=1;
											$item['value'] = sanitize_text_field($item['value']);
											$rt= $item;
										}
										$in_loop=false;
								break;								
								case 'range':
								case 'number':
								case 'prcfld':
										$stated=0;	
										if(isset($item['value']) && is_numeric($item['value'])){ 
											$stated=1;
											$item['value'] = sanitize_text_field($item['value']);
											$rt= $item;
											$l=strlen($item['value']);											
											if(strcmp($f['type'],"range")!==0 && ((isset($f['milen']) && $f['milen']> $l)||( isset($f['mlen']) && $f['mlen']< $l))  ) {												
												$stated=0;}
											else if(((isset($f['milen']) && $f['milen']> $item['value'])||( isset($f['mlen']) && $f['mlen']< $item['value'])) && strcmp($f['type'],"range")==0 ) {
												$stated=0;}
										}
										$in_loop=false;
								break;								
								default:
									$stated=0;
									$t	= strtolower($item['type']);
									$t = strpos(strtolower($f['type']),'checkbox');
									$b = strpos(strtolower($f['type']),'chlcheckbox');
									if(gettype($t)=="integer" || (isset($f['type']) && $f['type']=='table_matrix')){
										
										$stated=1;
										break;
									}																	
									if(isset($item['value']) ){
										$stated=1;
										$item['value'] = sanitize_text_field($item['value']);
										$l=mb_strlen($item['value'], 'UTF-8');
										if(isset($f['milen'])!=true  &&   isset($f['mlen'])!=true){	$stated=1;	}						
										else if((isset($f['milen'])==true && $f['milen']>0 && $f['milen']> $l)) {											
											$mr = $this->lanText["ptrnMmm"];
											$mr =str_replace('XXX', "<b>".$f['name']."</b>", $mr );
											$mr =str_replace('NN', "<b>".$f['milen']."</b>", $mr );											
											$stated=0;
										}
										else if( isset($f['mlen'])==true && $f['mlen']>0   && $f['mlen']< $l) {
											$mr = $this->lanText["ptrnMmx"];							
											$mr =str_replace('NN', "<b>".$f['mlen']."</b>", $mr );
											$mr =str_replace('XXX', "<b>".$f['name']."</b>", $mr );
											$stated=0;}
									}
									//$item['value'] =  'test';
									$rt= $item;
									$in_loop=false;
								break;
							}
							}
					});

					if(isset($rt)){
						array_push($valobj,$rt);
					};	
				}
				$count =  count($valobj);
				if($count==0){
					$stated=0;
					if($mr=='')$mr=$this->lanText["pleaseMakeSureAllFields"];
				}
				
				array_push($valobj,array('type'=>'w_link','value'=>$url,'amount'=>-1));

				

				$this->id = $type=="payment" ? sanitize_text_field($data_POST['payid']) :$this->id ;
				$not_captcha= $type!="payment" ? $formObj[0]["captcha"] : "";
				if($stated==0){
					
					$response = array( 'success' => false  , 'm'=>$mr); 
					wp_send_json_success($response,$data_POST);
				}

				
					$this->value = json_encode($valobj,JSON_UNESCAPED_UNICODE);
					$this->value = str_replace('"', '\\"', $this->value);
					if($form_condition=='booking'){
						$table_name = $this->db->prefix . "emsfb_form";
					
						$id = sanitize_text_field($data_POST['id']);
						$value =json_encode($formObj,JSON_UNESCAPED_UNICODE);
						
						$r = $this->db->update($table_name, ['form_structer' => $value], ['form_id' => $id]);
						
					}
					
			}
		}else if ($fs==''){
			$m = "Error 404 ";
			$response = array( 'success' => false  , 'm'=>$m); 
			wp_send_json_success($response,$data_POST);
		}
		if(true){

			

			
			$captcha_success="null";
			$r= $this->setting ;
			$formObj = array_slice($formObj, 0, 1);
			
			if(gettype($r)=="string"){
				// error_log('setting is string');
				$setting =str_replace('\\', '', $r);
				$r=null;
							
				$setting =json_decode($setting);
				$this->setting=$setting;
				$email_fa = $setting->emailSupporter;
				//if(!empty($email_fa) && !in_array($email_fa, $email_user[0])) array_push($email_user[0] ,$email_fa);
				if(!empty($email_fa) ){ 
					 emails_list($email_user , 0 , $email_fa , $email_array_state);
					 $send_email_to_user_state = true;
					}
				if(isset($setting->femail) && is_email($setting->femail)) $email_user[2] = $setting->femail ;
				//strlen($email_fa)>0 ? $email_fa .=','.$setting->emailSupporter : $email_fa = $setting->emailSupporter;

				$secretKey= isset($setting->secretKey) && strlen($setting->secretKey)>5 ? $setting->secretKey : null;
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				if(isset($setting->activeCode) &&!empty($setting->activeCode) && md5($server_name) ==$setting->activeCode){
					$pro=true;
				}
				$response=$data_POST['valid'];
				$args = array(
					'secret'        => $secretKey,
					'response'     => $response,
				);
				if(gettype($formObj)!="string" && $formObj[0]['type']!='payment' && $formObj[0]['captcha']==true && strlen($response)>5 && $formObj[0]["captcha"]==true){				
					if(isset($setting->secretKey) && strlen($setting->secretKey)>5){
						$verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
							
							$captcha_success =json_decode($verify['body']);						
						//$not_captcha=false;	 
					}else{
						//secretkey is not valid		
						$response = array( 'success' => false  , 'm'=>$this->lanText["errorSiteKeyM"]); 
						wp_send_json_success($response,$data_POST);
						return;
					}
				}
			}
			if ($type=="logout" || $type=="recovery") {$not_captcha==true;}
			if ($not_captcha==true && ( $captcha_success=="null" || $captcha_success->success!=true )  ) {
			$response = array( 'success' => false  , 'm'=>$this->lanText["errorCaptcha"]); 
			wp_send_json_success($response,$data_POST);
			die();
			}else if ($not_captcha==false || ($not_captcha==true &&  $captcha_success->success==true)) {
			if(empty($data_POST['value']) || empty($data_POST['name']) || empty($data_POST['id']) ){
				$response = array( 'success' => false , "m"=>$this->lanText["pleaseEnterVaildValue"]); 
				wp_send_json_success($response,$data_POST);
				die();
			}
			$this->name = sanitize_text_field($data_POST['name']);
			$this->id = sanitize_text_field($data_POST['id']);		
			if($send_email_to_user_state==true ){
				  array_filter($valobj, function($item) use($formObj ,&$emailuser){ 
					if(isset($item['id_']) && $item['id_']==$formObj[0]["email_to"]){
						$emailuser = $item["value"];
						return true;}					
				});				
				emails_list($email_user , 1 , $emailuser , $email_array_state);
			}
							$ip = $this->ip=$this->get_ip_address();						
							//$this->location = $efbFunction->iplocation_efb($ip,1);
					switch($type){
						case "form":						
							$check=	$this->insert_message_db(0,false);
							$nnc = wp_create_nonce($check);
																										
							$this->efbFunction->efb_code_validate_update($sid ,'send' ,$check );
							$response = array( 'success' => true  ,'ID'=>$data_POST['id'] , 'track'=>$check  , 'ip'=>$ip,'nonce'=>$nnc); 
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }

							if(isset($formObj[0]['smsnoti']) && $formObj[0]['smsnoti']==1 ) {
						
								$this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,$check );

							}												
							if($send_email_to_user_state==true){
								//$email_user[0]=$email_fa;
								emails_list($email_user , 0 , $email_fa , $email_array_state);
								//array_push($email_user[0],$email_fa);
								$state_email_user = $trackingCode_state==1 ? 'notiToUserFormFilled_TrackingCode' : 'notiToUserFormFilled';
								$state_of_email = ['newMessage',$state_email_user];
								$msg_content='null';
								if(isset($formObj[0]["email_noti_type"]) && $formObj[0]["email_noti_type"]=='msg'){
									$msg_content =$this->email_get_content($valobj ,$check);
									$msg_content = str_replace("\"","'",$msg_content);
									
								}
								$msg_sub = 'null';
								if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
									$msg_sub = $formObj[0]["email_sub"];
								}
								//error_log(json_encode($email_user));
								 $this->send_email_Emsfb_( $email_user,$check ,$pro,$state_of_email,$url,$msg_content,$msg_sub );
							}
							wp_send_json_success($response,$data_POST);
						break;
						case "payment":								
							$id = sanitize_text_field($data_POST['payid']);
							$table_name_ = $this->db->prefix . "emsfb_msg_";
							$currentDateTime = date('Y-m-d H');
							$payment_getWay =isset($data_POST['payment']) ? sanitize_text_field($data_POST['payment']) :'stripe';
							if( strlen($id)<7 && $payment_getWay=="zarinPal"){
								$response = array( 'success' => false , "m"=>"خطای داده های پرداختی ، صفحه را رفرش کنید"); 
								wp_send_json_success($response,$data_POST);
								die();
							}		
							$value = $this->db->get_results( "SELECT content,form_id FROM `$table_name_` WHERE track = '$id' AND read_=2" );	
							$trackId= $id;
							if($value!=null){
								$vv=$value[0]->content;
								$vv_ =str_replace('\\', '', $vv);
								$vv = json_decode($vv_,true);
								$fs =str_replace('\\', '', $this->value);
								$valobj = json_decode($fs , true);
								$filtered = array_filter($valobj, function($item) use ($vv) { 
									if(strpos($item['type'], 'pay')===false){return $item;}					
								});
								$amount =0;
								foreach ($vv as $k => $v) {
									if(isset($v['price'])) $amount +=$v['price'];
								}								
								$result;
								if($payment_getWay=="persiaPay"){
									//zarinPal validation code
										$amount = $amount;
									if(gettype($r)=="string" && $fs!=''){
										$setting =str_replace('\\', '', $r);
										$setting =json_decode($setting);
										$r=null;
										$TokenCode = $setting->payToken;
										$data = array("merchant_id" => $TokenCode, "authority" => sanitize_text_field($data_POST['auth']), "amount" => $amount);
										$jsonData = json_encode($data);
										$msg="ok";
										if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/")) {
											$msg = " خطای تنظیمات : با مدیر وبسایت تماس بگیرید . نیاز به نصب مجدد درگاه می باشد";
										}else{
											include(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/zarinpal.php");
											$persiaPay = new zarinPalEFB() ;
											$result = $persiaPay->validate_payment_zarinPal($jsonData);
											if($result['errors']){
												$msg = $result['errors']['message'];
											}
										}
									}else{
										$msg = 'خطای تنظیمات : با مدیر وبسایت تماس بگیرید ، خطای 406 ' ;
										//تنظیمات یافت نشد
									}
									if($msg!="ok"){
										$response = array( 'success' => false , "m"=>$this->$msg); 
										wp_send_json_success($response,$data_POST);
										die();
									}
									date_default_timezone_set('Iran');
									$result=[
										'id_' =>"payment",
										'name' => "payment",
										'amount' => 0,
										'total' => $amount,
										'type' => "payment",
										"paymentGateway"=>$payment_getWay,
										"paymentCreated"=>wp_date( __( 'Y/m/d \a\t g:ia', 'textdomain' ) ),
										"paymentmethod"=>'کارت',
										"paymentIntent"=>sanitize_text_field($data_POST['auth']),
										"paymentCard"=>$result['data']['card_pan'], 
										"refId"=>$result['data']['ref_id'],
										"paymentcurrency"=>'IRR'
									];
									//end zarinPal
								}
								$form_id = $value[0]->form_id;
								$table_name = $this->db->prefix . "emsfb_form";
								$fs = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$form_id'" );
								$fs = isset($fs[0]->form_structer) ? str_replace('\\', '', $fs[0]->form_structer) :'';
								if($fs==''){
									$response = array( 'success' => false  ,'m'=>'Error 406'); 
									wp_send_json_success($response,$data_POST);
									die();
								}
								$fs = json_decode ($fs,true);
								if($fs[0]["thank_you"]=="rdrct"){
									$rePage= $this->string_to_url($fs[0]["rePage"]);								
								}
								$valobj =[] ;
								foreach ($fs as $f){
								$it= array_filter($filtered, function($item) use ($f) { 							
									if(isset($f['id_']) && isset($item['id_']) && $f['id_']==$item['id_'] && $f['name']==$item['name']   ) {         										
										return $item;
									}
								});
								$valobj =   empty($valobj) ? $it : array_merge((array)$valobj,$it);								
								//stripe edit					
								if($payment_getWay=="persiaPay")array_push($valobj, $result);
								}
								$fs=json_encode($valobj);
								$filtered = array_unique(array_merge($valobj,$vv), SORT_REGULAR);
								$fs=[];
								foreach ($filtered as $key => $v) {
									
									array_push($fs,$v);
								}
								array_push($fs,array('type'=>'w_link' , 'id_'=>'w_link' , 'id'=>'w_link','value'=>$url,'amount'=>-1));
								$filtered=json_encode($fs ,JSON_UNESCAPED_UNICODE);	
								$fss=str_replace('"', '\\"', $filtered);
								$this->value = sanitize_text_field($fss);									
								$this->id = sanitize_text_field($data_POST['payid']);			
								$check=$this->update_message_db();	
								
								
								// $admin_email = $formObj[0]["email"];
								 if($send_email_to_user_state==true){
									//$email_user[0]=$email_fa;
									$state_email_user = $trackingCode_state==1 ? 'notiToUserFormFilled_TrackingCode' : 'notiToUserFormFilled';
									$state_of_email = ['newMessage',$state_email_user];
									$msg_content='null';
									if(isset($formObj[0]["email_noti_type"]) && $formObj[0]["email_noti_type"]=='msg'){
										$msg_content =$this->email_get_content($fs ,$trackId);
										$msg_content = str_replace("\"","'",$msg_content);
										
									}
	
									$msg_sub = 'null';
									if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
										$msg_sub = $formObj[0]["email_sub"];
									}
									 $this->send_email_Emsfb_( $email_user,$trackId ,$pro,$state_of_email,$url,$msg_content,$msg_sub );
								}								
								 if(isset($formObj[0]['smsnoti']) && $formObj[0]['smsnoti']==1 ) $this->efbFunction->sms_ready_for_send_efb($form_id, $phone_numbers,$url,'fform' ,'wpsms' ,$check);
								 $fs=[];
								
								
							}else{
								$response = array( 'success' => false  ,'m'=>esc_html__('Error Code','easy-form-builder').'</br>'. esc_html__('Payment Form','easy-form-builder')); 
								wp_send_json_success($response,$data_POST);
							}
						
							$m = "Error 500";
							$response = $check == 1 ? array( 'success' => true  ,'ID'=>$data_POST['id'] , 'track'=>$this->id ,'nonce'=>wp_create_nonce($this->id)  , 'ip'=>$ip) :  array( 'success' => false  ,'m'=>$m);
							$this->efbFunction->efb_code_validate_update($sid ,'pay' ,$check );
							if($rePage!="null" && $check == 1){$response = array( 'success' => true  ,'m'=>$rePage); }
							wp_send_json_success($response,$data_POST);
							//unset($_SESSION["val_efb"]);
						break;
						case "register":
							$username ;
							$password;
							$email = 'null';
							$m = str_replace("\\","",$this->value);
							$registerValues = json_decode($m,true);					
							foreach ($registerValues as &$rv) {
								if(isset($rv['id_'])){
									if ($rv['id_'] == 'passwordRegisterEFB'){
										$password=$rv['value'];
										$rv['value'] = str_repeat('*',strlen($rv['value']));
									}else if($rv['id_'] == 'usernameRegisterEFB'){
										$username=$rv['value'];
									}else if($rv['id_'] == 'emailRegisterEFB'){
										$email=$rv['value'];
									}
								}
								
							}
							$r =$this->new_user_validate_efb($username,$email,$password);
							if(gettype($r)=="string"){
								$response = array( 'success' => false , 'm' =>$r); 
								wp_send_json_success($response,$data_POST);
							}
							$this->value=json_encode($registerValues,JSON_UNESCAPED_UNICODE);
							$creds = array();
							$creds['user_login'] =esc_sql($username);
							$creds['user_pass'] = esc_sql($password);
							$creds['user_email'] = esc_sql($email);
							$creds['role'] = 'subscriber';			
							$creds['rich_editing '] = 'false';			
							$creds['user_registered'] = wp_date('Y-m-d H:i:s');			
							$state =wp_insert_user($creds);
						
							//get user id of inserted user
							
							$response;
														
							$m =$this->lanText["createAcountDoneM"];
							// hide password
							if(gettype($state)=="object"){
								foreach($state->errors as $key => $value){
									$m= $value[0];
								}
								$response = array( 'success' => false , 'm' =>$m); 
							}else{							
								if($email!="null"){									
									$this->ip= $this->get_ip_address();
									//$ip = $this->ip;
									$check=	$this->insert_message_db(0,false);
									//$state= get_user_by( 'email', $email);
									//if(gettype($state)=="object"){
										
										$to = $email;
										//$email_user[1]=$email;
										emails_list($email_user , 1 , $email , $email_array_state);
										//if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email!="null" ){
											//just show first and last chrs of $password
											$firstChar = $password[0];
											$lastChar = $password[strlen($password)-1];

											$maskedPassword = $firstChar . str_repeat('*', strlen($password) - 2) . $lastChar;

											$ms ="<p>".  esc_html__('Username','easy-form-builder')  .":".$username ." </p> <p>".  esc_html__('Password','easy-form-builder') .":".$maskedPassword."</p>";
											
											//$email_user[0]=$email_fa;
											
											$state_of_email = ['newUser','register'];
											if($send_email_to_user_state==true)
											{
												$msg_sub = 'null';
												if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
													$msg_sub = $formObj[0]["email_sub"];
												}
												$this->send_email_Emsfb_( $email_user,$ms ,$pro,$state_of_email,$url,'null',$msg_sub);
											} 

											if(isset($formObj[0]['smsnoti']) && $formObj[0]['smsnoti']==1 ) $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,$check);
											
									   // }
										//$sent = wp_mail($to, $subject, strip_tags($message), $headers);
									//}
									$this->efbFunction->efb_code_validate_update($sid ,'register' ,$check );
								}
								$response = array( 'success' => true , 'm' =>$m); 
								if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }
							}
							
							wp_send_json_success($response,$data_POST);
						break;
						case "login":
							$username ;
							$password;
							$m = str_replace("\\","",$this->value);
							$loginValue = json_decode($m,true);
							foreach($loginValue as $value){
								$state =-1; //0 username 1 password
								foreach($value as $key=>$val){
									if ($key=="id_"){
										if($val=='emaillogin') $state =0;
										if($val=='passwordlogin') $state =1;
									}
									if($key=="value" && $state==0){
										$username=$val;
										$state =-1;
									}
									if($key=="value" && $state==1){
										$password=$val;
										$state =-1;
									}
								}
							}
							$creds = array();
							$creds['user_login'] =esc_sql($username);
							$creds['user_password'] = esc_sql($password);
							$creds['remember'] = true;
							$user = wp_signon( $creds, false );
							if(isset($user->ID)){
								$userID = $user->ID;
								do_action( 'wp_login', $creds['user_login'] ,$user );				
								wp_set_current_user($user->ID );
								wp_set_auth_cookie( $user->ID, true, false );
								$send=array();
								$send['state']=true;
								$send['display_name']=$user->data->display_name;
								$send['user_email']=$user->data->user_email;
								$send['user_login']=$user->data->user_login;
								$send['user_nicename']=$user->data->user_nicename;
								$send['user_registered']=$user->data->user_registered;
								$send['user_image']=get_avatar_url($user->data->ID);
								$response = array( 'success' => true , 'm' =>$send); 
								if($rePage!="null"){
									$response = array( 'success' => true  ,'m'=>$rePage); 
								}		
								$this->efbFunction->efb_code_validate_update($sid ,'login' ,'login' );		
								if(isset($formObj[0]['smsnoti']) && $formObj[0]['smsnoti']==1 ) $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,'');				
								wp_send_json_success($response,$data_POST);
							}else{
								// user not login
								$send=array();
								$send['state']=false;
								$send['pro']=$pro;
								$send['error']=$this->lanText["incorrectUP"];
								$response = array( 'success' => true , 'm' =>$send); 
								
								wp_send_json_success($response,$data_POST);
							}
						break;
						case "logout":
							$this->efbFunction->efb_code_validate_update($sid ,'logout' ,'logout' );
							wp_logout();
							$response = array( 'success' => true  );
							wp_send_json_success($response,$data_POST);
						break;
						case "recovery":
							$m = str_replace("\\","",$this->value);
							$userinfo = json_decode($m,true);
							//email
							$email="null";
							foreach($userinfo as $value){
								if(is_email($value)){
									$email = sanitize_email($value);
									break;
								}
							}
							if($email!="null"){
								$state= get_user_by( 'email', $email);
								if(gettype($state)=="object"){
   								 	$newpass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0,9);									
									$id =(int) $state->data->ID;
									 wp_set_password($newpass ,$id);
									$to = $email;
									$efb ='<p> '. $this->lanText["sentBy"] . home_url(). '</p>';
									if($pro==false) $efb ='<p> '. esc_html__("from").''. home_url(). ' '. $this->lanText["sentBy"] .'<b>['. esc_html__('Easy Form Builder' , 'easy-form-builder') .']</b></p>' ;
									$subject ="". esc_html__("Password recovery")."[".get_bloginfo('name')."]";
									$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
									if(isset($email_user[2]) && is_email($email_user[2])) $from =  blog_info('name')." <".$email_user[2].">";
									$message ='<!DOCTYPE html> <html> <body><h3>'.  esc_html__('New Password')  .':'.$newpass.'</h3>
									<p> '.$efb. '</p>
									</body> </html>';
									$headers = array(
									 'MIME-Version: 1.0\r\n',
									 '"Content-Type: text/html; charset=ISO-8859-1\r\n"',
									 'From:'.$from.''
									 );
									$sent = wp_mail($to, $subject, strip_tags($message), $headers);
								}
							}
							$m=		$this->lanText["newPassM"];						
							$response = array( 'success' => true , 'm' =>$m); 
							$this->efbFunction->efb_code_validate_update($sid ,'repass' ,'repass' );
							wp_send_json_success($response,$data_POST);
						break;
						case "subscribe":
							$check=	$this->insert_message_db(0,false);
						
							if($send_email_to_user_state==true){
								//$email_user[0]=$email_fa;
								//$state_email_user = $trackingCode_state=='subscribe';
								$state_of_email = ['newMessage','subscribe'];
								$msg_content='null';
								if(isset($formObj[0]["email_noti_type"]) && $formObj[0]["email_noti_type"]=='msg'){
									$msg_content =$this->email_get_content($valobj ,$check);
									$msg_content = str_replace("\"","'",$msg_content);
									
								}
								$msg_sub = 'null';
								if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
									$msg_sub = $formObj[0]["email_sub"];
								}
								 $this->send_email_Emsfb_( $email_user,$check ,$pro,$state_of_email,$url,$msg_content,$msg_sub );
							}
				
							$response = array( 'success' => true , 'm' =>$this->lanText["done"]); 
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }
							$this->efbFunction->efb_code_validate_update($sid ,'nwltr' ,'nwltr' );
							wp_send_json_success($response,$data_POST);
						break;
						case "survey":
							//$ip = $this->ip;
							$check=	$this->insert_message_db(0,false);
						
							if($send_email_to_user_state==true){
								//$email_user[0]=$email_fa;
								//$state_email_user = $trackingCode_state=='subscribe';
								$state_of_email = ['newMessage',"survey"];
								$msg_content='null';
								if(isset($formObj[0]["email_noti_type"]) && $formObj[0]["email_noti_type"]=='msg'){
									$msg_content =$this->email_get_content($valobj ,$check);
									$msg_content = str_replace("\"","'",$msg_content);
									
								}
								$msg_sub = 'null';
								if(isset($formObj[0]["email_sub"]) && $formObj[0]["email_sub"]!=''){
									$msg_sub = $formObj[0]["email_sub"];
								}
								$this->send_email_Emsfb_( $email_user,$check ,$pro,$state_of_email,$url,$msg_content,$msg_sub );
							}
							if(isset($formObj[0]['smsnoti']) && $formObj[0]['smsnoti']==1 ) $this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,$check);
							$response = array( 'success' => true , 'm' =>$this->lanText["surveyComplatedM"]);
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); } 
							$this->efbFunction->efb_code_validate_update($sid ,'poll' ,'poll' );
							wp_send_json_success($response,$data_POST);
						break;
						case "reservation":
						break;
						default:									
						$response = array( 'success' => false  ,'m'=>$this->lanText["somethingWentWrongPleaseRefresh"]); 
						wp_send_json_success($response,$data_POST);
					}
		}
		//recaptcha end
		}else{
			$response = array( 'success' => false , "m"=>$this->lanText["errorSettingNFound"]); 
			wp_send_json_success($response,$data_POST);
		}
	  }//end function 
	  public function get_track_public_api($data_POST_) {	
		//error_log('get_track_public_api')	;
		$data_POST = $data_POST_->get_json_params();
		//$this->public_scripts_and_css_head();
		$this->get_efbFunction(0);
		$text_ = ["spprt","somethingWentWrongPleaseRefresh",'error403',"errorMRobot","enterVValue","guest","cCodeNFound"];
		$lanText= $this->efbFunction->text_efb($text_);
		$sid = sanitize_text_field($data_POST['sid']);
		
		$s_sid = $this->efbFunction->efb_code_validate_select($sid , 0);
		
		if ($s_sid !=1 || $sid==null){
			$m =  $lanText["somethingWentWrongPleaseRefresh"]. '<br>'. esc_html__('Error Code','easy-form-builder') .': 403';
		$response = array( 'success' => false  , 'm'=>$m); 
		wp_send_json_success($response,$data_POST);
		} 
		$response=$data_POST['valid'];
		$captcha_success =[];
		$not_captcha=true;
		if(gettype($this->setting)=="string"){
		$r=str_replace('\\', '', $this->setting);
			 $setting =json_decode($r);
			 $r=null;
		}
		 $strR = json_encode($captcha_success);
		 if (!empty($captcha_success) &&$captcha_success->success==false &&  $not_captcha==false ) {
		  $response = array( 'success' => false  , 'm'=> $lanText["errorMRobot"]); 
		  wp_send_json_success($response,$data_POST);
		 }
		 else if ((!empty($captcha_success) && $captcha_success->success==true) ||  $not_captcha==true) {
			if(empty($data_POST['value']) ){
				$response = array( 'success' => false , "m"=>$lanText["enterVValue"]); 
				wp_send_json_success($response,$data_POST);
				die();
			}		
			$id = sanitize_text_field($data_POST['value']);
			$this->ip=$this->get_ip_address();
			$ip = $this->ip;
			//$this->location = $this->efbFunction->iplocation_efb($ip,1);			
			$table_name = $this->db->prefix . "emsfb_msg_";
			$value = $this->db->get_results( "SELECT content,msg_id,track,date FROM `$table_name` WHERE track = '$id'" );				
			if($value!=null){
				
				$id=$value[0]->msg_id;
				
				$id = preg_replace('/[,]+/','',$id);
				$this->id =$id;
				$table_name = $this->db->prefix . "emsfb_rsp_";
				$content = $this->db->get_results( "SELECT * FROM `$table_name` WHERE msg_id = '$id'" );
				
				foreach($content as $key=>$val){					
					$r = (int)$val->rsp_by;
					if ($r>0){
						$usr =get_user_by('id',$r);
						$val->rsp_by= $usr->display_name;
					}else if ($r==-1){
						$val->rsp_by=$lanText["spprt"];
					}else{
						$val->rsp_by=$lanText["guest"];
					}				 
				}
			}
			$r = false;
			$code = 'efb'.$this->id;
			$code =wp_create_nonce($code);
			if($value!=null){
				$r=true;
				$response = array( 'success' => true  , "value" =>$value[0] , "content"=>$content,'nonce_msg'=> $code , 'id'=>$this->id); 
			}else{
				$response = array( 'success' => false  , "m" =>$lanText["cCodeNFound"]); 
			}
			wp_send_json_success($response,$data_POST);
			}
			//send_email to admin of page
	  }//end function get_track_public_api

	public function insert_message_db($read,$uniqid){
		if(isset($read)==false) $read=0;
		if($uniqid==false) $uniqid= date("ymd").substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 5) ;
		$table_name = $this->db->prefix . "emsfb_msg_";
		$this->db->insert($table_name, array(
			'form_title_x' => $this->name, 
			'content' => $this->value, 
			'form_id' => $this->id, 
			'track' => $uniqid, 
			'ip' => $this->ip, 
			'read_' => $read, 
			'date'=>wp_date('Y-m-d H:i:s')
		));    return $uniqid; 
	}//end function
	public function update_message_db(){
		$table_name = $this->db->prefix . "emsfb_msg_";
		return $this->db->update( $table_name, array( 'content' => $this->value , 'read_' =>0,  'ip'=>$this->ip , 'read_date'=>wp_date('Y-m-d H:i:s') ), array( 'track' => $this->id ) );
		//, '%d' ,'%s'
		//,'read_' =>0  , 'ip'=>$this->ip
	}
	public function get_ip_address() {
        //source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {$ip = $_SERVER['REMOTE_ADDR'];}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }
	public function file_upload_public(){
		
        $_POST['id']=sanitize_text_field($_POST['id']);
        $_POST['pl']=sanitize_text_field($_POST['pl']);
        $_POST['nonce_msg']=sanitize_text_field($_POST['nonce_msg']);
		$page_id = sanitize_text_field($_POST['page_id']);
        $vl=null;
		//validate sid here
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{
            $id = $_POST['id'];
            $table_name = $this->db->prefix . "emsfb_form";
            $vl  = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");
            if($vl!=null){              
                if(strpos($vl , '\"type\":\"dadfile\"') || strpos($vl , '\"type\":\"file\"')){                   
                    $vl ='efb'.$id;
                    //'efb'.$this->id
                }
            }
        }
		if (check_ajax_referer('public-nonce','nonce')!=1 && check_ajax_referer($vl,"nonce_msg")!=1){
			$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$this->text_ = empty($this->text_)==false ? $this->text_ :['error403',"errorMRobot","errorFilePer"];
	
		$efbFunction =  $this->get_efbFunction(1);
		
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
		if (in_array($_FILES['file']['type'], $arr_ext)) { 			
			$name = 'efb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION) ;
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["file"]["tmp_name"]));				
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$_FILES['file']['type']); 
			  wp_send_json_success($response,$_POST);
		}else{
			$response = array( 'success' => false  ,'error'=>$this->lanText["errorFilePer"]); 
			wp_send_json_success($response,$_POST);
			die('invalid file '.$_FILES['file']['type']);
		}
	}//end function 
	public function file_upload_api(){		
		$efbFunction =  $this->get_efbFunction(1);
		$_POST['id']=sanitize_text_field($_POST['id']);
        $_POST['pl']=sanitize_text_field($_POST['pl']);
        $fid=sanitize_text_field($_POST['fid']);
		$sid = sanitize_text_field($_POST['sid']);
		$page_id = sanitize_text_field($_POST['page_id']);
		
		
		
		
		

		$s_sid = $this->efbFunction->efb_code_validate_select($sid ,  $fid);
		
		if ($s_sid !=1 || $sid==null){
			
			
		$response = array( 'success' => false  , 'm'=>esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder') .'<br>'. esc_html__('Error Code','easy-form-builder') . ": 402"); 
		wp_send_json_success($response,200);
		} 

		$this->cache_cleaner_Efb($page_id);
        //check validate here
        $vl=null;
		$have_validate =0;
		$temp=0;
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{
			
            $id = $_POST['id'];
            $table_name = $this->db->prefix . "emsfb_form";
            $vl  = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$fid'");		
            if($vl!=null){   
				if(gettype($vl)=="string"){
					$temp = strpos($vl , '\"type\":\"dadfile\"') || strpos($vl , '\"type\":\"file\"') ? true : false;
				}
				
				
                if($temp==false){  

                    $response = array( 'success' => false  , 'm'=>esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder') .'<br>'. esc_html__('Error Code','easy-form-builder') . ": 601"); 
					wp_send_json_success($response,200);
                }
				

				//$temp = strpos($vl , '\"value\":\"customize\"');
				
			
				if(strpos($vl , '\"value\":\"customize\"')!=false){
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
		$_FILES['async-upload']['name'] = sanitize_file_name($_FILES['async-upload']['name']);

				
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
				$valid = in_array($_FILES['async-upload']['type'], $arr_ext);		
			}
			


		if($have_validate==1){
			if(gettype($vl)=="string"){
				$val_ = str_replace('\\', '', $vl);
				$vl = json_decode($val_);}
			
			foreach($vl as $key=>$val){
				
				if($key>1 && ($val->type=="dadfile" || $val->type=="file") && $val->id_==$_POST['id']){
					
					$val->file_ctype = strtolower($val->file_ctype);
					
					$valid_types = explode(',', str_replace(' ', '', $val->file_ctype));
					//error_log(json_encode($valid_types));
					$file_name = $_FILES['async-upload']['name'];
					//error_log($file_name);
					$ext = strtolower(substr($file_name, strrpos($file_name, '.') + 1));
					//error_log($ext);
					//$valid = in_array($ext, $valid_types);
					foreach($valid_types as $val){
						//error_log($val);
						if($val==$ext){
							$valid=true;
							break;
						}
					}
					//error_log($valid);
					break;
				}
			}

		}
		
		if ($valid) { 
			
			$name = 'efb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["async-upload"]["name"], PATHINFO_EXTENSION) ;
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["async-upload"]["tmp_name"]));				
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$_FILES['async-upload']['type']); 
			  wp_send_json_success($response,200);
		}else{
			$response = array( 'success' => false  ,'error'=>$this->lanText["errorFilePer"]); 
			wp_send_json_success($response,200);
			die('invalid file '.$_FILES['async-upload']['type']);
		}
	}//end function
	public function set_rMessage_id_Emsfb_api($data_POST_) {		
		$data_POST = $data_POST_->get_json_params();
		$this->text_ = empty($this->text_)==false ? $this->text_ :["error400","somethingWentWrongPleaseRefresh","atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","clcdetls","vmgs","required","mcplen","mmxplen","mxcplen","mmplen","offlineSend","settingsNfound","error405","error403","videoDownloadLink","downloadViedo","pleaseEnterVaildValue","errorSomthingWrong","nAllowedUseHtml","guest","messageSent","MMessageNSendEr",
		"youRecivedNewMessage","trackNo","WeRecivedUrM","thankFillForm","msgdml","spprt"];

	
		$efbFunction =  $this->get_efbFunction(1);

		
		$this->lanText= $this->efbFunction->text_efb($this->text_);
		$sid = sanitize_text_field($data_POST['sid']);
		$rsp_by = sanitize_text_field($data_POST['user_type']);
		$sc = isset($data_POST['sc']) ? sanitize_text_field($data_POST['sc']) : 'null';
		$track = sanitize_text_field($data_POST['track']);
		
		$s_sid = $this->efbFunction->efb_code_validate_select($sid , 0);
		$page_id = sanitize_text_field($data_POST['page_id']);
		if ($s_sid !=1 || $sid==null){
			$m = '<b>'. $this->lanText["somethingWentWrongPleaseRefresh"]. '<br> '. esc_html__('Error Code','easy-form-builder') .': 403 </br></b>';
		$response = array( 'success' => false  , 'm'=>$m ); 
		wp_send_json_success($response,200);
		} 
		$this->id =sanitize_text_field($data_POST['id']);
		$by ="";
		if(empty($data_POST['message']) ){
			$response = array( 'success' => false , "m"=>$this->lanText["pleaseEnterVaildValue"]); 
			wp_send_json_success($response,200);
		}
		if(empty($this->id) ){			
			$response = array( 'success' => false , "m"=>$this->lanText["errorSomthingWrong"]); 
			wp_send_json_success($response,200);
		}
		if($this->isHTML($data_POST['message'])){
			$response = array( 'success' => false , "m"=>$this->lanText["nAllowedUseHtml"]); 
			wp_send_json_success($response,200);
		}

		$this->cache_cleaner_Efb($page_id);
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: $this->get_setting_Emsfb('setting');

		if(gettype($r)=="string"){
			$r =str_replace('\\', '', $r);
			$setting =json_decode($r);
			$this->setting = $setting;
			$secretKey=isset($setting->secretKey) && strlen($setting->secretKey)>5 ?$setting->secretKey:null ;
			$email = isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5 ?$setting->emailSupporter :null  ;
			$pro = isset($setting->activeCode) &&  strlen($setting->activeCode)>5 ? $setting->activeCode :null ;
			$email_key = isset($setting->email_key) && strlen($setting->email_key)>5 ?$setting->email_key:null ;
			if($sc!='null' && $email_key==null){
				$response = array( 'success' => false , "m"=>$this->lanText["error400"]); 
				wp_send_json_success($response,200);
			}else{

			}
			$response=$data_POST['valid'];
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
						switch ($f->type) {											
							case 'allformat':	
								$d = $_SERVER['HTTP_HOST'];
								//$p = strpos($item['url'],'http://'.$d);
								//don't change value stated because always file is sending 
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
								//$item['value'] =  'test';
								$in_loop=false;
							break;
						}
						if($stated==0){
							$response = array( 'success' => false  , 'm'=>$this->lanText["error405"]); 
							wp_send_json_success($response,200);
						}
				}
				$m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);
				$ip =$this->ip= $this->get_ip_address();
				//$this->location = $efbFunction->iplocation_efb($ip,1);
				//
				
				$id = preg_replace('/[,]+/','',$id);
				//preg_replace('/(@efb@)+/','/',$rePage);
				
				
				$table_name = $this->db->prefix . "emsfb_msg_";
				
				$value=null;
				$value = $this->db->get_results( "SELECT * FROM `$table_name` WHERE msg_id = '$id'" );
				if($value==null|| $value[0]->read_==4){
					
					$response = array( 'success' => false  , 'm'=>$this->lanText["error405"]); 
					wp_send_json_success($response,200);
					die();
				}
				$valn =str_replace('\\', '', $value[0]->content);
				
				$msg_obj = json_decode($valn,true);
				$vv_="";
				$lst = end($msg_obj);
				$link_w = $lst['type']=="w_link" ? $lst['value'] : 'null';
			   
			   
				$table_name = $this->db->prefix . "emsfb_rsp_";	
					
				$read_s = $rsp_by=='admin' ? 1 :0;
				$by=$this->lanText["guest"];
				if($read_s==1){
					$by = get_user_by('id',$this->efb_uid);
				}
				if($sc!='null'){				
					$email_key = $this->setting->email_key;
					$md5 = md5($track.$email_key);
					if ($md5==$sc){
						$read_s =1;
						if($this->efb_uid==0) $this->efb_uid = -1; 		
						$by = $this->lanText["spprt"];		
						$rsp_by ='admin';
					}else{
						$response = array( 'success' => false  , 'm'=>$this->lanText["error405"]); 
					    wp_send_json_success($response,200);
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
				$track = $value[0]->track;
				$table_name = $this->db->prefix . "emsfb_msg_";	
				$this->db->update($table_name,array('read_'=>$read_s), array('msg_id' => $id) );
				
				$email_usr ="";
				
				if($this->efb_uid!=0 && $this->efb_uid!==-1){
					$usr= wp_get_current_user();
					$by = $usr->user_nicename;
					$email_usr = $usr->user_email;
				}
				$form_id  = $value[0]->form_id;
				$table_name = $this->db->prefix . "emsfb_form";
				$vald = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$form_id'" );
				$valb =str_replace('\\', '', $vald[0]->form_structer);
				$valn= json_decode($valb,true);
				
				$usr;
				$valb=null;
				
				//$id =$valn[0]["email_to"];
				$users_email =array();;
				
				if(isset($id)){
					foreach ($msg_obj as $key => $value) {
						
						if(isset($value['id_']) && $value['id_']==$valn[0]["email_to"]){
							// $users_email= $value["value"];
							array_push($users_email,$value["value"]);
							break;
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
					//parsing form structer for find mobile input and active smsnoti attrebute
					foreach($valn as $val){
						if($val['type']=="mobile" && isset($val['smsnoti']) && intval($val['smsnoti'])==1){
							array_push($have_noti_id,$val['id_']);
						}
					}

					//parsing message recived 
					if(!empty($have_noti_id)){
						foreach ($msg_obj as $value) {
							
							
							
							if($value['type']=="mobile" && in_array($value['id_'],$have_noti_id)){
								
								array_push($phone_numbers[1],$value['value']);
								
							}
						}
					}
					
				$tt = $rsp_by=='admin' ? 'respadmin' : 'resppa';
					//$efbFunction
				
				if(isset($setting->sms_config) && ($setting->sms_config=="wpsms" || $setting->sms_config=='ws.team') ) $efbFunction->sms_ready_for_send_efb($form_id, $phone_numbers,$link_w,$tt ,$setting->sms_config ,$track);
				

				//parsing from_Structer for find type="mobile" and smsnoti=1

			
					
				}
				$user_eamil=[[],[],null];
				if (isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5){
					// array_push($to ,$setting->emailSupporter);
					// $user_eamil[0]=$setting->emailSupporter.",";
					array_push($user_eamil[0],$setting->emailSupporter);
				}
				if(isset($setting->femail)) $user_eamil[2]=$setting->femail;

				$email_fa = $valn[0]["email"];		
						
				if (isset($email_fa) && strlen($email_fa)>5){					
					// $user_eamil[0]==null ? $user_eamil[0]=$email_fa : $user_eamil[0].=$email_fa.",";
					array_push($user_eamil[0],$email_fa);
				}

				$links=$link_w;
				// error_log('check before send');
				// error_log(json_encode($user_eamil));
				$email_status =["",""];
			    !empty($users_email) ? $user_eamil[1]= $users_email : 0;
				 
				if($rsp_by=='admin'){
					
					//send email noti to users
					//$links[1] = $link_w."?track=".$track;	
					$email_status[1]= "newMessage";
					$email_status[0] ='respRecivedMessage';
					$user_eamil[0]=[null];
				
				}else{
					$email_status[1]= "respRecivedMessage";
					$email_status[0] ='newMessage';
				}
				$this->send_email_Emsfb_($user_eamil,$track,$pro,$email_status,$links ,'null','null');
				$response = array( 
				'success' => true , "m"=>$this->lanText["messageSent"] , "by"=>$by,
				'track'=>$track,
				'nonce_msg'=>wp_create_nonce($track)); 	
				
				wp_send_json_success($response,200);
					
		}else{
			$m = $this->lanText["settingsNfound"] . '</br>' . $this->lanText["MMessageNSendEr"] ;
			$response = array( 'success' => false , "m"=>$m, "by"=>$by);
			wp_send_json_success($response,200);	
		}
	}//end function

	public function send_email_Emsfb_($to , $track ,$pro , $state,$link ,$content ='null' , $sub ='null'){	
		$link_w=[];
		$cont=[];
		$subject=[];
		$message=[];
		$homeUrl = home_url();
		$blogName = get_bloginfo('name');
		for($i=0;$i<2;$i++){
			if(strlen($link)>5){
				$link_w[$i] =strpos($link,'?')!=false  ? $link.'&track='.$track : $link.'?track='.$track;
				if($i==0){			
						$s= isset($this->setting->adminSN);
					if( $s== false|| ($s==true && intval($this->setting->adminSN)==1)){
						$link_w[$i] .='&user=admin';
					}else{
						$sc = $this->genrate_sacure_code_admin_email($track);
						$link_w[$i] .='&user=admin&sc='.$sc;
					}
				}
			}else{
				$link_w[$i] = $homeUrl;
			}			
			
			$cont[$i] = $track;
			// find %s in $this->lanText['msgdml'] and replace with $track

			$dt =  $this->lanText['msgdml'];
			$dt = str_replace('%s', $track, $dt);
			$subject[$i] ="📮 " . $this->lanText["youRecivedNewMessage"] .' ['.$track.']';
			if($state[$i]=="notiToUserFormFilled_TrackingCode"){
				$subject[$i] =$this->lanText["WeRecivedUrM"];
				$message[$i] ="<h2>".$this->lanText["thankFillForm"]."</h2>
						<p>". $this->lanText["trackNo"].":<br> ".$track." </p>
						<p>". $dt." </p>
						<div style='text-align:center'><a href='".$link_w[$i]."' target='_blank' style='padding:5px;color:white;background:black;'>". $this->lanText["vmgs"]."</a></div>";
				$cont[$i]=$message[$i];
			}elseif($state[$i]=="notiToUserFormFilled"){
				$subject[$i] =$this->lanText["WeRecivedUrM"];	   
				$message[$i] ="<h2>".$this->lanText["thankFillForm"]."</h2>
				<div style='text-align:center'><a href='".$homeUrl."' target='_blank' style='padding:5px;color:white;background:black;'>".$blogName."</a></div>";
				$cont[$i]=$message[$i];
			}elseif($state[$i]=="respRecivedMessage"){
				$subject[$i] =$this->lanText["WeRecivedUrM"] .' ['.$track.']' ;
				$message[$i] ="<h2>".$this->lanText["WeRecivedUrM"]."</h2>
						<p>". $this->lanText["trackNo"].":<br> ".$track." </p>
						<p>". $dt." </p>
						<div style='text-align:center'><a href='".$link_w[$i]."' target='_blank' style='padding:5px;color:white;background:black;'>". $this->lanText["vmgs"]."</a></div>";
				$cont[$i]=$message[$i];
			}elseif ($state[$i]=="register"){  
				$subject[$i] =$this->lanText["thankRegistering"];   	
				$message[$i] ="<h2>".$this->lanText["welcome"]."</h2>
				".$cont[$i]."
				<div style='text-align:center'><a href='".$homeUrl."' target='_blank' style='padding:5px;color:white;background:black;'>".$blogName."</a></div>";
				$cont[$i]=$message[$i];
			}elseif ($state[$i]=="subscribe"){
				$subject[$i] =$this->lanText["welcome"];   
				$message[$i] ="<h2>".$this->lanText["thankSubscribing"]."</h2>
				<div style='text-align:center'><a href='".$homeUrl."' target='_blank' style='padding:5px;color:white;background:black;'>".$blogName."</a></div>";
				$cont[$i]=$message[$i];
			}elseif ($state[$i]=="survey"){
				$subject[$i] =$this->lanText["welcome"];   
				$message[$i] ="<h2>".$this->lanText["thankDonePoll"]."</h2>
				<div style='text-align:center'><a href='".$homeUrl."' target='_blank' style='padding:5px;color:white;background:black;'>".$blogName."</a></div>";
				$cont[$i]=$message[$i];
			}elseif($state[$i]=='newUser'){
				//get value from first tag <p>
				$start = strpos($cont[$i], '<p>') + 3; // Add 3 to exclude the <p> tag itself
				$end = strpos($cont[$i], '</p>') + 4;
				$slicedStr = substr($cont[$i], $start, $end - $start);
				$subject[$i] = esc_html__('New user registration' , 'easy-form-builder');
				$message[$i] ="<p>". esc_html__( 'New user registration', 'easy-form-builder' ) .'</p><p>'.$slicedStr ." </p>";
				$cont[$i]=$message[$i];
			}

			if($content!="null"){
				$cont[$i] = [$track, $content] ;
			}

			if($sub!="null"){
				$rp = [
					['[confirmation_code]','[link_page]','[link_domain]','[link_response]','[website_name]'],
					[$track, $link_w[$i], get_site_url(), $link_w[$i] , get_bloginfo('name')]
				];
			
				$subject[$i] = str_replace($rp[0],$rp[1],$sub);
				
			}
		}
		$check =  $this->efbFunction->send_email_state_new( $to,$subject ,$cont,$pro,$state,$link_w,$this->setting);
	}
	
	
	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function get_setting_Emsfb($state){
		// تنظیمات  برای عموم بر می گرداند
	 	$table_name = $this->db->prefix . "emsfb_setting";
	 	$value = $this->db->get_var( "SELECT setting,email FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
 		
		$rtrn;
		$siteKey;
		$trackingCode ="";
		$mapKey="";
		if($value!= null){
			
			$r =str_replace('\\', '', $value);
			$r =json_decode($r);
			if($state=="pub"){	
				$this->setting =$value;		
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				$pro = false;
				if(isset($r->activeCode) &&  md5($server_name) ==$r->activeCode){$pro=true;}			
				$this->pro_efb = $pro;
				$trackingCode = isset($r->trackingCode) ? $r->trackingCode : "";
				$siteKey = isset($r->siteKey) ? $r->siteKey : "";
				$mapKey = isset($r->apiKeyMap) ? $r->apiKeyMap : "";
				$paymentKey = isset($r->stripePKey) ? $r->stripePKey : "";
				$scaptcha = isset($r->scaptcha) ? $r->scaptcha : false;
				$dsupfile = isset($r->dsupfile) ? $r->dsupfile : false;
				$activeDlBtn = isset($r->activeDlBtn) ? $r->activeDlBtn : true;
				$efb_version = isset($r->efb_version) ? $r->efb_version : "1.0.0";
				$osLocationPicker = isset($r->osLocationPicker) ? $r->osLocationPicker : false;
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
				*/
				$addons = ['AdnSPF' => 0,
				'AdnOF' => 0,
				'AdnPPF' => 0,
				'AdnATC' => 0,
				'AdnSS' => 0,
				'AdnCPF' => 0,
				'AdnESZ' => 0,
				'AdnSE' => 0,
				'AdnPDP'=>0,
				'AdnADP'=>0
				];
				if(isset($r->AdnSPF)==true){
					//$ac
					$addons["AdnSPF"]=$r->AdnSPF;
					$addons["AdnOF"]=$r->AdnOF;
					$addons["AdnATC"]=$r->AdnATC;
					$addons["AdnPPF"]=$r->AdnPPF;
					$addons["AdnSS"]=$r->AdnSS;
					$addons["AdnCPF"]=$r->AdnCPF;
					$addons["AdnESZ"]=$r->AdnESZ;
					$addons["AdnSE"]=$r->AdnSE;
					$addons["AdnPDP"]=isset($ac->AdnPDP) ? $ac->AdnPDP : 0;
					$addons["AdnADP"]=isset($ac->AdnADP) ? $ac->AdnPDP : 0;
				}
				

				$this->pub_stting=array("pro"=>$pro,"trackingCode"=>$trackingCode,"siteKey"=>$siteKey,"mapKey"=>$mapKey,"paymentKey"=>$paymentKey, "version"=>$efb_version,"osLocationPicker"=>$osLocationPicker,
				"scaptcha"=>$scaptcha,"dsupfile"=>$dsupfile,"activeDlBtn"=>$activeDlBtn,"addons"=>$addons);
				$rtrn =json_encode($this->pub_stting,JSON_UNESCAPED_UNICODE);
				return [$rtrn ,$this->pub_stting];
			}else{
				$rtrn=$value;
				$this->setting =$rtrn;
			}
		}else{
			$rtrn=0;
		}
		
	 //return $value[0];
	 return $rtrn;
	}
	public function pay_stripe_sub_Emsfb_api($data_POST_) {		
		$data_POST = $data_POST_->get_json_params();
		$user = wp_get_current_user();
		$uid= $user->exists() ? $user->user_nicename :  esc_html__('Guest','easy-form-builder') ;
		$this->id =sanitize_text_field($data_POST['id']);
		$sid = sanitize_text_field($data_POST['sid']);	
		$s_sid = $this->efbFunction->efb_code_validate_select($sid , $this->id);
		if ($s_sid !=1){
			$m = esc_html__('error', 'easy-form-builder') . ' 403';
			$response = array( 'success' => false  , 'm'=>$m); 
			wp_send_json_success($response,$data_POST);
		} 
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');
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
			 return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".esc_html__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
		}
		require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/autoload.php");
		$this->id = sanitize_text_field($data_POST['id']);
		$val_ = sanitize_text_field($data_POST['value']);
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs =str_replace('\\', '', $value_form[0]->form_structer);
		$fs_ = json_decode($fs,true);
		$val =str_replace('\\', '', $val_);
		$val_ = json_decode($val,true);
		$paymentmethod = $fs_[0]['paymentmethod'];
		$price_c =0;
		$price_f=0;
		$email ='';
		$valobj=[];
		for ($i=0; $i <count($val_) ; $i++) { 
			$a=-1;
			if(isset($val_[$i]['price'])){			
				if($val_[$i]['price'] ) $price_c += abs($val_[$i]['price']);
				if($val_[$i]['type']=="email" ) $email = $val_[$i]["value"];
				$iv = $val_[$i];
				if($iv["type"]=="paySelect" || $iv["type"]=="payRadio" || $iv["type"]=="payCheckbox"){
					$filtered = array_filter($fs_, function($item) use ($iv) { 
						switch ($iv["type"]) {
							case 'paySelect':
								if(isset($item['parent']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false ; 								
							break;
							case 'payRadio':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false; 								
							break;
							case 'payCheckbox':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['parent']==$iv['id_'] ? $item['value'] :false; 								
							break;
							
						}
					});
					if($filtered==false){
						$m = esc_html__('error', 'easy-form-builder') . ' 405';
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, 200);
					}
					 $iv = array_keys($filtered);
					 $a = isset( $iv[0])? $iv[0] :-1;
				}else if ($iv["type"]=="payMultiselect" && isset($iv['price'])  && isset($iv['ids']) ){
					$rows = explode( ',', $iv["ids"] );					
					foreach ($rows as $key => $value) {
						$filtered = array_filter($fs_, function($item) use ($value) { 							
							if(isset($item['id_']))return $item['id_'] == $value ;
						});
						$iv = array_keys($filtered);
						$price_f += $fs_[$a]["price"];										
					}
					$a=-1;
				}else if($iv["type"]=="prcfld" ){					
					   $a=-1;
					   $price_f += $iv["price"];											
				}
				if($a !=-1){											
					if($fs_[$a]["type"]!="payMultiselect"){						
						$price_f+=$fs_[$a]["price"];					
					}				
						$fs_[$a]["name"] = $val_[$i]["name"];
						$fs_[$a]["type"] = "option_payment";
						array_push($valobj,$fs_[$a]);
				}
			}
		}
		$ip =$this->get_ip_address();
		$this->ip = $ip;
		if($price_c != $price_f) {
			$t=time();
			$from =get_bloginfo('name')." <Alert@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			$to =get_option('admin_email');
			$message="This message from Easy Form Builder, This IP:".$this->ip. 
			" try to enter invalid value like fee of the service of the form id:" .$this->id. " at :".date("Y-m-d-h:i:s",$t) ;
			wp_mail( $to,"Warning Entry[Easy Form Builder]", $message, $headers );
		}
		$price_f = $price_f*100;
		$description =  get_bloginfo('name') . ' >' . $fs_[0]['formName'];
		if($price_f>0){
			$currency= $fs_[0]['currency'] ;
			//private key
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
				//Create a products 
				//$stripe = new \Stripe\StripeClient($Sk);
				$product = $stripe->products->create([
					'name' => $description,
					]);
					//Create price for products				
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
			//array_push($filtered,$ar);
			$this->ip=$this->get_ip_address();
			$ip = $this->ip;
			$val_ = json_encode($filtered ,JSON_UNESCAPED_UNICODE);	
			$this->value = str_replace('"', '\\"', $val_);
			$this->name = sanitize_text_field($data_POST['name']);
			$check=	$this->insert_message_db(2,false);
			//$response->transStat
			//array_push($response->transStat ,array('id'=>$check));
			$response=array_merge($response , ['id'=>$check]);
			
			wp_send_json_success($response, 200);
		}else{
			$response = array( 'success' => false  , 'm'=>esc_html__('Error Code:V02','easy-form-builder'));		
			wp_send_json_success($response, 200);
		}
	}
	public function pay_persia_sub_Emsfb_api($data_POST_){
		$data_POST = $data_POST_->get_json_params();
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');
		
		$efbFunction =  $this->get_efbFunction(1);
		
		$sid = sanitize_text_field($data_POST['sid']);
		$this->id = sanitize_text_field($data_POST['id']);
		$s_sid = $this->efbFunction->efb_code_validate_select($sid , $this->id);
		$text_=['somethingWentWrongPleaseRefresh'];
		$this->lanText= $this->efbFunction->text_efb($text_);
		if ($s_sid !=1){
			
			$m =  $this->lanText["somethingWentWrongPleaseRefresh"]. '<br>'. esc_html__('Error Code','easy-form-builder') .': 403';
		$response = array( 'success' => false  , 'm'=>$m); 
		wp_send_json_success($response,$data_POST);
		} 
		$Sk ='null';
		if(gettype($r)=="string"){
			$setting =str_replace('\\', '', $r);
			$setting =json_decode($setting);
			$Sk = isset($setting->payToken) && strlen($setting->payToken)>5  ? $setting->payToken :'null';
		}
		if ($Sk=="null"){
				$m = esc_html__('persiaPayment', 'easy-form-builder').'->'.	esc_html__('error', 'easy-form-builder') . ' 402';
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, 200);
				die("secure!");
		}
		$this->id = sanitize_text_field($data_POST['id']);
		$val_ = sanitize_text_field($data_POST['value']);
		$url = sanitize_url($data_POST['url']);
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs =str_replace('\\', '', $value_form[0]->form_structer);
		$fs_ = json_decode($fs,true);
		$val =str_replace('\\', '', $val_);
		$val_ = json_decode($val,true);
		$paymentmethod = $fs_[0]['paymentmethod'];
		$price_c =0;
		$price_f=0;
		$email ='no@email.com';
		$des = ':پرداختی فرم' . $fs_[0]['formName'];
		$valobj=[];
		for ($i=0; $i <count($val_) ; $i++) { 
			$a=-1;
			if(isset($val_[$i]['price'])){				
				if($val_[$i]['price'] ) $price_c += abs($val_[$i]['price']);
				if($val_[$i]['type']=="email" ) $email = $val_[$i]["value"];
				$iv = $val_[$i];
				if($iv["type"]=="paySelect" || $iv["type"]=="payRadio" || $iv["type"]=="payCheckbox"){
					$filtered = array_filter($fs_, function($item) use ($iv) { 
						switch ($iv["type"]) {
							case 'paySelect':
								if(isset($item['parent']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false ; 								
							break;
							case 'payRadio':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false; 								
							break;
							case 'payCheckbox':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['parent']==$iv['id_'] ? $item['value'] :false; 								
							break;							
						}
					});
					if($filtered==false){
						$m = esc_html__('error', 'easy-form-builder') . ' 405';
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, 200);
					}
					 $iv = array_keys($filtered);
					 $a = isset( $iv[0])? $iv[0] :-1;
				}else if ($iv["type"]=="payMultiselect" && isset($iv['price'])  && isset($iv['ids']) ){
					$rows = explode( ',', $iv["ids"] );					
					foreach ($rows as $key => $value) {
						$filtered = array_filter($fs_, function($item) use ($value) { 							
							if(isset($item['id_']))return $item['id_'] == $value ;
						});
						$iv = array_keys($filtered);
						$a = isset( $iv[0])? $iv[0] :-1;
						$price_f += $fs_[$a]["price"];										
					}
					$a=-1;
				}else if ($iv["type"]=="prcfld" ){					
					 
					   $price_c += abs($val_[$i]['price']);
					   $price_f += abs($val_[$i]['price']);		

				}
				if($a !=-1){											
					if($fs_[$a]["type"]!="payMultiselect"){						
						$price_f+=$fs_[$a]["price"];					
					}
					//$k =array_search($fs_[$a]['parent'], array_column($fs_, 'id_'));
					$fs_[$a]["name"] = $val_[$i]["name"];
					$fs_[$a]["type"] = "option_payment";
					//$fs_[$a]
					array_push($valobj,$fs_[$a]);
				}
			}
		}
		$this->ip= $this->get_ip_address();
		$ip = $this->ip;		
		if($price_c != $price_f) {
			$t=time();
			$from =get_bloginfo('name')." <Alert@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			$to =get_option('admin_email');
			$message="This message from Easy Form Builder, This IP:".$this->ip. 
			" try to enter invalid value like fee of the service of a form at :".date("Y-m-d-h:i:s",$t) ;
			wp_mail( $to,"Warning Entry[Easy Form Builder]", $message, $headers );
		}
		$price_f = $price_f;
		$description =  get_bloginfo('name') . ' >' . $fs_[0]['formName'];		
		if($price_f>0){
			$currency= $fs_[0]['currency'] ;
			$filtered = array_filter($valobj, function($item) { 
				if(isset($item['price']))	return $item; 								
			});
			
			$clientRefId =substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 12);
			$TokenCode =$Sk;
			$returnUrl =$url;
			//$amount=1000;
			$data = array("merchant_id" => $TokenCode,
			"amount" => $price_f,
			"callback_url" => $returnUrl,
			"description" =>  $des,
			"metadata" => [ "email" =>  $email],
			);
			$jsonData = json_encode($data);
			if($price_f<4999){
				$response = array( 'success' => false  , 'm'=>'مجموع مبلغ پرداختی نباید کمتر از پانصد تومان باشد');		
				wp_send_json_success($response, 200);
				die();
			}
			
			require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/zarinpal.php");
			$persiapay = new zarinPalEFB() ;
			$check;
			if(gettype($persiapay)=="object"){
				$response = $persiapay->create_bill_zarinPal($jsonData,$clientRefId);
				if($response['success']==true){
					$val_ = json_encode($filtered ,JSON_UNESCAPED_UNICODE);	
						//$this->get_ip_address();
						$this->value = str_replace('"', '\\"', $val_);
						$this->name = sanitize_text_field($data_POST['name']);
						$check=	$this->insert_message_db(2,$clientRefId);
						if(isset($check)!=true){
							$response = array('success' => false, 'm' => 'خطا در ارتباط با دیتابیس ، شماره خطا DB-403');
						}
				}else{
					$response = array( 'success' => false  , 'm'=>'اختلال در ارتباط با زرین پال. این اختلال ممکن است از طرف سرور زرین پال باشد');		
				}
			}			
			//array_push($filtered,$ar);
			//$response->transStat
			//array_push($response->transStat ,array('id'=>$check));
			$response=array_merge($response , ['id'=>$check]);
		}else{
			$response = array( 'success' => false  , 'm'=>esc_html__('Error Code:V01','easy-form-builder'));		
		}
		wp_send_json_success($response, 200);
	}
	public function persia_pay_Emsfb() {		
        if (check_ajax_referer('public-nonce', 'nonce') != 1) {
            $m = esc_html__('error', 'easy-form-builder') . ' 403';
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');
		$Sk ='null';
		if(gettype($r)=="string"){
			$setting =str_replace('\\', '', $r);
			$setting =json_decode($setting);
			$Sk = isset($setting->payToken) && strlen($setting->payToken)>5  ? $setting->payToken :'null';
		}
		if ($Sk=="null"){
				$m = esc_html__('persiaPayment', 'easy-form-builder').'->'.	esc_html__('error', 'easy-form-builder') . ' 402';
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, 200);
				die("secure!");
		}
		$this->id = sanitize_text_field($_POST['id']);
		$val_ = sanitize_text_field($_POST['value']);
		$url = sanitize_url($_POST['url']);
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs =str_replace('\\', '', $value_form[0]->form_structer);
		$fs_ = json_decode($fs,true);
		$val =str_replace('\\', '', $val_);
		$val_ = json_decode($val,true);
		$paymentmethod = $fs_[0]['paymentmethod'];
		$price_c =0;
		$price_f=0;
		$email ='no@email.com';
		$des = ':پرداختی فرم' . $fs_[0]['formName'];
		$valobj=[];
		for ($i=0; $i <count($val_) ; $i++) { 
			$a=-1;
			if(isset($val_[$i]['price'])){				
				if($val_[$i]['price'] ) $price_c += abs($val_[$i]['price']);
				if($val_[$i]['type']=="email" ) $email = $val_[$i]["value"];
				$iv = $val_[$i];
				if($iv["type"]=="paySelect" || $iv["type"]=="payRadio" || $iv["type"]=="payCheckbox"){
					$filtered = array_filter($fs_, function($item) use ($iv) { 
						switch ($iv["type"]) {
							case 'paySelect':
								if(isset($item['parent']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false ; 								
							break;
							case 'payRadio':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value'] ? $item['value'] :false; 								
							break;
							case 'payCheckbox':
								if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['parent']==$iv['id_'] ? $item['value'] :false; 								
							break;
						}
					});
					if($filtered==false){
						$m = esc_html__('error', 'easy-form-builder') . ' 405';
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, 200);
					}
					 $iv = array_keys($filtered);
					 $a = isset( $iv[0])? $iv[0] :-1;
				}else if ($iv["type"]=="payMultiselect" && isset($iv['price'])  && isset($iv['ids']) ){
					$rows = explode( ',', $iv["ids"] );					
					foreach ($rows as $key => $value) {
						$filtered = array_filter($fs_, function($item) use ($value) { 							
							if(isset($item['id_']))return $item['id_'] == $value ;
						});
						$iv = array_keys($filtered);
						$a = isset( $iv[0])? $iv[0] :-1;
						$price_f += $fs_[$a]["price"];										
					}
					$a=-1;
				}
				if($a !=-1){											
					if($fs_[$a]["type"]!="payMultiselect"){						
						$price_f+=$fs_[$a]["price"];					
					}
					//$k =array_search($fs_[$a]['parent'], array_column($fs_, 'id_'));
					$fs_[$a]["name"] = $val_[$i]["name"];
					$fs_[$a]["type"] = "option_payment";
					//$fs_[$a]
					array_push($valobj,$fs_[$a]);
				}
			}
		}
		$this->ip= $this->get_ip_address();
		$ip = $this->ip;
		if($price_c != $price_f) {
			$t=time();
			$from =get_bloginfo('name')." <Alert@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			$to =get_option('admin_email');
			$message="This message from Easy Form Builder, This IP:".$this->ip. 
			" try to enter invalid value like fee of the service of a form at :".date("Y-m-d-h:i:s",$t) ;
			wp_mail( $to,"Warning Entry[Easy Form Builder]", $message, $headers );
		}
		$price_f = $price_f;
		$description =  get_bloginfo('name') . ' >' . $fs_[0]['formName'];		
		if($price_f>0){
			$currency= $fs_[0]['currency'] ;
			$filtered = array_filter($valobj, function($item) { 
				if(isset($item['price']))	return $item; 								
			});
			
			$clientRefId =substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 12);
			$TokenCode =$Sk;
			$returnUrl =$url;
			//$amount=1000;
			$data = array("merchant_id" => $TokenCode,
			"amount" => $price_f,
			"callback_url" => $returnUrl,
			"description" =>  $des,
			"metadata" => [ "email" =>  $email],
			);
			$jsonData = json_encode($data);
			if($price_f<4999){
				$response = array( 'success' => false  , 'm'=>'مجموع مبلغ پرداختی نباید کمتر از پانصد تومان باشد');		
				wp_send_json_success($response, 200);
				die();
			}
			
			require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/zarinpal.php");
			$persiapay = new zarinPalEFB() ;
			$check;
			if(gettype($persiapay)=="object"){
				$response = $persiapay->create_bill_zarinPal($jsonData,$clientRefId);
				if($response['success']==true){
					$val_ = json_encode($filtered ,JSON_UNESCAPED_UNICODE);	
						//$this->get_ip_address();
						$this->value = str_replace('"', '\\"', $val_);
						$this->name = sanitize_text_field($_POST['name']);
						$check=	$this->insert_message_db(2,$clientRefId);
						if(isset($check)!=true){
							$response = array('success' => false, 'm' => 'خطا در ارتباط با دیتابیس ، شماره خطا DB-403');
						}
				}else{
					$response = array( 'success' => false  , 'm'=>'اختلال در ارتباط با زرین پال. این اختلال ممکن است از طرف سرور زرین پال باشد');		
				}
			}			
			//array_push($filtered,$ar);
			//$response->transStat
			//array_push($response->transStat ,array('id'=>$check));
			$response=array_merge($response , ['id'=>$check]);
		}else{			
			$response = array( 'success' => false  , 'm'=>esc_html__('Error Code:V01','easy-form-builder'));		
		}
		wp_send_json_success($response, 200);
    }
	public function fun_convert_form_structer($form_structure){
		$form_ = str_replace('\\', '', $form_structure);;
		$form_ = json_decode($form_, true);
		$str = '<!--efb.app-->';
		$this->name ='<!--efb.app head-->';
		//	$str = sprintf('<div class="efb-form-container">%s</div>',$form_[0]['type']);
		$first = $form_[1];
		array_filter($form_, function($item) use($first) { 
			if(isset($item['id_'])!=true ){
				return false;
			}
			$dataTag = '';
			$desc ='<!--efb.app-->';
			$label ='<!--efb.app-->';
			$ui ='<!--efb.app-->';
			//add pos function 
			$pos =['','','',''];
			if($item['type']!='option' && $item['type']!='step' && $item['type']!='link'  && $item['type']!='html'){
				switch ($item['size']) {
					case 100:
					case '100':
					  $pos[1] = 'col-md-12';$pos[2] = 'col-md-3';$pos[3] = 'col-md-9';
					  break;
					case 80:
					case '80':
					 $pos[1] = 'col-md-10';$pos[2] = 'col-md-2';$pos[3] = 'col-md-10';
					  break;
					case 50:
					case '50':
					  $pos[1] = 'col-md-6';$pos[2] = 'col-md-3';$pos[3] = 'col-md-9';
					  break;
					case 33:
					case '33':
					  $pos[1] = 'col-md-4';$pos[2] = 'col-md-4';$pos[3] = 'col-md-8';
					  break;
				  }
				  if ($item['label_position'] == "up") {$pos[2] = 'col-md-12';$pos[3] = 'col-md-12';} else {$pos[0] = 'row px-0';}
				$desc = sprintf('<small id="%s-des" class="efb  form-text d-flex  fs-7 col-sm-12 efb mx-4 %s  %s %s">%s </small>',$item['id_'] ,$item['message_align'],$item['message_text_color'],$item['message_text_size'],$item['message']);
				$required = $item['required']==true ? '*' : '';
				$label = sprintf('<small id="%s_-message" class="efb text-danger efb fs-7 ttiptext px-2  "></small> <label for="%s_" class="efb  %s col-sm-12 col-form-label %s %s %s" id="%s_labG"><span id="%s_lab" class="efb  %s">%s</span> <span class="efb  mx-1 text-danger" id="%s_req">%s</span></label>',
				 $item['id_'],$item['id_'],$pos[2],$item['label_text_color'],$item['label_align'],
				 $item['label_text_size'],$item['id_'],$item['id_'],$item['label_text_size'],$item['name'],$item['id_'],$required);
			}else if($item['type']=='option'){
				return false;
			}
			$required = isset($item['required'])==true && $item['required']==true ? 'required' : '';
			switch ($item['type']) {
				case 'email':
				case 'text':
				case 'password':
				case 'tel':
				case 'url':
				case "date":
				case 'color':
				case 'range':
				case 'number':
				case 'firstName':
				case 'lastName':
					$type = $item['type'] == "firstName" || $item['type'] == "lastName" ? 'text' : $item['type'];
					$value = strlen($item['value'])>0 ? "value='".$item['value']."'" : '';
      				$classes = $item['type'] != 'range' ? 'form-control '.$item['el_border_color'].'' : 'form-range';
					$input = '<!--tags--><div class="efb %s col-sm-12 ttEfb show"  id="%s-f"><input type="%s" class="efb input-efb px-2  %s emsFormBuilder_v   %s  %s  %s  %s  %s efbField" data-id="%s-el" data-vid="%s" id="%s" placeholder="%s" %s>';					 
					 $input = sprintf($input, $pos[3] ,$item['id_'] ,$item['type'], $classes, $item['classes'], $item['el_height'],$item['corner'], $item['el_text_color'],$required,$item['id_'],$item['id_'],$item['id_'],$item['placeholder'],$item['value']);
					 $ui = " {$label} {$input} {$desc}</div></div>";
					$dataTag = $item['type'];
					break;
				case 'textarea':				 
						$input = sprintf('<div class="efb %s col-sm-12 ttEfb show"  id="%s-f"><textarea  id="%s_"  placeholder="%s" class="efb px-2 input-efb emsFormBuilder_v form-control %s %s %s %s %s %s efbField" data-vid="%s" data-id="%s-el"  value="%s" rows="5" ></textarea>',$pos[3], $item['id_'], $item['id_'], $item['placeholder'], $required, $item['classes'],
						$item['el_height'], $item['corner'], $item['el_text_color'], $item['el_border_color'],
						$item['id_'], $item['id_'], $item['value']);
						$ui = " {$label} {$input} {$desc}</div></div>";
						$dataTag = $item['type'];
					break;
				case 'maps';
						$dataTag = $item['type'];
					break;
				case 'file':					 
						$input = sprintf('<div class="efb  %s col-sm-12 ttEfb show"  id="%s-f"> <input type="file" class="efb mb-0 input-efb px-2 emsFormBuilder_v %s %s %s %s %s form-control efb efbField" data-vid="%s" data-id="%s-el" id="%s_" >'
								, $pos[3], $item['id_'], $required, $item['classes'], $item['el_height'], $item['corner'], $item['el_border_color'], $item['id_'], $item['id_'], $item['id_']);
						$ui = " {$label} {$input} {$desc}</div></div>";
						$dataTag = $item['type'];
					break;
				case "mobile":
						$input = sprintf('<div class="efb  %s col-sm-12 ttEfb show"  id="%s-f"> <input type="phone" class="efb  input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control %s  %s %s %s %s %s efbField" data-id="%s-el" data-vid="%s" id="%s_" placeholder="%s" value = "%s" >',$pos[3], $item['id_'], $item['el_border_color'], $item['classes'], $item['el_height'], $item['corner'], 
						$item['el_text_color'], $required, $item['id_'], $item['id_'], $item['id_'], $item['placeholder'], $item['value']);
						$ui = " {$label} {$input} {$desc}</div></div>";  						 
						$dataTag = $item['type'];
					break;
				case 'dadfile':
						$dataTag = $item['type'];
					break;
				case 'checkbox':
				case 'radio':
				case 'payCheckbox':
				case 'payRadio':
						$dataTag = $item['type'];
					break;
				case 'switch':
						$dataTag = $item['type'];
					break;
				case 'esign':
						$dataTag = $item['type'];
					break;
				case 'rating':
						$dataTag = $item['type'];
					break;
				case "step":
						$step_no = $item['id_'];
						$ui = $step_no == 1 ? '<fieldset data-step="step-'.$step_no.'-efb" class="efb my-2  steps-efb efb row ">': '<!-- fieldset!!! --> </fieldset><fieldset data-step="step-'.$step_no.'-efb"  class="efb my-2 steps-efb efb row d-none">';

						$dataTag = $item['type'];
					break;
				case 'select':
				case 'paySelect':
						$dataTag = $item['type'];
					break;
				case 'conturyList':
						$dataTag = $item['type'];
					break;
				case 'stateProvince':
						$dataTag = $item['type'];
					break;
				case 'multiselect':
				case 'payMultiselect':
						$dataTag = $item['type'];
					break;
				case 'html':
						$dataTag = $item['type'];
					break;
				case 'yesNo':
						$dataTag = $item['type'];
					break;
				case 'link':
						$dataTag = $item['type'];
					break;
				case 'stripe':
						$dataTag = $item['type'];
					break;
				case "persiaPay":
						$dataTag = $item['type'];
					break;
				case 'heading':
						$dataTag = $item['type'];
					break;
				case 'booking':
						$dataTag = $item['type'];
					break;
				default:
					break;
			}
			$newElement='<div  class="efb my-1 mx-0 ttEfb  %s  %s col-sm-12 efbField" data-step="%s" data-amount="%s" id="%s" data-id="%s-id" data-tag="%s"> %s<!--endTag EFB-->';
			$this->value .= sprintf($newElement, $pos[0], $pos[1], $item['step'] , $item['amount'],$item['id_'],$item['id_'],$dataTag,$ui);
		});	
		$str= $form_[0]['show_icon']==0 || $form_[0]['show_icon']==false  ?sprintf('<h4 id="title_efb" class="efb %s text-center mt-1">%s</h4><p id="desc_efb" class="efb %s text-center  fs-6 efb">%s</p>',$form_[1]['label_text_color'], $form_[1]['name'], $form_[1]['message_text_color'] , $form_[1]['message'] ) : '';
		//s1 head
		//s2 content new.js  1342
		$str = '<div class="efb px-0 pt-2 pb-0 my-1 col-12" id="view-efb">'.$str.'<form id="efbform">%s<div class="efb mt-1 px-2">%s</div> </form></div>';
		//buttons
		$step_no = intval($form_[0]["steps"]) +1;		
		 $this->value .= isset($this->pub_stting->siteKey) && $form_[0]['captcha'] == true ? '<div class="efb row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-2" data-sitekey="'.$this->pub_stting->siteKey .'" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>' : '';
		 $this->value .= '</fieldset>
		 <fieldset data-step="step-'.$step_no.'-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
		  <div class="efb card-body text-center efb"><h3 class="efb">'.esc_html__('Waiting','easy-form-builder').'</h3></div>                
		   <!-- final fieldset --></fieldset>';
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
            'value' => $slug["name"],
            'content' => "content",
            'nonce_msg' => "code",
            'id' => $slug["id"]
          );
        return new WP_REST_Response($response, 200);
       // return $fs;
    } 
	

	function replaceContentMessageEfb($value) {
		$value = preg_replace('/[\\\\]/', '', $value);
		$value = preg_replace('/(\\"|"\\\\)/', '"', $value);
		$value = preg_replace('/(\\\\\\\\n|\\\\\\\\r)/', '<br>', $value);
		$value = str_replace('@efb@sq#', "'", $value);
		// $value = str_replace('@efb@bsq#', "\\", $value);
		$value = str_replace('@efb@vq#', "`", $value);
		$value = str_replace('@efb@dq#', "''", $value);
		$value = str_replace('@efb@nq#', "<br>", $value);
		return $value;
	}


	function email_get_content($content ,$track){
		$m ='<!-- efb-v3 -->';
		$text_ =['msgemlmp','paymentCreated','videoDownloadLink','downloadViedo','payment','id','payAmount','ddate','updated','methodPayment','interval'];
		$list=[];
	
		  $s = false;
		  $checboxs = [];
		  $total_amount =0;

		  $lst = end($content);
		  $link_w = $lst['type']=="w_link" ? $lst['value'] : 'null';
		  if(strlen($link_w)>5){
			
			$link_w =strpos($link_w,'?')!=false ? $link.'&track='.$track : $link_w.'?track='.$track;
		}else{
			$link_w = home_url();
		}
	
		  
		  $currency = array_key_exists('paymentcurrency', $content[0]) ? $content[0]['paymentcurrency'] : 'usd';
		  $this->get_efbFunction(0);
		
		usort($content, function($a, $b) {
			return $a['amount'] <=> $b['amount'];
		});
		$lanText= $this->efbFunction->text_efb($text_);
		foreach ($content as $c){
			
			if(isset($c['type']) && $c['type'] == "w_link"){
			 continue;
			}

			
			// Check if the current item has a value property and is not of type maps
			if (isset($c['value']) && $c['type'] != "maps") {
			  $c['value'] = $this->replaceContentMessageEfb($c['value']);
			}
			// Check if the current item has a qty property
			if (isset($c['qty']) != false) {
			  //$c.qty = $this->replaceContentMessageEfb($c.qty);
			  $c['qty'] = $this->replaceContentMessageEfb($c['qty']);
			}
			
			
			$s = false;
			$value = '';
			// Check if the current item's value is a string
			if (is_string($c['value'])) {
			  $value = '<b>' . str_replace('@efb!', ',', $c['value']) . '</b>';
			  $value = str_replace('@n#', '<br>', $c['value']);
			}
		
			  if(isset($c['qty']) != false){
			  $value .= ': <b>' . $c['qty'] . '</b>';
			}
			// Check if the current item's value is "@file@"

			  if(isset($c['value']) && $c['value'] == "@file@" && !in_array($c['url'], $list)){
			  $s = true;
			  array_push($list, $c['url']);
		  
			  $name = substr($c['url'], strrpos($c['url'], '/') + 1, strrpos($c['url'], '.') - strrpos($c['url'], '/') - 1);
			  // Check the current item's type
		  
			   if(isset($c['type']) && ($c['type'] == "Image" || $c['type'] == "image")){			
				  $value = '<br><img src="' . $c['url'] . '" alt="' . $c['name'] . '" class="efb img-thumbnail m-1">';
				}else if(isset($c['type']) && ($c['type'] == "Document" || $c['type'] == "document" || $c['type'] == "allformat")){
				  $value = '<br><a class="efb btn btn-primary m-1" href="' . $c['url'] . '" target="_blank">' . $c['name'] . '</a>';
				}else if(isset($c['type']) && ($c['type'] == "Media" || $c['type'] == "media")){
				$audios = ['mp3', 'wav', 'ogg'];
				$media = "video";
				foreach ($audios as $aud) {
				  if (strpos($c['url'], $aud) !== false) {
					$media = 'audio';
				  }
				}
				if ($media == "video") {
				  $poster_emsFormBuilder =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';							
				  $value = $type !== 'avi' ? '<br><div class="efb px-1"><video poster="' . $poster_emsFormBuilder . '" src="' . $c['url'] . '" type="video/' . $type . '" controls></video></div><p class="efb text-center"><a href="' . $c['url'] . '">' . $lanText['videoDownloadLink'] . '</a></p>' : '<p class="efb text-center"><a href="' . $c['url'] . '">' . $lanText['downloadViedo'] . '</a></p>';
				
				} else {
				  $value = '<div><audio controls><source src="' . $c['url'] . '"></audio></div>';
				}
				  
				} else {
					$value = strlen($c['url']) > 1 ? '<br><a class="efb btn btn-primary m-1" href="' . $c['url'] . '" target="_blank">' . $c['name'] . '</a>' : '<span class="efb fs-5">💤</span>';
					//$value = strlen($c.url) > 1 ? '<br><a class="efb btn btn-primary" href="' . $c.url . '" target="_blank">' . $c.name . '</a>' : '<span class="efb fs-5">💤</span>';
				}

			} else if ($c['type'] == "esign") {
			  $titile =isset($c['name'])? $c['name'] : '';
			  //$title = $lanText[$title] || $c.name;
			  $title =  $c['name'];
			  $s = true;
			  $value = '<img src="' . $c['value'] . '" alt="' . $c['name'] . '" class="efb img-thumbnail">';
			  
			  $m.= '<p >' . $title . ':</p><p style="margin: 0px 10px;">' . $value . '</span>';
			} else if ($c['type'] == "color") {
			  $title =isset($c['name'])? $c['name'] : '';
		  
			  $title =  $c['name'];
			  $s = true;
			  $value = '<div class="efb img-thumbnail" style="background-color:' . $c['value'] . '; height: 50px;">' . $c['value'] . '</div>';
			  //$value = '<div class="efb img-thumbnail" style="background-color:' . $c.value . '; height: 50px;">' . $c.value . '</div>';
			  $m .= '<p >' . $title . ':</p><p style="margin: 0px 10px;">' . $value . '</p>';
			} else if ($c['type'] == "maps") {
			  
			  //if (is_array($c.value)) {
			  if (is_array($c['value'])) {
				$s = true;
			  //  $value = '<div id="' . $c.id_ . '-map" data-type="maps" class="efb maps-efb h-d-efb required" data-id="' . $c.id_ . '-el" data-name="maps"><h1>maps</h1></div>';
				$value = '<a style="margin: 0px 10px;" href='.$link_w.'>'.$lanText['msgemlmp'].'</a>';	
				$m .= $value;
			  }
				   
		  
			  } else if ($c['type'] == "rating") {
			  $s = true;
			  
			  $title =isset($c['name'])? $c['name'] : '';
			  $value = '<div class="efb fs-4 star-checked star-efb mx-1>';
			  for ($i = 0; $i < intval($c['value']); $i++) {
				$value .= '⭐';
			  }
			  $value .= '</div>';
			  $m .= '<p >' . $title . ':</p><p  style="margin: 0px 10px;">' . $value . '</p>';
			} else if ($c['type'] == "payCheckbox" || $c['type'] == "payRadio") { 
			  $s = true;
			  $vc = 'null';
			  $total_amount = $total_amount+ intval( $c['price']);
			  array_push($checboxs, $c['id_']);
				$vc == 'null' ? $vc = '<p ><b>' . $c['price'] . '</b></p>' : $vc .= '<p  style="margin: 0px 10px;"><b>' . $c['price'] . '</b></p>';			  
			  $m .= '<p >' . $c['name'] . ':</p>'. $vc.' ' . strtoupper($currency);
			
		  
			  } else if ($c['type'] == "r_matrix" && !in_array($c['id_'], $checboxs)) {
			  $s = true;
			  $vc = 'null';
			  array_push($checboxs, $c['id_']);
			  foreach ($content as $op) {
				if ($op['type'] == "r_matrix" && $op['id_'] == $c['id_']) {
				  $vc == 'null' ? $vc = '<p ><b>' . $op['value'] . '</b></p>' : $vc .= '<p  style="margin: 0px 10px;"><b>' . $op['value'] . '</b></p>';
				}
			  }
			  $m .= $vc;
			}

			if (isset($c['id_']) && $c['id_'] == 'passwordRegisterEFB') {
			  $m .= $value;
			  $value = '**********';
			}
				
			if (((($s == true && $c['value'] == "@file@") || ($s == false && $c['value'] != "@file@")) && (isset($c['id_']) &&  $c['id_'] != "payment") && $c['type'] != "checkbox")) {
				
			  $title =isset($c['name'])? $c['name'] : '';
			  if ($title == "file") {
				$title = "atcfle";
			  }
			
			 
			  $q = $value !== '<b>@file@</b>' ? $value : '';
			  if($q=='<b>@file@</b>') continue;
			  if (strpos($c['type'], 'pay')) {
				$vc = 'null';
				$total_amount = $total_amount+ intval( $c['price']);
				$q = '<b >'. number_format($c['price'], 0, '.', ',') . ' ' . ($currency) . '</b>';
				$title =$c['value'] ;
			  } else if (strpos($c['type'], 'checkbox') !== false) {
				//checboxs.push
			  
			  } else if (strpos($c['type'], 'imgRadio') !== false) {
				$q= $c['value'];
			  }
			  $m .= '<p >' . $title . ':</p><p  style="margin: 0px 10px;">' . $q . '</p>';
			}

			if ($c['type'] == "payment") {
				
			  if ($c['paymentGateway'] == "stripe") {				
				  $m .= '<div style="margin: 10px 0px;">
				  <p style="margin:5px;">' . $lanText['payment'] . ' ' . $lanText['id'] . ':<span > ' . $c['paymentIntent'] . '</span></p>
				  <p style="margin: 5px;">' . $lanText['methodPayment'] . ':<span > ' . $c['paymentmethod'] . '</p>' . ($c['paymentmethod'] != 'charge' ? '<p style="margin: 5px;">' . $lanText['interval'] . ':<span class="efb mb-1 text-capitalize"> ' . $c['interval'] . '</span></p>' : '') . '</div>
				  <p style="margin: 5px;">' . $lanText['payAmount'] . ':<span > ' . number_format($c['paymentAmount'], 0, '.', ',') .' '. strtoupper($c['paymentcurrency']). '</span></p>
				  <p style="margin: 5px;">' . $lanText['ddate']  . ':<span > ' . $c['paymentCreated'] . '</span></p>
				  </div>
				 
				  ';
			  } else {
				
				$m .= '<div style="margin: 10px 0px;">
				  <p style="margin: 5px;">' . $lanText['payment'] . ' ' .  $lanText['id'] . ':<span > ' . $c['paymentIntent'] . '</p>
				  <p style="margin: 5px;">' . $lanText['payAmount'] . ':<span > ' . number_format($c['total'], 0, '.', ',') . ' ریال</p>				
				  </div>';
							
			  }
			}
			
			
			
		  }
		  return $m;
		}


		function fun_imgRadio_efb($id ,$link,$row){
			
			
			$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';
			$u = function($url){
			  $url = preg_replace('/(http:@efb@)+/g', 'http://', $url);
			  $url = preg_replace('/(https:@efb@)+/g', 'https://', $url);
			  $url = preg_replace('/(@efb@)+/g', '/', $url);
			  return $url;
			 };
			$value = isset($row['value'])  ? $row['value'] : '';
			$sub_value = isset($row['sub_value']) ? $row['sub_value'] : '';
			$link =strpos($link,'http')==false ?  $poster : $row['src'];
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
	public function cache_cleaner_Efb($page_id){
		if (defined('LSCWP_V') || defined('LSCWP_BASENAME' )){
			//litespeed done			
			do_action( 'litespeed_purge_post', $page_id );
		}else if (function_exists('rocket_clean_post')){
			//wp-rocket done					
			$r = rocket_clean_post($page_id);			
		}elseif (function_exists('wp_cache_post_change')){
			//WP Super Cache done			
			//Jetpack done
			$GLOBALS["super_cache_enabled"]=1;
			wp_cache_post_change($page_id);
		}elseif(function_exists('autoptimize_filter_js_noptimize ')){
			//auto-Optimize done
			autoptimize_filter_js_exclude(['jquery.min-efb.js','core-efb.js']);
			autoptimize_filter_js_noptimize();
		}elseif(class_exists('WPO_Page_Cache')){
			//WP-Optimize done
			\WPO_Page_Cache::delete_single_post_cache($page_id);
		}elseif(function_exists('w3tc_flush_post')){
			//W3 Total Cache done			
			w3tc_flush_post($page_id);
		}elseif(function_exists('wpfc_clear_post_cache_by_id')){
			//WP Fastest Cache done
			wpfc_clear_post_cache_by_id($page_id);
		}elseif(has_action('wphb_clear_page_cache')){
			//Hummingbird done			
			do_action( 'wphb_clear_page_cache', $page_id );
		}

	}

	public function comper_version_efb($v){

		if(version_compare(EMSFB_PLUGIN_VERSION,$v)!=0 ){
			
			$efbFunction =  $this->get_efbFunction(1) ;
			$efbFunction->setting_version_efb_update('null' ,$this->pro_efb);
		}
	}

	public function form_preview_efb(){
		//check request is ajax and nonce
		if (  check_ajax_referer('admin-nonce', 'nonce') != 1) {
			die();
		}
		$new_page_id = 0;
		//get current user id 
		$current_user = get_current_user_id();
		if(!isset($_POST['id'])) return;
		$id = sanitize_text_field($_POST['id']);
		// error_log( __LINE__.':'.$id);
		$r = 'preview@'. str_replace([' ', '[', ']', '='], '', $id);
		$r = strtolower($r);
		//error_log($r);
		$v = get_option($r);
		// error_log( __LINE__.':'.$v);
		if($v != false ){
			 wp_update_post(array(
				'ID'             => $v,
				'post_content'   => ' '.$id.' ', 
				'post_status'    => 'draft',
			));				
			$new_page_id =$v;
		}else{
			//insert page by form id
			$new_page_id = wp_insert_post(array(
				'post_title'     => esc_html__('Form Preview', 'easy-form-builder'),
				'post_type'      => 'page',
				'post_name'      => 'easy-form-builder-preview',
				'post_content'   => ' '.$id.' ', 
				'post_status'    => 'draft',
				'post_author'    => $current_user, // or any other author id
			));
			$v ='preview@'. str_replace([' ', '[', ']', '='], '', $id);
			$v = strtolower($v);
			update_option( $v, $new_page_id);
			//86400 > +12 hours
			$args = [$new_page_id,$v];
			// error_log(json_encode($args));
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
		// error_log(json_encode($this->setting->activeCode));
		if(isset($this->setting->email_key)){
		  
		}else{
			//create randome string lenght 10
			$rand = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
			//check if not exist email_key add email_key with $rand value and added to table
			$this->setting->email_key = $rand;
			$setting = json_encode($this->setting,JSON_UNESCAPED_UNICODE);
			$setting= str_replace('"', '\"', $setting);  
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
			
		}
		return g($track , $this->setting->email_key);

	}

	public function get_efbFunction($state){
		if(empty($this->efbFunction)){ 
			if(!class_exists('Emsfb\efbFunction')){
				require_once(EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php');
			}
			$this->efbFunction = new \Emsfb\efbFunction();
		}

		
		if($state==1) return $this->efbFunction;
	}
	
}

new _Public();
