<?php
// Exercise the actual post-save SMS block; no WordPress, SMS, email or DB writes.
$path = $argv[1] ?? __DIR__ . '/../includes/class-Emsfb-public.php';
$source = file_get_contents($path);
$start = strpos($source, "if (isset(\$form_fields_array[0]['smsnoti'])", strpos($source, 'case "form":'));
$end = strpos($source, 'if ($should_send_email)', $start);
if ($start === false || $end === false) { throw new RuntimeException('SMS block not found'); }
$block = substr($source, $start, $end - $start);
$block = str_replace('$this->send_optional_sms_efb(', '$this->efbFunction->sms_ready_for_send_efb(', $block);
function wp_send_json_success(...$args) { throw new RuntimeException('Request aborted before email'); }
eval('class SmsContinuationFixture { public $id=2; public $efbFunction; public $traces=[]; public function efb_trace($stage,$data){$this->traces[]=$stage;} public function run($enabled,$result){$this->efbFunction=new class($result){public $result;function __construct($r){$this->result=$r;}function sms_ready_for_send_efb(...$args){return $this->result;}};$form_fields_array=[["smsnoti"=>$enabled]];$phone_numbers=[];$url="https://example.invalid";$track_code="retained-fixture";' . $block . 'return "email-stage-reached";}}');
$checks=0;
foreach ([2,3,5] as $id) {
    foreach ([true,false,null,0] as $result) {
        $fixture=new SmsContinuationFixture();$fixture->id=$id;
        if ($fixture->run('1',$result)!=='email-stage-reached') throw new RuntimeException('No continuation');
        if (($result!==true) !== in_array('submit.sms-failed',$fixture->traces,true)) throw new RuntimeException('Wrong SMS failure trace');
        $checks+=2;
    }
    $fixture=new SmsContinuationFixture();
    if ($fixture->run('0',false)!=='email-stage-reached' || $fixture->traces) throw new RuntimeException('SMS disabled regression');
    $checks++;
}
echo "PASS: $checks SMS/email continuation checks; no persistent state changed\n";
