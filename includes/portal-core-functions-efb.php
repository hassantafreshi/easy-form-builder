<?php
/**
 * F20-core: the customer portal contract, version 1.x (docs/portal/portal-core-contract.en.md). The portal is the
 * separate, free plugin EFB Customer Portal (efb-customer-portal); these functions are what it builds on, on every plan.
 * All of them are inert on a site without it: they answer efb_portal_unavailable until the plugin answers true on the
 * filter efb_portal_active.
 *
 * Identities ([['k' => 'e'|'p'|'u', 'h' => hash], …]) come only from efb_portal_identity_from_proof(); every read and
 * write checks that the entry belongs to the identity (the owner index), is active (not spam / trash), of a form the
 * plugin's policy puts in the portal (efb_portal_form_policy) and not one that stores nothing (F13 prv_nostore).
 * Refusals are WP_Error with a stable code and data['status'] (efb_portal_not_found for anything not owned).
 *
 * @package Emsfb
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('EMSFB_PORTAL_API')) define('EMSFB_PORTAL_API', 1);
/** The contract's minor version (1.x): what each one adds is in the contract's "Versions" section. */
if (!defined('EMSFB_PORTAL_API_MINOR')) define('EMSFB_PORTAL_API_MINOR', \Emsfb\Portal_Core::API_MINOR);

if (!function_exists('efb_portal_core_api')) {
	/** P1: the contract version (major, minor), its features and refusal codes, the owner index's state; 1.2: channels. */
	function efb_portal_core_api() {
		$st = class_exists('\\Emsfb\\Owner_Index') ? \Emsfb\Owner_Index::state() : array('state' => 'off');
		return array('version' => EMSFB_PORTAL_API, 'minor' => EMSFB_PORTAL_API_MINOR, 'contract' => EMSFB_PORTAL_API . '.' . EMSFB_PORTAL_API_MINOR, 'features' => \Emsfb\Portal_Core::features(), 'errors' => array_keys(\Emsfb\Portal_Core::ERRORS),
			'policy_keys' => array_keys(\Emsfb\Portal_Core::POLICY), 'index' => $st, 'active' => \Emsfb\Portal_Core::active(), 'channels' => \Emsfb\Portal_Core::channels(),
			'hooks' => array('efb_portal_active', 'efb_portal_form_policy', 'efb_portal_booking_api', 'efb_entry_stored', 'efb_entry_reply_added', 'efb_entry_ticket_state', 'efb_entry_cancelled', 'efb_date_released', 'efb_entry_view_url', 'emsfb_cache_shortcodes', 'efb_portal_entry_row', 'efb_addon_setting_keys'));
	}
	/** P5: switches the owner index on and (re)builds it in the background (the add-on on activation, "Rebuild the index"). */
	function efb_portal_reindex($restart = false) { return \Emsfb\Owner_Index::enable((bool) $restart); }
	/** P7: a sign-in code ('email' | 'sms'); ctx: psid, lang, captcha. */
	function efb_portal_send_code($channel, $dest, array $ctx) { return \Emsfb\Portal_Core::send_code((string) $channel, $dest, $ctx); }
	/** P7: checks a sign-in code; ['proof', 'kind'] on success. */
	function efb_portal_check_code($cid, $code, array $ctx) { return \Emsfb\Portal_Core::check_code((string) $cid, (string) $code, $ctx); }
	/** P6: the identity of verified proofs, plus the signed-in user ($uid) and its account e-mail ($wp_email). */
	function efb_portal_identity_from_proof(array $proofs, $uid = 0, $wp_email = true) { return \Emsfb\Portal_Core::identity_from_proof($proofs, (int) $uid, (bool) $wp_email); }
	/** P9: ['rows', 'total', 'page', 'per_page']. */
	function efb_portal_entries(array $identity, array $q = array()) { return \Emsfb\Portal_Core::entries($identity, $q); }
	/** P9: one entry as text to show, its payments, booking and what may be done. */
	function efb_portal_entry($msg_id, array $identity) { return \Emsfb\Portal_Core::entry((int) $msg_id, $identity); }
	/** P10: the conversation of an entry. */
	function efb_portal_thread($msg_id, array $identity) { return \Emsfb\Portal_Core::thread((int) $msg_id, $identity); }
	/** P10: a reply; actor ['kind' => 'owner', 'identity' => …]. */
	function efb_entry_add_reply($msg_id, array $rows, array $actor) { return \Emsfb\Portal_Core::add_reply((int) $msg_id, $rows, $actor); }
	/** P10: a token for forms/file/upload (an attachment of the owner's reply). */
	function efb_portal_reply_upload_token($msg_id, array $identity) { return \Emsfb\Portal_Core::reply_upload_token((int) $msg_id, $identity); }
	/** P11: 'closed' | 'open'; actor ['kind' => 'owner', 'identity' => …] or ['kind' => 'admin']. */
	function efb_entry_set_ticket_state($msg_id, $state, array $actor) { return \Emsfb\Portal_Core::set_ticket_state((int) $msg_id, (string) $state, $actor); }
	/** P12: the payments of an entry (no gateway reference, no payer address). */
	function efb_portal_payments($msg_id, array $identity) { return \Emsfb\Portal_Core::payments((int) $msg_id, $identity); }
	/** P13: a link to the n-th file of an entry, signed for 10 minutes when private. */
	function efb_portal_file_link($msg_id, array $identity, $n) { return \Emsfb\Portal_Core::file_link((int) $msg_id, $identity, (int) $n); }
	/** P14: cancel a booking (the Booking add-on's contract). */
	function efb_entry_owner_cancel($msg_id, array $identity, $reason = '') { return \Emsfb\Portal_Core::owner_cancel((int) $msg_id, $identity, (string) $reason); }
	/** P14: move a booking to another date. */
	function efb_entry_owner_reschedule($msg_id, array $identity, $field_id, $value) { return \Emsfb\Portal_Core::owner_reschedule((int) $msg_id, $identity, (string) $field_id, $value); }
	/** P15: ['fields', 'hash', 'until'] or [] when nothing may be edited now. */
	function efb_entry_owner_editable($msg_id, array $identity) { return \Emsfb\Portal_Core::owner_editable((int) $msg_id, $identity); }
	/** P15: the owner's edit; $changes = [{id_, value}]. */
	function efb_entry_owner_update($msg_id, array $identity, $hash, array $changes) { return \Emsfb\Portal_Core::owner_update((int) $msg_id, $identity, (string) $hash, $changes); }
	/** P16: the identity's unfinished forms. */
	function efb_portal_drafts(array $identity) { return \Emsfb\Portal_Core::drafts($identity); }
	/** P16: a new link to a draft (the earlier one stops working); $page = the form's page URL. */
	function efb_portal_draft_link($draft_id, array $identity, $page = '') { return \Emsfb\Portal_Core::draft_link((int) $draft_id, $identity, (string) $page); }
	/** 1.3: every form with its type, booking, payment and the pages that show it — administrators only. */
	function efb_portal_forms() { return \Emsfb\Portal_Core::forms(); }
	/** §4.10: export | erase request (WordPress' own e-mail confirmation), or a note to the site for a mobile-only identity. */
	function efb_portal_privacy_request(array $identity, $email, $type) { return \Emsfb\Portal_Core::privacy_request($identity, (string) $email, (string) $type); }
}
