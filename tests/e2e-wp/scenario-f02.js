/**
 * F02 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F02-verification-otp.fa.md), on the real site.
 * SMS codes go through the WP SMS plugin only (owner rule): the scenarios turn on the SMS add-on
 * (AdnSS, vendor/smssended, its WP SMS bridge) and a stand-in of WP SMS from the mu-plugin
 * efb-e2e-f02.php (wp_sms_send() records the text; no network). E-mails are captured (efb-e2e-f11.php).
 *
 *  f02a  E1 "type it twice": the builder's "Email confirmation" select saves email_confirm_mode; a guest
 *        with a different second copy is stopped at the second box (role=alert, aria-invalid); the
 *        same address in another case passes and is stored; a request without email_confirms (changed
 *        after the browser check) is refused by the server (efb_verify_mismatch).
 *  f02b  E2/E3 e-mail code: "Send code" enabled only for a valid address, the status line names the
 *        masked address in a <bdi>, the e-mail has the code once and no link; two wrong codes ("N
 *        attempts left"), the right code typed in Persian digits → "Verified"; Send stores the entry with
 *        the server's badge, the challenge is consumed; the same token again (a replayed request) and
 *        a changed address with the old token are refused; Send before verifying is stopped at the field.
 *  f02c  S4 SMS code through WP SMS on a two-step form: Next blocked until verified, a country outside
 *        the list refused in the page and on the server, the WP SMS text has the WebOTP line
 *        "@host #code", a changed number after verifying blocks again, the entry is stored with the badge
 *        "sms" (response viewer "Verified by SMS"); WP SMS failing → the visitor's retry sentence only;
 *        WP SMS missing → the form says verification is unavailable and refuses (fail-closed).
 *  f02d  fa_IR at 375 px: the code block right to left (the button under the field, full width), the
 *        mixed-script status keeps its order (unicode-bidi plaintext, the number in a <bdi dir=ltr>),
 *        Persian digits in the countdown, no horizontal scroll; screenshots.
 *  f02e  Settings > Verification (?tab=verify): channel states, "WP SMS is not installed" with the
 *        install link when it is missing, Save of the countries; AutoPilot: capabilities.verification,
 *        efbBuilderApi.setVerification() (written, refused with efb_verify_unavailable when SMS is not
 *        ready), the save answer's verify_unavailable warning.
 *  f02pay A paid form (test gateway) with an e-mail code: no payments/create before the code is
 *        verified; then the payment is created and the gateway gets the verified address as the payer.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const byId = (id) => `[id="${id}"]`;
  const plan3 = () => { if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3); };
  const form = (name, fields, row0, steps) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', String(steps || 1));
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const setup = () => {
    plan3();
    ctl('f11-mu'); ctl('f02-mu');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
    ctl('set-option', 'efb_e2e_f02_plan', '1');
  };
  const smsOn = (mode) => { ctl('addon-flag', 'AdnSS', '1'); ctl('f02-wpsms', '1', mode || 'ok'); };
  const smsOff = () => { ctl('f02-wpsms', '0'); };
  const lastMail = (to) => {
    const log = ctl('option', 'efb_e2e_mail_bodies') || [];
    const list = (Array.isArray(log) ? log : Object.values(log || {})).filter((m) => JSON.stringify(m.to).indexOf(to) !== -1);
    return list[list.length - 1] || null;
  };
  const mailCode = (m) => { const x = m && /letter-spacing:6px[^>]*>(\d{6,8})</.exec(m.message); return x ? x[1] : ''; };
  const lastSms = () => { const l = ctl('f02-sms'); const a = Array.isArray(l) ? l : Object.values(l || {}); return a[a.length - 1] || null; };
  const fa = (s) => String(s).replace(/[0-9]/g, (d) => String.fromCharCode(0x06F0 + Number(d)));
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await sleep(1500);
    return g;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    // The last step of a multi-step form sends with its Next button.
    await page.click((await page.isVisible('#btn_send_efb')) ? '#btn_send_efb' : '#next_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(900);
    return j && (j.data || j);
  }
  /** Clicks "Send code" and waits for verify/send. */
  async function sendCode(page, vid) {
    const resp = page.waitForResponse((x) => /verify\/send|verify%2Fsend/.test(x.url()), { timeout: 20000 }).catch(() => null);
    await page.click(byId(vid + '_vrf-send'));
    const r = await resp;
    await sleep(500);
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    return { status: j && j.success === false ? j.status : (r ? r.status() : 0), http: r ? r.status() : 0, body: j };
  }
  async function typeCode(page, vid, code) {
    const resp = page.waitForResponse((x) => /verify\/check|verify%2Fcheck/.test(x.url()), { timeout: 20000 }).catch(() => null);
    await page.fill(byId(vid + '_vrf-code'), '');
    await page.type(byId(vid + '_vrf-code'), code);
    const r = await resp;
    await sleep(500);
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    return { status: j && j.success === false ? j.status : (r ? r.status() : 0), http: r ? r.status() : 0, body: j };
  }
  const block = (page, vid) => page.evaluate((v) => {
    const g = (s) => document.getElementById(v + s);
    return { state: (g('_vrf') || {}).getAttribute ? g('_vrf').getAttribute('data-state') : null, status: g('_vrf-status') ? g('_vrf-status').textContent : null, statusHtml: g('_vrf-status') ? g('_vrf-status').innerHTML : '',
      error: g('_vrf-error') && !g('_vrf-error').hidden ? g('_vrf-error').textContent : '', errRole: g('_vrf-error') ? g('_vrf-error').getAttribute('role') : null, live: g('_vrf-status') ? g('_vrf-status').getAttribute('aria-live') : null,
      boxOpen: g('_vrf-box') ? !g('_vrf-box').hidden : false, sendDisabled: g('_vrf-send') ? g('_vrf-send').disabled : null, sendHidden: g('_vrf-send') ? g('_vrf-send').hidden : null,
      codeAttrs: g('_vrf-code') ? ['inputmode', 'autocomplete', 'maxlength', 'dir'].map((a) => g('_vrf-code').getAttribute(a)) : null, codeInvalid: g('_vrf-code') ? g('_vrf-code').getAttribute('aria-invalid') : null,
      codeIsAnswer: g('_vrf-code') ? (g('_vrf-code').classList.contains('emsFormBuilder_v') || g('_vrf-code').hasAttribute('data-vid')) : null };
  }, vid);

  /* ------------------------------------------------------------------ f02a */
  scenario('f02a', 'F02-E1 "type it twice": builder select, the second box in the page, the server check', async ({ context, adminPage }) => {
    setup();
    // Builder: the e-mail field's "Email confirmation" select (free on every plan) saves the key.
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'email');
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#vrfEmailModeEl', { timeout: 10000 });
    const opts = await admin.evaluate(() => Array.from(document.querySelectorAll('#vrfEmailModeEl option')).map((o) => o.value + (o.disabled ? ':off' : '')));
    R.check('f02a: builder select offers off / type it twice / code / both', opts.join(',') === 'none,retype,otp,retype_otp', opts);
    await admin.selectOption('#vrfEmailModeEl', 'retype');
    await sleep(300);
    const lbl = await admin.isVisible('#vrfRetypeLabelEl');
    { const v_ = lbl; R.check('f02a: the label box of the second copy shows for "type it twice"', JSON.stringify(v_) === JSON.stringify(true), v_); }
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid0 = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows0 = (ctl('form', String(fid0)) || {}).form_structer || [];
    { const v_ = (rows0.find((r) => r && r.id_ === t.id_) || {}).email_confirm_mode; R.check('f02a: Save stores email_confirm_mode = retype', v_ === 'retype', v_); }

    const F = form('F02 e2e retype', [{ key: 'mail', type: 'email', name: 'Email', required: 1, email_confirm_mode: 'retype' }, { key: 'name', type: 'text', name: 'Name' }]);
    const url = pageOf(F.form_id);
    const g = await open(context, url, 'f02a');
    const has = await g.evaluate((i) => ({ box: !!document.getElementById(i + '_confirm'), cls: (document.getElementById(i + '_confirm') || {}).className || '', label: (document.querySelector(`label[for="${i}_confirm"]`) || {}).textContent }), F.ids.mail);
    R.check('f02a: the second box "Confirm email" is there and is not an answer (no emsFormBuilder_v)', has.box && has.label === 'Confirm email' && has.cls.indexOf('emsFormBuilder_v') === -1, has);
    await g.fill(byId(F.ids.mail + '_'), 'Guest@Example.com');
    await g.press(byId(F.ids.mail + '_'), 'Tab');
    await g.fill(byId(F.ids.mail + '_confirm'), 'guest@exampel.com');
    await g.press(byId(F.ids.mail + '_confirm'), 'Tab');
    await sleep(300);
    await g.click('#btn_send_efb');
    await sleep(800);
    const mis = await g.evaluate((i) => ({ inv: document.getElementById(i + '_confirm').getAttribute('aria-invalid'), msg: document.getElementById(i + '_confirm-message').textContent, role: document.getElementById(i + '_confirm-message').getAttribute('role') }), F.ids.mail);
    R.check('f02a: a different copy stops Send, told at the second box (aria-invalid, role=alert)', mis.inv === 'true' && mis.msg === 'The email addresses do not match.' && mis.role === 'alert', mis);
    { const v_ = (ctl('entries', String(F.form_id)) || []).length; R.check('f02a: nothing stored', JSON.stringify(v_) === JSON.stringify(0), v_); }
    // The paste is allowed (no blocking), the same address in another case passes.
    await g.fill(byId(F.ids.mail + '_confirm'), 'GUEST@example.COM');
    await g.press(byId(F.ids.mail + '_confirm'), 'Tab');
    // The server: a request without email_confirms (changed after the browser check) is refused.
    await g.route('**/*', async (route) => {
      const req = route.request();
      if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST') {
        const b = JSON.parse(req.postData()); b.email_confirms = { [F.ids.mail]: 'other@example.com' };
        return route.continue({ postData: JSON.stringify(b) });
      }
      return route.continue();
    });
    const a1 = await send(g);
    R.check('f02a: server refuses a mismatching copy: efb_verify_mismatch at the field', a1 && a1.success === false && a1.code === 'efb_verify_mismatch' && a1.field_id === F.ids.mail, a1);
    await g.unroute('**/*');
    const g2 = await open(context, url, 'f02a-2');
    await g2.fill(byId(F.ids.mail + '_'), 'Guest@Example.com');
    await g2.press(byId(F.ids.mail + '_'), 'Tab');
    await g2.fill(byId(F.ids.mail + '_confirm'), 'GUEST@example.COM');
    await g2.press(byId(F.ids.mail + '_confirm'), 'Tab');
    const reqBody = { v: null };
    g2.on('request', (rq) => { if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(rq.url())) reqBody.v = rq.postData(); });
    const a2 = await send(g2);
    R.check('f02a: matching copies → sent', a2 && a2.success === true, a2);
    const sent = JSON.parse(reqBody.v || '{}');
    R.check('f02a: the copy travels as email_confirms beside the answers, never as an answer row', !!(sent.email_confirms && sent.email_confirms[F.ids.mail]) && String(sent.value).indexOf('GUEST@example.COM') === -1, sent.email_confirms);
    const ent = ctl('entries', String(F.form_id)) || [];
    R.check('f02a: one entry with the address, no "verified" badge (no code was asked)', ent.length === 1 && JSON.stringify(ent[0].content).toLowerCase().indexOf('guest@example.com') !== -1 && JSON.stringify(ent[0].content).indexOf('"verified"') === -1, ent[0] && ent[0].content);
  });

  /* ------------------------------------------------------------------ f02b */
  scenario('f02b', 'F02-E2/E3 e-mail code: send, wrong ×2, Persian digits, verified, stored once, replay and swap refused', async ({ context }) => {
    setup();
    const F = form('F02 e2e email code', [{ key: 'mail', type: 'email', name: 'Email', required: 1, email_confirm_mode: 'otp' }, { key: 'name', type: 'text', name: 'Name' }]);
    ctl('f02-rows', String(F.form_id), 'reset');
    const url = pageOf(F.form_id);
    const g = await open(context, url, 'f02b');
    let b = await block(g, F.ids.mail);
    R.check('f02b: the block: Send code disabled while empty; status aria-live polite; errors role=alert; code box one-time-code, numeric, LTR, not an answer', b.sendDisabled === true && b.live === 'polite' && b.errRole === 'alert' && JSON.stringify(b.codeAttrs) === JSON.stringify(['numeric', 'one-time-code', '6', 'ltr']) && b.codeIsAnswer === false, b);
    await g.fill(byId(F.ids.mail + '_'), 'not-an-address');
    await sleep(200);
    { const v_ = (await block(g, F.ids.mail)).sendDisabled; R.check('f02b: an invalid address keeps Send code disabled', JSON.stringify(v_) === JSON.stringify(true), v_); }
    await g.click('#btn_send_efb');
    await sleep(800);
    await g.fill(byId(F.ids.mail + '_'), 'Visitor@Example.com');
    await g.press(byId(F.ids.mail + '_'), 'Tab');
    await g.click('#btn_send_efb');
    await sleep(800);
    b = await block(g, F.ids.mail);
    R.check('f02b: Send before verifying is stopped with "Please verify Email before continuing." at the field', b.error === 'Please verify Email before continuing.' && (ctl('entries', String(F.form_id)) || []).length === 0, b);
    const s1 = await sendCode(g, F.ids.mail);
    b = await block(g, F.ids.mail);
    R.check('f02b: verify/send 200: the code box opens, the status names the masked address in a <bdi>', s1.status === 200 && b.boxOpen && b.state === 'code_sent' && /<bdi dir="ltr">vi\*\*\*@example\.com<\/bdi>/.test(b.statusHtml) && /Check your spam folder too\./.test(b.status), { s1, b });
    const focus = await g.evaluate(() => document.activeElement && document.activeElement.id);
    R.check('f02b: the focus moves to the code box', focus === F.ids.mail + '_vrf-code', focus);
    const ro = await g.evaluate((i) => document.getElementById(i + '_').readOnly, F.ids.mail);
    { const v_ = ro; R.check('f02b: the address is read-only while the code is pending', JSON.stringify(v_) === JSON.stringify(true), v_); }
    const m = lastMail('visitor@example.com');
    const code = mailCode(m);
    R.check('f02b: one e-mail to the address, the code once, no link, no other answer, subject "Your verification code for …"', !!m && code.length === 6 && m.message.split(code).length === 2 && !/href=/i.test(m.message) && /^Your verification code for /.test(m.subject), m && { subject: m.subject, len: code.length });
    const wrong = code === '000000' ? '111111' : '000000';
    const w1 = await typeCode(g, F.ids.mail, wrong);
    b = await block(g, F.ids.mail);
    R.check('f02b: a wrong code (auto-checked at 6 digits) → "That code is not correct. 4 attempts left.", aria-invalid (status 422, HTTP 200: no console error)', w1.status === 422 && w1.http === 200 && b.error === 'That code is not correct. 4 attempts left.' && b.codeInvalid === 'true', { w1, b });
    await typeCode(g, F.ids.mail, wrong);
    b = await block(g, F.ids.mail);
    R.check('f02b: a second wrong code → 3 attempts left', b.error === 'That code is not correct. 3 attempts left.', b.error);
    const ok = await typeCode(g, F.ids.mail, fa(code));
    b = await block(g, F.ids.mail);
    R.check('f02b: the right code typed in Persian digits → "Verified", the box closes', ok.status === 200 && b.state === 'verified' && b.status === 'Verified' && !b.boxOpen, { st: ok.status, b });
    await g.fill(byId(F.ids.name + '_'), 'Visitor');
    await g.press(byId(F.ids.name + '_'), 'Tab');
    const bodyBox = { v: null };
    g.on('request', (rq) => { if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(rq.url())) bodyBox.v = rq.postData(); });
    const a = await send(g);
    R.check('f02b: Send → stored', a && a.success === true, a);
    const ent = ctl('entries', String(F.form_id)) || [];
    const row = (ent[0] && (Array.isArray(ent[0].content) ? ent[0].content : [])).find((r) => r && r.id_ === F.ids.mail) || {};
    R.check('f02b: the entry holds the address as typed with the server\'s badge verified = email', String(row.value).toLowerCase() === 'visitor@example.com' && row.verified === 'email', row);
    const ch = (ctl('f02-rows', String(F.form_id)).rows || []);
    const used = ch.filter((r) => r.status === 'consumed');
    R.check('f02b: one challenge consumed and tied to the entry; no raw address or code stored', used.length === 1 && Number(used[0].msg_id) > 0 && JSON.stringify(ch).indexOf('visitor@example.com') === -1 && JSON.stringify(ch).indexOf(code) === -1, ch.map((r) => r.status));
    // Replay: the same token in a new request (new request id) → refused; swap: another address with the token.
    const sent = JSON.parse(bodyBox.v || '{}');
    const g3 = await open(context, url, 'f02b-replay');
    const replay = await g3.evaluate(async ([fid, body, mail]) => {
      const rt = await window.EFBRuntime.prepare(Number(fid));
      const rows = JSON.parse(body.value);
      const post = async (b) => { const r = await fetch(efb_request_health.rest_url + 'forms/message/add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, body: JSON.stringify(b) }); return r.json(); };
      const base = Object.assign({}, body, { sid: rt.sid, runtime_token: rt.runtime_token, request_id: crypto.randomUUID() });
      const r1 = await post(base);
      const swapped = Object.assign({}, base, { request_id: crypto.randomUUID(), value: JSON.stringify(rows.map((r) => (r.id_ === mail ? Object.assign({}, r, { value: 'someone@example.com' }) : r))) });
      const r2 = await post(swapped);
      return [r1.data || r1, r2.data || r2];
    }, [F.form_id, sent, F.ids.mail]);
    R.check('f02b: the same token again (replay, another session) → refused efb_verify_required', replay[0] && replay[0].success === false && replay[0].code === 'efb_verify_required' && replay[0].field_id === F.ids.mail, replay[0]);
    R.check('f02b: another address with the token (value swap) → refused', replay[1] && replay[1].success === false && replay[1].code === 'efb_verify_required', replay[1]);
    { const v_ = (ctl('entries', String(F.form_id)) || []).length; R.check('f02b: still one entry', JSON.stringify(v_) === JSON.stringify(1), v_); }
    // Editing after verifying drops the token in the page.
    const g4 = await open(context, url, 'f02b-edit');
    await g4.fill(byId(F.ids.mail + '_'), 'second@example.com');
    await g4.press(byId(F.ids.mail + '_'), 'Tab');
    await sendCode(g4, F.ids.mail);
    const c2 = mailCode(lastMail('second@example.com'));
    await typeCode(g4, F.ids.mail, c2);
    await g4.fill(byId(F.ids.mail + '_'), 'third@example.com');
    await sleep(300);
    b = await block(g4, F.ids.mail);
    R.check('f02b: changing the address after verifying → back to "Send code" (idle), the token is gone', b.state === 'idle' && b.sendHidden === false && b.sendDisabled === false, b);
  });

  /* ------------------------------------------------------------------ f02c */
  scenario('f02c', 'F02-S4 SMS code through WP SMS: two steps, country list, WebOTP line, changed number, badge, failures, fail-closed', async ({ context, adminPage }) => {
    setup();
    smsOn('ok');
    ctl('f02-settings', JSON.stringify({ sms: { allowed_countries: ['ir'] } }));
    const st = ctl('f02-settings');
    R.check('f02c: SMS channel ready with the SMS add-on and WP SMS', st.sms && st.sms.ready, st.sms);
    const F = form('F02 e2e sms', [{ key: 'mob', type: 'mobile', name: 'Mobile', required: 1, otp_enabled: '1', step: 1 }, { key: 'name', type: 'text', name: 'Name', step: 2 }], '', 2);
    ctl('f02-rows', String(F.form_id), 'reset');
    ctl('f02-sms', 'clear');
    const url = pageOf(F.form_id);
    const g = await open(context, url, 'f02c');
    await g.waitForFunction((i) => !!(document.getElementById(i + '_') && document.getElementById(i + '_').closest('.iti')), F.ids.mob, { timeout: 20000 });
    await sleep(800);
    const noCode = await g.evaluate((i) => !document.getElementById(i + '-code') && !document.getElementById(i + '-btn'), F.ids.mob);
    { const v_ = noCode; R.check('f02c: the old hidden "-code" input and hidden submit button are gone', JSON.stringify(v_) === JSON.stringify(true), v_); }
    await g.click(byId(F.ids.mob + '_'));
    await g.keyboard.type('+4915112345678');
    await g.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(600);
    await g.click(byId(F.ids.mob + '_vrf-send')).catch(() => {});
    await sleep(600);
    let b = await block(g, F.ids.mob);
    R.check('f02c: a German number (not in the list) → "Verification codes cannot be sent to this country."', b.error === 'Verification codes cannot be sent to this country.', b);
    const srv = await g.evaluate(async ([fid, vid]) => {
      const rt = await window.EFBRuntime.prepare(Number(fid));
      const r = await fetch(efb_request_health.rest_url + 'verify/send', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, body: JSON.stringify({ form_id: Number(fid), field_id: vid, destination: '+4915112345678', runtime_token: rt.runtime_token }) });
      const j = await r.json(); return [j.status || r.status, j.code, r.status];
    }, [F.form_id, F.ids.mob]);
    R.check('f02c: the server refuses it too (efb_verify_country, status 422 in the plugin\'s envelope, HTTP 200)', srv[0] === 422 && srv[1] === 'efb_verify_country' && srv[2] === 200, srv);
    { const v_ = (ctl('f02-sms') || []).length; R.check('f02c: no SMS went out', JSON.stringify(v_) === JSON.stringify(0), v_); }
    await g.click(byId(F.ids.mob + '_'), { clickCount: 3 });
    await g.keyboard.press('Backspace');
    await g.keyboard.type('+989121234567');
    await g.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(600);
    await g.click('#next_efb');
    await sleep(800);
    b = await block(g, F.ids.mob);
    const step = await g.evaluate((fid) => document.getElementById('body_efb_' + fid).dataset.currentstep, F.form_id);
    R.check('f02c: Next is blocked until the number is verified', step === '1' && b.error === 'Please verify Mobile before continuing.', { step, b });
    const s1 = await sendCode(g, F.ids.mob);
    const sms = lastSms();
    const code = sms && /(\d{6})/.exec(sms.msg) ? /(\d{6})/.exec(sms.msg)[1] : '';
    R.check('f02c: WP SMS got one message to +989121234567 with the code and the WebOTP line "@127.0.0.1 #code"', s1.status === 200 && sms && JSON.stringify(sms.to) === '["+989121234567"]' && sms.msg.indexOf('#' + code) !== -1 && /\n\n@[^\s]+ #\d{6}$/.test(sms.msg), sms);
    b = await block(g, F.ids.mob);
    R.check('f02c: the status names "+98 912 ***-4567" in a <bdi dir="ltr">', /<bdi dir="ltr">\+98 912 \*\*\*-4567<\/bdi>/.test(b.statusHtml), b.statusHtml);
    await typeCode(g, F.ids.mob, code);
    b = await block(g, F.ids.mob);
    R.check('f02c: verified', b.state === 'verified', b);
    // A changed number after verifying blocks again.
    await g.click(byId(F.ids.mob + '_'), { clickCount: 3 });
    await g.keyboard.press('Backspace');
    await g.keyboard.type('+989121234568');
    await g.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(500);
    await g.click('#next_efb');
    await sleep(600);
    b = await block(g, F.ids.mob);
    R.check('f02c: a changed number after verifying → idle and Next blocked again', b.state === 'idle' && (await g.evaluate((fid) => document.getElementById('body_efb_' + fid).dataset.currentstep, F.form_id)) === '1', b.state);
    await g.click(byId(F.ids.mob + '_'), { clickCount: 3 });
    await g.keyboard.press('Backspace');
    await g.keyboard.type('+989121234567');
    await g.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(500);
    // The token went with the change: a new code is needed in the page. (reset: no 60 s resend wait in the test)
    ctl('f02-rows', String(F.form_id), 'reset');
    await sendCode(g, F.ids.mob);
    const code2 = /(\d{6})/.exec(lastSms().msg)[1];
    await typeCode(g, F.ids.mob, code2);
    await g.click('#next_efb');
    await sleep(800);
    { const v_ = await g.evaluate((fid) => document.getElementById('body_efb_' + fid).dataset.currentstep, F.form_id); R.check('f02c: verified → Next moves to step 2', JSON.stringify(v_) === JSON.stringify('2'), v_); }
    await g.fill(byId(F.ids.name + '_'), 'Sara');
    await g.press(byId(F.ids.name + '_'), 'Tab');
    const reqC = { v: null };
    g.on('request', (rq) => { if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(rq.url())) reqC.v = rq.postData(); });
    const a = await send(g);
    R.check('f02c: sent', a && a.success === true, { a, sent: reqC.v && (() => { const j = JSON.parse(reqC.v); return { v: j.verifications, value: j.value }; })() });
    const ent = ctl('entries', String(F.form_id)) || [];
    const row = ((ent[0] || {}).content || []).find((r) => r && r.id_ === F.ids.mob) || {};
    R.check('f02c: the entry: +989121234567 with verified = sms', row.value === '+989121234567' && row.verified === 'sms', row);
    // Response viewer badge (admin).
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await sleep(1500);
    const html = await admin.evaluate(([m, fid]) => { try { return fun_emsFormBuilder_show_messages(m, 'user', 'x', fid, 1) || ''; } catch (e) { return 'ERR ' + e.message; } }, [ent[0].content, F.form_id]);
    R.check('f02c: the response viewer shows "Verified by SMS"', typeof html === 'string' && html.indexOf('Verified by SMS') !== -1, String(html).slice(0, 200));

    // WP SMS fails: the visitor reads the retry sentence only; the owner gets one e-mail.
    ctl('f02-wpsms', '1', 'error');
    ctl('f02-rows', String(F.form_id), 'reset');
    const g2 = await open(context, url, 'f02c-fail');
    await g2.waitForFunction((i) => !!(document.getElementById(i + '_') && document.getElementById(i + '_').closest('.iti')), F.ids.mob, { timeout: 20000 });
    await sleep(600);
    await g2.click(byId(F.ids.mob + '_'));
    await g2.keyboard.type('+989351112233');
    await g2.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(500);
    const f1 = await sendCode(g2, F.ids.mob);
    b = await block(g2, F.ids.mob);
    R.check('f02c: WP SMS answers a WP_Error → 502 and "We could not send the code. Please try again shortly." (no gateway text)', f1.status === 502 && b.error === 'We could not send the code. Please try again shortly.' && b.error.indexOf('credit') === -1, { st: f1.status, b });
    const alert = (ctl('option', 'efb_e2e_mail_bodies') || []).filter((m) => /Verification codes need your attention/.test(m.subject));
    R.check('f02c: the owner got one e-mail about WP SMS', alert.length >= 1 && /WP SMS/.test(alert[alert.length - 1].message), alert.length);
    // WP SMS missing: fail-closed.
    smsOff();
    const g3 = await open(context, url, 'f02c-off');
    await g3.waitForFunction((i) => !!(document.getElementById(i + '_') && document.getElementById(i + '_').closest('.iti')), F.ids.mob, { timeout: 20000 });
    await sleep(600);
    await g3.click(byId(F.ids.mob + '_'));
    await g3.keyboard.type('+989351112244');
    await g3.press(byId(F.ids.mob + '_'), 'Tab');
    await sleep(500);
    const off = await sendCode(g3, F.ids.mob);
    b = await block(g3, F.ids.mob);
    R.check('f02c: WP SMS not active → 503 "Verification is not available right now. Please contact the site owner."', off.status === 503 && b.error === 'Verification is not available right now. Please contact the site owner.', { st: off.status, b });
    const forced = await g3.evaluate(async ([fid, vid]) => {
      const rt = await window.EFBRuntime.prepare(Number(fid));
      const body = { action: 'get_form_Emsfb', value: JSON.stringify([{ id_: vid, name: 'Mobile', id_ob: vid, amount: 2, type: 'mobile', value: '+989351112244', session: '', form_id: fid }]), name: 'x', id: fid, type: 'form', url: location.href, sid: rt.sid, runtime_token: rt.runtime_token, request_id: crypto.randomUUID(), page_id: 0 };
      const r = await fetch(efb_request_health.rest_url + 'forms/message/add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, body: JSON.stringify(body) });
      return (await r.json()).data;
    }, [F.form_id, F.ids.mob]);
    R.check('f02c: a hand-made submit with a number is refused (efb_verify_unavailable), never skipped', forced && forced.success === false && forced.code === 'efb_verify_unavailable', forced);
    ctl('f02-wpsms', '1', 'ok');
  });

  /* ------------------------------------------------------------------ f02d */
  scenario('f02d', 'F02 fa_IR at 375 px: the code block right to left, mixed-script status in order, Persian digits, no horizontal scroll', async ({ context }) => {
    setup();
    smsOn('ok');
    ctl('f02-settings', JSON.stringify({ sms: { allowed_countries: ['ir'] } }));
    const F = form('F02 e2e fa', [{ key: 'mob', type: 'mobile', name: 'موبایل', otp_enabled: '1', label_align: 'txt-right' }, { key: 'mail', type: 'email', name: 'ایمیل', email_confirm_mode: 'retype_otp', label_align: 'txt-right' }]);
    ctl('f02-rows', String(F.form_id), 'reset');
    const url = pageOf(F.form_id);
    ctl('locale', 'fa_IR');
    try {
      const g = await open(context, url, 'f02d', { viewport: { width: 375, height: 800 } });
      await g.waitForFunction((i) => !!(document.getElementById(i + '_') && document.getElementById(i + '_').closest('.iti')), F.ids.mob, { timeout: 20000 });
      await sleep(800);
      const lay = await g.evaluate((ids) => {
        const inp = document.getElementById(ids.mob + '_');
        const btn = document.getElementById(ids.mob + '_vrf-send');
        const ib = inp.getBoundingClientRect(); const bb = btn.getBoundingClientRect();
        return { dir: document.documentElement.dir || getComputedStyle(document.body).direction, below: bb.top >= ib.bottom - 1, full: Math.abs(bb.width - (inp.closest('.efb-vrf-row') || inp).getBoundingClientRect().width) < 4, tall: bb.height >= 44, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, inputDir: getComputedStyle(inp).direction };
      }, F.ids);
      R.check('f02d: RTL page; at 375 px the "Send code" button sits under the number, full width, ≥ 44 px; the number stays LTR; no horizontal scroll', lay.dir === 'rtl' && lay.below && lay.full && lay.tall && lay.inputDir === 'ltr' && lay.scroll, lay);
      await g.screenshot({ path: SHOT('f02-fa-375-idle'), fullPage: true });
      await g.click(byId(F.ids.mob + '_'));
      await g.keyboard.type('+۹۸۹۱۲۷۶۵۴۳۲۱'); // Persian digits, as a phone keyboard types them
      await g.press(byId(F.ids.mob + '_'), 'Tab');
      await sleep(600);
      await sendCode(g, F.ids.mob);
      const st = await g.evaluate((i) => { const s = document.getElementById(i + '_vrf-status'); const bdis = s.querySelectorAll('bdi'); const t = document.getElementById(i + '_vrf-timer');
        return { bidi: getComputedStyle(s).unicodeBidi, align: getComputedStyle(s).textAlign, first: bdis[0] && bdis[0].textContent, num: bdis[1] && bdis[1].textContent, numDir: bdis[1] && bdis[1].getAttribute('dir'), timer: t.textContent, endsWithDot: /\.$/.test(s.textContent.trim()) }; }, F.ids.mob);
      R.check('f02d: the status keeps its order: unicode-bidi plaintext, the number in <bdi dir="ltr">, the digit count in Persian digits, the period at the end', st.bidi === 'plaintext' && st.num === '+98 912 ***-4321' && st.numDir === 'ltr' && st.first === '۶' && st.endsWithDot, st);
      R.check('f02d: the countdown in Persian digits ("Resend in ۱:۰۰" or less)', /[۰-۹]:[۰-۹]{2}/.test(st.timer), st.timer);
      await g.screenshot({ path: SHOT('f02-fa-375-code'), fullPage: true });
      const code = /(\d{6})/.exec(lastSms().msg)[1];
      await typeCode(g, F.ids.mob, fa(code));
      const b = await block(g, F.ids.mob);
      R.check('f02d: the code typed in Persian digits verifies', b.state === 'verified', b.state);
      const lab = await g.evaluate((i) => { const a = document.querySelector(`label[for="${i}_confirm"]`); const m = document.querySelector(`label[for="${i}_"]`) || document.getElementById(i + '_lab'); return { conf: a && getComputedStyle(a).textAlign, main: m && getComputedStyle(m).textAlign }; }, F.ids.mail);
      R.check('f02d: the "Confirm email" label is aligned like the field label', lab.conf === lab.main || (lab.conf === 'right' && lab.main === 'right') || (lab.conf === 'start' || lab.conf === 'right'), lab);
      const scroll = await g.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
      { const v_ = scroll; R.check('f02d: still no horizontal scroll with the code block open', JSON.stringify(v_) === JSON.stringify(true), v_); }
      await g.screenshot({ path: SHOT('f02-fa-375-verified'), fullPage: true });
    } finally {
      ctl('locale', '');
    }
  });

  /* ------------------------------------------------------------------ f02e */
  scenario('f02e', 'F02 Settings > Verification, AutoPilot setVerification / capabilities, save warning verify_unavailable', async ({ adminPage }) => {
    setup();
    smsOff();
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=verify', { waitUntil: 'load' });
    await admin.waitForSelector('#vrfs-ChannelStates', { timeout: 20000 });
    await admin.waitForSelector('#vrfs-SmsVia', { timeout: 10000 });
    await sleep(500);
    const s0 = await admin.evaluate(() => ({ states: Array.from(document.querySelectorAll('#vrfs-ChannelStates [data-vrf-ready]')).map((x) => x.getAttribute('data-vrf-ready')), missing: !!document.getElementById('vrfs-WpSmsMissing'), link: (document.querySelector('#vrfs-WpSmsMissing a') || {}).href || '', via: (document.getElementById('vrfs-SmsVia') || {}).textContent || '' }));
    R.check('f02e: tab "Verification": e-mail ready, SMS not available; "WP SMS is not installed or not active." with the install link', s0.states.join(',') === '1,0' && s0.missing && /plugin-install\.php\?s=wp-sms/.test(s0.link) && /through the WP SMS plugin/.test(s0.via), s0);
    await admin.screenshot({ path: SHOT('f02-settings-tab') });
    await admin.fill('#vrfs-CountriesSet', 'IR, ae');
    await admin.click('#vrfs-SaveBtn');
    await admin.waitForFunction(() => /saved/.test((document.getElementById('vrfs-SaveMsg') || {}).textContent || ''), null, { timeout: 10000 });
    { const v_ = ctl('f02-settings').settings.sms.allowed_countries; R.check('f02e: Save keeps the countries as ISO codes', JSON.stringify(v_) === JSON.stringify(['ir', 'ae']), v_); }
    // AutoPilot.
    await openNewForm(admin);
    const e = await addTile(admin, 'email');
    const m = await addTile(admin, 'mobile');
    const ap = await admin.evaluate(([eid, mid]) => {
      const out = { cap: efb_var.capabilities && efb_var.capabilities.verification, feat: efb_var.capabilities && efb_var.capabilities.features['setting:verify_sms_otp'] };
      out.email = efbBuilderApi.setVerification(eid, { email_confirm_mode: 'retype_otp', email_otp_required_when: 'always' });
      try { efbBuilderApi.setVerification(mid, { otp_enabled: 1 }); out.sms = 'no error'; } catch (x) { out.sms = x.code + '|' + x.mode + '|' + x.reason; }
      try { efbBuilderApi.setVerification(eid, { otp_enabled: 1 }); out.wrong = 'no error'; } catch (x) { out.wrong = x.code; }
      return out;
    }, [e.id_, m.id_]);
    R.check('f02e: capabilities.verification: keys, channels (e-mail ready, SMS not, add-on AdnSS), downgrade blocks_submit, no provider name', ap.cap && ap.cap.channels.email.ready === true && ap.cap.channels.sms.ready === false && ap.cap.channels.sms.addon === 'AdnSS' && ap.cap.downgrade === 'blocks_submit' && JSON.stringify(ap.cap).indexOf('wpsms') === -1, ap.cap);
    { const v_ = ap.email.changed; R.check('f02e: setVerification writes the e-mail keys', JSON.stringify(v_) === JSON.stringify(['email_confirm_mode', 'email_otp_required_when']), v_); }
    { const v_ = ap.sms; R.check('f02e: SMS on while WP SMS is missing → efb_verify_unavailable (mode sms, reason sender_not_ready)', JSON.stringify(v_) === JSON.stringify('efb_verify_unavailable|sms|sender_not_ready'), v_); }
    { const v_ = ap.wrong; R.check('f02e: a mobile key on an e-mail field → unsupported_key', JSON.stringify(v_) === JSON.stringify('unsupported_key'), v_); }
    // A form saved with SMS on while it cannot be sent: saved, with the warning.
    await admin.evaluate((mid) => { const r = valj_efb.find((x) => x.id_ === mid); r.otp_enabled = '1'; }, m.id_);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    const w = ((sj.data || {}).warnings || []).find((x) => x.code === 'verify_unavailable');
    R.check('f02e: the save answer warns verify_unavailable for the mobile field (reason sender_not_ready)', !!w && w.field_id === m.id_ && w.reason === 'sender_not_ready' && /WP SMS/.test(w.m), sj.data && sj.data.warnings);
  });

  /* ------------------------------------------------------------------ f02pay */
  scenario('f02pay', 'F02 × payments: no payments/create before the e-mail code is verified; the payer e-mail is the verified one', async ({ context }) => {
    setup();
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'fixed', 'F02 e2e pay');
    const fid = P.form_id;
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const em = rows.find((r) => r && r.id_ === P.email);
    if (!em) { R.check('f02pay: the payment form has an e-mail field', false, rows.map((r) => r && r.type)); return; }
    ctl('f03-field', String(fid), em.id_, 'email_confirm_mode=otp');
    ctl('f02-rows', String(fid), 'reset');
    const url = pageOf(fid);
    const g = await open(context, url, 'f02pay');
    let creates = 0;
    g.on('request', (rq) => { if (/payments\/create|payments%2Fcreate/.test(rq.url())) creates++; });
    await g.fill(byId(em.id_ + '_'), 'payer@example.com');
    await g.press(byId(em.id_ + '_'), 'Tab');
    await g.click(byId(P.o1));
    await sleep(500);
    await g.click('[data-pf-pay]');
    await sleep(1500);
    const b = await block(g, em.id_);
    R.check('f02pay: Pay before the code → stopped at the field, no payments/create request', creates === 0 && b.error === 'Please verify ' + (em.name || 'Email') + ' before continuing.', { creates, b });
    await sendCode(g, em.id_);
    await typeCode(g, em.id_, mailCode(lastMail('payer@example.com')));
    const pays = (ctl('payments', String(fid)) || []).length;
    const created = g.waitForResponse((x) => /payments\/create|payments%2Fcreate/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await g.click('[data-pf-pay]');
    const cr = await created;
    await sleep(1500);
    const list = ctl('payments', String(fid)) || [];
    R.check('f02pay: verified → payments/create runs, one payment created', !!cr && list.length === pays + 1, { status: cr && cr.status(), n: list.length });
    const p = list[0] || {};
    R.check('f02pay: the gateway got the verified address as the payer', JSON.stringify(p).indexOf('payer@example.com') !== -1, Object.keys(p));
    ctl('pay-mock', '0');
  });
};
