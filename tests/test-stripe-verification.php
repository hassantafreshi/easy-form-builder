<?php
/** Real WP handler and database, controlled provider responses. Not a real payment. */
namespace Stripe {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    class StripeClient {
        public static $intent;
        public static $fail = false;
        public $paymentIntents;
        public function __construct($key) {
            $this->paymentIntents = new class {
                public function retrieve($id,$args) {
                    if (StripeClient::$fail) throw new \RuntimeException('Test gateway outage');
                    return StripeClient::$intent;
                }
            };
        }
    }
}
namespace {
    define('DOING_AJAX',true);
    require dirname(__DIR__,4).'/wp-load.php';
    require_once dirname(__DIR__).'/vendor/stripe/class-Emsfb-stripe-payment.php';
    class EfbStripeVerificationEnd extends Exception {}
    add_filter('wp_die_ajax_handler',static function(){return static function(){throw new EfbStripeVerificationEnd();};});
    $ref=new ReflectionClass('Emsfb\\_Public');
    $public=$ref->newInstanceWithoutConstructor();
    $original_health=get_option(\Emsfb\Request_Health::OPTION,null);
    $original_event=wp_next_scheduled('emsfb_stripe_reconcile_efb'); // the outage check queues a background re-check
    $rows=array(); $passed=0;
    $check=static function($ok,$label)use(&$passed){if(!$ok)throw new RuntimeException($label);$passed++;};
    $call=static function($intent,$track)use($public){
        $request=new WP_REST_Request('POST','/Emsfb/v1/forms/payment/stripe/confirm');
        $request->set_header('Content-Type','application/json');
        $request->set_body(wp_json_encode(array('paymentIntentId'=>$intent,'trackid'=>$track)));
        ob_start();
        try{$public->pay_stripe_confirm_Emsfb_api($request);}catch(EfbStripeVerificationEnd $end){}
        return json_decode(ob_get_clean(),true);
    };
    try {
        new \Emsfb\StripePayment();
        $track='EFBTEST-'.substr(str_replace('-','',wp_generate_uuid4()),0,12);
        $intent='pi_EfbFixture'.str_replace('-','',wp_generate_uuid4());
        $id=\Emsfb\StripePayment::insert_payment(array('track'=>$track,'transaction_id'=>$intent,'amount'=>1,'currency'=>'USD'));
        if(!$id)throw new RuntimeException('Fixture insertion failed');
        $rows[]=$id;
        $status=static function()use($wpdb,$id){return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}emsfb_pay_ WHERE id=%d",$id));};
        \Stripe\StripeClient::$intent=(object)array('id'=>$intent,'status'=>'requires_payment_method','amount_received'=>0,'currency'=>'usd');
        $r=$call($intent,$track);
        $check(empty($r['data']['success'])&&$status()==='pending','Unpaid provider response rejected');
        \Stripe\StripeClient::$intent->status='succeeded'; \Stripe\StripeClient::$intent->amount_received=100;
        $r=$call('pi_unrelated',$track);
        $check(empty($r['data']['success'])&&$status()==='pending','Original tracking fallback exploit rejected');
        $r=$call($intent,$track.'x');
        $check(empty($r['data']['success'])&&$status()==='pending','Different tracking code rejected');
        \Stripe\StripeClient::$intent->amount_received=50;
        $r=$call($intent,$track);
        $check(empty($r['data']['success'])&&$status()==='pending','Wrong amount rejected');
        \Stripe\StripeClient::$intent->amount_received=100; \Stripe\StripeClient::$intent->currency='eur';
        $r=$call($intent,$track);
        $check(empty($r['data']['success'])&&$status()==='pending','Wrong currency rejected');
        \Stripe\StripeClient::$intent->currency='usd'; \Stripe\StripeClient::$fail=true;
        $r=$call($intent,$track);
        $check(empty($r['data']['success'])&&$status()==='pending','Provider outage does not complete payment');
        $check(isset(get_option(\Emsfb\Request_Health::OPTION)['server:payment:503:gateway_verification_unavailable']),'Verification outage reported in dashboard diagnostics');
        \Stripe\StripeClient::$fail=false;
        $r=$call($intent,$track);
        $check(!empty($r['data']['success'])&&$status()==='completed','Matching verified payment completes');
        $r=$call($intent,$track);
        $check(!empty($r['data']['success'])&&$status()==='completed','Verified confirmation is repeatable');
        \Emsfb\StripePayment::update_payment($id,array('status'=>'refunded'));
        $r=$call($intent,$track);
        $check(empty($r['data']['success'])&&$status()==='refunded','Refunded payment cannot be completed again');
        $r=$call(array('pi_bad'),array('bad'));
        $check(empty($r['data']['success']),'Malformed JSON shapes fail safely');
        echo "$passed Stripe handler checks passed (mocked provider, real WordPress/database).\n";
    } finally {
        foreach($rows as $id)$wpdb->delete($wpdb->prefix.'emsfb_pay_',array('id'=>$id));
        if($original_health===null)delete_option(\Emsfb\Request_Health::OPTION);else update_option(\Emsfb\Request_Health::OPTION,$original_health,false);
        wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');
        if($original_event)wp_schedule_single_event($original_event,'emsfb_stripe_reconcile_efb');
    }
}
