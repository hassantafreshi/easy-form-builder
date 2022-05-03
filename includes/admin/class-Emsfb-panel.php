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

			wp_register_script('gchart-js', 'https://www.gstatic.com/charts/loader.js', null, null, true);	
			wp_enqueue_script('gchart-js');
			$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
			"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
			"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/recaptcha.png',
			"emailTemplate1"=>''.EMSFB_PLUGIN_URL . 'public/assets/images/email_template1.png'
			];
			$pro =false;
			$efbFunction = new efbFunction(); 
			//$lng =new lng();		
			$ac= $efbFunction->get_setting_Emsfb();
			$lang = $efbFunction->text_efb(1);
			
			/* error_log(gettype($ac));
			error_log($ac->activeCode); */
			$smtp =false;
			$captcha =false;
			$maps=false;
			$mdtest = "15f57cc603c2ea64721ae0d0b5983136";

			if(gettype($ac)!="string" && isset($ac) ){
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				if (isset($ac->activeCode)){$pro= md5($server_name)==$ac->activeCode ? true : false;}
				if(isset($ac->siteKey)){$captcha="true";}	
				if(isset($ac->smtp) && $ac->smtp!="false"){$smtp=$ac->smtp;}else{$smtp_m =$lang["sMTPNotWork"];}	
				if(isset($ac->apiKeyMap)){				
					$k= $ac->apiKeyMap;
					$maps =true;
					$lng = strval(get_locale());					
						if ( strlen($lng) > 0 ) {
						$lng = explode( '_', $lng )[0];
						}
					wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
					wp_enqueue_script('googleMaps-js');
				}
			}else{$smtp_m =$lang["goToEFBAddEmailM"];}	
			
			
			wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin.js' );
			wp_localize_script('Emsfb-admin-js','efb_var',array(
				'nonce'=> wp_create_nonce("admin-nonce"),
				'pro' => $pro,
				'check' => 0,
				'rtl' => $rtl,
				'text' => $lang,
				'images' => $img,
				'captcha'=>$captcha,
				'smtp'=>$smtp,
				'maps'=> $maps,
				'bootstrap' =>$this->check_temp_is_bootstrap(),
				"language"=> get_locale()		));

		
			if($pro==true){
				// اگر پولی بود این کد لود شود 
				//پایان کد نسخه پرو
				wp_register_script('whitestudio-admin-pro-js', 'https://whitestudio.team/js/cool.js'.$ac->activeCode, null, null, true);	
				wp_enqueue_script('whitestudio-admin-pro-js');


			}
			
			//اگر پلاگین مربوط نصب بود این بخش فعال شود
			//stipe
			//اگر نسه پرو بود
			wp_register_script('stripe-js', 'https://js.stripe.com/v3/', null, null, true);	
			wp_enqueue_script('stripe-js');

			if(gettype($ac)!="string" && isset($ac->apiKeyMap)){
				$k= $ac->apiKeyMap;
				$lng = get_locale();
					if ( strlen( $lng ) > 0 ) {
					$lng = explode( '_', $lng )[0];
					}
				//error_log($lang);
				wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.';language='.$lng.'libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
				wp_enqueue_script('googleMaps-js');
			}
			
			 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core.js' );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
						));
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min.js');
			wp_enqueue_script('efb-bootstrap-select-js'); 

			wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new.js');
			wp_enqueue_script('efb-main-js'); 

			wp_register_script('addsOnLocal-js', 'https://whitestudio.team/api/plugin/efb/addson/zone.js'.get_locale().'', null, null, true);	
			wp_enqueue_script('addsOnLocal-js');

			
			$table_name = $this->db->prefix . "Emsfb_form";
			$value = $this->db->get_results( "SELECT form_id,form_name,form_create_date,form_type FROM `$table_name`" );
		
			$table_name = $this->db->prefix . "Emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
			

			$lng = get_locale();
			$k ="";
			if(gettype($ac)!="string" && isset($ac->siteKey))$k= $ac->siteKey;	
			if ( strlen( $lng ) > 0 ) {
				$lng = explode( '_', $lng )[0];
				}

		
			?>
			<!--sideMenu--> <div class="sideMenuFEfb efbDW-0" id="sideMenuFEfb">
			<div class="efb side-menu-efb bg-light bg-gradient border border-secondary  text-dark fade efbDW-0"  id="sideBoxEfb">
				<div class="efb head sidemenu bg-light bg-gradient py-2 my-1">
				<span> </span>
					<a class="BtnSideEfb efb close sidemenu  text-danger" onClick="sideMenuEfb(0)" ><i class="bi-x-lg" ></i></a>
				</div>
				<div class="efb mx-3 sideMenu" id="sideMenuConEfb"></div>
				</div></div>
			<div id="body_emsFormBuilder" class="m-2"> 
				<div id="msg_emsFormBuilder" class="mx-2">
			</div>
			<div class="top_circle-efb-2"></div>
			<div class="top_circle-efb-1"></div>
			<script>let sitekye_emsFormBuilder="<?php echo $k;  ?>" </script>
				<nav class="navbar navbar-expand-lg navbar-light efb" id="navbar">
					<div class="container">
						<a class="navbar-brand efb" href="admin.php?page=Emsfb_create" >
							<img src="<?php echo EMSFB_PLUGIN_URL.'/includes/admin/assets/image/logo-easy-form-builder.svg' ?>" class="logo efb">
							<?= __('Easy Form Builder','easy-form-builder') ?></a>
						<button class="navbar-toggler efb" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
							<span class="navbar-toggler-icon efb"></span>
						</button>
						<div class="collapse navbar-collapse" id="navbarSupportedContent">
							<ul class="navbar-nav me-auto mb-2 mb-lg-0">
								<li class="nav-item"><a class="nav-link efb active" aria-current="page" onClick="fun_show_content_page_emsFormBuilder('forms')" role="button"><?= $lang["forms"] ?></a></li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><?= $lang["setting"] ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" href="admin.php?page=Emsfb_create" role="button"><?= $lang["create"]  ?></a>
								</li>
								<li class="nav-item">
									<a class="nav-link efb" onClick="fun_show_content_page_emsFormBuilder('help')" role="button"><?= $lang["help"] ?></a>
								</li>
							</ul>
							<div class="d-flex">
								<form class="d-flex">
									<i class="efb bi-search search-icon"></i>
									<input class="form-control efb search-form-control efb-rounded efb mx-2" type="search" type="search" id="track_code_emsFormBuilder" placeholder="<?=$lang["entrTrkngNo"]  ?>">
									<button class="btn efb btn-outline-pink mx-2" type="submit" id="track_code_btn_emsFormBuilder" onClick="fun_find_track_emsFormBuilder()"><?=  $lang["search"] ?></button>
								</form>
								<div class="nav-icon efb mx-2">
									<a class="nav-link efb" href="https://whitestudio.team/login" target="blank"><i class="efb bi-person"></i></a>
								</div>
								<div class="nav-icon efb">
									<a class="nav-link efb"  onClick="fun_show_content_page_emsFormBuilder('setting')" role="button"><i class="efb bi-gear"></i></a>
								</div>
							</div>
						</div>
					</div>
				</nav>
				<div id="alert_efb" class="mx-5"></div>
				<!-- end  new nav  -->
					<div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
						<div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
							<div class="modal-content efb " id="settingModalEfb-sections">
									<div class="modal-header efb"> <h5 class="modal-title efb" ><i class="bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5></div>
									<div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb"></h3></div></div>
					</div></div></div>

					<div class="row mb-2">					
					<button type="button" class="btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="fa fa-home"></i></button>
					</div>
					<div class="row" id ="content-efb">
				 	<div class="card-body text-center my-5"><div class="lds-hourglass"></div> <h3 class="efb"><?=  $lang["loading"] ?></h3></div>
					<!--  <h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i><?=  $lang["loading"] ?></h2> -->
					</div>
					<div class="mt-3 d-flex justify-content-center align-items-center ">
					<button type="button" id="more_emsFormBuilder" class="efb btn btn-delete btn-sm" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="bi-chevron-double-down"></i></button>
					</div></div>
					<datalist id="color_list_efb">
						<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
					</datalist>
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



			wp_register_script('Emsfb-list_form-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/list_form.js', null, null, true);
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
					'response_state' =>$this->get_not_read_response(),
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
					'bootstrap'=>$this->check_temp_is_bootstrap(),
					'pro'=>$pro
					
				));

				;
					
		}else{
			echo "Easy Form Builder: You dont access this section";
		}
	}

	
	public function get_not_read_message(){
		//error_log('get_not_read_message');
		
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0" );

		//error_log(json_encode($value));
		return $value;
	}
	public function get_not_read_response(){
		$table_name_msg = $this->db->prefix . "Emsfb_msg_";
		$table_name_rsp = $this->db->prefix . "Emsfb_rsp_"; 
		//$table_name = $this->db->prefix . "Emsfb_rsp_"; 
		$value = $this->db->get_results( "SELECT t.msg_id, t.form_id
		FROM `$table_name_msg` AS t 
		 INNER JOIN `$table_name_rsp` AS tr 
		 ON t.msg_id = tr.msg_id AND tr.read_ = 0" );
		return $value;
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
		//error_log($s);
        return  $s;
    }//end fun




}