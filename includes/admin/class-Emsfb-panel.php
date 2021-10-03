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
				"trackNo" => __('Tracking Code','easy-form-builder'),
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
				"enterActivateCode" => __('Enter the Activate Code','easy-form-builder'),
				"reCAPTCHAv2" => __('reCAPTCHA v2','easy-form-builder'),
				"reCAPTCHA" => __('reCAPTCHA','easy-form-builder'),
				"reCAPTCHASetError" => __('Please go to Easy Form Builder Panel > Setting > Google Keys  and set Keys of Google reCAPTCHA','easy-form-builder'), //v2 
				"protectsYourWebsiteFromFraud" => __('protects your website from fraud and abuse.','easy-form-builder'),
				"clickHereWatchVideoTutorial" => __('Click here to watch a video tutorial.','easy-form-builder'),
				"siteKey" => __('SITE KEY','easy-form-builder'),
				"enterSITEKEY" => __('Enter the SITE KEY','easy-form-builder'),
				"SecreTKey" => __('SECRET KEY','easy-form-builder'),
				"EnterSECRETKEY" => __('Enter the SECRET KEY','easy-form-builder'),
				"alertEmail" => __('Alert Email','easy-form-builder'),
				"whenEasyFormBuilderRecivesNewMessage" => __('When Easy Form Builder recives a new message, It will send an alret email to admin of plugin.','easy-form-builder'),
				"email" => __('Email','easy-form-builder'),
				"enterAdminEmail" => __('Enter an Admin Email','easy-form-builder'),
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
				"text" => __('Text','easy-form-builder'),
				"password" => __('Password','easy-form-builder'),
				"email" => __('Email','easy-form-builder'),
				"number" => __('Number','easy-form-builder'),
				"file" => __('file','easy-form-builder'),
				"date" => __('Date Picker','easy-form-builder'),
				"tel" => __('Tel','easy-form-builder'),
				"textarea" => __('Long Text','easy-form-builder'),
				"checkbox" => __('Check Box','easy-form-builder'),
				"radiobutton" => __('Radio Button','easy-form-builder'),
				"radio" => __('Radio Button','easy-form-builder'),
				"multiselect" => __('Multiple Select','easy-form-builder'),
				"url" => __('URL','easy-form-builder'),
				"range" => __('Range','easy-form-builder'),
				"color" => __('Color Picker','easy-form-builder'),
				"fileType" => __('File Type','easy-form-builder'),
				"label" => __('Label','easy-form-builder'),
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
				"pleaseUploadA" => __('Please upload the','easy-form-builder'),
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
				"step" => __('Step','easy-form-builder'),
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
				"survey" => __('Survey','easy-form-builder'),
				"chart" => __('Chart','easy-form-builder'),
				"noComment" => __('No comment','easy-form-builder'),
				"easyFormBuilder" => __('Easy Form Builder','easy-form-builder'),
				"byWhiteStudioTeam" => __('By WhiteStudio.team','easy-form-builder'),
				"createForms" => __('Create Forms','easy-form-builder'),
				"tutorial" => __('Tutorial','easy-form-builder'),
				"forms" => __('Forms','easy-form-builder'),
				"tobeginSentence" => __('To begin, you should to be Create a from into Easy Form Builder Plugin. for create a form click below button.','easy-form-builder'),
				"efbIsTheUserSentence" => __('Easy Form Builder is the user-friendly form creator that allows you to create professional multistep forms within minutes.','easy-form-builder'),
				"efbYouDontNeedAnySentence" => __(' You do not need any coding skills to use Easy Form Builder. Simply drag and drop your layouts into order to easily create unlimited custom multistep forms. A unique tracking Code allows you to connect any submission to an individual request.','easy-form-builder'),
				"please" => __('Please','easy-form-builder'),
				"newResponse" => __('New Response','easy-form-builder'),
				"read" => __('Read','easy-form-builder'),
				"copy" => __('Copy','easy-form-builder'),
				"general" => __('General','easy-form-builder'),
				"help" => __('Help','easy-form-builder'),
				"setting" => __('Setting','easy-form-builder'),
				"maps" => __('Maps','easy-form-builder'),
				"youCanFindTutorial" => __('You can find tutorial in beside box and if you need more tutorials click on "Document" button.','easy-form-builder'),
				"proUnlockMsg" => __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder'),
				"aPIKey" => __('API KEY','easy-form-builder'),
				"youNeedAPIgMaps" => __('You need API key of Google Maps if you want to use Maps in forms.','easy-form-builder'),
				"copiedClipboard" => __('Copied to Clipboard','easy-form-builder'),
				"noResponse" => __('No Response','easy-form-builder'),
				"offerGoogleCloud" => __('To activite reCAPTCHA and Maps you have to sign up in Google cloud service which its basic service is free and Google give <b> $350 worth of credits </b> to the new users of Cloud service.','easy-form-builder'),
				"getOfferTextlink" => __('To get the credit you must only click here to start','easy-form-builder'),
				"clickHere" => __('Click here','easy-form-builder'),
				"SpecialOffer" => __('Special offer','easy-form-builder'),
				"googleKeys" => __('Google Keys','easy-form-builder'),
				"youNeedAPIgMaps" => __('You need API key of Google Maps if you want to use Maps in forms.','easy-form-builder'),
				"emailServer" => __('Email server','easy-form-builder'),
				"beforeUsingYourEmailServers" => __('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'),
				"emailSetting" => __('Email Settings','easy-form-builder'),
				"clickToCheckEmailServer" => __('Click To Check Email Server','easy-form-builder'),

				//v2 translate 
				"dadfile" => __('D&D File Upload','easy-form-builder'), //v2 
				"field" => __('Field','easy-form-builder'), //v2 
				"advanced" => __('Advanced','easy-form-builder'), //v2 
				"switch" => __('Switch','easy-form-builder'), //v2 
				"locationPicker" => __('Location Picker','easy-form-builder'), //v2 
				"rating" => __('Rating','easy-form-builder'), //v2 
				"esign" => __('Signature','easy-form-builder'), //v2 
				"yesNo" => __('Yes/No','easy-form-builder'), //v2 
				"htmlCode" => __('HTML Code','easy-form-builder'), //v2 
				"pcPreview" => __('PC Preview','easy-form-builder'), //v2 
				"youDoNotAddAnyInput" => __('You do not add any field','easy-form-builder'), //v2 
				"copyShortcode" => __('Copy shortcode','easy-form-builder'), //v2 
				"copyTrackingcode" => __('Copy Tracking Code','easy-form-builder'), //v2 
				"previewForm" => __('Preview Form','easy-form-builder'), //v2 
				"activateProVersion" => __('Activate Pro Version','easy-form-builder'), //v2 
				"itAppearedStepsEmpty" => __('It is appeared to steps empty','easy-form-builder'), //v2 
				"youUseProElements" => __('You are Using Pro version field. For saving  this element in the form, activate Pro version.','easy-form-builder'), //v2 
				"sampleDescription" => __('Sample description','easy-form-builder'), //v2 
				"fieldAvailableInProversion" => __('This field available in Pro version','easy-form-builder'), //v2 
				"editField" => __('Edit Field','easy-form-builder'), //v2 
				"description" => __('Description','easy-form-builder'), //v2 
				"thisEmailNotificationReceive" => __('Get email notifications','easy-form-builder'), //v2 
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
				"name" => __('Name','easy-form-builder'), //v2 
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
				"stars" => __('Stars','easy-form-builder'), //v2 
				"nothingSelected" => __('Nothing selected','easy-form-builder'), //v2 
				"duplicate" => __('Duplicate','easy-form-builder'), //v2 
				"availableProVersion" => __('Available in pro version','easy-form-builder'), //v2 
				"mobilePreview" => __('Mobile Preview','easy-form-builder'), //v2 
				"thanksFillingOutform" => __('Thanks for filling out our form.','easy-form-builder'), //v2 
				"finish" => __('Finish','easy-form-builder'), //v2 
				"dragAndDropA" => __('Drag & Drop the','easy-form-builder'), //v2 
				"browseFile" => __('Browse File','easy-form-builder'), //v2 
				"removeTheFile" => __('Remove the file','easy-form-builder'), //v2 
				"enterAPIKey" => __('Enter API KEY','easy-form-builder'), //v2 
				"formSetting" => __('Form Settings','easy-form-builder'), //v2 
				"select" => __('Select','easy-form-builder'), //v2 
				"up" => __('Up','easy-form-builder'), //v2 
				"red" => __('Red','easy-form-builder'), //v2 
				"Red" => __('Red','easy-form-builder'), //v2 
				"sending" => __('Sending','easy-form-builder'), //v2 
				"enterYourMessage" => __('Please Enter your message','easy-form-builder'), //v2 
			

				"name" => __('Name','easy-form-builder'), //v2 
				"add" => __('Add','easy-form-builder'), //v2 
				"code" => __('Code','easy-form-builder'), //v2 
				"star" => __('Star','easy-form-builder'), //v2 
				"form" => __('Form','easy-form-builder'), //v2
				"black" => __('Black','easy-form-builder'), //v2  
				"pleaseReporProblem" => __('Please report the following problem to Easy Form builder team','easy-form-builder'), //v2 
				"reportProblem" => __('Report problem','easy-form-builder'), //v2 
				"ddate" => __('Date','easy-form-builder'),//v2
				"serverEmailAble" => __('Your e-mail server able to send Emails','easy-form-builder'),//v2
				"sMTPNotWork" => __('your host can not send emails because Easy form Builder can not connect to the Email server. contact to your Host support','easy-form-builder'),//v2
				"aPIkeyGoogleMapsFeild" => __('Google Maps Loading Errors.','easy-form-builder'),//v2
				"fileIsNotRight" => __('The file is not the right file type','easy-form-builder'), //v2 
				"thisElemantNotAvailable" => __('TThe Field is not available in this type forms','easy-form-builder'),//v2

				
				//v2 translate end

			];
			wp_enqueue_script( 'Emsfb-listicons-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/listicons.js' );
			wp_enqueue_script('Emsfb-listicons-js');

			

			wp_register_script('gchart-js', 'https://www.gstatic.com/charts/loader.js', null, null, true);	
			wp_enqueue_script('gchart-js');
			$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
			"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg'
			];
			$pro =false;
			$efbFunction = new efbFunction(); 
			$ac= $efbFunction->get_setting_Emsfb();
			$smtp =false;
			$captcha =false;
			if(gettype($ac)!="string"){
				if (md5($_SERVER['SERVER_NAME'])==$ac->activeCode){$pro=true;}
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
				'pro' => $pro,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang,
				'images' => $img,
				'captcha'=>$captcha,
				'smtp'=>$smtp		));

		
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
				
			<!-- new nav  -->
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
								<li class="nav-item">
									<a class="nav-link efb active" aria-current="page" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button"><?php _e('Forms','easy-form-builder') ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><?php _e('Setting','easy-form-builder') ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" href="admin.php?page=Emsfb_create" role="button"><?php _e('Create','easy-form-builder') ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('help')" role="button"><?php _e('help','easy-form-builder') ?></a>
								</li>
							</ul>
							<div class="d-flex">
								<form class="d-flex">
									<i class="efb bi-search search-icon"></i>
									<input class="form-control efb search-form-control efb-rounded efb me-2" type="search" type="search" id="track_code_emsFormBuilder" placeholder="<?php _e('Enter the Tracking Code','easy-form-builder') ?>">
									<button class="btn efb btn-outline-pink me-2" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()"><?php _e('Search','easy-form-builder') ?></button>
								</form>
								<div class="nav-icon efb me-2">
									<a class="nav-link efb" href="https://whitestudio.team/?login" target="blank"><i class="efb bi-person"></i></a>
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
									<div class="modal-header efb"> <h5 class="modal-title" ><i class="bi-ui-checks me-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
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
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg'
					
				));
					
		}else{
			echo "Easy Form Builder: You dont access this section";
		}
	}



	
	public function get_not_read_message(){
		//error_log('get_not_read_message');
		
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0" );
		$rtrn='null';
		//error_log(json_encode($value));
		return $value;
	}

/* 
	public function send_email_state($to ,$sub ,$cont,$pro){

		$mailResult = 'n';
		//error_log($mailResult);
		$usr =get_user_by('id','1');
		//error_log(json_encode($usr));
		 $email= $usr->user_email;
		 $role = $usr->roles[0];
		 $name = $usr->display_name;

		 

		$from =$name." <".$email.">";
		if($sub=="reportProblem" || $sub =="testMailServer" )
		{
			$cont .="<span>website:". $_SERVER['SERVER_NAME'] . "</span></br>";
			$cont .="<span>IP:". $_SERVER['REMOTE_ADDR'] . "</span></br>";
			$cont .="<span>Pro user:".$pro . "</span></br>";
		}
		if($to=="test"){$to="hasan.tafreshi@gmail.com";}
		$message ='<!DOCTYPE html> <html> <body><p>'. $cont. '</p>
		</body> </html>';
		//error_log($from);
		$headers = array(
		 'MIME-Version: 1.0\r\n',
		 '"Content-Type: text/html; charset=ISO-8859-1\r\n"',
		 'From:'.$from.''
		 );
		$mailResult = wp_mail( $to,$sub, $cont, $headers );		
		//if($mailResult==1)echo "<h1 class='text-danger' id=mailResult'>".$mailResult."<h1>";
	    return $mailResult;
		/* if ($mailResult=='n' || strlen($mailResult)<1 || $mailResult==false || $mailResult=="false" || $mailResult!=1 || $mailResult<>1){ return false;
		}else {return true;} * /
	} */


	


	



}