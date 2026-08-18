/**
 * Regression test for the two defects in the admin Response viewer
 * (admin.php?page=Emsfb&state=show-messages) when a reply carries a file.
 *
 * 1. The card appended the instant the reply is accepted was credited to
 *    "Guest". The dashboard read the author positionally - message[0].by -
 *    but sendBack_emsFormBuilder_pub queues every finished upload BEFORE the
 *    typed row, so as soon as a file was attached index 0 was the file row,
 *    which carries no `by`, and fun_emsFormBuilder_show_messages fell through
 *    to its guest branch. Reloading the ticket showed the right name, because
 *    that path resolves rsp_by server-side.
 *
 * 2. The attachment chips (#efb_upload_file_info_resp_file_efb_<id>) stayed on
 *    screen after a successful send, so the composer looked like it was still
 *    carrying files that had already left with the previous reply.
 *
 * What must hold:
 *   - the name comes from the server when it answers with one, and never
 *     degrades to "Guest" just because an attachment sits first in the queue;
 *   - a successful reply clears the chips, their progress rows, the pending
 *     file bookkeeping and the paperclip highlight;
 *   - ordering: the card must be rendered from the FULL payload before the
 *     reset splices the file rows out of that same array.
 *
 * Run: node tests/test-response-reply-sender-and-uploads.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// ── Minimal fake DOM ─────────────────────────────────────────────────────────
let registry = [];

class FakeClassList {
  constructor(el) { this.el = el; }
  add(...names) { names.forEach((n) => this.el.classes.add(n)); }
  remove(...names) { names.forEach((n) => this.el.classes.delete(n)); }
  contains(name) { return this.el.classes.has(name); }
  toggle(name, force) {
    if (force === undefined) { this.contains(name) ? this.remove(name) : this.add(name); }
    else { force ? this.add(name) : this.remove(name); }
  }
}

class FakeElement {
  constructor(tag, attrs = {}) {
    this.tag = tag;
    this.attrs = Object.assign({}, attrs);
    this.dataset = {};
    this.style = {};
    this.children = [];
    this.parent = null;
    this.classes = new Set(attrs.class ? attrs.class.split(/\s+/) : []);
    this.innerHTML = '';
    this.value = '';
  }
  get id() { return this.attrs.id || ''; }
  get classList() { return new FakeClassList(this); }
  appendChild(child) { child.parent = this; this.children.push(child); return child; }
  remove() {
    if (this.parent) this.parent.children = this.parent.children.filter((c) => c !== this);
    this.parent = null;
    registry = registry.filter((el) => el !== this);
  }
  descendants() {
    const out = [];
    this.children.forEach((c) => { out.push(c); out.push(...c.descendants()); });
    return out;
  }
  querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
  querySelectorAll(sel) { return this.descendants().filter((el) => matches(el, sel)); }
  addEventListener() {}
  scrollIntoView() {}
}

/* Only the selector shapes response-viewer-efb.js actually builds:
   '.efb-upload-file-info' and '.efb-upload-file-info[data-upload-id]'. */
function matches(el, selector) {
  const tokens = String(selector).trim().match(/\.[\w-]+|\[[^\]]+\]/g) || [];
  return tokens.every((t) => {
    if (t[0] === '.') return el.classes.has(t.slice(1));
    const name = t.slice(1, -1);
    const key = name.replace(/^data-/, '').replace(/-([a-z])/g, (m, c) => c.toUpperCase());
    return el.dataset[key] !== undefined;
  });
}

function makeEl(tag, attrs = {}) {
  const el = new FakeElement(tag, attrs);
  registry.push(el);
  return el;
}

global.window = global;
global.addEventListener = function () {};
/* Node 22 exposes a real read-only navigator; the reply path only reads
   onLine, so redefine the property rather than replacing the object. */
Object.defineProperty(global, 'navigator', { value: { onLine: true }, configurable: true, writable: true });
global.document = {
  addEventListener() {},
  createElement(tag) { return makeEl(tag); },
  getElementById(id) { return registry.find((el) => el.id === id) || null; },
  querySelector(sel) { return registry.find((el) => matches(el, sel)) || null; },
  querySelectorAll(sel) { return registry.filter((el) => matches(el, sel)); },
  documentElement: { style: { setProperty() {} }, clientHeight: 900 },
  head: makeEl('head'),
};
global.localStorage = { setItem() {}, getItem() { return null; }, removeItem() {} };
global.setTimeout = function (fn) { if (typeof fn === 'function') fn(); return 1; };
global.scrollTo = function () {};
global.alert_message_efb = function () {};

const ADMIN_NAME = 'Hassan Tafreshi';
global.efb_var = {
  rtl: 0,
  sid: 'sid',
  msg_id: 77,
  text: { guest: 'Guest', file: 'File', reply: 'Reply', sending: 'Sending', by: 'by', delete: 'delete' },
};
global.ajax_object_efm = {
  ajax_url: '/wp-admin/admin-ajax.php',
  user_name: ADMIN_NAME,
  user_ip: '10.0.0.5',
  text: global.efb_var.text,
};
global.setting_emsFormBuilder = { dsupfile: true };
global.pro_efb = true;
global.sendBack_emsFormBuilder_pub = [];
global.files_emsFormBuilder = [];
global.sessionPub_emsFormBuilder = 'sess';
global._efb_core_nonce_ = 'nonce';
global.form_type_emsFormBuilder = 'form';
global.valueJson_ws_messages = [];
global.stock_state_efb = false;

/* list_form-efb.js runs jQuery(fn) at load and drives the reply POST through
   $.post; nothing else in this test needs a real jQuery. */
let postHandler = null;
let jqReady = false;
function jQueryStub(fn) {
  /* Real jQuery defers ready blocks until the panel exists; run callbacks only
     once the files are loaded, so the reply flow still executes inline. */
  if (jqReady && typeof fn === 'function') fn(jQueryStub);
  return { on() { return this; }, ready() { return this; }, each() { return this; } };
}
jQueryStub.post = function (url, data, cb) { if (postHandler) postHandler(url, data, cb); };
jQueryStub.fn = { extend() {} };
global.jQuery = jQueryStub;
global.$ = jQueryStub;

const root = path.join(__dirname, '..');
vm.runInThisContext(
  fs.readFileSync(path.join(root, 'includes/admin/assets/js/response-viewer-efb.js'), 'utf8'),
  { filename: 'response-viewer-efb.js' }
);
/* Same order the panel enqueues them in: response-viewer in the head,
   list_form in the footer, so list_form's definitions are the live ones. */
vm.runInThisContext(
  fs.readFileSync(path.join(root, 'includes/admin/assets/js/list_form-efb.js'), 'utf8'),
  { filename: 'list_form-efb.js' }
);

jqReady = true;

const Viewer = vm.runInThisContext('EfbResponseViewer');

let pass = 0;
let fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}

const UPLOAD_ID = 'resp_file_efb_1787061521080_3';

/** The payload the dashboard really sends: attachment queued ahead of the text. */
function attachmentFirstPayload() {
  return [
    { id_: UPLOAD_ID, name: 'file', type: 'allformat', value: '@file@', url: 'https://site/efb-PLG-260818-ab.pdf', amount: 0 },
    { id_: 'message', name: 'message', type: 'text', amount: 0, value: 'here you go', by: ADMIN_NAME },
  ];
}

// ── 1. Sender resolution ─────────────────────────────────────────────────────
console.log('\n--- sender name on the freshly appended card ---');

const payload = attachmentFirstPayload();

test('the old positional read is what produced "Guest"', payload[0].by, undefined);

test('server name wins',
  efb_reply_sender_name_efb({ data: { by: 'Support Desk' } }, payload),
  'Support Desk');

test('no server name falls back to the authored row, not index 0',
  efb_reply_sender_name_efb({ data: {} }, payload),
  ADMIN_NAME);

test('an attachment-only payload still credits the signed-in admin',
  efb_reply_sender_name_efb({ data: {} }, [payload[0]]),
  ADMIN_NAME);

test('never degrades to Guest while a name is known',
  efb_reply_sender_name_efb({ data: {} }, payload) === global.efb_var.text.guest,
  false);

// ── 2. Composer reset ────────────────────────────────────────────────────────
console.log('\n--- attachment chips after a successful send ---');

/** Rebuilds the composer with one staged attachment, exactly as the viewer leaves it. */
function buildComposer() {
  registry = [];
  const zone = makeEl('div', { id: 'efb_upload_zone', class: 'efb efb-upload-zone' });
  const list = makeEl('div', { id: 'efb_upload_file_list', class: 'efb efb-upload-file-list' });
  zone.appendChild(list);

  const chip = makeEl('div', { id: 'efb_upload_file_info_' + UPLOAD_ID, class: 'efb efb-upload-file-info' });
  chip.dataset.uploadId = UPLOAD_ID;
  list.appendChild(chip);

  const progress = makeEl('div', { id: UPLOAD_ID + '-prG', class: 'efb efb-upload-progress' });
  list.appendChild(progress);

  makeEl('div', { id: 'resp_file_efb-prG', class: 'efb efb-upload-progress' });
  makeEl('div', { id: 'resp_file_efb-prA', class: 'efb efb-upload-progress-bar' });
  makeEl('div', { id: 'resp_file_efb-prB' });

  const attachBtn = makeEl('button', { id: 'efb_attach_btn', class: 'efb-attach-btn efb-attach-active' });
  const input = makeEl('input', { id: 'resp_file_efb_' });
  input.value = 'C:\\fakepath\\invoice.pdf';
  makeEl('span', { id: 'name_attach_efb' }).innerHTML = 'invoice..';

  makeEl('div', { id: 'resp_efb' });
  makeEl('p', { id: 'replay_state__emsFormBuilder' });
  makeEl('textarea', { id: 'replayM_emsFormBuilder' }).value = 'here you go';
  makeEl('button', { id: 'replayB_emsFormBuilder', class: 'efb-reply-btn disabled' });
  makeEl('div', { id: 'efb_rich_editor' });

  /* list_form-efb.js declares files_emsFormBuilder with `let`, so it lives in
     the script scope and a plain global assignment would not reach it. */
  vm.runInThisContext('files_emsFormBuilder = [{ id_: ' + JSON.stringify(UPLOAD_ID) + ', value: "@file@", state: 2 }];');
  global.sendBack_emsFormBuilder_pub = attachmentFirstPayload();

  return { zone, list, chip, progress, attachBtn, input };
}

let dom = buildComposer();
Viewer.resetReplyUploads();

test('the chip is gone from the DOM', document.getElementById('efb_upload_file_info_' + UPLOAD_ID), null);
test('its progress row is gone too', document.getElementById(UPLOAD_ID + '-prG'), null);
test('the file list is hidden again', dom.list.classes.has('d-none'), true);
test('the upload zone is hidden again', dom.zone.classes.has('d-none'), true);
test('the paperclip drops its active state', dom.attachBtn.classes.has('efb-attach-active'), false);
test('the file input is cleared', dom.input.value, '');
test('the legacy attach label is reset', document.getElementById('name_attach_efb').innerHTML, 'File');
test('pending upload bookkeeping is dropped', vm.runInThisContext('files_emsFormBuilder.length'), 0);
test('the queued file row is dropped', global.sendBack_emsFormBuilder_pub.map((x) => x.id_), ['message']);

// ── 3. The dashboard reply round trip ────────────────────────────────────────
console.log('\n--- dashboard reply: render then reset ---');

dom = buildComposer();

let renderedBy = null;
let renderedPayload = null;
global.fun_emsFormBuilder_show_messages = function (content, by) {
  renderedBy = by;
  renderedPayload = content.map((x) => x.id_);
  return '<div class="efb-msg-card">card</div>';
};

postHandler = function (url, data, cb) {
  cb({ success: true, data: { success: true, m: 'Message sent', by: ADMIN_NAME } });
};

fun_send_replayMessage_ajax_emsFormBuilder(global.sendBack_emsFormBuilder_pub, 77);

test('the card is credited to the admin, not a guest', renderedBy, ADMIN_NAME);
test('the card was rendered from the full payload, before the reset',
  renderedPayload, [UPLOAD_ID, 'message']);
test('the appended card reached the thread', document.getElementById('resp_efb').innerHTML.indexOf('efb-msg-card') !== -1, true);
test('the chip is cleared after the send', document.getElementById('efb_upload_file_info_' + UPLOAD_ID), null);
test('the upload zone is hidden after the send', dom.zone.classes.has('d-none'), true);
test('the queue is emptied', global.sendBack_emsFormBuilder_pub.length, 0);

/* A server that answers without `by` (older builds, or the closed/opened
   state messages) must still not fall back to the guest label. */
dom = buildComposer();
renderedBy = null;
postHandler = function (url, data, cb) { cb({ success: true, data: { success: true, m: 'Message sent' } }); };
fun_send_replayMessage_ajax_emsFormBuilder(global.sendBack_emsFormBuilder_pub, 77);

test('a `by`-less response still credits the signed-in admin', renderedBy, ADMIN_NAME);

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail === 0 ? 0 : 1);
