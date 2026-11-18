/**
 * Generates tests/fixtures/payout-builder-panels.json: the builder settings panels of the Stripe, PayPal and Persia
 * Pay fields as core drew them before PAY-OUT (git revision EFB_PAYOUT_CORE_BASE, default e890a6db), for
 * tests/test-payout-builder-panels.js, which holds the add-ons' panels (window.efbFieldPanels) to the same markup.
 *
 * Run: node tests/generate-payout-panels.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const L = require('./payout-panels-lib.js');

const rev = process.env.EFB_PAYOUT_CORE_BASE || 'e890a6db';
const src = execFileSync('git', ['-C', path.join(__dirname, '..'), 'show', rev + ':includes/admin/assets/js/val-efb.js'], { encoding: 'utf8', maxBuffer: 64 << 20 });
const out = { rev, cases: L.CASES.map(([type, row0, lang]) => ({ type, row0, lang, html: L.norm(L.oldCorePanel(src, type, row0, lang)) })) };
fs.writeFileSync(path.join(__dirname, 'fixtures/payout-builder-panels.json'), JSON.stringify(out, null, 1) + '\n');
console.log('cases: ' + out.cases.length);
