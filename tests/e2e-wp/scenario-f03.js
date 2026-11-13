/**
 * F03 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F03-text-validation.fa.md), on the real site:
 *
 *  f03a  A: the owner's messages: a field with msg_minlen shows the owner's sentence under the field
 *        (textContent, aria-invalid, aria-describedby, role=alert) and clears when fixed; a request
 *        tampered after the browser check is refused by the server with the same sentence and the
 *        field id, shown at the field; the required message (customRequiredMsg) comes back from the
 *        server; builder: the "Error messages" group saves msg_minlen through the real sanitizer.
 *  f03b  B: a live counter (what is left, then over the limit in red), announced at the thresholds only
 *        (polite live region), the hidden "Up to N" hint in aria-describedby; word limits ("می‌خواهم بروم"
 *        = 2 words); a textarea with 3 newlines and mlen 10 is accepted at 10 characters by the browser
 *        and the server and stored with its newlines; a plain minimum length now stops Send (§18.2);
 *        fa_IR at 375 px: Persian digits in the counter, no horizontal scroll; builder: the word and
 *        counter controls save through the real sanitizer and the canvas shows the counter.
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

  scenario('f03b', 'F03-B characters, words and the live counter (en and fa_IR at 375 px), textarea newlines, §18.2', async ({ context, adminPage }) => {
    plan3();
    const F = form('F03 e2e counter', [
      { key: 'notes', type: 'textarea', name: 'Notes', mlen: '10', counter: 'chars', counter_style: 'remaining', message: 'Short notes' },
      { key: 'bio', type: 'text', name: 'Bio', wmin: '2', wmax: '4', counter: 'words' },
      { key: 'code', type: 'text', name: 'Code', milen: '5' },
    ]);
    const url = pageOf(F.form_id);
    const g = await (await context({}, 'f03b')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector(byId(F.ids.notes + '_'), { timeout: 20000 });
    await sleep(800);
    const c0 = await g.evaluate((i) => ({ text: document.getElementById(i + '_-counter').textContent, hidden: document.getElementById(i + '_-counter').getAttribute('aria-hidden'), described: document.getElementById(i + '_').getAttribute('aria-describedby'), hint: document.getElementById(i + '_-limit').textContent, max: document.getElementById(i + '_').getAttribute('maxlength') }), F.ids.notes);
    R.check('f03b: counter "10 characters left" (aria-hidden), the hint "Up to 10 characters" in aria-describedby, textarea maxlength', c0.text === '10 characters left' && c0.hidden === 'true' && c0.described.split(' ').includes(F.ids.notes + '_-limit') && c0.hint === 'Up to 10 characters' && c0.max === '10', c0);
    await g.click(byId(F.ids.notes + '_'));
    await g.keyboard.type('ab');
    const live1 = await g.evaluate((fid) => (document.getElementById('efb-live-' + fid) || {}).textContent || '', F.form_id);
    await g.keyboard.press('Enter'); await g.keyboard.type('cd'); await g.keyboard.press('Enter'); await g.keyboard.type('ef'); await g.keyboard.press('Enter');
    await g.keyboard.type('g');
    await sleep(150);
    const live2 = await g.evaluate((fid) => (document.getElementById('efb-live-' + fid) || {}).textContent || '', F.form_id);
    const c1 = await g.evaluate((i) => document.getElementById(i + '_-counter').textContent, F.ids.notes);
    R.check('f03b: no announcement on every key; one when the limit is reached ("0 characters left", polite region)', live1 === '' && live2 === '0 characters left', { live1, live2 });
    R.check('f03b: 3 newlines + 7 letters = 10 characters, "0 characters left"', c1 === '0 characters left', c1);
    await g.press(byId(F.ids.notes + '_'), 'Tab');
    await sleep(300);
    // Words
    await g.fill(byId(F.ids.bio + '_'), 'one');
    await g.press(byId(F.ids.bio + '_'), 'Tab');
    await sleep(400);
    const w1 = await fieldState(g, F.ids.bio);
    R.check('f03b: one word where two are needed → "Bio needs at least 2 words."', w1.text === 'Bio needs at least 2 words.' && w1.invalid === 'true', w1);
    await g.fill(byId(F.ids.bio + '_'), 'می‌خواهم بروم');
    await g.press(byId(F.ids.bio + '_'), 'Tab');
    await sleep(400);
    const w2 = await g.evaluate((i) => ({ s: document.getElementById(i + '_').getAttribute('aria-invalid'), c: document.getElementById(i + '_-counter').textContent }), F.ids.bio);
    R.check('f03b: "می‌خواهم بروم" is 2 words (ZWNJ joins): accepted, counter "2 words left"', w2.s === null && w2.c === '2 words left', w2);
    // §18.2: a plain minimum length stops Send now.
    await g.fill(byId(F.ids.code + '_'), 'abc');
    await g.press(byId(F.ids.code + '_'), 'Tab');
    await sleep(400);
    let posted = false;
    const onReq = (r) => { if (/forms\/message\/add/.test(r.url())) posted = true; };
    g.on('request', onReq);
    await g.click('#btn_send_efb').catch(() => {});
    await sleep(1500);
    const codeMsg = await g.evaluate((i) => (document.getElementById(i + '_-message') || {}).textContent || '', F.ids.code);
    R.check('f03b: §18.2 a 3-letter value under "minimum 5" shows its message and nothing is sent', !posted && /at least 5/.test(codeMsg), { posted, codeMsg });
    g.off('request', onReq);
    await g.fill(byId(F.ids.code + '_'), 'abcdef');
    await g.press(byId(F.ids.code + '_'), 'Tab');
    await sleep(400);
    const ans = await send(g);
    R.check('f03b: the form with the 10-character textarea is accepted by the server', ans && ans.success === true, ans);
    const ent = (ctl('entries', String(F.form_id)) || [])[0];
    const notes = ent && Array.isArray(ent.content) ? (ent.content.find((r) => r.id_ === F.ids.notes) || {}).value : null;
    R.check('f03b: stored with its newlines (@n# tokens)', notes === 'ab@n#cd@n#ef@n#g', notes);

    // fa_IR, 375 px
    ctl('locale', 'fa_IR');
    try {
      const m = await (await context({ viewport: { width: 375, height: 760 } }, 'f03b-fa')).newPage();
      await m.goto(url, { waitUntil: 'load' });
      await m.waitForSelector(byId(F.ids.notes + '_'), { timeout: 20000 });
      await sleep(800);
      await m.click(byId(F.ids.notes + '_'));
      await m.keyboard.type('سلام');
      const fa = await m.evaluate((i) => ({ c: document.getElementById(i + '_-counter').textContent, align: getComputedStyle(document.getElementById(i + '_-counter')).textAlign, dir: getComputedStyle(document.getElementById(i + '_')).direction, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 }), F.ids.notes);
      R.check('f03b: fa_IR at 375 px: Persian digits in the counter, right to left, no horizontal scroll', /[۰-۹]/.test(fa.c) && !/[0-9]/.test(fa.c) && fa.dir === 'rtl' && fa.scroll, fa);
      await m.screenshot({ path: SHOT('f03b-counter-fa-375') });
    } finally { ctl('locale', ''); }

    // Builder: the controls save through the real sanitizer; the canvas shows the counter.
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'textarea');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#wMaxEl', { timeout: 10000 });
    await admin.fill('#wMaxEl', '۵۰'); await admin.dispatchEvent('#wMaxEl', 'change');
    await admin.selectOption('#counterEl', 'words'); await admin.dispatchEvent('#counterEl', 'change');
    await sleep(500);
    const canvas = await admin.evaluate((id) => { const b = document.querySelector('#dropZoneEFB [id="' + id + '_"]'); const host = b && (b.closest('.ttEfb') || b.parentElement); const c = host && host.querySelector('.efb-counter'); return c ? c.textContent : null; }, t.id_);
    R.check('f03b: builder canvas shows the counter ("50 words left")', canvas === '50 words left', canvas);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const tr = (((ctl('form', String(fid)) || {}).form_structer) || []).find((r) => r && r.id_ === t.id_) || {};
    R.check('f03b: Save stores wmax "50" (typed in Persian digits) and counter "words"', tr.wmax === '50' && tr.counter === 'words', { wmax: tr.wmax, counter: tr.counter });
  });
};