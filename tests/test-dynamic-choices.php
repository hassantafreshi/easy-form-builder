<?php
/**
 * F05-F: choices built from the site (\Emsfb\Choices::expand_dynamic / dynamic_build), WordPress stubbed.
 *
 *  - posts: only a public post type, only status "publish", never a password-protected post (asked of WordPress
 *    and checked again on what it returns), the category filter; label = title, stored value = ID;
 *  - terms: only a public taxonomy, hide_empty; value = slug;  users: display name only (never login or e-mail),
 *    the roles asked for;  csv: an attachment of the owner (never a URL): a BOM, the header, quoted commas,
 *    Persian digits in values, at most 2000 rows / 1 MB, a non-CSV file refused;
 *  - the ids are the same on every expansion ({field}_p{ID}, _t{term}, _u{ID}, _c{md5 8}); the typed choices are
 *    set aside while the field has a source; a field without the plan keeps its typed choices; a cached list is
 *    used for the page (500 items read in under 200 ms), a fresh one on submit; the filter efb_dynamic_choices
 *    can add or remove items (cleaned); flush on save; the schema lists the options without secrets.
 *
 * Run: php tests/test-dynamic-choices.php
 */
define('ABSPATH', __DIR__ . '/');
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$GLOBALS['plan'] = true; $GLOBALS['calls'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['filters'] = array();
function efb_plan_at_least_efb($p) { return $GLOBALS['plan']; }
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $v))); }
function wp_specialchars_decode($s, $q = 0) { return htmlspecialchars_decode((string) $s, ENT_QUOTES); }
function get_post_types($a, $o = 'names') { return array('post' => 'post', 'page' => 'page', 'product' => 'product', 'attachment' => 'attachment'); }
function get_taxonomy($t) { return in_array($t, array('category', 'post_tag'), true) ? (object) array('public' => true) : ($t === 'secret_tax' ? (object) array('public' => false) : false); }
function is_wp_error($x) { return false; }
$GLOBALS['posts'] = array(
	(object) array('ID' => 11, 'post_title' => 'Alpha &amp; Co', 'post_status' => 'publish', 'post_password' => ''),
	(object) array('ID' => 12, 'post_title' => 'Beta', 'post_status' => 'publish', 'post_password' => ''),
	(object) array('ID' => 13, 'post_title' => 'Draft one', 'post_status' => 'draft', 'post_password' => ''),
	(object) array('ID' => 14, 'post_title' => 'Locked', 'post_status' => 'publish', 'post_password' => 'pw'),
);
function get_posts($q) { $GLOBALS['calls'][] = array('get_posts', $q); return $GLOBALS['posts']; }
function get_the_title($p) { return $p->post_title; }
function get_terms($q) { $GLOBALS['calls'][] = array('get_terms', $q); return array((object) array('term_id' => 5, 'name' => 'News', 'slug' => 'news'), (object) array('term_id' => 6, 'name' => 'Events <b>', 'slug' => 'events')); }
function get_users($q) { $GLOBALS['calls'][] = array('get_users', $q); return array((object) array('ID' => 2, 'display_name' => 'Ana Díaz', 'user_email' => 'ana@example.test', 'user_login' => 'ana')); }
$GLOBALS['att'] = array();
function get_attached_file($id) { return isset($GLOBALS['att'][$id]) ? $GLOBALS['att'][$id] : false; }
function get_post_mime_type($id) { return isset($GLOBALS['att'][$id]) ? (substr($GLOBALS['att'][$id], -4) === '.csv' ? 'text/csv' : 'image/png') : false; }
function get_transient($k) { return isset($GLOBALS['tr'][$k]) ? $GLOBALS['tr'][$k] : false; }
function set_transient($k, $v, $e) { $GLOBALS['tr'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['tr'][$k]); return true; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['filters'][$h])) $a[1] = call_user_func_array($GLOBALS['filters'][$h], array_slice($a, 1)); return $a[1]; }
require __DIR__ . '/../includes/class-Emsfb-choices.php';
require __DIR__ . '/../includes/class-Emsfb-stock.php';
use Emsfb\Choices;

$form = array(array('type' => 'form'),
	array('id_' => 'p', 'type' => 'select', 'name' => 'Post', 'dyn_src' => 'posts', 'dyn_post_type' => 'post', 'dyn_tax' => 'category', 'dyn_term' => '5,7', 'dyn_limit' => '20', 'dyn_orderby' => 'date', 'dyn_order' => 'DESC'),
	array('id_' => 'p0', 'type' => 'option', 'parent' => 'p', 'value' => 'Typed'),
	array('id_' => 'next', 'type' => 'text', 'name' => 'Next'),
);
$out = Choices::expand_dynamic($form, 3);
$opts = array_values(array_filter($out, function ($r) { return ($r['type'] ?? '') === 'option'; }));
t('posts: published and password-free only; the typed choice set aside', array_map(function ($o) { return array($o['id_'], $o['value'], $o['opt_value'], $o['parent'], $o['dyn']); }, $opts), array(array('p_p11', 'Alpha & Co', '11', 'p', '1'), array('p_p12', 'Beta', '12', 'p', '1')));
$q = $GLOBALS['calls'][0][1];
t('WordPress is asked for published, password-free posts of a public type, with the category and the order', array($q['post_type'], $q['post_status'], $q['has_password'], $q['numberposts'], $q['orderby'], $q['order'], $q['tax_query'][0]['field'], $q['tax_query'][0]['terms']), array('post', 'publish', false, 20, 'date', 'DESC', 'term_id', array(5, 7)));
t('the rows keep their order: the field, its choices, then the next field', array_column(array_slice($out, 1), 'id_'), array('p', 'p_p11', 'p_p12', 'next'));
$GLOBALS['calls'] = array();
$again = Choices::expand_dynamic($form, 3);
t('the page: the cached list (no second query), the same ids', array(count($GLOBALS['calls']), array_column($again, 'id_')), array(0, array_column($out, 'id_')));
Choices::expand_dynamic($form, 3, true);
t('the submit: a fresh list (the source is asked again)', count($GLOBALS['calls']), 1);
foreach (array('attachment', 'secret_type') as $pt) {
	$f = $form; $f[1]['dyn_post_type'] = $pt;
	t("a post type that is not public ($pt): no choice", count(Choices::dynamic_build($f[1])), 0);
}
$f = $form; $f[1]['dyn_tax'] = 'secret_tax';
Choices::dynamic_build($f[1]);
t('a category filter on a private taxonomy is not used', isset(end($GLOBALS['calls'])[1]['tax_query']), false);

/* terms, users */
$tf = array('id_' => 't', 'type' => 'radio', 'dyn_src' => 'terms', 'dyn_tax' => 'category');
$items = Choices::dynamic_build($tf);
t('terms: name / slug, hide_empty asked, markup removed', array($items, end($GLOBALS['calls'])[1]['hide_empty']), array(array(array('id' => 't_t5', 'label' => 'News', 'value' => 'news'), array('id' => 't_t6', 'label' => 'Events', 'value' => 'events')), true));
t('terms of a private taxonomy: nothing', Choices::dynamic_build(array('id_' => 't', 'type' => 'radio', 'dyn_src' => 'terms', 'dyn_tax' => 'secret_tax')), array());
$uf = array('id_' => 'u', 'type' => 'checkbox', 'dyn_src' => 'users', 'dyn_role' => 'author,editor');
$items = Choices::dynamic_build($uf);
$uq = end($GLOBALS['calls'])[1];
t('users: the display name and the ID only (no login, no e-mail asked or kept)', array($items, $uq['fields'], $uq['role__in']), array(array(array('id' => 'u_u2', 'label' => 'Ana Díaz', 'value' => '2')), array('ID', 'display_name'), array('author', 'editor')));
t('no e-mail or login anywhere in the list', strpos(json_encode($items), 'example.test') === false && strpos(json_encode($items), '"ana"') === false, true);

/* csv */
$dir = sys_get_temp_dir() . '/efb-dyn-' . getmypid();
@mkdir($dir);
file_put_contents($dir . '/list.csv', "\xEF\xBB\xBFlabel,value\nتهران,۲۳\n\"Comma, Town\",ct\nPlain,\n,orphan\n");
file_put_contents($dir . '/pic.png', "x");
file_put_contents($dir . '/big.csv', str_repeat("a,b\n", 300000));
$GLOBALS['att'] = array(7 => $dir . '/list.csv', 8 => $dir . '/pic.png', 9 => $dir . '/big.csv');
$cf = array('id_' => 'c', 'type' => 'radio', 'dyn_src' => 'csv', 'dyn_csv' => '7');
$items = Choices::dynamic_build($cf);
t('csv: BOM and header removed, a quoted comma kept, Persian digits read, an empty value = the label, a row without a label skipped', array_map(function ($i) { return array($i['label'], $i['value']); }, $items), array(array('تهران', '23'), array('Comma, Town', 'ct'), array('Plain', 'Plain')));
t('csv ids: c + 8 characters of md5(value), the same every time', array($items[0]['id'], Choices::dynamic_build($cf)[0]['id']), array('c_c' . substr(md5('23'), 0, 8), 'c_c' . substr(md5('23'), 0, 8)));
t('a file that is not a CSV: nothing', Choices::dynamic_build(array('id_' => 'c', 'type' => 'radio', 'dyn_src' => 'csv', 'dyn_csv' => '8')), array());
t('a CSV over 1 MB: nothing', Choices::dynamic_build(array('id_' => 'c', 'type' => 'radio', 'dyn_src' => 'csv', 'dyn_csv' => '9')), array());
t('no attachment id: nothing (a URL is never read)', Choices::dynamic_build(array('id_' => 'c', 'type' => 'radio', 'dyn_src' => 'csv', 'dyn_csv' => '')), array());
$rows = Choices::parse_csv(str_repeat("x,y\n", 2500));
t('csv: at most 2000 rows', count($rows), 2000);

/* plan, filter, flush */
$GLOBALS['plan'] = false;
$plain = Choices::expand_dynamic($form, 4);
t('without the plan: the typed choices stay, no source is read', array_column(array_values(array_filter($plain, function ($r) { return ($r['type'] ?? '') === 'option'; })), 'value'), array('Typed'));
$GLOBALS['plan'] = true;
$GLOBALS['filters']['efb_dynamic_choices'] = function ($items, $f, $fid) { $items[] = array('label' => 'Extra <script>', 'value' => 'x"y'); $items[] = array('label' => ''); return $items; };
$GLOBALS['tr'] = array();
$items = Choices::dynamic_items($form[1], 9, true);
t('efb_dynamic_choices adds an item (cleaned, a stable id); an empty label dropped', end($items), array('id' => 'p_c' . substr(md5('xy'), 0, 8), 'label' => 'Extra', 'value' => 'xy'));
unset($GLOBALS['filters']['efb_dynamic_choices']);
t('the cached lists of a form are listed for the flush', isset($GLOBALS['opts']['efb_dyn_keys'][9]), true);
Choices::flush_dynamic(9);
t('a save flushes the form\'s lists', count(array_filter(array_keys($GLOBALS['tr']), function ($k) { return strpos($k, 'efb_dyn_9_') === 0; })), 0);
Choices::expand_dynamic($form, 3);
Choices::flush_all_dynamic();
t('a post, term or user change flushes every list', count($GLOBALS['tr']), 0);

/* 500 from the cache */
$GLOBALS['posts'] = array();
for ($i = 1; $i <= 500; $i++) $GLOBALS['posts'][] = (object) array('ID' => $i, 'post_title' => 'Post ' . $i, 'post_status' => 'publish', 'post_password' => '');
$big = $form; $big[1]['dyn_limit'] = '500';
Choices::expand_dynamic($big, 5);
$t0 = microtime(true);
$o = Choices::expand_dynamic($big, 5);
$ms = (microtime(true) - $t0) * 1000;
t('500 choices from the cache in under 200 ms', array(count($o) - 3, $ms < 200), array(500, true));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
