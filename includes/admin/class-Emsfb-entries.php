<?php
/**
 * Entries management helpers for the dashboard (F14).
 *
 * docs/feature-gaps/F14-entries-management.fa.md puts the entries list,
 * filters, bulk actions and notes in this class. For now it only holds the
 * small, pure helpers the existing dashboard handlers share.
 *
 * @package Emsfb
 */

namespace Emsfb;

defined('ABSPATH') || exit;

final class Entries {

    /**
     * Format an entry or reply date for display.
     *
     * Entries are written with wp_date('Y-m-d H:i:s'), i.e. in the site's
     * time zone. strtotime() reads that string as UTC (WordPress pins PHP to
     * UTC), and wp_date() then adds the site offset a second time, so the old
     * list was off by the site's UTC offset. Parse it in the site zone instead.
     *
     * @param string $mysql_date 'Y-m-d H:i:s' in the site time zone.
     * @param string $format     PHP date format; defaults to the site's date + time format.
     * @return string Formatted date, or the input unchanged when it cannot be parsed.
     */
    public static function format_entry_date($mysql_date, $format = '') {
        if (!is_string($mysql_date) || $mysql_date === '' || strpos($mysql_date, '0000-00-00') === 0) {
            return is_string($mysql_date) ? $mysql_date : '';
        }
        if ($format === '') {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $mysql_date, wp_timezone());
        if ($date === false) {
            return $mysql_date;
        }

        $formatted = wp_date($format, $date->getTimestamp());
        return is_string($formatted) ? $formatted : $mysql_date;
    }

    /**
     * Display name for the author of a reply (emsfb_rsp_.rsp_by).
     *
     * rsp_by is a user id (> 0), -1 for the support team, and 0 for a guest.
     * A user who has since been deleted is shown as a guest instead of
     * reading a property from false.
     *
     * @param int|string $rsp_by       Stored author.
     * @param string     $guest_label  Label for guests and deleted users.
     * @param string     $support_label Label for the support team.
     * @return string
     */
    public static function responder_name($rsp_by, $guest_label, $support_label) {
        $id = (int) $rsp_by;
        if ($id === -1) {
            return (string) $support_label;
        }
        if ($id > 0) {
            $user = get_user_by('id', $id);
            if ($user && isset($user->display_name) && $user->display_name !== '') {
                return (string) $user->display_name;
            }
        }
        return (string) $guest_label;
    }
}
