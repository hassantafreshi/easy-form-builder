<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class efbFunction {

    protected static $req_cache = [];

    protected static $cached_settings = null;
    protected static $cached_lang = null;

    public function invalidate_settings_cache($old, $new, $option) {
        wp_cache_delete('settings:decoded', 'efb');
        delete_transient('emsfb_settings_transient');
        update_option('emsfb_text_version', time());
    }

    private function detect_current_lang_slug() {
        if (function_exists('icl_object_id') && defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }
        if (function_exists('pll_current_language')) {
            $pll = pll_current_language('slug');
            if (!empty($pll)) return $pll;
        }
        return function_exists('get_locale') ? get_locale() : 'en_US';
    }

    private function get_text_version($settingsObj) {
        $v = get_option('emsfb_text_version', 0);
        if (!empty($v)) return (string)$v;
        $raw = '';
        if (is_object($settingsObj) && isset($settingsObj->text) && is_object($settingsObj->text)) {
            $raw = json_encode($settingsObj->text);
        }
        return substr(md5((string)$raw), 0, 12);
    }

	protected static $lang_cache = [];
	private const EFB_LANG_CACHE_TTL = 21600;

	protected $db;

	public function __construct() {

		if (function_exists('add_action')) {
			add_action('update_option_emsfb_settings', [ $this, 'invalidate_lang_cache_on_settings_update' ], 10, 2);
		}

		global $wpdb;
		$this->db = $wpdb;

		register_activation_hook( __FILE__, [$this ,'download_all_addons_efb'] );
		add_action( 'load-index.php', [$this ,'addon_adds_cron_efb'] );

		add_action( 'emsfb_download_addons_cron', [$this, 'download_all_addons_efb'] );

    }

	public function text_efb($inp,$page_request = 'default') {

         if (static::$cached_settings === null) {
            static::$cached_settings = get_setting_Emsfb();
            static::$cached_lang = $this->detect_current_lang_slug();
        }
        $ac = static::$cached_settings;
        $efb_lang = static::$cached_lang;
        $efb_needX    = ($inp === 1);
        $efb_ver      = $this->get_text_version($ac);

        $efb_subset   = 'all';
        if (is_array($inp)) {
            $tmp = array_values(array_unique($inp));
            sort($tmp);
            $efb_subset = 'subset:' . substr(md5(json_encode($tmp)), 0, 12);
        } elseif ($efb_needX) {
            $efb_subset = 'with-extra';
        }

        $efb_ck_final = "langfinal:$efb_lang:$efb_ver:$efb_subset:$page_request";

        if (isset(self::$req_cache[$efb_ck_final])) {
            return self::$req_cache[$efb_ck_final];
        }
        $efb_cached_final = wp_cache_get($efb_ck_final, 'efb');
        if ($efb_cached_final !== false) {
            self::$req_cache[$efb_ck_final] = $efb_cached_final;
            return $efb_cached_final;
        }

		$ac= get_setting_Emsfb();
		$state= $ac!=='null' && isset($ac->text) && gettype($ac->text)!='string' ? true : false ;
		$s= 'easy-form-builder';
		$lang = [

			/* translators: Create = to make or build form */
			"create" => $state ? $ac->text->create : esc_html__('Create','easy-form-builder'),
			"define" => $state ? $ac->text->define : esc_html__('Define','easy-form-builder'),
			"formName" => $state ? $ac->text->formName : esc_html__('Form Name','easy-form-builder'),
			"createDate" => $state ? $ac->text->createDate : esc_html__('Create Date','easy-form-builder'),
			"edit" => $state ? $ac->text->edit : esc_html__('Edit','easy-form-builder'),
			"content" => $state ? $ac->text->content : esc_html__('Content','easy-form-builder'),
			"trackNo" => $state ? $ac->text->trackNo : esc_html__('Confirmation Code','easy-form-builder'),
			"formDate" => $state ? $ac->text->formDate : esc_html__('Form Date','easy-form-builder'),
			/* translators: By = submitted by/created by (indicating author/creator) */
			"by" => $state ? $ac->text->by : esc_html__('By','easy-form-builder'),
			/* translators: IP = Internet Protocol address */
			"ip" => $state ? $ac->text->ip : esc_html__('IP','easy-form-builder'),
			/* translators: Guest = user who is not logged in */
			"guest" => $state ? $ac->text->guest : esc_html__('Guest','easy-form-builder'),
			/* translators: Response = reply or answer to a form submission */
			"response" => $state ? $ac->text->response : esc_html__('Response','easy-form-builder'),
			/* translators: Date Picker = calendar widget for selecting dates */
			"date" => $state ? $ac->text->date : esc_html__('Date Picker','easy-form-builder'),
			/* translators: Video Download Link = link to download a video file */
			"videoDownloadLink" => $state ? $ac->text->videoDownloadLink : esc_html__('Video Download','easy-form-builder'),
			/* translators: Download Video = action to save a video file locally */
			"downloadViedo" => $state ? $ac->text->downloadViedo : esc_html__('Download Video','easy-form-builder'),
			"download" => $state ? $ac->text->download : esc_html__('Download','easy-form-builder'),
			"youCantUseHTMLTagOrBlank" => $state ? $ac->text->youCantUseHTMLTagOrBlank : esc_html__('Please avoid using HTML tags and ensure that your message is not blank.','easy-form-builder'),
			/* translators: Reply = label for replying to a message */
			"reply" => $state ? $ac->text->reply : esc_html__('Reply','easy-form-builder'),
			/* translators: Messages = plural of message, multiple communications */
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

			"alertEmail" => $state ? $ac->text->alertEmail : esc_html__('Alert Email','easy-form-builder'),
			/* translators: Enter Admin Email = input field for administrator's email address */
			"enterAdminEmail" => $state ? $ac->text->enterAdminEmail : esc_html__('Enter the admin email address to receive email notifications.','easy-form-builder'),
			/* translators: Confirmation Code = unique code for tracking form submissions */
			"showTrackingCode" => $state ? $ac->text->showTrackingCode : esc_html__('Show Confirmation Code','easy-form-builder'),
			/* translators: Confirmation Code Finder = tool to locate confirmation codes [shortcode] */
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : esc_html__('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : esc_html__('Copy and paste this shortcode to add the confirmation code finder to any page or post.','easy-form-builder'),
			"save" => $state ? $ac->text->save : esc_html__('Save','easy-form-builder'),
			"waiting" => $state ? $ac->text->waiting : esc_html__('Waiting','easy-form-builder'),
			"saved" => $state ? $ac->text->saved : esc_html__('Saved','easy-form-builder'),
			/* translators: Step Name = name of a step in a multi-step form */
			"stepName" => $state ? $ac->text->stepName : esc_html__('Step Name','easy-form-builder'),
			/* translators: Icon of Step = graphical symbol representing a step in a multi-step form */
			"IconOfStep" => $state ? $ac->text->IconOfStep : esc_html__('Icon of step','easy-form-builder'),
			/* translators: Step Titles = titles of steps in a multi-step form */
			"stepTitles" => $state ? $ac->text->stepTitles : esc_html__('Step Titles','easy-form-builder'),
			/* translators: Elements = components or tags of a form */
			"elements" => $state ? $ac->text->elements : esc_html__('Elements:','easy-form-builder'),
			"delete" => $state ? $ac->text->delete : esc_html__('Delete','easy-form-builder'),
			/* translators: New Option = label for adding a new option to a form field */
			"newOption" => $state ? $ac->text->newOption : esc_html__('New option','easy-form-builder'),
			/* translators: Required = field must be filled out */
			"required" => $state ? $ac->text->required : esc_html__('Required','easy-form-builder'),
			/* translators: Text = label for a input field for text */
			"button" => $state ? $ac->text->button : esc_html__('Text','easy-form-builder'),
			/* translators: Password = input field for password */
			"password" => $state ? $ac->text->password : esc_html__('Password','easy-form-builder'),
			/* translators: Email = input field for email address */
			"email" => $state ? $ac->text->email : esc_html__('Email','easy-form-builder'),
			/* translators: Number = input field for numeric values */
			"number" => $state ? $ac->text->number : esc_html__('Number','easy-form-builder'),
			/* translators: File = input field for uploading files */
			"file" => $state ? $ac->text->file : esc_html__('File upload','easy-form-builder'),
			/* translators: Tel = Telephone/Phone number */
			"tel" => $state ? $ac->text->tel : esc_html__('Tel','easy-form-builder'),
			/* translators: Textarea = input field for text[textarea] */
			"textarea" => $state ? $ac->text->textarea : esc_html__('Longer Text','easy-form-builder'),
			/* translators: Checkbox = input field for selecting options */
			"checkbox" => $state ? $ac->text->checkbox : esc_html__('Check Box','easy-form-builder'),
			/* translators: Radio Button = input field for selecting one option from many */
			"radiobutton" => $state ? $ac->text->radiobutton : esc_html__('Radio Button','easy-form-builder'),
			/* translators: Radio = input field for selecting one option from many */
			"radio" => $state ? $ac->text->radio : esc_html__('Radio','easy-form-builder'),
			/* translators: URL = Website address/link */
			"url" => $state ? $ac->text->url : esc_html__('URL','easy-form-builder'),
			/* translators: Range = input field for selecting a value within a range */
			"range" => $state ? $ac->text->range : esc_html__('Range','easy-form-builder'),
			/* translators: Color Picker = input field for selecting a color */
			"color" => $state ? $ac->text->color : esc_html__('Color Picker','easy-form-builder'),
			/* translators: File Type = type of file allowed for upload (e.g., jpg, png, pdf) */
			"fileType" => $state ? $ac->text->fileType : esc_html__('File Type','easy-form-builder'),
			/* translators: Label = text label for a form element */
			"label" => $state ? $ac->text->label : esc_html__('Label','easy-form-builder'),
			"labels" => $state ? $ac->text->labels : esc_html__('Labels','easy-form-builder'),
			/* translators: Class = CSS class for styling */
			"class" => $state ? $ac->text->class : esc_html__('Class','easy-form-builder'),
			/* translators: ID = Identifier */
			"id" => $state ? $ac->text->id : esc_html__('ID','easy-form-builder'),
			/* translators: Tooltip = small popup text that appears when hovering over an element */
			"tooltip" => $state ? $ac->text->tooltip : esc_html__('Tooltip','easy-form-builder'),
			/* translators: Congratulations/success message */
			"goodJob" => $state ? $ac->text->goodJob : esc_html__('Good Job','easy-form-builder'),
			"formUpdatedDone" => $state ? $ac->text->formUpdatedDone : esc_html__('The form has been successfully updated','easy-form-builder'),
			"formIsBuild" => $state ? $ac->text->formIsBuild : esc_html__('The form is successfully built','easy-form-builder'),
			/* translators: Form Code = code snippet representing the form */
			"formCode" => $state ? $ac->text->formCode : esc_html__('Form Code','easy-form-builder'),
			"close" => $state ? $ac->text->close : esc_html__('Close','easy-form-builder'),
			"done" => $state ? $ac->text->done : esc_html__('Done','easy-form-builder'),
			/* translators: Please fill in all required fields = message prompting the user to complete mandatory fields */
			"pleaseFillInRequiredFields" => $state ? $ac->text->pleaseFillInRequiredFields : esc_html__('Please fill in all required fields.','easy-form-builder'),
			/* translators: Available in Pro version = message indicating a feature is only available in the Pro version */
			"availableInProversion" => $state ? $ac->text->availableInProversion : esc_html__('This option is only available in the Pro version.','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => $state ? $ac->text->ifYouNeedCreateMoreThan2Steps : esc_html__('If you need to create more than 2 steps, you can activate the pro version of Easy Form Builder, which allows for unlimited steps.','easy-form-builder'),
			"preview" => $state ? $ac->text->preview : esc_html__('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => $state ? $ac->text->somethingWentWrongPleaseRefresh : esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder'),
			"allowMultiselect" => $state ? $ac->text->allowMultiselect : esc_html__('Allow multi-select','easy-form-builder'),
			"selectOpetionDisabled" => $state ? $ac->text->selectOpetionDisabled : esc_html__('Choose an option (not available in test view)','easy-form-builder'),
			"pleaseEnterTheTracking" => $state ? $ac->text->pleaseEnterTheTracking : esc_html__('Please enter the Confirmation Code','easy-form-builder'),
			"formNotFound" => $state ? $ac->text->formNotFound : esc_html__('Form not found.','easy-form-builder'),
			/* translators: V01 = Validation error code 01 */
			"errorV01" => $state ? $ac->text->errorV01 : esc_html__('Oops, V01 Error occurred.','easy-form-builder'),
			"password8Chars" => $state ? $ac->text->password8Chars : esc_html__('Password should be at least 8 characters long.','easy-form-builder'),
			"registered" => $state ? $ac->text->registered : esc_html__('Registered','easy-form-builder'),
			"yourInformationRegistered" => $state ? $ac->text->yourInformationRegistered : esc_html__('Your information is successfully registered','easy-form-builder'),
			"youNotPermissionUploadFile" => $state ? $ac->text->youNotPermissionUploadFile : esc_html__('You do not have permission to upload this file:','easy-form-builder'),
			/* translators: NN is the file type (e.g., image, document, PDF) */
			"pleaseUploadA" => $state ? $ac->text->pleaseUploadA : esc_html__('Please upload NN file','easy-form-builder'),
			"please" => $state ? $ac->text->please : esc_html__('Please','easy-form-builder'),
			"trackingForm" => $state ? $ac->text->trackingForm : esc_html__('Tracking Form','easy-form-builder'),
			"trackingCodeIsNotValid" => $state ? $ac->text->trackingCodeIsNotValid : esc_html__('The confirmation Code is not valid.','easy-form-builder'),
			/* translators: Instruction to check the reCAPTCHA checkbox - 'I am not a robot' */
			"checkedBoxIANotRobot" => $state ? $ac->text->checkedBoxIANotRobot : esc_html__('Please check the box of I am Not robot','easy-form-builder'),
			"howConfigureEFB" => $state ? $ac->text->howConfigureEFB : esc_html__('How to configure Easy Form Builder','easy-form-builder'),

			"howGetGooglereCAPTCHA" => $state ? $ac->text->howGetGooglereCAPTCHA : esc_html__('How to get Google reCAPTCHA and implement it into Easy Form Builder','easy-form-builder'),
			"howActivateAlertEmail" => $state ? $ac->text->howActivateAlertEmail : esc_html__('How to activate the alert email for new form submission','easy-form-builder'),
			"howCreateAddForm" => $state ? $ac->text->howCreateAddForm : esc_html__('How to create and add a form with Easy Form Builder','easy-form-builder'),
			"howActivateTracking" => $state ? $ac->text->howActivateTracking : esc_html__('How to activate a Confirmation Code in Easy Form Builder','easy-form-builder'),
			"howWorkWithPanels" => $state ? $ac->text->howWorkWithPanels : esc_html__('How to work with panels in Easy Form Builder','easy-form-builder'),
			/* translators: Points = score/rating points */
			"points" => $state ? $ac->text->points : esc_html__('points','easy-form-builder'),
			"howAddTrackingForm" => $state ? $ac->text->howAddTrackingForm : esc_html__('How to add The Confirmation Code Finder to a post, page, or custom post type','easy-form-builder'),
			"howFindResponse" => $state ? $ac->text->howFindResponse : esc_html__('How to find a specific submission using the Confirmation Code','easy-form-builder'),
			"pleaseEnterVaildValue" => $state ? $ac->text->pleaseEnterVaildValue : esc_html__('Please enter a valid value','easy-form-builder'),
			"step" => $state ? $ac->text->step : esc_html__('Step','easy-form-builder'),
			"advancedCustomization" => $state ? $ac->text->advancedCustomization : esc_html__('Advanced customization','easy-form-builder'),
			"orClickHere" => $state ? $ac->text->orClickHere : esc_html__(' or click here','easy-form-builder'),
			/* translators: CSV = Comma-Separated Values - a spreadsheet file format */
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
			"proUnlockMsg" => $state ? $ac->text->proUnlockMsg : esc_html__('Activate Pro version for more features and unlimited access to all plugin services.','easy-form-builder'),
			/* translators: API = Application Programming Interface - a code that allows software to communicate */
			"aPIKey" => $state ? $ac->text->aPIKey : esc_html__('API KEY','easy-form-builder'),
			"youNeedAPIgMaps" => $state ? $ac->text->youNeedAPIgMaps : esc_html__('Your form needs an API key for Google Maps to work properly.','easy-form-builder'),
			"copiedClipboard" => $state ? $ac->text->copiedClipboard : esc_html__('Copied to Clipboard','easy-form-builder'),
			"noResponse" => $state ? $ac->text->noResponse : esc_html__('No Response','easy-form-builder'),
			"offerGoogleCloud" => $state ? $ac->text->offerGoogleCloud : esc_html__('To use reCAPTCHA and location picker (Maps), sign up for the Google Cloud service and receive $350 worth of credits exclusively for our users ','easy-form-builder'),
			"getOfferTextlink" => $state ? $ac->text->getOfferTextlink : esc_html__(' Get credits by clicking here.','easy-form-builder'),
			"clickHere" => $state ? $ac->text->clickHere : esc_html__('Click here','easy-form-builder'),
			"SpecialOffer" => $state ? $ac->text->SpecialOffer : esc_html__('Special offer','easy-form-builder'),
			"googleKeys" => $state ? $ac->text->googleKeys : esc_html__('Google Keys','easy-form-builder'),
			/* translators: Captchas = tab name in the settings panel for configuring CAPTCHA services */
			"captchas" => $state && isset($ac->text->captchas) ? $ac->text->captchas  : esc_html__('Captchas','easy-form-builder'),
			"emailServer" => $state ? $ac->text->emailServer : esc_html__('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : esc_html__('Use this test to check if your server can send emails properly.','easy-form-builder'),
			"emailSetting" => $state ? $ac->text->emailSetting : esc_html__('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : esc_html__('Check Email Server','easy-form-builder'),
			/* translators: D&D means Drag and Drop */
			"dadfile" => $state ? $ac->text->dadfile : esc_html__('D&D File Upload','easy-form-builder'),
			"field" => $state ? $ac->text->field : esc_html__('Field','easy-form-builder'),
			"advanced" => $state ? $ac->text->advanced : esc_html__('Advanced','easy-form-builder'),
			"switch" => $state ? $ac->text->switch : esc_html__('Switch','easy-form-builder'),
			"locationPicker" => $state ? $ac->text->locationPicker : esc_html__('Location Picker','easy-form-builder'),
			"rating" => $state ? $ac->text->rating : esc_html__('Rating','easy-form-builder'),
			"esign" => $state ? $ac->text->esign : esc_html__('Signature','easy-form-builder'),
			"yesNo" => $state ? $ac->text->yesNo : esc_html__('Yes/No','easy-form-builder'),
			/* translators: HTML = HyperText Markup Language - code for web pages */
			"htmlCode" => $state ? $ac->text->htmlCode : esc_html__('HTML Code','easy-form-builder'),
			/* translators: Desktop = computer/PC view (as opposed to mobile/tablet) */
			"pcPreview" => $state ? $ac->text->pcPreview : esc_html__('Desktop Preview','easy-form-builder'),
			"youDoNotAddAnyInput" => $state ? $ac->text->youDoNotAddAnyInput : esc_html__('You have not added any fields.','easy-form-builder'),
			"copyShortcode" => $state ? $ac->text->copyShortcode : esc_html__('Copy ShortCode','easy-form-builder'),
			/* translators: ShortCode = a WordPress code snippet inserted in brackets like [form id=1] */
			"shortcode" => $state ? $ac->text->shortcode : esc_html__('ShortCode','easy-form-builder'),
			"copyTrackingcode" => $state ? $ac->text->copyTrackingcode : esc_html__('Copy Confirmation Code','easy-form-builder'),
			"previewForm" => $state ? $ac->text->previewForm : esc_html__('Preview Form','easy-form-builder'),
			"activateProVersion" => $state ? $ac->text->activateProVersion : esc_html__('Upgrade to Pro','easy-form-builder'),
			"itAppearedStepsEmpty" => $state ? $ac->text->itAppearedStepsEmpty : esc_html__('It seems that some of the steps in your form are empty. Please add a field to all steps before saving.','easy-form-builder'),
			/* translators: Message shown when user tries to use Pro features without activating Pro version */
			"youUseProElements" => $state ? $ac->text->youUseProElements : esc_html__('You are using the pro field in the form. To save and use the form including pro fields, activate Pro.','easy-form-builder'),
			"sampleDescription" => $state ? $ac->text->sampleDescription : esc_html__('Sample description','easy-form-builder'),
			/* translators: Pro version = Premium/paid version of the plugin */
			"fieldAvailableInProversion" => $state ? $ac->text->fieldAvailableInProversion : esc_html__('This feature is only available in the Pro version of Easy Form Builder.','easy-form-builder'),
			"editField" => $state ? $ac->text->editField : esc_html__('Edit Field','easy-form-builder'),
			"description" => $state ? $ac->text->description : esc_html__('Description','easy-form-builder'),
			"descriptions" => $state ? $ac->text->descriptions : esc_html__('Descriptions','easy-form-builder'),
			"thisEmailNotificationReceive" => $state ? $ac->text->thisEmailNotificationReceive : esc_html__('Enable email notifications','easy-form-builder'),
			"activeTrackingCode" => $state ? $ac->text->activeTrackingCode : esc_html__('Show Confirmation Code','easy-form-builder'),
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : esc_html__('Add Google reCAPTCHA to the form ','easy-form-builder'),
			"dontShowIconsStepsName" => $state ? $ac->text->dontShowIconsStepsName : esc_html__('Hide icons and step names.','easy-form-builder'),
			"dontShowProgressBar" => $state ? $ac->text->dontShowProgressBar : esc_html__('Hide progress bar','easy-form-builder'),
			/* translators: Private form = form visible only to logged-in users */
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
			/* translators: Mobile Width = width of element on mobile devices */
			"swidth" => $state && isset($ac->text->swidth) ? $ac->text->swidth : esc_html__('%s width','easy-form-builder'),
			/* translators: %s = context prefix (e.g. Mobile/Desktop). Label Position = position of field label */
			"slabelPosition" => $state && isset($ac->text->slabelPosition) ? $ac->text->slabelPosition : esc_html__('%s Label Position','easy-form-builder'),
			/* translators: %s = context prefix (e.g. Mobile/Desktop). Label size = font size of field label */
			"slabelSize" => $state && isset($ac->text->slabelSize) ? $ac->text->slabelSize : esc_html__('%s Label size','easy-form-builder'),
			/* translators: %s = context prefix (e.g. Mobile/Desktop). Label Align = text alignment of field label */
			"slabelAlign" => $state && isset($ac->text->slabelAlign) ? $ac->text->slabelAlign : esc_html__('%s Label | Align','easy-form-builder'),
			/* translators: %s = context prefix (e.g. Mobile/Desktop). Description Align = text alignment of field description */
			"sdescAlign" => $state && isset($ac->text->sdescAlign) ? $ac->text->sdescAlign : esc_html__('%s Description | Align','easy-form-builder'),
			/* translators: Desktop = computer/PC view */
			"desktop" => $state && isset($ac->text->desktop) ? $ac->text->desktop : esc_html__('Desktop','easy-form-builder'),
			/* translators: Mobile = mobile phone view */
			"mobileView" => $state && isset($ac->text->mobileView) ? $ac->text->mobileView : esc_html__('Mobile','easy-form-builder'),
			/* translators: CSS = Cascading Style Sheets - used for styling/design */
			"cSSClasses" => $state ? $ac->text->cSSClasses : esc_html__('CSS Classes','easy-form-builder'),
			"defaultValue" => $state ? $ac->text->defaultValue : esc_html__('Default value','easy-form-builder'),
			"placeholder" => $state ? $ac->text->placeholder : esc_html__('Placeholder','easy-form-builder'),
			"enterAdminEmailReceiveNoti" => $state ? $ac->text->enterAdminEmailReceiveNoti : esc_html__('Enter email address to receive notifications.','easy-form-builder'),
			/* translators: Corners = button/element corner style (rounded or square) */
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
			/* translators: %s Checked Color = color of checked radio/checkbox elements, %s is replaced with field type name */
			"checkedClr" => $state && isset($ac->text->checkedClr) ? $ac->text->checkedClr : esc_html__('%s Checked Color','easy-form-builder'),
			/* translators: Range Thumb range slider button/thumb */
			"rangeThumb" => $state && isset($ac->text->rangeThumb) ? $ac->text->rangeThumb : esc_html__('Slider Button','easy-form-builder'),
			/* translators: Range Value  range slider value */
			"rangeValue" => $state && isset($ac->text->rangeValue) ? $ac->text->rangeValue : esc_html__('Value Text','easy-form-builder'),
			/* translators: Switch %s = dynamic switch label, %s is replaced with On/Off/Handle */
			"switchs" => $state && isset($ac->text->switchs) ? $ac->text->switchs : esc_html__('Switch %s','easy-form-builder'),
			/* translators: Handle = the toggle button/thumb of switch */
			"handle" => $state && isset($ac->text->handle) ? $ac->text->handle : esc_html__('Handle','easy-form-builder'),

			/* translators: %s Color = color of the field element, %s is replaced with field type name */
			"scolor" => $state && isset($ac->text->scolor) ? $ac->text->scolor : esc_html__('%s color','easy-form-builder'),

			"borderColor" => $state ? $ac->text->borderColor : esc_html__('Border Color','easy-form-builder'),
			"height" => $state ? $ac->text->height : esc_html__('Height','easy-form-builder'),
			"name" => $state ? $ac->text->name : esc_html__('Name','easy-form-builder'),
			"latitude" => $state ? $ac->text->latitude : esc_html__('Latitude','easy-form-builder'),
			"longitude" => $state ? $ac->text->longitude : esc_html__('Longitude','easy-form-builder'),
			/* translators: e.g. = for example (from Latin exempli gratia) */
			"exDot" => $state ? $ac->text->exDot : esc_html__('e.g.','easy-form-builder'),
			"pleaseDoNotAddJsCode" => $state ? $ac->text->pleaseDoNotAddJsCode : esc_html__('(Avoid adding JavaScript or jQuery codes to HTML for security reasons.)','easy-form-builder'),
			"button1Value" => $state ? $ac->text->button1Value : esc_html__('Button 1 value','easy-form-builder'),
			"button2Value" => $state ? $ac->text->button2Value : esc_html__('Button 2 value','easy-form-builder'),
			"iconList" => $state ? $ac->text->iconList : esc_html__('Icons list','easy-form-builder'),
			"previous" => $state ? $ac->text->previous : esc_html__('Previous','easy-form-builder'),
			"next" => $state ? $ac->text->next : esc_html__('Next','easy-form-builder'),
			"noCodeAddedYet" => $state ? $ac->text->noCodeAddedYet : esc_html__('The code has not yet been added. Click on','easy-form-builder'),
			"andAddingHtmlCode" => $state ? $ac->text->andAddingHtmlCode : esc_html__('and adding HTML code.','easy-form-builder'),

			/* translators: Essential Features = basic/core features of the plugin */
			"essentialFeatures" => $state && isset($ac->text->essentialFeatures) ? $ac->text->essentialFeatures : esc_html__('Essential Features','easy-form-builder'),
			/* translators: Getting started message */
			"perfectForGettingStarted" => $state && isset($ac->text->perfectForGettingStarted) ? $ac->text->perfectForGettingStarted : esc_html__('Perfect for getting started with simple forms.','easy-form-builder'),
			/* translators: Core form fields = basic input fields like text, email, etc */
			"coreFormFields" => $state && isset($ac->text->coreFormFields) ? $ac->text->coreFormFields : esc_html__('Core form fields','easy-form-builder'),
			/* translators: Email notifications = automatic email alerts */
			"emailNotifications" => $state && isset($ac->text->emailNotifications) ? $ac->text->emailNotifications : esc_html__('Email notifications','easy-form-builder'),
			/* translators: Advanced form fields = complex input types like file upload, date picker */
			"advancedFormFields" => $state && isset($ac->text->advancedFormFields) ? $ac->text->advancedFormFields : esc_html__('Advanced form fields','easy-form-builder'),
			/* translators: Built-in advanced features = integrated advanced functionality */
			"builtInAdvancedFeatures" => $state && isset($ac->text->builtInAdvancedFeatures) ? $ac->text->builtInAdvancedFeatures : esc_html__('Built-in advanced features','easy-form-builder'),
			/* translators: Add-ons & extensions = additional plugins or modules */
			"addonsExtensions" => $state && isset($ac->text->addonsExtensions) ? $ac->text->addonsExtensions : esc_html__('Add-ons & extensions','easy-form-builder'),
			/* translators: Start with Free = button text for free plan */
			"startWithFree" => $state && isset($ac->text->startWithFree) ? $ac->text->startWithFree : esc_html__('Start with Free','easy-form-builder'),
			/* translators: Free Plus = plan name for enhanced free version */
			"freePlus" => $state && isset($ac->text->freePlus) ? $ac->text->freePlus : esc_html__('Free Plus','easy-form-builder'),
			/* translators: Pro Pending = label for pending professional plan */
			"proPending" => $state && isset($ac->text->proPending) ? $ac->text->proPending : esc_html__('Pro Pending','easy-form-builder'),
			/* translators: Recommended = label for suggested plan */
			"recommended" => $state && isset($ac->text->recommended) ? $ac->text->recommended : esc_html__('Recommended','easy-form-builder'),
			/* translators: Best Value = label indicating the best price/value ratio */
			"bestValue" => $state && isset($ac->text->bestValue) ? $ac->text->bestValue : esc_html__('Best Value','easy-form-builder'),
			/* translators: Unlock advanced features message */
			"unlockAdvancedFeatures" => $state && isset($ac->text->unlockAdvancedFeatures) ? $ac->text->unlockAdvancedFeatures : esc_html__('Unlock advanced features - supported by a credit line.','easy-form-builder'),
			/* translators: Core & advanced form fields = both basic and complex input types */
			"coreAdvancedFormFields" => $state && isset($ac->text->coreAdvancedFormFields) ? $ac->text->coreAdvancedFormFields : esc_html__('Core & advanced form fields','easy-form-builder'),
			/* translators: Powered by credit line message */
			"poweredByCredit" => $state && isset($ac->text->poweredByCredit) ? $ac->text->poweredByCredit : esc_html__('Lightweight “Powered by Easy Form Builder” credit & link','easy-form-builder'),
			/* translators: Continue with Free Plus = button text */
			"continueWithFreePlus" => $state && isset($ac->text->continueWithFreePlus) ? $ac->text->continueWithFreePlus : esc_html__('Continue with Free Plus','easy-form-builder'),
			/* translators: Pro = professional/premium plan name */
			"pro" => $state && isset($ac->text->pro) ? $ac->text->pro : esc_html__('Pro','easy-form-builder'),
			/* translators: Advanced = plan features description */
			"advancedAdFree" => $state && isset($ac->text->advancedAdFree) ? $ac->text->advancedAdFree : esc_html__('Advanced','easy-form-builder'),
			/* translators: free = no-cost plan */
			"free" => $state && isset($ac->text->free) ? $ac->text->free : esc_html__('Free','easy-form-builder'),
			/* translators: Complete clean experience message */
			"completeCleanExperience" => $state && isset($ac->text->completeCleanExperience) ? $ac->text->completeCleanExperience : esc_html__('For professionals who want the complete, clean experience.','easy-form-builder'),
			/* translators: Everything in Free Plus = includes all features from lower plan */
			"everythingInFreePlus" => $state && isset($ac->text->everythingInFreePlus) ? $ac->text->everythingInFreePlus : esc_html__('Everything in Free Plus','easy-form-builder'),
			/* translators: Advanced integrations = complex third-party connections */
			"advancedIntegrations" => $state && isset($ac->text->advancedIntegrations) ? $ac->text->advancedIntegrations : esc_html__('Advanced integrations','easy-form-builder'),
			/* translators: Add-ons included = extensions are part of the package */
			"addonsIncluded" => $state && isset($ac->text->addonsIncluded) ? $ac->text->addonsIncluded : esc_html__('Add-ons included','easy-form-builder'),
			/* translators: No promotional messages = ad-free experience */
			"noCreditsPromo" => $state && isset($ac->text->noCreditsPromo) ? $ac->text->noCreditsPromo : esc_html__('No credits or promotional messages','easy-form-builder'),
			/* translators: Premium experience = high-quality, professional experience */
			"premiumExperience" => $state && isset($ac->text->premiumExperience) ? $ac->text->premiumExperience : esc_html__('premium experience','easy-form-builder'),
			/* translators: Upgrade to Pro = button text for upgrading */
			"upgradeToPro" => $state && isset($ac->text->upgradeToPro) ? $ac->text->upgradeToPro : esc_html__('Upgrade to Pro','easy-form-builder'),
			/* translators: Most Popular = label indicating most chosen plan */
			"mostPopular" => $state && isset($ac->text->mostPopular) ? $ac->text->mostPopular : esc_html__('Most Popular','easy-form-builder'),
			/* translators: Information about plan changeability */
			"canChangeAnytime" => $state && isset($ac->text->canChangeAnytime) ? $ac->text->canChangeAnytime : esc_html__('You can change this at any time from the panel settings menu. No data is lost when you upgrade.','easy-form-builder'),
			/* translators: Maybe later = postpone action button */
			"maybeLater" => $state && isset($ac->text->maybeLater) ? $ac->text->maybeLater : esc_html__('Maybe later','easy-form-builder'),
			/* translators: Build professional forms message */
			"buildProfessionalForms" => $state && isset($ac->text->buildProfessionalForms) ? $ac->text->buildProfessionalForms : esc_html__('Build professional WordPress forms in minutes. Choose how you\'d like to get started.','easy-form-builder'),

			/* translators: Selected = indicates something has been chosen */
			"selected" => $state && isset($ac->text->selected) ? $ac->text->selected : esc_html__('selected','easy-form-builder'),
			/* translators: Setup reminder message */
			"setupReminder" => $state && isset($ac->text->setupReminder) ? $ac->text->setupReminder : esc_html__('You can access setup from plugin settings anytime.','easy-form-builder'),
			/* translators: Welcome modal title for new users */
			"welcomeToEasyFormBuilder" => $state && isset($ac->text->welcomeToEasyFormBuilder) ? $ac->text->welcomeToEasyFormBuilder : esc_html__('Welcome to Easy Form Builder','easy-form-builder'),
			/* translators: Pro plan redirect confirmation message */
			"proRedirectMessage" => $state && isset($ac->text->proRedirectMessage) ? $ac->text->proRedirectMessage : esc_html__('You will be redirected to the Pro plan purchase page. Continue?','easy-form-builder'),


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
			"browseFile" => $state ? $ac->text->browseFile : esc_html__('Browse the file','easy-form-builder'),
			"removeTheFile" => $state ? $ac->text->removeTheFile : esc_html__('Remove the file','easy-form-builder'),
			"enterAPIKey" => $state ? $ac->text->enterAPIKey : esc_html__('Enter API KEY','easy-form-builder'),
			"formSetting" => $state ? $ac->text->formSetting : esc_html__('Form Settings','easy-form-builder'),
			"select" => $state ? $ac->text->select : esc_html__('Select','easy-form-builder'),
			"up" => $state ? $ac->text->up : esc_html__('Up','easy-form-builder'),
			"sending" => $state ? $ac->text->sending : esc_html__('Sending','easy-form-builder'),
			"enterYourMessage" => $state ? $ac->text->enterYourMessage : esc_html__('Please enter your message','easy-form-builder'),
			"add" => $state ? $ac->text->add : esc_html__('Add','easy-form-builder'),
			"code" => $state ? $ac->text->code : esc_html__('Code','easy-form-builder'),
			"star" => $state ? $ac->text->star : esc_html__('Star','easy-form-builder'),
			"form" => $state ? $ac->text->form : esc_html__('Form','easy-form-builder'),
			"black" => $state ? $ac->text->black : esc_html__('Black','easy-form-builder'),
			"pleaseReporProblem" => $state ? $ac->text->pleaseReporProblem : esc_html__('Please kindly report the following issue to the Easy Form Builder team.','easy-form-builder'),
			"reportProblem" => $state ? $ac->text->reportProblem : esc_html__('Report problem','easy-form-builder'),
			"ddate" => $state ? $ac->text->ddate : esc_html__('Date','easy-form-builder'),
			"serverEmailAble" => $state ? $ac->text->serverEmailAble : esc_html__('Your server is capable of sending emails','easy-form-builder'),
			/* translators: SMTP = Simple Mail Transfer Protocol - email sending method */
			"sMTPNotWork" => $state ? $ac->text->sMTPNotWork : esc_html__('SMTP Error: The host is unable to send an email. Please contact the host support team for assistance.','easy-form-builder'),

			"aPIkeyGoogleMapsFeild" => $state ? $ac->text->aPIkeyGoogleMapsFeild : esc_html__('There was an error loading Maps.','easy-form-builder'),
			"fileIsNotRight" => $state ? $ac->text->fileIsNotRight : esc_html__('The uploaded file is not in the correct file format.','easy-form-builder'),
			"thisElemantNotAvailable" => $state ? $ac->text->thisElemantNotAvailable : esc_html__('The selected field is not available in this type of form.','easy-form-builder'),
			"clickHereGetActivateCode" => $state ? $ac->text->clickHereGetActivateCode : esc_html__('Get your activation code now and unlock exclusive features ! Click here.','easy-form-builder'),
			/* translators: Confirmation Code is a unique identifier and after filling the form, users receive this code to track their submission */
			"trackingCode" => $state ? $ac->text->trackingCode : esc_html__('Confirmation Code','easy-form-builder'),
			"text" => $state ? $ac->text->text : esc_html__('Text','easy-form-builder'),
			"multiselect" => $state ? $ac->text->multiselect : esc_html__('Multiple Select','easy-form-builder'),
			"newForm" => $state ? $ac->text->newForm : esc_html__('New Form','easy-form-builder'),
			"registerForm" => $state ? $ac->text->registerForm : esc_html__('Register Form','easy-form-builder'),
			"loginForm" => $state ? $ac->text->loginForm : esc_html__('Login Form','easy-form-builder'),
			"subscriptionForm" => $state ? $ac->text->subscriptionForm : esc_html__('Subscription Form','easy-form-builder'),
			"supportForm" => $state ? $ac->text->supportForm : esc_html__('Support Form','easy-form-builder'),
			"createBlankMultistepsForm" => $state ? $ac->text->createBlankMultistepsForm : esc_html__('Start a form from scratch with one or multiple steps.','easy-form-builder'),
			"createContactusForm" => $state ? $ac->text->createContactusForm : esc_html__('Create a Contact us form.','easy-form-builder'),
			"createRegistrationForm" => $state ? $ac->text->createRegistrationForm : esc_html__('Create a user registration form for your WordPress site.','easy-form-builder'),
			"createLoginForm" => $state ? $ac->text->createLoginForm : esc_html__('Create a login form for your WordPress site','easy-form-builder'),
			"createnewsletterForm" => $state ? $ac->text->createnewsletterForm : esc_html__('Create a newsletter subscription form','easy-form-builder'),
			"createSupportForm" => $state ? $ac->text->createSupportForm : esc_html__('Create a support contact form.','easy-form-builder'),
			"quoteFormT" => $state && isset($ac->text->quoteFormT) ? $ac->text->quoteFormT : esc_html__('Request a Quote','easy-form-builder'),
			"quoteFormD" => $state && isset($ac->text->quoteFormD) ? $ac->text->quoteFormD : esc_html__('Collect project details and budget info from clients.','easy-form-builder'),
			"customOrderFormT" => $state && isset($ac->text->customOrderFormT) ? $ac->text->customOrderFormT : esc_html__('Custom Order Form','easy-form-builder'),
			"customOrderFormD" => $state && isset($ac->text->customOrderFormD) ? $ac->text->customOrderFormD : esc_html__('Accept custom orders with product selection and payment.','easy-form-builder'),
			"jobApplicationFormT" => $state && isset($ac->text->jobApplicationFormT) ? $ac->text->jobApplicationFormT : esc_html__('Job Application','easy-form-builder'),
			"jobApplicationFormD" => $state && isset($ac->text->jobApplicationFormD) ? $ac->text->jobApplicationFormD : esc_html__('Collect resumes and applicant information.','easy-form-builder'),
			"rentCarFormT" => $state && isset($ac->text->rentCarFormT) ? $ac->text->rentCarFormT : esc_html__('Rent a Car','easy-form-builder'),
			"rentCarFormD" => $state && isset($ac->text->rentCarFormD) ? $ac->text->rentCarFormD : esc_html__('Car rental booking form with vehicle and date selection.','easy-form-builder'),
			"salonConsultationFormT" => $state && isset($ac->text->salonConsultationFormT) ? $ac->text->salonConsultationFormT : esc_html__('Salon Consultation','easy-form-builder'),
			"salonConsultationFormD" => $state && isset($ac->text->salonConsultationFormD) ? $ac->text->salonConsultationFormD : esc_html__('Book salon consultations with service preferences.','easy-form-builder'),
			"graphicDesignOrderFormT" => $state && isset($ac->text->graphicDesignOrderFormT) ? $ac->text->graphicDesignOrderFormT : esc_html__('Graphic Design Order','easy-form-builder'),
			"graphicDesignOrderFormD" => $state && isset($ac->text->graphicDesignOrderFormD) ? $ac->text->graphicDesignOrderFormD : esc_html__('Collect graphic design project requirements.','easy-form-builder'),
			"sampleCvFormT" => $state && isset($ac->text->sampleCvFormT) ? $ac->text->sampleCvFormT : esc_html__('CV Application','easy-form-builder'),
			"sampleCvFormD" => $state && isset($ac->text->sampleCvFormD) ? $ac->text->sampleCvFormD : esc_html__('Collect CV and resume details from applicants.','easy-form-builder'),
			"videographyBriefFormT" => $state && isset($ac->text->videographyBriefFormT) ? $ac->text->videographyBriefFormT : esc_html__('Videography Brief','easy-form-builder'),
			"videographyBriefFormD" => $state && isset($ac->text->videographyBriefFormD) ? $ac->text->videographyBriefFormD : esc_html__('Gather creative brief details for video projects.','easy-form-builder'),
			"partyInviteFormT" => $state && isset($ac->text->partyInviteFormT) ? $ac->text->partyInviteFormT : esc_html__('Party Invitation','easy-form-builder'),
			"partyInviteFormD" => $state && isset($ac->text->partyInviteFormD) ? $ac->text->partyInviteFormD : esc_html__('Create party invitation RSVP forms.','easy-form-builder'),
			"eventRegistrationFormT" => $state && isset($ac->text->eventRegistrationFormT) ? $ac->text->eventRegistrationFormT : esc_html__('Event Registration','easy-form-builder'),
			"eventRegistrationFormD" => $state && isset($ac->text->eventRegistrationFormD) ? $ac->text->eventRegistrationFormD : esc_html__('Register attendees for events with custom fields.','easy-form-builder'),
			"storeSurveyFormT" => $state && isset($ac->text->storeSurveyFormT) ? $ac->text->storeSurveyFormT : esc_html__('Store Experience Survey','easy-form-builder'),
			"storeSurveyFormD" => $state && isset($ac->text->storeSurveyFormD) ? $ac->text->storeSurveyFormD : esc_html__('Collect customer feedback about in-store experience.','easy-form-builder'),
			"voterSurveyFormT" => $state && isset($ac->text->voterSurveyFormT) ? $ac->text->voterSurveyFormT : esc_html__('Voter Behavior Survey','easy-form-builder'),
			"voterSurveyFormD" => $state && isset($ac->text->voterSurveyFormD) ? $ac->text->voterSurveyFormD : esc_html__('Survey template for voter behavior research.','easy-form-builder'),
			"signupFormT" => $state && isset($ac->text->signupFormT) ? $ac->text->signupFormT : esc_html__('Signup Form','easy-form-builder'),
			"signupFormD" => $state && isset($ac->text->signupFormD) ? $ac->text->signupFormD : esc_html__('Multi-step signup form with payment integration.','easy-form-builder'),
			"sportsLeagueFormT" => $state && isset($ac->text->sportsLeagueFormT) ? $ac->text->sportsLeagueFormT : esc_html__('Sports League Signup','easy-form-builder'),
			"sportsLeagueFormD" => $state && isset($ac->text->sportsLeagueFormD) ? $ac->text->sportsLeagueFormD : esc_html__('Register players for recreational sports leagues.','easy-form-builder'),
			"summerReadingFormT" => $state && isset($ac->text->summerReadingFormT) ? $ac->text->summerReadingFormT : esc_html__('Summer Reading Program','easy-form-builder'),
			"summerReadingFormD" => $state && isset($ac->text->summerReadingFormD) ? $ac->text->summerReadingFormD : esc_html__('Sign up participants for summer reading programs.','easy-form-builder'),
			"childrenLibraryCardFormT" => $state && isset($ac->text->childrenLibraryCardFormT) ? $ac->text->childrenLibraryCardFormT : esc_html__('Children Library Card','easy-form-builder'),
			"childrenLibraryCardFormD" => $state && isset($ac->text->childrenLibraryCardFormD) ? $ac->text->childrenLibraryCardFormD : esc_html__('Application form for children’s library cards.','easy-form-builder'),
			"employeeSuggestionFormT" => $state && isset($ac->text->employeeSuggestionFormT) ? $ac->text->employeeSuggestionFormT : esc_html__('Employee Suggestion','easy-form-builder'),
			"employeeSuggestionFormD" => $state && isset($ac->text->employeeSuggestionFormD) ? $ac->text->employeeSuggestionFormD : esc_html__('Collect employee suggestions and feedback.','easy-form-builder'),
			"bookClubFormT" => $state && isset($ac->text->bookClubFormT) ? $ac->text->bookClubFormT : esc_html__('Book Club Suggestion','easy-form-builder'),
			"bookClubFormD" => $state && isset($ac->text->bookClubFormD) ? $ac->text->bookClubFormD : esc_html__('Collect book suggestions from club members.','easy-form-builder'),
			"availableSoon" => $state ? $ac->text->availableSoon : esc_html__('Available Soon','easy-form-builder'),
			"reservation" => $state ? $ac->text->reservation : esc_html__('Reservation ','easy-form-builder'),
			"createsurveyForm" => $state ? $ac->text->createsurveyForm : esc_html__('Create survey, poll, or questionnaire forms.','easy-form-builder'),
			"createReservationyForm" => $state ? $ac->text->createReservationyForm : esc_html__('Create reservation or booking forms.','easy-form-builder'),
			"firstName" => $state ? $ac->text->firstName : esc_html__('First name','easy-form-builder'),
			"lastName" => $state ? $ac->text->lastName : esc_html__('Last name','easy-form-builder'),
			"message" => $state ? $ac->text->message : esc_html__('Message','easy-form-builder'),
			"subject" => $state ? $ac->text->subject : esc_html__('Subject','easy-form-builder'),
			"phone" => $state ? $ac->text->phone : esc_html__('Phone','easy-form-builder'),
			"register" => $state ? $ac->text->register : esc_html__('Register','easy-form-builder'),
			"username" => $state ? $ac->text->username : esc_html__('Username','easy-form-builder'),
			/* translators: all step = all form steps/pages in multi-step form */
			"allStep" => $state ? $ac->text->allStep : esc_html__('all step','easy-form-builder'),
			/* translators: Beside = next to/alongside (label position) */
			"beside" => $state ? $ac->text->beside : esc_html__('Beside','easy-form-builder'),
			"invalidEmail" => $state ? $ac->text->invalidEmail : esc_html__('Invalid Email address','easy-form-builder'),
			"clearUnnecessaryFiles" => $state ? $ac->text->clearUnnecessaryFiles : esc_html__('Delete unnecessary files','easy-form-builder'),
			"youCanRemoveUnnecessaryFileUploaded" => $state ? $ac->text->youCanRemoveUnnecessaryFileUploaded : esc_html__('Remove leftover files from incomplete form submissions. These are uploads that were never finalized.','easy-form-builder'),
			"whenEasyFormBuilderRecivesNewMessage" => $state ? $ac->text->whenEasyFormBuilderRecivesNewMessage : esc_html__('When a new message is received through an Easy Form Builder form, an alert email is sent to the site administrator.','easy-form-builder'),
			/* translators: reCAPTCHA v2 = Google's version 2 anti-spam verification system */
			"reCAPTCHAv2" => $state ? $ac->text->reCAPTCHAv2 : esc_html__('reCAPTCHA v2','easy-form-builder'),
			"shieldSilentCaptcha" => $state && isset($ac->text->shieldSilentCaptcha) ? $ac->text->shieldSilentCaptcha : esc_html__('silentCAPTCHA Spam Protection','easy-form-builder'),
			"shieldSilentCaptchaDesc" => $state && isset($ac->text->shieldSilentCaptchaDesc) ? $ac->text->shieldSilentCaptchaDesc : esc_html__("Enable silentCAPTCHA (Shield Security) to protect against spam and bots.",'easy-form-builder'),
			"shieldNotDetected" => $state && isset($ac->text->shieldNotDetected) ? $ac->text->shieldNotDetected : esc_html__('Shield Security is not detected, but it can be installed to enhance security.','easy-form-builder'),
			"clickHereWatchVideoTutorial" => $state ? $ac->text->clickHereWatchVideoTutorial : esc_html__('Click here to watch a video tutorial.','easy-form-builder'),
			"siteKey" => $state ? $ac->text->siteKey : esc_html__('Site Key','easy-form-builder'),
			"SecreTKey" => $state ? $ac->text->SecreTKey : esc_html__('Secret Key','easy-form-builder'),
			"EnterSECRETKEY" => $state ? $ac->text->EnterSECRETKEY : esc_html__('Enter the Secret Key','easy-form-builder'),
			"clearFiles" => $state ? $ac->text->clearFiles : esc_html__('Clear Files','easy-form-builder'),
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : esc_html__('Enter your activation code','easy-form-builder'),
			"error" => $state ? $ac->text->error : esc_html__('Error','easy-form-builder'),
			"somethingWentWrongTryAgain" => $state ? $ac->text->somethingWentWrongTryAgain : esc_html__('Something unexpected happened. Please try again by refreshing the page.','easy-form-builder'),
			"enterThePhone" => $state ? $ac->text->enterThePhone : esc_html__('Please enter a valid phone number.','easy-form-builder'),
			"pleaseMakeSureAllFields" => $state ? $ac->text->pleaseMakeSureAllFields : esc_html__('Please ensure that all fields are filled correctly.','easy-form-builder'),
			"enterTheEmail" => $state ? $ac->text->enterTheEmail : esc_html__('Please enter an email address.','easy-form-builder'),
			/* translators: NN is the maximum file size in megabytes */
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
			/* translators: Sync = Synchronize - to update and match data */
			"sync" => $state ? $ac->text->sync : esc_html__('Sync','easy-form-builder'),
			"enterTheValueThisField" => $state ? $ac->text->enterTheValueThisField : esc_html__('This field is required.','easy-form-builder'),
			/* translators: %s will be replaced with field type like "Required", "Validation", etc. Example: "Custom Required Message" */
			"customMessage" => $state && isset($ac->text->customMessage) ? $ac->text->customMessage : esc_html__('Custom %s Message','easy-form-builder'),
			/* translators: Hint text for custom message field. %s will be replaced with type like "message", "value", etc. */
			"customMessageHint" => $state && isset($ac->text->customMessageHint) ? $ac->text->customMessageHint : esc_html__('Leave empty to use default %s','easy-form-builder'),
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
			/* translators: %1$s and %2$s are opening and closing HTML link tags for the WordPress.org translation portal */
			"translateContrib" => $state ? $ac->text->translateContrib : esc_html__('Help us speak your language! Translate Easy Form Builder on the %1$sWordPress.org translation portal%2$s and make it accessible to your community.','easy-form-builder'),
			/* translators: %1$s and %2$s are opening and closing HTML link tags for the WordPress.org translation portal, %3$s is the discount percentage */
			"translateDiscount" => $state && isset($ac->text->translateDiscount) ? $ac->text->translateDiscount : esc_html__('If your language translation is not available yet, translate it and get a %3$s lifetime discount! Contribute via the %1$sWordPress.org translation portal%2$s.','easy-form-builder'),
			"discountOff" => $state && isset($ac->text->discountOff) ? $ac->text->discountOff : esc_html__('OFF','easy-form-builder'),
			"translateLocal" => $state ? $ac->text->translateLocal : esc_html__('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.','easy-form-builder'),
			"enterValidURL" => $state ? $ac->text->enterValidURL : esc_html__('Please enter a valid URL. Protocol is required (http://, https://)','easy-form-builder'),
			"emailOrUsername" => $state ? $ac->text->emailOrUsername : esc_html__('Email or Username','easy-form-builder'),
			"contactusForm" => $state ? $ac->text->contactusForm : esc_html__('Contact Us Form','easy-form-builder'),
			"clear" => $state ? $ac->text->clear : esc_html__('Clear','easy-form-builder'),
			"entrTrkngNo" => $state ? $ac->text->entrTrkngNo : esc_html__('Enter the Confirmation Code','easy-form-builder'),
			"search" => $state ? $ac->text->search : esc_html__('Search','easy-form-builder'),
			"enterThePhones" => $state ? $ac->text->enterThePhones : esc_html__('Enter The Phone No','easy-form-builder'),
			"conturyList" => $state ? $ac->text->conturyList : esc_html__('Countries Drop-down','easy-form-builder'),
			/* translators: Prov = Province - administrative region/state */
			"stateProvince" => $state ? $ac->text->stateProvince : esc_html__('State/Prov Drop-down','easy-form-builder'),
			"thankYouMessage" => $state ? $ac->text->thankYouMessage : esc_html__('Thank you message','easy-form-builder'),
			"newMessage" => $state ? $ac->text->newMessage : esc_html__('New message!', 'easy-form-builder'),
			"newMessageReceived" => $state ? $ac->text->newMessageReceived : esc_html__('A New Message has been Received.', 'easy-form-builder'),
			"createdBy" => $state ? $ac->text->createdBy : esc_html__('Created by','easy-form-builder'),
			"hiUser" => $state ? $ac->text->hiUser : esc_html__('Hi dear user', 'easy-form-builder'),
			"sentBy" => $state ? $ac->text->sentBy : esc_html__("Sent by:",'easy-form-builder'),
			"youRecivedNewMessage" => $state ? $ac->text->youRecivedNewMessage : esc_html__('You have a new message.', 'easy-form-builder'),
			"formNExist" => $state ? $ac->text->formNExist : esc_html__('Form does not exist !!','easy-form-builder'),
			/* translators: E403 = Error code 403 - security/permission error */
			"error403" => $state ? $ac->text->error403 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E403','easy-form-builder'),
			/* translators: E400 = Error code 400 - bad request error */
			"error400" => $state ? $ac->text->error400 : esc_html__('Your security session has expired or is invalid. Please refresh the page. E400','easy-form-builder'),
			"formPrivateM" => $state  && isset($ac->text->formPrivateM) ? $ac->text->formPrivateM : esc_html__('This is a private form. Please log in to access it.','easy-form-builder'),
			/* translators: site key and secret key = Google reCAPTCHA keys */
			"errorSiteKeyM" => $state ? $ac->text->errorSiteKeyM : esc_html__('Please check the site key and secret key on Easy Form Builder panel > Settings > Google Keys to resolve the error.','easy-form-builder'),
			"errorCaptcha" => $state ? $ac->text->errorCaptcha : esc_html__('There seems to be a problem with the Captcha. Please try again.','easy-form-builder'),
			"createAcountDoneM" => $state ? $ac->text->createAcountDoneM : esc_html__('Your account has been successfully created! You will receive an email containing your information','easy-form-builder'),
			"incorrectUP" => $state ? $ac->text->incorrectUP : esc_html__('This username or password combination is incorrect.','easy-form-builder'),
			"newPassM" => $state ? $ac->text->newPassM : esc_html__('If your email is valid, a new password will send to your email.','easy-form-builder'),
			"surveyComplatedM" => $state ? $ac->text->surveyComplatedM : esc_html__('The survey has been successfully completed.','easy-form-builder'),
			/* translators: Survey Results Display = option to configure how survey results are shown to users after submission */
			"surveyResultsDisplay" => $state && isset($ac->text->surveyResultsDisplay) ? $ac->text->surveyResultsDisplay : esc_html__('Survey Results Display','easy-form-builder'),
			/* translators: Do not show results = option to hide survey results from users */
			"surveyNoChart" => $state && isset($ac->text->surveyNoChart) ? $ac->text->surveyNoChart : esc_html__('Do not show results','easy-form-builder'),
			/* translators: Show results with bar chart = option to display survey results as a bar chart */
			"surveyBarChart" => $state && isset($ac->text->surveyBarChart) ? $ac->text->surveyBarChart : esc_html__('Show results with bar chart','easy-form-builder'),
			/* translators: Show results with pie chart = option to display survey results as a pie chart */
			"surveyPieChart" => $state && isset($ac->text->surveyPieChart) ? $ac->text->surveyPieChart : esc_html__('Show results with pie chart','easy-form-builder'),
			/* translators: Help text explaining survey chart feature */
			"surveyChartHelp" => $state && isset($ac->text->surveyChartHelp) ? $ac->text->surveyChartHelp : esc_html__('After submission, visitors can see aggregate survey results','easy-form-builder'),
			/* translators: Show this field in public survey results = field-level option to include field in public survey results */
			"showInPublicResults" => $state && isset($ac->text->showInPublicResults) ? $ac->text->showInPublicResults : esc_html__('Show this field in public survey results','easy-form-builder'),
			/* translators: Survey Results = title for survey results section */
			"surveyResults" => $state && isset($ac->text->surveyResults) ? $ac->text->surveyResults : esc_html__('Survey Results','easy-form-builder'),
			/* translators: Responses = number of survey responses */
			"responses" => $state && isset($ac->text->responses) ? $ac->text->responses : esc_html__('Responses','easy-form-builder'),
			/* translators: E405 = Error code 405 - security error */
			"error405" => $state ? $ac->text->error405 : esc_html__('We are sorry, but there seems to be a security error (405) with your request.','easy-form-builder'),
			"errorSettingNFound" => $state ? $ac->text->errorSettingNFound : esc_html__('Error, Setting not Found','easy-form-builder'),
			/* translators: errorMRobot = Error message for robot verification failure */
			"errorMRobot" => $state ? $ac->text->errorMRobot : esc_html__('Sorry, there seems to be an error. Please verify that you are human and try again.','easy-form-builder'),
			/* translators: errorMEmail = Error message for validation field input */
			"enterVValue" => $state ? $ac->text->enterVValue : esc_html__('Please enter valid values','easy-form-builder'),
			/* translators: Confirmation Code is a unique identifier and after filling the form, users receive this code to track their submission. this message is shown when the confirmation code is not found in the search box */
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
			/* translators: this sentence refers to the Bootstrap Template option in the plugin settings */
			"bootStrapTemp" => $state ? $ac->text->bootStrapTemp : esc_html__('Bootstrap Template','easy-form-builder'),
			"iUsebootTempW" => $state ? $ac->text->iUsebootTempW : esc_html__('Warning: If your theme uses Bootstrap, make sure the option below is enabled.','easy-form-builder'),
			"iUsebootTemp" => $state ? $ac->text->iUsebootTemp : esc_html__('My theme is based on Bootstrap','easy-form-builder'),
			"invalidRequire" => $state ? $ac->text->invalidRequire : esc_html__('Uh oh, it looks like there is a problem with your request. Please review everything and try again.','easy-form-builder'),
			"updated" => $state ? $ac->text->updated : esc_html__('updated','easy-form-builder'),
			"PEnterMessage" => $state ? $ac->text->PEnterMessage : esc_html__('Please type in your message','easy-form-builder'),
			"fileDeleted" => $state ? $ac->text->fileDeleted : esc_html__('The files have been deleted.','easy-form-builder'),
			"activationNcorrect" => $state ? $ac->text->activationNcorrect : esc_html__('The activation code you entered is incorrect. Please double-check and try again.','easy-form-builder'),
			"MMessageNSendEr" => $state ? $ac->text->MMessageNSendEr : esc_html__('We are sorry, but the message was not sent due to a settings error. Please contact the admin for assistance.','easy-form-builder'),
			/* translators: OR = logical operator meaning one option or the other */
			"or" => $state  && isset($ac->text->or)? $ac->text->or : esc_html__('OR','easy-form-builder'),
			"emailTemplate" => $state  &&  isset($ac->text->emailTemplate) ? $ac->text->emailTemplate : esc_html__('Email Template','easy-form-builder'),
			"reset" => $state  &&  isset($ac->text->reset) ? $ac->text->reset : esc_html__('reset','easy-form-builder'),
			/* translators: Message explaining email notification is a free feature - shown on form cards on the creation pages */
			"freefeatureNotiEmail" => $state && isset($ac->text->freefeatureNotiEmail) ? $ac->text->freefeatureNotiEmail : esc_html__('Email notifications are available in all versions, including Free, Free Plus, and Pro.','easy-form-builder'),
			"notFound" => $state  &&  isset($ac->text->notFound) ? $ac->text->notFound : esc_html__('Not Found','easy-form-builder'),
			"editor" => $state  &&  isset($ac->text->editor) ? $ac->text->editor : esc_html__('Editor','easy-form-builder'),
			"addSCEmailM" => $state  &&  isset($ac->text->addSCEmailM) ? $ac->text->addSCEmailM : esc_html__('Please add the shortcode_message shortcode to the email template.','easy-form-builder'),
			"ChrlimitEmail" => $state  &&  isset($ac->text->ChrlimitEmail) ? $ac->text->ChrlimitEmail : esc_html__('Your Email Template cannot exceed 10,000 characters.','easy-form-builder'),
			"pleaseEnterVaildEtemp" => $state  &&  isset($ac->text->pleaseEnterVaildEtemp) ? $ac->text->pleaseEnterVaildEtemp : esc_html__('Please use HTML tags to create your email template.','easy-form-builder'),
			/* translators: HTML2 = HTML (HyperText Markup Language) for creating email templates */
			"infoEmailTemplates" => $state  &&  isset($ac->text->infoEmailTemplates) ? $ac->text->infoEmailTemplates : esc_html__('To create an email template using HTML2, use the following shortcodes. Please note that the shortcodes marked with an asterisk “*” should be included in the email template.','easy-form-builder'),
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : esc_html__('Add this shortcode inside a tag to display the title of the email.','easy-form-builder'),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : esc_html__('Add this shortcode inside an HTML tag to display the message content of an email.','easy-form-builder'),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : esc_html__('To display the website name, add this shortcode inside an HTML tag.','easy-form-builder'),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : esc_html__('Add this shortcode within an HTML tag to display the Website URL.','easy-form-builder'),
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
			/* translators: Reference Code = unique tracking code from payment gateway */
			"refCode" => $state  &&  isset($ac->text->refCode) ? $ac->text->refCode : esc_html__('Reference Code','easy-form-builder'),
			"cardExpiry" => $state  &&  isset($ac->text->cardExpiry) ? $ac->text->cardExpiry : esc_html__('Card Expiry','easy-form-builder'),
			/* translators: CVC = Card Verification Code - 3-digit security code on credit cards */
			"cardCVC" => $state  &&  isset($ac->text->cardCVC) ? $ac->text->cardCVC : esc_html__('Card CVC','easy-form-builder'),
			"payNow" => $state  &&  isset($ac->text->payNow) ? $ac->text->payNow : esc_html__('Pay Now','easy-form-builder'),
			"payAmount" => $state  &&  isset($ac->text->payAmount) ? $ac->text->payAmount : esc_html__('Pay amount','easy-form-builder'),
			"successPayment" => $state  &&  isset($ac->text->successPayment) ? $ac->text->successPayment : esc_html__('Success payment','easy-form-builder'),
			"transctionId" => $state  &&  isset($ac->text->transctionId) ? $ac->text->transctionId : esc_html__('Transaction Id','easy-form-builder'),
			"addPaymentGetway" => $state  &&  isset($ac->text->addPaymentGetway) ? $ac->text->addPaymentGetway : esc_html__('Error: No payment gateway has been added to the form.','easy-form-builder'),
			"emptyCartM" => $state  &&  isset($ac->text->emptyCartM) ? $ac->text->emptyCartM : esc_html__('Your cart is currently empty. Please add items to continue.','easy-form-builder'),
			"payCheckbox" => $state  &&  isset($ac->text->payCheckbox) ? $ac->text->payCheckbox : esc_html__('Payment Multi choose','easy-form-builder'),
			"payRadio" => $state  &&  isset($ac->text->payRadio) ? $ac->text->payRadio : esc_html__('Payment Single choose','easy-form-builder'),
			"paySelect" => $state  &&  isset($ac->text->paySelect) ? $ac->text->paySelect : esc_html__('Payment Selection choose','easy-form-builder'),
			"payMultiselect" => $state  &&  isset($ac->text->payMultiselect) ? $ac->text->payMultiselect : esc_html__('Payment dropdown list','easy-form-builder'),
			"errorCode" => $state  &&  isset($ac->text->errorCode) ? $ac->text->errorCode : esc_html__('Error Code','easy-form-builder'),
			"stripeKeys" => $state  &&  isset($ac->text->stripeKeys) ? $ac->text->stripeKeys : esc_html__('Stripe Keys','easy-form-builder'),
			"publicKey" => $state  &&  isset($ac->text->publicKey) ? $ac->text->publicKey : esc_html__('Public Key','easy-form-builder'),
			"price" => $state  &&  isset($ac->text->price) ? $ac->text->price : esc_html__('Price','easy-form-builder'),
			"title" => $state  &&  isset($ac->text->title) ? $ac->text->title : esc_html__('title','easy-form-builder'),
			"medium" => $state  &&  isset($ac->text->medium) ? $ac->text->medium : esc_html__('Medium','easy-form-builder'),
			"small" => $state  &&  isset($ac->text->small) ? $ac->text->small : esc_html__('Small','easy-form-builder'),
			"xsmall" => $state  &&  isset($ac->text->xsmall) ? $ac->text->xsmall : esc_html__('XSmall','easy-form-builder'),
			"xxsmall" => $state  &&  isset($ac->text->xxsmall) ? $ac->text->xxsmall : esc_html__('XXSmall','easy-form-builder'),
			"createPaymentForm" => $state  &&  isset($ac->text->createPaymentForm) ? $ac->text->createPaymentForm : esc_html__('Create a payment form to collect online payments.','easy-form-builder'),
			"pro" => $state  &&  isset($ac->text->pro) ? $ac->text->pro : esc_html__('Pro','easy-form-builder'),
			"submit" => $state  &&  isset($ac->text->submit) ? $ac->text->submit : esc_html__('Submit','easy-form-builder'),
			"purchaseOrder" => $state  &&  isset($ac->text->purchaseOrder) ? $ac->text->purchaseOrder : esc_html__('Purchase Order','easy-form-builder'),
			"paymentNcaptcha" => $state  &&  isset($ac->text->paymentNcaptcha) ? $ac->text->paymentNcaptcha : esc_html__('It is not possible to include reCAPTCHA on payment forms.','easy-form-builder'),
			"PleaseMTPNotWork" => $state &&  isset($ac->text->PleaseMTPNotWork) ? $ac->text->PleaseMTPNotWork : esc_html__('Easy Form Builder could not confirm if your service is able to send emails. Please check your email inbox (or spam folder) to see if you have received an email with the subject line: Email server [Easy Form Builder]. If you have received the email, please select the option < This site can send emails > and save the changes.','easy-form-builder'),
			"hostSupportSmtp" => $state  &&  isset($ac->text->hostSupportSmtp) ? $ac->text->hostSupportSmtp : esc_html__('This site can send emails','easy-form-builder'),
			"actions" => $state  &&  isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions','easy-form-builder'),

			/* translators: %s is the toggle option name for email confirmation */
			"PleaseMTPNotWork2" => $state &&  isset($ac->text->PleaseMTPNotWork2) ? $ac->text->PleaseMTPNotWork2 : esc_html__('Easy Form Builder could not confirm that your server can send emails. Please check your inbox or spam folder for an email with the subject: "Email server [Easy Form Builder]". If you received it, please enable the "%s" toggle and save your changes.','easy-form-builder'),
			"hostSupportSmtp2" => $state  &&  isset($ac->text->hostSupportSmtp2) ? $ac->text->hostSupportSmtp2 : esc_html__('I confirm that this WordPress site is able to send emails properly','easy-form-builder'),
			"interval" => $state  &&  isset($ac->text->interval) ? $ac->text->interval : esc_html__('Interval','easy-form-builder'),
			"nextBillingD" => $state  &&  isset($ac->text->nextBillingD) ? $ac->text->nextBillingD : esc_html__('Next Billing Date','easy-form-builder'),
			"dayly" => $state  &&  isset($ac->text->dayly) ? $ac->text->dayly : esc_html__('Daily','easy-form-builder'),
			"monthly" => $state  &&  isset($ac->text->monthly) ? $ac->text->monthly : esc_html__('Monthly','easy-form-builder'),
			"weekly" => $state  &&  isset($ac->text->weekly) ? $ac->text->weekly : esc_html__('Weekly','easy-form-builder'),
			"yearly" => $state  &&  isset($ac->text->yearly) ? $ac->text->yearly : esc_html__('Yearly','easy-form-builder'),
			"howProV" => $state  &&  isset($ac->text->howProV) ? $ac->text->howProV : esc_html__('How to activate the Pro version of Easy Form Builder','easy-form-builder'),
			"uploadedFile" => $state  &&  isset($ac->text->uploadedFile) ? $ac->text->uploadedFile : esc_html__('Uploaded File','easy-form-builder'),
			"offlineMSend" => $state  &&  isset($ac->text->offlineMSend) ? $ac->text->offlineMSend : esc_html__('Your internet connection has been lost, but do not worry, we have saved the information you entered on this form. Once you are reconnected to the internet, you can easily send your information by clicking the submit button.','easy-form-builder'),
			"offlineSend" => $state  &&  isset($ac->text->offlineSend) ? $ac->text->offlineSend : esc_html__('Please ensure that you have a stable internet connection and try again.','easy-form-builder'),
			"fileUploadNetworkError" => $state  &&  isset($ac->text->fileUploadNetworkError) ? $ac->text->fileUploadNetworkError : esc_html__('There was a problem uploading the file and the form was not submitted. Please check that your internet connection is stable and try again.','easy-form-builder'),
			"options" => $state  &&  isset($ac->text->options) ? $ac->text->options : esc_html__('Options','easy-form-builder'),
			/* translators: JQ-500 = jQuery error code 500 - JavaScript library issue */
			"eJQ500" => $state  &&  isset($ac->text->eJQ500) ? $ac->text->eJQ500 : esc_html__('You are experiencing issues with JQuery. Please contact the administrator for assistance. (Error code: JQ-500)','easy-form-builder'),
			/* translators: Nonce = Number used once - a security token to prevent unauthorized actions */
			"nonceExpired" => $state  &&  isset($ac->text->nonceExpired) ? $ac->text->nonceExpired : esc_html__('Your session has expired. Please refresh the page and try again.','easy-form-builder'),
			"basic" => $state  &&  isset($ac->text->basic) ? $ac->text->basic : esc_html__('Basic','easy-form-builder'),
			"blank" => $state  &&  isset($ac->text->blank) ? $ac->text->blank : esc_html__('Blank','easy-form-builder'),
			"support" => $state  &&  isset($ac->text->support) ? $ac->text->support : esc_html__('Support','easy-form-builder'),
			/* translators: Sign-In|Up = Sign In or Sign Up (login or register) */
			"signInUp" => $state  &&  isset($ac->text->signInUp) ? $ac->text->signInUp : esc_html__('Sign-In|Up','easy-form-builder'),
			"advance" => $state  &&  isset($ac->text->advance) ? $ac->text->advance : esc_html__('Advance','easy-form-builder'),
			"all" => $state  &&  isset($ac->text->all) ? $ac->text->all : esc_html__('All','easy-form-builder'),
			"new" => $state  &&  isset($ac->text->new) ? $ac->text->new : esc_html__('New','easy-form-builder'),
			/* translators: Tnx = Thanks - page shown after form submission */
			"landingTnx" => $state  &&  isset($ac->text->landingTnx) ? $ac->text->landingTnx : esc_html__('Thank you Page','easy-form-builder'),
			"redirectPage" => $state  &&  isset($ac->text->redirectPage) ? $ac->text->redirectPage : esc_html__('Redirect page','easy-form-builder'),
			"pWRedirect" => $state  &&  isset($ac->text->pWRedirect) ? $ac->text->pWRedirect : esc_html__('Please wait, you will be redirected shortly.','easy-form-builder'),
			"persiaPayment" => $state  &&  isset($ac->text->persiaPayment) ? $ac->text->persiaPayment : esc_html__('Persia payment','easy-form-builder'),
			"getPro" => $state  &&  isset($ac->text->getPro) ? $ac->text->getPro : esc_html__('Unlock Pro Features Today','easy-form-builder'),
			"yFreeVEnPro" => $state  &&  isset($ac->text->yFreeVEnPro) ? $ac->text->yFreeVEnPro : esc_html__('You are using the free version. Upgrade to Pro for just %1$s%2$s%3$s/year and unlock advanced features to improve your experience and productivity.%4$sView Pro Features%5$s','easy-form-builder'),
			/* translators: %1$s is the name of the addon */
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('Add-on','easy-form-builder'),
			"addons" => $state  &&  isset($ac->text->addons) ? $ac->text->addons : esc_html__('Add-ons','easy-form-builder'),
			"stripeTAddon" => $state  &&  isset($ac->text->stripeTAddon) ? $ac->text->stripeTAddon : esc_html__('Stripe Payment Addon','easy-form-builder'),
			"stripeDAddon" => $state  &&  isset($ac->text->stripeDAddon) ? $ac->text->stripeDAddon : esc_html__('The Stripe add-on for Easy Form Builder enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.','easy-form-builder'),
			"offlineTAddon" => $state  &&  isset($ac->text->offlineTAddon) ? $ac->text->offlineTAddon : esc_html__('Offline Forms Addon','easy-form-builder'),
			"offlineDAddon" => $state  &&  isset($ac->text->offlineDAddon) ? $ac->text->offlineDAddon : esc_html__('The Offline Forms add-on for Easy Form Builder allows users to save their progress when filling out forms in offline situations.','easy-form-builder'),

			"install" => $state  &&  isset($ac->text->install) ? $ac->text->install : esc_html__('Install','easy-form-builder'),
			"upDMsg" => $state  &&  isset($ac->text->upDMsg) ? $ac->text->upDMsg : esc_html__('Please update Easy Form Builder before trying again.','easy-form-builder'),
			"AfLnFrm" => $state  &&  isset($ac->text->AfLnFrm) ? $ac->text->AfLnFrm : esc_html__('Activation of offline form mode.','easy-form-builder'),
			"IMAddons" => $state  &&  isset($ac->text->IMAddons) ? $ac->text->IMAddons : esc_html__('Before activating this option, install','easy-form-builder'),
			"IMAddonP" => $state  &&  isset($ac->text->IMAddonP) ? $ac->text->IMAddonP : esc_html__('To create a payment form to collect online payments, you must first install a payment add-on such as the Stripe Add-on.','easy-form-builder'),
			"allformat" => $state  &&  isset($ac->text->allformat) ? $ac->text->allformat : esc_html__('All formats','easy-form-builder'),
			"AdnSST" => $state  &&  isset($ac->text->AdnSST) ? $ac->text->AdnSST : esc_html__('EFB SMS Addon','easy-form-builder'),
			"AdnSSD" => $state  &&  isset($ac->text->AdnSSD) ? $ac->text->AdnSSD : esc_html__('Enable SMS functionality in your forms with the EFB SMS add-on, allowing you to validate mobile numbers and send confirmation codes via SMS, as well as receive notifications through SMS service.','easy-form-builder'),
			"AdnATCT" => $state  &&  isset($ac->text->AdnATCT) ? $ac->text->AdnATCT : esc_html__('Advanced confirmation code Addon','easy-form-builder'),
			"AdnATCD" => $state  &&  isset($ac->text->AdnATCD) ? $ac->text->AdnATCD : esc_html__('Send a confirmation code via email or SMS to users and/or admins, allowing them to quickly access new responses.','easy-form-builder'),
			"chlCheckBox" => $state  &&  isset($ac->text->chlCheckBox) ? $ac->text->chlCheckBox : esc_html__('Box Checklist','easy-form-builder'),
			"chlRadio" => $state  &&  isset($ac->text->chlRadio) ? $ac->text->chlRadio : esc_html__('Radio Checklist','easy-form-builder'),
			/* translators: Qty = Quantity - the amount or number of items */
			"qty" => $state  &&  isset($ac->text->qty) ? $ac->text->qty : esc_html__('Qty','easy-form-builder'),
			/* translators: WPBakery = a WordPress page builder plugin */
			"wwpb" => $state  &&  isset($ac->text->wwpb) ? $ac->text->wwpb : esc_html__('This is a warning for WPBakery users. For more information, please click here.','easy-form-builder'),
			"clsdrspnsM" => $state  &&  isset($ac->text->clsdrspnsM) ? $ac->text->clsdrspnsM : esc_html__('Are you sure you want to close replies to this message?','easy-form-builder'),
			"clsdrspnsMo" => $state  &&  isset($ac->text->clsdrspnsMo) ? $ac->text->clsdrspnsMo : esc_html__('Are you sure you want to open the responses to this message?','easy-form-builder'),
			"clsdrspn" => $state  &&  isset($ac->text->clsdrspn) ? $ac->text->clsdrspn : esc_html__('The response has been closed by Admin.','easy-form-builder'),
			"clsdrspo" => $state  &&  isset($ac->text->clsdrspo) ? $ac->text->clsdrspo : esc_html__('The response has been opened by Admin.','easy-form-builder'),
			"open" => $state  &&  isset($ac->text->open) ? $ac->text->open : esc_html__('Open','easy-form-builder'),
			/* translators: Price display format - e.g., $27/year */
			"priceyr" => $state  &&  isset($ac->text->priceyr) ? $ac->text->priceyr : esc_html__('$NN/year','easy-form-builder'),
			"cols" => $state  &&  isset($ac->text->cols) ? $ac->text->cols : esc_html__('columns','easy-form-builder'),
			"col" => $state  &&  isset($ac->text->col) ? $ac->text->col : esc_html__('column','easy-form-builder'),
			"ilclizeFfb" => $state  &&  isset($ac->text->ilclizeFfb) ? $ac->text->ilclizeFfb : esc_html__('I would like to localize Easy Form Builder.','easy-form-builder'),
			"mlen" => $state  &&  isset($ac->text->mlen) ? $ac->text->mlen : esc_html__('Max length','easy-form-builder'),
			"milen" => $state  &&  isset($ac->text->milen) ? $ac->text->milen : esc_html__('Min length','easy-form-builder'),
			"mmlen" => $state  &&  isset($ac->text->mmlen) ? $ac->text->mmlen : esc_html__('The maximum number of characters allowed in the input element is 524288','easy-form-builder'),
			/* translators: NN is the minimum number of characters required */
			"mmplen" => $state  &&  isset($ac->text->mmplen) ? $ac->text->mmplen : esc_html__('Please enter a value that is at least NN characters long.','easy-form-builder'),
			"mcplen" => $state  &&  isset($ac->text->mcplen) ? $ac->text->mcplen : esc_html__('Please enter a number that is greater than or equal to NN.','easy-form-builder'),
			"mmxplen" => $state  &&  isset($ac->text->mmxplen) ? $ac->text->mmxplen : esc_html__('Please Enter a maximum of NN Characters For this field','easy-form-builder'),
			"mxcplen" => $state  &&  isset($ac->text->mxcplen) ? $ac->text->mxcplen : esc_html__('Please enter a number that is less than or equal to NN','easy-form-builder'),
			"max" => $state  &&  isset($ac->text->max) ? $ac->text->max : esc_html__('Max','easy-form-builder'),
			"min" => $state  &&  isset($ac->text->min) ? $ac->text->min : esc_html__('Min','easy-form-builder'),
			/* translators: Validation message - minimum value must be less than maximum value */
			"mxlmn" => $state  &&  isset($ac->text->mxlmn) ? $ac->text->mxlmn : esc_html__('Minimum entry must be lower than the maximum entry','easy-form-builder'),
			"disabled" => $state  &&  isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Disabled','easy-form-builder'),
			"hflabel" => $state  &&  isset($ac->text->hflabel) ? $ac->text->hflabel : esc_html__('Hide the label','easy-form-builder'),
			/* translators: Response/ticket = form submission that can be closed/opened like a support ticket */
			"resop" => $state  &&  isset($ac->text->resop) ? $ac->text->resop : esc_html__('The response(ticket) closed','easy-form-builder'),
			/* translators: Response/ticket = form submission that can be closed/opened like a support ticket */
			"rescl" => $state  &&  isset($ac->text->rescl) ? $ac->text->rescl : esc_html__('The response(ticket) opened','easy-form-builder'),
			"clcdetls" => $state  &&  isset($ac->text->clcdetls) ? $ac->text->clcdetls : esc_html__('Click here for more details','easy-form-builder'),
			"lson" => $state  &&  isset($ac->text->lson) ? $ac->text->lson : esc_html__('Label of the ON status','easy-form-builder'),
			"lsoff" => $state  &&  isset($ac->text->lsoff) ? $ac->text->lsoff : esc_html__('Label of the OFF status','easy-form-builder'),
			"pr5" => $state  &&  isset($ac->text->pr5) ? $ac->text->pr5 : esc_html__('5 Point Scale','easy-form-builder'),
			/* translators: NPS = Net Promoter Score (0-10 rating scale) */
			"nps_" => $state  &&  isset($ac->text->nps_) ? $ac->text->nps_ : esc_html__('Net Promoter Score','easy-form-builder'),
			/* translators: NPS Table Matrix = Net Promoter Score in table/matrix format */
			"nps_tm" => $state  &&  isset($ac->text->nps_tm) ? $ac->text->nps_tm : esc_html__('NPS Table Matrix','easy-form-builder'),
			"pointr10" => $state  &&  isset($ac->text->pointr10) ? $ac->text->pointr10 : esc_html__('Net Promoter Score','easy-form-builder'),
			"pointr5" => $state  &&  isset($ac->text->pointr5) ? $ac->text->pointr5 : esc_html__('5 Point Scale','easy-form-builder'),
			"table_matrix" => $state  &&  isset($ac->text->table_matrix) ? $ac->text->table_matrix : esc_html__('NPS Table Matrix','easy-form-builder'),
			/* translators: Jalali/Persian/Shamsi calendar used in Iran and Afghanistan */
			"pdate" => $state  &&  isset($ac->text->pdate) ? $ac->text->pdate : esc_html__('Jalali Date','easy-form-builder'),
			/* translators: Hijri/Islamic calendar used in Islamic countries */
			"ardate" => $state  &&  isset($ac->text->ardate) ? $ac->text->ardate : esc_html__('Hijri Date','easy-form-builder'),
			"iaddon" => $state  &&  isset($ac->text->iaddon) ? $ac->text->iaddon : esc_html__('Install the addon','easy-form-builder'),
			/* translators: Jalili is a typo for Jalali (Persian/Shamsi calendar) */
			"IMAddonPD" => $state  &&  isset($ac->text->IMAddonPD) ? $ac->text->IMAddonPD : esc_html__('Please go to the Add-ons Page of Easy Form Builder plugin and install the Jalili date addons','easy-form-builder'),
			"IMAddonAD" => $state  &&  isset($ac->text->IMAddonAD) ? $ac->text->IMAddonAD : esc_html__('Please go to the Add-ons Page of Easy Form Builder plugin and install the Hijri date addons','easy-form-builder'),
			"warning" => $state  &&  isset($ac->text->warning) ? $ac->text->warning : esc_html__('warning','easy-form-builder'),
			"datetimelocal" => $state  &&  isset($ac->text->datetimelocal) ? $ac->text->datetimelocal : esc_html__('date & time','easy-form-builder'),
			"dsupfile" => $state  &&  isset($ac->text->dsupfile) ? $ac->text->dsupfile : esc_html__('Enable file upload in the response box','easy-form-builder'),
			"scaptcha" => $state  &&  isset($ac->text->scaptcha) ? $ac->text->scaptcha : esc_html__('Enable Google reCAPTCHA in the response box','easy-form-builder'),
			"sdlbtn" => $state  &&  isset($ac->text->sdlbtn) ? $ac->text->sdlbtn : esc_html__('Enable download button in the response box','easy-form-builder'),
			"sips" => $state  &&  isset($ac->text->sips) ? $ac->text->sips : esc_html__('Display the IP addresses of users in the response box.','easy-form-builder'),
			/* translators: Persia Payment = Iranian online payment gateway service */
			"persiaPayTAddon" => $state  &&  isset($ac->text->persiaPayTAddon) ? $ac->text->persiaPayTAddon : esc_html__('Persia Payment Addon','easy-form-builder'),
			"persiaPayDAddon" => $state  &&  isset($ac->text->persiaPayDAddon) ? $ac->text->persiaPayDAddon : esc_html__('The Persia payment addon for Easy Form Builder enables you to connect your website with Persia payment to process payments, donations, and online orders.','easy-form-builder'),

			"datePTAddon" => $state  &&  isset($ac->text->datePTAddon) ? $ac->text->datePTAddon : esc_html__('Jalali date Addon','easy-form-builder'),
			/* translators: Shamsi = another name for Jalali/Persian calendar */
			"datePDAddon" => $state  &&  isset($ac->text->datePDAddon) ? $ac->text->datePDAddon : esc_html__('The Jalali date addon allows you to add a Jalali date field to your forms and create any type of form that includes this Shamsi date field.','easy-form-builder'),
			"dateATAddon" => $state  &&  isset($ac->text->dateATAddon) ? $ac->text->dateATAddon : esc_html__('Hijri date Addon','easy-form-builder'),
			"dateADAddon" => $state  &&  isset($ac->text->dateADAddon) ? $ac->text->dateADAddon : esc_html__('The Hijri date addon allows you to add a Hijri date field to your forms and create any type of form that includes this field.','easy-form-builder'),
			"smsTAddon" => $state  &&  isset($ac->text->smsTAddon) ? $ac->text->smsTAddon : esc_html__('SMS service Addon','easy-form-builder'),
			"smsDAddon" => $state  &&  isset($ac->text->smsDAddon) ? $ac->text->smsDAddon : esc_html__('The SMS service addon enables you to receive notification SMS messages when you or your customers receive new messages or responses.','easy-form-builder'),
			"mPAdateW" => $state  &&  isset($ac->text->mPAdateW) ? $ac->text->mPAdateW : esc_html__('Please install either the Hijri or Jalali date addon. You cannot install both addons simultaneously.','easy-form-builder'),
			/* translators: Response box = admin panel where responses/submissions are managed and replied to */
			"rbox" => $state  &&  isset($ac->text->rbox) ? $ac->text->rbox : esc_html__('Response box','easy-form-builder'),
			"smartcr" => $state  &&  isset($ac->text->smartcr) ? $ac->text->smartcr : esc_html__('Regions Drop-Down','easy-form-builder'),




			"wmaddon" => $state  &&  isset($ac->text->wmaddon) ? $ac->text->wmaddon : esc_html__('You are seeing this message because your required add-ons are being installed. Please wait a few minutes and then visit this page again. If it has been more than five minutes and nothing has happened, please contact the support team of Easy Form Builder at Whitestudio.team.','easy-form-builder'),
			"cpnnc" => $state  &&  isset($ac->text->cpnnc) ? $ac->text->cpnnc : esc_html__('The cell phone number is not correct','easy-form-builder'),
			"icc" => $state  &&  isset($ac->text->icc) ? $ac->text->icc : esc_html__('Invalid country code','easy-form-builder'),
			"cpnts" => $state  &&  isset($ac->text->cpnts) ? $ac->text->cpnts : esc_html__('The cell phone number is too short','easy-form-builder'),
			"cpntl" => $state  &&  isset($ac->text->cpntl) ? $ac->text->cpntl : esc_html__('The cell phone number is too long','easy-form-builder'),
			"scdnmi" => $state  &&  isset($ac->text->scdnmi) ? $ac->text->scdnmi : esc_html__('Please select the number of countries to display within an acceptable range.','easy-form-builder'),
			"dField" => $state  &&  isset($ac->text->dField) ? $ac->text->dField : esc_html__('Disabled Field','easy-form-builder'),
			"hField" => $state  &&  isset($ac->text->hField) ? $ac->text->hField : esc_html__('Hidden Field','easy-form-builder'),
			"sctdlosp" => $state  &&  isset($ac->text->sctdlosp) ? $ac->text->sctdlosp : esc_html__('Select a country to display a list of states/provinces.','easy-form-builder'),
			"sctdlocp" => $state  &&  isset($ac->text->sctdlocp) ? $ac->text->sctdlocp : esc_html__('Select a state/province to display a list of cities.','easy-form-builder'),

			"AdnOF" => $state  &&  isset($ac->text->AdnOf) ? $ac->text->AdnOf : esc_html__('Offline Forms Addon','easy-form-builder'),
			"AdnSPF" => $state  &&  isset($ac->text->AdnSPF) ? $ac->text->AdnSPF : esc_html__('Stripe Payment Addon','easy-form-builder'),
			"AdnPDP" => $state  &&  isset($ac->text->AdnPDP) ? $ac->text->AdnPDP : esc_html__('Jalali date Addon','easy-form-builder'),
			"AdnADP" => $state  &&  isset($ac->text->AdnADP) ? $ac->text->AdnADP : esc_html__('Hijri date Addon','easy-form-builder'),
			"AdnPPF" => $state  &&  isset($ac->text->AdnPPF) ? $ac->text->AdnPPF : esc_html__('Persia Payment Addon','easy-form-builder'),
			"AdnSS" => $state  &&  isset($ac->text->AdnSS) ? $ac->text->AdnSS : esc_html__('SMS service Addon','easy-form-builder'),
			"tfnapca" => $state  &&  isset($ac->text->tfnapca) ? $ac->text->tfnapca : esc_html__('Please contact the administrator as the field is currently unavailable.','easy-form-builder'),
			"wylpfucat" => $state  &&  isset($ac->text->wylpfucat) ? $ac->text->wylpfucat : esc_html__('Would you like to customize the form using the colors of the active template?','easy-form-builder'),
			"efbmsgctm" => $state  &&  isset($ac->text->efbmsgctm) ? $ac->text->efbmsgctm : esc_html__('Easy Form Builder has utilized the colors of the active template. Please choose a color for each option below to customize the form you are creating based on the colors of your template. By selecting a color for each option below, the color of all form fields associated with that feature will change accordingly.','easy-form-builder'),
			"btntcs" => $state  &&  isset($ac->text->btntcs) ? $ac->text->btntcs : esc_html__('Buttons text colors','easy-form-builder'),

			"atcfle" => $state  &&  isset($ac->text->atcfle) ? $ac->text->atcfle : esc_html__('attached files','easy-form-builder'),
			"dslctd" => $state  &&  isset($ac->text->dslctd) ? $ac->text->dslctd : esc_html__('Default selected','easy-form-builder'),
			"shwattr" => $state  &&  isset($ac->text->shwattr) ? $ac->text->shwattr : esc_html__('Show attributes','easy-form-builder'),
			"hdattr" => $state  &&  isset($ac->text->hdattr) ? $ac->text->hdattr : esc_html__('Hide attributes','easy-form-builder'),
			"idl5" => $state  &&  isset($ac->text->idl5) ? $ac->text->idl5 : esc_html__('The ID length should be at least 3 characters long.','easy-form-builder'),
			"idmu" => $state  &&  isset($ac->text->idmu) ? $ac->text->idmu : esc_html__('The ID value must be unique, as it is already being used in this field. Please try a new, unique value.','easy-form-builder'),
			"imgRadio" => $state  &&  isset($ac->text->imgRadio) ? $ac->text->imgRadio : esc_html__('Image picker','easy-form-builder'),
			"iimgurl" => $state  &&  isset($ac->text->iimgurl) ? $ac->text->iimgurl : esc_html__('Insert an image url','easy-form-builder'),
			"newbkForm" => $state &&  isset($ac->text->newbkForm)? $ac->text->newbkForm : esc_html__('New Booking Form','easy-form-builder'),
			"AdnSMF" => $state  &&  isset($ac->text->AdnSMF) ? $ac->text->AdnSMF : esc_html__('Conditional logic Addon','easy-form-builder'),
			"condATAddon" => $state  &&  isset($ac->text->condATAddon) ? $ac->text->condATAddon : esc_html__('Conditional logic Addon','easy-form-builder'),
			"condADAddon" => $state  &&  isset($ac->text->condADAddon) ? $ac->text->condADAddon : esc_html__('The Conditional Logic Addon enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users’ unique needs.','easy-form-builder'),

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
			/* translators: OR = logical operator meaning one option or the other */
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
			"sms_mp" => $state  &&  isset($ac->text->sms_mp) ? $ac->text->sms_mp : esc_html__('To enable SMS notifications in your forms, choose a delivery method.','easy-form-builder'),
			"sms_ct" => $state  &&  isset($ac->text->sms_ct) ? $ac->text->sms_ct : esc_html__('Select an SMS delivery method','easy-form-builder'),
			"sms_admn_no" => $state  &&  isset($ac->text->sms_admn_no) ? $ac->text->sms_admn_no : esc_html__('Enter administrators’ mobile numbers','easy-form-builder'),

			"sms_efbs" => $state  &&  isset($ac->text->sms_efbs) ? $ac->text->sms_efbs : esc_html__('Easy Form Builder SMS service','easy-form-builder'),
			/* translators: Phone number format hint shown below the phone input field */
			"phoneFormatHint" => $state && isset($ac->text->phoneFormatHint) ? $ac->text->phoneFormatHint : esc_html__('Format: +12345678900 or +1 (234) 567-8900','easy-form-builder'),
			/* translators: WP SMS = WSMS = WordPress SMS plugin; VeronaLabs = the plugin developer */
			"sms_wpsmss" => $state  &&  isset($ac->text->sms_wpsmss) ? $ac->text->sms_wpsmss : esc_html__('WSMS plugin by VeronaLabs','easy-form-builder'),
			"wpsms_nm" => $state  &&  isset($ac->text->wpsms_nm) ? $ac->text->wpsms_nm : esc_html__('WSMS plugin by VeronaLabs is not installed or activated. Please select another option, or install and configure WSMS.','easy-form-builder'),
			/* translators: NN = Name of the add-on */
			"msg_adons" => $state  &&  isset($ac->text->msg_adons) ? $ac->text->msg_adons : esc_html__('To use this option, please install the NN add-ons from the Easy Form Builder plugin\'s add-ons page.','easy-form-builder'),
			"sms_noti" => $state  &&  isset($ac->text->sms_noti) ? $ac->text->sms_noti : esc_html__('SMS Notifications','easy-form-builder'),
			"sms_dnoti" => $state  &&  isset($ac->text->sms_dnoti) ? $ac->text->sms_dnoti : esc_html__('Enter the administrators’ mobile numbers to receive SMS notifications, such as alerts or new messages.','easy-form-builder'),
			"sms_ndnoti" => $state  &&  isset($ac->text->sms_ndnoti) ? $ac->text->sms_ndnoti : esc_html__('All SMS notifications sent by Easy Form Builder will be delivered to the numbers entered here.','easy-form-builder'),
			"emlc" => $state  &&  isset($ac->text->emlc) ? $ac->text->emlc : esc_html__('Choose Email notification content','easy-form-builder'),
			"emlacl" => $state  &&  isset($ac->text->emlacl) ? $ac->text->emlacl : esc_html__('Send email with confirmation code and link','easy-form-builder'),
			"emlml" => $state  &&  isset($ac->text->emlml) ? $ac->text->emlml : esc_html__('Send email with submitted form content and link','easy-form-builder'),
			"msgemlmp" => $state  &&  isset($ac->text->msgemlmp) ? $ac->text->msgemlmp : esc_html__('To view the map and selected points, simply click here to navigate to the received message page','easy-form-builder'),

			"sms" => $state  &&  isset($ac->text->sms) ? $ac->text->sms : esc_html__('SMS','easy-form-builder'),
			"documentation" => $state  &&  isset($ac->text->documentation) ? $ac->text->documentation : esc_html__('Documentation','easy-form-builder'),
			"smscw" => $state  &&  isset($ac->text->smscw) ? $ac->text->smscw : esc_html__('Click on the Settings button on the panel page of Easy Form Builder Plugin and configure the SMS sending method. Then, try again.','easy-form-builder'),
			"to" => $state  &&  isset($ac->text->to) ? $ac->text->to : esc_html__('To','easy-form-builder'),
			"esmsno" => $state  &&  isset($ac->text->esmsno) ? $ac->text->esmsno : esc_html__('Enable SMS notifications','easy-form-builder'),
			"etelegramno" => $state  &&  isset($ac->text->etelegramno) ? $ac->text->etelegramno : esc_html__('Enable Telegram notifications','easy-form-builder'),
			"telegram" => $state  &&  isset($ac->text->telegram) ? $ac->text->telegram : esc_html__('Telegram','easy-form-builder'),
			"payPalTAddon" => $state  &&  isset($ac->text->payPalTAddon) ? $ac->text->payPalTAddon : esc_html__('PayPal Payment Addon','easy-form-builder'),
			"payPalDAddon" => $state  &&  isset($ac->text->payPaleDAddon) ? $ac->text->payPaleDAddon : esc_html__('The PayPal add-on for Easy Form Builder enables you to integrate your WordPress site with PayPal for payment processing, donations, and online orders.','easy-form-builder'),
			"file_cstm" => $state  &&  isset($ac->text->file_cstm) ? $ac->text->file_cstm : esc_html__('Acceptable file types','easy-form-builder'),
			"cstm_rd" => $state  &&  isset($ac->text->cstm_rd) ? $ac->text->cstm_rd : esc_html__('Customized Ordering','easy-form-builder'),
			"maxfs" => $state  &&  isset($ac->text->maxfs) ? $ac->text->maxfs : esc_html__('Max File Size','easy-form-builder'),
			"cityList" => $state  &&  isset($ac->text->cityList) ? $ac->text->cityList : esc_html__('Cities Drop-Down','easy-form-builder'),
			"elan" => $state  &&  isset($ac->text->elan) ? $ac->text->elan : esc_html__('English language','easy-form-builder'),
			"nlan" => $state  &&  isset($ac->text->nlan) ? $ac->text->nlan : esc_html__('National language','easy-form-builder'),
			"stsd" => $state  &&  isset($ac->text->stsd) ? $ac->text->stsd : esc_html__('Select display language','easy-form-builder'),

			"trya" => $state  &&  isset($ac->text->trya) ? $ac->text->trya : esc_html__('Trying again.','easy-form-builder'),
			"rnfn" => $state  &&  isset($ac->text->rnfn) ? $ac->text->rnfn : esc_html__('Rename the file name','easy-form-builder'),
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
			"loadingType" => $state  &&  isset($ac->text->loadingType) ? $ac->text->loadingType : esc_html__('Loading Animation','easy-form-builder'),
			"loadingColor" => $state  &&  isset($ac->text->loadingColor) ? $ac->text->loadingColor : esc_html__('Loading Color','easy-form-builder'),
			"dots" => $state  &&  isset($ac->text->dots) ? $ac->text->dots : esc_html__('Dots','easy-form-builder'),
			"spinner" => $state  &&  isset($ac->text->spinner) ? $ac->text->spinner : esc_html__('Spinner','easy-form-builder'),
			"pulse" => $state  &&  isset($ac->text->pulse) ? $ac->text->pulse : esc_html__('Pulse','easy-form-builder'),
			"bars" => $state  &&  isset($ac->text->bars) ? $ac->text->bars : esc_html__('Bars','easy-form-builder'),
			"ripple" => $state  &&  isset($ac->text->ripple) ? $ac->text->ripple : esc_html__('Ripple','easy-form-builder'),
			"bounce" => $state  &&  isset($ac->text->bounce) ? $ac->text->bounce : esc_html__('Bounce','easy-form-builder'),
			"orbit" => $state  &&  isset($ac->text->orbit) ? $ac->text->orbit : esc_html__('Orbit','easy-form-builder'),
			"wave" => $state  &&  isset($ac->text->wave) ? $ac->text->wave : esc_html__('Wave','easy-form-builder'),
			"hourglass" => $state  &&  isset($ac->text->hourglass) ? $ac->text->hourglass : esc_html__('Hourglass','easy-form-builder'),
			"fernvtf" => $state  &&  isset($ac->text->fernvtf) ? $ac->text->fernvtf : esc_html__('The entered data does not match the form type. If you are an admin, please review the form type.','easy-form-builder'),
			"fetf" => $state  &&  isset($ac->text->fetf) ? $ac->text->fetf : esc_html__('Error: Please ensure there is only one form per page.','easy-form-builder'),
			"actvtcmsg" => $state  &&  isset($ac->text->actvtcmsg) ? $ac->text->actvtcmsg : esc_html__('Your activation code has been verified. Enjoy all Pro features of Easy Form Builder.','easy-form-builder'),

			/* translators: %s is the confirmation code */
			"msgdml" => $state  &&  isset($ac->text->msgdml) ? $ac->text->msgdml : esc_html__('The confirmation code for this message is %s. By clicking the button below, you will be able to track messages and view received responses. If needed, you can also send a new reply.','easy-form-builder'),

			/* translators: %1$s and %2$s are opening and closing link tags for documentation */
			"msgnml" => $state  &&  isset($ac->text->msgnml) ? $ac->text->msgnml : esc_html__('To explore the full functionality and settings of Easy Form Builder, including email configurations, form creation options, and other features, simply delve into our %1$s documentation %2$s .','easy-form-builder'),

			"rspcon" => $state  &&  isset($ac->text->rspcon) ? $ac->text->rspcon : esc_html__('Responses & Confirmation','easy-form-builder'),

			/* translators: %1$s, %2$s, %3$s, %4$s are opening and closing link tags for help resources */
			"mlntip" => $state  &&  isset($ac->text->mlntip) ? $ac->text->mlntip : esc_html__('Test emails may land in your spam folder. If emails are not delivered, this is usually related to your hosting or email server configuration.server settings %1$sLearn how to fix email delivery issues%2$s or %3$scontact Easy Form Builder support%4$s.','easy-form-builder'),
			"from" => $state  &&  isset($ac->text->from) ? $ac->text->from : esc_html__('From Address','easy-form-builder'),
			"msgfml" => $state  &&  isset($ac->text->msgfml) ? $ac->text->msgfml : esc_html__('Make sure this email address matches the one configured in your SMTP settings to prevent email delivery issues.','easy-form-builder'),
			"prsm" => $state  &&  isset($ac->text->prsm) ? $ac->text->prsm : esc_html__('To preview the form, you need to save the built form and try again.','easy-form-builder'),
			"nsrf" => $state  &&  isset($ac->text->nsrf) ? $ac->text->nsrf : esc_html__('No selected rows found.','easy-form-builder'),
			"spprt" => $state  &&  isset($ac->text->spprt) ? $ac->text->spprt : esc_html__('Support','easy-form-builder'),
			"mread" => $state  &&  isset($ac->text->mread) ? $ac->text->mread : esc_html__('Mark as Read','easy-form-builder'),
			"admines" => $state  &&  isset($ac->text->admines) ? $ac->text->admines : esc_html__('Require admin login to view responses','easy-form-builder'),
			"devMode" => $state  &&  isset($ac->text->devMode) ? $ac->text->devMode : esc_html__('Development Mode (Sandbox)','easy-form-builder'),
			"devModeDesc" => $state  &&  isset($ac->text->devModeDesc) ? $ac->text->devModeDesc : esc_html__('When enabled, uses sandbox/demo servers for PayPal and other services. Disable for production.','easy-form-builder'),
			"devModeWarn" => $state  &&  isset($ac->text->devModeWarn) ? $ac->text->devModeWarn : esc_html__('After changing the Development Mode (Sandbox) option, save the settings, then deactivate and reactivate Easy Form Builder plugin from the Plugins page for the changes to take effect.','easy-form-builder'),

			/* translators: %1$s and %2$s are opening and closing link tags for terms and conditions */
			"trmcn" => $state  &&  isset($ac->text->trmcn) ? $ac->text->trmcn : esc_html__('I have read and agree to %1$sthe terms and conditions%2$s','easy-form-builder'),
			"trmCheckbox" => $state  &&  isset($ac->text->trmCheckbox) ? $ac->text->trmCheckbox : esc_html__('Terms','easy-form-builder'),
			"prvnt" => $state  &&  isset($ac->text->prvnt) ? $ac->text->prvnt : esc_html__('Preview in new tab','easy-form-builder'),
			"mxdt" => $state  &&  isset($ac->text->mxdt) ? $ac->text->mxdt : esc_html__('Maximum date','easy-form-builder'),
			"mindt" => $state  &&  isset($ac->text->mindt) ? $ac->text->mindt : esc_html__('Minimum date','easy-form-builder'),

			/* translators: %s is the list of valid file formats */
			"ivf" => $state  &&  isset($ac->text->ivf) ? $ac->text->ivf : esc_html__('Valid formats: %s','easy-form-builder'),
			"zoom" => $state  &&  isset($ac->text->zoom) ? $ac->text->zoom : esc_html__('Zoom','easy-form-builder'),
			/* translators: CDN = Content Delivery Network - a service that loads files faster; leafletjs.com is a mapping library; unpkg.com is a JavaScript file hosting service */
			"lpds" => $state  &&  isset($ac->text->lpds) ? $ac->text->lpds : esc_html__('This is the best version. The em dash creates a natural pause that makes "only on pages where this feature is used" land as a reassuring afterthought — exactly the tone you want for a privacy/transparency notice. It reads more conversationally than the other two options.','easy-form-builder'),
			"elpo" => $state  &&  isset($ac->text->elpo) ? $ac->text->elpo : esc_html__('Enable Location Picker in Easy Form Builder','easy-form-builder'),
			"jqinl" => $state  &&  isset($ac->text->jqinl) ? $ac->text->jqinl : esc_html__('Easy Form Builder cannot display the form because jQuery is not properly loaded. This issue might be due to incorrect jQuery invocation by another plugin or the current website theme.','easy-form-builder'),

			'tlgm' => $state  &&  isset($ac->text->tlgm) ? $ac->text->tlgm : esc_html__('Telegram','easy-form-builder'),
			"tlgmAddon" => $state  &&  isset($ac->text->tlgmAddon) ? $ac->text->tlgmAddon : esc_html__('Telegram notification Addon','easy-form-builder'),
			"tlgmDAddon" => $state  &&  isset($ac->text->tlgmDAddon) ? $ac->text->tlgmDAddon : esc_html__('The Telegram notification addon lets you get notifications on your Telegram app whenever you receive new messages or responses','easy-form-builder'),
			"eln" => $state  &&  isset($ac->text->eln) ? $ac->text->eln : esc_html__('Enter a location name','easy-form-builder'),

			/* translators: %1$s is the plugin name, %2$s and %3$s are opening and closing link tags for support */
			"alns" => $state  &&  isset($ac->text->alns) ? $ac->text->alns : esc_html__('The %1$s pages are currently unavailable. It looks like another plugin is causing a conflict with %1$s . To fix this issue, %2$s contact %1$s support %3$s for assistance or try disabling your plugins one at a time to identify the one causing the conflict.','easy-form-builder'),

			/* translators: %s is the notification type (e.g., Email, SMS, Telegram) */
			"notis" => $state  &&  isset($ac->text->noti) ? $ac->text->noti : esc_html__('%s notification','easy-form-builder'),
			"settings" => $state  &&  isset($ac->text->settings) ? $ac->text->settings : esc_html__('Settings','easy-form-builder'),
			"emlcc" => $state  &&  isset($ac->text->emlcc) ? $ac->text->emlcc : esc_html__('Send email with submitted form content only','easy-form-builder'),
			"copied" => $state  &&  isset($ac->text->copied) ? $ac->text->copied : esc_html__('%s copied!','easy-form-builder'),
			"srvnrsp" => $state  &&  isset($ac->text->srvnrsp) ? $ac->text->srvnrsp : esc_html__('The website is not responding; please refresh and try again—saving or submitting is not available until it is restored.','easy-form-builder'),

			"ecnr" => $state  &&  isset($ac->text->ecnr) ? $ac->text->ecnr : esc_html__('Hi %s, %s your account has been successfully created! To get started, please verify your email address by clicking the link below. This activation link will be valid for 24 hours. %s %s %s %s','easy-form-builder'),
			"ecrp" => $state  &&  isset($ac->text->ecrp) ? $ac->text->ecrp : esc_html__('Hi %s, %s you have requested to reset your password. To reset your password, please click the link below. This link will be valid for 24 hours. If the link expires, you can request a new one through our website. %s %s %s %s','easy-form-builder'),
			"udnrtun" => $state  &&  isset($ac->text->udnrtun) ? $ac->text->udnrtun : esc_html__('If you did not request this, you don\'t need to do anything further.','easy-form-builder'),
			"sxnlex" => $state  &&  isset($ac->text->sxnlex) ? $ac->text->sxnlex : esc_html__('Your session has expired or is no longer valid. Please refresh the page to continue.','easy-form-builder'),
			"uraatn" => $state  &&  isset($ac->text->uraatn) ? $ac->text->uraatn : esc_html__('Your account has been successfully activated. You can now log in and get started!','easy-form-builder'),
			/* translators: Success message indicating completion */
			"yad" => $state  &&  isset($ac->text->yad) ? $ac->text->yad : esc_html__('You\'re all done','easy-form-builder'),
			"servpss" => $state  &&  isset($ac->text->servpss) ? $ac->text->servpss : esc_html__('Enter your email to reset your password','easy-form-builder'),
			"imvpwsy" => $state  &&  isset($ac->text->imvpwsy) ? $ac->text->imvpwsy : esc_html__('If your email is valid, a password reset link has been sent to your email address.','easy-form-builder'),
			/* translators: %s is the feature name being enabled (e.g., SMS, Email, Auto-Populate) */
			"enbl" => $state  &&  isset($ac->text->enbl) ? $ac->text->enbl : esc_html__('Enable %s','easy-form-builder'),
			"atfll" => $state  &&  isset($ac->text->atfll) ? $ac->text->atfll : esc_html__('Auto-Populate','easy-form-builder'),
			"atflls" => $state  &&  isset($ac->text->atflls) ? $ac->text->atflls : esc_html__('Auto-Populates','easy-form-builder'),
			"atflldm" => $state  &&  isset($ac->text->atflldm) ? $ac->text->atflldm : esc_html__('Auto-populate from previously submitted forms','easy-form-builder'),
			"atflltm" => $state  &&  isset($ac->text->atflltm) ? $ac->text->atflltm : esc_html__('Enable Auto-Populate to automatically populate this field','easy-form-builder'),
			"atfllApiActive" => $state && isset($ac->text->atfllApiActive) ? $ac->text->atfllApiActive : esc_html__('API Auto-Populate Integration is Active','easy-form-builder'),
			"atfllApiActiveDesc" => $state && isset($ac->text->atfllApiActiveDesc) ? $ac->text->atfllApiActiveDesc : esc_html__('This form uses External API Auto-Populate. To configure settings, go to','easy-form-builder'),
			"atfllApiLink" => $state && isset($ac->text->atfllApiLink) ? $ac->text->atfllApiLink : esc_html__('Auto-Populate Integrations','easy-form-builder'),

			"selectFormTitle" => $state && isset($ac->text->selectFormTitle) ? $ac->text->selectFormTitle : esc_html__('Select Form', 'easy-form-builder'),
			"targetForm" => $state && isset($ac->text->targetForm) ? $ac->text->targetForm : esc_html__('Target Form', 'easy-form-builder'),
			"selectForm" => $state && isset($ac->text->selectForm) ? $ac->text->selectForm : esc_html__('Select a Form', 'easy-form-builder'),
			"targetFormHelp" => $state && isset($ac->text->targetFormHelp) ? $ac->text->targetFormHelp : esc_html__('Select the form that will receive data from the API', 'easy-form-builder'),
			"searchFieldsTitle" => $state && isset($ac->text->searchFieldsTitle) ? $ac->text->searchFieldsTitle : esc_html__('Search Fields (Trigger Fields)', 'easy-form-builder'),
			"searchFieldsInfo" => $state && isset($ac->text->searchFieldsInfo) ? $ac->text->searchFieldsInfo : esc_html__('Select the form fields that will trigger the API search. When user types in these fields, the API will be called.', 'easy-form-builder'),
			"selectFormFirst" => $state && isset($ac->text->selectFormFirst) ? $ac->text->selectFormFirst : esc_html__('Please select a form first', 'easy-form-builder'),
			"targetFieldsTitle" => $state && isset($ac->text->targetFieldsTitle) ? $ac->text->targetFieldsTitle : esc_html__('Target Fields (Fields to Fill)', 'easy-form-builder'),
			"targetFieldsInfo" => $state && isset($ac->text->targetFieldsInfo) ? $ac->text->targetFieldsInfo : esc_html__('Map API response fields to form fields. The API data will automatically fill these fields.', 'easy-form-builder'),
			"noFieldsFound" => $state && isset($ac->text->noFieldsFound) ? $ac->text->noFieldsFound : esc_html__('No fillable fields found in this form', 'easy-form-builder'),
			"apiFieldName" => $state && isset($ac->text->apiFieldName) ? $ac->text->apiFieldName : esc_html__('API Field Name', 'easy-form-builder'),
			"formFieldSelect" => $state && isset($ac->text->formFieldSelect) ? $ac->text->formFieldSelect : esc_html__('Form Field', 'easy-form-builder'),
			"selectField" => $state && isset($ac->text->selectField) ? $ac->text->selectField : esc_html__('Select Field', 'easy-form-builder'),
			"cacheSettings" => $state && isset($ac->text->cacheSettings) ? $ac->text->cacheSettings : esc_html__('Cache Settings', 'easy-form-builder'),
			"cacheHelp" => $state && isset($ac->text->cacheHelp) ? $ac->text->cacheHelp : esc_html__('Cache API responses to improve performance', 'easy-form-builder'),
			"externalApi" => $state && isset($ac->text->externalApi) ? $ac->text->externalApi : esc_html__('External API Connections', 'easy-form-builder'),
			"apiIntroTitle" => $state && isset($ac->text->apiIntroTitle) ? $ac->text->apiIntroTitle : esc_html__('Connect Your Forms to External APIs', 'easy-form-builder'),
			"apiIntroDesc" => $state && isset($ac->text->apiIntroDesc) ? $ac->text->apiIntroDesc : esc_html__('Easily auto-populate your form fields with data from any API. Just add your API endpoint and map the fields!', 'easy-form-builder'),
			"addNewApi" => $state && isset($ac->text->addNewApi) ? $ac->text->addNewApi : esc_html__('Add API Connection', 'easy-form-builder'),
			/* translators: %s is the file type (e.g., Image, Document, ZIP, JPEG PNG ...) */
			"uplsf" => $state  &&  isset($ac->text->uplsf) ? $ac->text->uplsf : esc_html__('Upload the %s file','easy-form-builder'),
			"csv" => $state  &&  isset($ac->text->csv) ? $ac->text->csv : esc_html__('CSV','easy-form-builder'),
			/* translators: Dataset = collection of data/information */
			"datas" => $state  &&  isset($ac->text->datas) ? $ac->text->datas : esc_html__('Dataset','easy-form-builder'),
			/* translators: %s is the action or process that was successfully completed */
			"tshbc" => $state  &&  isset($ac->text->tshbc) ? $ac->text->tshbc : esc_html__('The %s has been successfully completed','easy-form-builder'),
			"rename" => $state  &&  isset($ac->text->rename) ? $ac->text->rename : esc_html__('Rename','easy-form-builder'),
			"source" => $state  &&  isset($ac->text->source) ? $ac->text->source : esc_html__('Source','easy-form-builder'),
			/* translators: %s is the item that was not found (e.g., File, Form, Response) */
			"snotfound" => $state  &&  isset($ac->text->snotfound) ? $ac->text->snotfound : esc_html__('%s not found','easy-form-builder'),
			/* translators: %s is the location name of places or cities */
			"slocation" => $state  &&  isset($ac->text->slocation) ? $ac->text->slocation : esc_html__('%s Location','easy-form-builder'),
			"installation" => $state  &&  isset($ac->text->installation) ? $ac->text->installation : esc_html__('installation','easy-form-builder'),
			/* translators: %s is the item type that was deleted (e.g., files, forms, messages) */
			"tDeleted" => $state && isset($ac->text->tDeleted) ? $ac->text->tDeleted : esc_html__('The %s have been deleted.','easy-form-builder'),
			/* translators: %s is the label name of field that must be filled correctly (e.g., Email, Password) */
			"sfmcfop" => $state  &&  isset($ac->text->sfmcfop) ? $ac->text->sfmcfop : esc_html__('The %s field must be correctly filled out to proceed.','easy-form-builder'),
			"fform" => $state  &&  isset($ac->text->fform) ? $ac->text->fform : esc_html__('Submitted Form','easy-form-builder'),
			"paymentNcaptcha" => $state  &&  isset($ac->text->paymentNcaptcha) ? $ac->text->paymentNcaptcha : esc_html__('You can\'t add reCAPTCHA to payment forms.','easy-form-builder'),

			/* translators: %s is the feature name */
			"lmavt" => $state  &&  isset($ac->text->lmavt) ? $ac->text->lmavt : esc_html__('Learn more about %s or watch the video tutorial.','easy-form-builder'),

			"lrnmrs" => $state  &&  isset($ac->text->lrnmrs) ? $ac->text->lrnmrs : esc_html__('Learn more %s','easy-form-builder'),

			"grecaptcha" => $state  &&  isset($ac->text->grecaptcha) ? $ac->text->grecaptcha : esc_html__('Google reCAPTCHA','easy-form-builder'),

			"srvnsave" => $state  &&  isset($ac->text->srvnsave) ? $ac->text->srvnsave : esc_html__('The connection was interrupted, but don\'t worry—your edits are safely stored in your browser. Refresh the page to continue working.','easy-form-builder'),

			"rasfmb" => $state  &&  isset($ac->text->rasfmb) ? $ac->text->rasfmb : esc_html__('There is an auto-saved version of the form available. Do you want to restore it?','easy-form-builder'),
			"smsWPN" => $state  &&  isset($ac->text->smsWPN) ? $ac->text->smsWPN : esc_html__('SMS notification could not be sent. Check if the SMS plugin is installed and configured properly.','easy-form-builder'),
			/* translators: %1$s and %2$s are HTML tags for formatting the success message */
			"msgSndBut" => $state && isset($ac->text->msgSndBut) ? $ac->text->msgSndBut : esc_html__('Your request was completed successfully. %1$s %2$s', 'easy-form-builder'),
			"paypal" => $state  &&  isset($ac->text->paypal) ? $ac->text->paypal : esc_html__('PayPal','easy-form-builder'),
			/* translators: %1$s is the payment service name (e.g., Stripe, PayPal), %2$s is the key type (e.g., API, Public) */
			"ufinyf" => $state  &&  isset($ac->text->ufinyf) ? $ac->text->ufinyf : esc_html__('To use %1$s features in your forms, you need to get your %2$s keys.','easy-form-builder'),
			"payment" => $state  &&  isset($ac->text->payment) ? $ac->text->payment : esc_html__('Payment','easy-form-builder'),
			/* translators: %s: Add-on name */
			"INAddonMsg" => $state  &&  isset($ac->text->INAddonMsg) ? $ac->text->INAddonMsg : esc_html__('Go to the add-ons page in the Easy Form Builder plugin, install the %s add-on, and try again.','easy-form-builder'),
			/* translators: %s: Payment add-on name */
			"IMAddonPMsg" => $state && isset($ac->text->IMAddonPMsg) ? $ac->text->IMAddonPMsg  : esc_html__('To create a payment form to collect online payments, install a payment add-on such as the %s Add-on first.', 'easy-form-builder'),
			"activated" => $state  &&  isset($ac->text->activated) ? $ac->text->activated : esc_html__('Activated','easy-form-builder'),
			"thank" => $state  &&  isset($ac->text->thank) ? $ac->text->thank : esc_html__('Thank','easy-form-builder'),

			/* translators: %s is the caching plugin name */
			"excefb" => $state  &&  isset($ac->text->excefb) ? $ac->text->excefb : esc_html__('The %s plugin may interfere with Easy Form Builder form functionality. If you encounter any issues with the forms, disable caching for the Easy Form Builder plugin in the %s settings.','easy-form-builder'),
			"rmndltr" => $state  &&  isset($ac->text->rmndltr) ? $ac->text->rmndltr : esc_html__('Remind me later','easy-form-builder'),
			"gotitdsmss" => $state  &&  isset($ac->text->gotitdsmss) ? $ac->text->gotitdsmss : esc_html__('Got it, don\'t show again','easy-form-builder'),
			/* translators: %1$s is the field name, %2$s is the minimum character count */
			"ptrnMmm_" => $state  &&  isset($ac->text->ptrnMmm_) ? $ac->text->ptrnMmm_ : esc_html__('The value of the %1$s field does not match the pattern and must be at least %2$s characters.','easy-form-builder'),
			/* translators: %1$s is the field name, %2$s is the maximum character count */
			"ptrnMmx_" => $state  &&  isset($ac->text->ptrnMmx_) ? $ac->text->ptrnMmx_ : esc_html__('The value of the %1$s field does not match the pattern and must be at most %2$s characters.','easy-form-builder'),
			/* translators: %s is the field name[Label name of the field] */
			"mnvvXXX_" => $state  &&  isset($ac->text->mnvvXXX_) ? $ac->text->mnvvXXX_ : esc_html__('Please enter valid value for the %s field.','easy-form-builder'),
			/* translators: %s is the list name */
			"list_" => $state  &&  isset($ac->text->list_) ? $ac->text->list_ : esc_html__('%s list','easy-form-builder'),
			/* translators: %s is the file size in MB */
			"fSiz_l_dy_" => $state &&  isset($ac->text->fSiz_l_dy_) ? $ac->text->fSiz_l_dy_ : esc_html__('The uploaded file exceeds the allowable limit of %s MB.','easy-form-builder'),
			/* translators: %s is the file size in MB */
			"fSiz_s_dy_" => $state &&  isset($ac->text->fSiz_s_dy_) ? $ac->text->fSiz_s_dy_ : esc_html__('The uploaded file is below the required minimum size of %s MB.','easy-form-builder'),
			/* translators: %s is the tab name of the settings in the panel Easy Form Builder */
			"msgchckvt_" => $state  &&  isset($ac->text->msgchckvt_) ? $ac->text->msgchckvt_ : esc_html__('Review the entered values in the %s tab. This message appeared because an error was detected.','easy-form-builder'),
			/* translators: %s is the item being duplicated (e.g., form, field) */
			"ausdup_" => $state  &&  isset($ac->text->ausdup_) ? $ac->text->ausdup_ : esc_html__('Are you sure you want to duplicate the "%s" ?','easy-form-builder'),
			/* translators: %s is the option name */
			"bkXpM_" => $state  &&  isset($ac->text->bkXpM_) ? $ac->text->bkXpM_ : esc_html__('We are sorry, the booking time for the %s option has expired. Please choose from the other available options.','easy-form-builder'),
			/* translators: %s is the option name */
			"bkFlM_" => $state  &&  isset($ac->text->bkFlM_) ? $ac->text->bkFlM_ : esc_html__('We are sorry, the %s option is currently at full capacity. Please choose from the other available options.','easy-form-builder'),
			/* translators: %s is the payment add-on name like Stripe and Paypal */
			"sSTAddon" => $state  &&  isset($ac->text->sSTAddon) ? $ac->text->sSTAddon : esc_html__('%s Payment Addon','easy-form-builder'),
			/* translators: %1$s is the payment add-on name, %2$s is the payment processor name */
			"sSTDAddon" => $state  &&  isset($ac->text->sSTDAddon) ? $ac->text->sSTDAddon : esc_html__('The %s add-on for Easy Form Builder enables you to integrate your WordPress site with %s for payment processing, donations, and online orders.','easy-form-builder'),
			/* translators: Activation code = license key. */
			'activationCode' => $state  &&  isset($ac->text->activationCode) ? $ac->text->activationCode : esc_html__('Activation Code','easy-form-builder'),

			/* translators: Message indicating a feature is available in Free Plus or Pro versions */
			'thisFeatureAvailableFreePlusPro' => $state && isset($ac->text->thisFeatureAvailableFreePlusPro) ? $ac->text->thisFeatureAvailableFreePlusPro : esc_html__('Want to use this feature? It is included in Free Plus and Pro plans.','easy-form-builder'),

			/* translators: Button text for Free Plus Guide  (link to https://easyformbuilder.com/document/easy-form-builder-free-plus-activation-guide/) */
			'freePlusActivation' => $state && isset($ac->text->freePlusActivation) ? $ac->text->freePlusActivation : esc_html__('Free Plus Guide','easy-form-builder'),

			/* translators: Search Details - header for detailed search information */
			'searchDetails' => $state && isset($ac->text->searchDetails) ? $ac->text->searchDetails : esc_html__('Search Details','easy-form-builder'),

			/* translators: Search Results - header for search results */
			'searchResults' => $state && isset($ac->text->searchResults) ? $ac->text->searchResults : esc_html__('Search Results','easy-form-builder'),

			/* translators: Text for search result count message */
			'foundResultsFor' => $state && isset($ac->text->foundResultsFor) ? $ac->text->foundResultsFor : esc_html__('Found %s %s for: "%s"','easy-form-builder'),

			/* translators: Single result text */
			'result' => $state && isset($ac->text->result) ? $ac->text->result : esc_html__('result','easy-form-builder'),

			/* translators: Multiple results text */
			'results' => $state && isset($ac->text->results) ? $ac->text->results : esc_html__('results','easy-form-builder'),

			/* translators: No results found message */
			'noResultsFound' => $state && isset($ac->text->noResultsFound) ? $ac->text->noResultsFound : esc_html__('No results found for:','easy-form-builder'),

			/* translators: Forbidden characters error message */
			'forbiddenCharacters' => $state && isset($ac->text->forbiddenCharacters) ? $ac->text->forbiddenCharacters : esc_html__('Forbidden characters:','easy-form-builder'),

			/* translators: Search details modal title */
			'searchDetailsTitle' => $state && isset($ac->text->searchDetailsTitle) ? $ac->text->searchDetailsTitle : esc_html__('Search Details','easy-form-builder'),

			/* translators: Template for found results text with placeholders - %1$s is result count, %2$s is result/results text */
			'foundResultsText' => $state && isset($ac->text->foundResultsText) ? $ac->text->foundResultsText : esc_html__('Found %1$s %2$s for','easy-form-builder'),

			/* translators: Session Duration = title for nonce/session expiration settings */
			"sessionDuration" => $state && isset($ac->text->sessionDuration) ? $ac->text->sessionDuration : esc_html__('Session Duration','easy-form-builder'),

			/* translators: Nonce Expiration = subtitle for form security token expiration */
			"nonceExpiration" => $state && isset($ac->text->nonceExpiration) ? $ac->text->nonceExpiration : esc_html__('Form Security Token Expiration','easy-form-builder'),

			/* translators: Session Duration Description = explanation of session duration setting */
			"sessionDurationDesc" => $state && isset($ac->text->sessionDurationDesc) ? $ac->text->sessionDurationDesc : esc_html__('Set how long form security tokens remain valid. Longer durations provide better user experience but may reduce security.','easy-form-builder'),

			/* translators: %s Day = singular form for day count in session duration (e.g., "1 Day") */
			"sessionDurationDay" => $state && isset($ac->text->sessionDurationDay) ? $ac->text->sessionDurationDay : esc_html__('%s Day','easy-form-builder'),

			/* translators: %s Days = plural form for day count in session duration (e.g., "2 Days") */
			"sessionDurationDays" => $state && isset($ac->text->sessionDurationDays) ? $ac->text->sessionDurationDays : esc_html__('%s Days','easy-form-builder'),

			/* translators: Select Duration = placeholder text for session duration dropdown */
			"selectDuration" => $state && isset($ac->text->selectDuration) ? $ac->text->selectDuration : esc_html__('Select Duration','easy-form-builder'),

			/* translators: %s is the feature name (e.g., Confirmation Code) */
			"trackCodeStyleDesc" => $state && isset($ac->text->trackCodeStyleDesc) ? $ac->text->trackCodeStyleDesc : esc_html__('Choose the style for the %s.','easy-form-builder'),

			/* translators: %1$s + %2$s = pattern for composing code option labels like "Date + Random Numbers" */
			"trackCodeDatePlus" => $state && isset($ac->text->trackCodeDatePlus) ? $ac->text->trackCodeDatePlus : esc_html__('%1$s + %2$s','easy-form-builder'),

			/* translators: %1$s %2$s & %3$s = pattern for composing labels like "Local Letters & Numbers" */
			"trackCodeTriple" => $state && isset($ac->text->trackCodeTriple) ? $ac->text->trackCodeTriple : esc_html__('%1$s %2$s & %3$s','easy-form-builder'),

			/* translators: Letters = alphabet characters */
			"tLetters" => $state && isset($ac->text->tLetters) ? $ac->text->tLetters : esc_html__('Letters','easy-form-builder'),

			/* translators: Unique Number = a unique numeric identifier (date-based) */
			"uniqueNum" => $state && isset($ac->text->uniqueNum) ? $ac->text->uniqueNum : esc_html__('Unique Number (date-based)','easy-form-builder'),

			"trackCodeLocalChars" => implode('', (get_locale_script_chars_efb() ?: ['alpha' => []])['alpha']),
			"trackCodeLocalDigits" => implode('', (get_locale_script_chars_efb() ?: ['digits' => null])['digits'] ?: []),

			/* translators: Colors & Fonts = heading for the color and font section */
			"respColors" => $state && isset($ac->text->respColors) ? $ac->text->respColors : esc_html__('Colors & Fonts','easy-form-builder'),

			/* translators: Description under color settings heading */
			"respColorsDesc" => $state && isset($ac->text->respColorsDesc) ? $ac->text->respColorsDesc : esc_html__('Customize colors and fonts of the response viewer to match your brand.','easy-form-builder'),

			/* translators: Primary Color = label for main brand color picker */
			"respClrPrimary" => $state && isset($ac->text->respClrPrimary) ? $ac->text->respClrPrimary : esc_html__('Primary','easy-form-builder'),

			/* translators: Primary Dark Color = label for dark variant of primary color */
			"respClrPrimaryDk" => $state && isset($ac->text->respClrPrimaryDk) ? $ac->text->respClrPrimaryDk : esc_html__('Primary Dark','easy-form-builder'),

			/* translators: Accent Color = label for highlight/accent color */
			"respClrAccent" => $state && isset($ac->text->respClrAccent) ? $ac->text->respClrAccent : esc_html__('Accent','easy-form-builder'),

			/* translators: Text Color = label for main text color */
			"respClrText" => $state && isset($ac->text->respClrText) ? $ac->text->respClrText : esc_html__('Text','easy-form-builder'),

			/* translators: Muted Text = label for secondary/muted text color */
			"respClrMuted" => $state && isset($ac->text->respClrMuted) ? $ac->text->respClrMuted : esc_html__('Muted Text','easy-form-builder'),

			/* translators: Card Background = label for card background color */
			"respClrBgCard" => $state && isset($ac->text->respClrBgCard) ? $ac->text->respClrBgCard : esc_html__('Card Background','easy-form-builder'),

			/* translators: Meta Background = label for meta bar background color */
			"respClrBgMeta" => $state && isset($ac->text->respClrBgMeta) ? $ac->text->respClrBgMeta : esc_html__('Meta Background','easy-form-builder'),

			/* translators: Reset Colors = button label to restore default colors */
			"respClrReset" => $state && isset($ac->text->respClrReset) ? $ac->text->respClrReset : esc_html__('Reset to Defaults','easy-form-builder'),

			/* translators: Customize Colors = button label to open the color customization modal */
			"respClrCustomize" => $state && isset($ac->text->respClrCustomize) ? $ac->text->respClrCustomize : esc_html__('Customize Colors','easy-form-builder'),

			/* translators: Live Preview = label shown on the live preview section in color modal */
			"respClrPreview" => $state && isset($ac->text->respClrPreview) ? $ac->text->respClrPreview : esc_html__('Live Preview','easy-form-builder'),

			/* translators: Tracker Background = label for tracker section background color */
			"respClrBgTrack" => $state && isset($ac->text->respClrBgTrack) ? $ac->text->respClrBgTrack : esc_html__('Tracker Background','easy-form-builder'),

			/* translators: Response Area Background = label for chat/response area background */
			"respClrBgResp" => $state && isset($ac->text->respClrBgResp) ? $ac->text->respClrBgResp : esc_html__('Response Area Background','easy-form-builder'),

			/* translators: Editor Background = label for rich editor background color */
			"respClrBgEditor" => $state && isset($ac->text->respClrBgEditor) ? $ac->text->respClrBgEditor : esc_html__('Editor Background','easy-form-builder'),

			/* translators: Editor Text = label for rich editor text/value color */
			"respClrEditorText" => $state && isset($ac->text->respClrEditorText) ? $ac->text->respClrEditorText : esc_html__('Editor Text','easy-form-builder'),

			/* translators: Placeholder = label for editor placeholder color */
			"respClrEditorPh" => $state && isset($ac->text->respClrEditorPh) ? $ac->text->respClrEditorPh : esc_html__('Placeholder','easy-form-builder'),

			/* translators: Button Text = label for button text color */
			"respClrBtnText" => $state && isset($ac->text->respClrBtnText) ? $ac->text->respClrBtnText : esc_html__('Button Text','easy-form-builder'),

			/* translators: Font Family = label for font family selector */
			"respFontFamily" => $state && isset($ac->text->respFontFamily) ? $ac->text->respFontFamily : esc_html__('Font Family','easy-form-builder'),

			/* translators: Font Size = label for font size selector */
			"respFontSize" => $state && isset($ac->text->respFontSize) ? $ac->text->respFontSize : esc_html__('Font Size','easy-form-builder'),

			/* translators: Custom Font = label for custom font input */
			"respCustomFont" => $state && isset($ac->text->respCustomFont) ? $ac->text->respCustomFont : esc_html__('Custom Font','easy-form-builder'),

			/* translators: Font Name = placeholder for custom font name input */
			"respCustomFontName" => $state && isset($ac->text->respCustomFontName) ? $ac->text->respCustomFontName : esc_html__('Font Name','easy-form-builder'),

			/* translators: Font URL = placeholder for custom font URL input */
			"respCustomFontUrl" => $state && isset($ac->text->respCustomFontUrl) ? $ac->text->respCustomFontUrl : esc_html__('Font URL (CSS/Google Fonts)','easy-form-builder'),

			/* translators: Add Custom Font description */
			"respCustomFontDesc" => $state && isset($ac->text->respCustomFontDesc) ? $ac->text->respCustomFontDesc : esc_html__('Add your own font by entering the font name and its CSS URL (e.g. Google Fonts link).','easy-form-builder'),

			/* translators: Plan Management = heading for the plan/subscription management section in settings */
			"plnMng" => $state && isset($ac->text->plnMng) ? $ac->text->plnMng : esc_html__('Plan Management','easy-form-builder'),

			/* translators: Description text under Plan Management heading */
			"plnMngD" => $state && isset($ac->text->plnMngD) ? $ac->text->plnMngD : esc_html__('Choose a plan or upgrade to unlock advanced features.','easy-form-builder'),

			/* translators: Change Plan = button label to switch subscription plan */
			"chngPln" => $state && isset($ac->text->chngPln) ? $ac->text->chngPln : esc_html__('Change Plan','easy-form-builder'),

			/* translators: Description under Change Plan button - explains clicking opens plan selection */
			"plnMngSw" => $state && isset($ac->text->plnMngSw) ? $ac->text->plnMngSw : esc_html__('Click to view and choose from Free, Free Plus, or Pro plans.','easy-form-builder'),

			/* translators: Current Plan = label showing the user's active plan */
			"crntPln" => $state && isset($ac->text->crntPln) ? $ac->text->crntPln : esc_html__('Current Plan','easy-form-builder'),

			/* translators: Block type labels for drag-and-drop email builder */
			"ebHeader" => $state && isset($ac->text->ebHeader) ? $ac->text->ebHeader : esc_html__('Header','easy-form-builder'),
			"ebLogoImage" => $state && isset($ac->text->ebLogoImage) ? $ac->text->ebLogoImage : esc_html__('Logo / Image','easy-form-builder'),
			"ebTitle" => $state && isset($ac->text->ebTitle) ? $ac->text->ebTitle : esc_html__('Title','easy-form-builder'),
			"ebTextBlock" => $state && isset($ac->text->ebTextBlock) ? $ac->text->ebTextBlock : esc_html__('Text Block','easy-form-builder'),
			"ebMessageContent" => $state && isset($ac->text->ebMessageContent) ? $ac->text->ebMessageContent : esc_html__('Message Content','easy-form-builder'),
			"ebButton" => $state && isset($ac->text->ebButton) ? $ac->text->ebButton : esc_html__('Button','easy-form-builder'),
			"ebDivider" => $state && isset($ac->text->ebDivider) ? $ac->text->ebDivider : esc_html__('Divider','easy-form-builder'),
			"ebSpacer" => $state && isset($ac->text->ebSpacer) ? $ac->text->ebSpacer : esc_html__('Spacer','easy-form-builder'),
			"ebImage" => $state && isset($ac->text->ebImage) ? $ac->text->ebImage : esc_html__('Image','easy-form-builder'),
			"ebTwoColumns" => $state && isset($ac->text->ebTwoColumns) ? $ac->text->ebTwoColumns : esc_html__('Two Columns','easy-form-builder'),
			"ebSocialLinks" => $state && isset($ac->text->ebSocialLinks) ? $ac->text->ebSocialLinks : esc_html__('Social Links','easy-form-builder'),
			"ebFooter" => $state && isset($ac->text->ebFooter) ? $ac->text->ebFooter : esc_html__('Footer','easy-form-builder'),
			"ebCustomHTML" => $state && isset($ac->text->ebCustomHTML) ? $ac->text->ebCustomHTML : esc_html__('Custom HTML','easy-form-builder'),

			/* translators: Template labels for email builder */
			"ebProfessional" => $state && isset($ac->text->ebProfessional) ? $ac->text->ebProfessional : esc_html__('Professional','easy-form-builder'),
			"ebModernDark" => $state && isset($ac->text->ebModernDark) ? $ac->text->ebModernDark : esc_html__('Modern Dark','easy-form-builder'),
			"ebMinimalClean" => $state && isset($ac->text->ebMinimalClean) ? $ac->text->ebMinimalClean : esc_html__('Minimal Clean','easy-form-builder'),
			"ebElegant" => $state && isset($ac->text->ebElegant) ? $ac->text->ebElegant : esc_html__('Elegant','easy-form-builder'),
			"ebColorful" => $state && isset($ac->text->ebColorful) ? $ac->text->ebColorful : esc_html__('Colorful','easy-form-builder'),

			/* translators: Category labels for email builder blocks panel */
			"ebCatLayout" => $state && isset($ac->text->ebCatLayout) ? $ac->text->ebCatLayout : esc_html__('Layout','easy-form-builder'),
			"ebCatContent" => $state && isset($ac->text->ebCatContent) ? $ac->text->ebCatContent : esc_html__('Content','easy-form-builder'),
			"ebCatShortcodes" => $state && isset($ac->text->ebCatShortcodes) ? $ac->text->ebCatShortcodes : esc_html__('Shortcodes','easy-form-builder'),
			"ebCatAdvanced" => $state && isset($ac->text->ebCatAdvanced) ? $ac->text->ebCatAdvanced : esc_html__('Advanced','easy-form-builder'),

			/* translators: Canvas & UI labels for email builder */
			"ebDragBlocksHere" => $state && isset($ac->text->ebDragBlocksHere) ? $ac->text->ebDragBlocksHere : esc_html__('Drag blocks here to build your email template','easy-form-builder'),
			"ebOrChooseTemplate" => $state && isset($ac->text->ebOrChooseTemplate) ? $ac->text->ebOrChooseTemplate : esc_html__('or choose a template from the Templates panel','easy-form-builder'),
			"ebSelectBlock" => $state && isset($ac->text->ebSelectBlock) ? $ac->text->ebSelectBlock : esc_html__('Select a block to edit its properties','easy-form-builder'),
			"ebMoveUp" => $state && isset($ac->text->ebMoveUp) ? $ac->text->ebMoveUp : esc_html__('Move Up','easy-form-builder'),
			"ebMoveDown" => $state && isset($ac->text->ebMoveDown) ? $ac->text->ebMoveDown : esc_html__('Move Down','easy-form-builder'),

			/* translators: Property labels for email builder properties panel */
			"ebBgColor" => $state && isset($ac->text->ebBgColor) ? $ac->text->ebBgColor : esc_html__('Background Color','easy-form-builder'),
			"ebBgCSS" => $state && isset($ac->text->ebBgCSS) ? $ac->text->ebBgCSS : esc_html__('Background (CSS)','easy-form-builder'),
			"ebPadding" => $state && isset($ac->text->ebPadding) ? $ac->text->ebPadding : esc_html__('Padding','easy-form-builder'),
			"ebImageURL" => $state && isset($ac->text->ebImageURL) ? $ac->text->ebImageURL : esc_html__('Image URL','easy-form-builder'),
			"ebWidthPx" => $state && isset($ac->text->ebWidthPx) ? $ac->text->ebWidthPx : esc_html__('Width (px)','easy-form-builder'),
			"ebAltText" => $state && isset($ac->text->ebAltText) ? $ac->text->ebAltText : esc_html__('Alt Text','easy-form-builder'),
			"ebFontSize" => $state && isset($ac->text->ebFontSize) ? $ac->text->ebFontSize : esc_html__('Font Size (px)','easy-form-builder'),
			"ebTitleText" => $state && isset($ac->text->ebTitleText) ? $ac->text->ebTitleText : esc_html__('Title Text','easy-form-builder'),
			"ebWeight" => $state && isset($ac->text->ebWeight) ? $ac->text->ebWeight : esc_html__('Weight','easy-form-builder'),
			"ebLineHeight" => $state && isset($ac->text->ebLineHeight) ? $ac->text->ebLineHeight : esc_html__('Line Height','easy-form-builder'),
			"ebButtonText" => $state && isset($ac->text->ebButtonText) ? $ac->text->ebButtonText : esc_html__('Button Text','easy-form-builder'),
			"ebLinkURL" => $state && isset($ac->text->ebLinkURL) ? $ac->text->ebLinkURL : esc_html__('Link URL','easy-form-builder'),
			"ebBackground" => $state && isset($ac->text->ebBackground) ? $ac->text->ebBackground : esc_html__('Background','easy-form-builder'),
			"ebTextColor" => $state && isset($ac->text->ebTextColor) ? $ac->text->ebTextColor : esc_html__('Text Color','easy-form-builder'),
			"ebBorderRadius" => $state && isset($ac->text->ebBorderRadius) ? $ac->text->ebBorderRadius : esc_html__('Border Radius (px)','easy-form-builder'),
			"ebInnerPadding" => $state && isset($ac->text->ebInnerPadding) ? $ac->text->ebInnerPadding : esc_html__('Inner Padding','easy-form-builder'),
			"ebOuterPadding" => $state && isset($ac->text->ebOuterPadding) ? $ac->text->ebOuterPadding : esc_html__('Outer Padding','easy-form-builder'),
			"ebThickness" => $state && isset($ac->text->ebThickness) ? $ac->text->ebThickness : esc_html__('Thickness (px)','easy-form-builder'),
			"ebWidthPercent" => $state && isset($ac->text->ebWidthPercent) ? $ac->text->ebWidthPercent : esc_html__('Width (%)','easy-form-builder'),
			"ebHeightPx" => $state && isset($ac->text->ebHeightPx) ? $ac->text->ebHeightPx : esc_html__('Height (px)','easy-form-builder'),
			"ebWidthUnit" => $state && isset($ac->text->ebWidthUnit) ? $ac->text->ebWidthUnit : esc_html__('Width Unit','easy-form-builder'),
			"ebLeftColumn" => $state && isset($ac->text->ebLeftColumn) ? $ac->text->ebLeftColumn : esc_html__('Left Column','easy-form-builder'),
			"ebRightColumn" => $state && isset($ac->text->ebRightColumn) ? $ac->text->ebRightColumn : esc_html__('Right Column','easy-form-builder'),
			"ebLeftTextColor" => $state && isset($ac->text->ebLeftTextColor) ? $ac->text->ebLeftTextColor : esc_html__('Left Text Color','easy-form-builder'),
			"ebRightTextColor" => $state && isset($ac->text->ebRightTextColor) ? $ac->text->ebRightTextColor : esc_html__('Right Text Color','easy-form-builder'),
			"ebGap" => $state && isset($ac->text->ebGap) ? $ac->text->ebGap : esc_html__('Gap (px)','easy-form-builder'),
			"ebLinkColor" => $state && isset($ac->text->ebLinkColor) ? $ac->text->ebLinkColor : esc_html__('Link Color','easy-form-builder'),
			"ebAddLink" => $state && isset($ac->text->ebAddLink) ? $ac->text->ebAddLink : esc_html__('Add Link','easy-form-builder'),
			"ebFooterText" => $state && isset($ac->text->ebFooterText) ? $ac->text->ebFooterText : esc_html__('Footer Text','easy-form-builder'),
			"ebHeaderChildren" => $state && isset($ac->text->ebHeaderChildren) ? $ac->text->ebHeaderChildren : esc_html__('Header Children','easy-form-builder'),
			"ebLinks" => $state && isset($ac->text->ebLinks) ? $ac->text->ebLinks : esc_html__('Links','easy-form-builder'),

			/* translators: Shortcode button labels for email builder */
			"ebInsertShortcode" => $state && isset($ac->text->ebInsertShortcode) ? $ac->text->ebInsertShortcode : esc_html__('Insert shortcode:','easy-form-builder'),
			"ebSCMessage" => $state && isset($ac->text->ebSCMessage) ? $ac->text->ebSCMessage : esc_html__('Message *','easy-form-builder'),
			"ebSCTitle" => $state && isset($ac->text->ebSCTitle) ? $ac->text->ebSCTitle : esc_html__('Title','easy-form-builder'),
			"ebSCSiteName" => $state && isset($ac->text->ebSCSiteName) ? $ac->text->ebSCSiteName : esc_html__('Site Name','easy-form-builder'),
			"ebSCSiteURL" => $state && isset($ac->text->ebSCSiteURL) ? $ac->text->ebSCSiteURL : esc_html__('Site URL','easy-form-builder'),
			"ebSCAdminEmail" => $state && isset($ac->text->ebSCAdminEmail) ? $ac->text->ebSCAdminEmail : esc_html__('Admin Email','easy-form-builder'),
			"ebSCFormData" => $state && isset($ac->text->ebSCFormData) ? $ac->text->ebSCFormData : esc_html__('Form data','easy-form-builder'),
			"ebSCFormName" => $state && isset($ac->text->ebSCFormName) ? $ac->text->ebSCFormName : esc_html__('Form name','easy-form-builder'),
			"ebSCBlogName" => $state && isset($ac->text->ebSCBlogName) ? $ac->text->ebSCBlogName : esc_html__('Blog name','easy-form-builder'),
			"ebSCHomeURL" => $state && isset($ac->text->ebSCHomeURL) ? $ac->text->ebSCHomeURL : esc_html__('Home URL','easy-form-builder'),
			"ebSCAdminEmailDesc" => $state && isset($ac->text->ebSCAdminEmailDesc) ? $ac->text->ebSCAdminEmailDesc : esc_html__('Admin email','easy-form-builder'),

			/* translators: Notification messages for email builder */
			"ebSCRequired" => $state && isset($ac->text->ebSCRequired) ? $ac->text->ebSCRequired : esc_html__('shortcode_message is required!','easy-form-builder'),
			"ebMustContainSC" => $state && isset($ac->text->ebMustContainSC) ? $ac->text->ebMustContainSC : esc_html__('Template must contain shortcode_message!','easy-form-builder'),
			"ebTemplateExported" => $state && isset($ac->text->ebTemplateExported) ? $ac->text->ebTemplateExported : esc_html__('Template exported!','easy-form-builder'),
			"ebHTMLApplied" => $state && isset($ac->text->ebHTMLApplied) ? $ac->text->ebHTMLApplied : esc_html__('HTML code applied!','easy-form-builder'),
			"ebResetConfirm" => $state && isset($ac->text->ebResetConfirm) ? $ac->text->ebResetConfirm : esc_html__('Are you sure you want to reset the email template? This cannot be undone.','easy-form-builder'),
			"ebTemplateReset" => $state && isset($ac->text->ebTemplateReset) ? $ac->text->ebTemplateReset : esc_html__('Template reset to default!','easy-form-builder'),
			"ebFormContentHere" => $state && isset($ac->text->ebFormContentHere) ? $ac->text->ebFormContentHere : esc_html__('Form content appears here','easy-form-builder'),
			"ebAddImageURL" => $state && isset($ac->text->ebAddImageURL) ? $ac->text->ebAddImageURL : esc_html__('Add image URL','easy-form-builder'),
			"ebUnknownBlock" => $state && isset($ac->text->ebUnknownBlock) ? $ac->text->ebUnknownBlock : esc_html__('Unknown block','easy-form-builder'),

			/* translators: Toolbar & sidebar labels for email builder */
			"ebUndo" => $state && isset($ac->text->ebUndo) ? $ac->text->ebUndo : esc_html__('Undo','easy-form-builder'),
			"ebRedo" => $state && isset($ac->text->ebRedo) ? $ac->text->ebRedo : esc_html__('Redo','easy-form-builder'),
			"ebExport" => $state && isset($ac->text->ebExport) ? $ac->text->ebExport : esc_html__('Export','easy-form-builder'),
			"ebBlocks" => $state && isset($ac->text->ebBlocks) ? $ac->text->ebBlocks : esc_html__('Blocks','easy-form-builder'),
			"ebProperties" => $state && isset($ac->text->ebProperties) ? $ac->text->ebProperties : esc_html__('Properties','easy-form-builder'),
			"ebHTMLSourceCode" => $state && isset($ac->text->ebHTMLSourceCode) ? $ac->text->ebHTMLSourceCode : esc_html__('HTML Source Code','easy-form-builder'),
			"ebApply" => $state && isset($ac->text->ebApply) ? $ac->text->ebApply : esc_html__('Apply','easy-form-builder'),

			/* translators: Global settings labels for email builder */
			"ebEmailBg" => $state && isset($ac->text->ebEmailBg) ? $ac->text->ebEmailBg : esc_html__('Email Background','easy-form-builder'),
			"ebContentBg" => $state && isset($ac->text->ebContentBg) ? $ac->text->ebContentBg : esc_html__('Content Background','easy-form-builder'),
			"ebContentWidth" => $state && isset($ac->text->ebContentWidth) ? $ac->text->ebContentWidth : esc_html__('Content Width (px)','easy-form-builder'),
			"ebDirection" => $state && isset($ac->text->ebDirection) ? $ac->text->ebDirection : esc_html__('Direction','easy-form-builder'),

			/* translators: Message block notice in email builder properties */
			"ebMessageNotice" => $state && isset($ac->text->ebMessageNotice) ? $ac->text->ebMessageNotice : esc_html__('This block outputs shortcode_message — the submitted form data.','easy-form-builder'),
			/* translators: Email-safe HTML notice */
			"ebNoScript" => $state && isset($ac->text->ebNoScript) ? $ac->text->ebNoScript : esc_html__('Use email-safe HTML only. No script tags.','easy-form-builder'),
			"ebBlkCount" => $state && isset($ac->text->ebBlkCount) ? $ac->text->ebBlkCount : esc_html__('blocks','easy-form-builder'),
			/* translators: Copy shortcode label with tooltip */
			"ebCopyShortcode" => $state && isset($ac->text->ebCopyShortcode) ? $ac->text->ebCopyShortcode : esc_html__('Copy shortcode','easy-form-builder'),
			"ebCopied" => $state && isset($ac->text->ebCopied) ? $ac->text->ebCopied : esc_html__('Copied!','easy-form-builder'),
			"ebSCReference" => $state && isset($ac->text->ebSCReference) ? $ac->text->ebSCReference : esc_html__('Shortcode Reference','easy-form-builder'),
			"ebSCInserted" => $state && isset($ac->text->ebSCInserted) ? $ac->text->ebSCInserted : esc_html__('Shortcode inserted!','easy-form-builder'),
			"ebSCSelectBlock" => $state && isset($ac->text->ebSCSelectBlock) ? $ac->text->ebSCSelectBlock : esc_html__('Select a text block first, or shortcode copied to clipboard.','easy-form-builder'),
			"ebSCRequired" => $state && isset($ac->text->ebSCRequired) ? $ac->text->ebSCRequired : esc_html__('Required','easy-form-builder'),
			"ebViewWebsite" => $state && isset($ac->text->ebViewWebsite) ? $ac->text->ebViewWebsite : esc_html__('View Website','easy-form-builder'),
			"ebDisclaimerText" => $state && isset($ac->text->ebDisclaimerText) ? $ac->text->ebDisclaimerText : esc_html__('This email was sent automatically. Please do not reply directly.','easy-form-builder'),
			'payments' => $state && isset($ac->text->payments) ? $ac->text->payments : esc_html__('Payments','easy-form-builder'),
			/* translators: Cache warning messages shown to admin when cache plugins detected */
			"cacheWarnTitle" => $state && isset($ac->text->cacheWarnTitle) ? $ac->text->cacheWarnTitle : esc_html__('Cache Plugin Detected','easy-form-builder'),
			"cacheWarnMsg" => $state && isset($ac->text->cacheWarnMsg) ? $ac->text->cacheWarnMsg : esc_html__('The following cache plugins may interfere with form functionality. If you experience issues, please review the documentation.','easy-form-builder'),
			"cacheWarnPlugin" => $state && isset($ac->text->cacheWarnPlugin) ? $ac->text->cacheWarnPlugin : esc_html__('Plugin','easy-form-builder'),
			"cacheWarnVersion" => $state && isset($ac->text->cacheWarnVersion) ? $ac->text->cacheWarnVersion : esc_html__('Version','easy-form-builder'),
			"cacheWarnDoc" => $state && isset($ac->text->cacheWarnDoc) ? $ac->text->cacheWarnDoc : esc_html__('Read more about cache compatibility','easy-form-builder'),

			"TAdnAtF" => $state  &&  isset($ac->text->TAdnAtF) ? $ac->text->TAdnAtF : esc_html__('Auto-Populate Addon','easy-form-builder'),
			"DAdnAtF" => $state  &&  isset($ac->text->DAdnAtF) ? $ac->text->DAdnAtF : esc_html__('The Auto-Populate addon enables you to automatically populate form fields from datasets, previously submitted forms, or external APIs.','easy-form-builder'),
			"fillrequiredfields" => $state && isset($ac->text->fillrequiredfields) ? $ac->text->fillrequiredfields : esc_html__('Please fill in all required fields', 'easy-form-builder'),

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

		if ($page_request !== 'default') {

			if (!class_exists('EfbAddonPhrases')) {
				require_once EMSFB_PLUGIN_DIRECTORY . 'includes/phrases.php';
			}

			$addon_phrases = efb_get_addon_phrases($page_request, $ac, $state);
			if (!empty($addon_phrases)) {
				$rtrn = array_merge($rtrn, $addon_phrases);
			}
		}

		wp_cache_set($efb_ck_final, $rtrn, 'efb', 7200);
		self::$req_cache[$efb_ck_final] = $rtrn;
		return $rtrn;
	}

	public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = "null") {

		if (!class_exists('EmsfbEmailHandler')) {
			$email_handler_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
			if (file_exists($email_handler_file)) {
				require_once $email_handler_file;
			} else {
				return false;
			}
		}

		$emailHandler = new EmsfbEmailHandler();
		return $emailHandler->send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st);
	}

	public function response_to_user_by_msd_id($msg_id,$pro){

		global $wpdb;
		$text = ['youRecivedNewMessage'];
        $lang= $this->text_efb($text);

		$msg_id = preg_replace('/[,]+/','',$msg_id);
		$email="null";
		$table_name =  $wpdb->prefix . "emsfb_msg_";
		$data =  $wpdb->get_results( $wpdb->prepare( "SELECT content, form_id, track FROM `{$table_name}` WHERE msg_id = %s ORDER BY msg_id DESC LIMIT 1", $msg_id ) );

		$form_id = $data[0]->form_id;
		$response_msg = $data[0]->content;
		$trackingCode = $data[0]->track;
		$response_msg  = str_replace('\\', '', $response_msg);

		$user_res = json_decode($response_msg,true);
		$lst = end($user_res);
		$link_w = $lst['type']=="w_link" ? $lst['value'].'?track='.$trackingCode : 'null';

		$table_name =  $wpdb->prefix . "emsfb_form";
		$data =  $wpdb->get_results( $wpdb->prepare( "SELECT form_structer FROM `{$table_name}` WHERE form_id = %s ORDER BY form_id DESC LIMIT 1", $form_id ) );

		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);
		if(($data[0]['sendEmail']=="true"|| $data[0]['sendEmail']==true ) &&   strlen($data[0]['email_to'])>2 ){
			$emailsId=[];
			$email_to = $data[0]["email_to"];

			foreach($data as $key=>$val){
				if($val['type']=="email" && isset($val['noti']) && in_array($val['noti'] ,[1,'1',true,'true'],true) ){
					$emailsId[]=$val['id_'];
				}else if ($val['type']=="email" &&  $val['id_']==$email_to){
					$emailsId[]=$val['id_'];
				}
			}

			$settings = get_setting_Emsfb();
			$smtp = (is_object($settings) && isset($settings->smtp) && (bool)$settings->smtp ) ? true : false;
			if($smtp) {

				$rtrn = false;
				$emails =[];
				foreach($user_res as $key=>$val){
					if(isset($user_res[$key]["id_"]) && in_array($user_res[$key]["id_"],$emailsId,true) && isset($val["value"]) && is_email($val["value"]) ){
						$email=$val["value"];
						$subject ="📮 ".$lang["youRecivedNewMessage"];
						$rtrn =$this->send_email_state_new($email ,$subject ,$trackingCode,$pro,"newMessage",$link_w,'null');
					}
				}
				return $rtrn;
			}
			return false;
		}

		if(isset($data[0]['smsnoti']) && intval($data[0]['smsnoti'])==1){

			$phone_numbers=[[],[]];
			$setting = get_setting_Emsfb();

			$numbers = is_object($setting) && isset($setting->sms_config) && isset($setting->phnNo) && strlen($setting->phnNo)>5  ? explode(',',$setting->phnNo) :[];
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

			$smsSendResult =true;
			if(isset($setting->sms_config) && ($setting->sms_config=="wpsms" || $setting->sms_config=='ws.team') ) $smsSendResult = $this->sms_ready_for_send_efb($form_id, $phone_numbers,$link_w,'respp' ,'wpsms' ,$trackingCode);
		}

		return 0;
	}

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

						if (!is_array($v)) {
							$v = [];
						}
						$valp[$key][$k] = [
							'icon' => sanitize_text_field($v['icon'] ?? 'bi-hand-thumbs-up'),
							'thankYou' => sanitize_text_field($v['thankYou'] ?? esc_html__('Thank you message','easy-form-builder')),
							'done' => sanitize_text_field($v['done'] ?? esc_html__('Done','easy-form-builder')),
							'trackingCode' => sanitize_text_field($v['trackingCode'] ??  esc_html__('Confirmation Code', 'easy-form-builder')),
							'pleaseFillInRequiredFields' => sanitize_text_field($v['pleaseFillInRequiredFields'] ?? esc_html__('Please fill in all required fields.','easy-form-builder')),
						];
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

	public function get_geolocation() {
		  $ip = $this->get_ip_address();
	  }

	  public function get_ip_address() {
        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip =
			sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } else {$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }

	public function addon_adds_cron_efb(){

	if ( ! wp_next_scheduled( 'emsfb_download_addons_cron' ) ) {
		wp_schedule_single_event( time() + 5, 'emsfb_download_addons_cron' );
		}

	}

public function addon_add_efb($value) {

        if (!emsfb_is_addon_install_ready_efb()) {
            $status = emsfb_get_file_access_status_efb();
            if ($status) {
                $message = $status['error_message'] ?? $status['current_message'];
                return array('status' => false, 'message' => $message);
            } else {
                $message = esc_html__('File access status not checked yet. Please wait.', 'easy-form-builder');
                return array('status' => false, 'message' => $message);
            }
        }

        $_server_name = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost';
        $server_name = str_replace("www.", "", $_server_name);
        $vwp = get_bloginfo('version');
		$vwp = substr($vwp,0,3);
		$vefb = EMSFB_PLUGIN_VERSION;
		$domain =  get_option('emsfb_dev_mode', '0') === '1' ? 'demo.whitestudio.team' : 'whitestudio.team';
        $u = 'https://' . $domain . '/wp-json/wl/v1/addons-link/' . $server_name . '/' . $value . '/' . $vwp . '/' . $vefb . '/';
        $name_space = 'emsfb_addon_' . $value;
        if (get_locale() == 'fa_IR' && false) {
            $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/' . $server_name . '/' . $value . '/' . $vwp . '/' . $vefb . '/';
        }
		delete_option($name_space);

        $max_attempts = 2;
        $attempt = 0;
        $success = false;
        $error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ','easy-form-builder');
		$error_messag = sprintf($error_message, $domain, 'not_success');

        while ($attempt < $max_attempts && !$success) {
            $request = wp_remote_get($u);

            if (is_wp_error($request)) {
                $attempt++;
                $error_message = esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server','easy-form-builder');

                if ($attempt >= $max_attempts) {
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($request);

            if ($response_code != 200) {
                $attempt++;
                $error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ','easy-form-builder');
				$error_message = sprintf($error_message, 'whitestudio.team', $response_code);

                if ($attempt >= $max_attempts) {
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }

            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $attempt++;
				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ','easy-form-builder');
				$error_message = sprintf($error_message, 'whitestudio.team', 'invalid_json');

                if ($attempt >= $max_attempts) {
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }
			if($data==null){

				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ','easy-form-builder');
				$error_message = sprintf($error_message, 'whitestudio.team', 'invalid_data');

				if ($attempt >= $max_attempts) {
					return array('status' => false, 'message' => $error_message);
				}
			}

            if ($data->status == false) {
				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ','easy-form-builder');
				$error_message = sprintf($error_message, 'whitestudio.team', 'invalid_status');
                return array('status' => false, 'message' => $error_message);
            }

            if (version_compare(EMSFB_PLUGIN_VERSION, $data->v) == -1) {
                return array('status' => false, 'message' =>  esc_html__('The version of the add-on is not compatible with the version of the Easy Form Builder plugin.','easy-form-builder'));
            }

            if ($data->download == true) {
                $url = $data->link;

                $directory_name = substr($url, strrpos($url, "/") + 1, -4);
                $directory = EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $directory_name;

                if (!file_exists($directory)) {
                    $result = $this->fun_addon_new($url);
                    if (is_wp_error($result)) {
                        return array('status' => false, 'message' => $result->get_error_message());
                    }
                }
				update_option($name_space, 1);
                $success = true;
            }
        }

        if ($success) {
			update_option($name_space, 1);
			$message = esc_html__('The %s has been successfully completed','easy-form-builder');
			$message = sprintf($message,  esc_html__('installation','easy-form-builder'));
            return array('status' => true, 'message' => $message );
        } else {
            return array('status' => false, 'message' => $error_message);
        }

}

	   public function fun_addon_new($url){

		$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
		require_once( $path . 'wp-load.php' );
		require_once (ABSPATH .'wp-admin/includes/admin.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');

		$name =substr($url,strrpos($url ,"/")+1,-4);

		$r =download_url($url);
		if(is_wp_error($r)){
			return new WP_Error('download_failed',
				esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to download files', 'easy-form-builder')
				. ' (' . $r->get_error_message() . ')'
			);
		}
		$filesystem_ready = WP_Filesystem();
		if ($filesystem_ready) {
			global $wp_filesystem;

			$directory = EMSFB_PLUGIN_DIRECTORY . 'temp';
			if (!$wp_filesystem->exists($directory)) {
				$wp_filesystem->mkdir($directory, 0755);
			}
			$moved = $wp_filesystem->move($r, EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip', true);
		} else {
			$directory = EMSFB_PLUGIN_DIRECTORY . 'temp';
			if (!file_exists($directory)) {
				mkdir($directory, 0755, true);
			}
			$moved = rename($r, EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip');
		}
		if(!$moved){
			if (file_exists($r) && !@unlink($r)) {
				error_log('[EFB-ADDON] cleanup temp failed after move failure | file=' . $r);
			}
			error_log('[EFB-ADDON] move failed | url=' . $url);
			return new WP_Error('move_failed',
				esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to move the downloaded file', 'easy-form-builder')
			);
		}
		if (!$filesystem_ready) {
			WP_Filesystem();
		}
		$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . 'vendor/');
		if (file_exists(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip') && !@unlink(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip')) {
			error_log('[EFB-ADDON] cleanup temp.zip failed after unzip');
		}
		if(is_wp_error($r)){
			error_log('[EFB-ADDON] unzip failed | error=' . $r->get_error_message());
			return new WP_Error('unzip_failed',
				esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files', 'easy-form-builder')
				. ' (' . $r->get_error_message() . ')'
			);
		}
		error_log('[EFB-ADDON] fun_addon_new success | url=' . $url);
		return true;
	}

	public function download_all_addons_efb(){
		$state=true;
		$settings=get_setting_Emsfb();
		$addons['AdnSPF']	=	isset($settings->AdnSPF)	? $settings->AdnSPF	:0;
		$addons['AdnATC']	=	isset($settings->AdnATC)	? $settings->AdnATC	:0;
		$addons['AdnTLG']	=	isset($settings->AdnTLG)	? $settings->AdnTLG	:0;
		$addons['AdnPPF']	=	isset($settings->AdnPPF)	? $settings->AdnPPF	:0;
		$addons['AdnSS']	=	isset($settings->AdnSS)		? $settings->AdnSS	:0;
		$addons['AdnESZ']	=	isset($settings->AdnESZ)	? $settings->AdnESZ	:0;
		$addons['AdnSE']	=	isset($settings->AdnSE)		? $settings->AdnSE	:0;
		$addons['AdnPDP']	=	isset($settings->AdnPDP)	? $settings->AdnPDP	:0;
		$addons['AdnADP']	=	isset($settings->AdnADP)	? $settings->AdnADP	:0;
		$addons['AdnATF']	=	isset($settings->AdnATF)	? $settings->AdnATF	:0;
		$addons['AdnPAP']	=	isset($settings->AdnPAP)	? $settings->AdnPAP	:0;
		$addons['AdnOF']	=	isset($settings->AdnOF)		? $settings->AdnOF	:0;

		$error_messag ='';
		foreach ($addons as $key => $value) {

			if($value ==1){
				$r =$this->addon_add_efb($key);
				if(!is_array($r) || !isset($r['status'])){
					$state=false;
					error_log("Unexpected response format when downloading add-on $key: " . print_r($r, true));
					continue;
				}
				if($r['status']==false){
					$state=false;
					$error_messag .= $r['message']."<br>";
				}
			}
		}

		if($state==false){
			$to = isset($settings->emailSupporter) ? $settings->emailSupporter : null;
			if($to==null){$to = get_option('admin_email');}

			if($to==null || $to=="null" || $to=="") return false;
			$sub = esc_html__('Report problem','easy-form-builder') .' ['. esc_html__('Easy Form Builder','easy-form-builder').']';
			$m =  '<div><p>'. $error_messag.
				'</p><p><a href="https://whitestudio.team/support/" target="_blank">'.esc_html__('Please kindly report the following issue to the Easy Form Builder team.','easy-form-builder').
				'</a></p><p>'. esc_html__('Easy Form Builder','easy-form-builder') . '</p>
					<p><a href="'.home_url().'" target="_blank">'.esc_html__("Sent by:",'easy-form-builder'). ' '.get_bloginfo('name').'</a></p></div>';

			if(isset($settings->smtp) && (bool)$settings->smtp ) $this->send_email_state_new($to ,$sub ,$m,0,"addonsDlProblem",'null','null');
			return false;
		}

            return true;

	}

	public function update_message_admin_side_efb(){
		$text = ['wmaddon','contactUs' ,'addons' ,'easyFormBuilder'];
        $lang= $this->text_efb($text);
		return '<div id="body_efb" class="efb card-public efb" style="max-width:680px; margin:60px auto; text-align:center;">
			<div class="efb alert alert-light bg-dark" style="border-radius:16px; padding:40px 32px; box-shadow:0 4px 24px rgba(54,68,210,0.13);">
				<div style="margin-bottom:18px;">
					<span style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:rgba(233,195,26,0.15);">
						<i class="efb bi-arrow-repeat text-warning" style="font-size:32px;"></i>
					</span>
				</div>
				<h3 class="efb text-warning" style="font-size:2em; margin:0 0 14px; font-weight:600;">'.$lang['addons'].'</h3>
				<p class="efb text-info" style="font-size:1.5em; line-height:1.8; margin:0 0 18px; opacity:0.9;">'.$lang['wmaddon'].'</p>
				<p class="efb text-warning" style="margin:0 0 12px; font-size:1.5em; font-weight:600;">
				 <span id="efb-addon-countdown">03:00</span>
				</p>
				<hr style="border-color:rgba(255,255,255,0.1); margin:18px 0;">
				<p class="efb" style="margin:0; font-size:0.95em;">
					<a href="https://whitestudio.team/support/" target="_blank" class="efb text-info" style="text-decoration:none; font-weight:500;">
						<i class="efb bi-headset mx-1"></i>'.$lang['contactUs'].'
					</a>
				</p>
				<p class="efb text-pinkEfb" style="margin:12px 0 0; font-size:0.9em; font-weight:600;">
					<i class="efb bi-plugin mx-1"></i>'.$lang['easyFormBuilder'].'
				</p>
			</div>
		</div>
		<script>
		(function(){
			if (window.efbAddonCountdownStarted) {
				return;
			}
			window.efbAddonCountdownStarted = true;

			var remaining = 180;
			var node = document.getElementById("efb-addon-countdown");

			function renderTimer() {
				if (!node) {
					node = document.getElementById("efb-addon-countdown");
					if (!node) {
						return;
					}
				}

				var min = Math.floor(remaining / 60);
				var sec = remaining % 60;
				node.textContent = String(min).padStart(2, "0") + ":" + String(sec).padStart(2, "0");
			}

			renderTimer();

			var intervalId = setInterval(function(){
				remaining -= 1;
				renderTimer();

				if (remaining <= 0) {
					clearInterval(intervalId);
					window.location.reload();
				}
			}, 1000);
		})();
		</script>';
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
		global $wpdb;

		$table_name = $wpdb->prefix . "emsfb_form";
		$value = $wpdb->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
		$date_format = get_option( 'date_format' );
		foreach ( $value as $row ) {
			if ( ! empty( $row->form_create_date ) ) {
				$timestamp = strtotime( $row->form_create_date );
				if ( $timestamp !== false ) {
					$row->form_create_date = wp_date( $date_format, $timestamp );
				}
			}
		}
		return $value;
	}

	public function efb_code_validate_create($fid, $type, $status, $tc) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emsfb_stts_';
		$ip = $this->get_ip_address();
		$date_now = wp_date('Y-m-d H:i:s');

		$settings = get_setting_Emsfb();
		$sessionDuration = isset($settings->sessionDuration) && is_numeric($settings->sessionDuration) ? intval($settings->sessionDuration) : 1;
		$date_limit = wp_date('Y-m-d H:i:s', strtotime("+{$sessionDuration} days"));

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

		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT sid FROM {$table_name} WHERE fid = %d AND uid = %d AND ip = %s AND active = 1",
			$fid, $uid, $ip
		));

		if ($existing) {

			$wpdb->query($wpdb->prepare(
				"UPDATE {$table_name} SET `type_` = %d, `status` = %s, `date` = %s, `read_date` = %s WHERE fid = %d AND uid = %d AND ip = %s AND active = 1",
				$type, $status, $date_now, $date_limit, $fid, $uid, $ip
			));
			return $existing;
		} else {
			$sql = $wpdb->prepare(
				"INSERT INTO {$table_name} (`sid`, `fid`, `type_`, `status`, `ip`, `os`, `browser`, `uid`, `tc`, `active`, `date`, `read_date`)
				VALUES (%s, %d, %d, %s, %s, %s, %s, %d, %s, %d, %s, %s)",
				$sid, $fid, $type, $status, $ip, $os, $browser, $uid, $tc, 1, $date_now, $date_limit
			);
		}

		$state = $wpdb->query($sql);
		return $sid;
	}

    public function efb_code_validate_update($sid ,$status ,$tc ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emsfb_stts_';
        $date_limit = wp_date('Y-m-d H:i:s', strtotime('-24 hours'));
		$active =0;
		$read_date = wp_date('Y-m-d H:i:s');
		if($status=="rsp" || $status=="ppay")  $active =1;

	   $sql = "UPDATE $table_name SET status='{$status}', active={$active}, read_date='{$read_date}', tc='{$tc}' WHERE sid='{$sid}' AND active=1";
		$stmt = $wpdb->query($sql);
	   return $stmt > 0;
    }

    public function efb_code_validate_select($sid ,$fid) {
		global $wpdb;

		$fid = intval($fid);
		$table_name = $wpdb->prefix . 'emsfb_stts_';
        $date_now = wp_date('Y-m-d H:i:s');

        if(empty($fid) || $fid == 0) {
            $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 ORDER BY date DESC LIMIT 1", $sid, $date_now);
        } else {
            $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 AND fid = %s ORDER BY date DESC LIMIT 1", $sid, $date_now, $fid);
        }

        $result = $wpdb->get_row($query, ARRAY_A);

		if(empty($result)){
			$query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE sid = %s  AND fid = %s ORDER BY date DESC LIMIT 1", $sid, $fid);
			$result = $wpdb->get_row($query, ARRAY_A);
			$valid = ['regis','login','reset','recov','logou'];
			if(empty($result) || !in_array($result['status'], $valid)){
				return false;
			}
			$wpdb->query($wpdb->prepare("UPDATE {$table_name} SET status = %s WHERE sid = %s", 'inact', $sid));
		}

        return !empty($result);
    }

	public function getVisitorOS() {

		$_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;
		$ua = strtolower($_HTTP_USER_AGENT);
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

	    $_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;
	    $ua = strtolower($_HTTP_USER_AGENT);
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
		$sms_exists =get_option('emsfb_addon_AdnSS',false);
		if(!$sms_exists){
			return false;
		}
		$path = EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php";
		if(!file_exists($path)){
			return false;
		}
		require_once($path);
		$smssendefb = new \Emsfb\smssendefb();
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
		$resukt_send_message = false;
		if($state=="fform"){
			if(!empty($numbers[1]) && $new_message){
				$smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
			}
			if(!empty($numbers[0]) && $new_message){
				$new_message = str_replace($page_url."?track=".$tracking_code,$page_url."?track=".$tracking_code.'&user=admin',$new_message);
				$resukt_send_message = $smssendefb->send_sms_efb($numbers[0],$new_message,$form_id,$severType);
			}
			return $resukt_send_message==false ? false : true;
		}else if($state=="resppa"){
			if(!empty($numbers[1]) && $recived_your_message){
				$resukt_send_message =  $smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
			}
			if(!empty($numbers[0]) && $news_response){
				$news_response = str_replace($page_url, $page_url."?track=".$tracking_code.'&user=admin',$news_response);
				$resukt_send_message =  $smssendefb->send_sms_efb($numbers[0],$news_response,$form_id,$severType);
			}
			return $resukt_send_message==false ? false : true;
		}else if ($state=="respp" || $state=="respadmin"){
			if(!empty($numbers[1]) && $news_response){
				$resukt_send_message = $smssendefb->send_sms_efb($numbers[1],$news_response,$form_id,$severType);
			}
			return $resukt_send_message==false ? false : true;
		}
	}

	public function check_for_active_plugins_cache() {

		$cache_plugins = get_option('emsfb_cache_plugins' ,0);
		if(!is_bool($cache_plugins)){
			$cache_plugins_list = json_decode($cache_plugins, true);
			$name = '';
			if (empty($cache_plugins_list)) return 0;
			foreach ($cache_plugins_list as $plugin) {
				$name .= $plugin['name'] . ', ';
			}

			$name = rtrim($name, ', ');
			return $name;
		}

		return 0;
	}

	public function setting_version_efb_update($st ,$pro, $skip_redirect = false){
		global $wpdb;

		if($st=='null' || !is_object($st)){
			$st=get_setting_Emsfb();
		}
		if(!is_object($st)){
			$st = new \stdClass();
		}
		$st->efb_version=EMSFB_PLUGIN_VERSION;

		$st_ = json_encode($st,JSON_UNESCAPED_UNICODE);

        $setting = str_replace('"', '\"', $st_);
		$this->set_setting_Emsfb($setting,$st->emailSupporter);

		if($pro == true || $pro ==1){

			$is_pro = (int) get_option('emsfb_pro' ,2);
			if($is_pro==3){ return true; }

			$this->download_all_addons_efb();

			if($skip_redirect === true) {
				return true;
			}

			$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : null;
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

		wp_register_style('leaflet_css_efb', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1');
		wp_enqueue_style('leaflet_css_efb');
		wp_register_script('leaflet_js_efb', $url, array(), '1.7.1', true);
		wp_enqueue_script('leaflet_js_efb');
		wp_register_style('leaflet_fullscreen_css_efb', 'https://unpkg.com/leaflet.fullscreen/Control.FullScreen.css');
		wp_enqueue_style('leaflet_fullscreen_css_efb');
		wp_register_script('leaflet_fullscreen_js_efb', 'https://unpkg.com/leaflet.fullscreen/Control.FullScreen.js');
		wp_enqueue_script('leaflet_fullscreen_js_efb');

		return true;

	}

	public function check_and_enqueue_google_captcha_efb($lang) {
        $url = 'https://www.google.com/recaptcha/api.js?hl='.$lang.'&render=explicit#asyncload';
        $response = wp_remote_head($url);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            wp_register_script('recaptcha', $url, array() , '2.0', true);
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
		$settings = get_setting_Emsfb('decoded');
		if(is_object($settings) && isset($settings->smtp) && (bool)$settings->smtp ) $this->send_email_state_new('reportProblem' ,'reportProblem' ,$str,0,"reportProblem",'null','null');
		return true;
	}

	public function parsing_plugins_efb(){
		$plugins = get_plugins();
		$active_plugins = get_option('active_plugins');
		$plugin_list = [];
		$cache_plugins_slug = array(
			'wp-optimize', 'hummingbird-performance', 'big-scoots-cache', 'wp-cloudflare-page-cache',
			'breeze', 'jetpack', 'w3-total-cache', 'wp-fastest-cache',
			'wp-rocket', 'comet-cache', 'hyper-cache', 'cache-enabler',
			'wp-super-cache', 'litespeed-cache', 'nitropack', 'jetpack-boost',
			'autoptimize', 'wp-rest-cache', 'speedycache', 'clear-cache-for-widgets',
			'wp-cache', 'wp-cache-system', 'atec-cache-info', 'atec-cache-apcu',
			'wpspeed', 'wp-speed', 'flying-press',
			'sg-optimizer', 'swift-performance', 'powered-cache'
		);
		foreach ($plugins as $plugin_file => $plugin_data) {
			$slug = explode('/', $plugin_file)[0];
			$exists_cache = in_array($slug, $cache_plugins_slug);
			if($exists_cache){
				$plugin_list[] = [
					'name' => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
					'slug' => $slug
				];
			}
		}

		$val = !empty($plugin_list) ? json_encode($plugin_list) : 0;
		$old_val = get_option('emsfb_cache_plugins' ,0);
		if($val != $old_val){
			update_option('emsfb_cache_plugins', $val );
			$this->send_email_noti_about_cache_plugins($val);
		}else{
			update_option('emsfb_cache_plugins', $val );
		}

	}

	public function send_email_noti_about_cache_plugins($val){
		$to = [];
		$to[] = get_option('admin_email');
		$settings = get_setting_Emsfb('decoded');
		if(is_object($settings) && isset($settings->emailSupporter) && $settings->emailSupporter != null && $settings->emailSupporter != 'null' && $settings->emailSupporter != ''){
			$to[] = $settings->emailSupporter;
		}
		$cache_plugins = json_decode($val ,true);
		$subject = esc_html__('Important: Caching Plugin May Affect Easy Form Builder','easy-form-builder');
		$message = esc_html__('The following caching plugins are active on your site:','easy-form-builder') . '<br>';
		foreach ($cache_plugins as $plugin) {
			$message .= esc_html__('Plugin Name','easy-form-builder') . ': ' . $plugin['name'] . '<br>';
			$message .= esc_html__('Version','easy-form-builder') . ': ' . $plugin['version'] . '<br>';
			$message .= esc_html__('Slug','easy-form-builder') . ': ' . $plugin['slug'] . '<br><br>';
		}
		$message .= esc_html__('Please note that these plugins may affect the functionality of Easy Form Builder.','easy-form-builder') . '<br>';

		$message .= esc_html__('If you experience any issues, please exclude the page where your form is published from caching or disable these plugins. For detailed guidance on how to set up this exclusion, please refer to the documentation of the respective caching plugin.','easy-form-builder') . '<br>';
		$message .= esc_html__('Easy Form Builder','easy-form-builder') . '<br>';
		$message .= esc_html__('Sent by','easy-form-builder') . ': ' . get_bloginfo('name') . '<br>';
		$message .= esc_html__('URL','easy-form-builder') . ': ' . get_site_url() . '<br>';
		$message .= esc_html__('Date','easy-form-builder') . ': ' . date('Y-m-d H:i:s') . '<br>';

		if(is_object($settings) && isset($settings->smtp) && (bool)$settings->smtp ) $this->send_email_state_new($to ,$subject ,$message,0,"cache_plugins_noti",'null','null');

		return true;
	}

	public function make_post_request_efb( $ac) {
		$url = EMSFB_SERVER_URL . '/wp-json/wl/v1/pro/key';

		$_http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
		$connected = wp_remote_post('https://www.whitestudio.team', array('timeout' => 2));
		if (is_wp_error($connected)) {
			$s = explode('@', $ac)[0];
			$server_name = str_replace("www.", "", $_http_host);
			$r= isset($s) && md5($server_name) == $s ? (object)['r' => true , 'state' => 'active','pakcage'=>1]   : (object)['r' => false , 'state' => 'notExists' ];
			return $r;

		}
		$get_list_plugins_active = json_encode(get_option('active_plugins'));
		$info = array(
			'domain' => $_http_host,
			'email' => get_option('admin_email'),
			'version_efb' => EMSFB_PLUGIN_VERSION,
			'php_version' => phpversion(),
			'wp_version' => get_bloginfo('version'),
			'lang' => get_locale(),
			'plugins_active' => $get_list_plugins_active,
			'key' => $ac,
			'template_path' => get_template(),
			'plugins_cache' => get_option('emsfb_cache_plugins'),
		);
		$data = array('key' => $ac ,'info'=>$info);
		 $options = array(
			'method' => 'POST',
			'body' => json_encode($data),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);
		$response = wp_remote_post($url, $options);
		if (is_wp_error($response)) {
			return false;
		}
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		return $data;
	}

	public function update_pro_status_efb($code) {
		update_option('emsfb_pro', 1);
		update_option('emsfb_pro_activeCode', $code);
		$json = $this->make_post_request_efb($code);

		$r = isset($json->r) ? $json->r : false;
		if($r===false) {
			delete_option('emsfb_pro');
			delete_option('emsfb_pro_ac_date');
			delete_option('emsfb_pro_activeCode');
			return false;
		}
		update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s'));
		$state = isset($json->state) ? $json->state : '';
		if($state=="new") {
			$activeCode = $json->key;
			update_option('emsfb_pro_activeCode', $activeCode);
			update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s'));
			update_option('emsfb_pro', 1);
			$st = get_setting_Emsfb();
			if(!is_object($st)){ $st = new \stdClass(); }
			$st->activeCode = $activeCode;
			$this->setting_version_efb_update($st,1);
			return true;
		}elseif($state=="active") {
			update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s'));
			return true;
		}elseif ($state=="deactive") {
			update_option('emsfb_pro' , 0);
			delete_option('emsfb_pro_ac_date');
			update_option('emsfb_pro_activeCode' ,$code);
			return false;
		}elseif ($state=="notExists") {
			delete_option('emsfb_pro');
			delete_option('emsfb_pro_ac_date');
			delete_option('emsfb_pro_activeCode');
			return false;
		}
	}

	public function weekly_check_pro_efb($activeCode) {
		$ac_date = get_option('emsfb_pro_ac_date');
		$ac_date = strtotime($ac_date);
		$now = strtotime(date('Y-m-d H:i:s'));
		$diff = ($now - $ac_date) / (60 * 60 * 24);
		if ($diff > 7) {

			$r = $this->update_pro_status_efb($activeCode);
			if ($r==1) {
				update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s'));
				$this->delete_old_rows_emsfb_stts_();
				return true;
			} else {
				update_option('emsfb_pro' , 0);
				delete_option('emsfb_pro_ac_date');
				update_option('emsfb_pro_activeCode', $activeCode);
				return false;
			}
		}
		return true;
	}
	private function validated_pro_efb($s) {
		$_http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
		$server_name = str_replace("www.", "", $_http_host);
		return isset($s) && md5($server_name) == $s ? true : false;
	}
	public function is_efb_pro($s=1) {

		if ($s == 1) {
			$is_pro = (int) get_option('emsfb_pro' ,2);
			if($is_pro==3){ return true; }
			if($is_pro != 1){ return false; }

			$activeCode = get_option('emsfb_pro_activeCode');
			if (empty($activeCode)) {

				$st = get_option('emsfb_settings' , 'null');
				if($st=='null'){
					$st = get_setting_Emsfb();
					$activeCode = is_object($st) && isset($st->activeCode) ? $st->activeCode : '';

				}else{

					$st = json_decode($st);
					if ($st === null) {
						$st = json_decode(stripslashes($st));
					}
					$activeCode = is_object($st) && isset($st->activeCode) ? $st->activeCode : '';
				}

				if(strlen($activeCode)>5){
					update_option('emsfb_pro_activeCode', $activeCode);
				}else{
					delete_option('emsfb_pro');
					return false;
				}
			}

			$ac = explode('@', $activeCode)[0];
			if($this->validated_pro_efb($ac)){

				return $this->weekly_check_pro_efb($activeCode);
			}
			delete_option('emsfb_pro');
			return false;
		} else {
			$activeCode = explode('@', $s)[0];
			if ($this->validated_pro_efb($activeCode)) {
				return $this->update_pro_status_efb($s);
			}
			delete_option('emsfb_pro');
			delete_option('emsfb_pro_ac_date');
			delete_option('emsfb_pro_activeCode');
		}
		return false;
	}

	public function render_pro_gate_efb( $addon_name = '' ) {
		$pro_status = (int) get_option( 'emsfb_pro', -1 );

		if ( $pro_status === 1 || $pro_status === 3 ) {
			return false;
		}

		$is_expired = ( $pro_status === 0 );

		$buy_url    = EMSFB_SERVER_URL . '/register-costumer';
		$ac         = get_option( 'emsfb_pro_activeCode', '' );
		$renew_url  = $buy_url . '?renew=' . urlencode( $ac );

		if ( $is_expired ) {
			$title   = esc_html__( 'Your activation code has expired!', 'easy-form-builder' );
			$message = sprintf(
				esc_html__( 'Your Easy Form Builder Pro subscription has expired. To continue using the %s addon and all Pro features, please renew your subscription.', 'easy-form-builder' ),
				'<strong>' . esc_html( $addon_name ) . '</strong>'
			);
			$btn_url  = $renew_url;
			$btn_text = esc_html__( 'Renew Subscription', 'easy-form-builder' );
			$icon     = 'bi-exclamation-triangle-fill';
			$bg_class = 'bg-dark text-warning';
		} else {
			$title   = esc_html__( 'Pro Version Required', 'easy-form-builder' );
			$message = sprintf(
				esc_html__( 'The %s addon is a Pro feature. Please upgrade to Easy Form Builder Pro to access this functionality.', 'easy-form-builder' ),
				'<strong>' . esc_html( $addon_name ) . '</strong>'
			);
			$btn_url  = $buy_url;
			$btn_text = esc_html__( 'Upgrade to Pro', 'easy-form-builder' );
			$icon     = 'bi-lock-fill';
			$bg_class = 'bg-dark text-info';
		}

		?>
		<div class="wrap">
			<div class="efb mx-3 mt-5 mb-3 p-4 alert alert-light <?php echo esc_attr( $bg_class ); ?>" style="border-radius:12px; max-width:700px; margin:60px auto; text-align:center;">
				<i class="efb <?php echo esc_attr( $icon ); ?>" style="font-size:48px; display:block; margin-bottom:16px;"></i>
				<h2 style="margin:0 0 12px; font-size:1.4em;"><?php echo $title; ?></h2>
				<p style="font-size:1.05em; line-height:1.7; margin-bottom:20px;"><?php echo $message; ?></p>
				<a href="<?php echo esc_url( $btn_url ); ?>" target="_blank" class="efb btn btn-primary btn-lg" style="padding:10px 32px; font-size:1.1em; border-radius:8px; text-decoration:none;">
					<?php echo $btn_text; ?>
				</a>
			</div>
		</div>
		<?php

		return true;
	}

	public function noti_expire_efb() {
		$url = 'https://demo.whitestudio.team/register-costumer?renew=';
		$url = EMSFB_SERVER_URL . '/register-costumer?renew=';

		$msg = esc_html__('Your Easy Form Builder Pro subscription has expired. To continue enjoying all Pro features and keep your forms running, %1$sRenew your subscription now.%2$s', 'easy-form-builder');
		$ac = get_option('emsfb_pro_activeCode');
		$renew = '<br><a class="efb alert-link fw-bold text-info" href="'.$url.'' . $ac . '" target="_blank">';
		$msg = sprintf($msg, $renew, '</a>');
		$ativ = esc_html__('Your activation code has expired!', 'easy-form-builder');
		$div_noti = '<div class="efb mx-3  mt-4 mb-3 pd-5  alert alert-light pointer-efb buy-noti  alert-dismissible bg-dark text-warning"><i class="efb bi-exclamation-triangle-fill text-warning mx-1"></i><span class="efb text-warning">'.$ativ.'</span><br>' . $msg . '<button type="button" class="efb btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';

		return $div_noti;
	}

	public function delete_old_rows_emsfb_stts_() {
		global $wpdb;

		$date_limit = date('Y-m-d', strtotime('-40 days'));

		$table_name_stts =  $wpdb->prefix . 'emsfb_stts_';
		 $wpdb->query(
			 $wpdb->prepare(
				"DELETE FROM $table_name_stts WHERE date < %s",
				$date_limit
			)
		);

		$table_name_temp_links =  $wpdb->prefix . 'emsfb_temp_links';
		$table_exists = get_option('emsfb_temp_links_table_exists' , false);

		if ($table_exists === false) {

			$table_exists =  $wpdb->get_var("SHOW TABLES LIKE '{$table_name_temp_links}'") == $table_name_temp_links;
			update_option('emsfb_temp_links_table_exists', $table_exists);
		}
		if ($table_exists) {
			 $wpdb->query(
				 $wpdb->prepare(
					"DELETE FROM $table_name_temp_links WHERE created_at < %s",
					$date_limit
				)
			);
			return true;
		}

		return false;
	}

	public function allowed_properties_thml_efb(){
		return array(

			'color', 'background', 'background-color', 'background-image', 'background-position',
			'background-repeat', 'background-size', 'background-attachment', 'background-clip', 'background-origin',
			'border-image', 'border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat',

			'font', 'font-family', 'font-size', 'font-style', 'font-variant', 'font-weight',
			'letter-spacing', 'line-height', 'text-align', 'text-decoration', 'text-indent',
			'text-overflow', 'text-shadow', 'text-transform', 'white-space', 'word-break', 'word-spacing',
			'direction', 'unicode-bidi', 'writing-mode', 'hyphens',

			'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
			'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
			'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
			'box-sizing', 'overflow', 'overflow-x', 'overflow-y', 'aspect-ratio',

			'border', 'border-width', 'border-style', 'border-color', 'border-top', 'border-right', 'border-bottom', 'border-left',
			'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
			'border-radius', 'outline', 'outline-width', 'outline-style', 'outline-color',
			'border-collapse', 'border-spacing', 'border-image', 'border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat',

			'box-shadow', 'box-sizing', 'box-decoration-break',

			'position', 'top', 'right', 'bottom', 'left', 'z-index',
			'float', 'clear', 'vertical-align', 'clip',

			'display', 'flex', 'flex-grow', 'flex-shrink', 'flex-basis',
			'align-items', 'align-content', 'align-self', 'justify-content', 'order',
			'grid', 'grid-template-rows', 'grid-template-columns', 'grid-template-areas',
			'grid-area', 'row-gap', 'column-gap', 'gap', 'place-items', 'place-content', 'place-self',

			'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay',
			'animation-iteration-count', 'animation-direction', 'animation-fill-mode', 'animation-play-state',
			'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',

			'border-collapse', 'border-spacing', 'caption-side', 'empty-cells', 'table-layout','collapse',

			'cursor', 'opacity', 'clip-path', 'filter', 'backface-visibility', 'visibility',
			'transform', 'transform-origin', 'transform-style', 'perspective', 'perspective-origin',
			'pointer-events', 'resize', 'scroll-behavior', 'user-select', 'will-change',
			'isolation', 'contain', 'mix-blend-mode', 'object-fit', 'object-position', 'overflow-wrap',
			'shape-outside', 'shape-margin', 'shape-image-threshold'
		);
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
			'html' => array('xmlns' => true, 'lang' => true, 'dir' => true, 'xmlns:v' => true, 'xmlns:o' => true),
			'head' => array(),
			'body' => array_merge($global_attributes, array('bgcolor' => true)),
			'style' => array('type' => true, 'media' => true),
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
			'article' => $global_attributes,
			'aside' => $global_attributes,
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
			'colgroup' => array_merge($global_attributes, array('span' => true)),
			'data' => array_merge($global_attributes, array('value' => true)),
			'datalist' => $global_attributes,
			'div' => $global_attributes,
			'em' => $global_attributes,
			'figure' => $global_attributes,
			'figcaption' => $global_attributes,
			'footer' => $global_attributes,
			'h1' => $global_attributes,
			'h2' => $global_attributes,
			'h3' => $global_attributes,
			'h4' => $global_attributes,
			'h5' => $global_attributes,
			'h6' => $global_attributes,
			'header' => $global_attributes,
			'hr' => $global_attributes,
			'i' => $global_attributes,
			'iframe' => array_merge($global_attributes, array(
				'src' => true,
				'width' => true,
				'height' => true,
				'frameborder' => true,
				'allowfullscreen' => true,
			)),
			'img' => array_merge($global_attributes, array(
				'src' => true,
				'alt' => true,
				'width' => true,
				'height' => true,
			)),
			'label' => array_merge($global_attributes, array('for' => true)),
			'li' => $global_attributes,
			'meta' => array_merge($global_attributes, array(
				'name' => true,
				'content' => true,
				'charset' => true,
				'http-equiv' => true,
			)),
			'nav' => $global_attributes,
			'ol' => array_merge($global_attributes, array('start' => true, 'type' => true)),
			'p' => $global_attributes,
			'pre' => $global_attributes,
			'section' => $global_attributes,
			'span' => $global_attributes,
			'strong' => $global_attributes,
			'sub' => $global_attributes,
			'sup' => $global_attributes,
			'table' => array_merge($global_attributes, array(
				'border' => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'width' => true,
				'role' => true,
				'align' => true,
			)),
			'tbody' => $global_attributes,
			'td' => array_merge($global_attributes, array(
				'colspan' => true,
				'rowspan' => true,
				'align' => true,
				'valign' => true,
				'width' => true,
			)),
			'textarea' => array_merge($global_attributes, array(
				'name' => true,
				'rows' => true,
				'cols' => true,
				'placeholder' => true,
				'required' => true,
			)),
			'tfoot' => $global_attributes,
			'th' => array_merge($global_attributes, array('colspan' => true, 'rowspan' => true, 'scope' => true)),
			'thead' => $global_attributes,
			'tr' => $global_attributes,
			'ul' => $global_attributes,
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

		$sanitized_html = wp_kses($html, $allowed_tags, array_merge(wp_allowed_protocols(), array('data')));

		$sanitized_html = preg_replace_callback(
			'/style=["\']([^"\']+)["\']/i',
			function ($matches) {
				return 'style="' . $this->sanitize_style_attribute_efb($matches[1]) . '"';
			},
			$sanitized_html
		);

		return $sanitized_html;
	}

	public function send_email_noti_sid_plugins_efb($status){

		if (!class_exists('EmsfbEmailHandler')) {
			$email_handler_file = EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
			if (file_exists($email_handler_file)) {
				require_once $email_handler_file;
			} else {
				return false;
			}
		}

		$emailHandler = new EmsfbEmailHandler();
		return $emailHandler->send_email_noti_sid_plugins_efb($status);

	}

	public function validate_url_efb($url) {
			global $allowed_domains;
			$parsed_url = wp_parse_url($url);

			if (isset($parsed_url['host']) && in_array($parsed_url['host'], $allowed_domains)) {
				return esc_url($url);
			}

			$lower = strtolower(preg_replace('/\s+/', '', $url));
			if (strpos($lower, 'javascript:') !== false ||
			    strpos($lower, 'vbscript:') !== false ||
			    strpos($lower, 'data:text/html') !== false ||
			    strpos($lower, 'data:application') !== false) {
				return '';
			}

			return esc_url($url);
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

						$lower_val = strtolower(preg_replace('/\s+/', '', $value));
						if (strpos($lower_val, 'expression(') !== false ||
						    strpos($lower_val, '-moz-binding') !== false ||
						    strpos($lower_val, 'behavior:') !== false ||
						    strpos($lower_val, 'javascript:') !== false ||
						    strpos($lower_val, 'vbscript:') !== false) {
							continue;
						}

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

		$punctClass = '[:：\.\!\?\…‥。！？｡．؟\x{06D4}؛;;‽‼⁇⁈⁉⸮።፧။។៕։\x{0964}\x{0965}\x{0589}\x{1362}\x{104B}\x{17D4}\x{17D5}\x{05C3}]';

		if (preg_match('/' . $punctClass . '/u', $s)) {
			return $s;
		}

		$closersRe = '(?:\p{Pe}|\p{Pf}|["\'»”’）\)\]】］｝〉》」』〕〗])*';
		if (preg_match('/(?P<closers>' . $closersRe . ')(?P<spaces>[\s\x{00A0}\x{202F}]*)$/u', $s, $m)) {
			$endClosers = $m['closers'];
			$endSpaces  = $m['spaces'];

			$s = preg_replace('/' . $closersRe . '[\s\x{00A0}\x{202F}]*$/u', '', $s);
		} else {
			$endClosers = '';
			$endSpaces  = '';
		}

		if (!preg_match('/\s$/u', $s)) {
			$s .= ' ';
		}

		return $s . $colon . $endClosers . $endSpaces;
	}

	public function invalidate_lang_cache_on_settings_update($old_value, $value){
		self::$lang_cache = [];
	}

	function fun_is_plugin_active_by_slug( $slug ) {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		static $all_plugins = null;
		if ( $all_plugins === null ) {

			$all_plugins = get_plugins();
		}

		foreach ( $all_plugins as $plugin_file => $data ) {

			$dir = ( strpos( $plugin_file, '/' ) !== false )
				? substr( $plugin_file, 0, strpos( $plugin_file, '/' ) )
				: basename( $plugin_file, '.php' );

			if ( $dir === $slug || sanitize_title( $data['Name'] ) === $slug ) {

				if ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) {
					return true;
				}

				if ( is_plugin_active( $plugin_file ) ) {
					return true;
				}
			}
		}

		$mu_plugins = function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : [];
		foreach ( $mu_plugins as $mu_file => $data ) {

			$base   = basename( $mu_file, '.php' );
			$folder = basename( dirname( $mu_file ) );

			if ( $folder === $slug || $base === $slug || sanitize_title( $data['Name'] ) === $slug ) {
				return true;
			}
		}

		return false;
	}

	function fun_get_addons_list_efb($ac = null){

		$addons = [
			'AdnSPF' => 0,
			'AdnOF' => 0,
			'AdnPPF' => 0,
			'AdnATC' => 0,
			'AdnSS' => 0,
			'AdnCPF' => 0,
			'AdnESZ' => 0,
			'AdnSE' => 0,
			'AdnPDP' => 0,
			'AdnADP' => 0,
			'AdnPAP' => 0,
			'AdnTLG' => 0,
			'AdnATF' => 0,
		];
		if($ac!=null && isset($ac->AdnSPF)==true){
			$addons['AdnSPF'] = isset($ac->AdnSPF) ? intval($ac->AdnSPF) : 0;
			$addons["AdnOF"] =  isset($ac->AdnOF) ? intval($ac->AdnOF) : 0;
			$addons["AdnPPF"] = isset($ac->AdnPPF) ? intval($ac->AdnPPF) : 0;
			$addons["AdnSS"] =  isset($ac->AdnSS) ? intval($ac->AdnSS) : 0;
			$addons["AdnESZ"] = isset($ac->AdnESZ) ? intval($ac->AdnESZ) : 0;
			$addons["AdnSE"]  = isset($ac->AdnSE) ? intval($ac->AdnSE) : 0;
			$addons["AdnCPF"] = isset($ac->AdnCPF) ? intval($ac->AdnCPF) : 0;
			$addons["AdnATC"] = isset($ac->AdnATC) ? intval($ac->AdnATC) : 0;
			$addons["AdnPDP"] = isset($ac->AdnPDP) ? intval($ac->AdnPDP) : 0;
			$addons["AdnADP"] = isset($ac->AdnADP) ? intval($ac->AdnADP) : 0;
			$addons["AdnPAP"] =  isset($ac->AdnPAP) ? intval($ac->AdnPAP) : 0;
			$addons["AdnTLG"] =  isset($ac->AdnTLG) ? intval($ac->AdnTLG) : 0;
			$addons['AdnATF'] =	isset($ac->AdnATF)	? intval($ac->AdnATF)	:0;
		}

		return $addons;
	}

	function user_permission_efb_admin_dashboard(){

		if ( is_user_logged_in() && (current_user_can('manage_options') || current_user_can('Emsfb')) ) {
			return true;
		}
		return false;
	}

	public static function set_setting_Emsfb ($newSettings, $email = '')
    {
        if (empty($newSettings)) {
            return false;
        }

        $json = '';
        if(is_object($newSettings) || is_array($newSettings)){

            $json = json_encode($newSettings, JSON_UNESCAPED_UNICODE);
        }else{

            $json = $newSettings;

            if (json_decode($json) === null && json_last_error() !== JSON_ERROR_NONE) {
                $unslashed = stripslashes($json);
                if (json_decode($unslashed) !== null) {
                    $json = $unslashed;
                } else {
                    return false;
                }
            }
        }

        if ($json === false) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . "emsfb_setting";

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($count > 2) {

            $last_id = $wpdb->get_var("SELECT MAX(id) FROM {$table_name}");

            $wpdb->update(
                $table_name,
                [
                    'setting' => $json,
                    'edit_by' => get_current_user_id(),
                    'date'    => wp_date('Y-m-d H:i:s'),
                    'email'   => $email
                ],
                ['id' => $last_id],
                ['%s', '%d', '%s', '%s'],
                ['%d']
            );
        } else {

            $wpdb->insert(
                $table_name,
                [
                    'setting' => $json,
                    'edit_by' => get_current_user_id(),
                    'date'    => wp_date('Y-m-d H:i:s'),
                    'email'   => $email
                ],
                ['%s', '%d', '%s', '%s']
            );
        }

        update_option('emsfb_settings', $json);
        set_transient('emsfb_settings_transient', $json, 1800);

        $decoded_for_sync = json_decode($json);
        if ($decoded_for_sync !== null && isset($decoded_for_sync->package_type)) {
            $synced_pt = intval($decoded_for_sync->package_type);
            if (in_array($synced_pt, [0, 1, 2, 3], true)) {
                $current_pro = get_option('emsfb_pro');
                if (intval($current_pro) !== $synced_pt) {
                    update_option('emsfb_pro', $synced_pt);
                }
            }
        }

        wp_cache_delete('settings:decoded', 'emsfb');
        wp_cache_delete('settings:pub', 'emsfb');
        wp_cache_delete('settings:raw', 'emsfb');

        \Emsfb::get_setting_Emsfb('_clear_cache');

        return true;
       }

}
