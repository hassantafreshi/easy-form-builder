<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */
require_once('functions.php');

class _Public {
	/**
	 * _Public constructor.
	 */
	
	//public $plugin_url;
	public $value;
	public $id;
	public $ip;
	public $name;
	protected $db;
	//private $wpdb;
	
	public function __construct() {


		global $wpdb;
		$this->db = $wpdb;

		
		//add_action('init',  array($this,'modify_jquery'));
		add_action('wp_enqueue_scripts', array($this,'public_scripts_and_css_head'));
		add_action('wp_ajax_nopriv_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_action('wp_ajax_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_shortcode( 'EMS_Form_Builder',  array( $this, 'EMS_Form_Builder' ) ); 
		
		add_action('wp_enqueue_scripts', array($this,'fun_footer'));
		
		add_action('wp_ajax_nopriv_update_file_Emsfb', array( $this,'file_upload_public'));
		add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));
		
		
		add_shortcode( 'Easy_Form_Builder_confirmation_code_finder',  array( $this, 'EMS_Form_Builder_track' ) ); 
		add_action('wp_ajax_nopriv_get_track_Emsfb', array( $this,'get_ajax_track_public'));
		add_action('wp_ajax_get_track_Emsfb', array( $this,'get_ajax_track_public'));
		
		

		add_action( 'wp_ajax_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		add_action( 'wp_ajax_nopriv_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		
		add_action('init',  array($this, 'hide_toolmenu'));
		
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

	public function EMS_Form_Builder($id){

		
		$table_name = $this->db->prefix . "Emsfb_form";
		
		

		foreach ($id as $row_id){
			//error_log($row_id);
			//$this->value = $this->db->get_var( "SELECT form_structer ,form_type FROM `$table_name` WHERE form_id = '$row_id'" );				
			$this->value = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$row_id'" );
							
		}
		$this->id = $id;
/* 		error_log($this->value[0]->form_structer);
		error_log($this->value[0]->form_type); */
		$lang = get_locale();
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		$state="form";
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			$stng=__("Settings not found",'easy-form-builder');
			$state="form";
			
		}
		
				$typeOfForm =$this->value[0]->form_type;
				//error_log($this->value[0]);
				$value = $this->value[0]->form_structer;
				$poster =  EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg';
				$send=array();
				$efbFunction = new efbFunction();   
				//translate v2
				$showform =["error", "somethingWentWrongTryAgain", "define", "loading", "trackingCode", "pleaseWaiting", "enterThePhone", "please", "pleaseMakeSureAllFields", "enterTheEmail", "formNotFound", "errorV01", "enterValidURL", "password8Chars", "registered", "yourInformationRegistered", "preview", "selectOpetionDisabled", "youNotPermissionUploadFile", "pleaseUploadA", "fileSizeIsTooLarge", "documents", "document", "image", "media", "zip", "trackingForm", "trackingCodeIsNotValid", "checkedBoxIANotRobot", "messages", "pleaseEnterTheTracking", "alert", "pleaseFillInRequiredFields", "enterThePhones", "pleaseWatchTutorial", "somethingWentWrongPleaseRefresh", "formIsNotShown", "errorVerifyingRecaptcha", "orClickHere", "enterThePassword", "PleaseFillForm", "selectOption", "selected", "selectedAllOption", "field", "sentSuccessfully", "thanksFillingOutform", "trackingCode", "sync", "enterTheValueThisField", "thankYou", "login", "logout", "YouSubscribed", "send", "subscribe", "contactUs", "support", "send", "register", "passwordRecovery", "info", "areYouSureYouWantDeleteItem", "noComment", "waitingLoadingRecaptcha", "please", "itAppearedStepsEmpty", "youUseProElements", "fieldAvailableInProversion", "thisEmailNotificationReceive", "activeTrackingCode", "default", "defaultValue", "name", "latitude", "longitude", "previous", "next", "invalidEmail", "aPIkeyGoogleMapsError", "howToAddGoogleMap", "deletemarkers", "updateUrbrowser", "stars", "nothingSelected", "availableProVersion", "thanksFillingOutform", "finish", "select", "up", "red", "Red", "sending", "enterYourMessage", "name", "add", "code", "star", "form", "black", "pleaseReporProblem", "reportProblem", "ddate", "serverEmailAble", "sMTPNotWork", "aPIkeyGoogleMapsFeild","download" , "done", "copyTrackingcode", "copiedClipboard", "browseFile", "dragAndDropA", "fileIsNotRight", "on", "off", "settingsNfound", "lastName", "firstName", "contactusForm", "registerForm"];
				
				$text= $efbFunction->text_efb($showform);
				if (($this->value[0]->form_type=="login" || $this->value[0]->form_type=="register") && is_user_logged_in()){

					$typeOfForm ="userIsLogin";
					$user = wp_get_current_user();
			//		$Value = $value->data;
					$state="userIsLogin";
				//	$poster = get_avatar_url(get_current_user_id());



					$send['state']=true;
					$send['display_name']=$user->data->display_name;
					$send['user_email']=$user->data->user_email;
					$send['user_login']=$user->data->user_login;
					$send['user_nicename']=$user->data->user_nicename;
					$send['user_registered']=$user->data->user_registered;
					$send['user_image']=get_avatar_url(get_current_user_id());
					$value=$send;
				}
		wp_localize_script( 'core_js', 'ajax_object_efm',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'ajax_value' =>$value,
			    'type' => $typeOfForm,
			//   'type' =>'login',
			   'state' => $state,
			   'language' => $lang,
			   'id' => $this->id,			  
			   'form_setting' => $stng,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> $poster,
			   'rtl' => is_rtl(),
			   'text' =>$text 
		 ));  
		 $k="";
		 $pro=false;
		 if(gettype($stng)!="string"){
			 $valstng= json_decode($stng);
			 if($valstng->siteKey){$k =$valstng->siteKey;}
			 if($valstng->activeCode) $pro = true;
		 }
		 $efb_m ="<h6 class='text-center text-pinkEfb efb'>".__('Easy Form Builder', 'easy-form-builder')."</h6> ";
		 if($pro==true) $efb_m="";
	 	$content="<div id='body_efb' class='card card-public pb-3 efb'>
		 <div class='text-center'>
		 <div class='lds-hourglass efb text-center mt-5'></div><h3 class='efb text-center text-darkb fs-5'>".__('Please Waiting','easy-form-builder')."</h2>
		 ".$efb_m."
		 </div>
		 
		 </div><div id='alert_efb' class='mx-5'></div>
		 <script>let sitekye_emsFormBuilder='".$k."'</script>";
		return $content; 
		
		// 
	}


	public function EMS_Form_Builder_track(){
		//Confirmation Code show
		$lang = get_locale();

				$efbFunction = new efbFunction();   
				//translate v2
				//$showform =["error", "somethingWentWrongTryAgain", "define", "loading", "trackingCode", "pleaseWaiting", "enterThePhone", "please", "pleaseMakeSureAllFields", "enterTheEmail", "formNotFound", "errorV01", "enterValidURL", "password8Chars", "registered", "yourInformationRegistered", "preview", "selectOpetionDisabled", "youNotPermissionUploadFile", "pleaseUploadA", "fileSizeIsTooLarge", "documents", "document", "image", "media", "zip", "trackingForm", "trackingCodeIsNotValid", "checkedBoxIANotRobot", "messages", "pleaseEnterTheTracking", "alert", "pleaseFillInRequiredFields", "enterThePhones", "pleaseWatchTutorial", "somethingWentWrongPleaseRefresh", "formIsNotShown", "errorVerifyingRecaptcha", "orClickHere", "enterThePassword", "PleaseFillForm", "selectOption", "selected", "selectedAllOption", "field", "sentSuccessfully", "thanksFillingOutform", "trackingCode", "sync", "enterTheValueThisField", "thankYou", "login", "logout", "YouSubscribed", "send", "subscribe", "contactUs", "support", "send", "register", "passwordRecovery", "info", "areYouSureYouWantDeleteItem", "noComment", "waitingLoadingRecaptcha", "please", "itAppearedStepsEmpty", "youUseProElements", "fieldAvailableInProversion", "thisEmailNotificationReceive", "activeTrackingCode", "default", "defaultValue", "name", "latitude", "longitude", "previous", "next", "invalidEmail", "aPIkeyGoogleMapsError", "howToAddGoogleMap", "deletemarkers", "updateUrbrowser", "stars", "nothingSelected", "availableProVersion", "thanksFillingOutform", "finish", "select", "up", "red", "Red", "sending", "enterYourMessage", "name", "add", "code", "star", "form", "black", "pleaseReporProblem", "reportProblem", "ddate", "serverEmailAble", "sMTPNotWork", "aPIkeyGoogleMapsFeild", "done", "copyTrackingcode", "copiedClipboard", "browseFile", "dragAndDropA", "fileIsNotRight", "on", "off", "settingsNfound", "lastName", "firstName", "contactusForm", "registerForm", ];
				$trackfinder=["trackingCode", "pleaseEnterTheTracking", "alert", "pleaseFillInRequiredFields", "error", "somethingWentWrongTryAgain", "define", "loading", "enterThePhone", "please", "pleaseMakeSureAllFields", "enterTheEmail", "formNotFound", "errorV01", "enterValidURL", "password8Chars", "somethingWentWrongPleaseRefresh", "enterThePhones", "registered", "yourInformationRegistered", "preview", "selectOpetionDisabled", "youNotPermissionUploadFile", "pleaseUploadA", "trackingForm", "trackingCodeIsNotValid", "checkedBoxIANotRobot", "messages", "pleaseWatchTutorial", "formIsNotShown", "errorVerifyingRecaptcha", "orClickHere", "sentSuccessfully", "thanksFillingOutform", "trackingCode", "waitingLoadingRecaptcha", "sync", "please", "entrTrkngNo", "search", "guest", "info", "response", "reply", "ddate", "by", "sending", "enterYourMessage", "finish", "youCantUseHTMLTagOrBlank", "error", "settingsNfound"];
				$text= $efbFunction->text_efb($trackfinder);
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		$state="tracker";
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			$stng=__("Settings not found",'easy-form-builder');
			$state="tracker";
		}
		
		wp_localize_script( 'core_js', 'ajax_object_efm',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'state' => $state,
			   'language' => $lang,			  
			   'form_setting' => $stng,
			   'user_name'=> wp_get_current_user()->display_name,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
			   'rtl' => is_rtl(),
			   'text' =>$text 
		 ));  

	 	$content="<script>let sitekye_emsFormBuilder='' </script><div id='body_tracker_emsFormBuilder'><div><div id='alert_efb' class='mx-5'></div>";
		return $content; 

	}
	function public_scripts_and_css_head(){
	
		$lang = get_locale();
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		
		if(is_rtl()){
			wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl.css', true);
			wp_enqueue_style('Emsfb-css-rtl');
		}

		/* v2 */
		wp_enqueue_script('efb-jquery-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery.min.js');
		wp_enqueue_script('efb-jquery-min-js'); 
		
		wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min.js');
		wp_enqueue_script('efb-bootstrap-min-js'); 

		wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min.js');
		wp_enqueue_script('efb-bootstrap-bundle-min-js'); 
		
		wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
		wp_enqueue_script('efb-bootstrap-select-js'); 

		wp_register_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min.css', true);
		wp_enqueue_style('Emsfb-bootstrap-css');

		wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons.css', true);
		wp_enqueue_style('Emsfb-bootstrap-icons-css');
		
		
		wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style.css', true);
		wp_enqueue_style('Emsfb-style-css');


		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js',array('jquery'), null, true);
		wp_enqueue_script('efb-main-js'); 

		
		wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select.css', true);
		wp_enqueue_style('Emsfb-bootstrap-select-css');




		/* end v2 */

		
		wp_register_script('core_js', plugins_url('../public/assets/js/core.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('core_js');
	
		$params = array(
			'hl' => $lang
		  );
		//change langugae recaptcha
		//https://stackoverflow.com/questions/18859857/setting-recaptcha-in-a-different-language-other-than-english
		
	//	wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload', null , null, true);
		wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&onload=onloadRecaptchakEFB&render=explicit#asyncload', null , null, true);
		wp_enqueue_script('recaptcha');
		

				
		wp_register_script('jquery', plugins_url('../public/assets/js/jquery.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('jquery');

		
	  }




	  public function get_ajax_form_public(){
		error_log('get_ajax_form_public');
		
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>__('Security Error 403', 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		//recaptcha start
		$r= $this->get_setting_Emsfb('setting');
		$pro = false;
		$type =sanitize_text_field($_POST['type']);
		$email=get_option('admin_email');
		$setting;
		$this->id = sanitize_text_field($_POST['id']);
		$table_name = $this->db->prefix . "Emsfb_form";
		$this->value = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$this->id'" );
		$fs =str_replace('\\', '', $this->value[0]->form_structer);
		//error_log($fs);
		$formObj= json_decode($fs,true);
		$email_fa = $formObj[0]["email"];
		$trackingCode = $formObj[0]["trackingCode"];
		$send_email_to_user_state =$formObj[0]["sendEmail"];
		$email_user="null";
		$check;
		
	

		
		if(true){
			$not_captcha=true;
			$captcha_success;
			if(gettype($r)=="object"){
				$setting =json_decode($r->setting);
				$secretKey=$setting->secretKey;
			//	error_log($setting->activeCode);
				if(!empty($setting->activeCode) && md5($_SERVER['SERVER_NAME']) ==$setting->activeCode){
					//error_log('pro == true');
					$pro=true;
				}
				error_log($_POST['valid']);
				$response=$_POST['valid'];
				
				$args = array(
					'secret'        => $secretKey,
					'response'     => $response,
				);
				if($formObj[0]['captcha']==true){
					if(strlen($secretKey)>5){
						$verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
							//error_log(json_encode($verify));
						$captcha_success =json_decode($verify['body']);
						$not_captcha=false;	 
					}else{
						//secretkey is not valid
						$response = array( 'success' => false  , 'm'=>__('Error, Check site Key and secret Key on Easy Form Builder > Panel > Setting > Google Keys' , 'easy-form-builder')); 
					}
				}
			}
			if ($type=="logout" || $type=="recovery") {$not_captcha==true;}

		/* 	error_log($type);
		error_log($captcha_success->succes);
		error_log($not_captcha); */
		if ($not_captcha==false && $captcha_success->success==false  ) {
		  $response = array( 'success' => false  , 'm'=>__('Error, Captcha problem!' , 'easy-form-builder')); 
		  wp_send_json_success($response,$_POST);
		  die();
		}else if ( $not_captcha==true || $captcha_success->success==true) {
		/* 	 error_log('code');
			 error_log($_POST['value']);
			 error_log($_POST['name']);
			 error_log($_POST['id']); */
			if(empty($_POST['value']) || empty($_POST['name']) || empty($_POST['id']) ){
				$response = array( 'success' => false , "m"=>__("Please enter a vaild value" , 'easy-form-builder')); 
				wp_send_json_success($response,$_POST);
				die();
			}
			$this->value = sanitize_text_field($_POST['value']);
			$this->name = sanitize_text_field($_POST['name']);
			$this->id = sanitize_text_field($_POST['id']);
			
			
			$fs =str_replace('\\', '', $this->value);
			$valobj = json_decode($fs , true);
			if($send_email_to_user_state==true || $send_email_to_user_state=="true"){
				foreach($valobj as $key => $val){	
						
						error_log($val["id_"]);
						if ($val["id_"]==$formObj[0]["email_to"]){
							error_log('formObj[$key]["id_"]');
							$email_user=$val["value"];
						}
				} 
			}

		  
		
					switch($type){
						case "form":
						
							$this->get_ip_address();
							$ip = $this->ip;
							$check=	$this->insert_message_db();
							
				
							//$r= $this->get_setting_Emsfb('setting');
							if(!empty($r)){
								//$setting =json_decode($r->setting);								
								if (strlen($setting->emailSupporter)>2){
								//	error_log($setting->emailSupporter);
									$email = $setting->emailSupporter;
								}
								
								$this->send_email_Emsfb($email,$check,$pro,"newMessage");
								if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null"){
									if($trackingCode=="true"||$trackingCode=="true")
									{
										
										
									$this->send_email_Emsfb($email_user,$check,$pro,"notiToUserFormFilled_TrackingCode");
									}else{
									 $this->send_email_Emsfb($email_user,$check,$pro,"notiToUserFormFilled");
									}
								}
							}
		
					
							if(strlen($email_fa)>4){
								error_log($email_fa);
								$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage");
							}
					 
			
							$response = array( 'success' => true  ,'ID'=>$_POST['id'] , 'track'=>$check  , 'ip'=>$ip); 
							wp_send_json_success($response,$_POST);
						break;
						case "register":
							error_log("register");
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
							$registerValues =json_encode($registerValues);
							error_log($registerValues);
							
							$this->value=json_encode($registerValues);
							$creds = array();
							$creds['user_login'] =esc_sql($username);
							$creds['user_pass'] = esc_sql($password);
							$creds['user_email'] = esc_sql($email);
							$creds['role'] = 'subscriber';
							$state =wp_insert_user($creds);
							$response;
							//error_log(json_encode($state));
							$m =__('Your account has been successfully created! You will receive an email containing your information','easy-form-builder');

							// hide password

							/* error_log('print_r($registerValues,1)');
							error_log(print_r($registerValues,1)); */
							//here
							if(gettype($state)=="object"){
								foreach($state->errors as $key => $value){
									$m= $value[0];
								}
								$response = array( 'success' => false , 'm' =>$m); 
							}else{
								//error_log($m);
								if($email!="null"){
								
									$this->get_ip_address();
									//$ip = $this->ip;
									$check=	$this->insert_message_db();
									$state= get_user_by( 'email', $email);
									if(gettype($state)=="object"){

										$to = $email;
									
										
										
										if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null" ){
											$ms ="<p>".  __("username")  .":".$username ." </p> <p>". __("password")  .":".$password."</p>";
											$this->send_email_Emsfb($email_user,$ms,$pro,"register");
									    }
										//$sent = wp_mail($to, $subject, strip_tags($message), $headers);
									}
								}
								$response = array( 'success' => true , 'm' =>$m); 
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
								//user login in successfully
								// return user profile and ....
								//778899
								$userID = $user->ID;
								//error_log(json_encode($user));

								wp_set_current_user( $userID, $creds['user_login'] );
								wp_set_auth_cookie( $userID, true, false );
								do_action( 'wp_login', $creds['user_login'] );

								$send=array();
								$send['state']=true;
								$send['display_name']=$user->data->display_name;
								$send['user_email']=$user->data->user_email;
								$send['user_login']=$user->data->user_login;
								$send['user_nicename']=$user->data->user_nicename;
								$send['user_registered']=$user->data->user_registered;
								$send['user_image']=get_avatar_url($user->data->ID);

								//error_log(json_encode($send));
								$response = array( 'success' => true , 'm' =>$send); 
								wp_send_json_success($response,$_POST);
								
								//error_log(is_user_logged_in());
							}else{
							//	error_log(json_encode($user));

								
								// user not login
								// return to user a message you are not login
								//778899
								$send=array();
								$send['state']=false;
								$send['pro']=$pro;
								$send['error']=__('The username or password is incorrect' , 'easy-form-builder');
								$response = array( 'success' => true , 'm' =>$send); 
								wp_send_json_success($response,$_POST);
							}
							
							
							


						break;
						case "logout":
							//error_log('logout');
							wp_logout();
							$response = array( 'success' => true  );
							wp_send_json_success($response,$_POST);
						break;
						case "recovery":
							//error_log('recovery');
							$m = str_replace("\\","",$this->value);
							$userinfo = json_decode($m,true);
							//email
							$email="null";
							foreach($userinfo as $value){
								//error_log($value);
								if(is_email($value)){
									$email = sanitize_email($value);
									break;
								}
							}

							if($email!="null"){
								
								
								$state= get_user_by( 'email', $email);
								if(gettype($state)=="object"){
									
   								 	$newpass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0,9);									
									error_log($newpass);
									$id =(int) $state->data->ID;
									 wp_set_password($newpass ,$id);
									$to = $email;
									$efb ='<p> '. __("sent by:") . home_url(). '</p>';
									if($pro==false) $efb ='<p> '. __("from").''. home_url(). ' '. __("sent by:" , 'easy-form-builder') .'<b>['. __('Easy Form Builder' , 'easy-form-builder') .']</b></p>' ;
									$subject ="". __("Password recovery")."";
									$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
									$message ='<!DOCTYPE html> <html> <body><h3>'.  __('New Password')  .':'.$newpass.'</h3>
									<p> '.$efb. '</p>
									</body> </html>';
									//error_log($from);
									$headers = array(
									 'MIME-Version: 1.0\r\n',
									 '"Content-Type: text/html; charset=ISO-8859-1\r\n"',
									 'From:'.$from.''
									 );
									$sent = wp_mail($to, $subject, strip_tags($message), $headers);
								}
							}
							$m=__('If your email is correct, the new password will sent to your eamil.', 'easy-form-builder');
							$response = array( 'success' => true , 'm' =>$m); 
							wp_send_json_success($response,$_POST);
						break;
						case "subscribe":
							//error_log('subscribe2');
							$this->get_ip_address();
							//$ip = $this->ip;
							$check=	$this->insert_message_db();
			
							//$r= $this->get_setting_Emsfb('setting');
							if(!empty($r)){
								//$setting =json_decode($r->setting);
								if (strlen($setting->emailSupporter)>2){
								//	error_log($setting->emailSupporter);
									$email = $setting->emailSupporter;
								}													
								if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null" ){
									 $this->send_email_Emsfb($email_user,"",$pro,"subscribe");
								}
							}
							if(strlen($email_fa)>4){
								error_log($email_fa);
								$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage");
							}
			
							$response = array( 'success' => true , 'm' =>__('Done', 'easy-form-builder')); 
							wp_send_json_success($response,$_POST);
						break;
						case "survey":
							$this->get_ip_address();
							//$ip = $this->ip;
							$check=	$this->insert_message_db();
			
							//$r= $this->get_setting_Emsfb('setting');
							if(!empty($r)){
								//$setting =json_decode($r->setting);
								if (strlen($setting->emailSupporter)>2){
								//	error_log($setting->emailSupporter);
									$email = $setting->emailSupporter;
								}
			
								
								if(($send_email_to_user_state==true || $send_email_to_user_state=="true") && $email_user!="null" ){
									$this->send_email_Emsfb($email_user,"",$pro,"survey");
							    }
							}
							if(strlen($email_fa)>4){
								error_log($email_fa);
								$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage");
							}
			
			
							$response = array( 'success' => true , 'm' =>__('survey has been completed', 'easy-form-builder')); 
							wp_send_json_success($response,$_POST);
						break;
						case "reservation":
						break;

						if(strlen($email_fa)>4){
							error_log($email_fa);
							$this->send_email_Emsfb($email_fa,$check,$pro,"newMessage");
						}
						
						default:
						$response = array( 'success' => false  ,'m'=>__('Security Error 405', 'easy-form-builder')); 
						wp_send_json_success($response,$_POST);
					}

		}
		//recaptcha end
	}else{
		$response = array( 'success' => false , "m"=>__("Error,Setting not Found" , 'easy-form-builder')); 
		wp_send_json_success($response,$_POST);
	}



	  }
	  public function get_ajax_track_public(){
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>__('Security Error 403', 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$r= $this->get_setting_Emsfb('setting');
		
		$response=$_POST['valid'];
		$captcha_success =[];
		$not_captcha=true;
		
		if(gettype($r)=="object"){
		 $setting =json_decode($r->setting);
		 $secretKey=$setting->secretKey;
	
		 if(strlen($secretKey)>3){
			 $verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
			 $captcha_success =json_decode($verify['body']);
			 $not_captcha=false;	 
		 }
		}

		 $strR = json_encode($captcha_success);
		 //error_log($strR);	

		 if (!empty($captcha_success) &&$captcha_success->success==false &&  $not_captcha==false ) {
		 // "Error, you are a robot?";
		  $response = array( 'success' => false  , 'm'=>__('Error, Are you a robot?' , 'easy-form-builder')); 
		  wp_send_json_success($response,$_POST);
		 }
		 else if ((!empty($captcha_success) && $captcha_success->success==true) ||  $not_captcha==true) {
		//	 "successful!!";

		if(empty($_POST['value']) ){
			$response = array( 'success' => false , "m"=>__("Please enter vaild values", 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		
			$id = sanitize_text_field($_POST['value']);
		
			$this->get_ip_address();
			
			$table_name = $this->db->prefix . "Emsfb_msg_";
			$value = $this->db->get_results( "SELECT content,msg_id,track FROM `$table_name` WHERE track = '$id'" );	
			if($value!=null){
				$id=$value[0]->msg_id;
				$table_name = $this->db->prefix . "Emsfb_rsp_";
				$content = $this->db->get_results( "SELECT content,rsp_by FROM `$table_name` WHERE msg_id = '$id'" );
				///get_current_user_id
				foreach($content as $key=>$val){
					
					$r = (int)$val->rsp_by;
					if ($r>0){
						$usr =get_user_by('id',$r);
						$val->rsp_by= $usr->display_name;
					}else{
						$val->rsp_by="Guest";
					}				 
				}
				//$ip = $this->ip;
			}
			$r = false;
			if($value!=null){
				$r=true;
				
				$response = array( 'success' => true  , "value" =>$value[0] , "content"=>$content); 
			}else{
				$response = array( 'success' => false  , "m" =>__("Confirmation Code not found!", 'easy-form-builder')); 
			}
		
			wp_send_json_success($response,$_POST);
			}
		//recaptcha end

			
	  }//end function




	  public function fun_footer(){

		
		wp_register_script('jquery', plugins_url('../public/assets/js/jquery.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('jquery');
	
	  }//end function



	public function insert_message_db(){
	
		
		
		$uniqid= date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 5) ;
		$table_name = $this->db->prefix . "Emsfb_msg_";
		$this->db->insert($table_name, array(
			'form_title_x' => $this->name, 
			'content' => $this->value, 
			'form_id' => $this->id, 
			'track' => $uniqid, 
			'ip' => $this->ip, 
			'read_' => 0, 
			
		));    return $uniqid; 
	  		
	}//end function

	public function get_ip_address(){
		//source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {$ip = $_SERVER['REMOTE_ADDR'];}
	 $this->ip = $ip;	 
	 return $ip;
	}//end function


	public function file_upload_public(){
		error_log('file_upload_public');
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>__('Security Error 403')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' ,'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'
		);
		
		if (in_array($_FILES['file']['type'], $arr_ext)) { 
			// تنظیمات امنیتی بعدا اضافه شود که فایل از مسیر کانت که عمومی هست جابجا شود به مسیر دیگری
			//error_log($_FILES["file"]["name"]);			
			$name = 'efb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION) ;
			//error_log($name);
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["file"]["tmp_name"]));
			//$upload = wp_upload_bits($_FILES["file"]["name"], null, file_get_contents($_FILES["file"]["tmp_name"]));
			//$upload['url'] will gives you uploaded file path
			//error_log($upload);	
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$_FILES['file']['type']); 
			  wp_send_json_success($response,$_POST);
		}else{
			$response = array( 'success' => false  ,'error'=>__("File Permissions Error", 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die('invalid file '.$_FILES['file']['type']);
		}
		
		 
	}//end function

	public function set_rMessage_id_Emsfb(){


		// این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند 
		// با این مضنون که پاسخ شما داده شده است
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>__('Security Error 403' , 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}

		
		if(empty($_POST['message']) ){
			$response = array( 'success' => false , "m"=>__("Please enter a vaild value", 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		if(empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>__("Something went wrong ,Please refresh and try again", 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}
		

		if($this->isHTML($_POST['message'])){
			$response = array( 'success' => false , "m"=> __("You are not allowed use HTML tag", 'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}

		$r= $this->get_setting_Emsfb('setting');
		if(gettype($r)=="object"){
			$setting =json_decode($r->setting);
			$secretKey=$setting->secretKey;
			$email =$setting->emailSupporter ;
			$pro = $setting->activeCode;
			//error_log($email);
			$response=$_POST['valid'];
			$id;
				$id=number_format(sanitize_text_field($_POST['id']));
				$m=sanitize_text_field($_POST['message']);
			
				
				//	$message = preg_replace('/\s+/', '', $m);
				$m = str_replace("\\","",$m);	
				$message =json_decode($m);
				$table_name = $this->db->prefix . "Emsfb_rsp_";
				//echo $table_name;
			
				//	$m = json_encode($m);
				$ip =$this->get_ip_address();
				//error_log($ip);
				//error_log(get_current_user_id());
				$this->db->insert($table_name, array(
					'ip' => $ip, 
					'content' => $m, 
					'msg_id' => $id, 
					'rsp_by' => get_current_user_id(), 
					'read_' => 0
					
				));  


			
	


			
				//error_log($id);
				$this->db->update($table_name,array('read_'=>0),array('msg_id' => $id) );

				$by=__("Guest" , 'easy-form-builder');

				error_log(json_encode(wp_get_current_user()));
				
				if(get_current_user_id()!=0 && get_current_user_id()!==-1){
					$usr= wp_get_current_user();
					$by = $usr->user_nicename;
					error_log($by);
				}
				$table_name = $this->db->prefix . "Emsfb_msg_";
				$value = $this->db->get_results( "SELECT track,form_id FROM `$table_name` WHERE msg_id = '$id'" );
				$form_id  = $value[0]->form_id;
				$table_name = $this->db->prefix . "Emsfb_form";
				$vald = $this->db->get_results( "SELECT form_structer ,form_type   FROM `$table_name` WHERE form_id = '$form_id'" );
				$valn =str_replace('\\', '', $vald[0]->form_structer);
				$valn= json_decode($valn,true);
				$usr;
				$email_fa = $valn[0]["email"];

				if (strlen($setting->emailSupporter)>0){
				//	error_log($setting->emailSupporter);
					$email = $setting->emailSupporter;
				}
				
				if($email!= null  && gettype($email)=="string") {$this->send_email_Emsfb($email,$value[0]->track,$pro,"newMessage");}

				error_log($email_fa);
				if(strlen($email_fa)>4){
					
					$this->send_email_Emsfb($email_fa,$value[0]->track,$pro,"newMessage");
				}
				$response = array( 'success' => true , "m"=>__("Message was sent" , 'easy-form-builder') , "by"=>$by); 
				wp_send_json_success($response,$_POST);							
			
		}

	}//end function

	public function send_email_Emsfb($to , $track ,$pro , $state){
  
   $cont = $track;
   $subject ="📮 ".__('You have Recived New Message', 'easy-form-builder');
   if($state=="notiToUserFormFilled_TrackingCode"){
	$subject =__('We Recived your Message', 'easy-form-builder');
	$message ="<h2>".__('Thank You for filling out the form', 'easy-form-builder')."</h2>
			<p>". __('Confirmation Code' , 'easy-form-builder').": ".$cont." </p>
			<button><a href='".home_url()."' style='color: white;'>".get_bloginfo('name')."</a></button>
			";
	$cont=$message;
   }elseif($state=="notiToUserFormFilled"){
	$subject =__('We Recived your Message.', 'easy-form-builder');
	$message ="<h2>".__('Thank You for filling out the form', 'easy-form-builder')."</h2>
	<button><a href='".home_url()."' style='color: white;'>".get_bloginfo('name')."</a></button>
	";
	$cont=$message;
   }elseif ($state=="register"){
	$subject =__('Thank You for registering.', 'easy-form-builder');   
	$message ="<h2>".__('Welecome', 'easy-form-builder')."</h2>
	".$cont."
	<button><a href='".home_url()."' style='color: white;'>".get_bloginfo('name')."</a></button>
	";
	$cont=$message;
   }elseif ($state=="subscribe"){
	$subject =__('welecome', 'easy-form-builder');   
	$message ="<h2>".__('Thank You For Subscribing!', 'easy-form-builder')."</h2>
	<button><a href='".home_url()."' style='color: white;'>".get_bloginfo('name')."</a></button>
	";
	$cont=$message;
   }elseif ($state=="survey"){
	$subject =__('welecome', 'easy-form-builder');   
	$message ="<h2>".__('Thank You for taking the time to complete this survey.', 'easy-form-builder')."</h2>
	<button><a href='".home_url()."' style='color: white;'>".get_bloginfo('name')."</a></button>
	";
	$cont=$message;
   }
/*    error_log("state");
   error_log($state);
   error_log($cont); */
   $efbFunction = new efbFunction();   
   $check =  $efbFunction->send_email_state( $to,$subject ,$cont,$pro,$state);

	}

	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function get_setting_Emsfb($state)
	{
	// تنظیمات  برای عموم بر می گرداند
	 
	
	 
	 $table_name = $this->db->prefix . "Emsfb_setting";
 
 
	 $value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
 	// error_log(gettype($value));
	$rtrn;
	$siteKey;
	$trackingCode ="";

	
	if(count($value)>0){
		//error_log('count($value)>0');
		foreach($value[0] as $key=>$val){
		   
		   $r =json_decode($val);
		   $i=0;
   
		   foreach($r as $k=>$v){	
   
			   if($k=="siteKey" && $state=="pub"){				
				   $siteKey=$v;
			   }elseif ($k=="trackingCode" && $state=="pub"){
				   $trackingCode=$v;
			   }
		   }
		   
		} 
   
	   if($state=="pub"){
			
		   $rtr =	array('trackingCode' => ''.$trackingCode.'' , 'siteKey' => ''.$siteKey.'');
		   $rtrn =json_encode($rtr);
	   }else{
		   $rtrn=$value[0];
	   }
	}else{
		$rtrn=0;
	}
	//error_log($rtrn);
	 //return $value[0];
	 return $rtrn;
	}


	
	function modify_jquery() {
		//this function added jquery vesrion 3.5.1 for multiselect
/* 		if (!is_admin() && $GLOBALS['pagenow']!='wp-login.php') {
			wp_deregister_script('jquery');
			wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', false, '3.5.1');
			wp_enqueue_script('jquery');
		} */
	
	}
	public function load_textdomain(): void {
		//error_log('load_textdomain');
        load_plugin_textdomain(
            EMSFB_PLUGIN_TEXTDOMAIN,
            false,
            EMSFB_PLUGIN_DIRECTORY . "/languages"
        );
    }
}

new _Public();