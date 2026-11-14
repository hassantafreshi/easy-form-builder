/**
 * F10 builder controls (includes/admin/assets/js/layout-builder-efb.js): every control a panel draws is handled.
 *
 *  - efbLayoutBuilderChange() writes the stored key of each control (review switches and title, "Keep only one
 *    section open", the section's switches, rich text and its toolbar, "Show this field to" and its role boxes,
 *    "How visitors sign" and the signature font) through the save rule (EFBLayoutCore.sanitizeKey);
 *  - every control id the panels draw (id="…El", id="visRoleEl_…") is passed on by change_el_edit_Efb() in
 *    admin-efb.js (its case list, or the visRoleEl_ prefix), so no control is drawn and then ignored;
 *  - a Pro control below the plan writes nothing (visModeEl other than "all").
 *
 * Run: node tests/test-layout-builder-change.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const roleBoxes = [{ dataset: { role: 'administrator' }, checked: false }, { dataset: { role: 'editor' }, checked: true }, { dataset: { role: 'author' }, checked: true }];
function context(planOpen) {
  const ctx = {
    console, CustomEvent: class { constructor(n, o) { this.type = n; this.detail = o && o.detail; } },
    document: {
      getElementById: (id) => (id === 'efbLayoutVisRoles' ? { classList: { toggle() {} }, querySelectorAll: () => roleBoxes } : null),
      dispatchEvent() {}, querySelector: () => null, querySelectorAll: () => [],
    },
    efb_var: { text: {}, pro: 1, roles: [{ slug: 'administrator', name: 'Administrator' }, { slug: 'editor', name: 'Editor' }, { slug: 'author', name: 'Author' }] },
    efb_capability_available_efb: () => planOpen,
  };
  ctx.window = ctx;
  ctx.globalThis = ctx;
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.join(ROOT, 'public/assets/js/layout-efb.js'), 'utf8'), ctx);
  vm.runInContext(fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/layout-builder-efb.js'), 'utf8'), ctx);
  return ctx;
}
const rows = () => [{ type: 'form', steps: 1 }, { id_: 's1', type: 'step', step: '1' },
  { id_: 'sec', type: 'section', step: '1' }, { id_: 'ta', type: 'textarea', step: '1' }, { id_: 'tx', type: 'text', step: '1' }, { id_: 'sg', type: 'esign', step: '1' }];
const run = (ctx, el, indx) => { ctx.valj_efb = ctx.__rows; return ctx.efbLayoutBuilderChange(Object.assign({ classList: { toggle() {} }, dataset: {} }, el), indx); };

const ctx = context(true);
ctx.__rows = rows();
const L = () => ctx.__rows;
vm.runInContext('var valj_efb = __rows;', ctx);
const cases = [
  [{ id: 'reviewBeforeSubmitEl', checked: true }, 0, () => [L()[0].review_before_submit, L()[0].review_hide_empty], ['1', '1']],
  [{ id: 'reviewHideEmptyEl', checked: false }, 0, () => L()[0].review_hide_empty, '0'],
  [{ id: 'reviewTitleEl', value: 'Check <b>it</b>' }, 0, () => L()[0].review_title, 'Check it'],
  [{ id: 'sectionAccordionEl', checked: true }, 0, () => L()[0].section_accordion, '1'],
  [{ id: 'sectionCollapsibleEl', checked: false }, 2, () => [L()[2].collapsible, L()[2].collapsed], ['0', '0']],
  [{ id: 'sectionCollapsedEl', checked: true }, 2, () => L()[2].collapsed, '1'],
  [{ id: 'richTextEl', checked: true }, 3, () => [L()[3].rich_text, L()[3].rich_toolbar], ['1', 'basic']],
  [{ id: 'richToolbarEl', value: 'full' }, 3, () => L()[3].rich_toolbar, 'full'],
  [{ id: 'visModeEl', value: 'roles' }, 4, () => [L()[4].vis_mode, L()[4].vis_roles], ['roles', '']],
  [{ id: 'visRoleEl_1', checked: true }, 4, () => L()[4].vis_roles, 'editor,author'],
  [{ id: 'sigModeEl', value: 'both' }, 5, () => [L()[5].esign_mode, L()[5].esign_font], ['both', 'auto']],
  [{ id: 'sigFontEl', value: 'arabic' }, 5, () => L()[5].esign_font, 'arabic'],
];
cases.forEach(([el, i, get, want]) => { const ok = run(ctx, el, i); t(`${el.id} is handled and writes ${JSON.stringify(want)}`, [ok, get()], [true, want]); });

const locked = context(false);
locked.__rows = rows();
vm.runInContext('var valj_efb = __rows;', locked);
const r = locked.efbLayoutBuilderChange({ id: 'visModeEl', value: 'in', classList: { toggle() {} }, dataset: {} }, 4);
t('visModeEl below the plan writes nothing', [r, locked.__rows[4].vis_mode], [false, undefined]);

// Every control id the panels draw reaches efbLayoutBuilderChange through change_el_edit_Efb().
const builder = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/layout-builder-efb.js'), 'utf8');
const admin = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
const drawn = [...new Set([...builder.matchAll(/(?:id="|check\('|sw\(')([A-Za-z]+El)\b/g)].map((m) => m[1]))].sort();
const block = admin.slice(admin.indexOf('case "reviewBeforeSubmitEl":'), admin.indexOf('efbLayoutBuilderChange(el, indx);', admin.indexOf('case "reviewBeforeSubmitEl":')));
const passed = drawn.filter((id) => block.indexOf(`case "${id}":`) !== -1);
t(`every control the panels draw is passed on by change_el_edit_Efb (${drawn.length} ids)`, passed, drawn);
t('the role boxes (visRoleEl_N) are passed on too', /\/\^visRoleEl_\\d\+\$\/\.test\(el\.id\)\) \{ if \(typeof efbLayoutBuilderChange/.test(admin), true);
const handled = drawn.filter((id) => builder.indexOf(`case '${id}':`) !== -1);
t('and efbLayoutBuilderChange has a case for each of them', handled, drawn);

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
