/**
 * F03 in the builder: the field-rule controls of text-like fields (settings panel), their handlers
 * and the canvas preview. Spec: docs/feature-gaps/F03-text-validation.fa.md (§6).
 *
 * Every key lives on the field row as a string ('' = not set) and is checked again on the server
 * (sanitize_obj_msg_efb → Field_Rules::sanitize_key); these controls write valj_efb directly with
 * the same rules (EfbRules.sanitizeKey, public/assets/js/field-rules-efb.js), without
 * change_el_edit_Efb (which refuses the characters a pattern needs and runs on a timer).
 * efbBuilderApi.setFieldRules() uses the same rules.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const R = () => root.EfbRules || (typeof EfbRules !== 'undefined' ? EfbRules : null);
  const icon = () => (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1');

  function rowById(dataId) {
    if (typeof valj_efb === 'undefined') return null;
    const i = valj_efb.findIndex((x) => x && x.dataId == dataId);
    return i > 0 ? valj_efb[i] : null;
  }
  function plan(part) {
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features;
    const key = { unique: 'setting:field_unique', pattern: 'setting:field_pattern', domains: 'setting:field_email_domains', strength: 'setting:field_password_strength', messages: 'setting:field_rule_messages' }[part];
    if (!key) return true;
    if (typeof efb_capability_available_efb === 'function') { try { return efb_capability_available_efb(key, isPaid()); } catch (e) {} }
    if (cap && cap[key]) return !!cap[key].available_now;
    return isPaid();
  }
  function isPaid() {
    return (typeof pro_efb !== 'undefined' && pro_efb === true) || !!(root.efb_var && (root.efb_var.pro == '1' || root.efb_var.pro === 1 || root.efb_var.pro === true));
  }
  function gem(part) {
    if (plan(part)) return '';
    const cap = { unique: 'setting:field_unique', pattern: 'setting:field_pattern', domains: 'setting:field_email_domains', strength: 'setting:field_password_strength', messages: 'setting:field_rule_messages' }[part];
    return ` <a type="button" role="button" tabindex="0" class="efb pro-version-efb efb-fr-gem" data-cap="${cap}" onclick="efb_fr_gem_efb(this)" title="${esc(T('upgTipFreePlus', 'Free Plus'))}" aria-label="${esc(T('upgTipFreePlus', 'Free Plus'))}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  const lock = (part) => (part && !plan(part) ? ' disabled aria-disabled="true"' : '');

  /* ------------------------------------------------------------------ controls */
  function textEl(r, idset, id, key, label, opts) {
    opts = opts || {};
    const v = r && typeof r[key] === 'string' ? r[key] : '';
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <input type="text" maxlength="${opts.max || 200}" class="efb form-control efb h-d-efb border-d rounded-4 efb-fr" id="${id}" data-id="${esc(idset)}" data-key="${key}" value="${esc(v)}" placeholder="${esc(opts.placeholder || '')}" ${opts.dir ? `dir="${opts.dir}"` : ''} onchange="efb_fr_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8" id="${id}Help">${opts.help}</small>` : ''}
    </div>`;
  }
  function numEl(r, idset, id, key, label, opts) {
    opts = opts || {};
    const v = r && r[key] !== undefined && r[key] !== '0' ? String(r[key]) : '';
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <input type="text" inputmode="numeric" maxlength="6" class="efb form-control efb h-d-efb border-d rounded-4 efb-fr" id="${id}" data-id="${esc(idset)}" data-key="${key}" value="${esc(v)}" onchange="efb_fr_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
    </div>`;
  }
  function selEl(r, idset, id, key, label, options, dflt, opts) {
    opts = opts || {};
    const cur = r && typeof r[key] === 'string' && r[key] !== '' ? r[key] : dflt;
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-fr" id="${id}" data-id="${esc(idset)}" data-key="${key}" onchange="efb_fr_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
        ${options.map((o) => `<option value="${esc(o[0])}" ${cur === o[0] ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}
      </select>
    </div>`;
  }
  function toggleEl(r, idset, id, key, label, opts) {
    opts = opts || {};
    const R_ = R();
    const active = R_ ? R_.on(r, key, !!opts.dflt) : false;
    return `<div class="efb mx-1 my-2 efb ${opts.wrap || ''}">
      <button type="button" id="${id}" data-id="${esc(idset)}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-fr ${active ? 'active' : ''}" aria-pressed="${active ? 'true' : 'false'}" autocomplete="off" onclick="efb_fr_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
      <div class="efb handle"></div>
      </button>
      <label class="efb form-check-label fs-7" for="${id}">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      ${opts.help ? `<small class="efb form-text text-muted d-block fs-8 mx-2">${opts.help}</small>` : ''}
    </div>`;
  }

  /* ------------------------------------------------------------------ sections */

  /** The rules whose message the owner may write for this field (placeholder = the default text). */
  function msgRules(r) {
    const R_ = R();
    if (!R_ || !r) return [];
    const out = [];
    if (R_.typeOk(r, 'length')) out.push(['minlen', 'tvMinLen_'], ['maxlen', 'tvMaxLen_']);
    if (['text', 'tel', 'postalcode'].includes(r.type)) out.push(['format', 'mskFormat_']);
    if (R_.typeOk(r, 'words')) out.push(['words', 'tvMinWords_']);
    return out;
  }

  /** "Error messages": one box per rule of this field (spec §6 ruleMsgEls), Free Plus like the required message. */
  function efbFieldRulesMsgEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    const rules = msgRules(r);
    if (!rules.length) return '<!-- F03: no rule messages for this field -->';
    const R_ = R();
    const name = typeof r.name === 'string' ? r.name : '';
    const boxes = rules.map(([rule, key]) => {
      const n = { minlen: r.milen, maxlen: r.mlen, words: r.wmin || r.wmax }[rule];
      const ph = R_ ? R_.message({ name: name }, '__', key, root.efb_var && root.efb_var.text, n || 'N').text : '';
      return textEl(r, idset, 'frMsg_' + rule + 'El', 'msg_' + rule, T('tvErrMsgs', 'Error messages') + ': ' + rule, { placeholder: ph, plan: 'messages' });
    }).join('');
    return `<div class="efb mt-3 efb-fr-msgs" id="frMsgsBoxEl">
      <button class="efb btn btn-sm btn-outline-light w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#frMsgsCollapseEl" aria-expanded="false" aria-controls="frMsgsCollapseEl" id="frMsgsBtnEl">
        <i class="efb bi-chat-square-text fs-7 ${icon()}" aria-hidden="true"></i>${esc(T('tvErrMsgs', 'Error messages'))}
      </button>
      <div class="efb collapse" id="frMsgsCollapseEl">
        <small class="efb form-text text-muted d-block mt-1 fs-8">${esc(T('tvErrMsgsHint', 'Leave empty to use the default message. %s is replaced by the field label.'))}</small>
        ${boxes}
      </div>
    </div>`;
  }

  /** Controls of the main (not advanced) part of the panel. */
  function efbFieldRulesMainEls(indx, idset) {
    return '<!-- F03 main -->';
  }

  /** B: word limits and the live counter (text-like fields and textarea). */
  function wordsEls(r, idset) {
    const R_ = R();
    if (!R_ || !R_.typeOk(r, 'words')) return '';
    return `<div class="efb mt-3 efb-fr-words" id="frWordsBoxEl">
      <span class="efb d-block fs-7 fw-bold"><i class="efb bi-fonts fs-7 ${icon()}" aria-hidden="true"></i>${esc(T('tvWords', 'Word limit'))}</span>
      <div class="efb row g-2">
        ${numEl(r, idset, 'wMinEl', 'wmin', T('tvMinWords', 'Minimum words'), { wrap: 'col-6' })}
        ${numEl(r, idset, 'wMaxEl', 'wmax', T('tvMaxWords', 'Maximum words'), { wrap: 'col-6' })}
      </div>
      <div class="efb row g-2">
        ${selEl(r, idset, 'counterEl', 'counter', T('tvCounter', 'Counter'), [['none', T('tvCntNone', 'No counter')], ['chars', T('tvCntChars', 'Characters')], ['words', T('tvCntWords', 'Words')]], 'none', { wrap: 'col-6' })}
        ${selEl(r, idset, 'counterStyleEl', 'counter_style', T('tvCounter', 'Counter'), [['remaining', T('tvCntRemaining', 'Show what is left')], ['used', T('tvCntUsed', 'Show what is used')]], 'remaining', { wrap: 'col-6' })}
      </div>
    </div>`;
  }

  /** Controls of the advanced part (#collapseAdvanced). */
  function efbFieldRulesAdvEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    return wordsEls(r, idset) + efbFieldRulesMsgEls(indx, idset);
  }

  /* ------------------------------------------------------------------ handler */

  /** One control changed: write the field row by the server's rules (EfbRules.sanitizeKey). */
  function efb_fr_change_efb(el) {
    if (!el || el.getAttribute('aria-disabled') === 'true') return;
    const key = el.getAttribute('data-key');
    const r = rowById(el.getAttribute('data-id'));
    if (!key || !r) return;
    const R_ = R();
    let raw;
    if (el.classList.contains('btn-toggle')) {
      const isOn = !el.classList.contains('active');
      el.classList.toggle('active', isOn);
      el.setAttribute('aria-pressed', isOn ? 'true' : 'false');
      raw = isOn ? '1' : '0';
    } else {
      raw = el.value;
    }
    const v = R_ && R_.sanitizeKey ? R_.sanitizeKey(key, raw) : String(raw);
    if (v === null) return;
    r[key] = v;
    if (el.tagName === 'INPUT' && el.type === 'text' && el.value !== v && key.indexOf('msg_') !== 0) el.value = v;
    efb_fr_after_change_efb(r, key, el);
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) {} }
  }

  /** Side effects of a change: exclusive switches, live canvas preview. */
  function efb_fr_after_change_efb(r, key, el) {
    try { efb_fr_decorate_efb(document); } catch (e) {}
  }

  /* ------------------------------------------------------------------ canvas preview */

  /** The F03 pieces of every field on the canvas and in the PC / mobile preview (same markup as the page). */
  function efb_fr_decorate_efb(scope) {
    const R_ = R();
    if (!R_ || typeof valj_efb === 'undefined' || !scope || !scope.querySelectorAll) return;
    const lang = (root.efb_var && (root.efb_var.wp_lan || root.efb_var.language)) || 'en';
    valj_efb.forEach((r, i) => {
      if (i < 2 || !r || !r.id_) return;
      scope.querySelectorAll(`[id="${String(r.id_).replace(/"/g, '')}_"]`).forEach((input) => {
        const host = input.closest('.ttEfb') || input.parentElement;
        if (!host) return;
        // B: counter preview (0 typed).
        const c = R_.counter(r);
        let box = host.querySelector(':scope > .efb-counter-wrap');
        if (!c) { if (box) box.remove(); }
        else {
          if (!box) {
            box = document.createElement('div');
            box.className = 'efb efb-counter-wrap';
            box.innerHTML = '<div class="efb efb-counter fs-7" aria-hidden="true"></div>';
            const des = host.querySelector('.efb-field-description') || host.querySelector(`[id="${r.id_}-des"]`);
            if (des && des.parentElement === host) host.insertBefore(box, des); else host.appendChild(box);
          }
          box.firstChild.textContent = R_.counterText(c, 0, root.efb_var && root.efb_var.text, lang).text;
        }
      });
    });
  }
  let decoTimer = null;
  function scheduleDecorate() {
    clearTimeout(decoTimer);
    decoTimer = setTimeout(() => { try { efb_fr_decorate_efb(document); } catch (e) {} }, 60);
  }
  if (typeof document !== 'undefined' && document.addEventListener && typeof MutationObserver !== 'undefined') {
    const start = () => {
      const mo = new MutationObserver((list) => {
        // Only the builder's own redraws (not the pieces this file adds).
        if (list.some((m) => Array.from(m.addedNodes).some((n) => n.nodeType === 1 && !(n.classList && n.classList.contains('efb-counter-wrap'))))) scheduleDecorate();
      });
      mo.observe(document.body, { childList: true, subtree: true });
      scheduleDecorate();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  }

  function efb_fr_gem_efb(btn) {
    const cap = btn && btn.getAttribute('data-cap');
    if (typeof efb_capability_prompt_efb === 'function') efb_capability_prompt_efb(cap, 3, btn);
    else if (typeof pro_show_efb === 'function') pro_show_efb(3);
  }

  const api = { efbFieldRulesMainEls, efbFieldRulesAdvEls, efbFieldRulesMsgEls, efb_fr_change_efb, efb_fr_gem_efb, efb_fr_after_change_efb, efb_fr_decorate_efb };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
