<?php

namespace Emsfb;

defined('ABSPATH') || exit;

/**
 * Single decision point for "may this file be written to disk, and may this
 * visitor write another one right now?".
 *
 * Before this class the same rules were copy-pasted into four handlers
 * (file_upload_api, the two file_upload_public methods, and the recorder path
 * helper) and had already drifted apart. Everything upload-related that is a
 * policy decision now lives here so the handlers only ask questions.
 *
 * Three independent layers, in the order they run:
 *
 *   1. SIZE     - a plugin-level ceiling that does not depend on php.ini. The
 *                 per-field "max_fsize" the form owner set in the builder is
 *                 authoritative; the host's own limit only lowers it further.
 *                 Previously this was enforced only in the browser, so the
 *                 field setting was advisory and a direct POST ignored it.
 *
 *   2. TYPE     - an extension allow-list joined to the real, sniffed MIME.
 *                 The old code trusted $_FILES['type'], which is just a header
 *                 the client sends, and paired it with a MIME allow-list broad
 *                 enough (text/plain) that a crafted payload could satisfy
 *                 both. The extension is now the primary key and the sniffed
 *                 MIME must match what that extension is allowed to contain.
 *                 The executable blocklist stays as a second, redundant net.
 *
 *   3. QUOTA    - how many files one visitor may push through in a window.
 *                 This is the part that works with no add-on installed. The
 *                 shape of the form sets the budget: a form is allowed its own
 *                 file-field count times a retry allowance, so a one-field
 *                 response box lands on 3 uploads and a four-field application
 *                 form lands on 12. Human Shield, when present, overrides the
 *                 numbers through the filters documented on each method.
 *
 * Everything a site owner might need to tune is a filter, so none of this
 * requires editing the plugin. See docs/uploads/ for the article version.
 */
class Upload_Guard {

    /** Rolling window for the per-visitor upload budget. */
    const DEFAULT_WINDOW_SECONDS = 3600;

    /** Uploads allowed per file field, i.e. the "changed my mind" allowance. */
    const DEFAULT_RETRY_ALLOWANCE = 3;

    /** Fallback per-file ceiling in MB, matching the form builder's own default. */
    const DEFAULT_MAX_MB = 20;

    /** Transient prefix for the rolling upload counters. */
    const QUOTA_PREFIX = 'emsfb_uq_';

    /** Option holding files written but not yet attached to a submission. */
    const PENDING_OPTION = 'emsfb_pending_uploads';

    /**
     * A short-lived reservation for an attachment uploaded into a Response
     * Box.  Unlike the pending-upload ledger this is intentionally per-file:
     * it proves that the stored file belongs to one particular support ticket
     * when the reply is finally saved.
     */
    const RESPONSE_ATTACHMENT_PREFIX = 'emsfb_response_attachment_';

    /** Response-box attachments may be submitted for up to thirty minutes. */
    const RESPONSE_ATTACHMENT_TTL = 1800;

    /** Cron hook that sweeps abandoned uploads. */
    const CLEANUP_HOOK = 'emsfb_cleanup_orphan_uploads';

    /** How long an unclaimed file is kept before the sweeper may remove it. */
    const DEFAULT_ORPHAN_TTL = 86400;

    /** Hard ceiling on ledger size so a flood cannot grow the option unbounded. */
    const PENDING_MAX_ROWS = 500;

    /**
     * Extensions that must never reach disk regardless of content.
     *
     * This is the second layer, not the first: the allow-list in
     * allowed_extension_mimes() already refuses anything not named there. The
     * blocklist exists so that a form owner who adds a custom extension list to
     * a field still cannot open a hole, and so that a future allow-list entry
     * cannot silently re-admit something executable.
     *
     * "pht", "phtm", "phps" and "php-s" are the variants that were missing and
     * that some Apache configurations still hand to the PHP handler.
     *
     * @return string[] Lowercase extensions, no leading dot.
     */
    public static function blocked_extensions() {
        $blocked = array(
            // PHP and every handler-adjacent spelling of it.
            'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
            'pht', 'phtm', 'phtml', 'phps', 'php-s', 'phar', 'inc', 'hphp',
            // Other server-side languages.
            'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'ashx', 'asmx', 'jsp', 'jspx', 'cfm',
            // Shells and native executables.
            'sh', 'bash', 'zsh', 'bat', 'cmd', 'com', 'exe', 'dll', 'msi', 'scr', 'jar',
            // Server configuration.
            'htaccess', 'htpasswd', 'user.ini', 'ini',
            // Markup that executes in the visitor's browser on the site's origin.
            'shtml', 'shtm', 'stm', 'html', 'htm', 'xhtml', 'xht', 'svg', 'svgz', 'xml', 'xsl',
        );

        /**
         * Extensions refused by every upload handler.
         *
         * Filter to add site-specific extensions. Removing entries is possible
         * but is the one change here that can open a remote-code-execution
         * path, so treat the shipped list as a floor.
         *
         * @param string[] $blocked Lowercase extensions without a dot.
         */
        $blocked = apply_filters('emsfb_upload_blocked_extensions', $blocked);

        return array_map('strtolower', array_filter((array) $blocked, 'is_string'));
    }

    /**
     * Extension allow-list joined to the real MIME types that extension may
     * legitimately contain.
     *
     * This replaces the old "trust $_FILES['type']" check. The lookup is by
     * extension because the extension is what ends up on disk and what the web
     * server uses to pick a handler; the sniffed MIME then has to agree with
     * it. A .pdf whose bytes sniff as text/plain is refused here, where before
     * it passed because text/plain was globally allowed.
     *
     * An empty array as the value means "extension allowed, MIME not asserted"
     * and is deliberately used for the archive formats, whose sniffed type
     * varies too much between libmagic builds to pin down.
     *
     * @return array<string,string[]> extension => acceptable sniffed MIME types.
     */
    public static function allowed_extension_mimes() {
        $map = array(
            // Images.
            'jpg'  => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'png'  => array('image/png'),
            'gif'  => array('image/gif'),
            'webp' => array('image/webp'),
            'bmp'  => array('image/bmp', 'image/x-ms-bmp'),
            'heic' => array('image/heic', 'image/heif', 'application/octet-stream'),
            'heif' => array('image/heif', 'image/heic', 'application/octet-stream'),

            // Documents.
            'pdf'  => array('application/pdf'),
            'txt'  => array('text/plain'),
            'csv'  => array('text/plain', 'text/csv', 'application/csv'),
            'rtf'  => array('application/rtf', 'text/rtf'),
            'doc'  => array('application/msword', 'application/vnd.ms-office', 'application/x-ole-storage'),
            'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
            'dot'  => array('application/msword', 'application/vnd.ms-office'),
            'dotx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/zip'),
            'xls'  => array('application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage'),
            'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
            'ppt'  => array('application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage'),
            'pptx' => array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'),
            'odt'  => array('application/vnd.oasis.opendocument.text', 'application/zip'),
            'ods'  => array('application/vnd.oasis.opendocument.spreadsheet', 'application/zip'),
            'odp'  => array('application/vnd.oasis.opendocument.presentation', 'application/zip'),

            // Audio.
            'mp3'  => array('audio/mpeg', 'audio/mp3', 'audio/mpg'),
            'wav'  => array('audio/wav', 'audio/x-wav', 'audio/wave'),
            'ogg'  => array('audio/ogg', 'application/ogg', 'video/ogg'),
            'oga'  => array('audio/ogg', 'application/ogg'),
            'm4a'  => array('audio/mp4', 'audio/x-m4a', 'video/mp4'),
            'aac'  => array('audio/aac', 'audio/x-aac', 'audio/mp4'),

            // Video.
            'mp4'  => array('video/mp4', 'audio/mp4'),
            'webm' => array('video/webm', 'audio/webm'),
            'mov'  => array('video/quicktime', 'video/mov'),
            'avi'  => array('video/x-msvideo', 'video/avi'),
            'mpeg' => array('video/mpeg'),
            'mpg'  => array('video/mpeg', 'video/mpg'),
            'mkv'  => array('video/x-matroska'),

            'pptm' => array('application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'application/zip'),

            // Archives. Sniffed type is build-dependent, so extension only.
            // The long tail here mirrors the "Zip" preset the form builder has
            // always offered; dropping any of it would reject files that used
            // to upload fine.
            'zip'   => array(),
            'rar'   => array(),
            '7z'    => array(),
            'gz'    => array(),
            'gzip'  => array(),
            'tgz'   => array(),
            'tar'   => array(),
            'bz'    => array(),
            'bz2'   => array(),
            'bzip'  => array(),
            'bzip2' => array(),
            'tbz'   => array(),
            'tbz2'  => array(),
            'tz'    => array(),
            'tz2'   => array(),
            'z'     => array(),
        );

        /**
         * Extensions the plugin will store, and the sniffed MIME types each may
         * hold. An empty array skips the MIME assertion for that extension.
         *
         * @param array<string,string[]> $map extension => MIME allow-list.
         */
        return apply_filters('emsfb_upload_allowed_extension_mimes', $map);
    }

    /**
     * Extensions behind each "Acceptable file types" preset in the builder.
     *
     * The dropdown offers All formats / Image / Media / Document / Zip, and
     * until now only the "Customize" option was enforced on the server: a
     * field set to "Document" would still store a .jpg, because the other
     * presets were checked in the browser only. These lists are the exact
     * server-side counterpart of filetype_efb in new-efb.js, so what the
     * browser refuses to offer, the server now also refuses to keep.
     *
     * "allformat" deliberately returns an empty list, meaning "do not narrow".
     * That is what the reply box on a tracked conversation uses: it has no
     * field definition of its own to read a preset from, so it falls through
     * to the global allow-list, exactly as its own client-side check does.
     *
     * @param string $preset Value stored in the field's `file` / `value` key.
     * @return string[] Extensions to narrow to; empty means no narrowing.
     */
    public static function preset_extensions($preset) {
        $preset = strtolower(trim((string) $preset));

        $presets = array(
            // Union of every other preset: no narrowing beyond the global list.
            'allformat' => array(),

            'image'    => array('png', 'jpg', 'jpeg', 'gif', 'heic', 'heif'),

            'media'    => array('mp3', 'wav', 'ogg', 'oga', 'webm', 'm4a', 'aac',
                                'mp4', 'mkv', 'avi', 'mpeg', 'mpg', 'mov'),

            'document' => array('xlsx', 'xls', 'doc', 'docx', 'dot', 'dotx',
                                'ppt', 'pptx', 'pptm', 'txt', 'pdf', 'rtf',
                                'odt', 'ods', 'odp'),

            'zip'      => array('zip', 'rar', '7z', 'tar', 'gz', 'gzip', 'tgz',
                                'bz', 'bz2', 'bzip', 'bzip2', 'tbz', 'tbz2',
                                'tz', 'tz2', 'z'),
        );

        /**
         * Extensions each builder preset narrows an upload field to.
         *
         * An empty array for a preset disables narrowing for it. Note that the
         * executable blocklist still applies afterwards, so widening a preset
         * here cannot re-admit something dangerous.
         *
         * @param array<string,string[]> $presets preset name => extensions.
         */
        $presets = apply_filters('emsfb_upload_preset_extensions', $presets);

        if (!isset($presets[$preset])) {
            return array(); // Unknown or absent preset: do not narrow.
        }

        return array_map('strtolower', (array) $presets[$preset]);
    }

    /**
     * Resolve the extension list a single upload field narrows to.
     *
     * @param object|array|null $field    Field definition from the structure.
     * @param string            $source   'form' or 'response'.
     * @return string[] Empty means "global allow-list only".
     */
    public static function field_extensions($field, $source = 'form') {
        /* The reply box has no field definition. Its client-side check runs
         * against "allformat", so the server matches that by not narrowing.
         * Sites that want the reply box restricted can return a list here. */
        if ($source === 'response' || $field === null) {
            /**
             * Extensions accepted by the reply box on a tracked conversation.
             *
             * Empty (the default) means the global allow-list applies, which
             * matches the reply box's own client-side behaviour. Return a list
             * such as array('pdf','jpg','png') to restrict it.
             *
             * @param string[] $extensions Default empty.
             */
            return array_map('strtolower', (array) apply_filters('emsfb_upload_response_box_extensions', array()));
        }

        $get = function ($key) use ($field) {
            if (is_object($field) && isset($field->$key)) return $field->$key;
            if (is_array($field) && isset($field[$key])) return $field[$key];
            return null;
        };

        /* The builder writes the chosen preset to both `file` and `value`;
         * `file` is the one the settings panel binds to, so it wins. */
        $preset = $get('file');
        if ($preset === null || $preset === '') {
            $preset = $get('value');
        }
        $preset = strtolower(trim((string) $preset));

        if ($preset === 'customize') {
            $custom = $get('file_ctype');
            if (!is_string($custom) || trim($custom) === '') {
                return array();
            }
            $list = explode(',', strtolower(str_replace(' ', '', $custom)));
            return array_values(array_filter(array_map(function ($e) {
                return ltrim(trim($e), '.');
            }, $list), 'strlen'));
        }

        return self::preset_extensions($preset);
    }

    /**
     * @param string $filename Original or generated file name.
     * @return string Lowercase extension without the dot, '' when absent.
     */
    public static function extension_of($filename) {
        $extension = pathinfo((string) $filename, PATHINFO_EXTENSION);
        return strtolower((string) $extension);
    }

    /**
     * @param string $extension Lowercase extension without a dot.
     * @return bool
     */
    public static function is_blocked_extension($extension) {
        return in_array(strtolower((string) $extension), self::blocked_extensions(), true);
    }

    /**
     * Decide whether an extension/sniffed-MIME pair may be stored.
     *
     * @param string       $extension Lowercase extension without a dot.
     * @param string|false $real_mime Sniffed MIME, or false when unavailable.
     * @param string[]     $field_extensions Per-field custom extension list, if the
     *                                       form owner configured one.
     * @return bool
     */
    public static function is_allowed_type($extension, $real_mime, $field_extensions = array()) {
        $extension = strtolower((string) $extension);

        if ($extension === '' || self::is_blocked_extension($extension)) {
            return false;
        }

        /* A per-field extension list narrows the allow-list, it never widens
         * it: the form owner can say "only PDF here", not "also .pht here". */
        if (!empty($field_extensions)) {
            $field_extensions = array_map('strtolower', array_filter((array) $field_extensions, 'is_string'));
            if (!in_array($extension, $field_extensions, true)) {
                return false;
            }
        }

        $map = self::allowed_extension_mimes();
        if (!isset($map[$extension])) {
            return false;
        }

        $expected = (array) $map[$extension];
        if (empty($expected)) {
            return true; // Extension trusted on its own; see allowed_extension_mimes().
        }

        /* No libmagic on this host: the extension allow-list plus the blocklist
         * are all we have, and both already passed. Refusing here would break
         * uploads on minimal PHP builds for no gain, since the byte-level check
         * is exactly the layer that is missing. */
        if ($real_mime === false || $real_mime === null || $real_mime === '') {
            return true;
        }

        return in_array(strtolower((string) $real_mime), array_map('strtolower', $expected), true);
    }

    /**
     * Read the real MIME type from the file's bytes.
     *
     * @param string $path Absolute path to the uploaded temp file.
     * @return string|false false when libmagic is unavailable or unreadable.
     */
    public static function sniff_mime($path) {
        if (!function_exists('emsfb_is_php_function_available_efb')
            || !emsfb_is_php_function_available_efb('finfo_open')) {
            return false;
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return false;
        }

        $mime = @finfo_file($finfo, $path);
        @finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : false;
    }

    /**
     * Per-file ceiling in bytes.
     *
     * The form owner's per-field setting wins, the host's limit can only lower
     * it, and a corrupted or hostile form structure cannot lift it above 1 GB.
     *
     * @param float|int|string|null $configured_mb Field's max_fsize, if any.
     * @param array                 $context       Upload context, passed to the filter.
     * @return int Bytes.
     */
    public static function max_bytes($configured_mb = null, $context = array()) {
        $mb = is_numeric($configured_mb) && (float) $configured_mb > 0
            ? (float) $configured_mb
            : self::DEFAULT_MAX_MB;

        $max_bytes = (int) min($mb * 1024 * 1024, 1024 * 1024 * 1024);

        $host_limit = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
        if ($host_limit > 0) {
            $max_bytes = min($max_bytes, $host_limit);
        }

        /**
         * Final per-file byte ceiling.
         *
         * @param int   $max_bytes Bytes.
         * @param array $context   'source', 'form_id', 'field_id'.
         */
        return (int) apply_filters('emsfb_upload_max_bytes', $max_bytes, $context);
    }

    /**
     * Count the fields in a form that can produce an upload.
     *
     * The quota budget is derived from this, so a form that grew a second
     * attachment field automatically gets a bigger budget with no setting to
     * change.
     *
     * @param mixed $structure Decoded form structure, or the raw escaped string
     *                         exactly as it is stored in form_structer.
     * @return int Number of file/dadfile/recorder fields, minimum 0.
     */
    public static function count_upload_fields($structure) {
        $types = array('file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder');

        if (is_string($structure)) {
            $decoded = json_decode(str_replace('\\', '', $structure));
            if (!is_array($decoded)) {
                /* Undecodable structure: fall back to counting the type markers
                 * in the raw string so a stored-escaping quirk cannot collapse
                 * a legitimate multi-upload form to a budget of one. */
                $count = 0;
                foreach ($types as $type) {
                    $count += substr_count($structure, '"type":"' . $type . '"');
                    $count += substr_count($structure, '\"type\":\"' . $type . '\"');
                }
                return $count;
            }
            $structure = $decoded;
        }

        if (!is_array($structure)) {
            return 0;
        }

        $count = 0;
        foreach ($structure as $field) {
            $type = '';
            if (is_object($field) && isset($field->type)) {
                $type = (string) $field->type;
            } elseif (is_array($field) && isset($field['type'])) {
                $type = (string) $field['type'];
            }
            if (in_array($type, $types, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Upload-field count for a published form, read from its stored structure.
     *
     * Lives here rather than in the upload handler because the Human Shield
     * rate limiter needs the same number: a form with four attachment fields
     * must be allowed four uploads in quick succession, or a visitor filling it
     * in normally trips a per-minute cap sized for a one-field form.
     *
     * Cached per request and in the object cache, because a rate-limit check
     * runs on every upload request and must not cost a query each time.
     *
     * @param int $form_id Published form id.
     * @return int Number of file/dadfile/recorder fields, 0 when unknown.
     */
    public static function upload_field_count_for_form($form_id) {
        static $memo = array();

        $form_id = (int) $form_id;
        if ($form_id < 1) {
            return 0;
        }

        if (isset($memo[$form_id])) {
            return $memo[$form_id];
        }

        $cache_key = 'upload_fields:' . $form_id;
        $cached    = wp_cache_get($cache_key, 'emsfb');
        if (false !== $cached && is_numeric($cached)) {
            $memo[$form_id] = (int) $cached;
            return $memo[$form_id];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'emsfb_form';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $structure = $wpdb->get_var($wpdb->prepare("SELECT form_structer FROM `{$table}` WHERE form_id = %d", $form_id));

        $count = self::count_upload_fields($structure);

        wp_cache_set($cache_key, $count, 'emsfb', 5 * MINUTE_IN_SECONDS);
        $memo[$form_id] = $count;

        return $count;
    }

    /**
     * How many uploads this visitor may make in one window.
     *
     * The budget is the form's own shape times a retry allowance, so it scales
     * with the form instead of being a number someone has to guess. A response
     * box has a single attachment slot and therefore lands on 3.
     *
     * @param array $context 'source' ('form'|'response'), 'form_id', 'fields'.
     * @return int Minimum 1.
     */
    public static function quota_limit($context = array()) {
        $source = isset($context['source']) ? (string) $context['source'] : 'form';
        $fields = isset($context['fields']) ? (int) $context['fields'] : 0;

        /* The response box has exactly one attachment slot and no form
         * structure to count, so its field count is fixed at one. */
        if ($source === 'response' || $fields < 1) {
            $fields = 1;
        }

        /**
         * Uploads allowed per file field before the window resets. Covers the
         * ordinary "picked the wrong file, uploaded again" case.
         *
         * @param int   $allowance Default 3.
         * @param array $context   Upload context.
         */
        $allowance = (int) apply_filters('emsfb_upload_retry_allowance', self::DEFAULT_RETRY_ALLOWANCE, $context);
        $allowance = max(1, $allowance);

        $limit = $fields * $allowance;

        /**
         * Total uploads allowed per visitor per window.
         *
         * Human Shield overrides this from its own settings when the add-on is
         * active; with no add-on the derived value above applies.
         *
         * @param int   $limit   Derived budget.
         * @param array $context 'source', 'form_id', 'fields', 'key'.
         */
        $limit = (int) apply_filters('emsfb_upload_quota_limit', $limit, $context);

        return max(1, $limit);
    }

    /**
     * @param array $context Upload context.
     * @return int Window length in seconds.
     */
    public static function quota_window($context = array()) {
        /**
         * Length of the rolling upload window in seconds.
         *
         * @param int   $seconds Default 3600.
         * @param array $context Upload context.
         */
        $window = (int) apply_filters('emsfb_upload_quota_window', self::DEFAULT_WINDOW_SECONDS, $context);
        return max(60, $window);
    }

    /**
     * Bucket key for the rolling counter.
     *
     * Keyed on the visitor's nonce first. The nonce is what identifies one
     * open form session, it is what the request must already carry, and it is
     * what the brief asks the response box to be metered on. Session id and
     * form id join the key so two different forms on one page keep separate
     * budgets. The IP is a last-resort component only, because it is shared
     * behind NAT and would otherwise let one visitor exhaust an office.
     *
     * @param array $context Upload context.
     * @return string Transient key.
     */
    public static function quota_key($context = array()) {
        $parts = array(
            isset($context['nonce']) ? (string) $context['nonce'] : '',
            isset($context['sid']) ? (string) $context['sid'] : '',
            (string) (isset($context['form_id']) ? (int) $context['form_id'] : 0),
            isset($context['source']) ? (string) $context['source'] : 'form',
        );

        $identity = implode('|', $parts);

        /* Neither a nonce nor a session reached us. Rather than handing every
         * such request its own fresh budget, fall back to the IP so the
         * anonymous case is still bounded. */
        if (trim($parts[0]) === '' && trim($parts[1]) === '') {
            $identity .= '|ip:' . (isset($context['ip']) ? (string) $context['ip'] : '');
        }

        return self::QUOTA_PREFIX . md5($identity);
    }

    /**
     * Read the current counter without touching it.
     *
     * @param array $context Upload context.
     * @return int
     */
    public static function quota_used($context = array()) {
        $value = get_transient(self::quota_key($context));
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Record one upload against the visitor's budget.
     *
     * Called only after the file has actually been written, so a rejected file
     * never costs the visitor part of their allowance.
     *
     * @param array $context Upload context.
     * @return int The new count.
     */
    public static function quota_consume($context = array()) {
        $key   = self::quota_key($context);
        $used  = self::quota_used($context);
        $used += 1;

        set_transient($key, $used, self::quota_window($context));

        return $used;
    }

    /**
     * Whether this visitor has budget left.
     *
     * @param array $context Upload context.
     * @return bool
     */
    public static function quota_allows($context = array()) {
        /**
         * Short-circuit the whole quota layer.
         *
         * @param bool  $enabled Default true.
         * @param array $context Upload context.
         */
        if (!apply_filters('emsfb_upload_quota_enabled', true, $context)) {
            return true;
        }

        return self::quota_used($context) < self::quota_limit($context);
    }

    /**
     * The message shown when the budget is spent.
     *
     * Phrased for the person who is trying to attach a CV, not for a log file:
     * it says what happened, what the limit is, and what to do next.
     *
     * @param array $context Upload context.
     * @return string
     */
    public static function quota_message($context = array()) {
        $limit   = self::quota_limit($context);
        $minutes = (int) ceil(self::quota_window($context) / 60);

        $message = sprintf(
            /* translators: 1: number of files allowed, 2: number of minutes in the window. */
            _n(
                'You can attach up to %1$d file here. Please wait about %2$d minutes before trying again.',
                'You can attach up to %1$d files here. Please wait about %2$d minutes before trying again.',
                $limit,
                'easy-form-builder'
            ),
            $limit,
            $minutes
        );

        /**
         * Message shown to a visitor who has used up their upload allowance.
         *
         * @param string $message Ready-to-display sentence.
         * @param array  $context Upload context.
         */
        return apply_filters('emsfb_upload_quota_message', $message, $context);
    }

    /**
     * The message shown when a file is over the size ceiling.
     *
     * @param int $max_bytes Ceiling that was exceeded.
     * @return string
     */
    public static function size_message($max_bytes) {
        return sprintf(
            /* translators: %s: human-readable maximum file size, e.g. "8 MB". */
            esc_html__('This file is too large. The maximum size for this field is %s.', 'easy-form-builder'),
            function_exists('size_format') ? size_format((int) $max_bytes) : ((int) round($max_bytes / 1048576) . ' MB')
        );
    }

    /**
     * The message shown when the type is refused.
     *
     * @return string
     */
    public static function type_message() {
        return esc_html__('This file type is not accepted here. Please attach one of the file types listed on the field.', 'easy-form-builder');
    }

    /**
     * Run the size and type layers against one $_FILES entry.
     *
     * Quota is deliberately not checked here: it is checked before the file is
     * examined and consumed after it is stored, which keeps a rejected file
     * from spending the visitor's allowance.
     *
     * @param array $file    One $_FILES entry.
     * @param array $context 'max_mb', 'field_extensions', 'form_id', 'field_id', 'source'.
     * @return true|string true when acceptable, otherwise a ready-to-display message.
     */
    public static function validate_file($file, $context = array()) {
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $tmp  = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
		$error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;

		$max_bytes = self::max_bytes(
			isset($context['max_mb']) ? $context['max_mb'] : null,
			$context
		);

		/* PHP may reject an oversized multipart body before it creates a temp
		 * file. Translate that transport-level failure into the same useful,
		 * field-specific size message as a file we were able to inspect. */
		if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
			return self::size_message($max_bytes);
		}
		if ($error !== UPLOAD_ERR_OK) {
			return esc_html__('The file could not be read. Please try attaching it again.', 'easy-form-builder');
		}

        if ($tmp === '' || !is_uploaded_file($tmp) || !is_readable($tmp)) {
            return esc_html__('The file could not be read. Please try attaching it again.', 'easy-form-builder');
        }

        /* Trust the bytes on disk over the reported size, which is client data. */
        $actual = @filesize($tmp);
        if (is_int($actual) && $actual > 0) {
            $size = $actual;
        }

        if ($size < 1) {
            return esc_html__('This file appears to be empty. Please choose a different file.', 'easy-form-builder');
        }

        if ($size > $max_bytes) {
            return self::size_message($max_bytes);
        }

        $extension = self::extension_of($name);
        $real_mime = self::sniff_mime($tmp);
        $field_ext = isset($context['field_extensions']) ? (array) $context['field_extensions'] : array();

        if (!self::is_allowed_type($extension, $real_mime, $field_ext)) {
            return self::type_message();
        }

        return true;
    }

    /**
     * Re-validate a file that was already written by a previous upload
     * request. Final form submission is a separate request, so it must not
     * trust that the URL supplied by the browser was uploaded through the
     * intended form field.
     *
     * This deliberately mirrors validate_file(), apart from the
     * is_uploaded_file() assertion: a completed upload is no longer a PHP
     * temporary upload by the time the form is submitted.
     *
     * @param string $path     Absolute path to a stored upload.
     * @param string $filename Name whose extension is being validated.
     * @param array  $context  See validate_file().
     * @return true|string true when acceptable, otherwise a visitor-safe message.
     */
    public static function validate_stored_file($path, $filename = '', $context = array()) {
        $path = (string) $path;
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return esc_html__('The uploaded file could not be verified. Please attach it again.', 'easy-form-builder');
        }

        $filename = $filename !== '' ? (string) $filename : basename($path);
        $size     = @filesize($path);
        if (!is_int($size) || $size < 1) {
            return esc_html__('This file appears to be empty. Please choose a different file.', 'easy-form-builder');
        }

        $max_bytes = self::max_bytes(
            isset($context['max_mb']) ? $context['max_mb'] : null,
            $context
        );

        if ($size > $max_bytes) {
            return self::size_message($max_bytes);
        }

        $extension = self::extension_of($filename);
        $real_mime = self::sniff_mime($path);
        $field_ext = isset($context['field_extensions']) ? (array) $context['field_extensions'] : array();

        if (!self::is_allowed_type($extension, $real_mime, $field_ext)) {
            return self::type_message();
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     * Response-box attachment reservations
     * ------------------------------------------------------------------ */

    /**
     * Keep a response attachment tied to the ticket it was uploaded for.
     *
     * Uploading and sending a reply are separate HTTP requests.  A pending
     * ledger alone only tells us that the file exists; it cannot distinguish
     * an attachment uploaded for ticket A from one later pasted into ticket B.
     * This small, expiring reservation provides that missing binding.
     *
     * @param string $path       Stored path (or a generated file name).
     * @param int    $message_id Support message id.
     * @param string $track      Ticket tracking code.
     * @return bool
     */
    public static function reserve_response_attachment($path, $message_id, $track) {
        $basename   = wp_basename((string) $path);
        $message_id = absint($message_id);
        $track      = (string) $track;

        if ($basename === '' || $message_id < 1 || $track === '') {
            return false;
        }

        $record = array(
            'message_id' => $message_id,
            /* Never store a tracking code in a transient in clear text. */
            'track_hash' => hash('sha256', $track),
        );

        return (bool) set_transient(
            self::response_attachment_key($basename),
            $record,
            self::RESPONSE_ATTACHMENT_TTL
        );
    }

    /**
     * Check whether an attachment was uploaded for this exact ticket.
     *
     * @param string $name_or_url File name or URL.
     * @param int    $message_id  Support message id.
     * @param string $track       Ticket tracking code.
     * @return bool
     */
    public static function response_attachment_is_reserved($name_or_url, $message_id, $track) {
        $basename   = wp_basename((string) $name_or_url);
        $message_id = absint($message_id);
        $track      = (string) $track;
        if ($basename === '' || $message_id < 1 || $track === '') {
            return false;
        }

        $record = get_transient(self::response_attachment_key($basename));
        if (!is_array($record) || !isset($record['message_id'], $record['track_hash'])) {
            return false;
        }

        return (int) $record['message_id'] === $message_id
            && hash_equals((string) $record['track_hash'], hash('sha256', $track));
    }

    /**
     * Read attachment URLs from a decoded reply payload.
     *
     * Response Box files are the only `allformat` items in a reply.  Returning
     * false for a malformed item keeps both REST and dashboard save handlers
     * from accidentally accepting a hand-crafted attachment object.
     *
     * @param array|object $message Decoded reply payload.
     * @return string[]|false URLs, or false when an attachment is malformed.
     */
    public static function response_attachment_urls($message) {
        if (!is_array($message)) {
            return false;
        }

        $urls = array();
        foreach ($message as $item) {
            $type = is_object($item) && isset($item->type)
                ? (string) $item->type
                : (is_array($item) && isset($item['type']) ? (string) $item['type'] : '');
            if ($type !== 'allformat') {
                continue;
            }

            $url = is_object($item) && isset($item->url)
                ? $item->url
                : (is_array($item) && isset($item['url']) ? $item['url'] : '');
            if (!is_string($url) || $url === '') {
                return false;
            }
            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    /**
     * Remove reservations after their reply was successfully saved.
     *
     * @param string|string[] $names File names or URLs.
     * @return void
     */
    public static function release_response_attachment_reservations($names) {
        foreach ((array) $names as $name) {
            $basename = wp_basename((string) $name);
            if ($basename !== '') {
                delete_transient(self::response_attachment_key($basename));
            }
        }
    }

    /**
     * @param string $basename Plugin-generated filename.
     * @return string Transient key, independent of the original filename.
     */
    private static function response_attachment_key($basename) {
        return self::RESPONSE_ATTACHMENT_PREFIX . hash('sha256', wp_basename((string) $basename));
    }

    /* ---------------------------------------------------------------------
     * Abandoned-upload sweeper
     * ------------------------------------------------------------------ */

    /**
     * Note a file that has been written but not yet attached to a submission.
     *
     * An upload and the form submission that claims it are two separate
     * requests, and nothing guarantees the second one ever arrives. Without
     * this ledger those files stay on disk forever, which is what turns a
     * repeated upload into disk growth.
     *
     * @param string $path Absolute path to the stored file.
     * @return void
     */
    public static function track_pending_upload($path) {
        $path = (string) $path;
        if ($path === '' || !file_exists($path)) {
            return;
        }

        $pending = get_option(self::PENDING_OPTION, array());
        if (!is_array($pending)) {
            $pending = array();
        }

        $pending[wp_basename($path)] = time();

        /* Keep only the newest rows. An overflowing ledger means uploads are
         * arriving faster than they are being claimed, and the oldest entries
         * are the ones the next sweep would have removed anyway. */
        if (count($pending) > self::PENDING_MAX_ROWS) {
            asort($pending);
            $pending = array_slice($pending, -self::PENDING_MAX_ROWS, null, true);
        }

        update_option(self::PENDING_OPTION, $pending, false);
    }

    /**
     * Drop ledger rows for files a submission has now claimed.
     *
     * @param string|string[] $names File names (or URLs) that were submitted.
     * @return void
     */
    public static function release_pending_uploads($names) {
        $pending = get_option(self::PENDING_OPTION, array());
        if (!is_array($pending) || empty($pending)) {
            return;
        }

        $changed = false;
        foreach ((array) $names as $name) {
            $base = wp_basename((string) $name);
            if ($base !== '' && isset($pending[$base])) {
                unset($pending[$base]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::PENDING_OPTION, $pending, false);
        }
    }

    /**
     * Remove uploads that were never attached to anything.
     *
     * Deletion is deliberately conservative. A file is removed only when it is
     * older than the grace period, still sits in the uploads directory, carries
     * one of the plugin's own generated name prefixes, and cannot be found in
     * any stored submission or reply. Anything that fails one of those tests is
     * left alone and simply dropped from the ledger.
     *
     * @return int Number of files deleted.
     */
    public static function cleanup_orphan_uploads() {
        global $wpdb;

        $pending = get_option(self::PENDING_OPTION, array());
        if (!is_array($pending) || empty($pending)) {
            return 0;
        }

        /**
         * Grace period, in seconds, before an unclaimed upload may be removed.
         *
         * @param int $ttl Default 86400 (24 hours).
         */
        $ttl = (int) apply_filters('emsfb_upload_orphan_ttl', self::DEFAULT_ORPHAN_TTL);
        $ttl = max(3600, $ttl);

        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return 0;
        }

        $basedir = wp_normalize_path(trailingslashit($uploads['basedir']));
        $now     = time();
        $deleted = 0;
        $changed = false;

        $msg_table = $wpdb->prefix . 'emsfb_msg_';
        $rsp_table = $wpdb->prefix . 'emsfb_rsp_';

        foreach ($pending as $basename => $stored_at) {
            if (($now - (int) $stored_at) < $ttl) {
                continue;
            }

            unset($pending[$basename]);
            $changed = true;

            $basename = wp_basename((string) $basename);

            /* Only ever touch names this plugin generated itself. */
            if (strpos($basename, 'efb-PLG-') !== 0 && strpos($basename, 'efb-rec-') !== 0) {
                continue;
            }

            $path = $basedir . $basename;
            $real = realpath($path);
            if ($real === false || strpos(wp_normalize_path($real), $basedir) !== 0 || !is_file($real)) {
                continue;
            }

            /* Final safety net: never delete something a submission points at,
             * even if the ledger row was somehow not released. */
            $like = '%' . $wpdb->esc_like($basename) . '%';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $in_msg = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM `{$msg_table}` WHERE content LIKE %s", $like));
            if ($in_msg > 0) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $in_rsp = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM `{$rsp_table}` WHERE content LIKE %s", $like));
            if ($in_rsp > 0) {
                continue;
            }

            if (function_exists('wp_delete_file')) {
                wp_delete_file($real);
            }

            if (!file_exists($real)) {
                $deleted++;
            }
        }

        if ($changed) {
            update_option(self::PENDING_OPTION, $pending, false);
        }

        return $deleted;
    }

    /**
     * Bind the sweeper. Called once from Emsfb::includes().
     *
     * @return void
     */
    public static function register() {
        add_action(self::CLEANUP_HOOK, array(__CLASS__, 'cleanup_orphan_uploads'));

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * Unbind the sweeper on deactivation.
     *
     * @return void
     */
    public static function unregister() {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }
}
