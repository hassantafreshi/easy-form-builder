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
		//add_action( 'upgrader_process_complete', [$this ,'wp_up_upgrade_completed_efb'], 10, 2 );
		//$this->test_Efb();
		//error_log('called function.php');
		register_activation_hook( __FILE__, [$this ,'download_all_addons_efb'] );
		add_action( 'load-index.php', [$this ,'addon_adds_cron_efb'] );
    }

	public function test_Efb(){
		 //error_log('===>test_Efb==function.php');
	

		/* if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/stripe")) {	
			error_log('not found stripe');
		}
		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {	
			error_log('not found persiadatepicker');
		}
		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {	
			error_log('not found arabicdatepicker');
		}
		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiapay")) {	
			error_log('not found persiapay');
		} */
	}
	public function text_efb($inp){
		//isset($test) ? $test:
		$ac= $this->get_setting_Emsfb();		 
		$state= $ac!=='null' && isset($ac->text) && gettype($ac->text)!='string' ? true : false ;		
		$lang = [

			"create" => $state ? $ac->text->create : __('Create','easy-form-builder'),
			"define" => $state ? $ac->text->define : __('Define','easy-form-builder'),
			"formName" => $state ? $ac->text->formName : __('Form Name','easy-form-builder'),
			"createDate" => $state ? $ac->text->createDate : __('Create Date','easy-form-builder'),
			"edit" => $state ? $ac->text->edit : __('Edit','easy-form-builder'),
			"content" => $state ? $ac->text->content : __('Content','easy-form-builder'),
			"trackNo" => $state ? $ac->text->trackNo : __('Confirmation Code','easy-form-builder'),
			"formDate" => $state ? $ac->text->formDate : __('Form Date','easy-form-builder'),
			"by" => $state ? $ac->text->by : __('By','easy-form-builder'),
			"ip" => $state ? $ac->text->ip : __('IP','easy-form-builder'),
			"guest" => $state ? $ac->text->guest : __('Guest','easy-form-builder'),			
			"response" => $state ? $ac->text->response : __('Response','easy-form-builder'),
			"date" => $state ? $ac->text->date : __('Date Picker','easy-form-builder'),
			"videoDownloadLink" => $state ? $ac->text->videoDownloadLink : __('Video Download','easy-form-builder'),
			"downloadViedo" => $state ? $ac->text->downloadViedo : __('Download Video','easy-form-builder'),
			"download" => $state ? $ac->text->download : __('Download','easy-form-builder'),
			"youCantUseHTMLTagOrBlank" => $state ? $ac->text->youCantUseHTMLTagOrBlank : __('Please avoid using HTML tags and ensure that your message is not blank.','easy-form-builder'),
			"reply" => $state ? $ac->text->reply : __('Reply','easy-form-builder'),
			"messages" => $state ? $ac->text->messages : __('Messages','easy-form-builder'),
			"pleaseWaiting" => $state ? $ac->text->pleaseWaiting : __('Please Wait','easy-form-builder'),
			"loading" => $state ? $ac->text->loading : __('Loading','easy-form-builder'),
			"remove" => $state ? $ac->text->remove : __('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => $state ? $ac->text->areYouSureYouWantDeleteItem : __('Are you sure you want to delete this?','easy-form-builder'),
			"no" => $state ? $ac->text->no : __('NO','easy-form-builder'),
			"yes" => $state ? $ac->text->yes : __('Yes','easy-form-builder'),
			//"numberOfSteps" => $state ? $ac->text->numberOfSteps : __('Number of steps','easy-form-builder'),
			//"titleOfStep" => $state ? $ac->text->titleOfStep : __('Title of step','easy-form-builder'),
			"proVersion" => $state ? $ac->text->proVersion : __('Pro Version','easy-form-builder'),
			"getProVersion" => $state ? $ac->text->getProVersion : __('Activate Pro version','easy-form-builder'),					
			"reCAPTCHA" => $state ? $ac->text->reCAPTCHA : __('reCAPTCHA','easy-form-builder'),
			//"protectsYourWebsiteFromFraud" => $state ? $ac->text->protectsYourWebsiteFromFraud : __('Click here to watch a video tutorial.','easy-form-builder'),
			"enterSITEKEY" => $state ? $ac->text->enterSITEKEY : __('SECRET KEY','easy-form-builder'),
			"alertEmail" => $state ? $ac->text->alertEmail : __('Alert Email','easy-form-builder'),
			"enterAdminEmail" => $state ? $ac->text->enterAdminEmail : __('Enter the admin email address to receive email notifications.','easy-form-builder'),
			"showTrackingCode" => $state ? $ac->text->showTrackingCode : __('Show Confirmation Code','easy-form-builder'),
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : __('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : __('Copy and paste the following shortcode to add the Confirmation Code finder to any page or post.','easy-form-builder'),
			"save" => $state ? $ac->text->save : __('Save','easy-form-builder'),
			"waiting" => $state ? $ac->text->waiting : __('Waiting','easy-form-builder'),
			"saved" => $state ? $ac->text->saved : __('Saved','easy-form-builder'),
			"stepName" => $state ? $ac->text->stepName : __('Step Name','easy-form-builder'),
			"IconOfStep" => $state ? $ac->text->IconOfStep : __('Icon of step','easy-form-builder'),
			"stepTitles" => $state ? $ac->text->stepTitles : __('Step Titles','easy-form-builder'),
			"elements" => $state ? $ac->text->elements : __('Elements:','easy-form-builder'),
			"delete" => $state ? $ac->text->delete : __('Delete','easy-form-builder'),
			"newOption" => $state ? $ac->text->newOption : __('New option','easy-form-builder'),
			"required" => $state ? $ac->text->required : __('Required','easy-form-builder'),
			"button" => $state ? $ac->text->button : __('Text','easy-form-builder'),
			"password" => $state ? $ac->text->password : __('Password','easy-form-builder'),
			"email" => $state ? $ac->text->email : __('Email','easy-form-builder'),
			"number" => $state ? $ac->text->number : __('Number','easy-form-builder'),
			"file" => $state ? $ac->text->file : __('File Upload','easy-form-builder'),
			"tel" => $state ? $ac->text->tel : __('Tel','easy-form-builder'),
			"textarea" => $state ? $ac->text->textarea : __('Long Text','easy-form-builder'),
			"checkbox" => $state ? $ac->text->checkbox : __('Check Box','easy-form-builder'),
			"radiobutton" => $state ? $ac->text->radiobutton : __('Radio Button','easy-form-builder'),
			"radio" => $state ? $ac->text->radio : __('Radio','easy-form-builder'),
			"url" => $state ? $ac->text->url : __('URL','easy-form-builder'),
			"range" => $state ? $ac->text->range : __('Range','easy-form-builder'),
			"color" => $state ? $ac->text->color : __('Color Picker','easy-form-builder'),
			"fileType" => $state ? $ac->text->fileType : __('File Type','easy-form-builder'),
			"label" => $state ? $ac->text->label : __('Label','easy-form-builder'),
			"labels" => $state ? $ac->text->labels : __('Labels','easy-form-builder'),
			"class" => $state ? $ac->text->class : __('Class','easy-form-builder'),
			"id" => $state ? $ac->text->id : __('ID','easy-form-builder'),
			"tooltip" => $state ? $ac->text->tooltip : __('Tooltip','easy-form-builder'),
			"formUpdated" => $state ? $ac->text->formUpdated : __('The Form Updated','easy-form-builder'),
			"goodJob" => $state ? $ac->text->goodJob : __('Good Job','easy-form-builder'),
			"formUpdatedDone" => $state ? $ac->text->formUpdatedDone : __('The form has been successfully updated','easy-form-builder'),
			"formIsBuild" => $state ? $ac->text->formIsBuild : __('The form is successfully built','easy-form-builder'),
			"formCode" => $state ? $ac->text->formCode : __('Form Code','easy-form-builder'),
			"close" => $state ? $ac->text->close : __('Close','easy-form-builder'),
			"done" => $state ? $ac->text->done : __('Done','easy-form-builder'),
			"demo" => $state ? $ac->text->demo : __('Demo','easy-form-builder'),			
			"pleaseFillInRequiredFields" => $state ? $ac->text->pleaseFillInRequiredFields : __('Please fill in all required fields.','easy-form-builder'),
			"availableInProversion" => $state ? $ac->text->availableInProversion : __('This option is only available in the Pro version.','easy-form-builder'),
			"formNotBuilded" => $state ? $ac->text->formNotBuilded : __('The form has not been built!','easy-form-builder'),
			"someStepsNotDefinedCheck" => $state ? $ac->text->someStepsNotDefinedCheck : __('Please check that all steps are defined before proceeding.','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => $state ? $ac->text->ifYouNeedCreateMoreThan2Steps : __('If you need to create more than 2 steps, you can activate the pro version of Easy Form Builder, which allows for unlimited steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwo" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwo : __('You can create a minimum of 1 step and a maximum of 2 steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwenty" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwenty : __('You can create a minimum of 1 step and a maximum of 20 steps.','easy-form-builder'),
			"preview" => $state ? $ac->text->preview : __('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => $state ? $ac->text->somethingWentWrongPleaseRefresh : __('Something went wrong. Please refresh the page and try again.','easy-form-builder'),
			"formNotCreated" => $state ? $ac->text->formNotCreated : __('Sorry, it seems like the form has not been created.','easy-form-builder'),
			"atFirstCreateForm" => $state ? $ac->text->atFirstCreateForm : __('Please create a form and add elements before trying again.','easy-form-builder'),
			"allowMultiselect" => $state ? $ac->text->allowMultiselect : __('Allow multi-select','easy-form-builder'),
			"DragAndDropUI" => $state ? $ac->text->DragAndDropUI : __('Drag and drop UI','easy-form-builder'),
			"clickHereForActiveProVesrsion" => $state ? $ac->text->clickHereForActiveProVesrsion : __('Click here for Active Pro version','easy-form-builder'),
			"selectOpetionDisabled" => $state ? $ac->text->selectOpetionDisabled : __('Choose an option (not available in test view)','easy-form-builder'),
			"pleaseEnterTheTracking" => $state ? $ac->text->pleaseEnterTheTracking : __('Please enter the Confirmation Code','easy-form-builder'),									
			"formNotFound" => $state ? $ac->text->formNotFound : __('Form not found.','easy-form-builder'),
			"errorV01" => $state ? $ac->text->errorV01 : __('Oops, V01 Error occurred.','easy-form-builder'),
			"password8Chars" => $state ? $ac->text->password8Chars : __('Password should be at least 8 characters long.','easy-form-builder'),
			"registered" => $state ? $ac->text->registered : __('Registered','easy-form-builder'),
			"yourInformationRegistered" => $state ? $ac->text->yourInformationRegistered : __('Your information is successfully registered','easy-form-builder'),
			"youNotPermissionUploadFile" => $state ? $ac->text->youNotPermissionUploadFile : __('You do not have permission to upload this file:','easy-form-builder'),
			"pleaseUploadA" => $state ? $ac->text->pleaseUploadA : __('Please upload the','easy-form-builder'),
			"please" => $state ? $ac->text->please : __('Please','easy-form-builder'),
			"trackingForm" => $state ? $ac->text->trackingForm : __('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => $state ? $ac->text->trackingCodeIsNotValid : __('The confirmation Code is not valid.','easy-form-builder'),
			"checkedBoxIANotRobot" => $state ? $ac->text->checkedBoxIANotRobot : __('Please Checked Box of I am Not robot','easy-form-builder'),
			"howConfigureEFB" => $state ? $ac->text->howConfigureEFB : __('How to configure Easy Form Builder','easy-form-builder'),
			"howGetGooglereCAPTCHA" => $state ? $ac->text->howGetGooglereCAPTCHA : __('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
			"howActivateAlertEmail" => $state ? $ac->text->howActivateAlertEmail : __('How to activate the alert email for new form submission','easy-form-builder'),
			"howCreateAddForm" => $state ? $ac->text->howCreateAddForm : __('How to create and add a form with Easy Form Builder','easy-form-builder'),
			"howActivateTracking" => $state ? $ac->text->howActivateTracking : __('How to activate a Confirmation Code in Easy Form Builder','easy-form-builder'),
			"howWorkWithPanels" => $state ? $ac->text->howWorkWithPanels : __('How to work with panels in Easy Form Builder','easy-form-builder'),
			"points" => $state ? $ac->text->points : __('points','easy-form-builder'),
			"howAddTrackingForm" => $state ? $ac->text->howAddTrackingForm : __('How to add The Confirmation Code Finder to a post, page, or custom post type','easy-form-builder'),//here
			"howFindResponse" => $state ? $ac->text->howFindResponse : __('How to find a specific submission using the Confirmation Code','easy-form-builder'),
			"pleaseEnterVaildValue" => $state ? $ac->text->pleaseEnterVaildValue : __('Please enter a valid value','easy-form-builder'),
			"step" => $state ? $ac->text->step : __('Step','easy-form-builder'),
			"advancedCustomization" => $state ? $ac->text->advancedCustomization : __('Advanced customization','easy-form-builder'),
			"orClickHere" => $state ? $ac->text->orClickHere : __(' or click here','easy-form-builder'),
			"downloadCSVFile" => $state ? $ac->text->downloadCSVFile : __(' Download CSV file','easy-form-builder'),
			"downloadCSVFileSub" => $state ? $ac->text->downloadCSVFileSub : __(' Download subscriptions CSV.','easy-form-builder'),
			"login" => $state ? $ac->text->login : __('Login','easy-form-builder'),
			"thisInputLocked" => $state ? $ac->text->thisInputLocked : __('this input is locked','easy-form-builder'),
			"thisElemantAvailableRemoveable" => $state ? $ac->text->thisElemantAvailableRemoveable : __('This element is available and removable.','easy-form-builder'),
			"thisElemantWouldNotRemoveableLoginform" => $state ? $ac->text->thisElemantWouldNotRemoveableLoginform : __('This element cannot be removed from the Login form.','easy-form-builder'),
			"send" => $state ? $ac->text->send : __('Send','easy-form-builder'),
			"contactUs" => $state ? $ac->text->contactUs : __('Contact us','easy-form-builder'),
			"support" => $state ? $ac->text->support : __('Support','easy-form-builder'),
			"subscribe" => $state ? $ac->text->subscribe : __('Subscribe','easy-form-builder'),
			"logout" => $state ? $ac->text->logout : __('Logout','easy-form-builder'),
			"survey" => $state ? $ac->text->survey : __('Survey','easy-form-builder'),
			"chart" => $state ? $ac->text->chart : __('Chart','easy-form-builder'),
			"noComment" => $state ? $ac->text->noComment : __('No comment','easy-form-builder'),
			"easyFormBuilder" => $state ? $ac->text->easyFormBuilder : __('Easy Form Builder','easy-form-builder'),			
			"byWhiteStudioTeam" => $state ? $ac->text->byWhiteStudioTeam : __('By WhiteStudio.team','easy-form-builder'),
			"createForms" =>  $state ? $ac->text->createForms :  __('Create Forms','easy-form-builder'),
			"tutorial" => $state ? $ac->text->tutorial : __('Tutorial','easy-form-builder'),
			"forms" => $state ? $ac->text->forms : __('Forms','easy-form-builder'),
			"tobeginSentence" => $state ? $ac->text->tobeginSentence : __('To get started, simply create a form using the Easy Form Builder Plugin. Click the button below to create a form.','easy-form-builder'),
			"efbIsTheUserSentence" => $state ? $ac->text->efbIsTheUserSentence : __('Easy Form Builder is an intuitive and user-friendly tool that lets you create custom, multi-step forms in just minutes, without requiring any coding skills.','easy-form-builder'),
			"efbYouDontNeedAnySentence" => $state ? $ac->text->efbYouDontNeedAnySentence : __(' You don not have to be a coding expert to use Easy Form Builder. Simply drag and drop the fields to create customized multistep forms easily. Plus, you can connect each submission to a unique request using the Confirmation Code feature.','easy-form-builder'),
			"newResponse" => $state ? $ac->text->newResponse : __('New Response','easy-form-builder'),
			"read" => $state ? $ac->text->read : __('Read','easy-form-builder'),
			"copy" => $state ? $ac->text->copy : __('Copy','easy-form-builder'),
			"general" => $state ? $ac->text->general : __('General','easy-form-builder'),
			"dadFieldHere" => $state ? $ac->text->dadFieldHere : __('Drag & Drop Fields Here','easy-form-builder'),
			"help" => $state ? $ac->text->help : __('Help','easy-form-builder'),
			"setting" => $state ? $ac->text->setting : __('Setting','easy-form-builder'),
			"maps" => $state ? $ac->text->maps : __('Maps','easy-form-builder'),
			"youCanFindTutorial" => $state ? $ac->text->youCanFindTutorial : __('Find video tutorials in the adjacent box and click the document button for tutorials and articles.','easy-form-builder'),
			"proUnlockMsg" => $state ? $ac->text->proUnlockMsg : __('Activate Pro version for more features and unlimited access to the all plugin services.','easy-form-builder'),
			"aPIKey" => $state ? $ac->text->aPIKey : __('API KEY','easy-form-builder'),
			"youNeedAPIgMaps" => $state ? $ac->text->youNeedAPIgMaps : __('Your form needs an API key for Google Maps to work properly.','easy-form-builder'),
			"copiedClipboard" => $state ? $ac->text->copiedClipboard : __('Copied to Clipboard','easy-form-builder'),
			"noResponse" => $state ? $ac->text->noResponse : __('No Response','easy-form-builder'),
			"offerGoogleCloud" => $state ? $ac->text->offerGoogleCloud : __('To use reCAPTCHA and location picker (Maps), sign up for the Google Cloud service and receive $350 worth of credits exclusively for our users ','easy-form-builder'),
			"getOfferTextlink" => $state ? $ac->text->getOfferTextlink : __(' Get credits by clicking here.','easy-form-builder'),
			"clickHere" => $state ? $ac->text->clickHere : __('Click here','easy-form-builder'),
			"SpecialOffer" => $state ? $ac->text->SpecialOffer : __('Special offer','easy-form-builder'),
			"googleKeys" => $state ? $ac->text->googleKeys : __('Google Keys','easy-form-builder'),
			"emailServer" => $state ? $ac->text->emailServer : __('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : __('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'),
			"emailSetting" => $state ? $ac->text->emailSetting : __('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : __('Check Email Server','easy-form-builder'),
			"dadfile" => $state ? $ac->text->dadfile : __('D&D File Upload','easy-form-builder'),
			"field" => $state ? $ac->text->field : __('Field','easy-form-builder'),
			"advanced" => $state ? $ac->text->advanced : __('Advanced','easy-form-builder'),
			"switch" => $state ? $ac->text->switch : __('Switch','easy-form-builder'),
			"locationPicker" => $state ? $ac->text->locationPicker : __('Location Picker','easy-form-builder'),
			"rating" => $state ? $ac->text->rating : __('Rating','easy-form-builder'),
			"esign" => $state ? $ac->text->esign : __('Signature','easy-form-builder'),
			"yesNo" => $state ? $ac->text->yesNo : __('Yes/No','easy-form-builder'),
			"htmlCode" => $state ? $ac->text->htmlCode : __('HTML Code','easy-form-builder'),
			"pcPreview" => $state ? $ac->text->pcPreview : __('PC Preview','easy-form-builder'),
			"youDoNotAddAnyInput" => $state ? $ac->text->youDoNotAddAnyInput : __('You have not added any fields.','easy-form-builder'),
			"copyShortcode" => $state ? $ac->text->copyShortcode : __('Copy ShortCode','easy-form-builder'),
			"shortcode" => $state ? $ac->text->shortcode : __('ShortCode','easy-form-builder'),
			"copyTrackingcode" => $state ? $ac->text->copyTrackingcode : __('Copy Confirmation Code','easy-form-builder'),
			"previewForm" => $state ? $ac->text->previewForm : __('Preview Form','easy-form-builder'),
			"activateProVersion" => $state ? $ac->text->activateProVersion : __('Activate Pro Now','easy-form-builder'),
			"itAppearedStepsEmpty" => $state ? $ac->text->itAppearedStepsEmpty : __('It seems that some of the steps in your form are empty. Please add field to all steps before saving.','easy-form-builder'),
			"youUseProElements" => $state ? $ac->text->youUseProElements : __('You are using the pro field in the form. For save and using the form included pro fields, activate Pro version.','easy-form-builder'),
			"sampleDescription" => $state ? $ac->text->sampleDescription : __('Sample description','easy-form-builder'),
			"fieldAvailableInProversion" => $state ? $ac->text->fieldAvailableInProversion : __('This feature is only available in the Pro of Easy Form Builder.','easy-form-builder'),
			"editField" => $state ? $ac->text->editField : __('Edit Field','easy-form-builder'),
			"description" => $state ? $ac->text->description : __('Description','easy-form-builder'),
			"descriptions" => $state ? $ac->text->descriptions : __('Descriptions','easy-form-builder'),
			"thisEmailNotificationReceive" => $state ? $ac->text->thisEmailNotificationReceive : __('Enable email notifications','easy-form-builder'),
			"activeTrackingCode" => $state ? $ac->text->activeTrackingCode : __('Show Confirmation Code','easy-form-builder'),
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : __('Add Google reCAPTCHA to the form ','easy-form-builder'),
			"dontShowIconsStepsName" => $state ? $ac->text->dontShowIconsStepsName : __('Hide icons and step names.','easy-form-builder'),
			"dontShowProgressBar" => $state ? $ac->text->dontShowProgressBar : __('Hide progress bar','easy-form-builder'),
			"showTheFormTologgedUsers" => $state ? $ac->text->showTheFormTologgedUsers : __('Private form','easy-form-builder'),
			"labelSize" => $state ? $ac->text->labelSize : __('Label size','easy-form-builder'),
			"default" => $state ? $ac->text->default : __('Default','easy-form-builder'),
			"small" => $state ? $ac->text->small : __('Small','easy-form-builder'),
			"large" => $state ? $ac->text->large : __('Large','easy-form-builder'),
			"xlarge" => $state ? $ac->text->xlarge : __('XLarge','easy-form-builder'),
			"xxlarge" => $state ? $ac->text->xxlarge : __('XXLarge','easy-form-builder'),
			"xxxlarge" => $state ? $ac->text->xxxlarge : __('XXXLarge','easy-form-builder'),
			"labelPostion" => $state ? $ac->text->labelPostion : __('Label Position','easy-form-builder'),
			"align" => $state ? $ac->text->align : __('Align','easy-form-builder'),
			"left" => $state ? $ac->text->left : __('Left','easy-form-builder'),
			"center" => $state ? $ac->text->center : __('Center','easy-form-builder'),
			"right" => $state ? $ac->text->right : __('Right','easy-form-builder'),
			"width" => $state ? $ac->text->width : __('Width','easy-form-builder'),
			"cSSClasses" => $state ? $ac->text->cSSClasses : __('CSS Classes','easy-form-builder'),
			"defaultValue" => $state ? $ac->text->defaultValue : __('Default value','easy-form-builder'),
			"placeholder" => $state ? $ac->text->placeholder : __('Placeholder','easy-form-builder'),
			"enterAdminEmailReceiveNoti" => $state ? $ac->text->enterAdminEmailReceiveNoti : __('Enter admin email for email notifications.','easy-form-builder'),
			"corners" => $state ? $ac->text->corners : __('Corners','easy-form-builder'),
			"rounded" => $state ? $ac->text->rounded : __('Rounded','easy-form-builder'),
			"square" => $state ? $ac->text->square : __('Square','easy-form-builder'),
			"icon" => $state ? $ac->text->icon : __('Icon','easy-form-builder'),
			"icons" => $state ? $ac->text->icon : __('Icons','easy-form-builder'),
			"buttonColor" => $state ? $ac->text->buttonColor : __('Button color','easy-form-builder'),
			"buttonColors" => $state ? $ac->text->buttonColors : __('Buttons colors','easy-form-builder'),
			"blue" => $state ? $ac->text->blue : __('Blue','easy-form-builder'),
			"darkBlue" => $state ? $ac->text->darkBlue : __('Dark Blue','easy-form-builder'),
			"lightBlue" => $state ? $ac->text->lightBlue : __('Light Blue','easy-form-builder'),
			"grayLight" => $state ? $ac->text->grayLight : __('Gray Light','easy-form-builder'),
			"grayLighter" => $state ? $ac->text->grayLighter : __('Gray Lighter','easy-form-builder'),
			"green" => $state ? $ac->text->green : __('Green','easy-form-builder'),
			"pink" => $state ? $ac->text->pink : __('Pink','easy-form-builder'),
			"yellow" => $state ? $ac->text->yellow : __('Yellow','easy-form-builder'),
			"light" => $state ? $ac->text->light : __('Light','easy-form-builder'),
			"Red" => $state ? $ac->text->Red : __('red','easy-form-builder'),
			"grayDark" => $state ? $ac->text->grayDark : __('Gray Dark','easy-form-builder'),
			"white" => $state ? $ac->text->white : __('White','easy-form-builder'),
			"clr" => $state ? $ac->text->clr : __('Color','easy-form-builder'),
			"borderColor" => $state ? $ac->text->borderColor : __('Border Color','easy-form-builder'),
			"height" => $state ? $ac->text->height : __('Height','easy-form-builder'),
			"name" => $state ? $ac->text->name : __('Name','easy-form-builder'),
			"latitude" => $state ? $ac->text->latitude : __('Latitude','easy-form-builder'),
			"longitude" => $state ? $ac->text->longitude : __('Longitude','easy-form-builder'),
			"exDot" => $state ? $ac->text->exDot : __('e.g.','easy-form-builder'),
			"pleaseDoNotAddJsCode" => $state ? $ac->text->pleaseDoNotAddJsCode : __('(Avoid adding JavaScript or jQuery codes to HTML for security reasons.)','easy-form-builder'),
			"button1Value" => $state ? $ac->text->button1Value : __('Button 1 value','easy-form-builder'),
			"button2Value" => $state ? $ac->text->button2Value : __('Button 2 value','easy-form-builder'),
			"iconList" => $state ? $ac->text->iconList : __('Icons list','easy-form-builder'),
			"previous" => $state ? $ac->text->previous : __('Previous','easy-form-builder'),
			"next" => $state ? $ac->text->next : __('Next','easy-form-builder'),
			"noCodeAddedYet" => $state ? $ac->text->noCodeAddedYet : __('The code has not yet been added. Click on','easy-form-builder'),
			"andAddingHtmlCode" => $state ? $ac->text->andAddingHtmlCode : __('and adding HTML code.','easy-form-builder'),
			//"proMoreStep" => $state ? $ac->text->proMoreStep : __('When you activate the Pro version, so you can create unlimited form steps.','easy-form-builder'),
			"aPIkeyGoogleMapsError" => $state ? $ac->text->aPIkeyGoogleMapsError : __('The API key for Google Maps has not been added. Please go to Easy Form Builder > Panel > Setting > Google Keys, add the API key for Google Maps, and try again.','easy-form-builder'),
			"howToAddGoogleMap" => $state ? $ac->text->howToAddGoogleMap : __('How to Add Google maps to Easy form Builder WordPress Plugin','easy-form-builder'),
			"deletemarkers" => $state ? $ac->text->deletemarkers : __('Delete markers','easy-form-builder'),
			"updateUrbrowser" => $state ? $ac->text->updateUrbrowser : __('update your browser','easy-form-builder'),
			"stars" => $state ? $ac->text->stars : __('Stars','easy-form-builder'),
			"nothingSelected" => $state ? $ac->text->nothingSelected : __('Nothing selected','easy-form-builder'),
			"duplicate" => $state ? $ac->text->duplicate : __('Duplicate','easy-form-builder'),
			"availableProVersion" => $state ? $ac->text->availableProVersion : __('Available in the Pro version','easy-form-builder'),
			"mobilePreview" => $state ? $ac->text->mobilePreview : __('Mobile Preview','easy-form-builder'),
			"thanksFillingOutform" => $state ? $ac->text->thanksFillingOutform : __('Thanks for filling out the form.','easy-form-builder'),
			"finish" => $state ? $ac->text->finish : __('Finish','easy-form-builder'),
			"dragAndDropA" => $state ? $ac->text->dragAndDropA : __('Drag & Drop the','easy-form-builder'),
			"browseFile" => $state ? $ac->text->browseFile : __('Browse File','easy-form-builder'),
			"removeTheFile" => $state ? $ac->text->removeTheFile : __('Remove the file','easy-form-builder'),
			"enterAPIKey" => $state ? $ac->text->enterAPIKey : __('Enter API KEY','easy-form-builder'),
			"formSetting" => $state ? $ac->text->formSetting : __('Form Settings','easy-form-builder'),
			"select" => $state ? $ac->text->select : __('Select','easy-form-builder'),
			"up" => $state ? $ac->text->up : __('Up','easy-form-builder'),
			"sending" => $state ? $ac->text->sending : __('Sending','easy-form-builder'),
			"enterYourMessage" => $state ? $ac->text->enterYourMessage : __('Please Enter your message','easy-form-builder'),
			"add" => $state ? $ac->text->add : __('Add','easy-form-builder'),
			"code" => $state ? $ac->text->code : __('Code','easy-form-builder'),
			"star" => $state ? $ac->text->star : __('Star','easy-form-builder'),
			"form" => $state ? $ac->text->form : __('Form','easy-form-builder'),
			"black" => $state ? $ac->text->black : __('Black','easy-form-builder'),
			"pleaseReporProblem" => $state ? $ac->text->pleaseReporProblem : __('Please kindly report the following issue to the Easy Form Builder team.','easy-form-builder'),
			"reportProblem" => $state ? $ac->text->reportProblem : __('Report problem','easy-form-builder'),
			"ddate" => $state ? $ac->text->ddate : __('Date','easy-form-builder'),
			"serverEmailAble" => $state ? $ac->text->serverEmailAble : __('Your server is capable of sending emails','easy-form-builder'),
			"sMTPNotWork" => $state ? $ac->text->sMTPNotWork : __('SMTP Error: The host is unable to send an email. Please contact the host is support team for assistance.','easy-form-builder'),
			
			"aPIkeyGoogleMapsFeild" => $state ? $ac->text->aPIkeyGoogleMapsFeild : __('There was an error loading Google Maps.','easy-form-builder'),
			"fileIsNotRight" => $state ? $ac->text->fileIsNotRight : __('The uploaded file is not in the correct file format.','easy-form-builder'),
			"thisElemantNotAvailable" => $state ? $ac->text->thisElemantNotAvailable : __('The selected field is not available in this type of form.','easy-form-builder'),
			"numberSteps" => $state ? $ac->text->numberSteps : __('Edit','easy-form-builder'),
			"clickHereGetActivateCode" => $state ? $ac->text->clickHereGetActivateCode : __('Get your activation code now and unlock exclusive features ! Click here.','easy-form-builder'),			
			"trackingCode" => $state ? $ac->text->trackingCode : __('Confirmation Code','easy-form-builder'),
			"text" => $state ? $ac->text->text : __('Text','easy-form-builder'),
			"multiselect" => $state ? $ac->text->multiselect : __('Multiple Select','easy-form-builder'),
			"newForm" => $state ? $ac->text->newForm : __('New Form','easy-form-builder'),
			"registerForm" => $state ? $ac->text->registerForm : __('Register Form','easy-form-builder'),
			"loginForm" => $state ? $ac->text->loginForm : __('Login Form','easy-form-builder'),
			"subscriptionForm" => $state ? $ac->text->subscriptionForm : __('Subscription Form','easy-form-builder'),
			"supportForm" => $state ? $ac->text->supportForm : __('Support Form','easy-form-builder'),
			"createBlankMultistepsForm" => $state ? $ac->text->createBlankMultistepsForm : __('Create a blank multisteps form.','easy-form-builder'),
			"createContactusForm" => $state ? $ac->text->createContactusForm : __('Create a Contact us form.','easy-form-builder'),
			"createRegistrationForm" => $state ? $ac->text->createRegistrationForm : __('Create a user registration(Sign-up) form.','easy-form-builder'),
			"createLoginForm" => $state ? $ac->text->createLoginForm : __('Create a user login (Sign-in) form.','easy-form-builder'),
			"createnewsletterForm" => $state ? $ac->text->createnewsletterForm : __('Create a newsletter form','easy-form-builder'),
			"createSupportForm" => $state ? $ac->text->createSupportForm : __('Create a support contact form.','easy-form-builder'),			
			"availableSoon" => $state ? $ac->text->availableSoon : __('Available Soon','easy-form-builder'),
			"reservation" => $state ? $ac->text->reservation : __('Reservation ','easy-form-builder'),
			"createsurveyForm" => $state ? $ac->text->createsurveyForm : __('Create survey or poll or questionnaire forms ','easy-form-builder'),
			"createReservationyForm" => $state ? $ac->text->createReservationyForm : __('Create reservation or booking forms ','easy-form-builder'),
			"firstName" => $state ? $ac->text->firstName : __('First name','easy-form-builder'),
			"lastName" => $state ? $ac->text->lastName : __('Last name','easy-form-builder'),
			"message" => $state ? $ac->text->message : __('Message','easy-form-builder'),
			"subject" => $state ? $ac->text->subject : __('Subject','easy-form-builder'),
			"phone" => $state ? $ac->text->phone : __('Phone','easy-form-builder'),
			"register" => $state ? $ac->text->register : __('Register','easy-form-builder'),
			"username" => $state ? $ac->text->username : __('Username','easy-form-builder'),
			"allStep" => $state ? $ac->text->allStep : __('all step','easy-form-builder'),
			"beside" => $state ? $ac->text->beside : __('Beside','easy-form-builder'),
			"invalidEmail" => $state ? $ac->text->invalidEmail : __('Invalid Email address','easy-form-builder'),
			"clearUnnecessaryFiles" => $state ? $ac->text->clearUnnecessaryFiles : __('Delete unnecessary files.','easy-form-builder'),
			"youCanRemoveUnnecessaryFileUploaded" => $state ? $ac->text->youCanRemoveUnnecessaryFileUploaded : __('You can delete unnecessary files uploaded by users using the button below.','easy-form-builder'),			
			"whenEasyFormBuilderRecivesNewMessage" => $state ? $ac->text->whenEasyFormBuilderRecivesNewMessage : __('When a new message is received through Easy Form Builder, an alert email will be sent to the plugin admin.','easy-form-builder'),
			"reCAPTCHAv2" => $state ? $ac->text->reCAPTCHAv2 : __('reCAPTCHA v2','easy-form-builder'),					
			"clickHereWatchVideoTutorial" => $state ? $ac->text->clickHereWatchVideoTutorial : __('Click here to watch a video tutorial.','easy-form-builder'),
			"siteKey" => $state ? $ac->text->siteKey : __('SITE KEY','easy-form-builder'),			
			"SecreTKey" => $state ? $ac->text->SecreTKey : __('SECRET KEY','easy-form-builder'),
			"EnterSECRETKEY" => $state ? $ac->text->EnterSECRETKEY : __('Enter a Secret Key','easy-form-builder'),
			"clearFiles" => $state ? $ac->text->clearFiles : __('Clear Files','easy-form-builder'),			
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : __('Enter the activate code','easy-form-builder'),			
			"error" => $state ? $ac->text->error : __('Error','easy-form-builder'),
			"somethingWentWrongTryAgain" => $state ? $ac->text->somethingWentWrongTryAgain : __('Something unexpected happened. Please try again by refreshing the page.','easy-form-builder'),										
			"enterThePhone" => $state ? $ac->text->enterThePhone : __('Please enter a valid phone number.','easy-form-builder'),
			"pleaseMakeSureAllFields" => $state ? $ac->text->pleaseMakeSureAllFields : __('Please ensure that all fields are filled correctly.','easy-form-builder'),
			"enterTheEmail" => $state ? $ac->text->enterTheEmail : __('Please enter an email address.','easy-form-builder'),			
			"fileSizeIsTooLarge" => $state ? $ac->text->fileSizeIsTooLarge : __('The file size exceeds the maximum allowed limit of 8MB','easy-form-builder'),
			"documents" => $state ? $ac->text->documents : __('Documents','easy-form-builder'),
			"document" => $state ? $ac->text->document : __('Document','easy-form-builder'),
			"image" => $state ? $ac->text->image : __('Image','easy-form-builder'),
			"media" => $state ? $ac->text->media : __('Media','easy-form-builder'),
			"zip" => $state ? $ac->text->zip : __('Zip','easy-form-builder'),				
			"alert" => $state ? $ac->text->alert : __('Alert!','easy-form-builder'),			
			"pleaseWatchTutorial" => $state ? $ac->text->pleaseWatchTutorial : __('We recommend watching this tutorial for assistance.','easy-form-builder'),
			"formIsNotShown" => $state ? $ac->text->formIsNotShown : __('The form is not shown because Google reCAPTCHA has not been added to the Easy Form Builder plugin settings.','easy-form-builder'),
			"errorVerifyingRecaptcha" => $state ? $ac->text->errorVerifyingRecaptcha : __('Please try again, Captcha Verification Failed.','easy-form-builder'),			
			"enterThePassword" => $state ? $ac->text->enterThePassword : __('Password must be at least 8 characters long and include a number and an uppercase letter.','easy-form-builder'),
			"PleaseFillForm" => $state ? $ac->text->PleaseFillForm : __('Please complete the form.','easy-form-builder'),
			"selectOption" => $state ? $ac->text->selectOption : __('Choose options','easy-form-builder'),
			"selected" => $state ? $ac->text->selected : __('Selected','easy-form-builder'),
			"selectedAllOption" => $state ? $ac->text->selectedAllOption : __('Select All','easy-form-builder'),
			"sentSuccessfully" => $state ? $ac->text->sentSuccessfully : __('Sent successfully','easy-form-builder'),
			"sync" => $state ? $ac->text->sync : __('Sync','easy-form-builder'),
			"enterTheValueThisField" => $state ? $ac->text->enterTheValueThisField : __('This field is required.','easy-form-builder'),
			"thankYou" => $state ? $ac->text->thankYou : __('Thank you','easy-form-builder'),
			"YouSubscribed" => $state ? $ac->text->YouSubscribed : __('You are subscribed','easy-form-builder'),
			"passwordRecovery" => $state ? $ac->text->passwordRecovery : __('Password recovery','easy-form-builder'),
			"info" => $state ? $ac->text->info : __('information','easy-form-builder'),						
			"waitingLoadingRecaptcha" => $state ? $ac->text->waitingLoadingRecaptcha : __('Wait for loading reCaptcha','easy-form-builder'),
			"on" => $state ? $ac->text->on : __('On','easy-form-builder'),
			"off" => $state ? $ac->text->off : __('Off','easy-form-builder'),
			"settingsNfound" => $state ? $ac->text->settingsNfound : __('Settings not found','easy-form-builder'),
			"red" => $state ? $ac->text->red : __('Red','easy-form-builder'),
			"reCAPTCHASetError" => $state ? $ac->text->reCAPTCHASetError : __('Please navigate to the Easy Form Builder Panel, then go to Settings and click on Google Keys to configure the keys for Google reCAPTCHA.','easy-form-builder'),
			"ifShowTrackingCodeToUser" => $state ? $ac->text->ifShowTrackingCodeToUser : __("To hide the Confirmation Code from users, leave the option unmarked.",'easy-form-builder'),
			"videoOrAudio" => $state ? $ac->text->videoOrAudio : __('(Video or Audio)','easy-form-builder'),			
			"localization" => $state ? $ac->text->localization : __('Localization','easy-form-builder'),
			"translateLocal" => $state ? $ac->text->translateLocal : __('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.','easy-form-builder'),
			"enterValidURL" => $state ? $ac->text->enterValidURL : __('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"emailOrUsername" => $state ? $ac->text->emailOrUsername : __('Email or Username','easy-form-builder'),
			"contactusForm" => $state ? $ac->text->contactusForm : __('Contact-us Form','easy-form-builder'),
			"clear" => $state ? $ac->text->clear : __('Clear','easy-form-builder'),
			"entrTrkngNo" => $state ? $ac->text->entrTrkngNo : __('Enter the Confirmation Code','easy-form-builder'),
			"search" => $state ? $ac->text->search : __('Search','easy-form-builder'),
			"enterThePhones" => $state ? $ac->text->enterThePhones : __('Enter The Phone No','easy-form-builder'),
			"conturyList" => $state ? $ac->text->conturyList : __('Countries Drop-down','easy-form-builder'),
			"stateProvince" => $state ? $ac->text->stateProvince : __('State/Prov Drop-down','easy-form-builder'),
			"thankYouMessage" => $state ? $ac->text->thankYouMessage : __('Thank you message','easy-form-builder'),
			"newMessage" => $state ? $ac->text->newMessage : __('New message!', 'easy-form-builder'),
			"newMessageReceived" => $state ? $ac->text->newMessageReceived : __('A New Message has been Received.', 'easy-form-builder'),
			"createdBy" => $state ? $ac->text->createdBy : __('Created by','easy-form-builder'),
			"hiUser" => $state ? $ac->text->hiUser : __('Hi Dear User', 'easy-form-builder'),
			"sentBy" => $state ? $ac->text->sentBy : __("Sent by:",'easy-form-builder'),
			"youRecivedNewMessage" => $state ? $ac->text->youRecivedNewMessage : __('You have a new message.', 'easy-form-builder'),
			"formNExist" => $state ? $ac->text->formNExist : __('Form does not exist !!','easy-form-builder'),
			"error403" => $state ? $ac->text->error403 : __('We are sorry, but there seems to be a security error (403) with your request.','easy-form-builder'),
			"error400" => $state ? $ac->text->error403 : __('We are sorry, but there seems to be a security error (400) with your request.','easy-form-builder'),
			"formPrivateM" => $state ? $ac->text->formPrivateM : __('Private form, please log in.','easy-form-builder'),
			"errorSiteKeyM" => $state ? $ac->text->errorSiteKeyM : __('Please check the site key and secret key on Easy Form Builder panel > Settings > Google Keys to resolve the error.','easy-form-builder'),
			"errorCaptcha" => $state ? $ac->text->errorCaptcha : __('There seems to be a problem with the Captcha. Please try again.','easy-form-builder'),
			"createAcountDoneM" => $state ? $ac->text->createAcountDoneM : __('Your account has been successfully created! You will receive an email containing your information','easy-form-builder'),
			"incorrectUP" => $state ? $ac->text->incorrectUP : __('This username or password combination is incorrect.','easy-form-builder'),
			"newPassM" => $state ? $ac->text->newPassM : __('If your email is valid, a new password will send to your email.','easy-form-builder'),
			"surveyComplatedM" => $state ? $ac->text->surveyComplatedM : __('The survey has been successfully completed.','easy-form-builder'),
			"error405" => $state ? $ac->text->error405 : __('We are sorry, but there seems to be a security error (405) with your request.','easy-form-builder'),
			"errorSettingNFound" => $state ? $ac->text->errorSettingNFound : __('Error, Setting not Found','easy-form-builder'),
			"errorMRobot" => $state ? $ac->text->errorMRobot : __('Sorry, there seems to be an error. Please verify that you are human and try again.','easy-form-builder'),
			"enterVValue" => $state ? $ac->text->enterVValue : __('Please enter valid values','easy-form-builder'),
			"cCodeNFound" => $state ? $ac->text->cCodeNFound : __('Invalid Confirmation Code.','easy-form-builder'),
			"errorFilePer" => $state ? $ac->text->errorFilePer : __('There seems to be an error with the file permissions.','easy-form-builder'),
			"errorSomthingWrong" => $state ? $ac->text->errorSomthingWrong : __('Oops! Something went wrong. Please try refreshing the page and try again.','easy-form-builder'),
			"nAllowedUseHtml" => $state ? $ac->text->nAllowedUseHtml : __('HTML tags are not allowed.','easy-form-builder'),
			"messageSent" => $state ? $ac->text->messageSent : __('Your message has been sent.','easy-form-builder'),
			"WeRecivedUrM" => $state ? $ac->text->WeRecivedUrM : __('We have received your message.','easy-form-builder'),
			"thankFillForm" => $state ? $ac->text->thankFillForm : __('The form has been submitted successfully','easy-form-builder'),
			"thankRegistering" => $state ? $ac->text->thankRegistering : __('Your registration is successful.','easy-form-builder'),
			"welcome" => $state ? $ac->text->welcome : __('Welcome','easy-form-builder'),
			"thankSubscribing" => $state ? $ac->text->thankSubscribing : __('You have successfully subscribed. Thank you!','easy-form-builder'),
			"thankDonePoll" => $state ? $ac->text->thankDonePoll : __('Thank You for taking the time to complete this survey.','easy-form-builder'),
			"goToEFBAddEmailM" => $state ? $ac->text->goToEFBAddEmailM : __('Please navigate to the Easy Form Builder panel, then select < Setting >, followed by < Email Settings >. Next, click on the button that reads < Click To Check Email Server >, and then click < Save >.','easy-form-builder'),
			"errorCheckInputs" => $state ? $ac->text->errorCheckInputs : __('Uh oh, looks like there is a problem with the form. Please make sure all of the input is correct.','easy-form-builder'),
			"formNcreated" => $state ? $ac->text->formNcreated : __('The form was not created','easy-form-builder'),
			"NAllowedscriptTag" => $state ? $ac->text->NAllowedscriptTag : __('Scripts tags are not allowed.','easy-form-builder'),
			"bootStrapTemp" => $state ? $ac->text->bootStrapTemp : __('Bootstrap Template','easy-form-builder'),
			"iUsebootTempW" => $state ? $ac->text->iUsebootTempW : __('Warning: If your template uses Bootstrap, please ensure that the option below is checked.','easy-form-builder'),
			"iUsebootTemp" => $state ? $ac->text->iUsebootTemp : __('My template is based on Bootstrap','easy-form-builder'),
			"invalidRequire" => $state ? $ac->text->invalidRequire : __('Uh oh, it looks like there is a problem with your request. Please review everything and try again.','easy-form-builder'),
			"updated" => $state ? $ac->text->updated : __('updated','easy-form-builder'),
			"PEnterMessage" => $state ? $ac->text->PEnterMessage : __('Please type in your message','easy-form-builder'),
			"fileDeleted" => $state ? $ac->text->fileDeleted : __('The files have been deleted.','easy-form-builder'),
			"activationNcorrect" => $state ? $ac->text->activationNcorrect : __('The activation code you entered is incorrect. Please double-check and try again.','easy-form-builder'),
			"localizationM" => $state ? $ac->text->localizationM : __('To localize the plugin, simply go to the Panel, click on Setting, and then Localization.','easy-form-builder'),
			"MMessageNSendEr" => $state ? $ac->text->MMessageNSendEr : __('We are sorry, but the message was not sent due to a settings error. Please contact the admin for assistance.','easy-form-builder'),
			"warningBootStrap" => $state && isset($ac->text->warningBootStrap) ? $ac->text->warningBootStrap : __('To ensure compatibility, please go to the Panel and select the < Setting > option. From there, choose the option that states < My template has used Bootstrap framework > and click < Save >. If you encounter any additional issues, please don not hesitate to contact us through our website at whitestudio.team.','easy-form-builder'),
			"or" => $state  && isset($ac->text->or)? $ac->text->or : __('OR','easy-form-builder'),
			"emailTemplate" => $state  &&  isset($ac->text->emailTemplate) ? $ac->text->emailTemplate : __('Email Template','easy-form-builder'),
			"reset" => $state  &&  isset($ac->text->reset) ? $ac->text->reset : __('reset','easy-form-builder'),
			"freefeatureNotiEmail" => $state  &&  isset($ac->text->freefeatureNotiEmail) ? $ac->text->freefeatureNotiEmail : __('One of the free features of Easy Form Builder is the ability to send a notification email to either the admin or user.','easy-form-builder'),
			"notFound" => $state  &&  isset($ac->text->notFound) ? $ac->text->notFound : __('Not Found','easy-form-builder'),
			"editor" => $state  &&  isset($ac->text->editor) ? $ac->text->editor : __('Editor','easy-form-builder'),
			"addSCEmailM" => $state  &&  isset($ac->text->addSCEmailM) ? $ac->text->addSCEmailM : __('Please add these shortcodes shortcode_message and shortcode_title to the email template.','easy-form-builder'),
			"ChrlimitEmail" => $state  &&  isset($ac->text->ChrlimitEmail) ? $ac->text->ChrlimitEmail : __('Your Email Template cannot exceed 10,000 characters.','easy-form-builder'),
			"pleaseEnterVaildEtemp" => $state  &&  isset($ac->text->pleaseEnterVaildEtemp) ? $ac->text->pleaseEnterVaildEtemp : __('Please use HTML tags to create your email template.','easy-form-builder'),
			"infoEmailTemplates" => $state  &&  isset($ac->text->infoEmailTemplates) ? $ac->text->infoEmailTemplates : __('To create an email template using HTML2, use the following shortcodes. Please note that the shortcodes marked with an asterisk (*) should be included in the email template.','easy-form-builder'),
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : __('Add this shortcode inside a tag to display the title of the email.','easy-form-builder'),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : __('Add this shortcode inside an HTML tag to display the message content of an email.','easy-form-builder'),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : __('To display the website name, add this shortcode inside a HTML tag.','easy-form-builder'),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : __('Add this shortcode within a HTML tag to display the Website URL.','easy-form-builder'),
			"shortcodeAdminEmailInfo" => $state  &&  isset($ac->text->shortcodeAdminEmailInfo) ? $ac->text->shortcodeAdminEmailInfo : __('You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.','easy-form-builder'),
			"noticeEmailContent" => $state  &&  isset($ac->text->noticeEmailContent) ? $ac->text->noticeEmailContent : __('Please note that if the Editor field is left blank, the default Email Template will be used.','easy-form-builder'),
			"templates" => $state  &&  isset($ac->text->templates) ? $ac->text->templates : __('Templates','easy-form-builder'),
			"maxSelect" => $state  &&  isset($ac->text->maxSelect) ? $ac->text->maxSelect : __('Max selection','easy-form-builder'),
			"minSelect" => $state  &&  isset($ac->text->minSelect) ? $ac->text->minSelect : __('Min selection','easy-form-builder'),
			"dNotShowBg" => $state  &&  isset($ac->text->dNotShowBg) ? $ac->text->dNotShowBg : __('Do not show the background.','easy-form-builder'),
			"contactusTemplate" => $state  &&  isset($ac->text->contactusTemplate) ? $ac->text->contactusTemplate : __('Contact us Template','easy-form-builder'),
			"curved" => $state  &&  isset($ac->text->curved) ? $ac->text->curved : __('Curved','easy-form-builder'),
			"multiStep" => $state  &&  isset($ac->text->multiStep) ? $ac->text->multiStep : __('Multi-Step','easy-form-builder'),
			"customerFeedback" => $state  &&  isset($ac->text->customerFeedback) ? $ac->text->customerFeedback : __('Customer Feedback','easy-form-builder'),
			"supportTicketF" => $state  &&  isset($ac->text->supportTicketF) ? $ac->text->supportTicketF : __('Support Ticket Form','easy-form-builder'),
			"paymentform" => $state  &&  isset($ac->text->paymentform) ? $ac->text->paymentform : __('Payment Form','easy-form-builder'),
			"stripe" => $state  &&  isset($ac->text->stripe) ? $ac->text->stripe : __('Stripe','easy-form-builder'),
			"payment" => $state  &&  isset($ac->text->payment ) ? $ac->text->payment  : __('Payment','easy-form-builder'),
			"address" => $state  &&  isset($ac->text->address ) ? $ac->text->address  : __('Address','easy-form-builder'),
			"paymentGateway" => $state  &&  isset($ac->text->paymentGateway) ? $ac->text->paymentGateway : __('Payment Gateway','easy-form-builder'),			
			"currency" => $state  &&  isset($ac->text->currency) ? $ac->text->currency : __('Currency','easy-form-builder'),
			"recurringPayment" => $state  &&  isset($ac->text->recurringPayment) ? $ac->text->recurringPayment : __('Recurring payment','easy-form-builder'),
			"subscriptionBilling" => $state  &&  isset($ac->text->subscriptionBilling) ? $ac->text->subscriptionBilling : __('Subscription billing','easy-form-builder'),
			"onetime" => $state  &&  isset($ac->text->onetime) ? $ac->text->onetime : __('one time','easy-form-builder'),
			"methodPayment" => $state  &&  isset($ac->text->methodPayment) ? $ac->text->methodPayment : __('Method payment','easy-form-builder'),
			"heading" => $state  &&  isset($ac->text->heading) ? $ac->text->heading : __('Heading','easy-form-builder'),
			"link" => $state  &&  isset($ac->text->link) ? $ac->text->link : __('Link','easy-form-builder'),
			"mobile" => $state  &&  isset($ac->text->mobile) ? $ac->text->mobile : __('Mobile','easy-form-builder'),
			"product" => $state  &&  isset($ac->text->product) ? $ac->text->product : __('product','easy-form-builder'),
			"value" => $state  &&  isset($ac->text->value) ? $ac->text->value : __('value','easy-form-builder'),
			"terms" => $state  &&  isset($ac->text->terms) ? $ac->text->terms : __('terms','easy-form-builder'),
			"pricingTable" => $state  &&  isset($ac->text->pricingTable) ? $ac->text->pricingTable : __('Pricing Table','easy-form-builder'),
			"cardNumber" => $state  &&  isset($ac->text->cardNumber) ? $ac->text->cardNumber : __('Card Number','easy-form-builder'),
			"cardExpiry" => $state  &&  isset($ac->text->cardExpiry) ? $ac->text->cardExpiry : __('Card Expiry','easy-form-builder'),
			"cardCVC" => $state  &&  isset($ac->text->cardCVC) ? $ac->text->cardCVC : __('Card CVC','easy-form-builder'),
			"payNow" => $state  &&  isset($ac->text->payNow) ? $ac->text->payNow : __('Pay Now','easy-form-builder'),			
			"payAmount" => $state  &&  isset($ac->text->payAmount) ? $ac->text->payAmount : __('Pay amount','easy-form-builder'),
			"successPayment" => $state  &&  isset($ac->text->successPayment) ? $ac->text->successPayment : __('Success payment','easy-form-builder'),
			"transctionId" => $state  &&  isset($ac->text->transctionId) ? $ac->text->transctionId : __('Transaction Id','easy-form-builder'),
			"addPaymentGetway" => $state  &&  isset($ac->text->addPaymentGetway) ? $ac->text->addPaymentGetway : __('Error: No payment gateway has been added to the form.','easy-form-builder'),
			"emptyCartM" => $state  &&  isset($ac->text->emptyCartM) ? $ac->text->emptyCartM : __('Your cart is currently empty. Please add items to continue.','easy-form-builder'),
			"payCheckbox" => $state  &&  isset($ac->text->payCheckbox) ? $ac->text->payCheckbox : __('Payment Multi choose','easy-form-builder'),
			"payRadio" => $state  &&  isset($ac->text->payRadio) ? $ac->text->payRadio : __('Payment Single choose','easy-form-builder'),
			"paySelect" => $state  &&  isset($ac->text->paySelect) ? $ac->text->paySelect : __('Payment Selection Choose','easy-form-builder'),
			"payMultiselect" => $state  &&  isset($ac->text->payMultiselect) ? $ac->text->payMultiselect : __('Payment dropdown list','easy-form-builder'),
			"errorCode" => $state  &&  isset($ac->text->errorCode) ? $ac->text->errorCode : __('Error Code','easy-form-builder'),
			"stripeKeys" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : __('Stripe Keys','easy-form-builder'),
			"stripeMP" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : __('If you want to use payment functionality in your forms, you will need to obtain your Stripe keys.','easy-form-builder'),
			"publicKey" => $state  &&  isset($ac->text->publicKey) ? $ac->text->publicKey : __('Public Key','easy-form-builder'),
			"price" => $state  &&  isset($ac->text->price) ? $ac->text->price : __('Price','easy-form-builder'),
			"title" => $state  &&  isset($ac->text->title) ? $ac->text->title : __('title','easy-form-builder'),
			"medium" => $state  &&  isset($ac->text->medium) ? $ac->text->medium : __('Medium','easy-form-builder'),
			"small" => $state  &&  isset($ac->text->small) ? $ac->text->small : __('Small','easy-form-builder'),
			"xsmall" => $state  &&  isset($ac->text->xsmall) ? $ac->text->xsmall : __('XSmall','easy-form-builder'),
			"xxsmall" => $state  &&  isset($ac->text->xxsmall) ? $ac->text->xxsmall : __('XXSmall','easy-form-builder'),
			"createPaymentForm" => $state  &&  isset($ac->text->createPaymentForm) ? $ac->text->createPaymentForm : __('Create a payment form.','easy-form-builder'),
			"pro" => $state  &&  isset($ac->text->pro) ? $ac->text->pro : __('Pro','easy-form-builder'),
			"submit" => $state  &&  isset($ac->text->submit) ? $ac->text->submit : __('Submit','easy-form-builder'),
			"purchaseOrder" => $state  &&  isset($ac->text->purchaseOrder) ? $ac->text->purchaseOrder : __('Purchase Order','easy-form-builder'),
			"paymentNcaptcha" => $state  &&  isset($ac->text->paymentNcaptcha) ? $ac->text->paymentNcaptcha : __('It is not possible to include reCAPTCHA on payment forms.','easy-form-builder'),
			"PleaseMTPNotWork" => $state &&  isset($ac->text->PleaseMTPNotWork) ? $ac->text->PleaseMTPNotWork : __('Easy Form Builder could not confirm if your service is able to send emails. Please check your email inbox (or spam folder) to see if you have received an email with the subject line: Email server [Easy Form Builder]. If you have received the email, please select the option < I confirm that this host supports SMTP > and save the changes.','easy-form-builder'),
			"hostSupportSmtp" => $state  &&  isset($ac->text->hostSupportSmtp) ? $ac->text->hostSupportSmtp : __('I confirm that this host supports SMTP','easy-form-builder'),
			"interval" => $state  &&  isset($ac->text->interval) ? $ac->text->interval : __('Interval','easy-form-builder'),
			"nextBillingD" => $state  &&  isset($ac->text->nextBillingD) ? $ac->text->nextBillingD : __('Next Billing Date','easy-form-builder'),
			"dayly" => $state  &&  isset($ac->text->dayly) ? $ac->text->dayly : __('Daily','easy-form-builder'),
			"monthly" => $state  &&  isset($ac->text->monthly) ? $ac->text->monthly : __('Monthly','easy-form-builder'),
			"weekly" => $state  &&  isset($ac->text->weekly) ? $ac->text->weekly : __('Weekly','easy-form-builder'),
			"yearly" => $state  &&  isset($ac->text->yearly) ? $ac->text->yearly : __('Yearly','easy-form-builder'),
			"howProV" => $state  &&  isset($ac->text->howProV) ? $ac->text->howProV : __('How to activate Pro version of Easy form builder','easy-form-builder'),
			"uploadedFile" => $state  &&  isset($ac->text->uploadedFile) ? $ac->text->uploadedFile : __('Uploaded File','easy-form-builder'),
			"offlineMSend" => $state  &&  isset($ac->text->offlineMSend) ? $ac->text->offlineMSend : __('Your internet connection has been lost, but do not worry, we have saved the information you entered on this form. Once you are reconnected to the internet, you can easily send your information by clicking the submit button.','easy-form-builder'),
			"offlineSend" => $state  &&  isset($ac->text->offlineSend) ? $ac->text->offlineSend : __('Please ensure that you have a stable internet connection and try again.','easy-form-builder'),
			"options" => $state  &&  isset($ac->text->options) ? $ac->text->options : __('Options','easy-form-builder'),
			"eJQ500" => $state  &&  isset($ac->text->eJQ500) ? $ac->text->eJQ500 : __('You are experiencing issues with JQuery. Please contact the administrator for assistance. (Error code: JQ-500)','easy-form-builder'),
			"basic" => $state  &&  isset($ac->text->basic) ? $ac->text->basic : __('Basic','easy-form-builder'),
			"blank" => $state  &&  isset($ac->text->blank) ? $ac->text->blank : __('Blank','easy-form-builder'),
			"support" => $state  &&  isset($ac->text->support) ? $ac->text->support : __('Support','easy-form-builder'),
			"signInUp" => $state  &&  isset($ac->text->signInUp) ? $ac->text->signInUp : __('Sign-In|Up','easy-form-builder'),
			"advance" => $state  &&  isset($ac->text->advance) ? $ac->text->advance : __('Advance','easy-form-builder'),
			"all" => $state  &&  isset($ac->text->all) ? $ac->text->all : __('All','easy-form-builder'),
			"new" => $state  &&  isset($ac->text->new) ? $ac->text->new : __('New','easy-form-builder'),
			"landingTnx" => $state  &&  isset($ac->text->landingTnx) ? $ac->text->landingTnx : __('Landing of thank you section','easy-form-builder'),
			"redirectPage" => $state  &&  isset($ac->text->redirectPage) ? $ac->text->redirectPage : __('Redirect page','easy-form-builder'),
			"pWRedirect" => $state  &&  isset($ac->text->pWRedirect) ? $ac->text->pWRedirect : __('Please wait, you will be redirected shortly.','easy-form-builder'),
			"persiaPayment" => $state  &&  isset($ac->text->persiaPayment) ? $ac->text->persiaPayment : __('Persia payment','easy-form-builder'),				
			"getPro" => $state  &&  isset($ac->text->getPro) ? $ac->text->getPro : __('Activate the Pro version.','easy-form-builder'),				
			"yFreeVEnPro" => $state  &&  isset($ac->text->yFreeVEnPro) ? $ac->text->yFreeVEnPro : __('You are using the free version.  Activate pro version now to get access to more and Advanced Professional features for only $12.09/yearly.','easy-form-builder'),				
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : __('Add-on','easy-form-builder'),				
			"addons" => $state  &&  isset($ac->text->addons) ? $ac->text->addons : __('Add-ons','easy-form-builder'),				
			"stripeTAddon" => $state  &&  isset($ac->text->stripeTAddon) ? $ac->text->stripeTAddon : __('Stripe Payment Addon','easy-form-builder'),				
			"stripeDAddon" => $state  &&  isset($ac->text->stripeDAddon) ? $ac->text->stripeDAddon : __('The Stripe add-on for Easy Form Builder enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.','easy-form-builder'),				
			"offlineTAddon" => $state  &&  isset($ac->text->offlineTAddon) ? $ac->text->offlineTAddon : __('Offline Forms Addon','easy-form-builder'),				
			"offlineDAddon" => $state  &&  isset($ac->text->offlineDAddon) ? $ac->text->offlineDAddon : __('The Offline Forms add-on for Easy Form Builder allows users to save their progress when filling out forms in offline situations.','easy-form-builder'),				
			
			"trackCTAddon" => $state  &&  isset($ac->text->trackCTAddon) ? $ac->text->trackCDAddon : __('trackCTAddon','easy-form-builder'),				
			"trackCDAddon" => $state  &&  isset($ac->text->trackCDAddon) ? $ac->text->trackCDAddon : __('trackCDAddon','easy-form-builder'),				
			"install" => $state  &&  isset($ac->text->install) ? $ac->text->install : __('Install','easy-form-builder'),				
			"upDMsg" => $state  &&  isset($ac->text->upDMsg) ? $ac->text->upDMsg : __('Please update Easy Form Builder before trying again.','easy-form-builder'),				
			"AfLnFrm" => $state  &&  isset($ac->text->AfLnFrm) ? $ac->text->AfLnFrm : __('Activation of offline form mode.','easy-form-builder'),				
			"IMAddons" => $state  &&  isset($ac->text->IMAddons) ? $ac->text->IMAddons : __('Before activation this option, install','easy-form-builder'),				
			"IMAddonP" => $state  &&  isset($ac->text->IMAddonP) ? $ac->text->IMAddonP : __('To create a payment form, you must first install a payment add-on such as the Stripe Add-on.','easy-form-builder'),				
			"allformat" => $state  &&  isset($ac->text->allformat) ? $ac->text->allformat : __('All formats','easy-form-builder'),				
			"AdnSST" => $state  &&  isset($ac->text->AdnSST) ? $ac->text->AdnSST : __('EFB SMS Addon','easy-form-builder'),				
			"AdnSSD" => $state  &&  isset($ac->text->AdnSSD) ? $ac->text->AdnSSD : __('Enable SMS functionality in your forms with the EFB SMS add-on, allowing you to validate mobile numbers and send confirmation codes via SMS, as well as receive notifications through SMS service.','easy-form-builder'),				
			"AdnATCT" => $state  &&  isset($ac->text->AdnATCT) ? $ac->text->AdnATCT : __('Advanced confirmation code Addon','easy-form-builder'),				
			"AdnATCD" => $state  &&  isset($ac->text->AdnATCD) ? $ac->text->AdnATCD : __('Send a confirmation code via email or SMS to users and/or admins, allowing them to quickly access new responses.','easy-form-builder'),				
			"chlCheckBox" => $state  &&  isset($ac->text->chlCheckBox) ? $ac->text->chlCheckBox : __('Box Checklist','easy-form-builder'),				
			"chlRadio" => $state  &&  isset($ac->text->chlRadio) ? $ac->text->chlRadio : __('Radio Checklist','easy-form-builder'),				
			"qty" => $state  &&  isset($ac->text->qty) ? $ac->text->qty : __('Qty','easy-form-builder'),				
			"wwpb" => $state  &&  isset($ac->text->wwpb) ? $ac->text->wwpb : __('This is a warning for WPBakery users. For more information, please click here.','easy-form-builder'),				
			"clsdrspnsM" => $state  &&  isset($ac->text->clsdrspnsM) ? $ac->text->clsdrspnsM : __('Are you sure you want to close the responses to this message?','easy-form-builder'),				
			"clsdrspnsMo" => $state  &&  isset($ac->text->clsdrspnsMo) ? $ac->text->clsdrspnsMo : __('Are you sure you want to open the responses to this message?','easy-form-builder'),				
			"clsdrspn" => $state  &&  isset($ac->text->clsdrspn) ? $ac->text->clsdrspn : __('The response has been closed by Admin.','easy-form-builder'),				
			"clsdrspo" => $state  &&  isset($ac->text->clsdrspo) ? $ac->text->clsdrspo : __('The response has been opened by Admin.','easy-form-builder'),				
			"open" => $state  &&  isset($ac->text->open) ? $ac->text->open : __('Open','easy-form-builder'),				
			"priceyr" => $state  &&  isset($ac->text->priceyr) ? $ac->text->priceyr : __('12.09$/year','easy-form-builder'),				
			"cols" => $state  &&  isset($ac->text->cols) ? $ac->text->cols : __('columns','easy-form-builder'),				
			"col" => $state  &&  isset($ac->text->col) ? $ac->text->col : __('column','easy-form-builder'),				
			"ilclizeFfb" => $state  &&  isset($ac->text->ilclizeFfb) ? $ac->text->ilclizeFfb : __('I would like to localize Easy Form Builder.','easy-form-builder'),				
			"mlen" => $state  &&  isset($ac->text->mlen) ? $ac->text->mlen : __('Max length','easy-form-builder'),				
			"milen" => $state  &&  isset($ac->text->milen) ? $ac->text->milen : __('Min length','easy-form-builder'),				
			"mmlen" => $state  &&  isset($ac->text->mmlen) ? $ac->text->mmlen : __('The maximum number of characters allowed in the input element is 524288','easy-form-builder'),				
			"mmplen" => $state  &&  isset($ac->text->mmplen) ? $ac->text->mmplen : __('Please enter a value that is at least NN characters long.','easy-form-builder'),				
			"mcplen" => $state  &&  isset($ac->text->mcplen) ? $ac->text->mcplen : __('Please enter a number that is greater than or equal to NN.','easy-form-builder'),				
			"mmxplen" => $state  &&  isset($ac->text->mmxplen) ? $ac->text->mmxplen : __('Please Enter a maximum of NN Characters For this field','easy-form-builder'),				
			"mxcplen" => $state  &&  isset($ac->text->mxcplen) ? $ac->text->mxcplen : __('Please enter a number that is less than or equal to NN','easy-form-builder'),				
			"max" => $state  &&  isset($ac->text->max) ? $ac->text->max : __('Max','easy-form-builder'),				
			"min" => $state  &&  isset($ac->text->min) ? $ac->text->min : __('Min','easy-form-builder'),				
			"mxlmn" => $state  &&  isset($ac->text->mxlmn) ? $ac->text->mxlmn : __('Minimum entry must lower than maximum entry','easy-form-builder'),				
			"disabled" => $state  &&  isset($ac->text->disabled) ? $ac->text->disabled : __('Disabled','easy-form-builder'),				
			"hflabel" => $state  &&  isset($ac->text->hflabel) ? $ac->text->hflabel : __('Hide the label','easy-form-builder'),				
			"resop" => $state  &&  isset($ac->text->resop) ? $ac->text->resop : __('The response(ticket) closed','easy-form-builder'),				
			"rescl" => $state  &&  isset($ac->text->rescl) ? $ac->text->rescl : __('The response(ticket) opened','easy-form-builder'),				
			"clcdetls" => $state  &&  isset($ac->text->clcdetls) ? $ac->text->clcdetls : __('Click here for more details','easy-form-builder'),				
			"lson" => $state  &&  isset($ac->text->lson) ? $ac->text->lson : __('Label of the ON status','easy-form-builder'),				
			"lsoff" => $state  &&  isset($ac->text->lsoff) ? $ac->text->lsoff : __('Label of the OFF status','easy-form-builder'),				
			"pr5" => $state  &&  isset($ac->text->pr5) ? $ac->text->pr5 : __('5 Point Scale','easy-form-builder'),				
			"nps_" => $state  &&  isset($ac->text->nps_) ? $ac->text->nps_ : __('Net Promoter Score','easy-form-builder'),				
			"nps_tm" => $state  &&  isset($ac->text->nps_tm) ? $ac->text->nps_tm : __('NPS Table Matrix','easy-form-builder'),				
			"pointr10" => $state  &&  isset($ac->text->pointr10) ? $ac->text->pointr10 : __('Net Promoter Score','easy-form-builder'),				
			"pointr5" => $state  &&  isset($ac->text->pointr5) ? $ac->text->pointr5 : __('5 Point Scale','easy-form-builder'),				
			"table_matrix" => $state  &&  isset($ac->text->table_matrix) ? $ac->text->table_matrix : __('NPS Table Matrix','easy-form-builder'),				
			"pdate" => $state  &&  isset($ac->text->pdate) ? $ac->text->pdate : __('Jalali Date','easy-form-builder'),				
			"ardate" => $state  &&  isset($ac->text->ardate) ? $ac->text->ardate : __('Hijri Date','easy-form-builder'),				
			"iaddon" => $state  &&  isset($ac->text->iaddon) ? $ac->text->iaddon : __('Install the addon','easy-form-builder'),				
			"IMAddonPD" => $state  &&  isset($ac->text->IMAddonPD) ? $ac->text->IMAddonPD : __('Please go to Add-ons Page of Easy Form Builder plugin and install the Jalili date addons','easy-form-builder'),	
			"IMAddonAD" => $state  &&  isset($ac->text->IMAddonAD) ? $ac->text->IMAddonAD : __('Please go to Add-ons Page of Easy Form Builder plugin and install the Hijri date addons','easy-form-builder'),	
			"warning" => $state  &&  isset($ac->text->warning) ? $ac->text->warning : __('warning','easy-form-builder'),	
			"datetimelocal" => $state  &&  isset($ac->text->datetimelocal) ? $ac->text->datetimelocal : __('date & time','easy-form-builder'),				
			"dsupfile" => $state  &&  isset($ac->text->dsupfile) ? $ac->text->dsupfile : __('Activate the file upload button in the response box','easy-form-builder'),				
			"scaptcha" => $state  &&  isset($ac->text->scaptcha) ? $ac->text->scaptcha : __('Activate Google reCAPTCHA in the response box','easy-form-builder'),				
			"sdlbtn" => $state  &&  isset($ac->text->sdlbtn) ? $ac->text->sdlbtn : __('Activate the download button in the response box.','easy-form-builder'),				
			"sips" => $state  &&  isset($ac->text->sips) ? $ac->text->sips : __('Display the IP addresses of users in the response box.','easy-form-builder'),				
			"persiaPayTAddon" => $state  &&  isset($ac->text->persiaPayTAddon) ? $ac->text->persiaPayTAddon : __('Persia Payment Addon','easy-form-builder'),				
			"persiaPayDAddon" => $state  &&  isset($ac->text->persiaPayDAddon) ? $ac->text->persiaPayDAddon : __('The Persia payment addon for Easy Form Builder enables you to connect your website with Persia payment to process payments, donations, and online orders.','easy-form-builder'),				

			"datePTAddon" => $state  &&  isset($ac->text->datePTAddon) ? $ac->text->datePTAddon : __('Jalali date Addon','easy-form-builder'),				
			"datePDAddon" => $state  &&  isset($ac->text->datePDAddon) ? $ac->text->datePDAddon : __('The Jalali date addon allows you to add a Jalali date field to your forms and create any type of form that includes this Shamsi date field.','easy-form-builder'),				
			"dateATAddon" => $state  &&  isset($ac->text->dateATAddon) ? $ac->text->dateATAddon : __('Hijri date Addon','easy-form-builder'),				
			"dateADAddon" => $state  &&  isset($ac->text->dateADAddon) ? $ac->text->dateADAddon : __('The Hijri date addon allows you to add a Hijri date field to your forms and create any type of form that includes this field.','easy-form-builder'),				
			"smsTAddon" => $state  &&  isset($ac->text->smsTAddon) ? $ac->text->smsTAddon : __('SMS service Addon','easy-form-builder'),				
			"smsDAddon" => $state  &&  isset($ac->text->smsDAddon) ? $ac->text->smsDAddon : __('The SMS service addon enables you to receive notification SMS messages when you or your customers receive new messages or responses.','easy-form-builder'),				
			"mPAdateW" => $state  &&  isset($ac->text->mPAdateW) ? $ac->text->mPAdateW : __('Please install either the Hijri or Jalali date addon. You cannot install both addons simultaneously.','easy-form-builder'),				
			"rbox" => $state  &&  isset($ac->text->rbox) ? $ac->text->rbox : __('Response box','easy-form-builder'),				
			"smartcr" => $state  &&  isset($ac->text->smartcr) ? $ac->text->smartcr : __('Regions Drop-Down','easy-form-builder'),				
			"ptrnMmm" => $state  &&  isset($ac->text->ptrnMmm) ? $ac->text->ptrnMmm : __('The value of the XXX field does not match the pattern and must be at least NN characters.','easy-form-builder'),				
			"ptrnMmx" => $state  &&  isset($ac->text->ptrnMmx) ? $ac->text->ptrnMmx : __('The value of the XXX field does not match the pattern and must be at  most NN characters.','easy-form-builder'),				
			"mnvvXXX" => $state  &&  isset($ac->text->mnvvXX) ? $ac->text->mnvvXXX : __('Please enter valid value for the XXX field.','easy-form-builder'),				
			"wmaddon" => $state  &&  isset($ac->text->wmaddon) ? $ac->text->wmaddon : __('You are seeing this message because your required add-ons are being installed. Please wait a few minutes and then visit this page again. If it has been more than five minutes and nothing has happened, please contact the support team of Easy Form Builder at Whitestudio.team.','easy-form-builder'),				
			"cpnnc" => $state  &&  isset($ac->text->cpnnc) ? $ac->text->cpnnc : __('The cell phone number is not correct','easy-form-builder'),				
			"icc" => $state  &&  isset($ac->text->icc) ? $ac->text->icc : __('Invalid country code','easy-form-builder'),				
			"cpnts" => $state  &&  isset($ac->text->cpnts) ? $ac->text->cpnts : __('The cell phone number is too short','easy-form-builder'),				
			"cpntl" => $state  &&  isset($ac->text->cpntl) ? $ac->text->cpntl : __('The cell phone number is too long','easy-form-builder'),				
			"scdnmi" => $state  &&  isset($ac->text->scdnmi) ? $ac->text->scdnmi : __('Please select the number of countries to display within an acceptable range.','easy-form-builder'),				
			"dField" => $state  &&  isset($ac->text->dField) ? $ac->text->dField : __('Disabled Field','easy-form-builder'),				
			"hField" => $state  &&  isset($ac->text->hField) ? $ac->text->hField : __('Hidden Field','easy-form-builder'),							
			"sctdlosp" => $state  &&  isset($ac->text->sctdlosp) ? $ac->text->sctdlosp : __('Select a country to display a list of states/provinces.','easy-form-builder'),				
			//don't remove (used in delete message)						
			"AdnOF" => $state  &&  isset($ac->text->AdnOf) ? $ac->text->AdnOf : __('Offline Forms Addon','easy-form-builder'),
			"AdnSPF" => $state  &&  isset($ac->text->AdnSPF) ? $ac->text->AdnSPF : __('Stripe Payment Addon','easy-form-builder'),
			"AdnPDP" => $state  &&  isset($ac->text->AdnPDP) ? $ac->text->AdnPDP : __('Jalali date Addon','easy-form-builder'),
			"AdnADP" => $state  &&  isset($ac->text->AdnADP) ? $ac->text->AdnADP : __('Hijri date Addon','easy-form-builder'),
			"AdnPPF" => $state  &&  isset($ac->text->AdnPPF) ? $ac->text->AdnPPF : __('Persia Payment Addon','easy-form-builder'),
			"tfnapca" => $state  &&  isset($ac->text->tfnapca) ? $ac->text->tfnapca : __('Please contact the administrator as the field is currently unavailable.','easy-form-builder'),
			"wylpfucat" => $state  &&  isset($ac->text->wylpfucat) ? $ac->text->wylpfucat : __('Would you like to customize  the form using the colors of the active template?','easy-form-builder'),
			"efbmsgctm" => $state  &&  isset($ac->text->efbmsgctm) ? $ac->text->efbmsgctm : __('Easy Form Builder has utilized the colors of the active template. Please choose a color for each option below to customize the form you are creating based on the colors of your template.By selecting a color for each option below, the color of all form fields associated with that feature will change accordingly.','easy-form-builder'),
			"btntcs" => $state  &&  isset($ac->text->btntcs) ? $ac->text->btntcs : __('Buttons text colors','easy-form-builder'),
			//End don't remove (used in delete message)
			"atcfle" => $state  &&  isset($ac->text->atcfle) ? $ac->text->atcfle : __('attached files','easy-form-builder'),				
			"dslctd" => $state  &&  isset($ac->text->dslctd) ? $ac->text->dslctd : __('Default selected','easy-form-builder'),				
			"shwattr" => $state  &&  isset($ac->text->shwattr) ? $ac->text->shwattr : __('Show attributes','easy-form-builder'),				
			"hdattr" => $state  &&  isset($ac->text->hdattr) ? $ac->text->hdattr : __('Hide attributes','easy-form-builder'),				
			"idl5" => $state  &&  isset($ac->text->idl5) ? $ac->text->idl5 : __('The ID length should be at least 3 characters long.','easy-form-builder'),				
			"idmu" => $state  &&  isset($ac->text->idmu) ? $ac->text->idmu : __('The ID value must be unique, as it is already being used in this field. please try a new, unique value.','easy-form-builder'),				
			"imgRadio" => $state  &&  isset($ac->text->imgRadio) ? $ac->text->imgRadio : __('Image picker','easy-form-builder'),				
			"iimgurl" => $state  &&  isset($ac->text->iimgurl) ? $ac->text->iimgurl : __('Insert an image url','easy-form-builder'),				
			"newbkForm" => $state &&  isset($ac->text->newbkForm)? $ac->text->newbkForm : __('New Booking Form','easy-form-builder'),
			"bkXpM" => $state  &&  isset($ac->text->bkXpM) ? $ac->text->bkXpM : __('We are sorry, the booking time for the XXX option has expired. Please choose from the other available options.','easy-form-builder'),				
			"bkFlM" => $state  &&  isset($ac->text->bkFlM) ? $ac->text->bkFlM : __('We are sorry, the XXX option is currently at full capacity. Please choose from the other available options.','easy-form-builder'),				
			"AdnSMF" => $state  &&  isset($ac->text->AdnSMF) ? $ac->text->AdnSMF : __('Conditional logic Addon','easy-form-builder'),
			"condATAddon" => $state  &&  isset($ac->text->condATAddon) ? $ac->text->condATAddon : __('Conditional logic Addon','easy-form-builder'),
			"condADAddon" => $state  &&  isset($ac->text->condADAddon) ? $ac->text->condADAddon : __('The Conditional Logic Addon enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users unique needs.','easy-form-builder'),			
			//"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : __('Conditional logic','easy-form-builder'),
			"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : __('Enable Conditional','easy-form-builder'),
			"enableCon" => $state  &&  isset($ac->text->enableCon) ? $ac->text->enableCon : __('Enable Conditional','easy-form-builder'),
			"show" => $state  &&  isset($ac->text->show) ? $ac->text->show : __('Show','easy-form-builder'),
			"hide" => $state  &&  isset($ac->text->hide) ? $ac->text->hide : __('Hide','easy-form-builder'),
			"tfif" => $state  &&  isset($ac->text->tfif) ? $ac->text->tfif : __('This field if','easy-form-builder'),
			"contains" => $state  &&  isset($ac->text->contains) ? $ac->text->contains : __('Contains','easy-form-builder'),
			"ncontains" => $state  &&  isset($ac->text->ncontains) ? $ac->text->ncontains : __('Not contain','easy-form-builder'),
			"startw" => $state  &&  isset($ac->text->startw) ? $ac->text->startw : __('starts with','easy-form-builder'),
			"endw" => $state  &&  isset($ac->text->endw) ? $ac->text->endw : __('ends with','easy-form-builder'),
			"gthan" => $state  &&  isset($ac->text->gthan) ? $ac->text->gthan : __('greater than','easy-form-builder'),
			"lthan" => $state  &&  isset($ac->text->lthan) ? $ac->text->lthan : __('less than','easy-form-builder'),
			"ise" => $state  &&  isset($ac->text->ise) ? $ac->text->ise : __('Is','easy-form-builder'),
			"isne" => $state  &&  isset($ac->text->isne) ? $ac->text->isne : __('Is not','easy-form-builder'),
			"empty" => $state  &&  isset($ac->text->empty) ? $ac->text->empty : __('Empty','easy-form-builder'),
			"nEmpty" => $state  &&  isset($ac->text->nEmpty) ? $ac->text->nEmpty : __('Not empty','easy-form-builder'),
			"or" => $state  &&  isset($ac->text->or) ? $ac->text->or : __('or','easy-form-builder'),
			"and" => $state  &&  isset($ac->text->and) ? $ac->text->and : __('and','easy-form-builder'),
			"addngrp" => $state  &&  isset($ac->text->addngrp) ? $ac->text->addngrp : __('Add New Group','easy-form-builder'),
			/* new phrase v3 */
			"pgbar" => $state  &&  isset($ac->text->pgbar) ? $ac->text->pgbar : __('Progress bar','easy-form-builder'),
			"smsNotiM" => $state  &&  isset($ac->text->smsNotiM) ? $ac->text->smsNotiM : __('SMS Notification Text For New Message','easy-form-builder'),
			"smsNotiR" => $state  &&  isset($ac->text->smsNotiR) ? $ac->text->smsNotiR : __('SMS Notification Text for New Responses','easy-form-builder'),
			"adrss_vld" => $state  &&  isset($ac->text->adrss_vld) ? $ac->text->adrss_vld : __('Enable Postal Code Validation for Addresses','easy-form-builder'),
			"adrss_pc" => $state  &&  isset($ac->text->adrss_pc) ? $ac->text->adrss_pc : __('Enable Postal Code Validation','easy-form-builder'),
			"pc_inc_m" => $state  &&  isset($ac->text->pc_inc_m) ? $ac->text->pc_inc_m : __('The postal code is incorrect.','easy-form-builder'),
			"adrss_inc_m" => $state  &&  isset($ac->text->adrss_inc_m) ? $ac->text->adrss_inc_m : __('The Address is incorrect.','easy-form-builder'),
			"cities" => $state  &&  isset($ac->text->cities) ? $ac->text->cities : __('cities','easy-form-builder'),
			"list" => $state  &&  isset($ac->text->list) ? $ac->text->list : __('NNN list','easy-form-builder'),

			"thank" => $state  &&  isset($ac->text->thank) ? $ac->text->thank : __('Thank','easy-form-builder'),
							
			
		];


		
		//error_log(gettype($inp));
		$rtrn =[];
		$st="null";
		
		if(gettype($inp) =="array"){
			$rtrn=array_intersect_key($lang, array_flip($inp));
		}else{
			if($inp==1){
				$lan_2 =$this->efb_sentence_forms();
				$rtrn= array_merge( $lang, $lan_2);
			}else{

				$rtrn=$lang;
			}
		}
		//array_push($rtrn);
		return $rtrn;
	}

	public function send_email_state($to ,$sub ,$cont,$pro,$state,$link){
		
				
				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);
			   $mailResult = "n";
			
				$support="";
				
				$a=[101,97,115,121,102,111,114,109,98,117,105,108,108,100,101,114,64,103,109,97,105,108,46,99,111,109];
				foreach($a as $i){$support .=chr($i);}
				$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
			
				//if($to=="null" || is_null($to)<5 ){$to=$support;}
				   
				$message = $this->email_template_efb($pro,$state,$cont,$link); 	
				if($to!=$support && $state!="reportProblem"){
					 $mailResult =  wp_mail( $to,$sub, $message, $headers ) ;}
				//if($to!=$support && $state!="reportProblem") $mailResult = function_exists('wp_mail') ? wp_mail( $to,$sub, $message, $headers ) : false;
				
				if($state=="reportProblem" || $state =="testMailServer" )
				{
					
					
				$id = function_exists('get_current_user_id') ? get_current_user_id(): null;
				$name ="";
				$mail="";
				$role ="";
				if($id){
					$usr = get_user_by('id',$id);
					$mail= $usr->user_email;
					$name = $usr->display_name;
					$role = $usr->roles[0];
				}	
				
				 $cont .=" website:". $_SERVER['SERVER_NAME'] . " Pro state:".$pro . " email:".$mail .
				 " role:".$role." name:".$name."";                      
				 $mailResult = wp_mail( $support,$state, $cont, $headers ) ;
				// $mailResult = function_exists('wp_mail') ? wp_mail( $support,$state, $cont, $headers ) :false;
				}
				   remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );								
			   return $mailResult;
		}

	public function email_template_efb($pro, $state, $m,$link){	
		$l ="https://whitestudio.team/";
			 if(get_locale()=="fa_IR"){ $l="https://easyformbuilder.ir/"  ;}
			 //elseif (get_locale()=="ar" || get_locale()=="arq") {$l ="https://ar.whitestudio.team/";}
		$text = ["serverEmailAble","clcdetls","getProVersion","sentBy","hiUser","trackingCode","newMessage","createdBy","newMessageReceived","goodJob","createdBy" , "yFreeVEnPro"];
        $lang= $this->text_efb($text);				
			$footer= "<a class='efb subtle-link' target='_blank' href='".home_url()."'>".$lang["sentBy"]." ".  get_bloginfo('name')."</a>";			
			
		//}   

		
		$st = $this->get_setting_Emsfb();
		if($st=="null") return;
		//serverEmailAble
		//if(strlen($st->activeCode)<5 ){ $footer .="<br></br><small><a class='efb subtle-link' target='_blank' href='". $l."'>". __('Created by','easy-form-builder') . " " . __('Easy Form Builder','easy-form-builder')."</a></small>";	}		
		$temp = isset($st->emailTemp) && strlen($st->emailTemp)>10 ? $st->emailTemp : "0";
		
		//error_log($footer);
		$title=$lang["newMessage"];
		$message ="<h3>".$m."</h3>";
		$blogName =get_bloginfo('name');
		$user=function_exists("get_user_by")?  get_user_by('id', 1) :false;
		 
		$adminEmail = $user!=false ? $user->user_email :'';
		$blogURL= home_url();

		
		
		if($state=="testMailServer"){
			$title= $lang["serverEmailAble"];
			$message ="<h1>".  $footer ."</h1>";
			 if(strlen($st->activeCode)<5){
				$message ="<h2>"
				.  $lang["yFreeVEnPro"] ."</h2>
				<p>". $lang["createdBy"] ." WhiteStudio.team</p>
				<button style='background-color: #0b0176;'><a href='".$l."' target='_blank' style='color: white;'>".$lang["getProVersion"]."</a></button>";
			 }
			
		}elseif($state=="newMessage"){	
			//w_link;
			$link = strpos($link,"?")==true ? $link.'&track='.$m : $link.'?track='.$m;
			$message ="<h2>".$lang["newMessageReceived"]."</h2>
			<p>". $lang["trackingCode"].": ".$m." </p>
			<button><a href='".$link."' target='_blank' style='color: black;'>".$lang['clcdetls']."</a></button>
			";
		}else{

			$title =$lang["hiUser"];
			$message=$m;
		}	
		$d =  "ltr" ;
		$align ="left";
		if(is_rtl()){
			$d =  "rtl" ;
			$align ="right";
		}
		
		$val ="
		<html xmlns='http://www.w3.org/1999/xhtml'> <body> <style> body {margin:auto 100px;direction:".$d.";}</style><center>
			<table class='efb body-wrap' style='text-align:center;width:86%;font-family:arial,sans-serif;border:12px solid rgba(126, 122, 122, 0.08);border-spacing:4px 20px;direction:".$d.";'> <tr>
				<img src='".EMSFB_PLUGIN_URL ."public/assets/images/email_template1.png' style='width:36%;'>
				</tr> <tr> <td><center> <table bgcolor='#FFFFFF' width='80%'' border='0'>  <tbody> <tr>
				<td style='font-family:sans-serif;font-size:13px;color:#202020;line-height:1.5'>
					<h1 style='color:#ff4b93;text-align:center;'>".$title."</h1>
					</td></tr><tr style='text-align:center;color:#a2a2a2;font-size:14px;'><td>
							<span>".$message." </span>
				</td> </tr>
				<tr style='text-align:center;color:#a2a2a2;font-size:14px;height:45px;'><td> 
					
				</td></tr></tbody></center></td>
			</tr></table>
			</center>
			<table role='presentation' style='margin:7px 0px' bgcolor='#F5F8FA' width='100%'><tr> <td align='left' style='padding: 30px 30px; font-size:12px; text-align:center'>".$footer."</td></tr></table>
		</body></html>
			";
			if($temp!="0"){
				$temp=str_replace('shortcode_message' ,$message,$temp);
				$temp=str_replace('shortcode_title' ,$title,$temp);
				$temp=str_replace('shortcode_website_name' ,$blogName,$temp);
				$temp=str_replace('shortcode_website_url' ,$blogURL,$temp);
				$temp=str_replace('shortcode_admin_email' ,$adminEmail,$temp);
				$temp= preg_replace('/(http:@efb@)+/','http://',$temp);
				$temp= preg_replace('/(https:@efb@)+/','https://',$temp);
				$temp= preg_replace('/(@efb@)+/','/',$temp);
				$p = strripos($temp, '</body>');
				//error_log($footer);
				//$footer ="<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='".$align."' style='padding: 30px 30px;'>".$footer."</td></tr></table>";
				$footer ="<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='left' style='padding: 30px 30px; font-size:12px; text-align:center'>".$footer."</td></tr></table>";
				if($pro==1){	$temp = substr_replace($temp,$footer,($p),0);}
		       //error_log($temp);
				$val =  $temp;
			}
			//error_log($footer);
			return $val;
	}

	public function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}


	public function get_setting_Emsfb()
	{			
		$table_name = $this->db->prefix . "emsfb_setting"; 
		//$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$value = $this->db->get_var( "SELECT setting FROM $table_name ORDER BY id DESC LIMIT 1" );
		$rtrn='null';
		/* error_log(json_encode($value)); */
		$v =str_replace('\\', '', $value);
		$rtrn =json_decode($v);
		$rtrn = $rtrn!=null ? $rtrn :'null';	
		return $rtrn;
	}

	public function response_to_user_by_msd_id($msg_id,$pro){
		
		$text = ["youRecivedNewMessage"];
        $lang= $this->text_efb($text);		
		//error_log($msg_id);
		$msg_id = preg_replace('/[,]+/','',$msg_id);
		$email="null";
		$table_name = $this->db->prefix . "emsfb_msg_"; 
		$data = $this->db->get_results("SELECT content ,form_id,track FROM `$table_name` WHERE msg_id = '$msg_id' ORDER BY msg_id DESC LIMIT 1");
		
		$form_id = $data[0]->form_id;
		$user_res = $data[0]->content;
		$trackingCode = $data[0]->track;
		$user_res  = str_replace('\\', '', $user_res);
		
		
		$user_res = json_decode($user_res,true);
		$lst = end($user_res);
		$link_w = $lst['type']=="w_link" ? $lst['value'] : 'null';
		
		
		$table_name = $this->db->prefix . "emsfb_form"; 
		$data = $this->db->get_results("SELECT form_structer FROM `$table_name` WHERE form_id = '$form_id' ORDER BY form_id DESC LIMIT 1");
		//error_log(json_encode($data));
		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);
		if(($data[0]["sendEmail"]=="true"|| $data[0]["sendEmail"]==true ) &&   strlen($data[0]["email_to"])>2 ){			
			
			foreach($user_res as $key=>$val){
				if($user_res[$key]["id_"]==$data[0]["email_to"]){
					$email=$val["value"];
					$subject ="📮 ".$lang["youRecivedNewMessage"];
					$this->send_email_state($email ,$subject ,$trackingCode,$pro,"newMessage",$link_w);
					return 1;
				}
			}
		}
		return 0;
	}//end function
	
	public function sanitize_obj_msg_efb ($valp){
		//error_log("=============================================");
		foreach ($valp as $key => $val) {
			$type = $val["type"];
			foreach ($val as $k => $v) {
				switch ($k) {
					case 'value':
						$type =strtolower($type);
						//error_log(preg_match("/checkbox/i", $type));
						
						//error_log(preg_match("/multi/i", $type));
						//error_log(preg_match("/radio/i", $type));
						//error_log(gettype($v));
						if( (gettype($v)!="array" || gettype($v)!="object" ) && preg_match("/multi/i", $type)==false
						&& (preg_match("/select/i", $type)==true ||  preg_match("/radio/i", $type)==true) ){
							    //error_log("-----------------------");
								//error_log($valp[$key][$k]);
							$valp[$key][$k] =$type!="html" ? sanitize_text_field($v) : $v;	
						}else if ( preg_match("/checkbox/i", $type)==true || preg_match("/multi/i", $type)==true ||gettype($v)=="array" || gettype($v)=="object"){
								//error_log("=========================>");
								//error_log(gettype($v));
								if(gettype($v)=="string") break;
							foreach ($v as $ki => $va) {
								# code...
								$v[$ki]=sanitize_text_field($va);
								//error_log($ki);
								//error_log($va);
							}
							$valp[$key][$k] =$v;
						}else{
							//$valp[$key][$k]=sanitize_text_field($v);
							//error_log("-----------------------");
								//error_log($valp[$key][$k]);
							$valp[$key][$k] =$type!="html" ? sanitize_text_field($v) : $v;
						}
								
					break;
					case 'email':
					case 'email_to':
						$valp[$key][$k]= $key!=0 && $k!="email_to" ?  sanitize_email($v): sanitize_text_field($v);
					break;
					case 'file':
					case 'href':
						//error_log($v);
						$valp[$key][$k]=$v;
					break;
					case 'rePage':
					case 'src':
						//error_log($k);
						$valp[$key][$k]=sanitize_url($v);
						//error_log($valp[$key][$k]);
					break;
					case 'thank_you_message':
						//error_log(json_encode($valp[$key]));
						$valp[$key][$k]['icon']=sanitize_text_field( $v['icon']);
						$valp[$key][$k]['thankYou']=sanitize_text_field( $v['thankYou']);
						$valp[$key][$k]['done']=sanitize_text_field( $v['done']);
						$valp[$key][$k]['trackingCode']=sanitize_text_field( $v['trackingCode']);
						$valp[$key][$k]['pleaseFillInRequiredFields']=sanitize_text_field( $v['pleaseFillInRequiredFields']);
					break;
					case 'c_c':			
						//error_log($valp[$key][$k]);
						foreach ($valp[$key][$k] as $kei => $value) {
							# code...							
							$valp[$key][$k][$kei] = sanitize_text_field($value);
						}
						//$valp[$key][$k]= $key!=0 && $k!="c_c" ||  $valp[$key][$k]= $key!=0 && $k!="c_n" ?
						break;
						case 'c_n':
							//error_log("c_n sanitize_obj_msg_efb=>mobile");
							//error_log(json_encode($valp[$key][$k]));
							foreach ($valp[$key][$k] as $kei => $value) {
								# code...
								//error_log("c_n foreach =====>");
								//error_log($key);
								//error_log($value);
								$valp[$key][$k][$kei] = sanitize_text_field($value);
							}
							//$valp[$key][$k]= $key!=0 && $k!="c_c" ||  $valp[$key][$k]= $key!=0 && $k!="c_n" ?
							break;
					case 'id':
						$valp[$key][$k]= sanitize_text_field($valp[$key][$k]);
						if(strlen($valp[$key][$k])<1) break;
						
						
						//error_log($valp[$key][$k]);
						//error_log($valp[$key]["id_"]);
						if($valp[$key]["type"]=="option"){
							//error_log("iddddddddddddddddddddd===================");
							foreach ($valp as $ki => $vl) {
								$tp = $vl["type"];
								if(array_key_exists('id_',$vl)==false) continue;
								//error_log(json_encode($vl));
								if($vl['id_']!=$valp[$key]["parent"]){
									continue;
								}
								//error_log("_____________________________________________________________");
								//error_log($vl['id_']);
								//error_log(json_encode($vl));
								//error_log($valp[$key]["parent"]);
								foreach ($vl as $kii => $vll) {
									//value
									//error_log("::::::::::::::");
									if($kii!="value") continue;
									//error_log($kii);
									if(gettype($vll)!="array" && gettype($vll)!="object" ){
										if($vll==$valp[$key]["id_"])$vll=$valp[$key][$k];
									}else{
										foreach ($vll as $ke => $vn) {
											//error_log('>>>>>>>>>>>>>>>>>>');
											//error_log($vn);
											# code...
											//$vll[$ke]=sanitize_text_field($va);
											if($vn==$valp[$key]["id_"]) {
												//error_log('<<<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>>');
												//error_log($vn);
												$valp[$ki][$kii][$ke] =$valp[$key][$k];
												//error_log($valp[$ki][$kii][$ke] );
												//error_log($valp[$vll][$k]);
											}

											//error_log($ki);
											//error_log($vn);
										}
									}
									//error_log($vll);
								}
							}
							$valp[$key]["id_old"]=$valp[$key]["id_"];
							$valp[$key]["id_"] = $valp[$key][$k];
							if(isset($valp[$key]["id_op"]))$valp[$key]["id_op"]=$valp[$key][$k];
							if(isset($valp[$key]["dataId"]))$valp[$key]["dataId"]=$valp[$key][$k] ."-id";
							$valp[$key]["option"] = $valp[$key][$k];
						}
					break;
					case 'conditions':
						//$valp[$key][$k]=$v;
						$valp[$key][$k]=$v;
					break;
					default:
					$valp[$key][$k]=sanitize_text_field($v);
					
					break;
				}
			}
		}
		return $valp;
	}//end function


	public function get_geolocation() {		
		  $ip = $this->get_ip_address();
		 return $this->iplocation_efb($ip,1);
	  }

	  public function get_ip_address() {        
        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {$ip = $_SERVER['REMOTE_ADDR'];}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }

	public function iplocation_efb($ip , $state){
		
		$url = "https://api.iplocation.net/?ip=".$ip."";
		$cURL = curl_init();
		$userAgent ;
		if(empty($_SERVER['HTTP_USER_AGENT'])){
			
			$userAgent = array(
				'name' => 'unrecognized',
				'version' => 'unknown',
				'platform' => 'unrecognized',
				'userAgent' => ''
			);
		}else{
			
			$userAgent =$_SERVER['HTTP_USER_AGENT'];
		}
		curl_setopt($cURL, CURLOPT_URL, $url);
		curl_setopt($cURL, CURLOPT_HTTPGET, true);
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Accept: application/json',
			'User-Agent: '.$userAgent
		));
		$location = json_decode(curl_exec($cURL), true); 
		//error_log(json_encode($location));
		if(isset($location)){
			return $state==1 ? $location["country_code2"] :$location  ;
		}else{
			return 0;
		}
		
	}


	   public function addon_adds_cron_efb(){
		//error_log('addon_adds_cron_efb');
		//error_log(wp_next_scheduled( 'download_all_addons_efb' ));
		if ( ! wp_next_scheduled( 'download_all_addons_efb' ) ) {
			wp_schedule_single_event( time() + 1, 'download_all_addons_efb' );
		  }
	   }//addon_adds_cron_efb


	   public function addon_add_efb($value){
		if($value!="AdnOF"){

            // اگر لینک دانلود داشت
            $server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
            $vwp = get_bloginfo('version');
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
            $request = wp_remote_get($u);
            
            if( is_wp_error( $request ) ) {
				//sample_admin_notice__success
				add_action( 'admin_notices', 'admin_notice_msg_efb' );
                //error_log("function.php====> Cannot connect to wp-json of ws.team");
                return false;
            }
            
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body );

             if($data->status==false){
              return false;
               
            }

            // Check version of EFB to Addons
            if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {        
				return false;                
            } 

            if($data->download==true){
                $url =$data->link;
                //$url ="https://easyformbuilder.ir/source/files/zip/stripe.zip";
                $this->fun_addon_new($url);
				return true;
            }
			
        }
	   }//end function

	   public function fun_addon_new($url){
		//download the addon dependency 
		$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
		require_once( $path . 'wp-load.php' );
		require_once (ABSPATH .'wp-admin/includes/admin.php');
		//error_log($url);
		$name =substr($url,strrpos($url ,"/")+1,-4);
		/* 
		 */
		$r =download_url($url);
		if(is_wp_error($r)){
			//show error message
			//error_log(json_encode($r));
		}else{
			$v = rename($r, EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip');
			if(is_wp_error($v)){
				$s = unzip_file($r, EMSFB_PLUGIN_DIRECTORY . '\\vendor\\');
				if(is_wp_error($s)){
				
					error_log('EFB=>unzip addons error 1:');
					error_log(json_encode($r));
					return false;
				}
			}else{
				
				require_once(ABSPATH . 'wp-admin/includes/file.php');
				WP_Filesystem();
				$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
				if(is_wp_error($r)){
				
					//error_log('error unzip');
					//error_log(json_encode($r));
					error_log('EFB=>unzip addons error 2:');
					error_log(json_encode($r));
					return false;
				}
			} 
			return true;           
		}


		//run install php of addons
		$fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/".$name."/".$name.".php"; 
				
		if(file_exists($fl_ex)){         
			$name ='\Emsfb\\'.$name;
			require_once  $fl_ex;  
			$t = new $name();      
		}
		
	}// end function


	public function download_all_addons_efb(){
		//error_log("run download_all_addons_efb");
		
		$ac=$this->get_setting_Emsfb();
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
		foreach ($addons as $key => $value) {
			//error_log($key);
			//error_log($value);
			if($value ==1){
				
				$this->addon_add_efb($key);
			}
		}
	   //
	}


	public function update_message_admin_side_efb(){
		$text = ["wmaddon"];
        $lang= $this->text_efb($text);
		return "<div id='body_efb' class='efb card-public row pb-3 efb'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'>⚠️</h2><h3 class='efb warning text-center text-darkb fs-4'>".$lang["wmaddon"]."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><b>".__('Easy Form Builder', 'easy-form-builder')."</b><p></div></div>";
	}

	function admin_notice_msg_efb($s) {
		$v = __('Easy Form Builder','easy-form-builder');
		$t = "notice-success";
		if($s=="dlproblem"){
			$t = "notice-error";
			$v =__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server','easy-form-builder');
		}else if($s=="unzipproblem"){
			$t = "notice-error";
			$v =__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files','easy-form-builder');

		}
		?>
		<div class="notice <?php $t ?> is-dismissible">
			<p><?php $v ?></p>
		</div>
		<?php
	}


	public function efb_sentence_forms(){
		$r =[
			"s_t" => __('One of the free features','easy-form-builder'),
								
		];
		return $r;
	}

	public function efb_list_forms(){
		$table_name = $this->db->prefix . "emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
			return $value;
	}

	/* section of generate validate code and status of visit and message [start] */
	public function efb_code_validate_create( $fid, $type, $status, $tc) {
		//$fid => form Id
		//$type => form 0 , response 1, sms 2, email 3
		// $status => visit , send , upd , del  =>  max len 5
		//$tc => tracking code if exists 	
		$table_name = $this->db->prefix . 'emsfb_stts_';
		$query =$this->db->prepare( 'SHOW TABLES LIKE %s',$this->db->esc_like( $table_name ) );
		$check_test_table =$query!=null ?$this->db->get_var( $query ) :0;
		if($check_test_table==0){
			$charset_collate =$this->db->get_charset_collate();
			$sql = "CREATE TABLE {$table_name} (
				`id` int(20) NOT NULL AUTO_INCREMENT,
				`sid` varchar(21) COLLATE utf8mb4_unicode_ci NOT NULL,
				`fid` int(11)   NOT NULL, 
				`type_` int(8)  NOT NULL,
				`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,		
				`status` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
				`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
				`os` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
				`browser` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,						
				`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,		
				`uid` int(10)  NOT NULL, 
				`tc` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,	
				`active` int(1)   NOT NULL,						
				PRIMARY KEY  (id)
			) {$charset_collate};";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		$ip = $this->get_ip_address();
		$date_limit = date('Y-m-d H:i:s', strtotime('+24 hours'));
		$date_now = date('Y-m-d H:i:s');
		/* error_log($date_now); */
        $query =$this->db->prepare("SELECT sid FROM {$table_name} WHERE ip = %s AND read_date > %s AND active = %d AND fid = %s", $ip, $date_now,1,$fid);
		$result =$this->db->get_var($query);
		/* error_log(json_encode($query));
		error_log(json_encode($result)); */
		if($result!=null) return $result;
		
        $sid = date("ymdHis").substr(str_shuffle("0123456789_-abcdefghijklmnopqrstuvwxyz"), 0, 9) ;
		$uid = get_current_user_id();
		$os = $this->getVisitorOS();
		$browser =$this->getVisitorBrowser();
        $data = array(
            'sid' => $sid,
            'fid' => $fid,
            'type_' => $type,
            'status' => $status,
            'ip' => $ip,
            'os' => $os,
            'browser' => $browser,
            'uid' => $uid,
            'tc' => $tc,
			'active'=>1,
			'date'=>date('Y-m-d H:i:s'),
			'read_date'=>$date_limit
        );
       $this->db->insert($table_name, $data);
	   return $sid;
    }

    public function efb_code_validate_update($sid ,$status ,$tc ) {
		// $status => visit , send , upd , del => max len 5
		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = date('Y-m-d H:i:s', strtotime('-24 hours'));
		$active =0;
		$read_date =date('Y-m-d H:i:s');
		if($status=="rsp" || $status=="ppay")  $active =1;
		/* $data_= $data = array(
			'status' => $status,
			'active' =>0,
			'read_date'=> date('Y-m-d H:i:s')
        );
		if($tc!="null"){
			error_log("============>tc");
			$data_['tc'] = $tc;
		}
        $where = array(
            'sid' => $sid,
			'active'=>1
            			
        ); */
		//error_log(json_encode($data));
      /* $r= $this->db->update($table_name, $data_,$where); */

	   $sql = "UPDATE $table_name SET status='{$status}', active={$active}, read_date='{$read_date}', tc='{$tc}' WHERE sid='{$sid}' AND active=1";

		$stmt = $this->db->query($sql);
		//$stmt->bindParam(':date_', $$date_limit);
	  
	   return $stmt > 0;
    }

    public function efb_code_validate_select($sid ,$fid) {
		/* error_log("efb_code_validate_select");
		error_log($sid);
		error_log($fid); */
		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $date_now = date('Y-m-d H:i:s');
        $query =$this->db->prepare("SELECT COUNT(*) FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 AND fid = %s", $sid, $date_now,$fid);
		/* error_log(json_encode(  $query)); */
        $result =$this->db->get_var($query);
		//error_log(json_encode(  $result));
        return $result === '1';
    }

	/* section of generate validate code and status of visit and message [end] */
	//$uniqid= date("ymd").substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8) ;
	public function getVisitorOS() {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		$os = "Unknown";
	
		if (strpos($userAgent, 'Windows') !== false) {
			$os = "Windows";
		} elseif (strpos($userAgent, 'Linux') !== false) {
			$os = "Linux";
		} elseif (strpos($userAgent, 'Macintosh') !== false || strpos($userAgent, 'Mac OS X') !== false) {
			$os = "Mac";
		} elseif (strpos($userAgent, 'Android') !== false) {
			$os = "Android";
		} elseif (strpos($userAgent, 'iOS') !== false) {
			$os = "iOS";
		}
	
		return $os;
	}

	public function getVisitorBrowser() {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		$browser = "Unknown";
	
		if (strpos($userAgent, 'Firefox') !== false) {
			$browser = "Mozilla Firefox";
		} elseif (strpos($userAgent, 'Chrome') !== false) {
			if (strpos($userAgent, 'Edg') !== false) {
				$browser = "Microsoft Edge";
			} elseif (strpos($userAgent, 'Brave') !== false) {
				$browser = "Brave";
			} else {
				$browser = "Google Chrome";
			}
		} elseif (strpos($userAgent, 'Safari') !== false) {
			$browser = "Apple Safari";
		} elseif (strpos($userAgent, 'Opera') !== false) {
			$browser = "Opera";
		} elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
			$browser = "Internet Explorer";
		}
	
		return $browser;
	}
}
