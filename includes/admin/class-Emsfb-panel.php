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
			$rtl = is_rtl();
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
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
				"Define" => __('Define','easy-form-builder'),
			];
			wp_enqueue_script( 'Emsfb-listicons-js', Emsfb_URL . 'includes/admin/assets/js/listicons.js' );
			wp_enqueue_script('Emsfb-listicons-js');
			$pro =false;
			$ac= $this->get_activeCode_Emsfb();
			if (md5($_SERVER['SERVER_NAME'])==$ac){$pro=true;}
			wp_enqueue_script( 'Emsfb-admin-js', Emsfb_URL . 'includes/admin/assets/js/admin.js' );		
			wp_localize_script('Emsfb-admin-js','efb_var',array(
				'nonce'=> wp_create_nonce("admin-nonce"),
				'pro' => $pro,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang		));

		
			if($pro==true){
				// اگر پولی بود این کد لود شود 
				//پایان کد نسخه پرو
				wp_register_script('whitestudio-admin-pro-js', 'http://whitestudio.team/js/cool.js'.$ac, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');
			}
			
			 wp_enqueue_script( 'Emsfb-core-js', Emsfb_URL . 'includes/admin/assets/js/core.js' );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
						));

			
			$table_name = $this->db->prefix . "Emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date FROM `$table_name`" );
		
			$table_name = $this->db->prefix . "Emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
		

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
					<?php _e('Easy Form Builder','easy-form-builder') ?>
				</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarToggler" aria-controls="navbarToggler" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse" id="navbarToggler">
					<ul class="navbar-nav mr-auto mt-2 mt-lg-0">
					<li class="nav-item">
						<a class="nav-link active" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button"><?php _e('Forms','easy-form-builder') ?><span class="sr-only">(current)</span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><?php _e('Setting','easy-form-builder') ?></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="admin.php?page=Emsfb_create" role="button"><?php _e('Create','easy-form-builder') ?></a>
					</li>
					<li class="nav-item">
						<a class="nav-link " onClick="fun_show_content_page_emsFormBuilder('help')" role="button"><?php _e('help','easy-form-builder') ?></a>
					</li>
					</ul>
					<div class="form-inline my-2 my-lg-0">
					<input class="form-control mr-sm-2" type="search" id="track_code_emsFormBuilder" placeholder="<?php _e('Search track No.','easy-form-builder') ?>">
					<button class="btn btn-outline-success my-2 my-sm-0" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()"><?php _e('Search','easy-form-builder') ?></button>
					</div>
				</div>
			</nav>


					<div class="row mb-2">					
					<button type="button" class="btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="fa fa-home"></i></button>
					</div>
					<div class="row" id ="emsFormBuilder-content">
					 <h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i><?php _e('Loading','easy-form-builder') ?></h2>
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
			wp_localize_script( 'Emsfb-list_form-js', 'ajax_object_efm',
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