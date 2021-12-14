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
		add_submenu_page( 'Emsfb', __('Create', 'easy-form-builder' ), __('Create', 'easy-form-builder' ), 'Emsfb_create', 'Emsfb_create', array(
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
			<script>		
				let bdy =document.getElementsByTagName('body');
				bdy[0].classList.add("bg-color");
				const sitekye_emsFormBuilder= ""
			</script>
			<div id="alert_efb" class="mx-5"></div>
			<div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="modal-content efb " id="settingModalEfb-sections">
									<div class="modal-header efb"> <h5 class="modal-title efb" ><i class="bi-ui-checks me-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
									<div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb"></h3></div></div>
					</div></div></div>
            <div id="tab_container">
           
        	</div>
		<?php


		$pro =false;
		$maps =false;
		$efbFunction = new efbFunction(); 
		$ac= $efbFunction->get_setting_Emsfb();
		//v2 translate
		$creat=["localizationM","goToEFBAddEmailM","sMTPNotWork","thankYouMessage","stateProvince","conturyList","shortcode","create", "define", "formName","enterYourMessage", "numberSteps", "createDate", "edit", "content", "trackNo", "formDate", "by", "ip", "guest", "info", "response", "date","download" , "videoDownloadLink", "downloadViedo", "youCantUseHTMLTagOrBlank", "error", "reply", "messages", "close", "pleaseWaiting", "loading", "remove", "areYouSureYouWantDeleteItem", "no", "yes", "numberOfSteps", "titleOfStep", "proVersion", "youUseProElements", "getProVersion", "clickHereGetActivateCode", "email", "trackingCode", "save", "pcPreview", "help", "waiting", "saved", "error", "itAppearedStepsEmpty", "previewForm", "activateProVersion", "copyTrackingcode", "copyShortcode", "youDoNotAddAnyInput", "stepName", "IconOfStep", "define", "stepTitles", "elements", "delete", "newOption", "documents", "image", "media", "videoOrAudio", "zip", "required", "button", "text", "password", "emailOrUsername", "number", "file", "dadfile", "date", "tel", "maps", "textarea", "checkbox", "radiobutton", "radio", "select", "multiselect", "switch", "url", "range", "locationPicker", "color", "fileType", "label", "rating", "esign", "htmlCode", "yesNo", "class", "id", "tooltip", "formUpdated", "goodJob", "formUpdatedDone", "formIsBuild", "formCode", "close", "done", "demo", "alert", "pleaseFillInRequiredFields", "availableInProversion", "preview", "somethingWentWrongPleaseRefresh", "formNotCreated", "atFirstCreateForm", "formNotBuilded", "allowMultiselect", "DragAndDropUI", "clickHereForActiveProVesrsion", "someStepsNotDefinedCheck", "ifYouNeedCreateMoreThan2Steps", "youCouldCreateMinOneAndMaxtwo", "youCouldCreateMinOneAndMaxtwenty", "selectOpetionDisabled", "orClickHere", "pleaseEnterTheTracking", "somethingWentWrongTryAgain", "enterThePhone", "pleaseMakeSureAllFields", "enterTheEmail", "formNotFound", "errorV01", "enterValidURL", "password8Chars", "registered", "yourInformationRegistered", "youNotPermissionUploadFile", "pleaseUploadA", "trackingForm", "trackingCodeIsNotValid", "checkedBoxIANotRobot", "step", "contactusForm", "newForm", "registerForm", "loginForm", "login", "thisInputLocked", "subscriptionForm", "supportForm", "createBlankMultistepsForm", "createContactusForm", "createRegistrationForm", "createLoginForm", "createnewsletterForm", "createSupportForm", "availableSoon", "advancedCustomization", "contactUs", "support", "subscribe", "survey", "reservation", "createsurveyForm", "createReservationyForm", "send", "thisElemantAvailableRemoveable", "thisElemantNotAvailable", "thisElemantWouldNotRemoveableLoginform", "firstName", "lastName", "message", "subject", "phone", "register", "username", "proUnlockMsg", "easyFormBuilder", "byWhiteStudioTeam", "allStep", "createForms", "tutorial", "efbIsTheUserSentence", "efbYouDontNeedAnySentence", "please", "fieldAvailableInProversion", "sampleDescription", "editField", "description", "thisEmailNotificationReceive", "activeTrackingCode", "addGooglereCAPTCHAtoForm", "dontShowIconsStepsName", "dontShowProgressBar", "showTheFormTologgedUsers", "labelSize", "default", "small", "large", "xlarge", "xxlarge", "xxxlarge", "labelPostion", "beside", "align", "left", "center", "right", "width", "cSSClasses", "defaultValue", "placeholder", "enterAdminEmailReceiveNoti", "corners", "rounded", "square", "icon", "buttonColor", "blue", "darkBlue", "lightBlue", "grayLight", "grayLighter", "green", "pink", "yellow", "light", "Red", "grayDark", "white", "clr", "borderColor", "height", "latitude", "longitude", "exDot", "pleaseDoNotAddJsCode", "button1Value", "button2Value", "iconList", "previous", "next", "invalidEmail", "noCodeAddedYet", "andAddingHtmlCode", "proMoreStep", "aPIkeyGoogleMapsError", "howToAddGoogleMap", "deletemarkers", "updateUrbrowser", "clear", "star", "stars", "nothingSelected", "duplicate", "availableProVersion", "mobilePreview", "thanksFillingOutform", "finish", "copiedClipboard", "dragAndDropA", "browseFile", "removeTheFile", "offerGoogleCloud", "getOfferTextlink", "SpecialOffer", "trackingCodeFinder", "copyAndPasteBelowShortCodeTrackingCodeFinder", "clearUnnecessaryFiles", "youCanRemoveUnnecessaryFileUploaded", "alertEmail", "whenEasyFormBuilderRecivesNewMessage", "reCAPTCHAv2", "reCAPTCHA", "reCAPTCHASetError", "protectsYourWebsiteFromFraud", "clickHereWatchVideoTutorial", "siteKey", "enterSITEKEY", "SecreTKey", "EnterSECRETKEY", "youNeedAPIgMaps", "aPIKey", "clearFiles", "enterAdminEmail", "emailServer", "beforeUsingYourEmailServers", "clickToCheckEmailServer", "emailSetting","points", "setting","dadFieldHere", "general", "googleKeys", "enterActivateCode", "formSetting", "up", "red", "Red", "field", "advanced", "form", "clickHere", "name", "add", "code", "star", "form", "black", "pleaseReporProblem", "reportProblem", "ddate", "sMTPNotWork", "aPIkeyGoogleMapsFeild", "fileIsNotRight", "lastName", "firstName" ];
		$lang = $efbFunction->text_efb($creat);
		if(gettype($ac)!="string"){
			if (md5($_SERVER['SERVER_NAME'])==$ac->activeCode){
				$pro=true;
			}
			if(	$pro==true){
					wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac->activeCode, null, null, true);	
					wp_enqueue_script('whitestudio-admin-pro-js');
			}

			if(strlen($ac->apiKeyMap)>5){
				$k= $ac->apiKeyMap;
				$maps=true;
				$lng = strval(get_locale());
				//error_log($lng);
					if ( strlen($lng) > 0 ) {
					$lng = explode( '_', $lng )[0];
					}
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
		}
		wp_register_script('addsOnLocal-js', 'https://whitestudio.team/api/plugin/efb/addson/zone.js'.get_locale().'', null, null, true);	
		wp_enqueue_script('addsOnLocal-js');

		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png'
		];
		
		$smtp =false;
		$captcha =false;
		$smtp_m = "";
		if(gettype($ac)!="string"){
			if(strlen($ac->siteKey)>5){$captcha="true";}
			if($ac->smtp!="false"){$smtp=$ac->smtp;}else{$smtp_m =$lang["sMTPNotWork"];}			
		}else{$smtp_m =$lang["goToEFBAddEmailM"];}

		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin.js' );
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
			"language"=> get_locale()

		));
		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));

		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js');
		wp_enqueue_script('efb-main-js'); 

		wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
		wp_enqueue_script('efb-bootstrap-select-js'); 
	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){
		$efbFunction = new efbFunction(); 
		$creat=["errorCheckInputs","NAllowedscriptTag","formNcreated"];
		$lang = $efbFunction->text_efb($creat);
		$this->userId =get_current_user_id();
	//	error_log('get_current_user_id');
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';

		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m =$lang["errorCheckInputs"];
			$response = array( 'success' => false , "m"=>$m); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		
		if(isset($_POST['email']) ){$email =sanitize_email($_POST['email']);}
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;
		$this->value = $_POST['value'];
		//error_log($this->value);
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){			
			$response = array( 'success' => false , "m"=> $lang["NAllowedscriptTag"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		//error_log('$this->insert_db();');
		$this->insert_db();
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_); 
		}else{$response = array( 'success' => false , "m"=> $lang["formNcreated"]);}
		wp_send_json_success($response,$_POST);
		die();		
	}

	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		$table_name = $this->db->prefix . "Emsfb_form";
		$this->db->insert($table_name, array(
			'form_name' => $this->name, 
			'form_structer' => $this->value, 
			'form_email' => $this->email, 
			'form_created_by' => $this->userId, 
			'form_type'=>$this->formtype, 			
		));    $this->id_  = $this->db->insert_id; 
		
	}

}

new Create();