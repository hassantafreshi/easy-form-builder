/**
 * F17 in the builder: a name or address dropped from the palette asks for its format first (the site language's one is
 * offered first; "Always use this" keeps the choice per user), then adds its parts as ordinary rows of one group
 * (cmp_grp = the first part's id_) with their labels, widths, required flags and keys; a part's settings show its group
 * (strict letters, the city that follows the province, address search of the Address & Postal add-on, the Saudi
 * "short address or full address" rule) and "Delete the whole group".
 * Spec: docs/feature-gaps/F17-localized-name-address.fa.md (§4.1, §6). Definitions: efb_var.name_address (the JSON of
 * Emsfb\Address_Schemes). efbNaClean is the twin of Address_Schemes::sanitize_key(); efbNaAcFor of autocomplete().
 */
(function (root) {
  'use strict';

  // efb_var.text holds HTML-escaped phrases (esc_html__): decoded here, escaped once where they are written.
  const unent = (v) => { if (typeof document === 'undefined' || !/&[#a-z0-9]+;/i.test(v)) return v; const t = document.createElement('textarea'); t.innerHTML = v; return t.value; };
  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return unent(String(root.efb_var.text[k])); } catch (e) { /* no text */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const CFG = () => (root.efb_var && root.efb_var.name_address) || { name_formats: {}, schemes: {}, prefixes: {}, defaults: {} };
  const EN = {
    nmFmtFull: 'Single field', nmFmtFl: 'First and last name', nmFmtPfl: 'With title', nmFmtFml: 'With middle name', nmFmtAr4: 'Four-part Arabic name', nmFmtAr3: 'Three-part Arabic name',
    nmFull: 'Full name', nmPrefix: 'Title', nmMiddle: 'Middle name', nmFather: "Father's name", nmGrandfather: "Grandfather's name", nmFamily: 'Family name', firstName: 'First name', lastName: 'Last name',
    addrSchIntl: 'International', addrSchIr: 'Iran', addrSchSa: 'Saudi National Address', addrSchAe: 'United Arab Emirates', addrSchEg: 'Egypt', addrSchUs: 'United States', addrSchUk: 'United Kingdom', addrSchCa: 'Canada', addrSchDe: 'Germany', addrSchJp: 'Japan', addrSchTr: 'Türkiye',
    addrCountry: 'Country', addrState: 'State', addrProvince: 'Province', addrRegion: 'Region', addrEmirate: 'Emirate', addrGovernorate: 'Governorate', addrPrefecture: 'Prefecture', city: 'City', addrTown: 'Town or city', addrCityWard: 'City, ward or town',
    addrLine1: 'Address line 1', addrLine2: 'Address line 2', addrStreet: 'Street', addrStreetNo: 'Street and house number', addrMainStreet: 'Main street or neighbourhood', addrAlley: 'Side street or alley', addrPlaque: 'Plaque', addrFloor: 'Floor', addrUnit: 'Unit', addrApartment: 'Apartment',
    addrBuildingNo: 'Building number', addrDistrict: 'District', addrNeighbourhood: 'Neighbourhood', addrAdditionalNo: 'Additional number', addrShortCode: 'Short address', addrArea: 'Area', addrBuilding: 'Building or villa', addrMakani: 'Makani number', addrPoBox: 'P.O. Box', addrLandmark: 'Landmark',
    addrCounty: 'County', addrPostcode: 'Postcode', postalcode: 'Postal code', zip: 'ZIP code',
  };
  const L = (k) => T(k, EN[k] || k);
  const KEYS = ['cmp_grp', 'cmp_kind', 'name_format', 'name_part', 'name_strict', 'addr_scheme', 'addr_part', 'addr_cascade', 'addr_lookup', 'addr_req_mode'];
  const NAME_PARTS = ['prefix', 'first', 'middle', 'last', 'father', 'grandfather', 'family', 'full'];
  const rnd = () => Math.random().toString(36).substr(2, 9);
  const kindSet = (kind) => (kind === 'name' ? CFG().name_formats : CFG().schemes) || {};
  const addrParts = () => { const o = {}; Object.values(CFG().schemes || {}).forEach((s) => (s.parts || []).forEach((p) => { o[p.p] = 1; })); return Object.keys(o); };

  /** The save rule of an F17 key ('' = not kept); Address_Schemes::sanitize_key(). */
  function efbNaClean(k, v) {
    if (v === true) v = '1'; else if (v === false) v = '0';
    if (v === null || v === undefined || typeof v === 'object') return '';
    v = String(v).trim();
    switch (k) {
      case 'cmp_grp': return /^[A-Za-z0-9_-]{1,40}$/.test(v) ? v : '';
      case 'cmp_kind': return v === 'name' || v === 'address' ? v : '';
      case 'name_format': return CFG().name_formats && CFG().name_formats[v] ? v : '';
      case 'name_part': return NAME_PARTS.indexOf(v) !== -1 ? v : '';
      case 'addr_scheme': return CFG().schemes && CFG().schemes[v] ? v : '';
      case 'addr_part': return addrParts().indexOf(v) !== -1 ? v : '';
      case 'addr_req_mode': return v === 'sa_short_or_full' ? v : '';
      case 'name_strict': case 'addr_cascade': case 'addr_lookup': return ['1', 'true', 'on'].indexOf(v.toLowerCase()) !== -1 ? '1' : '0';
    }
    return '';
  }
  function partDef(row) {
    if (!row || !row.cmp_grp || !row.cmp_kind) return null;
    const set = row.cmp_kind === 'name' ? (CFG().name_formats || {})[row.name_format] : (CFG().schemes || {})[row.addr_scheme];
    const p = row.cmp_kind === 'name' ? row.name_part : row.addr_part;
    return set && set.parts ? set.parts.find((x) => x.p === p) || null : null;
  }
  /** The autocomplete token of a part ('' = not a part); Address_Schemes::autocomplete(). */
  function efbNaAcFor(row) { const d = partDef(row); return d && d.ac ? d.ac : ''; }

  /** The format the site language suggests (the user's "Always use this" first). */
  function efbNaDefault(kind, locale) {
    const u = (CFG().user_defaults || {})[kind];
    if (u && kindSet(kind)[u] && !locale) return u;
    const map = (CFG().defaults || {})[kind] || {};
    const loc = String(locale || (root.efb_var && root.efb_var.wp_lan) || 'en_US');
    return map[loc] || map[loc.slice(0, 2)] || map['*'] || (kind === 'name' ? 'fl' : 'intl');
  }
  function prefixList() {
    const loc = String((root.efb_var && root.efb_var.wp_lan) || 'en').slice(0, 2);
    const p = CFG().prefixes || {};
    return p[loc] || p.en || ['Mr', 'Mrs', 'Ms', 'Dr'];
  }

  /**
   * The rows of a new group on any list of rows (the builder's or the API's scratch copy): $ids are the new parts' ids,
   * in the format's order, already pushed by addNewElement(). Labels, widths, required flags, keys; the title's choices;
   * the city of a cascading address without a list of its own; a state list of a fixed country is loaded later.
   */
  function efbNaPatch(list, plan, ids) {
    const set = kindSet(plan.kind)[plan.format];
    if (!set) return [];
    const grp = ids[0];
    const adr = !!CFG().adr;
    const out = [];
    set.parts.forEach((p, i) => {
      const idx = list.findIndex((r) => r && r.id_ === ids[i]);
      if (idx < 0) return;
      const r = list[idx];
      r.name = L(p.lb);
      r.placeholder = plan.kind === 'name' ? L(p.lb) : (r.placeholder || '');
      r.required = p.r ? 1 : 0;
      r.size = String(p.sz || 100);
      r.mobile_size = '100';
      r.cmp_grp = grp;
      r.cmp_kind = plan.kind;
      if (plan.kind === 'name') {
        r.name_format = plan.format; r.name_part = p.p;
        if (p.t === 'text') r.name_strict = '1';
        if (p.p === 'prefix') {
          for (let j = list.length - 1; j >= 0; j--) if (list[j] && list[j].type === 'option' && list[j].parent === r.id_) list.splice(j, 1);
          prefixList().forEach((v) => { const id = rnd(); list.push({ id_: id, dataId: id + '-id', parent: r.id_, type: 'option', value: v, id_op: id, step: r.step, amount: r.amount }); });
        }
      } else {
        r.addr_scheme = plan.format; r.addr_part = p.p;
        if (p.t === 'address_line' || p.t === 'postalcode') { r.placeholder = ''; if (p.mx) r.mlen = String(p.mx); }
        if (p.cas) { r.addr_cascade = '1'; r.statePov = ''; }
        if (p.cc) { r.country = p.cc.toUpperCase(); r.statePov = ''; }
        if ((p.cas || p.cc) && p.t !== 'conturyList') for (let j = list.length - 1; j >= 0; j--) if (list[j] && list[j].type === 'option' && list[j].parent === r.id_) list.splice(j, 1);
        if (p.t === 'cityList' && !p.cas) r.addr_cascade = '0';
        // AdnADR: the postal typing mask (F01 preset) when the add-on is active; without it the core rule alone.
        if (p.mask && adr) r.mask_preset = p.mask;
        if (i === 0 && plan.lookup && adr) r.addr_lookup = '1';
        if (i === 0 && plan.format === 'sa' && plan.mode) r.addr_req_mode = 'sa_short_or_full';
      }
      out.push(r);
    });
    return out;
  }

  /** A state list of a fixed country: the CDN (or the Offline add-on's copy), as when the administrator picks a country. */
  async function efbNaLoadStates(id_) {
    const r = valj_efb.find((x) => x && x.id_ === id_);
    if (!r || !r.country || typeof fetch_json_from_url_efb_admin !== 'function') return false;
    const cc = String(r.country).toLowerCase();
    const cdn = (efb_var.zone_area || '') + `json/states/${cc}.json`;
    let url = cdn;
    if (efb_var.setting && efb_var.setting.AdnOF == true && typeof efb_admin_offline_name_efb === 'function') url = String(efb_var.images.plugin_url + `/vendor/offline/json/states/${efb_admin_offline_name_efb(cc)}.json`).replaceAll('//vendor', '/vendor');
    let res = await fetch_json_from_url_efb_admin(url);
    if ((!res || res.s == false || res == 'null') && url !== cdn) res = await fetch_json_from_url_efb_admin(cdn);
    if (!res || res.s == false || res == 'null' || !res.r) return false;
    for (let j = valj_efb.length - 1; j >= 0; j--) if (valj_efb[j] && valj_efb[j].type === 'option' && valj_efb[j].parent === r.id_) valj_efb.splice(j, 1);
    const tail = rnd();
    Object.keys(res.r).forEach((k) => {
      const v = res.r[k]; const n = String(v.n || '').trim(); const l = String(v.l || '').trim();
      const value = l.length > 1 && l !== n ? `${l} (${n})` : n;
      const id = String((v.s || n) + tail).replaceAll(' ', '_');
      valj_efb.push({ id_: id, dataId: id + '-id', parent: r.id_, type: 'option', value: value, id_op: n.replaceAll(' ', '_'), step: r.step, amount: r.amount, l: l, n: n, s2: v.s });
    });
    return true;
  }
  function redraw() {
    if (root.efbBuilderApi && typeof root.efbBuilderApi.render === 'function') return root.efbBuilderApi.render().catch(() => {});
    return Promise.resolve();
  }

  /** fun_efb_add_el() calls this after it added the parts of the chosen format. */
  function efbNaAfterAddEfb(plan, ids) {
    const rows = efbNaPatch(valj_efb, plan, ids);
    setTimeout(async () => {
      await redraw();
      const fixed = rows.filter((r) => r.type === 'stateProvince' && r.country && r.addr_scheme !== 'intl');
      let any = false;
      for (const r of fixed) { try { any = (await efbNaLoadStates(r.id_)) || any; } catch (e) { /* offline: the administrator picks the country again */ } }
      if (any) await redraw();
      if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) { /* no API */ } }
    }, 0);
  }

  /** The format list of the chooser (a preview of the parts' labels). */
  function chooserBody(kind) {
    const set = kindSet(kind);
    const def = efbNaDefault(kind);
    const keys = Object.keys(set).sort((a, b) => (a === def ? -1 : b === def ? 1 : 0));
    const items = keys.map((k) => `<li class="efb mb-2"><button type="button" class="efb btn btn-outline-pink w-100 text-start efb-na-pick ${k === def ? 'active' : ''}" data-fmt="${esc(k)}" style="min-height:44px">
        <span class="efb fw-bold d-block" dir="auto">${esc(L(set[k].lb))}${k === def ? ` <span class="efb badge bg-light text-dark">${esc(T('nmSuggested', 'Suggested'))}</span>` : ''}</span>
        <span class="efb fs-7 text-muted d-block" dir="auto" style="unicode-bidi:plaintext">${set[k].parts.map((p) => esc(L(p.lb))).join(' · ')}</span></button></li>`).join('');
    const extra = kind === 'address' ? `<label class="efb d-flex align-items-center gap-2 mt-2" style="min-height:44px"><input type="checkbox" class="efb form-check-input" id="efbNaLookup" ${CFG().adr ? '' : 'disabled'}> <span dir="auto">${esc(T('addrLookupOpt', 'Add an address search button'))}</span></label>${CFG().adr ? '' : `<p class="efb fs-7 text-muted mb-1" dir="auto">${esc(T('addrLookupNeedsAdr', 'Address search needs the Address & Postal add-on.'))}</p>`}` : '';
    return `<div class="efb efb-na-chooser" role="group" aria-labelledby="efbNaChooserHelp"><p class="efb fs-7 mb-2" id="efbNaChooserHelp" dir="auto">${esc(T(kind === 'name' ? 'nmFmtPick' : 'addrSchPick', kind === 'name' ? 'Choose a name format' : 'Choose an address format'))}</p>
      <ul class="efb list-unstyled m-0 p-0">${items}</ul>${extra}
      <label class="efb d-flex align-items-center gap-2 mt-2" style="min-height:44px"><input type="checkbox" class="efb form-check-input" id="efbNaAlways"> <span dir="auto">${esc(T('cmpAlways', 'Always use this'))}</span></label></div>`;
  }
  /** The palette's name / address: ask for the format, then add it at the same place. */
  function efbNaChooseEfb(kind, insertAfterEl) {
    const set = kindSet(kind);
    if (!Object.keys(set).length || typeof show_modal_efb !== 'function') { root.efbNaPendingEfb = { kind: kind, format: kind === 'name' ? 'fl' : 'intl' }; fun_efb_add_el(kind, insertAfterEl); return; }
    const wire = () => {
      document.querySelectorAll('.efb-na-pick').forEach((b) => b.addEventListener('click', () => {
        const fmt = b.getAttribute('data-fmt');
        const always = document.getElementById('efbNaAlways');
        const lk = document.getElementById('efbNaLookup');
        if (always && always.checked) efbNaSaveDefault(kind, fmt);
        root.efbNaPendingEfb = { kind: kind, format: fmt, lookup: !!(lk && lk.checked) };
        state_modal_show_efb(0);
        fun_efb_add_el(kind, insertAfterEl);
      }));
      const first = document.querySelector('.efb-na-pick'); if (first) first.focus();
    };
    const painted = show_modal_efb(chooserBody(kind), esc(T(kind === 'name' ? 'nmFmtPick' : 'addrSchPick', kind === 'name' ? 'Choose a name format' : 'Choose an address format')), 'efb bi-' + (kind === 'name' ? 'person-circle' : 'geo-alt') + ' mx-2', 'settingBox', { onShown: wire });
    if (painted) state_modal_show_efb(1);
  }
  function efbNaSaveDefault(kind, fmt) {
    const c = CFG(); c.user_defaults = c.user_defaults || {}; c.user_defaults[kind] = fmt;
    try {
      const b = new FormData();
      b.append('action', 'efb_na_default'); b.append('kind', kind); b.append('format', fmt); b.append('nonce', efb_var.nonce || '');
      fetch(root.ajaxurl || 'admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: b }).catch(() => {});
    } catch (e) { /* the choice stays for this page */ }
  }
  /** The plan fun_efb_add_el() follows (its palette ids), or null for the old fixed list. */
  function efbNaPlanEfb(kind) {
    const p = root.efbNaPendingEfb;
    root.efbNaPendingEfb = null;
    if (!p || p.kind !== kind || !kindSet(kind)[p.format]) return null;
    p.parts = kindSet(kind)[p.format].parts;
    return p;
  }

  /* ---------------- the settings panel of a part ---------------- */

  function toggle(id, key, on, label, idset, disabled, help) {
    return `<div class="efb mx-1 my-2"><button type="button" id="${id}" data-id="${esc(idset)}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-na-ctl ${on ? 'active' : ''}" aria-pressed="${on ? 'true' : 'false'}" ${disabled ? 'disabled' : ''} ${help ? `aria-describedby="${id}Help"` : ''} onclick="efbNaChangeEfb(this)"><div class="efb handle"></div></button>
      <label class="efb form-check-label pt-1" for="${id}" dir="auto">${esc(label)}</label>${help ? `<small class="efb d-block text-muted fs-7" id="${id}Help" dir="auto">${esc(help)}</small>` : ''}</div>`;
  }
  /** The group block at the top of a part's settings. */
  function efbNaPanelEfb(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx] || !valj_efb[indx].cmp_grp) return;
    const r = valj_efb[indx];
    const box = document.getElementById('childsSideMenuConEfb');
    if (!box || document.getElementById('efbNaPanel')) return;
    const set = kindSet(r.cmp_kind)[r.cmp_kind === 'name' ? r.name_format : r.addr_scheme];
    const first = valj_efb.find((x) => x && x.cmp_grp === r.cmp_grp);
    const isFirst = first && first.id_ === r.id_;
    let h = `<div class="efb mx-1 my-2 p-2 border rounded-3 efb-na-panel" id="efbNaPanel" role="group" aria-labelledby="efbNaPanelT">
      <p class="efb fw-bold mb-1" id="efbNaPanelT" dir="auto"><i class="efb bi-${r.cmp_kind === 'name' ? 'person-circle' : 'geo-alt'}" aria-hidden="true"></i> ${esc(T('cmpGroup', r.cmp_kind === 'name' ? 'Name' : 'Address'))}: ${esc(set ? L(set.lb) : '')}</p>
      <p class="efb fs-7 text-muted mb-1" dir="auto">${esc(T('cmpPart', 'This part'))}: ${esc(L((partDef(r) || {}).lb || ''))}</p>`;
    if (r.cmp_kind === 'name' && r.type === 'text') h += toggle('nameStrictEl', 'name_strict', r.name_strict === '1', T('nmStrict', 'Letters only (no digits)'), idset);
    if (r.cmp_kind === 'address' && r.type === 'cityList') h += toggle('addrCascadeEl', 'addr_cascade', r.addr_cascade === '1', T('addrCascade', 'Cities follow the chosen province'), idset);
    if (r.cmp_kind === 'address' && isFirst) {
      h += toggle('addrLookupEl', 'addr_lookup', r.addr_lookup === '1', T('addrLookupOpt', 'Add an address search button'), idset, !CFG().adr && r.addr_lookup !== '1', CFG().adr ? T('addrLookupPrivacy', 'Searching sends the address the visitor types to OpenStreetMap.') : T('addrLookupNeedsAdr', 'Address search needs the Address & Postal add-on.'));
      if (r.addr_scheme === 'sa') h += `<div class="efb mx-1 my-2"><label for="addrReqModeEl" class="efb form-label mb-1 fs-7" dir="auto">${esc(T('addrReqMode', 'Required parts'))}</label>
        <select id="addrReqModeEl" class="efb form-select border-d rounded-4 efb-na-ctl" data-id="${esc(idset)}" data-key="addr_req_mode" onchange="efbNaChangeEfb(this)" style="min-height:44px">
        <option value="" ${r.addr_req_mode ? '' : 'selected'}>${esc(T('addrReqAll', 'Every required part'))}</option>
        <option value="sa_short_or_full" ${r.addr_req_mode === 'sa_short_or_full' ? 'selected' : ''}>${esc(T('addrSaShortOrFullOpt', 'The short address, or the full National Address'))}</option></select></div>`;
    }
    h += `<button type="button" class="efb btn btn-sm btn-outline-danger mt-1" style="min-height:44px" onclick="efbNaDeleteGroupEfb('${esc(r.cmp_grp)}')"><i class="efb bi-trash" aria-hidden="true"></i> ${esc(T('cmpDelGroup', 'Delete the whole group'))}</button></div>`;
    box.insertAdjacentHTML('afterbegin', h);
  }
  /** A group control changed: the row (the first part for group keys), the API's "edited by a person" mark. */
  function efbNaChangeEfb(el) {
    if (!el || typeof valj_efb === 'undefined') return;
    const key = el.getAttribute('data-key');
    const r = valj_efb.find((x, i) => i > 0 && x && x.dataId == el.getAttribute('data-id'));
    if (!r || KEYS.indexOf(key) === -1) return;
    let v;
    if (el.classList.contains('btn-toggle')) {
      const on = !el.classList.contains('active');
      el.classList.toggle('active', on); el.setAttribute('aria-pressed', on ? 'true' : 'false');
      v = on ? '1' : '0';
    } else v = efbNaClean(key, el.value);
    if (key === 'addr_lookup' && v === '1' && !CFG().adr) return;
    if (v === '' || (key === 'addr_lookup' && v === '0')) delete r[key]; else r[key] = v;
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) { /* no API */ } }
  }
  /** "Delete the whole group": every part and its options. */
  function efbNaDeleteGroupEfb(grp) {
    if (typeof valj_efb === 'undefined' || !grp) return;
    const ids = valj_efb.filter((x) => x && x.cmp_grp === grp).map((x) => x.id_);
    if (!ids.length) return;
    for (let j = valj_efb.length - 1; j > 0; j--) { const x = valj_efb[j]; if (x && (ids.indexOf(x.id_) !== -1 || (x.type === 'option' && ids.indexOf(x.parent) !== -1))) valj_efb.splice(j, 1); }
    if (typeof efbHistHintEfb === 'function') efbHistHintEfb(T('cmpDelGroup', 'Delete the whole group'), 'nadel|' + grp);
    if (typeof state_modal_show_efb === 'function') { try { const sb = document.getElementById('sideBoxEfb'); if (sb && sb.classList.contains('show') && typeof sideMenuEfb === 'function') sideMenuEfb(0); } catch (e) { /* panel */ } }
    redraw();
  }
  /** The duplicate of one part is an ordinary field again (two "postal" parts in one group would be ambiguous). */
  function efbNaStripKeys(row) { KEYS.forEach((k) => { delete row[k]; }); return row; }

  const api = { efbNaClean, efbNaAcFor, efbNaDefault, efbNaPatch, efbNaPlanEfb, efbNaAfterAddEfb, efbNaChooseEfb, efbNaPanelEfb, efbNaChangeEfb, efbNaDeleteGroupEfb, efbNaLoadStates, efbNaStripKeys, EFB_NA_KEYS: KEYS };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
