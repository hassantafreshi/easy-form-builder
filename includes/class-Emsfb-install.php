<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class Install
 * @package Emsfb
 */
class Install {
	/**
	 * Creating plugin tables
	 *
	 */
	
	static function install() {
		global $wpdb;
		$state="gi";
		$table_name_stng = $wpdb->prefix . "emsfb_setting";
		$table_name = $wpdb->prefix . "emsfb_form";
		$table_name_msg = $wpdb->prefix . "emsfb_msg_";
		$table_name_rsp = $wpdb->prefix . "emsfb_rsp_";
		$table_name_status = $wpdb->prefix . "emsfb_stts_";

		$charset_collate = $wpdb->get_charset_collate();
		$upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';

		
			
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name_stng} (
			`id` int(1) NOT NULL AUTO_INCREMENT,
			`setting` text COLLATE utf8mb4_unicode_ci NOT NULL, 
			`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
			`edit_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
			`email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
			PRIMARY KEY  (id)

		) {$charset_collate};";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`form_id` int(11) NOT NULL AUTO_INCREMENT,
			`form_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
			`form_structer` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
			`form_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL, 
			`form_type` varchar(15) COLLATE utf8mb4_unicode_ci NULL DEFAULT  'form',
			`form_created_by` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
			`form_access_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL, 
			`form_create_date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,		
			PRIMARY KEY  (form_id)
		) {$charset_collate};";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name_msg} (
			`msg_id` int(11) NOT NULL AUTO_INCREMENT,
			`form_id` int(11) COLLATE utf8mb4_unicode_ci NOT NULL,
			`track` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
			`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`form_title_x` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
			`content` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,		
			`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,		
			`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,		
			`read_` int(10) COLLATE utf8mb4_unicode_ci NOT NULL,
			`read_by` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			PRIMARY KEY  (msg_id)
		) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name_rsp} (
			`rsp_id` int(20) NOT NULL AUTO_INCREMENT,
			`msg_id` int(11) COLLATE utf8mb4_unicode_ci NOT NULL,
			`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`content` text COLLATE utf8mb4_unicode_ci NOT NULL,		
			`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,		
			`read_by` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,		
			`read_` int(10) COLLATE utf8mb4_unicode_ci NOT NULL, 
			`reader_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`rsp_by` int(1) COLLATE utf8mb4_unicode_ci NOT NULL, 
			PRIMARY KEY  (rsp_id)
		) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name_status} (
			`id` int(20) NOT NULL AUTO_INCREMENT,
			`sid` varchar(21) COLLATE utf8mb4_unicode_ci NOT NULL,
			`fid` int(11)   NOT NULL, 
			`type_` int(8)  NOT NULL,
			`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,		
			`status` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
			`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`os` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
			`browser` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,						
			`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,		
			`uid` int(10)  NOT NULL, 
			`tc` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,	
			`active` int(1)   NOT NULL,						
			PRIMARY KEY  (id)
		) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
					
					/* foreach($it as $path) {
						if (preg_match("/\bbootstrap+.+.css+/i", $path)) 
						{
							$f = file_get_contents($path);
							if(preg_match("/col-md-12/i", $f)){$s= true; break;}
						}
					} */
						$user_id = get_current_user_id();
						$usr =get_user_by('id',$user_id);
						$eml=$usr->user_email;
						$s = false; 	
						$v = $wpdb->get_var( "SELECT setting FROM $table_name_stng ORDER BY id DESC LIMIT 1" );
						if($v==NULL && $s==true){
							$setting ='{\"activeCode\":\"\",\"siteKey\":\"\",\"secretKey\":\"\",\"emailSupporter\":\"'.$eml.'\",\"apiKeyMap\":\"\",\"smtp\":\"\",\"bootstrap\":true,\"emailTemp\":\"\"}';
							$s = $wpdb->insert( $table_name_stng, array( 'setting' => $setting, 'edit_by' => get_current_user_id() 
							, 'date'=>current_time('mysql') , 'email'=>'' ));
							require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
							dbDelta( $s );			
						}else if($v==NULL && $s==false){
							$setting ='{\"activeCode\":\"\",\"siteKey\":\"\",\"secretKey\":\"\",\"emailSupporter\":\"'.$eml.'\",\"apiKeyMap\":\"\",\"smtp\":\"\",\"bootstrap\":false,\"emailTemp\":\"\"}';
							$s = $wpdb->insert( $table_name_stng, array( 'setting' => $setting, 'edit_by' => get_current_user_id() 
							, 'date'=>current_time('mysql') , 'email'=>'' ));
							require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
							dbDelta( $s );			
						}else if($v!=NULL ){
							$v_ =str_replace('\\', '', $v);
							

							 function fun_addon_new($url){
								//download the addon dependency 
								$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
								require_once( $path . 'wp-load.php' );
								require_once (ABSPATH .'wp-admin/includes/admin.php');
								
								$name =substr($url,strrpos($url ,"/")+1,-4);
								
								$r =download_url($url);
								if(is_wp_error($r)){
									//show error message
									
								}else{
									$directory = EMSFB_PLUGIN_DIRECTORY . '//temp';
									if (!file_exists($directory)) {
										mkdir($directory, 0755, true);
									}
									$v = rename($r, EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip');
									if(is_wp_error($v)){
										$s = unzip_file($r, EMSFB_PLUGIN_DIRECTORY . '\\vendor\\');
										if(is_wp_error($s)){										
											error_log('EFB=>unzip addons error 1:');
											error_log(json_encode($r));
											return false;
										}
									}else{
										
										require_once(ABSPATH . 'wp-admin/includes/file.php');
										WP_Filesystem();
										$r = unzip_file(EMSFB_PLUGIN_DIRECTORY . '//temp/temp.zip', EMSFB_PLUGIN_DIRECTORY . '//vendor/');
										if(is_wp_error($r)){																															
											error_log('EFB=>unzip addons error 2:');
											error_log(json_encode($r));
											return false;
										}
									} 
									return true;           
								}
							}
							//echo 'Installing Addons of Easy Form Builder';
							$setting = json_decode($v_);
							$adns =['AdnPDP','AdnADP','AdnSS','AdnCPF','AdnESZ','AdnSE','AdnWHS','AdnPAP','AdnWSP','AdnSMF','AdnPLF','AdnMSF','AdnBEF','AdnWPB','AdnELM','AdnGTB','AdnPFA'];
							//if(isset($setting->AdnSPF)==true){
								$s_time =false;
								foreach($adns as $adn){
									if(isset($setting->$adn)!=false && $setting->$adn==true){
										//error_log('check install-'.$adn.'-'. $setting->$adn);
										if($s_time==false){
											$s_time =true;
											set_time_limit(240);
											ignore_user_abort(true);
										}
										$value = $adn;
										// اگر لینک دانلود داشت
										$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
										$vwp = get_bloginfo('version');
										$u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
										$request = wp_remote_get($u);
									
										if( is_wp_error( $request ) ) {
											
											add_action( 'admin_notices', 'admin_notice_msg_efb' );
											
											return false;
										}
										
										$body = wp_remote_retrieve_body( $request );
										$data = json_decode( $body );

										if($data->status==false){
										  continue;
										
										}

										// Check version of EFB to Addons
										if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {        
											continue;                
										} 

										if($data->download==true){
											$url =$data->link;
											
											fun_addon_new($url);
											continue;
										}
									}
								}
							
								


						}
					

		
	

		add_option( 'Emsfb_db_version', 1.0 );
		return $state;
	}


	public function addon_add_efb($value){
		
		$server_name = str_replace("www.", "", $_SERVER['HTTP_HOST']);
		$vwp = get_bloginfo('version');
		$u = 'https://whitestudio.team/wp-json/wl/v1/addons-link/'. $server_name.'/'.$value .'/'.$vwp.'/' ;
		$request = wp_remote_get($u);
	
		if( is_wp_error( $request ) ) {
			
			add_action( 'admin_notices', 'admin_notice_msg_efb' );
			
			return false;
		}
		
		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );

		if($data->status==false){
		return false;
		
		}

		// Check version of EFB to Addons
		if (version_compare(EMSFB_PLUGIN_VERSION,$data->v)==-1) {        
			return false;                
		} 

		if($data->download==true){
			$url =$data->link;
			
			$this->fun_addon_new($url);
			return true;
		}	
}//end function
	



	
}
