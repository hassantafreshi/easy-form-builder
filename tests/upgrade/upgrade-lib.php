<?php
/**
 * UPG-01, run through WP-CLI by tests/upgrade/run-upgrade-431.sh (`wp eval-file upgrade-lib.php <command> …`):
 *   seed <entries> <replies>      a 4.3.1 site's data: 6 forms (contact, registration, legacy Stripe one-time and
 *                                 subscription, PayPal, Persia Pay), <entries> entries over two years, <replies>
 *                                 replies, sessions, the Stripe add-on's emsfb_pay_ rows in its old decimal(12,2)
 *                                 shape, custom Localization texts, payment and reCAPTCHA keys (placeholders)
 *   snapshot <out.json> [base]    row counts, columns, keys, the DB version, the settings, and a sha256 of every row
 *                                 of each table over its columns (over the base snapshot's columns when given, so a
 *                                 column the upgrade adds does not change the checksum of the data that was there)
 *   rerun <out.json>              Install::upgrade_schema() once more: what it applied (must be nothing)
 *   compare <before> <after> <after-cron> <rerun> <timing> <max seconds> <php errors file> <result.json>
 *                                 prints [PASS] / [FAIL] lines and RESULTS, writes the result
 * Throw-away sites only: seed writes a lot of rows.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run through WP-CLI\n"); exit(1); }

global $wpdb;
$efb_upg_args = isset($args) && is_array($args) ? array_values($args) : array();
$efb_upg_cmd  = isset($efb_upg_args[0]) ? $efb_upg_args[0] : '';
$p = $wpdb->prefix;
$core_tables = array('emsfb_form', 'emsfb_msg_', 'emsfb_rsp_', 'emsfb_setting', 'emsfb_stts_', 'emsfb_pay_');

/** A 4.3.1 form's JSON: row 0 the form, a step, the fields. $pay = '' | stripe | paypal | persiaPay. */
function efb_upg_form_json($name, $type, $pay = '', $subscription = false) {
	$base = array('step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '');
	$row0 = array('type' => $pay !== '' ? 'payment' : $type, 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_state' => 'single', 'show_pro_bar' => '1', 'email_to' => 'em', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array('icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code'), 'dShowBg' => '1');
	if ($pay !== '') {
		$row0['getway'] = $pay;
		$row0['currency'] = $pay === 'persiaPay' ? 'irr' : 'usd';
		$row0['paymentmethod'] = $subscription ? 'monthly' : 'charge';
		if ($pay === 'persiaPay') $row0['persiaPay'] = 'zarinPal';
	}
	$rows = array($row0, array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'visible' => 1));
	$rows[] = array_merge($base, array('id_' => 'em', 'dataId' => 'em-id', 'type' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1));
	$rows[] = array_merge($base, array('id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => 'Name', 'amount' => 3));
	if ($pay !== '') {
		$rows[] = array_merge($base, array('id_' => 'rad', 'dataId' => 'rad-id', 'type' => 'payRadio', 'name' => 'Ticket', 'amount' => 4, 'required' => 1));
		$rows[] = array('id_' => 'o1', 'dataId' => 'o1-id', 'parent' => 'rad', 'type' => 'option', 'value' => 'Adult', 'id_op' => 'o1', 'step' => 1, 'amount' => 5, 'price' => $pay === 'persiaPay' ? '250000' : '25');
		$rows[] = array('id_' => 'o2', 'dataId' => 'o2-id', 'parent' => 'rad', 'type' => 'option', 'value' => 'Child', 'id_op' => 'o2', 'step' => 1, 'amount' => 5, 'price' => $pay === 'persiaPay' ? '125000' : '12.5');
		$rows[] = array_merge($base, array('id_' => 'pf', 'dataId' => 'pf-id', 'type' => $pay, 'name' => ucfirst($pay), 'amount' => 6));
	} else {
		$rows[] = array_merge($base, array('id_' => 'msg', 'dataId' => 'msg-id', 'type' => 'textarea', 'name' => 'Message', 'amount' => 4));
		if ($type === 'register') $rows[] = array_merge($base, array('id_' => 'pw', 'dataId' => 'pw-id', 'type' => 'password', 'name' => 'Password', 'amount' => 5));
	}
	return wp_json_encode($rows);
}

/** sha256 of every row of $table over $cols, in primary key order, read in pages. */
function efb_upg_table_hash($table, array $cols, $pk) {
	global $wpdb;
	$h = hash_init('sha256');
	$last = null; $n = 0;
	$list = '`' . implode('`,`', $cols) . '`';
	do {
		$where = $last === null ? '' : $wpdb->prepare(" WHERE `$pk` > %d", $last);
		$rows = $wpdb->get_results("SELECT `$pk` AS __pk, $list FROM `$table`$where ORDER BY `$pk` LIMIT 5000", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ((array) $rows as $r) {
			$last = (int) $r['__pk'];
			unset($r['__pk']);
			hash_update($h, json_encode(array_values($r)) . "\n");
			$n++;
		}
	} while ($rows && count($rows) === 5000);
	return array('rows' => $n, 'sha256' => hash_final($h));
}

switch ($efb_upg_cmd) {
	case 'seed':
		$n_entries = isset($efb_upg_args[1]) ? max(1, (int) $efb_upg_args[1]) : 100000;
		$n_replies = isset($efb_upg_args[2]) ? max(0, (int) $efb_upg_args[2]) : 20000;
		mt_srand(431);
		$forms = array(
			array('Contact us', 'form', ''), array('Join the club', 'register', ''), array('Tickets (Stripe)', 'payment', 'stripe'),
			array('Membership (Stripe monthly)', 'payment', 'stripe', true), array('Donation (PayPal)', 'payment', 'paypal'), array('Course (Persia Pay)', 'payment', 'persiaPay'),
		);
		$ids = array();
		foreach ($forms as $f) {
			$wpdb->insert($p . 'emsfb_form', array('form_name' => $f[0], 'form_structer' => efb_upg_form_json($f[0], $f[1], $f[2], !empty($f[3])), 'form_email' => 'owner@example.test', 'form_type' => $f[2] !== '' ? 'payment' : $f[1], 'form_created_by' => '1', 'form_access_by' => '1', 'form_create_date' => '2024-11-01 10:00:00'));
			$ids[] = array((int) $wpdb->insert_id, $f[0], $f[2]);
		}
		$t0 = microtime(true);
		$base_ts = strtotime('2024-11-25 00:00:00');
		$batch = array();
		for ($i = 1; $i <= $n_entries; $i++) {
			$f = $ids[$i % count($ids)];
			$content = array(
				array('id_' => 'em', 'name' => 'Email', 'type' => 'email', 'value' => "visitor$i@example.test", 'amount' => 2),
				array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => "Visitor $i \"Q\" O'Neil – نام", 'amount' => 3),
			);
			if ($f[2] !== '') $content[] = array('id_' => 'rad', 'name' => 'Ticket', 'type' => 'payRadio', 'value' => $i % 2 ? 'Adult' : 'Child', 'price' => $i % 2 ? 25 : 12.5, 'amount' => 4);
			else $content[] = array('id_' => 'msg', 'name' => 'Message', 'type' => 'textarea', 'value' => str_repeat("Line of a longer message $i. ", 1 + $i % 6), 'amount' => 4);
			$date = gmdate('Y-m-d H:i:s', $base_ts + (int) floor($i * (2 * 365 * 86400) / $n_entries));
			$batch[] = $wpdb->prepare('(%d,%s,%s,%s,%s,%s,%s,%d,%s)', $f[0], sprintf('EF%010d', $i), '203.0.113.' . ($i % 250), $f[1], wp_json_encode($content), $date, $date, $i % 5, $i % 3 ? '1' : '');
			if (count($batch) === 1000 || $i === $n_entries) {
				$wpdb->query("INSERT INTO `{$p}emsfb_msg_` (form_id, track, ip, form_title_x, content, `date`, read_date, read_, read_by) VALUES " . implode(',', $batch)); // phpcs:ignore WordPress.DB.PreparedSQL
				$batch = array();
			}
		}
		$t_entries = microtime(true) - $t0;
		$max_msg = (int) $wpdb->get_var("SELECT MAX(msg_id) FROM `{$p}emsfb_msg_`");
		for ($i = 1; $i <= $n_replies; $i++) {
			$batch[] = $wpdb->prepare('(%d,%s,%s,%s,%s,%s,%d,%s,%d)', 1 + ($i * 7919) % $max_msg, '198.51.100.' . ($i % 250), wp_json_encode(array(array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'value' => "Reply $i", 'amount' => 0))), gmdate('Y-m-d H:i:s', $base_ts + $i * 600), '1', gmdate('Y-m-d H:i:s', $base_ts + $i * 600), 1, '127.0.0.1', $i % 2);
			if (count($batch) === 1000 || $i === $n_replies) {
				$wpdb->query("INSERT INTO `{$p}emsfb_rsp_` (msg_id, ip, content, `date`, read_by, read_date, read_, reader_ip, rsp_by) VALUES " . implode(',', $batch)); // phpcs:ignore WordPress.DB.PreparedSQL
				$batch = array();
			}
		}
		for ($i = 1; $i <= 5000; $i++) {
			$batch[] = $wpdb->prepare('(%s,%d,%d,%s,%s,%s,%s,%s,%d,%s,%d)', substr(md5("s$i"), 0, 21), $ids[$i % count($ids)][0], 1, gmdate('Y-m-d H:i:s', $base_ts + $i * 3600), 'v', '192.0.2.' . ($i % 250), 'Linux', 'Chrome', 0, '', 1);
			if (count($batch) === 1000) {
				$wpdb->query("INSERT INTO `{$p}emsfb_stts_` (sid, fid, type_, `date`, status, ip, os, browser, uid, tc, active) VALUES " . implode(',', $batch)); // phpcs:ignore WordPress.DB.PreparedSQL
				$batch = array();
			}
		}
		// The Stripe / PayPal add-ons' payments table as 4.3.1's add-ons made it (amount decimal(12,2)).
		$wpdb->query("CREATE TABLE IF NOT EXISTS `{$p}emsfb_pay_` (id int(11) NOT NULL AUTO_INCREMENT, form_id int(11) NOT NULL DEFAULT 0, track varchar(20) NOT NULL DEFAULT '', gateway varchar(30) NOT NULL DEFAULT '', payment_type varchar(30) NOT NULL DEFAULT 'one-time', transaction_id varchar(100) NOT NULL DEFAULT '', subscription_id varchar(100) NOT NULL DEFAULT '', amount decimal(12,2) NOT NULL DEFAULT 0.00, currency varchar(10) NOT NULL DEFAULT 'USD', status varchar(30) NOT NULL DEFAULT 'pending', payer_email varchar(200) NOT NULL DEFAULT '', meta MEDIUMTEXT, created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY idx_track (track)) " . $wpdb->get_charset_collate()); // phpcs:ignore WordPress.DB.PreparedSQL
		for ($i = 1; $i <= 2000; $i++) {
			$gw = $i % 3 === 0 ? 'paypal' : 'stripe';
			$wpdb->insert($p . 'emsfb_pay_', array('form_id' => $ids[$gw === 'paypal' ? 4 : 2][0], 'track' => sprintf('EF%010d', $i * 3), 'gateway' => $gw, 'payment_type' => $i % 10 === 0 ? 'subscription' : 'one-time', 'transaction_id' => ($gw === 'paypal' ? 'PAYID-' : 'pi_test_') . $i, 'amount' => $i % 2 ? '25.00' : '12.50', 'currency' => 'USD', 'status' => $i % 7 === 0 ? 'refunded' : 'paid', 'payer_email' => "visitor$i@example.test", 'meta' => '{}', 'created_at' => gmdate('Y-m-d H:i:s', $base_ts + $i * 7200)));
		}
		// Custom Localization texts and keys in the settings row, the way 4.3.1 saved them (placeholders only).
		$row = $wpdb->get_row("SELECT id, setting FROM `{$p}emsfb_setting` ORDER BY id DESC LIMIT 1");
		$s = $row ? json_decode($row->setting) : null;
		if (!is_object($s)) $s = new stdClass();
		if (!isset($s->text) || !is_object($s->text)) $s->text = new stdClass();
		$s->text->sending = 'Sending your answers to the club…';
		$s->text->done = 'Thanks, we have it!';
		$s->text->trackingCode = 'Your club code';
		$s->stripePKey = 'pk_test_UPGRADE_PLACEHOLDER';
		$s->stripeSKey = 'sk_test_UPGRADE_PLACEHOLDER';
		$s->paypalPKey = 'PAYPAL_CLIENT_PLACEHOLDER';
		$s->paypalSKey = 'PAYPAL_SECRET_PLACEHOLDER';
		$s->siteKey = 'rc-site-placeholder';
		$s->secretKey = 'rc-secret-placeholder';
		$s->emailSupporter = 'owner@example.test';
		if ($row) $wpdb->update($p . 'emsfb_setting', array('setting' => wp_json_encode($s)), array('id' => (int) $row->id));
		else $wpdb->insert($p . 'emsfb_setting', array('setting' => wp_json_encode($s), 'edit_by' => '1', 'email' => 'owner@example.test'));
		echo wp_json_encode(array('forms' => count($ids), 'entries' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$p}emsfb_msg_`"), 'replies' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$p}emsfb_rsp_`"), 'entries_seconds' => round($t_entries, 1), 'msg_table_mb' => round((float) $wpdb->get_var($wpdb->prepare('SELECT (data_length + index_length) / 1048576 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s', $p . 'emsfb_msg_')), 1))) . "\n";
		break;

	case 'snapshot':
		$out = isset($efb_upg_args[1]) ? $efb_upg_args[1] : '';
		$base = isset($efb_upg_args[2]) && is_readable($efb_upg_args[2]) ? json_decode((string) file_get_contents($efb_upg_args[2]), true) : null;
		$snap = array('db_version' => get_option('Emsfb_db_version', ''), 'tables' => array(), 'emsfb_tables' => array());
		foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($p . 'emsfb') . '%')) as $t) $snap['emsfb_tables'][] = substr($t, strlen($p));
		sort($snap['emsfb_tables']);
		foreach ($core_tables as $t) {
			if (!in_array($t, $snap['emsfb_tables'], true)) continue;
			$cols = array(); $pk = '';
			foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM `{$p}{$t}`", ARRAY_A) as $c) { $cols[] = $c['Field']; if ($c['Key'] === 'PRI') $pk = $c['Field']; } // phpcs:ignore WordPress.DB.PreparedSQL
			$keys = array();
			foreach ((array) $wpdb->get_results("SHOW INDEX FROM `{$p}{$t}`", ARRAY_A) as $k) $keys[$k['Key_name']] = true; // phpcs:ignore WordPress.DB.PreparedSQL
			$base_cols = $base && isset($base['tables'][$t]['columns']) ? array_values(array_intersect($base['tables'][$t]['columns'], $cols)) : $cols;
			$snap['tables'][$t] = array('columns' => $cols, 'keys' => array_keys($keys), 'pk' => $pk, 'base' => efb_upg_table_hash($p . $t, $base_cols, $pk), 'all' => efb_upg_table_hash($p . $t, $cols, $pk));
		}
		if (isset($snap['tables']['emsfb_msg_']) && in_array('entry_status', $snap['tables']['emsfb_msg_']['columns'], true)) {
			$snap['entry_defaults'] = 0 === (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$p}emsfb_msg_` WHERE entry_status <> 0 OR starred <> 0 OR trashed_at IS NOT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		$s = json_decode((string) $wpdb->get_var("SELECT setting FROM `{$p}emsfb_setting` ORDER BY id DESC LIMIT 1"), true);
		$snap['settings'] = array(
			'texts' => is_array($s) && isset($s['text']) ? array_intersect_key((array) $s['text'], array('sending' => 1, 'done' => 1, 'trackingCode' => 1)) : null,
			'keys'  => is_array($s) ? array_map(function ($k) use ($s) { return isset($s[$k]) ? hash('sha256', (string) $s[$k]) : null; }, array('stripePKey' => 'stripePKey', 'stripeSKey' => 'stripeSKey', 'paypalPKey' => 'paypalPKey', 'paypalSKey' => 'paypalSKey', 'siteKey' => 'siteKey', 'secretKey' => 'secretKey')) : null,
		);
		file_put_contents($out, wp_json_encode($snap, JSON_PRETTY_PRINT) . "\n");
		echo wp_json_encode(array('db_version' => $snap['db_version'], 'tables' => count($snap['emsfb_tables']), 'entries' => isset($snap['tables']['emsfb_msg_']) ? $snap['tables']['emsfb_msg_']['base']['rows'] : 0)) . "\n";
		break;

	case 'rerun':
		$t0 = microtime(true);
		$applied = class_exists('\Emsfb\Install') ? \Emsfb\Install::upgrade_schema() : array('Emsfb\Install missing');
		file_put_contents($efb_upg_args[1], wp_json_encode(array('applied' => array_values((array) $applied), 'seconds' => round(microtime(true) - $t0, 3), 'db_version' => get_option('Emsfb_db_version', ''))) . "\n");
		echo wp_json_encode(array('applied' => count((array) $applied))) . "\n";
		break;

	case 'compare':
		list(, $f_before, $f_after, $f_cron, $f_rerun, $f_timing, $max, $f_errors, $f_result) = array_pad($efb_upg_args, 9, '');
		$rd = function ($f) { return is_readable($f) ? json_decode((string) file_get_contents($f), true) : null; };
		$before = $rd($f_before); $after = $rd($f_after); $cron = $rd($f_cron); $rerun = $rd($f_rerun);
		$timing = get_option('efb_upgrade_test_timing', null);
		$errors = is_readable($f_errors) ? trim((string) file_get_contents($f_errors)) : '';
		$pass = 0; $fail = 0; $checks = array();
		$t = function ($label, $ok, $info = '') use (&$pass, &$fail, &$checks) {
			$checks[] = array('ok' => (bool) $ok, 'label' => $label, 'info' => $info);
			if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }
		};
		$t('the 4.3.1 site was on database version 1.3', $before && (string) $before['db_version'] === '1.3', $before ? (string) $before['db_version'] : 'no snapshot');
		$t('after the update the database version is EMSFB_DB_VERSION (' . EMSFB_DB_VERSION . ')', $after && (string) $after['db_version'] === (string) EMSFB_DB_VERSION, $after ? (string) $after['db_version'] : 'no snapshot');
		foreach (array('emsfb_form' => 'forms', 'emsfb_msg_' => 'entries', 'emsfb_rsp_' => 'replies', 'emsfb_stts_' => 'sessions', 'emsfb_pay_' => 'payments (the Stripe / PayPal add-ons\' table)', 'emsfb_setting' => 'the settings row') as $tb => $what) {
			$b = $before['tables'][$tb]['base'] ?? null; $a = $after['tables'][$tb]['base'] ?? null; $c = $cron['tables'][$tb]['base'] ?? null;
			$t("$what: the same rows and the same content after the update ({$tb}, " . ($b ? $b['rows'] : '?') . ' rows)', $b && $a && $b == $a, $b && $a ? "{$b['rows']}/{$b['sha256']} vs {$a['rows']}/{$a['sha256']}" : 'missing');
			$t("$what: still the same after the update's background jobs (WP-Cron)", $b && $c && $b == $c, $b && $c ? "{$c['rows']}/{$c['sha256']}" : 'missing');
		}
		$t('the custom Localization texts are kept', $before && $after && $before['settings']['texts'] === $after['settings']['texts'] && count((array) $before['settings']['texts']) === 3, wp_json_encode($after['settings']['texts'] ?? null));
		$t('the payment and reCAPTCHA keys are kept (compared by hash)', $before && $after && $before['settings']['keys'] === $after['settings']['keys']);
		$new_tables = $after ? array_values(array_diff($after['emsfb_tables'], $before['emsfb_tables'])) : array();
		$t('the update adds the 4.3.2 tables (' . count($new_tables) . ': ' . implode(', ', $new_tables) . ')', count($new_tables) >= 14 && !array_diff(array('emsfb_pmt_', 'emsfb_draft_', 'emsfb_uniq_', 'emsfb_vrfy_', 'emsfb_stock_', 'emsfb_msg_notes_', 'emsfb_msg_log_', 'emsfb_stats_', 'emsfb_form_rev_', 'emsfb_msg_own_'), $new_tables));
		$new_cols = $after ? array_values(array_diff($after['tables']['emsfb_msg_']['columns'], $before['tables']['emsfb_msg_']['columns'])) : array();
		$t('emsfb_msg_ gets its new columns (' . implode(', ', $new_cols) . ')', !array_diff(array('uid', 'prv_state', 'entry_status', 'starred', 'trashed_at', 'edited_at', 'owner_state', 'owner_state_at'), $new_cols));
		$t('every entry is active, not starred, not trashed (the new columns\' defaults)', $after && !empty($after['entry_defaults']));
		$t('a second run of the upgrade applies nothing', $rerun && $rerun['applied'] === array(), wp_json_encode($rerun['applied'] ?? null));
		$t('...and changes no row (all columns)', $after && $cron && array_map(function ($x) { return $x['all']; }, $after['tables']) == array_map(function ($x) { return $x['all']; }, $cron['tables']));
		$secs = is_array($timing) ? (float) $timing['seconds'] : -1;
		$t('the update ran in one request and was timed', is_array($timing) && $timing['from'] === '1.3' && (string) $timing['to'] === (string) EMSFB_DB_VERSION, wp_json_encode($timing));
		$t("the schema update took at most {$max} s (" . $secs . ' s)', $secs >= 0 && $secs <= (float) $max, (string) $secs);
		$t('no PHP error, warning, notice or deprecation during the update', $errors === '', substr($errors, 0, 400));
		$server = $wpdb->get_var('SELECT VERSION()');
		$result = array('server' => $server, 'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION, 'entries' => $before['tables']['emsfb_msg_']['base']['rows'] ?? 0, 'upgrade_seconds' => $secs, 'timing' => $timing, 'new_tables' => $new_tables, 'new_msg_columns' => $new_cols, 'checks' => $checks, 'passed' => $pass, 'failed' => $fail);
		file_put_contents($f_result, wp_json_encode($result, JSON_PRETTY_PRINT) . "\n");
		echo "server: $server, WordPress " . get_bloginfo('version') . ', PHP ' . PHP_VERSION . ", upgrade {$secs} s\n";
		echo "RESULTS: $pass passed, $fail failed\n";
		break;

	default:
		fwrite(STDERR, "usage: wp eval-file tests/upgrade/upgrade-lib.php seed|snapshot|rerun|compare …\n");
		exit(2);
}
