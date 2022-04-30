
//stepNavEfb add class to divider of steps
//Copyright 2021
//Easy Form Builder
//WhiteStudio.team


//EFB.APP
let activeEl_efb = 0;
let amount_el_efb = 1; //تعداد المان ها را نگه می دارد
let step_el_efb = 0; // تعداد استپ ها
let steps_index_efb = [] // hold index of steps
let valj_efb = []; 
//let pro_efb = false
let maps_efb = [];
let state_efb = 'view';
let mousePostion_efb = { x: 0, y: 0 };
let draw_mouse_efb = false;
let c2d_contex_efb
let lastMousePostion_efb = mousePostion_efb;
let canvas_id_efb = "";
let fileEfb;
let formName_Efb ;
let current_s_efb =1
let verifyCaptcha_efb =""
let devlop_efb=false;
let preview_efb=false;
const mobile_view_efb =  document.getElementsByTagName('body')[0].classList.contains("mobile") ? 1 :0;


efb_var_waitng=(time)=>{
  setTimeout(()=>{
   
    if(typeof (efb_var)== "object"){
      
      formName_Efb = efb_var.text.form
      default_val_efb =efb_var.text.selectOption
      pro_efb = efb_var.pro=="1" || efb_var.pro==1 ? true :false;
      return;
    }else{
      time +=50;
      time!=30000 ?   efb_var_waitng(time) : noti_message_efb(efb_var.text.error,"Please Hard Refresh",60)
    }
  },time)
}

efb_var_waitng(400)
//اضافه کردن رویداد کلیک و نمایش و عدم نمایش کنترل المان اضافه شده 


jQuery(document).ready(function(){jQuery("body").addClass("folded")})
function remove_other_noti(){
  window.onload=(()=>{
    // remove all notifications from other plugins or wordpress
      setTimeout(()=>{
          for (const el of document.querySelectorAll(".notice")) {
              el.remove()
          }
      },50)
  })
}


function creator_form_builder_Efb() {
  remove_other_noti()
  if(valj_efb.length<2){
    step_el_efb = 1;
    console.log(form_type_emsFormBuilder);
    valj_efb.push({
      type: form_type_emsFormBuilder, steps: 1, formName: efb_var.text.form, email: '', trackingCode:true, EfbVersion: 2,
      button_single_text: efb_var.text.send, button_color: 'btn-primary', icon: 'bi-ui-checks-grid', button_Next_text:  efb_var.text.next, button_Previous_text: efb_var.text.previous,
      button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single', corner: 'efb-square', label_text_color: 'text-light',
      el_text_color: 'text-light', message_text_color: 'text-muted', icon_color: 'text-light', el_height: 'h-d-efb', email_to: false, show_icon: false, 
      show_pro_bar: false, captcha: false,private:false,sendEmail: false,font:true,stateForm:0,
      thank_you_message:{thankYou:efb_var.text.thanksFillingOutform, done:efb_var.text.done, trackingCode:efb_var.text.trackingCode, error:efb_var.text.error,pleaseFillInRequiredFields:efb_var.text.pleaseFillInRequiredFields }, email_temp:'',font:true,      
    });

    if(form_type_emsFormBuilder=="payment"){
      Object.assign(valj_efb[0], {getway:'stripe' , currency:'usd', paymentmethod:'subscription' })
    }
   
  }
 
  const objct = [{ name: efb_var.text.text, icon: 'bi-file-earmark-text', id: 'text', pro: false },
  { name: efb_var.text.name, icon: 'bi-person-circle', id: 'name', pro: false },
  { name: efb_var.text.password, icon: 'bi-lock', id: 'password', pro: false },
  { name: efb_var.text.email, icon: 'bi-envelope', id: 'email', pro: false },
  { name: efb_var.text.number, icon: 'bi-pause', id: 'number', pro: false },
  /* { name: efb_var.text.address, icon: 'bi-geo-alt', id: 'address', pro: false }, */
  { name: efb_var.text.textarea, icon: 'bi-card-text', id: 'textarea', pro: false },
  { name: efb_var.text.heading, icon: 'bi-fonts', id: 'heading', pro: true },
  { name: efb_var.text.checkbox, icon: 'bi-check-square', id: 'checkbox', pro: false },
  { name: efb_var.text.radiobutton, icon: 'bi-record-circle', id: 'radio', pro: false },
  { name: efb_var.text.select, icon: 'bi-check2', id: 'select', pro: false },
  { name: efb_var.text.multiselect, icon: 'bi-check-all', id: 'multiselect', pro: false }, 
  { name: efb_var.text.payCheckbox, icon: 'bi-basket2', id: 'payCheckbox', pro: true },
  { name: efb_var.text.payRadio, icon: 'bi-basket3', id: 'payRadio', pro: true },
  { name: efb_var.text.paySelect, icon: 'bi-bag-check', id: 'paySelect', pro: true },
  { name: efb_var.text.payMultiselect, icon: 'bi-bag-plus', id: 'payMultiselect', pro: true }, 
  { name: efb_var.text.mobile, icon: 'bi-phone', id: 'mobile', pro: true },
  { name: efb_var.text.tel, icon: 'bi-telephone', id: 'tel', pro: false },
  { name: efb_var.text.url, icon: 'bi-link-45deg', id: 'url', pro: false },
  { name: efb_var.text.range, icon: 'bi-arrow-left-right', id: 'range', pro: false },
  { name: efb_var.text.dadfile, icon: 'bi-plus-square-dotted', id: 'dadfile', pro: true },
  { name: efb_var.text.file, icon: 'bi-file-earmark-plus', id: 'file', pro: false },
  { name: efb_var.text.step, icon: 'bi-file', id: 'steps', pro: false },
  { name: efb_var.text.ddate, icon: 'bi-calendar-date', id: 'date', pro: false },
  { name: efb_var.text.conturyList, icon: 'bi-flag', id: 'conturyList', pro: true },
  { name: efb_var.text.stateProvince, icon: 'bi-triangle-fill', id: 'stateProvince', pro: true },
  { name: efb_var.text.esign, icon: 'bi-pen', id: 'esign', pro: true }, 
  { name: efb_var.text.switch, icon: 'bi-toggle2-on', id: 'switch', pro: true },
  { name: efb_var.text.locationPicker, icon: 'bi-pin-map', id: 'maps', pro: true },
  { name: efb_var.text.color, icon: 'bi-palette', id: 'color', pro: true },
  { name: efb_var.text.rating, icon: 'bi-star', id: 'rating', pro: true },
  { name: efb_var.text.yesNo, icon: 'bi-hand-index', id: 'yesNo', pro: true },
  { name: efb_var.text.link, icon: 'bi-link-45deg', id: 'link', pro: true },
/*   { name: efb_var.text.product, icon: 'bi-bag-check-fill', id: 'product', pro: true },
  { name: efb_var.text.pricingTable, icon: 'bi-tags', id: 'pricingTable', pro: true }, */
  { name: efb_var.text.stripe, icon: 'bi-credit-card', id: 'stripe', pro: true },
 /*  { name: efb_var.text.terms, icon: 'bi-shield-check', id: 'terms', pro: true }, */
  { name: efb_var.text.htmlCode, icon: 'bi-code-square', id: 'html', pro: true },
]


  let els = "<!--efb.app-->";
  let dragab = true;
  let disable ="disable";
  let formType = valj_efb[0].type
  const ond = `onClick="noti_message_efb('${efb_var.text.error}','${efb_var.text.thisElemantNotAvailable}',7,'danger')"`
  if(formType=="login"){
    dragab=false;
    disable=ond;
    //thisElemantNotAvailable
  }
  
  for (let ob of objct) {
    if (formType=="login") {if( ob.id=="html" || ob.id=="link" || ob.id=="heading") {dragab=true; disable="disable"}else{ dragab=false;disable=ond}}
    else if (formType!="payment") {if( ob.id=="stripe") { dragab=false;disable=ond} else {{ dragab=true;disable="disable"}}}
    els += `
    <div class="col-3 draggable-efb" draggable="${dragab}" id="${ob.id}" ${mobile_view_efb ? `onClick="add_element_dpz_efb('${ob.id}')"`: '' }>
     ${ob.pro == true && pro_efb==false ? ` <a type="button" onClick='pro_show_efb(1)' class="pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb bi-gem text-light"></i></a>` : ''}
      <button type="button" class="btn efb btn-select-form float-end ${disable!="disable" ? "btn-muted" :''}" id="${ob.id}_b" ${disable}><i class="efb ${ob.icon}"></i><span class="d-block text-capitalize">${ob.name}</span></button>
    </div>
    `
  }
  let navs = [
    { name: efb_var.text.save, icon: 'bi-save', fun: `saveFormEfb()` },
    { name: efb_var.text.pcPreview, icon: 'bi-display', fun: `previewFormEfb('pc')` },
    { name: efb_var.text.formSetting, icon: 'bi-sliders', fun: `show_setting_window_efb('formSet')` },
    { name: efb_var.text.help, icon: 'bi-question-lg', fun: `Link_emsFormBuilder('createSampleForm')` },
    
  ]
  if(devlop_efb==true) navs.push({ name: 'edit(Test)', icon: 'bi-pen', fun: `editFormEfb()` });
  let nav = "<!--efb.app-->";
  for (let ob in navs) {
    nav += `<li class="nav-item"><a class="nav-link efb btn text-capitalize ${ob==2 ? 'BtnSideEfb':''} ${ob != 0 ? '' : 'btn-outline-pink'} " ${navs[ob].fun.length > 2 ? `onClick="${navs[ob].fun}""` : ''} ><i class="${navs[ob].icon} mx-1 "></i>${navs[ob].name}</a></li>`
  }
  
  document.getElementById(`content-efb`).innerHTML = `
  <div class="${mobile_view_efb ? 'my-2 mx-1': 'm-5' }" id="pCreatorEfb" >
  <div id="panel_efb">
      <nav class="navbar navbar-expand-lg navbar-light bg-light my-2 bg-response efb">
          <div class="container-fluid">
              <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
              <div class="collapse navbar-collapse py-1" id="navbarTogglerDemo01"><ul class="navbar-nav me-auto mb-2 mb-lg-0">${nav}</ul></div>
          </div>      
      </nav>
      <div class="row">
      <!-- over page -->
      <div id="overlay_efb" class="d-none"><div class="card-body text-center efb mt-5 pt-2"><div class="lds-hourglass efb text-white"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3></div></div>
      <!--end  over page -->
          <div class="col-md-4" id="listElEfb"><div class="row">${els}</div></div>
         <div class="col-md-8 body-dpz-efb"><div class="crd efb  drag-box"><div class="card-body dropZoneEFB row " id="dropZoneEFB">
       
        <div id="efb-dd" class="text-center ">
        <h1 class="text-muted display-1  bi-plus-circle-dotted"> </h1>
        <div class="text-muted fs-4 efb">${!mobile_view_efb ? efb_var.text.dadFieldHere:''}</div>
        </div>

         </div></div></div></div>
      </div>
  <div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
      <div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
          <div class="modal-content efb " id="settingModalEfb-sections">
                  <div class="modal-header efb"> <h5 class="modal-title efb" ><i class="bi-ui-checks mx-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title" class="efb">${efb_var.text.editField}</span></h5></div>
                  <div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="efb lds-hourglass"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3></div></div>
  </div></div></div>
  </div></div>
  `
  create_dargAndDrop_el()
}
/* for (const el of document.querySelectorAll(".showBtns")) {

  el.addEventListener("click", (e) => {
    active_element_efb(el);

  });
} */


function fub_shwBtns_efb() {
  for (const el of document.querySelectorAll(".showBtns")) {
    el.addEventListener("click", (e) => {
      active_element_efb(el);

    });
  }
}

function active_element_efb(el) {
  // تابع نمایش دهنده و مخفی کنند کنترل هر المان
  //show config buttons
  if (el.id != activeEl_efb) {
    if (activeEl_efb == 0) {
      activeEl_efb = document.getElementById(el.id).dataset.id;
      
    }else{
      
      document.getElementById(`btnSetting-${activeEl_efb}`).classList.toggle('d-none')
    }
    const ac = document.querySelector(`[data-id="${activeEl_efb}"]`)
    if (ac) {
      // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-none')
      if (ac.classList.contains('field-selected-efb')) ac.classList.remove('field-selected-efb')
    }

    activeEl_efb = el.dataset.id
    if (document.getElementById(`btnSetting-${activeEl_efb}`).classList.contains('d-none')){ 
      document.getElementById(`btnSetting-${activeEl_efb}`).classList.remove('d-none');
     
    }
    // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-block')
    document.querySelector(`[data-id="${activeEl_efb}"]`).classList.add('field-selected-efb')


  }
}

//setting of  element
fun_el_select_in_efb=(el)=>{ return el == 'conturyList' || el == 'stateProvince' || el == 'select' || el == 'multiselect' || el == 'paySelect' || el == 'payMultiselect' ? true:false}
function show_setting_window_efb(idset) {
  console.log(idset);
  if(document.getElementById('sideBoxEfb').classList.contains('show')){return};
  //console.log('show_setting_window_efb',idset,valj_efb)

  let el = idset != "formSet" ? document.querySelector(`[data-id="${idset}"]`) : { dataset: { id: 'formSet', tag: 'formSet' } }
  let body = ``;
  //const bodySetting = document.getElementById("settingModalEfb-body");
  const indx = idset != "button_group" && idset != "formSet" ? valj_efb.findIndex(x => x.dataId == idset) : 0;
  console.log(indx);
  if (indx == 0 && idset != "formSet") el = document.getElementById(`f_btn_send_efb`);
 
  const labelEls = `<label for="labelEl" class="form-label mt-2 mb-1 efb">${efb_var.text.label}<span class=" mx-1 efb text-danger">*</span></label>
  <input type="text"  data-id="${idset}" class="efb elEdit form-control text-muted border-d efb-rounded h-d-efb mb-1"  placeholder="${efb_var.text.label}" id="labelEl" required value="${valj_efb[indx].name ? valj_efb[indx].name : ''}">`

  const desEls = `<label for="desEl" class="form-label mt-2 mb-1 efb">${efb_var.text.description}</label>
  <input type="text" data-id="${idset}" class="elEdit form-control text-muted efb border-d efb-rounded h-d-efb mb-1" placeholder="${efb_var.text.description}" id="desEl" required value="${valj_efb[indx].message ? valj_efb[indx].message : ''}">`
  const requireEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="requiredEl" ${valj_efb[indx].required == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="requiredEl">${efb_var.text.required}</label>                                            
  </div>`;
  const cardEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="cardEl" ${valj_efb[0].dShowBg && valj_efb[0].dShowBg == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="cardEl">${efb_var.text.dNotShowBg}</label>                                            
  </div>`;

  const emailEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="SendemailEl" ${valj_efb[0].email_to && valj_efb[0].email_to == valj_efb[indx].id_ ? 'checked' : ''}>
  <label class="form-check-label efb" for="SendemailEl">${efb_var.text.thisEmailNotificationReceive} </label> <i class="bi-info-circle efb fs-7 text-success pointer-efb" onClick="Link_emsFormBuilder('EmailNoti')"> </i>                                            
  </div>`;
  const adminFormEmailEls = `<label for="adminFormEmailEl" class="form-label mt-2 mb-1 efb">${efb_var.text.enterAdminEmailReceiveNoti} <i class="bi-info-circle efb fs-7 text-success pointer-efb" onClick="Link_emsFormBuilder('EmailNoti')"> </i></label> 
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.enterAdminEmailReceiveNoti}" id="adminFormEmailEl" required value="${valj_efb[0].email ? valj_efb[0].email : ''}">`
  const trackingCodeEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="trackingCodeEl" ${valj_efb[0].trackingCode && valj_efb[0].trackingCode == 1 || valj_efb[0].trackingCode == true ? 'checked' : ''}>
  <label class="form-check-label efb" for="trackingCodeEl">${efb_var.text.activeTrackingCode}</label>                                            
  </div>`;
  const captchaEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="captchaEl" ${valj_efb[0].captcha && valj_efb[0].captcha == 1 || valj_efb[0].captcha == true ? 'checked' : ''}>
  <label class="form-check-label efb" for="captchaEl">${efb_var.text.addGooglereCAPTCHAtoForm}</label>                                            
  </div>`;
  const showSIconsEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="showSIconsEl" ${valj_efb[0].show_icon && valj_efb[0].show_icon == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="showSIconsEl">${efb_var.text.dontShowIconsStepsName}</label>                                            
  </div>`;
  const showSprosiEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="showSprosiEl" ${valj_efb[0].show_pro_bar && valj_efb[0].show_pro_bar == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="showSprosiEl">${efb_var.text.dontShowProgressBar}</label>                                            
  </div>`;
  const thankYouMessageEls = `<label for="thankYouMessageEl" class="form-label mt-2 mb-1 efb">${efb_var.text.thankYouMessage}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.thankYouMessage}" id="thankYouMessageEl" required value="${valj_efb[0].thank_you_message.thankYou ? valj_efb[0].thank_you_message.thankYou : efb_var.text.thankYouMessage}">`;
  const thankYouMessageDoneEls = `<label for="thankYouMessageDoneEl" class="form-label mt-2 mb-1 efb">${efb_var.text.done} ${efb_var.text.message}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.done}" id="thankYouMessageDoneEl" required value="${valj_efb[0].thank_you_message.done ? valj_efb[0].thank_you_message.done : efb_var.text.done}">`;
  const thankYouMessageConfirmationCodeEls = `<label for="thankYouMessageConfirmationCodeEl" class="form-label mt-2 mb-1 efb">${efb_var.text.trackingCode} ${efb_var.text.message}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.trackingCode}" id="thankYouMessageConfirmationCodeEl" required value="${valj_efb[0].thank_you_message.trackingCode ? valj_efb[0].thank_you_message.trackingCode : efb_var.text.trackingCode}">`;
  const thankYouMessageErrorEls = `<label for="thankYouMessageErrorEl" class="form-label mt-2 mb-1 efb">${efb_var.text.error} ${efb_var.text.message}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.error}" id="thankYouMessageErrorEl" required value="${valj_efb[0].thank_you_message.error ? valj_efb[0].thank_you_message.error : efb_var.text.error}">`;
  const thankYouMessagepleaseFillInRequiredFieldsEls = `<label for="thankYouMessagepleaseFillInRequiredFieldsEl" class="form-label mt-2 mb-1 efb">${efb_var.text.required} ${efb_var.text.message}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb border-d efb-rounded  mb-1 efb" placeholder="${efb_var.text.pleaseFillInRequiredFields}" id="thankYouMessagepleaseFillInRequiredFieldsEl" required value="${valj_efb[0].thank_you_message.pleaseFillInRequiredFields ? valj_efb[0].thank_you_message.pleaseFillInRequiredFields : efb_var.text.pleaseFillInRequiredFields}">`;

 
  const showformLoggedEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="showformLoggedEl" ${valj_efb[0].stateForm && valj_efb[0].stateForm == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="showformLoggedEl">${efb_var.text.showTheFormTologgedUsers}</label>                                            
  </div>`;

  const Nadvanced = `
    ${labelEls}
    ${desEls}
    ${requireEls}`
  const labelFontSizeEls = `
    <label for="labelFontSizeEl" class="mt-3 bi-aspect-ratio mx-2 efb">${efb_var.text.labelSize}</label>
                      <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="labelFontSizeEl"  data-tag="${valj_efb[indx].type}">                                            
                          <option value="fs-6" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-6' ? `selected` : ''}>${efb_var.text.default}</option>
                          <option value="fs-7" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-7' ? `selected` : ''}>${efb_var.text.small}</option>
                          <option value="fs-5" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-5' ? `selected` : ''} >${efb_var.text.large}</option>                      
                          <option value="fs-4" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-4' ? `selected` : ''} >${efb_var.text.xlarge}</option>                      
                          <option value="fs-3" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-3' ? `selected` : ''} >${efb_var.text.xxlarge}</option>                      
                      </select>`;
  const paymentGetWayEls =()=>{
    return`<label for="paymentGetWayEl" class="mt-3 bi-wallet-fill mx-2 efb"> ${efb_var.text.paymentGateway}</label>
    <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="paymentGetWayEl"  data-tag="${valj_efb[0].type}">                                            
        <option value="stripe" selected>${efb_var.text.strip}</option>                                                            
    </select>`;
  } 
  const paymentMethodEls =()=>{
    console.log(`paymentMethodEls[${valj_efb[0].paymentmethod}]`);
    return`<label for="paymentMethodEl" class="mt-3 bi-wallet2 mx-2 efb"> ${efb_var.text.methodPayment}</label>
    <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="paymentMethodEl"  data-tag="${valj_efb[0].type}">                                            
    <option value="subscription" ${valj_efb[0].paymentmethod=='subscription' ? 'selected' :''}>${efb_var.text.subscriptionBilling}</option>                                                            
   <!-- <option value="recurring" ${valj_efb[0].paymentmethod=='recurring' ? 'selected' :''}>${efb_var.text.recurringPayment}</option> -->
    </select>`;
  } 
   
  const currencyTypeEls=()=>{
    const c =["USD","AED","AFN","ALL","AMD","ANG","AOA","ARS","AUD","AWG","AZN","BAM","BBD","BDT","BGN","BIF","BMD","BND","BOB","BRL","BSD","BWP","BYN","BZD","CAD","CDF","CHF","CLP","CNY","COP","CRC","CVE","CZK","DJF","DKK","DOP","DZD","EGP","ETB","EUR","FJD","FKP ","GBP","GEL","GIP","GMD","GNF ","GTQ ","ALL","HKD","HNL ","HRK","HTG","HUF","IDR","ILS","INR","ISK","JMD","JPY","KES","KGS","KHR","KMF","KRW","KYD","KZT","LAK","LBP","LKR","LRD","LSL","MAD","MDL","MGA","MKD","MMK","MNT","MOP","MRO","MUR","MVR","MWK","MXN","MYR","MZN","NAD","NGN","NIO","ENOUGH","NPR","NZD","PAB ","PEN","PGK","PHP","PKR","PLN","PYG ","QAR","RON","RSD","RUB","RWF","SAR","SBD","SCR","SEK","SGD","SHP","SLL","SOS","SRD","STD","SZL","THB","TJS","TOP","TRY","TTD","TWD","TZS","UAH","UGX","THIS ","UZS","VND","VUV","WST","XAF","XCD","XOF ","XPF ","YER","ZAR","ZMW"];
    let op = `<-- options -->`;
    for(let i of c){
      op += `<option value="${i.toLowerCase()}" ${valj_efb[0].currency.toUpperCase()==i ? 'selected' :''}>${i}</option>`
    }
    return `
    <label for="currencyTypeEl" class="mt-3 bi-cash mx-2 efb"> ${efb_var.text.currency}</label>
                      <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="currencyTypeEl"  data-tag="${valj_efb[0].currency}">                                            
                         ${op}                     
                      </select>
    `

  }

  const labelPostionEls = `    
  <div class="row efb">     
  <label for="labelPostionEl" class="mt-3 col-12 bi-arrows-angle-contract mx-2 efb">${efb_var.text.labelPostion}</label>
    <div class="btn-group btn-group-toggle col-12 efb" data-toggle="buttons" data-id="${idset}"  id="labelPostionEl">    
      <label class=" efb ntb btn-primary bi-chevron-bar-down ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'up' ? `active` : ''}" onClick="funSetPosElEfb('${idset}','up')">
        <input type="radio" name="options" class="opButtonEfb elEdit "   data-id="${idset}"  id="labelPostionEl" value="up" >${efb_var.text.up}</label>
      <span class="efb border-right border border-light "></span>
      <label class=" efb ntb btn-primary bi-chevron-bar-right ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'beside' ? `active` : ''}" onClick="funSetPosElEfb('${idset}','besie')">
        <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="beside"> ${efb_var.text.beside}
      </label>
    </div></div>`;
  const ElementAlignEls = (side) => {
    const left = side == 'label' ? 'txt-left' : 'justify-content-start'
    const right = side == 'label' ? 'txt-right' : 'justify-content-end'
    const center = side == 'label' ? 'txt-center' : 'justify-content-center'
    let value = valj_efb[indx].label_align;
    let t = efb_var.text.label
    if (side == 'description') {
      value = valj_efb[indx].message_align;
      t = efb_var.text.description
    }
    return `    
    <div class="efb row">     
    <label for="labelPostionEl" class="efb mt-3 col-12 bi-align-center mx-2">${side} ${efb_var.text.align}</label>
      <div class="efb btn-group btn-group-toggle col-12 " data-toggle="buttons" data-side="${side}" data-id="${idset}"  id="ElementAlignEl">    
        <label class=" efb ntb btn-primary bi-align-start ${value == left ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${left}','${side}')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit "  data-id="${idset}"  id="labelPostionEl" value="left" >${efb_var.text.left}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" efb ntb btn-primary bi-align-center ${value == center ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${center}','${side}')">
          <input type="radio" name="options" class="opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="center">${efb_var.text.center}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" efb ntb btn-primary bi-align-end ${value == right ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${right}','${side}')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="right">${efb_var.text.right}</label>
      </div></div>`;
  }
  const widthEls = `
    <label for="widthEl" class="efb mt-3 bi-arrow-left-right mx-2">${efb_var.text.width}</label>
    <select  data-id="${idset}" class="efb efb-rounded elEdit form-select"  id="sizeEl" >                                            
        <option value="33" ${valj_efb[indx].size && valj_efb[indx].size == 33 ? `selected` : ''}>33%</option>
        <option value="50" ${valj_efb[indx].size && valj_efb[indx].size == 50 ? `selected` : ''}>50%</option>
        <option value="80" ${valj_efb[indx].size && valj_efb[indx].size == 80 ? `selected` : ''} >80%</option>
        <option value="100" ${valj_efb[indx].size && valj_efb[indx].size == 100 ? `selected` : ''} >100%</option>
    </select>
    `
  const classesEls = `
    <label for="cssClasses" class="efb mt-3 bi-journal-code mx-2">${efb_var.text.cSSClasses}</label>
    <input type="text"  data-id="${idset}" class="efb elEdit text-muted form-control border-d efb-rounded efb mb-3 mb-1" id="classesEl" placeholder="${efb_var.text.cSSClasses}"  ${valj_efb[indx].classes && valj_efb[indx].classes.length > 1 ? `value="${valj_efb[indx].classes}"` : ''}>
    `
  const valueEls = `
  <label for="valueEl" class="efb mt-3 bi-cursor-text mx-2">${efb_var.text.value}</label>
    <input type="text"  data-id="${idset}" class="elEdit text-muted form-control border-d efb-rounded efb mb-3" data-tag="${valj_efb[indx].type}" id="valueEl" placeholder="${efb_var.text.defaultValue}" ${valj_efb[indx].value && valj_efb[indx].value.length > 1 ? `value="${valj_efb[indx].value}"` : ''}>
    `
  const placeholderEls = `
    <label for="placeholderEl" class="efb mt-3 bi-patch-exclamation mx-2">${efb_var.text.placeholder}</label>
    <input type="text"  data-id="${idset}" class="efb elEdit form-control text-muted border-d efb-rounded h-d-efb mb-1"id="placeholderEl" placeholder="${efb_var.text.placeholder}" ${valj_efb[indx].placeholder && valj_efb[indx].placeholder.length > 1 ? `value="${valj_efb[indx].placeholder}"` : ''}>
    `
  const cornerEls = (side) => {

    return `
      <div class="efb row">
      <label for="cornerEl" class="efb mt-3 col-12 bi-bounding-box-circles">${efb_var.text.corners}</label>
      <div class="efb btn-group col-12  btn-group-toggle" data-toggle="buttons" data-side="${side}" data-id="${idset}-set" data-tag="${valj_efb[indx].type}" id="cornerEl">    
        <label class=" efb ntb  btn-primary bi-app ${valj_efb[indx].corner && valj_efb[indx].corner == 'efb-rounded' ? `active` : ''}" onClick="funSetCornerElEfb('${idset}','efb-rounded')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="efb-rounded" >${efb_var.text.rounded}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" efb ntb btn-primary bi-diamond ${valj_efb[indx].corner && valj_efb[indx].corner == 'efb-square' ? `active` : ''}" onClick="funSetCornerElEfb('${idset}','efb-square')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="cornerEl" value="efb-square"> ${efb_var.text.square}</label>
      </div></div>`
  }
  const iconEls = (side) => {
    let icon = "";
    let t = ""
    let iset ="";
    //console.log(`valj_efb`,valj_efb);
    if (side == "Next") {iset=idset=side+"_"; icon = valj_efb[0].button_Next_icon; t = efb_var.text.next; }
    else if (side == "Previous") {iset=idset=side+"_"; icon = valj_efb[0].button_Previous_icon; t = efb_var.text.previous }
    else {
     idset != "button_group" ? iset=idset=valj_efb[indx].id_: iset=idset="button_group_"
      if(isNumericEfb(iset))idset=iset="step-"+iset;
     //console.log(iset)
      icon = valj_efb[indx].icon }
    let list ="<!-- list of Bootstrap Icon --!>"
    bootstrap_icons.forEach((e,key )=> {
      const v= e.replace(`-`, ' ');      
      list+=`<tr class="efblist text-white" data-id="${iset}" data-name="bi-${e}" data-row="${key}" data-state="0" data-visible="1">
      <th scope="row" class="bi-${e}"></th>
      <td>${v}</td>      
    </tr>`      
    });
    let iNo =''
    if (icon.length>1){
       iNo =bootstrap_icons.findIndex(x=>x==icon.replace('bi-',''));
    }
    //check for Nex and previous
  
    return `<label for="iconEl" class="efb form-label  bi-heptagon mx-2 mt-2">${t} ${efb_var.text.icon} </label>
    
      
      <div class=" efb listSelect my-2">     
      <div class="efb efblist mx-1  p-2 inplist  h-d-efb elEdit border efb border-d efb-rounded" id="iconEl" data-id="${iset}" data-idset="${idset}" data-side="${side}"  data-no="1" data-parent="1" data-iconset="${iNo}" data-select="">${icon=="" ? efb_var.text.selectOption :icon}</div>
      <i class="efb efblist  h-d-efb iconDD bi-caret-down-fill text-primary" data-id="${iset}"></i>
      <div class="efb efblist mx-1  listContent d-none rounded-bottom  bg-secondary" data-id="${iset}" data-list="${iset}">
      <table class="efb table ${iset}">
              <thead class="efb efblist">
                <tr><div class="efb searchSection efblist  p-2 bg-secondary">
                    <i class="efb efblist  searchIcon  bi-search text-primary "></i>
                    <input type="text" class="efb efblist search searchBox my-1 col-12 rounded border-primary" data-id="${iset}" data-tag="search" placeholder="${efb_var.text.search}" onkeyup="FunSearchTableEfb('${iset}')">                                            
                  </div></tr>
              </thead> <tbody >
              ${list}
              </tbody></table>
      </div>
  </div>
      `
  }


  const SingleTextEls = (side) => {
    let text = "";
    let t = ""
    if (side == "Next") { text = valj_efb[0].button_Next_text; t = efb_var.text.next; }
    else if (side == "Previous") { text = valj_efb[0].button_Previous_text; t = efb_var.text.previous; }
    else { text = valj_efb[indx].button_single_text }
    /* side == "Next" ? text = valj_efb[0].button_Next_text : text = valj_efb[0].button_Previous_text;
    side == "" ? text = valj_efb[indx].button_single_text : 0; */
    side == "Next" ? text = valj_efb[0].button_Next_text : text = valj_efb[0].button_Previous_text;
    side == "" ? text = valj_efb[indx].button_single_text : 0;
    return `<label for="SingleTextEl" class="efb form-label  mt-2">${t} ${efb_var.text.text}</label>
    <input type="text" data-id="${idset}" class="efb elEdit text-muted border-d efb-rounded form-control h-d-efb mb-1" data-side="${side}" placeholder="${efb_var.text.text}" id="SingleTextEl" required value="${text ? text : ''}">`
  }


  const fileTypeEls = `
        <label for="fileTypeEl" class="efb mt-3 bi-file-earmark-medical mx-2 ">${efb_var.text.fileType}</label>
        <select  data-id="${idset}" class="efb elEdit form-select border-d efb-rounded"  id="fileTypeEl" data-tag="${valj_efb[indx].type}">                                            
        <option value="document" ${valj_efb[indx].file && valj_efb[indx].file == 'document' ? `selected` : ''} >${efb_var.text.documents}</option>
        <option value="image" ${valj_efb[indx].file && valj_efb[indx].file == 'image' ? `selected` : ''}>${efb_var.text.image}</option>
        <option value="media" ${valj_efb[indx].file && valj_efb[indx].file == 'media' ? `selected` : ''} >${efb_var.text.media}</option>
        <option value="zip" ${valj_efb[indx].file && valj_efb[indx].file == 'zip' ? `selected` : ''} >${efb_var.text.zip}</option>
    </select>
    `
  const btnColorEls =() =>{
    //console.log(indx,valj_efb[indx]);
    color = valj_efb[indx].button_color;
    //console.log(ColorNameToHexEfbOfElEfb(color.slice(4),indx,'btn') ,color)
    const hex=ColorNameToHexEfbOfElEfb(color.slice(4),indx,'btn') //slice text=5 bg=2 border=6 btn=3    
    addColorTolistEfb(hex);
    idset =  valj_efb[indx].type =="esign" ? idset+'-id' :idset;
    return `<label for="btnColorEl" class="mt-3 bi-paint-bucket mx-2 efb">${efb_var.text.buttonColor}</label>
    <input type="color" id="btnColorEl" class="elEdit form-select efb border-d efb-rounded" data-id="${idset}" data-el="button" data-type="button"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="btnColorEl" list="color_list_efb" id="${idset}" >
    `
  } 

  const selectBorderColorEls = (forEl) => {       
    let color = valj_efb[indx].el_border_color;
    let t = ''
    const hex=ColorNameToHexEfbOfElEfb(color.slice(7),indx,'border') //slice text=5 bg=2 border=6 btn=3       
    addColorTolistEfb(hex);
    return `<label for="selectBorderColorEl" class="mt-3 bi-paint-bucket mx-2 efb">${efb_var.text.borderColor}</label>
    <input type="color" id="selectBorderColorEl" class="elEdit form-select efb border-d efb-rounded" data-id="${idset}" data-el="${forEl}" data-type="border"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="selectColorEl" list="color_list_efb" id="${idset}" >
    `
  }
  const hrefEls = () => {       
    return `<label for="hrefEl" class="mt-3 bi-box-arrow-up-right mx-2 efb">${efb_var.text.link}</label>
    <input type="url" id="hrefEl" class="efb elEdit text-muted form-control border-d efb-rounded efb mb-3 mb-1" data-id="${idset}" data-el="link" data-type="border" placeholder="https://"  data-tag="${valj_efb[indx].type}" value="${valj_efb[indx].href}" name="hrefEls"  id="${idset}" >
    `
  }
  const selectColorEls = (forEl ,f) => {
    //f ===> text , border,  bg 
    //console.log(forEl,indx)
    let t = ''
    let color = '';
    let hex=''
    if (forEl == 'icon') {
      color = valj_efb[indx].icon_color;
      //console.log(color.slice(5));
      t = efb_var.text.icon;
      if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'icon') //slice text=5 bg=2 border=6 btn=3
    } else if (forEl == 'description') {
      color = valj_efb[indx].message_text_color;
      //console.log(color.slice(5));
      t = efb_var.text.description
      if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'description')
    } else if (forEl == 'label') {
      color = valj_efb[indx].label_text_color;
      //console.log(color.slice(5));
      t = efb_var.text.label
      if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'label')
    } else if (forEl == "el") {
      color = valj_efb[indx].el_text_color;
      //console.log(color.slice(5));
      t = efb_var.text.field
      if(color!="") hex=ColorNameToHexEfbOfElEfb(color.slice(5),indx,'el')
    }
    addColorTolistEfb(hex);
    return `<label for="selectColorEl" class="mt-3 bi-paint-bucket mx-2 efb">${t} ${efb_var.text.clr}</label>
    <input type="color" id="selectColorEl" class="elEdit form-select efb border-d efb-rounded" data-id="${idset}" data-el="${forEl}" data-type="${f}"  data-tag="${valj_efb[indx].type}" value="${hex!=''?hex:'#fff000'}" name="selectColorEl" list="color_list_efb" id="${idset}" >
    `
  }
  const selectHeightEls = () => {
    return `
      <label for="selectHeightEl" class="efb mt-3 bi-arrow-down-up mx-2">${efb_var.text.height}</label>
      <select  data-id="${idset}" class="efb efb-rounded elEdit form-select"  id="selectHeightEl" data-tag="${valj_efb[indx].type}">                                            
      <option value="h-d-efb" ${valj_efb[indx].el_text_size && valj_efb[indx].el_height == 'h-d-efb' ? `selected` : ''}>${efb_var.text.default}</option>
      <option value="h-l-efb"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_height == 'h-l-efb' ? `selected` : ''} >${efb_var.text.large}</option>                      
      <option value="h-xl-efb"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_height == 'h-xl-efb' ? `selected` : ''} >${efb_var.text.xlarge}</option>                      
      <option value="h-xxl-efb"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_height == 'h-xxl-efb' ? `selected` : ''} >${efb_var.text.xxlarge}</option>                      
      <option value="h-xxxl-efb"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_height == 'h-xxxl-efb' ? `selected` : ''} >${efb_var.text.xxxlarge}</option>   
      </select>
      `
  }
  const fontSizeEls = () => {
    return `
      <label for="fontSizeEl" class="efb mt-3 bi-arrow-down-up mx-2">${efb_var.text.height}</label>
      <select  data-id="${idset}" class="efb efb-rounded elEdit form-select"  id="fontSizeEl" data-tag="${valj_efb[indx].type}">                                                  
      <option value="display-1" ${valj_efb[indx].el_text_size && valj_efb[indx].el_text_size ==  'display-1' ? `selected` : ''}>${efb_var.text.xxxlarge}</option>
      <option value="display-2"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_text_size == 'display-2' ? `selected` : ''} >${efb_var.text.xxlarge}</option>                      
      <option value="display-3"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_text_size == 'display-3' ? `selected` : ''} >${efb_var.text.xlarge}</option>                      
      <option value="display-4"  ${valj_efb[indx].el_text_size && valj_efb[indx].el_text_size == 'display-4' ? `selected` : ''} >${efb_var.text.large}</option>                            
      </select>
      `
  }

  const selectMultiSelectEls = `<label for="labelEl" class="form-label mt-2 mb-1 efb">${efb_var.text.maxSelect}</label>
  <input type="number"  data-id="${idset}" class="efb elEdit form-control text-muted border-d efb-rounded h-d-efb mb-1"  placeholder="${efb_var.text.maxSelect}" id="selectMultiSelectMaxEl"  value="${valj_efb[indx].maxSelect ? valj_efb[indx].maxSelect : '2'}" >
  <label for="labelEl" class="form-label mt-2 mb-1 efb">${efb_var.text.minSelect}</label>
  <input type="number"  data-id="${idset}" class="efb elEdit form-control text-muted border-d efb-rounded h-d-efb mb-1"  placeholder="${efb_var.text.minSelect}" id="selectMultiSelectMinEl"  value="${valj_efb[indx].minSelect ? valj_efb[indx].minSelect : '0'}" >`

//console.log(el.dataset);
 
  switch (el.dataset.tag) {
    case 'email':
    case 'text':
    case 'password':
    case 'tel':
    case 'number':
    case 'url':
    case "textarea":
      body = `
              <div class="efb mb-3">
              <!--  not   advanced-->
              ${Nadvanced}
              ${placeholderEls}
              ${el.dataset.tag == "email" ? emailEls : ''}
              <!--  not   advanced-->
              <div class="efb d-grid gap-2">              
              <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                  <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
              </button>
              </div>
              <div class="efb  mb-3 mt-3 collapse" id="collapseAdvanced">
                      <div class="efb mb-3 px-3 row">                                            
                      ${labelFontSizeEls}
                      ${selectColorEls('label','text')}
                      ${selectColorEls('description','text')}
                      ${selectColorEls('el','text')}
                      ${selectBorderColorEls('element')}
                      
                      ${labelPostionEls}
                      ${ElementAlignEls('label')}
                      ${ElementAlignEls('description')}
                      ${widthEls}
                      ${selectHeightEls()}
                      ${cornerEls('')}
                      ${valueEls}
                      ${classesEls}
                      </div>
                  </div>
              </div><div class="efb clearfix"></div>
              `
      break;
    case "heading":
      body = `
              <div class="efb mb-3">
              <!--  not   advanced-->
              ${valueEls}
              ${selectColorEls('el','heading')}
              ${fontSizeEls()}                                  
              ${classesEls}
              <div class="efb clearfix"></div>
              `
      break;
    case "link":
      body = `
              <div class="efb mb-3">
              <!--  not   advanced-->
              ${valueEls}
              ${hrefEls()}                                  
              ${selectColorEls('el','link')}
              ${selectHeightEls()}                                  
              ${classesEls}
              <div class="efb clearfix"></div>
              `
      break;
    case "radio":
    case "checkbox":
    case "select":
    case "multiselect":
    case "conturyList":
    case "stateProvince":
    case "payCheckbox":
    case "payRadio":
    case "paySelect":
    case "payMultiselect":
      const objOptions = valj_efb.filter(obj => {
        return obj.parent === el.id
      })
      let s = el.dataset.tag;
      s= s=="payCheckbox" || s=="payRadio" || s=="paySelect" || s=="payMultiselect" ? true :false
      const newRndm = Math.random().toString(36).substr(2, 9);
      let opetions = `<!-- options --!>`;
      const col = s==true ||  form_type_emsFormBuilder=="smart"  ?'col-md-7':'col-md-12'

      if (objOptions.length > 0) {
        for (let ob of objOptions) {         
          opetions += `<div id="${ob.id_op}-v" class="col-md-12">
        <input type="text" placeholder="${efb_var.text.name}" id="EditOption"  value="${ob.value}" data-parent="${el.id}" data-id="${ob.id_op}" data-tag="${el.dataset.tag}" class="efb ${col}  text-muted mb-1 fs-7 border-d efb-rounded elEdit">
        ${s==true ? `<input type="number" placeholder="$"  value='${typeof ob.price=="string" ? ob.price : 0}' data-value="" id="paymentOption" data-parent="${el.id}" data-id="${ob.id_op}" data-tag="${el.dataset.tag}-payment"  class="efb col-md-3 text-muted mb-1 fs-7 border-d efb-rounded elEdit">` :''}
        <div class="efb btn-edit-holder" id="deleteOption" data-parent_id="${ob.parent}">
          <button type="button" id="deleteOption"  onClick="delete_option_efb('${ob.id_op}')" data-parent="${el.id}" data-tag="${el.dataset.tag}"  data-id="${ob.id_op}" class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}"> 
              <i class="efb efb bi-x-lg text-danger"></i>
          </button>
          <button type="button" id="addOption" onClick="add_option_edit_pro_efb('${el.id.trim()}','${el.dataset.tag.trim()}' ,${valj_efb.length})" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndm}" class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" > 
              <i class="efb bi-plus-circle  text-success"></i>
          </button> 
          
        </div>
        </div>`
        }
      }

      //optionElpush_efb
      
      body = `
              <div class="efb mb-3">
              <!--notAdvanced-->
              ${Nadvanced}
              ${ el.dataset.tag == 'multiselect' ||el.dataset.tag == 'payMultiselect'? selectMultiSelectEls :''}
              <label for="optionListefb" class="efb ">Options 
              <button type="button" id="addOption" onClick="add_option_edit_pro_efb('${el.id.trim()}','${el.dataset.tag.trim()}' ,${valj_efb.length})" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndm}"   class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" > 
              <i class="efb bi-plus-circle  text-success"></i>
             </button> 
              </label>
              <div class="efb mb-3" id="optionListefb">
               ${opetions}
              </div>
              <!--notAdvanced-->

              <!--advanced-->
              <div class="efb d-grid gap-2">
                  <button class="btn efb btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                    <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}                    
                  </button>
              </div>
              <div class="efb collapse mb-3 mt-3 " id="collapseAdvanced">
                      <div class="efb mb-3 px-3 row">                                        
                      ${labelFontSizeEls}
                      ${selectColorEls('label','text')}
                      ${selectColorEls('description','text')}
                      ${fun_el_select_in_efb(el.dataset.tag)  ? cornerEls('') : ''} 
                      ${fun_el_select_in_efb(el.dataset.tag) ? selectBorderColorEls('element') : ''} 
                      ${el.dataset.tag != 'multiselect' || el.dataset.tag != 'payMultiselect' ? selectColorEls('el','text') : ''} 
                      ${labelPostionEls}
                      ${ElementAlignEls('label')}
                      ${ElementAlignEls('description')}
                      ${widthEls}     
                      ${fun_el_select_in_efb(el.dataset.tag) ? selectHeightEls() : ''}               
                      ${classesEls}
                      </div>
                  </div>
                  
              </div>
              <div class="efb clearfix"></div>
              `
      break;
    case "date":
    case "color":
    case "range":
    case "esign":
    case "rating":
    case "switch":
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${Nadvanced}
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb  mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">                                            
              ${labelFontSizeEls}
              ${selectColorEls('label','text')}
              ${selectColorEls('description','text')}
             
              ${el.dataset.tag == 'rating' || el.dataset.tag == 'range'  || el.dataset.tag == 'switch' ? "" : selectBorderColorEls('element')}
              ${labelPostionEls}
              ${ElementAlignEls('label')}
              ${ElementAlignEls('description')}
              ${el.dataset.tag == 'rating' ? '' : widthEls}
              ${selectHeightEls()}
              ${el.dataset.tag == 'rating' || el.dataset.tag == 'switch' ? '' : cornerEls('')}
              ${/* el.dataset.tag == 'esign' ? selectColorEls('icon') : '' */ ''}
              ${el.dataset.tag == 'esign' ? iconEls('') : ''}
              ${el.dataset.tag == 'esign' ? btnColorEls() : ''}
              ${el.dataset.tag == 'esign' ? SingleTextEls('') : ''}
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "file":
    case "dadfile":
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${Nadvanced}
      ${el.dataset.tag == 'dadfile' ?fileTypeEls:''}
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">                                                          
              ${labelFontSizeEls}
              ${selectColorEls('label','text')}
              ${selectColorEls('description','text')}
              ${el.dataset.tag == 'dadfile' ? selectColorEls('icon','text') : ''}
              ${el.dataset.tag == 'dadfile' ? btnColorEls() : ''}
              ${selectBorderColorEls('element')}
              ${labelPostionEls}
              ${ElementAlignEls('label')}
              ${ElementAlignEls('description')}
              ${widthEls}
              ${selectHeightEls()}
              ${cornerEls()}
              ${classesEls}
              <!-- select type of file -->
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "maps":
      // Object.assign(valj_efb[indx], { lat: 49.24803870604257, lon: -123.10512829684463 })

      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${Nadvanced}
      <label for="letEl" class="efb form-label  mt-2">${efb_var.text.latitude}</label>
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control border-d efb-rounded efb h-d-efb mb-1" placeholder="${efb_var.text.exDot} 49.24803870604257" id="letEl" required value="${valj_efb[indx].lat}">
      <label for="lonEl" class="efb form-label  mt-2">${efb_var.text.longitude}</label>
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control border-d efb-rounded efb h-d-efb mb-1" placeholder="${efb_var.text.exDot}  -123.10512829684463" id="lonEl" required value="${valj_efb[indx].lng}">
      <label for="marksEl" class="efb form-label  mt-2">${efb_var.text.points}<a class="fs-7 efb" onClick ="open_whiteStudio_efb('pickupByUser')">${efb_var.text.help}</a></label></label>
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control border-d efb-rounded efb h-d-efb mb-1" placeholder=${efb_var.text.exDot}  1" id="marksEl" required value="${valj_efb[indx].mark}">
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb  bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">
              ${labelPostionEls} 
              ${ElementAlignEls('label')}   
              ${ElementAlignEls('description')}                                        
              ${widthEls}
              ${selectHeightEls()}
              ${labelFontSizeEls}
              ${selectColorEls('label','text')}
              ${selectColorEls('description','text')}
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `

      break;
    case "html":
      const valHTML = valj_efb[indx].value.replace(/@!/g,`"`);
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      <label for="htmlCodeEl" class="efb form-label mt-2 mb-1"><i class="efb bi-code-square mx-2" ></i>${efb_var.text.code}</label>
      <small class="text-info text-danger bg-muted  efb">${efb_var.text.pleaseDoNotAddJsCode}</small>
      <textarea placeholder="${efb_var.text.htmlCode}" 
      class="elEdit form-control efb  h-d-efb   mb-1"
       data-id="${valj_efb[indx].id_}" id="htmlCodeEl" rows="13" >${valHTML}</textarea>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "yesNo":
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${Nadvanced}
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb  mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">                                            
              ${labelFontSizeEls}
              ${selectColorEls('label','text')}
              ${selectColorEls('description','text')}
              ${selectColorEls('el','text')}
              ${btnColorEls()}
              ${labelPostionEls}
              ${ElementAlignEls('label')}
              ${ElementAlignEls('description')}
              
              ${widthEls}
              ${selectHeightEls()}
              ${cornerEls('yesNo')}
              <label for="valueEl" class="efb mt-3 bi-cursor-text mx-2">${efb_var.text.button1Value}</label>
              <input type="text"  data-id="${idset}" class="elEdit border-d efb-rounded text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="1" placeholder="${efb_var.text.exDot} ${efb_var.text.yes}" value="${valj_efb[indx].button_1_text}">
              <label for="valueEl" class="efb mt-3 bi-cursor-text mx-2">${efb_var.text.button2Value}</label>
              <input type="text"  data-id="${idset}" class="elEdit border-d efb-rounded text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="2" placeholder="${efb_var.text.exDot} ${efb_var.text.no}" value="${valj_efb[indx].button_2_text}">
              ${classesEls}
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "booking":
      break;
    case "steps":
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${labelEls}
      ${desEls}
  
                                  
      </div>
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb  mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">
              ${iconEls('')}
             
              ${selectColorEls('label','text')}   
              ${selectColorEls('description','text')}     
              ${selectColorEls('icon','text')}                                          
              ${classesEls}              
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "buttonNav":
      //console.log("buttonNav");
      let content = ` 
      ${SingleTextEls('')}
      ${iconEls('')}
      ${selectColorEls('el','text')}
      ${cornerEls('Next')}
      ${btnColorEls()}
      ${selectColorEls('icon','text')}
      ${selectHeightEls()}`

      if (valj_efb[0].button_state != "single") {
        content = `
           ${SingleTextEls("Previous")}
           ${iconEls("Previous")}
           ${SingleTextEls("Next")}
           ${iconEls("Next")}
           ${selectColorEls('el','text')}
           ${cornerEls('Next')}
           ${btnColorEls()}
           ${selectColorEls('icon','text')}
           ${selectHeightEls()}
           `
      }
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
                  ${content}                      
      </div>
      `
      break;
    case 'formSet':
      body = `
        <label for="formNameEl" class="form-label mt-2 mb-1 efb">${efb_var.text.formName}<span class=" mx-1 efb text-danger">*</span></label>
         <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb  h-d-efb  mb-1"  placeholder="${efb_var.text.formName}" id="formNameEl" required value="${valj_efb[0].formName}">
        ${trackingCodeEls}
        ${captchaEls}
        ${showSIconsEls}
        ${showSprosiEls}
        ${showformLoggedEls}
        ${cardEls}
        ${adminFormEmailEls}
       
        ${valj_efb[0].type=="payment" ? currencyTypeEls() :''}
        ${valj_efb[0].type=="payment" ? paymentMethodEls() :''}
        <div class="efb d-grid gap-2">
        <button class="btn efb btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}                    
        </button>
    </div>
    <div class="efb collapse mb-3 mt-3 " id="collapseAdvanced">
            <div class="efb mb-3 px-3 row">   
        ${thankYouMessageDoneEls}
        ${thankYouMessageEls}
        ${thankYouMessageConfirmationCodeEls}
        
        </div>
        </div>        
    </div>
    <div class="efb clearfix"></div>
      
        `
      break;

  }

  
  //console.log("show_setting_window_efb",body)
  //show_modal_efb(body, efb_var.text.edit, 'bi-ui-checks mx-2', 'settingBox')
  sideMenuEfb(1)
 // document.getElementById('sideBoxEfb').classList.add('show');
  //console.log('body',body);
  document.getElementById('sideMenuConEfb').innerHTML=body;
  //console.log(document.getElementById('sideMenuConEfb').innerHTML)
  for (const el of document.querySelectorAll(`.elEdit`)) {
    if(el.tagName!="DIV"){el.addEventListener("change", (e) => { change_el_edit_Efb(el);})}
    else{ }
  }


  //  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  // myModal.show()



}

let change_el_edit_Efb = (el) => {
  let lenV = valj_efb.length
  console.log(el.id , el.value)
  if (el.value.length>0 && el.value.search(/(")+/g)!=-1){
    el.value = el.value.replaceAll(`"`,'');
    noti_message_efb(efb_var.text.error,`Don't use forbidden Character like: "`,10,"danger");
  }
  
  if(lenV>20){
    timeout=5;
    const p = calPLenEfb(lenV)/2
    if(el.dataset.tag=="multiselect" || el.dataset.tag=="payMultiselect")timeout=100;
    lenV = (lenV * (Math.log(lenV)) * p);
    setTimeout(() => {
      document.getElementById("overlay_efb").classList.remove("d-none")
      document.getElementById("overlay_efb").classList.add("d-block")
      setTimeout(() => {document.getElementById("overlay_efb").classList.remove("d-block")
      }, lenV);
      clearTimeout(lenV);      
    }, timeout);
    clearTimeout(timeout);
  }

  let postId = el.dataset.id.includes('step-') ? el.dataset.id.slice(5) : el.dataset.id
  postId = el.dataset.id.includes('Next_') || el.dataset.id.includes('Previous_') ? 0 : postId;
  //console.log(el.dataset.id != "button_group" || el.dataset.id != "button_group_",el,postId)
  const indx = el.dataset.id != "button_group" && el.dataset.id != "button_group_" && postId!=0 ? valj_efb.findIndex(x => x.dataId == postId) : 0;
  const len_Valj =valj_efb.length;
  //console.log(el.dataset,indx,postId);
  postId=null

  let clss = ''
  let c,color;
  setTimeout(()=>{
    switch (el.id) {
      case "labelEl":
        valj_efb[indx].name = el.value;
        document.getElementById(`${valj_efb[indx].id_}_lab`).innerHTML = el.value
        break;
        case "desEl":
          valj_efb[indx].message = el.value;
          document.getElementById(`${valj_efb[indx].id_}-des`).innerHTML = el.value
          break;
          case "adminFormEmailEl":
       
            if(efb_var.smtp=="true"){
          if (el.value.match(/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/)) // email validation
          {
            valj_efb[0].email = el.value;
            return true;
          }
          else {
            document.getElementById("adminFormEmailEl").value = "";
            noti_message_efb(efb_var.text.error,efb_var.text.invalidEmail,10,"danger");
          }
        }else{
          // trackingCodeEl.checked=false;
          document.getElementById("adminFormEmailEl").value = "";
          noti_message_efb(efb_var.text.error,efb_var.text.sMTPNotWork,20,"danger")
        }
        
        
        break;
        case "cardEl":
          valj_efb[0].dShowBg? valj_efb[0].dShowBg = el.checked : Object.assign(valj_efb[0] , {dShowBg:el.checked})
          console.log(valj_efb[0] )
          
          //console.log('test',valj_efb[indx].showbg);
          break;
        case "requiredEl":
          valj_efb[indx].required = el.checked
       
        document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML = el.checked == true ? '*' : '';
        const aId = {
          email: "_", text: "_", password: "_", tel: "_", url: "_", date: "_", color: "_", range: "_", number: "_", file: "_",
          textarea: "_", dadfile: "_", maps: "-map", checkbox: "_options", radio: "_options", select: "_options",
          multiselect: "_options", esign: "-sig-data", rating: "-stared", yesNo: "_yn"
        }
        postId = aId[valj_efb[indx].type]
        id = valj_efb[indx].id_     
        document.getElementById(`${id}${postId}`).classList.toggle('required')
        //postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
        break;
      case "SendemailEl":
        if(efb_var.smtp=="true"){
          valj_efb[0].sendEmail = el.checked
          valj_efb[0].email_to = el.dataset.id.replace('-id', '');
        }else{
         // trackingCodeEl.checked=false;
          document.getElementById("SendemailEl").checked = false;
          noti_message_efb(efb_var.text.error,efb_var.text.sMTPNotWork,20,"danger")
        }
      
        break;
      case "formNameEl":
        valj_efb[0].formName = el.value
        break;
      case "trackingCodeEl":
        valj_efb[0].trackingCode = el.checked;

        break;
      case "thankYouMessageDoneEl":
        valj_efb[0].thank_you_message.done = el.value;
        break;
      case "thankYouMessageEl":
        valj_efb[0].thank_you_message.thankYou = el.value;
        break;
      case "thankYouMessageConfirmationCodeEl":
        valj_efb[0].thank_you_message.trackingCode = el.value;
        break;
      case "thankYouMessageErrorEl":
        valj_efb[0].thank_you_message.error = el.value;
        break;
      case "thankYouMessagepleaseFillInRequiredFieldsEl":
        valj_efb[0].thank_you_message.pleaseFillInRequiredFields = el.value;
        break;
      case "captchaEl":
       
        if(efb_var.captcha=="true"){
          valj_efb[0].captcha = el.checked
          el.checked==true ? document.getElementById('recaptcha_efb').classList.remove('d-none') : document.getElementById('recaptcha_efb').classList.add('d-none')

        }else{
         // trackingCodeEl.checked=false;
          document.getElementById("captchaEl").checked = false;
          noti_message_efb(efb_var.text.reCAPTCHA,efb_var.text.reCAPTCHASetError,20,"danger")
          
        }
        break;
      case "showSIconsEl":
        valj_efb[0].show_icon = el.checked
        break;
      case "showSprosiEl":
        valj_efb[0].show_pro_bar = el.checked
        break;
      case "showformLoggedEl":
        valj_efb[0].stateForm =el.checked==true ? 1 :0
        break;
      case "placeholderEl":
        document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).placeholder = el.value;
  
        valj_efb[indx].placeholder = el.value;
        break;
      case "valueEl":
        console.log(el.dataset.tag);
        if (el.dataset.tag != 'yesNo' && el.dataset.tag != 'heading' && el.dataset.tag != 'textarea') {
          
          //document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).value = el.value;
          document.getElementById(`${valj_efb[indx].id_}_`).value = el.value;
          valj_efb[indx].value = el.value;
        }else if(el.dataset.tag == 'heading' || el.dataset.tag == 'textarea'){
          console.log(valj_efb[indx].id_,document.getElementById(`${valj_efb[indx].id_}_`) );
          document.getElementById(`${valj_efb[indx].id_}_`).innerHTML = el.value;
          valj_efb[indx].value = el.value;
        }else {
          //yesNo
          id = `${valj_efb[indx].id_}_${el.dataset.no}`
          document.getElementById(id).value = el.value;
          document.getElementById(`${id}_lab`).innerHTML = el.value;
          el.dataset.no == 1 ? valj_efb[indx].button_1_text = el.value : valj_efb[indx].button_2_text = el.value
        }
        break;
      case "classesEl":
        id= valj_efb[indx].id_;
        const v = el.value.replace(` `, `,`);
        //document.querySelector(`[data-id="${idset}"]`).classes=el.value;
     //   clss=document.getElementById(id).className;
        document.getElementById(id).className+=el.value.replace(`,`, ` `);
        valj_efb[indx].classes = v;
        break;
      case "sizeEl":
        postId = document.getElementById(`${valj_efb[indx].id_}_labG`)
        const op = el.options[el.selectedIndex].value;
        valj_efb[indx].size = op;
        get_position_col_el(valj_efb[indx].dataId, true);
        break;
      case "cornerEl":

        const co = el.options[el.selectedIndex].value;
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
          valj_efb[indx].corner = co;
          postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
          let cornEl = document.getElementById(postId);          
          // /
          if (fun_el_select_in_efb(el.dataset.tag)) cornEl = el.dataset.tag == 'conturyList' || el.dataset.tag == 'stateProvince'|| el.dataset.tag == 'select'  ? document.getElementById(`${postId}options`) : document.getElementById(`${id}ms`)
          //efb-square

          cornEl.classList.toggle('efb-square')
          if (el.dataset.tag == 'dadfile' || el.dataset.tag == 'esign') document.getElementById(`${valj_efb[indx].id_}_b`).classList.toggle('efb-square')
  
  
        } else {
          valj_efb[0].corner = co;
          postId = document.getElementById('btn_send_efb');
        
          postId.classList.toggle('efb-square')
          document.getElementById('next_efb').classList.toggle('efb-square')
          document.getElementById('prev_efb').classList.toggle('efb-square')
        }
        break;
      case "labelFontSizeEl":
        valj_efb[indx].label_text_size = el.options[el.selectedIndex].value;
        let fontleb = document.getElementById(`${valj_efb[indx].id_}_lab`);
        const sizef = el.options[el.selectedIndex].value
        fontleb.className = fontSizeChangerEfb(fontleb.className, sizef)
        if (el.dataset.tag == "step") { let iconTag = document.getElementById(`${valj_efb[indx].id_}_icon`); iconTag.className = fontSizeChangerEfb(iconTag.className, sizef); }
        break;
      case "paymentGetWayEl":
        console.log('paymentGetWayEl')
        valj_efb[0].payment = el.options[el.selectedIndex].value;
        console.log(el.options[el.selectedIndex].value);
        break;
      case "paymentMethodEl":
        console.log('paymentMethodEl')
        valj_efb[0].paymentmethod = el.options[el.selectedIndex].value;
        console.log(el.options[el.selectedIndex].value,valj_efb[0].paymentmethod);
        break;
        //paymentMethodEl
      case "currencyTypeEl":
        console.log('currencyTypeEl')
        valj_efb[0].currency = el.options[el.selectedIndex].value;
        console.log(el.options[el.selectedIndex].value);
        break;
      case "fileTypeEl":
        valj_efb[indx].file = el.options[el.selectedIndex].value;
        
        console.log(valj_efb[indx].file)
        valj_efb[indx].value = el.options[el.selectedIndex].value;
        let nfile = el.options[el.selectedIndex].value.toLowerCase();
        nfile = efb_var.text[nfile];
        if( document.getElementById(`${valj_efb[indx].id_}_txt`))document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${nfile}`
        break;
      case "btnColorEl":
        color = el.value;
        //valj_efb[indx].button_color = el.options[el.selectedIndex].value;

        clss = switch_color_efb(color);;
        if(clss.includes('colorDEfb')){addStyleColorBodyEfb(clss,color,"btn",indx);}
        if (indx != 0) {
          if (el.dataset.tag != "yesNo") {
            if((indx==0 && valj_efb[indx].step==1) || indx>0){
              document.getElementById(`${valj_efb[indx].id_}_b`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b`).className,"btn-"+clss)
            }else{
              document.getElementById(`prev_efb`).className = colorBtnChangerEfb(document.getElementById(`prev_efb`).className,"btn-"+clss)
              document.getElementById(`next_efb`).className = colorBtnChangerEfb(document.getElementById(`next_efb`).className,"btn-"+clss)
            }
          } else {
            document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className,"btn-"+clss)
            document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className,"btn-"+clss)
          }
        }else {          
          document.getElementById(`btn_send_efb`).className = colorBtnChangerEfb(document.getElementById(`btn_send_efb`).className,"btn-"+clss)
          document.getElementById(`next_efb`).className = colorBtnChangerEfb(document.getElementById(`next_efb`).className,"btn-"+clss)
          document.getElementById(`prev_efb`).className = colorBtnChangerEfb(document.getElementById(`prev_efb`).className,"btn-"+clss)
        }
        valj_efb[indx].button_color = "btn-"+clss;
        //console.log( valj_efb[indx]);
        if(clss.includes('colorDEfb')){
          valj_efb[indx].style_btn_color ?  valj_efb[indx].style_btn_color=color : Object.assign(valj_efb[indx] , {style_btn_color:color}) ;
          addColorTolistEfb(color)
        }

        break;
      case "selectColorEl":  
         color = el.value;
         c =switch_color_efb(color);
        
        //console.log(color, c ,el.dataset,indx)
        if(c.includes('colorDEfb')){      
          addStyleColorBodyEfb(c,color,"text",indx);
        }
        postId = ''
        if (el.dataset.el == "label") {
          valj_efb[indx].label_text_color = "text-"+c;
          postId = valj_efb[indx].type != 'step' ? '_labG' : '_lab'
        }
        else if (el.dataset.el == "description") {
          valj_efb[indx].message_text_color ="text-"+c;
          postId = '-des'
        }
        else if (el.dataset.el == "icon") {
          //console.log(c,indx,valj_efb[indx])
          valj_efb[indx].icon_color ="text-"+c;
          postId = '_icon'
        } else if (el.dataset.el == "el") {
          valj_efb[indx].el_text_color ="text-"+c;
          postId = '_'
        }
        if (el.dataset.tag != "form" &&
          ((el.dataset.tag == "select" && el.dataset.el != "el")
            || (el.dataset.tag == "radio" && el.dataset.el != "el")
            || (el.dataset.tag == "checkbox" && el.dataset.el != "el")
            || (el.dataset.tag == "yesNo" && el.dataset.el != "el")
            || (el.dataset.tag == "stateProvince" && el.dataset.el != "el")
            || (el.dataset.tag == "conturyList" && el.dataset.el != "el")
            || (el.dataset.tag != "yesNo" && el.dataset.tag != "checkbox" 
            && el.dataset.tag != "radio" && el.dataset.tag != "select"  &&el.dataset.tag != 'stateProvince' && el.dataset.tag != 'conturyList' ))
        ) {
        // if( c=="colorDEf")   {console.log(`${valj_efb[indx].id_}${postId}`);document.getElementById(`${valj_efb[indx].id_}${postId}`).style.color=`#${color}`}
        //console.log(`id_ =[${valj_efb[indx].id_}] postId=[${postId}] index=[${indx}] id=[${valj_efb[indx].id_}${postId}]`,valj_efb[indx] )
        //console.log(document.getElementById(`${valj_efb[indx].id_}${postId}`).className)
          document.getElementById(`${valj_efb[indx].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}${postId}`).className, "text-"+c)
        } else if (el.dataset.tag == "form") {
          if (el.dataset.el != "icon" && el.dataset.el != "el") {
            document.getElementById(`${valj_efb[0].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[0].id_}${postId}`).className, "text-"+c)
            c=="colorDEf" ?  document.getElementById(`${valj_efb[indx].id_}${postId}`).style.color="#"+color:0
          } else if (el.dataset.el == "icon") {
            document.getElementById(`button_group_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_icon`).className, "text-"+c)
            document.getElementById(`button_group_Next_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_icon`).className, "text-"+c)
            document.getElementById(`button_group_Previous_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_icon`).className, "text-"+c)
            
          } else if (el.dataset.el == "el") {
            document.getElementById(`button_group_button_single_text`).className = colorTextChangerEfb(document.getElementById(`button_group_button_single_text`).className, "text-"+c)
            document.getElementById(`button_group_Next_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_button_text`).className, "text-"+c)
            document.getElementById(`button_group_Previous_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_button_text`).className, "text-"+c)
            
  
          }
          //button_group_button_single_text
        } else if (el.dataset.tag == "checkbox" || el.dataset.tag == "radio") {
          const objOptions = valj_efb.filter(obj => {
            return obj.parent === valj_efb[indx].id_
          })
          for (let obj of objOptions) {
            let optin = document.getElementById(`${obj.id_}_lab`);
            optin.className = colorTextChangerEfb(optin.className, "text-"+c)
          }
  
          //find list of options by id  from valueJson
          // change color of el by id finded of
        } else if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList' ) {
          const objOptions = valj_efb.filter(obj => {
            return obj.parent === valj_efb[indx].id_
          })
          for (let obj of objOptions) {
            try {
              let optin = document.querySelector(`[data-op="${obj.id_op}"]`);
              optin.className = colorTextChangerEfb(optin.className, "text-"+c)              
            } catch {
            }
          }
  
          //find list of options by id  from valueJson
          // change color of el by id finded of
        } else if (el.dataset.tag == "yesNo") {
          document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, "text-"+c)
          document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, "text-"+c)
  
        } 

        if(c.includes('colorDEfb')){
          switch (el.dataset.el) {
            case 'label':
              valj_efb[indx].style_label_color ?  valj_efb[indx].style_label_color=color : Object.assign(valj_efb[indx] , {style_label_color:color}) ;
              //console.log('costume color',valj_efb[indx])
              break;
            case 'description':
              valj_efb[indx].style_label_color ?  valj_efb[indx].style_message_text_color=color : Object.assign(valj_efb[indx] , {style_message_text_color:color}) ;
              //console.log('costume color',valj_efb[indx])
              break;
            case 'el':
              valj_efb[indx].el_text_color ?  valj_efb[indx].style_el_text_color=color : Object.assign(valj_efb[indx] , {style_el_text_color:color}) ;
              //console.log('costume color',valj_efb[indx])
              break;          
            case 'icon':
              valj_efb[indx].style_icon_color ?  valj_efb[indx].style_icon_color=color : Object.assign(valj_efb[indx] , {style_icon_color:color}) ;
              //console.log('costume color',valj_efb[indx])
              break;
          
            default:
              break;
          }
          addColorTolistEfb(color)
        }
        break;
      case "selectBorderColorEl":
        //console.log(el.value);
         color = el.value;
        c =switch_color_efb(color);
        
       //console.log(color, c ,el.dataset,indx)
       if(c.includes('colorDEfb')){
         addStyleColorBodyEfb(c,color,"border",indx);
       }
        postId = '_'
  
        if (el.dataset.tag == "dadfile") { postId = "_box" }
        else if (fun_el_select_in_efb(el.dataset.tag)) { postId = "_options" }
  
        setTimeout(() => {       
           const l =  document.getElementById(`${valj_efb[indx].id_}${postId}`);      
          l.className =colorBorderChangerEfb(l.className, `border-${c}`);
        }, 100)
        valj_efb[indx].el_border_color =`border-${c}`

        if(c.includes('colorDEfb')){
          valj_efb[indx].style_border_color ?  valj_efb[indx].style_border_color=color : Object.assign(valj_efb[indx] , {style_border_color:color}) ;
          addColorTolistEfb(color)
        }
        break;
      case "fontSizeEl":
        console.log(el.options[el.selectedIndex].value);
        valj_efb[indx].el_text_size = el.options[el.selectedIndex].value;
        id=`${valj_efb[indx].id_}_`;
        console.log(id)
        document.getElementById(id).className =headSizeEfb(document.getElementById(id).className,el.options[el.selectedIndex].value)
        break;
      case "selectHeightEl":
        el.dataset.tag == 'form' ? valj_efb[0].el_height = el.options[el.selectedIndex].value : valj_efb[indx].el_height = el.options[el.selectedIndex].value;
        let fsize = 'fs-6';
        if (valj_efb[indx].el_height == 'h-l-efb') { fsize = 'fs-5'; }
        else if (valj_efb[indx].el_height == 'h-xl-efb') { fsize = 'fs-4'; }
        else if (valj_efb[indx].el_height == 'h-xxl-efb') { fsize = 'fs-3'; }
        else if (valj_efb[indx].el_height == 'h-xxxl-efb') { fsize = 'fs-2'; }
        else if (valj_efb[indx].el_height == 'h-d-efb') { fsize = 'fs-6'; }
        if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList') {
          postId = `${valj_efb[indx].id_}_options`
        } else if (el.dataset.tag == "radio" || el.dataset.tag == "checkbox") {
          valj_efb[indx].label_text_size = fsize;
          const objOptions = valj_efb.filter(obj => { return obj.parent === valj_efb[indx].id_ })
          setTimeout(() => {
            for (let obj of objOptions) {             
              valj_efb[indx].el_text_size = fsize;
              let clslabel = document.getElementById(`${obj.id_}_lab`).className 
              clslabel = inputHeightChangerEfb(clslabel, el.options[el.selectedIndex].value)
              clslabel = inputHeightChangerEfb(clslabel, fsize)
              document.getElementById(obj.id_).className = inputHeightChangerEfb(document.getElementById(obj.id_).className, fsize)            
              //document.querySelector(`[data-id="${obj.dataId}"]`).className = fontSizeChangerEfb(document.querySelector(`[data-id='${obj.dataId}']`).className, )
            }
          }, objOptions.length * len_Valj);
          break;
  
        } else if (el.dataset.tag == "form") {
          postId = `btn_send_efb`;
          document.getElementById(`btn_send_efb`).className = inputHeightChangerEfb(document.getElementById(`btn_send_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`next_efb`).className = inputHeightChangerEfb(document.getElementById(`next_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`prev_efb`).className = inputHeightChangerEfb(document.getElementById(`prev_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Previous_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_Previous_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Next_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_Next_icon`).className, el.options[el.selectedIndex].value)
          break;
        } else if (el.dataset.tag == "maps") {
          postId = `${valj_efb[indx].id_}-map`;
        } else if (el.dataset.tag == "dadfile") {
          postId = `${valj_efb[indx].id_}_box`;
        } else if (el.dataset.tag == "multiselect" ||el.dataset.tag == "payMultiselect" ) {
          //h-xxl-efb
          postId = `${valj_efb[indx].id_}_options`;
          let msel = document.getElementById(postId);
          const iconDD = document.getElementById(`iconDD-${valj_efb[indx].id_}`)
          msel.className.match(/h-+\w+-efb/g) ? msel.className = inputHeightChangerEfb(msel.className, valj_efb[indx].el_height) : msel.classList.add(valj_efb[indx].el_height)
          iconDD.className.match(/h-+\w+-efb/g) ? iconDD.className = inputHeightChangerEfb(iconDD.className, valj_efb[indx].el_height) : iconDD.classList.add(valj_efb[indx].el_height)
          msel.className = fontSizeChangerEfb(msel.className, fsize)
          valj_efb[indx].el_text_size = fsize
        } else if (el.dataset.tag == "rating") {
          postId = valj_efb[indx].id_;
          setTimeout(() => {
            const newClass = inputHeightChangerEfb(document.getElementById(`${postId}_star1`).className, valj_efb[indx].el_height);
          
            document.getElementById(`${postId}_star1`).className = newClass;
            document.getElementById(`${postId}_star2`).className = newClass;
            document.getElementById(`${postId}_star3`).className = newClass;
            document.getElementById(`${postId}_star4`).className = newClass;
            document.getElementById(`${postId}_star5`).className = newClass;
          }, 10);
          break;
        } else if (el.dataset.tag == "switch") {
          postId = `${valj_efb[indx].id_}-switch`;
        } else if (el.dataset.tag == "yesNo") {
          setTimeout(() => {
            postId = `${valj_efb[indx].id_}_b_1`;
            document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
            postId = `${valj_efb[indx].id_}_b_2`;
            document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
          }, 10);
          break;
        } else if (el.dataset.tag == "link"){
          
          postId = `${valj_efb[indx].id_}_`
          
          document.getElementById(postId).className = fontSizeChangerEfb(document.getElementById(postId).className, fsize);
          console.log(fsize,postId , document.getElementById(postId));
        } else {
        
          postId = `${valj_efb[indx].id_}_`
        }
        setTimeout(() => {
          
          document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
        }, 10)
  
    
        break;
      case 'SingleTextEl':
        let iidd = ""
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
          // iidd = indx !=0 ? `${valj_efb[indx].id_}_icon` : `button_group_icon` ;
          iddd = indx != 0 ? `${valj_efb[indx].id_}_button_single_text` : 'button_group_button_single_text';
          valj_efb[indx].button_single_text = el.value ;
        } else {
          iidd = el.dataset.side == "Next" ? `button_group_Next_button_text` : `button_group_Previous_button_text`
          el.dataset.side == "Next" ? valj_efb[0].button_Next_text = el.value : valj_efb[0].button_Previous_text = el.value
        }        
        document.getElementById(iddd).innerHTML = el.value;
     
        break;
      case 'iconEl':
      
/*         let di = '';
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
          di = indx != 0 ? `${valj_efb[indx].id_}_icon` : `button_group_icon`;
          valj_efb[indx].icon = returnValueSelectedOfListEfb(el.dataset.id);
        } else {
          const i = returnValueSelectedOfListEfb(el.dataset.id);
          console.log(`i====================>${i}`);
          di = el.dataset.side == "Next" ? `button_group_Next_icon` : `button_group_Previous_icon`
          el.dataset.side == "Next" ? valj_efb[0].button_Next_icon = i : valj_efb[0].button_Previous_icon = i
        } 
        document.getElementById(`${di}`).className =`efb ${valj_efb[indx].icon} ${valj_efb[indx].icon_color}`
        */
     
      //  document.getElementById(`${di}`).className = `${el.value} mx-2`;
        break;
      case 'marksEl':
        valj_efb[indx].mark = parseInt(document.getElementById('marksEl').value);
    
        break;
      case 'letEl':
        const lat = parseFloat(el.value);
        const lon = parseFloat(document.getElementById('lonEl').value)
     
        map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
          center: { lat: lat, lng: lon },
          zoom: 8,
        })
        valj_efb[indx].lat = lat;
     
        break;
      case 'lonEl':
        const lonLoc = parseFloat(el.value);
        const letLoc = parseFloat(document.getElementById('letEl').value)
        map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
          center: { lat: letLoc, lng: lonLoc },
          zoom: 8,
        })
        valj_efb[indx].lng = lonLoc;
    
        break;
      case 'EditOption':        
       
        const iindx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
     
        if (iindx != -1) {
          console.log(1545,el.dataset.id ,iindx ,el.dataset.tag);
          valj_efb[iindx].value = el.value;
          if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList') {
          
            //Select
            document.querySelector(`[data-op="${el.dataset.id}"]`).innerHTML = el.value;
            document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
          }else if( el.dataset.tag != "multiselect" &&  el.dataset.tag != 'payMultiselect') {
            //radio || checkbox  
            console.log('radio || checkbox');
            console.log(document.querySelector(`[data-op="${el.dataset.id}"]`).value);
            console.log(document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML);
            
            document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
          }
          el.setAttribute('value', valj_efb[iindx].value);
          el.setAttribute('defaultValue', valj_efb[iindx].value);
        }
        break;
      case 'paymentOption': 
      console.log('paymentOption');     
        el.dataset.id;
        const ipndx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
     
        
        if (ipndx != -1) {
          valj_efb[ipndx].price = el.value;
          console.log( valj_efb[ipndx])
        }
        break;
      case "htmlCodeEl":
        
        const idhtml=`${el.dataset.id}_html`;
        postId = valj_efb.findIndex(x => x.id_ == el.dataset.id);
        if (el.value.length > 2) {
      
          document.getElementById(idhtml).innerHTML = el.value;
          document.getElementById(idhtml).classList.remove('sign-efb')
          valj_efb[postId].value = el.value.replace(/\r?\n|\r/g, " ");
          valj_efb[postId].value = valj_efb[postId].value.replace(/"/g,`@!`);
        
        } else {
  
          document.getElementById(idhtml).classList.add('sign-efb')
          document.getElementById(idhtml).innerHTML = `
            <div class="efb noCode-efb m-5 text-center" id="${el.dataset.id}_noCode">
            ${efb_var.text.noCodeAddedYet}  <button type="button" class="efb btn btn-edit btn-sm" id="settingElEFb" data-id="${el.dataset.id}-id" data-bs-toggle="tooltip" title="Edit" onclick="show_setting_window_efb('${el.dataset.id}-id')">
            <i class="efb bi-gear-fill text-success" id="efbSetting"></i></button> ${efb_var.text.andAddingHtmlCode}
            </div>`
          valj_efb[postId].value = '';
  
        }
  
        break;
        case "selectMultiSelectMaxEl":
          const vms= el.value=="" ? 2 :el.value
          valj_efb[indx].maxSelect = vms;
          document.getElementById(`${valj_efb[indx].id_}_options`).dataset.no = vms
          
          break;
        case "selectMultiSelectMinEl":
          const vmsn= el.value=="" ? 2 :el.value
          valj_efb[indx].minSelect = vmsn;
          document.getElementById(`${valj_efb[indx].id_}_options`).dataset.min = vmsn
         
          break;
    }

  },len_Valj*10)
}




function pro_show_efb(state) {

   let message = state;
  if(typeof state!="string") message = state == 1 ? efb_var.text.proUnlockMsg : `${efb_var.text.ifYouNeedCreateMoreThan2Steps} ${efb_var.text.proVersion}`;
  const body = `<div class="efb pro-version-efb-modal"><i class="efb bi-gem"></i></div>
  <h5 class="efb txt-center">${message}</h5>
  <div class="efb text-center"><button type="button" class="btn efb btn-primary efb-btn-lg mt-3 mb-3" onClick ="open_whiteStudio_efb('pro')">
    <i class="efb bi-gem mx-1"></i>
      ${efb_var.text.activateProVersion}
    </button>
  </div>`
  show_modal_efb(body, efb_var.text.proVersion, '', 'proBpx')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show()
}


const show_modal_efb = (body, title, icon, type) => {
 //console.log('show_modal_efb in ',type ,title,body.slice(1,50))
// const myModal =  new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
 //console.log(myModal);
  document.getElementById("settingModalEfb-title").innerHTML = title;
  document.getElementById("settingModalEfb-icon").className = icon + ` mx-2`;
  document.getElementById("settingModalEfb-body").innerHTML = body
  if (type == "settingBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.contains('modal-new-efb') ? '' : document.getElementById("settingModalEfb").classList.add('modal-new-efb')
  }
  else if (type == "deleteBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    if (!document.getElementById('modalConfirmBtnEfb')) document.getElementById('settingModalEfb-sections').innerHTML += `
    <div class="efb modal-footer" id="modal-footer-efb">
      <button type="button" class="efb btn btn-danger" data-bs-dismiss="modal" id="modalConfirmBtnEfb">
          ${efb_var.text.yes}
      </button> 
    </div>`
    //settingModalEfb-sections
  } else if (type == "saveBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if(!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  }else if (type == "saveLoadingBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if(!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
    document.getElementById('settingModalEfb-body').innerHTML=loading_messge_efb();
  }else if(type=="chart"){
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if(!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
  }
  // myModal.show()
}


function add_option_edit_pro_efb(parent,tag,len) {
  const p = calPLenEfb(len)
  len = len<50 ? 200 :(len+Math.log(len))*p
  const id_ob = Math.random().toString(36).substr(2, 9);
  optionElpush_efb(parent, efb_var.text.newOption, id_ob, id_ob,tag);    
  setTimeout(() => {
    add_new_option_efb(parent, id_ob, efb_var.text.newOption, id_ob, tag);
  }, len);
 
}

//delete element
function show_delete_window_efb(idset) {
  // این تابع المان را از صفحه پاک می کند
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  const is_step = document.getElementById(idset) ? document.getElementById(idset).classList.contains('stepNavEfb') : false;
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  if (is_step == false) {
    myModal.show();
    confirmBtn.dataset.id = document.querySelector(`[data-id="${idset}"]`).id;
    confirmBtn.addEventListener("click", (e) => {
      console.log(idset ,confirmBtn.dataset.id )
      document.getElementById(confirmBtn.dataset.id).remove();
      obj_delete_row(idset, false, confirmBtn.dataset.id);
      activeEl_efb = 0;
      myModal.hide()
    })
    //myModal.show();
  } else if (is_step) {
    const el = document.getElementById(idset);
    if (el.dataset.id != 1) {
      // اگر استپ اول نباشد باید حذف شود و ردیف های بعد از شماره شان عوض شود به آخرین

      myModal.show();
      confirmBtn.dataset.id = idset;
   
      confirmBtn.addEventListener("click", () => {

        activeEl_efb = 0;
        if (pro_efb == false) {
          step_el_efb = step_el_efb > 1 ? step_el_efb - 1 : 1;
        }

        valj_efb[0].steps = valj_efb[0].steps - 1
        obj_delete_row(idset, true)
        document.getElementById(confirmBtn.dataset.id).remove();
        myModal.hide()

      })


    }
  }

}

const obj_delete_row = (dataid, is_step) => {

  let step = 0
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1
  
  if (foundIndex != -1 && is_step == true) { step = valj_efb[foundIndex].step }
  if (foundIndex != -1) {
    if (valj_efb[foundIndex].type == "maps") {
      document.getElementById('maps').draggable = true;
      document.getElementById('maps_b').classList.remove('disabled')
    }else if(valj_efb[foundIndex].type == "stripe"){
      valj_efb[0].type="form";
      console.log(valj_efb[0]);
    } else if (fun_el_select_in_efb(valj_efb[foundIndex].type) || valj_efb[foundIndex].type == 'radio' || valj_efb[foundIndex].type == 'checkbox'  ) {
      obj_delete_options(valj_efb[foundIndex].id_)
      //  foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1
    } else if (valj_efb[foundIndex].type == 'email' && valj_efb[0].email_to == valj_efb[foundIndex].id_) {
      valj_efb[0].sendEmail = 0
      valj_efb[0].email_to = ''
    }
    
    valj_efb.splice(foundIndex, 1);
  }
  if (is_step == true) {
    for (let ob of valj_efb) {
      if (ob.step == step) ob.step = step - 1;
  
    }
  }
  obj_resort_row(step_el_efb);
}
const obj_delete_options = (parentId) => {
  while (valj_efb.findIndex(x => x.parent == parentId) != -1) {
    let indx = valj_efb.findIndex(x => x.parent == parentId);

    valj_efb.splice(indx, 1);
  }

}
const obj_delete_the_option = (id) => {
  //Just Delete the option with ID
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.id_op == id) : -1;
  if (foundIndex != -1) valj_efb.splice(foundIndex, 1);
}

function show_duplicate_fun(idset) {
  //از آبجکت خروجی بگیرد و بعد اینجا تولید کند
}

/* darggable new */
let enableDragSort = (listClass) => {
  const sortLists = document.getElementsByClassName(listClass);
  Array.prototype.map.call(sortLists, (lst) => { enableDragList(lst) });
}

let enableDragList = (lst) => {
  Array.prototype.map.call(lst.children, (item) => { enableDragItem(item) });
}

let enableDragItem = (item) => {
  // این تابع وقتی المان جدیدی اضافه می شود باید فراخوانی شود تا به حالت درگ و دروپ به آن اضافه شود
  if (!item.classList.contains('stepNavEfb')) {
    // if not step
    item.setAttribute('draggable', true)
    item.ondrag = handleDrag;
    item.ondragend = handleDrop;
  }
}
let status_drag_start = false ;
let handleDrag = (item) => {

  const selectedItem = item.target,
  lst = selectedItem.parentNode,
  x = event.clientX,
  y = event.clientY;

  let swapItem = document.elementFromPoint(x, y) === null ? selectedItem : document.elementFromPoint(x, y);
 
  if(status_drag_start==false){
    for(i of valj_efb){
      
      if(i.type!="option"  && i.type!="form" && selectedItem.id!=i.id_ && selectedItem.previousElementSibling.id !=i.id_ ) {
        document.getElementById(i.id_).classList.add("drophere")
      }
    }
    status_drag_start=true;
  }

  selectedItem.classList.add('drag-sort-active-efb');
  if (lst === swapItem.parentNode) {
    swapItem = swapItem !== selectedItem.nextSibling && swapItem.dataset == "steps" && swapItem.id != "1" ? swapItem : swapItem.nextSibling;
    if (lst.insertBefore(selectedItem, swapItem)) {
     

    }
  }
}

let handleDrop = (item) => {
  item.target.classList.remove('drag-sort-active-efb');
  sort_obj_el_efb_()
  if(status_drag_start==true){
    for(i of valj_efb){
      if(i.type!="option" && i.type!="form") 
      document.getElementById(i.id_).classList.remove("drophere")
    }
    status_drag_start=false;
  }
  
  // sort_obj_efb();
}






const sort_obj_efb = () => {

  const len = valj_efb.length;
  let p = calPLenEfb(len)
  setTimeout(() => {
    const valj_efb_ = valj_efb.sort((a, b) => (a.amount > b.amount) ? 1 : ((b.amount > a.amount) ? -1 : 0))

  }, ((len * (Math.log(len)) * p))
  );
}


/* darggable new */


const sort_obj_el_efb = () => {
  // این تابع  مرتبط سازی المان ها را بر عهده دارد و آی دی و قدم آن را بعد از هر تغییر در ترتیب توسط کاربر مرتبط می کند
  // باید بعد بجز المان ها برای آبجکت هم اینجا را  اضافه کنید
  let amount = 1;
  let step = 0;
  let state = false;
  //console.error('------', valj_efb.length)

  for (const el of document.querySelectorAll(".efbField")) {
   
    if (el.classList.contains('stepNavEfb')) {
      amount = 1;
      step = el.dataset.step;
    } else {
      if (step == 1) {

        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id) // این خط خطا دارد

        const lastIndx = (valj_efb.length) - 1;

        valj_efb[indx].step = valj_efb[lastIndx].step
        valj_efb[indx].amount = !valj_efb[lastIndx].amount ? 1 : (valj_efb[lastIndx].amount) + 1;

        //  el.remove();
        state = true;
      } else {
        el.dataset.amount = amount;
        el.dataset.step = step;
        amount = amount + 1;
        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)
       
        if (indx != -1) {
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
        }
      }
    }
    const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)

  }

  if (state) fub_shwBtns_efb();
}
const sort_obj_el_efb_ = () => {
  // این تابع  مرتبط سازی المان ها را بر عهده دارد و آی دی و قدم آن را بعد از هر تغییر در ترتیب توسط کاربر مرتبط می کند
  // باید بعد بجز المان ها برای آبجکت هم اینجا را  اضافه کنید
  let amount = 0;
  let step = 0;
  let state = false;
  let op_state = false;
  const len = valj_efb.length;
  for (const el of document.querySelectorAll(".efbField")) {

    amount += 1;

    let indx = valj_efb.findIndex(x => x.id_ === el.id)

    try {
      if (indx != -1) {

        if (el.classList.contains('stepNavEfb')) {
          //اگر استپ بود
          step = el.dataset.step;
          //el.dataset.amount=amount;
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;

        } else {

          // if not a step
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
          //el.dataset.step =step;
          //el.dataset.amount=amount;
        }
        if (op_state == false && (fun_el_select_in_efb(el.dataset.tag) || valj_efb[indx].type == "radio" || valj_efb[indx].type == "checkbox" ||valj_efb[indx].type == "payRadio" || valj_efb[indx].type == "payCheckbox" )) {

          op_state == true;
          valj_efb.filter(obj => { return obj.parent === valj_efb[indx].id_ }).forEach((value) => {
            amount += 1;
            value.amount = amount;
            value.step = step
          })
        }
      }

    } catch {

    }

  }

  if (len > 20) {
    //show loading message full screen
    sort_obj_efb()
    const p = calPLenEfb(len)
    wating_sort_complate_efb((len * (Math.log(len)) * p))
  } else {
    sort_obj_efb()
  }
  /* const valj_efb_ = valj_efb.sort((a,b) => (a.amount - b.amount))
  valj_efb=valj_efb_; */

  if (state) fub_shwBtns_efb();
}//enf fun obj


const add_new_option_efb = (parentsID, idin, value, id_ob, tag) => {
  let p = document.getElementById("optionListefb")
  let p_prime = p.cloneNode(true)
  const ftyp=tag.includes("pay") ? 'payment':'';
  const col = ftyp=="payment" ||  ftyp=="smart"  ?'col-md-7':'col-md-12'
  console.log(`form_type_emsFormBuilder:${form_type_emsFormBuilder}`);
  document.getElementById('optionListefb').innerHTML += `
  <div id="${id_ob}-v" class="col-md-12">
  <input type="text"  value='${value}' data-value="${value}" id="EditOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}"  class="efb ${col} text-muted mb-1 fs-7 border-d efb-rounded elEdit">
  ${ftyp=="payment" ? `<input type="number" placeholder="$"  value='' data-value="${value}" id="paymentOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}-payment"  class="efb col-md-3 text-muted mb-1 fs-7 border-d efb-rounded elEdit">` :''}
  <div class="efb ${ftyp=="payment" ||  ftyp=="smart" ?'pay':'' } btn-edit-holder" id="deleteOption" data-parent_id="${parentsID}">
    <button type="button" id="deleteOption" onClick="delete_option_efb('${idin}')"  data-parent="${parentsID}" data-tag="${tag}"  data-id="${idin}-id"  class="efb btn btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}" > 
        <i class="efb bi-x-lg text-danger"></i>
    </button>
   <button type="button" id="addOption" onClick="add_option_edit_pro_efb('${parentsID.trim()}','${tag.trim()}',${valj_efb.length})" data-parent="${parentsID}" data-tag="${tag}" data-id="${idin}-id"   class="efb btn btn-edit btn-sm elEdit " data-bs-toggle="tooltip"   title="${efb_var.text.add}" > 
        <i class="efb bi-plus-circle  text-success"></i>
    </button>
  </div>
  </div>`;
 if(tag!=="multiselect" && tag!=="payMultiselect") document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(idin, value, id_ob, tag, parentsID);

  for (let el of  document.getElementById(`${id_ob}-v`).querySelectorAll(`.elEdit`)) {
    //console.log(el);
    el.addEventListener("change", (e) => { change_el_edit_Efb(el); })
  }

  
}
const add_new_option_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  let op = `<!-- option --!> 2`
  if (fun_el_select_in_efb(tag)) {
    op = `<option value="${value}" id="${idin}" data-id="${idin}-id"  data-op="${idin}" class="${valj_efb[indxP].el_text_color} efb">${value}</option>`
  } else {
    op = `<div class="efb form-check" id="${id_ob}-v">
    <input class="efb form-check-input ${valj_efb[indxP].el_text_size}" type="${tag}" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
    <label class="efb ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height} hStyleOpEfb " id="${idin}_lab" for="${idin}">${value}</label>
    </div>`

  }
  
  return op;
}
const delete_option_efb = (id) => {
  //حذف آپشن ها مولتی سلکت و درایو
  document.getElementById(`${id}-v`).remove();
  if(document.getElementById(`${id}-v`))document.getElementById(`${id}-v`).remove();
  const indx = valj_efb.findIndex(x => x.id_op == id)
  if (indx != -1) { valj_efb.splice(indx, 1); }
}


//drag and drop form creator  (pure javascript)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
function create_dargAndDrop_el() {
  
  const dropZoneEFB = document.getElementById("dropZoneEFB");
  dropZoneEFB.addEventListener("dragover", (e) => {
    e.preventDefault();
  });
  for (const el of document.querySelectorAll(".draggable-efb")) {

    el.addEventListener("dragstart", (e) => {
      e.dataTransfer.setData("text/plain", el.id)
     
    });
 
  }
  dropZoneEFB.addEventListener("drop", (e) => {
    // Add new element to dropZoneEFB
    e.preventDefault();
    if (e.dataTransfer.getData("text/plain") !== "step" && e.dataTransfer.getData("text/plain") != null && e.dataTransfer.getData("text/plain") != "") {
      const rndm = Math.random().toString(36).substr(2, 9);
      const t = e.dataTransfer.getData("text/plain");
      //console.log(t);

      fun_efb_add_el(t);
    }



    enableDragSort('dropZoneEFB');
  }); // end drogZone

}


function addNewElement(elementId, rndm, editState, previewSate) {
  //editState == true when form is edit method
  let pos = [``, ``, ``, ``]
  const shwBtn = previewSate != true ? 'showBtns' : '';
  let indexVJ = editState != false ? valj_efb.findIndex(x => x.id_ == rndm) : 0;
  if (previewSate == true && elementId!="html" && elementId!="register" && elementId!="login" && elementId!="subscribe" && elementId!="survey") pos = get_position_col_el(valj_efb[indexVJ].dataId, false)
  amount_el_efb = editState == false ? amount_el_efb + 1 : valj_efb[indexVJ].amount;
  element_name = editState == false ? elementId : valj_efb[indexVJ].name;
  let optn = '<!-- options -->';
  step_el_efb >= 1 && editState == false && elementId == "steps" ? step_el_efb = step_el_efb + 1 : 0;
  if (editState != false && previewSate!=true) {
    
    step_el_efb = valj_efb[0].steps;
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    add_buttons_zone_efb(t, 'dropZoneEFB')
  }
  let pay = previewSate==true ? 'payefb' : '';
  newElement = ``;
 // console.log(valj_efb[indexVJ]);
  //for(let q in  valj_efb[indexVJ]){
   if(previewSate==false) Object.entries(valj_efb[indexVJ]).forEach(([key, val]) =>{fun_addStyle_costumize_efb(val.toString(),key,indexVJ);})
  if (step_el_efb == 1) {
    let state = false;

    if (editState == false) {
      state = true;

    }

    if (elementId != 'steps') {
      if (editState == false && valj_efb.length<2) {
        console.log(formName_Efb,efb_var.text[formName_Efb])
        valj_efb.push({
          id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
          id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
          label_text_size: 'fs-5', message_text_size: 'default', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
          el_text_color: 'text-labelEfb', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
        });

        indexVJ = valj_efb.length - 1;
        newElement = ` 
            <div class="efb row my-2  ${shwBtn} efbField stepNavEfb" data-step="${step_el_efb}" data-amount="${step_el_efb}" data-id="${step_el_efb}" id="${step_el_efb}" data-tag="steps">
            <h2 class="efb col-10 mx-2 my-0"><i class="efb ${valj_efb[indexVJ].icon} ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'}  ${valj_efb[indexVJ].icon_color}"
                    id="${step_el_efb}_icon"></i> <span id="${step_el_efb}_lab" class="efb  text-darkb  ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'} ">${valj_efb[indexVJ].name}</span></span></h2>
            <small id="${step_el_efb}-des" class="efb form-text ${valj_efb[indexVJ].message_text_color} border-bottom px-4   ">${valj_efb[indexVJ].message}</small>
            <div class="efb col-sm-10">
                <div class="efb btn-edit-holder d-none " id="btnSetting-${step_el_efb}">
                    <button type="button" class="efb btn  btn-edit  btn-sm BtnSideEfb" id="settingElEFb"
                        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
                        onclick="show_setting_window_efb('${step_el_efb}')">
                        <i class="efb bi-gear-fill text-success" id="efbSetting"></i>
                    </button>
                </div>
            </div>
        </div>
        `;
      }
      const t = valj_efb[0].steps == 1 ? 0 : 1;
      if(previewSate!=true) editState == false   ? add_buttons_zone_efb(0, 'dropZoneEFB') : add_buttons_zone_efb(t, 'dropZoneEFB')
      
      
    } else if (elementId == "steps" && step_el_efb == 1 && state == false && editState == false) {

      valj_efb.push({
        id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
        id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
        label_text_size: 'fs-5', message_text_size: 'default', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
        el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
      });
     // add_buttons_zone_efb(0, 'dropZoneEFB');

     editState == false && valj_efb.length>2 ? step_el_efb+=1 :0;
    }

    amount_el_efb += 1;

  }
  console.log(valj_efb);
  if (editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_efb == false && step_el_efb < 3) || pro_efb == true)) { sampleElpush_efb(rndm, elementId); }
  
  //const idd = editState==false && elementId=="steps" ? `${rndm}` : rndm
  let iVJ = editState == false ? valj_efb.length - 1 : valj_efb.findIndex(x => x.id_ == rndm);
  
  let dataTag = 'text'
  const desc = `<small id="${rndm}-des" class="efb form-text d-flex  fs-7 col-sm-12 efb ${previewSate == true && pos[1] == 'col-md-4' || valj_efb[iVJ].message_align != "justify-content-start" ? `` : `mx-4`}  ${valj_efb[iVJ].message_align}  ${valj_efb[iVJ].message_text_color} ${valj_efb[iVJ].message_text_size != "default" ? valj_efb[iVJ].message_text_size : ''} ">${valj_efb[iVJ].message} </small> <small id="${rndm}_-message" class="text-danger efb fs-7"></small>`;
  const label = ` <label for="${rndm}_" class="efb ${previewSate == true ? pos[2] : `col-md-3`} col-sm-12 efb col-form-label ${valj_efb[iVJ].label_text_color} ${valj_efb[iVJ].label_align} ${valj_efb[iVJ].label_text_size != "default" ? valj_efb[iVJ].label_text_size : ''} " id="${rndm}_labG""><span id="${rndm}_lab" class="efb ${valj_efb[iVJ].label_text_size}">${valj_efb[iVJ].name}</span><span class=" mx-1 text-danger" id="${rndm}_req">${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? '*' : ''}</span></label>`
  const rndm_1 = Math.random().toString(36).substr(2, 9);
  const op_3 = Math.random().toString(36).substr(2, 9);
  const op_4 = Math.random().toString(36).substr(2, 9);
  const op_5 = Math.random().toString(36).substr(2, 9);
  let ui = ''
  const vtype =(elementId=="payCheckbox" || elementId=="payRadio" ||elementId=="paySelect"  || elementId=="payMultiselect") ? elementId.slice(3).toLowerCase() : elementId ;
  switch (elementId) {
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
      
      const type = elementId =="firstName" || elementId =="lastName" ? 'text' : elementId;
      const classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12"  id='${rndm}-f'>
        <input type="${type}" class="efb input-efb px-2 mb-0 emsFormBuilder_v ${classes} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length>0 ? value=`"${valj_efb[iVJ].value}"` :''} ${previewSate != true ? 'disabled' : ''}>
        ${desc}`
      dataTag = elementId;
      //  if(editState==false) sampleElpush_efb(rndm, elementId);

      console.log(ui);
      break;
    case 'maps':
     
      ui = `
      ${label}
      <!-- ${rndm}-map -->
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 "  id='${rndm}-f'>      
      ${previewSate == true && valj_efb[iVJ].mark != 0 ? `<div id="floating-panel" class="efb"><input id="delete-markers_maps_efb-efb" class="efb btn btn-danger" type="button" value="${efb_var.text.deletemarkers}" /></div>` : '<!--notPreview-->'}
        <div id="${rndm}-map" data-type="maps" class="efb maps-efb emsFormBuilder_v ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} " data-id="${rndm}-el" data-name='maps'></div>
        ${desc}`
      dataTag = elementId;



      break;
    case 'file':
      ui = `
       ${label}
        <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12"  id='${rndm}-f'>        
          <input type="${elementId}" class="efb input-efb px-2 emsFormBuilder_v  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}    form-control efb efbField" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" placeholder="${elementId}" ${previewSate != true ? 'disabled' : ''}>
          ${desc}`
      dataTag = elementId;

      break;
    case "textarea":
      ui = `
                ${label}
                <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12"  id='${rndm}-f'>
                <textarea  id="${rndm}_"  placeholder="${valj_efb[iVJ].placeholder}"  class="efb px-2 input-efb emsFormBuilder_v form-control ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color}  efbField" data-vid='${rndm}' data-id="${rndm}-el"  value="${valj_efb[iVJ].value}" rows="5" ${previewSate != true ? 'disabled' : ''}></textarea>
                ${desc}
            `
      dataTag = "textarea";

      break;
    case 'dadfile':

      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 " id='${rndm}-f'>
      ${desc}
      <div class="efb mb-3" id="uploadFilePreEfb">
                    <label for="${rndm}_" class="efb form-label">
                        <div class="efb dadFile-efb   ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner}   ${valj_efb[iVJ].el_border_color}" id="${rndm}_box">
                          ${ui_dadfile_efb(iVJ, previewSate)}                            
                        </div>
                    </label>
                </div>`
      dataTag = elementId;

      break;
    case 'checkbox':
    case 'radio':
    case 'payCheckbox':
    case 'payRadio':
      // const rndm_a = Math.random().toString(36).substr(2, 9);
     
      dataTag = elementId;
      if(elementId=="radio" || elementId=="checkbox") pay="";
      if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        
        for (const i of optns_obj) {
          optn += `<div class="efb form-check " data-id="${i.id_}" id="${i.id_}-v">
          <input class="efb form-check-input emsFormBuilder_v ${pay}  ${valj_efb[iVJ].el_text_size} " data-type="${vtype}" data-vid='${rndm}' type="${vtype}" name="${i.parent}" value="${i.value}" id="${i.id_}" data-id="${i.id_}-id" data-op="${i.id_}" ${previewSate != true ? 'disabled' : ''}>
          <label class="efb  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${i.id_}_lab" for="${i.id_}">${i.value}</label>
          </div>`
        }//end for 

      } else {
        const op_1 = Math.random().toString(36).substr(2, 9);
        const op_2 = Math.random().toString(36).substr(2, 9);
        optn = `
       <div class="efb form-check" data-id="${op_1}" id="${op_1}-v">
       <input class="efb emsFormBuilder_v form-check-input ${pay} ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].parent}" value="${vtype}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'disabled' : ''}>
       <label class="efb  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${op_1}_lab">${efb_var.text.newOption} 1</label>
       </div>
       <div class="efb form-check" data-id="${op_2}" id="${op_2}-v">
           <input class="efb emsFormBuilder_v form-check-input ${pay}  ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].parent}" value="${vtype}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'disabled' : ''}>
           <label class="efb ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>
       </div>`
        optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, op_1, op_1);
        optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, op_2, op_2);
      }
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12"   data-id="${rndm}-el" id='${rndm}-f'>
        <div class="efb ${valj_efb[iVJ].classes} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} " id="${rndm}_options">
        ${optn}
        </div>
        <div class="efb mb-3">${desc}</div>`


      //
      break;
    case 'switch':
    //onClick="switchGetStateEfb("${rndm}") 
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12" id ="${rndm}-f">
      <div class="efb form-check form-switch ${valj_efb[iVJ].classes}  ${valj_efb[iVJ].el_height} mb-1" id="${rndm}-switch">
        <input class="efb emsFormBuilder_v efb-switch form-check-input efbField" type="checkbox" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''}>
      </div>
      <div class="efb mb-3">${desc}</div>
      `
      dataTag = elementId;

      //
      break;
    case 'esign':
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12" id ="${rndm}-f">
      <canvas class="efb sign-efb bg-white ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color} " data-code="${rndm}"  data-id="${rndm}-el" id="${rndm}_" width="600" height="200">
          ${efb_var.text.updateUrbrowser}
      </canvas>
     ${previewSate == true ? `<input type="hidden" data-type="esign" data-vid='${rndm}' class="efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">` : ``}
      <div class="efb mx-1">${desc}</div>
      <div class="efb mb-3"><button type="button" class="efb btn ${valj_efb[iVJ].corner} ${valj_efb[iVJ].button_color} efb-btn-lg mt-1" id="${rndm}_b" onClick="fun_clear_esign_efb('${rndm}')">  <i class="efb ${valj_efb[iVJ].icon} mx-2 ${valj_efb[iVJ].icon_color != "default" ? valj_efb[iVJ].icon_color : ''} " id="${rndm}_icon"></i><span id="${rndm}_button_single_text" class=" text-white efb ">${valj_efb[iVJ].button_single_text}</span></button></div>
        `
      dataTag = elementId;

      //
      break;
    case 'rating':
      // 
      ui = `
      ${label}
      <div class="efb col-md-10 col-sm-12" id ="${rndm}-f">
      <div class="efb star-efb d-flex justify-content-center ${valj_efb[iVJ].classes}"> 
                        <input type="radio" id="${rndm}-star5" data-vid='${rndm}' data-type="rating" class="efb"   data-star='star'  name="${rndm}-star-efb" value="5" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star5" for="${rndm}-star5"  ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',5)"` : ''} title="5stars" class="efb ${valj_efb[iVJ].el_height} star-efb">5 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star4" data-vid='${rndm}' data-type="rating" class="efb"  data-star='star' name="${rndm}-star-efb" value="4" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star4"  for="${rndm}-star4" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',4)"` : ''} title="4stars" class="efb ${valj_efb[iVJ].el_height} star-efb">4 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star3" data-vid='${rndm}' data-type="rating" class="efb"  data-star='star' name="${rndm}-star-efb" data-name="star" value="3"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star3" for="${rndm}-star3"  ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',3)"` : ''} title="3stars" class="efb ${valj_efb[iVJ].el_height} star-efb">3 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star2" data-vid='${rndm}' data-type="rating" class="efb"  data-star='star' data-name="star" name="${rndm}-star-efb" value="2"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star2" for="${rndm}-star2" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',2)"` : ''} title="2stars" class="efb ${valj_efb[iVJ].el_height} star-efb">2 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star1" data-vid='${rndm}' data-type="rating" class="efb" data-star='star' data-name="star" name="${rndm}-star-efb" value="1"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star1" for="${rndm}-star1" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',1)"` : ''} title="1star" class="efb  ${valj_efb[iVJ].el_height} star-efb">1 ${efb_var.text.star}</label>
      </div>  
      <input type="hidden" data-vid="${rndm}" data-type="rating" class="emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-stared" >   
      <div class="efb mb-3">${desc}</div>
        `
      dataTag = elementId;

      //
      break;
    case "steps":
      dataTag = 'step';
      let del = ``;

      if (step_el_efb > 1) {
        del = `
          <button type="button" class="efb btn btn-edit btn-sm" id="${valj_efb[iVJ].id_}"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.delete}"
          onclick="show_delete_window_efb('${valj_efb[iVJ].id_}')">
          <i class="efb bi-x-lg text-danger"></i>
          </button>`
      }
      if (step_el_efb <= 2 || (step_el_efb > 2 && pro_efb == true)) {
        valj_efb[0].steps =editState==false ?  step_el_efb :valj_efb[0].steps 
        newElement += ` 
        <div class="efb row my-2  ${shwBtn} efbField ${valj_efb[iVJ].classes} stepNavEfb" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}">
        <h2 class="efb col-md-10 col-sm-12 mx-2 my-0"><i class="efb ${valj_efb[iVJ].icon} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].icon_color} "
        id="${valj_efb[iVJ].id_}_icon"></i> <span id="${valj_efb[iVJ].id_}_lab" class="efb ${valj_efb[iVJ].label_text_size}  ${valj_efb[iVJ].label_text_color}  ">${valj_efb[iVJ].name}</span></span></h2>
        <small id="${valj_efb[iVJ].id_}-des" class="efb form-text ${valj_efb[iVJ].message_text_color} border-bottom px-4">${valj_efb[iVJ].message}</small>
       
        <div class="efb col-md-10 col-sm-12">
        <div class="efb btn-edit-holder d-none" id="btnSetting-${valj_efb[iVJ].id_}">
        <button type="button" class="efb btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"
        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
        onclick="show_setting_window_efb('${valj_efb[iVJ].id_}')">
        <i class="efb bi-gear-fill text-success BtnSideEfb" ></i>
        </button>
          ${del}
        </div>
        </div>
        </div>`
      } else {
        //اگر نسخه پرو نبود
        // کد زیر بهینه نیست و وقتی هر بار پیام نمایش داده می شود به دارپ زون اضافه می شود که نباید اینگونه باشد
        // باید یک تابع مخصوص نمایش آلرت نوشته شود و فرآخوانی شود هر وقت نیاز بود

        pro_show_efb(2)


      }
      break;
    case 'select':
    case 'paySelect':
      
      if(elementId=="select") pay="";
      if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="${valj_efb[indx_parent].el_text_color}  emsFormBuilder_v efb">${i.value}</option>`
        }//end for 

      } else {
        optn = `
        <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="text-dark efb  " >${efb_var.text.newOption} 1</option>
        <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="text-dark efb " >${efb_var.text.newOption} 2</option>
       `
        optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
        optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);
        // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
      }
      ui = `
      ${label}
      <div class="${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 efb ${valj_efb[iVJ].classes}"  id='${rndm}-f'  data-id="${rndm}-el" >
      <select class="form-select efb emsFormBuilder_v ${pay}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'disabled' : ''}>
      <option selected disabled>${efb_var.text.nothingSelected}</option>
      ${optn}
      </select>
      ${desc}
      `
      
      dataTag = elementId;



      break;

      case 'conturyList':

        if (editState != false) {
          // if edit mode
          const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
          const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
          for (const i of optns_obj) {
            
            optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb">${i.value}</option>`
          }//end for 
  
        } else {
          
          if(typeof countries_local !='object'){
            optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="text-dark efb" >${efb_var.text.newOption} 2</option>
           `
            optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
            optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);
            
          }else{
            countries_local.sort();
            let optn ='<!-- list of counries -->'
            for(let i=0 ; i<countries_local.length ; i++){   
              
              const op_id = countries_local[i].replace(" ", "_"); 
              optn += `<option value="${countries_local[i]} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_id}" data-op="${op_id}" class="text-dark efb" >${countries_local[i]}</option>`
              
              optionElpush_efb(rndm,countries_local[i], rndm_1, op_id);
              
             // i+=1;
            }

          }
          // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
        }
        ui = `
        ${label}
        <div class="${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 efb ${valj_efb[iVJ].classes}"  id='${rndm}-f'  data-id="${rndm}-el" >
        <select class="form-select efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'disabled' : ''}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
        dataTag = elementId;
  
  
  
        break;
      case 'stateProvince':

        if (editState != false) {
          // if edit mode
          const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
          const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
          for (const i of optns_obj) {
            optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb">${i.value}</option>`
          }//end for 
  
        } else {
          
          if(typeof state_local !='object'){
            optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="text-dark efb" >${efb_var.text.newOption} 2</option>
           `
           optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
           optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);
           
          }else{
            state_local.sort();
            let optn ='<!-- list of counries -->'
            for(let i=0 ; i<state_local.length ; i++){              
              
              optn += `<option value="${state_local[i]} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${state_local[i]}" data-op="${state_local[i]}" class="text-dark efb" >${state_local[i]}</option>`
              optionElpush_efb(rndm,state_local[i], rndm_1, state_local[i]);
            }

          }
          // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
        }
        ui = `
        ${label}
        <div class="${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 efb ${valj_efb[iVJ].classes}"  id='${rndm}-f'  data-id="${rndm}-el" >
        <select class="form-select efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'disabled' : ''}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
        dataTag = elementId;
  
  
  
        break;
    case 'multiselect':
    case 'payMultiselect':
      if(elementId=="select") pay="";
      dataTag = 'multiselect';


      if (editState != false) {
        // if edit mode
        optn = `<!--opt-->`;
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
   /*        optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" data-vid='${rndm}' 
          class="${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb">${i.value}</option>`
 */
          optn +=`<tr class="efblist efb ${valj_efb[indx_parent].el_text_color}  ${pay}" data-id="${rndm}" data-name="${i.value}" data-row="${i.id_}" data-state="0" data-visible="1">
          <th scope="row" class="bi-square efb"></th><td class="efb ms">${i.value}</td>
        </tr>  `

        }//end for 
        //optn += `</ul></div>`
      } else {
        optn = `
        <tr class="efblist  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.blue}" data-row="${op_3}" data-state="0" data-visible="1">
        <th scope="row" class="bi-square efb"></th><td class="efb ms">${efb_var.text.blue}</td>
        </tr>
      <tr class="efblist  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.Red}" data-row="${op_4}" data-state="0" data-visible="1">
        <th scope="row" class="bi-square efb"></th><td class="efb ms">${efb_var.text.Red}</td>                  
      </tr>
      <tr class="efblist  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.yellow}" data-row="${op_5}" data-state="0" data-visible="1">
        <th scope="row" class="bi-square efb"></th><td class="efb ms">${efb_var.text.yellow}</td>
      </tr>  
       `
       const id = `menu-${rndm}`;
       //console.log(rndm);
        optionElpush_efb(rndm, `${efb_var.text.blue}`, `${op_3}`, op_3);
        optionElpush_efb(rndm, `${efb_var.text.Red}`, `${op_4}`, op_4);
        optionElpush_efb(rndm, `${efb_var.text.yellow}`,`${op_5}`, op_5);

      }

      ui = ` 
      ${label}
      <!--multiselect-->
      
      <div class="efb ${valj_efb[iVJ].classes} ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 listSelect"   id='${rndm}-f' data-id="${rndm}-el" >


        <div class="efb efblist  mx-1  p-2 inplist ${pay}  ${previewSate != true ? 'disabled' : ''}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}" data-id="menu-${rndm}"   data-no="${valj_efb[iVJ].maxSelect}" data-min="${valj_efb[iVJ].minSelect}" data-parent="1" data-icon="1" data-select=""  data-vid='${rndm}' id="${rndm}_options" >${efb_var.text.selectOption}</div>
        <i class="efb efblist iconDD bi-caret-down-fill text-primary ${previewSate != true ? 'disabled' : ''} ${valj_efb[iVJ].el_height}" id="iconDD-${rndm}" data-id="menu-${rndm}"></i>
        <div class="efb efblist mx-1  listContent d-none rounded-bottom  bg-light" data-id="menu-${rndm}" data-list="menu-${rndm}">
        <table class="efb table menu-${rndm}">

         <thead class="efb efblist">
           <tr> <div class="efb searchSection efblist  p-2 bg-light"> <i class="efb efblist  searchIcon  bi-search text-primary "></i>
               <input type="text" class="efb efblist search searchBox my-1 col-12 rounded border-primary" data-id="menu-${rndm}" data-tag="search" placeholder="${efb_var.text.search}" onkeyup="FunSearchTableEfb('menu-${rndm}')"> </div>
         </tr> </thead>
         <tbody >
                  
          ${optn}
         </tbody>
       </table>
      </div>

     
        
      ${desc}
       `;
      dataTag = elementId;


      break;
    case 'html':
      dataTag = elementId;
      if (valj_efb[iVJ].value.length < 2) {
        ui = ` 
        <div class="col-sm-12 efb"  id='${rndm}-f' data-id="${rndm}-el" data-tag="htmlCode">            
            <div class="boxHtml-efb sign-efb efb" id="${rndm}_html">
            <div class="noCode-efb m-5 text-center efb" id="${rndm}_noCode">
              ${efb_var.text.noCodeAddedYet} <button type="button" class="BtnSideEfb btn efb btn-edit efb btn-sm" id="settingElEFb"
              data-id="${rndm}-id" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
              onclick="show_setting_window_efb('${rndm}-id')">
              <i class="efb bi-gear-fill text-success" id="efbSetting"></i></button>${efb_var.text.andAddingHtmlCode}
          </div></div></div> `;
      } else {
        ui = valj_efb[iVJ].value.replace(/@!/g,`"`) +  "<!--endhtml first -->";
        ui =`<div ${ previewSate==false ? `class="bg-light" id="${rndm}_html" `: ''}> ${ui} </div>`
      }
      break;
    case 'yesNo':
      dataTag = elementId;
      ui = `
      ${label}
      <div class="col-md-9 col-sm-12 efb ${valj_efb[iVJ].classes}"  id='${rndm}-f'>
      <div class="efb btn-group  btn-group-toggle w-100  col-md-12 col-sm-12  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" data-toggle="buttons" data-id="${rndm}-id" id="${rndm}_yn">    
      <label for="${rndm}_1" onClick="yesNoGetEFB('${valj_efb[iVJ].button_1_text}', '${rndm}')" class=" btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb left-efb ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_1">
        <input type="radio" name="${rndm}" data-type="switch" class="opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_1" value="${valj_efb[iVJ].button_1_text}"><span id="${rndm}_1_lab">${valj_efb[iVJ].button_1_text}</span></label>
      <span class="border-right border border-light efb"></span>
      <label for="${rndm}_2" onClick="yesNoGetEFB('${valj_efb[iVJ].button_2_text}' ,'${rndm}')" class=" btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb right-efb ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_2">
        <input type="radio" name="${rndm}" data-type="switch" class="opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_2" value="${valj_efb[iVJ].button_2_text}"> <span id="${rndm}_2_lab">${valj_efb[iVJ].button_2_text}</span></label>
      </div>
        ${desc}`
      break;
    case 'link':
      dataTag = elementId;
      ui = `
      <div class="efb col-md-12 col-sm-12"  id='${rndm}-f'>
      <a  id="${rndm}_"  class="efb px-2 btn underline emsFormBuilder_v ${previewSate != true ? 'disabled' : ''} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size} efbField" data-vid='${rndm}' data-id="${rndm}-el" href="${valj_efb[iVJ].href}">${valj_efb[iVJ].value}</a>
      </div>
      `
      break;
    case 'stripe':
      dataTag = elementId;
      ui = `
      <div class="efb col-md-12 col-sm-12 stripe"  id='${rndm}-f'>
      <div class="stripe-bg mx-2 p-3 card efb w-100">
      <div class="headpay efb border-b  row col-md-12">
        <div class="h3 col-md-8">${efb_var.text.payAmount}: </div> <div class="h3  col-md-4 d-flex justify-content-end" id="payPriceEfb"> <span  class="totalpayEfb d-flex justify-content-evenly mx-1">0</span> <span class="currencyPayEfb">${valj_efb[0].currency.toUpperCase()}</span></div>
      </div>
      <div id="stripeCardSectionEfb" class="efb">
        <div class="col-md-12 my-2">
        <label for="cardnoEfb" class="efb fs-8">${efb_var.text.cardNumber}: </label>
        <div id="cardnoEfb" class="efb"></div>
        </div>
        <div class="col-md-12 row my-2">
          <div class="col-md-6 my-2">     
          <label for="cardexpEfb" class="efb fs-8">${efb_var.text.cardExpiry}: </label>
          <div id="cardexpEfb" class="efb"></div>
          </div>
          <div class="col-md-6 my-2">
          <label for="cardcvcEfb" class="efb fs-8">${efb_var.text.cardCVC}: </label>
          <div id="cardcvcEfb" class="efb"></div>
          </div>
        </div>
      </div>
      <a class="btn my-2 efb p-2 efb-square h-l-efb  efb-btn-lg float-end disabled" id="btnStripeEfb">${efb_var.text.payNow}</a>
      <div class="efb bg-light border-d rounded-3 p-2 bg-muted" id="statusStripEfb" style="display: none"></div>
      </div>
      </div>
      </div>
      `
      valj_efb[0].type="payment";
      break;
    case 'heading':
      dataTag = elementId;
      ui = `
      <div class="efb col-md-12 col-sm-12"  id='${rndm}-f'>
      <p  id="${rndm}_"  class="efb px-2  emsFormBuilder_v  ${valj_efb[iVJ].classes}  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size}   efbField" data-vid='${rndm}' data-id="${rndm}-el" >${valj_efb[iVJ].value}</p>
      </div>
      `
      break;
    case 'booking':
      dataTag = elementId;
      break;



  }
  const addDeleteBtnState = (formName_Efb=="login" && ( valj_efb[iVJ].id_=="emaillogin" || valj_efb[iVJ].id_=="passwordlogin")) || (formName_Efb=="register" && (valj_efb[iVJ].id_=="usernameRegisterEFB" || valj_efb[iVJ].id_=="passwordRegisterEFB" || valj_efb[iVJ].id_=="emailRegisterEFB")) ? true : false;
  if (elementId != "form" && dataTag != "step" && ((previewSate == true && elementId != 'option') || previewSate != true)) {
    const pro_el = ( dataTag == "dadfile" || dataTag == "stripe" || dataTag == "switch" || dataTag == "rating" || dataTag == "esign" || dataTag == "maps"  || dataTag == "color" || dataTag == "html" || dataTag == "yesNo" || dataTag == "stateProvince" || dataTag == "conturyList") ? true : false;
    const contorl = ` <div class="btn-edit-holder d-none efb" id="btnSetting-${rndm}-id">
    <button type="button" class="efb btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"  data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.edit}" onclick="show_setting_window_efb('${rndm}-id')">
    <i class="efb bi-gear-fill text-success BtnSideEfb"></i>
    </button>
    <!--<button type="button" class="efb btn btn-edit btn-sm" id="dupElEFb" data-id="${rndm}-id"  data-bs-toggle="tooltip"  title="${efb_var.text.duplicate}" onclick="show_duplicate_fun('${rndm}-id')">
    <i class="efb bi-files text-warning"></i> -->
    </button>
    ${ addDeleteBtnState ? '': `<button type="button" class="efb btn btn-edit btn-sm" id="deleteElEFb"   data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.delete}" onclick="show_delete_window_efb('${rndm}-id')"> <i class="efb bi-x-lg text-danger"></i>`}

    `
    const proActiv = `⭐ 
    <div class="btn-edit-holder efb d-none zindex-10-efb " id="btnSetting-${rndm}-id">
    <button type="button" class="btn efb btn-pro-efb btn-sm px-2 mx-3" id="pro" data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.proVersion}" onclick="pro_show_efb(1)"> 
    <i class="efb bi-gem text-dark"> ${efb_var.text.availableProVersion}</i>`;

    ps = elementId=="html" ? 'col-md-12': 'col-md-12'
    endTags = previewSate==false ? `</button> </button> </div></div>` : `</div></div>`
    const tagId =elementId== "firstName" || elementId=="lastName" ? 'text' : elementId;
    newElement += `
    <div class="efb my-1  ${previewSate == true &&( pos[1]=="col-md-12" || pos[1]=="col-md-10") ? `mx-1` : ''} ${previewSate == true ? `${pos[0]} ${pos[1]}` : `${ps} row`} col-sm-12 ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >
    ${(previewSate == true && elementId != 'option') || previewSate != true ? ui : ''}
    
    ${previewSate != true && pro_efb == false && pro_el ? proActiv : ''}
    ${previewSate != true ? contorl : '<!--efb.app-->'}
    ${previewSate != true && pro_efb == false && pro_el ? '</div>' : ''}
    ${(previewSate == true && elementId != 'option' && elementId!="html") || previewSate != true ? endTags : '</div>'}
    ${ previewSate == true && elementId=="html" || true ?   "<!--endhtml -->" : ''}

    `
    console.log(previewSate != true && pro_efb == false && pro_el ? proActiv : 'Not pro');

  } else if (dataTag == 'step' && previewSate != true) {
    if (elementId == "steps" && pro_efb == false && step_el_efb == 3) {
      amount_el_efb = amount_el_efb - 1;
      step_el_efb = 2;
      valj_efb[0].steps = 2
    } else {
      valj_efb[0].steps = step_el_efb;
    }
   
    if (!document.getElementById('button_group')) {
       add_buttons_zone_efb(0, 'dropZoneEFB')
      fub_shwBtns_efb();
    } else if (valj_efb[0].steps > 1) {
      fun_handle_buttons_efb(true)
      fub_shwBtns_efb();
    }
  }

  return newElement;
}
//id,id_,value

/* new d&D */

const funSetPosElEfb = (dataId, position) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx != -1) {
    valj_efb[indx].label_position = position
  }

 if(valj_efb[indx].type!="heading"  && valj_efb[indx].type!="link" && valj_efb[indx].type!="html") get_position_col_el(dataId, true)

}
const funSetAlignElEfb = (dataId, align, element) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx == -1) { return }
  switch (element) {
    case 'label':
      document.getElementById(`${valj_efb[indx].id_}_labG`).className = alignChangerEfb(document.getElementById(`${valj_efb[indx].id_}_labG`).className, align)
      valj_efb[indx].label_align = align
      break;
    case 'description':
      const elm = document.getElementById(`${valj_efb[indx].id_}-des`)
      elm.className = alignChangerElEfb(elm.className, align)
      valj_efb[indx].message_align = align
      if (align != 'justify-content-start' && elm.classList.contains('mx-4') == true) { elm.classList.remove('mx-4') }
      else if (align == 'justify-content-start' && elm.classList.contains('mx-4') == false) {
        elm.classList.add('mx-4')
      }
      break;
  }
}//justify-content-center

const funSetCornerElEfb = (dataId, co) => {
  //efb-square
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el = document.querySelector(`[data-id='${dataId}-set']`)
  if (el.dataset.side == "undefined" || el.dataset.side == "") {
    valj_efb[indx].corner = co;
    postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
    let cornEl = document.getElementById(postId);   
    if (fun_el_select_in_efb(el.dataset.tag)) cornEl=document.getElementById(`${postId}options`) 
    if (el.dataset.tag == 'esign') cornEl = document.getElementById(`${valj_efb[indx].id_}_b`)
    else if (el.dataset.tag == 'dadfile') cornEl = document.getElementById(`${valj_efb[indx].id_}_box`)
    //console.log(cornEl);
    cornEl.className = cornerChangerEfb(cornEl.className, co)

  } else if (el.dataset.side == "yesNo") {
    valj_efb[indx].corner = co;
    document.getElementById(`${valj_efb[indx].id_}_b_1`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, co)
    document.getElementById(`${valj_efb[indx].id_}_b_2`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, co)
  } else {

    valj_efb[0].corner = co;
    postId = document.getElementById('btn_send_efb');
    postId.classList.toggle('efb-square')
    postId.className = cornerChangerEfb(postId.className, co)
    document.getElementById('next_efb').className = cornerChangerEfb(document.getElementById('next_efb').className, co)
    document.getElementById('prev_efb').className = cornerChangerEfb(document.getElementById('prev_efb').className, co)
  }
}

let get_position_col_el = (dataId, state) => {
  console.log(dataId, state);
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el_parent = document.getElementById(valj_efb[indx].id_);
  let el_label = document.getElementById(`${valj_efb[indx].id_}_labG`)
  let el_input = document.getElementById(`${valj_efb[indx].id_}-f`)
  let parent_col = ``;
  let label_col = `col-md-12`;
  let input_col = `col-md-12`;
  let parent_row = '';
  switch (valj_efb[indx].size) {
    case 100:
    case '100':
      parent_col = 'col-md-12';
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 80:
    case '80':
      parent_col = 'col-md-10'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 50:
    case '50':
      parent_col = 'col-md-6'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 33:
    case '33':
      parent_col = 'col-md-4'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
  }
  if (valj_efb[indx].label_position == "up") {
    label_col = `col-md-12`;
    input_col = `col-md-12`;
    if (state == true) {
      if (el_parent.classList.contains('row')) el_parent.classList.remove('row');
      if (!el_input.classList.contains('mx-2')) el_input.classList.add('mx-2');
      if (!el_label.classList.contains('mx-2')) el_label.classList.add('mx-2');
    }
  } else {
    parent_row = 'row';
    if (state == true) {
      //console.log(el_input);
      el_parent && el_parent.classList.contains('row') ? 0 : el_parent.classList.add('row')
      if (el_input.classList.contains('mx-2')) el_input.classList.remove('mx-2');
      if (el_label.classList.contains('mx-2')) el_label.classList.remove('mx-2');
    }
  }
  if (state == true) {
    el_parent.classList = colMdChangerEfb(el_parent.className, parent_col);
    el_input.classList = colMdChangerEfb(el_input.className, input_col);
    el_label.classList = colMdChangerEfb(el_label.className, label_col);
  }

  return [parent_row, parent_col, label_col, input_col]
}
const loadingShow_efb = (title) => {
  return `<div class="modal-dialog modal-dialog-centered efb"  id="settingModalEfb_" >
 <div class="modal-content efb " id="settingModalEfb-sections">
     <div class="modal-header efb">
         <h5 class="modal-title efb" ><i class="bi-ui-checks mx-2 efb" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${title ? title : efb_var.text.loading} </span></h5>
     </div>
     <div class="modal-body efb" id="settingModalEfb-body">
         ${loading_messge_efb()}
     </div>
 </div>
</div>`
}
let sampleElpush_efb = (rndm, elementId) => {
  //console.log(elementId);
  const testb =valj_efb.length;
  const label_align = efb_var.rtl == 1 ? 'txt-right' : 'txt-left'
  let pro = false;
  let size =100;
  let type=elementId;
  switch (elementId) {
    case "firstName":
    case "lastName":
      size=100;
      type="text";
      break;
  
    default:
      size=100;
      break;
  }
  if ( elementId == "dadfile"  || elementId == "switch" || elementId == "rating" || elementId == "esign" || elementId == "maps" 
  || elementId == "html" || elementId == "stateProvince" || elementId == "conturyList" || elementId == "payMultiselect" 
  || elementId == "paySelect" || elementId == "payRadio" || elementId == "payCheckbox"|| elementId == "heading" || elementId == "link") { pro = true }
  const txt_color = elementId != "yesNo" ? 'text-labelEfb' : "text-white"
  if (elementId != "file" && elementId != "dadfile" && elementId != "html" && elementId != "steps" && elementId !="heading" && elementId !="link") {  
    console.log(elementId , efb_var.text);
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type:type, placeholder: efb_var.text[elementId], value: '', size: size, message: efb_var.text.sampleDescription,
      id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb, corner: 'efb-square', label_text_size: 'fs-6',
      label_position: 'beside', message_text_size: 'default', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d',
      el_text_color: txt_color, message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: label_align, message_align: 'justify-content-start',
      el_align: 'justify-content-start', pro: pro ,icon_input:''
    })

    if (elementId == "esign") {

      Object.assign(valj_efb[(valj_efb.length) - 1], {
        icon: 'bi-save', icon_color: "default", button_single_text: efb_var.text.clear,
        button_color: 'btn-danger'
      })
      //icon: ''
    } else if (elementId == "yesNo") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { button_1_text: efb_var.text.yes, button_2_text: efb_var.text.no, button_color: 'btn-primary' })
    } else if (elementId == "maps") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { lat: 49.24803870604257, lng: -123.10512829684463, mark: 1 , zoom:7 });
      setTimeout(()=>{
        document.getElementById('maps').draggable = false;
       if(document.getElementById('maps_b')) document.getElementById('maps_b').classList.add('disabled')
      },valj_efb.length*5);
    }else if(elementId == "multiselect" || elementId == "payMultiselect"){
      //console.log(valj_efb.length)
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        maxSelect:2,
        minSelect:0
      })
    }

  } else if (elementId == "html") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, value: '',amount: amount_el_efb, step: step_el_efb, pro: pro
    })
  } else if (elementId == "heading") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, classes:'', value: efb_var.text[elementId],amount: amount_el_efb, step: step_el_efb, el_text_size: 'display-4',
      el_text_color: 'text-dark', el_align: 'justify-content-start', pro: pro
    })
  }else if (elementId == "link") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, classes:'', value: efb_var.text[elementId],amount: amount_el_efb, step: step_el_efb, el_text_size: 'fs-3',
      el_text_color: 'text-primary', el_align: 'justify-content-start',href:"https://whitestudio.team" , pro: pro
    })
  }else if (elementId == "steps") {
    step_el_efb = step_el_efb == 0 ? 1 : step_el_efb;  
    const stepName = efb_var.text[formName_Efb]!=undefined ?efb_var.text[formName_Efb].toUpperCase() :efb_var.text.step;
    valj_efb.push({
      id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: 'stepNavEfb',
      id: `${step_el_efb}`, name: stepName, icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
      label_text_size: 'fs-5', message_text_size: 'default', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
      el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
    });

  }else {
   
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, placeholder: elementId, value: 'document', size: 100,
      message: efb_var.text.sampleDescription, id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,
      corner: 'efb-square', label_text_size: 'fs-6', message_text_size: 'fs-7', el_text_size: 'fs-6', file: 'document',
      label_text_color: 'text-labelEfb', label_position: 'beside', el_text_color: 'text-dark', message_text_color: 'text-muted', el_height: 'h-d-efb',
      label_align: label_align, message_align: 'justify-content-start', el_border_color: 'border-d',
      el_align: 'justify-content-start', pro: pro
    })
    if (elementId == "dadfile") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { icon: 'bi-cloud-arrow-up-fill', icon_color: "text-pinkEfb", button_color: 'btn-primary' })
      //icon_color: 'default'
    } 


  }
}
let optionElpush_efb = (parent, value, rndm, op,tag) => {
  if(tag!=undefined && tag.includes("pay")){
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });
  }else{
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb,price:'0', amount: amount_el_efb });
  }
  //console.log(valj_efb)
}


function obj_resort_row(step) {
  // ترتیب را مرتب می کند بعد از پاک شدن یک استپ
  // const newStep = step - 1;
  for (v of valj_efb) {
    if (v.step == step) {
      v.step = step;
      if (v.dataId) {
        //document.querySelector(`[data-id="${v.dataId}"]`).dataset.step = step;
    
        if (document.getElementById(v.id_)) document.getElementById(v.id_).dataset.step = step;
      }
    }
  }
  /*   if(pro_efb==false) step_el_efb = step_el_efb-1;
    valj_efb[0].steps = valj_efb[0].steps-1; */
  fub_shwBtns_efb()
  if (valj_efb[0].steps == 1) fun_handle_buttons_efb(false);
}

let fun_handle_buttons_efb = (state) => {
  //d-none
  setTimeout(() => {

    if (state == true && document.getElementById('f_btn_send_efb').classList.contains('d-block')) {
      document.getElementById('f_btn_send_efb').classList.add('d-none');
      if (document.getElementById('f_button_form_np').classList.contains('d-none')) {
        document.getElementById('f_button_form_np').classList.remove('d-none');
        document.getElementById('f_button_form_np').classList.add('d-block');
      }
    } else if (state == false) {

      if (document.getElementById('f_button_form_np').classList.contains('d-block')) {
        document.getElementById('f_button_form_np').classList.remove('d-block');
      }
      document.getElementById('f_button_form_np').classList.add('d-none');
      if (document.getElementById('f_btn_send_efb').classList.contains('d-none')) {
        document.getElementById('f_btn_send_efb').classList.remove('d-none')
        document.getElementById('f_btn_send_efb').classList.add('d-block')
      }
    }
    valj_efb[0].button_state = state == true ? 'multi' : 'single';
  }, 50)
}

let add_buttons_zone_efb = (state, id) => {

console.log(`state==>{${state}}`)
  const stng = `  <div class="col-sm-10 efb">
  <div class=" BtnSideEfb btn-edit-holder d-none efb" id="btnSetting-button_group">
      <button type="button" class="btn efb btn-edit efb btn-sm" id="settingElEFb"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
          onclick="show_setting_window_efb('button_group')">
          <i class="efb bi-gear-fill text-success" id="efbSetting"></i>
      </button>
  </div>
  </div>`;
  const floatEnd = id == "dropZoneEFB" ? 'float-end' : ``;
  const btnPos = id != "dropZoneEFB" ? ' text-center' : ''
console.log(valj_efb[0])
  let dis
  if(preview_efb==true) {
    let t=valj_efb.findIndex(x=>x.type=="stripe");
    t= t!=-1 ? valj_efb[i].step : 0 ;
    valj_efb[0].type=="payment" &&  (valj_efb[0].steps==1  && t==1  )? 'disabled' :'';
  }
  const s = `
  <div class="d-flex justify-content-center ${state == 0 ? 'd-block' : 'd-none'} ${btnPos} efb" id="f_btn_send_efb" data-tag="buttonNav">
    <button id="btn_send_efb" type="button" class="btn efb p-2 ${dis} ${valj_efb[0].button_color}    ${valj_efb[0].corner} ${valj_efb[0].el_height}  efb-btn-lg ${floatEnd}"> ${valj_efb[0].icon.length>3 ? `<i class="efb ${valj_efb[0].icon}  mx-2 ${valj_efb[0].icon_color}   ${valj_efb[0].el_height}" id="button_group_icon"> </i>` :`` }<span id="button_group_button_single_text" class=" ${valj_efb[0].el_text_color} ">${valj_efb[0].button_single_text}</span</button>
  </div>`
  const d = `
  <div class="d-flex justify-content-center ${state == 1 ? 'd-block' : 'd-none'} ${btnPos} efb" id="f_button_form_np">
  <button id="prev_efb" type="button" class="btn efb p-2  ${valj_efb[0].button_color}    ${valj_efb[0].corner}   ${valj_efb[0].el_height}   efb-btn-lg ${floatEnd} m-1">${valj_efb[0].button_Previous_icon.length>2 ? `<i class="efb ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} ${valj_efb[0].el_height}" id="button_group_Previous_icon"></i>` :``} <span id="button_group_Previous_button_text" class=" ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Previous_text}</span></button>
  <button id="next_efb" type="button" class="btn efb ${dis} p-2 ${valj_efb[0].button_color}    ${valj_efb[0].corner}  ${valj_efb[0].el_height}    efb-btn-lg ${floatEnd} m-1"><span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color}  mx-2">${valj_efb[0].button_Next_text}</span> ${ valj_efb[0].button_Next_icon.length>3 ? ` <i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}  ${valj_efb[0].el_height}" id="button_group_Next_icon"></i>` :``}</button>
  </div>
  `
  let c = `<div class="footer-test mt-1 efb">`
  if (id != "dropZoneEFB") {
     c += state == 0 ? `${s}</div>` : `${d}</div> <!-- end btn -->`
  } else {
    c = ` <div class="col-12 mb-2 mt-3 efb bottom-0 ${valj_efb[0].captcha!=true ? 'd-none' :''} " id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>  <div class="bottom-0 " id="button_group_efb"> <div class=" row  showBtns efb" id="button_group" data-id="button_group" data-tag="buttonNav">${s} ${d} ${stng} </div></div>`
  }
  if (id != 'preview'  && id != 'body_efb' && !document.getElementById('button_group')) { document.getElementById(id).innerHTML +=c } else {
    return c;
  }
}



const colorTextChangerEfb = (classes, color) => {return classes.replace(/(text-primary|text-darkb|text-muted|text-secondary|text-pinkEfb|text-success|text-white|text-light|\btext-colorDEfb-+[\w\-]+|text-danger|text-warning|text-info|text-dark|text-labelEfb)/, `${color}`); }
const colorBtnChangerEfb = (classes, color) => { return classes.replace(/\bbtn+-+[\w\-]+/gi, `${color}`); }
const colorBorderChangerEfb = (classes, color) => { return classes.replace(/\bborder+-+[\w\-]+/gi, `${color}`); }
const colorBGrChangerEfb = (classes, color) => { return classes.replace(/\bbg+-+[\w\-]+/gi, `${color}`); }
const inputHeightChangerEfb = (classes, value) => { return classes.replace(/(h-d-efb|h-l-efb|h-xl-efb|h-xxl-efb|h-xxxl-efb)/, `${value}`); }
const fontSizeChangerEfb = (classes, value) => { return classes.replace(/\bfs+-\d+/gi, `${value}`); }
const cornerChangerEfb = (classes, value) => { return classes.replace(/(efb-square|efb-rounded)/, `${value}`); }
const colChangerEfb = (classes, value) => { return classes.replace(/\bcol-\d+|\bcol-\w+-\d+/, `${value}`); }
const colMdChangerEfb = (classes, value) => { return classes.replace(/\bcol-md+-\d+/, `${value}`); }
const colMdRemoveEfb = (classes) => { return classes.replace(/\bcol-md+-\d+/gi, ``); }
const headSizeEfb = (classes,value) => { return classes.replace(/\bdisplay+-\d+/gi, `${value}`); }
const colSmChangerEfb = (classes, value) => { return classes.replace(/\bcol-sm+-\d+/, `${value}`); }
const iconChangerEfb = (classes, value) => { return classes.replace(/(\bbi-+[\w\-]+|bXXX)/g, `${value}`); }
const RemoveTextOColorEfb = (classes) => { return classes.replace('text-', ``); }
const alignChangerEfb = (classes, value) => { return classes.replace(/(txt-left|txt-right|txt-center)/, `${value}`); }
const alignChangerElEfb = (classes, value) => { return classes.replace(/(justify-content-start|justify-content-end|justify-content-center)/, `${value}`); }
const isNumericEfb=(value)=>  {return /^\d+$/.test(value);}

const open_whiteStudio_efb = (state) => {
  
  let link = `https://whitestudio.team/`
  switch (state) {
    case 'mapErorr':
      link = `https://github.com/hassantafreshi/easy-form-builder/wiki/How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder`
      // چگونه کی گوگل مپ اضافه کنیم
      break;
    case 'pro':
      link += `#proBox`
      break;
    case 'publishForm':
      link = `https://www.youtube.com/watch?v=XjBPQExEvPE`
      // چگونه فرم را  منتشر کنیم
      // how create and publish form
      break;
    case 'emptyStep':
      link = `https://github.com/hassantafreshi/easy-form-builder/wiki/How-to-Create-a-form-on-Easy-Form-Builder#empty-step-alert`
      // پیام استپ خالی چیست و چگونه برطرف شود
      break;
    case 'notInput':
      link += `?notInputExists`
      // پیام ورودی وجود ندارد چیست و چگونه برطرف شود
      break;
    case 'pickupByUser':
      link = `https://github.com/hassantafreshi/easy-form-builder/wiki/How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder#how-to-add-a-location-picker-when-creating-form`
      // pickup location by user
      // چگونگی تنظیم انتخاب لوکیشن توسط کاربر و تعدادش
      // در پنجره تنظیمات نقشه
      break;

  }
  window.open(link, "_blank")
}

const loading_messge_efb = () => {
  return ` <div class="card-body text-center efb"><div class="lds-hourglass efb"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3></div>`
}
let editFormEfb = () => {
  valueJson_ws_p=0; // set ajax to edit mode
  let dropZoneEFB = document.getElementById('dropZoneEFB');
  dropZoneEFB.innerHTML = loading_messge_efb();
  if (localStorage.getItem("valj_efb")) { valj_efb = JSON.parse(localStorage.getItem("valj_efb")); } // test code => replace from value
  const len = valj_efb.length | 10;


  setTimeout(() => {
    dropZoneEFB.innerHTML = "<!-- edit efb -->"
    for (let v in valj_efb) {

      try {
        if (valj_efb[v].type != "option") {
          const type = valj_efb[v].type == "step" ? "steps" : valj_efb[v].type;
          let el = addNewElement(type, valj_efb[v].id_, true, false);        
          dropZoneEFB.innerHTML += el;      
          console.log(valj_efb[v].type,'!!!!!!')   ;
          if (valj_efb[v].type != "form" && valj_efb[v].type != "step" && valj_efb[v].type != "html" && valj_efb[v].type != "register" && valj_efb[v].type != "login"  && valj_efb[v].type != "subscribe"  && valj_efb[v].type != "survey" && valj_efb[v].type != "payment" && valj_efb[v].type != "smartForm") funSetPosElEfb(valj_efb[v].dataId, valj_efb[v].label_position)

          if (type == 'maps') {
            setTimeout(() => {
              const lat = valj_efb[v].lat;
              const lon = valj_efb[v].lng;
              map = new google.maps.Map(document.getElementById(`${valj_efb[v].id_}-map`), {
                center: { lat: lat, lng: lon },
                zoom: 8,
              })
            }, (len * 10) + 10);
          } 
        }
      } catch (error) {
        console.error('Error', error);
      }
    }

    fub_shwBtns_efb()
    enableDragSort('dropZoneEFB');
  }, (len * 10));

 
}//editFormEfb end

const saveFormEfb = () => {
  

  let proState = true;
  let stepState = true;
  let body = ``;
  let btnText = ``;
  let btnFun = ``
  let message = ``;
  let state = false
  let title = efb_var.text.error;
  let icon = `bi-exclamation-triangle-fill`
  let box = `error`
  let btnIcon = `bi-question-lg`
  let returnState= false;
  const gateway = valj_efb.findIndex(x=>x.type=="stripe");
  setTimeout(()=>{
    
    
   
    //settingModalEfb-body
    const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
    show_modal_efb("", efb_var.text.save, "bi-check2-circle", "saveLoadingBox")
    console.log(valj_efb[0].type=="payment" &&  gateway==-1)
    let timeout =1000;
    check_show_box=()=>{
     
      setTimeout(() => {
        if(returnState==false){         
          check_show_box();
          timeout =500;
        }else{          
         show_modal_efb(body, title, icon, box)
        }
      }, timeout);
    }
    
  try {
    if (valj_efb.length < 3) {
      btnText = efb_var.text.help
      btnFun = `open_whiteStudio_efb('notInput')`
      message = efb_var.text.youDoNotAddAnyInput
      icon =""
      
    } else {
      
      if (pro_efb == false) { proState = valj_efb.findIndex(x => x.pro == true) != -1 ? false : true }
      for (let s = 1; s <= valj_efb[0].steps; s++) {
        const stp = valj_efb.findIndex(x => x.step == s && x.type != "step");       
        if (stp == -1) {
          stepState = false;
          break;
        }
      }
    }
    console.log("befor run");
    if (valj_efb.length > 2 && proState == true && stepState == true &&( (valj_efb[0].type=="payment" &&  gateway!=-1) || valj_efb[0].type!="payment")) {
      title = efb_var.text.save
      box = `saveBox`
      icon = `bi-check2-circle`
      state = true;
      localStorage.setItem('valj_efb', JSON.stringify(valj_efb));      
      localStorage.setItem("valueJson_ws_p",JSON.stringify(valj_efb))
      formName_Efb = valj_efb[0].formName.length > 1 ? valj_efb[0].formName :formName_Efb
      returnState =actionSendData_emsFormBuilder()
    } else if (proState == false) {
      btnText = efb_var.text.activateProVersion
      btnFun = `open_whiteStudio_efb('pro')`
      message = efb_var.text.youUseProElements
      title = efb_var.text.proVersion
      icon = 'bi-gem'
      btnIcon= icon;
      returnState=true;
 
    } else if (stepState == false) {

      btnText = efb_var.text.help
      btnFun = `open_whiteStudio_efb('emptyStep')`
      message = efb_var.text.itAppearedStepsEmpty
     
      returnState=true;
     
    } else if (valj_efb[0].type=="payment" &&  gateway==-1 ){
      console.log('payment not add');
      btnText = efb_var.text.help
      btnFun = `open_whiteStudio_efb('paymentform')`
      message = efb_var.text.addPaymentGetway;
      icon = 'bi-bug'
      returnState=true;
    }
    if (state == false) {
      
      btn = `<button type="button" class="btn efb btn-outline-pink efb-btn-lg mt-3 mb-3 text-capitalize" onClick ="${btnFun}">
      <i class="efb ${btnIcon} mx-2"></i> ${btnText} </button>`
      body = `
      <div class="pro-version-efb-modal efb"></div>
        <h5 class="efb txt-center text-darkb fs-6 text-capitalize">${message}</h5>
        <div class="efb text-center text-capitalize">
        ${btn}
        </div>
      `
      check_show_box();
    }
   
  
   
   
    myModal.show();
  } catch(error) {
    btnIcon ='bi-bug'
    body =`
    <div class="pro-version-efb-modal efb"></div>
    <h5 class="efb txt-center text-darkb fs-6 text-capitalize">${efb_var.text.pleaseReporProblem}</h5>
    <div class="efb text-center text-capitalize">
    <button type="button" class="btn efb btn-outline-pink efb-btn-lg mt-3 mb-3 text-capitalize" onClick ="fun_report_error('fun_saveFormEfb','${error}')">
      <i class="efb bi-megaphone mx-2"></i> ${efb_var.text.reportProblem} </button>
    </div>
    `
    show_modal_efb(body,efb_var.text.error, btnIcon, 'error')
    myModal.show();
  }
  },100)
}//end function



async function previewFormMobileEfb() {
  // preview of form

  const frame = `
  <div class="smartphone-efb">
  <div class="content efb" >
      <div id="parentMobileView-efb">
      <div class="text-center pt-5"> 
      <div class="lds-hourglass efb"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3>
      <h3 class="efb text-darkb">${efb_var.text.efb}</h3>
      </div>
      </div>
   
  </div>
  
</div> `
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  show_modal_efb(frame, efb_var.text.mobilePreview, 'bi-phone', 'settingBox');
  myModal.show();

  let content = `<!--efb.app-->`
  let step_no = valj_efb[0].steps | 0;
  let head = ``
  let icons = ``
  let pro_bar = ``
  const len = valj_efb.length
  const p = calPLenEfb(len)
  if (len > 2) { localStorage.setItem('valj_efb', JSON.stringify(valj_efb)) } else {
    document.getElementById('parentMobileView-efb').innerHTML = `
       <div class="text-center pt-5 text-darkb efb"> 
       <div class="bi-emoji-frown fs-4 efb"></div><p class="fs-5 efb">${efb_var.text.formNotFound}</p></div>
      </div>
  `
  }
  document.getElementById('dropZoneEFB').innerHTML = '';
  setTimeout(() => {
    const content = create_form_efb()
    ReadyElForViewEfb(content);

  }, (len * (Math.log(len)) * p));




}

function create_form_efb() {
  let content = `<!--efb.app-->`
  let step_no = 0;
  let head = ``
  const len = valj_efb.length;
  const p = calPLenEfb(len)
  try {
    valj_efb.forEach((value, index) => {
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb fs-7 ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="efb mt-1 mb-2 steps-efb row">` : `<!-- fieldsetFOrm!!! --></fieldset><fieldset data-step="step-${step_no}-efb"  class="my-2 steps-efb efb row d-none">`

        if (valj_efb[0].show_icon == false) { }
        
      }
      if (value.type == 'step') {
        steps_index_efb.push(index)
        //steps_index_efb.length<2 ? content =`<div data-step="${step_no}" class="m-2 content-efb row">` : content +=`</div><div data-step="${step_no}"  class="m-2 content-efb row">` 
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option') {
        // content+='<div class="mb-3">'
        content += addNewElement(value.type, value.id_, true, true);
        //  content+=`<div id="${value.id_}_fb" class="m-2"></div></div>`

      }
    })
    step_no += 1;
    content += `
                ${sitekye_emsFormBuilder.length>1 &&  valj_efb[0].captcha==true ? `<div class="row mx-3"><div id="gRecaptcha" class="g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}"></div><small class="text-danger" id="recaptcha-message"></small></div>` : ``}
                <!-- fieldset formNew 1 --> </fieldset> 
    
                <fieldset data-step="step-${step_no}-efb" class="my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
                ${loading_messge_efb()}
                <!-- fieldset formNew 2 --> </fieldset>
      `
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb fs-7 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch(error){
    console.error(`Preview of Pc Form has an Error`,error)
  }

  if (content.length > 10) content += `</div>`
  head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="progress mx-4"><div class="efb progress-bar-efb  btn-${RemoveTextOColorEfb(valj_efb[1].label_text_color)} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div> <br> ` : ``}`



 // if (document.getElementById(`settingModalEfb_`)) document.getElementById(`settingModalEfb_`).classList.add('pre-efb')
  content = `  
    <div class="px-0 pt-2 pb-0 my-1 col-12" id="view-efb">
    <h4 id="title_efb" class="${valj_efb[1].label_text_color} mt-3 mb-0 text-center efb">${valj_efb[1].name}</h4>
    <p id="desc_efb" class="${valj_efb[1].message_text_color} fs-7 mb-2 text-center efb">${valj_efb[1].message}</p>
    
     <form id="efbform"> ${head} <div class="mt-1 px-2">${content}</div> </form>
    </div>
    `
  return content
}// end function


function fun_renderform_Efb() {
  try {
    valj_efb.forEach((v, i) => {
      switch (v.type) {
        case "maps":
          initMap();
          break;
        case "esign":
          c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
          c2d_contex_efb.lineWidth = 5;
          c2d_contex_efb.strokeStyle = "#000000";

          document.getElementById(`${v.id_}_`).addEventListener("mousedown", (e) => {
            draw_mouse_efb = true;
            c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
            canvas_id_efb = v.id_;
            lastMousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e);            
          }, false);

          document.getElementById(`${v.id_}_`).addEventListener("mouseup", (e) => {
            draw_mouse_efb = false;
            const value = document.getElementById(`${v.id_}-sig-data`).value;
          }, false);

          document.getElementById(`${v.id_}_`).addEventListener("mousemove", (e) => { mousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e); }, false);

          // touch event support for mobile
          document.getElementById(`${v.id_}_`).addEventListener("touchmove", (e) => {
            // canvas_id_efb = el.dataset.code;
            let touch = e.touches[0];
            let ms = new MouseEvent("mousemove", { clientY: touch.clientY, clientX: touch.clientX });
            document.getElementById(`${v.id_}_`).dispatchEvent(ms);
          }, false);

          document.getElementById(`${v.id_}_`).addEventListener("touchstart", (e) => {
            canvas_id_efb = v.id_;
            c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
            mousePostion_efb = getTouchPos_efb(document.getElementById(`${v.id_}_`), e);
            let touch = e.touches[0];
            let ms = new MouseEvent("mousedown", {
              clientY: touch.clientY,
              clientX: touch.clientX
            });
            document.getElementById(`${v.id_}_`).dispatchEvent(ms);
          }, false);

          document.getElementById(`${v.id_}_`).addEventListener("touchend", (e) => {
            let ms = new MouseEvent("mouseup", {});
            document.getElementById(`${v.id_}_`).dispatchEvent(ms);
            const value = document.getElementById(`${v.id_}-sig-data`).value;
          }, false);

          (function drawLoop() {
            requestAnimFrame(drawLoop);
            renderCanvas_efb(v.id_);
          })();
          break;
        case "multiselect":
        case "payMultiselect":
          jQuery(function () {
            jQuery('.selectpicker').selectpicker();
          });
          setTimeout(() => {
            //const v = valj_efb.find(x=>x.id_==rndm);
            const opd = document.querySelector(`[data-id='${v.id_}_options']`)
            opd.className += ` efb ${v.corner} ${v.el_border_color} ${v.el_text_size} ${v.el_height}`
          }, 350);
          // document.querySelector(`[data-id='${v.id_}_options']`).className += `efb ${v.corner} ${v.el_border_color} ${v.el_text_size}`
          break;
        case "rating":
          /*    const rate_efbs = document.querySelector(` [data-id='${v.id_}-el']`)
             for(let rate_efb of rate_efbs){
             (rate_efb.value, v ,rate_efb);
               rate_efb.addEventListener("click", (e)=> {
                   (rate_efb.value, v.id_ ,rate_efb);
                     document.getElementById(`${v.id_}-stared`).innerHTML = rate_efb.value;
                 })
             }  */
          break;
        case "dadfile":
          set_dadfile_fun_efb(v.id_, i)
          break;

      }
    })

  } catch {
    console.error(`Preview of Pc Form has an Error`)
  } finally {
    handle_navbtn_efb(valj_efb[0].steps)
  }//end try
  return true;
}//end function
function copyCodeEfb(id) {
  /* Get the text field */
  var copyText = document.getElementById(id);

  /* Select the text field */
  copyText.select();
  copyText.setSelectionRange(0, 99999); /* For mobile devices */

  /* Copy the text inside the text field */
  document.execCommand("copy");

  /* Alert the copied text */
  noti_message_efb(efb_var.text.copiedClipboard, '', 3.7)
  /*   document.getElementById('alert_efb').innerHTML = `<div class="efb alert alert-info alert-dismissible mt-5" role="alert">\n<strong>${efb_var.text.copiedClipboard}</strong>
      <button type="button" class="efb btn-close" data-dismiss="alert" aria-label="Close"></button>
      </div>`;
  
    setTimeout(function () {
      jQuery('.alert').hide();
    }, 3400); */
}

function setAtrOfElefb(id, text, color, time) {

  document.getElementById(id).innerHTML += `<div class="efb alert ${color} alert-dismissible mt-5" role="alert">
      <strong>${text}</strong>
      <button type="button" class="efb btn-close" data-dismiss="alert" aria-label="Close"></button></div>`
  setTimeout(function () {
    jQuery('.alert').hide();
  }, time);
}

previewMobleFormEfb = () => {

}


jQuery(function(jQuery){
  jQuery("#settingModalEfb").on('hidden.bs.modal', function () 
  {
    jQuery('#regTitle').empty().append(loadingShow_efb());
    // document.getElementById(`settingModalEfb`).innerHTML=loadingShow_efb();
    if (jQuery('#settingModalEfb_').hasClass('save-efb')) {
      jQuery('#settingModalEfb_').removeClass('save-efb')
     

    }
    if (jQuery('#settingModalEfb_').hasClass('pre-efb')) {
      //document.getElementById('dropZoneEFB').innerHTML = editFormEfb()
      jQuery('#dropZoneEFB').empty().append(editFormEfb());
      jQuery('#settingModalEfb_').removeClass('pre-efb');

      //fub_shwBtns_efb()
    }else if (jQuery('#settingModalEfb_').hasClass('pre-form-efb')){
      jQuery('#settingModalEfb_').removeClass('pre-form-efb');
    }
    if (jQuery('#modal-footer-efb')) {
      jQuery('#modal-footer-efb').remove()
    }

    var val = loading_messge_efb();
   if(jQuery(`#settingModalEfb-body`)) jQuery(`#settingModalEfb-body`).html(val)

  });
});




/* map section */

let map;
let markers_maps_efb = [];
let mark_maps_efb = []
//document.addEventListener('ondomready', function(){
function initMap() {

  setTimeout(function () {
    // code to be executed after 1 second
    //mrk ==0 show A point 
    // mrk ==-1 show all point inside  markers_maps_efb
    const idx = valj_efb.findIndex(x => x.type == "maps")
    // lat: 49.24803870604257, lon: -123.10512829684463
    const lat = idx != -1 && valj_efb[idx].lat ? valj_efb[idx].lat : 49.24803870604257;
    const lon = idx != -1 && valj_efb[idx].lng ? valj_efb[idx].lng : -123.10512829684463;
    const mark = idx != -1 ? valj_efb[idx].mark : 1;
    const zoom = idx != -1 && valj_efb[idx].zoom  && valj_efb[idx].zoom!="" ? valj_efb[idx].zoom  :10;
    const location = { lat: lat, lng: lon };

    map = new google.maps.Map(document.getElementById(`${valj_efb[idx].id_}-map`), {
      zoom: zoom,
      center: location,
      mapTypeId: "roadmap",
    });
  
    // This event listener will call addMarker() when the map is clicked.
    if (mark != 0 && mark != -1 ) {
      map.addListener("click", (event) => {
        const latlng = event.latLng.toJSON();
        if (mark_maps_efb.length < mark) {
          mark_maps_efb.push(latlng);
          addMarker(event.latLng);
        }
      });
      // add event listeners for the buttons


    if(document.getElementById("delete-markers_maps_efb-efb"))  document.getElementById("delete-markers_maps_efb-efb").addEventListener("click", deletemarkers_maps_efb_efb);
      // Adds a marker at the center of the map.
    }else if (mark == -1){
      const lab_map_efb = "0ABCDEFGHIJKLMNOPQRSTUVWXYZ";
      let nn=0;
      for(const mrk of marker_maps_efb){
        nn+=1;

        const lab = lab_map_efb[nn];
        const position = { lat: mrk.lat, lng: mrk.lng };
        const marker = new google.maps.Marker({
          position,
          label: lab,
          map,
        });
        markers_maps_efb.push(marker);
      }

    } else {
      addMarker(location);
    }
  }, 1000);
}

//});

// Adds a marker to the map and push to the array.

function addMarker(position) {
  const lab_map_efb = "0ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  const idx = valj_efb.findIndex(x => x.type == "maps")
  const idxm = (mark_maps_efb.length)
  const lab = idx !== -1 && valj_efb[idx].mark < 2 ? '' : lab_map_efb[idxm % lab_map_efb.length];
  //idx!==-1 && valj_efb[idx].mark ? '' :
  const marker = new google.maps.Marker({
    position,
    label: lab,
    map,
  });

  markers_maps_efb.push(marker);

  if(typeof(sendBack_emsFormBuilder_pub)!="undefined" ){
    //mark_maps_efb
    const vmaps = JSON.stringify(mark_maps_efb);
    const o = [{ id_:valj_efb[idx].id_, name:valj_efb[idx].name, amount:valj_efb[idx].amount, type: "maps", value: mark_maps_efb, session: sessionPub_emsFormBuilder }];
    fun_sendBack_emsFormBuilder(o[0])
  }

}

// Sets the map on all markers_maps_efb in the array.
function setMapOnAll(map) {
  for (let i = 0; i < markers_maps_efb.length; i++) {
    markers_maps_efb[i].setMap(map);
  }
}

// Removes the markers_maps_efb from the map, but keeps them in the array.
function hidemarkers_maps_efb() {
  setMapOnAll(null);
}

// Shows any markers_maps_efb currently in the array.
function showmarkers_maps_efb() {
  setMapOnAll(map);
}

// Deletes all markers_maps_efb in the array by removing references to them.
function deletemarkers_maps_efb_efb() {
  hidemarkers_maps_efb();
  markers_maps_efb = [];
  mark_maps_efb = [];

  if(typeof(sendBack_emsFormBuilder_pub)!="undefined" ){
    const indx = sendBack_emsFormBuilder_pub.findIndex(x=>x.type=="maps");
    if (indx!=-1){sendBack_emsFormBuilder_pub.splice(indx,1);}
  }
}
/* map section end */

function fun_get_rating_efb(v, no) {
  document.getElementById(`${v}-stared`).value = no;
  document.getElementById(`${v}-star${no}`).checked = true; 
  if(typeof(sendBack_emsFormBuilder_pub)!="undefined" ){
    const indx = valj_efb.findIndex(x=>x.id_==v)
    const o = [{ id_:v, name:valj_efb[indx].name, amount:valj_efb[indx].amount, type: "rating", value: no, session: sessionPub_emsFormBuilder }];
    fun_sendBack_emsFormBuilder(o[0])
  }
}

function switchGetStateEfb(id){

}

function yesNoGetEFB(v,id){
  if(typeof(sendBack_emsFormBuilder_pub)!="undefined" ){
    const indx = valj_efb.findIndex(x=>x.id_==id)
    const o = [{ id_:id, name:valj_efb[indx].name, amount:valj_efb[indx].amount, type: "yesNo", value: v, session: sessionPub_emsFormBuilder }];
    fun_sendBack_emsFormBuilder(o[0])
  }
}


/* clear esignature function */
function fun_clear_esign_efb(id) {
  const canvas = document.getElementById(`${id}_`);
  document.getElementById(`${id}-sig-data`).value = "Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=="
  const c2d = canvas.getContext("2d");
  c2d.clearRect(0, 0, canvas.width, canvas.height);
  var w = canvas.width;
  canvas.width = 1;
  canvas.width = w;
  c2d.lineWidth = 5;
  c2d.strokeStyle = "#000000";
  c2d.save();
  const o = [{ id_: id }];
  //remove  from object
  const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === id);
  if (indx!=-1)sendBack_emsFormBuilder_pub.splice(indx,1)

}

window.requestAnimFrame = ((callback) => {
  return window.requestAnimationFrame ||
    window.webkitRequestAnimationFrame ||
    window.mozRequestAnimationFrame ||
    window.oRequestAnimationFrame ||
    window.msRequestAnimaitonFrame ||
    function (callback) {
      window.setTimeout(callback, 1000 / 60);
    };
})();


function getmousePostion_efb(canvasDom, mouseEvent) {

  let rct = canvasDom.getBoundingClientRect();
  return {
    y: mouseEvent.clientY - rct.top,
    x: mouseEvent.clientX - rct.left
  }
}

function getTouchPos_efb(canvasDom, touchEvent) {
  let rct = canvasDom.getBoundingClientRect();
  return {
    y: touchEvent.touches[0].clientY - rct.top,
    x: touchEvent.touches[0].clientX - rct.left
  }
}

function renderCanvas_efb() {

  if (draw_mouse_efb) {

    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);
    c2d_contex_efb.lineTo(mousePostion_efb.x, mousePostion_efb.y);
    c2d_contex_efb.stroke();
    lastMousePostion_efb = mousePostion_efb;

    const data = document.getElementById(`${canvas_id_efb}_`).toDataURL();

    document.getElementById(`${canvas_id_efb}-sig-data`).value = data;
    // image.setAttribute("src", data);
  }
}



/* clear esignature function  end*/


set_dadfile_fun_efb = (id, indx) => {
  setTimeout(() => { create_dadfile_efb(id, indx) }, 50)
}


create_dadfile_efb = (id, indx) => {
  const dropAreaEfb = document.getElementById(`${id}_box`),
    dragTextEfb = dropAreaEfb.querySelector("h6"),
    dragbtntEfb = dropAreaEfb.querySelector("button"),
    dragInptEfb = dropAreaEfb.querySelector("input");
  dropAreaEfb.classList.remove("active");
  dragInptEfb.disabled = false;
  dragbtntEfb.onclick = () => {
    dragInptEfb.click();
  }

  dragInptEfb.addEventListener("change", function () {
    fileEfb = this.files[0];
    dropAreaEfb.classList.add("active");
    viewfileEfb(id, indx);
  });



  dropAreaEfb.addEventListener("dragover", (event) => {
    event.preventDefault();
    dropAreaEfb.classList.add("active");
    dragTextEfb.textContent = "Release to Upload File";
  });


  dropAreaEfb.addEventListener("dragleave", () => {
    let n = valj_efb[indx].file;
    n = efb_var.text[n];
    dragTextEfb.textContent = `${efb_var.text.dragAndDropA} ${n}`;
  });

  dropAreaEfb.addEventListener("drop", (event) => {
    event.preventDefault();

    fileEfb = event.dataTransfer.files[0];
    viewfileEfb(id, indx);
  });


}


function removeFileEfb(id, indx) {
  fileEfb = "";
  //dropAreaEfb.classList.add("active");
  document.getElementById(`${id}_box`).innerHTML = ui_dadfile_efb(indx)
  
  setTimeout(() => {
    create_dadfile_efb(id, indx);
    document.getElementById(`${id}_`).addEventListener('change',()=>{
      valid_file_emsFormBuilder(id);
    }) 

  }, 500)

  if(typeof(sendBack_emsFormBuilder_pub)!="undefined"){
    let inx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ ==id);
      if(inx!=-1) {
        sendBack_emsFormBuilder_pub.splice(inx,1)
        inx = files_emsFormBuilder.findIndex(x=>x.id_ ==id)
        files_emsFormBuilder[inx].url ="";
      }
      else{
        inx = files_emsFormBuilder.findIndex(x=>x.id_ ==id)       
        if(inx!=-1) {
          files_emsFormBuilder[inx].url ="";
          setTimeout(() => {
            inx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ ==id);            
            if(inx!=-1) {sendBack_emsFormBuilder_pub.splice(inx,1)}
          }, 100);
        }
     }
  }
}//end function


function ui_dadfile_efb(indx, previewSate) {
  let n = valj_efb[indx].file;
  n = efb_var.text[n];
  //console.log(n,efb_var.text[n] )
  return `<div class="icon efb"><i class="efb fs-3 ${valj_efb[indx].icon} ${valj_efb[indx].icon_color}" id="${valj_efb[indx].id_}_icon"></i></div>
  <h6 id="${valj_efb[indx].id_}_txt" class="text-center m-1">${efb_var.text.dragAndDropA} ${n} </h6> <span>${efb_var.text.or}</span>
  <button type="button" class="efb btn ${valj_efb[indx].button_color} efb-btn-lg" id="${valj_efb[indx].id_}_b">
      <i class="efb bi-upload mx-2"></i>${efb_var.text.browseFile}
  </button>
 <input type="file" hidden="" data-type="dadfile" data-vid='${valj_efb[indx].id_}' data-ID='${valj_efb[indx].id_}' class="efb emsFormBuilder_v   ${valj_efb[indx].required == 1 || valj_efb[indx].required == true ? 'required' : ''}" id="${valj_efb[indx].id_}_" data-id="${valj_efb[indx].id_}-el" ${previewSate != true ? 'disabled' : ''}>`

}


function viewfileEfb(id, indx) {
  let fileType = fileEfb.type;
  const filename = fileEfb.name
  let icon = ``;
  switch (valj_efb[indx].file) {
    case 'document':
      icon = `bi-file-earmark-richtext`
      /*  validExtensions = ["application/pdf", "text", 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword',
       'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel',
       'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
       'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
       'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.text']; */
      break;
    case 'media':
      icon = `bi-file-earmark-play`
      //  validExtensions = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'video/mp4', 'video/webm', 'video/x-matroska', 'video/avi',];

      break;
    case 'zip':
      icon = `bi-file-earmark-zip`
      // validExtensions = ['application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip'];


      break;

  }
  let box_v = `<div class="efb">
  <button type="button" class="btn m-2 btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onClick="removeFileEfb('${id}',${indx})"
       aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.removeTheFile}"></button> 
       <div class="card efb">
        <i class="efb ico-file ${icon} ${valj_efb[indx].icon_color} text-center"></i>
        <span class="efb text-muted">${fileEfb.name}</span>
        </div>
  </div>`

  if (validExtensions_efb_fun(valj_efb[indx].file, fileType)) {
    let fileReader = new FileReader();
    fileReader.onload = () => {
      let fileURL = fileReader.result;
      const box = document.getElementById(`${id}_box`)
      if (valj_efb[indx].file == "image") {
        box.innerHTML = `<div class="efb">
            <button type="button" class="btn m-2 btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onClick="removeFileEfb('${id}',${indx})"
                 aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title=${efb_var.text.removeTheFile}"></button> 
            <img src="${fileURL}" alt="image">
            </div>`;

          //  files_emsFormBuilder.push({ id: valj_efb[indx].id_, value: "@file@", state: 0, url: "", type: "file", name: valj_efb[indx].name, session: sessionPub_emsFormBuilder });
      } else {
        box.innerHTML = box_v;
      }
    }
    fileReader.readAsDataURL(fileEfb);
    document.getElementById(`${id}_-message`).innerHTML="";
  } else {
    const m = `${ajax_object_efm.text.pleaseUploadA} ${ajax_object_efm.text[valj_efb[indx].file]}`;
    document.getElementById(`${id}_-message`).innerHTML=m;
    noti_message_efb('', m, 4 ,'danger')
    
    document.getElementById(`${id}_box`).innerHTML.classList.remove("active");
    //  dragTextEfb.textContent = "Drag & Drop to Upload a File";
    fileEfb =[];
  }
}


function validExtensions_efb_fun(type, fileType) {

  console.log(type , fileType);
  let validExtensions = ["image/jpeg", "image/jpg", "image/png", 'image/gif'];
  if (type == "document") {
    validExtensions = ["application/pdf", "text", 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel',
      'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
      'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.text'];
  }
  else if (type == "media") { validExtensions = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'video/mp4', 'video/webm', 'video/x-matroska', 'video/avi',]; }
  else if (type == "zip") { validExtensions = ['application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip']; }
  return validExtensions.includes(fileType);
}



function wating_sort_complate_efb(t) {
  if (t > 500) t = 500
  const body = loading_messge_efb()
  show_modal_efb(body, efb_var.text.editField, 'bi-ui-checks mx-2', 'settingBox')
  const el = document.getElementById("settingModalEfb");
  const myModal = new bootstrap.Modal(el, {});
  myModal.backdrop = 'static';
  myModal.show()
  setTimeout(() => { myModal.hide()}, t)
}




function handle_navbtn_efb(steps, device) {
  var next_s_efb, prev_s_efb; //fieldsets
  var opacity_efb;

  var steps_len_efb = (steps) + 1;
  current_s_efb=1
  setProgressBar_efb(current_s_efb,steps_len_efb);
  if (steps > 1) {

    if (current_s_efb == 1 ) { jQuery("#prev_efb").toggleClass("d-none"); }
    
    jQuery("#next_efb").click(function () {
      var cp = current_s_efb + 1
      var state = true
      if(preview_efb==false && fun_validation_efb()==false){state=false ; return false};
  
      setTimeout(function(){ 
        if(state=true){
      if (cp == steps_len_efb) {
        jQuery("#prev_efb").addClass("d-none");
        jQuery("#next_efb").addClass("d-none");
        //send to server after validation 778899
        send_data_efb();
      }
      
    
      var current_s = jQuery('[data-step="step-' + (current_s_efb) + '-efb"]');
      next_s_efb = current_s.next();
      var nxt = "" + (current_s_efb + 1) + "";
      jQuery('[data-step="icon-s-' + (nxt) + '-efb"]').addClass("active");
      jQuery('[data-step="step-' + (nxt) + '-efb"]').toggleClass("d-none");


      next_s_efb.show();
     
      current_s.animate({ opacity_efb: 0 }, {
        step: function (now) {
          // for making fielset appear animation
          opacity_efb = 1 - now;

          current_s.css({
            'display': 'none',
            'position': 'relative'
          });
          next_s_efb.css({ 'opacity_efb': opacity_efb });
        },
        duration: 500
      });
      current_s_efb += 1;
      setProgressBar_efb(current_s_efb,steps_len_efb);
     
      if (current_s_efb <= steps) {
        var val = valj_efb.find(x => x.step == nxt)
        jQuery("#title_efb").attr('class', val['label_text_color']);
        jQuery("#desc_efb").attr('class', val['message_text_color']);
        jQuery("#title_efb").text(val['name']);
        jQuery("#desc_efb").text(val['message']);
        jQuery("#title_efb").addClass('text-center efb mt-1');
        jQuery("#desc_efb").addClass('text-center efb fs-7');
        jQuery("#prev_efb").removeClass("d-none"); 
      }
     
     
        if (current_s_efb==(steps_len_efb-1)){            
          if(sitekye_emsFormBuilder && sitekye_emsFormBuilder.length>1 &&  valj_efb[0].captcha==true)jQuery("#next_efb").toggleClass('disabled');
          var val= `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${efb_var.text.send}</span><i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color} " id="button_group_Next_icon"></i>`
          jQuery("#next_efb").html(val);
         // `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color} " id="button_group_Next_icon"></i>`
        }

      }},200)
      document.getElementById('body_efb').scrollIntoView(true);
    });

    jQuery("#prev_efb").click(function () {
      var cs = current_s_efb;
      if (cs == 2) {
        var val= `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color} " id="button_group_Next_icon"></i>`
        jQuery("#next_efb").html(val);
        jQuery("#next_efb").toggleClass("d-none"); 
        
      }else if (cs==steps){
        var val= `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color} " id="button_group_Next_icon"></i>`
        jQuery("#next_efb").html(val);
        if(sitekye_emsFormBuilder.length>1 && valj_efb[0]==true )   jQuery("#next_efb").removeClass('disabled');
      }
      var current_s = jQuery('[data-step="step-' + (current_s_efb) + '-efb"]');
      prev_s_efb = current_s.prev();
      jQuery('[data-step="icon-s-' + (current_s_efb) + '-efb"]').removeClass("active");
      jQuery('[data-step="step-' + (current_s_efb) + '-efb"]').toggleClass("d-none");
      //bug here
      var s = "" + (current_s_efb - 1) + ""
      var val = valj_efb.find(x => x.step == s)
       jQuery("#title_efb").attr('class', val['label_text_color']);
        jQuery("#desc_efb").attr('class', val['message_text_color']);
        jQuery("#title_efb").text(val['name']);
        jQuery("#desc_efb").text(val['message']);
        jQuery("#title_efb").addClass('text-center efb mt-1');
        jQuery("#desc_efb").addClass('text-center efb fs-7');
      prev_s_efb.show();

      current_s.animate({ opacity_efb: 0 }, {
        step: function (now) {
          opacity_efb = 1 - now;

          current_s.css({
            'display': 'none',
            'position': 'relative'
          });
          prev_s_efb.css({ 'opacity_efb': opacity_efb });
        },
        duration: 500
      });
      current_s_efb = current_s_efb-1;
      setProgressBar_efb(current_s_efb,steps_len_efb);
      if (current_s_efb == 1) { 
        jQuery("#prev_efb").toggleClass("d-none"); 
        jQuery("#next_efb").toggleClass("d-none"); 
    }
     // if(verifyCaptcha_efb.length>1  ) jQuery("#next_efb").removeClass("disabled");

     document.getElementById('body_efb').scrollIntoView(true);
    });


 
  } else {
    //One Step section


    jQuery("#btn_send_efb").click(function () {
      var state = true
      if( preview_efb==false && fun_validation_efb()==false){state=false ; return false};
  
      setTimeout(function(){ 
        if(state=true){
          jQuery('[data-step="icon-s-' + (current_s_efb + 1) + '-efb"]').addClass("active");
          jQuery('[data-step="step-' + (current_s_efb + 1) + '-efb"]').toggleClass("d-none");          
          jQuery("#btn_send_efb").toggleClass("d-none");
          var current_s = jQuery('[data-step="step-' + (current_s_efb) + '-efb"]');
          next_s_efb = current_s.next();
          next_s_efb.show();
          current_s.animate({ opacity_efb: 0 }, {
            step: function (now) {
              // for making fielset appear animation
              opacity_efb = 1 - now;
    
              current_s.css({
                'display': 'none',
                'position': 'relative'
              });
              next_s_efb.css({ 'opacity_efb': opacity_efb });
            },
            duration: 500
          });
          current_s_efb += 1;
          setProgressBar_efb(current_s_efb,steps_len_efb);
          send_data_efb();
          //send to server after validation
       
      }
      
      if(document.getElementById('body_efb'))document.getElementById('body_efb').scrollIntoView(true);
      }, 200);
    })
  }



  jQuery(".submit").click(function () {
    return false;
  })

};


function setProgressBar_efb(curStep,steps_len_efb) {
 
  var percent = parseFloat(100 / steps_len_efb) * curStep;
  percent = percent.toFixed();
  jQuery(".progress-bar-efb")
    .css("width", percent + "%")
}

function calPLenEfb(len) {
  let p = 2
  if (len <= 5) { p = 60 }
  else if (len > 5 && len <= 10) { p = 35 }
  else if (len > 10 && len <= 50) { p = 18 }
  else if (len > 50 && len <= 100) { p = 15 }
  else if (len > 100 && len <= 300) { p = 11 }
  else if (len > 300 && len <= 600) { p = 6 }
  else if (len > 600 && len <= 1000) { p = 3 }
  else { p = 2 }
  return p;
}


function ReadyElForViewEfb(content) {
  //here bug
  setTimeout(() => {
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    const btns = add_buttons_zone_efb(t, 'preview')

    const html = `
    <head>
    <script>
    valj_efb = ${JSON.stringify(valj_efb)};
    </script>   
    
 
  </head>
    <body>
    ${content}
    ${btns}
  
  
    
   
    handle_navbtn_efb(${valj_efb[0].steps},'mobile')
   

    </script>
    </body>
    `
    let iframe = document.createElement('iframe');
    iframe.id = `efbMobileView`;
    iframe.src = 'data:text/html;charset=utf-8,' + encodeURI(html);
    //iframe.src =`file:///home/hassan/Downloads/newUi/index%20-new.html`
    document.getElementById('parentMobileView-efb').innerHTML = "";
    document.getElementById('parentMobileView-efb').appendChild(iframe)
  }, 1000)

}



localStorage.getItem('count_view') ? localStorage.setItem(`count_view`, parseInt(localStorage.getItem('count_view')) + 1) : localStorage.setItem(`count_view`, 0)
if (localStorage.getItem('count_view')>=0 && localStorage.getItem('count_view') <3 && typeof efb_var =="object" && efb_var.maps!="1") {
  setTimeout(() => {  noti_message_efb(efb_var.text.warningBootStrap, "", 15 ,"danger") }, 100);
  setTimeout(() => {  noti_message_efb(efb_var.text.localizationM, "", 15 ,"info") }, 17000);
  setTimeout(() => {
    noti_message_efb(`🎉 ${efb_var.text.SpecialOffer}`, googleCloudOffer(), 15 ,"warning")
  }, 27000);
}

function googleCloudOffer() {

  return `<p>${efb_var.text.offerGoogleCloud} <a href="https://gcpsignup.page.link/8cwn" target="blank">${efb_var.text.getOfferTextlink}</a> </p> `
}


function alert_message_efb(title, message) {
  return `
  <div class="alert alert-info alert-dismissible fade show" role="alert">
  <strong>${title}</strong> ${message}
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
</div>
  `
}
function noti_message_efb(title, message, sec ,alert) {
  sec = sec * 1000
  /* Alert the copied text */
  alert = alert ? `alert-${alert}` : 'alert-info';
  document.getElementById('alert_efb').innerHTML = ` <div id="alert_content_efb" class="efb alert ${alert} alert-dismissible  mx-5 ${efb_var.text.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <h4 class="alert-heading">${title}</h4>
    <p>${message}</p>
    <button type="button" class="btn-close" data-dismiss="alert" aria-label="Close"></button>
  </div>`
  setTimeout(function () {
    jQuery('.alert_efb').hide();
    document.getElementById('alert_efb').innerHTML = ""
  }, sec);

  window.scrollTo({ top: 0, behavior: 'smooth' });
  jQuery('.alert').alert()
}


function gm_authFailure() {
  const body =`<p class="fs-6 efb">${efb_var.text.aPIkeyGoogleMapsFeild} <a href="https://developers.google.com/maps/documentation/javascript/error-messages" target="blank">${efb_var.text.clickHere}</a> </p>`
  noti_message_efb(efb_var.text.error, body, 120 ,'danger')

}

function funTnxEfb(val,title,message){

  const done = valj_efb[0].thank_you_message.done || efb_var.text.done
  const thankYou =valj_efb[0].thank_you_message.thankYou || efb_var.text.thanksFillingOutform
  const t = title  ? title :done ;
  const m = message ? message : thankYou;
  const trckCd = `
  <div class="efb"><h5 class="mt-3 efb">${valj_efb[0].thank_you_message.trackingCode || efb_var.text.trackingCode}: <strong>${val}</strong></h5>
               <input type="text" class="hide-input efb " value="${val}" id="trackingCodeEfb">
               <div id="alert"></div>
               <button type="button" class="btn efb btn-primary efb-btn-lg my-3" onclick="copyCodeEfb('trackingCodeEfb')">
                   <i class="efb bi-clipboard-check mx-1"></i>${efb_var.text.copy}
               </button></div>`
  return `
                      <h4 class="efb my-1">
                        <i class="efb bi-hand-thumbs-up title-icon mx-2"></i>${t}
                    </h4>
                    <h3 class="efb">${m}</h3>
                   ${valj_efb[0].trackingCode == true ? trckCd : '</br>'}
  
  `
}


function send_data_efb(){
  //if is preview 210201-SMHTH06 then recive from server and show
  if(state_efb!="run"){
    const cp = funTnxEfb('DemoCode-220201')
    document.getElementById('efb-final-step').innerHTML=cp
   // current_s_efb=1;
  }else{   
   endMessage_emsFormBuilder_view()
  }
}



function previewFormEfb(state){
  //v2
  //console.log('previewFormEfb')
    if(state!="run") {
      state_efb="view";
      preview_efb=true;
    }
  
  //state_efb
  let content = `<!--efb.app-->`
  let step_no = 0;
  let head = ``
  let icons = ``
  let pro_bar = ``
  //public key stripe
  const id = state == "run" ? 'body_efb' : 'settingModalEfb_';
  const len = valj_efb.length;
  const p = calPLenEfb(len)
  let timeout =  (len/2)*(Math.log(len)) * p;
  //console.log(len , p ,timeout)
  timeout<1700 ? timeout=1700 : 0;
 

  //  content = `<div data-step="${step_no}" class="m-2 content-efb 25 row">`
  //content =`<span class='efb row efb'>`
  if (state != "show" && state !="run") {
    if (valj_efb.length > 2) { localStorage.setItem('valj_efb', JSON.stringify(valj_efb)) } else {
      show_modal_efb(`<div class="text-center text-darkb efb"><div class="bi-emoji-frown fs-4 efb"></div><p class="fs-5 efb">${efb_var.text.formNotFound}</p></div>`, efb_var.text.previewForm, '', 'saveBox');
      const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
      myModal.show();
      return;
    }
    if(state =="pc"){
      show_modal_efb(loading_messge_efb(), efb_var.text.previewForm, '', 'saveBox')
      
      const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
      myModal.show();
    }
  }

  setTimeout(() => {
    try {
      valj_efb.forEach((value, index) => {
        console.log(valj_efb[index].type , valj_efb[index]);
        if(valj_efb[index].type!="html" && valj_efb[index].type!="link" && valj_efb[index].type!="heading") Object.entries(valj_efb[index]).forEach(([key, val]) =>{fun_addStyle_costumize_efb(val.toString(),key,index)});
        if (step_no < value.step && value.type == "step") {
          step_no += 1;
          head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb fs-7  ${value.label_text_color} ">${value.name}</strong></li>`
          content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="my-2  steps-efb efb row ">` : `<!-- fieldset!!! --> </fieldset><fieldset data-step="step-${step_no}-efb"  class="my-2 steps-efb efb row d-none">`

          if (valj_efb[0].show_icon == false) { }
          if(valj_efb[0].dShowBg && valj_efb[0].dShowBg==true && state=="run" ){ document.getElementById('body_efb').classList.remove('card')}
        }

        if (value.type == 'step' && value.type != 'html') {
          console.log(value.type);
          steps_index_efb.push(index)
          //steps_index_efb.length<2 ? content =`<div data-step="${step_no}" class="m-2 content-efb row">` : content +=`</div><div data-step="${step_no}"  class="m-2 content-efb row">` 
        } else if (value.type != 'step' && value.type != 'form' && value.type != 'option') {
          content += addNewElement(value.type, value.id_, true, true);        
          if(value.type=="html") content+="<!--testHTML-->"
        }       
      })

      step_no += 1;     
      content += `
           ${sitekye_emsFormBuilder.length>1? `<div class="row mx-3"><div id="gRecaptcha" class="g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" data-callback="verifyCaptcha"></div><small class="text-danger" id="recaptcha-message"></small></div>` : ``}
           <!-- fieldset1 --> 
           ${state_efb=="view" && valj_efb[0].captcha==true ? `<div class="col-12 mb-2 mt-3 efb" id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>` :''}
           </fieldset>
          <fieldset data-step="step-${step_no}-efb" class="my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
            ${loading_messge_efb()}                
            <!-- fieldset2 --></fieldset>`
      head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb fs-7 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
    } catch(error) {
      console.error(`Preview of Pc Form has an Error`,error)
    }





    if (content.length > 10) content += `</div>`
    head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="progress mx-5"><div class="efb progress-bar-efb  btn-${RemoveTextOColorEfb(valj_efb[1].label_text_color)} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div> <br> ` : ``}
    `
    const idn = state=="pre"? "pre-form-efb": "pre-efb";
    document.getElementById(id).classList.add(idn)
    content = `  
    <div class="px-0 pt-2 pb-0 my-1 col-12" id="view-efb">

    ${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<h4 id="title_efb" class="efb ${valj_efb[1].label_text_color} text-center mt-1">${valj_efb[1].name}</h4><p id="desc_efb" class="${valj_efb[1].message_text_color} text-center  fs-7 efb">${valj_efb[1].message}</p>` :`` }
    
     <form id="efbform"> ${head} <div class="mt-1 px-2">${content}</div> </form>
    </div>
    `

    const t = valj_efb[0].steps == 1 ? 0 : 1;
    if (state == 'pc') {
      document.getElementById('dropZoneEFB').innerHTML = '';
      content = `<!-- find xxxx -->`+content;
      show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
      add_buttons_zone_efb(t, 'settingModalEfb-body')
  
    }else if(state == 'pre'){
      //console.log('pre');
      show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
      add_buttons_zone_efb(t, 'settingModalEfb-body')
    } else if (state == "mobile") {
      const frame = `
        <div class="smartphone-efb">
        <div class="content efb" >
            <div id="parentMobileView-efb">
            <div class="lds-hourglass efb"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3>
            </div>
         
        </div>
        
      </div> `
      show_modal_efb(frame, efb_var.text.mobilePreview, 'bi-phone', 'settingBox');
      ReadyElForViewEfb(content)


    } else {
      console.log('public');
      //content is hold element and should added to a innerHTML
      document.getElementById(id).innerHTML=content;
      document.getElementById(id).innerHTML+=add_buttons_zone_efb(t, id);
      if(valj_efb[0].type=="payment"){
        console.log('payment');
       if(ajax_object_efm.paymentGateway=="stripe") fun_add_stripe_efb();
      }
    }




    // در اینجا ویژگی ها مربوط به نقشه و امضا و ستاره  و مولتی سلکت اضافه شود
    try {
      const len = valj_efb.length;
      valj_efb.forEach((v, i) => {
        switch (v.type) {
          case "maps":
            initMap();
            break;
          case "esign":
            c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
            c2d_contex_efb.lineWidth = 5;
            c2d_contex_efb.strokeStyle = "#000000";

            document.getElementById(`${v.id_}_`).addEventListener("mousedown", (e) => {
              draw_mouse_efb = true;
              c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
              canvas_id_efb = v.id_;
              lastMousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e);
            }, false);

            document.getElementById(`${v.id_}_`).addEventListener("mouseup", (e) => {
              draw_mouse_efb = false;

              // const ob = valueJson_ws.find(x => x.id_ === el.dataset.code);
              const el = document.getElementById(`${v.id_}-sig-data`);
              const value = el.value;
              const o = [{ id_: v.id_, name: v.name, amount:v.amount, type:v.type, value: value, session: sessionPub_emsFormBuilder }];
              fun_sendBack_emsFormBuilder(o[0]);
            }, false);

            document.getElementById(`${v.id_}_`).addEventListener("mousemove", (e) => { mousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e); }, false);

            // touch event support for mobile
            document.getElementById(`${v.id_}_`).addEventListener("touchmove", (e) => {
              // canvas_id_efb = el.dataset.code;
              let touch = e.touches[0];
              let ms = new MouseEvent("mousemove", { clientY: touch.clientY, clientX: touch.clientX });
              document.getElementById(`${v.id_}_`).dispatchEvent(ms);
            }, false);

            document.getElementById(`${v.id_}_`).addEventListener("touchstart", (e) => {
              canvas_id_efb = v.id_;
              c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
              mousePostion_efb = getTouchPos_efb(document.getElementById(`${v.id_}_`), e);
              let touch = e.touches[0];
              let ms = new MouseEvent("mousedown", {
                clientY: touch.clientY,
                clientX: touch.clientX
              });
              document.getElementById(`${v.id_}_`).dispatchEvent(ms);
            }, false);

            document.getElementById(`${v.id_}_`).addEventListener("touchend", (e) => {
              let ms = new MouseEvent("mouseup", {});
              document.getElementById(`${v.id_}_`).dispatchEvent(ms);
              const value = document.getElementById(`${v.id_}-sig-data`).value;
            }, false);

            (function drawLoop() {
              requestAnimFrame(drawLoop);
              renderCanvas_efb(v.id_);
            })();
            break;
          case "multiselect":
          case "payMultiselect":
            let callback =1;
            function mutlselect (len){
              setTimeout(() => {
                //const v = valj_efb.find(x=>x.id_==rndm);
                callback +=1;
                const opd = document.querySelector(`[data-id='${v.id_}_options']`);
               
                if(opd!=null){
               
                  opd.className += ` efb emsFormBuilder_v  ${v.corner} ${v.el_border_color} ${v.el_text_size} ${v.el_height}`;
                  
                  opd.onclick = function getMultiSelectvalue (){
                 
                  }

                  jQuery(function () {
                    jQuery('.selectpicker').selectpicker();
                  });
                
                }else{
                  mutlselect(10*callback);
                }
              }, len);
            }
            mutlselect(len);
            // document.querySelector(`[data-id='${v.id_}_options']`).className += `efb ${v.corner} ${v.el_border_color} ${v.el_text_size}`
            break;
          case "rating":
            /*    const rate_efbs = document.querySelector(` [data-id='${v.id_}-el']`)
               for(let rate_efb of rate_efbs){
               (rate_efb.value, v ,rate_efb);
                 rate_efb.addEventListener("click", (e)=> {
                     (rate_efb.value, v.id_ ,rate_efb);
                       document.getElementById(`${v.id_}-stared`).innerHTML = rate_efb.value;
                   })
               }  */
            break;
          case "dadfile":
            set_dadfile_fun_efb(v.id_, i)
            break;

        }
      })
    } catch {
      console.error(`Preview of Pc Form has an Error`)
    }
    if (state != 'mobile') handle_navbtn_efb(valj_efb[0].steps, 'pc')
    if(state=='run'){
      sitekye_emsFormBuilder.length>1 ? loadCaptcha_efb() :'';
      createStepsOfPublic()
    }
   // if (state != "show") myModal.show();
   step_el_efb=valj_efb[0].steps;
  }, timeout) //nlogn
  //funSetPosElEfb(valj_efb[v].dataId ,valj_efb[v].label_position)
  // وقتی پنجره پیش نمایش بسته شد دوباره المان ها اضافه شود به دارگ زون
  // تابع اضافه کردن
  //editFormEfb()
}//end function v2



function fun_prev_send(){
  jQuery(function () {
    var stp=(valj_efb[0].steps)+1;
    var wtn= loading_messge_efb();
    jQuery('#efb-final-step').html(wtn);
    var current_s = jQuery('[data-step="step-' + (current_s_efb) + '-efb"]');
    prev_s_efb = current_s.prev();
    jQuery('[data-step="icon-s-' + (current_s_efb) + '-efb"]').removeClass("active");
    jQuery('[data-step="step-' + (current_s_efb) + '-efb"]').toggleClass("d-none");
    if(stp==2){

      jQuery("#btn_send_efb").toggleClass("d-none");
    }else{
      jQuery("#next_efb").toggleClass("d-none");
    }
    //bug here
    var s = "" + (current_s_efb - 1) + ""
    var val = valj_efb.find(x => x.step == s)
    jQuery("#title_efb").attr('class', val['label_text_color']);
    jQuery("#title_efb").attr('class', "text-center");
    jQuery("#desc_efb").attr('class', val['message_text_color']);
    jQuery("#desc_efb").attr('class', "text-center");
    jQuery("#title_efb").text(val['name']);
    jQuery("#desc_efb").text(val['message']);
    jQuery("#prev_efb").toggleClass("d-none");
    prev_s_efb.show();
    
    
    current_s.animate({ opacity_efb: 0 }, {
      step: function (now) {
        opacity_efb = 1 - now;

        current_s.css({
          'display': 'none',
          'position': 'relative'
        });
        prev_s_efb.css({ 'opacity_efb': opacity_efb });
      },
      duration: 500
    });
    current_s_efb -= 1;
    setProgressBar_efb(current_s_efb,stp);
   //preview problem
  });
}


function verifyCaptcha(token){
  if(token.length>1){
    verifyCaptcha_efb=token;
    const id = valj_efb[0].steps >1 ? 'next_efb' :'btn_send_efb'
    document.getElementById(id).classList.remove('disabled');
    setTimeout(()=>{timeOutCaptcha()},61000)   
  }
}


function timeOutCaptcha(){
  const id = valj_efb[0].steps >1 ? 'next_efb' :'btn_send_efb'
  document.getElementById(id).classList.add('disabled');
 // ajax_object_efm.text.errorVerifyingRecaptcha
 noti_message_efb( ajax_object_efm.text.error, ajax_object_efm.text.errorVerifyingRecaptcha,7,'warning');
}


function fun_validation_efb(){
 //console.log(valj_efb,current_s_efb,sendBack_emsFormBuilder_pub);
  let state =true;
  let idi ="null";
  for(let row in valj_efb){
    if(row>1 && valj_efb[row].required==true && current_s_efb ==valj_efb[row].step ){
      const s = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ ==valj_efb[row].id_)
      //console.log(sendBack_emsFormBuilder_pub,s ,valj_efb[row].id_ )
      // console.log(`exist [${s}] row[${row}] id[${valj_efb[row].id_}] type[${valj_efb[row].type}] `,valj_efb[row] , sendBack_emsFormBuilder_pub[s])
      const id =fun_el_select_in_efb(valj_efb[row].type) ==false? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options` ;
      if(s==-1 ){
        if(state==true){ state=false; idi =valj_efb[row].id_ }        
        // console.log(`id [${id}]`);
         document.getElementById(`${valj_efb[row].id_}_-message`).innerHTML=efb_var.text.enterTheValueThisField;
        // console.log(id, document.getElementById(id));
        if(type_validate_efb(valj_efb[row].type)==true)document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className,"border-danger");
      }else{
       // console.log('success')
        idi = valj_efb[row].id_;
        //console.log(`id [${id}]`);
        document.getElementById(`${valj_efb[row].id_}_-message`).innerHTML="";
        if(type_validate_efb(valj_efb[row].type)==true) document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className,"border-success");

        const v= sendBack_emsFormBuilder_pub[s].value.split("@efb!");
        //console.log(valj_efb[row].type,valj_efb[row].minSelect ,v.length);
        if((valj_efb[row].type=="multiselect"||valj_efb[row].type=="payMultiselect") && (v.length-1)<valj_efb[row].minSelect  ){
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className,"border-danger");
          //console.log(efb_var.text);
          document.getElementById(`${valj_efb[row].id_}_-message`).innerHTML=efb_var.text.minSelect +" " + valj_efb[row].minSelect
          if(state==true){ state=false; idi =valj_efb[row].id_ }
        }
      }
    }
    
  }
  if(idi!="null"){document.getElementById(idi).scrollIntoView(true);}
  //console.log(state,idi);
  return state
}

function type_validate_efb(type){
 // console.log(type)
  return type == "select" || type=="multiselect"  || type=="text" || type=="password" || type=="email" || type=="conturyList" ||  type=="stateProvince"  || type=="file" || type=="url" || type=="color" || type=="date"  || type=="textarea" || type=="tel" ? true : false;
}



/* add element to dropzone Mobile */
add_element_dpz_efb=(id)=>{fun_efb_add_el(id);}

fun_efb_add_el=(t)=>{

  const rndm = Math.random().toString(36).substr(2, 9);

  console.log(t);

  if(t=="steps" && valj_efb.length<2){return;}
  if(valj_efb.length<2){dropZoneEFB.innerHTML="" , dropZoneEFB.classList.add('pb')}

  if(t=="address" || t=="name"){
    const olist = [{n:'name' ,t:"firstName"},{n:'name' ,t:"lastName"}]
    for(const ob of olist){
      if(ob.n==t){
        let el = addNewElement(ob.t, Math.random().toString(36).substr(2, 9), false, false);
        dropZoneEFB.innerHTML += el;
      }
    }

  }else{
    let el = addNewElement(t, rndm, false, false);
    dropZoneEFB.innerHTML += el;
  }
  
  fub_shwBtns_efb();

  if (t == 'maps') {

    const id = `${rndm}-map`;
  
    if (typeof google !== "undefined") {
      let map = new google.maps.Map(document.getElementById(`${id}`), {
        center: { lat: 49.24803870604257, lng: -123.10512829684463 },
        zoom: 10,
      })
    } else {
      setTimeout(() => {
        const mp = document.getElementById(`${rndm}-map`)
        mp.innerHTML = `<div class="efb boxHtml-efb sign-efb" >
        <div class="efb noCode-efb m-5 text-center">
        <button type="button" class="efb btn btn-edit btn-sm" data-bs-toggle="tooltip" title="${efb_var.text.howToAddGoogleMap}" onclick="open_whiteStudio_efb('mapErorr')">
         <i class="efb bi-question-lg text-pinkEfb"></i></button> 
         ${efb_var.text.aPIkeyGoogleMapsError}  
      </div></div>`
      }, 1400);
    }

  } 
}


addStyleColorBodyEfb=(t,c,type,id)=>{
  const ttype = valj_efb[id].type;
  //console.log(`t=>[${t}]`,`c=>[${c}]`,type , ttype);
  let v =`.${t}{color:${c}!important;}`
  let tag="";
  switch (ttype) {
    case 'textarea':
      tag="textarea"
      break;
    case 'text':
    case 'password':
    case 'email':
    case 'number':
    case 'image':
    case 'date':
    case 'tel':
    case 'url':
    case 'range':
    case 'color':
    case 'checkbox':
    case 'radiobutton':
      tag ="input"
      break;
  
    default:
      tag=""
      break;
  }
/*   if(ttype =='textarea'){
    tag="textarea"
  }  if(type =='btn' || ttype=="form"){
    tag=""
  }  else if(ttype =='text' || ttype =='password' || ttype =='email' 
    || ttype =='number' ||'image' || ttype =='date' || ttype =='tel'
    || ttype =='url' ||'range' || ttype =='color' || ttype =='checkbox'  || ttype =='radiobutton' ){
    tag ="input"
    }  */

  if(type=="text"){v =`.${type}-${t}{color:${c}!important;}`}
  else if(type=="icon"){v =`.text-${t}{color:${c}!important;}`}
  else if(type=="border"){v =`${tag}.${type}-${t}{border-color:${c}!important;}`}
  else if(type=="bg"){v =`.${type}-${t}{background-color:${c}!important;}`}
  else if(type=="btn"){v =`.${type}-${t}{background-color:${c}!important;}`}
  //console.log(tag, valj_efb[id].type,v);
  document.body.appendChild(Object.assign(document.createElement("style"), {textContent: `${v}`}))
}

fun_addStyle_costumize_efb=(val,key,indexVJ)=>{
  if(val.toString().includes('colorDEfb')) {  
    let type=""
    let color =""
    switch(key.toString()){
      case 'button_color': type="btn";color= valj_efb[indexVJ].style_btn_color ? valj_efb[indexVJ].style_btn_color :'';break;
      case 'icon_color': type="icon";color=valj_efb[indexVJ].style_icon_color ? valj_efb[indexVJ].style_icon_color:'';break;
      case 'el_text_color': type="text";color=valj_efb[indexVJ].style_el_text_color ? valj_efb[indexVJ].style_el_text_color:'';break;
      case 'label_text_color': type="text"; color=valj_efb[indexVJ].style_label_color ? valj_efb[indexVJ].style_label_color:'';break;
      case 'message_text_color': type="text";color=valj_efb[indexVJ].style_message_text_color? valj_efb[indexVJ].style_message_text_color:'';break;
      case 'el_border_color': type="border";color=valj_efb[indexVJ].style_border_color?valj_efb[indexVJ].style_border_color:'';break;
    }
    //console.log(color, type, val,key,indexVJ ,valj_efb[indexVJ])
    if(color!="")addStyleColorBodyEfb((`colorDEfb-${color.slice(1)}`),color,type,indexVJ);
    //t=>[colorDEfb-tn-colorDEfb-ff5900] c=>[btn-colorDEfb-ff5900] btn
  }
}


fun_add_stripe_efb=()=>{
  if(typeof document.getElementById('cardnoEfb')!="object") return;
  console.log('fun_add_stripe_efb');
  if(ajax_object_efm.hasOwnProperty('paymentKey')){
        if(efb_var.pro)noti_message_efb(efb_var.text.error,`${efb_var.text.errorCode}: ${efb_var.text.payment}->${efb_var.text.proVersion}`,100,'danger');
        
        const stripe = Stripe(ajax_object_efm.paymentKey,{locale: 'auto'})
      console.log(stripe);
        const elsStripeStyleEfb={
          base:{iconColor:'#6c757d',
          color:'#6c757d',
          fontFamily:'sans-serif',
          fontSize: '30px',
          '::placeholder': { color:'#757593'}
          },
          complete:{ color:'green'}
      }
      const btntripeStyleEfb ={
          fontFamily:'sans-serif',
          fontSize: '20px',
          fontWeight:'400',
          complete:{ color:'green'}
      }
      
      
      const cardnoEfb = document.getElementById('cardnoEfb')
      const cardexpEfb = document.getElementById('cardexpEfb')
      const cardcvcEfb = document.getElementById('cardcvcEfb')
      const btnStripeEfb = document.getElementById('btnStripeEfb')
      const stsStripeEfb = document.getElementById('statusStripEfb')
      /* console.log(valj_efb[0].currency ,document.getElementById('currencyPayEfb'));
      document.getElementById('currencyPayEfb').innerHTML=valj_efb[0].currency; */
      const elements = stripe.elements()
      
      const numElm = elements.create('cardNumber',{showIcon:true,iconStyle:'solid', style:elsStripeStyleEfb})
      numElm.mount(cardnoEfb)
          
      const expElm = elements.create('cardExpiry', {disabled:true, style:elsStripeStyleEfb})
      expElm.mount(cardexpEfb)
      
      const cvcElm = elements.create('cardCvc', {disabled:true, style:elsStripeStyleEfb})
      cvcElm.mount(cardcvcEfb)
      
      numElm.on('change', (e) =>{
          if(e.complete){
              expElm.update({disabled:false})
              expElm.focus()
          }
      })
      
      expElm.on('change', (e) =>{
          if(e.complete){
              cvcElm.update({disabled:false})
              cvcElm.focus()
          }
      })
      
      cvcElm.on('change', (e) =>{
      
          if(e.complete ){
            //btnStripeEfb.disabled = false 
            console.log(btnStripeEfb.classList);
              btnStripeEfb.classList.remove('disabled');
          
          }
      })
      
      btnStripeEfb.addEventListener('click', ()=>{
          btnStripeEfb.innerHTML=efb_var.text.pleaseWaiting;
          //console.log(ajax_object_efm.ajax_url);
          const v = fun_pay_valid_price();
          console.log(v)
          if(v==false){
            noti_message_efb(efb_var.text.error,efb_var.text.emptyCartM,10,'warning')      
            btnStripeEfb.innerHTML=efb_var.text.payNow
            return false;
          }else{
            btnStripeEfb.classList.add('disabled');
            jQuery(function ($) {
              data = {
                action: "pay_stripe_sub_efb",
                value: JSON.stringify(sendBack_emsFormBuilder_pub),
                name: formName,
                id : efb_var.id.id,
                nonce: ajax_object_efm.nonce,
              };
              console.log(data);
              $.ajax({
                type: "POST",
                async: false,
                url: ajax_object_efm.ajax_url,
                data: data,
                success: function (res) {         
                  console.log(res.data) ;    
      
                  if(res.data.success==true){
                    stripe.confirmCardPayment(res.data.client_secret, {
                      payment_method:{card:numElm}
                      }).then(transStat => {
                          if(transStat.error){
                              stsStripeEfb.innerHTML = `
                              <strong>${efb_var.text.error}  </string> ${transStat.error.message}
                              `
                              noti_message_efb(efb_var.text.error,transStat.error.message,10,'warning')  
                              btnStripeEfb.classList.remove('disabled');
                              btnStripeEfb.innerHTML=efb_var.text.payNow
                          }
                          else{
                              const id =valj_efb[0].steps==1 ? 'btn_send_efb' :'next_efb';
                              console.log(id);
                              document.getElementById(id).classList.remove('disabled')
                              fun_disabled_all_pay_efb()
                              stsStripeEfb.innerHTML = `
                              <h3 class="efb text-darkb p-0 m-0 mt-1 text-center"><i class="bi-check2-circle"></i> ${efb_var.text.successPayment}</h3> 
                              <p class="efb text-muted p-0  m-0 mb-2 text-center">${transStat.paymentIntent.description}</p>
                              <p class="efb text-muted p-0 m-0"><b>${efb_var.text.transctionId}:</b> ${transStat.paymentIntent.id}</p>
                              <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.payAmount}</b> : ${transStat.paymentIntent.amount/100} ${transStat.paymentIntent.currency.toUpperCase()}</p><br>
                              `

                              //sendBack_emsFormBuilder_pub.push({id_:transStat.paymentIntent.id , amount:efb_var.text.payAmount ,type:'paymentGateway' , gateway:'strip'})
                              btnStripeEfb.innerHTML="Done"
                              btnStripeEfb.style.display="none";
                              jQuery( "#statusStripEfb" ).show( "slow" )
                              //active next or send button !!
                              //disable button
                          }
                          stsStripeEfb.style.display='block'
                      })
                  }else{
                    btnStripeEfb.classList.remove('disabled');
                    noti_message_efb(efb_var.text.error, res.data.m, 60, 'danger')
                  }
                  
                },
                error: function (res) {
                  console.error(res) ;  
                  btnStripeEfb.classList.remove('disabled'); 
                  const m = `<p class="h4">${efb_var.text.error}${res.status}</p> ${res.statusText} </br> ${res.responseText}`
      
                  noti_message_efb('Stripe', m, 120, 'danger')
                  btnStripeEfb.innerHTML="Pay" 
                
                }
              })
            }); //end jquery
            
          }
        
      })//end  btnStripeEfb


  }// end if paymentKey


}//end fun_add_stripe_efb

fun_pay_valid_price=()=>{
  //console.log('fun_pay_valid_price')
let s=false ;
let price=0
for(let o of sendBack_emsFormBuilder_pub){
  console.log(o.hasOwnProperty('price'))
  if(o.hasOwnProperty('price')) price += parseFloat(o.price)
}
s = price>0 ? true : false;
console.log(s,price);

return s;
}
//pub function
fun_disabled_all_pay_efb=()=>{
  let type ='';
  document.getElementById('stripeCardSectionEfb').classList.add('d-none');
  for (let o of valj_efb){
    if(o.hasOwnProperty('price')){
      if(o.hasOwnProperty('parent')){
        const p = valj_efb.findIndex(x=>x.id_ == o.parent);
        type = valj_efb[p].type;
        console.log(o.parent,p,type);
        let ov = document.querySelector(`[data-vid ="${o.parent}"]`); 
        ov.classList.remove('payefb');
        ov.classList.add('disabled');
        ov.disabled=true;
        if(type !="multiselect" && type !="select" && type !="payMultiselect" && type !="paySelect"){
          const ob = valj_efb.filter(obj => {
            return obj.parent === o.parent
          })
          console.log(ob);
          for(let o of ob){
            ov = document.getElementById(o.id_);
            console.log(ov);
            ov.classList.add('disabled');
            ov.classList.remove('payefb');
            ov.disabled=true;
          }//end for
          
        }//end if multiselect 
        } 
        /*   const ov = document.querySelector(`[data-vid ="${o.id_}"]`)
          //console.log(o.type,o , `[${document.getElementById(o.id_)}]`);
         if(ov!=null && document.ov.className!=null) {
          ov.classList.add('disabled') 
          ov.classList.remove('payefb');
         } */
     
    }
  }
}




