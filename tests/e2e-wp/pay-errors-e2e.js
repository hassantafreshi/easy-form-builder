/**
 * Not a scenario. Helpers of the payment error checks (docs/payments/payment-error-matrix.en.md) shared
 * by the gateway scenarios of run.js and scenario-*.js.
 */
'use strict';

/** Save the open form the way the builder does and return the save answer's warnings (what AutoPilot reads). */
async function saveWarnings(page) {
  const resp = page.waitForResponse((r) => /admin-ajax\.php/.test(r.url()) && r.request().method() === 'POST' && /action=(add|update)_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.evaluate(() => saveFormEfb(1));
  try {
    const j = await (await resp).json();
    return (j && j.data && j.data.warnings) || [];
  } catch (e) {
    return null;
  }
}

/** The payment warnings of a save answer: every one has code, m, level; nothing red unless expected. */
function payWarnings(list) {
  return (list || []).filter((w) => w && typeof w.code === 'string' && w.code.indexOf('pay_') === 0);
}

/** The panel's live warnings (code, level, text). */
async function panelWarnings(page) {
  return page.evaluate(() => Array.from(document.querySelectorAll('#efbPfWarnEl li')).map((l) => ({ code: l.getAttribute('data-code'), level: l.getAttribute('data-level'), m: l.textContent })));
}

/** The message a payment check drew at a form field (its own slot), and the input's state. */
async function fieldMessage(page, id) {
  return page.evaluate((i) => {
    const s = document.getElementById(i + '_-message');
    const el = document.querySelector('[data-id="' + i + '-el"]') || document.getElementById(i + '_');
    return { text: s ? s.textContent : '', role: s && s.getAttribute('role'), live: s && s.getAttribute('aria-live'), shown: !!(s && s.style.display === 'block'), dir: s ? getComputedStyle(s).direction : '',
      invalid: el && el.getAttribute('aria-invalid'), described: !!(el && (el.getAttribute('aria-describedby') || '').indexOf(i + '_-message') !== -1), focus: document.activeElement === el };
  }, id);
}

/** Counts the page's payments/create requests from now on. */
function countCreates(page) {
  const n = { count: 0 };
  page.on('request', (r) => { if (/payments\/create/.test(r.url())) n.count++; });
  return n;
}

module.exports = { saveWarnings, payWarnings, panelWarnings, fieldMessage, countCreates };
