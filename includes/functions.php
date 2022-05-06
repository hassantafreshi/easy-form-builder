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
		//isset($test) ? $test:
		$ac= $this->get_setting_Emsfb();		 
		$state= $ac!==null && isset($ac->text) ? true : false ;
	//	error_log($ac);
		
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
			"youCantUseHTMLTagOrBlank" => $state ? $ac->text->youCantUseHTMLTagOrBlank : __('You can not use HTML Tag or send blank messages.','easy-form-builder'),
			"reply" => $state ? $ac->text->reply : __('Reply','easy-form-builder'),
			"messages" => $state ? $ac->text->messages : __('Messages','easy-form-builder'),
			"pleaseWaiting" => $state ? $ac->text->pleaseWaiting : __('Please Waiting','easy-form-builder'),
			"loading" => $state ? $ac->text->loading : __('Loading','easy-form-builder'),
			"remove" => $state ? $ac->text->remove : __('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => $state ? $ac->text->areYouSureYouWantDeleteItem : __('Are you sure you want to delete this item?','easy-form-builder'),
			"no" => $state ? $ac->text->no : __('NO','easy-form-builder'),
			"yes" => $state ? $ac->text->yes : __('Yes','easy-form-builder'),
			"numberOfSteps" => $state ? $ac->text->numberOfSteps : __('Number of steps','easy-form-builder'),
			"titleOfStep" => $state ? $ac->text->titleOfStep : __('Title of step','easy-form-builder'),
			"proVersion" => $state ? $ac->text->proVersion : __('Pro Version','easy-form-builder'),
			"getProVersion" => $state ? $ac->text->getProVersion : __('Get Pro version','easy-form-builder'),					
			"reCAPTCHA" => $state ? $ac->text->reCAPTCHA : __('reCAPTCHA','easy-form-builder'),
			"protectsYourWebsiteFromFraud" => $state ? $ac->text->protectsYourWebsiteFromFraud : __('Click here to watch a video tutorial.','easy-form-builder'),
			"enterSITEKEY" => $state ? $ac->text->enterSITEKEY : __('SECRET KEY','easy-form-builder'),
			"alertEmail" => $state ? $ac->text->alertEmail : __('Alert Email','easy-form-builder'),
			"enterAdminEmail" => $state ? $ac->text->enterAdminEmail : __('Enter Admin Email to receive email notification','easy-form-builder'),
			"showTrackingCode" => $state ? $ac->text->showTrackingCode : __('Show Confirmation Code','easy-form-builder'),
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : __('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : __('Copy and Paste below short-code of Confirmation Code finder in any page or post.','easy-form-builder'),
			"save" => $state ? $ac->text->save : __('Save','easy-form-builder'),
			"waiting" => $state ? $ac->text->waiting : __('Waiting','easy-form-builder'),
			"saved" => $state ? $ac->text->saved : __('Saved','easy-form-builder'),
			"stepName" => $state ? $ac->text->stepName : __('Step Name','easy-form-builder'),
			"IconOfStep" => $state ? $ac->text->IconOfStep : __('Icon of step','easy-form-builder'),
			"stepTitles" => $state ? $ac->text->stepTitles : __('Step Titles','easy-form-builder'),
			"elements" => $state ? $ac->text->elements : __('Elements:','easy-form-builder'),
			"delete" => $state ? $ac->text->delete : __('Delete','easy-form-builder'),
			"newOption" => $state ? $ac->text->newOption : __('New option','easy-form-builder'),
			"media" => $state ? $ac->text->media : __('(Video or Audio)','easy-form-builder'),
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
			"availableInProversion" => $state ? $ac->text->availableInProversion : __('This option is available in Pro version','easy-form-builder'),
			"formNotBuilded" => $state ? $ac->text->formNotBuilded : __('The form has not been builded!','easy-form-builder'),
			"someStepsNotDefinedCheck" => $state ? $ac->text->someStepsNotDefinedCheck : __('Some steps not defined, Please check:','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => $state ? $ac->text->ifYouNeedCreateMoreThan2Steps : __('If you need create more than 2 Steps, activate ','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwo" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwo : __('You can create minimum 1 and maximum 2 Steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwenty" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwenty : __('You Could create minimum 1 Step and maximum 20 Step','easy-form-builder'),
			"preview" => $state ? $ac->text->preview : __('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => $state ? $ac->text->somethingWentWrongPleaseRefresh : __('Something went wrong, Please refresh and try again','easy-form-builder'),
			"formNotCreated" => $state ? $ac->text->formNotCreated : __('The form has not been created!','easy-form-builder'),
			"atFirstCreateForm" => $state ? $ac->text->atFirstCreateForm : __('At first create a form and add elements then try again','easy-form-builder'),
			"allowMultiselect" => $state ? $ac->text->allowMultiselect : __('Allow multi-select','easy-form-builder'),
			"DragAndDropUI" => $state ? $ac->text->DragAndDropUI : __('Drag and drop UI','easy-form-builder'),
			"clickHereForActiveProVesrsion" => $state ? $ac->text->clickHereForActiveProVesrsion : __('Click here for Active Pro version','easy-form-builder'),
			"selectOpetionDisabled" => $state ? $ac->text->selectOpetionDisabled : __('Select anoption (Disabled in test view)','easy-form-builder'),
			"pleaseEnterTheTracking" => $state ? $ac->text->pleaseEnterTheTracking : __('Please enter the Confirmation Code','easy-form-builder'),									
			"formNotFound" => $state ? $ac->text->formNotFound : __('Form is not found','easy-form-builder'),
			"errorV01" => $state ? $ac->text->errorV01 : __('Error Code:V01','easy-form-builder'),
			"password8Chars" => $state ? $ac->text->password8Chars : __('Password must be at least 8 characters','easy-form-builder'),
			"registered" => $state ? $ac->text->registered : __('Registered','easy-form-builder'),
			"yourInformationRegistered" => $state ? $ac->text->yourInformationRegistered : __('Your information is successfully registered','easy-form-builder'),
			"youNotPermissionUploadFile" => $state ? $ac->text->youNotPermissionUploadFile : __('You do not have permission to upload this file:','easy-form-builder'),
			"pleaseUploadA" => $state ? $ac->text->pleaseUploadA : __('Please upload the','easy-form-builder'),
			"please" => $state ? $ac->text->please : __('Please','easy-form-builder'),
			"trackingForm" => $state ? $ac->text->trackingForm : __('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => $state ? $ac->text->trackingCodeIsNotValid : __('Confirmation Code is not valid.','easy-form-builder'),
			"checkedBoxIANotRobot" => $state ? $ac->text->checkedBoxIANotRobot : __('Please Checked Box of I am Not robot','easy-form-builder'),
			"howConfigureEFB" => $state ? $ac->text->howConfigureEFB : __('How to configure Easy Form Builder','easy-form-builder'),
			"howGetGooglereCAPTCHA" => $state ? $ac->text->howGetGooglereCAPTCHA : __('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
			"howActivateAlertEmail" => $state ? $ac->text->howActivateAlertEmail : __('How to activate the alert email for new form submission','easy-form-builder'),
			"howCreateAddForm" => $state ? $ac->text->howCreateAddForm : __('How to create and add a form with Easy Form Builder','easy-form-builder'),
			"howActivateTracking" => $state ? $ac->text->howActivateTracking : __('How to activate a Confirmation Code in Easy Form Builder','easy-form-builder'),
			"howWorkWithPanels" => $state ? $ac->text->howWorkWithPanels : __('How to work with panels in Easy Form Builder','easy-form-builder'),
			"points" => $state ? $ac->text->points : __('points','easy-form-builder'),
			"howAddTrackingForm" => $state ? $ac->text->howAddTrackingForm : __('How to add The Confirmation Code Finder to a post, page, or custom post type','easy-form-builder'),
			"howFindResponse" => $state ? $ac->text->howFindResponse : __('How to find a response through a Confirmation Code','easy-form-builder'),
			"pleaseEnterVaildValue" => $state ? $ac->text->pleaseEnterVaildValue : __('Please enter a valid value','easy-form-builder'),
			"step" => $state ? $ac->text->step : __('Step','easy-form-builder'),
			"advancedCustomization" => $state ? $ac->text->advancedCustomization : __('Advanced customization','easy-form-builder'),
			"orClickHere" => $state ? $ac->text->orClickHere : __(' or click here','easy-form-builder'),
			"downloadCSVFile" => $state ? $ac->text->downloadCSVFile : __(' Download CSV file','easy-form-builder'),
			"downloadCSVFileSub" => $state ? $ac->text->downloadCSVFileSub : __('Download CSV file of subscriptions','easy-form-builder'),
			"login" => $state ? $ac->text->login : __('Login','easy-form-builder'),
			"thisInputLocked" => $state ? $ac->text->thisInputLocked : __('this input is locked','easy-form-builder'),
			"thisElemantAvailableRemoveable" => $state ? $ac->text->thisElemantAvailableRemoveable : __('This element is available and removable.','easy-form-builder'),
			"thisElemantWouldNotRemoveableLoginform" => $state ? $ac->text->thisElemantWouldNotRemoveableLoginform : __('This element would not removable in Login form.','easy-form-builder'),
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
			"tobeginSentence" => $state ? $ac->text->tobeginSentence : __('To begin, you should to be Create a from into Easy Form Builder Plugin. for create a form click below button.','easy-form-builder'),
			"efbIsTheUserSentence" => $state ? $ac->text->efbIsTheUserSentence : __('Easy Form Builder is the user-friendly form creator that allows you to create professional multistep forms within minutes.','easy-form-builder'),
			"efbYouDontNeedAnySentence" => $state ? $ac->text->efbYouDontNeedAnySentence : __(' You do not need any coding skills to use Easy Form Builder. Simply drag and drop your layouts into order to easily create unlimited custom multistep forms. A unique Confirmation Code allows you to connect any submission to an individual request.','easy-form-builder'),
			"newResponse" => $state ? $ac->text->newResponse : __('New Response','easy-form-builder'),
			"read" => $state ? $ac->text->read : __('Read','easy-form-builder'),
			"copy" => $state ? $ac->text->copy : __('Copy','easy-form-builder'),
			"general" => $state ? $ac->text->general : __('General','easy-form-builder'),
			"dadFieldHere" => $state ? $ac->text->dadFieldHere : __('Drag & Drop Fields Here','easy-form-builder'),
			"help" => $state ? $ac->text->help : __('Help','easy-form-builder'),
			"setting" => $state ? $ac->text->setting : __('Setting','easy-form-builder'),
			"maps" => $state ? $ac->text->maps : __('Maps','easy-form-builder'),
			"youCanFindTutorial" => $state ? $ac->text->youCanFindTutorial : __('You can find tutorial in beside box and if you need more tutorials click on <Document> button.','easy-form-builder'),
			"proUnlockMsg" => $state ? $ac->text->proUnlockMsg : __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder'),
			"aPIKey" => $state ? $ac->text->aPIKey : __('API KEY','easy-form-builder'),
			"youNeedAPIgMaps" => $state ? $ac->text->youNeedAPIgMaps : __('You need API key of Google Maps if you want to use Maps in forms.','easy-form-builder'),
			"copiedClipboard" => $state ? $ac->text->copiedClipboard : __('Copied to Clipboard','easy-form-builder'),
			"noResponse" => $state ? $ac->text->noResponse : __('No Response','easy-form-builder'),
			"offerGoogleCloud" => $state ? $ac->text->offerGoogleCloud : __('For using reCAPTCHA and location picker(Maps) sign up in Google cloud service, you will get USD $350 worth of credits in Google Cloud only for our users,','easy-form-builder'),
			"getOfferTextlink" => $state ? $ac->text->getOfferTextlink : __(' Click here to get credits.','easy-form-builder'),
			"clickHere" => $state ? $ac->text->clickHere : __('Click here','easy-form-builder'),
			"SpecialOffer" => $state ? $ac->text->SpecialOffer : __('Special offer','easy-form-builder'),
			"googleKeys" => $state ? $ac->text->googleKeys : __('Google Keys','easy-form-builder'),
			"emailServer" => $state ? $ac->text->emailServer : __('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : __('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'),
			"emailSetting" => $state ? $ac->text->emailSetting : __('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : __('Click To Check Email Server','easy-form-builder'),
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
			"youDoNotAddAnyInput" => $state ? $ac->text->youDoNotAddAnyInput : __('You do not add any field','easy-form-builder'),
			"copyShortcode" => $state ? $ac->text->copyShortcode : __('Copy ShortCode','easy-form-builder'),
			"shortcode" => $state ? $ac->text->shortcode : __('ShortCode','easy-form-builder'),
			"copyTrackingcode" => $state ? $ac->text->copyTrackingcode : __('Copy Confirmation Code','easy-form-builder'),
			"previewForm" => $state ? $ac->text->previewForm : __('Preview Form','easy-form-builder'),
			"activateProVersion" => $state ? $ac->text->activateProVersion : __('Activate Pro Version','easy-form-builder'),
			"itAppearedStepsEmpty" => $state ? $ac->text->itAppearedStepsEmpty : __('It is appeared some steps are empty','easy-form-builder'),
			"youUseProElements" => $state ? $ac->text->youUseProElements : __('You are Using Pro version field. For saving this element in the form, activate Pro version.','easy-form-builder'),
			"sampleDescription" => $state ? $ac->text->sampleDescription : __('Sample description','easy-form-builder'),
			"fieldAvailableInProversion" => $state ? $ac->text->fieldAvailableInProversion : __('This field available in Pro version','easy-form-builder'),
			"editField" => $state ? $ac->text->editField : __('Edit Field','easy-form-builder'),
			"description" => $state ? $ac->text->description : __('Description','easy-form-builder'),
			"thisEmailNotificationReceive" => $state ? $ac->text->thisEmailNotificationReceive : __('Enable email notification','easy-form-builder'),
			"activeTrackingCode" => $state ? $ac->text->activeTrackingCode : __('Active Confirmation Code','easy-form-builder'),
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : __('Add Google reCAPTCHA to the form ','easy-form-builder'),
			"dontShowIconsStepsName" => $state ? $ac->text->dontShowIconsStepsName : __('Do not show Icons & Steps name','easy-form-builder'),
			"dontShowProgressBar" => $state ? $ac->text->dontShowProgressBar : __('Do not show progress bar','easy-form-builder'),
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
			"enterAdminEmailReceiveNoti" => $state ? $ac->text->enterAdminEmailReceiveNoti : __('Enter Admin Email to receive email notification','easy-form-builder'),
			"corners" => $state ? $ac->text->corners : __('Corners','easy-form-builder'),
			"rounded" => $state ? $ac->text->rounded : __('Rounded','easy-form-builder'),
			"square" => $state ? $ac->text->square : __('Square','easy-form-builder'),
			"icon" => $state ? $ac->text->icon : __('Icon','easy-form-builder'),
			"buttonColor" => $state ? $ac->text->buttonColor : __('Button Color','easy-form-builder'),
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
			"pleaseDoNotAddJsCode" => $state ? $ac->text->pleaseDoNotAddJsCode : __('(Please do not add Javascript or jQuery codes to html codes for security reasons)','easy-form-builder'),
			"button1Value" => $state ? $ac->text->button1Value : __('Button 1 value','easy-form-builder'),
			"button2Value" => $state ? $ac->text->button2Value : __('Button 2 value','easy-form-builder'),
			"iconList" => $state ? $ac->text->iconList : __('Icons list','easy-form-builder'),
			"previous" => $state ? $ac->text->previous : __('Previous','easy-form-builder'),
			"next" => $state ? $ac->text->next : __('Next','easy-form-builder'),
			"noCodeAddedYet" => $state ? $ac->text->noCodeAddedYet : __('Code has not yet been added. Click on','easy-form-builder'),
			"andAddingHtmlCode" => $state ? $ac->text->andAddingHtmlCode : __(' and adding html code.','easy-form-builder'),
			"proMoreStep" => $state ? $ac->text->proMoreStep : __('When you activate pro version, so you can create unlimited form steps.','easy-form-builder'),
			"aPIkeyGoogleMapsError" => $state ? $ac->text->aPIkeyGoogleMapsError : __('API key of Google maps has not been added. Please add API key of google maps on Easy Form Builder > Panel > setting > Google Keys and try again.','easy-form-builder'),
			"howToAddGoogleMap" => $state ? $ac->text->howToAddGoogleMap : __('How to Add Google maps to Easy form Builder WordPress Plugin','easy-form-builder'),
			"deletemarkers" => $state ? $ac->text->deletemarkers : __('Delete markers','easy-form-builder'),
			"updateUrbrowser" => $state ? $ac->text->updateUrbrowser : __('update your browser','easy-form-builder'),
			"stars" => $state ? $ac->text->stars : __('Stars','easy-form-builder'),
			"nothingSelected" => $state ? $ac->text->nothingSelected : __('Nothing selected','easy-form-builder'),
			"duplicate" => $state ? $ac->text->duplicate : __('Duplicate','easy-form-builder'),
			"availableProVersion" => $state ? $ac->text->availableProVersion : __('Available in pro version','easy-form-builder'),
			"mobilePreview" => $state ? $ac->text->mobilePreview : __('Mobile Preview','easy-form-builder'),
			"thanksFillingOutform" => $state ? $ac->text->thanksFillingOutform : __('Thanks for filling out our form.','easy-form-builder'),
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
			"pleaseReporProblem" => $state ? $ac->text->pleaseReporProblem : __('Please report the following problem to Easy Form builder team','easy-form-builder'),
			"reportProblem" => $state ? $ac->text->reportProblem : __('Report problem','easy-form-builder'),
			"ddate" => $state ? $ac->text->ddate : __('Date','easy-form-builder'),
			"serverEmailAble" => $state ? $ac->text->serverEmailAble : __('Your e-mail server able to send Emails','easy-form-builder'),
			"sMTPNotWork" => $state ? $ac->text->sMTPNotWork : __('SMTP Error, the host is not able send email . Please contact host support','easy-form-builder'),
			"aPIkeyGoogleMapsFeild" => $state ? $ac->text->aPIkeyGoogleMapsFeild : __('Google Maps Loading Errors.','easy-form-builder'),
			"fileIsNotRight" => $state ? $ac->text->fileIsNotRight : __('The file is not the right file type','easy-form-builder'),
			"thisElemantNotAvailable" => $state ? $ac->text->thisElemantNotAvailable : __('The Field is not available in this type forms','easy-form-builder'),
			"numberSteps" => $state ? $ac->text->numberSteps : __('Edit','easy-form-builder'),
			"clickHereGetActivateCode" => $state ? $ac->text->clickHereGetActivateCode : __('Click here to get Activate Code.','easy-form-builder'),			
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
			"clearUnnecessaryFiles" => $state ? $ac->text->clearUnnecessaryFiles : __('Clear unnecessary files','easy-form-builder'),
			"youCanRemoveUnnecessaryFileUploaded" => $state ? $ac->text->youCanRemoveUnnecessaryFileUploaded : __('You can Remove unnecessary file uploaded by user with below button','easy-form-builder'),			
			"whenEasyFormBuilderRecivesNewMessage" => $state ? $ac->text->whenEasyFormBuilderRecivesNewMessage : __('When Easy Form Builder receives a new message, It will send an  alert email to admin of plugin.','easy-form-builder'),
			"reCAPTCHAv2" => $state ? $ac->text->reCAPTCHAv2 : __('reCAPTCHA v2','easy-form-builder'),					
			"clickHereWatchVideoTutorial" => $state ? $ac->text->clickHereWatchVideoTutorial : __('Click here to watch a video tutorial.','easy-form-builder'),
			"siteKey" => $state ? $ac->text->siteKey : __('SITE KEY','easy-form-builder'),			
			"SecreTKey" => $state ? $ac->text->SecreTKey : __('SECRET KEY','easy-form-builder'),
			"EnterSECRETKEY" => $state ? $ac->text->EnterSECRETKEY : __('Enter the SECRET KEY','easy-form-builder'),
			"clearFiles" => $state ? $ac->text->clearFiles : __('Clear Files','easy-form-builder'),			
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : __('Enter the Activate Code','easy-form-builder'),			
			"error" => $state ? $ac->text->error : __('Error,','easy-form-builder'),
			"somethingWentWrongTryAgain" => $state ? $ac->text->somethingWentWrongTryAgain : __('Something went wrong, Please refresh and try again','easy-form-builder'),										
			"enterThePhone" => $state ? $ac->text->enterThePhone : __('Please Enter the phone number','easy-form-builder'),
			"pleaseMakeSureAllFields" => $state ? $ac->text->pleaseMakeSureAllFields : __('Please make sure all fields are filled in correctly.','easy-form-builder'),
			"enterTheEmail" => $state ? $ac->text->enterTheEmail : __('Please Enter the Email address','easy-form-builder'),			
			"fileSizeIsTooLarge" => $state ? $ac->text->fileSizeIsTooLarge : __('The file size is too large , Allowed Maximum size is 8MB.','easy-form-builder'),
			"documents" => $state ? $ac->text->documents : __('Documents','easy-form-builder'),
			"document" => $state ? $ac->text->document : __('Document','easy-form-builder'),
			"image" => $state ? $ac->text->image : __('Image','easy-form-builder'),
			"media" => $state ? $ac->text->media : __('Media','easy-form-builder'),
			"zip" => $state ? $ac->text->zip : __('Zip','easy-form-builder'),				
			"alert" => $state ? $ac->text->alert : __('Alert!','easy-form-builder'),			
			"pleaseWatchTutorial" => $state ? $ac->text->pleaseWatchTutorial : __('Please watch this tutorial','easy-form-builder'),
			"formIsNotShown" => $state ? $ac->text->formIsNotShown : __('The form is not shown, Because You Have not added Google recaptcha at setting of Easy Form Builder Plugin.','easy-form-builder'),
			"errorVerifyingRecaptcha" => $state ? $ac->text->errorVerifyingRecaptcha : __('Captcha Verification Failed','easy-form-builder'),			
			"enterThePassword" => $state ? $ac->text->enterThePassword : __('Password must be at least 8 characters long contain a number and an uppercase letter','easy-form-builder'),
			"PleaseFillForm" => $state ? $ac->text->PleaseFillForm : __('Please fill in the form.','easy-form-builder'),
			"selectOption" => $state ? $ac->text->selectOption : __('Select an option','easy-form-builder'),
			"selected" => $state ? $ac->text->selected : __('Selected','easy-form-builder'),
			"selectedAllOption" => $state ? $ac->text->selectedAllOption : __('Select All','easy-form-builder'),
			"sentSuccessfully" => $state ? $ac->text->sentSuccessfully : __('Sent successfully','easy-form-builder'),
			"sync" => $state ? $ac->text->sync : __('Sync','easy-form-builder'),
			"enterTheValueThisField" => $state ? $ac->text->enterTheValueThisField : __('This field is required.','easy-form-builder'),
			"thankYou" => $state ? $ac->text->thankYou : __('Thank you','easy-form-builder'),
			"YouSubscribed" => $state ? $ac->text->YouSubscribed : __('You are subscribed','easy-form-builder'),
			"passwordRecovery" => $state ? $ac->text->passwordRecovery : __('Password recovery','easy-form-builder'),
			"info" => $state ? $ac->text->info : __('information','easy-form-builder'),						
			"waitingLoadingRecaptcha" => $state ? $ac->text->waitingLoadingRecaptcha : __('Waiting for loading recaptcha','easy-form-builder'),
			"on" => $state ? $ac->text->on : __('On','easy-form-builder'),
			"off" => $state ? $ac->text->off : __('Off','easy-form-builder'),
			"settingsNfound" => $state ? $ac->text->settingsNfound : __('Settings not found','easy-form-builder'),
			"content" => $state ? $ac->text->content : __('Content','easy-form-builder'),
			"red" => $state ? $ac->text->red : __('Red','easy-form-builder'),
			"reCAPTCHASetError" => $state ? $ac->text->reCAPTCHASetError : __('Please go to Easy Form Builder Panel > Setting > Google Keys  and set Keys of Google reCAPTCHA','easy-form-builder'),
			"ifShowTrackingCodeToUser" => $state ? $ac->text->ifShowTrackingCodeToUser : __("If you do not want to show Confirmation Code to users, do not mark below option.",'easy-form-builder'),
			"videoOrAudio" => $state ? $ac->text->videoOrAudio : __('(Video or Audio)','easy-form-builder'),			
			"localization" => $state ? $ac->text->localization : __('Localization','easy-form-builder'),
			"translateLocal" => $state ? $ac->text->translateLocal : __('You can localize Easy Form Builder in Your language by translating below sentences. WARNING: If your wordpress site is multi language so DO Not Change Below Values.','easy-form-builder'),
			"enterValidURL" => $state ? $ac->text->enterValidURL : __('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"emailOrUsername" => $state ? $ac->text->emailOrUsername : __('Email or Username','easy-form-builder'),
			"contactusForm" => $state ? $ac->text->contactusForm : __('Contact-us Form','easy-form-builder'),
			"clear" => $state ? $ac->text->clear : __('Clear','easy-form-builder'),
			"entrTrkngNo" => $state ? $ac->text->entrTrkngNo : __('Enter the Confirmation Code','easy-form-builder'),
			"search" => $state ? $ac->text->search : __('Search','easy-form-builder'),
			"enterThePhones" => $state ? $ac->text->enterThePhones : __('Enter The Phone No','easy-form-builder'),
			"conturyList" => $state ? $ac->text->conturyList : __('Countries list','easy-form-builder'),
			"stateProvince" => $state ? $ac->text->stateProvince : __('States/ Provinces','easy-form-builder'),
			"thankYouMessage" => $state ? $ac->text->thankYouMessage : __('Thank you message','easy-form-builder'),
			"newMessage" => $state ? $ac->text->newMessage : __('New message!', 'easy-form-builder'),
			"newMessageReceived" => $state ? $ac->text->newMessageReceived : __('A New Message has been Received.', 'easy-form-builder'),
			"createdBy" => $state ? $ac->text->createdBy : __('Created by','easy-form-builder'),
			"hiUser" => $state ? $ac->text->hiUser : __('Hi Dear User', 'easy-form-builder'),
			"sentBy" => $state ? $ac->text->sentBy : __("Sent by:",'easy-form-builder'),
			"youRecivedNewMessage" => $state ? $ac->text->youRecivedNewMessage : __('You have received New Message', 'easy-form-builder'),
			"formNExist" => $state ? $ac->text->formNExist : __('Form does not exist !!','easy-form-builder'),
			"error403" => $state ? $ac->text->error403 : __('Security Error 403','easy-form-builder'),
			"formPrivateM" => $state ? $ac->text->formPrivateM : __('Form is Private, Please Login.','easy-form-builder'),
			"errorSiteKeyM" => $state ? $ac->text->errorSiteKeyM : __('Error, Check site Key and secret Key on Easy Form Builder > Panel > Setting > Google Keys','easy-form-builder'),
			"errorCaptcha" => $state ? $ac->text->errorCaptcha : __('Error, Captcha problem!','easy-form-builder'),
			"createAcountDoneM" => $state ? $ac->text->createAcountDoneM : __('Your account has been successfully created! You will receive an email containing your information','easy-form-builder'),
			"incorrectUP" => $state ? $ac->text->incorrectUP : __('The username or password is incorrect','easy-form-builder'),
			"newPassM" => $state ? $ac->text->newPassM : __('If your email is correct, the new password will send to your email.','easy-form-builder'),
			"surveyComplatedM" => $state ? $ac->text->surveyComplatedM : __('survey has been completed','easy-form-builder'),
			"error405" => $state ? $ac->text->error405 : __('Security Error 405','easy-form-builder'),
			"errorSettingNFound" => $state ? $ac->text->errorSettingNFound : __('Error,Setting not Found','easy-form-builder'),
			"errorMRobot" => $state ? $ac->text->errorMRobot : __('Error, Are you a robot?','easy-form-builder'),
			"enterVValue" => $state ? $ac->text->enterVValue : __('Please enter vaild values','easy-form-builder'),
			"cCodeNFound" => $state ? $ac->text->cCodeNFound : __('Confirmation Code not found!','easy-form-builder'),
			"errorFilePer" => $state ? $ac->text->errorFilePer : __('File Permissions Error','easy-form-builder'),
			"errorSomthingWrong" => $state ? $ac->text->errorSomthingWrong : __('Something went wrong ,Please refresh and try again','easy-form-builder'),
			"nAllowedUseHtml" => $state ? $ac->text->nAllowedUseHtml : __('You are not allowed use HTML tag','easy-form-builder'),
			"messageSent" => $state ? $ac->text->messageSent : __('Message was sent','easy-form-builder'),
			"WeRecivedUrM" => $state ? $ac->text->WeRecivedUrM : __('We received your Message','easy-form-builder'),
			"thankFillForm" => $state ? $ac->text->thankFillForm : __('Thank You for filling out the form','easy-form-builder'),
			"thankRegistering" => $state ? $ac->text->thankRegistering : __('Thank You for registering.','easy-form-builder'),
			"welcome" => $state ? $ac->text->welcome : __('Welcome','easy-form-builder'),
			"thankSubscribing" => $state ? $ac->text->thankSubscribing : __('Thank You For Subscribing!','easy-form-builder'),
			"thankDonePoll" => $state ? $ac->text->thankDonePoll : __('Thank You for taking the time to complete this survey.','easy-form-builder'),
			"goToEFBAddEmailM" => $state ? $ac->text->goToEFBAddEmailM : __('Please go to Easy Form Builder panel > setting > Email Settings  and Click on <<Click To Check Email Server>>','easy-form-builder'),
			"errorCheckInputs" => $state ? $ac->text->errorCheckInputs : __('Something went wrong,Please check all input','easy-form-builder'),
			"formNcreated" => $state ? $ac->text->formNcreated : __('The form is not Created!','easy-form-builder'),
			"NAllowedscriptTag" => $state ? $ac->text->NAllowedscriptTag : __('You are not allowed use Scripts tag','easy-form-builder'),
			"bootStrapTemp" => $state ? $ac->text->bootStrapTemp : __('BootStrap Template','easy-form-builder'),
			"iUsebootTempW" => $state ? $ac->text->iUsebootTempW : __('Warning, if your template have used BootStrap then Checked below option','easy-form-builder'),
			"iUsebootTemp" => $state ? $ac->text->iUsebootTemp : __('My template have used BootStrap','easy-form-builder'),
			"invalidRequire" => $state ? $ac->text->invalidRequire : __('Invalid require, Please Check everything','easy-form-builder'),
			"updated" => $state ? $ac->text->updated : __('updated','easy-form-builder'),
			"PEnterMessage" => $state ? $ac->text->PEnterMessage : __('Please enter a message','easy-form-builder'),
			"fileDeleted" => $state ? $ac->text->fileDeleted : __('Files are Deleted','easy-form-builder'),
			"activationNcorrect" => $state ? $ac->text->activationNcorrect : __('Your activation code is not Correct!','easy-form-builder'),
			"localizationM" => $state ? $ac->text->localizationM : __('You can localization the plugin in this path Panel >> Setting >> localization','easy-form-builder'),
			"MMessageNSendEr" => $state ? $ac->text->MMessageNSendEr : __('Message was not sent! Please contact Admin, Settings Error','easy-form-builder'),
			"warningBootStrap" => $state && isset($ac->text->warningBootStrap) ? $ac->text->warningBootStrap : __('Your template base on Bootstrap so go to Panel >> Settings >> selected option:  My template have used BootStrap framework >> Save','easy-form-builder'),
			"or" => $state  && isset($ac->text->or)? $ac->text->or : __('OR','easy-form-builder'),
			"emailTemplate" => $state  &&  isset($ac->text->emailTemplate) ? $ac->text->emailTemplate : __('Email Template','easy-form-builder'),
			"reset" => $state  &&  isset($ac->text->reset) ? $ac->text->reset : __('reset','easy-form-builder'),
			"freefeatureNotiEmail" => $state  &&  isset($ac->text->freefeatureNotiEmail) ? $ac->text->freefeatureNotiEmail : __('Free feature of sending notification email to admin or user.','easy-form-builder'),
			"notFound" => $state  &&  isset($ac->text->notFound) ? $ac->text->notFound : __('Not Found','easy-form-builder'),
			"editor" => $state  &&  isset($ac->text->editor) ? $ac->text->editor : __('Editor','easy-form-builder'),
			"addSCEmailM" => $state  &&  isset($ac->text->addSCEmailM) ? $ac->text->addSCEmailM : __('Please have add these shortcodes; shortcode_message and shortcode_title in email template.','easy-form-builder'),
			"ChrlimitEmail" => $state  &&  isset($ac->text->ChrlimitEmail) ? $ac->text->ChrlimitEmail : __('Email Template have a limit of 10,000 characters','easy-form-builder'),
			"pleaseEnterVaildEtemp" => $state  &&  isset($ac->text->pleaseEnterVaildEtemp) ? $ac->text->pleaseEnterVaildEtemp : __('Please enter html tags for email template','easy-form-builder'),
			"infoEmailTemplates" => $state  &&  isset($ac->text->infoEmailTemplates) ? $ac->text->infoEmailTemplates : __('Use HTML 2 to create a email template and you can use the following short codes, Note that short codes starred must be in the form of an email template','easy-form-builder'),
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : __('Add this short code on a tag for show title of email.','easy-form-builder'),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : __('Add this short code on a tag for show message content of email.','easy-form-builder'),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : __('Add this short code on a tag for show the Website name.','easy-form-builder'),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : __('Add this short code on a tag for show the Website URL on a tag.','easy-form-builder'),
			"shortcodeAdminEmailInfo" => $state  &&  isset($ac->text->shortcodeAdminEmailInfo) ? $ac->text->shortcodeAdminEmailInfo : __('Add this short code on a tag for show the Admin Email address of WordPress on a tag.','easy-form-builder'),
			"noticeEmailContent" => $state  &&  isset($ac->text->noticeEmailContent) ? $ac->text->noticeEmailContent : __('Notice if the Editor be blank the default Email Template is used.','easy-form-builder'),
			"templates" => $state  &&  isset($ac->text->templates) ? $ac->text->templates : __('Templates','easy-form-builder'),
			"maxSelect" => $state  &&  isset($ac->text->maxSelect) ? $ac->text->maxSelect : __('Max selection','easy-form-builder'),
			"minSelect" => $state  &&  isset($ac->text->minSelect) ? $ac->text->minSelect : __('Min selection','easy-form-builder'),
			"dNotShowBg" => $state  &&  isset($ac->text->dNotShowBg) ? $ac->text->dNotShowBg : __('Do not Show BackGround','easy-form-builder'),
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
			"chargeBilling" => $state  &&  isset($ac->text->chargeBilling) ? $ac->text->chargeBilling : __('Charge billing','easy-form-builder'),
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
			"transctionId" => $state  &&  isset($ac->text->transctionId) ? $ac->text->transctionId : __('Transction Id','easy-form-builder'),
			"addPaymentGetway" => $state  &&  isset($ac->text->addPaymentGetway) ? $ac->text->addPaymentGetway : __('Error,You need to add payment gateway to the form','easy-form-builder'),
			"emptyCartM" => $state  &&  isset($ac->text->emptyCartM) ? $ac->text->emptyCartM : __('Your cart is empty , Please add a few items','easy-form-builder'),
			"payCheckbox" => $state  &&  isset($ac->text->payCheckbox) ? $ac->text->payCheckbox : __('Payment Multi choose','easy-form-builder'),
			"payRadio" => $state  &&  isset($ac->text->payRadio) ? $ac->text->payRadio : __('Payment Single choose','easy-form-builder'),
			"paySelect" => $state  &&  isset($ac->text->paySelect) ? $ac->text->paySelect : __('Payment Select choose','easy-form-builder'),
			"payMultiselect" => $state  &&  isset($ac->text->payMultiselect) ? $ac->text->payMultiselect : __('Payment dropdown list','easy-form-builder'),
			"errorCode" => $state  &&  isset($ac->text->errorCode) ? $ac->text->errorCode : __('Error Code','easy-form-builder'),
			"stripeKeys" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : __('Stripe Keys','easy-form-builder'),
			"stripeMP" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : __('You need stripe keys if you want to use payment in forms.','easy-form-builder'),
			"publicKey" => $state  &&  isset($ac->text->publicKey) ? $ac->text->publicKey : __('Public Key','easy-form-builder'),
			"thank" => $state  &&  isset($ac->text->thank) ? $ac->text->thank : __('Thank','easy-form-builder'),
			
			


			
		];

		//error_log(gettype($inp));
		$rtrn =[];
		$st="null";
		if(gettype($inp) =="array"){
			foreach ($inp as $key => $value) {
				//	$rtrn +=["".$value.""=>"".str_replace('"' ,"'","".$lang[$value]).""];
				$rtrn +=["".$value.""=>"".$lang[$value].""];
			}

		}else{
			foreach ($lang as $key => $value) {
				//	$rtrn +=["".$value.""=>"".str_replace('"' ,"'","".$lang[$value]).""];
				/* errorlog($value);
				errorlog($key); */
				$rtrn +=["".$key.""=>"".$value.""];
			}
		}
		array_push($rtrn);
		
		//error_log(json_encode($rtrn));
			return $rtrn;
	}

	public function send_email_state($to ,$sub ,$cont,$pro,$state){
			
				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);
			   $mailResult = "n";
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
			
				//if($to=="null" || is_null($to)<5 ){$to=$support;}
				   
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
		
		$text = ["getProVersion","sentBy","hiUser","trackingCode","newMessage","createdBy","newMessageReceived","goodJob","createdBy" , "proUnlockMsg"];
        $lang= $this->text_efb($text);		
		
		$footer= "<a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'> <img src='https://plugins.svn.wordpress.org/easy-form-builder/assets/icon-256x256.png' style='margin:0px 5px;  width:16px;height:16px' > ".  __('Easy Form Builder' , 'easy-form-builder')."</a> <br>
		<a class='subtle-link' target='_blank' href='https://whitestudio.team/'> <image src='https://plugins.svn.wordpress.org/easy-form-builder/assets/ws.png' style='margin:0px 5px; width:16px;height:16px'>". $lang["createdBy"]. " White Studio Team</a>";
		$header = " <a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'> <img src='https://plugins.svn.wordpress.org/easy-form-builder/assets/icon-256x256.png' style='margin:0px 5px; width:25px;height:25px' >". __('Easy Form Builder' , 'easy-form-builder')."</a>";
		if($pro!="not pro"){
			$footer= "<a class='subtle-link' target='_blank' href='".home_url()."'>". get_bloginfo('name')."</a> ";
			$header = " <a class='subtle-link' target='_blank'  href='".home_url().">". get_bloginfo('name')."</a>";
		}   
		
		$st = $this->get_setting_Emsfb();
		$temp = isset($st->emailTemp) && strlen($st->emailTemp)>10 ? $st->emailTemp : "0";
		
		$title=$lang["newMessage"];
		$message ="<h2>".$m."</h2>";
		$blogName =get_bloginfo('name');
		$user= get_user_by('id', 1);
		$adminEmail = $user->user_email;
		$blogURL= home_url();

		//error_log('temo');
		//error_log($temp);
		if($state=="testMailServer"){
			$title=$lang["goodJob"];
			$message ="<h2>"
			.  $lang["proUnlockMsg"] ."</h2>
			<p>". $lang["createdBy"] ." White Studio Team</p>
			<button><a href='https://whitestudio.team/?".home_url()."' target='_blank' style='color: #3a22ff;'>".$lang["getProVersion"]."</a></button>";
		}elseif($state=="newMessage"){			
			$message ="<h2>".$lang["newMessageReceived"]."</h2>
			<p>". $lang["trackingCode"].": ".$m." </p>
			<button><a href='".home_url()."' target='_blank' style='color: white;'>".get_bloginfo('name')."</a></button>
			";
		}else{

			$title =$lang["hiUser"];
			$message=$m;
		}	
		if(is_rtl()){
			$d =  "rtl" ;
			$align ="right";
		}else{
			$d =  "ltr" ;
			$align ="left";
		}
		$val ="
		<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional //EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'><html xmlns='http://www.w3.org/1999/xhtml' xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:v='urn:schemas-microsoft-com:vml' lang='en'><head> <link rel='stylesheet' type='text/css' hs-webfonts='true' href='https://fonts.googleapis.com/css?family=Lato|Lato:i,b,bi'> <title>Email template</title> <meta property='og:title' content='Email template'> <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'> <meta http-equiv='X-UA-Compatible' content='IE=edge'> <meta name='viewport' content='width=device-width, initial-scale=1.0'> <style type='text/css'> a {  color: inherit; font-weight: bold; color: #253342; text-decoration : none } h1 { font-size: 56px; } h2 { font-size: 28px; font-weight: 900; } p { font-weight: 100; } td { vertical-align: top; } #email { margin: auto; width: 600px; background-color: white; } button { font: inherit; background-color: #ff4b93; border: none; padding: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: 900; color: white; border-radius: 5px;  } .subtle-link { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #CBD6E2; } </style></head>
		<body bgcolor='#F5F8FA' style='width: 100%; margin: auto 0;direction:".$d."; padding:0; font-family:Lato, sans-serif; font-size:18px; color:#33475B; word-break:break-word'>
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
					<tr> <td align='".$align."' style='padding: 30px 30px;'>
						<p style='color:#99ACC2'>".$lang["sentBy"]." ".  get_bloginfo('name')."</p>
						".$footer."
					</td></tr>
				</table>
				</div>
			</body>
			</html>
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
				
			//	error_log($pro);
				$footer ="<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='".$align."' style='padding: 30px 30px;'>".$footer."</td></tr></table>";
				if($pro=="not pro"){	$temp = substr_replace($temp,$footer,($p),0);}
			//	error_log($temp);
				$val =  $temp;
			}
			//($val);
			
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
			$v =str_replace('\\', '', $val);
			$rtrn =json_decode($v);
			break;
			} 
		}
		//error_log(json_encode($rtrn));
		return $rtrn;
	}

	public function response_to_user_by_msd_id($msg_id,$pro){
		$text = ["youRecivedNewMessage"];
        $lang= $this->text_efb($text);		
	 
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
					$subject ="📮 ".$lang["youRecivedNewMessage"];
					$this->send_email_state($email ,$subject ,$trackingCode,$pro,"newMessage");
					return 1;
				}
			}
		}
		return 0;
	}//end function
	

}