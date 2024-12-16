<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} 


class Panel_edit  {

	public $nounce;
	protected $db;
	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
		
		if ( is_admin() ) {
			$rtl = is_rtl();		
			$plugins =['wpsms' => 0,'wpbaker' => 0,'elemntor'=> 0 , 'cache'=>0];
			$plugins_get = get_plugins();
			
			if (is_plugin_active('wp-sms/wp-sms.php')) {
				
				$plugins['wpsms']=1;
			}
			$plugins_get =null;
			wp_register_script('gchart-js', 'https://www.gstatic.com/charts/loader.js', null, null, true);	
			wp_enqueue_script('gchart-js');
			$img = ["logo" => ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-easy-form-builder.svg',
			"head"=> ''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			"title"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/title.svg',
			"recaptcha"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/reCaptcha.png',
			"emailTemplate1"=>''.EMSFB_PLUGIN_URL . 'public/assets/images/email_template1.png',
			"movebtn"=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/move-button.gif',
			'utilsJs'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/js/utils-efb.js',
			'logoGif'=>''.EMSFB_PLUGIN_URL . 'includes/admin/assets/image/efb-256.gif'
			];
			$pro =false;
			$efbFunction = $this->get_efbFunction();
			//$lng =new lng();		
			$ac= $efbFunction->get_setting_Emsfb();
			$lang = $efbFunction->text_efb(2);
			$smtp =false;
			$captcha =false;
			$maps=false;
			$mdtest = "15f57cc603c2ea64721ae0d0b5983136";
			$addons = ['AdnSPF' => 0,
			'AdnOF' => 0,
			'AdnPPF' => 0,
			'AdnATC' => 0,
			'AdnSS' => 0,
			'AdnCPF' => 0,
			'AdnESZ' => 0,
			'AdnSE' => 0,
			'AdnPDP'=>0,
			'AdnADP'=>0];
			$efbFunction->openstreet_map_required_efb(0);

			if(gettype($ac)!="string" && isset($ac) ){
				$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
				if (isset($ac->activeCode)){$pro= md5($server_name)==$ac->activeCode ? true : false;}
				if(isset($ac->siteKey)){$captcha="true";}	
				if(isset($ac->smtp) && $ac->smtp=="true"){$smtp=1;}else{$smtp_m =$lang["sMTPNotWork"];}
				/* if(isset($ac->apiKeyMap) && strlen($ac->apiKeyMap)>5){
							
					$k= $ac->apiKeyMap;
					$maps =true;
					$lng = strval(get_locale());					
						if ( strlen($lng) > 0 ) {
						$lng = explode( '_', $lng )[0];
						}
					wp_register_script('googleMaps-js', 'https://maps.googleapis.com/maps/api/js?key='.$k.'&#038;language='.$lng.'&#038;libraries=&#038;v=weekly&#038;channel=2', null, null, true);	
					wp_enqueue_script('googleMaps-js');
				} */
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


				$lng = get_locale();
			$k ="";
			if(gettype($ac)!="string" && isset($ac->siteKey))$k= $ac->siteKey;	
			if ( strlen( $lng ) > 0 ) {
				$lng = explode( '_', $lng )[0];
				}

		
				?>
				<style>
					.efb {font-family: 'Roboto', sans-serif!important;}
				</style>
				<!-- 3.8.6 start-->
				<!--sideMenu--> <div class="efb sideMenuFEfb efbDW-0" id="sideMenuFEfb">
				<div class="efb side-menu-efb bg-light bg-gradient border text-dark fade efbDW-0 "  id="sideBoxEfb">
					<div class="efb head sidemenu bg-light bg-gradient py-2 my-1">
					<span> </span>
						<a class="efb BtnSideEfb efb close sidemenu  text-danger ec-efb"  data-eventform='sideMenuEfb' ><i class="efb bi-x-lg" ></i></a>
					</div>
					<div class="efb mb-5 mx-2 sideMenu" id="sideMenuConEfb"></div>
					</div></div>
				<div id="body_emsFormBuilder" class="efb m-2"> 
					<div id="msg_emsFormBuilder" class="efb mx-2">
				</div>
				
				<div class="efb top_circle-efb-1"></div>
				<script>let sitekye_emsFormBuilder="<?php echo $k;  ?>";</script>
					<nav class="efb navbar navbar-expand-lg navbar-light efb" id="navbar">
						<div class="efb container">
							<a class="efb navbar-brand efb" href="admin.php?page=Emsfb_create" >
								<img src="<?php echo EMSFB_PLUGIN_URL.'/includes/admin/assets/image/logo-easy-form-builder.svg' ?>" class="efb logo efb">
								<?php echo esc_html__('Easy Form Builder','easy-form-builder') ?></a>
							<button class="efb navbar-toggler efb" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
								<span class="efb navbar-toggler-icon efb"></span>
							</button>
							<div class="efb collapse navbar-collapse" id="navbarSupportedContent">
								<ul class="efb navbar-nav me-auto mb-2 mb-lg-0">
									<li class="efb nav-item"><a class="efb nav-link efb active ec-efb" data-eventform='forms' id="efb-nav-panel" aria-current="page"  role="button"><?php echo $lang["forms"] ?></a></li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" id="efb-nav-setting" data-eventform='setting'  role="button"><?php echo $lang["setting"] ?></a>
									</li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" href="admin.php?page=Emsfb_create" role="button"><?php echo $lang["create"]  ?></a>
									</li>
									<li class="efb nav-item">
										<a class="efb nav-link efb ec-efb" id="efb-nav-help" data-eventform='help' role="button"><?php echo $lang["help"] ?></a>
									</li>
								</ul>
								<div class="efb d-flex">
									<form class="efb d-flex">
										<i class="efb  bi-search search-icon"></i>
										<input class="efb form-control efb search-form-control efb-rounded efb mx-2" type="search" id="track_code_emsFormBuilder" placeholder="<?php echo $lang["trackNo"]  ?>">
										<a class="efb btn efb btn-outline-pink mx-2 ec-efb" type="submit" id="track_code_btn_emsFormBuilder" data-eventform='searchCC'><?php echo   $lang["search"] ?></a>
									</form>
									<div class="efb nav-icon efb mx-2">
										<a class="efb nav-link efb" href="https://whitestudio.team/login" target="blank"><i class="efb  bi-person"></i></a>
									</div>
									<div class="efb nav-icon efb">
										<a class="efb nav-link efb ec-efb" data-eventform='setting'  role="button"><i class="efb  bi-gear"></i></a>
									</div>
								</div>
							</div>
						</div>
					</nav>
					<div id="alert_efb" class="efb mx-5"></div>
					<!-- end  new nav  -->
						<div class="efb modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
							<div class="efb modal-dialog modal-dialog-centered " id="settingModalEfb_" >
								<div class="efb modal-content efb " id="settingModalEfb-sections">
										<div class="efb modal-header efb"> 
											<h5 class="efb modal-title efb" ><i class="efb bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span></h5>
										<a class="mt-3 mx-3 efb  text-danger position-absolute top-0 <?php echo  is_rtl() ? 'start-0' : 'end-0' ?>" id="settingModalEfb-close" onclick="state_modal_show_efb(0)" role="button"><i class="efb bi-x-lg"></i></a>
										</div>
										<div class="efb modal-body row" id="settingModalEfb-body">
											<div class="efb card-body text-center">
											<?php echo    do_action('efb_loading_card') ?>
										</div></div><!-- settingModalEfb-body-->
						</div></div></div>

						<div class="efb row mb-2">					
						<button type="button" class="efb btn btn-secondary" id="back_emsFormBuilder" onClick="fun_emsFormBuilder_back()" style="display:none;"><i class="efb fa fa-home"></i></button>
						</div>
						<div class="efb row m-0 p-0" id ="content-efb">
						<div class="efb card-body text-center my-5">
							<?php echo    do_action('efb_loading_card'); ?>
						</div>
								
						
						</div>
						<div class="efb mt-3 d-flex justify-content-center align-items-center ">
						<button type="button" id="more_emsFormBuilder" class="efb  btn btn-delete btn-sm" onClick="fun_emsFormBuilder_more()" style="display:none;"><i class="efb bi-chevron-double-down"></i></button>
						</div></div>
						<datalist id="color_list_efb">
							<option value="#0d6efd"><option value="#198754"><option value="#6c757d"><option value="#ff455f"> <option value="#e9c31a"> <option value="#31d2f2"><option value="#FBFBFB"> <option value="#202a8d"> <option value="#898aa9"> <option value="#ff4b93"><option value="#ffff"><option value="#212529"> <option value="#777777">
						</datalist>
						<!-- 3.8.6 end-->
				<?php

				if(isset($ac->efb_version)==false || version_compare(EMSFB_PLUGIN_VERSION,$ac->efb_version)!=0){
					$efbFunction->setting_version_efb_update($ac ,$pro);
				}

				if(is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/smssended")){
					$addons["AdnSS"] =1;
				}

				if(isset($ac->AdnPDP) && $ac->AdnPDP==1){
					//wmaddon
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker")) {	
						$r = $efbFunction->update_message_admin_side_efb();
						echo $r; 
						$efbFunction->download_all_addons_efb();
						return 0;
					}
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/persiadatepicker/persiandate.php");
					$persianDatePicker = new persianDatePickerEFB() ; 
					 	
				}
				if(isset($ac->AdnPDP) && $ac->AdnADP==1){
					if(!is_dir(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker")) {	
						$r = $efbFunction->update_message_admin_side_efb();
						echo $r;
						$efbFunction->download_all_addons_efb();
						
						return 0;
					}	
					require_once(EMSFB_PLUGIN_DIRECTORY."/vendor/arabicdatepicker/arabicdate.php");
					$arabicDatePicker = new arabicDatePickerEfb() ; 				
				}

			}else{$smtp_m =$lang["goToEFBAddEmailM"];}	
			
		
			//$location =$pro==true  ? $efbFunction->get_geolocation() :'';
			//$colors = $efbFunction->get_list_colores_template();
			$colors =[];
			$location ='';
			//efb_code_validate_create( $fid, $type, $status, $tc)
			$sid = $efbFunction->efb_code_validate_create(0, 1, 'admin' , 0);
			$plugins['cache'] = $efbFunction->check_for_active_plugins_cache();
			
			wp_enqueue_script( 'Emsfb-admin-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/admin-efb.js',false,'3.8.9');
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
				"language"=> get_locale(),
				"addons"=>$addons,
				'wp_lan'=>get_locale(),
				'location'=>$location,
				'setting'=>$ac,
				'v_efb'=>EMSFB_PLUGIN_VERSION,
				'colors'=>$colors,
				'sid'=>$sid,
				'rest_url'=>get_rest_url(null),
				'plugins'=>$plugins,
				
			));

			wp_enqueue_script('efb-val-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/val-efb.js',false,'3.8.8');
			 

			wp_enqueue_script('efb-pro-els', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/pro_els-efb.js',false,'3.8.8');
			
			

			$lng_ = get_locale();
			if ( strlen( $lng_ ) > 0 ) {
			$lng_ = explode( '_', $lng_ )[0];
			}


			if("fa_IR"==get_locale()){
				wp_register_script('persia_pay-efb.js',  EMSFB_PLUGIN_URL .'/public/assets/js/persia_pay-efb.js', array('jquery'),'3.8.8' , true);
				wp_enqueue_script('persia_pay-efb.js');
			}
	
			wp_register_script('stripe_js',  EMSFB_PLUGIN_URL .'/public/assets/js/stripe_pay-efb.js', array('jquery'),'3.8.8' , true);
			wp_enqueue_script('stripe_js');
			
		
			 wp_enqueue_script( 'Emsfb-core-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/core-efb.js',false,'3.8.8' );
			 wp_localize_script('Emsfb-core-js','ajax_object_efm_core',array(
					'nonce'=> wp_create_nonce("admin-nonce"),
					'check' => 0
					));
			wp_enqueue_script('efb-bootstrap-select-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/bootstrap-select.min-efb.js',false ,'3.8.8');
			

			wp_enqueue_script('efb-main-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/new-efb.js',false,'3.8.8');
			
			
				/* new code v4 */
			
				wp_register_script('jquery-ui-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-ui-efb.js', array('jquery'),  true,'3.8.8');	
				wp_enqueue_script('jquery-ui-efb');
				wp_register_script('jquery-dd-efb', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/jquery-dd-efb.js', array('jquery'),  true,'3.8.8');	
				wp_enqueue_script('jquery-dd-efb'); 
				/*end new code v4 */

			/* wp_register_script('addsOnLocal-js', 'https://whitestudio.team/wp-json/wl/v1/zone.js'.get_locale().'', null, null, true);	
			wp_enqueue_script('addsOnLocal-js'); */
			/* wp_register_script('addsOnLocal-js', 'https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/js/wp/'.get_locale().'.js', null, null, true);	
			wp_enqueue_script('addsOnLocal-js'); */

			wp_register_script('countries-js', 'https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/js/wp/countries.js', null, null, true);	
			wp_enqueue_script('countries-js');

			
			wp_register_script('intlTelInput-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/intlTelInput.min-efb.js', null, null, true);	
			wp_enqueue_script('intlTelInput-js');

			wp_register_style('intlTelInput-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/intlTelInput.min-efb.css',true,'3.8.8');
			wp_enqueue_style('intlTelInput-css');

			if( false){
				wp_register_script('logic-efb',EMSFB_PLUGIN_URL.'/vendor/logic/assets/js/logic.js', null, null, true);	
				wp_enqueue_script('logic-efb');
			}
			

		
			$value = $efbFunction->efb_list_form();
			$table_name = $this->db->prefix . "emsfb_setting";
			$stng = $this->db->get_results( "SELECT * FROM `$table_name`  ORDER BY id DESC LIMIT 1" );
			

			$lng = get_locale();
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



			wp_register_script('Emsfb-list_form-efb-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/list_form-efb.js', true,'3.8.8');
			wp_enqueue_script('Emsfb-list_form-efb-js');
			wp_localize_script( 'Emsfb-list_form-efb-js', 'ajax_object_efm',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
					'ajax_value' => $value,
					'language' => $lng_,
					'text' => $lang,
					'nonce'=>wp_create_nonce("public-nonce"),
					'user_name'=> wp_get_current_user()->display_name,
					'user_ip'=> $ip,
					'setting'=>$stng,
					'messages_state' =>$this->get_not_read_message(),
					'response_state' =>$this->get_not_read_response(),
					'poster'=> EMSFB_PLUGIN_URL . 'public/assets/images/efb-poster.svg',
					'bootstrap'=>$this->check_temp_is_bootstrap(),
					'pro'=>$pro									
				));

				
				$this->delete_old_rows_emsfb_stts_();
					//smart zone test
					//$this->test_smart_zone();
		}else{
			echo "Easy Form Builder: You don't access this section";
		}
	}

	
	public function get_not_read_message(){
		
		
		$table_name = $this->db->prefix . "emsfb_msg_"; 
		$value = $this->db->get_results( "SELECT msg_id,form_id FROM `$table_name` WHERE read_=0 OR read_=3" );
		return $value;
	}
	public function get_not_read_response(){
		$table_name_msg = $this->db->prefix . "emsfb_msg_";
		$table_name_rsp = $this->db->prefix . "emsfb_rsp_"; 
		//$table_name = $this->db->prefix . "emsfb_rsp_"; 
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
		
        return  $s;
    }//end fun


	public function test_smart_zone (){
		
			     //=>>>>>>>>>>>>>>>>>Temp Remove <<<<<<<<<<<<<<<<<< 
            //test code for create database adsone 
            $fl_ex = EMSFB_PLUGIN_DIRECTORY."/vendor/smartzone/smartzone.php";
            if(file_exists($fl_ex)){
                
                $name ='smartzone';
                $name ='\Emsfb\\'.$name;
                require_once $fl_ex;
                $t = new $name();
                
            }else{}
            //end test 
			
	}

	public function file_upload_api(){
		$efbFunction = $this->get_efbFunction();
		if(empty($this->efbFunction))$this->efbFunction =$efbFunction;
		$_POST['id']=sanitize_text_field($_POST['id']);
        $_POST['pl']=sanitize_text_field($_POST['pl']);
        $_POST['fid']=sanitize_text_field($_POST['fid']);
		$sid = sanitize_text_field($_POST['sid']);
		$s_sid = $this->efbFunction->efb_code_validate_select($sid ,  $_POST['fid']);
		if ($s_sid !=1 || $sid==null){
			
			error_log('s_sid is not valid!! Panel');
			
		$response = array( 'success' => false  , 'm'=>esc_html__('Something went wrong. Please refresh the page and try again.','easy-form-builder') .'<br>'. esc_html__('Error Code','easy-form-builder') . " 403"); 
		wp_send_json_success($response,200);
		} 
        //check validate here
        $vl=null;
        if($_POST['pl']!="msg"){
            $vl ='efb'. $_POST['id'];
        }else{
            $id = $_POST['id'];
            $table_name = $this->db->prefix . "emsfb_form";
            $vl  = $this->db->get_var("SELECT form_structer FROM `$table_name` WHERE form_id = '$id'");
            if($vl!=null){              
                if(strpos($vl , '\"type\":\"dadfile\"') || strpos($vl , '\"type\":\"file\"')){                   
                    $vl ='efb'.$id;
                    //'efb'.$this->id
                }
           
            }
        
        }
		
		$this->text_ = empty($this->text_)==false ? $this->text_ :['error403',"errorMRobot","errorFilePer"];
		
		$this->lanText= $this->efbFunction->text_efb($this->text_);
	
		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,'image/heic',
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' , 'video/mpeg', 'video/mpg', 'audio/mpg','video/mov','video/quicktime',
		 'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'
		);
		

		$_FILES['async-upload']['name'] = sanitize_file_name($_FILES['async-upload']['name']);
		//error_log($_FILES['async-upload']['name']);
		if (in_array($_FILES['async-upload']['type'], $arr_ext)) { 
			// تنظیمات امنیتی بعدا اضافه شود که فایل از مسیر کانت که عمومی هست جابجا شود به مسیر دیگری
						
			$name = 'efb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["async-upload"]["name"], PATHINFO_EXTENSION) ;
			
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["async-upload"]["tmp_name"]));				
			if(is_ssl()==true){
				$upload['url'] = str_replace('http://', 'https://', $upload['url']);
			}
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload ,"name"=>$name ,'type'=>$_FILES['async-upload']['type']); 
			  wp_send_json_success($response,200);
		}else{
			$response = array( 'success' => false  ,'error'=>$this->lanText["errorFilePer"]); 
			wp_send_json_success($response,200);
			die('invalid file '.$_FILES['async-upload']['type']);
		}
		 
	}//end function


	public function delete_old_rows_emsfb_stts_() {		
		$table_name = $this->db->prefix . 'emsfb_stts_';
		$date_limit = date('Y-m-d', strtotime('-40 days'));

		$this->db->query(
			$this->db->prepare(
				"DELETE FROM $table_name WHERE date < %s",
				$date_limit
			)
		);
	}

	public function get_efbFunction(){
		
			if(!class_exists('Emsfb\efbFunction')){
				require_once(EMSFB_PLUGIN_DIRECTORY . 'includes/functions.php');
			}
			return new \Emsfb\efbFunction();
		
	}




}