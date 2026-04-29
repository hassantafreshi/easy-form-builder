
 let devMode_efb = false

function efb_safe_json_parse(str) {
  try { return JSON.parse(str); } catch (e) {  }
  var v = str.replace(/[\\]/g, '');
  v = v.replace(/[\x00-\x1F\x7F]/g, function(c) {
    switch (c) {
      case '\n': return '\\n';
      case '\r': return '\\r';
      case '\t': return '\\t';
      default: return '';
    }
  });
  return JSON.parse(v);
}

if (typeof efbLoadingCard === 'undefined') {
  efbLoadingCard = (bgColor, size = 0) => {
    size = size ? size : 3;
    const w = size < 4 ? 'w-50' : 'w-25';
    const images = (typeof efb_var !== 'undefined' && efb_var.images) ? efb_var.images : {};
    const text = (typeof efb_var !== 'undefined' && efb_var.text) ? efb_var.text : {};
    return `<div class='efb row justify-content-center card-body text-center efb mt-5 pt-3'>
    <div class='efb col-12 col-md-4 col-sm-7 mx-0 my-1 d-flex flex-column align-items-center ${bgColor || ''}'>
        <img class='efb ${w}' src='${images.logoGif || ''}'>
        <p class='efb fs-${size} text-darkb mb-0'>${text.easyFormBuilder || ''}</p>
        <p class='efb fs-${size + 1} text-dark'>${text.pleaseWaiting || ''}</p>
    </div>
  </div> `;
  };
}

function allowOnlyPhoneChars_efb(event) {
  const allowedChars = /[0-9\+\(\)\-\s,]/;
  const key = String.fromCharCode(event.which || event.keyCode);

  if (event.ctrlKey || event.metaKey ||
      [8, 9, 13, 27, 46, 37, 38, 39, 40].indexOf(event.keyCode) !== -1) {
    return true;
  }

  if (!allowedChars.test(key)) {
    event.preventDefault();
    const input = event.target;
    input.classList.add('is-invalid');
    setTimeout(() => {
      input.classList.remove('is-invalid');
    }, 300);
    return false;
  }

  return true;
}

function filterPhoneNumberInput_efb(input) {
  const allowedPattern = /[^0-9\+\(\)\-\s,]/g;
  const cursorPosition = input.selectionStart;
  const oldValue = input.value;
  const newValue = oldValue.replace(allowedPattern, '');

  if (oldValue !== newValue) {
    input.value = newValue;
    const removedChars = oldValue.length - newValue.length;
    const newCursorPosition = Math.max(0, cursorPosition - removedChars);
    input.setSelectionRange(newCursorPosition, newCursorPosition);

    const messageEl = document.getElementById(input.id + '-message');
    if (messageEl && newValue.length > 0) {
      messageEl.innerHTML = '';
      input.classList.remove('invalid');
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const _attachPhoneFilter = () => {
    const el = document.getElementById('pno_emsFormBuilder');
    if (!el || el._phoneFilterAttached) return;
    el._phoneFilterAttached = true;

    const cleanPhone = (str) => str.replace(/[^0-9\+\(\)\-\s,]/g, '');

    el.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = cleanPhone(pasted);
      const start = el.selectionStart;
      const end = el.selectionEnd;
      const current = el.value;
      el.value = current.slice(0, start) + cleaned + current.slice(end);
      const newPos = start + cleaned.length;
      el.setSelectionRange(newPos, newPos);
    });

    el.addEventListener('drop', function(e) {
      e.preventDefault();
      const dropped = e.dataTransfer.getData('text');
      const cleaned = cleanPhone(dropped);
      el.value += cleaned;
    });

    el.addEventListener('compositionend', function() {
      el.value = cleanPhone(el.value);
    });

    el.addEventListener('change', function() {
      el.value = cleanPhone(el.value);
    });
  };

  _attachPhoneFilter();
  const observer = new MutationObserver(() => _attachPhoneFilter());
  observer.observe(document.body, { childList: true, subtree: true });
});

function highlightSearchResults_efb(text, searchTerm) {
  if (!searchTerm || searchTerm.trim() === '') return text;

  let displayText = text;
  try {
    const parsed = JSON.parse(text);
    if (typeof parsed === 'object') {
      displayText = JSON.stringify(parsed, null, 2);
    }
  } catch (e) {
  }

  const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  return displayText.replace(regex, '<mark class="efb search-highlight">$1</mark>');
}

function setupSearchSuggestions_efb() {
  const searchInput = document.getElementById('track_code_emsFormBuilder');
  if (!searchInput) return;

  searchInput.addEventListener('input', function(e) {
    const searchTerm = e.target.value;
    if (searchTerm.length >= 2) {
      window.lastSearchTerm_efb = searchTerm;
    }
  });

  searchInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      fun_find_track_emsFormBuilder();
    }
  });
}

function enhanceSearchResults_efb(messages, searchTerm) {
  if (!searchTerm || !messages) return messages;

  return messages.sort((a, b) => {
    const getSearchableContent = (msg) => {
      let content = msg.content || '';
      try {
        const parsed = JSON.parse(content);
        if (typeof parsed === 'object') {
          content = JSON.stringify(parsed);
        }
      } catch (e) {
      }
      return JSON.stringify(msg).toLowerCase();
    };

    const aContent = getSearchableContent(a);
    const bContent = getSearchableContent(b);
    const lowerSearchTerm = searchTerm.toLowerCase();

    const aTrackExact = (a.track || '').toLowerCase() === lowerSearchTerm;
    const bTrackExact = (b.track || '').toLowerCase() === lowerSearchTerm;

    if (aTrackExact && !bTrackExact) return -1;
    if (!aTrackExact && bTrackExact) return 1;

    const aMatch = aContent.includes(lowerSearchTerm);
    const bMatch = bContent.includes(lowerSearchTerm);

    if (aMatch && !bMatch) return -1;
    if (!aMatch && bMatch) return 1;

    return new Date(b.date) - new Date(a.date);
  });
}

let valueJson_ws_form = [];
let valueJson_ws_messages = [];
let valueJson_ws_setting = []
let state_seting_emsFormBuilder = false;
let poster_emsFormBuilder = '';
let response_state_efb;
let sms_config_efb ='null'
let files_emsFormBuilder = [];

const colors_efb = ['#0013CB', '#E90056', '#7CEF00', '#FFBA00', '#FF3888', '#526AFF', '#FFC738', '#A6FF38', '#303563', '#7D324E', '#5D8234', '#8F783A', '#FB5D9D', '#FFA938', '#45B2FF', '#A6FF38', '#0011B4', '#8300AD', '#E9FB00', '#FFBA00']

jQuery(function () {
  valueJson_ws_form = ajax_object_efm.ajax_value;
  poster_emsFormBuilder = ajax_object_efm.poster
  response_state_efb = ajax_object_efm.response_state;
  pro_ws_efb = ajax_object_efm.pro == '1' ? true : false;
  page_state_efb="panel";
  devMode_efb =Number(ajax_object_efm.devMode) === 1 ? true : false;
  if (ajax_object_efm.setting, ajax_object_efm.setting.length > 0) {
    const rawSetting = ajax_object_efm.setting[0].setting;
    try { valueJson_ws_setting = JSON.parse(rawSetting); }
    catch(e) {
      try { valueJson_ws_setting = JSON.parse(rawSetting.replace(/\\\\/g, '\\')); }
      catch(e2) {
        try { valueJson_ws_setting = JSON.parse(rawSetting.replace(/[\\]/g, '')); }
        catch(e3) {  valueJson_ws_setting = {}; }
      }
    }
    if (valueJson_ws_setting.bootstrap == 0 && ajax_object_efm.bootstrap == 1) {
      if (localStorage.getItem('bootstrap_w') === null) localStorage.setItem('bootstrap_w', 0)
      if (localStorage.getItem('bootstrap_w') >= 0 && localStorage.getItem('bootstrap_w') < 3) {
        localStorage.setItem('bootstrap_w', (parseInt(localStorage.getItem('bootstrap_w')) + 1))
      }
    }
  }
  let g =new URLSearchParams(location.search)
  const state = g.get('state') !=null ? sanitize_text_efb(g.get('state')) : null;
 if(state==null){
   fun_emsFormBuilder_render_view(25);
   history.replaceState("panel",null,'?page=Emsfb');
 }else{

  fun_show_content_page_emsFormBuilder(state)
 }

 setTimeout(() => {
   setupSearchSuggestions_efb();
 }, 500);
});

let count_row_emsFormBuilder = 0;

function fun_emsFormBuilder_render_view(x) {
  if (typeof restore_auto_save_efb === 'function') restore_auto_save_efb();

  if(!document.getElementById('alert_efb')){
    const currentUrl = window.location.href;
    const txt = fun_create_content_nloading_efb();
    const txtWithoutHTML = txt.replace(/<[^>]+>/g, '');
    alert(txtWithoutHTML)
    report_problem_efb('AdminPagesNotLoaded' ,currentUrl);
    return;
  }

  let rows = ""
  let o_rows = ""
  count_row_emsFormBuilder = x;
  let count = 0;
  fun_backButton_efb(2);

  function creatRowsFormsEFB(i, newM) {
    const fid = Number(i.form_id);
    const sc = '[EMS_Form_Builder id=' + fid + ']';
    return ` <tr class="efb pointer-efb efb" id="emsFormBuilder-tr-${fid}" >
   <th scope="row" class="efb emsFormBuilder-tr" data-id="${fid}" data-label="${efb_var.text.formCode}">
     <span class="efb d-inline-flex align-items-center gap-1">
       <code class="efb text-muted user-select-all" style="font-size:0.85em">${sc}</code>
       <button type="button" class="efb btn btn-sm btn-outline-secondary border-0 px-1 py-0" onclick="event.stopPropagation();copyShortcode_efb('${sc}',this,'shortcode')" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.copy}">
         <i class="efb bi-clipboard"></i>
       </button>
     </span>
   </th>
   <td class="efb emsFormBuilder-tr" data-id="${Number(i.form_id)}" data-label="${efb_var.text.formName}">${sanitize_text_efb(i.form_name)}</td>
   <td class="efb emsFormBuilder-tr" data-id="${Number(i.form_id)}" data-label="${efb_var.text.createDate}">${sanitize_text_efb(i.form_create_date)}</td>
   <td class="efb efb-actions-cell" data-label="${efb_var.text.actions}">
     <div class="efb-actions-group">
       <button type="button" class="efb efb-act-btn efb-act-msg ec-efb ${newM ? 'efb-has-badge' : ''}" data-id="${Number(i.form_id)}" data-eventform="message" data-efb-tip="${newM == true ? sanitize_text_efb(efb_var.text.newResponse) : sanitize_text_efb(efb_var.text.read)}" aria-label="${newM == true ? sanitize_text_efb(efb_var.text.newResponse) : sanitize_text_efb(efb_var.text.read)}">
         <i class="efb ${newM ? 'bi-chat-dots-fill' : 'bi-chat'}"></i>
         ${newM ? '<span class="efb-noti-badge" role="status" aria-label="' + sanitize_text_efb(efb_var.text.newResponse) + '"></span>' : ''}
       </button>
       <button type="button" class="efb efb-act-btn efb-act-edit ec-efb" data-id="${Number(i.form_id)}" data-eventform="edit" data-efb-tip="${sanitize_text_efb(efb_var.text.edit)}" aria-label="${sanitize_text_efb(efb_var.text.edit)}">
         <i class="efb bi-pencil"></i>
       </button>
       <button type="button" class="efb efb-act-btn efb-act-dup ec-efb" data-id="${Number(i.form_id)}" data-eventform="duplicate" data-formname="${sanitize_text_efb(i.form_name)}" data-efb-tip="${sanitize_text_efb(efb_var.text.duplicate)}" id="${Number(i.form_id)}-dup-efb" aria-label="${sanitize_text_efb(efb_var.text.duplicate)}">
         <i class="efb bi-clipboard-plus"></i>
       </button>
       <button type="button" class="efb efb-act-btn efb-act-delete ec-efb" data-id="${Number(i.form_id)}" data-eventform="delete" data-formname="${sanitize_text_efb(i.form_name)}" data-efb-tip="${sanitize_text_efb(efb_var.text.delete)}" aria-label="${sanitize_text_efb(efb_var.text.delete)}">
         <i class="efb bi-trash3"></i>
       </button>
     </div>
     <input type="text" class="efb d-none" value='[EMS_Form_Builder id=${Number(i.form_id)}]' id="${Number(i.form_id)}-fc">
   </td>
  </tr>
  `
  }
  if (valueJson_ws_form.length > 0) {

    for (let i of valueJson_ws_form) {
      const id_form = Number(i.form_id);
      if (x > count) {
        if(i.hasOwnProperty('status') &&  i.status!=1 ) continue;
        let newM = false;
        const d = ajax_object_efm.messages_state.findIndex(x => Number(x.form_id) == id_form)
        if (d != -1) { newM = true; }
        const b = ajax_object_efm.response_state.findIndex(x => Number(x.form_id) == id_form)
        if (b != -1) { newM = true; }
        newM != true ? o_rows += creatRowsFormsEFB(i, newM) : rows += creatRowsFormsEFB(i, newM);
        count += 1;
      }
    }
    rows += o_rows;
    if (valueJson_ws_form.length <= x) {
      const d = document.getElementById("more_emsFormBuilder");
      if(d) d.style.display = "none";
    }

    document.getElementById('content-efb').innerHTML = `
   <h4 class="efb title-holder efb fs-4 d-none"> <img src="${efb_var.images.title}" class="efb title efb">
                <i class="efb  bi-archive title-icon  mx-1 fs-4"></i>${efb_var.text.forms}
            </h4>
    <div class="efb card efb">
    <table class="efb table table-striped table-hover mt-3" id="emsFormBuilder-list">
        <thead class="efb">
            <tr class="efb">
            <th scope="col" class="efb">${efb_var.text.formCode}</th>
            <th scope="col" class="efb">${efb_var.text.formName}</th>
            <th scope="col" class="efb">${efb_var.text.createDate}</th>
            <th scope="col" class="efb">${efb_var.text.actions}</th>
            </tr>
        </thead>
        <tbody class="efb">${rows}</tbody>
    </table>
 </div>
 ${typeof efb_powered_by === 'function' ? efb_powered_by() : ''}
 `

  } else {
    fun_backButton_efb(1);
    document.getElementById('content-efb').innerHTML = head_introduce_efb('panel')
    document.getElementById('content-efb').classList.add('m-1');
  }

  for (const el of document.querySelectorAll(`.emsFormBuilder-tr`)) {
    el.addEventListener("click", (e) => { emsFormBuilder_messages(el.dataset.id) });
  }
}

function emsFormBuilder_waiting_response() {
  document.getElementById('emsFormBuilder-list').innerHTML = efbLoadingCard('',5)
}

function toast_efb(icon, message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = 'efb-copy-toast efb-toast-' + type;
  toast.innerHTML = '<i class="efb ' + icon + ' me-2"></i>' + message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 2500);
}

function copyShortcode_efb(text, btn ,type ='shortcode') {
  navigator.clipboard.writeText(text).then(() => {
    const icon = btn.querySelector('i');
    if (icon) {
      icon.className = 'efb bi-clipboard-check text-success';
      setTimeout(() => { icon.className = 'efb bi-clipboard'; }, 2000);
    }
    const message = efb_var.text[type] || '';
    const copiedMessage = efb_var.text.copied.replace('%s', message);
    toast_efb('bi-check-circle-fill', copiedMessage, 'success');
  }).catch(() => {
    const tmp = document.createElement('textarea');
    tmp.value = text;
    tmp.style.position = 'fixed';
    tmp.style.opacity = '0';
    document.body.appendChild(tmp);
    tmp.select();
    document.execCommand('copy');
    tmp.remove();
  });
}

function emsFormBuilder_get_edit_form(id) {
  history.pushState("edit-form",null,`?page=Emsfb&state=edit-form&id=${id}`);
  fun_backButton_efb();
  emsFormBuilder_waiting_response();
  fun_get_form_by_id(id);
}

function emsFormBuilder_show_content_message(id) {
  const formType = form_type_emsFormBuilder;
  const indx = valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const msg_id = valueJson_ws_messages[indx].msg_id;

  const body = EfbResponseViewer.buildAdminResponseBody(indx, formType);

  show_modal_efb(body, efb_var.text.response, 'efb bi-chat-square-text mx-2', 'saveBox');
  setTimeout(() => {
    EfbResponseViewer.initAfterRender(msg_id, true);
  }, 10);
  state_modal_show_efb(1);

  jQuery('#track_code_emsFormBuilder').on('keypress',
  function (event) {
      if (event.which == '13') {
          event.preventDefault();
          return;
      }
  });

}

function fun_backButton_efb(state) {
   if(!document.getElementById("more_emsFormBuilder"))return;
  if (document.getElementById("more_emsFormBuilder").style.display == "block" && state == 1) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
    (document.getElementById("more_emsFormBuilder").style.display, 255)
  } else {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }

  if (state == 0 || state == null) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
  } else if (state == 2) {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }
}

function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();
}

function fun_confirm_remove_emsFormBuilder(id) {
  fun_delete_form_with_id_by_server(parseInt(id));
  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => Number(x.form_id) == Number(id)) : -1
  if (foundIndex != -1) valueJson_ws_form.splice(foundIndex, 1);
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);

}

function fun_confirm_remove_message_emsFormBuilder(id) {

  fun_delete_message_with_id_by_server(parseInt(id));

  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => Number(x.form_id) == Number(id)) : -1
  if (foundIndex != -1) valueJson_ws_form.splice(foundIndex, 1);
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);

}
function fun_confirm_remove_all_message_emsFormBuilder(val) {
  fun_delete_all_message_by_server(val);

   for (const v of val) {
    const foundIndex = Object.keys(valueJson_ws_messages).length > 0 ? valueJson_ws_messages.findIndex(x => x.msg_id == v.msg_id) : -1
    if (foundIndex != -1) valueJson_ws_messages.splice(foundIndex, 1);
  }
  fun_ws_show_list_messages(valueJson_ws_messages);

}

function fun_emsFormBuilder_back() {
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
}

function fun_emsFormBuilder_more() {
  count_row_emsFormBuilder += 5;
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })

}

function fun_ws_show_edit_form(id) {
  const len = valj_efb.length;

  creator_form_builder_Efb();
  setTimeout(() => {
    editFormEfb()
  }, 500)

}

function fun_send_replayMessage_emsFormBuilder(id) {
  document.getElementById('replay_state__emsFormBuilder').innerHTML = `<i class="efb bi-hourglass-split mx-1"></i> ${efb_var.text.sending}`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  localStorage.removeItem('replayM_emsFormBuilder_'+id)
  let message = EfbResponseViewer.getEditorValue();
  message=message ? sanitize_text_efb(message) : null;
  if (message==null) return  valNotFound_efb()

  const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}];
  fun_sendBack_emsFormBuilder(ob[0])
  if (message.length < 1) {
    check_msg_ext_resp_efb();
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6 class="efb fs-7"><i class="efb bi-exclamation-triangle-fill nmsgefb"></i>${efb_var.text.error}: ${efb_var.text.pleaseEnterVaildValue}</h6>`;
    return
  }

  fun_send_replayMessage_ajax_emsFormBuilder(sendBack_emsFormBuilder_pub, id)

}

function getContentPreview_efb(contentStr) {
  try {
    const parsed = JSON.parse(replaceContentMessageEfb(contentStr));
    if (!Array.isArray(parsed)) return { short: '—', full: '' };
    const parts = [];
    for (const c of parsed) {
      if (!c || !c.value || c.value === '@file@') continue;
      if (c.type === 'maps' || c.type === 'esign' || c.type === 'payment' || c.type ==='w_link') continue;
      let val = String(c.value).replace(/<[^>]*>/g, '').replace(/@efb!/g, ',').replace(/@efb[^#]*#/g, ' ').replace(/,\s*$/, '').trim();
      if (val.length > 0) {
        const label = c.name || c.id_ || '';
        parts.push(label ? `${label}: ${val}` : val);
      }
    }
    const full = parts.join(' | ');
    const short = full.length > 50 ? full.substring(0, 50) + '…' : full;
    return { short: short || '—', full: full || '—' };
  } catch (e) {
    return { short: '—', full: '' };
  }
}

function fun_ws_show_list_messages(value) {

  let rows = '';
  let no = 1;
  let head = `<!-- rows -->`;
  let iconRead = 'bi-envelope-open';
  let iconNotRead = ' <path  d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>';
  const fun = pro_ws_efb == true ? "generat_csv_emsFormBuilder()" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
  const fun1 = pro_ws_efb == true ? "event_selected_row_emsFormBuilder('read')" : `pro_show_efb('${efb_var.text.availableInProversion}')`;

  if (form_type_emsFormBuilder == 'subscribe') {
    head = `<div class="efb d-flex mb-3"><button class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="subscribe" title="${efb_var.text.downloadCSVFileSub}" >  <i class="efb  bi-download mx-2"></i><span class="efb d-none d-sm-inline">${efb_var.text.downloadCSVFile}</span></button >
    `;
    iconRead = 'bi-person';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'register') {

    head = `<div class="efb d-flex mb-3"> <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="register"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2"></i><span class="efb d-none d-sm-inline">${efb_var.text.downloadCSVFile}</span></button >
    `;
    iconRead = 'bi-person ';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'survey') {

    head = `<div class="efb d-flex mb-3">
    <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb"  data-eventform="generateCSV" data-formtype="survey"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2"></i><span class="efb d-none d-sm-inline">${efb_var.text.downloadCSVFile}</span></button >
    <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb"  data-eventform="generateChart" data-formtype="survey"  onClick="convert_to_dataset_emsFormBuilder()" title="${efb_var.text.chart}" >  <i class="efb  bi-bar-chart-line mx-2"></i><span class="efb d-none d-sm-inline">${efb_var.text.chart}</span></button >
    `;
    iconRead = 'bi-chat-square-text';
    iconNotRead = ' <path  d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>';
  } else if (form_type_emsFormBuilder == 'form' || form_type_emsFormBuilder == 'payment') {

    head = `<div class="efb d-flex mb-3"> <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="payment"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2"></i><span class="efb d-none d-sm-inline">${efb_var.text.downloadCSVFile}</span></button >
    `;
  }
   head +=`
  <div class="efb" id="selectedBtnlistEfb">
  <button  class="efb  btn efb btn-danger text-white mt-2 ec-efb" data-eventform="deleteSelectedRow"  title="${efb_var.text.delete}" >  <i class="efb  bi-trash mx-2"></i></button >
  <button  class="efb  btn efb btn-secondary text-white mt-2 ec-efb" data-eventform="readSelectedRow" title="${efb_var.text.mread}" >  <i class="efb  mx-2 ${iconRead}"></i></button >
  </div>
  </div>
  `
  if (value.length > 0) {
    let no =1;
    for (const v of value) {
      let state = Number(v.read_);

      iconNotRead = `<div class="efb bi-envelope-fill nmsgefb" data-msgid="${v.msg_id}" data-msgstate="${state}" ></div>`;
      if(state==2){
         iconRead = 'bi-bag-x';
         iconNotRead = `<div class="efb bi-bag-x nmsgefb" data-msgid="${v.msg_id}" data-msgstate="${state}"></div>`;
      }
      $txtColor = state == 2 ? 'text-danger' : '';
      if (response_state_efb.findIndex(x => x.msg_id == v.msg_id) != -1) { state = 0 }
      const preview = getContentPreview_efb(v.content);
      const tooltipFull = preview.full.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      rows += `<tr class="efb  pointer-efb" id=""  >
        <th scope="col" class="efb"><input class="efb  emsFormBuilder_v form-check-input   fs-8 onemsg" type="checkbox"  value="checkbox"  data-id="${v.msg_id}"  onclick="fun_select_rows_table(this)"></th>
         <td class="efb ${$txtColor} ec-efb efb-content-cell" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" data-label="${efb_var.text.content || 'Content'}">
           <div class="efb-content-preview" data-efb-tooltip="${tooltipFull}">
             <span class="efb-preview-text">${preview.short}</span>
           </div>
         </td>
         <th scope="row" class="efb ${$txtColor} ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" data-label="${efb_var.text.trackNo}">
           <span class="efb d-inline-flex align-items-center gap-1">
             <code class="efb text-muted user-select-all" style="font-size:0.85em">${v.track}</code>
             <button type="button" class="efb btn btn-sm btn-outline-secondary border-0 px-1 py-0" onclick="event.stopPropagation();copyShortcode_efb('${v.track}',this ,'trackingCode')" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.copy}">
               <i class="efb bi-clipboard"></i>
             </button>
           </span>
         </th>
           <td class="efb ${$txtColor} ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" data-label="${efb_var.text.ddate}">${v.date}</td>
            <td class="efb efb-actions-cell" data-label="${efb_var.text.actions}">
              <div class="efb-actions-group">
                <button type="button" class="efb efb-act-btn efb-act-open ec-efb ${Number(state) != 1 && Number(state) != 4 ? 'efb-has-badge' : ''}" id="btn-m-${v.msg_id}" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" data-efb-tip="${Number(state) != 1 && Number(state) != 4 ? efb_var.text.newResponse : efb_var.text.read}" aria-label="${Number(state) != 1 && Number(state) != 4 ? efb_var.text.newResponse : efb_var.text.read}">
                  <i class="efb ${Number(state) != 1 && Number(state) != 4 ? 'bi-envelope-fill' : iconRead}"></i>
                  ${Number(state) != 1 && Number(state) != 4 ? '<span class="efb-noti-badge" role="status" aria-label="' + efb_var.text.newResponse + '"></span>' : ''}
                </button>
                <button type="button" class="efb efb-act-btn efb-act-delete ec-efb" id="btn-m-d-${v.msg_id}" data-eventform="deleteMsg" data-msgid="${v.msg_id}" data-trackid="${v.track}" data-efb-tip="${efb_var.text.delete}" aria-label="${efb_var.text.delete}">
                  <i class="efb bi-trash3"></i>
                </button>
              </div>
            </td>
            </tr>` ;
      no += 1;
    }
  } else {
    rows = `<tr class="efb efb"><td colspan="5" class="efb text-center py-5">
      <div class="efb-empty-state">
        <i class="efb bi-inbox efb-empty-icon"></i>
        <p class="efb-empty-title">${efb_var.text.noResponse}</p>
        <p class="efb-empty-desc">${efb_var.text.noResponseDesc || 'Submitted responses will appear here.'}</p>
      </div>
    </td></tr>`
  }

  document.getElementById('content-efb').innerHTML = `<div class="efb head-efb">${head}</div>
    <h4 class="efb title-holder efb fs-4 d-none"> <img src="${efb_var.images.title}" class="efb title efb">
    <i class="efb  bi-archive title-icon  mx-1 fs-4"></i>${efb_var.text.messages}
    </h4>
    <div class="efb card efb">
    <table class="efb table table-striped table-hover mt-3" id="emsFormBuilder-list">
    <thead>
    <th scope="col" class="efb"><input class="efb  emsFormBuilder_v form-check-input fs-8 allmsg" type="checkbox"  value="checkbox"   onclick="fun_select_rows_table(this)"></th>
    <th scope="col" class="efb">${efb_var.text.content || 'Content'}</th>
    <th scope="col" class="efb">${efb_var.text.trackNo}</th>
    <th scope="col" class="efb">${efb_var.text.ddate}</th>
    <th scope="col" class="efb">${efb_var.text.actions}</th>
    </tr>
    </thead>
    <tbody class="efb">
    ${rows}
    </tbody>
    </table>
    </div>
     ${typeof efb_powered_by === 'function' ? efb_powered_by() : ''}
    `;
  if (form_type_emsFormBuilder != 'login') fun_export_rows_for_Subscribe_emsFormBuilder(value);

}

function fun_delete_form_with_id_by_server(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "remove_id_Emsfb",
      type: "POST",
      id: id,
      nonce: _efb_core_nonce_,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        const m = efb_var.text.tDeleted.replace('%s', efb_var.text.form.replace('%s1','').toLowerCase());
        setTimeout(() => {
          alert_message_efb(m, '', 5, 'success')
        }, 3)
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error, '', 5, 'danger')
        }, 3)
      }
    })
  });

}
function fun_delete_message_with_id_by_server(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "remove_message_id_Emsfb",
      type: "POST",
      id: id,
      nonce: _efb_core_nonce_,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        const m = efb_var.text.tDeleted.replace('%s', efb_var.text.message.replace('%s1','').toLowerCase());
        setTimeout(() => {
          alert_message_efb(m, '', 5, 'success')
        }, 3)
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error, '', 5, 'danger')
        }, 3)
      }
    })
  });

}
function fun_delete_all_message_by_server(val) {

  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "remove_messages_Emsfb",
      type: "POST",
      val: JSON.stringify(val),
      state: 'msg',
      nonce: _efb_core_nonce_,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.data.success == true) {
        const m = efb_var.text.tDeleted.replace('%s', efb_var.text.message.replace('%s1','').toLowerCase());
        setTimeout(() => {
          alert_message_efb(m, '', 5, 'success')

        }, 3)
        location.reload();
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error,res.data.m, 3, 'danger')
        }, 3)
      }
    })
  });

}

function emsFormBuilder_messages(id) {
  id = Number(id);
  const row = ajax_object_efm.ajax_value.find(x => Number(x.form_id) == id)
  efb_var.msg_id =id;
  form_type_emsFormBuilder = row.form_type;
  history.pushState("show-message",null,`?page=Emsfb&state=show-messages&id=${id}&form_type=${row.form_type}`);
  fun_get_messages_by_id(Number(id));
  emsFormBuilder_waiting_response();
  fun_backButton_efb(0);
}

function fun_open_message_emsFormBuilder(msg_id, state) {
  show_modal_efb(efbLoadingCard('',4), '', '', 'saveBox');
  state_modal_show_efb(1)

  if (state == 0 || state == 3) {
    const btn = document.getElementById(`btn-m-${msg_id}`);
    if (btn) {
      btn.classList.remove('efb-has-badge');
      let iconRead = 'bi-envelope-open';
      if (form_type_emsFormBuilder == 'subscribe' || form_type_emsFormBuilder == 'register') {
        iconRead = 'bi-person';
      } else if (form_type_emsFormBuilder == 'survey') {
        iconRead = 'bi-chat-square-text';
      }
      btn.innerHTML = `<i class="efb ${iconRead} text-muted"></i>`;
      btn.setAttribute('data-efb-tip', efb_var.text.read);
      btn.setAttribute('aria-label', efb_var.text.read);
    }
  }

  fun_emsFormBuilder_get_all_response_by_id(Number(msg_id));
  emsFormBuilder_show_content_message(msg_id)
  if (state == 0 || state == 3) {
    fun_update_message_state_by_id(msg_id);
  }
}

function fun_get_form_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  sessionStorage.removeItem('valj_efb');
  sessionStorage.removeItem('Edit_ws_form');
  jQuery(function ($) {
    data = {
      action: "get_form_id_Emsfb",
      type: "POST",
      nonce: _efb_core_nonce_,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        try {
          const value = efb_safe_json_parse(res.data.ajax_value);
          const len = value.length
          const p = calPLenEfb(len) + 1;
          valj_efb = value;
          setTimeout(() => {
            formName_Efb = valj_efb[0].formName;
            form_type_emsFormBuilder=valj_efb[0].type
            form_ID_emsFormBuilder = id;
            sessionStorage.setItem('valj_efb', JSON.stringify(value));
            const edit = { id: res.data.id, edit: true };
            sessionStorage.setItem('Edit_ws_form', JSON.stringify(edit))
            fun_ws_show_edit_form(id);
            state_page_efb = 'edit';
            localStorage.setItem('efb_auto_save', 0);
          }, len * p)
        } catch (error) {
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
    });
  });
}
function fun_update_message_state_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "update_message_state_Emsfb",
      type: "POST",
      nonce: _efb_core_nonce_,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        let iconRead = `<i class="efb  bi-envelope-open text-muted"></iv>`;
        if (form_type_emsFormBuilder == 'subscribe') {
          iconRead = `<i class="efb  bi-person text-muted"></iv>`;
        } else if (form_type_emsFormBuilder == 'register') {
          iconRead = `<i class="efb  bi-person text-muted"></iv>`;
        }
        document.getElementById(`btn-m-${id}`).innerHTML = iconRead;
        if(document.getElementById(`efbCountM`))document.getElementById(`efbCountM`).innerHTML = parseInt(document.getElementById(`efbCountM`).innerHTML) - 1;

        if (res.data.ajax_value != undefined) {
          const value = efb_safe_json_parse(res.data.ajax_value);
          sessionStorage.setItem('valueJson_ws_p', JSON.stringify(value));
          const edit = { id: res.data.id, edit: true };
          sessionStorage.setItem('Edit_ws_form', JSON.stringify(edit))
          fun_ws_show_edit_form(id)
        }
      }
    })
  });
}
function fun_get_messages_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "get_messages_id_Emsfb",
      nonce: _efb_core_nonce_,
      type: "POST",
      form: form_type_emsFormBuilder,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        valueJson_ws_messages = res.data.ajax_value;
        efb_var.nonce_msg = res.data.nonce_msg

          efb_var.msg_id = res.data.id

        fun_ws_show_list_messages(valueJson_ws_messages)
      } else {
      }
    })
  });
}
function fun_emsFormBuilder_get_all_response_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  jQuery(function ($) {
    data = {
      action: "get_all_response_id_Emsfb",
      nonce: _efb_core_nonce_,
      type: "POST",
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.success == true) {
        fun_ws_show_response(res.data.ajax_value);
      }

      state_rply_btn_efb(100)
    })
  });
}

function fun_send_replayMessage_ajax_emsFormBuilder(message, id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }

  if (message.length < 1) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    document.getElementById('replayM_emsFormBuilder').value = "";
    var _re = document.getElementById('efb_rich_editor'); if (_re) _re.innerHTML = '';
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    return;
  }

  jQuery(function ($) {
    data = {
      action: "set_replyMessage_id_Emsfb",
      type: "POST",
      nonce: _efb_core_nonce_,
      id: id,
      message: JSON.stringify(message)
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.success == true) {

        if(document.getElementById('replay_state__emsFormBuilder')){

          document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
          document.getElementById('replayM_emsFormBuilder').value = "";
          document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
          const date = Date();
          document.getElementById('replayM_emsFormBuilder').value = "";
          const richEditor = document.getElementById('efb_rich_editor');
          if (richEditor) richEditor.innerHTML = '';
          fun_emsFormBuilder__add_a_response_to_messages(message, message[0].by, ajax_object_efm.user_ip, 0, date);
          const chatHistory = document.getElementById("resp_efb");
          chatHistory.scrollTop = chatHistory.scrollHeight;
          sendBack_emsFormBuilder_pub=[];
        }else{
          alert_message_efb(res.data.m,'', 7 , 'info')
        }
        localStorage.removeItem('replayM_emsFormBuilder_'+id);

      } else {
        if(document.getElementById('replay_state__emsFormBuilder')){
          document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
          document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
          document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply;

        }else{
          alert_message_efb(res.data.m,'', 12 , 'danger')
        }
      }
    })
  });
}

function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {

  const resp = fun_emsFormBuilder_show_messages(message, by, userIp, track, date);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}

function fun_ws_show_response(value) {
  for (let v of value) {

    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'Message', value: 'message not exists' }
    fun_emsFormBuilder__add_a_response_to_messages(content, v.rsp_by, v.ip, 0, v.date);
  }
}

function fun_show_content_page_emsFormBuilder(state) {
  if (state == "forms") {
    document.getElementById('content-efb').innerHTML = `<div class="efb card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb -color text-center"> ${efb_var.text.loading}</div>`
    history.pushState("setting",null,'?page=Emsfb');
    window.location.reload();
  } else if (state == "setting" || state == "reload-setting") {
    history.pushState("setting",null,'?page=Emsfb&state=setting');
    fun_show_setting__emsFormBuilder();
    fun_backButton_efb(0);
    state = 2
    const s = sanitize_text_efb(getUrlparams_efb.get('save'));
    if(s=='ok') alert_message_efb("", efb_var.text.saved, 7, "info");
  } else if (state == "help") {
    history.pushState("help",null,'?page=Emsfb&state=help');
    fun_show_help__emsFormBuilder();
    state = 4
  }else if (state=='search'){
    history.pushState("search",null,'?page=Emsfb&state=search');
    document.getElementById("track_code_emsFormBuilder").value =sanitize_text_efb(localStorage.getItem('search_efb'));
    fun_find_track_emsFormBuilder();
  }else if(state=="show-messages"){
    document.getElementById('content-efb').innerHTML = `<div class="efb card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb -color text-center"> ${efbLoadingCard('',4)}</div>`
    history.pushState("setting",null,'?page=Emsfb');
    window.location.reload();
  }else if(state=="edit-form"){
   const v =sanitize_text_efb(getUrlparams_efb.get('id'));
      fun_get_form_by_id(Number(v));
      fun_backButton_efb();
      fun_hande_active_page_emsFormBuilder(1);
  }

  fun_hande_active_page_emsFormBuilder(state);

}

function fun_hande_active_page_emsFormBuilder(no) {
  let count = 0;
  for (const el of document.querySelectorAll(`.nav-link`)) {
    count += 1;
    if (el.classList.contains('active')) el.classList.remove('active');
    if (count == no) el.classList.add('active');
  }
}

function fun_show_help__emsFormBuilder() {
  document.getElementById("more_emsFormBuilder").style.display = "none";
  let $lan =lan_subdomain_wsteam_efb();
  const ws = `https://${$lan}whitestudio.team/document/`;
  listOfHow_emsfb = {
    1: { title: efb_var.text.howProV, url: `${ws}/how-to-activate-pro-version-easy-form-builder-plugin/` },
    2: { title: efb_var.text.howConfigureEFB, url: `${ws}/how-to-set-up-form-notification-emails-in-easy-form-builder/#settingUp-Notification` },
    3: { title: efb_var.text.howGetGooglereCAPTCHA, url: `${ws}/how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder/` },
    4: { title: efb_var.text.howActivateAlertEmail, url: `${ws}/how-to-set-up-form-notification-emails-in-easy-form-builder/#email-notification` },
    5: { title: efb_var.text.howCreateAddForm, url: `${ws}/how-to-create-your-first-form-with-easy-form-builder/` },
    6: { title: efb_var.text.howActivateTracking, url: `${ws}/how-to-activate-confirmation-code-in-easy-form-builder/` },
    7: { title: efb_var.text.howWorkWithPanels, url: `${ws}/complete-guide-of-form-entries-and-mange-forms/` },
    8: { title: efb_var.text.howAddTrackingForm, url: `${ws}/how-to-add-the-confirmation-code-finder/` },
    9: { title: efb_var.text.howFindResponse, url: `${ws}/how-to-find-a-response-through-a-confirmation-code/` },
  }

  if(efb_var.language == "fa_IR"){
    const ef = `https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/`
    listOfHow_emsfb = {
      1: { title: efb_var.text.howProV, url: `${ef}%d9%86%d8%ad%d9%88%d9%87-%d9%81%d8%b9%d8%a7%d9%84-%d8%b3%d8%a7%d8%b2%db%8c-%d9%86%d8%b3%d8%ae%d9%87-%d9%88%db%8c%da%98%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      2: { title: efb_var.text.howConfigureEFB, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%db%8c%d9%85%db%8c%d9%84-%d8%a7%d8%b7%d9%84%d8%a7%d8%b9-%d8%b1%d8%b3%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3/` },
      3: { title: efb_var.text.howGetGooglereCAPTCHA, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%da%a9%d9%be%da%86%d8%a7-%da%af%d9%88%da%af%d9%84-%d8%b1%d8%a7-%d8%af%d8%b1%db%8c%d8%a7%d9%81%d8%aa-%d9%88-%d8%af%d8%b1-%d8%a7%d9%81%d8%b2%d9%88%d9%86%d9%87-%d9%81/` },
      4: { title: efb_var.text.howActivateAlertEmail, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%db%8c%d9%85%db%8c%d9%84-%d8%a7%d8%b7%d9%84%d8%a7%d8%b9-%d8%b1%d8%b3%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3/` },
      5: { title: efb_var.text.howCreateAddForm, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/` },
      6: { title: efb_var.text.howActivateTracking, url: `${ef}%d9%86%d8%ad%d9%88%d9%87-%d9%81%d8%b9%d8%a7%d9%84-%d8%b3%d8%a7%d8%b2%db%8c-%da%a9%d8%af-%d9%be%db%8c%da%af%db%8c%d8%b1%db%8c-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      7: { title: efb_var.text.howWorkWithPanels, url: `${ef}%d8%b1%d9%88%d8%b4-%d9%85%d8%af%db%8c%d8%b1%db%8c%d8%aa-%d9%81%d8%b1%d9%85-%d9%87%d8%a7%db%8c-%d9%be%d8%b1-%d8%b4%d8%af%d9%87%d9%be%d8%a7%d8%b3%d8%ae-%d9%87%d8%a7-%d8%af%d8%b1-%d8%a7%d9%81%d8%b2/` },
      8: { title: efb_var.text.howAddTrackingForm, url: `${ef}%d8%a7%d9%86%d8%aa%d8%b4%d8%a7%d8%b1-%db%8c%d8%a7%d8%a8%d9%86%d8%af%d9%87-%da%a9%d8%af-%d9%be%db%8c%da%af%db%8c%d8%b1%db%8c-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      9: { title: efb_var.text.howFindResponse, url: `${ef}%d9%be%db%8c%d8%af%d8%a7-%da%a9%d8%b1%d8%af%d9%86-%d9%be%db%8c%d8%a7%d9%85-%da%a9%d8%af-%d8%b1%d9%87%da%af%db%8c%d8%b1%db%8c-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d9%88%d8%b1/` },
    }
  }

  let str = "";
  for (const l in listOfHow_emsfb) {
    str += `<a class="efb btn efb btn-darkb text-white btn-lg d-block mx-3 mt-2" target="_blank" href="${listOfHow_emsfb[l].url}"><i class="efb  bi-youtube mx-1"></i>${listOfHow_emsfb[l].title}</a>`
  }
  document.getElementById('content-efb').innerHTML = `
  <img src="${efb_var.images.title}"  class="efb crcle-footer">
  <div class="efb container row">
  <h4 class="efb title-holder efb fs-4 d-none">
      <img src="${efb_var.images.title}" class="efb title efb">
      <i class="efb  bi-info-circle title-icon mx-2"></i>${efb_var.text.lrnmrs.replace('%s', '')}
  </h4>
  <div class="efb crd efb col-md-7 d-none d-md-block"><div class="efb card-body"> <div class="efb d-grid gap-2">${str}</div></div></div>
  <div class="efb col-md-4 mx-1 py-5 crd efb">
              <div class="efb mt-2 pd-5 col-md-12">
                  <img src="${efb_var.images.logo}"  class="efb description-logo efb">
                  <h1 class="efb  pointer-efb ec-efb fs-5" data-eventform="links" data-linkname="ws" ><font style="vertical-align: inherit;"><font style="vertical-align: inherit;">${efb_var.text.easyFormBuilder}</font></font></h1>
                  <h2 class="efb  pointer-efb  card-text ec-efb fs-7" data-eventform="links" data-linkname="ws">${efb_var.text.byWhiteStudioTeam}</h2>
              </div>
                  <div class="efb clearfix"></div>
                  <p class="efb  card-text efb pb-3 fs-6">
                  ${efb_var.text.youCanFindTutorial} ${efb_var.text.proUnlockMsg}
                  </p>
                  ${efb_var.pro == true ||  efb_var.pro == 1 ? '' : `<a class="efb btn text-dark btn-r btn-warning  btn-lg ec-efb"  data-eventform="links" data-linkname="price"><i class="efb  bi-gem mx-1"></i>${efb_var.text.activateProVersion}</a>`}
                  <a class="efb btn mt-1 efb btn-outline-pink btn-lg ec-efb" data-eventform="links" data-linkname="wiki"><i class="efb  bi-info-circle mx-1"></i>${efb_var.text.documents}</a>
              </div>
  </div>
  ${typeof efb_powered_by === 'function' ? efb_powered_by() : ''}
 `;
}

function fun_show_setting__emsFormBuilder() {

  let activeCode = 'null';
  let sitekey = 'null';
  let secretkey = 'null';
  let stripeSKey = 'null';
  let stripePKey = 'null';
  let paypalSKey = 'null';
  let paypalPKey = 'null';
  let email = 'null';
  let trackingcode = 'null';
  let apiKeyMap = 'null';
  let smtp = false;
  let text = efb_var.text;
  let textList = "<!--list EFB -->";
  let bootstrap = false;
  let emailTemp = "null"
  let payToken="null";
  let sessionDuration = 1;
  let trackCodeStyle = 'date_en_mix';
  let act_local_efb =scaptcha =false;
  let dsupfile= showIp =activeDlBtn =scaptcha=act_local_efb =false;

  let phoneNumbers=sms_method = 'null';
  let femail ='null';
  let demail ='no-reply@'+ window.location.hostname;
  let osLocationPicker = false;
  let shieldSilentCaptcha = false;
  let emailBtnBgColor = '#202a8d';
  let emailBtnTextColor = '#ffffff';
  const shieldAvailable = efb_var.shield_available === true || efb_var.shield_available === 1 || efb_var.shield_available === '1' || efb_var.shield_available === 'true';
  const translateDiscountPercent = 60;
  let respPrimary = '#3644d2';
  let respPrimaryDark = '#202a8d';
  let respAccent = '#ffc107';
  let respText = '#1a1a2e';
  let respTextMuted = '#657096';
  let respBgCard = '#ffffff';
  let respBgMeta = '#f6f7fb';
  demail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(demail)  &&  demail.includes('127.')==false ? demail : 'no-reply@yourDomainName.com';
  if ((ajax_object_efm.setting[0] && ajax_object_efm.setting[0].setting.length > 5) || typeof valueJson_ws_setting == "object" && valueJson_ws_setting.length != 0) {

    if (valueJson_ws_setting.length == 0) {
      const rawSetting2 = ajax_object_efm.setting[0].setting;
      try { valueJson_ws_setting = JSON.parse(rawSetting2); }
      catch(e) { valueJson_ws_setting = JSON.parse(rawSetting2.replace(/[\\]/g, '')); }
    } else if (typeof valueJson_ws_setting == "string") {
      try { valueJson_ws_setting = JSON.parse(valueJson_ws_setting); }
      catch(e) { valueJson_ws_setting = JSON.parse(valueJson_ws_setting.replace(/[\\]/g, '')); }
    }
    const f = (name) => {
      if (valueJson_ws_setting.hasOwnProperty(name)==true) { return valueJson_ws_setting[name] } else { return 'null' } }
    if (valueJson_ws_setting.text) text = valueJson_ws_setting.text

    activeCode = f('activeCode');
    sitekey = f(`siteKey`);
    secretkey = f(`secretKey`);
    email = f(`emailSupporter`);
    femail = f('femail');

    femail = femail=='null' ? demail : femail;
    trackingcode = f(`trackingCode`);
    apiKeyMap = f(`apiKeyMap`);
    stripeSKey = f(`stripeSKey`);
    stripePKey = f(`stripePKey`);
    paypalSKey =  f(`paypalSKey`);
    paypalSKey = paypalSKey == 'null' ? '' : paypalSKey;
    paypalPKey = f(`paypalPKey`);
    paypalPKey = paypalPKey == 'null' ? '' : paypalPKey;
    smtp = f('smtp') == 'null' ? false : Boolean(f('smtp'));
    bootstrap = f('bootstrap');
    osLocationPicker = f('osLocationPicker') == 'null' ? false : Boolean(f('osLocationPicker'));
    emailTemp = f('emailTemp');
    emailTemp = emailTemp!='null' ? efb_url_convert_url(emailTemp) : '';
    sms_config_efb= sms_method = f('sms_config')=='null' ? 'null' :f('sms_config');

    scaptcha = f('scaptcha')=='null' ? false :f('scaptcha') ;
    activeDlBtn = f('activeDlBtn')=='null' ? true :f('activeDlBtn');
    showIp = f('showIp') =='null' ? false :f('showIp');
    dsupfile = f('dsupfile') =='null' ? true :f('dsupfile');
    phoneNumbers = f('phnNo');
    adminSN  = f('adminSN') =='null' ? false :f('adminSN');
    sessionDuration = f('sessionDuration') == 'null' ? 1 : parseInt(f('sessionDuration'));
    trackCodeStyle = f('trackCodeStyle') == 'null' ? 'date_en_mix' : f('trackCodeStyle');
    const shieldSilentCaptchaSetting = f('shield_silent_captcha');
    shieldSilentCaptcha = shieldSilentCaptchaSetting === true || shieldSilentCaptchaSetting === 1 || shieldSilentCaptchaSetting === '1' || shieldSilentCaptchaSetting === 'true';

    respPrimary = f('respPrimary') == 'null' ? '#3644d2' : f('respPrimary');
    respPrimaryDark = f('respPrimaryDark') == 'null' ? '#202a8d' : f('respPrimaryDark');
    respAccent = f('respAccent') == 'null' ? '#ffc107' : f('respAccent');
    respText = f('respText') == 'null' ? '#1a1a2e' : f('respText');
    respTextMuted = f('respTextMuted') == 'null' ? '#657096' : f('respTextMuted');
    respBgCard = f('respBgCard') == 'null' ? '#ffffff' : f('respBgCard');
    respBgMeta = f('respBgMeta') == 'null' ? '#f6f7fb' : f('respBgMeta');
    respBgTrack = f('respBgTrack') == 'null' ? '#ffffff' : f('respBgTrack');
    respBgResp = f('respBgResp') == 'null' ? '#f8f9fd' : f('respBgResp');
    respBgEditor = f('respBgEditor') == 'null' ? '#ffffff' : f('respBgEditor');
    respEditorText = f('respEditorText') == 'null' ? '#1a1a2e' : f('respEditorText');
    respEditorPh = f('respEditorPh') == 'null' ? '#a0aec0' : f('respEditorPh');
    respBtnText = f('respBtnText') == 'null' ? '#ffffff' : f('respBtnText');
    respFontFamily = f('respFontFamily') == 'null' ? 'inherit' : f('respFontFamily');
    respFontSize = f('respFontSize') == 'null' ? '0.9rem' : f('respFontSize');
    respCustomFont = f('respCustomFont') == 'null' ? '' : f('respCustomFont');
    emailBtnBgColor = f('emailBtnBgColor') == 'null' ? '#202a8d' : f('emailBtnBgColor');
    emailBtnTextColor = f('emailBtnTextColor') == 'null' ? '#ffffff' : f('emailBtnTextColor');

    payToken = f('payToken');
    act_local_efb = f('act_local_efb');

    act_local_efb= act_local_efb =='null'  || act_local_efb==false ? false :true
  }

  let persianPayToken = () => {
    const visible = efb_var.language == "fa_IR" ? "style='display:block'" : "style='display:none'";
      return `
      <div ${visible}>
      <h5 class="efb  card-title mt-3 mobile-title"> <i class="efb bi-credit-card-2-front m-3"></i>درگاه پرداخت</h5>
      <p class="efb mx-5">توکن: <a class="efb  pointer-efb ec-efb" data-eventform="links" data-linkname="AdnPPF">توکن دریافتی از درگاه پرداخت خود را در زیر وارد کنید</a></p>
      <div class="efb mx-3 my-2">
        <div class="efb card-body mx-0 py-1 ${mxCSize4}">
          <label class="efb form-label mx-2 fs-6">توکن</label>
          <input type="text" class="efb form-control w-75 h-d-efb border-d  efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''} " id="payToken_emsFormBuilder"placeholder="توکن" ${payToken !== "null" ? `value="${payToken}"` : ""} data-tab="${efb_var.text.payment}">
        </div>
      </div>
      </div>
    `

  }

  Object.entries(text).forEach(([key, value]) => {
    state = key == "easyFormBuilder" ? "d-none" : "d-block";
    if (key != "forbiddenChr") textList += `<input type="text"  id="${key}"  class="efb sen w-75 sen-validate-efb ${state} form-control text-muted efb  border-d efb-rounded h-d-efb  m-2"  placeholder="${value}" id="labelEl" required value="${value ? value : ''}" data-tab="${efb_var.text.localization}">`
  });
  const mxCSize = !mobile_view_efb ? 'mx-5' : 'mx-1';
  const mxCSize4 = !mobile_view_efb ? 'mx-4' : 'mx-1';
  let msg_email = efb_var.text.mlntip.replace('%1$s', `<a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="EmailSpam" >`).replace('%2$s', '</a>').replace('%3$s', `<a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="support" >`).replace('%4$s', '</a>');
  const is_pro = efb_var.pro == true || efb_var.pro == "true" ? true : false;
  const proChckEvent =is_pro ? `onChange="pro_show_efb('${efb_var.text.proUnlockMsg}')"` :'';
  const stripemessage = efb_var.text.ufinyf.replace('%1$s', efb_var.text.payment.toLowerCase()).replace('%2$s', efb_var.text.stripe);
  const paypalmessage = efb_var.text.ufinyf.replace('%1$s', efb_var.text.payment.toLowerCase()).replace('%2$s', efb_var.text.paypal);
  const package_type = efb_var.setting.hasOwnProperty('package_type') ? Number(efb_var.setting.package_type) : Number(efb_var.pro) ;

  const language_not_needed_show =['fa_IR','ar_AR' ,'fr_FR','de','en_US'].includes(efb_var.language) ? false : true;
  let message_lanaguage = ''
  console.log('language_not_needed_show', language_not_needed_show)
  if(language_not_needed_show){
    message_lanaguage = `<div class="efb my-3 mx-4 p-3" role="" style="border-radius:10px;border:1px solid #e0e7ff;background:linear-gradient(135deg,#f0f4ff 0%,#e8f5e9 100%);">
                                 <p class="efb mb-2" style="line-height:1.7;">
                                   <i class="efb bi-translate" style="margin-inline-end:6px;"></i>${efb_var.text.translateContrib.replace('%1$s', `<a class="efb pointer-efb ec-efb" style="font-weight:600;text-decoration:underline;" data-eventform="links" data-linkname="translateWP">`).replace('%2$s', '</a>')}
                                 </p>
                                 <div class="efb" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                   <span style="display:inline-block;background:linear-gradient(135deg,#ff6b35,#f7c948);color:#fff;font-weight:700;font-size:13px;padding:4px 12px;border-radius:20px;white-space:nowrap;">🎁 ${translateDiscountPercent}% ${efb_var.text.discountOff || 'OFF'}</span>
                                   <p class="efb mb-0" style="line-height:1.6;font-size:13px;color:#37474f;">
                                     ${efb_var.text.translateDiscount ? efb_var.text.translateDiscount.replace('%1$s', `<a class="efb pointer-efb ec-efb" style="font-weight:600;text-decoration:underline;" data-eventform="links" data-linkname="translateWP">`).replace('%2$s', '</a>').replace('%3$s', translateDiscountPercent + '%') : ''}
                                   </p>
                                 </div>
                               </div>`
  }

  const planBadgeHtml = getCurrentPlanBadge_efb();
  document.getElementById('content-efb').innerHTML = `
  <div class="efb container">
            <h4 class="efb title-holder efb fs-4 d-none">
                <img src="${efb_var.images.title}" class="efb title efb">
                <i class="efb  bi-gear title-icon mx-1"></i>${efb_var.text.setting}
            </h4>
            <div class="efb crd efb">
                <div class="efb card-body">
                        <nav>
                            <div class="efb nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="efb  nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><i class="efb  bi bi-gear mx-2"></i>${efb_var.text.general}</button>
                            <button class="efb  nav-link " id="nav-response-tab" data-bs-toggle="tab" data-bs-target="#nav-response" type="button" role="tab" aria-controls="nav-respons" aria-selected="true"><i class="efb  bi bi-chat-left-text mx-2"></i>${efb_var.text.rspcon}</button>
                            <button class="efb  nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-google" type="button" role="tab" aria-controls="nav-profile" aria-selected="false"><i class="efb  bi bi-robot mx-2"></i>${efb_var.text.captchas}</button>
                            <button class="efb  nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-email" type="button" role="tab" aria-controls="nav-contact" aria-selected="false"><i class="efb  bi bi-at mx-2"></i>${efb_var.text.emailSetting}</button>
                            <button class="efb  nav-link" id="nav-contact-tab " data-bs-toggle="tab" data-bs-target="#nav-emailtemplate" type="button" role="tab" aria-controls="nav-emailtemplate" aria-selected="false"><i class="efb  bi bi-envelope mx-2"></i>${efb_var.text.emailTemplate}</button>
                            <button class="efb  nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-text" type="button" role="tab" aria-controls="nav-text" aria-selected="false"><i class="efb  bi bi-fonts mx-2"></i>${efb_var.text.localization}</button>
                            <button class="efb  nav-link" id="nav-stripe-tab" data-bs-toggle="tab" data-bs-target="#nav-stripe" type="button" role="tab" aria-controls="nav-stripe" aria-selected="false"><i class="efb  bi bi-credit-card mx-2"></i>${efb_var.text.payments}</button>
                            <button class="efb  nav-link" id="nav-smsconfig-tab" data-bs-toggle="tab" data-bs-target="#nav-smsconfig" type="button" role="tab" aria-controls="nav-smsconfig" aria-selected="false"><i class="efb  bi bi-chat-left-dots mx-2"></i>${efb_var.text.sms_config}</button>
                        </div>
                        </nav>
                        <div class="efb tab-content" id="nav-tabContent">
                          <div class="efb tab-pane fade show active" id="nav-general" role="tabpanel" aria-labelledby="nav-home-tab">
                            <!--General-->
                            <div class="efb m-3">
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-gem m-3"></i>${efb_var.text.activationCode}
                                </h5>
                                <!-- 3.8.6 start -->
                                ${package_type == 1 ? '' :`<a class="efb ${mxCSize} efb pointer-efb ec-efb" data-eventform="links" data-linkname="price">${efb_var.text.clickHereGetActivateCode}</a>`}
                                <!-- 3.8.6 end -->
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${package_type == 1 ? 'is-valid bg-light' : ''}" id="activeCode_emsFormBuilder" placeholder="${efb_var.text.enterActivateCode}" ${activeCode !== "null" ? `value="${activeCode}"` : ""} data-tab="${efb_var.text.general}">
                                ${package_type == 1 ? `<p class="efb text-darkb fs-7 mx-2 ">${efb_var.text.actvtcmsg}</p>` : '' }
                                    <span id="activeCode_emsFormBuilder-message" class="efb text-danger"></span>
                                </div>

                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-layers m-3"></i>${efb_var.text.plnMng}
                                </h5>
                                <p class="efb  ${mxCSize} mobile-text">${efb_var.text.plnMngD}</p>
                                <div class="efb d-flex align-items-center gap-2 mb-3 ${mxCSize}" id="efbCurrentPlanBadge">
                                    ${planBadgeHtml}
                                </div>
                                <div class="efb card-body text-center py-1">
                                    <button type="button" class="efb btn efb btn-outline-primary btn-lg" onclick="showSetupAsOverlayPage()" id="changePlanBtn">
                                        <i class="efb  bi-gear mx-1 efb mobile-text"></i>${efb_var.text.chngPln}
                                    </button>
                                    <p class="efb text-muted fs-7 mt-2">${efb_var.text.plnMngSw}</p>
                                </div>
                                <!--
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-bootstrap m-3 mobile-text"></i>${efb_var.text.bootStrapTemp}
                                </h5>
                                <h6 class="efb  ${mxCSize} text-danger mobile-text">${efb_var.text.iUsebootTempW}</h6>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" id="bootstrap_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${bootstrap == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="bootstrap_emsFormBuilder">${efb_var.text.iUsebootTemp}</label>
                                </div>
                                -->
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-file-earmark-minus m-3"></i>${efb_var.text.clearFiles}
                                </h5>
                                <p class="efb  ${mxCSize} mobile-text">${efb_var.text.youCanRemoveUnnecessaryFileUploaded}</p>
                                <div class="efb card-body text-center py-1">
                                    <button type="button" class="efb btn efb btn-outline-pink btn-lg " OnClick="clear_garbeg_emsFormBuilder()" id="clrUnfileEfb">
                                        <i class="efb  bi-x-lg mx-1 efb mobile-text"></i><span id="clrUnfileEfbText">${efb_var.text.clearUnnecessaryFiles}</span
                                    </button>
                                </div>

                                 <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  m-3 bi-pin-map-fill mobile-text"></i>${efb_var.text.locationPicker}
                                </h5>
                                <p class="efb  ${mxCSize} mobile-text">${efb_var.text.lpds}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" id="osLocationPicker_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${osLocationPicker == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="osLocationPicker_emsFormBuilder">${efb_var.text.elpo}</label>
                                </div>

                              <!-- Development Mode Toggle -->
                              <h5 class="efb card-title mt-4 mobile-title">
                                <i class="efb bi-code-slash m-3"></i>${efb_var.text.devMode}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.devModeDesc}</p>
                              <div class="efb card-body mx-0 py-0 ${mxCSize4}">
                                  <button type="button" id="devMode_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${devMode_efb == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off" >
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="devMode_emsFormBuilder">${efb_var.text.devMode}</label>
                              </div>
                              <div class="efb mx-4 mt-3 mb-2">
                                <div class="efb d-flex align-items-start p-3 rounded-3" style="background:linear-gradient(135deg,#fffde7 0%,#fff8e1 100%);border-left:4px solid #f9a825;border-top:1px solid #ffe08233;border-right:1px solid #ffe08233;border-bottom:1px solid #ffe08233;box-shadow:0 1px 4px rgba(249,168,37,0.10);">
                                  <i class="efb bi-exclamation-triangle-fill" style="color:#f9a825;font-size:1.25rem;min-width:28px;margin-top:1px;"></i>
                                  <div class="efb mx-2" style="line-height:1.6;">
                                    <span class="efb" style="color:#5d4037;font-size:0.9rem;">${efb_var.text.devModeWarn}</span>
                                  </div>
                                </div>
                              </div>
                                <div class="efb clearfix"></div>

                            <!--End General-->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-response" role="tabpanel" aria-labelledby="nav-response-tab">
                            <!--response-->
                            <div class="efb m-3">

                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-search m-3"></i>${efb_var.text.trackingCodeFinder}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.copyAndPasteBelowShortCodeTrackingCodeFinder}</p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                      <div class="efb row efb col-12">
                                          <div class="efb  col-md-8">
                                            <input type="text"  class="efb form-control efb h-d-efb  border-d efb-rounded my-1" id="shortCode_emsFormBuilder" value="[Easy_Form_Builder_confirmation_code_finder]" readonly>
                                            <span id="shortCode_emsFormBuilder-message" class="efb text-danger"></span>
                                          </div>
                                            <button type="button" class="efb btn col-md-4 efb btn-r h-d-efb btn-outline-pink my-1" onclick="copyCodeEfb('shortCode_emsFormBuilder','copyshortCodeEfb')">
                                                <i class="efb  bi-clipboard-check mx-1"></i><span id="copyshortCodeEfb">${efb_var.text.copy}</span>
                                            </button>
                                        </div>
                              </div>

                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-clock m-3"></i>${efb_var.text.sessionDuration}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.sessionDurationDesc}</p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                      <div class="efb row efb col-12">
                                          <div class="efb  col-md-8">
                                            <select class="efb form-control efb h-d-efb  border-d efb-rounded my-1" id="sessionDuration_emsFormBuilder" data-tab="${efb_var.text.rspcon}">
                                                <option value="">${efb_var.text.selectDuration}</option>
                                                <option value="1" ${sessionDuration == 1 ? 'selected' : ''}>${efb_var.text.sessionDurationDay.replace('%s', '1')}</option>
                                                <option value="2" ${sessionDuration == 2 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '2')}</option>
                                                <option value="3" ${sessionDuration == 3 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '3')}</option>
                                                <option value="4" ${sessionDuration == 4 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '4')}</option>
                                                <option value="5" ${sessionDuration == 5 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '5')}</option>
                                                <option value="6" ${sessionDuration == 6 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '6')}</option>
                                                <option value="7" ${sessionDuration == 7 ? 'selected' : ''}>${efb_var.text.sessionDurationDays.replace('%s', '7')}</option>
                                            </select>
                                            <span id="sessionDuration_emsFormBuilder-message" class="efb text-danger"></span>
                                          </div>
                                        </div>
                              </div>

                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-hash m-3"></i>${efb_var.text.trackNo}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.trackCodeStyleDesc.replace('%s', efb_var.text.trackNo)}</p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                      <div class="efb row efb col-12">
                                          <div class="efb  col-md-8">
                                            <select class="efb form-control efb h-d-efb  border-d efb-rounded my-1" id="trackCodeStyle_emsFormBuilder" data-tab="${efb_var.text.rspcon}" onchange="efb_preview_track_code(this.value)">
                                                ${efb_build_track_options(trackCodeStyle)}
                                            </select>
                                            <span id="trackCodeStyle_emsFormBuilder-message" class="efb text-danger"></span>
                                          </div>
                                        </div>
                                      <div class="efb mt-2 mx-1">
                                        <span class="efb text-muted small">${efb_var.text.preview}: </span>
                                        <code id="trackCodePreview_efb" class="efb px-2 py-1" style="font-size:1.05em;letter-spacing:1px;background:#f6f7fb;border-radius:6px;">${efb_generate_track_preview(trackCodeStyle)}</code>
                                      </div>
                              </div>

                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-chat-left-text m-3"></i>${efb_var.text.rbox}
                              </h5>
                                <div class="efb card-body mx-0 py-0 ${mxCSize4}">
                                  <button type="button" id="scaptcha_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${scaptcha == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   onclick="efb_check_el_pro(this)">
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="scaptcha_emsFormBuilder">${efb_var.text.scaptcha}</label>
                                </div>

                                <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="showUpfile_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${dsupfile == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"  onclick="efb_check_el_pro(this)" >
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="showUpfile_emsFormBuilder">${efb_var.text.dsupfile}</label>

                                </div>
                                <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="activeDlBtn_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${activeDlBtn == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   onclick="efb_check_el_pro(this)">
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="activeDlBtn_emsFormBuilder">${efb_var.text.sdlbtn}</label>
                                </div>
                               <!-- <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="showIp_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${showIp == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   >
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="showIp_emsFormBuilder">${efb_var.text.sips}</label>
                                </div> -->
                               <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="adminSN_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${adminSN == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"  onclick="efb_check_el_pro(this)" >
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="adminSN_emsFormBuilder">${efb_var.text.admines}</label>
                                </div>

                              <!-- Response Box Color Settings -->
                              <h5 class="efb card-title mt-4 mobile-title">
                                <i class="efb bi-palette m-3"></i>${efb_var.text.respColors}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.respColorsDesc}</p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" class="efb efb-customize-colors-btn" onclick="${is_pro ? 'efb_open_color_modal()' : 'pro_show_efb(3)'}">
                                  <i class="efb bi-palette2"></i> ${efb_var.text.respClrCustomize}
                                </button>
                              </div>
                              <!-- Hidden color inputs (read by save function) -->
                              <div style="display:none">
                                <input type="color" id="respPrimary_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respPrimary}">
                                <input type="color" id="respPrimaryDark_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respPrimaryDark}">
                                <input type="color" id="respAccent_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respAccent}">
                                <input type="color" id="respText_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respText}">
                                <input type="color" id="respTextMuted_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respTextMuted}">
                                <input type="color" id="respBgCard_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBgCard}">
                                <input type="color" id="respBgMeta_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBgMeta}">
                                <input type="color" id="respBgTrack_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBgTrack}">
                                <input type="color" id="respBgResp_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBgResp}">
                                <input type="color" id="respBgEditor_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBgEditor}">
                                <input type="color" id="respEditorText_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respEditorText}">
                                <input type="color" id="respEditorPh_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respEditorPh}">
                                <input type="color" id="respBtnText_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respBtnText}">
                                <input type="hidden" id="respFontFamily_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respFontFamily}">
                                <input type="hidden" id="respFontSize_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respFontSize}">
                                <input type="hidden" id="respCustomFont_emsFormBuilder" data-tab="${efb_var.text.rspcon}" value="${respCustomFont.replace(/"/g, '&quot;')}">
                              </div>

                            <!--End Response Customize window-->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-google" role="tabpanel" aria-labelledby="nav-profile-tab">
                            <div class="efb m-3">
                                <div id="message-google-efb"></div>

                             <!--Google-->

                             ${apiKeyMap == 'null' ? `<div class="efb m-3 p-3 efb alert-info" role=""><h5 class="efb alert-heading">🎉 ${efb_var.text.SpecialOffer} </h5> <div>${googleCloudOffer()} </div></div>` : ``}
                             <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-person-check m-3"></i>${efb_var.text.reCAPTCHAv2}
                            </h5>
                            <p class="efb ${mxCSize}"><a target="_blank" href="https://youtu.be/JI7RojBgU_o">${efb_var.text.lmavt.replace('%s',efb_var.text.grecaptcha)}</a></p>
                            <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.siteKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="sitekey_emsFormBuilder" placeholder="${efb_var.text.siteKey}" ${sitekey !== "null" ? `value="${sitekey}"` : ""} data-tab="${efb_var.text.captchas}">
                                <span id="sitekey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                <label class="efb  form-label mx-2 col-12  mt-4 fs-6">${efb_var.text.SecreTKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="secretkey_emsFormBuilder" placeholder="${efb_var.text.SecreTKey}" ${secretkey !== "null" ? `value="${secretkey}"` : ""} data-tab="${efb_var.text.captchas}">
                                <span id="secretkey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            </div>

                            <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb bi-shield-check m-3"></i>${efb_var.text.shieldSilentCaptcha}
                            </h5>
                            <p class="efb ${mxCSize}">${efb_var.text.shieldSilentCaptchaDesc}</p>
                            <p class="efb ${mxCSize}"><a target="_blank" href="https://clk.shldscrty.com/silentcaptchaintegrationhelp"  rel="nofollow noopener" >${efb_var.text.lmavt.replace('%s','Shield silentCAPTCHA')}</a></p>
                            ${shieldAvailable ? '' : `<p class="efb ${mxCSize} text-warning">${efb_var.text.shieldNotDetected}</p>`}
                            <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" id="shieldSilentCaptcha_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${shieldSilentCaptcha == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off" ${shieldAvailable ? '' : 'disabled aria-disabled="true"'}>
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="shieldSilentCaptcha_emsFormBuilder">${efb_var.text.shieldSilentCaptcha}</label>
                            </div>

                            <h5 class="efb  card-title mt-3 mobile-title d-none">
                                <i class="efb  bi-geo-alt m-3"></i> ${efb_var.text.maps}
                            </h5>
                             <a href="#" class="efb d-none">${efb_var.text.clickHereWatchVideoTutorial}</a>
                            <p class="efb ${mxCSize} d-none">${efb_var.text.youNeedAPIgMaps}</p>
                            <div class="efb  d-none card-body mx-0 py-1 ${mxCSize4}">
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.aPIKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="apikey_map_emsFormBuilder" placeholder="${efb_var.text.enterAPIKey}" ${apiKeyMap !== "null" ? `value="${apiKeyMap}"` : ""} ${proChckEvent} data-tab="${efb_var.text.captchas}">
                                <span id="apikey_map_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            </div>

                              <!--End Google-->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-email" role="tabpanel" aria-labelledby="nav-contact-tab">
                            <div class="efb mx-3 ">
                                <!--Email-->
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-at m-3"></i>${efb_var.text.alertEmail}
                                </h5>
                                <p class="efb ${mxCSize}">${efb_var.text.whenEasyFormBuilderRecivesNewMessage}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4} mb-3">
                                    <label class="efb form-label mx-2 fs-6">${efb_var.text.email}</label>
                                    <input type="email" class="efb form-control w-75 h-d-efb border-d efb-rounded mb-1 ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="${efb_var.text.enterAdminEmail}" ${email !== "null" ? `value="${email}"` : ""} data-tab="${efb_var.text.emailSetting}">
                                    <span id="email_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                    <span  class="efb bg-light text-dark form-control border-0  w-75 efb">${msg_email}</span>
                                </div>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4} mb-3">
                                    <label class="efb form-label mx-2 fs-6">${efb_var.text.from}</label>
                                    <input type="email" class="efb form-control w-75 h-d-efb border-d efb-rounded mb-1 ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="femail_emsFormBuilder"  ${efb_var.pro != true  &&  efb_var.pro != 1 ? 'onclick="pro_show_efb(1)"' :''} placeholder="${'no-reply@'+ window.location.hostname}" ${femail !== "null" ? `value="${femail}"` : ""} data-tab="${efb_var.text.emailSetting}">
                                    <span id="femail_emsFormBuilder-message" class="efb  text-danger  w-75 efb"></span>
                                    <span  class="efb  form-control border-0  w-75 efb">${efb_var.text.msgfml}</span>
                                </div>

                                <h5 class="efb card-title mt-3col-12 efb ">
                                    <i class="efb  bi-envelope m-3"></i>${efb_var.text.emailServer}
                                </h5>
                                <p class="efb ${mxCSize}">${efb_var.text.beforeUsingYourEmailServers}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                    <button type="button" class="efb btn  efb btn-outline-pink btn-lg "onclick="clickToCheckEmailServer()" id="clickToCheckEmailServer">
                                        <i class="efb  bi-chevron-double-up mx-1 text-center"></i>${efb_var.text.clickToCheckEmailServer}
                                    </button>
                                   <input type="hidden" id="smtp_emsFormBuilder" value="${smtp == "null" ? 'false' : smtp}">
                                </div>
                                <div class="efb card-body mx-0 py-1 mx-4">

                                <button type="button" id="hostSupportSmtp_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${smtp == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="hostSupportSmtp_emsFormBuilder">${efb_var.text.hostSupportSmtp}</label>

                                </div>
                                <!--End Email-->
                            </div>
                        </div>

                        <div class="efb tab-pane fade" id="nav-text" role="tabpanel" aria-labelledby="nav-text-tab">
                            <div class="efb mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb  card-title mt-3 mobile-title">
                                 <i class="efb  bi-fonts m-3"></i>${efb_var.text.localization}
                               </h5>
                                ${message_lanaguage}
                               <p class="efb ${mxCSize}">${efb_var.text.translateLocal}</p>
                               <div class="efb card-body mx-0 py-1 mx-4">

                               <button type="button" id="act_local_efb" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${act_local_efb == true ? "active" : ""}" onclick="act_local_efb_event(this);"  data-toggle="button" aria-pressed="false" autocomplete="off"   >
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-7 efb m-2 " for="act_local_efb">${efb_var.text.ilclizeFfb}</label>

                                </div>
                                <div id="textList-efb"  class="efb mt-2 py-2 ${mobile_view_efb ? '' : 'px-5'}  ${act_local_efb == false ? "d-none" : ''}">${textList} </div>
                                <!-- END Text Section -->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-stripe" role="tabpanel" aria-labelledby="nav-stripe-tab">
                            <div class="efb mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb  card-title mt-3 mobile-title">
                                 <i class="efb  bi-stripe m-3"></i>${efb_var.text.stripe}
                               </h5>
                               <!-- 3.8.6 start -->
                               <p class="efb ${mxCSize}">${stripemessage} <a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="stripe" >${efb_var.text.lrnmrs.replace('%s', '')}</a></p>
                                <!-- 3.8.6 end -->
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                  <label class="efb form-label mx-2 fs-6">${efb_var.text.publicKey}</label>
                                  <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="stripePKey_emsFormBuilder" placeholder="${efb_var.text.publicKey}" ${stripePKey !== "null" ? `value="${stripePKey}"` : ""} ${proChckEvent} data-tab="${efb_var.text.payment}">
                                  <span id="stripePKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                  <label class="efb  form-label mx-2 fs-6 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                  <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="stripeSKey_emsFormBuilder" placeholder="${efb_var.text.SecreTKey}" ${stripeSKey !== "null" ? `value="${stripeSKey}"` : ""} ${proChckEvent} data-tab="${efb_var.text.payment}">
                                  <span id="stripeSKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>

                              </div>
                              <div class="efb ${efb_var.addons.hasOwnProperty('AdnPAP') && efb_var.addons.AdnPAP == 1 ? '' : 'd-none'}">
                                  <h5 class="efb  card-title mt-3 mobile-title">
                                  <i class="efb  bi-paypal m-3"></i>${efb_var.text.paypal}
                                </h5>
                                <p class="efb ${mxCSize}">${paypalmessage} <a class="efb  pointer-efb" onclick="Link_emsFormBuilder('paypal')" >${efb_var.text.lrnmrs.replace('%s', '')}</a></p>
                                  <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                    <label class="efb form-label mx-2 fs-6">${efb_var.text.publicKey}</label>
                                    <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="paypalPKey_emsFormBuilder" placeholder="${efb_var.text.publicKey}" value="${paypalPKey}" ${proChckEvent} data-tab="${efb_var.text.payment}">
                                    <span id="paypalPKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                    <label class="efb  form-label mx-2 fs-6 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                    <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="paypalSKey_emsFormBuilder" placeholder="${efb_var.text.SecreTKey}" value="${paypalSKey}" ${proChckEvent} data-tab="${efb_var.text.payment}">
                                    <span id="paypalSKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                  </div>
                              </div>
                              ${persianPayToken()}

                                <!-- END payment Section -->
                            </div>
                        </div>

                        <div class="efb tab-pane fade" id="nav-emailtemplate" role="tabpanel" aria-labelledby="nav-contact-tab">
                        <div class="efb my-2 mx-1">
                          <!-- Drag & Drop Email Template Builder -->
                          <div id="efb-email-builder"></div>
                          <!-- Hidden textarea keeps the same ID for save/validation compatibility -->
                          <textarea class="efb form-control" id="emailTemp_emsFirmBuilder" rows="5" data-tab="${efb_var.text.emailTemplate}" style="display:none;">${emailTemp}</textarea>
                          <input type="hidden" id="emailBtnBgColor_emsFormBuilder" value="${emailBtnBgColor}">
                          <input type="hidden" id="emailBtnTextColor_emsFormBuilder" value="${emailBtnTextColor}">
                          <span id="emailTemp_emsFirmBuilder-message" class="efb text-danger"></span>
                        </div>
                    </div>
                        <!-- smsconfig Section -->
                        <div class="efb tab-pane fade" id="nav-smsconfig" role="tabpanel" aria-labelledby="nav-smsconfig-tab">
                          <div class="efb mx-3 my-2">

                            <h5 class="efb  card-title mt-3 ">
                              <i class="efb  bi-chat-left-dots m-3"></i>${efb_var.text.sms_config}
                            </h5>
                            <p class="efb ${mxCSize}">${efb_var.text.sms_mp} <a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="smsconfig" >${efb_var.text.lrnmrs.replace('%s', '')}</a></p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.sms_ct}</label>
                                <div class="efb  col-md-12 col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show" data-id="sms_config_select" id="sms_config_select" >
                                  <div class="efb     efb1 " data-css="sms_config_select" id="sms_config_select_options">
                                      <!-- <div class="efb  form-check  radio  efb1 " data-css="sms_config_select" data-parent="sms_config_select" data-id="efb_sms_service" id="efb_sms_service-v" >
                                        <input class="efb  form-check-input emsFormBuilder_v   fs-7 disabled " data-tag="radio" data-type="radio" data-vid="sms_config_select" type="radio" name="sms_config_select" value="efb_sms_service" id="efb_sms_service" data-id="efb_sms_service-id" data-op="efb_sms_service" onchange="check_server_sms_method_efb(this)" data-tab="${efb_var.text.sms_config}">
                                        <label class="efb   text-labelEfb  h-d-efb fs-7 hStyleOpEfb " id="efb_sms_service_lab" for="efb_sms_service">${efb_var.text.sms_efbs}</label>
                                      </div> -->
                                      <div class="efb  form-check  radio  efb1 " data-css="sms_config_select" data-parent="sms_config_select" data-id="wp_sms_plugin" id="wp_sms_plugin-v">
                                        <input class="efb  form-check-input emsFormBuilder_v   fs-7 disabled" data-tag="radio" data-type="radio" data-vid="sms_config_select" type="radio" name="sms_config_select" value="wp_sms_plugin" id="wp_sms_plugin" data-id="wp_sms_plugin-id" data-op="wp_sms_plugin" onchange="check_server_sms_method_efb(this)" data-tab="${efb_var.text.sms_config}" ${sms_method=="wpsms" ? 'checked' :''}>
                                        <label class="efb   text-labelEfb  h-d-efb fs-7 hStyleOpEfb " id="wp_sms_plugin_lab" for="wp_sms_plugin">${efb_var.text.sms_wpsmss}</label>
                                        <i class="mx-1 efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="wpsmss" > </i>
                                      </div>
                                  </div>
                                </div>
                              </div>
                          </div>
                          <h5 class="efb  card-title mt-3 ">
                              <i class="efb bi-phone m-3"></i>${efb_var.text.sms_noti}
                            </h5>
                          <p class="efb ${mxCSize}">${efb_var.text.sms_dnoti}</p>
                          <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                          <label class="efb form-label mx-2 fs-6">${efb_var.text.sms_admn_no}</label>
                            <input type="tel" inputmode="tel" class="efb form-control w-75 h-d-efb border-d efb-rounded ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="pno_emsFormBuilder" pattern="^[\\+0-9\\(\\)\\-\\s,]+$" placeholder="+12345678900" ${phoneNumbers !== "null" ? `value="${phoneNumbers}"` : ""}  data-tab="${efb_var.text.sms_config}" oninput="filterPhoneNumberInput_efb(this)" onkeypress="allowOnlyPhoneChars_efb(event)" autocomplete="off">
                            <small class="efb text-muted d-block mt-1 fs-7" ><i class="efb bi-info-circle fs-7" style="margin-inline-end:4px;"></i>${efb_var.text.phoneFormatHint || 'Format: +12345678900 or +1 (234) 567-8900'}</small>
                            <span id="pno_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            <p class="efb m-2">${efb_var.text.sms_ndnoti}</p>
                          </div>
                        </div>
                        <!-- smsconfig Section end-->
                        <button type="button" id="save-stng-efb" class="efb btn btn-r btn-primary btn-lg ${Number(efb_var.rtl) == 1 ? 'float-start' : 'float-end '}" mt-2 mx-5"  onclick="fun_set_setting_emsFormBuilder(0)">
                            <i class="efb  bi-save mx-1"></i>${efb_var.text.save}
                        </button>
                </div>
            </div>
            </div>
            ${typeof efb_powered_by === 'function' ? efb_powered_by() : ''}
`

  if (typeof efbEmailBuilder !== 'undefined' && document.getElementById('efb-email-builder')) {
    efbEmailBuilder.init();
  }

  for (const el of document.querySelectorAll(`.sen`)) {
    el.addEventListener("change", (e) => {
      if (el.value.match(/["'\\]/) != null) {
        el.className = colorBorderChangerEfb(el.className, "border-danger")
        fun_switch_saveSetting(true, el.id);
      } else {
        text[el.id] = el.value;
        efb_var.text[el.id] = el.value;
        el.className = colorBorderChangerEfb(el.className, "border-d")
        fun_switch_saveSetting(false, el.id);
      }
    })
  }

}

function efb_open_color_modal() {

  const colorDefs = [
    { key: 'respPrimary',     label: efb_var.text.respClrPrimary,    group: 'brand' },
    { key: 'respPrimaryDark', label: efb_var.text.respClrPrimaryDk,  group: 'brand' },
    { key: 'respAccent',      label: efb_var.text.respClrAccent,     group: 'brand' },
    { key: 'respText',        label: efb_var.text.respClrText,       group: 'text' },
    { key: 'respTextMuted',   label: efb_var.text.respClrMuted,      group: 'text' },
    { key: 'respBgCard',      label: efb_var.text.respClrBgCard,     group: 'bg' },
    { key: 'respBgMeta',      label: efb_var.text.respClrBgMeta,     group: 'bg' },
    { key: 'respBgTrack',     label: efb_var.text.respClrBgTrack,    group: 'bg' },
    { key: 'respBgResp',      label: efb_var.text.respClrBgResp,     group: 'bg' },
    { key: 'respBgEditor',    label: efb_var.text.respClrBgEditor,   group: 'editor' },
    { key: 'respEditorText',  label: efb_var.text.respClrEditorText, group: 'editor' },
    { key: 'respEditorPh',    label: efb_var.text.respClrEditorPh,   group: 'editor' },
    { key: 'respBtnText',     label: efb_var.text.respClrBtnText,    group: 'brand' },
  ];
  const defaults = {
    respPrimary: '#3644d2', respPrimaryDark: '#202a8d', respAccent: '#ffc107',
    respText: '#1a1a2e', respTextMuted: '#657096', respBgCard: '#ffffff', respBgMeta: '#f6f7fb',
    respBgTrack: '#ffffff', respBgResp: '#f8f9fd', respBgEditor: '#ffffff',
    respEditorText: '#1a1a2e', respEditorPh: '#a0aec0', respBtnText: '#ffffff',
    respFontFamily: 'inherit', respFontSize: '0.9rem',
    respCustomFont: '',
  };

  const _efbLang = (efb_var.language || '').toLowerCase();
  const _efbIsPersian = _efbLang.startsWith('fa');
  const _efbIsArabic = _efbLang.startsWith('ar');
  const _efbIsRtlLang = _efbIsPersian || _efbIsArabic;

  const fontFamilies = [
    { value: 'inherit', label: 'Default (Inherit)' },
  ];

  if (_efbIsPersian) {
    fontFamilies.push(
      { value: "Vazirmatn, Tahoma, sans-serif", label: 'Vazirmatn (فارسی)' },
      { value: "Vazir, Tahoma, sans-serif", label: 'Vazir (فارسی)' },
      { value: "Sahel, Tahoma, sans-serif", label: 'Sahel (فارسی)' },
      { value: "Samim, Tahoma, sans-serif", label: 'Samim (فارسی)' },
      { value: "'Shabnam', Tahoma, sans-serif", label: 'Shabnam (فارسی)' },
      { value: "Parastoo, Tahoma, sans-serif", label: 'Parastoo (فارسی)' },
      { value: "Gandom, Tahoma, sans-serif", label: 'Gandom (فارسی)' },
      { value: "Lalezar, Tahoma, sans-serif", label: 'Lalezar (فارسی)' },
    );
  }

  if (_efbIsArabic) {
    fontFamilies.push(
      { value: "Cairo, Tahoma, sans-serif", label: 'Cairo (عربی)' },
      { value: "Tajawal, Tahoma, sans-serif", label: 'Tajawal (عربی)' },
      { value: "'Noto Sans Arabic', Tahoma, sans-serif", label: 'Noto Sans Arabic (عربی)' },
      { value: "'IBM Plex Sans Arabic', Tahoma, sans-serif", label: 'IBM Plex Sans Arabic (عربی)' },
      { value: "Amiri, Tahoma, serif", label: 'Amiri (عربی)' },
      { value: "'Noto Kufi Arabic', Tahoma, sans-serif", label: 'Noto Kufi Arabic (عربی)' },
    );
  }

  fontFamilies.push(
    { value: 'system-ui, -apple-system, sans-serif', label: 'System UI' },
    { value: "'Segoe UI', Tahoma, Geneva, sans-serif", label: 'Segoe UI' },
    { value: "'Helvetica Neue', Helvetica, Arial, sans-serif", label: 'Helvetica' },
    { value: "'Inter', sans-serif", label: 'Inter' },
    { value: "'Roboto', sans-serif", label: 'Roboto' },
    { value: "'Open Sans', sans-serif", label: 'Open Sans' },
    { value: "Tahoma, Geneva, sans-serif", label: 'Tahoma' },
    { value: "Georgia, 'Times New Roman', serif", label: 'Georgia (Serif)' },
    { value: "'Courier New', Courier, monospace", label: 'Courier (Mono)' },
    { value: '__custom__', label: '✦ ' + (efb_var.text.respCustomFont || 'Custom Font') + '...' },
  );

  const fontCssMap = {
    "Vazirmatn, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap",
    "Vazir, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@latest/dist/font-face.css",
    "Sahel, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@latest/dist/font-face.css",
    "Samim, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@latest/dist/font-face.css",
    "'Shabnam', Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@latest/dist/font-face.css",
    "Parastoo, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/parastoo-font@latest/dist/font-face.css",
    "Gandom, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/gandom-font@latest/dist/font-face.css",
    "Lalezar, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Lalezar&display=swap",
    "Cairo, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap",
    "Tajawal, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap",
    "'Noto Sans Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100..900&display=swap",
    "'IBM Plex Sans Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100;200;300;400;500;600;700&display=swap",
    "Amiri, Tahoma, serif": "https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap",
    "'Noto Kufi Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@100..900&display=swap",
    "'Inter', sans-serif": "https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap",
    "'Roboto', sans-serif": "https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap",
    "'Open Sans', sans-serif": "https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..800&display=swap",
  };

  const fontSizes = [
    { value: '0.75rem', label: '12px' },
    { value: '0.8rem',  label: '13px' },
    { value: '0.85rem', label: '14px' },
    { value: '0.9rem',  label: '15px' },
    { value: '0.95rem', label: '16px' },
    { value: '1rem',    label: '17px' },
    { value: '1.05rem', label: '18px' },
    { value: '1.1rem',  label: '19px' },
    { value: '1.15rem', label: '20px' },
  ];

  const cur = {};
  colorDefs.forEach(d => {
    const el = document.getElementById(`${d.key}_emsFormBuilder`);
    cur[d.key] = el ? el.value : defaults[d.key];
  });
  const curFontFamily = document.getElementById('respFontFamily_emsFormBuilder')?.value || defaults.respFontFamily;
  const curFontSize = document.getElementById('respFontSize_emsFormBuilder')?.value || defaults.respFontSize;
  const curCustomFont = document.getElementById('respCustomFont_emsFormBuilder')?.value || defaults.respCustomFont;

  let customFontName = '', customFontUrl = '';
  if (curCustomFont) {
    try {
      const cf = JSON.parse(curCustomFont);
      customFontName = cf.name || '';
      customFontUrl = cf.url || '';
    } catch (e) {  }
  }
  const isCustomSelected = curFontFamily === '__custom__' || (curFontFamily !== defaults.respFontFamily && customFontName && curFontFamily.indexOf(customFontName) !== -1);

  const makePickerHtml = (group) => colorDefs.filter(d => d.group === group).map(d => `
    <div class="efb col-6 col-md-4">
      <label class="efb form-label fw-semibold small mb-1">${d.label}</label>
      <div class="efb d-flex align-items-center gap-2">
        <input type="color" class="efb form-control form-control-color border-d" data-color-key="${d.key}" value="${cur[d.key]}" title="${d.label}">
        <code class="efb small text-muted efb-color-hex">${cur[d.key]}</code>
      </div>
    </div>`).join('');

  const fontFamilyOpts = fontFamilies.map(ff => {
    let sel = '';
    if (ff.value === '__custom__' && isCustomSelected) sel = 'selected';
    else if (ff.value !== '__custom__' && ff.value === curFontFamily && !isCustomSelected) sel = 'selected';
    return `<option value="${ff.value}" ${sel}>${ff.label}</option>`;
  }).join('');
  const fontSizeOpts = fontSizes.map(fs =>
    `<option value="${fs.value}" ${fs.value === curFontSize ? 'selected' : ''}>${fs.label}</option>`).join('');

  const previewHtml = `
    <div class="efb-color-modal-preview" id="efbColorPreviewBox">
      <div class="efb-preview-title">${efb_var.text.respClrPreview}</div>
      <!-- Response card preview -->
      <div class="efb-preview-header">
        <div class="efb-preview-avatar"><i class="bi bi-person"></i></div>
        <div class="efb-preview-sender">${efb_var.text.by || 'Sender'}:<span style="font-weight:400;margin-inline-start:4px">${efb_var.text.guest || 'Guest'}</span></div>
      </div>
      <div class="efb-preview-meta"><i class="bi bi-calendar3" style="margin-inline-end:4px"></i>2026-02-16  12:30<span class="efb-preview-accent"></span></div>
      <div class="efb-preview-field"><span class="efb-preview-field-label">${efb_var.text.email || 'Email'}</span><span class="efb-preview-field-value">user@example.com</span></div>
      <div class="efb-preview-field"><span class="efb-preview-field-label">${efb_var.text.name || 'Name'}</span><span class="efb-preview-field-value">John Doe</span></div>
      <!-- Editor preview -->
      <div class="efb-preview-editor-wrap" style="margin-top:10px;border:1px solid var(--efb-resp-border);border-radius:8px;overflow:hidden">
        <div class="efb-preview-editor-area" style="padding:8px 10px;min-height:32px;background:var(--efb-resp-bg-editor);color:var(--efb-resp-editor-text);font-size:var(--efb-resp-font-size);font-family:var(--efb-resp-font-family)">
          <span class="efb-preview-editor-ph" style="color:var(--efb-resp-editor-ph);opacity:0.8">${efb_var.text.replyMsg || 'Type your reply...'}</span>
        </div>
      </div>
      <button class="efb-preview-btn" disabled style="color:var(--efb-resp-btn-text)"><i class="bi bi-reply me-1"></i>${efb_var.text.reply || 'Reply'}</button>
      <!-- Tracker mini preview -->
      <div class="efb-preview-tracker-wrap" style="margin-top:12px;padding:18px 14px;border-radius:14px;background:var(--efb-resp-bg-track);border:1px solid var(--efb-resp-border);display:flex;flex-direction:column;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,0.04)">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--efb-resp-primary),var(--efb-resp-primary-dark));display:flex;align-items:center;justify-content:center;margin-bottom:8px;box-shadow:0 3px 10px var(--efb-resp-primary-10)"><i class="bi bi-shield-check" style="color:#fff;font-size:1.15rem"></i></div>
        <div style="font-weight:700;color:var(--efb-resp-text);font-family:var(--efb-resp-font-family);margin-bottom:2px;font-size:var(--efb-resp-font-size)">${efb_var.text.trackNo || 'Confirmation Code'}</div>
        <div style="color:var(--efb-resp-text-muted);font-family:var(--efb-resp-font-family);margin-bottom:8px;font-size:calc(var(--efb-resp-font-size) * 0.87)">${efb_var.text.trackingCode || 'Tracking Code'}</div>
        <div style="width:100%;position:relative;margin-bottom:8px">
          <i class="bi bi-hash" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);color:var(--efb-resp-editor-ph);font-size:0.95rem"></i>
          <div style="padding:10px 12px 10px 32px;border:1.5px solid var(--efb-resp-border);border-radius:10px;background:var(--efb-resp-bg-editor);color:var(--efb-resp-editor-ph);font-size:var(--efb-resp-font-size);font-family:var(--efb-resp-font-family);opacity:0.65">${efb_var.text.entrTrkngNo || 'Enter tracking number'}</div>
        </div>
        <div style="width:100%;padding:10px;border:none;border-radius:10px;background:linear-gradient(65deg,var(--efb-resp-primary),var(--efb-resp-primary-dark));color:var(--efb-resp-btn-text);font-family:var(--efb-resp-font-family);font-weight:600;text-align:center;font-size:var(--efb-resp-font-size)"><i class="bi bi-search" style="margin-inline-end:6px"></i>${efb_var.text.search || 'Search'}</div>
      </div>
    </div>`;

  const section = (icon, title, content) => `
    <div class="efb-clr-section" style="margin-bottom:14px">
      <h6 class="efb" style="font-size:0.82rem;font-weight:700;color:#4a5078;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px"><i class="bi ${icon}" style="margin-inline-end:6px"></i>${title}</h6>
      <div class="efb row g-3 efb-resp-color-grid">${content}</div>
    </div>`;

  const body = `
    <div class="efb-color-modal-body">
      ${previewHtml}
      ${section('bi-palette-fill', efb_var.text.respClrPrimary + ' & ' + efb_var.text.respClrAccent, makePickerHtml('brand'))}
      ${section('bi-fonts', efb_var.text.respClrText, makePickerHtml('text'))}
      ${section('bi-square-half', efb_var.text.respClrBgCard, makePickerHtml('bg'))}
      ${section('bi-pencil-square', efb_var.text.respClrBgEditor, makePickerHtml('editor'))}
      <div class="efb-clr-section" style="margin-bottom:14px">
        <h6 class="efb" style="font-size:0.82rem;font-weight:700;color:#4a5078;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px"><i class="bi bi-type" style="margin-inline-end:6px"></i>${efb_var.text.respFontFamily} & ${efb_var.text.respFontSize}</h6>
        <div class="efb row g-3">
          <div class="efb col-6">
            <label class="efb form-label fw-semibold small mb-1">${efb_var.text.respFontFamily}</label>
            <select class="efb form-select form-select-sm border-d efb-rounded" id="efbModalFontFamily">${fontFamilyOpts}</select>
          </div>
          <div class="efb col-6">
            <label class="efb form-label fw-semibold small mb-1">${efb_var.text.respFontSize}</label>
            <select class="efb form-select form-select-sm border-d efb-rounded" id="efbModalFontSize">${fontSizeOpts}</select>
          </div>
        </div>
        <!-- Custom Font Fields -->
        <div class="efb-custom-font-area" id="efbCustomFontArea" style="display:${isCustomSelected ? 'block' : 'none'};margin-top:12px;padding:14px;border:1.5px dashed var(--efb-resp-border, #ced4ee);border-radius:12px;background:#f8f9fd">
          <div class="efb d-flex align-items-center gap-2" style="margin-bottom:8px">
            <i class="bi bi-fonts" style="color:#4a5078;font-size:1.1rem"></i>
            <span class="efb fw-semibold small" style="color:#4a5078">${efb_var.text.respCustomFont || 'Custom Font'}</span>
          </div>
          <p class="efb small text-muted" style="margin:0 0 10px;line-height:1.45">${efb_var.text.respCustomFontDesc || 'Add your own font by entering the font name and its CSS URL.'}</p>
          <div class="efb row g-2">
            <div class="efb col-12 col-md-5">
              <input type="text" class="efb form-control form-control-sm border-d efb-rounded" id="efbCustomFontName" placeholder="${efb_var.text.respCustomFontName || 'Font Name'}" value="${customFontName}" style="font-size:0.85rem" autocomplete="off">
            </div>
            <div class="efb col-12 col-md-7">
              <input type="url" class="efb form-control form-control-sm border-d efb-rounded" id="efbCustomFontUrl" placeholder="${efb_var.text.respCustomFontUrl || 'Font URL (CSS/Google Fonts)'}" value="${customFontUrl}" style="font-size:0.85rem;direction:ltr" autocomplete="off">
            </div>
          </div>
          <div class="efb small text-muted" style="margin-top:8px;line-height:1.4">
            <i class="bi bi-info-circle" style="margin-inline-end:4px"></i>
            <span>Example: <code style="font-size:0.78rem;direction:ltr;display:inline-block">https:
          </div>
        </div>
      </div>
      <div class="efb d-flex justify-content-end">
        <button type="button" class="efb btn btn-sm btn-outline-secondary efb-rounded" id="efbColorResetModal">
          <i class="efb bi-arrow-counterclockwise me-1"></i>${efb_var.text.respClrReset}
        </button>
      </div>
    </div>`;

  show_modal_efb(body, efb_var.text.respColors, 'bi-palette', 'saveBox');
  state_modal_show_efb(1);

  setTimeout(() => {
    const previewBox = document.getElementById('efbColorPreviewBox');
    const modal = document.getElementById('settingModalEfb-body');
    if (!modal) return;

    const varMap = {
      respPrimary: '--efb-resp-primary', respPrimaryDark: '--efb-resp-primary-dark',
      respAccent: '--efb-resp-accent', respText: '--efb-resp-text',
      respTextMuted: '--efb-resp-text-muted', respBgCard: '--efb-resp-bg-card',
      respBgMeta: '--efb-resp-bg-meta', respBgTrack: '--efb-resp-bg-track',
      respBgResp: '--efb-resp-bg-resp', respBgEditor: '--efb-resp-bg-editor',
      respEditorText: '--efb-resp-editor-text', respEditorPh: '--efb-resp-editor-ph',
      respBtnText: '--efb-resp-btn-text',
    };

    const refreshPreview = () => {
      if (!previewBox) return;
      modal.querySelectorAll('input[type="color"][data-color-key]').forEach(inp => {
        const key = inp.dataset.colorKey;
        if (varMap[key]) previewBox.style.setProperty(varMap[key], inp.value);
        if (key === 'respPrimary') {
          const v = inp.value;
          const r = parseInt(v.slice(1,3),16), g = parseInt(v.slice(3,5),16), b = parseInt(v.slice(5,7),16);
          previewBox.style.setProperty('--efb-resp-primary-08', `rgba(${r},${g},${b},0.08)`);
          previewBox.style.setProperty('--efb-resp-primary-10', `rgba(${r},${g},${b},0.10)`);
          previewBox.style.setProperty('--efb-resp-border', `rgba(${r},${g},${b},0.12)`);
        }
      });
      const ff = document.getElementById('efbModalFontFamily');
      const fs = document.getElementById('efbModalFontSize');
      if (ff) {
        let fontVal = ff.value;
        if (fontVal === '__custom__') {
          const cfName = document.getElementById('efbCustomFontName')?.value?.trim();
          if (cfName) fontVal = "'" + cfName + "', sans-serif";
          else fontVal = 'inherit';
        }
        previewBox.style.setProperty('--efb-resp-font-family', fontVal);
      }
      if (fs) previewBox.style.setProperty('--efb-resp-font-size', fs.value);
    };

    modal.querySelectorAll('input[type="color"][data-color-key]').forEach(inp => {
      const hexLabel = inp.closest('.d-flex')?.querySelector('.efb-color-hex');
      inp.addEventListener('input', () => {
        if (hexLabel) hexLabel.textContent = inp.value;
        const hidden = document.getElementById(`${inp.dataset.colorKey}_emsFormBuilder`);
        if (hidden) hidden.value = inp.value;
        refreshPreview();
      });
    });

    const ffSelect = document.getElementById('efbModalFontFamily');
    const fsSelect = document.getElementById('efbModalFontSize');
    const customArea = document.getElementById('efbCustomFontArea');
    const cfNameInput = document.getElementById('efbCustomFontName');
    const cfUrlInput = document.getElementById('efbCustomFontUrl');

    const loadCustomFontPreview = (url) => {
      let link = document.getElementById('efbCustomFontLink');
      if (!url) { if (link) link.remove(); return; }
      if (!link) {
        link = document.createElement('link');
        link.id = 'efbCustomFontLink';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
      }
      link.href = url;
    };

    const loadBuiltinFontPreview = (fontValue) => {
      const url = fontCssMap[fontValue];
      let link = document.getElementById('efbBuiltinFontLink');
      if (!url) { if (link) link.remove(); return; }
      if (!link) {
        link = document.createElement('link');
        link.id = 'efbBuiltinFontLink';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
      }
      if (link.href !== url) link.href = url;
    };

    const syncCustomFont = () => {
      const name = cfNameInput?.value?.trim() || '';
      const url = cfUrlInput?.value?.trim() || '';
      const hiddenCF = document.getElementById('respCustomFont_emsFormBuilder');
      const hiddenFF = document.getElementById('respFontFamily_emsFormBuilder');
      if (name && url) {
        if (hiddenCF) hiddenCF.value = JSON.stringify({ name: name, url: url });
        if (hiddenFF) hiddenFF.value = "'" + name + "', sans-serif";
        loadCustomFontPreview(url);
      } else {
        if (hiddenCF) hiddenCF.value = '';
        if (hiddenFF) hiddenFF.value = '__custom__';
        loadCustomFontPreview('');
      }
      refreshPreview();
    };

    if (ffSelect) ffSelect.addEventListener('change', () => {
      const isCustom = ffSelect.value === '__custom__';
      if (customArea) customArea.style.display = isCustom ? 'block' : 'none';
      if (isCustom) {
        loadBuiltinFontPreview('');
        syncCustomFont();
      } else {
        const hiddenCF = document.getElementById('respCustomFont_emsFormBuilder');
        if (hiddenCF) hiddenCF.value = '';
        loadCustomFontPreview('');
        loadBuiltinFontPreview(ffSelect.value);
        const hidden = document.getElementById('respFontFamily_emsFormBuilder');
        if (hidden) hidden.value = ffSelect.value;
        refreshPreview();
      }
    });
    if (cfNameInput) cfNameInput.addEventListener('input', syncCustomFont);
    if (cfUrlInput) cfUrlInput.addEventListener('input', syncCustomFont);

    if (fsSelect) fsSelect.addEventListener('change', () => {
      const hidden = document.getElementById('respFontSize_emsFormBuilder');
      if (hidden) hidden.value = fsSelect.value;
      refreshPreview();
    });

    if (customArea && customArea.style.display !== 'none') {
      const initUrl = cfUrlInput?.value?.trim();
      if (initUrl) loadCustomFontPreview(initUrl);
    } else if (ffSelect && ffSelect.value !== 'inherit' && ffSelect.value !== '__custom__') {
      loadBuiltinFontPreview(ffSelect.value);
    }

    const resetBtn = document.getElementById('efbColorResetModal');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        modal.querySelectorAll('input[type="color"][data-color-key]').forEach(inp => {
          const key = inp.dataset.colorKey;
          if (defaults[key]) {
            inp.value = defaults[key];
            const hex = inp.closest('.d-flex')?.querySelector('.efb-color-hex');
            if (hex) hex.textContent = defaults[key];
            const hidden = document.getElementById(`${key}_emsFormBuilder`);
            if (hidden) hidden.value = defaults[key];
          }
        });
        if (ffSelect) { ffSelect.value = defaults.respFontFamily; document.getElementById('respFontFamily_emsFormBuilder').value = defaults.respFontFamily; }
        if (fsSelect) { fsSelect.value = defaults.respFontSize; document.getElementById('respFontSize_emsFormBuilder').value = defaults.respFontSize; }
        if (customArea) customArea.style.display = 'none';
        if (cfNameInput) cfNameInput.value = '';
        if (cfUrlInput) cfUrlInput.value = '';
        const hiddenCF = document.getElementById('respCustomFont_emsFormBuilder');
        if (hiddenCF) hiddenCF.value = '';
        loadCustomFontPreview('');
        loadBuiltinFontPreview('');
        refreshPreview();
      });
    }

    refreshPreview();
  }, 80);
}

let idOfListsEfb = [];
function fun_switch_saveSetting(i, id) {
  if (i == true) {
    idOfListsEfb.push(id);
    document.getElementById("save-stng-efb").classList.contains("disabled") == false ? document.getElementById("save-stng-efb").classList.add("disabled") : "";
    alert_message_efb(`Forbidden characters: " \' \\ `, "", 5000, "danger");
  } else {
    const indx = idOfListsEfb.findIndex(x => x == id);
    if (indx != -1) idOfListsEfb.splice(indx, 1);
    idOfListsEfb.length == 0 && document.getElementById("save-stng-efb").classList.contains("disabled") == true ? document.getElementById("save-stng-efb").classList.remove("disabled") : "";
  }
}

function fun_set_setting_emsFormBuilder(state_auto = 0) {
  if(state_auto==0){
  let btn = document.getElementById('save-stng-efb');
  btn.classList.add('disabled');

  const nnrhtml = btn.innerHTML;
  btn.innerHTML = `<i class="efb  bi-hourglass-split"></i>`
  }

  const returnError=(val)=>{
    if(state_auto==1){return}
    const m =efb_var.text.msgchckvt_.replace('%s', val );

    noti_message_efb(m, 'danger' , `content-efb` );
    window.scrollTo({
      top: document.body.scrollHeight,
      behavior: 'smooth'
    });
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 20000);
  }
  const f = (id) => {
     const u = (url)=>{
      url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
      url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
      url = url.replace(/([/])+/g, '@efb@');
      return url;
     }
    const el = document.getElementById(id)

    if(el.hasAttribute('value') && el.id!="emailTemp_emsFirmBuilder"){

      el.value = sanitize_text_efb(el.value);}

    let r = "NotFoundEl"
    if (el.type == "text" || el.type == "email" || el.type == "textarea" || el.type == "hidden" || el.type == "color" || el.type == "tel") {
      if (id == "emailTemp_emsFirmBuilder") {
        let v = el.value.replace(/(\r\n|\r|\n|\t)+/g, '');
        v = u(v);
        v = v.replace(/(["])+/g, `'`);
        return v;
      }
      return el.value;
    } else if (el.type == "checkbox") {

      return el.checked;
    }else if (el.type == "button"){
      return el.classList.contains('active')
    }else if (el.tagName === "SELECT" || el.type == "select-one") {
      return el.value;
    }
    return "NotFoundEl"
  }
  const v = (id) => {

    let el = document.getElementById(id);
    if(el.hasAttribute('value') && el.id!="emailTemp_emsFirmBuilder"){
      if(el.type!='email'){
        el.value = sanitize_text_efb(el.value);

      }else{
        let value = sanitize_text_efb(el.value);
        const vs = value.split(',');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        for(let i=0;i<vs.length;i++){
          if(el.id==='activeCode_emsFormBuilder' && !regex.test(vs[i]) ){
            el.className = colorBorderChangerEfb(el.className, "border-danger")
            document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
            returnError(`<b>${el.dataset.tab}</b>`);
            value=false;
           break;
          }
        }
        el.value = sanitize_text_efb(el.value);
        if(value==false) return false;

      }
    }
    if (id == 'smtp_emsFormBuilder') { return true }
    if (el.type !== "checkbox") {

      if (el.value.length > 0 && el.value.length < 10 && id !== "activeCode_emsFormBuilder" && id !== "email_emsFormBuilder" && id !== "bootstrap_emsFormBuilder" && id !== "emailTemp_emsFirmBuilder" && id !== "pno_emsFormBuilder" && id !== "sessionDuration_emsFormBuilder") {
        document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
        el.classList.add('invalid');
        window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
        return false;
      } else if (id == "bootstrap_emsFormBuilder") {
      } else if (id == "emailTemp_emsFirmBuilder") {
        let st = 1;
        let c = ''
        let ti = '';
        if (el.value.length < 5 && el.value.length > 1) {
          st = 0;
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.pleaseEnterVaildEtemp}</p></div>`
        } else if (el.value.length > 50000) {
          st = 0;
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>`;
        } else if (el.value.length > 1 && el.value.indexOf('shortcode_message') == -1 ) {
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.addSCEmailM}</p></div>`;
          st = 0;
        }

        if (st == 0) {
          ti = efb_var.text.error
          show_modal_efb(c, ti, '', 'saveBox');
          state_modal_show_efb(1)
          return false;
        }
      } else if (id == "activeCode_emsFormBuilder") {
        if (el.value.length < 10 && el.value.length != 0) {
          el.classList.add('invalid');
          returnError(`<b>${el.dataset.tab}</b>`);
          return false;
        }
      } else if(id=="pno_emsFormBuilder" && Number(efb_var.pro)==1){

        if (  el.value.length < 5 && el.value.length == 0) {

          if(el.value.length==0){ el.value=""; return true;}
          el.classList.add('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue;
          returnError(`<b>${el.dataset.tab}</b>`);
          return false;
        }else{
          let phoneNo=el.value;
          let phoneNoArr=phoneNo.split(',');
          let phoneNoArrLen=phoneNoArr.length;
          for(let i=0;i<phoneNoArrLen;i++){
            let cleanPhone = phoneNoArr[i].replace(/[\s\(\)\-]/g, '');
            if( !cleanPhone.match(/^\+\d{8,14}$/)){
              returnError(`<b>${el.dataset.tab}</b>`);
              el.classList.add('invalid');
              const msg = efb_var.text.pleaseEnterVaildValue +`(${phoneNoArr[i]})`
              document.getElementById(`${el.id}-message`).innerHTML =msg ;
              return false;
            }
          }
          document.getElementById(`${el.id}-message`).innerHTML = '';

        }

      } else if(id=="sessionDuration_emsFormBuilder"){
        if (el.value === "" || el.selectedIndex === 0) {
          el.classList.add('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue;
          returnError(`<b>${el.dataset.tab}</b>`);
          return false;
        }
        const durationValue = parseInt(el.value);
        if (isNaN(durationValue) || durationValue < 1 || durationValue > 7) {
          el.classList.add('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue;
          returnError(`<b>${el.dataset.tab}</b>`);
          return false;
        }

        if (el.classList.contains("invalid") == true) {
          el.classList.remove('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = '';
        }

      } else {

        if (el.classList.contains("invalid") == true) {
          el.classList.remove('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = '';
        }
        if (el.type == "email" && el.value.length > 0) {

          const r = valid_email_emsFormBuilder(el);
          if (r == false) {
            returnError(`<b>${el.dataset.tab}</b>`);
            el.classList.add('invalid');
            return false;
          }
        }
      }
    } else {
      if (el.id == "bootstrap_emsFormBuilder") {
      }
    }
    return true;
  }
  const ids = ['paypalSKey_emsFormBuilder', 'paypalPKey_emsFormBuilder', 'stripeSKey_emsFormBuilder', 'stripePKey_emsFormBuilder', 'smtp_emsFormBuilder', 'apikey_map_emsFormBuilder', 'sitekey_emsFormBuilder', 'secretkey_emsFormBuilder', 'email_emsFormBuilder', 'activeCode_emsFormBuilder', 'emailTemp_emsFirmBuilder', 'pno_emsFormBuilder','femail_emsFormBuilder','osLocationPicker_emsFormBuilder', 'sessionDuration_emsFormBuilder'];
  let state = true

  for (let id of ids) {
    if (v(id) === false) {
      state = false;
      fun_State_btn_set_setting_emsFormBuilder(true);
      const m = document.getElementById(`${id}-message`).innerHTML;
      break;
    }
  }
  if (state == true) {
    const activeCode = f('activeCode_emsFormBuilder');
    const sitekey = f(`sitekey_emsFormBuilder`);
    const secretkey = f(`secretkey_emsFormBuilder`);
    const stripeSKey = f(`stripeSKey_emsFormBuilder`);
    const stripePKey = f(`stripePKey_emsFormBuilder`);
    const paypalSKey = f(`paypalSKey_emsFormBuilder`);
    const paypalPKey = f(`paypalPKey_emsFormBuilder`);
    const email = f(`email_emsFormBuilder`);
    let femail = f(`femail_emsFormBuilder`);
    if(femail.length<6){ femail = 'no-reply@'+window.location.hostname;}
    const apiKeyMap = f(`apikey_map_emsFormBuilder`)
    const osLocationPicker = f('osLocationPicker_emsFormBuilder');
    const scaptcha = f('scaptcha_emsFormBuilder');
    const shieldSilentCaptcha = f('shieldSilentCaptcha_emsFormBuilder');

    const activeDlBtn = f('activeDlBtn_emsFormBuilder');
    const showUpfile = f('showUpfile_emsFormBuilder');
    const adminSN  = f('adminSN_emsFormBuilder');
    const showIp=false;
    const devMode_efb = f('devMode_emsFormBuilder');

    const sessionDurationEl = document.getElementById('sessionDuration_emsFormBuilder');
    if (!v('sessionDuration_emsFormBuilder')) return false;
    const sessionDuration = f('sessionDuration_emsFormBuilder');

    const trackCodeStyle = f('trackCodeStyle_emsFormBuilder');

    smtp = f('hostSupportSmtp_emsFormBuilder');
    act_local_efb =f('act_local_efb')
    let emailTemp = f('emailTemp_emsFirmBuilder');
     emailTemp = emailTemp.replace(/([/\r\n|\r|\n/])+/g, ' ');
    const emailBtnBgColor = f('emailBtnBgColor_emsFormBuilder') || '#202a8d';
    const emailBtnTextColor = f('emailBtnTextColor_emsFormBuilder') || '#ffffff';
    let text = act_local_efb==true ? efb_var.text :'';
    if(typeof text != 'object' && text!=''){
        noti_message_efb('Localization not found. It seems there may be a conflict with a plugin and Easy Form Builder. Please reach out to the Easy Form Builder support team for assistance', 'danger', 'content-efb');
      return false;
    }else if(typeof text == 'object'){
      for(let i in text){
        text[i] = text[i].replace(/(["])+/g, `̎ᐥ`);
        text[i] = text[i].replace(/(['])+/g, `ᐠ`);
        text[i]= sanitize_text_efb(text[i]);
      }
   }

    const payToken = f('payToken_emsFormBuilder');
    let temp = f('pno_emsFormBuilder');
    const phoneNumbers = temp.length<5 ? 'null' : temp;
    let AdnSPF=AdnOF=AdnPPF=AdnATC=AdnSS=AdnCPF=AdnESZ=AdnSE=
    AdnWHS=AdnPAP=AdnWSP=AdnSMF=AdnPLF=AdnMSF=AdnBEF=AdnPDP=AdnADP=AdnATF=AdnTLG=0,
    AdnGoS=0
    if(valueJson_ws_setting.hasOwnProperty('AdnSPF')){
      AdnSPF=valueJson_ws_setting.AdnSPF;
      AdnOF=valueJson_ws_setting.AdnOF;
      AdnPPF=valueJson_ws_setting.AdnPPF;
      AdnSS=valueJson_ws_setting.AdnSS;
      AdnESZ=valueJson_ws_setting.AdnESZ;
      AdnSE=valueJson_ws_setting.AdnSE;
      AdnPAP=valueJson_ws_setting.AdnPAP;
      AdnPDP=valueJson_ws_setting.hasOwnProperty('AdnPDP') ?valueJson_ws_setting.AdnPDP :0;
      AdnADP=valueJson_ws_setting.hasOwnProperty('AdnADP') ? valueJson_ws_setting.AdnADP :0;
      AdnATF=valueJson_ws_setting.hasOwnProperty('AdnATF') ? valueJson_ws_setting.AdnATF :0;
      AdnTLG=valueJson_ws_setting.hasOwnProperty('AdnTLG') ? valueJson_ws_setting.AdnTLG :0;
      AdnGoS=valueJson_ws_setting.hasOwnProperty('AdnGoS') ? valueJson_ws_setting.AdnGoS :0;
    }
    const email_key_efb = valueJson_ws_setting.email_key ??  Math.random().toString(36).substr(2, 10);

    const respPrimary = f('respPrimary_emsFormBuilder');
    const respPrimaryDark = f('respPrimaryDark_emsFormBuilder');
    const respAccent = f('respAccent_emsFormBuilder');
    const respText = f('respText_emsFormBuilder');
    const respTextMuted = f('respTextMuted_emsFormBuilder');
    const respBgCard = f('respBgCard_emsFormBuilder');
    const respBgMeta = f('respBgMeta_emsFormBuilder');
    const respBgTrack = f('respBgTrack_emsFormBuilder');
    const respBgResp = f('respBgResp_emsFormBuilder');
    const respBgEditor = f('respBgEditor_emsFormBuilder');
    const respEditorText = f('respEditorText_emsFormBuilder');
    const respEditorPh = f('respEditorPh_emsFormBuilder');
    const respBtnText = f('respBtnText_emsFormBuilder');
    const respFontFamily = f('respFontFamily_emsFormBuilder');
    const respFontSize = f('respFontSize_emsFormBuilder');
    const respCustomFont = f('respCustomFont_emsFormBuilder');
    const package_type = sessionStorage.getItem('efb_license_selected') ?? valueJson_ws_setting.package_type ?? (valueJson_ws_setting.activeCode == '' ? '2' : '1');
    let setting = { ...(valueJson_ws_setting || {}) };
    const patch = {
          activeCode: activeCode,
          siteKey: sitekey,
          secretKey: secretkey,
          emailSupporter: email,

          apiKeyMap: `${apiKeyMap}`,
          smtp: smtp,
          text: text,
          bootstrap: bootstrap,
          emailTemp: emailTemp,
          emailBtnBgColor: emailBtnBgColor,
          emailBtnTextColor: emailBtnTextColor,

          paypalPKey: paypalPKey,
          paypalSKey: paypalSKey,

          stripePKey: stripePKey,
          stripeSKey: stripeSKey,

          payToken: payToken,
          act_local_efb: act_local_efb,

          scaptcha: scaptcha,
          shield_silent_captcha: shieldSilentCaptcha,

          activeDlBtn: activeDlBtn,
          dsupfile: showUpfile,

          sms_config: sms_config_efb,

          AdnSPF: AdnSPF,
          AdnOF: AdnOF,
          AdnPPF: AdnPPF,
          AdnSS: AdnSS,
          AdnESZ: AdnESZ,
          AdnSE: AdnSE,
          AdnPAP: AdnPAP,
          AdnPDP: AdnPDP,
          AdnADP: AdnADP,
          AdnATF: AdnATF,
          AdnTLG: AdnTLG,
          AdnGoS: AdnGoS,

          phnNo: phoneNumbers,
          femail: femail,
          email_key: email_key_efb,

          showIp: showIp,
          adminSN: adminSN,

          osLocationPicker: osLocationPicker,
          sessionDuration: sessionDuration,
          trackCodeStyle: trackCodeStyle,

          respPrimary: respPrimary,
          respPrimaryDark: respPrimaryDark,
          respAccent: respAccent,
          respText: respText,
          respTextMuted: respTextMuted,
          respBgCard: respBgCard,
          respBgMeta: respBgMeta,
          respBgTrack: respBgTrack,
          respBgResp: respBgResp,
          respBgEditor: respBgEditor,
          respEditorText: respEditorText,
          respEditorPh: respEditorPh,
          respBtnText: respBtnText,
          respFontFamily: respFontFamily,
          respFontSize: respFontSize,
          respCustomFont: respCustomFont,

          package_type:package_type,
          devMode: devMode_efb
        };

        for (const [key, val] of Object.entries(patch)) {
          if (val !== undefined) {
            setting[key] = val;
          }
        }
    fun_send_setting_emsFormBuilder( setting , state_auto);
  }

}

function efb_build_track_options(sel) {
  const t = efb_var.text;
  const dp = (a,b) => t.trackCodeDatePlus.replace('%1$s',a).replace('%2$s',b);
  const tp = (a,b,c) => t.trackCodeTriple.replace('%1$s',a).replace('%2$s',b).replace('%3$s',c);
  const nl = t.nlan;  // National language / Local
  const el = t.elan;  // English language
  const lt = t.tLetters; // Letters
  const nm = t.number;  // Number
  const opts = [
    ['date_num',         dp(t.ddate, nm)],
    ['date_local_mix',   dp(t.ddate, tp(nl, lt, nm))],
    ['date_local_alpha', dp(t.ddate, nl + ' ' + lt)],
    ['date_en_mix',      dp(t.ddate, tp(el, lt, nm))],
    ['date_local_num',   dp(t.ddate, nl + ' ' + nm)],
    ['unique_num',       t.uniqueNum],
    ['local_mix',        tp(nl, lt, nm)],
  ];
  return opts.map(([v,l]) => `<option value="${v}" ${sel===v?'selected':''}>${l}</option>`).join('');
}

function efb_generate_track_preview(style) {
  const d = new Date();
  const ymd = String(d.getFullYear()).slice(-2) + String(d.getMonth()+1).padStart(2,'0') + String(d.getDate()).padStart(2,'0');
  const pick = (chars, n) => { const a = [...chars]; for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];} return a.slice(0,n).join(''); };
  const lc = efb_var.text.trackCodeLocalChars || '';
  const ld = efb_var.text.trackCodeLocalDigits || '';
  const toLD = (s) => ld ? s.replace(/[0-9]/g, c => ld[parseInt(c)] || c) : s;
  const en = '0123456789ASDFGHJKLQWERTYUIOPZXCVBNM';
  switch(style) {
    case 'date_num': return ymd + '-' + String(10000 + Math.floor(Math.random()*90000));
    case 'date_local_mix': return (lc ? toLD(ymd) : ymd) + pick(lc ? (lc + (ld || '0123456789')) : en, 5);
    case 'date_local_alpha': return ymd + pick(lc || 'ASDFGHJKLQWERTYUIOPZXCVBNM', 5);
    case 'date_local_num': { const r = String(10000+Math.floor(Math.random()*90000)); return (ld ? toLD(ymd)+'-'+toLD(r) : ymd+'-'+r); }
    case 'unique_num': return String(parseInt(ymd)*100000 + 10000+Math.floor(Math.random()*90000));
    case 'local_mix': return pick(lc ? (lc + (ld || '0123456789')) : en, 11);
    case 'date_en_mix':
    default: return ymd + pick(en, 5);
  }
}

function efb_preview_track_code(style) {
  const el = document.getElementById('trackCodePreview_efb');
  if(el) el.textContent = efb_generate_track_preview(style);
}

function fun_State_btn_set_setting_emsFormBuilder($state) {

   let el =  document.getElementById('save-stng-efb');
    if($state==true){
      el.classList.remove('disabled');
      el.innerHTML = `<i class="efb  bi-save mx-1"></i>${efb_var.text.save}`;
    }else{
     el.classList.add('disabled');
      el.innerHTML = `<i class="efb  bi-hourglass-split"></i>`;
    }
}

function fun_state_loading_message_emsFormBuilder(state) {
  if (state !== 0) {
    if (document.getElementById('loading_message_emsFormBuilder').classList.contains('invisible') == true) {
      document.getElementById('loading_message_emsFormBuilder').classList.remove('invisible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('visible');
    } else {
      document.getElementById('loading_message_emsFormBuilder').classList.remove('visible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('invisible');
    }
  }
}

function fun_send_setting_emsFormBuilder(data , state_auto = 0) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  data = JSON.stringify(data);
  jQuery(function ($) {
    data = {
      action: "set_settings_Emsfb",
      type: "POST",
      nonce: _efb_core_nonce_,
      contentType: "application/x-www-form-urlencoded;charset=utf-8",
      message: data
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      let m = ''
      let t = efb_var.text.tshbc.replace('%s', efb_var.text.save)
      let lrt = "info"
      let time = 5
      if (res.success == true) {
        valueJson_ws_setting = data.message;

        if (res.data.success != true) {
          t = efb_var.text.error
          m = res.data.m;
          lrt = "danger";
          time = 15;
        }
      } else {
        t = '';
        m = res;
        lrt = "danger";
        time = 15;
      }
      if(state_auto==1){return}
      if(res.data.success == true){
        history.replaceState("panel",null,'?page=Emsfb&state=reload-setting&save=ok');
        window.location=location.search;

      }else{
        document.getElementById('save-stng-efb').innerHTML = `<i class="efb  bi-save mx-1"></i>${efb_var.text.save}`;
        document.getElementById('save-stng-efb').classList.remove('disabled');
        alert_message_efb(t, m, time, lrt);
      }

    })
  });
}

function fun_find_track_emsFormBuilder() {

  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  const el = document.getElementById("track_code_emsFormBuilder").value;
  localStorage.setItem('search_efb',`${el}`)
  history.pushState("search",null,'?page=Emsfb&state=search');
  if (el.length < 1) {
    alert_message_efb(efb_var.text.error, efb_var.text.pleaseEnterVaildValue, 7, 'warning');
    return;
  } else {
    search_comprehensive_efb(el)
  }
}

search_comprehensive_efb =(el)=>{
  document.getElementById('track_code_emsFormBuilder').disabled = true;
    document.getElementById('track_code_btn_emsFormBuilder').disabled = true;
    const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
    document.getElementById('track_code_btn_emsFormBuilder').innerHTML = `<i class="efb bi-hourglass-split"></i>`;

  jQuery(function ($) {
    data = {
      action: "get_track_id_Emsfb",
      nonce: _efb_core_nonce_,
      value: el,
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {

        valueJson_ws_messages = res.data.ajax_value;
        efb_var.nonce_msg = res.data.nonce_msg

        efb_var.msg_id = res.data.id

        valueJson_ws_messages = enhanceSearchResults_efb(valueJson_ws_messages, el);

        document.getElementById("more_emsFormBuilder").style.display = "none";
        fun_ws_show_list_messages(valueJson_ws_messages);

        const resultCount = valueJson_ws_messages.length;
        const searchTerm = el;
        const resultText = resultCount === 1 ? (efb_var.text.result || 'result') : (efb_var.text.results || 'results');

        let searchInfo;
        if (efb_var.text.foundResultsText) {
          searchInfo = efb_var.text.foundResultsText
            .replace('%1$s', resultCount)
            .replace('%2$s', resultText) + `: "${searchTerm}"`;
        } else if (efb_var.text.foundResultsFor) {
          searchInfo = efb_var.text.foundResultsFor.replace('%s', resultCount).replace('%s', resultText).replace('%s', searchTerm);
        } else {
          searchInfo = `Found ${resultCount} ${resultText} for: "${searchTerm}"`;
        }

        alert_message_efb(
          efb_var.text.searchResults || 'Search Results',
          searchInfo ,
          8,
          'success'
        );

        document.getElementById('track_code_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue;

      } else {
        const noResultsMsg = efb_var.text.noResultsFound
          ? `${efb_var.text.noResultsFound} "${el}"`
          : `No results found for: "${el}"`;
        alert_message_efb(efb_var.text.error, res.data.m + ` - ${noResultsMsg}`, 6, 'warning');
        document.getElementById('track_code_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue

      }
    })
  });
}

function clear_garbeg_emsFormBuilder() {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }

  document.getElementById('clrUnfileEfb').classList.add('disabled')
  document.getElementById('clrUnfileEfbText').innerHTML = efb_var.text.pleaseWaiting;

  jQuery(function ($) {
    data = {
      action: "clear_garbeg_Emsfb",
      nonce: _efb_core_nonce_
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {
        alert_message_efb(efb_var.text.done, res.data.m, 4.7, 'info');
        document.getElementById('clrUnfileEfbText').innerHTML = efb_var.text.clearUnnecessaryFiles;
      } else {
        alert_message_efb(efb_var.text.error, res.data.m, 4.7, 'danger');

      }
    })
  });
  document.getElementById('clrUnfileEfb').classList.remove('disabled')
}

function fun_export_rows_for_Subscribe_emsFormBuilder(value) {
  let head = {};
  let heads = [];
  let ids = [];
  let count = -1;
  let rows = Array.from(Array(value.length + 1), () => Array(100).fill('null@EFB'));

  rows[0][0] = 'id';
  rows[0][1] = efb_var.text.createDate;

  let i_count = -1;
  add_multi = (c, content, value_col_index, v) => {
    if (form_type_emsFormBuilder == "survey") {
      if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
        rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
      } else {
        const r = rows.length
        const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
        rows = rows.concat(row);
        rows[parseInt(r)][parseInt(value_col_index)] = content[c].value;
        rows[parseInt(r)][0] = v;
      }
    }else if(content[c].type =="chlCheckBox"){
        rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB" ? rows[parseInt(i_count)][parseInt(value_col_index)] = `${content[c].value} : ${content[c].qty}` : rows[parseInt(i_count)][parseInt(value_col_index)] += "|| " + `${content[c].value} : ${content[c].qty}`
    }else {
      rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB" ? rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value : rows[parseInt(i_count)][parseInt(value_col_index)] += "|| " + content[c].value
    }
  }
  for (let v of value) {

    const content = JSON.parse(replaceContentMessageEfb(v.content))
    count += 1;
    i_count += i_count == -1 ? 2 : 1;
    rows[i_count][1] = v.date;

    for (let c in content) {
      let value_col_index;
      if(content[c]!=null && content[c].hasOwnProperty('id_') && content[c].id_.length>1){

        if (content[c].type != "checkbox" && content[c].type != 'multiselect'
          && content[c].type != "payCheckbox" && content[c].type != 'payMultiselect' && content[c].type != 'chlCheckBox'
          ) {

          if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;

          value_col_index = rows[0].findIndex(x => x == content[c].name);

          if (value_col_index == -1) {

            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = content[c].name;
            if (content[c].type == 'payment') rows[0][parseInt(value_col_index) + 1] = "TID";
          }

          rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          if (content[c].type == 'payment') {
            const vx = rows[0].findIndex(x => x == "TID");
            rows[parseInt(i_count)][parseInt(vx)] = content[c].paymentIntent;
          }else if(content[c].value == '@file@' || content[c].type == 'file' || content[c].type == 'media' || content[c].type == 'zip'  || content[c].type == 'image' || content[c].type == 'document' || content[c].type == 'allformat'){
            rows[parseInt(i_count)][parseInt(value_col_index)] =content[c].url;
          }else if (content[c].type == "maps"){
            let val =''
            content[c].value.forEach(r => {
              const address = r.address.replaceAll(',' ,' -');
              val=='' ? val = `${efb_var.text.latitude}:${r.lat}; ${efb_var.text.longitude}:${r.lng}; ${efb_var.text.address}:${address}`  : val +=`| ${efb_var.text.latitude}:${r.lat}; ${efb_var.text.longitude}:${r.lng}; ${efb_var.text.address}:${address}`
            });
            rows[parseInt(i_count)][parseInt(value_col_index)] = val;
          }
        } else if (content[c].type == 'multiselect' || content[c].type == 'payMultiselect') {
          if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;
          value_col_index = rows[0].findIndex(x => x == content[c].name);
          if (value_col_index == -1) {
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = content[c].name;
          }
          if (content[c].value.search(/@efb!+/g) != -1) {
            if (form_type_emsFormBuilder == "survey") {
              const nOb = content[c].value.split("@efb!")
              nOb.forEach(n => {
                if (n != "") {

                  if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
                    rows[parseInt(i_count)][parseInt(value_col_index)] = n;
                  } else {
                    const r = rows.length
                    const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
                    rows = rows.concat(row);
                    rows[parseInt(r)][parseInt(value_col_index)] = n;
                    rows[parseInt(r)][0] = v.msg_id;
                  }
                }
              });
            } else {
              rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value.replaceAll('@efb!', " || ")
            }
          } else {
            rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value.replaceAll('@efb!', "");;
          }
        } else {
          if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;

          const name = content[c].name;
          value_col_index = rows[0].findIndex(x => x == name);
          if (value_col_index != -1) {
            add_multi(c, content, value_col_index, v.msg_id)
          } else {
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = name;
            add_multi(c, content, value_col_index, v.msg_id)

          }

        }

      }

    }

  }
  const col_index = rows[0].findIndex(x => x == 'null@EFB');

  const exp = Array.from(Array(rows.length), () => Array(col_index).fill(efb_var.text.noComment));

  for (let e in exp) {
    for (let i = 0; i < col_index; i++) {
      if (rows[e][i] != "null@EFB") exp[e][i] = rows[e][i];
    }
  }

  localStorage.setItem('rows_ws_p', JSON.stringify(exp));
}

function exportCSVFile_emsFormBuilder(items, fileTitle) {

  items.forEach(item => { for (let i in item) { if (item[i] == "notCount@EFB") item[i] = ""; } });
  var jsonObject = JSON.stringify(items);
  var csv = this.convertToCSV_emsFormBuilder(jsonObject);
  var exportedFilenmae = fileTitle + '.csv' || 'export.csv';
  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  if (navigator.msSaveBlob) {
    navigator.msSaveBlob(blob, exportedFilenmae);
  } else {
    var link = document.createElement("a");
    if (link.download !== undefined) {
      var url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute("download", exportedFilenmae);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }
}

function convertToCSV_emsFormBuilder(objArray) {
  var array = typeof objArray != 'object' ? JSON.parse(objArray) : objArray;
  var str = '';
  const separators = [',',';', '|', '^', '~', '=', ':', '#', '\t'];
  let foundSeparators = {};
  let separator = ',';
  for (let i = 0; i < array.length; i++) {
    let line = '';
    for (let k = 0; k < array[i].length; k++) {
      for (let s = 0; s < separators.length; s++) {
        let val = array[i][k];
        if (typeof val === 'object' && val !== null) {
          val = JSON.stringify(val);
        }
        if (typeof val === 'string' && val.includes(separators[s])) {
          foundSeparators[separators[s]] = true;
        }
      }
      let val = array[i][k];
      if (typeof val === 'object' && val !== null) {
        val = JSON.stringify(val);
      }
      if (line !== '') line += '@emsfb@';
      line += val;
    }
    str += line + '\r\n';
  }

  for (let s = 0; s < separators.length; s++) {
    if (!foundSeparators[separators[s]]) {
      separator = separators[s];
      break;
    }
  }

  str = str.replace(/@emsfb@/g, separator);
  str = str.slice(0, -2);

  return str;
}

function generat_csv_emsFormBuilder() {
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  const filename = `EasyFormBuilder-${form_type_emsFormBuilder}-export-${Math.random().toString(36).substr(2, 3)}`
  exportCSVFile_emsFormBuilder(exp, filename);
}

function convert_to_dataset_emsFormBuilder() {
  const head = JSON.parse(localStorage.getItem("head_ws_p"));
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  let rows = exp;
  let countEnrty = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let entry = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let titleTable = [];
  for (let col in rows) {
    if (col != 0) {
      for (let c=0 ; c<rows[col].length ; c++) {
        if (rows[col][c] != 'null@EFB' && rows[col][c] != 'notCount@EFB') {
          const indx = entry[c].findIndex(x => x == rows[col][c]);

          if (indx != -1) {
            countEnrty[c][indx] += 1;
          } else {
            countEnrty[c].push(1)
            entry[c].push(rows[col][c]);
          }
        }

      }

    } else {

      for (let v of rows[col]) {

        titleTable.push(v);
      }
    }
  }

  emsFormBuilder_chart(titleTable, entry, countEnrty);

}

function emsFormBuilder_chart(titles, colname, colvalue) {
  let publicidofchart
  let chartview = "<!-- charts -->";
  let chartId = [];
  let publicRows = [];
  let options = {};
  let body = `
  <div class="efb  ${Number(efb_var.rtl) == 1 ? 'rtl-text' : ''}" id="overpage">
    <div id="overpage-chart">
        ${efbLoadingCard('',4)}
    </div>
  </div>`;

  show_modal_efb(body, efb_var.text.chart, "bi-pie-chart-fill", 'chart')
  state_modal_show_efb(1)

  setTimeout(() => {

    for (let t in titles) {
      chartId.push(Math.random().toString(36).substring(8));
      if (t != 0) {
        chartview += ` </br> <div id="${chartId[t]}"/ class="efb ${t == 0 ? `hidden` : ``}">
          <h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
          <h3 class="efb text-white  text-center">${efb_var.text.pleaseWaiting}<h3> </div>`
      } else { chartview += ` </br> <div id="${chartId[t]}"/ class="efb ${t == 0 ? `hidden` : ``}"></div>` }
    }

    document.getElementById('overpage-chart').innerHTML = chartview

    let drawPieChartArr = [];
    let rowsOfCharts = [];
    let opetionsOfCharts = [];
    for (let t in titles) {

      opetionsOfCharts[t] = {
        'title': titles[t],
        'height': 300,
        colors: colors_efb
      };
      const countCol = colname[t].length;
      const rows = Array.from(Array(countCol), () => Array(2).fill(0));
      const valj_efb_ = JSON.parse(sessionStorage.getItem("valj_efb"));
      for (let r in colname[t]) {

        rows[r][0] = colname[t][r];
        rows[r][1] = colvalue[t][r];
      }

      rowsOfCharts[t] = rows;

      google.charts.load('current', { packages: ['corechart'] });
      publicidofchart = chartId[t];

      drawPieChartArr[t] = () => {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Element');
        data.addColumn('number', 'integer');

        data.addRows(rowsOfCharts[t]);

        var chart = new google.visualization.PieChart(document.getElementById(chartId[t]));
        chart.draw(data, opetionsOfCharts[t]);
      }

      try {

        google.charts.setOnLoadCallback(drawPieChartArr[t]);
      } catch (error) {

      }

    }

  }, 1000);

}

function googleCloudOffer() { return `<p>${efb_var.text.offerGoogleCloud} <a href="https://gcpsignup.page.link/8cwn" target="blank">${efb_var.text.getOfferTextlink}</a> </p> ` }

function clickToCheckEmailServer() {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  document.getElementById('clickToCheckEmailServer').classList.add('disabled')
  const nnrhtml = document.getElementById('clickToCheckEmailServer').innerHTML;
  document.getElementById('clickToCheckEmailServer').innerHTML = `<i class="efb bi bi-hourglass-split"></i>`;
  const email = document.getElementById('email_emsFormBuilder').value;
  if (email.length > 5) {
    jQuery(function ($) {
      data = {
        action: "check_email_server_efb",
        nonce: _efb_core_nonce_,
        value: 'testMailServer',
        email: email
      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {
        const el= document.getElementById("hostSupportSmtp_emsFormBuilder");
        if (res.data.success == true) {
          alert_message_efb(efb_var.text.done, efb_var.text.serverEmailAble, 5);
         if(el.classList.contains('active')==false) el.classList.add('active') ;
        } else {
          const label = '<b>'+efb_var.text.hostSupportSmtp+'</b>';
          const massage = efb_var.text.PleaseMTPNotWork.replace('%s', label);
          alert_message_efb(efb_var.text.alert, massage, 60, 'warning');
          el.classList.remove('active') ;
        }
        document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
        document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
      })
    });

  } else {
    alert_message_efb(efb_var.text.error, efb_var.text.enterAdminEmail, 10, 'warning');
    document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
    document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
  }

}

function email_template_efb(s) {

  if (s == 'p') {
    let c = document.getElementById('emailTemp_emsFirmBuilder').value;
    let ti = efb_var.text.error;
    if (c.match(/(<script+)/gi)) {
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.pleaseDoNotAddJsCode}</p></div>`;
    } else if (c.length > 2 && c.length < 2000) {
      ti = efb_var.text.preview;
      if (!c.includes('shortcode_message')) {
        c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.addSCEmailM}</p></div>`;
        ti = efb_var.text.error;
      }
      else if (efb_var.pro!="true" && efb_var.pro!=true) {

        c += funNproEmailTemp();

      }
    } else if (c.length >= 10000) {
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>`;
    } else if (c.length < 2) {
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.notFound}</p></div>`
    } else {
      ti = efb_var.text.preview;
    }
    show_modal_efb(c, ti, '', 'saveBox');
    state_modal_show_efb(1)
  } else if (s == "h") {
  } else if (s == 'r') {
    document.getElementById('emailTemp_emsFirmBuilder').value = '';
  }
}

function EmailTemp1Efb() {
  return `<html xmlns='http://www.w3.org/1999/xhtml'>
  <head>
  <meta http-equiv='content-type' content='text/html; charset=utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0;'>
   <meta name='format-detection' content='telephone=no'/>
  <style>
  body { margin: 0; padding: 0; min-width: 100%; width: 100% !important; height: 100% !important; ${Number(efb_var.rtl) == 1 ? `direction:rtl;` : ''}}
  body, table, td, div, p, a { -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%; }
  table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse !important; border-spacing: 0; }
  img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  #outlook a { padding: 0; }
  .ReadMsgBody { width: 100%; } .ExternalClass { width: 100%; }
  .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }
  @media all and (min-width: 560px) {
  .container { border-radius: 8px; -webkit-border-radius: 8px; -moz-border-radius: 8px; -khtml-border-radius: 8px; }
  }
  a, a:hover {color: #f50565!important;}
  .footer a, .footer a:hover {color: #828999;}
  </style></head>
  <body topmargin='0' rightmargin='0' bottommargin='0' leftmargin='0' marginwidth='0' marginheight='0' width='100%' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%; height: 100%; -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%; background-color: #2D3445; color: #FFFFFF;' bgcolor='#2D3445' text='#FFFFFF'>
  <table width='100%' align='center' border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%;' class='efb background'><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;' bgcolor='#2D3445'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' width='500' style='border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit; max-width: 500px;' class='efb wrapper'>
  <tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;
  padding-top: 0px;' class='efb hero'><a target='_blank' style='text-decoration: none;' href='shortcode_website_url'><img border='0' vspace='0' hspace='0' src='${efb_var.images.emailTemplate1}' alt='Please enable images to view this content' title='Email Notification' width='340' style='width: 87.5%;max-width: 340px;color: #FFFFFF; font-size: 13px; margin: 0; padding: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; border: none; display: block;'/></a></td>
  </tr><tr></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 24px; font-weight: bold; line-height: 130%;padding-top: 5px;color: #FFFFFF;font-family: sans-serif;' class='efb header'>shortcode_title
  </td></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 17px; font-weight: 400; line-height: 160%;
  padding-top: 15px; color: #FFFFFF; font-family: sans-serif;' class='efb paragraph'> shortcode_message </td></tr><tr>
  <td align='center' valign='top' style='background:#2D3445; border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%;
  padding-top: 25px; padding-bottom: 5px; border-color: #2d3445;' class='efb button'><a href='shortcode_website_url' target='_blank' style='text-decoration: none;'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' style='max-width: 240px; min-width: 120px; border-collapse: collapse; border-spacing: 0; padding: 0;'><tr><td align='center' valign='middle' style='padding: 12px 24px; margin: 0; text-decoration: none; border-collapse: collapse; border-spacing: 0; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; -khtml-border-radius: 4px;' bgcolor='#0a016e'><a target='_blank' style='text-decoration: none; color: #FFFFFF; font-family: sans-serif; font-size: 17px; font-weight: 400; line-height: 120%;' href='shortcode_website_url'>
  ${efb_var.text.clickHere}  </a></td></tr></table></a> </td>  </tr>
  <tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; padding-top: 30px;' class='efb line'><hr color='#565F73' align='center' width='100%' size='1' noshade style='margin: 0; padding: 0;' /></td></tr><tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 13px; font-weight: 400; line-height: 150%; padding-top: 10px; padding-bottom: 20px; color: #828999; font-family: sans-serif;' class='efb footer'>
  ${efb_var.text.sentBy} <a href='shortcode_website_url' target='_blank' style='text-decoration: none; color: #828999; font-family: sans-serif; font-size: 13px; font-weight: 400; line-height: 150%;'>shortcode_website_name</a>.
  </td></tr></table></td></tr></table>

  </body></html>`
}

function EmailTemp2Efb() {
  return `<html xmlns='http://www.w3.org/1999/xhtml'> <body> <style> body {margin:auto 10px;${efb_var.rtl == 1 ? `direction:rtl;` : ''}}</style><center>
<table class='efb body-wrap' style='text-align:center;width:100%;font-family:arial,sans-serif;border:12px solid rgba(126, 122, 122, 0.08);border-spacing:4px 20px;'> <tr>
          <img src='${efb_var.images.emailTemplate1}' style='width:36%;'>
</tr> <tr> <td><center> <table bgcolor='#FFFFFF' width='80%'' border='0'>  <tbody> <tr>
<td style='font-family:Roboto-Regular,Helvetica,Arial,sans-serif;font-size:13px;color:#202020;line-height:1.5'>
<h1 style='color:#575252;text-align:center;'>shortcode_title</h1>
</td></tr><tr style='text-align:center;color:#000000;font-size:14px;'><td>
            <span>shortcode_message</span>
</td> </tr><tr style='text-align:center;color:#000000;font-size:14px;height:45px;'><td> <span><b style='color:#575252;'>
            <a href='shortcode_website_url'>shortcode_website_name</a>
</span></td></tr></tbody></center></td> </tr></table></center></body>  </html>`
}

function fun_add_email_template_efb(i) {
  switch (i) {
    case 1:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp1Efb();
      break;

    case 2:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp2Efb();
      break;

  }
}

function funNproEmailTemp() {
 const ws = efb_var.language != "fa_IR" ? "https://whitestudio.team/" : 'https://easyformbuilder.ir';

  return `<table role='presentation' bgcolor='#F5F8FA' width='100%'>
  <a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="This field available in Pro version" data-original-title="This field available in Pro version"><i class="efb  bi-gem text-light"></i></a>
  <tr> <td align='left' style='padding: 30px 30px; font-size:12px; text-align:center'><a class='efb subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'><img src="https://ps.w.org/easy-form-builder/assets/icon-256x256.gif" style="margin: 5px; width:16px;height:16px" >  ${efb_var.text.easyFormBuilder}</a>
 <br> <img src="${ws}img/favicon.png" style="margin: 5px"> <a class='efb subtle-link' target='_blank' href='${ws}'>White Studio Team</a></td></tr>`
}

function act_local_efb_event(t){

  setTimeout(() => {

    t.classList.contains('active')==true ? document.getElementById('textList-efb').classList.remove('d-none'): document.getElementById('textList-efb').classList.add('d-none')
  }, 80);
}

function check_server_sms_method_efb(el){

  if(Number(efb_var.pro)!=1){
    pro_show_efb(efb_var.text.proUnlockMsg)
    el.checked = false;
    return;
  } else if(valueJson_ws_setting.AdnSS!=1){
    let m = efb_var.text.msg_adons.replace('NN',`<b>${efb_var.text.sms_noti}</b>`);
    noti_message_efb(m, 'danger' , `content-efb` );
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 20000);
    el.checked = false;
    return;
  }else if( efb_var.plugins.wpsms ==0 && el.id=="wp_sms_plugin"){
   noti_message_efb(efb_var.text.wpsms_nm, 'danger' , `content-efb` );
   window.scrollTo({
    top: document.body.scrollHeight,
    behavior: 'smooth'
  });
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 15000);

    el.checked = false;
    return;
  }

  if(el.id=="efb_sms_service"){
    sms_config_efb='efb'
  }else if(el.id=="wp_sms_plugin"){
    sms_config_efb='wpsms'
  }

}

async function fun_dup_request_server_efb(id ,type){
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }
  if(type=='form'){
    document.getElementById(id+'-dup-efb').innerHTML=svg_loading_efb('text-light');
    document.getElementById(id+'-dup-efb').disabled=true;
    $result = await fun_dup_form_server_efb(id,type);
  }
}

function fun_dup_form_server_efb(id,type){
  return new Promise(resolve => {
    jQuery(function ($) {
      data = {
        action: "dup_efb",
        nonce: _efb_core_nonce_,
        id: id,
        type: type

      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {
        if (res.data.success == true) {
          emsFormBuilder_waiting_response();
          valueJson_ws_form.push({form_id:res.data.form_id, form_name:res.data.form_name, form_create_date:res.data.date,form_type:res.data.form_type});
          alert_message_efb(efb_var.text.done, res.data.m, 4, 'success');
          fun_emsFormBuilder_render_view(valueJson_ws_form.length)

          resolve(true);
        } else {
          alert_message_efb(efb_var.text.error, res.data.m, 4, 'danger');
          document.getElementById(id+'-dup-efb').innerHTML='<i class="efb  bi-clipboard-plus"></i>';
          document.getElementById(id+'-dup-efb').disabled=false;
          resolve(false);
        }
      })
    });
  });
}

function fun_select_rows_table(el){
  if(el.classList.contains('allmsg')){
    let els = document.querySelectorAll(".onemsg")
   let state =true;
    if(el.checked==true){
      for (let i = 0; i < els.length; i++) {
        els[i].checked=true;
      }
    }else{
      state = false;
      for (let i = 0; i < els.length; i++) {
        els[i].checked=false;
      }

    }
    for (let i = 0; i < valueJson_ws_messages.length; i++) {
      valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = state : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:state}
    }
  }else if (el.classList.contains('onemsg')){
    const msg_id = el.dataset.id;
    const i = valueJson_ws_messages.findIndex(x => x.msg_id == msg_id);
    if(el.checked){
    valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = true : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:true}
    }else{
      valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = false : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:false}
    }
  }
}

function event_selected_row_emsFormBuilder(state){
  let list_selected = valueJson_ws_messages.filter(x => x.checked == true).map(x => JSON.parse(JSON.stringify(x)));
  for(let i in list_selected){
    if(list_selected[i].hasOwnProperty('content')){
      list_selected[i].content='';
    }
  }
  if(list_selected.length==0){
    alert_message_efb(efb_var.text.error, efb_var.text.nsrf, 8, 'warning');
    return;
  }
  if(state=='delete'){
    emsFormBuilder_delete('','message',list_selected);
  }else{
    emsFormBuilder_read('msg',list_selected);
    for (const v of list_selected) {
      const foundIndex = Object.keys(valueJson_ws_messages).length > 0 ? valueJson_ws_messages.findIndex(x => x.msg_id == v.msg_id) : -1
      if (foundIndex != -1) valueJson_ws_messages[foundIndex].read_ = "1";
    }
    setTimeout(() => {
      fun_ws_show_list_messages(valueJson_ws_messages)
    }, 1000);
  }
}

function emsFormBuilder_read(state,val){
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
    return;
  }

  jQuery(function ($) {
    data = {
      action: "read_list_Emsfb",
      type: "POST",
      val: JSON.stringify(val),
      state: state,
      nonce: _efb_core_nonce_,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.data.success == true) {
        setTimeout(() => {
          alert_message_efb(efb_var.text.done, '', 3, 'info');

        }, 3)
      } else {

        setTimeout(() => {
          alert_message_efb(efb_var.text.error, res.data.m, 3, 'danger')
        }, 3)
      }
    })
  });
}

function efb_test_onchange(){
}
