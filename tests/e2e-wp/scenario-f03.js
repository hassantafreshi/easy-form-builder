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
 *  f03c  C: a safe owner pattern: the builder lints as the owner types (ReDoS refused with the reason),
 *        the test box marks each line, a pattern with < " %41 and double spaces is stored unchanged
 *        (base64url) through the real save; AutoPilot setFieldRules() throws pattern_invalid, and a
 *        save carrying a bad pattern_b64 is refused with its path; a guest: Persian digits accepted,
 *        a wrong value shows msg_pattern, a request changed after the browser check is refused
 *        (efb_field_pattern) and nothing is stored.
 *  f03d  D: unique values: builder switch (Free Plus) and Save, the save hook hashes the older answers;
 *        a guest with a used address gets "This value has already been used." at the field after Send
 *        (role=alert, aria-invalid) and nothing is stored; a paid form (test gateway): a pending payment
 *        holds the address, a failed one gives it back, another visitor pays with it, the first
 *        visitor's "Try again" is refused with the sentence; fa_IR at 375 px.
 *  f03e  E: e-mail domains: the builder keeps the list as the server does (Unicode → punycode, broken
 *        items counted), Save; a guest outside the allowed domains sees the sentence at the field, a
 *        request changed after the browser check is refused (efb_field_domain); a paid form whose
 *        payer e-mail field has a domain rule: an allowed address pays (the gateway gets it as the
 *        payer e-mail), a blocked one is refused before any payment starts.
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

  scenario('f03c', 'F03-C safe patterns: builder lint and test box, exact save, AutoPilot errors, guest and server', async ({ context, adminPage }) => {
    plan3();
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'text');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#patternEl', { timeout: 10000 });
    await admin.fill('#patternEl', '(a+)+');
    await sleep(500);
    const lint = await admin.evaluate(() => ({ msg: document.getElementById('patternLintMsg').textContent, inv: document.getElementById('patternEl').getAttribute('aria-invalid') }));
    const rowBad = await admin.evaluate((id) => valj_efb.find((r) => r.id_ === id).pattern_b64 || '', t.id_);
    R.check('f03c: builder refuses (a+)+ as you type with the reason, nothing stored', /This pattern cannot be used: a repeated group repeats something inside it again/.test(lint.msg) && lint.inv === 'true' && rowBad === '', { lint, rowBad });
    const src = 'x<y "q" %41  ?[A-Z]{2}\\d{3}'; // a value never keeps two spaces (the text sanitizers), so the second one is optional
    await admin.fill('#patternEl', src);
    await sleep(500);
    await admin.fill('#patternTestEl', 'x<y "q" %41 AB123\nx<y "q" %41 ab123');
    await sleep(300);
    const test = await admin.evaluate(() => Array.from(document.querySelectorAll('#patternTestOut li')).map((li) => li.className.includes('text-success')));
    R.check('f03c: the test box marks each line (accepted, refused)', JSON.stringify(test) === '[true,false]', test);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const tr = (((ctl('form', String(fid)) || {}).form_structer) || []).find((r) => r && r.id_ === t.id_) || {};
    const back = tr.pattern_b64 ? Buffer.from(tr.pattern_b64.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8') : null;
    R.check('f03c: Save keeps the pattern with < " %41 and double spaces exactly (base64url)', back === src, { stored: tr.pattern_b64, back });
    // AutoPilot
    const api = await admin.evaluate((id) => {
      const out = {};
      try { efbBuilderApi.setFieldRules(id, { pattern: '(\\w+\\s?)*' }); out.a = 'no throw'; } catch (e) { out.a = [e.code, e.reason]; }
      try { efbBuilderApi.setFieldRules(id, { email_domains: 'x.com' }); out.b = 'no throw'; } catch (e) { out.b = e.code; }
      out.c = efbBuilderApi.setFieldRules(id, { pattern: 'EMP\\d{4}', msg_pattern: 'Like EMP1234' }).values;
      try { efbBuilderApi.insertItems([{ id_: 'zzbadpat1', type: 'text', name: 'Z', pattern_b64: 'KGErKSs' }]); out.d = 'no throw'; } catch (e) { out.d = [e.code, e.path]; }
      out.cap = efb_var.capabilities.field_rules ? efb_var.capabilities.field_rules.gated_keys : null;
      return out;
    }, t.id_);
    R.check('f03c: AutoPilot setFieldRules: pattern_invalid (nested_quantifier), unsupported_key for a domain list on a text field, a good pattern stored as pattern_b64; insertItems refuses a bad pattern_b64 with its path; capabilities.field_rules.gated_keys',
      JSON.stringify(api.a) === '["pattern_invalid","nested_quantifier"]' && api.b === 'unsupported_key' && api.c.pattern_b64 === 'RU1QXGR7NH0' && api.c.msg_pattern === 'Like EMP1234' && JSON.stringify(api.d) === '["pattern_invalid","rows[0].pattern_b64"]' && JSON.stringify(api.cap) === '["unique","pattern_b64","email_domain_mode","pw_strength_min"]', api);
    const refused = await admin.evaluate(async (fid) => {
      const rows = JSON.parse(JSON.stringify(valj_efb));
      const f = rows.find((r, i) => i > 1 && r.type === 'text');
      f.pattern_b64 = 'KGErKSs'; // "(a+)+"
      const body = new URLSearchParams({ action: 'update_form_Emsfb', value: JSON.stringify(rows), name: 'x', nonce: _efb_nonce_, id: String(fid) });
      const r = await fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' });
      return (await r.json()).data;
    }, fid);
    R.check('f03c: a save carrying a pattern the lint refuses → code pattern_invalid with its path', refused && refused.success === false && refused.code === 'pattern_invalid' && /^rows\[\d+\]\.pattern_b64$/.test(refused.path), refused);

    // Guest
    const F = form('F03 e2e pattern', [{ key: 'code', type: 'text', name: 'Staff code', pattern_b64: 'RU1QXGR7NH0', msg_pattern: 'Use EMP and 4 digits, like EMP1234.' }]);
    const g = await (await context({}, 'f03c')).newPage();
    await g.goto(pageOf(F.form_id), { waitUntil: 'load' });
    await g.waitForSelector(byId(F.ids.code + '_'), { timeout: 20000 });
    await sleep(800);
    await g.fill(byId(F.ids.code + '_'), 'emp1234');
    await g.press(byId(F.ids.code + '_'), 'Tab');
    await sleep(400);
    const e1 = await fieldState(g, F.ids.code);
    R.check('f03c: guest: a wrong value shows msg_pattern at the field', e1.text === 'Use EMP and 4 digits, like EMP1234.' && e1.invalid === 'true', e1);
    await g.fill(byId(F.ids.code + '_'), 'EMP۱۲۳۴');
    await g.press(byId(F.ids.code + '_'), 'Tab');
    await sleep(400);
    const e2 = await fieldState(g, F.ids.code);
    R.check('f03c: guest: Persian digits count as 0-9', e2.invalid === null, e2);
    const st = await tamper(g, 'EMP۱۲۳۴', 'EMPX234');
    const ans = await send(g);
    R.check('f03c: a request changed after the browser check → efb_field_pattern with the owner\'s sentence, nothing stored', st.done && ans && ans.success === false && ans.code === 'efb_field_pattern' && ans.m === 'Use EMP and 4 digits, like EMP1234.' && (ctl('entries', String(F.form_id)) || []).length === 0, { st, ans });
  });

  scenario('f03d', 'F03-D unique values: builder, older answers, guest refusal at the field, payments hold / give back / retry refused, fa_IR at 375 px', async ({ context, adminPage }) => {
    plan3();
    ctl('f11-mu');
    ctl('set-option', 'efb_e2e_unique_fail_limit', 'del');
    // Builder: the switch, Save, the save hook does the older answers.
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'email');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#uniqueEl', { timeout: 10000 });
    await admin.click('#uniqueEl');
    await sleep(300);
    const sw = await admin.evaluate((id) => ({ pressed: document.getElementById('uniqueEl').getAttribute('aria-pressed'), v: valj_efb.find((r) => r.id_ === id).unique }), t.id_);
    R.check('f03d: builder: the unique switch (aria-pressed) writes unique "1"', sw.pressed === 'true' && sw.v === '1', sw);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(1500);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const tr = (((ctl('form', String(fid)) || {}).form_structer) || []).find((r) => r && r.id_ === t.id_) || {};
    R.check('f03d: Save stores unique "1"', tr.unique === '1', tr.unique);

    // Guest refusal at the field (server answer after Send).
    const F = form('F03 e2e unique', [{ key: 'email', type: 'email', name: 'Email', required: 1, unique: '1' }, { key: 'name', type: 'text', name: 'Name' }]);
    ctl('f03-uniq', String(F.form_id), 'reset');
    const url = pageOf(F.form_id);
    const sendAs = async (label, email, opts) => {
      const p = await (await context(opts || {}, label)).newPage();
      await p.goto(url, { waitUntil: 'load' });
      await p.waitForSelector(byId(F.ids.email + '_'), { timeout: 20000 });
      await sleep(800);
      await p.fill(byId(F.ids.email + '_'), email);
      await p.press(byId(F.ids.email + '_'), 'Tab');
      await sleep(300);
      return { p, ans: await send(p) };
    };
    const first = await sendAs('f03d-1', 'dup@example.test');
    R.check('f03d: the first visitor is saved', first.ans && first.ans.success === true, first.ans);
    const second = await sendAs('f03d-2', 'DUP@Example.test');
    await sleep(400);
    const st2 = await fieldState(second.p, F.ids.email);
    R.check('f03d: the same address in other case: "This value has already been used." at the field (role=alert, aria-invalid), efb_field_unique, one entry', second.ans && second.ans.code === 'efb_field_unique' && st2.text === 'This value has already been used.' && st2.role === 'alert' && st2.invalid === 'true' && (ctl('entries', String(F.form_id)) || []).length === 1, { ans: second.ans, st2 });
    ctl('locale', 'fa_IR');
    try {
      const fa = await sendAs('f03d-fa', 'dup@example.test', { viewport: { width: 375, height: 760 } });
      await sleep(400);
      const v = await fa.p.evaluate((i) => ({ dir: getComputedStyle(document.getElementById(i + '_-message')).direction, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 }), F.ids.email);
      R.check('f03d: fa_IR at 375 px: the refusal at the field, right to left, no horizontal scroll', fa.ans && fa.ans.code === 'efb_field_unique' && v.dir === 'rtl' && v.scroll, v);
      await fa.p.evaluate(() => { const b = document.getElementById('btn_prev_efb') || document.querySelector('[onclick*="efb_go_to_step_direct"]'); if (b) b.click(); });
      await sleep(600);
      await fa.p.screenshot({ path: SHOT('f03d-unique-fa-375') });
    } finally { ctl('locale', ''); }

    // Payments (test gateway): hold, give back, retry refused.
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'fixed', 'F03 e2e paid unique');
    ctl('f03-field', String(P.form_id), P.email, 'unique=1');
    const purl = ctl('page', String(P.form_id)).url;
    const startPay = async (label, email) => {
      const p = await (await context({}, label)).newPage();
      await p.goto(purl, { waitUntil: 'networkidle' });
      await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await p.fill(`[data-id="${P.email}-el"]`, email);
      await p.click(`[id="${P.o1}"]`);
      await sleep(400);
      const create = p.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 20000 }).catch(() => null);
      await p.click('.efb-pf [data-pf-pay]');
      const cr = await create;
      let cj = null; try { cj = cr ? await cr.json() : null; } catch (e) { cj = null; }
      return { p, cj: cj && (cj.data || cj) };
    };
    const A = await startPay('f03d-pay-a', 'payer@example.test');
    await A.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
    const held = ctl('f03-uniq', String(P.form_id)).rows;
    R.check('f03d/pay: visitor A is paying: the pending entry holds the address', held.length === 1 && Number(held[0].msg_id) > 0, held);
    const B = await startPay('f03d-pay-b', 'Payer@example.test');
    R.check('f03d/pay: visitor B with the same address is refused before any payment starts (efb_field_unique)', B.cj && B.cj.success === false && B.cj.code === 'efb_field_unique' && ctl('payments', String(P.form_id)).length === 1, B.cj);
    await Promise.all([A.p.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), A.p.click('#fail')]);
    await sleep(1500);
    R.check('f03d/pay: A\'s payment failed: the address is given back', ctl('f03-uniq', String(P.form_id)).rows.length === 0);
    const C = await startPay('f03d-pay-c', 'payer@example.test');
    await C.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
    R.check('f03d/pay: visitor C now pays with the address', ctl('f03-uniq', String(P.form_id)).rows.length === 1);
    await A.p.waitForSelector('.efb-pf-retry', { timeout: 15000 });
    const retry = A.p.waitForResponse((x) => /payments\/retry/.test(x.url()), { timeout: 20000 }).catch(() => null);
    await A.p.click('.efb-pf-retry');
    const rr = await retry;
    let rj = null; try { rj = rr ? await rr.json() : null; } catch (e) { rj = null; }
    const rd = rj && (rj.data || rj);
    await sleep(800);
    const said = await A.p.evaluate(() => (document.querySelector('.efb-pf [data-pf-status]') || {}).textContent || '');
    R.check('f03d/pay: A\'s "Try again" is refused with the sentence (no new attempt)', rd && rd.success === false && rd.code === 'efb_field_unique' && /already been used/.test(said) && ctl('payments', String(P.form_id)).length === 2, { rd, said });
  });

  scenario('f03e', 'F03-E e-mail domains: builder list, guest and server, the payer e-mail of a paid form', async ({ context, adminPage }) => {
    plan3();
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'email');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#emailDomainsEl', { timeout: 10000 });
    await admin.selectOption('#emailDomainModeEl', 'allow'); await admin.dispatchEvent('#emailDomainModeEl', 'change');
    await admin.fill('#emailDomainsEl', 'Example.com; *.EDU bücher.de bad%41.com');
    await admin.dispatchEvent('#emailDomainsEl', 'change');
    await sleep(300);
    const b = await admin.evaluate(() => ({ v: document.getElementById('emailDomainsEl').value, n: document.getElementById('emailDomainsCountEl').textContent }));
    R.check('f03e: builder keeps the list as the server does (punycode) and counts what it did not understand', b.v === 'example.com, *.edu, xn--bcher-kva.de' && b.n === '3 accepted, 1 not understood', b);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const tr = (((ctl('form', String(fid)) || {}).form_structer) || []).find((r) => r && r.id_ === t.id_) || {};
    R.check('f03e: Save stores mode allow and the list', tr.email_domain_mode === 'allow' && tr.email_domains === 'example.com,*.edu,xn--bcher-kva.de', { m: tr.email_domain_mode, d: tr.email_domains });

    const F = form('F03 e2e domains', [{ key: 'email', type: 'email', name: 'Work email', required: 1, email_domain_mode: 'allow', email_domains: 'example.com,*.edu' }]);
    const g = await (await context({}, 'f03e')).newPage();
    await g.goto(pageOf(F.form_id), { waitUntil: 'load' });
    await g.waitForSelector(byId(F.ids.email + '_'), { timeout: 20000 });
    await sleep(800);
    await g.fill(byId(F.ids.email + '_'), 'me@gmail.com');
    await g.press(byId(F.ids.email + '_'), 'Tab');
    await sleep(400);
    const s1 = await fieldState(g, F.ids.email);
    R.check('f03e: guest: another domain → "Please use an email address from an allowed domain." at the field (the list is not shown)', s1.text === 'Please use an email address from an allowed domain.' && s1.invalid === 'true', s1);
    await g.fill(byId(F.ids.email + '_'), 'me@uni.edu');
    await g.press(byId(F.ids.email + '_'), 'Tab');
    await sleep(400);
    const st = await tamper(g, 'me@uni.edu', 'me@gmail.com');
    const ans = await send(g);
    R.check('f03e: a request changed after the browser check → efb_field_domain, nothing stored', st.done && ans && ans.code === 'efb_field_domain' && (ctl('entries', String(F.form_id)) || []).length === 0, ans);

    // The payer e-mail of a paid form.
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'fixed', 'F03 e2e paid domains');
    ctl('f03-field', String(P.form_id), P.email, 'email_domain_mode=allow&email_domains=example.test');
    const purl = ctl('page', String(P.form_id)).url;
    const payWith = async (label, email) => {
      const p = await (await context({}, label)).newPage();
      await p.goto(purl, { waitUntil: 'networkidle' });
      await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await p.fill(`[data-id="${P.email}-el"]`, email);
      await p.click(`[id="${P.o1}"]`);
      await sleep(400);
      const create = p.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 8000 }).catch(() => null);
      await p.click('.efb-pf [data-pf-pay]');
      const cr = await create;
      let cj = null; try { cj = cr ? await cr.json() : null; } catch (e) { cj = null; }
      return { p, cj: cj && (cj.data || cj) };
    };
    const ok = await payWith('f03e-pay-ok', 'payer@example.test');
    await ok.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }).catch(() => {});
    const pays = ctl('payments', String(P.form_id));
    R.check('f03e/pay: an allowed payer address starts the payment and reaches the gateway as the payer e-mail', pays.length === 1 && pays[0].payer_email === 'payer@example.test', pays.map((x) => ({ s: x.status, e: x.payer_email })));
    const bad = await payWith('f03e-pay-bad', 'payer@gmail.com');
    const badState = await fieldState(bad.p, P.email);
    R.check('f03e/pay: a blocked payer address: no payment request (the browser stops it at the field)', !bad.cj && ctl('payments', String(P.form_id)).length === 1 && /allowed domain/.test(badState.text || ''), { cj: bad.cj, badState });
  });
};