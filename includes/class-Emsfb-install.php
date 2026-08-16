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
							PRIMARY KEY  (msg_id)
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
							PRIMARY KEY  (rsp_id)
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

			$v = $wpdb->get_var( $wpdb->prepare( "SELECT setting FROM %i ORDER BY id DESC LIMIT 1", $table_name_stng ) );
			$rand = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
			// smtp (the "This site can send emails" switch) always starts OFF on a new
			// install: no notification email is sent until the admin verifies delivery
			// and turns it on. See Email_Monitor::mark_email_ready().
			if ($v === NULL) {
				$settings = (object) array(
					'activeCode' => '',
					'siteKey' => '',
					'secretKey' => '',
					'emailSupporter' => $eml,
					'apiKeyMap' => '',
					'smtp' => false,
					'bootstrap' => false,
					'emailTemp' => \Emsfb::get_default_email_template_efb(),
					'email_key' => $rand,
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

}
