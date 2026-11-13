<?php
/**
 * F11-E/F: saved answers ("save and continue later") and partial entries.
 * Spec: docs/feature-gaps/F11-form-limits-resume.fa.md §5, §6.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Drafts {

	const TABLE = 'emsfb_draft_';

	/** CREATE TABLE in the dbDelta format of Install (spec "جدول پیش‌نویس"). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fid` int(11) NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `owner_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` bigint(20) unsigned NOT NULL DEFAULT 0,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `step` smallint(5) unsigned NOT NULL DEFAULT 1,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `contact_mask` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `completed_msg_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY token (token_hash),
  KEY owner (fid,kind,owner_hash),
  KEY listing (fid,kind,status,updated_at),
  KEY expires (expires_at)
) {$charset_collate};";
	}
}
