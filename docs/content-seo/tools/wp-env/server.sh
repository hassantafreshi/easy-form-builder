#!/usr/bin/env bash
# server.sh start|stop|status|restart  -- PHP built-in server for the local WordPress.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
PIDFILE="$EFB_WORK/php-server.pid"
LOGFILE="$EFB_WORK/php-server.log"

is_up() { curl -fsS -o /dev/null --max-time 5 --noproxy '*' "$WP_URL/wp-login.php"; }

case "${1:-status}" in
  start)
    if is_up; then efb_log "server already up at $WP_URL"; exit 0; fi
    [ -d "$WP_ROOT" ] || { efb_log "no WordPress at $WP_ROOT - run setup.sh first"; exit 1; }
    # Router: serve existing files, send everything else to index.php (pretty permalinks).
    cat > "$EFB_WORK/router.php" <<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ($path !== '/' && is_file($file)) { return false; }
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php') && $path !== '/') {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require rtrim($file, '/') . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
PHP
    (cd "$WP_ROOT" && PHP_CLI_SERVER_WORKERS="$WP_PHP_WORKERS" setsid nohup php \
        -d memory_limit=512M -d upload_max_filesize=64M -d post_max_size=64M -d max_execution_time=120 \
        -S "127.0.0.1:$WP_PORT" -t "$WP_ROOT" "$EFB_WORK/router.php" >"$LOGFILE" 2>&1 & echo $! >"$PIDFILE")
    for _ in $(seq 1 40); do is_up && { efb_log "server up at $WP_URL (pid $(cat "$PIDFILE"))"; exit 0; }; sleep 0.5; done
    efb_log "server failed to start; see $LOGFILE"; tail -20 "$LOGFILE" >&2; exit 1 ;;
  stop)
    if [ -f "$PIDFILE" ]; then
      pid="$(cat "$PIDFILE")"; pkill -P "$pid" 2>/dev/null || true; kill "$pid" 2>/dev/null || true; rm -f "$PIDFILE"; efb_log "server stopped"
    fi
    pkill -f "php .* -S 127.0.0.1:$WP_PORT" 2>/dev/null || true ;;
  restart) "$0" stop; sleep 1; "$0" start ;;
  status) if is_up; then echo "up $WP_URL"; else echo "down"; exit 1; fi ;;
  *) echo "usage: $0 start|stop|restart|status" >&2; exit 2 ;;
esac
