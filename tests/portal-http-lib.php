<?php
/**
 * Not a test. Helpers of the customer portal contract's HTTP tests on the real WordPress (tests/e2e-wp,
 * EFB_E2E_SITE_DIR): ctl-f20.php through WP-CLI, a portal client of the mock portal (mu-plugin efb-e2e-f20.php) with
 * its own cookie jar and CSRF header, and a sign-in by e-mail code (the code read from the captured e-mail).
 */
require_once __DIR__ . '/f12b-http-lib.php';

/** A portal client: its own cookie jar, the session's CSRF header. */
class EfbPortalHttpClient {
	public $jar; public $csrf = ''; private $H;
	public function __construct($H) { $this->H = $H; $this->jar = sys_get_temp_dir() . '/efb-prt-' . getmypid() . '-' . mt_rand(1000, 99999); }
	public function op($op, array $body = array(), $csrf = true, array $headers = array()) {
		if ($csrf && $this->csrf !== '') $headers['X-EFB-Portal-CSRF'] = $this->csrf;
		$r = $this->H->http('POST', '/wp-json/Emsfb/v1/e2e-portal/' . $op, $body ?: new stdClass(), $headers, true, $this->jar);
		$j = is_array($r['json']) ? $r['json'] : array('raw' => substr($r['body'], 0, 300));
		$j['_status'] = $r['status']; $j['_headers'] = $r['headers'];
		return $j;
	}
	public function __destruct() { @unlink($this->jar); }
}

class EfbPortalHttp {
	public $H;
	public function __construct(EfbF12bHttp $H) { $this->H = $H; }
	/** ctl-f20.php <args…> → its JSON answer. */
	public function c20(...$a) {
		$H = $this->H;
		$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f20.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
		$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
		return json_decode($lines ? end($lines) : '', true);
	}
	public function mails() { $l = $this->H->ctl('option', 'efb_e2e_mail_bodies'); return is_array($l) ? array_values($l) : array(); }
	public static function code_of($m) { return $m && preg_match('/letter-spacing:6px[^>]*>(\d{4,10})</', $m['message'], $x) ? $x[1] : ''; }
	/** Signs a client in by e-mail code; ['start', 'verify', 'code']. */
	public function signin(EfbPortalHttpClient $P, $dest) {
		$this->H->ctl('set-option', 'efb_e2e_mail_bodies', 'del');
		$s = $P->op('start', array('channel' => 'email', 'dest' => $dest));
		if (empty($s['success'])) return array('start' => $s);
		$m = $this->mails();
		$code = self::code_of(end($m));
		$v = $P->op('verify', array('cid' => $s['data']['challenge_id'], 'code' => $code));
		if (!empty($v['success'])) $P->csrf = $v['data']['csrf'];
		return array('start' => $s, 'verify' => $v, 'code' => $code);
	}
}
