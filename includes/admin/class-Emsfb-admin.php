<?php
namespace Emsfb;
/**
 * Class Admin
 *
 * @package Emsfb
 */
class Admin {
    /**
     * Admin constructor.
     */
    public $ip;
    public $plugin_version;
    protected $db;
    private $form_cache = [];


    // private $wpdb;
    public function __construct() {
        $this->init_hooks();
        global $wpdb;
        $this->db = $wpdb;
    }
    /**
     * Initial plugin
     */
    private function init_hooks() {
        // Check exists require function
        if (!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }
        if (is_admin() and is_super_admin()) {
            $this->add_cap();
        }
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
        $this->ip = $this->get_ip_address();
        if (is_admin()) {
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            add_action('wp_ajax_remove_id_Emsfb', [$this, 'delete_form_id_public']);                 //Remove a form by id
            add_action('wp_ajax_remove_message_id_Emsfb', [$this, 'delete_message_id_public']);      //Remove a message by id
            add_action('wp_ajax_get_form_id_Emsfb', [$this, 'get_form_id_Emsfb']);                   //Get a form by id
            add_action('wp_ajax_get_messages_id_Emsfb', [$this, 'get_messages_id_Emsfb']);           //Get a message by id
            add_action('wp_ajax_get_all_response_id_Emsfb', [$this, 'get_all_response_id_Emsfb']);   //Get all response by id
            add_action('wp_ajax_update_form_Emsfb', [$this, 'update_form_id_Emsfb']);                //Update a form by id
            add_action('wp_ajax_update_message_state_Emsfb', [$this, 'update_message_state_Emsfb']); //Update a message status by id
            add_action('wp_ajax_set_replyMessage_id_Emsfb', [$this, 'set_replyMessage_id_Emsfb']);   //Set reply message by id from admin
            add_action('wp_ajax_set_settings_Emsfb', [$this, 'set_settings_Emsfb']);                  //Set setting
            add_action('wp_ajax_get_track_id_Emsfb', [$this, 'get_ajax_track_admin']);               //Get track id
            add_action('wp_ajax_clear_garbeg_Emsfb', [$this, 'clear_garbeg_admin']);                 //Clear files is not used
            add_action('wp_ajax_check_email_server_efb', [$this, 'check_email_server_admin']);       //Check email server
            add_action('wp_ajax_add_addons_Emsfb', [$this, 'add_addons_Emsfb']);                     //Add new addons
            add_action('wp_ajax_remove_addons_Emsfb', [$this, 'remove_addons_Emsfb']);               //Remove a addon
            add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));
            add_action('wp_ajax_send_sms_pnl_efb', [$this, 'send_sms_admin_Emsfb']);                 //Send sms from admin panel
            add_action('wp_ajax_dup_efb', [$this, 'fun_duplicate_Emsfb']);                           //Duplicate a form
            add_action('efb_loading_card', [$this, 'loading_card_efb']);                             //Loading card
            add_action('wp_ajax_remove_messages_Emsfb', [$this, 'delete_messages_Emsfb']);          //Remove messages by object
            add_action('wp_ajax_read_list_Emsfb', [$this, 'read_list_Emsfb']);                      //Remove messages by object
            add_action('wp_ajax_heartbeat_Emsfb' , [$this, 'heartbeat_Emsfb'] );
            add_action('wp_ajax_report_problem_Emsfb' , [$this, 'report_problem_Emsfb'] );
            add_action('wp_ajax_efb_save_plan_selection', [$this, 'efb_save_plan_selection']);        //Save plan selection

            add_action('create_temporary_links_table_Emsfb' , [$this , 'create_temporary_links_table_Emsfb']);

            add_action('admin_notices', [$this, 'admin_notices_efb']);

        }
    }
    public function add_cap() {
        // Get administrator role
        $role = get_role('administrator');
        $role->add_cap('Emsfb');
        $role->add_cap('Emsfb_create');
        $role->add_cap('Emsfb_panel');
        $role->add_cap('Emsfb_addon');
        if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
            $role->add_cap('Emsfb_sms_efb');
        }
    }

    public function admin_assets($hook) {
        global $current_screen;
        $hook = $hook ? $hook : http_build_query($_GET);
        $package_type_efb = (int) get_option('emsfb_pro' ,2);
        if (strpos($hook, 'Emsfb')==true && is_admin()) {

                    wp_register_style('Emsfb-admin', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-efb.css', true,EMSFB_PLUGIN_VERSION );
                    wp_enqueue_style('Emsfb-admin');

            if (is_rtl()) {
                wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl-efb.css', true,EMSFB_PLUGIN_VERSION );
                wp_enqueue_style('Emsfb-css-rtl');
            }
            wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-style-css');
            wp_register_style('Emsfb-responsive-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/min-1200-style.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-responsive-css');
            wp_register_style('Emsfb-bootstrap', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap');
            wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap-icons-css');
            wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap-select-css');
            wp_register_style('Emsfb-response-viewer-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/response-viewer-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-response-viewer-css');
            $this->check_and_enqueue_font_roboto_Emsfb();
            $lang = get_locale();
            if (strlen($lang) > 0) {$lang = explode('_', $lang)[0];}
                wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min-efb.js',false,EMSFB_PLUGIN_VERSION);
                 wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min-efb.js', array( 'jquery' ),true,EMSFB_PLUGIN_VERSION);
                wp_enqueue_script('efb-bootstrap-icon-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-icon-efb.js',false,EMSFB_PLUGIN_VERSION);
                wp_enqueue_script('efb-response-viewer-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/response-viewer-efb.js',false,EMSFB_PLUGIN_VERSION);
        }
    }
    /**
     * Register admin menu
     */
    public function admin_menu() {
        $noti_count = count($this->get_not_read_message());
        $icon       = EMSFB_PLUGIN_URL . '/includes/admin/assets/image/logo-gray.png';
        add_menu_page(
            esc_html__('Panel', 'Emsfb'),
            $noti_count ? sprintf(esc_html__('Easy Form Builder', 'easy-form-builder') . ' <span id="efbCountM" class="efb awaiting-mod">%d</span>', $noti_count) : esc_html__('Easy Form Builder', 'easy-form-builder'),
            'Emsfb',
            'Emsfb',
            '',
            '' . $icon . ''
        );
        add_submenu_page('Emsfb', esc_html__('Panel', 'easy-form-builder'), esc_html__('Panel', 'easy-form-builder'), 'Emsfb', 'Emsfb', [$this, 'panel_callback']);
    }
    /**
     * Callback outbox page.
     */
    public function panel_callback() {
        include_once EMSFB_PLUGIN_DIRECTORY . "/includes/admin/class-Emsfb-panel.php";
        $list_table = new Panel_edit();
    }
    public function delete_form_id_public() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
         if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m = $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $id =  ( int ) sanitize_text_field(wp_unslash( $_POST['id']) );
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_form";
        $r          = $this->db->delete(
            $table_name,
            ['form_id' => $id],
            ['%d']
        );


        if ($r !== false) {
            $this->clear_form_cache_efb($id);
        }

        $table_name = $this->db->prefix . "emsfb_msg_";
         $this->db->delete(
            $table_name,
            ['form_id' => $id],
            ['%d']
        );
        $response = ['success' => true, 'r' => $r];
        wp_send_json_success($response, 200);
    }
    public function delete_message_id_public() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m = $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $id =  ( int ) sanitize_text_field( wp_unslash( $_POST['id']) );
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $r          = $this->db->delete(
            $table_name,
            ['msg_id' => $id],
            ['%d']
        );
        $response = ['success' => true, 'r' => $r];
        wp_send_json_success($response, 200);
    }
    public function update_form_id_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["sms_noti","msg_adons","error403","invalidRequire","nAllowedUseHtml","updated","upDMsg" ,"newMessageReceived","trackNo","url","newResponse","WeRecivedUrM"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $id =  ( int ) sanitize_text_field( wp_unslash( $_POST['id']) );
        $name = sanitize_text_field( wp_unslash( $_POST['name']) );
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can)  {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
        }
        if (empty( $post_value) || empty($id) || empty($name)) {
            $m = $lang['invalidRequire'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        if ($this->isScript(json_encode( $post_value),JSON_UNESCAPED_UNICODE) || $this->isScript(json_encode($name,JSON_UNESCAPED_UNICODE))) {
            $m = $lang['nAllowedUseHtml'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        $valp =str_replace('\\', '',  $post_value);
		$valp = json_decode($valp,true);
        // ,`form_name` =>
		// check if smsnoti axist then call add_sms_contact_efb
		$sms_msg_new_noti="";
		$sms_msg_responsed_noti="";
		$sms_msg_recived_user="";
		$sms_admins_phoneno="";

        $telegram_msg_new_noti = "";
        $telegram_msg_responsed_noti = "";
        $telegram_msg_recived_user = "";
        $telegram_bot_token = "";
        $telegram_admin_chat_ids = "";

        if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1){
			$sms_msg_new_noti = isset($valp[0]['sms_msg_new_noti']) ?$valp[0]['sms_msg_new_noti'] :$lang['newMessageReceived'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_msg_responsed_noti = isset($valp[0]['sms_msg_responsed_noti']) ? $valp[0]['sms_msg_responsed_noti'] :  $lang['newResponse']."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_msg_recived_user = isset($valp[0]['sms_msg_recived_usr']) ? $valp[0]['sms_msg_recived_usr'] : $lang['WeRecivedUrM'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
			$sms_admins_phoneno = isset($valp[0]['sms_admins_phone_no']) ? $valp[0]['sms_admins_phone_no'] : "";
			unset($valp[0]['sms_msg_new_noti']);
			unset($valp[0]['sms_msg_responsed_noti']);
			unset($valp[0]['sms_msg_recived_user']);
			if(isset($valp[0]['sms_admins_phone_no'])){unset($valp[0]['sms_admins_phone_no']);}
		}

        // Ù¾Ø±Ø¯Ø§Ø²Ø´ ØªÙ†Ø¸ÛŒÙ…Ø§Øª ØªÙ„Ú¯Ø±Ø§Ù…
        if(isset($valp[0]['telegramnoti']) && intval($valp[0]['telegramnoti'])==1){
            $telegram_msg_new_noti = isset($valp[0]['telegram_msg_new_noti']) ? $valp[0]['telegram_msg_new_noti'] : $lang['newMessageReceived'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_msg_responsed_noti = isset($valp[0]['telegram_msg_responsed_noti']) ? $valp[0]['telegram_msg_responsed_noti'] : $lang['newResponse']."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_msg_recived_user = isset($valp[0]['telegram_msg_recived_usr']) ? $valp[0]['telegram_msg_recived_usr'] : $lang['WeRecivedUrM'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_bot_token = isset($valp[0]['telegram_bot_token']) ? $valp[0]['telegram_bot_token'] : "";
            $telegram_admin_chat_ids = isset($valp[0]['telegram_admin_chat_ids']) ? $valp[0]['telegram_admin_chat_ids'] : "";

            // Ø­Ø°Ù Ù¾Ø§Ø±Ø§Ù…ØªØ±Ù‡Ø§ÛŒ ØªÙ„Ú¯Ø±Ø§Ù… Ø§Ø² Ø¢Ø±Ø§ÛŒÙ‡ Ø§ØµÙ„ÛŒ
            unset($valp[0]['telegram_msg_new_noti']);
            unset($valp[0]['telegram_msg_responsed_noti']);
            unset($valp[0]['telegram_msg_recived_user']);
            unset($valp[0]['telegram_bot_token']);
            unset($valp[0]['telegram_admin_chat_ids']);
        }
        $valp = $efbFunction->sanitize_obj_msg_efb($valp);
        $form_type = $valp[0]['type'];
		$value =json_encode($valp,JSON_UNESCAPED_UNICODE);
        $value_ =str_replace('"', '\"', $value);
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_form";
        $r = $this->db->update($table_name, ['form_structer' => $value_, 'form_name' => $name ,'form_type'=>$form_type ], ['form_id' => $id]);

        if ($r !== false) {
            $cache_data = (object) array(
                'form_structer' => $value_,
                'form_name' => $name,
                'form_type' => $form_type
            );
            $this->update_form_cache_efb($id, $cache_data, array('form_structer', 'form_type'));
        }

        $value_="";
        $value="";
        if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1 ){
			// $efbFunction->add_sms_contact_efb($this->id_,$sms_msg_new_noti,$sms_msg_recived_admin,$sms_msg_recived_user);
			// require smsefb.php and call add_sms_contact_efb
            $sms_exists = get_option('emsfb_addon_AdnSS',false);
            $smf_file_exist = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
            if(!$sms_exists || !$smf_file_exist) {
               $m = str_replace('NN', '<b>' . $lang['sms_noti'] . '</b>', $lang['msg_adons']);
                $response = ['success' => false, 'm' => $m];
                wp_send_json_success($response, 200);
            }

			require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
			$smsefb = new smssendefb();
			$smsefb->add_sms_contact_efb(
                $id,
				$sms_admins_phoneno,
				$sms_msg_recived_user,
				$sms_msg_new_noti,
				$sms_msg_new_noti,
				$sms_msg_responsed_noti);
		}

        // Ø°Ø®ÛŒØ±Ù‡ ØªÙ†Ø¸ÛŒÙ…Ø§Øª ØªÙ„Ú¯Ø±Ø§Ù…
        if(isset($valp[0]['telegramnoti']) && intval($valp[0]['telegramnoti'])==1 ){
            $telegram_exists = get_option('emsfb_addon_AdnTLG', false);
            $telegram_file_exist = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );

            if(!$telegram_exists || !$telegram_file_exist) {
                $m = str_replace('NN', '<b>Telegram Notification</b>', $lang['msg_adons']);
                $response = ['success' => false, 'm' => $m];
                wp_send_json_success($response, 200);
            }

            require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
            $telegramsendefb = new telegramsendefb();
            $telegramsendefb->add_telegram_contact_efb(
                $id,
                $telegram_admin_chat_ids,
                $telegram_bot_token,
                $telegram_msg_recived_user,
                $telegram_msg_new_noti,
                $telegram_msg_new_noti,
                $telegram_msg_responsed_noti
            );
		}
        $m = $lang['updated'];
        $response = ['success' => true, 'r' =>"updated", 'value' => "[EMS_Form_Builder id=$id]"];
        wp_send_json_success($response, 200);
    }
    public function add_addons_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["error403","done","invalidRequire","upDMsg"];
        $lang= $efbFunction->text_efb($text);
        $ac= get_setting_Emsfb('decoded');
        	/*
            AdnSPF == stripe payment
            AdnOF == offline form
            AdnPPF == persia payment
            AdnATC == advance tracking code
            AdnSS == sms service
            AdnCPF == crypto payment
            AdnESZ == zone picker
            AdnSE == email service
             AdnWHS == webhook
            AdnPAP == paypal
            AdnWSP == whitestudio pay
            AdnSMF == smart form
            AdnPLF == passwordless form
            AdnMSF == membership form
            AdnBEF == booking and event form
            'AdnPDP'=>0,
			'AdnADP'=>0
        */
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $allw = ["AdnSPF","AdnOF","AdnPPF","AdnATC","AdnSS","AdnCPF","AdnESZ","AdnSE",
                 "AdnWHS","AdnPAP","AdnWSP","AdnSMF","AdnPLF","AdnMSF","AdnBEF","AdnPDP","AdnADP"];
        $dd =gettype(array_search($post_value, $allw));
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can || $dd !='integer') {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
        }
        if ($this->isScript($post_value)) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        if ($this->isScript($post_value)) {
            $m = $lang['nAllowedUseHtml'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        $name_space ='emsfb_addon_'.$value;
       if($value!="AdnOF"){
            $server_name = isset($_SERVER['HTTP_HOST']) ? str_replace("www.", "", sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
            $name_space = 'emsfb_addon_' . $value;
            delete_option($name_space);
            $vwp = get_bloginfo('version');
            $vwp = substr($vwp,0,3);
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/' . $server_name . '/' . $value . '/' . $vwp . '/';
            if (get_locale() == 'fa_IR') {
                $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/' . $server_name . '/' . $value . '/' . $vwp . '/';
            }
            $attempts = 2;
            for ($i = 0; $i < $attempts; $i++) {
                $request = wp_remote_get($u);
                if (!is_wp_error($request)) {
                    break;
                }
                if ($i == $attempts - 1) {
                    $m = esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server', 'easy-form-builder');
                    $response = ['success' => false, "m" => $m];
                    wp_send_json_success($response, 200);
                }
            }

            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body);
            if ($data == null || $data == 'null') {
                $m = esc_html__('It looks like you cannot use the Easy Form Builder features right now. Please contact Whitestudio.team support if you need assistance.', 'easy-form-builder');
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, 200);
            }
            if (isset($data->status)==true && $data->status == false) {
                $response = ['success' => false, "m" => $data->error];
                wp_send_json_success($response, 200);
            }
            // Check version of EFB to Addons
            if (isset($data->v)==true && version_compare(EMSFB_PLUGIN_VERSION, $data->v) == -1) {
                $m = $lang['upDMsg'];
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, 200);
            }
            if ( isset($data->download) && $data->download == true) {
                $url = $data->link;
                $s = $this->fun_addon_new($url);
                if ($s == false) {
                    $m = esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files', 'easy-form-builder');
                    $response = ['success' => false, "m" => $m];
                    wp_send_json_success($response, 200);
                }
            }
        }
        /*
            AdnSPF == strip payment
            AdnOF == offline form
            AdnPPF == persia payment
            AdnATC == advance tracking code
            AdnSS == sms service
            AdnCPF == crypto payment
            AdnESZ == zone picker
            AdnSE == email service
            AdnWHS == webhook
            AdnPAP == paypal
            AdnWSP == whitestudio pay
            AdnSMF == smart form
            AdnPLF == passwordless form
            AdnMSF == membership form
            AdnBEF == booking and event form
        */
        if(isset($ac->AdnSPF)==false){
            // $ac['AdnSPF=0;
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
        }
        $ac->{$value}=1;
        // add efb_version to ac
        $ac->efb_version=EMSFB_PLUGIN_VERSION;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        // json_encode produces valid JSON; set_setting_Emsfb handles DB escaping.
        // Do NOT add str_replace('"','\"') â€” it double-escapes and breaks json_decode.
        $efbFunction->set_setting_Emsfb( $ac, $ac->emailSupporter );
        $newAc = json_encode( $ac, JSON_UNESCAPED_UNICODE );
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        update_option($name_space, 1);
        wp_send_json_success($response, 200);
    }
    public function remove_addons_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["error403","done","invalidRequire"];
        $lang= $efbFunction->text_efb($text);
        $ac= get_setting_Emsfb('decoded');
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        if ($this->isScript($post_value)) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        if ($this->isScript($post_value)) {
            $m = $lang['nAllowedUseHtml'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $server_name = str_replace("www.", "", isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'yourdomain.com');
        $name_space ='emsfb_addon_'.$post_value;
        /*
            AdnSPF == strip payment
            AdnOF == offline form
            AdnPPF == persia payment
            AdnATC == advance tracking code
            AdnSS == sms service
            AdnCPF == crypto payment
            AdnESZ == zone picker
            AdnSE == email service
            AdnWHS == webhook
            AdnPAP == paypal
            AdnWSP == whitestudio pay
            AdnSMF == smart form
            AdnPLF == passwordless form
            AdnMSF == membership form
            AdnBEF == booking and event form
        */
        if(isset($ac->AdnSPF)==false){
            // $ac['AdnSPF=0;
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
        }
        $ac->{$post_value}=0;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        // json_encode produces valid JSON; set_setting_Emsfb handles DB insert.
        // Do NOT add str_replace('"','\"') â€” it double-escapes and breaks json_decode.
        // Do NOT call $this->db->insert separately â€” set_setting_Emsfb already inserts.
        delete_option($name_space);
        $efbFunction->set_setting_Emsfb( $ac, $ac->emailSupporter );
        $newAc = json_encode( $ac, JSON_UNESCAPED_UNICODE );
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        wp_send_json_success($response, 200);
    }
    public function update_message_state_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh","updated"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m =   $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id']) && $this->isHTML(json_encode($_POST['value']),JSON_UNESCAPED_UNICODE)) {
            $m =   $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" => esc_html__("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, 200);
            die();
        }
        $id =  ( int ) sanitize_text_field(wp_unslash( $_POST['id']));
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $r          = $this->db->update($table_name, ['read_' => 1, 'read_date' => wp_date('Y-m-d H:i:s')], ['msg_id' => $id]);
        $m =   $lang['updated'];
        $response = ['success' => true, 'r' =>"updated"];
        wp_send_json_success($response, 200);
    }
    public function get_form_id_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m =   $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, 200);
            die();
        }
        $id =  ( int ) sanitize_text_field(wp_unslash( $_POST['id']));
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_form";
        $value      = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");
        // check if smsnoti axist then call get_sms_contact_efb from smsefb.php
        // check $value with regix "smsnoti":"1" is exists
        $smsnoti = strpos($value,'\"smsnoti\":\"1\"') !==false ? 1 : 0;
        if($smsnoti){
            // require smsefb.php and call get_sms_contact_efb
            // check smsefb.php is exists
            $sms_exists = get_option('emsfb_addon_AdnSS', false);
            $smf_file_exist = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
            if($sms_exists !== false && $smf_file_exist) {
                require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
                $smsefb = new smssendefb();
                $sms = $smsefb->get_sms_contact_efb($id);
                $value = str_replace('\"smsnoti\":\"1\"', '\"smsnoti\":\"1\",\"sms_msg_new_noti\":\"'.$sms->new_message_noti_user.'\",\"sms_msg_responsed_noti\":\"'.$sms->new_response_noti.'\",\"sms_msg_recived_usr\":\"'.$sms->recived_message_noti_user.'\",\"sms_admins_phone_no\":\"'.$sms->admin_numbers.'\"',$value);
            }
        }

        // Ø¨Ø±Ø±Ø³ÛŒ ÙˆØ¬ÙˆØ¯ ØªÙ†Ø¸ÛŒÙ…Ø§Øª ØªÙ„Ú¯Ø±Ø§Ù…
        $telegramnoti = strpos($value,'\"telegramnoti\":\"1\"') !==false ? 1 : 0;
        if($telegramnoti){
            $telegram_exists = get_option('emsfb_addon_AdnTLG', false);
            $telegram_file_exist = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
            if($telegram_exists !== false && $telegram_file_exist) {
                require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
                $telegramsendefb = new telegramsendefb();
                $telegram = $telegramsendefb->get_telegram_contact_efb($id);
                if($telegram) {
                    $value = str_replace('\"telegramnoti\":\"1\"', '\"telegramnoti\":\"1\",\"telegram_msg_new_noti\":\"'.$telegram->new_message_noti_user.'\",\"telegram_msg_responsed_noti\":\"'.$telegram->new_response_noti.'\",\"telegram_msg_recived_usr\":\"'.$telegram->received_message_noti_user.'\",\"telegram_bot_token\":\"'.$telegram->bot_token.'\",\"telegram_admin_chat_ids\":\"'.$telegram->admin_chat_ids.'\"',$value);
                }
            }
        }
        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, 200);
    }
    // stripe
    public function get_messages_id_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m =   $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        $id = sanitize_text_field(wp_unslash( $_POST['id']));
        $code = 'efb'. $id;
        $code =wp_create_nonce($code);
        $id =  ( int ) sanitize_text_field($id);
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id' ORDER BY `$table_name`.date DESC");
        $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        foreach ( $value as $row ) {
            if ( ! empty( $row->date ) ) {
                $timestamp = strtotime( $row->date );
                if ( $timestamp !== false ) {
                    $row->date = wp_date( $date_format, $timestamp );
                }
            }
        }
        $response   = ['success' => true, 'ajax_value' => $value, 'id' => $id,'nonce_msg'=> $code];
        wp_send_json_success($response, 200);
    }
    public function get_all_response_id_Emsfb() {
        $efbFunction = get_efbFunction();
        $text = ["spprt","error403","somethingWentWrongPleaseRefresh" ,"guest"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m =   $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        $id =  ( int ) sanitize_text_field(wp_unslash( $_POST['id'])) ;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_rsp_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE msg_id = '$id'");
        $this->db->update($table_name, ['read_' => 1], ['msg_id' => $id, 'read_' => 0]);
        foreach ($value as $key => $val) {
            $r = (int)$val->rsp_by;
            if ($r > 0) {
                $usr         = get_user_by('id', $r);
                $val->rsp_by = $usr->display_name;
            }else if ($r==-1){
                $val->rsp_by= $lang['spprt'];
            }
            else {
                $m =   $lang['guest'];
                $val->rsp_by =$m;
            }
        }
        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, 200);
    }
    public function set_replyMessage_id_Emsfb() {
         $this->efbFunction = get_efbFunction();
        $text = ["error405","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","messageSent"];
        $efbFunction = get_efbFunction();
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $response = ['success' => false, 'm' => $lang['error403']];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        $post_message = isset($_POST['message']) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $post_id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        if (empty($post_message) || empty($post_id)) {
            $response = ['success' => false, "m" => $lang['somethingWentWrongPleaseRefresh']];
            wp_send_json_success($response, 200);
        }
        if ($this->isHTML(json_encode($post_message))) {
            $response = ['success' => false, "m" => $lang['nAllowedUseHtml']];
            wp_send_json_success($response, 200);
        }
        $id =  ( int ) $post_id ;
        $id = preg_replace('/[,]+/','',$id);

        // echo $table_name;
        $m = str_replace("\\","",$post_message);
        $message =json_decode($m);
				$valobj=[];
				$stated=1;
				foreach ($message as $k =>$f){
					$in_loop=true;
					if($stated==0){break;}
						switch ($f->type) {
							case 'allformat':
								$d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
								// $p = strpos($item['url'],'http://'.$d);
								// don't change value stated because always file is sending
								$stated=1;
								if(isset($f->url) && strlen($f->url)>5 ){
									$stated=0;
									$ar = ['http://wwww.'.$d , 'https://wwww.'.$d ,'http://'.$d, 'https://'.$d ];
									$s = 0 ;
									foreach ($ar as  $r) {
										$c=strpos($f->url,$r);
										if(gettype($c)!='boolean' && $c==0) $s=1;
									}
										if($s==1 ){
											$stated=1;
											$f->url = sanitize_url($f->url);
										}else{
											$f->url="";
											$stated=0;
										}
								}
									$in_loop=false;
							break;
							default:
								$stated=0;
								if(isset($f->value) && $f->id_=="message"){
									$stated=1;
									$f->value = sanitize_text_field($f->value);
								}
								// $item['value'] =  'test';
								$in_loop=false;
							break;
						}
						if($stated==0){
							$response = array( 'success' => false  , 'm'=>$lang['error405']);
							wp_send_json_success($response, 200);
						}
				}
                $m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);
                if(empty($this->db)){
                    global $wpdb;
                    $this->db = $wpdb;
                }
        $table_name = $this->db->prefix . "emsfb_msg_";
        if(strpos($m , '"type\":\"closed\"')){
            $r = $this->db->update($table_name, ['read_' => 4], ['msg_id' => $id]);
        }else if(strpos($m , '"type\":\"opened\"')){
            $r = $this->db->update($table_name, ['read_' => 1], ['msg_id' => $id]);
        }
        $table_name = $this->db->prefix . "emsfb_rsp_";
        $ip = $this->ip;
        // 1 read
        // 0 Nread
        $this->db->insert(
            $table_name,
            [
                'ip'      => $ip,
                'content' => $m,
                'msg_id'  => $id,
                'rsp_by'  => get_current_user_id(),
                'read_'   => 1,
                'date'    => wp_date('Y-m-d H:i:s')
            ]
        );
        $table_name = $this->db->prefix . "emsfb_msg_";
        $this->db->update($table_name,array('read_'=>1), array('msg_id' => $id) );
        $m        = $lang['messageSent'];
        $response = ['success' => true, "m" => $m];
        // "rescl", "resop",
        $pro =$efbFunction->is_efb_pro(1);

        $efbFunction->response_to_user_by_msd_id($id ,$pro);
        wp_send_json_success($response, 200);
    }
    public function set_settings_Emsfb() {
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["pleaseDoNotAddJsCode","emailTemplate","addSCEmailM","messageSent","activationNcorrect","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","PEnterMessage"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        // Do NOT use sanitize_text_field() here â€” it strips ALL HTML tags from the
        // entire JSON string, destroying emailTemp content. Individual fields are
        // sanitized in the foreach loop below (sanitize_text_field for plain fields,
        // sanitize_full_html_efb for emailTemp).
        $post_message = isset($_POST['message']) ? wp_unslash( $_POST['message'] ) : '';
        if (empty($post_message)) {
            $m = $lang['PEnterMessage'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        // wp_unslash already removes WordPress magic-quote slashes.
        // Try json_decode directly first; only fallback to stripslashes for legacy
        $m = json_decode($post_message, true);
        if ($m === null && json_last_error() !== JSON_ERROR_NONE) {
            $m = json_decode(stripslashes($post_message), true);
        }
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $setting    = $post_message;
        $table_name = $this->db->prefix . "emsfb_setting";
        $email="";
        $em_st=false;
        if($m==null || gettype($m)!='array'){
            $m = $lang['somethingWentWrongPleaseRefresh'];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, 200);
        }
        foreach ($m as $key => $value) {
             if (in_array($key ,['emailSupporter','femail'])) {
                $value = sanitize_text_field($value);
                $m[$key] = sanitize_email($value);
                $email =  $value;
            }else if ($key == "activeCode" ) {
                if(strlen($value)<1){
                    continue;
                }
                $state = $efbFunction->is_efb_pro($value);
                $m['activeCode'] = sanitize_text_field($value);
                if ($state==false) {
                    $response = ['success' => false, "m" =>$lang['activationNcorrect']];
                    if(strlen($value) > 1){ wp_send_json_success($response, 200);}
                }
            }else if($key == "emailTemp"){
                if( strlen($value)>5  && strpos($setting ,'shortcode_message')===false){
                    $response = ['success' => false, "m" =>$lang['addSCEmailM']];
                    wp_send_json_success($response, 200);
                }else if(strlen($value)<6 && strlen($value)>0 ){
                    $response = ['success' => false, "m" =>$lang['emailTemplate']];
                    wp_send_json_success($response, 200);
                }else if(strlen($value)>50001){
                    $response = ['success' => false, "m" =>$lang['addSCEmailM']];
                    wp_send_json_success($response, 200);
                }
                  // â”€â”€ XSS Prevention: strip dangerous patterns before wp_kses â”€â”€
                  $v = str_replace('@efb@' , '/', $value);

                  // Preserve EFBDATA comment (URI-encoded builder JSON, safe)
                  $efbdata_comment = '';
                  if (preg_match('/<!--\s*EFBDATA:([\S]+)\s*-->/', $v, $efb_match)) {
                      $efbdata_comment = $efb_match[0];
                      $v = str_replace($efbdata_comment, '', $v);
                  }

                  // Strip script tags (case-insensitive)
                  $v = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $v);
                  $v = preg_replace('/<\s*script[^>]*>/i', '', $v);
                  // Strip all event handler attributes (onclick, onerror, onload, etc.)
                  $v = preg_replace('/\bon\w+\s*=\s*(["\'][^"]*["\']|[^\s>]+)/i', '', $v);
                  // Strip javascript: / vbscript: / dangerous data: URIs
                  $v = preg_replace('/javascript\s*:/i', '', $v);
                  $v = preg_replace('/vbscript\s*:/i', '', $v);
                  $v = preg_replace('/data\s*:\s*text\/html/i', '', $v);
                  $v = preg_replace('/data\s*:\s*text\/javascript/i', '', $v);
                  $v = preg_replace('/data\s*:\s*application\//i', '', $v);
                  // Strip dangerous CSS expressions
                  $v = preg_replace('/expression\s*\(/i', '', $v);
                  $v = preg_replace('/-moz-binding\s*:/i', '', $v);
                  $v = preg_replace('/behavior\s*:/i', '', $v);
                  // Strip iframe, object, embed, form, input, math tags
                  // Note: svg removed from this list â€” social icons use base64 <img> now,
                  // but keep stripping raw SVG tags for XSS safety
                  $v = preg_replace('/<\s*\/?(iframe|object|embed|form|input|textarea|button|select|svg|path|math|base|link|applet)[^>]*>/i', '', $v);
                  // Now run through wp_kses (with data: protocol allowed for base64 images)
                  $v = $efbFunction->sanitize_full_html_efb($v);
                  // wp_kses converts single-quoted HTML attributes to double quotes,
                  // which causes JSON escaping issues when stored. Convert back to single quotes.
                  $v = str_replace('"', "'", $v);

                  // Re-append preserved EFBDATA comment
                  if ($efbdata_comment) {
                      $v .= "\n" . $efbdata_comment;
                  }

                  $m[$key] = str_replace('/' , '@efb@', $v);
            }else if($key == 'smtp'){

                function result_ok() {
                    return [
                        'status' => 'ok_set_smtp',
                        'message' => [
                            'title' => 'configured',
                            'description' => 'user configured email settings',
                            'id' => 'email_settings_configured'
                        ]
                    ];
                }
                if(isset($value) && in_array($value,[1,true,'true','1']) ){

                  $check =  get_option('emsfb_email_status',false);
                    if($check==false || $check==null){
                         update_option('emsfb_email_status', result_ok());
                    }else if($check['status']!='ok_set_smtp' || $check['status']!='ok'){

                            update_option('emsfb_email_status', result_ok());
                    }

                }


            }else{
                $m[$key] = sanitize_text_field($value);
            }
        }

        if(isset($m['efb_version'])==false){
           $m['efb_version'] = EMSFB_PLUGIN_VERSION;
        }

        // Handle devMode toggle â€” stored in wp_options, not in the settings JSON
        // IMPORTANT: Store as '1'/'0' strings, NOT booleans â€” WordPress update_option
        // silently fails when saving boolean false (treats it as "option not set").
        if(isset($m['devMode'])){
            $dev_mode_value = in_array($m['devMode'], [true, 'true', 1, '1'], true) ? '1' : '0';
            update_option('emsfb_dev_mode', $dev_mode_value);
            unset($m['devMode']); // Remove from settings JSON â€” it's stored in wp_options
        }

        // json_encode produces valid JSON; wpdb->insert handles DB escaping.
        // Do NOT add extra str_replace('"','\"') â€” it creates double-escaping
        // that breaks json_decode when values contain literal quotes (e.g. emailTemp HTML).
        $setting = json_encode($m, JSON_UNESCAPED_UNICODE);
        $email = isset($m['emailSupporter']) ? $m['emailSupporter'] : wp_get_current_user()->user_email;
        $efbFunction->set_setting_Emsfb( $setting, $email );
        $m = $lang['messageSent'];
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, 200);
    }

    public function get_ajax_track_admin() {
        // Ø¬Ø³ØªØ¬ÙˆÛŒ Ø¬Ø§Ù…Ø¹ Ø¯Ø± Ø¯Ùˆ Ø¬Ø¯ÙˆÙ„: wp_emsfb_msg_ Ùˆ wp_emsfb_rsp_
        // Search in: track, content (JSON) fields from msg table
        // AND content (JSON) field from response table with JOIN

        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["notFound","error403"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $table_name_rsp = $this->db->prefix . "emsfb_rsp_";
        $id = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

        // First try exact match in track field
        $value = $this->db->get_results($this->db->prepare("SELECT * FROM `$table_name` WHERE track = %s", $id));

        if (count($value) > 0) {
            $code = 'efb'. $value[0]->msg_id;
			$code = wp_create_nonce($code);
            $response = ['success' => true, "ajax_value" => $value,'nonce_msg'=> $code , 'id'=>$value[0]->msg_id];
        }
        else {
            // Enhanced search: search in both tables with JSON content support
            $search_term = "%$id%";

            // Search in messages table (track and content fields)
            $sql_msg = $this->db->prepare(
                "SELECT DISTINCT m.* FROM {$table_name} m
                 WHERE m.track LIKE %s OR m.content LIKE %s",
                $search_term, $search_term
            );

            // Search in responses table (content field) and join with messages
            $sql_rsp = $this->db->prepare(
                "SELECT DISTINCT m.* FROM {$table_name} m
                 INNER JOIN {$table_name_rsp} r ON m.msg_id = r.msg_id
                 WHERE r.content LIKE %s",
                $search_term
            );

            // Combine both queries and remove duplicates
            $combined_sql = "($sql_msg) UNION ($sql_rsp) ORDER BY date DESC";

            $value = $this->db->get_results($combined_sql);

            if(count($value) > 0){
                $code = 'efb'. $value[0]->msg_id;
                $code = wp_create_nonce($code);
                $response = ['success' => true, "ajax_value" => $value,'nonce_msg'=> $code , 'id'=>$value[0]->msg_id];
            } else {
                $m = $lang['notFound'];
                $response = ['success' => false, "m" => $m];
            }
        }
        wp_send_json_success($response, 200);
    }// end function
    public function clear_garbeg_admin() {
        // Ù¾Ø§Ú© Ú©Ø±Ø¯Ù† ÙØ§ÛŒÙ„ Ù‡Ø§ÛŒ Ø§Ø¶Ø§ÙÛŒ
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["fileDeleted","error403"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $m = $lang['error403'];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $value      = $this->db->get_results("SELECT content FROM `$table_name`");
        $urlsDB     = [];
        foreach ($value as $v) {
            if (strpos($v->content, 'url') != false) {
                $jsn  = $v->content;
                $jsn  = str_replace('\\', '', $jsn);
                $json = json_decode($jsn);
                foreach ($json as $keyR => $row) {
                    foreach ($row as $key => $val) {
                        if ($key == "url" && $val != "" && gettype($val) == 'string') {
                            array_push($urlsDB, $val);
                        }
                    }
                }
            }
        }
        $upload_dir = wp_upload_dir();
        // $arrayFiles=[] ;
        $files    = list_files($upload_dir['basedir']);
        $urlDBStr = json_encode($urlsDB);
        foreach ($files as &$file) {
            if (strpos($file, 'emsfb-PLG-') != false) {
                $namfile = strrchr($file, '/');
                if (strpos($urlDBStr, $namfile) == false) {
                    // array_push($arrayFiles,$file);
                    wp_delete_file($file);
                }
            }
        }
        $m = $lang['fileDeleted'];
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, 200);
    }// end function
    public function check_email_server_admin() {
        // Ù¾Ø§Ú© Ú©Ø±Ø¯Ù† ÙØ§ÛŒÙ„ Ù‡Ø§ÛŒ Ø§Ø¶Ø§ÙÛŒ
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["error403","emailServer"];
        $lang= $efbFunction->text_efb($text);
        $m = $lang['error403'];
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        $pro = $efbFunction->is_efb_pro(1);

        $con ='';
        $sub='';
        $to ='';
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $post_email = isset($_POST['email']) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if('testMailServer'==$post_value){
            if(is_email( $post_email)){
                $to = $post_email;
            }
            $m = $lang['emailServer'];
            $sub ="ðŸ“« ". $m ." [".esc_html__('Easy Form Builder','easy-form-builder') ."]";
            $cont = "Test Email Server";
            if(strlen($to)<5) {
                if(strlen($ac->emailSupporter)!=0) {$to = $ac->emailSupporter;}else{
                    $to="null";
                }
            }
            $server_name =  str_replace("www.", "",  isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'yourdomain.com');
            $from = "no-reply@".$server_name;
            if(isset($ac->femail) && strlen($ac->femail)>5){
                $from =$ac->femail ;
            }
        }
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $check = $efbFunction->send_email_state_new([$to , null,$from] ,$sub ,$cont,$pro,'testMailServer',home_url(),$ac);
                if($check==true){
                   $ac->smtp = true;
                    $ac->emailSupporter = $to;
                    // json_encode produces valid JSON; set_setting_Emsfb handles DB insert.
                    // Do NOT add str_replace('"','\"') â€” it double-escapes and breaks json_decode.
                    // Do NOT call $this->db->insert separately â€” set_setting_Emsfb already inserts.
                     $ok =  [
                        'status' => 'ok_set_smtp',
                        'message' => [
                            'title' => 'configured',
                            'description' => 'user configured email settings',
                            'id' => 'email_settings_configured'
                        ]
                        ];
                    update_option('emsfb_email_status',$ok);
                    $efbFunction->set_setting_Emsfb( $ac, $to );
                }
        $response = ['success' => $check ];
        wp_send_json_success($response, 200);
    }
    public function isHTML($str) {
        return preg_match("/\/[a-z]*>/i", $str) != 0;
    }
    public function get_ip_address() {
        // source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
        $ip='1.1.1.1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }
    public function get_not_read_message() {
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $table_name = $this->db->prefix . "emsfb_msg_";
        $sql = "SHOW TABLES LIKE %s";
        $exists = $this->db->get_var($this->db->prepare($sql, $table_name));
        if ($exists){
            $value      = $this->db->get_results("SELECT msg_id,form_id FROM `$table_name` WHERE read_=0");
            $rtrn       = 'null';
            return $value;
        }
        return [];
    }
        public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
        public function fun_addon_new($url){
            $name =substr($url,strrpos($url ,"/")+1,-4);
            $r =download_url($url);
            if(is_wp_error($r)){
                // show error message
            }else{
                $directory = EMSFB_PLUGIN_DIRECTORY . '//temp';
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                $r = rename($r, EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip');
                if(is_wp_error($r)){
                    return false;
                }else{
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    $r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
                    if(is_wp_error($r)){
                        return false;
                    }
                    return true;
                }
            }
            // run install php of addons
            $fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/".$name."/".$name.".php";
            if(file_exists($fl_ex)){
                $name ='\Emsfb\\'.$name;
                require_once  $fl_ex;
                $t = new $name();
            }
        }
    public function file_upload_public(){


        $_POST['id'] = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $_POST['pl'] = isset($_POST['pl']) ? sanitize_text_field( wp_unslash($_POST['pl'])) : '';
        $_POST['nonce_msg'] = isset($_POST['nonce_msg']) ? sanitize_text_field( wp_unslash($_POST['nonce_msg'])) : '';
        $vl=null;


        $post_pl = isset($_POST['pl']) ? sanitize_text_field( wp_unslash( $_POST['pl'] ) ) : '';
        if($post_pl != "msg"){
            $post_id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
            $vl ='efb'. $post_id;
        }else{
            $id = isset($_POST['id']) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
            $table_name = $this->db->prefix . "emsfb_form";
            $vl  = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");
            if($vl!=null){
                if(strpos($vl , '\"type\":\"dadfile\"') || strpos($vl , '\"type\":\"file\"') || strpos($vl , '"type":"dadfile"') || strpos($vl , '"type":"file"')){
                    $vl ='efb'.$id;

                }

            }

        }



		if (check_ajax_referer('public-nonce','nonce')!=1 && check_ajax_referer($vl,"nonce_msg")!=1){


			$response = array( 'success' => false  , 'm'=>"403 Forbidden Error");
			wp_send_json_success($response,200);
			die();
		}

		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,'image/heic',
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' , 'video/mpeg', 'video/mpg', 'audio/mpg','video/mov','video/quicktime',
		 'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip','application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip',"zip","rar","tar","gz","gzip","application/x-rar-compressed","application/x-tar","application/x-gzip","application/gzip","multipart/x-compressed","multipart/x-rar-compressed"
		);


		if (isset($_FILES['file']['name'])) {
			$_FILES['file']['name'] = sanitize_file_name($_FILES['file']['name']);
		}

		if (isset($_FILES['file']['type']) && in_array($_FILES['file']['type'], $arr_ext)) {


            $file_name = isset($_FILES['file']['name']) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
            $file_tmp = isset($_FILES['file']['tmp_name']) ? sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) : '';
            $file_type = isset($_FILES['file']['type']) ? sanitize_text_field( wp_unslash( $_FILES['file']['type'] ) ) : '';
            $name = 'efb-PLG-'. wp_date("ymd"). '-'.substr(str_shuffle("..."), 0, 8).'.'.pathinfo($file_name, PATHINFO_EXTENSION) ;
            $upload = wp_upload_bits($name, null, file_get_contents($file_tmp));
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=> $file_type);
			  wp_send_json_success($response,200);
		}else{
			$response = array( 'success' => false  ,'error'=>"File Type Error");
			wp_send_json_success($response,200);
			die('invalid file '. $file_type);
		}


	}
    public function custom_ui_plugins(){
           // // Check if wpbakery available
           if( is_plugin_active('js_composer/js_composer.php')){
                // first check wp bakery addons installed or not
                //  if wp bakery is not installed
                //  first install after that call wp bakery function
                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery")){
                 }
                 // require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery/wpb_extend.php");
                 // require_once(EMSFB_PLUGIN_DIRECTORY."/includes/page-builders/wpbakery/class-Emsfb-wpbakery.php");
             }
             // WPBakery integration is now loaded via class-Emsfb.php load_page_builder_integrations()
             // Check if Gutenberg editor is available
             if (function_exists('register_block_type')) {
                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/gutenberg")){
                }
             }
    }
    public function send_sms_admin_Emsfb(){

        $efbFunction = get_efbFunction();
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
       if(!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {

            $response = ['success' => false, 'm' =>'Security Error'];
            wp_send_json_success($response, 200);
        }
        $path = EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php";
        if(!file_exists($path)){
            $response = ['success' => false, 'm' =>'SMS Add-on Not Installed'];
            wp_send_json_success($response, 200);
        }

        require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended/smsefb.php");
        $smssendefb = new smssendefb();
        $smssendefb->send_sms_Emsfb($_POST);

    }
    public function fun_duplicate_Emsfb(){
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["error403","somethingWentWrongPleaseRefresh","copy"];
        $lang= $efbFunction->text_efb($text);
         $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can){
            $response = ['success' => false, 'm' =>$lang['error403']];
            wp_send_json_success($response, 200);
        }
        $post_id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
        $post_type = isset($_POST['type']) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        if (empty($post_id) || empty($post_type)) {
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            wp_send_json_success($response,200);
        }
        $id =  ( int ) $post_id ;
        $type = $post_type ;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        if($type =='form'){
            $table_name = $this->db->prefix . "emsfb_form";
            $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id'");
            if(count($value)<1){
                $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
                wp_send_json_success($response,200);
            }
            $val = $value[0];
            $form_name = $val->form_name . " - " . $lang['copy'];
            $date = wp_date('Y-m-d H:i:s');
            $r =$this->db->insert($table_name, array(
                'form_name' =>  $form_name,
                'form_structer' => $val->form_structer,
                'form_email' => $val->form_email,
                'form_created_by' => get_current_user_id(),
                'form_type'=>$val->form_type,
                'form_create_date' =>  $date,
            ));
            $this->id_  = $this->db->insert_id;
            // get inserted value
            $response = ['success' => true, "m" =>$lang['copy'] , 'form_id'=>$this->id_ , 'form_name'=>$form_name ,
            'date'=>$date , 'form_type'=>$val->form_type];
            wp_send_json_success($response, 200);
        }
    }
    public function loading_card_efb(){
        echo "<div class='efb row justify-content-center card-body text-center efb mt-5 pt-3'>
                    <div class='efb col-md-3 col-sm-3 mx-0 my-1 d-flex flex-column align-items-center'>
                        <img class='efb w-50' src='". EMSFB_PLUGIN_URL . "includes/admin/assets/image/efb-256.gif'>
                        <h3 class='efb fs-3 text-darkb'>".  esc_html__('Easy Form Builder','easy-form-builder') ."</h3>
                        <h3 class='efb fs-2 text-dark'>".  esc_html__('Please Wait','easy-form-builder') ."</h3>
                    </div>
                </div> ";
    }
    public function delete_messages_Emsfb(){
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["error403","somethingWentWrongPleaseRefresh","delete"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $response = ['success' => false, 'm' =>$lang['error403']];
            wp_send_json_success($response, 200);
        }
        if (empty($_POST['val'])) {
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            wp_send_json_success($response,200);
        }
        $state = sanitize_text_field(wp_unslash( $_POST['state'] ) ) ;
        $val =   sanitize_text_field(wp_unslash( $_POST['val'] ) ) ;
        $val_  = str_replace('\\', '', $val);
        $val = json_decode($val_ ,true);
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        if($state =='msg'){
            $table_name = $this->db->prefix . "emsfb_msg_";
            $msg_ids ='';
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $msg_ids !='' ? $msg_ids .=','.$value['msg_id'] : $msg_ids .= $value['msg_id'];
                }
            }
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            if($msg_ids !=''){
                $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                $r = $this->db->query($sql);
                // delete  all responses from table emsfb_rsp_ where msg_id in ($msg_ids)
                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";
                    $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                    $r = $this->db->query($sql);
                }
                $response = ['success' => true, "m" =>$lang['delete']];
            }
            wp_send_json_success($response, 200);
        }
    }
    public function read_list_Emsfb(){
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["error403","somethingWentWrongPleaseRefresh","done"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $response = ['success' => false, 'm' =>$lang['error403']];
            wp_send_json_success($response, 200);
        }
        if (empty($_POST['val'])) {
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            wp_send_json_success($response,200);
        }
        $state = sanitize_text_field(wp_unslash( $_POST['state'] ) ) ;
        $val =  sanitize_text_field(wp_unslash( $_POST['val'] ) ) ;
        $val_  = str_replace('\\\\', '', $val);
        $val_  = str_replace('\\', '', $val);
        $val = json_decode($val_ ,true);
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        if($state =='msg'){
            $table_name = $this->db->prefix . "emsfb_msg_";
            $msg_ids ='';
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $msg_ids !='' ? $msg_ids .=','.$value['msg_id'] : $msg_ids .= $value['msg_id'];
                }
            }
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            $user_id = get_current_user_id();
            if($msg_ids !='' ){
                // $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                $sql = "UPDATE $table_name SET read_ = 1 WHERE msg_id IN ($msg_ids)";
                $r = $this->db->query($sql);
                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";
                    // $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                    $sql = "UPDATE $table_name SET read_ = 1 WHERE msg_id IN ($msg_ids)";
                    $r = $this->db->query($sql);
                }
                
        }
        wp_send_json_success($response, 200);
    }
    public function check_and_enqueue_font_roboto_Emsfb() {
        $font_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap';
        $response = wp_remote_head($font_url);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            wp_register_style('Font_Roboto', $font_url);
            wp_enqueue_style('Font_Roboto');
        } else {
        }
    }
    public function heartbeat_Emsfb(){
        $efbFunction = get_efbFunction();
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {

            $response = ['success' => false, 'm' =>'Security Error'];
            wp_send_json_success($response, 200);
        }
        $new_nonce = wp_create_nonce('wp_rest');
        $response = ['success' => true, "m" =>'heartBeat' , 'newNonce'=>$new_nonce];
        wp_send_json_success($response, 200);
    }
    public function report_problem_Emsfb(){
        $efbFunction = get_efbFunction();
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {

            $response = ['success' => false, 'm' =>'Security Error'];
            wp_send_json_success($response, 200);
        }

        $state = isset($_POST['state']) ? sanitize_text_field( wp_unslash($_POST['state'])) : '';
        $value = isset($_POST['value']) ? sanitize_text_field( wp_unslash($_POST['value'])) : '';
        $this->efbFunction = get_efbFunction();
        $this->efbFunction->report_problem_efb($state , $value);
        $response = ['success' => true, "m" =>'report_problem_done'];
        wp_send_json_success($response, 200);
    }
  /*   function efb_check_shared_hosting_and_store() {
        // Retrieve the stored result from wp_options
        $is_shared_hosting = get_option('efb_is_shost');
        // If no stored result is found
        if ($is_shared_hosting === false) {
            // Check for shared hosting
            $hostname = gethostname();
            $ip = gethostbyname($hostname);
            $hostnames = gethostbynamel($hostname);
            // Assume shared hosting if more than one IP address is associated with the hostname
            if ($hostnames !== false && count($hostnames) > 1) {
                // Shared hosting
                $is_shared_hosting = 1;
            } else {
                // Dedicated or VPS hosting
                $is_shared_hosting = 2;
            }
            // Store the result in wp_options with the prefix efb_
            update_option('efb_is_shost', $is_shared_hosting);
        }
        return $is_shared_hosting;
    } */
    function create_temporary_links_table_Emsfb() {


		global $wpdb;

		$table_name = $wpdb->prefix . 'emsfb_temp_links';
		$table_exists = get_option('emsfb_temp_links_table_exists', false);
		// Check if the table already exists
		if ($table_exists===false) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				id INT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				username VARCHAR(60) NOT NULL,
				created_at DATETIME NOT NULL,
				code VARCHAR(60) NOT NULL,
				ip_address VARCHAR(45) NOT NULL,
				status_ TINYINT(1) NOT NULL,
				PRIMARY KEY (id)
			) $charset_collate;";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
            update_option('emsfb_temp_links_table_exists', true);
		}


	}

function admin_notices_efb () {
             $check = get_option('emsfb_email_status', false);
            function result_ok ($ok) {
                   $r['status'] = $ok;
                   $r['message']['title'] = 'configured';
                   $r['message']['description'] = 'user configured email settings';
                   $r['message']['id'] = 'email_settings_configured';
                   return $r;
            }
            $efbFunction = get_efbFunction();
            $settings= get_setting_Emsfb('decoded');

            if(is_array($check)){
                    if($check['status'] === 'ok_set_smtp') {
                        return;
                    }else if ($check['status'] === 'ok' ) {
                        if (isset($settings->smtp) && !in_array($settings->smtp, ['1', 'true', true,1], true)) {
                            $settings->smtp = true;
                            $email = isset($settings->emailSupporter) ? $settings->emailSupporter : '';
                            $efbFunction->set_setting_Emsfb($settings, $email);
                        }

                        return;
                    }else if (($check['status'] !== 'ok' || $check['status'] !== 'ok_set_smtp') && (isset($settings->smtp) && in_array($settings->smtp, ['1', 'true', true,1], true))) {
                            update_option('emsfb_email_status',  result_ok('ok_set_smtp'));
                            return;
                    }
            }else{
                if (isset($settings->smtp) && in_array($settings->smtp, ['1', 'true', true,1], true)) {
                       update_option('emsfb_email_status', result_ok('ok_set_smtp'));
                       return;
                }else{
                    require_once (EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-requirement.php');
                    $efbRequirement = new CheckRequirementEmsfb();
                    $efbRequirement->run_and_save_efb();
                    $check = get_option('emsfb_email_status', false);
                    if(is_array($check)  && isset($check['status']) && ($check['status'] == 'ok_set_smtp' || $check['status'] == 'ok')) {
                        if (isset($settings->smtp) && !in_array($settings->smtp, ['1', 'true', true,1], true)) {
                            $settings->smtp = true;
                            $email = isset($settings->emailSupporter) ? $settings->emailSupporter : '';
                            $efbFunction->set_setting_Emsfb($settings, $email);
                        }
                        return;
                    }
                }

            }
            $email_notifi = sprintf(
                esc_html__('%s notification', 'easy-form-builder'),
                esc_html__('Email', 'easy-form-builder')
            );

            $warning =' '. sprintf(
                esc_html__('Disabling this feature may affect the proper functionality of Easy Form Builder. If you plan to use the %s feature, please ensure it is enabled.', 'easy-form-builder'),
                $email_notifi
            );

             $messages = [
                'mail_function_ok' => [
                    'title' => esc_html__('Email system is working properly.', 'easy-form-builder'),
                    'description' => esc_html__('Your server is able to send emails using the default PHP mail system.', 'easy-form-builder'),
                ],
                'mail_function_missing' => [
                    'title' => esc_html__('Email system is not available.', 'easy-form-builder'),
                    'description' => esc_html__('The PHP mail() function is missing. Your server cannot send emails.', 'easy-form-builder') . $warning,
                ],
                'mail_function_disabled' => [
                    'title' => esc_html__('Email sending is blocked by server settings.', 'easy-form-builder'),
                    'description' => esc_html__('The mail() function is disabled in your server PHP configuration (php.ini).', 'easy-form-builder') . $warning,
                ],
                'wp_mail_function_missing' => [
                    'title' => esc_html__('WordPress mail function not found.', 'easy-form-builder'),
                    'description' => esc_html__('The wp_mail() function is missing or not available. WordPress email features may be broken.', 'easy-form-builder') . $warning,
                ],
                'smtp_sendmail_empty' => [
                    'title' => esc_html__('No email handler configured.', 'easy-form-builder'),
                    'description' => esc_html__('Your server has no SMTP host or sendmail path set. Emails may not be delivered.', 'easy-form-builder') ,
                ],
                'mail_function_failed' => [
                    'title' => esc_html__('Test email could not be sent.', 'easy-form-builder'),
                    'description' => esc_html__('It seems that your WordPress site could not send a test email. To manually test your email system, go to Easy Form Builder > Settings > Email Settings tab and click the "Check Email Server" button.', 'easy-form-builder') . $warning,
                ],
            ];




            $logo_url = EMSFB_PLUGIN_URL.'includes/admin/assets/image/logo.png';
            $msg_id = isset($check['message']['id']) ? $check['message']['id'] : '';
            $help = '<a href="https://whitestudio.team/documents/how-to-fix-email-not-working-issue#'.$msg_id.'" target="_blank" >' . esc_html__('Click here for more details','easy-form-builder') . '</a>';
            $title = isset($messages[$msg_id]['title']) ? $messages[$msg_id]['title'] : esc_html__('Email Issue', 'easy-form-builder');
            $description = isset($messages[$msg_id]['description']) ? $messages[$msg_id]['description'] : '';
            ob_start();
            ?>
            <div id="notice-email-efb" class="notice notice-error efb-notice-email-error notice-alt efb" style="display:flex;align-items:flex-start;gap:12px;padding:10px 20px;position:relative;z-index:1000;">
               <button type="button" id="efb-close-notice-btn"
            style="position:absolute;top:8px;right:8px;background:transparent;border:none;font-size:20px;cursor:pointer;"
            aria-label="Close">&times;</button>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('Easy Form Builder', 'easy-form-builder'); ?>" style="width:46px;height:auto;margin-top:4px;" />
                <div>
                    <p><strong><?php echo esc_html__('Easy Form Builder Email Warning:', 'easy-form-builder'); ?></strong> <?php echo esc_html($title); ?></p>
                    <p><?php echo esc_html($description); ?></p>
                    <p><?= $help ?></p>
                </div>
            </div>
            <script>
                var efbNotice = document.getElementById('notice-email-efb');
                // Check if notice has been dismissed permanently
                if (window.localStorage.getItem('efb_email_notice_dismissed') === 'true') {
                    if (efbNotice) efbNotice.style.display = 'none';
                }
                var efbCloseBtn = document.getElementById('efb-close-notice-btn');

                if (efbCloseBtn) {
                    //look for efb classes on the elements of page
                    const page = document.querySelector('.sideMenuFEfb');
                    if (page) {
                        efbNotice.style.display = 'none';
                    }
                    efbCloseBtn.addEventListener('click', function () {
                        console.log('Notice closed permanently');
                        var efbNotice = document.getElementById('notice-email-efb');
                        if (efbNotice) efbNotice.style.display = 'none';
                        // Mark notice as permanently dismissed
                        window.localStorage.setItem('efb_email_notice_dismissed', 'true');
                    });
                }
            </script>
            <?php
            $output = ob_get_clean();

            echo $output;
    }

    /**
     * Handle plan selection save via AJAX
     */
    public function efb_save_plan_selection() {
        // Verify nonce for security
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        // Check nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'wp_rest') || !$currrent_user_can) {
            $response = ['success' => false, 'm' => $lang['error403']];
            wp_send_json_error($response, 403);
            die("secure!");
        }

        // Check if plan_data is provided
        if (!isset($_POST['plan_data'])) {
            wp_send_json_error(array(
                'message' => __('Plan data is missing.', 'easy-form-builder')
            ), 400);
            return;
        }

        // Get and validate plan data
        $plan_data_raw = sanitize_textarea_field($_POST['plan_data']);
        $plan_data = json_decode(stripslashes($plan_data_raw), true);

        if (!is_array($plan_data)) {
            wp_send_json_error(array(
                'message' => __('Invalid plan data format.', 'easy-form-builder')
            ), 400);
            return;
        }

        // Extract plan information
        $selected_plan = isset($plan_data['selected_plan']) ? sanitize_text_field(wp_unslash($plan_data['selected_plan'])) : '';
        $timestamp = isset($plan_data['timestamp']) ? intval($plan_data['timestamp']) : time();
        if (!in_array($selected_plan, array('free', 'free_plus', 'pro'))) {
            wp_send_json_error(array(
                'message' => __('Invalid plan type.', 'easy-form-builder')
            ), 400);
            return;
        }

        // Process plan selection based on type
        $redirect_url = null;
        $action_performed = null;
        $package_type_efb = 2;
        switch($selected_plan) {
            case 'free':
                // free = 0 (no action required)
                update_option('emsfb_pro', 2);
                $action_performed = __('Free plan activated - no additional features.', 'easy-form-builder');

                break;

            case 'free_plus':
                // free_plus = 3 (set option to 3)
                update_option('emsfb_pro', 3);
                $action_performed = __('Free Plus plan activated with enhanced features.', 'easy-form-builder');
                $package_type_efb =3;
                break;

            case 'pro':
                // pro => redirect to whitestudio.team/#price
                $redirect_url = 'https://whitestudio.team/#price';
                $package_type_efb =0;
                // Check if site language is Persian (Farsi)
                if (get_locale() == 'fa_IR') {
                    $redirect_url = 'https://easyformbuilder.ir/#price';
                }
                $action_performed = __('Redirecting to Pro plan purchase page.', 'easy-form-builder');
                // Optionally set pro option as well
                // update_option('emsfb_pro', 1);

            break;
        }

        // Create response data
        $response_data = array(
            'success' => true,
            'message' => sprintf(__('Plan "%s" has been successfully processed.', 'easy-form-builder'), $selected_plan),
            'plan' => $selected_plan,
            'action' => $action_performed,
            'redirect_url' => $redirect_url,
            'timestamp' => $timestamp,
            'saved_at' => current_time('mysql'),
            'package_type' =>$package_type_efb
        );



        // Send success response
        wp_send_json_success($response_data);
    }

    /**
     * Update form cache with new data
     * Ø§ÛŒÙ† ØªØ§Ø¨Ø¹ Ø¨Ø±Ø§ÛŒ Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ cache Ø¨Ø¯ÙˆÙ† Ù†ÛŒØ§Ø² Ø¨Ù‡ Ø¯ÛŒØªØ§Ø¨ÛŒØ³ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯
     *
     * @param int $form_id Form ID
     * @param array $form_data Form data to cache
     * @param array $fields Fields being cached (optional)
     * @return bool Success status
     */
    public function update_form_cache_efb($form_id, $form_data, $fields = array('form_structer', 'form_type')) {
        $form_id = intval($form_id);

        if ($form_id <= 0 || empty($form_data)) {
            return false;
        }

        // Ensure database connection for compatibility
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }

        $cache_key = $form_id . '_' . md5(implode('_', $fields));

        // Convert array to object if needed (to match database result format)
        $cache_data = is_array($form_data) ? (object) $form_data : $form_data;

        // Update memory cache
        $this->form_cache[$cache_key] = $cache_data;

        // Update WordPress object cache
        wp_cache_set('efb_form_' . $cache_key, $cache_data, 'emsfb', 3600);

        return true;
    }

    /**
     * Bulk update multiple form caches
     * Ø¨Ø±Ø§ÛŒ Ø¨Ø±ÙˆØ²Ø±Ø³Ø§Ù†ÛŒ Ú†Ù†Ø¯ÛŒÙ† ÙØ±Ù… Ø¨Ù‡ ØµÙˆØ±Øª ÛŒÚ©Ø¬Ø§
     *
     * @param array $forms_data Array of form data: [form_id => form_data]
     * @param array $fields Fields being cached
     * @return array Results: [form_id => success_status]
     */
    public function bulk_update_form_cache_efb($forms_data, $fields = array('form_structer', 'form_type')) {
        $results = array();

        foreach ($forms_data as $form_id => $form_data) {
            $results[$form_id] = $this->update_form_cache_efb($form_id, $form_data, $fields);
        }

        return $results;
    }

    /**
     * Clear form cache for specific form or all forms
     * Ù¾Ø§Ú© Ú©Ø±Ø¯Ù† cache Ù…Ø®ØµÙˆØµ ÙØ±Ù… Ø®Ø§Øµ ÛŒØ§ Ù‡Ù…Ù‡ ÙØ±Ù…â€ŒÙ‡Ø§
     *
     * @param int|null $form_id Form ID (null for all forms)
     * @param array $fields Specific fields to clear (empty for all)
     * @return bool Success status
     */
    public function clear_form_cache_efb($form_id = null, $fields = array()) {
        // Clear all memory cache if no specific form
        if ($form_id === null) {
            $this->form_cache = array();

            // Clear WordPress object cache group
            wp_cache_flush_group('emsfb');

            return true;
        }

        $form_id = intval($form_id);

        // Clear specific form cache
        if (empty($fields)) {
            // Clear all field combinations for this form
            $fields_to_clear = array(
                array('form_structer', 'form_type'),
                array('form_structer'),
                array('form_type'),
                array('form_name'),
                array('form_name', 'form_structer')
            );
        } else {
            $fields_to_clear = array($fields);
        }

        foreach ($fields_to_clear as $field_set) {
            $cache_key = $form_id . '_' . md5(implode('_', $field_set));

            // Clear memory cache
            unset($this->form_cache[$cache_key]);

            // Clear WordPress object cache
            wp_cache_delete('efb_form_' . $cache_key, 'emsfb');
        }

        return true;
    }

    /**
     * Get form cache status and statistics
     * Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ùˆ Ø¢Ù…Ø§Ø± cache ÙØ±Ù…â€ŒÙ‡Ø§
     *
     * @return array Cache statistics
     */
    public function get_form_cache_stats_efb() {
        $memory_cache_count = count($this->form_cache);
        $memory_size_estimate = strlen(serialize($this->form_cache));

        return array(
            'memory_cache_items' => $memory_cache_count,
            'memory_size_bytes' => $memory_size_estimate,
            'memory_size_mb' => round($memory_size_estimate / 1024 / 1024, 2),
            'cache_keys' => array_keys($this->form_cache)
        );
    }

    /**
     * Validate and refresh cache for specific form
     *
     * @param int $form_id Form ID
     * @param array $fields Fields to validate
     * @param bool $force_refresh Force refresh even if cache exists
     * @return object|null Form data or null if not found
     */
    public function validate_and_refresh_cache_efb($form_id, $fields = array('form_structer', 'form_type'), $force_refresh = false) {
        $form_id = intval($form_id);
        $cache_key = $form_id . '_' . md5(implode('_', $fields));

        // Clear existing cache if force refresh
        if ($force_refresh) {
            unset($this->form_cache[$cache_key]);
            wp_cache_delete('efb_form_' . $cache_key, 'emsfb');
        }

        // Check if cache exists and is valid
        if (!$force_refresh && isset($this->form_cache[$cache_key])) {
            return $this->form_cache[$cache_key];
        }

        // Ensure database connection
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }

        // Fetch fresh data from database
        $table_name = $this->db->prefix . "emsfb_form";
        $fields_str = implode(', ', array_map('esc_sql', $fields));

        $result = $this->db->get_results(
            $this->db->prepare(
                "SELECT {$fields_str} FROM `{$table_name}` WHERE form_id = %d ORDER BY form_id DESC LIMIT 1",
                $form_id
            )
        );

        if (!$result || empty($result)) {
            return null;
        }

        // Update cache with fresh data
        $this->form_cache[$cache_key] = $result[0];
        wp_cache_set('efb_form_' . $cache_key, $result[0], 'emsfb', 3600);

        return $result[0];
    }


}
new Admin();
