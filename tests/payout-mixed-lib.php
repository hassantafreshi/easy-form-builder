<?php
/**
 * Not a test. PAY-OUT mixed versions on the e2e site (tests/e2e-wp): each PAY-OUT step must ship on its own, so a site
 * with the new core and an OLD Stripe / PayPal / Persia Pay add-on, or the OLD core and a new add-on, keeps taking
 * payments or shows the clear "update the add-on" notice. This puts a version of core and of each legacy add-on in
 * place on that site and puts the working copies back afterwards (also on a fatal error, register_shutdown_function).
 *
 *   old core   = git archive of the core repository at EFB_PAYOUT_CORE_BASE (default e890a6db, before PAY-OUT)
 *   old add-on = git archive of the add-ons repository at EFB_PAYOUT_ADDON_BASE (default 5c4051e, before PAY-OUT)
 *   new        = the working copies (this repository; the add-ons repository the core's vendor/<dir> links to)
 *
 * Snapshots are made once under sys_get_temp_dir()/efb-payout-snap. The site's plugin folder
 * (wp-content/plugins/easy-form-builder) and the core's vendor/<dir> are symbolic links: only links are changed. The
 * test server must re-read links soon (php -d realpath_cache_ttl=1); use() waits until the site answers with the
 * expected version (ctl-payout.php versions, read over HTTP-independent WP-CLI, and a probe over HTTP).
 */

require_once __DIR__ . '/f11-http-lib.php';

class EfbPayoutMixed {
	const ADDONS = array('stripe', 'paypal', 'persiapay');

	public $H;
	public $core_new;
	public $addons_repo;
	public $plugin_link;
	public $snap;
	public $core_base;
	public $addon_base;
	private $restored = true;

	public function __construct(EfbF11Http $H) {
		$this->H = $H;
		$this->core_new = dirname(__DIR__);
		$t = @readlink($this->core_new . '/vendor/stripe');
		$this->addons_repo = getenv('EFB_ADDONS_REPO') ?: ($t ? dirname($t) : dirname($this->core_new) . '/vendores-efb');
		$this->plugin_link = rtrim((string) $H->site, '/') . '/wp-content/plugins/easy-form-builder';
		$this->snap = getenv('EFB_PAYOUT_SNAP') ?: sys_get_temp_dir() . '/efb-payout-snap';
		$this->core_base = getenv('EFB_PAYOUT_CORE_BASE') ?: 'e890a6db';
		$this->addon_base = getenv('EFB_PAYOUT_ADDON_BASE') ?: '5c4051e';
		register_shutdown_function(array($this, 'restore'));
	}

	/** Why the mixed tests cannot run here (null = they can). */
	public function unavailable() {
		if (!is_link($this->plugin_link)) return 'the site\'s plugin folder is not a link (' . $this->plugin_link . ')';
		if (!is_dir($this->addons_repo . '/.git') && !is_file($this->addons_repo . '/.git')) return 'the add-ons repository is not found (EFB_ADDONS_REPO)';
		foreach (array(array($this->core_new, $this->core_base), array($this->addons_repo, $this->addon_base)) as $r) {
			exec('git -C ' . escapeshellarg($r[0]) . ' cat-file -t ' . escapeshellarg($r[1]) . ' 2>/dev/null', $o, $rc);
			if ($rc !== 0) return 'commit ' . $r[1] . ' is not in ' . $r[0];
		}
		return null;
	}

	private function sh($cmd) {
		exec($cmd . ' 2>&1', $out, $rc);
		if ($rc !== 0) throw new RuntimeException("command failed ($rc): $cmd\n" . implode("\n", $out));
		return $out;
	}

	/** The old core: an archive of the core at its base, with vendor/ (autoload, composer, add-on links) like the new one. */
	public function old_core() {
		$dir = $this->snap . '/core-' . $this->core_base;
		if (!is_file($dir . '/emsfb.php')) {
			@mkdir($dir, 0777, true);
			$this->sh('git -C ' . escapeshellarg($this->core_new) . ' archive ' . escapeshellarg($this->core_base) . ' | tar -x -C ' . escapeshellarg($dir));
			@mkdir($dir . '/vendor', 0777, true);
			$this->sh('cp -R ' . escapeshellarg($this->core_new . '/vendor/autoload.php') . ' ' . escapeshellarg($this->core_new . '/vendor/composer') . ' ' . escapeshellarg($dir . '/vendor/'));
		}
		// Every add-on link of the new core, in the old core too (the legacy ones are set by use()).
		foreach (scandir($this->core_new . '/vendor') as $e) {
			$src = $this->core_new . '/vendor/' . $e;
			if ($e[0] === '.' || !is_link($src) || in_array($e, self::ADDONS, true)) continue;
			$dst = $dir . '/vendor/' . $e;
			if (!is_link($dst)) @symlink(readlink($src), $dst);
		}
		return $dir;
	}

	/** The old add-ons: an archive of the add-ons repository at its base. */
	public function old_addons() {
		$dir = $this->snap . '/addons-' . $this->addon_base;
		if (!is_dir($dir . '/stripe')) {
			@mkdir($dir, 0777, true);
			$this->sh('git -C ' . escapeshellarg($this->addons_repo) . ' archive ' . escapeshellarg($this->addon_base) . ' ' . implode(' ', array_map('escapeshellarg', self::ADDONS)) . ' | tar -x -C ' . escapeshellarg($dir));
		}
		return $dir;
	}

	private function link($path, $target) {
		if (is_link($path) && readlink($path) === $target) return;
		$tmp = $path . '.efbtmp';
		@unlink($tmp);
		if (!symlink($target, $tmp) || !rename($tmp, $path)) throw new RuntimeException("cannot link $path → $target");
	}

	/**
	 * @param string $core   'new' | 'old'
	 * @param array  $addons dir => 'new' | 'old' (missing: new)
	 */
	public function setup($core, array $addons = array()) {
		$this->restored = false;
		$core_dir = $core === 'old' ? $this->old_core() : $this->core_new;
		$old_addons = in_array('old', $addons, true) ? $this->old_addons() : null;
		foreach (self::ADDONS as $a) {
			$which = isset($addons[$a]) ? $addons[$a] : 'new';
			$this->link($core_dir . '/vendor/' . $a, $which === 'old' ? $old_addons . '/' . $a : $this->addons_repo . '/' . $a);
		}
		$this->link($this->plugin_link, $core_dir);
		$this->wait($core_dir);
		return $core_dir;
	}

	/** Until the site (WP-CLI and HTTP) runs the core folder just put in place. */
	private function wait($core_dir) {
		$want = realpath($core_dir);
		for ($i = 0; $i < 70; $i++) {
			$v = $this->ctl('versions');
			$r = $this->H->http('GET', '/wp-json/efb-e2e/v1/payout-probe', null, array(), false);
			if (is_array($v) && ($v['dir'] ?? '') === $want && ($r['json']['dir'] ?? '') === $want) return true;
			usleep($i < 10 ? 300000 : 2000000);
		}
		throw new RuntimeException('the site did not switch to ' . $want);
	}

	public function restore() {
		if ($this->restored) return;
		$this->restored = true;
		foreach (self::ADDONS as $a) $this->link($this->core_new . '/vendor/' . $a, $this->addons_repo . '/' . $a);
		$this->link($this->plugin_link, $this->core_new);
		try { $this->wait($this->core_new); } catch (Exception $e) { fwrite(STDERR, $e->getMessage() . "\n"); }
	}

	public function ctl($cmd, ...$args) {
		$parts = array_merge(preg_split('/\s+/', trim($this->H->wpcli)), array('eval-file', __DIR__ . '/e2e-wp/ctl-payout.php', $cmd), array_map('strval', $args), array('--path=' . $this->H->site, '--allow-root'));
		$out = shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null');
		$lines = array_values(array_filter(explode("\n", (string) $out), 'strlen'));
		return json_decode($lines ? end($lines) : '', true);
	}
}
