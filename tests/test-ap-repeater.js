/**
 * F09 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi):
 *  - addGroup(): a repeater group with its settings (save rules: Persian digits, limits), placed after a field,
 *    fields moved into it (opts.fields); moveIntoGroup() / moveOutOfGroup() change membership and keep the order
 *    "group, its fields and their options"; setGroup() writes a group's settings and refuses others;
 *  - insertItems() / setForm() take `group` on a field: a child of a group is placed right after it with the
 *    group's step; refused: group naming a field that is not a group (repeater_group), a type a row may not hold
 *    (repeater_child_type), a group inside a group (repeater_nested), max 0 / min > max / a start outside
 *    [min, max] (repeater_invalid), runtime keys rpt_* (repeater_invalid), more than 10 groups (repeater_cap);
 *  - removeItems() of a group removes its fields and their options;
 *  - capabilities: field:repeater Free Plus, setting:repeater_rows Pro, capabilities.repeater (keys, caps,
 *    child types, wire shape);
 *  - the canvas draws the group's fields inside its container after a render.
 *
 * Run: node tests/test-ap-repeater.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const base = (id, type, name, extra) => Object.assign({ id_: id, dataId: id + '-id', type, elementId: type, name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
  amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});

(async () => {
  const form = blankForm();
  form.push(base('cnt', 'number', 'Count', { amount: '2' }));
  form.push(base('nm', 'text', 'Name', { amount: '3' }));
  form.push(base('ml', 'select', 'Meal', { amount: '4' }));
  form.push({ id_: 'ml1', dataId: 'ml1-id', parent: 'ml', type: 'option', value: 'Vegan', id_op: 'ml1', step: 1, amount: '5' });
  form.push(base('pw', 'password', 'Secret', { amount: '6' }));
  form.push(base('after', 'email', 'E-mail', { amount: '7' }));
  const caps = capabilities(1);
  const h = await openBuilder({ builder: true, pro: 1, packageType: 3, form, scripts: ['public/assets/js/repeater-efb.js', 'includes/admin/assets/js/repeater-builder-efb.js'],
    extraVar: { capabilities: caps, repeater: { plan: { group: true, repeat: true }, caps: { rows: 50, children: 25, groups: 10 } } } });
  const { page } = h;
  const r = await page.evaluate(() => {
    const o = {};
    const tryit = (f) => { try { const v = f(); return v === undefined ? 'ok' : v; } catch (e) { return { code: e.code, path: e.path, message: e.message }; } };
    const ids = () => valj_efb.slice(1).map((x) => x.id_);
    o.add = tryit(() => efbBuilderApi.addGroup({ id_: 'trv', name: 'Travellers', max_rows: '۴', default_rows: '2', row_label: 'Traveller {n}', rows_from: 'cnt' }, { after: 'cnt', fields: ['nm', 'ml'] }));
    o.g = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'trv')));
    o.order1 = ids();
    o.kids1 = valj_efb.filter((x) => x.group === 'trv').map((x) => x.id_ + '@' + x.step);
    o.pwIn = tryit(() => efbBuilderApi.moveIntoGroup(['pw'], 'trv'));
    o.noGroup = tryit(() => efbBuilderApi.moveIntoGroup(['after'], 'cnt'));
    o.out = tryit(() => efbBuilderApi.moveOutOfGroup(['ml']));
    o.order2 = ids();
    o.notIn = tryit(() => efbBuilderApi.moveOutOfGroup(['after']));
    o.back = tryit(() => efbBuilderApi.moveIntoGroup(['ml'], 'trv'));
    o.order3 = ids();
    o.set = tryit(() => efbBuilderApi.setGroup('trv', { min_rows: '2', collapsible: true, name: 'Guests' }));
    o.setBad = tryit(() => efbBuilderApi.setGroup('trv', { max_rows: '0' }));
    o.setMin = tryit(() => efbBuilderApi.setGroup('trv', { min_rows: '9' }));
    o.setKey = tryit(() => efbBuilderApi.setGroup('trv', { formula: '1' }));
    o.g2 = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'trv')));
    // insertItems with group: placed right after the group's fields, the group's step.
    o.ins = tryit(() => efbBuilderApi.insertItems([{ id_: 'age', type: 'number', name: 'Age', group: 'trv', step: 3 }]));
    o.order4 = ids();
    o.age = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'age')));
    o.insBadGroup = tryit(() => efbBuilderApi.insertItems([{ id_: 'z1', type: 'text', name: 'Z', group: 'cnt' }]));
    o.insBadType = tryit(() => efbBuilderApi.insertItems([{ id_: 'z2', type: 'password', name: 'Z', group: 'trv' }]));
    o.insNested = tryit(() => efbBuilderApi.insertItems([{ id_: 'z3', type: 'repeater', name: 'Z', group: 'trv' }]));
    o.insMax0 = tryit(() => efbBuilderApi.insertItems([{ id_: 'z4', type: 'repeater', name: 'Z', max_rows: '0' }]));
    o.insStart = tryit(() => efbBuilderApi.insertItems([{ id_: 'z5', type: 'repeater', name: 'Z', min_rows: '1', max_rows: '3', default_rows: '5' }]));
    o.insRuntime = tryit(() => efbBuilderApi.insertItems([{ id_: 'z6', type: 'text', name: 'Z', rpt_base: 'nm' }]));
    o.untouched = !valj_efb.some((x) => /^z\d$/.test(x.id_));
    // setForm: the same rules, children placed after their group.
    const f = JSON.parse(JSON.stringify(valj_efb.slice(0, 2)));
    f.push({ id_: 'kid', type: 'text', name: 'Kid', group: 'g9', step: 1 }, { id_: 'g9', type: 'repeater', name: 'G9', step: 1 }, { id_: 'solo', type: 'text', name: 'Solo', step: 1 });
    const saved = JSON.stringify(valj_efb);
    o.sf = tryit(() => { efbBuilderApi.setForm(f, { render: false }); return 'set'; });
    o.sfOrder = ids();
    const many = JSON.parse(JSON.stringify(valj_efb.slice(0, 2)));
    for (let i = 0; i < 11; i++) many.push({ id_: 'gg' + i, type: 'repeater', name: 'G' + i, step: 1 }, { id_: 'kk' + i, type: 'text', name: 'K', group: 'gg' + i, step: 1 });
    o.sfCap = tryit(() => efbBuilderApi.setForm(many, { render: false }));
    valj_efb = JSON.parse(saved);
    o.rm = tryit(() => efbBuilderApi.removeItems(['trv']));
    o.afterRm = ids();
    const cap = efb_var.capabilities;
    o.cap = { field: cap.features['field:repeater'] && cap.features['field:repeater'].package, rows: cap.features['setting:repeater_rows'] && cap.features['setting:repeater_rows'].package,
      keys: cap.repeater && cap.repeater.keys, wire: !!(cap.repeater && cap.repeater.answer && cap.repeater.builder.indexOf('efbBuilderApi.addGroup') !== -1 && cap.repeater.builder_codes.indexOf('repeater_group') !== -1), types: cap.repeater && cap.repeater.child_types && cap.repeater.child_types.indexOf('password') === -1 && cap.repeater.child_types.indexOf('text') !== -1 };
    return o;
  });
  c.check('addGroup: placed after "cnt", settings with the save rules (۴ → 4), fields moved in', r.add && r.add.id === 'trv' && r.g.max_rows === '4' && r.g.default_rows === '2' && r.g.row_label === 'Traveller {n}' && r.g.rows_from === 'cnt' && JSON.stringify(r.order1) === JSON.stringify(['1', 'cnt', 'trv', 'nm', 'ml', 'ml1', 'pw', 'after']), r);
  c.check('addGroup: the fields carry group and the group\'s step', JSON.stringify(r.kids1) === JSON.stringify(['nm@1', 'ml@1']), r.kids1);
  c.check('moveIntoGroup: a password refused (repeater_child_type); a non-group target refused', r.pwIn.code === 'repeater_child_type' && r.noGroup.code === 'unknown_target', [r.pwIn, r.noGroup]);
  c.check('moveOutOfGroup: Meal (with its option) right after the group\'s block; a field not in a group refused', JSON.stringify(r.order2) === JSON.stringify(['1', 'cnt', 'trv', 'nm', 'ml', 'ml1', 'pw', 'after']) && r.notIn.code === 'not_in_group', [r.order2, r.notIn]);
  c.check('moveIntoGroup: Meal back, after the group\'s other fields', JSON.stringify(r.order3) === JSON.stringify(['1', 'cnt', 'trv', 'nm', 'ml', 'ml1', 'pw', 'after']), r.order3);
  c.check('setGroup: settings written (collapsible true → "1"); max 0, min > max and a key that is not a group setting refused', r.set.changed && r.g2.min_rows === '2' && r.g2.collapsible === '1' && r.g2.name === 'Guests' && r.setBad.code === 'repeater_invalid' && r.setMin.code === 'repeater_invalid' && r.setKey.code === 'invalid_setting', [r.set, r.setBad, r.setMin, r.setKey]);
  c.check('insertItems with group: placed after the group\'s fields with the group\'s step', r.ins.ids && JSON.stringify(r.order4) === JSON.stringify(['1', 'cnt', 'trv', 'nm', 'ml', 'ml1', 'age', 'pw', 'after']) && String(r.age.step) === '1', [r.order4, r.age]);
  c.check('insertItems refused: group on a non-group, a password in a row, a group in a group, max 0, a start outside the limits, a runtime key', r.insBadGroup.code === 'repeater_group' && r.insBadType.code === 'repeater_child_type' && r.insNested.code === 'repeater_nested' && r.insMax0.code === 'repeater_invalid' && r.insStart.code === 'repeater_invalid' && r.insStart.path === 'rows[0].default_rows' && r.insRuntime.code === 'repeater_invalid' && r.untouched, [r.insBadGroup, r.insBadType, r.insNested, r.insMax0, r.insStart, r.insRuntime]);
  c.check('setForm: a child before its group is put right after it', r.sf === 'set' && JSON.stringify(r.sfOrder) === JSON.stringify(['1', 'g9', 'kid', 'solo']), [r.sf, r.sfOrder]);
  c.check('setForm: 11 groups refused (repeater_cap)', r.sfCap.code === 'repeater_cap', r.sfCap);
  c.check('removeItems of a group removes its fields and their options', JSON.stringify(r.afterRm) === JSON.stringify(['1', 'cnt', 'pw', 'after']), r.afterRm);
  c.check('capabilities: field:repeater Free Plus, setting:repeater_rows Pro, capabilities.repeater (keys, answer shape, builder calls and codes, child types without password)', r.cap.field === 'free_plus' && r.cap.rows === 'pro' && Array.isArray(r.cap.keys) && r.cap.keys.indexOf('max_rows') !== -1 && r.cap.wire && r.cap.types, r.cap);
  // The canvas.
  const refused = await page.evaluate(() => { let code = null; try { efbBuilderApi.addGroup({ id_: 'g2', name: 'Items' }, { fields: ['pw'] }); } catch (e) { code = e.code + '|' + e.path; } return { code, g2: valj_efb.some((x) => x.id_ === 'g2'), pw: !valj_efb.find((x) => x.id_ === 'pw').group }; });
  c.check('addGroup with a field a row may not hold: refused (fields[0]), nothing added, the field unchanged', refused.code === 'repeater_child_type|fields[0].group' && refused.g2 === false && refused.pw === true, refused);
  await page.evaluate(() => { efbBuilderApi.addGroup({ id_: 'g3', name: 'Items' }, { after: 'cnt', fields: ['after'] }); });
  await page.evaluate(() => efbBuilderApi.render());
  await page.waitForTimeout(800);
  const canvas = await page.evaluate(() => ({ inBody: Array.from(document.querySelectorAll('#g3_rptbody > section')).map((s) => s.id) }));
  c.check('the canvas draws the group\'s field inside its container', JSON.stringify(canvas.inBody) === JSON.stringify(['after']), canvas);
  c.check('no page errors', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
