/**
 * F07: upload fields that take several files (window.EFBUpload).
 * Spec: docs/feature-gaps/F07-file-upload.fa.md (§3, §6, §8.1).
 *
 * A file / dadfile field whose rendered input says data-max-files > 1 is run here instead of the
 * one-file path of core-efb.js: every chosen or dropped file joins a queue (never replaces it), is
 * checked in the browser (size, type, count, total), optionally made smaller (data-img-max, JPEG /
 * PNG / WebP; EXIF and location removed), and sent two at a time through the same upload route
 * (fetch_uploadFile, new-efb.js). Each stored file becomes its own answer row
 * {id_, id_ob: stored name, value: '@file@', url, ut, fnm, fsz}; the server checks every row again.
 * A field of one file (no data-max-files, or 1) keeps the old path and look.
 *
 * Also the save rules of the F07 keys (rules.sanitizeKey = Emsfb\Uploads::sanitize_key) for the
 * builder and efbBuilderApi, tested on the same vectors as the PHP twin.
 */
(function (root) {
  'use strict';

  var CONCURRENCY = 2; // EFB_UPLOAD_CONCURRENCY (§3.1)
  var MB = 1048576;

  /* ------------------------------------------------------------------ rules (builder + API) */
  var FIELD_KEYS = ['max_files', 'min_files', 'max_total_mb', 'capture', 'img_max_px', 'img_quality', 'file_store'];
  var FORM_KEYS = ['email_attach'];
  var MULTI_TYPES = ['file', 'dadfile'];
  var CAPTURE_PRESETS = ['image', 'media'];
  var RESIZE_PRESETS = ['image', 'allformat', 'customize'];

  function capOf() {
    var v = root.efb_var && root.efb_var.uploads && Number(root.efb_var.uploads.max_files_cap);
    return v > 0 ? Math.min(100, v) : 20;
  }
  function asciiNumber(v) {
    var s = v === null || v === undefined ? '' : String(v).trim();
    return s.replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
      .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
      .replace(/٫/g, '.').replace(/[،,]/g, '');
  }
  function trimNum(f) { return f.toFixed(2).replace(/0+$/, '').replace(/\.$/, ''); }
  /** One F07 key as the save keeps it ('' = not set). Twin of Emsfb\Uploads::sanitize_key(). */
  function sanitizeKey(k, v) {
    if (v === true) v = '1';
    if (v === false || v === null || v === undefined) v = '';
    if (typeof v === 'object') return '';
    var n;
    switch (k) {
      case 'max_files':
      case 'min_files':
        n = asciiNumber(v);
        if (!/^[0-9]{1,6}$/.test(n) || Number(n) < 1) return '';
        return String(Math.min(capOf(), Number(n)));
      case 'max_total_mb':
        n = asciiNumber(v);
        if (!/^[0-9]{1,6}(\.[0-9]{1,6})?$/.test(n) || Number(n) <= 0) return '';
        return trimNum(Math.min(10240, Math.round(Number(n) * 100) / 100));
      case 'capture':
        n = String(v).trim().toLowerCase();
        return n === 'user' || n === 'environment' ? n : '';
      case 'img_max_px':
        n = asciiNumber(v);
        if (!/^[0-9]{1,6}$/.test(n) || Number(n) < 1) return '';
        return String(Math.max(320, Math.min(8000, Number(n))));
      case 'img_quality':
        n = asciiNumber(v);
        if (n === '' || n === '.' || !/^(0|1)?(\.[0-9]{1,4})?$/.test(n)) return '';
        return trimNum(Math.max(0.5, Math.min(0.95, Math.round(Number(n) * 100) / 100)));
      case 'file_store':
        n = String(v).trim().toLowerCase();
        return n === 'media' || n === 'private' ? n : '';
      case 'email_attach':
        return v === 1 || v === '1' || v === 'true' || v === 'on' ? '1' : '';
    }
    return '';
  }
  function presetOf(row) {
    var p = row && typeof row.file === 'string' && row.file !== '' ? row.file : (row && typeof row.value === 'string' ? row.value : '');
    p = String(p || '').trim().toLowerCase();
    return p === '' ? 'allformat' : p;
  }
  /** The effective configuration of a field row (Emsfb\Uploads::config()); plan = {multi, resize, media}. */
  function config(row, plan) {
    plan = plan || (root.efb_var && root.efb_var.uploads && root.efb_var.uploads.plan) || { multi: true, resize: true, media: true };
    var type = row && row.type ? String(row.type) : '';
    var multiType = MULTI_TYPES.indexOf(type) !== -1;
    var preset = presetOf(row);
    var max = multiType ? Number(sanitizeKey('max_files', row.max_files)) || 1 : 1;
    if (max > 1 && plan.multi === false) max = 1;
    var required = row && (row.required === 1 || row.required === '1' || row.required === true);
    var min = multiType ? Number(sanitizeKey('min_files', row.min_files)) || 0 : 0;
    min = Math.min(max, min);
    if (required) min = Math.max(1, min);
    var mb = row && Number(row.max_fsize) > 0 ? Number(row.max_fsize) : 20;
    var total = multiType && max > 1 ? Number(sanitizeKey('max_total_mb', row.max_total_mb)) || 0 : 0;
    if (total > 0 && total < mb) total = mb;
    var store = multiType ? sanitizeKey('file_store', row.file_store) : '';
    if (store === 'media' && plan.media === false) store = '';
    var q = sanitizeKey('img_quality', row && row.img_quality);
    return {
      type: type, multi: max > 1, max_files: max, min_files: min, required: !!required, max_mb: mb,
      max_total_mb: total, preset: preset,
      capture: multiType && CAPTURE_PRESETS.indexOf(preset) !== -1 ? sanitizeKey('capture', row.capture) : '',
      img_max_px: multiType && RESIZE_PRESETS.indexOf(preset) !== -1 && plan.resize !== false ? Number(sanitizeKey('img_max_px', row.img_max_px)) || 0 : 0,
      img_quality: q === '' ? 0.85 : Number(q),
      file_store: store,
    };
  }

  /* ------------------------------------------------------------------ helpers */
  function T(k, fb) {
    try {
      var a = root.ajax_object_efm && root.ajax_object_efm.text;
      if (a && typeof a[k] === 'string' && a[k] !== '') return a[k];
      var e = root.efb_var && root.efb_var.text;
      if (e && typeof e[k] === 'string' && e[k] !== '') return e[k];
    } catch (e) { /* fall back */ }
    return fb;
  }
  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function locale() {
    var l = (root.efb_var && root.efb_var.wp_lan) || (root.ajax_object_efm && root.ajax_object_efm.wp_lan) || 'en';
    return String(l).replace('_', '-');
  }
  /** A number in the site's digits (Persian on fa_*, Arabic-Indic on ar*, as the F06 calendar does). */
  function num(n, frac) {
    var loc = locale();
    var opts = { maximumFractionDigits: frac || 0 };
    if (/^fa\b/i.test(loc)) opts.numberingSystem = 'arabext';
    else if (/^ar\b/i.test(loc)) opts.numberingSystem = 'arab';
    try { return new Intl.NumberFormat(loc, opts).format(n); } catch (e) { return String(n); }
  }
  function sizeText(bytes) {
    if (bytes < MB) return T('fileSizeKB', '%s KB').replace('%s', num(Math.max(1, Math.round(bytes / 1024))));
    return T('fileSizeMB', '%s MB').replace('%s', num(Math.round((bytes / MB) * 10) / 10, 1));
  }
  /** A value inside a sentence: isolated (FSI … PDI) so a mixed-script sentence keeps its order. */
  function iso(v) { return '<bdi dir="auto">' + esc(v) + '</bdi>'; }
  function extOf(name) {
    var i = String(name || '').lastIndexOf('.');
    return i === -1 ? '' : String(name).slice(i + 1).toLowerCase();
  }
  function uidOf() { return 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
  function structure(formId) {
    try { if (typeof root.get_structure_by_form_id_efb === 'function') return root.get_structure_by_form_id_efb(formId) || []; } catch (e) {}
    /* global valj_efb */
    if (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb)) return valj_efb;
    return Array.isArray(root.valj_efb) ? root.valj_efb : [];
  }
  function fieldRow(fieldId, formId) {
    var s = structure(formId);
    for (var i = 0; i < s.length; i++) if (s[i] && s[i].id_ === fieldId) return s[i];
    return null;
  }
  /* core-efb.js declares these with let: global lexical bindings, not window properties. */
  function sendBack() {
    /* global sendBack_emsFormBuilder_pub */
    if (typeof sendBack_emsFormBuilder_pub !== 'undefined' && Array.isArray(sendBack_emsFormBuilder_pub)) return sendBack_emsFormBuilder_pub;
    return Array.isArray(root.sendBack_emsFormBuilder_pub) ? root.sendBack_emsFormBuilder_pub : null;
  }
  function session() {
    /* global sessionPub_emsFormBuilder */
    if (typeof sessionPub_emsFormBuilder !== 'undefined') return sessionPub_emsFormBuilder;
    return root.sessionPub_emsFormBuilder;
  }
  function storeSendBack() {
    try {
      var sb = sendBack();
      if (sb && typeof root.efb_sendback_for_storage_efb === 'function') localStorage.setItem('sendback', root.efb_sendback_for_storage_efb(sb));
    } catch (e) { /* storage blocked */ }
  }
  function filesList() {
    /* global files_emsFormBuilder */
    if (typeof files_emsFormBuilder !== 'undefined' && Array.isArray(files_emsFormBuilder)) return files_emsFormBuilder;
    return Array.isArray(root.files_emsFormBuilder) ? root.files_emsFormBuilder : null;
  }

  /* ------------------------------------------------------------------ fields */
  var fields = {}; // key `${formId}|${fieldId}` -> state

  function keyOf(fieldId, formId) { return String(formId || 0) + '|' + fieldId; }

  function readLimits(input) {
    var d = input.dataset || {};
    var accept = String(input.getAttribute('accept') || '');
    var exts = accept.split(',').map(function (x) { return x.trim().toLowerCase(); }).filter(function (x) { return x.charAt(0) === '.'; }).map(function (x) { return x.slice(1); });
    return {
      max: Math.max(1, Number(d.maxFiles) || 1),
      min: Math.max(0, Number(d.minFiles) || 0),
      maxMb: Number(d.maxMb) > 0 ? Number(d.maxMb) : 20,
      totalMb: Number(d.maxTotal) > 0 ? Number(d.maxTotal) : 0,
      imgMax: Number(d.imgMax) > 0 ? Number(d.imgMax) : 0,
      imgQ: Number(d.imgQ) > 0 ? Number(d.imgQ) : 0.85,
      exts: exts,
    };
  }

  /**
   * Bind one field: the input, its drop zone (dadfile), list and queue. Answers false for a field of
   * one file (the old path runs), true when this module now runs the field.
   */
  function bind(fieldId, formId) {
    var scope = document.getElementById(formId ? 'body_efb_' + formId : 'body_efb') || document;
    var input = scope.querySelector ? scope.querySelector('[id="' + fieldId + '_"]') : null;
    if (!input) input = document.getElementById(fieldId + '_');
    if (!input || input.type !== 'file') return false;
    var lim = readLimits(input);
    if (lim.max <= 1) return false;
    var key = keyOf(fieldId, formId);
    if (fields[key] && fields[key].input === input) return true;
    var st = {
      id: fieldId, formId: Number(formId) || 0, input: input, lim: lim, items: [],
      list: document.getElementById(fieldId + '_-list'), sum: document.getElementById(fieldId + '_-sum'),
      box: document.getElementById(fieldId + '_box'), running: 0,
    };
    fields[key] = st;
    if (!st.list) {
      st.list = document.createElement('ul');
      st.list.id = fieldId + '_-list';
      st.list.className = 'efb efb-file-list list-unstyled';
      input.parentNode.insertBefore(st.list, input.nextSibling);
    }
    if (!st.sum) {
      st.sum = document.createElement('div');
      st.sum.id = fieldId + '_-sum';
      st.sum.className = 'efb efb-file-sum';
      st.list.parentNode.insertBefore(st.sum, st.list.nextSibling);
    }
    st.sum.setAttribute('aria-live', 'polite');
    st.live = document.createElement('span');
    st.live.className = 'efb efb-sr-only';
    st.live.setAttribute('aria-live', 'polite');
    st.live.setAttribute('role', 'status');
    st.sum.parentNode.insertBefore(st.live, st.sum.nextSibling);
    input.multiple = true;
    input.addEventListener('change', function () {
      var chosen = Array.prototype.slice.call(input.files || []);
      if (chosen.length) queue(fieldId, formId, chosen);
      try { input.value = ''; } catch (e) { /* old browsers */ }
    });
    if (st.box) bindDrop(st);
    render(st);
    return true;
  }

  function bindDrop(st) {
    var box = st.box;
    var txt = document.getElementById(st.id + '_txt');
    var original = txt ? txt.innerHTML : '';
    box.setAttribute('role', 'button');
    box.setAttribute('tabindex', '0');
    if (txt && txt.id) box.setAttribute('aria-describedby', txt.id + (st.sum ? ' ' + st.sum.id : ''));
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault();
        if (!st.input.disabled) st.input.click();
      }
    });
    box.addEventListener('dragover', function (e) {
      e.preventDefault();
      box.classList.add('active');
      if (txt) txt.textContent = T('releaseToUpload', 'Release to upload');
    });
    box.addEventListener('dragleave', function () {
      box.classList.remove('active');
      if (txt) txt.innerHTML = original;
    });
    box.addEventListener('drop', function (e) {
      e.preventDefault();
      box.classList.remove('active');
      if (txt) txt.innerHTML = original;
      var files = e.dataTransfer && e.dataTransfer.files ? Array.prototype.slice.call(e.dataTransfer.files) : [];
      if (files.length && !st.input.disabled) queue(st.id, st.formId, files);
    });
  }

  function live(st, text) {
    if (!st.live) return;
    st.live.textContent = '';
    setTimeout(function () { st.live.textContent = text; }, 30);
  }

  function counted(st) { return st.items.filter(function (it) { return it.state !== 'rejected'; }); }
  function bytesTaken(st, except) {
    return counted(st).reduce(function (a, it) { return it === except ? a : a + (it.size || 0); }, 0);
  }

  /** The browser's own checks of one file against the field (§8.1); null when it may go. */
  function refuse(st, file, size) {
    var lim = st.lim;
    var ext = extOf(file.name);
    if (lim.exts.length && lim.exts.indexOf(ext) === -1) return T('fileTypeRefused', 'This file type is not accepted here.');
    if (size > lim.maxMb * MB) return T('fileTooBigMB', 'This file is larger than %s MB.').replace('%s', num(lim.maxMb, 2));
    if (lim.totalMb > 0 && bytesTaken(st) + size > lim.totalMb * MB) return T('fileTotalOver', 'This file would pass the total limit of %s MB.').replace('%s', num(lim.totalMb, 2));
    return null;
  }

  /** Add files to the queue (never replaces what is there). Answers the items added. */
  function queue(fieldId, formId, files) {
    var st = fields[keyOf(fieldId, formId)];
    if (!st) { if (!bind(fieldId, formId)) return []; st = fields[keyOf(fieldId, formId)]; }
    var added = [];
    (files || []).forEach(function (file) {
      if (!file) return;
      if (counted(st).length >= st.lim.max) {
        var over = { uid: uidOf(), file: file, name: file.name, size: file.size, state: 'rejected', err: T('maxFilesReached', 'Maximum number of files reached') };
        st.items.push(over);
        added.push(over);
        return;
      }
      var it = { uid: uidOf(), file: file, name: file.name, size: file.size, state: 'queued', progress: 0 };
      var why = refuse(st, file, file.size);
      if (why) { it.state = 'rejected'; it.err = why; }
      st.items.push(it);
      added.push(it);
      if (it.state === 'queued' && st.lim.imgMax > 0 && /^image\/(jpeg|png|webp)$/.test(file.type || '')) {
        it.state = 'resizing';
        resizeImage(file, st.lim.imgMax, st.lim.imgQ).then(function (out) {
          if (it.state !== 'resizing') return;
          it.file = out;
          it.size = out.size;
          it.state = 'queued';
          var again = refuse(st, out, out.size);
          if (again) { it.state = 'rejected'; it.err = again; }
          render(st);
          pump(st);
        });
      }
    });
    render(st);
    pump(st);
    return added;
  }

  /**
   * Make a photo smaller in the browser (§3.3): the longest side at most maxPx, JPEG at quality q
   * (a PNG stays PNG), orientation from EXIF; the canvas drops EXIF, location included. GIF, HEIC
   * and SVG are never touched, and a result larger than the original keeps the original. Any
   * failure (no createImageBitmap, no canvas) also keeps the original.
   */
  function resizeImage(file, maxPx, q) {
    var keep = Promise.resolve(file);
    if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type || '') || typeof root.createImageBitmap !== 'function') return keep;
    return root.createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      var w = bmp.width, h = bmp.height;
      var scale = Math.min(1, maxPx / Math.max(w, h));
      var png = file.type === 'image/png';
      if (scale >= 1 && png) { if (bmp.close) bmp.close(); return file; }
      var cw = Math.max(1, Math.round(w * scale)), ch = Math.max(1, Math.round(h * scale));
      var canvas = document.createElement('canvas');
      canvas.width = cw; canvas.height = ch;
      var ctx = canvas.getContext('2d');
      if (!ctx) return file;
      ctx.drawImage(bmp, 0, 0, cw, ch);
      if (bmp.close) bmp.close();
      var type = png ? 'image/png' : 'image/jpeg';
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          if (!blob || blob.size >= file.size) { resolve(file); return; }
          var name = png ? file.name : String(file.name).replace(/\.(jpe?g|webp)$/i, '') + '.jpg';
          try { resolve(new File([blob], name, { type: type, lastModified: Date.now() })); } catch (e) { blob.name = name; resolve(blob); }
        }, type, png ? undefined : q);
      });
    }).catch(function () { return file; });
  }

  function busy(st) { return st.items.some(function (it) { return it.state === 'queued' || it.state === 'uploading' || it.state === 'resizing'; }); }

  function markInflight(st, it, on) {
    var list = filesList();
    if (!list) return;
    var i = list.findIndex(function (x) { return x && x.uid === it.uid; });
    if (on && i === -1) list.push({ id_: st.id, uid: it.uid, value: '@file@', state: 1, url: '', type: 'file', form_id: st.formId, multi: true });
    if (!on && i !== -1) list.splice(i, 1);
  }

  function pump(st) {
    while (st.running < CONCURRENCY) {
      var next = st.items.find(function (it) { return it.state === 'queued'; });
      if (!next) break;
      upload(st, next);
    }
    render(st);
  }

  function runtimeOf(formId) {
    var fallback = function () {
      var sid = '';
      /* global fun_sid_efb */
      try { sid = typeof fun_sid_efb === 'function' ? (fun_sid_efb(formId) || {}).sid || '' : (typeof root.fun_sid_efb === 'function' ? (root.fun_sid_efb(formId) || {}).sid || '' : ''); } catch (e) {}
      return { sid: sid || (root.efb_var && root.efb_var.sid) || '', nonce: (root.efb_var && root.efb_var.nonce) || '' };
    };
    if (root.EFBRuntime && typeof root.EFBRuntime.prepare === 'function') {
      return Promise.resolve(root.EFBRuntime.prepare(Number(formId) || 0)).then(function (r) { return r && r.sid ? r : fallback(); }, fallback);
    }
    return Promise.resolve(fallback());
  }

  function upload(st, it) {
    it.state = 'uploading';
    it.progress = 0;
    it.err = '';
    st.running += 1;
    markInflight(st, it, true);
    var done = function () { st.running = Math.max(0, st.running - 1); markInflight(st, it, false); render(st); pump(st); };
    var send = root.fetch_uploadFile;
    if (typeof send !== 'function') { it.state = 'error'; it.err = T('fileFailed', 'Upload failed'); done(); return; }
    runtimeOf(st.formId).then(function (rt) {
      var pageId = root.efb_var && root.efb_var.page_id;
      return send(it.file, st.id, 'msg', rt.nonce, pageId, st.formId, rt.sid, {
        onProgress: function (p) { it.progress = p; paintProgress(st, it); },
      });
    }).then(function (res) {
      var d = res && res.data ? res.data : res;
      if (!(res && res.success === true && d && d.success === true && d.file && d.file.url)) {
        it.state = 'error';
        it.err = (d && (d.error || d.m)) || T('fileFailed', 'Upload failed');
        live(st, it.name + ': ' + T('fileFailed', 'Upload failed'));
        done();
        return;
      }
      it.state = 'done';
      it.progress = 100;
      it.url = d.file.url;
      it.stored = d.name;
      it.ut = d.ut || '';
      if (Number(d.size) > 0) it.size = Number(d.size);
      addRow(st, it);
      live(st, it.name + ': ' + T('fileUploaded', 'Uploaded'));
      done();
    }, function (err) {
      it.state = 'error';
      it.err = typeof err === 'string' ? err : (err && err.message) || T('fileFailed', 'Upload failed');
      live(st, it.name + ': ' + T('fileFailed', 'Upload failed'));
      done();
    });
  }

  function addRow(st, it) {
    var sb = sendBack();
    if (!sb) return;
    var f = fieldRow(st.id, st.formId) || {};
    var row = {
      id_: st.id, name: f.name || '', amount: f.amount || 0, type: f.type || 'file', value: '@file@',
      url: it.url, id_ob: it.stored, ut: it.ut, fnm: it.name, fsz: String(it.size || 0),
      session: session(), page_id: root.efb_var && root.efb_var.page_id, form_id: st.formId,
    };
    if (typeof root.fun_sendBack_emsFormBuilder === 'function') {
      root.fun_sendBack_emsFormBuilder(row);
    } else {
      sb.push(row);
      storeSendBack();
    }
    var msg = document.getElementById(st.id + '_-message');
    if (msg && typeof root.hide_msg_efb === 'function') root.hide_msg_efb(msg);
  }

  function dropRow(st, it) {
    var sb = sendBack();
    if (!sb || !it.stored) return;
    for (var i = sb.length - 1; i >= 0; i--) {
      var r = sb[i];
      if (r && r.id_ === st.id && r.id_ob === it.stored && Number(r.form_id || st.formId) === Number(st.formId)) sb.splice(i, 1);
    }
    storeSendBack();
  }

  /**
   * Remove one file from the answer. A stored file only leaves the answer; the daily cleanup
   * deletes it (no delete request from the page, so there is no unauthenticated delete route).
   */
  function remove(fieldId, formId, uid) {
    var st = fields[keyOf(fieldId, formId)];
    if (!st) return false;
    var i = st.items.findIndex(function (x) { return x.uid === uid; });
    if (i === -1) return false;
    var it = st.items[i];
    if (it.state === 'uploading') return false;
    it.state = 'removed';
    dropRow(st, it);
    if (it.thumb) { try { URL.revokeObjectURL(it.thumb); } catch (e) {} }
    st.items.splice(i, 1);
    render(st);
    live(st, T('fileRemovedA11y', '%s removed').replace('%s', it.name));
    // Focus goes to the next item's button, or back to the picker.
    var buttons = st.list.querySelectorAll('.efb-file-rm');
    var target = buttons[Math.min(i, buttons.length - 1)] || (st.box && st.box.getAttribute('tabindex') === '0' ? st.box : st.input);
    try { target.focus(); } catch (e) {}
    return true;
  }

  function retry(fieldId, formId, uid) {
    var st = fields[keyOf(fieldId, formId)];
    if (!st) return false;
    var it = st.items.find(function (x) { return x.uid === uid; });
    if (!it || it.state !== 'error') return false;
    it.state = 'queued';
    pump(st);
    return true;
  }

  /** Answer rows of a form (or of one field) as they will be sent. */
  function rows(formId, fieldId) {
    var sb = sendBack() || [];
    return sb.filter(function (r) {
      return r && r.value === '@file@' && typeof r.url === 'string' && r.url !== ''
        && (fieldId === undefined || r.id_ === fieldId)
        && (formId === undefined || Number(r.form_id) === Number(formId))
        && fields[keyOf(r.id_, r.form_id)];
    });
  }

  function paintProgress(st, it) {
    var li = st.list.querySelector('[data-uid="' + it.uid + '"]');
    if (!li) return;
    var bar = li.querySelector('.efb-file-bar');
    if (bar) {
      bar.setAttribute('aria-valuenow', String(it.progress || 0));
      var fill = bar.firstElementChild;
      if (fill) fill.style.width = (it.progress || 0) + '%';
    }
  }

  /* Inline icons: the page's icon subset is built from the saved form, so a class-based icon added
     here might not be in it. */
  var ICON_FILE = '<svg class="efb efb-file-icon" viewBox="0 0 16 16" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M4 0h5.5L14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm5 1v3.5A1.5 1.5 0 0 0 10.5 6H13v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5z"/></svg>';
  var ICON_X = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2.15 2.15a.5.5 0 0 1 .7 0L8 7.29l5.15-5.14a.5.5 0 0 1 .7.7L8.71 8l5.14 5.15a.5.5 0 0 1-.7.7L8 8.71l-5.15 5.14a.5.5 0 0 1-.7-.7L7.29 8 2.15 2.85a.5.5 0 0 1 0-.7z"/></svg>';

  function stateText(it) {
    switch (it.state) {
      case 'queued': return T('fileQueued', 'Waiting');
      case 'resizing': return T('fileResizing', 'Preparing');
      case 'uploading': return T('fileUploading', 'Uploading');
      case 'done': return T('fileUploaded', 'Uploaded');
      default: return it.err || T('fileFailed', 'Upload failed');
    }
  }

  function render(st) {
    if (!st.list) return;
    var html = '';
    st.items.forEach(function (it) {
      var isImg = it.file && /^image\//.test(it.file.type || '') && typeof URL !== 'undefined' && URL.createObjectURL;
      if (isImg && !it.thumb) { try { it.thumb = URL.createObjectURL(it.file); } catch (e) { it.thumb = ''; } }
      var icon = it.thumb ? '<img class="efb efb-file-thumb" src="' + esc(it.thumb) + '" alt="">' : ICON_FILE;
      var short = it.name.length > 40 ? it.name.slice(0, 26) + '…' + it.name.slice(-10) : it.name;
      var bad = it.state === 'rejected' || it.state === 'error';
      html += '<li class="efb efb-file-item efb-file-' + it.state + '" data-uid="' + esc(it.uid) + '">'
        + icon
        + '<div class="efb efb-file-main">'
        + '<span class="efb efb-file-name" dir="auto" title="' + esc(it.name) + '">' + esc(short) + '</span>'
        + '<span class="efb efb-file-meta' + (bad ? ' text-danger' : '') + '" dir="auto">' + iso(sizeText(it.size || 0)) + ' · ' + esc(stateText(it)) + '</span>'
        + (it.state === 'uploading' || it.state === 'queued' || it.state === 'resizing'
          ? '<div class="efb efb-file-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + (it.progress || 0) + '" aria-label="' + esc(it.name) + '"><span style="width:' + (it.progress || 0) + '%"></span></div>' : '')
        + '</div>'
        + (it.state === 'error' ? '<button type="button" class="efb btn btn-sm efb-file-retry" data-uid="' + esc(it.uid) + '">' + esc(T('retryUpload', 'Try again')) + '</button>' : '')
        + (it.state !== 'uploading' ? '<button type="button" class="efb efb-file-rm" data-uid="' + esc(it.uid) + '" aria-label="' + esc(T('removeFileNamed', 'Remove %s').replace('%s', it.name)) + '">' + ICON_X + '</button>' : '')
        + '</li>';
    });
    st.list.innerHTML = html;
    var rtl = false;
    try { rtl = getComputedStyle(st.list).direction === 'rtl'; } catch (e) { rtl = false; }
    st.list.classList.toggle('efb-file-rtl', rtl);
    st.sum.classList.toggle('efb-file-rtl', rtl);
    Array.prototype.forEach.call(st.list.querySelectorAll('.efb-file-rm'), function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); remove(st.id, st.formId, b.getAttribute('data-uid')); });
    });
    Array.prototype.forEach.call(st.list.querySelectorAll('.efb-file-retry'), function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); retry(st.id, st.formId, b.getAttribute('data-uid')); });
    });
    var n = counted(st).length;
    var full = n >= st.lim.max;
    var sum = '<span dir="auto" class="efb efb-file-count">' + T('filesCountOf', '%1$s of %2$s files').replace('%1$s', iso(num(n))).replace('%2$s', iso(num(st.lim.max)))
      + (st.lim.totalMb > 0 ? ' · ' + iso(T('fileSizeMB', '%s MB').replace('%s', num(Math.round((bytesTaken(st) / MB) * 10) / 10, 1))) + ' / ' + iso(T('fileSizeMB', '%s MB').replace('%s', num(st.lim.totalMb, 2))) : '') + '</span>'
      + (full ? ' <span dir="auto" class="efb efb-file-full">' + esc(T('maxFilesReached', 'Maximum number of files reached')) + '</span>' : '');
    st.sum.innerHTML = n > 0 || full ? sum : '';
    st.input.disabled = full || st.input.hasAttribute('readonly');
    if (st.box) {
      st.box.classList.toggle('efb-file-full', full);
      st.box.setAttribute('aria-disabled', full ? 'true' : 'false');
    }
  }

  /** Whether files of this field (or of the form) are still on their way. */
  function isBusy(formId, fieldId) {
    return Object.keys(fields).some(function (k) {
      var st = fields[k];
      return Number(st.formId) === Number(formId) && (fieldId === undefined || st.id === fieldId) && busy(st);
    });
  }
  function isMulti(fieldId, formId) {
    var st = fields[keyOf(fieldId, formId)];
    if (st) return true;
    var el = document.getElementById(fieldId + '_');
    return !!(el && el.type === 'file' && Number(el.dataset && el.dataset.maxFiles) > 1);
  }
  /** Files of a field that reached the server. */
  function count(fieldId, formId) {
    var st = fields[keyOf(fieldId, formId)];
    return st ? st.items.filter(function (it) { return it.state === 'done'; }).length : 0;
  }
  /**
   * The Next / Send check of one field (§8.1): null, or {code, message} — files still uploading,
   * fewer than the minimum (when answered or required), more than the maximum.
   */
  function check(fieldId, formId) {
    var st = fields[keyOf(fieldId, formId)];
    if (!st) return null;
    var row = fieldRow(fieldId, formId) || {};
    var label = String(row.name || '');
    if (busy(st)) return { code: 'uploading', message: T('fileWaitUploads', 'Please wait until the files finish uploading.') };
    var n = count(fieldId, formId);
    var required = row.required === 1 || row.required === '1' || row.required === true;
    var min = Math.max(st.lim.min, required ? 1 : 0);
    if ((n > 0 || required) && min > 0 && n < min) {
      return { code: 'min_files', message: T('minFilesMsg', 'Please attach at least %1$s files to %2$s.').replace('%1$s', num(min)).replace('%2$s', label) };
    }
    if (n > st.lim.max) return { code: 'max_files', message: T('maxFilesMsg', 'You can attach up to %1$s files to %2$s.').replace('%1$s', num(st.lim.max)).replace('%2$s', label) };
    return null;
  }

  /** multiple / capture / data-* of a field row, for the builder canvas and the in-admin preview. */
  function attrs(row) {
    if (!row || (row.type !== 'file' && row.type !== 'dadfile')) return '';
    var c = config(row);
    var a = ' data-max-files="' + c.max_files + '" data-min-files="' + c.min_files + '" data-max-mb="' + esc(c.max_mb) + '"';
    if (c.multi) a += ' multiple';
    if (c.capture) a += ' capture="' + esc(c.capture) + '"';
    if (c.max_total_mb > 0) a += ' data-max-total="' + esc(c.max_total_mb) + '"';
    if (c.img_max_px > 0) a += ' data-img-max="' + c.img_max_px + '" data-img-q="' + esc(c.img_quality) + '"';
    return a;
  }
  /** The empty file list of a field of several files (same markup as Uploads::list_markup()). */
  function listMarkup(row) {
    if (!row || !config(row).multi) return '';
    var id = esc(row.id_);
    return '<ul id="' + id + '_-list" class="efb efb-file-list list-unstyled" data-efb-upload-list="' + id + '"></ul><div class="efb efb-file-sum" id="' + id + '_-sum" aria-live="polite"></div>';
  }

  root.EFBUpload = {
    attrs: attrs,
    listMarkup: listMarkup,
    version: 1,
    rules: { FIELD_KEYS: FIELD_KEYS, FORM_KEYS: FORM_KEYS, MULTI_TYPES: MULTI_TYPES, sanitizeKey: sanitizeKey, config: config, presetOf: presetOf },
    bind: bind,
    queue: queue,
    remove: remove,
    retry: retry,
    rows: rows,
    count: count,
    check: check,
    isBusy: isBusy,
    isMulti: isMulti,
    resizeImage: resizeImage,
    _fields: fields,
    _num: num,
    _sizeText: sizeText,
  };
})(typeof window !== 'undefined' ? window : this);
