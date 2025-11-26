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
    public $efbFunction;


    public function __construct() {


        $this->init_hooks();
        global $wpdb;
        $this->db = $wpdb;

    }

    /**
     * Initial plugin
     */
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




            add_action('wp_ajax_remove_id_Emsfb', [$this, 'delete_form_id_public']);
            add_action('wp_ajax_remove_message_id_Emsfb', [$this, 'delete_message_id_public']);
            add_action('wp_ajax_get_form_id_Emsfb', [$this, 'get_form_id_Emsfb']);
            add_action('wp_ajax_get_messages_id_Emsfb', [$this, 'get_messages_id_Emsfb']);
            add_action('wp_ajax_get_all_response_id_Emsfb', [$this, 'get_all_response_id_Emsfb']);
            add_action('wp_ajax_update_form_Emsfb', [$this, 'update_form_id_Emsfb']);
            add_action('wp_ajax_update_message_state_Emsfb', [$this, 'update_message_state_Emsfb']);
            add_action('wp_ajax_set_replyMessage_id_Emsfb', [$this, 'set_replyMessage_id_Emsfb']);
            add_action('wp_ajax_set_setting_Emsfb', [$this, 'set_setting_Emsfb']);
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

    /* function extend_nonce_life_efb($seconds) {
        return 60 * 60 * 24;
    } */
    public function admin_assets($hook) {
        global $current_screen;
        $hook = $hook ? $hook : http_build_query($_GET);




        if (strpos($hook, 'Emsfb')==true && is_admin()) {

            if (is_rtl()) {

                wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl-efb.css', true,EMSFB_PLUGIN_VERSION );
                wp_enqueue_style('Emsfb-css-rtl');

            }

            wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-style-css');

            wp_register_style('Emsfb-bootstrap', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap');







            wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap-icons-css');

            wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select-efb.css',true,EMSFB_PLUGIN_VERSION);
            wp_enqueue_style('Emsfb-bootstrap-select-css');

            $this->check_and_enqueue_font_roboto();
            $lang = get_locale();
            if (strlen($lang) > 0) {$lang = explode('_', $lang)[0];}

                wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min-efb.js', false, EMSFB_PLUGIN_VERSION, true);


                 wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min-efb.js', array( 'jquery' ), true, EMSFB_PLUGIN_VERSION, true);


                wp_enqueue_script('efb-bootstrap-icon-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-icon-efb.js', false, EMSFB_PLUGIN_VERSION, true);

        }
    }

    /**
     * Register admin menu
     */
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

    /**
     * Callback outbox page.
     */
    public function panel_callback() {
        include_once EMSFB_PLUGIN_DIRECTORY . "/includes/admin/class-Emsfb-panel.php";
        $list_table = new Panel_edit();

    }

    public function delete_form_id_public() {
        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        if (empty($_POST['id'])) {
            $m = $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;

        $table_name = $this->db->prefix . "emsfb_form";
        $r          = $this->db->delete(
            $table_name,
            ['form_id' => $id],
            ['%d']
        );
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
        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        if (empty($_POST['id'])) {
            $m = $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;

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
        $efbFunction = $this->get_efbFunction(1);
        $text = ["sms_noti","msg_adons","error403","invalidRequire","nAllowedUseHtml","updated","upDMsg" ,"newMessageReceived","trackNo","url","newResponse","WeRecivedUrM"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);

        }

        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $post_id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $post_name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        if (empty($post_value) || empty($post_id) || empty($post_name)) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);

        }

        if ($this->isScript(json_encode($post_value),JSON_UNESCAPED_UNICODE) || $this->isScript(json_encode($_POST['name']),JSON_UNESCAPED_UNICODE)) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }

        $valp = isset($post_value) ? str_replace('\\', '', wp_unslash($post_value)) : '';
		$valp = json_decode($valp,true);

		$sms_msg_new_noti="";
		$sms_msg_responsed_noti="";
		$sms_msg_recived_user="";
		$sms_admins_phoneno="";
        if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1){




			$sms_msg_new_noti = isset($valp[0]['sms_msg_new_noti']) ?$valp[0]['sms_msg_new_noti'] :$lang["newMessageReceived"] ."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_msg_responsed_noti = isset($valp[0]['sms_msg_responsed_noti']) ? $valp[0]['sms_msg_responsed_noti'] :  $lang["newResponse"]."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_msg_recived_user = isset($valp[0]['sms_msg_recived_usr']) ? $valp[0]['sms_msg_recived_usr'] : $lang["WeRecivedUrM"] ."\n". $lang["trackNo"] .": [confirmation_code]\n". $lang["url"] .": [link_response]";
			$sms_admins_phoneno = isset($valp[0]['sms_admins_phone_no']) ? $valp[0]['sms_admins_phone_no'] : "";







			unset($valp[0]['sms_msg_new_noti']);
			unset($valp[0]['sms_msg_responsed_noti']);
			unset($valp[0]['sms_msg_recived_user']);
			if(isset($valp[0]['sms_admins_phone_no'])){unset($valp[0]['sms_admins_phone_no']);}



		}
        $valp = $efbFunction->sanitize_obj_msg_efb($valp);
        $form_type = $valp[0]['type'];

		$value =json_encode($valp,JSON_UNESCAPED_UNICODE);
        $value_ =str_replace('"', '\"', $value);


        $table_name = $this->db->prefix . "emsfb_form";

        $r = $this->db->update($table_name, ['form_structer' => $value_, 'form_name' => $post_name ,'form_type'=>$form_type ], ['form_id' => $post_id]);
        $value_="";
        $value="";
        if(isset($valp[0]['smsnoti']) && intval($valp[0]['smsnoti'])==1 ){


            if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")) {
               $m = str_replace('NN', '<b>' . $lang['sms_noti'] . '</b>', $lang['msg_adons']);
                $response = ['success' => false, 'm' => $m];
                wp_send_json_success($response, 200);
            }

			require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
			$smsefb = new smssendefb();



			$smsefb->add_sms_contact_efb(
                $post_id,
				$sms_admins_phoneno,
				$sms_msg_recived_user,
				$sms_msg_new_noti,
				$sms_msg_new_noti,
				$sms_msg_responsed_noti);
		}
        $m = $lang["updated"];
        $response = ['success' => true, 'r' =>"updated", 'value' => "[EMS_Form_Builder id=$post_id]"];
        wp_send_json_success($response, 200);
    }
    public function add_addons_Emsfb() {

        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","done","invalidRequire","upDMsg"];
        $lang= $efbFunction->text_efb($text);
        $ac= $efbFunction->get_setting_Emsfb();
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
        if (check_ajax_referer('wp_rest', 'nonce') != 1 || $dd!="integer" && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);

        }

        if (empty($post_value) ) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);

        }


        if ($this->isScript($post_value)) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
       if($post_value!="AdnOF"){


            $server_name = isset($_SERVER['HTTP_HOST']) ? str_replace("www.", "", sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
            $vwp = get_bloginfo('version');
            $vwp = substr($vwp,0,3);
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$post_value .'/'.$vwp.'/' ;
            if(get_locale()=='fa_IR'){
                $u = 'https://easyformbuilder.ir/wp-json/wl/v1/addons-link/'. $server_name.'/'.$post_value .'/'.$vwp.'/' ;
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


            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body );

            if($data==null || $data=='null'){
                $m = esc_html__('You can not use the Easy Form Builder features right now. Contact whitestudio.team support for help.','easy-form-builder');
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, 200);
            }

            if($data->status==false){
                $response = ['success' => false, "m" => $data->error];
                wp_send_json_success($response, 200);

            }


            if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {
                $m = $lang["upDMsg"];
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, 200);

            }

            if($data->download==true ){
                $url =$data->link;

               $s= $this->fun_addon_new($url);
               if($s==false ){
                $m = esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files','easy-form-builder');
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
        $ac->{$post_value}=1;

        $ac->efb_version=EMSFB_PLUGIN_VERSION;

        $table_name = $this->db->prefix . "emsfb_setting";
        $newAc= json_encode( $ac ,JSON_UNESCAPED_UNICODE );
        $newAc= str_replace('"', '\"', $newAc);
        $this->db->insert(
            $table_name,
            [
                'setting' => $newAc,
                'edit_by' => get_current_user_id(),
                'date'    => wp_date('Y-m-d H:i:s'),
                'email'   => $ac->emailSupporter,
            ]
        );
        update_option('emsfb_settings', $newAc);
        set_transient('emsfb_settings_transient', $newAc, 1440);
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        wp_send_json_success($response, 200);
    }
    public function remove_addons_Emsfb() {
        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","done","invalidRequire"];
        $lang= $efbFunction->text_efb($text);
        $ac= $efbFunction->get_setting_Emsfb();
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (check_ajax_referer('wp_rest', 'nonce') != 1 && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        if (empty($_POST['value']) ) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];

            wp_send_json_success($response, 200);
            die();
        }

        $post_value  = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        if ($this->isScript($post_value)) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }

        $server_name =  str_replace("www.", "",  isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '');


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

        $table_name = $this->db->prefix . "emsfb_setting";
        $newAc= json_encode( $ac ,JSON_UNESCAPED_UNICODE );
        $newAc= str_replace('"', '\"', $newAc);

        $this->db->insert(
            $table_name,
            [
                'setting' => $newAc,
                'edit_by' => get_current_user_id(),
                'date'    => wp_date('Y-m-d H:i:s'),
                'email'   => $ac->emailSupporter,
            ]
        );

        update_option('emsfb_settings', $newAc);
        set_transient('emsfb_settings_transient', $newAc, 1440);
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        wp_send_json_success($response, 200);
    }

    public function update_message_state_Emsfb() {
        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","somethingWentWrongPleaseRefresh","updated"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        if (empty($_POST['id']) && $this->isHTML(json_encode($post_value),JSON_UNESCAPED_UNICODE)) {
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => esc_html__("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, 200);
            die();
        }

        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;

        $table_name = $this->db->prefix . "emsfb_msg_";
        $r          = $this->db->update($table_name, ['read_' => 1, 'read_date' => wp_date('Y-m-d H:i:s')], ['msg_id' => $id]);

        $m =   $lang["updated"];
        $response = ['success' => true, 'r' =>"updated"];
        wp_send_json_success($response, 200);
    }

    public function get_form_id_Emsfb() {

        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, 200);
            die();
        }
        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;

        $table_name = $this->db->prefix . "emsfb_form";
        $value = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");



        $smsnoti = strpos($value,'\"smsnoti\":\"1\"') !==false ? 1 : 0;

        if($smsnoti){


            if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")){
                require_once( EMSFB_PLUGIN_DIRECTORY . '/vendor/smssended/smsefb.php' );
                $smsefb = new smssendefb();
                $sms = $smsefb->get_sms_contact_efb($id);



                $value = str_replace('\"smsnoti\":\"1\"', '\"smsnoti\":\"1\",\"sms_msg_new_noti\":\"'.$sms->new_message_noti_user.'\",\"sms_msg_responsed_noti\":\"'.$sms->new_response_noti.'\",\"sms_msg_recived_usr\":\"'.$sms->recived_message_noti_user.'\",\"sms_admins_phone_no\":\"'.$sms->admin_numbers.'\"',$value);


            }
        }

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, 200);

    }



    public function get_messages_id_Emsfb() {
        $efbFunction = $this->get_efbFunction(1);
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }
        $id = isset($_POST['id']) ? sanitize_text_field( wp_unslash($_POST['id'])) : '';
        $code = 'efb'. $id;

        $code =wp_create_nonce($code);

        $id =  ( int ) sanitize_text_field($id);

        $table_name = $this->db->prefix . "emsfb_msg_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id' ORDER BY `$table_name`.date DESC");

        $response   = ['success' => true, 'ajax_value' => $value, 'id' => $id,'nonce_msg'=> $code];
        wp_send_json_success($response, 200);
    }

    public function get_all_response_id_Emsfb() {
        $efbFunction = $this->get_efbFunction(1);
        $text = ["spprt","error403","somethingWentWrongPleaseRefresh" ,"guest"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
        }

        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;

        $table_name = $this->db->prefix . "emsfb_rsp_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE msg_id = '$id'");
        $this->db->update($table_name, ['read_' => 1], ['msg_id' => $id, 'read_' => 0]);
        foreach ($value as $key => $val) {
            $r = (int)$val->rsp_by;
            if ($r > 0) {
                $usr         = get_user_by('id', $r);
                $val->rsp_by = $usr->display_name;
            }else if ($r==-1){
                $val->rsp_by= $lang["spprt"];
            }
            else {
                $m =   $lang["guest"];
                $val->rsp_by =$m;
            }
        }

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, 200);
    }

    public function set_replyMessage_id_Emsfb() {
         $this->get_efbFunction(0);
        $ac= $this->efbFunction->get_setting_Emsfb();
        $text = ["error405","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","messageSent"];
        $lang= $this->efbFunction->text_efb($text);
        $currrent_user_can = $this->efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {
            $response = ['success' => false, 'm' => $lang["error403"]];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        $post_message = isset($_POST['message']) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;
        if (empty($post_message) || empty($id)) {
            $response = ['success' => false, "m" => $lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response, 200);
        }

        if ($this->isHTML(json_encode($post_message))) {
            $response = ['success' => false, "m" => $lang["nAllowedUseHtml"]];
            wp_send_json_success($response, 200);
        }
        $id = preg_replace('/[,]+/','',$id);
        $m  = sanitize_text_field( wp_unslash($post_message));


        $m = str_replace("\\","",$m);
        $message =json_decode($m);
				$valobj=[];
				$stated=1;
				foreach ($message as $k =>$f){

					$in_loop=true;

				if($stated==0){break;}
					switch ($f->type) {
						case 'allformat':
							   $d = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'yourdomain.com';
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
									$f->value = sanitize_text_field( wp_unslash($f->value));
								}

								$in_loop=false;
							break;
						}
						if($stated==0){
							$response = array( 'success' => false  , 'm'=>$lang["error405"]);
							wp_send_json_success($response,200);
						}


				}
                $m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);


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
        $m        = $lang["messageSent"];
        $response = ['success' => true, "m" => $m];

        $pro =isset( $ac->activeCode) ? $ac->activeCode : null;

        $this->efbFunction->response_to_user_by_msd_id($id ,$pro);
        wp_send_json_success($response, 200);

    }

    public function set_setting_Emsfb() {

        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["pleaseDoNotAddJsCode","emailTemplate","addSCEmailM","messageSent","activationNcorrect","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","PEnterMessage"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {
            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        if (empty($_POST['message'])) {
            $m = $lang["PEnterMessage"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, 200);
            die();
        }
        $post_message = isset($_POST['message']) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        if ($this->isHTML(json_encode($post_message))) {
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, 200);
            die();
        }

        $m= str_replace('\\', '', $post_message);

        $m = json_decode($m,true);

        $setting = $post_message;
        $table_name = $this->db->prefix . "emsfb_setting";
        $email="";
        $em_st=false;

        if($m==null || gettype($m)!='array'){
            $m = $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, 200);
            die();
        }

        foreach ($m as $key => $value) {
            if (in_array($key ,['emailSupporter','femail'])) {
                $m[$key] = sanitize_text_field( wp_unslash($value));
                $email =  $m[$key];

            }else if ($key == "activeCode" && strlen($value) > 1) {

                $server_name = str_replace("www.", "", isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '');
                if (md5($server_name) != $value) {
                    $m = $lang["activationNcorrect"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, 200);
                    die();
                }
                else {

                }

            }else if($key == "emailTemp"){
                if( strlen($value)>5  && (strpos($setting ,'shortcode_message')==false )){
                    $m = $lang["addSCEmailM"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, 200);
                    die();
                }else if(strlen($value)<6 && strlen($value)>0 ){
                    $m = $lang["emailTemplate"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, 200);
                    die();
                }else if(strlen($value)>20001){
                    $m = $lang["addSCEmailM"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, 200);
                    die();
                }else if(strpos($value ,'<script')){
                    $m = $lang["pleaseDoNotAddJsCode"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, 200);
                    die();
                }

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


            }

        }
        if(isset($m['efb_version'])==false){
            $m['efb_version']=EMSFB_PLUGIN_VERSION;
            $st_ = json_encode($m,JSON_UNESCAPED_UNICODE);
            $setting = str_replace('"', '\"', $st_);
        }
        $email = isset($m['emailSupporter']) ? $m['emailSupporter'] : '';
        $this->database_set_emsfb_settings($setting, $email);
        $m = $lang["messageSent"];
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, 200);

    }

    private function database_set_emsfb_settings($setting, $email) {
        global $wpdb;
        $table_name = $wpdb->prefix . "emsfb_setting";
        $wpdb->insert(
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


        wp_cache_delete('emsfb_settings_latest', 'emsfb');
    }

    public function get_ajax_track_admin() {

        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["cCodeNFound","error403"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
        }


        $table_name = $this->db->prefix . "emsfb_msg_";
        $id         = isset($_POST['value']) ? sanitize_text_field( wp_unslash($_POST['value'])) : '';
        $value      = $this->db->get_results($this->db->prepare("SELECT * FROM `$table_name` WHERE track = %s", $id));


        if (count($value)>0) {
            $code = 'efb'. $value[0]->msg_id;
			$code =wp_create_nonce($code);
            $response = ['success' => true, "ajax_value" => $value,'nonce_msg'=> $code , 'id'=>$value[0]->msg_id];
        }
        else {
            $m = $lang["cCodeNFound"];
            $response = ['success' => false, "m" => $m];
        }

        wp_send_json_success($response, 200);

    }

    public function clear_garbeg_admin() {

        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["fileDeleted","error403"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
            die("secure!");
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


        $files    = list_files($upload_dir['basedir']);
        $urlDBStr = json_encode($urlsDB);
        foreach ($files as &$file) {
            if (strpos($file, 'emsfb-PLG-') != false) {
                $namfile = strrchr($file, '/');
                if (strpos($urlDBStr, $namfile) == false) {

                    wp_delete_file($file);
                }

            }
        }
        $m = $lang["fileDeleted"];
        $response = ['success' => true, "m" => $m];

        wp_send_json_success($response, 200);

    }


    public function check_email_server_admin() {

        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error403","emailServer"];
        $lang= $efbFunction->text_efb($text);
        $m = $lang["error403"];
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, 200);
            die("secure!");
        }

        $pro = "not pro";

        if(gettype($ac)=="object" && strlen($ac->activeCode)!=0) $pro=$ac->activeCode;
        $con ='';
        $sub='';
        $to ='';
        $post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

        if('testMailServer'==$post_value){
            if(isset($_POST['email']) && is_email($_POST['email'])){
                $to = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            }
            $m = $lang["emailServer"];
            $sub ="📫 ". $m ." [".esc_html__('Easy Form Builder','easy-form-builder') ."]";
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
        $check = $efbFunction->send_email_state_new([$to , null,$from] ,$sub ,$cont,$pro,'testMailServer',home_url(),$ac);

                if($check==true){
                    $ac->smtp = true;
                    $ac->emailSupporter = $to;
                    $table_name = $this->db->prefix . "emsfb_setting";
                    $newAc= json_encode( $ac ,JSON_UNESCAPED_UNICODE );
                    $newAc= str_replace('"', '\"', $newAc);
                    $this->db->insert(
                        $table_name,
                        [
                            'setting' => $newAc,
                            'edit_by' => get_current_user_id(),
                            'date'    => wp_date('Y-m-d H:i:s'),
                            'email'   => $to,
                        ]
                    );
                    $ok =  [
                        'status' => 'ok_set_smtp',
                        'message' => [
                            'title' => 'configured',
                            'description' => 'user configured email settings',
                            'id' => 'email_settings_configured'
                        ]
                        ];
                    update_option('emsfb_email_status',$ok);
                    set_transient('emsfb_settings_transient', $newAc, 1440);
                    update_option('emsfb_settings', $newAc);
                }
        $response = ['success' => $check ];
        wp_send_json_success($response, 200);
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
        if($check!=false){$ip = substr($ip,0,$check);}
        return $ip;
    }


    public function get_not_read_message() {
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

            }else{
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                if (WP_Filesystem()) {
                    global $wp_filesystem;

                    $directory = EMSFB_PLUGIN_DIRECTORY . '/temp';
                    if (!$wp_filesystem->exists($directory)) {
                        $wp_filesystem->mkdir($directory, 0755);
                    }
                    $r = $wp_filesystem->move($r, EMSFB_PLUGIN_DIRECTORY . '/temp/temp.zip', true);
                } else {

                    $directory = EMSFB_PLUGIN_DIRECTORY . '/temp';
                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    $r = rename($r, EMSFB_PLUGIN_DIRECTORY . '/temp/temp.zip');
                }
                if(is_wp_error($r)){
                    return false;
                }else{

                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    $r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '/temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '/vendor/');
                    if(is_wp_error($r)){
                        error_log('error unzip');

                        return false;
                    }
                    return true;
                }
            }



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
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
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
			die('invalid file '.$_FILES['file']['type']);
		}


	}

    public function custom_ui_plugins(){

           if( is_plugin_active('js_composer/js_composer.php')){




                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery")){

                 }




             }
             require_once(EMSFB_PLUGIN_DIRECTORY."/includes/integrate-wpb.php");

             if (function_exists('register_block_type')) {

                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/gutenberg")){

                }
             }
    }
    public function send_sms_admin_Emsfb(){

        $efbFunction = $this->get_efbFunction(1);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
       if(check_ajax_referer('wp_rest', 'nonce') != 1 && !$currrent_user_can) {

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
        $efbFunction =$this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error403","somethingWentWrongPleaseRefresh","copy"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $response = ['success' => false, 'm' =>$lang["error403"]];
            wp_send_json_success($response, 200);
        }
        if (empty($_POST['id']) || !isset($_POST['type'])) {
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response,200);
        }
        $id = isset($_POST['id']) ? (int) sanitize_text_field( wp_unslash($_POST['id'])) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field( wp_unslash($_POST['type'])) : '';
        if($type =='form'){
            $table_name = $this->db->prefix . "emsfb_form";
            $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id'");
            if(count($value)<1){
                $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
                wp_send_json_success($response,200);
            }
            $val = $value[0];
            $form_name = $val->form_name . " - " . $lang["copy"];
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

            $response = ['success' => true, "m" =>$lang["copy"] , 'form_id'=>$this->id_ , 'form_name'=>$form_name ,
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
        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error403","somethingWentWrongPleaseRefresh","delete"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $response = ['success' => false, 'm' =>$lang["error403"]];
            wp_send_json_success($response, 200);
        }
        if (empty($_POST['val'])) {
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response,200);
        }
        $state = isset($_POST['state']) ? sanitize_text_field( wp_unslash($_POST['state'])) : '';
        $val = isset($_POST['val']) ? sanitize_text_field( wp_unslash($_POST['val'])) : '';
        $val_  = str_replace('\\', '', $val);
        $val = json_decode($val_ ,true);


        if($state =='msg'){
            $table_name = $this->db->prefix . "emsfb_msg_";
            $msg_ids ='';
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $msg_ids !='' ? $msg_ids .=','.$value['msg_id'] : $msg_ids .= $value['msg_id'];
                }

            }
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];

            if($msg_ids !=''){
                $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                $r = $this->db->query($sql);

                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";
                    $sql = "DELETE FROM $table_name WHERE msg_id IN ($msg_ids)";
                    $r = $this->db->query($sql);
                }

                $response = ['success' => true, "m" =>$lang["delete"]];
            }
            wp_send_json_success($response, 200);
        }
    }
    public function read_list_Emsfb(){

        $efbFunction = $this->get_efbFunction(1);
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error403","somethingWentWrongPleaseRefresh","done"];
        $lang= $efbFunction->text_efb($text);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (!check_ajax_referer('wp_rest', 'nonce') && !$currrent_user_can) {

            $response = ['success' => false, 'm' =>$lang["error403"]];
            wp_send_json_success($response, 200);
        }
        if (empty($_POST['val'])) {
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response,200);
        }
        $state = isset($_POST['state']) ? sanitize_text_field( wp_unslash($_POST['state'])) : '';
        $val = isset($_POST['val']) ? sanitize_text_field( wp_unslash($_POST['val'])) : '';

        $val_  = str_replace('\\\\', '', $val);
        $val_  = str_replace('\\', '', $val);
        $val = json_decode($val_ ,true);

        if($state =='msg'){
            $table_name = $this->db->prefix . "emsfb_msg_";
            $msg_ids ='';
            foreach ($val as $key => $value) {
                if(isset($value['msg_id'])){
                    $msg_ids !='' ? $msg_ids .=','.$value['msg_id'] : $msg_ids .= $value['msg_id'];
                }

            }
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
            $user_id = get_current_user_id();
            if($msg_ids !='' ){

                $sql = "UPDATE $table_name SET read_ = 1 WHERE msg_id IN ($msg_ids)";

                $r = $this->db->query($sql);


                if($r>0){
                    $table_name = $this->db->prefix . "emsfb_rsp_";

                    $sql = "UPDATE $table_name SET read_ = 1 WHERE msg_id IN ($msg_ids)";
                    $r = $this->db->query($sql);

                }

                $response = ['success' => true, "m" =>$lang["done"]];
            }
        }
        wp_send_json_success($response, 200);
    }


    public function check_and_enqueue_font_roboto() {

        $font_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap';
        $response = wp_remote_head($font_url);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            wp_register_style('Font_Roboto', $font_url, array(), null);
            wp_enqueue_style('Font_Roboto');
        } else {

            error_log('Font Roboto URL is not accessible.');
        }
    }


    public function heartbeat_Emsfb(){
        $efbFunction = $this->get_efbFunction(1);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (check_ajax_referer('wp_rest', 'nonce') != 1 && !$currrent_user_can) {

            $response = ['success' => false, 'm' =>'Security Error'];
            wp_send_json_success($response, 200);
        }
        $new_nonce = wp_create_nonce('wp_rest');
        $response = ['success' => true, "m" =>'heartBeat' , 'newNonce'=>$new_nonce];
        wp_send_json_success($response, 200);
    }

    public function report_problem_Emsfb(){
        $efbFunction = $this->get_efbFunction(1);
        $currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();

        if (check_ajax_referer('wp_rest', 'nonce') != 1 && !$currrent_user_can) {

            $response = ['success' => false, 'm' =>'Security Error'];
            wp_send_json_success($response, 200);
        }

        $state = isset($_POST['state']) ? sanitize_text_field( wp_unslash($_POST['state'])) : '';
        $value = isset($_POST['value']) ? sanitize_text_field( wp_unslash($_POST['value'])) : '';
        $this->get_efbFunction(0);
        $this->efbFunction->report_problem_efb($state , $value);
        $response = ['success' => true, "m" =>'report_problem_done'];
        wp_send_json_success($response, 200);
    }

    public function get_efbFunction($state){
		if(empty($this->efbFunction)){
			if(!class_exists('Emsfb\efbFunction')){
				require_once(EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php');
			}
			$this->efbFunction = new \Emsfb\efbFunction();
		}


		if($state==1) return $this->efbFunction;
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
            $efbFunction = $this->get_efbFunction(1);
            $settings= $efbFunction->get_setting_Emsfb();

            if(is_array($check)){
                    if($check['status'] === 'ok_set_smtp') {
                        return;
                    }else if ($check['status'] === 'ok' ) {
                        if (isset($settings->smtp) && !in_array($settings->smtp, ['1', 'true', true,1], true)) {
                            $settings->smtp = true;
                            $email = isset($settings->emailSupporter) ? $settings->emailSupporter : '';
                            $st_ = json_encode($settings,JSON_UNESCAPED_UNICODE);
                            $setting = str_replace('"', '\"', $st_);
                            $this->database_set_emsfb_settings($setting, $email);
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
                            $st_ = json_encode($settings,JSON_UNESCAPED_UNICODE);
                            $setting = str_replace('"', '\"', $st_);
                            $this->database_set_emsfb_settings($setting, $email);
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

            $allowed_html = array(
                'div' => array('id' => array(), 'class' => array(), 'style' => array()),
                'button' => array('type' => array(), 'id' => array(), 'style' => array(), 'aria-label' => array()),
                'img' => array('src' => array(), 'alt' => array(), 'style' => array()),
                'p' => array(),
                'strong' => array(),
                'a' => array('href' => array(), 'target' => array()),
            );

            ob_start();
            ?>
            <div id="notice-email-efb" class="notice notice-error efb-notice-email-error notice-alt efb" style="display:flex;align-items:flex-start;gap:12px;padding:10px 20px;position:relative;">
               <button type="button" id="efb-close-notice-btn"
            style="position:absolute;top:8px;right:8px;background:transparent;border:none;font-size:20px;cursor:pointer;"
            aria-label="Close">&times;</button>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr__('Easy Form Builder', 'easy-form-builder'); ?>" style="width:46px;height:auto;margin-top:4px;" />
                <div>
                    <p><strong><?php echo esc_html__('Easy Form Builder Email Warning:', 'easy-form-builder'); ?></strong> <?php echo esc_html($title); ?></p>
                    <p><?php echo esc_html($description); ?></p>
                    <p><?php echo wp_kses_post($help); ?></p>
                </div>
            </div>
            <?php
            $output = ob_get_clean();
            echo wp_kses($output, $allowed_html);
            ?>
            <script>
                (function() {
                    var efbNotice = document.getElementById('notice-email-efb');
                    if (window.sessionStorage.getItem('efb_hide_notice') === '3') {
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

                            var efbNotice = document.getElementById('notice-email-efb');
                            if (efbNotice) efbNotice.style.display = 'none';
                            let count = window.sessionStorage.getItem('efb_hide_notice') ?? 0
                            count = parseInt(count) + 1;
                            window.sessionStorage.setItem('efb_hide_notice', count);
                        });
                    }
                })();
            </script>
            <?php
    }

}




new Admin();

