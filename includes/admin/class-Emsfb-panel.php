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
			$lang = [
				"create" => __('Create','easy-form-builder'),
				"define" => __('Define','easy-form-builder'),
				"formName" => __('Form Name','easy-form-builder'),
				"createDate" => __('Create Date','easy-form-builder'),
				"edit" => __('Edit','easy-form-builder'),
				"content" => __('Content','easy-form-builder'),
				"trackNo" => __('Track No.','easy-form-builder'),
				"formDate" => __('Form Date','easy-form-builder'),
				"by" => __('By','easy-form-builder'),
				"ip" => __('IP','easy-form-builder'),
				"guest" => __('Guest','easy-form-builder'),
				"info" => __('Info','easy-form-builder'),
				"response" => __('Response','easy-form-builder'),
				"date" => __('Date','easy-form-builder'),
				"videoDownloadLink" => __('Video Download Link','easy-form-builder'),
				"downloadViedo" => __('Download Viedo','easy-form-builder'),
				"youCantUseHTMLTagOrBlank" => __('You can not use HTML Tag or send blank message.','easy-form-builder'),
				"error" => __('Error,','easy-form-builder'),
				"reply" => __('Reply','easy-form-builder'),
				"messages" => __('Messages','easy-form-builder'),
				"close" => __('Close','easy-form-builder'),
				"pleaseWaiting" => __('Please Waiting','easy-form-builder'),
				"loading" => __('Loading','easy-form-builder'),
				"remove" => __('Remove!','easy-form-builder'),
				"areYouSureYouWantDeleteItem" => __('Are you sure want to delete this item?','easy-form-builder'),
				"no" => __('NO','easy-form-builder'),
				"yes" => __('Yes','easy-form-builder'),
				"numberOfSteps" => __('Number of steps','easy-form-builder'),
				"easyFormBuilder" => __('Easy Form Builder','easy-form-builder'),
				"titleOfStep" => __('Title of step','easy-form-builder'),
				"proVersion" => __('Pro Version','easy-form-builder'),
				"getProVersion" => __('Get Pro version','easy-form-builder'),
				"clickHereGetActivateCode" => __('Click here to get Activate Code.','easy-form-builder'),
				"enterActivateCode" => __('Enter Activate Code','easy-form-builder'),
				"reCAPTCHAv2" => __('reCAPTCHA v2','easy-form-builder'),
				"reCAPTCHA" => __('reCAPTCHA','easy-form-builder'),
				"protectsYourWebsiteFromFraud" => __('protects your website from fraud and abuse.','easy-form-builder'),
				"clickHereWatchVideoTutorial" => __('Click here to watch a video tutorial.','easy-form-builder'),
				"siteKey" => __('SITE KEY','easy-form-builder'),
				"enterSITEKEY" => __('Enter SITE KEY','easy-form-builder'),
				"SecreTKey" => __('SECRET KEY','easy-form-builder'),
				"EnterSECRETKEY" => __('Enter SECRET KEY','easy-form-builder'),
				"alertEmail" => __('Alert Email','easy-form-builder'),
				"whenEasyFormBuilderRecivesNewMessage" => __('When Easy Form Builder recives a new message, It will send an alret email to admin of plugin.','easy-form-builder'),
				"email" => __('Email','easy-form-builder'),
				"enterAdminEmail" => __('Enter Admin Email','easy-form-builder'),
				"clearFiles" => __('Clear Files','easy-form-builder'),
				"youCanRemoveUnnecessaryFileUploaded" => __('You can Remove unnecessary file uploaded by user with below button','easy-form-builder'),
				"clearUnnecessaryFiles" => __('Clear unnecessary files','easy-form-builder'),
				"trackingCode" => __('Tracking code','easy-form-builder'),
				"ifShowTrackingCodeToUser" => __("If you don't want to show tracking code to user, don't mark below option.",'easy-form-builder'),
				"showTrackingCode" => __('Show tracking Code','easy-form-builder'),
				"trackingCodeFinder" => __('Tracking code Finder','easy-form-builder'),
				"copyAndPasteBelowShortCodeTrackingCodeFinder" => __('Copy and Paste below short-code of tracking code finder in any page or post.','easy-form-builder'),
				"save" => __('Save','easy-form-builder'),
				"waiting" => __('Waiting','easy-form-builder'),
				"saved" => __('Saved','easy-form-builder'),
				"error" => __('Error,','easy-form-builder'),
				"stepName" => __('Step Name','easy-form-builder'),
				"IconOfStep" => __('Icon of step','easy-form-builder'),
				"define" => __('Define','easy-form-builder'),
				"stepTitles" => __('Step Titles','easy-form-builder'),
				"elements" => __('Elements:','easy-form-builder'),
				"delete" => __('Delete','easy-form-builder'),
				"newOption" => __('New option','easy-form-builder'),
				"documents" => __('Documents','easy-form-builder'),
				"image" => __('Image','easy-form-builder'),
				"media" => __('Media','easy-form-builder'),
				"videoOrAudio" => __('(Video or Audio)','easy-form-builder'),
				"zip" => __('Zip','easy-form-builder'),
				"required" => __('Required','easy-form-builder'),
				"button" => __('button','easy-form-builder'),
				"text" => __('text','easy-form-builder'),
				"password" => __('password','easy-form-builder'),
				"email" => __('email','easy-form-builder'),
				"number" => __('number','easy-form-builder'),
				"file" => __('file','easy-form-builder'),
				"date" => __('date','easy-form-builder'),
				"tel" => __('tel','easy-form-builder'),
				"textarea" => __('textarea','easy-form-builder'),
				"checkbox" => __('checkbox','easy-form-builder'),
				"radiobutton" => __('radiobutton','easy-form-builder'),
				"multiselect" => __('multiselect','easy-form-builder'),
				"url" => __('url','easy-form-builder'),
				"range" => __('range','easy-form-builder'),
				"color" => __('color','easy-form-builder'),
				"fileType" => __('File Type','easy-form-builder'),
				"label" => __('Label:*','easy-form-builder'),
				"class" => __('Class','easy-form-builder'),
				"id" => __('ID','easy-form-builder'),
				"tooltip" => __('Tooltip','easy-form-builder'),
				"formUpdated" => __('The Form Updated','easy-form-builder'),
				"goodJob" => __('Good Job','easy-form-builder'),
				"formUpdatedDone" => __('The form has been successfully updated','easy-form-builder'),
				"formIsBuild" => __('The form is successfully built','easy-form-builder'),
				"formCode" => __('Form Code','easy-form-builder'),
				"close" => __('Close','easy-form-builder'),
				"done" => __('Done','easy-form-builder'),
				"demo" => __('Demo','easy-form-builder'),
				"alert" => __('Alert!','easy-form-builder'),
				"pleaseFillInRequiredFields" => __('Please fill in all required fields.','easy-form-builder'),
				"availableInProversion" => __('This option is available in Pro version','easy-form-builder'),
				"formNotBuilded" => __('The form has not been builded!','easy-form-builder'),
				"someStepsNotDefinedCheck" => __('Some steps not defined, Please check:','easy-form-builder'),
				"ifYouNeedCreateMoreThan2Steps" => __('If you need create more than 2 Steps, activeate ','easy-form-builder'),
				"youCouldCreateMinOneAndMaxtwo" => __('You can create minmum 1 and maximum 2 Steps.','easy-form-builder'),
				"youCouldCreateMinOneAndMaxtwenty" => __('You Could create minmum 1 Step and maximum 20 Step','easy-form-builder'),
				"preview" => __('Preview','easy-form-builder'),
				"somethingWentWrongPleaseRefresh" => __('Something went wrong, Please refresh and try again','easy-form-builder'),
				"formNotCreated" => __('The form has not been created!','easy-form-builder'),
				"atFirstCreateForm" => __('At first create a form and add elemants then try again','easy-form-builder'),
				"formNotBuilded" => __('The form has not been builded!','easy-form-builder'),
				"allowMultiselect" => __('Allow multi-select','easy-form-builder'),
				"DragAndDropUI" => __('Drag and drop UI','easy-form-builder'),
				"clickHereForActiveProVesrsion" => __('Click here for Active Pro vesrsion','easy-form-builder'),
				"someStepsNotDefinedCheck" => __('Some steps not defined, Please check:','easy-form-builder'),
				"ifYouNeedCreateMoreThan2Steps" => __('If you need create more than 2 Steps, activeate ','easy-form-builder'),
				"youCouldCreateMinOneAndMaxtwo" => __('You can create minmum 1 and maximum 2 Steps.','easy-form-builder'),
				"youCouldCreateMinOneAndMaxtwenty" => __('You Could create minmum 1 Step and maximum 20 Step','easy-form-builder'),
				"selectOpetionDisabled" => __('Select a option (Disabled in test view)','easy-form-builder'),
				"DragAndDropA" => __('Drag and drop a','easy-form-builder'),
				"pleaseEnterTheTracking" => __('Please enter the tracking code','easy-form-builder'),
				"somethingWentWrongTryAgain" => __('Something went wrong, Please refresh and try again','easy-form-builder'),			
				"enterThePhone" => __('Please Enter the phone number','easy-form-builder'),			
				"pleaseMakeSureAllFields" => __('Please make sure all fields are filled in correctly.','easy-form-builder'),
				"enterTheEmail" => __('Please Enter the Email address','easy-form-builder'),
				"formNotFound" => __('Form is not found','easy-form-builder'),
				"errorV01" => __('Error Code:V01','easy-form-builder'),
				"enterٰValidURL" => __('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
				"password8Chars" => __('Password must be at least 8 characters','easy-form-builder'),
				"registered" => __('Registered','easy-form-builder'),
				"yourInformationRegistered" => __('Your information is successfully registered','easy-form-builder'),		
				"youNotPermissionUploadFile" => __('You do not have permission to upload this file:','easy-form-builder'),
				"pleaseUploadA" => __('Please upload a','easy-form-builder'),
				"please" => __('Please','easy-form-builder'),
				"trackingForm" => __('Tracking Form','easy-form-builder'),
				"trackingCodeIsNotValid" => __('Tracking Code is not valid.','easy-form-builder'),
				"checkedBoxIANotRobot" => __('Please Checked Box of I am Not robot','easy-form-builder'),
				"howConfigureEFB" => __('How to configure Easy Form Builder','easy-form-builder'),
				"howGetGooglereCAPTCHA" => __('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
				"howActivateAlertEmail" => __('How to activate the alert email for new form submission','easy-form-builder'),
				"howCreateAddForm" => __('How to create and add a form with Easy Form Builder','easy-form-builder'),
				"howActivateTracking" => __('How to activate a tracking code in Easy Form Builder','easy-form-builder'),
				"howWorkWithPanels" => __('How to work with panels in Easy Form Builder','easy-form-builder'),
				"howAddTrackingForm" => __('How to add a tracking form to a post, page, or custom post type','easy-form-builder'),
				"howFindResponse" => __('How to find a response through a tracking ID','easy-form-builder'),
				"pleaseEnterVaildValue" => __('Please enter a vaild value','easy-form-builder'),
				"step" => __('step','easy-form-builder'),
				"advancedCustomization" => __('Advanced customization','easy-form-builder'),
				"orClickHere" => __(' or click here','easy-form-builder'),
				"downloadCSVFile" => __(' Download CSV file','easy-form-builder'),
				"downloadCSVFileSub" => __('Download CSV file of subscriptions','easy-form-builder'),
				"login" => __('Login','easy-form-builder'),
				"thisInputLocked" => __('this input is locked','easy-form-builder'),
				"thisElemantAvailableRemoveable" => __('This elemant is available and removeable.','easy-form-builder'),
				"thisElemantWouldNotRemoveableLoginform" => __('This elemant would not removeable in Login form.','easy-form-builder'),
				"send" => __('Send','easy-form-builder'),
				"contactUs" => __('Contact us','easy-form-builder'),
				"support" => __('Support','easy-form-builder'),
				"subscribe" => __('Subscribe','easy-form-builder'),
				"login" => __('Login','easy-form-builder'),
				"logout" => __('Logout','easy-form-builder'),
				"please" => __('Please','easy-form-builder'),
			];
			wp_enqueue_script( 'Emsfb-listicons-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/listicons.js' );
			wp_enqueue_script('Emsfb-listicons-js');
			$pro =false;
			$ac= $this->get_activeCode_Emsfb();
			if (md5($_SERVER['SERVER_NAME'])==$ac){$pro=true;}
			wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin.js' );
			wp_localize_script('Emsfb-admin-js','efb_var',array(
				'nonce'=> wp_create_nonce("admin-nonce"),
				'pro' => $pro,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang		));

		
			if($pro==true){
				// اگر پولی بود این کد لود شود 
				//پایان کد نسخه پرو
				wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');
			}
			
			 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
						));

			
			$table_name = $this->db->prefix . "Emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
		
			$table_name = $this->db->prefix . "Emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
		

			$lang = get_locale();
		
			if ( strlen( $lang ) > 0 ) {
				$lang = explode( '_', $lang )[0];
				}

		
			?>
			<div id="body_emsFormBuilder" class="m-2"> 
				<div id="msg_emsFormBuilder" class="mx-2">

				
				</div>
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="#">
					<img src="<?php echo EMSFB_PLUGIN_URL.'/includes/admin/assets/image/logo.png' ?>" width="30" height="30" class="d-inline-block align-top" alt="">
					<?php _e('Easy Form Builder','easy-form-builder') ?>
				</a>
				<button class="navbar-toggler" id="navbartogglerb" type="button" data-toggle="collapse" data-target="#navbarToggler" aria-controls="navbarToggler" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse" id="navbarToggler">
					<ul class="navbar-nav mr-auto mt-2 mt-lg-0">
					<li class="nav-item">
						<a class="nav-link active" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button"><?php _e('Forms','easy-form-builder') ?><span class="sr-only">(current)</span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><?php _e('Setting','easy-form-builder') ?></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="admin.php?page=Emsfb_create" role="button"><?php _e('Create','easy-form-builder') ?></a>
					</li>
					<li class="nav-item">
						<a class="nav-link " onClick="fun_show_content_page_emsFormBuilder('help')" role="button"><?php _e('help','easy-form-builder') ?></a>
					</li>
					</ul>
					<div class="form-inline my-2 my-lg-0">
					<input class="form-control mr-sm-2" type="search" id="track_code_emsFormBuilder" placeholder="<?php _e('Search track No.','easy-form-builder') ?>">
					<button class="btn btn-outline-success my-2 my-sm-0" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()"><?php _e('Search','easy-form-builder') ?></button>
					</div>
				</div>
			</nav>


					<div class="row mb-2">					
					<button type="button" class="btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="fa fa-home"></i></button>
					</div>
					<div class="row" id ="emsFormBuilder-content">
					 <h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i><?php _e('Loading','easy-form-builder') ?></h2>
					</div>
					<div class="row mt-2 d-flex justify-content-center align-items-center ">
					<button type="button" id="more_emsFormBuilder" class="mat-shadow emsFormBuilder p-3" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="fa fa-angle-double-down"></i></button>
					</div>
				</div>
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
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.png'
					
				));
					
		}
	}
	public function get_activeCode_Emsfb()
	{
		// اکتیو کد بر می گرداند	
		
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$r =json_decode($val);
			$rtrn =$r->activeCode;
			break;
			} 
		}
		return $rtrn;
	}


	
	public function get_not_read_message(){
		//error_log('get_not_read_message');
		
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0" );
		$rtrn='null';
		//error_log(json_encode($value));
		return $value;
	}


	


	



}