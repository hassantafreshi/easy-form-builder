/**
 * AutoPilot B5 (builder): the SMS switch on a mobile field also switches the form's SMS on.
 *
 * The confirmation SMS to the person who filled the form needs smsnoti=1 on the mobile field AND
 * on the form (class-Emsfb-public.php sends only when valj_efb[0].smsnoti is 1). The switch in the
 * mobile field's panel set only the first, and then read the form-level message boxes, which are
 * not on that panel (a TypeError). Now it turns the form's SMS on too, fills the three default
 * messages when they are empty, and tells the admin. Server side: tests/test-ap-b5-sms-save.php.
 *
 * Run: node tests/test-ap-b5-sms-filler.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

(async () => {
  const text = { newMessageReceived: 'New message', trackNo: 'Tracking code', url: 'URL', newResponse: 'New response', WeRecivedUrM: 'We received your message', smsOnForForm: 'SMS ON' };
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, setting: { AdnSS: 1 }, form: blankForm(), text });
  const { page } = h;
  const r = await page.evaluate(async () => {
    window.__alerts = [];
    const real = alert_message_efb;
    alert_message_efb = function (t, m) { __alerts.push(String(m)); };
    const rows = efbBuilderApi.newItems('mobile', { label: 'Mobile' });
    efbBuilderApi.insertItems(rows);
    await efbBuilderApi.render();
    const id = rows[0].id_;
    efbBuilderApi.openSettings(id);
    await new Promise((r) => setTimeout(r, 200));
    const btn = document.getElementById('smsEnableEl');
    btn.classList.add('active');
    change_el_edit_Efb(btn);
    await new Promise((r) => setTimeout(r, 300));
    return { field: valj_efb.find((x) => x.id_ === id).smsnoti, form: valj_efb[0].smsnoti,
      texts: [valj_efb[0].sms_msg_new_noti, valj_efb[0].sms_msg_recived_usr, valj_efb[0].sms_msg_responsed_noti], alerts: __alerts.slice() };
  });
  c.check('mobile field: smsnoti = 1', Number(r.field) === 1, r);
  c.check('the form\'s SMS is switched on too (valj_efb[0].smsnoti = 1)', Number(r.form) === 1, r);
  c.check('the three SMS messages get their defaults', r.texts[0] === 'New message\nTracking code: [confirmation_code]\nURL: [link_response]'
    && r.texts[1] === 'We received your message\nTracking code: [confirmation_code]\nURL: [link_response]' && r.texts[2] === 'New response\nTracking code: [confirmation_code]\nURL: [link_response]', r.texts);
  c.check('the admin is told', r.alerts.includes('SMS ON'), r.alerts);
  // A message the admin already wrote is kept.
  const kept = await page.evaluate(async () => {
    valj_efb[0].sms_msg_new_noti = 'Mine';
    valj_efb[0].smsnoti = 0;
    const btn = document.getElementById('smsEnableEl');
    btn.classList.add('active');
    change_el_edit_Efb(btn);
    await new Promise((r) => setTimeout(r, 300));
    return [valj_efb[0].sms_msg_new_noti, valj_efb[0].smsnoti];
  });
  c.check('a message already written is kept', kept[0] === 'Mine' && Number(kept[1]) === 1, kept);
  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})();
