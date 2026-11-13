<?php
/**
 * F08-B/C: the PHP renderer (Formbuilder::addNewElement_efb) for hidden fields and smart tags, without WordPress.
 *  - hiddenField: an invisible wrapper and a hidden input; a fixed text is its value, a template with tags is
 *    never resolved into the HTML (data-st-hidden, value ""); the next field stays inside the form (tags count).
 *  - A form without tags renders byte for byte as before (label, description, text input, textarea, select).
 *  - Labels / descriptions / options / headings with tags: escaped static text, form / site / post resolved,
 *    other tags as isolated <bdi class="efb-st" data-st> pieces; a hostile fallback escaped; no user value.
 *  - Defaults / placeholders with visitor tags go to data-st-value / data-st-ph with an empty value.
 *
 * Run: php tests/test-hidden-field-render.php
 */
define('ABSPATH', __DIR__ . '/');
if (!function_exists('get_option')) { function get_option($o, $d = false) { return $d; } }
if (!function_exists('esc_attr')) { function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('is_rtl')) { function is_rtl() { return false; } }
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script() {} }
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';
$with_tags = getenv('EFB_NO_SMART') ? false : true;
if ($with_tags) require_once __DIR__ . '/../includes/class-Emsfb-smart-tags.php';

$pass = 0; $fail = 0;
function t($label, $ok, $detail = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n  " . substr($detail, 0, 700) . "\n"; } }
$base = array('step' => '1', 'amount' => 2, 'required' => 0, 'el_border_color' => 'border-d', 'el_height' => 'h-d-efb', 'el_text_color' => 'text-labelEfb', 'label_text_color' => 'text-labelEfb', 'classes' => '', 'message' => '', 'value' => '', 'label_text_size' => 'fs-6', 'el_text_size' => 'fs-6', 'size' => 100, 'label_position' => 'up');
$form = function ($fields) use ($base) {
	$rows = array((object) array('type' => 'form', 'formName' => 'Contact', 'steps' => 1), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'amount' => 1, 'name' => 'S'));
	foreach ($fields as $f) $rows[] = (object) array_merge($base, $f);
	return $rows;
};
$render = function ($rows, $i, $ctx = null) {
	$b = new \Emsfb\Formbuilder($rows, true);
	if ($ctx !== null) $b->st_ctx = $ctx;
	$texts = array('tfnapca' => 'x', 'nothingSelected' => 'Nothing selected', 'on' => 'On', 'off' => 'Off');
	$r = $b->addNewElement_efb($i, $rows[$i]->id_, 5, $texts, true);
	return is_array($r) ? $r[0] : '';
};
$opens = function ($h) { return preg_match_all('/<div\b/', $h); };
$closes = function ($h) { return preg_match_all('/<\/div>/', $h); };

/* hidden field */
$rows = $form(array(
	array('id_' => 'h1', 'type' => 'hiddenField', 'name' => 'Campaign', 'value' => 'spring-2026'),
	array('id_' => 'h2', 'type' => 'hiddenField', 'name' => 'Member', 'value' => '{user:email}'),
	array('id_' => 'h3', 'type' => 'hiddenField', 'name' => 'UTM', 'value' => '{get:utm_source|direct}'),
	array('id_' => 'n1', 'type' => 'text', 'name' => 'Name'),
));
$h1 = $render($rows, 2); $h2 = $render($rows, 3); $h3 = $render($rows, 4); $n1 = $render($rows, 5);
t('hidden: an invisible wrapper (d-none) and a hidden input', strpos($h1, 'd-none') !== false && preg_match('/<input type="hidden" class="efb emsFormBuilder_v" data-type="hiddenField" data-vid="h1" data-formid="5" id="h1_" name="h1" value="spring-2026">/', $h1) === 1, $h1);
t('hidden: no label, no description, nothing focusable', strpos($h1, '<label') === false && strpos($h1, '-des"') === false && strpos($h1, 'tabindex') === false, $h1);
t('hidden: a fixed text is its value', strpos($h1, 'value="spring-2026"') !== false, $h1);
t('hidden: {user:email} is never in the HTML (value "" and data-st-hidden)', strpos($h2, 'value=""') !== false && strpos($h2, 'data-st-hidden="1"') !== false && strpos($h2, 'user:email') === false, $h2);
t('hidden: {get:…} is resolved in the browser only', strpos($h3, 'value=""') !== false && strpos($h3, 'direct') === false, $h3);
t('hidden: balanced tags, so the next field stays in the form', $opens($h1) === $closes($h1) && $opens($h2) === $closes($h2), $opens($h1) . '/' . $closes($h1));
t('hidden: starts with a block tag (wpautop)', preg_match('/^<!--startTag hiddenField--><div/', $h1) === 1, substr($h1, 0, 80));
t('the next field renders normally', strpos($n1, 'id="n1_"') !== false && $opens($n1) === $closes($n1), $n1);

/* byte for byte without tags: the same markup with and without the smart-tags class loaded */
$plain = $form(array(
	array('id_' => 'p1', 'type' => 'text', 'name' => 'Name <i>', 'message' => 'Help <b>x</b> & more', 'placeholder' => 'Your "name"', 'value' => 'Ana'),
	array('id_' => 'p2', 'type' => 'textarea', 'name' => 'Note', 'placeholder' => 'Write', 'value' => "a@n#b"),
	array('id_' => 'p3', 'type' => 'select', 'name' => 'Plan', 'value' => ''),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'p3', 'value' => 'Pro', 'id_op' => 'o1'),
	array('id_' => 'p4', 'type' => 'radio', 'name' => 'Color', 'value' => ''),
	array('id_' => 'o2', 'type' => 'option', 'parent' => 'p4', 'value' => 'Red', 'id_op' => 'o2'),
	array('id_' => 'p5', 'type' => 'heading', 'name' => 'H', 'value' => 'Title & more'),
));
$snap = '';
foreach (array(2, 3, 4, 6, 8) as $i) $snap .= $render($plain, $i, array()) . "\n";
$file = __DIR__ . '/fixtures/smart-tags-render-snapshot.html';
if (getenv('EFB_WRITE_SNAPSHOT')) file_put_contents($file, $snap);
t('a form without tags renders byte for byte as the snapshot taken before F08', is_readable($file) && file_get_contents($file) === $snap, 'snapshot differs');

if ($with_tags) {
	$ctx = array('form' => array('id' => '5', 'name' => 'Contact <x>'), 'site' => array('name' => 'Site & Co', 'url' => 'https://site.test/'), 'post' => array('id' => '9', 'title' => 'Page', 'url' => '', 'type' => 'page'), 'fields' => array('color' => array('type' => 'radio', 'name' => 'Color', 'options' => array())), 'user' => array('id' => '1', 'email' => 'secret@example.test', 'display_name' => 'Secret Name'));
	$tag = $form(array(
		array('id_' => 'q1', 'type' => 'text', 'name' => 'Why {field:color|this}? ({form:name})', 'message' => 'For {site:name} <b>now</b> {user:display_name|you}', 'placeholder' => 'Hi {user:first_name|friend}', 'value' => '{get:email}'),
		array('id_' => 'q2', 'type' => 'text', 'name' => '{get:x|"><svg onload=1>}', 'value' => '{form:id}-{post:id}'),
		array('id_' => 'q3', 'type' => 'select', 'name' => 'Pick', 'value' => ''),
		array('id_' => 'o3', 'type' => 'option', 'parent' => 'q3', 'value' => 'Same as {field:color}', 'id_op' => 'o3'),
		array('id_' => 'q4', 'type' => 'radio', 'name' => 'R', 'value' => ''),
		array('id_' => 'o4', 'type' => 'option', 'parent' => 'q4', 'value' => 'Keep {field:color|it}', 'id_op' => 'o4'),
		array('id_' => 'q5', 'type' => 'heading', 'name' => 'H', 'value' => 'Hello {field:color|there}'),
		array('id_' => 'q6', 'type' => 'textarea', 'name' => 'T', 'value' => '{date:Y}', 'placeholder' => '{get:hint}'),
	));
	$q1 = $render($tag, 2, $ctx); $q2 = $render($tag, 3, $ctx); $q3 = $render($tag, 4, $ctx); $q4 = $render($tag, 6, $ctx); $q5 = $render($tag, 8, $ctx); $q6 = $render($tag, 9, $ctx);
	t('label: a field tag is an isolated piece, form:name resolved and escaped', strpos($q1, 'Why <bdi class="efb efb-st" data-st="field:color|this">this</bdi>? (Contact &lt;x&gt;)') !== false, $q1);
	t('description: static text as saved, site:name escaped, user tag a piece (no user value)', strpos($q1, 'For Site &amp; Co <b>now</b> <bdi class="efb efb-st" data-st="user:display_name|you">you</bdi>') !== false, $q1);
	t('no user value anywhere in the HTML (cached pages)', strpos($q1 . $q2 . $q3 . $q4 . $q5 . $q6, 'secret@example.test') === false && strpos($q1 . $q2 . $q3 . $q4 . $q5 . $q6, 'Secret Name') === false, '');
	t('default: {get:email} goes to data-st-value, value empty', strpos($q1, 'data-st-value="{get:email}"') !== false && strpos($q1, ' value="{get:email}"') === false && strpos($q1, 'aria-label="Why this? (Contact &lt;x&gt;)"') !== false, $q1);
	t('placeholder: the fallback text and data-st-ph', strpos($q1, 'placeholder=""') !== false || strpos($q1, 'data-st-ph="Hi {user:first_name|friend}"') !== false, $q1);
	t('a hostile fallback in a label is escaped', strpos($q2, '<svg') === false && strpos($q2, '&lt;svg onload=1&gt;') !== false, $q2);
	t('default: public tags are resolved in the HTML', strpos($q2, 'value="5-9"') !== false, $q2);
	t('select option: fallback text and data-st-text', strpos($q3, 'data-st-text="Same as {field:color}"') !== false && strpos($q3, '>Same as </option>') !== false, $q3);
	t('radio option label: a piece', strpos($q4, 'Keep <bdi class="efb efb-st" data-st="field:color|it">it</bdi>') !== false, $q4);
	t('heading: a piece', strpos($q5, 'Hello <bdi class="efb efb-st" data-st="field:color|there">there</bdi>') !== false, $q5);
	t('textarea: data-st-value / data-st-ph, nothing resolved', strpos($q6, 'data-st-value="{date:Y}"') !== false && strpos($q6, 'data-st-ph="{get:hint}"') !== false && $opens($q6) === $closes($q6), $q6);
}
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
