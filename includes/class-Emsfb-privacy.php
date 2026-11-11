<?php
/**
 * Privacy and data lifecycle helpers (F13).
 *
 * This first slice holds the shared purge used by every place that deletes
 * entries: the single-entry delete, the bulk delete and form deletion in the
 * dashboard. Retention cron, anonymisation, the IP mode and the WordPress
 * exporter/eraser from docs/feature-gaps/F13-privacy-retention.fa.md build on
 * top of purge_entries() and are not here yet.
 *
 * @package Emsfb
 */

namespace Emsfb;

defined('ABSPATH') || exit;

final class Privacy {

    /** Same pattern claim_uploaded_files_efb() uses for generated upload names. */
    const FILE_PATTERN = '/efb-(?:PLG|rec)-[0-9]{6}-[A-Za-z0-9]+\.[A-Za-z0-9]{1,10}/';

    /** Rows read and deleted per query (F13 §6.3). */
    const BATCH = 100;

    /**
     * Delete entries together with their replies and uploaded files.
     *
     * For each batch of ids: read the entry and reply contents, collect the
     * file names they point to, delete the emsfb_msg_ rows, then the
     * emsfb_rsp_ rows of the same ids. Files are removed only after every
     * batch is done, and only when no remaining entry or reply still names
     * the same file (a forwarded or copied entry can share an upload).
     *
     * Modes: 'delete' only. 'anonymize' (F13 §4.3) needs the prv_state column
     * and arrives with F13; until then it is a no-op that reports zeros, so a
     * caller can never lose data by asking for the wrong mode.
     *
     * @param int[]  $msg_ids    Entry ids.
     * @param string $mode       'delete'.
     * @param bool   $with_files Also remove the uploaded files.
     * @return array{deleted:int, anonymized:int, files:int}
     */
    public static function purge_entries(array $msg_ids, $mode = 'delete', $with_files = true) {
        global $wpdb;

        $result = array('deleted' => 0, 'anonymized' => 0, 'files' => 0);

        $ids = array();
        foreach ($msg_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if (empty($ids) || $mode !== 'delete') {
            return $result;
        }

        $msg_table = $wpdb->prefix . 'emsfb_msg_';
        $rsp_table = $wpdb->prefix . 'emsfb_rsp_';
        $files     = array();
        $purged    = array();

        foreach (array_chunk($ids, self::BATCH) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '%d'));

            $chunk_files = array();
            if ($with_files) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
                $contents = array_merge(
                    (array) $wpdb->get_col($wpdb->prepare("SELECT content FROM `{$msg_table}` WHERE msg_id IN ({$in})", ...$chunk)),
                    (array) $wpdb->get_col($wpdb->prepare("SELECT content FROM `{$rsp_table}` WHERE msg_id IN ({$in})", ...$chunk))
                );
                // phpcs:enable
                foreach ($contents as $content) {
                    self::collect_files($content, $chunk_files);
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see above
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM `{$msg_table}` WHERE msg_id IN ({$in})", ...$chunk));
            if ($deleted === false) {
                // Rows still exist, so their files must stay too.
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see above
            $wpdb->query($wpdb->prepare("DELETE FROM `{$rsp_table}` WHERE msg_id IN ({$in})", ...$chunk));

            $result['deleted'] += (int) $deleted;
            $purged = array_merge($purged, $chunk);
            foreach ($chunk_files as $basename => $urls) {
                $files[$basename] = isset($files[$basename]) ? array_merge($files[$basename], $urls) : $urls;
            }
        }

        if ($with_files && !empty($files)) {
            $result['files'] = self::delete_unreferenced_files($files);
        }

        if (!empty($purged)) {
            /**
             * Entries were removed (or, later, anonymised).
             *
             * Tables keyed by msg_id (F14 notes and change log) clean up here.
             *
             * @param int[]  $msg_ids Ids whose rows were processed.
             * @param string $mode    'delete'.
             */
            do_action('efb_entries_purged', $purged, $mode);
        }

        return $result;
    }

    /**
     * Collect plugin-generated upload names and the addresses they appear at.
     *
     * Both the stored JSON's "url" keys and a plain scan of the text are used:
     * replies and older entries do not always keep the address under "url",
     * and JSON may escape the slashes.
     *
     * @param mixed                        $content Stored entry or reply content.
     * @param array<string, string[]>      $files   basename => candidate URLs (by reference).
     * @return void
     */
    public static function collect_files($content, array &$files) {
        if (!is_string($content) || $content === '') {
            return;
        }

        $text = str_replace('\\/', '/', $content);
        if (!preg_match_all(self::FILE_PATTERN, $text, $names)) {
            return;
        }
        foreach ($names[0] as $basename) {
            if (!isset($files[$basename])) {
                $files[$basename] = array();
            }
        }

        $urls = array();
        if (preg_match_all('#(?:https?:)?//[^\s"\'<>,\\\\]+#i', $text, $found)) {
            $urls = $found[0];
        }
        $decoded = function_exists('emsfb_json_decode_efb') ? \emsfb_json_decode_efb($content, true) : json_decode($content, true);
        if (is_array($decoded)) {
            array_walk_recursive($decoded, function ($value, $key) use (&$urls) {
                if ($key === 'url' && is_string($value) && $value !== '') {
                    $urls[] = $value;
                }
            });
        }

        foreach ($urls as $url) {
            $url = (string) $url;
            if (preg_match(self::FILE_PATTERN, $url, $m) && isset($files[$m[0]]) && !in_array($url, $files[$m[0]], true)) {
                $files[$m[0]][] = $url;
            }
        }
    }

    /**
     * Remove files that no remaining entry or reply refers to.
     *
     * @param array<string, string[]> $files basename => candidate URLs.
     * @return int Files removed from disk.
     */
    private static function delete_unreferenced_files(array $files) {
        global $wpdb;

        if (!class_exists('\\Emsfb\\Upload_Guard')) {
            return 0;
        }

        $uploads   = wp_upload_dir(null, false);
        $baseurl   = !empty($uploads['baseurl']) ? trailingslashit($uploads['baseurl']) : '';
        $msg_table = $wpdb->prefix . 'emsfb_msg_';
        $rsp_table = $wpdb->prefix . 'emsfb_rsp_';
        $removed   = 0;

        foreach ($files as $basename => $urls) {
            $basename = (string) $basename;
            $like     = '%' . $wpdb->esc_like($basename) . '%';
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM `{$msg_table}` WHERE content LIKE %s", $like)) > 0) {
                continue;
            }
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM `{$rsp_table}` WHERE content LIKE %s", $like)) > 0) {
                continue;
            }
            // phpcs:enable

            // Without an address (a name in free text), try the flat layout.
            if ($baseurl !== '') {
                $urls[] = $baseurl . $basename;
            }

            foreach (array_unique($urls) as $url) {
                $path = Upload_Guard::path_from_url($url);
                if ($path === false || wp_basename($path) !== $basename) {
                    continue;
                }
                wp_delete_file($path);
                if (!file_exists($path)) {
                    $removed++;
                    Upload_Guard::release_response_attachment_reservations(array($basename));
                }
                break;
            }
        }

        return $removed;
    }
}
