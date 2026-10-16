<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Real WordPress ledger and signed-context integration; creates no submissions. */
require_once dirname(__DIR__,4).'/wp-load.php';
use Emsfb\Form_Runtime;
if (($argv[1] ?? '')==='--claim') {
    wp_set_current_user(0);
    $claim=Form_Runtime::begin('form',42,array('runtime_token'=>$argv[2],'request_id'=>$argv[3],'value'=>'parallel'));
    echo is_string($claim) ? 'CLAIM_OWNER' : 'CLAIM_BLOCKED';
    exit;
}
$original_user=get_current_user_id(); $keys=array(); $passed=0;
$original_server=$_SERVER; $original_cookies=$_COOKIE; $sessions=array();
function runtime_check($ok,$label) { global $passed; if (!$ok) throw new Exception($label); $passed++; }
try {
    wp_set_current_user(0);
    $token=Form_Runtime::token(42,'sid-test');
    $proof=Form_Runtime::verify($token,42);
    runtime_check(is_array($proof) && $proof['exp']>time(),'valid dated token');
    runtime_check(Form_Runtime::verify($token.'x',42)===false,'tampering rejected');
    runtime_check(Form_Runtime::verify($token,43)===false,'form isolation');
    runtime_check(Form_Runtime::verify(array(),42)===false,'malformed token rejected');
    runtime_check(Form_Runtime::verify(Form_Runtime::token(42,'sid',gmdate('Ymd',time()-9*DAY_IN_SECONDS).'-'.wp_generate_uuid4()),42)===false,'expired root rejected');
    wp_set_current_user(1);
    runtime_check(is_wp_error(Form_Runtime::verify($token,42)),'identity change rejected');
    $_COOKIE[LOGGED_IN_COOKIE]='test|'.(time()+3600).'|session-a|signature';
    $login_token=Form_Runtime::token(42,'sid');
    runtime_check(is_array(Form_Runtime::verify($login_token,42)),'authenticated context matches current login session');
    $_COOKIE[LOGGED_IN_COOKIE]='test|'.(time()+3600).'|session-b|signature';
    runtime_check(is_wp_error(Form_Runtime::verify($login_token,42)),'different login session for same user rejected');
    $_COOKIE=$original_cookies;
    wp_set_current_user(0);
    $data=array('runtime_token'=>$token,'request_id'=>wp_generate_uuid4(),'value'=>'private test value','sid'=>'sid-test');
    runtime_check(Form_Runtime::begin('form',42,array())==='','legacy compatibility');
    runtime_check(Form_Runtime::status('form',42,$data)->get_data()['state']==='not_found','unknown operation not claimed as failed');
    $key=Form_Runtime::begin('form',42,$data); $keys[]=$key;
    runtime_check(is_string($key) && strpos($key,'efb_op_')===0,'first writer owns ledger');
    runtime_check(is_wp_error(Form_Runtime::begin('form',42,$data)),'second writer blocked while processing');
    runtime_check(Form_Runtime::status('form',42,$data)->get_data()['state']==='processing','ambiguous operation stays pending');
    $changed=$data; $changed['value']='different';
    runtime_check(Form_Runtime::begin('form',42,$changed)->get_error_code()==='efb_operation_conflict','changed payload cannot reuse id');
    Form_Runtime::finish($key,array('success'=>true,'track'=>'TEST-TRACK'));
    runtime_check(Form_Runtime::begin('form',42,$data) instanceof WP_REST_Response,'finished write replayed without new claim');
    runtime_check(Form_Runtime::replay('form',42,$data)->get_data()['data']['track']==='TEST-TRACK','lost response recoverable');
    runtime_check(Form_Runtime::replay('form',42,$changed)->get_error_code()==='efb_operation_conflict','replay checks payload');
    $renewed=$data; $renewed['runtime_token']=Form_Runtime::token(42,'new-session',$proof['owner']); $renewed['sid']='new-session';
    runtime_check(Form_Runtime::replay('form',42,$renewed) instanceof WP_REST_Response,'session renewal keeps same operation owner');
    $other=$data; $other['runtime_token']=Form_Runtime::token(42,'other-session');
    runtime_check(Form_Runtime::status('form',42,$other)->get_data()['state']==='not_found','other visitor cannot read result');
    runtime_check(Form_Runtime::status('payment',42,$data) instanceof WP_Error,'payment never replayed by generic ledger');
    global $wpdb;
    runtime_check(!in_array($wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name=%s",$key)),array('yes','on','auto-on','auto'),true),'ledger does not autoload');
    runtime_check(strpos($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key)),'private test value')===false,'ledger stores hash instead of form values');
    Form_Runtime::abort($key);
    runtime_check(Form_Runtime::status('form',42,$data)->get_data()['state']==='not_found','definite insert failure can release operation');
    $_COOKIE=array(); $_SERVER['HTTP_ORIGIN']=home_url(); $_SERVER['HTTP_SEC_FETCH_SITE']='same-origin'; $_SERVER['REMOTE_ADDR']='192.0.2.239';
    $public=new Emsfb\_Public();
    $request=new WP_REST_Request('POST','/Emsfb/v1/forms/session/prepare'); $request->set_param('form_id',0); $request->set_param('sid','old-shared-cached-sid');
    $first=$public->prepare_form_session_api($request)->get_data(); $sessions[]=$first['sid'];
    $second=$public->prepare_form_session_api($request)->get_data(); $sessions[]=$second['sid'];
    runtime_check($first['sid']!==$second['sid'] && $first['sid']!=='old-shared-cached-sid','cached HTML never shares live context across visits');
    $request->set_param('runtime_token',$first['runtime_token']);
    $renew=$public->prepare_form_session_api($request)->get_data();
    runtime_check($first['sid']===$renew['sid'],'open session renewal preserves uploads and sid');
    $request->set_param('runtime_token',$first['runtime_token'].'x');
    runtime_check($public->prepare_form_session_api($request) instanceof WP_Error,'invalid context cannot silently switch owners');
    if (function_exists('proc_open')) {
        $parallel_id=wp_generate_uuid4(); $jobs=array();
        $parallel_key='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|form|'.$parallel_id,wp_salt('nonce')); $keys[]=$parallel_key;
        for($i=0;$i<2;$i++) {
            $process=proc_open(array(PHP_BINARY,__FILE__,'--claim',$token,$parallel_id),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
            if (!is_resource($process)) throw new Exception('Cannot start concurrent ledger test');
            fclose($pipes[0]); $jobs[]=array($process,$pipes);
        }
        $owners=0; $blocked=0;
        foreach($jobs as $job) {
            $out=stream_get_contents($job[1][1]); $err=stream_get_contents($job[1][2]); fclose($job[1][1]); fclose($job[1][2]);
            runtime_check(proc_close($job[0])===0,'concurrent worker completes');
            $owners+=strpos($out,'CLAIM_OWNER')!==false; $blocked+=strpos($out,'CLAIM_BLOCKED')!==false;
        }
        runtime_check($owners===1 && $blocked===1,'exactly one concurrent worker acquires write');
    }
    echo "PASS: $passed signed context and durable operation checks\n";
} finally {
    foreach ($keys as $key) if (is_string($key)) delete_option($key);
    foreach ($sessions as $sid) $wpdb->delete($wpdb->prefix.'emsfb_stts_',array('sid'=>$sid));
    delete_transient('efb_nrl_'.md5('192.0.2.239|'.gmdate('YmdHi')));
    $_SERVER=$original_server; $_COOKIE=$original_cookies;
    wp_set_current_user($original_user);
}
