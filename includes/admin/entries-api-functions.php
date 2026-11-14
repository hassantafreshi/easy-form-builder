<?php
/**
 * F14: the global functions of SitePilot's entries.read (docs/feature-gaps/reference/sitepilot-compatibility.en.md
 * §5.5): read-only, as the current user, a WP_Error on refusal. The work is in \Emsfb\Entries.
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('efb_api_entries_query')) {
	function efb_api_entries_query($form_id, $q = array()) { return \Emsfb\Entries::api_query($form_id, $q); }
}
if (!function_exists('efb_api_entry_get')) {
	function efb_api_entry_get($msg_id, $args = array()) { return \Emsfb\Entries::api_get($msg_id, $args); }
}
if (!function_exists('efb_api_entries_stats')) {
	function efb_api_entries_stats($form_id, $args = array()) { return \Emsfb\Entries::api_stats($form_id, $args); }
}
