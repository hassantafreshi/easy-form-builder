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
		add_submenu_page( 'Emsfb', __( 'Create', 'easy-form-builder' ), __( 'Create', 'easy-form-builder' ), 'Emsfb_create', 'Emsfb_create', array(
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
									<div class="modal-header efb"> <h5 class="modal-title" ><i class="bi-ui-checks me-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
									<div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb"></h3></div></div>
					</div></div></div>
            <div id="tab_container">
           
        	</div>
		<?php
		wp_enqueue_script( 'Emsfb-listicons-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/listicons.js' );
		wp_enqueue_script('Emsfb-listicons-js');

		$pro =false;
		$efbFunction = new efbFunction(); 
		$ac= $efbFunction->get_setting_Emsfb();
		
		if(gettype($ac)!="string"){
			if (md5($_SERVER['SERVER_NAME'])==$ac->activeCode){
				$pro=true;
			}
			if(	$pro==true){
					wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac->activeCode, null, null, true);	
					wp_enqueue_script('whitestudio-admin-pro-js');

				/* 	wp_localize_script('whitestudio-admin-pro-js','efb_var',array(
						'pro' => $pro,
						'rtl' => is_rtl(),
						'text' => $lang
								)); */
			}

			if(strlen($ac->apiKeyMap)>5){
				$k= $ac->apiKeyMap;
				$lang = get_locale();
					if ( strlen( $lang ) > 0 ) {
					$lang = explode( '_', $lang )[0];
					}
				//error_log($lang);
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.';language='.$lang.'libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
		}

		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg'
		];
		$lang = [
			"create" => __('Create','easy-form-builder'),
			"define" => __('Define','easy-form-builder'),
			"formName" => __('Form Name','easy-form-builder'),
			"numberSteps" => __('Number of steps','easy-form-builder'),
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
			"pleaseWaiting" => __('Please Waiting','easy-form-builder'), //v2
			"loading" => __('Loading','easy-form-builder'),
			"remove" => __('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => __('Are you sure want to delete this item?','easy-form-builder'),
			"no" => __('NO','easy-form-builder'),
			"yes" => __('Yes','easy-form-builder'),
			"numberOfSteps" => __('Number of steps','easy-form-builder'),
			"titleOfStep" => __('Title of step','easy-form-builder'),
			"proVersion" => __('Pro Version','easy-form-builder'),
			"youUseProElements" => __('You are Using Pro version field. For saving  this element in the form, activate Pro version.','easy-form-builder'),
			"getProVersion" => __('Get Pro version','easy-form-builder'),
			"clickHereGetActivateCode" => __('Click here to get Activate Code.','easy-form-builder'),
			"email" => __('Email','easy-form-builder'),
			"trackingCode" => __('Tracking code','easy-form-builder'),		
			"save" => __('Save','easy-form-builder'),
			"pcPreview" => __('PC Preview','easy-form-builder'),
			"help" => __('Help','easy-form-builder'),
			"waiting" => __('Waiting','easy-form-builder'),
			"saved" => __('Saved','easy-form-builder'),
			"error" => __('Error,','easy-form-builder'),
			"itAppearedStepsEmpty" => __('It is appeared to steps empty','easy-form-builder'),
			"previewForm" => __('Preview Form','easy-form-builder'),
			"activateProVersion" => __('Activate Pro Version','easy-form-builder'),
			"copyTrackingcode" => __('Copy Tracking Code','easy-form-builder'),
			"copyShortcode" => __('Copy shortcode','easy-form-builder'),
			"youDoNotAddAnyInput" => __('You do not add any fields','easy-form-builder'),
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
			"required" => __('Required','easy-form-builder'),//v2
			"button" => __('button','easy-form-builder'),
			"text" => __('Text','easy-form-builder'),
			"password" => __('Password','easy-form-builder'),
			"emailOrUsername" => __('Email or Username','easy-form-builder'),
			"number" => __('number','easy-form-builder'),
			"file" => __('File Upload','easy-form-builder'),
			"dadfile" => __('D&D File Upload','easy-form-builder'),
			"date" => __('Date Picker','easy-form-builder'),
			"tel" => __('tel','easy-form-builder'),
			"maps" => __('Maps','easy-form-builder'),
			"textarea" => __('Long Text','easy-form-builder'),
			"checkbox" => __('Check Box','easy-form-builder'),
			"radiobutton" => __('Radio Button','easy-form-builder'),
			"radio" => __('Radio Button','easy-form-builder'),
			"select" => __('Select','easy-form-builder'),
			"multiselect" => __('Multiple Select','easy-form-builder'),
			"switch" => __('Switch','easy-form-builder'),
			"url" => __('URL','easy-form-builder'),
			"range" => __('range','easy-form-builder'),
			"locationPicker" => __('Location Picker','easy-form-builder'),
			"color" => __('Color Picker','easy-form-builder'),
			"fileType" => __('File Type','easy-form-builder'),
			"label" => __('Label','easy-form-builder'),
			"rating" => __('Rating','easy-form-builder'),
			"esign" => __('Signature','easy-form-builder'),
			"htmlCode" => __('HTML Code','easy-form-builder'),
			"yesNo" => __('Yes/No','easy-form-builder'),
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
			"orClickHere" => __(' or click here','easy-form-builder'),
			"pleaseEnterTheTracking" => __('Please enter the tracking code','easy-form-builder'),
			"somethingWentWrongTryAgain" => __('Something went wrong, Please refresh and try again','easy-form-builder'),			
			"enterThePhone" => __('Please Enter the phone number','easy-form-builder'),			
			"pleaseMakeSureAllFields" => __('Please make sure all fields are filled in correctly.','easy-form-builder'),
			"enterTheEmail" => __('Please Enter the Email address','easy-form-builder'),
			"formNotFound" => __('Form does not found','easy-form-builder'),
			"errorV01" => __('Error Code:V01','easy-form-builder'),
			"enterٰValidURL" => __('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"password8Chars" => __('Password must be at least 8 characters','easy-form-builder'),
			"registered" => __('Registered','easy-form-builder'),
			"yourInformationRegistered" => __('Your information is successfully registered','easy-form-builder'),		
			"youNotPermissionUploadFile" => __('You do not have permission to upload this file:','easy-form-builder'),
			"pleaseUploadA" => __('Please upload the','easy-form-builder'),
			"trackingForm" => __('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => __('Tracking Code is not valid.','easy-form-builder'),
			"checkedBoxIANotRobot" => __('Please Checked Box of I am Not robot','easy-form-builder'),
			"step" => __('step','easy-form-builder'),
			"contactusForm" => __('Contact-us Form','easy-form-builder'),
			"newForm" => __('New Form','easy-form-builder'),
			"registerForm" => __('Register Form','easy-form-builder'),
			"loginForm" => __('Login Form','easy-form-builder'),
			"login" => __('Login','easy-form-builder'),
			"thisInputLocked" => __('this input is locked','easy-form-builder'),
			"subscriptionForm" => __('Subscription Form','easy-form-builder'),
			"supportForm" => __('Support Form','easy-form-builder'),
			"createBlankMultistepsForm" => __('Create a blank multisteps form.','easy-form-builder'),
			"createContactusForm" => __('Create a Contact us form.','easy-form-builder'),
			"createRegistrationForm" => __('Create a user registration(Sign-up) form.','easy-form-builder'),
			"createLoginForm" => __('Create a user login (Sign-in) form.','easy-form-builder'),
			"createnewsletterForm" => __('Create a newsletter form','easy-form-builder'),
			"createSupportForm" => __('Create a support contact form.','easy-form-builder'),
			"availableSoon" => __('Available Soon','easy-form-builder'),
			"advancedCustomization" => __('Advanced customization','easy-form-builder'),
			"contactUs" => __('Contact us','easy-form-builder'),
			"support" => __('Support','easy-form-builder'),
			"subscribe" => __('Subscribe','easy-form-builder'),
			"survey" => __('Survey ','easy-form-builder'),
			"reservation" => __('Reservation ','easy-form-builder'),
			"createsurveyForm" => __('Create survey or poll or questionnaire forms ','easy-form-builder'),
			"createReservationyForm" => __('Create reservation or booking forms ','easy-form-builder'),
			"send" => __('Send','easy-form-builder'),
			"thisElemantAvailableRemoveable" => __('This elemant is available and removeable.','easy-form-builder'),
			"thisElemantWouldNotRemoveableLoginform" => __('This elemant would not removeable in Login or Register form.','easy-form-builder'),
			"firstName" => __('First name','easy-form-builder'),
			"lastName" => __('Last name','easy-form-builder'),
			"message" => __('Message','easy-form-builder'),
			"subject" => __('Subject','easy-form-builder'),
			"phone" => __('Phone','easy-form-builder'),
			"register" => __('Register'),
			"username" => __('Username'),
			"proUnlockMsg" => __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder'),
			"easyFormBuilder" => __('Easy Form Builder','easy-form-builder'),
			"byWhiteStudioTeam" => __('By WhiteStudio.team','easy-form-builder'),
			"allStep" => __('all step','easy-form-builder'),
			"createForms" => __('Create Forms','easy-form-builder'),
			"tutorial" => __('Tutorial','easy-form-builder'),
			"efbIsTheUserSentence" => __('Easy Form Builder is the user-friendly form creator that allows you to create professional multistep forms within minutes.','easy-form-builder'),
			"efbYouDontNeedAnySentence" => __('You do not need any coding skills to use Easy Form Builder. Simply drag and drop your layouts into order to easily create unlimited custom multistep forms. A unique tracking Code allows you to connect any submission to an individual request.  ','easy-form-builder'),
			"please" => __('Please','easy-form-builder'),
			//v2 translate 
			"fieldAvailableInProversion" => __('This field available in Pro version','easy-form-builder'),//v2
			"sampleDescription" => __('Sample description','easy-form-builder'),//v2
			"editField" => __('Edit Field','easy-form-builder'),//v2
			"description" => __('Description','easy-form-builder'),//v2
			"thisEmailNotificationReceive" => __('Get email notifications','easy-form-builder'),
			"activeTrackingCode" => __('Active Tracking Code','easy-form-builder'), //v2 
			"addGooglereCAPTCHAtoForm" => __('Add Google reCAPTCHA to the form ','easy-form-builder'), //v2 
			"dontShowIconsStepsName" => __('Don\'t show Icons & Steps name','easy-form-builder'), //v2 
			"dontShowProgressBar" => __('Don\'t show progress bar','easy-form-builder'), //v2 
			"showTheFormTologgedUsers" => __('Show the form to logged in users only','easy-form-builder'), //v2 
			"labelSize" => __('Label size','easy-form-builder'), //v2 
			"default" => __('Default','easy-form-builder'), //v2 
			"small" => __('Small','easy-form-builder'), //v2 
			"large" => __('Large','easy-form-builder'), //v2 
			"xlarge" => __('XLarge','easy-form-builder'), //v2 
			"xxlarge" => __('XXLarge','easy-form-builder'), //v2 
			"xxxlarge" => __('XXXLarge','easy-form-builder'), //v2 
			"labelPostion" => __('Label Postion','easy-form-builder'), //v2 
			"beside" => __('Beside','easy-form-builder'), //v2 
			"align" => __('Align','easy-form-builder'), //v2 
			"left" => __('Left','easy-form-builder'), //v2 
			"center" => __('Center','easy-form-builder'), //v2 
			"right" => __('Right','easy-form-builder'), //v2 
			"width" => __('Width','easy-form-builder'), //v2 
			"cSSClasses" => __('CSS Classes','easy-form-builder'), //v2 
			"defaultValue" => __('Default value','easy-form-builder'), //v2 
			"placeholder" => __('Placeholder','easy-form-builder'), //v2 
			"enterAdminEmailReceiveNoti" => __('Enter Admin Email to receive notification email','easy-form-builder'), //v2 
			"corners" => __('Corners','easy-form-builder'), //v2 
			"rounded" => __('Rounded','easy-form-builder'), //v2 
			"square" => __('Square','easy-form-builder'), //v2 
			"icon" => __('Icon','easy-form-builder'), //v2 
			"buttonColor" => __('Button Color','easy-form-builder'), //v2 
			"blue" => __('Blue','easy-form-builder'), //v2 
			"darkBlue" => __('Dark Blue','easy-form-builder'), //v2 
			"lightBlue" => __('Light Blue','easy-form-builder'), //v2 
			"grayLight" => __('Gray Light','easy-form-builder'), //v2 
			"grayLighter" => __('Gray Lighter','easy-form-builder'), //v2 
			"green" => __('Green','easy-form-builder'), //v2 
			"pink" => __('Pink','easy-form-builder'), //v2 
			"yellow" => __('Yellow','easy-form-builder'), //v2 
			"light" => __('Light','easy-form-builder'), //v2 
			"Red" => __('red','easy-form-builder'), //v2 
			"grayDark" => __('Gray Dark','easy-form-builder'), //v2 
			"white" => __('White','easy-form-builder'), //v2 
			"clr" => __('Color','easy-form-builder'), //v2 
			"borderColor" => __('Border Color','easy-form-builder'), //v2 
			"height" => __('Height','easy-form-builder'), //v2 
			"latitude" => __('Latitude','easy-form-builder'), //v2 
			"longitude" => __('Longitude','easy-form-builder'), //v2 
			"exDot" => __('ex.','easy-form-builder'), //v2 
			"pleaseDoNotAddJsCode" => __('(Please do not add Javascript or jQuery codes to html codes for security reasons)','easy-form-builder'), //v2 
			"button1Value" => __('Button 1 value','easy-form-builder'), //v2 
			"button2Value" => __('Button 2 value','easy-form-builder'), //v2 
			"iconList" => __('Icons list','easy-form-builder'), //v2 
			"previous" => __('Previous','easy-form-builder'), //v2 
			"next" => __('next','easy-form-builder'), //v2 
			"invalidEmail" => __('Invalid email address”','easy-form-builder'), //v2 
			"noCodeAddedYet" => __('Code has not yet been added. Click on','easy-form-builder'), //v2 
			"andAddingHtmlCode" => __(' and adding html code.','easy-form-builder'), //v2 
			"proMoreStep" => __('for use mor steps XXXXXX','easy-form-builder'), //v2 
			"aPIkeyGoogleMapsError" => __('API key of Google maps has not been added. Please add API key of google maps in setting of plugin and try again.','easy-form-builder'), //v2 
			"howToAddGoogleMap" => __('How to Add Google Map to Easy form Builder WordPress Plugin','easy-form-builder'), //v2 
			"deletemarkers" => __('Delete markers','easy-form-builder'), //v2 
			"updateUrbrowser" => __('update your browser','easy-form-builder'), //v2 
			"clear" => __('Clear','easy-form-builder'), //v2 
			"star" => __('Star','easy-form-builder'), //v2 
			"stars" => __('Stars','easy-form-builder'), //v2 
			"nothingSelected" => __('Nothing selected','easy-form-builder'), //v2 
			"duplicate" => __('Duplicate','easy-form-builder'), //v2 
			"availableProVersion" => __('Available in pro version','easy-form-builder'), //v2 
			"mobilePreview" => __('Mobile Preview','easy-form-builder'), //v2 
			"thanksFillingOutform" => __('Thanks for filling out our form.','easy-form-builder'), //v2 
			"finish" => __('Finish','easy-form-builder'), //v2 
			"copiedClipboard" => __('Copied to Clipboard','easy-form-builder'), //v2 
			"dragAndDropA" => __('Drag & Drop the','easy-form-builder'), //v2 
			"browseFile" => __('Browse File','easy-form-builder'), //v2 
			"removeTheFile" => __('Remove the file','easy-form-builder'), //v2 
			"offerGoogleCloud" => __('To activite reCAPTCHA and Maps you have to sign up in Google cloud service which its basic service is free and Google give <b> $350 worth of credits </b> to the new users of Cloud service.','easy-form-builder'), //v2 
			"getOfferTextlink" => __('To get the credit you must only click here to start','easy-form-builder'), //v2 
			"SpecialOffer" => __('Special offer','easy-form-builder'), //v2 
			"trackingCodeFinder" => __('Tracking code Finder','easy-form-builder'), //v2 
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => __('Copy and Paste below short-code of tracking code finder in any page or post.','easy-form-builder'), //v2 
			"clearUnnecessaryFiles" => __('Clear unnecessary files','easy-form-builder'), //v2 
			"youCanRemoveUnnecessaryFileUploaded" => __('You can Remove unnecessary file uploaded by user with below button','easy-form-builder'), //v2 
			"alertEmail" => __('Alert Email','easy-form-builder'), //v2 
			"whenEasyFormBuilderRecivesNewMessage" => __('When Easy Form Builder recives a new message, It will send an alret email to admin of plugin.','easy-form-builder'), //v2 
			"reCAPTCHAv2" => __('reCAPTCHA v2','easy-form-builder'), //v2 
			"reCAPTCHA" => __('reCAPTCHA','easy-form-builder'), //v2 
			"reCAPTCHASetError" => __('Please go to Easy Form Builder Panel > Setting > Google Keys  and set Keys of Google reCAPTCHA','easy-form-builder'), //v2 
			"protectsYourWebsiteFromFraud" => __('protects your website from fraud and abuse.','easy-form-builder'), //v2 
			"clickHereWatchVideoTutorial" => __('Click here to watch a video tutorial.','easy-form-builder'), //v2 
			"siteKey" => __('SITE KEY','easy-form-builder'), //v2 
			"enterSITEKEY" => __('Enter the SITE KEY','easy-form-builder'), //v2 
			"SecreTKey" => __('SECRET KEY','easy-form-builder'), //v2 
			"EnterSECRETKEY" => __('Enter the SECRET KEY','easy-form-builder'), //v2 
			"youNeedAPIgMaps" => __('You need API key of Google Maps if you want to use Maps in forms.','easy-form-builder'), //v2 
			"aPIKey" => __('API KEY','easy-form-builder'), //v2 
			
			"clearFiles" => __('Clear Files','easy-form-builder'), //v2 
			"enterAdminEmail" => __('Enter an Admin Email','easy-form-builder'), //v2 
			"emailServer" => __('Email server','easy-form-builder'), //v2 
			"beforeUsingYourEmailServers" => __('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'), //v2 
			"clickToCheckEmailServer" => __('Click To Check Email Server','easy-form-builder'), //v2 
			"emailSetting" => __('Email Settings','easy-form-builder'), //v2 
			"setting" => __('Setting','easy-form-builder'), //v2 
			"general" => __('General','easy-form-builder'), //v2 
			"googleKeys" => __('Google Keys','easy-form-builder'), //v2 
			"enterActivateCode" => __('Enter the Activate Code','easy-form-builder'), //v2 
			"formSetting" => __('Form Settings','easy-form-builder'), //v2 
			"up" => __('Up','easy-form-builder'), //v2
			"red" => __('Red','easy-form-builder'), //v2 
			"Red" => __('Red','easy-form-builder'), //v2 
			"field" => __('Field','easy-form-builder'), //v2 
			"advanced" => __('Advanced','easy-form-builder'), //v2 
			"form" => __('Form','easy-form-builder'), //v2 
			"clickHere" => __('Click here','easy-form-builder'),

			"name" => __('Name','easy-form-builder'), //v2 
			"add" => __('Add','easy-form-builder'), //v2 
			"code" => __('Code','easy-form-builder'), //v2 
			"star" => __('Star','easy-form-builder'), //v2 
			"form" => __('Form','easy-form-builder'), //v2 
			"black" => __('Black','easy-form-builder'), //v2 
			"pleaseReporProblem" => __('Please report the following problem to Easy Form builder team','easy-form-builder'), //v2 
			"reportProblem" => __('Report problem','easy-form-builder'), //v2 
			"ddate" => __('Date','easy-form-builder'),//v2
			"sMTPNotWork" => __('your host can not send emails because Easy form Builder can not connect to the Email server. contact to your Host support','easy-form-builder'),//v2
			"aPIkeyGoogleMapsFeild" => __('Google Maps Loading Errors.','easy-form-builder'),//v2
			"fileIsNotRight" => __('The file is not the right file type','easy-form-builder'), //v2 
			//v2 translate end
			
			
		];
		$smtp =false;
		$captcha =false;
		$smtp_m = "";
		error_log(gettype($ac)!="string");
		if(gettype($ac)!="string"){
			if(strlen($ac->siteKey)>5){$captcha="true";}
			if(strlen($ac->smtp)>3){$smtp=$ac->smtp;}else{
				$smtp_m =__('your host can not send emails because Easy form Builder can not connect to the Email server. contact to your Host support','easy-form-builder');
			}			
		}else{
			$smtp_m = __('Please go to Easy Form Builder panel > setting > Email Settings  and Click on "Click To Check Email Server"','easy-form-builder');
		}
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
			"smtp_message"=>$smtp
			

					));

			


		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));

			wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js');
			wp_enqueue_script('efb-main-js'); 


	
	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){
	

		$this->userId =get_current_user_id();
	//	error_log('get_current_user_id');
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';

		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m = __('Something went wrong,Please check all input','easy-form-builder');
			$response = array( 'success' => false , "m"=>$m); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		
		if( isset($_POST['email']) ){
			$email =sanitize_email($_POST['email']);
		}
		//error_log('$this->id_ ="hid";');
		
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;
		$this->value =  sanitize_text_field($_POST['value']);
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){
			$response = array( 'success' => false , "m"=> __("You are not allowed use Scripts tag" ,'easy-form-builder')); 
			wp_send_json_success($response,$_POST);
			die();
		}

		//error_log('$this->insert_db();');
		$this->insert_db();
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_); 
		}else{
			$response = array( 'success' => false , "m"=> __("The form is not Created!" ,'easy-form-builder')); 
		}
		//error_log($response);
		wp_send_json_success($response,$_POST);
		die();
		
	}
	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
	
		
		

		$table_name = $this->db->prefix . "Emsfb_form";
		//echo $table_name;
		$this->db->insert($table_name, array(
			'form_name' => $this->name, 
			'form_structer' => $this->value, 
			'form_email' => $this->email, 
			'form_created_by' => $this->userId, 
			'form_type'=>$this->formtype, 			
		));    $this->id_  = $this->db->insert_id; 
		//echo "last id" + $lastid;
		
		
		
		
	}
	public function get_setting_Emsfb()
	{
		// اکتیو کد بر می گرداند	
		
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$rtrn =json_decode($val);
			//$rtrn =$r->activeCode;
			//error_log($r->apiKeyMap);
			break;
			} 
		}
		return $rtrn;
	}
}

new Create();