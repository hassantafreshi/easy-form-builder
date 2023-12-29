<?php

namespace Emsfb;

use WP_REST_Response;
/**
 * Class Emsfb
 * @package Emsfb
 */

 //require_once('functions.php');
class webhook {


    /**
     * Emsfb constructor.
     */
    public function __construct() {

        
        $this->web_hooks();
    }

  

    public function web_hooks(){
        
        add_action('rest_api_init',  @function(){
    
      
              register_rest_route('Emsfb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=>  [$this,'test_fun'],
                  'permission_callback' => '__return_true'
              ]); 

          });
    }


    public function test_fun($slug){

        

        $response = array(
            'success' => true,
            'value' => $slug["name"],
            'content' => "content",
            'nonce_msg' => "code",
            'id' => $slug["id"]
          );
        
        return new WP_REST_Response($response, 200);
       
    
      
    } 



}
new webhook();