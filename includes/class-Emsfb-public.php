<?php

namespace Emsfb;

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
	public function __construct() {
		

		global $wpdb;
		$this->db = $wpdb;
		$this->id =-1;
		$this->pro_efb =false;
		add_shortcode( 'Easy_Form_Builder_confirmation_code_finder',  array( $this, 'EMS_Form_Builder_track' ) ); 
		add_action('wp_ajax_nopriv_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_action('wp_ajax_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_action('wp_ajax_mail_send_submited_Emsfb', array( $this,'mail_send_form_submit'));
		add_action('wp_ajax_nopriv_mail_send_submited_Emsfb', array( $this,'mail_send_form_submit'));
		
		add_action('wp_enqueue_scripts', array($this,'fun_footer'));
		
		add_action('wp_ajax_nopriv_update_file_Emsfb', array( $this,'file_upload_public'));
		add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));
		
		
		add_action('wp_ajax_nopriv_get_track_Emsfb', array( $this,'get_ajax_track_public'));
		add_action('wp_ajax_get_track_Emsfb', array( $this,'get_ajax_track_public'));
		
		add_action('wp_ajax_nopriv_pay_stripe_sub_efb', array( $this,'pay_stripe_sub_Emsfb'));
		add_action('wp_ajax_pay_stripe_sub_efb', array( $this,'pay_stripe_sub_Emsfb'));
		
		
		add_action( 'wp_ajax_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		add_action( 'wp_ajax_nopriv_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند

		add_action( 'wp_ajax_pay_IRBank_payEfb',  array($this, 'persia_pay_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		add_action( 'wp_ajax_nopriv_pay_IRBank_payEfb',  array($this, 'persia_pay_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند

		add_action( 'email_recived_new_message_hook_efb', array($this, 'corn_email_new_message_recived_Emsfb' ) ); //send email by cron wordpress
		
		$this->efbFunction = new efbFunction();  
		add_shortcode( 'EMS_Form_Builder',  array( $this, 'EFB_Form_Builder' ) ); 
		add_action('init',  array($this, 'hide_toolmenu'));
		$this->text_ = ["atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","mcplen","mmxplen","mxcplen","clcdetls","required","mmplen","offlineSend","amount","allformat","videoDownloadLink","downloadViedo","removeTheFile","pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","somethingWentWrongPleaseRefresh","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank"];		
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
		/* wp_register_script('efb_js', plugins_url('../public/assets/js/efb.js',__FILE__), null, null, true);
		wp_enqueue_script('efb_js'); */
	}

	public function EFB_Form_Builder($id){
		
		$state_form = isset($_GET['track'])  ? sanitize_text_field($_GET['track']) : 'not';
		$admin_form =isset($_GET['user'])  && $_GET['user']=="admin"  ? true : false;
		//error_log($_GET['user']);
		//error_log(is_user_logged_in());
		if($admin_form==true && is_user_logged_in()==false){
			return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'>⚠️</h2><h3 class='efb warning text-center text-darkb fs-4'>".__('It seems that you are the admin of this form. Please login and try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
		}
		$table_name = $this->db->prefix . "emsfb_form";
		if($this->id!=-1){return __('Easy Form Builder' , 'easy-form-builder');}

		$row_id = array_pop($id);
		$this->id = $row_id;
		
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$row_id'" );
		
		if($value_form!=null){
			$typeOfForm =$value_form[0]->form_type;
			
			if($state_form!='not' && strlen($state_form)>7 
			&& ($typeOfForm!="register" || $typeOfForm!="login")){
				
				$this->id =-1;
				
				return $this->EMS_Form_Builder_track();
			}
		}
		
		$this->public_scripts_and_css_head();
		
		
		$state="";
		$pro=  $this->pro_efb;
		//$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		//$text =isset($this->text_) ?  $this->text_ : $this->text_ = ["pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","somethingWentWrongPleaseRefresh","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank"];
		//$this->lanText= $this->efbFunction->text_efb($text);
		$lanText= $this->efbFunction->text_efb($this->text_);
		/* $table_name = $this->db->prefix . "emsfb_form";
		
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$row_id'" ); */
		if($value_form==null){
			return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'> <div class='efb text-center my-5'><div class='efb text-danger bi-exclamation-triangle-fill efb text-center display-1 my-2'></div><h3 class='efb  text-center text-darkb fs-4'>".$lanText["formNExist"]."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb'>".__('Easy Form Builder', 'easy-form-builder')."<p></div></div>";
		}/* else{
			$this->fun_convert_form_structer($value_form[0]->form_structer);
		} */
		$typeOfForm =$value_form[0]->form_type;
		$value = $value_form[0]->form_structer;
		$value = preg_replace('/\\\"email\\\":\\\"(.*?)\\\"/', '\"email\":\"\"', $value);
		$lang = get_locale();
		$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;
		
		
		

		$advanced = ["removeTheFile","heading" , "link" , "payMultiselect" , "paySelect" , "payRadio" , "payCheckbox" , "stripe" , "switch" , "rating" , "esign" , "maps" , "color" , "html" , "yesNo" , "stateProvince" , "conturyList" , "mobile" , "persiaPay"];
		

		$state="form";		
		if(strpos($value , '"type\":\"multiselect\"') || strpos($value , '"type":"multiselect"') || strpos($value , '"type\":\"payMultiselect\"') || strpos($value , '"type":"payMultiselect"')){
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js',false,'3.5.24');
			wp_enqueue_script('efb-bootstrap-select-js'); 
			
			
			wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select.css', true,'3.5.24' );
			wp_enqueue_style('Emsfb-bootstrap-select-css');
		
		}
		
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			
			$stng=$lanText["settingsNfound"];
			$state="form";
			
		}
		$paymentType="";
		$paymentKey="null";
		$this->setting= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');

		$refid = isset($_GET['Authority'])  ? sanitize_text_field($_GET['Authority']) : 'not';
		$Status_pay = isset($_GET['Status'])  ? sanitize_text_field($_GET['Status']) : 'NOK';
		//error_log($typeOfForm);
		if($typeOfForm=="payment"){
			
			$r = $this->setting;
			
			if(gettype($r)=="string"){
				$setting =str_replace('\\', '', $r);
				$setting =json_decode($setting);
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				
				
				if(isset($setting->activeCode) &&  md5($server_name) ==$setting->activeCode){$pro=true;}
				//"type":"stripe"
				if(strpos($value , '\"type\":\"stripe\"') || strpos($value , '"type":"stripe"')){$paymentType="stripe";}
				else if(strpos($value , '\"type\":\"persiaPay\"') || strpos($value , '"type":"persiaPay"')){
					$paymentType="zarinPal";
				}else if(strpos($value , '\"type\":\"zarinPal\"') || strpos($value , '"type":"zarinPal"')){$paymentType="zarinPal";}
					if($paymentType!="null" && $pro==true){
						wp_register_script('pay_js', plugins_url('../public/assets/js/pay.js',__FILE__), array('jquery'), true, '3.5.24');
						wp_enqueue_script('pay_js');
						
						if($paymentType=="stripe"){ 
							
							wp_register_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);	
							wp_enqueue_script('stripe-js');

							wp_register_script('parsipay_js', plugins_url('../public/assets/js/stripe_pay.js',__FILE__), array('jquery'), true, '3.5.24');
							wp_enqueue_script('parsipay_js');
							//pub key stripe
							$paymentKey=isset($setting->stripePKey) && strlen($setting->stripePKey)>5 ? $setting->stripePKey:'null';							
						}else if($paymentType=="persiaPay" || $paymentType=="zarinPal"  || $paymentType="payping" ){
							$paymentKey=isset($setting->payToken) && strlen($setting->payToken)>5 ? $setting->stripePKey:'null';
							wp_register_script('parsipay_js', plugins_url('../public/assets/js/persia_pay.js',__FILE__), array('jquery'), true, '3.5.24');
							wp_enqueue_script('parsipay_js');
						}
				}

			
			}//end if payment
			
		
		}else if((strpos($value , '\"type\":\"pay') || strpos($value , '"type":"pay'))){
			//error_log('===========> added public pay.js');
			wp_register_script('pay_js', plugins_url('../public/assets/js/pay.js',__FILE__), array('jquery'), true, '3.5.24');
			wp_enqueue_script('pay_js');
		}
		
		if(strpos($value , '\"type\":\"pdate\"') || strpos($value , '"type":"pdate"')){

			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {
				//
				$this->efbFunction->addon_adds_cron_efb();
				 return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'>⚠️</h2><h3 class='efb warning text-center text-darkb fs-4'>".__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
			}
			
			require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
			$persianDatePicker = new persianDatePickerEFB() ; 	
		}
		if(strpos($value , '\"type\":\"ardate\"') || strpos($value , '"type":"ardate"')){

			if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {
				$this->efbFunction->addon_adds_cron_efb();
				 return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'>⚠️</h2><h3 class='efb warning text-center text-darkb fs-4'>".__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
			}
			require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
			$arabicDatePicker = new arabicDatePickerEfb() ; 
		}//end if custom date

		if(strpos($value , '\"type\":\"mobile\"') || strpos($value , '"type":"mobile"')){
			wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min.js', null, null, true);	
			wp_enqueue_script('intlTelInput-js');

			wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min.css',true,'3.5.28');
			wp_enqueue_style('intlTelInput-css');
		}

		if(strpos($value , '\"logic\":\"1\"') || strpos($value , '"logic":"1"')){
			wp_register_script('intlTelInput-js',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', null, null, true);	
			wp_enqueue_script('intlTelInput-js');
		}
				$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';
				$send=array();

				//translate v3
				//$this->text_=["pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","somethingWentWrongPleaseRefresh","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank"];
				$text= $lanText;

				$fs =str_replace('\\', '', $value_form[0]->form_structer);
				
				
				$formObj= json_decode($fs,true);
				if(($formObj[0]["stateForm"]==true || $formObj[0]["stateForm"]==1) &&  is_user_logged_in()==false ){
					$typeOfForm="";
					$value="";
					$stng="";
				}else{
					$formObj[0]["stateForm"] =false;
				}

				if($formObj[0]["thank_you"]=="rdrct"){
					$formObj[0]["rePage"]="";
					$val_ = json_encode($formObj,JSON_UNESCAPED_UNICODE);
					$value = str_replace('"', '\\"', $val_);
				}
				//modify_jquery_login_efb
				

				
				if($value_form[0]->form_type=="form"){

				}else if (($value_form[0]->form_type=="login" || $value_form[0]->form_type=="register")){
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
				
				
				
				
				$code = 'efb'.$this->id;
				$img = [
						'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils.js'
						];
								//$location = $this->pro_efb==true  ? $this->efbFunction->get_geolocation() :'';
				$location = '';
				$ar_core = array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
				'ajax_value' =>$value,
				'type' => $typeOfForm,
				'state' => $state,
				'language' => $lang,
				'id' => $this->id,			  
				'form_setting' => $stng,
				'nonce'=> wp_create_nonce("public-nonce"),
				'poster'=> $poster,
				'rtl' => is_rtl(),
				'text' =>$text ,
				'pro'=>$this->pro_efb,
				'wp_lan'=>get_locale(),
				'location'=> $location,
				'v_efb'=>EMSFB_PLUGIN_VERSION,
				'nonce_msg'=> wp_create_nonce($code),
				'images' => $img,
				
				);

			if($typeOfForm=="payment"){
					$ar_core = array_merge($ar_core , array(
						'paymentGateway' =>$paymentType,
						'paymentKey' => $paymentKey
					));
			}
		wp_localize_script( 'core_js', 'ajax_object_efm',$ar_core);  
		 $k="";
		// $pro=false;		
		 //$stng = $this->get_setting_Emsfb('pub');
		 $stng = $this->pub_stting;
		 if(gettype($stng)!=="integer" && $lanText["settingsNfound"]){
			// $valstng= json_decode($stng);
			 if( $formObj[0]["captcha"]==true && (isset($this->pub_stting->siteKey)==true) && strlen($this->pub_stting->siteKey)>1)
			 {				
				 $k =$this->pub_stting->siteKey;
				 $k = "<script>let sitekye_emsFormBuilder='".$k."'</script>";
			 }
			  
			 //error_log("this->pub_stting->apiKeyMap");
			 //error_log(json_encode($this->pub_stting));
			 //error_log($this->pub_stting->mapKey);
			 //error_log($this->pub_stting["mapKey"]);
			 if( isset($this->pub_stting["mapKey"]) && strlen($this->pub_stting["mapKey"])>5 && (strpos($value , '\"type\":\"maps\"') || strpos($value , '"type":"maps"'))){
				 //error_log('maps!!!!!!!!!!!!!!!');
				 $key= $this->pub_stting["mapKey"];
				 $lng = strval(get_locale());
				 
					 if ( strlen($lng) > 0 ) {
					 $lng = explode( '_', $lng )[0];
					 }
				 wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$key.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				 wp_enqueue_script('googleMaps-js');
			 }
		 }	
		 $efb_m= $pro==true ||$this->pro_efb==true ? "" :"<p class='efb fs-5 text-center my-1 text-pinkEfb'>".__('Easy Form Builder', 'easy-form-builder')."</p> ";
		 if($formObj[0]["stateForm"]==true){

			
			$content ="<div id='body_efb' class='efb card card-public row pb-3 efb px-2'> <div class='efb text-center my-5'>
			<div class='efb bi-shield-lock-fill efb text-center display-1 my-2'></div><h3 class='efb  text-center text-darkb fs-5'>". $lanText["formPrivateM"]."</h3>
			 ".$efb_m."
			</div> </div>";
		 }else{

			 $content="<div id='body_efb' class='efb card card-public row pb-3 efb px-2'>
			 <div class='efb text-center my-5'>
			 <div class='efb lds-hourglass efb text-center my-2' style='display:inline-block'></div><h3 class='efb  text-center text-darkb fs-5'>".$lanText["pleaseWaiting"]."</h2>
			 ".$efb_m."
			 </div>
			 
			 </div><div id='alert_efb' class='efb mx-5'></div>
			 ".$k."
			 ";
		 }

		
		return $content; 
		die();
		// 
	}


	public function EMS_Form_Builder_track(){

		if($this->id!=-1){return __('Easy Form Builder' , 'easy-form-builder');}
		$this->id=0;;
		
		$this->public_scripts_and_css_head();
		//Confirmation Code show
		$lang = get_locale();
		$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;
		
		
				$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ; 
				//translate v2
				$text=["atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","mcplen","mmxplen","mxcplen","mmplen","offlineSend","message","clsdrspn","createdBy","easyFormBuilder","payAmount","payment","id","methodPayment","ddate","updated","methodPayment","interval","file","videoDownloadLink","downloadViedo","pWRedirect","eJQ500","error400","errorCode","remove","minSelect","search","MMessageNSendEr","formNExist","settingsNfound","formPrivateM","pleaseWaiting","youRecivedNewMessage","WeRecivedUrM","thankFillForm","trackNo","thankRegistering","welcome","thankSubscribing","thankDonePoll","error403","errorSiteKeyM","errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound","errorMRobot","enterVValue","guest","cCodeNFound","errorFilePer","errorSomthingWrong","nAllowedUseHtml","messageSent","offlineMSend","uploadedFile","interval","dayly","weekly","monthly","yearly","nextBillingD","onetime","proVersion","payment","emptyCartM","transctionId","successPayment","cardNumber","cardExpiry","cardCVC","payNow","payAmount","selectOption","copy","or","document","error","somethingWentWrongTryAgain","define","loading","trackingCode","enterThePhone","please","pleaseMakeSureAllFields","enterTheEmail","formNotFound","errorV01","enterValidURL","password8Chars","registered","yourInformationRegistered","preview","selectOpetionDisabled","youNotPermissionUploadFile","pleaseUploadA","fileSizeIsTooLarge","documents","image","media","zip","trackingForm","trackingCodeIsNotValid","checkedBoxIANotRobot","messages","pleaseEnterTheTracking","alert","pleaseFillInRequiredFields","enterThePhones","pleaseWatchTutorial","somethingWentWrongPleaseRefresh","formIsNotShown","errorVerifyingRecaptcha","orClickHere","enterThePassword","PleaseFillForm","selected","selectedAllOption","field","sentSuccessfully","thanksFillingOutform","sync","enterTheValueThisField","thankYou","login","logout","YouSubscribed","send","subscribe","contactUs","support","register","passwordRecovery","info","areYouSureYouWantDeleteItem","noComment","waitingLoadingRecaptcha","itAppearedStepsEmpty","youUseProElements","fieldAvailableInProversion","thisEmailNotificationReceive","activeTrackingCode","default","defaultValue","name","latitude","longitude","previous","next","invalidEmail","aPIkeyGoogleMapsError","howToAddGoogleMap","deletemarkers","updateUrbrowser","stars","nothingSelected","availableProVersion","finish","select","up","red","Red","sending","enterYourMessage","add","code","star","form","black","pleaseReporProblem","reportProblem","ddate","serverEmailAble","sMTPNotWork","aPIkeyGoogleMapsFeild","download","copyTrackingcode","copiedClipboard","browseFile","dragAndDropA","fileIsNotRight","on","off","lastName","firstName","contactusForm","registerForm","entrTrkngNo","response","reply","by","youCantUseHTMLTagOrBlank"];				
				$text= $efbFunction->text_efb($text) ;
		
		$state="tracker";
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			$stng=$text["settingsNfound"];
			$state="tracker";
		}else{
			$valstng= json_decode($stng);
				if(isset($valstng->siteKey) && isset($valstng->scaptcha) && $valstng->scaptcha==true){
				wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload', null , null, true);
				wp_enqueue_script('recaptcha');
			}
			//error_log("valstng->apiKeyMap");
			//error_log($valstng->mapKey);
			if(isset($valstng->mapKey) && $valstng->mapKey!=""){
				
				$key= $valstng->mapKey;
				
				$lng = strval(get_locale());
				
					if ( strlen($lng) > 0 ) {
					$lng = explode( '_', $lng )[0];
					}
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$key.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
		}
		//$location = $this->pro_efb==true  ? $efbFunction->get_geolocation() :'';
		$location = '';
		
		wp_localize_script( 'core_js', 'ajax_object_efm',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'state' => $state,
			   'v_efb'=>EMSFB_PLUGIN_VERSION,
			   'language' => $lang,			  
			   'form_setting' => $stng,
			   'user_name'=> wp_get_current_user()->display_name,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
			   'rtl' => is_rtl(),
			   'text' =>$text,
			   'pro'=>$this->pro_efb,
			   'wp_lan'=>get_locale(),			   
			   'location'=>$location
		 ));  
		 $val = $this->pro_efb==true ? '<!--efb.app-->' : '<h3 class="efb fs-4 text-darkb mb-4">'.$text['easyFormBuilder'].'</h3>';
	 	$content="<script>let sitekye_emsFormBuilder='' </script><div id='body_tracker_emsFormBuilder'><div><div id='alert_efb' class='efb mx-5'><div class='efb text-center'><div class='efb lds-hourglass efb'></div><h3 class='efb fs-3 '>".$text["pleaseWaiting"]."</h3> ".$val."</div>";	
		return $content; 

	}


	function public_scripts_and_css_head(){
	
		wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style.css', true,'3.5.24');
		wp_enqueue_style('Emsfb-style-css');

		wp_register_script('core_js', plugins_url('../public/assets/js/core.js',__FILE__), array('jquery'), true,'3.5.24');
		wp_enqueue_script('core_js');
		

		wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons.css', true,'3.5.24');
		wp_enqueue_style('Emsfb-bootstrap-icons-css');
		


		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js',array('jquery'), true,'3.5.24');
		wp_enqueue_script('efb-main-js'); 		
		

		/* end v2 */
		
		

		if(is_rtl()){
			wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl.css', true ,'3.5.24');
			wp_enqueue_style('Emsfb-css-rtl');
		}

		
		//$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		$this->setting=$this->get_setting_Emsfb('setting');
		
		$googleCaptcha=false;
		$bootstrap =false;
		$pro=false;
		if(gettype($this->setting)=="string"){
			$setting =str_replace('\\', '', $this->setting);
			$setting =json_decode($setting);
			$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);			
			$bootstrap =isset($setting->bootstrap) ?$setting->bootstrap: false ;
			$googleCaptcha = strlen($setting->siteKey) >5  && strlen($setting->secretKey) >5? true:false;
			if(isset($setting->activeCode) &&  md5($server_name) ==$setting->activeCode){$pro=true;}			
			$this->pro_efb = $pro;
			$trackingCode = isset($setting->trackingCode) ? $setting->trackingCode : "";
			$siteKey = isset($setting->siteKey) ? $setting->siteKey : "";
			$mapKey = isset($setting->apiKeyMap) ? $setting->apiKeyMap : "";
			$paymentKey = isset($setting->stripePKey) ? $setting->stripePKey : "";
			$rtr=array("pro"=>$pro,"trackingCode"=>$trackingCode,"siteKey"=>$siteKey,"mapKey"=>$mapKey,"paymentKey"=>$paymentKey);
			
		}
		/* v2 */

		//اگر پرو بود اگر پلاگین نصب بود 
		//اگر یکی از پرو ها وجود داشت این لینک لود شود اگر نبود لود نشود
		if($this->pro_efb==1 ){
			wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els.js',false,'3.5.24');
			wp_enqueue_script('efb-pro-els'); 
		}
	
		if($bootstrap==false){
			


			wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min.js',false,'3.5.24');
			wp_enqueue_script('efb-bootstrap-min-js'); 
	
			
			wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min.js', array( 'jquery' ), true,'3.5.24');
			wp_enqueue_script('efb-bootstrap-bundle-min-js'); 
			
						
		}else{
			$it = get_template_directory();
	/* 		

			$it = list_files($it); 

			$s = false;
			$minb = true;
			$bund = true;
			foreach($it as $path) {
				if (preg_match("/\bbootstrap.min.js+/i", $path)) $minb = false;
				if (preg_match("/\bbootstrap.bundle.min.js+/i", $path)) $bund = false;				
			}
			if($minb){
				wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min.js',false,'3.5.24');
				wp_enqueue_script('efb-bootstrap-min-js'); 
			}
			if($bund){
				wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min.js', array( 'jquery' ), true,'3.5.24');
				wp_enqueue_script('efb-bootstrap-bundle-min-js'); 
			} */

		}
		
		wp_register_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min.css', true,'3.5.24');
		wp_enqueue_style('Emsfb-bootstrap-css');
		
		


	
		//change langugae recaptcha
		//https://stackoverflow.com/questions/18859857/setting-recaptcha-in-a-different-language-other-than-english
		
		//	wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload', null , null, true);
		if($googleCaptcha==true){
			$lang = get_locale();
			$lang =strpos($lang,'_')!=false ? explode( '_', $lang )[0]:$lang;
			
			
			$params = array(
				'hl' => $lang
			  );
			wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload', null , null, true);
			wp_enqueue_script('recaptcha');
		}
		
	  }


	  public function mail_send_form_submit(){
		
		$this->id = sanitize_text_field($_POST['id']);
		$track = $this->id ;
		$type = sanitize_text_field($_POST['type']); //two type msg/rsp

		
		if (check_ajax_referer($this->id,'nonce')==false){
			$response = array( 'success' => false  , 'm'=>'error 403'); 
			wp_send_json_success($response,$_POST);		
		}

		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: $this->get_setting_Emsfb('setting');
		$this->setting =$r;
		if(gettype($r)!="string"){return false;}
		$r = str_replace("\\","",$r);
		$setting =json_decode($r,true);;

		//$secretKey=isset($setting->secretKey) && strlen($setting->secretKey)>5 ?$setting->secretKey:null ;
		$email = isset($setting["emailSupporter"]) ?$setting["emailSupporter"] :null  ;
		$pro = isset($setting["activeCode"]) &&  strlen($setting["activeCode"])>5 ? $setting["activeCode"] :null ;
		$table_msgs = $this->db->prefix . "emsfb_msg_";
		$table_forms = $this->db->prefix . "emsfb_form";
		
		//error_log(json_encode($value_msgs));
		//$value_msgs = $this->db->get_results( "SELECT * FROM `$table_msgs` INNER JOIN $table_forms ON $table_msgs.form_id = $table_forms.form_id   WHERE $table_msgs.read_ = 3" );
		$value_msgs = $this->db->get_results( "SELECT * FROM `$table_msgs` INNER JOIN $table_forms ON $table_msgs.form_id = $table_forms.form_id   WHERE $table_msgs.read_ = 3" );

		//error_log(json_encode($value_msgs));
		$trackingCode ="";
		$admin_email ="";
		$user_email ="null";
		$fs;
		foreach ($value_msgs as $key => $value) {
				$trackingCode = $value->track;
				$fs = str_replace("\\","",$value->form_structer);			
				$msg = str_replace("\\","", $value->content);
				$msg_obj = json_decode($msg,true); //object of message
				$fs_obj = json_decode($fs,true); // object of form_structer
				//$this->id = $trackingCode;
				
			    $this->db->update( $table_msgs, array('read_' =>0), array( 'track' => $trackingCode ) );				
				$admin_email = $fs_obj[0]["email"];
				$w_ = end($msg_obj) ;
				$link = $w_['type']=="w_link" ? $w_['value'] :'null';
				
				
				//error_log(json_encode($w_));
				$this->fun_send_email_noti_efb($fs_obj,$msg_obj, $email,$track,$pro ,$admin_email,$link);
				
				
			}
				
		$response = array( 'success' => true  , 'm'=>'mail ok'); 
	  }

	  public function get_ajax_form_public(){
		
		
		$text_ =["bkXpM","bkFlM","mnvvXXX","ptrnMmm","clcdetls",'payment','error403','errorSiteKeyM',"errorCaptcha","pleaseEnterVaildValue","createAcountDoneM","incorrectUP","sentBy","newPassM","done","surveyComplatedM","error405","errorSettingNFound"];
		$efbFunction = new efbFunction() ;
		$this->lanText= $this->efbFunction->text_efb($text_);
		$this->id = sanitize_text_field($_POST['id']);
		$msgnonce = 'efb'.$this->id;
		
		
		
		//error_log(strval(wp_verify_nonce($msgnonce,'nonce_msg')));
		// 
		if (check_ajax_referer('public-nonce','nonce')==false){
				
			$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
			wp_send_json_success($response,$_POST);
		}

		if (check_ajax_referer($msgnonce,'nonce_msg')==false){
				
			$response = array( 'success' => false  , 'm'=>$this->lanText["error405"]); 
			wp_send_json_success($response,$_POST);
		}
		
		
		$r=  $this->get_setting_Emsfb('setting');
		$pro = false;
		$type =sanitize_text_field($_POST['type']);
		$email=get_option('admin_email');
		$setting;
		$rePage ="null";
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs = isset($value_form) ? str_replace('\\', '', $value_form[0]->form_structer) :'';
		
		
		$not_captcha=$formObj= $email_fa = $trackingCode = $send_email_to_user_state = $email_user= $check = "";
		$email_user="null";
		$this->value = $_POST['value'];
		
		$this->value =str_replace('\\', '', $this->value);
		$valo = json_decode($this->value , true);
		//error_log($_POST['value']);
		//error_log($this->value);
		
		if($fs!=''){
				$formObj=  json_decode($fs,true);
				if( !isset($valo['logout']) && !isset($valo['recovery']) ){
				$email_fa = $formObj[0]["email"];
				$trackingCode = $formObj[0]["trackingCode"];
				$send_email_to_user_state =$formObj[0]["sendEmail"];			
				//$type = $formObj[0]["type"];
				
				
				if($type!=$formObj[0]["type"]){
					$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
					wp_send_json_success($response,$_POST);
				}
				
				if($formObj[0]["thank_you"]=="rdrct"){
					$rePage= $this->string_to_url($formObj[0]["rePage"]);
				}
				
				/* form validation  */
				$valobj =[] ;
				$stated = 0;	
				$rt;	

				$_POST['url']= $url = sanitize_url($_POST['url']);
				
				
				//$d = $_SERVER['HTTP_HOST'];
				//$p = strpos($item['url'],'http://'.$d);
				//don't change value stated because always file is sending 
				
				if(isset($_POST['url']) && strlen($_POST['url'])>5 ){
					
					
					$ar = ['http://wwww.'.$_SERVER['HTTP_HOST'] , 'https://wwww.'.$_SERVER['HTTP_HOST'] ,'http://'.$_SERVER['HTTP_HOST'], 'https://'.$_SERVER['HTTP_HOST']];
					
					foreach ($ar as  $r) {
						$c=strpos($_POST['url'],$r);
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
					wp_send_json_success($response,$_POST);
				}
				
				
				error_log(json_encode($valo));
				error_log(json_encode($formObj));
				$mr=$this->lanText["error405"];
				$stated = 1;
				$form_condition = '';
				if(isset($formObj[0]['booking']) && $formObj[0]['booking']==1) $form_condition='booking';
				foreach ($formObj as $key =>$f){
						$rt =null;	
						$in_loop=true;						
						if($key<2) continue;
						if($stated==0){break;}
						$it= array_filter($valo, function($item) use ($f,$key,&$stated ,&$rt,&$formObj,&$in_loop,&$mr,$form_condition) { 
							if($in_loop==false){
								return;
							}
							//error_log($f['disabled']);
							//error_log(json_encode($f));
							//error_log('commmper');
							//error_log(isset($f['disabled'])==true &&  $f['disabled']==1  && isset($f['hidden']) ==false);
							//error_log(isset($f['disabled'])==true && $f['disabled']==1 && isset($f['hidden'])==true && $f['hidden']==false);
							if((( isset($f['disabled'])==true &&  $f['disabled']==1  && isset($f['hidden']) ==false) 
							|| ( isset($f['disabled'])==true && $f['disabled']==1 && isset($f['hidden'])==true && $f['hidden']==false ) )
							&&( $item['id_'] == $f['id_'] || $f['id_']==$item['id_'])
							&& strlen($item['value'])>1 ){
								//error_log("-------------------------->");
								//error_log($item['name']);
								$stated=0;
								$in_loop==false;
								return;
							}
							$t =strpos(strtolower($item['type']),'checkbox');
							if(isset($f['id_']) && isset($item['id_']) && ( $f['id_']==$item['id_'] 
							||  gettype($t)=="integer" && $f['id_']==$item['id_ob'])
							||( ( $f['type']=="persiaPay" ||$f['type']=="persiapay" || $f['type']=="payment") && $formObj[0]["type"]=='payment')) {   							
							
							if(isset($f['name'])){
						    $mr = $this->lanText["mnvvXXX"];
							$mr =str_replace('XXX', $f['name'], $mr );}
							
												
							//error_log($f["type"]);
							//error_log(json_encode($f));	
							switch ($f['type']) {
								case 'email':
									$stated=0;
									if(isset($item['value'])){
										$stated=1;
										$item['value'] = sanitize_email($item['value']);
										$rt= $item;
										$l=strlen($item['value']);
										
										if((isset($f['milen']) && $f['milen']> $l)||( isset($f['mlen']) && $f['mlen']< $l) ) {$stated=0;}
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
										//error_log($item['value']);
										$item['value'] = sanitize_url($item['value']);
										$l=strlen($item['value']);
										if((isset($f['milen']) && $f['milen']> $l)||( isset($f['mlen']) && $f['mlen']< $l) ) {$stated=0;}
									}
									# code...
									$rt= $item;
									$in_loop=false;
									break;
								case 'mobile':
									//error_log('===========> mobile');
									//error_log(json_encode($f["c_n"]));
									//error_log($item['value']);
									//error_log(json_encode($f));
									$stated=0;
									
									if(isset($item['value'])){
										$stated=0;
										$item['value'] = sanitize_text_field($item['value']);	
										//error_log("====>stated");
										//error_log($stated);
										$l = isset($f["c_n"]) ? $f["c_n"] : ['all'];
										 array_filter($l, function($no) use($item , &$stated){
											//error_log("value===c");
											//$stated=0;
											//error_log(strpos($item['value'] , '+'.$no));
											//error_log($item['value']);
											//error_log($no);
											//error_log($stated);
											$v = strpos($item['value'] , '+'.$no);
											//error_log($v);
											//error_log($v === 0);
											if ( strpos($item['value'] , '+'.$no) === 0 || $no=='all') $stated=1;
											
										});		
										//error_log("====>stated");
										//error_log(json_encode($stated));
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
													error_log('booking con inside radio');
													error_log($ki);
													error_log(json_encode($formObj[$ki]));
													error_log(wp_date('Y-m-d'));
													if(isset($fr['dateExp'])==true){
														error_log($fr['dateExp']);

														error_log(strtotime($fr['dateExp']));
														error_log(strtotime(wp_date('Y-m-d')));
														//$fr['dateExp'] ='04-04-2023';
														if(strtotime($fr['dateExp'])<strtotime(wp_date('Y-m-d'))){
															$stated=0;
															$mr = $this->lanText["bkXpM"];
															$mr =str_replace('XXX', $fr['value'], $mr );
															
														}
														error_log($mr);
														
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
												$rt= $item;
												return;
											}
										},ARRAY_FILTER_USE_BOTH );
									}
									//error_log(json_encode($item));

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
												
												error_log("json_encodef");
												error_log(json_encode($f));
												if($form_condition=='booking')	{
													error_log('booking con inside options');
													error_log($key);
													error_log(wp_date('Y-m-d'));
													error_log(isset($f['dateExp']));
													if(isset($f['dateExp'])==true){
														error_log($f['dateExp']);
														//$f['dateExp']="04-04-2023";
														error_log(strtotime($f['dateExp']));
														error_log(strtotime(wp_date('Y-m-d')));
														
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
															error_log($formObj[$key]['registered_count']);
															$formObj[$key]['registered_count'] =(int) $formObj[$key]['registered_count'] +1;
															error_log($formObj[$key]['registered_count']);
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
								case 'table_matrix':					
									$t = strpos(strtolower($item['type']),'r_matrix');
									if(gettype($t)!='boolean'){
										
									}
									$stated=0;
									if(isset($item['value'])){
										$item['value'] = sanitize_text_field($item['value']);
										$item['name'] = sanitize_text_field($item['name']);
										//error_log(json_encode($item));
										//error_log(json_encode($f));								
									
										
										
										//array_filter($formObj, function($fr) use($item,&$rt,&$stated) { 											
											if((isset($f['id_']) && isset($item['id_']) && $f['id_']==$item['id_'] )){
												//error_log($item['id_']);
												//error_log($f['id_']);
												$item['value']=$f['value'];
												$item['value']=$f['value'];
												$rt = $item;
												$stated=1;
												$t=strpos($item['type'],'pay');
												//gettype($t)!='boolean'
												if(gettype($t)!='boolean'){
													$item['price']=$f['price'];
												}											
											}
										//});
									}
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
								case 'stateProvince':
								case 'conturyList':
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
									//error_log('maps');
									//error_log(json_encode($item['value']));
									$stated=1;
									$rt= $item;
									$c = 0;
									//$item['value'] =$item['value'];
									foreach ($item['value'] as $key => $value) {
										$c+=1;
										//error_log($value["lat"]);
										//error_log($value["lng"]);
										//error_log(is_numeric($value["lat"]));
										if(is_numeric($value["lat"])==false || is_numeric($value["lng"])==false){ $stated=0;$rt =null;};
										//error_log(is_numeric($value["lng"]));
									}
									if($c!=$f["mark"]){ $stated=0;$rt =null;}
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
									if(gettype($t)=="integer"){
										$stated=1;
										return;
									}
									
								//error_log(gettype(strpos(strtolower($f['type']),'checkbox'))!='boolean');
									if(isset($item['value'])){
										
										$stated=1;
										$item['value'] = sanitize_text_field($item['value']);
										$l=strlen($item['value']);	
										/* new code */
										if(isset($f['milen'])!=true  &&   isset($f['mlen'])!=true){	$stated=1;	}						
										else if((isset($f['milen'])==true && $f['milen']>0 && $f['milen']> $l)) {											
											$mr = $this->lanText["ptrnMmm"];
											$mr =str_replace('XXX', $f['name'], $mr );
											$mr =str_replace('NN', $f['mlen'], $mr );											
											$stated=0;
										}
										else if( isset($f['mlen'])==true && $f['mlen']>0   && $f['mlen']< $l) {
												
											$mr = $this->lanText["ptrnMmm"];							
											$mr =str_replace('NN', $f['mlen'], $mr );
											$mr =str_replace('XXX', $f['name'], $mr );
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
				array_push($valobj,array('type'=>'w_link','value'=>$url,'amount'=>-1));

				
			
				/* 	//test return
				return ; */
				$this->id = $type=="payment" ? sanitize_text_field($_POST['payid']) :$this->id ;
				$not_captcha= $type!="payment" ? $formObj[0]["captcha"] : "";
				if($stated==0){
					error_log($mr);
					$response = array( 'success' => false  , 'm'=>$mr); 
					wp_send_json_success($response,$_POST);
				}

					
					
					$this->value = json_encode($valobj,JSON_UNESCAPED_UNICODE);
					
					$this->value = str_replace('"', '\\"', $this->value);
					if($form_condition=='booking'){
						$table_name = $this->db->prefix . "emsfb_form";
						//,`form_name` =>
						$id = sanitize_text_field($_POST['id']);
						$value =json_encode($formObj,JSON_UNESCAPED_UNICODE);
						error_log("================>inside booking check!");
						error_log($value);
						$r = $this->db->update($table_name, ['form_structer' => $value], ['form_id' => $id]);
						error_log(json_encode($r));
					}
					
				
				/*end form validation  */	
				
			}else if ($fs==''){
				$m = "Error 404 ";
				$response = array( 'success' => false  , 'm'=>$m); 
				wp_send_json_success($response,$_POST);
			}
		}


	
	

		
		if(true){
			
			$captcha_success="null";
			$r= $this->setting ;
			if(gettype($r)=="string" && $fs!=''){
				$setting =str_replace('\\', '', $r);
				$setting =json_decode($setting);
				$secretKey= isset($setting->secretKey) && strlen($setting->secretKey)>5 ? $setting->secretKey : null;
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				
				if(isset($setting->activeCode) &&!empty($setting->activeCode) && md5($server_name) ==$setting->activeCode){
					
					$pro=true;
				}
				
				$response=$_POST['valid'];
				
				$args = array(
					'secret'        => $secretKey,
					'response'     => $response,
				);
				/* error_log(json_encode($formObj));
				//error_log(json_encode($formObj[0])); */
				if(gettype($formObj)!="string" && $formObj[0]['type']!='payment' && $formObj[0]['captcha']==true && strlen($response)>5 && $formObj[0]["captcha"]==true){				
					
					if(isset($setting->secretKey) && strlen($setting->secretKey)>5){
						$verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
							//error_log(json_encode($verify));
							
							$captcha_success =json_decode($verify['body']);
							
						//$not_captcha=false;	 
					}else{
						//secretkey is not valid		
						$response = array( 'success' => false  , 'm'=>$this->lanText["errorSiteKeyM"]); 
						wp_send_json_success($response,$_POST);
						return;
					}
				}
			}

			if ($type=="logout" || $type=="recovery") {$not_captcha==true;}
			if ($not_captcha==true && ( $captcha_success=="null" || $captcha_success->success!=true )  ) {
			
			$response = array( 'success' => false  , 'm'=>$this->lanText["errorCaptcha"]); 
			wp_send_json_success($response,$_POST);
			die();
			}else if ($not_captcha==false || ($not_captcha==true &&  $captcha_success->success==true)) {
			if(empty($_POST['value']) || empty($_POST['name']) || empty($_POST['id']) ){
				$response = array( 'success' => false , "m"=>$this->lanText["pleaseEnterVaildValue"]); 
				wp_send_json_success($response,$_POST);
				die();
			}
			
			$this->name = sanitize_text_field($_POST['name']);
			
			$this->id = sanitize_text_field($_POST['id']);		
			
			if($send_email_to_user_state==true || $send_email_to_user_state=="true"){
				$email_user = array_filter($valobj, function($item) use($formObj){ 
					if(isset($item['id_']) && $item['id_']==$formObj[0]["email_to"]){return $item["value"];}					
				});			
			}
							$ip = $this->ip=$this->get_ip_address();						
							//$this->location = $efbFunction->iplocation_efb($ip,1);
					switch($type){
						case "form":						
							
							$check=	$this->insert_message_db(3,false);
									
							$timed = time();									
							$timed += 20;
													
							//wp_schedule_single_event( $timed, 'email_recived_new_message_hook_efb' ); 							
							
							$response = array( 'success' => true  ,'ID'=>$_POST['id'] , 'track'=>$check  , 'ip'=>$ip,'nonce'=>wp_create_nonce($check)); 
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }
							wp_send_json_success($response,$_POST);
							
						break;
						case "payment":								
							$id = sanitize_text_field($_POST['payid']);
							$table_name_ = $this->db->prefix . "emsfb_msg_";
							$currentDateTime = date('Y-m-d H');
							$payment_getWay =isset($_POST['payment']) ? sanitize_text_field($_POST['payment']) :'stripe';
							if( strlen($id)<7 && $payment_getWay=="zarinPal"){
								$response = array( 'success' => false , "m"=>"خطای داده های پرداختی ، صفحه را رفرش کنید"); 
								wp_send_json_success($response,$_POST);
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
										$TokenCode = $setting->payToken;
										$data = array("merchant_id" => $TokenCode, "authority" => sanitize_text_field($_POST['auth']), "amount" => $amount);
										$jsonData = json_encode($data);
										$msg="ok";
						
										
									
										/* try{
											$ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
											curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
											curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
											curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
											curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
											curl_setopt($ch, CURLOPT_HTTPHEADER, array(
												'Content-Type: application/json',
												'Content-Length: ' . strlen($jsonData)
											));
											$result = curl_exec($ch);
											curl_close($ch);
											
											$result = json_decode($result, true);
											
											if($result['errors']){
											 $msg = 'زرین پال ، شرح خطا:' . $result['errors']['code'];
											}else{        
												if($result['data']['code'] == 100 || $result['data']['code'] == 101){
						
													$refid =$result['data']['ref_id'];
													if( isset($refid) && $refid != '' ){
														$msg = 'ok';
														$outp['msg'] = $msg;
														$status=true;
													}else{
														$msg = 'متافسانه زرین پال  قادر به دریافت کد پیگیری نمی‌باشد! نتیجه درخواست: ' . $header['http_code'];
													}
												}elseif($header['http_code'] == 400){
													$msg = ' تراکنش ناموفق بود، شرح خطا: ' . $response;
													$outp['msg'] = $msg;
												}else{
													
													$msg = ' تراکنش ناموفق بود، شرح خطا: ' . $header['http_code'];
												}
											}
										}catch( Exception $e ){
											$msg = ' تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
										}  */
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
										wp_send_json_success($response,$_POST);
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
										"paymentIntent"=>sanitize_text_field($_POST['auth']),
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
									wp_send_json_success($response,$_POST);
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
									
									//error_log(json_encode($v));
									array_push($fs,$v);
									
								}
								array_push($fs,array('type'=>'w_link' , 'id_'=>'w_link' , 'id'=>'w_link','value'=>$url,'amount'=>-1));
								$filtered=json_encode($fs ,JSON_UNESCAPED_UNICODE);	
								$fs=str_replace('"', '\\"', $filtered);
								$this->value = sanitize_text_field($fs);									
								$this->id = sanitize_text_field($_POST['payid']);			
								$check=$this->update_message_db();								

								if(!empty($r)){
									
									//$setting =json_decode($r->setting);	
														
									if (isset($setting) && strlen($setting->emailSupporter)>2){
									
										$email = $setting->emailSupporter;
									}
									$this->send_email_Emsfb($email,$trackId,$pro,"newMessage",$url);
									if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null"){
										if($trackingCode=="true"||$trackingCode==true)
										{
											foreach($email_user as $key => $val){	
												$this->send_email_Emsfb($val['value'],$trackId,$pro,"notiToUserFormFilled_TrackingCode",$url);
											}
										//$this->send_email_Emsfb($email_user,$trackId,$pro,"notiToUserFormFilled_TrackingCode");
										}else{
											foreach($email_user as $key => $val){
												$this->send_email_Emsfb($val['value'],$trackId,$pro,"notiToUserFormFilled",$url);
											}
										 //$this->send_email_Emsfb($email_user,$trackId,$pro,"notiToUserFormFilled");
										}
									}
								}
							}else{
								$response = array( 'success' => false  ,'m'=>__('Error Code','easy-form-builder').'</br>'. __('Payment Form','easy-form-builder')); 
								wp_send_json_success($response,$_POST);
								die();
							}
							
								
							if(strlen($email_fa)>4){
								$this->send_email_Emsfb($email_fa,$trackId,$pro,"newMessage",$url);
								
							}
							
							
							$m = "Error 500";
							$response = $check == 1 ? array( 'success' => true  ,'ID'=>$_POST['id'] , 'track'=>$this->id ,'nonce'=>wp_create_nonce($this->id)  , 'ip'=>$ip) :  array( 'success' => false  ,'m'=>$m);
							if($rePage!="null" && $check == 1){$response = array( 'success' => true  ,'m'=>$rePage); }
							wp_send_json_success($response,$_POST);
							
							//unset($_SESSION["val_efb"]);
						break;
						case "register":
							
							$username ;
							$password;
							$email = 'null';
							$m = str_replace("\\","",$this->value);
							$registerValues = json_decode($m,true);					
							foreach ($registerValues as &$rv) {
								if ($rv['id_'] == 'passwordRegisterEFB'){
									$password=$rv['value'];
									$rv['value'] = str_repeat('*',strlen($rv['value']));
								}else if($rv['id_'] == 'usernameRegisterEFB'){
									$username=$rv['value'];
								}else if($rv['id_'] == 'emailRegisterEFB'){
									$email=$rv['value'];
								}
							}
						
							$r =$this->new_user_validate_efb($username,$email,$password);
							
							if(gettype($r)=="string"){
								$response = array( 'success' => false , 'm' =>$r); 
								wp_send_json_success($response,$_POST);
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
							$response;
							//error_log(json_encode($state));							
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
										//if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email!="null" ){
											$ms ="<p>".  __("username")  .":".$username ." </p> <p>". __("password")  .":".$password."</p>";
											$this->send_email_Emsfb($to,$ms,$pro,"register",$url);
											//$this->send_email_Emsfb($email_user,$ms,$pro,"register");
									   // }
										//$sent = wp_mail($to, $subject, strip_tags($message), $headers);
									//}
								}
								$response = array( 'success' => true , 'm' =>$m); 
								if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }
							}
							wp_send_json_success($response,$_POST);
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
								if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }								
								wp_send_json_success($response,$_POST);
							}else{
								// user not login
								$send=array();
								$send['state']=false;
								$send['pro']=$pro;

								$send['error']=$this->lanText["incorrectUP"];
								$response = array( 'success' => true , 'm' =>$send); 
								//error_log(json_encode($response));
								wp_send_json_success($response,$_POST);
							}
							
							
							


						break;
						case "logout":
							
							wp_logout();
							$response = array( 'success' => true  );
							wp_send_json_success($response,$_POST);
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
									if($pro==false) $efb ='<p> '. __("from").''. home_url(). ' '. $this->lanText["sentBy"] .'<b>['. __('Easy Form Builder' , 'easy-form-builder') .']</b></p>' ;
									$subject ="". __("Password recovery")."[".get_bloginfo('name')."]";
									$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
									$message ='<!DOCTYPE html> <html> <body><h3>'.  __('New Password')  .':'.$newpass.'</h3>
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
							wp_send_json_success($response,$_POST);
						break;
						case "subscribe":
							
							$check=	$this->insert_message_db(3,false);
			
							
							if(!empty($r)){
								//$setting =json_decode($r->setting);
								if (isset($setting->emailSupporter) && strlen($setting->emailSupporter)>2){
								//	
									$email = $setting->emailSupporter;
								}													
								if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null" ){

									foreach($email_user as $key => $val){$this->send_email_Emsfb($val['value'],'',$pro,"subscribe",$url);}
									// $this->send_email_Emsfb($email_user,"",$pro,"subscribe");
								}
							}
							if(strlen($email_fa)>4){
								
								$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage",$url);
							}

							$response = array( 'success' => true , 'm' =>$this->lanText["done"]); 
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); }
							wp_send_json_success($response,$_POST);
						break;
						case "survey":
							//$ip = $this->ip;
							$check=	$this->insert_message_db(3,false);
			
							
							if(!empty($r)){
								//$setting =json_decode($r->setting);
								if (isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5){
								
									$email = $setting->emailSupporter;
								}
			
								
								if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null" ){
									foreach($email_user as $key => $val){$this->send_email_Emsfb($val['value'],'',$pro,"survey",$url);}
									//$this->send_email_Emsfb($email_user,"",$pro,"survey");
							    }
							}
							if(strlen($email_fa)>4){
								
								$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage",$url);
							}
			
						
							
							$response = array( 'success' => true , 'm' =>$this->lanText["surveyComplatedM"]);
							if($rePage!="null"){$response = array( 'success' => true  ,'m'=>$rePage); } 
							wp_send_json_success($response,$_POST);
						break;
						case "reservation":
						break;

					
						
						default:									
						$response = array( 'success' => false  ,'m'=>$this->lanText["error405"]); 
						wp_send_json_success($response,$_POST);
					}

		}
		//recaptcha end
		}else{
			
			$response = array( 'success' => false , "m"=>$this->lanText["errorSettingNFound"]); 
			wp_send_json_success($response,$_POST);
		}
	  }//end function 

	  public function get_ajax_track_public(){		
		$this->public_scripts_and_css_head();
		$text_ = ['error403',"errorMRobot","enterVValue","guest","cCodeNFound"];
		$lanText= $this->efbFunction->text_efb($text_);
		if (check_ajax_referer('public-nonce','nonce')!=1){
			$response = array( 'success' => false  , 'm'=>$lanText["error403"]); 
			wp_send_json_success($response,$_POST);
			die();
		}		
		$response=$_POST['valid'];
		$captcha_success =[];
		$not_captcha=true;
		if(gettype($this->setting)=="string"){
		$r=str_replace('\\', '', $this->setting);
			 $setting =json_decode($r);
			 
			 /* $secretKey= isset($setting->secretKey) && strlen($setting->secretKey)>5 ? $setting->secretKey : 'null';
			if($secretKey!="null"){
				$verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
				$captcha_success =json_decode($verify['body']);
				$not_captcha=false;	 
			} */
		}
		 $strR = json_encode($captcha_success);
		 if (!empty($captcha_success) &&$captcha_success->success==false &&  $not_captcha==false ) {
		  $response = array( 'success' => false  , 'm'=> $lanText["errorMRobot"]); 
		  wp_send_json_success($response,$_POST);
		 }
		 else if ((!empty($captcha_success) && $captcha_success->success==true) ||  $not_captcha==true) {
			if(empty($_POST['value']) ){
				$response = array( 'success' => false , "m"=>$lanText["enterVValue"]); 
				wp_send_json_success($response,$_POST);
				die();
			}		
			$id = sanitize_text_field($_POST['value']);
			$this->ip=$this->get_ip_address();
			$ip = $this->ip;
			//$this->location = $this->efbFunction->iplocation_efb($ip,1);			
			$table_name = $this->db->prefix . "emsfb_msg_";
			$value = $this->db->get_results( "SELECT content,msg_id,track,date FROM `$table_name` WHERE track = '$id'" );				
			if($value!=null){
				//error_log('value is not null');
				$id=$value[0]->msg_id;
				//error_log($value[0]->msg_id);
				$id = preg_replace('/[,]+/','',$id);
				$this->id =$id;
				$table_name = $this->db->prefix . "emsfb_rsp_";
				$content = $this->db->get_results( "SELECT * FROM `$table_name` WHERE msg_id = '$id'" );
				//error_log(json_encode($content));
				foreach($content as $key=>$val){					
					$r = (int)$val->rsp_by;
					if ($r>0){
						$usr =get_user_by('id',$r);
						$val->rsp_by= $usr->display_name;
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
			
			wp_send_json_success($response,$_POST);
			}
			//send_email to admin of page
	  }//end function




	  public function fun_footer(){
		wp_register_script('jquery', plugins_url('../public/assets/js/jquery.js',__FILE__), array('jquery'), true,'3.5.24');
		wp_enqueue_script('jquery');
		return "<script>console.log('Easy Form Builder v3.5.9')</script>";
	  }//end function



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
        $vl=null;
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
                /* $m = str_replace('\\', '', $vl);       
                $vl = json_decode($m,true); */
                //"type":"file"
                //
            }
        
        }
		if (check_ajax_referer('public-nonce','nonce')!=1 && check_ajax_referer($vl,"nonce_msg")!=1){
			
			
			$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$this->text_ = empty($this->text_)==false ? $this->text_ :['error403',"errorMRobot","errorFilePer"];
		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
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
			// تنظیمات امنیتی بعدا اضافه شود که فایل از مسیر کانت که عمومی هست جابجا شود به مسیر دیگری
						
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

	public function set_rMessage_id_Emsfb(){
		
		$this->text_ = empty($this->text_)==false ? $this->text_ :["atcfle","cpnnc","tfnapca", "icc","cpnts","cpntl","clcdetls","required","mcplen","mmxplen","mxcplen","mmplen","offlineSend","settingsNfound","error405","error403","videoDownloadLink","downloadViedo",'error403',"pleaseEnterVaildValue","errorSomthingWrong","nAllowedUseHtml","guest","messageSent","MMessageNSendEr"];
		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		$this->lanText= $this->efbFunction->text_efb($this->text_);
		if (check_ajax_referer('public-nonce','nonce')!=1){
			
			$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$this->id =sanitize_text_field($_POST['id']);
		$by ="";
		$msgnonce = 'efb'.$this->id;
		if (check_ajax_referer($msgnonce,'nonce_msg')!=1){
			
			$response = array( 'success' => false  , 'm'=>$this->lanText["error403"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		if(empty($_POST['message']) ){
			$response = array( 'success' => false , "m"=>$this->lanText["pleaseEnterVaildValue"]); 
			wp_send_json_success($response,$_POST);
			die();
		}
		if(empty($_POST['id']) ){			
			$response = array( 'success' => false , "m"=>$this->lanText["errorSomthingWrong"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		if($this->isHTML($_POST['message'])){
			$response = array( 'success' => false , "m"=>$this->lanText["nAllowedUseHtml"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: $this->get_setting_Emsfb('setting');
		if(gettype($r)=="string"){
			$r =str_replace('\\', '', $r);
			$setting =json_decode($r);
			
			$secretKey=isset($setting->secretKey) && strlen($setting->secretKey)>5 ?$setting->secretKey:null ;
			$email = isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5 ?$setting->emailSupporter :null  ;
			$pro = isset($setting->activeCode) &&  strlen($setting->activeCode)>5 ? $setting->activeCode :null ;
			
			$response=$_POST['valid'];
			$id;
				$id=number_format(sanitize_text_field($_POST['id']));
				$m=sanitize_text_field($_POST['message']);
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
							wp_send_json_success($response,$_POST);
						}
				}
			
				$m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);
				$ip =$this->ip= $this->get_ip_address();
				//$this->location = $efbFunction->iplocation_efb($ip,1);
				//
				//error_log("----------->id");
				$id = preg_replace('/[,]+/','',$id);
				//preg_replace('/(@efb@)+/','/',$rePage);
				//error_log($id);
				//error_log(gettype($id));
				$table_name = $this->db->prefix . "emsfb_msg_";
				//error_log($table_name);
				$value=null;
				$value = $this->db->get_results( "SELECT * FROM `$table_name` WHERE msg_id = '$id'" );
				
				if($value==null|| $value[0]->read_==4){
					//error_log('not exist!');
					$response = array( 'success' => false  , 'm'=>$this->lanText["error405"]); 
					wp_send_json_success($response,$_POST);
					die();
				}
				$valn =str_replace('\\', '', $value[0]->content);
				//error_log($vv_);
				$msg_obj = json_decode($valn,true);
				$vv_="";
				$lst = end($msg_obj);
				$link_w = $lst['type']=="w_link" ? $lst['value'] : 'null';
			   //error_log("=============>link_w ");
			   //error_log($link_w );
				$table_name = $this->db->prefix . "emsfb_rsp_";				
				$this->db->insert($table_name, array(
					'ip' => $ip, 
					'content' => $m, 
					'msg_id' => $id, 
					'rsp_by' => get_current_user_id(), 
					'read_' => 0,
					'date'=>wp_date('Y-m-d H:i:s'),
					
				));  
				$track = $value[0]->track;
				
				$this->db->update($table_name,array('read_'=>0), array('msg_id' => $id) );
				$by=$this->lanText["guest"];
				$email_usr ="";

				//error_log(json_encode(wp_get_current_user()));
				
				if(get_current_user_id()!=0 && get_current_user_id()!==-1){
					$usr= wp_get_current_user();
					$by = $usr->user_nicename;
					$email_usr = $usr->user_email;
					
				}
				
				$form_id  = $value[0]->form_id;
				$table_name = $this->db->prefix . "emsfb_form";
				
				$vald = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$form_id'" );
				
				$valn =str_replace('\\', '', $vald[0]->form_structer);
				$valn= json_decode($valn,true);
				$usr;
				$email_fa = $valn[0]["email"];

				if (isset($setting->emailSupporter) && strlen($setting->emailSupporter)>5){
					$email = $setting->emailSupporter;
				}
				
				if($email!= null  && gettype($email)=="string" && $email != $email_usr) {
					$link = $link_w. "?user=admin";
					//error_log($link);
					$this->send_email_Emsfb($email,$track,$pro,"newMessage",$link);
				}
				if(strlen($email_fa)>4 && $email_usr!=$email_fa){
					$link = $link_w. "?user=admin";
					//error_log($link);
					$this->send_email_Emsfb($email_fa,$track,$pro,"newMessage",$link);
				}
				//error_log("===============>email of filled");
				//error_log($email_usr);
				//error_log("===============>email_fa");
				//error_log($email_fa);
				//error_log("===============>email");
				//error_log($email);
				if($email == $email_usr || $email_usr==$email_fa){
					//error_log('==============> ارسال پاسخ به کاربری که فرم را پر کرده است');
					//error_log($value[0]->content);

					$id =$valn[0]["email_to"];
					//error_log($id);
					//error_log($valn[0]["email_to"]);
					if(isset($id)){
						//error_log("================>id");

						/* $email_fa = array_filter($msg_obj, function($item) use($id){ 
							if(isset($item['id_']) && $item['id_']==$id){return $item["value"]["value"];}					
						});	 */
						foreach ($msg_obj as $key => $value) {
							if(isset($value['id_']) && $value['id_']==$id){
								$email_fa= $value["value"];
								break;
							}
						}
						//error_log($email_fa);
						if($email_fa!="") $this->send_email_Emsfb($email_fa,$track,$pro,"newMessage",$link_w);
					}
				}
				//messageSent s78
				
				$response = array( 
				'success' => true , "m"=>$this->lanText["messageSent"] , "by"=>$by,
				'track'=>$track,
				'nonce_msg'=>wp_create_nonce($track)); 										
				wp_send_json_success($response,$_POST);	
		}else{
			$m = $this->lanText["settingsNfound"] . '</br>' . $this->lanText["MMessageNSendEr"] ;
			$response = array( 'success' => false , "m"=>$m, "by"=>$by);
			wp_send_json_success($response,$_POST);	
		}

	}//end function

	public function send_email_Emsfb($to , $track ,$pro , $state,$link){
		
		$this->text_ = empty($this->text_)==false ? $this->text_ :["clcdetls","youRecivedNewMessage","WeRecivedUrM","thankRegistering","welcome","thankSubscribing","thankDonePoll"];
		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		//$link_w = strlen($link)>5 ? $link.'?track='.$track : home_url();
		if(strlen($link)>5){

			$link_w =strpos($link,'?')!=false ? $link.'&track='.$track : $link.'?track='.$track;
		}else{
			$link_w = home_url();
		}
		//error_log($to);
		//error_log($link_w);
		$this->lanText= $this->efbFunction->text_efb($this->text_);
		//error_log(json_encode($this->lanText));
				$cont = $track;
		$subject ="📮 ". $this->lanText["youRecivedNewMessage"];
		if($state=="notiToUserFormFilled_TrackingCode"){
			
			$subject =$this->lanText["WeRecivedUrM"];
			$message ="<h2>".$this->lanText["thankFillForm"]."</h2>
					<p>". $this->lanText["trackNo"].":<br> ".$cont." </p>
					<button><a href='".$link_w."' style='color: black;'>". $this->lanText["clcdetls"]."</a></button>
					";
			$cont=$message;
		}elseif($state=="notiToUserFormFilled"){

			$subject =$this->lanText["WeRecivedUrM"];	   
			$message ="<h2>".$this->lanText["thankFillForm"]."</h2>
			<button><a href='".home_url()."' style='color: black;'>".get_bloginfo('name')."</a></button>
			";
			$cont=$message;
		}elseif ($state=="register"){  
			$subject =$this->lanText["thankRegistering"];   	
			$message ="<h2>".$this->lanText["welcome"]."</h2>
			".$cont."
			<button><a href='".home_url()."' style='color: black;'>".get_bloginfo('name')."</a></button>
			";
			$cont=$message;
		}elseif ($state=="subscribe"){
			$subject =$this->lanText["welcome"];   
			$message ="<h2>".$this->lanText["thankSubscribing"]."</h2>
			<button><a href='".home_url()."' style='color: black;'>".get_bloginfo('name')."</a></button>
			";
			$cont=$message;
		}elseif ($state=="survey"){
			$subject =$this->lanText["welcome"];   
			$message ="<h2>".$this->lanText["thankDonePoll"]."</h2>
			<button><a href='".home_url()."' style='color: black;'>".get_bloginfo('name')."</a></button>
			";
			$cont=$message;
		}   
		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		//error_log($link);
		$check =  $efbFunction->send_email_state( $to,$subject ,$cont,$pro,$state,$link);
		
	}

	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function get_setting_Emsfb($state){
		// تنظیمات  برای عموم بر می گرداند
	 
	
	 
	 	$table_name = $this->db->prefix . "emsfb_setting";
 
 
	 	$value = $this->db->get_var( "SELECT setting,email FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
 		//error_log(gettype($value));
		$rtrn;
		$siteKey;
		$trackingCode ="";
		$mapKey="";

	
		if($value!= null){
			//error_log('count($value)>0');
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
				//error_log(json_encode($addons));
				//$this->pub_stting=array("pro"=>$pro,"trackingCode"=>$trackingCode,"siteKey"=>$siteKey,"mapKey"=>$mapKey,"paymentKey"=>$paymentKey,"addons"=>$addons);		

				$this->pub_stting=array("pro"=>$pro,"trackingCode"=>$trackingCode,"siteKey"=>$siteKey,"mapKey"=>$mapKey,"paymentKey"=>$paymentKey,
				"scaptcha"=>$scaptcha,"dsupfile"=>$dsupfile,"activeDlBtn"=>$activeDlBtn,"addons"=>$addons);
				$rtrn =json_encode($this->pub_stting,JSON_UNESCAPED_UNICODE);
				
			}else{
				$rtrn=$value;
				$this->setting =$rtrn;
			}
		}else{
			$rtrn=0;
		}
		//error_log(json_encode($rtrn));
	 //return $value[0];
	 return $rtrn;
	}

	public function pay_stripe_sub_Emsfb() {		
		
		$user = wp_get_current_user();
		$uid= $user->exists() ? $user->user_nicename :  __('Guest','easy-form-builder') ;
        if (check_ajax_referer('public-nonce', 'nonce') != 1) {
			
            $m = __('error', 'easy-form-builder') . ' 403';
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting:  $this->get_setting_Emsfb('setting');
		$Sk ='null';
		if(gettype($r)=="string"){
			$setting =str_replace('\\', '', $r);
			$setting =json_decode($setting);
			$Sk = isset($setting->stripeSKey) && strlen($setting->stripeSKey)>5  ? $setting->stripeSKey :'null';
		}

		if ($Sk=="null"){
			
				$m = __('Stripe', 'easy-form-builder').'->'.	__('error', 'easy-form-builder') . ' 402';
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, $_POST);
				die("secure!");
		}

		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe")) {	
			 $efbFunction->addon_adds_cron_efb();
			 return "<div id='body_efb' class='efb card-public row pb-3 efb px-2'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'>⚠️</h2><h3 class='efb warning text-center text-darkb fs-4'>".__('We have some changes. Please wait a few minutes before you try again.', 'easy-form-builder')."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><p></div></div>";
		}
        require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/autoload.php");
        
        
		$this->id = sanitize_text_field($_POST['id']);
		$val_ = sanitize_text_field($_POST['value']);
		
		/*  */
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		/*  */
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
				if($val_[$i]['price'] ) $price_c += $val_[$i]['price'];
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
						$m = __('error', 'easy-form-builder') . ' 405';
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, $_POST);
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
				}
				/* if($val_[$i]["type"]=="select"){ 
				}else if ($val_[$i]["type"]=="checkbox"){						
				}else{
					
					$filtered = array_filter($fs_, function($item) use ($iv) { 
						//error_log(json_encode($item));
						if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value']; 
					});
					$iv = array_keys($filtered);
					 $a = $iv[0];
				} */
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
		if($price_c != $price_f) {
			$t=time();
			$from =get_bloginfo('name')." <Alert@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			$to =get_option('admin_email');
			$message="This message from Easy form builder, This IP:".$this->ip. 
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
				$token= sanitize_text_field($_POST['token']);			
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
				$ar = (object)['id_'=>'payment','amount'=>0,'name'=> __('Payment','easy-form-builder') ,'type'=>'payment',
				'value'=> $payA , 'paymentIntent'=>$paymentIntent->id , 'paymentGateway'=>'stripe' ,
				'paymentAmount'=>$amount,'paymentCreated'=>$created ,'paymentcurrency' =>$price->currency, 'gateway'=>'stripe',
				'interval'=>$paymentIntent->plan->interval,'nextDate'=> $nextdate, 'paymentmethod'=>$paymentmethod
				,'uid'=>$uid ,'status'=>'active' ,'updatetime'=>$created , 'description'=>$description,'total'=>$amount ];
				 $filtered=array_merge($filtered , array($ar)); 
				 
				$response = array( 'success' => true  ,  'transStat'=>$ar , 'uid'=> $uid);
			}else{
				$amount = $paymentIntent->amount/100;
				$payA =  $amount  . ' '. $paymentIntent->currency;
				$ar = (object)['id_'=>'payment','amount'=>0,'name'=> __('Payment','easy-form-builder') ,'type'=>'payment',
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
			
			$this->name = sanitize_text_field($_POST['name']);
			$check=	$this->insert_message_db(2,false);
			
			//$response->transStat
			//array_push($response->transStat ,array('id'=>$check));
			$response=array_merge($response , ['id'=>$check]);
			//error_log(json_encode($response));
			wp_send_json_success($response, $_POST);

		}else{
			$response = array( 'success' => false  , 'm'=>__('Error Code:V02','easy-form-builder'));		
			wp_send_json_success($response, $_POST);
		}

    }
	public function persia_pay_Emsfb() {		
		/* 
		value: JSON.stringify(sendBack_emsFormBuilder_pub),
                id : efb_var.id,                      
                product:product,
                nonce: ajax_cor_pay.nonce,
                url :window.location.href
		*/
	
        if (check_ajax_referer('public-nonce', 'nonce') != 1) {
			
            $m = __('error', 'easy-form-builder') . ' 403';
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
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
			
				$m = __('persiaPayment', 'easy-form-builder').'->'.	__('error', 'easy-form-builder') . ' 402';
				$response = ['success' => false, 'm' => $m];
				wp_send_json_success($response, $_POST);
				die("secure!");
		}

		$this->id = sanitize_text_field($_POST['id']);
		$val_ = sanitize_text_field($_POST['value']);
		$url = sanitize_url($_POST['url']);
		
		/*  */
		$table_name = $this->db->prefix . "emsfb_form";
		$value_form = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		/*  */
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
				if($val_[$i]['price'] ) $price_c += $val_[$i]['price'];
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
						$m = __('error', 'easy-form-builder') . ' 405';
						$response = ['success' => false, 'm' => $m];
						wp_send_json_success($response, $_POST);
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
				/* if($val_[$i]["type"]=="select"){ 
				}else if ($val_[$i]["type"]=="checkbox"){						
				}else{
					
					$filtered = array_filter($fs_, function($item) use ($iv) { 
						//error_log(json_encode($item));
						if(isset($item['price']))	return $item['id_'] == $iv["id_ob"] &&  $item['value']==$iv['value']; 
					});
					$iv = array_keys($filtered);
					 $a = $iv[0];
				} */
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
			$message="This message from Easy form builder, This IP:".$this->ip. 
			" try to enter invalid value like fee of the service of a form at :".date("Y-m-d-h:i:s",$t) ;
			wp_mail( $to,"Warning Entry[Easy Form Builder]", $message, $headers );
		}
		$price_f = $price_f;
		$description =  get_bloginfo('name') . ' >' . $fs_[0]['formName'];		
		if($price_f>0){
			$currency= $fs_[0]['currency'] ;
			
			//private key
			/* $stripe = new \Stripe\StripeClient($Sk);
			$newPay = [
				'amount' => $price_f,
				'currency' => $currency,
				'payment_method_types' =>['card'],
				'description' =>$description,
			];

			 $subPay;
			 $amount;
			 $paymentIntent;
			 $amount;$created;$val ; */

			

			$filtered = array_filter($valobj, function($item) { 
				if(isset($item['price']))	return $item; 								
			});
			
			
			/* zarinpal pay start*/

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
				wp_send_json_success($response, $_POST);
				die();
			}
	/* 		try{
			
			
				$ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
				curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($jsonData)
				));
			
				$result = curl_exec($ch);
				$err = curl_error($ch);
				$result = json_decode($result, true, JSON_PRETTY_PRINT);
				curl_close($ch);
			
		
				//$result['data']["authority"]
				if ($err) {
					$msg = 'کد خطا: CURL#' . $er;
					$erro = 'در اتصال به درگاه مشکلی پیش آمد.';
					return false;
				} else {
				if (empty($result['errors'])) {
					if ($result['data']['code'] == 100) {

						//header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
						$auth = $result['data']["authority"];
						//$filtered +=['auth'=>$auth];
						$val_ = json_encode($filtered ,JSON_UNESCAPED_UNICODE);	
						
						
						$this->get_ip_address();
						$this->value = str_replace('"', '\\"', $val_);
						
						$this->name = sanitize_text_field($_POST['name']);
						$check=	$this->insert_message_db(2,$clientRefId);
						//$this->insert_temp_costumer($website,$paymentType,$product_price,'ZarinPal',$email,$name,$auth);
						$response;
						if(isset($check)==true){
							$GoToIPG = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"];
							$response = array( 'success' => true  ,'trackingCode'=>$check, 're'=>'در حال انتقال به درگاه بانک' , 'url'=>$GoToIPG);	
							
						}else{
							$response = array('success' => false, 'm' => 'DB Error 400-PP');
						}
						wp_send_json_success($response, $_POST);
						
					}
					} else {
					$erro ='Error Code: ' . $result['errors']['code'];
					$msg = 'تراکنش ناموفق بود، شرح خطا: ' .  $result['errors']['message'];
			
					}
				}
			}catch(Exception $e){
				$msg = 'تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
				$response = array( 'success' => false  , 're'=>$msg);				
			} */

			//EMSFB_PLUGIN_DIRECTORY."/vendor/autoload.php"
			require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay/zarinpal.php");
			$persiapay = new zarinPalEFB() ;
			$check;
			if(gettype($persiapay)=="object"){
				
				$response = $persiapay->create_bill_zarinPal($jsonData,$clientRefId);
				/* print_r($jsonData);
				print_r($response); */
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
			/* 		//payping
			$data = array(
				'clientRefId'   => $clientRefId,
				'payerIdentity' => $email,
				'Amount'        => $price_f,
				'Description'   => $des,
				'returnUrl'     => $returnUrl
			);
		
			try{
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.payping.ir/v2/pay",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 45,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => json_encode($data),
				CURLOPT_HTTPHEADER => array(
					"accept: application/json",
					"authorization: Bearer " . $TokenCode,
					"cache-control: no-cache",
					"content-type: application/json"
				),
					)
			);
			$response = curl_exec( $curl );
			
			
			$header = curl_getinfo( $curl );
			
			
			$err = curl_error( $curl );
			
			curl_close( $curl );
			
		
			if( $err ){
				$msg = 'کد خطا: CURL#' . $er;
				$erro = 'در اتصال به درگاه مشکلی پیش آمد.';
				$response = array( 'success' => false  , 're'=>$msg);	
				return false;				
			}else{
				if( $header['http_code'] == 200 ){
					$response = json_decode( $response, true );
					
					if( isset( $response ) and $response != '' ){
						$response = $response['code'];
						// ارسال به درگاه پرداخت با استفاده از کد ارجاع 
						$GoToIPG = 'https://api.payping.ir/v2/pay/gotoipg/' . $response;


						
						$auth = $this->generate_uuid();
						$this->insert_temp_costumer($website,$paymentType,$product_price,'payPing',$email,$name,$auth);

						$response = array( 'success' => true  , 're'=>'در حال انتقال به درگاه بانک' , 'url'=>$GoToIPG);	
						wp_send_json_success($response, $_POST);
						//header( 'Location: ' . $GoToIPG );
					}else{
						$msg = 'تراکنش ناموفق بود - شرح خطا: عدم وجود کد ارجاع';
					}
				}elseif($header['http_code'] == 400){
					$msg = 'تراکنش ناموفق بود، شرح خطا: ' . $response;
					$response = array( 'success' => false  , 're'=>$msg);
				}else{
					$msg = 'تراکنش ناموفق بود، شرح خطا: ' . $header['http_code'];
					$response = array( 'success' => false  , 're'=>$msg);
				}
					
			}
			}catch(Exception $e){
				$msg = 'تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
				$response = array( 'success' => false  , 're'=>$msg);				
			} */
			
			/* zarinpal pay end*/
			
			

			//array_push($filtered,$ar);
			
			
			//$response->transStat
			//array_push($response->transStat ,array('id'=>$check));
			$response=array_merge($response , ['id'=>$check]);
	

		}else{
			$response = array( 'success' => false  , 'm'=>__('Error Code:V01','easy-form-builder'));		
			
		}
		wp_send_json_success($response, $_POST);
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
				
				
				/* 
				'<h4 id="title_efb" class="efb %s text-center mt-1">%s</h4><p id="desc_efb" class="efb %s text-center  fs-6 efb">%s</p>'
				$first['label_text_color'], $first['name'], $first['message_text_color'] , $first['message'] );
				 */
				
				

				// موارد مربوط به هد و ایکون و غیره اینجا هندل شود
				//error_log(json_encode($item));
				return false;
			}
			/* else if( $item['type']=='option'){
				return false;
			} */
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
						// هدر 
						//$first :  valj_efb[0]
						//head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb  ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb  fs-5 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
						//$this->name .=printf('')
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
		
		/* 
		
		 content += `
           ${sitekye_emsFormBuilder.length > 1 ? `<div class="efb row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
           <!-- fieldset1 --> 
           ${state_efb == "view" && valj_efb[0].captcha == true ? `<div class="efb col-12 mb-2 mt-3 efb" id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>` : ''}
           </fieldset>
          <fieldset data-step="step-${step_no}-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
            ${loading_messge_efb()}                
            <!-- fieldset2 --></fieldset>`


    		
		 */
		//head nveshteh shavad
		/* 
		  head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="efb mb-2 px-2">${head}</ul>` : ''}
			${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="efb progress mx-5"><div class="efb  progress-bar-efb  btn-${RemoveTextOColorEfb(valj_efb[1].label_text_color)} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div> <br> ` : ``}
			`
		
		*/
		//buttons
		$step_no = intval($form_[0]["steps"]) +1;		
		 $this->value .= isset($this->pub_stting->siteKey) && $form_[0]['captcha'] == true ? '<div class="efb row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-2" data-sitekey="'.$this->pub_stting->siteKey .'" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>' : '';
		 $this->value .= '</fieldset>
		 <fieldset data-step="step-'.$step_no.'-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
		  <div class="efb card-body text-center efb"><div class="efb lds-hourglass efb"></div><h3 class="efb">'.__('Waiting','easy-form-builder').'</h3></div>                
		   <!-- final fieldset --></fieldset>';
		 
		   //print_r($this->value);

		   //content = $this->value
		   //head = $this->name
		   //body =  $str 
		   //sprintf($str,$this->value,$this->name,$this->value)
	}

	
/* 	function modify_jquery_login_efb() {
		//this function added jquery vesrion 3.5.24 for multiselect
		if (!is_admin() && $GLOBALS['pagenow']!='wp-login.php') {
			
			wp_deregister_script('jquery');
			wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.3.2/jquery.min.js', false, '3.3.2');
			wp_enqueue_script('jquery');
		}
	
	} */
	public function load_textdomain(): void {
		
        load_plugin_textdomain(
            EMSFB_PLUGIN_TEXTDOMAIN,
            false,
            EMSFB_PLUGIN_DIRECTORY . "/languages"
        );
    }
	
	public function string_to_url($string) {
		
			$rePage= preg_replace('/(http:@efb@)+/','http://',$string);
			$rePage= preg_replace('/(https:@efb@)+/','https://',$rePage);
			$rePage =preg_replace('/(@efb@)+/','/',$rePage);
	
		return $rePage;
	}

	public function corn_email_new_message_recived_Emsfb(){		
		$efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;
		$r= $this->setting!=NULL  && empty($this->setting)!=true ? $this->setting: $this->get_setting_Emsfb('setting');
		if(gettype($r)!="string"){return false;}
		$r = str_replace("\\","",$r);
		$setting =json_decode($r,true);;

		
		
		
		//$secretKey=isset($setting->secretKey) && strlen($setting->secretKey)>5 ?$setting->secretKey:null ;
		$email = isset($setting["emailSupporter"]) ?$setting["emailSupporter"] :null  ;
		$pro = isset($setting["activeCode"]) &&  strlen($setting["activeCode"])>5 ? $setting["activeCode"] :null ;
		$table_msgs = $this->db->prefix . "emsfb_msg_";
		$table_forms = $this->db->prefix . "emsfb_form";
		$value_msgs = $this->db->get_results( "SELECT * FROM `$table_msgs` INNER JOIN $table_forms ON $table_msgs.form_id = $table_forms.form_id   WHERE $table_msgs.read_ = 3" );
		//error_log(json_encode($value_msgs));
		$trackingCode ="";
		$admin_email ="";
		$user_email ="null";
		$fs;
		foreach ($value_msgs as $key => $value) {
				/* error_log(json_encode($value));
				error_log(json_encode($value->track)); */
				$trackingCode = $value->track;
				$fs = str_replace("\\","",$value->form_structer);			
				$msg = str_replace("\\","", $value->content);
				$msg_obj = json_decode($msg,true); //object of message
				$fs_obj = json_decode($fs,true); // object of form_structer
				//$this->id = $trackingCode;
				
			    $this->db->update( $table_msgs, array('read_' =>0), array( 'track' => $trackingCode ) );
				
				
				//error_log(gettype($trackingCode));
				
				
				
				//$this->fun_send_email_noti_efb($fs_obj,$msg_obj, $email,$trackingCode,$pro);
				//error_log(json_encode($user_email));
				
				
		}
	
	}


	public function  fun_send_email_noti_efb($fs_obj,$msg_obj, $email,$trackingCode,$pro,$admin_email,$link){
		//if($fs_obj[0]["sendEmail"]==true || $fs_obj[0]["sendEmail"]=="true"){
			//error_log($admin_email);
			//error_log($email);
			$user_email="null";
			$user_email = array_filter($msg_obj, function($item) use($fs_obj){ 
				if(isset($item['id_']) && $item['id_']==$fs_obj[0]["email_to"]){return $item["value"];}					
			});		
			if($user_email!="null"){
				if( $fs_obj[0]["trackingCode"]==true || $fs_obj[0]["trackingCode"]=="true" || $fs_obj[0]["trackingCode"]==1)
				{	
					
					foreach($user_email as $key => $val){	
						$this->send_email_Emsfb($val['value'],$trackingCode,$pro,"notiToUserFormFilled_TrackingCode",$link);
					}						
				}else{
					foreach($user_email as $key => $val){	
						$this->send_email_Emsfb($val['value'],$trackingCode,$pro,"notiToUserFormFilled",$link);
					}						 
				}
			}
			$link = $link. "?user=admin";
			if(isset($admin_email)==true){
				/* 
				 */
				
				$this->send_email_Emsfb($admin_email,$trackingCode,$pro,"newMessage",$link);
			}
			/* 
			 */
			if($email!=null)$this->send_email_Emsfb($email,$trackingCode,$pro,"newMessage",$link);
		//}
	}

	public function new_user_validate_efb($username,$email,$password){
		if(!is_email($email)){
			return __("The Email Address Is Not Valid" , 'easy-form-builder');
		}
		 if(preg_match('/^[a-z0-9._]*$/',$username)!=true ){
			return __("The Username Must Contain Only Letters, Numbers And Underscores." , 'easy-form-builder');
		}else if(strlen($username)<3){
			return __("The Username Must Contain At Least 3 Characters." , 'easy-form-builder');
		}
		 
		if (strlen($password) <=  8) {
			return __("The Password Must Contain At Least 8 Characters!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[0-9]+#",$password)) {
			return __("The Password Must Contain At Least 1 Number!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[A-Z]+#",$password)) {
			return __("The Password Must Contain At Least 1 Capital Letter!" , 'easy-form-builder');
		}
		elseif(!preg_match("#[a-z]+#",$password)) {
			return  __("The Password Must Contain At Least 1 Lowercase Letter!" , 'easy-form-builder');
		}

		return 0;
	}




	
}

new _Public();