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
	public $formtype;

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
		add_submenu_page( 'Emsfb', __( 'Create', 'easy-form-builder' ), __( 'Create', 'easy-form-builder' ), 'Emsfb_create', 'Emsfb_create', array(
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
    <div class="row d-flex justify-content-center align-items-center <?php  if(is_rtl()) echo 'rtl-text' ?>">
        <div class="col-md-12">
            <div id="emsFormBuilder-form" >
            <form id="emsFormBuilder-form-id">
                <h1 id="emsFormBuilder-form-title"><?php _e('Easy Form Builder','easy-form-builder') ?></h1>
                
                <div class="all-steps" id="all-steps"> 
                    <span class="step"><i class="fa fa-tachometer"></i></span> 
                    <span class="step"><i class="fa fa-briefcase"></i></span> 
                    <div class="addStep" id="addStep" >
                    </div>
                    <span class="step"><i class="fa fa-floppy-o"></i></span> 
                </div>
                <div class="all-steps" > 
                    <h5 class="step-name f-setp-name" id ="step-name"><?php _e('Define','easy-form-builder') ?></h5> 
                </div>
                <div id="message-area"></div>
                <div class="tab" id="firsTab">
                    <h5><?php _e('Form Name','easy-form-builder') ?>: *</h5>
                    <input placeholder="" type="text"  name="setps" class="require emsFormBuilder" id="form_name" max="20">
                    </br>
                    <h5><?php _e('Number of steps','easy-form-builder') ?>: *</h5>
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
				wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');

				wp_localize_script('whitestudio-admin-pro-js','efb_var',array(
					'pro' => $pro,
					'rtl' => is_admin() ,
					'text' => [
						"allowMultiselect" => __('Allow multi-select','easy-form-builder'),
						"DragAndDropUI" => __('Drag and drop UI','easy-form-builder')]
							));
		}

		$lang = [
			"create" => __('Create','easy-form-builder'),
			"define" => __('Define','easy-form-builder'),
			"formName" => __('Form Name','easy-form-builder'),
			"createDate" => __('Create Date','easy-form-builder'),
			"edit" => __('Edit','easy-form-builder'),
			"content" => __('Content','easy-form-builder'),
			"trackNo" => __('Track No.','easy-form-builder'),
			"formDate" => __('Form Date','easy-form-builder'),
			"by" => __('By','easy-form-builder'),
			"ip" => __('IP','easy-form-builder'),
			"guest" => __('Guest','easy-form-builder'),
			"info" => __('Info','easy-form-builder'),
			"response" => __('Response','easy-form-builder'),
			"date" => __('Date','easy-form-builder'),
			"videoDownloadLink" => __('Video Download Link','easy-form-builder'),
			"downloadViedo" => __('Download Viedo','easy-form-builder'),
			"youCantUseHTMLTagOrBlank" => __('You can not use HTML Tag or send blank message.','easy-form-builder'),
			"error" => __('Error,','easy-form-builder'),
			"reply" => __('Reply','easy-form-builder'),
			"messages" => __('Messages','easy-form-builder'),
			"close" => __('Close','easy-form-builder'),
			"pleaseWaiting" => __('Please Waiting','easy-form-builder'),
			"loading" => __('Loading','easy-form-builder'),
			"remove" => __('Remove!','easy-form-builder'),
			"areYouSureYouWantDeleteItem" => __('Are you sure you want to delete this item?','easy-form-builder'),
			"no" => __('NO','easy-form-builder'),
			"yes" => __('Yes','easy-form-builder'),
			"numberOfSteps" => __('Number of steps','easy-form-builder'),
			"easyFormBuilder" => __('Easy Form Builder','easy-form-builder'),
			"titleOfStep" => __('Title of step','easy-form-builder'),
			"proVersion" => __('Pro Version','easy-form-builder'),
			"getProVersion" => __('Get Pro version','easy-form-builder'),
			"clickHereGetActivateCode" => __('Click here to get Activate Code.','easy-form-builder'),
			"email" => __('Email','easy-form-builder'),
			"trackingCode" => __('Tracking code','easy-form-builder'),		
			"save" => __('Save','easy-form-builder'),
			"waiting" => __('Waiting','easy-form-builder'),
			"saved" => __('Saved','easy-form-builder'),
			"error" => __('Error,','easy-form-builder'),
			"stepName" => __('Step Name','easy-form-builder'),
			"IconOfStep" => __('Icon of step','easy-form-builder'),
			"define" => __('Define','easy-form-builder'),
			"stepTitles" => __('Step Titles','easy-form-builder'),
			"elements" => __('Elements:','easy-form-builder'),
			"delete" => __('Delete','easy-form-builder'),
			"newOption" => __('New option','easy-form-builder'),
			"documents" => __('Documents','easy-form-builder'),
			"image" => __('Image','easy-form-builder'),
			"media" => __('Media','easy-form-builder'),
			"videoOrAudio" => __('(Video or Audio)','easy-form-builder'),
			"zip" => __('Zip','easy-form-builder'),
			"required" => __('Required','easy-form-builder'),
			"button" => __('button','easy-form-builder'),
			"text" => __('text','easy-form-builder'),
			"password" => __('password','easy-form-builder'),
			"email" => __('email','easy-form-builder'),
			"number" => __('number','easy-form-builder'),
			"file" => __('file','easy-form-builder'),
			"date" => __('date','easy-form-builder'),
			"tel" => __('tel','easy-form-builder'),
			"textarea" => __('textarea','easy-form-builder'),
			"checkbox" => __('checkbox','easy-form-builder'),
			"radiobutton" => __('radiobutton','easy-form-builder'),
			"multiselect" => __('multiselect','easy-form-builder'),
			"url" => __('url','easy-form-builder'),
			"range" => __('range','easy-form-builder'),
			"color" => __('color','easy-form-builder'),
			"fileType" => __('File Type','easy-form-builder'),
			"label" => __('Label:*','easy-form-builder'),
			"class" => __('Class','easy-form-builder'),
			"id" => __('ID','easy-form-builder'),
			"tooltip" => __('Tooltip','easy-form-builder'),
			"formUpdated" => __('The Form Updated','easy-form-builder'),
			"goodJob" => __('Good Job','easy-form-builder'),
			"formUpdatedDone" => __('The form has been successfully updated','easy-form-builder'),
			"formIsBuild" => __('form is successfully build','easy-form-builder'),
			"formCode" => __('Form Code','easy-form-builder'),
			"close" => __('Close','easy-form-builder'),
			"done" => __('Done','easy-form-builder'),
			"demo" => __('Demo','easy-form-builder'),
			"alert" => __('Alert!','easy-form-builder'),
			"pleaseFillInRequiredFields" => __('Please fill in all required fields.','easy-form-builder'),
			"availableInProversion" => __('This option is available in Pro version','easy-form-builder'),
			"preview" => __('Preview','easy-form-builder'),
			"somethingWentWrongPleaseRefresh" => __('Something went wrong, Please refresh and try again','easy-form-builder'),
			"formNotCreated" => __('The form has not been created!','easy-form-builder'),
			"atFirstCreateForm" => __('At first create a form and add elemants then try again','easy-form-builder'),

			
			"formNotBuilded" => __('The form has not been builded!','easy-form-builder'),
			"allowMultiselect" => __('Allow multi-select','easy-form-builder'),
			"DragAndDropUI" => __('Drag and drop UI','easy-form-builder'),
			"clickHereForActiveProVesrsion" => __('Click here for Active Pro vesrsion','easy-form-builder'),
			"someStepsNotDefinedCheck" => __('Some steps not defined, Please check:','easy-form-builder'),
			"ifYouNeedCreateMoreThan2Steps" => __('If you need create more than 2 Steps, activeate ','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwo" => __('You can create minmum 1 and maximum 2 Steps.','easy-form-builder'),
			"youCouldCreateMinOneAndMaxtwenty" => __('You Could create minmum 1 Step and maximum 20 Step','easy-form-builder'),
		];
		wp_enqueue_script( 'Emsfb-admin-js', Emsfb_URL . 'includes/admin/assets/js/admin.js' );		
		wp_localize_script('Emsfb-admin-js','efb_var',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1,
			'pro' => $pro,
			'rtl' => is_admin() ,
			'text' => $lang	
					));

			


		 wp_enqueue_script( 'Emsfb-core-js', Emsfb_URL . 'includes/admin/assets/js/core.js' );
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));


	
	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){
	

		$this->userId =get_current_user_id();
		
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';
		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m = __('Something went wrong,Please check all input','easy-form-builder');
			$response = array( 'success' => false , "m"=>$m); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		
		if( isset($_POST['email']) ){
			$email =sanitize_email($_POST['email']);
		}
		
		
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;
		$this->value =  sanitize_text_field($_POST['value']);
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isHTML($_POST['value']) ||$this->isHTML($_POST['type'])){
			$response = array( 'success' => false , "m"=> __("You don't allow to use HTML tag")); 
			wp_send_json_success($response,$_POST);
			die();
		}


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
			'form_type'=>$this->formtype, 			
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