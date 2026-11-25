<?php
/**
 * PAY-OUT step 5 (owner approved 2026-11-18): the forms of the older Stripe, PayPal and Persia Pay fields (the
 * legacy_gateway contract) move to the add-ons' fields on the payment framework (PAY00): stripe → stripecheckout,
 * paypal → paypalcheckout, persiaPay / zarinPal → zarinpalgate. Each add-on names its field (filter
 * efb_legacy_gateway_target: legacy type => framework slug, used only while that gateway is registered) and copies its
 * keys into the framework's settings (filter efb_legacy_gateway_seed( $done, $type ): true once the gateway is set up).
 *
 * Per form, once (idempotent): only the gateway field's type and row 0's getway / currency change, every priced field
 * stays as it is. A form is moved only when:
 *   - its add-on registered the new field (an older add-on: "waiting_addon", nothing changes, the older field keeps
 *     taking payments through the add-on);
 *   - the keys are there (seeded from the add-on's own keys, or entered on the gateway's settings: "waiting_keys");
 *   - it charges once (row 0 paymentmethod charge): subscriptions stay on the older field ("recurring", final);
 *   - Persia Pay's sub-gateway is ZarinPal ("gateway_unsupported", final), it has one gateway field;
 *   - it charges the same amount: for every priced choice (and a typed amount) the add-on compares what the older field
 *     charged with Pricing::quote() of the moved form (filter efb_legacy_gateway_same_amount); any difference, or no
 *     answer, keeps the form as it is ("amount_differs", final until the owner moves it by hand);
 *   - the moved form is ready (Readiness::of_form(); else the issue's code, retried).
 * Before the form is written, its original JSON (the stored text, byte for byte) is kept in the option
 * emsfb_pay_legacy_bak_<form_id> (not autoloaded) and read back; the form is written and read back too (a failed write
 * is retried by the next batch). Every step is logged per form in the option emsfb_pay_legacy_migration (status, from,
 * to, when, why, by; the last 10 events). Rollback (per form, admin): the backup is written back byte for byte and the
 * form is not moved again automatically ("rolled_back"); "Move again" moves it on request.
 *
 * Batches: 20 forms per run; the run is a single cron event (efb_pay_legacy_migrate) queued from the admin (at most
 * hourly) and again a minute later while a run moved forms and some are left; one run at a time (a 2-minute lock). Entries, receipts and the older payments table
 * (emsfb_pay_, the add-ons') are never touched, so earlier entries and receipts read as before.
 *
 * The filter efb_legacy_migration_auto (false) holds the background run; the admin's buttons still work.
 *
 * REST (manage_options): GET Emsfb/v1/payments/legacy-migration (the log), POST …/run (one batch now),
 * POST …/rollback {form_id}, POST …/migrate {form_id} (one form now, also after a rollback).
 *
 * The legacy_gateway contract is deprecated by this step and is removed in the next major version (5.0); see
 * docs/split/SPLIT-spec (legacy gateway section) and includes/payments/legacy-gateway-shim-efb.php.
 *
 * @package Emsfb
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Legacy_Migration {

	const LOG   = 'emsfb_pay_legacy_migration';
	const BAK   = 'emsfb_pay_legacy_bak_';
	const HOOK  = 'efb_pay_legacy_migrate';
	const LOCK  = 'efb_pay_legacy_mig_lock';
	const BATCH = 20;
	/** The older field types (contract legacy_gateway) and the gateway each stands for in row 0's getway. */
	const TYPES = array( 'stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal' );
	/** Statuses that are final for the automatic run (the owner can still move the form by hand). */
	const FINAL = array( 'migrated', 'rolled_back', 'recurring', 'gateway_unsupported', 'several_gateways', 'amount_differs', 'no_gateway_field' );

	public static function register() {
		if ( ! function_exists( 'add_action' ) ) return;
		add_action( self::HOOK, array( __CLASS__, 'cron' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_queue' ) );
	}

	/* ------------------------------------------------------------------------------------------------ the map */

	/** legacy type => framework slug, only for gateways registered now. */
	public static function targets() {
		$map = function_exists( 'apply_filters' ) ? apply_filters( 'efb_legacy_gateway_target', array() ) : array();
		$out = array();
		foreach ( is_array( $map ) ? $map : array() as $type => $slug ) {
			if ( ! in_array( (string) $type, self::TYPES, true ) || ! is_string( $slug ) || in_array( $slug, self::TYPES, true ) ) continue;
			if ( Registry::has( $slug ) ) $out[ (string) $type ] = $slug;
		}
		if ( isset( $out['persiaPay'] ) && ! isset( $out['persiapay'] ) ) $out['persiapay'] = $out['persiaPay'];
		return $out;
	}

	/* ------------------------------------------------------------------------------------------------- one form */

	/** The stored form's text and rows (null when there is no such form). */
	public static function read_form( $form_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT form_id, form_name, form_structer, form_type FROM `{$t}` WHERE form_id = %d", (int) $form_id ), ARRAY_A );
		if ( ! is_array( $row ) ) return null;
		$raw = (string) $row['form_structer'];
		$json = function_exists( 'emsfb_json_normalize_efb' ) ? emsfb_json_normalize_efb( $raw ) : str_replace( '\\"', '"', $raw );
		$rows = function_exists( 'emsfb_json_decode_efb' ) ? emsfb_json_decode_efb( $json, true ) : json_decode( $json, true );
		return array( 'id' => (int) $row['form_id'], 'name' => (string) $row['form_name'], 'raw' => $raw, 'type' => (string) $row['form_type'], 'rows' => is_array( $rows ) ? $rows : null );
	}

	/** The index and type of each older gateway field of a form. */
	public static function legacy_fields( array $rows ) {
		$out = array();
		foreach ( $rows as $i => $r ) {
			if ( $i === 0 || ! is_array( $r ) || ! isset( $r['type'] ) || ! is_string( $r['type'] ) ) continue;
			if ( in_array( $r['type'], self::TYPES, true ) ) $out[ $i ] = $r['type'];
		}
		return $out;
	}

	/**
	 * What moving a form would do: array( ok, status, reason, rows (moved), from, to, field_id ). Nothing is written.
	 * $targets: legacy type => slug (default: targets()).
	 */
	public static function plan( array $rows, $targets = null ) {
		$targets = $targets === null ? self::targets() : $targets;
		$fields = self::legacy_fields( $rows );
		$out = array( 'ok' => false, 'status' => '', 'reason' => '', 'rows' => null, 'from' => '', 'to' => '', 'field_id' => '' );
		if ( ! $fields ) return array( 'status' => 'no_gateway_field' ) + $out;
		if ( count( $fields ) > 1 ) return array( 'status' => 'several_gateways' ) + $out;
		$i = key( $fields );
		$type = $fields[ $i ];
		$out['from'] = $type;
		$out['field_id'] = isset( $rows[ $i ]['id_'] ) && is_scalar( $rows[ $i ]['id_'] ) ? (string) $rows[ $i ]['id_'] : '';
		$r0 = isset( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : array();
		$pm = isset( $r0['paymentmethod'] ) && is_scalar( $r0['paymentmethod'] ) ? (string) $r0['paymentmethod'] : 'charge';
		if ( $pm !== '' && $pm !== 'charge' ) return array( 'status' => 'recurring', 'reason' => $pm ) + $out;
		$persia = in_array( $type, array( 'persiaPay', 'persiapay', 'zarinPal' ), true );
		if ( $persia && isset( $r0['persiaPay'] ) && is_scalar( $r0['persiaPay'] ) && ! in_array( (string) $r0['persiaPay'], array( '', 'zarinPal', 'zarinpal' ), true ) ) {
			return array( 'status' => 'gateway_unsupported', 'reason' => (string) $r0['persiaPay'] ) + $out;
		}
		if ( ! isset( $targets[ $type ] ) ) return array( 'status' => 'waiting_addon' ) + $out;
		$slug = $targets[ $type ];
		$out['to'] = $slug;
		$new = $rows;
		$new[ $i ]['type'] = $slug;
		if ( isset( $new[ $i ]['elementId'] ) ) $new[ $i ]['elementId'] = $slug;
		$new[0]['getway'] = $slug;
		$new[0]['paymentmethod'] = 'charge';
		if ( $persia ) {
			// ZarinPal charges rials: the older field sent the prices as rials whatever row 0 said.
			$new[0]['currency'] = 'irr';
			unset( $new[0]['persiaPay'] );
		}
		if ( isset( $new[0]['type'] ) && $new[0]['type'] === 'persiaPay' ) $new[0]['type'] = 'payment';
		$out['rows'] = $new;
		$out['ok'] = true;
		$out['status'] = 'ready';
		return $out;
	}

	/**
	 * Whether the moved form charges what the older field charged: each priced choice alone (and a typed amount)
	 * through Pricing::quote() of the moved form, compared by the add-on (it knows how its older field turned a price
	 * into the provider's amount). Returns '' when the same, else the reason.
	 */
	public static function same_amount( array $old, array $new, $type, $slug, $field_id ) {
		$g = Registry::get( $slug );
		if ( ! $g ) return 'waiting_addon';
		$sup = $g->supports();
		$cur = Currency::code( isset( $new[0]['currency'] ) ? $new[0]['currency'] : '' );
		if ( $cur === '' || ( ! empty( $sup['currencies'] ) && ! in_array( $cur, array_map( 'strtoupper', (array) $sup['currencies'] ), true ) ) ) return 'currency_unsupported';
		$fields = array();
		foreach ( $new as $k => $r ) {
			if ( $k > 0 && is_array( $r ) && isset( $r['id_'] ) && is_scalar( $r['id_'] ) ) $fields[ (string) $r['id_'] ] = $r;
		}
		$samples = array();
		foreach ( $fields as $id => $r ) {
			$t = isset( $r['type'] ) ? (string) $r['type'] : '';
			if ( $t === 'option' && isset( $r['parent'], $fields[ (string) $r['parent'] ] ) && isset( $r['price'] ) && is_numeric( $r['price'] ) && (float) $r['price'] > 0 ) {
				$p = $fields[ (string) $r['parent'] ];
				$pt = isset( $p['type'] ) ? (string) $p['type'] : '';
				$val = isset( $r['value'] ) && is_scalar( $r['value'] ) ? (string) $r['value'] : '';
				if ( in_array( $pt, array( 'payRadio', 'payCheckbox', 'paySelect' ), true ) ) $row = array( 'id_' => (string) $r['parent'], 'id_ob' => $id, 'type' => $pt, 'value' => $val, 'name' => '', 'price' => (string) $r['price'] );
				elseif ( $pt === 'payMultiselect' ) $row = array( 'id_' => (string) $r['parent'], 'type' => $pt, 'value' => $val, 'ids' => $id, 'name' => '', 'price' => (string) $r['price'] );
				else continue;
				$samples[] = array( (string) $r['price'], array( $row ) );
			} elseif ( $t === 'prcfld' ) {
				$v = isset( $r['milen'] ) && is_numeric( $r['milen'] ) && (float) $r['milen'] > 0 ? (string) $r['milen'] : ( isset( $r['mlen'] ) && is_numeric( $r['mlen'] ) && (float) $r['mlen'] > 0 && (float) $r['mlen'] < 10 ? (string) $r['mlen'] : '10' );
				$samples[] = array( $v, array( array( 'id_' => $id, 'type' => 'prcfld', 'value' => $v, 'price' => $v, 'name' => '' ) ) );
			}
			if ( count( $samples ) >= 200 ) break;
		}
		foreach ( $samples as $s ) {
			$q = Pricing::quote( $new, $s[1], array( 'gateway' => $slug, 'field_id' => (string) $field_id, 'supports' => array( 'currencies' => isset( $sup['currencies'] ) ? $sup['currencies'] : array() ) ) );
			if ( empty( $q['ok'] ) ) return 'amount_differs';
			$same = function_exists( 'apply_filters' ) ? apply_filters( 'efb_legacy_gateway_same_amount', null, $type, $s[0], strtolower( $cur ), (int) $q['total_minor'], $slug ) : null;
			if ( $same !== true ) return 'amount_differs';
		}
		return '';
	}

	/**
	 * Moves one form (idempotent: a moved form is left as it is). $by: cron | admin. $force: the owner asked (also after
	 * a rollback or a final status). Returns the log entry.
	 */
	public static function migrate_form( $form_id, $by = 'cron', $force = false ) {
		$form_id = (int) $form_id;
		$log = self::log_get( $form_id );
		if ( $log && $log['status'] === 'migrated' ) return $log;
		if ( ! $force && $log && in_array( $log['status'], self::FINAL, true ) ) return $log;
		$f = self::read_form( $form_id );
		if ( ! $f ) return self::log_set( $form_id, array( 'status' => 'no_form' ), $by );
		if ( ! is_array( $f['rows'] ) ) return self::log_set( $form_id, array( 'status' => 'unreadable' ), $by );
		$plan = self::plan( $f['rows'] );
		$base = array( 'from' => $plan['from'], 'to' => $plan['to'], 'name' => $f['name'] );
		if ( ! $plan['ok'] ) return self::log_set( $form_id, $base + array( 'status' => $plan['status'], 'reason' => $plan['reason'] ), $by );
		// The keys: the add-on copies its own (once; true when the framework gateway is set up for its mode).
		$seeded = function_exists( 'apply_filters' ) ? apply_filters( 'efb_legacy_gateway_seed', false, $plan['from'] ) : false;
		$g = Registry::get( $plan['to'] );
		$s = Settings::load( $plan['to'] );
		if ( ! $seeded && ! ( $g && $g->is_configured( $s, $s->mode() ) ) ) return self::log_set( $form_id, $base + array( 'status' => 'waiting_keys' ), $by );
		$why = self::same_amount( $f['rows'], $plan['rows'], $plan['from'], $plan['to'], $plan['field_id'] );
		if ( $why !== '' ) return self::log_set( $form_id, $base + array( 'status' => $why === 'waiting_addon' ? 'waiting_addon' : 'amount_differs', 'reason' => $why ), $by );
		$ready = Readiness::of_form( $plan['rows'], $form_id );
		if ( ! $ready['ready'] ) {
			$code = isset( $ready['issues'][0]['code'] ) ? (string) $ready['issues'][0]['code'] : 'not_ready';
			return self::log_set( $form_id, $base + array( 'status' => 'not_ready', 'reason' => $code ), $by );
		}
		// The backup first, read back; then the form, read back.
		if ( ! self::backup( $form_id, $f ) ) return self::log_set( $form_id, $base + array( 'status' => 'failed', 'reason' => 'backup_not_written' ), $by );
		$json = wp_json_encode( $plan['rows'], JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || ! self::write_form( $form_id, str_replace( '"', '\\"', $json ), $f['raw'], $f['type'] === 'persiaPay' ? 'payment' : null ) ) {
			return self::log_set( $form_id, $base + array( 'status' => 'failed', 'reason' => 'form_not_written' ), $by );
		}
		self::saved( $form_id, $plan['rows'], $f['rows'], 'Payment field moved to the add-on\'s new field (' . $plan['from'] . ' → ' . $plan['to'] . ')' );
		return self::log_set( $form_id, $base + array( 'status' => 'migrated', 'field_id' => $plan['field_id'] ), $by );
	}

	/** Puts the form's original JSON back (byte for byte) and keeps it out of the automatic run. */
	public static function rollback( $form_id, $by = 'admin' ) {
		$form_id = (int) $form_id;
		$bak = self::backup_get( $form_id );
		if ( ! $bak ) return array( 'ok' => false, 'code' => 'no_backup' );
		$f = self::read_form( $form_id );
		if ( ! $f ) return array( 'ok' => false, 'code' => 'no_form' );
		if ( $f['raw'] !== $bak['raw'] && ! self::write_form( $form_id, $bak['raw'], $f['raw'], $bak['type'] !== '' ? $bak['type'] : null ) ) return array( 'ok' => false, 'code' => 'form_not_written' );
		$back = self::read_form( $form_id );
		if ( is_array( $back['rows'] ) ) self::saved( $form_id, $back['rows'], is_array( $f['rows'] ) ? $f['rows'] : array(), 'Payment field moved back to the older field' );
		$log = self::log_get( $form_id );
		$entry = self::log_set( $form_id, array( 'status' => 'rolled_back', 'from' => $log ? $log['from'] : '', 'to' => $log ? $log['to'] : '', 'name' => $f['name'] ), $by );
		return array( 'ok' => true, 'entry' => $entry );
	}

	/* ------------------------------------------------------------------------------------------------- batches */

	/** The ids of forms with an older gateway field (newest first), at most $limit. */
	public static function candidates( $limit = 500 ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		$like = array();
		foreach ( self::TYPES as $ty ) {
			// The stored text has its quotes escaped (\"stripe\"); an importer may have stored them plain.
			$like[] = $wpdb->prepare( 'form_structer LIKE %s', '%' . $wpdb->esc_like( '"' . $ty . '\\"' ) . '%' );
			$like[] = $wpdb->prepare( 'form_structer LIKE %s', '%' . $wpdb->esc_like( '"' . $ty . '"' ) . '%' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL -- table from $wpdb->prefix, the LIKE parts prepared above
		$list = $wpdb->get_results( "SELECT form_id, form_structer FROM `{$t}` WHERE " . implode( ' OR ', $like ) . ' ORDER BY form_id ASC LIMIT ' . (int) $limit, ARRAY_A );
		// Only a field's type counts (a field named "stripe" is not one).
		$re = '/type\\\\?"\s*:\s*\\\\?"(' . implode( '|', self::TYPES ) . ')\\\\?"/';
		$out = array();
		foreach ( (array) $list as $r ) if ( is_array( $r ) && preg_match( $re, (string) $r['form_structer'] ) ) $out[] = (int) $r['form_id'];
		return $out;
	}

	/** The forms the automatic run still has to look at (not final). */
	public static function pending() {
		$log = self::log_all();
		$out = array();
		foreach ( self::candidates() as $id ) {
			$e = isset( $log['forms'][ $id ] ) ? $log['forms'][ $id ] : null;
			if ( $e && in_array( $e['status'], self::FINAL, true ) ) continue;
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * One batch: up to $limit forms, each moved or logged with why not. One run at a time.
	 * @return array {ran, done: [form_id => status], left}
	 */
	public static function run_batch( $limit = self::BATCH, $by = 'cron' ) {
		if ( function_exists( 'get_transient' ) && get_transient( self::LOCK ) ) return array( 'ran' => false, 'done' => array(), 'left' => count( self::pending() ) );
		if ( function_exists( 'set_transient' ) ) set_transient( self::LOCK, 1, 120 );
		$done = array();
		try {
			$ids = self::pending();
			// Forms waiting for something (an add-on update, keys) are looked at again, after the others.
			$log = self::log_all();
			usort( $ids, function ( $a, $b ) use ( $log ) { return ( isset( $log['forms'][ $a ] ) ? 1 : 0 ) - ( isset( $log['forms'][ $b ] ) ? 1 : 0 ) ?: $a - $b; } );
			foreach ( array_slice( $ids, 0, max( 1, (int) $limit ) ) as $id ) {
				$e = self::migrate_form( $id, $by );
				$done[ $id ] = $e['status'];
			}
		} finally {
			if ( function_exists( 'delete_transient' ) ) delete_transient( self::LOCK );
		}
		$left = self::pending();
		$all = self::log_all();
		$all['runs'] = ( isset( $all['runs'] ) ? (int) $all['runs'] : 0 ) + 1;
		$all['last_run'] = gmdate( 'Y-m-d H:i:s' );
		self::log_put( $all );
		return array( 'ran' => true, 'done' => $done, 'left' => count( $left ) );
	}

	/**
	 * The cron event: one batch; the next one a minute later while this one moved forms and some are left. Forms that
	 * only wait (for an add-on update or keys) are looked at again from the admin (maybe_queue(), at most hourly).
	 */
	public static function cron() {
		if ( ! self::auto() ) return;
		$r = self::run_batch( self::BATCH, 'cron' );
		$moved = array_filter( $r['done'], function ( $s ) { return $s === 'migrated'; } );
		if ( $r['ran'] && $moved && $r['left'] > 0 && function_exists( 'wp_schedule_single_event' ) ) wp_schedule_single_event( time() + 60, self::HOOK );
	}

	/** admin_init: queues a run when there are forms to look at and none is queued (at most hourly). */
	public static function maybe_queue() {
		if ( ! self::auto() || ! function_exists( 'wp_next_scheduled' ) || wp_next_scheduled( self::HOOK ) ) return;
		if ( get_transient( 'efb_pay_legacy_mig_seen' ) ) return;
		set_transient( 'efb_pay_legacy_mig_seen', 1, HOUR_IN_SECONDS );
		if ( self::pending() ) wp_schedule_single_event( time() + 30, self::HOOK );
	}

	/**
	 * Whether forms are moved in the background (filter efb_legacy_migration_auto, default true). A site can hold the
	 * automatic move and use the admin's Move now / Check now instead (Settings › Payments).
	 */
	public static function auto() {
		return ! function_exists( 'apply_filters' ) || (bool) apply_filters( 'efb_legacy_migration_auto', true );
	}

	/* --------------------------------------------------------------------------------------------- storage */

	private static function backup( $form_id, array $f ) {
		$name = self::BAK . (int) $form_id;
		$have = self::backup_get( $form_id );
		if ( $have ) return true; // the first original is kept (a form moved, rolled back and moved again keeps it)
		$val = array( 'raw' => $f['raw'], 'type' => $f['type'], 'sha' => hash( 'sha256', $f['raw'] ), 'at' => gmdate( 'Y-m-d H:i:s' ) );
		if ( function_exists( 'add_option' ) ) add_option( $name, $val, '', 'no' ); else update_option( $name, $val, false );
		$back = self::backup_get( $form_id );
		return $back && $back['raw'] === $f['raw'];
	}

	public static function backup_get( $form_id ) {
		$v = get_option( self::BAK . (int) $form_id, null );
		if ( ! is_array( $v ) || ! isset( $v['raw'], $v['sha'] ) || ! is_string( $v['raw'] ) || ! hash_equals( (string) $v['sha'], hash( 'sha256', $v['raw'] ) ) ) return null;
		return array( 'raw' => $v['raw'], 'type' => isset( $v['type'] ) ? (string) $v['type'] : '', 'at' => isset( $v['at'] ) ? (string) $v['at'] : '' );
	}

	/** Writes form_structer only if it is still what was read (no lost update), then reads it back. */
	private static function write_form( $form_id, $raw, $expect, $type = null ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		$set = array( 'form_structer' => $raw );
		if ( $type !== null ) $set['form_type'] = $type;
		$n = $wpdb->update( $t, $set, array( 'form_id' => (int) $form_id, 'form_structer' => $expect ) );
		if ( $n === false ) return false;
		$f = self::read_form( $form_id );
		return $f && $f['raw'] === $raw;
	}

	/** The save hooks (form versions, caches, the add-ons' own hooks) as for a save from the builder's API. */
	private static function saved( $form_id, array $rows, array $old, $note ) {
		if ( ! function_exists( 'do_action' ) ) return;
		do_action( 'efb_form_saved', (int) $form_id, $rows, array( 'form_id' => (int) $form_id, 'source' => 'api', 'user_id' => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0, 'rev_note' => $note, 'old_rows' => $old ) );
	}

	public static function log_all() {
		$v = get_option( self::LOG, array() );
		if ( ! is_array( $v ) ) $v = array();
		if ( ! isset( $v['forms'] ) || ! is_array( $v['forms'] ) ) $v['forms'] = array();
		return $v;
	}

	private static function log_put( array $v ) {
		update_option( self::LOG, $v, false );
	}

	public static function log_get( $form_id ) {
		$all = self::log_all();
		return isset( $all['forms'][ (int) $form_id ] ) ? $all['forms'][ (int) $form_id ] : null;
	}

	private static function log_set( $form_id, array $e, $by ) {
		$all = self::log_all();
		$old = isset( $all['forms'][ (int) $form_id ] ) ? $all['forms'][ (int) $form_id ] : array();
		$now = gmdate( 'Y-m-d H:i:s' );
		$entry = array(
			'status' => (string) $e['status'],
			'reason' => isset( $e['reason'] ) ? (string) $e['reason'] : '',
			'from'   => isset( $e['from'] ) && $e['from'] !== '' ? (string) $e['from'] : ( isset( $old['from'] ) ? $old['from'] : '' ),
			'to'     => isset( $e['to'] ) && $e['to'] !== '' ? (string) $e['to'] : ( isset( $old['to'] ) ? $old['to'] : '' ),
			'name'   => isset( $e['name'] ) ? (string) $e['name'] : ( isset( $old['name'] ) ? $old['name'] : '' ),
			'at'     => $now,
			'by'     => (string) $by,
			'events' => isset( $old['events'] ) && is_array( $old['events'] ) ? $old['events'] : array(),
		);
		if ( $entry['status'] === 'migrated' ) $entry['moved_at'] = $now; elseif ( isset( $old['moved_at'] ) ) $entry['moved_at'] = $old['moved_at'];
		$last = $entry['events'] ? $entry['events'][ count( $entry['events'] ) - 1 ] : null;
		// The same "waiting" again is not a new event (a batch looks at waiting forms every hour).
		if ( ! $last || $last[1] !== $entry['status'] || $last[2] !== $entry['reason'] ) $entry['events'][] = array( $now, $entry['status'], $entry['reason'], (string) $by );
		$entry['events'] = array_slice( $entry['events'], -10 );
		$all['forms'][ (int) $form_id ] = $entry;
		self::log_put( $all );
		return $entry;
	}

	/* -------------------------------------------------------------------------------------------------- REST */

	public static function routes() {
		$can = function () { return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ); };
		register_rest_route( 'Emsfb/v1', 'payments/legacy-migration', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_status' ), 'permission_callback' => $can ) );
		register_rest_route( 'Emsfb/v1', 'payments/legacy-migration/run', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_run' ), 'permission_callback' => $can ) );
		register_rest_route( 'Emsfb/v1', 'payments/legacy-migration/rollback', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_rollback' ), 'permission_callback' => $can ) );
		register_rest_route( 'Emsfb/v1', 'payments/legacy-migration/migrate', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_migrate' ), 'permission_callback' => $can ) );
	}

	/** The log for the admin: one row per form with an older field or a move (no form content, no key). */
	public static function status() {
		$all = self::log_all();
		$forms = array();
		$ids = array_unique( array_merge( array_keys( $all['forms'] ), self::candidates() ) );
		sort( $ids );
		foreach ( $ids as $id ) {
			if ( isset( $all['forms'][ $id ] ) && $all['forms'][ $id ]['status'] === 'no_gateway_field' ) continue; // e.g. getway left on a form without the field
			$e = isset( $all['forms'][ $id ] ) ? $all['forms'][ $id ] : array( 'status' => 'pending', 'reason' => '', 'from' => '', 'to' => '', 'name' => '', 'at' => '', 'by' => '', 'events' => array() );
			if ( $e['name'] === '' ) { $f = self::read_form( $id ); if ( $f ) { $e['name'] = $f['name']; $p = is_array( $f['rows'] ) ? self::plan( $f['rows'] ) : null; if ( $p ) { $e['from'] = $p['from']; $e['to'] = $p['to']; } } }
			$e['form_id'] = (int) $id;
			$e['can_rollback'] = $e['status'] === 'migrated' && self::backup_get( $id ) !== null;
			$e['can_migrate'] = $e['status'] !== 'migrated';
			$e['name'] = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( str_replace( '@efb@dq#', '"', $e['name'] ) ) : preg_replace('/<[^>]*>/', '',  $e['name'] );
			$forms[] = $e;
		}
		$counts = array();
		foreach ( $forms as $e ) $counts[ $e['status'] ] = ( isset( $counts[ $e['status'] ] ) ? $counts[ $e['status'] ] : 0 ) + 1;
		return array( 'forms' => $forms, 'counts' => $counts, 'targets' => self::targets(), 'runs' => isset( $all['runs'] ) ? (int) $all['runs'] : 0, 'last_run' => isset( $all['last_run'] ) ? $all['last_run'] : '',
			'queued' => function_exists( 'wp_next_scheduled' ) ? (bool) wp_next_scheduled( self::HOOK ) : false );
	}

	public static function rest_status( $r ) {
		return new \WP_REST_Response( self::status(), 200 );
	}

	public static function rest_run( $r ) {
		$res = self::run_batch( self::BATCH, 'admin' );
		if ( ! $res['ran'] ) return new \WP_REST_Response( array( 'code' => 'busy', 'message' => __( 'A run is in progress. Try again in a minute.', 'easy-form-builder' ) ) + $res, 409 );
		return new \WP_REST_Response( $res + array( 'status' => self::status() ), 200 );
	}

	public static function rest_rollback( $r ) {
		$id = (int) $r->get_param( 'form_id' );
		if ( $id < 1 ) return new \WP_REST_Response( array( 'code' => 'bad_request', 'message' => __( 'Send form_id.', 'easy-form-builder' ) ), 400 );
		$res = self::rollback( $id, 'admin' );
		return new \WP_REST_Response( $res, $res['ok'] ? 200 : ( $res['code'] === 'no_form' || $res['code'] === 'no_backup' ? 404 : 500 ) );
	}

	public static function rest_migrate( $r ) {
		$id = (int) $r->get_param( 'form_id' );
		if ( $id < 1 ) return new \WP_REST_Response( array( 'code' => 'bad_request', 'message' => __( 'Send form_id.', 'easy-form-builder' ) ), 400 );
		$e = self::migrate_form( $id, 'admin', true );
		return new \WP_REST_Response( array( 'ok' => $e['status'] === 'migrated', 'entry' => $e ), 200 );
	}
}
