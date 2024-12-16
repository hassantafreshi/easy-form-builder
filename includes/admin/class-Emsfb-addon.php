<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No direct access allow ;)

class Addon {

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
		
		$this->setting_name = 'Emsfb_addon';
		global $wpdb;
		$this->db = $wpdb;
		$this->get_settings();
		
		$this->options = get_option( $this->setting_name );
		

		if ( empty( $this->options ) ) {
			update_option( $this->setting_name, array() );
		}

		add_action( 'admin_menu', array( $this, 'add_addon_menu' ), 11 );

		
	}

	public function add_addon_menu() {
		add_submenu_page( 'Emsfb', esc_html__('Add-ons', 'easy-form-builder' ),'<span style="color:#ff4b93">'. esc_html__('Add-ons', 'easy-form-builder' ) .'</span>', 'Emsfb_addon', 'Emsfb_addon', array(
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
		$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);

		wp_register_script('whiteStudioAddone', 'https://whitestudio.team/wp-json/wl/v1/addons.js' .$server_name, null, null, true);		
        wp_enqueue_script('whiteStudioAddone');
	?>
	
	<!-- new code ddd -->
	<div id="alert_efb" class="efb mx-5"></div>
	<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="efb modal-content efb " id="settingModalEfb-sections">
									<div class="efb modal-header efb"> 
										<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button" role="button"><i class="efb bi-x-lg"></i></a> 
									</div>
									<div class="efb modal-body row" id="settingModalEfb-body">
										<?php echo   do_action('efb_loading_card'); ?>
									</div>
	</div></div></div>
	<div id="tab_container_efb">
			<div class="efb card-body text-center efb">
				<?php echo   do_action('efb_loading_card'); ?>
			</div>	
    </div>
			
	<!-- end new code dd -->
	
			
		<?php


		$pro =false;
		$maps =false;
		$efbFunction = $this->get_efbFunction(); 
		$ac= $efbFunction->get_setting_Emsfb();

		if(gettype($ac)!="string"){			
			if (md5($server_name)==$ac->activeCode){
				$pro=true;
			}				
		}
		
		if(isset($ac->efb_version)==false || version_compare(EMSFB_PLUGIN_VERSION,$ac->efb_version)!=0){			
			$efbFunction->setting_version_efb_update($ac ,$pro);
		}
		//v2 translate
		
		$lang = $efbFunction->text_efb(2);
		



			
			
			wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),'3.8.8', true);	
			wp_enqueue_script('jquery-ui-efb');
			wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),'3.8.8' , true);	
			wp_enqueue_script('jquery-dd-efb'); 
			

		$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
		"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
		"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
		"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
		"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
		'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif',

		];
		
		$smtp =-1;
		$captcha =false;
		$smtp_m = "";
			/*
            AdnSPF == stripe payment
            AdnOF == offline form
            AdnPPF == persia payment
			AdnPDP == persia data picker
			AdnADP == arabic data picker
            AdnATC == advance tracking code
            AdnSS == sms service
            AdnCPF == crypto payment
            AdnESZ == zone picker
            AdnSE == email service

            AdnWHS == webhook
            AdnPAP == paypal
            AdnWSP == whitestudio pay
            AdnSMF == smart form
            AdnPLF == passwordless form
            AdnMSF == membership form
            AdnBEF == booking and event form

			AdnWPB == WP Bakery
			AdnELM == Elemntor 
			AdnGTB == Gutnberg

			AdnPFA == Private Form Advanced
			
        */
		$addons = ['AdnSPF' => 0,
		'AdnOF' => 0,
		'AdnPPF' => 0,
		'AdnATC' => 0,
		'AdnSS' => 0,
		'AdnCPF' => 0,
		'AdnESZ' => 0,
		'AdnSE' => 0,
		'AdnPDP'=>0,
		'AdnADP'=>0
		];
			

		if(gettype($ac)!="string"){
			if( isset($ac->siteKey)&& strlen($ac->siteKey)>5){$captcha="true";}
			if($ac->smtp=="true"){$smtp=1;}else if ($ac->smtp=="false"){$smtp=0;$smtp_m =$lang["sMTPNotWork"];}			
			if(isset($ac->AdnSPF)==true){
				
				$addons["AdnSPF"]=$ac->AdnSPF;
				$addons["AdnOF"]=$ac->AdnOF;
				$addons["AdnATC"]=$ac->AdnATC;
				$addons["AdnPPF"]=$ac->AdnPPF;
				$addons["AdnSS"]=$ac->AdnSS;
				$addons["AdnSPF"]=$ac->AdnSPF;
				$addons["AdnESZ"]=$ac->AdnESZ;
				$addons["AdnSE"]=$ac->AdnSE;
				$addons["AdnPDP"]=isset($ac->AdnPDP) ? $ac->AdnPDP : 0;
				$addons["AdnADP"]=isset($ac->AdnADP) ? $ac->AdnADP : 0;
			
			}
		}else{$smtp_m =$lang["goToEFBAddEmailM"];}


		wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js',false,'3.8.9');
		wp_localize_script('Emsfb-admin-js','efb_var',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 2,
			'pro' => $pro,
			'rtl' => is_rtl() ,
			'text' => $lang	,
			'images' => $img,
			'captcha'=>$captcha,
			'smtp'=>$smtp,
			"smtp_message"=>$smtp,
			'maps'=> $maps,
			'bootstrap' =>$this->check_temp_is_bootstrap(),
			"language"=> get_locale(),
			"addson"=>$addons,
			'wp_lan'=>get_locale(),
			'v_efb'=>EMSFB_PLUGIN_VERSION,
			'setting'=>$ac,
			
		));

		wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js',false,'3.8.8');


		 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js',false,'3.8.8');
		 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
			'nonce'=> wp_create_nonce("admin-nonce"),
			'check' => 1		));

		wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js',false,'3.8.8');
	
	


		


		

	}

	public function fun_Emsfb_creator()
	{
		
	}

	public function add_form_structure(){
		
	
		
		$efbFunction = $this->get_efbFunction(); 
		$creat=["errorCheckInputs","NAllowedscriptTag","formNcreated"];
		$lang = $efbFunction->text_efb($creat);
		$this->userId =get_current_user_id();
	//	
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
		
		$this->formtype =  sanitize_text_field($_POST['type']);
		if($this->isScript($_POST['value']) ||$this->isScript($_POST['type'])){			
			$response = array( 'success' => false , "m"=> $lang["NAllowedscriptTag"]); 
			wp_send_json_success($response,$_POST);
			die();
		}

		
		$this->insert_db();
		if($this->id_ !=0){
			$response = array( 'success' => true ,'r'=>"insert" , 'value' => "[EMS_Form_Builder id=$this->id_]" , "id"=>$this->id_); 
		}else{$response = array( 'success' => false , "m"=> $lang["formNcreated"]);}
		wp_send_json_success($response,$_POST);
		die();		
	}

	public function isScript( $str ) { return preg_match( "/<script.*type=\"(?!text\/x-template).*>(.*)<\/script>/im", $str ) != 0; }
	public function insert_db(){
		$table_name = $this->db->prefix . "emsfb_form";
		$r =$this->db->insert($table_name, array(
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

	public function get_efbFunction(){
			if(!class_exists('Emsfb\efbFunction')){
				require_once(EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php');
			}
			return new \Emsfb\efbFunction();				
	}

}

new Addon();