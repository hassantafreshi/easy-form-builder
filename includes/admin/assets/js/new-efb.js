//Copyright 2021-2024
//Easy Form Builder
//WhiteStudio.team
//EFB.APP
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
const getUrlparams_efb = new URLSearchParams(location.search);
const mobile_view_efb = document.getElementsByTagName('body')[0].classList.contains("mobile") ? 1 : 0;
efb_var_waitng = (time) => {
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
function fub_shwBtns_efb() {
  for (const el of document.querySelectorAll(".showBtns")) {
    el.addEventListener("click", (e) => {
      active_element_efb(el);
    });
  }
}

// 3.8.6 start
function pro_show_efb(state) {
  let message = sanitize_text_efb(state);
  if (typeof state != "string") message = state == 1 ? sanitize_text_efb(efb_var.text.proUnlockMsg) : sanitize_text_efb(efb_var.text.ifYouNeedCreateMoreThan2Steps);
  
  const body = `<div class="efb  pro-version-efb-modal"><i class="efb  bi-gem"></i></div>
  <h5 class="efb  txt-center">${message}</h5>
  <div class="efb row">
  <div class="efb  col-md-6  text-center">
  <button class="efb btn mt-3 efb btn-r h-d-efb btn-outline-pink "  onClick ="open_whiteStudio_efb('pro')">${efb_var.text.priceyr.replace('NN',pro_price_efb)} </button>
  </div>
    <div class="efb  text-center col-md-6"><button type="button" class="efb btn btn-r efb btn-primary efb-btn-lg mt-3 mb-3" onClick ="open_whiteStudio_efb('pro')">
      <i class="efb  bi-gem mx-1 pro"></i>
        ${sanitize_text_efb(efb_var.text.activateProVersion)}
      </button></div>
  </div>`
  show_modal_efb(body, sanitize_text_efb(efb_var.text.proVersion), '', 'proBpx')
  state_modal_show_efb(1)
}
// 3.8.6 end

function move_show_efb() {
  const body = `<div class="efb  pro-version-efb-modal"><i class="efb "></i></div>
  <div class="efb  text-center" dir="rtl">
   <img src="${efb_var.images.movebtn}" class="efb  img-fluid" alt="">
  </div>`
  show_modal_efb(body, '','bi-arrows-move', 'saveBox')
  state_modal_show_efb(1)
}
const show_modal_efb = (body, title, icon, type) => {
  document.getElementById("settingModalEfb-title").innerHTML = title;
  document.getElementById("settingModalEfb-icon").className = icon + ` mx-2`;
  document.getElementById("settingModalEfb-body").innerHTML = body;
  if (type == "settingBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.contains('modal-new-efb') ? '' : document.getElementById("settingModalEfb").classList.add('modal-new-efb')
  }
  else if (type == "deleteBox" || type=="duplicateBox") {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    if (!document.getElementById('modalConfirmBtnEfb')) document.getElementById('settingModalEfb-sections').innerHTML += `
    <div class="efb  modal-footer" id="modal-footer-efb">
      <a type="button" class="efb  btn btn-danger" data-bs-dismiss="modal"  id="modalConfirmBtnEfb">
          ${efb_var.text.yes}
      </a> 
    </div>`
  } else if (type == "saveBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else if (type == "saveLoadingBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
    document.getElementById('settingModalEfb-body').innerHTML = efbLoadingCard();
  } else if (type == "chart") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
  }
}
const add_new_option_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  let op = `<!-- option --!> 2`
  let price ="<!--efb.app-->";
  let qst ='<!--efb.app-->';
  let tagtype= tag;
  const $pv = 0;
  const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
  if (tag.includes("pay")!=false){ 
    tagtype = tag.slice(3);
    price =`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${idin}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>`;
  }
   if(tag.includes("chl")!=false){
    tagtype = tag.slice(3);
    qst =`<input type="text" class="efb ${valj_efb[indxP].el_text_color}  ${valj_efb[indxP].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${idin}" data-vid="" id="${idin}_chl" placeholder="${valj_efb[indxP].pholder_chl_value}"  disabled>`
  }
  if (fun_el_select_in_efb(tag)) {
    op = `<option value="${value}" id="${idin}" data-id="${idin}-id"  data-op="${idin}" class="efb ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height}">${value}</option>`
  } else {
    let col = valj_efb[indxP].hasOwnProperty('op_style') && Number(valj_efb[indxP].op_style )!=1 ? 'col-md-'+(12/Number(valj_efb[indxP].op_style )) :''
    op = `<div class="efb  form-check ${col}" data-parent="${parentsID}"  id="${id_ob}-v">
    <input class="efb  form-check-input ${valj_efb[indxP].el_text_size}" type="${tagtype}" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
    <label class="efb ${valj_efb[indxP].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height} hStyleOpEfb " id="${idin}_lab" for="${idin}">${value}</label>
    ${qst}
    ${price}
    </div>`
  }
  return op;
}
function addNewElement(elementId, rndm, editState, previewSate) {
  let pos = [``, ``, ``, ``]
  const shwBtn = previewSate != true ? 'showBtns' : '';
  let indexVJ = editState != false ? valj_efb.findIndex(x => x.id_ == rndm) : 0;
  if (previewSate == true && elementId != "html" && elementId != "register" && elementId != "login" && elementId != "subscribe" && elementId != "survey") pos = get_position_col_el(valj_efb[indexVJ].dataId, false)  
  amount_el_efb = editState == false ?  Number(amount_el_efb) + 1 : valj_efb[indexVJ].amount;
  element_name = editState == false ? elementId : valj_efb[indexVJ].name;
  let optn = '<!-- options -->';
  step_el_efb >= 1 && editState == false && elementId == "steps" ? step_el_efb = Number(step_el_efb) + 1 : 0;
  if (editState != false && previewSate != true) {
    step_el_efb = valj_efb[0].steps;
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    add_buttons_zone_efb(t, 'dropZoneEFB')
  }
  let pay = previewSate == true ? 'payefb' : 'pay';
  newElement = ``;
  if (previewSate == false) Object.entries(valj_efb[indexVJ]).forEach(([key, val]) => {
    fun_addStyle_costumize_efb(val.toString(), key, indexVJ); })
  if (step_el_efb == 1) {
    let state = false;
    if (editState == false) {
      state = true;
    }
    if (elementId != 'steps') {
      if (editState == false && valj_efb.length < 2) {
        valj_efb.push({
          id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
          id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: "",
          label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb',
          el_text_color: 'text-labelEfb', message_text_color:pub_message_text_color_efb, icon_color: pub_icon_color_efb, icon: 'bi-ui-checks-grid', visible: 1
        });
        indexVJ = valj_efb.length - 1;
        const sort = indexVJ<=1 ? 'unsortable'  : 'sortable';
        newElement = ` 
        <section class="efb  ${sort} list   row my-2  efbField stepNavEfb stepNo" data-step="${step_el_efb}" data-amount="${step_el_efb}" data-id="${step_el_efb}" id="${step_el_efb}" data-tag="steps">
            <div class="efb  row my-2  ${shwBtn} efbField stepNavEfb" data-step="${step_el_efb}" data-amount="${step_el_efb}" data-id="${step_el_efb}" id="${step_el_efb}" data-tag="steps">
            <h2 class="efb  col-10 mx-2 my-0"><i class="efb  ${valj_efb[indexVJ].icon} ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'}  ${valj_efb[indexVJ].icon_color}"
                    id="${step_el_efb}_icon"></i> <span id="${step_el_efb}_lab" class="efb   text-darkb  ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'} ">${valj_efb[indexVJ].name}</span></span></h2>
            <small id="${step_el_efb}-des" class="efb  form-text ${valj_efb[indexVJ].message_text_color} border-bottom px-4   ">${valj_efb[indexVJ].message}</small>
            <div class="efb  col-sm-10">
                <div class="efb  btn-edit-holder btnSetting d-none " id="btnSetting-${step_el_efb}">
                    <button type="button" class="efb  btn  btn-edit  btn-sm BtnSideEfb" id="settingElEFb"
                        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
                        onclick="show_setting_window_efb('${step_el_efb}')">
                        <div class="icon-container efb"><i class="efb   bi-gear-wide-connected text-success" id="efbSetting"></i></div>
                    </button>
                </div>
            </div>
        </div>
        </section>`;
      }
      const t = valj_efb[0].steps == 1 ? 0 : 1;
      if (previewSate != true) editState == false ? add_buttons_zone_efb(0, 'dropZoneEFB') : add_buttons_zone_efb(t, 'dropZoneEFB')
    } else if (elementId == "steps" && step_el_efb == 1 && state == false && editState == false) {
      valj_efb.push({
        id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
        id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: "",
        label_text_size: 'fs-5', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
        el_text_color: 'text-dark', message_text_color: pub_message_text_color_efb, icon_color: pub_icon_color_efb, icon: 'bi-ui-checks-grid', visible: 1
      });
      editState == false && valj_efb.length > 2 ? step_el_efb= Number(step_el_efb) +1 : 0;
    }
    amount_el_efb =Number(amount_el_efb)+1;
  }
  if (editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_efb == false && step_el_efb < 3) || pro_efb == true)) { sampleElpush_efb(rndm, elementId); }
  let iVJ = editState == false ? valj_efb.length - 1 : valj_efb.findIndex(x => x.id_ == rndm);
  let dataTag = 'text'
  const desc = `<small id="${rndm}-des" class="efb  form-text d-flex  fs-7 col-sm-12 efb ${previewSate == true && pos[1] == 'col-md-4' || valj_efb[iVJ].message_align != "justify-content-start" ? `` : `mx-4`}  ${valj_efb[iVJ].message_align}  ${valj_efb[iVJ].message_text_color} ${ valj_efb[iVJ].hasOwnProperty('message_text_size') ? valj_efb[iVJ].message_text_size : ''} ">${valj_efb[iVJ].message} </small> `;
  const  label = `<label for="${rndm}_" class="efb mx-0 px-0 pt-2 pb-1  ${previewSate == true ? pos[2] :"col-md-12"} col-sm-12 col-form-label ${valj_efb[iVJ].hasOwnProperty('hflabel') && Number(valj_efb[iVJ].hflabel)==1 ? 'd-none' :''} ${valj_efb[iVJ].label_text_color} ${valj_efb[iVJ].label_align} ${valj_efb[iVJ].label_text_size != "default" ? valj_efb[iVJ].label_text_size : ''} " id="${rndm}_labG" ><span id="${rndm}_lab" class="efb  ${valj_efb[iVJ].label_text_size}">${valj_efb[iVJ].name}</span><span class="efb  mx-1 text-danger" id="${rndm}_req" role='none'>${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? '*' : ''}</span></label>`
  const ttip = `<small id="${rndm}_-message" class="efb py-1 fs-7 tx ttiptext px-2"> ! </small>`
  const rndm_1 = Math.random().toString(36).substr(2, 9);
  const rndm_2 = Math.random().toString(36).substr(2, 9);
  const op_3 = Math.random().toString(36).substr(2, 9);
  const op_4 = Math.random().toString(36).substr(2, 9);
  const op_5 = Math.random().toString(36).substr(2, 9);
  let ui = ''
  const vtype = (elementId == "payCheckbox" || elementId == "payRadio" || elementId == "paySelect" || elementId == "payMultiselect" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio" || elementId=='trmCheckbox') ? elementId.slice(3).toLowerCase() : elementId;
  let classes = ''
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square';
  let minlen,maxlen,temp,col;
  let hidden =  previewSate == true  && valj_efb[iVJ].hasOwnProperty('hidden') &&  valj_efb[iVJ].hidden==1 ? 'd-none' : ''
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  let ps =  elementId == "html" ? 'col-md-12' : 'col-md-12'
  if(pos[3]==""){
     if( elementId=="firstName" || elementId=="lastName" ){ ps = 'col-md-6';
     }
  }
  pos[3] = pos[3]=="" ? 'col-md-12' :  pos[3];
  genertate_ops_select_Efb =()=>{
    const op_1 = Math.random().toString(36).substr(2, 9);
    const op_2 = Math.random().toString(36).substr(2, 9);
    const pv=0;
    const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
    optn = `
   <div class="efb  form-check  ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
   <input class="efb  emsFormBuilder_v form-check-input ${pay} ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
   ${elementId!='imgRadio' ?`<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${op_1}_lab">${efb_var.text.newOption} 1</label>` : fun_imgRadio_efb(op_1,'urlLin',valj_efb[iVJ])}
   ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
   ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_1}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
   </div>
   <div class="efb  form-check ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-parent="${rndm}" data-id="${op_2}" id="${op_2}-v">
       <input class="efb  emsFormBuilder_v form-check-input ${pay}  ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
       ${elementId!='imgRadio' ?  `<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>` : fun_imgRadio_efb(op_2,'urlLin',valj_efb[iVJ])}
       ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
       ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_2}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
   </div>`
   temp = '1';
   tp = '2';
   if(elementId=="imgRadio"){
    temp = '';
   tp = '';
   }
    optionElpush_efb(rndm, `${efb_var.text.newOption} ${temp}`, op_1, op_1 ,dataTag);
    optionElpush_efb(rndm, `${efb_var.text.newOption} ${tp}`, op_2, op_2 ,dataTag);
  }
  const aire_describedby = valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : "";
  switch (elementId) {
    case 'email':
    case 'text':
    case 'password':
    case 'tel':
    case 'url':
    case "date":
    case 'color':
    //case 'range':
    case 'number':
    case 'firstName':
    case 'lastName':
    case 'datetime-local':
    case 'postalcode':
    case 'address_line':
      const type = elementId == "firstName" || elementId == "lastName" || elementId == "postalcode" || elementId == "address_line" ? 'text' : elementId;
      const autocomplete = elementId == "email" ? 'email' : elementId == "tel" ? 'tel' : elementId == "url" ? 'url' : elementId == "password" ? 'current-password' : elementId == "firstName" ? 'given-name' : elementId == "lastName" ? 'family-name' : elementId == "postalcode" ? 'postal-code' : elementId == "address_line" ? 'street-address' : 'off';
      //email, number, password, search, tel, text, or url
      const placeholder =  elementId != 'color'  && elementId != 'range' &&  elementId != 'password' &&  elementId != 'date' ? `placeholder="${valj_efb[iVJ].placeholder}"` : '';
     
      if(elementId != 'date'){
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
        //console.log(maxlen);
        maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :0;    
        minlen = Number(minlen)!=0  ? `minlength="${minlen}"`:``;
      }else{
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen')  ? valj_efb[iVJ].mlen :'';
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :'';  
          const today = new Date();
          const dd = String(today.getDate()).padStart(2, '0');
          const mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
          const yyyy = today.getFullYear();
        if(maxlen==1) {
          //current time YYYY-MM-DD          
          maxlen = `${yyyy}-${mm}-${dd}`;
        }
        if (minlen==1) {
          //current time YYYY-MM-DD
          minlen = `${yyyy}-${mm}-${dd}`;
        }

        maxlen = Number(maxlen)!=0 && maxlen!='' ? `max="${maxlen}"`:``;        
        minlen = Number(minlen)!=0 && minlen !='' ? `min="${minlen}"`:``;
      }
      
      //console.log(`[${minlen}]`,valj_efb[iVJ].milen);
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="${type}"   class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-id="${rndm}-el" data-vid='${rndm}' data-css="${rndm}" id="${rndm}_" ${placeholder}  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} autocomplete="${autocomplete}"  ${maxlen} ${minlen} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      break;
    case 'pdate':
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb pdpF2 input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}'  id="${rndm}_"   ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'ardate':
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb hijri-picker input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_"   ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'range':
         maxlen = valj_efb[iVJ].hasOwnProperty('mlen') ? valj_efb[iVJ].mlen :100;
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :0;    
        temp =valj_efb[iVJ].value>0 ? valj_efb[iVJ].value :Math.round((Number(valj_efb[iVJ].mlen)+Number(valj_efb[iVJ].milen))/2) ;
        ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show  "  id='${rndm}-f'>
          ${ttip}
          <div class="efb slider m-0 p-2 ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].el_text_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id='${rndm}-range'>
          <input type="${elementId}"  class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" oninput="fun_show_val_range_efb('${rndm}')"  ${valj_efb[iVJ].value.length > 0 ? `value ="${temp}"` : ''} min="${minlen}" max="${maxlen}" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          <p id="${rndm}_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">${temp||50}</p>
          </div>
          ${desc}`
        dataTag = elementId;
        break;
    case 'maps':
      ui = `
      ${label}
      <!-- ${rndm}-map -->
      ${ttip}
       ${typeof maps_os_pro_efb =="function" ? maps_os_pro_efb(previewSate, pos , rndm,iVJ) : public_pro_message()}
        ${desc}`
      dataTag = elementId;
      break;
    case 'file':
      ui = `
       ${label}
        <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
          ${ttip}        
          <input type="${elementId}" class="efb  input-efb px-2 py-1 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}    form-control efb efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_"  aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          ${desc}`
      dataTag = elementId;
      break;
    case "textarea":
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;  
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
      ui = `
                ${label}
                <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f' >
                ${ttip}
                <textarea  id="${rndm}_"  placeholder="${valj_efb[iVJ].placeholder}"  class="efb  px-2 input-efb emsFormBuilder_v form-control w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el"  value="${valj_efb[iVJ].value}" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} rows="5" ${previewSate != true ? 'readonly' : ''} ${disabled} ${minlen}>${efb_text_nr(valj_efb[iVJ].value,0)}</textarea>
                ${desc}
            `
      dataTag = "textarea";
      break;
    case "mobile":
      temp = typeof create_intlTelInput_efb =="function" ?  create_intlTelInput_efb(rndm,iVJ,previewSate,corner) : public_pro_message() ;
      ui = `
                ${label}
                <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
                ${ttip}
                ${temp}
                ${desc}
            `
      dataTag = "textarea";
      break;
    case 'dadfile':
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id='${rndm}-f'>
      ${desc}      
      ${ttip}
      ${typeof dadfile_el_pro_efb =="function" ?  dadfile_el_pro_efb(previewSate, rndm,iVJ) : public_pro_message()}
      `
      dataTag = elementId;
      break;
    case 'checkbox':
    case 'radio':
    case 'payCheckbox':
    case 'payRadio':
    case 'chlCheckBox':
    case 'chlRadio':
    case 'imgRadio':
    case 'trmCheckbox':
      dataTag = elementId;
       col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style )!=1 ? 'col-md-'+(12/Number(valj_efb[iVJ].op_style )) :''
      if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio" || elementId == "trmCheckbox") pay = "";
      temp = elementId=="imgRadio" ? 'col-md-4 mx-0 px-2' :'';
      if (editState != false) {
        let tp = dataTag.toLowerCase();
        let parent = valj_efb[iVJ]
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
        const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
        for (const i of optns_obj) {
          let checked ="";          
          if((tp.includes("radio")==true ||( tp.includes("select")==true &&  tp.includes("multi")==false))  && ( parent.value == i.id_ || (i.hasOwnProperty("id_old") && parent.value == i.id_old) )  ){ checked="checked";
          }else if((tp.includes("multi")==true || tp.includes("checkbox")==true) &&  typeof parent.value!="string" &&  parent.value.findIndex(x=>x==i.id_ || x==i.id_old)!=-1 ){checked="checked"}
          const prc = i.hasOwnProperty('price') ? Number(i.price):0;
          //console.log(i);
          optn += `<div class="efb  form-check ${col} ${elementId} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)} mt-1" data-css="${rndm}" data-parent="${i.parent}" data-id="${i.id_}" id="${i.id_}-v">
          <input class="efb  form-check-input emsFormBuilder_v ${pay}  ${valj_efb[iVJ].el_text_size} " data-tag="${dataTag}" data-type="${vtype}" data-vid='${rndm}' type="${vtype}" name="${i.parent}" value="${i.value}" id="${i.id_}" data-id="${i.id_}-id" data-op="${i.id_}"${previewSate != true ? 'readonly' : ''} ${disabled} ${checked}>
          ${elementId!='imgRadio'?` <label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${i.id_}_lab" for="${i.id_}">${fun_get_links_from_string_Efb(i.value,true)}</label>`: fun_imgRadio_efb(i.id_,i.src,i)}
          ${elementId.includes('chl')!=false?`<input type="text" class="efb ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${i.id_}" data-type="${dataTag}" data-vid="" id="${i.id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}"   disabled>` :''}
          ${ pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${i.id_}-price" class="efb efb-crrncy">${prc.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
          </div>`
        }
      } else {
       const op_1 = Math.random().toString(36).substr(2, 9);
       const op_2 = Math.random().toString(36).substr(2, 9);
       const pv=0;
       const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';

      let t1= '1';
      let t2 = '2';
      if(elementId=="imgRadio"){
        t1 = '';
        t2 = '';
      }
       let opt_label = `${efb_var.text.newOption} ${t1}`;
       if(elementId!='trmCheckbox'){
        optionElpush_efb(rndm, `${opt_label}`, op_1, op_1 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.newOption} ${t2}`, op_2, op_2 ,dataTag);
      }else{
        opt_label = efb_var.text.trmcn;
        opt_label = opt_label.replace('%s1', "[");
        opt_label = opt_label.replace('%s2', "](https://whitestudio.team/privacy-policy-terms)");
        optionElpush_efb(rndm, `${opt_label}`, op_1, op_1 ,dataTag);
        opt_label = fun_get_links_from_string_Efb(opt_label,true);
      }
       optn = `
      <div class="efb  form-check  ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
      <input class="efb  emsFormBuilder_v form-check-input ${pay} ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
      ${elementId!='imgRadio' ?`<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${op_1}_lab">${opt_label}</label>` : fun_imgRadio_efb(op_1,'urlLin',valj_efb[iVJ])}
      ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
      ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_1}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
      </div>
     `
     if(elementId!="trmCheckbox"){
      optn += ` <div class="efb  form-check ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-parent="${rndm}" data-id="${op_2}" id="${op_2}-v">
      <input class="efb  emsFormBuilder_v form-check-input ${pay}  ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
      ${elementId!='imgRadio' ?  `<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>` : fun_imgRadio_efb(op_2,'urlLin',valj_efb[iVJ])}
      ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
      ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_2}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
      </div> `;
     }
      
       
      }
      temp = elementId=="imgRadio" ?  "row  justify-content-center" :"";
      ui = `
      <!-- checkbox -->
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show"   data-id="${rndm}-el" id='${rndm}-f'>
      ${ttip}
      <div class="efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${col!=''? 'row col-md-12' :''} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" ${aire_describedby} id="${rndm}_options">
        ${optn}
        </div>
        <div class="efb  mb-3">${desc}</div>
        <!-- end checkbox -->
        `
      break;
    case 'switch':
      valj_efb[iVJ].on =  valj_efb[iVJ].hasOwnProperty('on') ? valj_efb[iVJ].on :efb_var.text.on
      valj_efb[iVJ].off =  valj_efb[iVJ].hasOwnProperty('off') ? valj_efb[iVJ].off :efb_var.text.off
      ui = `
      ${label}
      ${ttip}
      <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f" ${aire_describedby}>
      <label class="efb fs-6" id="${rndm}_off">${valj_efb[iVJ].off}</label>
      <button type="button"  data-state="off" class="efb btn   ${valj_efb[iVJ].el_height}  btn-toggle efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-toggle="button" aria-pressed="false" data-vid='${rndm}' onClick="fun_switch_efb(this)" data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''} ${disabled}>
        <div class="efb handle"></div>
      </button>
      <label class="efb fs-6" id="${rndm}_on">${valj_efb[iVJ].on}</label>
   <!--   <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f">
      <div class="efb  form-check form-switch   ${valj_efb[iVJ].el_height} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}-switch">
        <input class="efb d-none emsFormBuilder_v efb-switch form-check-input efbField" type="checkbox" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${previewSate != true ? 'disabled' : ''} ${disabled}>
      </div> -->
      <div class="efb  mb-3">${desc}</div>
      `
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      dataTag = elementId;
      break;
    case 'esign':
      ui = `
      ${label}
      ${ttip}
      ${typeof esign_el_pro_efb =="function" ? esign_el_pro_efb(previewSate, pos , rndm,iVJ,desc) : public_pro_message()}
      `
      dataTag = elementId;
      break;
    case 'rating':
      ui = `
      ${ttip}
      ${label}
      ${typeof rating_el_pro_efb =="function" ? rating_el_pro_efb(previewSate,pos, rndm,iVJ) : public_pro_message()}
      <div class="efb  mb-3">${desc}</div>
        `
      dataTag = elementId;
      break;
    case "steps":
      dataTag = 'step';
      let del = ``;
      if (step_el_efb > 1) {
        del = `
          <button type="button" class="efb  btn btn-edit btn-sm" id="${valj_efb[iVJ].id_}"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.delete}"
          onclick="show_delete_window_efb('${valj_efb[iVJ].id_}' ,${iVJ})">
          <i class="efb  bi-x-lg text-danger"></i>
          </button>`
      }
      if (step_el_efb <= 2 || (step_el_efb > 2 && pro_efb == true)) {
        valj_efb[0].steps = editState == false ? step_el_efb : valj_efb[0].steps;
        const clss = valj_efb[iVJ].classes!="" ? 'efb1 '+valj_efb[iVJ].classes.replace(`,`, ` `) : "";
        const sort = iVJ<3 ? 'unsortable'  : 'sortable';
        newElement += ` 
        <setion class="efb ${sort}  row my-2  ${shwBtn} efbField stepNavEfb stepNo ${clss}" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}">
       <!-- <div class="efb  row my-2  ${shwBtn} efbField ${valj_efb[iVJ].classes.replace(`,`, ` `)} stepNavEfb" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}"> -->
        <h2 class="efb  col-md-10 col-sm-12 mx-2 my-0"><i class="efb  ${valj_efb[iVJ].icon} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].icon_color} "
        id="${valj_efb[iVJ].id_}_icon"></i> <span id="${valj_efb[iVJ].id_}_lab" class="efb  ${valj_efb[iVJ].label_text_size}  ${valj_efb[iVJ].label_text_color}  ">${valj_efb[iVJ].name}</span></span></h2>
        <small id="${valj_efb[iVJ].id_}-des" class="efb  form-text ${valj_efb[iVJ].message_text_color} border-bottom px-4">${valj_efb[iVJ].message}</small>
        <div class="efb  col-md-10 col-sm-12">
        <div class="efb  btn-edit-holder d-none" id="btnSetting-${valj_efb[iVJ].id_}">
        <button type="button" class="efb  btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"
        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
        onclick="show_setting_window_efb('${valj_efb[iVJ].id_}')">
        <div class="icon-container efb"><i class="efb bi-gear-wide-connected  text-success BtnSideEfb" ></i></div>
        </button>
          ${del}
        </div>
        </div>
        <!--  </div> -->
        </setion>
        `
      } else {
        pro_show_efb(2);
      }
      break;
    case 'select':
    case 'paySelect':
      if(elementId!="paySelect") pay='';
      if(editState!=false){
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
        for (const i of optns_obj) {
          optn += `<option class="efb ${valj_efb[iVJ].el_text_color} emsFormBuilder_v efb" data-id="${i.id_}" data-op="${i.id_}" value="${i.value}" ${valj_efb[iVJ].value==i.id_ || ( i.hasOwnProperty('id_old') && valj_efb[iVJ].value==i.id_old) ? "selected" :''}>${i.value}</option>`
        }
      }else{
        const op_1 = Math.random().toString(36).substr(2, 9);
       const op_2 = Math.random().toString(36).substr(2, 9);
       temp = '1';
        tp = '2';
        optionElpush_efb(rndm, `${efb_var.text.newOption} ${temp}`, op_1, op_1 ,dataTag);
       optionElpush_efb(rndm, `${efb_var.text.newOption} ${tp}`, op_2, op_2 ,dataTag);
      }
      ui = `
      ${label}
      <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1  ${valj_efb[iVJ].classes.replace(`,`, ` `)}"  data-css="${rndm}"   id='${rndm}-f'  data-id="${rndm}-el" >
      ${ttip}
      <select class="efb form-select efb emsFormBuilder_v w-100 ${pay}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  w-100 " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
      <option selected disabled>${efb_var.text.nothingSelected}</option>
      ${optn}
      </select>
      ${desc}
      `
      dataTag = elementId;
      break;
    case 'conturyList':
    case 'country':
       optn= typeof countryList_el_pro_efb =="function"? countryList_el_pro_efb(rndm,rndm_1,op_3,op_4,editState) : "null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 efb  px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="conturyList" class="efb form-select efb w-100 emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
        if (optn=="null") ui = public_pro_message();
      dataTag = elementId;
      break;
    case 'stateProvince':
    case 'statePro':
      temp=false
       optn =valj_efb.findIndex(x=>x.id_=='EC')
    if (editState==false && optn==-1) { 
      valj_efb[iVJ].country = "GB";
      const uk =  fun_state_of_UK(rndm,iVJ);
      for(u of uk){
      valj_efb.push(     
        u
      );}
      temp=true;
    }
      optn = typeof statePrevion_el_pro_efb =="function"? statePrevion_el_pro_efb(rndm, rndm_1, temp, op_4, editState) :"null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="stateProvince" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
      dataTag = elementId;
      if (optn=="null") ui = public_pro_message();
      break;
    case 'city':
    case 'cityList':
       optn =valj_efb.findIndex(x=>x.id_=='Antrim_Newtownabbey');
        temp=false
    if (editState==false && optn==-1) { 
      console.error(optn,valj_efb.findIndex(x=>x.id_=='Antrim_Newtownabbey'));
      valj_efb[iVJ].country = "GB";
      valj_efb[iVJ].statePov = "Antrim_Newtownabbey";
      valj_efb.push(     
        {
          "id_": "Antrim_Newtownabbey",
          "dataId": "Antrim_Newtownabbey-id",
          "parent":rndm,
          "type": "option",
          "value": "Antrim and Newtownabbey",
          "id_op": "Antrim_Newtownabbey",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
        "id_": "Ards_and_North_Down",
        "dataId": "Ards_and_North_Down-id",
        "parent":rndm,
        "type": "option",
        "value": "Ards and North Down",
        "id_op": "Ards_and_North_Down",
        "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Armagh_Banbridge_Craigavon",
          "dataId": "Armagh_Banbridge_Craigavon-id",
          "parent":rndm,
          "type": "option",
          "value": "Armagh City, Banbridge and Craigavon",
          "id_op": "Armagh_Banbridge_Craigavon",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Belfast",
          "dataId": "Belfast-id",
          "parent":rndm,
          "type": "option",
          "value": "Belfast",
          "id_op": "Belfast",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Causeway_Coast_Glens",
          "dataId": "Causeway_Coast_Glens-id",
          "parent":rndm,
          "type": "option",
          "value": "Causeway Coast and Glens",
          "id_op": "Causeway_Coast_Glens",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Derry_City_Strabane",
          "dataId": "Derry_City_Strabane-id",
          "parent":rndm,
          "type": "option",
          "value": "Derry City and Strabane",
          "id_op": "Derry_City_Strabane",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Fermanagh_Omagh",
          "dataId": "Fermanagh_Omagh-id",
          "parent":rndm,
          "type": "option",
          "value": "Fermanagh and Omagh",
          "id_op": "Fermanagh_Omagh",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Lisburn_Castlereagh",
          "dataId": "Lisburn_Castlereagh-id",
          "parent":rndm,
          "type": "option",
          "value": "Lisburn and Castlereagh",
          "id_op": "Lisburn_Castlereagh",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Mid_East_Antrim",
          "dataId": "Mid_East_Antrim-id",
          "parent":rndm,
          "type": "option",
          "value": "Mid and East Antrim",
          "id_op": "Mid_East_Antrim",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Mid_Ulster",
          "dataId": "Mid_Ulster-id",
          "parent":rndm,
          "type": "option",
          "value": "Mid Ulster",
          "id_op": "Mid_Ulster",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Newry_Mourne_Down",
          "dataId": "Newry_Mourne_Down-id",
          "parent":rndm,
          "type": "option",
          "value": "Newry, Mourne and Down",
          "id_op": "Newry_Mourne_Down",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      );
      temp=true;
    }
      optn = typeof cityList_el_pro_efb =="function"? cityList_el_pro_efb(rndm, rndm_1, temp, op_4, editState) :"null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="citylist" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
      dataTag = elementId;
      if (optn=="null") ui = public_pro_message();
      break;
    case 'multiselect':
    case 'payMultiselect':
      if (elementId == "multiselect") pay = "";
      dataTag = 'multiselect';
      const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
      let va = ``
      let sl =``
      if (editState != false) {
        optn = `<!--opt-->`;
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        const s = valj_efb[indx_parent].value.length>0 ? true :false
        for (const i of optns_obj) {
          let c = "efb bi-square efb"
          if(s==true && valj_efb[indx_parent].value.findIndex(x=>x==i.id_)!=-1){
             c = "bi-check-square text-info efb"
             va+= i.value+',' 
             sl +=i.id_ +' @efb!'}            
          optn += `<tr class="efb  efblist ${valj_efb[indx_parent].el_text_color}  ${pay}" data-id="${rndm}" data-name="${i.value}" data-row="${i.id_}" data-state="0" data-visible="1">
          <th scope="row" class="${c}"></th><td class="efb  ms col-12">${i.value}</td>
          ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${i.id_}-price" class="efb efb-crrncy">${Number(i.price).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
        </tr>  `
        }
      } else {
        optn = `
        <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.blue}" data-row="${op_3}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.blue}</td>
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_3}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
        </tr>
      <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.Red}" data-row="${op_4}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.Red}</td>                  
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_4}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
      </tr>
      <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.yellow}" data-row="${op_5}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.yellow}</td>
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_5}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
      </tr>  
       `
        const id = `menu-${rndm}`;
        optionElpush_efb(rndm, `${efb_var.text.blue}`, `${op_3}`, op_3 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.Red}`, `${op_4}`, op_4 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.yellow}`, `${op_5}`, op_5 ,dataTag);
      }
      ui = ` 
      ${label}
      <!--multiselect-->      
      <div class="efb  ${pos[3]} col-sm-12 listSelect px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"   id='${rndm}-f' data-id="${rndm}-el" >
        ${ttip}
        <div class="efb efblist  mx-0  inplist ${pay}  ${previewSate != true ? 'disabled' : ''} ${disabled}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color} bi-chevron-down" data-id="menu-${rndm}"   data-no="${valj_efb[iVJ].maxSelect}" data-min="${valj_efb[iVJ].minSelect}" data-parent="1" data-icon="1" data-select="${sl}"  data-vid='${rndm}' id="${rndm}_options" > ${va.length==0 ? efb_var.text.selectOption : va}</div>
        <div class="efb efblist mx-0  listContent shadow d-none border rounded-bottom bg-light" data-id="menu-${rndm}" data-list="menu-${rndm}">
        <table class="efb table menu-${rndm}">
         <thead class="efb efblist">
           <tr> <div class="efb searchSection efblist p-2 bg-light"> 
           <!-- <i class="efb efblist searchIcon  bi-search text-primary "></i> -->
               <input type="text" class="efb efblist search searchBox my-1 col-12 rounded " data-id="menu-${rndm}" data-tag="search" placeholder="🔍 ${efb_var.text.search}" onkeyup="FunSearchTableEfb('menu-${rndm}')" > </div>
         </tr> </thead>
         <tbody class="efb fs-7">                  
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
      ui = typeof html_el_pro_efb =="function" ? html_el_pro_efb(previewSate, rndm,iVJ) : public_pro_message()
      break;
    case 'yesNo':
      dataTag = elementId;
      ui = `
      ${label}
      ${ttip}
      ${typeof yesNi_el_pro_efb =="function" ? yesNi_el_pro_efb(previewSate,pos, rndm,iVJ) : public_pro_message()}
        ${desc}`;
      break;
    case 'link':
      dataTag = elementId;
      ui =typeof link_el_pro_efb =="function" ?  link_el_pro_efb (previewSate,pos, rndm,iVJ) : public_pro_message();
      break;
    case 'stripe':
      if(addons_emsFormBuilder.AdnSPF ==1){
        let sub = efb_var.text.onetime;
        let cl = `one`;
        if (valj_efb[0].paymentmethod != 'charge') {
          const n = `${valj_efb[0].paymentmethod}ly`
          sub = efb_var.text[n];
          cl = valj_efb[0].paymentmethod;
        }
        dataTag = elementId;
        ui =typeof add_ui_stripe_efb =="function" ? add_ui_stripe_efb(rndm,cl,sub): public_pro_message();
        valj_efb[0].type = "payment";
        form_type_emsFormBuilder=valj_efb[0].type;
      }else{
        alert_message_efb(efb_var.text.error, efb_var.text.IMAddonP, 20 , 'danger');
        const l = valj_efb.length -1;
        valj_efb.splice(l,1);
        return 'null';
      }
      break;
    case "persiaPay":
    case "zarinPal":
        if(  addons_emsFormBuilder.AdnPPF ==1 ){
          valj_efb[0].type = "payment";
          form_type_emsFormBuilder=valj_efb[0].type;
          dataTag = elementId;
          valj_efb[0].paymentmethod="charge"
          ui =typeof add_ui_persiaPay_efb =="function" ? add_ui_persiaPay_efb(rndm): public_pro_message();
        }else{
          alert_message_efb(efb_var.text.error, efb_var.text.IMAddonP, 20 , 'danger');
          const l = valj_efb.length -1;
          valj_efb.splice(l,1);
          return 'null';
        }
      break;
    case 'heading':
      dataTag = elementId;
      ui =typeof headning_el_pro_efb =="function" ?headning_el_pro_efb (rndm,pos,iVJ) :public_pro_message();
      break;
    case 'booking':
      dataTag = elementId;
      break;
    case 'pointr10':
      temp =typeof pointer10_el_pro_efb =="function" ? pointer10_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'pointr5':
      temp =typeof pointer5_el_pro_efb =="function" ? pointer5_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'smartcr':
      temp =typeof smartcr_el_pro_efb =="function" ? smartcr_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'table_matrix':
        type_field_efb = elementId;
        dataTag = elementId;      
         col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[iVJ].op_style)) : ''
        if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox") pay = "";
        if (editState != false) {
          optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
          for (const i of optns_obj) {
            prc = i.hasOwnProperty('price') ? Number(i.price) : 0;
            optn += `
            <!-- start r_matrix -->
            <div class="efb  col-sm-12 ${col} row my-1   t-matrix" data-id="${i.id_}" data-parent="${i.parent}" id="${i.id_}-v">
              <div class="efb my-2  col-md-8 fs-6  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${i.id_}_lab">${i.value}</div>
              <div class="efb col-md-4  d-flex justify-content-${position_l_efb} " ${aire_describedby} id="${i.id_}" > 
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <input type="hidden" class="efb emsFormBuilder_v" data-vid="${i.id_}" data-parent="${i.parent} data-type="rating"  id="${i.id_}-point-rating" >
              </div>                
              <hr class="efb t-matrix my-1">                                       
          </div>
          <!-- end r_matrix -->
            `
          }
        } else {
          const op_1 = Math.random().toString(36).substr(2, 9);
          const op_2 = Math.random().toString(36).substr(2, 9);
          const pv = 0;
          optn = `
          <div class="efb   col-sm-12 row my-1  t-matrix" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
              <div class="efb my-2 col-md-8 fs-6 ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${op_1}_lab">${efb_var.text.newOption}</div>
              <div class="efb col-md-4 d-flex justify-content-${position_l_efb} "  id="${op_1}" > 
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <input type="hidden" data-vid="${op_1}" data-parent="${rndm} data-type="rating"  id="${op_1}-point-rating" >
              </div>  
              <hr class="efb t-matrix my-1">                                          
          </div>
          <div class="efb col-md-12    col-sm-12 row my-1  t-matrix" data-id="${op_2}" data-parent="${rndm}" id="${op_2}-v">
              <div class="efb my-2 col-md-8 fs-6 ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${op_2}_lab">${efb_var.text.newOption}</div>
              <div class="efb col-md-4  d-flex justify-content-${position_l_efb} " id="${op_2}" > 
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <input type="hidden" data-vid="${op_2}" data-parent="${rndm} data-type="rating"  id="${op_2}-point-rating" >
              </div> 
              <hr class="efb t-matrix my-1">                                           
          </div>`
          r_matrix_push_efb(rndm, efb_var.text.newOption, op_1, op_1);
          r_matrix_push_efb(rndm, efb_var.text.newOption, op_2, op_2);
        }
        ui = `
        <!-- table matrix -->
        ${label}
          <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"   data-id="${rndm}-el" id='${rndm}-f'>
            ${ttip}
            <div class="efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${col != '' ? 'row col-md-12' : ''} efb1  ${valj_efb[iVJ].classes.replace(`,`, ` `)}" id="${rndm}_options">
              ${optn}
            </div>
            <div class="efb  mb-3">${desc}</div>
          <!-- end table matrix -->
          `
        break;

    case 'prcfld':
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
        maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
        minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;    
        minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
        dataTag = valj_efb[0].hasOwnProperty('currency')==false ? 'usd' : valj_efb[0].currency;
        classes = new Intl.NumberFormat(lan_name_emsFormBuilder, { style: 'currency', currency: dataTag, currencyDisplay: 'narrowSymbol' }).formatToParts(0).find(part => part.type === 'currency').value;
        dataTag = `<span class="efb input-group-text crrncy-clss">${ classes}</span>`
        classes = `form-control ${valj_efb[iVJ].el_border_color} `;
        ui = `
        ${label}
        <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
          ${ttip}
          <div class="efb input-group m-0 p-0">
           ${efb_var.rtl==true ? '' :dataTag}
          <input type="number"   class="efb input-efb px-2 mb-0 payefb emsFormBuilder_v  ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-id="${rndm}-el" data-vid='${rndm}' data-css="${rndm}" id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} ${aire_describedby} ${maxlen} ${minlen} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          ${efb_var.rtl==true ? dataTag :''}
          </div>
          ${desc}`
        dataTag = elementId;
        break;
    case 'ttlprc':
        dataTag = elementId;
        if(valj_efb[0].hasOwnProperty('currency')==false ) Object.assign(valj_efb[0], {currency: 'USD'});
        ui = `
        ${label}
        <div class="efb  ${pos[3]} col-sm-12  pt-2 pb-1 px-0 mx-0 ttEfb show" id='${rndm}-f'>
        ${typeof add_ui_totalprice_efb =="function" ? add_ui_totalprice_efb(rndm,iVJ): public_pro_message()}
        ${desc}      
        `
        form_type_emsFormBuilder=valj_efb[0].type;
      break;
  }
  const addDeleteBtnState = (formName_Efb == "login" && (valj_efb[iVJ].id_ == "emaillogin" || valj_efb[iVJ].id_ == "passwordlogin")) || (formName_Efb == "register" && (valj_efb[iVJ].id_ == "usernameRegisterEFB" || valj_efb[iVJ].id_ == "passwordRegisterEFB" || valj_efb[iVJ].id_ == "emailRegisterEFB")) ? true : false;
  if (elementId != "form" && dataTag != "step" && ((previewSate == true && elementId != 'option') || previewSate != true)) 
    {
    const pro_el = valj_efb[iVJ].hasOwnProperty('pro') ? valj_efb[iVJ].pro :false ;
    const contorl = ` <div class="efb btn-edit-holder d-none efb" id="btnSetting-${rndm}-id">
    <button type="button" class="efb  btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"  data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.edit}" onclick="show_setting_window_efb('${rndm}-id')">
    <div class="icon-container efb"><i class="efb bi-gear-wide-connected  text-success BtnSideEfb"></i></div>
    </button>
    <button type="button" class="efb  btn btn-edit btn-sm" id="dupElEFb-${rndm}" data-id="${rndm}-id"  data-bs-toggle="tooltip"  title="${efb_var.text.duplicate}" onclick="show_duplicate_fun('${rndm}','${valj_efb[iVJ].name}')">
    <i class="efb  bi-clipboard-plus text-muted"></i>
    </button>
    ${addDeleteBtnState ? '' : `<button type="button" class="efb  btn btn-edit btn-sm" id="deleteElEFb"   data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.delete}" onclick="show_delete_window_efb('${rndm}-id' ,${iVJ})"> <i class="efb  bi-x-lg text-danger"></i></button>`}
    <span class="efb  btn btn-edit btn-sm "  id="moveElEFb" onclick="move_show_efb()"><i class="efb text-dark bi-arrows-move"></i></span>
    `
    const proActiv = `
    <div class="efb btn-edit-holder efb d-none zindex-10-efb " id="btnSetting-${rndm}-id">
    <button type="button" class="efb btn efb pro-bg btn-pro-efb btn-sm px-2 mx-3" id="pro" data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.proVersion}" onclick="pro_show_efb(1)"> 
    <i class="efb  bi-gem pro"> ${efb_var.text.pro}</i>`;
    endTags = previewSate == false ? `</button> </button></div></div>` : `</div></div>`
    const tagId = elementId == "firstName" || elementId == "lastName" || elementId == "address" || elementId == "address_line" || elementId == "postalcode" ? 'text' : elementId;
    const tagT = elementId =="esign" || elementId=="yesNo" || elementId=="rating" ? '' : 'def'   
    newElement += `
    ${previewSate == false  ? `<setion class="efb my-1 px-0 mx-0 ttEfb ${previewSate != true ? disabled : ""} ${previewSate == false && valj_efb[iVJ].hidden==1 ? "hidden" : ""} ${previewSate == true && (pos[1] == "col-md-12" || pos[1] == "col-md-10") ? `mx-0 px-0` : 'position-relative'} ${previewSate == true ? `${pos[0]} ${pos[1]}` : `${ps}`} row col-sm-12 ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >` : ''}
    ${previewSate == false && valj_efb[iVJ].hidden==1 ? hiddenMarkEl(valj_efb[iVJ].id_) : ''}
    <div class="efb my-1 mx-0  ${elementId} ${tagT} ${hidden} ${previewSate == true ? disabled : ""}  ttEfb ${previewSate == true ? `${pos[0]} ${pos[1]}` : ` row`} col-sm-12 ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >
    ${(previewSate == true && elementId != 'option') || previewSate != true ? ui : ''}
    ${previewSate != true && pro_efb == false && pro_el==true ? proActiv : ''}
    ${previewSate != true ? contorl : '<!--efb.app-->'}
    ${previewSate != true && pro_efb == false && pro_el==true  ? '</div>' : ''}
    ${(previewSate == true && elementId != 'option' && elementId != "html" && elementId != "stripe" && elementId != "heading" && elementId != "link") || previewSate != true ? endTags : '</div>'}
    ${previewSate == false  ? ` </setion><!--endTag EFB-->` :''}
     <!--endTag EFB-->
    `;
  } else if (dataTag == 'step' && previewSate != true) {
    if (elementId == "steps" && pro_efb == false && Number(step_el_efb) == 3) {
      amount_el_efb = Number(amount_el_efb) - 1;
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
const public_pro_message= ()=>{
 return `<div class="efb text-white fs-6 bg-danger px-1 rounded px-2">${efb_var.text.tfnapca}</div>`
}
const hiddenMarkEl=(id) =>{ return`
<div id="${id}-hidden">
<!--<button type="button" class="efb btn efb pro-bg btn-pro-efb btn-sm px-2 mx-3" id="pro"  data-bs-toggle="tooltip" onclick="pro_show_efb(1)"> -->
<i class="efb  bi-eye-slash pro efb-hidden mx-3" > ${efb_var.text.hField}</i>
</div>
`}
const funSetPosElEfb = (dataId, position) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx != -1) {
    valj_efb[indx].label_position = position
  }
  if (valj_efb[indx].type != "stripe"  && valj_efb[indx].type != "html") get_position_col_el(dataId, true)
}
const funSetAlignElEfb = (dataId, align, element) => {
  const indx = dataId!='button_group_' && dataId!='Next_' ? valj_efb.findIndex(x => x.dataId == dataId) :0;
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
    case 'buttons':
      const id = valj_efb[0].steps<2 ? 'f_btn_send_efb' : 'f_button_form_np';
      const elm_ = document.getElementById(id)
      elm_.className = alignChangerElEfb(elm_.className, align)
      valj_efb[0].hasOwnProperty('btns_align') ? valj_efb[0].btns_align = align : Object.assign(valj_efb[0], { btns_align: align });
      break;
      default:
        break;
  }
}
const loadingShow_efb = (title) => {
  return `<div class="efb modal-dialog modal-dialog-centered efb"  id="settingModalEfb_" >
 <div class="efb modal-content efb " id="settingModalEfb-sections">
     <div class="efb modal-header efb">
         <h5 class="efb modal-title fs-5" ><i class="efb bi-ui-checks mx-2 efb" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${title ? title : efb_var.text.loading} </span></h5>
     </div>
     <div class="efb modal-body efb" id="settingModalEfb-body">
         ${efbLoadingCard()}
     </div>
 </div>
</div>`
}
let fun_handle_buttons_efb = (state) => {
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
  const btnPos = id != "dropZoneEFB" ? ' text-center mx-3' : ''
  let dis = ''
  if (true) {
    let t = valj_efb.findIndex(x => x.type == "stripe");
     t = t==-1 ? valj_efb.findIndex(x => x.type == "persiaPay") : t;
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
const colMdChangerEfb = (classes, value) => { return classes.replace(/\bcol-md+-\d+/, ` ${value} `) ?? `${classes} ${value} ` ; }
const PxChangerEfb = (classes, value) => { return classes.replace(/\bpx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const MxChangerEfb = (classes, value) => { return classes.replace(/\bmx+-\d+/, ` ${value} `) ?? `${classes} ${value} `; }
const btnChangerEfb = (classes, value) => { return classes.replace(/\bbtn-outline-+\w+|\bbtn-+\w+/, ` ${value} `) ?? `${classes} ${value} `; }
const open_whiteStudio_efb = (state) => {
const sub =lan_subdomain_wsteam_efb();
  let link = `https://${sub}whitestudio.team/document/`;
  if(efb_var.language != "fa_IR"){
  switch (state) {
    case 'mapErorr':
      link += `How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder`
      break;
    case 'pro':
      link = `https://${sub}whitestudio.team/#price`
      break;
    case 'publishForm':
      link = `https://www.youtube.com/watch?v=XjBPQExEvPE`
      break;
    case 'emptyStep':
      link += `how-to-create-your-first-form-with-easy-form-builder#empty-step-alert`
      break;
    case 'notInput':
      link += `?notInputExists`
      break;
    case 'pickupByUser':
      link = `How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder#how-to-add-a-location-picker-when-creating-form`
      break;
    case 'paymentform':
      link = `How-to-Create-a-Payment-Form-in-Easy-Form-Builder`
      break;
  }
}else{
  link = `https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/`;
  switch (state) {
    case 'mapErorr':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%d9%86%d8%aa%d8%ae%d8%a7%d8%a8%da%af%d8%b1-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d9%85%da%a9%d8%a7%d9%86%db%8c-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d8%ac%d8%ba/`
      break;
    case 'pro':
      link = `https://easyformbuilder.ir/#price`
      break;
    case 'publishForm':
      case 'notInput':
      link += "%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/";
      break;
    case 'emptyStep':
      link += `%DA%86%DA%AF%D9%88%D9%86%D9%87-%D9%81%D8%B1%D9%85-%D8%AA%D9%88%D8%B3%D8%B7-%D9%81%D8%B1%D9%85-%D8%B3%D8%A7%D8%B2-%D8%A2%D8%B3%D8%A7%D9%86-%D8%AF%D8%B1-%D9%88%D8%B1%D8%AF%D9%BE%D8%B1%D8%B3-%D8%A8%D8%B3/`
      break;
    case 'pickupByUser':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%d9%86%d8%aa%d8%ae%d8%a7%d8%a8%da%af%d8%b1-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d9%85%da%a9%d8%a7%d9%86%db%8c-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d8%ac%d8%ba/`
      break;
    case 'paymentform':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%af%d8%b1%da%af%d8%a7%d9%87-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d8%a7%db%8c%d8%b1%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%a8%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2/`
      break;
  }
}
  window.open(link, "_blank")
}
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
function copyCodeEfb(id) {
  var copyText = document.getElementById(id);
  copyText.select();
  copyText.setSelectionRange(0, 99999); 
  document.execCommand("copy");
  alert_message_efb(efb_var.text.copiedClipboard, '', 6)
}
function validExtensions_efb_fun(type, fileType,indx) {
  type= type.toLowerCase();
  const tt = valj_efb.length>1 && valj_efb[indx].hasOwnProperty('file_ctype') ? valj_efb[indx].file_ctype.replaceAll(',',' , ') : '';
  filetype_efb={'image':'image/png, image/jpeg, image/jpg, image/gif',
  'media':'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg', 
  'document':'.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
  'zip':'.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar',
  'allformat':'image/png, image/jpeg, image/jpg, image/gif audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg .xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, .heic, image/heic, video/mov, .mov, video/quicktime',
  'customize':tt
  }
  return filetype_efb[type].includes(fileType) ;
}
let steps_len_efb 
function handle_navbtn_efb(steps, device) {
  var next_s_efb, prev_s_efb; 
  var opacity_efb;
  steps_len_efb = Number(steps) + 1;
  current_s_efb = 1;
  setProgressBar_efb(current_s_efb, steps_len_efb);
  if (steps > 1) {
    if (valj_efb[0].type == "payment" && preview_efb != true) {
      let state = valj_efb.findIndex(x => x.type == "stripe");
      state = state == -1 ? valj_efb.findIndex(x => x.type == "persiaPay") : state;
      if (valj_efb[state].step == current_s_efb) {
        document.getElementById("next_efb").classList.add("disabled");
      }
    }
    if (current_s_efb == 1) {
      document.getElementById("prev_efb").classList.toggle("d-none");
    }
    document.getElementById("next_efb").addEventListener("click", function () {
      var cp = current_s_efb + 1;
      var state = true;
      if (preview_efb == false && fun_validation_efb() == false) {
        state = false;
        return false;
      }
      setTimeout(function () {
        var url = new URL(window.location);
        history.pushState("EFBstep-" + cp, null, url);
        if (state = true) {
          if (cp == steps_len_efb) {
            document.getElementById("prev_efb").classList.add("d-none");
            document.getElementById("next_efb").classList.add("d-none");
            send_data_efb();
            document.getElementById('efbform').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
          }
          var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
          current_s.classList.add("d-none");
          next_s_efb = current_s.nextElementSibling;
          var nxt = "" + (current_s_efb + 1) + "";
          if(Number(valj_efb[0].show_icon)!=1){
            document.querySelector('[data-step="icon-s-' + nxt + '-efb"]').classList.add("active");
          }
          document.querySelector('[data-step="step-' + nxt + '-efb"]').classList.toggle("d-none");
          if(next_s_efb)next_s_efb.classList.remove('d-none');
          if(document.getElementById('gRecaptcha'))document.getElementById('gRecaptcha').classList.add('d-none');
          current_s_efb += 1;
          localStorage.setItem("step", current_s_efb);
          setProgressBar_efb(current_s_efb, steps_len_efb);
          if (current_s_efb <= steps) {
            var val = valj_efb.find(x => x.step == nxt);
            if(Number(valj_efb[0].show_icon)!=1){
              document.getElementById("title_efb").className = val["label_text_color"];
              document.getElementById("desc_efb").className = val["message_text_color"];
              document.getElementById("title_efb").textContent = val["name"];
              document.getElementById("desc_efb").textContent = val["message"];
              document.getElementById("title_efb").classList.add("text-center", "efb", "mt-1");
              document.getElementById("desc_efb").classList.add("text-center", "efb", "fs-7");
            }
            document.getElementById("prev_efb").classList.remove("d-none");
          }
          if (current_s_efb == steps_len_efb - 1) {
            if (sitekye_emsFormBuilder && sitekye_emsFormBuilder.length > 1 && valj_efb[0].captcha == true) {
              document.getElementById("next_efb").classList.toggle("disabled");
              document.getElementById("gRecaptcha").classList.remove("d-none");
            }
            var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${efb_var.text.send}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
            document.getElementById("next_efb").innerHTML = val;
          }
          if (valj_efb[0].type == "payment" && preview_efb != true) {
            let state = valj_efb.findIndex(x => x.type == "stripe");
            state = state == -1 ? valj_efb.findIndex(x => x.type == "persiaPay") : state;
            if (valj_efb[state].step == current_s_efb && !efb_var.hasOwnProperty("payId") == true) {
              document.getElementById("next_efb").classList.add("disabled");
            }
          }
          if (document.getElementById("body_efb")) {
            document.getElementById("body_efb").scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
          }
        }
      }, 200);
    });
    document.getElementById("prev_efb").addEventListener("click", function () {
      prev_btn_efb();
    });
  } else {
    document.getElementById("btn_send_efb").addEventListener("click", function () {
      var state = true;
      if (preview_efb == false && fun_validation_efb() == false) {
        state = false;
        return false;
      }
      setTimeout(function () {
        if (state == true) {
         if(Number(valj_efb[0].show_icon)!=1)  document.querySelector('[data-step="icon-s-' + (current_s_efb + 1) + '-efb"]').classList.add("active");
          document.querySelector('[data-step="step-' + (current_s_efb + 1) + '-efb"]').classList.toggle("d-none");
          document.getElementById("btn_send_efb").classList.toggle("d-none");
          var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
          next_s_efb = current_s.nextElementSibling;
          current_s.classList.add('d-none');
          if(next_s_efb)next_s_efb.classList.remove('d-none');
          if(document.getElementById('gRecaptcha'))document.getElementById('gRecaptcha').classList.add('d-none');     
          current_s_efb += 1;
          setProgressBar_efb(current_s_efb, steps_len_efb);
          send_data_efb();
        }
        if (document.getElementById("body_efb")) {
          document.getElementById("body_efb").scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
        }
      }, 200);
    });
  }
 if(document.querySelector(".submit")){
   document.querySelector(".submit").addEventListener("click", function () {
     return false;
   });
 }
}
function prev_btn_efb() {
  var cs = current_s_efb;
  if (cs == 2) {
    var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
    document.getElementById("next_efb").innerHTML = val;
    document.getElementById("next_efb").classList.toggle("d-none");
  } else if (cs == valj_efb[0].steps) {
    var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${valj_efb[0].button_Next_text}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
    document.getElementById("next_efb").innerHTML = val;
    if (sitekye_emsFormBuilder.length > 1 && (valj_efb[0].captcha == 1 || valj_efb[0].captcha == "1")) {
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
      document.getElementById("desc_efb").textContent = val["message"];
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
function ReadyElForViewEfb(content) {
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
    document.getElementById('parentMobileView-efb').innerHTML = "";
    document.getElementById('parentMobileView-efb').appendChild(iframe)
  }, 1000)
}
localStorage.getItem('count_view') ? localStorage.setItem(`count_view`, parseInt(localStorage.getItem('count_view')) + 1) : localStorage.setItem(`count_view`, 0)
function alert_message_efb(title, message, sec, alert) {
  sec = sec * 1000
  alert = alert ? `alert-${alert}` : 'alert-info';
  const id_ = document.getElementById(`step-${current_s_efb}-efb-msg`) ? `step-${current_s_efb}-efb-msg` : `body_efb`
  let id = document.getElementById('body_efb')  ? id_ : 'alert_efb';
  if (id=="body_efb" && Number(document.getElementById('body_efb').offsetWidth)<380) id='alert_content_efb'
  if (document.getElementById('alert_efb')==null){
    document.getElementById("body_efb").innerHTML += `<div id='alert_efb' class='efb mx-5'></div>`;
  }
  document.getElementById(id).innerHTML += ` <div id="alert_content_efb" class="efb alert_efb  alert ${alert} alert-dismissible ${efb_var.text.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <h5 class="efb alert-heading fs-4">${title}</h5>
    <div>${String(message)}</div>
    <button type="button" class="efb btn-close" data-dismiss="alert" aria-label="Close" onclick="close_msg_efb()"></button>
  </div>`
  document.getElementById(id).scrollIntoView({behavior: "smooth", block: "center", inline: "center"}, true);
  setTimeout(() => {
    if(document.querySelector(".alert_efb")){
      document.querySelector(".alert_efb").style.display = "none";
      document.getElementById("alert_efb").innerHTML = "";
    }
  }, sec);
  //window.scrollTo({ top: document.getElementById(id).scrollHeight, behavior: 'smooth' });
}
function close_msg_efb(){
  const v=document.getElementById('alert_content_efb')
  if (v) {
    v.remove();
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
function previewFormEfb(state) {
  if (state != "run") {
    state_efb = "view";
    preview_efb = true;
    activeEl_efb = 0;
  }
  let content = `<!--efb.app-->`
  let step_no = 0;
  let head = ``
  let icons = ``
  let pro_bar = ``
  const id = state == "run" ? 'body_efb' : 'settingModalEfb_';
  const len = valj_efb.length;
  const p = calPLenEfb(len)
  let timeout = state == 'run' ? 0 : len * p;
  timeout < 1700 ? timeout = 1700 : 0;
  timeout = state == 'run' ? 0 : timeout;
  if (state != "show" && state != "run") {
    if (valj_efb.length > 2) { sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)) } else {
      show_modal_efb(`<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.formNotFound}</p></div>`, efb_var.text.previewForm, '', 'saveBox');
      state_modal_show_efb(1)
      return;
    }
    if (state == "new") {
      preview_form_new_efb();
      return;
    }else if (state == "pc"){
      show_modal_efb(efbLoadingCard(), efb_var.text.previewForm, '', 'saveBox')
      state_modal_show_efb(1)
    }
  }
  try {
    let count =0;
    valj_efb.forEach((value, index) => {
      let t = value.type.toLowerCase();
      if (valj_efb[index].type != "html" && valj_efb[index].type != "link" && valj_efb[index].type != "heading" && valj_efb[index].type != "persiaPay") Object.entries(valj_efb[index]).forEach(([key, val]) => { fun_addStyle_costumize_efb(val.toString(), key, index) });
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb  ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb  fs-5  ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="efb my-2 mx-0 px-0 steps-efb efb row">` : `<!-- fieldset!!!? --><div id="step-${Number(step_no)-1}-efb-msg"></div></fieldset><fieldset data-step="step-${step_no}-efb"  class="efb my-2 mx-0 px-0 steps-efb efb row d-none">`
        if (valj_efb[0].show_icon == false) { }
        if (valj_efb[0].hasOwnProperty('dShowBg') && valj_efb[0].dShowBg == true && state == "run") { document.getElementById('body_efb').classList.remove('card') }
      }
      if (value.type == 'step' && value.type != 'html') {
        steps_index_efb.push(index)
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option' && value.type != 'r_matrix' && index>0) {
        content += addNewElement(value.type, value.id_, true, true);
        if (value.type == "html") content += "<!--testHTML-->"
      }
      if((value.hasOwnProperty('disabled') && value.disabled==true && value.hasOwnProperty('hidden')==false)
      || (value.hasOwnProperty('disabled') && value.disabled==true &&
      value.hasOwnProperty('hidden')==true && value.hidden==false)) return;
      if( value.hasOwnProperty('value') && (value.type =='email'|| value.type =='text'|| value.type =='password'|| value.type =='tel'
        || value.type =='number'|| value.type =='url'|| value.type =='textarea'|| value.type =='range' || value.type =='prcfld')){
       if(typeof fun_sendBack_emsFormBuilder=="function" && value.value.length>=1) fun_sendBack_emsFormBuilder({ id_: value.id_, name: value.name, id_ob: value.id_+"_", amount: value.amount, type: value.type, value: value.value, session: sessionPub_emsFormBuilder });
      }else if(typeof fun_sendBack_emsFormBuilder=="function" && value.hasOwnProperty('value') && value.value.length>0 && value.type !='option' ){
        let o=[]
        if(t.includes('radio')==true || value.type=='radio'){
          count+=1;
          let ch = valj_efb.find(x=>x.id_==value.value || x.id_old==value.value);
          o=[{
            id_: value.id_,
            name: value.name,
            id_ob: ch.id_,
            amount: value.amount,
            type: value.type,
            value: ch.value,
            session: sessionPub_emsFormBuilder
        }]
        if(t.includes('pay')){
          Object.assign(o.at(-1),{price:ch.price})
        }
        t=1;
        }else if (t.includes('checkbox')==true){
          count+=1;
          for(let c of value.value){
            let ch = valj_efb.find(x=>x.id_==c);
            o.push({
              id_: value.id_,
              name: value.name,
              id_ob: ch.id_,
              amount: value.amount,
              type: value.type,
              value: ch.value,
              session: sessionPub_emsFormBuilder
              })
            if(t.includes('pay')){
              Object.assign(o.at(-1),{price:ch.price})
            }
          }
          t=1;
        }else if(t.includes('multi')==true){
          count+=1;
          let val='';
          for(let c of value.value){
            let ch = valj_efb.find(x=>x.id_==c);
            val += ch.value+'@efb!'
          }
              o=[{
                id_: value.id_,
                name: value.name,              
                amount: value.amount,
                type: value.type,
                value: val,
                session: sessionPub_emsFormBuilder
            }]
            t=1;
        }else if(t.includes('select')==true || t.includes('stateprovince')==true || t.includes('conturylist')==true){
          count+=1;
           let ch = valj_efb.find(x=>x.id_==value.value);
          o=[{
            id_: value.id_,
            name: value.name,
            id_ob: ch.id_,
            amount: value.amount,
            type: value.type,
            value: ch.value,
            session: sessionPub_emsFormBuilder
        }]
        t=1;
        }
        if(t===1){
          for(let i in o){
            fun_sendBack_emsFormBuilder(o[i]);
          }
        }
      }
    })
    step_no += 1;
    const wv = `
  <div class="efb text-center ">
  ${loading_messge_efb()}
  <p class="efb fs-5">${efb_var.text.stf}</p>
  </div>
  `;
  //console.log(wv);
    content += `
           ${valj_efb[0].hasOwnProperty('logic')==false ||(valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==false)  ? fun_captcha_load_efb() : '<!--logic efb--!>'}
           </fieldset>
          <fieldset data-step="step-${step_no}-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
            ${valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==true  ? fun_captcha_load_efb() :wv}                
            <!-- fieldset2 -->
            <div id="step-2-efb-msg"></div>
            </fieldset>`
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb  ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb  fs-5 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch (error) {
    console.error(`Preview of Pc Form has an Error`, error)
  }
  if (content.length > 10){
    const bgc = valj_efb[0].hasOwnProperty('prg_bar_color') ?valj_efb[0].prg_bar_color: 'btn-primary'
     content += `</div>`
    head = `${Number(valj_efb[0].show_icon)!=1 ? `<ul id="steps-efb" class="efb mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="efb d-flex justify-content-center" id="f-progress-efb"><div class="efb progress mx-3 w-100 ${bgc}"><div class="efb  progress-bar-efb   progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div></div><br> ` : ``}
    `}
  const idn = state == "pre" ? "pre-form-efb" : "pre-efb";
  document.getElementById(id).classList.add(idn)
  content = `  
    <div class="efb px-0 pt-2 pb-0 my-1 col-12 mb-2" id="view-efb">
    ${Number(valj_efb[0].show_icon)!=1 ? `<h4 id="title_efb" class="efb fs-3 ${valj_efb[1].label_text_color} text-center mt-3 mb-0">${valj_efb[1].name}</h4><p id="desc_efb" class="efb ${valj_efb[1].message_text_color} text-center  fs-6 mb-2">${valj_efb[1].message}</p>` : ``}
      ${head} <div class="efb mt-1 px-2">${content}</div> 
    </div>
    `
  const t = valj_efb[0].steps == 1 ? 0 : 1;
  if (state == 'pc') {
    document.getElementById('dropZoneEFB').innerHTML = '';
    content = `<!-- find xxxx -->` + content;
    show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
    add_buttons_zone_efb(t, 'settingModalEfb-body')
  } else if (state == 'pre') {
    show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
    add_buttons_zone_efb(t, 'settingModalEfb-body')
  } else if (state == "mobile") {
    const frame = `
        <div class="efb smartphone-efb">
        <div class="efb content efb" >
            <div id="parentMobileView-efb">
            ${efbLoadingCard()}
            </div>
        </div>
      </div> `
    show_modal_efb(frame, efb_var.text.mobilePreview, 'bi-phone', 'settingBox');
    ReadyElForViewEfb(content)
  } else {   
    document.getElementById(id).innerHTML ='<form id="efbform">'+ content + add_buttons_zone_efb(t, id) + '</form>';
    if (valj_efb[0].type == "payment") {      
     if (efb_var.paymentGateway == "stripe" && typeof post_api_stripe_apay_efb =="function") post_api_stripe_apay_efb();
    }
  }
  let ttype ='text'
  try {
    const len = valj_efb.length;
    valj_efb.forEach((v, i) => {
      let disabled = v.hasOwnProperty('disabled') ? v.disabled : false;
      switch (v.type) {
        case "maps":
          efbCreateMap(v.id_ ,v,false)
          break;
        case "esign":
          c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
          c2d_contex_efb.lineWidth = 5;
          c2d_contex_efb.strokeStyle = "#000000";
          if(disabled)return;
          document.getElementById(`${v.id_}_`).addEventListener("mousedown", (e) => {
            draw_mouse_efb = true;
            c2d_contex_efb = document.getElementById(`${v.id_}_`).getContext("2d");
            canvas_id_efb = v.id_;
            lastMousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e);
          }, false);
          document.getElementById(`${v.id_}_`).addEventListener("mouseup", (e) => {
            draw_mouse_efb = false;
            const el = document.getElementById(`${v.id_}-sig-data`);
            const value = el.value;
            document.getElementById(`${v.id_}_-message`).classList.remove('show');
            const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: value, session: sessionPub_emsFormBuilder }];
            fun_sendBack_emsFormBuilder(o[0]);
          }, false);
          document.getElementById(`${v.id_}_`).addEventListener("mousemove", (e) => { mousePostion_efb = getmousePostion_efb(document.getElementById(`${v.id_}_`), e); }, false);
          document.getElementById(`${v.id_}_`).addEventListener("touchmove", (e) => {
            e.preventDefault();
            document.body.style.overflow = 'hidden';
            let touch = e.touches[0];
            let ms = new MouseEvent("mousemove", { clientY: touch.clientY, clientX: touch.clientX });
            document.getElementById(`${v.id_}_`).dispatchEvent(ms);
          }, false);
          document.getElementById(`${v.id_}_`).addEventListener("touchstart", (e) => {
            e.preventDefault();
            document.body.style.overflow = 'hidden';
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
            e.preventDefault();
            document.body.style.overflow = 'auto';
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
          let callback = 1;
          function mutlselect(len) {
            setTimeout(() => {
              callback += 1;
              const opd = document.querySelector(`[data-id='${v.id_}_options']`);
              if (opd != null) {
                const corner = v.hasOwnProperty('corner') ? v.corner: 'efb-square';
                opd.className += ` efb emsFormBuilder_v  ${corner} ${v.el_border_color} ${v.el_text_size} ${v.el_height}`;
                opd.onclick = function getMultiSelectvalue() {
                }
                jQuery(function () {
                  jQuery('.selectpicker').selectpicker();
                });
              } else {
                mutlselect(10 * callback);
              }
            }, len);
          }
          mutlselect(len);          
          break;
        case "rating":
          break;
        case "dadfile":
          if(disabled)return;
          set_dadfile_fun_efb(v.id_, i)
          break;
        case 'ardate':
        case 'pdate':
          ttype= v.type
          break;
      }
    })
  } catch {
    console.error(`Preview of Pc Form has an Error`)
  }
  if (state != 'mobile') (valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic==true) ? logic_handle_navbtn_efb(valj_efb[0].steps, 'pc'):  handle_navbtn_efb(valj_efb[0].steps, 'pc')
  if (state == 'run') {
    sitekye_emsFormBuilder.length > 1 ? loadCaptcha_efb() : '';
    createStepsOfPublic()
  }
  else if(state == 'pc'){    
    for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
      valueJson_ws = valj_efb;
      el.addEventListener("change", (e) => {
        handle_change_event_efb(el);
      });
    }
  }
  step_el_efb = Number(valj_efb[0].steps);
  if ( state == 'run' && 
  ( (addons_emsFormBuilder.AdnOF==1 && typeof valj_efb[0].AfLnFrm =='string' &&  valj_efb[0].AfLnFrm==1) ) || (valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string") ) { fun_offline_Efb() 
  }
  if (ttype=='ardate'){
    if(typeof  load_hijir_data_picker_efb=="function"){ 
      load_hijir_data_picker_efb()
    }else{
      setTimeout(() => {
        alert_message_efb(efb_var.text.iaddon, efb_var.text.IMAddonAD, 20 , 'info')        
      }, 1000);
    }
  }else if(ttype=='pdate'){
    if(typeof load_style_persian_data_picker_efb =="function"){ 
    load_style_persian_data_picker_efb();
  }else{
    setTimeout(() => {
      alert_message_efb(efb_var.text.iaddon, efb_var.text.IMAddonPD, 20 , 'info');        
    }, 1000);
  }
  }
}
function fun_prev_send() {
  var stp = Number(valj_efb[0].steps) + 1;
  document.getElementById('efb-final-step').innerHTML = loading_messge_efb();
  var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
  prev_s_efb = document.querySelector('[data-step="step-' + (current_s_efb-1) + '-efb"]');
  if(Number(valj_efb[0].show_icon)!=1)  document.querySelector('[data-step="icon-s-' + current_s_efb + '-efb"]').classList.remove("active");
  document.querySelector('[data-step="step-' + current_s_efb + '-efb"]').classList.toggle("d-none");
  if (stp == 2) {
    document.getElementById("btn_send_efb").classList.toggle("d-none");
    if(document.getElementById("gRecaptcha")) document.getElementById("gRecaptcha").classList.toggle("d-none");
  } else {
    document.getElementById("next_efb").classList.toggle("d-none");
  }
  var s = "" + (current_s_efb - 1) + "";
  var val = valj_efb.find(x => x.step == s);
  if(Number(valj_efb[0].show_icon)!=1){
    document.getElementById("title_efb").className = val['label_text_color'] + ' efb text-center mt-1';
    document.getElementById("desc_efb").className = val['message_text_color'] + " efb text-center fs-6";
    document.getElementById("title_efb").textContent = val['name'];
    document.getElementById("desc_efb").textContent = val['message'];
  }
  if(document.getElementById("prev_efb"))document.getElementById("prev_efb").classList.toggle("d-none");
  current_s.classList.add('d-none');
  prev_s_efb.classList.remove('d-none');
  current_s_efb -= 1;
  localStorage.setItem('step', current_s_efb);
  setProgressBar_efb(current_s_efb, stp);
}
function verifyCaptcha(token) {
  if (token.length > 1) {
    const id = valj_efb[0].steps > 1 ? 'next_efb' : 'btn_send_efb'
    document.getElementById(id).classList.remove('disabled');
    setTimeout(() => { timeOutCaptcha() }, 61000)
  }
}
function timeOutCaptcha() {
  const id = valj_efb[0].steps > 1 ? 'next_efb' : 'btn_send_efb'
  document.getElementById(id).classList.add('disabled');
  alert_message_efb(efb_var.text.error, efb_var.text.errorVerifyingRecaptcha, 7, 'warning');
}
fun_el_select_in_efb = (el) => { return  el == 'select' || el == 'multiselect' || el == 'conturyList' || el == 'stateProvince' || el == 'cityList'||  el == 'paySelect' || el == 'payMultiselect' ? true : false }
fun_el_check_radio_in_efb = (el) => { return el == 'radio' || el == 'checkbox' || el == 'payRadio' || el == 'payCheckbox' || el == 'imgRadio' || el == 'chlRadio' || el == 'chlCheckBox' ? true : false }
function fun_validation_efb() {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${efb_var.text.enterTheValueThisField}','',10,'danger')"></div>` : efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null";
  for (let row in valj_efb) {
    let s =  get_row_sendback_by_id_efb(valj_efb[row].id_);
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      let el =document.getElementById(`${valj_efb[row].id_}_-message`);
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        let r=files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_);
        s = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state)==0 || r==-1 ? -1 :1;
      }
      if (s == -1) {
        if (state == true) { state = false; idi = valj_efb[row].id_ }
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
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");
          el.innerHTML = efb_var.text.minSelect + " " + valj_efb[row].minSelect
          if(!el.classList.contains('show'))el.classList.add('show');
          if (state == true) { state = false; idi = valj_efb[row].id_ }
        }
      }
    }else if (row > 1 && valj_efb[row].type == "chlCheckBox" && current_s_efb == valj_efb[row].step){
      for(let em of sendBack_emsFormBuilder_pub){
        if(em.type=="chlCheckBox" && em.id_==valj_efb[row].id_ && em.qty.length=="")
        {
          const vd = em.id_ob+"_chl";
          document.getElementById(vd).classList.add('bg-danger');
          state = false;
          idi = valj_efb[row].id_;
        }
      }
      if (state == false) { 
          noti_message_efb(efb_var.text.enterTheValueThisField, 'danger' , `step-${current_s_efb}-efb-msg` );
      }
    }
  }
  if (idi != "null") { document.getElementById(idi).scrollIntoView({behavior: "smooth", block: "center", inline: "center"}); }
  return state
}
function type_validate_efb(type) {
  return type == "select" || type == "multiselect" || type == "text" || type == "password" || type == "email" || type == "conturyList" || type == "stateProvince" || type == "file" || type == "url" || type == "color" || type == "date" || type == "textarea" || type == "tel" || type == "number" ? true : false;
}
addStyleColorBodyEfb = (t, c, type, id) => {
  let ttype = "text";
  if(id==-1){
    ttype =type;
  }else{
    ttype = valj_efb[id].type
  }
  let v = `.${t}{color:${c}!important;}`
  let tag = "";
  switch (ttype) {
    case 'textarea':
      tag = "textarea"
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
    case 'prcfld':
      tag = "input"
      break;
    case 'btn':
      tag = "btn"
      break;
    default:
      tag = ""
      break;
  }
  c=c[0]!="#" ? "#"+c : c
  efb_add_costum_color(t, c ,v , type)
}
efb_add_costum_color=(t, c ,v , type)=>{
  let n =''
  c=c[0]!="#" ? "#"+c : c
  if (type == "text") {       n=`${type}-${t}`;         v = `.${n}{color:${c}!important;}` }
  else if (type == "icon") {  n=`text-${t}`;            v = `.${n}{color:${c}!important;}` }
  else if (type == "border") {n=`${type}-${t}`;         v = `.${n}{border-color:${c}!important;}` }
  else if (type == "bg") {    n=`${type}-${t}`;         v = `.${n}{background-color:${c}!important;}` }
  else if (type == "btn") {   n=`${type}-${t}`;         v = `.${n}{background-color:${c}!important;}` }
  document.body.appendChild(Object.assign(document.createElement("style"), { textContent: `${v}` }))
  return n;
}
fun_addStyle_costumize_efb = (val, key, indexVJ) => {
  if (val.toString().includes('colorDEfb')) {
    let type = ""
    let color = ""
    switch (key.toString()) {
      case 'button_color': type = "btn"; color = valj_efb[indexVJ].style_btn_color ? valj_efb[indexVJ].style_btn_color : ''; break;
      case 'icon_color': type = "icon"; color = valj_efb[indexVJ].style_icon_color ? valj_efb[indexVJ].style_icon_color : ''; break;
      case 'el_text_color': type = "text"; color = valj_efb[indexVJ].style_el_text_color ? valj_efb[indexVJ].style_el_text_color : ''; break;
      case 'label_text_color': type = "text"; color = valj_efb[indexVJ].style_label_color ? valj_efb[indexVJ].style_label_color : ''; break;
      case 'message_text_color': type = "text"; color = valj_efb[indexVJ].style_message_text_color ? valj_efb[indexVJ].style_message_text_color : ''; break;
      case 'el_border_color': type = "border"; color = valj_efb[indexVJ].style_border_color ? valj_efb[indexVJ].style_border_color : ''; break;
      case 'clrdoneTitleEfb': type = "text"; color = valj_efb[indexVJ].clrdoneTitleEfb ? valj_efb[indexVJ].clrdoneTitleEfb.slice(-7) : ''; break;
      case 'clrdoniconEfb': type = "text"; color = valj_efb[indexVJ].clrdoniconEfb ? valj_efb[indexVJ].clrdoniconEfb.slice(-7) : ''; break;
      case 'clrdoneMessageEfb': type = "text"; color = valj_efb[indexVJ].clrdoneMessageEfb ? valj_efb[indexVJ].clrdoneMessageEfb.slice(-7) : ''; break;
      case 'prg_bar_color': type = "btn"; color = valj_efb[0].prg_bar_color ? valj_efb[indexVJ].prg_bar_color.slice(-7) : ''; indexVJ=-1; break;
    }
    if (color != "") addStyleColorBodyEfb((`colorDEfb-${color.slice(1)}`), color.length>6 ? color.slice(-6) : color, type, indexVJ);
  }
}
fun_offline_Efb = () => {
  let el = '';
  if(localStorage.hasOwnProperty('sendback')==false) return;
  const values =   JSON.parse(localStorage.getItem('sendback'))
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
        break;
      case 'textarea':
        document.getElementById(value.id_ob).innerHTML = value.value;
        break;
      case 'checkbox':
      case 'radio':
      case 'payCheckbox':
      case 'payRadio':
      case 'payRadio':
        document.getElementById(value.id_ob).checked = true;
        break;
      case 'paySelect':
      case 'conturyList':
      case 'stateProvince':
      case 'select':
        document.getElementById(value.id_ob).value = value.value;
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
        break;
      case 'esign':
        el = document.getElementById(`${value.id_}_`);
        let ctx = el.getContext("2d");
        let image = new Image();
        image.onload = function () {
          ctx.drawImage(image, 0, 0);
        };
        image.src = value.value
        break;
      case 'yesNo':
        el = document.querySelectorAll(`[data-lid='${value.id_}']`)
        for (let op of el) {
          if (op.dataset.value == value.value) {
            op.className += 'active';
          }
        }
        break;
      case 'switch':
        document.getElementById(value.id_ob).checked = value.value == "On" ? true : false;
        break;
      case 'rating':
        if (value.value >= 1) document.getElementById(`${value.id_}-star1`).checked = true;
        if (value.value >= 2) document.getElementById(`${value.id_}-star2`).checked = true;
        if (value.value >= 3) document.getElementById(`${value.id_}-star3`).checked = true;
        if (value.value >= 4) document.getElementById(`${value.id_}-star4`).checked = true;
        if (value.value == 5) document.getElementById(`${value.id_}-star5`).checked = true;
        break;
      case 'document':
        let s = value.url.split('/');
        s = s.pop();
        el = document.getElementById(`${value.id_}_-message`);
        el.className = `efb text-success efb fs-7 fw-bolder`;
        el.innerHTML = `${efb_var.text.uploadedFile}: ${s}`;
        if(!el.classList.contains('show'))el.classList.add('show');
        break;
      case 'stripe':
      break;
      case 'persiaPay':
        break;
    }
  }
  if(valj_efb[0].type=="payment" && valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string"){
    fun_after_bankpay_persia_ui();
  }
}
function send_data_efb() {
  if (state_efb != "run") {
    const cp = funTnxEfb('DemoCode-220201')
    document.getElementById('efb-final-step').innerHTML = cp
  } else {
    endMessage_emsFormBuilder_view()
  }
}
function funTnxEfb(val, title, message) {
  const done = valj_efb[0].thank_you_message.done || efb_var.text.done
  const corner = valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner: 'efb-square';
  const thankYou = valj_efb[0].thank_you_message.thankYou || efb_var.text.thanksFillingOutform
  const t = title ? title : done;
  const m = message ? message : thankYou;
  const clr_doneMessageEfb=valj_efb[0].hasOwnProperty("clrdoneMessageEfb") ? valj_efb[0].clrdoneMessageEfb :"doneMessageEfb" ;
  const clr_doneTitleEfb =valj_efb[0].hasOwnProperty("clrdoneTitleEfb") ? valj_efb[0].clrdoneTitleEfb :"doneTitleEfb" ;
  const clr_doniconEfb =valj_efb[0].hasOwnProperty("clrdoniconEfb") ? valj_efb[0].clrdoniconEfb :"doneTitleEfb" ;
  const doneTrackEfb=clr_doneTitleEfb ;
  const trckCd = `
  <div class="efb fs-4"><h5 class="efb mt-3 efb fs-4 ${clr_doneMessageEfb} text-center" id="doneTrackEfb">${valj_efb[0].thank_you_message.trackingCode || efb_var.text.trackingCode}: <strong>${val}</strong></h5>
               <input type="text" class="efb hide-input efb " value="${val}" id="trackingCodeEfb">
               <div id="alert"></div>
               <button type="button" class="efb btn  ${corner} efb ${valj_efb[0].button_color}  ${valj_efb[0].el_text_color} efb-btn-lg my-3 fs-5" onclick="copyCodeEfb('trackingCodeEfb')">
                   <i class="efb fs-5 bi-clipboard-check mx-1  ${valj_efb[0].el_text_color}"></i>${efb_var.text.copy}
               </button></div>`
  return `
                    <h4 class="efb  my-1 fs-2 ${doneTrackEfb} text-center" id="doneTitleEfb">
                        <i class="efb ${valj_efb[0].thank_you_message.hasOwnProperty('icon') ? valj_efb[0].thank_you_message.icon : 'bi-hand-thumbs-up'}  title-icon mx-2 fs-2 ${clr_doniconEfb}" id="DoneIconEfb"></i>${t}
                    </h4>
                    <h3 class="efb fs-4 ${clr_doneMessageEfb} text-center" id="doneMessageEfb">${m}</h3>
                  <span class="efb text-center" ${valj_efb[0].trackingCode == true ? trckCd : '</br>'}</span>
  `
}
let get_position_col_el = (dataId, state) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el_parent = document.getElementById(valj_efb[indx].id_) ?? "null";
  let el_label = document.getElementById(`${valj_efb[indx].id_}_labG`) ?? "null";
  let el_input = document.getElementById(`${valj_efb[indx].id_}-f`) ?? "null";
  let parent_col = ``;
  let label_col = `col-md-12`;
  let input_col = `col-md-12`;
  let parent_row = '';
  const size = valj_efb[indx].hasOwnProperty("size")? Number(valj_efb[indx].size)  : 100
  switch (size) {
    case 100:
      parent_col = 'col-md-12';
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 92:
      parent_col = 'col-md-11'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 80:
    case 83:
      parent_col = 'col-md-10'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 75:
      parent_col = 'col-md-9'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 67:
      parent_col = 'col-md-8'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 58:
      parent_col = 'col-md-7'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 50:
      parent_col = 'col-md-6'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 42:
      parent_col = 'col-md-5'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 33:
      parent_col = 'col-md-4'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 25:
      parent_col = 'col-md-3'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 17:
      parent_col = 'col-md-2'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 8:
      parent_col = 'col-md-1'
      label_col = `col-md-5`;
      input_col = `col-md-5`;
      break;
  }
  if (valj_efb[indx].label_position == "up") {
    label_col = `col-md-12`;
    input_col = `col-md-12`;
    if (state == true) {
      //if (!el_label.classList.contains('mx-2')) el_label.classList.add('mx-2');
    }
  } else {
    parent_row = 'row';
    if (state == true) {
      //if (el_input.classList.contains('mx-2')) el_input.classList.remove('mx-2');
      //if (el_label.classList.contains('mx-2')) el_label.classList.remove('mx-2');
    }
  }
  if (state == true) {
    el_parent.classList = colMdChangerEfb(el_parent.className, parent_col);
   if(el_input!="null") el_input.classList = colMdChangerEfb(el_input.className, input_col);
   if(el_label!="null") el_label.classList = colMdChangerEfb(el_label.className, label_col);
  }
  return [parent_row, parent_col, label_col, input_col]
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
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  //v3.6.2  updated
  let indx = files_emsFormBuilder.findIndex(x => x.id_ === id);
  files_emsFormBuilder[indx].state = 1;
  files_emsFormBuilder[indx].type = type;
  let r = ""
  const nonce_msg = efb_var.nonce_msg ;
  const page_id = efb_var.page_id ;
  //jQuery(function ($) {
    const fd = new FormData();
    const idn =  id + '_';
    //const file = document.getElementById(idn);
    setTimeout(() => {
      //const caption = document.querySelector(idn);
      uploadFile_api(file, id, tp, nonce_msg ,indx ,idn,page_id)
      return true;
    }, 500);
}
function uploadFile_api(file, id, pl, nonce_msg ,indx,idn,page_id) {
  const progressBar = document.querySelector('#progress-bar');
  const idB =id+'-prB';
  //setTimeout(() => {
      fetch_uploadFile(file, id, pl, nonce_msg,page_id).then((data) => {
        if (data.success === true && data.data.success===true) {
          files_emsFormBuilder[indx].url = data.data.file.url;
            files_emsFormBuilder[indx].state = 2;
            files_emsFormBuilder[indx].id = idn;
            const ob = valueJson_ws.find(x => x.id_ === id) || 0;
            const o = [{
              id_: files_emsFormBuilder[indx].id_,
              name: files_emsFormBuilder[indx].name,
              amount: ob.amount,
              type: files_emsFormBuilder[indx].type,
              value: '@file@',
              url: files_emsFormBuilder[indx].url,
              session: sessionPub_emsFormBuilder,
              page_id: page_id,
            }];            
            fun_sendBack_emsFormBuilder(o[0]);
            const el = document.getElementById(idB)
            if(el){
              el.style.width = '100%';
              el.textContent = '100% = ' + file.name;
            }
            if(document.getElementById(id + '-prG')) document.getElementById(id + '-prG').classList.add('d-none');
        } else {
          const m = data.data.hasOwnProperty('file') ? data.data.file.error : data.data.m;
          const el = document.getElementById(idB);
          console.error(m);
          alert_message_efb('', m, 30, 'danger');
          if(el==null) return;
          el.style.width = '0%';
          el.textContent = '0% = ' + file.name;
          return;
        }
      })
      .catch((error) => {
        console.error(error);
      });
}
function fetch_uploadFile(file, id, pl, nonce_msg,page_id) {
  var idB =id+'-prB';
  return new Promise((resolve, reject) => {
    const formData = new FormData();
    const fid = efb_var.hasOwnProperty('id') ? efb_var.id :0;
    formData.append('async-upload', file);
    formData.append('id', id);
    formData.append('pl', pl);
    formData.append('nonce_msg', nonce_msg);
    formData.append('sid', efb_var.sid);
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
      const response = JSON.parse(xhr.responseText);
      resolve(response);
    } else {
      reject(xhr.statusText);
    }
    });
    xhr.addEventListener('error', () => {
    reject(xhr.statusText);
    });
    xhr.open('POST', url, true);
    xhr.send(formData);
  });
}
function generatePDF_EFB(id) 
{
  const fonts_name =[{loc:'am', font:'Noto Serif Ethiopic'},{loc:'ar', font:'Noto Sans Arabic'},{loc:'fa_IR', font:'Noto Sans Arabic'},{loc:'arq', font:'Alegreya Sans SC'},{loc:'az_TR', font:'Noto Sans'},{loc:'bn_BD', font:'Noto Sans Bengali'},{loc:'cs_CZ', font:'Signika'},{loc:'hat', font:'Tinos'},{loc:'he_IL', font:'Noto Sans Hebrew'},{loc:'hr', font:'Noto Sans'},{loc:'hy', font:'Noto Sans Armenian'},{loc:'id_ID', font:'Noto Sans'},{loc:'ja', font:'Noto Sans JP'},{loc:'ka_GE', font:'Noto Sans Georgian'},{loc:'km', font:'Noto Sans Khmer'},{loc:'ko_KR', font:'Noto Sans KR'},{loc:'lt_LT', font:'Noto Sans'},{loc:'ml_IN', font:'Noto Sans'},{loc:'ms_MY', font:'Noto Sans'},{loc:'ne_NP', font:'Noto Sans'},{loc:'ru_RU', font:'Noto Sans'},{loc:'sw', font:'Noto Sans'},{loc:'th', font:'Noto Sans Thai'},{loc:'ur', font:'Noto Nastaliq Urdu'},{loc:'uz_UZ', font:'Noto Sans'},{loc:'vi', font:'Noto Sans'},{loc:'zh_CN', font:'Noto Sans SC'},{loc:'zh_HK', font:'Noto Sans HK'},{loc:'zh_TW', font:'Noto Sans TC'}]
  const indx = fonts_name.findIndex(x=>x.loc==efb_var.wp_lan);
  const fontname = indx!=-1 ? fonts_name[indx].font : 'Noto Sans';
  const fontStyle=`
        <link href="https://fonts.googleapis.com/css2?family=${fontname}" rel="stylesheet">
        <style>
           html{ font-family: '${fontname}', sans-serif;}
            .bg-response.efb {
              padding: 10px 19px;
              margin: 15px 20px;
              border-radius: 15px !important;
              background-color: #fcfcfc !important;
              box-shadow: 0px 1px 0px 2px rgb(0 7 17) !important;
          }
          img{width: 200px;}
          img.emoji{ width: 40px;}
          p {margin: 0px;}
          #watermark {
            margin: 0% 24%;
            font-size: 28px;
            position: absolute;
            color: #a8bde085;
            transform: rotate(-45deg);
            align-items: center;
            align-items: center;
            text-align: center;
            font-weight: bold;
        }
        </style>
  `
  const divPrint=document.getElementById(id);
  let n_win=window.open('','Print-Window');
  const text=`<a href="${window.location.origin}" target="_blank">${window.location.hostname}</a></h2>
  ${efb_var.pro!=1 ?`<h2>${efb_var.text.createdBy} <a href="https://whitestudio.team" target="_blank">${efb_var.text.easyFormBuilder}</a>`:''}`;
  const div=` <div style="text-align:center">
  <h2>${text}</h2>
</div>`
  val =`<html style="direction:${efb_var.rtl==0?'ltr':'rtl'}"><head>${fontStyle}</head>
  <title>${window.location.hostname}</title>
  <body onload='winprint()'>
  <script>
  function winprint(){setTimeout(()=>{window.print()},100);}
  </script>
  ${div}
  ${divPrint.innerHTML}
  </body></html>`;
  n_win.document.open();
  n_win.document.write(val);
  setTimeout(()=>{
  n_win.document.close();
  },100);
}
santize_string_efb=(str)=>{ 
  const regexp = /(<)(script[^>]*>[^<]*(?:<(?!\/script>)[^<]*)*<\/script>|\/?\b[^<>]+>|!(?:--\s*(?:(?:\[if\s*!IE]>\s*-->)?[^-]*(?:-(?!->)-*[^-]*)*)--|\[CDATA[^\]]*(?:](?!]>)[^\]]*)*]])>)/g
  return  str.replaceAll(regexp,'do not use HTML tags');
}
state_rply_btn_efb=(t)=>{
    if(pro_efb ==false){return};
   setTimeout(() => {
     if(stock_state_efb==true){
       let d= document.getElementById('respStateEfb');
       if(d){
          d.disabled=false;
         d.classList.remove('btn-outline-pink');
         d.classList.contains('btn-outline-pink') ? 0 : d.classList.remove('btn-outline-pink');
         d.classList.contains('btn-outline-success') ? 0 : d.classList.add('btn-outline-success');
         d.innerHTML =  efb_var.text.open;
         d.dataset.state ="1";
       }
       document.getElementById("replayB_emsFormBuilder").remove();
       if(document.getElementById("attach_efb")) document.getElementById("attach_efb").remove();
       document.getElementById("replayM_emsFormBuilder").remove();
       document.getElementById("label_replyM_efb").remove();
       document.getElementById("replay_state__emsFormBuilder").innerHTML=`<h5 class="efb fs-4 my-3 text-center text-pinkEfb">${efb_var.text.clsdrspn}</h5>`
      }else{
        let d= document.getElementById('respStateEfb');
        if(d){
          d.disabled=false;
          d.classList.contains('btn-outline-success') ? 0 : d.classList.remove('btn-outline-success');
          d.classList.contains('btn-outline-pink') ? 0 : d.classList.add('btn-outline-pink');
          d.innerHTML =  efb_var.text.close;
          d.dataset.state ="0";
        }
      }
   }, t);
}
window.addEventListener('offline', (e) => { console.log('offline'); });
window.addEventListener('online', (e) => { console.log('online'); });
function check_msg_ext_resp_efb() {
  const replayM_emsFormBuilder = document.querySelector("#replayM_emsFormBuilder");
  replayM_emsFormBuilder.addEventListener("keypress", (event) => {
    if (document.querySelector("#replayB_emsFormBuilder").classList.contains("disabled")) {
      document.querySelector("#replayB_emsFormBuilder").classList.remove("disabled");
    }
    if (event.which === 13) {
      event.preventDefault();
    }
  });
}
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
    return escHtml(matches[0]);
  }
  return matches[0];
}
function escHtml(unsafe)
{
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
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
valNotFound_efb=()=>{  
        alert_message_efb(efb_var.text.error,efb_var.text.empty, 30,'danger');
}
const add_r_matrix_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  let tagtype = tag;
  position_l_efb = efb_var.rtl == 1 ? "end" : "start";
  let col = valj_efb[indxP].hasOwnProperty('op_style') && Number(valj_efb[indxP].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[indxP].op_style)) : ''
  return `
    <!---here r_matrix-->
      <div class="efb  col-sm-12 row my-1   t-matrix" data-id="${idin}" data-parent="${parentsID}" id="${idin}-v">
        <div class="efb my-2 col-md-8 fs-6 ${valj_efb[indxP].el_text_color}  ${valj_efb[indxP].el_height} ${valj_efb[indxP].label_text_size}" id="${idin}_lab">${efb_var.text.newOption}</div>
        <div class="efb col-md-4 d-flex justify-content-${position_l_efb}" id="${idin}" > 
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="5"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="4"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="3"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="2"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="1"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <input type="hidden" data-vid="${idin}" data-parent="${parentsID} data-type="rating"  id="${idin}-point-rating" >
        </div>    
        <hr class="efb t-matrix my-1">                                        
    </div>
    `
}
async  function  fetch_json_from_url_efb(url){
  temp_efb="null";
 let r = {s:false ,r:"false"}
 return fetch(url)
  .then(response => response.json())
  .then(data => {
    r.s=true ;
    r.r =data
    temp_efb=r;
    return r;
  })
  .catch(error => {
    console.error('Error:', error);
    r.r =error
    temp_efb=r;
    return r;
  });
}
fun_captcha_load_efb = ()=>{
  if(valj_efb[0].captcha == true && document.getElementById('dropZoneEFB')) document.getElementById('dropZoneEFB').classList.add('captcha');
  return ` ${sitekye_emsFormBuilder.length > 1 ? `<div class="efb row mx-0"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0 px-0" data-sitekey="${sitekye_emsFormBuilder}" data-callback="verifyCaptcha" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
            <!-- fieldset1 --> 
            ${state_efb == "view" && valj_efb[0].captcha == true ? `<div class="efb col-12 mb-2 mx-0 mt-3 efb" id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>` : ''}
            <div id="step-1-efb-msg"></div>`
 }
function efb_text_nr(text , type){
  const val = type ==1 ?'<br>': '\n';
  text = text.replace(/@n#/g, val);
  return text;
}
function efb_remove_forbidden_chrs(text){
return text.replaceAll(/[!@#$%^&*()_,+}{?><":<=\][';/.\\|}]/g, '-');
}
svg_loading_efb=(classes)=>{
return`<span class="efb ${classes}"><svg version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve">
<path  d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50 M30.9,50c0-10.5,8.5-19.1,19.1-19.1S69.1,39.5,69.1,50">
  <animateTransform attributeName="transform" attributeType="XML" type="rotate" dur="1s" from="0 50 50" to="360 50 50" repeatCount="indefinite"></animateTransform>
</path>
</svg><span>`
} 
lan_subdomain_wsteam_efb=()=>{
  let sub =''; 
  if(efb_var.language == 'de_DE' || efb_var.language == 'de_AT' ){ sub = 'de.'; }
  else if(efb_var.language == 'ar' || efb_var.language == 'ary' ||  efb_var.language == 'arq'){ sub = 'ar.'; }
  //console.log(`subdomain => ${sub}`);
  return sub;
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
    validate_len =()=>{
      let offsetw = offset_view_efb();
      const mi=()=> {return  el.type!="number" ? 2 :0}
      let len = el.hasAttribute('minlength')  ? el.minLength :mi();
      if (value.length < len && el.type!="number") {
        state = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd = document.getElementById(`${el.id}-message`);
        let m = efb_var.text.mmplen.replace('NN',len);
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
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
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
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
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
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
        let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
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
    }//end validate_len
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
        }
        if(validate_len()==0 && (el.dataset.hasOwnProperty('type') && el.dataset.type!="chlCheckBox")){
          //console.log('validate_len()==0!!!');
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
              fun_check_link_state_efb(el.options[el.selectedIndex].dataset.iso , temp)
        }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="stateProvince"){
             let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);       
              iso_con = el.options[el.selectedIndex].dataset.isoc
              iso_state = el.options[el.selectedIndex].dataset.iso        
              fun_check_link_city_efb(iso_con,iso_state , temp)
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
    if(state==false && value.length > 0)  if(typeof(sendback_state_handler_efb)=='function') sendback_state_handler_efb(id_,false,current_s_efb);
    if (value != "" || value.length > 0) {
      const type = ob.type;
      const id_ob = ob.type != "paySelect" ? el.id : el.options[el.selectedIndex].id;
      let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder }];      
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
        fun_total_pay_efb()
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
//end payment functions
function fun_total_pay_efb() {
  let total = 0;
  updateTotal = (i) => {
    //totalpayEfb
    for (const l of document.querySelectorAll(".totalpayEfb")) {
      l.innerHTML = Number(i).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
    }
  }
  for (let r of sendBack_emsFormBuilder_pub) {
    if (r.hasOwnProperty('price') ) total += Math.abs(parseFloat(r.price));
  }
  setTimeout(() => { updateTotal(total); }, 800);
  if(valj_efb[0].getway=="persiaPay" && typeof fun_total_pay_persiaPay_efn=="function"){ fun_total_pay_persiaPay_efn(total)}
  else if(valj_efb[0].getway=="persiaPay"){
    //console.error('pyament persia not loaded (fun_total_pay_persiaPay_efn)')
  }
}
fun_currency_no_convert_efb = (currency, number) => {
  return new Intl.NumberFormat('us', { style: 'currency', currency: currency }).format(number)
}
fun_disabled_all_pay_efb = () => {
  let type = '';
  if(valj_efb[0].getway!="persiaPay")document.getElementById('stripeCardSectionEfb').classList.add('d-none');
  for (let o of valj_efb) {
    //console.log(o.type.includes('pay'),o);
    if (o.hasOwnProperty('price')==true || (o.hasOwnProperty('type') && o.type=='prcfld')) {
      //|| o.type.includes('pay')==true && o.type.includes('payment')==false
      //console.log(o.hasOwnProperty('parent'));
      if (o.hasOwnProperty('parent')) {
        const p = valj_efb.findIndex(x => x.id_ == o.parent);
        if (p==-1) continue;
        if(valj_efb[p].hasOwnProperty('type')==false) continue;
        type = valj_efb[p].type.toLowerCase();
        //if( type != "radio"  && type != "checkbox" && type != "select") continue;
        if(type.includes('pay')==false) continue;
        //console.log(valj_efb[p])
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
          }//end for
        }//end if multiselect 
      }else{
        let ov = document.querySelector(`[data-vid="${o.id_}"]`);
        //console.log(ov)
        ov.classList.add('disabled');        
        ov.disabled = true;
        ov.classList.remove('payefb');
      }
    }
  }
}
add_ui_totalprice_efb = (rndm ,iVJ) => {
  //${valj_efb[indx]. valj_efb[indx].el_text_color}
  return  `
  <!-- total Price -->
    <label class="efb totalpayEfb  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].classes.replace(`,`, ` `)}  mt-1"   data-id="${rndm}-el" id="${rndm}_"> 
     ${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })}
    </label>
  <!-- end total Price -->
  `
}
function fun_emsFormBuilder_show_messages(content, by, userIp, track, date) {
  //console.log(content, by, userIp, track, date);
  stock_state_efb=false;
  let totalpaid =0;
  if(content[(content.length)- 1].type=="w_link")content.pop();
   //console.log(by);
  const ipSection = userIp!='' ? `<p class="efb small fs-7 mb-0"><span>${efb_var.text.ip}:</span> ${userIp}</p>` :''
  if (by == 1) {
     by = 'Admin'; by=`<span>${efb_var.text.by}:</span> ${by}`; } 
  else if (by ==''  && (efb_var.hasOwnProperty('user_name') &&  efb_var.user_name.length > 1)){ 
    //console.log('by admin or null',efb_var.user_name.length,efb_var.user_name.length > 1 ,efb_var.user_name );
    by = efb_var.hasOwnProperty('user_name') &&  efb_var.user_name.length > 1 ? `<span>${efb_var.text.by}:</span> ${efb_var.user_name}`  : `<span>${efb_var.text.by}:</span> ${efb_var.text.guest}`;
  }else if(by==-1){
    by = 'Admin';
    by=`<span>${efb_var.text.by}:</span> ${by}`;
  }else if (by==undefined ||by == 0 || by.length == 0 || by.length == -1) {
    by=`<span>${efb_var.text.by}:</span> ${efb_var.text.guest}`; 
  }else if(by=='#first'){

  }else {     
    by = `<span>${efb_var.text.by}:</span> ${by}`;
   }
   by = by=='#first' ? `` : `<p class="efb small fs-7 mb-0">${by}</p>`;
  let m = `<Div class="efb bg-response efb card-body my-2 py-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
    <div class="efb  form-check">
     <div>
      ${by}
      ${ipSection}
      ${track != 0 ? `<p class="efb small fs-7 mb-0"><span> ${efb_var.text.trackNo}:</span> ${track} </p>` : ''}
      <p class="efb small fs-7 mb-0"><span>${efb_var.text.ddate}:</span> ${date} </p>  
   </div>
   <div class="efb col fs-4 h-d-efb pointer-efb text-darkb d-flex justify-content-end bi-download" data-toggle="tooltip" data-placement="bottom" title="${efb_var.text.download}" onClick="generatePDF_EFB('resp_efb')"></div>
   </div>
  <hr>
  `;
  content.sort((a, b) => (Number(a.amount) > Number(b.amount)) ? 1 : -1);
  let list = []
  let s = false;
  let checboxs=[];
  let currency = content[0].hasOwnProperty('paymentcurrency') ? content[0].paymentcurrency :'usd';
  //console.error(content[0].paymentcurrency,content);
  for (const c of content) {
    if (c.hasOwnProperty('price')){ totalpaid +=Number(c.price)}
    if(c.hasOwnProperty('value') && c.type!="maps"){ c.value = replaceContentMessageEfb(c.value)}
    if(c.hasOwnProperty('qty')){ c.qty = replaceContentMessageEfb(c.qty)}
    s = false;
    let value = typeof(c.value)=="string" ? `<b>${c.value.toString().replaceAll('@efb!', ',')}</b>` :'';
    if(c.hasOwnProperty('qty')!=false) value+=`: <b> ${c.qty}</b>`
    if (c.value == "@file@" && list.findIndex(x => x == c.url) == -1) {
      s = true;
      list.push(c.url);
      $name = c.url.slice((c.url.lastIndexOf("/") + 1), (c.url.lastIndexOf(".")));
      if (c.type == "Image" || c.type == "image") {
        value = `</br><img src="${c.url}" alt="${c.name}" class="efb img-thumbnail m-1">`
      } else if (c.type == "Document" || c.type == "document" || c.type == "allformat") {
        value = `</br><a class="efb btn btn-primary m-1 text-decoration-none" href="${c.url}" target="_blank" >${efb_var.text.download}</a>`
      } else if (c.type == "Media" || c.type == "media") {
        const audios = ['mp3', 'wav', 'ogg'];
        let media = "video";
        audios.forEach(function (aud) {
          if (c.url.indexOf(aud) !== -1) {
            media = 'audio';
          }
        })
        if (media == "video") {
          const len = c.url.length;
          const type = c.url.slice((len - c.url.lastIndexOf(x => x == ".")), len);
          value = type !== 'avi' ? `</br><div class="efb px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="efb text-center" ><a href="${c.url}">${efb_var.text.videoDownloadLink}</a></p>` : `<p class="efb text-center"><a href="${c.url}">${efb_var.text.downloadViedo}</a></p>`;
        } else {
          value = `<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
      } else {
        value = c.url.length > 1 ? `</br><a class="efb btn btn-primary" href="${c.url}" target="_blank" >${c.name}</a>` : `<span class="efb  fs-5">💤</span>`
      }
    } else if (c.type == "esign") {
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      s = true;
      value = `<img src="${c.value}" alt="${c.name}" class="efb img-thumbnail">`;
      m += `<p class="efb fs-6 my-0 efb  form-check">${title}:</p> <p class="efb my-1 mx-3 fs-7 form-check"> ${value}</span>`;
    } else if (c.type == "color") {
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      s = true;
      //value = `<img src="${c.value}" alt="${c.name}" class="efb img-thumbnail">`;
      value = `<div class="efb img-thumbnail"  style="background-color:${c.value}; height: 50px;">${c.value}</div>`;
      m += `<p class="efb fs-6 my-0 efb  form-check">${title}:</p> <p class="efb my-1 mx-3 fs-7 form-check"> ${value}</p>`;
    } else if (c.type == "maps") {
      if (typeof (c.value) == "object") {
        s = true;
        //value = `<div id="${c.id_}-map" data-type="maps" class="efb  maps-efb h-d-efb  required " data-id="${c.id_}-el" data-name="maps"><h1>maps</h1></div>`;
        value = maps_os_pro_efb(false, '', c.id_,'') 
        
        marker_maps_efb = c.value;
        m += value;
        setTimeout(() => {
          efbCreateMap(c.id_ ,c,true)
        }, 800);
      }
    } else if (c.type == "rating") {
      s = true;
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      value = `<div class='efb fs-4 star-checked star-efb mx-1 ${efb_var.rtl == 1 ? 'text-end' : 'text-start'}'>`;
      for (let i = 0; i < parseInt(c.value); i++) {
        value += `<i class="efb bi bi-star-fill"></i>`
      }
      value += "</div>";
      m += `<p class="efb fs-6 my-0 efb  form-check">${title}:</p><p class="efb my-1 mx-3 fs-7 form-check"> ${value}</p>`;
      //console.log(checboxs.includes(c.id_))
    } else if (c.type=="checkbox" && checboxs.includes(c.id_)==false){
      s = true;
      //console.log(361 ,checboxs.includes(c.id_));
      let vc ='null';
      checboxs.push(c.id_);
      for(let op of content){
        if(op.type=="checkbox" && op.id_ == c.id_){
          vc=='null' ? vc =`<p class="efb my-1 mx-3 fs-7 form-check"><b> ${op.value}</b></p>` :vc +=`<p class="efb my-1 mx-3 fs-7 form-check"><b> ${op.value}</b></p>`
        }
      }
      m += `<p class="efb fs-6 my-0 efb">${c.name}:</p>${vc}`;
    }else if (c.type=="r_matrix"){
      s = true;
      //console.log(390 ,checboxs.includes(c.id_));
      vc =`${c.hasOwnProperty('label') ? `<p class=efb fs-6 my-0 efb"">${c.label}</p>`:''}<p class="efb my-1 mx-3 fs-7 test form-check"> ${c.name} :${c.value} </p>`
      m += `${vc}`;
    }
    if (c.id_ == 'passwordRegisterEFB') { m += value; value = '**********' };
    if (((s == true && c.value == "@file@") || (s == false && c.value != "@file@")) && c.id_!="payment" && c.type!="checkbox"){
        let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
        if(title=="file") title ="atcfle"
        title = efb_var.text[title] || c.name ;
        let q =value !== '<b>@file@</b>' ? value : '';;
        if(c.type.includes('pay')) {
          //console.log(currency ,c)
          q+=`<span class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end">${Number(c.price).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span>`
        }else if(c.type.includes('checkbox')){
          //checboxs.push
        }else if(c.type.includes('imgRadio')){
          q =`<div class="efb w-25">`+fun_imgRadio_efb(c.id_, c.src ,c)+`</div>`
        } 
        m += `<p class="efb fs-6 my-0 efb">${title}:</p><p class="efb my-1 mx-3 fs-7 test form-check">${efb_text_nr(q,1)}</p>`
       //m += `<p class="efb fs-6 my-0 efb  form-check">${c.name}: <span class="efb mb-1"> ${value !== '<b>@file@</b>' ? value : ''}</span> `
      }
    if (c.type == "payment") {
      if(c.paymentGateway == "stripe"){
        m += `<div class="efb mx-3 mb-1 p-1 fs7 text-capitalize bg-dark text-white">
            <p class="efb fs-6 my-0">${efb_var.text.payment} ${efb_var.text.id}:<span class="efb mb-1"> ${c.paymentIntent}</span></p>
            <p class="efb  my-0">${efb_var.text.payAmount}:<span class="efb mb-1"> ${Number(c.total).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></p>
            <p class="efb my-0">${efb_var.text.ddate}:<span class="efb mb-1"> ${c.paymentCreated}</span></p>
            <p class="efb my-0">${efb_var.text.updated}:<span class="efb mb-1"> ${c.updatetime}</span></p>
            <p class="efb  my-0">${efb_var.text.methodPayment}:<span class="efb mb-1"> ${c.paymentmethod}</span></p>
            ${c.paymentmethod != 'charge' ? `<p class="efb fs-6 my-0">${efb_var.text.interval}:<span class="efb mb-1 text-capitalize"> ${c.interval}</span></p>` : ''}
            </div>`
      }else {
        m += ``
        m += `<div class="efb mx-3 mb-1 p-1 fs7 text-capitalize bg-dark text-white">
            <p class="efb my-0">${efb_var.text.payment} ${efb_var.text.id}:<span class="efb mb-1"> ${c.paymentIntent}</span></p>
            <p class="efb my-0">${efb_var.text.payAmount}:<span class="efb mb-1"> ${c.total} ریال</span></p>
            <p class="efb  my-0">${efb_var.text.methodPayment}:<span class="efb mb-1"> ${c.paymentmethod}</span></p>
            <p class="efb my-0">${efb_var.text.ddate}:<span class="efb mb-1"> ${c.paymentCreated}</span></p>
            <p class="efb my-0">شماره کارت:<span class="efb mb-1"> ${c.paymentCard}</span></p>
            <p class="efb my-0">کد پیگیری زرین پال<span class="efb mb-1"> ${c.refId}</span></p>
            </div>`
      }
    }else if (c.type =="closed"){
      stock_state_efb=true;
    }else if (c.type =="opened"){
      stock_state_efb=false;
    }
  }
  if(totalpaid>0){
    m +=`<div class="efb my-2 fs7 bg-dark text-light">
    <p class="efb p-2">${efb_var.text.ttlprc}:<span class="efb mb-1"> ${Number(totalpaid)}</span></p>
    </div>`
  }
  m += '</div>';
  return m;
}
//end payment functions

fun_get_links_from_string_Efb=(str , handler)=>{
  /* 
  handler : false mean return export link and anchor text
  handler : true mean return string with anchor tag
  */
  //let str = "Here is a link [test1](https://github.com/hassantafreshi/), and here is another [test2](https://github.com/hassantafreshi/another-repo)";
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
    //console.log(r);
    return [state,r]
  }else{
   // let str = "Here is a link [test1](https://github.com/hassantafreshi/), and here is another [test2](https://github.com/hassantafreshi/another-repo)";

    str= str.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(_, anchorText, url) {
          state=true;
        return `<a href="${url}" target="_blank")>${anchorText}</a>`;
    });

    //console.log(str);
    return str;
  }
 
  
}

// 3.6.8 start
function deepFreeze_efb(obj) {
  Object.keys(obj).forEach((key) => {
      if (typeof obj[key] === "object" && obj[key] !== null) {
          deepFreeze_efb(obj[key]);
      }
  });
  return Object.freeze(obj);
}

//3.6.8 end

