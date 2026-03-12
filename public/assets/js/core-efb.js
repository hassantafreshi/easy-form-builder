let exportView_emsFormBuilder = [];
let stepsCount;
let sessionPub_emsFormBuilder = "reciveFromClient"
let stepNames_emsFormBuilder = [`t`, ``, ``];
let currentTab_emsFormBuilder = 0;
let multiSelectElemnets_emsFormBuilder = [];
let formNameEfb = ""
let files_emsFormBuilder = [];
let addons_emsFormBuilder =""
let recaptcha_emsFormBuilder = [];

if (typeof efb_var !== 'undefined') {
}
let poster_emsFormBuilder = '';
const fileSizeLimite_emsFormBuilder = 8300000;
let select_options_emsFormBuilder = [];
let form_type_emsFormBuilder = 'form';
let valueJson_ws = []
let motus_efb = {};
let g_timeout_efb = 100
let price_efb ="";
let sendback_efb_state= [];
let valj_efb_new = [];
let form_ID_emsFormBuilder = 0;
if (window.React || window.react) {
  reactJs_active_efb = true;
}
if (typeof(ajax_object_efm)=='object' && ajax_object_efm.hasOwnProperty('ajax_value') && typeof ajax_object_efm.ajax_value == "string") {
  g_timeout_efb = (g_timeout_efb, ajax_object_efm.ajax_value.match(/id_/g) || []).length;
  g_timeout_efb = g_timeout_efb * calPLenEfb(g_timeout_efb);
  ajax_object_efm.ajax_value_forms.forEach(form => {
    form.form_structer = JSON.parse(form.form_structer.replace(/\\/g, ''));
  });
  valj_efb_new = ajax_object_efm.ajax_value_forms
}
g_timeout_efb = typeof ajax_object_efm == "object" && typeof ajax_object_efm.ajax_value == "string" ? g_timeout_efb : 1100;
function fun_render_view_efb(val, check) {
  var url = new URL(window.location);
  history.replaceState("EFBstep-1",null,url);
  exportView_emsFormBuilder = [];
  valueJson_ws = JSON.parse(val.replace(/[\\]/g, ''));
  valj_efb = valueJson_ws;
  fun_gets_url_efb();
  state_efb = "run";
}

function deepFreeze_efb_pub(obj) {
  if (typeof obj !== "object" || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
      if (typeof obj[key] === "object" && obj[key] !== null) {
          deepFreeze_efb_pub(obj[key]);
      }
  });
  return Object.freeze(obj);
}

function check_body_efb_timer (){
  g_timeout_efb -=10;
  if((document.getElementById('body_efb')==null && document.getElementById('body_tracker_emsFormBuilder')==null) && g_timeout_efb>10){
    setTimeout(() => {
      check_body_efb_timer();
    }, 800);
  }else{

    fun_efb_run();
  }
}
  function fun_efb_run() {
    if (window.efb_initialized) return;

    window.efb_initialized = true;

    g_timeout_efb = 100;

    const docBodyEfb = document.querySelector('.body_efb');
    if (typeof window.jQuery != "function" || typeof jQuery != "function") {
      let msg = `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
        <strong>${ajax_object_efm.text.alert}</strong> ${ajax_object_efm.text.jqinl}
      </div>`;
      if(docBodyEfb){
        for (const el of docBodyEfb) {
          el.innerHTML = msg;
        }

        }
      if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML = msg;
      return;
    }

    jQuery(() => {
      if (typeof ajax_object_efm === 'undefined' || !ajax_object_efm.ajax_value) return;

      if (!docBodyEfb && !document.getElementById('body_tracker_emsFormBuilder')) {
        check_body_efb_timer();
      }

      poster_emsFormBuilder = deepFreeze_efb_pub(ajax_object_efm.poster);
      ajax_object_efm.text = deepFreeze_efb_pub(ajax_object_efm.text);
      lan_name_emsFormBuilder = ajax_object_efm.language.slice(0, 2);
      pro_efb = ajax_object_efm.pro == '1' ? true : false;
      page_state_efb = "public";

      setting_emsFormBuilder=typeof ajax_object_efm.form_settingJSON=='string' ? JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, '')) : ajax_object_efm.form_settingJSON;
      efb_var = ajax_object_efm;
      efb_var.text = deepFreeze_efb_pub(efb_var.text);

      if (ajax_object_efm.cache_plugins && ajax_object_efm.cache_plugins !== '0') {
        try {
          const _cacheList = typeof ajax_object_efm.cache_plugins === 'string'
            ? JSON.parse(ajax_object_efm.cache_plugins)
            : ajax_object_efm.cache_plugins;

          if (Array.isArray(_cacheList) && _cacheList.length > 0) {
            const _t = ajax_object_efm.text;
            const _docUrl = 'https://whitestudio.team/document/exclude-easy-form-builder-froms-cache/';

            _cacheList.forEach(function(p) {
              let _line = (_t.cacheWarnPlugin || 'Plugin') + ': ' + p.name;
              if (p.version) {
                _line += '  |  ' + (_t.cacheWarnVersion || 'Version') + ': ' + p.version;
              }

              const _msg = '\u26A0\uFE0F ' + (_t.cacheWarnTitle || 'Cache Plugin Detected') + '\n'
                + (_t.cacheWarnMsg || 'The following cache plugins may interfere with form functionality. If you experience issues, please review the documentation.') + '\n\n'
                + _line + '\n\n'
                + (_t.cacheWarnDoc || 'Read more about cache compatibility') + ':\n' + _docUrl;

              if (typeof EFB_ERROR_PANEL !== 'undefined' && typeof EFB_ERROR_PANEL.log === 'function') {
                EFB_ERROR_PANEL.log(_msg, { source: 'cache-warning', type: 'notice', name: 'Easy Form Builder', captureStack: false });
              } else {
              }
            });
          }
        } catch (_e) {
        }
      }

      if (ajax_object_efm.state != 'tracker') {
        const ajax_value = typeof (ajax_object_efm.ajax_value) == "string" ? JSON.parse(ajax_object_efm.ajax_value.replace(/[\\]/g, '')) : ajax_object_efm.ajax_value;
        if (ajax_object_efm.form_setting && ajax_object_efm.form_setting.length > 0 && ajax_object_efm.form_setting !== ajax_object_efm.text.settingsNfound) {
          form_type_emsFormBuilder = ajax_object_efm.type;
          const vs = setting_emsFormBuilder;
          addons_emsFormBuilder = vs.addons;

          if (ajax_object_efm.type !== "userIsLogin") {
            if (Number(ajax_value[0]?.captcha) === 1) {
              if (vs.siteKey.length < 3) {
                document.getElementById('body_efb').innerHTML = alarm_emsFormBuilder(ajax_object_efm.text.formIsNotShown);
                return;
              }
              if(sitekye_emsFormBuilder==2)alert(c_r_efb);
              sitekye_emsFormBuilder = vs.siteKey;
            } else {
              sitekye_emsFormBuilder = "";
            }
          }
        }
      }

      if (!ajax_object_efm.hasOwnProperty('ajax_value_forms')) {
        if (ajax_object_efm.state !== 'settingError') {
          switch (ajax_object_efm.state) {
            case 'form':
              fun_render_view_efb(ajax_object_efm.ajax_value, 1);
              break;
            case 'tracker':
              fun_tracking_show_emsFormBuilder();
              break;
            case 'userIsLogin':
              document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(ajax_object_efm.ajax_value);
              break;
            default:
              fun_show_alert_setting_emsFormBuilder();
          }
        } else {
          fun_show_alert_setting_emsFormBuilder();
        }
      }
    });

    (function () {
      var exportObj = {
        init: function (element, data, selectCb, options) {
          createMultiselect(element, data, selectCb, options);
        }
      };
      motus_efb.ElementMultiselect = exportObj;
    })();
  }

setTimeout( fun_efb_run, g_timeout_efb)

async function createStepsOfPublic() {
 state_efb = "run";
 efb_var = ajax_object_efm;
 setting_emsFormBuilder = typeof ajax_object_efm.form_setting == "string" ? JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, '')) : ajax_object_efm.form_setting;
  for (let el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    let form_id = el.dataset.formid  || 0;
    if (el.tagName == "OPTION" || el.tagName=='P' || el.tagName=='A' || ( el.tagName=='INPUT' && el.type=='hidden')) continue;
    let price = '';
    let el_type =''
    const valj_efb_ =get_structure_by_form_id_efb(form_id);
    valj_efb = valj_efb_;
    form_ID_emsFormBuilder = parseInt(form_id);
    const id = el.dataset.vid ?? '';
    const classes = el.classList;
    let handler_state=true;
    const form_type = valj_efb_[0].hasOwnProperty('type') ? valj_efb_[0].type : 'form';
    let o =[{ id_: id, name: el.name, id_ob: el.id, amount: 0, type: el.type, value: el.value, session: sessionPub_emsFormBuilder, form_id: form_id }];
    if ('type' in el) {
      if( ( el.type=="checkbox" || el.type=="radio" ) && valj_efb_[0].type == "payment" && classes.contains('payefb')){
        let indx = valj_efb.findIndex(x => x.id_ === id);
        const row = valj_efb[indx];
        const parent_id= row.hasOwnProperty('parent_id') ? row.parent_id : -1;
        if(parent_id!=-1){
          let indx_parent = valj_efb.findIndex(x => x.id_ === parent_id);
          const row_parent = valj_efb[indx_parent];
          if (row.value.length > 0 || el.checked == true) {
            price = row_parent.hasOwnProperty('price') ? row_parent.price : 0;

          }
        }
        fun_total_pay_efb(form_id)
      }else if (el.type != "submit") {
        switch (el.type) {
          case "text":
          case 'email':
          case 'number':
          case 'tel':
          case 'password':
          case 'textarea':
          case 'image':
          case 'url':
          case 'range':
          case 'color':
            if(el.value && el.value.length>0){
              if(el.type!="range" && el.type!="color"){
                handle_change_event_efb_v4(el,form_id);
              }else{
                let indx = valj_efb.findIndex(x => x.id_ === id);
                if(valj_efb[indx].hasOwnProperty('value') && valj_efb[indx].value==""){
                  break;
                }
                handle_change_event_efb_v4(el,form_id);
              }
            }
            if (classes.contains("pdpF2")) {
              call_fun_jalali_datepicker_efb_v4();
            }else if (classes.contains("hijri-picker")) {
              call_fun_hijri_datapicker_efb_v4();
              jQuery("#"+el.id).on('dp.change', function (arg) {
                if (!arg.date) {
                    jQuery("#selected-date").html('');
                    return;
                };
                let date = arg.date;
                handle_change_event_efb_v4(el,form_id);
              });
            }else if (classes.contains("intlPhone") && classes.contains("d-none")==false) {
              const indx = valj_efb_.findIndex(x => x.id_ === id);
              valj_efb=valj_efb_;
              load_intlTelInput_efb(id,indx);

            }
            if(el.type === 'tel') {
              el.setAttribute('inputmode', 'tel');
              el.addEventListener('input', function() {
                var cursorPos = this.selectionStart;
                var oldLen = this.value.length;
                var cleaned = this.value.replace(/[^0-9+\-\s().]/g, '');
                var firstPlus = cleaned.indexOf('+');
                if (firstPlus > 0) { cleaned = cleaned.replace(/\+/g, ''); }
                else if (firstPlus === 0) { cleaned = '+' + cleaned.slice(1).replace(/\+/g, ''); }
                if (cleaned !== this.value) {
                  this.value = cleaned;
                  var newLen = this.value.length;
                  this.setSelectionRange(cursorPos - (oldLen - newLen), cursorPos - (oldLen - newLen));
                }
              });
              el.addEventListener('paste', function(e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text') || '';
                var cleaned = paste.replace(/[^0-9+\-\s().]/g, '');
                var firstPlus = cleaned.indexOf('+');
                if (firstPlus > 0) { cleaned = cleaned.replace(/\+/g, ''); }
                else if (firstPlus === 0) { cleaned = '+' + cleaned.slice(1).replace(/\+/g, ''); }
                var start = this.selectionStart;
                var end = this.selectionEnd;
                var before = this.value.substring(0, start);
                var after = this.value.substring(end);
                this.value = before + cleaned + after;
                var newPos = start + cleaned.length;
                this.setSelectionRange(newPos, newPos);
                handle_change_event_efb_v4(this, this.dataset.formid || 0);
              });
              el.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey || e.altKey) return;
                if (e.key && e.key.length === 1) {
                  if (!/[0-9+\-\s().]/.test(e.key)) { e.preventDefault(); }
                  if (e.key === '+' && (this.selectionStart !== 0 || this.value.indexOf('+') !== -1)) { e.preventDefault(); }
                }
              });
            }
          break;
          case "file":
            const ob = valj_efb_.find(x => x.id_ === id);
            if(((ob.hasOwnProperty("disabled") && ob.disabled!=true ) || ob.hasOwnProperty("disabled")==false )&&
            ((ob.hasOwnProperty('hidden') && ob.hidden==true) || ob.hasOwnProperty('hidden')==false))

            files_emsFormBuilder.push({ id_: ob.id_, value: "@file@", state: 0, url: "", type: "file",state:0, name: ob.name, session: sessionPub_emsFormBuilder, form_id: form_id });

            if (el.classList.contains('dadfile')){
              const indx = valj_efb_.findIndex(x => x.id_ === id);
              set_dadfile_fun_efb(id,indx,form_id)
              handler_state=false;
            }
          break;
          case "hidden":
            el_type = el.dataset.type ?? '';
            if(el_type=='esign'){
              const ob = valj_efb_.find(x => x.id_ === id);
              const disabled = ob.hasOwnProperty("disabled") && ob.disabled==true ? true : false;
              fun_event_esign_efb(id,form_id,disabled,ob);
            }

            break;
          case 'checkbox':
          case 'radio':
            document.getElementById(el.id).addEventListener("change", (e) => {
                if(el.dataset.tag && el.dataset.tag.includes("chl")!=false){
                }else if(classes.contains('payefb') && valj_efb_[0].type == "form"){
                }
              })
              if(el.hasOwnProperty('checked') || el.checked==true){
                handle_change_event_efb_v4(el,form_id);
              }
          break;
          case 'select-one':
            const selected_option_tag = el.querySelector('option:checked');
            if (selected_option_tag && selected_option_tag.id !== 'efbNotingSelected') {
              handle_change_event_efb_v4(el,form_id);
            }

          break;

          }
      }else if (el.type == "submit") {
        el.addEventListener("click", (e) => {
          const id_ = el.dataset.vid
          const form_id = el.dataset.formid || -1;
          const ob = valueJson_ws.find(x => x.id_ === id_);
          let o = [{ id_: id_, name: ob.name, id_ob: el.id, amount: ob.amount, type: el.type, value: el.value, session: sessionPub_emsFormBuilder, form_id: form_id }];
          if (valj_efb_[0].type == "payment" && classes.contains('payefb')) {
            let q = valueJson_ws.find(x => x.id_ === el.id);
            const p = price_efb.length > 0 ? { price: price } : { price: q.price }
            Object.assign(o[0], p)
            fun_sendBack_emsFormBuilder(o[0]);
            fun_total_pay_efb(form_id)
          } else {
            fun_sendBack_emsFormBuilder(o[0]);
          }
        });
      }
    }else if (el.classList.contains('maps-efb')){

        const c = valj_efb_.find(x => x.id_ === id);
        const parents = el.parentNode
        if(!c.hasOwnProperty('formid'))Object.assign(c , {'formid':parents.dataset.formid})
    }else if(form_type=="payment"){
      const getway = valj_efb_[0].hasOwnProperty('getway') ? valj_efb_[0].getway : '';
      if (getway == "stripe" && typeof post_api_stripe_apay_efb =="function") post_api_stripe_apay_efb(form_id);
    }
    if(handler_state){
      el.addEventListener("change", async(e) => {
        await handle_change_event_efb_v4(el,form_id);
      });

    }

  }

  for (let switchEl of document.querySelectorAll('.btn-toggle[onclick*="fun_switch_efb"]')) {
    const sw_form_id = switchEl.dataset.formid || 0;
    const sw_vid = switchEl.dataset.vid;
    if (!sw_vid) continue;
    try {
      const sw_valj = get_structure_by_form_id_efb(sw_form_id);
      const sw_v = sw_valj.find(x => x.id_ == sw_vid);
      if (!sw_v || sw_v.value == undefined || sw_v.value == null || sw_v.value == "") continue;
      const sw_value = switchEl.classList.contains('active') ? "1" : "0";
      const sw_ob = { id_: sw_v.id_, name: sw_v.name, amount: sw_v.amount, type: sw_v.type, value: sw_value, session: sessionPub_emsFormBuilder, form_id: sw_form_id };
      fun_sendBack_emsFormBuilder(sw_ob);
    } catch(e) {  }
  }

}
async function fun_sendBack_emsFormBuilder(ob) {
  const form_id = ob.form_id || -1;
  if(typeof ob=='string' || ob.hasOwnProperty('value')==false ){return}
  remove_ttmsg_efb(ob.id_)
  if(ob.hasOwnProperty('value') && typeof(ob.value)!='number' && typeof(ob.value)!='object' && typeof(ob.value)!='string') {ob.value=fun_text_forbiden_convert_efb(ob.value);
  }else if(ob.hasOwnProperty('value') && ( typeof(ob.value)=='object') &&  ob.type=="maps" ){
    ob.value=ob.value;
  }
  if (sendBack_emsFormBuilder_pub.length>0) {
    let indx = get_row_sendback_by_id_efb_v4(ob.id_,form_id);
    if (indx != -1 && ob.type != "switch" && (sendBack_emsFormBuilder_pub[indx].type == "checkbox" || sendBack_emsFormBuilder_pub[indx].type == "payCheckbox" || sendBack_emsFormBuilder_pub[indx].type == "multiselect" || sendBack_emsFormBuilder_pub[indx].type == "payMultiselect" || sendBack_emsFormBuilder_pub[indx].type == "chlCheckBox")) {
      indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_ && x.value == ob.value);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub.splice(indx, 1);
    }
    else if(indx != -1 && ob.value == "@file@" ){
      sendBack_emsFormBuilder_pub[indx]=ob;
    }else if(ob.type == "r_matrix"){
      indx = sendBack_emsFormBuilder_pub.findIndex( x => x!=null && x.hasOwnProperty('id_ob') && x.id_ob === ob.id_ob);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub[indx]=ob;
    }else if(indx != -1 && ob.type == "mobile" ){
      ob.value= ob.value.replace(/^(\+\d+)\1/, '$1');
    } else {
      if (indx == -1) {
        sendBack_emsFormBuilder_pub.push(ob)
      } else {
        if (typeof ob.price != "string") {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
        } else {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
          sendBack_emsFormBuilder_pub[indx].price = ob.price;
          if(ob.type == "payRadio"){
            sendBack_emsFormBuilder_pub[indx].id_ob = ob.id_ob;
          }

        }
      }
    }
  } else {
    sendBack_emsFormBuilder_pub.push(ob);
  }
  localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
  localStorage.setItem('formId', efb_var.id)
}
function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
      <div><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></div>
      <strong>${ajax_object_efm.text.alert} </strong>${val}
    </div>`
}
 async function endMessage_emsFormBuilder_view(current_step,form_id) {
  let valj_efb = get_structure_by_form_id_efb(form_id);

  fun_check_upload_files_complate_efb = (form_id) => {
    let checkFile_uploaded = 0;
    let countFile_notUloaded = 0;
    for (let file of files_emsFormBuilder) {
      if(file.form_id!=form_id)continue;

      if ( file.state == 2) {
        checkFile_uploaded += 1;
      }else if (file.state == 1) {
        countFile_notUloaded += 1;
      }

    }
    if(countFile_notUloaded>0){
      setTimeout(() => {
        fun_check_upload_files_complate_efb(form_id)
      }, countFile_notUloaded*1000);
    }else{
      actionSendData_emsFormBuilder(form_id);
    }

  }
  let counter = 0;
  const btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? `logic_fun_prev_send(${form_id})`:`fun_prev_send(${form_id})`;
  const stepMax = current_step + 1;
  let notfilled = []
  for (let i = 1; i <= stepMax; i++) {
    if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('step')  && x.step == i))) notfilled.push(i);
  }
  const corner = valj_efb[0].hasOwnProperty('corner') ?  valj_efb[0].corner :'efb-square';
  let countRequired = 0;
  let valueExistsRequired = 0;
  for (let el of exportView_emsFormBuilder) {
    if (el.required == true) {
      const id = el.id_;
      countRequired += 1;
      if (-1 == (get_row_sendback_by_id_efb_v4(id,form_id))) valueExistsRequired += 1;
    }
  }
  const id_body ='body_efb_'+form_id
  const body_efb = document.getElementById(id_body);
  const efb_final_step =body_efb.querySelector('#efb-final-step');
  if (countRequired != valueExistsRequired && sendBack_emsFormBuilder_pub.length < 1) {
    let str = ""
    currentTab_emsFormBuilder = 0;
    efb_final_step.innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb text-center">${ajax_object_efm.text.error}</h3> <span class="efb mb-2  text-center">${ajax_object_efm.text.pleaseMakeSureAllFields}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
  } else {
    let checkFile = 0;
    for (let file of files_emsFormBuilder) {
      if (files_emsFormBuilder.length > 0 && file.state == 1) {
        checkFile += 1;
      } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
        checkFile = -100;
        efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb font-weight-bold  text-center">File Error</h3> <span class="efb font-weight-bold  text-center">${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
         <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
        return;
      }
    }
    if (checkFile == 0) {
      if (files_emsFormBuilder.length > 0) {
        for (const file of files_emsFormBuilder) {
          if (get_row_sendback_by_id_efb_v4(file.id_,form_id) == -1) {
             sendBack_emsFormBuilder_pub.push(file);
             localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub)); }
        }
      }
      const state = await validation_before_send_efb(form_id);
      if ( state == true){ actionSendData_emsFormBuilder(form_id)
      }
    } else {
        let checkFile = 0;
        for (let file of files_emsFormBuilder) {
          if(file.form_id!=form_id)continue;

          if (files_emsFormBuilder.length > 0 && file.state == 1) {
            checkFile += 1;
          } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
            checkFile = -100;
            efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb fs-4  text-center">File Error</h3> <span class="efb fs-6  text-center">${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
               <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
            return;
          }
        }
        if (checkFile == 0) {
          for (const file of files_emsFormBuilder) {
            sendBack_emsFormBuilder_pub.push(file);
            localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
          }
          const state = await validation_before_send_efb(form_id);
          if (state == true){
            actionSendData_emsFormBuilder(form_id);
          }

        }else{
           fun_check_upload_files_complate_efb(form_id);
        }

    }
  }
}
async function actionSendData_emsFormBuilder(form_id=0) {
  let sendback = [];
  if(form_type_emsFormBuilder =='recovery'){
    sendback = sendBack_emsFormBuilder_pub;
  }else{
    sendback = sendBack_emsFormBuilder_pub.filter(x=>Number(x.form_id)==Number(form_id));
  }

  const vj =  fun_sid_efb(form_id);
  let formNameEfb = vj.formName;
  let recaptcha_emsFormBuilder_row =''
  if (ajax_object_efm.type == "userIsLogin") return 0;
  if (form_type_emsFormBuilder != 'login'){
     localStorage.setItem('sendback', JSON.stringify(sendback));
     if(form_type_emsFormBuilder =='recovery'){
      vj.type ='recovery';
     }
     sendBack_emsFormBuilder_pub = [];
  }
  if( vj.captcah){
    const indx = sendback.findIndex(x => x.id_ == 'captcha_v2');

    if (indx != -1) {
      recaptcha_emsFormBuilder_row = sendback[indx].value;
      sendback.splice(indx, 1);
    }
   }
  if (!navigator.onLine) {
    await response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.offlineMSend } },form_id);
    return;
  }
  form_type_emsFormBuilder = valj_efb.length >2 ? valj_efb[0].type : form_type_emsFormBuilder
  let  data = {
      action: "get_form_Emsfb",
      value: JSON.stringify(sendback),
      name: formNameEfb,
      id: form_id,
      valid: recaptcha_emsFormBuilder_row,
      type:  vj.type,
      url:location.href.split('?')[0],
      sid:vj.sid ,
      page_id: ajax_object_efm.page_id
    };
    if(vj.type=="payment" ){
      if(valj_efb[0].getway=="persiaPay"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          payid: sessionStorage.getItem("payId"),
          id: sessionStorage.getItem("id"),
          valid: recaptcha_emsFormBuilder_row,
          type:  vj.type,
          payment: 'persiaPay',
          auth:get_authority_efb,
          url:location.href.split('?')[0],
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      }else if(valj_efb[0].getway=="stripe"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          id: form_id,
          payid: efb_var.payId,
          valid: recaptcha_emsFormBuilder_row ,
          type: vj.type,
          payment: 'stripe',
          url:location.href.split('?')[0],
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      } else if (valj_efb[0].getway=="paypal"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          id: form_id,
          payid: efb_var.payId,
          valid: recaptcha_emsFormBuilder,
          type: form_type_emsFormBuilder,
          payment: 'paypal',
          url:location.href.split('?')[0],
          sid:efb_var.sid,
          page_id: ajax_object_efm.page_id
        };
      }
    }
    post_api_forms_efb(data,form_id);
}
function valid_email_emsFormBuilder(el) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterTheEmail}','',10,'danger');"></div>` : ajax_object_efm.text.enterTheEmail;
  let check = 0;
  const format =/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  check += el.value.match(format) ? 0 : 1;
  const form_id = el.dataset.formid || 0;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${el.id}-message`).classList.add('unpx');
    }
    document.getElementById(`${el.id}-message`).innerHTML = msg;
    if(document.getElementById(`${el.id}-message`).classList.contains('show')==false)document.getElementById(`${el.id}-message`).classList.add('show');
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    document.getElementById(`${el.id}-message`).classList.remove('show');
    document.getElementById(`${el.id}-message`).innerHTML="";
  }
  return check > 0 ? false : true
}
function valid_password_emsFormBuilder(el) {
  let check = 0;
  const id = el.id;
  let offsetw = offset_view_efb();
  const form_id = el.dataset.formid || 0;
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterThePassword}','',10,'danger');"></div>` : efb_var.text.enterThePassword;
  if (el.value.length < 3) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${id}-message`).classList.add('unpx');
    }
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
    document.getElementById(`${id}-message`).innerHTML = msg;
    if(document.getElementById(`${el.id}-message`).classList.contains('show')==false)document.getElementById(`${el.id}-message`).classList.add('show');
    return false;
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    document.getElementById(`${id}-message`).innerHTML = ""
    document.getElementById(`${id}-message`).classList.remove('show');
    return true;
  }
}
function valid_phone_emsFormBuilder(el) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterThePhones}','',10,'danger');"></div>` : ajax_object_efm.text.enterThePhones;
  let check = 0;
  var val = el.value.replace(/\s+/g, ' ').trim();
  var formatChars = /^[0-9+\-\s().]+$/;
  var digitCount = (val.match(/[0-9]/g) || []).length;
  var plusPos = val.indexOf('+');
  var plusCount = (val.match(/\+/g) || []).length;
  var consecutiveSpecial = /[\-\s().]{3,}/.test(val);
  var openParen = (val.match(/\(/g) || []).length;
  var closeParen = (val.match(/\)/g) || []).length;
  if (!formatChars.test(val) || digitCount < 7 || digitCount > 15 || val.length > 25 || plusCount > 1 || (plusCount === 1 && plusPos !== 0) || consecutiveSpecial || openParen !== closeParen) {
    check = 1;
  }
  const id = el.id;
  const form_id = el.dataset.formid || 0;
  let msg_el = document.getElementById(`${id}-message`);
  if (check >0) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      msg_el.classList.add('unpx');
    }
    msg_el.innerHTML = msg;
    if(msg_el.classList.contains('show')==false) msg_el.classList.add('show');
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    msg_el.innerHTML = ""
    msg_el.classList.remove('show');
  }
  return check > 0 ? false : true
}
function valid_file_emsFormBuilder(id,tp,filed,form_id) {
  let valj_efb = get_structure_by_form_id_efb(form_id);
  let msgEl = document.getElementById(`${id}_-message`);
  msgEl.innerHTML = "";
  msgEl.classList.remove('show');
  document.getElementById(`${id}_`).classList.remove('border-danger');
  let file = ''
  if (true) {
    const f = valj_efb.find(x => x.id_ === id);
    file = f.file && f.file.length > 3 ? f.file : 'Zip';
    file = file.toLocaleLowerCase();
  }
  let check = 0;
  let rtrn = false;
  let fileName = ''
  const i = `${id}_`;
  let message = "";
  let file_size = 8*1024*1024;
  const indx = valj_efb.findIndex(x => x.id_ === id);
  let val_in = valj_efb[indx];
    if(val_in.hasOwnProperty('max_fsize') && val_in.max_fsize.length>0){
      file_size = Number(val_in.max_fsize) * 1024 * 1024;
    }
    const el = document.getElementById(i);
    if(filed==''){
      if (el.files[0] && el.files[0].size < file_size) {
        const filetype = el.files[0].type.length > 1 && file!='customize'  ? el.files[0].type : el.files[0].name.slice(el.files[0].name.lastIndexOf(".") + 1)
        const r = validExtensions_efb_fun(file, filetype,indx)
        if (r == true) {
          check = +1;
        }
        filed = el.files;
      }
    }else{
      if (filed && filed.size < file_size) {
        const filetype = filed.type.length > 1 && file!='customize'  ? filed.type : filed.name.slice(filed.name.lastIndexOf(".") + 1)
        const r = validExtensions_efb_fun(file, filetype,indx)
        if (r == true) {
          check = +1;
        }
      }
      let fi = filed ;
      filed =[];
      filed.push(fi);
    }
    if (check > 0) {
      msgEl.innerHTML = "";
      fun_upload_file_api_emsFormBuilder(id, filed[0].type,tp,filed[0]);
      rtrn = true;
    } else {
      const f_s_l = val_in.hasOwnProperty('max_fsize') && val_in.max_fsize.length>0 ? val_in.max_fsize : 8;
      const m =ajax_object_efm.text.pleaseUploadA.replace('NN', efb_var.text[val_in.file]);
      const size_m = ajax_object_efm.text.fileSizeIsTooLarge.replace('NN', f_s_l);
      if (filed[0] && message.length < 2){ message = filed[0].size < file_size ? m : size_m;}
      else if(filed.length==0){ message =size_m;}
      const newClass = colorTextChangerEfb(msgEl.className, "text-danger");
      newClass!=false ? msgEl.className=newClass:0;
      msgEl.innerHTML = message;
      if(!msgEl.classList.contains('show'))msgEl.classList.add('show');
      rtrn = false;
    }
    return rtrn;
}
function fun_show_alert_setting_emsFormBuilder() {
  const m = `<div class="efb alert alert-danger" role="alert"> <h2 class="efb font-weight-bold">
            ${ajax_object_efm.text.error}</br>
            ${ajax_object_efm.text.formIsNotShown}</br>
            <a href="https://www.youtube.com/embed/JI7RojBgU_o"  target="_blank" class="efb font-weight-normal">${ajax_object_efm.text.pleaseWatchTutorial}</a> </h2> </div>`
  if (document.getElementById('body_emsFormBuilder')) {
    document.getElementById('body_emsFormBuilder').innerHTML = m;
  } else if (document.getElementById('body_tracker_emsFormBuilder')) {
    document.getElementById('body_tracker_emsFormBuilder').innerHTML = m;
  } else {
    window.alert(`${ajax_object_efm.text.error} ${ajax_object_efm.text.formIsNotShown}`)
  }
}
async function validation_before_send_efb(form_id) {
  let valj_efb
  if (form_id){
    valj_efb = get_structure_by_form_id_efb(form_id);
  }else{
    valj_efb = valj_efb;
  }
  const btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? `logic_fun_prev_send(${form_id})`:`fun_prev_send(${form_id})`;
  const count = [0, 0]
  let fill = 0;
  let require = 0;
  const id_body = form_id == 0 ? 'body_efb' : `body_efb_${form_id}`;
  for (const v of valj_efb) {
    require += v.required == true && v.type !== "file" ? 1 : 0;
    if (v.type == "file") {
      if (document.getElementById(`${v.id_}_`).files[0] == undefined && v.required == true) {
        fill -= 1;
      }
    }
  }
  let count_ = 0;
  for (const row of sendBack_emsFormBuilder_pub) {
    count_ += 1;
    if(row==null || typeof(row)!='object' || row.hasOwnProperty('value')==false ) {
      count_ -= 1;
      sendBack_emsFormBuilder_pub.splice(count_,1);
      continue;
    }
    count[0] += 1;
    if (row.value == "@file@") {
      const indx = valj_efb.findIndex(x => x.id_ == row.id_);
      if (indx != -1) {
        if(valj_efb[indx].hasOwnProperty("disabled") && valj_efb[indx].disabled==true &&
           ((valj_efb[indx].hasOwnProperty('hidden') && valj_efb[indx].hidden==false) || valj_efb[indx].hasOwnProperty('hidden')==false)
          ){
            const i = get_row_sendback_by_id_efb_v4(valj_efb[indx].id_,valj_efb[indx].form_id);

            sendBack_emsFormBuilder_pub.splice(i,1);
            count[0] -= 1;
            continue
          }
        if (row.url.length > 5) {
          fill += valj_efb[indx].required == true ? 1 : 0;
          count[1] += 1;
        }
      }
    } else if (row.type != "@file@" && row.type != "payment") {
      const indx = valj_efb.findIndex(x => x.id_ == row.id_);
      if(indx!=-1){
        if ( (valj_efb[indx].type == "multiselect" || valj_efb[indx].type == "option" || valj_efb[indx].type == "Select"
        || valj_efb[indx].type == "payMultiselect" || valj_efb[indx].type == "paySelect")) {
        const exists = valj_efb[indx].type == "multiselect" || valj_efb[indx].type == "payMultiselect" ? valj_efb.findIndex(x => x.parent == valj_efb[indx].id_) : valj_efb.findIndex(x => x.parents == valj_efb[indx].id_);
        fill += valj_efb[indx].required == true && exists > -1 ? 1 : 0;
      }else if(valj_efb[indx].type == "chlCheckBox"){
          const exists = valj_efb.findIndex(x => x.parents == valj_efb[indx].id_)
          fill += valj_efb[indx].required == true && exists > -1 ? 1 : 0;
        }else {
          fill += valj_efb[indx].required == true ? 1 : 0;
        }
      if (row.value.length > 0) count[1] += 1;
      }
    } else {
      if (row.value.length > 0) count[1] += 1;
    }
  }
  require = require > fill ? 1 : 0;
  if (((count[1] == 0 && count[0] != 0) || (count[0] == 0 && count[1] == 0) || require == 1) && ( (valj_efb[0].hasOwnProperty("logic")== true || valj_efb[0].hasOwnProperty("logic")==false) && valj_efb.logic==false )) {
    const body_efb = document.getElementById(id_body);
    const efb_final_step = body_efb.getElementsByClassName('efb-final-step');
    efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center fs-2 efb"></i></h1><h3 class="efb fs-3 efb text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 fs-5 efb text-muted"> ${require != 1 ? ajax_object_efm.text.PleaseFillForm : ajax_object_efm.text.pleaseFillInRequiredFields} </br></span>
     <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner:'efb-square'}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    smoothy_scroll_postion_efb(id_body)
    for (const v of valj_efb) {
      if (v.type != 'file' && v.type != 'dadfile' && v.type != 'checkbox' && v.type != 'radiobutton' && v.type != 'option' && v.type != 'multiselect' && v.type != 'select' && v.type != 'payMultiselect' && v.type != 'paySelect' && v.type != 'payRadio' && v.type != 'payCheckbox' && v.type != 'chlCheckBox') {
        (v.id_ && document.getElementById(v.id_).value.length < 5) ? document.getElementById(`${v.id_}-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField : 0
        return false;
      }
    }
  } else {
    return true;
  }
}
function show_user_profile_emsFormBuilder(ob) {

  return `<div class="efb mt-5"><div class="efb card-block text-center text-dark ">
              <div class="efb mb-3 d-flex justify-content-center"> <img src="${ob.user_image}" class="efb userProfileImageEFB" alt="${ob.display_name}"> </div>
              <h6 class="efb  fs-5 mb-1 d-flex justify-content-center text-dark">${ob.display_name}</h6> <p class="efb  fs-6">${ob.user_login}</p>
              <button type="button"  class="efb btn fs-5 btn-lg btn-danger efb mt-1 " onclick="fun_logout_efb()">${ajax_object_efm.text.logout}</button>
          </div> </div>`
}
function fun_logout_efb(form_id) {
  const id_body = form_id == 0 ? 'body_efb' : `body_efb_${form_id}`;
  document.getElementById(id_body).innerHTML = loading_messge_efb();
  form_type_emsFormBuilder = "logout";
  formNameEfb = "logout";
  ajax_object_efm.type = "logout";
  sendBack_emsFormBuilder_pub.push ({ logout: true ,type:'logout' ,form_id: form_id});
  recaptcha_emsFormBuilder = '';
  indx = valj_efb_new.findIndex(x => x.id == form_id);
  if(indx!=-1) {
    valj_efb_new[indx].type = 'logout';
  }
  actionSendData_emsFormBuilder(form_id);
}
function Show_recovery_pass_efb() {
  let el = document.getElementById('recoverySectionemsFormBuilder');
  let elBtnBack = document.getElementById('prev_efb_send');
  let iconBtn = document.getElementById('icon_btn_Show_recovery_efb');
  let elMsg = document.getElementById('alertFinalStepEFB');
  const isHidden = el.classList.contains('d-none');

  if (isHidden) {

      el.classList.remove('d-none');
      el.classList.remove('fadeOut');
      el.classList.add('fadeIn');
      iconBtn.className = 'bi bi-chevron-up mx-2';
      el = document.getElementById('btn_recovery_pass_efb');
      el.disabled = true;
      elMsg.classList.add('d-none');
      elBtnBack.classList.add('d-none');
  } else {
      el.classList.remove('fadeIn');
      el.classList.add('fadeOut');

      iconBtn.className = 'bi bi-chevron-down';

      el.addEventListener('animationend', function() {
          el.classList.add('d-none');
          elBtnBack.classList.remove('d-none');
          elMsg.classList.remove('d-none');
      }, { once: true });
  }

  document.getElementById('recoverySectionemsFormBuilder').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});

  if ( el.dataset.hasOwnProperty('id') &&el.dataset.id == 1) {
    el.dataset.id = 0;
    const us = document.getElementById('username_recovery_pass_efb');
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    el.addEventListener("click", (e) => {
      form_type_emsFormBuilder = "recovery";
      formNameEfb = form_type_emsFormBuilder;
      document.getElementById('efb-final-step').innerHTML = `<h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3 class="efb text-center">${ajax_object_efm.text.pleaseWaiting}<h3>`
      const valj = valj_efb_new.find(x => x.type ==  'login');
      const form_id = valj ? valj.id : 0;
      sendBack_emsFormBuilder_pub = { email: us.value ,'recovery':true , form_id: form_id};
      actionSendData_emsFormBuilder(form_id)
    })
    us.addEventListener("keyup", (e) => {
      const isValid = us.value.match(format);
      if (isValid) {
        el.classList.remove('disabled');
        el.disabled = false;
      } else {
        if (!el.classList.contains('disabled')) {
          el.classList.add('disabled');
        }
        el.disabled = true;
      }
    })
  }
}
async function response_fill_form_efb(res ,form_id=0) {
  form_id = Number(form_id);
  let btn_prev ='';
  const t = valj_efb_new.find(x => x.id == form_id);
  const valj_efb = t.form_structer;
  const stps = t.form_structer && t.form_structer.hasOwnProperty('steps')  ? Number(valj_efb[0].steps) : -123;
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  const efb_final_step = body_efb.querySelector('#efb-final-step');
  if(valj_efb.length>1) btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? `logic_fun_prev_send(${form_id})`:`fun_prev_send(${form_id})`;
  if (res.data.success == true) {
    if(valj_efb.length>0 && valj_efb[0].hasOwnProperty('thank_you')==true && valj_efb[0].thank_you=='rdrct'){
      efb_final_step.innerHTML = `
      <h3 class="efb fs-4 text-center">${efb_var.text.sentSuccessfully}</h3>
      <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${res.data.m}">${efb_var.text.orClickHere}</a></h3>
      `
      window.location.href = res.data.m;
      return ;
    }
    switch (t.type) {
      case 'form':
      case 'payment':
        efb_final_step.innerHTML = funTnxEfb(res.data.track)
        localStorage.clear();
        break;
      case 'survey':
        localStorage.clear();
        efb_final_step.innerHTML = funTnxEfb('','',res.data.m);
        if (res.data.survey_chart_type && res.data.survey_chart_type !== 'none' && res.data.survey_results && res.data.survey_results.length > 0) {
          if (typeof renderSurveyResultsChart === 'function') {
            renderSurveyResultsChart(res.data, efb_final_step, form_id);
          } else {
          }
        } else {
        }
        break;
      case 'subscribe':
        efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder fs-4'><i class="efb fs-2 bi-hand-thumbs-up  text-center"></i></h3><h3 class='efb emsFormBuilder fs-5  text-center'>${valj_efb[0].thank_you_message.thankYou}</h3></br> <span class="efb fs-5">${ajax_object_efm.text.YouSubscribed}</span></br></br></h3>`;
        localStorage.clear();
        break;
      case 'register':
          const m = form_type_emsFormBuilder !='recovery' ? valj_efb[0].thank_you_message.thankYou: ajax_object_efm.text.checkYourEmail;
          efb_final_step.innerHTML = funTnxEfb('','',m );
          break;
      case 'recovery':
        efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder fs-4  text-center'><i class="efb fs-2 bi-envelope text-center"></i></h3><h3 class='efb emsFormBuilder fs-5  text-center'>${res.data.m}</h3></br></br></h3>`;
      break;
      case 'login':
        if (res.data.m && typeof res.data.m === 'object' && res.data.m.state == true) {
          document.getElementById('body_efb_'+form_id).innerHTML = show_user_profile_emsFormBuilder(res.data.m);
          location.reload();
        } else if(typeof res.data.m === 'string' && res.data.success == true){
          document.getElementById('body_efb_'+form_id).innerHTML = `
          <div class="efb mt-5"><div class="efb card-block text-center text-dark ">
              <div class="efb mb-3 d-flex justify-content-center"><i class="efb fs-1 bi-envelope-check text-success"></i></div>
              <p class="efb fs-6">${res.data.m}</p>
          </div>
          `
        } else {
          const errorMsg = res.data.m && res.data.m.error ? res.data.m.error : (res.data.m || ajax_object_efm.text.error);
          efb_final_step.innerHTML = `
            <div class="efb mx-auto" style="max-width: 400px;">
              <div class="efb card-body text-center py-4 px-3">
                <!-- Error Icon & Message -->
                <div id="alertFinalStepEFB" class="efb mb-4">
                  <div class="efb mb-3">
                    <i class="efb bi-shield-exclamation fs-1 text-warning"></i>
                  </div>
                  <h5 class="efb fs-5 text-dark mb-2">${ajax_object_efm.text.error}</h5>
                  <p class="efb fs-6 text-muted mb-0">${errorMsg}</p>
                </div>

                <!-- Recovery Link -->
                <div class="efb pt-3 mt-3">
                  <button id="btn_Show_recovery_efb" type="button"
                    class="efb btn btn-outline-secondary ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'rounded-2'} ${valj_efb[0].el_height}   px-4 py-2 d-inline-flex align-items-center"
                    onclick="Show_recovery_pass_efb()">
                    <span>${ajax_object_efm.text.passwordRecovery}</span>
                    <i id="icon_btn_Show_recovery_efb" class="efb bi-chevron-down mx-2"></i>
                  </button>
                </div>

                <!-- Recovery Form Section -->
                <div class="efb bg-light rounded-3 p-3 mt-3 d-none" id="recoverySectionemsFormBuilder">
                  <div class="efb mb-3">
                    <i class="efb bi-envelope fs-3 text-muted"></i>
                  </div>
                  <p class="efb fs-7 text-muted mb-3">${ajax_object_efm.text.servpss}</p>
                  <div class="efb mb-3">
                    <input type="email" id="username_recovery_pass_efb"
                      class="efb form-control h-d-efb rounded-2 border text-center"
                      placeholder="${ajax_object_efm.text.email || 'Email'}"
                      autocomplete="email">
                  </div>
                  <button id="btn_recovery_pass_efb"
                    class="efb btn  ${valj_efb[0].button_color} ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'rounded-2'} ${valj_efb[0].el_text_color} w-100 h-d-efb disabled"
                    data-id="1">
                    <i class="efb bi-send me-2"></i>${ajax_object_efm.text.send}
                  </button>
                </div>

                <!-- Back Button -->
                <div class="efb mt-4">
                  <button id="prev_efb_send" type="button"
                    class="efb btn ${valj_efb[0].button_color} ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'rounded-2'} ${valj_efb[0].el_height} px-4"
                    onclick="fun_prev_send(${form_id})">
                    <i class="efb ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} me-2"></i>
                    <span class="efb ${valj_efb[0].el_text_color}">${valj_efb[0].button_Previous_text}</span>
                  </button>
                </div>
              </div>
            </div>`;
        }
        break;
      case "logout":
        location.reload();
        localStorage.clear();
        break;
    }

      if(stps>1 ){smoothy_scroll_postion_efb(id_body)}
  } else {
    if(efb_final_step){efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder text-center'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center efb fs-3  text-center"></i></h1><h3 class="efb  text-center fs-3 text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5"> ${res.data.m}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].hasOwnProperty('button_color') ? valj_efb[0].button_color : 'btn-darkb'}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'efb-square'}   ${valj_efb[0].hasOwnProperty('el_height') ? valj_efb[0].el_height : 'h-l-efb'}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    }else{
      alert_message_efb(res.data.m,'',14,'warning');
    }
  }
}
  function loadCaptcha_efb(timer = 20) {
  let sitekye_emsFormBuilder = ajax_object_efm?.form_setting?.siteKey;
  if (!sitekye_emsFormBuilder) return;

  if (!window.grecaptcha || !window.grecaptcha.render) {
    setTimeout(() => {
      loadCaptcha_efb(timer + 100);
    }, timer);
  } else {
    document.querySelectorAll('.g-recaptcha').forEach(el => {
      if (el.dataset.grecaptchaWidgetId) return;
      const formId = el.dataset.formid ?? -1;
      const widgetId = grecaptcha.render(el, {
        sitekey: sitekye_emsFormBuilder,
        callback: (token) => recaptchaSuccessEfb(token, formId),
        'expired-callback': () => recaptchaExpiredEfb(formId),
        'error-callback': () => recaptchaErrorEfb?.(formId),
      });
      el.dataset.grecaptchaWidgetId = String(widgetId);
    });
  }
};

recaptchaExpiredEfb = (formId)=>{
  formId = formId==-1 ? -1 : Number(formId);
  const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == 'captcha_v2' && x.form_id == formId);
  if(i!=-1) sendBack_emsFormBuilder_pub.splice(i,1);

  if(formId==0 || formId==-1){
    const bdy = document.getElementById('body_tracker_emsFormBuilder');
    if(bdy){
      const btn = bdy.querySelector('#vaid_check_emsFormBuilder');
      if(btn){
        btn.classList.add('disabled');
      }
    }
  }else{
    updateStepButtonState_efb(formId);
  }
}

recaptchaErrorEfb = (formId) =>{
}

recaptchaSuccessEfb = (token, formid) => {
  formid = formid==-1 ? -1 : Number(formid);
  fun_sendBack_emsFormBuilder({ id_: 'captcha_v2', name: 'recaptcha', value: token ,form_id:formid, type:'captcha_v2' });
  if(formid==0 || formid==-1){
    const bdy = document.getElementById('body_tracker_emsFormBuilder');
    if(bdy){
      const btn = bdy.querySelector('#vaid_check_emsFormBuilder');
      if(btn){
        btn.classList.remove('disabled');
      }
    }
  }else{
    updateStepButtonState_efb(formid);
  }
}
function calPLenEfb(len) {
  if (len <= 5) { return 40;}
  else if (len <= 10) { return 20; }
  else if (len <= 50) { return 15; }
  else if (len <= 100) { return 9; }
  else if (len <= 300) { return 3; }
  else if (len <= 600) { return 1.5; }
  else if (len <= 1000) { return 1.2; }
  else { return 1.1; }
}
fun_text_forbiden_convert_efb=(value)=>{
 value= value.replaceAll("'", "@efb@sq#");
 value= value.replaceAll("`", "@efb@vq#");
 value= value.replaceAll(`"`, "@efb@dq#");
 value= value.replaceAll(`\t`, " ");
 value= value.replaceAll(`\b`, " ");
 value= value.replaceAll(`\r`, "@efb@nq#");
 value= value.replaceAll(`\n`, "@efb@nq#");
 value= value.replaceAll(`\r`, " ");
 return value;
}
remove_ttmsg_efb=(id)=>{
  if(document.getElementById(`${id}_-message`)){
    document.getElementById(`${id}_-message`).classList.remove('show');
    document.getElementById(`${id}_-message`).innerHTML="";
  }
}
change_url_back_persia_pay_efb=()=>{
  const indx = document.URL.indexOf('?');
  if(indx!=-1)history.pushState({'page_id': 1},`${document.title} !`, document.URL.slice(0,indx));
}
window.addEventListener("popstate",e=>{
  if (e.state.search('EFBstep-') ==-1) return
    Number(e.state.slice(8)) <= Number(current_s_efb)  ? prev_btn_efb() :jQuery("#next_efb").trigger('click');
 })
 fun_gets_url_efb =()=>{
  const getUrlparams = new URLSearchParams(location.search)
  const iefb =  getUrlparams.getAll("iefb");
  const hefb =  getUrlparams.getAll("hefb");
  const sefb =  getUrlparams.getAll("sefb");
  const defb =  getUrlparams.getAll("defb");
  const vefb =  getUrlparams.getAll("vefb");
  if(iefb.length>0){
    for(let i in iefb){
      const id =iefb[i];
      const i_ = valj_efb.findIndex(x=>x.id_==id);
      let i_p = -1;
      let t_e ='string';
      if(valj_efb[i_].hasOwnProperty('parent')==true) i_p= valj_efb.findIndex(x=>x.id_==valj_efb[i_].parent)
      if(i_p!=-1){
        t_e = valj_efb[i_p].type.toLowerCase();
        t_e = (t_e.includes('select')==true && t_e.includes('multi')==false) ||t_e.includes('radio')==true ? "string" :'array';
      }
      if(sefb.length>0 && sefb.length>i){
         if(i_p==-1)continue;
        if(t_e=="string"  ){
            if(sefb[i]==1){
            valj_efb[i_p].value = id;
          }
        }else{
          t_e  = typeof valj_efb[i_p].value;
          const indx = t_e!="string" ?  valj_efb[i_p].value.findIndex(x=>x==id) : -2
          if(sefb[i]==1){
            if(indx==-1){
              valj_efb[i_p].value.push(id);
            }else if (indx==-2){
              valj_efb[i_p].value=[id];
            }
          }else{
            if(indx>-1)valj_efb[i_p].value.splice(indx,1);
          }
        }
      }
      if(defb.length>0 && defb.length>i){
       i_p=  i_p!=-1 ? i_p : i_
       if(defb[i]==1){
        valj_efb[i_p].disabled=1
       }else if(defb[i]==0){
        valj_efb[i_p].disabled=0
       }
      }
      if(hefb.length>0 && hefb.length>i){
        i_p=  i_p!=-1 ? i_p : i_
        if(hefb[i]==1){
          valj_efb[i_p].hidden=1
         }else if(hefb[i]==0){
          valj_efb[i_p].hidden=0
         }
      }
      if(vefb.length>0 && vefb.length>i && i_p==-1){
        valj_efb[i].value = vefb[i];
      }
    }
  }
 }
 fun_booking_avilable =(el)=>{
 let r =[true,''];
 let id = el.id;
 if(el.type=='select' || el.type=='select-one'){
  id=el.options[el.selectedIndex].dataset.id;
 }
  const row = valj_efb.find(x=>x.id_==id)
  const lan = wp_lan.replaceAll(`_`, "-")
  const ndate = new Date().toLocaleDateString(lan, {year:"numeric", month: "2-digit", day:"2-digit"});
  uncheck =()=>{
    if(el.type=="radio" || el.type=="checkbox"){
      el.checked=false
    }
  }
    if(typeof row!="object"){
      r=[false,'Row not Found! contact to admin'];
      uncheck();
      return r;
    }
    if( row.hasOwnProperty('dateExp') && row.dateExp<ndate){
      r=[false,'Sorry, the selected option is no longer available as its expiration date has passed. Please choose another option.'];
      uncheck();
      return  r;
    }
    if( row.hasOwnProperty('mlen') && Number(row.mlen)<= Number(row.registered_count)){
      r=[false,'Unfortunately, the option you selected is no longer available. Please choose from the other available options.'];
      uncheck();
      return  r;
    }
  return  r;
 }
 post_api_forms_efb=async(data,form_id)=>{
    const url = efb_var.rest_url+'Emsfb/v1/forms/message/add';
    const headers = new Headers({
      'Content-Type': 'application/json',
      'X-WP-Nonce': efb_var.nonce,
      'form-id': form_id ? form_id : 0,
      'sid':data.sid ? data.sid : '',
    });

    const jsonData = JSON.stringify(data);
    const requestOptions = {
      method: 'POST',
      headers,
      body: jsonData,
    };

  try {
    const response = await fetch(url, requestOptions);
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const responseData = await response.json();
    await response_fill_form_efb(responseData, form_id);
    if (localStorage.getItem('sendback')) localStorage.removeItem('sendback');
  } catch (error) {
    await response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 } }, form_id);
  }
  bdy = document.getElementById('body_efb_'+form_id);
  if(bdy){
    const steps = bdy.dataset.steps ? Number(bdy.dataset.steps) : 1;
    if(steps==1){ return; }
    const prev_efb = bdy.querySelector('#prev_efb');
    const next_efb = bdy.querySelector('next_efb');
    if(prev_efb && prev_efb.classList.contains('d-none')==false){prev_efb.classList.add('d-none')}
    if(next_efb && next_efb.classList.contains('d-none')==false){next_efb.classList.add('d-none')}
  }
}
post_api_tracker_check_efb=(data,innrBtn)=>{
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/get';
  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
    'form-id': data.id ? data.id : 0,
    'sid':efb_var.sid ? efb_var.sid : '',
  });
  const jsonData = JSON.stringify(data);
  const requestOptions = {
    method: 'POST',
    headers,
    body: jsonData,
  };
  fetch(url, requestOptions)
  .then(response => {
    if (!response.ok) {
      throw new Error(`Network response was not ok (HTTP ${response.status})`);
    }
    return response.json();
  })
  .then(responseData => {
    if (document.getElementById('vaid_check_emsFormBuilder')) {
      document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn;
      document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled');
    }
    response_Valid_tracker_efb(responseData);
    efb_var.nonce_msg = responseData.data.nonce_msg;
    efb_var.msg_id = responseData.data.id;
  })
  .catch(error => {
    if (document.getElementById('vaid_check_emsFormBuilder')) {
      document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn;
      document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled');
    }
    response_Valid_tracker_efb({ success: false, data: { success: false, m: error.message } });
  });
}
post_api_r_message_efb=(data,message)=>{
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/add';
  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
    'form-id': 0,
    'sid':data.sid ? data.sid : '',
  });
  const jsonData = JSON.stringify(data);
  const requestOptions = {
    method: 'POST',
    headers,
    body: jsonData,
  };
    fetch(url, requestOptions)
    .then(response => {
      if (!response.ok) {
        throw new Error(`Network response was not ok (HTTP ${response.status})`);
      }
      return response.json();
    })
    .then(responseData => {
      response_rMessage_id(responseData, message);
      sendBack_emsFormBuilder_pub = [];
    })
    .catch(error => {
      response_Valid_tracker_efb({ success: false, data: { success: false, m: error.message } });
    });
}

document.addEventListener("DOMContentLoaded",async function() {
  let elements = document.querySelectorAll('#body_efb');
  const msg = `<h3 class="efb fs-5 text-center text-dark bg-warning m-3 p-3">${ajax_object_efm.text.fetf} <div class='efb mt-1 fs-6'> ${ajax_object_efm.text.easyFormBuilder}</div> </h3>`
  fun =()=>{
    for (let i = 1; i < elements.length; i++) {
      elements[i].innerHTML = msg;
    }
  }
  if( (document.getElementById('body_efb') && document.getElementById('body_tracker_emsFormBuilder'))){
   if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML =msg;
   if(elements.length > 1) fun();
  }else if (elements.length > 1){
    fun();
  }

  fun_wait_form_load_efb =(state ,speed)=>{
    if(speed!='fast'){
      const body_efbs = document.querySelectorAll('.body_efb');
      body_efbs.forEach((body_efb) => {
        const form_id = body_efb.dataset.formid;
        if(state ==true){
          body_efb.classList.add(`efb-waiting-${form_id}`);
        }else{
          body_efb.classList.remove(`efb-waiting-${form_id}`);
        }
      })
    }
  }

  await createStepsOfPublic();
  fun_wait_form_load_efb(false,'nfast');
  let captcha = false ;
  valj_efb_new.forEach((valj_efb) => {
    if(captcha==true) return;
    captcha = valj_efb.form_structer[0].captcha ;
    captcha = [1, '1', true, 'true'].includes(captcha) ? true : false;
  });

  if(captcha) loadCaptcha_efb(20);

  try {
    valj_efb_new.forEach(function(formData) {
      if (formData && formData.id && formData.form_structer && formData.form_structer[0]) {
        var steps = Number(formData.form_structer[0].steps) || 1;
        if (steps > 1) {
          updateStepButtonState_efb(formData.id);
        }
      }
    });
  } catch (e) { }
});

function updateStepButtonState_efb(form_id) {
  try {
    var id_body = 'body_efb_' + form_id;
    var body_efb = document.getElementById(id_body);
    if (!body_efb) return;

    var valj = get_structure_by_form_id_efb(form_id);
    if (!valj || !valj[0]) return;

    var max_step = Number(valj[0].steps) || 1;
    var currentStep = Number(body_efb.dataset.currentstep) || 1;

    var next_btn = body_efb.querySelector('#next_efb');
    var send_btn = body_efb.querySelector('#btn_send_efb');
    var target_btn = null;

    if (currentStep < max_step && next_btn) {
      target_btn = next_btn;
    } else if (currentStep === max_step && send_btn) {
      target_btn = send_btn;
    } else if (next_btn) {
      target_btn = next_btn;
    }

    if (!target_btn) return;

    var hasValidationErrors = false;
    for (var si = 0; si < sendback_efb_state.length; si++) {
      var entry = sendback_efb_state[si];
      if (entry && Number(entry.form_id) === Number(form_id) && entry.state === false) {
        var fieldInStep = valj.find(function(v) {
          return v.id_ === entry.id_ && Number(v.step) === currentStep;
        });
        if (fieldInStep) {
          hasValidationErrors = true;
          break;
        }
      }
    }

    if (hasValidationErrors) {
      if (!target_btn.classList.contains('disabled')) {
        target_btn.classList.add('disabled');
      }
      return;
    }

    var requiredFields = valj.filter(function(v) {
      return Number(v.step) === currentStep &&
             Number(v.required) === 1 &&
             v.type !== 'step' &&
             v.type !== 'option' &&
             v.type !== 'form';
    });

    var allRequiredFilled = true;
    for (var ri = 0; ri < requiredFields.length; ri++) {
      var field = requiredFields[ri];
      var fieldId = field.id_;

      if (field.type === 'file' || field.type === 'dadfile') {
        var fileIdx = files_emsFormBuilder.findIndex(function(f) { return f.id_ === fieldId; });
        if (fileIdx === -1 || (files_emsFormBuilder[fileIdx].hasOwnProperty('state') && Number(files_emsFormBuilder[fileIdx].state) === 0)) {
          allRequiredFilled = false;
          break;
        }
      } else {
        var sbIdx = sendBack_emsFormBuilder_pub.findIndex(function(x) {
          return x != null && x.hasOwnProperty('id_') && x.id_ === fieldId && Number(x.form_id) === Number(form_id);
        });
        if (sbIdx === -1) {
          allRequiredFilled = false;
          break;
        }
      }
    }

    if (allRequiredFilled && currentStep === max_step && Number(valj[0].captcha) === 1) {
      var hasCaptcha = sendBack_emsFormBuilder_pub.findIndex(function(x) {
        return x != null && x.id_ === 'captcha_v2' && Number(x.form_id) === Number(form_id);
      }) !== -1;
      if (!hasCaptcha) {
        allRequiredFilled = false;
      }
    }

    if (allRequiredFilled) {
      target_btn.classList.remove('disabled');
    } else {
      if (!target_btn.classList.contains('disabled')) {
        target_btn.classList.add('disabled');
      }
    }
  } catch (e) {
  }
}

smoothy_scroll_postion_efb=(id)=>{

  const error = new Error();
  const stack = error.stack || '';

  const element = document.getElementById(id);
  if (element) {
    const elementRect = element.getBoundingClientRect();
    const elementTop = elementRect.top + window.scrollY;
    const offset = 50;
    const scrollPosition = elementTop - offset;

    window.scrollTo({
      top: scrollPosition,
      behavior: 'smooth'
    });
  } else {
  }

}

setTimeout(() => {
  const sourceElement = document.getElementById('step-1-efb');
  const targetElement = document.getElementById('efb-final-step');

  if (sourceElement && targetElement) {
    const sourceHeight = sourceElement.offsetHeight;

    if(sourceElement.offsetHeight<300 && sourceElement.offsetWidth<300 ){
    }
  } else {
  }
}, 5000);

async function btn_navigate_handle_efb(form_id , form_type , btn_state,el){

  const id_body = 'body_efb_'+form_id;
  let parent_body = document.getElementById(id_body)

  if (!parent_body) {
    return false;
  }

  const max_step = Number(parent_body.dataset.steps);
  let no_step = Number(parent_body.dataset.currentstep);
  let valj_efb = get_structure_by_form_id_efb(form_id);
  const progessbar = parent_body.querySelector('.progress-bar-efb') ?? null;
  fun_progessbar = (no_step,max_step)=>{
    max_step = max_step+1;
    const percent_progess = ((no_step)/(max_step))*100+'%';
    if (progessbar) progessbar.style.width = percent_progess;
  }
  fun_check_step_has_necessary_fields_efb=(no_step ,valj_efb)=>{
    sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter(Boolean);
    const result = (valj_efb ?? []).filter(x =>
      Number(x.step) === Number(no_step) && Number(x.required) === 1
    );
    if (result.length==0) return 0;

    for (let i = 0; i < result.length; i++) {
      const field_id = result[i].id_;
      const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === field_id && Number(x.form_id) === Number(form_id));
      if (indx == -1) { return 1;  }
    }
    return 0;
  }
  step_payment_exists = -1;

  const title_efb = parent_body.querySelector('#title_efb') ?? null;
  const desc_efb = parent_body.querySelector('#desc_efb') ?? null;
  const steps_efb = parent_body.querySelector('#steps-efb') ?? null;
  fun_handle_header_efb = async(no_step,nav_state)=>{
    if(!title_efb || !desc_efb || !steps_efb) return false;
    if(Number(valj_efb[0].show_icon)==1){
      return true;
    }
    icon_step_handler = (no_step,form_id,nav_state)=>{
      let id_active_icon = `${no_step}-f-step-efb-${form_id}`;
      let active_step_icon = document.getElementById(id_active_icon);
      active_step_icon ?  active_step_icon.classList.add('active') : false;
      if(nav_state=='forward'){
        id_active_icon = `${(no_step-1)}-f-step-efb-${form_id}`;
        if(no_step==1) return true;
      }else{
        id_active_icon = `${(no_step+1)}-f-step-efb-${form_id}`;
      }
      active_step_icon = document.getElementById(id_active_icon)
      active_step_icon ? active_step_icon.classList.remove('active') : false;
      return true;

    }
    if(no_step<=max_step){
    icon_step_handler(no_step,form_id,nav_state);
    const cp = no_step;
    const val = valj_efb.find(x=>x.step==cp);
      title_efb.className = colorTextChangerEfb(title_efb.className,val["label_text_color"]);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val["message_text_color"]);
      title_efb.innerHTML = val["name"];
      desc_efb.innerHTML = val["message"];
    }else{
      icon_step_handler(no_step,form_id,nav_state);
      const val = valj_efb.find(x=>x.step==1);
      title_efb.className = colorTextChangerEfb(title_efb.className,val["label_text_color"]);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val["message_text_color"]);
      title_efb.textContent = efb_var.text.finish;
      desc_efb.textContent = ' ';
    }
    return true;
  }

  const first_row = valj_efb[0];
  const current_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);

  if (!current_fieldset) {
    return false;
  }
  if(form_type == 'payment'){
    const payment_method = ['paypal','stripe','persiapay' ,'zarinpal'];
    const payment_complated = get_row_sendback_by_id_efb_v4('payment',form_id);
    if(payment_complated){
      const payment_rows = (valj_efb ?? []).filter(x => payment_method.includes(String(x.type)) && x.hasOwnProperty('step'));
      if (payment_rows.length > 0) {
        step_payment_exists = Number(payment_rows[0].step);
      }
    }else{
      step_payment_exists = -1;
      alert('The form(form id:'+form_id+') cannot be submitted because it requires a payment method, which is currently missing. If you are the Admin, please add a payment method to the form or change the form type to "Form" or "Survey".');
      return false;
    }
  }

  const validate = await fun_validation_efb_v4(form_id);
        if (validate == false) {

          return false;
        }

  if(btn_state=='next_efb'){
        let prev_btn = parent_body.querySelector('#prev_efb');
        if(no_step<2){
          if(prev_btn)prev_btn.classList.remove('d-none');
        }
        no_step = Number(no_step)+1;

        await fun_handle_header_efb(no_step,'forward');
        current_fieldset.classList.add('d-none');
        const next_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
        if (next_fieldset) next_fieldset.classList.remove('d-none');

       parent_body.dataset.currentstep = no_step;
       if(progessbar)fun_progessbar(no_step,max_step);

       if(no_step>max_step){
         el.classList.add('d-none');
         prev_btn.classList.add('d-none');
         endMessage_emsFormBuilder_view(max_step,form_id);
       }else if(no_step==max_step){
        updateStepButtonState_efb(form_id);
       }
       smoothy_scroll_postion_efb(id_body);
       if(no_step==step_payment_exists){
         el.disabled = true;
       }

  }else if (btn_state=='prev_efb'){
    if(no_step==2){
      let prev_btn = parent_body.querySelector('#prev_efb');
      if(prev_btn)prev_btn.classList.add('d-none');
    }

    no_step = Number(no_step)-1;
    parent_body.dataset.currentstep =  no_step;
    const prev_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
    current_fieldset.classList.add('d-none');

    if(prev_fieldset)prev_fieldset.classList.remove('d-none');
    if(progessbar) fun_progessbar(no_step,max_step);
    smoothy_scroll_postion_efb(id_body);
    await fun_handle_header_efb(no_step,'backward');
    updateStepButtonState_efb(form_id);
  }else if (btn_state=='btn_send_efb'){
    no_step = Number(no_step)+1;

    parent_body.dataset.currentstep = no_step;
    const next_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
    endMessage_emsFormBuilder_view(current_s_efb,form_id);
    current_fieldset.classList.add('d-none');
    next_fieldset.classList.remove('d-none');
    if(progessbar)fun_progessbar(no_step,max_step);
    smoothy_scroll_postion_efb(id_body);
    await fun_handle_header_efb(no_step,'forward');

    el.classList.add('d-none');
  }

}

get_structure_by_form_id_efb=(form_id)=>{
  let ob = valj_efb_new.find(x => x.id == form_id);
  const form_structer = ob.form_structer;
  form_ID_emsFormBuilder = form_id;
  return form_structer.filter(Boolean);
}

sendback_state_handler_efb_v4=(id_,state,step,form_id)=>{
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  if (!body_efb) return;
  const indx = sendback_efb_state.findIndex(x=>x.id_==id_ && Number(x.form_id)==Number(form_id));
  if(indx==-1 && state==false){
    var actualStep = step;
    try {
      var _valj = get_structure_by_form_id_efb(form_id);
      var _field = _valj ? _valj.find(function(v){ return v.id_ === id_; }) : null;
      if (_field && _field.step) actualStep = Number(_field.step);
    } catch(e) {}
    sendback_efb_state.push({id_:id_,state:state,step:actualStep,form_id:form_id});
    updateStepButtonState_efb(form_id);
  }else if(indx>-1 && state==true && sendback_efb_state.length>0){
    sendback_efb_state.splice(indx,1);
    setTimeout(function() {
      updateStepButtonState_efb(form_id);
    }, 100);
  }
}

get_row_sendback_by_id_efb_v4=(id_,form_id=0)=>{

  if(form_id==0){
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_)

  }else{
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_ && Number(x.form_id)==Number(form_id))
  }
 }

fun_prev_send =(form_id =0) =>{
  let valj_efb = get_structure_by_form_id_efb(form_id);
  var stp = Number(valj_efb[0].steps) + 1;
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  current_s_efb = body_efb.dataset.currentstep;
  const finalStepEl = body_efb.querySelector('#efb-final-step');
  finalStepEl.innerHTML = loading_messge_efb();
  let id = `step-${current_s_efb}-efb`;
  var current_s = body_efb.querySelector(`[data-step="${id}"]`);
  id = `step-${current_s_efb-1}-efb`;
  const prev_s_efb = body_efb.querySelector(`[data-step="${id}"]`);

  fun_progessbar = (current_step,max_step)=>{
    const progessbar = body_efb.querySelector('.progress-bar-efb')
    if(!progessbar)return false;
    max_step = max_step+1;
    const percent_progess = ((current_step)/(max_step))*100+'%';
    progessbar.style.width = percent_progess;
  }

  if(Number(valj_efb[0].show_icon)!=1)  body_efb.querySelector('[data-step="icon-s-' + current_s_efb + '-efb"]').classList.remove("active");
  body_efb.querySelector('[data-step="step-' + current_s_efb + '-efb"]').classList.toggle("d-none");
  if (stp == 2) {
       const btn_send_efb = body_efb.querySelector('#btn_send_efb');
       btn_send_efb.classList.remove('d-none');
       const gRecaptcha = body_efb.querySelector('#gRecaptcha');
    if(gRecaptcha) {
      gRecaptcha.classList.remove('d-none');
      }
  } else {
    const next_efb = body_efb.querySelector('#next_efb');
    next_efb.classList.remove('d-none');
  }
  var s = "" + (current_s_efb - 1) + "";
  var val = valj_efb.find(x => x.step == s);
  if(Number(valj_efb[0].show_icon)!=1){
    const title_efb = body_efb.querySelector("#title_efb");
    const desc_efb = body_efb.querySelector("#desc_efb");
    title_efb.className = colorTextChangerEfb(title_efb.className,val['label_text_color']);
    desc_efb.className = colorTextChangerEfb(desc_efb.className,val['message_text_color']);
    title_efb.textContent = val['name'];
    desc_efb.textContent = val['message'];

    let id_active_icon = `${s}-f-step-efb-${form_id}`;
    const next_active_step_icon = document.getElementById(id_active_icon);
    next_active_step_icon.classList.add('active');
  }
  const prev_efb = body_efb.querySelector('#prev_efb');
  if(prev_efb)prev_efb.classList.toggle("d-none");
  current_s.classList.add('d-none');
  prev_s_efb.classList.remove('d-none');
  current_s_efb -= 1;
  body_efb.dataset.currentstep = current_s_efb;
  fun_progessbar(current_s_efb,stp);
  smoothy_scroll_postion_efb(id_body);

}

async function handle_change_event_efb_v4(el ,form_id=0){
  let valj_efb = get_structure_by_form_id_efb(form_id);
  slice_sback=(i)=>{
    sendBack_emsFormBuilder_pub.splice(i, 1)
  }
  delete_by_id=(id)=>{
    const i = get_row_sendback_by_id_efb_v4(id,form_id);
    if (i != -1) { slice_sback(i) }
  }
  el_empty_value=(id)=>{
    const id_ = id +'_';
    const s = valj_efb.find(x => x.id_ == id);
    const el_msg = document.getElementById(id_+'-message');
    const el = document.getElementById(id_);
    if(Number(s.required)==0){
      if(el !== null) el.className = colorBorderChangerEfb(el.className, s.el_border_color);
      el_msg.classList.remove('show');
     sendback_state_handler_efb_v4(id,true,0,form_id);
    }else{
      if(el !== null) el.className = colorBorderChangerEfb(el.className, "border-danger");
      if(el_msg!=null){
        if(el_msg.classList.contains('show')==false)el_msg.classList.add('show');
        el_msg.innerHTML = efb_var.text.enterTheValueThisField;
      }
    }
    delete_by_id(id);
    updateStepButtonState_efb(form_id);
  }
  validate_len_efb_v4 =async()=>{

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

  if(form_id==0) form_id = el.dataset.formid;
  valueJson_ws = get_structure_by_form_id_efb(form_id)
  let ob = valueJson_ws.find(x => x.id_ === el.dataset.vid);
  let value = ""
  const id_ = el.dataset.vid
  let state
  if(!ob){
    if(el.id.includes('chl')!=false){
      ob= sendBack_emsFormBuilder_pub.find(x => x.id_ob === el.dataset.id);
    }
  }
  let vd ;
  if(valj_efb[0].hasOwnProperty('booking')==true && Number(valj_efb[0].booking)==1) {
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
      }
      {
        let lenResult = await validate_len_efb_v4();
        if(lenResult===0 && (el.dataset.hasOwnProperty('type') && el.dataset.type!="chlCheckBox")){
          sendback_state_handler_efb_v4(id_,false,0,form_id);
          updateStepButtonState_efb(form_id);
          return;
        }else {
          el.className = colorBorderChangerEfb(el.className, "border-success");
          vd= document.getElementById(`${el.id}-message`)
          if(vd)vd.classList.remove('show');
        }
      }
      break;
    case 'url':
      vd = document.getElementById(`${el.id}-message`)
      const che = el.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?!&\/=;',]*)$/g);
      if(el.value.length==0){
        el_empty_value(id_);
      } else if (che == null) {
        valid = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd.innerHTML = efb_var.text.enterValidURL;
        if(vd.classList.contains('show')==false)vd.classList.add('show');
         sendback_state_handler_efb_v4(id_,false,0,form_id)
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
          if(ob.type=="payCheckbox") fun_total_pay_efb(form_id);
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
           await fun_check_link_state_efb(el.options[el.selectedIndex].dataset.iso , temp,el.dataset.formid);
      }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="stateProvince"){
           let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
            iso_con = el.options[el.selectedIndex].dataset.isoc
            iso_state = el.options[el.selectedIndex].dataset.iso
            setTimeout(async() => {
              if(iso_state && iso_con) {
               await fun_check_link_city_efb(iso_con,iso_state , temp, form_id);
             }
            }, 100);
      }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="cityList"){
            let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
            iso_state = el.options[el.selectedIndex].dataset.statepov
            iso_con = el.options[el.selectedIndex].dataset.isoc

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
      if(!el.classList.contains('dadfile')){
        valid_file_emsFormBuilder(id_,'msg','',form_id);
      }
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
  form_id = el.dataset.hasOwnProperty('formid') ? el.dataset.formid : 0;
  if(state===false && value.length > 0)   sendback_state_handler_efb_v4(id_,false,0,form_id);
  if (value != "" || value.length > 0) {

    const type = ob.type;
    const id_ob = ob.type != "paySelect" ? el.id : el.options[el.selectedIndex].id;
    let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder,form_id:  form_id }];
     sendback_state_handler_efb_v4(id_,true,0,form_id);
    if (el.classList.contains('payefb')) {
      let q = valueJson_ws.find(x => x.id_ === el.id);
      let p ;
      if(type =='prcfld'){
        p= Object.assign(o[0], {price: el.value});
      }else{
        p = price_efb.length > 0 ? { price: price } : { price: q.price }
      }
      Object.assign(o[0], p)

      fun_sendBack_emsFormBuilder(o[0]);
      fun_total_pay_efb(form_id)
    }else if(type.includes('option')){
      const ch = el.id.includes('_chl')
      const qty = ch  ? document.getElementById(el.id).value :'';
      if(ch==false){
      }else{
        el.classList.remove('bg-danger');
        const v =fun_text_forbiden_convert_efb(qty);
        const vid= el.dataset.vid;
        const indx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ob==vid);

        if(indx!=-1){
          sendBack_emsFormBuilder_pub[indx].hasOwnProperty('qty') ? sendBack_emsFormBuilder_pub[indx].qty = v : Object.assign(sendBack_emsFormBuilder_pub[indx], {qty: v});
          return;
        }
      }

       await fun_sendBack_emsFormBuilder(o[0]);
    }else if (o[0].type=="email"){

      await fun_sendBack_emsFormBuilder(o[0]);
    }else {

      await fun_sendBack_emsFormBuilder(o[0]);
    }
  }
  updateStepButtonState_efb(form_id);
}

async function fun_validation_efb_v4(form_id) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${efb_var.text.enterTheValueThisField}','',10,'danger')"></div>` : efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null";
  let name_field = "";
  if(Number(form_id)!=Number(form_ID_emsFormBuilder)) {valj_efb = get_structure_by_form_id_efb(form_id);}
  let id_noti_message = valj_efb.steps > 1 ?  `step-${current_s_efb}-efb-msg` : 'alert_efb';
  for (let row in valj_efb) {
    let s =  get_row_sendback_by_id_efb_v4(valj_efb[row].id_,form_id);
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      let el =document.getElementById(`${valj_efb[row].id_}_-message`);
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        let r=files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_);
        s = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state)==0 || r==-1 ? -1 :1;
      }
      if (s == -1) {
        if (state == true) { state = false; idi = valj_efb[row].id_ , name_field = valj_efb[row].name }
        const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
        if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
          el.classList.add('unpx');
        }
        el.innerHTML = msg;
        if(!el.classList.contains('show'))el.classList.add('show');
        if (type_validate_efb(valj_efb[row].type) == true) {
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");}
      } else {
        idi = valj_efb[row].id_;
        el.innerHTML = "";
        el.classList.remove('show');
        if (type_validate_efb(valj_efb[row].type) == true) document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-success");
        const v = sendBack_emsFormBuilder_pub.length>0 && valj_efb[row].type == "multiselect" && sendBack_emsFormBuilder_pub[s].hasOwnProperty('value') ? sendBack_emsFormBuilder_pub[s].value.split("@efb!") :"";
        if ((valj_efb[row].type == "multiselect" || valj_efb[row].type == "payMultiselect") && (v.length - 1) < valj_efb[row].minSelect) {
          name_field = valj_efb[row].name
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");
          el.innerHTML = efb_var.text.minSelect + " " + valj_efb[row].minSelect
          if(!el.classList.contains('show'))el.classList.add('show');
          if (state == true) { state = false; idi = valj_efb[row].id_ }
        }
      }
    }else if (row > 1 && valj_efb[row].type == "chlCheckBox" && current_s_efb == valj_efb[row].step){
      name_field = valj_efb[row].name;
      idi = valj_efb[row].id_;
      fun_noti_chlcheckbox = (idi,name_field,id_noti_message,form_id) => {
         const vd = idi+"_chl";
          state = false;

          const messag = efb_var.text.sfmcfop.replace('%s',`<b>${name_field}</b>`);
          noti_message_efb_v4(messag, 'danger' ,id_noti_message ,form_id);

      }
        let state_of_ch = true;;
        const em = sendBack_emsFormBuilder_pub.find(x => x.id_ob == idi);
        if(em==undefined || em==null ){
            document.getElementById(idi+'_-message').innerHTML = msg;
            document.getElementById(idi+'_-message').classList.add('show');

            document.getElementById(idi).classList.add('bg-warning');

        }else if(em.id_ob =idi && em.hasOwnProperty('qty')==false && em.qty == "" && em.value.length == 0 ) {
          fun_noti_chlcheckbox(idi,name_field,id_noti_message,form_id);

        }else{
            document.getElementById(idi+'_-message').innerHTML = "";
            document.getElementById(idi+'_-message').classList.remove('show');
             document.getElementById(idi).classList.remove('bg-warning');
        }

    }
  }

  if (state===false && idi != "null") {
    if(typeof smoothy_scroll_postion_efb === 'function'){
      smoothy_scroll_postion_efb(idi)

    }else{
      document.getElementById(idi).scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
    }
    return false;
  }
  return state
}

function noti_message_efb_v4(message, alert ,id,form_id=0){
  alert = alert ? `alert-${alert}` : 'alert-info';
  let d = document.querySelector(`#${id}[data-formid="${form_id}"]`);
  if(d.querySelector('#noti_content_efb')){
    d.querySelector('#noti_content_efb').remove()
  }
    d.innerHTML += ` <div id="noti_content_efb" class="efb w-75 mt-0 my-1 alert-dismissible alert ${alert}  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <p class="efb my-0">${message}</p>
  </div>`
}

function call_fun_jalali_datepicker_efb_v4(){
  if(typeof load_style_persian_data_picker_efb =="function"){
    load_style_persian_data_picker_efb();
  }else{
    setTimeout(() => {
      alert_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.tfnapca + '(jalali datepicker)', 20 , 'info');
    }, 1000);
  }
}

function call_fun_hijri_datapicker_efb_v4(){
  if(typeof  load_hijir_data_picker_efb=="function"){
    load_hijir_data_picker_efb()

  }else{
    setTimeout(() => {
      alert_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.tfnapca + '(Hijri datepicker)', 20 , 'info')
    }, 1000);
  }
}
const fun_sid_efb =(form_id)=>{
  let vj = valj_efb_new.find(x=>x.id==form_id);
  const sid = vj.sid;
  valj_efb = vj.form_structer;
  formNameEfb = valj_efb[0].formName;
  captcah = valj_efb[0].hasOwnProperty('captcha') ? valj_efb[0].captcha : false;
  let r=  {sid:sid,type:vj.type,formName:formNameEfb,captcah:captcah};
  if(vj.hasOwnProperty('nonce_msg')){
    r = Object.assign(r,{nonce_msg:vj.nonce_msg});
  }
  return r;
}

const speed_test_efb=()=>{
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

      if( connection.downlink<1){
        return 'slow';
      }else if(connection.downlink>=1 && connection.downlink<2){
        return 'medium';
      }else {
        return 'fast'
      }
  } else {
     return 'notSupported';
  }
}

const check_form_payment_filled_efb = (form_id=0) =>{
  const payment_method =['paypal','stripe','persiapay' ,'zarinpal'];
  let valj_efb = get_structure_by_form_id_efb(form_id);
  if(valj_efb[0].type != 'payment') return true;
  let necessary_fields_filed = [];
  let is_getway_last_filed = false;
  last_row_index = valj_efb.length - 1;
  const steps = Number(valj_efb[0].steps);
  const peyment_type = valj_efb[last_row_index].type.toLowerCase();
  if(payment_method.includes(peyment_type)){
    is_getway_last_filed = true;
  }else{
    return false;
  }

  for (let row in valj_efb) {

    if (valj_efb[row].hasOwnProperty('required') && valj_efb[row].required == true) {
      necessary_fields_filed.push(valj_efb[row].id_);
    }
  }
    for(let i=0; i<necessary_fields_filed.length; i++){
      const field_id = necessary_fields_filed[i];
      const s =  get_row_sendback_by_id_efb_v4(field_id, form_id);
      if(s == -1){
        return false;
      }
    }
    const bdy = document.getElementById(`body_efb_${form_id}`);
    if(!bdy) return false;
    if(steps==1){
      const btn_el =bdy.querySelector('#btn_send_efb');
      btn_navigate_handle_efb(form_id , 'payment' , 'btn_send_efb' , btn_el);

    }else{
      bdy.dataset.currentstep = steps;
      current_s_efb = steps;
      const btn_el =bdy.querySelector('#next_efb');
      btn_navigate_handle_efb(form_id , 'payment' , 'next_efb' , btn_el);

    }

}
