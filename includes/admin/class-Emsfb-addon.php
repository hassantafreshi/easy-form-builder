<?php
namespace Emsfb;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Addon {
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
		$this->setting_name = 'Emsfb_addon';
		global $wpdb;
		$this->db = $wpdb;
		$this->get_settings();
		$this->options = get_option( $this->setting_name );
		if ( empty( $this->options ) ) {
			update_option( $this->setting_name, array() );
		}
		add_action( 'admin_menu', array( $this, 'add_addon_menu' ), 11 );
	}
	public function add_addon_menu() {
		add_submenu_page( 'Emsfb', esc_html__('Add-ons', 'easy-form-builder' ),'<span style="color:#ff4b93">'. esc_html__('Add-ons', 'easy-form-builder' ) .'</span>', 'Emsfb_addon', 'Emsfb_addon', array(
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
		// Check all bundled add-ons before rendering their remote catalogue. The
		// result is informational for inactive add-ons; enabled incompatible
		// add-ons are also prevented from loading by the shared compatibility
		// guard in the plugin bootstrap.
		$ac = get_setting_Emsfb('decoded');
		$php_addon_issues = emsfb_get_incompatible_addons_efb( $ac );

		// After a plugin update, block the Add-ons page until missing add-on files
		// are reinstalled. The Recover button on the blocking screen performs the
		// reinstall, then offers an Activate (reload) button.
		$efb_recovery_fn = get_efbFunction();
		$addon_recovery = $efb_recovery_fn->recover_missing_addons_efb( null, 'addons' );
		if ( ! empty( $addon_recovery['recovered'] ) ) {
			echo $efb_recovery_fn->render_addon_recovery_reload_ui_efb(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$addon_recovery_state = $efb_recovery_fn->addon_recovery_state_efb();
		if ( 'block' === $addon_recovery_state ) {
			echo $efb_recovery_fn->render_addon_recovery_ui_efb( 'block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$server_name = str_replace("www.", "", isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '');
		if ( get_locale() === 'fa_IR' ) {
			// For Persian sites, prefer the local mirror easyformbuilder.ir. Check
			// that it actually responds first and fall back to whitestudio.team when
			// it does not. The result is cached in a transient so the Add-ons page
			// is not slowed by a remote request on every render.
			$domain = get_transient( 'emsfb_addons_fa_domain' );
			if ( false === $domain ) {
				$primary  = 'https://easyformbuilder.ir';
				$response = wp_remote_head( $primary, array( 'timeout' => 4 ) );
				$domain   = ( ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) )
					? $primary
					: 'https://whitestudio.team';
				set_transient( 'emsfb_addons_fa_domain', $domain, HOUR_IN_SECONDS );
			}
		} else {
			$domain = untrailingslashit( EMSFB_SERVER_URL );
		}
		wp_register_script('whiteStudioAddone', $domain . '/wp-json/wl/v1/addons.js' .$server_name, null, null, true);

        wp_enqueue_script('whiteStudioAddone');

		$efbFunction = get_efbFunction();
		$noti_pro = intval(get_option('emsfb_pro' ,-1));
		$addon_status = null;
		if ($noti_pro === 0  ){
			$noti_pro ="<script>const noti_exp_efb='".$efbFunction->noti_expire_efb()."';</script>";

		}else{
			$noti_pro = '<script>const noti_exp_efb="";</script>';
			$addon_status = emsfb_get_file_access_status_efb();
		}

	?>
	<!-- new code ddd -->
	<?php echo   $noti_pro ?>

	<!-- Addon Directory Status Check - Only for Pro version -->
	<?php if ($noti_pro !== 0 && $addon_status): ?>
		<?php if (!$addon_status['status']): ?>
			<div class="notice notice-error efb" style="margin: 20px 0;">
				<p><strong><?php echo esc_html__('Addon Installation Issue', 'easy-form-builder'); ?>:</strong></p>
				<p><?php echo esc_html($addon_status['current_message']); ?></p>
				<?php if (!empty($addon_status['error_codes'])): ?>
					<details style="margin-top: 10px;">
						<summary><?php echo esc_html__('Technical Details', 'easy-form-builder'); ?></summary>
						<ul style="margin: 10px 0;">
							<?php foreach ($addon_status['error_codes'] as $error_code): ?>
								<li><code><?php echo esc_html($error_code); ?></code></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
				<p><em><?php echo sprintf(esc_html__('Checked %s ago', 'easy-form-builder'), human_time_diff(strtotime($addon_status['checked_at']), current_time('timestamp'))); ?></em></p>
			</div>
		<?php else: ?>
			<div class="notice notice-success efb" style="margin: 20px 0;">
				<p><strong><?php echo esc_html__('System Ready', 'easy-form-builder'); ?>:</strong> <?php echo esc_html($addon_status['current_message']); ?></p>
				<p><em><?php echo sprintf(esc_html__('Checked %s ago', 'easy-form-builder'), human_time_diff(strtotime($addon_status['checked_at']), current_time('timestamp'))); ?></em></p>
			</div>
		<?php endif; ?>
	<?php elseif ($noti_pro !== 0 && !$addon_status): ?>
		<div class="notice notice-info efb" style="margin: 20px 0;">
			<p><?php echo esc_html__('Checking add-on installation capabilities&hellip;', 'easy-form-builder'); ?></p>
			<p><em><?php echo esc_html__('This check runs once after plugin activation. Please refresh the page in a few moments.', 'easy-form-builder'); ?></em></p>
		</div>
	<?php endif; ?>
	<!-- End Addon Directory Status Check -->

	<?php if ( ! empty( $php_addon_issues ) ) : ?>
		<div class="notice notice-error efb" style="margin: 20px 0;">
			<p><strong><?php echo esc_html__( 'Some add-ons need a PHP server change', 'easy-form-builder' ); ?></strong></p>
			<p><?php echo esc_html__( 'Easy Form Builder has safely paused each affected active add-on, so the rest of the plugin can continue working.', 'easy-form-builder' ); ?></p>
			<ul style="list-style: disc; margin: 0 0 0 22px;">
				<?php foreach ( $php_addon_issues as $php_addon_issue ) : ?>
					<li style="margin-bottom: 8px;">
						<strong><?php echo esc_html( $php_addon_issue['name'] ); ?></strong>
						<?php if ( ! empty( $php_addon_issue['enabled'] ) ) : ?>
							<?php echo esc_html__( '(currently enabled and paused)', 'easy-form-builder' ); ?>
						<?php else : ?>
							<?php echo esc_html__( '(not enabled)', 'easy-form-builder' ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $php_addon_issue['disabled_functions'] ) ) : ?>
							&mdash; <?php echo esc_html__( 'disabled in php.ini:', 'easy-form-builder' ); ?>
							<code><?php echo esc_html( implode( ', ', $php_addon_issue['disabled_functions'] ) ); ?></code>.
						<?php endif; ?>
						<?php if ( ! empty( $php_addon_issue['missing_extension_functions'] ) ) : ?>
							&mdash; <?php echo esc_html__( 'not provided by PHP or its required extension:', 'easy-form-builder' ); ?>
							<code><?php echo esc_html( implode( ', ', $php_addon_issue['missing_extension_functions'] ) ); ?></code>.
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><?php echo esc_html__( 'This usually means the needed PHP extension is not installed, or the function is listed in disable_functions in php.ini. Please ask your hosting provider to enable it, then refresh this page.', 'easy-form-builder' ); ?></p>
		</div>
	<?php endif; ?>

	<div id="alert_efb" class="efb mx-5"></div>

	<?php if ( 'inline' === $addon_recovery_state ) : ?>
		<?php echo $efb_recovery_fn->render_addon_recovery_ui_efb( 'inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="efb modal-content efb " id="settingModalEfb-sections">
									<div class="efb modal-header efb">
										<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button" role="button"><i class="efb bi-x-lg"></i></a>
									</div>
									<div class="efb modal-body row" id="settingModalEfb-body">
										<?php do_action('efb_loading_card'); ?>
									</div>
	</div></div></div>
	<div id="tab_container_efb">
			<div class="efb card-body text-center efb">
				<?php do_action('efb_loading_card'); ?>
			</div>
    </div>
	<!-- end new code dd -->
		<?php
		$pro = intval(get_option('emsfb_pro' ,-1));
		$pro = $pro == 1 ? true : false;
		$maps =false;

		if(is_object($ac) && (!isset($ac->efb_version) || version_compare(EMSFB_PLUGIN_VERSION,$ac->efb_version)!=0)){
			$efbFunction->setting_version_efb_update($ac ,$pro);
		}
		$lang = $efbFunction->text_efb(2);
			wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION, true);
			wp_enqueue_script('jquery-ui-efb');
			wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),EMSFB_PLUGIN_VERSION , true);
			wp_enqueue_script('jquery-dd-efb');
		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
		"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
		'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif',
		];
		$smtp =-1;
		$captcha =false;
		$smtp_m = "";
		$addons = $efbFunction->fun_get_addons_list_efb($ac);
		if(is_object($ac)){
			if( isset($ac->siteKey)&& strlen($ac->siteKey)>5){$captcha="true";}
			/* String compare against "true"/"false" missed the bool and int shapes
			 * the settings row also stores, leaving $smtp at -1. */
			if(emsfb_is_email_sending_enabled_efb($ac)){$smtp=1;}else{$smtp=0;$smtp_m =$lang['sMTPNotWork'];}
		}else{$smtp_m =$lang['goToEFBAddEmailM'];}
		wp_register_script('efb-recorder-js', EMSFB_PLUGIN_URL . 'public/assets/js/recorder-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-recorder-js');
		wp_register_style('efb-recorder-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/recorder-efb.css', array(), EMSFB_PLUGIN_VERSION);
		wp_enqueue_style('efb-recorder-css');
		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js', array('jquery','efb-recorder-js'), EMSFB_PLUGIN_VERSION, true);
		$efb_var_data = apply_filters('efb_admin_localize_vars', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'=> wp_create_nonce("wp_rest"),
			'check' => 2,
			'pro' => $pro ? 1 : 0,
			'rtl' => is_rtl() ,
			'text' => $lang	,
			'images' => $img,
			'captcha'=>$captcha,
			'smtp'=>$smtp,
			"smtp_message"=>$smtp,
			'maps'=> $maps,
			'bootstrap' =>$this->check_temp_is_bootstrap(),
			"language"=> get_locale(),
			"addson"=>$addons,
			'wp_lan'=>get_locale(),
			'v_efb'=>EMSFB_PLUGIN_VERSION,
			'setting'=>$ac,
			'upload_max'=>(int) floor(wp_max_upload_size() / MB_IN_BYTES),
		), 'addon');
		wp_localize_script('Emsfb-admin-js','efb_var',$efb_var_data);
		$efb_val_js_path = EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/val-efb.js';
		$efb_val_js_version = is_readable($efb_val_js_path) ? (string) filemtime($efb_val_js_path) : EMSFB_PLUGIN_VERSION;
		wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js', array('jquery'), $efb_val_js_version, true);
		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js', array('jquery','efb-recorder-js'), EMSFB_PLUGIN_VERSION, true);
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("wp_rest"),
			'check' => 1		));
		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION, true);
	}
	public function fun_Emsfb_creator()
	{
	}

	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
		$table_name = $this->db->prefix . "emsfb_form";
		$r =$this->db->insert($table_name, array(
			'form_name' => $this->name,
			'form_structer' => $this->value,
			'form_email' => $this->email,
			'form_created_by' => $this->userId,
			'form_type'=>$this->formtype,
		));    $this->id_  = $this->db->insert_id;
	}
	public function check_temp_is_bootstrap (){

		$cached = get_transient('emsfb_theme_has_bootstrap');
		if ($cached !== false) {
			return $cached === 'yes';
		}

        $it = list_files(get_template_directory());
        $s = false;
        foreach($it as $path) {
            if (preg_match("/\bbootstrap+.+.css+/i", $path))
            {
				$f = emsfb_read_file_efb($path);
                if(preg_match("/col-md-12/i", $f)){
                    $s= true;
                    break;
                }
            }
        }

		set_transient('emsfb_theme_has_bootstrap', $s ? 'yes' : 'no', DAY_IN_SECONDS);
        return  $s;
    }
}
new Addon();
