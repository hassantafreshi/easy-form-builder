<?php
/**
 * Stripe one-time payment verification: guards around the confirmation endpoint
 * and the background reconciliation. Real WordPress and database, no network,
 * no payment. Each scenario runs in its own PHP process, because a class stub
 * cannot be unloaded:
 *
 *   stub      \Stripe\StripeClient is a local stub; neither the Stripe SDK nor the
 *             add-on's StripePayment class is loaded. The reconciliation
 *             completes a paid pending row, keeps an unpaid one pending, marks a
 *             canceled one cancelled and stops at a gateway outage; the
 *             confirmation endpoint works without the add-on class.
 *   no-table  emsfb_pay_ does not exist (the add-on's admin screen, which creates
 *             it, was never opened): no fatal error, no database error, the
 *             reconciliation is a no-op and the confirmation falls back to the
 *             submission card/add stored. Skipped when the table exists
 *             (tests/test-stripe-reconcile.php drops and restores it instead).
 *   no-sdk    no stub and no Stripe SDK files (add-on not installed): no fatal
 *             error, the confirmation answers 503 and the row stays pending.
 *             Skipped when vendor/stripe holds the SDK.
 *
 *   cd wp-content/plugins/easy-form-builder && php tests/test-stripe-resilience-guards.php
 */
namespace Stripe {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    if (in_array('--scenario=stub', $argv, true) || in_array('--scenario=no-table', $argv, true)) {
        /** Same surface as the SDK call the handlers make: $client->paymentIntents->retrieve($id, $params). */
        class StripeClient {
            /** @var string succeeded|unpaid|canceled|outage */
            public static $mode = 'succeeded';
            public static $amounts = array();
            public static $calls = array();
            public $paymentIntents;
            public function __construct($key) { $this->paymentIntents = new EfbGuardIntentsStub(); }
        }
        class EfbGuardIntentsStub {
            public function retrieve($id, $params = array()) {
                StripeClient::$calls[] = $id;
                if (StripeClient::$mode === 'outage') throw new \RuntimeException('Test gateway outage');
                $amount = isset(StripeClient::$amounts[$id]) ? StripeClient::$amounts[$id] : 100;
                $status = StripeClient::$mode === 'canceled' ? 'canceled' : (StripeClient::$mode === 'unpaid' ? 'requires_payment_method' : 'succeeded');
                return (object) array('id' => $id, 'object' => 'payment_intent', 'status' => $status, 'amount' => $amount, 'amount_received' => $status === 'succeeded' ? $amount : 0, 'currency' => 'usd');
            }
        }
    }
}

namespace {
    $scenario = '';
    foreach ($argv as $arg) {
        if (strpos($arg, '--scenario=') === 0) $scenario = substr($arg, 11);
    }

    if ($scenario === '') {
        // Parent: run every scenario in a fresh process and require a clean exit.
        $total = 0; $failed = false;
        foreach (array('stub', 'no-table', 'no-sdk') as $name) {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg('--scenario=' . $name);
            $worker = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, __DIR__);
            if (!is_resource($worker)) { fwrite(STDERR, "FAIL: scenario $name could not start\n"); $failed = true; continue; }
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $code = proc_close($worker);
            echo $out;
            if ($code !== 0 || !preg_match('/^SCENARIO_OK ' . preg_quote($name, '/') . ' (\d+)$/m', $out, $m)) {
                fwrite(STDERR, "FAIL: scenario $name exited with $code\n" . $err);
                $failed = true;
                continue;
            }
            $total += (int) $m[1];
        }
        if ($failed) exit(1);
        echo "$total Stripe guard checks passed (stubbed client, missing table, missing SDK; real WordPress/database).\n";
        exit(0);
    }

    define('DOING_AJAX', true);
    ob_start(); // keep headers unsent so the handlers' HTTP status reaches the status_header filter
    require dirname(__DIR__, 4) . '/wp-load.php';

    $plugin_dir = dirname(__DIR__) . '/';
    // A fatal error must surface as a failed scenario, whatever WP_DEBUG_DISPLAY says.
    register_shutdown_function(static function () {
        $last = error_get_last();
        if ($last && in_array($last['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true)) {
            fwrite(STDOUT, 'FATAL: ' . $last['message'] . ' @' . $last['file'] . ':' . $last['line'] . "\n");
        }
    });
    // Any notice or warning raised by the handlers under test fails the scenario (PHP 8.x hygiene).
    $php_notices = array();
    set_error_handler(static function ($type, $message, $file, $line) use (&$php_notices) {
        if (strpos(str_replace('\\', '/', $file), '/includes/class-Emsfb-public.php') !== false) {
            $php_notices[] = $message . ' @' . $line;
        }
        return false;
    });

    class EfbGuardTestEnd extends Exception {}
    add_filter('wp_die_ajax_handler', static function () { return static function () { throw new EfbGuardTestEnd(); }; });

    global $wpdb, $EZSQL_ERROR;
    $table     = $wpdb->prefix . 'emsfb_pay_';
    $msg_table = $wpdb->prefix . 'emsfb_msg_';
    $ref       = new ReflectionClass('Emsfb\\_Public');
    $public    = $ref->newInstanceWithoutConstructor();
    $private   = static function ($name) use ($ref) { $m = $ref->getMethod($name); $m->setAccessible(true); return $m; };
    $passed    = 0;
    $check     = static function ($ok, $label) use (&$passed) { if (!$ok) throw new RuntimeException('FAIL: ' . $label); $passed++; fwrite(STDOUT, "PASS $label\n"); };
    $db_errors = static function () { return is_array($GLOBALS['EZSQL_ERROR'] ?? null) ? count($GLOBALS['EZSQL_ERROR']) : 0; };
    $confirm   = static function ($intent, $track) use ($public) {
        $request = new WP_REST_Request('POST', '/Emsfb/v1/forms/payment/stripe/confirm');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode(array('paymentIntentId' => $intent, 'trackid' => $track)));
        $code = 200;
        add_filter('status_header', $hook = static function ($header, $status) use (&$code) { $code = $status; return $header; }, 10, 2);
        ob_start();
        try { $public->pay_stripe_confirm_Emsfb_api($request); } catch (EfbGuardTestEnd $end) {}
        $body = json_decode(ob_get_clean(), true);
        remove_filter('status_header', $hook, 10);
        return array($code, $body);
    };
    $table_exists = static function () use ($private, $public) { return $private('stripe_pay_table_exists_efb')->invoke($public); };
    $row = static function ($id) use ($wpdb, $table) { return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)); };

    // The handlers need a Stripe secret key. Use the site's when one is set; otherwise a
    // placeholder for this process only (object cache, never saved). No request leaves the host.
    $settings_injected = false;
    if ($private('resolve_payment_key_efb')->invoke($public, get_setting_Emsfb('decoded'), 'stripeSKey') === 'null') {
        $decoded = get_setting_Emsfb('decoded');
        $decoded = is_object($decoded) ? clone $decoded : new stdClass();
        $decoded->stripeSKey = 'sk_test_efbguards0000000000';
        get_setting_Emsfb('_clear_cache');
        wp_cache_set('settings:decoded', $decoded, 'emsfb');
        $settings_injected = true;
    }

    $now = time();
    $at  = static function ($seconds_ago) use ($now) { return wp_date('Y-m-d H:i:s', $now - $seconds_ago); };
    $rows = array(); $msgs = array(); $created_table = false; $parked_ids = array();
    $add = static function ($created_ago, $fields = array()) use (&$rows, $at, $wpdb, $table) {
        static $n = 0; $n++;
        $track  = 'EFBGRD' . $n . substr(md5(uniqid('', true)), 0, 6);
        $intent = 'pi_EfbGuard' . $n . substr(md5(uniqid('', true)), 0, 10);
        $ok = $wpdb->insert($table, array_merge(array(
            'form_id' => 0, 'track' => $track, 'gateway' => 'stripe', 'payment_type' => 'one-time', 'transaction_id' => $intent,
            'amount' => 1, 'currency' => 'USD', 'status' => 'pending', 'created_at' => $at($created_ago), 'updated_at' => $at($created_ago),
        ), $fields));
        if (!$ok) throw new RuntimeException('fixture insert failed: ' . $wpdb->last_error);
        $rows[] = (int) $wpdb->insert_id;
        return array((int) $wpdb->insert_id, $intent, $track);
    };
    // A test copy of the add-on's schema (class-Emsfb-stripe-payment.php), used only when the
    // table does not exist yet and dropped again afterwards.
    $create_table = static function () use ($wpdb, $table) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) NOT NULL DEFAULT 0,
            `track` varchar(20) NOT NULL DEFAULT '',
            `gateway` varchar(30) NOT NULL DEFAULT '',
            `payment_type` varchar(30) NOT NULL DEFAULT 'one-time',
            `transaction_id` varchar(100) NOT NULL DEFAULT '',
            `subscription_id` varchar(100) NOT NULL DEFAULT '',
            `plan_id` varchar(100) NOT NULL DEFAULT '',
            `product_id` varchar(100) NOT NULL DEFAULT '',
            `capture_id` varchar(100) NOT NULL DEFAULT '',
            `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `currency` varchar(10) NOT NULL DEFAULT 'USD',
            `status` varchar(30) NOT NULL DEFAULT 'pending',
            `interval_unit` varchar(20) NOT NULL DEFAULT '',
            `payer_email` varchar(200) NOT NULL DEFAULT '',
            `payer_name` varchar(200) NOT NULL DEFAULT '',
            `uid` varchar(100) NOT NULL DEFAULT '',
            `ip` varchar(45) NOT NULL DEFAULT '',
            `form_name` varchar(250) NOT NULL DEFAULT '',
            `meta` MEDIUMTEXT,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_track (track),
            KEY idx_transaction_id (transaction_id)
        ) " . $wpdb->get_charset_collate() . ';');
    };
    // The submission card/add stores for a one-time charge (pending stub, read_=2).
    $add_stub = static function ($intent, $amount) use (&$msgs, $wpdb, $msg_table) {
        $track = 'G' . substr(md5(uniqid('', true)), 0, 11); // emsfb_msg_.track is varchar(12)
        $payment_row = array('id_' => 'payment', 'amount' => 0, 'name' => 'Payment', 'type' => 'payment', 'value' => $amount . ' usd', 'paymentIntent' => $intent, 'paymentGateway' => 'stripe', 'paymentmethod' => 'charge', 'paymentAmount' => $amount, 'paymentCreated' => '2026-10-18-01:00:00', 'paymentcurrency' => 'usd', 'gateway' => 'stripe', 'status' => 'active', 'total' => $amount);
        $content = str_replace('"', '\\"', wp_json_encode(array(array('id_' => 'g1probe', 'name' => 'Plan', 'type' => 'option_payment', 'price' => (string) $amount, 'value' => 'A'), $payment_row), JSON_UNESCAPED_UNICODE));
        $ok = $wpdb->insert($msg_table, array('form_title_x' => 'test', 'content' => $content, 'form_id' => 999998, 'track' => $track, 'ip' => '127.0.0.1', 'read_' => 2, 'date' => wp_date('Y-m-d H:i:s')));
        if (!$ok) throw new RuntimeException('stub insert failed: ' . $wpdb->last_error);
        $msgs[] = (int) $wpdb->insert_id;
        return $track;
    };
    $original_health = get_option(\Emsfb\Request_Health::OPTION, null);
    $errors_before = $db_errors();
    $original_event = wp_next_scheduled('emsfb_stripe_reconcile_efb'); // put back afterwards
    try {
        delete_transient('emsfb_stripe_reconcile_lock');
        wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');

        if ($scenario === 'stub') {
            $check(!class_exists('Emsfb\\StripePayment', false), "The add-on's StripePayment class is not loaded");
            $check(!class_exists('Stripe\\HttpClient\\CurlClient', false), 'The Stripe SDK is not loaded (stubbed StripeClient only)');
            if (!$table_exists()) { $create_table(); $created_table = true; }
            $errors_before = $db_errors(); // creating the fixture table may log DESCRIBE misses
            $check($table_exists(), 'Payment table detected');
            // Leave other pending rows of this site out of the run; restored in finally.
            $parked_ids = array_map('intval', $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'pending'"));
            if ($parked_ids) $wpdb->query("UPDATE {$table} SET status = 'test-parked' WHERE id IN (" . implode(',', $parked_ids) . ')');

            list($paid, $paid_pi) = $add(20 * MINUTE_IN_SECONDS);
            list($young) = $add(2 * MINUTE_IN_SECONDS);
            list($sub) = $add(30 * MINUTE_IN_SECONDS, array('payment_type' => 'subscription', 'status' => 'active'));
            $public->reconcile_stripe_payments_efb();
            $check($row($paid)->status === 'completed' && $row($paid)->capture_id === $paid_pi, 'Reconciliation completes a paid pending row via the stubbed StripeClient');
            $check(\Stripe\StripeClient::$calls === array($paid_pi), 'Only the due one-time row was looked up');
            $check($row($young)->status === 'pending' && $row($sub)->status === 'active', 'Rows younger than 10 min and subscriptions are left alone');
            $next = wp_next_scheduled('emsfb_stripe_reconcile_efb');
            $check($next && $next > $now + 50 * MINUTE_IN_SECONDS && $next < $now + 70 * MINUTE_IN_SECONDS, 'Next run queued about an hour later');

            wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');
            \Stripe\StripeClient::$calls = array();
            list($unpaid) = $add(25 * MINUTE_IN_SECONDS);
            \Stripe\StripeClient::$mode = 'unpaid';
            $public->reconcile_stripe_payments_efb();
            $check($row($unpaid)->status === 'pending' && $row($unpaid)->updated_at > $row($unpaid)->created_at, 'Unpaid intent stays pending and is marked as checked');
            $wpdb->update($table, array('status' => 'test-done'), array('id' => $unpaid));

            list($canceled) = $add(26 * MINUTE_IN_SECONDS);
            \Stripe\StripeClient::$mode = 'canceled';
            $public->reconcile_stripe_payments_efb();
            $check($row($canceled)->status === 'cancelled', 'Intent canceled at Stripe is marked cancelled');

            \Stripe\StripeClient::$calls = array();
            list($down1) = $add(40 * MINUTE_IN_SECONDS);
            list($down2) = $add(41 * MINUTE_IN_SECONDS);
            \Stripe\StripeClient::$mode = 'outage';
            $public->reconcile_stripe_payments_efb();
            $check(count(\Stripe\StripeClient::$calls) === 1 && $row($down1)->status === 'pending' && $row($down2)->status === 'pending', 'Gateway outage: one request, rows stay pending, no exception');
            $check(get_transient('emsfb_stripe_reconcile_lock') === false, 'The run lock is released');

            // Confirmation endpoint without the add-on class.
            wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');
            list($c, $c_pi, $c_track) = $add(0);
            list($code, $body) = $confirm($c_pi, $c_track);
            $check($code === 503 && empty($body['data']['success']) && $row($c)->status === 'pending', 'Confirm during an outage: 503 (browser retries, then submits), row pending');
            $check((bool) wp_next_scheduled('emsfb_stripe_reconcile_efb'), 'Confirm during an outage queues the background check');
            \Stripe\StripeClient::$mode = 'unpaid';
            list($code) = $confirm($c_pi, $c_track);
            $check($code === 409 && $row($c)->status === 'pending', 'Unpaid intent: definitive 409');
            \Stripe\StripeClient::$mode = 'succeeded';
            list($code, $body) = $confirm($c_pi, $c_track);
            $check($code === 200 && !empty($body['data']['success']) && $row($c)->status === 'completed', 'Paid intent completes without the add-on StripePayment class');
            $check(!class_exists('Emsfb\\StripePayment', false), 'StripePayment was never required');
        } elseif ($scenario === 'no-table' && $table_exists()) {
            // Not dropped here: the SQLite drop-in cannot rename tables, and recreating the
            // add-on's table belongs to its own schema code. tests/test-stripe-reconcile.php
            // covers this case by dropping and restoring the table through the add-on class.
            fwrite(STDOUT, "SKIP no-table: emsfb_pay_ exists on this site (run on a site where the Stripe add-on screen was never opened)\n");
        } elseif ($scenario === 'no-table') {
            $check(!$table_exists(), 'Payment table absent');
            $public->reconcile_stripe_payments_efb();
            $check(\Stripe\StripeClient::$calls === array() && !wp_next_scheduled('emsfb_stripe_reconcile_efb'), 'Reconciliation is a silent no-op');
            $pi = 'pi_EfbGuardStub' . substr(md5(uniqid('', true)), 0, 10);
            \Stripe\StripeClient::$amounts[$pi] = 250;
            $track = $add_stub($pi, 2.5);
            list($code, $body) = $confirm($pi, $track);
            $check($code === 200 && !empty($body['data']['success']), 'Paid intent verified against the submission card/add stored');
            \Stripe\StripeClient::$mode = 'outage';
            list($code) = $confirm($pi, $track);
            $check($code === 503, 'Stripe unreachable: 503 (retry, then submit), not 409');
            \Stripe\StripeClient::$mode = 'succeeded';
            list($code) = $confirm('pi_EfbGuardForeign123', $track);
            $check($code === 409, 'An intent that is not in this submission is a mismatch');
            \Stripe\StripeClient::$amounts[$pi] = 300;
            list($code) = $confirm($pi, $track);
            $check($code === 409, 'An amount that differs from the submission is a mismatch');
            $check($db_errors() === $errors_before, 'No database error without the table');
        } elseif ($scenario === 'no-sdk') {
            if (is_readable($plugin_dir . 'vendor/stripe/stripe-php/init.php') || is_readable($plugin_dir . 'vendor/stripe/stripe-php/lib/StripeClient.php')) {
                fwrite(STDOUT, "SKIP no-sdk: the Stripe SDK is installed in vendor/stripe (run on a site without the Stripe add-on)\n");
            } else {
                $check(!class_exists('Stripe\\StripeClient', false), 'No Stripe SDK loaded');
                if (!$table_exists()) { $create_table(); $created_table = true; }
                $errors_before = $db_errors();
                $parked_ids = array_map('intval', $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'pending'"));
                if ($parked_ids) $wpdb->query("UPDATE {$table} SET status = 'test-parked' WHERE id IN (" . implode(',', $parked_ids) . ')');
                list($p, $p_pi, $p_track) = $add(30 * MINUTE_IN_SECONDS);
                $public->reconcile_stripe_payments_efb();
                $check($row($p)->status === 'pending', 'Reconciliation without the SDK: no fatal error, row stays pending');
                $check(get_transient('emsfb_stripe_reconcile_lock') === false, 'No lock left behind');
                list($code, $body) = $confirm($p_pi, $p_track);
                $check($code === 503 && empty($body['data']['success']) && $row($p)->status === 'pending', 'Confirm without the SDK: 503 (browser retries, then submits), row pending');
                $health = get_option(\Emsfb\Request_Health::OPTION, array());
                $check(isset($health['server:payment:503:gateway_verification_unavailable']), 'Missing SDK is reported as gateway_verification_unavailable');
                $check(!class_exists('Stripe\\StripeClient'), 'Still no StripeClient (autoloader found no SDK files)');
            }
        }
        $check($php_notices === array(), 'No PHP notice or warning from the handlers' . ($php_notices ? ': ' . implode('; ', $php_notices) : ''));
        $last_db_error = $db_errors() > $errors_before ? end($GLOBALS['EZSQL_ERROR']) : null;
        $check($last_db_error === null, 'No database error during the scenario' . ($last_db_error ? ': ' . $last_db_error['error_str'] . ' for ' . $last_db_error['query'] : ''));
        fwrite(STDOUT, "SCENARIO_OK $scenario $passed\n");
        $exit = 0;
    } catch (Throwable $error) {
        fwrite(STDOUT, $error->getMessage() . ' @' . $error->getFile() . ':' . $error->getLine() . "\n");
        $exit = 1;
    } finally {
        if ($created_table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        } elseif ($table_exists()) {
            foreach ($rows as $id) $wpdb->delete($table, array('id' => $id));
            if ($parked_ids) $wpdb->query("UPDATE {$table} SET status = 'pending' WHERE id IN (" . implode(',', $parked_ids) . ')');
        }
        foreach ($msgs as $id) $wpdb->delete($msg_table, array('msg_id' => $id));
        delete_transient('emsfb_stripe_reconcile_lock');
        wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');
        if ($original_event) wp_schedule_single_event($original_event, 'emsfb_stripe_reconcile_efb');
        if ($original_health === null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION, $original_health, false);
        if ($settings_injected) { wp_cache_delete('settings:decoded', 'emsfb'); get_setting_Emsfb('_clear_cache'); }
    }
    ob_end_flush();
    exit($exit);
}
