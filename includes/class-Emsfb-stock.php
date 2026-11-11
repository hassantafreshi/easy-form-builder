<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-option capacity counters for booking forms (F05 sub-task D).
 *
 * The count used to live in the form JSON (`registered_count` on each option
 * row) and every submit rewrote the whole `form_structer`: parallel submits
 * could oversell, a submit that later failed captcha or Shield still used a
 * seat, and the per-request `required`/`disabled` changes made by conditional
 * logic were saved into the form for good.
 *
 * Now the count lives in `{prefix}emsfb_stock_`, one row per
 * (form, field, option). A seat is taken with a single conditional UPDATE
 * (`used = used + 1 ... AND used < cap`), so MySQL serialises concurrent
 * claims on the row and the last seat goes to exactly one request. The row is
 * seeded lazily from the stored `registered_count`, so existing booking forms
 * carry on from their current number without a migration.
 *
 * The submit path calls reserve() right before the entry is written, after
 * every rejection check, and release() when the write fails.
 */
final class Stock {

	const TABLE = 'emsfb_stock_';

	/** @var array<string,array<string,int>> per-request cache of used() */
	private static $used_cache = array();

	private static function db( $db = null ) {
		if ( $db ) {
			return $db;
		}
		global $wpdb;
		return $wpdb;
	}

	public static function table_name( $db = null ) {
		return self::db( $db )->prefix . self::TABLE;
	}

	/**
	 * CREATE statement shared by install() and upgrade_schema().
	 *
	 * @param string $table           Full table name.
	 * @param string $charset_collate $wpdb->get_charset_collate().
	 * @param bool   $if_not_exists   true for the raw query in upgrade_schema();
	 *                                false for dbDelta, which cannot parse it.
	 */
	public static function create_table_sql( $table, $charset_collate, $if_not_exists = false ) {
		$create = $if_not_exists ? 'CREATE TABLE IF NOT EXISTS' : 'CREATE TABLE';
		return "{$create} {$table} (
			`form_id` int(11) NOT NULL,
			`field_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
			`option_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
			`used` int(11) NOT NULL DEFAULT 0,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (`form_id`,`field_id`,`option_id`)
		) {$charset_collate};";
	}

	/**
	 * Build a claim from a stored option row of a booking form.
	 *
	 * Returns null when the option has no capacity (`mlen` absent). An empty or
	 * non-numeric `mlen` counts as 0, i.e. full, which is what the old
	 * `mlen <= registered_count` test did in PHP and in core-efb.js.
	 *
	 * @param array $option Stored option row (`type:"option"`, `id_`, `parent`, `mlen`, `registered_count`, `value`).
	 * @return array|null
	 */
	public static function claim_from_option( $option ) {
		if ( ! is_array( $option ) || ! isset( $option['mlen'] ) || ! isset( $option['id_'] ) ) {
			return null;
		}
		$field_id  = isset( $option['parent'] ) ? (string) $option['parent'] : '';
		$option_id = (string) $option['id_'];
		if ( $option_id === '' ) {
			return null;
		}
		$cap  = is_numeric( $option['mlen'] ) ? max( 0, (int) $option['mlen'] ) : 0;
		$seed = isset( $option['registered_count'] ) && is_numeric( $option['registered_count'] ) ? max( 0, (int) $option['registered_count'] ) : 0;
		return array(
			'field_id'  => substr( $field_id, 0, 40 ),
			'option_id' => substr( $option_id, 0, 40 ),
			'cap'       => $cap,
			'seed'      => $seed,
			'label'     => isset( $option['value'] ) && is_scalar( $option['value'] ) ? (string) $option['value'] : '',
		);
	}

	/**
	 * One claim per option, in a fixed order, so two requests holding several
	 * claims always lock rows in the same order (no deadlock).
	 */
	public static function normalize_claims( array $claims ) {
		$out = array();
		foreach ( $claims as $c ) {
			if ( ! is_array( $c ) || ! isset( $c['field_id'], $c['option_id'], $c['cap'] ) ) {
				continue;
			}
			$out[ $c['field_id'] . '|' . $c['option_id'] ] = $c;
		}
		ksort( $out, SORT_STRING );
		return array_values( $out );
	}

	/**
	 * Atomically take one seat for every claim, or none.
	 *
	 * @param int        $form_id
	 * @param array      $claims From claim_from_option().
	 * @param \wpdb|null $db
	 * @return array{ok:bool,reason?:string,claim?:array,taken:array}
	 *         reason is 'full' (claim holds the option that is full) or
	 *         'unavailable' (the table is missing or a query failed).
	 */
	public static function reserve( $form_id, array $claims, $db = null ) {
		$db      = self::db( $db );
		$form_id = (int) $form_id;
		$claims  = self::normalize_claims( $claims );
		$taken   = array();
		if ( empty( $claims ) ) {
			return array( 'ok' => true, 'taken' => $taken );
		}
		$t   = self::table_name( $db );
		$now = gmdate( 'Y-m-d H:i:s' );

		foreach ( $claims as $c ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$seeded = $db->query( $db->prepare(
				"INSERT IGNORE INTO `{$t}` (form_id, field_id, option_id, used, updated) VALUES (%d, %s, %s, %d, %s)",
				$form_id, $c['field_id'], $c['option_id'], (int) $c['seed'], $now
			) );
			if ( $seeded === false ) {
				self::release( $form_id, $taken, $db );
				return array( 'ok' => false, 'reason' => 'unavailable', 'claim' => $c, 'taken' => array() );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ok = $db->query( $db->prepare(
				"UPDATE `{$t}` SET used = used + 1, updated = %s WHERE form_id = %d AND field_id = %s AND option_id = %s AND used < %d",
				$now, $form_id, $c['field_id'], $c['option_id'], (int) $c['cap']
			) );
			if ( $ok === false ) {
				self::release( $form_id, $taken, $db );
				return array( 'ok' => false, 'reason' => 'unavailable', 'claim' => $c, 'taken' => array() );
			}
			if ( (int) $ok !== 1 ) {
				self::release( $form_id, $taken, $db );
				return array( 'ok' => false, 'reason' => 'full', 'claim' => $c, 'taken' => array() );
			}
			$taken[] = $c;
		}
		unset( self::$used_cache[ $form_id ] );
		return array( 'ok' => true, 'taken' => $taken );
	}

	/**
	 * Give back seats taken by reserve(). Never goes below zero.
	 */
	public static function release( $form_id, array $taken, $db = null ) {
		if ( empty( $taken ) ) {
			return;
		}
		$db  = self::db( $db );
		$t   = self::table_name( $db );
		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $taken as $c ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$db->query( $db->prepare(
				"UPDATE `{$t}` SET used = used - 1, updated = %s WHERE form_id = %d AND field_id = %s AND option_id = %s AND used > 0",
				$now, (int) $form_id, $c['field_id'], $c['option_id']
			) );
		}
		unset( self::$used_cache[ (int) $form_id ] );
	}

	/**
	 * Current counters of a form: "field|option" => used. Empty when the form
	 * has no rows yet or the table is missing.
	 */
	public static function used( $form_id, $db = null ) {
		$form_id = (int) $form_id;
		if ( isset( self::$used_cache[ $form_id ] ) ) {
			return self::$used_cache[ $form_id ];
		}
		$db = self::db( $db );
		$t  = self::table_name( $db );
		$suppress = method_exists( $db, 'suppress_errors' ) ? $db->suppress_errors( true ) : null;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = $db->get_results( $db->prepare( "SELECT field_id, option_id, used FROM `{$t}` WHERE form_id = %d", $form_id ), ARRAY_A );
		if ( $suppress !== null ) {
			$db->suppress_errors( $suppress );
		}
		$map = array();
		foreach ( (array) $rows as $r ) {
			if ( is_array( $r ) && isset( $r['field_id'], $r['option_id'], $r['used'] ) ) {
				$map[ $r['field_id'] . '|' . $r['option_id'] ] = (int) $r['used'];
			}
		}
		return self::$used_cache[ $form_id ] = $map;
	}

	/**
	 * Display only: put the live counter into `registered_count` of the form
	 * JSON sent to the browser, so core-efb.js greys out full options. The
	 * server never trusts this number; reserve() decides.
	 *
	 * @param string $json Form JSON (form_structer).
	 * @return string The same JSON when there is nothing to change.
	 */
	public static function overlay_json( $json, $form_id, $db = null ) {
		if ( ! is_string( $json ) || $json === '' ) {
			return $json;
		}
		$used = self::used( $form_id, $db );
		if ( empty( $used ) ) {
			return $json;
		}
		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) {
			return $json;
		}
		$changed = false;
		foreach ( $rows as $i => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['mlen'], $row['id_'] ) ) {
				continue;
			}
			$key = ( isset( $row['parent'] ) ? (string) $row['parent'] : '' ) . '|' . (string) $row['id_'];
			if ( isset( $used[ $key ] ) ) {
				$rows[ $i ]['registered_count'] = max( (int) ( $row['registered_count'] ?? 0 ), $used[ $key ] );
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return $json;
		}
		$out = json_encode( $rows, JSON_UNESCAPED_UNICODE );
		return is_string( $out ) ? $out : $json;
	}

	/** Remove the counters of a deleted form. */
	public static function forget_form( $form_id, $db = null ) {
		$db = self::db( $db );
		$t  = self::table_name( $db );
		$suppress = method_exists( $db, 'suppress_errors' ) ? $db->suppress_errors( true ) : null;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$db->query( $db->prepare( "DELETE FROM `{$t}` WHERE form_id = %d", (int) $form_id ) );
		if ( $suppress !== null ) {
			$db->suppress_errors( $suppress );
		}
		unset( self::$used_cache[ (int) $form_id ] );
	}
}
