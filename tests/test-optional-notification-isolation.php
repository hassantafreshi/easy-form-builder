<?php
// Actual EFB methods with simulated providers: no network, persistence or mail.
class WP_Error {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_url() { return 'https://example.invalid'; }
function wp_unslash($value) { return $value; }
function sanitize_url($value) { return $value; }
$failed_hook = ''; $denied_channel = ''; $actions = [];
function has_action($hook) { return true; }
function apply_filters($hook,$value,...$args) {
    global $denied_channel;
    return $hook === 'efb_shield_allow_side_effect' && ($args[0]['channel'] ?? '') === $denied_channel ? false : $value;
}
function do_action($hook,...$args) {
    global $failed_hook,$actions;
    $actions[]=$hook;
    if($hook===$failed_hook) throw new TypeError('Simulated add-on error');
}
$source=file_get_contents(__DIR__.'/../includes/class-Emsfb-public.php');
$methods='';
foreach(['run_optional_notification_efb','send_optional_sms_efb','efb_intgrate_with_3rd_party_services_efb','dispatch_optional_integrations_efb','efb_side_effect_has_work_efb'] as $name) {
    if(!preg_match('/private function '.$name.'\(.*?^\t\}/ms',$source,$m))throw new RuntimeException('Missing method '.$name);
    $methods.=$m[0];
}
eval('class OptionalNotificationFixture {public $id=2;public $efbFunction;public $logs=[];public $conditionalFailure=false;public $emailCalls=0;
function efb_trace($stage,$data){$this->logs[]=$data;}
function process_conditional_webhook_rules(...$args){if($this->conditionalFailure)throw new RuntimeException("Webhook failure");return [];}
function run($result){$this->efbFunction=new class($result){public $r;function __construct($r){$this->r=$r;}function sms_ready_for_send_efb(...$args){if($this->r instanceof Throwable)throw $this->r;return $this->r;}};
$this->send_optional_sms_efb(2,[],"https://example.invalid","fform","wpsms","fixture");
$this->efb_intgrate_with_3rd_party_services_efb("fixture",[],[[]]);$this->emailCalls++;}
function invoke($name,...$args){return $this->$name(...$args);}' . $methods . '}');
$checks=0;
function isolation_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
foreach([true,false,null,0,new WP_Error(),new RuntimeException('provider'),new Error('missing dependency'),new TypeError('bad provider argument')] as $result){
    $fixture=new OptionalNotificationFixture();$actions=[];$fixture->run($result);
    isolation_check($fixture->emailCalls===1,'SMS failure must not block email');
    isolation_check(count($actions)===3,'Other integrations still run');
    isolation_check(count($fixture->logs)===($result===true?0:1),'Failure is traced accurately');
}
foreach(['efb_3rd_party_telegram_notify','efb_3rd_party_google_sheet_sync','efb_after_form_integration'] as $failed_hook){
    $fixture=new OptionalNotificationFixture();$actions=[];$fixture->run(true);
    isolation_check($fixture->emailCalls===1,'Integration error must not block email');
    isolation_check(count($actions)===3,'A failed channel must not prevent the next channel');
    isolation_check(count($fixture->logs)===1,'Integration error is traced');
}
$failed_hook='';$fixture=new OptionalNotificationFixture();$fixture->conditionalFailure=true;$actions=[];$fixture->run(true);
isolation_check($fixture->emailCalls===1&&count($actions)===3,'Conditional webhook failure is isolated');
foreach(['telegram','googlesheet','webhook'] as $denied_channel){
    $fixture=new OptionalNotificationFixture();$actions=[];$fixture->run(true);
    isolation_check($fixture->emailCalls===1,'Side-effect veto preserves independent email flow');
    isolation_check(count($actions)===2,'Side-effect veto is respected');
}
$denied_channel='';$fixture=new OptionalNotificationFixture();
isolation_check($fixture->invoke('run_optional_notification_efb','test',function(){return new WP_Error();})===false,'WP_Error is normalized');
isolation_check($fixture->invoke('run_optional_notification_efb','test',function(){return 'success';})==='success','Success result preserved');
echo "PASS: $checks optional notification isolation checks; no external effects\n";
