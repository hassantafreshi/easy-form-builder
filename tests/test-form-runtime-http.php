<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Actual local REST requests and entries; removes every test row in finally. */
require_once dirname(__DIR__,4).'/wp-load.php';
global $wpdb;
$fid=0; $sid=''; $key=''; $passed=0;
$health=get_option(Emsfb\Request_Health::OPTION,null);
function http_runtime_check($ok,$label) { global $passed; if (!$ok) throw new Exception($label); $passed++; }
function runtime_post($route,$data,$nonce='') {
    $headers=array('Content-Type'=>'application/json','Origin'=>home_url());
    if ($nonce) $headers['X-WP-Nonce']=$nonce;
    $r=wp_remote_post(rest_url('Emsfb/v1/'.$route),array('timeout'=>15,'headers'=>$headers,'body'=>wp_json_encode($data)));
    if (is_wp_error($r)) throw new Exception($r->get_error_message());
    return array(wp_remote_retrieve_response_code($r),json_decode(wp_remote_retrieve_body($r),true));
}
try {
    $structure=array(
        array('type'=>'form','steps'=>1,'formName'=>'Runtime test','captcha'=>false,'sendEmail'=>false,'smsnoti'=>'0','stateForm'=>'0','trackingCode'=>true,'thank_you'=>'msg','thank_you_message'=>array('thankYou'=>'ok','done'=>'ok')),
        array('id_'=>'1','type'=>'step','dataId'=>'1','id'=>'1','name'=>'Step','step'=>'1','amount'=>1,'visible'=>1),
        array('id_'=>'runtime_text','dataId'=>'runtime_text-id','type'=>'text','name'=>'Text','step'=>'1','amount'=>2,'required'=>'0','visible'=>1,'minlength'=>0,'maxlength'=>1000)
    );
    $wpdb->insert($wpdb->prefix.'emsfb_form',array('form_name'=>'Isolated runtime HTTP test','form_type'=>'form','form_structer'=>wp_json_encode($structure),'form_email'=>'','form_created_by'=>1));
    $fid=(int)$wpdb->insert_id; http_runtime_check($fid>0,'fixture stored');
    [$code,$context]=runtime_post('forms/session/prepare',array('form_id'=>$fid,'sid'=>'cached-invalid'));
    http_runtime_check($code===200 && !empty($context['runtime_token']),'uncached bootstrap reachable with no old nonce');
    $sid=$context['sid']; $proof=Emsfb\Form_Runtime::verify($context['runtime_token'],$fid);
    $id=wp_generate_uuid4();
    $key='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|form|'.$id,wp_salt('nonce'));
    $data=array('id'=>(string)$fid,'name'=>'Runtime test','sid'=>$sid,'page_id'=>'0','url'=>home_url('/'),'type'=>'form','valid'=>'','request_id'=>$id,'runtime_token'=>$context['runtime_token'],'value'=>wp_json_encode(array(array('id_'=>'runtime_text','name'=>'Text','type'=>'text','value'=>'test value'))));
    [$code,$first]=runtime_post('forms/message/add',$data,$context['nonce']);
    http_runtime_check($code===200 && !empty($first['data']['success']),'actual form saved');
    [$code,$second]=runtime_post('forms/message/add',$data,$context['nonce']);
    http_runtime_check($code===200 && ($second['data']['track']??null)===($first['data']['track']??null),'repeat returns same saved response');
    http_runtime_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid))===1,'repeat created no duplicate entry');
    $changed=$data; $changed['value']='changed';
    [$code,$rejected]=runtime_post('forms/message/add',$changed,$context['nonce']);
    http_runtime_check($code===409 && $rejected['data']['code']==='efb_operation_conflict','same id cannot save changed body');
    [$code,$status]=runtime_post('forms/operation/status',array('form_id'=>$fid,'kind'=>'form','request_id'=>$id,'runtime_token'=>$context['runtime_token']));
    http_runtime_check($code===200 && $status['state']==='done' && $status['result']['track']===$first['data']['track'],'lost response can be recovered through read-only status');
    echo "PASS: $passed real REST submission/replay checks\n";
} finally {
    if ($key) delete_option($key);
    if ($fid) { $wpdb->delete($wpdb->prefix.'emsfb_msg_',array('form_id'=>$fid)); $wpdb->delete($wpdb->prefix.'emsfb_stts_',array('fid'=>$fid)); $wpdb->delete($wpdb->prefix.'emsfb_form',array('form_id'=>$fid)); }
    if ($health===null) delete_option(Emsfb\Request_Health::OPTION); else update_option(Emsfb\Request_Health::OPTION,$health,false);
}
