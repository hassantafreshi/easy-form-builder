<?php
/** Prevent this file from being accessed directly */
namespace Emsfb;

use WP_REST_Response;
/**
 * Class Emsfb
 * @package Emsfb
 */
class webhook {


    /**
     * Emsfb constructor.
     */
    public function __construct() {

        error_log("test webhook");
        $this->web_hooks();
    }

  

    public function web_hooks(){
        
        add_action('rest_api_init',  @function(){
    
      
              register_rest_route('Emsfb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=>  [$this,'test_fun'],
                  'permission_callback' => '__return_true'
              ]); 

              //             //post filled forms => get_ajax_form_public
             //post upload file => file_upload_public
            
             //post pay_stripe_sub_Emsfb => payment


             //set_rMessage_id_Emsfb => store responses
             //persia_pay_Emsfb

             //get track id => return messages => get_ajax_track_public
             //get send email => mail_send_form_submit
          

          });
    }


    public function test_fun($slug){



        $response = array(
            'success' => true,
            'value' => 'value',
            'content' => "content",
            'nonce_msg' => "code",
            'id' => 'this->id'      
          );
        
        return new WP_REST_Response($response, 200);
       // return $fs;
    
      
    } 

}
new webhook();