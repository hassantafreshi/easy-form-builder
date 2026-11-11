/**
 * AutoPilot B4: changing the form type to "login" or "register" adds the fields their handlers read.
 *
 * Before: formTypeEl only changed valj_efb[0].type, the register option was disabled, and a login
 * form without emaillogin / passwordlogin could be saved and never worked.
 * Now (real builder in Chromium): efbBuilderApi.setFormType() and the form-type select add the
 * fixed-id fields after the step-1 heading when missing, the register option is enabled, leaving
 * login/register asks before removing those fields, and Save refuses a login form that lacks them.
 * The server refusal is covered by tests/test-ap-b4-auth-save.php.
 *
 * Run: node tests/test-ap-b4-login-register.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

(async () => {
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm(), text: { authFieldsMissing: 'A %1$s form needs these fields: %2$s.' } });
  const { page } = h;
  const r = await page.evaluate(async () => {
    const out = {};
    const t = efbBuilderApi.newItems('text', { label: 'Company' });
    efbBuilderApi.insertItems(t);
    await efbBuilderApi.render();
    show_setting_window_efb('formSet');
    await new Promise((r) => setTimeout(r, 200));
    const opt = document.querySelector('#formTypeEl option[value="register"]');
    out.registerEnabled = !!opt && !opt.disabled;

    const res = await efbBuilderApi.setFormType('login');
    out.login = { res, type: valj_efb[0].type, order: valj_efb.slice(1).map((x) => x.id_ === t[0].id_ ? 'company' : x.id_), req: valj_efb.filter((x) => /login$/.test(x.id_)).map((x) => [x.type, x.required]),
      canvas: !!document.querySelector('[data-id="emaillogin-id"]') && !!document.querySelector('[data-id="passwordlogin-id"]') };
    const again = await efbBuilderApi.setFormType('login');
    out.idempotent = again.added.length === 0 && valj_efb.filter((x) => x.id_ === 'emaillogin').length === 1;

    const reg = await efbBuilderApi.setFormType('register', { removeAuthFields: true });
    out.register = { reg, ids: valj_efb.slice(1).map((x) => x.id_), emailTo: valj_efb[0].email_to, noti: (valj_efb.find((x) => x.id_ === 'emailRegisterEFB') || {}).noti,
      types: ['usernameRegisterEFB', 'passwordRegisterEFB', 'emailRegisterEFB'].map((id) => (valj_efb.find((x) => x.id_ === id) || {}).type) };
    try { efbBuilderApi.setFormType('nope'); out.bad = 'no error'; } catch (e) { out.bad = e.code; }
    return out;
  });
  c.check('the register option is enabled', r.registerEnabled);
  c.check('setFormType("login") adds emaillogin and passwordlogin after the step-1 heading', r.login.type === 'login' && r.login.order.join(',') === '1,emaillogin,passwordlogin,company', r.login);
  c.check('the login fields are required text / password fields, drawn on the canvas', JSON.stringify(r.login.req) === '[["text",1],["password",1]]' && r.login.canvas, r.login);
  c.check('setting the same type again adds nothing', r.idempotent);
  c.check('register (removing the login fields): username, password, email; email_to and noti set',
    r.register.ids.join(',') === '1,usernameRegisterEFB,passwordRegisterEFB,emailRegisterEFB,' + r.register.ids[4] && !r.register.ids.includes('emaillogin') && r.register.emailTo === 'emailRegisterEFB' && r.register.noti === 1
    && r.register.types.join(',') === 'text,password,email', r.register);
  c.check('unknown type: invalid_type', r.bad === 'invalid_type', r.bad);

  // The settings-panel select (what the owner does), then back to "form": asked, then removed.
  const ui = await page.evaluate(async () => {
    const out = {};
    await efbBuilderApi.setFormType('form', { removeAuthFields: true });
    efbBuilderApi.openSettings('formSet');
    await new Promise((r) => setTimeout(r, 200));
    let sel = document.getElementById('formTypeEl');
    sel.value = 'login'; sel.dispatchEvent(new Event('change'));
    for (let i = 0; i < 60 && !valj_efb.some((x) => x.id_ === 'passwordlogin'); i++) await new Promise((r) => setTimeout(r, 50));
    out.added = ['emaillogin', 'passwordlogin'].every((id) => valj_efb.some((x) => x.id_ === id)) && valj_efb[0].type === 'login';
    await new Promise((r) => setTimeout(r, 500));
    sel = document.getElementById('formTypeEl');
    sel.value = 'form'; sel.dispatchEvent(new Event('change'));
    for (let i = 0; i < 60 && !document.getElementById('modalConfirmBtnEfb'); i++) await new Promise((r) => setTimeout(r, 50));
    out.asked = !!document.getElementById('modalConfirmBtnEfb');
    if (out.asked) document.getElementById('modalConfirmBtnEfb').click();
    for (let i = 0; i < 60 && valj_efb.some((x) => x.id_ === 'emaillogin'); i++) await new Promise((r) => setTimeout(r, 50));
    out.removed = !valj_efb.some((x) => x.id_ === 'emaillogin' || x.id_ === 'passwordlogin') && valj_efb[0].type === 'form';
    return out;
  });
  c.check('choosing "login" in the form settings adds the fields', ui.added, ui);
  c.check('going back to "form" asks before removing them', ui.asked, ui);
  c.check('and removes them when confirmed', ui.removed, ui);

  // Save refuses a login form without its fields.
  const save = await page.evaluate(async () => {
    await efbBuilderApi.setFormType('login', { render: false });
    efbBuilderApi.removeItems('passwordlogin');
    const missing = efb_auth_fields_missing_efb(valj_efb);
    let sent = 0;
    actionSendData_emsFormBuilder = () => { sent++; };
    saveFormEfb(0);
    await new Promise((r) => setTimeout(r, 2500));
    const text = (document.getElementById('settingModalEfb-body') || {}).textContent || '';
    return { missing, sent, text };
  });
  c.check('Save on a login form without passwordlogin is refused with a clear message', JSON.stringify(save.missing) === '["passwordlogin"]' && save.sent === 0 && /passwordlogin/.test(save.text), save);
  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})();
