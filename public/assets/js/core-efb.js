let exportView_emsFormBuilder = [];
let stepsCount;
let sessionPub_emsFormBuilder = "reciveFromClient"
let stepNames_emsFormBuilder = [`t`, ``, ``];
let currentTab_emsFormBuilder = 0;
let multiSelectElemnets_emsFormBuilder = [];
let formNameEfb = ""
let files_emsFormBuilder = [];
let addons_emsFormBuilder =""
let recaptcha_emsFormBuilder = '';
let poster_emsFormBuilder = '';
const fileSizeLimite_emsFormBuilder = 8300000;
let select_options_emsFormBuilder = [];
let form_type_emsFormBuilder = 'form';
let valueJson_ws = []
let motus_efb = {};
let g_timeout_efb = 100
let price_efb ="";
let sendback_efb_state= [];


if (typeof(ajax_object_efm)=='object' && ajax_object_efm.hasOwnProperty('ajax_value') && typeof ajax_object_efm.ajax_value == "string") {
  g_timeout_efb = (g_timeout_efb, ajax_object_efm.ajax_value.match(/id_/g) || []).length;
  g_timeout_efb = g_timeout_efb * calPLenEfb(g_timeout_efb);
}
g_timeout_efb = typeof ajax_object_efm == "object" && typeof ajax_object_efm.ajax_value == "string" ? g_timeout_efb : 1100;
function fun_render_view_efb(val, check) {
  
  var url = new URL(window.location);
  history.replaceState("EFBstep-1",null,url); 
  exportView_emsFormBuilder = [];
  valueJson_ws = JSON.parse(val.replace(/[\\]/g, ''));
  valueJson_ws[0].email ="";
  valj_efb = valueJson_ws;
  fun_gets_url_efb();
  formNameEfb = valj_efb[0].formName;
  state_efb = "run";
  previewFormEfb('run');
  if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic==1){
    stack_steps_efb.push(1);
    logic_ui_forms_efb();
  }
  if(form_type_emsFormBuilder=="payment"){
    setTimeout(() => {    
      fun_total_pay_efb()
    }, valj_efb.length *2);
  }
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
function fun_efb_run(){
  g_timeout_efb=100;  
  if (typeof window.jQuery != "function" || typeof jQuery != "function") {
    let msg = `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">  <strong>${ajax_object_efm.text.alert} </strong>${ajax_object_efm.text.jqinl}</div>`
     if(document.getElementById('body_efb')) document.getElementById('body_efb').innerHTML = msg;
     if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML = msg;
  } 
  (function () {
    jQuery(function () {
      if (typeof ajax_object_efm == 'undefined') return;
      //check if the form is private so no need to run the code 
      if(ajax_object_efm.ajax_value=="")return;
      if(document.getElementById('body_efb')==null && document.getElementById('body_tracker_emsFormBuilder')==null) check_body_efb_timer();
     // ajax_object_efm = deepFreeze_efb(ajax_object_efm);
      efb_var = ajax_object_efm; 
      //  efb_var = deepFreeze_efb(ajax_object_efm);   
       // efb_var = ajax_object_efm;
      poster_emsFormBuilder = ajax_object_efm.poster;
      poster_emsFormBuilder = deepFreeze_efb(poster_emsFormBuilder);
      ajax_object_efm.text = deepFreeze_efb(ajax_object_efm.text);
      efb_var.text = deepFreeze_efb(efb_var.text);
      lan_name_emsFormBuilder =efb_var.language.slice(0,2);
      pro_efb = efb_var.pro == '1' ? true : false;
      page_state_efb="public";
      
      setting_emsFormBuilder=JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, ''));
      if (ajax_object_efm.state != 'tracker') {
        const ajax_value = typeof (ajax_object_efm.ajax_value) == "string" ? JSON.parse(ajax_object_efm.ajax_value.replace(/[\\]/g, '')) : ajax_object_efm.ajax_value;
        if (ajax_object_efm.form_setting && ajax_object_efm.form_setting.length > 0 && ajax_object_efm.form_setting !== ajax_object_efm.text.settingsNfound) {
          form_type_emsFormBuilder = ajax_object_efm.type;
          const vs = setting_emsFormBuilder;
          addons_emsFormBuilder = vs.addons;
          if (ajax_object_efm.type != "userIsLogin") {
            if (Number(ajax_value[0].captcha )== 1) {           
              if(vs.siteKey.length<3){
                const vd =  alarm_emsFormBuilder(ajax_object_efm.text.formIsNotShown);
                // console.log(vd);
                document.getElementById('body_efb').innerHTML =vd;
                return;
              }
              if(sitekye_emsFormBuilder==2)alert(c_r_efb);
              sitekye_emsFormBuilder = vs.siteKey;
            } else { sitekye_emsFormBuilder = ""; }
          } else {
            form_type_emsFormBuilder = ajax_object_efm.type;
          }
        }
      }    
      if (ajax_object_efm.state !== 'settingError') {
        if (ajax_object_efm.state == 'form') {
          fun_render_view_efb(ajax_object_efm.ajax_value, 1);
        } else if (ajax_object_efm.state == 'tracker') {
          fun_tracking_show_emsFormBuilder()
        } else if (ajax_object_efm.state == 'settingError') {
          fun_show_alert_setting_emsFormBuilder()
        } else if (ajax_object_efm.state == 'userIsLogin') {
          document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(ajax_object_efm.ajax_value);
        }
      } else {
        fun_show_alert_setting_emsFormBuilder()
      }
    });
  })();
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
function createStepsOfPublic() {
  for (let el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    let price = '';
    if (el.type != "submit") {
      switch (el.type) {
        case "file":
          const ob = valueJson_ws.find(x => x.id_ === el.dataset.vid);
          if(((ob.hasOwnProperty("disabled") && ob.disabled!=true ) || ob.hasOwnProperty("disabled")==false )&& 
          ((ob.hasOwnProperty('hidden') && ob.hidden==true) || ob.hasOwnProperty('hidden')==false))
           files_emsFormBuilder.push({ id_: ob.id_, value: "@file@", state: 0, url: "", type: "file", name: ob.name, session: sessionPub_emsFormBuilder });
          break;
        case "hidden":
          break;
        case 'checkbox':
        case 'radio':
          document.getElementById(el.id).addEventListener("change", (e) => {
            if(el.dataset.tag && el.dataset.tag.includes("chl")!=false){
            }else if(el.classList.contains('payefb') && valj_efb[0].type == "form"){
            }
          })
          break;
      }
      el.addEventListener("change", (e) => {
        handle_change_event_efb(el);
      });
      if(el.type=="text" &&  el.classList.contains("hijri-picker")){
          $("#"+el.id).on('dp.change', function (arg) {
            if (!arg.date) {
                $("#selected-date").html('');
                return;
            };
            let date = arg.date;
            handle_change_event_efb(el);
        });
      }
    }else if(el.type=="checkbox" && valj_efb[0].type == "payment" && el.classList.contains('payefb')){
      fun_sendBack_emsFormBuilder(o[0]);
      fun_total_pay_efb()
    }  else if (el.type == "submit") {
      el.addEventListener("click", (e) => {
        const id_ = el.dataset.vid
        const ob = valueJson_ws.find(x => x.id_ === id_);
        let o = [{ id_: id_, name: ob.name, id_ob: el.id, amount: ob.amount, type: el.type, value: el.value, session: sessionPub_emsFormBuilder }];
        if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
          let q = valueJson_ws.find(x => x.id_ === el.id);
          const p = price_efb.length > 0 ? { price: price } : { price: q.price }
          Object.assign(o[0], p)
          fun_sendBack_emsFormBuilder(o[0]);
          fun_total_pay_efb()
        } else {
          fun_sendBack_emsFormBuilder(o[0]);
        }
      });
    }
  }
} 
function fun_sendBack_emsFormBuilder(ob) {
  if(typeof ob=='string' || ob.hasOwnProperty('value')==false ){return}
  remove_ttmsg_efb(ob.id_)
  if(ob.hasOwnProperty('value') && typeof(ob.value)!='number' && typeof(ob.value)!='object') {ob.value=fun_text_forbiden_convert_efb(ob.value);
  }else if(ob.hasOwnProperty('value') && ( typeof(ob.value)=='object') &&  ob.type=="maps" ){
    ob.value=ob.value;
  }
  if (sendBack_emsFormBuilder_pub.length>0) {
    let indx = get_row_sendback_by_id_efb(ob.id_);
    if (indx != -1 && ob.type != "switch" && (sendBack_emsFormBuilder_pub[indx].type == "checkbox" || sendBack_emsFormBuilder_pub[indx].type == "payCheckbox" || sendBack_emsFormBuilder_pub[indx].type == "multiselect" || sendBack_emsFormBuilder_pub[indx].type == "payMultiselect" || sendBack_emsFormBuilder_pub[indx].type == "chlCheckBox")) {
      indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_ && x.value == ob.value);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub.splice(indx, 1);
    }
    else if(indx != -1 && ob.value == "@file@" ){
      sendBack_emsFormBuilder_pub[indx]=ob;
    }else if(ob.type == "r_matrix"){  
      indx = sendBack_emsFormBuilder_pub.findIndex( x => x!=null && x.hasOwnProperty('id_ob') && x.id_ob === ob.id_ob);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub[indx]=ob;
    } else {
      if (indx == -1) { sendBack_emsFormBuilder_pub.push(ob) } else {
        if (typeof ob.price != "string") {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
        } else {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
          sendBack_emsFormBuilder_pub[indx].price = ob.price;
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
      <strong>${efb_var.text.alert} </strong>${val}
    </div>`
}
 function endMessage_emsFormBuilder_view() {
  let counter = 0;
  const btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? "logic_fun_prev_send()":"fun_prev_send()"
  const stepMax = currentTab_emsFormBuilder + 1;
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
      if (-1 == (get_row_sendback_by_id_efb(id))) valueExistsRequired += 1;
    }
  }
  if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  if (countRequired != valueExistsRequired && sendBack_emsFormBuilder_pub.length < 1) {
    let str = ""
    currentTab_emsFormBuilder = 0;
    document.getElementById('efb-final-step').innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb text-center">${efb_var.text.error}</h3> <span class="efb mb-2  text-center">${efb_var.text.pleaseMakeSureAllFields}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg " onClick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
  } else {
    let checkFile = 0;
    for (let file of files_emsFormBuilder) {
      if (files_emsFormBuilder.length > 0 && file.state == 1) {
        checkFile += 1;
      } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
        checkFile = -100;
        document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb font-weight-bold  text-center">File Error</h3> <span class="efb font-weight-bold  text-center">${efb_var.text.youNotPermissionUploadFile}</br>${file.url}</span>
         <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
        return;
      }
    }
    if (checkFile == 0) {
      if (files_emsFormBuilder.length > 0) {
        for (const file of files_emsFormBuilder) {
          if (get_row_sendback_by_id_efb(file.id_) == -1) { sendBack_emsFormBuilder_pub.push(file); localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub)); }
        }
      }
      if (validation_before_send_emsFormBuilder() == true){ actionSendData_emsFormBuilder()
      }
    } else {
      const timeValue = setInterval(function () {
        let checkFile = 0;
        for (let file of files_emsFormBuilder) {
          if (files_emsFormBuilder.length > 0 && file.state == 1) {
            checkFile += 1;
          } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
            checkFile = -100;
            document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb fs-4  text-center">File Error</h3> <span class="efb fs-6  text-center">${efb_var.text.youNotPermissionUploadFile}</br>${file.url}</span>
               <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
            return;
          }
        }
        if (checkFile == 0) {
          for (const file of files_emsFormBuilder) {
            sendBack_emsFormBuilder_pub.push(file);
            localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
          }
          if (validation_before_send_emsFormBuilder() == true){ 
            actionSendData_emsFormBuilder();
          }
          clearInterval(timeValue);
        }
      }, 1500);
    }
  }
}
function actionSendData_emsFormBuilder() {
  if (efb_var.type == "userIsLogin") return 0;
  if (form_type_emsFormBuilder != 'login') localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
  recaptcha_emsFormBuilder = valueJson_ws.length > 1 && valueJson_ws[0].hasOwnProperty('captcha') == true && valueJson_ws[0].captcha == true && typeof grecaptcha == "object" ? grecaptcha.getResponse() : "";
  if (!navigator.onLine) {
    response_fill_form_efb({ success: false, data: { success: false, m: efb_var.text.offlineMSend } });
    return;
  }
  form_type_emsFormBuilder = typeof valj_efb.length>2 ? valj_efb[0].type : form_type_emsFormBuilder
  let  data = {
      action: "get_form_Emsfb",
      value: JSON.stringify(sendBack_emsFormBuilder_pub),
      name: formNameEfb,
      id: efb_var.id,
      valid: recaptcha_emsFormBuilder,
      type:  form_type_emsFormBuilder,
      url:location.href.split('?')[0],
      sid:efb_var.sid,
      page_id: efb_var.page_id
    };
    if(valj_efb.length>0 && valj_efb[0].hasOwnProperty('type') && valj_efb[0].type=="payment" ){
      if(valj_efb[0].getway=="persiaPay"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendBack_emsFormBuilder_pub),
          name: formNameEfb,
          payid: sessionStorage.getItem("payId"),
          id: sessionStorage.getItem("id"),
          valid: recaptcha_emsFormBuilder,
          type:  form_type_emsFormBuilder,
          payment: 'persiaPay',
          auth:get_authority_efb,
          url:location.href.split('?')[0],
          sid:efb_var.sid,
          page_id: efb_var.page_id
        };
      }else if(valj_efb[0].getway=="stripe"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendBack_emsFormBuilder_pub),
          name: formNameEfb,
          id: efb_var.id,
          payid: efb_var.payId,
          valid: recaptcha_emsFormBuilder ,
          type: form_type_emsFormBuilder,
          payment: 'stripe',
          url:location.href.split('?')[0],
          sid:efb_var.sid,
          page_id: efb_var.page_id
        };
      }
    }
    post_api_forms_efb(data);
}
function valid_email_emsFormBuilder(el) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${efb_var.text.enterTheEmail}','',10,'danger');"></div>` : efb_var.text.enterTheEmail;
  let check = 0;
  //const format = /^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/;
  const format =/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  check += el.value.match(format) ? 0 : 1;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${el.id}-message`).classList.add('unpx');                
    }
    document.getElementById(`${el.id}-message`).innerHTML = msg;
    if(document.getElementById(`${el.id}-message`).classList.contains('show')==false)document.getElementById(`${el.id}-message`).classList.add('show');
    const i = get_row_sendback_by_id_efb(el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(typeof(sendback_state_handler_efb)=='function')sendback_state_handler_efb(el.dataset.vid,false,current_s_efb)
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
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${efb_var.text.enterThePassword}','',10,'danger');"></div>` : efb_var.text.enterThePassword;
  if (el.value.length < 3) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = get_row_sendback_by_id_efb(el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${id}-message`).classList.add('unpx');                
    }
    if(typeof(sendback_state_handler_efb)=='function')sendback_state_handler_efb(el.dataset.vid,false,current_s_efb)
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
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${efb_var.text.enterThePhones}','',10,'danger');"></div>` : efb_var.text.enterThePhones;
  let check = 0;
  const format = /^[\d\s-)(]{4,15}$/;
  const id = el.id;
  let msg_el = document.getElementById(`${id}-message`);
  check += el.value.match(format) ? 0 : 1;
  if (check >0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");    
    const i = get_row_sendback_by_id_efb(el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      msg_el.classList.add('unpx');                
    }
    msg_el.innerHTML = msg;
    if(msg_el.classList.contains('show')==false) msg_el.classList.add('show');
    if(typeof(sendback_state_handler_efb)=='function')sendback_state_handler_efb(el.dataset.vid,false,current_s_efb)
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    msg_el.innerHTML = ""
    msg_el.classList.remove('show');
  }
  return check > 0 ? false : true
}
function valid_file_emsFormBuilder(id,tp,filed) {
  let msgEl = document.getElementById(`${id}_-message`);
  msgEl.innerHTML = "";
  msgEl.classList.remove('show');
  document.getElementById(`${id}_`).classList.remove('border-danger');
  let file = ''
  if (true) {
    const f = valueJson_ws.find(x => x.id_ === id);
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
      const m =efb_var.text.pleaseUploadA.replace('NN', efb_var.text[val_in.file]);
      const size_m = efb_var.text.fileSizeIsTooLarge.replace('NN', f_s_l);
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
function fun_tracking_show_emsFormBuilder() {
  const time = pro_efb==true ? 10 :900;
  const getUrlparams = new URLSearchParams(location.search);
  efb_var.user_type = location.href.includes("user=admin") ? 'admin' : 'user';
  let get_track = getUrlparams.get('track') !=null ? sanitize_text_efb(getUrlparams.get('track')) :null;
  if(get_track){ get_track= `value="${get_track}"`; change_url_back_persia_pay_efb()}else{get_track='';}
setTimeout(() => {
  document.getElementById("body_tracker_emsFormBuilder").innerHTML = ` 
  <div class="efb  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" >
                <div class="efb row mb-3 pb-3 px-1" id="body_efb-track">
                    <h4 class="efb  title-holder  col-12 mt-4 fs-3"><i class="efb  bi-check2-square title-icon mx-1 fs-3"></i> ${efb_var.text.pleaseEnterTheTracking}</h4>
                <div class="efb  row col-md-12">
                        <label for="trackingCodeEfb" class="efb fs-6 form-label mx-2 col-12">
                        ${efb_var.text.trackingCode}:<span class="efb fs-8 text-danger mx-1">*</span></label>
                        <div class="efb  col-12 text-center mx-2 row">
                        <input type="text" class="efb input-efb form-control border-d rounded-4 text-labelEfb h-l-efb mb-2" placeholder="${efb_var.text.entrTrkngNo}" id="trackingCodeEfb" ${get_track}>
                         <!-- recaptcha  -->
                         ${setting_emsFormBuilder.scaptcha==true ? `<div class="efb  row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-2" data-sitekey="${setting_emsFormBuilder.siteKey}" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                         <!-- recaptcha end  -->
                         <button type="submit" class="efb fs-5  btn btn-pinkEfb col-12 text-white mb-1 "  id="vaid_check_emsFormBuilder" onclick="fun_vaid_tracker_check_emsFormBuilder()">
                        <i class="efb fs-5  bi-search"></i> ${efb_var.text.search}  </button>
                        </div>
                    </div>
                </div>
            <!-- efb -->            
        </div>
        <div id="alert_efb" class="efb mx-5"></div>
`
  if(setting_emsFormBuilder.scaptcha==true ){
    sitekye_emsFormBuilder=setting_emsFormBuilder.siteKey;
    loadCaptcha_efb();
  }
}, time);
}
function fun_get_tracking_code(){
}
function fun_vaid_tracker_check_emsFormBuilder() {
  if (!navigator.onLine) {
    noti_message_efb(efb_var.text.offlineSend , 'danger' , `body_efb-track` );      
    return;
  }
  const innrBtn = document.getElementById('vaid_check_emsFormBuilder').innerHTML;
  document.getElementById('vaid_check_emsFormBuilder').innerHTML = `<i class="efb fs-5 bi-hourglass-split"></i>`
  document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
  el = document.getElementById('trackingCodeEfb').value;
  if (el.length < 5) {
    document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
    document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
    noti_message_efb(efb_var.text.trackingCodeIsNotValid, 'danger' ,'body_efb-track')
  } else {
    if (currentTab_emsFormBuilder == 0) {
      const response = sitekye_emsFormBuilder ? grecaptcha.getResponse() || null : 'not';
      if (response == null) {
        document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
        document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
        noti_message_efb(efb_var.text.checkedBoxIANotRobot, 'danger' ,'body_efb-track')
      }
      else {
        sessionStorage.setItem('track', el);
        data = {
          action: "get_track_Emsfb",
          value: el,
          name: formNameEfb,
          valid: recaptcha_emsFormBuilder,
          nonce: efb_var.nonce,
          sid:efb_var.sid
        };
        recaptcha_emsFormBuilder = response;
        post_api_tracker_check_efb(data,innrBtn);    
      }
    }
  }
}
function emsFormBuilder_show_content_message(value, content) {
  const msg_id = value.msg_id;
  const userIp = "XXXXXXXXX";
  const track = value.track;
  const date = value.date ;
  const val = JSON.parse(replaceContentMessageEfb(value.content));
  let m = fun_emsFormBuilder_show_messages(val, '#first' ,'', track, date);
  for (let c of content) {
    const val = JSON.parse(c.content.replace(/[\\]/g, ''));
    // console.log(c);
    m += `<div class="efb   mb-3"><div class="efb  clearfix"> ${fun_emsFormBuilder_show_messages(val, c.rsp_by,'', track, c.date)}</div></div>`
  }
  let replayM = `<div class="efb mt-2"><div class="efb form-group mb-3" id="replay_section__emsFormBuilder">
  <label for="replayM_emsFormBuilder" class:'efb mx-1 fs-7" id="label_replyM_efb">${efb_var.text.reply}:</label>
  <textarea class="efb form-control border-d fs-6" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
  </div>
  <div class="efb col text-right row my-2 mx-1">
  <button type="submit" class="efb btn fs-5 round-2 btn-primary btn-lg" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">${efb_var.text.reply} </button>
  <!-- recaptcha  -->
  ${sitekye_emsFormBuilder ? `<div class="efb row mx-3"><div class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" id="recaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
  <!-- recaptcha end  -->
  <p class="efb mx-2 my-1 text-pinkEfb efb fs-7" id="replay_state__emsFormBuilder">  </p>
  </div></div>
  `
  const body = `
  <div class="efb modal-header efb py-4">
  <h5 class="efb modal-title fs-5 ">
   <!-- <i class="efb  bi-chat-square-text mx-2 mx-2 fs-5"></i>
   <span id="settingModalEfb-title">${efb_var.text.response}</span></h5> -->
 </div>
  <div class="efb modal-body overflow-auto py-0 my-0  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="resp_efb">
    ${m} 
   </div>
   ${replayM}
   </div>
   </div>
</div>
<div>
</div></div>`;
  return body;
}

function fun_send_replayMessage_emsFormBuilder(id) {
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  document.getElementById('replayB_emsFormBuilder').innerHTML =`<i class="efb fs-5 bi-hourglass-split mx-1"></i>`+efb_var.text.sending;
setTimeout(() => {
  let message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g, '@efb@nq#');
  message=sanitize_text_efb(message);
  const by = efb_var.user_name.length > 1 ? efb_var.user_name : efb_var.text.guest;
  const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: by , session: sessionPub_emsFormBuilder}];
  fun_sendBack_emsFormBuilder(ob[0])
  if (message.length < 1 ) {
    check_msg_ext_resp_efb();
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6 class="efb fs-6"><i class="efb bi-exclamation-triangle-fill nmsgefb"></i>${efb_var.text.error}${efb_var.text.pleaseEnterVaildValue}</h6>`;
    return;
  } else {
    if(setting_emsFormBuilder.hasOwnProperty('dsupfile')==true && setting_emsFormBuilder.dsupfile !=true) {
      for(const s in sendBack_emsFormBuilder_pub ){ if(sendBack_emsFormBuilder_pub[s].name=="file") sendBack_emsFormBuilder_pub.splice(s,1)  }
    }
    fun_send_replayMessage_reast_emsFormBuilder(sendBack_emsFormBuilder_pub)
  }
}, 100);
}
function fun_send_replayMessage_reast_emsFormBuilder(message) {
  if (!navigator.onLine) {
    noti_message_efb(efb_var.text.offlineSend , 'danger' , `replay_state__emsFormBuilder` );       
    return;
  }
  f_btn =()=>{
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    document.getElementById('replayM_emsFormBuilder').innerHTML = "";
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
  }
  if (message.length < 1) {
    f_btn();
    return;
  }
  const track = sessionStorage.getItem('track') ?? 'null';
  data = {
    action: "set_rMessage_id_Emsfb",
    type: "POST",
    id: efb_var.msg_id,
    valid: recaptcha_emsFormBuilder,
    message: JSON.stringify(message),
    type: form_type_emsFormBuilder,
    sid:efb_var.sid,
    user_type : efb_var.user_type,
    page_id: efb_var.page_id,
    sc:efb_var.sc,
    track: track
  };
  // console.log(data);
  post_api_r_message_efb(data,message);
}
function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
 
  const resp = fun_emsFormBuilder_show_messages(message, by, '',track, date);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}
function fun_show_alert_setting_emsFormBuilder() {
  const m = `<div class="efb alert alert-danger" role="alert"> <h2 class="efb font-weight-bold">
            ${efb_var.text.error}</br>
            ${efb_var.text.formIsNotShown}</br>
            <a href="https://www.youtube.com/embed/a1jbMqunzkQ"  target="_blank" class="efb font-weight-normal">${efb_var.text.pleaseWatchTutorial}</a> </h2> </div>`
  if (document.getElementById('body_emsFormBuilder')) {
    document.getElementById('body_emsFormBuilder').innerHTML = m;
  } else if (document.getElementById('body_tracker_emsFormBuilder')) {
    document.getElementById('body_tracker_emsFormBuilder').innerHTML = m;
  } else {
    window.alert(`${efb_var.text.error} ${efb_var.text.formIsNotShown}`)
  }
}
function validation_before_send_emsFormBuilder() {
  const btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? "logic_fun_prev_send()":"fun_prev_send()"
  const count = [0, 0]
  let fill = 0;
  let require = 0;
  for (const v of valueJson_ws) {
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
      //slice by count_ on sendBack_emsFormBuilder_pub
      count_ -= 1;
      sendBack_emsFormBuilder_pub.splice(count_,1);
      continue;
    }
    count[0] += 1;
    if (row.value == "@file@") {
      const indx = valueJson_ws.findIndex(x => x.id_ == row.id_);
      if (indx != -1) {
        if(valueJson_ws[indx].hasOwnProperty("disabled") && valueJson_ws[indx].disabled==true && 
           ((valueJson_ws[indx].hasOwnProperty('hidden') && valueJson_ws[indx].hidden==false) || valueJson_ws[indx].hasOwnProperty('hidden')==false)
          ){           
            const i = get_row_sendback_by_id_efb(valueJson_ws[indx].id_);
            sendBack_emsFormBuilder_pub.splice(i,1);
            count[0] -= 1;
            continue 
          }
        if (row.url.length > 5) {
          fill += valueJson_ws[indx].required == true ? 1 : 0;
          count[1] += 1;
        }
      }
    } else if (row.type != "@file@" && row.type != "payment") {
      const indx = valueJson_ws.findIndex(x => x.id_ == row.id_);
      if(indx!=-1){
        if ( (valueJson_ws[indx].type == "multiselect" || valueJson_ws[indx].type == "option" || valueJson_ws[indx].type == "Select"
        || valueJson_ws[indx].type == "payMultiselect" || valueJson_ws[indx].type == "paySelect")) {
        const exists = valueJson_ws[indx].type == "multiselect" || valueJson_ws[indx].type == "payMultiselect" ? valueJson_ws.findIndex(x => x.parent == valueJson_ws[indx].id_) : valueJson_ws.findIndex(x => x.parents == valueJson_ws[indx].id_);
        fill += valueJson_ws[indx].required == true && exists > -1 ? 1 : 0;
      }else if(valueJson_ws[indx].type == "chlCheckBox"){
          const exists = valueJson_ws.findIndex(x => x.parents == valueJson_ws[indx].id_)
          fill += valueJson_ws[indx].required == true && exists > -1 ? 1 : 0;
        }else {
          fill += valueJson_ws[indx].required == true ? 1 : 0;
        }
      if (row.value.length > 0) count[1] += 1;
      }
    } else {
      if (row.value.length > 0) count[1] += 1;
    }
  }
  require = require > fill ? 1 : 0;
  if (((count[1] == 0 && count[0] != 0) || (count[0] == 0 && count[1] == 0) || require == 1) && ( (valj_efb[0].hasOwnProperty("logic")== true || valj_efb[0].hasOwnProperty("logic")==false) && valj_efb.logic==false )) {
    document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center fs-2 efb"></i></h1><h3 class="efb fs-3 efb text-muted">${efb_var.text.error}</h3> <span class="efb mb-2 fs-5 efb text-muted"> ${require != 1 ? efb_var.text.PleaseFillForm : efb_var.text.pleaseFillInRequiredFields} </br></span>
     <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner:'efb-square'}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
    for (const v of valueJson_ws) {
      if (v.type != 'file' && v.type != 'dadfile' && v.type != 'checkbox' && v.type != 'radiobutton' && v.type != 'option' && v.type != 'multiselect' && v.type != 'select' && v.type != 'payMultiselect' && v.type != 'paySelect' && v.type != 'payRadio' && v.type != 'payCheckbox' && v.type != 'chlCheckBox') {
        (v.id_ && document.getElementById(v.id_).value.length < 5) ? document.getElementById(`${v.id_}-message`).innerHTML = efb_var.text.enterTheValueThisField : 0
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
              <button type="button"  class="efb btn fs-5 btn-lg btn-danger efb mt-1 " onclick="emsFormBuilder_logout()">${efb_var.text.logout}</button>
          </div> </div>`
}
function emsFormBuilder_logout() {
  document.getElementById('body_efb').innerHTML = loading_messge_efb();
  form_type_emsFormBuilder = "logout";
  formNameEfb = "logout";
 // efb_var.type = "logout";
  sendBack_emsFormBuilder_pub = { logout: true };
  recaptcha_emsFormBuilder = '';
  actionSendData_emsFormBuilder();
}
function Show_recovery_pass_efb() {
  let el = document.getElementById(`recoverySectionemsFormBuilder`);
  el.style.display = el.style.display == "none" ? "block" : "none";
  document.getElementById('recoverySectionemsFormBuilder').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  el = document.getElementById('btn_recovery_pass_efb');
  el.disabled = true;
  if (el.dataset.id == 1) {
    el.dataset.id = 0;
    const us = document.getElementById('username_recovery_pass_efb');
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    el.addEventListener("click", (e) => {
      form_type_emsFormBuilder = "recovery";
      formNameEfb = form_type_emsFormBuilder;
      sendBack_emsFormBuilder_pub = { email: us.value ,'recovery':true};
      document.getElementById('efb-final-step').innerHTML = `<h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3 class="efb text-center">${efb_var.text.pleaseWaiting}<h3>`
      actionSendData_emsFormBuilder()
    })
    us.addEventListener("keyup", (e) => {
      const check = us.value.match(format) ? 0 : 1;
      if (check == 0) { el.classList.remove('disabled') } else { el.classList.contains('disabled') != true ? el.classList.add('disabled') : 0 }
    })
  }
}
function response_fill_form_efb(res) {
  let btn_prev ='';
  if(valj_efb.length>1) btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? "logic_fun_prev_send()":"fun_prev_send()"
  if (res.data.success == true) {
    if(valj_efb.length>0 && valj_efb[0].hasOwnProperty('thank_you')==true && valj_efb[0].thank_you=='rdrct'){
      document.getElementById('efb-final-step').innerHTML = `
      <h3 class="efb fs-4 text-center">${efb_var.text.sentSuccessfully}</h3>
      <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${res.data.m}">${efb_var.text.orClickHere}</a></h3>
      `      
      window.location.href = res.data.m;
      return ;
    }
    switch (form_type_emsFormBuilder) {
      case 'form':
      case 'survey':
      case 'payment':
        document.getElementById('efb-final-step').innerHTML = funTnxEfb(res.data.track)
        localStorage.clear();
        break;
      case 'subscribe':
        document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder fs-4'><i class="efb fs-2 bi-hand-thumbs-up text-primary  text-center"></i></h3><h3 class='efb emsFormBuilder fs-5  text-center'>${valj_efb[0].thank_you_message.thankYou}</h3></br> <span class="efb fs-5">${efb_var.text.YouSubscribed}</span></br></br></h3>`;
        localStorage.clear();
        break;
      case 'register':
          const m = form_type_emsFormBuilder !='recovery' ? valj_efb[0].thank_you_message.thankYou: efb_var.text.checkYourEmail;
          document.getElementById('efb-final-step').innerHTML = funTnxEfb('','',m );
          break;
      case 'recovery':
        document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder fs-4  text-center'><i class="efb fs-2 bi-envelope text-primary  text-center"></i></h3><h3 class='efb emsFormBuilder fs-5  text-center'>${res.data.m}</h3></br></br></h3>`;
      break;
      case 'login':
        if (res.data.m.state == true) {
          document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(res.data.m);
          location.reload();
        } else {
          document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder text-center fs-5 efb mb-0 mt-5  text-center'><i class="efb fs-2 bi-exclamation-triangle-fill nmsgefb  text-center"></i></h3> <span class="efb fs-7  text-center"> <br>${res.data.m.error}</span>
           </br>
           <a  id="btn_Show_recovery_efb" class="efb pointer-efb emsFormBuilder " onClick="Show_recovery_pass_efb()" >${efb_var.text.passwordRecovery} </a>
           <div class="efb py-5 px-2 container bg-light mb-3" id="recoverySectionemsFormBuilder" style="display: none;">     
              <input type="email" id="username_recovery_pass_efb" class="efb px-2 mb-1 emsFormBuilder_v w-100 bg-white  h-d-efb efb-square-1 col-8 border border-dark" placeholder="Email" >
              <a  id="btn_recovery_pass_efb" class=" efb btn h-d-efb btn-block btn-pinkEfb text-white mb-2 get-emsFormBuilder disabled" data-id="1" >${efb_var.text.send}</a>
              </div>
              <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner:'efb-square'}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div>
              `;
        }
        break;
      case "logout":
        location.reload();
        localStorage.clear();
        break;
    }
    if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  } else {
    if(document.getElementById('efb-final-step')){document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder text-center'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center efb fs-3  text-center"></i></h1><h3 class="efb  text-center fs-3 text-muted">${efb_var.text.error}</h3> <span class="efb mb-2 efb fs-5"> ${res.data.m}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].hasOwnProperty('button_color') ? valj_efb[0].button_color : 'btn-darkb'}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'efb-square'}   ${valj_efb[0].hasOwnProperty('el_height') ? valj_efb[0].el_height : 'h-l-efb'}  p-2 text-center  btn-lg  " onClick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    }else{
      alert_message_efb(res.data.m,'',14,'warning');
    }
  }
}
function response_Valid_tracker_efb(res) {
  if (res.data.success == true) {
    document.getElementById('body_efb-track').innerHTML = emsFormBuilder_show_content_message(res.data.value, res.data.content)
    setTimeout(() => {
     if(typeof reply_attach_efb =='function') reply_attach_efb(res.data.value.msg_id)
     state_rply_btn_efb(100)
    }, 50);
    document.getElementById('body_efb-track').classList.add('card');
  } else {
    document.getElementById('body_efb-track').innerHTML = `<div class="efb text-center"><h3 class='efb emsFormBuilder mt-3  text-center'><i class="efb nmsgefb  bi-exclamation-triangle-fill text-center efb fs-1"></i></h1><h3 class="efb  fs-3 text-muted  text-center">${efb_var.text.error}</h3> <span class="efb mb-2 efb fs-5 mx-1">${efb_var.text.somethingWentWrongTryAgain} </br></br> ${res.data.m} </br></span>
     <div class="efb display-btn emsFormBuilder"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="efb  btn btn-darkb m-5 text-white" onClick="(() => {  location.reload(); })()" style="display;"><i class="efb ${efb_var.rtl == 1 ? 'bi-arrow-right' : 'bi-arrow-left'}"></i></button></div></div>`;
  }
}
function response_rMessage_id(res, message) {
  if (res.success == true && res.data.success == true) {
    document.getElementById('replayM_emsFormBuilder').value = "";
    document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    document.getElementById('replayB_emsFormBuilder').innerHTML =efb_var.text.reply;
     if(document.getElementById('name_attach_efb')) document.getElementById('name_attach_efb').innerHTML =efb_var.text.file
    const date = Date();
    fun_emsFormBuilder__add_a_response_to_messages(message, res.data.by, 0, 0, date);
    const chatHistory = document.getElementById("resp_efb");
    chatHistory.scrollTop = chatHistory.scrollHeight;
  } else {
    document.getElementById('replayB_emsFormBuilder').innerHTML =efb_var.text.reply;
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<p class="efb text-danger bg-warning p-2">${res.data.m}</p>`;
  }
}
function loadCaptcha_efb() {
  if (!window.grecaptcha || !window.grecaptcha.render) {
    setTimeout(() => {
      this.loadCaptcha_efb();
    }, 500);
  } else {
    if (valj_efb.length!=0  && valj_efb[0].steps == 1)  document.getElementById('btn_send_efb').classList.toggle('disabled'); 
    grecaptcha.render('gRecaptcha', {
      'sitekey': sitekye_emsFormBuilder
    });
  }
};
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
   //if(efb_var.pro!=true && efb_var.pro!="true"){console.error(`${efb_var.text.fieldAvailableInProversion}`);return;}
   //iefb --> id 
   //hefb --> hidden of element f==show / null or t === hidden
   //sefb --> selected  t = selected / f=unselected / null == selected
   //defb --> disabled  t = disabled / f=enabled / null == don't change pro
  const getUrlparams = new URLSearchParams(location.search)
  const iefb =  getUrlparams.getAll("iefb");  //id field
  const hefb =  getUrlparams.getAll("hefb"); //hidden field
  const sefb =  getUrlparams.getAll("sefb"); //selected field
  const defb =  getUrlparams.getAll("defb"); //disabled field
  const vefb =  getUrlparams.getAll("vefb"); //value for field
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
       //1= disabled
       //2= enabled
       //n= don't change pro
       i_p=  i_p!=-1 ? i_p : i_
       if(defb[i]==1){
        valj_efb[i_p].disabled=1          
       }else if(defb[i]==0){
        valj_efb[i_p].disabled=0    
       }
      }
      if(hefb.length>0 && hefb.length>i){
        //hidden =1
        i_p=  i_p!=-1 ? i_p : i_
        if(hefb[i]==1){
          valj_efb[i_p].hidden=1            
         }else if(hefb[i]==0){
          valj_efb[i_p].hidden=0  
         }
      }
      if(vefb.length>0 && vefb.length>i && i_p==-1){
        valj_efb[i].value = vefb[i];
        //vefb =1
        //vefb == string  added to value
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
 post_api_forms_efb=(data)=>{
    const url = efb_var.rest_url+'Emsfb/v1/forms/message/add';
const headers = new Headers({
  'Content-Type': 'application/json',
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
      throw new Error('Network response was not ok');
    }
    return response.json();
  })
  .then(responseData => {
    response_fill_form_efb(responseData);
    if(localStorage.getItem('sendback'))localStorage.removeItem('sendback')
  })
  .catch(error => {
    console.error(error);
    response_fill_form_efb({ success: false, data: { success: false, m: efb_var.text.eJQ500 }});
  });
  if(document.getElementById('prev_efb') && document.getElementById('prev_efb').classList.contains('d-none')==false)document.getElementById('prev_efb').classList.add('d-none')
  if(document.getElementById('next_efb') && document.getElementById('next_efb').classList.contains('d-none')==false)document.getElementById('next_efb').classList.add('d-none')
}
post_api_tracker_check_efb=(data,innrBtn)=>{
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/get';
  const headers = new Headers({
    'Content-Type': 'application/json',
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
    console.error(error.message);
    if (document.getElementById('vaid_check_emsFormBuilder')) {
      document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn;
      document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled');
    }
    response_Valid_tracker_efb({ success: false, data: { success: false, m: error.message } });
  });
}
post_api_r_message_efb=(data,message)=>{
  // console.log(data);
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/add';
  const headers = new Headers({
    'Content-Type': 'application/json',
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
      console.error(error.message);
      response_Valid_tracker_efb({ success: false, data: { success: false, m: error.message } });
    });
}
sendback_state_handler_efb=(id_,state,step)=>{
  const indx = sendback_efb_state.findIndex(x=>x.id_==id_);
  if(indx==-1 && state==false){
    sendback_efb_state.push({id_:id_,state:state,step:step})
    if(document.getElementById('btn_send_efb') && document.getElementById('btn_send_efb').classList.contains('disabled')==false )document.getElementById('btn_send_efb').classList.add('disabled');
    else if(document.getElementById('next_efb') && document.getElementById('next_efb').classList.contains('disabled')==false )document.getElementById('next_efb').classList.add('disabled');
  }else if(indx>-1 && state==true && sendback_efb_state.length>0){
    //remove for  sendback_efb_state by id_
    sendback_efb_state.splice(indx,1);
    //get sendback_efb_state by step if exists return true else return false
    setTimeout(() => {
      const indx_ = sendback_efb_state.findIndex(x=>x.step==step);
      if(indx_==-1 || sendback_efb_state.length==0){
        if(document.getElementById('btn_send_efb'))document.getElementById('btn_send_efb').classList.remove('disabled');
        else if(document.getElementById('next_efb'))document.getElementById('next_efb').classList.remove('disabled');
      }      
    }, 200);
  }
}
document.addEventListener("DOMContentLoaded", function() {
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
});