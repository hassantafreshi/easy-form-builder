<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class Panel_edit  {

	public $nounce;
	protected $db;
	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		if ( is_admin() ) {
	
			
			wp_enqueue_script( 'Emsfb-listicons-js', Emsfb_URL . 'includes/admin/assets/js/listicons.js' );
			wp_enqueue_script('Emsfb-listicons-js');
			$pro =false;
			$ac= $this->get_activeCode_Emsfb();
			if (md5($_SERVER['SERVER_NAME'])==$ac){$pro=true;}
			wp_enqueue_script( 'Emsfb-admin-js', Emsfb_URL . 'includes/admin/assets/js/admin.js' );		
			wp_localize_script('Emsfb-admin-js','s_var',array(
				'nonce'=> wp_create_nonce("admin-nonce"),
				'pro' => $pro,
				'check' => 0		));

		
			if($pro==true){
				wp_register_script('whitestudio-admin-pro-js', 'http://whitestudio.team/js/cool.js'.$ac, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');
			}
			// اگر پولی بود این کد لود شود 
			//پایان کد نسخه پرو
			//echo ob_get_clean();
			 wp_enqueue_script( 'Emsfb-core-js', Emsfb_URL . 'includes/admin/assets/js/core.js' );
			 wp_localize_script('Emsfb-core-js','ajax_object_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
						));

					wp_enqueue_script( 'ajax331', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js' );
			wp_enqueue_script('ajax331');

			wp_enqueue_script( 'tbootstrap', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0/js/bootstrap.min.js' );
			wp_enqueue_script('tbootstrap');
			//	add_action('wp_ajax_remove_id_Emsfb', array( $this,'delete_form_id_public'));
			//add_action('wp_ajax_remove_id_Emsfb', array( $this,'delete_form_id_public'));
			
			$table_name = $this->db->prefix . "Emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date FROM `$table_name`" );
		
			$table_name = $this->db->prefix . "Emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
			//	print_r($value)	;
		

			$lang = get_locale();
		
			if ( strlen( $lang ) > 0 ) {
				$lang = explode( '_', $lang )[0];
				}

		
			?>
			<div id="body_emsFormBuilder" class="m-2"> 
				<div id="msg_emsFormBuilder" class="mx-2">

				
				</div>
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="#">
					<img src="<?php echo Emsfb_URL.'/includes/admin/assets/image/logo.png' ?>" width="30" height="30" class="d-inline-block align-top" alt="">
					Easy Form Builder
				</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarToggler" aria-controls="navbarToggler" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse" id="navbarToggler">
					<ul class="navbar-nav mr-auto mt-2 mt-lg-0">
					<li class="nav-item">
						<a class="nav-link active" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button">Forms <span class="sr-only">(current)</span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button">Setting</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="admin.php?page=Emsfb_create" role="button">Create</a>
					</li>
					<li class="nav-item">
						<a class="nav-link " onClick="fun_show_content_page_emsFormBuilder('help')" role="button">help</a>
					</li>
					</ul>
					<div class="form-inline my-2 my-lg-0">
					<input class="form-control mr-sm-2" type="search" id="track_code_emsFormBuilder" placeholder="Search track No.">
					<button class="btn btn-outline-success my-2 my-sm-0" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()">Search</button>
					</div>
				</div>
			</nav>


					<div class="row mb-2">					
					<button type="button" class="btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="fa fa-home"></i></button>
					</div>
					<div class="row" id ="emsFormBuilder-content">
					 <h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i>Loading</h2>
					</div>
					<div class="row mt-2 d-flex justify-content-center align-items-center ">
					<button type="button" id="more_emsFormBuilder" class="mat-shadow emsFormBuilder p-3" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="fa fa-angle-double-down"></i></button>
					</div>
				</div>
			<?php

			$ip =0;
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				//check ip from share internet
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				//to check ip is pass from proxy
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}


			

			wp_register_script('Emsfb-list_form-js', Emsfb_URL . 'includes/admin/assets/js/list_form.js', null, null, true);
			wp_enqueue_script('Emsfb-list_form-js');
			wp_localize_script( 'Emsfb-list_form-js', 'ajax_object',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
					'ajax_value' => $value,
					'language' => $lang,
					'ajax_nonce'=>$this->nounce,
					'user_name'=> wp_get_current_user()->display_name,
					'user_ip'=> $ip,
					'setting'=>$stng,
					'messages_state' =>$this->get_not_read_message(),
					'poster'=> Emsfb_URL . 'public/assets/images/efb-poster.png'	
					
				));
					
		}
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


	
	public function get_not_read_message(){
		//error_log('get_not_read_message');
		
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0" );
		$rtrn='null';
		//error_log(json_encode($value));
		return $value;
	}


	


	



}