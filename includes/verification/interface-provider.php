<?php
/**
 * F02 §6.1: the interface of an SMS sender for one-time codes (provider-neutral).
 *
 * Owner rule: SMS codes go through the WP SMS plugin only. The SMS add-on (AdnSS, vendor/smssended)
 * registers the one shipped sender, a bridge to WP SMS's wp_sms_send()
 * (https://wp-sms-pro.com/resources/wp_sms_send/); the gateway, its credentials and the sender number
 * are set up in WP SMS, never in Easy Form Builder. Core ships no sender. The efb_verify_providers
 * filter stays open so a later add-on can register another sender with this interface.
 *
 * EFB always makes, stores (as an HMAC), limits and checks the code itself: a sender only delivers
 * the text. Rules for a sender: never write the code or the full number into a log or a trace; an
 * error is a short code ('wpsms_failed'); a visitor never sees it.
 */

namespace Emsfb\Verification;

if (!defined('ABSPATH')) {
	exit;
}

interface Provider {

	/** Stable key stored in the settings (e.g. 'wpsms'): [a-z0-9_], at most 20 characters. */
	public function id();

	/** Name shown to the site owner. */
	public function label();

	/** Channels this sender can deliver: a subset of array('sms'). */
	public function channels();

	/** Whether it can send now (e.g. WP SMS is installed and active). */
	public function ready();

	/**
	 * Delivers the message. $msg = array('to' => E.164, 'text' => the full text with the code and the
	 * WebOTP line, 'code' => the digits, 'channel' => 'sms', 'lang' => 'fa_IR').
	 *
	 * @return array array('ok' => bool, 'error' => short code)
	 */
	public function send(array $msg);
}
