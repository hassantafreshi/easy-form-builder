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
let valj_efb_new = [];
//+ let valj_efb = [];

console.log(ajax_object_efm);
if (typeof(ajax_object_efm)=='object' && ajax_object_efm.hasOwnProperty('ajax_value') && typeof ajax_object_efm.ajax_value == "string") {
  g_timeout_efb = (g_timeout_efb, ajax_object_efm.ajax_value.match(/id_/g) || []).length;
  g_timeout_efb = g_timeout_efb * calPLenEfb(g_timeout_efb);
  ajax_object_efm.ajax_value_forms.forEach(form => {
    form.form_structer = JSON.parse(form.form_structer.replace(/\\/g, ''));
  });
  valj_efb_new = ajax_object_efm.ajax_value_forms
  console.log(valj_efb_new);
}
g_timeout_efb = typeof ajax_object_efm == "object" && typeof ajax_object_efm.ajax_value == "string" ? g_timeout_efb : 1100;
function fun_render_view_efb(val, check) {
  
  var url = new URL(window.location);
  history.replaceState("EFBstep-1",null,url); 
  exportView_emsFormBuilder = [];
  valueJson_ws = JSON.parse(val.replace(/[\\]/g, ''));
  valj_efb = valueJson_ws;
  fun_gets_url_efb();
  //formNameEfb = valj_efb[0].formName;
  state_efb = "run";
  //previewFormEfb('run');
  /* if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic==1){
    stack_steps_efb.push(1);
    logic_ui_forms_efb();
  }
  if(form_type_emsFormBuilder=="payment"){
    setTimeout(() => {    
      fun_total_pay_efb()
    }, valj_efb.length *2);
  } */
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
    
    if (typeof window.jQuery != "function" || typeof jQuery != "function") {
      let msg = `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
        <strong>${ajax_object_efm.text.alert}</strong> ${ajax_object_efm.text.jqinl}
      </div>`;
      
      if(document.getElementById('body_efb')) document.getElementById('body_efb').innerHTML = msg;
      if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML = msg;
      return;
    }
  
    jQuery(() => {
      if (typeof ajax_object_efm === 'undefined' || !ajax_object_efm.ajax_value) return;
  
      if (!document.getElementById('body_efb') && !document.getElementById('body_tracker_emsFormBuilder')) {
        check_body_efb_timer();
      }
  
      poster_emsFormBuilder = deepFreeze_efb(ajax_object_efm.poster);
      ajax_object_efm.text = deepFreeze_efb(ajax_object_efm.text);
      lan_name_emsFormBuilder = ajax_object_efm.language.slice(0, 2);
      pro_efb = ajax_object_efm.pro == '1' ? true : false;
      page_state_efb = "public";
  
      console.log(`state_efb = [${state_efb}]`);
      console.log(ajax_object_efm.form_setting);
      setting_emsFormBuilder=typeof ajax_object_efm.form_settingJSON=='string' ? JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, '')) : ajax_object_efm.form_settingJSON;
      efb_var = ajax_object_efm;
      efb_var.text = deepFreeze_efb(efb_var.text); 
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
// v4  start for parsing of elements of forms for add event listener by call function handle_change_event_efb and store value in sendtoback varible by call function fun_sendBack_emsFormBuilder


async function createStepsOfPublic() {
 // let form_id= 0;
 state_efb = "run";
 efb_var = ajax_object_efm;
 setting_emsFormBuilder = typeof ajax_object_efm.form_setting == "string" ? JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, '')) : ajax_object_efm.form_setting;
  for (let el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    let form_id = el.dataset.formid  || 0;
    console.log(`tagName[${el.tagName}] el.type[${el.type}] form_id[${form_id}]`);
    if (el.tagName == "OPTION" || el.tagName=='P' || el.tagName=='A' || ( el.tagName=='INPUT' && el.type=='hidden')) continue;
    // form_id = Number(form_id);
    let price = '';
    let el_type =''
    console.log(el,el.type,form_id ,el.id);
    const valj_efb_ =get_structure_by_form_id_efb(form_id);
    const id = el.dataset.vid ?? '';
    const classes = el.classList;
    //if el is option of select return
    if ('type' in el) {
      if(el.type=="checkbox" && valj_efb_[0].type == "payment" && classes.contains('payefb')){
        console.log('checkbox');
        console.log('166');
        fun_sendBack_emsFormBuilder(o[0]);
        fun_total_pay_efb()
      }else if (el.type != "submit") {
        console.log(`type:[${el.type}]`);
        switch (el.type) {
          case "text":
            //pdpF2
            console.log('text');
            if (classes.contains("pdpF2")) {
              call_fun_jalali_datepicker_efb_v4();
            }else if (classes.contains("hijri-picker")) {
              call_fun_hijri_datapicker_efb_v4();
              $("#"+el.id).on('dp.change', function (arg) {
                if (!arg.date) {
                    $("#selected-date").html('');
                    return;
                };
                let date = arg.date;
                handle_change_event_efb_v4(el,form_id);
              });
            }else if (classes.contains("intlPhone") && classes.contains("d-none")==false) {
              console.log('>>>>>>>>>>phone');
              //load_intlTelInput_efb(rndm,iVJ)
              const indx = valj_efb_.findIndex(x => x.id_ === id);
              valj_efb=valj_efb_;
              load_intlTelInput_efb(id,indx);
              /*   let value = valj_efb.find(x => x.id_ === id);
                if(sendBack_emsFormBuilder_pub.length>0){
                  let indx = get_row_sendback_by_id_efb_v4(id,form_id);
                  if(indx!=-1){
                    value.value = sendBack_emsFormBuilder_pub[indx].value;
                  }
                }
                let v = value.value.split('+');
              
                let storedPhoneNumber =value.value;
                if(v.length==3){
                  storedPhoneNumber ='+'+ v[2];
                }
                const country =efb_var.wp_lan.split('_')[1].toUpperCase();
                const iti = window.intlTelInput(el, {
                    initialCountry: country,
                    utilsScript:  efb_var.images.utilsJs
                });
                iti.setNumber(storedPhoneNumber); */

            }
          break;
          case "file":
            console.log('>>file');
            const ob = valj_efb_.find(x => x.id_ === id);
            if(((ob.hasOwnProperty("disabled") && ob.disabled!=true ) || ob.hasOwnProperty("disabled")==false )&& 
            ((ob.hasOwnProperty('hidden') && ob.hidden==true) || ob.hasOwnProperty('hidden')==false))
          
            files_emsFormBuilder.push({ id_: ob.id_, value: "@file@", state: 0, url: "", type: "file",state:0, name: ob.name, session: sessionPub_emsFormBuilder, form_id: form_id });
          break;
          case "hidden":
            console.log('hidden');
            console.log(el);
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
          break;
          case 'select-one':
          break;
      /*     case 'maps':
            console.log('maps!');
            const c = valj_efb_.find(x => x.id_ === id);
            const points = Number(c.mark);
            efbCreateMap(id,c,points,false)    
          break; */
          }
          el.addEventListener("change", async(e) => {
            await handle_change_event_efb_v4(el,form_id);
          });
        /*  if(el.type=="text" &&  classes.contains("hijri-picker")){
              $("#"+el.id).on('dp.change', function (arg) {
                if (!arg.date) {
                    $("#selected-date").html('');
                    return;
                };
                let date = arg.date;
                handle_change_event_efb_v4(el,form_id);
            });
          } */
      }else if (el.type == "submit") {
        el.addEventListener("click", (e) => {
          console.log('submit');
          const id_ = el.dataset.vid
          const form_id = el.dataset.formid || -1;
          const ob = valueJson_ws.find(x => x.id_ === id_);
          let o = [{ id_: id_, name: ob.name, id_ob: el.id, amount: ob.amount, type: el.type, value: el.value, session: sessionPub_emsFormBuilder, form_id: form_id }];
          if (valj_efb_[0].type == "payment" && classes.contains('payefb')) {
            let q = valueJson_ws.find(x => x.id_ === el.id);
            const p = price_efb.length > 0 ? { price: price } : { price: q.price }
            Object.assign(o[0], p)
            console.log('179');
            fun_sendBack_emsFormBuilder(o[0]);
            fun_total_pay_efb()
          } else {
            console.log('182');
            fun_sendBack_emsFormBuilder(o[0]);
          }
        });
      }
    }else if (el.classList.contains('maps-efb')){
      
        console.log('maps!!');
        const c = valj_efb_.find(x => x.id_ === id);
        const parents = el.parentNode
        if(!c.hasOwnProperty('formid'))Object.assign(c , {'formid':parents.dataset.formid})
          efbCreateMap(id,c,false)                    
    }
    el.addEventListener("change", async(e) => {
      await handle_change_event_efb_v4(el,form_id);
    });
  }
  //disable-efb
 /*  if(form_id>0){
    document.getElementById(`body_efb_${form_id}`).classList.remove('disable-efb');
  }
  console.log('186'); */
//  fun_create_event_buttons_efb();
} 
function fun_sendBack_emsFormBuilder(ob) {
  const form_id = ob.form_id || -1;
  console.log(`form_id[${form_id}]`);
  console.log(ob);
  if(typeof ob=='string' || ob.hasOwnProperty('value')==false ){return}
  remove_ttmsg_efb(ob.id_)
  if(ob.hasOwnProperty('value') && typeof(ob.value)!='number' && typeof(ob.value)!='object') {ob.value=fun_text_forbiden_convert_efb(ob.value);
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
      <strong>${ajax_object_efm.text.alert} </strong>${val}
    </div>`
}
 async function endMessage_emsFormBuilder_view(current_step,form_id) {
  console.log(`endMessage_emsFormBuilder_view form_id`, form_id)
  
  fun_check_upload_files_complate_efb = (form_id) => {
    let checkFile_uploaded = 0;
    let countFile_notUloaded = 0;
    for (let file of files_emsFormBuilder) {
      console.log(`file>[${file.state}]`);
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
      if (-1 == (get_row_sendback_by_id_efb(id))) valueExistsRequired += 1;
    }
  }
  const id_body ='body_efb_'+form_id
  const body_efb = document.getElementById(id_body);
  const efb_final_step =body_efb.querySelector('#efb-final-step');
  // if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
 // smoothy_scroll_postion_efb('body_efb');
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
    console.log(`checkFile[${checkFile}]`);
    if (checkFile == 0) {
      if (files_emsFormBuilder.length > 0) {
        for (const file of files_emsFormBuilder) {
          if (get_row_sendback_by_id_efb(file.id_) == -1) {
             sendBack_emsFormBuilder_pub.push(file); 
             localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub)); }
        }
      }
      const state = await validation_before_send_efb(form_id);
      if ( state == true){ actionSendData_emsFormBuilder(form_id)
      }
    } else {
        // + اینجا بررسی شود برای آپلود فایل
        let checkFile = 0;
        for (let file of files_emsFormBuilder) {
          console.log(`file[${file.state}]`);
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
        console.log('before if and checkFile', checkFile);
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
  console.log('actionSendData_emsFormBuilder fomId', form_id);
  const sendback = sendBack_emsFormBuilder_pub.filter(x=>x.form_id==form_id);
  console.log('sendback', sendback);
  
  console.log('sendback', sendback);
  const vj =  fun_sid_efb(form_id);
  let formNameEfb = vj.formName;
  console.log(vj )
  if (ajax_object_efm.type == "userIsLogin") return 0;
  if (form_type_emsFormBuilder != 'login') localStorage.setItem('sendback', JSON.stringify(sendback));
  recaptcha_emsFormBuilder = valueJson_ws.length > 1 && valueJson_ws[0].hasOwnProperty('captcha') == true && valueJson_ws[0].captcha == true && typeof grecaptcha == "object" ? grecaptcha.getResponse() : "";
  if (!navigator.onLine) {
    await response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.offlineMSend } },form_id);
    return;
  }
  form_type_emsFormBuilder = typeof valj_efb.length>2 ? valj_efb[0].type : form_type_emsFormBuilder
  let  data = {
      action: "get_form_Emsfb",
      value: JSON.stringify(sendback),
      name: formNameEfb,
      id: form_id,
      valid: recaptcha_emsFormBuilder,
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
          valid: recaptcha_emsFormBuilder,
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
          valid: recaptcha_emsFormBuilder ,
          type: vj.type,
          payment: 'stripe',
          url:location.href.split('?')[0],
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      }
    }
    console.log('data',data);
    post_api_forms_efb(data,form_id);
}
function valid_email_emsFormBuilder(el) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterTheEmail}','',10,'danger');"></div>` : ajax_object_efm.text.enterTheEmail;
  let check = 0;
  // const format = /^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/;
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
  //const format = /^[\d\s-)(]{4,15}$/;
  const format = /^\+?[0-9\s\-()]{7,20}$/;
  const id = el.id;
  const form_id = el.dataset.formid || 0;
  let msg_el = document.getElementById(`${id}-message`);
  check += el.value.match(format) ? 0 : 1;
  if (check >0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");    
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
function valid_file_emsFormBuilder(id,tp,filed) {
  console.log('valid_file_emsFormBuilder!!!');
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
function fun_tracking_show_emsFormBuilder() {
  const time = pro_efb==true ? 10 :900;
  const getUrlparams = new URLSearchParams(location.search);
  efb_var.user_type = location.href.includes("user=admin") ? 'admin' : 'user';
  let get_track = getUrlparams.get('track') !=null ? sanitize_text_efb(getUrlparams.get('track')) :null;
  if(get_track){ get_track= `value="${get_track}"`; change_url_back_persia_pay_efb()}else{get_track='';}
setTimeout(() => {
  document.getElementById("body_tracker_emsFormBuilder").innerHTML = ` 
  <div class="efb  ${ajax_object_efm.rtl == 1 ? 'rtl-text' : ''}" >
                <div class="efb row mb-3 pb-3 px-1" id="body_efb-track">
                    <h4 class="efb  title-holder  col-12 mt-4 fs-3"><i class="efb  bi-check2-square title-icon mx-1 fs-3"></i> ${ajax_object_efm.text.pleaseEnterTheTracking}</h4>
                <div class="efb  row col-md-12">
                        <label for="trackingCodeEfb" class="efb fs-6 form-label mx-2 col-12">
                        ${ajax_object_efm.text.trackingCode}:<span class="efb fs-8 text-danger mx-1">*</span></label>
                        <div class="efb  col-12 text-center mx-2 row">
                        <input type="text" class="efb input-efb form-control border-d rounded-4 text-labelEfb h-l-efb mb-2" placeholder="${ajax_object_efm.text.entrTrkngNo}" id="trackingCodeEfb" ${get_track}>
                         <!-- recaptcha  -->
                         ${setting_emsFormBuilder.scaptcha==true ? `<div class="efb  row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-2" data-sitekey="${setting_emsFormBuilder.siteKey}" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                         <!-- recaptcha end  -->
                         <button type="submit" class="efb fs-5  btn btn-pinkEfb col-12 text-white mb-1 "  id="vaid_check_emsFormBuilder" onclick="fun_vaid_tracker_check_emsFormBuilder()">
                        <i class="efb fs-5  bi-search"></i> ${ajax_object_efm.text.search}  </button>
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
    noti_message_efb_v4(efb_var.text.offlineSend , 'danger' , `body_efb-track`,0 );      
    return;
  }
  const innrBtn = document.getElementById('vaid_check_emsFormBuilder').innerHTML;
  document.getElementById('vaid_check_emsFormBuilder').innerHTML = `<i class="efb fs-5 bi-hourglass-split"></i>`
  document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
  el = document.getElementById('trackingCodeEfb').value;
  if (el.length < 5) {
    document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
    document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
    noti_message_efb_v4(ajax_object_efm.text.trackingCodeIsNotValid, 'danger' ,'body_efb-track',0)
  } else {
    if (currentTab_emsFormBuilder == 0) {
      const response = sitekye_emsFormBuilder ? grecaptcha.getResponse() || null : 'not';
      if (response == null) {
        document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
        document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
        noti_message_efb_v4(ajax_object_efm.text.checkedBoxIANotRobot, 'danger' ,'body_efb-track',0)
      }
      else {
        sessionStorage.setItem('track', el);
        data = {
          action: "get_track_Emsfb",
          value: el,
          name: formNameEfb,
          valid: recaptcha_emsFormBuilder,
          nonce: ajax_object_efm.nonce,
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
  <label for="replayM_emsFormBuilder" class:'efb mx-1 fs-7" id="label_replyM_efb">${ajax_object_efm.text.reply}:</label>
  <textarea class="efb form-control border-d fs-6" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
  </div>
  <div class="efb col text-right row my-2 mx-1">
  <button type="submit" class="efb btn fs-5 round-2 btn-primary btn-lg" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">${ajax_object_efm.text.reply} </button>
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
   <span id="settingModalEfb-title">${ajax_object_efm.text.response}</span></h5> -->
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
  const by = ajax_object_efm.user_name.length > 1 ? ajax_object_efm.user_name : efb_var.text.guest;
  const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: by , session: sessionPub_emsFormBuilder}];
  console.log('620');
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
    noti_message_efb_v4(efb_var.text.offlineSend , 'danger' , `replay_state__emsFormBuilder`,0 );       
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
    page_id: ajax_object_efm.page_id,
    sc:ajax_object_efm.sc,
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
            ${ajax_object_efm.text.error}</br>
            ${ajax_object_efm.text.formIsNotShown}</br>
            <a href="https://www.youtube.com/embed/a1jbMqunzkQ"  target="_blank" class="efb font-weight-normal">${ajax_object_efm.text.pleaseWatchTutorial}</a> </h2> </div>`
  if (document.getElementById('body_emsFormBuilder')) {
    document.getElementById('body_emsFormBuilder').innerHTML = m;
  } else if (document.getElementById('body_tracker_emsFormBuilder')) {
    document.getElementById('body_tracker_emsFormBuilder').innerHTML = m;
  } else {
    window.alert(`${ajax_object_efm.text.error} ${ajax_object_efm.text.formIsNotShown}`)
  }
}
async function validation_before_send_efb(form_id) {
  const btn_prev =valj_efb[0].hasOwnProperty('logic') &&  valj_efb[0].logic==true  ? `logic_fun_prev_send(${form_id})`:`fun_prev_send(${form_id})`;
  const count = [0, 0]
  let fill = 0;
  let require = 0;
  const id_body = form_id == 0 ? 'body_efb' : `body_efb_${form_id}`;
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
      console.log('indx',indx);
      if (indx != -1) {
        if(valueJson_ws[indx].hasOwnProperty("disabled") && valueJson_ws[indx].disabled==true && 
           ((valueJson_ws[indx].hasOwnProperty('hidden') && valueJson_ws[indx].hidden==false) || valueJson_ws[indx].hasOwnProperty('hidden')==false)
          ){    
            console.log('indx',indx);       
            const i = get_row_sendback_by_id_efb_v4(valueJson_ws[indx].id_,valueJson_ws[indx].form_id);

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
    const body_efb = document.getElementById(id_body);
    const efb_final_step = body_efb.getElementsByClassName('efb-final-step');
    efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center fs-2 efb"></i></h1><h3 class="efb fs-3 efb text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 fs-5 efb text-muted"> ${require != 1 ? ajax_object_efm.text.PleaseFillForm : ajax_object_efm.text.pleaseFillInRequiredFields} </br></span>
     <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner:'efb-square'}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    //if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
    smoothy_scroll_postion_efb(id_body)
    for (const v of valueJson_ws) {
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
              <button type="button"  class="efb btn fs-5 btn-lg btn-danger efb mt-1 " onclick="emsFormBuilder_logout()">${ajax_object_efm.text.logout}</button>
          </div> </div>`
}
function emsFormBuilder_logout() {
  document.getElementById('body_efb').innerHTML = loading_messge_efb();
  form_type_emsFormBuilder = "logout";
  formNameEfb = "logout";
  ajax_object_efm.type = "logout";
  sendBack_emsFormBuilder_pub = { logout: true };
  recaptcha_emsFormBuilder = '';
  actionSendData_emsFormBuilder();
}
function Show_recovery_pass_efb() {
  let el = document.getElementById('recoverySectionemsFormBuilder');
  let elBtnBack = document.getElementById('prev_efb_send');
  let iconBtn = document.getElementById('icon_btn_Show_recovery_efb');
  let elMsg = document.getElementById('alertFinalStepEFB');
  // بررسی وضعیت نمایش عنصر
  const isHidden = el.classList.contains('d-none');
  
  if (isHidden) {
      
      el.classList.remove('d-none');
      el.classList.remove('fadeOut');
      el.classList.add('fadeIn');
      iconBtn.className = 'bi bi-chevron-up';
      el = document.getElementById('btn_recovery_pass_efb');
      el.disabled = true;
      elMsg.classList.add('d-none');
      elBtnBack.classList.add('d-none');
  } else {
      el.classList.remove('fadeIn');
      el.classList.add('fadeOut');
      
      iconBtn.className = 'bi bi-chevron-down';
      
      // اضافه کردن رویداد فقط یک بار برای پنهان کردن عنصر بعد از انیمیشن
      el.addEventListener('animationend', function() {
          el.classList.add('d-none');
          elBtnBack.classList.remove('d-none');
          elMsg.classList.remove('d-none');
      }, { once: true });
  }

 /*  el.style.display == "none" ? elBtnBack.classList.add('d-none') : elBtnBack.classList.remove('d-none') ;
  el.style.display = el.style.display == "none" ? "block" : "none"; */
  document.getElementById('recoverySectionemsFormBuilder').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  

  //check el has not add event listener

  if ( el.dataset.hasOwnProperty('id') &&el.dataset.id == 1) {
    el.dataset.id = 0;
    const us = document.getElementById('username_recovery_pass_efb');
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    el.addEventListener("click", (e) => {
      form_type_emsFormBuilder = "recovery";
      formNameEfb = form_type_emsFormBuilder;
      sendBack_emsFormBuilder_pub = { email: us.value ,'recovery':true};
      document.getElementById('efb-final-step').innerHTML = `<h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3 class="efb text-center">${ajax_object_efm.text.pleaseWaiting}<h3>`
      actionSendData_emsFormBuilder()
    })
    us.addEventListener("keyup", (e) => {
      const check = us.value.match(format) ? 0 : 1;
      if (check == 0) { el.classList.remove('disabled') } else { el.classList.contains('disabled') != true ? el.classList.add('disabled') : 0 }
    })
  }
}
async function response_fill_form_efb(res ,form_id=0) {
  console.log('response_fill_form_efb formid',form_id,res)
  form_id = Number(form_id);
  let btn_prev ='';
  const t = valj_efb_new.find(x => x.id == form_id);
  console.log(t)
  const valj_efb = t.form_structer;
  const stps = t.form_structer && t.form_structer.hasOwnProperty('steps')  ? Number(valj_efb[0].steps) : -123;
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  console.log(body_efb);
  const efb_final_step = body_efb.querySelector('#efb-final-step');
  console.log(efb_final_step)
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
      case 'survey':
      case 'payment':
        efb_final_step.innerHTML = funTnxEfb(res.data.track)
        localStorage.clear();
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
        if (res.data.m.state == true) {
          document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(res.data.m);
          location.reload();
        } else {
          efb_final_step.innerHTML = `<div id="alertFinalStepEFB" class="efb m-0 p-0"><h3 class='efb emsFormBuilder text-center fs-5 efb mb-0 mt-5  text-center' ><i class="efb fs-2 bi-exclamation-triangle-fill nmsgefb  text-center"></i></h3> <span class="efb fs-7  text-center"> <br>${res.data.m.error}</span></div>
           </br>
           <a  id="btn_Show_recovery_efb" class="efb pointer-efb emsFormBuilder " onclick="Show_recovery_pass_efb()" >${ajax_object_efm.text.passwordRecovery} <i id="icon_btn_Show_recovery_efb" class="bi-chevron-down"> </i> </a>
           <div class="efb py-3 px-4 container bg-light mb-3 card rounded-3 d-none" id="recoverySectionemsFormBuilder" >   
              <p class="efb fs-6">${ajax_object_efm.text.servpss}</p>  
              <input type="email" id="username_recovery_pass_efb" class="efb px-2 mb-1 emsFormBuilder_v w-100 bg-white  h-d-efb efb-square-1 col-8 border border-dark rounded-2" placeholder="Email" >
              <a  id="btn_recovery_pass_efb" class=" efb btn h-d-efb btn-block btn-pinkEfb text-white mb-2 get-emsFormBuilder disabled  rounded-2 w-100" data-id="1" >${ajax_object_efm.text.send}</a>
              </div>
              <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner:'efb-square'}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onclick="fun_prev_send(${form_id})"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div>
              `;
        }
        break;
      case "logout":
        location.reload();
        localStorage.clear();
        break;
    }
    //if (efb_final_step) efb_final_step.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
     
      if(stps>1 ){smoothy_scroll_postion_efb(id_body)}
  } else {
    if(efb_final_step){efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder text-center'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center efb fs-3  text-center"></i></h1><h3 class="efb  text-center fs-3 text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5"> ${res.data.m}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].hasOwnProperty('button_color') ? valj_efb[0].button_color : 'btn-darkb'}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'efb-square'}   ${valj_efb[0].hasOwnProperty('el_height') ? valj_efb[0].el_height : 'h-l-efb'}  p-2 text-center  btn-lg  " onclick="${btn_prev}"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
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
    document.getElementById('body_efb-track').innerHTML = `<div class="efb text-center"><h3 class='efb emsFormBuilder mt-3  text-center'><i class="efb nmsgefb  bi-exclamation-triangle-fill text-center efb fs-1"></i></h1><h3 class="efb  fs-3 text-muted  text-center">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5 mx-1">${ajax_object_efm.text.somethingWentWrongTryAgain} </br></br> ${res.data.m} </br></span>
     <div class="efb display-btn emsFormBuilder"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="efb  btn btn-darkb m-5 text-white" onclick="(() => {  location.reload(); })()" style="display;"><i class="efb ${ajax_object_efm.rtl == 1 ? 'bi-arrow-right' : 'bi-arrow-left'}"></i></button></div></div>`;
  }
}
function response_rMessage_id(res, message) {
  if (res.success == true && res.data.success == true) {
    document.getElementById('replayM_emsFormBuilder').value = "";
    document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply;
     if(document.getElementById('name_attach_efb')) document.getElementById('name_attach_efb').innerHTML =ajax_object_efm.text.file
    const date = Date();
    fun_emsFormBuilder__add_a_response_to_messages(message, res.data.by, 0, 0, date);
    const chatHistory = document.getElementById("resp_efb");
    chatHistory.scrollTop = chatHistory.scrollHeight;
  } else {
    document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply;
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
 post_api_forms_efb=async(data,form_id)=>{
  console.log('post_api_forms_efb fomId', form_id,data);
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
 /*  fetch(url, requestOptions)
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.json();
  })
  .then(responseData => {
    console.log('responseData',responseData)
    response_fill_form_efb(responseData,form_id);
    if(localStorage.getItem('sendback'))localStorage.removeItem('sendback')
  })
  .catch(error => {
    console.error(error);
    response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 }},form_id);
  }); */

  try {
    const response = await fetch(url, requestOptions);
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    const responseData = await response.json();
    console.log('responseData', responseData);
    await response_fill_form_efb(responseData, form_id);
    if (localStorage.getItem('sendback')) localStorage.removeItem('sendback');
  } catch (error) {
    console.error(error);
    await response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 } }, form_id);
  }
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

    //v4 start
  fun_wait =(state ,speed)=>{
    if(speed!='fast'){
      const body_efbs = document.querySelectorAll('.body_efb');
      body_efbs.forEach((body_efb) => {
        if(state ==true){
          body_efb.classList.add('efb-waiting');
        }else{
          body_efb.classList.remove('efb-waiting');
        } 
        console.log(state,body_efb.classList)
      })
    }
  }

  await createStepsOfPublic();
  fun_wait(false,'nfast');
 
  //v4 end
});


/* new Codes */
smoothy_scroll_postion_efb=(id)=>{

  /* debug section JUSt For test */
  const error = new Error();
  // استخراج استک از شیء Error
  const stack = error.stack || '';

  //console.log(`smoothy_scroll_postion_efb[${id}]`);
  //console.log('Call stack:', stack);

  /* End debug section JUSt For test */

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
    //console.log('Element not found');
  }

}


setTimeout(() => {
  // انتخاب عنصر اصلی
  const sourceElement = document.getElementById('step-1-efb');
  // انتخاب عنصر هدف
  const targetElement = document.getElementById('efb-final-step');

  if (sourceElement && targetElement) {
    // دریافت ارتفاع منبع
    const sourceHeight = sourceElement.offsetHeight;
    //console.log('Source Height:', sourceHeight);
    
    // تنظیم ارتفاع عنصر هدف
   // targetElement.style.minHeight = `${sourceHeight}px`;
    if(sourceElement.offsetHeight<300 && sourceElement.offsetWidth<300 ){
      //console.log('added auto overflow');
      //+ این تابع بروز شود و وقتی که سایز عنصر اصلی کمتر از 300 است بود به تناسب اندازه های مربوط به استپ آخر کم شود مثل لودینگ یا پاسخ سرور
      /* targetElement.style.overflow='auto' */
    }
  } else {
    //console.log('One or both elements not found');
  }
}, 5000);




// v4
async function btn_navigate_handle_efb(form_id , form_type , btn_state,el){
  
  console.log('btn_navigate_handle_efb');
  console.log(`form_id:${form_id} form_type:${form_type} btn_state:${btn_state}`,el);
  const id_body = 'body_efb_'+form_id;
  console.log(id_body,el);
  let parent_body = document.getElementById(id_body)
  console.log(parent_body)
  const max_step = Number(parent_body.dataset.steps);
  let no_step = Number(parent_body.dataset.currentstep);
  console.log(`max_step:${max_step} no_step${no_step}` , parent_body);
  valj_efb = get_structure_by_form_id_efb(form_id);
  console.log(`valj_efb=>`,valj_efb)
  const progessbar = parent_body.querySelector('.progress-bar-efb') ?? null;
  fun_progessbar = (no_step,max_step)=>{
    max_step = max_step+1;
    console.log(`fun_progessbar no_step:${no_step} max_step:${max_step}`);
    const percent_progess = ((no_step)/(max_step))*100+'%';
    console.log(`percent_progess:${percent_progess}`);
    progessbar.style.width = percent_progess;
  }
  
  step_payment_exists = -1;
  
  const title_efb = parent_body.querySelector('#title_efb') ?? null;
  const desc_efb = parent_body.querySelector('#desc_efb') ?? null;
  const steps_efb = parent_body.querySelector('#steps-efb') ?? null;
  console.log('title_efb',title_efb)
  fun_handle_header_efb = async(no_step,nav_state)=>{
    console.log('fun_handle_header_efb',valj_efb[0].show_icon);
    if(!title_efb || !desc_efb || !steps_efb) return false;
    if(Number(valj_efb[0].show_icon)==1){
      return true;
    }
    icon_step_handler = (no_step,form_id,nav_state)=>{
      console.log(`icon_step_handler no_step:${no_step} form_id:${form_id} nav_state:${nav_state}`);
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
    //-f-step-efb
    console.log(`no_step:${no_step} max_step:${max_step}`);
    if(no_step<=max_step){
    icon_step_handler(no_step,form_id,nav_state);
    const cp = no_step;
    const val = valj_efb.find(x=>x.step==cp);
    console.log(`-------------->fun_handler_efb`,val , val["label_text_color"])
      title_efb.className = colorTextChangerEfb(title_efb.className,val["label_text_color"]);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val["message_text_color"]);
      title_efb.innerHTML = val["name"];
      desc_efb.innerHTML = val["message"]; 
      //document.getElementById(id_active_icon).classList.remove('active');
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

  console.log(title_efb,desc_efb,steps_efb);

  // update this line to get the first row of valj_efb by search form_id and get form_structer of form
  const first_row = valj_efb[0];
  const current_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
  if(form_type == 'payment'){
    //check in valj_efb_new payment exists which step
    //if not found payment method show a message to user that payment method not exist so the form can't be submitted
    const payment_method = ['stripe','persiaPay','paypal'];
    if(false){

    }else{
      step_payment_exists = -1;
      alert('The form(form id:'+form_id+') cannot be submitted because it requires a payment method, which is currently missing. If you are the admin, please add a payment method to the form or change the form type to "Form" or "Survey".');
    }
  }

  const validate = await fun_validation_efb_v4(form_id);
        console.log(`validate:${validate}`);
        if (validate == false) {

          return false;
        }

  if(btn_state=='next_efb'){
    let prev_btn = parent_body.querySelector('#prev_efb');
    if(no_step<2){
      if(prev_btn)prev_btn.classList.remove('d-none');
    }
    // + befor go to next step check validation of current step
    
     no_step = Number(no_step)+1;
     await fun_handle_header_efb(no_step,'forward');
    
    parent_body.dataset.currentstep = no_step; 
    const next_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);

    current_fieldset.classList.add('d-none');
    next_fieldset.classList.remove('d-none');
    if(progessbar)fun_progessbar(no_step,max_step);
  
    console.log(`no_step:${no_step} max_step:${max_step} ,${no_step==(max_step+1)}`);
    if(no_step>max_step){
      el.classList.add('d-none');
      prev_btn.classList.add('d-none');
      endMessage_emsFormBuilder_view(no_step+1,form_id);
    }
    smoothy_scroll_postion_efb(id_body);
    // check if payment exists and current step is equal to paymant step then disable the button
    if(no_step==step_payment_exists){
      //show payment
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
  }else if (btn_state=='btn_send_efb'){
    no_step = Number(no_step)+1;
    
    parent_body.dataset.currentstep = no_step;
    const next_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
    // + befor go to next step check validation of current step
    endMessage_emsFormBuilder_view(current_s_efb,form_id);
    current_fieldset.classList.add('d-none');
    next_fieldset.classList.remove('d-none');
    if(progessbar)fun_progessbar(no_step,max_step);
    smoothy_scroll_postion_efb(id_body);
    await fun_handle_header_efb(no_step,'forward');

    el.classList.add('d-none');
  }

}

// v4
get_structure_by_form_id_efb=(form_id)=>{
  let ob = valj_efb_new.find(x => x.id == form_id);
  return ob.form_structer;
}

// v4
sendback_state_handler_efb_v4=(id_,state,step,form_id)=>{
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  const indx = sendback_efb_state.findIndex(x=>x.id_==id_ && x.form_id==form_id);
  const next_btn = body_efb.querySelector('#next_efb');
  const send_btn = body_efb.querySelector('#btn_send_efb');
  if(indx==-1 && state==false){
    sendback_efb_state.push({id_:id_,state:state,step:step,form_id:form_id})
    if(send_btn && send_btn.classList.contains('disabled')==false )send_btn.classList.add('disabled');
    else if(next_btn && next_btn.classList.contains('disabled')==false )next_btn.classList.add('disabled');
  }else if(indx>-1 && state==true && sendback_efb_state.length>0){
    //remove for  sendback_efb_state by id_
    sendback_efb_state.splice(indx,1);
    //get sendback_efb_state by step if exists return true else return false
    setTimeout(() => {
      const indx_ = sendback_efb_state.findIndex(x=>x.step==step);
      if(indx_==-1 || sendback_efb_state.length==0){
        if(send_btn)send_btn.classList.remove('disabled');
        else if(next_btn)next_btn.classList.remove('disabled');
      }      
    }, 200);
  }
}

// v4
get_row_sendback_by_id_efb_v4=(id_,form_id=0)=>{
  if(form_id==0){
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_)

  }else{
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_ && x.form_id==form_id)
  }
 }

// v4
//+  بخش زیر بعد از پاک کردن تابع new-efb از کامنت خارج شود
/* 
const colorTextChangerEfb = (classes, color) => { return classes.replace(/(text-primary|text-darkb|text-muted|text-secondary|text-pinkEfb|text-success|text-white|text-light|\btext-colorDEfb-+[\w\-]+|text-danger|text-warning|text-info|text-dark|text-labelEfb)/, ` ${color} `) ?? `${classes} ${color}`; }
const alignChangerElEfb = (classes, value) => { return classes.replace(/(justify-content-start|justify-content-end|justify-content-center)/, ` ${value} `) ?? `${classes} ${value} `; }
const alignChangerEfb = (classes, value) => { return classes.replace(/(txt-left|txt-right|txt-center)/, ` ${value} `) ?? `${classes} ${value} `; }
const RemoveTextOColorEfb = (classes) => { return classes.replace('text-', ``); }
const colorBorderChangerEfb = (classes, color) => { return classes.replace(/\bborder+-+[\w\-]+/gi, ` ${color} `) ?? `${classes} ${color} `; }
const cornerChangerEfb = (classes, value) => { return classes.replace(/(efb-square|efb-rounded|rounded-+[0-5] )/, ` ${value} `) ?? `${classes} ${value} `; }
const colMdChangerEfb = (classes, value) => { return classes.replace(/\bcol-md+-\d+/, ` ${value} `) ?? `${classes} ${value} ` ; }
const PxChangerEfb = (classes, value) => { return classes.replace(/\bpx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const MxChangerEfb = (classes, value) => { return classes.replace(/\bmx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const btnChangerEfb = (classes, value) => { return classes.replace(/\bbtn-outline-+\w+|\bbtn-+\w+/, ` ${value} `) ?? `${classes} ${value} `; }
 */


fun_prev_send =(form_id =0) =>{
  console.log(`fun_prev_send[${form_id}]`);
  valj_efb = get_structure_by_form_id_efb(form_id);
  var stp = Number(valj_efb[0].steps) + 1;
  //body_efb_119
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  current_s_efb = body_efb.dataset.currentstep;
  console.log(id_body,body_efb);
  const finalStepEl = body_efb.querySelector('#efb-final-step');
  finalStepEl.innerHTML = loading_messge_efb();
  let id = `step-${current_s_efb}-efb`;
  var current_s = body_efb.querySelector(`[data-step="${id}"]`);
  id = `step-${current_s_efb-1}-efb`;
  console.log(id);
  const prev_s_efb = body_efb.querySelector(`[data-step="${id}"]`);
  console.log(prev_s_efb);

  fun_progessbar = (current_step,max_step)=>{
    const progessbar = body_efb.querySelector('.progress-bar-efb')
    if(!progessbar)return false;
    max_step = max_step+1;
    console.log(`fun_progessbar current_step:${current_step} max_step:${max_step}`);
    const percent_progess = ((current_step)/(max_step))*100+'%';
    console.log(`percent_progess:${percent_progess}`);
    progessbar.style.width = percent_progess;
  }

  if(Number(valj_efb[0].show_icon)!=1)  body_efb.querySelector('[data-step="icon-s-' + current_s_efb + '-efb"]').classList.remove("active");
  body_efb.querySelector('[data-step="step-' + current_s_efb + '-efb"]').classList.toggle("d-none");
  if (stp == 2) {
       //body_efb.getElementById("btn_send_efb").classList.remove("d-none");
       const btn_send_efb = body_efb.querySelector('#btn_send_efb');
       btn_send_efb.classList.remove('d-none');
       const gRecaptcha = body_efb.querySelector('#gRecaptcha');
    if(gRecaptcha) {
      //body_efb.getElementById("gRecaptcha").classList.toggle("d-none");
      gRecaptcha.classList.toggle('d-none');
      }
  } else {
    //body_efb.getElementById("next_efb").classList.remove("d-none");
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
    console.log(`id_active_icon:${id_active_icon}`);
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
  console.log(id_body)
  smoothy_scroll_postion_efb(id_body);

  
}


//v4 
async function handle_change_event_efb_v4(el ,form_id=0){  
  console.log('handle_change_event_efb_v4');
  valj_efb = get_structure_by_form_id_efb(form_id);
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
    const el = document.getElementById(id_);
    if(Number(s.required)==0){
      if(el !== null) el.className = colorBorderChangerEfb(el.className, s.el_border_color);
      el_msg.classList.remove('show');
     sendback_state_handler_efb_v4(id,true,0,form_id);
    }else{
      console.log('el_empty_value',id_);
      if(el !== null) el.className = colorBorderChangerEfb(el.className, "border-danger");
      if(el_msg!=null){
        if(el_msg.classList.contains('show')==false)el_msg.classList.add('show');
        el_msg.innerHTML = efb_var.text.enterTheValueThisField;
      }
    }
    delete_by_id(id);
  }
  validate_len_efb_v4 =()=>{
    console.log('validate_len_efb_v4!!!');
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
  }//end validate_len_efb

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
      if(validate_len_efb_v4()==0 && (el.dataset.hasOwnProperty('type') && el.dataset.type!="chlCheckBox")){
        //console.log('validate_len_efb()==0!!!');
         sendback_state_handler_efb_v4(id_,false,0,form_id);
       return;
      }else {          
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
           await fun_check_link_state_efb(el.options[el.selectedIndex].dataset.iso , temp)
      }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="stateProvince"){
           let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);       
            iso_con = el.options[el.selectedIndex].dataset.isoc
            iso_state = el.options[el.selectedIndex].dataset.iso   
            console.log(iso_con,iso_state,temp,el.options[el.selectedIndex].dataset);     
            await fun_check_link_city_efb(iso_con,iso_state , temp)
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
      console.log('===================================================>file');
      valid_file_emsFormBuilder(id_,'msg','');
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
  if(state==false && value.length > 0)   sendback_state_handler_efb_v4(id_,false,0,form_id);
  if (value != "" || value.length > 0) {

    const type = ob.type;
    const id_ob = ob.type != "paySelect" ? el.id : el.options[el.selectedIndex].id;
    let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder,form_id:  form_id }];      
     sendback_state_handler_efb_v4(id_,true,0,form_id);
     console.log(`type[${type}]`,el);
    if (el.classList.contains('payefb')) {
      let q = valueJson_ws.find(x => x.id_ === el.id);
      let p ;
      if(type =='prcfld'){
        p= Object.assign(o[0], {price: el.value});
      }else{ 
        p = price_efb.length > 0 ? { price: price } : { price: q.price }
      }
      Object.assign(o[0], p)
      console.log('3090');
      
      fun_sendBack_emsFormBuilder(o[0]);
      fun_total_pay_efb()
    }else if(type.includes('option')){
      const ch = el.id.includes('_chl')
      const qty = ch  ? document.getElementById(el.id).value :'';
      if(ch==false){
        //Object.assign(o[0], {qty:qty});
      }else{
        el.classList.remove('bg-danger');
        const v =fun_text_forbiden_convert_efb(qty);
        //find in sendBack_emsFormBuilder_pub by id_ and id_ob
        const vid= el.dataset.vid;
        const indx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ob==vid);
        console.log(el.dataset,vid,sendBack_emsFormBuilder_pub[indx]);
      
        if(indx!=-1){
          sendBack_emsFormBuilder_pub[indx].hasOwnProperty('qty') ? sendBack_emsFormBuilder_pub[indx].qty = v : Object.assign(sendBack_emsFormBuilder_pub[indx], {qty: v});
          return;
        }
      }
      
      console.log('3102',el);
      fun_sendBack_emsFormBuilder(o[0]);
    }else if (o[0].type=="email"){
      
      console.log('3105',el);
      fun_sendBack_emsFormBuilder(o[0]);
    }else {
      console.log('3108',el);
      
      fun_sendBack_emsFormBuilder(o[0]);
    }
  }
}


async function fun_validation_efb_v4(form_id) {
  console.log('fun_validation_efb_v4')
  let offsetw = offset_view_efb();
  console.log(efb_var.text.enterTheValueThisField);
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${efb_var.text.enterTheValueThisField}','',10,'danger')"></div>` : efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null"; 
  let name_field = "";
  let id_noti_message = valj_efb.steps > 1 ?  `step-${current_s_efb}-efb-msg` : 'alert_efb';
  for (let row in valj_efb) {
     // console.log('row:',valj_efb[row]);
    let s =  get_row_sendback_by_id_efb(valj_efb[row].id_);
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      let el =document.getElementById(`${valj_efb[row].id_}_-message`);
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        let r=files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_);
        s = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state)==0 || r==-1 ? -1 :1;
        console.log(`=====>s[${s}] id[${valj_efb[row].id_}]`);
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
     
      for(let em of sendBack_emsFormBuilder_pub){
        console.log('em:',em)
        form_id = em.form_id;
        if(em.type=="chlCheckBox" && em.id_==valj_efb[row].id_ && em.hasOwnProperty('qty')==false)
        {
         
          const vd = em.id_ob+"_chl";
          document.getElementById(vd).classList.add('bg-danger');
          state = false;
          idi = valj_efb[row].id_;
          const messag = efb_var.text.sfmcfop.replace('%s',`<b>${name_field}</b>`);
          //const msg = efb_var.text.enterTheValueThisField + " " + name_field;
          noti_message_efb_v4(messag, 'danger' ,id_noti_message ,form_id);
          break;
        
         // id_noti_message ='alert_efb';
        }
      }
    
    }
  }
  
  if (state===false && idi != "null") { 
    if(typeof smoothy_scroll_postion_efb === 'function'){
      smoothy_scroll_postion_efb(idi)
     
    }else{
      //console.log('fun_validation_efb 1972')
      document.getElementById(idi).scrollIntoView({behavior: "smooth", block: "center", inline: "center"}); 
    }
    return false;
  }
  return state
}


function noti_message_efb_v4(message, alert ,id,form_id=0){
  alert = alert ? `alert-${alert}` : 'alert-info';
  console.log(form_id);
  let d = document.querySelector(`#${id}[data-formid="${form_id}"]`);
  if(document.getElementById('noti_content_efb')){
    document.getElementById('noti_content_efb').remove()
  }
    d.innerHTML += ` <div id="noti_content_efb" class="efb w-75 mt-0 my-1 alert-dismissible alert ${alert}  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <p class="efb my-0">${message}</p>    
  </div>`
}

function call_fun_jalali_datepicker_efb_v4(){
  console.log('call fun jalali datepicker');
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
  console.log(form_id)
  let vj = valj_efb_new.find(x=>x.id==form_id);
  console.log('vj',vj);
  const sid = vj.sid;
  console.log(vj , sid);
  valj_efb = vj.form_structer;
  formNameEfb = valj_efb[0].formName;
  let r=  {sid:sid,type:vj.type,formName:formNameEfb};
  if(vj.hasOwnProperty('nonce_msg')){
    r = Object.assign(r,{nonce_msg:vj.nonce_msg});
  }
  console.log(r);
  return r;
} 



const speed_test_efb=()=>{
  console.log('speed_test_efb');
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

  /*  console.log('Effective Network Type:', connection.effectiveType); // e.g., '4g', '3g', '2g', 'slow-2g'
      console.log('Downlink Speed (Mbps):', connection.downlink); // Estimated download bandwidth in Mbps
      console.log('Round-Trip Time (ms):', connection.rtt); // Estimated round-trip latency in milliseconds */

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









