<?php
/**
 * SPLIT §10.1: the 18 postal_code presets are the Address & Postal add-on's (AdnADR, data/postal-presets-efb.json).
 * The masks tests run as a site with the add-on: the catalogue they read is core's plus the add-on's presets, and the
 * add-on's labels. The add-on is found at vendor/address (a symlink to vendores-efb) or EFB_ADDRESS_DIR.
 */
function efb_test_address_dir() {
	return getenv('EFB_ADDRESS_DIR') ?: __DIR__ . '/../vendor/address';
}

/** Core's catalogue with the add-on's presets, each in the place the docs catalogue has it. */
function efb_test_masks_catalog() {
	$core = json_decode(file_get_contents(__DIR__ . '/../includes/data/masks-catalog-efb.json'), true);
	$postal = json_decode(file_get_contents(efb_test_address_dir() . '/data/postal-presets-efb.json'), true);
	$ref = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/masks-catalog.json'), true);
	$all = array();
	foreach (array_merge($core['masks'], $postal['masks']) as $m) $all[$m['id']] = $m;
	$out = array();
	foreach ($ref['masks'] as $m) if (isset($all[$m['id']])) { $out[] = $all[$m['id']]; unset($all[$m['id']]); }
	foreach ($all as $m) $out[] = $m;
	$core['masks'] = $out;
	return $core;
}

/** Masks::catalog() of a site with the add-on (its efb_mask_presets filter applied). */
function efb_test_masks_install_address() {
	if (!class_exists('\Emsfb\Masks')) require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
	\Emsfb\Masks::reset();
	$r = new ReflectionClass('\Emsfb\Masks');
	$data = efb_test_masks_catalog();
	$c = $r->getProperty('catalog'); $c->setAccessible(true); $c->setValue(null, $data);
	$index = array();
	foreach ($data['masks'] as $m) if (is_array($m) && isset($m['id'], $m['regex'])) $index[$m['id']] = $m;
	$i = $r->getProperty('index'); $i->setAccessible(true); $i->setValue(null, $index);
	return $data;
}
