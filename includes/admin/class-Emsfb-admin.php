<?php
namespace Emsfb;

class Admin {

    public $ip;
    public $plugin_version;
    protected $db;
    private $form_cache = [];

    public function __construct() {
        $this->init_hooks();
        global $wpdb;
        $this->db = $wpdb;
    }

    private function init_hooks() {
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
            add_action('wp_ajax_remove_id_Emsfb', [$this, 'delete_form_id_public']);
            add_action('wp_ajax_remove_message_id_Emsfb', [$this, 'delete_message_id_public']);
            add_action('wp_ajax_get_form_id_Emsfb', [$this, 'get_form_id_Emsfb']);
            add_action('wp_ajax_get_messages_id_Emsfb', [$this, 'get_messages_id_Emsfb']);
            add_action('wp_ajax_get_all_response_id_Emsfb', [$this, 'get_all_response_id_Emsfb']);
            add_action('wp_ajax_update_form_Emsfb', [$this, 'update_form_id_Emsfb']);
            add_action('wp_ajax_update_message_state_Emsfb', [$this, 'update_message_state_Emsfb']);
            add_action('wp_ajax_set_replyMessage_id_Emsfb', [$this, 'set_replyMessage_id_Emsfb']);
            add_action('wp_ajax_set_settings_Emsfb', [$this, 'set_settings_Emsfb']);
            add_action('wp_ajax_get_track_id_Emsfb', [$this, 'get_ajax_track_admin']);
            add_action('wp_ajax_clear_garbeg_Emsfb', [$this, 'clear_garbeg_admin']);
            add_action('wp_ajax_check_email_server_efb', [$this, 'check_email_server_admin']);
            add_action('wp_ajax_add_addons_Emsfb', [$this, 'add_addons_Emsfb']);
            add_action('wp_ajax_remove_addons_Emsfb', [$this, 'remove_addons_Emsfb']);
            add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));
            add_action('wp_ajax_send_sms_pnl_efb', [$this, 'send_sms_admin_Emsfb']);
            add_action('wp_ajax_dup_efb', [$this, 'fun_duplicate_Emsfb']);
            add_action('efb_loading_card', [$this, 'loading_card_efb']);
            add_action('wp_ajax_remove_messages_Emsfb', [$this, 'delete_messages_Emsfb']);
            add_action('wp_ajax_read_list_Emsfb', [$this, 'read_list_Emsfb']);
            add_action('wp_ajax_heartbeat_Emsfb' , [$this, 'heartbeat_Emsfb'] );
            add_action('wp_ajax_report_problem_Emsfb' , [$this, 'report_problem_Emsfb'] );
            add_action('wp_ajax_efb_save_plan_selection', [$this, 'efb_save_plan_selection']);

            add_action('create_temporary_links_table_Emsfb' , [$this , 'create_temporary_links_table_Emsfb']);

            add_action('admin_notices', [$this, 'admin_notices_efb']);

        }
    }
    public function add_cap() {
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
        if (strpos($hook, 'Emsfb') !== false && is_admin()) {

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
            wp_enqueue_style('wp-pointer');
            wp_enqueue_script('wp-pointer');
            $lang = get_locale();
            if (strlen($lang) > 0) {$lang = explode('_', $lang)[0];}
                wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
                 wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min-efb.js', array( 'jquery' ), EMSFB_PLUGIN_VERSION);
                wp_enqueue_script('efb-bootstrap-icon-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-icon-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
                wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js', array('jquery'), EMSFB_PLUGIN_VERSION);
                wp_enqueue_script('efb-response-viewer-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/response-viewer-efb.js', array('efb-main-js', 'jquery'), EMSFB_PLUGIN_VERSION);
        }
    }

    public function admin_menu() {
        $noti_count = count($this->get_not_read_message());
        $icon       = EMSFB_PLUGIN_URL . '/includes/admin/assets/image/logo-gray.png';
        add_menu_page(
            esc_html__('Panel', 'easy-form-builder'),
            $noti_count ? sprintf(esc_html__('Easy Form Builder', 'easy-form-builder') . ' <span id="efbCountM" class="efb awaiting-mod">%d</span>', $noti_count) : esc_html__('Easy Form Builder', 'easy-form-builder'),
            'Emsfb',
            'Emsfb',
            '',
            '' . $icon . ''
        );
        add_submenu_page('Emsfb', esc_html__('Panel', 'easy-form-builder'), esc_html__('Panel', 'easy-form-builder'), 'Emsfb', 'Emsfb', [$this, 'panel_callback']);
    }

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
        $post_value = isset($_POST['value']) ? wp_unslash( $_POST['value'] ) : '';
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
		$sms_msg_new_noti="";
		$sms_msg_responsed_noti="";
		$sms_msg_recived_user="";
		$sms_admins_phoneno="";

        $telegram_msg_new_noti = "";
        $telegram_msg_responsed_noti = "";
        $telegram_msg_recived_user = "";
        $telegram_bot_token = "";
        $telegram_admin_chat_ids = "";
        $settings = get_setting_Emsfb('decoded', []);

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

        if(isset($valp[0]['telegramnoti']) && intval($valp[0]['telegramnoti'])==1){
            $telegram_msg_new_noti = isset($valp[0]['telegram_msg_new_noti']) ? $valp[0]['telegram_msg_new_noti'] : $lang['newMessageReceived'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_msg_responsed_noti = isset($valp[0]['telegram_msg_responsed_noti']) ? $valp[0]['telegram_msg_responsed_noti'] : $lang['newResponse']."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_msg_recived_user = isset($valp[0]['telegram_msg_recived_usr']) ? $valp[0]['telegram_msg_recived_usr'] : $lang['WeRecivedUrM'] ."\n". $lang['trackNo'] .": [confirmation_code]\n". $lang['url'] .": [link_response]";
            $telegram_bot_token = isset($valp[0]['telegram_bot_token']) && !empty($valp[0]['telegram_bot_token']) ? $valp[0]['telegram_bot_token'] : get_option('emsfb_telegram_bot_token', '');
            $telegram_admin_chat_ids = isset($valp[0]['telegram_admin_chat_ids']) && !empty($valp[0]['telegram_admin_chat_ids']) ? $valp[0]['telegram_admin_chat_ids'] : get_option('emsfb_telegram_chat_id', '');

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
            $sms_exists = isset($settings->AdnSS) ? intval($settings->AdnSS) : false;
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

        if(isset($valp[0]['telegramnoti']) && intval($valp[0]['telegramnoti'])==1 ){
            $telegram_exists = isset($settings->AdnTLG) ? intval($settings->AdnTLG) : false;
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

        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $allw = ["AdnSPF","AdnOF","AdnPPF","AdnATC","AdnSS","AdnCPF","AdnESZ","AdnSE",
                 "AdnWHS","AdnPAP","AdnWSP","AdnSMF","AdnPLF","AdnMSF","AdnBEF","AdnPDP","AdnADP","AdnATF","AdnTLG","AdnGoS"];
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
            wp_send_json_error($response, 200);
            return;
        }

        if (!emsfb_is_addon_install_ready_efb()) {
            $status = emsfb_get_file_access_status_efb();
            $m = $status ? ($status['error_message'] ?? $status['current_message']) : esc_html__('File access status not checked yet. Please wait.', 'easy-form-builder');
            $response = ['success' => false, 'm' => $m];
            wp_send_json_error($response, 200);
            return;
        }

        $name_space = 'emsfb_addon_' . $post_value;

        $_server_name = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'localhost';
        $server_name = str_replace("www.", "", $_server_name);
        delete_option($name_space);
        $vwp = get_bloginfo('version');
        $vwp = substr($vwp,0,3);
        $vefb = EMSFB_PLUGIN_VERSION;
        $admin_test = get_option('EMSFB_team_test', '0') === '1';
		$domain =  $admin_test ? 'demo.whitestudio.team' : 'whitestudio.team';
        $u = 'https://' . $domain . '/wp-json/wl/v1/addons-link/' . $server_name . '/' . $post_value . '/' . $vwp . '/' . $vefb . '/';
        if (get_locale() == 'fa_IR') {
            $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/' . $server_name . '/' . $post_value . '/' . $vwp . '/' . $vefb . '/';
        }

        $max_attempts = 2;
        $attempt = 0;
        $success = false;
        $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ', 'easy-form-builder');
        $error_message = sprintf($error_message, $domain, 'not_success');

        while ($attempt < $max_attempts && !$success) {
            $request = wp_remote_get($u);

            if (is_wp_error($request)) {
                $attempt++;
                $error_message = esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server', 'easy-form-builder');
                if ($attempt >= $max_attempts) {
                    $response = ['success' => false, 'm' => $error_message];
                    wp_send_json_error($response, 200);
                    return;
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($request);
            if ($response_code != 200) {
                $attempt++;
                $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ', 'easy-form-builder');
                $error_message = sprintf($error_message, $domain, $response_code);
                if ($attempt >= $max_attempts) {
                    $response = ['success' => false, 'm' => $error_message];
                    wp_send_json_error($response, 200);
                    return;
                }
                continue;
            }

            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $attempt++;
                $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ', 'easy-form-builder');
                $error_message = sprintf($error_message, $domain, 'invalid_json');
                if ($attempt >= $max_attempts) {
                    $response = ['success' => false, 'm' => $error_message];
                    wp_send_json_error($response, 200);
                    return;
                }
                continue;
            }

            if ($data == null) {
                $attempt++;
                $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ', 'easy-form-builder');
                $error_message = sprintf($error_message, $domain, 'invalid_data');
                if ($attempt >= $max_attempts) {
                    $response = ['success' => false, 'm' => $error_message];
                    wp_send_json_error($response, 200);
                    return;
                }
                continue;
            }

            if ($data->status == false) {
                $error_message = esc_html__('Error: server (%s) responded with an invalid request. responded code : %s ', 'easy-form-builder');
                $error_message = sprintf($error_message, $domain, 'invalid_status');
                $response = ['success' => false, 'm' => $error_message];
                wp_send_json_error($response, 200);
                return;
            }

            if (version_compare(EMSFB_PLUGIN_VERSION, $data->v) == -1) {
                $m = $lang['upDMsg'];
                $response = ['success' => false, 'm' => $m];
                wp_send_json_error($response, 200);
                return;
            }

            if ($data->download == true) {
                $url = $data->link;
                $directory_name = substr($url, strrpos($url, "/") + 1, -4);
                $directory = EMSFB_PLUGIN_DIRECTORY . 'vendor/' . $directory_name;

                if (!file_exists($directory)) {
                    $result = $this->fun_addon_new($url);
                    if (is_wp_error($result)) {
                        $response = ['success' => false, 'm' => $result->get_error_message()];
                        wp_send_json_error($response, 200);
                        return;
                    }
                }
                update_option($name_space, 1);
                $success = true;
            }
        }

        if (!$success) {
            $response = ['success' => false, 'm' => $error_message];
            wp_send_json_error($response, 200);
            return;
        }

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
        }
        $ac->{$post_value}=1;
        $ac->efb_version=EMSFB_PLUGIN_VERSION;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }
        $efbFunction->set_setting_Emsfb( $ac, $ac->emailSupporter );
        $newAc = json_encode( $ac, JSON_UNESCAPED_UNICODE );
        update_option($name_space, 1);
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
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
        }
        $ac->{$post_value}=0;
        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }

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
            $response = ['success' => false, "m" => esc_html__("Something went wrong, please refresh the page." ,'easy-form-builder')];
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

        $decoded_form = json_decode( stripslashes( $value ) );
        if ( $decoded_form === null ) {

            $decoded_form = json_decode( $value );
        }
        $use_decoded = ( $decoded_form !== null && is_array( $decoded_form ) && ! empty( $decoded_form ) );

        if ( $use_decoded && ! empty( $decoded_form[0]->smsnoti ) && $decoded_form[0]->smsnoti === '1' ) {
            $sms_exists      = get_option( 'emsfb_addon_AdnSS', false );
            $smf_file_exist  = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
            if ( $sms_exists !== false && $smf_file_exist ) {
                require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
                $smsefb = new smssendefb();
                $sms    = $smsefb->get_sms_contact_efb( $id );
                if ( $sms ) {
                    $decoded_form[0]->sms_msg_new_noti      = isset( $sms->new_message_noti_user )   ? $sms->new_message_noti_user   : '';
                    $decoded_form[0]->sms_msg_responsed_noti = isset( $sms->new_response_noti )       ? $sms->new_response_noti       : '';
                    $decoded_form[0]->sms_msg_recived_usr   = isset( $sms->recived_message_noti_user ) ? $sms->recived_message_noti_user : '';
                    $decoded_form[0]->sms_admins_phone_no   = isset( $sms->admin_numbers )            ? $sms->admin_numbers            : '';
                }
            }
        }

        if ( $use_decoded && ! empty( $decoded_form[0]->telegramnoti ) && $decoded_form[0]->telegramnoti === '1' ) {
            $telegram_exists     = get_option( 'emsfb_addon_AdnTLG', false );
            $telegram_file_exist = file_exists( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
            if ( $telegram_exists !== false && $telegram_file_exist ) {
                require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/telegram-new-efb.php' );
                $telegramsendefb = new telegramsendefb();
                $telegram        = $telegramsendefb->get_telegram_contact_efb( $id );
                if ( $telegram ) {
                    $decoded_form[0]->telegram_msg_new_noti      = isset( $telegram->new_message_noti_user )    ? $telegram->new_message_noti_user    : '';
                    $decoded_form[0]->telegram_msg_responsed_noti = isset( $telegram->new_response_noti )        ? $telegram->new_response_noti        : '';
                    $decoded_form[0]->telegram_msg_recived_usr   = isset( $telegram->received_message_noti_user ) ? $telegram->received_message_noti_user : '';
                    $decoded_form[0]->telegram_bot_token         = isset( $telegram->bot_token )                 ? $telegram->bot_token                 : '';
                    $decoded_form[0]->telegram_admin_chat_ids    = isset( $telegram->admin_chat_ids )            ? $telegram->admin_chat_ids            : '';
                }
            }
        }

        if ( $use_decoded ) {
            $value = wp_json_encode( $decoded_form, JSON_UNESCAPED_UNICODE );
        }
        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, 200);
    }
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
        $post_message = isset($_POST['message']) ? wp_unslash( $_POST['message'] ) : '';
        if (empty($post_message)) {
            $m = $lang['PEnterMessage'];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
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
        $active_code_is_valid = false;
        foreach ($m as $key => $value) {
             if (in_array($key ,['emailSupporter','femail'])) {
                $value = sanitize_text_field($value);
                $m[$key] = sanitize_email($value);
                $email =  $value;
            }else if ($key == "activeCode" ) {
                if(strlen($value)<1){
                    if(get_option('emsfb_pro',false)==1){
                        update_option('emsfb_pro', 2);
                    }
                    continue;
                }
                $m['activeCode'] = sanitize_text_field($value);
                $state = $efbFunction->is_efb_pro($m['activeCode']);
                if ($state==true) {
                    $active_code_is_valid = true;
                    $m['package_type'] = 1;
                    $package_type = 1;
                    update_option('emsfb_pro', 1);
                } else {
                    $active_code_is_valid = false;
                    $m['package_type'] = 2;
                    $response = ['success' => false, "m" =>$lang['activationNcorrect']];
                    if(strlen($value) > 1){ wp_send_json_success($response, 200);}
                }
            }else if($key == 'package_type'){
                // Keep the validated Pro package even if payload contains stale package_type.
                if ($active_code_is_valid) {
                    $m[$key] = 1;
                    continue;
                }
                $package_type = intval(sanitize_text_field($value));
                $m[$key] = in_array($package_type, [0, 1, 2, 3], true) ? $package_type : 2;
            }else if($key == "emailTemp"){
                if( strlen($value)>5  && strpos($value ,'shortcode_message')===false){
                    $response = ['success' => false, "m" =>$lang['addSCEmailM']];
                    wp_send_json_success($response, 200);
                }else if(strlen($value)<6 && strlen($value)>0 ){
                    $response = ['success' => false, "m" =>$lang['emailTemplate']];
                    wp_send_json_success($response, 200);
                }else if(strlen($value)>50001){
                    $response = ['success' => false, "m" =>$lang['addSCEmailM']];
                    wp_send_json_success($response, 200);
                }
                  $v = str_replace('@efb@' , '/', $value);

                  $efbdata_comment = '';
                  if (preg_match('/<!--\s*EFBDATA:([\S]+)\s*-->/', $v, $efb_match)) {
                      $efbdata_comment = $efb_match[0];
                      $v = str_replace($efbdata_comment, '', $v);
                  }

                  $v = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $v);
                  $v = preg_replace('/<\s*script[^>]*>/i', '', $v);
                  $v = preg_replace('/\bon\w+\s*=\s*(["\'][^"]*["\']|[^\s>]+)/i', '', $v);
                  $v = preg_replace('/javascript\s*:/i', '', $v);
                  $v = preg_replace('/vbscript\s*:/i', '', $v);
                  $v = preg_replace('/data\s*:\s*text\/html/i', '', $v);
                  $v = preg_replace('/data\s*:\s*text\/javascript/i', '', $v);
                  $v = preg_replace('/data\s*:\s*application\//i', '', $v);
                  $v = preg_replace('/expression\s*\(/i', '', $v);
                  $v = preg_replace('/-moz-binding\s*:/i', '', $v);
                  $v = preg_replace('/behavior\s*:/i', '', $v);
                  $v = preg_replace('/<\s*\/?(iframe|object|embed|form|input|textarea|button|select|svg|path|math|base|link|applet)[^>]*>/i', '', $v);
                  $v = $efbFunction->sanitize_full_html_efb($v);
                  $v = str_replace('"', "'", $v);

                  if ($efbdata_comment) {
                      $v .= "\n" . $efbdata_comment;
                  }

                  $m[$key] = str_replace('/' , '@efb@', $v);
            }else if($key == 'weeklyEmailReport'){
                if (class_exists('\Emsfb\Email_Monitor') && \Emsfb\Email_Monitor::can_manage_setting()) {
                    \Emsfb\Email_Monitor::update_enabled($value);
                }
                $m[$key] = class_exists('\Emsfb\Email_Monitor')
                    ? \Emsfb\Email_Monitor::is_enabled()
                    : true;
            }else if($key == 'smtp'){
                if(isset($value) && in_array($value,[1,true,'true','1']) ){

                  $check =  get_option('emsfb_email_status',false);
                    if($check==false || $check==null){
                         update_option('emsfb_email_status', $this->build_email_ready_status_efb());
                    }else if(!is_array($check) || !isset($check['status']) || !in_array($check['status'], ['ok_set_smtp', 'ok'], true)){

                            update_option('emsfb_email_status', $this->build_email_ready_status_efb());
                    }

                }

            }else{
                $m[$key] = sanitize_text_field($value);
            }
        }

        if(isset($m['efb_version'])==false){
           $m['efb_version'] = EMSFB_PLUGIN_VERSION;
        }

        if(isset($m['devMode'])){
            $dev_mode_value = in_array($m['devMode'], [true, 'true', 1, '1'], true) ? '1' : '0';
            update_option('emsfb_dev_mode', $dev_mode_value);
            unset($m['devMode']);
        }

        if ($active_code_is_valid) {
            $m['package_type'] = 1;
        }

        $setting = json_encode($m, JSON_UNESCAPED_UNICODE);
        $email = isset($m['emailSupporter']) ? $m['emailSupporter'] : wp_get_current_user()->user_email;
        $efbFunction->set_setting_Emsfb( $setting, $email );
        $m = $lang['messageSent'];
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, 200);
    }

    public function get_ajax_track_admin() {

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

        $value = $this->db->get_results($this->db->prepare("SELECT * FROM `$table_name` WHERE track = %s", $id));

        if (count($value) > 0) {
            $code = 'efb'. $value[0]->msg_id;
			$code = wp_create_nonce($code);
            $response = ['success' => true, "ajax_value" => $value,'nonce_msg'=> $code , 'id'=>$value[0]->msg_id];
        }
        else {
            $search_term = "%$id%";

            $sql_msg = $this->db->prepare(
                "SELECT DISTINCT m.* FROM {$table_name} m
                 WHERE m.track LIKE %s OR m.content LIKE %s",
                $search_term, $search_term
            );

            $sql_rsp = $this->db->prepare(
                "SELECT DISTINCT m.* FROM {$table_name} m
                 INNER JOIN {$table_name_rsp} r ON m.msg_id = r.msg_id
                 WHERE r.content LIKE %s",
                $search_term
            );

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
    }
    public function clear_garbeg_admin() {
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
            if (strpos($v->content, 'url') !== false) {
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
        $files    = list_files($upload_dir['basedir']);
        $urlDBStr = json_encode($urlsDB);
        foreach ($files as &$file) {
            if (strpos($file, 'emsfb-PLG-') !== false) {
                $namfile = strrchr($file, '/');
                if (strpos($urlDBStr, $namfile) === false) {
                    wp_delete_file($file);
                }
            }
        }
        $m = $lang['fileDeleted'];
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, 200);
    }
    public function check_email_server_admin() {
        $efbFunction = get_efbFunction();
        $ac= get_setting_Emsfb('decoded');
        $text = ["error403","emailServer"];
        $lang= $efbFunction->text_efb($text);
        $m = $lang['error403'];
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
            $response = ['success' => false, 'm' => $m];
            $this->email_tester_log_efb('ajax_forbidden', [
                'mode' => isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'start',
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_success($response, 200);
            die("secure!");
        }
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'start';
        $this->email_tester_log_efb('ajax_request', [
            'mode' => $mode,
            'user_id' => get_current_user_id(),
            'site_url' => home_url(),
            'posted_email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'posted_sender_email' => isset($_POST['sender_email']) ? sanitize_email(wp_unslash($_POST['sender_email'])) : '',
            'test_hash' => isset($_POST['test_hash']) ? sanitize_text_field(wp_unslash($_POST['test_hash'])) : '',
            'run_id' => isset($_POST['run_id']) ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '',
        ]);
        if ($mode === 'result') {
            $test_hash = isset($_POST['test_hash']) ? sanitize_text_field(wp_unslash($_POST['test_hash'])) : '';
            $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
            $response = $this->get_email_tester_result_efb($test_hash, $efbFunction, $ac, $admin_email);
            wp_send_json_success($response, 200);
        }

        $response = $this->start_email_tester_efb($efbFunction, $ac);
        wp_send_json_success($response, 200);
    }

    private function start_email_tester_efb($efbFunction, $ac) {
        $admin_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (!is_email($admin_email)) {
            $validation_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Invalid email address', 'easy-form-builder'),
                    'description' => esc_html__('Please enter a valid email address.', 'easy-form-builder'),
                    'id' => 'invalid_admin_email'
                ],
                'details' => [
                    'stage' => 'validation',
                    'test_timestamp' => current_time('mysql', true),
                    'reason' => 'invalid_admin_email',
                ]
            ];
            $this->email_tester_log_efb('start_validation_failed', [
                'reason' => 'invalid_admin_email',
                'admin_email' => $admin_email,
            ]);
            update_option('emsfb_email_status', $validation_error);
            return [
                'success' => false,
                'm' => esc_html__('Please enter a valid email address.', 'easy-form-builder'),
                'stage' => 'validation'
            ];
        }

        $sender_email = isset($_POST['sender_email']) ? sanitize_email(wp_unslash($_POST['sender_email'])) : '';
        if (!is_email($sender_email)) {
            $sender_email = $this->get_default_sender_email_efb($ac);
        }
        if (!is_email($sender_email)) {
            $validation_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Invalid sender email', 'easy-form-builder'),
                    'description' => esc_html__('The sender email address is not valid.', 'easy-form-builder'),
                    'id' => 'invalid_sender_email'
                ],
                'details' => [
                    'stage' => 'validation',
                    'test_timestamp' => current_time('mysql', true),
                    'reason' => 'invalid_sender_email',
                ]
            ];
            $this->email_tester_log_efb('start_validation_failed', [
                'reason' => 'invalid_sender_email',
                'admin_email' => $admin_email,
                'sender_email' => $sender_email,
            ]);
            update_option('emsfb_email_status', $validation_error);
            return [
                'success' => false,
                'm' => esc_html__('The sender email address is not valid.', 'easy-form-builder'),
                'stage' => 'validation'
            ];
        }

        $start = $this->request_email_tester_start_efb($sender_email, $admin_email, $efbFunction);
        if (empty($start['success'])) {
            $api_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Email tester service error', 'easy-form-builder'),
                    'description' => isset($start['m']) ? sanitize_text_field($start['m']) : esc_html__('Could not start email test.', 'easy-form-builder'),
                    'id' => 'service_start_error'
                ],
                'details' => [
                    'stage' => isset($start['stage']) ? $start['stage'] : '',
                    'test_timestamp' => current_time('mysql', true),
                    'code' => isset($start['code']) ? $start['code'] : null,
                ]
            ];
            $this->email_tester_log_efb('start_failed_before_mail', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'stage' => isset($start['stage']) ? $start['stage'] : '',
                'message' => isset($start['m']) ? $start['m'] : '',
                'code' => isset($start['code']) ? $start['code'] : null,
                'test' => isset($start['test']) ? $start['test'] : null,
            ]);
            update_option('emsfb_email_status', $api_error);
            return $start;
        }

        $test = isset($start['test']) && is_array($start['test']) ? $start['test'] : [];
        $recipient_email = isset($test['recipient_email']) ? sanitize_email($test['recipient_email']) : '';
        $email_subject = isset($test['email_subject']) ? str_replace(["\r", "\n"], '', (string) $test['email_subject']) : '';
        $test_hash = isset($test['test_hash']) ? sanitize_text_field($test['test_hash']) : '';

        if (!is_email($recipient_email) || empty($email_subject) || !$this->is_valid_email_test_hash_efb($test_hash)) {
            $payload_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Invalid service response', 'easy-form-builder'),
                    'description' => esc_html__('The email tester service returned an invalid response.', 'easy-form-builder'),
                    'id' => 'invalid_service_payload'
                ],
                'details' => [
                    'stage' => 'start',
                    'test_timestamp' => current_time('mysql', true),
                ]
            ];
            $this->email_tester_log_efb('start_invalid_service_payload', [
                'recipient_email' => $recipient_email,
                'email_subject' => $email_subject,
                'test_hash' => $test_hash,
                'test' => $test,
            ]);
            update_option('emsfb_email_status', $payload_error);
            return [
                'success' => false,
                'm' => esc_html__('The email tester service returned an invalid response.', 'easy-form-builder'),
                'stage' => 'start',
                'test' => $test
            ];
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_email,
            'X-EFB-Test-Hash: ' . $test_hash,
        ];
        $message = sprintf(
            '<p>Easy Form Builder email delivery test.</p><p>Site: %s</p><p>Test hash: %s</p><p>Short hash: %s</p>',
            esc_html(home_url()),
            esc_html($test_hash),
            esc_html(isset($test['short_hash']) ? $test['short_hash'] : '')
        );

        $this->email_tester_log_efb('wp_mail_before_send', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'expected_to' => $recipient_email,
            'expected_from' => $sender_email,
            'admin_email' => $admin_email,
            'expected_subject' => $email_subject,
            'test_hash' => $test_hash,
            'short_hash' => isset($test['short_hash']) ? $test['short_hash'] : '',
            'headers' => $headers,
        ]);

        $last_mail_error = null;
        $actual_mail = null;
        $mail_error_listener = function($wp_error) use (&$last_mail_error) {
            if ($wp_error instanceof \WP_Error) {
                $last_mail_error = [
                    'code' => $wp_error->get_error_code(),
                    'message' => $wp_error->get_error_message(),
                    'data' => $wp_error->get_error_data(),
                ];
            }
        };
        $from_filter = function() use ($sender_email) {
            return $sender_email;
        };
        $from_name_filter = function() {
            return '';
        };
        $phpmailer_listener = function($phpmailer) use (&$actual_mail, $sender_email, $test_hash) {
            if (is_object($phpmailer)) {
                $phpmailer->From = $sender_email;
                $phpmailer->FromName = '';
                $phpmailer->Sender = $sender_email;
                $actual_mail = $this->email_tester_collect_phpmailer_state_efb($phpmailer, $test_hash);
            }
        };
        add_action('wp_mail_failed', $mail_error_listener);
        add_filter('wp_mail_from', $from_filter, PHP_INT_MAX);
        add_filter('wp_mail_from_name', $from_name_filter, PHP_INT_MAX);
        add_action('phpmailer_init', $phpmailer_listener, PHP_INT_MAX);
        $sent = wp_mail($recipient_email, $email_subject, $message, $headers);
        remove_action('phpmailer_init', $phpmailer_listener, PHP_INT_MAX);
        remove_filter('wp_mail_from_name', $from_name_filter, PHP_INT_MAX);
        remove_filter('wp_mail_from', $from_filter, PHP_INT_MAX);
        remove_action('wp_mail_failed', $mail_error_listener);

        $this->email_tester_log_efb('wp_mail_after_send', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'sent' => $sent ? true : false,
            'expected_to' => $recipient_email,
            'expected_from' => $sender_email,
            'expected_subject' => $email_subject,
            'test_hash' => $test_hash,
            'mail_error' => $last_mail_error,
            'actual_phpmailer' => $actual_mail,
            'actual_matches_expected' => [
                'to' => is_array($actual_mail) && isset($actual_mail['to']) ? in_array($recipient_email, (array) $actual_mail['to'], true) : false,
                'from' => is_array($actual_mail) && isset($actual_mail['from']) ? hash_equals((string) $sender_email, (string) $actual_mail['from']) : false,
                'subject' => is_array($actual_mail) && isset($actual_mail['subject']) ? hash_equals((string) $email_subject, (string) $actual_mail['subject']) : false,
                'hash_header' => is_array($actual_mail) && !empty($actual_mail['has_expected_hash_header']),
            ],
        ]);
        if (!$sent) {
            $failure_status = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Email delivery failed', 'easy-form-builder'),
                    'description' => esc_html__('WordPress could not send the test email. Please check your hosting mail settings or SMTP configuration.', 'easy-form-builder'),
                    'id' => 'mail_function_failed'
                ],
                'details' => [
                    'stage' => 'send',
                    'test_timestamp' => current_time('mysql', true),
                    'error' => $last_mail_error,
                ]
            ];
            $this->email_tester_log_efb('wp_mail_failed_save_status', $failure_status);
            update_option('emsfb_email_status', $failure_status);

            return [
                'success' => false,
                'm' => esc_html__('WordPress could not send the test email. Please check your hosting mail settings or SMTP configuration.', 'easy-form-builder'),
                'stage' => 'send',
                'test' => $test
            ];
        }

        $this->email_tester_log_efb('start_completed_test_ready_to_poll', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'test_hash' => $test_hash,
            'recipient_email' => $recipient_email,
            'sender_email' => $sender_email,
            'email_subject' => $email_subject,
            'check_after_seconds' => isset($test['check_after_seconds']) ? $test['check_after_seconds'] : null,
            'expires_in_seconds' => isset($test['expires_in_seconds']) ? $test['expires_in_seconds'] : null,
        ]);

        $pending_status = [
            'status' => 'warning',
            'message' => [
                'title' => esc_html__('Email test is waiting for delivery confirmation', 'easy-form-builder'),
                'description' => esc_html__('WordPress sent the test email. Easy Form Builder is waiting for the delivery result before marking email as ready.', 'easy-form-builder'),
                'id' => 'email_test_pending'
            ],
            'details' => [
                'stage' => 'sent',
                'test_timestamp' => current_time('mysql', true),
                'test_hash' => $test_hash,
                'recipient_email' => $recipient_email,
                'sender_email' => $sender_email,
            ]
        ];
        update_option('emsfb_email_status', $pending_status);

        return [
            'success' => true,
            'm' => isset($start['m']) ? $start['m'] : esc_html__('The test email has been sent. Waiting for the server result.', 'easy-form-builder'),
            'stage' => 'sent',
            'test' => $test
        ];
    }

    private function request_email_tester_start_efb($sender_email, $admin_email, $efbFunction) {
        $body = [
            'site_url' => home_url(),
            'sender_email' => $sender_email,
            'admin_email' => $admin_email,
            'plugin' => 'easy-form-builder',
            'plugin_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'language' => get_locale(),
            'license_type' => $efbFunction->is_efb_pro(1) ? 'pro' : 'free',
            'license_key' => '',
        ];

        $this->email_tester_log_efb('start_request_before_remote', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'endpoint' => $this->email_tester_endpoint_efb('/start'),
            'body' => $body,
        ]);

        $request = $this->email_tester_remote_request_efb('POST', '/start', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($request)) {
            $this->email_tester_log_efb('start_request_wp_error', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'message' => $request->get_error_message(),
                'code' => $request->get_error_code(),
            ]);
            return [
                'success' => false,
                'm' => $request->get_error_message(),
                'stage' => 'start'
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($request);
        $raw_body = wp_remote_retrieve_body($request);
        $data = json_decode($raw_body, true);
        $this->email_tester_log_efb('start_response', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'http_code' => $code,
            'body' => $data,
            'raw_body' => is_array($data) ? null : $raw_body,
        ]);
        if (!is_array($data)) {
            return [
                'success' => false,
                'm' => esc_html__('The email tester service returned an invalid JSON response.', 'easy-form-builder'),
                'stage' => 'start',
                'code' => $code
            ];
        }

        if ($code < 200 || $code >= 300 || empty($data['success'])) {
            return [
                'success' => false,
                'm' => isset($data['message']) ? sanitize_text_field($data['message']) : esc_html__('The email tester service could not start the test.', 'easy-form-builder'),
                'stage' => 'start',
                'code' => $code,
                'test' => $data
            ];
        }

        return [
            'success' => true,
            'm' => isset($data['message']) ? sanitize_text_field($data['message']) : '',
            'stage' => 'start',
            'test' => $data,
        ];
    }

    private function get_email_tester_result_efb($test_hash, $efbFunction, $ac, $admin_email = '') {
        if (!$this->is_valid_email_test_hash_efb($test_hash)) {
            $validation_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Invalid test hash', 'easy-form-builder'),
                    'description' => esc_html__('The email test hash is not valid.', 'easy-form-builder'),
                    'id' => 'invalid_test_hash'
                ],
                'details' => [
                    'stage' => 'result',
                    'test_timestamp' => current_time('mysql', true),
                    'reason' => 'invalid_test_hash',
                ]
            ];
            $this->email_tester_log_efb('result_validation_failed', [
                'reason' => 'invalid_test_hash',
                'test_hash' => $test_hash,
            ]);
            update_option('emsfb_email_status', $validation_error);
            return [
                'success' => false,
                'm' => esc_html__('The email test hash is not valid.', 'easy-form-builder'),
                'stage' => 'result'
            ];
        }

        $this->email_tester_log_efb('result_request_before_remote', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'endpoint' => $this->email_tester_endpoint_efb('/result/' . rawurlencode($test_hash)),
            'test_hash' => $test_hash,
        ]);

        $request = $this->email_tester_remote_request_efb('GET', '/result/' . rawurlencode($test_hash), [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($request)) {
            $request_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Service connection error', 'easy-form-builder'),
                    'description' => $request->get_error_message(),
                    'id' => 'service_request_error'
                ],
                'details' => [
                    'stage' => 'result',
                    'test_timestamp' => current_time('mysql', true),
                    'code' => $request->get_error_code(),
                ]
            ];
            $this->email_tester_log_efb('result_request_wp_error', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'test_hash' => $test_hash,
                'message' => $request->get_error_message(),
                'code' => $request->get_error_code(),
            ]);
            update_option('emsfb_email_status', $request_error);
            return [
                'success' => false,
                'm' => $request->get_error_message(),
                'stage' => 'result'
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($request);
        $raw_body = wp_remote_retrieve_body($request);
        $data = json_decode($raw_body, true);
        $this->email_tester_log_efb('result_response', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'http_code' => $code,
            'test_hash' => $test_hash,
            'body' => $data,
            'raw_body' => is_array($data) ? null : $raw_body,
        ]);
        if (!is_array($data)) {
            $parse_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Invalid service response', 'easy-form-builder'),
                    'description' => esc_html__('The email tester service returned an invalid JSON response.', 'easy-form-builder'),
                    'id' => 'invalid_json_response'
                ],
                'details' => [
                    'stage' => 'result',
                    'test_timestamp' => current_time('mysql', true),
                    'http_code' => $code,
                ]
            ];
            update_option('emsfb_email_status', $parse_error);
            return [
                'success' => false,
                'm' => esc_html__('The email tester service returned an invalid JSON response.', 'easy-form-builder'),
                'stage' => 'result',
                'code' => $code
            ];
        }

        $this->email_tester_log_efb('result_status_interpreted', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'http_code' => $code,
            'test_hash' => $test_hash,
            'status' => isset($data['status']) ? $data['status'] : '',
            'analysis_stage' => isset($data['analysis_stage']) ? $data['analysis_stage'] : '',
            'success' => !empty($data['success']),
            'can_send_email' => !empty($data['can_send_email']),
            'failure_reason' => isset($data['delivery']['failure_reason']) ? $data['delivery']['failure_reason'] : '',
        ]);

        if ($code < 200 || $code >= 300) {
            $http_error = [
                'status' => 'error',
                'message' => [
                    'title' => esc_html__('Service error', 'easy-form-builder'),
                    'description' => isset($data['message']) ? sanitize_text_field($data['message']) : esc_html__('The email tester service could not return the result.', 'easy-form-builder'),
                    'id' => 'service_http_error'
                ],
                'details' => [
                    'stage' => 'result',
                    'test_timestamp' => current_time('mysql', true),
                    'http_code' => $code,
                ]
            ];
            update_option('emsfb_email_status', $http_error);
            return [
                'success' => false,
                'm' => isset($data['message']) ? sanitize_text_field($data['message']) : esc_html__('The email tester service could not return the result.', 'easy-form-builder'),
                'stage' => 'result',
                'code' => $code,
                'result' => $data
            ];
        }

        // Save test result to emsfb_email_status for all scenarios
        $this->save_email_tester_result_to_status_efb($data, $efbFunction, $ac);

        $email_report = $this->maybe_request_email_tester_no_delivery_report_efb($test_hash, $data, $admin_email);
        if (is_array($email_report)) {
            $data['email_report'] = $email_report;
        }

        if (!empty($data['can_send_email'])) {
            $this->mark_email_server_as_ready_efb($efbFunction, $ac, isset($data['admin_email']) ? sanitize_email($data['admin_email']) : '', false);
        }

        return [
            'success' => !empty($data['success']),
            'm' => isset($data['message']) ? sanitize_text_field($data['message']) : '',
            'stage' => 'result',
            'result' => $data
        ];
    }

    private function save_email_tester_result_to_status_efb($test_result, $efbFunction, $ac) {
        if (!is_array($test_result)) {
            return;
        }

        $status_data = [
            'status' => 'error',
            'message' => [
                'title' => esc_html__('Email test failed', 'easy-form-builder'),
                'description' => esc_html__('The email server test could not verify email capability.', 'easy-form-builder'),
                'id' => 'email_test_failed'
            ],
            'details' => [
                'test_timestamp' => current_time('mysql', true),
                'can_send_email' => !empty($test_result['can_send_email']),
                'success' => !empty($test_result['success']),
            ]
        ];

        if (!empty($test_result['can_send_email'])) {
            $status_data['status'] = 'ok_set_smtp';
            $status_data['message'] = [
                'title' => esc_html__('Email capability verified', 'easy-form-builder'),
                'description' => esc_html__('Server confirmed ability to send emails.', 'easy-form-builder'),
                'id' => 'email_settings_configured'
            ];
        } else if (isset($test_result['status']) && in_array($test_result['status'], ['pending', 'delayed'], true)) {
            $status_data['status'] = 'warning';
            $status_data['message'] = [
                'title' => esc_html__('Email test is waiting for delivery confirmation', 'easy-form-builder'),
                'description' => esc_html__('WordPress sent the test email, but delivery has not been confirmed yet.', 'easy-form-builder'),
                'id' => 'email_test_pending'
            ];
        }

        // If there's a delivery failure reason
        if (!empty($test_result['delivery']['failure_reason'])) {
            $failure_reason = sanitize_text_field($test_result['delivery']['failure_reason']);
            $status_data['message']['description'] = sprintf(
                esc_html__('Email delivery failed: %s', 'easy-form-builder'),
                $failure_reason
            );
            $status_data['details']['failure_reason'] = $failure_reason;
        }

        // Add analysis stage if available
        if (!empty($test_result['analysis_stage'])) {
            $status_data['details']['analysis_stage'] = sanitize_text_field($test_result['analysis_stage']);
        }

        // Add API response status if available
        if (!empty($test_result['status'])) {
            $status_data['details']['api_status'] = sanitize_text_field($test_result['status']);
        }

        $this->email_tester_log_efb('saving_status_to_option', [
            'status_data' => $status_data,
            'test_result' => [
                'can_send_email' => !empty($test_result['can_send_email']),
                'success' => !empty($test_result['success']),
                'status' => isset($test_result['status']) ? $test_result['status'] : '',
                'analysis_stage' => isset($test_result['analysis_stage']) ? $test_result['analysis_stage'] : '',
            ]
        ]);

        update_option('emsfb_email_status', $status_data);

        if (!empty($test_result['can_send_email']) && is_object($ac) && isset($ac->smtp) && $ac->smtp != true) {
            $ac->smtp = true;
            $efbFunction->set_setting_Emsfb($ac);
        }
    }

    private function maybe_request_email_tester_no_delivery_report_efb($test_hash, $test_result, $admin_email = '') {
        if (!$this->is_valid_email_test_hash_efb($test_hash) || !is_array($test_result)) {
            return null;
        }

        $status = isset($test_result['status']) ? sanitize_key($test_result['status']) : '';
        $success = !empty($test_result['success']);
        $can_send_email = !empty($test_result['can_send_email']);
        $email_received = isset($test_result['delivery']['email_received']) ? (bool) $test_result['delivery']['email_received'] : false;

        if ($success || $can_send_email || $email_received || !in_array($status, ['delayed', 'expired'], true)) {
            return null;
        }

        $body = [
            'trigger_status' => $status,
            'language' => get_locale(),
            'reason' => 'client_displayed_' . $status . '_result',
        ];
        if (is_email($admin_email)) {
            $body['admin_email'] = $admin_email;
        } else if (!empty($test_result['admin_email']) && is_email($test_result['admin_email'])) {
            $body['admin_email'] = sanitize_email($test_result['admin_email']);
        }

        $this->email_tester_log_efb('email_report_request_before_remote', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'test_hash' => $test_hash,
            'trigger_status' => $status,
            'has_admin_email' => !empty($body['admin_email']),
        ]);

        $request = $this->email_tester_remote_request_efb('POST', '/result/' . rawurlencode($test_hash) . '/email-report', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($request)) {
            $report_result = [
                'success' => false,
                'code' => $request->get_error_code(),
                'message' => $request->get_error_message(),
            ];
            $this->email_tester_log_efb('email_report_request_wp_error', $report_result);
            return $report_result;
        }

        $code = (int) wp_remote_retrieve_response_code($request);
        $raw_body = wp_remote_retrieve_body($request);
        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            $data = [
                'success' => false,
                'code' => 'invalid_json_response',
                'message' => esc_html__('The email tester service returned an invalid JSON response.', 'easy-form-builder'),
            ];
        }

        $data['http_code'] = $code;
        $this->email_tester_log_efb('email_report_response', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'test_hash' => $test_hash,
            'http_code' => $code,
            'body' => $data,
        ]);

        return $data;
    }

    private function mark_email_server_as_ready_efb($efbFunction, $ac, $admin_email = '', $update_status = true) {
        if (!is_object($ac)) {
            return;
        }
        $ac->smtp = true;
        if (is_email($admin_email)) {
            $ac->emailSupporter = $admin_email;
        }
        if ($update_status) {
            update_option('emsfb_email_status', $this->build_email_ready_status_efb());
        }
        $setting_email = is_email($admin_email) ? $admin_email : '';
        if (empty($setting_email) && isset($ac->emailSupporter) && is_email($ac->emailSupporter)) {
            $setting_email = $ac->emailSupporter;
        }
        $efbFunction->set_setting_Emsfb($ac, $setting_email);
    }

    private function build_email_ready_status_efb() {
        return [
            'status' => 'ok_set_smtp',
            'message' => [
                'title' => 'configured',
                'description' => 'user configured email settings',
                'id' => 'email_settings_configured'
            ]
        ];
    }

    private function get_default_sender_email_efb($ac) {
        if (is_object($ac) && isset($ac->femail) && is_email($ac->femail)) {
            return sanitize_email($ac->femail);
        }
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'yourdomain.com';
        }
        $server_name = strtolower(str_replace("www.", "", preg_replace('/:\d+$/', '', $host)));
        return sanitize_email('no-reply@' . $server_name);
    }

    private function email_tester_remote_request_efb($method, $path, $args) {
        $primary_endpoint = $this->email_tester_endpoint_efb($path);
        $this->email_tester_log_efb('remote_request_attempt', [
            'run_id' => $this->email_tester_current_run_id_efb(),
            'method' => strtoupper($method),
            'endpoint' => $primary_endpoint,
        ]);
        $request = $this->email_tester_remote_request_once_efb($method, $primary_endpoint, $args);
        if (is_wp_error($request)) {
            $this->email_tester_log_efb('remote_request_attempt_failed', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'method' => strtoupper($method),
                'endpoint' => $primary_endpoint,
                'message' => $request->get_error_message(),
                'code' => $request->get_error_code(),
            ]);
            $fallback_endpoint = $this->email_tester_endpoint_efb($path, true);
            $this->email_tester_log_efb('remote_request_fallback_attempt', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'method' => strtoupper($method),
                'endpoint' => $fallback_endpoint,
            ]);
            $request = $this->email_tester_remote_request_once_efb($method, $fallback_endpoint, $args);
        }
        if (is_wp_error($request)) {
            $this->email_tester_log_efb('remote_request_final_error', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'method' => strtoupper($method),
                'path' => $path,
                'message' => $request->get_error_message(),
                'code' => $request->get_error_code(),
            ]);
        } else {
            $this->email_tester_log_efb('remote_request_final_response', [
                'run_id' => $this->email_tester_current_run_id_efb(),
                'method' => strtoupper($method),
                'path' => $path,
                'http_code' => (int) wp_remote_retrieve_response_code($request),
            ]);
        }
        return $request;
    }

    private function email_tester_remote_request_once_efb($method, $endpoint, $args) {
        return strtoupper($method) === 'POST' ? wp_remote_post($endpoint, $args) : wp_remote_get($endpoint, $args);
    }

    private function email_tester_collect_phpmailer_state_efb($phpmailer, $test_hash) {
        $to = [];
        if (method_exists($phpmailer, 'getToAddresses')) {
            foreach ((array) $phpmailer->getToAddresses() as $address) {
                $to[] = isset($address[0]) ? $address[0] : '';
            }
        }

        $custom_headers = [];
        if (method_exists($phpmailer, 'getCustomHeaders')) {
            foreach ((array) $phpmailer->getCustomHeaders() as $header) {
                if (is_array($header)) {
                    $name = isset($header[0]) ? (string) $header[0] : '';
                    $value = isset($header[1]) ? (string) $header[1] : '';
                    $custom_headers[$name] = $value;
                }
            }
        }

        return [
            'from' => isset($phpmailer->From) ? $phpmailer->From : '',
            'from_name' => isset($phpmailer->FromName) ? $phpmailer->FromName : '',
            'sender' => isset($phpmailer->Sender) ? $phpmailer->Sender : '',
            'to' => array_values(array_filter($to)),
            'subject' => isset($phpmailer->Subject) ? $phpmailer->Subject : '',
            'has_expected_hash_header' => isset($custom_headers['X-EFB-Test-Hash']) && hash_equals((string) $test_hash, (string) $custom_headers['X-EFB-Test-Hash']),
            'custom_headers' => $custom_headers,
        ];
    }

    private function email_tester_endpoint_efb($path, $use_www = false) {
        $host = $use_www ? 'www.whitestudio.team' : 'whitestudio.team';
        return 'https://' . $host . '/wp-json/ws-email-tester/v1' . $path;
    }

    private function email_tester_current_run_id_efb() {
        return isset($_POST['run_id']) ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '';
    }

    private function is_valid_email_test_hash_efb($test_hash) {
        return is_string($test_hash) && preg_match('/^[a-f0-9]{64}$/i', $test_hash);
    }

    private function email_tester_log_efb($event, $context = []) {
        $debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);
        if (!$debug_enabled) {
            return;
        }
        $safe_context = $this->email_tester_sanitize_log_context_efb($context);
        error_log('[EFB Email Tester] ' . $event . ' ' . wp_json_encode($safe_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function email_tester_sanitize_log_context_efb($value) {
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key => $item) {
                $safe_key = is_string($key) ? $key : (string) $key;
                if (in_array($safe_key, ['license_key', 'password', 'secret', 'nonce'], true)) {
                    $safe[$safe_key] = '[redacted]';
                    continue;
                }
                $safe[$safe_key] = $this->email_tester_sanitize_log_context_efb($item);
            }
            return $safe;
        }
        if (is_object($value)) {
            if ($value instanceof \WP_Error) {
                return [
                    'code' => $value->get_error_code(),
                    'message' => $value->get_error_message(),
                ];
            }
            return $this->email_tester_sanitize_log_context_efb((array) $value);
        }
        if (is_string($value)) {
            if (is_email($value)) {
                return $this->email_tester_mask_email_efb($value);
            }
            if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
                return substr($value, 0, 12) . '...' . substr($value, -8);
            }
            return strlen($value) > 2000 ? substr($value, 0, 2000) . '...[truncated]' : $value;
        }
        return $value;
    }

    private function email_tester_mask_email_efb($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        $domain = $parts[1];
        $visible = substr($name, 0, 2);
        return $visible . '***@' . $domain;
    }

    public function isHTML($str) {
        return preg_match("/\/[a-z]*>/i", $str) != 0;
    }
    public function get_ip_address() {
        $ip='1.1.1.1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );}
        $ip = strval($ip);
        $check =strpos($ip,',');
        if($check !== false){$ip = substr($ip,0,$check);}
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
            require_once(ABSPATH . 'wp-admin/includes/file.php');

            $r = download_url($url);
            if(is_wp_error($r)){
                $r = download_url($url, 300, true);
                if (is_wp_error($r)) {
                    return new \WP_Error('download_failed',
                        esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to download files', 'easy-form-builder')
                        . ' (' . $r->get_error_message() . ')'
                    );
                }
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
                @unlink($r);
                return new \WP_Error('move_failed',
                    esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to move the downloaded file', 'easy-form-builder')
                );
            }
            if (!$filesystem_ready) {
                WP_Filesystem();
            }
            $r = unzip_file(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . 'vendor/');
            @unlink(EMSFB_PLUGIN_DIRECTORY . 'temp/temp.zip');
            if(is_wp_error($r)){
                return new \WP_Error('unzip_failed',
                    esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files', 'easy-form-builder')
                    . ' (' . $r->get_error_message() . ')'
                );
            }
            return true;
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
            $vl  = $this->db->get_var($this->db->prepare("SELECT form_structer FROM `{$table_name}` WHERE form_id = %d", $id));
            if($vl!=null){
                if(strpos($vl , '\"type\":\"dadfile\"') !== false || strpos($vl , '\"type\":\"file\"') !== false || strpos($vl , '"type":"dadfile"') !== false || strpos($vl , '"type":"file"') !== false){
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
            $file_tmp = isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : '';
            $file_type = isset($_FILES['file']['type']) ? sanitize_text_field( wp_unslash( $_FILES['file']['type'] ) ) : '';

            if (empty($file_tmp) || !is_uploaded_file($file_tmp) || !is_readable($file_tmp)) {
                $response = array( 'success' => false, 'error' => esc_html__('There seems to be an error with the file permissions.','easy-form-builder') . ' ( File not readable)' );
                wp_send_json_success($response, 200);
            }

            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $real_mime = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                if (!in_array($real_mime, $arr_ext)) {
                    $response = array( 'success' => false, 'error' => esc_html__('There seems to be an error with the file permissions.','easy-form-builder') . ' (MIME type)' );
                    wp_send_json_success($response, 200);
                }
            }

            $name = 'efb-PLG-'. wp_date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($file_name, PATHINFO_EXTENSION) ;

            $blocked_ext = array('php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','asp','aspx','jsp','sh','bash','bat','cmd','com','exe','dll','msi','shtml','htaccess','svg');
            $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($file_ext, $blocked_ext)) {
                $response = array( 'success' => false, 'error' => 'File type not allowed');
                wp_send_json_success($response, 200);
            }

            $file_contents = file_get_contents($file_tmp);
            if ($file_contents === false) {
                $response = array( 'success' => false, 'error' => 'File read error');
                wp_send_json_success($response, 200);
            }

            $upload = wp_upload_bits($name, null, $file_contents);
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=> $file_type);
			  wp_send_json_success($response,200);
		}else{
			$file_type = isset($_FILES['file']['type']) ? sanitize_text_field( wp_unslash( $_FILES['file']['type'] ) ) : 'unknown';
			$response = array( 'success' => false  ,'error'=>'File Type Error');
			wp_send_json_success($response,200);
		}

	}
    public function custom_ui_plugins(){
           if( is_plugin_active('js_composer/js_composer.php')){
                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery")){
                 }
             }
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
            $msg_id_list = [];
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $clean_id = intval($value['msg_id']);
                    if ($clean_id > 0) {
                        $msg_id_list[] = $clean_id;
                    }
                }
            }
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            if(!empty($msg_id_list)){
                $placeholders = implode(',', array_fill(0, count($msg_id_list), '%d'));
                $sql = $this->db->prepare("DELETE FROM `$table_name` WHERE msg_id IN ($placeholders)", ...$msg_id_list);
                $r = $this->db->query($sql);
                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";
                    $sql = $this->db->prepare("DELETE FROM `$table_name` WHERE msg_id IN ($placeholders)", ...$msg_id_list);
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
            $msg_id_list = [];
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $clean_id = intval($value['msg_id']);
                    if ($clean_id > 0) {
                        $msg_id_list[] = $clean_id;
                    }
                }
            }
            $response = ['success' => false, "m" =>$lang['somethingWentWrongPleaseRefresh']];
            $user_id = get_current_user_id();
            if(!empty($msg_id_list)){
                $placeholders = implode(',', array_fill(0, count($msg_id_list), '%d'));
                $sql = $this->db->prepare("UPDATE `$table_name` SET read_ = 1 WHERE msg_id IN ($placeholders)", ...$msg_id_list);
                $r = $this->db->query($sql);
                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";
                    $sql = $this->db->prepare("UPDATE `$table_name` SET read_ = 1 WHERE msg_id IN ($placeholders)", ...$msg_id_list);
                    $r = $this->db->query($sql);
                }

        }
        wp_send_json_success($response, 200);
    }
    }
    public function check_and_enqueue_font_roboto_Emsfb() {
        $font_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap';
        $response = wp_remote_head($font_url);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            wp_register_style('Font_Roboto', $font_url);
            wp_enqueue_style('Font_Roboto');
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
        $efbFunction->report_problem_efb($state , $value);
        $response = ['success' => true, "m" =>'report_problem_done'];
        wp_send_json_success($response, 200);
    }
    function create_temporary_links_table_Emsfb() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'emsfb_temp_links';
		$table_exists = get_option('emsfb_temp_links_table_exists', false);
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
            $efbFunction = get_efbFunction();
            $settings= get_setting_Emsfb('decoded');

            if(is_array($check)){
                    $email_status = isset($check['status']) ? $check['status'] : '';
                    if($email_status === 'ok_set_smtp') {
                        return;
                    }else if ($email_status === 'ok' ) {
                        if (isset($settings->smtp) && !in_array($settings->smtp, ['1', 'true', true,1], true)) {
                            $settings->smtp = true;
                            $email = isset($settings->emailSupporter) ? $settings->emailSupporter : '';
                            $efbFunction->set_setting_Emsfb($settings, $email);
                        }

                        return;
                    }
            }else{
                if (isset($settings->smtp) && in_array($settings->smtp, ['1', 'true', true,1], true)) {
                       update_option('emsfb_email_status', $this->build_email_ready_status_efb());
                       return;
                }else{
                     $r = get_option('emsfb_email_status', false);
                     if($r===false){
                        $logo_url   = EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.png';
                        $panel_url  = admin_url('admin.php?page=Emsfb');
                        ob_start();
                        ?>
                        <div id="notice-email-setup-efb" class="notice notice-info efb-notice-email-setup notice-alt efb" style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;position:relative;z-index:1000;">
                            <button type="button" id="efb-close-setup-notice-btn"
                                style="position:absolute;top:8px;right:8px;background:transparent;border:none;font-size:20px;cursor:pointer;line-height:1;"
                                aria-label="<?php esc_attr_e('Close', 'easy-form-builder'); ?>">&times;</button>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Easy Form Builder', 'easy-form-builder'); ?>" style="width:46px;height:auto;margin-top:2px;flex-shrink:0;" />
                            <div style="flex:1;min-width:0;">
                                <p style="margin:0 0 6px 0;font-size:14px;">
                                    <strong><?php esc_html_e('Easy Form Builder', 'easy-form-builder'); ?></strong>
                                    &mdash;
                                    <?php esc_html_e('Enable the Email Notification Feature', 'easy-form-builder'); ?>
                                </p>
                                <p style="margin:0 0 8px 0;color:#555;"><?php esc_html_e('To receive email notifications from your forms, verify your email server by following these steps:', 'easy-form-builder'); ?></p>
                                <ol style="margin:0 0 10px 0;padding-left:22px;line-height:2;color:#333;">
                                    <li><?php printf( wp_kses( __('Go to the <a href="%s" style="font-weight:600;">Easy Form Builder Panel</a>', 'easy-form-builder'), ['a' => ['href' => [], 'style' => []]] ), esc_url($panel_url) ); ?></li>
                                    <li><?php esc_html_e('Click Settings from the top menu', 'easy-form-builder'); ?></li>
                                    <li><?php esc_html_e('Select the Email Settings tab', 'easy-form-builder'); ?></li>
                                    <li><?php esc_html_e('Click "Check Email Server" and wait for the test to finish', 'easy-form-builder'); ?></li>
                                    <li><?php esc_html_e('If the score is above 70 — enable the "This site can send emails" switch and save', 'easy-form-builder'); ?></li>
                                    <li><?php esc_html_e('Otherwise, follow the instructions shown in the test results', 'easy-form-builder'); ?></li>
                                </ol>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var n = document.getElementById('notice-email-setup-efb');
                            if (window.localStorage.getItem('efb_email_setup_notice_dismissed') === 'true') {
                                if (n) n.style.display = 'none';
                            }
                            var btn = document.getElementById('efb-close-setup-notice-btn');
                            if (btn && n) {
                                btn.addEventListener('click', function () {
                                    n.style.display = 'none';
                                    window.localStorage.setItem('efb_email_setup_notice_dismissed', 'true');
                                });
                            }
                        })();
                        </script>
                        <?php
                        echo ob_get_clean();
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
                'invalid_admin_email' => [
                    'title' => esc_html__('Invalid email address.', 'easy-form-builder'),
                    'description' => esc_html__('Please enter a valid admin email address before testing email delivery.', 'easy-form-builder'),
                ],
                'invalid_sender_email' => [
                    'title' => esc_html__('Invalid sender email.', 'easy-form-builder'),
                    'description' => esc_html__('The sender email address is not valid. Please check your email settings.', 'easy-form-builder'),
                ],
                'service_start_error' => [
                    'title' => esc_html__('Email tester service error.', 'easy-form-builder'),
                    'description' => esc_html__('Easy Form Builder could not start the email delivery test. Please try again later.', 'easy-form-builder'),
                ],
                'invalid_service_payload' => [
                    'title' => esc_html__('Invalid email tester response.', 'easy-form-builder'),
                    'description' => esc_html__('The email tester service returned an invalid response.', 'easy-form-builder'),
                ],
                'invalid_test_hash' => [
                    'title' => esc_html__('Invalid email test.', 'easy-form-builder'),
                    'description' => esc_html__('The email test identifier is invalid. Please start a new test.', 'easy-form-builder'),
                ],
                'service_request_error' => [
                    'title' => esc_html__('Email tester connection error.', 'easy-form-builder'),
                    'description' => esc_html__('Easy Form Builder could not connect to the email tester service.', 'easy-form-builder'),
                ],
                'invalid_json_response' => [
                    'title' => esc_html__('Invalid email tester response.', 'easy-form-builder'),
                    'description' => esc_html__('The email tester service returned an invalid JSON response.', 'easy-form-builder'),
                ],
                'service_http_error' => [
                    'title' => esc_html__('Email tester service error.', 'easy-form-builder'),
                    'description' => esc_html__('The email tester service could not return the result.', 'easy-form-builder'),
                ],
                'email_test_failed' => [
                    'title' => esc_html__('Email delivery test failed.', 'easy-form-builder'),
                    'description' => esc_html__('The email server test could not verify email capability.', 'easy-form-builder') . $warning,
                ],
                'email_test_pending' => [
                    'title' => esc_html__('Email delivery test is pending.', 'easy-form-builder'),
                    'description' => esc_html__('WordPress sent the test email, but delivery has not been confirmed yet.', 'easy-form-builder'),
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
                if (window.localStorage.getItem('efb_email_notice_dismissed') === 'true') {
                    if (efbNotice) efbNotice.style.display = 'none';
                }
                var efbCloseBtn = document.getElementById('efb-close-notice-btn');

                if (efbCloseBtn) {
                    const page = document.querySelector('.sideMenuFEfb');
                    if (page) {
                        efbNotice.style.display = 'none';
                    }
                    efbCloseBtn.addEventListener('click', function () {
                        var efbNotice = document.getElementById('notice-email-efb');
                        if (efbNotice) efbNotice.style.display = 'none';
                        window.localStorage.setItem('efb_email_notice_dismissed', 'true');
                    });
                }
            </script>
            <?php
            $output = ob_get_clean();

            echo $output;
    }

    public function efb_save_plan_selection() {
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'wp_rest') || !$currrent_user_can) {
            $response = ['success' => false, 'm' => $lang['error403']];
            wp_send_json_error($response, 403);
            die("secure!");
        }

        if (!isset($_POST['plan_data'])) {
            wp_send_json_error(array(
                'message' => __('Plan data is missing.', 'easy-form-builder')
            ), 400);
            return;
        }

        $plan_data_raw = sanitize_textarea_field($_POST['plan_data']);
        $plan_data = json_decode(stripslashes($plan_data_raw), true);

        if (!is_array($plan_data)) {
            wp_send_json_error(array(
                'message' => __('Invalid plan data format.', 'easy-form-builder')
            ), 400);
            return;
        }

        $selected_plan = isset($plan_data['selected_plan']) ? sanitize_text_field(wp_unslash($plan_data['selected_plan'])) : '';
        $timestamp = isset($plan_data['timestamp']) ? intval($plan_data['timestamp']) : time();
        if (!in_array($selected_plan, array('free', 'free_plus', 'pro'))) {
            wp_send_json_error(array(
                'message' => __('Invalid plan type.', 'easy-form-builder')
            ), 400);
            return;
        }

        $redirect_url = null;
        $action_performed = null;
        $package_type_efb = 2;

        $settings = get_setting_Emsfb('decoded');
        $has_active_code = isset($settings->activeCode) && !empty($settings->activeCode);

        switch($selected_plan) {
            case 'free':
                update_option('emsfb_pro', 2);
                $package_type_efb = 2;
                $action_performed = __('Free plan activated - no additional features.', 'easy-form-builder');
                if ($has_active_code) {
                    $settings->activeCode = '';
                }
                break;

            case 'free_plus':
                update_option('emsfb_pro', 3);
                $package_type_efb = 3;
                $action_performed = __('Free Plus plan activated with enhanced features.', 'easy-form-builder');
                if ($has_active_code) {
                    $settings->activeCode = '';
                }
                break;

            case 'pro':
                if ($has_active_code) {
                    $package_type_efb = 1;
                    update_option('emsfb_pro', 1);
                    $action_performed = __('Pro plan activated with existing activation code.', 'easy-form-builder');
                } else {
                    $package_type_efb = 0;
                    update_option('emsfb_pro', 0);
                    $redirect_url = 'https://whitestudio.team/#price';
                    if (get_locale() == 'fa_IR') {
                        $redirect_url = 'https://easyformbuilder.ir/#price';
                    }
                    $action_performed = __('Redirecting to Pro plan purchase page.', 'easy-form-builder');
                }
                break;
        }

        $settings->package_type = $package_type_efb;
        $email = isset($settings->emailSupporter) ? $settings->emailSupporter : '';
        $efbFunction->set_setting_Emsfb($settings, $email);

        $response_data = array(
            'success' => true,
            'message' => sprintf(__('Plan "%s" has been successfully processed.', 'easy-form-builder'), $selected_plan),
            'plan' => $selected_plan,
            'action' => $action_performed,
            'redirect_url' => $redirect_url,
            'timestamp' => $timestamp,
            'saved_at' => current_time('mysql'),
            'package_type' => $package_type_efb
        );

        wp_send_json_success($response_data);
    }

    public function update_form_cache_efb($form_id, $form_data, $fields = array('form_structer', 'form_type')) {
        $form_id = intval($form_id);

        if ($form_id <= 0 || empty($form_data)) {
            return false;
        }

        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }

        $cache_key = $form_id . '_' . md5(implode('_', $fields));

        $cache_data = is_array($form_data) ? (object) $form_data : $form_data;

        $this->form_cache[$cache_key] = $cache_data;

        wp_cache_set('efb_form_' . $cache_key, $cache_data, 'emsfb', 3600);

        return true;
    }

    public function bulk_update_form_cache_efb($forms_data, $fields = array('form_structer', 'form_type')) {
        $results = array();

        foreach ($forms_data as $form_id => $form_data) {
            $results[$form_id] = $this->update_form_cache_efb($form_id, $form_data, $fields);
        }

        return $results;
    }

    public function clear_form_cache_efb($form_id = null, $fields = array()) {
        if ($form_id === null) {
            $this->form_cache = array();

            wp_cache_flush_group('emsfb');

            return true;
        }

        $form_id = intval($form_id);

        if (empty($fields)) {
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

            unset($this->form_cache[$cache_key]);

            wp_cache_delete('efb_form_' . $cache_key, 'emsfb');
        }

        return true;
    }

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

    public function validate_and_refresh_cache_efb($form_id, $fields = array('form_structer', 'form_type'), $force_refresh = false) {
        $form_id = intval($form_id);
        $cache_key = $form_id . '_' . md5(implode('_', $fields));

        if ($force_refresh) {
            unset($this->form_cache[$cache_key]);
            wp_cache_delete('efb_form_' . $cache_key, 'emsfb');
        }

        if (!$force_refresh && isset($this->form_cache[$cache_key])) {
            return $this->form_cache[$cache_key];
        }

        if(empty($this->db)){
            global $wpdb;
            $this->db = $wpdb;
        }

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

        $this->form_cache[$cache_key] = $result[0];
        wp_cache_set('efb_form_' . $cache_key, $result[0], 'emsfb', 3600);

        return $result[0];
    }

}
new Admin();
