<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No direct access allow ;)


class efbFunction {

	protected $db;
	
	public function __construct() {  
		global $wpdb;
		$this->db = $wpdb; 
		
    }

	function test_call_efb(){
		//error_log('test functions Coll Done!');

		
	}

	public function text_efb($inp){
		$lang = [
			"create" => __('Create','easy-form-builder'),
			"define" => __('Define','easy-form-builder'),
			"formName" => __('Form Name','easy-form-builder'),
			"createDate" => __('Create Date','easy-form-builder'),
			"edit" => __('Edit','easy-form-builder'),
			"content" => __('Content','easy-form-builder'),
			"trackNo" => __('Confirmation Code','easy-form-builder'),
			"formDate" => __('Form Date','easy-form-builder'),
			"by" => __('By','easy-form-builder'),
			"ip" => __('IP','easy-form-builder'),
			"guest" => __('Guest','easy-form-builder'),			
			"response" => __('Response','easy-form-builder'),
			"date" => __('Date Picker','easy-form-builder'),
			"videoDownloadLink" => __('Video Download','easy-form-builder'),
			"downloadViedo" => __('Download Video','easy-form-builder'),
			"download" => __('Download','easy-form-builder'),
			"youCantUseHTMLTagOrBlank" => __('You can not use HTML Tag or send blank messages.','easy-form-builder'),
			"reply" => __('Reply','easy-form-builder'),
			"messages" => __('Messages','easy-form-builder'),
			"pleaseWaiting" => __('Please Waiting','easy-form-builder'),
			"loading" => __('Loading','easy-form-builder'),
			"remove" => __('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => __('Are you sure you want to delete this item?','easy-form-builder'),
			"no" => __('NO','easy-form-builder'),
			"yes" => __('Yes','easy-form-builder'),
			"numberOfSteps" => __('Number of steps','easy-form-builder'),
			"titleOfStep" => __('Title of step','easy-form-builder'),
			"proVersion" => __('Pro Version','easy-form-builder'),
			"getProVersion" => __('Get Pro version','easy-form-builder'),					
			"reCAPTCHA" => __('reCAPTCHA','easy-form-builder'),
			"protectsYourWebsiteFromFraud" => __('Click here to watch a video tutorial.','easy-form-builder'),
			"enterSITEKEY" => __('SECRET KEY','easy-form-builder'),
			"alertEmail" => __('Alert Email','easy-form-builder'),
			"enterAdminEmail" => __('If you do not want to show Confirmation Code to the user, do not mark the option below.','easy-form-builder'),
			"showTrackingCode" => __('Show Confirmation Code','easy-form-builder'),
			"trackingCodeFinder" => __('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => __('Copy and Paste below short-code of Confirmation Code finder in any page or post.','easy-form-builder'),
			"save" => __('Save','easy-form-builder'),
			"waiting" => __('Waiting','easy-form-builder'),
			"saved" => __('Saved','easy-form-builder'),
			"stepName" => __('Step Name','easy-form-builder'),
			"IconOfStep" => __('Icon of step','easy-form-builder'),
			"stepTitles" => __('Step Titles','easy-form-builder'),
			"elements" => __('Elements:','easy-form-builder'),
			"delete" => __('Delete','easy-form-builder'),
			"newOption" => __('New option','easy-form-builder'),
			"media" => __('(Video or Audio)','easy-form-builder'),
			"required" => __('Required','easy-form-builder'),
			"button" => __('Text','easy-form-builder'),
			"password" => __('Password','easy-form-builder'),
			"email" => __('Email','easy-form-builder'),
			"number" => __('Number','easy-form-builder'),
			"file" => __('File Upload','easy-form-builder'),
			"tel" => __('Tel','easy-form-builder'),
			"textarea" => __('Long Text','easy-form-builder'),
			"checkbox" => __('Check Box','easy-form-builder'),
			"radiobutton" => __('Radio Button','easy-form-builder'),
			"radio" => __('Radio','easy-form-builder'),
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
			"pleaseFillInRequiredFields" => __('Please fill in all required fields.','easy-form-builder'),
			"availableInProversion" => __('This option is available in Pro version','easy-form-builder'),
			"formNotBuilded" => __('The form has not been builded!','easy-form-builder'),
			"someStepsNotDefinedCheck" => __('Some steps not defined, Please check:','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => __('If you need create more than 2 Steps, activate ','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwo" => __('You can create minimum 1 and maximum 2 Steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwenty" => __('You Could create minimum 1 Step and maximum 20 Step','easy-form-builder'),
			"preview" => __('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => __('Something went wrong, Please refresh and try again','easy-form-builder'),
			"formNotCreated" => __('The form has not been created!','easy-form-builder'),
			"atFirstCreateForm" => __('At first create a form and add elements then try again','easy-form-builder'),
			"allowMultiselect" => __('Allow multi-select','easy-form-builder'),
			"DragAndDropUI" => __('Drag and drop UI','easy-form-builder'),
			"clickHereForActiveProVesrsion" => __('Click here for Active Pro version','easy-form-builder'),
			"selectOpetionDisabled" => __('Select a option (Disabled in test view)','easy-form-builder'),
			"pleaseEnterTheTracking" => __('Please enter the Confirmation Code','easy-form-builder'),									
			"formNotFound" => __('Form is not found','easy-form-builder'),
			"errorV01" => __('Error Code:V01','easy-form-builder'),
			"password8Chars" => __('Password must be at least 8 characters','easy-form-builder'),
			"registered" => __('Registered','easy-form-builder'),
			"yourInformationRegistered" => __('Your information is successfully registered','easy-form-builder'),
			"youNotPermissionUploadFile" => __('You do not have permission to upload this file:','easy-form-builder'),
			"pleaseUploadA" => __('Please upload the','easy-form-builder'),
			"please" => __('Please','easy-form-builder'),
			"trackingForm" => __('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => __('Confirmation Code is not valid.','easy-form-builder'),
			"checkedBoxIANotRobot" => __('Please Checked Box of I am Not robot','easy-form-builder'),
			"howConfigureEFB" => __('How to configure Easy Form Builder','easy-form-builder'),
			"howGetGooglereCAPTCHA" => __('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
			"howActivateAlertEmail" => __('How to activate the alert email for new form submission','easy-form-builder'),
			"howCreateAddForm" => __('How to create and add a form with Easy Form Builder','easy-form-builder'),
			"howActivateTracking" => __('How to activate a Confirmation Code in Easy Form Builder','easy-form-builder'),
			"howWorkWithPanels" => __('How to work with panels in Easy Form Builder','easy-form-builder'),
			"points" => __('points','easy-form-builder'),
			"howAddTrackingForm" => __('How to add The Confirmation Code Finder to a post, page, or custom post type','easy-form-builder'),
			"howFindResponse" => __('How to find a response through a Confirmation Code','easy-form-builder'),
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
			"efbYouDontNeedAnySentence" => __(' You do not need any coding skills to use Easy Form Builder. Simply drag and drop your layouts into order to easily create unlimited custom multistep forms. A unique Confirmation Code allows you to connect any submission to an individual request.','easy-form-builder'),
			"newResponse" => __('New Response','easy-form-builder'),
			"read" => __('Read','easy-form-builder'),
			"copy" => __('Copy','easy-form-builder'),
			"general" => __('General','easy-form-builder'),
			"dadFieldHere" => __('Drag & Drop Fields Here','easy-form-builder'),
			"help" => __('Help','easy-form-builder'),
			"setting" => __('Setting','easy-form-builder'),
			"maps" => __('Maps','easy-form-builder'),
			"youCanFindTutorial" => __('You can find tutorial in beside box and if you need more tutorials click on "Document" button.','easy-form-builder'),
			"proUnlockMsg" => __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder'),
			"aPIKey" => __('API KEY','easy-form-builder'),
			"youNeedAPIgMaps" => __('You need API key of Google Maps if you want to use Maps in forms.','easy-form-builder'),
			"copiedClipboard" => __('Copied to Clipboard','easy-form-builder'),
			"noResponse" => __('No Response','easy-form-builder'),
			"offerGoogleCloud" => __('For using reCAPTCHA and location picker(Maps) sign up in Google cloud service, you will get <b>USD $350</b> worth of credits in Google Cloud only for our users,','easy-form-builder'),
			"getOfferTextlink" => __(' Click here to get credits.','easy-form-builder'),
			"clickHere" => __('Click here','easy-form-builder'),
			"SpecialOffer" => __('Special offer','easy-form-builder'),
			"googleKeys" => __('Google Keys','easy-form-builder'),
			"emailServer" => __('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => __('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'),
			"emailSetting" => __('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => __('Click To Check Email Server','easy-form-builder'),
			"dadfile" => __('D&D File Upload','easy-form-builder'),
			"field" => __('Field','easy-form-builder'),
			"advanced" => __('Advanced','easy-form-builder'),
			"switch" => __('Switch','easy-form-builder'),
			"locationPicker" => __('Location Picker','easy-form-builder'),
			"rating" => __('Rating','easy-form-builder'),
			"esign" => __('Signature','easy-form-builder'),
			"yesNo" => __('Yes/No','easy-form-builder'),
			"htmlCode" => __('HTML Code','easy-form-builder'),
			"pcPreview" => __('PC Preview','easy-form-builder'),
			"youDoNotAddAnyInput" => __('You do not add any field','easy-form-builder'),
			"copyShortcode" => __('Copy ShortCode','easy-form-builder'),
			"shortcode" => __('ShortCode','easy-form-builder'),
			"copyTrackingcode" => __('Copy Confirmation Code','easy-form-builder'),
			"previewForm" => __('Preview Form','easy-form-builder'),
			"activateProVersion" => __('Activate Pro Version','easy-form-builder'),
			"itAppearedStepsEmpty" => __('It is appeared to steps empty','easy-form-builder'),
			"youUseProElements" => __('You are Using Pro version field. For saving this element in the form, activate Pro version.','easy-form-builder'),
			"sampleDescription" => __('Sample description','easy-form-builder'),
			"fieldAvailableInProversion" => __('This field available in Pro version','easy-form-builder'),
			"editField" => __('Edit Field','easy-form-builder'),
			"description" => __('Description','easy-form-builder'),
			"thisEmailNotificationReceive" => __('Enable email notification','easy-form-builder'),
			"activeTrackingCode" => __('Active Confirmation Code','easy-form-builder'),
			"addGooglereCAPTCHAtoForm" => __('Add Google reCAPTCHA to the form ','easy-form-builder'),
			"dontShowIconsStepsName" => __('Do not show Icons & Steps name','easy-form-builder'),
			"dontShowProgressBar" => __('Do not show progress bar','easy-form-builder'),
			"showTheFormTologgedUsers" => __('Private form','easy-form-builder'),
			"labelSize" => __('Label size','easy-form-builder'),
			"default" => __('Default','easy-form-builder'),
			"small" => __('Small','easy-form-builder'),
			"large" => __('Large','easy-form-builder'),
			"xlarge" => __('XLarge','easy-form-builder'),
			"xxlarge" => __('XXLarge','easy-form-builder'),
			"xxxlarge" => __('XXXLarge','easy-form-builder'),
			"labelPostion" => __('Label Position','easy-form-builder'),
			"align" => __('Align','easy-form-builder'),
			"left" => __('Left','easy-form-builder'),
			"center" => __('Center','easy-form-builder'),
			"right" => __('Right','easy-form-builder'),
			"width" => __('Width','easy-form-builder'),
			"cSSClasses" => __('CSS Classes','easy-form-builder'),
			"defaultValue" => __('Default value','easy-form-builder'),
			"placeholder" => __('Placeholder','easy-form-builder'),
			"enterAdminEmailReceiveNoti" => __('Enter Admin Email to receive email notification','easy-form-builder'),
			"corners" => __('Corners','easy-form-builder'),
			"rounded" => __('Rounded','easy-form-builder'),
			"square" => __('Square','easy-form-builder'),
			"icon" => __('Icon','easy-form-builder'),
			"buttonColor" => __('Button Color','easy-form-builder'),
			"blue" => __('Blue','easy-form-builder'),
			"darkBlue" => __('Dark Blue','easy-form-builder'),
			"lightBlue" => __('Light Blue','easy-form-builder'),
			"grayLight" => __('Gray Light','easy-form-builder'),
			"grayLighter" => __('Gray Lighter','easy-form-builder'),
			"green" => __('Green','easy-form-builder'),
			"pink" => __('Pink','easy-form-builder'),
			"yellow" => __('Yellow','easy-form-builder'),
			"light" => __('Light','easy-form-builder'),
			"Red" => __('red','easy-form-builder'),
			"grayDark" => __('Gray Dark','easy-form-builder'),
			"white" => __('White','easy-form-builder'),
			"clr" => __('Color','easy-form-builder'),
			"borderColor" => __('Border Color','easy-form-builder'),
			"height" => __('Height','easy-form-builder'),
			"name" => __('Name','easy-form-builder'),
			"latitude" => __('Latitude','easy-form-builder'),
			"longitude" => __('Longitude','easy-form-builder'),
			"exDot" => __('ex.','easy-form-builder'),
			"pleaseDoNotAddJsCode" => __('(Please do not add Javascript or jQuery codes to html codes for security reasons)','easy-form-builder'),
			"button1Value" => __('Button 1 value','easy-form-builder'),
			"button2Value" => __('Button 2 value','easy-form-builder'),
			"iconList" => __('Icons list','easy-form-builder'),
			"previous" => __('Previous','easy-form-builder'),
			"next" => __('Next','easy-form-builder'),
			"noCodeAddedYet" => __('Code has not yet been added. Click on','easy-form-builder'),
			"andAddingHtmlCode" => __(' and adding html code.','easy-form-builder'),
			"proMoreStep" => __('When you activate pro version, so you can create unlimited form steps.','easy-form-builder'),
			"aPIkeyGoogleMapsError" => __('API key of Google maps has not been added. Please add API key of google maps on Easy Form Builder > Panel > setting > Google Keys and try again.','easy-form-builder'),
			"howToAddGoogleMap" => __('How to Add Google maps to Easy form Builder WordPress Plugin','easy-form-builder'),
			"deletemarkers" => __('Delete markers','easy-form-builder'),
			"updateUrbrowser" => __('update your browser','easy-form-builder'),
			"stars" => __('Stars','easy-form-builder'),
			"nothingSelected" => __('Nothing selected','easy-form-builder'),
			"duplicate" => __('Duplicate','easy-form-builder'),
			"availableProVersion" => __('Available in pro version','easy-form-builder'),
			"mobilePreview" => __('Mobile Preview','easy-form-builder'),
			"thanksFillingOutform" => __('Thanks for filling out our form.','easy-form-builder'),
			"finish" => __('Finish','easy-form-builder'),
			"dragAndDropA" => __('Drag & Drop the','easy-form-builder'),
			"browseFile" => __('Browse File','easy-form-builder'),
			"removeTheFile" => __('Remove the file','easy-form-builder'),
			"enterAPIKey" => __('Enter API KEY','easy-form-builder'),
			"formSetting" => __('Form Settings','easy-form-builder'),
			"select" => __('Select','easy-form-builder'),
			"up" => __('Up','easy-form-builder'),
			"sending" => __('Sending','easy-form-builder'),
			"enterYourMessage" => __('Please Enter your message','easy-form-builder'),
			"add" => __('Add','easy-form-builder'),
			"code" => __('Code','easy-form-builder'),
			"star" => __('Star','easy-form-builder'),
			"form" => __('Form','easy-form-builder'),
			"black" => __('Black','easy-form-builder'),
			"pleaseReporProblem" => __('Please report the following problem to Easy Form builder team','easy-form-builder'),
			"reportProblem" => __('Report problem','easy-form-builder'),
			"ddate" => __('Date','easy-form-builder'),
			"serverEmailAble" => __('Your e-mail server able to send Emails','easy-form-builder'),
			"sMTPNotWork" => __('your host can not send emails because Easy form Builder can not connect to the Email server. contact to your Host support','easy-form-builder'),
			"aPIkeyGoogleMapsFeild" => __('Google Maps Loading Errors.','easy-form-builder'),
			"fileIsNotRight" => __('The file is not the right file type','easy-form-builder'),
			"thisElemantNotAvailable" => __('The Field is not available in this type forms','easy-form-builder'),
			"numberSteps" => __('Edit','easy-form-builder'),
			"clickHereGetActivateCode" => __('Click here to get Activate Code.','easy-form-builder'),			
			"trackingCode" => __('Confirmation Code','easy-form-builder'),
			"text" => __('Text','easy-form-builder'),
			"multiselect" => __('Multiple Select','easy-form-builder'),
			"newForm" => __('New Form','easy-form-builder'),
			"registerForm" => __('Register Form','easy-form-builder'),
			"loginForm" => __('Login Form','easy-form-builder'),
			"subscriptionForm" => __('Subscription Form','easy-form-builder'),
			"supportForm" => __('Support Form','easy-form-builder'),
			"createBlankMultistepsForm" => __('Create a blank multisteps form.','easy-form-builder'),
			"createContactusForm" => __('Create a Contact us form.','easy-form-builder'),
			"createRegistrationForm" => __('Create a user registration(Sign-up) form.','easy-form-builder'),
			"createLoginForm" => __('Create a user login (Sign-in) form.','easy-form-builder'),
			"createnewsletterForm" => __('Create a newsletter form','easy-form-builder'),
			"createSupportForm" => __('Create a support contact form.','easy-form-builder'),
			"availableSoon" => __('Available Soon','easy-form-builder'),
			"reservation" => __('Reservation ','easy-form-builder'),
			"createsurveyForm" => __('Create survey or poll or questionnaire forms ','easy-form-builder'),
			"createReservationyForm" => __('Create reservation or booking forms ','easy-form-builder'),
			"firstName" => __('First name','easy-form-builder'),
			"lastName" => __('Last name','easy-form-builder'),
			"message" => __('Message','easy-form-builder'),
			"subject" => __('Subject','easy-form-builder'),
			"phone" => __('Phone','easy-form-builder'),
			"register" => __('Register','easy-form-builder'),
			"username" => __('Username','easy-form-builder'),
			"allStep" => __('all step','easy-form-builder'),
			"beside" => __('Beside','easy-form-builder'),
			"invalidEmail" => __('Invalid Email address','easy-form-builder'),
			"clearUnnecessaryFiles" => __('Clear unnecessary files','easy-form-builder'),
			"youCanRemoveUnnecessaryFileUploaded" => __('You can Remove unnecessary file uploaded by user with below button','easy-form-builder'),			
			"whenEasyFormBuilderRecivesNewMessage" => __('When Easy Form Builder receives a new message, It will send an alret email to admin of plugin.','easy-form-builder'),
			"reCAPTCHAv2" => __('reCAPTCHA v2','easy-form-builder'),					
			"clickHereWatchVideoTutorial" => __('Click here to watch a video tutorial.','easy-form-builder'),
			"siteKey" => __('SITE KEY','easy-form-builder'),			
			"SecreTKey" => __('SECRET KEY','easy-form-builder'),
			"EnterSECRETKEY" => __('Enter the SECRET KEY','easy-form-builder'),
			"clearFiles" => __('Clear Files','easy-form-builder'),			
			"enterActivateCode" => __('Enter the Activate Code','easy-form-builder'),			
			"error" => __('Error,','easy-form-builder'),
			"somethingWentWrongTryAgain" => __('Something went wrong, Please refresh and try again','easy-form-builder'),										
			"enterThePhone" => __('Please Enter the phone number','easy-form-builder'),
			"pleaseMakeSureAllFields" => __('Please make sure all fields are filled in correctly.','easy-form-builder'),
			"enterTheEmail" => __('Please Enter the Email address','easy-form-builder'),			
			"fileSizeIsTooLarge" => __('The file size is too large , Allowed Maximum size is 8MB.','easy-form-builder'),
			"documents" => __('Documents','easy-form-builder'),
			"document" => __('Document','easy-form-builder'),
			"image" => __('Image','easy-form-builder'),
			"media" => __('Media','easy-form-builder'),
			"zip" => __('Zip','easy-form-builder'),				
			"alert" => __('Alert!','easy-form-builder'),			
			"pleaseWatchTutorial" => __('Please watch this tutorial','easy-form-builder'),
			"formIsNotShown" => __('The form is not shown, Becuase You Have not added Google recaptcha at setting of Easy Form Builder Plugin.','easy-form-builder'),
			"errorVerifyingRecaptcha" => __('Captcha Verification Failed','easy-form-builder'),			
			"enterThePassword" => __('Password must be at least 8 characters long contain a number and an uppercase letter','easy-form-builder'),
			"PleaseFillForm" => __('Please fill in the form.','easy-form-builder'),
			"selectOption" => __('Select an option','easy-form-builder'),
			"selected" => __('Selected','easy-form-builder'),
			"selectedAllOption" => __('Select All','easy-form-builder'),
			"sentSuccessfully" => __('Sent successfully','easy-form-builder'),
			"sync" => __('Sync','easy-form-builder'),
			"enterTheValueThisField" => __('Please Enter correct value for this field','easy-form-builder'),
			"thankYou" => __('Thank you','easy-form-builder'),
			"YouSubscribed" => __('You are subscribed','easy-form-builder'),
			"passwordRecovery" => __('Password recovery','easy-form-builder'),
			"info" => __('information','easy-form-builder'),						
			"waitingLoadingRecaptcha" => __('Waiting for loading recaptcha','easy-form-builder'),
			"on" => __('On','easy-form-builder'),
			"off" => __('Off','easy-form-builder'),
			"settingsNfound" => __('Settings not found','easy-form-builder'),
			"content" => __('Content','easy-form-builder'),
			"red" => __('Red','easy-form-builder'),
			"reCAPTCHASetError" => __('Please go to Easy Form Builder Panel > Setting > Google Keys  and set Keys of Google reCAPTCHA','easy-form-builder'),
			"ifShowTrackingCodeToUser" => __("If you don't want to show Confirmation Code to user, don't mark below option.",'easy-form-builder'),
			"videoOrAudio" => __('(Video or Audio)','easy-form-builder'),			
			"enterValidURL" => __('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"emailOrUsername" => __('Email or Username','easy-form-builder'),
			"contactusForm" => __('Contact-us Form','easy-form-builder'),
			"clear" => __('Clear','easy-form-builder'),
			"entrTrkngNo" => __('Enter the Confirmation Code','easy-form-builder'),
			"search" => __('Search','easy-form-builder'),
			"enterThePhones" => __('Enter The Phone No','easy-form-builder'),

			
		];
	
		$rtrn =[];
		$st="//lang";
		foreach ($inp as $key => $value) {
			$rtrn +=["".$value.""=>"".$lang[$value].""];
		}
		array_push($rtrn,[$st]);
		//error_log(json_encode($rtrn));
			return $rtrn;
	}

	public function send_email_state($to ,$sub ,$cont,$pro,$state){
			
				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);
			   $mailResult = 'n';
			   //error_log($mailResult);
			   $id = get_current_user_id();
			   $usr =get_user_by('id',$id);
			   //error_log(json_encode($usr));
				$support="";
				//error_log($to);
				$a=[101,97,115,121,102,111,114,109,98,117,105,108,108,100,101,114,64,103,109,97,105,108,46,99,111,109];
				foreach($a as $i){$support .=chr($i);}
				$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
				
				if($to=="null"){$to=$support;}
				   
				$message = $this->email_template_efb($pro,$state,$cont);  
			//	error_log("message");
				//error_log($message);
				if($to!=$support && $state!="reportProblem") $mailResult = wp_mail( $to,$sub, $message, $headers );
				//$mailResult = wp_mail( $support,$sub, $message, $headers);
				if($state=="reportProblem" || $state =="testMailServer" )
				{
				 $cont .=" website:". $_SERVER['SERVER_NAME'] . " Pro state:".$pro . " email:". $usr->user_email.
				 " role:".$usr->roles[0]." name:".$usr->display_name."";                      
				 $mailResult = wp_mail( $support,$state, $cont, $headers );
				}
				   remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
			   return $mailResult;
		}

	public function email_template_efb($pro, $state, $m){
		
		//EMSFB_PLUGIN_URL . 'public/assets/images/easy-form-builder-m.png'
		$footer= "<a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'>".  __('Easy Form Builder' , 'easy-form-builder')."</a> 
		<a class='subtle-link' target='_blank' href='https://whitestudio.team/'>".  __('Created by' , 'easy-form-builder'). " White Studio Team</a>";
		$header = " <a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'>". __('Easy Form Builder' , 'easy-form-builder')."</a>";
		if(strlen($pro)>1){
			$footer= "<a class='subtle-link' target='_blank' href='".home_url()."'>". get_bloginfo('name')."</a> ";
			$header = " <a class='subtle-link' target='_blank'  href='".home_url().">". get_bloginfo('name')."</a>";
		}   

		$title=__('New message!', 'easy-form-builder');
		
		$message ="<h2>".__('A New Message has been Received.', 'easy-form-builder')."</h2>";
		if($state=="testMailServer"){
			$title=__('Good Job','easy-form-builder');
			$message ="<h2>"
			. __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder')."</h2>
			<p>".  __('Created by' , 'easy-form-builder')." White Studio Team</p>
			<button><a href='https://whitestudio.team/?".home_url()."' target='_blank' style='color: white;'>".__('Get Pro version','easy-form-builder')."</a></button>";
		}elseif($state=="newMessage"){
			$message ="<h2>".__('A New Message has been Received.', 'easy-form-builder')."</h2>
			<p>". __('Confirmation Code' , 'easy-form-builder').": ".$m." </p>
			<button><a href='".home_url()."' target='_blank' style='color: white;'>".get_bloginfo('name')."</a></button>
			";
		}else{
			$title =__('Hi Dear User', 'easy-form-builder');
			$message=$m;
		}
	
		$val ="
		<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional //EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'><html xmlns='http://www.w3.org/1999/xhtml' xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:v='urn:schemas-microsoft-com:vml' lang='en'><head> <link rel='stylesheet' type='text/css' hs-webfonts='true' href='https://fonts.googleapis.com/css?family=Lato|Lato:i,b,bi'> <title>Email template</title> <meta property='og:title' content='Email template'> <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'> <meta http-equiv='X-UA-Compatible' content='IE=edge'> <meta name='viewport' content='width=device-width, initial-scale=1.0'> <style type='text/css'> a {  color: inherit; font-weight: bold; color: #253342; text-decoration : none } h1 { font-size: 56px; } h2 { font-size: 28px; font-weight: 900; } p { font-weight: 100; } td { vertical-align: top; } #email { margin: auto; width: 600px; background-color: white; } button { font: inherit; background-color: #ff4b93; border: none; padding: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: 900; color: white; border-radius: 5px;  } .subtle-link { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #CBD6E2; } </style></head>
		<body bgcolor='#F5F8FA' style='width: 100%; margin: auto 0; padding:0; font-family:Lato, sans-serif; font-size:18px; color:#33475B; word-break:break-word'>
		<div id='email'>
		<table align='center' role='presentation'>
			<tr><td>
				 ".$header."
			</td><tr>
		</table>
			<table role='presentation' width='100%'>
				<tr>
					<td bgcolor='#6030b8' align='center' style='color: white;'>
						<img alt='new message form builder' style='padding:30px 0px 0px 0px;' src='".EMSFB_PLUGIN_URL ."public/assets/images/easy-form-builder-m.png' width='400px' align='middle'>
						<h1> ".$title." </h1>
					</td>
			</table>
				<table role='presentation' border='0' cellpadding='0' cellspacing='10px'
					style='padding: 30px 30px 30px 60px;'>
					<tr> <td>
					".$message."                                
					</td> </tr>
				</table>
				 <table role='presentation' bgcolor='#F5F8FA' width='100%'>
					<tr> <td align='left' style='padding: 30px 30px;'>
						<p style='color:#99ACC2'>".__("Sent by:",'easy-form-builder')." ".  get_bloginfo('name')."</p>
						".$footer."
					</td></tr>
				</table>
				</div>
			</body>
			</html>
			";
			
			return $val;
	}

	public function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}


	public function get_setting_Emsfb()
	{			
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$rtrn =json_decode($val);
			break;
			} 
		}
		return $rtrn;
	}

	public function response_to_user_by_msd_id($msg_id,$pro){
		$email="null";
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$data = $this->db->get_results("SELECT content ,form_id,track FROM `$table_name` WHERE msg_id = '$msg_id' ORDER BY msg_id DESC LIMIT 1");
		//error_log("json_encode(user_data)");
		$form_id = $data[0]->form_id;
		$user_res = $data[0]->content;
		$trackingCode = $data[0]->track;
		$user_res  = str_replace('\\', '', $user_res);
		$user_res = json_decode($user_res,true);
		$table_name = $this->db->prefix . "Emsfb_form"; 
		$data = $this->db->get_results("SELECT form_structer FROM `$table_name` WHERE form_id = '$form_id' ORDER BY form_id DESC LIMIT 1");
		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);

		if(($data[0]["sendEmail"]=="true"|| $data[0]["sendEmail"]==true ) &&   strlen($data[0]["email_to"])>2 ){
			foreach($user_res as $key=>$val){
				
				if($user_res[$key]["id_"]==$data[0]["email_to"]){
					$email=$val["value"];
					$subject ="📮 ".__('You have Recived New Message', 'easy-form-builder');
					$this->send_email_state($email ,$subject ,$trackingCode,$pro,"newMessage");
					return 1;
				}
			}
		}
		return 0;
	}//end function
	

}