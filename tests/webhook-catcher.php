<?php
/**
 * Standalone webhook catcher for E2E testing (doc section 17.12).
 *
 * A zero-dependency endpoint on a separate path that ONLY logs what it
 * receives, so conditional webhook_rules deliveries can be verified without
 * any external service:
 *
 *   Endpoint / viewer URL:
 *   http://127.0.0.1/wp/wp-content/plugins/easy-form-builder/tests/webhook-catcher.php
 *
 *   - POST (EFB JSON webhook)            -> appended to wp-content/efb-webhook-catcher.log
 *   - GET with track_code/event_type     -> logged as a GET webhook delivery
 *   - plain GET in a browser             -> HTML viewer of received deliveries
 *   - GET ?clear=1                       -> wipes the log
 *
 * Dev fixture: access is restricted to localhost.
 */

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'local access only']);
    exit;
}

$log_file = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'efb-webhook-catcher.log';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$is_get_webhook = $method === 'GET' && (isset($_GET['track_code']) || isset($_GET['event_type']));

function catcher_log_entry($log_file, array $entry) {
    file_put_contents(
        $log_file,
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function catcher_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
}

/* ── Webhook deliveries: log and acknowledge ─────────────────────────────── */
if ($method === 'POST' || $is_get_webhook) {
    $raw_body = $method === 'POST' ? file_get_contents('php://input') : '';
    $decoded = $raw_body !== '' ? json_decode($raw_body, true) : null;

    $entry = [
        'received_at' => date('Y-m-d H:i:s'),
        'method' => $method,
        'headers' => [
            'content-type' => catcher_header('Content-Type'),
            'x-efb-webhook-id' => catcher_header('X-EFB-Webhook-Id'),
            'x-efb-rule-id' => catcher_header('X-EFB-Rule-Id'),
            'x-efb-track-code' => catcher_header('X-EFB-Track-Code'),
        ],
        'query' => $_GET,
        'body' => $decoded !== null ? $decoded : ($raw_body === '' ? null : mb_substr($raw_body, 0, 4000)),
    ];
    catcher_log_entry($log_file, $entry);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'logged' => true,
        'method' => $method,
        'webhook_id' => $entry['headers']['x-efb-webhook-id'] ?: ($_GET['webhook_id'] ?? ''),
    ]);
    exit;
}

/* ── ?clear=1: wipe the log ──────────────────────────────────────────────── */
if (isset($_GET['clear'])) {
    @unlink($log_file);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ── Plain GET: HTML viewer ──────────────────────────────────────────────── */
$entries = [];
if (file_exists($log_file)) {
    foreach (file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $entries[] = $row;
    }
}
$entries = array_reverse($entries); // newest first
$esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EFB Webhook Catcher</title>
<style>
  body { font-family: Segoe UI, Tahoma, sans-serif; background: #f1f5f9; margin: 0; padding: 24px; color: #0f172a; }
  .head { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .meta { color: #64748b; font-size: 13px; }
  a.btn { display: inline-block; background: #dc2626; color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: 13px; }
  a.btn.gray { background: #475569; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-top: 14px; box-shadow: 0 1px 3px rgba(15,23,42,.06); }
  .tag { display: inline-block; font-size: 12px; font-weight: 700; padding: 2px 10px; border-radius: 999px; margin-inline-end: 8px; }
  .tag.post { background: #dbeafe; color: #1d4ed8; }
  .tag.get { background: #dcfce7; color: #15803d; }
  .kv { color: #334155; font-size: 13px; margin: 2px 0; }
  .kv b { color: #0f172a; }
  pre { background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 12px; font-size: 12px; overflow-x: auto; margin: 10px 0 0; }
  .empty { color: #64748b; padding: 40px 0; text-align: center; }
</style>
</head>
<body>
  <div class="head">
    <h1>EFB Webhook Catcher</h1>
    <span class="meta"><?php echo count($entries); ?> deliveries — log: wp-content/efb-webhook-catcher.log</span>
    <a class="btn gray" href="">Refresh</a>
    <a class="btn" href="?clear=1" onclick="return confirm('Clear the webhook log?')">Clear log</a>
  </div>
<?php if (!$entries): ?>
  <div class="empty">No webhook received yet. Submit the test form, then refresh this page.</div>
<?php endif; ?>
<?php foreach ($entries as $e): ?>
  <div class="card">
    <span class="tag <?php echo strtolower($esc($e['method'] ?? '')); ?>"><?php echo $esc($e['method'] ?? '?'); ?></span>
    <span class="meta"><?php echo $esc($e['received_at'] ?? ''); ?></span>
    <div class="kv"><b>webhook_id:</b> <?php echo $esc($e['headers']['x-efb-webhook-id'] ?? ''); ?>
      &nbsp; <b>rule_id:</b> <?php echo $esc($e['headers']['x-efb-rule-id'] ?? ''); ?>
      &nbsp; <b>track:</b> <?php echo $esc(($e['headers']['x-efb-track-code'] ?? '') ?: ($e['query']['track_code'] ?? '')); ?></div>
<?php if (!empty($e['query'])): ?>
    <div class="kv"><b>query:</b> <?php echo $esc(json_encode($e['query'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div>
<?php endif; ?>
<?php if (isset($e['body']) && $e['body'] !== null): ?>
    <pre><?php echo $esc(json_encode($e['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
<?php endif; ?>
  </div>
<?php endforeach; ?>
</body>
</html>
