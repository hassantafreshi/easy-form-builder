/**
 * F10 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi), with the save rules of
 * EFBLayoutCore (layout-efb.js) and the capability registry (tests/f18-harness.js, Layout::capabilities):
 *
 *  - insertItems(): a section (collapsible / collapsed written "1" / "0"); a rich textarea (rich_text "1",
 *    rich_toolbar full); "Show this field to" (vis_mode roles, vis_roles of this site); a typed signature
 *    (esign_mode, esign_font) — refused: rich_text on a text field, esign_mode on a text field, an unknown
 *    vis_mode / esign_font, a role the site does not have (layout_vis_role), vis_roles without "roles", the page's
 *    own marks vis_view / vis_empty (layout_invalid); a section in a repeater group; rich text below Free Plus and a
 *    visibility rule below Pro (plan_required);
 *  - setForm(): row 0 review_before_submit true → "1"; refused on a form with a Stripe field (layout_review_payment);
 *  - setFormSettings(): review keys with the save rules (title as text), a bad flag refused;
 *  - setFieldLayout(): one field's layout keys; other keys and unknown fields refused;
 *  - capabilities: setting:review_before_submit and field:section Free, setting:rich_text and setting:esign_mode
 *    Free Plus, setting:vis_mode Pro; capabilities.layout lists the keys, modes, fonts.
 *
 * Run: node tests/test-ap-layout.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const base = (id, type, name, extra) => Object.assign({ id_: id, dataId: id + '-id', type, elementId: type, name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
  amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});
const ROLES = [{ slug: 'administrator', name: 'Administrator' }, { slug: 'editor', name: 'Editor' }, { slug: 'author', name: 'Author' }, { slug: 'subscriber', name: 'Subscriber' }];

(async () => {
  // The builder's own row shape for the rows the test adds (as the field templates give them).
  const B = (id, type, name) => base(id, type, name, { amount: 50 });
  const harness = require('./ap-builder-harness.js');
  // Pro (package 1).
  const pro = await (async () => {
    const form = blankForm();
    form.push(base('nm', 'text', 'Name', { amount: '2' }));
    form.push(base('ta', 'textarea', 'Story', { amount: '3' }));
    const h = await harness.openBuilder({ builder: true, pro: 1, packageType: 1, form, scripts: ['public/assets/js/repeater-efb.js', 'includes/admin/assets/js/repeater-builder-efb.js', 'public/assets/js/layout-efb.js', 'includes/admin/assets/js/layout-builder-efb.js'],
      extraVar: { capabilities: capabilities(1), roles: ROLES, repeater: { plan: { group: true, repeat: true }, caps: { rows: 50, children: 25, groups: 10 } } } });
    const src = 'const B = ' + B.toString().replace('base(', '(' + base.toString() + ')(') + ';';
    const o = await h.page.evaluate(new Function('return (async () => {' + src + `
      const o = {};
      const tryit = (f) => { try { const v = f(); return v === undefined || (v && typeof v.then === 'function') ? 'ok' : v; } catch (e) { return { code: e.code, path: e.path }; } };
      const row = (id) => JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === id) || null));
      const ins = (items) => { const r = tryit(() => efbBuilderApi.insertItems(items, { render: false })); return r && r.code ? r : 'ok'; };
      o.sec = ins([Object.assign(B('sec1', 'section', 'Contact'), { collapsible: true, collapsed: 1 })]);
      o.secRow = row('sec1');
      o.rich = ins([Object.assign(B('ta2', 'textarea', 'Notes'), { rich_text: true, rich_toolbar: 'full' })]);
      o.richRow = row('ta2');
      o.richOnText = ins([Object.assign(B('t3', 'text', 'X'), { rich_text: '1' })]);
      o.vis = ins([Object.assign(B('v1', 'text', 'Staff'), { vis_mode: 'roles', vis_roles: 'editor, author' })]);
      o.visRow = row('v1');
      o.visGhost = ins([Object.assign(B('v2', 'text', 'X'), { vis_mode: 'roles', vis_roles: 'editor,ghost' })]);
      o.visBad = ins([Object.assign(B('v3', 'text', 'X'), { vis_mode: 'members' })]);
      o.visRolesNoMode = ins([Object.assign(B('v4', 'text', 'X'), { vis_mode: 'in', vis_roles: 'editor' })]);
      o.mark = ins([Object.assign(B('v6', 'text', 'X'), { vis_view: 'public' })]);
      o.sig = ins([Object.assign(B('sg', 'esign', 'Sign'), { esign_mode: 'both', esign_font: 'arabic' })]);
      o.sigRow = row('sg');
      o.sigOnText = ins([Object.assign(B('s2', 'text', 'X'), { esign_mode: 'type' })]);
      o.sigFont = ins([Object.assign(B('s3', 'esign', 'X'), { esign_font: 'gothic' })]);
      o.grp = tryit(() => efbBuilderApi.addGroup({ id_: 'g1', name: 'Group' }, {}));
      o.secInGroup = ins([Object.assign(B('sec2', 'section', 'In'), { group: 'g1' })]);
      o.set1 = tryit(() => efbBuilderApi.setFormSettings({ review_before_submit: true, review_title: 'Check <b>your</b> answers' }));
      o.r0 = { on: valj_efb[0].review_before_submit, title: valj_efb[0].review_title };
      o.set2 = tryit(() => efbBuilderApi.setFormSettings({ review_hide_empty: 'maybe' }));
      const whole = JSON.parse(JSON.stringify(valj_efb));
      whole[0].review_before_submit = true;
      o.setForm = tryit(() => efbBuilderApi.setForm(whole, { render: false }));
      o.setFormR0 = valj_efb[0].review_before_submit;
      const paid = JSON.parse(JSON.stringify(valj_efb));
      paid[0].review_before_submit = '1';
      paid.push(Object.assign(B('st', 'stripe', 'Card'), { amount: 99 }));
      o.setFormPay = tryit(() => efbBuilderApi.setForm(paid, { render: false }));
      o.fl1 = tryit(() => efbBuilderApi.setFieldLayout('nm', { vis_mode: 'out' }));
      o.flRow = row('nm');
      o.fl2 = tryit(() => efbBuilderApi.setFieldLayout('ta', { rich_text: '1', rich_toolbar: 'basic' }));
      o.fl3 = tryit(() => efbBuilderApi.setFieldLayout('nm', { color: 'red' }));
      o.fl4 = tryit(() => efbBuilderApi.setFieldLayout('nope', { vis_mode: 'in' }));
      return o;
    })()`));
    await h.browser.close();
    return o;
  })();
  const code = (x) => (x && x.code) || x;
  c.check('insertItems: a section, collapsible / collapsed written "1"', pro.sec === 'ok' && pro.secRow && pro.secRow.collapsible === '1' && pro.secRow.collapsed === '1', [pro.sec, pro.secRow && [pro.secRow.collapsible, pro.secRow.collapsed]]);
  c.check('insertItems: a rich textarea (rich_text "1", toolbar full)', pro.rich === 'ok' && pro.richRow.rich_text === '1' && pro.richRow.rich_toolbar === 'full', [pro.rich, pro.richRow && pro.richRow.rich_text]);
  c.check('insertItems: rich_text on a text field is refused (layout_invalid)', code(pro.richOnText) === 'layout_invalid' && /rich_text/.test(pro.richOnText.path), pro.richOnText);
  c.check('insertItems: vis_mode roles with vis_roles "editor, author" → "editor,author"', pro.vis === 'ok' && pro.visRow.vis_mode === 'roles' && pro.visRow.vis_roles === 'editor,author', [pro.vis, pro.visRow && pro.visRow.vis_roles]);
  c.check('insertItems: a role the site does not have is refused (layout_vis_role)', code(pro.visGhost) === 'layout_vis_role', pro.visGhost);
  c.check('insertItems: an unknown vis_mode, vis_roles without "roles", the page\'s mark vis_view: layout_invalid', [code(pro.visBad), code(pro.visRolesNoMode), code(pro.mark)].join() === 'layout_invalid,layout_invalid,layout_invalid', [pro.visBad, pro.visRolesNoMode, pro.mark]);
  c.check('insertItems: a typed signature (esign_mode both, esign_font arabic)', pro.sig === 'ok' && pro.sigRow.esign_mode === 'both' && pro.sigRow.esign_font === 'arabic', [pro.sig, pro.sigRow]);
  c.check('insertItems: esign_mode on a text field, an unknown font: layout_invalid', code(pro.sigOnText) === 'layout_invalid' && code(pro.sigFont) === 'layout_invalid', [pro.sigOnText, pro.sigFont]);
  c.check('insertItems: a section in a repeater group is refused', ['layout_section_group', 'repeater_child_type'].indexOf(code(pro.secInGroup)) !== -1, [pro.grp, pro.secInGroup]);
  c.check('setFormSettings: review keys with the save rules (title as text)', pro.set1 && pro.set1.values && pro.r0.on === '1' && pro.r0.title === 'Check your answers', [pro.set1, pro.r0]);
  c.check('setFormSettings: a bad flag is refused (layout_invalid)', code(pro.set2) === 'layout_invalid', pro.set2);
  c.check('setForm: row 0 review_before_submit true → "1"', pro.setForm === 'ok' && pro.setFormR0 === '1', [pro.setForm, pro.setFormR0]);
  c.check('setForm: the review page on a form with a Stripe field is refused (layout_review_payment)', code(pro.setFormPay) === 'layout_review_payment', pro.setFormPay);
  c.check('setFieldLayout: one field\'s keys (vis_mode out; rich_text with its toolbar)', pro.fl1 && pro.fl1.values && pro.flRow.vis_mode === 'out' && pro.fl2 && pro.fl2.values && pro.fl2.values.rich_text === '1', [pro.fl1, pro.fl2]);
  c.check('setFieldLayout: another key or an unknown field is refused', code(pro.fl3) === 'layout_invalid' && code(pro.fl4) === 'unknown_target', [pro.fl3, pro.fl4]);

  // Plan gates: Free (2) and Free Plus (3).
  for (const [pkg, label] of [[2, 'Free'], [3, 'Free Plus']]) {
    const form = blankForm();
    form.push(base('nm', 'text', 'Name', { amount: '2' }));
    const h = await harness.openBuilder({ builder: true, pro: pkg === 2 ? 0 : 1, packageType: pkg, form, scripts: ['public/assets/js/layout-efb.js', 'includes/admin/assets/js/layout-builder-efb.js'], extraVar: { capabilities: capabilities(pkg), roles: ROLES } });
    const src = 'const B = ' + B.toString().replace('base(', '(' + base.toString() + ')(') + ';';
    const g = await h.page.evaluate(new Function('return (async () => {' + src + `
      const tryit = (f) => { try { f(); return 'ok'; } catch (e) { return e.code; } };
      return { rich: tryit(() => efbBuilderApi.insertItems([Object.assign(B('r1', 'textarea', 'R'), { rich_text: '1' })], { render: false })),
        vis: tryit(() => efbBuilderApi.insertItems([Object.assign(B('v1', 'text', 'V'), { vis_mode: 'in' })], { render: false })),
        visAll: tryit(() => efbBuilderApi.insertItems([Object.assign(B('v2', 'text', 'V'), { vis_mode: 'all' })], { render: false })),
        sec: tryit(() => efbBuilderApi.insertItems([B('s1', 'section', 'S')], { render: false })) };
    })()`));
    await h.browser.close();
    c.check(`${label}: rich text ${pkg === 2 ? 'needs Free Plus (plan_required)' : 'is open'}; a visibility rule needs Pro (plan_required), "all" is fine; a section is free`,
      g.rich === (pkg === 2 ? 'plan_required' : 'ok') && g.vis === 'plan_required' && g.visAll === 'ok' && g.sec === 'ok', g);
  }

  // The registry.
  const caps = capabilities(1);
  const f = caps.features;
  c.check('capabilities: review Free, section Free, rich text Free Plus, typed signature Free Plus, visibility Pro',
    [f['setting:review_before_submit'].package, f['field:section'].package, f['setting:rich_text'].package, f['setting:esign_mode'].package, f['setting:vis_mode'].package].join() === 'free,free,free_plus,free_plus,pro',
    ['setting:review_before_submit', 'field:section', 'setting:rich_text', 'setting:esign_mode', 'setting:vis_mode'].map((k) => f[k] && f[k].package));
  c.check('capabilities.layout: keys, visibility modes, signature modes and fonts', caps.layout && caps.layout.field_keys.indexOf('vis_mode') !== -1 && caps.layout.field_keys.indexOf('esign_mode') !== -1
    && caps.layout.visibility.modes.join() === 'all,in,out,roles' && caps.layout.typed_signature.modes.join() === 'draw,type,both' && caps.layout.typed_signature.fonts.join() === 'auto,latin,arabic', caps.layout);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
