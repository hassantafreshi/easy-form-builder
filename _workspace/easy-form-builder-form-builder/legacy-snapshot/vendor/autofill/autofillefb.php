<?php


namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class autofillefb
{

    public function __construct()
    {

        $this->create_autofill_tables_efb();
        add_action('create_autofill_tables_efb', [$this, 'create_autofill_tables_efb']);
        add_action('wp_ajax_efb_get_autofill_list', array($this, 'get_autofill_list_ajax_efb'));
    }

    public function create_autofill_tables_efb()
    {
        $s = intval(get_option('emsfb_addon_AdnATF', 0));
        if ($s == 2) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_options_list';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists == $table_name) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `status_` varchar(30) NOT NULL,
            `name_` varchar(150) NOT NULL,
            `value_` JSON NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        $wpdb->query("ALTER TABLE $table_name AUTO_INCREMENT = 1;");
        if ($result) {
            update_option('emsfb_addon_AdnATF', 2);
        }
    }


    public function get_autofill_list_efb($val = 'autofill', $state = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_options_list';
        if ($state == 'name') {
            $sql = $wpdb->prepare("SELECT id, name_ FROM $table_name WHERE status_ = %s ORDER BY id DESC", $val);
        } else if ($state == 'id') {
            $sql = $wpdb->prepare("SELECT name_, value_ FROM $table_name WHERE status_ = %s AND id = %d ORDER BY id DESC", 'autofill', $val);
        }
        $r = $wpdb->get_results($sql);
        return $r;
    }

    public function add_autofill_efb($name, $value)
    {
        global $wpdb;
        $name = sanitize_text_field($name);
        $value = json_encode($value);
        $value_ = sanitize_text_field($value);
        $table_name = $wpdb->prefix . 'emsfb_options_list';
        $wpdb->insert($table_name, array('name_' => $name, 'value_' => $value_, 'status_' => 'autofill'), array('%s', '%s', '%s'));
        return $wpdb->insert_id;
    }

    public function delete_autofill_efb($id)
    {
        global $wpdb;
        $id = absint($id);
        $table_name = $wpdb->prefix . 'emsfb_options_list';
        return $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }

    public function update_autofill_efb($id, $name, $value)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_options_list';
        $name = sanitize_text_field($name);
        $value = json_encode($value);
        $value_ = sanitize_text_field($value);
        $id = absint($id);
        return $wpdb->update($table_name, array('name_' => $name, 'value_' => $value_), array('id' => $id), array('%s', '%s'), array('%d'));
    }


    public function get_autofill_api_efb($data_POST)
    {
        global $wpdb;
        $text_ = [
            'notFound',
            'sxnlex',
            'pleaseEnterVaildValue',
            'errorSomthingWrong',
            'nAllowedUseHtml'
        ];
        $efbFunction = get_efbFunction();
        $lanText = $efbFunction->text_efb($text_);
        $fid = sanitize_text_field($data_POST['fid']);
        $data = sanitize_text_field($data_POST['data']);


        $id = sanitize_text_field($data_POST['id']);
        if (empty($data)) {
            $response = ['success' => false, 'm' => $lanText['pleaseEnterVaildValue']];
            wp_send_json_success($response, 200);
        }
        if (empty($id)) {
            $response = ['success' => false, 'm' => $lanText['errorSomthingWrong']];
            wp_send_json_success($response, 200);
        }
        $data = json_decode(str_replace("\\", "", $data), true);
        $id = intval($fid);
        $table_name = $wpdb->prefix . "emsfb_msg_";
        $table_form = $wpdb->prefix . "emsfb_form";

        $form = $wpdb->get_results("SELECT form_structer FROM `$table_form` WHERE form_id = '$id'");
        // error_logorm structure retrieved: ' . print_r($form, true));
        $form = json_decode(str_replace('\\', '', $form[0]->form_structer), true);
        $type_autofill = 'dataset';
        if ($form == null) {
            $response = ['success' => false, 'm' => $lanText['notFound']];
            wp_send_json_success($response, 200);
        } else if (isset($form[0]['auto_fill']) && intval($form[0]['auto_fill']) === 1 && isset($form[0]['autofill_id']) && intval($form[0]['autofill_id']) === 0) {
            $type_autofill = 'previous';
        }

        $count_data = count($data);
        $message = null;
        $message_autofilled = [];
        // error_logype of autofill: ' . $type_autofill);
        if ($type_autofill == 'dataset') {
            $table_name = $wpdb->prefix . 'emsfb_options_list';
            $first_row_form = $form[0];


            $condition_query = [];
            foreach ($data as $d) {
                foreach ($first_row_form['autofill_conditions'] as $key => $value) {


                    if ($d['id'] == $value['id_']) {
                        $val_d = preg_replace('/\s+/', '', $d['value']);
                        array_push($condition_query, ["key" => $value['source'], "value" => $val_d, "id_" => $value['id_']]);
                    }
                }
            }

            if (empty($condition_query)) {
                $response = ['success' => false, 'm' => $lanText['notFound']];
                wp_send_json_success($response, 200);
            }

            // error_logondition query: ' . print_r($condition_query, true));
            $r_dataset = null;
            $id = intval($form[0]['autofill_id']);
            $value = $wpdb->get_results("SELECT value_ FROM `$table_name` WHERE status_ = 'autofill' AND id = '$id'");
            if ($value == null) {
                $response = ['success' => false, 'm' => $lanText['notFound']];
                wp_send_json_success($response, 200);
            }

            $dataset = json_decode(str_replace('\\', '', $value[0]->value_), true);
            foreach ($dataset as $d) {
                $in_count_state = 0;
                foreach ($condition_query as $cq) {
                    if (!isset($d[$cq['key']])) continue;
                    $val_d = preg_replace('/\s+/', '', $d[$cq['key']]);
                    $val_cq = preg_replace('/\s+/', '', $cq['value']);


                    if ($val_d == $val_cq) {
                        $in_count_state++;
                        $r_dataset[] = $d;
                        if ($in_count_state == $count_data) {
                            break 2;
                        }
                    }
                }
            }

            if ($r_dataset == null) {
                $response = ['success' => false, 'm' => $lanText['notFound']];
                wp_send_json_success($response, 200);
            }
            // error_logataset results: ' . print_r($r_dataset, true));
            foreach ($form as $val) {

                foreach ($r_dataset as $key => $r) {
                    if (!isset($val['id_']) || !isset($val['autofill_condition_source'])) {
                        continue 2;
                    }
                    $type = $val['type'];
                    $val['value'] = $r[$val['autofill_condition_source']];
                    $val['id_ob'] = $val['id_'] . '_';
                    $val['session'] = 'dataset_' . $id;

                    $message[] = $val;
                    if (strpos($type, 'checkbox') === false) {
                        break;
                    }
                }
            }
        } else if ($type_autofill == 'previous') {
            $value = $wpdb->get_results("SELECT content FROM `$table_name` WHERE form_id = '$id'");
            if ($value == null) {
                $response = ['success' => false, 'm' => $lanText['notFound']];
                wp_send_json_success($response, 200);
            }

            foreach ($value as $val) {
                $msg_obj = json_decode(str_replace('\\', '', $val->content), true);
                $in_count_state = 0;

                foreach ($msg_obj as $msg) {
                    if (!isset($msg['id_'])) continue;

                    foreach ($data as $d) {
                        if ($msg['id_'] == $d['id']) {
                            $val_msg = preg_replace('/\s+/', '', $msg['value']);
                            $val_d = preg_replace('/\s+/', '', $d['value']);



                            if ($val_msg == $val_d) {
                                $in_count_state++;
                                $message_autofilled[] = $msg;
                                if ($in_count_state == $count_data) {
                                    $message = $msg_obj;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                if ($message) break;
            }
        }


        if (empty($message)) {
            $response = ['success' => false, 'm' => $lanText['notFound']];
            wp_send_json_success($response, 200);
        }



        foreach ($form as $val) {
            if (isset($val['id_']) && isset($val['auto_fill']) && ($val['auto_fill'] == "1" || $val['auto_fill'] == 1)) {


                foreach ($message as $msg) {
                    if (!isset($msg['id_'])) continue;
                    $type = $val['type'];
                    if ($msg['id_'] == $val['id_']) {
                        $message_autofilled[] = $msg;
                        if (strpos($type, 'checkbox') === false) {
                            break;
                        }
                    }
                }
            }
        }

        $response = ['success' => true, 'm' => 'done', 'data' => $message_autofilled];
        wp_send_json_success($response, 200);
    }




    public function get_autofill_list_ajax_efb()
    {

        $nonce = sanitize_text_field($_POST['nonce']);
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            die('Security check');
        }
        // بررسی دسترسی / Check permission
        $efbFunction = get_efbFunction();
        $current_user_can = $efbFunction->user_permission_efb_admin_dashboard();
        if (!$current_user_can) {
            // استفاده از سیستم phrases / Use phrases system
            $autofill_lang = $efbFunction->text_efb(1, 'Autofill');
            $permission_msg = isset($autofill_lang['permission_denied']) ? $autofill_lang['permission_denied'] : __('Permission denied', 'easy-form-builder');
            wp_send_json(array('status' => 'error', 'message' => $permission_msg));
            die();
        }
        $state = sanitize_text_field($_POST['state']);
        $value = sanitize_text_field($_POST['value']);
        if ($state == 'name') {
            $data = $this->get_autofill_list_efb('autofill', 'name');
        } else if ($state == 'id') {
            $data = $this->get_autofill_list_efb($value, 'id');
        }

        $response = array('status' => 'success', 'data' => $data);
        wp_send_json($response);
        //get the data form table  emsfb_options_list where status_ = 1 name = autofill
    }



}
