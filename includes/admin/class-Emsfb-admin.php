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

    //private $wpdb;
    public function __construct() {
        
        $this->init_hooks();
        global $wpdb;
        $this->db = $wpdb;
        //$this->efbFunction = new efbFunction();        
       
    }

    /**
     * Initial plugin
     */
    private function init_hooks() {
        // Check exists require function
        if (!function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
           
        }
       
       // apply_filters( 'the_content', [$this,'check_shortCode'] );
        // Add plugin caps to admin role
        if (is_admin() and is_super_admin()) {
            $this->add_cap();
        }

        // Actions.
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
 
        $this->ip = $this->get_ip_address();

        //$current_user->display_name
        if (is_admin()) {
            // برای نوشتن انواع اکشن مربوط به حذف و اضافه اینجا انجام شود
           
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $plugin_data          = get_plugin_data(EMSFB_PLUGIN_FILE);
            $this->plugin_version = $plugin_data['Version'];

           
            //$this->get_not_read_message();
            add_action('wp_ajax_remove_id_Emsfb', [$this, 'delete_form_id_public']);                 //یک فرم بر اساس ي دی حذف می کند
            add_action('wp_ajax_remove_message_id_Emsfb', [$this, 'delete_message_id_public']);                 //یک پیام بر اساس ي دی حذف می کند
            add_action('wp_ajax_get_form_id_Emsfb', [$this, 'get_form_id_Emsfb']);                   // اطلاعات یک فرم را بر اساسا آی دی بر می گرداند
            add_action('wp_ajax_get_messages_id_Emsfb', [$this, 'get_messages_id_Emsfb']);           // اطلاعات یک مسیج را بر می گرداند بر اساس ای دی
            add_action('wp_ajax_get_all_response_id_Emsfb', [$this, 'get_all_response_id_Emsfb']);   // اطلاعات همه مسیج را بر می گرداند بر اساس ای دی
            add_action('wp_ajax_update_form_Emsfb', [$this, 'update_form_id_Emsfb']);                //فرم را بروز رسانی می کند
            add_action('wp_ajax_update_message_state_Emsfb', [$this, 'update_message_state_Emsfb']); // وضععیت پیام را بروز رسانی می کند وضعیت خوانده شدن
            add_action('wp_ajax_set_replyMessage_id_Emsfb', [$this, 'set_replyMessage_id_Emsfb']);   // پاسخ ادمین را در دیتابیس ذخیره می کند
            add_action('wp_ajax_set_setting_Emsfb', [$this, 'set_setting_Emsfb']);                   // پاسخ ادمین را در دیتابیس ذخیره می کند
            add_action('wp_ajax_get_track_id_Emsfb', [$this, 'get_ajax_track_admin']);               //ردیف ترکینگ را بر می گرداند
            add_action('wp_ajax_clear_garbeg_Emsfb', [$this, 'clear_garbeg_admin']);                 //فایل های غیر ضروری را پاک می کند
            add_action('wp_ajax_check_email_server_efb', [$this, 'check_email_server_admin']);        //ارسال ایمیل    
            add_action('wp_ajax_add_addons_Emsfb', [$this, 'add_addons_Emsfb']);                     //Add new addons
            add_action('wp_ajax_remove_addons_Emsfb', [$this, 'remove_addons_Emsfb']);                //Remove a addon
            add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));               // بارگذاری فایل

           
            $this->custom_ui_plugins();
          
           
            /*    add_action( 'save_post', function ( $post_ID,$post,$update )
           {
            //https://developer.wordpress.org/reference/hooks/field_no_prefix_save_pre/
           }, 10, 3 ); */

          /*  add_action( 'transition_post_status', function ( $new_status, $old_status, $post )
            {
                if( 'publish' == $new_status && 'publish' == $old_status && $post->post_type == 'my_post_type' ) {

                    //DO SOMETHING IF A POST IN POST TYPE IS EDITED

                }
            }, 10, 3 ); */

       
        } 
    }

    public function add_cap() {
        // Get administrator role
        $role = get_role('administrator');

        $role->add_cap('Emsfb');
        $role->add_cap('Emsfb_create');
        $role->add_cap('Emsfb_panel');
        $role->add_cap('Emsfb_addon');

        

    }

    public function admin_assets($hook) {
        global $current_screen;       
        $hook = $hook ? $hook : http_build_query($_GET);
        /** Only enqueue scripts and styles on the actual plugin admin pages */
        if (is_admin() && isset($current_screen->id) && strpos($hook, "Emsfb")==true) {
            //$this->pay_stripe_sub_Emsfb();
            //notifcation new version
          /*   wp_register_script('whiteStudioMessage', 'https://whitestudio.team/js/message.js' . $this->plugin_version, null, null, true);
            wp_enqueue_script('whiteStudioMessage'); */


      /*       wp_enqueue_script('serverJs', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/server.js', null, null, true);
            wp_localize_script(
                'serverJs',
                'ajax_s_esmf',
                [
                    'CurrentVersion' => $this->plugin_version,
                    'LeastVersion'   => '3.33',
                    'check'          => 0
                ]
            ); */
        }

        // if page is edit_forms_Emsfb
        if (strpos($hook, 'Emsfb')==true && is_admin()) {

            if (is_rtl()) {
                //code_v1 start
                wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl.css', true,'3.6.0' );
                wp_enqueue_style('Emsfb-css-rtl');
                //code_v1 end
            }

            wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style.css',true,'3.6.0');
            wp_enqueue_style('Emsfb-style-css');

            wp_register_style('Emsfb-bootstrap', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min.css',true,'3.6.0');
            wp_enqueue_style('Emsfb-bootstrap');

            wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons.css',true,'3.6.0');
            wp_enqueue_style('Emsfb-bootstrap-icons-css');
            
            wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select.css',true,'3.6.0');
            wp_enqueue_style('Emsfb-bootstrap-select-css');

            wp_register_style('Font_Roboto', 'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap');
            wp_enqueue_style('Font_Roboto');
            $lang = get_locale();
            if (strlen($lang) > 0) {$lang = explode('_', $lang)[0];}

                wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min.js',false,'3.6.0');
                wp_enqueue_script('efb-bootstrap-min-js'); 

                 wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min.js', array( 'jquery' ),true,'3.6.0');
                wp_enqueue_script('efb-bootstrap-bundle-min-js');  
                
                wp_enqueue_script('efb-bootstrap-icon-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-icon.js',false,'3.6.0');
                wp_enqueue_script('efb-bootstrap-icon-js'); 
        }
    }

    /**
     * Register admin menu
     */
    public function admin_menu() {
        $noti_count = count($this->get_not_read_message());
        $icon       = EMSFB_PLUGIN_URL . '/includes/admin/assets/image/logo-gray.png';
        add_menu_page(
            __('Panel', 'Emsfb'),
            $noti_count ? sprintf(__('Easy Form Builder', 'easy-form-builder') . ' <span id="efbCountM" class="efb awaiting-mod">%d</span>', $noti_count) : __('Easy Form Builder', 'easy-form-builder'),
            
            'Emsfb',
            'Emsfb',
            '',
            '' . $icon . ''
        ); 
        add_submenu_page('Emsfb', __('Panel', 'easy-form-builder'), __('Panel', 'easy-form-builder'), 'Emsfb', 'Emsfb', [$this, 'panel_callback']);
        
    }

    /**
     * Callback outbox page.
     */
    public function panel_callback() {
        include_once EMSFB_PLUGIN_DIRECTORY . "/includes/admin/class-Emsfb-panel.php";
        $list_table = new Panel_edit();

    }

    public function delete_form_id_public() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        
        if (empty($_POST['id'])) {
            $m = $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id =  ( int ) sanitize_text_field($_POST['id']) ;

        $table_name = $this->db->prefix . "emsfb_form";
        $r          = $this->db->delete(
            $table_name,
            ['form_id' => $id],
            ['%d']
        );

        $response = ['success' => true, 'r' => $r];
        wp_send_json_success($response, $_POST);
    }
    public function delete_message_id_public() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        
        if (empty($_POST['id'])) {
            $m = $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id =  ( int ) sanitize_text_field($_POST['id']) ;

        $table_name = $this->db->prefix . "emsfb_msg_";
        $r          = $this->db->delete(
            $table_name,
            ['msg_id' => $id],
            ['%d']
        );

        $response = ['success' => true, 'r' => $r];
        wp_send_json_success($response, $_POST);
    }

    public function update_form_id_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","invalidRequire","nAllowedUseHtml","updated","upDMsg"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['value']) || empty($_POST['id']) || empty($_POST['name'])) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];

            wp_send_json_success($response, $_POST);
            die();
        }

        if ($this->isScript(json_encode($_POST['value']),JSON_UNESCAPED_UNICODE) || $this->isScript(json_encode($_POST['name']),JSON_UNESCAPED_UNICODE)) {        
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id =  ( int ) sanitize_text_field($_POST['id']) ;

        $valp =str_replace('\\', '', $_POST['value']);
		$valp = json_decode($valp,true);
		$valp = $efbFunction->sanitize_obj_msg_efb($valp);
		$value =json_encode($valp,JSON_UNESCAPED_UNICODE);
        //$value      = ($_POST['value']); 
        $name       = sanitize_text_field($_POST['name']);
        $table_name = $this->db->prefix . "emsfb_form";
        //,`form_name` =>
        $r = $this->db->update($table_name, ['form_structer' => $value, 'form_name' => $name], ['form_id' => $id]);
        $m = $lang["updated"];
        $response = ['success' => true, 'r' =>"updated", 'value' => "[EMS_form_builder id=$id]"];
        wp_send_json_success($response, $_POST);
    }
    public function add_addons_Emsfb() {
        
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
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
        
        $value      =sanitize_text_field($_POST['value']);
        $allw = ["AdnSPF","AdnOF","AdnPPF","AdnATC","AdnSS","AdnCPF","AdnESZ","AdnSE",
                 "AdnWHS","AdnPAP","AdnWSP","AdnSMF","AdnPLF","AdnMSF","AdnBEF","AdnPDP","AdnADP"];

        $dd =gettype(array_search($value, $allw));
        
        if (check_ajax_referer('admin-nonce', 'nonce') != 1 || $dd!="integer") {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
           
        }

        if (empty($_POST['value']) ) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
         
        }

        if ($this->isScript($_POST['value'])) {        
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
        }
       if($value!="AdnOF"){

            // اگر لینک دانلود داشت
            $server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
            $vwp = get_bloginfo('version');
            $u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
            $request = wp_remote_get($u);
            
            if( is_wp_error( $request )) {

                $m = __('Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server','easy-form-builder');
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, $_POST);
               
            }
            
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body );

            if($data->status==false){
                $response = ['success' => false, "m" => $data->error];
                wp_send_json_success($response, $_POST);
               
            }

            // Check version of EFB to Addons
            if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {        
                $m = $lang["upDMsg"];
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, $_POST);
                
            }

            if($data->download==true ){
                $url =$data->link;
                //$url ="https://easyformbuilder.ir/source/files/zip/stripe.zip";
               $s= $this->fun_addon_new($url);
               if($s==false ){
                $m = __('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files','easy-form-builder');
                $response = ['success' => false, "m" => $m];
                wp_send_json_success($response, $_POST);
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

            //$ac['AdnSPF=0;
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
        // بر اساس نام ستینگ در دیتا بیس ذخیره شود 
        //در سمت کلاینت مقدار مربوط به نام تنظیم ترو شود و اگر وجود نداشت اضافه شود
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        wp_send_json_success($response, $_POST);
    }
    public function remove_addons_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","done","invalidRequire"];
        $lang= $efbFunction->text_efb($text);
        $ac= $efbFunction->get_setting_Emsfb();
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['value']) ) {
            $m = $lang["invalidRequire"];
            $response = ['success' => false, "m" => $m];

            wp_send_json_success($response, $_POST);
            die();
        }

        if ($this->isScript($_POST['value'])) {        
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }

        $value      = $_POST['value'];
        $server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
        
       
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

            //$ac['AdnSPF=0;
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
        $ac->{$value}=0;
        
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
        // بر اساس نام ستینگ در دیتا بیس ذخیره شود 
        //در سمت کلاینت مقدار مربوط به نام تنظیم ترو شود و اگر وجود نداشت اضافه شود
        $response = ['success' => true, 'r' =>"done", 'value' => "add_addons_Emsfb",'new'=>$newAc];
        wp_send_json_success($response, $_POST);
    }

    public function update_message_state_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh","updated"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id']) && $this->isHTML(json_encode($_POST['value']),JSON_UNESCAPED_UNICODE)) {            
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
/*         if (empty($_POST['value'])) {
            $response = ['success' => false, "m" => __("Please enter a valid value")];
            wp_send_json_success($response, $_POST);
            die();
        } */
        //error_log('json_encode($ _POST[value])');
        //error_log(json_encode($_POST['value']));
/*         if ($_POST['value']) {
            if ($this->isHTML(json_encode($_POST['value']))) {
                $response = ['success' => false, "m" => __("You are not allowed to use HTML tag")];
                wp_send_json_success($response, $_POST);
                die();
            }
        } */
        $id =  ( int ) sanitize_text_field($_POST['id']);
        
        $table_name = $this->db->prefix . "emsfb_msg_";
        $r          = $this->db->update($table_name, ['read_' => 1, 'read_date' => wp_date('Y-m-d H:i:s')], ['msg_id' => $id]);

        $m =   $lang["updated"];
        $response = ['success' => true, 'r' =>"updated"];
        wp_send_json_success($response, $_POST);
    }

    public function get_form_id_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id =  ( int ) sanitize_text_field($_POST['id']) ;

        $table_name = $this->db->prefix . "emsfb_form";
        $value      = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, $_POST);

    }
    //stripe
   

    public function get_messages_id_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh"];
        $lang= $efbFunction->text_efb($text);

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {        
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id = sanitize_text_field(($_POST['id']));
        $code = 'efb'. $id;
        
        $code =wp_create_nonce($code); 
            
        $id =  ( int ) sanitize_text_field($id);
       
        $table_name = $this->db->prefix . "emsfb_msg_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id' ORDER BY `$table_name`.date DESC");
        //error_log(json_encode($value));
        $response   = ['success' => true, 'ajax_value' => $value, 'id' => $id,'nonce_msg'=> $code];
        wp_send_json_success($response, $_POST);
    }

    public function get_all_response_id_Emsfb() {
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $text = ["error403","somethingWentWrongPleaseRefresh" ,"guest"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
                       
            $m =   $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {            
            $m =   $lang["somethingWentWrongPleaseRefresh"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }

        $id =  ( int ) sanitize_text_field($_POST['id']) ;
        //error_log($id);
        $table_name = $this->db->prefix . "emsfb_rsp_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE msg_id = '$id'");
        $this->db->update($table_name, ['read_' => 1], ['msg_id' => $id, 'read_' => 0]);
        foreach ($value as $key => $val) {
            $r = (int)$val->rsp_by;
            if ($r > 0) {
                $usr         = get_user_by('id', $r);
                $val->rsp_by = $usr->display_name;
            }
            else {
                $m =   $lang["guest"];
                $val->rsp_by =$m;
            }
        }

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, $_POST);
    }

    public function set_replyMessage_id_Emsfb() {
        // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند
        // با این مضنون که پاسخ شما داده شده است
        error_log('admin ====>set_replyMessage_id_Emsfb');
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error405","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","messageSent"];
        $lang= $efbFunction->text_efb($text);

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
                        
            $response = ['success' => false, 'm' => $lang["error403"]];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" =>$lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response, $_POST);
            die();
        }
        if (empty($_POST['message'])) {
            $response = ['success' => false, "m" => $lang["somethingWentWrongPleaseRefresh"]];
            wp_send_json_success($response, $_POST);
            die();
        }

        if ($this->isHTML(json_encode($_POST['message']))) {
            $response = ['success' => false, "m" => $lang["nAllowedUseHtml"]];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id =  ( int ) sanitize_text_field($_POST['id']) ;
        $id = preg_replace('/[,]+/','',$id);
        $m  = sanitize_text_field($_POST['message']);

        //echo $table_name;
        $m = str_replace("\\","",$m);	        
        $message =json_decode($m);
				$valobj=[];
				$stated=1;
				foreach ($message as $k =>$f){
					
					$in_loop=true;
				
					if($stated==0){break;}					  
						switch ($f->type) {											
							case 'allformat':	
								$d = $_SERVER['HTTP_HOST'];
								//$p = strpos($item['url'],'http://'.$d);
								//don't change value stated because always file is sending 
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
								//$item['value'] =  'test';
								$in_loop=false;
							break;
						}
						if($stated==0){
							$response = array( 'success' => false  , 'm'=>$lang["error405"]); 
							wp_send_json_success($response,$_POST);
						}
						//&& $f['name']==$item['name']	
						/* 										
						error_log(json_encode($item));	 */																											
				//empty($valobj) ? error_log('valobj empty') : 	
				
				}
                $m = json_encode($message,JSON_UNESCAPED_UNICODE);
				$m = str_replace('"', '\\"', $m);
        //"type\":\"closed\"
        
        $table_name = $this->db->prefix . "emsfb_msg_";
        if(strpos($m , '"type\":\"closed\"')){
            /* 
             */
            //$id
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
                'read_'   => 0,
                'date'    => wp_date('Y-m-d H:i:s')

            ]
        );
        $m        = $lang["messageSent"];
        $response = ['success' => true, "m" => $m];
        //"rescl", "resop",
        $pro =isset( $ac->activeCode) ? $ac->activeCode : null;

        $efbFunction->response_to_user_by_msd_id($id ,$pro);
        wp_send_json_success($response, $_POST);

    }

    public function set_setting_Emsfb() {
        // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["pleaseDoNotAddJsCode","emailTemplate","addSCEmailM","messageSent","activationNcorrect","error403","somethingWentWrongPleaseRefresh","nAllowedUseHtml","PEnterMessage"];
        $lang= $efbFunction->text_efb($text);

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            $m = $lang["error403"];
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['message'])) {
            $m = $lang["PEnterMessage"];
            $response = ['success' => false, "m" => $m];
            wp_send_json_success($response, $_POST);
            die();
        }
        if ($this->isHTML(json_encode($_POST['message']))) {            
            $m = $lang["nAllowedUseHtml"];
            $response = ['success' => false, "m" =>$m];
            wp_send_json_success($response, $_POST);
            die();
        }
        
        $m= str_replace('\\', '', $_POST['message']);
        //$m= $_POST['message'];
        $m = json_decode($m,true);
     //  $setting    = sanitize_text_field($_POST['message']);
        $setting    = $_POST['message'];
        $table_name = $this->db->prefix . "emsfb_setting";
        $email="";
        $em_st=false;
        
        foreach ($m as $key => $value) {
            if ($key == "emailSupporter") {
                $m[$key] = sanitize_text_field($value);
                $email =  $m[$key];
                
            }else if ($key == "activeCode" && strlen($value) > 1) {
                $server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
                if (md5($server_name) != $value) {
                    $m = $lang["activationNcorrect"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, $_POST);
                    die();
                }
                else {
                    // یک رکوست سمت سرور ارسال شود که بررسی کند کد وجود دارد یا نه
                }
              
            }
             else if($key == "emailTemp"){
               
                //error_log(strlen($value));
                if( strlen($value)>5  && (strpos($setting ,'shortcode_message')==false || strpos($setting ,'shortcode_title')==false)){
                    $m = $lang["addSCEmailM"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, $_POST);
                    die();
                }else if(strlen($value)<6 && strlen($value)>0 ){
                    $m = $lang["emailTemplate"];               
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, $_POST);
                    die();
                }else if(strlen($value)>20001){                 
                    $m = $lang["addSCEmailM"];                    
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, $_POST);
                    die();
                }else if(strpos($value ,'<script')){
                    $m = $lang["pleaseDoNotAddJsCode"];
                    $response = ['success' => false, "m" =>$m];
                    wp_send_json_success($response, $_POST);
                    die();
                }
        
            } 

 
        }
 
        $this->db->insert(
            $table_name,
            [
                'setting' => $setting,
                'edit_by' => get_current_user_id(),
                'date'    => wp_date('Y-m-d H:i:s'),
                'email'   => $email
            ]
        );

        $m = $lang["messageSent"];            
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, $_POST);

    }

    public function get_ajax_track_admin() {
        //اطلاعات ردیف ترک را بر می گرداند
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["cCodeNFound","error403"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        

        $table_name = $this->db->prefix . "emsfb_msg_";
        $id         = sanitize_text_field($_POST['value']);
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE track = '$id'");
        /* 	
             */

        if (count($value)>0) {
            $code = 'efb'. $value[0]->msg_id;
			$code =wp_create_nonce($code);
            $response = ['success' => true, "ajax_value" => $value,'nonce_msg'=> $code , 'id'=>$value[0]->msg_id];
        }
        else {
            $m = $lang["cCodeNFound"];
            $response = ['success' => false, "m" => $m];
        }

        wp_send_json_success($response, $_POST);

    }//end function

    public function clear_garbeg_admin() {
        //پاک کردن فایل های اضافی
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["fileDeleted","error403"];
        $lang= $efbFunction->text_efb($text);
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            
            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, $_POST);
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
                        //error_log(json_encode($val));
                        if ($key == "url" && $val != "" && gettype($val) == 'string') {
                            /* 
                             */
                            array_push($urlsDB, $val);
                        }
                    }
                }

            }
        }
        //error_log(json_encode($urlsDB));
        $upload_dir = wp_upload_dir();
        
        //$arrayFiles=[] ;
        $files    = list_files($upload_dir['basedir']);
        $urlDBStr = json_encode($urlsDB);
        foreach ($files as &$file) {
            if (strpos($file, 'emsfb-PLG-') != false) {
                $namfile = strrchr($file, '/');
                if (strpos($urlDBStr, $namfile) == false) {
                    //array_push($arrayFiles,$file);
                    wp_delete_file($file);
                }

            }
        }        
        $m = $lang["fileDeleted"];
        $response = ['success' => true, "m" => $m];

        wp_send_json_success($response, $_POST);

    }//end function


    public function check_email_server_admin() {
        //پاک کردن فایل های اضافی
        $efbFunction = empty($this->efbFunction) ? new efbFunction() :$this->efbFunction ;   
        $ac= $efbFunction->get_setting_Emsfb();
        $text = ["error403","emailServer"];
        $lang= $efbFunction->text_efb($text);
        $m = $lang["error403"];
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {     
            $response = ['success' => false, 'm' => $m];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        
        $pro = "not pro";
       
        if(gettype($ac)=="object" && strlen($ac->activeCode)!=0) $pro=$ac->activeCode;  
        $con ='';
        $sub='';
        $to ='';
        if('testMailServer'==$_POST['value']){
            if(is_email($_POST['email'])){
                $to = sanitize_email($_POST['email']);
            }
            $m = $lang["emailServer"];
            $sub ="📫 ". $m ." [".__('Easy Form Builder','easy-form-builder') ."]";
            $cont = "Test Email Server";
            if(strlen($to)<5) {
                if(strlen($ac->emailSupporter)!=0) {$to = $ac->emailSupporter;}else{
                    $to="null";
                }
            }
        }
        
        $check = $efbFunction->send_email_state( $to,$sub ,$cont,$pro,"testMailServer" , home_url());
                if($check==true){           
                    $newAc["activeCode"] = isset($ac->activeCode) ? $ac->activeCode :'';
                    $newAc["siteKey"] = isset($ac->siteKey)? $ac->siteKey :"";
                    $newAc["secretKey"] =isset($ac->secretKey)?  $ac->secretKey :"";
                    $newAc["emailSupporter"] = $to;
                    $newAc["apiKeyMap"] = isset($ac->apiKeyMap) ? $ac->apiKeyMap:"";
                    $newAc["emailTemp"] = isset($ac->emailTemp) ? $ac->emailTemp:"";
                    $newAc["smtp"] = "true";
                    $newAc["text"] = isset($ac->text) ? $ac->text  : $efbFunction->text_efb(0); //change78 باید لیست جملات اینجا ذخیره شود
                    $table_name = $this->db->prefix . "emsfb_setting";
                    $newAc= json_encode( $newAc ,JSON_UNESCAPED_UNICODE );
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
                //}
                }
        $response = ['success' => $check ];
        wp_send_json_success($response, $_POST);
    }
    public function isHTML($str) {
        return preg_match("/\/[a-z]*>/i", $str) != 0;
    }

    public function get_ip_address() {
        //source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
        $ip='1.1.1.1';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {$ip = $_SERVER['REMOTE_ADDR'];}
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
            //http://easyformbuilder.ir/videos/how-create-add-form-Easy-Form-Builder-version-3.mp4
            //$url = 'https://easyformbuilder.ir/source/files/zip/stripe.zip';
            $name =substr($url,strrpos($url ,"/")+1,-4);
            /* 
             */
            $r =download_url($url);
            if(is_wp_error($r)){
                //show error message
            }else{
                $r = rename($r, EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip');
                if(is_wp_error($r)){
                    return false;
                }else{
                    
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    WP_Filesystem();
                    $r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
                    if(is_wp_error($r)){
                        error_log('error unzip');
                        error_log(json_encode($r));
                        return false;
                    }
                    return true; 
                }            
            }


            //run install php of addons
            $fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/".$name."/".$name.".php"; 
                    
            if(file_exists($fl_ex)){         
                $name ='\Emsfb\\'.$name;
                require_once  $fl_ex;  
                $t = new $name();      
            }
            
        }
        
    public function file_upload_public(){
        
        /* start new code */
        $_POST['id']=sanitize_text_field($_POST['id']);
        $_POST['pl']=sanitize_text_field($_POST['pl']);
        $_POST['nonce_msg']=sanitize_text_field($_POST['nonce_msg']);
        $vl=null;
        
        
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{
            $id = $_POST['id'];
            $table_name = $this->db->prefix . "emsfb_form";
            $vl  = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");
            if($vl!=null){              
                if(strpos($vl , '\"type\":\"dadfile\"') || strpos($vl , '\"type\":\"file\"') || strpos($vl , '"type":"dadfile"') || strpos($vl , '"type":"file"')){
                    $vl ='efb'.$id;
                    //'efb'.$this->id
                }
                /* $m = str_replace('\\', '', $vl);       
                $vl = json_decode($m,true); */
                //"type":"file"
                //
            }
        
        }
       
        /* end new code */
        //error_log(json_decode(check_ajax_referer('public-nonce','nonce')));
       // check_ajax_referer($msgnonce,'nonce_msg')
        //$nonce_msg = $_POST['nonce_msg'];
		if (check_ajax_referer('public-nonce','nonce')!=1 && check_ajax_referer($vl,"nonce_msg")!=1){
			
			
			$response = array( 'success' => false  , 'm'=>"403 Forbidden Error"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
        /* end new code */
		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,'image/heic',
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' , 'video/mpeg', 'video/mpg', 'audio/mpg','video/mov','video/quicktime',
		 'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'
		);

      
		
		if (in_array($_FILES['file']['type'], $arr_ext)) { 
			// تنظیمات امنیتی بعدا اضافه شود که فایل از مسیر کانت که عمومی هست جابجا شود به مسیر دیگری
						
			$name = 'efb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION) ;
			
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["file"]["tmp_name"]));				
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}            
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$_FILES['file']['type']); 
			  wp_send_json_success($response,$_POST);
		}else{
			$response = array( 'success' => false  ,'error'=>"File Type Error"); 
			wp_send_json_success($response,$_POST);
			die('invalid file '.$_FILES['file']['type']);
		}
		
		 
	}//end function


    /* function test_call_efb(){
        error_log('function===============>test_call_efb');
    } */

    public function custom_ui_plugins(){
           //// Check if wpbakery available
           if( is_plugin_active('js_composer/js_composer.php')){          
                //first check wp bakery addons installed or not
                // if wp bakery is not installed
                // first install after that call wp bakery function            
                 //error_log("WPBakeryShortCode exist");
                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery")){                    
                     //error_log("directory wpbakery not exist");
                 }
                 
                 //require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/wpbakery/wpb_extend.php");
                 //require_once(EMSFB_PLUGIN_DIRECTORY."/includes/integrate-wpb.php");
                 
             }
             require_once(EMSFB_PLUGIN_DIRECTORY."/includes/integrate-wpb.php");
             // Check if Gutenberg editor is available
             if (function_exists('register_block_type')) {
                 //error_log("Gutenberg exist");
                 if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/gutenberg")){                    
                    //error_log("directory gutenberg not exist");
                }
             }
    }





}

new Admin();

