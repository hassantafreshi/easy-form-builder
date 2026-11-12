<?php
/**
 * PAY00: storage of the payment framework (docs/payments/PAY00-payment-framework.fa.md §6).
 *
 * Four tables (DB version 1.5): emsfb_pmt_ (payments, amounts in the smallest currency unit),
 * emsfb_pmt_evt_ (raw event log; UNIQUE (gateway, event_key) drops duplicate webhooks),
 * emsfb_pmt_rfd_ (refunds) and emsfb_coupon_ (coupons, used from phase 2 on).
 *
 * Every status change is one conditional UPDATE (… WHERE status IN (allowed sources)), so two
 * requests that race (webhook, return URL, cron) can never both move a payment, and finalize()
 * is guarded the same way by finalized_at IS NULL. All SQL is prepared; times are UTC.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Store {

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	/** to => statuses it may come from (docs §6.2). */
	const TRANSITIONS = array(
		'created'            => array( 'pending', 'failed' ),
		// pending/authorized → refunded: the provider refunded the whole payment before EFB saw it paid.
		'pending'            => array( 'authorized', 'paid', 'failed', 'canceled', 'expired', 'refunded' ),
		'authorized'         => array( 'paid', 'canceled', 'expired', 'failed', 'refunded' ),
		'paid'               => array( 'refunded', 'partially_refunded' ),
		'failed'             => array( 'paid' ),
		'canceled'           => array( 'paid' ),
		'expired'            => array( 'paid' ),
		'partially_refunded' => array( 'refunded', 'partially_refunded' ),
		'refunded'           => array(),
	);

	const STATUSES = array( 'created', 'pending', 'authorized', 'paid', 'failed', 'canceled', 'expired', 'partially_refunded', 'refunded' );

	/** Columns update() may write besides status (never id, uid, form_id, amount). */
	const WRITABLE = array( 'msg_id', 'track', 'gateway_ref', 'gateway_sub_ref', 'method', 'payer_email', 'payer_name', 'ip',
		'expires_at', 'paid_at', 'last_checked_at', 'check_count', 'refunded_minor', 'next_json', 'locale', 'return_to', 'quote' );

	public static function time() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function now( $offset = 0 ) {
		return gmdate( 'Y-m-d H:i:s', self::time() + (int) $offset );
	}

	public static function table( $short ) {
		global $wpdb;
		return $wpdb->prefix . $short;
	}

	/**
	 * The CREATE TABLE statements (dbDelta format: two spaces after PRIMARY KEY, one KEY per line).
	 * Used by Install::install() and by the idempotent 1.5 upgrade step.
	 *
	 * @return array<string,string> short table name => SQL
	 */
	public static function ddl( $prefix, $charset_collate = '' ) {
		return array(
			'emsfb_pmt_' => "CREATE TABLE {$prefix}emsfb_pmt_ (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uid char(22) NOT NULL,
  form_id int(11) unsigned NOT NULL DEFAULT 0,
  field_id varchar(40) NOT NULL DEFAULT '',
  msg_id bigint(20) unsigned NULL,
  track varchar(20) NOT NULL DEFAULT '',
  gateway varchar(20) NOT NULL,
  mode varchar(4) NOT NULL DEFAULT 'test',
  kind varchar(16) NOT NULL DEFAULT 'one_time',
  parent_id bigint(20) unsigned NULL,
  status varchar(20) NOT NULL DEFAULT 'created',
  amount_minor bigint(20) NOT NULL,
  currency char(3) NOT NULL,
  refunded_minor bigint(20) NOT NULL DEFAULT 0,
  quote mediumtext NULL,
  coupon_code varchar(40) NOT NULL DEFAULT '',
  attempt_key char(36) NOT NULL,
  idem_key char(64) NOT NULL,
  gateway_ref varchar(100) NOT NULL DEFAULT '',
  gateway_sub_ref varchar(100) NOT NULL DEFAULT '',
  method varchar(40) NOT NULL DEFAULT '',
  payer_email varchar(190) NOT NULL DEFAULT '',
  payer_name varchar(190) NOT NULL DEFAULT '',
  return_to varchar(500) NOT NULL DEFAULT '',
  locale varchar(20) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  next_json mediumtext NULL,
  expires_at datetime NULL,
  paid_at datetime NULL,
  finalized_at datetime NULL,
  last_checked_at datetime NULL,
  check_count smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_uid (uid),
  UNIQUE KEY uq_attempt (form_id,attempt_key),
  KEY idx_gw_ref (gateway,gateway_ref),
  KEY idx_status (status,last_checked_at),
  KEY idx_form (form_id,created_at),
  KEY idx_msg (msg_id),
  KEY idx_track (track)
) {$charset_collate};",
			'emsfb_pmt_evt_' => "CREATE TABLE {$prefix}emsfb_pmt_evt_ (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  payment_id bigint(20) unsigned NULL,
  gateway varchar(20) NOT NULL,
  source varchar(12) NOT NULL,
  event_key char(64) NOT NULL,
  type varchar(60) NOT NULL DEFAULT '',
  verified tinyint(1) NOT NULL DEFAULT 0,
  from_status varchar(20) NOT NULL DEFAULT '',
  to_status varchar(20) NOT NULL DEFAULT '',
  http_code smallint(6) NOT NULL DEFAULT 0,
  payload mediumtext NULL,
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_event (gateway,event_key),
  KEY idx_payment (payment_id,id)
) {$charset_collate};",
			'emsfb_pmt_rfd_' => "CREATE TABLE {$prefix}emsfb_pmt_rfd_ (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  payment_id bigint(20) unsigned NOT NULL,
  amount_minor bigint(20) NOT NULL,
  currency char(3) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  gateway_ref varchar(100) NOT NULL DEFAULT '',
  reason varchar(255) NOT NULL DEFAULT '',
  idem_key char(64) NOT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_idem (idem_key),
  KEY idx_payment (payment_id)
) {$charset_collate};",
			'emsfb_coupon_' => "CREATE TABLE {$prefix}emsfb_coupon_ (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(40) NOT NULL,
  kind varchar(8) NOT NULL,
  value bigint(20) NOT NULL,
  currency char(3) NOT NULL DEFAULT '',
  forms varchar(500) NOT NULL DEFAULT '',
  min_total bigint(20) NOT NULL DEFAULT 0,
  max_uses int(11) unsigned NOT NULL DEFAULT 0,
  used int(11) unsigned NOT NULL DEFAULT 0,
  per_email tinyint(1) NOT NULL DEFAULT 0,
  applies_to varchar(10) NOT NULL DEFAULT 'first',
  starts_at datetime NULL,
  ends_at datetime NULL,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_code (code)
) {$charset_collate};",
		);
	}

	/** Whether the payments table exists (cached per request). */
	public static function ready() {
		static $ready = null;
		if ( $ready === true ) return true;
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		$ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
		return $ready;
	}

	/** 22 characters of base62 from 17 random bytes (136 bits): the public id in return URLs. */
	public static function new_uid() {
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$bytes = random_bytes( 22 );
		$out = '';
		for ( $i = 0; $i < 22; $i++ ) {
			$out .= $alphabet[ ord( $bytes[ $i ] ) % 62 ];
		}
		return $out;
	}

	/** The statuses a payment may move from to reach $to. */
	public static function sources_of( $to ) {
		$from = array();
		foreach ( self::TRANSITIONS as $src => $targets ) {
			if ( in_array( $to, $targets, true ) ) $from[] = $src;
		}
		return $from;
	}

	public static function can_move( $from, $to ) {
		return isset( self::TRANSITIONS[ $from ] ) && in_array( $to, self::TRANSITIONS[ $from ], true );
	}

	public static function is_final( $status ) {
		return in_array( $status, array( 'paid', 'failed', 'canceled', 'expired', 'refunded' ), true );
	}

	/* ------------------------------------------------------------------ rows */

	/**
	 * @param array $data form_id, field_id, gateway, mode, amount_minor, currency, attempt_key, …
	 * @return array|null The stored row, or null when the attempt already exists / insert failed.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now = self::now();
		$row = array_merge( array(
			'uid'            => self::new_uid(),
			'form_id'        => 0,
			'field_id'       => '',
			'track'          => '',
			'gateway'        => '',
			'mode'           => 'test',
			'kind'           => 'one_time',
			'status'         => 'created',
			'amount_minor'   => 0,
			'currency'       => '',
			'refunded_minor' => 0,
			'quote'          => '',
			'coupon_code'    => '',
			'attempt_key'    => '',
			'gateway_ref'    => '',
			'method'         => '',
			'payer_email'    => '',
			'payer_name'     => '',
			'return_to'      => '',
			'locale'         => '',
			'ip'             => '',
			'check_count'    => 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		), $data );
		$row['mode'] = $row['mode'] === 'live' ? 'live' : 'test';
		$row['currency'] = strtoupper( substr( (string) $row['currency'], 0, 3 ) );
		$row['amount_minor'] = (int) $row['amount_minor'];
		if ( empty( $row['idem_key'] ) ) {
			$site = function_exists( 'home_url' ) ? home_url() : '';
			$row['idem_key'] = hash( 'sha256', $site . '|' . $row['uid'] . '|' . $row['attempt_key'] );
		}
		if ( $row['status'] !== 'created' ) $row['status'] = 'created';
		foreach ( $row as $k => $v ) {
			if ( $v === null ) unset( $row[ $k ] );
		}
		$ok = $wpdb->insert( self::table( 'emsfb_pmt_' ), $row );
		if ( $ok === false || ! $wpdb->insert_id ) return null;
		return self::get( (int) $wpdb->insert_id );
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", (int) $id ), ARRAY_A );
		return $r ? self::cast( $r ) : null;
	}

	public static function find_by_uid( $uid ) {
		global $wpdb;
		if ( ! is_string( $uid ) || ! preg_match( '/^[0-9A-Za-z]{22}$/', $uid ) ) return null;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE uid = %s", $uid ), ARRAY_A );
		return $r ? self::cast( $r ) : null;
	}

	public static function find_by_ref( $gateway, $ref ) {
		global $wpdb;
		if ( ! is_string( $ref ) || $ref === '' ) return null;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE gateway = %s AND gateway_ref = %s ORDER BY id DESC LIMIT 1", (string) $gateway, $ref ), ARRAY_A );
		return $r ? self::cast( $r ) : null;
	}

	public static function find_by_attempt( $form_id, $attempt_key ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE form_id = %d AND attempt_key = %s", (int) $form_id, (string) $attempt_key ), ARRAY_A );
		return $r ? self::cast( $r ) : null;
	}

	/** Another payment of the same entry that was already finalized (paid once), or null. */
	public static function finalized_sibling( $msg_id, $not_id ) {
		global $wpdb;
		if ( ! $msg_id ) return null;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE msg_id = %d AND id <> %d AND finalized_at IS NOT NULL ORDER BY id ASC LIMIT 1", (int) $msg_id, (int) $not_id ), ARRAY_A );
		return $r ? self::cast( $r ) : null;
	}

	/** How many payment attempts an entry has. */
	public static function count_by_msg( $msg_id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE msg_id = %d", (int) $msg_id ) );
	}

	private static function cast( array $r ) {
		foreach ( array( 'id', 'form_id', 'msg_id', 'parent_id', 'amount_minor', 'refunded_minor', 'check_count' ) as $k ) {
			if ( array_key_exists( $k, $r ) && $r[ $k ] !== null ) $r[ $k ] = (int) $r[ $k ];
		}
		return $r;
	}

	/** Writes the allowed columns (never status). @return bool */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$data = array();
		foreach ( $fields as $k => $v ) {
			if ( in_array( $k, self::WRITABLE, true ) ) $data[ $k ] = $v;
		}
		if ( empty( $data ) ) return false;
		$data['updated_at'] = self::now();
		return false !== $wpdb->update( self::table( 'emsfb_pmt_' ), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Moves a payment to $to when its current status allows it (one conditional UPDATE).
	 *
	 * @return bool true when this call made the change; false when it was already done,
	 *              not allowed, or another request won the race.
	 */
	public static function transition( $id, $to, array $fields = array() ) {
		global $wpdb;
		$from = self::sources_of( $to );
		if ( empty( $from ) ) return false;
		$t = self::table( 'emsfb_pmt_' );
		$set = array( 'status = %s', 'updated_at = %s' );
		$args = array( $to, self::now() );
		foreach ( $fields as $k => $v ) {
			if ( ! in_array( $k, self::WRITABLE, true ) ) continue;
			if ( $v === null ) { $set[] = "`{$k}` = NULL"; continue; }
			$set[] = "`{$k}` = " . ( is_int( $v ) ? '%d' : '%s' );
			$args[] = $v;
		}
		$in = implode( ',', array_fill( 0, count( $from ), '%s' ) );
		$args[] = (int) $id;
		$args = array_merge( $args, $from );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- table from $wpdb->prefix; columns from the WRITABLE allow-list
		$n = $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET " . implode( ', ', $set ) . " WHERE id = %d AND status IN ({$in})", $args ) );
		return (int) $n === 1;
	}

	/** The "only once" lock of finalize(): true for exactly one caller of a paid payment. */
	public static function claim_finalize( $id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET finalized_at = %s, updated_at = %s WHERE id = %d AND finalized_at IS NULL AND status = %s", self::now(), self::now(), (int) $id, 'paid' ) );
		return (int) $n === 1;
	}

	/** Records a check (return, cron, poll) for the reconcile back-off. */
	public static function touch_checked( $id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET last_checked_at = %s, check_count = check_count + 1 WHERE id = %d", self::now(), (int) $id ) );
	}

	/* ------------------------------------------------------------------ events */

	/**
	 * Inserts an event once per (gateway, event_key): INSERT IGNORE on uq_event.
	 *
	 * @return bool true when this call stored it (first delivery), false for a duplicate.
	 */
	public static function claim_event( $gateway, $event_key, array $data = array() ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_evt_' );
		$row = self::event_row( $gateway, $event_key, $data );
		$cols = array_keys( $row );
		$ph = array();
		foreach ( $row as $v ) $ph[] = is_int( $v ) ? '%d' : '%s';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix; columns from event_row()
		$n = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$t}` (`" . implode( '`,`', $cols ) . '`) VALUES (' . implode( ',', $ph ) . ')', array_values( $row ) ) );
		return (int) $n === 1;
	}

	/** An event that is never a duplicate (admin action, create, transition note). */
	public static function log_event( $payment_id, $gateway, $source, array $data = array() ) {
		$data['payment_id'] = $payment_id;
		$data['source'] = $source;
		return self::claim_event( $gateway, hash( 'sha256', 'log|' . microtime( true ) . '|' . bin2hex( random_bytes( 8 ) ) ), $data );
	}

	private static function event_row( $gateway, $event_key, array $data ) {
		$payload = isset( $data['payload'] ) ? $data['payload'] : '';
		if ( ! is_string( $payload ) ) $payload = wp_json_encode_efb_pay( Redactor::redact( $payload ) );
		if ( strlen( $payload ) > 65536 ) $payload = substr( $payload, 0, 65536 );
		$row = array(
			'gateway'     => substr( (string) $gateway, 0, 20 ),
			'source'      => substr( isset( $data['source'] ) ? (string) $data['source'] : 'webhook', 0, 12 ),
			'event_key'   => strlen( (string) $event_key ) === 64 ? (string) $event_key : hash( 'sha256', (string) $gateway . '|' . (string) $event_key ),
			'type'        => substr( isset( $data['type'] ) ? (string) $data['type'] : '', 0, 60 ),
			'verified'    => ! empty( $data['verified'] ) ? 1 : 0,
			'from_status' => isset( $data['from_status'] ) ? (string) $data['from_status'] : '',
			'to_status'   => isset( $data['to_status'] ) ? (string) $data['to_status'] : '',
			'http_code'   => isset( $data['http_code'] ) ? (int) $data['http_code'] : 0,
			'payload'     => $payload,
			'note'        => substr( isset( $data['note'] ) ? (string) $data['note'] : '', 0, 255 ),
			'created_at'  => self::now(),
		);
		if ( isset( $data['payment_id'] ) && $data['payment_id'] ) $row['payment_id'] = (int) $data['payment_id'];
		return $row;
	}

	/**
	 * Frees an event key again (the delivery could not be processed, e.g. the provider was not
	 * reachable), so the provider's next delivery of the same event is not taken for a duplicate.
	 * The row stays in the log, marked 503.
	 */
	public static function release_event( $gateway, $event_key ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_evt_' );
		$key = strlen( (string) $event_key ) === 64 ? (string) $event_key : hash( 'sha256', (string) $gateway . '|' . (string) $event_key );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET event_key = %s, http_code = %d WHERE gateway = %s AND event_key = %s", hash( 'sha256', $key . '|retry|' . bin2hex( random_bytes( 8 ) ) ), 503, (string) $gateway, $key ) );
	}

	/** Attaches an event (stored before its payment was known) to a payment. */
	public static function attach_event( $gateway, $event_key, $payment_id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_evt_' );
		$key = strlen( (string) $event_key ) === 64 ? (string) $event_key : hash( 'sha256', (string) $gateway . '|' . (string) $event_key );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET payment_id = %d WHERE gateway = %s AND event_key = %s", (int) $payment_id, (string) $gateway, $key ) );
	}

	public static function events( $payment_id, $limit = 200 ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_evt_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE payment_id = %d ORDER BY id ASC LIMIT %d", (int) $payment_id, (int) $limit ), ARRAY_A );
	}

	/* ------------------------------------------------------------------ refunds */

	public static function insert_refund( array $data ) {
		global $wpdb;
		$now = self::now();
		$row = array_merge( array( 'status' => 'pending', 'gateway_ref' => '', 'reason' => '', 'created_by' => 0, 'created_at' => $now, 'updated_at' => $now ), $data );
		$ok = $wpdb->insert( self::table( 'emsfb_pmt_rfd_' ), $row );
		return $ok === false ? 0 : (int) $wpdb->insert_id;
	}

	public static function update_refund( $id, array $fields ) {
		global $wpdb;
		$fields['updated_at'] = self::now();
		return false !== $wpdb->update( self::table( 'emsfb_pmt_rfd_' ), $fields, array( 'id' => (int) $id ) );
	}

	public static function get_refund( $id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_rfd_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", (int) $id ), ARRAY_A );
		return $r ? $r : null;
	}

	/**
	 * Moves a refund row from pending to $to (refunded | failed | canceled) once: one conditional
	 * UPDATE, so a webhook and an admin click cannot both settle it. @return bool
	 */
	public static function settle_refund( $id, $to, $gateway_ref = '' ) {
		global $wpdb;
		if ( ! in_array( $to, array( 'refunded', 'failed', 'canceled' ), true ) ) return false;
		$t = self::table( 'emsfb_pmt_rfd_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET status = %s, updated_at = %s, gateway_ref = CASE WHEN gateway_ref = '' THEN %s ELSE gateway_ref END WHERE id = %d AND status = %s", $to, self::now(), substr( (string) $gateway_ref, 0, 100 ), (int) $id, 'pending' ) );
		return (int) $n === 1;
	}

	/** The sum of the confirmed refunds of a payment (rows with status refunded). */
	public static function refunded_total( $payment_id ) {
		$n = 0;
		foreach ( self::refunds( $payment_id ) as $r ) {
			if ( $r['status'] === 'refunded' ) $n += (int) $r['amount_minor'];
		}
		return $n;
	}

	public static function refunds( $payment_id ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_rfd_' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE payment_id = %d ORDER BY id ASC", (int) $payment_id ), ARRAY_A );
	}

	/* ------------------------------------------------------------------ lists */

	/**
	 * Admin list with filters, server-side pages.
	 *
	 * @param array $f gateway, status, mode, form_id, q, from (Y-m-d), to (Y-m-d), page, per_page
	 * @return array{rows:array,total:int}
	 */
	public static function search( array $f ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		$where = array( '1=1' );
		$args = array();
		if ( ! empty( $f['gateway'] ) ) { $where[] = 'gateway = %s'; $args[] = (string) $f['gateway']; }
		if ( ! empty( $f['status'] ) && in_array( $f['status'], self::STATUSES, true ) ) { $where[] = 'status = %s'; $args[] = $f['status']; }
		if ( ! empty( $f['mode'] ) && in_array( $f['mode'], array( 'test', 'live' ), true ) ) { $where[] = 'mode = %s'; $args[] = $f['mode']; }
		if ( ! empty( $f['form_id'] ) ) { $where[] = 'form_id = %d'; $args[] = (int) $f['form_id']; }
		if ( ! empty( $f['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['from'] ) ) { $where[] = 'created_at >= %s'; $args[] = $f['from'] . ' 00:00:00'; }
		if ( ! empty( $f['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['to'] ) ) { $where[] = 'created_at <= %s'; $args[] = $f['to'] . ' 23:59:59'; }
		if ( isset( $f['q'] ) && is_string( $f['q'] ) && trim( $f['q'] ) !== '' ) {
			$like = '%' . $wpdb->esc_like( trim( $f['q'] ) ) . '%';
			$where[] = '(gateway_ref LIKE %s OR track LIKE %s OR payer_email LIKE %s OR uid = %s)';
			array_push( $args, $like, $like, $like, trim( $f['q'] ) );
		}
		$per = isset( $f['per_page'] ) && in_array( (int) $f['per_page'], array( 20, 50, 100 ), true ) ? (int) $f['per_page'] : 20;
		$page = max( 1, isset( $f['page'] ) ? (int) $f['page'] : 1 );
		$w = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix; clauses built above with placeholders
		$count_sql = "SELECT COUNT(*) FROM `{$t}` WHERE {$w}";
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE {$w} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A );
		// phpcs:enable
		return array( 'rows' => array_map( array( __CLASS__, 'cast' ), (array) $rows ), 'total' => $total, 'page' => $page, 'per_page' => $per );
	}

	/** Paid totals per currency, pending count (the header of the Payments screen). */
	public static function stats( $mode = '' ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		$m = in_array( $mode, array( 'test', 'live' ), true ) ? $mode : '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$paid = $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(amount_minor - refunded_minor) AS total, COUNT(*) AS n FROM `{$t}` WHERE status IN ('paid','partially_refunded') AND (%s = '' OR mode = %s) GROUP BY currency", $m, $m ), ARRAY_A );
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE status IN ('created','pending','authorized') AND (%s = '' OR mode = %s)", $m, $m ) );
		// phpcs:enable
		return array( 'paid' => (array) $paid, 'pending' => $pending );
	}

	/**
	 * Open payments the reconcile job should look at now: created 5 minutes to 10 days ago,
	 * checked last before the back-off of their check_count (5, 15, 60 minutes, then 6 hours).
	 */
	public static function due_for_reconcile( $limit = 25, $days = 10 ) {
		global $wpdb;
		$t = self::table( 'emsfb_pmt_' );
		$now = self::time();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE status IN ('created','pending','authorized') AND created_at <= %s AND created_at >= %s ORDER BY last_checked_at IS NOT NULL, last_checked_at ASC, id ASC LIMIT %d",
			gmdate( 'Y-m-d H:i:s', $now - 5 * 60 ), gmdate( 'Y-m-d H:i:s', $now - max( 1, (int) $days ) * 86400 ), (int) $limit * 4 ), ARRAY_A );
		$out = array();
		foreach ( $rows as $r ) {
			$r = self::cast( $r );
			if ( $r['last_checked_at'] ) {
				$wait = self::backoff( (int) $r['check_count'] );
				if ( strtotime( $r['last_checked_at'] . ' UTC' ) + $wait > $now ) continue;
			}
			$out[] = $r;
			if ( count( $out ) >= $limit ) break;
		}
		return $out;
	}

	/** Seconds to wait after the n-th check. */
	public static function backoff( $checks ) {
		$steps = array( 5 * 60, 15 * 60, 60 * 60 );
		return isset( $steps[ $checks - 1 ] ) ? $steps[ $checks - 1 ] : ( $checks <= 0 ? 0 : 6 * 3600 );
	}

	/* ------------------------------------------------------------------ privacy (F13) */

	/**
	 * F13: when entries are deleted or anonymized (manual, retention job, WordPress erasure), the
	 * payment rows that belong to them are anonymized: payer name, e-mail and IP emptied, event
	 * payloads dropped. Amount, currency, gateway reference and dates stay for accounting.
	 *
	 * @param int[] $msg_ids
	 * @return int rows anonymized
	 */
	public static function anonymize_for_messages( array $msg_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $msg_ids ) ) );
		if ( empty( $ids ) || ! self::ready() ) return 0;
		$t = self::table( 'emsfb_pmt_' );
		$e = self::table( 'emsfb_pmt_evt_' );
		$n = 0;
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix; placeholders built above
			$pids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE msg_id IN ({$in})", $chunk ) ) );
			if ( empty( $pids ) ) continue;
			$pin = implode( ',', array_fill( 0, count( $pids ), '%d' ) );
			$n += (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET payer_email = '', payer_name = '', ip = '' WHERE id IN ({$pin})", $pids ) );
			$wpdb->query( $wpdb->prepare( "UPDATE `{$e}` SET payload = NULL WHERE payment_id IN ({$pin})", $pids ) );
			// phpcs:enable
		}
		return $n;
	}

	/** F13 hook efb_entries_purged. */
	public static function on_entries_purged( $ids, $mode = 'delete', $out = array() ) {
		self::anonymize_for_messages( (array) $ids );
	}
}

/** JSON for logs and the quote column (no escaped slashes, Unicode kept). */
function wp_json_encode_efb_pay( $v ) {
	$j = json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
	return is_string( $j ) ? $j : '';
}
