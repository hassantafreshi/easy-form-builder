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
    if (R_.typeOk(r, 'pattern')) out.push(['pattern', 'tvPattern_']);
    if (R_.typeOk(r, 'unique')) out.push(['unique', 'tvUnique_']);
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
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    const R_ = R();
    if (!R_) return '';
    let html = '';
    if (R_.typeOk(r, 'unique')) html += toggleEl(r, idset, 'uniqueEl', 'unique', T('tvUnique', 'Accept each value only once'), { plan: 'unique', help: esc(T('tvUniqueHint', 'Checked when the form is sent. Earlier entries are checked too.')) });
    return html ? `<div class="efb efb-fr-main mt-2">${html}</div>` : '';
  }

  /* D: after a Save, the older answers of a new unique field are hashed (efb_unique_backfill_Emsfb),
   * with the progress in the page; the server's save hook already did the first batches. */
  let backfillBusy = false;
  function efb_fr_backfill_efb(fid) {
    if (backfillBusy || !fid || typeof jQuery === 'undefined' || typeof valj_efb === 'undefined') return;
    if (!valj_efb.some((r, i) => i > 1 && r && R() && R().on(r, 'unique'))) return;
    backfillBusy = true;
    const url = (root.efb_var && root.efb_var.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const nonce = typeof _efb_nonce_ !== 'undefined' ? _efb_nonce_ : (root.efb_var && root.efb_var.nonce);
    let note = document.getElementById('efbUniqueBfEl');
    const step = (n) => {
      jQuery.post(url, { action: 'efb_unique_backfill_Emsfb', nonce: nonce, form_id: fid }, (res) => {
        const d = res && res.data;
        if (!d || !d.success) { backfillBusy = false; return; }
        if (!d.done && n < 200) {
          if (!note) { note = document.createElement('div'); note.id = 'efbUniqueBfEl'; note.className = 'efb alert alert-info fs-7 position-fixed bottom-0 end-0 m-3'; note.setAttribute('role', 'status'); document.body.appendChild(note); }
          note.textContent = T('tvBackfill', 'Checking %s earlier entries for duplicates…').replace('%s', String(d.total));
          step(n + 1);
          return;
        }
        backfillBusy = false;
        if (note) {
          note.textContent = T('tvBackfillDone', 'Earlier entries checked. Duplicates already stored: %s').replace('%s', String(d.duplicates));
          setTimeout(() => { if (note && note.parentNode) note.parentNode.removeChild(note); note = null; }, 6000);
        }
      }).fail(() => { backfillBusy = false; });
    };
    step(0);
  }
  if (typeof jQuery !== 'undefined' && typeof document !== 'undefined') {
    jQuery(document).ajaxSuccess((ev, xhr, settings) => {
      const body = settings && typeof settings.data === 'string' ? settings.data : '';
      if (!/action=(add_form_Emsfb|update_form_Emsfb)/.test(body)) return;
      const d = xhr && xhr.responseJSON && xhr.responseJSON.data;
      if (!d || d.success !== true) return;
      setTimeout(() => { const fid = typeof form_ID_emsFormBuilder !== 'undefined' ? Number(form_ID_emsFormBuilder) : 0; efb_fr_backfill_efb(fid); }, 300);
    });
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

  /** English reason of a lint code (the phrase tvRx_<code> when the site has it; HTML-escaped). */
  const RX_EN = {
    too_long: 'longer than 256 bytes', syntax: 'it is not a complete pattern', lookbehind: 'look-behind (?<= and (?<! is not supported',
    named_group: 'named groups are not supported', backref: 'back-references such as \\1 are not supported', inline_flag: 'inline flags such as (?i) are not supported; use the switch instead',
    atomic: 'atomic groups (?> are not supported', possessive: 'possessive repeats such as *+ are not supported', escape: 'this escape is not supported',
    posix_class: 'POSIX classes such as [[:alpha:]] are not supported', script_property: 'only \\p{L}, \\p{Lu}, \\p{Ll}, \\p{N}, \\p{Nd}, \\p{P}, \\p{S} and \\p{Z} are supported',
    unicode_escape: 'write the character itself instead of \\u or \\x{…}', tilde: 'the character ~ cannot be used', brace: 'write \\{, \\} or \\] for a literal brace or bracket',
    empty_class: 'an empty class [] matches nothing', range_order: 'a range or a repeat count is in the wrong order', class_range: 'a range cannot start or end with \\d, \\w or \\s',
    class_escape: '\\D, \\W and \\S cannot be used inside [ ]', nothing_to_repeat: 'a repeat sign has nothing to repeat', quantified_assertion: 'an anchor or look-ahead cannot be repeated',
    too_large: 'a repeat count is larger than 1000', nested_quantifier: 'a repeated group repeats something inside it again, which can be very slow',
    alternation: 'a repeated group of alternatives can be very slow; use a [class] instead', too_complex: 'too many unlimited repeats',
  };
  const Th = (k, fb) => { const v = root.efb_var && root.efb_var.text && root.efb_var.text[k]; return v ? String(v) : esc(fb); };
  function lintHtml(err) {
    return Th('tvPatternBad', 'This pattern cannot be used: %s').replace('%s', Th('tvRx_' + err.code, RX_EN[err.code] || err.code)) + ` <span class="efb text-muted" dir="ltr">(@${err.pos + 1})</span>`;
  }

  /** C: the owner's pattern (stored base64url), case and digit switches, the live test box. */
  function patternEls(r, idset) {
    const R_ = R();
    if (!R_ || !R_.typeOk(r, 'pattern')) return '';
    const src = r.pattern_b64 ? (R_.b64urlDecode(r.pattern_b64) || '') : '';
    return `<div class="efb mt-3 efb-fr-pattern" id="frPatternBoxEl">
      <label for="patternEl" class="efb mb-1 fs-7 fw-bold"><i class="efb bi-regex fs-7 ${icon()}" aria-hidden="true"></i>${esc(T('tvPattern', 'Custom pattern (regular expression)'))}${gem('pattern')}</label>
      <textarea rows="1" class="efb form-control efb border-d rounded-4 font-monospace efb-fr" id="patternEl" data-id="${esc(idset)}" data-key="pattern_src" dir="ltr" spellcheck="false" autocomplete="off" maxlength="256" aria-describedby="patternHelpEl patternLintMsg" oninput="efb_fr_pattern_input_efb(this)"${lock('pattern')}>${esc(src)}</textarea>
      <small class="efb form-text text-muted d-block mt-1 fs-8" id="patternHelpEl">${esc(T('tvPatternHint', 'The whole value must match. You do not need ^ or $.'))}</small>
      <small class="efb text-danger d-block mt-1 fs-8" id="patternLintMsg" role="alert"></small>
      ${toggleEl(r, idset, 'patternCaseEl', 'pattern_case', T('tvPatternCase', 'Ignore upper and lower case'), { plan: 'pattern' })}
      ${toggleEl(r, idset, 'patternAsciiEl', 'pattern_ascii', T('tvPatternAscii', 'Treat Persian and Arabic digits as 0-9'), { plan: 'pattern', dflt: true })}
      <label for="patternTestEl" class="efb mb-1 fs-7">${esc(T('tvPatternTest', 'Try some values, one per line'))}</label>
      <textarea rows="3" class="efb form-control efb border-d rounded-4" id="patternTestEl" data-id="${esc(idset)}" oninput="efb_fr_pattern_test_efb(this)" aria-describedby="patternTestOut"${lock('pattern')}></textarea>
      <ul class="efb list-unstyled fs-8 mt-1 mb-0" id="patternTestOut" aria-live="polite"></ul>
    </div>`;
  }
  let patternTimer = null;
  /** The pattern box: lint as you type; stored only when it passes (else the last good one stays). */
  function efb_fr_pattern_input_efb(el) {
    clearTimeout(patternTimer);
    patternTimer = setTimeout(() => {
      const R_ = R();
      const r = rowById(el.getAttribute('data-id'));
      if (!R_ || !r || el.getAttribute('aria-disabled') === 'true') return;
      const src = String(el.value || '').replace(/[\r\n]+/g, '');
      const msg = document.getElementById('patternLintMsg');
      const err = src === '' ? null : R_.lintRegex(src);
      if (err) {
        if (msg) msg.innerHTML = lintHtml(err);
        el.setAttribute('aria-invalid', 'true');
        return;
      }
      if (msg) msg.textContent = '';
      el.removeAttribute('aria-invalid');
      r.pattern_b64 = src === '' ? '' : R_.b64urlEncode(src);
      const test = document.getElementById('patternTestEl');
      if (test) efb_fr_pattern_test_efb(test);
      if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) {} }
    }, 250);
  }
  /** One result per line of the test box (nothing is saved). */
  function efb_fr_pattern_test_efb(el) {
    const R_ = R();
    const r = rowById(el.getAttribute('data-id'));
    const out = document.getElementById('patternTestOut');
    if (!R_ || !r || !out) return;
    out.textContent = '';
    if (!r.pattern_b64) return;
    String(el.value || '').split(/\r?\n/).filter((l) => l !== '').slice(0, 20).forEach((line) => {
      const ok = R_.checkPattern(r, line) !== false;
      const li = document.createElement('li');
      li.className = 'efb ' + (ok ? 'text-success' : 'text-danger');
      li.innerHTML = `<i class="efb ${ok ? 'bi-check-circle' : 'bi-x-circle'} ${icon()}" aria-hidden="true"></i>`;
      const b = document.createElement('bdi'); b.textContent = line; li.appendChild(b);
      li.appendChild(document.createTextNode(' — ' + (ok ? T('tvPatternOk', 'Accepted') : T('tvPatternNo', 'Refused'))));
      out.appendChild(li);
    });
  }

  /** Controls of the advanced part (#collapseAdvanced). */
  function efbFieldRulesAdvEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    return wordsEls(r, idset) + patternEls(r, idset) + efbFieldRulesMsgEls(indx, idset);
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

  const api = { efbFieldRulesMainEls, efbFieldRulesAdvEls, efbFieldRulesMsgEls, efb_fr_change_efb, efb_fr_gem_efb, efb_fr_after_change_efb, efb_fr_decorate_efb, efb_fr_pattern_input_efb, efb_fr_pattern_test_efb, efb_fr_backfill_efb };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
