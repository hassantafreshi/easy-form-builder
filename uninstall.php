<?php
/**
 * Runs when Easy Form Builder is deleted from the Plugins screen (not when it is deactivated).
 *
 * Always: the plugin's scheduled events, transients and temporary preview drafts go.
 * Your forms, entries, files and settings stay unless Settings › Privacy › "Delete all data when the plugin is
 * deleted" is on for the site (off by default). On a network each site decides for itself.
 * See includes/class-Emsfb-lifecycle.php.
 *
 * @package EasyFormBuilder
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once __DIR__ . '/includes/class-Emsfb-lifecycle.php';

\Emsfb\Lifecycle::uninstall();
