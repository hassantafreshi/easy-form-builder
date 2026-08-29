<?php


namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class smssendefb{

    public function __construct() {

        $this->create_sms_tables_efb();
        //error_log('smssendefb');
        add_action('create_sms_tables_efb', [$this, 'create_sms_tables_efb']);
    }

    public function create_sms_tables_efb(){
        $s = intval(get_option('emsfb_addon_AdnSS',0));
        if($s ==2 ){
            return;
        }
        //error_log('create_sms_tables_efb');
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_sent_list';
        $table_name_contact = $wpdb->prefix . 'emsfb_sms_contact';
        //$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        ////error_log(json_encode($table_exists));
        //if($table_exists != $table_name) {
            //error_log('create_sms_tables_efb=>table not exist!');
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `number`  varchar(255) NOT NULL,
                `message` text NOT NULL,
                `status`  varchar(20) NOT NULL,
                `date`    datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `form_id` int(11) NOT NULL,
                PRIMARY KEY (`id`)
              ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );

            $sql = "CREATE TABLE IF NOT EXISTS $table_name_contact (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `admin_numbers`  varchar(255) NOT NULL,
                `form_id` int(11) NOT NULL,
                `recived_message_noti_user` text NOT NULL,
                `new_message_noti_user` text NOT NULL,
                `new_message_noti_admin` text NOT NULL,
                `new_response_noti` text NOT NULL,
                `date`    datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`)
              ) $charset_collate;";
              require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
              dbDelta( $sql );
        //}
        update_option('emsfb_addon_AdnSS',2);
    }


    public function get_sms_sent_list_efb($form_id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_sent_list';
        if($form_id == 0){
            $sql = "SELECT * FROM $table_name ORDER BY id DESC";
        }else{

        $sql = "SELECT * FROM $table_name WHERE form_id = $form_id ORDER BY id DESC";
        }

        $result = $wpdb->get_results($sql);
        return $result;
    }

    public function send_sms_efb($number,$message,$form_id,$severType){
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_sent_list';
        $message = str_replace(["\\\\n", "@n#"], ["\n", "\n"], $message);

        if($form_id==0){
            $form_id = get_current_user_id()*-1;
        }

        $sent = false;
        if($severType == 'wpsms'){
            //https://wp-sms-pro.com/resources/wp_sms_send/
            $to = gettype($number) == 'string' ? explode(',', $number) : $number;
            $to = $this->check_fix_number($to);
            if(function_exists('wp_sms_send')){
                $result = wp_sms_send($to,$message);
                $sent = !is_wp_error($result) && $result !== false;
            }else{
                return false;
            }

        }else{
            // ws.team and other gateways have no implementation yet; report failure
            // instead of logging a row that pretends the SMS was sent
            return false;
        }
        $numbers= is_string($number) ? $number :implode(',', $number);
        $sql = $wpdb->prepare(
            "INSERT INTO $table_name (number, message, status, form_id) VALUES (%s, %s, %s, %d)",
            $numbers,
            $message,
            $sent ? $severType : 'failed',
            $form_id
        );

        $wpdb->query($sql);
        return $sent ? $wpdb->insert_id : false;
    }

    public function check_fix_number($numbers) {
        return array_map(function($number) {
            $number = preg_replace('/[ \-\(\)]/', '', $number);
            $pos = strrpos($number, '+');
            if ($pos !== false) {
                $number = substr($number, $pos);
            }

            return $number;
        }, $numbers);
    }

    public function delete_sms_efb($id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_sent_list';
        $sql = "DELETE FROM $table_name WHERE id = $id";
        $wpdb->query($sql);
    }


    public function get_sms_contact_efb($form_id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_contact';
        $sql = "SELECT * FROM $table_name WHERE form_id = $form_id";
        //$result = $wpdb->get_results($sql);
        $result = $wpdb->get_row($sql);
        return $result;
    }

    public function add_sms_contact_efb($form_id,$admin_numbers,$recived_message_noti_user,$new_message_noti_user,$new_message_noti_admin,$new_response_noti){
        //error_log('inside add_sms_contact_efb');
        //error_log($form_id);
        //error_log('==>admin_numbers');
        //error_log($admin_numbers);
        //error_log("==>new_message_noti_user");
        //error_log($recived_message_noti_user);
        //error_log("==>new_message_noti_user");
        //error_log($new_message_noti_user);
        //error_log("==>new_message_noti_admin");
        //error_log($new_message_noti_admin);
        //error_log("==>new_response_noti");
        //error_log($new_response_noti);

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_contact';
        $sql = "SELECT * FROM $table_name WHERE form_id = $form_id";
        $result = $wpdb->get_results($sql);
        //error_log(json_encode($result));
        if(count($result) == 0){
            //get id after insert
            //error_log('inside add_sms_contact_efb=>insert');
            $sql = "INSERT INTO $table_name (form_id,admin_numbers,recived_message_noti_user,new_message_noti_user,new_message_noti_admin,new_response_noti) VALUES ('$form_id','$admin_numbers','$recived_message_noti_user','$new_message_noti_user','$new_message_noti_admin','$new_response_noti')";
            $wpdb->query($sql);
            return $wpdb->insert_id;
        }else{
            //error_log('inside add_sms_contact_efb=>update');
            $sql = "UPDATE $table_name SET admin_numbers = '$admin_numbers',recived_message_noti_user = '$recived_message_noti_user',new_message_noti_user = '$new_message_noti_user',new_message_noti_admin = '$new_message_noti_admin',new_response_noti = '$new_response_noti' WHERE form_id = $form_id";
            $wpdb->query($sql);
            //get id after update
            return $result[0]->id;
        }
    }

    public function delete_sms_contact_efb($form_id){
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_sms_contact';
        $sql = "DELETE FROM $table_name WHERE form_id = $form_id";
        $wpdb->query($sql);
    }

    public function send_sms_Emsfb($POST){
        $efbFunction = get_efbFunction();
        $text = ["error403","somethingWentWrongPleaseRefresh" ,'messageSent','pleaseFillInRequiredFields','smscw'];
        $lang= $efbFunction->text_efb($text);

        // check_ajax_referer returns 1 or 2 for a valid nonce (2 = 12-24h old)
        if (check_ajax_referer('wp_rest', 'nonce', false) == false) {
            $m = $lang["error403"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);


        }
        $number = sanitize_text_field( wp_unslash( $POST['no']));
        $message = sanitize_text_field(wp_unslash( $POST['msg']));
        //if find \\n replace with \n

        $ac= get_setting_Emsfb('decoded');
        //error_log($number);
        //error_log($message);
        //error_log(json_encode($ac));
        $status = 'wpsms';
        $form_id = 0;
        if(empty($number) || empty($message)){
            $m = $lang["pleaseFillInRequiredFields"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
        }
        if($status=='null'){
            $m = $lang["smscw"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
        }


        $result = $this->send_sms_efb($number,$message,$form_id,$status);
        if($result === false){
            $m = $lang["smscw"];
            $response = ['success' => false, 'm' =>$m];
            wp_send_json_success($response, 200);
        }

        $user = wp_get_current_user();
        $row = array(
            'id' => 0,
            'number' => $number,
            'message' => $message,
            'status' => $status,
            'date' => date('Y-m-d H:i:s'),
            'form_id' => $user->display_name,
        );
        $m = $lang["messageSent"];
        $response = ['success' => true, 'm' =>$m , 'row'=>$row];
        wp_send_json_success($response, 200);


    }

}
