<?php
namespace Emsfb;
defined('ABSPATH') || exit;

/** Live per-tab context; never issued into cacheable HTML. */
class Form_Runtime {
    public static function register() {
        add_action('emsfb_operation_cleanup',array(__CLASS__,'cleanup'));
        add_action('init',static function () {
            if (!wp_next_scheduled('emsfb_operation_cleanup')) wp_schedule_event(time()+HOUR_IN_SECONDS,'hourly','emsfb_operation_cleanup');
        });
        register_deactivation_hook(EMSFB_PLUGIN_FILE,static function () { wp_clear_scheduled_hook('emsfb_operation_cleanup'); });
    }
    private static function fingerprint($data) {
        unset($data['runtime_token'],$data['request_id'],$data['sid'],$data['valid'],$data['nonce'],$data['response_token']);
        ksort($data);
        return hash('sha256',wp_json_encode($data));
    }

    public static function replay($kind,$fid,$data) {
        if (empty($data['request_id']) && empty($data['runtime_token'])) return false;
        $status=self::status($kind,$fid,$data);
        if (is_wp_error($status)) return $status;
        if ($status->get_data()['state']==='not_found') return false;
        $proof=self::verify($data['runtime_token'],$fid);
        $key='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|'.$kind.'|'.$data['request_id'],wp_salt('nonce'));
        $old=get_option($key);
        if (!is_array($old) || !hash_equals($old['hash'],self::fingerprint($data))) return new \WP_Error('efb_operation_conflict',__('This request changed. Please review it before continuing.', 'easy-form-builder'),array('status'=>409));
        if ($status->get_data()['state']==='done') return new \WP_REST_Response(array('success'=>true,'data'=>$status->get_data()['result']),200);
        return new \WP_Error('efb_operation_pending',__('The result of the previous request is not yet confirmed. Do not submit it again.', 'easy-form-builder'),array('status'=>409));
    }
    public static function token($fid, $sid, $owner = '') {
        $owner = $owner ?: gmdate('Ymd') . '-' . wp_generate_uuid4();
        $data = array('fid'=>(int)$fid, 'sid'=>(string)$sid, 'uid'=>get_current_user_id(), 'auth'=>hash_hmac('sha256',wp_get_session_token(),wp_salt('auth')), 'owner'=>$owner, 'exp'=>strtotime(substr($owner,0,8).' UTC')+7*DAY_IN_SECONDS);
        $body = rtrim(strtr(base64_encode(wp_json_encode($data)), '+/', '-_'), '=');
        return $body . '.' . hash_hmac('sha256', $body, wp_salt('nonce'));
    }

    public static function verify($token, $fid) {
        if (!is_string($token) || strlen($token)>2048) return false;
        $parts = explode('.', $token);
        if (count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],wp_salt('nonce')),$parts[1])) return false;
        $data = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!is_array($data) || !isset($data['uid'],$data['fid'],$data['sid'],$data['owner'],$data['exp']) || (int)$data['fid']!==(int)$fid || (int)$data['exp']<time()) return false;
        if ((int)$data['uid']!==get_current_user_id()) return new \WP_Error('efb_identity_changed', __('Your login status changed. Reopen the form before continuing.', 'easy-form-builder'), array('status'=>409));
        if ((int)$data['uid']>0 && (!is_string($data['auth'] ?? null) || !hash_equals($data['auth'],hash_hmac('sha256',wp_get_session_token(),wp_salt('auth'))))) return new \WP_Error('efb_identity_changed',__('Your login session changed. Reopen the form before continuing.', 'easy-form-builder'),array('status'=>409));
        return $data;
    }

    /** Acquire before a write. An incomplete write is never automatically replayed. */
    public static function begin($kind, $fid, $data) {
        if (empty($data['request_id']) && empty($data['runtime_token'])) return ''; // Older cached clients.
        $proof = self::verify($data['runtime_token'] ?? '', $fid);
        if (is_wp_error($proof)) return $proof;
        if (!$proof || !is_string($data['request_id'] ?? null) || !preg_match('/^[a-f0-9-]{36}$/D', $data['request_id'])) return new \WP_Error('efb_operation_invalid', __('The request could not be verified.', 'easy-form-builder'), array('status'=>403));
        $hash = self::fingerprint($data);
        $key = 'efb_op_' . substr($proof['owner'],0,8) . '_' . hash_hmac('sha256',$proof['owner'].'|'.$kind.'|'.$data['request_id'],wp_salt('nonce'));
        $row = array('hash'=>$hash,'state'=>'processing','time'=>time(),'result'=>null);
        global $wpdb;
        // add_option() uses ON DUPLICATE KEY UPDATE and cannot be a write lock.
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,%s)",$key,maybe_serialize($row),'off'));
        wp_cache_delete($key,'options'); wp_cache_delete('notoptions','options');
        if ($inserted===1) return $key;
        if ($inserted===false) return new \WP_Error('efb_operation_storage_failed',__('The request could not be safely recorded. Your input has been kept.', 'easy-form-builder'),array('status'=>503));
        $old = get_option($key);
        if (!is_array($old) || !isset($old['hash']) || !hash_equals($old['hash'],$hash)) return new \WP_Error('efb_operation_conflict', __('This request changed. Please review it before continuing.', 'easy-form-builder'), array('status'=>409));
        if (($old['state'] ?? '')==='done') return new \WP_REST_Response(array('success'=>true,'data'=>$old['result']),200);
        return new \WP_Error('efb_operation_pending', __('The result of the previous request is not yet confirmed. Do not submit it again.', 'easy-form-builder'), array('status'=>409));
    }

    public static function status($kind,$fid,$data) {
        $proof=self::verify($data['runtime_token'] ?? '',$fid);
        if (is_wp_error($proof)) return $proof;
        if (!$proof || !in_array($kind,array('form','reply','admin_reply'),true) || !is_string($data['request_id'] ?? null) || !preg_match('/^[a-f0-9-]{36}$/D',$data['request_id'])) return new \WP_Error('efb_operation_invalid','Invalid operation.',array('status'=>403));
        $key='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|'.$kind.'|'.$data['request_id'],wp_salt('nonce'));
        $row=get_option($key);
        return new \WP_REST_Response(array('state'=>is_array($row) ? $row['state'] : 'not_found','result'=>is_array($row) && ($row['state'] ?? '')==='done' ? $row['result'] : null),200);
    }

    public static function finish($key, $result) {
        if (!$key) return;
        $row = get_option($key);
        if (!is_array($row)) return;
        $row['state']='done'; $row['result']=$result;
        update_option($key,$row,false);
    }

    /** Only after the actual insert definitively returned false, never on timeout. */
    public static function abort($key) {
        if (is_string($key) && strpos($key,'efb_op_')===0) delete_option($key);
    }

    public static function cleanup() {
        global $wpdb;
        // Only our dated, hashed operation records. Retain at least seven days.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s LIMIT 500", $wpdb->esc_like('efb_op_').'%', 'efb_op_'.gmdate('Ymd',time()-8*DAY_IN_SECONDS).'_'));
    }
}
