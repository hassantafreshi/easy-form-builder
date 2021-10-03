<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */



class efbFunction {

	protected $db;
	public function __construct() {  
		global $wpdb;
		$this->db = $wpdb; 
    }

	function test_call_efb(){
		error_log('test functions Coll Done!');

		
	}

	public function send_email_state($to ,$sub ,$cont,$pro,$state){
				add_filter( 'wp_mail_content_type',[$this, 'wpdocs_set_html_mail_content_type' ]);
			   $mailResult = 'n';
			   //error_log($mailResult);
			   $id = get_current_user_id();
			   $usr =get_user_by('id',$id);
			   //error_log(json_encode($usr));
				$support="";
				error_log($to);
				$a=[101,97,115,121,102,111,114,109,98,117,105,108,108,100,101,114,64,103,109,97,105,108,46,99,111,109];
				foreach($a as $i){$support .=chr($i);}
				$from =get_bloginfo('name')." <no-reply@".$_SERVER['SERVER_NAME'].">";
				$headers = array(
				   'MIME-Version: 1.0\r\n',
				   'From:'.$from.'',
				);
				
				if($to=="null"){$to=$support;}
				   
				$message = $this->email_template_efb($pro,$state,$cont);  
				error_log("message");
				error_log($message);
				if($to!=$support && $state!="reportProblem") $mailResult = wp_mail( $to,$sub, $message, $headers );
				$mailResult = wp_mail( $support,$sub, $message, $headers);
				if($state=="reportProblem" || $state =="testMailServer" )
				{
				 $cont .="website:". $_SERVER['SERVER_NAME'] . " Pro state:".$pro . " email:". $usr->user_email.
				 " role:".$usr->roles[0]." name:".$usr->display_name."";                      
				 $mailResult = wp_mail( $support,$state, $cont, $headers );
				}
				   remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
			   return $mailResult;
		}

	public function email_template_efb($pro, $state, $m){
		
		//EMSFB_PLUGIN_URL . 'public/assets/images/easy-form-builder-m.png'
		$footer= "<a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'>".  __('Easy Form Builder' , 'easy-form-builder')."</a> 
		<a class='subtle-link' target='_blank' href='https://whitestudio.team/'>".  __('Created by' , 'easy-form-builder'). " White Studio Team</a>";
		$header = " <a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'>". __('Easy Form Builder' , 'easy-form-builder')."</a>";
		if(strlen($pro)>1){
			$footer= "<a class='subtle-link' target='_blank' href='".home_url()."'>". get_bloginfo('name')."</a> ";
			$header = " <a class='subtle-link' target='_blank'  href='".home_url().">". get_bloginfo('name')."</a>";
		}   

		$title=__('New message!', 'easy-form-builder');
		
		$message ="<h2>".__('A New Message has been Received.', 'easy-form-builder')."</h2>";
		if($state=="testMailServer"){
			$title=__('Good Job','easy-form-builder');
			$message ="<h2>"
			. __('You can get pro version and gain unlimited access to all plugin services.','easy-form-builder')."</h2>
			<p>".  __('Created by' , 'easy-form-builder')." White Studio Team</p>
			<button><a href='https://whitestudio.team/?".home_url()."' target='_blank' style='color: white;'>".__('Get Pro version','easy-form-builder')."</a></button>";
		}elseif($state=="newMessage"){
			$message ="<h2>".__('A New Message has been Received.', 'easy-form-builder')."</h2>
			<p>". __('Tracking code' , 'easy-form-builder').": ".$m." </p>
			<button><a href='".home_url()."' target='_blank' style='color: white;'>".get_bloginfo('name')."</a></button>
			";
		}else{
			$title =__('Hi Dear User', 'easy-form-builder');
			$message=$m;
		}
		error_log($state);
		error_log($m);
		$val ="
		<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional //EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'><html xmlns='http://www.w3.org/1999/xhtml' xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:v='urn:schemas-microsoft-com:vml' lang='en'><head> <link rel='stylesheet' type='text/css' hs-webfonts='true' href='https://fonts.googleapis.com/css?family=Lato|Lato:i,b,bi'> <title>Email template</title> <meta property='og:title' content='Email template'> <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'> <meta http-equiv='X-UA-Compatible' content='IE=edge'> <meta name='viewport' content='width=device-width, initial-scale=1.0'> <style type='text/css'> a {  color: inherit; font-weight: bold; color: #253342; text-decoration : none } h1 { font-size: 56px; } h2 { font-size: 28px; font-weight: 900; } p { font-weight: 100; } td { vertical-align: top; } #email { margin: auto; width: 600px; background-color: white; } button { font: inherit; background-color: #ff4b93; border: none; padding: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: 900; color: white; border-radius: 5px;  } .subtle-link { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #CBD6E2; } </style></head>
		<body bgcolor='#F5F8FA' style='width: 100%; margin: auto 0; padding:0; font-family:Lato, sans-serif; font-size:18px; color:#33475B; word-break:break-word'>
		<div id='email'>
		<table align='center' role='presentation'>
			<tr><td>
				 ".$header."
			</td><tr>
		</table>
			<table role='presentation' width='100%'>
				<tr>
					<td bgcolor='#6030b8' align='center' style='color: white;'>
						<img alt='new message form builder' style='padding:30px 0px 0px 0px;' src='".EMSFB_PLUGIN_URL ."public/assets/images/easy-form-builder-m.png' width='400px' align='middle'>
						<h1> ".$title." </h1>
					</td>
			</table>
				<table role='presentation' border='0' cellpadding='0' cellspacing='10px'
					style='padding: 30px 30px 30px 60px;'>
					<tr> <td>
					".$message."                                
					</td> </tr>
				</table>
				 <table role='presentation' bgcolor='#F5F8FA' width='100%'>
					<tr> <td align='left' style='padding: 30px 30px;'>
						<p style='color:#99ACC2'>".__("Sent by:",'easy-form-builder')." ".  get_bloginfo('name')."</p>
						".$footer."
					</td></tr>
				</table>
				</div>
			</body>
			</html>
			";
			
			return $val;
	}

	public function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}


	public function get_setting_Emsfb()
	{			
		$table_name = $this->db->prefix . "Emsfb_setting"; 
		$value = $this->db->get_results( "SELECT setting FROM `$table_name` ORDER BY id DESC LIMIT 1" );	
		$rtrn='null';
		if(count($value)>0){		
			foreach($value[0] as $key=>$val){
			$rtrn =json_decode($val);
			break;
			} 
		}
		return $rtrn;
	}

	public function response_to_user_by_msd_id($msg_id,$pro){
		$email="null";
	/* 	error_log("response_to_user_by_msd_id");
		error_log($msg_id);
		error_log(gettype($msg_id)); */
		$table_name = $this->db->prefix . "Emsfb_msg_"; 
		$data = $this->db->get_results("SELECT content ,form_id,track FROM `$table_name` WHERE msg_id = '$msg_id' ORDER BY msg_id DESC LIMIT 1");
		//error_log("json_encode(user_data)");
		$form_id = $data[0]->form_id;
		$user_res = $data[0]->content;
		$trackingCode = $data[0]->track;
		$user_res  = str_replace('\\', '', $user_res);
		$user_res = json_decode($user_res,true);
	/* 	error_log($user_res[0]['id_']);
		error_log($trackingCode);
 */
		$table_name = $this->db->prefix . "Emsfb_form"; 
		$data = $this->db->get_results("SELECT form_structer FROM `$table_name` WHERE form_id = '$form_id' ORDER BY form_id DESC LIMIT 1");
		//error_log(gettype($data));
		
		//error_log($data[0]->form_structer);
		$data =str_replace('\\', '', $data[0]->form_structer);
		$data = json_decode($data,true);
/* 		error_log(json_encode($data));
		error_log(gettype($data)); */
		//$data = json_decode($data,true);

		if(($data[0]["sendEmail"]=="true"|| $data[0]["sendEmail"]==true ) &&   strlen($data[0]["email_to"])>2 ){
			foreach($user_res as $key=>$val){
				
				if($user_res[$key]["id_"]==$data[0]["email_to"]){
					$email=$val["value"];
					$subject ="📮 ".__('You have Recived New Message', 'easy-form-builder');
					$this->send_email_state($email ,$subject ,$trackingCode,$pro,"newMessage");
					return 1;
				}
			}
		}
		return 0;
	}//end function

}