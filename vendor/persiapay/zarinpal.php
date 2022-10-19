<?php 

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class zarinPalEFB{

    public function __construct() { }

    public function create_bill_zarinPal($jsonData,){
        $response;
        try{						
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
            ));
        
            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            curl_close($ch);
        
    
            //$result['data']["authority"]
            if ($err) {
                $msg = 'کد خطا: CURL#' . $er;
                $erro = 'در اتصال به درگاه مشکلی پیش آمد.';
                $response = array( 'success' => false  , 're'=>$msg);
                return $response;
            } else {
            if (empty($result['errors'])) {
                if ($result['data']['code'] == 100) {

                    //header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                    $auth = $result['data']["authority"];
                    //$filtered +=['auth'=>$auth];
                    
                    //$this->insert_temp_costumer($website,$paymentType,$product_price,'ZarinPal',$email,$name,$auth);
              
                  
                        $GoToIPG = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"];
                        $response = array( 'success' => true  ,'trackingCode'=>$check, 're'=>'در حال انتقال به درگاه بانک' , 'url'=>$GoToIPG);	
                        
                  
                    
                    
                }
                } else {
                $erro ='Error Code: ' . $result['errors']['code'];
                $msg = 'تراکنش ناموفق بود، شرح خطا: ' .  $result['errors']['message'];
                $response = array( 'success' => false  , 're'=>$msg);
                }
            }
        }catch(Exception $e){
            $msg = 'تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
            $response = array( 'success' => false  , 're'=>$msg);				
        }

        return $response;
    }

    public function validate_payment_zarinPal($jsonData){
        error_log('json validate_payment_zarinPal');
        try{
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));
            $result = curl_exec($ch);
            /* curl_close($ch);
            error_log($result); */
            $result = json_decode($result, true);
            
            if($result['errors']){
             $msg = 'زرین پال ، شرح خطا:' . $result['errors']['code'];
             $result['errors']['msg']=$msg;
            }else{        
                if($result['data']['code'] == 100 || $result['data']['code'] == 101){

                /* {"data":{"code":100,"message":"Paid","card_hash":"9F4C29B42DEB085C948ED0A08B13B277E90C320479A47D335C3437DAC765076EA6591EB35A319F1784E7E19B91A9FCDF63513F999F66FCD1694964910F2B5F46",
                "card_pan":"603799******5292",
                "ref_id":35234721201,
                "fee_type":"Merchant",
                "fee":100},"errors":[]} 
                
                */
                    $refid =$result['data']['ref_id'];
                    if( isset($refid) && $refid != '' ){
                        $msg = 'ok';
                        $outp['msg'] = $msg;
                        $status=true;
                    }else{
                        $msg = 'متافسانه زرین پال  قادر به دریافت کد پیگیری نمی‌باشد! نتیجه درخواست: ' . $header['http_code'];
                        $result['errors']['message']=$msg;
                    }
                }elseif($header['http_code'] == 400){
                    $msg = ' تراکنش ناموفق بود، شرح خطا: ' . $response;
                    $result['errors']['message']=$msg;
                    $result['errors']['code']=400;
                    $outp['msg'] = $msg;
                }else{
                    error_log($result['errors']['code']);
                    $msg = ' تراکنش ناموفق بود، شرح خطا: ' . $header['http_code'];
                    $result['errors']['message']=$msg;
                    $result['errors']['code']=600;
                }
            }
        }catch( Exception $e ){
            $msg = ' تراکنش ناموفق بود، شرح خطا سمت برنامه شما: ' . $e->getMessage();
            $result['errors']['message']=$msg;
            $result['errors']['code']=500;
        }
        return $result;
    }
}