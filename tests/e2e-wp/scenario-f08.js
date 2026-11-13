/**
 * F08 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F08-hidden-smart-tags.fa.md), on the real site.
 *
 *  f08a  Builder: the Hidden field tile, its panel ("Fill with" UTM source, the prefill key), "Insert a smart
 *        tag" next to a label (the tag as a badge on the canvas), Save through the real sanitizer (st_uses,
 *        templates, keys), AutoPilot (setHiddenField, an unknown tag refused, capabilities.smart_tags),
 *        forms/{id}/schema (hidden = assistant none and no template, the prefill parameters listed).
 *  f08b  A guest (en_US) on /page/?utm_source=google&email=…&plan=pro&q=<img …>: the hidden values in the page
 *        (Conditional Logic sees them), prefilled fields through their own checks, a parameter without a key
 *        changes nothing, the label piece follows the answer as text (no element from <img onerror>), Send:
 *        the stored hidden values are the server's (a tampered request changes nothing), the thank-you piece
 *        comes from the server; the response viewer shows the hidden row with its badge, escaped.
 *  f08c  fa_IR and ar at 375 px: right to left, a Persian / Arabic answer piped into an English label and an
 *        English one into a Persian label keep their order (<bdi>), Persian / Arabic-Indic digits prefill a
 *        number, the label aligns like the other labels, no horizontal scroll; screenshots.
 *  f08d  Messages and redirects: the redirect address keeps its host ({get:next} cannot move it) and encodes
 *        the answers; the e-mail subject shows the choice's label and stays on one line.
 *  f08e  Cache: the page HTML a signed-in owner gets has no e-mail, name or login of that user; the same cached
 *        HTML served to a guest shows the fallbacks; the owner's page gets the name from forms/session/prepare;
 *        the guest's hidden {user:email} is stored empty.
 *  f08pay A paid form (test gateway): a URL parameter cannot pick a priced choice (no prefill key is kept on it)
 *        and a hidden "Price" of 999 changes nothing: the server charges the price of the chosen option.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const setup = () => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('f11-mu');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
  };
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const rowOf = (entry, id) => ((entry && entry.content) || []).find((r) => r && r.id_ === id) || null;
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => window.EFBSmart && typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(900);
    return g;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(1200);
    return j && (j.data || j);
  }
  const sendRows = (page) => page.evaluate(() => sendBack_emsFormBuilder_pub.map((r) => ({ id_: r.id_, type: r.type, value: r.value })));

  /* ------------------------------------------------------------------ f08a */
  scenario('f08a', 'F08 builder: the hidden field, its panel, the insert list, Save, AutoPilot, the schema', async ({ adminPage }) => {
    setup();
    const admin = await adminPage();
    await openNewForm(admin);
    const h = await addTile(admin, 'hiddenField');
    R.check('f08a: the Hidden field tile adds a hidden field (no placeholder, never required)', h && h.type === 'hiddenField' && h.value === '' && h.required === undefined, h);
    await admin.click(`#dropZoneEFB [data-id="${h.dataId}"] .efb-hdn-bar`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#hdnPresetEl', { timeout: 10000 });
    await admin.selectOption('#hdnPresetEl', 'utm_source');
    await admin.fill('#prefillKeyEl', 'campaign'); await admin.press('#prefillKeyEl', 'Tab');
    await admin.click('#hdnVisitorMailEl');
    await sleep(400);
    await admin.screenshot({ path: SHOT('f08a-hidden-panel-en'), fullPage: false });
    let r = await admin.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === i))), h.id_);
    R.check('f08a: the panel writes the template, the prefill key and the visitor copy', r.value === '{get:utm_source}' && r.prefill_key === 'campaign' && r.hdn_visitor_mail === '1', r);
    const bar = await admin.evaluate((i) => (document.getElementById(i + '_tpl') || {}).textContent, h.id_);
    R.check('f08a: the canvas bar shows the template', bar === '{get:utm_source}', bar);
    await closePanel(admin);
    const t = await addTile(admin, 'text');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#labelElStIns', { timeout: 10000 });
    await admin.fill('#labelEl', 'Hello ');
    await admin.evaluate(() => { const e = document.getElementById('labelEl'); e.setSelectionRange(6, 6); });
    await admin.selectOption('#labelElStIns', '{user:first_name}');
    await sleep(900);
    r = await admin.evaluate((i) => valj_efb.find((x) => x.id_ === i).name, t.id_);
    const badge = await admin.evaluate((i) => [...document.querySelectorAll(`[id="${i}_lab"] .efb-st-badge`)].map((b) => b.textContent), t.id_);
    R.check('f08a: "Insert a smart tag" puts {user:first_name} at the caret; the canvas shows it as a badge', r === 'Hello {user:first_name}' && badge[0] === '{user:first_name}', [r, badge]);
    const opts = await admin.evaluate(() => [...document.querySelectorAll('#labelElStIns option')].map((o) => o.value));
    R.check('f08a: the list next to a label has no ip, cookie or user:email', !opts.includes('{ip}') && !opts.includes('{user:email}') && !opts.some((x) => /cookie/.test(x)), opts);
    await admin.fill('#prefillKeyEl', 'email'); await admin.press('#prefillKeyEl', 'Tab');
    await closePanel(admin);
    // AutoPilot.
    const ap = await admin.evaluate((hid) => {
      const out = {};
      try { efbBuilderApi.insertItems([{ id_: 'apbad', type: 'hiddenField', name: 'Bad', value: '{nosuch:x}' }]); out.bad = 'inserted'; } catch (e) { out.bad = { code: e.code, path: e.path }; }
      out.set = efbBuilderApi.setHiddenField(hid, { preset: 'utm_campaign' });
      out.back = efbBuilderApi.setHiddenField(hid, { preset: 'utm_source' });
      const cap = efb_var.capabilities || {};
      out.st = cap.smart_tags ? { field_type: cap.smart_tags.field_type, builder: cap.smart_tags.builder, prefill: cap.smart_tags.prefill_types.includes('select'), pii: cap.smart_tags.risk_flags } : null;
      out.feat = cap.features ? ['setting:hidden_field', 'setting:smart_tags', 'setting:url_prefill', 'setting:answer_piping'].map((k) => cap.features[k] ? cap.features[k].package : null) : null;
      return out;
    }, h.id_);
    R.check('f08a: AutoPilot insertItems refuses an unknown tag (smart_invalid with its path)', ap.bad && ap.bad.code === 'smart_invalid' && /value$/.test(ap.bad.path), ap.bad);
    R.check('f08a: AutoPilot setHiddenField with a preset', ap.set && ap.set.value === '{get:utm_campaign}' && ap.back.value === '{get:utm_source}', ap);
    R.check('f08a: capabilities.smart_tags and the plan of each part (piping Free Plus)', ap.st && ap.st.field_type === 'hiddenField' && ap.st.prefill && JSON.stringify(ap.feat) === JSON.stringify(['free', 'free', 'free', 'free_plus']), ap);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const hs = rows.find((x) => x && x.id_ === h.id_) || {};
    const ts = rows.find((x) => x && x.id_ === t.id_) || {};
    R.check('f08a: Save through the real sanitizer keeps the template, keys and the label tag; st_uses computed', hs.value === '{get:utm_source}' && hs.prefill_key === 'campaign' && hs.hdn_visitor_mail === '1' && ts.name === 'Hello {user:first_name}' && ts.prefill_key === 'email' && /get/.test(rows[0].st_uses || '') && /user/.test(rows[0].st_uses || '') && /prefill/.test(rows[0].st_uses || ''), [hs, ts.name, rows[0].st_uses]);
    const g = await admin.context().newPage();
    await g.goto(pageOf(fid), { waitUntil: 'load' });
    const sc = await g.evaluate(async (f) => (await (await fetch('/wp-json/Emsfb/v1/forms/' + f + '/schema')).json()), fid);
    const he = (sc.fields || []).find((x) => x.id === h.id_) || {};
    R.check('f08a: forms/{id}/schema: the hidden field is the server\'s (assistant none, no template), its prefill key listed', he.assistant === 'none' && he.label === undefined && JSON.stringify(sc).indexOf('utm_source') === -1 && sc.prefill && sc.prefill.params.some((p) => p.key === 'campaign' && p.field === h.id_) && sc.prefill.params.some((p) => p.key === 'email'), [he, sc.prefill]);
  });

  /* ------------------------------------------------------------------ f08b */
  scenario('f08b', 'F08 guest (en_US): hidden values, prefill, answer piping as text, Send (the server\'s values), viewer', async ({ context, adminPage }) => {
    setup();
    const F = form('F08 e2e guest', [
      { key: 'name', type: 'text', name: 'Your name', prefill_key: 'who' },
      { key: 'why', type: 'text', name: 'Why, {field:@@name@@|friend}?' },
      { key: 'email', type: 'email', name: 'Email', prefill_key: 'email' },
      { key: 'plan', type: 'select', name: 'Plan', prefill_key: 'plan' },
      { key: 'p1', type: 'option', parent_key: 'plan', value: 'Pro plan', opt_value: 'pro' },
      { key: 'p2', type: 'option', parent_key: 'plan', value: 'Basic' },
      { key: 'free', type: 'text', name: 'Free text' },
      { key: 'utm', type: 'hiddenField', name: 'UTM source', value: '{get:utm_source|direct}' },
      { key: 'fixed', type: 'hiddenField', name: 'Campaign', value: 'spring-2026' },
      { key: 'day', type: 'hiddenField', name: 'Day', value: '{date:Y}' },
    ], 'thank_you_message[thankYou]=Thanks%20%7Bfield%3A@@name@@%7Cfriend%7D%2C%20code%20%7Bentry%3Atrack%7D&thank_you_message[done]=Done');
    // The thank-you text through the save: set it with its real key (the helper's row-0 overrides are flat).
    const url = pageOf(F.form_id);
    const g = await open(context, url + '?utm_source=google&email=ana%40example.test&plan=PRO&free=nope&who=Ana&iefb=x&defb=0', 'f08b');
    const s1 = await sendRows(g);
    const byId = (rows, id) => rows.find((r) => r.id_ === id) || {};
    R.check('f08b: the hidden values are in the page\'s answers (Conditional Logic reads them)', byId(s1, F.ids.utm).value === 'google' && byId(s1, F.ids.fixed).value === 'spring-2026', s1);
    const pf = await g.evaluate((ids) => ({ name: document.getElementById(ids.name + '_').value, email: document.getElementById(ids.email + '_').value, plan: document.getElementById(ids.plan + '_options').value, free: document.getElementById(ids.free + '_').value }), F.ids);
    R.check('f08b: prefill: only fields with a key (opt_value "PRO" picks "Pro plan"); a parameter without a key changes nothing', pf.name === 'Ana' && pf.email === 'ana@example.test' && pf.plan === 'Pro plan' && pf.free === '', pf);
    const lab = () => g.evaluate((i) => document.getElementById(i + '_lab').textContent, F.ids.why);
    R.check('f08b: the label piece shows the prefilled answer', await lab() === 'Why, Ana?', await lab());
    await g.fill(`[id="${F.ids.name}_"]`, '<img src=x onerror=window.pwned=1>'); await g.press(`[id="${F.ids.name}_"]`, 'Tab');
    await sleep(500);
    const x = await g.evaluate((i) => ({ text: document.getElementById(i + '_lab').textContent, imgs: document.querySelectorAll('[id="' + i + '_lab"] img').length, pwned: !!window.pwned }), F.ids.why);
    R.check('f08b: an answer with markup is piped as text (no element, no script)', x.imgs === 0 && !x.pwned && /Why, /.test(x.text), x);
    await g.fill(`[id="${F.ids.name}_"]`, 'Sara'); await g.press(`[id="${F.ids.name}_"]`, 'Tab');
    await sleep(500);
    R.check('f08b: the piece follows the next answer', await lab() === 'Why, Sara?', await lab());
    await g.fill(`[id="${F.ids.why}_"]`, 'Because');
    await g.press(`[id="${F.ids.why}_"]`, 'Tab');
    await g.screenshot({ path: SHOT('f08b-guest-en'), fullPage: true });
    // A request tampered after the page: every hidden value changed.
    await g.route('**/wp-json/Emsfb/v1/forms/message/add', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'hiddenField' ? Object.assign(r, { value: 'tampered' }) : r)));
      return route.continue({ postData: JSON.stringify(b) });
    });
    const res = await send(g);
    R.check('f08b: Send', res && res.success === true && res.track, res);
    const e = lastEntry(F.form_id);
    const st = { utm: (rowOf(e, F.ids.utm) || {}).value, fixed: (rowOf(e, F.ids.fixed) || {}).value, day: (rowOf(e, F.ids.day) || {}).value };
    R.check('f08b: the stored hidden values are the server\'s, not the tampered ones', st.utm === 'google' && st.fixed === 'spring-2026' && /^20\d\d$/.test(st.day || ''), st);
    const done = await g.evaluate(() => (document.querySelector('#doneMessageEfb') || {}).textContent || '');
    R.check('f08b: the thank-you piece comes from the server (the stored name, the confirmation code)', /Thanks Sara, code /.test(done) && done.indexOf(res.track) !== -1, done);
    // Viewer.
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function', null, { timeout: 20000 });
    const view = await admin.evaluate((rs) => {
      const out = fun_emsFormBuilder_show_messages(rs.concat([{ id_: 'h9', name: 'Ref', type: 'hiddenField', value: '<img src=x onerror=window.pwned2=1>', amount: 9 }, { id_: 'h8', name: 'Page', type: 'hiddenField', value: 'https://site.test/p?a=1&b="x"', amount: 10 }]), 'guest', '', 'TRK', '2026-01-01');
      const box = document.createElement('div'); box.innerHTML = typeof out === 'string' ? out : ''; document.body.appendChild(box);
      const rows = [...box.querySelectorAll('[data-type="hiddenField"]')];
      const page = rows.find((r) => /^Page:/.test(r.textContent.trim())) || rows[0];
      return { n: rows.length, badges: box.querySelectorAll('.efb-msg-hdn-badge').length, imgs: box.querySelectorAll('[data-type="hiddenField"] img').length, link: (page.querySelector('a') || {}).rel, href: (page.querySelector('a') || {}).href, text: rows.map((r) => r.textContent.replace(/\s+/g, ' ').trim()) };
    }, e.content);
    R.check('f08b: the viewer shows hidden rows with a badge, escaped; an address is a nofollow / noreferrer link', view.n >= 4 && view.badges === view.n && view.imgs === 0 && /noreferrer/.test(view.link || '') && /nofollow/.test(view.link || ''), view);
  });

  /* ------------------------------------------------------------------ f08c */
  scenario('f08c', 'F08 fa_IR and ar at 375 px: piped names keep their order, local digits prefill a number, aligned labels', async ({ context }) => {
    setup();
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        // As the builder makes them on a right-to-left site (label_align txt-right).
        const F = form('F08 e2e ' + loc, [
          { key: 'name', type: 'text', name: fa ? 'نام' : 'الاسم', prefill_key: 'n', label_align: 'txt-right' },
          { key: 'why', type: 'text', name: (fa ? 'چرا، ' : 'لماذا، ') + '{field:@@name@@|' + (fa ? 'دوست' : 'صديق') + '}؟', label_align: 'txt-right' },
          { key: 'en', type: 'text', name: 'Hello {field:@@name@@|friend}, welcome', label_align: 'txt-right' },
          { key: 'age', type: 'number', name: fa ? 'سن' : 'العمر', prefill_key: 'age', label_align: 'txt-right' },
          { key: 'src', type: 'hiddenField', name: 'UTM', value: '{get:utm_source}' },
        ]);
        const g = await open(context, pageOf(F.form_id) + '?n=' + encodeURIComponent('Sara Smith') + '&age=' + encodeURIComponent(fa ? '۳۲' : '٣٢') + '&utm_source=news', 'f08c-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        await sleep(500);
        const m = await g.evaluate((ids) => {
          const lab = (x) => document.getElementById(x + '_lab');
          const piece = (x) => lab(x).querySelector('.efb-st');
          const align = (x) => { const l = document.getElementById(x + '_labG'); return l ? getComputedStyle(l).textAlign : null; };
          return {
            dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction,
            why: lab(ids.why).textContent, en: lab(ids.en).textContent, pieceTag: piece(ids.why).tagName, pieceBidi: getComputedStyle(piece(ids.why)).unicodeBidi,
            age: document.getElementById(ids.age + '_').value, aligns: [align(ids.name), align(ids.why), align(ids.en), align(ids.age)],
            scroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            hidden: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === ids.src) || {}).value,
          };
        }, F.ids);
        R.check(`f08c ${loc}: right to left; a Latin name piped into a ${fa ? 'Persian' : 'Arabic'} label is an isolated <bdi> (its order kept)`, m.dir === 'rtl' && m.pieceTag === 'BDI' && /isolate/.test(m.pieceBidi) && m.why.indexOf('Sara Smith') !== -1, m);
        R.check(`f08c ${loc}: the English label keeps its sentence order with the piped name`, m.en === 'Hello Sara Smith, welcome', m.en);
        R.check(`f08c ${loc}: ${fa ? 'Persian' : 'Arabic-Indic'} digits prefill a number field (stored in Latin digits)`, m.age === '32', m.age);
        R.check(`f08c ${loc}: the labels with pieces align like the other labels; no horizontal scroll at 375 px`, new Set(m.aligns).size === 1 && m.scroll, m);
        R.check(`f08c ${loc}: the hidden value is set`, m.hidden === 'news', m.hidden);
        await g.screenshot({ path: SHOT(`f08c-${loc}-375`), fullPage: true });
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f08d */
  scenario('f08d', 'F08 messages and redirects: the redirect host kept and the answers encoded; the subject on one line with the label', async ({ context }) => {
    setup();
    ctl('mails', 'clear');
    const F = form('F08 e2e redirect', [
      { key: 'name', type: 'text', name: 'Name' },
      { key: 'color', type: 'radio', name: 'Color' },
      { key: 'red', type: 'option', parent_key: 'color', value: 'Red & Co' },
      { key: 'blue', type: 'option', parent_key: 'color', value: 'Blue' },
    ], 'thank_you=rdrct&rePage=' + encodeURIComponent(CFG.base + '/?f08=thanks&c={field:@@color@@}&n={field:@@name@@}&from={get:next}#{entry:track}') + '&email_sub=' + encodeURIComponent('Lead {field:@@color@@} {get:crlf}'));
    const g = await open(context, pageOf(F.form_id) + '?next=' + encodeURIComponent('https://evil.test/') + '&crlf=' + encodeURIComponent('a\r\nBcc: spy@evil.test'), 'f08d');
    await g.fill(`[id="${F.ids.name}_"]`, 'A&B #1');
    await g.press(`[id="${F.ids.name}_"]`, 'Tab');
    await g.check(`[id="${F.ids.red}"]`).catch(async () => { await g.click(`label[for="${F.ids.red}"]`); });
    await sleep(400);
    const nav = g.waitForURL(/f08=thanks/, { timeout: 20000 }).catch(() => null);
    const res = await send(g);
    await nav;
    const u = new URL(res && typeof res.m === 'string' ? res.m : 'https://x.invalid/');
    R.check('f08d: the redirect keeps the site\'s host; {get:next} is only a value', u.host === new URL(CFG.base).host && u.searchParams.get('from') === 'https://evil.test/', res);
    R.check('f08d: the answers are encoded in the query (a choice by its label)', u.searchParams.get('c') === 'Red & Co' && u.searchParams.get('n') === 'A&B #1' && /%26/.test(res.m), res && res.m);
    R.check('f08d: the browser went to the redirect address', /f08=thanks/.test(g.url()), g.url());
    let mails = [];
    for (let i = 0; i < 30 && !mails.some((m) => /^Lead /.test(m.subject)); i++) { await sleep(500); mails = ctl('mails') || []; }
    const sub = (mails.find((m) => /^Lead /.test(m.subject)) || {}).subject || '';
    R.check('f08d: the e-mail subject: the choice\'s label, CR/LF of a parameter removed (one line)', sub.indexOf('Lead Red & Co a Bcc: spy@evil.test') === 0 && !/[\r\n]/.test(sub), sub);
  });

  /* ------------------------------------------------------------------ f08e */
  scenario('f08e', 'F08 cache: no signed-in user in the page HTML; the user from forms/session/prepare; a guest on the cached copy', async ({ context, adminPage }) => {
    setup();
    const F = form('F08 e2e cache', [
      { key: 'name', type: 'text', name: 'Name', value: '{user:display_name}' },
      { key: 'hi', type: 'text', name: 'Hi {user:first_name|there}!' },
      { key: 'mail', type: 'hiddenField', name: 'Member', value: '{user:email}' },
    ]);
    const url = pageOf(F.form_id);
    const me = ctl('user', 'efb_e2e_f08', 'subscriber');
    const admin = await adminPage({}, 'f08e-member', me.login, me.password);
    const htmlRes = await admin.request.get(url);
    const html = await htmlRes.text();
    R.check('f08e: the page HTML of a signed-in user holds no e-mail, display name or login of that user', html.indexOf(me.login + '@example.test') === -1 && html.indexOf('"user_name":"' + me.login + '"') === -1 && !new RegExp('value="' + me.login).test(html), null);
    await admin.goto(url, { waitUntil: 'load' });
    await admin.waitForFunction(() => window.EFBSmart, null, { timeout: 20000 });
    await admin.click(`[id="${F.ids.hi}_"]`);
    await admin.waitForFunction((i) => document.getElementById(i + '_').value !== '', F.ids.name, { timeout: 15000 }).catch(() => null);
    const mine = await admin.evaluate((ids) => ({ name: document.getElementById(ids.name + '_').value, hidden: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === ids.mail) || {}).value || '' }), F.ids);
    R.check('f08e: the signed-in user\'s page fills {user:display_name} from forms/session/prepare (and the hidden {user:email})', mine.name === me.login && mine.hidden === me.login + '@example.test', mine);
    // The cached copy (the member's HTML) served to a guest.
    const guest = await (await context({}, 'f08e-guest')).newPage();
    await guest.route(url, (route) => route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
    await guest.goto(url, { waitUntil: 'load' });
    await guest.waitForFunction(() => window.EFBSmart, null, { timeout: 20000 });
    await sleep(1200);
    await guest.click(`[id="${F.ids.hi}_"]`);
    await sleep(1200);
    const g = await guest.evaluate((ids) => ({ name: document.getElementById(ids.name + '_').value, hi: document.getElementById(ids.hi + '_lab').textContent, hidden: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === ids.mail) || {}).value || '' }), F.ids);
    R.check('f08e: a guest on the cached copy sees the fallbacks, never the member\'s values', g.name === '' && g.hi === 'Hi there!' && g.hidden === '', g);
    await guest.fill(`[id="${F.ids.name}_"]`, 'Guest');
    const res = await send(guest);
    const e = lastEntry(F.form_id);
    R.check('f08e: the guest\'s entry: {user:email} empty even with the member\'s cached page', res && res.success === true && !rowOf(e, F.ids.mail), e && e.content);
  });

  /* ------------------------------------------------------------------ f08pay */
  scenario('f08pay', 'F08 a paid form (test gateway): an address cannot pick a priced choice, a hidden "Price" changes nothing', async ({ context }) => {
    setup();
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'hidden', 'F08 e2e paid');
    const rows = (ctl('form', String(P.form_id)) || {}).form_structer || [];
    const radio = rows.find((r) => r && r.id_ === P.radio) || {};
    R.check('f08pay: the save drops a prefill key on a priced choice', radio.prefill_key === undefined, radio);
    const g = await (await context({}, 'f08pay')).newPage();
    await g.goto(pageOf(P.form_id) + '?campaign=summer&ticket=Child&utm_source=ads', { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
    await sleep(800);
    const pre = await g.evaluate((ids) => ({ o1: document.getElementById(ids.o1).checked, o2: document.getElementById(ids.o2).checked, camp: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === ids.file) || {}).value }), P);
    R.check('f08pay: ?ticket=Child picks nothing; the hidden campaign takes its key', !pre.o1 && !pre.o2 && pre.camp === 'summer', pre);
    await g.fill(`[data-id="${P.email}-el"]`, 'payer@example.test');
    await g.check(`[id="${P.o1}"]`).catch(async () => { await g.click(`label[for="${P.o1}"]`); });
    await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'hiddenField' ? Object.assign(r, { value: '1', price: '1' }) : r)));
      return route.continue({ postData: JSON.stringify(b) });
    });
    const before = (ctl('payments', String(P.form_id)) || []).length;
    const resp = g.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await g.click('.efb-pf [data-pf-pay]');
    await resp;
    await sleep(1500);
    const pays = ctl('payments', String(P.form_id)) || [];
    const p = pays[0] || {};
    R.check('f08pay: one payment of the server\'s price (Adult 25 + fee 2 = 27 EUR), whatever the hidden fields said', pays.length === before + 1 && Number(p.amount_minor || p.amount) === 2700, p);
    const e = lastEntry(P.form_id);
    R.check('f08pay: the pending entry keeps the server\'s hidden values (the fixed 999, the campaign from the address)', (rowOf(e, P.total) || {}).value === '999' && (rowOf(e, P.file) || {}).value === 'summer', e && e.content);
  });
};
