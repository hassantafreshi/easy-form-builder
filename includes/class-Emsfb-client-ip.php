<?php
namespace Emsfb;

defined('ABSPATH') || exit;

/**
 * The visitor's IP address, resolved without trusting request headers.
 *
 * Client-IP and X-Forwarded-For are ordinary request headers: any visitor can
 * send them with any value. Reading them first let a visitor choose the address
 * stored with a submission, the key of every per-IP rate limit and the address
 * handed to Shield Security's bot lookup.
 *
 * REMOTE_ADDR, the address of the TCP peer, is used by default. A forwarded
 * header is read only when REMOTE_ADDR itself is one of the proxies the site
 * owner declared as trusted:
 *
 *   define('EMSFB_TRUSTED_PROXIES', '10.0.0.0/8, 172.16.0.5');      // wp-config.php
 *   add_filter('efb_trusted_proxies', fn($list) => [...$list, '192.168.1.0/24']);
 *
 * The forwarded chain is then walked from the right, skipping trusted hops, and
 * the first address that is not a trusted proxy is the visitor. Every value is
 * checked with FILTER_VALIDATE_IP; a malformed hop ends the walk.
 */
class Client_IP {

    /** Returned when no address can be determined (CLI, cron), as before. */
    const FALLBACK = '1.1.1.1';

    /**
     * @param array|null $server $_SERVER, or a replacement for tests.
     * @return string A valid IPv4 or IPv6 address.
     */
    public static function resolve($server = null) {
        if (!is_array($server)) {
            $server = isset($_SERVER) && is_array($_SERVER) ? $_SERVER : array();
        }

        $remote = self::normalize(isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '');
        if ($remote === '') {
            return self::FALLBACK;
        }

        $proxies = self::trusted_proxies();
        if (empty($proxies) || !self::in_list($remote, $proxies)) {
            return $remote;
        }

        foreach (self::forwarded_headers() as $header) {
            if (empty($server[$header]) || !is_string($server[$header])) {
                continue;
            }
            return self::client_from_chain($server[$header], $remote, $proxies);
        }

        return $remote;
    }

    /**
     * Trusted proxy addresses and CIDR ranges.
     *
     * @return string[]
     */
    public static function trusted_proxies() {
        $list = array();
        if (defined('EMSFB_TRUSTED_PROXIES')) {
            $configured = constant('EMSFB_TRUSTED_PROXIES');
            $list = is_array($configured) ? $configured : explode(',', (string) $configured);
        }
        if (function_exists('apply_filters')) {
            /**
             * IP addresses or CIDR ranges of reverse proxies / load balancers in
             * front of this site. Only a request whose REMOTE_ADDR is in this
             * list has its forwarded headers read. Empty by default.
             *
             * @param string[] $list
             */
            $list = apply_filters('efb_trusted_proxies', $list);
        }
        $clean = array();
        foreach ((array) $list as $entry) {
            if (!is_string($entry)) continue;
            $entry = trim($entry);
            if ($entry !== '' && self::parse_range($entry) !== null) {
                $clean[] = $entry;
            }
        }
        return $clean;
    }

    /**
     * $_SERVER keys read, in order, when the request came through a trusted proxy.
     *
     * @return string[]
     */
    public static function forwarded_headers() {
        $headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');
        if (function_exists('apply_filters')) {
            /**
             * For example add 'HTTP_CF_CONNECTING_IP' behind Cloudflare, with
             * Cloudflare's ranges in efb_trusted_proxies.
             *
             * @param string[] $headers
             */
            $headers = apply_filters('efb_trusted_proxy_headers', $headers);
        }
        $clean = array();
        foreach ((array) $headers as $h) {
            if (is_string($h) && preg_match('/^[A-Z0-9_]{1,64}$/D', $h)) {
                $clean[] = $h;
            }
        }
        return $clean;
    }

    /**
     * Walk a comma separated forwarded chain from the right.
     *
     * @return string
     */
    private static function client_from_chain($chain, $remote, $proxies) {
        $hops = array_slice(explode(',', $chain), -20);
        $candidate = $remote;
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            $hop = self::normalize($hops[$i]);
            if ($hop === '') {
                break;
            }
            if (!self::in_list($hop, $proxies)) {
                return $hop;
            }
            $candidate = $hop;
        }
        return $candidate;
    }

    /**
     * Strip a port and brackets, then validate. '' when not an IP address.
     *
     * @param mixed $value
     * @return string
     */
    public static function normalize($value) {
        if (!is_string($value)) {
            return '';
        }
        $ip = trim($value);
        if ($ip === '' || strlen($ip) > 64) {
            return '';
        }
        /* Some proxies (Azure App Service, IIS ARR) forward "IP:port" or
         * "[IPv6]:port"; keep the address part. */
        if (preg_match('/^\[([0-9a-fA-F:.]+)\](?::\d+)?$/D', $ip, $m)) {
            $ip = $m[1];
        } elseif (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/D', $ip, $m)) {
            $ip = $m[1];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
    }

    /**
     * @param string   $ip
     * @param string[] $list Addresses or CIDR ranges.
     * @return bool
     */
    public static function in_list($ip, $list) {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        foreach ((array) $list as $entry) {
            $range = self::parse_range((string) $entry);
            if ($range === null || strlen($range[0]) !== strlen($packed)) {
                continue;
            }
            list($net, $bits) = $range;
            $bytes = intdiv($bits, 8);
            $rest  = $bits % 8;
            if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($net, 0, $bytes)) {
                continue;
            }
            if ($rest > 0) {
                $mask = chr((0xFF << (8 - $rest)) & 0xFF);
                if ((ord($packed[$bytes]) & ord($mask)) !== (ord($net[$bytes]) & ord($mask))) {
                    continue;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @return array|null [packed network, prefix bits]
     */
    private static function parse_range($entry) {
        $entry = trim($entry);
        $bits = null;
        if (strpos($entry, '/') !== false) {
            list($entry, $bits) = explode('/', $entry, 2);
            if (!preg_match('/^[0-9]{1,3}$/D', $bits)) {
                return null;
            }
            $bits = (int) $bits;
        }
        if (filter_var($entry, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $packed = @inet_pton($entry);
        if ($packed === false) {
            return null;
        }
        $max = strlen($packed) * 8;
        if ($bits === null) {
            $bits = $max;
        }
        if ($bits < 0 || $bits > $max) {
            return null;
        }
        return array($packed, $bits);
    }
}
