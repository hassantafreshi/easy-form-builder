<?php
/**
 * Atomic counters in one table (F02 §5.3 / §5.4, shared with F11).
 *
 * Every counter is one row of {$prefix}emsfb_vrfy_rl_, keyed by an HMAC of
 * scope|key|slot: the raw key (an IP, an e-mail address, a user id) never
 * reaches the database. Two kinds of use:
 *
 *  - hit():     a rate limit over a fixed time window (draft routes, later F02);
 *  - reserve(): an exact cap (F11 total and per-person limits). It either counts
 *               and answers true, or answers false and leaves the row untouched.
 *
 * Both are one conditional UPDATE (… SET hits = hits + 1 WHERE … AND hits < limit):
 * the row lock of that single statement makes two concurrent requests unable to
 * take the same last place. The SQL is portable on purpose (INSERT IGNORE, a
 * plain conditional UPDATE, no LAST_INSERT_ID(expr)), so it runs on MySQL,
 * MariaDB and the SQLite database integration alike. A failed query answers
 * false (fail-closed).
 *
 * Spec: docs/feature-gaps/F11-form-limits-resume.fa.md (A), F02 §5.3, §5.4.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Rate_Limit {

	const TABLE = 'emsfb_vrfy_rl_';

	/** window_end of a counter that never ends ("ever"). The cleanup never removes it. */
	const NEVER = '9999-12-31 00:00:00';

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	private static $ready = null;

	public static function time() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (F02 §5.3 plus KEY scope for per-form resets). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `bucket` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `window_end` datetime NOT NULL,
  PRIMARY KEY  (bucket),
  KEY window_end (window_end),
  KEY scope (scope)
) {$charset_collate};";
	}

	/** Whether the table exists (cached per request; reset_ready() after creating it). */
	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		$t = self::table();
		self::$ready = is_object($wpdb) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		return self::$ready;
	}

	public static function reset_ready() {
		self::$ready = null;
	}

	/** Key of one purpose, separate from the nonce and form-runtime keys. */
	public static function key($purpose) {
		$salt = function_exists('wp_salt') ? wp_salt('auth') : 'efb';
		return hash_hmac('sha256', 'efb-rl-v1|' . (string) $purpose, $salt);
	}

	/** The row id of a counter. */
	public static function bucket($scope, $key, $slot) {
		return hash_hmac('sha256', (string) $scope . '|' . (string) $key . '|' . (string) $slot, self::key('rl'));
	}

	/** One-way hash of a value that has to be compared later (draft owners, IPs of drafts). */
	public static function hash($purpose, $value) {
		return hash_hmac('sha256', (string) $value, self::key((string) $purpose));
	}

	/**
	 * Rate limit: true while the bucket of this window is under $limit after this hit.
	 * A refused hit is not counted (the conditional UPDATE matched nothing).
	 *
	 * @param string $scope  ≤ 16 characters, e.g. 'dsv' (draft save per IP).
	 * @param string $key    the raw key (hashed here).
	 * @param int    $window seconds.
	 */
	public static function hit($scope, $key, $window, $limit) {
		$limit = (int) $limit;
		if ($limit <= 0) return true;
		$window = max(1, (int) $window);
		$slot = (int) floor(self::time() / $window);
		$end = gmdate('Y-m-d H:i:s', ($slot + 1) * $window + 60);
		return self::reserve($scope, $key, (string) $slot, $end, $limit, 0);
	}

	/**
	 * Exact cap: true and counted, or false and untouched (also false on a database error).
	 * $seed = the starting count of a new bucket (the real number of entries for a total cap).
	 */
	public static function reserve($scope, $key, $slot, $window_end, $limit, $seed = 0) {
		return self::reserve_bucket(self::bucket($scope, $key, $slot), $scope, $window_end, $limit, $seed);
	}

	public static function reserve_bucket($bucket, $scope, $window_end, $limit, $seed = 0) {
		global $wpdb;
		$limit = (int) $limit;
		if ($limit <= 0 || !self::ready()) return false;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$ins = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (bucket, scope, hits, window_end) VALUES (%s, %s, %d, %s)", $bucket, substr((string) $scope, 0, 16), max(0, (int) $seed), (string) $window_end));
		if ($ins === false) return false;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$ok = $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET hits = hits + 1 WHERE bucket = %s AND hits < %d", $bucket, $limit));
		return $ok === 1;
	}

	/** Counts one more without a limit (a payment that was paid after its place had been given back). */
	public static function force_bucket($bucket, $scope, $window_end) {
		global $wpdb;
		if (!self::ready()) return false;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (bucket, scope, hits, window_end) VALUES (%s, %s, %d, %s)", $bucket, substr((string) $scope, 0, 16), 0, (string) $window_end));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET hits = hits + 1 WHERE bucket = %s", $bucket)) === 1;
	}

	/** Gives one place back (never below 0). */
	public static function release($scope, $key, $slot) {
		return self::release_bucket(self::bucket($scope, $key, $slot));
	}

	public static function release_bucket($bucket) {
		global $wpdb;
		if (!self::ready()) return false;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET hits = hits - 1 WHERE bucket = %s AND hits > 0", (string) $bucket)) === 1;
	}

	/** The count of a counter; null when it does not exist yet. */
	public static function peek($scope, $key, $slot) {
		return self::peek_bucket(self::bucket($scope, $key, $slot));
	}

	public static function peek_bucket($bucket) {
		global $wpdb;
		if (!self::ready()) return null;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$v = $wpdb->get_var($wpdb->prepare("SELECT hits FROM `{$t}` WHERE bucket = %s", (string) $bucket));
		return $v === null ? null : (int) $v;
	}

	/** Removes every counter of these scopes (e.g. 'lt:5' after an entry of form 5 was deleted). */
	public static function reset_scopes(array $scopes) {
		global $wpdb;
		$scopes = array_values(array_unique(array_filter(array_map('strval', $scopes), 'strlen')));
		if (!$scopes || !self::ready()) return 0;
		$t = self::table();
		$in = implode(',', array_fill(0, count($scopes), '%s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE scope IN ({$in})", $scopes));
	}

	/** Removes buckets by id (the e-mail counters of a person, on an erasure request). */
	public static function delete_buckets(array $buckets) {
		global $wpdb;
		$buckets = array_values(array_unique(array_filter(array_map('strval', $buckets), 'strlen')));
		if (!$buckets || !self::ready()) return 0;
		$t = self::table();
		$in = implode(',', array_fill(0, count($buckets), '%s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE bucket IN ({$in})", $buckets));
	}

	/**
	 * Daily job: counters whose window ended (UTC) are removed, at most $batch × $rounds per
	 * run. SELECT then DELETE … IN (no DELETE … LIMIT, which SQLite does not take).
	 */
	public static function cleanup($batch = 1000, $rounds = 10) {
		global $wpdb;
		if (!self::ready()) return 0;
		$batch = is_numeric($batch) ? max(1, (int) $batch) : 1000;
		$rounds = is_numeric($rounds) ? max(1, (int) $rounds) : 10;
		$t = self::table();
		$now = gmdate('Y-m-d H:i:s', self::time());
		$done = 0;
		for ($i = 0; $i < $rounds; $i++) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ids = (array) $wpdb->get_col($wpdb->prepare("SELECT bucket FROM `{$t}` WHERE window_end < %s LIMIT %d", $now, $batch));
			if (!$ids) break;
			$done += self::delete_buckets($ids);
			if (count($ids) < $batch) break;
		}
		return $done;
	}
}
