
function deepFreeze_efb(obj) {
  if (typeof obj !== "object" || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
      if (typeof obj[key] === "object" && obj[key] !== null) {
          deepFreeze_efb(obj[key]);
      }
  });
  return Object.freeze(obj);
}
let mobile_view_efb = 0;
let activeEl_efb = 0;
let amount_el_efb = 1;
let step_el_efb = 0;
let steps_index_efb = []
let valj_efb = [];
let maps_efb = {};
let state_efb = 'view';
let mousePostion_efb = { x: 0, y: 0 };
let draw_mouse_efb = false;
let c2d_contex_efb
let lastMousePostion_efb = mousePostion_efb;
let canvas_id_efb = "";
let fileEfb;
let formName_Efb;
let current_s_efb = 1
let devlop_efb = false;
let preview_efb = false;
let lan_name_emsFormBuilder ='en';
let stock_state_efb =false;
let page_state_efb ="";
let setting_emsFormBuilder=[];
let position_l_efb ="start"
let temp_efb;
let pub_el_text_color_efb='text-labelEfb';
let pub_message_text_color_efb ='text-muted';
let pub_icon_color_efb = "text-pinkEfb";
let pub_label_text_color_efb ='text-labelEfb';
let pub_el_border_color_efb='border-d';
let pub_bg_button_color_efb='btn-primary';
let pub_txt_button_color_efb='text-white';
let sendBack_emsFormBuilder_pub = [];
const getUrlparams_efb = new URLSearchParams(location.search)

function efb_var_waitng(time) {
  setTimeout(() => {
    if (typeof (efb_var) == "object" && efb_var.hasOwnProperty('text')) {
      formName_Efb = efb_var.text.form
      default_val_efb = efb_var.text.selectOption
      pro_efb = efb_var.pro == "1" || efb_var.pro == 1 ? true : false;
      position_l_efb = efb_var.rtl == 1 ? "end" : "start";
      lan_name_emsFormBuilder =efb_var.language.slice(0,2);
      if(efb_var.hasOwnProperty('addons')  && typeof(efb_var.addons)== "object") addons_emsFormBuilder =efb_var.addons
      return;
    } else {
      time += 50;
      time != 30000 ? efb_var_waitng(time) : alert_message_efb(efb_var.text.error, "Please Hard Refresh", 60)
    }
  }, time)
}
efb_var_waitng(50)

const efb_build_confirm_body = (variant = 'danger', iconCls = 'bi-trash', title = '', message = '', itemLabel = '') => {
  const iconType = variant === 'warning' ? 'efb-icon-warning' : variant === 'info' ? 'efb-icon-info' : 'efb-icon-danger';
  const labelHtml = itemLabel ? `<b>${itemLabel}</b>` : '';
  return `<div class="efb-confirm-body">
    <div class="efb-confirm-icon-wrap ${iconType}"><i class="efb ${iconCls}"></i></div>
    <div class="efb-confirm-title">${title}</div>
    <div class="efb-confirm-message">${message}${labelHtml ? '<br>' + labelHtml : ''}</div>
  </div>`;
};

let last_show_modal_efb = '';
const show_modal_efb = (body, title, icon, type) => {
  last_show_modal_efb =type;
  const mx = Number(efb_var.rtl) == 1 ? 'ms-2' : 'me-2';
  document.getElementById("settingModalEfb-title").innerHTML = title;
  document.getElementById("settingModalEfb-icon").className = icon + ` efb ${mx}`;
  document.getElementById("settingModalEfb-body").innerHTML = body;
  if (type == "settingBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.contains('modal-new-efb') ? '' : document.getElementById("settingModalEfb").classList.add('modal-new-efb')
  }
  else if (type == "deleteBox" || type=="duplicateBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById('settingModalEfb_').classList.contains('efb-confirm-dialog')) {
      document.getElementById('settingModalEfb_').classList.add('efb-confirm-dialog');
    }
    if (!document.getElementById('modalConfirmBtnEfb')) {
      const isDelete = type === 'deleteBox';
      const confirmClass = isDelete ? 'efb-btn-confirm-danger' : 'efb-btn-confirm-primary';
      document.getElementById('settingModalEfb-sections').innerHTML += `
    <div class="efb modal-footer efb-confirm-footer" id="modal-footer-efb">
      <a type="button" class="efb-btn-cancel" onclick="state_modal_show_efb(0)">
          ${efb_var.text.no}
      </a>
      <a type="button" class="${confirmClass}" id="modalConfirmBtnEfb">
          ${isDelete ? efb_var.text.yes : efb_var.text.yes}
      </a>
    </div>`
    }
  } else if (type == "saveBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else if (type == "saveLoadingBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
    document.getElementById('settingModalEfb-body').innerHTML = efbLoadingCard('',5);

  } else if (type == "chart") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
  }
}

let add_buttons_zone_efb = (state, id) => {
  const stng = `  <div class="efb col-sm-10 efb">
  <div class="efb  BtnSideEfb btn-edit-holder d-none efb" id="btnSetting-button_group">
      <button type="button" class="efb btn efb btn-edit efb btn-sm" id="settingElEFb"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
          onclick="show_setting_window_efb('button_group')">
          <div class="icon-container efb"><i class="efb   bi-gear-wide-connected text-success" id="efbSetting"></i></div>
      </button>
  </div>
  </div>`;
  const floatEnd = id == "dropZoneEFB" ? 'float-end' : ``;
  const btnPos = id != "dropZoneEFB" ? ' text-center mx-2' : ''
  let dis = ''
  if (true) {
    let t = valj_efb.findIndex(x => x.type == "stripe");
     t = t==-1 ? valj_efb.findIndex(x => x.type == "persiaPay") : t;
     t = t==-1 ? valj_efb.findIndex(x => x.type == "paypal") : t;
    t = t != -1 ? valj_efb[t].step : 0;
    dis = (valj_efb[0].type == "payment" )&& (valj_efb[0].steps == 1 && t == 1) && preview_efb != true ? 'disabled' : '';
  }
  const corner = valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner: 'efb-square';
  const btns_align = valj_efb[0].hasOwnProperty('btns_align') ? valj_efb[0].btns_align + ' mx-3':'justify-content-center';
  const  row = Number(valj_efb[0].steps)==1 ? '' : 'row';
  const s = `
  <div class="efb d-flex ${btns_align} ${state == 0 ? 'd-block' : 'd-none'} ${btnPos} efb " id="f_btn_send_efb" data-tag="buttonNav">
    <a id="btn_send_efb" role="button" class="efb text-decoration-none mx-0 btn p-2 ${dis} ${valj_efb[0].button_color}  ${corner} ${valj_efb[0].el_height}  efb-btn-lg ${floatEnd}"> ${valj_efb[0].icon.length > 3 && valj_efb[0].icon != 'bi-undefined' && valj_efb[0].icon != 'bXXX' ? `<i class="efb   ${valj_efb[0].icon} mx-2  ${valj_efb[0].icon_color}   ${valj_efb[0].el_height}" id="button_group_icon"> </i>` : `<i class="efb d-none   ${valj_efb[0].icon} mx-2  ${valj_efb[0].icon_color}   ${valj_efb[0].el_height}" id="button_group_icon"> </i>`}<span id="button_group_button_single_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_single_text}</span></a>
  </div>`
  const d = `
  <div class="efb d-flex ${btns_align} ${state == 1 ? 'd-block' : 'd-none'} ${btnPos} ${efb_var.rtl == 1 ?'flex-row-reverse' :''} efb" id="f_button_form_np">
  <a id="prev_efb" role="button" class="efb text-decoration-none btn  p-2  ${valj_efb[0].button_color}    ${corner}   ${valj_efb[0].el_height}   efb-btn-lg ${floatEnd} m-1">${valj_efb[0].button_Previous_icon.length > 2 ? `<i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} ${valj_efb[0].el_height}" id="button_group_Previous_icon"></i>` : ``} <span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ${valj_efb[0].button_Previous_icon != 'bi-undefined' ? 'mx-2' : ''}">${valj_efb[0].button_Previous_text}</span></a>
  <a id="next_efb" role="button" class="efb text-decoration-none btn  ${dis} p-2 ${valj_efb[0].button_color}    ${corner}  ${valj_efb[0].el_height}    efb-btn-lg ${floatEnd} m-1"><span id="button_group_Next_button_text" class="efb  ${valj_efb[0].el_text_color} ${valj_efb[0].button_Next_text != 'bi-undefined' ? ' mx-2' : ''}">${valj_efb[0].button_Next_text}</span> ${valj_efb[0].button_Next_icon.length > 3 ? ` <i class="efb  ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}  ${valj_efb[0].el_height}" id="button_group_Next_icon"></i>` : ``}</a>
  </div>
  `
  let c = `<div class="efb footer-test efb">`
  if (id != "dropZoneEFB") {
     if(id =='body_efb') c = `<div class="efb footer-test efb p-1 ">`;
    c += state == 0 ? `${s}</div>` : `${d}</div> <!-- end btn -->`
  } else {
    if(valj_efb[0].captcha == true) document.getElementById('dropZoneEFB').classList.add('captcha');
    c = ` <div class="efb col-12 mb-2 mb-5 pb-5 mt-3 mx-1 bottom-0 ${valj_efb[0].captcha != true ? 'd-none' : ''} " id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>  <div class="efb bottom-0 " id="button_group_efb"> <div class="efb  ${row}  showBtns efb" id="button_group" data-id="button_group" data-tag="buttonNav">${s} ${d} ${stng} </div></div>`
  }
  if (id != 'preview' && id != 'body_efb' && !document.getElementById('button_group')) { document.getElementById(id).innerHTML += c } else {
    return c;
  }
}
const colorTextChangerEfb = (classes, color) => { return classes.replace(/(text-primary|text-darkb|text-muted|text-secondary|text-pinkEfb|text-success|text-white|text-light|\btext-colorDEfb-+[\w\-]+|text-danger|text-warning|text-info|text-dark|text-labelEfb)/, ` ${color} `) ?? `${classes} ${color}`; }
const alignChangerElEfb = (classes, value) => { return classes.replace(/(justify-content-start|justify-content-end|justify-content-center)/, ` ${value} `) ?? `${classes} ${value} `; }
const alignChangerEfb = (classes, value) => { return classes.replace(/(txt-left|txt-right|txt-center)/, ` ${value} `) ?? `${classes} ${value} `; }
const RemoveTextOColorEfb = (classes) => { return classes.replace('text-', ``); }
const colorBorderChangerEfb = (classes, color) => { return classes.replace(/\bborder+-+[\w\-]+/gi, ` ${color} `) ?? `${classes} ${color} `; }
const cornerChangerEfb = (classes, value) => { return classes.replace(/(efb-square|efb-rounded|rounded-+[0-5] )/, ` ${value} `) ?? `${classes} ${value} `; }
const colMdChangerEfb = (classes, value) => { return /\bcol-md-\d+/.test(classes) ? classes.replace(/\bcol-md-\d+/, ` ${value} `) : `${classes} ${value} `; }
const PxChangerEfb = (classes, value) => { return classes.replace(/\bpx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const MxChangerEfb = (classes, value) => { return classes.replace(/\bmx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const btnChangerEfb = (classes, value) => { return classes.replace(/\bbtn-outline-+\w+|\bbtn-+\w+/, ` ${value} `) ?? `${classes} ${value} `; }

const loading_messge_efb = () => {
  const svg = `<svg viewBox="0 0 120 30" height="15px" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
  <circle cx="15" cy="15" r="15" fill="#abb8c3">
      <animate attributeName="r" from="15" to="9"
               begin="0s" dur="1s"
               values="15;9;15" calcMode="linear"
               repeatCount="indefinite" />
  </circle>
  <circle cx="60" cy="15" r="9" fill="#abb8c3">
      <animate attributeName="r" from="9" to="15"
               begin="0.3s" dur="1s"
               values="9;15;9" calcMode="linear"
               repeatCount="indefinite" />
  </circle>
  <circle cx="105" cy="15" r="15" fill="#abb8c3">
      <animate attributeName="r" from="15" to="9"
               begin="0.6s" dur="1s"
               values="15;9;15" calcMode="linear"
               repeatCount="indefinite" />
  </circle>
</svg>`
  return `
<h3 class="efb fs-3 text-center">${efb_var.text.pleaseWaiting} ${svg}</h3>`
}
function copyCodeEfb(id , tagid = '') {
  var copyText = document.getElementById(id);
  var textVal = copyText.value || copyText.innerText;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(textVal).then(function() {
      if (tagid != '') {
        const t = (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text.copied)
          ? efb_var.text : (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text)
          ? ajax_object_efm.text : null;
        const tag = document.getElementById(tagid);
        tag.innerHTML = t && t.copied ? t.copied.replace('%s','') : '✓';
      }
    });
  } else {
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    if (tagid != '') {
      const t = (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text.copied)
        ? efb_var.text : (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text)
        ? ajax_object_efm.text : null;
      const tag = document.getElementById(tagid);
      tag.innerHTML = t && t.copied ? t.copied.replace('%s','') : '✓';
    }
  }

}
function validExtensions_efb_fun(type, fileType,indx) {
  type= type.toLowerCase();
  const tt = valj_efb.length>1 && valj_efb[indx].hasOwnProperty('file_ctype') ? valj_efb[indx].file_ctype.replaceAll(',',' , ') : '';
  filetype_efb={'image':'image/png, image/jpeg, image/jpg, image/gif, image/heic',
  'media':'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg, video/mov, video/quicktime',
  'document':'.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
  'zip':'.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar',
  'allformat':'image/png, image/jpeg, image/jpg, image/gif, image/heic, audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg, video/mpg, audio/mpg, video/mov, video/quicktime, .xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf, text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text, .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, .heic, video/mov, .mov, video/quicktime',
  'customize':tt
  }
  var allowed = filetype_efb[type];
  if (!allowed) return false;
  return allowed.includes(fileType) ;
}
let steps_len_efb

function prev_btn_efb() {
  var cs = current_s_efb;
  if (cs == 2) {
    var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
    document.getElementById("next_efb").innerHTML = val;
    document.getElementById("next_efb").classList.toggle("d-none");
  } else if (cs == valj_efb[0].steps) {
    var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
    document.getElementById("next_efb").innerHTML = val;
     if (sitekye_emsFormBuilder.length > 1 && (valj_efb[0].captcha == 1 || valj_efb[0].captcha == "1" ||  valj_efb[0].captcha == 'true')) {
      document.getElementById("next_efb").classList.remove("disabled");
    }
  }
  var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
  if (valj_efb[0].type == "payment" && preview_efb != true) {
    let state = valj_efb.findIndex(x => x.type == "stripe");
    state = state == -1 ? valj_efb.findIndex(x => x.type == "persiaPay") : state;
    if (valj_efb[state].step == current_s) {
      document.getElementById("next_efb").classList.remove("disabled");
    }
  }
  prev_s_efb = document.querySelector('[data-step="step-' + (current_s_efb-1) + '-efb"]');
  var s = "" + (current_s_efb - 1) + "";
  var val = valj_efb.find(x => x.step == s);
  if(Number(valj_efb[0].show_icon)!=1){
      document.querySelector('[data-step="icon-s-' + current_s_efb + '-efb"]').classList.remove("active");
      document.querySelector('[data-step="step-' + current_s_efb + '-efb"]').classList.toggle("d-none");
      document.getElementById("title_efb").className = val["label_text_color"];
      document.getElementById("desc_efb").className = val["message_text_color"];
      document.getElementById("title_efb").textContent = val["name"];
      document.getElementById("desc_efb").textContent = val['message'] != '' ? val['message'] : val['name'];
      document.getElementById("title_efb").classList.add("text-center", "efb", "mt-1");
      document.getElementById("desc_efb").classList.add("text-center", "efb", "fs-7");
  }
  current_s.classList.add('d-none');
  prev_s_efb.classList.remove("d-none");
  current_s_efb = current_s_efb - 1;
  localStorage.setItem("step", current_s_efb);
  setProgressBar_efb(current_s_efb, steps_len_efb);
  if (current_s_efb == 1) {
    document.getElementById("prev_efb").classList.toggle("d-none");
    document.getElementById("next_efb").classList.toggle("d-none");
  }
  if (Number(valj_efb[0].captcha) == 1 && current_s_efb < steps_len_efb) {
    document.getElementById("next_efb").classList.remove("disabled");
  }
  if (document.getElementById("body_efb")) {
    document.getElementById("body_efb").scrollIntoView({ behavior: "smooth", block: "center", inline: "center" });
  }
}
function setProgressBar_efb(curStep, steps_len_efb) {
  if(Number(valj_efb[0].show_pro_bar)==1) return
  var percent = (curStep / steps_len_efb) * 100;
  percent = Math.round(percent * 100) / 100;
  document.querySelector(".progress-bar-efb").style.width = percent + "%";
}

localStorage.getItem('count_view') ? localStorage.setItem(`count_view`, parseInt(localStorage.getItem('count_view')) + 1) : localStorage.setItem(`count_view`, 0)

const alertStyles_efb = {
  danger: { bg: 'linear-gradient(135deg, #c00751 0%, #f95e5e 100%)', icon: 'bi-ban', color: '#fff' },
  warning: { bg: 'linear-gradient(135deg, #ffc107 0%, #ffb300 100%)', icon: 'bi-exclamation-triangle-fill', color: '#333' },
  success: { bg: 'linear-gradient(135deg, #065518  0%, #108f69 100%)', icon: 'bi-check-lg', color: '#fff' },
  info: { bg: 'linear-gradient(135deg, #202a8d 0%, #667eea 100%)', icon: 'bi-info-lg', color: '#fff' }
};

let alertCounter_efb = 0;

function alert_message_efb(title, message, sec, alertType) {
  try {
    sec = sec * 1000;
    const alertId = `alert_item_efb_${++alertCounter_efb}`;
    const style = alertStyles_efb[alertType] || alertStyles_efb.info;
    const isRtl = efb_var.text.rtl == 1;
    const rtl = isRtl ? 'rtl-text' : '';
    const isMobile = window.innerWidth < 768;

    let container = document.getElementById('alert_container_efb');
    let width = document.getElementById('adminmenuback')?.offsetWidth || 0;
    width = width > 0 ? width + 15 : 15;
    if (!container) {
      container = document.createElement('div');
      container.id = 'alert_container_efb';
      container.className = 'efb';
      container.style.cssText = `position:fixed; top:80px; ${isRtl ? 'right' : 'left'}:${width}px; z-index:99999; width:${isMobile ? 'calc(100vw - 40px)' : '33%'}; min-width:280px; max-width:450px; display:flex; flex-direction:column; gap:10px; pointer-events:none;`;
      document.body.appendChild(container);
    }

    const alertHtml = `
      <div id="${alertId}" class="efb alert_item_efb ${rtl}" style="background:${style.bg}; border-radius:12px; padding:14px 16px; box-shadow:0 4px 20px rgb(0 0 0 / 49%); animation:slideIn_efb .3s ease; transition:all .3s ease; pointer-events:auto;">
        <div class="efb d-flex align-items-center">
          <div class="efb" style="border-radius:50%; padding:8px; margin-${isRtl ? 'left' : 'right'}:12px; flex-shrink:0;">
            <i class="efb bi ${style.icon}" style="font-size:1.2rem; color:${style.color};"></i>
          </div>
          <div class="efb flex-grow-1" style="min-width:0;">
            ${title ? `<h6 class="efb mb-0" style="color:${style.color}; font-weight:600; font-size:0.9rem;">${title}</h6>` : ''}
            ${message ? `<p class="efb mb-0" style="color:${style.color}; opacity:0.95; font-size:0.8rem; line-height:1.4;">${message}</p>` : ''}
          </div>
          <button type="button" class="efb p-0" onclick="close_msg_efb('${alertId}')" style="background:rgba(255,255,255,0.2); border:none; border-radius:50%; width:26px; height:26px; cursor:pointer; flex-shrink:0; margin-${isRtl ? 'right' : 'left'}:8px;">
            <i class="efb bi bi-x" style="color:${style.color}; font-size:1rem;"></i>
          </button>
        </div>
      </div>`;

    container.insertAdjacentHTML('beforeend', alertHtml);

    setTimeout(() => {
      const el = document.getElementById(alertId);
      if (el) {
        el.style.opacity = '0';
        el.style.transform = `translateX(${isRtl ? '' : '-'}20px)`;
        setTimeout(() => el.remove(), 300);
      }
    }, sec);

  } catch (error) {
    alert(message);
  }
}

function close_msg_efb(alertId) {
  const el = alertId ? document.getElementById(alertId) : document.querySelector('.alert_item_efb');
  if (el) {
    const isRtl = efb_var.text.rtl == 1;
    el.style.opacity = '0';
    el.style.transform = `translateX(${isRtl ? '' : '-'}20px)`;
    setTimeout(() => el.remove(), 200);
  }
}
function noti_message_efb(message, alert ,id) {
  alert = alert ? `alert-${alert}` : 'alert-info';
  let d = document.getElementById(id);
  if(document.getElementById('noti_content_efb')){
    document.getElementById('noti_content_efb').remove()
  }
    d.innerHTML += ` <div id="noti_content_efb" class="efb w-75 mt-0 my-1 alert-dismissible alert ${alert}  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <p class="efb my-0">${message}</p>
  </div>`
}

function fun_el_select_in_efb(el) {
  const validSelectElements = new Set(['select', 'multiselect', 'conturyList', 'stateProvince','statePro','country','city', 'cityList', 'paySelect', 'payMultiselect']);
  return validSelectElements.has(el);
 }
function fun_el_check_radio_in_efb(el) {
  const validElements = new Set(['radio', 'checkbox', 'payRadio', 'payCheckbox', 'imgRadio', 'chlRadio', 'chlCheckBox']);
  return  validElements.has(el);
  }

function type_validate_efb(type) {
  return type == "select" || type == "multiselect" || type == "text" || type == "password" || type == "email" || type == "conturyList" || type == "stateProvince" || type == "file" || type == "url" || type == "color" || type == "date" || type == "textarea" || type == "tel" || type == "number" ? true : false;
}

async function fun_offline_Efb() {
  const find_by_value = (val) => {
      const index = valj_efb.findIndex(row => row.value === val);
      if (index > -1) {
          return valj_efb[index];
      } else {
          return false;
      }
  };
  let el = '';
  if(localStorage.hasOwnProperty('sendback')==false) return;
  const values =   JSON.parse(localStorage.getItem('sendback'))
  let temp, id;
  for (let value of values) {

    sendBack_emsFormBuilder_pub.push(value);
    switch (value.type) {
      case 'email':
      case 'text':
      case 'password':
      case 'tel':
      case 'url':
      case "date":
      case 'color':
      case 'range':
      case 'number':
      case 'firstName':
      case 'lastName':
      case 'prcfld':
        document.getElementById(value.id_ob).value = value.value;

        el=value.type;
        break;
      case 'textarea':
        document.getElementById(value.id_ob).innerHTML = value.value;

        el=value.type;
        break;
      case 'checkbox':
      case 'radio':
      case 'payCheckbox':
      case 'payRadio':
      case 'payRadio':
        document.getElementById(value.id_ob).checked = true;

        el=value.type;
        break;
      case 'stateProvince':
        if(el=='contury' ||el=='conturyList' ){
            el = valj_efb.findIndex(x => x.id_ == value.id_);
            id= valj_efb[el].id_;
            await callFetchStatesPovEfb(id+'_options', temp, el, 'pubSelect',true);
          }

        el=value.type;
        break;
      case 'cityList':
      case 'city':
        if(el=='stateProvince' ||el=='stateProvinceList' ){
            el = valj_efb.findIndex(x => x.id_ == value.id_);
            id= valj_efb[el].id_;
            let iso2_country ='GB';
            let iso2_statePove = 'ENG';

            if(typeof autofilled_search_value_efb!== 'undefined'){
              temp = sendBack_emsFormBuilder_pub.find(x => x.id_ == id);
              iso2_country = temp.cont_;
              iso2_statePove = temp.statePrev_;
            }
            await callFetchCitiesEfb(id+'_options',iso2_country,iso2_statePove, el,'pubSelect',true);

          }

       el = value.type
       break;
      case 'paySelect':
      case 'select':

        document.getElementById(value.id_ob).value = value.value;

        el=value.type;
        break;
        case 'conturyList':
        case 'contury':
          id = valj_efb.findIndex(x => x.id_ == value.id_);
          el = find_by_value(value.value);
          temp = el.id_op

          valj_efb[id].hasOwnProperty('contury') ? valj_efb[id].country = temp : Object.assign(valj_efb[id], { country: temp });
          document.getElementById(value.id_ob).value = value.value;
          el=value.type;
        break;
      case 'multiselect':
      case 'payMultiselect':

        const op = document.getElementById(`${value.id_}_options`)
        op.innerHTML = value.value.replaceAll('@efb!', ',');
        const vs = value.value.split('@efb!');
        for (let v of vs) {
          el = document.querySelector(`.efblist  [data-name="${v}"]`)
          if (el) {
            el.className += ` border-info`;
            el.innerHTML = `
                <th scope="row" class="bi-check-square text-info efb"></th>
                <td class="efb  ms">${v}</td>
                `
            op.dataset.select = `${el.dataset.row} @efb!`
          }
        }

        el=value.type;
        break;
      case 'esign':
        el = document.getElementById(`${value.id_}_`);
        let ctx = el.getContext("2d");
        let image = new Image();
        image.onload = function () {
          ctx.drawImage(image, 0, 0);
        };
        image.src = value.value

        el=value.type;
        break;
      case 'yesNo':
        el = document.querySelectorAll(`[data-lid='${value.id_}']`)
        for (let op of el) {
          if (op.dataset.value == value.value) {
            op.className += 'active';
          }
        }

        el=value.type;
        break;
      case 'switch':
        document.getElementById(value.id_ob).checked = value.value == "On" ? true : false;
        el=value.type;
        break;
      case 'rating':
        if (value.value >= 1) document.getElementById(`${value.id_}-star1`).checked = true;
        if (value.value >= 2) document.getElementById(`${value.id_}-star2`).checked = true;
        if (value.value >= 3) document.getElementById(`${value.id_}-star3`).checked = true;
        if (value.value >= 4) document.getElementById(`${value.id_}-star4`).checked = true;
        if (value.value == 5) document.getElementById(`${value.id_}-star5`).checked = true;
        el=value.type;
        break;
      case 'document':
        let s = value.url.split('/');
        s = s.pop();
        el = document.getElementById(`${value.id_}_-message`);
        el.className = `efb text-success efb fs-7 fw-bolder`;
        el.innerHTML = `${efb_var.text.uploadedFile}: ${s}`;
        if(!el.classList.contains('show'))el.classList.add('show');

        el=value.type;
        break;
      case 'stripe':
      break;
      case 'persiaPay':
      break;
      case 'mobile':
        let v = value.value.split('+');
        el = document.getElementById(value.id_ob+'_');
        let storedPhoneNumber =value.value;
        if(v.length==3){
          storedPhoneNumber ='+'+ v[2];
        }
        const country =efb_var.wp_lan.split('_')[1].toUpperCase();
        const iti = window.intlTelInput(el, {
            initialCountry: country,
            loadUtils: () => import(efb_var.images.utilsJs)
        });
        iti.setNumber(storedPhoneNumber);

      el=value.type;
      break;
    }
  }
  if(valj_efb[0].type=="payment" && valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string"){
    fun_after_bankpay_persia_ui();
  }
}

function funTnxEfb(val, title, message) {
  const done = valj_efb[0].thank_you_message.done || efb_var.text.yad
  const corner = valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner: 'efb-square';
  const thankYou = valj_efb[0].thank_you_message.thankYou || efb_var.text.thanksFillingOutform
  const t = title ? title : done;
  const m = message ? message : thankYou;
  const clr_doneMessageEfb=valj_efb[0].hasOwnProperty("clrdoneMessageEfb") ? valj_efb[0].clrdoneMessageEfb :"doneMessageEfb" ;
  const clr_doneTitleEfb =valj_efb[0].hasOwnProperty("clrdoneTitleEfb") ? valj_efb[0].clrdoneTitleEfb :"doneTitleEfb" ;
  const clr_doniconEfb =valj_efb[0].hasOwnProperty("clrdoniconEfb") ? valj_efb[0].clrdoniconEfb :"doneTitleEfb" ;
  const doneTrackEfb=clr_doneTitleEfb ;
  const show_track = valj_efb[0].trackingCode == true && valj_efb[0].type != "survey" ? true : false;
  const trckCd = `
  <div class="efb fs-4"><h5 class="efb mt-3 efb fs-4 ${clr_doneMessageEfb} text-center" id="doneTrackEfb">${valj_efb[0].thank_you_message.trackingCode || efb_var.text.trackingCode}: <strong>${val}</strong></h5>
               <input type="text" class="efb hide-input efb d-none " value="${val}" id="trackingCodeEfb">
               <div id="alert"></div>
           <button type="button" class="efb btn  ${corner} efb ${valj_efb[0].button_color}  ${valj_efb[0].el_text_color}  ${show_track ? 'd-block mx-auto' : 'd-none mx-auto'} efb-btn-lg my-3 fs-5" onclick="copyCodeEfb('trackingCodeEfb' ,'trackingCodeEfb2')">
                   <i class="efb fs-5 bi-clipboard-check mx-1  ${valj_efb[0].el_text_color}"></i><span id="trackingCodeEfb2">${efb_var.text.copy}</span>
               </button></div>`
  return `
                    <h4 class="efb  my-1 fs-2 ${doneTrackEfb} text-center" id="doneTitleEfb">
                        <i class="efb ${valj_efb[0].thank_you_message.hasOwnProperty('icon') ? valj_efb[0].thank_you_message.icon : 'bi-hand-thumbs-up'}  title-icon mx-2 fs-2 ${clr_doniconEfb}" id="DoneIconEfb"></i>${t}
                    </h4>
                    <h3 class="efb fs-4 ${clr_doneMessageEfb} text-center" id="doneMessageEfb">${m}</h3>
                  <span class="efb text-center" ${show_track ? trckCd : ''}</span>
  `
}

function calPLenEfb(len) {
  let p = 2
  if (len <= 5) { p = 40 }
  else if (len > 5 && len <= 10) { p = 20 }
  else if (len > 10 && len <= 50) { p = 15 }
  else if (len > 50 && len <= 100) { p = 9 }
  else if (len > 100 && len <= 300) { p = 3 }
  else if (len > 300 && len <= 600) { p = 1.5 }
  else if (len > 600 && len <= 1000) { p = 1.2 }
  else { p = 1.1 }
  return p;
}
function replaceContentMessageEfb(value){
  if(typeof value !== 'string') return value;
  value = value.replace(/[\\]/g, '');
  value = value.replaceAll(/(\\"|"\\)/g, '"');
  value = value.replaceAll(/(\\\\n|\\\\r)/g, '<br>');
   value = value.replaceAll("@efb@sq#","'");
   value = value.replaceAll("@efb@vq#","`");
   value = value.replaceAll("@efb@dq#",`''`);
   value = value.replaceAll("@efb@nq#",`<br>`);
  return value;
}
function fun_upload_file_api_emsFormBuilder(id, type,tp,file) {
  if (!navigator.onLine) {
	const msg = efb_var.text.fileUploadNetworkError || efb_var.text.offlineSend;
	alert_message_efb('', msg, 17, 'danger');
    return;
  }
  let indx = files_emsFormBuilder.findIndex(x => x.id_ === id);
  if (indx === -1) {
    const ob = typeof valueJson_ws !== 'undefined' ? valueJson_ws.find(x => x.id_ === id) : null;
    const fid = ob && ob.hasOwnProperty('step') ? (document.getElementById(id + '_') ? document.getElementById(id + '_').dataset.formid || 0 : 0) : 0;
    files_emsFormBuilder.push({ id_: id, value: "@file@", state: 0, url: "", type: "file", name: ob ? ob.name : '', session: sessionPub_emsFormBuilder, form_id: fid });
    indx = files_emsFormBuilder.length - 1;
  }
  files_emsFormBuilder[indx].state = 1;
  files_emsFormBuilder[indx].type = type;
  let r = ""
  const form_id = files_emsFormBuilder[indx].hasOwnProperty('form_id') ? files_emsFormBuilder[indx].form_id : 0;
  let nonce_msg =''
  let sid =''
  if(form_id==0){
    sid = efb_var.sid;
  }else{
    const vj =fun_sid_efb(form_id)

    sid = vj.sid;
  }
    nonce_msg = efb_var.nonce ?? '';
  const page_id = efb_var.page_id ;
    const fd = new FormData();
    const idn =  id + '_';
    setTimeout(() => {
      uploadFile_api(file, id, tp, nonce_msg ,indx ,idn,page_id,form_id,sid);
      return true;
    }, 500);
}
function uploadFile_api(file, id, pl, nonce_msg ,indx,idn,page_id,fid,sid) {
  const progressBar = document.querySelector('#progress-bar');
  const idB =id+'-prB';
      fetch_uploadFile(file, id, pl, nonce_msg,page_id,fid,sid).then((data) => {

        var currentIndx = files_emsFormBuilder.findIndex(function(x) { return x.id_ === id; });
        if (currentIndx === -1) return;

        var responseData = data;
        if (data.hasOwnProperty('data')) {
          responseData = data.data;
        }

        if (data.success === true && responseData.success === true) {
          files_emsFormBuilder[currentIndx].url = responseData.file.url;
          files_emsFormBuilder[currentIndx].state = 2;
          files_emsFormBuilder[currentIndx].id = idn;
          const form_id = files_emsFormBuilder[currentIndx].hasOwnProperty('form_id') ? files_emsFormBuilder[currentIndx].form_id : 0;
          const ob = valueJson_ws.find(x => x.id_ === id) || 0;
          const o = [{
            id_: files_emsFormBuilder[currentIndx].id_,
            name: files_emsFormBuilder[currentIndx].name,
            amount: ob.amount,
            type: files_emsFormBuilder[currentIndx].type,
            value: '@file@',
            url: files_emsFormBuilder[currentIndx].url,
            session: sessionPub_emsFormBuilder,
            page_id: page_id,
            form_id: form_id,
          }];
          fun_sendBack_emsFormBuilder(o[0]);
          files_emsFormBuilder.splice(currentIndx, 1);
          const el = document.getElementById(idB)
          if(el){
            el.style.width = '100%';
            el.textContent = '100% = ' + file.name;
          }
          if(document.getElementById(id + '-prG')) document.getElementById(id + '-prG').classList.add('d-none');
        } else {
          var errorMessage = 'Upload failed';
          if (responseData.hasOwnProperty('file') && responseData.file.hasOwnProperty('error')) {
            errorMessage = responseData.file.error;
          } else if (responseData.hasOwnProperty('m')) {
            errorMessage = responseData.m;
          } else if (responseData.hasOwnProperty('error')) {
            errorMessage = responseData.error;
          } else if (data.hasOwnProperty('m')) {
            errorMessage = data.m;
          }

          const el = document.getElementById(idB);
		  const baseMsg = efb_var.text.fileUploadNetworkError || efb_var.text.offlineSend;
		  const fullMsg = errorMessage ? `${baseMsg}<br>${errorMessage}` : baseMsg;
		  alert_message_efb('', fullMsg, 300, 'danger');
          if(el==null) return;
          el.style.width = '0%';
          el.textContent = '0% = ' + file.name;

          var errIndx = files_emsFormBuilder.findIndex(function(x) { return x.id_ === id; });
          if (errIndx !== -1) files_emsFormBuilder[errIndx].state = 3;
          return;
        }
      })
      .catch((error) => {
        const el = document.getElementById(idB);

        var errorMessage = 'Network error or upload failed';
        if (typeof error === 'string') {
          errorMessage = error;
        } else if (error.message) {
          errorMessage = error.message;
        }

        const baseMsg = efb_var.text.fileUploadNetworkError || efb_var.text.offlineSend;
        const fullMsg = errorMessage ? `${baseMsg}<br>${errorMessage}` : baseMsg;
        alert_message_efb('', fullMsg, 30, 'danger');

        if(el) {
          el.style.width = '0%';
          el.textContent = '0% = Error: ' + file.name;
        }

        var catchIndx = files_emsFormBuilder.findIndex(function(x) { return x.id_ === id; });
        if (catchIndx !== -1) files_emsFormBuilder[catchIndx].state = 0;
      });
}
function fetch_uploadFile(file, id, pl, nonce_msg,page_id ,fid ,sid) {
  var idB =id+'-prB';
  return new Promise((resolve, reject) => {
    const formData = new FormData();
    formData.append('async-upload', file);
    formData.append('id', id);
    formData.append('pl', pl);
    formData.append('nonce_msg', nonce_msg);
    formData.append('sid', sid);
    formData.append('fid', fid);
    formData.append('page_id', efb_var.page_id);
    const url = efb_var.rest_url + 'Emsfb/v1/forms/file/upload';
    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', (event) => {
    if (event.lengthComputable) {
      const percent = Math.round((event.loaded / event.total) * 100);
      const el = document.getElementById(idB)
      if(el){
        el.style.width = percent + '%';
        el.textContent = percent + '% = ' + file.name;
      }
    }
    });
    xhr.addEventListener('load', () => {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        var cleanResponseText = xhr.responseText.trim();

        if (!cleanResponseText.startsWith('{') && !cleanResponseText.startsWith('[')) {
          var jsonMatch = cleanResponseText.match(/\{.*\}/s);
          if (jsonMatch) {
            cleanResponseText = jsonMatch[0];
          } else {
            reject('Server returned invalid JSON: ' + cleanResponseText.substring(0, 100));
            return;
          }
        }

        const response = JSON.parse(cleanResponseText);
        resolve(response);
      } catch (parseError) {
        reject('Invalid JSON response from server. Check console for details.');
      }
    } else {
      reject(xhr.statusText);
    }
    });
    xhr.addEventListener('error', () => {
    reject(xhr.statusText);
    });
    xhr.open('POST', url, true);
    xhr.setRequestHeader('X-WP-Nonce', nonce_msg);
    if (sid) xhr.setRequestHeader('sid', sid);
    if (fid) xhr.setRequestHeader('form_id', fid);
    xhr.send(formData);
  });
}
if (!Array.prototype.findIndex) {
  Array.prototype.findIndex = function(predicate) {
    if (this == null) {
      throw new TypeError('Array.prototype.findIndex called on null or undefined');
    }
    if (typeof predicate !== 'function') {
      throw new TypeError('predicate must be a function');
    }
    var list = Object(this);
    var length = parseInt(list.length) || 0;
    var thisArg = arguments[1];
    for (var i = 0; i < length; i++) {
      if (predicate.call(thisArg, list[i], i, list)) {
        return i;
      }
    }
    return -1;
  };
}

function santize_string_efb(str){
  if(str==undefined || str==null) return null;
  const regexp = /(<)(script[^>]*>[^<]*(?:<(?!\/script>)[^<]*)*<\/script>|\/?\b[^<>]+>|!(?:--\s*(?:(?:\[if\s*!IE]>\s*-->)?[^-]*(?:-(?!->)-*[^-]*)*)--|\[CDATA[^\]]*(?:](?!]>)[^\]]*)*]])>)/g
  return  str.replaceAll(regexp,'do not use HTML tags');
}
window.addEventListener('offline', (e) => {  });
window.addEventListener('online', (e) => {  });
 function fun_show_val_range_efb(id ){
  document.getElementById(id+'_rv').innerText=document.getElementById(id+'_').value;
 }
 santize_string_efb = (str) => {
  if(str==undefined || str==null) return null;
  const regexp = /(<)(script[^>]*>[^<]*(?:<(?!\/script>)[^<]*)*<\/script>|\/?\b[^<>]+>|!(?:--\s*(?:(?:\[if\s*!IE]>\s*-->)?[^-]*(?:-(?!->)-*[^-]*)*)--|\[CDATA[^\]]*(?:](?!]>)[^\]]*)*]])>)/g
  return str.replaceAll(regexp, '');
}
const checkInvalidUTF8_efb=(string, strip = false)=>{
  string = String(string);
  if (string.length === 0) {
    return '';
  }
  const isUTF8 = true;
  if (!isUTF8) {
    return string;
  }
  let utf8PCRE = true;
  if (!utf8PCRE) {
    return string;
  }
  if (encodeURI(string) === string) {
    return string;
  }
  if (strip) {
    return unescape(encodeURIComponent(string));
  }
  return '';
}
const preKsesLessThan_efb=(text)=>{
  return text.replace(/<[^>]*?((?=<)|>|$)/g, function(match) {
    return preKsesLessThanCallback_efb(match);
  });
}
const preKsesLessThanCallback_efb=(matches)=>{
  if (matches[0].indexOf(">") === -1) {
    return sanitizeXSS_efb(matches[0]);
  }
  return matches[0];
}
function sanitizeXSS_efb(unsafe) {
  const replacements = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;',
    '\\': '\\\\',
    '=': '&#61;',
    '/': '&#47;',
    '`': '&#96;',
    '%': '&#37;',
    ';': '&#59;',
    ':': '&#58;',
    '(': '&#40;',
    ')': '&#41;',
    'javascript:': 'javascript&#58;',
    'data:': 'data&#58;',
    'vbscript:': 'vbscript&#58;',
    'livescript:': 'livescript&#58;',
    'mhtml:': 'mhtml&#58;',
    'mocha:': 'mocha&#58;',
    'xss:': 'xss&#58;',
    '&lt;script': '&lt;scr&#105;pt',
    '&lt;/script': '&lt;&#47;scr&#105;pt',
    '&lt;iframe': '&lt;ifr&#97;me',
    '&lt;/iframe': '&lt;&#47;ifr&#97;me',
    '&lt;object': '&lt;obj&#101;ct',
    '&lt;/object': '&lt;&#47;obj&#101;ct',
    '&lt;embed': '&lt;emb&#101;d',
    '&lt;/embed': '&lt;&#47;emb&#101;d',
    '&lt;applet': '&lt;app&#108;et',
    '&lt;/applet': '&lt;&#47;app&#108;et',
    '&lt;meta': '&lt;m&#101;ta',
    '&lt;/meta': '&lt;&#47;m&#101;ta',
    '&lt;base': '&lt;b&#97;se',
    '&lt;/base': '&lt;&#47;b&#97;se',
    '&lt;link': '&lt;l&#105;nk',
    '&lt;/link': '&lt;&#47;l&#105;nk',
    '&lt;style': '&lt;styl&#101;',
    '&lt;/style': '&lt;&#47;styl&#101;'
  };

  return unsafe.replace(/&|<|>|'|"|\\|=|\/|`|%|;|:|\(|\)|javascript:|data:|vbscript:|livescript:|mhtml:|mocha:|xss:|&lt;script|&lt;\/script|&lt;iframe|&lt;\/iframe|&lt;object|&lt;\/object|&lt;embed|&lt;\/embed|&lt;applet|&lt;\/applet|&lt;meta|&lt;\/meta|&lt;base|&lt;\/base|&lt;link|&lt;\/link|&lt;style|&lt;\/style/g, match => replacements[match]);
}
const stripAllTags_efb=(string, removeBreaks = false)=>{
  string = string.replace(/</g, '＜');
  string = string.replace(/>/g, '＞');
  if (removeBreaks) {
    string = string.replace(/[\r\n\t ]+/g, " ");
  }
  return string.trim();
}
const sanitize_text_efb=(str, keep_newlines = false)=>{
  if (typeof str === 'object' || Array.isArray(str)) {
    return '';
  }else if(str==null || str==undefined){
    return str;
  }
  str = str.toString();
  let filtered=str;
  const urlRegex = /(https?:\/\/[^\s]+)/g;
  let match;
  while ((match = urlRegex.exec(filtered)) !== null) {
    let url = match[0];
    const unicodeUrl = decodeURI(url);
    filtered = filtered.replace(url,unicodeUrl);
  }
  if (filtered.indexOf('<') !== -1) {
    filtered = stripAllTags_efb(filtered, false);
    filtered = filtered.replace(/<\n/g, '&lt;\n');
  }
  if (!keep_newlines) {
    filtered = filtered.replace(/[\r\n\t ]+/g, ' ');
  }else{
    filtered = filtered.replace(/[\t ]+/g, ' ');
    filtered = filtered.replace(/[\r\n]+/g, '@n#');
  }
  filtered = filtered.trim();
  let found = false;
  while (/%[a-f0-9]{2}/i.test(filtered)) {
    filtered = filtered.replace(/%[a-f0-9]{2}/i, '');
    found = true;
  }
  if (found) {
    filtered = filtered.trim().replace(/ +/g, ' ');
  }
  return filtered;
}

function text_nr_efb(text , type){
  const val = type ==1 ?'<br>': '\n';
  text = text.replace(/@n#/g, val);
  return text;
}
function efb_remove_forbidden_chrs(text){
return text.replaceAll(/[!@#$%^&*()_,+}{?><":<=\][';/.\\|}]/g, '-');
}

function sendback_state_handler_efb(id_, state, step){
  const indx = sendback_efb_state.findIndex(x=>x.id_==id_);
  if(indx==-1 && state==false){
    sendback_efb_state.push({id_:id_,state:state,step:step})
    if(document.getElementById('btn_send_efb') && document.getElementById('btn_send_efb').classList.contains('disabled')==false )document.getElementById('btn_send_efb').classList.add('disabled');
    else if(document.getElementById('next_efb') && document.getElementById('next_efb').classList.contains('disabled')==false )document.getElementById('next_efb').classList.add('disabled');
  }else if(indx>-1 && state==true && sendback_efb_state.length>0){
    sendback_efb_state.splice(indx,1);
    setTimeout(() => {
      const indx_ = sendback_efb_state.findIndex(x=>x.step==step);
      if(indx_==-1 || sendback_efb_state.length==0){
        if(document.getElementById('btn_send_efb'))document.getElementById('btn_send_efb').classList.remove('disabled');
        else if(document.getElementById('next_efb'))document.getElementById('next_efb').classList.remove('disabled');
      }
    }, 200);
  }
}
function handle_change_event_efb(el){
    slice_sback=(i)=>{
      sendBack_emsFormBuilder_pub.splice(i, 1)
    }
    delete_by_id=(id)=>{
      const i = get_row_sendback_by_id_efb(id);
      if (i != -1) { slice_sback(i) }
    }
    el_empty_value=(id)=>{
      const id_ = id +'_';
      const s = valj_efb.find(x => x.id_ == id);
      const el_msg = document.getElementById(id_+'-message');
      if(Number(s.required)==0){
        document.getElementById(id_).className = colorBorderChangerEfb(document.getElementById(id_).className, s.el_border_color);
        el_msg.classList.remove('show');
      if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id,true,current_s_efb);
      }else{
        document.getElementById(id_).className = colorBorderChangerEfb(document.getElementById(id_).className, "border-danger");
        if(el_msg.classList.contains('show')==false)el_msg.classList.add('show');
        el_msg.innerHTML = efb_var.text.enterTheValueThisField;
      }
      delete_by_id(id);
    }
    validate_len_efb =()=>{
      let offsetw = offset_view_efb();
      const mi=()=> {return  el.type!="number" ? 2 :0}
      let len = el.hasAttribute('minlength')  ? el.minLength :mi();
      if (value.length < len && el.type!="number") {
        state = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd = document.getElementById(`${el.id}-message`);
        let m = efb_var.text.mmplen.replace('NN',len);
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
              if(vd){
                if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                  vd.classList.add('unpx');
                }
                vd.innerHTML =msg;
                vd.classList.add('show');
              }
              delete_by_id(id_);
              return 0;
      }else if(value < len && el.type=="number"){
        state = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd = document.getElementById(`${el.id}-message`);
        let m = efb_var.text.mcplen.replace('NN',len);
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
              if(vd){
                if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                  vd.classList.add('unpx');
                }
                vd.innerHTML =msg;
                vd.classList.add('show');
              }
              delete_by_id(id_);
              return 0;
      }
      len =  el.hasAttribute('maxlength') ? el.maxLength :0;
      if(len==0) return 1;
      if (value.length > len && el.type!="number") {
        state = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd = document.getElementById(`${el.id}-message`);
        let m = efb_var.text.mmxplen.replace('NN',len);
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
              if(vd){
                if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                  vd.classList.add('unpx');
                }
                vd.innerHTML =msg;
                vd.classList.add('show');
              }
              delete_by_id(id_);
              return 0;
      }else if(value > len && el.type=="number"){
        state = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd = document.getElementById(`${el.id}-message`);
        let m = efb_var.text.mxcplen.replace('NN',len);
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
              if(vd){
                if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                  vd.classList.add('unpx');
                }
                vd.innerHTML =msg;
                vd.classList.add('show');
              }
              delete_by_id(id_);
              return 0;
      }
            return 1;
    }
    const formid = el.dataset.hasOwnProperty('formid') ? el.dataset.formid : -1;
    valj_efb =  get_structure_by_form_id_efb(formid);
    let ob = valj_efb.find(x => x.id_ === el.dataset.vid);
    let value = ""
    const id_ = el.dataset.vid
    let state
    if(!ob){
      if(el.id.includes('chl')!=false){
        ob= sendBack_emsFormBuilder_pub.find(x => x.id_ob === el.dataset.id);
      }
    }
    let vd ;
    if(valj_efb[0].hasOwnProperty('booking') && Number(valj_efb[0].booking)==1) {
      const r = fun_booking_avilable(el)
      if(r[0]==false){
        alert_message_efb(r[1],'',150,'danger')
        return
      }
    }
    switch (el.type) {
      case "text":
      case "color":
      case "number":
      case "date":
      case "textarea":
        const outp = el.type =="textarea" ?true : false
        value = sanitize_text_efb(el.value,outp);
        if(value.length==0){el_empty_value(id_); return;}
        if(el.classList.contains("intlPhone")==true){
          el.value = el.value.replace(/\s/g, '');
          value = el.value;
         return;
        }else if(el.classList.contains("pdpF2")==true){
          const pdp_regex = /^\d{4}-\d{2}-\d{2}$/;
          if(!pdp_regex.test(el.value)){
            el.className = colorBorderChangerEfb(el.className, "border-danger");
            vd = document.getElementById(`${el.id}-message`);
            vd.innerHTML = efb_var.text.enterValidDate;
            if(vd.classList.contains('show')==false)vd.classList.add('show');
            if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,false,current_s_efb);
            delete_by_id(id_);
            return;
          }
          valid = true;
          value = el.value;
        }
        if(validate_len_efb()==0 && (el.dataset.hasOwnProperty('type') && el.dataset.type!="chlCheckBox")){
          if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,false,current_s_efb);
         return;
        }else {
          if (value.search(`"`) != -1) {
            el.value = value.replaceAll(`"`, '');
            noti_message_efb(`Don't use forbidden Character like: "` , 'danger' , `step-${current_s_efb}-efb-msg` );
          }
          el.className = colorBorderChangerEfb(el.className, "border-success");
          vd= document.getElementById(`${el.id}-message`)
          if(vd)vd.classList.remove('show');
        }
        break;
      case 'url':
        vd = document.getElementById(`${el.id}-message`)
        const che = el.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/g);
        if(el.value.length==0){
          el_empty_value(id_);
        } else if (che == null) {
          valid = false;
          el.className = colorBorderChangerEfb(el.className, "border-danger");
          vd.innerHTML = efb_var.text.enterValidURL;
          if(vd.classList.contains('show')==false)vd.classList.add('show');
          if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,false,current_s_efb)
          delete_by_id(id_);
        } else {
          valid = true;
          value = el.value;
          vd.classList.remove('show');
           vd.innerHTML="";
          el.className = colorBorderChangerEfb(el.className, "border-success");
        }
        break;
      case "checkbox":
      case "radio":
        value = sanitize_text_efb(el.value);
        if (ob.type == "switch") value = el.checked == true ? efb_var.text.on : efb_var.text.off;
        vd =document.getElementById(`${ob.id_}_-message`)
        if (el.value.length > 1 || el.checked == true) {
          vd.classList.remove('show');
          vd.innerHTML="";
        } else {
          vd.innerHTML = efb_var.text.enterTheValueThisField;
          if(vd.classList.contains('show'))vd.classList.add('show');
        }
        if( el.checked == false && el.type =="checkbox") {
          const indx= sendBack_emsFormBuilder_pub.findIndex(x=>x!=null && x.hasOwnProperty('id_ob')==true && x.id_ob ==el.id);
          if(indx!=-1) {
            slice_sback(indx)
            if(ob.type=="payCheckbox") fun_total_pay_efb();
            if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic) fun_statement_logic_efb(el.id ,el.type);
            return ;
          }
         }
         const indx =valj_efb.findIndex(x=>x.id_ ==el.name);
         if(indx!=-1 && valj_efb[indx].type.includes('chl')!=false && el.checked == true){
            if(el.type=="checkbox"){
              const id = el.id +'_chl';
              document.getElementById(id).disabled=false;
            }
         }else if (indx!=-1 && valj_efb[indx].type.includes('chl')!=false && el.checked == false){
          const id = el.id +'_chl';
          document.getElementById(id).disabled=true;
          document.getElementById(id).value ="";
         }
         if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic) fun_statement_logic_efb(el.id ,el.type);
        break;
      case "select-one":
      case "select":
        value = sanitize_text_efb(el.value);
        vd =document.getElementById(`${ob.id_}_-message`)
        vd.classList.remove('show');
        vd.innerHTML="";
        el.className = colorBorderChangerEfb(el.className, "border-success");
        if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
          let v = el.options[el.selectedIndex].id;
          v = valueJson_ws.find(x => x.id_ == v && x.value == el.value);
          if (typeof v.price == "string") price_efb = v.price;
        }
        if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic) fun_statement_logic_efb(el.dataset.vid , el.type);
        if(el.dataset.hasOwnProperty('type') && el.dataset.type=="conturyList"){
          let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
              fun_check_link_state_efb(el.options[el.selectedIndex].dataset.iso , temp,form_id)
        }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="stateProvince"){
             let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
              iso_con = el.options[el.selectedIndex].dataset.isoc
              iso_state = el.options[el.selectedIndex].dataset.iso
        }
        break;
      case "range":
          value = sanitize_text_efb(el.value);
          vd = document.getElementById(`${ob.id_}_-message`);
          vd.classList.remove('show');
          vd.innerHTML="";
        break;
      case "email":
        if(el.value.length==0){ el_empty_value(id_); return;}
        state = valid_email_emsFormBuilder(el);
        value = state == true ? sanitize_text_efb(el.value) : '';
        break;
      case "tel":
        if(el.value.length==0){ el_empty_value(id_);}
        state = valid_phone_emsFormBuilder(el);
        value = state == true ? sanitize_text_efb(el.value) : '';
        break;
      case "password":
        state = valid_password_emsFormBuilder(el);
        value = state == true ? sanitize_text_efb(el.value) : '0';
        break;
      case "select-multiple":
        const parents = el.name;
        if (el.classList.contains('multiple-emsFormBuilder') == true) {
          for (let i = 0; i < el.children.length; i++) {
            value += el.children[i].value + ",";
          }
        }
        break;
      case "file":
        valid_file_emsFormBuilder(id_,'msg','',0);
        break;
      case "hidden":
        value = sanitize_text_efb(el.value);
        break;
      case undefined:
        let check = false;
        for (let ex of exportView_emsFormBuilder) {
          if (ex.id_ == el.id) {
            check = true;
            break;
          }
        }
        if (check == true) {
          ob = valueJson_ws.find(x => x.id_ === el.id);
          for (let o of select_options_emsFormBuilder) {
            value += o + `,`;
          }
        }
        break;
        default:
          vd=document.getElementById(`${ob.id_}_-message`);
          if(!ob) {vd.classList.remove('show');
          vd.innerHTML="";}
        break;
    }
    const form_id = el.dataset.hasOwnProperty('formid') ? el.dataset.formid : 0;
    if(state==false && value.length > 0)  if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,false,current_s_efb);
    if (value != "" || value.length > 0) {
      const type = ob.type;
      const id_ob = ob.type != "paySelect" ? el.id : el.options[el.selectedIndex].id;
      let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder,form_id:  form_id }];
      if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,true,current_s_efb);
      if (el.classList.contains('payefb')) {
        let q = valueJson_ws.find(x => x.id_ === el.id);
        let p ;
        if(ob.type =='prcfld'){
          p= Object.assign(o[0], {price: el.value});
        }else{
          p = price_efb.length > 0 ? { price: price } : { price: q.price }
        }
        Object.assign(o[0], p)

        fun_sendBack_emsFormBuilder(o[0]);
        fun_total_pay_efb(form_id)
      }else if(type.includes('chl')){
        const ch = el.id.includes('_chl')
        const qty = ch  ? document.getElementById(el.id).value :'';
        if(ch==false){
          Object.assign(o[0], {qty:qty});
        }else{
          el.classList.remove('bg-danger');
          ob.qty=fun_text_forbiden_convert_efb(qty);
          o[0]="";
        }

        fun_sendBack_emsFormBuilder(o[0]);
      }else if (o[0].type=="email"){

        fun_sendBack_emsFormBuilder(o[0]);
      }else {

        fun_sendBack_emsFormBuilder(o[0]);
      }
    }
}
offset_view_efb=()=>{
  let r = 800;
  if(document.getElementById('body_efb')){
    r= document.getElementById('body_efb').offsetWidth;
  }else if(document.getElementById('settingModalEfb-body') && document.getElementById('settingModalEfb-body').offsetWidth>0){
    r= document.getElementById('settingModalEfb-body').offsetWidth;
  }else if (document.getElementById('body_emsFormBuilder')){
    r= document.getElementById('body_emsFormBuilder').offsetWidth;
  }
  return Number(r);
}
get_row_sendback_by_id_efb=(id_)=>{
 return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_)
}
function fun_total_pay_efb(form_id) {
  let total = 0;
  if(valj_efb==undefined || valj_efb==null || valj_efb.length==0){
    if(Number(form_id)!=Number(form_ID_emsFormBuilder)) valj_efb = get_structure_by_form_id_efb(form_id);
    return 0;
  }
  updateTotal_efb = (i ,form_id) => {
     if(Number(form_id)!=Number(form_ID_emsFormBuilder)) valj_efb = get_structure_by_form_id_efb(form_id);
    for (const l of document.querySelectorAll(".totalpayEfb")) {
      l.innerHTML = Number(i).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
      if(l.classList.contains('paypal')){
        const paypal_btn = document.querySelectorAll(`.paypalEfb[data-formid="${form_id}"]`);
        if (paypal_btn.length) {
          Number(i) > 0 ? paypal_btn.forEach(p => p.classList.remove('disabled')) : paypal_btn.forEach(p => p.classList.add('disabled'));
        }
      }
    }
  }
  for (let r of sendBack_emsFormBuilder_pub) {
    if (r.hasOwnProperty('price') ) total += Math.abs(parseFloat(r.price));
  }
  setTimeout(() => { updateTotal_efb(total,form_id); }, 800);
  if(valj_efb[0].getway=="persiaPay" && typeof fun_total_pay_persiaPay_efn=="function"){ fun_total_pay_persiaPay_efn(total)}
  else if(valj_efb[0].getway=="persiaPay"){
  }else if(valj_efb[0].getway=="paypal" && typeof fun_total_pay_paypal_efn=="function"){ fun_total_pay_paypal_efn(total)}
}
fun_currency_no_convert_efb = (currency, number) => {
  return new Intl.NumberFormat('us', { style: 'currency', currency: currency }).format(number)
}
fun_disabled_all_pay_efb = () => {
  let type = '';
  if(valj_efb[0].getway!="persiaPay")document.getElementById('stripeCardSectionEfb').classList.add('d-none');
  for (let o of valj_efb) {
    if (o.hasOwnProperty('price')==true || (o.hasOwnProperty('type') && o.type=='prcfld')) {
      if (o.hasOwnProperty('parent')) {
        const p = valj_efb.findIndex(x => x.id_ == o.parent);
        if (p==-1) continue;
        if(valj_efb[p].hasOwnProperty('type')==false) continue;
        type = valj_efb[p].type.toLowerCase();
        if(type.includes('pay')==false) continue;
        let ov = document.querySelector(`[data-vid="${o.parent}"]`);
        ov.classList.remove('payefb');
        ov.classList.add('disabled');
        ov.disabled = true;
        if (type != "multiselect"  && type != "payMultiselect" && type != "paySelect") {
          const ob = valj_efb.filter(obj => {
            return obj.parent === o.parent
          })
          for (let o of ob) {
            ov = document.getElementById(o.id_);
            ov.classList.add('disabled');
            ov.classList.remove('payefb');
            ov.disabled = true;
          }
        }
      }else{
        let ov = document.querySelector(`[data-vid="${o.id_}"]`);
        ov.classList.add('disabled');
        ov.disabled = true;
        ov.classList.remove('payefb');
      }
    }
  }
}
add_ui_totalprice_efb = (rndm ,iVJ) => {
  return  `
  <!-- total Price -->
    <label class="efb totalpayEfb  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].classes.replace(`,`, ` `)}  mt-1"   data-id="${rndm}-el" id="${rndm}_">
     ${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })}
    </label>
  <!-- end total Price -->
  `
}

fun_get_links_from_string_Efb=(str , handler)=>{
  if(handler==false){
    let regex = /\[([^\]]+)\]\(([^)]+)\)/g;
    let match;
    let r =[]
    let state=false;
    while ((match = regex.exec(str)) !== null) {
      state=true
        let anchorText = match[1];
        let url = match[2];
        r.push({text:anchorText,url:url})
    }
    return [state,r]
  }else{

    str= str.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(_, anchorText, url) {
          state=true;
        return `<a href="${url}" target="_blank")>${anchorText}</a>`;
    });

    return str;
  }

}

async function fun_valj_efb_run(form_id){
  form_id = isNaN(form_id) ? form_id : parseInt(form_id);
  form_ID_emsFormBuilder = form_id;
  const r = valj_efb_new.find(x=>x.id ==form_id);
  valj_efb = r.form_structer;
  return  valj_efb ;
}

function maps_os_pro_efb(previewSate, pos, rndm, iVJ){
    return `
    <!--maps-->
    <div class="efb  ${previewSate == true ? pos[3] : 'col-md-12'} col-sm-12 maps-os" id='${rndm}-f'>

      </div>
      <!--maps end-->
    `;
}

fun_imgRadio_efb=(id ,link,row ,state=true)=>{
  const u = (url)=>{
    url = url.replace(/(http:@efb@)+/g, 'http://');
    url = url.replace(/(https:@efb@)+/g, 'https://');
    url = url.replace(/(@efb@)+/g, '/');
    return url;
   }

   let value = row.hasOwnProperty('value')  ? row.value : efb_var.text.newOption ?? '';

  let sub_value = row.hasOwnProperty('sub_value') ? row.sub_value : efb_var.text.sampleDescription ?? '';
     if(state==false){
    value = efb_var.text.newOption ;
    sub_value = efb_var.text.sampleDescription ;
   }
  link = link.includes('http')==false || link.length <5 ?  u(efb_var.images.head) : u(row.src);
  link = u(link);
  return `
    <label class="efb  " id="${id}_lab" for="${id}">
    <div class="efb card col-md-3 mx-0 my-1 w-100" style="">
    <img src="${link}" alt="${value}" style="width: 100%"  id="${id}_img">
    <div class="efb card-body">
        <h5 class="efb card-title text-dark" id="${id}_value">${value}</h5>
        <p class="efb card-text" id="${id}_value_sub">${sub_value}</p>
    </div>
    </div>
    </label>`;
}
