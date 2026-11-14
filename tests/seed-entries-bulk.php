<?php
/**
 * Not a test. F14: many entries for one form, for the list's timing and paging on a real site
 * (tests/test-entries-query-wp.php, through tests/e2e-wp/ctl.php f14-seed-bulk <form_id> <n>).
 *
 * Rows are written in multi-row INSERTs of 500 with the stored format of the plugin (escaped quotes), dates spread
 * over 400 days, a tenth starred, every 50th in the spam and every 60th in the trash, so the tabs have work to do.
 * Local test site only.
 */
if (!defined('ABSPATH')) exit(1);

function efb_seed_entries_bulk($form_id, $n) {
	global $wpdb;
	$t = $wpdb->prefix . 'emsfb_msg_';
	$form_id = (int) $form_id;
	$n = max(1, min(250000, (int) $n));
	$start = strtotime('2025-10-01 00:00:00');
	$done = 0;
	while ($done < $n) {
		$vals = array();
		for ($i = 0; $i < 500 && $done < $n; $i++, $done++) {
			$rows = array(
				array('id_' => 'blk_name', 'name' => 'Name', 'type' => 'text', 'value' => 'Bulk person ' . $done, 'amount' => 2),
				array('id_' => 'blk_city', 'name' => 'City', 'type' => 'text', 'value' => ($done % 7 === 0 ? 'Shiraz' : 'Tehran'), 'amount' => 3),
				array('type' => 'w_link', 'value' => home_url('/'), 'amount' => -1),
			);
			$content = str_replace('"', '\\"', wp_json_encode($rows, JSON_UNESCAPED_UNICODE));
			$date = gmdate('Y-m-d H:i:s', $start + ($done % 400) * 86400 + ($done % 86400));
			$status = $done % 50 === 0 ? 1 : ($done % 60 === 0 ? 2 : 0);
			$vals[] = $wpdb->prepare('(%d, %s, %s, %s, %s, %s, %s, %d, %s, %d, %d)', $form_id, 'BLK' . $done, '', 'Bulk', $content, $date, $date, $done % 3 === 0 ? 0 : 1, '', $status, $done % 10 === 0 ? 1 : 0);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- each tuple prepared above
		$wpdb->query("INSERT INTO `{$t}` (form_id, track, ip, form_title_x, content, `date`, read_date, read_, read_by, entry_status, starred) VALUES " . implode(',', $vals));
	}
	return $done;
}
