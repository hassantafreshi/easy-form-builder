<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,4).'/wp-load.php';
if(home_url()!=='https://lightgoldenrodyellow-coyote-155974.hostingersite.com')throw new RuntimeException('Unexpected test site');
global $wpdb;
$mode=$argv[1]??'';$run=$argv[2]??'';
if(!preg_match('/^[a-f0-9]{24}$/D',$run))throw new RuntimeException('Invalid run identifier');
$key='_efb_guest_matrix_'.$run;$state=get_option($key,null);
function guest_clear_settings(){foreach(array('decoded','pub','raw')as$mode)wp_cache_delete('settings:'.$mode,'emsfb');get_setting_Emsfb('_clear_cache');}
if($mode==='audit'){
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    $runs=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('_efb_guest_matrix_').'%'));
    $forms=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_form WHERE form_name LIKE %s",'EFB guest %'));
    $pages=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_title LIKE %s",'EFB guest %'));
    echo wp_json_encode(array('remaining_runs'=>$runs,'remaining_forms'=>$forms,'remaining_pages'=>$pages,'autoptimize_active'=>is_plugin_active('autoptimize/autoptimize.php')));
}elseif($mode==='setup'){
    if($state)throw new RuntimeException('Run already exists');
    $input=json_decode(stream_get_contents(STDIN),true);
    if(strpos($input['stripeSKey']??'','sk_test_')!==0||strpos($input['stripePKey']??'','pk_test_')!==0)throw new RuntimeException('Sandbox keys required');
    $state=array('forms'=>array(),'pages'=>array(),'settings'=>get_option('emsfb_settings',null),'transient'=>get_transient('emsfb_settings_transient'),'health'=>get_option(\Emsfb\Request_Health::OPTION,null),'shield'=>get_option('emsfb_human_shield_settings',null),'options'=>array(),'uploads'=>array());
    foreach(array('litespeed.conf.optm-js_defer','litespeed.conf.optm-js_min','litespeed.conf.optm-js_comb','litespeed.conf.optm-css_min','emsfb_pro','emsfb_addon_AdnSPF','autoptimize_js','autoptimize_js_aggregate','autoptimize_js_include_inline','autoptimize_css','autoptimize_css_aggregate','autoptimize_css_include_inline','autoptimize_html')as$name)$state['options'][$name]=get_option($name,null);
    add_option($key,$state,'','off');
    if(!empty($input['pro_test'])){update_option('emsfb_pro',3);update_option('emsfb_addon_AdnSPF',1);}
    $settings=clone get_setting_Emsfb('decoded');$settings->stripePKey=$input['stripePKey'];$settings->stripeSKey=$input['stripeSKey'];$settings->AdnSPF=1;$settings->AdnHSH=0;
    $raw=wp_json_encode($settings);update_option('emsfb_settings',$raw,false);set_transient('emsfb_settings_transient',$raw,1800);guest_clear_settings();
    foreach($input['fixtures']as$name=>$fixture){
        $structure=$fixture['structure'];$structure[0]['sendEmail']=false;$structure[0]['email']='';$structure[0]['email_temp']='';
        $wpdb->insert($wpdb->prefix.'emsfb_form',array('form_name'=>'EFB guest '.$run.' '.$name,'form_type'=>$structure[0]['type'],'form_structer'=>wp_json_encode($structure),'form_email'=>'','form_created_by'=>1));
        $fid=(int)$wpdb->insert_id;if(!$fid)throw new RuntimeException('Form fixture creation failed');
        $state['forms'][$name]=$fid;update_option($key,$state,false);
        $content=$fixture['block']?'<!-- wp:easy-form-builder/form {"formId":"'.$fid.'"} /-->':'[EMS_Form_Builder id="'.$fid.'"]';
        $pid=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'EFB guest '.$run.' '.$name,'post_content'=>$content),true);
        if(is_wp_error($pid))throw new RuntimeException('Page fixture creation failed');
        $state['pages'][$name]=array('id'=>$pid,'url'=>get_permalink($pid),'fid'=>$fid);update_option($key,$state,false);
    }
    foreach(array('control'=>'<p>EFB guest cache control</p>','tracker'=>'[Easy_Form_Builder_confirmation_code_finder]')as$name=>$content){
        $pid=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'EFB guest '.$run.' '.$name,'post_content'=>$content),true);if(is_wp_error($pid))throw new RuntimeException('Page fixture failed');
        $state['pages'][$name]=array('id'=>$pid,'url'=>get_permalink($pid));update_option($key,$state,false);
    }
    $probe_key='_efb_cache_probe_'.$run;
    $probe_file=WPMU_PLUGIN_DIR.'/efb-cache-probe-'.$run.'.php';
    if(!is_dir(WPMU_PLUGIN_DIR)||file_exists($probe_file))throw new RuntimeException('Probe directory unavailable');
    $state['cache_probe_file']=$probe_file;update_option($key,$state,false);
    $probe='<?php add_action("wp",static function(){if((int)get_queried_object_id()==='.(int)$state['pages']['control']['id'].'){ $key='.var_export($probe_key,true).';update_option($key,(int)get_option($key,0)+1,false);}});';
    if(file_put_contents($probe_file,$probe)===false)throw new RuntimeException('Cannot create scoped cache probe');
    echo wp_json_encode(array('pages'=>$state['pages']));
}elseif(!$state){throw new RuntimeException('Missing run state');
}elseif($mode==='status'){
    $counts=array();foreach($state['forms']as$name=>$fid)$counts[$name]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid));
    $pay=array();foreach($state['forms']as$name=>$fid){$tracks=$wpdb->get_col($wpdb->prepare("SELECT track FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid));foreach($tracks as$track){$row=$wpdb->get_row($wpdb->prepare("SELECT transaction_id,status FROM {$wpdb->prefix}emsfb_pay_ WHERE track=%s",$track),ARRAY_A);if($row)$pay[$name]=$row['status'];}}
    $replies=array();foreach($state['forms']as$name=>$fid)$replies[$name]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_rsp_ r INNER JOIN {$wpdb->prefix}emsfb_msg_ m ON r.msg_id=m.msg_id WHERE m.form_id=%d",$fid));
    $control_path=parse_url($state['pages']['control']['url'],PHP_URL_PATH);
    $supercache_file=WP_CONTENT_DIR.'/cache/supercache/'.parse_url(home_url(),PHP_URL_HOST).$control_path.'index-https.html';
    echo wp_json_encode(array('counts'=>$counts,'replies'=>$replies,'payments'=>$pay,'cache_probe_count'=>(int)get_option('_efb_cache_probe_'.$run,0),'supercache_file_exists'=>file_exists($supercache_file),'health'=>get_option(\Emsfb\Request_Health::OPTION,array())));
}elseif($mode==='autoptimize'){
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    $state['autoptimize_active']=is_plugin_active('autoptimize/autoptimize.php');update_option($key,$state,false);
    $active=activate_plugin('autoptimize/autoptimize.php');if(is_wp_error($active))throw new RuntimeException('Autoptimize activation failed');
    foreach(array('autoptimize_js','autoptimize_js_aggregate','autoptimize_js_include_inline','autoptimize_css','autoptimize_css_aggregate','autoptimize_css_include_inline','autoptimize_html')as$name)update_option($name,'on');
    $plugin=get_plugin_data(WP_PLUGIN_DIR.'/autoptimize/autoptimize.php');echo wp_json_encode(array('plugin'=>'Autoptimize','version'=>$plugin['Version'],'js_css_html'=>'on','aggregate_inline'=>'on'));
}elseif($mode==='optimize'){
    update_option('litespeed.conf.optm-js_defer',2);foreach(array('litespeed.conf.optm-js_min','litespeed.conf.optm-js_comb','litespeed.conf.optm-css_min')as$name)update_option($name,true);
    echo wp_json_encode(array('optimization'=>'delay/minify/combine enabled'));
}elseif($mode==='shield'){
    $settings=clone get_setting_Emsfb('decoded');$settings->AdnHSH=1;$raw=wp_json_encode($settings);update_option('emsfb_settings',$raw,false);set_transient('emsfb_settings_transient',$raw,1800);guest_clear_settings();
    $shield=is_array($state['shield'])?$state['shield']:array();$shield['enabled']=1;$shield['mode']='balanced';update_option('emsfb_human_shield_settings',$shield,false);echo '{"shield":"balanced"}';
}elseif($mode==='cleanup'){
    $private=json_decode(stream_get_contents(STDIN),true)?:array();
    $settings=get_setting_Emsfb('decoded');$secret=$settings->stripeSKey??'';
    if(strpos($secret,'sk_test_')!==0)throw new RuntimeException('Refuse non-sandbox cleanup');
    if(!class_exists('Stripe\\StripeClient'))require dirname(__DIR__).'/vendor/autoload.php';
    $stripe=new \Stripe\StripeClient($secret);$payment_ids=array();$owned_messages=array();
    foreach($state['forms']as$fid){
        $messages=$wpdb->get_results($wpdb->prepare("SELECT msg_id,track,content FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id=%d",$fid));
        foreach($messages as$message){
            $payment=$wpdb->get_row($wpdb->prepare("SELECT id,transaction_id FROM {$wpdb->prefix}emsfb_pay_ WHERE track=%s",$message->track));
            if($payment){$intent=$stripe->paymentIntents->retrieve($payment->transaction_id);if($intent->status==='succeeded')$stripe->refunds->create(array('payment_intent'=>$intent->id));elseif(!in_array($intent->status,array('canceled','processing'),true))$stripe->paymentIntents->cancel($intent->id);$payment_ids[]=(int)$payment->id;}
            $owned_messages[]=(int)$message->msg_id;
            $wpdb->delete($wpdb->prefix.'emsfb_rsp_',array('msg_id'=>$message->msg_id));
        }
        foreach(array('emsfb_msg_','emsfb_stts_','emsfb_form')as$table)$wpdb->delete($wpdb->prefix.$table,array($table==='emsfb_form'?'form_id':($table==='emsfb_stts_'?'fid':'form_id')=>$fid));
        foreach(array('events','challenges','rate_limits')as$table)$wpdb->delete($wpdb->prefix.'emsfb_shield_'.$table,array('form_id'=>$fid));
    }
    foreach($payment_ids as$id)$wpdb->delete($wpdb->prefix.'emsfb_pay_',array('id'=>$id));
    foreach($private['operations']??array()as$operation){
        $fid=(int)($operation['form_id']??0);$kind=$operation['kind']??'';if($kind!=='reply'&&($kind!=='form'||!in_array($fid,array_values($state['forms']),true)))continue;
        $proof=\Emsfb\Form_Runtime::verify($operation['runtime_token']??'',$fid);
        if(!$proof||is_wp_error($proof)||!preg_match('/^[a-f0-9-]{36}$/D',$operation['request_id']??''))continue;
        if($kind==='reply'&&($fid!==0||!in_array((int)explode(':',$proof['sid'])[0],$owned_messages,true)))continue;
        $ledger='efb_op_'.substr($proof['owner'],0,8).'_'.hash_hmac('sha256',$proof['owner'].'|'.$kind.'|'.$operation['request_id'],wp_salt('nonce'));delete_option($ledger);
    }
    $upload_base=realpath(wp_upload_dir()['basedir']);
    foreach($private['uploads']??array()as$upload){
        $path=realpath($upload['file']??'');
        if($path&&$upload_base&&strpos($path,$upload_base.DIRECTORY_SEPARATOR)===0&&preg_match('/^efb-PLG-[0-9]{6}-[A-Z0-9]{8}\\.png$/D',basename($path))){
            \Emsfb\Upload_Guard::release_pending_uploads(array($upload['url']));wp_delete_file($path);
        }
    }
    foreach($state['pages']as$page)wp_delete_post($page['id'],true);
    if(!empty($state['cache_probe_file'])){if($state['cache_probe_file']!==WPMU_PLUGIN_DIR.'/efb-cache-probe-'.$run.'.php')throw new RuntimeException('Unexpected probe path');if(file_exists($state['cache_probe_file']))unlink($state['cache_probe_file']);}
    delete_option('_efb_cache_probe_'.$run);
    if(array_key_exists('autoptimize_active',$state)&&!$state['autoptimize_active']){require_once ABSPATH.'wp-admin/includes/plugin.php';deactivate_plugins('autoptimize/autoptimize.php',true);}
    foreach(array('emsfb_settings'=>$state['settings'],'emsfb_human_shield_settings'=>$state['shield'],\Emsfb\Request_Health::OPTION=>$state['health'])+$state['options']as$name=>$value){if($value===null)delete_option($name);else update_option($name,$value,false);}
    foreach($state['options']as$name=>$value)if(get_option($name,null)!=$value)throw new RuntimeException('Test option restoration verification failed');
    if(array_key_exists('autoptimize_active',$state)&&is_plugin_active('autoptimize/autoptimize.php')!==$state['autoptimize_active'])throw new RuntimeException('Autoptimize activation restoration failed');
    if($state['transient']===false)delete_transient('emsfb_settings_transient');else set_transient('emsfb_settings_transient',$state['transient'],1800);guest_clear_settings();delete_option($key);
    echo '{"cleanup":"done","settings_restored":true,"test_options_verified":true}';
}else throw new RuntimeException('Unknown mode');
