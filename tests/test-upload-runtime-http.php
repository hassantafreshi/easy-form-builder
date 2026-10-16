<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Real multipart upload, live-context renewal and final form save; isolated fixtures. */
require dirname(__DIR__,4).'/wp-load.php';
global $wpdb;
$fid=0;$files=array();$urls=array();$ledger='';$passed=0;
$health=get_option(\Emsfb\Request_Health::OPTION,null);
$check=static function($ok,$label)use(&$passed){if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS $label\n";};
$request=static function($route,$data,$headers=array()){
    $r=wp_remote_post(rest_url('Emsfb/v1/'.$route),array('timeout'=>30,'headers'=>array_merge(array('Origin'=>home_url(),'Content-Type'=>'application/json'),$headers),'body'=>wp_json_encode($data)));
    if(is_wp_error($r))throw new RuntimeException('HTTP failure: '.$r->get_error_code());
    return array(wp_remote_retrieve_response_code($r),json_decode(wp_remote_retrieve_body($r),true));
};
$remember=static function($response)use(&$files,&$urls){
    if(!empty($response['data']['file']['file']))$files[]=$response['data']['file']['file'];
    if(!empty($response['data']['file']['url']))$urls[]=$response['data']['file']['url'];
};
try {
    $structure=array(
        array('type'=>'form','steps'=>1,'formName'=>'Upload runtime test','captcha'=>false,'sendEmail'=>false,'smsnoti'=>'0','stateForm'=>'0','trackingCode'=>true,'thank_you'=>'msg','thank_you_message'=>array('thankYou'=>'ok','done'=>'ok')),
        array('id_'=>'1','type'=>'step','dataId'=>'1','id'=>'1','name'=>'Step','step'=>'1','amount'=>1,'visible'=>1),
        array('id_'=>'runtime_file','dataId'=>'runtime_file-id','type'=>'file','name'=>'File','step'=>'1','amount'=>2,'required'=>'0','visible'=>1,'file'=>'image','max_fsize'=>1)
    );
    $wpdb->insert($wpdb->prefix.'emsfb_form',array('form_name'=>'Isolated upload runtime HTTP test','form_type'=>'form','form_structer'=>wp_json_encode($structure),'form_email'=>'','form_created_by'=>1));
    $fid=(int)$wpdb->insert_id;$check($fid>0,'Temporary upload form created');
    [$status,$context]=$request('forms/session/prepare',array('form_id'=>$fid,'sid'=>'invalid-cached-session'));
    $check($status===200&&!empty($context['runtime_token']),'Fresh upload context obtained without cached nonce');
    $upload=static function($name,$bytes,$nonce)use($fid,$context){
        $boundary='EFB'.str_replace('-','',wp_generate_uuid4());$body='';
        foreach(array('id'=>'runtime_file','pl'=>'msg','fid'=>$fid,'sid'=>$context['sid'],'page_id'=>'0','nonce_msg'=>$nonce)as$key=>$value){$body.="--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";}
        $body.="--$boundary\r\nContent-Disposition: form-data; name=\"async-upload\"; filename=\"$name\"\r\nContent-Type: image/png\r\n\r\n".$bytes."\r\n--$boundary--\r\n";
        $r=wp_remote_post(rest_url('Emsfb/v1/forms/file/upload'),array('timeout'=>30,'headers'=>array('Origin'=>home_url(),'Content-Type'=>'multipart/form-data; boundary='.$boundary,'X-WP-Nonce'=>$nonce,'form-id'=>(string)$fid,'sid'=>$context['sid']),'body'=>$body));
        if(is_wp_error($r))throw new RuntimeException('Multipart HTTP failure: '.$r->get_error_code());
        return array(wp_remote_retrieve_response_code($r),json_decode(wp_remote_retrieve_body($r),true));
    };
    [$status,$blocked]=$upload('inert.php','EFB inert extension test',$context['nonce']);
    $remember($blocked);
    $check(empty($blocked['data']['success']),'Executable extension rejected by actual HTTP upload endpoint');
    [$status,$blocked]=$upload('fake.png','EFB inert MIME mismatch test',$context['nonce']);
    $remember($blocked);
    $check(empty($blocked['data']['success']),'Forged image MIME rejected by actual HTTP upload endpoint');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6AAAAABJRU5ErkJggg==');
    [$status,$uploaded]=$upload('valid.png',$png,$context['nonce']);
    $remember($uploaded);
    $check($status===200&&!empty($uploaded['data']['success'])&&!empty($uploaded['data']['file']['url']),'Actual safe multipart image upload succeeds');
    [$status,$renewed]=$request('forms/session/prepare',array('form_id'=>$fid,'runtime_token'=>$context['runtime_token'],'sid'=>$context['sid']));
    $check($status===200&&$renewed['sid']===$context['sid'],'Live context renewal preserves upload session');
    $id=wp_generate_uuid4();$proof=\Emsfb\Form_Runtime::verify($renewed['runtime_token'],$fid);
    $ledger='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|form|'.$id,wp_salt('nonce'));
    $data=array('id'=>(string)$fid,'name'=>'Upload runtime test','sid'=>$renewed['sid'],'page_id'=>'0','url'=>home_url('/'),'type'=>'form','valid'=>'','request_id'=>$id,'runtime_token'=>$renewed['runtime_token'],'value'=>wp_json_encode(array(array('id_'=>'runtime_file','name'=>'File','type'=>'file','value'=>'@file@','url'=>$urls[0]))));
    [$status,$saved]=$request('forms/message/add',$data,array('X-WP-Nonce'=>$renewed['nonce'],'form-id'=>(string)$fid,'sid'=>$renewed['sid']));
    $check($status===200&&!empty($saved['data']['success']),'Uploaded file remains valid when form is saved after context renewal');
    $check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid))===1,'Exactly one uploaded form entry saved');
    echo "$passed actual multipart upload/runtime checks passed.\n";
} finally {
    if($ledger)delete_option($ledger);
    if($urls)\Emsfb\Upload_Guard::release_pending_uploads($urls);
    $base=realpath(wp_upload_dir()['basedir']);
    foreach($files as$file){$real=realpath($file);if($real&&$base&&strpos($real,$base.DIRECTORY_SEPARATOR)===0)wp_delete_file($real);}
    if($fid)foreach(array('emsfb_msg_','emsfb_stts_','emsfb_form')as$table)$wpdb->delete($wpdb->prefix.$table,array($table==='emsfb_form'?'form_id':($table==='emsfb_stts_'?'fid':'form_id')=>$fid));
    if($health===null)delete_option(\Emsfb\Request_Health::OPTION);else update_option(\Emsfb\Request_Health::OPTION,$health,false);
}
