<?php
/**
 * F17-C: the server's part of a submission (Emsfb\Address_Schemes::check_row / prepare_form / finish_rows), the code
 * validate_rows_efb() runs after each type branch and after the loop:
 *  - a part's value is stored normalized (۱۲۳۴۵-۶۷۸۹۰ → 1234567890, ٠١٢٣ → 0123, rrrd2929 → RRRD2929, sw1a1aa → SW1A 1AA)
 *    with grp / part / cmp / sch written from the saved field (never from the browser);
 *  - a wrong value is refused with the translated message naming the field and a code (na_format, na_length, na_list,
 *    na_name); a forged province (not one of the list's options) is refused; a cascading city is a place name;
 *  - name_strict refuses "Ali2" and keeps "علی‌رضا", "عبد الله", "O'Neil"; name_strict 0 keeps digits;
 *  - Saudi "short address or full address": a valid short code makes the other parts optional; neither → refused;
 *  - each group's first row gets na_full (the P22 line); rows of fields without keys are untouched.
 *
 * Run: php tests/test-address-submit-rows.php
 */
define('ABSPATH', __DIR__ . '/');
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($h, $v) { return $h === 'efb_geo_strict_lists' && !empty($GLOBALS['loose']) ? false : $v; }
require_once __DIR__ . '/../includes/class-Emsfb-address-schemes.php';
use Emsfb\Address_Schemes as S;

$pass = 0; $fail = 0;
function t($name, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] {$name}\n"; } else { $fail++; echo "[FAIL] {$name}" . ($info !== '' ? ": " . $info : '') . "\n"; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } }
$L = array('addrInvalidX' => '%s is not valid.', 'addrPostalX' => 'Postal code of %s?', 'nmInvalidX' => '%s: letters only.', 'addrSaShortOrFull' => 'Short or full.');
$f = function ($id, $type, $kind, $set, $part, $x = array()) {
	return array_merge(array('id_' => $id, 'type' => $type, 'name' => 'F' . $id, 'required' => '0', 'cmp_grp' => 'g', 'cmp_kind' => $kind), $kind === 'name' ? array('name_format' => $set, 'name_part' => $part) : array('addr_scheme' => $set, 'addr_part' => $part), $x);
};
$item = function ($id, $v) { return array('id_' => $id, 'name' => 'x', 'type' => 'text', 'value' => $v, 'grp' => 'forged', 'part' => 'forged'); };

$r = S::check_row($f('p', 'postalcode', 'address', 'ir', 'postal'), $item('p', '۱۲۳۴۵-۶۷۸۹۰'), array(), $L);
t('IR postal: ۱۲۳۴۵-۶۷۸۹۰ stored as 1234567890', $r['ok'] && $r['item']['value'] === '1234567890');
t('the structure comes from the saved field (grp, part, cmp, sch), not from the browser', $r['item']['grp'] === 'g' && $r['item']['part'] === 'postal' && $r['item']['cmp'] === 'address' && $r['item']['sch'] === 'ir');
$r = S::check_row($f('p', 'postalcode', 'address', 'ir', 'postal'), $item('p', '۱۲۳۴'), array(), $L);
t('IR postal "۱۲۳۴": refused with the postal message naming the field, code na_format', !$r['ok'] && $r['m'] === "Postal code of <b>\u{2068}Fp\u{2069}</b>?" && $r['code'] === 'na_format');
$r = S::check_row($f('q', 'address_line', 'address', 'ir', 'plaque'), $item('q', '٠١٢٣'), array(), $L);
t('plaque ٠١٢٣ → 0123', $r['ok'] && $r['item']['value'] === '0123');
$r = S::check_row($f('s', 'address_line', 'address', 'sa', 'short_code'), $item('s', 'rrrd2929'), array(), $L);
t('short code rrrd2929 → RRRD2929', $r['ok'] && $r['item']['value'] === 'RRRD2929');
$r = S::check_row($f('u', 'postalcode', 'address', 'uk', 'postal'), $item('u', 'sw1a1aa'), array(), $L);
t('UK sw1a1aa → SW1A 1AA', $r['ok'] && $r['item']['value'] === 'SW1A 1AA');
$r = S::check_row($f('t', 'address_line', 'address', 'ir', 'street'), $item('t', 'x'), array(), $L);
t('a street of one letter: na_length with the general message', !$r['ok'] && $r['code'] === 'na_length' && $r['m'] === "<b>\u{2068}Ft\u{2069}</b> is not valid.");
$r = S::check_row($f('h', 'address_line', 'address', 'ir', 'street', array('name' => '<img src=x onerror=1>')), $item('h', 'x'), array(), $L);
t('a field name with markup is escaped in the message', strpos($r['m'], '<img') === false);

// Lists.
$fields = array($f('st', 'stateProvince', 'address', 'ir', 'state', array('country' => 'ir')), array('id_' => 'o1', 'type' => 'option', 'parent' => 'st', 'value' => 'تهران (Tehran)'), array('id_' => 'o2', 'type' => 'option', 'parent' => 'st', 'value' => 'فارس (Fars)'));
$r = S::check_row($fields[0], $item('st', 'تهران (Tehran)'), $fields, $L);
t('a province of the list is accepted', $r['ok']);
$r = S::check_row($fields[0], $item('st', 'Atlantis'), $fields, $L);
t('a forged province is refused (na_list)', !$r['ok'] && $r['code'] === 'na_list');
$GLOBALS['loose'] = true;
$r = S::check_row($fields[0], $item('st', 'Atlantis'), $fields, $L);
t('efb_geo_strict_lists false: the list is not compared', $r['ok']);
$GLOBALS['loose'] = false;
// Final test (F17 needs_states): a fixed country's province list the form does not hold (the server templates /
// AutoPilot leave it for the page to load) refused every answer; it is now checked like the other run-time lists.
$bare = array($f('sb', 'stateProvince', 'address', 'ir', 'state', array('country' => 'ir')));
t('a fixed-country province with no stored list: a place name is accepted (the page loaded the list)', S::check_row($bare[0], $item('sb', 'تهران (Tehran)'), $bare, $L)['ok']);
t('… digits or markup are still refused', !S::check_row($bare[0], $item('sb', 'Tehran 1'), $bare, $L)['ok'] && !S::check_row($bare[0], $item('sb', '<b>x</b>'), $bare, $L)['ok']);
t('its select tells the page the country (data-country)', strpos(S::list_attrs($bare[0]), 'data-country="ir"') !== false && strpos(S::list_attrs($f('cx', 'cityList', 'address', 'ir', 'city', array('addr_cascade' => '1'))), 'data-country') === false);
$city = $f('c', 'cityList', 'address', 'ir', 'city', array('addr_cascade' => '1'));
t('a cascading city: a place name is accepted (the list is the page\'s)', S::check_row($city, $item('c', 'شمیران (Shemiran)'), array(), $L)['ok']);
t('a cascading city with digits or markup is refused', !S::check_row($city, $item('c', 'City 12'), array(), $L)['ok'] && !S::check_row($city, $item('c', '<b>x</b>'), array(), $L)['ok']);
$intl = $f('is', 'stateProvince', 'address', 'intl', 'state');
t('international state (its list follows the chosen country): not compared with stored options', S::check_row($intl, $item('is', 'Bavaria'), $fields, $L)['ok']);

// Names.
$nm = $f('n', 'text', 'name', 'fl', 'first', array('name_strict' => '1'));
foreach (array('علی‌رضا', 'عبد الله', "O'Neil", 'Zoë') as $v) t("strict name \"$v\" accepted", S::check_row($nm, $item('n', $v), array(), $L)['ok']);
$r = S::check_row($nm, $item('n', 'Ali2'), array(), $L);
t('strict name "Ali2" refused (na_name)', !$r['ok'] && $r['code'] === 'na_name' && $r['m'] === "<b>\u{2068}Fn\u{2069}</b>: letters only.");
t('name_strict 0 (a form from before): digits kept', S::check_row($f('n', 'text', 'name', 'fl', 'first', array('name_strict' => '0')), $item('n', 'Ali2'), array(), $L)['ok']);
t('a row of an old field (no keys) is untouched', S::check_row(array('id_' => 'x', 'type' => 'text'), $item('x', 'Ali2'), array(), $L)['item']['value'] === 'Ali2');

// Saudi "short or full".
$sa = array(
	$f('b', 'address_line', 'address', 'sa', 'building_no', array('required' => '1', 'addr_req_mode' => 'sa_short_or_full')),
	$f('s', 'address_line', 'address', 'sa', 'street', array('required' => '1')),
	$f('k', 'address_line', 'address', 'sa', 'short_code'),
);
$p = S::prepare_form($sa, array(array('id_' => 'k', 'value' => 'rrrd 2929')));
t('prepare_form: a valid short code makes the other parts optional', $p[0]['required'] === 0 && $p[1]['required'] === 0 && $p[2]['required'] === '0');
$p = S::prepare_form($sa, array(array('id_' => 'k', 'value' => 'RRR29')));
t('prepare_form: an invalid short code changes nothing', $p[0]['required'] === '1');
$p = S::prepare_form(array($f('b', 'address_line', 'address', 'sa', 'building_no', array('required' => '1'))), array());
t('prepare_form: without the mode nothing changes', $p[0]['required'] === '1');
$rows = array(S::check_row($sa[2], $item('k', 'RRRD2929'), array(), $L)['item']);
$fin = S::finish_rows($sa, $rows, $L, 'ar');
t('finish_rows: the short code alone is enough', $fin['ok']);
t('finish_rows: the first row of the group has the line (na_full), labels from the saved fields', ($fin['rows'][0]['na_full'] ?? '') === 'Fk RRRD2929', json_encode($fin['rows'][0], JSON_UNESCAPED_UNICODE));
$fin = S::finish_rows($sa, array(S::check_row($sa[1], $item('s', 'King Fahd Rd'), array(), $L)['item']), $L, 'ar');
t('finish_rows: no short code and a required part missing → refused at that part', !$fin['ok'] && $fin['field_id'] === 'b' && $fin['m'] === 'Short or full.' && $fin['code'] === 'na_sa_short_or_full');
$all = array();
foreach (array(array('b', '2929'), array('s', 'King Fahd Rd')) as $x) $all[] = S::check_row($sa[$x[0] === 'b' ? 0 : 1], $item($x[0], $x[1]), array(), $L)['item'];
t('finish_rows: the full address without the short code is accepted', S::finish_rows($sa, $all, $L, 'ar')['ok']);
$fin = S::finish_rows(array($f('p', 'postalcode', 'address', 'ir', 'postal'), $f('t', 'address_line', 'address', 'ir', 'street')), array(
	S::check_row($f('t', 'address_line', 'address', 'ir', 'street'), $item('t', 'خیابان آزادی'), array(), $L)['item'],
	S::check_row($f('p', 'postalcode', 'address', 'ir', 'postal'), $item('p', '1234567890'), array(), $L)['item'],
	array('id_' => 'z', 'type' => 'text', 'value' => 'old'),
), $L, 'fa_IR');
t('na_full in the page language: Persian separator, label before the postal code', ($fin['rows'][0]['na_full'] ?? '') === 'خیابان آزادی، Fp 1234567890', json_encode($fin['rows'], JSON_UNESCAPED_UNICODE));
t('the old row is untouched', $fin['rows'][2] === array('id_' => 'z', 'type' => 'text', 'value' => 'old'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
