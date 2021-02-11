<?php

namespace Emsfb;

/**
 * Class Admin
 * @package Emsfb
 */
class Admin {
	/**
	 * Admin constructor.
	 */
	public $ip;
	public $plugin_version;
	protected $db;
	//private $wpdb;
	public function __construct() {
		$this->init_hooks();
		global $wpdb;
		$this->db = $wpdb;
	}

	/**
	 * Initial plugin
	 */
	private function init_hooks() {
		// Check exists require function
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		// Add plugin caps to admin role
		if ( is_admin() and is_super_admin() ) {
			$this->add_cap();
		}

		// Actions.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		$this->ip =$this->get_ip_address();
		
		//$current_user->display_name
		if ( is_admin()) {
			// برای نوشتن انواع اکشن مربوط به حذف و اضافه اینجا انجام شود


			if( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_data = get_plugin_data( EMSFB_PLUGIN_FILE );
			$this->plugin_version = $plugin_data['Version'];
		
			//$this->get_not_read_message();
			add_action( 'wp_ajax_remove_id_Emsfb',  array($this, 'delete_form_id_public' )); //یک فرم بر اساس ي دی حذف می کند
			add_action( 'wp_ajax_get_form_id_Emsfb',  array($this, 'get_form_id_Emsfb' )); // اطلاعات یک فرم را بر اساسا آی دی بر می گرداند
			add_action( 'wp_ajax_get_messages_id_Emsfb',  array($this, 'get_messages_id_Emsfb' )); // اطلاعات یک مسیج را بر می گرداند بر اساس ای دی
			add_action( 'wp_ajax_get_all_response_id_Emsfb',  array($this, 'get_all_response_id_Emsfb' )); // اطلاعات یک مسیج را بر می گرداند بر اساس ای دی
			add_action( 'wp_ajax_update_form_Emsfb',  array($this, 'update_form_id_Emsfb' )); //فرم را بروز رسانی می کند
			add_action( 'wp_ajax_update_message_state_Emsfb',  array($this, 'update_message_state_Emsfb' )); // وضععیت پیام را بروز رسانی می کند وضعیت خوانده شدن
			add_action( 'wp_ajax_set_replyMessage_id_Emsfb',  array($this, 'set_replyMessage_id_Emsfb' )); // پاسخ ادمین را در دیتابیس ذخیره می کند
			add_action( 'wp_ajax_set_setting_Emsfb',  array($this, 'set_setting_Emsfb' )); // پاسخ ادمین را در دیتابیس ذخیره می کند
			add_action('wp_ajax_get_track_id_Emsfb', array( $this,'get_ajax_track_admin'));//ردیف ترکینگ را بر می گرداند
			add_action('wp_ajax_clear_garbeg_Emsfb', array( $this,'clear_garbeg_admin'));//فایل های غیر ضروری را پاک می کند


		
			
	

		}
	}

	/**
	 * Adding new capability in the plugin
	 */
	public function add_cap() {
		// Get administrator role
		$role = get_role( 'administrator' );

		$role->add_cap( 'Emsfb' );		
		$role->add_cap( 'Emsfb_create' );
		$role->add_cap( 'Emsfb_panel' );
	
	}


	public function admin_assets( $hook ) {
		
		if(is_admin()){
		//notifcation new version
		wp_register_script('whiteStudioMessage', 'http://whitestudio.team/js/message.js'.$this->plugin_version, null, null, true);
		wp_enqueue_script('whiteStudioMessage');

		//source : https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css
		wp_register_style( 'bootstrap4-6-0-css',  plugins_url('../../public/assets/css/bootstrapv4-6-0.min.css',__FILE__), true );
		wp_enqueue_style( 'bootstrap4-6-0-css' );
		
			
		wp_enqueue_script('serverJs',  Emsfb_URL . 'includes/admin/assets/js/server.js' , null, null, true);
		wp_localize_script('serverJs','ajax_s_esmf',array(
			'CurrentVersion'=>$this->plugin_version,
			'LeastVersion' => '3.33',
			'check' => 0));
		}

		// if page is edit_forms_Emsfb
		if(strpos($hook, 'Emsfb') && is_admin()){
	
			if(is_rtl()){
				//error_log('is_rtl');
				wp_register_style( 'Emsfb-css-rtl',  Emsfb_URL . 'includes/admin/assets/css/admin-rtl.css', true);
				wp_enqueue_style( 'Emsfb-css-rtl' );
			}
			
			
			wp_register_style( 'Emsfb-admin-css', Emsfb_URL . 'includes/admin/assets/css/admin.css', true );
			wp_enqueue_style( 'Emsfb-admin-css' );

	
			$lang = get_locale();
			if ( strlen( $lang ) > 0 ) {
			$lang = explode( '_', $lang )[0];
			}

			$ac= $this->get_activeCode_Emsfb();
		
			

			//source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css
			wp_register_style('Font_Awesome-5', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css');
			wp_enqueue_style('Font_Awesome-5');

			//source : https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css
			wp_register_style('Font_Awesome-4', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
			wp_enqueue_style('Font_Awesome-4');

			//source:https://cdnjs.cloudflare.com/ajax/libs/font-awesome-animation/0.3.0/font-awesome-animation.min.css
			wp_register_style( 'font-awesome-animation-css',  plugins_url('../../public/assets/css/font-awesome-animation.min.css',__FILE__), true );
			wp_enqueue_style( 'font-awesome-animation-css' );

	
			//source :https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js
			wp_enqueue_script( 'popper-js', Emsfb_URL . 'includes/admin/assets/js/popper.min.js' );
			wp_enqueue_script('popper-js');	
		
		}

		

	
	}

	/**
	 * Register admin menu
	 */
	public function admin_menu() {
		$noti_count =count($this->get_not_read_message());
		/* error_log("noti_count");
		error_log($noti_count); */
		$icon = Emsfb_URL.'/includes/admin/assets/image/logo-gray.png';
		add_menu_page( 
			__( 'Panel', 'Emsfb' )
			,$noti_count ? sprintf( __( 'Easy Form Builder', 'Emsfb' ).' <span class="awaiting-mod">%d</span>', $noti_count ) : __( 'Easy Form Builder', 'Emsfb' ),
			 'Emsfb',
			'Emsfb',
			'',
			''.$icon.''
		);
		add_submenu_page( 'Emsfb', __( 'Panel', 'Emsfb' ), __( 'Panel', 'Emsfb' ), 'Emsfb', 'Emsfb', array( $this, 'panel_callback' ) );
		//
	
	}

	/**
	 * Callback outbox page.
	 */
	public function panel_callback() {
		include_once Emsfb_ABSPATH . "includes/admin/class-Emsfb-panel.php";
		$list_table = new Panel_edit();
		

		

	}
	

	public function delete_form_id_public(){

		if (check_ajax_referer('admin-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die("secure!");
		}
		
		if( empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		$id =number_format($_POST['id']);		
		
		$table_name = $this->db->prefix . "Emsfb_form";
		$r = $this->db->delete($table_name,
					[ 'form_id' => $id],
					[ '%d' ] );

		$response = array( 'success' => true ,'r'=>$r); 
		wp_send_json_success($response,$_POST);
   }
	public function update_form_id_Emsfb(){

		if (check_ajax_referer('admin-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die("secure!");
		}

		if(empty($_POST['value']) || empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>"Invalid require,Please Check every thing"); 
			wp_send_json_success($response,$_POST);
			die();
		} 

		if($this->isHTML(json_encode($_POST['value']))){
			$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
			wp_send_json_success($response,$_POST);
			die();
		}
		$id =number_format($_POST['id']);		
		$value =sanitize_text_field($_POST['value']);
		
		$table_name = $this->db->prefix . "Emsfb_form";
		$r = $this->db->update($table_name,array( 'form_structer' => $value),array('form_id'=>$id) );

		$response = array( 'success' => true ,'r'=>"update", 'value'=>"[EMS_Form_Builder id=$id]"); 
		wp_send_json_success($response,$_POST);
   }
	public function update_message_state_Emsfb(){
		if (check_ajax_referer('admin-nonce','nonce')!=1){
			//error_log('not valid nonce');
			$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
			wp_send_json_success($response,$_POST);
			die("secure!");
		}
		if( empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		if( empty($_POST['value']) ){
			$response = array( 'success' => false , "m"=>"Please Enter the value"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		//error_log('json_encode($ _POST[value])');
		//error_log(json_encode($_POST['value']));
		if($_POST['value']){
			if ($this->isHTML(json_encode($_POST['value']))){
				$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
				wp_send_json_success($response,$_POST);
				die();
			} 
		}
		$id =number_format($_POST['id']);		
		
		$table_name = $this->db->prefix . "Emsfb_msg_";
		$r = $this->db->update($table_name,array( 'read_' => 1 , 'read_by'=>get_current_user_id(), 'read_date'=>current_time('mysql')),array('msg_id'=>$id) );

		$response = array( 'success' => true ,'r'=>"update"); 
		wp_send_json_success($response,$_POST);
   }

   public function get_form_id_Emsfb(){
	if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
	}
	if( empty($_POST['id']) ){
		$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
		wp_send_json_success($response,$_POST);
		die();
	} 
		$id =number_format($_POST['id']);
		
		$table_name = $this->db->prefix . "Emsfb_form";
		$value = $this->db->get_var( "SELECT form_structer FROM `$table_name` WHERE form_id = '$id'" );	
		
		$response = array( 'success' => true ,'ajax_value' => $value , 'id'=> $id); 
		wp_send_json_success($response,$_POST);	

   }
   public function get_messages_id_Emsfb(){
	if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
	}
	if( empty($_POST['id']) ){
		$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
		wp_send_json_success($response,$_POST);
		die();
	} 
	   
		$id =number_format($_POST['id']);
		
		$table_name = $this->db->prefix . "Emsfb_msg_";
		$value = $this->db->get_results( "SELECT * FROM `$table_name` WHERE form_id = '$id' ORDER BY `$table_name`.date DESC" );	
		$response = array( 'success' => true ,'ajax_value' => $value , 'id'=> $id); 
		wp_send_json_success($response,$_POST);	
   }
   public function get_all_response_id_Emsfb(){
	if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
	}
	if( empty($_POST['id']) ){
		$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
		wp_send_json_success($response,$_POST);
		die();
	} 
	   
		$id =number_format($_POST['id']);
		
		$table_name = $this->db->prefix . "Emsfb_rsp_";
		$value = $this->db->get_results( "SELECT * FROM `$table_name` WHERE msg_id = '$id'" );	
		$this->db->update($table_name,array( 'read_' => 1),array('msg_id'=>$id , 'read_'=>0) );
		foreach($value as $key=>$val){				
			$r = (int)$val->rsp_by;
			if ($r>0){
				$usr =get_user_by('id',$r);
				$val->rsp_by= $usr->display_name;
			}else{
				$val->rsp_by="Guest";
			}				 
		}
		

		$response = array( 'success' => true ,'ajax_value' => $value , 'id'=> $id); 
		wp_send_json_success($response,$_POST);	
   }

   public function set_replyMessage_id_Emsfb(){
	   // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند 
	   // با این مضنون که پاسخ شما داده شده است

	   if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
		}
		if( empty($_POST['id']) ){
			$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		if( empty($_POST['message']) ){
			$response = array( 'success' => false , "m"=>"Something went wrong,Please refresh the page and Enter value"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
		

		if($this->isHTML(json_encode($_POST['message']))){
			$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
			wp_send_json_success($response,$_POST);
			die();
		}
	$id =number_format($_POST['id']);
	$m=sanitize_text_field($_POST['message']);
	

	$table_name = $this->db->prefix . "Emsfb_rsp_";
	//echo $table_name;
 
	
    $ip =$this->ip;
	$this->db->insert($table_name, array(
		'ip' => $ip, 
		'content' => $m, 
		'msg_id' => $id, 
		'rsp_by' => get_current_user_id(), 
		'read_' => 0
		
	));    


	$response = array( 'success' => true , "m"=>"message sent"); 
	wp_send_json_success($response,$_POST);	
   }
   public function set_setting_Emsfb(){
	   // این تابع بعلاوه به اضافه کردن مقدار به دیتابیس باید یک ایمیل هم به کاربر ارسال کند 
	   // با این مضنون که پاسخ شما داده شده است
	   if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
		}

		if( empty($_POST['message']) ){
			$response = array( 'success' => false , "m"=>"Please enter a message"); 
			wp_send_json_success($response,$_POST);
			die();
		} 
	if($this->isHTML(json_encode($_POST['message']))){
		$response = array( 'success' => false , "m"=> "You don't allow to use HTML tag"); 
		wp_send_json_success($response,$_POST);
		die();
	}
	$m=$_POST['message'];
	
	$setting =sanitize_text_field(json_encode($_POST['message']));
	$table_name = $this->db->prefix . "Emsfb_setting";
	$email ;
	foreach($m as $key=>$value){
		if($key =="emailSupporter"){
			$email = $value;
		}
		if($key =="activeCode" && strlen($value)>1){
	
			if(md5($_SERVER['SERVER_NAME'])!=$value){
				$response = array( 'success' => false , "m"=>"Your activation code is not Correct!"); 
				wp_send_json_success($response,$_POST);	
				die();
			}else{
				// یک رکوست سمت سرور ارسال شود که بررسی کند کد وجود دارد یا نه
			}

		}
	  }
	//echo $table_name;
	$this->db->insert($table_name, array(
		'setting'   => $setting,
		'edit_by'   => get_current_user_id(),
		'date'    	=> current_time('mysql'),
		'email'		=> $email
	)); 

	
	

	$response = array( 'success' => true , "m"=>"message sent"); 
	wp_send_json_success($response,$_POST);	
   }

   public function get_ajax_track_admin(){
	//اطلاعات ردیف ترک را بر می گرداند
	if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
	}
	//error_log('get_track_id_Emsfb');
	  	 
	  $table_name = $this->db->prefix . "Emsfb_msg_";
	  $id = sanitize_text_field($_POST['value']);
	  $value = $this->db->get_results( "SELECT * FROM `$table_name` WHERE track = '$id'" );
	/* 	error_log('get_ajax_track_admin');
		error_log($value[0]->track); */

	  if($value[0]!=null){
		$response = array( 'success' => true  , "ajax_value" =>$value ); 
		}else{
			$response = array( 'success' => false  , "m" =>"Tracking Code not found!"); 
		}

	wp_send_json_success($response,$_POST);




}//end function

   public function clear_garbeg_admin(){
	//پاک کردن فایل های اضافی
	if (check_ajax_referer('admin-nonce','nonce')!=1){
		//error_log('not valid nonce');
		$response = array( 'success' => false  , 'm'=>'Secure Error 403'); 
		wp_send_json_success($response,$_POST);
		die("secure!");
	}
	
	
	//error_log('clear_garbeg_admin');
		 
	$table_name = $this->db->prefix . "Emsfb_msg_";
	$value = $this->db->get_results( "SELECT content FROM `$table_name`" );
	$urlsDB =[];
	foreach ($value as $v){
		if(strpos($v->content,'url')!=false){
			$jsn = $v->content;		
			$jsn =str_replace('\\', '', $jsn);
			$json =json_decode($jsn);
		/* 	error_log($jsn);
			error_log(gettype($json)); */
			foreach($json as $keyR=>$row){
				foreach($row as $key =>$val){
					//error_log(json_encode($val));
	 				if($key=="url" && $val!="" && gettype($val)=='string'  ){
						/* error_log($key);
						error_log($val); */
						array_push($urlsDB,$val);
					}
				}
			}
			
		}
	}
	//error_log(json_encode($urlsDB));
	$upload_dir   = wp_upload_dir();
	//error_log($upload_dir['basedir']);
	//$arrayFiles=[] ;
	$files = list_files($upload_dir['basedir']);
	$urlDBStr= json_encode($urlsDB);
	foreach($files as &$file){
		if(strpos($file,'emsfb-PLG-')!=false){
			$namfile = strrchr($file, '/');
			if(strpos($urlDBStr,$namfile)==false){
			 //array_push($arrayFiles,$file);
			 wp_delete_file($file);
			}

		}
	}
	//error_log('*******************************************'); 
	//error_log(json_encode($arrayFiles));

	$response = array( 'success' => true  , "m" =>"Files Deleted" ); 

	wp_send_json_success($response,$_POST);




}//end function





public function isHTML( $str ) { return preg_match( "/\/[a-z]*>/i", $str ) != 0; }

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
  	return $ip;
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
		return $value;
	}

}

new Admin();
