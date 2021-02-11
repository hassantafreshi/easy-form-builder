<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */
class _Public {
	/**
	 * _Public constructor.
	 */
	
	//public $plugin_url;
	public $value;
	public $id;
	public $ip;
	public $name;
	protected $db;
	//private $wpdb;
	
	public function __construct() {
		
		global $wpdb;
		$this->db = $wpdb;
		
		add_action('wp_enqueue_scripts', array($this,'public_scripts_and_css_head'));
		add_action('wp_ajax_nopriv_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_action('wp_ajax_get_form_Emsfb', array( $this,'get_ajax_form_public'));
		add_shortcode( 'EMS_Form_Builder',  array( $this, 'EMS_Form_Builder' ) ); 
		
		add_action('wp_enqueue_scripts', array($this,'fun_footer'));
		
		add_action('wp_ajax_nopriv_update_file_Emsfb', array( $this,'file_upload_public'));
		add_action('wp_ajax_update_file_Emsfb', array( $this,'file_upload_public'));
		
		
		add_shortcode( 'EMS_Form_Builder_tracking_finder',  array( $this, 'EMS_Form_Builder_track' ) ); 
		add_action('wp_ajax_nopriv_get_track_Emsfb', array( $this,'get_ajax_track_public'));
		add_action('wp_ajax_get_track_Emsfb', array( $this,'get_ajax_track_public'));


		add_action( 'wp_ajax_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		add_action( 'wp_ajax_nopriv_set_rMessage_id_Emsfb',  array($this, 'set_rMessage_id_Emsfb' )); // پاسخ را در دیتابیس ذخیره می کند
		
		
	}

	public function EMS_Form_Builder($id){

		
		
		$table_name = $this->db->prefix . "Emsfb_form";
		
		

		foreach ($id as $row_id){
			//error_log($row_id);
			$this->value = $this->db->get_var( "SELECT form_structer FROM `$table_name` WHERE form_id = '$row_id'" );				
		}
		$this->id = $id;
		//error_log($this->value);

		$lang = get_locale();
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		$state="form";
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			$stng="setting was not added";
			$state="settingError";
			
		}
		
		wp_localize_script( 'core_js', 'ajax_object',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'ajax_value' => $this->value,
			   'state' => $state,
			   'language' => $lang,
			   'id' => $this->id,			  
			   'form_setting' => $stng,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> Emsfb_URL . 'public/assets/images/efb-poster.png'
		 ));  

	 	$content="<div id='body_emsFormBuilder'><h1></h1><div>";
		return $content; 
		
		// 
	}


	public function EMS_Form_Builder_track(){
		//tracking code show
			
		//error_log('EMS_Form_Builder_track');


		$lang = get_locale();
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		$state="tracker";
		$stng= $this->get_setting_Emsfb('pub');
		if(gettype($stng)=="integer" && $stng==0){
			$stng="setting was not added";
			$state="settingError";
		}
		wp_localize_script( 'core_js', 'ajax_object',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ),			
			   'state' => $state,
			   'language' => $lang,			  
			   'form_setting' => $stng,
			   'user_name'=> wp_get_current_user()->display_name,
			   'nonce'=> wp_create_nonce("public-nonce"),
			   'poster'=> Emsfb_URL . 'public/assets/images/efb-poster.png'
		 ));  

	 	$content="<div id='body_tracker_emsFormBuilder'><h1></h1><div>";
		return $content; 

	}
	function public_scripts_and_css_head(){
	
		$lang = get_locale();
		if ( strlen( $lang ) > 0 ) {
		$lang = explode( '_', $lang )[0];
		}
		wp_register_style( 'Emsfb-admin-css',  plugins_url('../public/assets/css/style.css',__FILE__), true );
		wp_enqueue_style( 'Emsfb-admin-css' );
		
		if(is_rtl()){
			wp_register_style( 'Emsfb-css-rtl',  plugins_url('../public/assets/css/style-rtl.css',__FILE__), true);
			wp_enqueue_style( 'Emsfb-css-rtl' );
		}
	
		//source :https://getbootstrap.com/docs/4.6/getting-started/introduction/
		wp_register_style( 'bootstrap4-6-0-css',  plugins_url('../public/assets/css/bootstrapv4-6-0.min.css',__FILE__), true );
		wp_enqueue_style( 'bootstrap4-6-0-css' );

		//source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css
		wp_register_style('Font_Awesome-5', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css');
		wp_enqueue_style('Font_Awesome-5');

		//source : https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css
		wp_register_style('Font_Awesome-4', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
		wp_enqueue_style('Font_Awesome-4');

		//source:https://res.cloudinary.com/dxfq3iotg/raw/upload/v1569006288/BBBootstrap/choices.min.css?version=7.0.0
		wp_register_style( 'choices-css',  plugins_url('../public/assets/css/choices.min.css',__FILE__), true );
		wp_enqueue_style( 'choices-css' );


		//source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.3.0/font-awesome-animation.min.css
		wp_register_style( 'font-awesome-animation-css',  plugins_url('../public/assets/css/font-awesome-animation.min.css',__FILE__), true );
		wp_enqueue_style( 'font-awesome-animation-css' );
	

		//source:https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js
		wp_enqueue_script( 'popper-js', plugins_url('../public/assets/js/popper.min.js',__FILE__), array('jquery'), null, true );
		wp_enqueue_script('popper-js');




		//source:https://res.cloudinary.com/dxfq3iotg/raw/upload/v1569006273/BBBootstrap/choices.min.js?version=7.0.0
		wp_register_script('choices-js', plugins_url('../public/assets/js/choices.min.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('choices-js');

		
		wp_register_script('core_js', plugins_url('../public/assets/js/core.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('core_js');

		//source:https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/css/bootstrap-multiselect.css
		wp_register_style( 'bootstrap-multiselect-css',  plugins_url('../public/assets/css/bootstrap-multiselect.css',__FILE__), true );
		wp_enqueue_style( 'bootstrap-multiselect-css' );
		/* wp_register_style('multiselect', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/0.9.13/css/bootstrap-multiselect.css');
		wp_enqueue_style('multiselect'); */

		
		wp_register_script('recaptcha-code', 'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.5.1.min.js', null, null, true);
		wp_enqueue_script('recaptcha-code'); 
	
		$params = array(
			'hl' => $lang
		  );
		//برای تنظیم زبان من رباط نیستم اینجا باید پارمتر ست بشه
		// نمونه اصلی
		//https://stackoverflow.com/questions/18859857/setting-recaptcha-in-a-different-language-other-than-english
		
		wp_register_script('recaptcha', 'https://www.google.com/recaptcha/api.js', null , null, true);
		wp_enqueue_script('recaptcha');
		
				wp_enqueue_script( 'Emsfb-listicons-js', plugins_url('../public/assets/js/listicons.js',__FILE__), array('jquery'), null, true );
				wp_enqueue_script('Emsfb-listicons-js');


				
				wp_register_script('jquery', plugins_url('../public/assets/js/jquery.js',__FILE__), array('jquery'), null, true);
				wp_enqueue_script('jquery');

		// 'ar' 'az' 'dv' 'he' 'ckb' 'fa' 'ur' 'arc' rtl language 
	  }




	  public function get_ajax_form_public(){

	
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die();
		}
		//recaptcha start
		$r= $this->get_setting_Emsfb('setting');
		
		if(gettype($r)=="object"){
			
		$setting =json_decode($r->setting);
		$secretKey=$setting->secretKey;
		$response=$_POST['valid'];
		
		$args = array(
			'secret'        => $secretKey,
			'response'     => $response,
		);
		
		$verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
		$captcha_success =json_decode($verify['body']);
		
		if ($captcha_success->success==false) {
		// "Error, you are a robot?";
		  $response = array( 'success' => false  , 'm'=>'Error, you are a robot?'); 
		  wp_send_json_success($response,$_POST);
		  die();
		}
		else if ($captcha_success->success==true) {

			if(empty($_POST['value']) || empty($_POST['name']) || empty($_POST['id']) ){
				$response = array( 'success' => false , "m"=>"Please Enter vaild value"); 
				wp_send_json_success($response,$_POST);
				die();
			}
			$this->value = sanitize_text_field($_POST['value']);
			$this->name = sanitize_text_field($_POST['name']);
			$this->id = sanitize_text_field($_POST['id']);
			$this->get_ip_address();

			$ip = $this->ip;
			$check=	$this->insert_message_db();
			

			$r= $this->get_setting_Emsfb('setting');
			$setting =json_decode($r->setting);

			if (strlen($setting->emailSupporter)>0){
			//	error_log($setting->emailSupporter);
				$email = $setting->emailSupporter;
			}
 	
			if($email!= null  && gettype($email)=="string") {$this->send_email_Emsfb($email,$check);}
			$response = array( 'success' => true  ,'ID'=>$_POST['id'] , 'track'=>$check  , 'ip'=>$ip); 
		
			wp_send_json_success($response,$_POST);
		}
		//recaptcha end
	}else{
		$response = array( 'success' => false , "m"=>"Error,Setting is not set"); 
		wp_send_json_success($response,$_POST);
	}



	  }
	  public function get_ajax_track_public(){
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$r= $this->get_setting_Emsfb('setting');		
		if(gettype($r)=="object"){
		 $setting =json_decode($r->setting);
		 $secretKey=$setting->secretKey;
		 $response=$_POST['valid'];
		 
		 $verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$response}" );
		 $captcha_success =json_decode($verify['body']);
		
		 if ($captcha_success->success==false) {
		 // "Error, you are a robot?";
		  $response = array( 'success' => false  , 'm'=>'Error,Are you a robot?'); 
		  wp_send_json_success($response,$_POST);
		 }
		 else if ($captcha_success->success==true) {
		//	 "successful!!";

		if(empty($_POST['value']) ){
			$response = array( 'success' => false , "m"=>"Please Enter vaild value"); 
			wp_send_json_success($response,$_POST);
			die();
		}
		
			$id = sanitize_text_field($_POST['value']);
		
			$this->get_ip_address();
			
			$table_name = $this->db->prefix . "Emsfb_msg_";
			$value = $this->db->get_results( "SELECT content,msg_id,track FROM `$table_name` WHERE track = '$id'" );	
			if($value!=null){
				$id=$value[0]->msg_id;
				$table_name = $this->db->prefix . "Emsfb_rsp_";
				$content = $this->db->get_results( "SELECT content,rsp_by FROM `$table_name` WHERE msg_id = '$id'" );
				///get_current_user_id
				foreach($content as $key=>$val){
					
					$r = (int)$val->rsp_by;
					if ($r>0){
						$usr =get_user_by('id',$r);
						$val->rsp_by= $usr->display_name;
					}else{
						$val->rsp_by="Guest";
					}				 
				}
				$ip = $this->ip;
			}

			if($value!=null){
				
				
				$response = array( 'success' => true  , "value" =>$value[0] , "content"=>$content); 
			}else{
				$response = array( 'success' => false  , "m" =>"Tracking Code not found!"); 
			}
		
			wp_send_json_success($response,$_POST);
			}
		}else{
			$response = array( 'success' => false , "m"=>"Error,Setting is not set"); 
			wp_send_json_success($response,$_POST);
		}

		//recaptcha end


	  }//end function




	  public function fun_footer(){

		
		wp_register_script('jquery', plugins_url('../public/assets/js/jquery.js',__FILE__), array('jquery'), null, true);
		wp_enqueue_script('jquery');
	
	  }//end function



	public function insert_message_db(){
	
		
		
		$uniqid= date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 5) ;
		$table_name = $this->db->prefix . "Emsfb_msg_";
		$this->db->insert($table_name, array(
			'form_title_x' => $this->name, 
			'content' => $this->value, 
			'form_id' => $this->id, 
			'track' => $uniqid, 
			'ip' => $this->ip, 
			'read_' => 0, 
			
		));    return $uniqid; 
	  		
	}//end function

	public function get_ip_address(){
		//source https://www.wpbeginner.com/wp-tutorials/how-to-display-a-users-ip-address-in-wordpress/
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			//check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			//to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
	 $this->ip = $ip;
	 //error_log($ip);
	 return $ip;
	}//end function


	public function file_upload_public(){
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die();
		}
		 $arr_ext = array('image/png', 'image/jpeg', 'image/jpg', 'image/gif' , 'application/pdf','audio/mpeg' ,
		 'audio/wav','audio/ogg','video/mp4','video/webm','video/x-matroska','video/avi' ,'text/plain' ,
		 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/msword',
		 'application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel',
		 'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
		 'application/vnd.ms-powerpoint.presentation.macroEnabled.12','application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		 'application/vnd.oasis.opendocument.spreadsheet','application/vnd.oasis.opendocument.presentation','application/vnd.oasis.opendocument.text',
		 'application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'
		);
		
		if (in_array($_FILES['file']['type'], $arr_ext)) { 
			// تنظیمات امنیتی بعدا اضافه شود که فایل از مسیر کانت که عمومی هست جابجا شود به مسیر دیگری
			//error_log($_FILES["file"]["name"]);			
			$name = 'emsfb-PLG-'. date("ymd"). '-'.substr(str_shuffle("0123456789ASDFGHJKLQWERTYUIOPZXCVBNM"), 0, 8).'.'.pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION) ;
			//error_log($name);
			$upload = wp_upload_bits($name, null, file_get_contents($_FILES["file"]["tmp_name"]));
			//$upload = wp_upload_bits($_FILES["file"]["name"], null, file_get_contents($_FILES["file"]["tmp_name"]));
			//$upload['url'] will gives you uploaded file path
			$response = array( 'success' => true  ,'ID'=>"id" , "file"=>$upload , 'type'=>$_FILES['file']['type']); 
			  wp_send_json_success($response,$_POST);
		}else{
			$response = array( 'success' => false  ,'error'=>"file permissions error"); 
			wp_send_json_success($response,$_POST);
			die('invalid file '.$_FILES['file']['type']);
		}
		
		 
	}//end function

	public function set_rMessage_id_Emsfb(){
		//error_log('test');
		// این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند 
		// با این مضنون که پاسخ شما داده شده است
		if (check_ajax_referer('public-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403M'); 
			wp_send_json_success($response,$_POST);
			die();
		}

		
		if(empty($_POST['message']) ){
			$response = array( 'success' => false , "m"=>"Please Enter vaild value"); 
			wp_send_json_success($response,$_POST);
			die();
		}
		if(empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>"Something went wrong ,Please refresh and try again"); 
			wp_send_json_success($response,$_POST);
			die();
		}
		

		if($this->isHTML($_POST['message'])){
			$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
			wp_send_json_success($response,$_POST);
			die();
		}

		$r= $this->get_setting_Emsfb('setting');
		if(gettype($r)=="object"){
			$setting =json_decode($r->setting);
			$secretKey=$setting->secretKey;
			$email =$setting->emailSupporter ;
			//error_log($email);
			$response=$_POST['valid'];
			$id;
				$id =number_format(sanitize_text_field($_POST['id']));
				$m=sanitize_text_field($_POST['message']);
			
				
				//	$message = preg_replace('/\s+/', '', $m);
				$m = str_replace("\\","",$m);	
				$message =json_decode($m);
				$table_name = $this->db->prefix . "Emsfb_rsp_";
				//echo $table_name;
			
				//	$m = json_encode($m);
				$ip =$this->get_ip_address();
				//error_log($ip);
				//error_log(get_current_user_id());
				$this->db->insert($table_name, array(
					'ip' => $ip, 
					'content' => $m, 
					'msg_id' => $id, 
					'rsp_by' => get_current_user_id(), 
					'read_' => 0
					
				));  
				$table_name = $this->db->prefix . "Emsfb_msg_";
				//error_log($id);
				$this->db->update($table_name,array('read_'=>0),array('msg_id' => $id) );

				$by="Guest";

				if(get_current_user_id()!=0 && get_current_user_id()!==-1){
					$by = get_user_by('id',$r);
				}
				$value = $this->db->get_results( "SELECT track,form_id FROM `$table_name` WHERE msg_id = '$id'" );
				//error_log('track');
				//error_log($id);
				/* error_log('setting->emailSupporter');
				error_log(json_encode($setting));
				error_log($setting->emailSupporter); */
				if (strlen($setting->emailSupporter)>0){
				//	error_log($setting->emailSupporter);
					$email = $setting->emailSupporter;
				}
			
				if($email!= null  && gettype($email)=="string") {$this->send_email_Emsfb($email,$value[0]->track);}

				$response = array( 'success' => true , "m"=>"message sent" , "by"=>$by); 
				wp_send_json_success($response,$_POST);
				
			
			
		}

	}//end function

	public function send_email_Emsfb($to , $track){
	//	error_log("send_email_Emsfb");
		//error_log($to);
   $message ='<!DOCTYPE html> <html> <body><h3>A New Message has been Received ,Track No: ['.$track.']</h3>
   <p>This message is sent by <b>Easy Form Builder</b> plugin from '. home_url().' </p>
   <p> <a href="'.wp_login_url().'">Email Owner: '. home_url().' </a> </body> </html>';
  
  
   $subject ="📮 [".get_bloginfo('name')."] Recived New Response in EFB Plugin";
   $from ="no-replay@".$_SERVER['SERVER_NAME']."";
   //error_log($from);
   $headers = array(
	'MIME-Version: 1.0\r\n',
	'"Content-Type: text/html; charset=ISO-8859-1\r\n"',
    'From:'.$from.''
	);
   $sent = wp_mail($to, $subject, strip_tags($message), $headers);
      if($sent) {
		//error_log("message Sent");
      }//message sent!
      else  {
		//error_log("message wasn't sent");
      }//message wasn't sent
	}

	public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }
	public function get_setting_Emsfb($state)
	{
	// تنظیمات  برای عموم بر می گرداند
	 
	
	 
	 $table_name = $this->db->prefix . "Emsfb_setting";
 
 
	 $value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
 	// error_log(gettype($value));
	$rtrn;
	$siteKey;
	$trackingCode;

	
	if(count($value)>0){
		//error_log('count($value)>0');
		foreach($value[0] as $key=>$val){
		   
		   $r =json_decode($val);
		   $i=0;
   
		   foreach($r as $k=>$v){	
   
			   if($k=="siteKey" && $state=="pub"){				
				   $siteKey=$v;
			   }elseif ($k=="trackingCode" && $state=="pub"){
				   $trackingCode=$v;
			   }
		   }
		   
		} 
   
	   if($state=="pub"){
			
		   $rtr =	array('trackingCode' => ''.$trackingCode.'' , 'siteKey' => ''.$siteKey.'');
		   $rtrn =json_encode($rtr);
	   }else{
		   $rtrn=$value[0];
	   }
	}else{
		$rtrn=0;
	}
	//error_log($rtrn);
	 //return $value[0];
	 return $rtrn;
	}
}

new _Public();