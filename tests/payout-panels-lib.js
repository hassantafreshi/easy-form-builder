/**
 * Not a test. PAY-OUT §1: draws the builder settings panel of a Stripe / PayPal / Persia Pay field in a Node vm, the
 * way the builder page does, either with a core val-efb.js from before PAY-OUT (its own panels) or with the add-ons'
 * window.efbFieldPanels. Used by tests/generate-payout-panels.js (the baseline fixture) and
 * tests/test-payout-builder-panels.js.
 */
'use strict';
const vm = require('vm');

/** One top-level `const name = …;` / `const name=(…)=>{…}` declaration of a script, by brace matching. */
function topConst(src, name) {
  const re = new RegExp('(^|\\n)const\\s+' + name + '\\s*=');
  const m = re.exec(src);
  if (!m) return null;
  const start = m.index + m[1].length;
  let i = src.indexOf('=', start) + 1;
  let depth = 0, inStr = null, esc = false;
  for (; i < src.length; i++) {
    const c = src[i];
    if (inStr) {
      if (esc) { esc = false; continue; }
      if (c === '\\') { esc = true; continue; }
      if (c === inStr) inStr = null;
      continue;
    }
    if (c === '"' || c === "'" || c === '`') { inStr = c; continue; }
    if (c === '{' || c === '(' || c === '[') depth++;
    if (c === '}' || c === ')' || c === ']') depth--;
    if (depth === 0 && (c === ';' || (c === '\n' && /[}\])]\s*$/.test(src.slice(start, i))))) return src.slice(start, i + 1);
  }
  return null;
}

function stubs(row0, lang) {
  const text = {
    stripe: 'Stripe', paypal: 'PayPal', paymentGateway: 'Payment gateway', currency: 'Currency', methodPayment: 'Payment method',
    onetime: 'One time', dayly: 'Daily', weekly: 'Weekly', monthly: 'Monthly', yearly: 'Yearly', addonUpdateNeeded: 'Update %s to keep using it with this version.',
  };
  return { valj_efb: [Object.assign({ type: 'payment', currency: 'usd', paymentmethod: 'charge' }, row0 || {})], efb_var: { text, rtl: lang === 'fa_IR' ? 1 : 0, language: lang || 'en_US', addon_catalog: {} } };
}

/** The panel of `type` drawn by an OLD core's val-efb.js source (its own switch cases). */
function oldCorePanel(valSrc, type, row0, lang) {
  const caseRe = new RegExp("case '" + type + "':\\s*\\n\\s*body = `([\\s\\S]*?)`\\s*\\n\\s*break;");
  const m = caseRe.exec(valSrc);
  if (!m) throw new Error('case ' + type + ' not found in the old val-efb.js');
  const ctx = stubs(row0, lang);
  ctx.iconMarginGlobal = ctx.efb_var.rtl == 1 ? 'ms-2' : 'me-2';
  const helpers = ['currency_efb', 'currency_paypal_efb', 'currencyTypeEls', 'currencyPaypalTypeEls', 'paymentMethodEls', 'paymentPersianPayEls']
    .map((n) => topConst(valSrc, n)).filter(Boolean).join('\n');
  const persia = /const currencyPersianPayEls\s*=\s*(`[\s\S]*?`)/.exec(valSrc);
  const code = helpers + '\nconst currencyPersianPayEls = ' + (persia ? persia[1] : "''") + ';\nconst idset = "efbIdset";\nout = `' + m[1] + '`;';
  vm.createContext(ctx);
  vm.runInContext(code, ctx);
  return ctx.out;
}

/** The panel drawn by a NEW core with an add-on's builder script (window.efbFieldPanels[type]). */
function addonPanel(valSrc, addonSrc, type, row0, lang) {
  const ctx = stubs(row0, lang);
  ctx.iconMarginGlobal = ctx.efb_var.rtl == 1 ? 'ms-2' : 'me-2';
  ctx.window = ctx;
  const helpers = ['currency_efb', 'currencyTypeEls'].map((n) => topConst(valSrc, n)).filter(Boolean).join('\n');
  vm.createContext(ctx);
  vm.runInContext(helpers + '\n' + addonSrc + '\nout = window.efbFieldPanels[' + JSON.stringify(type) + '](1, "efbIdset", {dataset:{tag:' + JSON.stringify(type) + '}});', ctx);
  return ctx.out;
}

/** The panel a NEW core draws without the add-on's script (efbLegacyGatewayPanelEfb). */
function noticePanel(valSrc, type, state, lang) {
  const ctx = stubs({}, lang);
  ctx.efb_var.legacy_gw = { types: { [type]: { code: type === 'paypal' ? 'AdnPAP' : (type === 'stripe' ? 'AdnSPF' : 'AdnPPF'), name: type === 'paypal' ? 'PayPal Payment' : (type === 'stripe' ? 'Stripe Payment' : 'Persia Payment'), state } } };
  ctx.efb_addon_needed_html_efb = (code) => '<div class="efb alert alert-warning efb-addon-needed" data-efb-addon="' + code + '">Needs the ' + code + ' add-on <button>Install</button></div>';
  vm.createContext(ctx);
  const a = valSrc.indexOf('const efbLegacyGatewayPanelEfb = ');
  const b = valSrc.indexOf('\n};', a);
  if (a === -1 || b === -1) throw new Error('efbLegacyGatewayPanelEfb not found');
  vm.runInContext(valSrc.slice(a, b + 3) + '\nout = efbLegacyGatewayPanelEfb(' + JSON.stringify(type) + ');', ctx);
  return ctx.out;
}

/** Markup compared without the whitespace between tags and inside them. */
function norm(html) {
  return String(html).replace(/<!--[\s\S]*?-->/g, '').replace(/\s+/g, ' ').replace(/>\s+</g, '><').replace(/\s+>/g, '>').trim();
}

const CASES = [
  ['stripe', {}, 'en_US'], ['stripe', { paymentmethod: 'month', currency: 'eur' }, 'en_US'], ['stripe', { type: 'form' }, 'en_US'], ['stripe', {}, 'fa_IR'],
  ['paypal', {}, 'en_US'], ['paypal', { paymentmethod: 'year', currency: 'gbp' }, 'en_US'], ['paypal', {}, 'fa_IR'],
  ['persiaPay', { currency: 'irr', persiaPay: 'zarinPal' }, 'fa_IR'], ['persiaPay', { type: 'form', persiaPay: 'zarinPal' }, 'fa_IR'],
];

module.exports = { topConst, oldCorePanel, addonPanel, noticePanel, norm, CASES };
