/**
 * QA1-012 (FX-ADMIN): Versions › "Compare with current" reports only what changed, in words.
 *  - EfbProductivity.diffForms(stored, builder): a stored version (strings, as the server keeps them) and the builder's
 *    state (1 / true / false / numbers) that differ only in types are the same; a nested set of texts (thank_you_message)
 *    in another key order, or with a default the builder adds, is the same; bookkeeping (EfbVersion, amount) is never
 *    a change; a real change (a label) is still found, and only it;
 *  - keyLabels(): the changed keys in words (Label, Required, …); a key without a name is counted, never printed.
 * The real rows are the ones QA-1 dumped (form saved, one label changed, compared): before the fix 6 fields and 12 form
 * settings were reported. The dialog itself: e2e f15c.
 *
 * Run: node tests/test-revisions-compare.js
 */
'use strict';
const P = require('../includes/admin/assets/js/productivity-efb.js');
let pass = 0, fail = 0;
const t = (name, ok, detail) => { if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (detail !== undefined ? ' — ' + JSON.stringify(detail) : '')); } };

const stored = [
  { type: 'form', formName: 'Contact Us Form', steps: '1', EfbVersion: '2', sendEmail: '1', trackingCode: '1', show_icon: '1', show_pro_bar: '1', captcha: '', stateForm: '', dShowBg: '1', step: '1', visible: '1',
    thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thanks for filling out the form.', done: 'You&#039;re all done', trackingCode: 'Confirmation Code', pleaseFillInRequiredFields: 'Please fill in all required fields.' } },
  { id_: 'a1', type: 'text', name: 'First name', required: '1', step: '1', pro: '', size: '50' },
  { id_: 'a2', type: 'email', name: 'Email', required: '1', step: '1', pro: '', noti: '1' },
  { id_: 'a3', type: 'select', name: 'Topic', required: '0', step: '1', pro: '' },
  { id_: 'o1', type: 'option', parent: 'a3', value: 'Sales', id_op: 'o1' },
];
const builder = [
  { type: 'form', formName: 'Contact Us Form', steps: 1, EfbVersion: 2, sendEmail: true, trackingCode: true, show_icon: true, show_pro_bar: true, captcha: false, stateForm: false, dShowBg: true, step: 1, visible: 1,
    thank_you_message: { thankYou: 'Thanks for filling out the form.', done: 'You&#039;re all done', trackingCode: 'Confirmation Code', error: 'Error', pleaseFillInRequiredFields: 'Please fill in all required fields.', icon: 'bi-hand-thumbs-up' } },
  { id_: 'a1', type: 'text', name: 'First name', required: true, step: 1, pro: false, size: 50, amount: 3 },
  { id_: 'a2', type: 'email', name: 'Email', required: true, step: 1, pro: false, noti: 1 },
  { id_: 'a3', type: 'select', name: 'Topic', required: false, step: 1, pro: false },
  { id_: 'o1', type: 'option', parent: 'a3', value: 'Sales', id_op: 'o1' },
];
let d = P.diffForms(stored, builder);
t('types only (1 / true / "1", false / "" , numbers / strings), key order, a default text: no change', d.changed.length === 0 && d.settings.length === 0 && !d.onlyOld.length && !d.onlyNow.length, d);

const b2 = JSON.parse(JSON.stringify(builder));
b2[1].name = 'Your name';
d = P.diffForms(stored, b2);
t('one label changed: "Changed (1): Your name · name", no form setting', d.changed.length === 1 && d.changed[0].name === 'Your name' && JSON.stringify(d.changed[0].keys) === '["name"]' && d.settings.length === 0, d);

const b3 = JSON.parse(JSON.stringify(builder));
b3[2].required = false; b3[0].captcha = true; b3[0].thank_you_message.thankYou = 'Merci';
d = P.diffForms(stored, b3);
t('real changes are still found: Required off, CAPTCHA on, the thank-you text', d.changed.length === 1 && d.changed[0].keys.join() === 'required' && d.settings.sort().join() === 'captcha,thank_you_message', d);

const b4 = JSON.parse(JSON.stringify(builder));
b4[0].EfbVersion = 3; b4[1].amount = 9;
t('bookkeeping (EfbVersion, amount) is never a change', (() => { const x = P.diffForms(stored, b4); return x.changed.length === 0 && x.settings.length === 0; })());

const b5 = JSON.parse(JSON.stringify(builder));
b5[4].value = 'Support';
t('an option\'s text is a change of the field\'s Options', (() => { const x = P.diffForms(stored, b5); return x.changed.length === 1 && x.changed[0].keys.join() === 'options'; })());

const words = P.keyLabels(['name', 'required', 'zzInternal', 'show_pro_bar'], (k, en) => (k === 'label' ? 'برچسب' : en));
t('keyLabels: known keys in words (through the phrase), unknown keys counted, never printed', JSON.stringify(words) === JSON.stringify({ named: ['برچسب', 'Required'], other: 2 }), words);
t('keyLabels: name / required / thank_you_message / captcha have words', JSON.stringify(P.keyLabels(['thank_you_message', 'captcha']).named) === '["Thank you message","reCAPTCHA"]');

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
