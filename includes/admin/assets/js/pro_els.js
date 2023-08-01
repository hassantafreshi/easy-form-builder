 maps_el_pro_efb =(previewSate, pos , rndm,iVJ)=>{
    return `
    <div class="efb  ${previewSate == true ? pos[3] : `col-md-12`} col-sm-12 "  id='${rndm}-f'>      
      ${previewSate == true && valj_efb[iVJ].mark != 0 ? `<div id="floating-panel" class="efb "><input id="delete-markers_maps_efb-efb" class="efb  btn btn-danger" type="button" value="${efb_var.text.deletemarkers}" /></div>` : '<!--notPreview-->'}
        <div id="${rndm}-map" data-type="maps" class="efb  maps-efb emsFormBuilder_v ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} " data-id="${rndm}-el" data-name='maps'>
      </div>
    `
 }
 dadfile_el_pro_efb =(previewSate , rndm,iVJ)=>{
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square'
  let disabled =  valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==true? 'disabled' : ''
    return `<div class="efb  mb-3" id="uploadFilePreEfb">
                <label for="${rndm}_" class="efb  form-label">
                    <div class="efb  dadFile-efb py-0 ${disabled} ${valj_efb[iVJ].el_height} ${corner}   ${valj_efb[iVJ].el_border_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}_box" ${disabled}>
                    ${ui_dadfile_efb(iVJ, previewSate)}                            
                    </div>
                </label>
            </div>`;
}
esign_el_pro_efb =(previewSate, pos , rndm,iVJ,desc)=>{
    const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square'
    let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
    return `<div class="efb  ${pos[3]} col-sm-12" id ="${rndm}-f">
    <canvas class="efb  sign-efb bg-white ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-code="${rndm}"  data-id="${rndm}-el" id="${rndm}_" width="600" height="200">
        ${efb_var.text.updateUrbrowser}
    </canvas>
   ${previewSate == true ? `<input type="hidden" data-type="esign" data-vid='${rndm}' class="efb  emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">` : ``}
    <div class="efb  mx-1">${desc}</div>
    <div class="efb  mb-3"><button type="button" class="efb  btn ${corner} ${valj_efb[iVJ].button_color} efb-btn-lg mt-1 fs-6 ${disabled}" id="${rndm}_b" onClick="fun_clear_esign_efb('${rndm}')">  <i class="efb  ${valj_efb[iVJ].icon} mx-2 ${valj_efb[iVJ].icon_color != "default" ? valj_efb[iVJ].icon_color : ''} " id="${rndm}_icon"></i><span id="${rndm}_button_single_text" class="efb  ${valj_efb[iVJ].icon_color} efb " ${disabled}>${valj_efb[iVJ].button_single_text}</span></button></div>
      `;
}
rating_el_pro_efb =(previewSate,pos, rndm,iVJ)=>{
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
    return ` <div class="efb  ${pos[3]} col-sm-12" id ="${rndm}-f">
      <div class="efb  star-efb d-flex justify-content-center ${disabled} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"> 
                        <input type="radio" id="${rndm}-star5" data-vid='${rndm}' data-type="rating" class="efb "   data-star='star'  name="${rndm}-star-efb" value="5" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star5" for="${rndm}-star5"  ${previewSate == true && disabled==false ? `onClick="fun_get_rating_efb('${rndm}',5)"` : ''} title="5stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">5 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star4" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" value="4" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star4"  for="${rndm}-star4" ${previewSate == true  && disabled==false ? `onClick="fun_get_rating_efb('${rndm}',4)"` : ''} title="4stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">4 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star3" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" data-name="star" value="3"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star3" for="${rndm}-star3"  ${previewSate == true  && disabled==false ? `onClick="fun_get_rating_efb('${rndm}',3)"` : ''} title="3stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">3 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star2" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' data-name="star" name="${rndm}-star-efb" value="2"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star2" for="${rndm}-star2" ${previewSate == true  && disabled==false ? `onClick="fun_get_rating_efb('${rndm}',2)"` : ''} title="2stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">2 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star1" data-vid='${rndm}' data-type="rating" class="efb " data-star='star' data-name="star" name="${rndm}-star-efb" value="1"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star1" for="${rndm}-star1" ${previewSate == true && disabled==false ? `onClick="fun_get_rating_efb('${rndm}',1)"` : ''} title="1star" class="efb   ${valj_efb[iVJ].el_height} star ${disabled}">1 ${efb_var.text.star}</label>
      </div>  
      <input type="hidden" data-vid="${rndm}" data-type="rating" class="efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-stared" >`
}
pointer10_el_pro_efb = (previewSate, classes,iVJ)=>{
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  previewSate = previewSate != true ? 'disabled' : '';
  return ` <div class="efb  NPS flex-row  justify-content-right efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" >     
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="0"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)">0</div>                            
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="1"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 1</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="2"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 2</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="3"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 3</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="4"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 4</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="5"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 5</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="6"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 6</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="7"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 7</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="8"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)"> 8</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="9"  data-id="${valj_efb[iVJ].id_}"  onclick="fun_nps_rating(this)">9</div>
  <div class="efb emsFormBuilder_v rating  btn btn-outline-secondary mx-1 mb-1 ${previewSate} ${disabled}"  data-point="10"  data-id="${valj_efb[iVJ].id_}" onclick="fun_nps_rating(this)">10</div>
  <input type="hidden" data-vid="${valj_efb[iVJ].id_}" data-type="rating"  id="${valj_efb[iVJ].id_}-nps-rating" >
  </div> `
}
pointer5_el_pro_efb = (previewSate, classes,iVJ)=>{
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  previewSate = previewSate != true ? 'disabled' : '';
  return `   <div class="efb d-flex justify-content-right efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}"> 
  <div class="efb btn btn-secondary emsFormBuilder_v  text-white mx-1 ${previewSate} ${disabled}" data-point="1" data-id="${valj_efb[iVJ].id_}" onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
  <div class="efb btn btn-secondary emsFormBuilder_v  text-white mx-1 ${previewSate} ${disabled}" data-point="2" data-id="${valj_efb[iVJ].id_}" onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
  <div class="efb btn btn-secondary emsFormBuilder_v  text-white mx-1 ${previewSate} ${disabled}" data-point="3" data-id="${valj_efb[iVJ].id_}" onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
  <div class="efb btn btn-secondary emsFormBuilder_v  text-white mx-1 ${previewSate} ${disabled}" data-point="4" data-id="${valj_efb[iVJ].id_}" onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
  <div class="efb btn btn-secondary emsFormBuilder_v  text-white mx-1 ${previewSate} ${disabled}" data-point="5" data-id="${valj_efb[iVJ].id_}" onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
  <input type="hidden" data-vid="${valj_efb[iVJ].id_}" data-type="rating" id="${valj_efb[iVJ].id_}-point-rating">
  </div> `
}
smartcr_el_pro_efb = (previewSate, classes,iVJ)=>{
  return `<h3> Smart</h3> `
}
countryList_el_pro_efb = ( rndm,rndm_1,op_3,op_4,editState)=>{
    let optn ='<!--countreies-->'
    if (editState != false) {
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb"  ${valj_efb[indx_parent].value==i.id_ ||( i.hasOwnProperty('id_old') && valj_efb[indx_parent].value==i.id_old) ? "selected" :''}>${i.value}</option>`
        }//end for 
      } else {
        if (typeof counstries_list_efb  != 'object') {
          optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="efb text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="efb text-dark efb" >${efb_var.text.newOption} 2</option>
           `
          optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3 ,'select');
          optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4 ,'select');
        } else {
          let optn = '<!-- list of counries -->'
          let count =0;
          for (let i of counstries_list_efb) {
            count+=1;
            let id = rndm_1 +count
            const op_id = i.s2.toLowerCase();
            optn += `<option value="${i.n} (${i.l})" id="${id}" data-vid='${rndm}' data-id="${op_id}" data-op="${op_id}" class="efb text-dark efb fs-6" >${i.n} (${i.l})</option>`
            optionElpush_efb(rndm, `${i.n} (${i.l})`, id, op_id ,'select');
          }
        }
      }
      return optn;
}
statePrevion_el_pro_efb = (rndm,rndm_1,op_3,op_4,editState)=>{
    let optn ='<!--states-->'
    const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
    if (editState != false) {
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb" ${valj_efb[indx_parent].value==i.id_ || ( i.hasOwnProperty('id_old') && valj_efb[indx_parent].value==i.id_old) ? "selected" :''}>${i.value}</option>`
        }//end for 
      } else {
        if (typeof state_local != 'object') {
          optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="efb text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="efb text-dark efb" >${efb_var.text.newOption} 2</option>
           `
          optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3 ,'select');
          optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4 ,'select');
        } else {
          state_local.sort();
          let optn = '<!-- list of states -->'
          let count =0;
          for (let i = 0; i < state_local.length; i++) {
            count+=1;
            let id = rndm_1 +count
            optn += `<option value="${state_local[i]} 1" id="${id}" data-vid='${rndm}' data-id="${state_local[i]}" data-op="${state_local[i]}" class="efb text-dark efb" ${valj_efb[indx_parent].value==i.id_ || valj_efb[indx_parent].value==i.id_old ? "selected" :''}>${state_local[i]}</option>`
            optionElpush_efb(rndm, state_local[i], id, state_local[i] ,'select');
          }
        }
      }
      return optn;
}
headning_el_pro_efb = (rndm,pos,iVJ)=>{
    return ` <div class="efb px-0 mx-0  ${pos[0]} col-sm-12"  id='${rndm}-f'>
    <p  id="${rndm}_"  class="efb  px-0  emsFormBuilder_v  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size}   efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el" >${valj_efb[iVJ].value}</p>
    </div>`
}
link_el_pro_efb = (previewSate,pos, rndm,iVJ)=>{
    return`<div class="efb ${pos[0]} px-0 mx-0   col-sm-12"  id='${rndm}-f'>
    <a  id="${rndm}_"  target="_blank" class="efb  px-0 btn underline emsFormBuilder_v ${previewSate != true ? 'disabled' : ''}  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size} efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el" href="${valj_efb[iVJ].href}">${valj_efb[iVJ].value}</a>
    </div>`
}
yesNi_el_pro_efb = (previewSate,pos, rndm,iVJ)=>{
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square';
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
    return `<div class="efb ${pos[3]} col-sm-12 efb  ${disabled} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'>
    <div class="efb  btn-group  btn-group-toggle w-100  col-md-12 col-sm-12  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" data-toggle="buttons" data-id="${rndm}-id" id="${rndm}_yn">    
    <label for="${rndm}_1" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_1_text}" onClick="yesNoGetEFB('${valj_efb[iVJ].button_1_text}', '${rndm}' ,'${rndm}_b_1')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${corner} yesno-efb left-efb  ${disabled} ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_1">
      <input type="radio" name="${rndm}" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_1" value="${valj_efb[iVJ].button_1_text}"><span id="${rndm}_1_lab">${valj_efb[iVJ].button_1_text}</span></label>
    <span class="efb border-right border border-light efb"></span>
    <label for="${rndm}_2" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_2_text}" onClick="yesNoGetEFB('${valj_efb[iVJ].button_2_text}' ,'${rndm}','${rndm}_b_2')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${corner} yesno-efb right-efb  ${disabled} ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_2">
      <input type="radio" name="${rndm}" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_2" value="${valj_efb[iVJ].button_2_text}"> <span id="${rndm}_2_lab">${valj_efb[iVJ].button_2_text}</span></label>
    </div>`
}
html_el_pro_efb = (previewSate, rndm,iVJ)=>{
    let ui ='';
    if (valj_efb[iVJ].value.length < 2) {
        ui = ` 
        <div class="efb col-sm-12 efb"  id='${rndm}-f' data-id="${rndm}-el" data-tag="htmlCode">            
            <div class="efb boxHtml-efb sign-efb efb" id="${rndm}_html">
            <div class="efb noCode-efb m-5 text-center efb" id="${rndm}_noCode">
              ${efb_var.text.noCodeAddedYet} <button type="button" class="efb BtnSideEfb btn efb btn-edit efb btn-sm" id="settingElEFb"
              data-id="${rndm}-id" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
              onclick="show_setting_window_efb('${rndm}-id')">
              <div class="icon-container efb"><i class="efb   bi-gear-wide-connected text-success" id="efbSetting"></i></div></button>${efb_var.text.andAddingHtmlCode}
          </div></div></div>`;
      } else {
        ui = valj_efb[iVJ].value.replace(/@!/g, `"`) ;
        ui = valj_efb[iVJ].value.replace(/@efb@nq#/g, ``) + "<!--endhtml first -->";
        ui = `<div ${previewSate == false ? `class="efb bg-light" id="${rndm}_html" ` : ''}> ${ui} </div>`;
      }
      return ui;
}
 function ui_dadfile_efb(indx, previewSate) {
    let n = valj_efb[indx].file;
    n = efb_var.text[n];
    let types = ""
    let disabled =  valj_efb[indx].hasOwnProperty('disabled') &&  valj_efb[indx].disabled==true? 'disabled' : ''
    filetype_efb={'image':'image/png, image/jpeg, image/jpg, image/gif, image/heic',
    'media':'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg, video/mov, video/quicktime',
    'document':'.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
    'zip':'.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip',
    'allformat':'image/png, image/jpeg, image/jpg, image/gif audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg .xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, .heic, image/heic, video/mov, .mov, video/quicktime, video/quicktime'
      }
    return `<div class="efb icon efb"><i class="efb  fs-3 ${valj_efb[indx].icon} ${valj_efb[indx].icon_color}" id="${valj_efb[indx].id_}_icon"></i></div>
    <h6 id="${valj_efb[indx].id_}_txt" class="efb text-center m-1 fs-6">${efb_var.text.dragAndDropA} ${n} </h6> <span class="efb fs-7">${efb_var.text.or}</span>
    <button type="button" class="efb  btn ${valj_efb[indx].button_color} efb-btn-lg fs-6" id="${valj_efb[indx].id_}_b" ${disabled}>
        <i class="efb  bi-upload mx-2 fs-6"></i>${efb_var.text.browseFile}
    </button>
   <input type="file" hidden="" accept="${filetype_efb[valj_efb[indx].value]}" data-type="dadfile" data-vid='${valj_efb[indx].id_}' data-ID='${valj_efb[indx].id_}' class="efb  emsFormBuilder_v   ${valj_efb[indx].required == 1 || valj_efb[indx].required == true ? 'required' : ''}" id="${valj_efb[indx].id_}_" data-id="${valj_efb[indx].id_}-el" ${previewSate != true ? 'disabled' : ''} ${disabled}>`
  }
function viewfileEfb(id, indx) {
    let fileType = fileEfb.type;
    const filename = fileEfb.name;
    let icon = ``;
    switch (valj_efb[indx].file) {
      case 'document':
      case 'allformat':
        icon = `bi-file-earmark-richtext`;
        break;
      case 'media':
        icon = `bi-file-earmark-play`;
        break;
      case 'zip':
        icon = `bi-file-earmark-zip`;
        break;
    }
    let box_v = `<div class="efb ">
    <button type="button" class="efb btn btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onClick="removeFileEfb('${id}',${indx})"
         aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.removeTheFile}"></button> 
         <div class="efb card efb">
          <i class="efb  ico-file ${icon} ${valj_efb[indx].icon_color} text-center fs-2"></i>
          <span class="efb  text-muted">${fileEfb.name}</span>
          </div>
    </div>`;
    fun_addProgessiveEl_efb(id ,0);
    if (validExtensions_efb_fun(valj_efb[indx].file, fileType)) {
      let fileReader = new FileReader();
      fileReader.onload = () => {
        let fileURL = fileReader.result;
        const box = document.getElementById(`${id}_box`)
        if (valj_efb[indx].file == "image") {
          box.innerHTML = `<div class="efb ">
              <button type="button" class="efb btn btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onClick="removeFileEfb('${id}',${indx})"
                   aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title=${efb_var.text.removeTheFile}"></button> 
              <img src="${fileURL}" alt="image">
              </div>`;
        } else {
          box.innerHTML = box_v;
        }
      }
      fileReader.readAsDataURL(fileEfb);
      document.getElementById(`${id}_-message`).innerHTML = "";
      document.getElementById(`${id}_-message`).classList.remove('show')
    } else {
      const m = `${efb_var.text.pleaseUploadA} ${efb_var.text[valj_efb[indx].file]}`;
      document.getElementById(`${id}_-message`).innerHTML = m;
      if(document.getElementById(`${id}_-message`).classList.contains('show'))document.getElementById(`${id}_-message`).classList.add('show');
      alert_message_efb('', m, 4, 'danger')
      document.getElementById(`${id}_box`).classList.remove("active");
      fileEfb = [];
    }
  }
function viewfileReplyEfb(id, indx) {
    let fileType = fileEfb.type;
    const filename = fileEfb.name;
    if (validExtensions_efb_fun('allformat',fileType)) {
      fun_addProgessiveEl_efb('resp_file_efb' ,1);
      let fileReader = new FileReader();
      fileReader.onload = () => {
        let fileURL = fileReader.result;
      }
      fileReader.readAsDataURL(fileEfb);  
      files_emsFormBuilder=[{ id_: 'resp_file_efb', value: "@file@", state: 0, url: "", type: "file", name: 'file', session: sessionPub_emsFormBuilder , amount:0 }];
      fun_upload_file_api_emsFormBuilder('resp_file_efb', 'allformat' ,'resp');
      document.getElementById('name_attach_efb').innerHTML = fileEfb.name.length > 10 ? `${fileEfb.name.slice(0,7)}..` :fileEfb.name;
    } else {
      const m = `${efb_var.text.pleaseUploadA} ${efb_var.text['media']} | ${efb_var.text['document']} | ${efb_var.text['zip']} `;      
      alert_message_efb('', m, 4, 'danger')
      fileEfb = [];
    }
  }
function removeFileEfb(id, indx) {
    fileEfb = "";
    document.getElementById(`${id}_box`).innerHTML = ui_dadfile_efb(indx)
    setTimeout(() => {
      create_dadfile_efb(id, indx);
      document.getElementById(`${id}_`).addEventListener('change', () => {
        valid_file_emsFormBuilder(id);
      })
    }, 500)
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      let inx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == id);
      if (inx != -1) {
        sendBack_emsFormBuilder_pub.splice(inx, 1)
        inx = files_emsFormBuilder.findIndex(x => x.id_ == id)
        files_emsFormBuilder[inx].url = "";
      }
      else {
        inx = files_emsFormBuilder.findIndex(x => x.id_ == id)
        if (inx != -1) {
          files_emsFormBuilder[inx].url = "";
          setTimeout(() => {
            inx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == id);
            if (inx != -1) { sendBack_emsFormBuilder_pub.splice(inx, 1) }
          }, 100);
        }
      }
    }
    fun_removeProgessiveEl_efb(id);
  }//end function
function gm_authFailure() {
    const body = `<p class="efb fs-6 efb">${efb_var.text.aPIkeyGoogleMapsFeild} <a href="https://developers.google.com/maps/documentation/javascript/error-messages" target="blank">${efb_var.text.clickHere}</a> </p>`
    alert_message_efb(efb_var.text.error, body, 15, 'danger')
  }
set_dadfile_fun_efb = (id, indx) => {
    setTimeout(() => { create_dadfile_efb(id, indx) }, 50)
  }
  create_dadfile_efb = (id, indx) => {
    let dropAreaEfb = document.getElementById(`${id}_box`);
    let dragTextEfb = dropAreaEfb.querySelector("h6");
    let  dragbtntEfb = dropAreaEfb.querySelector("button");
    let dragInptEfb = dropAreaEfb.querySelector("input");
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
      document.getElementById(`${id}_`).files=event.dataTransfer.files;
      dropAreaEfb.classList.add("active");
      viewfileEfb(id, indx);
      valid_file_emsFormBuilder(id)     
    });
  }
    /* attachment reply */
    reply_attach_efb = (id, indx) => {
        const v= reply_upload_efb(id);
        const lenV=(v.length/20)+10;
        let l = document.getElementById('replay_section__emsFormBuilder');
        if(l)l.innerHTML +=v  ;
        setTimeout(() => {
          let  dragbtntEfb = document.getElementById("attach_efb");
          let dragInptEfb =  document.getElementById(`resp_file_efb_`);
          if(pro_efb==true && dragInptEfb){
          dragInptEfb.addEventListener("change", function () {
            fileEfb = this.files[0];
            viewfileReplyEfb('resp_file_efb_', indx);
          });
        }
        }, lenV);
      }
    /*end  attachment reply */
  function renderCanvas_efb() {
    if (draw_mouse_efb) {
      c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);
      c2d_contex_efb.lineTo(mousePostion_efb.x, mousePostion_efb.y);
      c2d_contex_efb.stroke();
      lastMousePostion_efb = mousePostion_efb;
      const data = document.getElementById(`${canvas_id_efb}_`).toDataURL();
      document.getElementById(`${canvas_id_efb}-sig-data`).value = data;
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
    const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === id);
    if (indx != -1) sendBack_emsFormBuilder_pub.splice(indx, 1)
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
  function yesNoGetEFB(v, id,idl) {
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      let iv = idl.slice(0,-4)
       iv = idl.slice(-4)=='_b_2' ? `${iv}_b_1` :`${iv}_b_2`;
       document.getElementById(iv).classList.remove('btn-set');
       iv = document.getElementById(idl)
       if(!iv.classList.contains('btn-set')) iv.classList.add('btn-set');
       const indx = valj_efb.findIndex(x => x.id_ == id)
       const o = [{ id_: id, name: valj_efb[indx].name, amount: valj_efb[indx].amount, type: "yesNo", value: v, session: sessionPub_emsFormBuilder }];
       fun_sendBack_emsFormBuilder(o[0])
    }
  }
  function fun_get_rating_efb(v, no) {
    document.getElementById(`${v}-stared`).value = no;
    document.getElementById(`${v}-star${no}`).checked = true;
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      const indx = valj_efb.findIndex(x => x.id_ == v)
      const o = [{ id_: v, name: valj_efb[indx].name, amount: valj_efb[indx].amount, type: "rating", value: no, session: sessionPub_emsFormBuilder }];
      fun_sendBack_emsFormBuilder(o[0])
    }
  }
  /* map section */
let map;
let markers_maps_efb = [];
let mark_maps_efb = []
function initMap(disabled) {
  setTimeout(function () {
    const idx = valj_efb.findIndex(x => x.type == "maps")
    const lat = idx != -1 && valj_efb[idx].lat ? Number(valj_efb[idx].lat) : 49.24803870604257;
    const lon = idx != -1 && valj_efb[idx].lng ? Number(valj_efb[idx].lng) : -123.10512829684463;
    const mark = idx != -1 ? Number(valj_efb[idx].mark) : 1;
    const zoom = idx != -1 && valj_efb[idx].zoom && valj_efb[idx].zoom != "" ? Number(valj_efb[idx].zoom) : 10;
    const location = { lat: lat, lng: lon };
    if(typeof google == "undefined"){
      alert_message_efb(efb_var.text.error,googleMapsNOkEfb(),20,'danger');
      return 0;
    }
    if(typeof google!='undefined' && google.hasOwnProperty('maps')) map = new google.maps.Map(document.getElementById(`${valj_efb[idx].id_}-map`), {
      zoom: zoom,
      center: location,
      mapTypeId: "roadmap",
    });
    if (mark != 0 && mark != -1) {
      if (disabled)return;
      map.addListener("click", (event) => {
        const latlng = event.latLng.toJSON();
        if (mark_maps_efb.length < mark) {
          mark_maps_efb.push(latlng);
          addMarker(event.latLng);
        }
      });
      if (document.getElementById("delete-markers_maps_efb-efb")) document.getElementById("delete-markers_maps_efb-efb").addEventListener("click", deletemarkers_maps_efb_efb);
    } else if (mark == -1) {
      const lab_map_efb = "0ABCDEFGHIJKLMNOPQRSTUVWXYZ";
      let nn = 0;
      for (const mrk of marker_maps_efb) {
        nn += 1;
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
googleMapsNOkEfb =()=>{
  return `<div class="efb  boxHtml-efb sign-efb h-100" >
         <div class="efb  noCode-efb m-5 text-center text-light">
         <button type="button" class="efb  btn btn-edit btn-sm" data-bs-toggle="tooltip" title="${efb_var.text.howToAddGoogleMap}" onclick="open_whiteStudio_efb('mapErorr')">
          <i class="efb  bi-question-lg text-pinkEfb"></i></button> 
          <p class="efb fs-6">${efb_var.text.aPIkeyGoogleMapsError}</p> 
       </div></div>`;
 }
function addMarker(position) {
    const lab_map_efb = "0ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const idx = valj_efb.findIndex(x => x.type == "maps")
    const idxm = (mark_maps_efb.length)
    const lab = idx !== -1 && valj_efb[idx].mark < 2 ? '' : lab_map_efb[idxm % lab_map_efb.length];
    const marker = new google.maps.Marker({
      position,
      label: lab,
      map,
    });
    markers_maps_efb.push(marker);
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      const vmaps = JSON.stringify(mark_maps_efb);
      const o = [{ id_: valj_efb[idx].id_, name: valj_efb[idx].name, amount: valj_efb[idx].amount, type: "maps", value: mark_maps_efb, session: sessionPub_emsFormBuilder }];
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
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.type == "maps");
      if (indx != -1) { sendBack_emsFormBuilder_pub.splice(indx, 1); }
    }
  }
  fun_addProgessiveEl_efb=(id,state)=>{
  let newEl = document.createElement('div');
  const elId = `${id}-prG`;
  newEl.setAttribute("id",elId)
  newEl.className = "efb mt-3 mx-1"
    const elparent = state==0 ? "view-efb" : 'replay_section__emsFormBuilder';
    if(document.getElementById(`${id}-prG`)==null){
      document.getElementById(elparent).append(newEl);    
    }
  document.getElementById(elId).innerHTML = `<div class="efb d-flex justify-content-center"><div class="efb progress w-100" id="${id}-prA">
  <div id="${id}-prB" class="efb  text-light text-center btn-pinkEfb progress-bar-striped progress-bar-animated" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width:1%;">
        1%
        </div>
    </div></div>`;
  }
  fun_removeProgessiveEl_efb=(id)=>{
    document.getElementById(`${id}-prG`).remove();
  }
  reply_upload_efb =(msg_id)=>{
      let btnC = '<!--efb.app-->'
    if( pro_efb==true){
       if (page_state_efb=="panel") btnC =`<div class="efb col fs-5 h-d-efb pointer-efb text-darkb d-flex justify-content-end">
       <button type="button" class="efb btn mt-1 efb ${stock_state_efb ==true ? 'btn-outline-success' :"btn-outline-pink"}" onclick="closed_resp_emsFormBuilder(${msg_id})" data-state="${stock_state_efb ==true ? 1 :0}" id="respStateEfb" disabled>
           ${stock_state_efb ==true ?  efb_var.text.open : efb_var.text.close}
      </button></div>`
      if(setting_emsFormBuilder.hasOwnProperty('dsupfile')  && setting_emsFormBuilder.dsupfile==false && efb_var.hasOwnProperty('setting')==false) return '';
      return  `<div class="efb form-check">
      <div class="efb btn btn-light text-dark" id="attach_efb">
      <i class="bi bi-paperclip"></i><span id="name_attach_efb">${efb_var.text.file}</span>
      <input type="file" id="resp_file_efb_" name="file"  data-id="${msg_id}" >
      </div>
      ${btnC}
    </div>`
    }else{
      const $pr = `<a type="button" class="efb pro-v-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}" onclick="pro_show_efb(1)"><i class="efb  bi-gem text-light" ></i></a>`;
      if (page_state_efb=="panel") btnC =`<div class="efb col fs-5 h-d-efb pointer-efb text-darkb d-flex justify-content-end">
      <button type="button" class="efb btn mt-1 efb ${stock_state_efb ==true ? 'btn-outline-success' :"btn-outline-pink"} "id="respStateEfb">
      <i class="efb  bi-x-lg mx-1 efb mobile-text">${stock_state_efb ==true ?  efb_var.text.open : efb_var.text.close}</i>
      ${$pr}
     </button></div>`;
      return `<div class="efb form-check">
      <div class="efb btn btn-light text-dark" id="attach_efb" >
      <i class="bi bi-paperclip"></i><span id="name_attach_efb">${efb_var.text.file}</span>
        ${$pr}
      </div>
       ${btnC}
    </div>`
    }
}
function closed_resp_emsFormBuilder(msg_id){
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${stock_state_efb==false ? efb_var.text.clsdrspnsM : efb_var.text.clsdrspnsMo }</div></div>`
  show_modal_efb(body, efb_var.text.close, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    close_resp_efb(msg_id,stock_state_efb);
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })
  //myModal.show_efb();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
close_resp_efb=(id,s)=>{
  const ob = s==1 ? [{id_:'message', name:'message', type:'opened', amount:0, value: efb_var.text.clsdrspo, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}] : [{id_:'message', name:'message', type:'closed', amount:0, value: efb_var.text.clsdrspn, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}]
  sendBack_emsFormBuilder_pub= ob;
  fun_send_replayMessage_ajax_emsFormBuilder(sendBack_emsFormBuilder_pub, id)
}
function fun_point_rating(el) {
  const id = el.dataset.id;
  for (let l of document.querySelectorAll(`[data-id="${id}"]`)) {
      if (Number(l.dataset.point) <= Number(el.dataset.point)) {
          l.className = btnChangerEfb(l.className, pub_bg_button_color_efb);
      } else {
          l.className = btnChangerEfb(l.className, 'btn-secondary');
      }
  }
  document.getElementById(id + '-point-rating').value = el.dataset.point;
  if(state_efb=='run'){
      const v = valj_efb.find(x=>x.id_ ==id);
      if(v.type=="r_matrix"){
          const o = [{ id_ob: v.id_, name: v.value,id_:v.parent, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder }];
      fun_sendBack_emsFormBuilder(o[0]);
          const l = valj_efb.filter(obj => {
              return obj.parent == v.parent
            })
          const p = valj_efb.find(x=>x.id_ ==v.parent);
          if(p.required==true){
              setTimeout(() => {
                  const o_r = sendBack_emsFormBuilder_pub.filter(obj => {
                      return obj.parent == v.parent
                    })
                  if(l.length==o_r.length){
                  }
              }, 500);
          }
      }else{
      const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder }];
      fun_sendBack_emsFormBuilder(o[0]);
      }
  }
}
function fun_nps_rating(el) {
  const id = el.dataset.id;
  el.className = btnChangerEfb(el.className, pub_bg_button_color_efb);
  for (let l of document.querySelectorAll(`[data-id="${id}"]`)) {
      if (Number(l.dataset.point) != Number(el.dataset.point)) {
          l.className = btnChangerEfb(l.className, 'btn-outline-secondary');
      }
  }
  document.getElementById(id + '-nps-rating').value = el.dataset.point;
  if(state_efb=='run'){
      const v = valj_efb.find(x=>x.id_ ==id);
      const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder }];
      fun_sendBack_emsFormBuilder(o[0]);
  }
}
function fun_switch_efb(el){
  if(state_efb!='run'){ return}
  const v = valj_efb.find(x=>x.id_ ==el.dataset.vid);
  setTimeout(() => {
          let o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: "1", session: sessionPub_emsFormBuilder }];
          if(el.classList.contains('active')==false){
            o[0].value="0";
          }
          fun_sendBack_emsFormBuilder(o[0]);
  }, 100);
}
function create_intlTelInput_efb(rndm,iVJ,previewSate,corner){
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : '';
  load_intlTelInput_efb(rndm,iVJ)
  return `
  <input type="phone" class="efb  input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control ${valj_efb[iVJ].el_border_color}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_"  ${valj_efb[iVJ].value.length > 0 ? value = `"${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled}>
  <input type="phone" class="efb  input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control ${valj_efb[iVJ].el_border_color}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField d-none efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}-code" placeholder="verify"  ${valj_efb[iVJ].value.length > 0 ? value = `"${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled}>
  <button id="${rndm}-btn" type="submit" class="efb d-none">Submit</button>
 `;
}
load_intlTelInput_efb=(rndm,iVJ)=>{
 const onlyCountries= valj_efb[iVJ].hasOwnProperty("c_c") && valj_efb[iVJ].c_c.length>0 ? valj_efb[iVJ].c_c : "";
  setTimeout(()=>{
     const iti= window.intlTelInput(document.getElementById(rndm+"_"), {
    // allowDropdown: false,
    onlyCountries:onlyCountries,
    autoHideDialCode: true,
    placeholderNumberType:"MOBILE",
    utilsScript: efb_var.images.utilsJs,
  });
  document.getElementById(rndm+"_").addEventListener('blur', function() {
  const  errorMap = [efb_var.text.cpnnc, efb_var.text.icc,efb_var.text.cpnts,efb_var.text.cpntl, efb_var.text.cpnnc];
  document.getElementById(rndm+"_").classList.remove("border-danger");
  document.getElementById(rndm+"_").classList.remove("border-success");
  document.getElementById(rndm+"_-message").innerHTML="";
  document.getElementById(rndm+"_-message").classList.remove("d-block");
  document.getElementById(rndm+"_-message").classList.add("d-none");
    if (document.getElementById(rndm+"_").value.trim()) {
      if (iti.isValidNumber()) {
        document.getElementById(rndm+"_").classList.add("border-success");
          let value = `+${iti.s.dialCode}${document.getElementById(rndm+"_").value}`;
          fun_sendBack_emsFormBuilder({ id_: valj_efb[iVJ].id_, name: valj_efb[iVJ].name, id_ob: valj_efb[iVJ].id_, amount: valj_efb[iVJ].amount, type: valj_efb[iVJ].type, value: value, session: sessionPub_emsFormBuilder });
      } else {
        document.getElementById(rndm+"_").classList.add("border-danger");
        let errorCode = iti.getValidationError() 
        errorCode= errorMap[errorCode] ? errorMap[errorCode] :errorMap[0];
        document.getElementById(rndm+"_-message").classList.remove("d-none");
        document.getElementById(rndm+"_-message").classList.add("d-block");
        document.getElementById(rndm+"_-message").innerHTML=errorCode;
        let inx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == valj_efb[iVJ].id_);
        if (inx != -1) {
          sendBack_emsFormBuilder_pub.splice(inx, 1)
        }
      }
    }
  });
  },800)
}
fun_imgRadio_efb=(id ,link,row)=>{
  const u = (url)=>{
    url = url.replace(/(http:@efb@)+/g, 'http://');
    url = url.replace(/(https:@efb@)+/g, 'https://');
    url = url.replace(/(@efb@)+/g, '/');
    return url;
   }
  let value = row.hasOwnProperty('value')  ? row.value : efb_var.text.newOption ?? '';
  let sub_value = row.hasOwnProperty('sub_value') ? row.sub_value : efb_var.text.sampleDescription ?? '';
  link =link.includes('http')==false ?  efb_var.images.head : row.src;
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
add_new_imgRadio_efb=(idin, value, id_ob, tag, parentsID)=>{
 const idx = valj_efb.findIndex(x=>x.id_==id_ob)
 const temp = fun_imgRadio_efb(id_ob,"null",valj_efb[idx]);
  return`<div class="efb  form-check imgRadio col-md-3" data-parent="${parentsID}" data-id="${id_ob}"  id="${id_ob}-v">
  <input class="efb  form-check-input " type="radio" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
  ${temp}
  </div>`;
}
