/**
 * F07 in the builder: the controls of file / dadfile fields (several files, minimum, total size,
 * camera, image resizing, where files are kept), the form switch "Attach uploaded files to the
 * admin email", their handlers and the canvas preview. Spec: docs/feature-gaps/F07-file-upload.fa.md (§5).
 *
 * Every key is a string on the field row ('' = not set) and is checked again on the server
 * (sanitize_obj_msg_efb → Emsfb\Uploads::sanitize_key, then Uploads::normalize_rows). These controls
 * write valj_efb directly with the same rules (EFBUpload.rules.sanitizeKey, upload-efb.js), like the
 * F03 controls, without change_el_edit_Efb (which runs on a timer). efbBuilderApi.setUploadConfig()
 * uses the same rules.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const U = () => root.EFBUpload || null;
  const icon = () => (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1');
  const CAP_KEY = { multi: 'setting:upload_multi', resize: 'setting:upload_resize', media: 'setting:upload_media', attach: 'setting:email_attach' };

  function plan(part) {
    const key = CAP_KEY[part];
    if (!key) return true;
    if (typeof efb_capability_available_efb === 'function') { try { return efb_capability_available_efb(key, isPaid(part)); } catch (e) {} }
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features;
    if (cap && cap[key]) return !!cap[key].available_now;
    return isPaid(part);
  }
  function isPaid(part) {
    const up = root.efb_var && root.efb_var.uploads && root.efb_var.uploads.plan;
    if (up && typeof up[part] === 'boolean') return up[part];
    return (typeof pro_efb !== 'undefined' && pro_efb === true) || !!(root.efb_var && (root.efb_var.pro == '1' || root.efb_var.pro === 1 || root.efb_var.pro === true));
  }
  function gem(part) {
    if (plan(part)) return '';
    const label = part === 'attach' ? T('upgTipPro', 'Pro') : T('upgTipFreePlus', 'Free Plus');
    return ` <a type="button" role="button" tabindex="0" class="efb pro-version-efb efb-up-gem" data-cap="${CAP_KEY[part]}" onclick="efb_up_gem_efb(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();efb_up_gem_efb(this);}" title="${esc(label)}" aria-label="${esc(label)}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  const lock = (part) => (part && !plan(part) ? ' disabled aria-disabled="true"' : '');

  function rowById(dataId) {
    if (typeof valj_efb === 'undefined') return null;
    const i = valj_efb.findIndex((x) => x && x.dataId == dataId);
    return i > 0 ? valj_efb[i] : null;
  }
  function preset(r) { return U() ? U().rules.presetOf(r) : String((r && (r.file || r.value)) || 'allformat'); }

  function numEl(r, idset, id, key, label, opts) {
    opts = opts || {};
    const v = r && r[key] !== undefined && r[key] !== null ? String(r[key]) : '';
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}" id="${id}Box">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <input type="text" inputmode="numeric" maxlength="7" class="efb form-control efb h-d-efb border-d rounded-4 efb-up" id="${id}" data-id="${esc(idset)}" data-key="${key}" value="${esc(v)}" placeholder="${esc(opts.placeholder || '')}" ${opts.help ? `aria-describedby="${id}Help"` : ''} onchange="efb_up_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8" id="${id}Help">${esc(opts.help)}</small>` : ''}
    </div>`;
  }
  function selEl(r, idset, id, key, label, options, dflt, opts) {
    opts = opts || {};
    const cur = r && typeof r[key] === 'string' && r[key] !== '' ? r[key] : dflt;
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}" id="${id}Box">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-up" id="${id}" data-id="${esc(idset)}" data-key="${key}" ${opts.help ? `aria-describedby="${id}Help"` : ''} onchange="efb_up_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
        ${options.map((o) => `<option value="${esc(o[0])}" ${cur === o[0] ? 'selected' : ''} ${o[2] ? 'disabled' : ''}>${esc(o[1])}</option>`).join('')}
      </select>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8" id="${id}Help">${esc(opts.help)}</small>` : ''}
      ${opts.after || ''}
    </div>`;
  }

  /** The F07 controls of a file / dadfile field (after "Max File Size", §5). */
  function efbUploadSettingsEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    if (r.type !== 'file' && r.type !== 'dadfile') return '';
    const p = preset(r);
    const cap = (root.efb_var && root.efb_var.uploads && root.efb_var.uploads.max_files_cap) || 20;
    const canMedia = !(root.efb_var && root.efb_var.uploads && root.efb_var.uploads.can_media === false);
    const showCapture = p === 'image' || p === 'media';
    const showResize = p === 'image' || p === 'allformat' || p === 'customize';
    const q = String(r.img_quality || '0.85');
    return `<div class="efb mt-3 efb-up-settings" id="upSettingsBoxEl">
      <div class="efb row g-2">
        ${numEl(r, idset, 'maxFilesEl', 'max_files', T('maxFiles', 'Maximum number of files'), { wrap: 'col-6', placeholder: '1', plan: 'multi', help: '1–' + cap })}
        ${numEl(r, idset, 'minFilesEl', 'min_files', T('minFiles', 'Minimum number of files'), { wrap: 'col-6', placeholder: '0', plan: 'multi' })}
      </div>
      ${numEl(r, idset, 'maxTotalMbEl', 'max_total_mb', T('maxTotalSize', 'Maximum total size (MB)'), { placeholder: '0', plan: 'multi' })}
      <small class="efb text-danger fs-8 mx-2 d-none" id="maxTotalWarnEl" role="alert"></small>
      <div class="${showCapture ? '' : 'd-none'}" id="fileCaptureWrapEl">
        ${selEl(r, idset, 'fileCaptureEl', 'capture', T('fileCapture', 'Camera'), [['', T('captureNone', 'None (file or camera)')], ['user', T('captureUser', 'Front camera')], ['environment', T('captureEnv', 'Back camera')]], '', { help: T('fileCaptureHelp', 'With a camera set, phones open the camera only and the gallery cannot be used.') })}
      </div>
      <div class="${showResize ? '' : 'd-none'}" id="imgResizeWrapEl">
        <div class="efb row g-2">
          ${numEl(r, idset, 'imgMaxPxEl', 'img_max_px', T('imgMaxPx', 'Resize images to at most (pixels)'), { wrap: 'col-7', placeholder: '0', plan: 'resize' })}
          ${selEl(r, idset, 'imgQualityEl', 'img_quality', T('imgQuality', 'Image quality'), [['0.95', '95%'], ['0.9', '90%'], ['0.85', '85%'], ['0.8', '80%'], ['0.7', '70%'], ['0.6', '60%'], ['0.5', '50%']], q === '' ? '0.85' : q, { wrap: 'col-5', plan: 'resize' })}
        </div>
        <small class="efb form-text text-muted d-block mt-1 fs-8">${esc(T('imgMaxPxHelp', "0 = keep the original. Photos are made smaller in the visitor's browser before upload, which also removes their location data (EXIF)."))}</small>
      </div>
      ${selEl(r, idset, 'fileStoreEl', 'file_store', T('fileStore', 'Where to store files'), [
        ['', T('fileStorePublic', 'Uploads folder (public link)')],
        ['media', T('fileStoreMedia', 'Media Library'), !canMedia || !plan('media')],
        ['private', T('fileStorePrivate', 'Private (download through WordPress only)')],
      ], '', { help: T('fileStoreHelp', 'Private files are kept out of the public uploads address and reach you through the entry viewer or a signed link in the e-mail.'), after: '<div class="efb mt-1" id="fileStoreProbeEl" aria-live="polite"></div>' })}
    </div>`;
  }

  /** Form settings (Advanced): "Attach uploaded files to the admin email" (Pro, §7.2). */
  function efbUploadFormEls() {
    if (typeof valj_efb === 'undefined' || !valj_efb[0]) return '';
    const on = valj_efb[0].email_attach === '1' || valj_efb[0].email_attach === 1;
    return `<div class="efb mx-1 my-3 efb efb-up-attach">
      <button type="button" id="emailAttachEl" data-id="formSet" data-key="email_attach" class="efb mx-0 btn h-s-efb btn-toggle efb-up ${on ? 'active' : ''}" aria-pressed="${on ? 'true' : 'false'}" aria-describedby="emailAttachHelpEl" autocomplete="off" onclick="efb_up_change_efb(this)"${lock('attach')}>
        <div class="efb handle"></div>
      </button>
      <label class="efb form-check-label pt-1" for="emailAttachEl">${esc(T('emailAttach', 'Attach uploaded files to the admin email'))}${gem('attach')}</label>
      <small class="efb form-text text-muted d-block fs-8 mx-2" id="emailAttachHelpEl">${esc(T('emailAttachHelp', 'Up to 10 MB in total; larger files stay links. Visitors never receive the files by e-mail.'))}</small>
    </div>`;
  }

  function efb_up_change_efb(el) {
    if (!el || el.getAttribute('aria-disabled') === 'true') return;
    const key = el.getAttribute('data-key');
    const r = el.getAttribute('data-id') === 'formSet' ? (typeof valj_efb !== 'undefined' ? valj_efb[0] : null) : rowById(el.getAttribute('data-id'));
    if (!key || !r || !U()) return;
    let raw;
    if (el.classList.contains('btn-toggle')) {
      const isOn = !el.classList.contains('active');
      el.classList.toggle('active', isOn);
      el.setAttribute('aria-pressed', isOn ? 'true' : 'false');
      raw = isOn ? '1' : '';
    } else {
      raw = el.value;
    }
    const v = U().rules.sanitizeKey(key, raw);
    r[key] = v;
    if (el.tagName === 'INPUT' && el.value !== v) el.value = v;
    efb_up_after_change_efb(r, key);
    if (key === 'file_store' && v === 'private') efb_up_probe_efb();
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) {} }
  }

  /** The rules between keys (as the save applies them) and the canvas preview. */
  function efb_up_after_change_efb(r, key) {
    if (!r || r.type === undefined || (r.type !== 'file' && r.type !== 'dadfile')) return;
    const max = Number(r.max_files) || 1;
    if (r.min_files && Number(r.min_files) > max) {
      r.min_files = String(max);
      const m = document.getElementById('minFilesEl');
      if (m) m.value = r.min_files;
    }
    const warn = document.getElementById('maxTotalWarnEl');
    if (warn) {
      const per = Number(r.max_fsize) > 0 ? Number(r.max_fsize) : 20;
      const total = Number(r.max_total_mb) || 0;
      const host = Number(root.efb_var && root.efb_var.upload_max) || 0;
      let msg = '';
      if (total > 0 && total < per) msg = T('maxTotalLtFile', 'The total size must be at least the per-file size.');
      else if (total > 0 && host > 0 && max > 1 && total > host * max) msg = T('maxTotalHostWarn', "Visitors cannot send more than %s MB in this field with the hosting's upload limit.").replace('%s', String(host * max));
      warn.textContent = msg;
      warn.classList.toggle('d-none', msg === '');
    }
    efb_up_preset_changed_efb(r);
    efb_up_canvas_efb(r);
  }

  /** The preset (fileTypeEl) changed: the camera only for image / media, resizing where images arrive. */
  function efb_up_preset_changed_efb(r) {
    if (!r) return;
    const p = preset(r);
    const showCapture = p === 'image' || p === 'media';
    const showResize = p === 'image' || p === 'allformat' || p === 'customize';
    if (!showCapture && r.capture) { r.capture = ''; const c = document.getElementById('fileCaptureEl'); if (c) c.value = ''; }
    if (!showResize && r.img_max_px) { r.img_max_px = ''; const i = document.getElementById('imgMaxPxEl'); if (i) i.value = ''; }
    const cw = document.getElementById('fileCaptureWrapEl');
    if (cw) cw.classList.toggle('d-none', !showCapture);
    const rw = document.getElementById('imgResizeWrapEl');
    if (rw) rw.classList.toggle('d-none', !showResize);
    efb_up_canvas_efb(r);
  }

  /** Canvas and preview: multiple, capture and the limits on the field's input. */
  function efb_up_canvas_efb(r) {
    if (!r || !r.id_ || !U()) return;
    const c = U().rules.config(r);
    document.querySelectorAll(`[id="${r.id_}_"]`).forEach((input) => {
      if (input.type !== 'file') return;
      input.multiple = c.multi;
      if (c.capture) input.setAttribute('capture', c.capture); else input.removeAttribute('capture');
      input.setAttribute('data-max-files', String(c.max_files));
      input.setAttribute('data-min-files', String(c.min_files));
      if (c.max_total_mb > 0) input.setAttribute('data-max-total', String(c.max_total_mb)); else input.removeAttribute('data-max-total');
      if (c.img_max_px > 0) input.setAttribute('data-img-max', String(c.img_max_px)); else input.removeAttribute('data-img-max');
    });
  }

  /** The private folder check (§8.2): a warning with the nginx rule when the web server serves it. */
  function efb_up_probe_efb(fresh) {
    const box = document.getElementById('fileStoreProbeEl');
    if (!box || typeof jQuery === 'undefined') return;
    const url = (root.efb_var && root.efb_var.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const nonce = typeof _efb_nonce_ !== 'undefined' ? _efb_nonce_ : (root.efb_var && root.efb_var.nonce);
    jQuery.post(url, { action: 'efb_private_dir_probe_Emsfb', nonce: nonce, fresh: fresh ? 1 : 0 }, (res) => {
      const d = res && res.data;
      if (!d || !d.success) return;
      if (d.exposed === true) {
        box.innerHTML = `<div class="efb alert alert-warning fs-7 my-1 p-2" role="alert"><i class="efb bi-exclamation-triangle-fill ${icon()}" aria-hidden="true"></i>${esc(T('privateDirUnsafe', 'Your web server serves the private folder publicly. Add the rule below or choose another storage.'))}<code class="efb d-block mt-1 text-break" dir="ltr">${esc(d.rule || '')}</code></div>`;
      } else if (d.exposed === false) {
        box.innerHTML = `<small class="efb text-success fs-8 d-block"><i class="efb bi-shield-check ${icon()}" aria-hidden="true"></i>${esc(T('privateDirOk', 'The private folder is not reachable from the web.'))}</small>`;
      } else {
        box.innerHTML = '';
      }
    });
  }

  function efb_up_gem_efb(btn) {
    const cap = btn && btn.getAttribute('data-cap');
    if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: cap, fallback: cap === 'setting:email_attach' ? 'pro' : 'free_plus', origin: 'field_settings', opener: btn });
    else if (typeof efb_capability_prompt_efb === 'function') efb_capability_prompt_efb(cap, cap === 'setting:email_attach' ? 1 : 3, btn);
  }

  const api = { efbUploadSettingsEls, efbUploadFormEls, efb_up_change_efb, efb_up_after_change_efb, efb_up_preset_changed_efb, efb_up_canvas_efb, efb_up_probe_efb, efb_up_gem_efb };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
