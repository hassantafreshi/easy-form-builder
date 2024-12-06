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
	
		register_activation_hook( __FILE__, [$this ,'download_all_addons_efb'] );
		add_action( 'load-index.php', [$this ,'addon_adds_cron_efb'] );

    }


	public function text_efb($inp){
		$ac= $this->get_setting_Emsfb();		 
		$state= $ac!=='null' && isset($ac->text) && gettype($ac->text)!='string' ? true : false ;
		$s= 'easy-form-builder';		
		$lang = [

			"create" => $state ? $ac->text->create : esc_html__('Create',$s),
			"define" => $state ? $ac->text->define : esc_html__('Define',$s),
			"formName" => $state ? $ac->text->formName : esc_html__('Form Name',$s),
			"createDate" => $state ? $ac->text->createDate : esc_html__('Create Date',$s),
			"edit" => $state ? $ac->text->edit : esc_html__('Edit',$s),
			"content" => $state ? $ac->text->content : esc_html__('Content',$s),
			"trackNo" => $state ? $ac->text->trackNo : esc_html__('Confirmation Code',$s),
			"formDate" => $state ? $ac->text->formDate : esc_html__('Form Date',$s),
			"by" => $state ? $ac->text->by : esc_html__('By',$s),
			"ip" => $state ? $ac->text->ip : esc_html__('IP',$s),
			"guest" => $state ? $ac->text->guest : esc_html__('Guest',$s),			
			"response" => $state ? $ac->text->response : esc_html__('Response',$s),
			"date" => $state ? $ac->text->date : esc_html__('Date Picker',$s),
			"videoDownloadLink" => $state ? $ac->text->videoDownloadLink : esc_html__('Video Download',$s),
			"downloadViedo" => $state ? $ac->text->downloadViedo : esc_html__('Download Video',$s),
			"download" => $state ? $ac->text->download : esc_html__('Download',$s),
			"youCantUseHTMLTagOrBlank" => $state ? $ac->text->youCantUseHTMLTagOrBlank : esc_html__('Please avoid using HTML tags and ensure that your message is not blank.',$s),
			"reply" => $state ? $ac->text->reply : esc_html__('Reply',$s),
			"messages" => $state ? $ac->text->messages : esc_html__('Messages',$s),
			"pleaseWaiting" => $state ? $ac->text->pleaseWaiting : esc_html__('Please Wait',$s),
			"loading" => $state ? $ac->text->loading : esc_html__('Loading',$s),
			"remove" => $state ? $ac->text->remove : esc_html__('Remove!',$s),
			"areYouSureYouWantDeleteItem" => $state ? $ac->text->areYouSureYouWantDeleteItem : esc_html__('Are you sure you want to delete this?',$s),
			"no" => $state ? $ac->text->no : esc_html__('NO',$s),
			"yes" => $state ? $ac->text->yes : esc_html__('Yes',$s),
			//"numberOfSteps" => $state ? $ac->text->numberOfSteps : esc_html__('Number of steps',$s),
			//"titleOfStep" => $state ? $ac->text->titleOfStep : esc_html__('Title of step',$s),
			"proVersion" => $state ? $ac->text->proVersion : esc_html__('Pro Version',$s),
			"getProVersion" => $state ? $ac->text->getProVersion : esc_html__('Activate Pro version',$s),					
			"reCAPTCHA" => $state ? $ac->text->reCAPTCHA : esc_html__('reCAPTCHA',$s),
			//"protectsYourWebsiteFromFraud" => $state ? $ac->text->protectsYourWebsiteFromFraud : esc_html__('Click here to watch a video tutorial.',$s),
			"enterSITEKEY" => $state ? $ac->text->enterSITEKEY : esc_html__('SECRET KEY',$s),
			"alertEmail" => $state ? $ac->text->alertEmail : esc_html__('Alert Email',$s),
			"enterAdminEmail" => $state ? $ac->text->enterAdminEmail : esc_html__('Enter the admin email address to receive email notifications.',$s),
			"showTrackingCode" => $state ? $ac->text->showTrackingCode : esc_html__('Show Confirmation Code',$s),
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : esc_html__('Confirmation Code Finder',$s),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : esc_html__('Copy and paste the following shortcode to add the Confirmation Code finder to any page or post.',$s),
			"save" => $state ? $ac->text->save : esc_html__('Save',$s),
			"waiting" => $state ? $ac->text->waiting : esc_html__('Waiting',$s),
			"saved" => $state ? $ac->text->saved : esc_html__('Saved',$s),
			"stepName" => $state ? $ac->text->stepName : esc_html__('Step Name',$s),
			"IconOfStep" => $state ? $ac->text->IconOfStep : esc_html__('Icon of step',$s),
			"stepTitles" => $state ? $ac->text->stepTitles : esc_html__('Step Titles',$s),
			"elements" => $state ? $ac->text->elements : esc_html__('Elements:',$s),
			"delete" => $state ? $ac->text->delete : esc_html__('Delete',$s),
			"newOption" => $state ? $ac->text->newOption : esc_html__('New option',$s),
			"required" => $state ? $ac->text->required : esc_html__('Required',$s),
			"button" => $state ? $ac->text->button : esc_html__('Text',$s),
			"password" => $state ? $ac->text->password : esc_html__('Password',$s),
			"email" => $state ? $ac->text->email : esc_html__('Email',$s),
			"number" => $state ? $ac->text->number : esc_html__('Number',$s),
			"file" => $state ? $ac->text->file : esc_html__('File Upload',$s),
			"tel" => $state ? $ac->text->tel : esc_html__('Tel',$s),
			"textarea" => $state ? $ac->text->textarea : esc_html__('Long Text',$s),
			"checkbox" => $state ? $ac->text->checkbox : esc_html__('Check Box',$s),
			"radiobutton" => $state ? $ac->text->radiobutton : esc_html__('Radio Button',$s),
			"radio" => $state ? $ac->text->radio : esc_html__('Radio',$s),
			"url" => $state ? $ac->text->url : esc_html__('URL',$s),
			"range" => $state ? $ac->text->range : esc_html__('Range',$s),
			"color" => $state ? $ac->text->color : esc_html__('Color Picker',$s),
			"fileType" => $state ? $ac->text->fileType : esc_html__('File Type',$s),
			"label" => $state ? $ac->text->label : esc_html__('Label',$s),
			"labels" => $state ? $ac->text->labels : esc_html__('Labels',$s),
			"class" => $state ? $ac->text->class : esc_html__('Class',$s),
			"id" => $state ? $ac->text->id : esc_html__('ID',$s),
			"tooltip" => $state ? $ac->text->tooltip : esc_html__('Tooltip',$s),
			"formUpdated" => $state ? $ac->text->formUpdated : esc_html__('The Form Updated',$s),
			"goodJob" => $state ? $ac->text->goodJob : esc_html__('Good Job',$s),
			"formUpdatedDone" => $state ? $ac->text->formUpdatedDone : esc_html__('The form has been successfully updated',$s),
			"formIsBuild" => $state ? $ac->text->formIsBuild : esc_html__('The form is successfully built',$s),
			"formCode" => $state ? $ac->text->formCode : esc_html__('Form Code',$s),
			"close" => $state ? $ac->text->close : esc_html__('Close',$s),
			"done" => $state ? $ac->text->done : esc_html__('Done',$s),
			"demo" => $state ? $ac->text->demo : esc_html__('Demo',$s),			
			"pleaseFillInRequiredFields" => $state ? $ac->text->pleaseFillInRequiredFields : esc_html__('Please fill in all required fields.',$s),
			"availableInProversion" => $state ? $ac->text->availableInProversion : esc_html__('This option is only available in the Pro version.',$s),
			"formNotBuilded" => $state ? $ac->text->formNotBuilded : esc_html__('The form has not been built!',$s),
			"someStepsNotDefinedCheck" => $state ? $ac->text->someStepsNotDefinedCheck : esc_html__('Please check that all steps are defined before proceeding.',$s),
			"ifYouNeedCreateMoreThan2Steps" => $state ? $ac->text->ifYouNeedCreateMoreThan2Steps : esc_html__('If you need to create more than 2 steps, you can activate the pro version of Easy Form Builder, which allows for unlimited steps.',$s),
			"youCouldCreateMinOneAndMaxtwo" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwo : esc_html__('You can create a minimum of 1 step and a maximum of 2 steps.',$s),
			"youCouldCreateMinOneAndMaxtwenty" => $state ? $ac->text->youCouldCreateMinOneAndMaxtwenty : esc_html__('You can create a minimum of 1 step and a maximum of 20 steps.',$s),
			"preview" => $state ? $ac->text->preview : esc_html__('Preview',$s),
			"somethingWentWrongPleaseRefresh" => $state ? $ac->text->somethingWentWrongPleaseRefresh : esc_html__('Something went wrong. Please refresh the page and try again.',$s),
			"formNotCreated" => $state ? $ac->text->formNotCreated : esc_html__('Sorry, it seems like the form has not been created.',$s),
			"atFirstCreateForm" => $state ? $ac->text->atFirstCreateForm : esc_html__('Please create a form and add elements before trying again.',$s),
			"allowMultiselect" => $state ? $ac->text->allowMultiselect : esc_html__('Allow multi-select',$s),
			"DragAndDropUI" => $state ? $ac->text->DragAndDropUI : esc_html__('Drag and drop UI',$s),
			"clickHereForActiveProVesrsion" => $state ? $ac->text->clickHereForActiveProVesrsion : esc_html__('Click here for Active Pro version',$s),
			"selectOpetionDisabled" => $state ? $ac->text->selectOpetionDisabled : esc_html__('Choose an option (not available in test view)',$s),
			"pleaseEnterTheTracking" => $state ? $ac->text->pleaseEnterTheTracking : esc_html__('Please enter the Confirmation Code',$s),									
			"formNotFound" => $state ? $ac->text->formNotFound : esc_html__('Form not found.',$s),
			"errorV01" => $state ? $ac->text->errorV01 : esc_html__('Oops, V01 Error occurred.',$s),
			"password8Chars" => $state ? $ac->text->password8Chars : esc_html__('Password should be at least 8 characters long.',$s),
			"registered" => $state ? $ac->text->registered : esc_html__('Registered',$s),
			"yourInformationRegistered" => $state ? $ac->text->yourInformationRegistered : esc_html__('Your information is successfully registered',$s),
			"youNotPermissionUploadFile" => $state ? $ac->text->youNotPermissionUploadFile : esc_html__('You do not have permission to upload this file:',$s),
			"pleaseUploadA" => $state ? $ac->text->pleaseUploadA : esc_html__('Please upload NN file',$s),
			"please" => $state ? $ac->text->please : esc_html__('Please',$s),
			"trackingForm" => $state ? $ac->text->trackingForm : esc_html__('Tracking Form',$s),
			"trackingCodeIsNotValid" => $state ? $ac->text->trackingCodeIsNotValid : esc_html__('The confirmation Code is not valid.',$s),
			"checkedBoxIANotRobot" => $state ? $ac->text->checkedBoxIANotRobot : esc_html__('Please Checked Box of I am Not robot',$s),
			"howConfigureEFB" => $state ? $ac->text->howConfigureEFB : esc_html__('How to configure Easy Form Builder',$s),
			"howGetGooglereCAPTCHA" => $state ? $ac->text->howGetGooglereCAPTCHA : esc_html__('How to get Google reCAPTCHA and implement it into Easy Form Builder',$s),
			"howActivateAlertEmail" => $state ? $ac->text->howActivateAlertEmail : esc_html__('How to activate the alert email for new form submission',$s),
			"howCreateAddForm" => $state ? $ac->text->howCreateAddForm : esc_html__('How to create and add a form with Easy Form Builder',$s),
			"howActivateTracking" => $state ? $ac->text->howActivateTracking : esc_html__('How to activate a Confirmation Code in Easy Form Builder',$s),
			"howWorkWithPanels" => $state ? $ac->text->howWorkWithPanels : esc_html__('How to work with panels in Easy Form Builder',$s),
			"points" => $state ? $ac->text->points : esc_html__('points',$s),
			"howAddTrackingForm" => $state ? $ac->text->howAddTrackingForm : esc_html__('How to add The Confirmation Code Finder to a post, page, or custom post type',$s),//here
			"howFindResponse" => $state ? $ac->text->howFindResponse : esc_html__('How to find a specific submission using the Confirmation Code',$s),
			"pleaseEnterVaildValue" => $state ? $ac->text->pleaseEnterVaildValue : esc_html__('Please enter a valid value',$s),
			"step" => $state ? $ac->text->step : esc_html__('Step',$s),
			"advancedCustomization" => $state ? $ac->text->advancedCustomization : esc_html__('Advanced customization',$s),
			"orClickHere" => $state ? $ac->text->orClickHere : esc_html__(' or click here',$s),
			"downloadCSVFile" => $state ? $ac->text->downloadCSVFile : esc_html__(' Download CSV file',$s),
			"downloadCSVFileSub" => $state ? $ac->text->downloadCSVFileSub : esc_html__(' Download subscriptions CSV.',$s),
			"login" => $state ? $ac->text->login : esc_html__('Login',$s),
			"thisInputLocked" => $state ? $ac->text->thisInputLocked : esc_html__('this input is locked',$s),
			"thisElemantAvailableRemoveable" => $state ? $ac->text->thisElemantAvailableRemoveable : esc_html__('This element is available and removable.',$s),
			"thisElemantWouldNotRemoveableLoginform" => $state ? $ac->text->thisElemantWouldNotRemoveableLoginform : esc_html__('This element cannot be removed from the Login form.',$s),
			"send" => $state ? $ac->text->send : esc_html__('Send',$s),
			"contactUs" => $state ? $ac->text->contactUs : esc_html__('Contact us',$s),
			"support" => $state ? $ac->text->support : esc_html__('Support',$s),
			"subscribe" => $state ? $ac->text->subscribe : esc_html__('Subscribe',$s),
			"logout" => $state ? $ac->text->logout : esc_html__('Logout',$s),
			"survey" => $state ? $ac->text->survey : esc_html__('Survey',$s),
			"chart" => $state ? $ac->text->chart : esc_html__('Chart',$s),
			"noComment" => $state ? $ac->text->noComment : esc_html__('No comment',$s),
			"easyFormBuilder" => $state ? $ac->text->easyFormBuilder : esc_html__('Easy Form Builder',$s),			
			"byWhiteStudioTeam" => $state ? $ac->text->byWhiteStudioTeam : esc_html__('By WhiteStudio.team',$s),
			"createForms" =>  $state ? $ac->text->createForms :  esc_html__('Create Forms',$s),
			"tutorial" => $state ? $ac->text->tutorial : esc_html__('Tutorial',$s),
			"forms" => $state ? $ac->text->forms : esc_html__('Forms',$s),
			"tobeginSentence" => $state ? $ac->text->tobeginSentence : esc_html__('To get started, simply create a form using the Easy Form Builder Plugin. Click the button below to create a form.',$s),
			"efbIsTheUserSentence" => $state ? $ac->text->efbIsTheUserSentence : esc_html__('Easy Form Builder is an intuitive and user-friendly tool that lets you create custom, multi-step forms in just minutes, without requiring any coding skills.',$s),
			"efbYouDontNeedAnySentence" => $state ? $ac->text->efbYouDontNeedAnySentence : esc_html__('You do not have to be a coding expert to use Easy Form Builder. Simply drag and drop the fields to create customized multistep forms easily. Plus, you can connect each submission to a unique request using the Confirmation Code feature.',$s),
			"newResponse" => $state ? $ac->text->newResponse : esc_html__('New Response',$s),
			"read" => $state ? $ac->text->read : esc_html__('Read',$s),
			"copy" => $state ? $ac->text->copy : esc_html__('Copy',$s),
			"general" => $state ? $ac->text->general : esc_html__('General',$s),
			"dadFieldHere" => $state ? $ac->text->dadFieldHere : esc_html__('Drag & Drop Fields Here',$s),
			"help" => $state ? $ac->text->help : esc_html__('Help',$s),
			"setting" => $state ? $ac->text->setting : esc_html__('Setting',$s),
			"maps" => $state ? $ac->text->maps : esc_html__('Maps',$s),
			"youCanFindTutorial" => $state ? $ac->text->youCanFindTutorial : esc_html__('Find video tutorials in the adjacent box and click the document button for tutorials and articles.',$s),
			"proUnlockMsg" => $state ? $ac->text->proUnlockMsg : esc_html__('Activate Pro version for more features and unlimited access to the all plugin services.',$s),
			"aPIKey" => $state ? $ac->text->aPIKey : esc_html__('API KEY',$s),
			"youNeedAPIgMaps" => $state ? $ac->text->youNeedAPIgMaps : esc_html__('Your form needs an API key for Google Maps to work properly.',$s),
			"copiedClipboard" => $state ? $ac->text->copiedClipboard : esc_html__('Copied to Clipboard',$s),
			"noResponse" => $state ? $ac->text->noResponse : esc_html__('No Response',$s),
			"offerGoogleCloud" => $state ? $ac->text->offerGoogleCloud : esc_html__('To use reCAPTCHA and location picker (Maps), sign up for the Google Cloud service and receive $350 worth of credits exclusively for our users ',$s),
			"getOfferTextlink" => $state ? $ac->text->getOfferTextlink : esc_html__(' Get credits by clicking here.',$s),
			"clickHere" => $state ? $ac->text->clickHere : esc_html__('Click here',$s),
			"SpecialOffer" => $state ? $ac->text->SpecialOffer : esc_html__('Special offer',$s),
			"googleKeys" => $state ? $ac->text->googleKeys : esc_html__('Google Keys',$s),
			"emailServer" => $state ? $ac->text->emailServer : esc_html__('Email server',$s),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : esc_html__('Before using your Email servers, you need to verify the status of e-mail servers and make sure that they are all running.',$s),
			"emailSetting" => $state ? $ac->text->emailSetting : esc_html__('Email Settings',$s),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : esc_html__('Check Email Server',$s),
			"dadfile" => $state ? $ac->text->dadfile : esc_html__('D&D File Upload',$s),
			"field" => $state ? $ac->text->field : esc_html__('Field',$s),
			"advanced" => $state ? $ac->text->advanced : esc_html__('Advanced',$s),
			"switch" => $state ? $ac->text->switch : esc_html__('Switch',$s),
			"locationPicker" => $state ? $ac->text->locationPicker : esc_html__('Location Picker',$s),
			"rating" => $state ? $ac->text->rating : esc_html__('Rating',$s),
			"esign" => $state ? $ac->text->esign : esc_html__('Signature',$s),
			"yesNo" => $state ? $ac->text->yesNo : esc_html__('Yes/No',$s),
			"htmlCode" => $state ? $ac->text->htmlCode : esc_html__('HTML Code',$s),
			"pcPreview" => $state ? $ac->text->pcPreview : esc_html__('Desktop Preview',$s),
			"youDoNotAddAnyInput" => $state ? $ac->text->youDoNotAddAnyInput : esc_html__('You have not added any fields.',$s),
			"copyShortcode" => $state ? $ac->text->copyShortcode : esc_html__('Copy ShortCode',$s),
			"shortcode" => $state ? $ac->text->shortcode : esc_html__('ShortCode',$s),
			"copyTrackingcode" => $state ? $ac->text->copyTrackingcode : esc_html__('Copy Confirmation Code',$s),
			"previewForm" => $state ? $ac->text->previewForm : esc_html__('Preview Form',$s),
			"activateProVersion" => $state ? $ac->text->activateProVersion : esc_html__('Activate Pro Now',$s),
			"itAppearedStepsEmpty" => $state ? $ac->text->itAppearedStepsEmpty : esc_html__('It seems that some of the steps in your form are empty. Please add field to all steps before saving.',$s),
			"youUseProElements" => $state ? $ac->text->youUseProElements : esc_html__('You are using the pro field in the form. For save and using the form included pro fields, activate Pro version.',$s),
			"sampleDescription" => $state ? $ac->text->sampleDescription : esc_html__('Sample description',$s),
			"fieldAvailableInProversion" => $state ? $ac->text->fieldAvailableInProversion : esc_html__('This feature is only available in the Pro of Easy Form Builder.',$s),
			"editField" => $state ? $ac->text->editField : esc_html__('Edit Field',$s),
			"description" => $state ? $ac->text->description : esc_html__('Description',$s),
			"descriptions" => $state ? $ac->text->descriptions : esc_html__('Descriptions',$s),
			"thisEmailNotificationReceive" => $state ? $ac->text->thisEmailNotificationReceive : esc_html__('Enable email notifications',$s),
			"activeTrackingCode" => $state ? $ac->text->activeTrackingCode : esc_html__('Show Confirmation Code',$s),
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : esc_html__('Add Google reCAPTCHA to the form ',$s),
			"dontShowIconsStepsName" => $state ? $ac->text->dontShowIconsStepsName : esc_html__('Hide icons and step names.',$s),
			"dontShowProgressBar" => $state ? $ac->text->dontShowProgressBar : esc_html__('Hide progress bar',$s),
			"showTheFormTologgedUsers" => $state ? $ac->text->showTheFormTologgedUsers : esc_html__('Private form',$s),
			"labelSize" => $state ? $ac->text->labelSize : esc_html__('Label size',$s),
			"default" => $state ? $ac->text->default : esc_html__('Default',$s),
			"small" => $state ? $ac->text->small : esc_html__('Small',$s),
			"large" => $state ? $ac->text->large : esc_html__('Large',$s),
			"xlarge" => $state ? $ac->text->xlarge : esc_html__('XLarge',$s),
			"xxlarge" => $state ? $ac->text->xxlarge : esc_html__('XXLarge',$s),
			"xxxlarge" => $state ? $ac->text->xxxlarge : esc_html__('XXXLarge',$s),
			"labelPostion" => $state ? $ac->text->labelPostion : esc_html__('Label Position',$s),
			"align" => $state ? $ac->text->align : esc_html__('Align',$s),
			"left" => $state ? $ac->text->left : esc_html__('Left',$s),
			"center" => $state ? $ac->text->center : esc_html__('Center',$s),
			"right" => $state ? $ac->text->right : esc_html__('Right',$s),
			"width" => $state ? $ac->text->width : esc_html__('Width',$s),
			"cSSClasses" => $state ? $ac->text->cSSClasses : esc_html__('CSS Classes',$s),
			"defaultValue" => $state ? $ac->text->defaultValue : esc_html__('Default value',$s),
			"placeholder" => $state ? $ac->text->placeholder : esc_html__('Placeholder',$s),
			"enterAdminEmailReceiveNoti" => $state ? $ac->text->enterAdminEmailReceiveNoti : esc_html__('Enter email address to receive notifications.',$s),
			"corners" => $state ? $ac->text->corners : esc_html__('Corners',$s),
			"rounded" => $state ? $ac->text->rounded : esc_html__('Rounded',$s),
			"square" => $state ? $ac->text->square : esc_html__('Square',$s),
			"icon" => $state ? $ac->text->icon : esc_html__('Icon',$s),
			"icons" => $state ? $ac->text->icon : esc_html__('Icons',$s),
			"buttonColor" => $state ? $ac->text->buttonColor : esc_html__('Button color',$s),
			"buttonColors" => $state ? $ac->text->buttonColors : esc_html__('Buttons colors',$s),
			"blue" => $state ? $ac->text->blue : esc_html__('Blue',$s),
			"darkBlue" => $state ? $ac->text->darkBlue : esc_html__('Dark Blue',$s),
			"lightBlue" => $state ? $ac->text->lightBlue : esc_html__('Light Blue',$s),
			"grayLight" => $state ? $ac->text->grayLight : esc_html__('Gray Light',$s),
			"grayLighter" => $state ? $ac->text->grayLighter : esc_html__('Gray Lighter',$s),
			"green" => $state ? $ac->text->green : esc_html__('Green',$s),
			"pink" => $state ? $ac->text->pink : esc_html__('Pink',$s),
			"yellow" => $state ? $ac->text->yellow : esc_html__('Yellow',$s),
			"light" => $state ? $ac->text->light : esc_html__('Light',$s),
			"Red" => $state ? $ac->text->Red : esc_html__('red',$s),
			"grayDark" => $state ? $ac->text->grayDark : esc_html__('Gray Dark',$s),
			"white" => $state ? $ac->text->white : esc_html__('White',$s),
			"clr" => $state ? $ac->text->clr : esc_html__('Color',$s),
			"borderColor" => $state ? $ac->text->borderColor : esc_html__('Border Color',$s),
			"height" => $state ? $ac->text->height : esc_html__('Height',$s),
			"name" => $state ? $ac->text->name : esc_html__('Name',$s),
			"latitude" => $state ? $ac->text->latitude : esc_html__('Latitude',$s),
			"longitude" => $state ? $ac->text->longitude : esc_html__('Longitude',$s),
			"exDot" => $state ? $ac->text->exDot : esc_html__('e.g.',$s),
			"pleaseDoNotAddJsCode" => $state ? $ac->text->pleaseDoNotAddJsCode : esc_html__('(Avoid adding JavaScript or jQuery codes to HTML for security reasons.)',$s),
			"button1Value" => $state ? $ac->text->button1Value : esc_html__('Button 1 value',$s),
			"button2Value" => $state ? $ac->text->button2Value : esc_html__('Button 2 value',$s),
			"iconList" => $state ? $ac->text->iconList : esc_html__('Icons list',$s),
			"previous" => $state ? $ac->text->previous : esc_html__('Previous',$s),
			"next" => $state ? $ac->text->next : esc_html__('Next',$s),
			"noCodeAddedYet" => $state ? $ac->text->noCodeAddedYet : esc_html__('The code has not yet been added. Click on',$s),
			"andAddingHtmlCode" => $state ? $ac->text->andAddingHtmlCode : esc_html__('and adding HTML code.',$s),
			//"proMoreStep" => $state ? $ac->text->proMoreStep : esc_html__('When you activate the Pro version, so you can create unlimited form steps.',$s),
			"aPIkeyGoogleMapsError" => $state ? $ac->text->aPIkeyGoogleMapsError : esc_html__('The API key for Google Maps has not been added. Please go to Easy Form Builder > Panel > Setting > Google Keys, add the API key for Google Maps, and try again.',$s),
			"howToAddGoogleMap" => $state ? $ac->text->howToAddGoogleMap : esc_html__('How to Add Location Picker(maps) to Easy form Builder WordPress Plugin',$s),
			"deletemarkers" => $state ? $ac->text->deletemarkers : esc_html__('Delete markers',$s),
			"updateUrbrowser" => $state ? $ac->text->updateUrbrowser : esc_html__('update your browser',$s),
			"stars" => $state ? $ac->text->stars : esc_html__('Stars',$s),
			"nothingSelected" => $state ? $ac->text->nothingSelected : esc_html__('Nothing selected',$s),
			"duplicate" => $state ? $ac->text->duplicate : esc_html__('Duplicate',$s),
			"availableProVersion" => $state ? $ac->text->availableProVersion : esc_html__('Available in the Pro version',$s),
			"mobilePreview" => $state ? $ac->text->mobilePreview : esc_html__('Mobile Preview',$s),
			"thanksFillingOutform" => $state ? $ac->text->thanksFillingOutform : esc_html__('Thanks for filling out the form.',$s),
			"finish" => $state ? $ac->text->finish : esc_html__('Finish',$s),
			"dragAndDropA" => $state ? $ac->text->dragAndDropA : esc_html__('Drag & Drop the',$s),
			"browseFile" => $state ? $ac->text->browseFile : esc_html__('Browse File',$s),
			"removeTheFile" => $state ? $ac->text->removeTheFile : esc_html__('Remove the file',$s),
			"enterAPIKey" => $state ? $ac->text->enterAPIKey : esc_html__('Enter API KEY',$s),
			"formSetting" => $state ? $ac->text->formSetting : esc_html__('Form Settings',$s),
			"select" => $state ? $ac->text->select : esc_html__('Select',$s),
			"up" => $state ? $ac->text->up : esc_html__('Up',$s),
			"sending" => $state ? $ac->text->sending : esc_html__('Sending',$s),
			"enterYourMessage" => $state ? $ac->text->enterYourMessage : esc_html__('Please Enter your message',$s),
			"add" => $state ? $ac->text->add : esc_html__('Add',$s),
			"code" => $state ? $ac->text->code : esc_html__('Code',$s),
			"star" => $state ? $ac->text->star : esc_html__('Star',$s),
			"form" => $state ? $ac->text->form : esc_html__('Form',$s),
			"black" => $state ? $ac->text->black : esc_html__('Black',$s),
			"pleaseReporProblem" => $state ? $ac->text->pleaseReporProblem : esc_html__('Please kindly report the following issue to the Easy Form Builder team.',$s),
			"reportProblem" => $state ? $ac->text->reportProblem : esc_html__('Report problem',$s),
			"ddate" => $state ? $ac->text->ddate : esc_html__('Date',$s),
			"serverEmailAble" => $state ? $ac->text->serverEmailAble : esc_html__('Your server is capable of sending emails',$s),
			"sMTPNotWork" => $state ? $ac->text->sMTPNotWork : esc_html__('SMTP Error: The host is unable to send an email. Please contact the host is support team for assistance.',$s),
			
			"aPIkeyGoogleMapsFeild" => $state ? $ac->text->aPIkeyGoogleMapsFeild : esc_html__('There was an error loading Maps.',$s),
			"fileIsNotRight" => $state ? $ac->text->fileIsNotRight : esc_html__('The uploaded file is not in the correct file format.',$s),
			"thisElemantNotAvailable" => $state ? $ac->text->thisElemantNotAvailable : esc_html__('The selected field is not available in this type of form.',$s),
			"numberSteps" => $state ? $ac->text->numberSteps : esc_html__('Edit',$s),
			"clickHereGetActivateCode" => $state ? $ac->text->clickHereGetActivateCode : esc_html__('Get your activation code now and unlock exclusive features ! Click here.',$s),			
			"trackingCode" => $state ? $ac->text->trackingCode : esc_html__('Confirmation Code',$s),
			"text" => $state ? $ac->text->text : esc_html__('Text',$s),
			"multiselect" => $state ? $ac->text->multiselect : esc_html__('Multiple Select',$s),
			"newForm" => $state ? $ac->text->newForm : esc_html__('New Form',$s),
			"registerForm" => $state ? $ac->text->registerForm : esc_html__('Register Form',$s),
			"loginForm" => $state ? $ac->text->loginForm : esc_html__('Login Form',$s),
			"subscriptionForm" => $state ? $ac->text->subscriptionForm : esc_html__('Subscription Form',$s),
			"supportForm" => $state ? $ac->text->supportForm : esc_html__('Support Form',$s),
			"createBlankMultistepsForm" => $state ? $ac->text->createBlankMultistepsForm : esc_html__('Create a blank multisteps form.',$s),
			"createContactusForm" => $state ? $ac->text->createContactusForm : esc_html__('Create a Contact us form.',$s),
			"createRegistrationForm" => $state ? $ac->text->createRegistrationForm : esc_html__('Create a form to register new users to your WordPress site\'s user list.',$s),
			"createLoginForm" => $state ? $ac->text->createLoginForm : esc_html__('Create a login form for users to enter your WordPress site.',$s),
			"createnewsletterForm" => $state ? $ac->text->createnewsletterForm : esc_html__('Create a newsletter form',$s),
			"createSupportForm" => $state ? $ac->text->createSupportForm : esc_html__('Create a support contact form.',$s),			
			"availableSoon" => $state ? $ac->text->availableSoon : esc_html__('Available Soon',$s),
			"reservation" => $state ? $ac->text->reservation : esc_html__('Reservation ',$s),
			"createsurveyForm" => $state ? $ac->text->createsurveyForm : esc_html__('Create survey or poll or questionnaire forms ',$s),
			"createReservationyForm" => $state ? $ac->text->createReservationyForm : esc_html__('Create reservation or booking forms ',$s),
			"firstName" => $state ? $ac->text->firstName : esc_html__('First name',$s),
			"lastName" => $state ? $ac->text->lastName : esc_html__('Last name',$s),
			"message" => $state ? $ac->text->message : esc_html__('Message',$s),
			"subject" => $state ? $ac->text->subject : esc_html__('Subject',$s),
			"phone" => $state ? $ac->text->phone : esc_html__('Phone',$s),
			"register" => $state ? $ac->text->register : esc_html__('Register',$s),
			"username" => $state ? $ac->text->username : esc_html__('Username',$s),
			"allStep" => $state ? $ac->text->allStep : esc_html__('all step',$s),
			"beside" => $state ? $ac->text->beside : esc_html__('Beside',$s),
			"invalidEmail" => $state ? $ac->text->invalidEmail : esc_html__('Invalid Email address',$s),
			"clearUnnecessaryFiles" => $state ? $ac->text->clearUnnecessaryFiles : esc_html__('Delete unnecessary files.',$s),
			"youCanRemoveUnnecessaryFileUploaded" => $state ? $ac->text->youCanRemoveUnnecessaryFileUploaded : esc_html__('You can delete unnecessary files uploaded by users using the button below.',$s),			
			"whenEasyFormBuilderRecivesNewMessage" => $state ? $ac->text->whenEasyFormBuilderRecivesNewMessage : esc_html__('When a new message is received through Easy Form Builder, an alert email will be sent to the plugin admin.',$s),
			"reCAPTCHAv2" => $state ? $ac->text->reCAPTCHAv2 : esc_html__('reCAPTCHA v2',$s),					
			"clickHereWatchVideoTutorial" => $state ? $ac->text->clickHereWatchVideoTutorial : esc_html__('Click here to watch a video tutorial.',$s),
			"siteKey" => $state ? $ac->text->siteKey : esc_html__('SITE KEY',$s),			
			"SecreTKey" => $state ? $ac->text->SecreTKey : esc_html__('SECRET KEY',$s),
			"EnterSECRETKEY" => $state ? $ac->text->EnterSECRETKEY : esc_html__('Enter a Secret Key',$s),
			"clearFiles" => $state ? $ac->text->clearFiles : esc_html__('Clear Files',$s),			
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : esc_html__('Enter the activate code',$s),			
			"error" => $state ? $ac->text->error : esc_html__('Error',$s),
			"somethingWentWrongTryAgain" => $state ? $ac->text->somethingWentWrongTryAgain : esc_html__('Something unexpected happened. Please try again by refreshing the page.',$s),										
			"enterThePhone" => $state ? $ac->text->enterThePhone : esc_html__('Please enter a valid phone number.',$s),
			"pleaseMakeSureAllFields" => $state ? $ac->text->pleaseMakeSureAllFields : esc_html__('Please ensure that all fields are filled correctly.',$s),
			"enterTheEmail" => $state ? $ac->text->enterTheEmail : esc_html__('Please enter an email address.',$s),			
			"fileSizeIsTooLarge" => $state ? $ac->text->fileSizeIsTooLarge : esc_html__('The file size exceeds the maximum allowed limit of NN MB',$s),
			"documents" => $state ? $ac->text->documents : esc_html__('Documents',$s),
			"document" => $state ? $ac->text->document : esc_html__('Document',$s),
			"image" => $state ? $ac->text->image : esc_html__('Image',$s),
			"media" => $state ? $ac->text->media : esc_html__('Media',$s),
			"zip" => $state ? $ac->text->zip : esc_html__('Zip',$s),				
			"alert" => $state ? $ac->text->alert : esc_html__('Alert!',$s),			
			"pleaseWatchTutorial" => $state ? $ac->text->pleaseWatchTutorial : esc_html__('We recommend watching this tutorial for assistance.',$s),
			"formIsNotShown" => $state ? $ac->text->formIsNotShown : esc_html__('The form is not shown because Google reCAPTCHA has not been added to the Easy Form Builder plugin settings.',$s),
			"errorVerifyingRecaptcha" => $state ? $ac->text->errorVerifyingRecaptcha : esc_html__('Please try again, Captcha Verification Failed.',$s),			
			"enterThePassword" => $state ? $ac->text->enterThePassword : esc_html__('Password must be at least 8 characters long and include a number and an uppercase letter.',$s),
			"PleaseFillForm" => $state ? $ac->text->PleaseFillForm : esc_html__('Please complete the form.',$s),
			"selectOption" => $state ? $ac->text->selectOption : esc_html__('Choose options',$s),
			"selected" => $state ? $ac->text->selected : esc_html__('Selected',$s),
			"selectedAllOption" => $state ? $ac->text->selectedAllOption : esc_html__('Select All',$s),
			"sentSuccessfully" => $state ? $ac->text->sentSuccessfully : esc_html__('Sent successfully',$s),
			"sync" => $state ? $ac->text->sync : esc_html__('Sync',$s),
			"enterTheValueThisField" => $state ? $ac->text->enterTheValueThisField : esc_html__('This field is required.',$s),
			"thankYou" => $state ? $ac->text->thankYou : esc_html__('Thank you',$s),
			"YouSubscribed" => $state ? $ac->text->YouSubscribed : esc_html__('You are subscribed',$s),
			"passwordRecovery" => $state ? $ac->text->passwordRecovery : esc_html__('Password recovery',$s),
			"info" => $state ? $ac->text->info : esc_html__('information',$s),						
			"waitingLoadingRecaptcha" => $state ? $ac->text->waitingLoadingRecaptcha : esc_html__('Wait for loading reCaptcha',$s),
			"on" => $state ? $ac->text->on : esc_html__('On',$s),
			"off" => $state ? $ac->text->off : esc_html__('Off',$s),
			"settingsNfound" => $state ? $ac->text->settingsNfound : esc_html__('Settings not found',$s),
			"red" => $state ? $ac->text->red : esc_html__('Red',$s),
			"reCAPTCHASetError" => $state ? $ac->text->reCAPTCHASetError : esc_html__('Please navigate to the Easy Form Builder Panel, then go to Settings and click on Google Keys to configure the keys for Google reCAPTCHA.',$s),
			"ifShowTrackingCodeToUser" => $state ? $ac->text->ifShowTrackingCodeToUser : esc_html__("To hide the Confirmation Code from users, leave the option unmarked.",$s),
			"videoOrAudio" => $state ? $ac->text->videoOrAudio : esc_html__('(Video or Audio)',$s),			
			"localization" => $state ? $ac->text->localization : esc_html__('Localization',$s),
			"translateLocal" => $state ? $ac->text->translateLocal : esc_html__('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.',$s),
			"enterValidURL" => $state ? $ac->text->enterValidURL : esc_html__('Please enter a valid URL. Protocol is required (http://, https://)',$s),
			"emailOrUsername" => $state ? $ac->text->emailOrUsername : esc_html__('Email or Username',$s),
			"contactusForm" => $state ? $ac->text->contactusForm : esc_html__('Contact-us Form',$s),
			"clear" => $state ? $ac->text->clear : esc_html__('Clear',$s),
			"entrTrkngNo" => $state ? $ac->text->entrTrkngNo : esc_html__('Enter the Confirmation Code',$s),
			"search" => $state ? $ac->text->search : esc_html__('Search',$s),
			"enterThePhones" => $state ? $ac->text->enterThePhones : esc_html__('Enter The Phone No',$s),
			"conturyList" => $state ? $ac->text->conturyList : esc_html__('Countries Drop-down',$s),
			"stateProvince" => $state ? $ac->text->stateProvince : esc_html__('State/Prov Drop-down',$s),
			"thankYouMessage" => $state ? $ac->text->thankYouMessage : esc_html__('Thank you message',$s),
			"newMessage" => $state ? $ac->text->newMessage : esc_html__('New message!', $s),
			"newMessageReceived" => $state ? $ac->text->newMessageReceived : esc_html__('A New Message has been Received.', $s),
			"createdBy" => $state ? $ac->text->createdBy : esc_html__('Created by',$s),
			"hiUser" => $state ? $ac->text->hiUser : esc_html__('Hi Dear User', $s),
			"sentBy" => $state ? $ac->text->sentBy : esc_html__("Sent by:",$s),
			"youRecivedNewMessage" => $state ? $ac->text->youRecivedNewMessage : esc_html__('You have a new message.', $s),
			"formNExist" => $state ? $ac->text->formNExist : esc_html__('Form does not exist !!',$s),
			"error403" => $state ? $ac->text->error403 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E403',$s),
			"error400" => $state ? $ac->text->error400 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E400',$s),
			"formPrivateM" => $state ? $ac->text->formPrivateM : esc_html__('Private form, please log in.',$s),
			"errorSiteKeyM" => $state ? $ac->text->errorSiteKeyM : esc_html__('Please check the site key and secret key on Easy Form Builder panel > Settings > Google Keys to resolve the error.',$s),
			"errorCaptcha" => $state ? $ac->text->errorCaptcha : esc_html__('There seems to be a problem with the Captcha. Please try again.',$s),
			"createAcountDoneM" => $state ? $ac->text->createAcountDoneM : esc_html__('Your account has been successfully created! You will receive an email containing your information',$s),
			"incorrectUP" => $state ? $ac->text->incorrectUP : esc_html__('This username or password combination is incorrect.',$s),
			"newPassM" => $state ? $ac->text->newPassM : esc_html__('If your email is valid, a new password will send to your email.',$s),
			"surveyComplatedM" => $state ? $ac->text->surveyComplatedM : esc_html__('The survey has been successfully completed.',$s),
			"error405" => $state ? $ac->text->error405 : esc_html__('We are sorry, but there seems to be a security error (405) with your request.',$s),
			"errorSettingNFound" => $state ? $ac->text->errorSettingNFound : esc_html__('Error, Setting not Found',$s),
			"errorMRobot" => $state ? $ac->text->errorMRobot : esc_html__('Sorry, there seems to be an error. Please verify that you are human and try again.',$s),
			"enterVValue" => $state ? $ac->text->enterVValue : esc_html__('Please enter valid values',$s),
			"cCodeNFound" => $state ? $ac->text->cCodeNFound : esc_html__('Invalid Confirmation Code.',$s),
			"errorFilePer" => $state ? $ac->text->errorFilePer : esc_html__('There seems to be an error with the file permissions.',$s),
			"errorSomthingWrong" => $state ? $ac->text->errorSomthingWrong : esc_html__('Oops! Something went wrong. Please try refreshing the page and try again.',$s),
			"nAllowedUseHtml" => $state ? $ac->text->nAllowedUseHtml : esc_html__('HTML tags are not allowed.',$s),
			"messageSent" => $state ? $ac->text->messageSent : esc_html__('Your message has been sent.',$s),
			"WeRecivedUrM" => $state ? $ac->text->WeRecivedUrM : esc_html__('We have received your message.',$s),
			"thankFillForm" => $state ? $ac->text->thankFillForm : esc_html__('The form has been submitted successfully',$s),
			"thankRegistering" => $state ? $ac->text->thankRegistering : esc_html__('Your registration is successful.',$s),
			"welcome" => $state ? $ac->text->welcome : esc_html__('Welcome',$s),
			"thankSubscribing" => $state ? $ac->text->thankSubscribing : esc_html__('You have successfully subscribed. Thank you!',$s),
			"thankDonePoll" => $state ? $ac->text->thankDonePoll : esc_html__('Thank You for taking the time to complete this survey.',$s),
			"goToEFBAddEmailM" => $state ? $ac->text->goToEFBAddEmailM : esc_html__('Please navigate to the Easy Form Builder panel, then select < Setting >, followed by < Email Settings >. Next, click on the button that reads < Click To Check Email Server >, and then click < Save >.',$s),
			"errorCheckInputs" => $state ? $ac->text->errorCheckInputs : esc_html__('Uh oh, looks like there is a problem with the form. Please make sure all of the input is correct.',$s),
			"formNcreated" => $state ? $ac->text->formNcreated : esc_html__('The form was not created',$s),
			"NAllowedscriptTag" => $state ? $ac->text->NAllowedscriptTag : esc_html__('Scripts tags are not allowed.',$s),
			"bootStrapTemp" => $state ? $ac->text->bootStrapTemp : esc_html__('Bootstrap Template',$s),
			"iUsebootTempW" => $state ? $ac->text->iUsebootTempW : esc_html__('Warning: If your template uses Bootstrap, please ensure that the option below is checked.',$s),
			"iUsebootTemp" => $state ? $ac->text->iUsebootTemp : esc_html__('My template is based on Bootstrap',$s),
			"invalidRequire" => $state ? $ac->text->invalidRequire : esc_html__('Uh oh, it looks like there is a problem with your request. Please review everything and try again.',$s),
			"updated" => $state ? $ac->text->updated : esc_html__('updated',$s),
			"PEnterMessage" => $state ? $ac->text->PEnterMessage : esc_html__('Please type in your message',$s),
			"fileDeleted" => $state ? $ac->text->fileDeleted : esc_html__('The files have been deleted.',$s),
			"activationNcorrect" => $state ? $ac->text->activationNcorrect : esc_html__('The activation code you entered is incorrect. Please double-check and try again.',$s),
			"localizationM" => $state ? $ac->text->localizationM : esc_html__('To localize the plugin, simply go to the Panel, click on Setting, and then Localization.',$s),
			"MMessageNSendEr" => $state ? $ac->text->MMessageNSendEr : esc_html__('We are sorry, but the message was not sent due to a settings error. Please contact the admin for assistance.',$s),
			"warningBootStrap" => $state && isset($ac->text->warningBootStrap) ? $ac->text->warningBootStrap : esc_html__('To ensure compatibility, please go to the Panel and select the < Setting > option. From there, choose the option that states < My template has used Bootstrap framework > and click < Save >. If you encounter any additional issues, please don not hesitate to contact us through our website at whitestudio.team.',$s),
			"or" => $state  && isset($ac->text->or)? $ac->text->or : esc_html__('OR',$s),
			"emailTemplate" => $state  &&  isset($ac->text->emailTemplate) ? $ac->text->emailTemplate : esc_html__('Email Template',$s),
			"reset" => $state  &&  isset($ac->text->reset) ? $ac->text->reset : esc_html__('reset',$s),
			"freefeatureNotiEmail" => $state  &&  isset($ac->text->freefeatureNotiEmail) ? $ac->text->freefeatureNotiEmail : esc_html__('One of the free features of Easy Form Builder is the ability to send a notification email to either the admin or user.',$s),
			"notFound" => $state  &&  isset($ac->text->notFound) ? $ac->text->notFound : esc_html__('Not Found',$s),
			"editor" => $state  &&  isset($ac->text->editor) ? $ac->text->editor : esc_html__('Editor',$s),
			"addSCEmailM" => $state  &&  isset($ac->text->addSCEmailM) ? $ac->text->addSCEmailM : esc_html__('Please add these shortcodes shortcode_message and shortcode_title to the email template.',$s),
			"ChrlimitEmail" => $state  &&  isset($ac->text->ChrlimitEmail) ? $ac->text->ChrlimitEmail : esc_html__('Your Email Template cannot exceed 10,000 characters.',$s),
			"pleaseEnterVaildEtemp" => $state  &&  isset($ac->text->pleaseEnterVaildEtemp) ? $ac->text->pleaseEnterVaildEtemp : esc_html__('Please use HTML tags to create your email template.',$s),
			"infoEmailTemplates" => $state  &&  isset($ac->text->infoEmailTemplates) ? $ac->text->infoEmailTemplates : esc_html__('To create an email template using HTML2, use the following shortcodes. Please note that the shortcodes marked with an asterisk (*) should be included in the email template.',$s),
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : esc_html__('Add this shortcode inside a tag to display the title of the email.',$s),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : esc_html__('Add this shortcode inside an HTML tag to display the message content of an email.',$s),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : esc_html__('To display the website name, add this shortcode inside a HTML tag.',$s),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : esc_html__('Add this shortcode within a HTML tag to display the Website URL.',$s),
			"shortcodeAdminEmailInfo" => $state  &&  isset($ac->text->shortcodeAdminEmailInfo) ? $ac->text->shortcodeAdminEmailInfo : esc_html__('You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.',$s),
			"noticeEmailContent" => $state  &&  isset($ac->text->noticeEmailContent) ? $ac->text->noticeEmailContent : esc_html__('Please note that if the Editor field is left blank, the default Email Template will be used.',$s),
			"templates" => $state  &&  isset($ac->text->templates) ? $ac->text->templates : esc_html__('Templates',$s),
			"maxSelect" => $state  &&  isset($ac->text->maxSelect) ? $ac->text->maxSelect : esc_html__('Max selection',$s),
			"minSelect" => $state  &&  isset($ac->text->minSelect) ? $ac->text->minSelect : esc_html__('Min selection',$s),
			"dNotShowBg" => $state  &&  isset($ac->text->dNotShowBg) ? $ac->text->dNotShowBg : esc_html__('Do not show the background.',$s),
			"contactusTemplate" => $state  &&  isset($ac->text->contactusTemplate) ? $ac->text->contactusTemplate : esc_html__('Contact us Template',$s),
			"curved" => $state  &&  isset($ac->text->curved) ? $ac->text->curved : esc_html__('Curved',$s),
			"multiStep" => $state  &&  isset($ac->text->multiStep) ? $ac->text->multiStep : esc_html__('Multi-Step',$s),
			"customerFeedback" => $state  &&  isset($ac->text->customerFeedback) ? $ac->text->customerFeedback : esc_html__('Customer Feedback',$s),
			"supportTicketF" => $state  &&  isset($ac->text->supportTicketF) ? $ac->text->supportTicketF : esc_html__('Support Ticket Form',$s),
			"paymentform" => $state  &&  isset($ac->text->paymentform) ? $ac->text->paymentform : esc_html__('Payment Form',$s),
			"stripe" => $state  &&  isset($ac->text->stripe) ? $ac->text->stripe : esc_html__('Stripe',$s),
			"payment" => $state  &&  isset($ac->text->payment ) ? $ac->text->payment  : esc_html__('Payment',$s),
			"address" => $state  &&  isset($ac->text->address ) ? $ac->text->address  : esc_html__('Address',$s),
			"paymentGateway" => $state  &&  isset($ac->text->paymentGateway) ? $ac->text->paymentGateway : esc_html__('Payment Gateway',$s),			
			"currency" => $state  &&  isset($ac->text->currency) ? $ac->text->currency : esc_html__('Currency',$s),
			"recurringPayment" => $state  &&  isset($ac->text->recurringPayment) ? $ac->text->recurringPayment : esc_html__('Recurring payment',$s),
			"subscriptionBilling" => $state  &&  isset($ac->text->subscriptionBilling) ? $ac->text->subscriptionBilling : esc_html__('Subscription billing',$s),
			"onetime" => $state  &&  isset($ac->text->onetime) ? $ac->text->onetime : esc_html__('one time',$s),
			"methodPayment" => $state  &&  isset($ac->text->methodPayment) ? $ac->text->methodPayment : esc_html__('Method payment',$s),
			"heading" => $state  &&  isset($ac->text->heading) ? $ac->text->heading : esc_html__('Heading',$s),
			"link" => $state  &&  isset($ac->text->link) ? $ac->text->link : esc_html__('Link',$s),
			"mobile" => $state  &&  isset($ac->text->mobile) ? $ac->text->mobile : esc_html__('Mobile',$s),
			"product" => $state  &&  isset($ac->text->product) ? $ac->text->product : esc_html__('product',$s),
			"value" => $state  &&  isset($ac->text->value) ? $ac->text->value : esc_html__('value',$s),
			"terms" => $state  &&  isset($ac->text->terms) ? $ac->text->terms : esc_html__('terms',$s),
			"pricingTable" => $state  &&  isset($ac->text->pricingTable) ? $ac->text->pricingTable : esc_html__('Pricing Table',$s),
			"cardNumber" => $state  &&  isset($ac->text->cardNumber) ? $ac->text->cardNumber : esc_html__('Card Number',$s),
			"cardExpiry" => $state  &&  isset($ac->text->cardExpiry) ? $ac->text->cardExpiry : esc_html__('Card Expiry',$s),
			"cardCVC" => $state  &&  isset($ac->text->cardCVC) ? $ac->text->cardCVC : esc_html__('Card CVC',$s),
			"payNow" => $state  &&  isset($ac->text->payNow) ? $ac->text->payNow : esc_html__('Pay Now',$s),			
			"payAmount" => $state  &&  isset($ac->text->payAmount) ? $ac->text->payAmount : esc_html__('Pay amount',$s),
			"successPayment" => $state  &&  isset($ac->text->successPayment) ? $ac->text->successPayment : esc_html__('Success payment',$s),
			"transctionId" => $state  &&  isset($ac->text->transctionId) ? $ac->text->transctionId : esc_html__('Transaction Id',$s),
			"addPaymentGetway" => $state  &&  isset($ac->text->addPaymentGetway) ? $ac->text->addPaymentGetway : esc_html__('Error: No payment gateway has been added to the form.',$s),
			"emptyCartM" => $state  &&  isset($ac->text->emptyCartM) ? $ac->text->emptyCartM : esc_html__('Your cart is currently empty. Please add items to continue.',$s),
			"payCheckbox" => $state  &&  isset($ac->text->payCheckbox) ? $ac->text->payCheckbox : esc_html__('Payment Multi choose',$s),
			"payRadio" => $state  &&  isset($ac->text->payRadio) ? $ac->text->payRadio : esc_html__('Payment Single choose',$s),
			"paySelect" => $state  &&  isset($ac->text->paySelect) ? $ac->text->paySelect : esc_html__('Payment Selection Choose',$s),
			"payMultiselect" => $state  &&  isset($ac->text->payMultiselect) ? $ac->text->payMultiselect : esc_html__('Payment dropdown list',$s),
			"errorCode" => $state  &&  isset($ac->text->errorCode) ? $ac->text->errorCode : esc_html__('Error Code',$s),
			"stripeKeys" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : esc_html__('Stripe Keys',$s),
			"stripeMP" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : esc_html__('If you want to use payment functionality in your forms, you will need to obtain your Stripe keys.',$s),
			"publicKey" => $state  &&  isset($ac->text->publicKey) ? $ac->text->publicKey : esc_html__('Public Key',$s),
			"price" => $state  &&  isset($ac->text->price) ? $ac->text->price : esc_html__('Price',$s),
			"title" => $state  &&  isset($ac->text->title) ? $ac->text->title : esc_html__('title',$s),
			"medium" => $state  &&  isset($ac->text->medium) ? $ac->text->medium : esc_html__('Medium',$s),
			"small" => $state  &&  isset($ac->text->small) ? $ac->text->small : esc_html__('Small',$s),
			"xsmall" => $state  &&  isset($ac->text->xsmall) ? $ac->text->xsmall : esc_html__('XSmall',$s),
			"xxsmall" => $state  &&  isset($ac->text->xxsmall) ? $ac->text->xxsmall : esc_html__('XXSmall',$s),
			"createPaymentForm" => $state  &&  isset($ac->text->createPaymentForm) ? $ac->text->createPaymentForm : esc_html__('Create a payment form.',$s),
			"pro" => $state  &&  isset($ac->text->pro) ? $ac->text->pro : esc_html__('Pro',$s),
			"submit" => $state  &&  isset($ac->text->submit) ? $ac->text->submit : esc_html__('Submit',$s),
			"purchaseOrder" => $state  &&  isset($ac->text->purchaseOrder) ? $ac->text->purchaseOrder : esc_html__('Purchase Order',$s),
			"paymentNcaptcha" => $state  &&  isset($ac->text->paymentNcaptcha) ? $ac->text->paymentNcaptcha : esc_html__('It is not possible to include reCAPTCHA on payment forms.',$s),
			"PleaseMTPNotWork" => $state &&  isset($ac->text->PleaseMTPNotWork) ? $ac->text->PleaseMTPNotWork : esc_html__('Easy Form Builder could not confirm if your service is able to send emails. Please check your email inbox (or spam folder) to see if you have received an email with the subject line: Email server [Easy Form Builder]. If you have received the email, please select the option < I confirm that this host supports SMTP > and save the changes.',$s),
			"hostSupportSmtp" => $state  &&  isset($ac->text->hostSupportSmtp) ? $ac->text->hostSupportSmtp : esc_html__('I confirm that this host supports SMTP',$s),
			"interval" => $state  &&  isset($ac->text->interval) ? $ac->text->interval : esc_html__('Interval',$s),
			"nextBillingD" => $state  &&  isset($ac->text->nextBillingD) ? $ac->text->nextBillingD : esc_html__('Next Billing Date',$s),
			"dayly" => $state  &&  isset($ac->text->dayly) ? $ac->text->dayly : esc_html__('Daily',$s),
			"monthly" => $state  &&  isset($ac->text->monthly) ? $ac->text->monthly : esc_html__('Monthly',$s),
			"weekly" => $state  &&  isset($ac->text->weekly) ? $ac->text->weekly : esc_html__('Weekly',$s),
			"yearly" => $state  &&  isset($ac->text->yearly) ? $ac->text->yearly : esc_html__('Yearly',$s),
			"howProV" => $state  &&  isset($ac->text->howProV) ? $ac->text->howProV : esc_html__('How to activate Pro version of Easy form builder',$s),
			"uploadedFile" => $state  &&  isset($ac->text->uploadedFile) ? $ac->text->uploadedFile : esc_html__('Uploaded File',$s),
			"offlineMSend" => $state  &&  isset($ac->text->offlineMSend) ? $ac->text->offlineMSend : esc_html__('Your internet connection has been lost, but do not worry, we have saved the information you entered on this form. Once you are reconnected to the internet, you can easily send your information by clicking the submit button.',$s),
			"offlineSend" => $state  &&  isset($ac->text->offlineSend) ? $ac->text->offlineSend : esc_html__('Please ensure that you have a stable internet connection and try again.',$s),
			"options" => $state  &&  isset($ac->text->options) ? $ac->text->options : esc_html__('Options',$s),
			"eJQ500" => $state  &&  isset($ac->text->eJQ500) ? $ac->text->eJQ500 : esc_html__('You are experiencing issues with JQuery. Please contact the administrator for assistance. (Error code: JQ-500)',$s),
			"basic" => $state  &&  isset($ac->text->basic) ? $ac->text->basic : esc_html__('Basic',$s),
			"blank" => $state  &&  isset($ac->text->blank) ? $ac->text->blank : esc_html__('Blank',$s),
			"support" => $state  &&  isset($ac->text->support) ? $ac->text->support : esc_html__('Support',$s),
			"signInUp" => $state  &&  isset($ac->text->signInUp) ? $ac->text->signInUp : esc_html__('Sign-In|Up',$s),
			"advance" => $state  &&  isset($ac->text->advance) ? $ac->text->advance : esc_html__('Advance',$s),
			"all" => $state  &&  isset($ac->text->all) ? $ac->text->all : esc_html__('All',$s),
			"new" => $state  &&  isset($ac->text->new) ? $ac->text->new : esc_html__('New',$s),
			"landingTnx" => $state  &&  isset($ac->text->landingTnx) ? $ac->text->landingTnx : esc_html__('Landing of thank you section',$s),
			"redirectPage" => $state  &&  isset($ac->text->redirectPage) ? $ac->text->redirectPage : esc_html__('Redirect page',$s),
			"pWRedirect" => $state  &&  isset($ac->text->pWRedirect) ? $ac->text->pWRedirect : esc_html__('Please wait, you will be redirected shortly.',$s),
			"persiaPayment" => $state  &&  isset($ac->text->persiaPayment) ? $ac->text->persiaPayment : esc_html__('Persia payment',$s),				
			"getPro" => $state  &&  isset($ac->text->getPro) ? $ac->text->getPro : esc_html__('Activate the Pro version.',$s),				
			"yFreeVEnPro" => $state  &&  isset($ac->text->yFreeVEnPro) ? $ac->text->yFreeVEnPro : esc_html__('You are using the free version. Activate the pro version now to get access to more and advanced professional features for only $NN/year.',$s),				
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('Add-on',$s),				
			"addons" => $state  &&  isset($ac->text->addons) ? $ac->text->addons : esc_html__('Add-ons',$s),				
			"stripeTAddon" => $state  &&  isset($ac->text->stripeTAddon) ? $ac->text->stripeTAddon : esc_html__('Stripe Payment Addon',$s),				
			"stripeDAddon" => $state  &&  isset($ac->text->stripeDAddon) ? $ac->text->stripeDAddon : esc_html__('The Stripe add-on for Easy Form Builder enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.',$s),				
			"offlineTAddon" => $state  &&  isset($ac->text->offlineTAddon) ? $ac->text->offlineTAddon : esc_html__('Offline Forms Addon',$s),				
			"offlineDAddon" => $state  &&  isset($ac->text->offlineDAddon) ? $ac->text->offlineDAddon : esc_html__('The Offline Forms add-on for Easy Form Builder allows users to save their progress when filling out forms in offline situations.',$s),				
			
			"trackCTAddon" => $state  &&  isset($ac->text->trackCTAddon) ? $ac->text->trackCDAddon : esc_html__('trackCTAddon',$s),				
			"trackCDAddon" => $state  &&  isset($ac->text->trackCDAddon) ? $ac->text->trackCDAddon : esc_html__('trackCDAddon',$s),				
			"install" => $state  &&  isset($ac->text->install) ? $ac->text->install : esc_html__('Install',$s),				
			"upDMsg" => $state  &&  isset($ac->text->upDMsg) ? $ac->text->upDMsg : esc_html__('Please update Easy Form Builder before trying again.',$s),				
			"AfLnFrm" => $state  &&  isset($ac->text->AfLnFrm) ? $ac->text->AfLnFrm : esc_html__('Activation of offline form mode.',$s),				
			"IMAddons" => $state  &&  isset($ac->text->IMAddons) ? $ac->text->IMAddons : esc_html__('Before activation this option, install',$s),				
			"IMAddonP" => $state  &&  isset($ac->text->IMAddonP) ? $ac->text->IMAddonP : esc_html__('To create a payment form, you must first install a payment add-on such as the Stripe Add-on.',$s),				
			"allformat" => $state  &&  isset($ac->text->allformat) ? $ac->text->allformat : esc_html__('All formats',$s),				
			"AdnSST" => $state  &&  isset($ac->text->AdnSST) ? $ac->text->AdnSST : esc_html__('EFB SMS Addon',$s),				
			"AdnSSD" => $state  &&  isset($ac->text->AdnSSD) ? $ac->text->AdnSSD : esc_html__('Enable SMS functionality in your forms with the EFB SMS add-on, allowing you to validate mobile numbers and send confirmation codes via SMS, as well as receive notifications through SMS service.',$s),				
			"AdnATCT" => $state  &&  isset($ac->text->AdnATCT) ? $ac->text->AdnATCT : esc_html__('Advanced confirmation code Addon',$s),				
			"AdnATCD" => $state  &&  isset($ac->text->AdnATCD) ? $ac->text->AdnATCD : esc_html__('Send a confirmation code via email or SMS to users and/or admins, allowing them to quickly access new responses.',$s),				
			"chlCheckBox" => $state  &&  isset($ac->text->chlCheckBox) ? $ac->text->chlCheckBox : esc_html__('Box Checklist',$s),				
			"chlRadio" => $state  &&  isset($ac->text->chlRadio) ? $ac->text->chlRadio : esc_html__('Radio Checklist',$s),				
			"qty" => $state  &&  isset($ac->text->qty) ? $ac->text->qty : esc_html__('Qty',$s),				
			"wwpb" => $state  &&  isset($ac->text->wwpb) ? $ac->text->wwpb : esc_html__('This is a warning for WPBakery users. For more information, please click here.',$s),				
			"clsdrspnsM" => $state  &&  isset($ac->text->clsdrspnsM) ? $ac->text->clsdrspnsM : esc_html__('Are you sure you want to close the responses to this message?',$s),				
			"clsdrspnsMo" => $state  &&  isset($ac->text->clsdrspnsMo) ? $ac->text->clsdrspnsMo : esc_html__('Are you sure you want to open the responses to this message?',$s),				
			"clsdrspn" => $state  &&  isset($ac->text->clsdrspn) ? $ac->text->clsdrspn : esc_html__('The response has been closed by Admin.',$s),				
			"clsdrspo" => $state  &&  isset($ac->text->clsdrspo) ? $ac->text->clsdrspo : esc_html__('The response has been opened by Admin.',$s),				
			"open" => $state  &&  isset($ac->text->open) ? $ac->text->open : esc_html__('Open',$s),				
			"priceyr" => $state  &&  isset($ac->text->priceyr) ? $ac->text->priceyr : esc_html__('$NN/year',$s),				
			"cols" => $state  &&  isset($ac->text->cols) ? $ac->text->cols : esc_html__('columns',$s),				
			"col" => $state  &&  isset($ac->text->col) ? $ac->text->col : esc_html__('column',$s),				
			"ilclizeFfb" => $state  &&  isset($ac->text->ilclizeFfb) ? $ac->text->ilclizeFfb : esc_html__('I would like to localize Easy Form Builder.',$s),				
			"mlen" => $state  &&  isset($ac->text->mlen) ? $ac->text->mlen : esc_html__('Max length',$s),				
			"milen" => $state  &&  isset($ac->text->milen) ? $ac->text->milen : esc_html__('Min length',$s),				
			"mmlen" => $state  &&  isset($ac->text->mmlen) ? $ac->text->mmlen : esc_html__('The maximum number of characters allowed in the input element is 524288',$s),				
			"mmplen" => $state  &&  isset($ac->text->mmplen) ? $ac->text->mmplen : esc_html__('Please enter a value that is at least NN characters long.',$s),				
			"mcplen" => $state  &&  isset($ac->text->mcplen) ? $ac->text->mcplen : esc_html__('Please enter a number that is greater than or equal to NN.',$s),				
			"mmxplen" => $state  &&  isset($ac->text->mmxplen) ? $ac->text->mmxplen : esc_html__('Please Enter a maximum of NN Characters For this field',$s),				
			"mxcplen" => $state  &&  isset($ac->text->mxcplen) ? $ac->text->mxcplen : esc_html__('Please enter a number that is less than or equal to NN',$s),				
			"max" => $state  &&  isset($ac->text->max) ? $ac->text->max : esc_html__('Max',$s),				
			"min" => $state  &&  isset($ac->text->min) ? $ac->text->min : esc_html__('Min',$s),				
			"mxlmn" => $state  &&  isset($ac->text->mxlmn) ? $ac->text->mxlmn : esc_html__('Minimum entry must lower than maximum entry',$s),				
			"disabled" => $state  &&  isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Disabled',$s),				
			"hflabel" => $state  &&  isset($ac->text->hflabel) ? $ac->text->hflabel : esc_html__('Hide the label',$s),				
			"resop" => $state  &&  isset($ac->text->resop) ? $ac->text->resop : esc_html__('The response(ticket) closed',$s),				
			"rescl" => $state  &&  isset($ac->text->rescl) ? $ac->text->rescl : esc_html__('The response(ticket) opened',$s),				
			"clcdetls" => $state  &&  isset($ac->text->clcdetls) ? $ac->text->clcdetls : esc_html__('Click here for more details',$s),				
			"lson" => $state  &&  isset($ac->text->lson) ? $ac->text->lson : esc_html__('Label of the ON status',$s),				
			"lsoff" => $state  &&  isset($ac->text->lsoff) ? $ac->text->lsoff : esc_html__('Label of the OFF status',$s),				
			"pr5" => $state  &&  isset($ac->text->pr5) ? $ac->text->pr5 : esc_html__('5 Point Scale',$s),				
			"nps_" => $state  &&  isset($ac->text->nps_) ? $ac->text->nps_ : esc_html__('Net Promoter Score',$s),				
			"nps_tm" => $state  &&  isset($ac->text->nps_tm) ? $ac->text->nps_tm : esc_html__('NPS Table Matrix',$s),				
			"pointr10" => $state  &&  isset($ac->text->pointr10) ? $ac->text->pointr10 : esc_html__('Net Promoter Score',$s),				
			"pointr5" => $state  &&  isset($ac->text->pointr5) ? $ac->text->pointr5 : esc_html__('5 Point Scale',$s),				
			"table_matrix" => $state  &&  isset($ac->text->table_matrix) ? $ac->text->table_matrix : esc_html__('NPS Table Matrix',$s),				
			"pdate" => $state  &&  isset($ac->text->pdate) ? $ac->text->pdate : esc_html__('Jalali Date',$s),				
			"ardate" => $state  &&  isset($ac->text->ardate) ? $ac->text->ardate : esc_html__('Hijri Date',$s),				
			"iaddon" => $state  &&  isset($ac->text->iaddon) ? $ac->text->iaddon : esc_html__('Install the addon',$s),				
			"IMAddonPD" => $state  &&  isset($ac->text->IMAddonPD) ? $ac->text->IMAddonPD : esc_html__('Please go to Add-ons Page of Easy Form Builder plugin and install the Jalili date addons',$s),	
			"IMAddonAD" => $state  &&  isset($ac->text->IMAddonAD) ? $ac->text->IMAddonAD : esc_html__('Please go to Add-ons Page of Easy Form Builder plugin and install the Hijri date addons',$s),	
			"warning" => $state  &&  isset($ac->text->warning) ? $ac->text->warning : esc_html__('warning',$s),	
			"datetimelocal" => $state  &&  isset($ac->text->datetimelocal) ? $ac->text->datetimelocal : esc_html__('date & time',$s),				
			"dsupfile" => $state  &&  isset($ac->text->dsupfile) ? $ac->text->dsupfile : esc_html__('Activate the file upload button in the response box',$s),				
			"scaptcha" => $state  &&  isset($ac->text->scaptcha) ? $ac->text->scaptcha : esc_html__('Activate Google reCAPTCHA in the response box',$s),				
			"sdlbtn" => $state  &&  isset($ac->text->sdlbtn) ? $ac->text->sdlbtn : esc_html__('Activate the download button in the response box.',$s),				
			"sips" => $state  &&  isset($ac->text->sips) ? $ac->text->sips : esc_html__('Display the IP addresses of users in the response box.',$s),				
			"persiaPayTAddon" => $state  &&  isset($ac->text->persiaPayTAddon) ? $ac->text->persiaPayTAddon : esc_html__('Persia Payment Addon',$s),				
			"persiaPayDAddon" => $state  &&  isset($ac->text->persiaPayDAddon) ? $ac->text->persiaPayDAddon : esc_html__('The Persia payment addon for Easy Form Builder enables you to connect your website with Persia payment to process payments, donations, and online orders.',$s),				

			"datePTAddon" => $state  &&  isset($ac->text->datePTAddon) ? $ac->text->datePTAddon : esc_html__('Jalali date Addon',$s),				
			"datePDAddon" => $state  &&  isset($ac->text->datePDAddon) ? $ac->text->datePDAddon : esc_html__('The Jalali date addon allows you to add a Jalali date field to your forms and create any type of form that includes this Shamsi date field.',$s),				
			"dateATAddon" => $state  &&  isset($ac->text->dateATAddon) ? $ac->text->dateATAddon : esc_html__('Hijri date Addon',$s),				
			"dateADAddon" => $state  &&  isset($ac->text->dateADAddon) ? $ac->text->dateADAddon : esc_html__('The Hijri date addon allows you to add a Hijri date field to your forms and create any type of form that includes this field.',$s),				
			"smsTAddon" => $state  &&  isset($ac->text->smsTAddon) ? $ac->text->smsTAddon : esc_html__('SMS service Addon',$s),				
			"smsDAddon" => $state  &&  isset($ac->text->smsDAddon) ? $ac->text->smsDAddon : esc_html__('The SMS service addon enables you to receive notification SMS messages when you or your customers receive new messages or responses.',$s),				
			"mPAdateW" => $state  &&  isset($ac->text->mPAdateW) ? $ac->text->mPAdateW : esc_html__('Please install either the Hijri or Jalali date addon. You cannot install both addons simultaneously.',$s),				
			"rbox" => $state  &&  isset($ac->text->rbox) ? $ac->text->rbox : esc_html__('Response box',$s),				
			"smartcr" => $state  &&  isset($ac->text->smartcr) ? $ac->text->smartcr : esc_html__('Regions Drop-Down',$s),				
			"ptrnMmm" => $state  &&  isset($ac->text->ptrnMmm) ? $ac->text->ptrnMmm : esc_html__('The value of the XXX field does not match the pattern and must be at least NN characters.',$s),				
			"ptrnMmx" => $state  &&  isset($ac->text->ptrnMmx) ? $ac->text->ptrnMmx : esc_html__('The value of the XXX field does not match the pattern and must be at most NN characters.',$s),				
			"mnvvXXX" => $state  &&  isset($ac->text->mnvvXX) ? $ac->text->mnvvXXX : esc_html__('Please enter valid value for the XXX field.',$s),				
			"wmaddon" => $state  &&  isset($ac->text->wmaddon) ? $ac->text->wmaddon : esc_html__('You are seeing this message because your required add-ons are being installed. Please wait a few minutes and then visit this page again. If it has been more than five minutes and nothing has happened, please contact the support team of Easy Form Builder at Whitestudio.team.',$s),				
			"cpnnc" => $state  &&  isset($ac->text->cpnnc) ? $ac->text->cpnnc : esc_html__('The cell phone number is not correct',$s),				
			"icc" => $state  &&  isset($ac->text->icc) ? $ac->text->icc : esc_html__('Invalid country code',$s),				
			"cpnts" => $state  &&  isset($ac->text->cpnts) ? $ac->text->cpnts : esc_html__('The cell phone number is too short',$s),				
			"cpntl" => $state  &&  isset($ac->text->cpntl) ? $ac->text->cpntl : esc_html__('The cell phone number is too long',$s),				
			"scdnmi" => $state  &&  isset($ac->text->scdnmi) ? $ac->text->scdnmi : esc_html__('Please select the number of countries to display within an acceptable range.',$s),				
			"dField" => $state  &&  isset($ac->text->dField) ? $ac->text->dField : esc_html__('Disabled Field',$s),				
			"hField" => $state  &&  isset($ac->text->hField) ? $ac->text->hField : esc_html__('Hidden Field',$s),							
			"sctdlosp" => $state  &&  isset($ac->text->sctdlosp) ? $ac->text->sctdlosp : esc_html__('Select a country to display a list of states/provinces.',$s),				
			"sctdlocp" => $state  &&  isset($ac->text->sctdlocp) ? $ac->text->sctdlocp : esc_html__('Select a states/provinces to display a list of city.',$s),				
			//don't remove (used in delete message)						
			"AdnOF" => $state  &&  isset($ac->text->AdnOf) ? $ac->text->AdnOf : esc_html__('Offline Forms Addon',$s),
			"AdnSPF" => $state  &&  isset($ac->text->AdnSPF) ? $ac->text->AdnSPF : esc_html__('Stripe Payment Addon',$s),
			"AdnPDP" => $state  &&  isset($ac->text->AdnPDP) ? $ac->text->AdnPDP : esc_html__('Jalali date Addon',$s),
			"AdnADP" => $state  &&  isset($ac->text->AdnADP) ? $ac->text->AdnADP : esc_html__('Hijri date Addon',$s),
			"AdnPPF" => $state  &&  isset($ac->text->AdnPPF) ? $ac->text->AdnPPF : esc_html__('Persia Payment Addon',$s),
			"AdnSS" => $state  &&  isset($ac->text->AdnSS) ? $ac->text->AdnSS : esc_html__('SMS service Addon',$s),
			"tfnapca" => $state  &&  isset($ac->text->tfnapca) ? $ac->text->tfnapca : esc_html__('Please contact the administrator as the field is currently unavailable.',$s),
			"wylpfucat" => $state  &&  isset($ac->text->wylpfucat) ? $ac->text->wylpfucat : esc_html__('Would you like to customize the form using the colors of the active template?',$s),
			"efbmsgctm" => $state  &&  isset($ac->text->efbmsgctm) ? $ac->text->efbmsgctm : esc_html__('Easy Form Builder has utilized the colors of the active template. Please choose a color for each option below to customize the form you are creating based on the colors of your template.By selecting a color for each option below, the color of all form fields associated with that feature will change accordingly.',$s),
			"btntcs" => $state  &&  isset($ac->text->btntcs) ? $ac->text->btntcs : esc_html__('Buttons text colors',$s),
			//End don't remove (used in delete message)
			"atcfle" => $state  &&  isset($ac->text->atcfle) ? $ac->text->atcfle : esc_html__('attached files',$s),				
			"dslctd" => $state  &&  isset($ac->text->dslctd) ? $ac->text->dslctd : esc_html__('Default selected',$s),				
			"shwattr" => $state  &&  isset($ac->text->shwattr) ? $ac->text->shwattr : esc_html__('Show attributes',$s),				
			"hdattr" => $state  &&  isset($ac->text->hdattr) ? $ac->text->hdattr : esc_html__('Hide attributes',$s),				
			"idl5" => $state  &&  isset($ac->text->idl5) ? $ac->text->idl5 : esc_html__('The ID length should be at least 3 characters long.',$s),				
			"idmu" => $state  &&  isset($ac->text->idmu) ? $ac->text->idmu : esc_html__('The ID value must be unique, as it is already being used in this field. please try a new, unique value.',$s),				
			"imgRadio" => $state  &&  isset($ac->text->imgRadio) ? $ac->text->imgRadio : esc_html__('Image picker',$s),				
			"iimgurl" => $state  &&  isset($ac->text->iimgurl) ? $ac->text->iimgurl : esc_html__('Insert an image url',$s),				
			"newbkForm" => $state &&  isset($ac->text->newbkForm)? $ac->text->newbkForm : esc_html__('New Booking Form',$s),
			"bkXpM" => $state  &&  isset($ac->text->bkXpM) ? $ac->text->bkXpM : esc_html__('We are sorry, the booking time for the XXX option has expired. Please choose from the other available options.',$s),				
			"bkFlM" => $state  &&  isset($ac->text->bkFlM) ? $ac->text->bkFlM : esc_html__('We are sorry, the XXX option is currently at full capacity. Please choose from the other available options.',$s),				
			"AdnSMF" => $state  &&  isset($ac->text->AdnSMF) ? $ac->text->AdnSMF : esc_html__('Conditional logic Addon',$s),
			"condATAddon" => $state  &&  isset($ac->text->condATAddon) ? $ac->text->condATAddon : esc_html__('Conditional logic Addon',$s),
			"condADAddon" => $state  &&  isset($ac->text->condADAddon) ? $ac->text->condADAddon : esc_html__('The Conditional Logic Addon enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users unique needs.',$s),			
			//"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : esc_html__('Conditional logic',$s),
			"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : esc_html__('Enable Conditional',$s),
			"enableCon" => $state  &&  isset($ac->text->enableCon) ? $ac->text->enableCon : esc_html__('Enable Conditional',$s),
			"show" => $state  &&  isset($ac->text->show) ? $ac->text->show : esc_html__('Show',$s),
			"hide" => $state  &&  isset($ac->text->hide) ? $ac->text->hide : esc_html__('Hide',$s),
			"tfif" => $state  &&  isset($ac->text->tfif) ? $ac->text->tfif : esc_html__('This field if',$s),
			"contains" => $state  &&  isset($ac->text->contains) ? $ac->text->contains : esc_html__('Contains',$s),
			"ncontains" => $state  &&  isset($ac->text->ncontains) ? $ac->text->ncontains : esc_html__('Not contain',$s),
			"startw" => $state  &&  isset($ac->text->startw) ? $ac->text->startw : esc_html__('starts with',$s),
			"endw" => $state  &&  isset($ac->text->endw) ? $ac->text->endw : esc_html__('ends with',$s),
			"gthan" => $state  &&  isset($ac->text->gthan) ? $ac->text->gthan : esc_html__('greater than',$s),
			"lthan" => $state  &&  isset($ac->text->lthan) ? $ac->text->lthan : esc_html__('less than',$s),
			"ise" => $state  &&  isset($ac->text->ise) ? $ac->text->ise : esc_html__('Is',$s),
			"isne" => $state  &&  isset($ac->text->isne) ? $ac->text->isne : esc_html__('Is not',$s),
			"empty" => $state  &&  isset($ac->text->empty) ? $ac->text->empty : esc_html__('Empty',$s),
			"nEmpty" => $state  &&  isset($ac->text->nEmpty) ? $ac->text->nEmpty : esc_html__('Not empty',$s),
			"or" => $state  &&  isset($ac->text->or) ? $ac->text->or : esc_html__('or',$s),
			"and" => $state  &&  isset($ac->text->and) ? $ac->text->and : esc_html__('and',$s),
			"addngrp" => $state  &&  isset($ac->text->addngrp) ? $ac->text->addngrp : esc_html__('Add New Group',$s),
			
			"adduf" => $state  &&  isset($ac->text->adduf) ? $ac->text->adduf : esc_html__('Add your forms',$s),				
			
			"pgbar" => $state  &&  isset($ac->text->pgbar) ? $ac->text->pgbar : esc_html__('Progress bar',$s),
			"smsNotiM" => $state  &&  isset($ac->text->smsNotiM) ? $ac->text->smsNotiM : esc_html__('SMS notification texts',$s),
			"smsNotiMA" => $state  &&  isset($ac->text->smsNotiMA) ? $ac->text->smsNotiMA : esc_html__('The SMS should include your website address',$s),
			"adrss_vld" => $state  &&  isset($ac->text->adrss_vld) ? $ac->text->adrss_vld : esc_html__('Enable Postal Code validation for addresses',$s),
			"adrss_pc" => $state  &&  isset($ac->text->adrss_pc) ? $ac->text->adrss_pc : esc_html__('Enable Postal Code validation',$s),
			"pc_inc_m" => $state  &&  isset($ac->text->pc_inc_m) ? $ac->text->pc_inc_m : esc_html__('The postal code is incorrect.',$s),
			"adrss_inc_m" => $state  &&  isset($ac->text->adrss_inc_m) ? $ac->text->adrss_inc_m : esc_html__('The Address is incorrect.',$s),
			"cities" => $state  &&  isset($ac->text->cities) ? $ac->text->cities : esc_html__('cities',$s),
			"list" => $state  &&  isset($ac->text->list) ? $ac->text->list : esc_html__('XXX list',$s),
			"dftuwln" => $state  &&  isset($ac->text->dftuwln) ? $ac->text->dftuwln : esc_html__('Display forms to users who are logged in.',$s),
			"dftuwp" => $state  &&  isset($ac->text->dftuwp) ? $ac->text->dftuwp : esc_html__('Display forms to users who have the password.',$s),
			"fSiz_l_dy" => $state &&  isset($ac->text->fSiz_l_dy) ? $ac->text->fSiz_l_dy : esc_html__('The uploaded file exceeds the allowable limit of XXX MB.',$s),
			"fSiz_s_dy" => $state &&  isset($ac->text->fSiz_s_dy) ? $ac->text->fSiz_s_dy : esc_html__('The uploaded file is below the required minimum size of XXX MB.',$s),
			"lb_m_fSiz" => $state &&  isset($ac->text->lb_m_fSiz) ? $ac->text->lb_m_fSiz : esc_html__('Maximum File Size',$s),
			"lb_mi_fSiz" => $state &&  isset($ac->text->lb_mi_fSiz) ? $ac->text->lb_mi_fSiz : esc_html__('Minmum File Size',$s),
			"pss" => $state &&  isset($ac->text->pss) ? $ac->text->pss : esc_html__('Passwords',$s),
			"sms_config" => $state &&  isset($ac->text->sms_config) ? $ac->text->sms_config : esc_html__('SMS Configuration',$s),
			"sms_mp" => $state  &&  isset($ac->text->sms_mp) ? $ac->text->sms_mp : esc_html__('To enable SMS notifications in your forms, select the SMS notification delivery method.',$s),
			"sms_ct" => $state  &&  isset($ac->text->sms_ct) ? $ac->text->sms_ct : esc_html__('Select the method to send SMS notifications',$s),
			"sms_admn_no" => $state  &&  isset($ac->text->sms_admn_no) ? $ac->text->sms_admn_no : esc_html__('Enter the admins\' mobile numbers',$s),

			"sms_efbs" => $state  &&  isset($ac->text->sms_efbs) ? $ac->text->sms_efbs : esc_html__('Easy Form Builder SMS service',$s),
			"sms_wpsmss" => $state  &&  isset($ac->text->sms_wpsmss) ? $ac->text->sms_wpsmss : esc_html__('WP SMS plugin By VeronaLabs',$s),
			"wpsms_nm" => $state  &&  isset($ac->text->wpsms_nm) ? $ac->text->wpsms_nm : esc_html__('WP SMS plugin By VeronaLabs is not installed or activated. Please select another option, or install and configure WP SMS.',$s),
			"msg_adons" => $state  &&  isset($ac->text->msg_adons) ? $ac->text->msg_adons : esc_html__('To use this option, please install the NN add-ons from the Easy Form Builder plugin\'s Add-ons page.',$s),
			"sms_noti" => $state  &&  isset($ac->text->sms_noti) ? $ac->text->sms_noti : esc_html__('SMS notifications',$s),
			"sms_dnoti" => $state  &&  isset($ac->text->sms_dnoti) ? $ac->text->sms_dnoti : esc_html__('To send informational text messages, such as notifications or new messages, please enter the mobile numbers of the administrators here.',$s),
			"sms_ndnoti" => $state  &&  isset($ac->text->sms_ndnoti) ? $ac->text->sms_ndnoti : esc_html__(' Note that by entering mobile numbers, all notification messages for all forms and other informational texts will be sent to the provided numbers.',$s),
			"emlc" => $state  &&  isset($ac->text->emlc) ? $ac->text->emlc : esc_html__('Choose Email notification content',$s),
			"emlacl" => $state  &&  isset($ac->text->emlacl) ? $ac->text->emlacl : esc_html__('The email includes the confirmation code and link',$s),
			"emlml" => $state  &&  isset($ac->text->emlml) ? $ac->text->emlml : esc_html__('The email includes the filled form and link',$s),
			"msgemlmp" => $state  &&  isset($ac->text->msgemlmp) ? $ac->text->msgemlmp : esc_html__('To view the map and selected points, simply click here to navigate to the received message page',$s),
			"msgchckvt" => $state  &&  isset($ac->text->msgchckvt) ? $ac->text->msgchckvt : esc_html__('Review the entered values in the XXX tab.this message appeared because an error is detected.',$s),

			"sms" => $state  &&  isset($ac->text->sms) ? $ac->text->sms : esc_html__('SMS',$s),
			"smscw" => $state  &&  isset($ac->text->smscw) ? $ac->text->smscw : esc_html__('Click on the Settings button on the panel page of Easy Form Builder Plugin and configure the SMS sending method. Then, try again.',$s),
			"to" => $state  &&  isset($ac->text->to) ? $ac->text->to : esc_html__('To',$s),
			"esmsno" => $state  &&  isset($ac->text->esmsno) ? $ac->text->esmsno : esc_html__('Enable SMS notifications',$s),
			"payPalTAddon" => $state  &&  isset($ac->text->payPalTAddon) ? $ac->text->payPalTAddon : esc_html__('PayPal Payment Addon',$s),				
			"payPalDAddon" => $state  &&  isset($ac->text->payPaleDAddon) ? $ac->text->payPaleDAddon : esc_html__('The PayPal add-on for Easy Form Builder enables you to integrate your WordPress site with PayPal for payment processing, donations, and online orders.',$s),				
			"file_cstm" => $state  &&  isset($ac->text->file_cstm) ? $ac->text->file_cstm : esc_html__('Acceptable file types',$s),
			"cstm_rd" => $state  &&  isset($ac->text->cstm_rd) ? $ac->text->cstm_rd : esc_html__('Customized Ordering',$s),
			"maxfs" => $state  &&  isset($ac->text->maxfs) ? $ac->text->maxfs : esc_html__('Max File Size',$s),
			"cityList" => $state  &&  isset($ac->text->cityList) ? $ac->text->cityList : esc_html__('Cities Drop-Down',$s),
			"elan" => $state  &&  isset($ac->text->elan) ? $ac->text->elan : esc_html__('English language',$s),
			"nlan" => $state  &&  isset($ac->text->nlan) ? $ac->text->nlan : esc_html__('National language',$s),
			"stsd" => $state  &&  isset($ac->text->stsd) ? $ac->text->stsd : esc_html__('Select display language',$s),
			"excefb" => $state  &&  isset($ac->text->excefb) ? $ac->text->excefb : esc_html__('The XX plugin might interfere with forms of Easy Form Builder\'s functionality. If you encounter any issues with the Forms, disable caching for the Easy Form Builder plugin in the XX plugin\'s settings.',$s),
			"trya" => $state  &&  isset($ac->text->trya) ? $ac->text->trya : esc_html__('Trying again.',$s),
			"rnfn" => $state  &&  isset($ac->text->rnfn) ? $ac->text->rnfn : esc_html__('Rename the file name',$s),
			"ausdup" => $state  &&  isset($ac->text->ausdup) ? $ac->text->ausdup : esc_html__('Are you sure you want to duplicate the XXX ?',$s),
			"conlog" => $state  &&  isset($ac->text->conlog) ? $ac->text->conlog : esc_html__('Conditional logic',$s),
			"fil" => $state  &&  isset($ac->text->fil) ? $ac->text->fil : esc_html__('Form is loading',$s),
			"stf" => $state  &&  isset($ac->text->stf) ? $ac->text->stf : esc_html__('Submitting the form',$s),
			"address_line" => $state  &&  isset($ac->text->address_line ) ? $ac->text->address_line  : esc_html__('Address',$s),
			"postalcode" => $state  &&  isset($ac->text->postalcode ) ? $ac->text->postalcode  : esc_html__('Postal Code',$s),
			"vmgs" => $state  &&  isset($ac->text->vmgs ) ? $ac->text->vmgs  : esc_html__('View message and reply',$s),
			"prcfld" => $state  &&  isset($ac->text->prcfld) ? $ac->text->prcfld : esc_html__('Price field',$s),
			"ttlprc" => $state  &&  isset($ac->text->ttlprc) ? $ac->text->ttlprc : esc_html__('Total price',$s),
			"total" => $state  &&  isset($ac->text->total) ? $ac->text->total : esc_html__('Total',$s),
			"mlsbjt" => $state  &&  isset($ac->text->mlsbjt) ? $ac->text->mlsbjt : esc_html__('Email Subject',$s),
			"frmtype" => $state  &&  isset($ac->text->frmtype) ? $ac->text->frmtype : esc_html__('Form type',$s),
			"fernvtf" => $state  &&  isset($ac->text->fernvtf) ? $ac->text->fernvtf : esc_html__('The entered data does not match the form type. If you are an admin, please review the form type.',$s),
			"fetf" => $state  &&  isset($ac->text->fetf) ? $ac->text->fetf : esc_html__('Error: Please ensure there is only one form per page.',$s),
			"actvtcmsg" => $state  &&  isset($ac->text->actvtcmsg) ? $ac->text->actvtcmsg : esc_html__('The activation code has been successfully verified. Enjoy Pro features and utilize the Easy Form Builder.',$s),
			"msgdml" => $state  &&  isset($ac->text->msgdml) ? $ac->text->msgdml : esc_html__('The confirmation code for this message is %s. By clicking the button below, you will be able to track messages and view received responses. If needed, you can also send a new reply.',$s),
			"msgnml" => $state  &&  isset($ac->text->msgnml) ? $ac->text->msgnml : esc_html__('To explore the full functionality and settings of Easy Form Builder, including email configurations, form creation options, and other features, simply delve into our %s1 documentation %s2 .',$s),
			"mlntip" => $state  &&  isset($ac->text->mlntip) ? $ac->text->mlntip : esc_html__('Make sure to check your spam folder for test emails. If your emails are being marked as spam or not being sent, it\'s likely due to the hosting provider you are using. You will need to adjust your email server settings to prevent emails sent from your server from being flagged as spam. For more information, %s1 click here %s2 or %s3 contact Easy Form Builder support %s4.',$s),
			"from" => $state  &&  isset($ac->text->from) ? $ac->text->from : esc_html__('From Address',$s),
			"msgfml" => $state  &&  isset($ac->text->msgfml) ? $ac->text->msgfml : esc_html__('To avoid emails going to spam or not being sent, make sure the email address here matches the one in the SMTP settings.',$s),
			"prsm" => $state  &&  isset($ac->text->prsm) ? $ac->text->prsm : esc_html__('To preview the form, you need to save the built form and try again.',$s),
			"nsrf" => $state  &&  isset($ac->text->nsrf) ? $ac->text->nsrf : esc_html__('No selected rows found.',$s),
			"spprt" => $state  &&  isset($ac->text->spprt) ? $ac->text->spprt : esc_html__('Support',$s),
			"mread" => $state  &&  isset($ac->text->mread) ? $ac->text->mread : esc_html__('Mark as Read',$s),
			"admines" => $state  &&  isset($ac->text->admines) ? $ac->text->admines : esc_html__('Form admins can access the response box after logging in.',$s),
			"trmcn" => $state  &&  isset($ac->text->trmcn) ? $ac->text->trmcn : esc_html__('I have read and agree to %s1the terms and conditions%s2',$s),
			"trmCheckbox" => $state  &&  isset($ac->text->trmCheckbox) ? $ac->text->trmCheckbox : esc_html__('Terms',$s),
			"prvnt" => $state  &&  isset($ac->text->prvnt) ? $ac->text->prvnt : esc_html__('Preview in new tab',$s),
			"mxdt" => $state  &&  isset($ac->text->mxdt) ? $ac->text->mxdt : esc_html__('Maximum date',$s),
			"mindt" => $state  &&  isset($ac->text->mindt) ? $ac->text->mindt : esc_html__('Minimum date',$s),
			"ivf" => $state  &&  isset($ac->text->ivf) ? $ac->text->ivf : esc_html__('Valid formats: %s',$s),
			"zoom" => $state  &&  isset($ac->text->zoom) ? $ac->text->zoom : esc_html__('Zoom',$s),
			"lpds" => $state  &&  isset($ac->text->lpds) ? $ac->text->lpds : esc_html__('To enable the Location Picker field, Easy Form Builder loads JavaScript files from the unpkg.com CDN for the leafletjs.com service, but only on pages using this feature.',$s),
			"elpo" => $state  &&  isset($ac->text->elpo) ? $ac->text->elpo : esc_html__('Enable Location Picker in Easy Form Builder',$s),
			"jqinl" => $state  &&  isset($ac->text->jqinl) ? $ac->text->jqinl : esc_html__('Easy Form Builder cannot display the form because jQuery is not properly loaded. This issue might be due to incorrect jQuery invocation by another plugin or the current website theme.',$s),
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('%s1 Addon',$s),
			'tlgm' => $state  &&  isset($ac->text->tlgm) ? $ac->text->tlgm : esc_html__('Telegram',$s),
			"tlgmAddon" => $state  &&  isset($ac->text->tlgmAddon) ? $ac->text->tlgmAddon : esc_html__('Telegram notification Addon',$s),
			"tlgmDAddon" => $state  &&  isset($ac->text->tlgmDAddon) ? $ac->text->tlgmDAddon : esc_html__('The Telegram notification addon lets you get notifications on your Telegram app whenever you receive new messages or responses',$s),				
			"eln" => $state  &&  isset($ac->text->eln) ? $ac->text->eln : esc_html__('Enter a location name',$s),
			"alns" => $state  &&  isset($ac->text->alns) ? $ac->text->alns : esc_html__('The %s1 pages are currently unavailable. It looks like another plugin is causing a conflict with %s1 . To fix this issue, %s2 contact %s1 support %s3 for assistance  or try disabling your plugins one at a time to identify the one causing the conflict.',$s),
			"thank" => $state  &&  isset($ac->text->thank) ? $ac->text->thank : esc_html__('Thank',$s)
			
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
		//array_push($rtrn);
		return $rtrn;
	}

	public function send_email_state_new($to ,$sub ,$cont,$pro,$state,$link,$st="null"){													
				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);				
			   	$mailResult = "n";
				if(gettype($to) == 'array')ksort($to);
				$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
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
					$message = $this->email_template_efb($pro,$state,$cont,$link,$st); 				
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
					
						$cont .="<hr><br> website:[". $_SERVER['SERVER_NAME'] . "]<br> Pro state:[".$pro . "]<br> email:[".$mail .
						"]<br> role:[".$role."]<br> name:[".$name."]<br> state:[".$state."]";                   
						$mailResult = wp_mail( $support,$state, $cont, $headers ) ;
					
					}

					return $mailResult;
				}else{
					for($i=0 ; $i<2 ; $i++){
						if(empty($to[$i])==false && $to[$i]!="null" && $to[$i]!=null && $to[$i]!=[null] && $to[$i]!=[]){
							$message = $this->email_template_efb($pro,$state[$i],$cont[$i],$link[$i],$st); 	
							if( $state!="reportProblem"){										
								$to_;$mailResult;
								$to_ = $to[$i];
								if (gettype($to_) == 'string' && is_email($to_)) {																
									$sub_ = $sub[$i];
									$mailResult =  wp_mail( $to_,$sub_, $message, $headers ) ;
									
								} else {
									//remove duplicates
									$to[$i]= array_unique($to[$i]);
									foreach ($to[$i] as $r) {
										$sub_ = $sub[$i];
										$to_ = $r;
										if(is_email($to_)) $mailResult = wp_mail($to_, $sub_, $message, $headers);
									}
									
								}
								
								//end loop
							
		
								
							}
						}
					}
					
					
				}
				    remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );								
			   return $mailResult;
	}

	public function email_template_efb($pro, $state, $m,$link ,$st="null"){	
	
		$l ='https://whitestudio.team';
		$wp_lan = get_locale();
			 if($wp_lan=="fa_IR"){ $l='https://easyformbuilder.ir'  ;}
			 else if($wp_lan=="ar" || get_locale()=="arq") {$l ="https://ar.whitestudio.team";}
			 else if ($wp_lan=="de_DE") {$l ="https://de.whitestudio.team";}
			 //elseif (get_locale()=="ar" || get_locale()=="arq") {$l ="https://ar.whitestudio.team/";}
		$text = ['msgdml','mlntip','msgnml','serverEmailAble','vmgs','getProVersion','sentBy','hiUser','trackingCode','newMessage','createdBy','newMessageReceived','goodJob','createdBy' , 'yFreeVEnPro','WeRecivedUrM'];
        $lang= $this->text_efb($text);				
			$footer= "<a class='efb subtle-link' target='_blank' href='".home_url()."'>".$lang["sentBy"]." ".  get_bloginfo('name')."</a>";			
		$align ='left';
		$d =  'ltr';
		if(is_rtl()){
			$d =  'rtl' ;
			$align ='right';
		}
		//}   

		
		if($st=='null') $st = $this->get_setting_Emsfb();
		if($st=="null") return;
		//serverEmailAble
		//if(strlen($st->activeCode)<5 ){ $footer .="<br></br><small><a class='efb subtle-link' target='_blank' href='". $l."'>". esc_html__('Created by','easy-form-builder') . " " . esc_html__('Easy Form Builder','easy-form-builder')."</a></small>";	}		
		$temp = isset($st->emailTemp) && strlen($st->emailTemp)>10 ? $st->emailTemp : "0";
		
		
		$title=$lang["newMessage"];
		$message = gettype($m)=='string' ?  "<h3>".$m."</h3>" : "<h3>".$m[0]."</h3>";
		$blogName =get_bloginfo('name');
		$user=function_exists("get_user_by")?  get_user_by('id', 1) :false;
		
		$adminEmail = $user!=false ? $user->user_email :'';
		$blogURL= home_url();

		
		$dts =  $lang['msgdml'];
		
		if($state=="testMailServer"){
			$dt = $lang['msgnml'];
			$de = $lang['mlntip'];			
			$de =preg_replace('/^[^.]*\. /', '', $lang['mlntip']);			
			//$de = substr($de, 0, strpos($de, '.')+1);
			$link = "$l/document/send-email-using-smtp-plugin/";
			if($wp_lan=="fa_IR") $link = "$l/داکیومنت/ارسال-ایمیل-بوسیله-افزونه-smtp/";
			//
			$de = str_replace('%s1',"<a href='$link' target='_blank'>",$de);
			$de = str_replace('%s2',"</a>",$de);
			$de = str_replace('%s3',"<a href='$l/support/' target='_blank'>",$de);
			$de = str_replace('%s4',"</a>",$de);

			//replace %s1 and %s2 with links to documentation
			$dt = str_replace('%s1',"<a href='$l/documents/' target='_blank'>",$dt);
			$dt = str_replace('%s2',"</a>",$dt);
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
			//w_link;
			if(gettype($m)=='string'){
				$dts = str_replace('%s', $m, $dts);
				$link = strpos($link,"?")==true ? $link.'&track='.$m : $link.'?track='.$m;
				$message ="<h2 style='text-align:center'>".$lang["newMessageReceived"]."</h2>
				<p style='text-align:center'>". $lang["trackingCode"].": ".$m." </p>
				<p style='text-align:center'>".$dts." </p>
				<div style='text-align:center'><a href='".$link."' target='_blank' style='padding:5px;color:white;background:black;'>".$lang['vmgs']."</a></div>";
			}else{
				$dts = str_replace('%s', $m[0], $dts);
				$link = strpos($link,"?")==true ? $link.'&track='.$m[0] : $link.'?track='.$m[0];
				$message ="
				<div style='text-align:".$align.";color:#252526;font-size:14px;background: #f9f9f9;padding: 10px;margin: 20px 5px;'>".$m[1]." </div>
				<p style='text-align:center'>".$dts." </p>
				<div style='text-align:center'><a href='".$link."' target='_blank' style='padding:5px;color:white;background:black;'>".$lang['vmgs']."</a></div>";
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
				<div style='text-align:".$align.";color:#252526;font-size:14px;background: #f9f9f9;padding: 10px;margin: 20px 5px;'>".$m[1]." </div>
				<p style='text-align:center'>".$dts." </p>
				<div style='text-align:center'><a href='".$link."' target='_blank'  style='padding:5px;color:white;background:black;' >".$lang['vmgs']."</a></div>
				";
			}
		}		
		
		$val ="
		<html xmlns='http://www.w3.org/1999/xhtml'> <body style='margin:auto 10px;direction:".$d.";color:#000000;'><center>
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
				
				//$footer ="<table role='presentation' bgcolor='#F5F8FA' width='100%'><tr> <td align='".$align."' style='padding: 30px 30px;'>".$footer."</td></tr></table>";
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
		$table_name = $this->db->prefix . "emsfb_setting"; 
		//$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$value = $this->db->get_var( "SELECT setting FROM $table_name ORDER BY id DESC LIMIT 1" );
		$rtrn='null';
		if(isset($value)==false) return 'null';
		$v =str_replace('\\', '', $value);
		$rtrn =json_decode($v);
		$rtrn = $rtrn!=null ? $rtrn :'null';	
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
		$link_w = $lst['type']=="w_link" ? $lst['value'] : 'null';
		
		
		$table_name = $this->db->prefix . "emsfb_form"; 
		$data = $this->db->get_results("SELECT form_structer FROM `$table_name` WHERE form_id = '$form_id' ORDER BY form_id DESC LIMIT 1");
		
		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);
		if(($data[0]["sendEmail"]=="true"|| $data[0]["sendEmail"]==true ) &&   strlen($data[0]["email_to"])>2 ){			
			
			foreach($user_res as $key=>$val){
				if(isset($user_res[$key]["id_"]) && $user_res[$key]["id_"]==$data[0]["email_to"]){
					$email=$val["value"];
					$subject ="📮 ".$lang["youRecivedNewMessage"];
					$this->send_email_state_new($email ,$subject ,$trackingCode,$pro,"newMessage",$link_w,'null');
					//send_email_state_new($to ,$sub ,$cont,$pro,$state,$link,$st="null")
					return 1;
				}
			}
		}

		//send smsnoti
		
		if(isset($data[0]['smsnoti']) && intval($data[0]['smsnoti'])==1){		
				
			$phone_numbers=[[],[]];		
			$setting = $this->get_setting_Emsfb('setting');	
			
			//$numbers = isset($setting['phnNo']) ? explode(',',$setting['phnNo']) :[];
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
			//$this->efbFunction->sms_ready_for_send_efb($this->id, $phone_numbers,$url,'fform' ,'wpsms' ,$check);
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
						//sanitize url
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
						// $valp[$key][$k]=$v;
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
	}// end function



	public function sanitize_full_html_efb($html) {
		// General attributes allowed for all tags
		$global_attributes = array(
			'class' => true,       // CSS classes
			'id' => true,          // HTML ID
			'style' => true,       // Inline style (will be sanitized separately)
			'title' => true,       // Tooltip or descriptive text
			'data-*' => true,      // Custom data attributes
			'aria-*' => true,      // Accessibility attributes
		);
	
		// List of allowed CSS properties
		$allowed_properties = array(
			// Colors and background properties
			'color', 'background', 'background-color', 'background-image', 'background-position',
			'background-repeat', 'background-size', 'background-attachment', 'background-clip', 'background-origin',
			// Font properties
			'font', 'font-family', 'font-size', 'font-style', 'font-variant', 'font-weight',
			'letter-spacing', 'line-height', 'text-align', 'text-decoration', 'text-indent',
			'text-overflow', 'text-shadow', 'text-transform',
			// Dimensions and layout properties
			'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
			'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
			'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
			// Border properties
			'border', 'border-width', 'border-style', 'border-color', 'border-radius', 'outline',
			// Box and shadow properties
			'box-shadow', 'box-sizing',
			// Positioning and z-index
			'position', 'top', 'right', 'bottom', 'left', 'z-index', 'float', 'clear',
			// Flexbox and grid properties
			'display', 'flex', 'flex-grow', 'flex-shrink', 'flex-basis', 'align-items', 'align-content',
			'align-self', 'justify-content', 'grid', 'grid-template-rows', 'grid-template-columns',
			'grid-area', 'row-gap', 'column-gap',
			// Animation and transition properties
			'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay',
			'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
			// Miscellaneous
			'cursor', 'opacity', 'clip-path', 'filter', 'backface-visibility', 'transform',
			'transform-origin', 'transform-style',
		);
	
		// List of trusted domains for URLs in CSS (e.g., background-image)
		$current_domain = parse_url(home_url(), PHP_URL_HOST);
		$allowed_domains = array('google.com', 'gstatic.com', 'googleapis.com', 'googleusercontent.com', 'youtube.com', 'ytimg.com', 'microsoft.com', 'office.com', 'live.com', 'msn.com', 'outlook.com', 'amazonaws.com', 'cloudfront.net', 'cdnjs.cloudflare.com', 'maxcdn.bootstrapcdn.com', 'jsdelivr.net', 'unpkg.com', 'facebook.com', 'fbcdn.net', 'twitter.com', 'twimg.com', 'github.com', 'github.io', 'vimeo.com', 'vimeocdn.com', 'wikipedia.org', 'wikimedia.org', 'wikidata.org', 'stripe.com', 'paypal.com', 'braintreepayments.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'use.fontawesome.com', 'dailymotion.com', 'dmcdn.net', 'maps.googleapis.com', 'openstreetmap.org', 'mapbox.com', 'gravatar.com', 'unsplash.com', 'placekitten.com', 'placehold.co', 'akamaihd.net', 'cloudflare.com', 'fastly.net', 'linkedin.com', 'apple.com', 'adobe.com', 'cdn.shopify.com', 'example.com', 'example.org', 'trusted.com', 'cdn.trusted.com');

	
		// Function to validate URLs in attributes or CSS
		function validate_url($url) {
			global $allowed_domains;
			$parsed_url = parse_url($url);
	
			// Check if the domain is in the allowed list
			if (isset($parsed_url['host']) && in_array($parsed_url['host'], $allowed_domains)) {
				return esc_url($url);
			}
	
			// Ensure the URL does not contain dangerous schemes like `javascript:` or `data:`
			if (strpos($url, 'javascript:') === false && strpos($url, 'data:') === false) {
				return esc_url($url);
			}
	
			return ''; // Invalid URL
		}
	
		// Function to sanitize the `style` attribute
		function sanitize_style_attribute($style) {
			global $allowed_properties;
			$style_rules = explode(';', $style); // Split the style string into individual rules
			$sanitized_rules = array();
	
			foreach ($style_rules as $rule) {
				if (strpos($rule, ':') !== false) {
					list($property, $value) = explode(':', $rule, 2);
					$property = trim($property); // Clean up the property name
					$value = trim($value);       // Clean up the value
	
					// Check if the property is in the allowed list
					if (in_array($property, $allowed_properties)) {
						// If the value contains a URL, validate it
						if (strpos($value, 'url(') !== false) {
							preg_match('/url\(["\']?([^"\')]+)["\']?\)/i', $value, $matches);
							if (isset($matches[1]) && validate_url($matches[1])) {
								$sanitized_rules[] = $property . ': ' . $value;
							}
						} else {
							// Add the rule if it doesn't involve a URL
							$sanitized_rules[] = $property . ': ' . $value;
						}
					}
				}
			}
	
			// Reassemble the sanitized style attribute
			return implode('; ', $sanitized_rules);
		}
	
		// Allowed HTML tags and their attributes
		$allowed_tags = array(
			'a' => array_merge($global_attributes, array(
				'href' => true,  // Hyperlinks must be sanitized
				'title' => true,
				'rel' => true,
				'target' => true
			)),
			'abbr' => array_merge($global_attributes, array('title' => true)),
			'address' => $global_attributes,
			'area' => array_merge($global_attributes, array(
				'alt' => true,
				'coords' => true,
				'href' => true,  // Links must be sanitized
				'shape' => true,
				'target' => true,
			)),
			'audio' => array_merge($global_attributes, array(
				'autoplay' => true,
				'controls' => true,
				'loop' => true,
				'muted' => true,
				'preload' => true,
				'src' => true,  // Audio source must be sanitized
			)),
			'b' => $global_attributes,
			'blockquote' => array_merge($global_attributes, array('cite' => true)), // Validate cite attribute
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
				'src' => true,    // Image source must be sanitized
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
				'src' => true,  // Video source must be sanitized
				'width' => true,
				'height' => true,
			)),
		);
	
		// Sanitize the HTML using `wp_kses`
		$sanitized_html = wp_kses($html, $allowed_tags);
	
		// Further sanitize the `style` attribute
		$sanitized_html = preg_replace_callback(
			'/style=["\']([^"\']+)["\']/i',
			function ($matches) {
				return 'style="' . sanitize_style_attribute($matches[1]) . '"';
			},
			$sanitized_html
		);
	
		return $sanitized_html;
	}


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
		$ua ;
		if(empty($_SERVER['HTTP_USER_AGENT'])){
			
			$ua = array(
				'name' => 'unrecognized',
				'version' => 'unknown',
				'platform' => 'unrecognized',
				'userAgent' => ''
			);
		}else{
			
			$ua =$_SERVER['HTTP_USER_AGENT'];
		}
		curl_setopt($cURL, CURLOPT_URL, $url);
		curl_setopt($cURL, CURLOPT_HTTPGET, true);
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Accept: application/json',
			'User-Agent: '.$ua
		));
		$location = json_decode(curl_exec($cURL), true); 
		
		if(isset($location)){
			return $state==1 ? $location["country_code2"] :$location  ;
		}else{
			return 0;
		}
		
	}


	   public function addon_adds_cron_efb(){
		
		
		if ( ! wp_next_scheduled( 'download_all_addons_efb' ) ) {
			wp_schedule_single_event( time() + 1, 'download_all_addons_efb' );
		  }
		  
	   }//addon_adds_cron_efb


	   public function addon_add_efb($value){
				if($value!="AdnOF"){

            // اگر لینک دانلود داشت
            $server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
            $vwp = get_bloginfo('version');
			//just get version number
			$vwp = substr($vwp,0,3);
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
			if(get_locale()=='fa_IR'){
                $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
            }
			$attempts = 2; 
            for ($i = 0; $i < $attempts; $i++) {           
				$request = wp_remote_get($u);

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


            // Check version of EFB to Addons
            if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {        
				return false;                
            } 

            if($data->download==true){
                $url =$data->link;
				//split the url to get the folder name of the addon , bettwen last / and .zip	

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
		//download the addon dependency 
		$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
		require_once( $path . 'wp-load.php' );
		require_once (ABSPATH .'wp-admin/includes/admin.php');
		
		$name =substr($url,strrpos($url ,"/")+1,-4);
		
		$r =download_url($url);
		if(is_wp_error($r)){
			//show error message
			
		}else{
			$directory = EMSFB_PLUGIN_DIRECTORY . '//temp';
			if (!file_exists($directory)) {
				mkdir($directory, 0755, true);
			}
			$v = rename($r, EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip');
			if(is_wp_error($v)){
				$s = unzip_file($r, EMSFB_PLUGIN_DIRECTORY . '\\vendor\\');
				if(is_wp_error($s)){
				
					error_log('EFB=>unzip addons error 1:');
					//error_log(json_encode($r));
					return false;
				}
			}else{
				
				require_once(ABSPATH . 'wp-admin/includes/file.php');
				WP_Filesystem();
				$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
				if(is_wp_error($r)){
				
					
					
					error_log('EFB=>unzip addons error 2:');
					//error_log(json_encode($r));
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
			$this->send_email_state_new($to ,$sub ,$m,0,"addonsDlProblem",'null','null');
			return false;
		}
		//refresh carrent page by php
	
			
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
		$date_now = date('Y-m-d H:i:s');
		$date_limit = date('Y-m-d H:i:s', strtotime('+24 hours'));
	
		$sid = date("ymdHis") . substr(bin2hex(openssl_random_pseudo_bytes(5)), 0, 9);
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
		// $status => visit , send , upd , del => max len 5
		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = date('Y-m-d H:i:s', strtotime('-24 hours'));
		$active =0;
		$read_date =date('Y-m-d H:i:s');
		if($status=="rsp" || $status=="ppay")  $active =1;		
      

	   $sql = "UPDATE $table_name SET status='{$status}', active={$active}, read_date='{$read_date}', tc='{$tc}' WHERE sid='{$sid}' AND active=1";

		$stmt = $this->db->query($sql);
		//$stmt->bindParam(':date_', $$date_limit);
	  
	   return $stmt > 0;
    }

    public function efb_code_validate_select($sid ,$fid) {
		
		
		 
		$table_name = $this->db->prefix . 'emsfb_stts_';
        $date_limit = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $date_now = date('Y-m-d H:i:s');
        $query =$this->db->prepare("SELECT COUNT(*) FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 AND fid = %s", $sid, $date_now,$fid);
		
        $result =$this->db->get_var($query);
		
        return $result === '1';
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
			error_log('Easy Form Builder: SMS Addon is not installed');
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
		//error_log('EFB=>setting_version_efb_update: ' . $pro);     
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
		if($pro == true || $pro ==1){			
			$this->download_all_addons_efb();	
			$end_time = microtime(true);
			$execution_time = ($end_time - $start_time);
			//error_log('EFB=>setting_version_efb_update: ' . $execution_time);
			$request_uri = $_SERVER['REQUEST_URI'];			
		    if(isset($request_uri)==true && strpos($request_uri, 'Emsfb') == false ){			
				//error_log('if execution_time>2');
				wp_safe_redirect($_SERVER['REQUEST_URI']);
				exit;
			}else{
				//error_log('else execution_time>2');
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
            
            error_log('recaptcha google URL is not accessible.');
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
}
