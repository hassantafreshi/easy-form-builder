/**
 * F15-A: the pure parts of the palette search and the keyboard shortcuts
 * (includes/admin/assets/js/productivity-efb.js, spec §4.4 / §4.5):
 *  - normalize(): lower case, Persian / Arabic letter forms, half-space, harakat, tatweel, digits;
 *  - paletteMatch(): translated name, id, fixed English list, translated synonyms; every word must match;
 *  - shortcutAction(): matched on KeyboardEvent.code, so a Persian or Arabic layout (key "ظ", "س")
 *    still undoes and saves; only Save and Esc inside a text box or a dialog; single keys can be off;
 *  - isTextTarget().
 *
 *   node tests/test-palette-search.js
 */
'use strict';
const path = require('path');
const P = require(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'productivity-efb.js'));

let pass = 0, fail = 0;
function t(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '  got ' + JSON.stringify(actual) + ' want ' + JSON.stringify(expected)); }
}

/* ---- normalize */
t('lower case and trimmed', P.normalize('  Phone NUMBER '), 'phone number');
t('Arabic yeh and kaf become Persian ی and ک', P.normalize('يك'), P.normalize('یک'));
t('alef maksura becomes ی', P.normalize('ى'), 'ی');
t('half-space (ZWNJ) removed', P.normalize('می‌خواهم'), 'میخواهم');
t('harakat removed', P.normalize('جَوّال'), 'جوال');
t('tatweel removed', P.normalize('مـوبـایل'), 'موبایل');
t('Persian digits as ASCII', P.normalize('۱۲۳'), '123');
t('Arabic-Indic digits as ASCII', P.normalize('٤٥٦'), '456');
t('alef with hamza / madda as alef', P.normalize('آدرس أإ'), 'ادرس اا');
t('teh marbuta as heh', P.normalize('صفحة'), 'صفحه');
t('Latin diacritics removed', P.normalize('Téléphone'), 'telephone');
t('separators as one space', P.normalize('e-mail, address/zip'), 'e mail address zip');
t('null and numbers', [P.normalize(null), P.normalize(5)], ['', '5']);

/* ---- paletteMatch: a Persian admin page (names translated, synonyms translated) */
const fa = { palette_syn_mobile: 'تلفن همراه، جوال، شماره موبایل', palette_syn_email: 'رایانامه، پست الکترونیک' };
t('"موب" finds the mobile field by its Persian name', P.paletteMatch('موب', 'mobile', 'موبایل', fa), true);
t('"جوال" finds the mobile field by a translated synonym', P.paletteMatch('جوال', 'mobile', 'موبایل', fa), true);
t('"phone" finds the mobile field by the fixed English list', P.paletteMatch('phone', 'mobile', 'موبایل', fa), true);
t('"phone" finds the tel field', P.paletteMatch('phone', 'tel', 'تلفن', fa), true);
t('"phone" does not find the email field', P.paletteMatch('phone', 'email', 'ایمیل', fa), false);
t('the id matches (advdate)', P.paletteMatch('advdate', 'advdate', 'تاریخ پیشرفته', fa), true);
t('a Persian query written with Arabic letters (ي ك) still matches', P.paletteMatch('موبايل', 'mobile', 'موبایل', fa), true);
t('every word must match ("email phone" → no tile)', P.paletteMatch('email phone', 'email', 'Email', {}), false);
t('two words of one tile ("long text") match textarea', P.paletteMatch('long paragraph', 'textarea', 'Long Text', {}), true);
t('an empty query matches every tile', P.paletteMatch('   ', 'html', 'HTML', {}), true);
t('a payment tile is found by "payment"', P.paletteMatch('payment', 'mollie', 'Mollie', {}, 'efb tag efb-col-3 draggable-efb payment all'), true);
t('case does not matter', P.paletteMatch('EMAIL', 'email', 'Email', {}), true);
t('a German name is found by its prefix', P.paletteMatch('Datei', 'file', 'Datei-Upload', { palette_syn_file: 'Anhang' }), true);
t('a translated synonym is found ("Anhang")', P.paletteMatch('anhang', 'file', 'Datei-Upload', { palette_syn_file: 'Anhang' }), true);
t('every palette id has an English list', ['text', 'name', 'password', 'email', 'number', 'textarea', 'steps', 'checkbox', 'radio', 'select', 'multiselect', 'tel', 'mobile', 'range', 'date', 'advdate', 'file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder', 'address', 'payCheckbox', 'payRadio', 'prcfld', 'ttlprc', 'maps', 'stripe', 'paypal', 'url', 'conturyList', 'stateProvince', 'cityList', 'esign', 'switch', 'chlCheckBox', 'heading', 'section', 'color', 'rating', 'yesNo', 'link', 'html', 'pointr5', 'pointr10', 'imgRadio', 'pdate', 'ardate', 'trmCheckbox', 'ranking', 'calc', 'repeater', 'hiddenField', 'table_matrix'].filter((id) => !P.PALETTE_EN[id]), []);

/* ---- the palette ids of val-efb.js all have an English list (a new tile needs one) */
const fs = require('fs');
const val = fs.readFileSync(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'val-efb.js'), 'utf8');
const block = val.slice(val.indexOf('const fields_efb = ['), val.indexOf('];', val.indexOf('const fields_efb = [')));
const ids = (block.match(/id: *'([A-Za-z_0-9]+)'/g) || []).map((m) => m.replace(/id: *'|'/g, ''));
t('val-efb.js fields_efb read (' + ids.length + ' tiles)', ids.length > 40, true);
t('every tile of fields_efb has an English list', ids.filter((id) => !P.PALETTE_EN[id]), []);
const php = fs.readFileSync(path.join(__dirname, '..', 'includes', 'functions.php'), 'utf8');
t('every tile of fields_efb has a palette_syn_<id> phrase', ids.filter((id) => php.indexOf('"palette_syn_' + id + '" =>') === -1), []);

/* ---- shortcutAction */
const ev = (code, o) => Object.assign({ code, key: '', ctrlKey: false, metaKey: false, altKey: false, shiftKey: false }, o || {});
const A = (e, c) => P.shortcutAction(e, Object.assign({ inText: false, modal: false, singleKeys: true }, c || {}));
t('Ctrl+S saves', A(ev('KeyS', { ctrlKey: true, key: 's' })), 'save');
t('Ctrl+S on a Persian layout (key "س") saves', A(ev('KeyS', { ctrlKey: true, key: 'س' })), 'save');
t('Cmd+S saves (macOS)', A(ev('KeyS', { metaKey: true })), 'save');
t('Ctrl+S inside a text box still saves', A(ev('KeyS', { ctrlKey: true }), { inText: true }), 'save');
t('Ctrl+S with a dialog open still saves', A(ev('KeyS', { ctrlKey: true }), { modal: true }), 'save');
t('AltGr+S (Ctrl+Alt) is not Save', A(ev('KeyS', { ctrlKey: true, altKey: true })), null);
t('Ctrl+Z undoes', A(ev('KeyZ', { ctrlKey: true, key: 'z' })), 'undo');
t('Ctrl+Z on a Persian layout (key "ظ") undoes', A(ev('KeyZ', { ctrlKey: true, key: 'ظ' })), 'undo');
t('Ctrl+Z on an Arabic layout (key "ئ") undoes', A(ev('KeyZ', { ctrlKey: true, key: 'ئ' })), 'undo');
t('Ctrl+Z in a text box is the box\'s own undo', A(ev('KeyZ', { ctrlKey: true }), { inText: true }), null);
t('Ctrl+Shift+Z redoes', A(ev('KeyZ', { ctrlKey: true, shiftKey: true })), 'redo');
t('Cmd+Shift+Z redoes', A(ev('KeyZ', { metaKey: true, shiftKey: true })), 'redo');
t('Ctrl+Y redoes', A(ev('KeyY', { ctrlKey: true, key: 'غ' })), 'redo');
t('Ctrl+D duplicates', A(ev('KeyD', { ctrlKey: true, key: 'ی' })), 'duplicate');
t('Delete deletes', A(ev('Delete')), 'delete');
t('Backspace deletes', A(ev('Backspace')), 'delete');
t('Backspace in a text box is typing', A(ev('Backspace'), { inText: true }), null);
t('Alt+ArrowUp / Alt+ArrowDown move', [A(ev('ArrowUp', { altKey: true })), A(ev('ArrowDown', { altKey: true }))], ['moveUp', 'moveDown']);
t('ArrowUp alone is not ours', A(ev('ArrowUp')), null);
t('Alt+P previews (key "ح" on a Persian layout)', A(ev('KeyP', { altKey: true, key: 'ح' })), 'preview');
t('Esc closes, in a text box too', [A(ev('Escape')), A(ev('Escape'), { inText: true }), A(ev('Escape'), { modal: true })], ['close', 'close', 'close']);
t('"/" focuses the search', A(ev('Slash', { key: '/' })), 'search');
t('"/" on a Persian layout (key ".") still focuses the search', A(ev('Slash', { key: '.' })), 'search');
t('"/" inside a text box is typed', A(ev('Slash'), { inText: true }), null);
t('"?" (Shift+/) opens the help', A(ev('Slash', { shiftKey: true, key: '?' })), 'help');
t('single keys can be switched off (WCAG 2.1.4)', [A(ev('Slash'), { singleKeys: false }), A(ev('Slash', { shiftKey: true }), { singleKeys: false })], [null, null]);
t('with single keys off, Ctrl+Z still undoes', A(ev('KeyZ', { ctrlKey: true }), { singleKeys: false }), 'undo');
t('a dialog open: only Save and Esc', [A(ev('KeyZ', { ctrlKey: true }), { modal: true }), A(ev('Delete'), { modal: true }), A(ev('Slash'), { modal: true })], [null, null, null]);
t('a letter alone is not ours', A(ev('KeyA', { key: 'a' })), null);

/* ---- isTextTarget */
const elOf = (tag, o) => Object.assign({ nodeType: 1, tagName: tag, type: '', isContentEditable: false, getAttribute: () => null }, o || {});
t('input text / search / email / number are text', ['text', 'search', 'email', 'number'].map((ty) => P.isTextTarget(elOf('INPUT', { type: ty }))), [true, true, true, true]);
t('checkbox, radio, button are not', ['checkbox', 'radio', 'button'].map((ty) => P.isTextTarget(elOf('INPUT', { type: ty }))), [false, false, false]);
t('textarea, select, contenteditable, role=textbox are', [P.isTextTarget(elOf('TEXTAREA')), P.isTextTarget(elOf('SELECT')), P.isTextTarget(elOf('DIV', { isContentEditable: true })), P.isTextTarget(elOf('DIV', { getAttribute: (k) => (k === 'role' ? 'textbox' : null) }))], [true, true, true, true]);
t('a div, a button, null are not', [P.isTextTarget(elOf('DIV')), P.isTextTarget(elOf('BUTTON')), P.isTextTarget(null)], [false, false, false]);

/* ---- the help table */
t('the help lists every action with its phrase', P.shortcutRows(false).map((r) => r[1]), ['scSave', 'scUndo', 'scRedo', 'scSearch', 'scDelete', 'scDuplicate', 'scMove', 'scClose', 'scPreview', 'scHelp']);
t('macOS shows ⌘ instead of Ctrl', P.shortcutRows(true)[0][3][0][0], '⌘');

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
