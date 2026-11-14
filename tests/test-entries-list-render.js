/**
 * F14-C: the entries screen's renderers (includes/admin/assets/js/entries-efb.js) in a Node vm with a small
 * window: the head for chosen columns (aria-sort), a row (data-label on every cell, the star's aria-pressed,
 * the status badges, the open button keeps the old id btn-m-N), field columns read by id_ (not by label: two
 * fields with the same label stay apart), hostile answers / labels / column names escaped, the preview with every
 * value isolated (<bdi>), Persian / Arabic-Indic digits in counts and pages, the "Showing" sentence, the tabs
 * (role=tab, aria-selected, the pending tab only on payment forms), the trash's restore / delete buttons.
 *
 * Run: node tests/test-entries-list-render.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + ': expected ' + e + ', got ' + a); }
}

function load(locale, text) {
  const win = { efb_var: { text: Object.assign({ entShowingXY: 'Showing %1$s–%2$s of %3$s' }, text || {}), wp_lan: locale || 'en_US', rtl: /^(fa|ar)/.test(locale || '') ? 1 : 0, entries: { plan: { filter: true, columns: true } } } };
  win.window = win;
  win.document = { documentElement: { dir: win.efb_var.rtl ? 'rtl' : 'ltr' }, getElementById: () => null, addEventListener() {} };
  win.globalThis = win;
  const ctx = vm.createContext(win);
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/entries-efb.js'), 'utf8'), ctx);
  return win;
}
const store = (rows) => JSON.stringify(rows).replace(/"/g, '\\"');

const W = load('en_US');
const E = W.EfbEntries;
t('the renderers by the spec\'s names exist', [typeof W.efb_entries_render_head_efb, typeof W.efb_entries_render_row_efb], ['function', 'function']);
E.state.fields = [{ id: 'a1', name: 'Notes', type: 'text' }, { id: 'a2', name: 'Notes', type: 'textarea' }, { id: 'x"><img src=x onerror=alert(1)>', name: '<script>alert(1)</script>', type: 'text' }];
const head = W.efb_entries_render_head_efb(['a1', 'a2', '_ip', 'x"><img src=x onerror=alert(1)>']);
t('head: aria-sort descending on the date (default)', /aria-sort="descending"/.test(head), true);
t('head: two fields with the same label are two columns', (head.match(/data-col="a[12]"/g) || []).length, 2);
t('head: a hostile column name is escaped', head.indexOf('<script>') === -1 && head.indexOf('<img') === -1 && head.indexOf('&lt;script&gt;') !== -1, true);

const row = {
  msg_id: 7, form_id: 3, track: 'TRK<b>1</b>', date: '2026-11-14 10:00', read_: 0, entry_status: 0, starred: 1, notes_count: 2, ip: '203.0.113.9', edited_at: '',
  content: store([{ id_: 'a1', name: 'Notes', type: 'text', value: 'first <img src=x onerror=window.pwned=1>' }, { id_: 'a2', name: 'Notes', type: 'textarea', value: 'second "quoted" @efb@dq#tok@efb@dq#' }, { id_: 'nm', name: '<b>Label</b>', type: 'text', value: 'علی Ahmadi ۱۲' }, { type: 'w_link', value: 'https://x.test/' }]),
};
const html = W.efb_entries_render_row_efb(row, ['a1', 'a2', '_ip']);
const cells = html.match(/<td[^>]*>/g) || [];
t('row: every cell has data-label (phone cards)', cells.length > 0 && cells.every((c) => /data-label="/.test(c)), true);
t('row: the open button keeps the old id btn-m-7 and says new', /id="btn-m-7"/.test(html) && /efb-has-badge/.test(html), true);
t('row: the star is pressed (aria-pressed="true") and named "Remove star"', /aria-pressed="true"/.test(html) && /aria-label="Remove star"/.test(html), true);
t('row: unread → the row class efb-ent-unread', /efb-ent-unread/.test(html), true);
t('row: the column a1 shows field a1\'s own answer (by id_)', /data-col="a1"[^>]*>[\s\S]*?first/.test(html), true);
t('row: the column a2 shows field a2\'s answer, the quote token decoded', /data-col="a2"[\s\S]*?second &quot;quoted&quot; &quot;tok&quot;/.test(html), true);
t('row: hostile answers and labels are text (no <img>, no <b> from data)', html.indexOf('<img') === -1 && html.indexOf('<b>') === -1, true);
t('row: the tracking code is escaped', html.indexOf('TRK&lt;b&gt;1&lt;/b&gt;') !== -1, true);
t('row: the IP column, isolated left to right', /<bdi dir="ltr">203\.0\.113\.9<\/bdi>/.test(html), true);
t('row: the preview isolates every label and value (<bdi>)', /efb-preview-text[^>]*><bdi>Notes<\/bdi>: <bdi>first/.test(html), true);
t('row: the notes indicator (2)', /efb-ent-notes-ind[\s\S]*?<bdi>2<\/bdi>/.test(html), true);
const trash = W.efb_entries_render_row_efb(Object.assign({}, row, { entry_status: 2, starred: 0 }), []);
t('trash row: Restore and Delete permanently, a Trash badge, no Move to trash', /data-op="restore"/.test(trash) && /data-op="delete"/.test(trash) && !/data-op="trash"/.test(trash) && /efb-ent-badge-trash/.test(trash), true);
const spam = W.efb_entries_render_row_efb(Object.assign({}, row, { entry_status: 1 }), []);
t('spam row: the Spam badge', /efb-ent-badge-spam/.test(spam), true);
// F12: why an entry is spam, from its history's reason codes; anything that is not a reason code is dropped.
const spam2 = W.efb_entries_render_row_efb(Object.assign({}, row, { entry_status: 1, spam_reasons: ['honeypot', 'too_fast', '<img src=x onerror=alert(1)>', 'akismet_discard', 'akismet'] }), []);
t('F12 spam row: "Spam: Hidden field filled, Sent too fast, Akismet" (each isolated, Akismet once)', /efb-ent-spam-why[^>]*>Spam: <bdi>Hidden field filled<\/bdi>, <bdi>Sent too fast<\/bdi>, <bdi>Akismet<\/bdi><\/span>/.test(spam2), true);
t('F12 spam row: a hostile reason is never shown (not even escaped)', spam2.indexOf('<img') === -1 && spam2.indexOf('&lt;img') === -1 && spam2.indexOf('onerror') === -1, true);
t('F12 spam row without reasons (marked by hand): the badge only', /efb-ent-spam-why/.test(spam), false);
t('a row of a read entry is not "new"', /efb-ent-unread/.test(W.efb_entries_render_row_efb(Object.assign({}, row, { read_: 1 }), [])), false);

E.state.counts = { all: 1234, unread: 3, starred: 0, pending: 2, closed: 0, spam: 1, trash: 5 };
E.state.formType = 'form';
let tabs = E.renderTabs();
t('tabs: role=tab, one aria-selected="true", pending shown when there are pending entries', (tabs.match(/role="tab"/g) || []).length === 7 && (tabs.match(/aria-selected="true"/g) || []).length === 1, true);
t('tabs: the count with grouping (1,234)', /<bdi>1,234<\/bdi>/.test(tabs), true);
E.state.counts.pending = 0;
t('tabs: no pending tab on an ordinary form without pending entries', /efbEntTab-pending/.test(E.renderTabs()), false);
E.state.total = 1234; E.state.page = 2; E.state.per_page = 20; E.state.rows = new Array(20).fill(row); E.state.estimate = false;
let pager = E.renderPager();
t('pager: "Showing 21–40 of 1,234", each number isolated', pager.indexOf('Showing <bdi>21</bdi>–<bdi>40</bdi> of <bdi>1,234</bdi>') !== -1, true);
t('pager: the current page has aria-current', /aria-current="page"[^>]*>2</.test(pager), true);
E.state.estimate = true; E.state.total = 5000;
t('pager: an estimate says "More than 5,000"', E.renderPager().indexOf('More than <bdi>5,000</bdi>') !== -1, true);

const F = load('fa_IR');
{
  const fa = F.efb_entries_render_row_efb(Object.assign({}, row, { entry_status: 1, spam_reasons: ['honeypot', 'too_fast'] }), []);
  t('F12 fa_IR: the reasons joined with the Arabic comma, each isolated', /<bdi>Hidden field filled<\/bdi>، <bdi>Sent too fast<\/bdi>/.test(fa), true);
}
F.EfbEntries.state.counts = { all: 1234, unread: 3, starred: 0, pending: 0, closed: 0, spam: 0, trash: 0 };
tabs = F.EfbEntries.renderTabs();
t('fa_IR: counts in Persian digits (۱٬۲۳۴)', /<bdi>۱٬۲۳۴<\/bdi>/.test(tabs), true);
Object.assign(F.EfbEntries.state, { total: 45, page: 3, per_page: 20, rows: new Array(5).fill(row), estimate: false });
pager = F.EfbEntries.renderPager();
t('fa_IR: "Showing ۴۱–۴۵ of ۴۵" and page buttons in Persian digits', pager.indexOf('<bdi>۴۱</bdi>–<bdi>۴۵</bdi> of <bdi>۴۵</bdi>') !== -1 && />۳<\/button>/.test(pager), true);
const frow = F.efb_entries_render_row_efb(Object.assign({}, row, { date: 'November 14, 2026 10:00 am' }), []);
t('fa_IR: the date of a row in Persian digits', /۱۴, ۲۰۲۶ ۱۰:۰۰/.test(frow), true);
const A = load('ar');
A.EfbEntries.state.counts = { all: 23, unread: 0, starred: 0, pending: 0, closed: 0, spam: 0, trash: 0 };
t('ar: counts in Arabic-Indic digits (٢٣)', /<bdi>٢٣<\/bdi>/.test(A.EfbEntries.renderTabs()), true);
t('latin(): Persian / Arabic digits typed become 0-9', E.latin('۱۲۳٤٥'), '12345');
t('valueOf(): checkbox options and repeater rows joined', E.valueOf([{ id_: 'c', value: 'Cat' }, { id_: 'c', value: 'Bird' }, { id_: 'r', value: 'A', ri: 0 }, { id_: 'r', value: 'B', ri: 1 }], 'c') + ' / ' + E.valueOf([{ id_: 'r', value: 'A', ri: 0 }, { id_: 'r', value: 'B', ri: 1 }], 'r'), 'Cat | Bird / A | B');
t('valueOf(): a number shows its display text (F04 disp)', E.valueOf([{ id_: 'n', value: '1234.5', disp: '1,234.50 $' }], 'n'), '1,234.50 $');
t('valueOf(): an anonymized answer reads "Removed for privacy"', E.valueOf([{ id_: 'n', value: '@prv@' }], 'n'), 'Removed for privacy');

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
