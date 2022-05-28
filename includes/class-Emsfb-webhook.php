<?php
/** Prevent this file from being accessed directly */
namespace Emsfb;


/**
 * Class Emsfb
 */
class webhook {


    /**
     * Emsfb constructor.
     */
    public function __construct() {

     /*    error_log('construct webhook');
        $this->web_hooks(); */
    }

  

    public function web_hooks(){
        error_log('webook');
        add_action('rest_api_init',  @function(){
    
          
              register_rest_route('efb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=> 'test_fun'
              ]); 
          });
    }


    function test_fun($slug){
        error_log($slug['name']);
        error_log($slug['id']);
        return $slug['id'];
       // return $fs;
    
      
    } 
}
