/**
 * Conditional Logic add-on (builder): core's upgrade dialog and the plan-change event (F18 §11).
 *
 * vendores-efb logic/logic/assets/admin/js/conditional-logic-efb.js
 *  - opened its own upsell with pro_show_efb(3) for a Free plan. Core now has
 *    efb_upgrade_prompt_efb({feature, origin, resume}) (F18): the add-on calls it with the registry
 *    key setting:logic_rules and a resume callback that opens the builder once Free Plus is on;
 *    pro_show_efb(3) stays the fallback for older cores, the add-on's own locked panel the last one.
 *  - read the plan only when the panel opened, so a panel open while the plan changed in place
 *    (core's efb_on_plan_changed_efb() -> document event efb:plan-changed) kept the old locks.
 *    The add-on listens to the event and redraws an open panel; a closed panel, or a shell another
 *    dialog took over, is left alone.
 *
 * Runs the real production file via a minimal document/window stub (as
 * tests/test-conditional-logic-plan-gating.js does, which must stay green too).
 * Add-on source: EFB_LOGIC_ADMIN_JS (a file), vendor/logic (git-ignored symlink) or /home/user/vendores-efb/logic.
 *
 * Run: node tests/test-addon-logic-upgrade-prompt.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const file = [process.env.EFB_LOGIC_ADMIN_JS || '', path.join(__dirname, '../vendor/logic/logic/assets/admin/js/conditional-logic-efb.js'), '/home/user/vendores-efb/logic/logic/assets/admin/js/conditional-logic-efb.js'].find(f => f && fs.existsSync(f));
if (!file) { console.log('[SKIP] the Conditional Logic add-on source was not found'); process.exit(0); }

// ── DOM stub: the shared modal shell, with classList and a querySelector on the body ──
function escapeForHtml(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
const els = {};
function makeEl() {
  const cls = new Set();
  return {
    innerHTML: '', textContent: '', className: '',
    classList: { add: (...c) => c.forEach(x => cls.add(x)), remove: (...c) => c.forEach(x => cls.delete(x)), contains: (c) => cls.has(c) },
    querySelector(sel) { const h = this.innerHTML; return sel.split(',').some(s => h.includes(s.trim().replace(/^\./, ''))) ? {} : null; },
  };
}
const listeners = {};
global.document = {
  createElement() { return { _t: '', appendChild(n) { this._t += n && n.nodeValue !== undefined ? escapeForHtml(n.nodeValue) : ''; }, get innerHTML() { return this._t; } }; },
  createTextNode(s) { return { nodeValue: String(s) }; },
  getElementById(id) { return els[id] || (els[id] = makeEl()); },
  addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
};
global.window = global;
global.state_modal_show_efb = (i) => { const m = document.getElementById('settingModalEfb'); i ? m.classList.add('show') : m.classList.remove('show'); };
global.alert_message_efb = () => {};
global.efb_var = { text: {}, rtl: 0, addons: { AdnSMF: 1 }, pro: 0, setting: { package_type: 0 } };
global.valj_efb = [{ id_: 'form', type: 'form' }, { id_: 'firstName', type: 'text', name: 'First Name' }, { id_: 'lastName', type: 'text', name: 'Last Name' }];

// Core F18 API spies (a current core); removed below to play an older core.
let prompts = [], proShows = [];
const resumeFns = {};
global.efb_upgrade_prompt_efb = (opts) => { prompts.push(opts); return { shown: true, state: 'S1' }; };
global.efb_resume_register_efb = (name, fn) => { resumeFns[name] = fn; };
global.pro_show_efb = (s) => { proShows.push(s); };

require(file);

let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };
const body = () => document.getElementById('settingModalEfb-body').innerHTML;
const shown = () => document.getElementById('settingModalEfb').classList.contains('show');
const plan = (pro, pkg) => { efb_var.pro = pro; efb_var.setting = { package_type: pkg }; };
const planChanged = (pkg) => (listeners['efb:plan-changed'] || []).forEach(fn => fn({ type: 'efb:plan-changed', detail: { package_type: pkg } }));

// ── 1. Free plan, current core: core's dialog with the registry key and a resume ──
plan(0, 0);
EFB_Logic.open();
check('free: open() asks core\'s efb_upgrade_prompt_efb()', prompts.length === 1, prompts);
check('free: with feature setting:logic_rules and origin logic', prompts[0] && prompts[0].feature === 'setting:logic_rules' && prompts[0].origin === 'logic', prompts[0]);
check('free: not the old pro_show_efb(3)', proShows.length === 0, proShows);
check('free: the builder is not drawn behind the dialog', !body().includes('efb-logic-tabs'));
check('free: the resume opens the builder once Free Plus is on', prompts[0] && prompts[0].resume && prompts[0].resume.kind === 'callback' && typeof resumeFns[prompts[0].resume.name] === 'function', prompts[0] && prompts[0].resume);
plan(1, 3);
if (prompts[0] && prompts[0].resume && resumeFns[prompts[0].resume.name]) resumeFns[prompts[0].resume.name]();
check('resume after activation: the builder is open', shown() && body().includes('efb-logic-tabs'), body().slice(0, 120));
state_modal_show_efb(0); // core closes the shell (close button, backdrop)

// ── 2. Older cores ──
const prompt = global.efb_upgrade_prompt_efb;
delete global.efb_upgrade_prompt_efb;
plan(0, 0); prompts = []; proShows = [];
EFB_Logic.open();
check('older core: falls back to pro_show_efb(3)', proShows.length === 1 && proShows[0] === 3, proShows);
const proShow = global.pro_show_efb;
delete global.pro_show_efb;
EFB_Logic.open();
check('oldest core: the add-on\'s own locked panel', shown() && body().includes('efb-logic-pro-panel'), body().slice(0, 120));

// ── 3. efb:plan-changed redraws an open panel ──
check('a listener for efb:plan-changed is registered', (listeners['efb:plan-changed'] || []).length === 1, Object.keys(listeners));
// The locked Free panel is open; Free Plus is turned on in place.
plan(1, 3); planChanged(3);
check('free locked panel + Free Plus: the builder is drawn', body().includes('efb-logic-tabs') && !body().includes('efb-logic-pro-panel'), body().slice(0, 160));
// Free Plus at the field-rule limit: the Add button is locked.
const storedRule = (id) => ({ id, name: id, scope: 'field', enabled: true, priority: 10,
  conditions: { type: 'group', operator: 'AND', items: [{ type: 'condition', source: 'field', field_id: 'firstName', compare: 'is_not_empty', value: '' }] },
  actions: [{ type: 'show_field', target: 'lastName' }] });
valj_efb[0].logic_rules = [storedRule('r1'), storedRule('r2'), storedRule('r3')];
EFB_Logic.switchTab('field');
check('free plus at 3 field rules: Add is locked', EFB_Logic.getRules().length === 3 && body().includes('efb-logic-btn-locked'), EFB_Logic.getRules().length);
EFB_Logic.switchTab('webhook');
check('free plus: the webhook tab is the Pro panel', body().includes('efb-logic-pro-panel'));
plan(1, 1); planChanged(1);
check('upgrade to Pro while open: the webhook tab opens in place', !body().includes('efb-logic-pro-panel') && body().includes('efb-logic-tabs'), body().slice(0, 160));
check('upgrade to Pro: no gem left on the tabs', !body().includes('efb-logic-tab-gem'));
EFB_Logic.switchTab('field');
check('upgrade to Pro: Add is no longer locked at 3 rules', EFB_Logic.getRules().length === 3 && !body().includes('efb-logic-btn-locked'), EFB_Logic.getRules().length);
plan(1, 3); planChanged(3);
check('back to Free Plus while open: the limit lock is drawn again', body().includes('efb-logic-btn-locked'));
plan(0, 0); planChanged(0);
check('down to Free while open: the locked Free panel replaces the builder', body().includes('efb-logic-pro-panel') && !body().includes('efb-logic-tabs'), body().slice(0, 160));

// The free locked panel's Upgrade button, with a current core: the panel closes, core's dialog opens.
global.efb_upgrade_prompt_efb = prompt; prompts = [];
EFB_Logic.openProUpgrade();
check('free locked panel, Upgrade: core\'s dialog for setting:logic_rules', prompts.length === 1 && prompts[0].feature === 'setting:logic_rules', prompts);
check('free locked panel, Upgrade: the panel made room for it', !shown());

// ── 4. Nothing is redrawn when the panel is not on screen ──
plan(1, 3);
EFB_Logic.open();
state_modal_show_efb(0); // core closes the shell (close button, backdrop)
document.getElementById('settingModalEfb-body').innerHTML = '<p>other</p>';
plan(1, 1); planChanged(1);
check('closed panel: the event draws nothing', body() === '<p>other</p>', body().slice(0, 80));
EFB_Logic.open();
document.getElementById('settingModalEfb-body').innerHTML = '<div class="efb efb-upg">dialog</div>'; // another dialog took the shell
planChanged(1);
check('a shell another dialog took over is not overwritten', body() === '<div class="efb efb-upg">dialog</div>', body().slice(0, 80));
global.pro_show_efb = proShow;

console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
