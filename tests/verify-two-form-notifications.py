"""Verify real EFB form templates with synthetic data, only authorized emails."""
import getpass
from pathlib import Path
import paramiko
code=r'''<?php
define('DISABLE_WP_CRON',true);
define('WP_USE_THEMES',false);
require '/var/www/vhosts/ong-grandcoeur.org/httpdocs/public/wordpress/wp-load.php';
// JSON_HELPERS
$logs=get_option('efb_email_log',[]);
if(!is_array($logs)||count($logs)>196) throw new RuntimeException('Avoid trimming existing EFB logs');
$allowed=['hi@whitestudio.team','hasan.tafreshi@gmail.com'];
$calls=[];
add_filter('wp_mail',function($args)use($allowed,&$calls){
    $to=is_array($args['to'])?$args['to']:[$args['to']];
    foreach($to as $email) if(!in_array($email,$allowed,true)) throw new RuntimeException('Unauthorized diagnostic recipient');
    $calls[]=['to'=>$to,'body_bytes'=>strlen($args['message'])];return $args;
},9999);
global $wpdb;
$class=new ReflectionClass('Emsfb\\_Public');
$public=$class->newInstanceWithoutConstructor();
$public->efbFunction=get_efbFunction();
$public->setting=get_setting_Emsfb('decoded');
$public->lanText=$public->efbFunction->text_efb(['msgdml','thankFillForm','trackNo','vmgs','WeRecivedUrM','thankRegistering','welcome','thankSubscribing','thankDonePoll','newMessageReceived','youRecivedNewMessage']);
$run='EFB-SMS-FIX-'.gmdate('Ymd-His');
foreach([2,3] as $id){
    $public->id=$id;
    $raw=$wpdb->get_var($wpdb->prepare("SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id=%d",$id));
    $form=emsfb_json_decode_efb($raw,true);
    if(!is_array($form)||($form[0]['smsnoti']??'')!='1'||($form[0]['email_noti_type']??'')!=='msg') throw new RuntimeException('Unexpected live form settings');
    $rows=[['type'=>'text','name'=>'Diagnostic','value'=>'Authorized synthetic verification; no customer data.'],['type'=>'text','name'=>'Run','value'=>$run.'-form-'.$id]];
    $status=$public->email_status_efb($form,$rows,$run.'-'.$id);
    if($status['type']!=='message_link'||strlen($status['content'])<50)throw new RuntimeException('Form email rendering failed');
    $public->send_email_Emsfb_([[$allowed[0]],[$allowed[1]]],$run.'-'.$id,false,['newMessage','notiToUserFormFilled_TrackingCode',$status['type']],home_url(),$status['content'],'EFB authorized form '.$id.' verification ['.$run.']');
}
$after=get_option('efb_email_log',[]);
$new=array_slice($after,count($logs));
if(count($calls)!==4||count($new)!==4)throw new RuntimeException('Expected four diagnostic handoffs');
foreach($new as $entry)if(($entry['success']??false)!==true)throw new RuntimeException('Mailer did not accept a diagnostic notification');
echo json_encode(['run'=>$run,'calls'=>$calls,'all_handoffs_accepted'=>true,'entries_created'=>0,'settings_changed'=>false])."\n";
'''.replace('Emsfb\\\\_Public','Emsfb\\_Public')
helpers=(Path(__file__).resolve().parents[1]/'includes/json-efb.php').read_text(encoding='utf-8')
code=code.replace('// JSON_HELPERS',helpers.removeprefix('<?php'))
c=paramiko.SSHClient();c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('45.82.189.77',username='hiwhites',password=getpass.getpass('SSH password: '),look_for_keys=False,allow_agent=False,timeout=20)
try:
    inp,out,err=c.exec_command('/opt/plesk/php/8.4/bin/php',timeout=90)
    inp.write(code);inp.flush();inp.channel.shutdown_write()
    print(out.read().decode());print(err.read().decode());raise SystemExit(out.channel.recv_exit_status())
finally:c.close()
