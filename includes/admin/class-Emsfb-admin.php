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

    //private $wpdb;
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
        
        include(EMSFB_PLUGIN_DIRECTORY."includes/functions.php");
        

        // Add plugin caps to admin role
        if (is_admin() and is_super_admin()) {
            $this->add_cap();
        }

        // Actions.
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('test_call_efb', [$this, 'test_call_efb']);
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

        }

     
    }

    /**
     * Adding new capability in the plugin
     */
    public function add_cap() {
        // Get administrator role
        $role = get_role('administrator');

        $role->add_cap('Emsfb');
        $role->add_cap('Emsfb_create');
        $role->add_cap('Emsfb_panel');

    }

    public function admin_assets($hook) {
        global $current_screen;

        /** Only enqueue scripts and styles on the actual plugin admin pages */
        if (is_admin() && isset($current_screen->id) && strpos($hook, "Emsfb")) {
            //notifcation new version
            wp_register_script('whiteStudioMessage', 'https://whitestudio.team/js/message.js' . $this->plugin_version, null, null, true);
            wp_enqueue_script('whiteStudioMessage');


            wp_enqueue_script('serverJs', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/server.js', null, null, true);
            wp_localize_script(
                'serverJs',
                'ajax_s_esmf',
                [
                    'CurrentVersion' => $this->plugin_version,
                    'LeastVersion'   => '3.33',
                    'check'          => 0
                ]
            );
        }

        // if page is edit_forms_Emsfb
        if (strpos($hook, 'Emsfb') && is_admin()) {

            if (is_rtl()) {
                //code_v1 start
                wp_register_style('Emsfb-css-rtl', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin-rtl.css', true);
                wp_enqueue_style('Emsfb-css-rtl');
                //code_v1 end
            }
            //code_v1 start
            /* wp_register_style('Emsfb-admin-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/admin.css', true);
            wp_enqueue_style('Emsfb-admin-css'); */
            //code_v1 end

            wp_register_style('Emsfb-style-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/style.css', true);
            wp_enqueue_style('Emsfb-style-css');



            wp_register_style('Emsfb-bootstrap-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap.min.css', true);
            wp_enqueue_style('Emsfb-bootstrap-css');

            wp_register_style('Emsfb-bootstrap-icons-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-icons.css', true);
            wp_enqueue_style('Emsfb-bootstrap-icons-css');
            
            wp_register_style('Emsfb-bootstrap-select-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/bootstrap-select.css', true);
            wp_enqueue_style('Emsfb-bootstrap-select-css');

            wp_register_style('Font_Roboto', 'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap');
            wp_enqueue_style('Font_Roboto');
            $lang = get_locale();
            if (strlen($lang) > 0) {
                $lang = explode('_', $lang)[0];
            }

           // $ac = $this->get_setting_Emsfb();
             //code_v1 start
            //source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css
/*             wp_register_style('Font_Awesome-5', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css');
            wp_enqueue_style('Font_Awesome-5'); */

            //source : https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css
    /*         wp_register_style('Font_Awesome-4', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
            wp_enqueue_style('Font_Awesome-4'); */

            //source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.3.0/font-awesome-animation.min.css
          /*   wp_register_style('font-awesome-animation-css', plugins_url('../../public/assets/css/font-awesome-animation.min.css', __FILE__), true);
            wp_enqueue_style('font-awesome-animation-css'); */

            //source :https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js
           /*  wp_enqueue_script('popper-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/popper.min.js');
            wp_enqueue_script('popper-js');  */
             //code_v1 end


                //code_v2 start
                
                wp_enqueue_script('efb-jquery-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery.min.js');
                wp_enqueue_script('efb-jquery-min-js'); 
                
                wp_enqueue_script('efb-bootstrap-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.min.js');
                wp_enqueue_script('efb-bootstrap-min-js'); 
                wp_enqueue_script('efb-bootstrap-bundle-min-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap.bundle.min.js');
                wp_enqueue_script('efb-bootstrap-bundle-min-js'); 
                
                wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
                wp_enqueue_script('efb-bootstrap-select-js'); 

                

                //code_v2 end

        }
    }

    /**
     * Register admin menu
     */
    public function admin_menu() {
        $noti_count = count($this->get_not_read_message());
        $icon       = EMSFB_PLUGIN_URL . '/includes/admin/assets/image/logo-gray.png';


      //  add_menu_page(__('Panel', 'Emsfb'),__('Panel', 'Emsfb'), 'Emsfb', '', '');
        add_menu_page(
            __('Panel', 'Emsfb'),
            $noti_count ? sprintf(__('Easy Form Builder', 'easy-form-builder') . ' <span id="efbCountM" class="awaiting-mod">%d</span>', $noti_count) : __('Easy Form Builder', 'easy-form-builder'),
            
            'Emsfb',
            'Emsfb',
            '',
            '' . $icon . ''
        ); 
        add_submenu_page('Emsfb', __('Panel', 'easy-form-builder'), __('Panel', 'easy-form-builder'), 'Emsfb', 'Emsfb', [$this, 'panel_callback']);
        //

    }

    /**
     * Callback outbox page.
     */
    public function panel_callback() {
        include_once EMSFB_PLUGIN_DIRECTORY . "/includes/admin/class-Emsfb-panel.php";
        $list_table = new Panel_edit();

    }

    public function delete_form_id_public() {

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id = number_format($_POST['id']);

        $table_name = $this->db->prefix . "Emsfb_form";
        $r          = $this->db->delete(
            $table_name,
            ['form_id' => $id],
            ['%d']
        );

        $response = ['success' => true, 'r' => $r];
        wp_send_json_success($response, $_POST);
    }

    public function update_form_id_Emsfb() {

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['value']) || empty($_POST['id']) || empty($_POST['name'])) {
            $response = ['success' => false, "m" => __("Invalid require, Please Check everything" ,'easy-form-builder')];

            wp_send_json_success($response, $_POST);
            die();
        }

        if ($this->isScript(json_encode($_POST['value'])) || $this->isScript(json_encode($_POST['name']))) {
            $response = ['success' => false, "m" => __("You are not allowed use HTML tag" ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id         = number_format($_POST['id']);
        $value      = ($_POST['value']);
        $name       = sanitize_text_field($_POST['name']);
        $table_name = $this->db->prefix . "Emsfb_form";
        //,`form_name` =>
        $r = $this->db->update($table_name, ['form_structer' => $value, 'form_name' => $name], ['form_id' => $id]);

        $response = ['success' => true, 'r' => __("updated" ,'easy-form-builder'), 'value' => "[EMS_Form_Builder id=$id]"];
        wp_send_json_success($response, $_POST);
    }

    public function update_message_state_Emsfb() {
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id']) && $this->isHTML(json_encode($_POST['value']))) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
/*         if (empty($_POST['value'])) {
            $response = ['success' => false, "m" => __("Please enter a vaild value")];
            wp_send_json_success($response, $_POST);
            die();
        } */
        //error_log('json_encode($ _POST[value])');
        //error_log(json_encode($_POST['value']));
/*         if ($_POST['value']) {
            if ($this->isHTML(json_encode($_POST['value']))) {
                $response = ['success' => false, "m" => __("You are not allowed use HTML tag")];
                wp_send_json_success($response, $_POST);
                die();
            }
        } */
        $id = number_format($_POST['id']);

        $table_name = $this->db->prefix . "Emsfb_msg_";
        $r          = $this->db->update($table_name, ['read_' => 1, 'read_by' => get_current_user_id(), 'read_date' => current_time('mysql')], ['msg_id' => $id]);

        $response = ['success' => true, 'r' => __("updated" ,'easy-form-builder')];
        wp_send_json_success($response, $_POST);
    }

    public function get_form_id_Emsfb() {
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id = number_format($_POST['id']);

        $table_name = $this->db->prefix . "Emsfb_form";
        $value      = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, $_POST);

    }

    public function get_messages_id_Emsfb() {
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }

        $id = number_format($_POST['id']);
       // error_log($_POST['form']);
        $table_name = $this->db->prefix . "Emsfb_msg_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE form_id = '$id' ORDER BY `$table_name`.date DESC");
        $response   = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, $_POST);
    }

    public function get_all_response_id_Emsfb() {
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }

        $id = number_format($_POST['id']);

        $table_name = $this->db->prefix . "Emsfb_rsp_";
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE msg_id = '$id'");
        $this->db->update($table_name, ['read_' => 1], ['msg_id' => $id, 'read_' => 0]);
        foreach ($value as $key => $val) {
            $r = (int)$val->rsp_by;
            if ($r > 0) {
                $usr         = get_user_by('id', $r);
                $val->rsp_by = $usr->display_name;
            }
            else {
                $val->rsp_by = __("Guest");
            }
        }

        $response = ['success' => true, 'ajax_value' => $value, 'id' => $id];
        wp_send_json_success($response, $_POST);
    }

    public function set_replyMessage_id_Emsfb() {
        // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند
        // با این مضنون که پاسخ شما داده شده است

        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        if (empty($_POST['id'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page." ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        if (empty($_POST['message'])) {
            $response = ['success' => false, "m" => __("Something went wrong, Please refresh the page.'easy-form-builder'")];
            wp_send_json_success($response, $_POST);
            die();
        }

        if ($this->isHTML(json_encode($_POST['message']))) {
            $response = ['success' => false, "m" => __("You are not allowed use HTML tag",'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        $id = number_format($_POST['id']);
        $m  = sanitize_text_field($_POST['message']);

        $table_name = $this->db->prefix . "Emsfb_rsp_";
        //echo $table_name;

        $ip = $this->ip;
        $this->db->insert(
            $table_name,
            [
                'ip'      => $ip,
                'content' => $m,
                'msg_id'  => $id,
                'rsp_by'  => get_current_user_id(),
                'read_'   => 0

            ]
        );

        $m        = __('Message was sent', 'easy-form-builder');
        $response = ['success' => true, "m" => $m];

        $efbFunction = new efbFunction(); 
        $ac= $efbFunction->get_setting_Emsfb();
        $pro = $ac->activeCode;

        $efbFunction->response_to_user_by_msd_id($id ,$pro);
        wp_send_json_success($response, $_POST);

    }

    public function set_setting_Emsfb() {
        // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند
        // با این مضنون که پاسخ شما داده شده است
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403','easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }

        if (empty($_POST['message'])) {
            $response = ['success' => false, "m" => __("Please enter a message",'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        if ($this->isHTML(json_encode($_POST['message']))) {
            $response = ['success' => false, "m" => __("You are not allowed use HTML tag"  ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die();
        }
        $m = $_POST['message'];

        $setting    = sanitize_text_field(json_encode($_POST['message']));
        $table_name = $this->db->prefix . "Emsfb_setting";
        $email;
        foreach ($m as $key => $value) {
            if ($key == "emailSupporter") {
                $email = $value;
            }
            if ($key == "activeCode" && strlen($value) > 1) {

               // error_log($rdd);
                if (md5($_SERVER['SERVER_NAME']) != $value) {
                    $response = ['success' => false, "m" => __("Your activation code is not Correct!", 'easy-form-builder'),];
                    wp_send_json_success($response, $_POST);
                    die();
                }
                else {
                    // یک رکوست سمت سرور ارسال شود که بررسی کند کد وجود دارد یا نه
                }

            }
 
        }
        //echo $table_name;
        $this->db->insert(
            $table_name,
            [
                'setting' => $setting,
                'edit_by' => get_current_user_id(),
                'date'    => current_time('mysql'),
                'email'   => $email
            ]
        );

        $m        = __('Message was sent', 'easy-form-builder');
        $response = ['success' => true, "m" => $m];
        wp_send_json_success($response, $_POST);

    }

    public function get_ajax_track_admin() {
        //اطلاعات ردیف ترک را بر می گرداند
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403'  ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        //error_log('get_track_id_Emsfb');

        $table_name = $this->db->prefix . "Emsfb_msg_";
        $id         = sanitize_text_field($_POST['value']);
        $value      = $this->db->get_results("SELECT * FROM `$table_name` WHERE track = '$id'");
        /* 	error_log('get_ajax_track_admin');
            error_log($value[0]->track); */

        if ($value[0] != null) {
            $response = ['success' => true, "ajax_value" => $value];
        }
        else {
            $response = ['success' => false, "m" => __("Confirmation Code not found!"  ,'easy-form-builder')];
        }

        wp_send_json_success($response, $_POST);

    }//end function

    public function clear_garbeg_admin() {
        //پاک کردن فایل های اضافی
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403' ,'easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }


        //error_log('clear_garbeg_admin');

        $table_name = $this->db->prefix . "Emsfb_msg_";
        $value      = $this->db->get_results("SELECT content FROM `$table_name`");
        $urlsDB     = [];
        foreach ($value as $v) {
            if (strpos($v->content, 'url') != false) {
                $jsn  = $v->content;
                $jsn  = str_replace('\\', '', $jsn);
                $json = json_decode($jsn);
                /* 	error_log($jsn);
                    error_log(gettype($json)); */
                foreach ($json as $keyR => $row) {
                    foreach ($row as $key => $val) {
                        //error_log(json_encode($val));
                        if ($key == "url" && $val != "" && gettype($val) == 'string') {
                            /* error_log($key);
                            error_log($val); */
                            array_push($urlsDB, $val);
                        }
                    }
                }

            }
        }
        //error_log(json_encode($urlsDB));
        $upload_dir = wp_upload_dir();
        //error_log($upload_dir['basedir']);
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

        $response = ['success' => true, "m" => __("Files are Deleted",'easy-form-builder')];

        wp_send_json_success($response, $_POST);

    }//end function


    public function check_email_server_admin() {
        //پاک کردن فایل های اضافی
        if (check_ajax_referer('admin-nonce', 'nonce') != 1) {
            //error_log('not valid nonce');
            $response = ['success' => false, 'm' => __('Security Error 403','easy-form-builder')];
            wp_send_json_success($response, $_POST);
            die("secure!");
        }
        
        $efbFunction = new efbFunction();   
        $ac= $efbFunction->get_setting_Emsfb();
        $pro = $ac->activeCode;
        $con ='';
        $sub='';
        $to ='';
        if('testMailServer'==$_POST['value']){

            if(is_email($_POST['email'])){
                $to = sanitize_email($_POST['email']);
            }
            $sub ="📫 ".__('Email server','easy-form-builder')." [".__('Easy Form Builder','easy-form-builder') ."]";
            $cont = "Test Email Server";
            if(strlen($to)<5) {
                $to =$ac->emailSupporter;}
        }
        $check = $efbFunction->send_email_state( $to,$sub ,$cont,$pro,"testMailServer");
        if($check==true){
           
         $newAc["activeCode"] = $ac->activeCode;
         $newAc["siteKey"] = $ac->siteKey;
         $newAc["secretKey"] = $ac->secretKey;
         $newAc["emailSupporter"] = $to;
         $newAc["apiKeyMap"] = $ac->apiKeyMap;
         $newAc["smtp"] = "true";
            
            $table_name = $this->db->prefix . "Emsfb_setting";
            $this->db->insert(
                $table_name,
                [
                    'setting' => json_encode($newAc),
                    'edit_by' => get_current_user_id(),
                    'date'    => current_time('mysql'),
                    'email'   => $ac->emailSupporter
                ]
            );
        }
        $response = ['success' => $check ];
        wp_send_json_success($response, $_POST);
    }
    public function isHTML($str) {
        return preg_match("/\/[a-z]*>/i", $str) != 0;
    }

    public function get_ip_address() {
        //source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {$ip = $_SERVER['REMOTE_ADDR'];}
        return $ip;
    }

/* 	public function get_setting_Emsfb()
	{
		// اکتیو کد بر می گرداند	
		
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$r =json_decode($val);
			$rtrn =$r->activeCode;
			break;
			} 
		}
		return $r;
	}
 */
    public function get_not_read_message() {
        $table_name = $this->db->prefix . "Emsfb_msg_";
        $value      = $this->db->get_results("SELECT msg_id,form_id FROM `$table_name` WHERE read_=0");
        $rtrn       = 'null';
        return $value;
    }

   

        public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
  
       
}

new Admin();


