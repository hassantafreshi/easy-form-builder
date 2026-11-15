'use strict';
/**
 * SPLIT §10.1: the masks tests read the catalogue of a site with the Address & Postal add-on: core's
 * includes/data/masks-catalog-efb.json plus the add-on's data/postal-presets-efb.json (vendor/address or
 * EFB_ADDRESS_DIR), each preset in the place the docs catalogue has it.
 */
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
function addressDir() { return process.env.EFB_ADDRESS_DIR || path.join(ROOT, 'vendor', 'address'); }
function catalog() {
  const core = JSON.parse(fs.readFileSync(path.join(ROOT, 'includes', 'data', 'masks-catalog-efb.json'), 'utf8'));
  const postal = JSON.parse(fs.readFileSync(path.join(addressDir(), 'data', 'postal-presets-efb.json'), 'utf8'));
  const ref = JSON.parse(fs.readFileSync(path.join(ROOT, 'docs', 'feature-gaps', 'reference', 'masks-catalog.json'), 'utf8'));
  const all = new Map();
  core.masks.concat(postal.masks).forEach((m) => all.set(m.id, m));
  const out = [];
  ref.masks.forEach((m) => { if (all.has(m.id)) { out.push(all.get(m.id)); all.delete(m.id); } });
  all.forEach((m) => out.push(m));
  core.masks = out;
  return core;
}
module.exports = { catalog, addressDir };
