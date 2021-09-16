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
console.log(`text`, efb_var.text.form)
let formName_Efb = efb_var.text.form
let pro_test = false
let maps_efb = [];

let mousePostion_efb = { x: 0, y: 0 };
let draw_mouse_efb = false;
let c2d_contex_efb
let lastMousePostion_efb = mousePostion_efb;
let canvas_id_efb = "";
let fileEfb;
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
  console.log(`text [${efb_var.text.name}]`)
  remove_other_noti()
  console.log('creator_form_builder_Efb()')

  console.log(valj_efb)
  if(valj_efb.length<2){
    step_el_efb = 1;
    valj_efb.push({
      type: 'form', steps: 1, formName: efb_var.text.form, email: '', trackingCode: '', EfbVersion: 2,
      button_single_text: 'send', button_color: 'btn-primary', icon: 'bi-ui-checks-grid', button_Next_text: 'next', button_Previous_text: 'previous',
      button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single', corner: 'efb-rounded', label_text_color: 'text-light',
      el_text_color: 'text-light', message_text_color: 'text-muted', icon_color: 'text-light', el_height: 'h-d-efb', email_to: false, show_icon: false, show_pro_bar: false, captcha: false,
      sendEmail: false, stateForm: false
    });
  }
  console.log(efb_var);
  const objct = [{ name: 'Text', icon: 'bi-file-earmark-text', id: 'text', pro: false },
  { name: efb_var.text.password, icon: 'bi-lock', id: 'password', pro: false },
  { name: efb_var.text.email, icon: 'bi-envelope', id: 'email', pro: false },
  { name: efb_var.text.step, icon: 'bi-file', id: 'steps', pro: false },
  { name: efb_var.text.number, icon: 'bi-pause', id: 'number', pro: false },
  { name: efb_var.text.textarea, icon: 'bi-card-text', id: 'textarea', pro: false },
  { name: efb_var.text.checkbox, icon: 'bi-check-square', id: 'checkbox', pro: false },
  { name: efb_var.text.radiobutton, icon: 'bi-record-circle', id: 'radio', pro: false },
  { name: efb_var.text.select, icon: 'bi-check2', id: 'select', pro: false },
  { name: efb_var.text.file, icon: 'bi-file-earmark-plus', id: 'file', pro: false },
  { name: efb_var.text.dadfile, icon: 'bi-plus-square-dotted', id: 'dadfile', pro: true },
  { name: efb_var.text.date, icon: 'bi-calendar-date', id: 'date', pro: true },
  { name: efb_var.text.multiselect, icon: 'bi-check-all', id: 'multiselect', pro: true },
  { name: efb_var.text.esign, icon: 'bi-pen', id: 'esign', pro: true },
  { name: efb_var.text.switch, icon: 'bi-toggle2-on', id: 'switch', pro: true },
  { name: efb_var.text.locationPicker, icon: 'bi-pin-map', id: 'maps', pro: true },
  { name: efb_var.text.tel, icon: 'bi-telephone', id: 'tel', pro: true },
  { name: efb_var.text.url, icon: 'bi-link-45deg', id: 'url', pro: true },
  { name: efb_var.text.color, icon: 'bi-palette', id: 'color', pro: true },
  { name: efb_var.text.range, icon: 'bi-arrow-left-right', id: 'range', pro: true },
  { name: efb_var.text.rating, icon: 'bi-star', id: 'rating', pro: true },
  { name: efb_var.text.yesNo, icon: 'bi-hand-index', id: 'yesNo', pro: true },
  { name: efb_var.text.htmlCode, icon: 'bi-code-square', id: 'html', pro: true }]


  let els = "<!--efb.app-->";
  for (let ob of objct) {
    els += `
    <div class="col-3 draggable-efb" draggable="true" id="${ob.id}">
     ${ob.pro == true ? ` <a type="button" onClick='pro_show_efb(1)' class="pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb bi-gem text-light"></i></a>` : ''}
      <button type="button" class="btn efb btn-select-form float-end" id="${ob.id}_b"><i class="efb ${ob.icon}"></i><span class="d-block text-capitalize">${ob.name}</span></button>
    </div>
    `
  }
  const navs = [
    { name: efb_var.text.save, icon: 'bi-save', fun: `saveFormEfb()` },
    { name: efb_var.text.pcPreview, icon: 'bi-display', fun: `previewFormEfb('pc')` },
    { name: efb_var.text.formSetting, icon: 'bi-sliders', fun: `show_setting_window_efb('formSet')` },
    { name: efb_var.text.help, icon: 'bi-question-square', fun: `` },
    { name: 'edit(Test)', icon: 'bi-pen', fun: `editFormEfb()` },
  ]
  let nav = "<!--efb.app-->";
  for (let ob in navs) {
    nav += `  
  <li class="nav-item"><a class="nav-link efb btn text-capitalize ${ob != 0 ? '' : 'btn-outline-pink'} " ${navs[ob].fun.length > 2 ? `onClick="${navs[ob].fun}""` : ''} ><i class="${navs[ob].icon} me-1 "></i>${navs[ob].name}</a></li>`
  }
  document.getElementById(`content-efb`).innerHTML = `
  <div class="m-5">
  <div id="panel_efb">
      <nav class="navbar navbar-expand-lg navbar-light bg-light my-2 bg-response efb">
          <div class="container-fluid">
              <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
              <div class="collapse navbar-collapse" id="navbarTogglerDemo01"><ul class="navbar-nav me-auto mb-2 mb-lg-0">${nav}</ul></div>
          </div>      
      </nav>
      <div class="row">
          <div class="col-md-4" id="listElEfb"><div class="row">${els}</div></div>
         <div class="col-md-8"><div class="crd efb  drag-box"><div class="card-body dropZone row " id="dropZone"></div></div></div></div>
      </div>
  <div class="modal fade " id="settingModalEfb" aria-hidden="true" aria-labelledby="settingModalEfb"  role="dialog" tabindex="-1" data-backdrop="static" >
      <div class="modal-dialog modal-dialog-centered " id="settingModalEfb_" >
          <div class="modal-content efb " id="settingModalEfb-sections">
                  <div class="modal-header efb"> <h5 class="modal-title" ><i class="bi-ui-checks me-2" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${efb_var.text.editField}</span></h5></div>
                  <div class="modal-body" id="settingModalEfb-body"><div class="card-body text-center"><div class="lds-hourglass"></div><h3 class="efb">${efb_var.text.pleaseWaiting}</h3></div></div>
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
    console.log(el.id)
    el.addEventListener("click", (e) => {
      active_element_efb(el);

    });
  }
}

function active_element_efb(el) {
  // تابع نمایش دهنده و مخفی کنند کنترل هر المان
  //show config buttons
  console.log(el)
  if (el.id != activeEl_efb) {
    if (activeEl_efb == 0) {
      activeEl_efb = document.getElementById(el.id).dataset.id;
    }
    document.getElementById(`btnSetting-${activeEl_efb}`).classList.toggle('d-none')
    // console.log(`activeEl_efb [${activeEl_efb}]`, document.querySelector(`[data-id="${activeEl_efb}"]`));
    if (document.querySelector(`[data-id="${activeEl_efb}"]`)) {
      // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-none')
      if (document.querySelector(`[data-id="${activeEl_efb}"]`).classList.contains('field-selected-efb')) document.querySelector(`[data-id="${activeEl_efb}"]`).classList.remove('field-selected-efb')
    }

    activeEl_efb = el.dataset.id;
    console.log(activeEl_efb)
    if (document.getElementById(`btnSetting-${activeEl_efb}`).classList.contains('d-none')) document.getElementById(`btnSetting-${activeEl_efb}`).classList.remove('d-none')
    // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-block')
    document.querySelector(`[data-id="${activeEl_efb}"]`).classList.add('field-selected-efb')


  }
}

//setting of  element
function show_setting_window_efb(idset) {
  console.group('show_setting_window_efb')
  // ویرایش پیشرفته هر المان را به مدال اضافه می کند که کاربر ویرایش را بتواند انجام دهد
  // نکته : باید بعدا وقتی اضافه می کنیم از طریق جیسون مقدارهای قبلی هم نمایش بدهم
  console.log(idset, idset != "button_group" && idset != "formSet")
  // const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  let el = idset != "formSet" ? document.querySelector(`[data-id="${idset}"]`) : { dataset: { id: 'formSet', tag: 'formSet' } }
  console.log(el)
  let body = ``;
  //const bodySetting = document.getElementById("settingModalEfb-body");
  const indx = idset != "button_group" && idset != "formSet" ? valj_efb.findIndex(x => x.dataId == idset) : 0;
  if (indx == 0 && idset != "formSet") el = document.getElementById(`f_btn_send_efb`);
  console.log(idset, valj_efb[indx], el)
  const labelEls = `<label for="labelEl" class="form-label mt-2 efb">${efb_var.text.label}<span class=" mx-1 efb">*</span></label>
  <input type="text"  data-id="${idset}" class="elEdit form-control text-muted efb  h-d-efb  mb-1"  placeholder="${efb_var.text.label}" id="labelEl" required value="${valj_efb[indx].name ? valj_efb[indx].name : ''}">`

  const desEls = `<label for="desEl" class="form-label mt-2 efb">${efb_var.text.description}</label>
  <input type="text" data-id="${idset}" class="elEdit form-control text-muted efb h-d-efb mb-1" placeholder="${efb_var.text.description}" id="desEl" required value="${valj_efb[indx].message ? valj_efb[indx].message : ''}">`
  const requireEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="requiredEl" ${valj_efb[indx].required == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="requiredEl">${efb_var.text.required}</label>                                            
  </div>`;

  const emailEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="SendemailEl" ${valj_efb[0].email_to && valj_efb[0].email_to == valj_efb[indx].id_ ? 'checked' : ''}>
  <label class="form-check-label efb" for="SendemailEl">${efb_var.text.thisEmailNotificationReceive}</label>                                            
  </div>`;
  const adminFormEmailEls = `<label for="adminFormEmailEl" class="form-label mt-2 efb">${efb_var.text.enterAdminEmailReceiveNoti}</label>
  <input type="text" data-id="${idset}" class="elEdit text-muted form-control h-d-efb  mb-1 efb" placeholder="${efb_var.text.enterAdminEmailReceiveNoti}" id="adminFormEmailEl" required value="${valj_efb[0].email ? valj_efb[0].email : ''}">`
  const trackingCodeEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="trackingCodeEl" ${valj_efb[0].trackingCode && valj_efb[0].trackingCode == 1 || valj_efb[0].trackingCode == true ? 'checked' : ''}>
  <label class="form-check-label efb" for="trackingCodeEl">${efb_var.text.activeTrackingCode}</label>                                            
  </div>`;
  const captchaEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="captchaEl" ${valj_efb[0].captcha && valj_efb[0].captcha == 1 || valj_efb[0].trackingCode == true ? 'checked' : ''}>
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
  const showformLoggedEls = `<div class="mx-1 my-3 efb">
  <input  data-id="${idset}" class="elEdit form-check-input efb" type="checkbox"  id="showformLoggedEl" ${valj_efb[0].show_pro_bar && valj_efb[0].show_pro_bar == 1 ? 'checked' : ''}>
  <label class="form-check-label efb" for="showformLoggedEl">${efb_var.text.showTheFormTologgedUsers}</label>                                            
  </div>`;

  const Nadvanced = `
    ${labelEls}
    ${desEls}
    ${requireEls}`
  const labelFontSizeEls = `
    <label for="labelFontSizeEl" class="mt-3 bi-aspect-ratio me-2 efb">${efb_var.text.labelSize}</label>
                      <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="labelFontSizeEl"  data-tag="${valj_efb[indx].type}">                                            
                          <option value="fs-6" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-6' ? `selected` : ''}>${efb_var.text.default}</option>
                          <option value="fs-7" ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-7' ? `selected` : ''}>${efb_var.text.small}</option>
                          <option value="fs-5"  ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-5' ? `selected` : ''} >${efb_var.text.large}</option>                      
                          <option value="fs-4"  ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-4' ? `selected` : ''} >${efb_var.text.xlarge}</option>                      
                          <option value="fs-3"  ${valj_efb[indx].label_text_size && valj_efb[indx].label_text_size == 'fs-3' ? `selected` : ''} >${efb_var.text.xxlarge}</option>                      
                      </select>`;

  const labelPostionEls = `    
  <div class="row efb">     
  <label for="labelPostionEl" class="mt-3 col-12 bi-arrows-angle-contract me-2 efb">${efb_var.text.labelPostion}</label>
    <div class="btn-group btn-group-toggle col-12 efb" data-toggle="buttons" data-id="${idset}"  id="labelPostionEl">    
      <label class=" btn btn-primary bi-chevron-bar-down ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'up' ? `active` : ''}" onClick="funSetPosElEfb('${idset}','up')">
        <input type="radio" name="options" class="opButtonEfb elEdit "  data-id="${idset}"  id="labelPostionEl" value="up" >${efb_var.text.up}</label>
      <span class="efb border-right border border-light "></span>
      <label class=" btn btn-primary bi-chevron-bar-right ${valj_efb[indx].label_position && valj_efb[indx].label_position == 'beside' ? `active` : ''}" onClick="funSetPosElEfb('${idset}','besie')">
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
    <label for="labelPostionEl" class="efb mt-3 col-12 bi-align-center me-2">${side} ${efb_var.text.align}</label>
      <div class="efb btn-group btn-group-toggle col-12 " data-toggle="buttons" data-side="${side}" data-id="${idset}"  id="ElementAlignEl">    
        <label class=" btn btn-primary bi-align-start ${value == left ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${left}','${side}')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit "  data-id="${idset}"  id="labelPostionEl" value="left" >${efb_var.text.left}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" btn btn-primary bi-align-center ${value == center ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${center}','${side}')">
          <input type="radio" name="options" class="opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="center">${efb_var.text.center}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" btn btn-primary bi-align-end ${value == right ? `active` : ''}" onClick="funSetAlignElEfb('${idset}','${right}','${side}')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="labelPostionEl" value="right">${efb_var.text.right}</label>
      </div></div>`;
  }
  const widthEls = `
    <label for="widthEl" class="efb mt-3 bi-arrow-left-right me-2">${efb_var.text.width}</label>
    <select  data-id="${idset}" class="efb efb-rounded elEdit form-select"  id="sizeEl" >                                            
        <option value="33" ${valj_efb[indx].size && valj_efb[indx].size == 33 ? `selected` : ''}>33%</option>
        <option value="50" ${valj_efb[indx].size && valj_efb[indx].size == 50 ? `selected` : ''}>50%</option>
        <option value="80" ${valj_efb[indx].size && valj_efb[indx].size == 80 ? `selected` : ''} >80%</option>
        <option value="100" ${valj_efb[indx].size && valj_efb[indx].size == 100 ? `selected` : ''} >100%</option>
    </select>
    `
  const classesEls = `
    <label for="cssClasses" class="efb mt-3 bi-journal-code me-2">${efb_var.text.cSSClasses}</label>
    <input type="text"  data-id="${idset}" class="efb elEdit text-muted form-control efb mb-3 mb-1" id="classesEl" placeholder="${efb_var.text.cSSClasses}"  ${valj_efb[indx].classes && valj_efb[indx].classes.length > 1 ? `value=${valj_efb[indx].classes}` : ''}>
    `
  const valueEls = `
  <label for="valueEl" class="efb mt-3 bi-cursor-text me-2">${efb_var.text.defaultValue}</label>
    <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb mb-3" data-tag="${valj_efb[indx].type}" id="valueEl" placeholder="${efb_var.text.defaultValue}" ${valj_efb[indx].value && valj_efb[indx].value.length > 1 ? `value=${valj_efb[indx].value}` : ''}>
    `
  const placeholderEls = `
    <label for="placeholderEl" class="efb mt-3 bi-patch-exclamation me-2">${efb_var.text.placeholder}</label>
    <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb mb-3"id="placeholderEl" placeholder="${efb_var.text.placeholder}" ${valj_efb[indx].placeholder && valj_efb[indx].placeholder.length > 1 ? `value=${valj_efb[indx].placeholder}` : ''}>
    `

  const cornerEls = (side) => {

    return `
      <div class="efb row">
      <label for="cornerEl" class="efb mt-3 col-12 bi-bounding-box-circles">${efb_var.text.corners}</label>
      <div class="efb btn-group col-12  btn-group-toggle" data-toggle="buttons" data-side="${side}" data-id="${idset}-set" data-tag="${valj_efb[indx].type}" id="cornerEl">    
        <label class=" btn  btn-primary bi-app ${valj_efb[indx].corner && valj_efb[indx].corner == 'efb-rounded' ? `active` : ''}" onClick="funSetCornerElEfb('${idset}','efb-rounded')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit "  data-id="${idset}"  id="cornerEl" value="efb-rounded" >${efb_var.text.rounded}</label>
        <span class="efb border-right border border-light "></span>
        <label class=" btn btn-primary bi-diamond ${valj_efb[indx].corner && valj_efb[indx].corner == 'efb-square' ? `active` : ''}" onClick="funSetCornerElEfb('${idset}','efb-square')">
          <input type="radio" name="options" class="efb opButtonEfb elEdit" data-id="${idset}"  id="cornerEl" value="efb-square"> ${efb_var.text.square}</label>
      </div></div>`
  }
  const iconEls = (side) => {
    let icon = "";
    let t = ""
    if (side == "Next") { icon = valj_efb[0].button_Next_icon; t = efb_var.text.next; }
    else if (side == "Previous") { icon = valj_efb[0].button_Previous_icon; t = efb_var.text.previous }
    else { icon = valj_efb[indx].icon }

    console.log(icon);
    return `<label for="iconEl" class="efb form-label  bi-heptagon me-2 mt-2">${t} ${efb_var.text.icon}  <a class="fs-7 efb" href="https://icons.getbootstrap.com/#icons">${efb_var.text.iconList}</a></label>
      <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb mb-1" data-side="${side}"  placeholder="${efb_var.text.icon}" id="iconEl" required value="${icon}">`
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
    <input type="text" data-id="${idset}" class="efb elEdit text-muted form-control h-d-efb mb-1" data-side="${side}" placeholder="${efb_var.text.text}" id="SingleTextEl" required value="${text ? text : ''}">`
  }


  const fileTypeEls = `
        <label for="fileTypeEl" class="efb mt-3 bi-file-earmark-medical me-2 ">${efb_var.text.fileType}</label>
        <select  data-id="${idset}" class="efb elEdit form-select"  id="fileTypeEl" data-tag="${valj_efb[indx].type}">                                            
        <option value="document" ${valj_efb[indx].type && valj_efb[indx].type == 'document' ? `selected` : ''} >${efb_var.text.documents}</option>
        <option value="image" ${valj_efb[indx].type && valj_efb[indx].type == 'image' ? `selected` : ''}>${efb_var.text.image}</option>
        <option value="media" ${valj_efb[indx].type && valj_efb[indx].type == 'media' ? `selected` : ''} >${efb_var.text.media}</option>
        <option value="zip" ${valj_efb[indx].type && valj_efb[indx].type == 'zip' ? `selected` : ''} >${efb_var.text.zip}</option>
    </select>
    `
  const btnColorEls = `
        <label for="btnColorEl" class="efb mt-3 bi-paint-bucket me-2">${efb_var.text.buttonColor}</label>
        <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded"  id="btnColorEl" data-tag="${valj_efb[indx].type}">                                            
        <option value="btn-default" class="text-default efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-default' ? `selected` : ''} >${efb_var.text.default}</option>
        <option value="btn-primary" class="text-primary efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-primary' ? `selected` : ''}>${efb_var.text.blue}</option>
        <option value="btn-darkb" class="text-darkb efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-darkb' ? `selected` : ''}>${efb_var.text.darkBlue}</option>
        <option value="btn-info" class="text-info efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-info' ? `selected` : ''} >${efb_var.text.lightBlue}</option>
        <option value="btn-dark" class="text-dark efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-dark' ? `selected` : ''} >${efb_var.text.black}</option>
        <option value="btn-secondary" class="text-secondary efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-secondary' ? `selected` : ''} >${efb_var.text.grayLight}</option>
        <option value="btn-success"  class="text-success efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-success' ? `selected` : ''} >${efb_var.text.green}</option>
        <option value="btn-danger"  class="text-danger efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-danger' ? `selected` : ''} >${efb_var.text.red}</option>
        <option value="btn-pinkEfb" class="text-pinkEfb efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-pinkEfb' ? `selected` : ''} >${efb_var.text.pink}</option>
        <option value="btn-warning" class="text-warning efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-warning' ? `selected` : ''} >${efb_var.text.yellow}</option>
        <option value="btn-light" class="class="text-light bg-dark efb" ${valj_efb[indx].button_color && valj_efb[indx].button_color == 'btn-light' ? `selected` : ''} >${efb_var.text.light}</option>
    </select>
    `
  const selectBorderColorEls = (forEl) => {
    console.log(`[${valj_efb[indx].el_border_color}]`)
    let color = valj_efb[indx].el_border_color;

    return `
      <label for="selectBorderColorEl" class="efb mt-3 bi-paint-bucket me-2">${efb_var.text.borderColor}</label>
      <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded" data-el="${forEl}"  id="selectBorderColorEl" data-tag="${valj_efb[indx].type}">                                            
      <option value="border-d" class="textdefault efb" ${color && color == 'border-d' ? `selected` : ''} >${efb_var.text.default}</option>
      <option value="border-primary" class="text-primary efb" ${color && color == 'border-primary' ? `selected` : ''}>${efb_var.text.blue}</option>
      <option value="border-darkBEfb" class="text-darkb efb" ${color && color == 'border-darkBEfb' ? `selected` : ''} >${efb_var.text.darkBlue}</option>
      <option value="border-info" class="text-info efb" ${color && color == 'border-info' ? `selected` : ''} >${efb_var.text.lightBlue}</option>
      <option value="border-danger"  class="text-danger efb" ${color && color == 'border-danger' ? `selected` : ''} >${efb_var.text.red}</option>
      <option value="border-success"  class="text-success efb" ${color && color == 'border-success' ? `selected` : ''} >${efb_var.text.green}</option>
      <option value="border-dark" class="text-dark efb" ${color && color == 'border-dark' ? `selected` : ''} >${efb_var.text.grayDark}</option>
      <option value="border-muted" class="text-muted efb" ${color && color == 'border-muted' ? `selected` : ''} >${efb_var.text.grayLight}</option>
      <option value="border-secondary" class="text-secondary efb" ${color && color == 'border-secondary' ? `selected` : ''} >${efb_var.text.grayLighter}</option>
      <option value="border-pinkEfb" class="text-pinkEfb efb" ${color && color == 'border-pinkEfb' ? `selected` : ''} >${efb_var.text.pink}</option>
      <option value="border-warning" class="text-warning efb" ${color && color == 'border-warning' ? `selected` : ''} >${efb_var.text.yellow}</option>
      <option value="border-white" class="class="text-white bg-dark efb" ${color && color == 'border-white' ? `selected` : ''} >${efb_var.text.white}</option>
      <option value="border-light" class="class="text-light bg-dark efb" ${color && color == 'border-light' ? `selected` : ''} >${efb_var.text.light}</option>
      </select>
      `
  }
  const selectColorEls = (forEl) => {
    console.log(`[${valj_efb[indx].label_text_color}]`)
    let t = ''
    let color = '';
    if (forEl == 'icon') {
      color = valj_efb[indx].icon_color;
      t = efb_var.text.icon;
    } else if (forEl == 'description') {
      color = valj_efb[indx].message_text_color;
      t = efb_var.text.description
    } else if (forEl == 'label') {
      color = valj_efb[indx].label_text_color;
      t = efb_var.text.label
    } else if (forEl == "el") {
      color = valj_efb[indx].el_text_color;
      t = efb_var.text.field
    }
    return `
      <label for="selectColorEl" class="mt-3 bi-paint-bucket me-2 efb">${t} ${efb_var.text.clr}</label>
      <select  data-id="${idset}" class="elEdit form-select efb border-d efb-rounded" data-el="${forEl}"  id="selectColorEl" data-tag="${valj_efb[indx].type}">                                            
      <option value="text-labelEfb" class="textdefault efb" ${color && color == '' ? `selected` : ''} >${efb_var.text.default}</option>
      <option value="text-primary" class="text-primary efb" ${color && color == 'text-primary' ? `selected` : ''}>${efb_var.text.blue}</option>
      <option value="text-darkb" class="text-darkb efb" ${color && color == 'text-darkb' ? `selected` : ''} >${efb_var.text.darkBlue}</option>
      <option value="text-info" class="text-info efb" ${color && color == 'text-info' ? `selected` : ''} >${efb_var.text.lightBlue}</option>
      <option value="text-danger"  class="text-danger efb" ${color && color == 'text-danger' ? `selected` : ''} >${efb_var.text.red}</option>
      <option value="text-success"  class="text-success efb" ${color && color == 'text-success' ? `selected` : ''} >${efb_var.text.green}</option>
      <option value="text-dark" class="text-dark efb" ${color && color == 'text-dark' ? `selected` : ''} >${efb_var.text.grayDark}</option>
      <option value="text-muted" class="text-muted efb" ${color && color == 'text-muted' ? `selected` : ''} >${efb_var.text.grayLight}</option>
      <option value="text-secondary" class="text-secondary efb" ${color && color == 'text-secondary' ? `selected` : ''} >${efb_var.text.grayLighter}</option>
      <option value="text-pinkEfb" class="text-pinkEfb efb" ${color && color == 'text-pinkEfb' ? `selected` : ''} >${efb_var.text.pink}</option>
      <option value="text-warning" class="text-warning efb" ${color && color == 'text-warning' ? `selected` : ''} >${efb_var.text.yellow}</option>
      <option value="text-white" class="class="text-white bg-dark efb" ${color && color == 'text-white' ? `selected` : ''} >${efb_var.text.white}</option>
      <option value="text-light" class="class="text-light bg-dark efb" ${color && color == 'text-light' ? `selected` : ''} >${efb_var.text.light}</option>
      </select>
      `
  }
  const selectHeightEls = () => {
    return `
      <label for="selectHeightEl" class="efb mt-3 bi-arrow-down-up me-2">${efb_var.text.height}</label>
      <select  data-id="${idset}" class="efb efb-rounded elEdit form-select"  id="selectHeightEl" data-tag="${valj_efb[indx].type}">                                            
      <option value="h-d-efb" ${valj_efb[indx].el_height && valj_efb[indx].el_height == 'h-d-efb' ? `selected` : ''}>${efb_var.text.default}</option>
      <option value="h-l-efb"  ${valj_efb[indx].el_height && valj_efb[indx].el_height == 'h-l-efb' ? `selected` : ''} >${efb_var.text.large}</option>                      
      <option value="h-xl-efb"  ${valj_efb[indx].el_height && valj_efb[indx].el_height == 'h-xl-efb' ? `selected` : ''} >${efb_var.text.xlarge}</option>                      
      <option value="h-xxl-efb"  ${valj_efb[indx].el_height && valj_efb[indx].el_height == 'h-xxl-efb' ? `selected` : ''} >${efb_var.text.xxlarge}</option>                      
      <option value="h-xxxl-efb"  ${valj_efb[indx].el_height && valj_efb[indx].el_height == 'h-xxxl-efb' ? `selected` : ''} >${efb_var.text.xxxlarge}</option>                      
      </select>
      `
  }

  console.log(body);
  console.log(el)
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
                      ${selectColorEls('label')}
                      ${selectColorEls('description')}
                      ${selectColorEls('el')}
                      ${selectBorderColorEls('element')}
                      ${placeholderEls}
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
      console.log('input text')
      break;
    //  case "multiselect":
    case "radio":
    case "checkbox":
    case "select":
    case "multiselect":
      const objOptions = valj_efb.filter(obj => {
        return obj.parent === el.id
      })
      const newRndm = Math.random().toString(36).substr(2, 9);
      let opetions = `<!-- options --!>`;

      if (objOptions.length > 0) {
        for (let ob of objOptions) {
          console.log(ob);
          opetions += `<div id="${ob.id_op}-v">
        <input type="text" placeholder="${efb_var.text.name}" id="EditOption"  value="${ob.value}" data-parent="${el.id}" data-id="${ob.id_op}" data-tag="${el.dataset.tag}" class="col-5 form-control text-muted efb mb-1 fs-7 elEdit">
        <div class="efb btn-edit-holder" id="deleteOption">
          <button type="button" id="deleteOption"  onClick="delete_option_efb('${ob.id_op}')" data-parent="${el.id}" data-tag="${el.dataset.tag}"  data-id="${ob.id_op}" class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}"> 
              <i class="efb efb bi-x-lg text-danger"></i>
          </button>
          <button type="button" id="addOption" onClick="add_option_edit_pro_efb()" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndm}" class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" > 
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
              <label for="optionListefb" class="efb ">Options 
              <button type="button" id="addOption" onClick="add_option_edit_pro_efb()" data-parent="${el.id}" data-tag="${el.dataset.tag}" data-id="${newRndm}"   class="btn efb btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.add}" > 
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
                      ${selectColorEls('label')}
                      ${selectColorEls('description')}
                      ${el.dataset.tag == 'select' || el.dataset.tag == 'multiselect' ? cornerEls('') : ''} 
                      ${el.dataset.tag == 'select' || el.dataset.tag == 'multiselect' ? selectBorderColorEls('element') : ''} 
                      ${el.dataset.tag != 'multiselect' ? selectColorEls('el') : ''} 
                      ${labelPostionEls}
                      ${ElementAlignEls('label')}
                      ${ElementAlignEls('description')}
                      ${widthEls}     
                      ${selectHeightEls()}                 
                      ${classesEls}
                      </div>
                  </div>
                  
              </div>
              <div class="efb clearfix"></div>
              `

      console.log('options', valj_efb)
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
              ${selectColorEls('label')}
              ${selectColorEls('description')}
             
              ${el.dataset.tag == 'rating' || el.dataset.tag == 'range' ? "" : selectBorderColorEls('element')}
              ${labelPostionEls}
              ${ElementAlignEls('label')}
              ${ElementAlignEls('description')}
              ${el.dataset.tag == 'rating' ? '' : widthEls}
              ${selectHeightEls()}
              ${el.dataset.tag == 'rating' || el.dataset.tag == 'switch' ? '' : cornerEls('')}
              ${el.dataset.tag == 'esign' ? selectColorEls('icon') : ''}
              ${el.dataset.tag == 'esign' ? iconEls('') : ''}
              ${el.dataset.tag == 'esign' ? btnColorEls : ''}
              ${el.dataset.tag == 'esign' ? SingleTextEls('') : ''}
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "file":
    case "dadfile":
      console.log(idset)
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      ${Nadvanced}

      ${fileTypeEls}
      <!--  not   advanced-->
      <div class="efb d-grid gap-2">              
      <button class="efb btn btn-outline-light mt-3 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <i class="efb bi-arrow-down-circle-fill me-1"></i>${efb_var.text.advanced}
      </button>
      </div>
      <div class="efb mb-3 mt-3 collapse" id="collapseAdvanced">
              <div class="efb mb-3 px-3 row">                                                          
              ${labelFontSizeEls}
              ${selectColorEls('label')}
              ${selectColorEls('description')}
              ${el.dataset.tag == 'dadfile' ? selectColorEls('icon') : ''}
              ${el.dataset.tag == 'dadfile' ? btnColorEls : ''}
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
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control efb h-d-efb mb-1" placeholder="${efb_var.text.exDot} 49.24803870604257" id="letEl" required value="${valj_efb[indx].lat}">
      <label for="lonEl" class="efb form-label  mt-2">${efb_var.text.longitude}</label>
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control efb h-d-efb mb-1" placeholder="${efb_var.text.exDot}  -123.10512829684463" id="lonEl" required value="${valj_efb[indx].lng}">
      <label for="marksEl" class="efb form-label  mt-2">Pickup location by user <a class="fs-7 efb" onClick ="open_whiteStudio_efb('pickupByUser')">help</a></label></label>
      <input type="text" data-id="${idset}" class="elEdit text-muted form-control efb h-d-efb mb-1" placeholder=${efb_var.text.exDot}  1" id="marksEl" required value="${valj_efb[indx].mark}">
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
              ${selectColorEls('label')}
              ${selectColorEls('description')}
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `

      break;
    case "html":
      console.log(`HTML Code iS runnn [${valj_efb[indx].value}]`)
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
      <label for="letEl" class="efb form-label mt-2"><i class="efb bi-code-square me-2" ></i>${efb_var.text.code}</label>
      <small class="text-info fs-7 efb">${efb_var.text.pleaseDoNotAddJsCode}</small>
      <textarea placeholder="${efb_var.text.htmlCode}" 
      class="elEdit form-control efb  h-d-efb   mb-1"
       data-id="${valj_efb[indx].id_}" id="htmlCodeEl" rows="13" >${valj_efb[indx].value}</textarea>
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
              ${selectColorEls('label')}
              ${selectColorEls('description')}
              ${selectColorEls('el')}
              ${btnColorEls}
              ${labelPostionEls}
              ${ElementAlignEls('label')}
              ${ElementAlignEls('description')}
              
              ${widthEls}
              ${selectHeightEls()}
              ${cornerEls('yesNo')}
              <label for="valueEl" class="efb mt-3 bi-cursor-text me-2">${efb_var.text.button1Value}</label>
              <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="1" placeholder="${efb_var.text.exDot} ${efb_var.text.yes}" value="${valj_efb[indx].button_1_text}">
              <label for="valueEl" class="efb mt-3 bi-cursor-text me-2">${efb_var.text.button2Value}</label>
              <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb mb-3" id="valueEl" data-tag="yesNo" data-no="2" placeholder="${efb_var.text.exDot} ${efb_var.text.no}" value="${valj_efb[indx].button_2_text}">
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
             
              ${selectColorEls('label')}   
              ${selectColorEls('description')}     
              ${selectColorEls('icon')}                                          
              ${classesEls}              
              </div>
          </div>
      </div><div class="efb clearfix"></div>
      `
      break;
    case "buttonNav":
      let content = ` 
      ${SingleTextEls('')}
      ${iconEls('')}
      ${selectColorEls('el')}
      ${cornerEls('Next')}
      ${btnColorEls}
      ${selectColorEls('icon')}
      ${selectHeightEls()}`

      if (valj_efb[0].button_state != "single") {
        content = `
           ${SingleTextEls("Previous")}
           ${iconEls("Previous")}
           ${SingleTextEls("Next")}
           ${iconEls("Next")}
           ${selectColorEls('el')}
           ${cornerEls('Next')}
           ${btnColorEls}
           ${selectColorEls('icon')}
           ${selectHeightEls()}
           `
      }
      console.log('buttonNav');
      body = `
      <div class="efb mb-3">
      <!--  not   advanced-->
                  ${content}                      
      </div>
      `
      break;
    case 'formSet':
      body = `
        <label for="formNameEl" class="form-label mt-2 efb">${efb_var.text.formName}<span class=" mx-1 efb">*</span></label>
         <input type="text"  data-id="${idset}" class="elEdit text-muted form-control efb  h-d-efb  mb-1"  placeholder="${efb_var.text.formName}" id="formNameEl" required value="${valj_efb[0].formName}">
        ${trackingCodeEls}
        ${captchaEls}
        ${showSIconsEls}
        ${showSprosiEls}
        ${showformLoggedEls}
        ${adminFormEmailEls}
        `
      break;

  }
  console.log('before show');
  show_modal_efb(body, efb_var.text.edit, 'bi-ui-checks me-2', 'settingBox')
  console.log('end firs group befor elEdit')
  for (const el of document.querySelectorAll(`.elEdit`)) {
    el.addEventListener("change", (e) => {
      change_el_edit_Efb(el);

    })
  }
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show()
  console.log('after show');

}

let change_el_edit_Efb = (el) => {
  const indx = el.dataset.id != "button_group" ? valj_efb.findIndex(x => x.dataId == el.dataset.id) : 0;
  let clss = ''
  let postId
  console.log(el)
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
      if (el.value.match(/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/)) // email validation
      {
        valj_efb[0].email = el.value;
        return true;
      }
      else {
        alert(efb_var.text.invalidEmail);
      }

      break;
    case "requiredEl":
      valj_efb[indx].required = el.checked
      console.log(valj_efb[indx].id_, document.getElementById(`${valj_efb[indx].id_}_req`))
      document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML = el.checked == true ? '*' : '';
      const aId = {
        email: "_", text: "_", password: "_", tel: "_", url: "_", date: "_", color: "_", range: "_", number: "_", file: "_",
        textarea: "_", dadfile: "_", maps: "-map", checkbox: "_options", radio: "_options", select: "_options",
        multiselect: "_options", esign: "-sig-data", rating: "-stared", yesNo: "_yn"
      }
      postId = aId[valj_efb[indx].type]
      id = valj_efb[indx].id_
      console.log(id, aId)
      document.getElementById(`${id}${postId}`).classList.toggle('requirer')
      //postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
      break;
    case "SendemailEl":
      valj_efb[0].sendEmail = el.checked
      valj_efb[0].email_to = el.dataset.id.replace('-id', '');
      break;
    case "formNameEl":
      valj_efb[0].formName = el.value
      console.log(valj_efb[0])
      break;
    case "trackingCodeEl":
      valj_efb[0].trackingCode = el.checked
      break;
    case "captchaEl":
      valj_efb[0].captcha = el.checked
      break;
    case "showSIconsEl":
      valj_efb[0].show_icon = el.checked
      break;
    case "showSprosiEl":
      valj_efb[0].show_pro_bar = el.checked
      break;
    case "showformLoggedEl":
      valj_efb[0].stateForm = el.checked
      break;
    case "placeholderEl":
      document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).placeholder = el.value;

      valj_efb[indx].placeholder = el.value;
      break;
    case "valueEl":
      if (el.dataset.tag != 'yesNo') {
        document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).value = el.value;
        valj_efb[indx].value = el.value;
      } else {
        //yesNo
        id = `${valj_efb[indx].id_}_${el.dataset.no}`
        document.getElementById(id).value = el.value;
        document.getElementById(`${id}_lab`).innerHTML = el.value;
        el.dataset.no == 1 ? valj_efb[indx].button_1_text = el.value : valj_efb[indx].button_2_text = el.value
        console.log(valj_efb[indx])
        //value="${valj_efb[indx].button_1_text}"
        console.log(el);
      }
      break;
    case "classesEl":
      const v = el.value.replace(` `, `,`);
      //document.querySelector(`[data-id="${idset}"]`).classes=el.value;
      valj_efb[indx].classes = v;
      break;
    case "sizeEl":
      postId = document.getElementById(`${valj_efb[indx].id_}_labG`)
      const op = el.options[el.selectedIndex].value;
      valj_efb[indx].size = op;
      get_position_col_el(valj_efb[indx].dataId, true);
      break;
    case "cornerEl":
      console.log(el.dataset.side)
      const co = el.options[el.selectedIndex].value;
      if (el.dataset.side == "undefined" || el.dataset.side == "") {
        valj_efb[indx].corner = co;
        postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
        let cornEl = document.getElementById(postId);
        console.log(co);
        if (el.dataset.tag == 'select' || el.dataset.tag == 'multiselect') cornEl = el.dataset.tag == 'select' ? document.getElementById(`${postId}options`) : document.getElementById(`${id}ms`)
        //efb-square
        console.log(`data-tag[${el.dataset.tag}]`, `${postId}options`, el.dataset.tag == 'select' || el.dataset.tag == 'multiselect', cornEl);
        console.log(cornEl, cornEl.classList.contains('efb-square'));
        cornEl.classList.toggle('efb-square')
        if (el.dataset.tag == 'dadfile' || el.dataset.tag == 'esign') document.getElementById(`${valj_efb[indx].id_}_b`).classList.toggle('efb-square')


      } else {
        console.log('test', el.options[el.selectedIndex].value)
        valj_efb[0].corner = co;
        postId = document.getElementById('btn_send_efb');
        console.log(postId)
        postId.classList.toggle('efb-square')
        document.getElementById('next_efb').classList.toggle('efb-square')
        document.getElementById('prev_efb').classList.toggle('efb-square')
      }
      break;
    case "labelFontSizeEl":
      //console.log('label_text_sizeEl')
      valj_efb[indx].label_text_size = el.options[el.selectedIndex].value;
      let fontleb = document.getElementById(`${valj_efb[indx].id_}_lab`);
      const sizef = el.options[el.selectedIndex].value
      fontleb.className = fontSizeChangerEfb(fontleb.className, sizef)
      if (el.dataset.tag == "step") { let iconTag = document.getElementById(`${valj_efb[indx].id_}_icon`); iconTag.className = fontSizeChangerEfb(iconTag.className, sizef); }
      console.log(valj_efb[indx], indx)
      break;
    case "fileTypeEl":
      valj_efb[indx].file = el.options[el.selectedIndex].value;
      valj_efb[indx].value = el.options[el.selectedIndex].value;
      document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${valj_efb[indx].file.toUpperCase()}`
      console.log(valj_efb[indx])
      break;
    case "btnColorEl":
      console.log(valj_efb[indx]);
      valj_efb[indx].button_color = el.options[el.selectedIndex].value;
      console.log(clss, el.dataset.tag)
      clss = el.options[el.selectedIndex].value;
      if (indx != 0) {
        if (el.dataset.tag != "yesNo") {
          document.getElementById(`${valj_efb[indx].id_}_b`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b`).className, clss)
        } else {
          document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, clss)
          document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, clss)
        }
      }
      else {
        valj_efb[0].button_color = clss
        document.getElementById(`btn_send_efb`).className = colorBtnChangerEfb(document.getElementById(`btn_send_efb`).className, clss)
        document.getElementById(`next_efb`).className = colorBtnChangerEfb(document.getElementById(`next_efb`).className, clss)
        document.getElementById(`prev_efb`).className = colorBtnChangerEfb(document.getElementById(`prev_efb`).className, clss)
      }
      console.log(valj_efb[indx])
      break;
    case "selectColorEl":
      console.log(valj_efb[indx], el);

      let color = ''
      //valj_efb[indx].label_text_color = el.options[el.selectedIndex].value;
      postId = ''
      if (el.dataset.el == "label") {
        valj_efb[indx].label_text_color = el.options[el.selectedIndex].value;
        postId = valj_efb[indx].type != 'step' ? '_labG' : '_lab'
      }
      else if (el.dataset.el == "description") {
        valj_efb[indx].message_text_color = el.options[el.selectedIndex].value;
        postId = '-des'
      }
      else if (el.dataset.el == "icon") {
        valj_efb[indx].icon_color = el.options[el.selectedIndex].value;
        postId = '_icon'
      } else if (el.dataset.el == "el") {
        valj_efb[indx].el_text_color = el.options[el.selectedIndex].value;
        postId = '_'
      }
      if (el.dataset.tag != "form" &&
        ((el.dataset.tag == "select" && el.dataset.el != "el")
          || (el.dataset.tag == "radio" && el.dataset.el != "el")
          || (el.dataset.tag == "checkbox" && el.dataset.el != "el")
          || (el.dataset.tag == "yesNo" && el.dataset.el != "el")
          || (el.dataset.tag != "yesNo" && el.dataset.tag != "checkbox" && el.dataset.tag != "radio" && el.dataset.tag != "select"))
      ) {
        document.getElementById(`${valj_efb[indx].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}${postId}`).className, el.options[el.selectedIndex].value)
      } else if (el.dataset.tag == "form") {
        if (el.dataset.el != "icon" && el.dataset.el != "el") {
          document.getElementById(`${valj_efb[0].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[0].id_}${postId}`).className, el.options[el.selectedIndex].value)
        } else if (el.dataset.el == "icon") {
          document.getElementById(`button_group_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Next_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Previous_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_icon`).className, el.options[el.selectedIndex].value)
        } else if (el.dataset.el == "el") {
          document.getElementById(`button_group_button_single_text`).className = colorTextChangerEfb(document.getElementById(`button_group_button_single_text`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Next_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_button_text`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Previous_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_button_text`).className, el.options[el.selectedIndex].value)

        }
        //button_group_button_single_text
      } else if (el.dataset.tag == "checkbox" || el.dataset.tag == "radio") {
        const objOptions = valj_efb.filter(obj => {
          return obj.parent === valj_efb[indx].id_
        })
        for (let obj of objOptions) {
          let optin = document.getElementById(`${obj.id_}_lab`);
          optin.className = colorTextChangerEfb(optin.className, el.options[el.selectedIndex].value)
          console.log(optin.className, el.options[el.selectedIndex].value)
        }

        //find list of options by id  from valueJson
        // change color of el by id finded of
      } else if (el.dataset.tag == "select") {
        console.log(valj_efb[indx].id_);
        const objOptions = valj_efb.filter(obj => {
          return obj.parent === valj_efb[indx].id_
        })
        for (let obj of objOptions) {
          try {
            let optin = document.querySelector(`[data-op="${obj.id_op}"]`);
            optin.className = colorTextChangerEfb(optin.className, el.options[el.selectedIndex].value)
            console.log(optin.className, el.options[el.selectedIndex].value, optin.value, obj)
          } catch {
            console.log('catch error')
          }
        }

        //find list of options by id  from valueJson
        // change color of el by id finded of
      } else if (el.dataset.tag == "yesNo") {
        console.log('')
        document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, el.options[el.selectedIndex].value)
        document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, el.options[el.selectedIndex].value)

      }
      console.log(valj_efb[indx], document.getElementById(`${valj_efb[indx].id_}${postId}`), postId)
      break;

    case "selectBorderColorEl":
      console.log(indx, valj_efb[indx].el_border_color, el.options[el.selectedIndex].value)
      valj_efb[indx].el_border_color = el.options[el.selectedIndex].value;
      postId = '_'

      if (el.dataset.tag == "dadfile") { postId = "_box" }
      else if (el.dataset.tag == "multiselect" || el.dataset.tag == "select") { postId = "_options" }
      //console.log('selectBorderColorEl',el.options[el.selectedIndex].value,document.getElementById(`${valj_efb[indx].id_}${postId}`).className)
      // setTimeout(()=>{ document.getElementById('selectBorderColorEl').disabled =true },100)
      //setAtrOfElefb('settingModalEfb-body','Waiting','alert-warning',5000)
      setTimeout(() => {
        const l = el.dataset.tag == "multiselect" ? document.querySelector(`[data-id="${valj_efb[indx].id_}${postId}"]`) : document.getElementById(`${valj_efb[indx].id_}${postId}`);

        //const l = document.getElementById(`${valj_efb[indx].id_}${postId}`);
        const color = colorBorderChangerEfb(l.className, el.options[el.selectedIndex].value);
        l.className = color;
        console.log(color);
      }, 100)
      // document.getElementById('selectBorderColorEl').disabled =false
      //setAtrOfElefb('selectBorderColorEl','Done','alert-info',7000)
      break
    case "selectHeightEl":
      el.dataset.tag == 'form' ? valj_efb[0].el_height = el.options[el.selectedIndex].value : valj_efb[indx].el_height = el.options[el.selectedIndex].value;
      console.log(valj_efb[indx].el_height, el.dataset.tag == "rating", el.dataset.tag);
      let fsize = 'fs-6';
      if (valj_efb[indx].el_height == 'h-l-efb') { fsize = 'fs-5'; }
      else if (valj_efb[indx].el_height == 'h-xl-efb') { fsize = 'fs-4'; }
      else if (valj_efb[indx].el_height == 'h-xxl-efb') { fsize = 'fs-3'; }
      else if (valj_efb[indx].el_height == 'h-xxxl-efb') { fsize = 'fs-2'; }
      else if (valj_efb[indx].el_height == 'h-d-efb') { fsize = 'fs-6'; }
      if (el.dataset.tag == "select") {
        postId = `${valj_efb[indx].id_}_options`
      } else if (el.dataset.tag == "radio" || el.dataset.tag == "checkbox") {
        valj_efb[indx].label_text_size = fsize;
        const objOptions = valj_efb.filter(obj => { return obj.parent === valj_efb[indx].id_ })
        setTimeout(() => {
          for (let obj of objOptions) {
            console.log(`fsize is [${fsize}] height[${valj_efb[indx].el_height}]`)
            valj_efb[indx].el_text_size = fsize;
            document.getElementById(`${obj.id_}_lab`).className = inputHeightChangerEfb(document.getElementById(`${obj.id_}_lab`).className, el.options[el.selectedIndex].value)
            document.getElementById(`${obj.id_}_lab`).className = inputHeightChangerEfb(document.getElementById(`${obj.id_}_lab`).className, fsize)
            document.getElementById(obj.id_).className = inputHeightChangerEfb(document.getElementById(obj.id_).className, fsize)
            console.log(`${document.getElementById(obj.id_).className}`, fsize)
            //document.querySelector(`[data-id="${obj.dataId}"]`).className = fontSizeChangerEfb(document.querySelector(`[data-id='${obj.dataId}']`).className, )
          }
        }, objOptions.length * 2);
        break;

      } else if (el.dataset.tag == "form") {
        postId = `btn_send_efb`;
        document.getElementById(`btn_send_efb`).className = inputHeightChangerEfb(document.getElementById(`btn_send_efb`).className, el.options[el.selectedIndex].value)
        document.getElementById(`next_efb`).className = inputHeightChangerEfb(document.getElementById(`next_efb`).className, el.options[el.selectedIndex].value)
        document.getElementById(`prev_efb`).className = inputHeightChangerEfb(document.getElementById(`prev_efb`).className, el.options[el.selectedIndex].value)
        break;
      } else if (el.dataset.tag == "maps") {
        postId = `${valj_efb[indx].id_}-map`;
      } else if (el.dataset.tag == "dadfile") {
        postId = `${valj_efb[indx].id_}_box`;
      } else if (el.dataset.tag == "multiselect") {
        //h-xxl-efb
        postId = `${valj_efb[indx].id_}_options`;
        let msel = document.querySelector(`[data-id="${postId}"]`)
        console.log(msel, fsize);
        msel.className.match(/h-+\w+-efb/g) ? msel.className = inputHeightChangerEfb(msel.className, valj_efb[indx].el_height) : msel.classList.add(valj_efb[indx].el_height)
        msel.className = fontSizeChangerEfb(msel.className, fsize)
        valj_efb[indx].el_text_size = fsize
      } else if (el.dataset.tag == "rating") {
        postId = valj_efb[indx].id_;
        setTimeout(() => {
          const newClass = inputHeightChangerEfb(document.getElementById(`${postId}_star1`).className, valj_efb[indx].el_height);
          console.log(postId, newClass, document.getElementById(`${postId}_star1`).className, "rating")
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
      } else {
        console.log(el.id, valj_efb[indx].el_text_color)
        postId = `${valj_efb[indx].id_}_`
      }
      setTimeout(() => {
        document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
      }, 10)

      console.log(valj_efb[indx], document.getElementById(`${valj_efb[indx].id_}${postId}`), postId)
      break;
    case 'SingleTextEl':
      let iidd = ""
      if (el.dataset.side == "undefined" || el.dataset.side == "") {
        // iidd = indx !=0 ? `${valj_efb[indx].id_}_icon` : `button_group_icon` ;
        iddd = indx != 0 ? `${valj_efb[indx].id_}_button_single_text` : 'button_group_button_single_text';
        valj_efb[indx].icon = el.value;
      } else {
        console.log(el.dataset.side)
        iidd = el.dataset.side == "Next" ? `button_group_Next_button_text` : `button_group_Previous_button_text`
        el.dataset.side == "Next" ? valj_efb[0].button_Next_text = el.value : valj_efb[0].button_Previous_text = el.value
      }
      console.log(valj_efb[indx], iddd, document.getElementById(iddd));
      document.getElementById(iddd).innerHTML = el.value;
      break;
    case 'iconEl':
      console.log(valj_efb[indx].id_);
      let di = '';
      if (el.dataset.side == "undefined" || el.dataset.side == "") {
        di = indx != 0 ? `${valj_efb[indx].id_}_icon` : `button_group_icon`;
        valj_efb[indx].icon = el.value;
      } else {
        console.log(el.dataset.side)
        di = el.dataset.side == "Next" ? `button_group_Next_icon` : `button_group_Previous_icon`
        el.dataset.side == "Next" ? valj_efb[0].button_Next_icon = el.value : valj_efb[0].button_Previous_icon = el.value
      }
      console.log(valj_efb[indx]);
      document.getElementById(`${di}`).className = `${el.value} me-2`;
      break;
    case 'marksEl':
      valj_efb[indx].mark = parseInt(document.getElementById('marksEl').value);
      console.log(valj_efb[indx]);
      break;
    case 'letEl':
      const lat = parseFloat(el.value);
      const lon = parseFloat(document.getElementById('lonEl').value)
      console.log(lat, lon, typeof lat)
      map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
        center: { lat: lat, lng: lon },
        zoom: 8,
      })
      valj_efb[indx].lat = lat;
      console.log(valj_efb[indx]);
      break;
    case 'lonEl':
      const lonLoc = parseFloat(el.value);
      const letLoc = parseFloat(document.getElementById('letEl').value)
      map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
        center: { lat: letLoc, lng: lonLoc },
        zoom: 8,
      })
      valj_efb[indx].lng = lonLoc;
      console.log(valj_efb[indx]);
      break;
    case 'EditOption':
      el.dataset.id;
      const iindx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
      console.log(el.dataset.id, valj_efb, valj_efb[iindx]);

      if (iindx != -1) {
        valj_efb[iindx].value = el.value;
        if (el.dataset.tag == "select" || el.dataset.tag == "multiselect") {
          //Select
          document.querySelector(`[data-op="${el.dataset.id}"]`).innerHTML = el.value;
          document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
        } else {
          //radio || checkbox
          console.log(el.dataset.id)
          document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
          document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
        }
        el.setAttribute('value', valj_efb[iindx].value);
        el.setAttribute('defaultValue', valj_efb[iindx].value);
      }
      break;
    case "htmlCodeEl":
      console.log('htmlCodeEl');
      postId = valj_efb.findIndex(x => x.id_ == el.dataset.id);
      if (el.value.length > 2) {
        document.getElementById(`${el.dataset.id}_html`).innerHTML = el.value;
        document.getElementById(`${el.dataset.id}_html`).classList.remove('sign-efb')
        valj_efb[postId].value = el.value;
      } else {

        document.getElementById(`${el.dataset.id}_html`).classList.add('sign-efb')
        document.getElementById(`${el.dataset.id}_html`).innerHTML = `
          <div class="efb noCode-efb m-5 text-center" id="${el.dataset.id}_noCode">
          ${efb_var.text.noCodeAddedYet}  <button type="button" class="efb btn btn-edit btn-sm" id="settingElEFb" data-id="${el.dataset.id}-id" data-bs-toggle="tooltip" title="Edit" onclick="show_setting_window_efb('${el.dataset.id}-id')">
          <i class="efb bi-gear-fill text-success"></i></button> ${efb_var.text.andAddingHtmlCode}
          </div>`
        valj_efb[postId].value = '';

      }

      break;
  }
}




function pro_show_efb(state) {
  console.log('pro_Show_efb');
  const message = state == 1 ? efb_var.text.proUnlockMsg : `${efb_var.text.ifYouNeedCreateMoreThan2Steps} ${efb_var.text.proVersion}`;
  const body = `<div class="efb pro-version-efb-modal"><i class="efb bi-gem"></i></div>
  <h5 class="efb txt-center">${message}</h5>
  <div class="efb text-center"><button type="button" class="btn efb btn-primary btn-lg mt-3 mb-3" onClick ="open_whiteStudio_efb('pro')">
    <i class="efb bi-gem me-2"></i>
      ${efb_var.text.activateProVersion}
    </button>
  </div>`
  show_modal_efb(body, efb_var.text.proVersion, '', 'proBpx')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show()
}


const show_modal_efb = (body, title, icon, type) => {
  console.log('show')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  document.getElementById("settingModalEfb-title").innerHTML = title;
  document.getElementById("settingModalEfb-icon").className = icon + ` me-2`;
  document.getElementById("settingModalEfb-body").innerHTML = body
  if (type == "settingBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.contains('modal-new') ? '' : document.getElementById("settingModalEfb").classList.add('modal-new')
  }
  else if (type == "deleteBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    if (!document.getElementById('modalConfirmBtnEfb')) document.getElementById('settingModalEfb-sections').innerHTML += `
    <div class="efb modal-footer" id="modal-footer-efb">
      <button type="button" class="efb btn btn-danger" data-bs-dismiss="modal"id="modalConfirmBtnEfb">
          ${efb_var.text.yes}
      </button> 
    </div>`
    //settingModalEfb-sections
  } else if (type == "saveBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new')
    document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new')
  }
  // myModal.show()
}


function add_option_edit_pro_efb() {
  const el = document.getElementById(`addOption`);
  const id_ob = Math.random().toString(36).substr(2, 9);
  optionElpush_efb(el.dataset.parent, efb_var.text.newOption, id_ob, id_ob);
  console.log(el);
  add_new_option_efb(el.dataset.parent, id_ob, efb_var.text.newOption, id_ob, el.dataset.tag);
}
//delete element
function show_delete_window_efb(idset) {
  // این تابع المان را از صفحه پاک می کند
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  const is_step = document.getElementById(idset) ? document.getElementById(idset).classList.contains('stepNavEfb') : false;
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill me-2', 'deleteBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  if (is_step == false) {
    myModal.show();
    confirmBtn.dataset.id = document.querySelector(`[data-id="${idset}"]`).id;
    confirmBtn.addEventListener("click", (e) => {
      console.log('confirmBtn');
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
      //const step = document.querySelector(`[data-id="${idset}"]`).dataset.step;
      // const amount = document.querySelector(`[data-id="${idset}"]`).dataset.amount;
      // console.log(confirmBtn)
      confirmBtn.addEventListener("click", () => {

        activeEl_efb = 0;
        if (pro_test == false) {
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
  console.log(dataid)
  let step = 0
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1
  console.log(foundIndex, valj_efb[foundIndex]);
  if (foundIndex != -1 && is_step == true) { step = valj_efb[foundIndex].step }
  if (foundIndex != -1) {
    if (valj_efb[foundIndex].type == "maps") {
      document.getElementById('maps').draggable = true;
      document.getElementById('maps_b').classList.remove('disabled')
    } else if (valj_efb[foundIndex].type == 'multiselect' || valj_efb[foundIndex].type == 'select'
      || valj_efb[foundIndex].type == 'radio' || valj_efb[foundIndex].type == 'checkbox') {
      obj_delete_options(valj_efb[foundIndex].id_)
      //  foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1
    } else if (valj_efb[foundIndex].type == 'email' && valj_efb[0].email_to == valj_efb[foundIndex].id_) {
      valj_efb[0].sendEmail = 0
      valj_efb[0].email_to = ''
    }
    console.log(foundIndex, valj_efb[foundIndex]);
    valj_efb.splice(foundIndex, 1);
  }
  if (is_step == true) {
    for (let ob of valj_efb) {
      if (ob.step == step) ob.step = step - 1;
      // console.log(ob.step, step)
    }
  }
  obj_resort_row(step_el_efb);
}
const obj_delete_options = (parentId) => {
  while (valj_efb.findIndex(x => x.parent == parentId) != -1) {
    let indx = valj_efb.findIndex(x => x.parent == parentId);
    console.log(indx)
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

let handleDrag = (item) => {
  const selectedItem = item.target,
    lst = selectedItem.parentNode,
    x = event.clientX,
    y = event.clientY;
  selectedItem.classList.add('drag-sort-active');
  let swapItem = document.elementFromPoint(x, y) === null ? selectedItem : document.elementFromPoint(x, y);
  if (lst === swapItem.parentNode) {
    swapItem = swapItem !== selectedItem.nextSibling && swapItem.dataset == "steps" && swapItem.id != "1" ? swapItem : swapItem.nextSibling;
    if (lst.insertBefore(selectedItem, swapItem)) {
      console.log(selectedItem, swapItem, 47)

    }
  }
}

let handleDrop = (item) => {
  item.target.classList.remove('drag-sort-active');
  sort_obj_el_efb_()
  // sort_obj_efb();
}






const sort_obj_efb = () => {
  console.log('before timeout')
  const len = valj_efb.length;
  let p = calPLenEfb(len)
  setTimeout(() => {
    const valj_efb_ = valj_efb.sort((a, b) => (a.amount > b.amount) ? 1 : ((b.amount > a.amount) ? -1 : 0))
    console.log(valj_efb_, 'timeout');
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
  console.error('------', valj_efb.length)

  for (const el of document.querySelectorAll(".efbField")) {
    // console.log( el.dataset.step ,step ,el);
    if (el.classList.contains('stepNavEfb')) {
      amount = 1;
      step = el.dataset.step;
    } else {
      if (step == 1) {

        //document.getElementById('dropZone').appendChild(el.cloneNode(true))
        console.log(el);
        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id) // این خط خطا دارد
        console.log(valj_efb, el.dataset.id, indx)
        const lastIndx = (valj_efb.length) - 1;
        console.log(valj_efb[indx], valj_efb[lastIndx])
        valj_efb[indx].step = valj_efb[lastIndx].step
        valj_efb[indx].amount = !valj_efb[lastIndx].amount ? 1 : (valj_efb[lastIndx].amount) + 1;
        console.log(valj_efb[indx]);
        //  el.remove();
        state = true;
      } else {
        el.dataset.amount = amount;
        el.dataset.step = step;
        amount = amount + 1;
        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)
        //console.log(indx)
        if (indx != -1) {
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
        }
      }
    }
    const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)
    console.log(indx, valj_efb[indx], amount.step);
  }
  console.log(valj_efb);
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
    // console.log( el.dataset.step ,step ,el);
    amount += 1;

    let indx = valj_efb.findIndex(x => x.id_ === el.id)
    console.log(indx, el.id, el.dataset.step, 'timeout');
    try {
      if (indx != -1) {
        console.log(indx, valj_efb[indx])
        if (el.classList.contains('stepNavEfb')) {
          //اگر استپ بود
          step = el.dataset.step;
          //el.dataset.amount=amount;
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
          //console.log(`If amount[${amount}] step[${step}]`);
        } else {
          //  console.log(`amount[${amount}] step[${step}]`);
          // if not a step
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
          //el.dataset.step =step;
          //el.dataset.amount=amount;
        }
        if (op_state == false && (valj_efb[indx].type == "radio" || valj_efb[indx].type == "checkbox" || valj_efb[indx].type == "select" || valj_efb[indx].type == "multiselect")) {
          console.log('if', 'timeout');
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
  console.log(p_prime, "checkcheck");
  console.log(document.getElementById("optionListefb"), "checkcheck");
  document.getElementById('optionListefb').innerHTML += `
  <div id="${id_ob}-v">
  <input type="text"  value='${value}' data-value="${value}" id="EditOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}"  class="efb col-5 form-control h-d mb-1 elEdit">
  <div class="efb btn-edit-holder" id="deleteOption">
    <button type="button" id="deleteOption" onClick="delete_option_efb('${idin}')"  data-parent="${parentsID}" data-tag="${tag}"  data-id="${idin}-id"  class="efb btn btn-edit btn-sm elEdit " data-bs-toggle="tooltip" title="${efb_var.text.delete}" > 
        <i class="efb bi-x-lg text-danger"></i>
    </button>
    <button type="button" id="addOption" onClick="add_option_edit_pro_efb()" data-parent="${parentsID}" data-tag="${tag}" data-id="${idin}-id"   class="efb btn btn-edit btn-sm elEdit " data-bs-toggle="tooltip"   title="${efb_var.text.add}" > 
        <i class="efb bi-plus-circle  text-success"></i>
    </button> 
  </div>
  </div>`;
  console.log(parentsID, idin, value, id_ob, tag);
  console.log(document.getElementById(`${parentsID}_options`).innerHTML)
  document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(idin, value, id_ob, tag, parentsID);
  //<option value="Three" id="5zfd61k45" data-id="5zfd61k45-id" data-op="emc3db820">dd Three</option>

  for (let el of document.querySelectorAll(`.elEdit`)) {
    el.addEventListener("change", (e) => {
      change_el_edit_Efb(el);

    })
  }
}
const add_new_option_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  console.log(indxP, idin, value, id_ob, tag, parentsID);
  let op = `<!-- option --!> 2`
  if (tag == "select" || tag == "multiselect") {
    op = `<option value="${value}" id="${idin}" data-id="${idin}-id"  data-op="${idin}" class="${valj_efb[indxP].el_text_color} efb">${value}</option>`
  } else {
    op = `<div class="efb form-check" id="${id_ob}-v">
    <input class="efb form-check-input ${valj_efb[indxP].el_text_size}" type="${tag}" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
    <label class="efb ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height} hStyleOpEfb " id="${idin}_lab" for="${idin}">${value}</label>
    </div>`

  }
  console.log(op)
  return op;
}
const delete_option_efb = (id) => {
  //حذف آپشن ها مولتی سلکت و درایو
  // const id = document.getElementById('deleteOption').dataset.id
  console.log(id, document.getElementById(`${id}-v`), 'delete');
  document.getElementById(`${id}-v`).remove();
  document.querySelector(`[data-id="${id}"]`).remove();
  const indx = valj_efb.findIndex(x => x.id_op == id)
  if (indx != -1) { valj_efb.splice(indx, 1); }


}

/* const updateViewEFB = (el) => {
  // از طریق گزینه جدید برای سلکت یا مولتی سلکت یا ردیو باتن یا چک باکس اضافه شود هم در یو آی هم در جی سون
  // اگر در جی سون یا یو آی وجود داشت مقدار آن تغییر کند
  console.log(el.value, el.id, el.dataset.parent, el.dataset.type);
} */


/* new d&D */

//drag and drop form creator  (pure javascript)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
//let valj_efb = [];
function create_dargAndDrop_el() {
  console.log('create_dargAndDrop_el')
  const dropZone = document.getElementById("dropZone");
  dropZone.addEventListener("dragover", (e) => {
    e.preventDefault();
  });
  for (const el of document.querySelectorAll(".draggable-efb")) {

    el.addEventListener("dragstart", (e) => {
      e.dataTransfer.setData("text/plain", el.id)
    });
    // console.log(el.id , "el.id");
  }
  dropZone.addEventListener("drop", (e) => {
    // Add new element to dropZone
    e.preventDefault();
    if (e.dataTransfer.getData("text/plain") !== "step" && e.dataTransfer.getData("text/plain") != null && e.dataTransfer.getData("text/plain") != "") {
      const rndm = Math.random().toString(36).substr(2, 9);
      const t = e.dataTransfer.getData("text/plain");
      console.log(t);
      let el = addNewElement(t, rndm, false, false);
      dropZone.innerHTML += el;
      //show buttons del and setting
      fub_shwBtns_efb();

      if (t == 'maps') {

        const id = `${rndm}-map`;
        //   console.log('map',id,document.getElementById(`${id}`))
        //  console.log(`map`,el.id,id,document.getElementById(id));
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

      } else if (t == "multiselect") {
        jQuery(function () {
          jQuery('.selectpicker').selectpicker();
        });
        setTimeout(() => {
          const v = valj_efb.find(x => x.id_ == rndm);
          const opd = document.querySelector(`[data-id='${rndm}_options']`)
          console.log(v, rndm, `[data-id='${rndm}_options']`, opd);
          opd.className += ` efb ${v.corner} ${v.el_border_color} ${v.el_text_size}`
        }, 15);
      }


      // el = document.getElementById(`${rndm}`);

    }



    enableDragSort('dropZone');
  }); // end drogZone

}


/* 
dropZone.addEventListener("click", (e) => {

}); */

function addNewElement(elementId, rndm, editState, previewSate) {
  //editState == true when form is edit method
  // ایجاد المان
  //console.group('test', elementId)
  let pos = [``, ``, ``, ``]
  const shwBtn = previewSate != true ? 'showBtns' : '';
  // console.log(elementId ,previewSate ,previewSate!=true,shwBtn);
  let indexVJ = editState != false ? valj_efb.findIndex(x => x.id_ == rndm) : 0;
  if (previewSate == true) pos = get_position_col_el(valj_efb[indexVJ].dataId, false)
  console.log(pos, elementId, previewSate, 'pos');
  console.log(`function addNewElement`, elementId);
  amount_el_efb = editState == false ? amount_el_efb + 1 : valj_efb[indexVJ].amount;
  element_name = editState == false ? elementId : valj_efb[indexVJ].name;
  let optn = '<!-- options -->';
  console.log(`step_el_efb[${step_el_efb}] steps[${valj_efb[0].steps}] elemat[${elementId}]`)
  step_el_efb >= 1 && editState == false && elementId == "steps" ? step_el_efb = step_el_efb + 1 : 0;
  if (editState != false) {
    step_el_efb = valj_efb[0].steps;
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    add_buttons_zone_efb(t, 'dropZone')
  }
  console.log(step_el_efb);
  newElement = ``;

  if (step_el_efb == 1) {
    let state = false;

    if (editState == false) {
      state = true;

    }

    if (elementId != 'steps') {
      if (editState == false && valj_efb.length<2) {

        valj_efb.push({
          id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: 'stepNavEfb',
          id: `${step_el_efb}`, name: formName_Efb.toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
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
                <div class="efb btn-edit-holder d-none" id="btnSetting-${step_el_efb}">
                    <button type="button" class="efb btn  btn-edit  btn-sm" id="settingElEFb"
                        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
                        onclick="show_setting_window_efb('${step_el_efb}')">
                        <i class="efb bi-gear-fill text-success"></i>
                    </button>
                </div>
            </div>
        </div>
        `;
      }
      const t = valj_efb[0].steps == 1 ? 0 : 1;
      editState == false ? add_buttons_zone_efb(0, 'dropZone') : add_buttons_zone_efb(t, 'dropZone')

    } else if (elementId == "steps" && step_el_efb == 1 && state == false && editState == false) {

      valj_efb.push({
        id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: 'stepNavEfb',
        id: `${step_el_efb}`, name: formName_Efb.toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
        label_text_size: 'fs-5', message_text_size: 'default', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
        el_text_color: 'text-colorDEfb', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
      });
      add_buttons_zone_efb(0, 'dropZone');

    }

    amount_el_efb += 1;
    editState == false && valj_efb.length>2 ? step_el_efb+=1 :0;
  }
  console.log(step_el_efb, elementId)
  if (editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_test == false && step_el_efb < 3) || pro_test == true)) { sampleElpush_efb(rndm, elementId); }
  console.log(editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_test == false && step_el_efb < 3) || pro_test == true))
  //const idd = editState==false && elementId=="steps" ? `${rndm}` : rndm
  let iVJ = editState == false ? valj_efb.length - 1 : valj_efb.findIndex(x => x.id_ == rndm);
  console.log(iVJ, valj_efb[iVJ])
  let dataTag = 'text'
  const desc = `<small id="${rndm}-des" class="efb form-text d-flex  col-sm-12 efb ${previewSate == true && pos[1] == 'col-md-4' || valj_efb[iVJ].message_align != "justify-content-start" ? `` : `mx-4`}  ${valj_efb[iVJ].message_align}  ${valj_efb[iVJ].message_text_color} ${valj_efb[iVJ].message_text_size != "default" ? valj_efb[iVJ].message_text_size : ''} ">${valj_efb[iVJ].message} </small>`;
  const label = ` <label for="${rndm}_" class="efb ${previewSate == true ? pos[2] : `col-md-2`} col-sm-12 efb col-form-label ${valj_efb[iVJ].label_text_color} ${valj_efb[iVJ].label_align} ${valj_efb[iVJ].label_text_size != "default" ? valj_efb[iVJ].label_text_size : ''} " id="${rndm}_labG""><span id="${rndm}_lab" class="efb ${valj_efb[iVJ].label_text_size}">${valj_efb[iVJ].name}</span><span class=" mx-1" id="${rndm}_req">${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? '*' : ''}</span></label>`
  const rndm_1 = Math.random().toString(36).substr(2, 9);
  const op_3 = Math.random().toString(36).substr(2, 9);
  const op_4 = Math.random().toString(36).substr(2, 9);
  const op_5 = Math.random().toString(36).substr(2, 9);
  let ui = ''
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
      console.log(elementId);
      const classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range'
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12"  id='${rndm}-f'>
        <input type="${elementId}" class="efb input-efb ${classes} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''}  efbField" data-id="${rndm}-el" id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}" value="${valj_efb[iVJ].value}" ${previewSate != true ? 'disabled' : ''}>
        ${desc}`
      dataTag = elementId;
      //  if(editState==false) sampleElpush_efb(rndm, elementId);
      console.log(elementId);
      break;
    case 'maps':
      console.log('maps', `${rndm}-map`)
      ui = `
      ${label}
      <!-- ${rndm}-map -->
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12"  id='${rndm}-f'>      
      ${previewSate == true && valj_efb[iVJ].mark != 0 ? `<div id="floating-panel" class="efb"><input id="delete-markers-efb" class="btn btn-danger" type="button" value="${efb_var.text.deletemarkers}" /></div>` : '<!--notPreview-->'}
        <div id="${rndm}-map" class="efb maps-efb  ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''} " data-id="${rndm}-el" data-name='maps'></div>
        ${desc}`
      dataTag = elementId;



      break;
    case 'file':
      ui = `
       ${label}
        <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12"  id='${rndm}-f'>        
          <input type="${elementId}" class="efb input-efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''}  ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}    form-control efb efbField" data-id="${rndm}-el" id="${rndm}_" placeholder="${elementId}" ${previewSate != true ? 'disabled' : ''}>
          ${desc}`
      dataTag = elementId;

      break;
    case "textarea":
      ui = `
                ${label}
                <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12"  id='${rndm}-f'>
                <textarea  id="${rndm}_"  placeholder="${elementId}"  class="efb input-efb form-control ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color}  efbField" data-id="${rndm}-el"  value="${valj_efb[iVJ].value}" rows="5" ${previewSate != true ? 'disabled' : ''}></textarea>
                ${desc}
            `
      dataTag = "textarea";

      break;
    case 'dadfile':

      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12" id='${rndm}-f'>
      ${desc}
      <div class="efb mb-3" id="uploadFilePreEfb">
                    <label for="${rndm}_" class="efb form-label">
                        <div class="efb dadFile-efb   ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner}   ${valj_efb[iVJ].el_border_color}" id="${rndm}_box">
                          ${ui_dadfile_efb(iVJ, previewSate)}                            
                        </div>
                    </label>
                </div>`

      /* 
      <div class="icon"><i class="efb ${valj_efb[iVJ].icon} ${valj_efb[iVJ].icon_color}"   id="${rndm}_icon"></i></div>
                                <h6>Drag & Drop File Here</h6> <span>OR</span>
                                <button type="button" class="efb btn ${valj_efb[iVJ].button_color} btn-lg" id="${rndm}_b">
                                    <i class="efb bi-upload me-2"></i>Browse File
                                </button>
                                <!--  باید در کد اصلی ای دی هست و در سی اس اس به ای دی داده-->
                                <input type="file" hidden  class="efb " id="${rndm}_" data-id="${rndm}-el" ${previewSate!=true ?'disabled':''}>
      */
      dataTag = elementId;

      break;
    case 'checkbox':
    case 'radio':
      // const rndm_a = Math.random().toString(36).substr(2, 9);
      dataTag = elementId;
      if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        console.log(optns_obj)
        for (const i of optns_obj) {
          optn += `<div class="efb form-check " id="${i.id_}-v">
          <input class="efb form-check-input ${valj_efb[iVJ].el_text_size} " type="${elementId}" name="${i.parent}" value="${i.value}" id="${i.id_}" data-id="${i.id_}-id" data-op="${i.id_}" ${previewSate != true ? 'disabled' : ''}>
          <label class="efb  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].el_height} hStyleOpEfb " id="${i.id_}_lab" for="${i.id_}">${i.value}</label>
          </div>`
        }//end for 

      } else {
        const op_1 = Math.random().toString(36).substr(2, 9);
        const op_2 = Math.random().toString(36).substr(2, 9);
        optn = `
       <div class="efb form-check" id="${op_1}-v">
       <input class="efb form-check-input ${valj_efb[iVJ].el_text_size}" type="${elementId}" name="${valj_efb[iVJ].parent}" value="${elementId}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'disabled' : ''}>
       <label class="efb  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].el_height} hStyleOpEfb " id="${op_1}_lab">${efb_var.text.newOption} 1</label>
       </div>
       <div class="efb form-check" id="${op_2}-v">
           <input class="efb form-check-input  ${valj_efb[iVJ].el_text_size}" type="${elementId}" name="${valj_efb[iVJ].parent}" value="${elementId}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'disabled' : ''}>
           <label class="efb ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].el_height} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>
       </div>`
        optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, op_1, op_1);
        optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, op_2, op_2);
      }
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12"  data-id="${rndm}-el" id='${rndm}-f'>
        <div class="efb ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''} " id="${rndm}_options">
        ${optn}
        </div>
        <div class="efb mb-3">${desc}</div>`


      //
      break;
    case 'switch':

      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12" id ="${rndm}-f">
      <div class="efb form-check form-switch ${valj_efb[iVJ].classes}  ${valj_efb[iVJ].el_height} mb-1" id="${rndm}-switch">
        <input class="efb efb-switch form-check-input efbField" type="checkbox"  data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''}>
      </div>
      <div class="efb mb-3">${desc}</div>
      `
      dataTag = elementId;

      //
      break;
    case 'esign':
      ui = `
      ${label}
      <div class="efb ${previewSate == true ? pos[3] : `col-md-10`} col-sm-12" id ="${rndm}-f">
      <canvas class="efb sign-efb ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color} " data-code="${rndm}"  data-id="${rndm}-el" id="${rndm}_" width="600" height="200">
          ${efb_var.text.updateUrbrowser}
      </canvas>
     ${previewSate == true ? `<input type="hidden" class="efb ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''}" id="${rndm}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">` : ``}
      <div class="efb mx-1">${desc}</div>
      <div class="efb mb-3"><button type="button" class="efb btn ${valj_efb[iVJ].corner} ${valj_efb[iVJ].button_color} btn-lg mt-1" id="${rndm}_b" onClick="fun_clear_esign_efb('${rndm}')">  <i class="efb ${valj_efb[iVJ].icon} me-2 ${valj_efb[iVJ].icon_color != "default" ? valj_efb[iVJ].icon_color : ''} " id="${rndm}_icon"></i><span id="${rndm}_button_single_text" class=" text-white efb ">${valj_efb[iVJ].button_single_text}</span></button></div>
        `
      dataTag = elementId;

      //
      break;
    case 'rating':
      // 
      ui = `
      ${label}
      <div class="efb col-md-10 col-sm-12" id ="${rndm}-f">
      <div class="efb star-efb d-flex justify-content-center">
                        <input type="radio" id="${rndm}-star5"   data-star='star'  name="${rndm}-star-efb" value="5" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star5" for="star5"  ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',5)"` : ''} title="5stars" class="efb ${valj_efb[iVJ].el_height} star-efb">5 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star4"  data-star='star' name="${rndm}-star-efb" value="4" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star4"  for="star4" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',4)"` : ''} title="4stars" class="efb ${valj_efb[iVJ].el_height} star-efb">4 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star3"  data-star='star' name="${rndm}-star-efb" data-name="star" value="3"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star3" for="star3" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',3)"` : ''} title="3stars" class="efb ${valj_efb[iVJ].el_height} star-efb">3 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star2"  data-star='star' data-name="star" name="${rndm}-star-efb" value="2"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star2" for="star2" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',2)"` : ''} title="2stars" class="efb ${valj_efb[iVJ].el_height} star-efb">2 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star1" data-star='star' data-name="star" name="${rndm}-star-efb" value="1"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star1" for="star1" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',1)"` : ''} title="1star" class="efb  ${valj_efb[iVJ].el_height} star-efb">1 ${efb_var.text.star}</label>
      </div>  
      <input type="hidden" class"efb ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''}" id="${rndm}-stared" >   
      <div class="efb mb-3">${desc}</div>
        `
      dataTag = elementId;

      //
      break;
    case "steps":
      dataTag = 'step';

      console.log(valj_efb, step_el_efb);
      let del = ``;

      if (step_el_efb > 1) {
        del = `
          <button type="button" class="efb btn btn-edit btn-sm" id="${valj_efb[iVJ].id_}"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.delete}"
          onclick="show_delete_window_efb('${valj_efb[iVJ].id_}')">
          <i class="efb bi-x-lg text-danger"></i>
          </button>`
      }
      //(step_el_efb <=2|| step_el_efb > 2 ) && pro_test== true
      console.log(step_el_efb)
      if (step_el_efb <= 2 || (step_el_efb > 2 && pro_test == true)) {
        valj_efb[0].steps =editState==false ?  step_el_efb :valj_efb[0].steps 
        newElement += ` 
        <div class="efb row my-2  ${shwBtn} efbField stepNavEfb" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}">
        <h2 class="efb col-md-10 col-sm-12 mx-2 my-0"><i class="efb ${valj_efb[iVJ].icon} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].icon_color} "
        id="${valj_efb[iVJ].id_}_icon"></i> <span id="${valj_efb[iVJ].id_}_lab" class="efb ${valj_efb[iVJ].label_text_size}  ${valj_efb[iVJ].label_text_color}  ">${valj_efb[iVJ].name}</span></span></h2>
        <small id="${valj_efb[iVJ].id_}-des" class="efb form-text ${valj_efb[iVJ].message_text_color} border-bottom px-4">${valj_efb[iVJ].message}</small>
       
        <div class="efb col-md-10 col-sm-12">
        <div class="efb btn-edit-holder d-none" id="btnSetting-${valj_efb[iVJ].id_}">
        <button type="button" class="efb btn btn-edit btn-sm" id="settingElEFb"
        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
        onclick="show_setting_window_efb('${valj_efb[iVJ].id_}')">
        <i class="efb bi-gear-fill text-success"></i>
        </button>
          ${del}
        </div>
        </div>
        </div>`
        console.log(valj_efb[iVJ]);
      } else {
        //اگر نسخه پرو نبود
        // کد زیر بهینه نیست و وقتی هر بار پیام نمایش داده می شود به دارپ زون اضافه می شود که نباید اینگونه باشد
        // باید یک تابع مخصوص نمایش آلرت نوشته شود و فرآخوانی شود هر وقت نیاز بود

        pro_show_efb(2)


      }
      break;
    case 'select':

      if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="${valj_efb[indx_parent].el_text_color} efb">${i.value}</option>`
        }//end for 

      } else {
        optn = `
        <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-id="${op_3}" data-op="${op_3}" class="text-dark efb" >${efb_var.text.newOption} 1</option>
        <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-id="${op_4}" data-op="${op_4}" class="text-dark efb" >${efb_var.text.newOption} 2</option>
       `
        optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
        optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);
        // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
      }
      ui = `
      ${label}
      <div class="${previewSate == true ? pos[3] : `col-md-10`} col-sm-12 efb"  id='${rndm}-f'  data-id="${rndm}-el" >
      <select class="form-select efb ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_border_color}  " id="${rndm}_options" ${previewSate != true ? 'disabled' : ''}>
      <option selected>${efb_var.text.nothingSelected}</option>
      ${optn}
      </select>
      ${desc}
      `
      dataTag = elementId;



      break;
    case 'multiselect':

      dataTag = 'multiselect';


      if (editState != false) {
        // if edit mode
        optn = `<!--opt-->`;
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="${valj_efb[indx_parent].el_text_color} efb">${i.value}</option>`

        }//end for 
        optn += `</ul></div>`
      } else {
        optn = `
        <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-id="${op_3}" data-op="${op_3}" class="${valj_efb[iVJ].el_text_color} efb">${efb_var.text.newOption} 1</option>
        <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-id="${op_4}" data-op="${op_4}" class="${valj_efb[iVJ].el_text_color} efb">${efb_var.text.newOption} 2</option>
       `
        optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
        optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);

      }
      ui = ` 
      ${label}
      <div class="${previewSate == true ? pos[3] : `col-md-10`} col-sm-12" efb  id='${rndm}-f' data-id="${rndm}-el">
      <select class="selectpicker  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} efb" id='${rndm}_options' multiple="" data-live-search="false" tabindex="-98" ${previewSate != true ? 'disabled' : ''}>
      ${optn}
      </select>
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
            <div class="col-sm-12 efb" id="4xruqjnzb-f">
            <div class="boxHtml-efb sign-efb efb" id="${rndm}_html">
            <div class="noCode-efb m-5 text-center efb" id="${rndm}_noCode">
              ${efb_var.text.noCodeAddedYet} <button type="button" class="btn efb btn-edit efb btn-sm" id="settingElEFb"
              data-id="${rndm}-id" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
              onclick="show_setting_window_efb('${rndm}-id')">
              <i class="efb bi-gear-fill text-success"></i></button>${efb_var.text.andAddingHtmlCode}
          </div></div></div> `;
      } else {
        ui = valj_efb[iVJ].value;

        //s
      }
      break;
    case 'yesNo':

      dataTag = elementId;
      console.log('YesNo')
      ui = `
      ${label}
      <div class="col-md-10 col-sm-12 efb"  id='${rndm}-f'>
      <div class="btn-group  btn-group-toggle w-100  col-md-12 col-sm-12 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'requirer' : ''}" data-toggle="buttons" data-id="${rndm}-id" id="${rndm}_yn">    
      <label class="btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb  ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_1">
        <input type="radio" name="options" class="opButtonEfb elEdit " data-id="${rndm}-id" id="${rndm}_1" value="${valj_efb[iVJ].button_1_text}"><span id="${rndm}_1_lab">${valj_efb[iVJ].button_1_text}</span></label>
      <span class="border-right border border-light efb"></span>
      <label class="btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].corner}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb   ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_2">
        <input type="radio" name="options" class="opButtonEfb elEdit efb" data-id="${rndm}-id" id="${rndm}_2" value="${valj_efb[iVJ].button_2_text}"> <span id="${rndm}_2_lab">${valj_efb[iVJ].button_2_text}</span></label>
    </div>
        ${desc}`


      break;
    case 'booking':
      dataTag = elementId;
      break;



  }

  if (elementId != "form" && dataTag != "step" && ((previewSate == true && elementId != 'option') || previewSate != true)) {
    const pro_el = (dataTag == "multiselect" || dataTag == "dadfile" || dataTag == "url" || dataTag == "switch" || dataTag == "rating" || dataTag == "esign" || dataTag == "maps" || dataTag == "date" || dataTag == "color" || dataTag == "html" || dataTag == "tel" || dataTag == "range" || dataTag == "yesNo") ? true : false;
    console.log(dataTag, `${pro_test == false && pro_el}`);
    const contorl = ` <div class="btn-edit-holder d-none efb" id="btnSetting-${rndm}-id">
    <button type="button" class="efb btn btn-edit btn-sm" id="settingElEFb"  data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.edit}" onclick="show_setting_window_efb('${rndm}-id')">
    <i class="efb bi-gear-fill text-success"></i>
    </button>
    <!--<button type="button" class="efb btn btn-edit btn-sm" id="dupElEFb" data-id="${rndm}-id"  data-bs-toggle="tooltip"  title="${efb_var.text.duplicate}" onclick="show_duplicate_fun('${rndm}-id')">
    <i class="efb bi-files text-warning"></i> -->
    </button>
    <button type="button" class="efb btn btn-edit btn-sm" id="deleteElEFb"   data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.delete}" onclick="show_delete_window_efb('${rndm}-id')"> 
    <i class="efb bi-x-lg text-danger"></i>`

    const proActiv = `⭐ 
    <div class="btn-edit-holder d-none zindex-10-efb " id="btnSetting-${rndm}-id">
    <button type="button" class="btn efb btn-pro-efb btn-sm px-2 mx-3" id="pro"   data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.proVersion}" onclick="pro_show_efb(1)"> 
    <i class="efb bi-gem text-dark"> ${efb_var.text.availableProVersion}</i>`;

    newElement += `
    <div class=" my-2  ${shwBtn} ${previewSate == true ? `${pos[0]} ${pos[1]}` : `col-md-10 row`} col-sm-12   efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${elementId}"  >
    ${(previewSate == true && elementId != 'option') || previewSate != true ? ui : ''}
    ${previewSate != true && pro_test == false && pro_el ? proActiv : ''}
    ${previewSate != true ? contorl : '<!--efb.app-->'}
    ${previewSate != true && pro_test == false && pro_el ? '</div>' : ''}
    ${(previewSate == true && elementId != 'option') || previewSate != true ? ` </button> </button> </div></div>` : '</div>'}
   
    
    
    `

    console.log(dataTag);
  } else if (dataTag == 'step' && previewSate != true) {
    if (elementId == "steps" && pro_test == false && step_el_efb == 3) {
      amount_el_efb = amount_el_efb - 1;
      step_el_efb = 2;
      valj_efb[0].steps = 2
    } else {
      console.log(valj_efb[0].steps);
      valj_efb[0].steps = step_el_efb;
      console.log(valj_efb[0].steps);
    }
    console.log(valj_efb[0]);
    if (!document.getElementById('button_group')) {
      add_buttons_zone_efb(0, 'dropZone')
      fub_shwBtns_efb();
    } else if (valj_efb[0].steps > 1) {
      fun_handle_buttons_efb(true)
      fub_shwBtns_efb();
    }
  }
  console.log(valj_efb)


  return newElement;
}
//id,id_,value

/* new d&D */

const funSetPosElEfb = (dataId, position) => {
  console.log(dataId, position, "pos")
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx != -1) {
    valj_efb[indx].label_position = position
  }

  get_position_col_el(dataId, true)

}
const funSetAlignElEfb = (dataId, align, element) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  console.log(dataId, align, element, indx)
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
      console.log(elm.className, align);
      break;
  }
}//justify-content-center

const funSetCornerElEfb = (dataId, co) => {
  //efb-square
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el = document.querySelector(`[data-id='${dataId}-set']`)

  console.log(dataId, co, indx, el)
  //const co = el.options[el.selectedIndex].value;
  if (el.dataset.side == "undefined" || el.dataset.side == "") {
    valj_efb[indx].corner = co;
    postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
    let cornEl = document.getElementById(postId);
    if (el.dataset.tag == 'select' || el.dataset.tag == 'multiselect') cornEl = el.dataset.tag == 'select' ? document.getElementById(`${postId}options`) : document.querySelector(`[data-id="${postId}options"]`)
    //efb-square
    console.log(`data-tag[${el.dataset.tag}]`, `${postId} `, el.dataset.tag, cornEl);
    console.log(cornEl);

    if (el.dataset.tag == 'esign') cornEl = document.getElementById(`${valj_efb[indx].id_}_b`)
    else if (el.dataset.tag == 'dadfile') cornEl = document.getElementById(`${valj_efb[indx].id_}_box`)
    cornEl.className = cornerChangerEfb(cornEl.className, co)

  } else if (el.dataset.side == "yesNo") {
    valj_efb[indx].corner = co;
    console.log('YesNo')
    document.getElementById(`${valj_efb[indx].id_}_b_1`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, co)
    document.getElementById(`${valj_efb[indx].id_}_b_2`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, co)
  } else {

    valj_efb[0].corner = co;
    postId = document.getElementById('btn_send_efb');
    console.log(postId)
    postId.classList.toggle('efb-square')
    postId.className = cornerChangerEfb(postId.className, co)
    document.getElementById('next_efb').className = cornerChangerEfb(document.getElementById('next_efb').className, co)
    document.getElementById('prev_efb').className = cornerChangerEfb(document.getElementById('prev_efb').className, co)
  }
  console.log(valj_efb[indx]);
}

let get_position_col_el = (dataId, state) => {

  //show loading before run code
  console.log(dataId, valj_efb, "post")
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el_parent = document.getElementById(valj_efb[indx].id_);
  let el_label = document.getElementById(`${valj_efb[indx].id_}_labG`)
  let el_input = document.getElementById(`${valj_efb[indx].id_}-f`)
  console.log(el_input, el_label, el_parent, "post");
  let parent_col = ``;
  let label_col = `col-md-12`;
  let input_col = `col-md-12`;
  let parent_row = '';
  switch (valj_efb[indx].size) {
    case 100:
    case '100':
      parent_col = 'col-md-12';
      label_col = `col-md-2`;
      input_col = `col-md-10`;
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
      el_parent && el_parent.classList.contains('row') ? 0 : el_parent.classList.add('row')
      if (el_input.classList.contains('mx-2')) el_input.classList.remove('mx-2');
      if (el_label.classList.contains('mx-2')) el_label.classList.remove('mx-2');
    }
  }
  console.log('input', `${valj_efb[indx].id_}-f`, el_input);
  console.log('label', el_label);
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
         <h5 class="modal-title efb" ><i class="bi-ui-checks me-2 efb" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${title ? title : efb_var.text.loading} </span></h5>
     </div>
     <div class="modal-body efb" id="settingModalEfb-body">
         ${loading_messge_efb()}
     </div>
 </div>
</div>`
}
let sampleElpush_efb = (rndm, elementId) => {
  console.log(elementId ,efb_var.text)
  let pro = false;
  if (elementId == "multiselect" || elementId == "dadfile" || elementId == "url" || elementId == "switch" || elementId == "rating" || elementId == "esign" || elementId == "maps" || elementId == "date" || elementId == "color" || elementId == "html" || elementId == "tel" || elementId == "range" || elementId == "yesNo") { pro = true }
  console.log(elementId, "push");
  const txt_color = elementId != "yesNo" ? 'text-labelEfb' : "text-white"
  if (elementId != "file" && elementId != "dadfile" && elementId != "html" && elementId != "steps") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, placeholder: efb_var.text[elementId], value: '', size: 80, message: efb_var.text.sampleDescription,
      id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb, corner: 'efb-rounded', label_text_size: 'fs-6',
      label_position: 'beside', message_text_size: 'default', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d',
      el_text_color: txt_color, message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start',
      el_align: 'justify-content-start', pro: pro
    })

    /*     if (elementId == "email") {
          Object.assign(valj_efb[(valj_efb.length) - 1], { sendEmail: 0 })
        } else  */
    if (elementId == "esign") {

      Object.assign(valj_efb[(valj_efb.length) - 1], {
        icon: 'bi-save', icon_color: "default", button_single_text: efb_var.text.clear,
        button_color: 'btn-danger'
      })
      //icon: ''
      console.log(valj_efb[(valj_efb.length) - 1]);
    } else if (elementId == "yesNo") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { button_1_text: efb_var.text.yes, button_2_text: efb_var.text.no, button_color: 'btn-primary' })
      console.log(valj_efb[(valj_efb.length) - 1]);
    } else if (elementId == "maps") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { lat: 49.24803870604257, lng: -123.10512829684463, mark: 1 });
      document.getElementById('maps').draggable = false;
      document.getElementById('maps_b').classList.add('disabled')
    }
  } else if (elementId == "html") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, value: '', pro: pro
    })
    console.log(valj_efb);
  } else if (elementId == "steps") {
    step_el_efb = step_el_efb == 0 ? 1 : step_el_efb;
    valj_efb.push({
      id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: 'stepNavEfb',
      id: `${step_el_efb}`, name: formName_Efb.toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
      label_text_size: 'fs-5', message_text_size: 'default', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
      el_text_color: 'text-colorDEfb', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
    });

  } else {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, placeholder: elementId, value: 'document', size: 80,
      message: efb_var.text.sampleDescription, id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,
      corner: 'efb-rounded', label_text_size: 'fs-6', message_text_size: 'fs-7', el_text_size: 'fs-6', file: 'document',
      label_text_color: 'text-labelEfb', label_position: 'beside', el_text_color: 'text-colorDEfb', message_text_color: 'text-muted', el_height: 'h-d-efb',
      label_align: 'txt-left', message_align: 'justify-content-start', el_border_color: 'border-d',
      el_align: 'justify-content-start', pro: pro
    })
    if (elementId == "dadfile") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { icon: 'bi-cloud-arrow-up-fill', icon_color: "text-pinkEfb", button_color: 'btn-primary' })
      //icon_color: 'default'
    }


  }

}
let optionElpush_efb = (parent, value, rndm, op) => {

  valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });
  console.log(valj_efb);
}


function obj_resort_row(step) {
  // ترتیب را مرتب می کند بعد از پاک شدن یک استپ
  // const newStep = step - 1;
  for (v of valj_efb) {
    if (v.step == step) {
      v.step = step;
      if (v.dataId) {
        //document.querySelector(`[data-id="${v.dataId}"]`).dataset.step = step;
        console.log(v)
        if (document.getElementById(v.id_)) document.getElementById(v.id_).dataset.step = step;
      }
    }
  }
  /*   if(pro_test==false) step_el_efb = step_el_efb-1;
    valj_efb[0].steps = valj_efb[0].steps-1; */
  fub_shwBtns_efb()
  if (valj_efb[0].steps == 1) fun_handle_buttons_efb(false);
}

let fun_handle_buttons_efb = (state) => {
  //d-none
  setTimeout(() => {
    console.log('fun_handle_buttons_efb', state)
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
  console.log(state, id, state == 0 ? 'd-block' : 'd-none', 'previewMobile')


  const stng = `  <div class="col-sm-10 efb">
  <div class="btn-edit-holder d-none efb" id="btnSetting-button_group">
      <button type="button" class="btn efb btn-edit efb btn-sm" id="settingElEFb"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
          onclick="show_setting_window_efb('button_group')">
          <i class="efb bi-gear-fill text-success"></i>
      </button>
  </div>
  </div>`;
  const floatEnd = id == "dropZone" ? 'float-end' : ``;
  const btnPos = id != "dropZone" ? ' text-center' : ''
  const s = `
  <div class="${state == 0 ? 'd-block' : 'd-none'} ${btnPos} efb" id="f_btn_send_efb" data-tag="buttonNav">
    <button id="btn_send_efb" type="button" class="btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner} ${valj_efb[0].el_height}  btn-lg ${floatEnd}"><i class="efb ${valj_efb[0].icon}  me-2 ${valj_efb[0].icon_color}  " id="button_group_icon"></i><span id="button_group_button_single_text" class=" ${valj_efb[0].el_text_color} ">${valj_efb[0].button_single_text}</span</button>
  </div>`
  const d = `
  <div class="${state == 1 ? 'd-block' : 'd-none'} ${btnPos} efb" id="f_button_form_np">
  <button id="prev_efb" type="button" class="btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}   btn-lg ${floatEnd} m-1"><i class="efb ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color}  " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class=" ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button>
  <button id="next_efb" type="button" class="btn efb ${valj_efb[0].button_color}  ${valj_efb[0].corner}  ${valj_efb[0].el_height}    btn-lg ${floatEnd} m-1"><span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Next_text}</span> <i class="efb ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}  " id="button_group_Next_icon"></i></button>
  </div>
  `
  let c = `<div class="test mt-5 efb">`
  if (id != "dropZone") {
    c += state == 0 ? `${s}</div>` : `${d}</div> <!-- end btn -->`
  } else {
    c = `<div class="position-absolute row bottom-0 mx-5 translate-middle-x  showBtns m-3 efb" id="button_group" data-id="button_group" data-tag="buttonNav"> ${s} ${d} ${stng} </div>`
  }
  if (id != 'preview') { document.getElementById(id).innerHTML += c } else {
    return c;
  }
}



const colorTextChangerEfb = (classes, color) => { return classes.replace(/(text-primary|text-darkb|text-muted|text-secondary|text-pinkEfb|text-success|text-white|text-light|text-colorDEfb|text-danger|text-warning|text-info|text-dark|text-labelEfb)/, `${color}`); }
const colorBtnChangerEfb = (classes, color) => { return classes.replace(/(btn-select-form|btn-primary|btn-muted|btn-secondary|btn-pinkEfb|btn-success|btn-white|btn-light|text-colorDEfb|btn-danger|btn-warning|btn-info|btn-dark|btn-default)/, `${color}`); }
const colorBorderChangerEfb = (classes, color) => { return classes.replace(/\bborder+-\w+/gi, `${color}`); }
const inputHeightChangerEfb = (classes, value) => { return classes.replace(/(h-d-efb|h-l-efb|h-xl-efb|h-xxl-efb|h-xxxl-efb)/, `${value}`); }
const fontSizeChangerEfb = (classes, value) => { return classes.replace(/(fs-7|fs-5|fs-4|fs-3|fs-6)/, `${value}`); }
const cornerChangerEfb = (classes, value) => { return classes.replace(/(efb-square|efb-rounded)/, `${value}`); }
const colChangerEfb = (classes, value) => { return classes.replace(/\bcol-\d+|\bcol-\w+-\d+/, `${value}`); }
const colMdChangerEfb = (classes, value) => { return classes.replace(/\bcol-md+-\d+/, `${value}`); }
const colMdRemoveEfb = (classes) => { return classes.replace(/\bcol-md+-\d+/gi, ``); }
const colSmChangerEfb = (classes, value) => { return classes.replace(/\bcol-sm+-\d+/, `${value}`); }
const RemoveTextOColorEfb = (classes) => { return classes.replace('text-', ``); }
const alignChangerEfb = (classes, value) => { return classes.replace(/(txt-left|txt-right|txt-center)/, `${value}`); }
const alignChangerElEfb = (classes, value) => { return classes.replace(/(justify-content-start|justify-content-end|justify-content-center)/, `${value}`); }

const open_whiteStudio_efb = (state) => {
  console.log('open_whiteStudio_efb')
  let link = `https://whitestudio.team/`
  switch (state) {
    case 'mapErorr':
      link += `?help`
      // چگونه کی گوگل مپ اضافه کنیم
      break;
    case 'pro':
      link = `?pro`
      break;
    case 'publishForm':
      link += `?publish-form`
      // چگونه فرم را  منتشر کنیم
      // how create and publish form
      break;
    case 'emptyStep':
      link += `?empty-step`
      // پیام استپ خالی چیست و چگونه برطرف شود
      break;
    case 'notInput':
      link += `?notInputExists`
      // پیام ورودی وجود ندارد چیست و چگونه برطرف شود
      break;
    case 'pickupByUser':
      link += `?pickupByUser`
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
  let dropZone = document.getElementById('dropZone');
  dropZone.innerHTML = loading_messge_efb();
  if (localStorage.getItem("valj_efb")) { valj_efb = JSON.parse(localStorage.getItem("valj_efb")); } // test code => replace from value
  const len = valj_efb.length | 10;


  setTimeout(() => {
    dropZone.innerHTML = "<!-- edit efb -->"
    for (let v in valj_efb) {

      try {
        if (valj_efb[v].type != "option") {
          const type = valj_efb[v].type == "step" ? "steps" : valj_efb[v].type;
          let el = addNewElement(type, valj_efb[v].id_, true, false);
          console.log(`type [${valj_efb[v].type}] amount[${valj_efb[v].amount}] step[${valj_efb[v].step}]`)
          dropZone.innerHTML += el;

          if (valj_efb[v].type != "form" && valj_efb[v].type != "step") funSetPosElEfb(valj_efb[v].dataId, valj_efb[v].label_position)

          if (type == 'maps') {
            setTimeout(() => {
              const lat = valj_efb[v].lat;
              const lon = valj_efb[v].lng;
              map = new google.maps.Map(document.getElementById(`${valj_efb[v].id_}-map`), {
                center: { lat: lat, lng: lon },
                zoom: 8,
              })
            }, (len * 20) + 10);
          } else if (type == "multiselect") {
            jQuery(function () {
              jQuery('.selectpicker').selectpicker();
            });
            setTimeout(() => {
              // const v = valj_efb.find(x=>x.id_==rndm);
              const opd = document.querySelector(`[data-id='${valj_efb[v].id_}_options']`)
              opd.className += ` efb ${valj_efb[v].corner} ${valj_efb[v].el_border_color} ${valj_efb[v].el_height} ${valj_efb[v].el_text_size}`
            }, 15);

            //document.querySelector(`[data-id='${valj_efb[v].id_}_options']`).className += `efb ${valj_efb[v].corner} ${valj_efb[v].el_border_color} ${valj_efb[v].el_text_size}`
          }
          // console.log(dropZone.innerHTML)
        }
      } catch (err) {
        console.error('Error', err)
      }
    }

    fub_shwBtns_efb()
    enableDragSort('dropZone');
  }, (len * 20));

 
}//editFormEfb end

const saveFormEfb = () => {
  console.log('saveFormEfb function')

  let proState = true;
  let stepState = true;
  document.getElementById(`settingModalEfb`).innerHTML = loadingShow_efb('Save');

  setTimeout(()=>{

  
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show();
  try {
    
    let body = ``;
    let btnText = ``;
    let btnFun = ``
    let message = ``;
    let state = false
    let title = efb_var.text.error;
    let icon = `bi-exclamation-triangle-fill`
    let box = `error`
    let btnIcon = `bi-question-lg`
    console.log('saveFormEfb function [forEND]')
    if (valj_efb.length < 3) {
      console.log('not element added')
      btnText = efb_var.text.help
      btnFun = `open_whiteStudio_efb('notInput')`
      message = efb_var.text.youDoNotAddAnyInput
      icon =""

    } else {
      if (pro_test == false) { proState = valj_efb.findIndex(x => x.pro == true) != -1 ? false : true }
      for (let s = 1; s <= valj_efb[0].steps; s++) {
        const stp = valj_efb.findIndex(x => x.step == s && x.type != "step");
        console.log(stepState, 'stepState', s, valj_efb.findIndex(x => x.step == s && x.type != "step"), stp, "while")
        if (stp == -1) {
          console.log('a step is empty')
          stepState = false;
          break;
        }
      }
    }
    if (valj_efb.length > 2 && proState == true && stepState == true) {
      title = efb_var.text.save
      box = `saveBox`
      icon = `bi-check2-circle`
      state = true;

      // valj_efb = valj_efb.sort((a,b) => (a.amount - b.amount))

      localStorage.setItem('valj_efb', JSON.stringify(valj_efb));
      
      localStorage.setItem("valueJson_ws_p",JSON.stringify(valj_efb))
      actionSendData_emsFormBuilder()

    } else if (proState == false) {
      console.log('added pro elements');
      btnText = efb_var.text.activateProVersion
      btnFun = `open_whiteStudio_efb('pro')`
      message = efb_var.text.youUseProElements
      title = efb_var.text.proVersion
      icon = 'bi-gem'
      btnIcon= icon;
    } else if (stepState == false) {

      btnText = efb_var.text.help
      btnFun = `open_whiteStudio_efb('emptyStep')`
      message = efb_var.text.itAppearedStepsEmpty

    }
    console.log(`valj_efb[${valj_efb.length}]`)
    if (state == false) {
      btn = `<button type="button" class="btn efb btn-outline-pink btn-lg mt-3 mb-3 text-capitalize" onClick ="${btnFun}">
      <i class="efb ${btnIcon} me-2"></i> ${btnText} </button>`
      body = `
      <div class="pro-version-efb-modal efb"></div>
        <h5 class="efb txt-center text-darkb fs-6 text-capitalize">${message}</h5>
        <div class="efb text-center text-capitalize">
        ${btn}
        </div>
      `
    }
    show_modal_efb(body, title, icon, box)
    myModal.show();
  } catch(error) {
    btnIcon ='bi-bug'
    body =`
    <div class="pro-version-efb-modal efb"></div>
    <h5 class="efb txt-center text-darkb fs-6 text-capitalize">${efb_var.text.pleaseReporProblem}</h5>
    <div class="efb text-center text-capitalize">
    <button type="button" class="btn efb btn-outline-pink btn-lg mt-3 mb-3 text-capitalize" onClick ="fun_report_error('fun_saveFormEfb','${error}')">
      <i class="efb bi-megaphone me-2"></i> ${efb_var.text.reportProblem} </button>
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
  <div class="smartphone">
  <div class="content" >
      <div id="parentMobileView">
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
    document.getElementById('parentMobileView').innerHTML = `
       <div class="text-center pt-5 text-darkb efb"> 
       <div class="bi-emoji-frown fs-4 efb"></div><p class="fs-5 efb">${efb_var.text.formNotFound}</p></div>
      </div>
  `
  }
  document.getElementById('dropZone').innerHTML = '';
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



  //  setTimeout(() => {
  //const  valj_efb_ = valj_efb.sort((a,b) => (a.amount - b.amount))
  console.log(valj_efb)
  //valj_efb=valj_efb_
  try {
    valj_efb.forEach((value, index) => {
      console.log(index, value.step, value.amount, 'pre')
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb  ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="my-2  steps-efb efb row ">` : `</fieldset><fieldset data-step="step-${step_no}-efb"  class="my-2 steps-efb efb row d-none">`
        console.log(step_no, value.step, head, 'pre');

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
    const trckCd = `
       <div class="test"><h5 class="mt-3">${efb_var.text.trackingCode}: <strong>210201-SMHTH06</strong></h5>
                    <input type="text" class="hide-input" value="210201-SMHTH06" id="trackingCodeEfb">
                    <div id="alert"></div>
                    <button type="button" class="btn efb btn-primary btn-lg my-3" onclick="copyCodeEfb('trackingCodeEfb')">
                        <i class="efb bi-clipboard-check me-2"></i>${efb_var.text.copyTrackingcode}
                    </button></div>`
    content += `</fieldset><fieldset data-step="step-${step_no}-efb" class="my-2 steps-efb efb row d-none text-center">
                    <h4 class="efb my-1">
                        <i class="efb bi-hand-thumbs-up title-icon me-2"></i>${efb_var.text.done}
                    </h4>
                    <h3 class="efb">${efb_var.text.thanksFillingOutform}</h3>
                   ${valj_efb[0].trackingCode == true ? trckCd : '</br>'}
                    
                    
                
      </fieldset>`
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch {
    console.error(`Preview of Pc Form has an Error`)
  }

  if (content.length > 10) content += `</div>`

  console.log(head);

  head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="progress mx-5"><div class="efb progress-bar-efb  btn-${RemoveTextOColorEfb(valj_efb[1].label_text_color)} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div> <br> ` : ``}`



  document.getElementById(`settingModalEfb_`).classList.add('pre-efb')
  content = `  
    <div class="px-0 pt-2 pb-0 my-1 col-12 text-center" id="view-efb">
    <h4 id="title_efb" class="${valj_efb[1].label_text_color}">${valj_efb[1].name}</h4>
    <p id="desc_efb" class="${valj_efb[1].message_text_color}">${valj_efb[1].message}</p>
    
     <form id="msform"> ${head} <div class="my-5 py-2 px-5">${content}</div> </form>
    </div>
    `
  return content
}// end function


function fun_renderform_Efb() {
  try {
    valj_efb.forEach((v, i) => {
      console.log(v, i)
      switch (v.type) {
        case "maps":
          initMap();
          break;
        case "esign":
          console.log('CANVAS', v.id_)

          c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
          c2d_contex_efb.lineWidth = 5;
          c2d_contex_efb.strokeStyle = "#000";

          document.getElementById(`${v.id_}_`).addEventListener("mousedown", (e) => {
            draw_mouse_efb = true;
            c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
            canvas_id_efb = v.id_;
            lastMousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e);
            console.log(canvas_id_efb, 'canvas')
          }, false);

          document.getElementById(`${v.id_}_`).addEventListener("mouseup", (e) => {
            draw_mouse_efb = false;
            const value = document.getElementById(`${v.id_}-sig-data`).value;
            console.log(value);
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
          jQuery(function () {
            jQuery('.selectpicker').selectpicker();
          });
          setTimeout(() => {
            //const v = valj_efb.find(x=>x.id_==rndm);
            const opd = document.querySelector(`[data-id='${v.id_}_options']`)
            console.log(v, `"timeout" ${v.corner} ${v.el_border_color} ${v.el_text_size}`, opd)
            opd.className += ` efb ${v.corner} ${v.el_border_color} ${v.el_text_size} ${v.el_height}`
          }, 350);
          // document.querySelector(`[data-id='${v.id_}_options']`).className += `efb ${v.corner} ${v.el_border_color} ${v.el_text_size}`
          break;
        case "rating":
          /*    const rate_efbs = document.querySelector(` [data-id='${v.id_}-el']`)
             for(let rate_efb of rate_efbs){
              // console.log(rate_efb.value, v ,rate_efb);
               rate_efb.addEventListener("click", (e)=> {
                   console.log(rate_efb.value, v.id_ ,rate_efb);
                     document.getElementById(`${v.id_}-stared`).innerHTML = rate_efb.value;
                 })
             }  */
          break;
        case "dadfile":
          set_dadfile_fun_efb(v.id_, i)
          break;

      }
    })
    console.log("testtest")
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
      console.log()
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
      //document.getElementById('dropZone').innerHTML = editFormEfb()
      jQuery('#dropZone').empty().append(editFormEfb());
      jQuery('#settingModalEfb_').removeClass('pre-efb')
      console.log(`pre-view`);
      //fub_shwBtns_efb()
    }
    if (jQuery('#modal-footer-efb')) {
      jQuery('#modal-footer-efb').remove()
    }

  });
});




/* map section */

let map;
let markers = [];
let mark_efb = []
//document.addEventListener('ondomready', function(){
function initMap() {
  console.log('initMap');

  setTimeout(function () {
    // code to be executed after 1 second
    const idx = valj_efb.findIndex(x => x.type == "maps")
    // lat: 49.24803870604257, lon: -123.10512829684463
    const lat = idx != -1 && valj_efb[idx].lat ? valj_efb[idx].lat : 49.24803870604257;
    const lon = idx != -1 && valj_efb[idx].lng ? valj_efb[idx].lng : -123.10512829684463;
    const mark = idx != -1 ? valj_efb[idx].mark : 1;
    console.log('test map function', google, document.getElementById("map"))
    const location = { lat: lat, lng: lon };

    map = new google.maps.Map(document.getElementById(`${valj_efb[idx].id_}-map`), {
      zoom: 8,
      center: location,
      mapTypeId: "roadmap",
    });
    console.log('map function');
    // This event listener will call addMarker() when the map is clicked.
    if (mark != 0) {
      map.addListener("click", (event) => {
        const latlng = event.latLng.toJSON();
        mark_efb.push(latlng);
        if (mark_efb.length <= mark) {
          addMarker(event.latLng);
        }
      });
      // add event listeners for the buttons


      document
        .getElementById("delete-markers-efb")
        .addEventListener("click", deleteMarkers_efb);
      // Adds a marker at the center of the map.
    } else {
      addMarker(location);
    }
  }, 1000);
}

//});
//console.log('testinit')

// Adds a marker to the map and push to the array.

function addMarker(position) {
  const lab_map_efb = "0ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  const idx = valj_efb.findIndex(x => x.type == "maps")
  const idxm = (mark_efb.length)
  console.log(mark_efb.length)
  const lab = idx !== -1 && valj_efb[idx].mark < 2 ? '' : lab_map_efb[idxm % lab_map_efb.length];
  //idx!==-1 && valj_efb[idx].mark ? '' :
  const marker = new google.maps.Marker({
    position,
    label: lab,
    map,
  });

  markers.push(marker);

}

// Sets the map on all markers in the array.
function setMapOnAll(map) {
  for (let i = 0; i < markers.length; i++) {
    markers[i].setMap(map);
  }
}

// Removes the markers from the map, but keeps them in the array.
function hideMarkers() {
  setMapOnAll(null);
}

// Shows any markers currently in the array.
function showMarkers() {
  setMapOnAll(map);
}

// Deletes all markers in the array by removing references to them.
function deleteMarkers_efb() {
  hideMarkers();
  markers = [];
  mark_efb = [];
}
/* map section end */

function fun_get_rating_efb(v, no) {
  document.getElementById(`${v}-stared`).value = no;
  document.getElementById(`${v}-star${no}`).checked = true;
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
  c2d.strokeStyle = "#000";
  c2d.save();
  const o = [{ id_: id }];

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
  // console.log(canvas_id_efb,'canvas')
  if (draw_mouse_efb) {
    console.log(canvas_id_efb)
    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);
    c2d_contex_efb.lineTo(mousePostion_efb.x, mousePostion_efb.y);
    c2d_contex_efb.stroke();
    lastMousePostion_efb = mousePostion_efb;

    const data = document.getElementById(`${canvas_id_efb}_`).toDataURL();
    // console.log(data)
    document.getElementById(`${canvas_id_efb}-sig-data`).value = data;
    // image.setAttribute("src", data);
  }
}



/* clear esignature function  end*/


set_dadfile_fun_efb = (id, indx) => {
  setTimeout(() => { create_dadfile_efb(id, indx) }, 50)
}


create_dadfile_efb = (id, indx) => {
  console.log('create_dadfile_efb');
  const dropAreaEfb = document.getElementById(`${id}_box`),
    dragTextEfb = dropAreaEfb.querySelector("h6"),
    dragbtntEfb = dropAreaEfb.querySelector("button"),
    dragInptEfb = dropAreaEfb.querySelector("input");
  dropAreaEfb.classList.remove("active");
  dragInptEfb.disabled = false;
  console.log(dragInptEfb);
  dragbtntEfb.onclick = () => {
    console.log('click');
    dragInptEfb.click();
    console.log('click 2');
  }

  dragInptEfb.addEventListener("change", function () {
    console.log('change');
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
    dragTextEfb.textContent = `${efb_var.text.dragAndDropA} ${valj_efb[indx].file.toUpperCase()}`;
  });

  dropAreaEfb.addEventListener("drop", (event) => {
    event.preventDefault();

    fileEfb = event.dataTransfer.files[0];
    viewfileEfb(id, indx);
  });


}


function removeFileEfb(id, indx) {
  console.log('remove')
  fileEfb = "";
  //dropAreaEfb.classList.add("active");
  document.getElementById(`${id}_box`).innerHTML = ui_dadfile_efb(indx)

  setTimeout(() => {
    create_dadfile_efb(id, indx);
  }, 100)

}


function ui_dadfile_efb(indx, previewSate) {
  return `<div class="icon"><i class="efb ${valj_efb[indx].icon} ${valj_efb[indx].icon_color}" id="${valj_efb[indx].id_}"></i></div>
  <h6 id="${valj_efb[indx].id_}_txt" class="text-center">${efb_var.text.dragAndDropA} ${valj_efb[indx].file.toUpperCase()} </h6> <span>OR</span>
  <button type="button" class="efb btn ${valj_efb[indx].button_color} btn-lg" id="${valj_efb[indx].id_}_b">
      <i class="efb bi-upload me-2"></i>${efb_var.text.browseFile}
  </button>
  <input type="file" hidden="" class="efb   ${valj_efb[indx].required == 1 || valj_efb[indx].required == true ? 'requirer' : ''}" id="${valj_efb[indx].id_}_" data-id="${valj_efb[indx].id_}-el" ${previewSate != true ? 'disabled' : ''}>`

}


function viewfileEfb(id, indx) {
  console.log('viewfileEfb');
  let fileType = fileEfb.type;
  const filename = fileEfb.name
  console.log(filename, fileEfb)

  //let validExtensions = ["image/jpeg", "image/jpg", "image/png", 'image/gif'];
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
       <div class="card">
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
      } else {
        box.innerHTML = box_v;
      }
    }
    fileReader.readAsDataURL(fileEfb);
  } else {
    alert("The file is not the right file type");
    document.getElementById(`${id}_box`).innerHTML.classList.remove("active");
    //  dragTextEfb.textContent = "Drag & Drop to Upload a File";
  }
}


function validExtensions_efb_fun(type, fileType) {
  console.log(fileType)

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
  console.log(t);
  if (t > 500) t = 500
  const body = loading_messge_efb()
  show_modal_efb(body, efb_var.text.editField, 'bi-ui-checks me-2', 'settingBox')
  const el = document.getElementById("settingModalEfb");
  console.log(el.dataset.backdrop)
  const myModal = new bootstrap.Modal(el, {});
  console.log(myModal)
  myModal.backdrop = 'static';
  myModal.show()
  setTimeout(() => {
    console.log('test closer')
    myModal.hide()
  }, t)
}




function handle_navbtn_efb(steps, device) {
  console.log(device, steps, 'Buttons', 'Preview')
  var current_s_efb, next_s_efb, prev_s_efb; //fieldsets
  var opacity_efb;
  var current_s_efb = 1;
  var steps_len_efb = (steps) + 1;
  setProgressBar_efb(current_s_efb);
  if (steps > 1) {

    if (current_s_efb == 1) { jQuery("#prev_efb").toggleClass("invisible"); }

    jQuery("#next_efb").click(function () {

      if ((current_s_efb + 1) == steps_len_efb) {
        jQuery("#prev_efb").addClass("invisible");
        jQuery("#next_efb").addClass("invisible");
        //send to server after validation
      }


      if (current_s_efb == 1) { jQuery("#prev_efb").toggleClass("invisible"); }
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
      setProgressBar_efb(current_s_efb);
      console.log(current_s_efb, steps)
      if (current_s_efb <= steps) {
        var val = valj_efb.find(x => x.step == nxt)
        jQuery("#title_efb").attr('class', val['label_text_color']);
        jQuery("#desc_efb").attr('class', val['message_text_color']);
        jQuery("#title_efb").text(val['name']);
        jQuery("#desc_efb").text(val['message']);
      }

    });

    jQuery("#prev_efb").click(function () {
      if ((current_s_efb - 1) == 1) {
        jQuery("#prev_efb").toggleClass("invisible");
      }
      var current_s = jQuery('[data-step="step-' + (current_s_efb) + '-efb"]');
      prev_s_efb = current_s.prev();
      jQuery('[data-step="icon-s-' + (current_s_efb) + '-efb"]').removeClass("active");
      jQuery('[data-step="step-' + (current_s_efb) + '-efb"]').toggleClass("d-none");
      //bug here
      var s = "" + (current_s_efb - 1) + ""
      var val = valj_efb.find(x => x.step == s)
      console.log(val, 'val');
      jQuery("#title_efb").attr('class', val['label_text_color']);
      jQuery("#desc_efb").attr('class', val['message_text_color']);
      jQuery("#title_efb").text(val['name']);
      jQuery("#desc_efb").text(val['message']);
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
      setProgressBar_efb(current_s_efb);

    });
  } else {
    // if just one step!
    jQuery("#btn_send_efb").click(function () {

      jQuery('[data-step="icon-s-' + (current_s_efb + 1) + '-efb"]').addClass("active");
      jQuery('[data-step="step-' + (current_s_efb + 1) + '-efb"]').toggleClass("d-none");

      jQuery("#btn_send_efb").toggleClass("invisible");
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
      setProgressBar_efb(current_s_efb);
      //send to server after validation
    })
  }

  function setProgressBar_efb(curStep) {
    var percent = parseFloat(100 / steps_len_efb) * curStep;
    percent = percent.toFixed();
    jQuery(".progress-bar-efb")
      .css("width", percent + "%")
  }

  jQuery(".submit").click(function () {
    return false;
  })

};

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
    console.log("val" ,valj_efb[0])
    console.log("val" ,valj_efb)
    </script>   
    <link rel="stylesheet" href="https://merciful-useful-spider.glitch.me/bootstrap-select.css" />
    <link href="https://merciful-useful-spider.glitch.me/bootstrap.min.css" rel="stylesheet">
    <link href="https://merciful-useful-spider.glitch.me/style.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">

  <script src='https://maps.googleapis.com/maps/api/js?key=AIzaSyB41DRUbKWJHPxaFjMAwdrzWzbVKartNGg&language=en&libraries=&v=weekly&channel=2' id='googleMaps-js'></script>
  </head>
    <body>
    ${content}
    ${btns}
  
    <script src="https://merciful-useful-spider.glitch.me/bootstrap.min.js"><\/script>
    <script src="https://merciful-useful-spider.glitch.me/bootstrap.bundle.min.js"><\/script>
    <script src="https://merciful-useful-spider.glitch.me/bootstrap-select.min.js"><\/script>
   
    <script src="https://merciful-useful-spider.glitch.me/new.js"><\/script>
    <script>
    
   
    handle_navbtn_efb(${valj_efb[0].steps},'mobile')
   

    </script>
    </body>
    `
    let iframe = document.createElement('iframe');
    iframe.id = `efbMobileView`;
    iframe.src = 'data:text/html;charset=utf-8,' + encodeURI(html);
    //iframe.src =`file:///home/hassan/Downloads/newUi/index%20-new.html`
    document.getElementById('parentMobileView').innerHTML = "";
    document.getElementById('parentMobileView').appendChild(iframe)
  }, 1000)

}

/* function noti_message_efb(title, message, sec) {

  document.getElementById('alert_efb').innerHTML = ` <div id="alert_content_efb" class="efb alert alert-dismissible alert-info mx-5 alert-info {$efb_var.rtl==1 ? 'rtl-text' :''}" role="alert">
  <h4 class="alert-heading">${title}</h4>
  <div class="mx-2">${message}</div>
  <br>
  <button type="button" class="btn-close" data-dismiss="alert" aria-label="Close"></button>
</div>`
  setTimeout(function () {
    jQuery('.alert_efb').hide();
    document.getElementById('alert_efb').innerHTML = ""
  }, sec);

  window.scrollTo({ top: 0, behavior: 'smooth' });
  $('.alert').alert()
} */



localStorage.getItem('count_view') ? localStorage.setItem(`count_view`, parseInt(localStorage.getItem('count_view')) + 1) : localStorage.setItem(`count_view`, 0)
if (localStorage.getItem('count_view')>6 && localStorage.getItem('count_view') <10) {
  noti_message_efb(`🎉 ${efb_var.text.SpecialOffer}`, googleCloudOffer(), 15)
}

function googleCloudOffer() {

  return `<p>${efb_var.text.offerGoogleCloud} <a href="#" target="blank">${efb_var.text.getOfferTextlink}</a> </p> `
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
  document.getElementById('alert_efb').innerHTML = ` <div id="alert_content_efb" class="efb alert ${alert} alert-dismissible  mx-5 ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <h4 class="alert-heading">${title}</h4>
    <p>${message}</p>
    <button type="button" class="btn-close" data-dismiss="alert" aria-label="Close"></button>
  </div>`
  setTimeout(function () {
    jQuery('.alert_efb').hide();
    document.getElementById('alert_efb').innerHTML = ""
  }, sec);

  window.scrollTo({ top: 0, behavior: 'smooth' });
  $('.alert').alert()
}


function gm_authFailure() {
  const body =`<p class="fs-6 efb">${efb_var.text.aPIkeyGoogleMapsFeild} <a href="https://developers.google.com/maps/documentation/javascript/error-messages" target="blank">${efb_var.text.clickHere}</a> </p>`
  noti_message_efb(efb_var.text.error, body, 120 ,'danger')

}



