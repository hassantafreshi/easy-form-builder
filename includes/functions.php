<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class efbFunction {
	protected $db;


	public function __construct() {

		global $wpdb;
		$this->db = $wpdb;

		register_activation_hook( __FILE__, [$this ,'download_all_addons_efb'] );
		add_action( 'load-index.php', [$this ,'addon_adds_cron_efb'] );

    }


	public function text_efb($inp){
		$ac= $this->get_setting_Emsfb();
		$state= $ac!=='null' && isset($ac->text) && gettype($ac->text)!='string' ? true : false ;
		$s= 'easy-form-builder';
		$lang = [

			"create" => $state ? $ac->text->create : esc_html__('Create','easy-form-builder'),
			"define" => $state ? $ac->text->define : esc_html__('Define','easy-form-builder'),
			"formName" => $state ? $ac->text->formName : esc_html__('Form Name','easy-form-builder'),
			"createDate" => $state ? $ac->text->createDate : esc_html__('Create Date','easy-form-builder'),
			"edit" => $state ? $ac->text->edit : esc_html__('Edit','easy-form-builder'),
			"content" => $state ? $ac->text->content : esc_html__('Content','easy-form-builder'),
			"trackNo" => $state ? $ac->text->trackNo : esc_html__('Confirmation Code','easy-form-builder'),
			"formDate" => $state ? $ac->text->formDate : esc_html__('Form Date','easy-form-builder'),
			"by" => $state ? $ac->text->by : esc_html__('By','easy-form-builder'),
			"ip" => $state ? $ac->text->ip : esc_html__('IP','easy-form-builder'),
			"guest" => $state ? $ac->text->guest : esc_html__('Guest','easy-form-builder'),
			"response" => $state ? $ac->text->response : esc_html__('Response','easy-form-builder'),
			"date" => $state ? $ac->text->date : esc_html__('Date Picker','easy-form-builder'),
			"videoDownloadLink" => $state ? $ac->text->videoDownloadLink : esc_html__('Video Download','easy-form-builder'),
			"downloadViedo" => $state ? $ac->text->downloadViedo : esc_html__('Download Video','easy-form-builder'),
			"download" => $state ? $ac->text->download : esc_html__('Download','easy-form-builder'),
			"youCantUseHTMLTagOrBlank" => $state ? $ac->text->youCantUseHTMLTagOrBlank : esc_html__('Please avoid using HTML tags and ensure that your message is not blank.','easy-form-builder'),
			"reply" => $state ? $ac->text->reply : esc_html__('Reply','easy-form-builder'),
			"messages" => $state ? $ac->text->messages : esc_html__('Messages','easy-form-builder'),
			"pleaseWaiting" => $state ? $ac->text->pleaseWaiting : esc_html__('Please Wait','easy-form-builder'),
			"loading" => $state ? $ac->text->loading : esc_html__('Loading','easy-form-builder'),
			"remove" => $state ? $ac->text->remove : esc_html__('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => $state ? $ac->text->areYouSureYouWantDeleteItem : esc_html__('Are you sure you want to delete this?','easy-form-builder'),
			"no" => $state ? $ac->text->no : esc_html__('NO','easy-form-builder'),
			"yes" => $state ? $ac->text->yes : esc_html__('Yes','easy-form-builder'),


			"proVersion" => $state ? $ac->text->proVersion : esc_html__('Pro Version','easy-form-builder'),
			"getProVersion" => $state ? $ac->text->getProVersion : esc_html__('Activate Pro version','easy-form-builder'),
			"reCAPTCHA" => $state ? $ac->text->reCAPTCHA : esc_html__('reCAPTCHA','easy-form-builder'),

			"enterSITEKEY" => $state ? $ac->text->enterSITEKEY : esc_html__('SECRET KEY','easy-form-builder'),
			"alertEmail" => $state ? $ac->text->alertEmail : esc_html__('Alert Email','easy-form-builder'),
			"enterAdminEmail" => $state ? $ac->text->enterAdminEmail : esc_html__('Enter the admin email address to receive email notifications.','easy-form-builder'),
			"showTrackingCode" => $state ? $ac->text->showTrackingCode : esc_html__('Show Confirmation Code','easy-form-builder'),
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : esc_html__('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : esc_html__('Copy and paste the following shortcode to add the Confirmation Code finder to any page or post.','easy-form-builder'),
			"save" => $state ? $ac->text->save : esc_html__('Save','easy-form-builder'),
			"waiting" => $state ? $ac->text->waiting : esc_html__('Waiting','easy-form-builder'),
			"saved" => $state ? $ac->text->saved : esc_html__('Saved','easy-form-builder'),
			"stepName" => $state ? $ac->text->stepName : esc_html__('Step Name','easy-form-builder'),
			"IconOfStep" => $state ? $ac->text->IconOfStep : esc_html__('Icon of step','easy-form-builder'),
			"stepTitles" => $state ? $ac->text->stepTitles : esc_html__('Step Titles','easy-form-builder'),
			"elements" => $state ? $ac->text->elements : esc_html__('Elements:','easy-form-builder'),
			"delete" => $state ? $ac->text->delete : esc_html__('Delete','easy-form-builder'),
			"newOption" => $state ? $ac->text->newOption : esc_html__('New option','easy-form-builder'),
			"required" => $state ? $ac->text->required : esc_html__('Required','easy-form-builder'),
			"button" => $state ? $ac->text->button : esc_html__('Text','easy-form-builder'),
			"password" => $state ? $ac->text->password : esc_html__('Password','easy-form-builder'),
			"email" => $state ? $ac->text->email : esc_html__('Email','easy-form-builder'),
			"number" => $state ? $ac->text->number : esc_html__('Number','easy-form-builder'),
			"file" => $state ? $ac->text->file : esc_html__('File Upload','easy-form-builder'),
			"tel" => $state ? $ac->text->tel : esc_html__('Tel','easy-form-builder'),
			"textarea" => $state ? $ac->text->textarea : esc_html__('Long Text','easy-form-builder'),
			"checkbox" => $state ? $ac->text->checkbox : esc_html__('Check Box','easy-form-builder'),
			"radiobutton" => $state ? $ac->text->radiobutton : esc_html__('Radio Button','easy-form-builder'),
			"radio" => $state ? $ac->text->radio : esc_html__('Radio','easy-form-builder'),
			"url" => $state ? $ac->text->url : esc_html__('URL','easy-form-builder'),
			"range" => $state ? $ac->text->range : esc_html__('Range','easy-form-builder'),
			"color" => $state ? $ac->text->color : esc_html__('Color Picker','easy-form-builder'),
			"fileType" => $state ? $ac->text->fileType : esc_html__('File Type','easy-form-builder'),
			"label" => $state ? $ac->text->label : esc_html__('Label','easy-form-builder'),
			"labels" => $state ? $ac->text->labels : esc_html__('Labels','easy-form-builder'),
			"class" => $state ? $ac->text->class : esc_html__('Class','easy-form-builder'),
			"id" => $state ? $ac->text->id : esc_html__('ID','easy-form-builder'),
			"tooltip" => $state ? $ac->text->tooltip : esc_html__('Tooltip','easy-form-builder'),
			"formUpdated" => $state ? $ac->text->formUpdated : esc_html__('The Form Updated','easy-form-builder'),
			"goodJob" => $state ? $ac->text->goodJob : esc_html__('Good Job','easy-form-builder'),
			"formUpdatedDone" => $state ? $ac->text->formUpdatedDone : esc_html__('The form has been successfully updated','easy-form-builder'),
			"formIsBuild" => $state ? $ac->text->formIsBuild : esc_html__('The form is successfully built','easy-form-builder'),
			"formCode" => $state ? $ac->text->formCode : esc_html__('Form Code','easy-form-builder'),
			"close" => $state ? $ac->text->close : esc_html__('Close','easy-form-builder'),
			"done" => $state ? $ac->text->done : esc_html__('Done','easy-form-builder'),
			"demo" => $state ? $ac->text->demo : esc_html__('Demo','easy-form-builder'),
			"pleaseFillInRequiredFields" => $state ? $ac->text->pleaseFillInRequiredFields : esc_html__('Please fill in all required fields.','easy-form-builder'),
			"availableInProversion" => $state ? $ac->text->availableInProversion : esc_html__('This option is only available in the Pro version.','easy-form-builder'),
			"formNotBuilded" => $state ? $ac->text->formNotBuilded : esc_html__('The form has not been built!','easy-form-builder'),
			"someStepsNotDefinedCheck" => $state ? $ac->text->someStepsNotDefinedCheck : esc_html__('Please check that all steps are defined before proceeding.','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => $state ? $ac->text->ifYouNeedCreateMoreThan2Steps : esc_html__('If you need to create more than 2 steps, you can activate the pro version of Easy Form Builder, which allows for unlimited steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwo" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwo : esc_html__('You can create a minimum of 1 step and a maximum of 2 steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwenty" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwenty : esc_html__('You can create a minimum of 1 step and a maximum of 20 steps.','easy-form-builder'),
			"preview" => $state ? $ac->text->preview : esc_html__('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => $state ? $ac->text->somethingWentWrongPleaseRefresh : esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder'),
			"formNotCreated" => $state ? $ac->text->formNotCreated : esc_html__('Sorry, it seems like the form has not been created.','easy-form-builder'),
			"atFirstCreateForm" => $state ? $ac->text->atFirstCreateForm : esc_html__('Please create a form and add elements before trying again.','easy-form-builder'),
			"allowMultiselect" => $state ? $ac->text->allowMultiselect : esc_html__('Allow multi-select','easy-form-builder'),
			"DragAndDropUI" => $state ? $ac->text->DragAndDropUI : esc_html__('Drag and drop UI','easy-form-builder'),
			"clickHereForActiveProVesrsion" => $state ? $ac->text->clickHereForActiveProVesrsion : esc_html__('Click here for Active Pro version','easy-form-builder'),
			"selectOpetionDisabled" => $state ? $ac->text->selectOpetionDisabled : esc_html__('Choose an option (not available in test view)','easy-form-builder'),
			"pleaseEnterTheTracking" => $state ? $ac->text->pleaseEnterTheTracking : esc_html__('Please enter the Confirmation Code','easy-form-builder'),
			"formNotFound" => $state ? $ac->text->formNotFound : esc_html__('Form not found.','easy-form-builder'),
			"errorV01" => $state ? $ac->text->errorV01 : esc_html__('Oops, V01 Error occurred.','easy-form-builder'),
			"password8Chars" => $state ? $ac->text->password8Chars : esc_html__('Password should be at least 8 characters long.','easy-form-builder'),
			"registered" => $state ? $ac->text->registered : esc_html__('Registered','easy-form-builder'),
			"yourInformationRegistered" => $state ? $ac->text->yourInformationRegistered : esc_html__('Your information is successfully registered','easy-form-builder'),
			"youNotPermissionUploadFile" => $state ? $ac->text->youNotPermissionUploadFile : esc_html__('You do not have permission to upload this file:','easy-form-builder'),
			"pleaseUploadA" => $state ? $ac->text->pleaseUploadA : esc_html__('Please upload NN file','easy-form-builder'),
			"please" => $state ? $ac->text->please : esc_html__('Please','easy-form-builder'),
			"trackingForm" => $state ? $ac->text->trackingForm : esc_html__('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => $state ? $ac->text->trackingCodeIsNotValid : esc_html__('The confirmation Code is not valid.','easy-form-builder'),
			"checkedBoxIANotRobot" => $state ? $ac->text->checkedBoxIANotRobot : esc_html__('Please Checked Box of I am Not robot','easy-form-builder'),
			"howConfigureEFB" => $state ? $ac->text->howConfigureEFB : esc_html__('How to configure Easy Form Builder','easy-form-builder'),
			"howGetGooglereCAPTCHA" => $state ? $ac->text->howGetGooglereCAPTCHA : esc_html__('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
			"howActivateAlertEmail" => $state ? $ac->text->howActivateAlertEmail : esc_html__('How to activate the alert email for new form submission','easy-form-builder'),
			"howCreateAddForm" => $state ? $ac->text->howCreateAddForm : esc_html__('How to create and add a form with Easy Form Builder','easy-form-builder'),
			"howActivateTracking" => $state ? $ac->text->howActivateTracking : esc_html__('How to activate a Confirmation Code in Easy Form Builder','easy-form-builder'),
			"howWorkWithPanels" => $state ? $ac->text->howWorkWithPanels : esc_html__('How to work with panels in Easy Form Builder','easy-form-builder'),
			"points" => $state ? $ac->text->points : esc_html__('points','easy-form-builder'),
			"howAddTrackingForm" => $state ? $ac->text->howAddTrackingForm : esc_html__('How to add The Confirmation Code Finder to a post, page, or custom post type','easy-form-builder'),//here
			"howFindResponse" => $state ? $ac->text->howFindResponse : esc_html__('How to find a specific submission using the Confirmation Code','easy-form-builder'),
			"pleaseEnterVaildValue" => $state ? $ac->text->pleaseEnterVaildValue : esc_html__('Please enter a valid value','easy-form-builder'),
			"step" => $state ? $ac->text->step : esc_html__('Step','easy-form-builder'),
			"advancedCustomization" => $state ? $ac->text->advancedCustomization : esc_html__('Advanced customization','easy-form-builder'),
			"orClickHere" => $state ? $ac->text->orClickHere : esc_html__(' or click here','easy-form-builder'),
			"downloadCSVFile" => $state ? $ac->text->downloadCSVFile : esc_html__(' Download CSV file','easy-form-builder'),
			"downloadCSVFileSub" => $state ? $ac->text->downloadCSVFileSub : esc_html__(' Download subscriptions CSV.','easy-form-builder'),
			"login" => $state ? $ac->text->login : esc_html__('Login','easy-form-builder'),
			"thisInputLocked" => $state ? $ac->text->thisInputLocked : esc_html__('this input is locked','easy-form-builder'),
			"thisElemantAvailableRemoveable" => $state ? $ac->text->thisElemantAvailableRemoveable : esc_html__('This element is available and removable.','easy-form-builder'),
			"thisElemantWouldNotRemoveableLoginform" => $state ? $ac->text->thisElemantWouldNotRemoveableLoginform : esc_html__('This element cannot be removed from the Login form.','easy-form-builder'),
			"send" => $state ? $ac->text->send : esc_html__('Send','easy-form-builder'),
			"contactUs" => $state ? $ac->text->contactUs : esc_html__('Contact us','easy-form-builder'),
			"support" => $state ? $ac->text->support : esc_html__('Support','easy-form-builder'),
			"subscribe" => $state ? $ac->text->subscribe : esc_html__('Subscribe','easy-form-builder'),
			"logout" => $state ? $ac->text->logout : esc_html__('Logout','easy-form-builder'),
			"survey" => $state ? $ac->text->survey : esc_html__('Survey','easy-form-builder'),
			"chart" => $state ? $ac->text->chart : esc_html__('Chart','easy-form-builder'),
			"noComment" => $state ? $ac->text->noComment : esc_html__('No comment','easy-form-builder'),
			"easyFormBuilder" => $state ? $ac->text->easyFormBuilder : esc_html__('Easy Form Builder','easy-form-builder'),
			"byWhiteStudioTeam" => $state ? $ac->text->byWhiteStudioTeam : esc_html__('By WhiteStudio.team','easy-form-builder'),
			"createForms" =>  $state ? $ac->text->createForms :  esc_html__('Create Forms','easy-form-builder'),
			"tutorial" => $state ? $ac->text->tutorial : esc_html__('Tutorial','easy-form-builder'),
			"forms" => $state ? $ac->text->forms : esc_html__('Forms','easy-form-builder'),
			"tobeginSentence" => $state ? $ac->text->tobeginSentence : esc_html__('To get started, simply create a form using the Easy Form Builder Plugin. Click the button below to create a form.','easy-form-builder'),
			"efbIsTheUserSentence" => $state ? $ac->text->efbIsTheUserSentence : esc_html__('Easy Form Builder is an intuitive and user-friendly tool that lets you create custom, multi-step forms in just minutes, without requiring any coding skills.','easy-form-builder'),
			"efbYouDontNeedAnySentence" => $state ? $ac->text->efbYouDontNeedAnySentence : esc_html__('You do not have to be a coding expert to use Easy Form Builder. Simply drag and drop the fields to create customized multistep forms easily. Plus, you can connect each submission to a unique request using the Confirmation Code feature.','easy-form-builder'),
			"newResponse" => $state ? $ac->text->newResponse : esc_html__('New Response','easy-form-builder'),
			"read" => $state ? $ac->text->read : esc_html__('Read','easy-form-builder'),
			"copy" => $state ? $ac->text->copy : esc_html__('Copy','easy-form-builder'),
			"general" => $state ? $ac->text->general : esc_html__('General','easy-form-builder'),
			"dadFieldHere" => $state ? $ac->text->dadFieldHere : esc_html__('Drag & Drop Fields Here','easy-form-builder'),
			"help" => $state ? $ac->text->help : esc_html__('Help','easy-form-builder'),
			"setting" => $state ? $ac->text->setting : esc_html__('Setting','easy-form-builder'),
			"maps" => $state ? $ac->text->maps : esc_html__('Maps','easy-form-builder'),
			"youCanFindTutorial" => $state ? $ac->text->youCanFindTutorial : esc_html__('Find video tutorials in the adjacent box and click the document button for tutorials and articles.','easy-form-builder'),
			"proUnlockMsg" => $state ? $ac->text->proUnlockMsg : esc_html__('Activate Pro version for more features and unlimited access to the all plugin services.','easy-form-builder'),
			"aPIKey" => $state ? $ac->text->aPIKey : esc_html__('API KEY','easy-form-builder'),
			"youNeedAPIgMaps" => $state ? $ac->text->youNeedAPIgMaps : esc_html__('Your form needs an API key for Google Maps to work properly.','easy-form-builder'),
			"copiedClipboard" => $state ? $ac->text->copiedClipboard : esc_html__('Copied to Clipboard','easy-form-builder'),
			"noResponse" => $state ? $ac->text->noResponse : esc_html__('No Response','easy-form-builder'),
			"offerGoogleCloud" => $state ? $ac->text->offerGoogleCloud : esc_html__('To use reCAPTCHA and location picker (Maps), sign up for the Google Cloud service and receive $350 worth of credits exclusively for our users ','easy-form-builder'),
			"getOfferTextlink" => $state ? $ac->text->getOfferTextlink : esc_html__(' Get credits by clicking here.','easy-form-builder'),
			"clickHere" => $state ? $ac->text->clickHere : esc_html__('Click here','easy-form-builder'),
			"SpecialOffer" => $state ? $ac->text->SpecialOffer : esc_html__('Special offer','easy-form-builder'),
			"googleKeys" => $state ? $ac->text->googleKeys : esc_html__('Google Keys','easy-form-builder'),
			"emailServer" => $state ? $ac->text->emailServer : esc_html__('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : esc_html__('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.','easy-form-builder'),
			"emailSetting" => $state ? $ac->text->emailSetting : esc_html__('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : esc_html__('Check Email Server','easy-form-builder'),
			"dadfile" => $state ? $ac->text->dadfile : esc_html__('D&D File Upload','easy-form-builder'),
			"field" => $state ? $ac->text->field : esc_html__('Field','easy-form-builder'),
			"advanced" => $state ? $ac->text->advanced : esc_html__('Advanced','easy-form-builder'),
			"switch" => $state ? $ac->text->switch : esc_html__('Switch','easy-form-builder'),
			"locationPicker" => $state ? $ac->text->locationPicker : esc_html__('Location Picker','easy-form-builder'),
			"rating" => $state ? $ac->text->rating : esc_html__('Rating','easy-form-builder'),
			"esign" => $state ? $ac->text->esign : esc_html__('Signature','easy-form-builder'),
			"yesNo" => $state ? $ac->text->yesNo : esc_html__('Yes/No','easy-form-builder'),
			"htmlCode" => $state ? $ac->text->htmlCode : esc_html__('HTML Code','easy-form-builder'),
			"pcPreview" => $state ? $ac->text->pcPreview : esc_html__('Desktop Preview','easy-form-builder'),
			"youDoNotAddAnyInput" => $state ? $ac->text->youDoNotAddAnyInput : esc_html__('You have not added any fields.','easy-form-builder'),
			"copyShortcode" => $state ? $ac->text->copyShortcode : esc_html__('Copy ShortCode','easy-form-builder'),
			"shortcode" => $state ? $ac->text->shortcode : esc_html__('ShortCode','easy-form-builder'),
			"copyTrackingcode" => $state ? $ac->text->copyTrackingcode : esc_html__('Copy Confirmation Code','easy-form-builder'),
			"previewForm" => $state ? $ac->text->previewForm : esc_html__('Preview Form','easy-form-builder'),
			"activateProVersion" => $state ? $ac->text->activateProVersion : esc_html__('Activate Pro Now','easy-form-builder'),
			"itAppearedStepsEmpty" => $state ? $ac->text->itAppearedStepsEmpty : esc_html__('It seems that some of the steps in your form are empty. Please add field to all steps before saving.','easy-form-builder'),
			"youUseProElements" => $state ? $ac->text->youUseProElements : esc_html__('You are using the pro field in the form. For save and using the form included pro fields, activate Pro version.','easy-form-builder'),
			"sampleDescription" => $state ? $ac->text->sampleDescription : esc_html__('Sample description','easy-form-builder'),
			"fieldAvailableInProversion" => $state ? $ac->text->fieldAvailableInProversion : esc_html__('This feature is only available in the Pro of Easy Form Builder.','easy-form-builder'),
			"editField" => $state ? $ac->text->editField : esc_html__('Edit Field','easy-form-builder'),
			"description" => $state ? $ac->text->description : esc_html__('Description','easy-form-builder'),
			"descriptions" => $state ? $ac->text->descriptions : esc_html__('Descriptions','easy-form-builder'),
			"thisEmailNotificationReceive" => $state ? $ac->text->thisEmailNotificationReceive : esc_html__('Enable email notifications','easy-form-builder'),
			"activeTrackingCode" => $state ? $ac->text->activeTrackingCode : esc_html__('Show Confirmation Code','easy-form-builder'),
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : esc_html__('Add Google reCAPTCHA to the form ','easy-form-builder'),
			"dontShowIconsStepsName" => $state ? $ac->text->dontShowIconsStepsName : esc_html__('Hide icons and step names.','easy-form-builder'),
			"dontShowProgressBar" => $state ? $ac->text->dontShowProgressBar : esc_html__('Hide progress bar','easy-form-builder'),
			"showTheFormTologgedUsers" => $state ? $ac->text->showTheFormTologgedUsers : esc_html__('Private form','easy-form-builder'),
			"labelSize" => $state ? $ac->text->labelSize : esc_html__('Label size','easy-form-builder'),
			"default" => $state ? $ac->text->default : esc_html__('Default','easy-form-builder'),
			"small" => $state ? $ac->text->small : esc_html__('Small','easy-form-builder'),
			"large" => $state ? $ac->text->large : esc_html__('Large','easy-form-builder'),
			"xlarge" => $state ? $ac->text->xlarge : esc_html__('XLarge','easy-form-builder'),
			"xxlarge" => $state ? $ac->text->xxlarge : esc_html__('XXLarge','easy-form-builder'),
			"xxxlarge" => $state ? $ac->text->xxxlarge : esc_html__('XXXLarge','easy-form-builder'),
			"labelPostion" => $state ? $ac->text->labelPostion : esc_html__('Label Position','easy-form-builder'),
			"align" => $state ? $ac->text->align : esc_html__('Align','easy-form-builder'),
			"left" => $state ? $ac->text->left : esc_html__('Left','easy-form-builder'),
			"center" => $state ? $ac->text->center : esc_html__('Center','easy-form-builder'),
			"right" => $state ? $ac->text->right : esc_html__('Right','easy-form-builder'),
			"width" => $state ? $ac->text->width : esc_html__('Width','easy-form-builder'),
			"cSSClasses" => $state ? $ac->text->cSSClasses : esc_html__('CSS Classes','easy-form-builder'),
			"defaultValue" => $state ? $ac->text->defaultValue : esc_html__('Default value','easy-form-builder'),
			"placeholder" => $state ? $ac->text->placeholder : esc_html__('Placeholder','easy-form-builder'),
			"enterAdminEmailReceiveNoti" => $state ? $ac->text->enterAdminEmailReceiveNoti : esc_html__('Enter email address to receive notifications.','easy-form-builder'),
			"corners" => $state ? $ac->text->corners : esc_html__('Corners','easy-form-builder'),
			"rounded" => $state ? $ac->text->rounded : esc_html__('Rounded','easy-form-builder'),
			"square" => $state ? $ac->text->square : esc_html__('Square','easy-form-builder'),
			"icon" => $state ? $ac->text->icon : esc_html__('Icon','easy-form-builder'),
			"icons" => $state ? $ac->text->icon : esc_html__('Icons','easy-form-builder'),
			"buttonColor" => $state ? $ac->text->buttonColor : esc_html__('Button color','easy-form-builder'),
			"buttonColors" => $state ? $ac->text->buttonColors : esc_html__('Buttons colors','easy-form-builder'),
			"blue" => $state ? $ac->text->blue : esc_html__('Blue','easy-form-builder'),
			"darkBlue" => $state ? $ac->text->darkBlue : esc_html__('Dark Blue','easy-form-builder'),
			"lightBlue" => $state ? $ac->text->lightBlue : esc_html__('Light Blue','easy-form-builder'),
			"grayLight" => $state ? $ac->text->grayLight : esc_html__('Gray Light','easy-form-builder'),
			"grayLighter" => $state ? $ac->text->grayLighter : esc_html__('Gray Lighter','easy-form-builder'),
			"green" => $state ? $ac->text->green : esc_html__('Green','easy-form-builder'),
			"pink" => $state ? $ac->text->pink : esc_html__('Pink','easy-form-builder'),
			"yellow" => $state ? $ac->text->yellow : esc_html__('Yellow','easy-form-builder'),
			"light" => $state ? $ac->text->light : esc_html__('Light','easy-form-builder'),
			"Red" => $state ? $ac->text->Red : esc_html__('red','easy-form-builder'),
			"grayDark" => $state ? $ac->text->grayDark : esc_html__('Gray Dark','easy-form-builder'),
			"white" => $state ? $ac->text->white : esc_html__('White','easy-form-builder'),
			"clr" => $state ? $ac->text->clr : esc_html__('Color','easy-form-builder'),
			"borderColor" => $state ? $ac->text->borderColor : esc_html__('Border Color','easy-form-builder'),
			"height" => $state ? $ac->text->height : esc_html__('Height','easy-form-builder'),
			"name" => $state ? $ac->text->name : esc_html__('Name','easy-form-builder'),
			"latitude" => $state ? $ac->text->latitude : esc_html__('Latitude','easy-form-builder'),
			"longitude" => $state ? $ac->text->longitude : esc_html__('Longitude','easy-form-builder'),
			"exDot" => $state ? $ac->text->exDot : esc_html__('e.g.','easy-form-builder'),
			"pleaseDoNotAddJsCode" => $state ? $ac->text->pleaseDoNotAddJsCode : esc_html__('(Avoid adding JavaScript or jQuery codes to HTML for security reasons.)','easy-form-builder'),
			"button1Value" => $state ? $ac->text->button1Value : esc_html__('Button 1 value','easy-form-builder'),
			"button2Value" => $state ? $ac->text->button2Value : esc_html__('Button 2 value','easy-form-builder'),
			"iconList" => $state ? $ac->text->iconList : esc_html__('Icons list','easy-form-builder'),
			"previous" => $state ? $ac->text->previous : esc_html__('Previous','easy-form-builder'),
			"next" => $state ? $ac->text->next : esc_html__('Next','easy-form-builder'),
			"noCodeAddedYet" => $state ? $ac->text->noCodeAddedYet : esc_html__('The code has not yet been added. Click on','easy-form-builder'),
			"andAddingHtmlCode" => $state ? $ac->text->andAddingHtmlCode : esc_html__('and adding HTML code.','easy-form-builder'),

			"aPIkeyGoogleMapsError" => $state ? $ac->text->aPIkeyGoogleMapsError : esc_html__('The API key for Google Maps has not been added. Please go to Easy Form Builder > Panel > Setting > Google Keys, add the API key for Google Maps, and try again.','easy-form-builder'),
			"howToAddGoogleMap" => $state ? $ac->text->howToAddGoogleMap : esc_html__('How to Add Location Picker(maps) to Easy form Builder WordPress Plugin','easy-form-builder'),
			"deletemarkers" => $state ? $ac->text->deletemarkers : esc_html__('Delete markers','easy-form-builder'),
			"updateUrbrowser" => $state ? $ac->text->updateUrbrowser : esc_html__('update your browser','easy-form-builder'),
			"stars" => $state ? $ac->text->stars : esc_html__('Stars','easy-form-builder'),
			"nothingSelected" => $state ? $ac->text->nothingSelected : esc_html__('Nothing selected','easy-form-builder'),
			"duplicate" => $state ? $ac->text->duplicate : esc_html__('Duplicate','easy-form-builder'),
			"availableProVersion" => $state ? $ac->text->availableProVersion : esc_html__('Available in the Pro version','easy-form-builder'),
			"mobilePreview" => $state ? $ac->text->mobilePreview : esc_html__('Mobile Preview','easy-form-builder'),
			"thanksFillingOutform" => $state ? $ac->text->thanksFillingOutform : esc_html__('Thanks for filling out the form.','easy-form-builder'),
			"finish" => $state ? $ac->text->finish : esc_html__('Finish','easy-form-builder'),
			"dragAndDropA" => $state ? $ac->text->dragAndDropA : esc_html__('Drag & Drop the','easy-form-builder'),
			"browseFile" => $state ? $ac->text->browseFile : esc_html__('Browse File','easy-form-builder'),
			"removeTheFile" => $state ? $ac->text->removeTheFile : esc_html__('Remove the file','easy-form-builder'),
			"enterAPIKey" => $state ? $ac->text->enterAPIKey : esc_html__('Enter API KEY','easy-form-builder'),
			"formSetting" => $state ? $ac->text->formSetting : esc_html__('Form Settings','easy-form-builder'),
			"select" => $state ? $ac->text->select : esc_html__('Select','easy-form-builder'),
			"up" => $state ? $ac->text->up : esc_html__('Up','easy-form-builder'),
			"sending" => $state ? $ac->text->sending : esc_html__('Sending','easy-form-builder'),
			"enterYourMessage" => $state ? $ac->text->enterYourMessage : esc_html__('Please Enter your message','easy-form-builder'),
			"add" => $state ? $ac->text->add : esc_html__('Add','easy-form-builder'),
			"code" => $state ? $ac->text->code : esc_html__('Code','easy-form-builder'),
			"star" => $state ? $ac->text->star : esc_html__('Star','easy-form-builder'),
			"form" => $state ? $ac->text->form : esc_html__('Form','easy-form-builder'),
			"black" => $state ? $ac->text->black : esc_html__('Black','easy-form-builder'),
			"pleaseReporProblem" => $state ? $ac->text->pleaseReporProblem : esc_html__('Please kindly report the following issue to the Easy Form Builder team.','easy-form-builder'),
			"reportProblem" => $state ? $ac->text->reportProblem : esc_html__('Report problem','easy-form-builder'),
			"ddate" => $state ? $ac->text->ddate : esc_html__('Date','easy-form-builder'),
			"serverEmailAble" => $state ? $ac->text->serverEmailAble : esc_html__('Your server is capable of sending emails','easy-form-builder'),
			"sMTPNotWork" => $state ? $ac->text->sMTPNotWork : esc_html__('SMTP Error: The host is unable to send an email. Please contact the host is support team for assistance.','easy-form-builder'),

			"aPIkeyGoogleMapsFeild" => $state ? $ac->text->aPIkeyGoogleMapsFeild : esc_html__('There was an error loading Maps.','easy-form-builder'),
			"fileIsNotRight" => $state ? $ac->text->fileIsNotRight : esc_html__('The uploaded file is not in the correct file format.','easy-form-builder'),
			"thisElemantNotAvailable" => $state ? $ac->text->thisElemantNotAvailable : esc_html__('The selected field is not available in this type of form.','easy-form-builder'),
			"numberSteps" => $state ? $ac->text->numberSteps : esc_html__('Edit','easy-form-builder'),
			"clickHereGetActivateCode" => $state ? $ac->text->clickHereGetActivateCode : esc_html__('Get your activation code now and unlock exclusive features ! Click here.','easy-form-builder'),
			"trackingCode" => $state ? $ac->text->trackingCode : esc_html__('Confirmation Code','easy-form-builder'),
			"text" => $state ? $ac->text->text : esc_html__('Text','easy-form-builder'),
			"multiselect" => $state ? $ac->text->multiselect : esc_html__('Multiple Select','easy-form-builder'),
			"newForm" => $state ? $ac->text->newForm : esc_html__('New Form','easy-form-builder'),
			"registerForm" => $state ? $ac->text->registerForm : esc_html__('Register Form','easy-form-builder'),
			"loginForm" => $state ? $ac->text->loginForm : esc_html__('Login Form','easy-form-builder'),
			"subscriptionForm" => $state ? $ac->text->subscriptionForm : esc_html__('Subscription Form','easy-form-builder'),
			"supportForm" => $state ? $ac->text->supportForm : esc_html__('Support Form','easy-form-builder'),
			"createBlankMultistepsForm" => $state ? $ac->text->createBlankMultistepsForm : esc_html__('Create a blank multisteps form.','easy-form-builder'),
			"createContactusForm" => $state ? $ac->text->createContactusForm : esc_html__('Create a Contact us form.','easy-form-builder'),
			"createRegistrationForm" => $state ? $ac->text->createRegistrationForm : esc_html__('Create a form to register new users to your WordPress site\'s user list.','easy-form-builder'),
			"createLoginForm" => $state ? $ac->text->createLoginForm : esc_html__('Create a login form for users to enter your WordPress site.','easy-form-builder'),
			"createnewsletterForm" => $state ? $ac->text->createnewsletterForm : esc_html__('Create a newsletter form','easy-form-builder'),
			"createSupportForm" => $state ? $ac->text->createSupportForm : esc_html__('Create a support contact form.','easy-form-builder'),
			"availableSoon" => $state ? $ac->text->availableSoon : esc_html__('Available Soon','easy-form-builder'),
			"reservation" => $state ? $ac->text->reservation : esc_html__('Reservation ','easy-form-builder'),
			"createsurveyForm" => $state ? $ac->text->createsurveyForm : esc_html__('Create survey or poll or questionnaire forms ','easy-form-builder'),
			"createReservationyForm" => $state ? $ac->text->createReservationyForm : esc_html__('Create reservation or booking forms ','easy-form-builder'),
			"firstName" => $state ? $ac->text->firstName : esc_html__('First name','easy-form-builder'),
			"lastName" => $state ? $ac->text->lastName : esc_html__('Last name','easy-form-builder'),
			"message" => $state ? $ac->text->message : esc_html__('Message','easy-form-builder'),
			"subject" => $state ? $ac->text->subject : esc_html__('Subject','easy-form-builder'),
			"phone" => $state ? $ac->text->phone : esc_html__('Phone','easy-form-builder'),
			"register" => $state ? $ac->text->register : esc_html__('Register','easy-form-builder'),
			"username" => $state ? $ac->text->username : esc_html__('Username','easy-form-builder'),
			"allStep" => $state ? $ac->text->allStep : esc_html__('all step','easy-form-builder'),
			"beside" => $state ? $ac->text->beside : esc_html__('Beside','easy-form-builder'),
			"invalidEmail" => $state ? $ac->text->invalidEmail : esc_html__('Invalid Email address','easy-form-builder'),
			"clearUnnecessaryFiles" => $state ? $ac->text->clearUnnecessaryFiles : esc_html__('Delete unnecessary files.','easy-form-builder'),
			"youCanRemoveUnnecessaryFileUploaded" => $state ? $ac->text->youCanRemoveUnnecessaryFileUploaded : esc_html__('You can delete unnecessary files uploaded by users using the button below.','easy-form-builder'),
			"whenEasyFormBuilderRecivesNewMessage" => $state ? $ac->text->whenEasyFormBuilderRecivesNewMessage : esc_html__('When a new message is received through Easy Form Builder, an alert email will be sent to the plugin admin.','easy-form-builder'),
			"reCAPTCHAv2" => $state ? $ac->text->reCAPTCHAv2 : esc_html__('reCAPTCHA v2','easy-form-builder'),
			"clickHereWatchVideoTutorial" => $state ? $ac->text->clickHereWatchVideoTutorial : esc_html__('Click here to watch a video tutorial.','easy-form-builder'),
			"siteKey" => $state ? $ac->text->siteKey : esc_html__('SITE KEY','easy-form-builder'),
			"SecreTKey" => $state ? $ac->text->SecreTKey : esc_html__('SECRET KEY','easy-form-builder'),
			"EnterSECRETKEY" => $state ? $ac->text->EnterSECRETKEY : esc_html__('Enter a Secret Key','easy-form-builder'),
			"clearFiles" => $state ? $ac->text->clearFiles : esc_html__('Clear Files','easy-form-builder'),
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : esc_html__('Enter the activate code','easy-form-builder'),
			"error" => $state ? $ac->text->error : esc_html__('Error','easy-form-builder'),
			"somethingWentWrongTryAgain" => $state ? $ac->text->somethingWentWrongTryAgain : esc_html__('Something unexpected happened. Please try again by refreshing the page.','easy-form-builder'),
			"enterThePhone" => $state ? $ac->text->enterThePhone : esc_html__('Please enter a valid phone number.','easy-form-builder'),
			"pleaseMakeSureAllFields" => $state ? $ac->text->pleaseMakeSureAllFields : esc_html__('Please ensure that all fields are filled correctly.','easy-form-builder'),
			"enterTheEmail" => $state ? $ac->text->enterTheEmail : esc_html__('Please enter an email address.','easy-form-builder'),
			"fileSizeIsTooLarge" => $state ? $ac->text->fileSizeIsTooLarge : esc_html__('The file size exceeds the maximum allowed limit of NN MB','easy-form-builder'),
			"documents" => $state ? $ac->text->documents : esc_html__('Documents','easy-form-builder'),
			"document" => $state ? $ac->text->document : esc_html__('Document','easy-form-builder'),
			"image" => $state ? $ac->text->image : esc_html__('Image','easy-form-builder'),
			"media" => $state ? $ac->text->media : esc_html__('Media','easy-form-builder'),
			"zip" => $state ? $ac->text->zip : esc_html__('Zip','easy-form-builder'),
			"alert" => $state ? $ac->text->alert : esc_html__('Alert!','easy-form-builder'),
			"pleaseWatchTutorial" => $state ? $ac->text->pleaseWatchTutorial : esc_html__('We recommend watching this tutorial for assistance.','easy-form-builder'),
			"formIsNotShown" => $state ? $ac->text->formIsNotShown : esc_html__('The form is not shown because Google reCAPTCHA has not been added to the Easy Form Builder plugin settings.','easy-form-builder'),
			"errorVerifyingRecaptcha" => $state ? $ac->text->errorVerifyingRecaptcha : esc_html__('Please try again, Captcha Verification Failed.','easy-form-builder'),
			"enterThePassword" => $state ? $ac->text->enterThePassword : esc_html__('Password must be at least 8 characters long and include a number and an uppercase letter.','easy-form-builder'),
			"PleaseFillForm" => $state ? $ac->text->PleaseFillForm : esc_html__('Please complete the form.','easy-form-builder'),
			"selectOption" => $state ? $ac->text->selectOption : esc_html__('Choose options','easy-form-builder'),
			"selected" => $state ? $ac->text->selected : esc_html__('Selected','easy-form-builder'),
			"selectedAllOption" => $state ? $ac->text->selectedAllOption : esc_html__('Select All','easy-form-builder'),
			"sentSuccessfully" => $state ? $ac->text->sentSuccessfully : esc_html__('Sent successfully','easy-form-builder'),
			"sync" => $state ? $ac->text->sync : esc_html__('Sync','easy-form-builder'),
			"enterTheValueThisField" => $state ? $ac->text->enterTheValueThisField : esc_html__('This field is required.','easy-form-builder'),
			"thankYou" => $state ? $ac->text->thankYou : esc_html__('Thank you','easy-form-builder'),
			"YouSubscribed" => $state ? $ac->text->YouSubscribed : esc_html__('You are subscribed','easy-form-builder'),
			"passwordRecovery" => $state ? $ac->text->passwordRecovery : esc_html__('Password recovery','easy-form-builder'),
			"info" => $state ? $ac->text->info : esc_html__('information','easy-form-builder'),
			"waitingLoadingRecaptcha" => $state ? $ac->text->waitingLoadingRecaptcha : esc_html__('Wait for loading reCaptcha','easy-form-builder'),
			"on" => $state ? $ac->text->on : esc_html__('On','easy-form-builder'),
			"off" => $state ? $ac->text->off : esc_html__('Off','easy-form-builder'),
			"settingsNfound" => $state ? $ac->text->settingsNfound : esc_html__('Settings not found','easy-form-builder'),
			"red" => $state ? $ac->text->red : esc_html__('Red','easy-form-builder'),
			"reCAPTCHASetError" => $state ? $ac->text->reCAPTCHASetError : esc_html__('Please navigate to the Easy Form Builder Panel, then go to Settings and click on Google Keys to configure the keys for Google reCAPTCHA.','easy-form-builder'),
			"ifShowTrackingCodeToUser" => $state ? $ac->text->ifShowTrackingCodeToUser : esc_html__("To hide the Confirmation Code from users, leave the option unmarked.",'easy-form-builder'),
			"videoOrAudio" => $state ? $ac->text->videoOrAudio : esc_html__('(Video or Audio)','easy-form-builder'),
			"localization" => $state ? $ac->text->localization : esc_html__('Localization','easy-form-builder'),
			"translateLocal" => $state ? $ac->text->translateLocal : esc_html__('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.','easy-form-builder'),
			"enterValidURL" => $state ? $ac->text->enterValidURL : esc_html__('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"emailOrUsername" => $state ? $ac->text->emailOrUsername : esc_html__('Email or Username','easy-form-builder'),
			"contactusForm" => $state ? $ac->text->contactusForm : esc_html__('Contact-us Form','easy-form-builder'),
			"clear" => $state ? $ac->text->clear : esc_html__('Clear','easy-form-builder'),
			"entrTrkngNo" => $state ? $ac->text->entrTrkngNo : esc_html__('Enter the Confirmation Code','easy-form-builder'),
			"search" => $state ? $ac->text->search : esc_html__('Search','easy-form-builder'),
			"enterThePhones" => $state ? $ac->text->enterThePhones : esc_html__('Enter The Phone No','easy-form-builder'),
			"conturyList" => $state ? $ac->text->conturyList : esc_html__('Countries Drop-down','easy-form-builder'),
			"stateProvince" => $state ? $ac->text->stateProvince : esc_html__('State/Prov Drop-down','easy-form-builder'),
			"thankYouMessage" => $state ? $ac->text->thankYouMessage : esc_html__('Thank you message','easy-form-builder'),
			"newMessage" => $state ? $ac->text->newMessage : esc_html__('New message!', $s),
			"newMessageReceived" => $state ? $ac->text->newMessageReceived : esc_html__('A New Message has been Received.', $s),
			"createdBy" => $state ? $ac->text->createdBy : esc_html__('Created by','easy-form-builder'),
			"hiUser" => $state ? $ac->text->hiUser : esc_html__('Hi Dear User', $s),
			"sentBy" => $state ? $ac->text->sentBy : esc_html__("Sent by:",'easy-form-builder'),
			"youRecivedNewMessage" => $state ? $ac->text->youRecivedNewMessage : esc_html__('You have a new message.', $s),
			"formNExist" => $state ? $ac->text->formNExist : esc_html__('Form does not exist !!','easy-form-builder'),
			"error403" => $state ? $ac->text->error403 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E403','easy-form-builder'),
			"error400" => $state ? $ac->text->error400 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E400','easy-form-builder'),
			"formPrivateM" => $state  && isset($ac->text->formPrivateM) ? $ac->text->formPrivateM : esc_html__('This is a private form. Please log in to access it.','easy-form-builder'),
			"errorSiteKeyM" => $state ? $ac->text->errorSiteKeyM : esc_html__('Please check the site key and secret key on Easy Form Builder panel > Settings > Google Keys to resolve the error.','easy-form-builder'),
			"errorCaptcha" => $state ? $ac->text->errorCaptcha : esc_html__('There seems to be a problem with the Captcha. Please try again.','easy-form-builder'),
			"createAcountDoneM" => $state ? $ac->text->createAcountDoneM : esc_html__('Your account has been successfully created! You will receive an email containing your information','easy-form-builder'),
			"incorrectUP" => $state ? $ac->text->incorrectUP : esc_html__('This username or password combination is incorrect.','easy-form-builder'),
			"newPassM" => $state ? $ac->text->newPassM : esc_html__('If your email is valid, a new password will send to your email.','easy-form-builder'),
			"surveyComplatedM" => $state ? $ac->text->surveyComplatedM : esc_html__('The survey has been successfully completed.','easy-form-builder'),
			"error405" => $state ? $ac->text->error405 : esc_html__('We are sorry, but there seems to be a security error (405) with your request.','easy-form-builder'),
			"errorSettingNFound" => $state ? $ac->text->errorSettingNFound : esc_html__('Error, Setting not Found','easy-form-builder'),
			"errorMRobot" => $state ? $ac->text->errorMRobot : esc_html__('Sorry, there seems to be an error. Please verify that you are human and try again.','easy-form-builder'),
			"enterVValue" => $state ? $ac->text->enterVValue : esc_html__('Please enter valid values','easy-form-builder'),
			"cCodeNFound" => $state ? $ac->text->cCodeNFound : esc_html__('Invalid Confirmation Code.','easy-form-builder'),
			"errorFilePer" => $state ? $ac->text->errorFilePer : esc_html__('There seems to be an error with the file permissions.','easy-form-builder'),
			"errorSomthingWrong" => $state ? $ac->text->errorSomthingWrong : esc_html__('Oops! Something went wrong. Please try refreshing the page and try again.','easy-form-builder'),
			"nAllowedUseHtml" => $state ? $ac->text->nAllowedUseHtml : esc_html__('HTML tags are not allowed.','easy-form-builder'),
			"messageSent" => $state ? $ac->text->messageSent : esc_html__('Your message has been sent.','easy-form-builder'),
			"WeRecivedUrM" => $state ? $ac->text->WeRecivedUrM : esc_html__('We have received your message.','easy-form-builder'),
			"thankFillForm" => $state ? $ac->text->thankFillForm : esc_html__('The form has been submitted successfully','easy-form-builder'),
			"thankRegistering" => $state ? $ac->text->thankRegistering : esc_html__('Your registration is successful.','easy-form-builder'),
			"welcome" => $state ? $ac->text->welcome : esc_html__('Welcome','easy-form-builder'),
			"thankSubscribing" => $state ? $ac->text->thankSubscribing : esc_html__('You have successfully subscribed. Thank you!','easy-form-builder'),
			"thankDonePoll" => $state ? $ac->text->thankDonePoll : esc_html__('Thank You for taking the time to complete this survey.','easy-form-builder'),
			"goToEFBAddEmailM" => $state ? $ac->text->goToEFBAddEmailM : esc_html__('Please navigate to the Easy Form Builder panel, then select < Setting >, followed by < Email Settings >. Next, click on the button that reads < Click To Check Email Server >, and then click < Save >.','easy-form-builder'),
			"errorCheckInputs" => $state ? $ac->text->errorCheckInputs : esc_html__('Uh oh, looks like there is a problem with the form. Please make sure all of the input is correct.','easy-form-builder'),
			"formNcreated" => $state ? $ac->text->formNcreated : esc_html__('The form was not created','easy-form-builder'),
			"NAllowedscriptTag" => $state ? $ac->text->NAllowedscriptTag : esc_html__('Scripts tags are not allowed.','easy-form-builder'),
			"bootStrapTemp" => $state ? $ac->text->bootStrapTemp : esc_html__('Bootstrap Template','easy-form-builder'),
			"iUsebootTempW" => $state ? $ac->text->iUsebootTempW : esc_html__('Warning: If your template uses Bootstrap, please ensure that the option below is checked.','easy-form-builder'),
			"iUsebootTemp" => $state ? $ac->text->iUsebootTemp : esc_html__('My template is based on Bootstrap','easy-form-builder'),
			"invalidRequire" => $state ? $ac->text->invalidRequire : esc_html__('Uh oh, it looks like there is a problem with your request. Please review everything and try again.','easy-form-builder'),
			"updated" => $state ? $ac->text->updated : esc_html__('updated','easy-form-builder'),
			"PEnterMessage" => $state ? $ac->text->PEnterMessage : esc_html__('Please type in your message','easy-form-builder'),
			"fileDeleted" => $state ? $ac->text->fileDeleted : esc_html__('The files have been deleted.','easy-form-builder'),
			"activationNcorrect" => $state ? $ac->text->activationNcorrect : esc_html__('The activation code you entered is incorrect. Please double-check and try again.','easy-form-builder'),
			"localizationM" => $state ? $ac->text->localizationM : esc_html__('To localize the plugin, simply go to the Panel, click on Setting, and then Localization.','easy-form-builder'),
			"MMessageNSendEr" => $state ? $ac->text->MMessageNSendEr : esc_html__('We are sorry, but the message was not sent due to a settings error. Please contact the admin for assistance.','easy-form-builder'),
			"warningBootStrap" => $state && isset($ac->text->warningBootStrap) ? $ac->text->warningBootStrap : esc_html__('To ensure compatibility, please go to the Panel and select the < Setting > option. From there, choose the option that states < My template has used Bootstrap framework > and click < Save >. If you encounter any additional issues, please don not hesitate to contact us through our website at whitestudio.team.','easy-form-builder'),
			"or" => $state  && isset($ac->text->or)? $ac->text->or : esc_html__('OR','easy-form-builder'),
			"emailTemplate" => $state  &&  isset($ac->text->emailTemplate) ? $ac->text->emailTemplate : esc_html__('Email Template','easy-form-builder'),
			"reset" => $state  &&  isset($ac->text->reset) ? $ac->text->reset : esc_html__('reset','easy-form-builder'),
			"freefeatureNotiEmail" => $state  &&  isset($ac->text->freefeatureNotiEmail) ? $ac->text->freefeatureNotiEmail : esc_html__('One of the free features of Easy Form Builder is the ability to send a notification email to either the admin or user.','easy-form-builder'),
			"notFound" => $state  &&  isset($ac->text->notFound) ? $ac->text->notFound : esc_html__('Not Found','easy-form-builder'),
			"editor" => $state  &&  isset($ac->text->editor) ? $ac->text->editor : esc_html__('Editor','easy-form-builder'),
			"addSCEmailM" => $state  &&  isset($ac->text->addSCEmailM) ? $ac->text->addSCEmailM : esc_html__('Please add the shortcode_message shortcode to the email template.','easy-form-builder'),
			"ChrlimitEmail" => $state  &&  isset($ac->text->ChrlimitEmail) ? $ac->text->ChrlimitEmail : esc_html__('Your Email Template cannot exceed 10,000 characters.','easy-form-builder'),
			"pleaseEnterVaildEtemp" => $state  &&  isset($ac->text->pleaseEnterVaildEtemp) ? $ac->text->pleaseEnterVaildEtemp : esc_html__('Please use HTML tags to create your email template.','easy-form-builder'),
			"infoEmailTemplates" => $state  &&  isset($ac->text->infoEmailTemplates) ? $ac->text->infoEmailTemplates : esc_html__('To create an email template using HTML2, use the following shortcodes. Please note that the shortcodes marked with an asterisk (*) should be included in the email template.','easy-form-builder'),
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : esc_html__('Add this shortcode inside a tag to display the title of the email.','easy-form-builder'),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : esc_html__('Add this shortcode inside an HTML tag to display the message content of an email.','easy-form-builder'),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : esc_html__('To display the website name, add this shortcode inside a HTML tag.','easy-form-builder'),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : esc_html__('Add this shortcode within a HTML tag to display the Website URL.','easy-form-builder'),
			"shortcodeAdminEmailInfo" => $state  &&  isset($ac->text->shortcodeAdminEmailInfo) ? $ac->text->shortcodeAdminEmailInfo : esc_html__('You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.','easy-form-builder'),
			"noticeEmailContent" => $state  &&  isset($ac->text->noticeEmailContent) ? $ac->text->noticeEmailContent : esc_html__('Please note that if the Editor field is left blank, the default Email Template will be used.','easy-form-builder'),
			"templates" => $state  &&  isset($ac->text->templates) ? $ac->text->templates : esc_html__('Templates','easy-form-builder'),
			"maxSelect" => $state  &&  isset($ac->text->maxSelect) ? $ac->text->maxSelect : esc_html__('Max selection','easy-form-builder'),
			"minSelect" => $state  &&  isset($ac->text->minSelect) ? $ac->text->minSelect : esc_html__('Min selection','easy-form-builder'),
			"dNotShowBg" => $state  &&  isset($ac->text->dNotShowBg) ? $ac->text->dNotShowBg : esc_html__('Do not show the background.','easy-form-builder'),
			"contactusTemplate" => $state  &&  isset($ac->text->contactusTemplate) ? $ac->text->contactusTemplate : esc_html__('Contact us Template','easy-form-builder'),
			"curved" => $state  &&  isset($ac->text->curved) ? $ac->text->curved : esc_html__('Curved','easy-form-builder'),
			"multiStep" => $state  &&  isset($ac->text->multiStep) ? $ac->text->multiStep : esc_html__('Multi-Step','easy-form-builder'),
			"customerFeedback" => $state  &&  isset($ac->text->customerFeedback) ? $ac->text->customerFeedback : esc_html__('Customer Feedback','easy-form-builder'),
			"supportTicketF" => $state  &&  isset($ac->text->supportTicketF) ? $ac->text->supportTicketF : esc_html__('Support Ticket Form','easy-form-builder'),
			"paymentform" => $state  &&  isset($ac->text->paymentform) ? $ac->text->paymentform : esc_html__('Payment Form','easy-form-builder'),
			"stripe" => $state  &&  isset($ac->text->stripe) ? $ac->text->stripe : esc_html__('Stripe','easy-form-builder'),
			"payment" => $state  &&  isset($ac->text->payment ) ? $ac->text->payment  : esc_html__('Payment','easy-form-builder'),
			"address" => $state  &&  isset($ac->text->address ) ? $ac->text->address  : esc_html__('Address','easy-form-builder'),
			"paymentGateway" => $state  &&  isset($ac->text->paymentGateway) ? $ac->text->paymentGateway : esc_html__('Payment Gateway','easy-form-builder'),
			"currency" => $state  &&  isset($ac->text->currency) ? $ac->text->currency : esc_html__('Currency','easy-form-builder'),
			"recurringPayment" => $state  &&  isset($ac->text->recurringPayment) ? $ac->text->recurringPayment : esc_html__('Recurring payment','easy-form-builder'),
			"subscriptionBilling" => $state  &&  isset($ac->text->subscriptionBilling) ? $ac->text->subscriptionBilling : esc_html__('Subscription billing','easy-form-builder'),
			"onetime" => $state  &&  isset($ac->text->onetime) ? $ac->text->onetime : esc_html__('one time','easy-form-builder'),
			"methodPayment" => $state  &&  isset($ac->text->methodPayment) ? $ac->text->methodPayment : esc_html__('Method payment','easy-form-builder'),
			"heading" => $state  &&  isset($ac->text->heading) ? $ac->text->heading : esc_html__('Heading','easy-form-builder'),
			"link" => $state  &&  isset($ac->text->link) ? $ac->text->link : esc_html__('Link','easy-form-builder'),
			"mobile" => $state  &&  isset($ac->text->mobile) ? $ac->text->mobile : esc_html__('Mobile','easy-form-builder'),
			"product" => $state  &&  isset($ac->text->product) ? $ac->text->product : esc_html__('product','easy-form-builder'),
			"value" => $state  &&  isset($ac->text->value) ? $ac->text->value : esc_html__('value','easy-form-builder'),
			"terms" => $state  &&  isset($ac->text->terms) ? $ac->text->terms : esc_html__('terms','easy-form-builder'),
			"pricingTable" => $state  &&  isset($ac->text->pricingTable) ? $ac->text->pricingTable : esc_html__('Pricing Table','easy-form-builder'),
			"cardNumber" => $state  &&  isset($ac->text->cardNumber) ? $ac->text->cardNumber : esc_html__('Card Number','easy-form-builder'),
			"cardExpiry" => $state  &&  isset($ac->text->cardExpiry) ? $ac->text->cardExpiry : esc_html__('Card Expiry','easy-form-builder'),
			"cardCVC" => $state  &&  isset($ac->text->cardCVC) ? $ac->text->cardCVC : esc_html__('Card CVC','easy-form-builder'),
			"payNow" => $state  &&  isset($ac->text->payNow) ? $ac->text->payNow : esc_html__('Pay Now','easy-form-builder'),
			"payAmount" => $state  &&  isset($ac->text->payAmount) ? $ac->text->payAmount : esc_html__('Pay amount','easy-form-builder'),
			"successPayment" => $state  &&  isset($ac->text->successPayment) ? $ac->text->successPayment : esc_html__('Success payment','easy-form-builder'),
			"transctionId" => $state  &&  isset($ac->text->transctionId) ? $ac->text->transctionId : esc_html__('Transaction Id','easy-form-builder'),
			"addPaymentGetway" => $state  &&  isset($ac->text->addPaymentGetway) ? $ac->text->addPaymentGetway : esc_html__('Error: No payment gateway has been added to the form.','easy-form-builder'),
			"emptyCartM" => $state  &&  isset($ac->text->emptyCartM) ? $ac->text->emptyCartM : esc_html__('Your cart is currently empty. Please add items to continue.','easy-form-builder'),
			"payCheckbox" => $state  &&  isset($ac->text->payCheckbox) ? $ac->text->payCheckbox : esc_html__('Payment Multi choose','easy-form-builder'),
			"payRadio" => $state  &&  isset($ac->text->payRadio) ? $ac->text->payRadio : esc_html__('Payment Single choose','easy-form-builder'),
			"paySelect" => $state  &&  isset($ac->text->paySelect) ? $ac->text->paySelect : esc_html__('Payment Selection Choose','easy-form-builder'),
			"payMultiselect" => $state  &&  isset($ac->text->payMultiselect) ? $ac->text->payMultiselect : esc_html__('Payment dropdown list','easy-form-builder'),
			"errorCode" => $state  &&  isset($ac->text->errorCode) ? $ac->text->errorCode : esc_html__('Error Code','easy-form-builder'),
			"stripeKeys" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : esc_html__('Stripe Keys','easy-form-builder'),
			"stripeMP" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : esc_html__('If you want to use payment functionality in your forms, you will need to obtain your Stripe keys.','easy-form-builder'),
			"publicKey" => $state  &&  isset($ac->text->publicKey) ? $ac->text->publicKey : esc_html__('Public Key','easy-form-builder'),
			"price" => $state  &&  isset($ac->text->price) ? $ac->text->price : esc_html__('Price','easy-form-builder'),
			"title" => $state  &&  isset($ac->text->title) ? $ac->text->title : esc_html__('title','easy-form-builder'),
			"medium" => $state  &&  isset($ac->text->medium) ? $ac->text->medium : esc_html__('Medium','easy-form-builder'),
			"small" => $state  &&  isset($ac->text->small) ? $ac->text->small : esc_html__('Small','easy-form-builder'),
			"xsmall" => $state  &&  isset($ac->text->xsmall) ? $ac->text->xsmall : esc_html__('XSmall','easy-form-builder'),
			"xxsmall" => $state  &&  isset($ac->text->xxsmall) ? $ac->text->xxsmall : esc_html__('XXSmall','easy-form-builder'),
			"createPaymentForm" => $state  &&  isset($ac->text->createPaymentForm) ? $ac->text->createPaymentForm : esc_html__('Create a payment form.','easy-form-builder'),
			"pro" => $state  &&  isset($ac->text->pro) ? $ac->text->pro : esc_html__('Pro','easy-form-builder'),
			"submit" => $state  &&  isset($ac->text->submit) ? $ac->text->submit : esc_html__('Submit','easy-form-builder'),
			"purchaseOrder" => $state  &&  isset($ac->text->purchaseOrder) ? $ac->text->purchaseOrder : esc_html__('Purchase Order','easy-form-builder'),
			"paymentNcaptcha" => $state  &&  isset($ac->text->paymentNcaptcha) ? $ac->text->paymentNcaptcha : esc_html__('It is not possible to include reCAPTCHA on payment forms.','easy-form-builder'),
			"PleaseMTPNotWork" => $state &&  isset($ac->text->PleaseMTPNotWork) ? $ac->text->PleaseMTPNotWork : esc_html__('Easy Form Builder could not confirm if your service is able to send emails. Please check your email inbox (or spam folder) to see if you have received an email with the subject line: Email server [Easy Form Builder]. If you have received the email, please select the option < This site can send emails > and save the changes.','easy-form-builder'),
			"hostSupportSmtp" => $state  &&  isset($ac->text->hostSupportSmtp) ? $ac->text->hostSupportSmtp : esc_html__('This site can send emails','easy-form-builder'),
			/* translators: %s is the toggle button name */
			"PleaseMTPNotWork2" => $state &&  isset($ac->text->PleaseMTPNotWork2) ? $ac->text->PleaseMTPNotWork2 : esc_html__('Easy Form Builder could not confirm that your server can send emails. Please check your inbox or spam folder for an email with the subject: "Email server [Easy Form Builder]". If you received it, please enable the "%s" toggle and save your changes.','easy-form-builder'),
			"hostSupportSmtp2" => $state  &&  isset($ac->text->hostSupportSmtp2) ? $ac->text->hostSupportSmtp2 : esc_html__('I confirm that this WordPress site is able to send emails properly','easy-form-builder'),
			"interval" => $state  &&  isset($ac->text->interval) ? $ac->text->interval : esc_html__('Interval','easy-form-builder'),
			"nextBillingD" => $state  &&  isset($ac->text->nextBillingD) ? $ac->text->nextBillingD : esc_html__('Next Billing Date','easy-form-builder'),
			"dayly" => $state  &&  isset($ac->text->dayly) ? $ac->text->dayly : esc_html__('Daily','easy-form-builder'),
			"monthly" => $state  &&  isset($ac->text->monthly) ? $ac->text->monthly : esc_html__('Monthly','easy-form-builder'),
			"weekly" => $state  &&  isset($ac->text->weekly) ? $ac->text->weekly : esc_html__('Weekly','easy-form-builder'),
			"yearly" => $state  &&  isset($ac->text->yearly) ? $ac->text->yearly : esc_html__('Yearly','easy-form-builder'),
			"howProV" => $state  &&  isset($ac->text->howProV) ? $ac->text->howProV : esc_html__('How to activate Pro version of Easy form builder','easy-form-builder'),
			"uploadedFile" => $state  &&  isset($ac->text->uploadedFile) ? $ac->text->uploadedFile : esc_html__('Uploaded File','easy-form-builder'),
			"offlineMSend" => $state  &&  isset($ac->text->offlineMSend) ? $ac->text->offlineMSend : esc_html__('Your internet connection has been lost, but do not worry, we have saved the information you entered on this form. Once you are reconnected to the internet, you can easily send your information by clicking the submit button.','easy-form-builder'),
			"offlineSend" => $state  &&  isset($ac->text->offlineSend) ? $ac->text->offlineSend : esc_html__('Please ensure that you have a stable internet connection and try again.','easy-form-builder'),
			"options" => $state  &&  isset($ac->text->options) ? $ac->text->options : esc_html__('Options','easy-form-builder'),
			"eJQ500" => $state  &&  isset($ac->text->eJQ500) ? $ac->text->eJQ500 : esc_html__('You are experiencing issues with JQuery. Please contact the administrator for assistance. (Error code: JQ-500)','easy-form-builder'),
			"nonceExpired" => $state  &&  isset($ac->text->nonceExpired) ? $ac->text->nonceExpired : esc_html__('Your session has expired. Please refresh the page and try again.','easy-form-builder'),
			"basic" => $state  &&  isset($ac->text->basic) ? $ac->text->basic : esc_html__('Basic','easy-form-builder'),
			"blank" => $state  &&  isset($ac->text->blank) ? $ac->text->blank : esc_html__('Blank','easy-form-builder'),
			"support" => $state  &&  isset($ac->text->support) ? $ac->text->support : esc_html__('Support','easy-form-builder'),
			"signInUp" => $state  &&  isset($ac->text->signInUp) ? $ac->text->signInUp : esc_html__('Sign-In|Up','easy-form-builder'),
			"advance" => $state  &&  isset($ac->text->advance) ? $ac->text->advance : esc_html__('Advance','easy-form-builder'),
			"all" => $state  &&  isset($ac->text->all) ? $ac->text->all : esc_html__('All','easy-form-builder'),
			"new" => $state  &&  isset($ac->text->new) ? $ac->text->new : esc_html__('New','easy-form-builder'),
			"landingTnx" => $state  &&  isset($ac->text->landingTnx) ? $ac->text->landingTnx : esc_html__('Thank you Page','easy-form-builder'),
			"redirectPage" => $state  &&  isset($ac->text->redirectPage) ? $ac->text->redirectPage : esc_html__('Redirect page','easy-form-builder'),
			"pWRedirect" => $state  &&  isset($ac->text->pWRedirect) ? $ac->text->pWRedirect : esc_html__('Please wait, you will be redirected shortly.','easy-form-builder'),
			"persiaPayment" => $state  &&  isset($ac->text->persiaPayment) ? $ac->text->persiaPayment : esc_html__('Persia payment','easy-form-builder'),
			"getPro" => $state  &&  isset($ac->text->getPro) ? $ac->text->getPro : esc_html__('Activate the Pro version.','easy-form-builder'),
			"yFreeVEnPro" => $state  &&  isset($ac->text->yFreeVEnPro) ? $ac->text->yFreeVEnPro : esc_html__('You are using the free version. Activate the pro version now to get access to more and advanced professional features for only $NN/year.','easy-form-builder'),
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('Add-on','easy-form-builder'),
			"addons" => $state  &&  isset($ac->text->addons) ? $ac->text->addons : esc_html__('Add-ons','easy-form-builder'),
			"stripeTAddon" => $state  &&  isset($ac->text->stripeTAddon) ? $ac->text->stripeTAddon : esc_html__('Stripe Payment Addon','easy-form-builder'),
			"stripeDAddon" => $state  &&  isset($ac->text->stripeDAddon) ? $ac->text->stripeDAddon : esc_html__('The Stripe add-on for Easy Form Builder enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.','easy-form-builder'),
			"offlineTAddon" => $state  &&  isset($ac->text->offlineTAddon) ? $ac->text->offlineTAddon : esc_html__('Offline Forms Addon','easy-form-builder'),
			"offlineDAddon" => $state  &&  isset($ac->text->offlineDAddon) ? $ac->text->offlineDAddon : esc_html__('The Offline Forms add-on for Easy Form Builder allows users to save their progress when filling out forms in offline situations.','easy-form-builder'),

			"trackCTAddon" => $state  &&  isset($ac->text->trackCTAddon) ? $ac->text->trackCDAddon : esc_html__('trackCTAddon','easy-form-builder'),
			"trackCDAddon" => $state  &&  isset($ac->text->trackCDAddon) ? $ac->text->trackCDAddon : esc_html__('trackCDAddon','easy-form-builder'),
			"install" => $state  &&  isset($ac->text->install) ? $ac->text->install : esc_html__('Install','easy-form-builder'),
			"upDMsg" => $state  &&  isset($ac->text->upDMsg) ? $ac->text->upDMsg : esc_html__('Please update Easy Form Builder before trying again.','easy-form-builder'),
			"AfLnFrm" => $state  &&  isset($ac->text->AfLnFrm) ? $ac->text->AfLnFrm : esc_html__('Activation of offline form mode.','easy-form-builder'),
			"IMAddons" => $state  &&  isset($ac->text->IMAddons) ? $ac->text->IMAddons : esc_html__('Before activation this option, install','easy-form-builder'),
			"IMAddonP" => $state  &&  isset($ac->text->IMAddonP) ? $ac->text->IMAddonP : esc_html__('To create a payment form, you must first install a payment add-on such as the Stripe Add-on.','easy-form-builder'),
			"allformat" => $state  &&  isset($ac->text->allformat) ? $ac->text->allformat : esc_html__('All formats','easy-form-builder'),
			"AdnSST" => $state  &&  isset($ac->text->AdnSST) ? $ac->text->AdnSST : esc_html__('EFB SMS Addon','easy-form-builder'),
			"AdnSSD" => $state  &&  isset($ac->text->AdnSSD) ? $ac->text->AdnSSD : esc_html__('Enable SMS functionality in your forms with the EFB SMS add-on, allowing you to validate mobile numbers and send confirmation codes via SMS, as well as receive notifications through SMS service.','easy-form-builder'),
			"AdnATCT" => $state  &&  isset($ac->text->AdnATCT) ? $ac->text->AdnATCT : esc_html__('Advanced confirmation code Addon','easy-form-builder'),
			"AdnATCD" => $state  &&  isset($ac->text->AdnATCD) ? $ac->text->AdnATCD : esc_html__('Send a confirmation code via email or SMS to users and/or admins, allowing them to quickly access new responses.','easy-form-builder'),
			"chlCheckBox" => $state  &&  isset($ac->text->chlCheckBox) ? $ac->text->chlCheckBox : esc_html__('Box Checklist','easy-form-builder'),
			"chlRadio" => $state  &&  isset($ac->text->chlRadio) ? $ac->text->chlRadio : esc_html__('Radio Checklist','easy-form-builder'),
			"qty" => $state  &&  isset($ac->text->qty) ? $ac->text->qty : esc_html__('Qty','easy-form-builder'),
			"wwpb" => $state  &&  isset($ac->text->wwpb) ? $ac->text->wwpb : esc_html__('This is a warning for WPBakery users. For more information, please click here.','easy-form-builder'),
			"clsdrspnsM" => $state  &&  isset($ac->text->clsdrspnsM) ? $ac->text->clsdrspnsM : esc_html__('Are you sure you want to close the responses to this message?','easy-form-builder'),
			"clsdrspnsMo" => $state  &&  isset($ac->text->clsdrspnsMo) ? $ac->text->clsdrspnsMo : esc_html__('Are you sure you want to open the responses to this message?','easy-form-builder'),
			"clsdrspn" => $state  &&  isset($ac->text->clsdrspn) ? $ac->text->clsdrspn : esc_html__('The response has been closed by Admin.','easy-form-builder'),
			"clsdrspo" => $state  &&  isset($ac->text->clsdrspo) ? $ac->text->clsdrspo : esc_html__('The response has been opened by Admin.','easy-form-builder'),
			"open" => $state  &&  isset($ac->text->open) ? $ac->text->open : esc_html__('Open','easy-form-builder'),
			"priceyr" => $state  &&  isset($ac->text->priceyr) ? $ac->text->priceyr : esc_html__('$NN/year','easy-form-builder'),
			"cols" => $state  &&  isset($ac->text->cols) ? $ac->text->cols : esc_html__('columns','easy-form-builder'),
			"col" => $state  &&  isset($ac->text->col) ? $ac->text->col : esc_html__('column','easy-form-builder'),
			"ilclizeFfb" => $state  &&  isset($ac->text->ilclizeFfb) ? $ac->text->ilclizeFfb : esc_html__('I would like to localize Easy Form Builder.','easy-form-builder'),
			"mlen" => $state  &&  isset($ac->text->mlen) ? $ac->text->mlen : esc_html__('Max length','easy-form-builder'),
			"milen" => $state  &&  isset($ac->text->milen) ? $ac->text->milen : esc_html__('Min length','easy-form-builder'),
			"mmlen" => $state  &&  isset($ac->text->mmlen) ? $ac->text->mmlen : esc_html__('The maximum number of characters allowed in the input element is 524288','easy-form-builder'),
			"mmplen" => $state  &&  isset($ac->text->mmplen) ? $ac->text->mmplen : esc_html__('Please enter a value that is at least NN characters long.','easy-form-builder'),
			"mcplen" => $state  &&  isset($ac->text->mcplen) ? $ac->text->mcplen : esc_html__('Please enter a number that is greater than or equal to NN.','easy-form-builder'),
			"mmxplen" => $state  &&  isset($ac->text->mmxplen) ? $ac->text->mmxplen : esc_html__('Please Enter a maximum of NN Characters For this field','easy-form-builder'),
			"mxcplen" => $state  &&  isset($ac->text->mxcplen) ? $ac->text->mxcplen : esc_html__('Please enter a number that is less than or equal to NN','easy-form-builder'),
			"max" => $state  &&  isset($ac->text->max) ? $ac->text->max : esc_html__('Max','easy-form-builder'),
			"min" => $state  &&  isset($ac->text->min) ? $ac->text->min : esc_html__('Min','easy-form-builder'),
			"mxlmn" => $state  &&  isset($ac->text->mxlmn) ? $ac->text->mxlmn : esc_html__('Minimum entry must lower than maximum entry','easy-form-builder'),
			"disabled" => $state  &&  isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Disabled','easy-form-builder'),
			"hflabel" => $state  &&  isset($ac->text->hflabel) ? $ac->text->hflabel : esc_html__('Hide the label','easy-form-builder'),
			"resop" => $state  &&  isset($ac->text->resop) ? $ac->text->resop : esc_html__('The response(ticket) closed','easy-form-builder'),
			"rescl" => $state  &&  isset($ac->text->rescl) ? $ac->text->rescl : esc_html__('The response(ticket) opened','easy-form-builder'),
			"clcdetls" => $state  &&  isset($ac->text->clcdetls) ? $ac->text->clcdetls : esc_html__('Click here for more details','easy-form-builder'),
			"lson" => $state  &&  isset($ac->text->lson) ? $ac->text->lson : esc_html__('Label of the ON status','easy-form-builder'),
			"lsoff" => $state  &&  isset($ac->text->lsoff) ? $ac->text->lsoff : esc_html__('Label of the OFF status','easy-form-builder'),
			"pr5" => $state  &&  isset($ac->text->pr5) ? $ac->text->pr5 : esc_html__('5 Point Scale','easy-form-builder'),
			"nps_" => $state  &&  isset($ac->text->nps_) ? $ac->text->nps_ : esc_html__('Net Promoter Score','easy-form-builder'),
			"nps_tm" => $state  &&  isset($ac->text->nps_tm) ? $ac->text->nps_tm : esc_html__('NPS Table Matrix','easy-form-builder'),
			"pointr10" => $state  &&  isset($ac->text->pointr10) ? $ac->text->pointr10 : esc_html__('Net Promoter Score','easy-form-builder'),
			"pointr5" => $state  &&  isset($ac->text->pointr5) ? $ac->text->pointr5 : esc_html__('5 Point Scale','easy-form-builder'),
			"table_matrix" => $state  &&  isset($ac->text->table_matrix) ? $ac->text->table_matrix : esc_html__('NPS Table Matrix','easy-form-builder'),
			"pdate" => $state  &&  isset($ac->text->pdate) ? $ac->text->pdate : esc_html__('Jalali Date','easy-form-builder'),
			"ardate" => $state  &&  isset($ac->text->ardate) ? $ac->text->ardate : esc_html__('Hijri Date','easy-form-builder'),
			"iaddon" => $state  &&  isset($ac->text->iaddon) ? $ac->text->iaddon : esc_html__('Install the addon','easy-form-builder'),
			"IMAddonPD" => $state  &&  isset($ac->text->IMAddonPD) ? $ac->text->IMAddonPD : esc_html__('Please go to Add-ons Page of Easy Form Builder plugin and install the Jalili date addons','easy-form-builder'),
			"IMAddonAD" => $state  &&  isset($ac->text->IMAddonAD) ? $ac->text->IMAddonAD : esc_html__('Please go to Add-ons Page of Easy Form Builder plugin and install the Hijri date addons','easy-form-builder'),
			"warning" => $state  &&  isset($ac->text->warning) ? $ac->text->warning : esc_html__('warning','easy-form-builder'),
			"datetimelocal" => $state  &&  isset($ac->text->datetimelocal) ? $ac->text->datetimelocal : esc_html__('date & time','easy-form-builder'),
			"dsupfile" => $state  &&  isset($ac->text->dsupfile) ? $ac->text->dsupfile : esc_html__('Activate the file upload button in the response box','easy-form-builder'),
			"scaptcha" => $state  &&  isset($ac->text->scaptcha) ? $ac->text->scaptcha : esc_html__('Activate Google reCAPTCHA in the response box','easy-form-builder'),
			"sdlbtn" => $state  &&  isset($ac->text->sdlbtn) ? $ac->text->sdlbtn : esc_html__('Activate the download button in the response box.','easy-form-builder'),
			"sips" => $state  &&  isset($ac->text->sips) ? $ac->text->sips : esc_html__('Display the IP addresses of users in the response box.','easy-form-builder'),
			"persiaPayTAddon" => $state  &&  isset($ac->text->persiaPayTAddon) ? $ac->text->persiaPayTAddon : esc_html__('Persia Payment Addon','easy-form-builder'),
			"persiaPayDAddon" => $state  &&  isset($ac->text->persiaPayDAddon) ? $ac->text->persiaPayDAddon : esc_html__('The Persia payment addon for Easy Form Builder enables you to connect your website with Persia payment to process payments, donations, and online orders.','easy-form-builder'),

			"datePTAddon" => $state  &&  isset($ac->text->datePTAddon) ? $ac->text->datePTAddon : esc_html__('Jalali date Addon','easy-form-builder'),
			"datePDAddon" => $state  &&  isset($ac->text->datePDAddon) ? $ac->text->datePDAddon : esc_html__('The Jalali date addon allows you to add a Jalali date field to your forms and create any type of form that includes this Shamsi date field.','easy-form-builder'),
			"dateATAddon" => $state  &&  isset($ac->text->dateATAddon) ? $ac->text->dateATAddon : esc_html__('Hijri date Addon','easy-form-builder'),
			"dateADAddon" => $state  &&  isset($ac->text->dateADAddon) ? $ac->text->dateADAddon : esc_html__('The Hijri date addon allows you to add a Hijri date field to your forms and create any type of form that includes this field.','easy-form-builder'),
			"smsTAddon" => $state  &&  isset($ac->text->smsTAddon) ? $ac->text->smsTAddon : esc_html__('SMS service Addon','easy-form-builder'),
			"smsDAddon" => $state  &&  isset($ac->text->smsDAddon) ? $ac->text->smsDAddon : esc_html__('The SMS service addon enables you to receive notification SMS messages when you or your customers receive new messages or responses.','easy-form-builder'),
			"mPAdateW" => $state  &&  isset($ac->text->mPAdateW) ? $ac->text->mPAdateW : esc_html__('Please install either the Hijri or Jalali date addon. You cannot install both addons simultaneously.','easy-form-builder'),
			"rbox" => $state  &&  isset($ac->text->rbox) ? $ac->text->rbox : esc_html__('Response box','easy-form-builder'),
			"smartcr" => $state  &&  isset($ac->text->smartcr) ? $ac->text->smartcr : esc_html__('Regions Drop-Down','easy-form-builder'),
			"ptrnMmm" => $state  &&  isset($ac->text->ptrnMmm) ? $ac->text->ptrnMmm : esc_html__('The value of the XXX field does not match the pattern and must be at least NN characters.','easy-form-builder'),
			"ptrnMmx" => $state  &&  isset($ac->text->ptrnMmx) ? $ac->text->ptrnMmx : esc_html__('The value of the XXX field does not match the pattern and must be at most NN characters.','easy-form-builder'),
			"mnvvXXX" => $state  &&  isset($ac->text->mnvvXX) ? $ac->text->mnvvXXX : esc_html__('Please enter valid value for the XXX field.','easy-form-builder'),
			"wmaddon" => $state  &&  isset($ac->text->wmaddon) ? $ac->text->wmaddon : esc_html__('You are seeing this message because your required add-ons are being installed. Please wait a few minutes and then visit this page again. If it has been more than five minutes and nothing has happened, please contact the support team of Easy Form Builder at Whitestudio.team.','easy-form-builder'),
			"cpnnc" => $state  &&  isset($ac->text->cpnnc) ? $ac->text->cpnnc : esc_html__('The cell phone number is not correct','easy-form-builder'),
			"icc" => $state  &&  isset($ac->text->icc) ? $ac->text->icc : esc_html__('Invalid country code','easy-form-builder'),
			"cpnts" => $state  &&  isset($ac->text->cpnts) ? $ac->text->cpnts : esc_html__('The cell phone number is too short','easy-form-builder'),
			"cpntl" => $state  &&  isset($ac->text->cpntl) ? $ac->text->cpntl : esc_html__('The cell phone number is too long','easy-form-builder'),
			"scdnmi" => $state  &&  isset($ac->text->scdnmi) ? $ac->text->scdnmi : esc_html__('Please select the number of countries to display within an acceptable range.','easy-form-builder'),
			"dField" => $state  &&  isset($ac->text->dField) ? $ac->text->dField : esc_html__('Disabled Field','easy-form-builder'),
			"hField" => $state  &&  isset($ac->text->hField) ? $ac->text->hField : esc_html__('Hidden Field','easy-form-builder'),
			"sctdlosp" => $state  &&  isset($ac->text->sctdlosp) ? $ac->text->sctdlosp : esc_html__('Select a country to display a list of states/provinces.','easy-form-builder'),
			"sctdlocp" => $state  &&  isset($ac->text->sctdlocp) ? $ac->text->sctdlocp : esc_html__('Select a states/provinces to display a list of city.','easy-form-builder'),

			"AdnOF" => $state  &&  isset($ac->text->AdnOf) ? $ac->text->AdnOf : esc_html__('Offline Forms Addon','easy-form-builder'),
			"AdnSPF" => $state  &&  isset($ac->text->AdnSPF) ? $ac->text->AdnSPF : esc_html__('Stripe Payment Addon','easy-form-builder'),
			"AdnPDP" => $state  &&  isset($ac->text->AdnPDP) ? $ac->text->AdnPDP : esc_html__('Jalali date Addon','easy-form-builder'),
			"AdnADP" => $state  &&  isset($ac->text->AdnADP) ? $ac->text->AdnADP : esc_html__('Hijri date Addon','easy-form-builder'),
			"AdnPPF" => $state  &&  isset($ac->text->AdnPPF) ? $ac->text->AdnPPF : esc_html__('Persia Payment Addon','easy-form-builder'),
			"AdnSS" => $state  &&  isset($ac->text->AdnSS) ? $ac->text->AdnSS : esc_html__('SMS service Addon','easy-form-builder'),
			"tfnapca" => $state  &&  isset($ac->text->tfnapca) ? $ac->text->tfnapca : esc_html__('Please contact the administrator as the field is currently unavailable.','easy-form-builder'),
			"wylpfucat" => $state  &&  isset($ac->text->wylpfucat) ? $ac->text->wylpfucat : esc_html__('Would you like to customize the form using the colors of the active template?','easy-form-builder'),
			"efbmsgctm" => $state  &&  isset($ac->text->efbmsgctm) ? $ac->text->efbmsgctm : esc_html__('Easy Form Builder has utilized the colors of the active template. Please choose a color for each option below to customize the form you are creating based on the colors of your template.By selecting a color for each option below, the color of all form fields associated with that feature will change accordingly.','easy-form-builder'),
			"btntcs" => $state  &&  isset($ac->text->btntcs) ? $ac->text->btntcs : esc_html__('Buttons text colors','easy-form-builder'),

			"atcfle" => $state  &&  isset($ac->text->atcfle) ? $ac->text->atcfle : esc_html__('attached files','easy-form-builder'),
			"dslctd" => $state  &&  isset($ac->text->dslctd) ? $ac->text->dslctd : esc_html__('Default selected','easy-form-builder'),
			"shwattr" => $state  &&  isset($ac->text->shwattr) ? $ac->text->shwattr : esc_html__('Show attributes','easy-form-builder'),
			"hdattr" => $state  &&  isset($ac->text->hdattr) ? $ac->text->hdattr : esc_html__('Hide attributes','easy-form-builder'),
			"idl5" => $state  &&  isset($ac->text->idl5) ? $ac->text->idl5 : esc_html__('The ID length should be at least 3 characters long.','easy-form-builder'),
			"idmu" => $state  &&  isset($ac->text->idmu) ? $ac->text->idmu : esc_html__('The ID value must be unique, as it is already being used in this field. please try a new, unique value.','easy-form-builder'),
			"imgRadio" => $state  &&  isset($ac->text->imgRadio) ? $ac->text->imgRadio : esc_html__('Image picker','easy-form-builder'),
			"iimgurl" => $state  &&  isset($ac->text->iimgurl) ? $ac->text->iimgurl : esc_html__('Insert an image url','easy-form-builder'),
			"newbkForm" => $state &&  isset($ac->text->newbkForm)? $ac->text->newbkForm : esc_html__('New Booking Form','easy-form-builder'),
			"bkXpM" => $state  &&  isset($ac->text->bkXpM) ? $ac->text->bkXpM : esc_html__('We are sorry, the booking time for the XXX option has expired. Please choose from the other available options.','easy-form-builder'),
			"bkFlM" => $state  &&  isset($ac->text->bkFlM) ? $ac->text->bkFlM : esc_html__('We are sorry, the XXX option is currently at full capacity. Please choose from the other available options.','easy-form-builder'),
			"AdnSMF" => $state  &&  isset($ac->text->AdnSMF) ? $ac->text->AdnSMF : esc_html__('Conditional logic Addon','easy-form-builder'),
			"condATAddon" => $state  &&  isset($ac->text->condATAddon) ? $ac->text->condATAddon : esc_html__('Conditional logic Addon','easy-form-builder'),
			"condADAddon" => $state  &&  isset($ac->text->condADAddon) ? $ac->text->condADAddon : esc_html__('The Conditional Logic Addon enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users unique needs.','easy-form-builder'),

			"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : esc_html__('Enable Conditional','easy-form-builder'),
			"enableCon" => $state  &&  isset($ac->text->enableCon) ? $ac->text->enableCon : esc_html__('Enable Conditional','easy-form-builder'),
			"show" => $state  &&  isset($ac->text->show) ? $ac->text->show : esc_html__('Show','easy-form-builder'),
			"hide" => $state  &&  isset($ac->text->hide) ? $ac->text->hide : esc_html__('Hide','easy-form-builder'),
			"tfif" => $state  &&  isset($ac->text->tfif) ? $ac->text->tfif : esc_html__('This field if','easy-form-builder'),
			"contains" => $state  &&  isset($ac->text->contains) ? $ac->text->contains : esc_html__('Contains','easy-form-builder'),
			"ncontains" => $state  &&  isset($ac->text->ncontains) ? $ac->text->ncontains : esc_html__('Not contain','easy-form-builder'),
			"startw" => $state  &&  isset($ac->text->startw) ? $ac->text->startw : esc_html__('starts with','easy-form-builder'),
			"endw" => $state  &&  isset($ac->text->endw) ? $ac->text->endw : esc_html__('ends with','easy-form-builder'),
			"gthan" => $state  &&  isset($ac->text->gthan) ? $ac->text->gthan : esc_html__('greater than','easy-form-builder'),
			"lthan" => $state  &&  isset($ac->text->lthan) ? $ac->text->lthan : esc_html__('less than','easy-form-builder'),
			"ise" => $state  &&  isset($ac->text->ise) ? $ac->text->ise : esc_html__('Is','easy-form-builder'),
			"isne" => $state  &&  isset($ac->text->isne) ? $ac->text->isne : esc_html__('Is not','easy-form-builder'),
			"empty" => $state  &&  isset($ac->text->empty) ? $ac->text->empty : esc_html__('Empty','easy-form-builder'),
			"nEmpty" => $state  &&  isset($ac->text->nEmpty) ? $ac->text->nEmpty : esc_html__('Not empty','easy-form-builder'),
			"or" => $state  &&  isset($ac->text->or) ? $ac->text->or : esc_html__('or','easy-form-builder'),
			"and" => $state  &&  isset($ac->text->and) ? $ac->text->and : esc_html__('and','easy-form-builder'),
			"addngrp" => $state  &&  isset($ac->text->addngrp) ? $ac->text->addngrp : esc_html__('Add New Group','easy-form-builder'),

			"adduf" => $state  &&  isset($ac->text->adduf) ? $ac->text->adduf : esc_html__('Add your forms','easy-form-builder'),

			"pgbar" => $state  &&  isset($ac->text->pgbar) ? $ac->text->pgbar : esc_html__('Progress bar','easy-form-builder'),
			"smsNotiM" => $state  &&  isset($ac->text->smsNotiM) ? $ac->text->smsNotiM : esc_html__('SMS notification texts','easy-form-builder'),
			"smsNotiMA" => $state  &&  isset($ac->text->smsNotiMA) ? $ac->text->smsNotiMA : esc_html__('The SMS should include your website address','easy-form-builder'),
			"adrss_vld" => $state  &&  isset($ac->text->adrss_vld) ? $ac->text->adrss_vld : esc_html__('Enable Postal Code validation for addresses','easy-form-builder'),
			"adrss_pc" => $state  &&  isset($ac->text->adrss_pc) ? $ac->text->adrss_pc : esc_html__('Enable Postal Code validation','easy-form-builder'),
			"pc_inc_m" => $state  &&  isset($ac->text->pc_inc_m) ? $ac->text->pc_inc_m : esc_html__('The postal code is incorrect.','easy-form-builder'),
			"adrss_inc_m" => $state  &&  isset($ac->text->adrss_inc_m) ? $ac->text->adrss_inc_m : esc_html__('The Address is incorrect.','easy-form-builder'),
			"cities" => $state  &&  isset($ac->text->cities) ? $ac->text->cities : esc_html__('cities','easy-form-builder'),
			"list" => $state  &&  isset($ac->text->list) ? $ac->text->list : esc_html__('XXX list','easy-form-builder'),
			"dftuwln" => $state  &&  isset($ac->text->dftuwln) ? $ac->text->dftuwln : esc_html__('Display forms to users who are logged in.','easy-form-builder'),
			"dftuwp" => $state  &&  isset($ac->text->dftuwp) ? $ac->text->dftuwp : esc_html__('Display forms to users who have the password.','easy-form-builder'),
			"fSiz_l_dy" => $state &&  isset($ac->text->fSiz_l_dy) ? $ac->text->fSiz_l_dy : esc_html__('The uploaded file exceeds the allowable limit of XXX MB.','easy-form-builder'),
			"fSiz_s_dy" => $state &&  isset($ac->text->fSiz_s_dy) ? $ac->text->fSiz_s_dy : esc_html__('The uploaded file is below the required minimum size of XXX MB.','easy-form-builder'),
			"lb_m_fSiz" => $state &&  isset($ac->text->lb_m_fSiz) ? $ac->text->lb_m_fSiz : esc_html__('Maximum File Size','easy-form-builder'),
			"lb_mi_fSiz" => $state &&  isset($ac->text->lb_mi_fSiz) ? $ac->text->lb_mi_fSiz : esc_html__('Minmum File Size','easy-form-builder'),
			"pss" => $state &&  isset($ac->text->pss) ? $ac->text->pss : esc_html__('Passwords','easy-form-builder'),
			"sms_config" => $state &&  isset($ac->text->sms_config) ? $ac->text->sms_config : esc_html__('SMS Configuration','easy-form-builder'),
			"sms_mp" => $state  &&  isset($ac->text->sms_mp) ? $ac->text->sms_mp : esc_html__('To enable SMS notifications in your forms, select the SMS notification delivery method.','easy-form-builder'),
			"sms_ct" => $state  &&  isset($ac->text->sms_ct) ? $ac->text->sms_ct : esc_html__('Select the method to send SMS notifications','easy-form-builder'),
			"sms_admn_no" => $state  &&  isset($ac->text->sms_admn_no) ? $ac->text->sms_admn_no : esc_html__('Enter the admins\' mobile numbers','easy-form-builder'),

			"sms_efbs" => $state  &&  isset($ac->text->sms_efbs) ? $ac->text->sms_efbs : esc_html__('Easy Form Builder SMS service','easy-form-builder'),
			"sms_wpsmss" => $state  &&  isset($ac->text->sms_wpsmss) ? $ac->text->sms_wpsmss : esc_html__('WP SMS plugin By VeronaLabs','easy-form-builder'),
			"wpsms_nm" => $state  &&  isset($ac->text->wpsms_nm) ? $ac->text->wpsms_nm : esc_html__('WP SMS plugin By VeronaLabs is not installed or activated. Please select another option, or install and configure WP SMS.','easy-form-builder'),
			"msg_adons" => $state  &&  isset($ac->text->msg_adons) ? $ac->text->msg_adons : esc_html__('To use this option, please install the NN add-ons from the Easy Form Builder plugin\'s Add-ons page.','easy-form-builder'),
			"sms_noti" => $state  &&  isset($ac->text->sms_noti) ? $ac->text->sms_noti : esc_html__('SMS notifications','easy-form-builder'),
			"sms_dnoti" => $state  &&  isset($ac->text->sms_dnoti) ? $ac->text->sms_dnoti : esc_html__('To send informational text messages, such as notifications or new messages, please enter the mobile numbers of the administrators here.','easy-form-builder'),
			"sms_ndnoti" => $state  &&  isset($ac->text->sms_ndnoti) ? $ac->text->sms_ndnoti : esc_html__(' Note that by entering mobile numbers, all notification messages for all forms and other informational texts will be sent to the provided numbers.','easy-form-builder'),
			"emlc" => $state  &&  isset($ac->text->emlc) ? $ac->text->emlc : esc_html__('Choose Email notification content','easy-form-builder'),
			"emlacl" => $state  &&  isset($ac->text->emlacl) ? $ac->text->emlacl : esc_html__('Send email with confirmation code and link','easy-form-builder'),
			"emlml" => $state  &&  isset($ac->text->emlml) ? $ac->text->emlml : esc_html__('Send email with submitted form content and link','easy-form-builder'),
			"msgemlmp" => $state  &&  isset($ac->text->msgemlmp) ? $ac->text->msgemlmp : esc_html__('To view the map and selected points, simply click here to navigate to the received message page','easy-form-builder'),
			"msgchckvt" => $state  &&  isset($ac->text->msgchckvt) ? $ac->text->msgchckvt : esc_html__('Review the entered values in the XXX tab.this message appeared because an error is detected.','easy-form-builder'),

			"sms" => $state  &&  isset($ac->text->sms) ? $ac->text->sms : esc_html__('SMS','easy-form-builder'),
			"smscw" => $state  &&  isset($ac->text->smscw) ? $ac->text->smscw : esc_html__('Click on the Settings button on the panel page of Easy Form Builder Plugin and configure the SMS sending method. Then, try again.','easy-form-builder'),
			"to" => $state  &&  isset($ac->text->to) ? $ac->text->to : esc_html__('To','easy-form-builder'),
			"esmsno" => $state  &&  isset($ac->text->esmsno) ? $ac->text->esmsno : esc_html__('Enable SMS notifications','easy-form-builder'),
			"payPalTAddon" => $state  &&  isset($ac->text->payPalTAddon) ? $ac->text->payPalTAddon : esc_html__('PayPal Payment Addon','easy-form-builder'),
			"payPalDAddon" => $state  &&  isset($ac->text->payPaleDAddon) ? $ac->text->payPaleDAddon : esc_html__('The PayPal add-on for Easy Form Builder enables you to integrate your WordPress site with PayPal for payment processing, donations, and online orders.','easy-form-builder'),
			"file_cstm" => $state  &&  isset($ac->text->file_cstm) ? $ac->text->file_cstm : esc_html__('Acceptable file types','easy-form-builder'),
			"cstm_rd" => $state  &&  isset($ac->text->cstm_rd) ? $ac->text->cstm_rd : esc_html__('Customized Ordering','easy-form-builder'),
			"maxfs" => $state  &&  isset($ac->text->maxfs) ? $ac->text->maxfs : esc_html__('Max File Size','easy-form-builder'),
			"cityList" => $state  &&  isset($ac->text->cityList) ? $ac->text->cityList : esc_html__('Cities Drop-Down','easy-form-builder'),
			"elan" => $state  &&  isset($ac->text->elan) ? $ac->text->elan : esc_html__('English language','easy-form-builder'),
			"nlan" => $state  &&  isset($ac->text->nlan) ? $ac->text->nlan : esc_html__('National language','easy-form-builder'),
			"stsd" => $state  &&  isset($ac->text->stsd) ? $ac->text->stsd : esc_html__('Select display language','easy-form-builder'),
			"excefb" => $state  &&  isset($ac->text->excefb) ? $ac->text->excefb : esc_html__('The XX plugin might interfere with forms of Easy Form Builder\'s functionality. If you encounter any issues with the Forms, disable caching for the Easy Form Builder plugin in the XX plugin\'s settings.','easy-form-builder'),
			"trya" => $state  &&  isset($ac->text->trya) ? $ac->text->trya : esc_html__('Trying again.','easy-form-builder'),
			"rnfn" => $state  &&  isset($ac->text->rnfn) ? $ac->text->rnfn : esc_html__('Rename the file name','easy-form-builder'),
			"ausdup" => $state  &&  isset($ac->text->ausdup) ? $ac->text->ausdup : esc_html__('Are you sure you want to duplicate the XXX ?','easy-form-builder'),
			"conlog" => $state  &&  isset($ac->text->conlog) ? $ac->text->conlog : esc_html__('Conditional logic','easy-form-builder'),
			"fil" => $state  &&  isset($ac->text->fil) ? $ac->text->fil : esc_html__('Form is loading','easy-form-builder'),
			"stf" => $state  &&  isset($ac->text->stf) ? $ac->text->stf : esc_html__('Submitting the form','easy-form-builder'),
			"address_line" => $state  &&  isset($ac->text->address_line ) ? $ac->text->address_line  : esc_html__('Address','easy-form-builder'),
			"postalcode" => $state  &&  isset($ac->text->postalcode ) ? $ac->text->postalcode  : esc_html__('Postal Code','easy-form-builder'),
			"vmgs" => $state  &&  isset($ac->text->vmgs ) ? $ac->text->vmgs  : esc_html__('View message and reply','easy-form-builder'),
			"prcfld" => $state  &&  isset($ac->text->prcfld) ? $ac->text->prcfld : esc_html__('Price field','easy-form-builder'),
			"ttlprc" => $state  &&  isset($ac->text->ttlprc) ? $ac->text->ttlprc : esc_html__('Total price','easy-form-builder'),
			"total" => $state  &&  isset($ac->text->total) ? $ac->text->total : esc_html__('Total','easy-form-builder'),
			"mlsbjt" => $state  &&  isset($ac->text->mlsbjt) ? $ac->text->mlsbjt : esc_html__('Email Subject','easy-form-builder'),
			"frmtype" => $state  &&  isset($ac->text->frmtype) ? $ac->text->frmtype : esc_html__('Form type','easy-form-builder'),
			"fernvtf" => $state  &&  isset($ac->text->fernvtf) ? $ac->text->fernvtf : esc_html__('The entered data does not match the form type. If you are an admin, please review the form type.','easy-form-builder'),
			"fetf" => $state  &&  isset($ac->text->fetf) ? $ac->text->fetf : esc_html__('Error: Please ensure there is only one form per page.','easy-form-builder'),
			"actvtcmsg" => $state  &&  isset($ac->text->actvtcmsg) ? $ac->text->actvtcmsg : esc_html__('The activation code has been successfully verified. Enjoy Pro features and utilize the Easy Form Builder.','easy-form-builder'),
			/* translators: %s is the confirmation code */
			"msgdml" => $state  &&  isset($ac->text->msgdml) ? $ac->text->msgdml : esc_html__('The confirmation code for this message is %s. By clicking the button below, you will be able to track messages and view received responses. If needed, you can also send a new reply.','easy-form-builder'),
			/* translators: %1$s and %2$s are opening and closing link tags for documentation */
			"msgnml" => $state  &&  isset($ac->text->msgnml) ? $ac->text->msgnml : esc_html__('To explore the full functionality and settings of Easy Form Builder, including email configurations, form creation options, and other features, simply delve into our %1$s documentation %2$s .','easy-form-builder'),
			/* translators: %1$s, %2$s, %3$s, %4$s are opening and closing link tags for help resources */
			"mlntip" => $state  &&  isset($ac->text->mlntip) ? $ac->text->mlntip : esc_html__('Make sure to check your spam folder for test emails. If your emails are being marked as spam or not being sent, it\'s likely due to the hosting provider you are using. You will need to adjust your email server settings to prevent emails sent from your server from being flagged as spam. For more information, %1$s click here %2$s or %3$s contact Easy Form Builder support %4$s.','easy-form-builder'),
			"from" => $state  &&  isset($ac->text->from) ? $ac->text->from : esc_html__('From Address','easy-form-builder'),
			"msgfml" => $state  &&  isset($ac->text->msgfml) ? $ac->text->msgfml : esc_html__('To avoid emails going to spam or not being sent, make sure the email address here matches the one in the SMTP settings.','easy-form-builder'),
			"prsm" => $state  &&  isset($ac->text->prsm) ? $ac->text->prsm : esc_html__('To preview the form, you need to save the built form and try again.','easy-form-builder'),
			"nsrf" => $state  &&  isset($ac->text->nsrf) ? $ac->text->nsrf : esc_html__('No selected rows found.','easy-form-builder'),
			"spprt" => $state  &&  isset($ac->text->spprt) ? $ac->text->spprt : esc_html__('Support','easy-form-builder'),
			"mread" => $state  &&  isset($ac->text->mread) ? $ac->text->mread : esc_html__('Mark as Read','easy-form-builder'),
			"admines" => $state  &&  isset($ac->text->admines) ? $ac->text->admines : esc_html__('Form admins can access the response box after logging in.','easy-form-builder'),
			/* translators: %1$s and %2$s are opening and closing link tags for terms and conditions */
			"trmcn" => $state  &&  isset($ac->text->trmcn) ? $ac->text->trmcn : esc_html__('I have read and agree to %1$sthe terms and conditions%2$s','easy-form-builder'),
			"trmCheckbox" => $state  &&  isset($ac->text->trmCheckbox) ? $ac->text->trmCheckbox : esc_html__('Terms','easy-form-builder'),
			"prvnt" => $state  &&  isset($ac->text->prvnt) ? $ac->text->prvnt : esc_html__('Preview in new tab','easy-form-builder'),
			"mxdt" => $state  &&  isset($ac->text->mxdt) ? $ac->text->mxdt : esc_html__('Maximum date','easy-form-builder'),
			"mindt" => $state  &&  isset($ac->text->mindt) ? $ac->text->mindt : esc_html__('Minimum date','easy-form-builder'),
			/* translators: %s is the list of valid file formats */
			"ivf" => $state  &&  isset($ac->text->ivf) ? $ac->text->ivf : esc_html__('Valid formats: %s','easy-form-builder'),
			"zoom" => $state  &&  isset($ac->text->zoom) ? $ac->text->zoom : esc_html__('Zoom','easy-form-builder'),
			"lpds" => $state  &&  isset($ac->text->lpds) ? $ac->text->lpds : esc_html__('To enable the Location Picker field, Easy Form Builder loads JavaScript files from the unpkg.com CDN for the leafletjs.com service, but only on pages using this feature.','easy-form-builder'),
			"elpo" => $state  &&  isset($ac->text->elpo) ? $ac->text->elpo : esc_html__('Enable Location Picker in Easy Form Builder','easy-form-builder'),
			"jqinl" => $state  &&  isset($ac->text->jqinl) ? $ac->text->jqinl : esc_html__('Easy Form Builder cannot display the form because jQuery is not properly loaded. This issue might be due to incorrect jQuery invocation by another plugin or the current website theme.','easy-form-builder'),
			/* translators: %1$s is the name of the addon */
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('%1$s Addon','easy-form-builder'),
			'tlgm' => $state  &&  isset($ac->text->tlgm) ? $ac->text->tlgm : esc_html__('Telegram','easy-form-builder'),
			"tlgmAddon" => $state  &&  isset($ac->text->tlgmAddon) ? $ac->text->tlgmAddon : esc_html__('Telegram notification Addon','easy-form-builder'),
			"tlgmDAddon" => $state  &&  isset($ac->text->tlgmDAddon) ? $ac->text->tlgmDAddon : esc_html__('The Telegram notification addon lets you get notifications on your Telegram app whenever you receive new messages or responses','easy-form-builder'),
			"eln" => $state  &&  isset($ac->text->eln) ? $ac->text->eln : esc_html__('Enter a location name','easy-form-builder'),
			/* translators: %1$s is the plugin name, %2$s and %3$s are opening and closing link tags for support */
			"alns" => $state  &&  isset($ac->text->alns) ? $ac->text->alns : esc_html__('The %1$s pages are currently unavailable. It looks like another plugin is causing a conflict with %1$s . To fix this issue, %2$s contact %1$s support %3$s for assistance  or try disabling your plugins one at a time to identify the one causing the conflict.','easy-form-builder'),
			/* translators: %s is the notification type */
			"notis" => $state  &&  isset($ac->text->noti) ? $ac->text->noti : esc_html__('%s notification','easy-form-builder'),
			"settings" => $state  &&  isset($ac->text->settings) ? $ac->text->settings : esc_html__('Settings','easy-form-builder'),
			"emlcc" => $state  &&  isset($ac->text->emlcc) ? $ac->text->emlcc : esc_html__('Send email with submitted form content only','easy-form-builder'),
			"copied" => $state  &&  isset($ac->text->copied) ? $ac->text->copied : esc_html__('copied!','easy-form-builder'),
			"srvnrsp" => $state  &&  isset($ac->text->srvnrsp) ? $ac->text->srvnrsp : esc_html__('The website is not responding; please refresh and try again—saving or submitting is not available until it is restored.','easy-form-builder'),
			"thank" => $state  &&  isset($ac->text->thank) ? $ac->text->thank : esc_html__('Thank','easy-form-builder')

		];




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

		return $rtrn;
	}

	public function send_email_state_new($to ,$sub ,$cont,$pro,$state,$link,$st="null"){

				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);
				$email_content_type = isset($state[2]) ? $state[2]  : 'traking_link' ;
			   	$mailResult = "n";
				if(gettype($to) == 'array')ksort($to);
				$server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
				$from =get_bloginfo('name')." <no-reply@".$server_name.">";
				if(gettype($to) == 'array' && isset($to[2]) && is_email($to[2]) ){
					$f = array_pop($to);
					if(gettype($f)=="array"){
						$f = array_pop($f);
					}
					$from =get_bloginfo('name')." <".$f.">";
				}else if (gettype($to) == 'object' && isset($to[2]) && is_email($to[2]) ){
					$f = $to[2];
					unset($to[2]);
					$from =get_bloginfo('name')." <".$f.">";
				}
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from,

				);
				if(gettype($sub)=='string'){
					$message = $this->email_template_efb($pro,$state,$cont,$link,$email_content_type,$st);

					if( $state!="reportProblem"){
						$to_;$mailResult;
						if (gettype($to) == 'string') {
							$mailResult =  wp_mail( $to,$sub, $message, $headers ) ;
						} else {
							$to= array_unique($to);
							foreach ($to as $r) {
							  if(isset($r) && is_email($r)){$mailResult = wp_mail($r, $sub, $message, $headers);}
							}

						}


					}



					if($state=="reportProblem" || $state =="testMailServer" || $state=='addonsDlProblem' ){
						$support="";

						$a=[101,97,115,121,102,111,114,109,98,117,105,108,108,100,101,114,64,103,109,97,105,108,46,99,111,109];
						foreach($a as $i){$support .=chr($i);}

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

					$server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
					$cont .="<hr><br> website:[" . $server_name . "]<br> Pro state:[".$pro . "]<br> email:[".$mail .
					"]<br> role:[".$role."]<br> name:[".$name."]<br> state:[".$state."]";
					$mailResult = wp_mail( $support,$state, $cont, $headers ) ;					}

					return $mailResult;
				}else{
					for($i=0 ; $i<2 ; $i++){
						if(empty($to[$i])==false && $to[$i]!="null" && $to[$i]!=null && $to[$i]!=[null] && $to[$i]!=[]){

							$message = $this->email_template_efb($pro,$state[$i],$cont[$i],$link[$i],$email_content_type,$st);

							if( $state!="reportProblem"){
								$to_;$mailResult;
								$to_ = $to[$i];
								if (gettype($to_) == 'string' && is_email($to_)) {
									$sub_ = $sub[$i];
									$mailResult =  wp_mail( $to_,$sub_, $message, $headers ) ;

								} else {

									$to[$i]= array_unique($to[$i]);
									foreach ($to[$i] as $r) {
										$sub_ = $sub[$i];
										$to_ = $r;
										if(is_email($to_)) $mailResult = wp_mail($to_, $sub_, $message, $headers);
									}

								}





							}
						}
					}


				}
				    remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
			   return $mailResult;
	}

	public function email_template_efb($pro, $state, $m,$link ,$email_content_type,$st="null"){
		$l ='https://whitestudio.team';
		$wp_lan = get_locale();
			 if($wp_lan=="fa_IR"){ $l='https://easyformbuilder.ir'  ;}
			 else if($wp_lan=="ar" || get_locale()=="arq") {$l ="https://ar.whitestudio.team";}
			 else if ($wp_lan=="de_DE") {$l ="https://de.whitestudio.team";}

		$text = ['msgdml','mlntip','msgnml','serverEmailAble','vmgs','getProVersion','sentBy','hiUser','trackingCode','newMessage','createdBy','newMessageReceived','goodJob','createdBy' , 'yFreeVEnPro','WeRecivedUrM'];
        $lang= $this->text_efb($text);
			$footer= "<a class='efb subtle-link' target='_blank' href='".home_url()."'>".$lang["sentBy"]." ".  get_bloginfo('name')."</a>";
		$align ='left';
		$d =  'ltr';
		if(is_rtl()){
			$d =  'rtl' ;
			$align ='right';
		}



		if($st=='null') $st = $this->get_setting_Emsfb();
		if($st=="null") return;


		$temp = isset($st->emailTemp) && strlen($st->emailTemp)>10 ? $st->emailTemp : "0";


		$title=$lang["newMessage"];
		$message = gettype($m)=='string' ?  "<h3>".$m."</h3>" : "<h3>".$m[0]."</h3>";
		$blogName =get_bloginfo('name');
		$user=function_exists("get_user_by")?  get_user_by('id', 1) :false;

		$adminEmail = $user!=false ? $user->user_email :'';
		$blogURL= home_url();


		$dts =  $lang['msgdml'];
		$track_id = '';
		if(gettype($m)=='string'){
			$track_id =$m;
		}else{
			$track_id=$m[0];
		}
		$dts = str_replace('%s', $track_id, $dts);
		$tracking_section = $email_content_type=='just_message' ? "" : "<div id='sectionTracking'><p style='text-align:center'>".$dts." </p><div style='text-align:center'><a href='".$link."' target='_blank'  style='padding:5px;color:white;background:black;' >".$lang['vmgs']."</a></div></div>";
		if($state=="testMailServer"){
			$dt = $lang['msgnml'];
			$de = $lang['mlntip'];
			$de =preg_replace('/^[^.]*\. /', '', $lang['mlntip']);

			$link = "$l/document/send-email-using-smtp-plugin/";
			if($wp_lan=="fa_IR") $link = "$l/داکیومنت/ارسال-ایمیل-بوسیله-افزونه-smtp/";

			$de = str_replace('%1$s',"<a href='$link' target='_blank'>",$de);
			$de = str_replace('%2$s',"</a>",$de);
			$de = str_replace('%3$s',"<a href='$l/support/' target='_blank'>",$de);
			$de = str_replace('%4$s',"</a>",$de);


			$dt = str_replace('%1$s',"<a href='$l/documents/' target='_blank'>",$dt);
			$dt = str_replace('%2$s',"</a>",$dt);
			$title= $lang["serverEmailAble"];
			$message ="<div style='text-align:center'> <p>".  $footer ."</p></div>
			<h3 style='padding:5px 5px 5px 5px;color: #021623;'>". $de ."</h3> <h4 style='padding:5px 5px 5px 5px;color: #021623;'>". $dt ."</h4>
			";
			 if(strlen($st->activeCode)<5){
				$p = str_replace('NN'  ,'19' ,$lang["yFreeVEnPro"]);
				if($wp_lan=="de_DE") $p = str_replace('$'  ,'€' ,$lang["yFreeVEnPro"]);
				$message ="<h2 style='text-align:center'>"
				. $p ."</h2>
				<div style='text-align:center'>
					<a href='".$l."' target='_blank' style='padding:5px 5px 5px 5px;color:white;background:#202a8d;'>".$lang["getProVersion"]."</a>
				</div>
					<h3 style='padding:5px 5px 5px 5px;color: #021623;'>". $de ."</h3>
					<h4 style='padding:5px 5px 5px 5px;color: #021623;'>". $dt ."</h4>
					<div style='text-align:center'><p style='text-align:center'>". $lang["createdBy"] ." WhiteStudio.team</p></div>
				 ";
			 }

		}elseif($state=="newMessage"){


			if(gettype($m)=='string'){
				$dts = str_replace('%s', $m, $dts);
				$link = strpos($link,"?")==true ? $link.'&track='.$m : $link.'?track='.$m;
				$message ="<h2 style='text-align:center'>".$lang["newMessageReceived"]."</h2>
				<p style='text-align:center'>". $lang["trackingCode"].": ".$m." </p>".$tracking_section ;
			}else{
				$dts = str_replace('%s', $m[0], $dts);
				$link = strpos($link,"?")==true ? $link.'&track='.$m[0] : $link.'?track='.$m[0];
				$message ="
				<div style='text-align:".$align.";color:#252526;font-size:14px;background: #f9f9f9;padding: 10px;margin: 20px 5px;'>".$m[1]." </div>".$tracking_section;
			}
		}else{
			if(gettype($m)=='string'){

			$title =$lang["hiUser"];
			$message='<div style="text-align:center">'.$m.'</div>';
			}else{
				$title =$lang["hiUser"];
				$dts = str_replace('%s', $m[0], $dts);
				$message="
				<div style='text-align:center'><h2>".$lang["WeRecivedUrM"]."</h2> </div>
				<div style='text-align:".$align.";color:#252526;font-size:14px;background: #f9f9f9;padding: 10px;margin: 20px 5px;'>".$m[1]." </div>". $tracking_section;
			}
		}

		$val ="
		<html xmlns='http://www.w3.org/1999/xhtml'>
		<head>
		<style type='text/css'>
			@media only screen and (max-width:600px){
			.containerEmailEfb{width:100% !important; max-width:100% !important;}
			.containerEmailEfb .columnEmailEfb{display:block !important; width:100% !important; max-width:100% !important;}
			.containerEmailEfb .columnEmailEfb p{text-align:right !important;}
			.containerEmailEfb img{max-width:100% !important; height:auto !important; display:block !important;}
			}
			</style>
		</head>
		<body style='margin:auto 10px;direction:".$d.";color:#000000;'><center>
			<table class='efb body-wrap' style='text-align:center;width:100%;font-family:arial,sans-serif;border:12px solid rgba(126, 122, 122, 0.08);border-spacing:4px 20px;direction:".$d.";'> <tr>
				<img src='".EMSFB_PLUGIN_URL ."public/assets/images/email_template1.png' alt='$title' style='width:36%;'>
				</tr> <tr> <td><center> <table bgcolor='#FFFFFF' width='100%' border='0'>  <tbody> <tr>
				<td style='font-family:sans-serif;font-size:13px;color:#202020;line-height:1.5'>
					<h1 style='color:#ff4b93;text-align:center;'>".$title."</h1>
					</td></tr><tr style='text-align:".$align.";color:#000000;font-size:14px;'><td>
							<span>".$message." </span>
				</td> </tr>
				<tr style='text-align:center;color:#000000;font-size:14px;height:45px;'><td>

				</td></tr></tbody></center></td>
			</tr></table>
			</center>
			<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='".$align."' style='padding: 30px 30px; font-size:12px; text-align:center'>".$footer."</td></tr></table>
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


				$footer ="<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='".$align."' style='padding: 30px 30px; font-size:12px; text-align:center'>".$footer."</td></tr></table>";
				if($pro==1){	$temp = substr_replace($temp,$footer,($p),0);}

				$val =  $temp;
			}

			return $val;
	}

	public function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}


	public function get_setting_Emsfb()
	{

		$transient = get_transient('emsfb_settings_transient');
		if ($transient !== false && !empty($transient)) {
			if (is_string($transient)) {
				$transient = str_replace('\\', '', $transient);
				$decoded = json_decode($transient);
				if ($decoded !== null) return $decoded;
			} elseif (is_object($transient) || is_array($transient)) {
				return $transient;
			}
		}

		// Check WordPress object cache first
		$cache_key = 'emsfb_settings_latest';
		$cached_value = wp_cache_get($cache_key, 'emsfb');

		if (false !== $cached_value) {
			return $cached_value;
		}

		$table_name = $this->db->prefix . "emsfb_setting";
		$value = $this->db->get_var("SELECT setting FROM $table_name ORDER BY id DESC LIMIT 1");
		if (!isset($value) || empty($value)) {
			return 'null';
		}
		$v = str_replace('\\', '', $value);
		$rtrn = json_decode($v);
		$rtrn = $rtrn != null ? $rtrn : 'null';

		update_option('emsfb_settings', $value);
		if ($rtrn != 'null') {
			set_transient('emsfb_settings_transient', $value, 1440);
			// Cache the decoded object for 1 hour
			wp_cache_set($cache_key, $rtrn, 'emsfb', 3600);
		}

		return $rtrn;
	}

	public function response_to_user_by_msd_id($msg_id,$pro){

		$text = ["youRecivedNewMessage"];
        $lang= $this->text_efb($text);

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
		$link_w = $lst['type']=="w_link" ? $lst['value'].'?track='.$trackingCode : 'null';


		$table_name = $this->db->prefix . "emsfb_form";
		$data = $this->db->get_results("SELECT form_structer FROM `$table_name` WHERE form_id = '$form_id' ORDER BY form_id DESC LIMIT 1");

		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);
		if(($data[0]["sendEmail"]=="true"|| $data[0]["sendEmail"]==true ) &&   strlen($data[0]["email_to"])>2 ){
			$emailsId=[];
			$email_to = $data[0]["email_to"];
			foreach($data as $key=>$val){
				if($val['type']=="email" && isset($val['noti']) && in_array($val['noti'] ,[1,'1',true,'true'],true) ){
					$emailsId[]=$val['id_'];
				}else if ($val['type']=="email" && $val['id_']==$email_to ){
					$emailsId[]=$val['id_'];
				}
			}
			$ac=$this->get_setting_Emsfb();
			$smtp =(isset($ac->smtp) && (bool)$ac->smtp ) ? true : false;
			if($smtp) {
				foreach($user_res as $key=>$val){
					if(isset($user_res[$key]["id_"]) && in_array($user_res[$key]["id_"],$emailsId,true) && isset($val["value"]) && is_email($val["value"]) ){
						$email=$val["value"];
						$subject ="📮 ".$lang["youRecivedNewMessage"];
						$this->send_email_state_new($email ,$subject ,$trackingCode,$pro,"newMessage",$link_w,'null');
					}
				}
			}
		}



		if(isset($data[0]['smsnoti']) && intval($data[0]['smsnoti'])==1){

			$phone_numbers=[[],[]];
			$setting = $this->get_setting_Emsfb('setting');


			$numbers = isset($setting->sms_config) && isset($setting->phnNo) && strlen($setting->phnNo)>5  ? explode(',',$setting->phnNo) :[];
			$phone_numbers[0]= $numbers;



			$have_noti_id =[];
			foreach($data as $key=>$val){
				if($val['type']=="mobile" && isset($val['smsnoti']) && intval($val['smsnoti'])==1){
					array_push($have_noti_id,$val['id_']);
				}
			}
			if(!empty($have_noti_id)){
				foreach ($user_res as $value) {



					if($value['type']=="mobile" && in_array($value['id_'],$have_noti_id)){

						array_push($phone_numbers[1],$value['value']);

					}
				}
			}

			if(isset($setting->sms_config) && ($setting->sms_config=="wpsms" || $setting->sms_config=='ws.team') ) $this->sms_ready_for_send_efb($form_id, $phone_numbers,$link_w,'respp' ,'wpsms' ,$trackingCode);
		}
		return 0;
	}//end function

	public function sanitize_obj_msg_efb ($valp){

		foreach ($valp as $key => $val) {
			$type = $val['type'];
			foreach ($val as $k => $v) {
				switch ($k) {
					case 'value':
						$type =strtolower($type);
						if( (gettype($v)!="array" || gettype($v)!="object" ) && preg_match("/multi/i", $type)==false
						&& (preg_match("/select/i", $type)==true ||  preg_match("/radio/i", $type)==true) ){
							$valp[$key][$k] =$type!="html" ? sanitize_text_field($v) : $this->sanitize_full_html_efb($v);
						}else if ( preg_match("/checkbox/i", $type)==true || preg_match("/multi/i", $type)==true ||gettype($v)=="array" || gettype($v)=="object"){
							if(gettype($v)=="string") break;
							foreach ($v as $ki => $va) {
								$v[$ki]=sanitize_text_field($va);
							}
							$valp[$key][$k] =$v;
						}else{
							$valp[$key][$k] =$type!="html" ? sanitize_text_field($v) : $this->sanitize_full_html_efb($v);
						}

					break;
					case 'email':
					case 'email_to':
						$valp[$key][$k]= $key!=0 && $k!="email_to" ?  sanitize_email($v): sanitize_text_field($v);
					break;
					case 'file':
						$valp[$key][$k]=sanitize_text_field($v);
					break ;
					case 'href':

						$valp[$key][$k]= sanitize_url($v);
					break;
					case 'rePage':
					case 'src':

						$valp[$key][$k]=sanitize_url($v);

					break;
					case 'thank_you_message':

						$valp[$key][$k]['icon']=sanitize_text_field( $v['icon']);
						$valp[$key][$k]['thankYou']=sanitize_text_field( $v['thankYou']);
						$valp[$key][$k]['done']=sanitize_text_field( $v['done']);
						$valp[$key][$k]['trackingCode']=sanitize_text_field( $v['trackingCode']);
						$valp[$key][$k]['pleaseFillInRequiredFields']=sanitize_text_field( $v['pleaseFillInRequiredFields']);
					break;
					case 'autofill_conditions':
						foreach ($valp[$key][$k] as $kei => $value) {
							foreach ($value as $ke => $va) {
								$ke =sanitize_text_field($ke);
								$valp[$key][$k][$kei][$ke]=sanitize_text_field($va);
							}
						}
					break;
					case 'c_c':
						foreach ($valp[$key][$k] as $kei => $value) {
							$valp[$key][$k][$kei] = sanitize_text_field($value);
						}
						break;
						case 'c_n':
							foreach ($valp[$key][$k] as $kei => $value) {
								$valp[$key][$k][$kei] = sanitize_text_field($value);
							}
							break;
					case 'id':
						$valp[$key][$k]= sanitize_text_field($valp[$key][$k]);
						if(strlen($valp[$key][$k])<1) break;

						if($valp[$key]['type']=="option"){

							foreach ($valp as $ki => $vl) {

								if(array_key_exists('id_',$vl)==false) continue;

								if($vl['id_']!=$valp[$key]['parent']){
									continue;
								}
								foreach ($vl as $kii => $vll) {
									if($kii!="value") continue;
									if(gettype($vll)!="array" && gettype($vll)!="object" ){
										if($vll==$valp[$key]['id_'])$vll=$valp[$key][$k];
									}else{
										foreach ($vll as $ke => $vn) {
											if($vn==$valp[$key]['id_']) {
												$valp[$ki][$kii][$ke] =$valp[$key][$k];
											}
										}
									}

								}
							}
							$valp[$key]['id_'] = sanitize_text_field($valp[$key]['id_']);
							$valp[$key]['id_old']=$valp[$key]['id_'];
							$valp[$key]['id_'] = $valp[$key][$k];
							if(isset($valp[$key]['id_op']))$valp[$key]['id_op']=$valp[$key][$k];
							if(isset($valp[$key]['dataId']))$valp[$key]['dataId']=$valp[$key][$k] ."-id";
							$valp[$key]['option'] = $valp[$key][$k];
						}
					break;
					case 'conditions':

						$valp[$key][$k]=sanitize_text_field($v);
					break;
					default:
					$k =sanitize_text_field($k);
					$valp[$key][$k]=sanitize_text_field($v);

					break;
				}
			}
		}
		return $valp;
	}


	public function sanitize_full_html_efb($html) {

		$global_attributes = array(
			'class' => true,
			'id' => true,
			'style' => true,
			'title' => true,
			'data-*' => true,
			'aria-*' => true,
		);



	$allowed_properties = $this->allowed_properties_thml_efb();


	$current_domain = wp_parse_url(home_url(), PHP_URL_HOST);
	$allowed_domains = array('google.com', 'gstatic.com', 'googleapis.com', 'googleusercontent.com', 'youtube.com', 'ytimg.com', 'microsoft.com', 'office.com', 'live.com', 'msn.com', 'outlook.com', 'amazonaws.com', 'cloudfront.net', 'cdnjs.cloudflare.com', 'maxcdn.bootstrapcdn.com', 'jsdelivr.net', 'unpkg.com', 'facebook.com', 'fbcdn.net', 'twitter.com', 'twimg.com', 'github.com', 'github.io', 'vimeo.com', 'vimeocdn.com', 'wikipedia.org', 'wikimedia.org', 'wikidata.org', 'stripe.com', 'paypal.com', 'braintreepayments.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'use.fontawesome.com', 'dailymotion.com', 'dmcdn.net', 'maps.googleapis.com', 'openstreetmap.org', 'mapbox.com', 'gravatar.com', 'unsplash.com', 'placekitten.com', 'placehold.co', 'akamaihd.net', 'cloudflare.com', 'fastly.net', 'linkedin.com', 'apple.com', 'adobe.com', 'cdn.shopify.com', 'example.com', 'example.org', 'trusted.com', 'cdn.trusted.com');

		$allowed_tags = array(
			'a' => array_merge($global_attributes, array(
				'href' => true,
				'title' => true,
				'rel' => true,
				'target' => true
			)),
			'abbr' => array_merge($global_attributes, array('title' => true)),
			'address' => $global_attributes,
			'area' => array_merge($global_attributes, array(
				'alt' => true,
				'coords' => true,
				'href' => true,
				'shape' => true,
				'target' => true,
			)),
			'audio' => array_merge($global_attributes, array(
				'autoplay' => true,
				'controls' => true,
				'loop' => true,
				'muted' => true,
				'preload' => true,
				'src' => true,
			)),
			'b' => $global_attributes,
			'blockquote' => array_merge($global_attributes, array('cite' => true)),
			'br' => $global_attributes,
			'button' => array_merge($global_attributes, array(
				'disabled' => true,
				'name' => true,
				'type' => true,
				'value' => true,
			)),
			'canvas' => array_merge($global_attributes, array('height' => true, 'width' => true)),
			'caption' => $global_attributes,
			'code' => $global_attributes,
			'col' => array_merge($global_attributes, array('span' => true, 'width' => true)),
			'data' => array_merge($global_attributes, array('value' => true)),
			'div' => $global_attributes,
			'img' => array_merge($global_attributes, array(
				'src' => true,
				'alt' => true,
				'width' => true,
				'height' => true,
			)),
			'input' => array_merge($global_attributes, array(
				'type' => true,
				'name' => true,
				'value' => true,
				'placeholder' => true,
				'required' => true,
			)),
			'meta' => array_merge($global_attributes, array(
				'name' => true,
				'content' => true,
				'charset' => true,
			)),
			'p' => $global_attributes,
			'table' => $global_attributes,
			'video' => array_merge($global_attributes, array(
				'autoplay' => true,
				'controls' => true,
				'loop' => true,
				'muted' => true,
				'preload' => true,
				'src' => true,
				'width' => true,
				'height' => true,
			)),
			'iframe' => array_merge($global_attributes, array(
				'src' => true,
				'width' => true,
				'height' => true,
				'frameborder' => true,
				'scrolling' => true,
				'allowscriptaccess' => true,
				'allowfullscreen' => true,
			)),
		);


		$sanitized_html = wp_kses($html, $allowed_tags);


		$sanitized_html = preg_replace_callback(
			'/style=["\']([^"\']+)["\']/i',
			function ($matches) {
				return 'style="' . $this->sanitize_style_attribute_efb($matches[1]) . '"';
			},
			$sanitized_html
		);

		return $sanitized_html;
	}


	public function get_geolocation() {
		  $ip = $this->get_ip_address();

	  }

	  public function get_ip_address() {
        $ip='1.1.1.1';
		if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {$ip = $_SERVER['REMOTE_ADDR'];}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }



	   public function addon_adds_cron_efb(){


		if ( ! wp_next_scheduled( 'download_all_addons_efb' ) ) {
			wp_schedule_single_event( time() + 1, 'download_all_addons_efb' );
		  }

	   }//addon_adds_cron_efb


	   public function addon_add_efb($value){
				if($value!="AdnOF"){


            $server_name = isset($_SERVER['HTTP_HOST']) ? str_replace("www.", "", $_SERVER['HTTP_HOST']) : '';
            $vwp = get_bloginfo('version');

			$vwp = substr($vwp,0,3);
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
			if(get_locale()=='fa_IR'){
                $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
            }
			$attempts = 2;
			error_log($u);
            for ($i = 0; $i < $attempts; $i++) {
				$request = wp_remote_get($u);
				error_log(print_r($request,true));
				if (!is_wp_error($request)) {
					break;
				}

				if ($i == $attempts - 1) {

					add_action( 'admin_notices', 'admin_notice_msg_efb' );
					return false;
				}
			}

            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body );

			if (isset($data->status)==true && $data->status == false) {
                $response = ['success' => false, "m" => $data->error];
                wp_send_json_success($response, 200);
            }



            if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {
				return false;
            }

            if($data->download==true){
                $url =$data->link;


                $directory_name = substr($url,strrpos($url ,"/")+1,-4);
				$directory = EMSFB_PLUGIN_DIRECTORY . 'vendor/'.$directory_name;
				if (!file_exists($directory)) {
                	$this->fun_addon_new($url);
				}
				return true;
            }

        }
	   }//end function

	   public function fun_addon_new($url){

		$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
		require_once( $path . 'wp-load.php' );
		require_once (ABSPATH .'wp-admin/includes/admin.php');

		$name =substr($url,strrpos($url ,"/")+1,-4);

		$r =download_url($url);
		if(is_wp_error($r)){


		}else{
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			if (WP_Filesystem()) {
				global $wp_filesystem;

				$directory = EMSFB_PLUGIN_DIRECTORY . '/temp';
				if (!$wp_filesystem->exists($directory)) {
					$wp_filesystem->mkdir($directory, 0755);
				}
				$v = $wp_filesystem->move($r, EMSFB_PLUGIN_DIRECTORY . '/temp/temp.zip', true);
			} else {
				// Fallback: If WP_Filesystem fails, use direct PHP functions
				$directory = EMSFB_PLUGIN_DIRECTORY . '/temp';
				if (!file_exists($directory)) {
					@mkdir($directory, 0755, true);
				}
				$v = @rename($r, EMSFB_PLUGIN_DIRECTORY . '/temp/temp.zip');
			}
			if(is_wp_error($v)){
				$s = unzip_file($r, EMSFB_PLUGIN_DIRECTORY . '\\vendor\\');
				if(is_wp_error($s)){



					return false;
				}
			}else{

				require_once(ABSPATH . 'wp-admin/includes/file.php');
				WP_Filesystem();
				$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
				if(is_wp_error($r)){





					return false;
				}
			}
			return true;
		}



		$fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/".$name."/".$name.".php";

		if(file_exists($fl_ex)){
			$name ='\Emsfb\\'.$name;
			require_once  $fl_ex;
			$t = new $name();
		}

	}


	public function download_all_addons_efb(){
		$state=true;
		$ac=$this->get_setting_Emsfb();
		$addons["AdnSPF"]=isset($ac->AdnSPF)?$ac->AdnSPF:0;
		$addons["AdnATC"]=isset($ac->AdnATC)?$ac->AdnATC:0;
		$addons["AdnPPF"]=isset($ac->AdnPPF)?$ac->AdnPPF:0;
		$addons["AdnSS"]=isset($ac->AdnSS)?$ac->AdnSS:0;
		$addons["AdnESZ"]=isset($ac->AdnESZ)?$ac->AdnESZ:0;
		$addons["AdnSE"]=isset($ac->AdnSE)?$ac->AdnSE:0;
		$addons["AdnPDP"]=isset($ac->AdnPDP) ? $ac->AdnPDP : 0;
		$addons["AdnADP"]=isset($ac->AdnADP) ? $ac->AdnADP : 0;
		foreach ($addons as $key => $value) {


			if($value ==1){

				$r =$this->addon_add_efb($key);
				if($r==false){
					 $state = false;
					 break;
				}else{
					$state = true;
				}
			}
		}

		if($state==false){
			$to = isset($ac->emailSupporter) ? $ac->emailSupporter : null;
			if($to==null){$to = get_option('admin_email');}

			if($to==null || $to=="null" || $to=="") return false;
			$sub = esc_html__('Report problem','easy-form-builder') .' ['. esc_html__('Easy Form Builder','easy-form-builder').']';
			$m =  '<div><p>'.esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server','easy-form-builder').
				'</p><p><a href="https://whitestudio.team/support/" target="_blank">'.esc_html__('Please kindly report the following issue to the Easy Form Builder team.','easy-form-builder').
				'</a></p><p>'. esc_html__('Easy Form Builder','easy-form-builder') . '</p>
					<p><a href="'.home_url().'" target="_blank">'.esc_html__("Sent by:",'easy-form-builder'). ' '.get_bloginfo('name').'</a></p></div>';
			if(isset($ac->smtp) && (bool)$ac->smtp ) $this->send_email_state_new($to ,$sub ,$m,0,"addonsDlProblem",'null','null');
			return false;
		}



            return true;

	}


	public function update_message_admin_side_efb(){
		$text = ["wmaddon"];
        $lang= $this->text_efb($text);
		return "<div id='body_efb' class='efb card-public row pb-3 efb'  style='color: #9F6000; background-color: #FEEFB3;  padding: 5px 10px;'> <div class='efb text-center my-5'><h2 style='text-align: center;'></h2><h3 class='efb warning text-center text-darkb fs-4'>".$lang["wmaddon"]."</h3><p class='efb fs-5  text-center my-1 text-pinkEfb' style='text-align: center;'><b>".esc_html__('Easy Form Builder', 'easy-form-builder')."</b><p></div></div>";
	}

	function admin_notice_msg_efb($s) {
		$v = esc_html__('Easy Form Builder','easy-form-builder');
		$t = "notice-success";
		if($s=="dlproblem"){
			$t = "notice-error";
			$v =esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server','easy-form-builder');
		}else if($s=="unzipproblem"){
			$t = "notice-error";
			$v =esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files','easy-form-builder');

		}
		?>
		<div class="notice <?php $t ?> is-dismissible">
			<p><?php $v ?></p>
		</div>
		<?php
	}


	public function efb_sentence_forms(){
		$r =[
			"s_t" => esc_html__('One of the free features','easy-form-builder'),

		];
		return $r;
	}

	public function efb_list_form(){
		$table_name = $this->db->prefix . "emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
			return $value;
	}


	public function efb_code_validate_create($fid, $type, $status, $tc) {
		$table_name = $this->db->prefix . 'emsfb_stts_';
		$ip = $this->get_ip_address();
		$date_now = wp_date('Y-m-d H:i:s');
		$date_limit = wp_date('Y-m-d H:i:s', strtotime('+24 hours'));

		$sid = wp_date("ymdHis") . substr(bin2hex(openssl_random_pseudo_bytes(5)), 0, 9);
		$uid = get_current_user_id() ?? 0;
		$os = $this->getVisitorOS();
		$browser = $this->getVisitorBrowser();

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
			'active' => 1,
			'date' => $date_now,
			'read_date' => $date_limit
		);

		$sql = $this->db->prepare(
			"INSERT INTO {$table_name} (`sid`, `fid`, `type_`, `status`, `ip`, `os`, `browser`, `uid`, `tc`, `active`, `date`, `read_date`)
			VALUES (%s, %d, %d, %s, %s, %s, %s, %d, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE `type_` = VALUES(`type_`), `ip` = VALUES(`ip`), `status` = VALUES(`status`), `uid` = VALUES(`uid`), `active` = VALUES(`active`)",
			$sid, $fid, $type, $status, $ip, $os, $browser, $uid, $tc, 1, $date_now, $date_limit
		);

		$this->db->query($sql);
		return $sid;
	}

    public function efb_code_validate_update($sid ,$status ,$tc ) {

		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = wp_date('Y-m-d H:i:s', strtotime('-24 hours'));
		$active =0;
		$read_date = wp_date('Y-m-d H:i:s');
		if($status=="rsp" || $status=="ppay")  $active =1;


	   $sql = "UPDATE $table_name SET status='{$status}', active={$active}, read_date='{$read_date}', tc='{$tc}' WHERE sid='{$sid}' AND active=1";

		$stmt = $this->db->query($sql);


	   return $stmt > 0;
    }

    public function efb_code_validate_select($sid ,$fid) {

		error_log("efb_code_validate_select called with sid: $sid, fid: $fid");

		// Check cache first
		$cache_key = 'emsfb_validate_' . md5($sid . '_' . $fid);
		$cached_result = wp_cache_get($cache_key, 'emsfb');

		if (false !== $cached_result) {
			return $cached_result;
		}

		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = wp_date('Y-m-d H:i:s', strtotime('-24 hours'));
        $date_now = wp_date('Y-m-d H:i:s');
        $query =$this->db->prepare("SELECT COUNT(*) FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 AND fid = %s", $sid, $date_now,$fid);

        $result =$this->db->get_var($query);
        $is_valid = $result === '1';

        // Cache result for 5 minutes
        wp_cache_set($cache_key, $is_valid, 'emsfb', 300);

        return $is_valid;
    }


	public function getVisitorOS() {
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : null;
		$os = "Unknown";

		if ($ua) {
		        if (strpos($ua, 'windows') !== false) {
		            $os = "Windows";
		        } elseif (strpos($ua, 'linux') !== false) {
		            $os = "Linux";
		        } elseif (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os x') !== false) {
		            $os = "Mac";
		        } elseif (strpos($ua, 'android') !== false) {
		            $os = "Android";
		        } elseif (strpos($ua, 'ios') !== false) {
		            $os = "iOS";
		        }
		    }

		return $os;
	}

	public function getVisitorBrowser() {
	    $ua = isset($_SERVER['HTTP_USER_AGENT'] )? strtolower($_SERVER['HTTP_USER_AGENT']) : null;
	    $b = "Unknown";

	    if ($ua) {
	        if (strpos($ua, 'firefox') !== false) {
	            $b = "Mozilla Firefox";
	        } elseif (strpos($ua, 'chrome') !== false) {
	            if (strpos($ua, 'edg') !== false) {
	                $b = "Microsoft Edge";
	            } elseif (strpos($ua, 'brave') !== false) {
	                $b = "Brave";
	            } else {
	                $b = "Google Chrome";
	            }
	        } elseif (strpos($ua, 'safari') !== false) {
	            $b = "Apple Safari";
	        } elseif (strpos($ua, 'opera') !== false) {
	            $b = "Opera";
	        } elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) {
	            $b = "Internet Explorer";
	        }
	    }

	    return $b;
	}

	public function sms_ready_for_send_efb($form_id , $numbers ,$page_url ,$state ,$severType,$tracking_code = null){
		if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {

			return false;
		}
		require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php");
		$smssendefb = new smssendefb();
		$sms_content = $smssendefb->get_sms_contact_efb($form_id);

		if(empty($sms_content->id)) return false;
		$recived_your_message = $sms_content->recived_message_noti_user;
		$new_message = $sms_content->new_message_noti_user;
		$news_response = $sms_content->new_response_noti;

		if(!empty($sms_content->admin_numbers)){
			$admin_numbers = explode(',',$sms_content->admin_numbers);
			$numbers[0] = array_unique(array_merge($numbers[0],$admin_numbers));
			$numbers[1] = array_unique($numbers[1]);
		}

		$rp = [
			['[confirmation_code]','[link_page]','[link_domain]','[link_response]','[website_name]'],
			[$tracking_code, $page_url, get_site_url(), $page_url."?track=".$tracking_code , get_bloginfo('name')]
		];

		$recived_your_message = str_replace($rp[0],$rp[1],$recived_your_message);
		$new_message = str_replace($rp[0],$rp[1],$new_message);
		$news_response = str_replace($rp[0],$rp[1],$news_response);

		if($state=="fform"){
			if(!empty($numbers[1]) && $new_message){
				$smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
			}
			if(!empty($numbers[0]) && $new_message){
				$new_message = str_replace($page_url."?track=".$tracking_code,$page_url."?track=".$tracking_code.'&user=admin',$new_message);
				$smssendefb->send_sms_efb($numbers[0],$new_message,$form_id,$severType);
			}
			return true;
		}else if($state=="resppa"){
			if(!empty($numbers[1]) && $recived_your_message){
				$smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
			}
			if(!empty($numbers[0]) && $news_response){
				$news_response = str_replace($page_url, $page_url."?track=".$tracking_code.'&user=admin',$news_response);
				$smssendefb->send_sms_efb($numbers[0],$news_response,$form_id,$severType);
			}
			return true;
		}else if ($state=="respp" || $state=="respadmin"){
			if(!empty($numbers[1]) && $news_response){
				$smssendefb->send_sms_efb($numbers[1],$news_response,$form_id,$severType);
			}
			return true;
		}
	}


	public function check_for_active_plugins_cache() {

		$classes = [
		'Aruba HiSpeed Cache'=>'aruba-hispeed-cache/aruba-hispeed-cache.php',
			'Cache Enabler' => 'cache-enabler/cache-enabler.php',
			'Hyper Cache'=>'hyper-cache/plugin.php',
			'NitroPack '=>'nitropack/main.php',
		];



		foreach ( $classes as $plugin => $class ) {
			if ( is_plugin_active( $class ) ) {

				return $plugin;

			}
		}

		return 0;
	}

	public function setting_version_efb_update($st ,$pro){

		$start_time = microtime(true);
		if($st=='null'){
			$st=$this->get_setting_Emsfb();
		}
		$st->efb_version=EMSFB_PLUGIN_VERSION;
		$table_name = $this->db->prefix . "emsfb_setting";
		$st_ = json_encode($st,JSON_UNESCAPED_UNICODE);
        $setting = str_replace('"', '\"', $st_);
		$email = $st->emailSupporter;
		$this->db->insert(
            $table_name,
            [
                'setting' => $setting,
                'edit_by' => get_current_user_id(),
                'date'    => wp_date('Y-m-d H:i:s'),
                'email'   => $email
            ]
        );
		set_transient('emsfb_settings_transient', $setting, 1440);
		update_option('emsfb_settings', $setting);

		// Clear object cache when settings are updated
		wp_cache_delete('emsfb_settings_latest', 'emsfb');

		if($pro == true || $pro ==1){
			$this->download_all_addons_efb();
			$end_time = microtime(true);
			$execution_time = ($end_time - $start_time);

			$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		    if(isset($request_uri)==true && strpos($request_uri, 'Emsfb') == false ){

				wp_safe_redirect($request_uri);
				exit;
			}else{

				?>

				<script>
					location.reload();
				</script>
				<?php
			}

		}



	}


	public function openstreet_map_required_efb($s){

		$url = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js';
		$response = wp_remote_head($url);
		$s =false;
		if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {

			$s= true;
		}
		if($s==false) return false;

		wp_register_style('leaflet_css_efb', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
		wp_enqueue_style('leaflet_css_efb');
		wp_register_script('leaflet_js_efb', $url);
		wp_enqueue_script('leaflet_js_efb');

		if($s==1 || true){
			wp_register_style('leaflet_fullscreen_css_efb', 'https://unpkg.com/leaflet.fullscreen/Control.FullScreen.css');
			wp_enqueue_style('leaflet_fullscreen_css_efb');
			wp_register_script('leaflet_fullscreen_js_efb', 'https://unpkg.com/leaflet.fullscreen/Control.FullScreen.js');
			wp_enqueue_script('leaflet_fullscreen_js_efb');
		}

		return true;

	}


	public function check_and_enqueue_google_captcha_efb($lang) {
        $url = 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload';
        $response = wp_remote_head($url);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            wp_register_script('recaptcha', $url, null , null, true);
            wp_enqueue_script('recaptcha');
			return true;
        } else {


			return false;
        }
    }

	public function report_problem_efb($state ,$value){
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$str = '<!--efb-->';
		$str .= 'State:'.$state . '<br>';
		$str .= 'PHP Version: ' . phpversion() . '<br>';
		$str .= 'WordPress Version: ' . get_bloginfo('version') . '<br>';
		$str .= 'Easy Form Builder Version' . EMSFB_PLUGIN_VERSION . '<br>';
		$str .= 'Website URL: ' . get_site_url() . '<br>';
		$str .= 'Value:'.$value . '<br><hr>';
		foreach ($all_plugins as $plugin_file => $plugin_data) {
			$str.= 'Plugin Name: ' . $plugin_data['Name'] . '<br>';
			$str .= 'Plugin URI: ' . $plugin_data['PluginURI'] . '<br>';
			$str .= 'Version: ' . $plugin_data['Version'] . '<br><br>';
		}
		$this->send_email_state_new('reportProblem' ,'reportProblem' ,$str,0,"reportProblem",'null','null');
	return true;
}

public function validate_url_efb($url) {
		global $allowed_domains;
		$parsed_url = wp_parse_url($url);

		if (isset($parsed_url['host']) && in_array($parsed_url['host'], $allowed_domains)) {
			return esc_url($url);
		}
			if (strpos($url, 'javascript:') === false && strpos($url, 'data:') === false) {
				return esc_url($url);
			}

			return '';
		}
	public function allowed_properties_thml_efb(){
		return array(
			// Colors and background properties
			'color', 'background', 'background-color', 'background-image', 'background-position',
			'background-repeat', 'background-size', 'background-attachment', 'background-clip', 'background-origin',
			'border-image', 'border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat',

			// Font and text properties
			'font', 'font-family', 'font-size', 'font-style', 'font-variant', 'font-weight',
			'letter-spacing', 'line-height', 'text-align', 'text-decoration', 'text-indent',
			'text-overflow', 'text-shadow', 'text-transform', 'white-space', 'word-break', 'word-spacing',
			'direction', 'unicode-bidi', 'writing-mode', 'hyphens',

			// Dimensions and layout properties
			'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
			'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
			'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
			'box-sizing', 'overflow', 'overflow-x', 'overflow-y', 'aspect-ratio',

			// Border properties
			'border', 'border-width', 'border-style', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left',
			'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
			'border-radius', 'outline', 'outline-width', 'outline-style', 'outline-color',
			'border-collapse', 'border-spacing', 'border-image', 'border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat',

			// Box and shadow properties
			'box-shadow', 'box-sizing', 'box-decoration-break',

			// Positioning and z-index
			'position', 'top', 'right', 'bottom', 'left', 'z-index',
			'float', 'clear', 'vertical-align', 'clip',

			// Flexbox and grid properties
			'display', 'flex', 'flex-grow', 'flex-shrink', 'flex-basis',
			'align-items', 'align-content', 'align-self', 'justify-content', 'order',
			'grid', 'grid-template-rows', 'grid-template-columns', 'grid-template-areas',
			'grid-area', 'row-gap', 'column-gap', 'gap', 'place-items', 'place-content', 'place-self',

			// Animation and transition properties
			'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay',
			'animation-iteration-count', 'animation-direction', 'animation-fill-mode', 'animation-play-state',
			'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',

			// Table properties
			'border-collapse', 'border-spacing', 'caption-side', 'empty-cells', 'table-layout','collapse',

			// Miscellaneous
			'cursor', 'opacity', 'clip-path', 'filter', 'backface-visibility', 'visibility',
			'transform', 'transform-origin', 'transform-style', 'perspective', 'perspective-origin',
			'pointer-events', 'resize', 'scroll-behavior', 'user-select', 'will-change',
			'isolation', 'contain', 'mix-blend-mode', 'object-fit', 'object-position', 'overflow-wrap',
			'shape-outside', 'shape-margin', 'shape-image-threshold'
		);
	}

public function sanitize_style_attribute_efb($style) {
			$allowed_properties = $this->allowed_properties_thml_efb();
			$style_rules = explode(';', $style);
			$sanitized_rules = array();

			foreach ($style_rules as $rule) {
				if (strpos($rule, ':') !== false) {
					list($property, $value) = explode(':', $rule, 2);
					$property = trim($property);
					$value = trim($value);


					if ( !is_null($property) && in_array($property, $allowed_properties)) {

						if (strpos($value, 'url(') !== false) {
							preg_match('/url\(["\']?([^"\')]+)["\']?\)/i', $value, $matches);
							if (isset($matches[1]) && $this->validate_url_efb($matches[1])) {
								$sanitized_rules[] = $property . ': ' . $value;
							}
						} else {

							$sanitized_rules[] = $property . ': ' . $value;
						}
					}
				}
			}


			return implode('; ', $sanitized_rules);
		}

			function ensure_trailing_colon_efb(string $s, string $colon = ':'): string
			{
				// هر علامت پایان جمله/پایان عبارت در زبان‌های مختلف (به‌اضافه «:»)
				$punctClass = '[:：\.\!\?\…‥。！？｡．؟\x{06D4}؛;;‽‼⁇⁈⁉⸮።፧။។៕։\x{0964}\x{0965}\x{0589}\x{1362}\x{104B}\x{17D4}\x{17D5}\x{05C3}]';

				// اگر هر کدام از این‌ها هرجای متن باشد، چیزی اضافه نکن
				if (preg_match('/' . $punctClass . '/u', $s)) {
					return $s;
				}

				// کلوزرهای انتهایی (مثل ” ) ] …) و فاصله‌های آخر را جدا کنیم تا کولون قبل از آن‌ها بنشیند
				$closersRe = '(?:\p{Pe}|\p{Pf}|["\'»”’）\)\]】］｝〉》」』〕〗])*';
				if (preg_match('/(?P<closers>' . $closersRe . ')(?P<spaces>[\s\x{00A0}\x{202F}]*)$/u', $s, $m)) {
					$endClosers = $m['closers'];
					$endSpaces  = $m['spaces'];
					// حذف بخش انتهایی برای درج کولون قبل از آن
					$s = preg_replace('/' . $closersRe . '[\s\x{00A0}\x{202F}]*$/u', '', $s);
				} else {
					$endClosers = '';
					$endSpaces  = '';
				}

				// یک فاصله قبل از کولون (سبک فارسی/فرانسوی «نام خانوادگی :»)
				if (!preg_match('/\s$/u', $s)) {
					$s .= ' ';
				}

				return $s . $colon . $endClosers . $endSpaces;
			}

}




