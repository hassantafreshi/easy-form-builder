/**
 * F03 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F03-text-validation.fa.md), on the real site:
 *
 *  f03a  A: the owner's messages: a field with msg_minlen shows the owner's sentence under the field
 *        (textContent, aria-invalid, aria-describedby, role=alert) and clears when fixed; a request
 *        tampered after the browser check is refused by the server with the same sentence and the
 *        field id, shown at the field; the required message (customRequiredMsg) comes back from the
 *        server; builder: the "Error messages" group saves msg_minlen through the real sanitizer.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const byId = (id) => `[id="${id}"]`;
  const plan3 = () => { if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3); };
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  /** Rewrites one value of the next forms/message/add body (after the browser's checks). */
  async function tamper(page, from, to) {
    const st = { done: false };
    await page.route('**/*', async (route) => {
      const req = route.request();
      if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST' && !st.done) {
        const body = req.postData() || '';
        st.done = body.includes(from);
        return route.continue({ postData: body.split(from).join(to) });
      }
      return route.continue();
    });
    return st;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(900);
    return j && (j.data || j);
  }
  const fieldState = (page, id) => page.evaluate((i) => {
    const el = document.getElementById(i + '_');
    const m = document.getElementById(i + '_-message');
    return { invalid: el.getAttribute('aria-invalid'), described: el.getAttribute('aria-describedby') || '', text: m ? m.textContent : null, role: m ? m.getAttribute('role') : null, shown: m ? getComputedStyle(m).display !== 'none' : false };
  }, id);

  scenario('f03a', 'F03-A field-rule module: the owner\'s messages in the browser and from the server, required message, builder group', async ({ context, adminPage }) => {
    plan3();
    const F = form('F03 e2e messages', [
      { key: 'name', type: 'text', name: 'Name', milen: '5', msg_minlen: 'Five letters at least & no fewer (%s)' },
      { key: 'city', type: 'text', name: 'City', required: 1, customRequiredMsg: 'Tell us your city, please.' },
    ]);
    const url = pageOf(F.form_id);
    const g = await (await context({}, 'f03a')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector(byId(F.ids.name + '_'), { timeout: 20000 });
    await sleep(800);
    R.check('f03a: the page loads the rule engine (EfbRules) and the plan parts', await g.evaluate(() => typeof EfbRules === 'object' && !!(efb_var.field_rules && 'paid' in efb_var.field_rules)), true);
    await g.fill(byId(F.ids.name + '_'), 'Al');
    await g.press(byId(F.ids.name + '_'), 'Tab');
    await sleep(500);
    const s1 = await fieldState(g, F.ids.name);
    R.check('f03a: too short → the owner\'s sentence under the field (text), aria-invalid, aria-describedby, role=alert', s1.text === 'Five letters at least & no fewer (Name)' && s1.invalid === 'true' && s1.described.split(' ').includes(F.ids.name + '_-message') && s1.role === 'alert' && s1.shown, s1);
    await g.fill(byId(F.ids.name + '_'), 'Alice');
    await g.press(byId(F.ids.name + '_'), 'Tab');
    await sleep(500);
    const s2 = await fieldState(g, F.ids.name);
    R.check('f03a: fixed → the error state is gone', s2.invalid === null && !s2.described.includes('-message') && !s2.shown, s2);
    await g.fill(byId(F.ids.city + '_'), 'Shiraz');
    await g.press(byId(F.ids.city + '_'), 'Tab');
    await sleep(400);
    const st = await tamper(g, 'Alice', 'Al');
    const ans = await send(g);
    R.check('f03a: a request changed after the browser check → the server refuses with the owner\'s sentence, the field id and code efb_field_minlen', st.done && ans && ans.success === false && ans.field_id === F.ids.name && ans.code === 'efb_field_minlen' && /Five letters at least &amp;amp; no fewer \(<b>Name<\/b>\)|Five letters at least &amp; no fewer \(<b>Name<\/b>\)/.test(ans.m), { st, ans });
    const s3 = await fieldState(g, F.ids.name);
    R.check('f03a: the server\'s sentence is shown at the field as text', s3.text === 'Five letters at least & no fewer (Name)' && s3.invalid === 'true', s3);
    R.check('f03a: nothing stored for the refused request', (ctl('entries', String(F.form_id)) || []).length === 0);
    await g.unroute('**/*');

    // Required message from the server: a request without the required field (the browser normally stops it).
    const g2 = await (await context({}, 'f03a-req')).newPage();
    await g2.goto(url, { waitUntil: 'load' });
    await g2.waitForSelector(byId(F.ids.city + '_'), { timeout: 20000 });
    await sleep(800);
    await g2.fill(byId(F.ids.name + '_'), 'Alice');
    await g2.press(byId(F.ids.name + '_'), 'Tab');
    await g2.fill(byId(F.ids.city + '_'), 'Shiraz');
    await g2.press(byId(F.ids.city + '_'), 'Tab');
    await sleep(400);
    await g2.route('**/*', async (route) => {
      const req = route.request();
      if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST') {
        const b = JSON.parse(req.postData());
        b.value = JSON.stringify(JSON.parse(b.value).filter((r) => r.id_ !== F.ids.city));
        return route.continue({ postData: JSON.stringify(b) });
      }
      return route.continue();
    });
    const a2 = await send(g2);
    R.check('f03a: the server answers the field\'s own required message (customRequiredMsg)', a2 && a2.success === false && a2.field_id === F.ids.city && a2.m === 'Tell us your city, please.', a2);
    await g2.unroute('**/*');

    // Builder: the "Error messages" group of a text field saves through the real sanitizer.
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'text');
    await admin.evaluate((r) => { const x = valj_efb.find((y) => y.id_ === r.id_); x.milen = '3'; }, t);
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#frMsgsBtnEl', { timeout: 10000 });
    await admin.evaluate(() => { document.getElementById('frMsgsCollapseEl').classList.add('show'); });
    const ph = await admin.getAttribute('#frMsg_minlenEl', 'placeholder');
    R.check('f03a: builder "Error messages": one box per rule, the default sentence as placeholder', !!ph && /needs at least 3 characters\./.test(ph), ph);
    await admin.fill('#frMsg_minlenEl', 'Write more in %s "please" <b>now</b>');
    await admin.dispatchEvent('#frMsg_minlenEl', 'change');
    await sleep(300);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const tr = rows.find((r) => r && r.id_ === t.id_) || {};
    R.check('f03a: Save stores msg_minlen as plain text (quotes and tags dropped, %s kept)', tr.msg_minlen === 'Write more in %s please now', tr.msg_minlen);
  });
};
