/**
 * A form whose row 0 and steps lack the keys the builder draws with (button texts and icons, colours, sizes, classes,
 * the thank-you texts) is drawn by the builder and its panels open (BUGS-FOUND, AI builder core): editFormEfb() fills
 * them in its copy of the form with a new form's values. This test runs the shipped code (no copies):
 * efbFillBuilderDefaultsEfb() from new-efb.js, the new form's row 0 (efb_new_form_row0_efb(), val-efb.js) and step
 * (efb_new_step_row_efb(), admin-efb.js), and checks the call sites:
 *  - the defaults are the ones a new form gets (the creator and the first step use the same functions, unchanged rows);
 *  - only missing keys are filled (absent or null); settings, ids, order, names and fields are never touched;
 *  - editFormEfb() fills after normalizing and before drawing, and stores nothing (the owner's Save does).
 *
 * Run: node tests/test-builder-open-defaults.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 1200) : '')); }
};
const done = () => { console.log(`\nRESULTS: ${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0); };

/** The source of a top-level function (or `let name = ... => {`) by brace matching. */
function fnSource(src, head) {
  const at = src.indexOf(head);
  if (at === -1) return null;
  let i = src.indexOf('{', at + head.length - 1);
  let depth = 0;
  for (let j = i; j < src.length; j++) {
    if (src[j] === '{') depth++;
    else if (src[j] === '}' && --depth === 0) return src.slice(at, j + 1);
  }
  return null;
}

const newEfb = read('includes/admin/assets/js/new-efb.js');
const adminEfb = read('includes/admin/assets/js/admin-efb.js');
const valEfb = read('includes/admin/assets/js/val-efb.js');
const block = (newEfb.match(/\/\* efbBuilderDefaults:start \*\/[\s\S]*?\/\* efbBuilderDefaults:end \*\//) || [null])[0];
const row0Fn = fnSource(valEfb, 'function efb_new_form_row0_efb(');
const stepFn = fnSource(adminEfb, 'function efb_new_step_row_efb(');
const nameFn = fnSource(adminEfb, 'function efb_first_step_name_efb(');
const editFn = fnSource(adminEfb, 'let editFormEfb =');
const creatorFn = fnSource(valEfb, 'function creator_form_builder_Efb(');
const addFn = fnSource(adminEfb, 'function addNewElement(');
check('the shipped code has the helper block, the new form\'s row 0 and step, editFormEfb(), the creator and addNewElement()', !!(block && row0Fn && stepFn && nameFn && editFn && creatorFn && addFn));
if (!(block && row0Fn && stepFn && nameFn && editFn && creatorFn && addFn)) done();

// The builder's globals the defaults read: the colour defaults as new-efb.js declares them, and the texts.
const ctx = {
  efb_var: { rtl: 0, text: { form: 'Form', submit: 'Submit', next: 'Next', previous: 'Previous', thanksFillingOutform: 'Thanks', yad: 'Done', trackingCode: 'Confirmation Code', error: 'Error', pleaseFillInRequiredFields: 'Fill' } },
  form_type_emsFormBuilder: 'form', formName_Efb: 'form',
};
for (const m of newEfb.matchAll(/^let (pub_\w+_efb) *= *['"]([^'"]*)['"]/gm)) ctx[m[1]] = m[2];
check('the colour defaults come from new-efb.js', ['pub_bg_button_color_efb', 'pub_label_text_color_efb', 'pub_txt_button_color_efb', 'pub_message_text_color_efb', 'pub_icon_color_efb'].every((k) => typeof ctx[k] === 'string' && ctx[k] !== ''), ctx);
vm.createContext(ctx);
vm.runInContext(block + '\n' + row0Fn + '\n' + nameFn + '\n' + stepFn + '\nthis.API = { EFB_ROW0_BUILDER_KEYS_EFB, EFB_STEP_BUILDER_KEYS_EFB, efbFillBuilderDefaultsEfb, efb_new_form_row0_efb, efb_new_step_row_efb };', ctx);
const { EFB_ROW0_BUILDER_KEYS_EFB: K0, EFB_STEP_BUILDER_KEYS_EFB: KS, efbFillBuilderDefaultsEfb: fill, efb_new_form_row0_efb: newRow0, efb_new_step_row_efb: newStep } = ctx.API;
const plain = (o) => JSON.parse(JSON.stringify(o));

/* 1. The defaults are a new form's, as the builder wrote them before they became functions. */
const R0 = plain(newRow0());
const ST = plain(newStep(1, 1));
check('a new form\'s row 0 has the keys it always had, in the same order (what a new form stores)', Object.keys(R0).join(',') === 'type,steps,formName,email,trackingCode,EfbVersion,button_single_text,button_color,icon,button_Next_text,button_Previous_text,button_Next_icon,button_Previous_icon,button_state,label_text_color,el_text_color,message_text_color,icon_color,el_height,email_to,show_icon,show_pro_bar,captcha,private,sendEmail,font,stateForm,dShowBg,btns_align,thank_you,thank_you_message,email_temp', Object.keys(R0));
check('… and its values (texts of the site language, the colour defaults, chevrons, h-d-efb, centred buttons, tracking code on)', R0.button_single_text === 'Submit' && R0.button_Next_text === 'Next' && R0.button_Previous_text === 'Previous' && R0.icon === 'bi-ui-checks-grid' && R0.button_Next_icon === 'bi-chevron-right' && R0.button_Previous_icon === 'bi-chevron-left' && R0.button_state === 'single' && R0.button_color === ctx.pub_bg_button_color_efb && R0.label_text_color === ctx.pub_label_text_color_efb && R0.el_text_color === ctx.pub_txt_button_color_efb && R0.icon_color === ctx.pub_txt_button_color_efb && R0.message_text_color === ctx.pub_message_text_color_efb && R0.el_height === 'h-d-efb' && R0.btns_align === 'justify-content-center' && R0.trackingCode === true && R0.font === true && R0.email_temp === '' && R0.thank_you_message.thankYou === 'Thanks', R0);
check('a new form\'s first step has the keys it always had, in the same order, with its icon, sizes and colours', Object.keys(ST).join(',') === 'id_,type,dataId,classes,id,name,icon,step,amount,EfbVersion,message,label_text_size,el_text_size,label_text_color,el_text_color,message_text_color,icon_color,visible' && ST.icon === 'bi-ui-checks-grid' && ST.classes === '' && ST.name === 'FORM' && ST.label_text_size === 'fs-5' && ST.el_text_size === 'fs-5' && ST.label_text_color === 'text-darkb' && ST.el_text_color === 'text-labelEfb' && ST.message_text_color === ctx.pub_message_text_color_efb && ST.icon_color === ctx.pub_icon_color_efb, ST);
check('the step function takes the step and the order it is given', JSON.stringify(plain(newStep(1, 7))).includes('"step":1,"amount":7') && newStep(1, 7).id_ === '1' && newStep(1, 7).dataId === '1');
check('the filled keys are keys of a new form\'s row 0 and step (nothing invented)', K0.length > 0 && KS.length > 0 && K0.every((k) => k in R0) && KS.every((k) => k in ST), { K0, KS });
check('no setting, id, order or name is filled (row 0: the button texts, icons and colours and the thank-you texts)', K0.includes('thank_you_message') && K0.includes('button_Next_icon') && !['type', 'steps', 'formName', 'email', 'trackingCode', 'email_to', 'show_icon', 'show_pro_bar', 'captcha', 'private', 'sendEmail', 'font', 'stateForm', 'dShowBg', 'thank_you', 'email_temp'].some((k) => K0.includes(k)) && !['id_', 'type', 'dataId', 'id', 'name', 'step', 'amount', 'visible', 'EfbVersion'].some((k) => KS.includes(k)));

/* 2. A form made elsewhere: row 0 and step 1 without these keys. */
const field = { id_: 'tx1', dataId: 'tx1-id', type: 'text', name: 'Full name', step: 1, amount: 2, label_text_size: 'fs-6', el_height: 'h-d-efb', classes: '' };
const option = { id_: 'o1', dataId: 'o1-id', parent: 'r1', type: 'option', value: 'A', step: 1, amount: 3 };
const bare = () => [
  { type: 'form', steps: 2, formName: 'Imported', email: '', trackingCode: '1', EfbVersion: 2, stateForm: 0, thank_you: 'msg' },
  { id_: '1', type: 'step', dataId: '1', id: '1', name: 'Contact', step: 1, amount: 1 },
  plain(field), plain(option),
  { id_: '2', type: 'step', dataId: '2', id: '2', step: 2, amount: 4 },
];
const f = bare();
const ret = fill(f, newRow0(), newStep(1, 1));
check('efbFillBuilderDefaultsEfb() returns the rows it was given (in place)', ret === f);
check('row 0 gets every key with a new form\'s value (thank-you texts included)', K0.every((k) => JSON.stringify(f[0][k]) === JSON.stringify(R0[k])), f[0]);
check('every step (1 and 2) gets every key with a new form\'s step value', [f[1], f[4]].every((s) => KS.every((k) => s[k] === ST[k])), [f[1], f[4]]);
check('no setting is filled on row 0 (the keys it had stay as stored; no tracking code, CAPTCHA, e-mail, progress bar or icon switch added)', Object.keys(R0).filter((k) => !K0.includes(k)).every((k) => JSON.stringify(f[0][k]) === JSON.stringify(bare()[0][k])) && f[0].trackingCode === '1' && f[0].steps === 2 && f[0].formName === 'Imported', f[0]);
check('a step keeps its id, order and name; a step without a name gets none', f[1].name === 'Contact' && f[1].id_ === '1' && f[1].amount === 1 && !('name' in f[4]) && !('visible' in f[4]) && f[4].id_ === '2' && f[4].amount === 4, [f[1], f[4]]);
check('fields and options are not touched', JSON.stringify(f[2]) === JSON.stringify(field) && JSON.stringify(f[3]) === JSON.stringify(option));
const g = bare();
fill(g, newRow0(), newStep(1, 1));
g[0].thank_you_message.thankYou = 'Changed in the builder';
check('each form gets its own thank-you texts object (a change in one is not seen by another fill)', f[0].thank_you_message.thankYou === R0.thank_you_message.thankYou && newRow0().thank_you_message.thankYou === R0.thank_you_message.thankYou);
const again = JSON.stringify(f);
fill(f, newRow0(), newStep(1, 1));
check('a second fill changes nothing (editFormEfb() runs again on every redraw)', JSON.stringify(f) === again);

/* 3. What the form has is kept: its own look, an empty icon, a custom alignment, its own thank-you texts; null is missing. */
const own = [
  { type: 'payment', steps: 1, button_color: 'btn-success', icon: '', btns_align: 'justify-content-end', button_single_text: 'Pay now', el_height: null, button_Next_icon: null, thank_you_message: { done: 'Paid' } },
  { id_: '1', type: 'step', dataId: '1', step: 1, amount: 1, name: 'S', classes: 'my-step', icon: 'bi-star', label_text_color: 'text-danger', message: null },
];
fill(own, newRow0(), newStep(1, 1));
check('keys the form has are kept (button colour, empty icon, alignment, button text, its own thank-you texts, a step\'s classes, icon and colour)', JSON.stringify(own[0].thank_you_message) === '{"done":"Paid"}' && own[0].button_color === 'btn-success' && own[0].icon === '' && own[0].btns_align === 'justify-content-end' && own[0].button_single_text === 'Pay now' && own[1].classes === 'my-step' && own[1].icon === 'bi-star' && own[1].label_text_color === 'text-danger', own);
check('a null key counts as missing', own[0].el_height === 'h-d-efb' && own[0].button_Next_icon === 'bi-chevron-right' && own[1].message === '', own);

/* 4. Anything else is left alone. */
let junkOk = true;
try {
  junkOk = fill(null) === null && fill([]).length === 0 && fill([null]).length === 1 && fill(['x'])[0] === 'x';
  const noDef = [{ type: 'form' }, { type: 'step', id_: '1' }];
  fill(noDef);
  junkOk = junkOk && Object.keys(noDef[0]).length === 1 && Object.keys(noDef[1]).length === 2;
  fill([{ type: 'form' }, null, 5, { type: 'step' }], newRow0(), newStep(1, 1));
} catch (e) { junkOk = false; }
check('junk is tolerated (no rows, no row 0, rows that are not objects, no defaults given)', junkOk);

/* 5. Call sites. */
check('the creator writes a new form\'s row 0 with efb_new_form_row0_efb()', /valj_efb\.push\(efb_new_form_row0_efb\(\)\);/.test(creatorFn) && !/button_Next_icon/.test(creatorFn));
check('the first step of a new form is efb_new_step_row_efb(step_el_efb, amount_el_efb)', /valj_efb\.push\(efb_new_step_row_efb\(step_el_efb, amount_el_efb\)\);/.test(addFn));
const iNorm = editFn.indexOf('efbNormalizeOrderEfb(valj_efb)');
const iFill = editFn.indexOf('efbFillBuilderDefaultsEfb(valj_efb, efb_new_form_row0_efb(), efb_new_step_row_efb(1, 1))');
const iDraw = editFn.indexOf('addNewElement(');
check('editFormEfb() fills with a new form\'s row 0 and step after normalizing and before drawing', iNorm > 0 && iFill > iNorm && iDraw > iFill, { iNorm, iFill, iDraw });
check('editFormEfb() stores nothing (no session, local storage or request): the stored form changes on the owner\'s Save', !/(sessionStorage|localStorage)\.setItem|admin-ajax|fetch\(|jQuery\.ajax|\$\.ajax/.test(editFn));

done();
