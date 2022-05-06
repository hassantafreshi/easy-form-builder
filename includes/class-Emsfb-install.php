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

		$table_name_stng = $wpdb->prefix . "Emsfb_setting";
		$table_name = $wpdb->prefix . "Emsfb_form";
		$table_name_msg = $wpdb->prefix . "Emsfb_msg_";
		$table_name_rsp = $wpdb->prefix . "Emsfb_rsp_";

		$charset_collate = $wpdb->get_charset_collate();

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
			`read_` int(1) COLLATE utf8mb4_unicode_ci NOT NULL,
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
			`read_` int(1) COLLATE utf8mb4_unicode_ci NOT NULL, 
			`reader_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
			`rsp_by` int(1) COLLATE utf8mb4_unicode_ci NOT NULL, 
			PRIMARY KEY  (rsp_id)
		) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$it = list_files(get_template_directory());       
        $s = false;
        foreach($it as $path) {
            if (preg_match("/\bbootstrap+.+.css+/i", $path)) 
            {
                $f = file_get_contents($path);
                if(preg_match("/col-md-12/i", $f)){$s= true; break;}
            }
        }
		if($s==true){										
			$v = $wpdb->get_results("SELECT setting FROM  `$table_name_stng` ORDER BY id DESC LIMIT 1");
			if(count($v) == 0){
				$setting ='{\"activeCode\":\"\",\"siteKey\":\"\",\"secretKey\":\"\",\"emailSupporter\":\"\",\"apiKeyMap\":\"\",\"smtp\":\"\",\"bootstrap\":true,\"emailTemp\":\"\"}';
				$s = $wpdb->insert( $table_name_stng, array( 'setting' => $setting, 'edit_by' => get_current_user_id() 
				, 'date'=>current_time('mysql') , 'email'=>'' ));
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $s );			
			}								
		}

		add_option( 'Emsfb_db_version', 1.0 );
	}


	
}