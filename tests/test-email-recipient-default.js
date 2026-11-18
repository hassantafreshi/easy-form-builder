/**
 * 4.3.2 (owner request): an E-mail field's "SendemailEl" switch (field noti, form email_to and sendEmail) is ON by
 * default: the visitor gets the confirmation e-mail at the address typed in that field, unless the owner turns it off.
 *
 * In the real builder scripts (Chromium, the AutoPilot harness):
 *  - add: the first e-mail field of a form becomes the recipient (noti 1, email_to, sendEmail true), its switch drawn
 *    active with aria-pressed; a second e-mail field stays off (email_to holds one field);
 *  - toggle: off is saved as noti 0 (email_to emptied, sendEmail false); on again works also while mail delivery is not
 *    confirmed (efb_var.smtp 0: the switch used to be refused; now a non-blocking hint);
 *  - delete: deleting the recipient hands email_to to another switched-on e-mail field, or empties it (no silent on);
 *  - old forms: drawing a saved form without noti, and adding a non-e-mail field to it, change nothing;
 *  - efbBuilderApi.newItems('email') gets the default (the quick forms: e2e email-default); newItems on a form that has a recipient
 *    gives no noti and no email_to in its formPatch;
 *  - parity: efbEmailRecipientDefault() (JS) and Emsfb\Builder_Api::email_recipient_default() (PHP) on the same vectors;
 *    the server template field_templates('email') carries noti 1 and form_patch email_to / sendEmail with the same id,
 *    and recipient => false leaves them out.
 *
 * Run: node tests/test-email-recipient-default.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();

const VECTORS = [
  { name: 'no e-mail field', rows: [{ type: 'form', email_to: false }, { type: 'text', id_: 't1' }] },
  { name: 'one e-mail field, no noti', rows: [{ type: 'form', email_to: false }, { type: 'email', id_: 'e1' }] },
  { name: 'two e-mail fields: the first', rows: [{ type: 'form', email_to: '' }, { type: 'email', id_: 'e1' }, { type: 'email', id_: 'e2' }] },
  { name: 'the first switched off: the second', rows: [{ type: 'form' }, { type: 'email', id_: 'e1', noti: 0 }, { type: 'email', id_: 'e2' }] },
  { name: 'every e-mail switched off: none', rows: [{ type: 'form' }, { type: 'email', id_: 'e1', noti: '0' }] },
  { name: 'a recipient already: unchanged', rows: [{ type: 'form', email_to: 'e2', sendEmail: true }, { type: 'email', id_: 'e1' }, { type: 'email', id_: 'e2', noti: 1 }] },
  { name: 'a field already on, email_to empty: that field', rows: [{ type: 'form', email_to: '' }, { type: 'email', id_: 'e1' }, { type: 'email', id_: 'e2', noti: 1 }] },
  { name: 'email_to names no field (stale): the first', rows: [{ type: 'form', email_to: 'gone' }, { type: 'email', id_: 'e1' }] },
  { name: 'a template names its recipient without noti: the switch follows', rows: [{ type: 'form', email_to: 'e2' }, { type: 'email', id_: 'e1' }, { type: 'email', id_: 'e2' }] },
  { name: 'the named recipient was switched off: kept off', rows: [{ type: 'form', email_to: 'e1' }, { type: 'email', id_: 'e1', noti: 0 }] },
];

(async () => {
  // Mail delivery not confirmed on this site (efb_var.smtp 0; efb_var is frozen, so set at load): the default still applies.
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm(), extraVar: { smtp: 0 } });
  const { page } = h;
  try {
    // The canvas' button zone outside the drop zone (the harness draws none): fun_handle_buttons_efb() reads it.
    await page.evaluate(() => ['f_btn_send_efb', 'f_button_form_np'].forEach((id) => { if (!document.getElementById(id)) { const d = document.createElement('div'); d.id = id; d.className = 'd-block'; document.body.appendChild(d); } }));
    // Add.
    // The palette's own click handler (fun_efb_add_el): the canvas and the settings window as for a person.
    const addOne = () => page.evaluate(() => {
      const before = new Set(valj_efb.map((r) => r.id_));
      fun_efb_add_el('email');
      return valj_efb.find((x) => x.type === 'email' && !before.has(x.id_)).id_;
    });
    const E1 = await addOne();
    await page.waitForTimeout(300);
    const a1 = await page.evaluate((e1) => { const r1 = valj_efb.find((r) => r.id_ === e1); return { noti: r1.noti, to: valj_efb[0].email_to === e1, send: valj_efb[0].sendEmail }; }, E1);
    const E2 = await addOne();
    await page.waitForTimeout(300);
    const add = { a: a1, second: await page.evaluate((ids) => { const r2 = valj_efb.find((r) => r.id_ === ids[1]); return { noti: r2.noti, to: valj_efb[0].email_to === ids[0] }; }, [E1, E2]) };
    c.check('add: the first e-mail field is the recipient (noti 1, email_to, sendEmail true)', add.a.noti === 1 && add.a.to === true && add.a.send === true, add.a);
    c.check('add: a second e-mail field stays off (email_to keeps the first)', add.second.noti === undefined && add.second.to === true, add.second);
    await page.evaluate((e1) => show_setting_window_efb(e1 + '-id'), E1);
    await page.waitForSelector('#SendemailEl', { timeout: 8000 });
    const sw = await page.evaluate(() => { const b = document.getElementById('SendemailEl'); return { active: b.classList.contains('active'), pressed: b.getAttribute('aria-pressed') }; });
    c.check('add: its switch is drawn on (active, aria-pressed true)', sw.active && sw.pressed === 'true', sw);

    // Toggle off, then on with mail delivery not confirmed.
    await page.evaluate(() => { window.__hint = 0; efb_builder_maybe_warn_email_delivery = function () { window.__hint++; }; });
    // Bootstrap's data-toggle="button" flips "active" before the handler runs (not loaded in this harness).
    const flip = () => page.evaluate(() => { const b = document.getElementById('SendemailEl'); b.classList.toggle('active'); fun_switch_form_efb(b); });
    await flip();
    await page.waitForTimeout(400);
    const off = await page.evaluate((e1) => ({ noti: valj_efb.find((r) => r.id_ === e1).noti, to: valj_efb[0].email_to, send: valj_efb[0].sendEmail }), E1);
    c.check('toggle off: noti 0 (saved as off), email_to emptied, sendEmail false', off.noti === 0 && off.to === '' && off.send === false, off);
    await flip();
    await page.waitForTimeout(400);
    const on = await page.evaluate((e1) => ({ noti: valj_efb.find((r) => r.id_ === e1).noti, to: valj_efb[0].email_to === e1, send: valj_efb[0].sendEmail, active: document.getElementById('SendemailEl').classList.contains('active'), hint: window.__hint }), E1);
    c.check('toggle on while delivery is not confirmed: applied (not refused), the hint shown once, not blocking', on.noti === 1 && on.to === true && on.send === true && on.active && on.hint === 1, on);

    // The second switched on, then the recipient deleted: email_to moves to it; deleting it too empties it.
    const del = await page.evaluate((ids) => {
      valj_efb.find((r) => r.id_ === ids[1]).noti = 1;
      obj_delete_row(ids[0] + '-id', false);
      const a = { to: valj_efb[0].email_to === ids[1], send: Number(valj_efb[0].sendEmail) };
      obj_delete_row(ids[1] + '-id', false);
      return { a, b: { to: valj_efb[0].email_to, send: Number(valj_efb[0].sendEmail) } };
    }, [E1, E2]);
    c.check('delete the recipient: email_to goes to the other switched-on e-mail field', del.a.to === true && del.a.send === 1, del.a);
    c.check('delete the last: email_to empty, sendEmail off (nothing switched on silently)', del.b.to === '' && del.b.send === 0, del.b);

    // An old saved form: drawn and edited without a new e-mail field, it keeps its choice.
    const old = await page.evaluate((f) => {
      valj_efb = f; sessionStorage.setItem('valj_efb', JSON.stringify(f));
      return editFormEfb().then(() => { addNewElement('text', 'txt9zzzzz', false, false); const r = valj_efb.find((x) => x.id_ === 'oldmail01'); return { noti: r.noti, has: 'noti' in r, to: valj_efb[0].email_to, send: valj_efb[0].sendEmail }; });
    }, blankForm({ email_to: false, sendEmail: false }).concat([{ id_: 'oldmail01', dataId: 'oldmail01-id', type: 'email', name: 'Mail', step: 1, amount: 2, required: 0, value: '', placeholder: '', size: 100 }]));
    c.check('an old form drawn and edited (a text field added): its e-mail field stays off, row 0 unchanged', !old.has && old.to === false && old.send === false, old);

    // newItems (AutoPilot) on a form with and without a recipient.
    const ni = await page.evaluate(() => {
      valj_efb = [valj_efb[0], valj_efb[1]]; valj_efb[0].email_to = ''; valj_efb[0].sendEmail = false;
      const a = efbBuilderApi.newItems('email');
      valj_efb.push(Object.assign({}, a[0])); valj_efb[0].email_to = a[0].id_; valj_efb[0].sendEmail = true;
      const b = efbBuilderApi.newItems('email');
      return { a: { noti: a[0].noti, fp: a.formPatch || null, id: a[0].id_ }, b: { noti: b[0].noti, fp: b.formPatch || null } };
    });
    c.check('newItems("email") on a form without a recipient: noti 1, formPatch email_to = its id, sendEmail true', ni.a.noti === 1 && ni.a.fp && ni.a.fp.email_to === ni.a.id && ni.a.fp.sendEmail === true, ni.a);
    c.check('newItems("email") on a form with a recipient: no noti, no email_to / sendEmail in formPatch', ni.b.noti === undefined && !(ni.b.fp && ('email_to' in ni.b.fp || 'sendEmail' in ni.b.fp)), ni.b);

    // Parity JS / PHP on the vectors.
    const js = await page.evaluate((V) => V.map((v) => { const rows = JSON.parse(JSON.stringify(v.rows)); efbEmailRecipientDefault(rows); return rows; }), VECTORS);
    const php = JSON.parse(execFileSync('php', ['-r', `
      define('ABSPATH', '/');
      require '${ROOT}/includes/class-Emsfb-builder-api.php';
      $V = json_decode(stream_get_contents(STDIN), true);
      echo json_encode(array_map(function ($v) { return \\Emsfb\\Builder_Api::email_recipient_default($v['rows']); }, $V));
    `], { input: JSON.stringify(VECTORS) }).toString());
    VECTORS.forEach((v, i) => c.check(`parity (${v.name}): JS and PHP give the same rows`, JSON.stringify(js[i]) === JSON.stringify(php[i]), { js: js[i], php: php[i] }));
    c.check('vectors: the first / the second / none / unchanged / the one on / the stale one / the named one', [js[1][0].email_to, js[2][0].email_to, js[3][0].email_to, js[4][0].email_to, js[5][0].email_to, js[6][0].email_to, js[7][0].email_to, js[8][0].email_to].join() === ['e1', 'e1', 'e2', undefined, 'e2', 'e2', 'e1', 'e2'].join() && js[2][2].noti === undefined && js[8][2].noti === 1 && js[8][1].noti === undefined && js[9][1].noti === 0, js.map((r) => r[0].email_to));

    // The server template.
    const tpl = JSON.parse(execFileSync('php', ['-r', `
      define('ABSPATH', '/'); function apply_filters($h, $v) { return $v; }
      require '${ROOT}/includes/class-Emsfb-builder-api.php';
      $a = \\Emsfb\\Builder_Api::field_templates('email', array('text' => array(), 'rtl' => false));
      $b = \\Emsfb\\Builder_Api::field_templates('email', array('text' => array(), 'rtl' => false, 'recipient' => false));
      echo json_encode(array('a' => array($a['rows'][0]['id_'], $a['rows'][0]['noti'] ?? null, $a['form_patch']), 'b' => array($b['rows'][0]['noti'] ?? null, $b['form_patch'])));
    `]).toString());
    c.check('server template field_templates("email"): noti 1, form_patch email_to = the row\'s new id, sendEmail true', tpl.a[1] === 1 && tpl.a[2] && tpl.a[2].email_to === tpl.a[0] && tpl.a[2].sendEmail === true, tpl.a);
    c.check('… with recipient => false: no noti, no form patch', tpl.b[0] === null && tpl.b[1] === null, tpl.b);
    c.check('no page error', h.errors.length === 0, h.errors.slice(0, 3));
  } finally {
    await h.browser.close();
  }
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
