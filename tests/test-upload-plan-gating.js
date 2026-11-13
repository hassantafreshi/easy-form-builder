#!/usr/bin/env node
/**
 * F07-J: plan gating of the builder's upload controls (upload-builder-efb.js), from the capability
 * registry (efb_var.capabilities, Uploads::capabilities()):
 *  Free       several files, the total, resizing and the Media Library are locked (gem, disabled, the
 *             Media Library option disabled); the camera and the private store stay open (free parts);
 *  Free Plus  all field controls open; "Attach uploaded files to the admin email" still locked (Pro);
 *  Pro        everything open.
 * The handlers write the keys with the save rules and the panel follows the preset (camera for image /
 * media only, resizing where images arrive), and the canvas input gets multiple / capture.
 *
 * Run: node tests/test-upload-plan-gating.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
let pass = 0, fail = 0;
const t = (label, actual, expected) => { const ok = JSON.stringify(actual) === JSON.stringify(expected); if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); } };

const up = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'upload-efb.js'), 'utf8');
const ub = fs.readFileSync(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'upload-builder-efb.js'), 'utf8');
const feat = (pkg, have) => {
  const rank = { free: 0, free_plus: 1, pro: 2 };
  const out = {};
  [['setting:upload_multi', 'free_plus'], ['setting:upload_capture', 'free'], ['setting:upload_resize', 'free_plus'], ['setting:upload_media', 'free_plus'], ['setting:upload_private', 'free'], ['setting:email_attach', 'pro']]
    .forEach(([k, p]) => { out[k] = { package: p, available_now: rank[have] >= rank[p], reason: rank[have] >= rank[p] ? null : 'plan' }; });
  return out;
};
function env(plan) {
  const elements = {};
  const ctx = {
    efb_var: { text: {}, capabilities: { features: feat(null, plan) }, uploads: { max_files_cap: 20, can_media: true } },
    valj_efb: [{ type: 'form' }, { id_: 'f1', dataId: 'f1-id', type: 'file', file: 'allformat', value: 'allformat', name: 'Files' }],
    document: { getElementById: (id) => elements[id] || null, querySelectorAll: () => [] },
    pro_efb: plan !== 'free', console,
  };
  ctx.window = ctx; ctx.globalThis = ctx;
  vm.createContext(ctx);
  vm.runInContext(up, ctx);
  vm.runInContext(ub, ctx);
  return ctx;
}
const parse = (html) => {
  const ctl = (id) => { const m = html.match(new RegExp('<(?:input|select|button)[^>]*id="' + id + '"[^>]*>')); return m ? m[0] : ''; };
  return {
    max: / disabled/.test(ctl('maxFilesEl')), total: / disabled/.test(ctl('maxTotalMbEl')), img: / disabled/.test(ctl('imgMaxPxEl')),
    capture: / disabled/.test(ctl('fileCaptureEl')), store: / disabled/.test(ctl('fileStoreEl')),
    mediaOpt: /<option value="media"[^>]*disabled/.test(html), gems: (html.match(/efb-up-gem/g) || []).length,
  };
};
for (const [plan, want] of [
  ['free', { max: true, total: true, img: true, capture: false, store: false, mediaOpt: true }],
  ['free_plus', { max: false, total: false, img: false, capture: false, store: false, mediaOpt: false }],
  ['pro', { max: false, total: false, img: false, capture: false, store: false, mediaOpt: false }],
]) {
  const c = env(plan);
  const p = parse(c.efbUploadSettingsEls(1, 'f1-id'));
  t(`${plan}: field controls locked / open as the registry says`, { max: p.max, total: p.total, img: p.img, capture: p.capture, store: p.store, mediaOpt: p.mediaOpt }, want);
  t(`${plan}: a gem beside each locked control`, p.gems > 0, plan === 'free');
  const f = c.efbUploadFormEls();
  t(`${plan}: "Attach uploaded files" (Pro)`, [/id="emailAttachEl"[^>]* disabled/.test(f), /efb-up-gem/.test(f)], plan === 'pro' ? [false, false] : [true, true]);
}
// Handlers: the save rules and the preset rules.
const c = env('free_plus');
const input = { tagName: 'INPUT', value: '۷', classList: { contains: () => false }, getAttribute: (k) => ({ 'data-key': 'max_files', 'data-id': 'f1-id' })[k] || null };
c.efb_up_change_efb(input);
t('the handler stores max_files with the save rule (Persian digits)', [c.valj_efb[1].max_files, input.value], ['7', '7']);
c.efb_up_change_efb({ tagName: 'INPUT', value: '9', classList: { contains: () => false }, getAttribute: (k) => ({ 'data-key': 'min_files', 'data-id': 'f1-id' })[k] || null });
t('a minimum over the maximum is lowered to it', c.valj_efb[1].min_files, '7');
c.efb_up_change_efb({ tagName: 'SELECT', value: 'user', classList: { contains: () => false }, getAttribute: (k) => ({ 'data-key': 'capture', 'data-id': 'f1-id' })[k] || null });
t('the camera is not kept for "all formats"', c.valj_efb[1].capture, '');
c.valj_efb[1].file = 'image'; c.valj_efb[1].value = 'image';
c.efb_up_change_efb({ tagName: 'SELECT', value: 'user', classList: { contains: () => false }, getAttribute: (k) => ({ 'data-key': 'capture', 'data-id': 'f1-id' })[k] || null });
t('…and kept for images', c.valj_efb[1].capture, 'user');
c.valj_efb[1].file = 'document'; c.valj_efb[1].value = 'document';
c.efb_up_preset_changed_efb(c.valj_efb[1]);
t('a document preset clears it', c.valj_efb[1].capture, '');
const toggle = { tagName: 'BUTTON', active: false, classList: { contains: (k) => k === 'btn-toggle' || (k === 'active' && toggle.active), toggle: (k, on) => { if (k === 'active') toggle.active = on; } }, setAttribute() {}, getAttribute: (k) => ({ 'data-key': 'email_attach', 'data-id': 'formSet' })[k] || null };
c.efb_up_change_efb(toggle);
t('the form switch writes email_attach on row 0', c.valj_efb[0].email_attach, '1');

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
