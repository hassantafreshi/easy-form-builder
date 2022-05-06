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
		add_action('wp_ajax_add_form_Emsfb', array( $this,'add_form_structure'));//ساخت فرم
		
	}

	public function add_Create_menu() {
		add_submenu_page( 'Emsfb', __('Create', 'easy-form-builder' ), __('Create', 'easy-form-builder' ), 'Emsfb_create', 'Emsfb_create', array(
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
	<!--sideMenu--> <div class="sideMenuConEfb efbDW-0" id="sideMenuFEfb">
				<div class="efb side-menu-efb bg-light bg-gradient border border-secondary  text-dark fade efbDW-0" id="sideBoxEfb">
				<div class="efb head sidemenu bg-light bg-gradient py-2 my-1">
				<span> </span>
					<a class="BtnSideEfb efb close sidemenu text-danger" id="BtnCSideEfb" onClick="sideMenuEfb(0)"><i class="bi-x-lg" ></i></a>
				</div>
				<div class="efb mx-3 sideMenu" id="sideMenuConEfb"></div>
				</div></div>
			<script>		
				let bdy =document.getElementsByTagName('body');
				bdy[0].classList.add("bg-color");
				const sitekye_emsFormBuilder= ""
			</script>
			<div id="alert_efb" class="mx-5"></div>
			<div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="modal-content efb " id="settingModalEfb-sections">
									<div class="modal-header efb"> <h5 class="modal-title efb" ><i class="bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
									<div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb"></h3></div></div>
					</div></div></div>
            <div id="tab_container">
           
        	</div>
			<datalist id="color_list_efb">
			<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
			</datalist>
		<?php


		$pro =false;
		$maps =false;
		$efbFunction = new efbFunction(); 
		$ac= $efbFunction->get_setting_Emsfb();
		//v2 translate
		
		$lang = $efbFunction->text_efb(1);
		if(gettype($ac)!="string"){
			$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
			if (md5($server_name)==$ac->activeCode){
				$pro=true;
			}
			if(	$pro==true){
					/* wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac->activeCode, null, null, true);	
					wp_enqueue_script('whitestudio-admin-pro-js'); */
			}

			if( isset($ac->apiKeyMap) && strlen($ac->apiKeyMap)>5){
				$k= $ac->apiKeyMap;
				$maps=true;
				$lng = strval(get_locale());
				//error_log($lng);
					if ( strlen($lng) > 0 ) {
					$lng = explode( '_', $lng )[0];
					}
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
		}
			// اگر نسخه ویژه بود و پلاگین نصب بود این بخش فعال شود
		//stripe77
		wp_register_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);	
		wp_enqueue_script('stripe-js');
		
		wp_register_script('addsOnLocal-js', 'https://whitestudio.team/api/plugin/efb/addson/zone.js'.get_locale().'', null, null, true);	
		wp_enqueue_script('addsOnLocal-js');

		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png'
		];
		
		$smtp =-1;
		$captcha =false;
		$smtp_m = "";
		
		if(gettype($ac)!="string"){
			if( isset($ac->siteKey)&& strlen($ac->siteKey)>5){$captcha="true";}
			if($ac->smtp=="true"){$smtp=1;}else if ($ac->smtp=="false"){$smtp=0;$smtp_m =$lang["sMTPNotWork"];}			
		}else{$smtp_m =$lang["goToEFBAddEmailM"];}

		
		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin.js' );
		wp_localize_script('Emsfb-admin-js','efb_var',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1,
			'pro' => $pro,
			'rtl' => is_rtl() ,
			'text' => $lang	,
			'images' => $img,
			'captcha'=>$captcha,
			'smtp'=>$smtp,
			"smtp_message"=>$smtp,
			'maps'=> $maps,
			'bootstrap' =>$this->check_temp_is_bootstrap(),
			"language"=> get_locale()
			
		));
		wp_enqueue_script('efb-forms-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/forms.js');
		wp_enqueue_script('efb-forms-js');
		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));

		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js');
		wp_enqueue_script('efb-main-js'); 

		wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
		wp_enqueue_script('efb-bootstrap-select-js'); 

	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){

	
		
		$efbFunction = new efbFunction(); 
		$creat=["errorCheckInputs","NAllowedscriptTag","formNcreated"];
		$lang = $efbFunction->text_efb($creat);
		$this->userId =get_current_user_id();
	//	error_log('get_current_user_id');
		// get user email https://developer.wordpress.org/reference/functions/get_user_by/#user-contributed-notes
		$email = '';

		if( empty($_POST['name']) || empty($_POST['value']) ){
			$m =$lang["errorCheckInputs"];
			$response = array( 'success' => false , "m"=>$m); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		
		if(isset($_POST['email']) ){$email =sanitize_email($_POST['email']);}
		$this->id_ ="hid";
		$this->name =  sanitize_text_field($_POST['name']);
		$this->email =  $email;
		$this->value = $_POST['value'];
		//error_log($this->value);
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){			
			$response = array( 'success' => false , "m"=> $lang["NAllowedscriptTag"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		//error_log('$this->insert_db();');
		$this->insert_db();
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_); 
		}else{$response = array( 'success' => false , "m"=> $lang["formNcreated"]);}
		wp_send_json_success($response,$_POST);
		die();		
	}

	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		$table_name = $this->db->prefix . "Emsfb_form";
		$this->db->insert($table_name, array(
			'form_name' => $this->name, 
			'form_structer' => $this->value, 
			'form_email' => $this->email, 
			'form_created_by' => $this->userId, 
			'form_type'=>$this->formtype, 			
		));    $this->id_  = $this->db->insert_id; 
		
	}
	public function check_temp_is_bootstrap (){
        $it = list_files(get_template_directory()); 
        $s = false;
        foreach($it as $path) {
            if (preg_match("/\bbootstrap+.+.css+/i", $path)) 
            {				
                $f = file_get_contents($path);
                if(preg_match("/col-md-12/i", $f)){
                    $s= true;
                    break;
                }
            }
        }
        return  $s;
    }//end fun

}

new Create();