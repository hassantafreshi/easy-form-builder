<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class Panel_edit  {

	public $nounce;
	protected $db;
	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		
		if ( is_admin() ) {
			$rtl = is_rtl();

			wp_register_script('gchart-js', 'https://www.gstatic.com/charts/loader.js', null, null, true);	
			wp_enqueue_script('gchart-js');
			$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
			"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
			"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/recaptcha.png'
			];
			$pro =false;
			$efbFunction = new efbFunction(); 
			//$lng =new lng();
			$panel=["iUsebootTemp","bootStrapTemp", "iUsebootTempW","localization","translateLocal","message","thankYouMessage","stateProvince","conturyList","shortcode","create", "define","dadFieldHere","subject", "formName","beside", "createDate", "edit", "points","content", "trackNo", "formDate", "by", "ip", "guest", "info", "response", "date", "videoDownloadLink", "download" ,"downloadViedo", "youCantUseHTMLTagOrBlank", "error", "reply", "messages", "close", "pleaseWaiting", "loading", "remove", "areYouSureYouWantDeleteItem", "no", "yes", "numberOfSteps", "easyFormBuilder", "titleOfStep", "proVersion", "getProVersion", "clickHereGetActivateCode", "enterActivateCode", "reCAPTCHAv2", "reCAPTCHA", "reCAPTCHASetError", "protectsYourWebsiteFromFraud", "clickHereWatchVideoTutorial", "siteKey", "enterSITEKEY", "SecreTKey", "EnterSECRETKEY", "alertEmail", "whenEasyFormBuilderRecivesNewMessage", "email", "enterAdminEmail", "clearFiles", "youCanRemoveUnnecessaryFileUploaded","clear", "clearUnnecessaryFiles", "trackingCode", "ifShowTrackingCodeToUser", "showTrackingCode", "trackingCodeFinder", "copyAndPasteBelowShortCodeTrackingCodeFinder", "save", "waiting", "saved", "error", "stepName", "IconOfStep", "stepTitles", "elements", "delete", "newOption", "documents", "image", "media", "videoOrAudio", "zip", "required", "button", "text", "password", "email", "number", "file", "date", "tel", "textarea", "checkbox", "radiobutton", "radio", "multiselect", "url", "range", "color", "fileType", "label", "class", "id", "tooltip", "formUpdated", "goodJob", "formUpdatedDone", "formIsBuild", "formCode", "close", "done", "demo", "alert", "pleaseFillInRequiredFields", "availableInProversion", "formNotBuilded", "someStepsNotDefinedCheck", "ifYouNeedCreateMoreThan2Steps", "youCouldCreateMinOneAndMaxtwo", "youCouldCreateMinOneAndMaxtwenty", "preview", "somethingWentWrongPleaseRefresh", "formNotCreated", "atFirstCreateForm", "formNotBuilded", "allowMultiselect", "DragAndDropUI", "clickHereForActiveProVesrsion", "someStepsNotDefinedCheck", "ifYouNeedCreateMoreThan2Steps", "youCouldCreateMinOneAndMaxtwo", "youCouldCreateMinOneAndMaxtwenty", "selectOpetionDisabled", "pleaseEnterTheTracking", "somethingWentWrongTryAgain", "enterThePhone", "pleaseMakeSureAllFields", "enterTheEmail", "formNotFound", "errorV01", "enterValidURL", "password8Chars", "registered", "yourInformationRegistered", "youNotPermissionUploadFile", "pleaseUploadA", "please", "trackingForm", "trackingCodeIsNotValid", "checkedBoxIANotRobot", "howConfigureEFB", "howGetGooglereCAPTCHA", "howActivateAlertEmail", "howCreateAddForm", "howActivateTracking", "howWorkWithPanels", "howAddTrackingForm", "howFindResponse", "pleaseEnterVaildValue", "step", "advancedCustomization", "orClickHere", "downloadCSVFile", "downloadCSVFileSub", "login", "thisInputLocked", "thisElemantAvailableRemoveable", "thisElemantWouldNotRemoveableLoginform", "send", "contactUs", "support", "subscribe", "login", "logout", "survey", "chart", "noComment", "easyFormBuilder", "byWhiteStudioTeam", "createForms", "tutorial", "forms", "tobeginSentence", "efbIsTheUserSentence", "efbYouDontNeedAnySentence", "please", "newResponse", "read", "copy", "general", "help", "setting", "maps", "youCanFindTutorial", "proUnlockMsg", "aPIKey", "youNeedAPIgMaps", "copiedClipboard", "noResponse", "offerGoogleCloud", "getOfferTextlink", "clickHere", "SpecialOffer", "googleKeys", "youNeedAPIgMaps", "emailServer", "beforeUsingYourEmailServers", "emailSetting", "clickToCheckEmailServer", "dadfile", "field", "advanced", "switch", "locationPicker", "rating", "esign", "yesNo", "htmlCode", "pcPreview", "youDoNotAddAnyInput", "copyShortcode", "copyTrackingcode", "previewForm", "activateProVersion", "itAppearedStepsEmpty", "youUseProElements", "sampleDescription", "fieldAvailableInProversion", "editField", "description", "thisEmailNotificationReceive", "activeTrackingCode", "addGooglereCAPTCHAtoForm", "dontShowIconsStepsName", "dontShowProgressBar", "showTheFormTologgedUsers", "labelSize", "default", "small", "large", "xlarge", "xxlarge", "xxxlarge", "labelPostion", "align", "left", "center", "right", "width", "cSSClasses", "defaultValue", "placeholder", "enterAdminEmailReceiveNoti", "corners", "rounded", "square", "icon", "buttonColor", "blue", "darkBlue", "lightBlue", "grayLight", "grayLighter", "green", "pink", "yellow", "light", "Red", "grayDark", "white", "clr", "borderColor", "height", "name", "latitude", "longitude", "exDot", "pleaseDoNotAddJsCode", "button1Value", "button2Value", "iconList", "previous", "next", "invalidEmail", "noCodeAddedYet", "andAddingHtmlCode", "proMoreStep", "aPIkeyGoogleMapsError", "howToAddGoogleMap", "deletemarkers", "updateUrbrowser", "stars", "nothingSelected", "duplicate", "availableProVersion", "mobilePreview", "thanksFillingOutform", "finish", "dragAndDropA", "browseFile", "removeTheFile", "enterAPIKey", "formSetting", "select", "up", "red", "Red", "sending", "enterYourMessage", "name", "add", "code", "star", "form", "black", "pleaseReporProblem", "reportProblem", "ddate", "serverEmailAble", "sMTPNotWork", "aPIkeyGoogleMapsFeild", "fileIsNotRight", "thisElemantNotAvailable","lastName","firstName","registerForm","contactusForm" ];
			$ac= $efbFunction->get_setting_Emsfb();
			$lang = $efbFunction->text_efb($panel);
			/* error_log(gettype($ac));
			error_log($ac->activeCode); */
			$smtp =false;
			$captcha =false;
			$maps=false;
			if(gettype($ac)!="string"){
				if (md5($_SERVER['SERVER_NAME'])==$ac->activeCode){$pro=true;}
				if(strlen($ac->siteKey)>5){$captcha="true";}	
				if($ac->smtp!="false"){$smtp=$ac->smtp;}else{
					//sMTPNotWork s78
					$smtp_m =__('your host can not send emails because Easy form Builder can not connect to the Email server. contact to your Host support','easy-form-builder');
				}	
				if(strlen($ac->apiKeyMap)>5){				
					$k= $ac->apiKeyMap;
					$maps =true;
					$lng = strval(get_locale());					
						if ( strlen($lng) > 0 ) {
						$lng = explode( '_', $lng )[0];
						}
					wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
					wp_enqueue_script('googleMaps-js');
				}
			}else{
				//goToEFBAddEmailM s78
				$smtp_m = __('Please go to Easy Form Builder panel > setting > Email Settings  and Click on "Click To Check Email Server','easy-form-builder');
			}	
			
			
			wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin.js' );
			wp_localize_script('Emsfb-admin-js','efb_var',array(
				'nonce'=> wp_create_nonce("admin-nonce"),
				'pro' => $pro,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang,
				'images' => $img,
				'captcha'=>$captcha,
				'smtp'=>$smtp,
				'maps'=> $maps		));

		
			if($pro==true){
				// اگر پولی بود این کد لود شود 
				//پایان کد نسخه پرو
				wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac->activeCode, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');
			}

			if(gettype($ac)!="string" && $ac->apiKeyMap){
				$k= $ac->apiKeyMap;
				$lang = get_locale();
					if ( strlen( $lang ) > 0 ) {
					$lang = explode( '_', $lang )[0];
					}
				//error_log($lang);
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.';language='.$lang.'libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
			
			 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
						));
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
			wp_enqueue_script('efb-bootstrap-select-js'); 

			wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js');
			wp_enqueue_script('efb-main-js'); 

			wp_register_script('addsOnLocal-js', 'https://whitestudio.team/api/plugin/efb/addson/zone.js'.get_locale().'', null, null, true);	
			wp_enqueue_script('addsOnLocal-js');

			
			$table_name = $this->db->prefix . "Emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
		
			$table_name = $this->db->prefix . "Emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
			

			$lang = get_locale();
			$k ="";
			if(gettype($ac)!="string" && $ac->siteKey)$k= $ac->siteKey;	
			if ( strlen( $lang ) > 0 ) {
				$lang = explode( '_', $lang )[0];
				}

		
			?>
			<div id="body_emsFormBuilder" class="m-2"> 
			
				<div id="msg_emsFormBuilder" class="mx-2">

				
				</div>
				
			<!-- new nav 
			setting s78
			forms s78
			create s78
			help s78
			entrTrkngNo s78
			search s78
			loading s78
		 -->
			<div class="top_circle-efb-2"></div>
			<div class="top_circle-efb-1"></div>
			<script>let sitekye_emsFormBuilder="<?php echo $k;  ?>" </script>
				<nav class="navbar navbar-expand-lg navbar-light efb" id="navbar">
					<div class="container">
						<a class="navbar-brand efb" href="admin.php?page=Emsfb_create" >
							<img src="<?php echo EMSFB_PLUGIN_URL.'/includes/admin/assets/image/logo-easy-form-builder.svg' ?>" class="logo efb">
							Easy Form Builder</a>
						<button class="navbar-toggler efb" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
							<span class="navbar-toggler-icon efb"></span>
						</button>
						<div class="collapse navbar-collapse" id="navbarSupportedContent">
							<ul class="navbar-nav me-auto mb-2 mb-lg-0">
								<li class="nav-item"><a class="nav-link efb active" aria-current="page" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button"><?php _e('Forms','easy-form-builder') ?></a></li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><?php _e('Setting','easy-form-builder') ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" href="admin.php?page=Emsfb_create" role="button"><?php _e('Create','easy-form-builder') ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('help')" role="button"><?php _e('Help','easy-form-builder') ?></a>
								</li>
							</ul>
							<div class="d-flex">
								<form class="d-flex">
									<i class="efb bi-search search-icon"></i>
									<input class="form-control efb search-form-control efb-rounded efb me-2" type="search" type="search" id="track_code_emsFormBuilder" placeholder="<?php _e('Enter the Confirmation Code','easy-form-builder') ?>">
									<button class="btn efb btn-outline-pink me-2" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()"><?php _e('Search','easy-form-builder') ?></button>
								</form>
								<div class="nav-icon efb me-2">
									<a class="nav-link efb" href="https://whitestudio.team/login" target="blank"><i class="efb bi-person"></i></a>
								</div>
								<div class="nav-icon efb">
									<a class="nav-link efb"  onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><i class="efb bi-gear"></i></a>
								</div>
							</div>
						</div>
					</div>
				</nav>
				<div id="alert_efb" class="mx-5"></div>
				<!-- end  new nav  -->
					<div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="modal-content efb " id="settingModalEfb-sections">
									<div class="modal-header efb"> <h5 class="modal-title efb" ><i class="bi-ui-checks me-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
									<div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb"></h3></div></div>
					</div></div></div>

					<div class="row mb-2">					
					<button type="button" class="btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="fa fa-home"></i></button>
					</div>
					<div class="row" id ="content-efb">
				 	<div class="card-body text-center my-5"><div class="lds-hourglass"></div> <h3 class="efb"><?php _e('Loading','easy-form-builder') ?></h3></div>
					<!--  <h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i><?php _e('Loading','easy-form-builder') ?></h2> -->
					</div>
					<div class="mt-3 d-flex justify-content-center align-items-center ">
					<button type="button" id="more_emsFormBuilder" class="efb btn btn-delete btn-sm" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="bi-chevron-double-down"></i></button>
					</div></div>
			<?php
		
			$ip =0;
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				//check ip from share internet
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				//to check ip is pass from proxy
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}



			wp_register_script('Emsfb-list_form-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/list_form.js', null, null, true);
			wp_enqueue_script('Emsfb-list_form-js');
			wp_localize_script( 'Emsfb-list_form-js', 'ajax_object_efm',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
					'ajax_value' => $value,
					'language' => $lang,
					'ajax_nonce'=>$this->nounce,
					'user_name'=> wp_get_current_user()->display_name,
					'user_ip'=> $ip,
					'setting'=>$stng,
					'messages_state' =>$this->get_not_read_message(),
					'response_state' =>$this->get_not_read_response(),
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
					'pro'=>$pro
					
				));
					
		}else{
			echo "Easy Form Builder: You dont access this section";
		}
	}

	
	public function get_not_read_message(){
		//error_log('get_not_read_message');
		
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0" );

		//error_log(json_encode($value));
		return $value;
	}
	public function get_not_read_response(){
		$table_name_msg = $this->db->prefix . "Emsfb_msg_";
		$table_name_rsp = $this->db->prefix . "Emsfb_rsp_"; 
		//$table_name = $this->db->prefix . "Emsfb_rsp_"; 
		$value = $this->db->get_results( "SELECT t.msg_id, t.form_id
		FROM `$table_name_msg` AS t 
		 INNER JOIN `$table_name_rsp` AS tr 
		 ON t.msg_id = tr.msg_id AND tr.read_ = 0" );
		return $value;
	}




}