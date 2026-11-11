/**
 * AutoPilot B1: window.efbBuilderApi (version 1) in the real builder.
 *
 * Acceptance (brief B1): with the settings panel of a field open, insertItems() + render() +
 * refreshSettingsPanel() shows the new values, the panel stays open, and no guessed timer is needed
 * (render() resolves when the canvas is really drawn, mobile fields included).
 * Also: getForm() is a deep copy, setForm() replaces and renders, insertItems() places rows and
 * numbers them by the A2 rule and refuses duplicate ids, removeItems(), openSettings()/
 * closeSettings(), and the efb:form-changed / efb:canvas-rendered events for 'api' and 'user'.
 *
 * Run: node tests/test-ap-b1-builder-api.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

function textRow(id, name, extra) {
  return Object.assign({ id_: id, dataId: id + '-id', type: 'text', elementId: 'text', name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
    amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d',
    el_text_color: 'text-labelEfb', message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start',
    el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});
}

(async () => {
  const form = blankForm();
  form.push(textRow('aaa111aaa', 'Name', { amount: '2', step: '1' }));
  form.push(Object.assign(textRow('rad111rad', 'Pick', { amount: '3', step: '1' }), { type: 'radio', elementId: 'radio' }));
  form.push({ id_: 'op1', dataId: 'op1-id', parent: 'rad111rad', type: 'option', value: 'One', id_op: 'op1', step: '1', amount: '3' });
  form.push(Object.assign(textRow('mob111mob', 'Mobile', { amount: '4', step: '1' }), { type: 'mobile', elementId: 'mobile', c_c: [] }));
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form, scripts: ['includes/admin/assets/js/intlTelInput.min-efb.js'] });
  const { page } = h;
  c.check('window.efbBuilderApi exists with version 1', await page.evaluate(() => typeof efbBuilderApi === 'object' && efbBuilderApi.version === 1));
  await page.evaluate(() => {
    window.__ev = [];
    document.addEventListener('efb:form-changed', (e) => __ev.push(['changed', e.detail.source, e.detail.action, (e.detail.ids || []).join(',')]));
    document.addEventListener('efb:canvas-rendered', (e) => __ev.push(['rendered', e.detail.source, e.detail.complete]));
  });

  // render(): resolves when the canvas (mobile field included) is drawn.
  const r1 = await page.evaluate(async () => {
    const t = Date.now();
    const d = await efbBuilderApi.render();
    return { d, ms: Date.now() - t, field: !!document.querySelector('[data-id="aaa111aaa-id"]'), iti: !!document.querySelector('.iti #mob111mob_') };
  });
  c.check('render() resolves only when every field is on the canvas', r1.d.complete === true && r1.field, r1);
  c.check('render() waits for the mobile field to attach intlTelInput', r1.iti, r1);
  c.check('render() sends efb:canvas-rendered (api)', await page.evaluate(() => __ev.some((e) => e[0] === 'rendered' && e[1] === 'api' && e[2] === true)));

  // getForm(): deep copy, amounts already numbers (A2).
  const g = await page.evaluate(() => { const f = efbBuilderApi.getForm(); f[2].name = 'MUTATED'; return { live: valj_efb[2].name, amount: typeof efbBuilderApi.getForm()[2].amount }; });
  c.check('getForm() is a deep copy', g.live === 'Name', g);
  c.check('loaded amounts are numbers', g.amount === 'number', g);

  // Open the panel of the text field; openSettings twice must not close it.
  await page.evaluate(() => efbBuilderApi.openSettings('aaa111aaa'));
  await page.waitForTimeout(150);
  await page.evaluate(() => efbBuilderApi.openSettings('aaa111aaa'));
  await page.waitForTimeout(150);
  c.check('openSettings() opens, and a second call keeps it open (no toggle)', await page.evaluate(() => document.getElementById('sideBoxEfb').classList.contains('show') && document.getElementById('labelEl').value === 'Name' && window.efbOpenSettingIdEfb === 'aaa111aaa-id'));

  // Acceptance: insertItems + render + refreshSettingsPanel with the panel open.
  const acc = await page.evaluate(async () => {
    __ev.length = 0;
    const f = efbBuilderApi.getForm();
    f.find((r) => r.id_ === 'aaa111aaa').name = 'Full name';
    await efbBuilderApi.setForm(f, { render: false });
    const res = efbBuilderApi.insertItems([
      { id_: 'new111new', dataId: 'new111new-id', type: 'text', elementId: 'text', name: 'Company', placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0, amount: 99, step: 1,
        label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', message_text_color: 'text-muted',
        el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' },
    ], { after: 'aaa111aaa' });
    const rendered = await efbBuilderApi.render();
    const refreshed = efbBuilderApi.refreshSettingsPanel();
    await new Promise((r) => setTimeout(r, 120));
    return {
      res, rendered, refreshed,
      open: document.getElementById('sideBoxEfb').classList.contains('show'),
      label: document.getElementById('labelEl') && document.getElementById('labelEl').value,
      order: valj_efb.slice(1).map((r) => r.id_ + ':' + r.amount + ':' + r.step).join(' '),
      canvasNew: !!document.querySelector('[data-id="new111new-id"]'),
    };
  });
  c.check('insertItems() returns the inserted ids', acc.res && acc.res.ids && acc.res.ids[0] === 'new111new', acc.res);
  c.check('the new field is on the canvas after render()', acc.canvasNew && acc.rendered.complete, acc);
  c.check('refreshSettingsPanel() redraws the open panel with the new value', acc.refreshed.refreshed === true && acc.label === 'Full name', acc);
  c.check('the panel stays open', acc.open);
  c.check('rows placed after the target and numbered by the rule (options share the parent amount)',
    acc.order === '1:1:1 aaa111aaa:2:1 new111new:3:1 rad111rad:4:1 op1:4:1 mob111mob:5:1', acc.order);
  const ev = await page.evaluate(() => __ev.slice());
  c.check('API changes send efb:form-changed with source "api"', ev.some((e) => e[0] === 'changed' && e[1] === 'api' && e[2] === 'insertItems' && e[3] === 'new111new') && ev.some((e) => e[2] === 'setForm'), ev);

  // Errors.
  const errs = await page.evaluate(() => {
    const out = {};
    const t = (k, fn) => { try { fn(); out[k] = 'no error'; } catch (e) { out[k] = e.code; } };
    t('dup', () => efbBuilderApi.insertItems([{ id_: 'aaa111aaa', type: 'text' }]));
    t('dupInside', () => efbBuilderApi.insertItems([{ id_: 'x1', type: 'text' }, { id_: 'x1', type: 'text' }]));
    t('target', () => efbBuilderApi.insertItems([{ id_: 'x2', type: 'text' }], { after: 'nope' }));
    t('orphan', () => efbBuilderApi.insertItems([{ id_: 'x3', type: 'option', parent: 'nope' }]));
    t('step', () => efbBuilderApi.insertItems([{ id_: 'x4', type: 'text' }], { step: 9 }));
    t('form', () => efbBuilderApi.setForm([{ id_: 'x' }]));
    t('badId', () => efbBuilderApi.insertItems([{ id_: 'a b"', type: 'text' }]));
    out.unchanged = valj_efb.length;
    return out;
  });
  c.check('errors have stable codes and leave the form unchanged', errs.dup === 'duplicate_id' && errs.dupInside === 'duplicate_id' && errs.target === 'unknown_target' && errs.orphan === 'orphan_option'
    && errs.step === 'unknown_step' && errs.form === 'invalid_form' && errs.badId === 'invalid_id' && errs.unchanged === 7, errs);

  // before / step / option inserted under an existing parent.
  const pos = await page.evaluate(([bef, end]) => {
    efbBuilderApi.insertItems([bef], { before: 'rad111rad' });
    efbBuilderApi.insertItems([{ id_: 'op2', type: 'option', parent: 'rad111rad', value: 'Two', id_op: 'op2' }], { after: 'rad111rad' });
    efbBuilderApi.insertItems([end], { step: 1 });
    return valj_efb.slice(1).map((r) => r.id_ + ':' + r.amount).join(' ');
  }, [textRow('bef1', 'B'), textRow('end1', 'E')]);
  c.check('before / after a field with options / end of a step', pos === '1:1 aaa111aaa:2 new111new:3 bef1:4 rad111rad:5 op1:5 op2:5 mob111mob:6 end1:7', pos);

  // A field removed while its panel is open: refresh closes the panel.
  const closed = await page.evaluate(async () => {
    let early = null;
    try { efbBuilderApi.openSettings('bef1'); } catch (e) { early = e.code; }
    await efbBuilderApi.render();
    efbBuilderApi.openSettings('bef1');
    await new Promise((r) => setTimeout(r, 120));
    const open1 = document.getElementById('sideBoxEfb').classList.contains('show');
    efbBuilderApi.removeItems(['bef1']);
    await efbBuilderApi.render();
    const r = efbBuilderApi.refreshSettingsPanel();
    return { early, open1, r, open2: document.getElementById('sideBoxEfb').classList.contains('show'), gone: !valj_efb.some((x) => x.id_ === 'bef1'), dataId: null };
  });
  c.check('openSettings() before render() says not_rendered instead of opening an empty panel', closed.early === 'not_rendered', closed);
  c.check('refreshSettingsPanel() closes the panel of a removed field', closed.open1 && closed.r.closed === true && !closed.open2 && closed.gone, closed);
  c.check('removeItems() removes a field with its options', await page.evaluate(() => { efbBuilderApi.removeItems('rad111rad'); return !valj_efb.some((r) => r.id_ === 'rad111rad' || r.parent === 'rad111rad'); }));
  c.check('changes are written to sessionStorage.valj_efb (what Save and editFormEfb read)', await page.evaluate(() => sessionStorage.getItem('valj_efb') === JSON.stringify(valj_efb)));

  // A change made by the user in the panel is reported with source "user".
  const user = await page.evaluate(async () => {
    await efbBuilderApi.render();
    efbBuilderApi.openSettings('formSet');
    await new Promise((r) => setTimeout(r, 150));
    __ev.length = 0;
    const el = document.getElementById('formNameEl');
    el.value = 'Typed by the owner';
    el.dispatchEvent(new Event('change', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 700));
    return { ev: __ev.slice(), name: valj_efb[0].formName };
  });
  c.check('a user edit in the panel sends efb:form-changed with source "user"', user.ev.some((e) => e[0] === 'changed' && e[1] === 'user'), user);
  c.check('closeSettings() closes the panel', await page.evaluate(() => { efbBuilderApi.closeSettings(); return !document.getElementById('sideBoxEfb').classList.contains('show') && window.efbOpenSettingIdEfb === null; }));
  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
  c.done();
})();
