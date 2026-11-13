<?php

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Install {

	/**
	 * Create a table only when it does not exist yet.
	 *
	 * The statements below are deliberately kept away from dbDelta's column
	 * synchronisation on tables that already exist. Historically they were
	 * written as "CREATE TABLE IF NOT EXISTS", which dbDelta could not parse
	 * (core's |CREATE TABLE ([^ ]*)| captured the word "IF"), so it never
	 * emitted an ALTER for anything - that is the bug that kept indexes off
	 * existing sites. Simply removing "IF NOT EXISTS" would swing to the other
	 * extreme: dbDelta would suddenly start rewriting every column on schemas
	 * this plugin has not controlled for years, including any a site owner
	 * widened by hand, and a dry run showed it already wanted to rewrite
	 * emsfb_form.status on every single call (tinyint(4) vs the declared
	 * TINYINT) - a table rebuild each time, for nothing.
	 *
	 * So: creation happens here, and every change to an existing table goes
	 * through upgrade_schema() below, where each statement is explicit and
	 * reviewable.
	 *
	 * @param string $sql A full CREATE TABLE statement.
	 * @return void
	 */
	private static function create_table_if_absent( $sql ) {
		global $wpdb;

		if ( ! preg_match( '/CREATE TABLE\s+`?([^\s`(]+)`?/i', $sql, $m ) ) {
			return;
		}

		$table = $m[1];
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		dbDelta( $sql );
	}

	static function install() {
		global $wpdb;
		$state="gi";
		$table_name_stng = $wpdb->prefix . "emsfb_setting";
		$table_name = $wpdb->prefix . "emsfb_form";
		$table_name_msg = $wpdb->prefix . "emsfb_msg_";
		$table_name_rsp = $wpdb->prefix . "emsfb_rsp_";
		$table_name_status = $wpdb->prefix . "emsfb_stts_";

		$charset_collate = $wpdb->get_charset_collate();

						require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

						$sql = "CREATE TABLE {$table_name_stng} (
							`id` int(1) NOT NULL AUTO_INCREMENT,
							`setting` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
							`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
							`edit_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
							`email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
							PRIMARY KEY  (id)

						) {$charset_collate};";

						self::create_table_if_absent( $sql );

						$sql = "CREATE TABLE {$table_name} (
							`form_id` int(11) NOT NULL AUTO_INCREMENT,
							`form_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_structer` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_type` varchar(15) COLLATE utf8mb4_unicode_ci NULL DEFAULT  'form',
							`form_created_by` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_access_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_create_date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
							`status` TINYINT COLLATE utf8mb4_unicode_ci NULL DEFAULT  1,
							PRIMARY KEY  (form_id)
						) {$charset_collate};";

						self::create_table_if_absent( $sql );

						$sql = "CREATE TABLE {$table_name_msg} (
							`msg_id` int(11) NOT NULL AUTO_INCREMENT,
							`form_id` int(11) COLLATE utf8mb4_unicode_ci NOT NULL,
							`track` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
							`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`form_title_x` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
							`content` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
							`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
							`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,
							`read_` int(10) COLLATE utf8mb4_unicode_ci NOT NULL,
							`read_by` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`uid` BIGINT UNSIGNED NOT NULL DEFAULT 0,
							`prv_state` TINYINT NOT NULL DEFAULT 0,
							PRIMARY KEY  (msg_id),
							KEY form_date (form_id,`date`),
							KEY track (track),
							KEY date (`date`)
						) {$charset_collate};";

						self::create_table_if_absent( $sql );

						$sql = "CREATE TABLE {$table_name_rsp} (
							`rsp_id` int(20) NOT NULL AUTO_INCREMENT,
							`msg_id` int(11) COLLATE utf8mb4_unicode_ci NOT NULL,
							`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`content` text COLLATE utf8mb4_unicode_ci NOT NULL,
							`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
							`read_by` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,
							`read_` int(10) COLLATE utf8mb4_unicode_ci NOT NULL,
							`reader_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`rsp_by` int(1) COLLATE utf8mb4_unicode_ci NOT NULL,
							PRIMARY KEY  (rsp_id),
							KEY msg_id (msg_id)
						) {$charset_collate};";

						self::create_table_if_absent( $sql );

						$sql = "CREATE TABLE {$table_name_status} (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`sid` varchar(21) COLLATE utf8mb4_unicode_ci NOT NULL,
							`fid` int(11)   NOT NULL,
							`type_` int(8)  NOT NULL,
							`date` datetime  DEFAULT CURRENT_TIMESTAMP NOT NULL,
							`status` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
							`ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
							`os` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
							`browser` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
							`read_date` datetime  DEFAULT CURRENT_TIMESTAMP,
							`uid` int(10)  NOT NULL,
							`tc` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
							`active` int(1)   NOT NULL,
							PRIMARY KEY  (id),
							KEY sid (sid),
							KEY lookup (fid,uid,active),
							KEY date (date)
						) {$charset_collate};";

						self::create_table_if_absent( $sql );

						// F11 (DB 1.6): the atomic counters (shared with F02) and the saved / partial answers.
						foreach ( self::f11_ddl( $wpdb->prefix, $charset_collate ) as $sql ) {
							self::create_table_if_absent( $sql );
						}
						// F03 (DB 1.7): the claims of unique values.
						foreach ( self::f03_ddl( $wpdb->prefix, $charset_collate ) as $sql ) {
							self::create_table_if_absent( $sql );
						}
						// F02 (DB 1.8): the verification challenges (codes only as HMACs).
						foreach ( self::f02_ddl( $wpdb->prefix, $charset_collate ) as $sql ) {
							self::create_table_if_absent( $sql );
						}

				$user_id = get_current_user_id();
				$usr =get_user_by('id',$user_id);
				// Activation may run without a current user (ID 0). Do not read a
				// property from false here: PHP emits that warning as plugin output,
				// which makes WordPress report an "unexpected output" activation error.
				$eml = $usr ? $usr->user_email : '';
				if($eml==NULL || $eml=='') {
					$usr =get_user_by('id',1);
					$eml = $usr ? $usr->user_email :'';
				}

			$s = false;

			// %i (identifier placeholder) needs WordPress 6.2; this plugin supports
			// 5.7+. The name is the site prefix plus a constant, never user input.
			$v = $wpdb->get_var( "SELECT setting FROM `" . esc_sql( $table_name_stng ) . "` ORDER BY id DESC LIMIT 1" );
			$rand = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
			// smtp (the "This site can send emails" switch) starts ON: the owner's
			// first form must email them, or the plugin looks broken. The email
			// check measures how well that mail is delivered; it never switches
			// sending off. The owner can still turn it off in Email Settings.
			if ($v === NULL) {
				$settings = (object) array(
					'activeCode' => '',
					'siteKey' => '',
					'secretKey' => '',
					'emailSupporter' => $eml,
					'apiKeyMap' => '',
					'smtp' => true,
					'bootstrap' => false,
					'emailTemp' => \Emsfb::get_default_email_template_efb(),
					'email_key' => $rand,
					// Both response-box switches ship ON: the panel writes real JSON
					// booleans for these toggles, so seed them the same way instead of
					// leaving the keys absent and relying on each reader's fallback.
					'activeDlBtn' => true,
					'dsupfile' => true,
					'adminSN'=> true,
				);
				$setting = wp_json_encode($settings, JSON_UNESCAPED_UNICODE);

				$s = $wpdb->insert( $table_name_stng, array( 'setting' => $setting, 'edit_by' => get_current_user_id()
				, 'date'=>current_time('mysql') , 'email'=>'' ));

				if ($s) {
					// The database row is canonical, but the option/transient are read
					// by the admin page and public handlers before their next DB lookup.
					// Seed all three stores together on a first installation.
					update_option('emsfb_settings', $setting);
					set_transient('emsfb_settings_transient', $setting, 1800);
					wp_cache_delete('settings:decoded', 'emsfb');
					wp_cache_delete('settings:pub', 'emsfb');
					wp_cache_delete('settings:raw', 'emsfb');
					wp_cache_delete('emsfb_settings', 'emsfb');
					\Emsfb::get_setting_Emsfb('_clear_cache');
				}
			}

			// Only a genuinely new settings row should start the first-run guide.
			// Updates and existing installations never set this flag again.
			if ($v === NULL && $s) {
				/*
				 * This option can survive a plugin reset while the plugin tables are
				 * removed. add_option() silently leaves a previous completed value (0)
				 * untouched, which made a new settings row look like an existing site
				 * and skipped the email step. A newly-created settings row is the one
				 * authoritative first-run signal, so explicitly reset the guide here.
				 */
				update_option('emsfb_onboarding_pending', 1, false);
				update_option('emsfb_onboarding_initial_install', 1, false);
				delete_option('emsfb_onboarding_completed_at');
				delete_option('emsfb_onboarding_dismissed_at');
			}

		// Activation runs on every activate, not only the first one, and
		// create_table_if_absent() above skips a table that already exists - so a
		// site that was activated again (staging clone, host migration, WP-CLI)
		// reaches here with the old schema still in place. Writing the version
		// without migrating would mark the migration as done and make
		// maybe_upgrade_schema_efb() skip it forever, which is exactly the
		// situation the stored version is supposed to protect against.
		self::upgrade_schema();

		// upgrade_schema() records the version itself, but it returns early when
		// the table is absent, so this stays as the unconditional writer.
		// update_option(), not add_option(): add_option() leaves an existing value
		// untouched, so the stored schema version could never move past whatever
		// an old install first wrote, making every version-guarded migration a
		// no-op on exactly the sites that needed it.
		update_option( 'Emsfb_db_version', EMSFB_DB_VERSION );

		do_action('emsfb_update_cache_plugins_list');
		do_action('emsfb_update_security_plugins_list');
		if (class_exists('\Emsfb\Email_Monitor')) {
			\Emsfb\Email_Monitor::activate();
		}

		return $state;
	}

	/**
	 * Bring an existing installation's schema up to date.
	 *
	 * dbDelta() only ever emitted CREATE statements here, never ALTER: the
	 * queries were written as "CREATE TABLE IF NOT EXISTS", and core parses the
	 * table name with |CREATE TABLE ([^ ]*)| (wp-admin/includes/upgrade.php),
	 * which captured the literal word "IF". dbDelta therefore compared against a
	 * table called "IF", found nothing, and ran the query verbatim - which does
	 * nothing at all when the real table already exists. Every schema change
	 * since has silently reached new installs only.
	 *
	 * The CREATE statements are fixed, and this adds the indexes explicitly so
	 * sites that already have the tables actually receive them.
	 *
	 * @return array<int, string> Applied migration descriptions.
	 */
	static function upgrade_schema() {
		global $wpdb;

		$applied = array();

		// emsfb_form.status came after 3.9.x and create_table_if_absent() never
		// alters an existing table, so sites updated from 3.9 had no column for
		// the "active forms" count (weekly report query error). DB version 1.3.
		$form_table = $wpdb->prefix . 'emsfb_form';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $form_table ) ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$has_status = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$form_table}` LIKE %s", 'status' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, literal clause
			if ( empty( $has_status ) && false !== $wpdb->query( "ALTER TABLE `{$form_table}` ADD COLUMN `status` TINYINT NULL DEFAULT 1" ) ) {
				$applied[] = 'emsfb_form: ADD COLUMN status';
			}
		}

		// F13 (privacy and retention), DB version 1.4. emsfb_msg_.uid is the
		// logged-in sender (0 for guests) so WordPress' personal-data tools can
		// find a member's entries; prv_state = 1 marks an anonymized entry. The
		// keys serve the retention cron (form_id + date, date), tracking-code
		// lookups and every reply query (emsfb_rsp_.msg_id). Each step checks
		// first, so a second run issues no ALTER. They sit before the
		// emsfb_stts_ early return below: a site without the session table
		// still needs them.
		$applied = array_merge( $applied, self::upgrade_privacy_schema() );

		// PAY00 (payment framework), DB version 1.5: the four payment tables. Only missing
		// tables are created (create_table_if_absent), so a second run does nothing; placed
		// before the emsfb_stts_ early return for the same reason as the F13 steps.
		$applied = array_merge( $applied, self::upgrade_payments_schema() );

		// F11 (form limits, save and resume), DB version 1.6: the counter table (F02's design,
		// created by whichever of F02 / F11 comes first), the draft table and a form_id index on
		// the entries. Idempotent, and before the emsfb_stts_ early return like the steps above.
		$applied = array_merge( $applied, self::upgrade_f11_schema() );
		// F03 (text field rules), DB version 1.7: the unique-value claims. emsfb_msg_ already has an
		// index starting with form_id (F13 form_date), which the backfill and the fallback scan use.
		$applied = array_merge( $applied, self::upgrade_f03_schema() );
		// F02 (one-time codes), DB version 1.8: the challenge table. Idempotent, before the early return.
		$applied = array_merge( $applied, self::upgrade_f02_schema() );

		$table   = $wpdb->prefix . 'emsfb_stts_';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return $applied;
		}

		// The session table is read on every form page view and on every submit,
		// but shipped with only PRIMARY(id) - so both hot lookups were full table
		// scans that got slower as the table grew.
		$wanted = array(
			'sid'    => "ADD KEY sid (sid)",
			'lookup' => "ADD KEY lookup (fid,uid,active)",
			'date'   => "ADD KEY date (`date`)",
		);

		$present = array();
		$rows    = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $row['Key_name'] ) ) {
				$present[ $row['Key_name'] ] = true;
			}
		}

		foreach ( $wanted as $key_name => $clause ) {
			if ( isset( $present[ $key_name ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause from the literal map above
			$ok = $wpdb->query( "ALTER TABLE `{$table}` {$clause}" );
			if ( false !== $ok ) {
				$applied[] = "emsfb_stts_: {$clause}";
			}
		}

		update_option( 'Emsfb_db_version', EMSFB_DB_VERSION );

		return $applied;
	}

	/**
	 * PAY00: emsfb_pmt_, emsfb_pmt_evt_, emsfb_pmt_rfd_, emsfb_coupon_ (DB version 1.5).
	 * Idempotent: a table that exists is never touched. Without dbDelta (a stripped
	 * environment) nothing is attempted; the next request retries.
	 *
	 * @return array<int, string> Applied steps.
	 */
	public static function upgrade_payments_schema() {
		global $wpdb;
		$applied = array();
		if ( ! class_exists( '\\Emsfb\\Payments\\Store' ) ) {
			$f = __DIR__ . '/payments/class-Emsfb-payment-store.php';
			if ( ! is_readable( $f ) ) {
				return $applied;
			}
			require_once $f;
		}
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return $applied;
		}
		$collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		foreach ( \Emsfb\Payments\Store::ddl( $wpdb->prefix, $collate ) as $short => $sql ) {
			$table = $wpdb->prefix . $short;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				continue;
			}
			self::create_table_if_absent( $sql );
			$applied[] = "{$short}: CREATE TABLE";
		}
		// The Payments screen and its AJAX actions check this capability (administrators only).
		if ( function_exists( 'get_role' ) ) {
			$role = get_role( 'administrator' );
			if ( $role && ! $role->has_cap( 'Emsfb_payments' ) ) {
				$role->add_cap( 'Emsfb_payments' );
			}
		}
		return $applied;
	}

	/** F03: CREATE TABLE statement of emsfb_uniq_ (dbDelta format). */
	public static function f03_ddl( $prefix, $charset_collate = '' ) {
		if ( ! class_exists( '\\Emsfb\\Unique_Values' ) && is_readable( __DIR__ . '/class-Emsfb-unique.php' ) ) {
			require_once __DIR__ . '/class-Emsfb-unique.php';
		}
		return class_exists( '\\Emsfb\\Unique_Values' ) ? array( 'emsfb_uniq_' => \Emsfb\Unique_Values::ddl( $prefix, $charset_collate ) ) : array();
	}

	/** F03, DB version 1.7: emsfb_uniq_ when missing (idempotent). */
	public static function upgrade_f03_schema() {
		global $wpdb;
		$applied = array();
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return $applied;
		}
		$collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		foreach ( self::f03_ddl( $wpdb->prefix, $collate ) as $short => $sql ) {
			$table = $wpdb->prefix . $short;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				continue;
			}
			self::create_table_if_absent( $sql );
			$applied[] = "{$short}: CREATE TABLE";
		}
		if ( class_exists( '\\Emsfb\\Unique_Values' ) ) {
			\Emsfb\Unique_Values::reset_ready();
		}
		return $applied;
	}

	/** F02: CREATE TABLE statement of emsfb_vrfy_ (dbDelta format). */
	public static function f02_ddl( $prefix, $charset_collate = '' ) {
		if ( ! class_exists( '\\Emsfb\\Verification' ) && is_readable( __DIR__ . '/class-Emsfb-verification.php' ) ) {
			if ( ! interface_exists( '\\Emsfb\\Verification\\Provider' ) && is_readable( __DIR__ . '/verification/interface-provider.php' ) ) {
				require_once __DIR__ . '/verification/interface-provider.php';
			}
			require_once __DIR__ . '/class-Emsfb-verification.php';
		}
		return class_exists( '\\Emsfb\\Verification' ) ? array( 'emsfb_vrfy_' => \Emsfb\Verification::ddl( $prefix, $charset_collate ) ) : array();
	}

	/** F02, DB version 1.8: emsfb_vrfy_ when missing (idempotent; emsfb_vrfy_rl_ is F11's). */
	public static function upgrade_f02_schema() {
		global $wpdb;
		$applied = array();
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return $applied;
		}
		$collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		foreach ( self::f02_ddl( $wpdb->prefix, $collate ) as $short => $sql ) {
			$table = $wpdb->prefix . $short;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				continue;
			}
			self::create_table_if_absent( $sql );
			$applied[] = "{$short}: CREATE TABLE";
		}
		if ( class_exists( '\\Emsfb\\Verification' ) ) {
			\Emsfb\Verification::reset_ready();
		}
		return $applied;
	}

	/** F11: CREATE TABLE statements of emsfb_vrfy_rl_ and emsfb_draft_ (dbDelta format). */
	public static function f11_ddl( $prefix, $charset_collate = '' ) {
		foreach ( array( 'Rate_Limit' => 'class-Emsfb-rate-limit.php', 'Drafts' => 'class-Emsfb-drafts.php' ) as $class => $file ) {
			if ( ! class_exists( '\\Emsfb\\' . $class ) && is_readable( __DIR__ . '/' . $file ) ) {
				require_once __DIR__ . '/' . $file;
			}
		}
		$out = array();
		if ( class_exists( '\\Emsfb\\Rate_Limit' ) ) $out['emsfb_vrfy_rl_'] = \Emsfb\Rate_Limit::ddl( $prefix, $charset_collate );
		if ( class_exists( '\\Emsfb\\Drafts' ) ) $out['emsfb_draft_'] = \Emsfb\Drafts::ddl( $prefix, $charset_collate );
		return $out;
	}

	/**
	 * F11, DB version 1.6. Each step looks first, so a second run changes nothing:
	 *  - emsfb_vrfy_rl_ and emsfb_draft_ are created when missing;
	 *  - emsfb_vrfy_rl_ gets KEY scope when an earlier copy (F02's DDL) lacks it;
	 *  - emsfb_msg_ gets KEY form_id only when no index starts with form_id (F13's
	 *    form_date (form_id, date) already serves every per-form count and list).
	 *
	 * @return array<int, string> Applied steps.
	 */
	public static function upgrade_f11_schema() {
		global $wpdb;
		$applied = array();
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		foreach ( self::f11_ddl( $wpdb->prefix, $collate ) as $short => $sql ) {
			$table = $wpdb->prefix . $short;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				continue;
			}
			if ( ! function_exists( 'dbDelta' ) ) {
				continue;
			}
			self::create_table_if_absent( $sql );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$applied[] = "{$short}: CREATE TABLE";
			}
		}
		if ( class_exists( '\\Emsfb\\Rate_Limit' ) ) {
			\Emsfb\Rate_Limit::reset_ready();
		}
		$keys = array(
			'emsfb_vrfy_rl_' => array( 'scope' => array( 'scope', '(`scope`)' ) ),
			// form_date is F13's (form_id, date): it serves the same lookups.
			'emsfb_msg_'     => array( 'form_id' => array( 'form_id', '(`form_id`)', 'form_date' ) ),
		);
		foreach ( $keys as $short => $wanted ) {
			$table = $wpdb->prefix . $short;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}
			$present = array();
			$first   = array();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ) as $row ) {
				if ( ! isset( $row['Key_name'] ) ) continue;
				$present[ $row['Key_name'] ] = true;
				if ( isset( $row['Seq_in_index'], $row['Column_name'] ) && (int) $row['Seq_in_index'] === 1 ) {
					$first[ $row['Column_name'] ] = true;
				}
			}
			foreach ( $wanted as $key_name => $def ) {
				// Any index whose first column is the wanted one already serves the lookup.
				if ( isset( $present[ $key_name ] ) || isset( $first[ $def[0] ] ) || ( isset( $def[2] ) && isset( $present[ $def[2] ] ) ) ) continue;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, key from the literal map above
				if ( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD KEY `{$key_name}` {$def[1]}" ) ) {
					$applied[] = "{$short}: ADD KEY {$key_name}";
				}
			}
		}
		return $applied;
	}

	/**
	 * F13: columns and keys on emsfb_msg_ / emsfb_rsp_ (DB version 1.4).
	 * Idempotent: SHOW COLUMNS / SHOW INDEX first, ALTER only what is missing.
	 *
	 * @return array<int, string> Applied steps.
	 */
	private static function upgrade_privacy_schema() {
		global $wpdb;

		$applied = array();
		$steps   = array(
			'emsfb_msg_' => array(
				'columns' => array(
					'uid'       => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
					'prv_state' => 'TINYINT NOT NULL DEFAULT 0',
				),
				'keys'    => array(
					'form_date' => '(`form_id`,`date`)',
					'track'     => '(`track`)',
					'date'      => '(`date`)',
				),
			),
			'emsfb_rsp_' => array(
				'columns' => array(),
				'keys'    => array(
					'msg_id' => '(`msg_id`)',
				),
			),
		);

		foreach ( $steps as $short => $step ) {
			$table = $wpdb->prefix . $short;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				continue;
			}
			foreach ( $step['columns'] as $column => $definition ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				$has = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, column and definition from the literal map above
				if ( empty( $has ) && false !== $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" ) ) {
					$applied[] = "{$short}: ADD COLUMN {$column}";
				}
			}
			if ( empty( $step['keys'] ) ) {
				continue;
			}
			$present = array();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ) as $row ) {
				if ( isset( $row['Key_name'] ) ) {
					$present[ $row['Key_name'] ] = true;
				}
			}
			foreach ( $step['keys'] as $key_name => $columns ) {
				if ( isset( $present[ $key_name ] ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, key from the literal map above
				if ( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD KEY `{$key_name}` {$columns}" ) ) {
					$applied[] = "{$short}: ADD KEY {$key_name}";
				}
			}
		}

		return $applied;
	}

}
