
//Copyright 2021
//Easy Form Builder
//WhiteStudio.team
//EFB.APP
let activeEl_efb = 0;
let amount_el_efb = 1; //تعداد المان ها را نگه می دارد
let step_el_efb = 0; // تعداد استپ ها
let steps_index_efb = [] // hold index of steps
let valj_efb = [];
let maps_efb = [];
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
const getUrlparams_efb = new URLSearchParams(location.search);
const mobile_view_efb = document.getElementsByTagName('body')[0].classList.contains("mobile") ? 1 : 0;


efb_var_waitng = (time) => {
  setTimeout(() => {

    if (typeof (efb_var) == "object") {

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










function pro_show_efb(state) {

  let message = state;
  if (typeof state != "string") message = state == 1 ? efb_var.text.proUnlockMsg : efb_var.text.ifYouNeedCreateMoreThan2Steps;
  const body = `<div class="efb  pro-version-efb-modal"><i class="efb  bi-gem"></i></div>
  <h5 class="efb  txt-center">${message}</h5>
  <div class="efb row">
  <div class="efb  col-md-6  text-center">
  <button class="efb btn mt-3 efb btn-r h-d-efb btn-outline-pink "  onClick ="open_whiteStudio_efb('pro')">${efb_var.text.priceyr.replace('NN',pro_price_efb)} </button>
  </div>
    <div class="efb  text-center col-md-6"><button type="button" class="efb btn btn-r efb btn-primary efb-btn-lg mt-3 mb-3" onClick ="open_whiteStudio_efb('pro')">
      <i class="efb  bi-gem mx-1 pro"></i>
        ${efb_var.text.activateProVersion}
      </button></div>
    
  </div>`
  show_modal_efb(body, efb_var.text.proVersion, '', 'proBpx')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  //myModal.show_efb()
  state_modal_show_efb(1)
}
function move_show_efb() {
  const body = `<div class="efb  pro-version-efb-modal"><i class="efb "></i></div>
  <div class="efb  text-center" dir="rtl">
   <img src="${efb_var.images.movebtn}" class="efb  img-fluid" alt="">
  </div>`
  show_modal_efb(body, '','bi-arrows-move', 'saveBox')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  //myModal.show_efb()
  state_modal_show_efb(1)
}


const show_modal_efb = (body, title, icon, type) => {
  //console.log('show_modal_efb in ',type ,title,body.slice(1,50))
  // const myModal =  new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  
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
    //settingModalEfb-sections
  } else if (type == "saveBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else if (type == "saveLoadingBox") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
    document.getElementById('settingModalEfb-body').innerHTML = loading_messge_efb();
  } else if (type == "chart") {
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
    if (!document.getElementById("settingModalEfb_").classList.contains('save-efb')) document.getElementById("settingModalEfb_").classList.add('save-efb')
  } else {
    document.getElementById("settingModalEfb_").classList.remove('save-efb')
    document.getElementById("settingModalEfb").classList.remove('modal-new-efb')
  }
  //console.log(document.getElementById("settingModalEfb-body"));
  // myModal.show()
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
    //console.log(col, valj_efb[indxP],Number(valj_efb[indxP].op_style ))
    op = `<div class="efb  form-check ${col}" data-parent="${parentsID}"  id="${id_ob}-v">
    <input class="efb  form-check-input ${valj_efb[indxP].el_text_size}" type="${tagtype}" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
    <label class="efb ${valj_efb[indxP].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height} hStyleOpEfb " id="${idin}_lab" for="${idin}">${value}</label>
    ${qst}
    ${price}
    </div>`

  }
  
  return op;
}



//drag and drop form creator  (pure javascript)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com



function addNewElement(elementId, rndm, editState, previewSate) {

  //editState == true when form is edit method
  let pos = [``, ``, ``, ``]
  const shwBtn = previewSate != true ? 'showBtns' : '';
  let indexVJ = editState != false ? valj_efb.findIndex(x => x.id_ == rndm) : 0;
  if (previewSate == true && elementId != "html" && elementId != "register" && elementId != "login" && elementId != "subscribe" && elementId != "survey") pos = get_position_col_el(valj_efb[indexVJ].dataId, false)
  console.log(`indexVJ[${indexVJ}]`, valj_efb[indexVJ]);
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
  
  //for(let q in  valj_efb[indexVJ]){
  if (previewSate == false) Object.entries(valj_efb[indexVJ]).forEach(([key, val]) => {
    
    fun_addStyle_costumize_efb(val.toString(), key, indexVJ); })
  if (step_el_efb == 1) {
    let state = false;

    if (editState == false) {
      state = true;

    }

    if (elementId != 'steps') {
      if (editState == false && valj_efb.length < 2) {
        //console.log(formName_Efb,efb_var.text[formName_Efb])
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
      // add_buttons_zone_efb(0, 'dropZoneEFB');

      editState == false && valj_efb.length > 2 ? step_el_efb= Number(step_el_efb) +1 : 0;
    }

    amount_el_efb =Number(amount_el_efb)+1;

  }
  
  if (editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_efb == false && step_el_efb < 3) || pro_efb == true)) { sampleElpush_efb(rndm, elementId); }

  //const idd = editState==false && elementId=="steps" ? `${rndm}` : rndm
  let iVJ = editState == false ? valj_efb.length - 1 : valj_efb.findIndex(x => x.id_ == rndm);

  let dataTag = 'text'
  //const pos_pr = elementId!="table_matrix" ? "col-md-3" : pos[2] ="col-md-12"
 // pos[2] = previewSate == true ? pos[2] : pos_pr
  const desc = `<small id="${rndm}-des" class="efb  form-text d-flex  fs-7 col-sm-12 efb ${previewSate == true && pos[1] == 'col-md-4' || valj_efb[iVJ].message_align != "justify-content-start" ? `` : `mx-4`}  ${valj_efb[iVJ].message_align}  ${valj_efb[iVJ].message_text_color} ${ valj_efb[iVJ].hasOwnProperty('message_text_size') ? valj_efb[iVJ].message_text_size : ''} ">${valj_efb[iVJ].message} </small> `;
  const  label = `<label for="${rndm}_" class="efb mx-0 px-0 pt-2 pb-1  ${previewSate == true ? pos[2] :"col-md-12"} col-sm-12 col-form-label ${valj_efb[iVJ].hasOwnProperty('hflabel') && Number(valj_efb[iVJ].hflabel)==1 ? 'd-none' :''} ${valj_efb[iVJ].label_text_color} ${valj_efb[iVJ].label_align} ${valj_efb[iVJ].label_text_size != "default" ? valj_efb[iVJ].label_text_size : ''} " id="${rndm}_labG""><span id="${rndm}_lab" class="efb  ${valj_efb[iVJ].label_text_size}">${valj_efb[iVJ].name}</span><span class="efb  mx-1 text-danger" id="${rndm}_req">${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? '*' : ''}</span></label>`
  const ttip = `<small id="${rndm}_-message" class="efb text-danger py-1 fs-7 tx ttiptext px-2"> ! </small>`
  const rndm_1 = Math.random().toString(36).substr(2, 9);
  const rndm_2 = Math.random().toString(36).substr(2, 9);
  const op_3 = Math.random().toString(36).substr(2, 9);
  const op_4 = Math.random().toString(36).substr(2, 9);
  const op_5 = Math.random().toString(36).substr(2, 9);
  let ui = ''
  const vtype = (elementId == "payCheckbox" || elementId == "payRadio" || elementId == "paySelect" || elementId == "payMultiselect" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio") ? elementId.slice(3).toLowerCase() : elementId;
  let classes = ''
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square';
  let minlen,maxlen,temp,col;
  let hidden =  previewSate == true  && valj_efb[iVJ].hasOwnProperty('hidden') &&  valj_efb[iVJ].hidden==1 ? 'd-none' : ''
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  let ps =  elementId == "html" ? 'col-md-12' : 'col-md-12'
  if(pos[3]==""){
     if( elementId=="firstName" || elementId=="lastName" 
     || elementId=="country" || elementId=="statePro" || elementId=="city" ){ ps = 'col-md-6';}
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
    case 'zipcode':
    case 'address_line':
      
     
      
      const type = elementId == "firstName" || elementId == "lastName" || elementId == "zipcode" || elementId == "address_line" ? 'text' : elementId;
      maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
      maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;    
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';

      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="${type}"   class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-id="${rndm}-el" data-vid='${rndm}' data-css="${rndm}" id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} ${maxlen} ${minlen} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      break;
    case 'pdate':

      
     /*  maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
      maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;    
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``; */
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb pdpF2 input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}'  id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'ardate':

      
     /*  maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
      maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;    
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``; */
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb hijri-picker input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'range':

       
         maxlen = valj_efb[iVJ].hasOwnProperty('mlen') ? valj_efb[iVJ].mlen :100;
        //maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :0;    
        temp =valj_efb[iVJ].value>0 ? valj_efb[iVJ].value :Math.round((Number(valj_efb[iVJ].mlen)+Number(valj_efb[iVJ].milen))/2) ;
        //minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
        //classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      //  classes = `form-control ${valj_efb[iVJ].el_border_color} `;
        ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show  "  id='${rndm}-f'>
          ${ttip}
          <div class="efb slider m-0 p-2 ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].el_text_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id='${rndm}-range'>
          <input type="${elementId}"  class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" oninput="fun_show_val_range_efb('${rndm}')"  ${valj_efb[iVJ].value.length > 0 ? `value ="${temp}"` : ''} min="${minlen}" max="${maxlen}" ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
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
      ${typeof maps_el_pro_efb =="function" ? maps_el_pro_efb(previewSate, pos , rndm,iVJ) : public_pro_message()}
        ${desc}`
      dataTag = elementId;



      break;
    case 'file':
      ui = `
       ${label}
        <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
          ${ttip}        
          <input type="${elementId}" class="efb  input-efb px-2 pb-0 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}    form-control efb efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" placeholder="${elementId}" ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          ${desc}`
      dataTag = elementId;

      break;
    case "textarea":
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;  
      //console.log(minlen , valj_efb[iVJ].milen)  ;
      
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
      ui = `
                ${label}
                <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f' >
                ${ttip}
                <textarea  id="${rndm}_"  placeholder="${valj_efb[iVJ].placeholder}"  class="efb  px-2 input-efb emsFormBuilder_v form-control w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el"  value="${valj_efb[iVJ].value}" rows="5" ${previewSate != true ? 'readonly' : ''} ${disabled} ${minlen}>${efb_text_nr(valj_efb[iVJ].value,0)}</textarea>
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
      
      // const rndm_a = Math.random().toString(36).substr(2, 9);
    /* 
    <label for="img1">
            <img src="https://dummyimage.com/600x400/000/fff" alt="Image 1">
            <div class="tick_container">
              <div class="tick"><i class=" bi-check"></i></div>
            </div>
            </label>
    
    */

     
      dataTag = elementId;
       col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style )!=1 ? 'col-md-'+(12/Number(valj_efb[iVJ].op_style )) :''
      if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio") pay = "";
      temp = elementId=="imgRadio" ? 'col-md-4 mx-0 px-2' :'';
      if (editState != false) {
        // if edit mode
        
        let tp = dataTag.toLowerCase();
        let parent = valj_efb[iVJ]
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
        const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
        
        for (const i of optns_obj) {
          let checked ="";
          //console.error(parent.value,i.id_,i,parent.value , i.id_old            )
          
          if((tp.includes("radio")==true ||( tp.includes("select")==true &&  tp.includes("multi")==false))  && ( parent.value == i.id_ || (i.hasOwnProperty("id_old") && parent.value == i.id_old) )  ){ checked="checked";
          }else if((tp.includes("multi")==true || tp.includes("checkbox")==true) &&  typeof parent.value!="string" &&  parent.value.findIndex(x=>x==i.id_ || x==i.id_old)!=-1 ){checked="checked"}

          const prc = i.hasOwnProperty('price') ? Number(i.price):0;
          optn += `<div class="efb  form-check ${col} ${elementId} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)} mt-1" data-css="${rndm}" data-parent="${i.parent}" data-id="${i.id_}" id="${i.id_}-v">
          <input class="efb  form-check-input emsFormBuilder_v ${pay}  ${valj_efb[iVJ].el_text_size} " data-tag="${dataTag}" data-type="${vtype}" data-vid='${rndm}' type="${vtype}" name="${i.parent}" value="${i.value}" id="${i.id_}" data-id="${i.id_}-id" data-op="${i.id_}"${previewSate != true ? 'readonly' : ''} ${disabled} ${checked}>
          ${elementId!='imgRadio'?` <label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${i.id_}_lab" for="${i.id_}">${i.value}</label>`: fun_imgRadio_efb(i.id_,i.src,i)}
          ${elementId.includes('chl')!=false?`<input type="text" class="efb ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${i.id_}" data-type="${dataTag}" data-vid="" id="${i.id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}"  disabled>` :''}
          ${ pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${i.id_}-price" class="efb efb-crrncy">${prc.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
          </div>`
        }//end for 

      } else {     
       // console.log(valj_efb[iVJ],valj_efb[iVJ].id_) 
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
      temp = elementId=="imgRadio" ?  "row  justify-content-center" :"";
      ui = `
      <!-- checkbox -->
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show"   data-id="${rndm}-el" id='${rndm}-f'>
      ${ttip}
      <div class="efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${col!=''? 'row col-md-12' :''} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}_options">
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
      <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f">
      <label class="efb fs-6" id="${rndm}_off">${valj_efb[iVJ].off}</label>
      <button type="button"  data-state="off" class="efb btn   ${valj_efb[iVJ].el_height}  btn-toggle efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-toggle="button" aria-pressed="false" autocomplete="off" data-vid='${rndm}' onClick="fun_switch_efb(this)" data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''} ${disabled}>
        <div class="efb handle"></div>
      </button>
      <label class="efb fs-6" id="${rndm}_on">${valj_efb[iVJ].on}</label>
      
    

   <!--   <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f">
      <div class="efb  form-check form-switch   ${valj_efb[iVJ].el_height} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}-switch">
        <input class="efb d-none emsFormBuilder_v efb-switch form-check-input efbField" type="checkbox" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''} ${disabled}>
      </div> -->
      <div class="efb  mb-3">${desc}</div>
      `
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      dataTag = elementId;

      //
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
      <select class="efb form-select efb emsFormBuilder_v w-100 ${pay}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  w-100 " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'readonly' : ''} ${disabled}>
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
       //ui =typeof headning_el_pro_efb =="function" ?headning_el_pro_efb (rndm,iVJ) :public_pro_message();
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 efb  px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="conturyList" class="efb form-select efb w-100 emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'readonly' : ''} ${disabled}>
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
        <select data-type="stateProvince" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'readonly' : ''} ${disabled}>
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
        <select data-type="citylist" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
        //console.log(valj_efb);
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
        // if edit mode
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

        }//end for 
        //optn += `</ul></div>`
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
               <input type="text" class="efb efblist search searchBox my-1 col-12 rounded " data-id="menu-${rndm}" data-tag="search" placeholder="🔍 ${efb_var.text.search}" onkeyup="FunSearchTableEfb('menu-${rndm}')"> </div>
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
        // ${typeof html_el_pro_efb =="function" ? html_el_pro_efb(previewSate, rndm,iVJ) : public_pro_message()}
      break;
    case 'booking':
      dataTag = elementId;
      break;
    case 'pointr10':
      //console.log('pointr5')
      //pointer10_el_pro_efb
      //temp=pointer10_el_pro_efb(previewSate, classes,iVJ);
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
      //console.log('pointr5')
      //pointer10_el_pro_efb
      //temp=pointer5_el_pro_efb(previewSate, classes,iVJ);
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
      //console.log('smartcr')
      //pointer10_el_pro_efb
      //temp=smartcr_el_pro_efb(previewSate, classes,iVJ);
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
        
        //  case 'payCheckbox':
        //case 'payRadio':
        //case 'chlCheckBox':
        //case 'chlRadio':
        // const rndm_a = Math.random().toString(36).substr(2, 9);
        type_field_efb = elementId;
        dataTag = elementId;      
         col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[iVJ].op_style)) : ''
        if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox") pay = "";
  
        if (editState != false) {
          // if edit mode
          optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
  
          
          for (const i of optns_obj) {
            prc = i.hasOwnProperty('price') ? Number(i.price) : 0;
            optn += `
            <!-- start r_matrix -->
            <div class="efb  col-sm-12 ${col} row my-1   t-matrix" data-id="${i.id_}" data-parent="${i.parent}" id="${i.id_}-v">
              <div class="efb my-2  col-md-8 fs-6  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${i.id_}_lab">${i.value}</div>
              <div class="efb col-md-4  d-flex justify-content-${position_l_efb} " id="${i.id_}" > 
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
          }//end for 
  
  
  
        } else {
          const op_1 = Math.random().toString(36).substr(2, 9);
          const op_2 = Math.random().toString(36).substr(2, 9);
          const pv = 0;
  
          optn = `
          <div class="efb   col-sm-12 row my-1  t-matrix" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
              <div class="efb my-2 col-md-8 fs-6 ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${op_1}_lab">${efb_var.text.newOption}</div>
              <div class="efb col-md-4 d-flex justify-content-${position_l_efb} " id="${op_1}" > 
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
    case 'terms':
      dataTag = elementId;
      ui =typeof terms_el_pro_efb =="function" ? terms_el_pro_efb(previewSate, rndm,iVJ) :public_pro_message();
      
      break;


  }
  const addDeleteBtnState = (formName_Efb == "login" && (valj_efb[iVJ].id_ == "emaillogin" || valj_efb[iVJ].id_ == "passwordlogin")) || (formName_Efb == "register" && (valj_efb[iVJ].id_ == "usernameRegisterEFB" || valj_efb[iVJ].id_ == "passwordRegisterEFB" || valj_efb[iVJ].id_ == "emailRegisterEFB")) ? true : false;
  if (elementId != "form" && dataTag != "step" && ((previewSate == true && elementId != 'option') || previewSate != true)) 
    {
    //const pro_el = (dataTag == "heading" || dataTag == "link" || dataTag == "payMultiselect" || dataTag == "paySelect" || dataTag == "payRadio" || dataTag == "payCheckbox" || dataTag == "stripe" || dataTag == "switch" || dataTag == "rating" || dataTag == "esign" || dataTag == "maps" || dataTag == "color" || dataTag == "html" || dataTag == "yesNo" || dataTag == "stateProvince" || dataTag == "conturyList" || dataTag == "mobile" || dataTag == "persiaPay" || dataTag == "chlRadio" || dataTag == "chlCheckBox" || dataTag =="dadfile") ? true : false;
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
    const tagId = elementId == "firstName" || elementId == "lastName" || elementId == "address" || elementId == "address_line" || elementId == "zipcode" ? 'text' : elementId;
    const tagT = elementId =="esign" || elementId=="yesNo" || elementId=="rating" ? '' : 'def'
    //data-toggle="tooltip" data-placement="top" title="Tooltip on top !!! " data-bs-custom-class="custom-tooltip" 
    newElement += `
    ${previewSate == false  ? `<setion class="efb my-1 px-0 mx-0 ttEfb ${previewSate != true ? disabled : ""} ${previewSate == false && valj_efb[iVJ].hidden==1 ? "hidden" : ""} ${previewSate == true && (pos[1] == "col-md-12" || pos[1] == "col-md-10") ? `mx-0 px-0` : 'position-relative'} ${previewSate == true ? `${pos[0]} ${pos[1]}` : `${ps}`} row col-sm-12 ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >` : ''}
    ${previewSate == false && valj_efb[iVJ].hidden==1 ? hiddenMarkEl(valj_efb[iVJ].id_) : ''}
    <div class="efb my-1 mx-0  ${elementId} ${tagT} ${hidden} ${previewSate == true ? disabled : ""}  ttEfb ${previewSate == true ? `${pos[0]} ${pos[1]}` : `${ps} row`} col-sm-12 ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >
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
      //console.log('stepppp !!! ')
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
  //console.log('(EFB->Pro Version Expired)');
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
      //console.log('========>buttons')
      const id = valj_efb[0].steps<2 ? 'f_btn_send_efb' : 'f_button_form_np';
      const elm_ = document.getElementById(id)
      elm_.className = alignChangerElEfb(elm_.className, align)
      valj_efb[0].hasOwnProperty('btns_align') ? valj_efb[0].btns_align = align : Object.assign(valj_efb[0], { btns_align: align });
      
      break;
      default:
        //console.log('========>default')
        
        break;
  }
}//justify-content-center


const loadingShow_efb = (title) => {
  return `<div class="efb modal-dialog modal-dialog-centered efb"  id="settingModalEfb_" >
 <div class="efb modal-content efb " id="settingModalEfb-sections">
     <div class="efb modal-header efb">
         <h5 class="efb modal-title fs-5" ><i class="efb bi-ui-checks mx-2 efb" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${title ? title : efb_var.text.loading} </span></h5>
     </div>
     <div class="efb modal-body efb" id="settingModalEfb-body">
         ${loading_messge_efb()}
     </div>
 </div>
</div>`
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
  const btnPos = id != "dropZoneEFB" ? ' text-center' : ''
  //console.log(valj_efb[0])
  let dis = ''
  if (true) {
    let t = valj_efb.findIndex(x => x.type == "stripe");
     t = t==-1 ? valj_efb.findIndex(x => x.type == "persiaPay") : t;
    t = t != -1 ? valj_efb[t].step : 0;
    dis = (valj_efb[0].type == "payment" )&& (valj_efb[0].steps == 1 && t == 1) && preview_efb != true ? 'disabled' : '';
  }
  const corner = valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner: 'efb-square';
  const btns_align = valj_efb[0].hasOwnProperty('btns_align') ? valj_efb[0].btns_align + ' mx-2':'justify-content-center';
  
  const s = `
  <div class="efb d-flex ${btns_align} ${state == 0 ? 'd-block' : 'd-none'} ${btnPos} efb" id="f_btn_send_efb" data-tag="buttonNav">
    <a id="btn_send_efb" type="button" class="efb btn efb p-2 ${dis} ${valj_efb[0].button_color}  ${corner} ${valj_efb[0].el_height}  efb-btn-lg ${floatEnd}"> ${valj_efb[0].icon.length > 3 && valj_efb[0].icon != 'bi-undefined' && valj_efb[0].icon != 'bXXX' ? `<i class="efb   ${valj_efb[0].icon} mx-2  ${valj_efb[0].icon_color}   ${valj_efb[0].el_height}" id="button_group_icon"> </i>` : ``}<span id="button_group_button_single_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_single_text}</span></a>
  </div>`
  const d = `
  <div class="efb d-flex ${btns_align} ${state == 1 ? 'd-block' : 'd-none'} ${btnPos} ${efb_var.rtl == 1 ?'flex-row-reverse' :''} efb" id="f_button_form_np">
  <a id="prev_efb" type="button" class="efb btn efb p-2  ${valj_efb[0].button_color}    ${corner}   ${valj_efb[0].el_height}   efb-btn-lg ${floatEnd} m-1">${valj_efb[0].button_Previous_icon.length > 2 ? `<i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} ${valj_efb[0].el_height}" id="button_group_Previous_icon"></i>` : ``} <span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ${valj_efb[0].button_Previous_icon != 'bi-undefined' ? 'mx-2' : ''}">${valj_efb[0].button_Previous_text}</span></a>
  <a id="next_efb" type="button" class="efb btn efb ${dis} p-2 ${valj_efb[0].button_color}    ${corner}  ${valj_efb[0].el_height}    efb-btn-lg ${floatEnd} m-1"><span id="button_group_Next_button_text" class="efb  ${valj_efb[0].el_text_color} ${valj_efb[0].button_Next_text != 'bi-undefined' ? ' mx-2' : ''}">${valj_efb[0].button_Next_text}</span> ${valj_efb[0].button_Next_icon.length > 3 ? ` <i class="efb  ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}  ${valj_efb[0].el_height}" id="button_group_Next_icon"></i>` : ``}</a>
  </div>
  `
  let c = `<div class="efb footer-test efb">`
  if (id != "dropZoneEFB") {
    c += state == 0 ? `${s}</div>` : `${d}</div> <!-- end btn -->`
  } else {
    c = ` <div class="efb col-12 mb-5 pb-5 mt-3 mx-4 bottom-0 ${valj_efb[0].captcha != true ? 'd-none' : ''} " id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>  <div class="efb bottom-0 " id="button_group_efb"> <div class="efb  row  showBtns efb" id="button_group" data-id="button_group" data-tag="buttonNav">${s} ${d} ${stng} </div></div>`
  }
  if (id != 'preview' && id != 'body_efb' && !document.getElementById('button_group')) { document.getElementById(id).innerHTML += c } else {
    return c;
  }
}
const colorTextChangerEfb = (classes, color) => { return classes.replace(/(text-primary|text-darkb|text-muted|text-secondary|text-pinkEfb|text-success|text-white|text-light|\btext-colorDEfb-+[\w\-]+|text-danger|text-warning|text-info|text-dark|text-labelEfb)/, `${color}`); }
const alignChangerElEfb = (classes, value) => { return classes.replace(/(justify-content-start|justify-content-end|justify-content-center)/, `${value}`); }
const alignChangerEfb = (classes, value) => { return classes.replace(/(txt-left|txt-right|txt-center)/, `${value}`); }
const RemoveTextOColorEfb = (classes) => { return classes.replace('text-', ``); }
const colorBorderChangerEfb = (classes, color) => { return classes.replace(/\bborder+-+[\w\-]+/gi, `${color}`); }
const cornerChangerEfb = (classes, value) => { return classes.replace(/(efb-square|efb-rounded)/, `${value}`); }
const colMdChangerEfb = (classes, value) => { return classes.replace(/\bcol-md+-\d+/, `${value}`); }
const PxChangerEfb = (classes, value) => { return classes.replace(/\bpx+-\d+/, `${value}`); }
const MxChangerEfb = (classes, value) => { return classes.replace(/\bmx+-\d+/, `${value}`); }
const btnChangerEfb = (classes, value) => { return classes.replace(/\bbtn-outline-+\w+|\bbtn-+\w+/, `${value}`); }


const open_whiteStudio_efb = (state) => {

  let link = `https://whitestudio.team/document/`;
  if(efb_var.language != "fa_IR"){
  switch (state) {
    case 'mapErorr':
      link += `How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder`
      // چگونه کی گوگل مپ اضافه کنیم
      break;
    case 'pro':
      link = `https://whitestudio.team/#price`
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
      // چگونه کی گوگل مپ اضافه کنیم
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
  return ` <div class="efb card-body text-center efb"><div class="efb lds-hourglass efb"></div><h3 class="efb fs-3">${efb_var.text.pleaseWaiting}</h3></div>`
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
  
  //seprate string file to array by comma


  const tt = valj_efb.length>1 && valj_efb[indx].hasOwnProperty('file_ctype') ? valj_efb[indx].file_ctype.replaceAll(',',' , ') : '';
  filetype_efb={'image':'image/png, image/jpeg, image/jpg, image/gif',
  'media':'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg', 
  'document':'.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
  'zip':'.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip',
  'allformat':'image/png, image/jpeg, image/jpg, image/gif audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg .xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, .heic, image/heic, video/mov, .mov, video/quicktime',
  'customize':tt
  }
 //console.log(`validation of files[${filetype_efb[type].includes(fileType)}]`);
  return filetype_efb[type].includes(fileType) ;
}



let steps_len_efb 

function handle_navbtn_efb(steps, device) {

  var next_s_efb, prev_s_efb; //fieldsets
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
         // next_s_efb.style.display = "block";
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
    //One Step section

    document.getElementById("btn_send_efb").addEventListener("click", function () {
      var state = true;
      if (preview_efb == false && fun_validation_efb() == false) {
        state = false;
        return false;
      }

      setTimeout(function () {
        if (state = true) {
         if(Number(valj_efb[0].show_icon)!=1)  document.querySelector('[data-step="icon-s-' + (current_s_efb + 1) + '-efb"]').classList.add("active");
          document.querySelector('[data-step="step-' + (current_s_efb + 1) + '-efb"]').classList.toggle("d-none");
          document.getElementById("btn_send_efb").classList.toggle("d-none");
          var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
          next_s_efb = current_s.nextElementSibling;
          current_s.classList.add('d-none');
          if(next_s_efb)next_s_efb.classList.remove('d-none');
          if(document.getElementById('gRecaptcha'))document.getElementById('gRecaptcha').classList.add('d-none');
          //next_s_efb.style.display = "block";
          /* current_s.animate(
            { opacity_efb: 0 },
            {
              step: function (now) {
                // for making fielset appear animation
                opacity_efb = 1 - now;

                current_s.style.display = "none";
                current_s.style.position = "relative";
                next_s_efb.style.opacity_efb = opacity_efb;
              },
              duration: 500
            }
          ); */
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
 // prev_s_efb.style.display = "block";
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
/* if (localStorage.getItem('count_view') >= 0 && localStorage.getItem('count_view') < 3 && typeof efb_var == "object" && efb_var.maps != "1") {
 // setTimeout(() => { alert_message_efb(efb_var.text.localizationM, "", 15, "info") }, 17000);
} */




function alert_message_efb(title, message, sec, alert) {
  
  sec = sec * 1000
  
  alert = alert ? `alert-${alert}` : 'alert-info';
  const id_ = document.getElementById(`step-${current_s_efb}-efb-msg`) ? `step-${current_s_efb}-efb-msg` : `body_efb`
  let id = document.getElementById('body_efb')  ? id_ : 'alert_efb';
  if (id=="body_efb" && Number(document.getElementById('body_efb').offsetWidth)<380) id='alert_content_efb'
  
  if (document.getElementById('alert_efb')==null){
    //<div id='alert_efb' class='efb mx-5'></div>
    document.getElementById("body_efb").innerHTML += `<div id='alert_efb' class='efb mx-5'></div>`;
  }
  
  document.getElementById(id).innerHTML += ` <div id="alert_content_efb" class="efb alert_efb  alert ${alert} alert-dismissible ${efb_var.text.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <h5 class="efb alert-heading fs-4">${title}</h5>
    <div>${String(message)}</div>
    <button type="button" class="efb btn-close" data-dismiss="alert" aria-label="Close" onclick="close_msg_efb()"></button>
  </div>`
  
  document.getElementById(id).scrollIntoView({behavior: "smooth", block: "center", inline: "center"}, true);

  setTimeout(() => {
    // Hide the alert element.
   // const el = document.querySelector(".alert_efb");
    if(document.querySelector(".alert_efb")){
      document.querySelector(".alert_efb").style.display = "none";
      document.getElementById("alert_efb").innerHTML = "";
    }
  }, sec);
 // window.scrollTo({ top: document.getElementById(id).scrollHeight, behavior: 'smooth', block: "center", inline: "center" });
  //window.scrollTo({ top: document.getElementById(id).scrollHeight, behavior: 'smooth' });

}
function close_msg_efb(){
  const v=document.getElementById('alert_content_efb')
  if (v) {
    v.remove();
  }
}
function noti_message_efb(message, alert ,id) {
  //sec = sec * 1000
  
  alert = alert ? `alert-${alert}` : 'alert-info';
  /* if (document.getElementById('alert_efb')==null){
    //<div id='alert_efb' class='efb mx-5'></div>
    document.getElementById('body_efb').innerHTML += `<div id='alert_efb' class='efb mx-5'></div>`;
  } */
  let d = document.getElementById(id);
  if(document.getElementById('noti_content_efb')){
    document.getElementById('noti_content_efb').remove()
    
  }
    d.innerHTML += ` <div id="noti_content_efb" class="efb w-75 mt-0 my-1 alert-dismissible alert ${alert}  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <p class="efb my-0">${message}</p>    
  </div>`
  
 

  //window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
  //window.scrollTo({ top: document.getElementById('noti_content_efb'), behavior: 'smooth' });
  
}






function previewFormEfb(state) {
  //v2
  //console.log(state)
  if (state != "run") {
    state_efb = "view";
    preview_efb = true;
    activeEl_efb = 0;
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
  // let timeout =  (len/2)*(Math.log(len)) * p;
  //let timeout =  (len/2)*(Math.log(len)) * p;
  let timeout = state == 'run' ? 0 : len * p;
  timeout < 1700 ? timeout = 1700 : 0;
  timeout = state == 'run' ? 0 : timeout;
  if (state != "show" && state != "run") {
    if (valj_efb.length > 2) { sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)) } else {
      show_modal_efb(`<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.formNotFound}</p></div>`, efb_var.text.previewForm, '', 'saveBox');
      //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
      //myModal.show_efb();
      state_modal_show_efb(1)
      return;
    }
    if (state == "pc") {
      show_modal_efb(loading_messge_efb(), efb_var.text.previewForm, '', 'saveBox')

      //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
      //myModal.show_efb();
      state_modal_show_efb(1)
    }
  }

  ///setTimeout(() => { 

  try {
    let count =0;
    let power_by = Number(efb_var.pro) != 1 &&  ( efb_var.language=='fa' || efb_var.language=='ar')  ? `<div class="efb text-center efb d-none"><a href="https://easyformbuilder.ir" target="_blank" class="efb ">فرم ساز آسان وردپرس</a><a href="https://${efb_var.language=='ar' ? 'ar.' :''}whitestudio.team" target="_blank" class="efb text-decoration-none efb d-none">WordPress ${efb_var.text.easyFormBuilder}</a></div>` : '';
    valj_efb.forEach((value, index) => {
      let t = value.type.toLowerCase();
      if (valj_efb[index].type != "html" && valj_efb[index].type != "link" && valj_efb[index].type != "heading" && valj_efb[index].type != "persiaPay") Object.entries(valj_efb[index]).forEach(([key, val]) => { fun_addStyle_costumize_efb(val.toString(), key, index) });
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb  ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb  fs-5  ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="efb my-2 mx-0 px-0 steps-efb efb row">${power_by}` : `<!-- fieldset!!!? --><div id="step-${Number(step_no)-1}-efb-msg"></div></fieldset>${power_by}<fieldset data-step="step-${step_no}-efb"  class="efb my-2 steps-efb efb row d-none">`

        if (valj_efb[0].show_icon == false) { }
        if (valj_efb[0].hasOwnProperty('dShowBg') && valj_efb[0].dShowBg == true && state == "run") { document.getElementById('body_efb').classList.remove('card') }
      }
       
      if (value.type == 'step' && value.type != 'html') {
        
        steps_index_efb.push(index)
        //steps_index_efb.length<2 ? content =`<div data-step="${step_no}" class="efb m-2 content-efb row">` : content +=`</div><div data-step="${step_no}"  class="efb m-2 content-efb row">` 
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option' && value.type != 'r_matrix' && index>0) {
        content += addNewElement(value.type, value.id_, true, true);
        if (value.type == "html") content += "<!--testHTML-->"
        
        
      }
      if((value.hasOwnProperty('disabled') && value.disabled==true && value.hasOwnProperty('hidden')==false)
      || (value.hasOwnProperty('disabled') && value.disabled==true &&
      value.hasOwnProperty('hidden')==true && value.hidden==false)) return;
      if( value.hasOwnProperty('value') && (value.type =='email'|| value.type =='text'|| value.type =='password'|| value.type =='tel'
        || value.type =='number'|| value.type =='url'|| value.type =='textarea'|| value.type =='range')){
         
       if(typeof fun_sendBack_emsFormBuilder=="function" && value.value.length>=1) fun_sendBack_emsFormBuilder({ id_: value.id_, name: value.name, id_ob: value.id_+"_", amount: value.amount, type: value.type, value: value.value, session: sessionPub_emsFormBuilder });
      }else if(typeof fun_sendBack_emsFormBuilder=="function" && value.hasOwnProperty('value') && value.value.length>0 && value.type !='option' ){
        
        
        let o=[]

        if(t.includes('radio')==true || value.type=='radio'){
          
          count+=1;
          let ch = valj_efb.find(x=>x.id_==value.value || x.id_old==value.value);
          //console.log(`=========================>ch`,ch)
          
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
          //console.log(o.at(-1));
          Object.assign(o.at(-1),{price:ch.price})
        }
        t=1;
          
        }else if (t.includes('checkbox')==true){
          count+=1;
          
          
          for(let c of value.value){
            let ch = valj_efb.find(x=>x.id_==c);
            //console.log(`=========================>ch`,ch)          
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
              //console.log(o.at(-1));
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
          //console.log(`=========================>ch`,ch)
        
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
          //console.log(`============================================>t===1`)
          for(let i in o){
            
            fun_sendBack_emsFormBuilder(o[i]);
          }
          
        }
        
      }
    })

    step_no += 1;
    content += `
           ${valj_efb[0].hasOwnProperty('logic')==false ||(valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==false)  ? fun_captcha_load_efb() : '<!--logic efb--!>'}
           </fieldset>
          <fieldset data-step="step-${step_no}-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
            ${valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==true  ? fun_captcha_load_efb() : loading_messge_efb()}                
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
    
     <form id="efbform"> ${head} <div class="efb mt-1 px-2">${content}</div> </form>
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
            <div class="efb lds-hourglass efb"></div><h3 class="efb ">${efb_var.text.pleaseWaiting}</h3>
            </div>
         
        </div>
        
      </div> `
    show_modal_efb(frame, efb_var.text.mobilePreview, 'bi-phone', 'settingBox');
    ReadyElForViewEfb(content)
    

  } else {
    
    //content is hold element and should added to a innerHTML
    document.getElementById(id).innerHTML = content;
    document.getElementById(id).innerHTML += add_buttons_zone_efb(t, id);
    if (valj_efb[0].type == "payment") {      
     //if (ajax_object_efm.paymentGateway == "stripe" && typeof fun_add_stripe_efb =="function") fun_add_stripe_efb();
     if (ajax_object_efm.paymentGateway == "stripe" && typeof post_api_stripe_apay_efb =="function") post_api_stripe_apay_efb();
    }
  }
  let ttype ='text'
  try {
    const len = valj_efb.length;
    
    valj_efb.forEach((v, i) => {
      let disabled = v.hasOwnProperty('disabled') ? v.disabled : false;
      switch (v.type) {
        case "maps":
          initMap(disabled);
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

            // const ob = valueJson_ws.find(x => x.id_ === el.dataset.code);
            const el = document.getElementById(`${v.id_}-sig-data`);
            const value = el.value;
            document.getElementById(`${v.id_}_-message`).classList.remove('show');
            const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: value, session: sessionPub_emsFormBuilder }];
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
          let callback = 1;
          function mutlselect(len) {
            setTimeout(() => {
              //const v = valj_efb.find(x=>x.id_==rndm);
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
  // if (state != "show") myModal.show_efb();
  step_el_efb = Number(valj_efb[0].steps);
 
  //if (localStorage.getItem('formId') == efb_var.id && state == 'run' && 
  if ( state == 'run' && 
  ( (addons_emsFormBuilder.AdnOF==1 && typeof valj_efb[0].AfLnFrm =='string' &&  valj_efb[0].AfLnFrm==1) ) || (valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string") ) { fun_offline_Efb() 
  }

  
  if (ttype=='ardate'){
    //778899
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
  //}, timeout) //nlogn
}//end function v2



function fun_prev_send() {
  var stp = Number(valj_efb[0].steps) + 1;
  //console.log(stp,valj_efb[0].steps,current_s_efb )
  //current_s_efb -=1;
  //console.log(stp,valj_efb[0].steps,current_s_efb )
  var wtn = loading_messge_efb();
  document.getElementById('efb-final-step').innerHTML = wtn;
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
  //prev_s_efb.style.display = "block";

  current_s.classList.add('d-none');
  prev_s_efb.classList.remove('d-none');
  /* current_s.animate(
    { opacity_efb: 0 },
    {
      step: function (now) {
        opacity_efb = 1 - now;

        current_s.style.display = 'none';
        current_s.style.position = 'relative';
        prev_s_efb.style.opacity_efb = opacity_efb;
      },
      duration: 500
    }
  ); */
  current_s_efb -= 1;
  localStorage.setItem('step', current_s_efb);
  setProgressBar_efb(current_s_efb, stp);
}

function verifyCaptcha(token) {
  if (token.length > 1) {
    //verifyCaptcha_efb=token;
    const id = valj_efb[0].steps > 1 ? 'next_efb' : 'btn_send_efb'
    document.getElementById(id).classList.remove('disabled');
    setTimeout(() => { timeOutCaptcha() }, 61000)
  }
}


function timeOutCaptcha() {
  const id = valj_efb[0].steps > 1 ? 'next_efb' : 'btn_send_efb'
  document.getElementById(id).classList.add('disabled');
  // ajax_object_efm.text.errorVerifyingRecaptcha
  alert_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.errorVerifyingRecaptcha, 7, 'warning');
}

fun_el_select_in_efb = (el) => { return el == 'conturyList' || el == 'stateProvince' || el == 'select' || el == 'multiselect' || el == 'paySelect' || el == 'payMultiselect' ? true : false }
fun_el_check_radio_in_efb = (el) => { return el == 'radio' || el == 'checkbox' || el == 'payRadio' || el == 'payCheckbox' || el == 'imgRadio' || el == 'chlRadio' || el == 'chlCheckBox' ? true : false }

function fun_validation_efb() {
  //console.log('fun_validate')
  let offsetw = document.getElementById('body_efb').offsetWidth;
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onClick="alert_message_efb('${efb_var.text.enterTheValueThisField}','',10,'danger')"></div>` : efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null";
  for (let row in valj_efb) {
    let s = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == valj_efb[row].id_)
    
    
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      
      //  let s = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == valj_efb[row].id_)
      //console.log(sendBack_emsFormBuilder_pub,s ,valj_efb[row].id_ )
      // console.log(`exist [${s}] row[${row}] id[${valj_efb[row].id_}] type[${valj_efb[row].type}] `,valj_efb[row] , sendBack_emsFormBuilder_pub[s])
      const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      let el =document.getElementById(`${valj_efb[row].id_}_-message`);
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        //files_emsFormBuilder
        let r=files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_);
        s = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state)==0 || r==-1 ? -1 :1;
      }
      if (s == -1) {
        if (state == true) { state = false; idi = valj_efb[row].id_ }
        const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
        
        //let el =document.getElementById(`${valj_efb[row].id_}_-message`);
        if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
          el.classList.add('unpx');                
        }
        el.innerHTML = msg;
        if(!el.classList.contains('show'))el.classList.add('show');
        if (type_validate_efb(valj_efb[row].type) == true) {
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");}
      } else {
        // console.log('success')
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
     /*  const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger"); */
    // sendBack_emsFormBuilder_pub[s].hasOwnProperty('qty') && sendBack_emsFormBuilder_pub[s].qty.length==""
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
  //console.log(state)
  return state
}

function type_validate_efb(type) {
  // console.log(type)
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
  /* if (type == "text") { v = `.${type}-${t}{color:${c}!important;}` }
  else if (type == "icon") { v = `.text-${t}{color:${c}!important;}` }
  else if (type == "border") { v = `${tag}.${type}-${t}{border-color:${c}!important;}` }
  else if (type == "bg") { v = `.${type}-${t}{background-color:${c}!important;}` }
  else if (type == "btn") { v = `.${type}-${t}{background-color:${c}!important;}` }
  document.body.appendChild(Object.assign(document.createElement("style"), { textContent: `${v}` })) */
}


efb_add_costum_color=(t, c ,v , type)=>{
  //console.log('===>efb_add_costum_color',t, c ,v , type)
  //t colorDEfb-000e24
  //c #000e24
  //v = ""
  //type = text, border , bg , btn
  
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
    //console.error(color, type, val,key,indexVJ ,valj_efb[0])
    if (color != "") addStyleColorBodyEfb((`colorDEfb-${color.slice(1)}`), color.length>6 ? color.slice(-6) : color, type, indexVJ);
    //t=>[colorDEfb-tn-colorDEfb-ff5900] c=>[btn-colorDEfb-ff5900] btn
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
        //console.log('stripe')
        
        break;
    }
  }
 // const vvv= getUrlparams_efb.get('Authority');
  if(valj_efb[0].type=="payment" && valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string"){
    fun_after_bankpay_persia_ui();
  }
}

function send_data_efb() {
  //if is preview 210201-SMHTH06 then recive from server and show
  //console.log('send_data_efb')
  if (state_efb != "run") {
    const cp = funTnxEfb('DemoCode-220201')
    document.getElementById('efb-final-step').innerHTML = cp
    // current_s_efb=1;
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
  <div class="efb fs-4"><h5 class="efb mt-3 efb fs-4 ${clr_doneMessageEfb}" id="doneTrackEfb">${valj_efb[0].thank_you_message.trackingCode || efb_var.text.trackingCode}: <strong>${val}</strong></h5>
               <input type="text" class="efb hide-input efb " value="${val}" id="trackingCodeEfb">
               <div id="alert"></div>
               <button type="button" class="efb btn  ${corner} efb ${valj_efb[0].button_color}  ${valj_efb[0].el_text_color} efb-btn-lg my-3 fs-5" onclick="copyCodeEfb('trackingCodeEfb')">
                   <i class="efb fs-5 bi-clipboard-check mx-1  ${valj_efb[0].el_text_color}"></i>${efb_var.text.copy}
               </button></div>`
  return `
                    <h4 class="efb  my-1 fs-2 ${doneTrackEfb}" id="doneTitleEfb">
                        <i class="efb ${valj_efb[0].thank_you_message.hasOwnProperty('icon') ? valj_efb[0].thank_you_message.icon : 'bi-hand-thumbs-up'}  title-icon mx-2 fs-2 ${clr_doniconEfb}" id="DoneIconEfb"></i>${t}
                    </h4>
                    <h3 class="efb fs-4 ${clr_doneMessageEfb}" id="doneMessageEfb">${m}</h3>
                  <span class="efb " ${valj_efb[0].trackingCode == true ? trckCd : '</br>'}</span>
  
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
     // if (el_parent.classList.contains('row')) el_parent.classList.remove('row');
     // if (!el_input.classList.contains('mx-2')) el_input.classList.add('mx-2');
      //if (!el_label.classList.contains('mx-2')) el_label.classList.add('mx-2');
    }
  } else {
    parent_row = 'row';
    if (state == true) {
      
    //  el_parent && el_parent.classList.contains('row') ? 0 : el_parent.classList.add('row')
      //if (el_input.classList.contains('mx-2')) el_input.classList.remove('mx-2');
      //if (el_label.classList.contains('mx-2')) el_label.classList.remove('mx-2');
    }
  }
  
  if (state == true) {
    el_parent.classList = colMdChangerEfb(el_parent.className, parent_col);
   if(el_input!="null") el_input.classList = colMdChangerEfb(el_input.className, input_col);
   if(el_label!="null") el_label.classList = colMdChangerEfb(el_label.className, label_col);
  }
  //console.log(`============================>parent_row[${parent_row}] parent_col[${parent_col}], label_col[${label_col}], input_col[${label_col}]`)
  return [parent_row, parent_col, label_col, input_col]
}

/* var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
}) */

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
  // value = value.replaceAll("@efb@bsq#","\\");
   value = value.replaceAll("@efb@vq#","`");
   value = value.replaceAll("@efb@dq#",`''`);
   value = value.replaceAll("@efb@nq#",`<br>`);
   
  return value;

}


function fun_upload_file_api_emsFormBuilder(id, type,tp) {
  //console.log('fun_upload_file_api_emsFormBuilder',id, type,tp)
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
  
  
  //console.log(tp)
  //jQuery(function ($) {
    
    const fd = new FormData();
    const idn =  id + '_';
    const file = document.getElementById(idn);
    //const caption = document.querySelector(idn);
    

	const individual_file = file.files[0];
  //console.log('before ===> uploadFile_api',`tp===>[${tp}]` ,id, type, nonce_msg ,indx,idn)
  uploadFile_api(file.files[0], id, tp, nonce_msg ,indx ,idn)
  return true;
}

function uploadFile_api(file, id, pl, nonce_msg ,indx,idn) {
  //console.log('uploadFile_api',file, id, pl, nonce_msg ,indx,idn)
  const progressBar = document.querySelector('#progress-bar');
  const idB =id+'-prB';
  setTimeout(() => {
      fetch_uploadFile(file, id, pl, nonce_msg).then((data) => {
        // Handle the response data
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
            
            }];

            fun_sendBack_emsFormBuilder(o[0]);
            const el = document.getElementById(idB)
            if(el){
              el.style.width = '100%';
              el.textContent = '100% = ' + file.name;
            }

            if(document.getElementById(id + '-prG')) document.getElementById(id + '-prG').classList.add('d-none');
        } else {
          const m = data.data.hasOwnProperty('file') ? data.data.file.error : data.data.m
          alert_message_efb('', m, 30, 'danger');
          document.querySelector(idB).style.width = '0%';
          document.querySelector(idB).textContent = '0% = ' + file.name;
          return;
        }
      })
      .catch((error) => {
        // Handle the error
        console.error(error);
      }); 

  }, 800);
}

function fetch_uploadFile(file, id, pl, nonce_msg) {
  //console.log('fetch_uploadFile',file, id, pl, nonce_msg)
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
  //setTimeout(()=>{n_win.close();},10); 

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
       //console.log(document.getElementById("replayB_emsFormBuilder"));
       if(document.getElementById("attach_efb")) document.getElementById("attach_efb").remove();
       document.getElementById("replayM_emsFormBuilder").remove();
       document.getElementById("label_replyM_efb").remove();
       document.getElementById("replay_state__emsFormBuilder").innerHTML=`<h5 class="efb fs-4 my-3 text-center text-pinkEfb">${efb_var.text.clsdrspn}</h5>`

      }else{
        //XZ7CN48OKGV9
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
  // Get the `replayM_emsFormBuilder` element.
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


/* setTimeout(() => {
  console.log('set time out run!')
  noti_message_efb('this a test mesage for insure' , 'danger' , `step-${current_s_efb}-efb-msg` )
}, 5000); */




 function fun_show_val_range_efb(id ){
  document.getElementById(id+'_rv').innerText=document.getElementById(id+'_').value;
  //console.log(document.getElementById(id+'_').value);
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

  // Assume the site charset is UTF-8
  const isUTF8 = true;

  if (!isUTF8) {
    return string;
  }

  // Check for support for UTF-8 in the installed PCRE library
  let utf8PCRE = true;

  // We can't demand UTF-8 in the PCRE installation, so return the string
  if (!utf8PCRE) {
    return string;
  }
  
  // Check if the string is valid UTF-8
  if (encodeURI(string) === string) {
    return string;
  }
  
  // Attempt to strip the bad chars if requested (not recommended)
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
    //console.log(`escHtml[${unsafe}]`)
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
    console.log(url,unicodeUrl);
    filtered = filtered.replace(url,unicodeUrl);
  }
  //let filtered = checkInvalidUTF8_efb(str);
  //console.log(`filtered[${filtered}]`)
  if (filtered.indexOf('<') !== -1) {
    
   // filtered = preKsesLessThan_efb(filtered);
    filtered = stripAllTags_efb(filtered, false);
    filtered = filtered.replace(/<\n/g, '&lt;\n');
    //console.log(`filtered[${filtered}]`)
  }

  if (!keep_newlines) {
    filtered = filtered.replace(/[\r\n\t ]+/g, ' ');
  }else{
    filtered = filtered.replace(/[\t ]+/g, ' ');
    filtered = filtered.replace(/[\r\n]+/g, '@n#');
  }
 // console.log(`filtered[${filtered}]`)
  filtered = filtered.trim();
  //console.log(`filtered[${filtered}]`)





  let found = false;
  while (/%[a-f0-9]{2}/i.test(filtered)) {
    filtered = filtered.replace(/%[a-f0-9]{2}/i, '');
    found = true;
  }
  //console.log(`filtered[${filtered}]`)
  if (found) {
    filtered = filtered.trim().replace(/ +/g, ' ');
  }
  
  //console.log(`filtered[${filtered}]`)
  return filtered;
}

valNotFound_efb=()=>{
        //console.error('message not Found');
        alert_message_efb(efb_var.text.error,efb_var.text.empty, 30,'danger');
}


const add_r_matrix_view_select = (idin, value, id_ob, tag, parentsID) => {
  
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  
  // let op = `<!-- option --!> 2`

  let tagtype = tag;
  position_l_efb = efb_var.rtl == 1 ? "end" : "start";

  let col = valj_efb[indxP].hasOwnProperty('op_style') && Number(valj_efb[indxP].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[indxP].op_style)) : ''
  //console.log(col, valj_efb[indxP],Number(valj_efb[indxP].op_style ))
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
    // use the parsed JSON data here
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
  return ` ${sitekye_emsFormBuilder.length > 1 ? `<div class="efb row mx-0"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0 px-0" data-sitekey="${sitekye_emsFormBuilder}" data-callback="verifyCaptcha" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
            <!-- fieldset1 --> 
            ${state_efb == "view" && valj_efb[0].captcha == true ? `<div class="efb col-12 mb-2 mx-0 mt-3 efb" id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>` : ''}
            <div id="step-1-efb-msg"></div>`
 }


 function fun_send_mail_emsFormBuilder(id,nonce,type_) {
  

 const data = {
    action: "mail_send_submited_Emsfb",
    id: id,
    type_: type_,
    nonce:nonce,
    sid:efb_var.sid
  };
  
  const headers = new Headers({
    'Content-Type': 'application/json',
  });

  const url = efb_var.rest_url+'Emsfb/v1/forms/email/send'; // Replace with your REST API endpoint URL

  const jsonData = JSON.stringify(data);
  const requestOptions = {
    method: 'POST', // Or any other HTTP method (POST, GET, etc.)
    headers,
    body: jsonData, // The JSON data as the request body
  };

fetch(url, requestOptions)
.then(response => response.json())
.then(responseData => {
  // Handle the response data
  //response_fill_form_efb(responseData)
  //console.log(`responseData`,responseData)
  console.table(responseData);
})
.catch(error => {
  // Handle errors
  console.error(`error`,error)
  
});


}

function efb_text_nr(text , type){
  const val = type ==1 ?'<br>': '\n';
  //console.log(`efb_text_nr[${text}]`)
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










