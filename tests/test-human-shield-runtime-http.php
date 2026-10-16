<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Actual HTTP with Human Shield enabled; restores options and removes all fixture entries. */
require dirname(__DIR__,4).'/wp-load.php';
global $wpdb;
$fid=0; $ledger=''; $passed=0;
$settings_option=get_option('emsfb_settings',null);
$settings_transient=get_transient('emsfb_settings_transient');
$shield_option=get_option('emsfb_human_shield_settings',null);
$health_option=get_option(\Emsfb\Request_Health::OPTION,null);
$restore=static function()use(&$fid,&$ledger,$wpdb,$settings_option,$settings_transient,$shield_option,$health_option){
    if($ledger)delete_option($ledger);
    if($fid){
        foreach(array('emsfb_msg_','emsfb_stts_','emsfb_form')as$table)$wpdb->delete($wpdb->prefix.$table,array($table==='emsfb_form'?'form_id':($table==='emsfb_stts_'?'fid':'form_id')=>$fid));
        foreach(array('events','challenges','rate_limits')as$table)$wpdb->delete($wpdb->prefix.'emsfb_shield_'.$table,array('form_id'=>$fid));
    }
    foreach(array('emsfb_settings'=>$settings_option,'emsfb_human_shield_settings'=>$shield_option,\Emsfb\Request_Health::OPTION=>$health_option)as$name=>$value){if($value===null)delete_option($name);else update_option($name,$value,false);}
    if($settings_transient===false)delete_transient('emsfb_settings_transient');else set_transient('emsfb_settings_transient',$settings_transient,1800);
    foreach(array('decoded','pub','raw')as$mode)wp_cache_delete('settings:'.$mode,'emsfb');
    get_setting_Emsfb('_clear_cache');
};
register_shutdown_function($restore);
$check=static function($ok,$label)use(&$passed){if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS $label\n";};
$hs_request=static function($route,$data,$headers=array(),$namespace='Emsfb/v1/'){
    $r=wp_remote_post(rest_url($namespace.$route),array('timeout'=>20,'headers'=>array_merge(array('Content-Type'=>'application/json','Origin'=>home_url(),'User-Agent'=>'EFB-isolated-HS-HTTP'),$headers),'body'=>wp_json_encode($data)));
    if(is_wp_error($r))throw new RuntimeException('HTTP request failed: '.$r->get_error_code());
    return array(wp_remote_retrieve_response_code($r),json_decode(wp_remote_retrieve_body($r),true),wp_remote_retrieve_header($r,'cache-control'));
};
try {
    $settings=clone get_setting_Emsfb('decoded'); $settings->AdnHSH=1;
    $raw=wp_json_encode($settings);
    update_option('emsfb_settings',$raw,false);set_transient('emsfb_settings_transient',$raw,1800);
    foreach(array('decoded','pub','raw')as$mode)wp_cache_delete('settings:'.$mode,'emsfb');
    get_setting_Emsfb('_clear_cache');
    $active=is_array($shield_option)?$shield_option:array();
    $active=array_merge($active,array('enabled'=>1,'mode'=>'strict','ip_allowlist'=>'','ip_blocklist'=>'','submit_ip_per_minute'=>100,'challenge_ip_per_minute'=>100,'attest_ip_per_minute'=>100,'trusted_proxy_headers'=>0));
    update_option('emsfb_human_shield_settings',$active,false);
    $structure=array(array('type'=>'form','steps'=>1,'formName'=>'HS runtime test','captcha'=>false,'sendEmail'=>false,'smsnoti'=>'0','stateForm'=>'0','trackingCode'=>true,'thank_you'=>'msg','thank_you_message'=>array('thankYou'=>'ok','done'=>'ok')),array('id_'=>'1','type'=>'step','dataId'=>'1','id'=>'1','name'=>'Step','step'=>'1','amount'=>1,'visible'=>1),array('id_'=>'hs_text','dataId'=>'hs_text-id','type'=>'text','name'=>'Text','step'=>'1','amount'=>2,'required'=>'0','visible'=>1,'minlength'=>0,'maxlength'=>1000));
    $wpdb->insert($wpdb->prefix.'emsfb_form',array('form_name'=>'Isolated HS HTTP test','form_type'=>'form','form_structer'=>wp_json_encode($structure),'form_email'=>'','form_created_by'=>1));
    $fid=(int)$wpdb->insert_id;
    if(!$fid)throw new RuntimeException('Fixture creation failed');
    [$status,$context,$cache]=$hs_request('forms/session/prepare',array('form_id'=>$fid,'sid'=>'stale-html-session'));
    $check($status===200&&!empty($context['sid'])&&strpos($cache,'no-store')!==false,'Live context prepared under active Human Shield with no cached nonce');
    [$status,$invalid,$cache]=$hs_request('challenge',array('formId'=>$fid,'sid'=>'invalid-session','route'=>'/Emsfb/v1/forms/message/add'),array(),'EmsfbShield/v1/');
    $check($status===200&&($invalid['code']??'')==='session_invalid'&&strpos($cache,'no-store')!==false,'Actual challenge endpoint rejects forged session and is not cached');
    $data=array('id'=>(string)$fid,'sid'=>$context['sid'],'name'=>'HS test','page_id'=>'0','url'=>home_url('/'),'type'=>'form','valid'=>'','value'=>wp_json_encode(array(array('id_'=>'hs_text','name'=>'Text','type'=>'text','value'=>'Human Shield HTTP test'))));
    $headers=array('X-WP-Nonce'=>$context['nonce'],'form-id'=>(string)$fid,'sid'=>$context['sid']);
    [$status,$missing]=$hs_request('forms/message/add',$data,$headers);
    $check($status===403&&($missing['data']['code']??'')==='efb_human_shield_blocked','Missing security token blocks the actual write endpoint');
    $metrics=array('durationMs'=>20000,'firstInteractionDelayMs'=>800,'focusCount'=>5,'inputCount'=>6,'keyCount'=>45,'pointerMoveCount'=>30,'pointerDistance'=>900,'touchCount'=>0,'clickCount'=>3,'scrollCount'=>2,'pasteCount'=>0,'fieldsTouched'=>4,'fieldCount'=>4,'formFieldCount'=>4,'visibilityChanges'=>0,'touchCapable'=>false,'webdriver'=>false,'honeypotFilled'=>false);
    $mint=static function($metrics)use($hs_request,$fid,$context){
        $route='/Emsfb/v1/forms/message/add';
        [$status,$challenge]=$hs_request('challenge',array('formId'=>$fid,'sid'=>$context['sid'],'route'=>$route),array(),'EmsfbShield/v1/');
        if(empty($challenge['challengeId']))throw new RuntimeException('Real challenge creation failed');
        [$status,$attest]=$hs_request('attest',array('challengeId'=>$challenge['challengeId'],'formId'=>$fid,'sid'=>$context['sid'],'route'=>$route,'metrics'=>$metrics),array(),'EmsfbShield/v1/');
        if(empty($attest['token']))throw new RuntimeException('Real attestation failed');
        return $attest['token'];
    };
    $bad=$metrics;$bad['honeypotFilled']=true;
    $headers['X-EFB-Human-Token']=$mint($bad);
    [$status,$blocked]=$hs_request('forms/message/add',$data,$headers);
    $check($status===403&&($blocked['data']['code']??'')==='efb_human_shield_blocked','Signed honeypot failure blocks submission');
    $check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid))===0,'Blocked attempts create no entries');
    $data['runtime_token']=$context['runtime_token'];$data['request_id']=wp_generate_uuid4();
    $proof=\Emsfb\Form_Runtime::verify($context['runtime_token'],$fid);
    $ledger='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|form|'.$data['request_id'],wp_salt('nonce'));
    $headers['X-EFB-Human-Token']=$mint($metrics);
    [$status,$saved]=$hs_request('forms/message/add',$data,$headers);
    $check($status===200&&!empty($saved['data']['success']),'Real protected HTTP form submission succeeds with live context and valid attestation');
    [$status,$replay]=$hs_request('forms/message/add',$data,$headers);
    $check($status===403&&($replay['data']['code']??'')==='efb_human_shield_blocked','Used attestation cannot be replayed');
    [$status,$result]=$hs_request('forms/operation/status',array('kind'=>'form','form_id'=>$fid,'runtime_token'=>$context['runtime_token'],'request_id'=>$data['request_id']));
    $check($status===200&&($result['state']??'')==='done'&&($result['result']['track']??'')===$saved['data']['track'],'Saved result recovered without bypassing Human Shield or writing again');
    $check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid))===1,'No duplicate protected submission');
    echo "$passed actual Human Shield HTTP checks passed.\n";
} finally { $restore(); }
