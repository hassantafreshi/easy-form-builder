<?php

namespace Emsfb;

defined('ABSPATH') || exit;

/**
 * Step-by-step trace of the notification-email path, from "form submitted" to
 * "SMTP server accepted it".
 *
 * Why this exists: when a site reports "the form saves but no email arrives",
 * the cause is one of a dozen unrelated things - the send switch is off, the
 * recipient list resolved empty, a guard vetoed the send, wp_mail() returned
 * false, an SMTP plugin swallowed it, or php.ini has mail() disabled. Each of
 * those looks identical from the outside. This records which one it was.
 *
 * Enable on the affected site with either:
 *   define('EMSFB_EMAIL_DEBUG', true);            // wp-config.php
 *   add_filter('emsfb_email_trace_enabled', '__return_true');
 *
 * The log holds recipient addresses and message subjects, so it is an explicit
 * opt-in, stored outside the web root's reach (.htaccess + index.html + a
 * token in the filename) and never on by default.
 */
class Email_Trace {

    const OPTION_TOKEN = 'emsfb_email_trace_token';
    const DIR_NAME     = 'emsfb-email-trace';
    const MAX_BYTES    = 5242880; // 5 MB, then rotated to .1

    /** @var string|null Correlates every line written during one HTTP request. */
    private static $request_id = null;

    /** @var bool Environment snapshot is written once per request, not per email. */
    private static $env_logged = false;

    /** @var bool */
    private static $hooks_bound = false;

    /**
     * Bind the WordPress mail hooks. Called from Emsfb::includes() on every
     * request; does nothing at all unless tracing is switched on.
     */
    public static function register() {
        if (self::$hooks_bound || !self::enabled()) {
            return;
        }
        self::$hooks_bound = true;

        // Final arguments as WordPress sees them, after every other filter.
        add_filter('wp_mail', [__CLASS__, 'capture_wp_mail_args'], PHP_INT_MAX);
        // The transport actually in use - this is where an SMTP plugin shows up.
        add_action('phpmailer_init', [__CLASS__, 'capture_phpmailer'], PHP_INT_MAX);
        // The reason a send failed, straight from PHPMailer.
        add_action('wp_mail_failed', [__CLASS__, 'capture_mail_failed'], PHP_INT_MAX);
    }

    /**
     * Whether the trace records anything at all. EFB_DEBUG (the project-wide
     * switch in emsfb.php, on by default) is enough for this level: it names
     * the gate that stopped an email without writing the message itself.
     */
    public static function enabled() {
        $enabled = (defined('EMSFB_EMAIL_DEBUG') && EMSFB_EMAIL_DEBUG)
            || (defined('EFB_DEBUG') && EFB_DEBUG);
        if (function_exists('apply_filters')) {
            $enabled = (bool) apply_filters('emsfb_email_trace_enabled', $enabled);
        }
        return $enabled;
    }

    /**
     * Whether the message itself may be recorded - recipient addresses,
     * subjects, body previews. Only EMSFB_EMAIL_DEBUG unlocks that, because
     * EFB_DEBUG is on for every install by default and every line here is
     * mirrored into debug.log, which many hosts serve over HTTP.
     *
     * Below this level addresses are masked rather than dropped, so an empty
     * recipient list still reads differently from a populated one - which is
     * the single most common answer to "the form saved but no mail arrived".
     */
    public static function full_detail() {
        $full = defined('EMSFB_EMAIL_DEBUG') && EMSFB_EMAIL_DEBUG;
        if (function_exists('apply_filters')) {
            $full = (bool) apply_filters('emsfb_email_trace_full_detail', $full);
        }
        return $full;
    }

    /**
     * Write one trace entry.
     *
     * @param string $stage Dot-separated step id, e.g. "submit.gate".
     * @param array  $data  Context; nested arrays/objects are flattened safely.
     */
    public static function log($stage, array $data = []) {
        if (!self::enabled()) {
            return;
        }

        self::log_environment_once();
        self::write($stage, $data);
    }

    /**
     * Same as log(), but always writes even when the environment snapshot has
     * not been produced yet - used by the snapshot itself to avoid recursion.
     */
    private static function write($stage, array $data) {
        $line = [
            'time'  => function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            'req'   => self::request_id(),
            'stage' => (string) $stage,
            'data'  => self::sanitize(self::full_detail() ? $data : self::redact_pii($data)),
        ];

        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($line);

        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        self::append($encoded . "\n");

        // Mirror a compact summary into debug.log, so a host that only exposes
        // WordPress's own log still shows the trace.
        self::error_log_safe('[EFB Email Trace] ' . self::request_id() . ' ' . $stage . ' ' . $encoded);
    }

    /**
     * One-time-per-request dump of everything that decides whether mail can
     * leave this server at all. This is usually where the answer is.
     */
    private static function log_environment_once() {
        if (self::$env_logged) {
            return;
        }
        self::$env_logged = true;

        self::write('env', [
            'log_file'        => self::path(),
            'plugin_version'  => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
            'php_version'     => PHP_VERSION,
            'wp_version'      => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
            'site_url'        => function_exists('home_url') ? home_url() : '',
            'wp_mail_owner'   => self::wp_mail_owner(),
            'mail_plugins'    => self::mail_related_plugins(),
            'php_mail'        => self::php_mail_state(),
            'settings'        => self::settings_state(),
            'email_status'    => function_exists('get_option') ? get_option('emsfb_email_status', false) : null,
        ]);
    }

    /**
     * Which file declared wp_mail(). wp-includes/pluggable.php means core is
     * sending; anything else means a plugin replaced the whole function, and
     * that plugin's configuration - not this one's - decides delivery.
     */
    private static function wp_mail_owner() {
        if (!function_exists('wp_mail')) {
            return 'wp_mail() is not defined';
        }
        try {
            $ref  = new \ReflectionFunction('wp_mail');
            $file = (string) $ref->getFileName();
        } catch (\Throwable $e) {
            return 'unknown (' . $e->getMessage() . ')';
        }

        $normalized = str_replace('\\', '/', $file);
        $is_core = strpos($normalized, '/wp-includes/pluggable.php') !== false;

        return [
            'file'       => $file,
            'overridden' => !$is_core,
            'note'       => $is_core
                ? 'WordPress core is sending (PHPMailer).'
                : 'A plugin/mu-plugin replaced wp_mail(); its own settings control delivery.',
        ];
    }

    private static function mail_related_plugins() {
        if (!function_exists('get_option')) {
            return [];
        }
        $active = (array) get_option('active_plugins', []);
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        $needles = ['smtp', 'mail', 'postman', 'sendgrid', 'mailgun', 'sendinblue', 'brevo', 'amazonses', 'sparkpost', 'elastic'];
        $found = [];
        foreach ($active as $plugin) {
            $slug = strtolower((string) $plugin);
            foreach ($needles as $needle) {
                if (strpos($slug, $needle) !== false) {
                    $found[] = $plugin;
                    break;
                }
            }
        }

        $mu = [];
        if (function_exists('get_mu_plugins')) {
            $mu = array_keys((array) get_mu_plugins());
        }

        return ['active_mail_plugins' => array_values(array_unique($found)), 'mu_plugins' => $mu];
    }

    private static function php_mail_state() {
        $ini = function_exists('emsfb_get_php_ini_value_efb')
            ? 'emsfb_get_php_ini_value_efb'
            : null;

        $disabled = '';
        if ($ini) {
            $disabled = (string) call_user_func($ini, 'disable_functions');
        } elseif (function_exists('ini_get')) {
            $disabled = (string) @ini_get('disable_functions');
        }

        return [
            'mail_available'  => function_exists('emsfb_is_php_function_available_efb')
                ? emsfb_is_php_function_available_efb('mail')
                : function_exists('mail'),
            'ini_SMTP'        => $ini ? call_user_func($ini, 'SMTP') : null,
            'ini_smtp_port'   => $ini ? call_user_func($ini, 'smtp_port') : null,
            'ini_sendmail'    => $ini ? call_user_func($ini, 'sendmail_path') : null,
            'ini_mail_from'   => $ini ? call_user_func($ini, 'sendmail_from') : null,
            'disable_functions' => $disabled,
        ];
    }

    private static function settings_state() {
        if (!function_exists('get_setting_Emsfb')) {
            return null;
        }
        $settings = get_setting_Emsfb('decoded');
        if (!is_object($settings)) {
            return ['readable' => false];
        }

        $raw = isset($settings->smtp) ? $settings->smtp : '(key missing)';

        return [
            'readable'          => true,
            'smtp_raw'          => $raw,
            'smtp_raw_type'     => gettype($raw),
            'sending_enabled'   => function_exists('emsfb_is_email_sending_enabled_efb')
                ? emsfb_is_email_sending_enabled_efb($settings)
                : null,
            'emailSupporter'    => isset($settings->emailSupporter) ? $settings->emailSupporter : null,
            'femail'            => isset($settings->femail) ? $settings->femail : null,
            'email_key_set'     => !empty($settings->email_key),
        ];
    }

    /* ── WordPress mail hooks ─────────────────────────────────────────────── */

    public static function capture_wp_mail_args($args) {
        $headers = isset($args['headers']) ? $args['headers'] : [];
        self::log('wp_mail.args', [
            'to'          => isset($args['to']) ? $args['to'] : null,
            'subject'     => isset($args['subject']) ? $args['subject'] : null,
            'headers'     => is_array($headers) ? $headers : (string) $headers,
            'attachments' => isset($args['attachments']) ? $args['attachments'] : [],
            'body_bytes'  => isset($args['message']) ? strlen((string) $args['message']) : 0,
        ]);
        return $args;
    }

    /**
     * The transport in use. If Mailer is "smtp" the Host/Port/auth values come
     * from whichever SMTP plugin is installed; if it is "mail" the site is
     * relying on PHP mail(), which most shared hosts silently drop.
     */
    public static function capture_phpmailer($phpmailer) {
        $get = function ($prop) use ($phpmailer) {
            return isset($phpmailer->$prop) ? $phpmailer->$prop : null;
        };

        self::log('phpmailer.init', [
            'Mailer'      => $get('Mailer'),
            'Host'        => $get('Host'),
            'Port'        => $get('Port'),
            'SMTPSecure'  => $get('SMTPSecure'),
            'SMTPAuth'    => $get('SMTPAuth'),
            'Username_set' => !empty($get('Username')),
            'From'        => $get('From'),
            'FromName'    => $get('FromName'),
            'Sender'      => $get('Sender'),
            'ContentType' => $get('ContentType'),
            'CharSet'     => $get('CharSet'),
        ]);
    }

    public static function capture_mail_failed($error) {
        $payload = ['raw' => is_object($error) ? get_class($error) : gettype($error)];
        if (is_object($error) && method_exists($error, 'get_error_code')) {
            $payload = [
                'code'    => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data'    => method_exists($error, 'get_error_data') ? $error->get_error_data() : null,
            ];
        }
        self::log('wp_mail.failed', $payload);
    }

    /* ── storage ──────────────────────────────────────────────────────────── */

    /**
     * Stable per-site path. The token keeps the filename unguessable even if a
     * host serves the uploads directory without honouring .htaccess.
     */
    public static function path() {
        static $path = null;
        if ($path !== null) {
            return $path;
        }

        $token = function_exists('get_option') ? get_option(self::OPTION_TOKEN, '') : '';
        if (!is_string($token) || strlen($token) !== 12) {
            $token = substr(md5(uniqid('efb', true)), 0, 12);
            if (function_exists('update_option')) {
                update_option(self::OPTION_TOKEN, $token, false);
            }
        }

        $dir = self::directory();
        $path = $dir === '' ? '' : $dir . '/email-trace-' . $token . '.log';
        return $path;
    }

    private static function directory() {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        $dir = '';

        if (!function_exists('wp_upload_dir')) {
            return $dir;
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return $dir;
        }

        $target = rtrim($uploads['basedir'], '/\\') . '/' . self::DIR_NAME;
        if (!is_dir($target)) {
            if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($target)) {
                return $dir;
            }
        }

        // Deny direct HTTP access. Best-effort: hosts that ignore .htaccess are
        // covered by the token in the filename.
        self::protect($target);

        $dir = $target;
        return $dir;
    }

    private static function protect($target) {
        if (!function_exists('emsfb_is_php_function_available_efb') || !emsfb_is_php_function_available_efb('file_put_contents')) {
            return;
        }
        $htaccess = $target . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        $index = $target . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
    }

    private static function append($text) {
        $path = self::path();
        if ($path === '') {
            return;
        }
        if (!function_exists('emsfb_is_php_function_available_efb') || !emsfb_is_php_function_available_efb('file_put_contents')) {
            return;
        }

        // Rotate rather than grow without bound; a busy form can produce a lot.
        if (file_exists($path) && filesize($path) > self::MAX_BYTES) {
            @rename($path, $path . '.1');
        }

        @file_put_contents($path, $text, FILE_APPEND | LOCK_EX);
    }

    private static function error_log_safe($message) {
        $available = function_exists('emsfb_is_php_function_available_efb')
            ? emsfb_is_php_function_available_efb('error_log')
            : function_exists('error_log');
        if ($available) {
            error_log($message);
        }
    }

    private static function request_id() {
        if (self::$request_id === null) {
            self::$request_id = substr(md5(uniqid('', true)), 0, 8);
        }
        return self::$request_id;
    }

    /**
     * Strip person-identifying content for the EFB_DEBUG-only level: message
     * text is replaced outright, addresses are masked to "j***@example.com".
     * Masking rather than dropping is deliberate - the count and shape of the
     * recipient list is exactly what the trace is being read for.
     */
    private static function redact_pii($value, $depth = 0) {
        if ($depth > 6) {
            return $value;
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (preg_match('/^(subject|message|body|content|html|msg_content)$/i', (string) $key)) {
                    $out[$key] = is_scalar($item)
                        ? '[hidden - define EMSFB_EMAIL_DEBUG to record]'
                        : self::redact_pii($item, $depth + 1);
                    continue;
                }
                $out[$key] = self::redact_pii($item, $depth + 1);
            }
            return $out;
        }
        if (is_string($value)) {
            return preg_replace_callback(
                '/([^\s@,;<>"\']+)@([^\s@,;<>"\']+)/',
                function ($m) {
                    return substr($m[1], 0, 1) . '***@' . $m[2];
                },
                $value
            );
        }
        return $value;
    }

    /**
     * Make any context JSON-safe: WP_Error becomes code/message, objects become
     * arrays, long strings are clipped, and anything that looks like a secret is
     * redacted so the file can be shared with support.
     */
    private static function sanitize($value, $depth = 0) {
        if ($depth > 6) {
            return '[max depth]';
        }

        if ($value instanceof \WP_Error) {
            return ['wp_error' => $value->get_error_code(), 'message' => $value->get_error_message()];
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $k = (string) $key;
                if (preg_match('/(password|secret|token|api_?key|nonce|activeCode|SKey|payToken)/i', $k)) {
                    $out[$k] = '[redacted]';
                    continue;
                }
                $out[$k] = self::sanitize($item, $depth + 1);
            }
            return $out;
        }
        if (is_string($value) && strlen($value) > 600) {
            return substr($value, 0, 600) . '...[' . strlen($value) . ' bytes]';
        }
        return $value;
    }

    /* ── read back ────────────────────────────────────────────────────────── */

    /**
     * Last $lines entries, newest last. Used by the admin diagnostics view and
     * handy from WP-CLI: wp eval 'echo Emsfb\Email_Trace::tail(50);'
     */
    public static function tail($lines = 200) {
        $path = self::path();
        if ($path === '' || !file_exists($path)) {
            return '';
        }
        if (!function_exists('emsfb_is_php_function_available_efb') || !emsfb_is_php_function_available_efb('file_get_contents')) {
            return '';
        }
        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return '';
        }
        $all = explode("\n", trim($contents));
        return implode("\n", array_slice($all, -max(1, (int) $lines)));
    }

    public static function clear() {
        $path = self::path();
        if ($path !== '' && file_exists($path) && function_exists('wp_delete_file')) {
            wp_delete_file($path);
        }
    }
}
