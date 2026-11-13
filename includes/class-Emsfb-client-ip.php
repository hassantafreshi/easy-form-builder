<?php
/**
 * The visitor's address for limits (F11), on top of F13's Privacy::client_ip():
 * REMOTE_ADDR, and a forwarding header only when the request came through a
 * trusted proxy (loopback, private networks, Cloudflare, filter
 * efb_trusted_proxies). There is no second copy of the proxy rules here.
 *
 * limit_key() is what a per-IP counter is keyed with, and it follows the
 * form's IP mode (F13): 'store' = the address, 'anon' = the anonymized address
 * (the visitor's network: /24 for IPv4, /48 for IPv6), 'none' = '' (no per-IP
 * counter at all). The key is hashed again in Rate_Limit, so no address is
 * ever written to the database by a limit.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Client_Ip {

	/** @param array|null $server $_SERVER (tests pass their own). */
	public static function get($server = null) {
		if (!class_exists('\\Emsfb\\Privacy')) require_once __DIR__ . '/class-Emsfb-privacy.php';
		$ip = Privacy::client_ip($server);
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_client_ip', $ip, is_array($server) ? $server : $_SERVER);
			$f = Privacy::clean_ip($f);
			if ($f !== '') $ip = $f;
		}
		return $ip;
	}

	/** The per-IP limit key of a form: '', the anonymized address or the address (F13 IP mode). */
	public static function limit_key($form_id, $ip = null) {
		if (!class_exists('\\Emsfb\\Privacy')) require_once __DIR__ . '/class-Emsfb-privacy.php';
		$mode = Privacy::ip_mode((int) $form_id);
		if ($mode === 'none') return '';
		$ip = $ip === null ? self::get() : (string) $ip;
		if ($ip === '' || $ip === '1.1.1.1') return '';
		return $mode === 'anon' ? Privacy::anonymize_ip($ip) : $ip;
	}
}
