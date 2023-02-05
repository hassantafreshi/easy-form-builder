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

     /*    
        $this->web_hooks(); */
    }

  

    public function web_hooks(){
       /*  
        add_action('rest_api_init',  @function(){
    
          
              register_rest_route('efb/v1','test/(?P<name>[a-zA-Z0-9_]+)/(?P<id>[a-zA-Z0-9_]+)', [
                  'method'=> 'GET',
                  'callback'=> 'test_fun'
              ]); 
          }); */
    }


    function test_fun($slug){
        
        
        return $slug['id'];
       // return $fs;
    
      
    } 
}
