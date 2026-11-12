/* Not a test. Runs a batch of cases through tests/pay-core-cli.php and the JS core, for the parity tests. */
'use strict';
const { execFileSync } = require('child_process');
const path = require('path');
const core = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'payment-core-efb.js'));
function php(cases) {
  const out = execFileSync('php', [path.join(__dirname, 'pay-core-cli.php')], { input: JSON.stringify({ cases }), maxBuffer: 64 * 1024 * 1024 });
  return JSON.parse(out.toString('utf8'));
}
function js(c) {
  switch (c.op) {
    case 'sanitize': return core.sanitizeKey(c.k, c.v);
    case 'field': {
      const r = core.resolve(c.row, c.form0, c.opts || {});
      const t = core.texts(c.row, c.locale, c.defaults);
      return { resolved: r, texts: t, css: core.css(r, c.ctx.id), html: core.html(r, t, c.ctx) };
    }
    case 'quote': return core.quote(c.form, c.rows, c.ctx);
    case 'money': return core.fromDecimal(c.value, c.currency);
    case 'tominor': return core.toDecimal(c.minor, c.currency);
  }
}
/* PHP encodes an empty associative array as []; JS has {} there. Normalise both through JSON. */
function norm(v) {
  return JSON.stringify(v, (k, x) => (x && typeof x === 'object' && !Array.isArray(x) && Object.keys(x).length === 0 ? [] : x));
}
module.exports = { core, php, js, norm };
