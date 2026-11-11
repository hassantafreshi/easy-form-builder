/**
 * AutoPilot A1: the country -> state link must survive a save.
 *
 * After Save every `amount` comes back from the server as a string, so
 * fun_check_link_state_efb() compared "10" > "9" as text (false) and never
 * found the state list that follows a single-digit country: picking another
 * country left the old provinces on screen. The city link already compared
 * numbers. This test lifts both functions out of the shipped pro_els-efb.js
 * and runs them against saved-shape rows (string amounts) for 9/10, 99/100
 * and numeric amounts, with stubs for the DOM and the fetch.
 *
 * Run: node tests/test-ap-a1-geo-link-order.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/pro_els-efb.js'), 'utf8');

function lift(startToken, endToken) {
  const s = src.indexOf(startToken);
  const e = src.indexOf(endToken, s);
  if (s < 0 || e < 0) throw new Error('cannot find ' + startToken);
  return src.slice(s, e);
}
const stateSrc = lift('fun_check_link_state_efb=async', '\nasync function callFetchStatesPovEfb');
const citySrc = lift('const fun_check_link_city_efb = async', '\n  const row = valj_efb[indx_state];');

let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

function makeEnv(rows) {
  const env = { valj_efb: rows, form_ID_emsFormBuilder: 5, calls: [] };
  // efbOrderOf() (A2) lives in new-efb.js, which the builder and the public page load before
  // pro_els-efb.js runs its link code; lift it when present so both versions of the file work.
  const helper = (fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/new-efb.js'), 'utf8')
    .match(/\/\* efbOrderOf:start \*\/[\s\S]*?\/\* efbOrderOf:end \*\//) || [''])[0];
  const run = new Function('env', `
    ${helper}
    let valj_efb = env.valj_efb;
    let form_ID_emsFormBuilder = env.form_ID_emsFormBuilder;
    const fun_valj_efb_run = async () => valj_efb;
    const document = { getElementById: () => null };
    const callFetchStatesPovEfb = async (id, iso, indx) => { env.calls.push({ id, iso, indx }); };
    let fun_check_link_state_efb;
    ${stateSrc}
    ${citySrc}
      env.city = { indx_state, id_city };
    };
    return { state: fun_check_link_state_efb, city: fun_check_link_city_efb };
  `);
  return Object.assign(env, run(env));
}

// A saved form: 8 fields before the country (amounts 1..8), so country = 9, state = 10, city = 11.
function savedRows(countryAmount, stateAmount, cityAmount, asString) {
  const v = (n) => (asString ? String(n) : n);
  const rows = [{ type: 'form', steps: '1' }, { type: 'step', id_: '1', step: v(1), amount: v(0) }];
  for (let i = 1; i < countryAmount; i++) rows.push({ id_: 'f' + i, type: 'text', step: v(1), amount: v(i) });
  rows.push({ id_: 'cnt', type: 'conturyList', step: v(1), amount: v(countryAmount) });
  rows.push({ id_: 'o1', parent: 'cnt', type: 'option', id_op: 'gb', step: v(1), amount: v(countryAmount) });
  rows.push({ id_: 'o2', parent: 'cnt', type: 'option', id_op: 'de', step: v(1), amount: v(countryAmount) });
  rows.push({ id_: 'st', type: 'stateProvince', country: 'GB', step: v(1), amount: v(stateAmount) });
  rows.push({ id_: 's1', parent: 'st', type: 'option', step: v(1), amount: v(stateAmount) });
  rows.push({ id_: 'ct', type: 'cityList', country: 'GB', statePov: 'x', step: v(1), amount: v(cityAmount) });
  return rows;
}

(async () => {
  for (const [c, s, t] of [[9, 10, 11], [99, 100, 101], [1, 2, 3]]) {
    for (const asString of [true, false]) {
      const rows = savedRows(c, s, t, asString);
      const env = makeEnv(rows);
      const indx = rows.findIndex((r) => r.id_ === 'cnt');
      await env.state('de', indx, 5);
      const label = `country ${c} -> state ${s} (${asString ? 'saved strings' : 'numbers'})`;
      check(label + ': state list is reloaded for the new country', env.calls.length === 1 && env.calls[0].iso === 'de' && env.calls[0].id === 'st_options', env.calls);
      check(label + ': state row now carries the new country', rows.find((r) => r.id_ === 'st').country === 'de');
      check(label + ': old state options removed', !rows.some((r) => r.parent === 'st'));

      const env2 = makeEnv(savedRows(c, s, t, asString));
      const rows2 = env2.valj_efb;
      const sIndx = rows2.findIndex((r) => r.id_ === 'st');
      await env2.city('de', 'by', sIndx, 5);
      check(`state ${s} -> city ${t} (${asString ? 'saved strings' : 'numbers'}): city list is found`, env2.city && env2.city.id_city === 'ct', env2.city);
    }
  }

  // A state list placed BEFORE the country is not the country's state list.
  const rows = [{ type: 'form' }, { type: 'step', id_: '1', amount: '0' },
    { id_: 'st', type: 'stateProvince', country: 'GB', amount: '1' },
    { id_: 'cnt', type: 'conturyList', amount: '10' }];
  const env = makeEnv(rows);
  await env.state('de', 3, 5);
  check('no state list after the country: nothing is fetched', env.calls.length === 0, env.calls);

  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})();
