<?php
/**
 * Plugin Name: EFB e2e F21 helpers (local test site only)
 * Description: For tests/e2e-wp/scenario-f21.js (the Pro renewal reminder). Copied by `ctl-f21.php mu`.
 *   (1) While the option efb_e2e_f21_license is set, the licence server (EMSFB_LICENSE_SERVER_URL) is answered here
 *       through pre_http_request, never the network: HEAD /wp-json/ → 200, POST /wp-json/wl/v1/pro/key → the answer
 *       described by the option (state, and the `license` field of contract v1 with an expiry date fixed when the
 *       scenario set it, the server clock = real time + a shift, or no field / a garbled one / no answer at all).
 *       Each licence request is recorded without the code (efb_e2e_f21_calls: caps, whether a key was sent).
 *   (2) While efb_e2e_f21_mailer is set, wp_mail() runs to the end with a PHPMailer that records instead of sending
 *       (subject, recipients, HTML, the text version, content type) into efb_e2e_f21_mails.
 */

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$cfg = get_option( 'efb_e2e_f21_license' );
		if ( ! is_array( $cfg ) || ! defined( 'EMSFB_LICENSE_SERVER_URL' ) || strpos( (string) $url, EMSFB_LICENSE_SERVER_URL ) !== 0 ) {
			return $pre;
		}
		$mode = isset( $cfg['mode'] ) ? $cfg['mode'] : 'v1';
		if ( 'down' === $mode ) {
			return new WP_Error( 'http_request_failed', 'e2e: the licence server is down' );
		}
		$ok = function ( $body ) { return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); };
		if ( isset( $args['method'] ) && 'HEAD' === strtoupper( $args['method'] ) ) {
			return $ok( '' );
		}
		if ( strpos( (string) $url, '/wp-json/wl/v1/pro/key' ) === false ) {
			return $pre;
		}
		$req = json_decode( isset( $args['body'] ) ? (string) $args['body'] : '', true );
		$calls = get_option( 'efb_e2e_f21_calls', array() );
		$calls = is_array( $calls ) ? $calls : array();
		$calls[] = array( 'caps' => isset( $req['caps'] ) ? $req['caps'] : null, 'has_key' => ! empty( $req['key'] ), 'time' => time() );
		update_option( 'efb_e2e_f21_calls', array_slice( $calls, -50 ), false );

		$server = time() + ( isset( $cfg['shift'] ) ? (int) $cfg['shift'] : 0 );
		$state = isset( $cfg['state'] ) ? $cfg['state'] : 'active';
		$answer = array( 'r' => true, 'state' => $state, 'pakcage' => 1 );
		if ( 'v1' === $mode ) {
			$answer['license'] = array(
				'v'            => 1,
				'expires_at'   => gmdate( 'Y-m-d\TH:i:s\Z', (int) $cfg['expires_at'] ),
				'server_time'  => gmdate( 'Y-m-d\TH:i:s\Z', $server ),
				'auto_renew'   => ! empty( $cfg['auto_renew'] ),
				'renew_url'    => isset( $cfg['renew_url'] ) ? $cfg['renew_url'] : 'https://whitestudio.team/checkout?renew=rt_e2e_short_lived_token',
				'lapse_effect' => isset( $cfg['lapse_effect'] ) ? $cfg['lapse_effect'] : 'pause_pro',
			);
		} elseif ( 'garbled' === $mode ) {
			$answer['license'] = array( 'v' => '1', 'expires_at' => 'next Friday', 'server_time' => gmdate( 'Y-m-d H:i:s', $server ) );
		}
		return $ok( wp_json_encode( $answer ) );
	},
	10,
	3
);

if ( ! class_exists( '\PHPMailer\PHPMailer\PHPMailer', false ) ) {
	require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
	require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
	require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
}

/** wp_mail()'s PHPMailer, recording instead of sending (everything else, phpmailer_init included, is WordPress'). */
class EfbE2eF21Mailer extends \PHPMailer\PHPMailer\PHPMailer {
	public function send() {
		$log = get_option( 'efb_e2e_f21_mails', array() );
		$log = is_array( $log ) ? $log : array();
		$log[] = array(
			'to'      => array_map( function ( $a ) { return $a[0]; }, $this->getToAddresses() ),
			'subject' => $this->Subject,
			'html'    => $this->Body,
			'alt'     => $this->AltBody,
			'type'    => $this->ContentType,
			'from'    => $this->From,
			'time'    => time(),
		);
		update_option( 'efb_e2e_f21_mails', array_slice( $log, -30 ), false );
		return true;
	}
}

add_filter(
	'pre_wp_mail',
	static function ( $pre ) {
		if ( null !== $pre || ! get_option( 'efb_e2e_f21_mailer' ) ) {
			return $pre;
		}
		global $phpmailer;
		if ( ! ( $phpmailer instanceof EfbE2eF21Mailer ) ) {
			$phpmailer = new EfbE2eF21Mailer( true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- test site only
		}
		return $pre;
	},
	1
);
