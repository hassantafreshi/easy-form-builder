<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No direct access allow ;)

class Create {

	public $setting_name;
	public $options = array();

	public $id_;
	public $name;
	public $email;
	public $value;
	public $userId;

	protected $db;
	public function __construct() {
		$this->setting_name = 'Emsfb_create';
		global $wpdb;
		$this->db = $wpdb;
		$this->get_settings();
		
		$this->options = get_option( $this->setting_name );
		

		if ( empty( $this->options ) ) {
			update_option( $this->setting_name, array() );
		}

		add_action( 'admin_menu', array( $this, 'add_Create_menu' ), 11 );
		add_action( 'admin_create_scripts', array( $this, 'admin_create_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_create' ) );
		add_action('fun_Emsfb_creator', array( $this, 'fun_Emsfb_creator'));
		add_action('wp_ajax_add_form_Emsfb', array( $this,'add_form_structure'));
		
	}

	public function add_Create_menu() {
		add_submenu_page( 'Emsfb', __( 'Create', 'Emsfb' ), __( 'Create', 'Emsfb' ), 'Emsfb_create', 'Emsfb_create', array(
			$this,
			'render_settings'
		) );

	}


	public function get_settings() {
		$settings = get_option( $this->setting_name );
		if ( ! $settings ) {
			update_option( $this->setting_name, array(
				'rest_api_status' => 1,
			) );
		}

		return apply_filters( 'Emsfb_get_settings', $settings );
	}


	public function register_create() {
		
		if ( false == get_option( $this->setting_name ) ) {
			add_option( $this->setting_name );
		}
		
		
	}

	









	public function render_settings() {

	?>
			<script>
				
				let bdy =document.getElementsByTagName('body');
				bdy[0].classList.add("bg-color");
				
			</script>
            <div id="tab_container">
                
			<div class="m-4">
    <div class="row d-flex justify-content-center align-items-center">
        <div class="col-md-12">
            <div id="emsFormBuilder-form" >
            <form id="emsFormBuilder-form-id">
                <h1 id="emsFormBuilder-form-title">Easy Form Bulider</h1>
                
                <div class="all-steps" id="all-steps"> 
                    <span class="step"><i class="fa fa-tachometer"></i></span> 
                    <span class="step"><i class="fa fa-briefcase"></i></span> 
                    <div class="addStep" id="addStep" >
                    </div>
                    <span class="step"><i class="fa fa-floppy-o"></i></span> 
                </div>
                <div class="all-steps" > 
                    <h5 class="step-name f-setp-name" id ="step-name"><?php __('Define','Emsfb') ?></h5> 
                </div>
                <div id="message-area"></div>
                <div class="tab" id="firsTab">
                    <h5>Form Name: *</h5>
                    <input placeholder="" type="text"  name="setps" class="require emsFormBuilder" id="form_name" max="20">
                    </br>
                    <h5>Number of steps: *</h5>
                    <input placeholder="1,2,3.." type="number"  name="setps" class="require emsFormBuilder" id="steps" max="20">
                </div>
                <div class="tab" id="tabInfo">

                </div>
                <div  id="tabList">

                </div>
           
                <div class="thanks-message text-center" id="emsFormBuilder-text-message-view"> 
                    <h3>Done</h3> <span>Great, Your form is builded successfully</span>
                </div>
                <div style="overflow:auto;" id="nextprevious">
                    <div style="float:right;"> <button type="button" id="prevBtn" class="mat-shadow emsFormBuilder p-3" onclick="nextPrev(-1)"><i class="fa fa-angle-double-left"></i></button> <button type="button" id="nextBtn" class="mat-shadow emsFormBuilder p-3" onclick="nextPrev(1)"><i class="fa fa-angle-double-right"></i></button> </div>
                    <div style="float:left;"> 
                        <button type="button" class="mat-shadow emsFormBuilder p-3" onClick="helpLink_emsFormBuilder()"><i class="fa fa-question" placeholder="Help"></i></button>
                        <button type="button" class="mat-shadow emsFormBuilder p-3" id="button-preview-emsFormBuilder" onClick="preview_emsFormBuilder()"><i class="fa fa-eye" placeholder="preview"></i></button>
                    </div>
                </div>

              </form>      
            </div>
        </div>
    </div>
    <div id="body_emsFormBuilder" style="display:none"> </div>
</div>
                  
				
            
            </div><!-- #tab_container-->
        </div><!-- .wrap -->
		<?php
		wp_enqueue_script( 'Emsfb-listicons-js', Emsfb_URL . 'includes/admin/assets/js/listicons.js' );
		wp_enqueue_script('Emsfb-listicons-js');
		$pro =false;
		$ac= $this->get_activeCode_Emsfb();
		if (md5($_SERVER['SERVER_NAME'])==$ac){
			$pro=true;
		}
		if(	$pro==true){
				wp_register_script('whitestudio-admin-pro-js', 'http://whitestudio.team/js/cool.js'.$ac, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');
		}
		wp_enqueue_script( 'Emsfb-admin-js', Emsfb_URL . 'includes/admin/assets/js/admin.js' );		
		wp_localize_script('Emsfb-admin-js','s_var',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1,
			'pro' => $pro,
					));

			



		//پایان کد نسخه پرو

		//echo ob_get_clean();
		 wp_enqueue_script( 'Emsfb-core-js', Emsfb_URL . 'includes/admin/assets/js/core.js' );
		 wp_localize_script('Emsfb-core-js','ajax_object_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));
	
	


		wp_register_script('jquery', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js', null, null, true);
		wp_enqueue_script('jquery');

		wp_register_script('bootstrapcdn', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js', null, null, true);
		wp_enqueue_script('bootstrapcdn');

	
	}

	public function fun_Emsfb_creator()
	{
		
		?>
			
		<?php
	}

	public function add_form_structure(){
	

		$this->userId =get_current_user_id();
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';
		if( isset($_POST['email']) ){
			$email =$_POST['email'];
		}
		
		$this->id_ ="hid";
		$this->name =  $_POST['name'];
		$this->email =  $email;
		$this->value =  $_POST['value'];
		if($this->isHTML($_POST['value'])){
			$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
			wp_send_json_success($response,$_POST);
			die();
		}
/* 		error_log(json_encode($_POST['value']));
		error_log(json_encode($this->value));

		error_log(json_encode($_POST['name']));
		error_log(json_encode($this->name));

	//	error_log(json_encode($_POST['email']));
		error_log(json_encode($this->email)); */

		$this->insert_db();
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]"); 
		}else{
			$response = array( 'success' => false , "m"=> "Error,Form not Created!"); 
		}
		wp_send_json_success($response,$_POST);

		die();
		
	}
	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function insert_db(){
	
		
		

		$table_name = $this->db->prefix . "Emsfb_form";
		//echo $table_name;
		$this->db->insert($table_name, array(
			'form_name' => $this->name, 
			'form_structer' => $this->value, 
			'form_email' => $this->email, 
			'form_created_by' => $this->userId, 
			
		));    $this->id_  = $this->db->insert_id; 
		//echo "last id" + $lastid;
		
		
		
		
	}
	public function get_activeCode_Emsfb()
	{
		// اکتیو کد بر می گرداند	
		
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$r =json_decode($val);
			$rtrn =$r->activeCode;
			break;
			} 
		}
		return $rtrn;
	}
}

new Create();