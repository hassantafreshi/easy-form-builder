<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return whether a genuinely new installation should show first-run setup.
 *
 * The installation marker is intentionally required. An unselected package
 * alone is not enough: existing sites and plugin updates can also use package
 * type 0 and must never be shown the first-run email guide.
 */
if ( ! function_exists( 'emsfb_onboarding_pending_efb' ) ) {
	function emsfb_onboarding_pending_efb() {
		return (bool) get_option( 'emsfb_onboarding_initial_install', false )
			&& (bool) get_option( 'emsfb_onboarding_pending', false );
	}
}

/**
 * Return markup that wpautop() cannot damage.
 *
 * WordPress runs wpautop() on the_content at priority 10 and do_shortcode() at
 * 11, so shortcode output normally escapes it. A fair number of themes and page
 * builders break that order: they re-register wpautop after priority 11, or
 * they call wpautop( do_shortcode( $text ) ) themselves on module content. When
 * that happens every blank line in the form markup turns into a paragraph and
 * every remaining newline into a <br>, which is why a form that looks right in
 * the admin preview - where the_content filters never run - comes apart on the
 * frontend, with stray paragraphs sitting between the fields.
 *
 * Rather than fight each theme for filter position, the markup is made
 * uninteresting to wpautop: with no newline left for it to act on there is
 * nothing to convert. Newlines between tags mean nothing to the browser, so the
 * rendered result is unchanged on sites that were never affected.
 */
if ( ! function_exists( 'emsfb_autop_safe_markup_efb' ) ) {
	function emsfb_autop_safe_markup_efb( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		/* Four elements whose content is not ordinary markup, held aside under a
		 * token so none of the passes below can reach into them, and each given
		 * only the treatment its own content can take. */
		$protected = array();
		$stashed   = preg_replace_callback(
			// The lookbehind makes the wrapping below safe to run twice: a block
			// already carrying its hidden parent is left as it is.
			'#(?<!<div class="efb d-none">)<(script|style|pre|textarea)\b([^>]*)>(.*?)</\1\s*>#is',
			static function ( $match ) use ( &$protected ) {
				$tag  = strtolower( $match[1] );
				$body = $match[3];

				if ( 'textarea' === $tag ) {
					/* Rendered verbatim, so its line breaks have to survive as
					 * line breaks. A character reference reads back as one when
					 * the browser parses the control's value and gives wpautop
					 * nothing to act on. Only the content is rewritten - doing
					 * this to the open tag would glue its attributes together. */
					$body = str_replace( array( "\r\n", "\r", "\n" ), '&#10;', $body );
				} elseif ( 'pre' !== $tag ) {
					/* Blank lines are the only thing wpautop reacts to, and in
					 * CSS or JavaScript they carry no meaning. A single newline
					 * stays: dropping the break after a // comment would comment
					 * out the rest of the block. <pre> is excluded because its
					 * whitespace is content, and wpautop protects it already. */
					$collapsed = preg_replace( '/(?:\r\n|\r|\n)[ \t]*(?:(?:\r\n|\r|\n)[ \t]*)+/', "\n", $body );
					if ( null !== $collapsed ) {
						$body = $collapsed;
					}
				}

				if ( 'script' === $tag ) {
					/* wpautop reads the whole document as text, script bodies
					 * included, so a </div> inside a template literal is a place
					 * it will cut - which is how paragraph tags ended up spliced
					 * into the middle of the panel markup this JS builds. In
					 * JavaScript "<\/div>" and "</div>" are the same string, and
					 * the escaped form is invisible to that search. */
					$body = str_replace( '</', '<\\/', $body );
				}

				$block = '<' . $match[1] . $match[2] . '>' . $body . '</' . $match[1] . '>';

				if ( 'script' === $tag ) {
					/* wpautop does not count script as a block tag, so a piece of
					 * content starting with one keeps the paragraph wrapped
					 * around it - an empty paragraph with a margin, on the page.
					 * A hidden block-level parent gives that piece a block tag to
					 * start with. d-none draws no box at all, so it cannot reach
					 * the layout, and the rule ships in bootstrap.min-efb.css. */
					$block = '<div class="efb d-none">' . $block . '</div>';
				}

				$token = '<!--emsfb-autop-' . count( $protected ) . '-->';

				$protected[ $token ] = $block;

				return $token;
			},
			$html
		);
		if ( null === $stashed ) {
			// PCRE gave up (backtrack limit on a very large form). Untouched
			// markup is still the markup that worked before this guard existed.
			return $html;
		}

		/* A hidden input renders nothing at all, whatever the stylesheet says,
		 * so a block-level parent around one cannot move anything on the page -
		 * and it gives wpautop the block tag it needs to leave the piece of
		 * content alone. Two guards: [^<>] keeps the match from running past a
		 * malformed input into the next element, and the lookbehind leaves an
		 * input that already has its parent alone. */
		$stashed = preg_replace(
			'#(?<!<div class="efb d-none">)(<input\b[^<>]*\btype=(["\'])hidden\2[^<>]*>)#i',
			'<div class="efb d-none">$1</div>',
			$stashed
		);
		if ( null === $stashed ) {
			return $html;
		}

		/* The build markers - <!--startTag file-->, <!-- end body_efb--> and the
		 * rest - are read by nobody, and they are what wpautop trips over worst:
		 * a chunk that opens with a comment instead of a block tag keeps the
		 * paragraph wpautop puts around it, and the browser closing that
		 * paragraph before the field's own <div> is the empty <p></p> that
		 * shows up between the fields. Held-aside script and style blocks are
		 * already tokens by now, so their contents are out of reach. */
		$uncommented = preg_replace( '/<!--(?!emsfb-autop-)(?:(?!-->).)*-->/s', '', $stashed );
		if ( null === $uncommented ) {
			$uncommented = $stashed;
		}

		$flattened = preg_replace( '/[ \t]*(?:\r\n|\r|\n)+[ \t]*/', ' ', $uncommented );
		if ( null === $flattened ) {
			return $html;
		}

		return trim( $protected ? strtr( $flattened, $protected ) : $flattened );
	}
}

class efbFunction {

    protected static $req_cache = [];

    protected static $cached_settings = null;
    protected static $cached_lang = null;

    public function invalidate_settings_cache($old, $new, $option = '') {
        self::$req_cache = [];
        self::$lang_cache = [];
        self::$cached_settings = null;
        self::$cached_lang = null;
        wp_cache_delete('settings:decoded', 'emsfb');
        wp_cache_delete('settings:pub', 'emsfb');
        wp_cache_delete('settings:raw', 'emsfb');
        wp_cache_delete('emsfb_settings', 'emsfb');
        delete_transient('emsfb_settings_transient');
        if (function_exists('get_setting_Emsfb')) {
            get_setting_Emsfb('_clear_cache');
        }
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

		private function normalize_text_settings_compat($settingsObj) {
			if (!is_object($settingsObj) || !isset($settingsObj->text) || !is_object($settingsObj->text)) {
				return $settingsObj;
			}

			$reset_to_defaults = [
				'copyAndPasteBelowShortCodeTrackingCodeFinder' => esc_html__('Copy and paste this shortcode to add the confirmation code finder to any page or post.','easy-form-builder'),
				'checkedBoxIANotRobot' => esc_html__('Please check the box of I am Not robot','easy-form-builder'),
				'proUnlockMsg' => esc_html__('Activate Pro version for more features and unlimited access to all plugin services.','easy-form-builder'),
				'beforeUsingYourEmailServers' => esc_html__('Use this test to check if your server can send emails properly.','easy-form-builder'),
				'pcPreview' => esc_html__('Desktop Preview','easy-form-builder'),
				'activateProVersion' => esc_html__('Upgrade to Pro','easy-form-builder'),
				'fieldAvailableInProversion' => esc_html__('This feature is only available in the Pro version of Easy Form Builder.','easy-form-builder'),
				'enterAdminEmailReceiveNoti' => esc_html__('Enter email address to receive notifications.','easy-form-builder'),
				'howToAddGoogleMap' => esc_html__('How to Add Location Picker(maps) to Easy form Builder WordPress Plugin','easy-form-builder'),
				'browseFile' => esc_html__('Browse the file','easy-form-builder'),
				'freefeatureNotiEmail' => esc_html__('Email notifications are available in all versions, including Free, Free Plus, and Pro.','easy-form-builder'),
			];

			$fill_missing_defaults = [
				'videoOrAudio' => esc_html__('(Video or Audio)','easy-form-builder'),
				'localization' => esc_html__('Localization','easy-form-builder'),
				'translateContrib' => esc_html__('Help us speak your language! Translate Easy Form Builder on the %1$sWordPress.org translation portal%2$s and make it accessible to your community.','easy-form-builder'),
				'translateLocal' => esc_html__('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.','easy-form-builder'),
			];

			foreach ($reset_to_defaults as $key => $default) {
				if (!isset($settingsObj->text->$key) || $settingsObj->text->$key !== $default) {
					$settingsObj->text->$key = $default;
				}
			}

			foreach ($fill_missing_defaults as $key => $default) {
				if (!isset($settingsObj->text->$key) || $settingsObj->text->$key === null || $settingsObj->text->$key === 'null') {
					$settingsObj->text->$key = $default;
				}
			}

			return $settingsObj;
		}

	protected static $lang_cache = [];
	private const EFB_LANG_CACHE_TTL = 21600;

	protected $db;

	/**
	 * Set by the background recovery runner while it still has retries left, so
	 * the site owner receives one precise report after the final attempt rather
	 * than one email per attempt.
	 *
	 * @var bool
	 */
	public $suppress_addon_report_efb = false;

	public function __construct() {

		if (function_exists('add_action')) {
			add_action('update_option_emsfb_settings', [ $this, 'invalidate_settings_cache' ], 10, 3);
		}

		/* The ADDON_RECOVERY_EVENT_EFB listener is deliberately NOT bound here.
		 * This class is instantiated lazily, so on a wp-cron.php request nothing
		 * would have created it and the scheduled event would fire with no
		 * listener at all. Emsfb::__construct() binds it instead, next to the
		 * plugin's other cron handlers, and delegates back to this class. */

		global $wpdb;
		$this->db = $wpdb;

		register_activation_hook( __FILE__, [$this ,'download_all_addons_efb'] );
		$this->clear_legacy_addon_recovery_cron_efb();
    }

	/**
	 * Remove events left behind by the former background recovery flow. This runs
	 * once per plugin version and prevents a stale WP-Cron event from unexpectedly
	 * downloading add-ons after recovery became request-driven.
	 */
	public function clear_legacy_addon_recovery_cron_efb() {
		if ( get_option( 'emsfb_addon_recovery_cron_cleanup', '' ) === EMSFB_PLUGIN_VERSION ) {
			return;
		}

		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( 'emsfb_download_addons_cron' );
		}
		update_option( 'emsfb_addon_recovery_cron_cleanup', EMSFB_PLUGIN_VERSION, false );
	}

	public function text_efb($inp,$page_request = 'default') {

         if (static::$cached_settings === null) {
            static::$cached_settings = get_setting_Emsfb();
            static::$cached_lang = $this->detect_current_lang_slug();
        }
	$ac = $this->normalize_text_settings_compat(static::$cached_settings);
	static::$cached_settings = $ac;
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

        // NOTE: the cache group here is 'efb', not the plugin's usual 'emsfb'.
        // It is only paired with the matching wp_cache_set() at the end of this
        // method; both must be changed together or the cache silently misses on
        // every request and the whole array is rebuilt each time.
        // Cache key for the fully-built language array. Invalidation is by
        // versioning the key ($efb_ver changes when the text settings change),
        // which is why no wp_cache_delete() exists for this entry. The plugin
        // version is part of the key as well: the defaults below ship with the
        // code, so without it an update would keep serving the previous
        // release's phrases from a persistent object cache until the entry
        // expired on its own.
        $efb_ck_final = "langfinal:" . EMSFB_PLUGIN_VERSION . ":$efb_lang:$efb_ver:$efb_subset:$page_request";

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
			/* translators: Placeholder shown inside the empty reply editor in the responses panel */
			"replyMsg" => $state && isset($ac->text->replyMsg) ? $ac->text->replyMsg : esc_html__('Type your reply&hellip;','easy-form-builder'),
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
			/* translators: Confirmation Code Finder = tool to locate confirmation codes [shortcode] */
			"trackingCodeFinder" => $state ? $ac->text->trackingCodeFinder : esc_html__('Confirmation Code Finder','easy-form-builder'),
			"copyAndPasteBelowShortCodeTrackingCodeFinder" => $state ? $ac->text->copyAndPasteBelowShortCodeTrackingCodeFinder : esc_html__('Copy and paste this shortcode to add the confirmation code finder to any page or post.','easy-form-builder'),
			"save" => $state ? $ac->text->save : esc_html__('Save','easy-form-builder'),
			"waiting" => $state ? $ac->text->waiting : esc_html__('Waiting','easy-form-builder'),
			"saved" => $state ? $ac->text->saved : esc_html__('Saved','easy-form-builder'),
			/* translators: Step Name = name of a step in a multi-step form */
			"stepName" => $state ? $ac->text->stepName : esc_html__('Step Name','easy-form-builder'),
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
			/* translators: Shown under a Jalali/Hijri date field when the typed value is not a valid date */
			"enterValidDate" => $state && isset($ac->text->enterValidDate) ? $ac->text->enterValidDate : esc_html__('Please enter a valid date','easy-form-builder'),
			"step" => $state ? $ac->text->step : esc_html__('Step','easy-form-builder'),
			"orClickHere" => $state ? $ac->text->orClickHere : esc_html__('or click here','easy-form-builder'),
			/* translators: CSV = Comma-Separated Values - a spreadsheet file format */
			"downloadCSVFile" => $state ? $ac->text->downloadCSVFile : esc_html__('Download CSV file','easy-form-builder'),
			"downloadCSVFileSub" => $state ? $ac->text->downloadCSVFileSub : esc_html__('Download subscriptions CSV.','easy-form-builder'),
			"login" => $state ? $ac->text->login : esc_html__('Login','easy-form-builder'),
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
			/* translators: Description shown under the "No Response" empty state in the responses panel */
			"noResponseDesc" => $state && isset($ac->text->noResponseDesc) ? $ac->text->noResponseDesc : esc_html__('Submitted responses will appear here.','easy-form-builder'),
			"clickHere" => $state ? $ac->text->clickHere : esc_html__('Click here','easy-form-builder'),
			/* translators: Captchas = tab name in the settings panel for configuring CAPTCHA services */
			"captchas" => $state && isset($ac->text->captchas) ? $ac->text->captchas  : esc_html__('Captchas','easy-form-builder'),
			"emailServer" => $state ? $ac->text->emailServer : esc_html__('Email server','easy-form-builder'),
			"beforeUsingYourEmailServers" => $state ? $ac->text->beforeUsingYourEmailServers : esc_html__('Use this test to check if your server can send emails properly.','easy-form-builder'),
			"emailSetting" => $state ? $ac->text->emailSetting : esc_html__('Email Settings','easy-form-builder'),
			"clickToCheckEmailServer" => $state ? $ac->text->clickToCheckEmailServer : esc_html__('Check Email Server','easy-form-builder'),
			/* translators: D&D means Drag and Drop */
			"dadfile" => $state ? $ac->text->dadfile : esc_html__('D&D File Upload','easy-form-builder'),
			"audio_recorder" => $state && isset($ac->text->audio_recorder) ? $ac->text->audio_recorder : esc_html__('Audio Recorder','easy-form-builder'),
			"video_recorder" => $state && isset($ac->text->video_recorder) ? $ac->text->video_recorder : esc_html__('Video Recorder','easy-form-builder'),
			"screen_recorder" => $state && isset($ac->text->screen_recorder) ? $ac->text->screen_recorder : esc_html__('Screen Recorder','easy-form-builder'),
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
			"copyShortcode" => $state ? $ac->text->copyShortcode : esc_html__('Copy Shortcode','easy-form-builder'),
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
			"addGooglereCAPTCHAtoForm" => $state ? $ac->text->addGooglereCAPTCHAtoForm : esc_html__('Add Google reCAPTCHA to the form','easy-form-builder'),
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
			/* translators: %s = context prefix (e.g. Mobile/Desktop). Buttons Align = alignment of the form navigation/submit buttons */
			"sbtnsAlign" => $state && isset($ac->text->sbtnsAlign) ? $ac->text->sbtnsAlign : esc_html__('%s Buttons | Align','easy-form-builder'),
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
			"blue" => $state ? $ac->text->blue : esc_html__('Blue','easy-form-builder'),
			"green" => $state ? $ac->text->green : esc_html__('Green','easy-form-builder'),
			"pink" => $state ? $ac->text->pink : esc_html__('Pink','easy-form-builder'),
			"yellow" => $state ? $ac->text->yellow : esc_html__('Yellow','easy-form-builder'),
			"light" => $state ? $ac->text->light : esc_html__('Light','easy-form-builder'),
			"Red" => $state ? $ac->text->Red : esc_html__('red','easy-form-builder'),
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

			/* translators: Confirmation shown before replacing a Pro licence with Free Plus. */
			"downgradeProToFreePlusTitle" => $state && isset($ac->text->downgradeProToFreePlusTitle) ? $ac->text->downgradeProToFreePlusTitle : esc_html__('Switch to Free Plus?','easy-form-builder'),
			"downgradeProToFreePlusBody" => $state && isset($ac->text->downgradeProToFreePlusBody) ? $ac->text->downgradeProToFreePlusBody : esc_html__('Your Pro activation code will be removed from this site. Pro add-ons will be unavailable until you upgrade again. Your forms, entries and settings will not be deleted.','easy-form-builder'),
			"downgradeFreePlusToFreeTitle" => $state && isset($ac->text->downgradeFreePlusToFreeTitle) ? $ac->text->downgradeFreePlusToFreeTitle : esc_html__('Switch to Free?','easy-form-builder'),
			/* translators: Confirmation text for changing from Free Plus to Free. */
			"downgradeFreePlusToFreeAdvancedBody" => $state && isset($ac->text->downgradeFreePlusToFreeAdvancedBody) ? $ac->text->downgradeFreePlusToFreeAdvancedBody : esc_html__('Advanced features and advanced fields used in your forms will be disabled. Your forms, entries and settings will not be deleted, and will be available again if you upgrade.','easy-form-builder'),
			"downgradeProToFreeBody" => $state && isset($ac->text->downgradeProToFreeBody) ? $ac->text->downgradeProToFreeBody : esc_html__('Your Pro activation code will be removed from this site. Advanced features, Pro fields and add-ons will be unavailable until you upgrade again. Your forms, entries and settings will not be deleted.','easy-form-builder'),
			"keepPro" => $state && isset($ac->text->keepPro) ? $ac->text->keepPro : esc_html__('Keep Pro','easy-form-builder'),
			"keepFreePlus" => $state && isset($ac->text->keepFreePlus) ? $ac->text->keepFreePlus : esc_html__('Keep Free Plus','easy-form-builder'),
			"switchToFreePlus" => $state && isset($ac->text->switchToFreePlus) ? $ac->text->switchToFreePlus : esc_html__('Switch to Free Plus','easy-form-builder'),
			"switchToFree" => $state && isset($ac->text->switchToFree) ? $ac->text->switchToFree : esc_html__('Switch to Free','easy-form-builder'),
			/* translators: Title bar of the dialog that confirms moving to a lower plan. */
			"planChange" => $state && isset($ac->text->planChange) ? $ac->text->planChange : esc_html__('Plan change','easy-form-builder'),
			/* translators: Reassurance shown in the downgrade dialog, next to a database icon. */
			"downgradeDataKept" => $state && isset($ac->text->downgradeDataKept) ? $ac->text->downgradeDataKept : esc_html__('Your data stays untouched; only access to the features is limited.','easy-form-builder'),
			"planSelectionTryAgain" => $state && isset($ac->text->planSelectionTryAgain) ? $ac->text->planSelectionTryAgain : esc_html__('An error occurred. Please try again.','easy-form-builder'),
			"planSelectionFailed" => $state && isset($ac->text->planSelectionFailed) ? $ac->text->planSelectionFailed : esc_html__('Unable to change the plan. Please try again.','easy-form-builder'),

			/* translators: First-run setup screen. */
			"onboardingPlanSelected" => $state && isset($ac->text->onboardingPlanSelected) ? $ac->text->onboardingPlanSelected : esc_html__('Plan selected','easy-form-builder'),
			"onboardingEmailTitle" => $state && isset($ac->text->onboardingEmailTitle) ? $ac->text->onboardingEmailTitle : esc_html__('Set up form notifications','easy-form-builder'),
			/* translators: Description for the notification setup step. It explains that the email test confirms the selected address can receive form notifications. */
			"onboardingEmailDescription" => $state && isset($ac->text->onboardingEmailDescription) ? $ac->text->onboardingEmailDescription : esc_html__('Check whether your server can send emails, so you know you will receive form notifications.','easy-form-builder'),
			"onboardingAdminEmail" => $state && isset($ac->text->onboardingAdminEmail) ? $ac->text->onboardingAdminEmail : esc_html__('Form notification email','easy-form-builder'),
			"onboardingAdminEmailHint" => $state && isset($ac->text->onboardingAdminEmailHint) ? $ac->text->onboardingAdminEmailHint : esc_html__('This is saved in General Settings and can be changed later.','easy-form-builder'),
			"onboardingDefaults" => $state && isset($ac->text->onboardingDefaults) ? $ac->text->onboardingDefaults : esc_html__('Your default settings','easy-form-builder'),
			"onboardingNotifications" => $state && isset($ac->text->onboardingNotifications) ? $ac->text->onboardingNotifications : esc_html__('Notifications stay off until delivery is verified','easy-form-builder'),
			"onboardingSender" => $state && isset($ac->text->onboardingSender) ? $ac->text->onboardingSender : esc_html__('Sender','easy-form-builder'),
			"onboardingFormsReady" => $state && isset($ac->text->onboardingFormsReady) ? $ac->text->onboardingFormsReady : esc_html__('Your forms and submissions are ready to use','easy-form-builder'),
			"onboardingTestEmail" => $state && isset($ac->text->onboardingTestEmail) ? $ac->text->onboardingTestEmail : esc_html__('Save and test email delivery','easy-form-builder'),
			"onboardingTesting" => $state && isset($ac->text->onboardingTesting) ? $ac->text->onboardingTesting : esc_html__('Checking email delivery…','easy-form-builder'),
			"onboardingTestStarted" => $state && isset($ac->text->onboardingTestStarted) ? $ac->text->onboardingTestStarted : esc_html__('A test email was sent. Waiting for delivery confirmation…','easy-form-builder'),
			"onboardingTestPassed" => $state && isset($ac->text->onboardingTestPassed) ? $ac->text->onboardingTestPassed : esc_html__('Email delivery is ready. Form notifications can be sent.','easy-form-builder'),
			/* translators: Shown when the test message was sent but the delivery service has not confirmed receipt yet. */
			"onboardingTestPendingGuidance" => $state && isset($ac->text->onboardingTestPendingGuidance) ? $ac->text->onboardingTestPendingGuidance : esc_html__('Your test email was sent, but delivery is not confirmed yet. Check the inbox or spam folder for the address below; you can finish setup and try again later from General Settings.','easy-form-builder'),
			"onboardingTestFailed" => $state && isset($ac->text->onboardingTestFailed) ? $ac->text->onboardingTestFailed : esc_html__('We could not verify delivery. Your email address was saved; please check your mail configuration in General Settings.','easy-form-builder'),
			/* translators: Shown when the test message arrived but its deliverability score is too low to rely on. %1$s: measured score, %2$s: minimum acceptable score. */
			"emailDeliveryLowScore" => $state && isset($ac->text->emailDeliveryLowScore) ? $ac->text->emailDeliveryLowScore : esc_html__('Your test email was delivered, but its deliverability score is only %1$s out of 100 (below %2$s). The emails your forms send will most likely be filtered as spam. Set up SMTP and run the check again.','easy-form-builder'),
			"onboardingFinish" => $state && isset($ac->text->onboardingFinish) ? $ac->text->onboardingFinish : esc_html__('Finish setup','easy-form-builder'),

			/* translators: Selected = indicates something has been chosen */
			"selected" => $state && isset($ac->text->selected) ? $ac->text->selected : esc_html__('selected','easy-form-builder'),
			/* translators: Setup reminder message */
			"setupReminder" => $state && isset($ac->text->setupReminder) ? $ac->text->setupReminder : esc_html__('You can access setup from plugin settings anytime.','easy-form-builder'),


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
			"reservation" => $state ? $ac->text->reservation : esc_html__('Reservation','easy-form-builder'),
			"createsurveyForm" => $state ? $ac->text->createsurveyForm : esc_html__('Create survey, poll, or questionnaire forms.','easy-form-builder'),
			"firstName" => $state ? $ac->text->firstName : esc_html__('First name','easy-form-builder'),
			"lastName" => $state ? $ac->text->lastName : esc_html__('Last name','easy-form-builder'),
			"message" => $state ? $ac->text->message : esc_html__('Message','easy-form-builder'),
			"subject" => $state ? $ac->text->subject : esc_html__('Subject','easy-form-builder'),
			"phone" => $state ? $ac->text->phone : esc_html__('Phone','easy-form-builder'),
			"register" => $state ? $ac->text->register : esc_html__('Register','easy-form-builder'),
			"username" => $state ? $ac->text->username : esc_html__('Username','easy-form-builder'),
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
			"clearFiles" => $state ? $ac->text->clearFiles : esc_html__('Clear Files','easy-form-builder'),
			"enterActivateCode" => $state ? $ac->text->enterActivateCode : esc_html__('Enter your activation code','easy-form-builder'),
			"error" => $state ? $ac->text->error : esc_html__('Error','easy-form-builder'),
			/* translators: Generic error message shown when the form preview could not be generated */
			"errorMsg" => $state && isset($ac->text->errorMsg) ? $ac->text->errorMsg : esc_html__('Something went wrong. Please try again.','easy-form-builder'),
			/* translators: Label of the auto-added "Other" choice on a radio field with "Add Other option" enabled */
			"otherTxt" => $state && isset($ac->text->otherTxt) ? $ac->text->otherTxt : esc_html__('Other','easy-form-builder'),
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
			"videoOrAudio" => $state && isset($ac->text->videoOrAudio) ? $ac->text->videoOrAudio : esc_html__('(Video or Audio)','easy-form-builder'),
			"localization" => $state && isset($ac->text->localization) ? $ac->text->localization : esc_html__('Localization','easy-form-builder'),
			/* translators: %1$s and %2$s are opening and closing HTML link tags for the WordPress.org translation portal */
			"translateContrib" => $state && isset($ac->text->translateContrib) ? $ac->text->translateContrib : esc_html__('Help us speak your language! Translate Easy Form Builder on the %1$sWordPress.org translation portal%2$s and make it accessible to your community.','easy-form-builder'),
			/* translators: %1$s and %2$s are opening and closing HTML link tags for the WordPress.org translation portal, %3$s is the discount percentage */
			"translateDiscount" => $state && isset($ac->text->translateDiscount) ? $ac->text->translateDiscount : esc_html__('If your language translation is not available yet, translate it and get a %3$s lifetime discount! Contribute via the %1$sWordPress.org translation portal%2$s.','easy-form-builder'),
			"discountOff" => $state && isset($ac->text->discountOff) ? $ac->text->discountOff : esc_html__('OFF','easy-form-builder'),
			"translateLocal" => $state && isset($ac->text->translateLocal) ? $ac->text->translateLocal : esc_html__('You can translate Easy Form Builder into your preferred language by translating the following sentences. WARNING: If your WordPress site is multilingual, do not change the values below.','easy-form-builder'),
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
			"goToEFBAddEmailM" => $state ? $ac->text->goToEFBAddEmailM : esc_html__('Please go to Panel > Settings > Email Settings, click "Check Email Server", then click "Save".','easy-form-builder'),
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
			"shortcodeTitleInfo" => $state  &&  isset($ac->text->shortcodeTitleInfo) ? $ac->text->shortcodeTitleInfo : esc_html__('Add this shortcode inside a tag to display the title of the email.','easy-form-builder'),
			"shortcodeMessageInfo" => $state  &&  isset($ac->text->shortcodeMessageInfo) ? $ac->text->shortcodeMessageInfo : esc_html__('Add this shortcode inside an HTML tag to display the message content of an email.','easy-form-builder'),
			"shortcodeWebsiteNameInfo" => $state  &&  isset($ac->text->shortcodeWebsiteNameInfo) ? $ac->text->shortcodeWebsiteNameInfo : esc_html__('To display the website name, add this shortcode inside an HTML tag.','easy-form-builder'),
			"shortcodeWebsiteUrlInfo" => $state  &&  isset($ac->text->shortcodeWebsiteUrlInfo) ? $ac->text->shortcodeWebsiteUrlInfo : esc_html__('Add this shortcode within an HTML tag to display the Website URL.','easy-form-builder'),
			"shortcodeAdminEmailInfo" => $state  &&  isset($ac->text->shortcodeAdminEmailInfo) ? $ac->text->shortcodeAdminEmailInfo : esc_html__('You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.','easy-form-builder'),
			"templates" => $state  &&  isset($ac->text->templates) ? $ac->text->templates : esc_html__('Templates','easy-form-builder'),
			"maxSelect" => $state  &&  isset($ac->text->maxSelect) ? $ac->text->maxSelect : esc_html__('Max selection','easy-form-builder'),
			"minSelect" => $state  &&  isset($ac->text->minSelect) ? $ac->text->minSelect : esc_html__('Min selection','easy-form-builder'),
			"dNotShowBg" => $state  &&  isset($ac->text->dNotShowBg) ? $ac->text->dNotShowBg : esc_html__('Do not show the background.','easy-form-builder'),
			"contactusTemplate" => $state  &&  isset($ac->text->contactusTemplate) ? $ac->text->contactusTemplate : esc_html__('Contact us Template','easy-form-builder'),
			"curved" => $state  &&  isset($ac->text->curved) ? $ac->text->curved : esc_html__('Curved','easy-form-builder'),
			"multiStep" => $state  &&  isset($ac->text->multiStep) ? $ac->text->multiStep : esc_html__('Multi-Step','easy-form-builder'),
			"noCoding" => $state  &&  isset($ac->text->noCoding) ? $ac->text->noCoding : esc_html__('No Coding','easy-form-builder'),
			"dragAndDropBadge" => $state  &&  isset($ac->text->dragAndDropBadge) ? $ac->text->dragAndDropBadge : esc_html__('Drag & Drop','easy-form-builder'),
			"customerFeedback" => $state  &&  isset($ac->text->customerFeedback) ? $ac->text->customerFeedback : esc_html__('Customer Feedback','easy-form-builder'),
			"supportTicketF" => $state  &&  isset($ac->text->supportTicketF) ? $ac->text->supportTicketF : esc_html__('Support Ticket Form','easy-form-builder'),
			"paymentform" => $state  &&  isset($ac->text->paymentform) ? $ac->text->paymentform : esc_html__('Payment Form','easy-form-builder'),
			"stripe" => $state  &&  isset($ac->text->stripe) ? $ac->text->stripe : esc_html__('Stripe','easy-form-builder'),
			"payment" => $state  &&  isset($ac->text->payment ) ? $ac->text->payment  : esc_html__('Payment','easy-form-builder'),
			"address" => $state  &&  isset($ac->text->address ) ? $ac->text->address  : esc_html__('Address','easy-form-builder'),
			"paymentGateway" => $state  &&  isset($ac->text->paymentGateway) ? $ac->text->paymentGateway : esc_html__('Payment Gateway','easy-form-builder'),
			"currency" => $state  &&  isset($ac->text->currency) ? $ac->text->currency : esc_html__('Currency','easy-form-builder'),
			"onetime" => $state  &&  isset($ac->text->onetime) ? $ac->text->onetime : esc_html__('one time','easy-form-builder'),
			"methodPayment" => $state  &&  isset($ac->text->methodPayment) ? $ac->text->methodPayment : esc_html__('Method payment','easy-form-builder'),
			"heading" => $state  &&  isset($ac->text->heading) ? $ac->text->heading : esc_html__('Heading','easy-form-builder'),
			"link" => $state  &&  isset($ac->text->link) ? $ac->text->link : esc_html__('Link','easy-form-builder'),
			"mobile" => $state  &&  isset($ac->text->mobile) ? $ac->text->mobile : esc_html__('Mobile','easy-form-builder'),
			"product" => $state  &&  isset($ac->text->product) ? $ac->text->product : esc_html__('product','easy-form-builder'),
			"value" => $state  &&  isset($ac->text->value) ? $ac->text->value : esc_html__('value','easy-form-builder'),
			"terms" => $state  &&  isset($ac->text->terms) ? $ac->text->terms : esc_html__('terms','easy-form-builder'),
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
			"hostSupportSmtp" => $state  &&  isset($ac->text->hostSupportSmtp) ? $ac->text->hostSupportSmtp : esc_html__('This site can send emails','easy-form-builder'),
			"weeklyEmailReport" => $state && isset($ac->text->weeklyEmailReport) ? $ac->text->weeklyEmailReport : esc_html__('Weekly email health and form activity report','easy-form-builder'),
			"weeklyEmailReportDesc" => $state && isset($ac->text->weeklyEmailReportDesc) ? $ac->text->weeklyEmailReportDesc : esc_html__('Adds your form activity totals &ndash; forms, views and submissions &ndash; to the weekly email your site sends the main administrator. These totals are read on your own site and are never sent to WhiteStudio.','easy-form-builder'),
			"weeklyEmailLastCheck" => $state && isset($ac->text->weeklyEmailLastCheck) ? $ac->text->weeklyEmailLastCheck : esc_html__('Last check: %s','easy-form-builder'),
			"weeklyEmailNotRun" => $state && isset($ac->text->weeklyEmailNotRun) ? $ac->text->weeklyEmailNotRun : esc_html__('No automated email check has run yet.','easy-form-builder'),
			"emailStatsReport" => $state && isset($ac->text->emailStatsReport) ? $ac->text->emailStatsReport : esc_html__('Collect email delivery statistics','easy-form-builder'),
			"emailStatsReportDesc" => $state && isset($ac->text->emailStatsReportDesc) ? $ac->text->emailStatsReportDesc : esc_html__('Counts how many emails were sent or failed, shows them in the dashboard widget, and adds the email delivery status section to the weekly email. Once a week your site runs a delivery test and reports the result. Only Pro users can turn this off.','easy-form-builder'),
			"actions" => $state  &&  isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions','easy-form-builder'),

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
			"eJQ500" => $state  &&  isset($ac->text->eJQ500) ? $ac->text->eJQ500 : esc_html__('You are experiencing issues with jQuery. Please contact the administrator for assistance. (Error code: JQ-500)','easy-form-builder'),
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
			"yFreeVEnPro" => $state  &&  isset($ac->text->yFreeVEnPro) ? $ac->text->yFreeVEnPro : esc_html__('Upgrade to Pro for just %1$s%2$s%3$s/year and get access to powerful features, including advanced form fields, payment integrations, conditional logic, multi-step forms, file uploads, Security & Spam Protection, and priority support.%4$sView Pro Features%5$s','easy-form-builder'),
			/* translators: %1$s is the name of the addon */
			"addon" => $state  &&  isset($ac->text->addon) ? $ac->text->addon : esc_html__('Add-on','easy-form-builder'),
			"addons" => $state  &&  isset($ac->text->addons) ? $ac->text->addons : esc_html__('Add-ons','easy-form-builder'),
			"stripeTAddon" => $state  &&  isset($ac->text->stripeTAddon) ? $ac->text->stripeTAddon : esc_html__('Stripe Payment Add-on','easy-form-builder'),
			"stripeDAddon" => $state  &&  isset($ac->text->stripeDAddon) ? $ac->text->stripeDAddon : esc_html__('The Stripe add-on for Easy Form Builder enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.','easy-form-builder'),
			"offlineTAddon" => $state  &&  isset($ac->text->offlineTAddon) ? $ac->text->offlineTAddon : esc_html__('Offline Forms Add-on','easy-form-builder'),
			"offlineDAddon" => $state  &&  isset($ac->text->offlineDAddon) ? $ac->text->offlineDAddon : esc_html__('The Offline Forms add-on for Easy Form Builder allows users to save their progress when filling out forms in offline situations.','easy-form-builder'),

			"install" => $state  &&  isset($ac->text->install) ? $ac->text->install : esc_html__('Install','easy-form-builder'),
			"upDMsg" => $state  &&  isset($ac->text->upDMsg) ? $ac->text->upDMsg : esc_html__('Please update Easy Form Builder before trying again.','easy-form-builder'),
			"AfLnFrm" => $state  &&  isset($ac->text->AfLnFrm) ? $ac->text->AfLnFrm : esc_html__('Activation of offline form mode.','easy-form-builder'),
			"IMAddons" => $state  &&  isset($ac->text->IMAddons) ? $ac->text->IMAddons : esc_html__('Before activating this option, install','easy-form-builder'),
			"IMAddonP" => $state  &&  isset($ac->text->IMAddonP) ? $ac->text->IMAddonP : esc_html__('To create a payment form to collect online payments, you must first install a payment add-on such as the Stripe Add-on.','easy-form-builder'),
			"allformat" => $state  &&  isset($ac->text->allformat) ? $ac->text->allformat : esc_html__('All formats','easy-form-builder'),
			"AdnSST" => $state  &&  isset($ac->text->AdnSST) ? $ac->text->AdnSST : esc_html__('EFB SMS Add-on','easy-form-builder'),
			"AdnSSD" => $state  &&  isset($ac->text->AdnSSD) ? $ac->text->AdnSSD : esc_html__('Enable SMS functionality in your forms with the EFB SMS add-on, allowing you to validate mobile numbers and send confirmation codes via SMS, as well as receive notifications through SMS service.','easy-form-builder'),
			"AdnATCT" => $state  &&  isset($ac->text->AdnATCT) ? $ac->text->AdnATCT : esc_html__('Advanced confirmation code Add-on','easy-form-builder'),
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
			"mobileHideLabel" => $state && isset($ac->text->mobileHideLabel) ? $ac->text->mobileHideLabel : esc_html__('Hide label on mobile','easy-form-builder'),
			"mobileHideDescription" => $state && isset($ac->text->mobileHideDescription) ? $ac->text->mobileHideDescription : esc_html__('Hide description on mobile','easy-form-builder'),
			"globalMobileHideLabel" => $state && isset($ac->text->globalMobileHideLabel) ? $ac->text->globalMobileHideLabel : esc_html__('Hide all labels on mobile','easy-form-builder'),
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
			"iaddon" => $state  &&  isset($ac->text->iaddon) ? $ac->text->iaddon : esc_html__('Install the add-on','easy-form-builder'),
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
			"persiaPayTAddon" => $state  &&  isset($ac->text->persiaPayTAddon) ? $ac->text->persiaPayTAddon : esc_html__('Persia Payment Add-on','easy-form-builder'),
			"persiaPayDAddon" => $state  &&  isset($ac->text->persiaPayDAddon) ? $ac->text->persiaPayDAddon : esc_html__('The Persia payment add-on for Easy Form Builder enables you to connect your website with Persia payment to process payments, donations, and online orders.','easy-form-builder'),

			"datePTAddon" => $state  &&  isset($ac->text->datePTAddon) ? $ac->text->datePTAddon : esc_html__('Jalali Date Add-on','easy-form-builder'),
			/* translators: Shamsi = another name for Jalali/Persian calendar */
			"datePDAddon" => $state  &&  isset($ac->text->datePDAddon) ? $ac->text->datePDAddon : esc_html__('The Jalali Date add-on allows you to add a Jalali date field to your forms and create any type of form that includes this Shamsi date field.','easy-form-builder'),
			"dateATAddon" => $state  &&  isset($ac->text->dateATAddon) ? $ac->text->dateATAddon : esc_html__('Hijri Date Add-on','easy-form-builder'),
			"dateADAddon" => $state  &&  isset($ac->text->dateADAddon) ? $ac->text->dateADAddon : esc_html__('The Hijri Date add-on allows you to add a Hijri date field to your forms and create any type of form that includes this field.','easy-form-builder'),
			"smsTAddon" => $state  &&  isset($ac->text->smsTAddon) ? $ac->text->smsTAddon : esc_html__('SMS service Add-on','easy-form-builder'),
			"smsDAddon" => $state  &&  isset($ac->text->smsDAddon) ? $ac->text->smsDAddon : esc_html__('The SMS service add-on enables you to receive notification SMS messages when you or your customers receive new messages or responses.','easy-form-builder'),
			"mPAdateW" => $state  &&  isset($ac->text->mPAdateW) ? $ac->text->mPAdateW : esc_html__('Please install either the Hijri or Jalali date add-on. You cannot install both add-ons simultaneously.','easy-form-builder'),
			/* translators: Response box = admin panel where responses/submissions are managed and replied to */
			"rbox" => $state  &&  isset($ac->text->rbox) ? $ac->text->rbox : esc_html__('Response box','easy-form-builder'),
			"smartcr" => $state  &&  isset($ac->text->smartcr) ? $ac->text->smartcr : esc_html__('Regions Drop-Down','easy-form-builder'),




			"wmaddon" => $state  &&  isset($ac->text->wmaddon) ? $ac->text->wmaddon : esc_html__('You are seeing this message because your required add-ons are being installed. Please wait a few minutes and then visit this page again. If it has been more than five minutes and nothing has happened, please contact the support team of Easy Form Builder at Whitestudio.team.','easy-form-builder'),
			"cpnnc" => $state  &&  isset($ac->text->cpnnc) ? $ac->text->cpnnc : esc_html__('The cell phone number is incorrect','easy-form-builder'),
			"icc" => $state  &&  isset($ac->text->icc) ? $ac->text->icc : esc_html__('Invalid country code','easy-form-builder'),
			"cpnts" => $state  &&  isset($ac->text->cpnts) ? $ac->text->cpnts : esc_html__('The cell phone number is too short','easy-form-builder'),
			"cpntl" => $state  &&  isset($ac->text->cpntl) ? $ac->text->cpntl : esc_html__('The cell phone number is too long','easy-form-builder'),
			"scdnmi" => $state  &&  isset($ac->text->scdnmi) ? $ac->text->scdnmi : esc_html__('Please select the number of countries to display within an acceptable range.','easy-form-builder'),
			"dField" => $state  &&  isset($ac->text->dField) ? $ac->text->dField : esc_html__('Disabled Field','easy-form-builder'),
			"hField" => $state  &&  isset($ac->text->hField) ? $ac->text->hField : esc_html__('Hidden Field','easy-form-builder'),
			"sctdlosp" => $state  &&  isset($ac->text->sctdlosp) ? $ac->text->sctdlosp : esc_html__('Select a country to display a list of states/provinces.','easy-form-builder'),
			"sctdlocp" => $state  &&  isset($ac->text->sctdlocp) ? $ac->text->sctdlocp : esc_html__('Select a state/province to display a list of cities.','easy-form-builder'),

			"AdnOF" => $state  &&  isset($ac->text->AdnOf) ? $ac->text->AdnOf : esc_html__('Offline Forms Add-on','easy-form-builder'),
			"AdnSPF" => $state  &&  isset($ac->text->AdnSPF) ? $ac->text->AdnSPF : esc_html__('Stripe Payment Add-on','easy-form-builder'),
			"AdnPDP" => $state  &&  isset($ac->text->AdnPDP) ? $ac->text->AdnPDP : esc_html__('Jalali Date Add-on','easy-form-builder'),
			"AdnADP" => $state  &&  isset($ac->text->AdnADP) ? $ac->text->AdnADP : esc_html__('Hijri Date Add-on','easy-form-builder'),
			"AdnPPF" => $state  &&  isset($ac->text->AdnPPF) ? $ac->text->AdnPPF : esc_html__('Persia Payment Add-on','easy-form-builder'),
			"AdnSS" => $state  &&  isset($ac->text->AdnSS) ? $ac->text->AdnSS : esc_html__('SMS Service Add-on','easy-form-builder'),
			"tfnapca" => $state  &&  isset($ac->text->tfnapca) ? $ac->text->tfnapca : esc_html__('This field is currently unavailable. Please contact the site administrator.','easy-form-builder'),
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
			/* translators: Conditional Logic Add-on = name shown in the add-ons list */
			"AdnSMF" => $state  &&  isset($ac->text->AdnSMF) ? $ac->text->AdnSMF : esc_html__('Conditional Logic Add-on','easy-form-builder'),
			/* translators: Conditional Logic Add-on = header title on the add-on's settings page */
			"condATAddon" => $state  &&  isset($ac->text->condATAddon) ? $ac->text->condATAddon : esc_html__('Conditional logic Add-on','easy-form-builder'),
			/* translators: Description of the Conditional Logic add-on shown on its settings page */
			"condADAddon" => $state  &&  isset($ac->text->condADAddon) ? $ac->text->condADAddon : esc_html__('The Conditional Logic Add-on enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users’ unique needs.','easy-form-builder'),
			/* translators: Shown when the Conditional Logic builder script fails to load in the form editor */
			"logicLoadError" => $state  &&  isset($ac->text->logicLoadError) ? $ac->text->logicLoadError : esc_html__('The Conditional Logic module failed to load. Please refresh the page; if the problem continues, deactivate and reactivate the Conditional Logic add-on.','easy-form-builder'),
			/* translators: Toggle label - stop evaluating further rules once this rule matches */
			"stopProcessing" => $state  &&  isset($ac->text->stopProcessing) ? $ac->text->stopProcessing : esc_html__('Stop after this rule matches','easy-form-builder'),
			/* translators: IF = section label that introduces the conditions of a conditional-logic rule */
			"logicIf" => $state  &&  isset($ac->text->logicIf) ? $ac->text->logicIf : esc_html__('IF','easy-form-builder'),
			/* translators: THEN = section label that introduces the actions of a conditional-logic rule */
			"logicThen" => $state  &&  isset($ac->text->logicThen) ? $ac->text->logicThen : esc_html__('THEN','easy-form-builder'),
			/* translators: Test Mode = tab that lets the admin simulate rules with sample values */
			"testMode" => $state  &&  isset($ac->text->testMode) ? $ac->text->testMode : esc_html__('Test Mode','easy-form-builder'),
			/* translators: Run Test = button that executes the rule test */
			"runTest" => $state  &&  isset($ac->text->runTest) ? $ac->text->runTest : esc_html__('Run Test','easy-form-builder'),
			/* translators: Matched = test result shown when a rule's condition evaluated to true */
			"matched" => $state  &&  isset($ac->text->matched) ? $ac->text->matched : esc_html__('Matched','easy-form-builder'),
			/* translators: Not matched = test result shown when a rule's condition evaluated to false */
			"notMatched" => $state  &&  isset($ac->text->notMatched) ? $ac->text->notMatched : esc_html__('Not matched','easy-form-builder'),
			/* translators: Skipped = test result shown when a rule was not evaluated at all */
			"skipped" => $state  &&  isset($ac->text->skipped) ? $ac->text->skipped : esc_html__('Skipped','easy-form-builder'),
			/* translators: Empty state shown when the form has no fields to build conditions from */
			"noFields" => $state  &&  isset($ac->text->noFields) ? $ac->text->noFields : esc_html__('No fields found.','easy-form-builder'),
			/* translators: %1$s = maximum number of items allowed, %2$s = item label (e.g. rules, conditions). Shown when the current plan's limit is reached. */
			"planLimitReached" => $state  &&  isset($ac->text->planLimitReached) ? $ac->text->planLimitReached : esc_html__('You can create up to %1$s %2$s on your current plan. Upgrade to Pro for unlimited access.','easy-form-builder'),
			/* translators: Inspector = panel showing the details of the last rule test run */
			"inspector" => $state  &&  isset($ac->text->inspector) ? $ac->text->inspector : esc_html__('Inspector','easy-form-builder'),
			/* translators: Rule trace = section listing the step-by-step evaluation of a rule during testing */
			"logicTrace" => $state  &&  isset($ac->text->logicTrace) ? $ac->text->logicTrace : esc_html__('Rule trace','easy-form-builder'),
			/* translators: Final values = section showing the resulting field values after rules ran */
			"finalValues" => $state  &&  isset($ac->text->finalValues) ? $ac->text->finalValues : esc_html__('Final values','easy-form-builder'),
			/* translators: Effects = section listing the actions a rule performed during testing */
			"effects" => $state  &&  isset($ac->text->effects) ? $ac->text->effects : esc_html__('Effects','easy-form-builder'),
			/* translators: Conflicts = section listing rules whose actions contradict each other */
			"conflicts" => $state  &&  isset($ac->text->conflicts) ? $ac->text->conflicts : esc_html__('Conflicts','easy-form-builder'),
			/* translators: Warning shown when one rule writes a value to a field that another rule hides or disables, so the value is dropped from the entry */
			"valueOnStrippedField" => $state  &&  isset($ac->text->valueOnStrippedField) ? $ac->text->valueOnStrippedField : esc_html__('A value is written to this field while another rule hides or disables it &mdash; hidden and disabled fields are not saved with the entry.','easy-form-builder'),
			/* translators: Reason shown when a rule was skipped because an earlier rule stopped processing */
			"blockedByStop" => $state  &&  isset($ac->text->blockedByStop) ? $ac->text->blockedByStop : esc_html__('Blocked by stop processing','easy-form-builder'),
			/* translators: Calculate = action type that computes a value from a formula */
			"calculate" => $state  &&  isset($ac->text->calculate) ? $ac->text->calculate : esc_html__('Calculate','easy-form-builder'),
			/* translators: Formula = field label for the calculation expression input */
			"formula" => $state  &&  isset($ac->text->formula) ? $ac->text->formula : esc_html__('Formula','easy-form-builder'),
			/* translators: Decimals = field label for the number of decimal places in a calculated value */
			"decimals" => $state  &&  isset($ac->text->decimals) ? $ac->text->decimals : esc_html__('Decimals','easy-form-builder'),
			/* translators: Insert field = button that inserts a field token into the formula editor */
			"insertField" => $state  &&  isset($ac->text->insertField) ? $ac->text->insertField : esc_html__('Insert field','easy-form-builder'),
			/* translators: Error shown when a Calculate action's formula cannot be evaluated */
			"formulaInvalid" => $state  &&  isset($ac->text->formulaInvalid) ? $ac->text->formulaInvalid : esc_html__('Formula could not be calculated. Check field tokens and division by zero.','easy-form-builder'),
			/* translators: Set Value = action type that sets a field's value */
			"setValue" => $state  &&  isset($ac->text->setValue) ? $ac->text->setValue : esc_html__('Set Value','easy-form-builder'),
			/* translators: Clear Value = action type that empties a field's value */
			"clearValue" => $state  &&  isset($ac->text->clearValue) ? $ac->text->clearValue : esc_html__('Clear Value','easy-form-builder'),
			/* translators: Show Message = action type that displays a message to the visitor */
			"showMessage" => $state  &&  isset($ac->text->showMessage) ? $ac->text->showMessage : esc_html__('Show Message','easy-form-builder'),
			/* translators: Jump to Step = action type that moves a multi-step form to another step */
			"jumpStep" => $state  &&  isset($ac->text->jumpStep) ? $ac->text->jumpStep : esc_html__('Jump to Step','easy-form-builder'),
			/* translators: Static = value type meaning a fixed, literal value rather than a dynamic one */
			"staticValue" => $state  &&  isset($ac->text->staticValue) ? $ac->text->staticValue : esc_html__('Static','easy-form-builder'),
			/* translators: Optional = marks a rule field as not required */
			"optional" => $state  &&  isset($ac->text->optional) ? $ac->text->optional : esc_html__('Optional','easy-form-builder'),
			/* translators: Enable = generic toggle action label */
			"enable" => $state  &&  isset($ac->text->enable) ? $ac->text->enable : esc_html__('Enable','easy-form-builder'),
			/* translators: Disable = generic toggle action label */
			"disable" => $state  &&  isset($ac->text->disable) ? $ac->text->disable : esc_html__('Disable','easy-form-builder'),
			/* translators: Enabled = generic status label */
			"enabled" => $state  &&  isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Enabled','easy-form-builder'),
			/* translators: Notifications = action type/tab for sending notifications */
			"notifications" => $state  &&  isset($ac->text->notifications) ? $ac->text->notifications : esc_html__('Notifications','easy-form-builder'),
			/* translators: Confirmation = action type/tab for the post-submit confirmation message */
			"confirmation" => $state  &&  isset($ac->text->confirmation) ? $ac->text->confirmation : esc_html__('Confirmation','easy-form-builder'),
			/* translators: Webhook = action type/tab for triggering a webhook request */
			"webhook" => $state  &&  isset($ac->text->webhook) ? $ac->text->webhook : esc_html__('Webhook','easy-form-builder'),
			/* translators: Fields = tab listing the form's fields */
			"fields" => $state  &&  isset($ac->text->fields) ? $ac->text->fields : esc_html__('Fields','easy-form-builder'),
			/* translators: Redirect = action type that sends the visitor to another URL */
			"redirect" => $state  &&  isset($ac->text->redirect) ? $ac->text->redirect : esc_html__('Redirect','easy-form-builder'),
			/* translators: Shown = a field's visibility state after a show/hide rule runs */
			"shown" => $state  &&  isset($ac->text->shown) ? $ac->text->shown : esc_html__('Shown','easy-form-builder'),
			/* translators: Hidden = a field's visibility state after a show/hide rule runs */
			"hidden" => $state  &&  isset($ac->text->hidden) ? $ac->text->hidden : esc_html__('Hidden','easy-form-builder'),
			/* translators: Placeholder text for the Show Message action's free-text input */
			"enterText" => $state  &&  isset($ac->text->enterText) ? $ac->text->enterText : esc_html__('Message&hellip;','easy-form-builder'),
			/* translators: Stable = status meaning rule evaluation finished without looping */
			"stable" => $state  &&  isset($ac->text->stable) ? $ac->text->stable : esc_html__('Stable','easy-form-builder'),
			/* translators: Copy value from field = value source option that copies another field's value */
			"copyValue" => $state  &&  isset($ac->text->copyValue) ? $ac->text->copyValue : esc_html__('Copy value from field','easy-form-builder'),
			/* translators: Set placeholder = action type that changes a field's placeholder text */
			"setPlaceholder" => $state  &&  isset($ac->text->setPlaceholder) ? $ac->text->setPlaceholder : esc_html__('Set placeholder','easy-form-builder'),
			/* translators: Set help text = action type that changes a field's help text */
			"setHelp" => $state  &&  isset($ac->text->setHelp) ? $ac->text->setHelp : esc_html__('Set help text','easy-form-builder'),
			/* translators: Set label = action type that changes a field's label text */
			"setLabel" => $state  &&  isset($ac->text->setLabel) ? $ac->text->setLabel : esc_html__('Set label','easy-form-builder'),
			/* translators: Focus field = action type that moves keyboard focus to a field */
			"focusField" => $state  &&  isset($ac->text->focusField) ? $ac->text->focusField : esc_html__('Focus field','easy-form-builder'),
			/* translators: Scroll to field = action type that scrolls the page to a field */
			"scrollToField" => $state  &&  isset($ac->text->scrollToField) ? $ac->text->scrollToField : esc_html__('Scroll to field','easy-form-builder'),
			/* translators: Block submit = action type that prevents the form from being submitted */
			"blockSubmit" => $state  &&  isset($ac->text->blockSubmit) ? $ac->text->blockSubmit : esc_html__('Block submit','easy-form-builder'),
			/* translators: Custom submission message = action type that ends the form early with a custom message */
			"endForm" => $state  &&  isset($ac->text->endForm) ? $ac->text->endForm : esc_html__('Custom submission message','easy-form-builder'),
			/* translators: URL parameter = condition source based on a URL query parameter */
			"urlParam" => $state  &&  isset($ac->text->urlParam) ? $ac->text->urlParam : esc_html__('URL parameter','easy-form-builder'),
			/* translators: User = condition source group based on the visitor/logged-in user */
			"userSource" => $state  &&  isset($ac->text->userSource) ? $ac->text->userSource : esc_html__('User','easy-form-builder'),
			/* translators: Current step = condition source based on the multi-step form's active step */
			"currentStep" => $state  &&  isset($ac->text->currentStep) ? $ac->text->currentStep : esc_html__('Current step','easy-form-builder'),
			/* translators: Logged in = condition option matching a logged-in WordPress user */
			"loggedIn" => $state  &&  isset($ac->text->loggedIn) ? $ac->text->loggedIn : esc_html__('Logged in','easy-form-builder'),
			/* translators: Logged out = condition option matching a logged-out (guest) visitor */
			"loggedOut" => $state  &&  isset($ac->text->loggedOut) ? $ac->text->loggedOut : esc_html__('Logged out','easy-form-builder'),
			/* translators: Role = condition source based on the visitor's WordPress user role */
			"userRole" => $state  &&  isset($ac->text->userRole) ? $ac->text->userRole : esc_html__('Role','easy-form-builder'),
			/* translators: before = date comparison operator */
			"dateBefore" => $state  &&  isset($ac->text->dateBefore) ? $ac->text->dateBefore : esc_html__('before','easy-form-builder'),
			/* translators: after = date comparison operator */
			"dateAfter" => $state  &&  isset($ac->text->dateAfter) ? $ac->text->dateAfter : esc_html__('after','easy-form-builder'),
			/* translators: between dates = date comparison operator for a date range */
			"dateBetween" => $state  &&  isset($ac->text->dateBetween) ? $ac->text->dateBetween : esc_html__('between dates','easy-form-builder'),
			/* translators: NOT = logical negation operator label */
			"notOperator" => $state  &&  isset($ac->text->notOperator) ? $ac->text->notOperator : esc_html__('NOT','easy-form-builder'),
			/* translators: Toggle that inverts (logical NOT) an entire condition group; NAND/NOR are boolean-logic terms shown as a hint */
			"negateGroup" => $state  &&  isset($ac->text->negateGroup) ? $ac->text->negateGroup : esc_html__('NOT — invert this group (NAND/NOR)','easy-form-builder'),
			/* translators: Export = button that downloads the logic rules as a file */
			"exportRules" => $state  &&  isset($ac->text->exportRules) ? $ac->text->exportRules : esc_html__('Export','easy-form-builder'),
			/* translators: Import = button that loads logic rules from a file */
			"importRules" => $state  &&  isset($ac->text->importRules) ? $ac->text->importRules : esc_html__('Import','easy-form-builder'),
			/* translators: Success message shown after importing logic rules */
			"importDone" => $state  &&  isset($ac->text->importDone) ? $ac->text->importDone : esc_html__('Rules imported. Review and save the form.','easy-form-builder'),
			/* translators: Error shown when the imported file is not a valid logic-rules export */
			"importInvalid" => $state  &&  isset($ac->text->importInvalid) ? $ac->text->importInvalid : esc_html__('This file is not a valid EFB logic-rules export.','easy-form-builder'),
			/* translators: Duplicate = button that copies a rule or condition group */
			"duplicate" => $state  &&  isset($ac->text->duplicate) ? $ac->text->duplicate : esc_html__('Duplicate','easy-form-builder'),
			/* translators: Field label for choosing which fields are sent in the webhook payload; empty selection means all fields */
			"payloadFields" => $state  &&  isset($ac->text->payloadFields) ? $ac->text->payloadFields : esc_html__('Payload fields (empty = all)','easy-form-builder'),
			/* translators: Trigger webhook = action type that calls a webhook URL */
			"triggerWebhook" => $state  &&  isset($ac->text->triggerWebhook) ? $ac->text->triggerWebhook : esc_html__('Trigger webhook','easy-form-builder'),
			/* translators: Stop webhook = action type that cancels a previously triggered webhook */
			"stopWebhook" => $state  &&  isset($ac->text->stopWebhook) ? $ac->text->stopWebhook : esc_html__('Stop webhook','easy-form-builder'),
			/* translators: Hint under the stop-webhook field explaining that an empty value stops every webhook */
			"stopWebhookHint" => $state  &&  isset($ac->text->stopWebhookHint) ? $ac->text->stopWebhookHint : esc_html__('empty = stop all','easy-form-builder'),
			/* translators: Message shown to the visitor when a Block submit rule prevents form submission */
			"submitBlocked" => $state  &&  isset($ac->text->submitBlocked) ? $ac->text->submitBlocked : esc_html__('Submission is not allowed for the current answers.','easy-form-builder'),
			/* translators: Message shown to the visitor when a rule ends the form early */
			"formEnded" => $state  &&  isset($ac->text->formEnded) ? $ac->text->formEnded : esc_html__('This form is closed for your answers.','easy-form-builder'),
			/* translators: Warning shown when rule evaluation does not settle, e.g. two rules keep toggling each other (a possible loop) */
			"loopWarning" => $state  &&  isset($ac->text->loopWarning) ? $ac->text->loopWarning : esc_html__('Rules did not stabilize (possible loop)','easy-form-builder'),
			/* translators: Empty state prompting the admin to add their first logic rule */
			"addFirstRule" => $state  &&  isset($ac->text->addFirstRule) ? $ac->text->addFirstRule : esc_html__('Add your first rule to start building smart forms.','easy-form-builder'),

			/* translators: Enable Conditional = toggle label that turns on conditional logic for a field */
			"condlogic" => $state  &&  isset($ac->text->condlogic) ? $ac->text->condlogic : esc_html__('Enable Conditional','easy-form-builder'),
			/* translators: Show = field-level show/hide action option */
			"show" => $state  &&  isset($ac->text->show) ? $ac->text->show : esc_html__('Show','easy-form-builder'),
			/* translators: Hide = field-level show/hide action option */
			"hide" => $state  &&  isset($ac->text->hide) ? $ac->text->hide : esc_html__('Hide','easy-form-builder'),
			/* translators: Contains = text comparison operator */
			"contains" => $state  &&  isset($ac->text->contains) ? $ac->text->contains : esc_html__('Contains','easy-form-builder'),
			/* translators: Not contain = text comparison operator, the negated form of Contains */
			"ncontains" => $state  &&  isset($ac->text->ncontains) ? $ac->text->ncontains : esc_html__('Not contain','easy-form-builder'),
			/* translators: starts with = text comparison operator */
			"startw" => $state  &&  isset($ac->text->startw) ? $ac->text->startw : esc_html__('starts with','easy-form-builder'),
			/* translators: ends with = text comparison operator */
			"endw" => $state  &&  isset($ac->text->endw) ? $ac->text->endw : esc_html__('ends with','easy-form-builder'),
			/* translators: greater than = numeric comparison operator */
			"gthan" => $state  &&  isset($ac->text->gthan) ? $ac->text->gthan : esc_html__('greater than','easy-form-builder'),
			/* translators: less than = numeric comparison operator */
			"lthan" => $state  &&  isset($ac->text->lthan) ? $ac->text->lthan : esc_html__('less than','easy-form-builder'),
			/* translators: greater than or equal to = numeric comparison operator */
			"gtehan" => $state  &&  isset($ac->text->gtehan) ? $ac->text->gtehan : esc_html__('greater than or equal to','easy-form-builder'),
			/* translators: less than or equal to = numeric comparison operator */
			"ltehan" => $state  &&  isset($ac->text->ltehan) ? $ac->text->ltehan : esc_html__('less than or equal to','easy-form-builder'),
			/* translators: between = numeric/date range comparison operator */
			"between" => $state  &&  isset($ac->text->between) ? $ac->text->between : esc_html__('between','easy-form-builder'),
			/* translators: not between = numeric/date range comparison operator, the negated form of "between" */
			"nBetween" => $state  &&  isset($ac->text->nBetween) ? $ac->text->nBetween : esc_html__('not between','easy-form-builder'),
			/* translators: Is = equality comparison operator */
			"ise" => $state  &&  isset($ac->text->ise) ? $ac->text->ise : esc_html__('Is','easy-form-builder'),
			/* translators: Is not = equality comparison operator, the negated form of "Is" */
			"isne" => $state  &&  isset($ac->text->isne) ? $ac->text->isne : esc_html__('Is not','easy-form-builder'),
			/* translators: Empty = comparison operator meaning the field has no value */
			"empty" => $state  &&  isset($ac->text->empty) ? $ac->text->empty : esc_html__('Empty','easy-form-builder'),
			/* translators: Not empty = comparison operator meaning the field has a value */
			"nEmpty" => $state  &&  isset($ac->text->nEmpty) ? $ac->text->nEmpty : esc_html__('Not empty','easy-form-builder'),
			/* translators: OR = logical operator meaning one option or the other */
			"or" => $state  &&  isset($ac->text->or) ? $ac->text->or : esc_html__('or','easy-form-builder'),
			/* translators: AND = logical operator, paired with the OR operator above */
			"and" => $state  &&  isset($ac->text->and) ? $ac->text->and : esc_html__('and','easy-form-builder'),
			/* translators: Conditions = tab/section listing a group's conditions */
			"logicConditions" => $state  &&  isset($ac->text->logicConditions) ? $ac->text->logicConditions : esc_html__('Conditions','easy-form-builder'),
			/* translators: Condition = singular label for one condition row */
			"logicCondition" => $state  &&  isset($ac->text->logicCondition) ? $ac->text->logicCondition : esc_html__('Condition','easy-form-builder'),
			/* translators: Group = label for a group of conditions */
			"logicGroup" => $state  &&  isset($ac->text->logicGroup) ? $ac->text->logicGroup : esc_html__('Group','easy-form-builder'),
			/* translators: Placeholder message shown when a condition group has nothing added to it yet */
			"logicGroupEmpty" => $state  &&  isset($ac->text->logicGroupEmpty) ? $ac->text->logicGroupEmpty : esc_html__('Add a condition or a group.','easy-form-builder'),
			/* translators: Done title = field label for the completion screen's title text */
			"doneTitle" => $state  &&  isset($ac->text->doneTitle) ? $ac->text->doneTitle : esc_html__('Done title','easy-form-builder'),
			/* translators: Icon = field label for the completion screen's icon */
			"doneIcon" => $state  &&  isset($ac->text->doneIcon) ? $ac->text->doneIcon : esc_html__('Icon','easy-form-builder'),
			/* translators: Field label for the text shown next to the submission confirmation code */
			"trackingCodeLabel" => $state  &&  isset($ac->text->trackingCodeLabel) ? $ac->text->trackingCodeLabel : esc_html__('Confirmation code label','easy-form-builder'),
			/* translators: Icon color = field label for the completion screen's icon color */
			"iconColor" => $state  &&  isset($ac->text->iconColor) ? $ac->text->iconColor : esc_html__('Icon color','easy-form-builder'),
			/* translators: Title color = field label for the completion screen's title color */
			"titleColor" => $state  &&  isset($ac->text->titleColor) ? $ac->text->titleColor : esc_html__('Title color','easy-form-builder'),
			/* translators: Message color = field label for the completion screen's message color */
			"messageColor" => $state  &&  isset($ac->text->messageColor) ? $ac->text->messageColor : esc_html__('Message color','easy-form-builder'),
			/* translators: Default = marks an option as the default choice */
			"defaultOpt" => $state  &&  isset($ac->text->defaultOpt) ? $ac->text->defaultOpt : esc_html__('Default','easy-form-builder'),


			"pgbar" => $state  &&  isset($ac->text->pgbar) ? $ac->text->pgbar : esc_html__('Progress bar','easy-form-builder'),
			"cities" => $state  &&  isset($ac->text->cities) ? $ac->text->cities : esc_html__('cities','easy-form-builder'),
			"list" => $state  &&  isset($ac->text->list) ? $ac->text->list : esc_html__('XXX list','easy-form-builder'),
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
			"payPalTAddon" => $state  &&  isset($ac->text->payPalTAddon) ? $ac->text->payPalTAddon : esc_html__('PayPal Payment Add-on','easy-form-builder'),
			"payPalDAddon" => $state  &&  isset($ac->text->payPaleDAddon) ? $ac->text->payPaleDAddon : esc_html__('The PayPal add-on for Easy Form Builder enables you to integrate your WordPress site with PayPal for payment processing, donations, and online orders.','easy-form-builder'),
			"file_cstm" => $state  &&  isset($ac->text->file_cstm) ? $ac->text->file_cstm : esc_html__('Acceptable file types','easy-form-builder'),
			"cstm_rd" => $state  &&  isset($ac->text->cstm_rd) ? $ac->text->cstm_rd : esc_html__('Customized Ordering','easy-form-builder'),
			"maxfs" => $state  &&  isset($ac->text->maxfs) ? $ac->text->maxfs : esc_html__('Max File Size','easy-form-builder'),
			"recStart" => $state && isset($ac->text->recStart) ? $ac->text->recStart : esc_html__('Start Recording','easy-form-builder'),
			"recStop" => $state && isset($ac->text->recStop) ? $ac->text->recStop : esc_html__('Stop','easy-form-builder'),
			"recPause" => $state && isset($ac->text->recPause) ? $ac->text->recPause : esc_html__('Pause','easy-form-builder'),
			"recResume" => $state && isset($ac->text->recResume) ? $ac->text->recResume : esc_html__('Resume','easy-form-builder'),
			"recRedo" => $state && isset($ac->text->recRedo) ? $ac->text->recRedo : esc_html__('Re-record','easy-form-builder'),
			"recPlay" => $state && isset($ac->text->recPlay) ? $ac->text->recPlay : esc_html__('Play','easy-form-builder'),
			"recReady" => $state && isset($ac->text->recReady) ? $ac->text->recReady : esc_html__('Ready to record','easy-form-builder'),
			"recRecording" => $state && isset($ac->text->recRecording) ? $ac->text->recRecording : esc_html__('Recording…','easy-form-builder'),
			"recPaused" => $state && isset($ac->text->recPaused) ? $ac->text->recPaused : esc_html__('Paused','easy-form-builder'),
			"recReadyToSubmit" => $state && isset($ac->text->recReadyToSubmit) ? $ac->text->recReadyToSubmit : esc_html__('Recording ready.','easy-form-builder'),
			"recUpload" => $state && isset($ac->text->recUpload) ? $ac->text->recUpload : esc_html__('Upload','easy-form-builder'),
			"recUploading" => $state && isset($ac->text->recUploading) ? $ac->text->recUploading : esc_html__('Uploading recording…','easy-form-builder'),
			"recUploaded" => $state && isset($ac->text->recUploaded) ? $ac->text->recUploaded : esc_html__('Recording uploaded.','easy-form-builder'),
			"recUploadFailed" => $state && isset($ac->text->recUploadFailed) ? $ac->text->recUploadFailed : esc_html__('The recording could not be uploaded. Please try again.','easy-form-builder'),
			"recUploadOffline" => $state && isset($ac->text->recUploadOffline) ? $ac->text->recUploadOffline : esc_html__('You are offline. Please reconnect and upload the recording again.','easy-form-builder'),
			"recUploadUnavailable" => $state && isset($ac->text->recUploadUnavailable) ? $ac->text->recUploadUnavailable : esc_html__('Uploading is not available right now. Please try again.','easy-form-builder'),
			"recNoFile" => $state && isset($ac->text->recNoFile) ? $ac->text->recNoFile : esc_html__('No recording is ready to upload.','easy-form-builder'),
			"recFileTooLarge" => $state && isset($ac->text->recFileTooLarge) ? $ac->text->recFileTooLarge : esc_html__('The recording is larger than the allowed file size.','easy-form-builder'),
			"recInvalidFile" => $state && isset($ac->text->recInvalidFile) ? $ac->text->recInvalidFile : esc_html__('This recording format is not allowed.','easy-form-builder'),
			"recDurationExceeded" => $state && isset($ac->text->recDurationExceeded) ? $ac->text->recDurationExceeded : esc_html__('The recording is longer than the allowed duration.','easy-form-builder'),
			"recQuality" => $state && isset($ac->text->recQuality) ? $ac->text->recQuality : esc_html__('Recording Quality','easy-form-builder'),
			"recDuration" => $state && isset($ac->text->recDuration) ? $ac->text->recDuration : esc_html__('Max Duration (seconds)','easy-form-builder'),
			"recQualityLow" => $state && isset($ac->text->recQualityLow) ? $ac->text->recQualityLow : esc_html__('Low','easy-form-builder'),
			"recQualityStandard" => $state && isset($ac->text->recQualityStandard) ? $ac->text->recQualityStandard : esc_html__('Standard','easy-form-builder'),
			"recQualityHigh" => $state && isset($ac->text->recQualityHigh) ? $ac->text->recQualityHigh : esc_html__('High','easy-form-builder'),
			"recQuality480" => $state && isset($ac->text->recQuality480) ? $ac->text->recQuality480 : esc_html__('480p','easy-form-builder'),
			"recQuality720" => $state && isset($ac->text->recQuality720) ? $ac->text->recQuality720 : esc_html__('720p (HD)','easy-form-builder'),
			"recQuality1080" => $state && isset($ac->text->recQuality1080) ? $ac->text->recQuality1080 : esc_html__('1080p (Full HD)','easy-form-builder'),
			"recPermissionDenied" => $state && isset($ac->text->recPermissionDenied) ? $ac->text->recPermissionDenied : esc_html__('Permission to access your microphone/camera/screen was denied.','easy-form-builder'),
			"recNotSupported" => $state && isset($ac->text->recNotSupported) ? $ac->text->recNotSupported : esc_html__('Your browser does not support this recording feature.','easy-form-builder'),
			"recMaxDurationReached" => $state && isset($ac->text->recMaxDurationReached) ? $ac->text->recMaxDurationReached : esc_html__('Maximum recording duration reached.','easy-form-builder'),
			/* translators: Watermark label shown over the video/screen recorder preview frame */
			"recWatermark" => $state && isset($ac->text->recWatermark) ? $ac->text->recWatermark : esc_html__('Made by Easy Form Builder','easy-form-builder'),
			"recTapToStart" => $state && isset($ac->text->recTapToStart) ? $ac->text->recTapToStart : esc_html__('Tap to start recording','easy-form-builder'),
			/* translators: Button title: download a local copy of the recording */
			"recDownload" => $state && isset($ac->text->recDownload) ? $ac->text->recDownload : esc_html__('Download recording','easy-form-builder'),
			/* translators: Shown when the page is served over plain HTTP so browsers block mic/camera/screen APIs */
			"recNeedsHttps" => $state && isset($ac->text->recNeedsHttps) ? $ac->text->recNeedsHttps : esc_html__('Recording requires a secure (HTTPS) connection. Please ask the site administrator to enable HTTPS on this host.','easy-form-builder'),
			/* translators: Shown for the screen recorder on browsers without getDisplayMedia (practically all mobile browsers) */
			"recScreenNotSupported" => $state && isset($ac->text->recScreenNotSupported) ? $ac->text->recScreenNotSupported : esc_html__('Screen recording is not supported on this device or browser (most mobile browsers do not allow it). Please open the form in a desktop browser such as Chrome, Edge or Firefox.','easy-form-builder'),
			/* translators: Setting label: get-ready countdown shown before recording starts */
			"recCountdown" => $state && isset($ac->text->recCountdown) ? $ac->text->recCountdown : esc_html__('Countdown before recording','easy-form-builder'),
			/* translators: Setting label: which phone camera the video recorder opens with */
			"recFacing" => $state && isset($ac->text->recFacing) ? $ac->text->recFacing : esc_html__('Default camera (mobile)','easy-form-builder'),
			"recFacingFront" => $state && isset($ac->text->recFacingFront) ? $ac->text->recFacingFront : esc_html__('Front (selfie)','easy-form-builder'),
			"recFacingBack" => $state && isset($ac->text->recFacingBack) ? $ac->text->recFacingBack : esc_html__('Back (environment)','easy-form-builder'),
			/* translators: Setting label: mirror the live camera preview (recorded file stays unmirrored) */
			"recMirror" => $state && isset($ac->text->recMirror) ? $ac->text->recMirror : esc_html__('Mirror the live preview','easy-form-builder'),
			/* translators: Setting label: microphone noise suppression / echo cancellation */
			"recNoise" => $state && isset($ac->text->recNoise) ? $ac->text->recNoise : esc_html__('Noise suppression & echo cancellation','easy-form-builder'),
			/* translators: Setting label: show the watermark overlay on the recorder preview */
			"recShowWatermark" => $state && isset($ac->text->recShowWatermark) ? $ac->text->recShowWatermark : esc_html__('Show watermark on the preview','easy-form-builder'),
			/* translators: Setting label: let the visitor download a copy of their recording */
			"recAllowDownload" => $state && isset($ac->text->recAllowDownload) ? $ac->text->recAllowDownload : esc_html__('Let the user download a copy of the recording','easy-form-builder'),
			/* translators: %s = megabytes. Hint under the max file size setting showing the hosting upload limit */
			"hostUploadLimit" => $state && isset($ac->text->hostUploadLimit) ? $ac->text->hostUploadLimit : esc_html__('Your hosting accepts uploads up to %s MB.','easy-form-builder'),
			/* translators: %s = megabytes. Warning when the configured size exceeds the hosting upload limit */
			"hostUploadLimitOver" => $state && isset($ac->text->hostUploadLimitOver) ? $ac->text->hostUploadLimitOver : esc_html__('This is larger than the hosting upload limit (%s MB); uploads will fail.','easy-form-builder'),
			"cityList" => $state  &&  isset($ac->text->cityList) ? $ac->text->cityList : esc_html__('Cities Drop-Down','easy-form-builder'),
			"elan" => $state  &&  isset($ac->text->elan) ? $ac->text->elan : esc_html__('English language','easy-form-builder'),
			"nlan" => $state  &&  isset($ac->text->nlan) ? $ac->text->nlan : esc_html__('National language','easy-form-builder'),
			"stsd" => $state  &&  isset($ac->text->stsd) ? $ac->text->stsd : esc_html__('Select display language','easy-form-builder'),

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

			/* translators: Dashboard widget — card label: %s is replaced with the word 'Page' at runtime. Example: "Page views" */
			"dwVisits" => $state  &&  isset($ac->text->dwVisits) ? $ac->text->dwVisits : esc_html__('%s views','easy-form-builder'),
			/* translators: Dashboard widget — card label: number of form submissions */
			"dwSubmissions" => $state  &&  isset($ac->text->dwSubmissions) ? $ac->text->dwSubmissions : esc_html__('Submissions','easy-form-builder'),
			/* translators: Generic noun used in labels like "%s Views". Example: "Page" */
			"page" => $state  &&  isset($ac->text->page) ? $ac->text->page : esc_html__('Page','easy-form-builder'),
			/* translators: Dashboard widget card label. Example: "Emails sent" */
			"dwEmailsSent" => $state  &&  isset($ac->text->dwEmailsSent) ? $ac->text->dwEmailsSent : esc_html__('Emails sent','easy-form-builder'),
			/* translators: Dashboard widget — card label: %s is replaced with the word 'Email' at runtime. Example: "Email Failures" */
			"dwEmailsFailed" => $state  &&  isset($ac->text->dwEmailsFailed) ? $ac->text->dwEmailsFailed : esc_html__('%s Failures','easy-form-builder'),
			/* translators: Dashboard widget — panel title: %s is replaced with the word 'Email' at runtime. Example: "Email Error Log" */
			"dwEmailErrors" => $state  &&  isset($ac->text->dwEmailErrors) ? $ac->text->dwEmailErrors : esc_html__('%s Error Log','easy-form-builder'),
			/* translators: Dashboard widget — table column header: recipient email address */
			"dwRecipient" => $state  &&  isset($ac->text->dwRecipient) ? $ac->text->dwRecipient : esc_html__('Recipient','easy-form-builder'),
			/* translators: Dashboard widget — table column header: %s is replaced with the word 'Error' at runtime. Example: "Error Details" */
			"dwErrorDetail" => $state  &&  isset($ac->text->dwErrorDetail) ? $ac->text->dwErrorDetail : esc_html__('%s Details','easy-form-builder'),
			/* translators: Dashboard widget — shown when there is no data to display */
			"dwNoData" => $state  &&  isset($ac->text->dwNoData) ? $ac->text->dwNoData : esc_html__('No data available for this period','easy-form-builder'),
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

			"tlgmAddon" => $state  &&  isset($ac->text->tlgmAddon) ? $ac->text->tlgmAddon : esc_html__('Telegram Notification Add-on','easy-form-builder'),
			"tlgmDAddon" => $state  &&  isset($ac->text->tlgmDAddon) ? $ac->text->tlgmDAddon : esc_html__('The Telegram notification add-on lets you get notifications on your Telegram app whenever you receive new messages or responses','easy-form-builder'),
			"eln" => $state  &&  isset($ac->text->eln) ? $ac->text->eln : esc_html__('Enter a location name','easy-form-builder'),

			/* translators: %1$s is the plugin name, %2$s and %3$s are opening and closing link tags for support */
			"alns" => $state  &&  isset($ac->text->alns) ? $ac->text->alns : esc_html__('The %1$s pages are currently unavailable. It looks like another plugin is causing a conflict with %1$s . To fix this issue, %2$s contact %1$s support %3$s for assistance or try disabling your plugins one at a time to identify the one causing the conflict.','easy-form-builder'),

			"settings" => $state  &&  isset($ac->text->settings) ? $ac->text->settings : esc_html__('Settings','easy-form-builder'),
			"emlcc" => $state  &&  isset($ac->text->emlcc) ? $ac->text->emlcc : esc_html__('Send email with submitted form content only','easy-form-builder'),
			"copied" => $state  &&  isset($ac->text->copied) ? $ac->text->copied : esc_html__('%s copied!','easy-form-builder'),
			"srvnrsp" => $state  &&  isset($ac->text->srvnrsp) ? $ac->text->srvnrsp : esc_html__('The website is not responding; please refresh and try again—saving or submitting is not available until it is restored.','easy-form-builder'),

			"sxnlex" => $state  &&  isset($ac->text->sxnlex) ? $ac->text->sxnlex : esc_html__('Your session has expired or is no longer valid. Please refresh the page to continue.','easy-form-builder'),
			"uraatn" => $state  &&  isset($ac->text->uraatn) ? $ac->text->uraatn : esc_html__('Your account has been successfully activated. You can now log in and get started!','easy-form-builder'),
			/* translators: Success message indicating completion */
			"yad" => $state  &&  isset($ac->text->yad) ? $ac->text->yad : esc_html__('You\'re all done','easy-form-builder'),
			"servpss" => $state  &&  isset($ac->text->servpss) ? $ac->text->servpss : esc_html__('Enter your email to reset your password','easy-form-builder'),
			"imvpwsy" => $state  &&  isset($ac->text->imvpwsy) ? $ac->text->imvpwsy : esc_html__('If your email is valid, a password reset link has been sent to your email address.','easy-form-builder'),
			/* translators: %s is the feature name being enabled (e.g., SMS, Email, Auto-Populate) */
			"enbl" => $state  &&  isset($ac->text->enbl) ? $ac->text->enbl : esc_html__('Enable %s','easy-form-builder'),
			"atfll" => $state  &&  isset($ac->text->atfll) ? $ac->text->atfll : esc_html__('Auto-Populate','easy-form-builder'),
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
			"selectFormFirst" => $state && isset($ac->text->selectFormFirst) ? $ac->text->selectFormFirst : esc_html__('Please select a form first', 'easy-form-builder'),
			"targetFieldsTitle" => $state && isset($ac->text->targetFieldsTitle) ? $ac->text->targetFieldsTitle : esc_html__('Target Fields (Fields to Fill)', 'easy-form-builder'),
			"targetFieldsInfo" => $state && isset($ac->text->targetFieldsInfo) ? $ac->text->targetFieldsInfo : esc_html__('Map API response fields to form fields. The API data will automatically fill these fields.', 'easy-form-builder'),
			"noFieldsFound" => $state && isset($ac->text->noFieldsFound) ? $ac->text->noFieldsFound : esc_html__('No fillable fields found in this form', 'easy-form-builder'),
			"atfllFieldSingular" => $state && isset($ac->text->atfllFieldSingular) ? $ac->text->atfllFieldSingular : esc_html(_n('field', 'fields', 1, 'easy-form-builder')),
			"atfllFieldPlural" => $state && isset($ac->text->atfllFieldPlural) ? $ac->text->atfllFieldPlural : esc_html(_n('field', 'fields', 2, 'easy-form-builder')),
			"apiFieldName" => $state && isset($ac->text->apiFieldName) ? $ac->text->apiFieldName : esc_html__('API Field Name', 'easy-form-builder'),
			"formFieldSelect" => $state && isset($ac->text->formFieldSelect) ? $ac->text->formFieldSelect : esc_html__('Form Field', 'easy-form-builder'),
			"selectField" => $state && isset($ac->text->selectField) ? $ac->text->selectField : esc_html__('Select Field', 'easy-form-builder'),
			"cacheSettings" => $state && isset($ac->text->cacheSettings) ? $ac->text->cacheSettings : esc_html__('Cache Settings', 'easy-form-builder'),
			"cacheHelp" => $state && isset($ac->text->cacheHelp) ? $ac->text->cacheHelp : esc_html__('Cache API responses to improve performance', 'easy-form-builder'),
			"externalApi" => $state && isset($ac->text->externalApi) ? $ac->text->externalApi : esc_html__('External API Connections', 'easy-form-builder'),
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
			"sSTAddon" => $state  &&  isset($ac->text->sSTAddon) ? $ac->text->sSTAddon : esc_html__('%s Payment Add-on','easy-form-builder'),
			/* translators: %1$s is the payment add-on name, %2$s is the payment processor name */
			"sSTDAddon" => $state  &&  isset($ac->text->sSTDAddon) ? $ac->text->sSTDAddon : esc_html__('The %s add-on for Easy Form Builder enables you to integrate your WordPress site with %s for payment processing, donations, and online orders.','easy-form-builder'),
			/* translators: Activation code = license key. */
			'activationCode' => $state  &&  isset($ac->text->activationCode) ? $ac->text->activationCode : esc_html__('Activation Code','easy-form-builder'),

			/* translators: Message indicating a feature is available in Free Plus or Pro versions */
			'thisFeatureAvailableFreePlusPro' => $state && isset($ac->text->thisFeatureAvailableFreePlusPro) ? $ac->text->thisFeatureAvailableFreePlusPro : esc_html__('Want to use this feature? It is included in Free Plus and Pro plans.','easy-form-builder'),

			/* translators: Button text for Free Plus Guide  (link to https://easyformbuilder.com/document/easy-form-builder-free-plus-activation-guide/) */
			'freePlusActivation' => $state && isset($ac->text->freePlusActivation) ? $ac->text->freePlusActivation : esc_html__('Free Plus Guide','easy-form-builder'),

			/* translators: Headline of the upgrade dialog when the locked item is Pro-only. */
			'proFeatureTitle' => $state && isset($ac->text->proFeatureTitle) ? $ac->text->proFeatureTitle : esc_html__('A Pro version feature','easy-form-builder'),
			/* translators: Headline of the same dialog when Free Plus also unlocks the item. */
			'freePlusUnlocksThis' => $state && isset($ac->text->freePlusUnlocksThis) ? $ac->text->freePlusUnlocksThis : esc_html__('Free Plus unlocks this too','easy-form-builder'),
			/* translators: One-line description of the Free Plus plan, in the two-plan comparison. */
			'planFreePlusDesc' => $state && isset($ac->text->planFreePlusDesc) ? $ac->text->planFreePlusDesc : esc_html__('Unlocked by a free activation, which is enough for this feature.','easy-form-builder'),
			/* translators: One-line description of the Pro plan, in the two-plan comparison. */
			'planProDesc' => $state && isset($ac->text->planProDesc) ? $ac->text->planProDesc : esc_html__('Every feature, with no limits, and support included.','easy-form-builder'),


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



			/* translators: Template for found results text with placeholders - %1$s is result count, %2$s is result/results text */
			'foundResultsText' => $state && isset($ac->text->foundResultsText) ? $ac->text->foundResultsText : esc_html__('Found %1$s %2$s for','easy-form-builder'),

			/* translators: Session Duration = title for nonce/session expiration settings */
			"sessionDuration" => $state && isset($ac->text->sessionDuration) ? $ac->text->sessionDuration : esc_html__('Session Duration','easy-form-builder'),


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

			/* translators: Inherit = keep the font the surrounding theme already uses. First option of the font family selector */
			"respFontDefault" => $state && isset($ac->text->respFontDefault) ? $ac->text->respFontDefault : esc_html__('Default (Inherit)','easy-form-builder'),

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

			/* translators: Subtitle under the Colors & Fonts dialog title */
			"respClrSubtitle" => $state && isset($ac->text->respClrSubtitle) ? $ac->text->respClrSubtitle : esc_html__('Click any part of the preview to edit the colors of that part.','easy-form-builder'),

			/* translators: Hint shown next to the preview tabs in the Colors & Fonts dialog */
			"respClrHint" => $state && isset($ac->text->respClrHint) ? $ac->text->respClrHint : esc_html__('Click the preview to select a part','easy-form-builder'),

			/* translators: Presets = heading of the ready-made palette buttons */
			"respClrPresets" => $state && isset($ac->text->respClrPresets) ? $ac->text->respClrPresets : esc_html__('Presets','easy-form-builder'),

			/* translators: Parts = heading of the list of editable areas of the response box */
			"respClrParts" => $state && isset($ac->text->respClrParts) ? $ac->text->respClrParts : esc_html__('Parts','easy-form-builder'),

			/* translators: Light = name of the light color preset */
			"respPresetLight" => $state && isset($ac->text->respPresetLight) ? $ac->text->respPresetLight : esc_html__('Light','easy-form-builder'),

			/* translators: Dark = name of the dark color preset */
			"respPresetDark" => $state && isset($ac->text->respPresetDark) ? $ac->text->respPresetDark : esc_html__('Dark','easy-form-builder'),

			/* translators: Brand = name of the preset built from a single brand color */
			"respPresetBrand" => $state && isset($ac->text->respPresetBrand) ? $ac->text->respPresetBrand : esc_html__('Brand','easy-form-builder'),

			/* translators: Label of the single color the Brand preset is derived from */
			"respBrandColor" => $state && isset($ac->text->respBrandColor) ? $ac->text->respBrandColor : esc_html__('Your brand color','easy-form-builder'),

			/* translators: Hint under the brand color picker */
			"respBrandColorHint" => $state && isset($ac->text->respBrandColorHint) ? $ac->text->respBrandColorHint : esc_html__('Primary and primary dark are derived from it','easy-form-builder'),

			/* translators: Brand & buttons = name of the brand color group */
			"respZoneBrand" => $state && isset($ac->text->respZoneBrand) ? $ac->text->respZoneBrand : esc_html__('Brand & buttons','easy-form-builder'),

			/* translators: Message card = name of the response card color group */
			"respZoneCard" => $state && isset($ac->text->respZoneCard) ? $ac->text->respZoneCard : esc_html__('Message card','easy-form-builder'),

			/* translators: Response area = name of the surface behind the message cards */
			"respZoneResp" => $state && isset($ac->text->respZoneResp) ? $ac->text->respZoneResp : esc_html__('Response area','easy-form-builder'),

			/* translators: Editor = name of the reply editor color group */
			"respZoneEditor" => $state && isset($ac->text->respZoneEditor) ? $ac->text->respZoneEditor : esc_html__('Editor','easy-form-builder'),

			/* translators: Code finder = name of the tracking-code lookup card */
			"respZoneTrack" => $state && isset($ac->text->respZoneTrack) ? $ac->text->respZoneTrack : esc_html__('Code finder','easy-form-builder'),

			/* translators: Font = name of the typography group */
			"respZoneType" => $state && isset($ac->text->respZoneType) ? $ac->text->respZoneType : esc_html__('Font','easy-form-builder'),

			/* translators: Reset this part = restores the defaults of the selected group only */
			"respClrResetZone" => $state && isset($ac->text->respClrResetZone) ? $ac->text->respClrResetZone : esc_html__('Reset this part','easy-form-builder'),

			/* translators: Reset all = restores every color and font to the plugin defaults */
			"respClrResetAll" => $state && isset($ac->text->respClrResetAll) ? $ac->text->respClrResetAll : esc_html__('Reset all','easy-form-builder'),

			/* translators: Conversation = preview tab showing the message card */
			"respViewConv" => $state && isset($ac->text->respViewConv) ? $ac->text->respViewConv : esc_html__('Conversation','easy-form-builder'),

			/* translators: Reply form = preview tab showing the reply editor */
			"respViewReply" => $state && isset($ac->text->respViewReply) ? $ac->text->respViewReply : esc_html__('Reply form','easy-form-builder'),

			/* translators: Note explaining that these colors never touch the admin panel */
			"respClrScopeNote" => $state && isset($ac->text->respClrScopeNote) ? $ac->text->respClrScopeNote : esc_html__('These settings apply to the public response box only; the admin panel always keeps the default palette.','easy-form-builder'),

			/* translators: Save changes = the save button of the Colors & Fonts dialog */
			"respClrSave" => $state && isset($ac->text->respClrSave) ? $ac->text->respClrSave : esc_html__('Save changes','easy-form-builder'),

			/* translators: Cancel = discards the edits made in the Colors & Fonts dialog */
			"respClrCancel" => $state && isset($ac->text->respClrCancel) ? $ac->text->respClrCancel : esc_html__('Cancel','easy-form-builder'),

			/* translators: Asked before closing the Colors & Fonts dialog while changes are still unsaved */
			"respClrUnsaved" => $state && isset($ac->text->respClrUnsaved) ? $ac->text->respClrUnsaved : esc_html__('Your color changes have not been saved yet. Close and lose them?','easy-form-builder'),

			/* translators: Saved = confirmation shown after the colors are stored */
			"respClrSaved" => $state && isset($ac->text->respClrSaved) ? $ac->text->respClrSaved : esc_html__('Saved','easy-form-builder'),

			/* translators: Shown in the dialog footer while there is nothing left to save */
			"respClrClean" => $state && isset($ac->text->respClrClean) ? $ac->text->respClrClean : esc_html__('Everything is saved','easy-form-builder'),

			/* translators: Shown in the dialog footer when exactly one value was changed */
			"respClrDirtyOne" => $state && isset($ac->text->respClrDirtyOne) ? $ac->text->respClrDirtyOne : esc_html__('1 unsaved change','easy-form-builder'),

			/* translators: %s = number of changed values, always 2 or more */
			"respClrDirtyMany" => $state && isset($ac->text->respClrDirtyMany) ? $ac->text->respClrDirtyMany : esc_html__('%s unsaved changes','easy-form-builder'),

			/* translators: Hint under the Primary color picker */
			"respHintPrimary" => $state && isset($ac->text->respHintPrimary) ? $ac->text->respHintPrimary : esc_html__('Buttons, icons, field labels','easy-form-builder'),

			/* translators: Hint under the Primary Dark color picker */
			"respHintPrimaryDk" => $state && isset($ac->text->respHintPrimaryDk) ? $ac->text->respHintPrimaryDk : esc_html__('End of button gradients','easy-form-builder'),

			/* translators: Hint under the Accent color picker. Names the two places the response box uses it. */
			"respHintAccent" => $state && isset($ac->text->respHintAccent) ? $ac->text->respHintAccent : esc_html__('Rating stars and payment totals','easy-form-builder'),

			/* translators: Hint under the Button Text color picker */
			"respHintBtnText" => $state && isset($ac->text->respHintBtnText) ? $ac->text->respHintBtnText : esc_html__('Label and icon on colored buttons','easy-form-builder'),

			/* translators: Hint under the body Text color picker */
			"respHintText" => $state && isset($ac->text->respHintText) ? $ac->text->respHintText : esc_html__('Field values and message body','easy-form-builder'),

			/* translators: Hint under the Muted Text color picker */
			"respHintMuted" => $state && isset($ac->text->respHintMuted) ? $ac->text->respHintMuted : esc_html__('Dates, hints, secondary titles','easy-form-builder'),

			/* translators: Hint under the Card Background color picker */
			"respHintBgCard" => $state && isset($ac->text->respHintBgCard) ? $ac->text->respHintBgCard : esc_html__('The message card surface','easy-form-builder'),

			/* translators: Hint under the Meta Background color picker */
			"respHintBgMeta" => $state && isset($ac->text->respHintBgMeta) ? $ac->text->respHintBgMeta : esc_html__('Date bar and editor toolbar','easy-form-builder'),

			/* translators: Hint under the Response Area Background color picker */
			"respHintBgResp" => $state && isset($ac->text->respHintBgResp) ? $ac->text->respHintBgResp : esc_html__('Behind all cards','easy-form-builder'),

			/* translators: Hint under the Tracker Background color picker */
			"respHintBgTrack" => $state && isset($ac->text->respHintBgTrack) ? $ac->text->respHintBgTrack : esc_html__('The code lookup card','easy-form-builder'),

			/* translators: Hint under the Editor Background color picker */
			"respHintBgEditor" => $state && isset($ac->text->respHintBgEditor) ? $ac->text->respHintBgEditor : esc_html__('Reply box and code input','easy-form-builder'),

			/* translators: Hint under the Editor Text color picker */
			"respHintEditorText" => $state && isset($ac->text->respHintEditorText) ? $ac->text->respHintEditorText : esc_html__('What the user types','easy-form-builder'),

			/* translators: Hint under the Placeholder color picker */
			"respHintEditorPh" => $state && isset($ac->text->respHintEditorPh) ? $ac->text->respHintEditorPh : esc_html__('Field placeholder text','easy-form-builder'),

			/* translators: Your reply = title of the reply card in the dialog preview */
			"respPvReplyTitle" => $state && isset($ac->text->respPvReplyTitle) ? $ac->text->respPvReplyTitle : esc_html__('Your reply','easy-form-builder'),

			/* translators: Sample subject line shown in the dialog preview */
			"respPvSubject" => $state && isset($ac->text->respPvSubject) ? $ac->text->respPvSubject : esc_html__('Order follow-up','easy-form-builder'),

			/* translators: Sample message body shown in the dialog preview */
			"respPvMsg" => $state && isset($ac->text->respPvMsg) ? $ac->text->respPvMsg : esc_html__('Hi, I placed my order last week and still have no tracking code. Could you check it?','easy-form-builder'),

			/* translators: Attach = the attachment button of the reply editor, shown in the preview */
			"respPvAttach" => $state && isset($ac->text->respPvAttach) ? $ac->text->respPvAttach : esc_html__('Attach','easy-form-builder'),

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

			/* translators: Label and image alt text for the Logo block in the email template builder */
			"ebLogo" => $state && isset($ac->text->ebLogo) ? $ac->text->ebLogo : esc_html__('Logo','easy-form-builder'),
			/* translators: Aria-label suffix ("Block 1", "Block 2"...) on each block wrapper in the email template builder */
			"ebBlock" => $state && isset($ac->text->ebBlock) ? $ac->text->ebBlock : esc_html__('Block','easy-form-builder'),
			/* translators: Aria-label for a block's move/duplicate/delete toolbar in the email template builder */
			"ebBlockActions" => $state && isset($ac->text->ebBlockActions) ? $ac->text->ebBlockActions : esc_html__('Block actions','easy-form-builder'),
			/* translators: Aria-label for the block canvas list in the email template builder */
			"ebEmailBlocks" => $state && isset($ac->text->ebEmailBlocks) ? $ac->text->ebEmailBlocks : esc_html__('Email template blocks','easy-form-builder'),
			/* translators: Property panel label for the font-family selector in the email template builder */
			"ebFontFamily" => $state && isset($ac->text->ebFontFamily) ? $ac->text->ebFontFamily : esc_html__('Font','easy-form-builder'),
			/* translators: Property panel label for the "Default Font" field in the email template builder */
			"ebDefaultFont" => $state && isset($ac->text->ebDefaultFont) ? $ac->text->ebDefaultFont : esc_html__('Default Font','easy-form-builder'),
			/* translators: Placeholder option meaning "use the default font" (shown as a dash between em-dashes) */
			"ebDefaultOption" => $state && isset($ac->text->ebDefaultOption) ? $ac->text->ebDefaultOption : esc_html__('— Default —','easy-form-builder'),
			/* translators: Property panel label for a button block's background color */
			"ebBtnBgColor" => $state && isset($ac->text->ebBtnBgColor) ? $ac->text->ebBtnBgColor : esc_html__('Button Background','easy-form-builder'),
			/* translators: Property panel label for a button block's text color */
			"ebBtnTextColor" => $state && isset($ac->text->ebBtnTextColor) ? $ac->text->ebBtnTextColor : esc_html__('Button Text Color','easy-form-builder'),
			/* translators: Field label for the icon picker on a social-link block */
			"ebIcon" => $state && isset($ac->text->ebIcon) ? $ac->text->ebIcon : esc_html__('Icon','easy-form-builder'),
			/* translators: Property panel label for a social icon's color */
			"ebIconColor" => $state && isset($ac->text->ebIconColor) ? $ac->text->ebIconColor : esc_html__('Icon Color','easy-form-builder'),
			/* translators: Property panel label for a social icon's size (px) range slider */
			"ebIconSize" => $state && isset($ac->text->ebIconSize) ? $ac->text->ebIconSize : esc_html__('Icon Size (px)','easy-form-builder'),
			/* translators: Button title for choosing a custom SVG icon on a social-link block */
			"ebCustomSVG" => $state && isset($ac->text->ebCustomSVG) ? $ac->text->ebCustomSVG : esc_html__('Custom SVG','easy-form-builder'),
			/* translators: Label for the custom SVG markup textarea on a social-link block */
			"ebCustomSVGCode" => $state && isset($ac->text->ebCustomSVGCode) ? $ac->text->ebCustomSVGCode : esc_html__('Custom SVG Code','easy-form-builder'),
			/* translators: Default name given to a newly added social link */
			"ebLink" => $state && isset($ac->text->ebLink) ? $ac->text->ebLink : esc_html__('Link','easy-form-builder'),
			/* translators: Fallback label shown for a social link with no preset name */
			"ebCustom" => $state && isset($ac->text->ebCustom) ? $ac->text->ebCustom : esc_html__('Custom','easy-form-builder'),
			/* translators: Name of the built-in "Website" social icon preset */
			"ebWebsite" => $state && isset($ac->text->ebWebsite) ? $ac->text->ebWebsite : esc_html__('Website','easy-form-builder'),
			/* translators: Tooltip on the padding editor button that links all four sides together */
			"ebLinkAllSides" => $state && isset($ac->text->ebLinkAllSides) ? $ac->text->ebLinkAllSides : esc_html__('Link all sides','easy-form-builder'),
			/* translators: Default heading shown in the email builder when the form has no title */
			"ebFormTitle" => $state && isset($ac->text->ebFormTitle) ? $ac->text->ebFormTitle : esc_html__('Form Title','easy-form-builder'),

			/* translators: Default placeholder text inserted into a newly-added Text block */
			"ebTextDefault" => $state && isset($ac->text->ebTextDefault) ? $ac->text->ebTextDefault : esc_html__('Your text here…','easy-form-builder'),
			/* translators: Default button label inserted into a newly-added Button block */
			"ebVisitWebsite" => $state && isset($ac->text->ebVisitWebsite) ? $ac->text->ebVisitWebsite : esc_html__('Visit Website','easy-form-builder'),
			/* translators: Default placeholder text for the left column of a newly-added two-column block */
			"ebLeftColContent" => $state && isset($ac->text->ebLeftColContent) ? $ac->text->ebLeftColContent : esc_html__('Left column content','easy-form-builder'),
			/* translators: Default placeholder text for the right column of a newly-added two-column block */
			"ebRightColContent" => $state && isset($ac->text->ebRightColContent) ? $ac->text->ebRightColContent : esc_html__('Right column content','easy-form-builder'),
			/* translators: Default footer text — keep the literal token "shortcode_website_name", it is replaced at runtime with the site name */
			"ebFooterDefault" => $state && isset($ac->text->ebFooterDefault) ? $ac->text->ebFooterDefault : esc_html__('Sent by shortcode_website_name','easy-form-builder'),
			/* translators: Default placeholder content inserted into a newly-added custom HTML block */
			"ebCustomHTMLContent" => $state && isset($ac->text->ebCustomHTMLContent) ? $ac->text->ebCustomHTMLContent : esc_html__('Custom HTML content','easy-form-builder'),

			/* translators: Sample name shown in place of the shortcode_message merge tag when previewing an email template */
			"ebSampleName" => $state && isset($ac->text->ebSampleName) ? $ac->text->ebSampleName : esc_html__('John Doe','easy-form-builder'),
			/* translators: Sample message text shown in place of the shortcode_message merge tag when previewing an email template */
			"ebSampleSubmission" => $state && isset($ac->text->ebSampleSubmission) ? $ac->text->ebSampleSubmission : esc_html__('This is a sample form submission.','easy-form-builder'),
			/* translators: Sample site name shown in place of the shortcode_website_name merge tag when previewing an email template */
			"ebSampleWebsite" => $state && isset($ac->text->ebSampleWebsite) ? $ac->text->ebSampleWebsite : esc_html__('My Website','easy-form-builder'),

			/* translators: Tooltip on the Bold button in the response-viewer rich-text editor toolbar */
			"rtBold" => $state && isset($ac->text->rtBold) ? $ac->text->rtBold : esc_html__('Bold','easy-form-builder'),
			/* translators: Tooltip on the Italic button in the response-viewer rich-text editor toolbar */
			"rtItalic" => $state && isset($ac->text->rtItalic) ? $ac->text->rtItalic : esc_html__('Italic','easy-form-builder'),
			/* translators: Tooltip on the Underline button in the response-viewer rich-text editor toolbar */
			"rtUnderline" => $state && isset($ac->text->rtUnderline) ? $ac->text->rtUnderline : esc_html__('Underline','easy-form-builder'),
			/* translators: Tooltip on the "Clear formatting" button in the response-viewer rich-text editor toolbar */
			"clearFormatting" => $state && isset($ac->text->clearFormatting) ? $ac->text->clearFormatting : esc_html__('Clear formatting','easy-form-builder'),
			/* translators: Aria-label for the collapsible navbar toggle button in the form-builder element panel */
			"toggleNavigation" => $state && isset($ac->text->toggleNavigation) ? $ac->text->toggleNavigation : esc_html__('Toggle navigation','easy-form-builder'),
			/* translators: Aria-label for the desktop/mobile preview view-toggle button group in the form builder */
			"viewToggle" => $state && isset($ac->text->viewToggle) ? $ac->text->viewToggle : esc_html__('View toggle','easy-form-builder'),
			/* translators: Title attribute on the "Locate Me" button of the map field */
			"locateMe" => $state && isset($ac->text->locateMe) ? $ac->text->locateMe : esc_html__('Locate Me','easy-form-builder'),
			/* translators: Error message prefix shown when reverse-geocoding the map field's location fails */
			"errorFetchingAddress" => $state && isset($ac->text->errorFetchingAddress) ? $ac->text->errorFetchingAddress : esc_html__('Error fetching address','easy-form-builder'),
			/* translators: Alert shown when the browser does not support the Geolocation API, on the map field's "Locate Me" button */
			"geolocationNotSupported" => $state && isset($ac->text->geolocationNotSupported) ? $ac->text->geolocationNotSupported : esc_html__('Geolocation is not supported by this browser.','easy-form-builder'),
			/* translators: Placeholder on the phone verification code input */
			"verify" => $state && isset($ac->text->verify) ? $ac->text->verify : esc_html__('verify','easy-form-builder'),
			/* translators: Title shown when a required-field validation step fails */
			"failed" => $state && isset($ac->text->failed) ? $ac->text->failed : esc_html__('Failed','easy-form-builder'),
			/* translators: Alert shown when a payment-type form has no payment method configured */
			"paymentMethodMissing" => $state && isset($ac->text->paymentMethodMissing) ? $ac->text->paymentMethodMissing : esc_html__('The form cannot be submitted because it requires a payment method, which is currently missing. If you are the Admin, please add a payment method to the form or change the form type to "Form" or "Survey".','easy-form-builder'),

			'payments' => $state && isset($ac->text->payments) ? $ac->text->payments : esc_html__('Payments','easy-form-builder'),
			/* translators: Cache warning messages shown to admin when cache plugins detected */
			"cacheWarnTitle" => $state && isset($ac->text->cacheWarnTitle) ? $ac->text->cacheWarnTitle : esc_html__('Cache Plugin Detected','easy-form-builder'),
			"cacheWarnMsg" => $state && isset($ac->text->cacheWarnMsg) ? $ac->text->cacheWarnMsg : esc_html__('The following cache plugins may interfere with form functionality. If you experience issues, please review the documentation.','easy-form-builder'),
			"cacheWarnPlugin" => $state && isset($ac->text->cacheWarnPlugin) ? $ac->text->cacheWarnPlugin : esc_html__('Plugin','easy-form-builder'),
			"cacheWarnVersion" => $state && isset($ac->text->cacheWarnVersion) ? $ac->text->cacheWarnVersion : esc_html__('Version','easy-form-builder'),
			"cacheWarnDoc" => $state && isset($ac->text->cacheWarnDoc) ? $ac->text->cacheWarnDoc : esc_html__('Read more about cache compatibility','easy-form-builder'),
			"dismiss" => $state && isset($ac->text->dismiss) ? $ac->text->dismiss : esc_html__('Dismiss','easy-form-builder'),
			"dismissAll" => $state && isset($ac->text->dismissAll) ? $ac->text->dismissAll : esc_html__('Dismiss All','easy-form-builder'),
			/* translators: %s: item label */
			"dismissItem" => $state && isset($ac->text->dismissItem) ? $ac->text->dismissItem : esc_html__('Dismiss %s','easy-form-builder'),
			/* translators: Security plugin warning messages shown to admin on public forms */
			"securityWarnTitle" => $state && isset($ac->text->securityWarnTitle) ? $ac->text->securityWarnTitle : esc_html__('Security Plugin Detected','easy-form-builder'),
			"securityWarnMsg" => $state && isset($ac->text->securityWarnMsg) ? $ac->text->securityWarnMsg : esc_html__('The following security plugin may block form submissions with 403 errors. It may block the REST API, remove the X-WP-Nonce header, or apply firewall rules to form requests.','easy-form-builder'),
			"securityWarnPlugin" => $state && isset($ac->text->securityWarnPlugin) ? $ac->text->securityWarnPlugin : esc_html__('Plugin','easy-form-builder'),
			"securityWarnVersion" => $state && isset($ac->text->securityWarnVersion) ? $ac->text->securityWarnVersion : esc_html__('Version','easy-form-builder'),
			"securityWarnDoc" => $state && isset($ac->text->securityWarnDoc) ? $ac->text->securityWarnDoc : esc_html__('Read more about security plugin compatibility','easy-form-builder'),

			"TAdnAtF" => $state  &&  isset($ac->text->TAdnAtF) ? $ac->text->TAdnAtF : esc_html__('Auto-Populate Add-on','easy-form-builder'),
			"DAdnAtF" => $state  &&  isset($ac->text->DAdnAtF) ? $ac->text->DAdnAtF : esc_html__('The Auto-Populate add-on enables you to automatically populate form fields from datasets, previously submitted forms, or external APIs.','easy-form-builder'),
			"TAdnHSH" => $state  &&  isset($ac->text->TAdnHSH) ? $ac->text->TAdnHSH : esc_html__('Form Security & Spam Protection','easy-form-builder'),
			"DAdnHSH" => $state  &&  isset($ac->text->DAdnHSH) ? $ac->text->DAdnHSH : esc_html__('Behavior-based anti-spam and API abuse protection with rate limits and a stop-loss for paid notifications such as SMS, Telegram, email and webhooks.','easy-form-builder'),
			/* translators: Google Sheet add-on card title on the Add-ons page */
			"TAdnGoS" => $state  &&  isset($ac->text->TAdnGoS) ? $ac->text->TAdnGoS : esc_html__('Google Sheet Add-on','easy-form-builder'),
			/* translators: Google Sheet add-on card description on the Add-ons page */
			"DAdnGoS" => $state  &&  isset($ac->text->DAdnGoS) ? $ac->text->DAdnGoS : esc_html__('Automatically send form submissions to your Google Sheets in real time. Connect a service account, map fields to columns, and keep a live, styled spreadsheet of every response — no webhook or browser login required.','easy-form-builder'),
			/* translators: Validation message shown when a required form field is left empty */
			"fillrequiredfields" => $state && isset($ac->text->fillrequiredfields) ? $ac->text->fillrequiredfields : esc_html__('Please fill in all required fields', 'easy-form-builder'),

			/* translators: Email server test section title */
			"emailServerStatus" => $state && isset($ac->text->emailServerStatus) ? $ac->text->emailServerStatus : esc_html__('Email Server Status','easy-form-builder'),
			/* translators: Title shown when WordPress cannot send emails */
			"emailDeliveryNotWorking" => $state && isset($ac->text->emailDeliveryNotWorking) ? $ac->text->emailDeliveryNotWorking : esc_html__('The test email never arrived','easy-form-builder'),
			/* translators: Description shown when WordPress cannot send emails */
			"emailDeliveryNotWorkingDesc" => $state && isset($ac->text->emailDeliveryNotWorkingDesc) ? $ac->text->emailDeliveryNotWorkingDesc : esc_html__('Your WordPress site cannot send emails reliably. This is a very common hosting issue — the default PHP mail function is often blocked or ends up in spam. Installing an SMTP plugin routes your emails through a verified mail service and fixes this in minutes.','easy-form-builder'),
			/* translators: Title shown when WordPress sent the message but it never reached the delivery service */
			"emailSentNotArrivedTitle" => $state && isset($ac->text->emailSentNotArrivedTitle) ? $ac->text->emailSentNotArrivedTitle : esc_html__('WordPress sent the email - it just never arrived','easy-form-builder'),
			/* translators: Description shown when WordPress sent the message but it never reached the delivery service */
			"emailSentNotArrivedDesc" => $state && isset($ac->text->emailSentNotArrivedDesc) ? $ac->text->emailSentNotArrivedDesc : esc_html__('Your site handed the message to your mail server successfully, so WordPress and this plugin did their part. It was lost, delayed or rejected afterwards - most often the receiving mailbox filed it as spam, or your host never delivered it from the outbound queue. Sending through an SMTP service, with SPF and DKIM records for your domain, is what fixes this.','easy-form-builder'),
			/* translators: Title shown when wp_mail() itself failed and nothing left the site */
			"emailWpMailFailedTitle" => $state && isset($ac->text->emailWpMailFailedTitle) ? $ac->text->emailWpMailFailedTitle : esc_html__('WordPress could not send the email','easy-form-builder'),
			/* translators: Description shown when wp_mail() itself failed and nothing left the site */
			"emailWpMailFailedDesc" => $state && isset($ac->text->emailWpMailFailedDesc) ? $ac->text->emailWpMailFailedDesc : esc_html__('The message never left your website: WordPress returned an error while sending it. Install and configure an SMTP plugin, or ask your host whether PHP mail is disabled.','easy-form-builder'),
			/* translators: Title shown when the email arrives but its score is too low for the inbox */
			"emailSpamRiskTitle" => $state && isset($ac->text->emailSpamRiskTitle) ? $ac->text->emailSpamRiskTitle : esc_html__('Your emails arrive, but will most likely land in spam','easy-form-builder'),
			/* translators: Guidance shown when the email arrives but its score is too low for the inbox */
			"emailSpamRiskGuidance" => $state && isset($ac->text->emailSpamRiskGuidance) ? $ac->text->emailSpamRiskGuidance : esc_html__('Delivery itself works - the test message reached us. What is missing is trust: sending through an SMTP service and adding SPF and DKIM records for your domain is what moves your emails from the spam folder to the inbox.','easy-form-builder'),
			/* translators: Result sentence for a delivered email with a low score. %1$s: measured score, %2$s: healthy score threshold. */
			"emailSpamRiskDesc" => $state && isset($ac->text->emailSpamRiskDesc) ? $ac->text->emailSpamRiskDesc : esc_html__('The test email was delivered with a deliverability score of %1$s out of 100. Under %2$s, most mailboxes file messages in the spam folder, so the people filling in your forms may never see them.','easy-form-builder'),
			/* translators: Button label linking to the SMTP setup guide */
			"smtpSetupGuideBtn" => $state && isset($ac->text->smtpSetupGuideBtn) ? $ac->text->smtpSetupGuideBtn : esc_html__('Step-by-step SMTP setup guide','easy-form-builder'),
			/* translators: Message shown when email test succeeds. %s is replaced with the admin email address (e.g. "...sent to admin@example.com.") */
			"emailServerWorkingReport" => $state && isset($ac->text->emailServerWorkingReport) ? $ac->text->emailServerWorkingReport : esc_html__('Your email server is working. A detailed HTML report has been sent to %s.','easy-form-builder'),
			/* translators: Fallback text when admin email address is not available */
			"yourAdminEmail" => $state && isset($ac->text->yourAdminEmail) ? $ac->text->yourAdminEmail : esc_html__('your admin email address','easy-form-builder'),
			/* translators: Title of the spam score notification box shown when email test succeeds */
			"emailSpamReportTitle" => $state && isset($ac->text->emailSpamReportTitle) ? $ac->text->emailSpamReportTitle : esc_html__('Spam Score Report on the Way!','easy-form-builder'),
			/* translators: Description in spam score notification box. %s is replaced with the admin email address */
			"emailSpamReportDesc" => $state && isset($ac->text->emailSpamReportDesc) ? $ac->text->emailSpamReportDesc : esc_html__('A complete email health report — including your spam score, deliverability details, and recommendations — will be sent to %s within the next few minutes.','easy-form-builder'),
			/* translators: Warning title shown after saving a form whose email notifications may not reach the admin */
			"emailNotificationRiskTitle" => $state && isset($ac->text->emailNotificationRiskTitle) ? $ac->text->emailNotificationRiskTitle : esc_html__('Email notifications may not be delivered','easy-form-builder'),
			/* translators: %s = numeric spam score out of 100. Shown when the form has email notifications on but delivery is unverified */
			"emailNotificationRiskDesc" => $state && isset($ac->text->emailNotificationRiskDesc) ? $ac->text->emailNotificationRiskDesc : esc_html__('This form\'s email notification feature is enabled, but email delivery has not been verified and the latest spam score is %s/100. The form was saved, but admin notification emails may not reach you until SMTP/email delivery is fixed.','easy-form-builder'),
			/* translators: Title shown in the form builder when the "This site can send emails" switch is off */
			"emailSendingOffTitle" => $state && isset($ac->text->emailSendingOffTitle) ? $ac->text->emailSendingOffTitle : esc_html__('Notification emails are turned off','easy-form-builder'),
			/* translators: Explains that no email at all is sent while the switch is off */
			"emailSendingOffDesc" => $state && isset($ac->text->emailSendingOffDesc) ? $ac->text->emailSendingOffDesc : esc_html__('Easy Form Builder will not send any email — neither to you nor to the person who submits this form — until "This site can send emails" is enabled in Email Settings.','easy-form-builder'),
			/* translators: Button label that opens Email Settings on the switch */
			"emailSendingOffCta" => $state && isset($ac->text->emailSendingOffCta) ? $ac->text->emailSendingOffCta : esc_html__('Enable email sending','easy-form-builder'),
			/* translators: Step-by-step hint shown next to the switch in Email Settings */
			"emailSendingOffHowTo" => $state && isset($ac->text->emailSendingOffHowTo) ? $ac->text->emailSendingOffHowTo : esc_html__('Click "Check Email Server" to test delivery, turn this switch on, then press Save.','easy-form-builder'),
			/* translators: Title of the delayed delivery warning box */
			"deliveryDelayedTitle" => $state && isset($ac->text->deliveryDelayedTitle) ? $ac->text->deliveryDelayedTitle : esc_html__('Delivery is taking longer than expected','easy-form-builder'),
			/* translators: Description shown when email delivery is delayed */
			"deliveryDelayedDesc" => $state && isset($ac->text->deliveryDelayedDesc) ? $ac->text->deliveryDelayedDesc : esc_html__('WordPress sent the test email, but our server has not received it yet. This may be a temporary delay. Check the diagnostics below to troubleshoot.','easy-form-builder'),

			/* translators: Email test step 1 — title */
			"stepPrepareTest" => $state && isset($ac->text->stepPrepareTest) ? $ac->text->stepPrepareTest : esc_html__('Prepare Test','easy-form-builder'),
			/* translators: Email test step 1 — description */
			"stepPrepareTestDesc" => $state && isset($ac->text->stepPrepareTestDesc) ? $ac->text->stepPrepareTestDesc : esc_html__('Connecting to WhiteStudio to generate a unique test email address.','easy-form-builder'),
			/* translators: Email test step 2 — title */
			"stepSendEmail" => $state && isset($ac->text->stepSendEmail) ? $ac->text->stepSendEmail : esc_html__('WordPress Sends the Email','easy-form-builder'),
			/* translators: Email test step 2 — description */
			"stepSendEmailDesc" => $state && isset($ac->text->stepSendEmailDesc) ? $ac->text->stepSendEmailDesc : esc_html__('WordPress hands a real message to your mail server. A tick here means WordPress sent it, not yet that it arrived.','easy-form-builder'),
			/* translators: Email test step 3 — title */
			"stepWaitDelivery" => $state && isset($ac->text->stepWaitDelivery) ? $ac->text->stepWaitDelivery : esc_html__('Waiting for Delivery','easy-form-builder'),
			/* translators: Email test step 3 — description */
			"stepWaitDeliveryDesc" => $state && isset($ac->text->stepWaitDeliveryDesc) ? $ac->text->stepWaitDeliveryDesc : esc_html__('Checking whether the test email arrived at our server (usually takes a few seconds).','easy-form-builder'),
			/* translators: Email test step 4 — title */
			"stepQuickResult" => $state && isset($ac->text->stepQuickResult) ? $ac->text->stepQuickResult : esc_html__('Quick Result','easy-form-builder'),
			/* translators: Email test step 4 — description */
			"stepQuickResultDesc" => $state && isset($ac->text->stepQuickResultDesc) ? $ac->text->stepQuickResultDesc : esc_html__('Showing the first delivery result — you will see right away if email is working.','easy-form-builder'),
			/* translators: Email test step 5 — title */
			"stepFullReport" => $state && isset($ac->text->stepFullReport) ? $ac->text->stepFullReport : esc_html__('Full Report','easy-form-builder'),
			/* translators: Email test step 5 — description */
			"stepFullReportDesc" => $state && isset($ac->text->stepFullReportDesc) ? $ac->text->stepFullReportDesc : esc_html__('A detailed HTML report with full diagnostics is being prepared and emailed to you.','easy-form-builder'),

			/*
			 * The email server test panel. Both places the test runs - the
			 * settings modal and the setup wizard - read these, so the two can
			 * never drift into two different vocabularies.
			 */
			/* translators: Status chip shown while the email test is still running */
			"emailTestPhaseRunning" => $state && isset($ac->text->emailTestPhaseRunning) ? $ac->text->emailTestPhaseRunning : esc_html__('Running','easy-form-builder'),
			/* translators: Status chip shown when the email test finished successfully */
			"emailTestPhaseDone" => $state && isset($ac->text->emailTestPhaseDone) ? $ac->text->emailTestPhaseDone : esc_html__('Finished','easy-form-builder'),
			/* translators: Status chip shown when the email test finished with a warning */
			"emailTestPhaseWarn" => $state && isset($ac->text->emailTestPhaseWarn) ? $ac->text->emailTestPhaseWarn : esc_html__('Needs attention','easy-form-builder'),
			/* translators: Status chip shown when the email test failed */
			"emailTestPhaseFailed" => $state && isset($ac->text->emailTestPhaseFailed) ? $ac->text->emailTestPhaseFailed : esc_html__('Failed','easy-form-builder'),
			/* translators: %s = how many of the five test steps have finished */
			"emailTestStepsDone" => $state && isset($ac->text->emailTestStepsDone) ? $ac->text->emailTestStepsDone : esc_html__('%s of 5 steps done','easy-form-builder'),
			/* translators: %s = which of the five test steps is running now */
			"emailTestStepsRunning" => $state && isset($ac->text->emailTestStepsRunning) ? $ac->text->emailTestStepsRunning : esc_html__('Step %s of 5','easy-form-builder'),
			/* translators: Shown under the deliverability score, as in "92 / 100" */
			"emailTestScoreOutOf" => $state && isset($ac->text->emailTestScoreOutOf) ? $ac->text->emailTestScoreOutOf : esc_html__('/ 100','easy-form-builder'),
			"emailTestStartingTitle" => $state && isset($ac->text->emailTestStartingTitle) ? $ac->text->emailTestStartingTitle : esc_html__('Test started','easy-form-builder'),
			"emailTestStartingSub" => $state && isset($ac->text->emailTestStartingSub) ? $ac->text->emailTestStartingSub : esc_html__('A unique address is being generated for this test.','easy-form-builder'),
			"emailTestPendingTitle" => $state && isset($ac->text->emailTestPendingTitle) ? $ac->text->emailTestPendingTitle : esc_html__('Waiting for the email to arrive','easy-form-builder'),
			"emailTestPendingSub" => $state && isset($ac->text->emailTestPendingSub) ? $ac->text->emailTestPendingSub : esc_html__('We check our server every few seconds. Please keep this page open.','easy-form-builder'),
			"emailTestOkTitle" => $state && isset($ac->text->emailTestOkTitle) ? $ac->text->emailTestOkTitle : esc_html__('Your email server is healthy','easy-form-builder'),
			"emailTestOkSub" => $state && isset($ac->text->emailTestOkSub) ? $ac->text->emailTestOkSub : esc_html__('The test email arrived, and the subject and unique-code checks both passed.','easy-form-builder'),
			"emailTestLowTitle" => $state && isset($ac->text->emailTestLowTitle) ? $ac->text->emailTestLowTitle : esc_html__('Delivered, but likely to be filtered as spam','easy-form-builder'),
			"emailTestSpamTitle" => $state && isset($ac->text->emailTestSpamTitle) ? $ac->text->emailTestSpamTitle : esc_html__('Delivered, but deliverability is weak','easy-form-builder'),
			"emailTestExpiredTitle" => $state && isset($ac->text->emailTestExpiredTitle) ? $ac->text->emailTestExpiredTitle : esc_html__('No email arrived','easy-form-builder'),
			"emailTestExpiredSub" => $state && isset($ac->text->emailTestExpiredSub) ? $ac->text->emailTestExpiredSub : esc_html__('Nothing was received during the test window, so your server most likely cannot send email.','easy-form-builder'),
			"emailTestTimeoutTitle" => $state && isset($ac->text->emailTestTimeoutTitle) ? $ac->text->emailTestTimeoutTitle : esc_html__('The test timed out','easy-form-builder'),
			"emailTestTimeoutSub" => $state && isset($ac->text->emailTestTimeoutSub) ? $ac->text->emailTestTimeoutSub : esc_html__('The test reached its time limit. Your server may be slow, or may block outbound email.','easy-form-builder'),
			"emailTestStartErrorTitle" => $state && isset($ac->text->emailTestStartErrorTitle) ? $ac->text->emailTestStartErrorTitle : esc_html__('The test email could not be sent','easy-form-builder'),
			"emailTestStartErrorSub" => $state && isset($ac->text->emailTestStartErrorSub) ? $ac->text->emailTestStartErrorSub : esc_html__('WordPress returned an error while sending. See the message below.','easy-form-builder'),
			"emailTestNetErrorTitle" => $state && isset($ac->text->emailTestNetErrorTitle) ? $ac->text->emailTestNetErrorTitle : esc_html__('The connection was lost','easy-form-builder'),
			"emailTestNetErrorSub" => $state && isset($ac->text->emailTestNetErrorSub) ? $ac->text->emailTestNetErrorSub : esc_html__('No answer came back from the server. Refresh the page and try again.','easy-form-builder'),
			"emailTestUpgradeTitle" => $state && isset($ac->text->emailTestUpgradeTitle) ? $ac->text->emailTestUpgradeTitle : esc_html__('This test needs a higher plan','easy-form-builder'),
			"emailTestUpgradeSub" => $state && isset($ac->text->emailTestUpgradeSub) ? $ac->text->emailTestUpgradeSub : esc_html__('Upgrade your plan to run the full delivery test.','easy-form-builder'),
			/* translators: Sentence lead-in; the email address is appended after it */
			"emailServerWorkingReportLead" => $state && isset($ac->text->emailServerWorkingReportLead) ? $ac->text->emailServerWorkingReportLead : esc_html__('Your email server is working. A detailed HTML report has been sent to','easy-form-builder'),
			/* translators: Sentence lead-in; the email address is appended after it */
			"emailSpamReportLead" => $state && isset($ac->text->emailSpamReportLead) ? $ac->text->emailSpamReportLead : esc_html__('A complete email health report — including your spam score, deliverability details, and recommendations — will be sent within the next few minutes to','easy-form-builder'),
			"upgradeRequired" => $state && isset($ac->text->upgradeRequired) ? $ac->text->upgradeRequired : esc_html__('Upgrade required','easy-form-builder'),

			/* The auto-save restore prompt. "Yes"/"No" said nothing about which
			   button kept the draft, so both choices are named. */
			"restoreAutoSaveTitle" => $state && isset($ac->text->restoreAutoSaveTitle) ? $ac->text->restoreAutoSaveTitle : esc_html__('An auto-saved version exists','easy-form-builder'),
			/* translators: Button that restores the auto-saved draft */
			"restoreIt" => $state && isset($ac->text->restoreIt) ? $ac->text->restoreIt : esc_html__('Restore it','easy-form-builder'),
			/* translators: Button that discards the auto-saved draft and starts over */
			"startFresh" => $state && isset($ac->text->startFresh) ? $ac->text->startFresh : esc_html__('Start fresh','easy-form-builder'),
			/* translators: Label before the date and time the draft was auto-saved */
			"lastSaved" => $state && isset($ac->text->lastSaved) ? $ac->text->lastSaved : esc_html__('Last saved','easy-form-builder'),

			/* translators: Delivery details box title */
			"deliveryDetailsTitle" => $state && isset($ac->text->deliveryDetailsTitle) ? $ac->text->deliveryDetailsTitle : esc_html__('Delivery Details','easy-form-builder'),
			/* translators: Label for the email address the test was sent to */
			"testSentTo" => $state && isset($ac->text->testSentTo) ? $ac->text->testSentTo : esc_html__('Test sent to','easy-form-builder'),
			/* translators: Label for the email subject used in the delivery test */
			"emailSubjectLabel" => $state && isset($ac->text->emailSubjectLabel) ? $ac->text->emailSubjectLabel : esc_html__('Email subject','easy-form-builder'),
			/* translators: Label for the sender email address shown in delivery details */
			"senderAddress" => $state && isset($ac->text->senderAddress) ? $ac->text->senderAddress : esc_html__('Sender address','easy-form-builder'),
			/* translators: Label showing whether the test email was received */
			"emailReceived" => $state && isset($ac->text->emailReceived) ? $ac->text->emailReceived : esc_html__('Email received','easy-form-builder'),
			/* translators: Label showing whether the email subject line matched the expected value */
			"subjectMatched" => $state && isset($ac->text->subjectMatched) ? $ac->text->subjectMatched : esc_html__('Subject matched','easy-form-builder'),
			/* translators: Label showing whether the unique verification hash in the email matched */
			"uniqueCodeVerified" => $state && isset($ac->text->uniqueCodeVerified) ? $ac->text->uniqueCodeVerified : esc_html__('Unique code verified','easy-form-builder'),
			/* translators: Label showing how many seconds the test waited for the email */
			"timeWaited" => $state && isset($ac->text->timeWaited) ? $ac->text->timeWaited : esc_html__('Time waited','easy-form-builder'),
			/* translators: Label showing the maximum wait time before the test expires */
			"maxWaitTime" => $state && isset($ac->text->maxWaitTime) ? $ac->text->maxWaitTime : esc_html__('Max wait time','easy-form-builder'),
			/* translators: Label showing the reason email delivery failed */
			"failureReason" => $state && isset($ac->text->failureReason) ? $ac->text->failureReason : esc_html__('Failure reason','easy-form-builder'),

			/* translators: Diagnostics section title — & is an ampersand */
			"diagnosisTitle" => $state && isset($ac->text->diagnosisTitle) ? $ac->text->diagnosisTitle : esc_html__('Diagnosis & Troubleshooting','easy-form-builder'),
			/* translators: Heading for the list of possible reasons email failed */
			"possibleCauses" => $state && isset($ac->text->possibleCauses) ? $ac->text->possibleCauses : esc_html__('Possible causes','easy-form-builder'),
			/* translators: Heading for the list of recommended next troubleshooting steps */
			"whatToCheckNext" => $state && isset($ac->text->whatToCheckNext) ? $ac->text->whatToCheckNext : esc_html__('What to check next','easy-form-builder'),

			/* translators: Recommendations section title shown in email test result */
			"recommendations" => $state && isset($ac->text->recommendations) ? $ac->text->recommendations : esc_html__('Recommendations','easy-form-builder'),
			/* translators: Score label in email test result badge. %s is replaced with the numeric score value (e.g. "Score: 8") */
			"score" => $state && isset($ac->text->score) ? $ac->text->score : esc_html__('Score: %s','easy-form-builder'),
			/* translators: Upgrade button label shown when a higher plan is required */
			"upgrade" => $state && isset($ac->text->upgrade) ? $ac->text->upgrade : esc_html__('Upgrade','easy-form-builder'),

			/* translators: Status message shown when the email test times out */
			"emailTestTimedOut" => $state && isset($ac->text->emailTestTimedOut) ? $ac->text->emailTestTimedOut : esc_html__('The test timed out. Please try again — your server may be slow or blocking outgoing mail.','easy-form-builder'),
			/* translators: Status message shown while waiting for the test email to arrive */
			"waitingForEmail" => $state && isset($ac->text->waitingForEmail) ? $ac->text->waitingForEmail : esc_html__('Waiting for the test email to arrive…','easy-form-builder'),
			/* translators: Status message shown when the test email is delayed */
			"emailOnItsWay" => $state && isset($ac->text->emailOnItsWay) ? $ac->text->emailOnItsWay : esc_html__('Email is on its way — still waiting for delivery confirmation.','easy-form-builder'),
			/* translators: Status message shown when no test email arrived before expiry */
			"emailNeverArrived" => $state && isset($ac->text->emailNeverArrived) ? $ac->text->emailNeverArrived : esc_html__('No email arrived during the test window.','easy-form-builder'),
			/* translators: Status message shown while polling for test results */
			"stillChecking" => $state && isset($ac->text->stillChecking) ? $ac->text->stillChecking : esc_html__('Still checking — please wait a moment…','easy-form-builder'),
			/* translators: Status message shown when the email server test begins */
			"startingEmailTest" => $state && isset($ac->text->startingEmailTest) ? $ac->text->startingEmailTest : esc_html__('Starting email delivery test…','easy-form-builder'),
			/* translators: Status message shown after the test email has been sent */
			"testEmailSent" => $state && isset($ac->text->testEmailSent) ? $ac->text->testEmailSent : esc_html__('WordPress accepted and sent the message. Waiting for it to arrive…','easy-form-builder'),
			/* translators: Connection error message with HTTP status code. %s is replaced with the error code (e.g. "Code: 500") */
			"connectionErrorCode" => $state && isset($ac->text->connectionErrorCode) ? $ac->text->connectionErrorCode : esc_html__('Connection error. Please refresh the page and try again. (Code: %s)','easy-form-builder'),

			/* translators: Form template field labels */
			"fullName" => $state && isset($ac->text->fullName) ? $ac->text->fullName : esc_html__('Full Name','easy-form-builder'),
			"emailAddress" => $state && isset($ac->text->emailAddress) ? $ac->text->emailAddress : esc_html__('Email Address','easy-form-builder'),
			"areaCode" => $state && isset($ac->text->areaCode) ? $ac->text->areaCode : esc_html__('Area Code','easy-form-builder'),
			"city" => $state && isset($ac->text->city) ? $ac->text->city : esc_html__('City','easy-form-builder'),
			"comment" => $state && isset($ac->text->comment) ? $ac->text->comment : esc_html__('Comment','easy-form-builder'),
			"personalInformation" => $state && isset($ac->text->personalInformation) ? $ac->text->personalInformation : esc_html__('Personal Information','easy-form-builder'),
			"streetAddress" => $state && isset($ac->text->streetAddress) ? $ac->text->streetAddress : esc_html__('Street Address','easy-form-builder'),
			"streetAddressLine2" => $state && isset($ac->text->streetAddressLine2) ? $ac->text->streetAddressLine2 : esc_html__('Street Address Line 2','easy-form-builder'),
			"stateProvinceLabel" => $state && isset($ac->text->stateProvinceLabel) ? $ac->text->stateProvinceLabel : esc_html__('State / Province','easy-form-builder'),
			"selectAVehicle" => $state && isset($ac->text->selectAVehicle) ? $ac->text->selectAVehicle : esc_html__('Select a Vehicle','easy-form-builder'),
			"pickupDate" => $state && isset($ac->text->pickupDate) ? $ac->text->pickupDate : esc_html__('Pickup Date','easy-form-builder'),
			"pickupAddress" => $state && isset($ac->text->pickupAddress) ? $ac->text->pickupAddress : esc_html__('Pickup Address','easy-form-builder'),
			"pickupCity" => $state && isset($ac->text->pickupCity) ? $ac->text->pickupCity : esc_html__('Pickup City','easy-form-builder'),
			"pickupStateProvince" => $state && isset($ac->text->pickupStateProvince) ? $ac->text->pickupStateProvince : esc_html__('Pickup State / Province','easy-form-builder'),
			"dropoffDate" => $state && isset($ac->text->dropoffDate) ? $ac->text->dropoffDate : esc_html__('Dropoff Date','easy-form-builder'),
			"dropoffAddress" => $state && isset($ac->text->dropoffAddress) ? $ac->text->dropoffAddress : esc_html__('Dropoff Address','easy-form-builder'),
			"dropoffCity" => $state && isset($ac->text->dropoffCity) ? $ac->text->dropoffCity : esc_html__('Dropoff City','easy-form-builder'),
			"dropoffStateProvince" => $state && isset($ac->text->dropoffStateProvince) ? $ac->text->dropoffStateProvince : esc_html__('Dropoff State / Province','easy-form-builder'),
			"selectADate" => $state && isset($ac->text->selectADate) ? $ac->text->selectADate : esc_html__('Select a Date','easy-form-builder'),
			"exEmailExample" => $state && isset($ac->text->exEmailExample) ? $ac->text->exEmailExample : esc_html__('ex: example@mail.com','easy-form-builder'),
			"howCanWeHelpYou" => $state && isset($ac->text->howCanWeHelpYou) ? $ac->text->howCanWeHelpYou : esc_html__('How can we help you?','easy-form-builder'),

			/* translators: Form template option values */
			"optionNo" => $state && isset($ac->text->optionNo) ? $ac->text->optionNo : esc_html__('No','easy-form-builder'),
			"optionSomewhat" => $state && isset($ac->text->optionSomewhat) ? $ac->text->optionSomewhat : esc_html__('Somewhat','easy-form-builder'),
			"optionNotObserved" => $state && isset($ac->text->optionNotObserved) ? $ac->text->optionNotObserved : esc_html__('Not Observed','easy-form-builder'),
			"optionOther" => $state && isset($ac->text->optionOther) ? $ac->text->optionOther : esc_html__('Other','easy-form-builder'),
			"optionNone" => $state && isset($ac->text->optionNone) ? $ac->text->optionNone : esc_html__('None','easy-form-builder'),
			"optionMale" => $state && isset($ac->text->optionMale) ? $ac->text->optionMale : esc_html__('Male','easy-form-builder'),
			"optionFemale" => $state && isset($ac->text->optionFemale) ? $ac->text->optionFemale : esc_html__('Female','easy-form-builder'),
			"optionEnglish" => $state && isset($ac->text->optionEnglish) ? $ac->text->optionEnglish : esc_html__('English','easy-form-builder'),
			"optionFrench" => $state && isset($ac->text->optionFrench) ? $ac->text->optionFrench : esc_html__('French','easy-form-builder'),
			"optionGerman" => $state && isset($ac->text->optionGerman) ? $ac->text->optionGerman : esc_html__('German','easy-form-builder'),
			"optionRussian" => $state && isset($ac->text->optionRussian) ? $ac->text->optionRussian : esc_html__('Russian','easy-form-builder'),
			"optionPortuguese" => $state && isset($ac->text->optionPortuguese) ? $ac->text->optionPortuguese : esc_html__('Portuguese','easy-form-builder'),
			"optionHindi" => $state && isset($ac->text->optionHindi) ? $ac->text->optionHindi : esc_html__('Hindi','easy-form-builder'),
			"optionPasta" => $state && isset($ac->text->optionPasta) ? $ac->text->optionPasta : esc_html__('Pasta','easy-form-builder'),
			"optionPizza" => $state && isset($ac->text->optionPizza) ? $ac->text->optionPizza : esc_html__('Pizza','easy-form-builder'),
			"optionFishSeafood" => $state && isset($ac->text->optionFishSeafood) ? $ac->text->optionFishSeafood : esc_html__('Fish and seafood','easy-form-builder'),
			"optionVegetables" => $state && isset($ac->text->optionVegetables) ? $ac->text->optionVegetables : esc_html__('Vegetables','easy-form-builder'),
			"optionGeneralQuestion" => $state && isset($ac->text->optionGeneralQuestion) ? $ac->text->optionGeneralQuestion : esc_html__('General question','easy-form-builder'),
			"optionFeatureRequest" => $state && isset($ac->text->optionFeatureRequest) ? $ac->text->optionFeatureRequest : esc_html__('Feature request','easy-form-builder'),
			"optionBugReport" => $state && isset($ac->text->optionBugReport) ? $ac->text->optionBugReport : esc_html__('Bug report','easy-form-builder'),
			"optionMyAccount" => $state && isset($ac->text->optionMyAccount) ? $ac->text->optionMyAccount : esc_html__('My account','easy-form-builder'),
			"optionComments" => $state && isset($ac->text->optionComments) ? $ac->text->optionComments : esc_html__('Comments','easy-form-builder'),
			"optionQuestions" => $state && isset($ac->text->optionQuestions) ? $ac->text->optionQuestions : esc_html__('Questions','easy-form-builder'),
			"optionBugReports" => $state && isset($ac->text->optionBugReports) ? $ac->text->optionBugReports : esc_html__('Bug Reports','easy-form-builder'),
			"optionFeatureRequestT" => $state && isset($ac->text->optionFeatureRequestT) ? $ac->text->optionFeatureRequestT : esc_html__('Feature Request','easy-form-builder'),
			"optionVegetarian" => $state && isset($ac->text->optionVegetarian) ? $ac->text->optionVegetarian : esc_html__('Vegetarian','easy-form-builder'),
			"optionVegan" => $state && isset($ac->text->optionVegan) ? $ac->text->optionVegan : esc_html__('Vegan','easy-form-builder'),
			"optionKosher" => $state && isset($ac->text->optionKosher) ? $ac->text->optionKosher : esc_html__('Kosher','easy-form-builder'),
			"optionGlutenFree" => $state && isset($ac->text->optionGlutenFree) ? $ac->text->optionGlutenFree : esc_html__('Gluten-free','easy-form-builder'),

			/* translators: Form template vehicle options */
			"optionLimousine" => $state && isset($ac->text->optionLimousine) ? $ac->text->optionLimousine : esc_html__('Limousine','easy-form-builder'),
			"optionExecutiveLimousine" => $state && isset($ac->text->optionExecutiveLimousine) ? $ac->text->optionExecutiveLimousine : esc_html__('Executive Limousine','easy-form-builder'),
			"optionSuvLimousine" => $state && isset($ac->text->optionSuvLimousine) ? $ac->text->optionSuvLimousine : esc_html__('SUV Limousine','easy-form-builder'),
			"optionCoupe" => $state && isset($ac->text->optionCoupe) ? $ac->text->optionCoupe : esc_html__('COUPE','easy-form-builder'),
			"optionSportsCar" => $state && isset($ac->text->optionSportsCar) ? $ac->text->optionSportsCar : esc_html__('SPORTS CAR','easy-form-builder'),
			"optionConvertible" => $state && isset($ac->text->optionConvertible) ? $ac->text->optionConvertible : esc_html__('CONVERTIBLE','easy-form-builder'),

			/* translators: Form template salon service options */
			"optionCutShape" => $state && isset($ac->text->optionCutShape) ? $ac->text->optionCutShape : esc_html__('Cut - Shape','easy-form-builder'),
			"optionCurlyCut" => $state && isset($ac->text->optionCurlyCut) ? $ac->text->optionCurlyCut : esc_html__('Curly Cut','easy-form-builder'),
			"optionHairColor" => $state && isset($ac->text->optionHairColor) ? $ac->text->optionHairColor : esc_html__('Hair Color','easy-form-builder'),

			/* translators: Form template additional field labels */
			"anySpecialInstructions" => $state && isset($ac->text->anySpecialInstructions) ? $ac->text->anySpecialInstructions : esc_html__('Any special instructions?','easy-form-builder'),
			"additionalInformation" => $state && isset($ac->text->additionalInformation) ? $ac->text->additionalInformation : esc_html__('Additional Information','easy-form-builder'),
			"positionApplyingFor" => $state && isset($ac->text->positionApplyingFor) ? $ac->text->positionApplyingFor : esc_html__('Position Applying For','easy-form-builder'),
			"yourName" => $state && isset($ac->text->yourName) ? $ac->text->yourName : esc_html__('Your Name','easy-form-builder'),
			"notSure" => $state && isset($ac->text->notSure) ? $ac->text->notSure : esc_html__('Not Sure','easy-form-builder'),
			"website" => $state && isset($ac->text->website) ? $ac->text->website : esc_html__('Website','easy-form-builder'),
			"accountingQuestion" => $state && isset($ac->text->accountingQuestion) ? $ac->text->accountingQuestion : esc_html__('Accounting & Sell question','easy-form-builder'),
			"technicalQuestion" => $state && isset($ac->text->technicalQuestion) ? $ac->text->technicalQuestion : esc_html__('Technical & support question','easy-form-builder'),

			/* translators: Form template salon service options */
			"optionHighlights" => $state && isset($ac->text->optionHighlights) ? $ac->text->optionHighlights : esc_html__('Highlights','easy-form-builder'),
			"optionTwistOut" => $state && isset($ac->text->optionTwistOut) ? $ac->text->optionTwistOut : esc_html__('Twist-Out','easy-form-builder'),
			"optionTrim" => $state && isset($ac->text->optionTrim) ? $ac->text->optionTrim : esc_html__('Trim','easy-form-builder'),
			"optionTwoStrandTwists" => $state && isset($ac->text->optionTwoStrandTwists) ? $ac->text->optionTwoStrandTwists : esc_html__('Two-Strand Twists','easy-form-builder'),
			"optionNailPolish" => $state && isset($ac->text->optionNailPolish) ? $ac->text->optionNailPolish : esc_html__('Nail Polish','easy-form-builder'),
			"optionNailCare" => $state && isset($ac->text->optionNailCare) ? $ac->text->optionNailCare : esc_html__('Nail Care','easy-form-builder'),
			"optionMakeup" => $state && isset($ac->text->optionMakeup) ? $ac->text->optionMakeup : esc_html__('Make-up','easy-form-builder'),
			"optionIronCurling" => $state && isset($ac->text->optionIronCurling) ? $ac->text->optionIronCurling : esc_html__('Iron/Curling','easy-form-builder'),
			"optionTreatments" => $state && isset($ac->text->optionTreatments) ? $ac->text->optionTreatments : esc_html__('Treatments','easy-form-builder'),
			"optionShampooBlowdry" => $state && isset($ac->text->optionShampooBlowdry) ? $ac->text->optionShampooBlowdry : esc_html__('Shampoo & Blowdry','easy-form-builder'),
			"optionStraighteningPerming" => $state && isset($ac->text->optionStraighteningPerming) ? $ac->text->optionStraighteningPerming : esc_html__('Straightening and Perming','easy-form-builder'),
			"optionWashGo" => $state && isset($ac->text->optionWashGo) ? $ac->text->optionWashGo : esc_html__('Wash & Go','easy-form-builder'),
			"optionWaxing" => $state && isset($ac->text->optionWaxing) ? $ac->text->optionWaxing : esc_html__('Waxing','easy-form-builder'),

			/* translators: Form template graphic design type options */
			"optionFlyer" => $state && isset($ac->text->optionFlyer) ? $ac->text->optionFlyer : esc_html__('Flyer','easy-form-builder'),
			"optionBusinessCard" => $state && isset($ac->text->optionBusinessCard) ? $ac->text->optionBusinessCard : esc_html__('Business Card','easy-form-builder'),
			"optionPostCard" => $state && isset($ac->text->optionPostCard) ? $ac->text->optionPostCard : esc_html__('Post Card','easy-form-builder'),
			"optionBrochure" => $state && isset($ac->text->optionBrochure) ? $ac->text->optionBrochure : esc_html__('Brochure (trifold)','easy-form-builder'),
			"optionLogo" => $state && isset($ac->text->optionLogo) ? $ac->text->optionLogo : esc_html__('Logo','easy-form-builder'),
			"optionBanner" => $state && isset($ac->text->optionBanner) ? $ac->text->optionBanner : esc_html__('Banner','easy-form-builder'),

			/* translators: Form template party food options */
			"optionMains" => $state && isset($ac->text->optionMains) ? $ac->text->optionMains : esc_html__('Mains','easy-form-builder'),
			"optionSalad" => $state && isset($ac->text->optionSalad) ? $ac->text->optionSalad : esc_html__('Salad','easy-form-builder'),
			"optionDessert" => $state && isset($ac->text->optionDessert) ? $ac->text->optionDessert : esc_html__('Dessert','easy-form-builder'),
			"optionDrinks" => $state && isset($ac->text->optionDrinks) ? $ac->text->optionDrinks : esc_html__('Drinks','easy-form-builder'),
			"optionSidesAppetizers" => $state && isset($ac->text->optionSidesAppetizers) ? $ac->text->optionSidesAppetizers : esc_html__('Sides/Appetizers','easy-form-builder'),

			/* translators: Statistic labels shown under public survey/poll result charts */
			"pcResponses" => $state && isset($ac->text->pcResponses) ? $ac->text->pcResponses : esc_html__('Responses','easy-form-builder'),
			"pcAverage" => $state && isset($ac->text->pcAverage) ? $ac->text->pcAverage : esc_html__('Average','easy-form-builder'),
			"pcMin" => $state && isset($ac->text->pcMin) ? $ac->text->pcMin : esc_html__('Min','easy-form-builder'),
			"pcMax" => $state && isset($ac->text->pcMax) ? $ac->text->pcMax : esc_html__('Max','easy-form-builder'),
			"pcAvgLength" => $state && isset($ac->text->pcAvgLength) ? $ac->text->pcAvgLength : esc_html__('Avg Length','easy-form-builder'),

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

		// The phrases spell punctuation as an entity (&hellip;, &mdash;) the way
		// WordPress core writes translatable strings. That renders on its own in
		// markup, but this array is also handed to wp_localize_script() under the
		// nested 'text' key, and core only decodes the top level - so anything
		// JavaScript assigns to textContent or to a placeholder property would
		// show the raw entity. Resolving it once here covers every consumer,
		// including the add-ons that localize their own copy of efb_var.
		$rtrn = emsfb_decode_typographic_entities_efb($rtrn);

		// Group 'efb' must stay identical to the wp_cache_get() near the top of
		// this method (where $efb_ck_final is built); changing only one of the
		// two turns every lookup into a miss without raising any error.
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
		// sms_ready_for_send_efb appends ?track= itself, so it must get the bare page URL
		$link_sms = $lst['type']=="w_link" ? $lst['value'] : 'null';

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
			$smtp = emsfb_is_email_sending_enabled_efb($settings);
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
			}
			// No early return here: email and SMS notifications are independent,
			// the SMS block below must still run for forms that also send email.
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
			if(isset($setting->sms_config) && ($setting->sms_config=="wpsms" || $setting->sms_config=='ws.team') ) $smsSendResult = $this->sms_ready_for_send_efb($form_id, $phone_numbers,$link_sms,'respp' ,'wpsms' ,$trackingCode);
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
						if(is_array($v)){
							$valp[$key][$k] = $this->sanitize_logic_conditions($v);
						} else {
							$valp[$key][$k]=sanitize_text_field($v);
						}
					break;
					case 'logic_rules':
						if(is_array($v)){
							$valp[$key][$k] = $this->sanitize_logic_rules($v, $valp);
						} else {
							$valp[$key][$k]=sanitize_text_field($v);
						}
					break;
					case 'notification_rules':
						$valp[$key][$k] = is_array($v) ? $this->sanitize_notification_rules($v, $valp) : array();
					break;
					case 'confirmation_rules':
						$valp[$key][$k] = is_array($v) ? $this->sanitize_confirmation_rules($v, $valp) : array();
					break;
					case 'webhook_rules':
						$valp[$key][$k] = is_array($v) ? $this->sanitize_webhook_rules($v, $valp) : array();
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

	/**
	 * Sanitize logic_rules array (new conditional logic data model)
	 */
	private function sanitize_logic_rules($rules, $form_structure = array()) {
		if (!is_array($rules)) return array();

		$clean = array();
		$valid_fields = array();
		$valid_steps = array();
		foreach ($form_structure as $field) {
			if (!is_array($field) || empty($field['id_'])) continue;
			$id = sanitize_text_field($field['id_']);
			if (($field['type'] ?? '') === 'step') {
				$valid_steps[$id] = true;
			} elseif (!in_array(($field['type'] ?? ''), array('form', 'option', 'r_matrix', 'buttonNav'), true)) {
				$valid_fields[$id] = true;
			}
		}

		$allowed_action_types = array('show_field','hide_field','set_required','set_optional','enable_field','disable_field','show_step','hide_step','jump_to_step','set_value','copy_value','calculate','clear_value','show_message','set_placeholder','set_help','set_label','focus_field','scroll_to_field','block_submit','end_form');
		$targetless_action_types = array('block_submit','end_form');
		$allowed_scopes = array('field','step','notification','confirmation','webhook','pricing');

		foreach ($rules as $rule) {
			if (!is_array($rule)) continue;

			$r = array();
			$r['id'] = isset($rule['id']) ? sanitize_text_field($rule['id']) : '';
			$r['name'] = isset($rule['name']) ? sanitize_text_field($rule['name']) : '';
			$r['scope'] = isset($rule['scope']) && in_array($rule['scope'], $allowed_scopes, true) ? $rule['scope'] : 'field';
			$r['enabled'] = isset($rule['enabled']) ? (bool) $rule['enabled'] : true;
			$r['priority'] = isset($rule['priority']) ? max(0, min(100000, intval($rule['priority']))) : 10;
			$r['stop_processing'] = !empty($rule['stop_processing']);
			$r['conditions'] = $this->sanitize_logic_condition_group(
				$rule['conditions'] ?? array(),
				$valid_fields
			);

			$r['actions'] = array();
			if (isset($rule['actions']) && is_array($rule['actions'])) {
				foreach ($rule['actions'] as $act) {
					if (!is_array($act)) continue;

					$a = array();
					$a['type'] = isset($act['type']) && in_array($act['type'], $allowed_action_types, true) ? $act['type'] : '';
					$a['target'] = isset($act['target']) ? sanitize_text_field($act['target']) : '';
					if ($a['type'] === '') continue;

					$is_step_action = in_array($a['type'], array('show_step', 'hide_step', 'jump_to_step'), true);
					$is_targetless = in_array($a['type'], $targetless_action_types, true);
					$valid_targets = $is_step_action ? $valid_steps : $valid_fields;
					if ($is_targetless) {
						$a['target'] = '';
					} elseif ($a['target'] === '' || !isset($valid_targets[$a['target']])) {
						continue;
					}

					if (isset($act['value'])) {
						$a['value'] = is_array($act['value'])
							? array_map('sanitize_text_field', $act['value'])
							: sanitize_text_field($act['value']);
					}
					if ($a['type'] === 'set_value') {
						$a['value_type'] = isset($act['value_type']) && $act['value_type'] === 'autofill_key'
							? 'autofill_key'
							: 'static';
					}
					if ($a['type'] === 'copy_value') {
						/* value must reference a real form field to copy from */
						if (!isset($a['value']) || !is_string($a['value']) || !isset($valid_fields[$a['value']])) continue;
					}
					if ($a['type'] === 'calculate' && isset($act['decimals'])) {
						$a['decimals'] = max(0, min(6, intval($act['decimals'])));
					}
					$r['actions'][] = $a;
				}
			}

			$clean[] = $r;
		}
		return $clean;
	}

	private function get_logic_valid_fields_from_structure($form_structure) {
		$valid_fields = array();
		if (!is_array($form_structure)) return $valid_fields;

		foreach ($form_structure as $field) {
			if (!is_array($field) || empty($field['id_'])) continue;
			$type = isset($field['type']) ? $field['type'] : '';
			if (in_array($type, array('form', 'step', 'option', 'r_matrix', 'buttonNav'), true)) continue;
			$valid_fields[sanitize_text_field($field['id_'])] = true;
		}

		return $valid_fields;
	}

	private function sanitize_notification_rules($rules, $form_structure = array()) {
		if (!is_array($rules)) return array();
		$valid_fields = $this->get_logic_valid_fields_from_structure($form_structure);
		$clean = array();

		foreach ($rules as $rule) {
			if (!is_array($rule)) continue;
			$recipient = isset($rule['recipient']) ? sanitize_email($rule['recipient']) : '';
			if ($recipient === '') continue;

			$clean[] = array(
				'id' => isset($rule['id']) ? sanitize_text_field($rule['id']) : '',
				'enabled' => isset($rule['enabled']) ? (bool) $rule['enabled'] : true,
				'name' => isset($rule['name']) ? sanitize_text_field($rule['name']) : '',
				'priority' => isset($rule['priority']) ? max(0, min(100000, intval($rule['priority']))) : 10,
				'conditions' => $this->sanitize_logic_condition_group($rule['conditions'] ?? array(), $valid_fields),
				'recipient' => $recipient,
				'cc' => $this->sanitize_logic_email_list($rule['cc'] ?? ''),
				'bcc' => $this->sanitize_logic_email_list($rule['bcc'] ?? ''),
				'subject' => isset($rule['subject']) ? sanitize_text_field($rule['subject']) : '',
				'template' => isset($rule['template']) ? sanitize_text_field($rule['template']) : 'default',
			);
		}

		return $clean;
	}

	private function sanitize_confirmation_rules($rules, $form_structure = array()) {
		if (!is_array($rules)) return array();
		$valid_fields = $this->get_logic_valid_fields_from_structure($form_structure);
		$clean = array();

		foreach ($rules as $rule) {
			if (!is_array($rule)) continue;
			$action = isset($rule['action']) && in_array($rule['action'], array('message', 'redirect'), true)
				? $rule['action']
				: 'message';

			$clean[] = array(
				'id' => isset($rule['id']) ? sanitize_text_field($rule['id']) : '',
				'enabled' => isset($rule['enabled']) ? (bool) $rule['enabled'] : true,
				'name' => isset($rule['name']) ? sanitize_text_field($rule['name']) : '',
				'priority' => isset($rule['priority']) ? max(0, min(100000, intval($rule['priority']))) : 10,
				'conditions' => $this->sanitize_logic_condition_group($rule['conditions'] ?? array(), $valid_fields),
				'action' => $action,
				'url' => isset($rule['url']) ? esc_url_raw($rule['url']) : '',
				'message' => isset($rule['message']) ? wp_kses_post($rule['message']) : '',
				'done' => isset($rule['done']) ? sanitize_text_field($rule['done']) : '',
				'icon' => $this->sanitize_logic_bi_icon($rule['icon'] ?? ''),
				'tracking_label' => isset($rule['tracking_label']) ? sanitize_text_field($rule['tracking_label']) : '',
				'icon_color' => $this->sanitize_logic_hex_color($rule['icon_color'] ?? ''),
				'title_color' => $this->sanitize_logic_hex_color($rule['title_color'] ?? ''),
				'message_color' => $this->sanitize_logic_hex_color($rule['message_color'] ?? ''),
			);
		}

		return $clean;
	}

	/* CC/BCC lists: comma-separated string or array in, array of valid emails out. */
	private function sanitize_logic_email_list($value) {
		$items = is_array($value) ? $value : explode(',', (string) $value);
		$clean = array();
		foreach ($items as $item) {
			$email = sanitize_email(trim((string) $item));
			if ($email !== '' && is_email($email) && !in_array($email, $clean, true)) $clean[] = $email;
		}
		return $clean;
	}

	/* Confirmation display overrides: only a bootstrap-icons class name is a valid icon. */
	private function sanitize_logic_bi_icon($icon) {
		$icon = is_string($icon) ? trim($icon) : '';
		return preg_match('/^bi-[a-z0-9-]+$/', $icon) ? $icon : '';
	}

	/* Confirmation display overrides: only a full 6-digit hex color is accepted. */
	private function sanitize_logic_hex_color($color) {
		$color = is_string($color) ? trim($color) : '';
		return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '';
	}

	private function sanitize_webhook_rules($rules, $form_structure = array()) {
		if (!is_array($rules)) return array();
		$valid_fields = $this->get_logic_valid_fields_from_structure($form_structure);
		$clean = array();

		foreach ($rules as $rule) {
			if (!is_array($rule)) continue;
			$rule_action = isset($rule['action']) && $rule['action'] === 'stop' ? 'stop' : 'trigger';
			$url = isset($rule['url']) ? esc_url_raw($rule['url']) : '';
			/* stop rules cancel other webhooks; they never call a URL themselves */
			if ($url === '' && $rule_action !== 'stop') continue;
			$method = isset($rule['method']) ? strtoupper(sanitize_text_field($rule['method'])) : 'POST';
			if (!in_array($method, array('POST', 'GET'), true)) $method = 'POST';

			$payload_fields = array();
			$payload_raw = isset($rule['payload_fields']) ? $rule['payload_fields'] : '';
			foreach ((is_array($payload_raw) ? $payload_raw : explode(',', (string) $payload_raw)) as $payload_field) {
				$payload_field = sanitize_text_field(trim((string) $payload_field));
				if ($payload_field !== '' && isset($valid_fields[$payload_field]) && !in_array($payload_field, $payload_fields, true)) {
					$payload_fields[] = $payload_field;
				}
			}

			$clean[] = array(
				'id' => isset($rule['id']) ? sanitize_text_field($rule['id']) : '',
				'enabled' => isset($rule['enabled']) ? (bool) $rule['enabled'] : true,
				'name' => isset($rule['name']) ? sanitize_text_field($rule['name']) : '',
				'scope' => 'webhook',
				'priority' => isset($rule['priority']) ? max(0, min(100000, intval($rule['priority']))) : 10,
				'conditions' => $this->sanitize_logic_condition_group($rule['conditions'] ?? array(), $valid_fields),
				'webhook_id' => isset($rule['webhook_id']) ? sanitize_text_field($rule['webhook_id']) : '',
				'url' => $url,
				'method' => $method,
				'action' => $rule_action,
				'payload_fields' => $payload_fields,
			);
		}

		return $clean;
	}

	/**
	 * Sanitize a nested conditional-logic group.
	 */
	private function sanitize_logic_condition_group($group, $valid_fields) {
		$allowed_compares = array(
			'is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with',
			'gt', 'gte', 'lt', 'lte', 'between', 'not_between',
			'is_empty', 'is_not_empty',
			'is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt',
			'date_before', 'date_after', 'date_between'
		);
		$allowed_sources = array('field', 'query_param', 'user', 'current_step');
		$clean = array(
			'type' => 'group',
			'operator' => 'AND',
			'items' => array(),
		);

		if (!is_array($group)) return $clean;
		$operator = strtoupper(sanitize_text_field($group['operator'] ?? 'AND'));
		$clean['operator'] = in_array($operator, array('AND', 'OR'), true) ? $operator : 'AND';
		if (!empty($group['negate'])) $clean['negate'] = true;

		foreach (($group['items'] ?? array()) as $item) {
			if (!is_array($item)) continue;
			$connector = strtoupper(sanitize_text_field($item['connector'] ?? ''));
			$connector = in_array($connector, array('AND', 'OR'), true) ? $connector : '';
			if (($item['type'] ?? '') === 'group' || isset($item['items'])) {
				$nested = $this->sanitize_logic_condition_group($item, $valid_fields);
				if (!empty($nested['items'])) {
					if ($connector !== '' && !empty($clean['items'])) $nested['connector'] = $connector;
					$clean['items'][] = $nested;
				}
				continue;
			}

			$source = sanitize_text_field($item['source'] ?? 'field');
			if (!in_array($source, $allowed_sources, true)) $source = 'field';

			$field_id = sanitize_text_field($item['field_id'] ?? '');
			if ($source === 'field') {
				if ($field_id === '' || !isset($valid_fields[$field_id])) continue;
			} elseif ($source === 'query_param') {
				/* field_id carries the query-string key; only URL-safe chars */
				$field_id = preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', (string)($item['param'] ?? $field_id));
				if ($field_id === '') continue;
			} elseif ($source === 'user') {
				if (!in_array($field_id, array('logged_in', 'role'), true)) continue;
			} else { /* current_step */
				$field_id = 'current_step';
			}

			$compare = sanitize_text_field($item['compare'] ?? 'is');
			if (!in_array($compare, $allowed_compares, true)) $compare = 'is';
			$value = $item['value'] ?? '';
			if (is_array($value)) {
				$value = array_map('sanitize_text_field', $value);
			} else {
				$value = sanitize_text_field($value);
			}

			$condition = array(
				'type' => 'condition',
				'source' => $source,
				'field_id' => $field_id,
				'compare' => $compare,
				'value' => $value,
			);
			if ($source === 'query_param') $condition['param'] = $field_id;
			if ($connector !== '' && !empty($clean['items'])) $condition['connector'] = $connector;
			$clean['items'][] = $condition;
		}

		return $clean;
	}

	/**
	 * Sanitize legacy conditions array
	 */
	private function sanitize_logic_conditions($conditions) {
		if (!is_array($conditions)) return array();
		$clean = array();
		foreach ($conditions as $cond) {
			if (!is_array($cond)) continue;
			$c = array();
			$c['id_'] = isset($cond['id_']) ? sanitize_text_field($cond['id_']) : '';
			$c['state'] = isset($cond['state']) ? (bool) $cond['state'] : false;
			$c['show'] = isset($cond['show']) ? (bool) $cond['show'] : true;
			if (isset($cond['condition']) && is_array($cond['condition'])) {
				$c['condition'] = array();
				foreach ($cond['condition'] as $rule) {
					if (!is_array($rule)) continue;
					$c['condition'][] = array(
						'no' => isset($rule['no']) ? sanitize_text_field($rule['no']) : '0',
						'term' => isset($rule['term']) ? sanitize_text_field($rule['term']) : 'is',
						'one' => isset($rule['one']) ? sanitize_text_field($rule['one']) : '',
						'two' => isset($rule['two']) ? sanitize_text_field($rule['two']) : ''
					);
				}
			}
			$clean[] = $c;
		}
		return $clean;
	}

	public function get_geolocation() {
		  $ip = $this->get_ip_address();
	  }

	  public function get_ip_address() {
        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip =
			sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        // REMOTE_ADDR is absent under WP-CLI and WP-Cron, where this is reached
        // through the scheduled jobs. Reading it unguarded logged a warning on
        // every such run, and PHP 8.1+ then passed null on into strtolower().
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) { $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check !== false){$ip = substr($ip,0,$check);}
        return $ip;
    }

	public function addon_adds_cron_efb(){
		// Kept as a no-op for backward compatibility with sites that have an old
		// scheduled event. Add-on recovery is request-driven and never relies on
		// WP-Cron, so a visitor is never left waiting for a cron worker.
		return false;
	}

	public function resume_addon_downloads_efb(){

		if ( ! get_option('emsfb_addons_renew_required') ) {
			return;
		}
		delete_option('emsfb_addons_renew_required');
		delete_transient('emsfb_addons_renew_backoff');
		delete_option('emsfb_addons_dl_failures');
		delete_transient('emsfb_addons_dl_backoff');
		// The next relevant admin/form request performs recovery directly.
	}

	/**
	 * Canonical list of every add-on flag key.
	 *
	 * Single source of truth for the add-on identifiers used across the plugin.
	 * When bundling a new add-on, add its key here once (and, if it ships files,
	 * to get_addon_required_files_efb()); every consumer that only needs the
	 * full set of keys reads it from here instead of hard-coding its own copy.
	 *
	 * @return array<int, string>
	 */
	public function get_all_addon_keys_efb(){
		return array(
			'AdnSPF', // Stripe payment gateway
			'AdnOF',  // Offline Forms
			'AdnPPF', // Persia Payment
			'AdnATC', // Advanced Tracking Code
			'AdnSS',  // SMS notifications
			'AdnCPF', // AdnCPF add-on (reserved)
			'AdnESZ', // AdnESZ add-on (reserved)
			'AdnSE',  // Search Entry
			'AdnWHS', // Webhook
			'AdnPAP', // PayPal payment gateway
			'AdnWSP', // AdnWSP add-on (reserved)
			'AdnSMF', // Conditional Logic (smart form)
			'AdnPLF', // AdnPLF add-on (reserved)
			'AdnMSF', // AdnMSF add-on (reserved)
			'AdnBEF', // Booking
			'AdnPDP', // Persian (Jalali) Date Picker
			'AdnADP', // Arabic (Hijri) Date Picker
			'AdnATF', // Auto-Populate / Autofill
			'AdnTLG', // Telegram notifications
			'AdnGoS', // Google Sheet integration
			'AdnHSH', // Human Shield (form security & spam protection)
		);
	}

	/**
	 * Return the files that prove a bundled add-on is usable locally.
	 *
	 * This intentionally performs no HTTP request. Admin pages call this on
	 * every load, while a missing add-on is recovered later by WP-Cron.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function get_addon_required_files_efb(){
		return array(
			'AdnSPF' => array( 'vendor/stripe/class-Emsfb-stripe-payment.php' ),
			'AdnOF'  => array( 'vendor/offline/json/countries.js' ),
			'AdnPPF' => array( 'vendor/persiapay/zarinpal.php' ),
			'AdnSS'  => array( 'vendor/smssended/smsefb.php' ),
			'AdnPDP' => array( 'vendor/persiadatepicker/persiandate.php' ),
			'AdnADP' => array( 'vendor/arabicdatepicker/arabicdate.php' ),
			'AdnPAP' => array( 'vendor/paypal/paypalefb.php' ),
			'AdnTLG' => array( 'vendor/telegram/telegram-new-efb.php' ),
			'AdnATF' => array( 'vendor/autofill/autofillefb.php' ),
			'AdnGoS' => array( 'vendor/googlesheet/class-Emsfb-googlesheet.php' ),
			'AdnSMF' => array( 'vendor/logic/logic/class-Emsfb-logic-validator.php' ),
			'AdnHSH' => array( 'vendor/human-shield/human-shield-efb.php' ),
		);
	}

	/**
	 * Check enabled add-ons from disk only. This is deliberately cheap enough
	 * to run for every Create and Panel page request.
	 *
	 * @param object|null $settings Decoded EFB settings.
	 * @return array{missing: array<string, array<int, string>>, checked: array<int, string>}
	 */
	public function get_addon_local_health_efb( $settings = null ){
		if ( ! is_object( $settings ) ) {
			$settings = get_setting_Emsfb( 'decoded' );
		}

		$missing = array();
		$checked = array();
		foreach ( $this->get_addon_required_files_efb() as $addon_key => $required_files ) {
			if ( ! is_object( $settings ) || empty( $settings->{$addon_key} ) ) {
				continue;
			}

			$checked[] = $addon_key;
			$missing_files = array();
			foreach ( $required_files as $relative_file ) {
				if ( ! file_exists( EMSFB_PLUGIN_DIRECTORY . $relative_file ) ) {
					$missing_files[] = $relative_file;
				}
			}

			if ( ! empty( $missing_files ) ) {
				$missing[ $addon_key ] = $missing_files;
			}
		}

		return array(
			'missing' => $missing,
			'checked' => $checked,
		);
	}

	/**
	 * Determine whether an add-on that has a local manifest is already usable.
	 * Unknown legacy add-ons deliberately return false so their manual recovery
	 * path remains available.
	 */
	public function is_addon_installed_locally_efb( $addon_key ){
		$requirements = $this->get_addon_required_files_efb();
		if ( ! isset( $requirements[ $addon_key ] ) ) {
			return false;
		}

		foreach ( $requirements[ $addon_key ] as $relative_file ) {
			if ( ! file_exists( EMSFB_PLUGIN_DIRECTORY . $relative_file ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Queue a recovery only when a local health check finds missing files.
	 * The scheduler prevents network work from delaying the current request.
	 */
	public function schedule_missing_addon_recovery_efb( $settings = null ){
		$health = $this->get_addon_local_health_efb( $settings );
		// Deprecated compatibility wrapper. Recovery is performed immediately by
		// recover_missing_addons_efb() at the relevant admin/form entry point;
		// nothing is queued to WP-Cron.
		return ! empty( $health['missing'] );
	}

	/**
	 * User-facing names for recovery diagnostics.
	 *
	 * @param string $addon_key Add-on setting key.
	 * @return string
	 */
	public function get_addon_recovery_label_efb( $addon_key ) {
		$labels = array(
			'AdnSPF' => esc_html__( 'Stripe', 'easy-form-builder' ),
			'AdnOF'  => esc_html__( 'Offline Forms', 'easy-form-builder' ),
			'AdnPPF' => esc_html__( 'Persia Payment', 'easy-form-builder' ),
			'AdnSS'  => esc_html__( 'SMS Notifications', 'easy-form-builder' ),
			'AdnPDP' => esc_html__( 'Persian Date Picker', 'easy-form-builder' ),
			'AdnADP' => esc_html__( 'Arabic Date Picker', 'easy-form-builder' ),
			'AdnPAP' => esc_html__( 'PayPal', 'easy-form-builder' ),
			'AdnTLG' => esc_html__( 'Telegram', 'easy-form-builder' ),
			'AdnATF' => esc_html__( 'Auto-Populate', 'easy-form-builder' ),
			'AdnGoS' => esc_html__( 'Google Sheets', 'easy-form-builder' ),
			'AdnSMF' => esc_html__( 'Conditional Logic', 'easy-form-builder' ),
			'AdnHSH' => esc_html__( 'Form Security & Spam Protection', 'easy-form-builder' ),
		);

		return isset( $labels[ $addon_key ] ) ? $labels[ $addon_key ] : sanitize_text_field( $addon_key );
	}

	/**
	 * Recover missing enabled add-ons in the current request.
	 *
	 * Normal requests only do local file checks. A network request happens here
	 * only when a file is actually missing, which means recovery does not depend
	 * on WP-Cron and a form/admin screen cannot continue with partial add-ons.
	 *
	 * @param object|null $settings Decoded EFB settings.
	 * @param string      $source   Recovery initiator, used for diagnostics.
	 * @return array<string, mixed>
	 */
	public function recover_missing_addons_efb( $settings = null, $source = 'automatic' ) {
		static $request_result = null;
		if ( null !== $request_result ) {
			return $request_result;
		}

		$health = $this->get_addon_local_health_efb( $settings );
		$initial_missing = array_keys( $health['missing'] );
		if ( empty( $initial_missing ) ) {
			if ( false !== get_option( 'emsfb_addons_reinstall_required', false ) ) {
				delete_option( 'emsfb_addons_reinstall_required' );
			}
			if ( false !== get_option( 'emsfb_addon_recovery_result', false ) ) {
				delete_option( 'emsfb_addon_recovery_result' );
			}
			return $request_result = array(
				'success'         => true,
				'needed'          => false,
				'recovered'       => false,
				'initial_missing' => array(),
				'missing'         => array(),
				'errors'          => array(),
				'renew_required'  => false,
			);
		}

		$download = $this->download_all_addons_efb( true );
		$health_after = $this->get_addon_local_health_efb( $settings );
		$success = empty( $health_after['missing'] );
		$result = array(
			'success'         => $success,
			'needed'          => true,
			'recovered'       => $success,
			'initial_missing' => $initial_missing,
			'missing'         => array_keys( $health_after['missing'] ),
			'errors'          => isset( $download['errors'] ) ? $download['errors'] : array(),
			'renew_required'  => ! empty( $download['renew_required'] ),
			'source'          => sanitize_key( $source ),
			'attempted_at'    => current_time( 'mysql' ),
		);

		if ( $success ) {
			delete_option( 'emsfb_addons_reinstall_required' );
			if ( false !== get_option( 'emsfb_addon_recovery_result', false ) ) {
				delete_option( 'emsfb_addon_recovery_result' );
			}
			delete_option( 'emsfb_addons_dl_failures' );
			delete_transient( 'emsfb_addons_dl_backoff' );
		} else {
			update_option( 'emsfb_addon_recovery_result', $result, false );
		}

		return $request_result = $result;
	}

	/**
	 * Health gate for public form rendering.
	 *
	 * Deliberately does no network work. recover_missing_addons_efb() downloads
	 * inline, which is right in wp-admin where an administrator is waiting for
	 * the result, but on a visitor request it blocks page rendering for as long
	 * as the add-on server takes to answer. Here the repair is handed to the
	 * background runner and the caller is told immediately that the form cannot
	 * be rendered yet.
	 *
	 * Returns the same shape as recover_missing_addons_efb() so callers keep
	 * their existing branches.
	 *
	 * @param  object|null $settings Optional settings object.
	 * @return array
	 */
	public function check_addons_for_public_request_efb( $settings = null ) {
		/* No result cache here on purpose: the health check is a handful of
		 * file_exists() calls, and both expensive branches below are already
		 * once-per-request — recover_missing_addons_efb() keeps its own guard and
		 * queue_addon_recovery_efb() is rate limited by a transient lock. */
		$health  = $this->get_addon_local_health_efb( $settings );
		$missing = array_keys( $health['missing'] );

		if ( empty( $missing ) ) {
			return array(
				'success'         => true,
				'needed'          => false,
				'recovered'       => false,
				'initial_missing' => array(),
				'missing'         => array(),
				'errors'          => array(),
				'renew_required'  => false,
				'source'          => 'public_form',
				'deferred'        => false,
			);
		}

		/* No usable scheduler: deferring would leave the form showing the update
		 * notice forever, so repair here and now. The wait is capped by
		 * addon_inline_mode_efb, and on success recover_missing_addons_efb()
		 * reports recovered = true, which makes the caller render the reload UI
		 * and the visitor lands on a working form. */
		if ( ! $this->is_cron_available_efb() ) {
			$this->addon_inline_mode_efb = true;
			try {
				$inline = $this->recover_missing_addons_efb( $settings, 'public_inline' );
			} finally {
				$this->addon_inline_mode_efb = false;
			}
			$inline['deferred'] = false;
			$inline['inline']   = true;
			return $inline;
		}

		$queue = $this->queue_addon_recovery_efb( array(
			'addon'  => reset( $missing ),
			'source' => 'public_form',
		) );

		return array(
			'success'         => false,
			'needed'          => true,
			'recovered'       => false,
			'initial_missing' => $missing,
			'missing'         => $missing,
			'errors'          => array(),
			'renew_required'  => false,
			'source'          => 'public_form',
			'deferred'        => true,
			'queued'          => ! empty( $queue['queued'] ),
			'queue_reason'    => isset( $queue['reason'] ) ? $queue['reason'] : '',
		);
	}

	/**
	 * Turn the latest recovery result into a short, actionable summary.
	 *
	 * @param array<string, mixed> $result Recovery result.
	 * @return string
	 */
	public function get_addon_recovery_message_efb( $result ) {
		if ( ! empty( $result['renew_required'] ) ) {
			return esc_html__( 'Your subscription needs renewing before the missing add-ons can be restored.', 'easy-form-builder' );
		}

		if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
			$messages = array();
			foreach ( $result['errors'] as $addon_key => $message ) {
				$messages[] = $this->get_addon_recovery_label_efb( $addon_key ) . ': ' . wp_strip_all_tags( (string) $message );
			}
			return implode( ' ', $messages );
		}

		return esc_html__( 'The missing add-on files could not be restored. Check the server connection and file permissions, then try again.', 'easy-form-builder' );
	}

	/**
	 * Decide how the admin add-on recovery UI should behave for this request.
	 *
	 * Returns one of:
	 *  - 'block'  : a plugin update ran and required add-on files are still
	 *               missing; Create/Panel/Add-ons must not render their normal
	 *               contents until recovery completes.
	 *  - 'inline' : files are missing without a pending update; show a recovery
	 *               banner but let the page load.
	 *  - 'none'   : every enabled add-on is present on disk.
	 *
	 * The post-update flag is cleared automatically once nothing is missing, so
	 * the block clears itself the moment recovery (or a manual reinstall) lands.
	 *
	 * @param object|null $settings Decoded EFB settings.
	 * @return string
	 */
	public function addon_recovery_state_efb( $settings = null ) {
		$health      = $this->get_addon_local_health_efb( $settings );
		$missing     = ! empty( $health['missing'] );
		$post_update = (bool) get_option( 'emsfb_addons_reinstall_required' );

		if ( ! $missing ) {
			if ( $post_update ) {
				delete_option( 'emsfb_addons_reinstall_required' );
			}
			if ( false !== get_option( 'emsfb_addon_recovery_result', false ) ) {
				delete_option( 'emsfb_addon_recovery_result' );
			}
			return 'none';
		}

		return $post_update ? 'block' : 'inline';
	}

	/**
	 * Install every missing add-on on demand (the Recover button). Runs the same
	 * routine as the background cron, but reports the outcome to the browser so
	 * the user can activate immediately afterwards.
	 */
	public function ajax_recover_addons_efb() {
		if ( ! check_ajax_referer( 'wp_rest', 'nonce', false ) || ! $this->user_permission_efb_admin_dashboard() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to do this.', 'easy-form-builder' ) ), 403 );
		}

		// A manual recovery must never be skipped by an earlier automatic back-off.
		delete_option( 'emsfb_addons_dl_failures' );
		delete_transient( 'emsfb_addons_dl_backoff' );

		$result = $this->recover_missing_addons_efb( null, 'manual' );
		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( array( 'done' => true, 'message' => esc_html__( 'All required add-ons were restored.', 'easy-form-builder' ) ) );
		}

		wp_send_json_error( array(
			'message' => $this->get_addon_recovery_message_efb( $result ),
			'missing' => isset( $result['missing'] ) ? $result['missing'] : array(),
			'errors'  => isset( $result['errors'] ) ? $result['errors'] : array(),
		) );
	}

	/**
	 * Recovery UI shared by Create, Panel and the Add-ons page.
	 *
	 * The card asks the user to reinstall missing add-ons, installs them over
	 * admin-ajax, then swaps in an "Activate" button that reloads the page so the
	 * freshly restored add-on files are loaded by PHP.
	 *
	 * @param string $mode 'block' for the full-screen post-update gate, otherwise
	 *                     an inline banner.
	 * @return string
	 */
	public function render_addon_recovery_ui_efb( $mode = 'inline' ) {
		$is_block = ( 'block' === $mode );
		$ajax     = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'wp_rest' );
		$health   = $this->get_addon_local_health_efb();
		$last_result = get_option( 'emsfb_addon_recovery_result', array() );
		$has_error = is_array( $last_result ) && ! empty( $last_result['errors'] );
		$missing_labels = array();
		foreach ( array_keys( $health['missing'] ) as $addon_key ) {
			$missing_labels[] = $this->get_addon_recovery_label_efb( $addon_key );
		}

		$title = $is_block
			? esc_html__( 'Finish updating Easy Form Builder', 'easy-form-builder' )
			: esc_html__( 'Some add-on files are missing', 'easy-form-builder' );
		$intro = $is_block
			? esc_html__( 'The plugin was updated, so its add-ons must be reinstalled before this page can load. Click the button below to install them.', 'easy-form-builder' )
			: esc_html__( 'One or more add-on files are missing. Click the button below to reinstall them.', 'easy-form-builder' );
		if ( $has_error ) {
			$intro = esc_html__( 'Automatic recovery was attempted but did not finish. Review the reason below, fix it if necessary, then try again.', 'easy-form-builder' );
		}

		$t = array(
			'recover'   => esc_html__( 'Recover add-ons', 'easy-form-builder' ),
			'installing'=> esc_html__( 'Installing add-ons…', 'easy-form-builder' ),
			'doneTitle' => esc_html__( 'Installation complete.', 'easy-form-builder' ),
			'doneBody'  => esc_html__( 'Click to activate the add-ons.', 'easy-form-builder' ),
			'activate'  => esc_html__( 'Activate add-ons', 'easy-form-builder' ),
			'error'     => esc_html__( 'Installation failed. Please try again.', 'easy-form-builder' ),
		);

		$outer_style = $is_block
			? 'max-width:640px;margin:60px auto;'
			: 'max-width:960px;margin:16px auto;';

		ob_start();
		?>
		<div class="efb efb-addon-recovery" id="efb-addon-recovery" style="<?php echo esc_attr( $outer_style ); ?>">
			<div style="border:1px solid #e0c200;background:#fff9db;border-radius:14px;padding:28px 26px;text-align:center;box-shadow:0 4px 18px rgba(0,0,0,0.06);">
				<div style="margin-bottom:12px;font-size:34px;line-height:1;color:#b58900;"><i class="efb bi-shield-exclamation"></i></div>
				<h3 class="efb" style="margin:0 0 10px;font-size:1.4em;color:#5a4b00;"><?php echo $title; // phpcs:ignore ?></h3>
				<p class="efb" style="margin:0 0 18px;color:#6b5d16;font-size:1.02em;line-height:1.7;"><?php echo $intro; // phpcs:ignore ?></p>
				<?php if ( ! empty( $missing_labels ) ) : ?>
					<p class="efb" style="margin:0 0 14px;color:#5a4b00;"><strong><?php echo esc_html__( 'Missing add-ons:', 'easy-form-builder' ); ?></strong> <?php echo esc_html( implode( ', ', $missing_labels ) ); ?></p>
				<?php endif; ?>
				<?php if ( $has_error ) : ?>
					<details class="efb" open style="margin:0 0 16px;text-align:left;background:#fff;border:1px solid #eadc98;border-radius:8px;padding:10px 14px;color:#4a3d09;">
						<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html__( 'Why the recovery failed', 'easy-form-builder' ); ?></summary>
						<ul style="margin:10px 0 0;padding-inline-start:20px;">
							<?php foreach ( $last_result['errors'] as $addon_key => $message ) : ?>
								<li><strong><?php echo esc_html( $this->get_addon_recovery_label_efb( $addon_key ) ); ?>:</strong> <?php echo esc_html( wp_strip_all_tags( (string) $message ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
				<div id="efb-recover-actions">
					<button type="button" id="efb-recover-btn" class="efb btn btn-warning btn-lg" onclick="efbRecoverAddons_efb(this)" style="border:none;border-radius:9px;padding:10px 26px;font-size:1em;cursor:pointer;background:#e0b100;color:#3a2f00;font-weight:600;">
						<i class="efb bi-arrow-repeat" style="margin-inline-end:6px;"></i><?php echo $t['recover']; // phpcs:ignore ?>
					</button>
				</div>
				<div id="efb-recover-status" class="efb" style="margin-top:14px;font-size:0.95em;color:#6b5d16;min-height:1.2em;" aria-live="polite"></div>
			</div>
		</div>
		<script>
		if (typeof window.efbRecoverAddons_efb !== 'function') {
			window.efbRecoverAddonsCfg_efb = {
				ajax: <?php echo wp_json_encode( $ajax ); ?>,
				nonce: <?php echo wp_json_encode( $nonce ); ?>,
				t: <?php echo wp_json_encode( $t ); ?>
			};
			window.efbRecoverAddons_efb = function (btn) {
				var cfg = window.efbRecoverAddonsCfg_efb;
				var status = document.getElementById('efb-recover-status');
				var actions = document.getElementById('efb-recover-actions');
				if (btn) { btn.disabled = true; }
				if (status) { status.textContent = cfg.t.installing; }
				var body = new FormData();
				body.append('action', 'emsfb_recover_addons');
				body.append('nonce', cfg.nonce);
				fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (res && res.success) {
							if (actions) {
								actions.innerHTML = '<button type="button" class="efb btn btn-success btn-lg" onclick="location.reload()" style="border:none;border-radius:9px;padding:10px 26px;font-size:1em;cursor:pointer;background:#1a9d55;color:#fff;font-weight:600;"><i class="efb bi-check2-circle" style="margin-inline-end:6px;"></i>' + cfg.t.activate + '</button>';
							}
							if (status) { status.innerHTML = '<strong>' + cfg.t.doneTitle + '</strong> ' + cfg.t.doneBody; }
						} else {
							if (btn) { btn.disabled = false; }
							if (status) { status.textContent = (res && res.data && res.data.message) ? res.data.message : cfg.t.error; }
						}
					})
					.catch(function () {
						if (btn) { btn.disabled = false; }
						if (status) { status.textContent = cfg.t.error; }
					});
			};
		}
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Show a brief transition after a synchronous repair. Reloading is required
	 * because the add-on classes were not available when PHP started this request.
	 *
	 * @param bool $public Whether this is rendered for a public form page.
	 * @return string
	 */
	public function render_addon_recovery_reload_ui_efb( $public = false ) {
		$title = $public
			? esc_html__( 'Preparing your form', 'easy-form-builder' )
			: esc_html__( 'Add-ons restored successfully', 'easy-form-builder' );
		$body = $public
			? esc_html__( 'The form is being prepared. This page will reload automatically.', 'easy-form-builder' )
			: esc_html__( 'The page will reload automatically so the restored add-ons can be activated.', 'easy-form-builder' );
		$reload = esc_html__( 'Reload now', 'easy-form-builder' );

		ob_start();
		?>
		<div class="efb efb-addon-recovery" style="max-width:640px;margin:60px auto;text-align:center;">
			<div style="border:1px solid #9bd7b5;background:#f1fff6;border-radius:14px;padding:28px 26px;color:#155d32;">
				<div style="font-size:34px;line-height:1;margin-bottom:12px;"><i class="efb bi-check2-circle"></i></div>
				<h3 class="efb" style="margin:0 0 10px;"><?php echo esc_html( $title ); ?></h3>
				<p class="efb" style="margin:0 0 14px;"><?php echo esc_html( $body ); ?></p>
				<p class="efb" style="margin:0;"><a href="" onclick="location.reload();return false;" style="color:#155d32;font-weight:600;"><?php echo esc_html( $reload ); ?></a></p>
			</div>
		</div>
		<script>window.setTimeout(function(){ window.location.reload(); }, 100);</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Safe front-end fallback when automatic recovery cannot finish. Detailed
	 * diagnostics are retained for wp-admin; visitors only see an actionable,
	 * non-sensitive availability message.
	 *
	 * @return string
	 */
	/**
	 * Safe front-end fallback when the form cannot be rendered yet.
	 *
	 * @param int $retry_after_seconds Reload the page after this many seconds.
	 *                                 Pass 0 when a reload cannot help, so the
	 *                                 visitor is not put in a refresh loop.
	 * @return string
	 */
	public function render_addon_recovery_public_error_ui_efb( $retry_after_seconds = 0 ) {
		return '<div class="efb" role="alert" style="margin:18px 0;padding:20px;border:1px solid #e3b7b7;border-radius:10px;background:#fff7f7;color:#7a2020;text-align:center;">'
			. '<strong>' . esc_html__( 'This form is temporarily unavailable.', 'easy-form-builder' ) . '</strong><br>'
			. esc_html__( 'We could not restore a required form component automatically. Please try again shortly; the site administrator can view the exact recovery error in Easy Form Builder.', 'easy-form-builder' )
			. $this->addon_reload_script_efb( $retry_after_seconds )
			. '</div>';
	}

	/**
	 * One-shot auto-reload for the "please wait" notices.
	 *
	 * The repair runs in a scheduled event, so the page that shows the notice
	 * has to come back on its own for the visitor to see the working form.
	 * Guarded so several notices on one page schedule a single reload.
	 *
	 * @param  int $seconds Delay before reloading; 0 disables the reload.
	 * @return string       Inline script, or an empty string.
	 */
	public function addon_reload_script_efb( $seconds = 0 ) {
		$seconds = (int) $seconds;
		if ( $seconds <= 0 ) {
			return '';
		}

		return '<script>(function(){if(window.efbAddonReloadScheduled){return;}'
			. 'window.efbAddonReloadScheduled=true;'
			. 'window.setTimeout(function(){window.location.reload();},' . ( $seconds * 1000 ) . ');})();</script>';
	}

public function addon_add_efb($value) {

		// A local health check has already proved this add-on is ready. Never
		// contact the licensing/download endpoint just to rediscover that fact.
		if ( $this->is_addon_installed_locally_efb( $value ) ) {
			return array(
				'status'  => true,
				'message' => esc_html__( 'The add-on is already installed.', 'easy-form-builder' ),
			);
		}

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
        $is_persian_locale = get_locale() === 'fa_IR';
        $build_addon_url = function($base_domain) use ($server_name, $value, $vwp, $vefb) {
            return untrailingslashit($base_domain) . '/wp-json/wl/v1/addons-link/' . $server_name . '/' . $value . '/' . $vwp . '/' . $vefb . '/';
        };
		// Same endpoint order as the install handler, from the same helper.
		$addon_endpoints = $this->addon_api_domains_efb();
		$remaining_endpoints = isset($addon_endpoints['endpoints'])
			? array_values((array) $addon_endpoints['endpoints'])
			: array_filter(array($addon_endpoints['primary'], $addon_endpoints['fallback']));
		/* Inline repair runs inside a visitor's page load, so the wait is capped
		 * hard: one short attempt against a limited number of endpoints instead
		 * of the full ladder used when nobody is waiting on the other end. */
		$request_plan    = $this->addon_request_plan_efb($is_persian_locale);
		$request_timeout = $request_plan['timeout'];
		$remaining_endpoints = array_slice($remaining_endpoints, 0, max(1, (int) ($request_plan['max_endpoints'] ?? 3)));
		$domain = (string) array_shift($remaining_endpoints);
        $server_label = wp_parse_url($domain, PHP_URL_HOST);
        $u = $build_addon_url($domain);
		$name_space = 'emsfb_addon_' . $value;
		delete_option($name_space);

        $max_attempts = $request_plan['attempts'];
        $fallback_max_attempts = 1;
        $attempt = 0;
        $success = false;
        $error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
		$error_messag = sprintf($error_message, $domain, 'not_success');
        /* Walks the whole endpoint list rather than a single spare domain: with
         * an independent mirror in play a site can now have three places to
         * ask, and stopping at the second would leave the last one unused
         * exactly when it is needed most. */
        $switch_to_fallback = function($reason) use (&$domain, &$u, &$attempt, &$max_attempts, &$remaining_endpoints, &$server_label, $fallback_max_attempts, $build_addon_url, $value) {
            while (!empty($remaining_endpoints)) {
                $next = untrailingslashit((string) array_shift($remaining_endpoints));
                if ('' === $next || untrailingslashit($domain) === $next) {
                    continue;
                }

                $this->addon_api_mark_down_efb($domain);
                $domain = $next;
                $u = $build_addon_url($domain);
                $server_label = wp_parse_url($domain, PHP_URL_HOST);
                $attempt = 0;
                $max_attempts = $fallback_max_attempts;
                return true;
            }

            return false;
        };

        while ($attempt < $max_attempts && !$success) {
            $request = wp_remote_get($u, ['timeout' => $request_timeout]);

            if (is_wp_error($request)) {
                $attempt++;
                $error_message = sprintf(
                    esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the %s server','easy-form-builder'),
                    $server_label
                );

                if ($attempt >= $max_attempts) {
                    if ($switch_to_fallback('remote_request_wp_error')) {
                        continue;
                    }
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($request);

            if ($response_code != 200) {
                $attempt++;
                $error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
				$error_message = sprintf($error_message, $server_label, $response_code);

                if ($attempt >= $max_attempts) {
                    if ($switch_to_fallback('remote_response_invalid_code')) {
                        continue;
                    }
                    /* 401/403/429 from an endpoint that is plainly up means
                     * something between us and it is refusing the request —
                     * typically the host's IP-reputation firewall. The raw
                     * status code tells the site owner nothing they can act
                     * on, so hand them the offline route instead. */
                    if (in_array((int) $response_code, array(401, 403, 406, 429), true)) {
                        $this->notify_admin_addon_blocked_efb($server_label, $value);
                        return array(
                            'status'  => false,
                            'message' => $this->addon_offline_hint_efb($server_label),
                            'blocked' => true,
                        );
                    }
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }

            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $attempt++;
				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
				$error_message = sprintf($error_message, $server_label, 'invalid_json');

                if ($attempt >= $max_attempts) {
                    if ($switch_to_fallback('remote_response_invalid_json')) {
                        continue;
                    }
                    return array('status' => false, 'message' => $error_message);
                }
                continue;
            }
			if($data==null){
				$attempt++;
				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
				$error_message = sprintf($error_message, $server_label, 'invalid_data');

				if ($attempt >= $max_attempts) {
                    if ($switch_to_fallback('remote_response_empty_data')) {
                        continue;
                    }
					return array('status' => false, 'message' => $error_message);
				}
				continue;
			}

            if ($data->status == false) {
				if (!$is_persian_locale && isset($data->reason) && $data->reason == 'expired') {
					update_option('emsfb_addons_renew_required', time());
					set_transient('emsfb_addons_renew_backoff', 1, DAY_IN_SECONDS);
					$renew_url = isset($data->renew) ? esc_url_raw($data->renew) : $domain . '/checkout?renew=' . urlencode((string) get_option('emsfb_pro_activeCode', ''));
					$error_message = esc_html__('Your Easy Form Builder Pro subscription has expired, so the Pro add-ons could not be downloaded. The plugin keeps working without them. Renew your subscription to restore all Pro features:', 'easy-form-builder') . ' ' . $renew_url;
					return array('status' => false, 'message' => $error_message, 'expired' => true);
				}
				$error_message =  esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
				$error_message = sprintf($error_message, $server_label, 'invalid_status');
                if ($is_persian_locale) {
                    $attempt++;
                    if ($attempt >= $max_attempts) {
                        if ($switch_to_fallback('remote_response_status_false')) {
                            continue;
                        }
                        return array('status' => false, 'message' => $error_message);
                    }
                    continue;
                }
                return array('status' => false, 'message' => $error_message);
            }

            if (version_compare(EMSFB_PLUGIN_VERSION, $data->v) == -1) {
                return array('status' => false, 'message' =>  esc_html__('The version of the add-on is not compatible with the version of the Easy Form Builder plugin.','easy-form-builder'));
            }

            if ($data->download == true) {
                $url = $this->normalize_addon_download_url_efb( isset( $data->link ) ? $data->link : '' );
                if ( is_wp_error( $url ) ) {
                    $attempt++;
                    $error_message = $url->get_error_message();
                    if ( $attempt >= $max_attempts ) {
                        if ( $switch_to_fallback( 'download_url_not_allowed' ) ) {
                            continue;
                        }
                        return array( 'status' => false, 'message' => $error_message );
                    }
                    continue;
                }

                $directory_name = substr($url, strrpos($url, "/") + 1, -4);
                $directory = EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $directory_name;

				// A directory alone is not a valid installation: updates or partial
				// copies can leave the folder present while its required bootstrap file
				// is gone. Re-extract in that case so automatic recovery truly repairs
				// missing files instead of reporting a false success.
				if (!file_exists($directory) || ! $this->is_addon_installed_locally_efb( $value )) {
					$result = $this->fun_addon_new($url);
                    if (is_wp_error($result)) {
                        if ($is_persian_locale) {
                            $attempt++;
                            $error_message = $result->get_error_message();
                            if ($attempt >= $max_attempts) {
                                if ($switch_to_fallback('download_helper_failed')) {
                                    continue;
                                }
                                return array('status' => false, 'message' => $error_message);
                            }
                            continue;
                        }
                        return array('status' => false, 'message' => $result->get_error_message());
                    }
                }
				update_option($name_space, 1);
                $success = true;
            } else {
                $attempt++;
                $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code: %s','easy-form-builder');
                $error_message = sprintf($error_message, $server_label, 'download_unavailable');
                if ($attempt >= $max_attempts) {
                    if ($switch_to_fallback('download_flag_false')) {
                        continue;
                    }
                    return array('status' => false, 'message' => $error_message);
                }
            }
        }

        if ($success) {
			update_option($name_space, 1);
			$ac = get_setting_Emsfb('decoded');

			if(isset($ac->AdnSPF)==false){
				$ac->AdnSPF=0;
				$ac->AdnOF=0;
				$ac->AdnPPF=0;
				$ac->AdnATC=0;
				$ac->AdnSS=0;
				$ac->AdnCPF=0;
				$ac->AdnESZ=0;
				$ac->AdnSE=0;
				$ac->AdnWHS=0;
				$ac->AdnPAP=0;
				$ac->AdnWSP=0;
				$ac->AdnSMF=0;
				$ac->AdnPLF=0;
				$ac->AdnMSF=0;
				$ac->AdnBEF=0;
				$ac->AdnGoS=0;
				$ac->AdnHSH=0;
				$ac->AdnPDP=0;
				$ac->AdnADP=0;
			}
			$ac->{$value}=1;
			$ac->efb_version=EMSFB_PLUGIN_VERSION;
			$this->set_setting_Emsfb( $ac, $ac->emailSupporter );
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
			if ( ! emsfb_is_php_function_available_efb( 'mkdir' ) || ! emsfb_is_php_function_available_efb( 'rename' ) ) {
				return new WP_Error(
					'filesystem_functions_unavailable',
					esc_html__( 'Cannot install add-ons because this server has disabled the PHP filesystem functions needed to prepare the download. Please ask your hosting provider to enable mkdir and rename, or configure the WordPress filesystem.', 'easy-form-builder' )
				);
			}
			$directory = EMSFB_PLUGIN_DIRECTORY . 'temp';
			if (!file_exists($directory)) {
				mkdir($directory, 0755, true);
			}
			$moved = rename($r, EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip');
		}
		if(!$moved){
			if (file_exists($r)) {
				if ( emsfb_is_php_function_available_efb( 'unlink' ) ) {
					@unlink($r);
				}
			}
			return new WP_Error('move_failed',
				esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to move the downloaded file', 'easy-form-builder')
			);
		}
		if (!$filesystem_ready) {
			WP_Filesystem();
		}
		$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . 'vendor/');
		if (file_exists(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip')) {
			if ( emsfb_is_php_function_available_efb( 'unlink' ) ) {
				@unlink(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip');
			}
		}
		if(is_wp_error($r)){
			return new WP_Error('unzip_failed',
				esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files', 'easy-form-builder')
				. ' (' . $r->get_error_message() . ')'
			);
		}
		return true;
	}

	public function download_all_addons_efb( $return_details = false ){
		$state=true;
		$details = array(
			'success'         => false,
			'attempted'       => array(),
			'installed'       => array(),
			'already_present' => array(),
			'errors'          => array(),
			'missing'         => array(),
			'renew_required'  => false,
		);
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
		$addons['AdnGoS']	=	isset($settings->AdnGoS)	? $settings->AdnGoS	:0;
		$addons['AdnPAP']	=	isset($settings->AdnPAP)	? $settings->AdnPAP	:0;
		$addons['AdnOF']	=	isset($settings->AdnOF)		? $settings->AdnOF	:0;
		$addons['AdnSMF']	=	isset($settings->AdnSMF)	? $settings->AdnSMF	:0;
		$addons['AdnHSH']	=	isset($settings->AdnHSH)	? $settings->AdnHSH	:0;

		$error_messag ='';
		$renew_required = false;
		foreach ($addons as $key => $value) {

			if($value ==1){
				// Do not make an external request for add-ons whose required local
				// files are already present. This guard protects automatic recovery,
				// the manual recovery button, and every legacy caller.
				if ( $this->is_addon_installed_locally_efb( $key ) ) {
					$details['already_present'][] = $key;
					continue;
				}

				if ($key === 'AdnGoS') {
					$local_gs = EMSFB_PLUGIN_DIRECTORY . '/vendor/googlesheet/class-Emsfb-googlesheet.php';
					if (file_exists($local_gs)) {
						update_option('emsfb_addon_AdnGoS', 2);
						continue;
					}
				}
				if ($key === 'AdnHSH') {
					// Ships inside the plugin; never downloaded from the remote server.
					$local_hsh = EMSFB_PLUGIN_DIRECTORY . '/vendor/human-shield/human-shield-efb.php';
					if (file_exists($local_hsh)) {
						update_option('emsfb_addon_AdnHSH', 2);
						$details['already_present'][] = $key;
					} else {
						$state = false;
						$details['errors'][ $key ] = esc_html__( 'This bundled add-on is missing from the plugin files. Please reinstall the Easy Form Builder plugin itself.', 'easy-form-builder' );
					}
					continue;
				}
				$details['attempted'][] = $key;
				$r =$this->addon_add_efb($key);
				if(!is_array($r) || !isset($r['status'])){
					$state=false;
					$details['errors'][ $key ] = esc_html__( 'The add-on server returned an unexpected response.', 'easy-form-builder' );
					continue;
				}
				if($r['status']==false){
					$state=false;
					$details['errors'][ $key ] = isset( $r['message'] ) ? wp_strip_all_tags( (string) $r['message'] ) : esc_html__( 'The add-on could not be installed.', 'easy-form-builder' );
					if(!empty($r['expired'])){
						$renew_required = true;
						continue;
					}
					$error_messag .= $r['message']."<br>";
				} else {
					$details['installed'][] = $key;
				}
			}
		}

		$details['renew_required'] = $renew_required;
		$health_after = $this->get_addon_local_health_efb( $settings );
		$details['missing'] = array_keys( $health_after['missing'] );
		if ( ! empty( $details['missing'] ) ) {
			$state = false;
		}

		if($renew_required){
			// Subscription expired: the add-on server refuses the downloads and
			// notifies the customer itself, so no report email is needed here.
			$details['success'] = false;
			return $return_details ? $details : false;
		}

		if($state==false){
			update_option('emsfb_addons_dl_failures', (int) get_option('emsfb_addons_dl_failures', 0) + 1);
			set_transient('emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS);
		}

		if($state==false){
			/* The background recovery runner suppresses this report while it still
			 * has retries left, so the site owner gets one precise email after the
			 * last attempt instead of one per attempt. */
			if($this->suppress_addon_report_efb){
				return $return_details ? $details : false;
			}

			$to = isset($settings->emailSupporter) ? $settings->emailSupporter : null;
			if($to==null){$to = get_option('admin_email');}

			if($to==null || $to=="null" || $to=="") return $return_details ? $details : false;
			$sub = esc_html__('Report problem','easy-form-builder') .' ['. esc_html__('Easy Form Builder','easy-form-builder').']';
			$m = $this->build_addon_recovery_report_efb($details, $error_messag);

			if(emsfb_is_email_sending_enabled_efb($settings)) {
				$this->send_email_state_new($to ,$sub ,$m,0,"addonsDlProblem",'null','null');
			}
			return $return_details ? $details : false;
		}

			delete_option('emsfb_addons_renew_required');
			delete_transient('emsfb_addons_renew_backoff');
			delete_option('emsfb_addons_dl_failures');
			delete_transient('emsfb_addons_dl_backoff');

            return true;

	}

	/* ================================================================
	 * Background add-on recovery
	 *
	 * A visitor request must never wait on the add-on server. The public
	 * runtime only *queues* a recovery here; the work itself happens in a
	 * WP-Cron event, which core spawns through a non-blocking loopback so
	 * the page that queued it is not delayed.
	 * ============================================================= */

	/** Cron hook name for the background recovery runner. */
	const ADDON_RECOVERY_EVENT_EFB = 'emsfb_addon_recovery_event';

	/** Minutes to wait before each attempt: immediate, then backs off. */
	const ADDON_RECOVERY_DELAYS_EFB = array(0, 15, 120);

	/**
	 * Can a scheduled event actually be relied on to run?
	 *
	 * Without WP-Cron the background runner may never fire, which would leave a
	 * form showing the update notice forever. WP-Cron spawning on page loads is
	 * the normal case; when it is switched off, a real server cron may still be
	 * calling wp-cron.php, so this observes whether our own event has run
	 * recently rather than assuming either way.
	 *
	 * @return bool
	 */
	public function is_cron_available_efb(){
		if(!$this->wp_cron_disabled_efb()){
			return true; // Core spawns pending events on ordinary requests.
		}

		// WP-Cron is off: only trust it if a server cron proved it works.
		$last = (int) get_option('emsfb_cron_last_run', 0);
		return $last > 0 && (time() - $last) < DAY_IN_SECONDS;
	}

	/**
	 * Whether WP-Cron spawning is switched off on this site.
	 *
	 * Split out from is_cron_available_efb() so the decision above can be
	 * exercised in both states: DISABLE_WP_CRON is a constant and cannot be
	 * changed once the process has started.
	 *
	 * @return bool
	 */
	protected function wp_cron_disabled_efb(){
		return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
	}


	/**
	 * Dedicated add-on API and archive host for Persian sites.
	 *
	 * @var string
	 */
	const EMSFB_ADDON_IR_DOMAIN = 'https://easyformbuilder.ir';

	/**
	 * How long a "this domain did not answer" verdict is trusted.
	 *
	 * Retained for backwards compatibility with existing helper callers. It is
	 * used only to remember a temporary endpoint failure while the Persian
	 * add-on flow switches from Whitestudio to Easy Form Builder.
	 *
	 * Spelled out rather than written as HOUR_IN_SECONDS: a class constant is
	 * resolved the moment the class is touched, and a harness that loads this
	 * file without WordPress has no such constant - a fatal, not a skip.
	 */
	const EMSFB_ADDON_DOWN_TTL = 3600;

	/**
	 * Ordered endpoints for the add-on API.
	 *
	 * Whitestudio for everyone, plus easyformbuilder.ir on Persian sites.
	 *
	 * A third, independent mirror was built and then removed: every shared host
	 * evaluated for it sits behind an IP-reputation firewall of its own, so the
	 * mirror reproduced the same failure at a new address. When no endpoint
	 * answers, the caller now surfaces that to the administrator instead, and
	 * the offline Add-ons Handler plugin covers the case entirely.
	 *
	 * All three add-on call sites (the Add-ons page script, the install
	 * handler, and the background recovery) go through here so they cannot
	 * disagree about which endpoint is live.
	 *
	 * 'primary' and 'fallback' are kept alongside the ordered list purely so
	 * older callers keep working; new code should read 'endpoints'.
	 *
	 * @return array{endpoints:string[], primary:string, fallback:string, is_persian:bool, mirror_skipped:bool}
	 */
	public function addon_api_domains_efb() {
		$is_persian = ( get_locale() === 'fa_IR' );
		$endpoints  = array( untrailingslashit( EMSFB_SERVER_URL ) );

		if ( $is_persian ) {
			$endpoints[] = untrailingslashit( self::EMSFB_ADDON_IR_DOMAIN );
		}

		// A domain already proven dead within the TTL is moved to the back
		// rather than dropped: if every endpoint is marked down we still need
		// something to try.
		$live = array();
		$down = array();
		foreach ( array_values( array_unique( $endpoints ) ) as $index => $endpoint ) {
			if ( 0 !== $index && get_transient( $this->addon_api_down_key_efb( $endpoint ) ) ) {
				$down[] = $endpoint;
				continue;
			}
			$live[] = $endpoint;
		}
		$ordered = array_merge( $live, $down );

		return array(
			'endpoints'      => $ordered,
			'primary'        => $ordered[0],
			'fallback'       => isset( $ordered[1] ) ? $ordered[1] : '',
			'is_persian'     => $is_persian,
			'mirror_skipped' => ! empty( $down ),
		);
	}

	/**
	 * Validate an add-on archive URL without changing its provider.
	 *
	 * The allow-list is enforced for every locale, not just fa_IR. It used to
	 * be Persian-only, which was survivable while a single hardcoded domain
	 * answered every request; now that the endpoint list has more than one
	 * host, an unchecked "link" from any of them would be an open redirect
	 * into fun_addon_new() — i.e. arbitrary archive download and extraction.
	 *
	 * @param  string $url Archive URL supplied by the add-on API.
	 * @return string|WP_Error
	 */
	public function normalize_addon_download_url_efb( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return new \WP_Error(
				'emsfb_addon_download_url_missing',
				esc_html__( 'The add-on server did not provide a download URL.', 'easy-form-builder' )
			);
		}

		$parts  = wp_parse_url( $url );
		$parts  = is_array( $parts ) ? $parts : array();
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$allowed_hosts = $this->addon_download_allowed_hosts_efb();

		if (
			'' === $host
			|| ! in_array( $host, $allowed_hosts, true )
			|| '/' !== substr( $path, 0, 1 )
			|| ! preg_match( '/\.zip$/i', $path )
		) {
			return new \WP_Error(
				'emsfb_addon_download_host_not_allowed',
				esc_html__( 'The add-on download URL is not an approved Easy Form Builder source.', 'easy-form-builder' )
			);
		}

		return $url;
	}

	/**
	 * The offline route out of an unreachable download server.
	 *
	 * Some hosts sit behind an IP-reputation firewall that answers 403 to
	 * perfectly ordinary server-to-server requests, and the site owner can
	 * neither see it nor appeal it. Rather than leaving them with a bare
	 * "responded code: 403", point them at the Add-ons Handler plugin, which
	 * carries the archives inside itself and needs no network.
	 *
	 * @param  string $server_label Host that refused, for context.
	 * @return string HTML-safe message.
	 */
	public function addon_offline_hint_efb( $server_label = '' ) {
		$server_label = $server_label !== '' ? $server_label : wp_parse_url( EMSFB_SERVER_URL, PHP_URL_HOST );

		$message = sprintf(
			/* translators: 1: download server host name, 2: sign-in URL */
			esc_html__( 'Your server could not reach %1$s, so the add-on could not be downloaded. This is almost always a firewall on your hosting blocking the outgoing request, not a problem with your licence. To install add-ons without any download at all, sign in at %2$s and download the Easy Form Builder Add-ons Handler plugin, then install it like any other plugin. It contains every add-on your licence covers.', 'easy-form-builder' ),
			esc_html( (string) $server_label ),
			esc_url( EMSFB_SERVER_URL . '/login' )
		);

		// Only worth saying when we can actually name the address; telling
		// someone to "give your host this address: unknown" helps nobody.
		$outgoing_ip = $this->outgoing_ip_hint_efb();
		if ( '' !== $outgoing_ip ) {
			$message .= ' ' . sprintf(
				/* translators: %s: this site's outgoing IP address */
				esc_html__( 'If you would rather fix the connection, ask your hosting provider why outgoing requests from %s are being blocked.', 'easy-form-builder' ),
				esc_html( $outgoing_ip )
			);
		}

		return $message;
	}

	/**
	 * This site's outgoing address, as the blocking side would see it.
	 *
	 * Support tickets stall for days without it, because the address a host
	 * blocks is the outgoing one, which the site owner usually cannot name.
	 * Server-reported values are used as-is; nothing is fetched, so this stays
	 * safe to call while rendering a page.
	 *
	 * @return string
	 */
	public function outgoing_ip_hint_efb() {
		$cached = get_transient( 'emsfb_outgoing_ip_hint' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return 'none' === $cached ? '' : $cached;
		}

		$ip = '';
		foreach ( array( 'SERVER_ADDR', 'LOCAL_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$candidate = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					$ip = $candidate;
					break;
				}
			}
		}

		// An empty result is cached too: the server either reports an address
		// or it does not, and re-deriving it on every page view buys nothing.
		set_transient( 'emsfb_outgoing_ip_hint', '' !== $ip ? $ip : 'none', DAY_IN_SECONDS );

		return $ip;
	}

	/**
	 * Tell the site owner once that add-on downloads are being blocked.
	 *
	 * Rate limited to one message per day: the failure repeats on every
	 * retry, and a mailbox full of identical warnings gets filtered out
	 * exactly when it matters.
	 *
	 * @param  string $server_label Host that refused.
	 * @param  string $addon_key    Add-on that could not be installed.
	 * @return bool                 Whether a message was sent.
	 */
	public function notify_admin_addon_blocked_efb( $server_label = '', $addon_key = '' ) {
		if ( get_transient( 'emsfb_addon_blocked_notice_sent' ) ) {
			return false;
		}
		set_transient( 'emsfb_addon_blocked_notice_sent', 1, DAY_IN_SECONDS );

		$settings = get_setting_Emsfb( 'decoded' );
		if ( function_exists( 'emsfb_is_email_sending_enabled_efb' ) && ! emsfb_is_email_sending_enabled_efb( $settings ) ) {
			return false;
		}

		$to = is_object( $settings ) && ! empty( $settings->emailSupporter ) ? $settings->emailSupporter : get_option( 'admin_email' );
		if ( empty( $to ) ) {
			return false;
		}

		$subject = esc_html__( 'Easy Form Builder: add-on downloads are being blocked', 'easy-form-builder' );
		$body    = '<p>' . $this->addon_offline_hint_efb( $server_label ) . '</p>';
		if ( '' !== $addon_key ) {
			// get_addon_recovery_label_efb() is the plugin's only add-on name
			// map. This used to call a get_addon_display_names_efb() that has
			// never existed, so the first 401/403/406/429 from a download
			// server ended the install request in a fatal: the visitor saw
			// "something went wrong, Code:500" instead of the offline route
			// hint, and the once-a-day transient above was already set, so the
			// next attempt looked fine and the failure read as intermittent.
			$name  = $this->get_addon_recovery_label_efb( $addon_key );
			$body .= '<p>' . sprintf(
				/* translators: %s: add-on name */
				esc_html__( 'Add-on affected: %s', 'easy-form-builder' ),
				esc_html( $name )
			) . '</p>';
		}

		return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Hosts an add-on archive may be downloaded from.
	 *
	 * Derived from the endpoint list rather than hardcoded, so a mirror can
	 * never be reachable for the API but rejected for its own archives.
	 *
	 * @return string[] Lowercased hosts, each with and without a www prefix.
	 */
	public function addon_download_allowed_hosts_efb() {
		$hosts = array();

		$domains   = $this->addon_api_domains_efb();
		$candidates = isset( $domains['endpoints'] ) ? (array) $domains['endpoints'] : array();
		// The Iranian domain stays listed even for non-Persian locales: an
		// archive link can legitimately point there for a site that switched
		// locale after installing.
		$candidates[] = self::EMSFB_ADDON_IR_DOMAIN;
		$candidates[] = EMSFB_SERVER_URL;

		foreach ( $candidates as $candidate ) {
			$host = strtolower( (string) wp_parse_url( (string) $candidate, PHP_URL_HOST ) );
			if ( '' === $host ) {
				continue;
			}
			$hosts[] = $host;
			$hosts[] = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : 'www.' . $host;
		}

		return array_values( array_unique( array_filter( $hosts ) ) );
	}

	/**
	 * Transient key holding the "did not answer" verdict for one domain.
	 *
	 * @param  string $domain
	 * @return string
	 */
	public function addon_api_down_key_efb( $domain ) {
		return 'emsfb_addon_api_down_' . md5( untrailingslashit( (string) $domain ) );
	}

	/**
	 * Record that a domain failed to answer, so the next run can skip it.
	 *
	 * Any endpoint except the first one may be demoted. The first is never
	 * marked down: with every endpoint demoted the plugin would have nowhere
	 * left to ask, and a primary that is genuinely failing is better retried
	 * than removed.
	 *
	 * @param  string $domain
	 * @return void
	 */
	public function addon_api_mark_down_efb( $domain ) {
		$domain = untrailingslashit( (string) $domain );
		if ( '' === $domain || untrailingslashit( EMSFB_SERVER_URL ) === $domain ) {
			return;
		}
		set_transient( $this->addon_api_down_key_efb( $domain ), 'down', self::EMSFB_ADDON_DOWN_TTL );
		// The Add-ons page reads the same verdict, so its cached choice has to go too.
		delete_transient( 'emsfb_addons_fa_domain' );
		delete_transient( 'emsfb_addons_fa_catalogue_domain' );
	}

	/**
	 * Record that a domain answered, clearing any stored verdict against it.
	 *
	 * @param  string $domain
	 * @return void
	 */
	public function addon_api_mark_up_efb( $domain ) {
		$domain = untrailingslashit( (string) $domain );
		if ( '' === $domain ) {
			return;
		}
		delete_transient( $this->addon_api_down_key_efb( $domain ) );
	}
	/**
	 * Timeout and attempt budget for one add-on download.
	 *
	 * Isolated because it is the arithmetic that decides how long a visitor can
	 * be held: inline repairs get one short attempt, background repairs get the
	 * full retry ladder.
	 *
	 * max_endpoints is the ceiling that keeps this honest now that the list can
	 * hold three hosts: without it an inline repair would multiply its timeout
	 * by the number of endpoints and hold a visitor for the better part of
	 * half a minute. Background repairs have nobody waiting, so they may walk
	 * the whole list.
	 *
	 * @param  bool $is_persian_locale Persian sites retry against a fallback domain.
	 * @return array{timeout:int, attempts:int, max_endpoints:int}
	 */
	public function addon_request_plan_efb($is_persian_locale = false){
		if($this->addon_inline_mode_efb){
			return array(
				'timeout'       => min(5, max(3, (int) $this->addon_request_timeout_efb)),
				'attempts'      => 1,
				'max_endpoints' => 2,
			);
		}

		return array(
			'timeout'       => max(3, (int) $this->addon_request_timeout_efb),
			'attempts'      => $is_persian_locale ? 3 : 1,
			'max_endpoints' => 3,
		);
	}

	/**
	 * Timeout, in seconds, for one add-on download request.
	 * Lowered while repairing inline so a visitor is never held for long.
	 *
	 * @var int
	 */
	public $addon_request_timeout_efb = 15;

	/**
	 * Repairing inside a visitor request: one attempt per add-on, no retries.
	 *
	 * @var bool
	 */
	public $addon_inline_mode_efb = false;

	/**
	 * Queue a background add-on recovery.
	 *
	 * Safe to call from a public request: it performs no network, filesystem
	 * or mail work, and is rate limited so a burst of concurrent visitors
	 * schedules a single job.
	 *
	 * @param  array $context Diagnostic context (form_id, addon, source).
	 * @return array          ['queued' => bool, 'reason' => string]
	 */
	public function queue_addon_recovery_efb($context = array()){
		// A previous run already exhausted its attempts; wait out the backoff
		// instead of hammering the server on every page view. This transient
		// was written before but never read, so recovery retried endlessly.
		if(get_transient('emsfb_addons_dl_backoff')){
			return array('queued' => false, 'reason' => 'backoff');
		}

		if(get_transient('emsfb_addons_renew_backoff')){
			return array('queued' => false, 'reason' => 'renew_backoff');
		}

		/* The counter is a ceiling *within* the backoff window, not a permanent
		 * give-up. Once the day-long backoff above has expired, a site whose host
		 * or network has since been fixed gets a fresh set of attempts instead of
		 * staying broken until an administrator happens to log in. */
		if((int) get_option('emsfb_addons_dl_failures', 0) >= 3){
			delete_option('emsfb_addons_dl_failures');
		}

		// One job per 5 minutes, however many visitors hit the form.
		if(get_transient('emsfb_addon_recovery_lock')){
			return array('queued' => false, 'reason' => 'already_queued');
		}

		if(!function_exists('wp_schedule_single_event')){
			return array('queued' => false, 'reason' => 'cron_unavailable');
		}

		// Scheduling an event nothing will ever execute would leave the caller
		// promising a repair that cannot happen, and any auto-reload built on
		// that promise would loop the visitor forever.
		if(!$this->is_cron_available_efb()){
			return array('queued' => false, 'reason' => 'cron_unavailable');
		}

		set_transient('emsfb_addon_recovery_lock', 1, 5 * MINUTE_IN_SECONDS);

		$context = is_array($context) ? $context : array();
		$context['attempt']    = 0;
		$context['queued_at']  = time();
		$context['cron_disabled'] = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
		$context['queued_url'] = isset($_SERVER['REQUEST_URI'])
			? esc_url_raw(home_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))))
			: home_url();

		// The rich context lives in the option, not in the cron arguments: WP-Cron
		// detects duplicate events by hook + arguments, and a timestamp or URL in
		// there would make every event unique and defeat that protection.
		update_option('emsfb_addon_recovery_pending', $context, false);
		wp_schedule_single_event(time() + 5, self::ADDON_RECOVERY_EVENT_EFB, array(array('attempt' => 0)));

		return array('queued' => true, 'reason' => 'scheduled');
	}

	/**
	 * Cron callback: try to repair the installation, then escalate.
	 *
	 * Retries on its own schedule and only reports to the site owner once
	 * every attempt has been used, so a transient network problem is fixed
	 * silently and a real problem is explained precisely.
	 *
	 * @param array $context Context handed over by queue_addon_recovery_efb().
	 */
	public function run_addon_recovery_event_efb($context = array()){
		/* Proof that scheduled events really execute on this site. is_cron_available_efb()
		 * reads it to decide whether deferring is safe when WP-Cron is switched off. */
		update_option('emsfb_cron_last_run', time(), false);

		$context = is_array($context) ? $context : array();
		$attempt = isset($context['attempt']) ? (int) $context['attempt'] : 0;
		$last    = $attempt >= (count(self::ADDON_RECOVERY_DELAYS_EFB) - 1);

		// Nothing to do: the files came back by other means (a manual recovery
		// or a re-install) while this job was waiting in the queue.
		$health = $this->get_addon_local_health_efb();
		if(empty($health['missing'])){
			$this->clear_addon_recovery_state_efb();
			return;
		}

		// Suppress the report until the final attempt. Reset in finally so a fatal
		// inside the downloader cannot leave reporting muted for later runs.
		$this->suppress_addon_report_efb = !$last;
		try {
			$details = $this->download_all_addons_efb(true);
		} finally {
			$this->suppress_addon_report_efb = false;
		}

		$missing_after = $this->get_addon_local_health_efb();
		if(empty($missing_after['missing'])){
			$this->clear_addon_recovery_state_efb();
			return;
		}

		if($last){
			// download_all_addons_efb() has already emailed the detailed report.
			delete_transient('emsfb_addon_recovery_lock');
			update_option('emsfb_addon_recovery_result', is_array($details) ? $details : array(), false);
			return;
		}

		$next = $attempt + 1;
		$delay = self::ADDON_RECOVERY_DELAYS_EFB[$next] * MINUTE_IN_SECONDS;

		$pending = get_option('emsfb_addon_recovery_pending', array());
		$pending = is_array($pending) ? $pending : array();
		$pending['attempt'] = $next;

		// Keep the lock alive across the wait so page views do not queue a
		// duplicate job while this one is still retrying.
		set_transient('emsfb_addon_recovery_lock', 1, $delay + (5 * MINUTE_IN_SECONDS));
		update_option('emsfb_addon_recovery_pending', $pending, false);
		wp_schedule_single_event(time() + $delay, self::ADDON_RECOVERY_EVENT_EFB, array(array('attempt' => $next)));
	}

	/** Clear every flag the recovery cycle sets once the files are healthy. */
	public function clear_addon_recovery_state_efb(){
		delete_transient('emsfb_addon_recovery_lock');
		delete_transient('emsfb_addons_dl_backoff');
		delete_option('emsfb_addons_dl_failures');
		delete_option('emsfb_addon_recovery_pending');
	}

	/**
	 * Build the diagnostic report emailed to the site owner.
	 *
	 * Says what is missing, what was tried, what the server or host answered,
	 * and what to do next — a generic "report problem" line leaves the owner
	 * with no way to act.
	 *
	 * @param  array  $details  Result array from download_all_addons_efb(true).
	 * @param  string $fallback Last error message seen during the run.
	 * @return string           HTML email body.
	 */
	public function build_addon_recovery_report_efb($details, $fallback = ''){
		$details = is_array($details) ? $details : array();
		$missing = isset($details['missing']) && is_array($details['missing']) ? $details['missing'] : array();
		$errors  = isset($details['errors'])  && is_array($details['errors'])  ? $details['errors']  : array();

		$rows = '';
		foreach($missing as $key){
			$name   = $this->get_addon_recovery_label_efb($key);
			$reason = isset($errors[$key]) ? $errors[$key] : $fallback;
			if($reason === '' || $reason === null){
				$reason = esc_html__('No response was recorded for this add-on.', 'easy-form-builder');
			}
			$rows .= '<tr><td style="padding:6px 10px;border:1px solid #ddd;"><strong>' . esc_html($name) . '</strong><br><small>' . esc_html($key) . '</small></td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd;">' . esc_html(wp_strip_all_tags((string) $reason)) . '</td></tr>';
		}

		if($rows === ''){
			$rows = '<tr><td colspan="2" style="padding:6px 10px;border:1px solid #ddd;">'
				. esc_html(wp_strip_all_tags((string) $fallback)) . '</td></tr>';
		}

		$access  = function_exists('emsfb_get_file_access_status_efb') ? emsfb_get_file_access_status_efb() : null;
		$fs_ok   = is_array($access) && !empty($access['status']);
		$fs_note = $fs_ok
			? esc_html__('Writable', 'easy-form-builder')
			: esc_html__('Not writable — the add-on folder cannot be created or extracted into.', 'easy-form-builder');

		$blocked = (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL)
			? esc_html__('Blocked by WP_HTTP_BLOCK_EXTERNAL — outgoing requests are disabled on this site.', 'easy-form-builder')
			: esc_html__('Allowed', 'easy-form-builder');

		$pending = get_option('emsfb_addon_recovery_pending', array());
		$origin  = is_array($pending) && !empty($pending['queued_url']) ? $pending['queued_url'] : home_url();
		$form_id = is_array($pending) && !empty($pending['form_id']) ? (int) $pending['form_id'] : 0;

		/* With WP-Cron switched off, the background retries only run if a real
		 * server cron calls wp-cron.php. Saying so turns "it never recovered"
		 * into something the site owner can actually check. */
		$cron_note = (is_array($pending) && !empty($pending['cron_disabled']))
			? esc_html__('DISABLE_WP_CRON is on — background retries only run if a server cron task calls wp-cron.php.', 'easy-form-builder')
			: esc_html__('Enabled', 'easy-form-builder');

		$facts = array(
			esc_html__('Site', 'easy-form-builder')            => get_bloginfo('name') . ' — ' . home_url(),
			esc_html__('Page that needed it', 'easy-form-builder') => $origin,
			esc_html__('Form', 'easy-form-builder')            => $form_id > 0 ? '#' . $form_id : '—',
			esc_html__('Plugin version', 'easy-form-builder')  => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '—',
			esc_html__('WordPress', 'easy-form-builder')       => get_bloginfo('version'),
			'PHP'                                              => PHP_VERSION,
			esc_html__('Add-on folder', 'easy-form-builder')   => $fs_note,
			esc_html__('Outgoing requests', 'easy-form-builder') => $blocked,
			esc_html__('WP-Cron', 'easy-form-builder')         => $cron_note,
			esc_html__('Attempts made', 'easy-form-builder')   => (string) count(self::ADDON_RECOVERY_DELAYS_EFB),
		);

		$fact_rows = '';
		foreach($facts as $label => $value){
			$fact_rows .= '<tr><td style="padding:4px 10px;border:1px solid #ddd;width:38%;">' . esc_html($label) . '</td>'
				. '<td style="padding:4px 10px;border:1px solid #ddd;">' . esc_html(wp_strip_all_tags((string) $value)) . '</td></tr>';
		}

		$admin_url = admin_url('admin.php?page=Emsfb');

		return '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.7;">'
			. '<p>' . esc_html__('Easy Form Builder could not reinstall its add-on files automatically. Forms that rely on those add-ons are showing a temporary message to visitors until this is resolved.', 'easy-form-builder') . '</p>'

			. '<h3 style="margin:18px 0 6px;">' . esc_html__('What is missing', 'easy-form-builder') . '</h3>'
			. '<table style="border-collapse:collapse;width:100%;">' . $rows . '</table>'

			. '<h3 style="margin:18px 0 6px;">' . esc_html__('Diagnostics', 'easy-form-builder') . '</h3>'
			. '<table style="border-collapse:collapse;width:100%;">' . $fact_rows . '</table>'

			. '<h3 style="margin:18px 0 6px;">' . esc_html__('What to do next', 'easy-form-builder') . '</h3>'
			. '<ol>'
			. '<li>' . esc_html__('Open Easy Form Builder in your dashboard and use the Recover add-ons button.', 'easy-form-builder') . ' <a href="' . esc_url($admin_url) . '">' . esc_html($admin_url) . '</a></li>'
			. '<li>' . esc_html__('If the add-on folder is not writable, ask your host to allow writing to the plugin folder.', 'easy-form-builder') . '</li>'
			. '<li>' . esc_html__('If outgoing requests are blocked, ask your host to allow connections to the Easy Form Builder update server.', 'easy-form-builder') . '</li>'
			. '<li>' . esc_html__('If your Pro subscription has expired, renew it to restore the Pro add-ons.', 'easy-form-builder') . '</li>'
			. '</ol>'

			. '<p><a href="https://whitestudio.team/support/" target="_blank">' . esc_html__('Please kindly report the following issue to the Easy Form Builder team.', 'easy-form-builder') . '</a></p>'
			. '<p>' . esc_html__('Easy Form Builder', 'easy-form-builder') . '</p>'
			. '<p><a href="' . esc_url(home_url()) . '" target="_blank">' . esc_html__('Sent by:', 'easy-form-builder') . ' ' . esc_html(get_bloginfo('name')) . '</a></p>'
			. '</div>';
	}

	/**
	 * Public "we are updating" placeholder shown in place of a form whose
	 * add-on files are missing. Reuses the existing wait-message string so no
	 * new translatable phrase is introduced.
	 *
	 * @return string HTML.
	 */
	public function addon_wait_message_public_efb($retry_after_seconds = 25){
		return "<div id='body_efb' class='efb card-public row pb-3 efb px-2' style='color: #9F6000; background-color: #FEEFB3; padding: 5px 10px;'>"
			. "<div class='efb text-center my-5'><h2 style='text-align: center;'></h2>"
			. "<h3 class='efb warning text-center text-darkb fs-4'>"
			. esc_html__('We have made some updates. Please wait a few minutes before trying again.', 'easy-form-builder')
			. "</h3><p class='efb fs-5 text-center my-1 text-pinkEfb' style='text-align: center;'><p>"
			. $this->addon_reload_script_efb($retry_after_seconds)
			. "</div></div>";
	}

	public function flush_addon_wait_message_efb(){
		if (function_exists('wp_ob_end_flush_all')) {
			wp_ob_end_flush_all();
		} else {
			while (ob_get_level() > 0) {
				ob_end_flush();
			}
		}
		flush();
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
			/* translators: Lead-in sentence used when advertising one of the plugin's free features */
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

		// Some hardened hosts disable OpenSSL. Generate the tracking suffix via
		// the shared capability-aware helper so confirmation-code creation keeps
		// working without invoking a disabled PHP function.
		$sid = wp_date("ymdHis") . emsfb_generate_token_efb(9);
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

		// Sessions are per visitor, never per IP. Reusing an existing row keyed on
		// (fid, uid, ip, active) collapsed every visitor sharing one resolved
		// address - corporate/mobile NAT, or any reverse proxy that leaves
		// REMOTE_ADDR as the proxy - onto a single session row, so the first
		// person to submit flipped active=0 and locked out everyone behind that
		// address. v3 always inserted a fresh row; this restores that. Volume is
		// handled by the sid/lookup indexes and the daily prune, not by
		// conflating unrelated visitors.
		$sql = $wpdb->prepare(
			"INSERT INTO {$table_name} (`sid`, `fid`, `type_`, `status`, `ip`, `os`, `browser`, `uid`, `tc`, `active`, `date`, `read_date`)
			VALUES (%s, %d, %d, %s, %s, %s, %s, %d, %s, %d, %s, %s)",
			$sid, $fid, $type, $status, $ip, $os, $browser, $uid, $tc, 1, $date_now, $date_limit
		);

		$state = $wpdb->query($sql);
		return $sid;
	}

    public function efb_code_validate_update($sid ,$status ,$tc ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emsfb_stts_';
		$active = 0;
		$read_date = wp_date('Y-m-d H:i:s');
		if($status=="rsp" || $status=="ppay")  $active =1;

		$sql = $wpdb->prepare(
			"UPDATE `{$table_name}` SET status = %s, active = %d, read_date = %s, tc = %s WHERE sid = %s AND active = 1",
			$status, $active, $read_date, $tc, $sid
		);
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
			// A caching plugin serves the sid that was minted when the page copy
			// was generated, so on a cached page this row is routinely past
			// read_date (sessionDuration defaults to a single day) while the
			// form itself is perfectly legitimate. Rejecting it here is what
			// made every later visitor unsubmittable. Accept a plain 'visit'
			// row that is still inside the absolute cap and slide its window
			// forward, exactly as efb_code_touch_session() would.
			if ($this->efb_revive_visit_session($sid, $fid)) {
				return true;
			}

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

	/**
	 * Re-open an expired but still-recent 'visit' session.
	 *
	 * Only ever touches rows whose status is 'visit' - the single-use auth
	 * statuses (regis/login/reset/recov/logou) keep their one-shot semantics and
	 * are handled by the caller. Bounded by efb_session_absolute_max_days so an
	 * abandoned session cannot be revived indefinitely.
	 *
	 * @param string $sid Session id.
	 * @param int    $fid Form id (0 = any form).
	 * @return bool True when a session was revived.
	 */
	private function efb_revive_visit_session($sid, $fid) {
		global $wpdb;

		if ('' === (string) $sid) {
			return false;
		}

		$table_name = $wpdb->prefix . 'emsfb_stts_';
		$fid        = intval($fid);

		$max_days = (int) apply_filters('efb_session_absolute_max_days', 7);
		if ($max_days < 1) {
			$max_days = 1;
		}
		$cap_cutoff = wp_date('Y-m-d H:i:s', strtotime("-{$max_days} days"));

		if (empty($fid)) {
			$row = $wpdb->get_row($wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE sid = %s AND status = %s AND active = 1 AND `date` > %s ORDER BY `date` DESC LIMIT 1",
				$sid, 'visit', $cap_cutoff
			), ARRAY_A);
		} else {
			$row = $wpdb->get_row($wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE sid = %s AND fid = %d AND status = %s AND active = 1 AND `date` > %s ORDER BY `date` DESC LIMIT 1",
				$sid, $fid, 'visit', $cap_cutoff
			), ARRAY_A);
		}

		if (empty($row)) {
			return false;
		}

		$settings        = get_setting_Emsfb();
		$sessionDuration = isset($settings->sessionDuration) && is_numeric($settings->sessionDuration) ? intval($settings->sessionDuration) : 1;
		if ($sessionDuration < 1) {
			$sessionDuration = 1;
		}

		$wpdb->update(
			$table_name,
			array('read_date' => wp_date('Y-m-d H:i:s', strtotime("+{$sessionDuration} days"))),
			array('id' => (int) $row['id']),
			array('%s'),
			array('%d')
		);

		return true;
	}

    /**
     * Read-only twin of efb_code_validate_select(): reports whether the sid
     * belongs to a live form session (or a not-yet-consumed one-shot auth
     * session) without ever mutating it.
     *
     * The consuming variant flips a single-use regis/login/reset/recov/logou
     * row to 'inact' the first time it accepts it. Guards that only need to
     * *observe* liveness (e.g. Human Shield, which runs before core on the same
     * request and can even run on a mere field focus) must not burn that
     * one-shot acceptance, so they call this instead.
     *
     * @param string $sid Session id.
     * @param int    $fid Form id (0 = any form for this session).
     * @return bool
     */
    public function efb_code_validate_check($sid, $fid) {
		global $wpdb;

		$fid = intval($fid);
		$table_name = $wpdb->prefix . 'emsfb_stts_';
		$date_now = wp_date('Y-m-d H:i:s');

		if(empty($fid) || $fid == 0) {
			$query = $wpdb->prepare("SELECT status FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 ORDER BY date DESC LIMIT 1", $sid, $date_now);
		} else {
			$query = $wpdb->prepare("SELECT status FROM {$table_name} WHERE sid = %s AND read_date > %s AND active = 1 AND fid = %s ORDER BY date DESC LIMIT 1", $sid, $date_now, $fid);
		}

		if(!empty($wpdb->get_row($query, ARRAY_A))){
			return true;
		}

		// Fallback: a one-shot auth session may still be pending. Mirror the
		// acceptance rule of efb_code_validate_select() but never consume it.
		$query = $wpdb->prepare("SELECT status FROM {$table_name} WHERE sid = %s  AND fid = %s ORDER BY date DESC LIMIT 1", $sid, $fid);
		$result = $wpdb->get_row($query, ARRAY_A);
		$valid = ['regis','login','reset','recov','logou'];

		return !empty($result) && in_array($result['status'], $valid, true);
    }

	/**
	 * Slides a form session's expiry forward so a legitimately open form keeps a
	 * valid sid (used by the sid-fallback auth path and by nonce/refresh) instead
	 * of failing once the original session window elapses. Bounded by an absolute
	 * cap measured from the row's creation date, so an abandoned session can never
	 * live forever. Only ever extends an already-active, not-yet-capped row.
	 */
	public function efb_code_touch_session( $sid, $fid = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emsfb_stts_';

		$settings        = get_setting_Emsfb();
		$sessionDuration = isset( $settings->sessionDuration ) && is_numeric( $settings->sessionDuration ) ? intval( $settings->sessionDuration ) : 1;
		if ( $sessionDuration < 1 ) {
			$sessionDuration = 1;
		}
		$new_read = wp_date( 'Y-m-d H:i:s', strtotime( "+{$sessionDuration} days" ) );

		$max_days = (int) apply_filters( 'efb_session_absolute_max_days', 7 );
		if ( $max_days < 1 ) {
			$max_days = 1;
		}
		$cap_cutoff = wp_date( 'Y-m-d H:i:s', strtotime( "-{$max_days} days" ) );

		return $wpdb->query( $wpdb->prepare(
			"UPDATE `{$table_name}` SET read_date = %s WHERE sid = %s AND active = 1 AND `date` > %s",
			$new_read,
			$sid,
			$cap_cutoff
		) );
	}

	public function getVisitorOS() {

		// Defaults to '' rather than null: there is no user agent under WP-CLI or
		// WP-Cron, and PHP 8.1+ deprecates passing null to strtolower().
		$_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
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

	    // See getVisitorOS(): '' rather than null, for WP-CLI and WP-Cron.
	    $_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
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

		// Human Shield (or any other guard) may veto paid side effects here.
		$efb_shield_sms_context = array(
			'channel'       => 'sms',
			'event'         => $state,
			'form_id'       => $form_id,
			'tracking_code' => $tracking_code,
			'recipients'    => $numbers,
			'source'        => 'sms_ready_for_send_efb',
		);
		if ( ! apply_filters( 'efb_shield_allow_side_effect', true, $efb_shield_sms_context ) ) {
			return false;
		}

		$sent_any = false;
		if($state=="fform"){
			if(!empty($numbers[1]) && $recived_your_message){
				$resukt_send_message = $smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
				if($resukt_send_message != false) $sent_any = true;
			}
			if(!empty($numbers[0]) && $new_message){
				$new_message = str_replace($page_url."?track=".$tracking_code,$page_url."?track=".$tracking_code.'&user=admin',$new_message);
				$resukt_send_message = $smssendefb->send_sms_efb($numbers[0],$new_message,$form_id,$severType);
				if($resukt_send_message != false) $sent_any = true;
			}
			return $sent_any;
		}else if($state=="resppa"){
			if(!empty($numbers[1]) && $recived_your_message){
				$resukt_send_message =  $smssendefb->send_sms_efb($numbers[1],$recived_your_message,$form_id,$severType);
				if($resukt_send_message != false) $sent_any = true;
			}
			if(!empty($numbers[0]) && $news_response){
				$news_response = str_replace($page_url."?track=".$tracking_code, $page_url."?track=".$tracking_code.'&user=admin',$news_response);
				$resukt_send_message =  $smssendefb->send_sms_efb($numbers[0],$news_response,$form_id,$severType);
				if($resukt_send_message != false) $sent_any = true;
			}
			return $sent_any;
		}else if ($state=="respp" || $state=="respadmin"){
			if(!empty($numbers[1]) && $news_response){
				$resukt_send_message = $smssendefb->send_sms_efb($numbers[1],$news_response,$form_id,$severType);
				if($resukt_send_message != false) $sent_any = true;
			}
			return $sent_any;
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

	/**
	 * Add missing add-on flags to legacy settings without losing their option
	 * based activation state.
	 *
	 * @param mixed $settings Add-on settings object from an older plugin version.
	 * @return \stdClass
	 */
	public function normalize_addon_settings_efb($settings) {
		if (!is_object($settings)) {
			$settings = new \stdClass();
		}

		// Older settings rows do not contain every later add-on flag.  Keep the
		// persisted state shape in sync after an update so a bundled add-on is not
		// installed successfully but omitted from the editor/menu state.
		foreach ( $this->get_all_addon_keys_efb() as $addon_key ) {
			if ( ! property_exists( $settings, $addon_key ) ) {
				$legacy_value = get_option( 'emsfb_addon_' . $addon_key, false );
				$settings->{$addon_key} = $legacy_value !== false && absint( $legacy_value ) >= 1 ? 1 : 0;
			}
		}

		return $settings;
	}

	public function setting_version_efb_update($st ,$pro, $skip_redirect = false){
		global $wpdb;

		if($st=='null' || !is_object($st)){
			$st=get_setting_Emsfb();
		}
		$previous_settings = is_object( $st ) ? wp_json_encode( $st ) : '';
		$st = $this->normalize_addon_settings_efb($st);
		$st->efb_version=EMSFB_PLUGIN_VERSION;

		// Create used to write the settings option on every page load. Only write
		// when migration/version data actually changed.
		if ( $previous_settings !== wp_json_encode( $st ) ) {
			$this->set_setting_Emsfb($st, isset($st->emailSupporter) ? $st->emailSupporter : '');
		}

		if($pro == true || $pro ==1){

			$is_pro = (int) get_option('emsfb_pro' ,2);
			if($is_pro==3){ return true; }

			// Recovery is deliberately not scheduled here. The specific admin/form
			// entry point performs it synchronously only when files are missing.

			if($skip_redirect === true) {
				return true;
			}

			$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : null;
		    if(isset($request_uri)==true && strpos($request_uri, 'Emsfb') === false ){
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
		$details['success'] = true;
		return $return_details ? $details : true;
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
		if(emsfb_is_email_sending_enabled_efb($settings)) $this->send_email_state_new('reportProblem' ,'reportProblem' ,$str,0,"reportProblem",'null','null');
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

		if(emsfb_is_email_sending_enabled_efb($settings)) $this->send_email_state_new($to ,$subject ,$message,0,"cache_plugins_noti",'null','null');

		return true;
	}

	/**
	 * Farsi (fa_IR) sites run the licence in "offline" mode.
	 *
	 * Because of the recurring Internet restrictions in Iran the licensing
	 * server (whitestudio.team) is frequently unreachable and a failed or
	 * negative response would wrongly deactivate an otherwise legitimately
	 * licensed site. For these sites we therefore trust the locally validated
	 * activation code (which embeds md5(domain)) and never contact the server
	 * for validation.
	 */
	public function is_farsi_offline_license_efb() {
		return get_locale() === 'fa_IR';
	}

	/**
	 * Low-volume licence log used to monitor the 4.1.x roll-out on Farsi
	 * sites (readable in wp-content/debug.log). Pass a $throttle_key to emit
	 * the message at most once every 12 hours so admin page loads are not
	 * flooded. The plugin version is included so the log can be correlated
	 * with the update.
	 */
	public function emsfb_pro_log($message, $throttle_key = '') {
		// Gated so a production site stays quiet; without a write here the
		// docblock above was false and a licence question on a Farsi site left
		// nothing to read anywhere.
		$enabled = (defined('EMSFB_ADDON_DEBUG') && EMSFB_ADDON_DEBUG)
			|| (defined('WP_DEBUG') && WP_DEBUG);
		if (!$enabled) {
			return;
		}

		if ($throttle_key !== '') {
			$tk = 'emsfb_pro_log_' . md5($throttle_key);
			if (get_transient($tk)) {
				return;
			}
			set_transient($tk, 1, 12 * HOUR_IN_SECONDS);
		}
		$version = defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '?';

		$available = function_exists('emsfb_is_php_function_available_efb')
			? emsfb_is_php_function_available_efb('error_log')
			: function_exists('error_log');
		if ($available) {
			error_log('[EFB Pro ' . $version . '] ' . $message);
		}
	}

	public function make_post_request_efb( $ac) {
		$url = EMSFB_LICENSE_SERVER_URL . '/wp-json/wl/v1/pro/key';

		$_http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';

		// Farsi (Iran) sites: never contact the remote licensing server. The
		// Iran network restrictions make it unreliable and a failed/negative
		// response would wrongly deactivate a legitimately licensed site, so
		// we validate the activation code locally instead (it embeds md5(domain)).
		if ($this->is_farsi_offline_license_efb()) {
			// Matched through the shared helper rather than against
			// $_SERVER['HTTP_HOST'] directly: that header is absent under WP-CLI
			// and WP-Cron, where md5('') matches nothing and a valid code is
			// declared notExists. Revalidation now runs from the
			// emsfb_revalidate_license cron event, so on a site driven by a real
			// server cron this path is precisely where the check happens - and it
			// would have suspended Pro every week on a perfectly good licence.
			$valid = $this->activation_code_matches_domain_efb($ac);
			$this->emsfb_pro_log(
				'make_post_request_efb: farsi offline mode - local activation-code check ' . ($valid ? 'PASSED' : 'FAILED') . ' for host(s) "' . implode(', ', $this->license_domain_candidates_efb()) . '"',
				$valid ? 'farsi_local_ok' : ''
			);
			return $valid
				? (object) ['r' => true, 'state' => 'active', 'pakcage' => 1]
				: (object) ['r' => false, 'state' => 'notExists'];
		}

		$connected = wp_remote_post('https://www.whitestudio.team', array('timeout' => 2));
		if (is_wp_error($connected)) {
			// The server was never reached, so nothing here is authoritative. If
			// the activation code still matches this domain we keep running;
			// otherwise we report a transport error rather than inventing a
			// "notExists" verdict that would revoke a valid licence.
			if ($this->activation_code_matches_domain_efb($ac)) {
				return (object)['r' => true, 'state' => 'active', 'pakcage' => 1];
			}
			return (object)['transport_error' => true, 'reason' => 'probe_failed'];
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
			return (object)['transport_error' => true, 'reason' => 'request_failed'];
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			// 500/502, a maintenance page, a WAF challenge - the licence server
			// did not answer the question, so it must not be read as a "no".
			return (object)['transport_error' => true, 'reason' => 'http_' . $code];
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (!is_object($data) || !isset($data->state)) {
			return (object)['transport_error' => true, 'reason' => 'unparsable_response'];
		}

		return $data;
	}

	/**
	 * Whether an activation code was minted for this site.
	 *
	 * The code embeds md5 of the bare domain. The host is taken from the stored
	 * site URL first: $_SERVER['HTTP_HOST'] is absent under WP-CLI and WP-Cron
	 * (making the comparison fail against md5 of an empty string, which used to
	 * switch Pro off during automated updates) and it is attacker-controlled on
	 * many stacks. HTTP_HOST is still accepted as a fallback so codes minted
	 * under a different host spelling keep validating.
	 *
	 * @param string $ac Activation code.
	 * @return bool
	 */
	public function activation_code_matches_domain_efb($ac) {
		$expected = explode('@', (string) $ac)[0];
		if ('' === $expected) {
			return false;
		}

		foreach ($this->license_domain_candidates_efb() as $host) {
			if (md5($host) === $expected) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every spelling of this site's domain an activation code may have been
	 * minted against.
	 *
	 * @return array<int, string>
	 */
	public function license_domain_candidates_efb() {
		$hosts = array();

		foreach (array('siteurl', 'home') as $option) {
			$host = wp_parse_url((string) get_option($option), PHP_URL_HOST);
			if (is_string($host) && '' !== $host) {
				$hosts[] = $host;
			}
		}

		if (!empty($_SERVER['HTTP_HOST'])) {
			$hosts[] = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
		}

		$candidates = array();
		foreach ($hosts as $host) {
			$host = strtolower(trim($host));
			if ('' === $host) {
				continue;
			}
			$candidates[] = $host;
			$candidates[] = str_replace('www.', '', $host);
		}

		return array_values(array_unique(array_filter($candidates)));
	}

	public function update_pro_status_efb($code) {
		update_option('emsfb_pro', 1);
		update_option('emsfb_pro_activeCode', $code);
		$json = $this->make_post_request_efb($code);

		// A response that never arrived, or one we could not parse, is not a
		// verdict. Treating it as "invalid" used to revoke Pro and erase the
		// customer's activation key on any 500, timeout, WAF challenge or
		// truncated body - permanently, from a single bad round-trip, on a check
		// that runs every week.
		if (isset($json->transport_error) && $json->transport_error) {
			return $this->handle_license_transport_failure_efb($code, isset($json->reason) ? $json->reason : 'unknown');
		}

		// Reaching the server clears any accumulated failure streak, including
		// the "suspension email already sent" marker, so a future outage is
		// announced again instead of passing silently.
		delete_option('emsfb_license_failed_since');
		delete_option('emsfb_license_fail_reason');
		delete_option('emsfb_license_suspend_notified');

		$r = isset($json->r) ? $json->r : false;
		if($r===false && !isset($json->state)) {
			// Shape we do not recognise - still not a verdict.
			return $this->handle_license_transport_failure_efb($code, 'missing_state');
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
			$this->resume_addon_downloads_efb();
			return true;
		}elseif($state=="active") {
			update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s'));
			$this->resume_addon_downloads_efb();
			return true;
		}elseif ($state=="deactive") {
			update_option('emsfb_pro' , 0);
			delete_option('emsfb_pro_ac_date');
			update_option('emsfb_pro_activeCode' ,$code);
			return false;
		}elseif ($state=="notExists") {
			update_option('emsfb_pro', 0);
			delete_option('emsfb_pro_ac_date');
			// The key is kept even here so the customer can still see it and
			// re-activate; only the server's verdict on it is recorded.
			update_option('emsfb_pro_activeCode', $code);
			return false;
		}

		// Unknown state string: do not act on it.
		return $this->handle_license_transport_failure_efb($code, 'unknown_state');
	}

	/**
	 * Decide what to do when the licence server could not give a verdict.
	 *
	 * Keeps the site licensed through outages, and never discards the stored
	 * activation code. Only after a continuous failure streak longer than the
	 * grace period does Pro switch off, and even then the key is preserved so a
	 * single successful check restores everything.
	 *
	 * @param string $code   Activation code.
	 * @param string $reason Diagnostic reason.
	 * @return bool Effective Pro state.
	 */
	private function handle_license_transport_failure_efb($code, $reason) {
		$since = (int) get_option('emsfb_license_failed_since', 0);
		if (!$since) {
			$since = time();
			update_option('emsfb_license_failed_since', $since, false);
		}
		update_option('emsfb_license_fail_reason', (string) $reason, false);

		$grace_days = (int) apply_filters('efb_license_grace_days', 21);
		if ($grace_days < 1) {
			$grace_days = 1;
		}

		$within_grace = (time() - $since) < ($grace_days * DAY_IN_SECONDS);

		$this->emsfb_pro_log(sprintf(
			'update_pro_status_efb: licence server gave no verdict (%s). %s',
			$reason,
			$within_grace ? 'Keeping Pro active within grace period.' : 'Grace period exhausted; Pro suspended (key retained).'
		));

		// The key is never deleted on a non-authoritative outcome.
		update_option('emsfb_pro_activeCode', $code);

		if ($within_grace) {
			update_option('emsfb_pro', 1);
			// Push the next check out a little so a hard outage is not retried
			// on every single request.
			update_option('emsfb_pro_ac_date', date('Y-m-d H:i:s', time() - (5 * DAY_IN_SECONDS)));
			return true;
		}

		// Until now the only sign an administrator got was Pro features quietly
		// disappearing, with nothing in the panel explaining why. Tell both
		// administrators what happened and how to get Pro back.
		$this->notify_license_suspended_efb($code, $reason, $since, $grace_days);

		update_option('emsfb_pro', 0);
		return false;
	}

	/**
	 * Email the site administrators once when a licence outage suspends Pro.
	 *
	 * Keyed to the failure streak that caused the suspension: one email per
	 * streak, so a licence check that keeps failing every few hours cannot fill
	 * the inbox. update_pro_status_efb() drops the marker the moment the licence
	 * server answers again, which re-arms the notice for the next real outage.
	 *
	 * @param string $code       Activation code (masked before it is shown).
	 * @param string $reason     Diagnostic reason of the last failed check.
	 * @param int    $since      Timestamp of the first failed check in this streak.
	 * @param int    $grace_days Grace period that has just run out.
	 * @return bool Whether the message was handed to the mailer.
	 */
	public function notify_license_suspended_efb($code, $reason, $since, $grace_days) {
		$since = (int) $since;
		if ($since > 0 && (int) get_option('emsfb_license_suspend_notified', 0) === $since) {
			return false;
		}

		$settings = get_setting_Emsfb('decoded');
		if (!emsfb_is_email_sending_enabled_efb($settings)) {
			// The site is configured as unable to send email, so nothing would
			// arrive. The marker stays unset: the notice is still owed and goes
			// out on the next failed check once sending is switched on.
			return false;
		}

		$to = array();
		$admin_email = get_option('admin_email');
		if (is_email($admin_email)) {
			$to[] = $admin_email;
		}
		if (is_object($settings) && isset($settings->emailSupporter) && is_email($settings->emailSupporter)) {
			$to[] = $settings->emailSupporter;
		}
		// Both addresses are usually the same one; a duplicate would deliver the
		// same warning twice. Kept to two entries at most because
		// send_email_state_new() reads index 2 of the list as the From address.
		$to = array_values(array_unique($to));
		if (empty($to)) {
			return false;
		}

		update_option('emsfb_license_suspend_notified', $since, false);

		$subject = sprintf(
			/* translators: %s: site name. */
			esc_html__('Action needed: Easy Form Builder Pro is paused on %s', 'easy-form-builder'),
			wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
		);

		$this->emsfb_pro_log('notify_license_suspended_efb: emailing ' . implode(', ', $to) . ' (reason: ' . $reason . ')');

		return (bool) $this->send_email_state_new(
			$to,
			$subject,
			$this->license_suspended_email_body_efb($code, $reason, $since, $grace_days),
			0,
			'licenseSuspended',
			admin_url('admin.php?page=Emsfb&state=setting'),
			'null'
		);
	}

	/**
	 * Body of the "Pro features are paused" email.
	 *
	 * Written for an administrator who has never seen a licence error: what
	 * happened, what is still safe, and the three steps that bring Pro back.
	 * The support diagnostics sit at the bottom, and the activation code is
	 * masked so a forwarded copy cannot leak it.
	 *
	 * @param string $code       Activation code.
	 * @param string $reason     Diagnostic reason of the last failed check.
	 * @param int    $since      Timestamp of the first failed check in this streak.
	 * @param int    $grace_days Grace period that has just run out.
	 * @return string HTML fragment for the plugin's email template.
	 */
	private function license_suspended_email_body_efb($code, $reason, $since, $grace_days) {
		$align   = is_rtl() ? 'right' : 'left';
		$brand   = get_locale() === 'fa_IR' ? 'https://easyformbuilder.ir' : untrailingslashit(EMSFB_SERVER_URL);
		$days    = max(1, (int) floor((time() - (int) $since) / DAY_IN_SECONDS));
		$support = $brand . '/support/';
		$renew   = $brand . '/checkout?renew=' . rawurlencode((string) $code);

		$intro = sprintf(
			/* translators: 1: number of days, 2: site name. */
			esc_html__('Easy Form Builder could not confirm your Pro licence for %1$s days in a row, so the Pro features on %2$s are paused for now.', 'easy-form-builder'),
			'<strong>' . esc_html(number_format_i18n($days)) . '</strong>',
			'<strong>' . esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)) . '</strong>'
		);

		$safe_note = esc_html__('Your forms, entries and settings are untouched, every free feature keeps working, and your activation code is still stored &mdash; nothing was deleted.', 'easy-form-builder');

		$steps = array(
			sprintf(
				/* translators: 1: opening bold tag, 2: closing bold tag. */
				esc_html__('Open %1$sEasy Form Builder &rarr; Settings%2$s and press %1$sSave%2$s. That checks the licence again straight away; if the connection is back, Pro switches on within a minute.', 'easy-form-builder'),
				'<strong>',
				'</strong>'
			),
			sprintf(
				/* translators: %s: licence server address. */
				esc_html__('Still paused? Ask your hosting provider to allow outgoing HTTPS connections to %s &mdash; a firewall, proxy or regional block is the usual cause &mdash; then repeat step 1.', 'easy-form-builder'),
				'<strong>whitestudio.team</strong>'
			),
			sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				esc_html__('If your subscription has expired, or you moved this website to a new domain, %1$srenew or re-activate your licence%2$s and paste the new code into Settings.', 'easy-form-builder'),
				'<a href="' . esc_url($renew) . '" target="_blank" style="color:#202a8d;">',
				'</a>'
			),
		);

		$rows = array(
			esc_html__('Website', 'easy-form-builder')            => esc_html(home_url()),
			esc_html__('Reason', 'easy-form-builder')             => $this->license_failure_reason_text_efb($reason),
			esc_html__('First failed check', 'easy-form-builder') => esc_html(wp_date(get_option('date_format', 'Y-m-d'), (int) $since)),
			esc_html__('Grace period', 'easy-form-builder')       => sprintf(
				/* translators: %s: number of days. */
				esc_html__('%s days', 'easy-form-builder'),
				esc_html(number_format_i18n((int) $grace_days))
			),
			esc_html__('Activation code', 'easy-form-builder')    => esc_html($this->mask_license_code_efb($code)),
			esc_html__('Error code', 'easy-form-builder')         => esc_html((string) $reason) . ' &middot; ' . esc_html('v' . EMSFB_PLUGIN_VERSION),
		);

		$html = '<div style="text-align:' . $align . ';font-size:15px;line-height:1.9;color:#333333;">';
		$html .= '<p style="margin:0 0 14px 0;">' . $intro . '</p>';
		$html .= '<p style="margin:0 0 18px 0;padding:12px 16px;background-color:#f1f5f9;border-radius:8px;color:#334155;">' . $safe_note . '</p>';
		$html .= '<p style="margin:0 0 6px 0;font-weight:700;">' . esc_html__('How to bring Pro back', 'easy-form-builder') . '</p>';
		$html .= '<ol style="margin:0;padding-' . $align . ':20px;">';
		foreach ($steps as $step) {
			$html .= '<li style="margin:0 0 8px 0;">' . $step . '</li>';
		}
		$html .= '</ol>';

		$html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px auto;">
			<tr>
				<td align="center" style="background-color:#202a8d;border-radius:8px;padding:14px 28px;">
					<a href="' . esc_url(admin_url('admin.php?page=Emsfb&state=setting')) . '" target="_blank" style="color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;">'
						. esc_html__('Check my licence now', 'easy-form-builder') .
					'</a>
				</td>
			</tr>
		</table>';

		$html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;font-size:13px;color:#4b5563;">';
		foreach ($rows as $label => $value) {
			$html .= '<tr>'
				. '<td style="padding:6px 10px 6px 0;border-bottom:1px solid #e5e7eb;text-align:' . $align . ';white-space:nowrap;">' . $label . '</td>'
				. '<td style="padding:6px 0;border-bottom:1px solid #e5e7eb;text-align:' . $align . ';">' . $value . '</td>'
				. '</tr>';
		}
		$html .= '</table>';

		$html .= '<p style="margin:16px 0 0 0;font-size:13px;color:#6b7280;">' . sprintf(
			/* translators: 1: opening link tag, 2: closing link tag. */
			esc_html__('Still stuck? Send the details above to %1$sour support team%2$s and we will take it from there.', 'easy-form-builder'),
			'<a href="' . esc_url($support) . '" target="_blank" style="color:#202a8d;">',
			'</a>'
		) . '</p>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Turn a licence failure code into a sentence an administrator can act on.
	 *
	 * Every reason here means "no verdict was received", never "your licence is
	 * invalid", so the wording must not make a customer think they were
	 * rejected.
	 *
	 * @param string $reason Diagnostic reason recorded by the failed check.
	 * @return string
	 */
	private function license_failure_reason_text_efb($reason) {
		$reason = (string) $reason;

		if (strpos($reason, 'http_') === 0) {
			return esc_html__('The licence server answered with an error and could not confirm your licence.', 'easy-form-builder');
		}

		switch ($reason) {
			case 'request_failed':
			case 'probe_failed':
				return esc_html__('Your website could not reach the licence server &mdash; almost always a network, firewall or DNS restriction on the hosting side.', 'easy-form-builder');
			case 'unparsable_response':
			case 'missing_state':
			case 'unknown_state':
				return esc_html__('The licence server&rsquo;s answer arrived damaged, usually because a security plugin, proxy or CDN changed it on the way.', 'easy-form-builder');
		}

		return esc_html__('The licence check could not be completed.', 'easy-form-builder');
	}

	/**
	 * Show enough of the activation code to identify it, never the whole key.
	 *
	 * @param string $code Activation code.
	 * @return string
	 */
	private function mask_license_code_efb($code) {
		$code = (string) $code;
		if (strlen($code) <= 12) {
			return $code === '' ? '&mdash;' : str_repeat('*', strlen($code));
		}

		return substr($code, 0, 6) . str_repeat('*', 6) . substr($code, -4);
	}

	public function weekly_check_pro_efb($activeCode) {
		$ac_date = get_option('emsfb_pro_ac_date');
		$ac_date = strtotime($ac_date);
		$now = strtotime(date('Y-m-d H:i:s'));
		$diff = ($now - $ac_date) / (60 * 60 * 24);
		if ($diff > 7) {

			// update_pro_status_efb() is now the single place that decides what a
			// given outcome means, including the grace period for outages, so its
			// verdict is taken as-is rather than being overridden here.
			return (bool) $this->update_pro_status_efb($activeCode);
		}
		return true;
	}
	private function validated_pro_efb($s) {
		if (!isset($s) || '' === (string) $s) {
			return false;
		}

		// Resolved from the stored site URL, with HTTP_HOST only as a fallback:
		// under WP-CLI and WP-Cron there is no HTTP_HOST, so this used to compare
		// against md5('') and fail, switching Pro off during automated updates.
		foreach ($this->license_domain_candidates_efb() as $host) {
			if (md5($host) === $s) {
				return true;
			}
		}

		return false;
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
				if ($this->is_farsi_offline_license_efb()) {
					// Heartbeat so a Farsi site's Pro status can be confirmed in
					// wp-content/debug.log after the update (throttled to 12h).
					$this->emsfb_pro_log('is_efb_pro: farsi site - local activation code valid, license active (offline mode)', 'farsi_is_pro_ok');
				}
				// Answer from local state only. This runs during public form
				// rendering, and it used to fire the weekly licence round-trip
				// inline - making a real visitor wait for a 2s connectivity probe
				// plus a full API call, and letting that page view revoke the
				// licence. Revalidation now happens on the emsfb_revalidate_license
				// cron event; see cron_check_pro_efb().
				return true;
			}
			// Local code does not match this domain: stop granting Pro, but keep
			// the key so the customer can re-activate.
			update_option('emsfb_pro', 0);
			if ($this->is_farsi_offline_license_efb()) {
				$this->emsfb_pro_log('is_efb_pro: farsi site - local activation code INVALID for current domain, pro disabled');
			}
			return false;
		} else {
			$activeCode = explode('@', $s)[0];
			if ($this->validated_pro_efb($activeCode)) {
				return $this->update_pro_status_efb($s);
			}
			update_option('emsfb_pro', 0);
			delete_option('emsfb_pro_ac_date');
		}
		return false;
	}

	/**
	 * Scheduled licence revalidation (emsfb_revalidate_license).
	 *
	 * Runs the remote check that is::efb_pro() used to run inline, so no visitor
	 * ever waits for the licence server and no page view can revoke a licence.
	 *
	 * @return bool|null Effective Pro state, or null when there is nothing to check.
	 */
	public function cron_check_pro_efb() {
		$is_pro = (int) get_option('emsfb_pro', 2);
		if (3 === $is_pro) {
			return true;
		}

		$activeCode = (string) get_option('emsfb_pro_activeCode', '');
		if (strlen($activeCode) < 5) {
			return null;
		}

		return $this->weekly_check_pro_efb($activeCode);
	}

	public function render_pro_gate_efb( $addon_name = '' ) {
		$pro_status = (int) get_option( 'emsfb_pro', -1 );

		// Free Plus has its own built-in features, but Pro add-ons require an
		// active Pro package. Treating package 3 as Pro here made an installed
		// paid add-on reachable after a confirmed Pro -> Free Plus downgrade.
		if ( $pro_status === 1 ) {
			return false;
		}

		$is_expired = ( $pro_status === 0 );

		$buy_url    = EMSFB_SERVER_URL . '/checkout';
		$ac         = get_option( 'emsfb_pro_activeCode', '' );
		$renew_url  = $buy_url . '?renew=' . urlencode( $ac );

		if ( $is_expired ) {
			$title   = esc_html__( 'Your activation code has expired!', 'easy-form-builder' );
			$message = sprintf(
				esc_html__( 'Your Easy Form Builder Pro subscription has expired. To continue using the %s add-on and all Pro features, please renew your subscription.', 'easy-form-builder' ),
				'<strong>' . esc_html( $addon_name ) . '</strong>'
			);
			$btn_url  = $renew_url;
			$btn_text = esc_html__( 'Renew Subscription', 'easy-form-builder' );
			$icon     = 'bi-exclamation-triangle-fill';
			$bg_class = 'bg-dark text-warning';
		} else {
			$title   = esc_html__( 'Pro Version Required', 'easy-form-builder' );
			$message = sprintf(
				esc_html__( 'The %s add-on is a Pro feature. Please upgrade to Easy Form Builder Pro to access this functionality.', 'easy-form-builder' ),
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

		$url = EMSFB_SERVER_URL . '/checkout?renew=';

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

		$date_limit = date('Y-m-d', strtotime('-8 days'));

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

		$addons = array_fill_keys( $this->get_all_addon_keys_efb(), 0 );

		if ( is_object( $ac ) ) {
			foreach ( $addons as $addon_key => $default_value ) {
				if ( property_exists( $ac, $addon_key ) ) {
					$addons[$addon_key] = intval( $ac->{$addon_key} );
					continue;
				}

				$legacy_value = get_option( 'emsfb_addon_' . $addon_key, false );
				if ( $legacy_value !== false ) {
					$addons[$addon_key] = intval( $legacy_value );
				}
			}
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
            if (is_array($newSettings) && isset($newSettings[0]) && in_array($newSettings[0], ['{', '['], true) && count($newSettings) > 20) {
                $keys = array_keys($newSettings);
                $is_char_map = true;
                $expected = 0;
                foreach ($keys as $key) {
                    if (!is_int($key) || $key !== $expected || !is_string($newSettings[$key]) || strlen($newSettings[$key]) > 8) {
                        $is_char_map = false;
                        break;
                    }
                    $expected++;
                }
                if ($is_char_map) {
                    $candidate = implode('', $newSettings);
                    $candidate_decoded = json_decode($candidate);
                    if (is_object($candidate_decoded) || is_array($candidate_decoded)) {
                        $json = $candidate;
                    }
                }
            }
            if ($json === '') {
                $json = json_encode($newSettings, JSON_UNESCAPED_UNICODE);
            }
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

        /* Wipe guard: version bumps and cache-heal paths sometimes rebuild the
         * settings from a stale copy that silently lost fields (payment keys,
         * SMTP, captcha...). A deliberate change always sends the property
         * (possibly empty); a property that is entirely absent from the new
         * payload but exists in the stored row means the caller never saw it —
         * keep the stored value instead of dropping it. */
        $new_decoded = json_decode($json);
        if (is_object($new_decoded)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix
            $existing_raw = $wpdb->get_var("SELECT setting FROM `{$table_name}` ORDER BY id DESC LIMIT 1");
            $existing = is_string($existing_raw) ? json_decode($existing_raw) : null;
            if ($existing === null && is_string($existing_raw)) {
                $tmp = $existing_raw;
                for ($i = 0; $i < 5 && $existing === null; $i++) {
                    $tmp = stripslashes($tmp);
                    $existing = json_decode($tmp);
                }
            }
            if (is_object($existing)) {
                $merged = false;
                foreach (get_object_vars($existing) as $k => $v) {
                    if (!property_exists($new_decoded, $k)) {
                        $new_decoded->$k = $v;
                        $merged = true;
                    }
                }
                if ($merged) {
                    $json = wp_json_encode($new_decoded, JSON_UNESCAPED_UNICODE);
                }
            }
        }

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
        /* The public submit handler caches the settings under its own key with
         * no expiry. Leaving it behind meant that on sites with a persistent
         * object cache, turning "This site can send emails" on never reached
         * form submissions - they kept reading the pre-save copy. */
        wp_cache_delete('emsfb_settings', 'emsfb');

        get_setting_Emsfb('_clear_cache');

        return true;
    }

}
