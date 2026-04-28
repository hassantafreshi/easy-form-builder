async function fetch_json_from_url_efb(url) {
  let r = { s: false, r: "false" };
  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const data = await response.json();
    r.s = true;
    r.r = data;
  } catch (error) {
    r.r = error.message;
  }
  return r;
}

let marker_maps_efb;
maps_el_pro_efb =(previewSate, pos , rndm,iVJ)=>{
    return `
    <div class="efb  ${previewSate == true ? pos[3] : `col-md-12`} col-sm-12 "  id='${rndm}-f'>
      ${previewSate == true && valj_efb[iVJ].mark != 0 ? `<div id="floating-panel" class="efb "><input id="delete-markers_maps_efb-efb" class="efb  btn btn-danger" type="button" value="${efb_var.text.deletemarkers}" /></div>` : '<!--notPreview-->'}
        <div id="${rndm}-map" data-type="maps" class="efb  maps-efb emsFormBuilder_v ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} " data-id="${rndm}-el" data-name='maps' ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""}>
      </div>
    `
 }

 dadfile_el_pro_efb =(previewSate , rndm,iVJ)=>{
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square'
  let disabled =  valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==true? 'disabled' : ''
    return `<div class="efb  mb-3" id="uploadFilePreEfb">
                <label for="${rndm}_" class="efb  form-label">
                    <div class="efb  dadFile-efb py-0 ${disabled} ${valj_efb[iVJ].el_height} ${corner}   ${valj_efb[iVJ].el_border_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}_box"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""} ${disabled}>
                    ${ui_dadfile_efb(iVJ, previewSate)}
                    </div>
                </label>
            </div>`;
}
esign_el_pro_efb =(previewSate, pos , rndm,iVJ,desc)=>{
    const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square'
    let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
    return `<div class="efb  ${pos[3]} col-sm-12" id ="${rndm}-f">
    <canvas class="efb  sign-efb bg-white ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-code="${rndm}"  data-id="${rndm}-el" id="${rndm}_"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""} >
        ${efb_var.text.updateUrbrowser}
    </canvas>
   ${previewSate == true ? `<input type="hidden" data-type="esign" data-vid='${rndm}' class="efb  emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">` : ``}
    <div class="efb  mx-1">${desc}</div>
    <div class="efb  mb-3"><button type="button" class="efb  btn ${corner} ${valj_efb[iVJ].button_color} efb-btn-lg mt-1 fs-6 ${disabled}" id="${rndm}_b" onclick="fun_clear_esign_efb('${rndm}')">  <i class="efb  ${valj_efb[iVJ].icon} mx-2 ${valj_efb[iVJ].icon_color != "default" ? valj_efb[iVJ].icon_color : ''} " id="${rndm}_icon"></i><span id="${rndm}_button_single_text" class="efb  ${valj_efb[iVJ].icon_color} efb " ${disabled}>${valj_efb[iVJ].button_single_text}</span></button></div>
      `;
}
rating_el_pro_efb =(previewSate,pos, rndm,iVJ)=>{
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
    return ` <div class="efb  ${pos[3]} col-sm-12" id ="${rndm}-f">
      <div class="efb  star-efb d-flex justify-content-center ${disabled} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""}>
                        <input type="radio" id="${rndm}-star5" data-vid='${rndm}' data-type="rating" class="efb "   data-star='star'  name="${rndm}-star-efb" value="5" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star5" for="${rndm}-star5"  ${previewSate == true && disabled==false ? `onclick="fun_get_rating_efb('${rndm}',5)"` : ''} title="5stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">5 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star4" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" value="4" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star4"  for="${rndm}-star4" ${previewSate == true  && disabled==false ? `onclick="fun_get_rating_efb('${rndm}',4)"` : ''} title="4stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">4 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star3" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" data-name="star" value="3"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star3" for="${rndm}-star3"  ${previewSate == true  && disabled==false ? `onclick="fun_get_rating_efb('${rndm}',3)"` : ''} title="3stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">3 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star2" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' data-name="star" name="${rndm}-star-efb" value="2"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star2" for="${rndm}-star2" ${previewSate == true  && disabled==false ? `onclick="fun_get_rating_efb('${rndm}',2)"` : ''} title="2stars" class="efb  ${valj_efb[iVJ].el_height} star ${disabled}">2 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star1" data-vid='${rndm}' data-type="rating" class="efb " data-star='star' data-name="star" name="${rndm}-star-efb" value="1"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}  ${disabled}>
                        <label id="${rndm}_star1" for="${rndm}-star1" ${previewSate == true && disabled==false ? `onclick="fun_get_rating_efb('${rndm}',1)"` : ''} title="1star" class="efb   ${valj_efb[iVJ].el_height} star ${disabled}">1 ${efb_var.text.star}</label>
      </div>
      <input type="hidden" data-vid="${rndm}" data-type="rating" class="efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-stared" >`
}
pointer10_el_pro_efb = (previewSate, classes,iVJ)=>{
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  previewSate = previewSate != true ? 'disabled' : '';
  return ` <div class="efb  NPS flex-row  justify-content-right efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""} >
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
  return `   <div class="efb d-flex justify-content-right efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""}>
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
          let value = i.value;
          if (i.hasOwnProperty('stylish')) {
            if (i.stylish == '2') {
              value = `<span class="efb">${i.l}</span>`
            }else if(i.stylish == '3'){
              value = `<span class="efb">${i.n}</span>`
            }
          }
          optn += `<option value="${value}" id="${i.id_}" data-iso="${i.id_op}" data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb"  ${valj_efb[indx_parent].value==i.id_ ||( i.hasOwnProperty('id_old') && valj_efb[indx_parent].value==i.id_old) ? "selected" :''}>${value}</option>`
        }
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
          counstries_list_efb.sort((a, b) => a.n.localeCompare(b.n));
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
statePrevion_el_pro_efb = (rndm,rndm_1,temp,op_4,editState,autofilled)=>{
    let optn ='<!--states-->'
    const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
    const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
    const iso_con = valj_efb[indx_parent].country;
    check_autofilled =(id , i ,iso_con)=>{
      let value = i.value;
      let s2 = i.s2;
      let row= sendBack_emsFormBuilder_pub.findIndex(r=>r.id_==rndm);
      const row_value = sendBack_emsFormBuilder_pub[row].value.replace(/\s/g, '');
      value = value.replace(/\s/g, '');
      let re =false;
      if(row_value==value){
        re=true;
        sendBack_emsFormBuilder_pub[row].hasOwnProperty('statePrev_') ? sendBack_emsFormBuilder_pub[row].statePrev_=s2 : Object.assign(sendBack_emsFormBuilder_pub[row], {statePrev_: s2})
        sendBack_emsFormBuilder_pub[row].hasOwnProperty('cont_') ? sendBack_emsFormBuilder_pub[row].cont_=iso_con : Object.assign(sendBack_emsFormBuilder_pub[row], {cont_: iso_con})
        if(sendBack_emsFormBuilder_pub[row].type=='cityList'){
          sendBack_emsFormBuilder_pub[row].hasOwnProperty('city_') ? sendBack_emsFormBuilder_pub[row].city_=s2 : Object.assign(sendBack_emsFormBuilder_pub[row], {city_: s2})
        }
      }
      return re
    }
    if (editState != false) {
        for (const i of optns_obj) {
          let value = i.value;
          if (i.hasOwnProperty('stylish')) {
            if (i.stylish == '2') {
              value = `<span class="efb">${i.l}</span>`
            }else if(i.stylish == '3'){
              value = `<span class="efb">${i.n}</span>`
            }
          }
          let autofill_check='' ;
          if(autofilled==true){
            autofill_check=check_autofilled(i.id_,i,iso_con);
           }
          const selected = valj_efb[indx_parent].value==i.id_ || ( i.hasOwnProperty('id_old') && valj_efb[indx_parent].value==i.id_old) || (autofill_check ) ? "selected" :''
          optn += `<option id="${i.id_}" value="${value}" data-iso="${i.s2}" data-isoc='${iso_con}'  data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb" ${selected}>${value}</option>`
        }
      } else {
          state_local=optns_obj;
          state_local.sort();
          let optn = '<!-- list of states -->'
          let count =0;
          for (let i = 0; i < state_local.length; i++) {
            count+=1;
            let id = rndm_1 +count
            optn += `<option id="${id}" value="${state_local[i]} 1" data-iso="${i.s2}"  data-isoc='${iso_con}'  data-vid='${rndm}' data-id="${state_local[i]}" data-op="${state_local[i]}" class="efb text-dark efb" ${valj_efb[indx_parent].value==i.id_ || valj_efb[indx_parent].value==i.id_old ? "selected" :''}>${state_local[i]}</option>`
            if(temp!=true) optionElpush_efb(rndm, state_local[i], id, state_local[i] ,'select');
          }
      }
      return optn;
}
cityList_el_pro_efb = (rndm,rndm_1,temp,op_4,editState)=>{
    let optn ='<!--states-->'
    const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        const stylish = valj_efb[indx_parent].hasOwnProperty('stylish') ? valj_efb[indx_parent].stylish : '1';
    if (editState != false) {
        for (const i of optns_obj) {
          let value = i.value;
      if (i.hasOwnProperty('stylish')) {
            if (stylish == '2') {
              value = `<span class="efb">${i.l}</span>`
            }else if(stylish == '3'){
              value = `<span class="efb">${i.n}</span>`
            }
          }
          optn += `<option value="${value}" data-iso="${i.id_}" id="${i.id_}" data-id="${i.id_}"  data-iso='${valj_efb[indx_parent].country}' data-statepov='${valj_efb[indx_parent].statePov}' data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb" ${valj_efb[indx_parent].value==i.id_ || ( i.hasOwnProperty('id_old') && valj_efb[indx_parent].value==i.id_old) ? "selected" :''}>${value}</option>`
        }
      } else {
          state_local=optns_obj;
          state_local.sort();
          let optn = '<!-- list of states -->'
          let count =0;
          for (let i = 0; i < state_local.length; i++) {
            count+=1;
            let id = rndm_1 +count
            optn += `<option value="${state_local[i]} 1" data-iso="${i.id_}" id="${id}" data-iso='${valj_efb[indx_parent].country}' data-statepov='${valj_efb[indx_parent].statePov}' data-vid='${rndm}' data-id="${state_local[i]}" data-op="${state_local[i]}" class="efb text-dark efb" ${valj_efb[indx_parent].value==i.id_ || valj_efb[indx_parent].value==i.id_old ? "selected" :''}>${state_local[i]}</option>`
            if(temp!=true) optionElpush_efb(rndm, state_local[i], id, state_local[i] ,'select');
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
    return `<div class="efb ${pos[3]} col-sm-12 efb  ${disabled} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""}>
    <div class="efb  btn-group  btn-group-toggle w-100  col-md-12 col-sm-12  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" data-toggle="buttons" data-id="${rndm}-id" id="${rndm}_yn">
    <label for="${rndm}_1" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_1_text}" onclick="yesNoGetEFB('${valj_efb[iVJ].button_1_text}', '${rndm}' ,'${rndm}_b_1')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${corner} yesno-efb left-efb  ${disabled} ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_1">
      <input type="radio" name="${rndm}" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_1" value="${valj_efb[iVJ].button_1_text}"><span id="${rndm}_1_lab">${valj_efb[iVJ].button_1_text}</span></label>
    <span class="efb border-right border border-light efb"></span>
    <label for="${rndm}_2" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_2_text}" onclick="yesNoGetEFB('${valj_efb[iVJ].button_2_text}' ,'${rndm}','${rndm}_b_2')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${corner} yesno-efb right-efb  ${disabled} ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_2">
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
function ui_dadfile_efb(indx, previewSate,form_id) {
  let valj_efb_
  if (form_id){
    valj_efb_ = get_structure_by_form_id_efb(form_id);
  }else{
   valj_efb_ = valj_efb;
  }
  let n = valj_efb_[indx].file ?? 'Zip';
  n = efb_var.text[n];
  if(valj_efb_[indx].file=='customize'){
    n = valj_efb_[indx].file_ctype;
  }
  let types = ""
  let disabled =  valj_efb_[indx].hasOwnProperty('disabled') &&  valj_efb_[indx].disabled==true? 'disabled' : ''
  filetype_efb={'image':'image/png, image/jpeg, image/jpg, image/gif, image/heic',
  'media':'audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg , video/mpg, audio/mpg, video/mov, video/quicktime',
  'document':'.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf,  text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text',
  'zip':'.zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar',
  'allformat':'image/png, image/jpeg, image/jpg, image/gif, image/heic, audio/mpeg, audio/wav, audio/ogg, video/mp4, video/webm, video/x-matroska, video/avi, video/mpeg, video/mpg, audio/mpg, video/mov, video/quicktime, .xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp,application/pdf, text/plain, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.ms-powerpoint.presentation.macroEnabled.12, application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/vnd.oasis.opendocument.text, .zip, application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip, rar, application/x-rar-compressed, application/x-rar, application/rar, application/x-compressed, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .rar, .zip, .7z, .tar, .gz, .gzip, .tgz, .tar.gz, .tar.gzip, .tar.z, .tar.Z, .tar.bz2, .tar.bz, .tar.bzip2, .tar.bzip, .tbz2, .tbz, .bz2, .bz, .bzip2, .bzip, .tz2, .tz, .z, .war, .jar, .ear, .sar, .heic, image/heic, video/mov, .mov, video/quicktime, video/quicktime',
  'customize':`${valj_efb_[indx].file_ctype}`
    }
  return `<div class="efb icon efb"><i class="efb  fs-3 ${valj_efb_[indx].icon} ${valj_efb_[indx].icon_color}" id="${valj_efb_[indx].id_}_icon"></i></div>
  <h6 id="${valj_efb_[indx].id_}_txt" class="efb text-center m-1 fs-6">${efb_var.text.dragAndDropA} ${n} </h6> <span class="efb fs-7">${efb_var.text.or}</span>
  <div class="efb  btn ${valj_efb_[indx].button_color} efb-btn-lg fs-6" id="${valj_efb_[indx].id_}_b" ${disabled}>
      <i class="efb  bi-upload mx-2 fs-6"></i>${efb_var.text.browseFile}
  </div>
 <input type="file" hidden="" accept="${filetype_efb[valj_efb_[indx].value]}" data-type="dadfile" data-vid='${valj_efb_[indx].id_}' data-ID='${valj_efb_[indx].id_}' class="efb  emsFormBuilder_v   ${valj_efb_[indx].required == 1 || valj_efb_[indx].required == true ? 'required' : ''}" id="${valj_efb_[indx].id_}_" data-id="${valj_efb_[indx].id_}-el" ${previewSate != true ? 'disabled' : ''} ${disabled}>`
}
function viewfileEfb(id, indx ,filed,form_id) {
  const valj_efb = get_structure_by_form_id_efb(form_id);
    if(filed==undefined) {
      document.getElementById(`${valj_efb[indx].id_}_-message`).innerHTML='';
      document.getElementById(`${valj_efb[indx].id_}_-message`).style.display='none';
      return;}
    const filename =  filed.name ;
    let fileType =valj_efb[indx].file=='customize' ? filename.slice(filename.lastIndexOf('.') + 1) : filed.type;
    let icon = ``;
    const svg_file = `<svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" fill="currentColor" class="bi bi-file-earmark" viewBox="0 0 16 16">
    <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/>
    <text x="50%" y="67%" dominant-baseline="middle" text-anchor="middle" fill="black" font-size="4">${filename.slice(filename.lastIndexOf('.') + 1)}</text>
  </svg>`
    let box_v = `<div class="efb ">
    <button type="button" class="efb btn btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onclick="removeFileEfb('${id}',${indx} ,${form_id})"
         aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.removeTheFile}"></button>
         <div class="efb card p-2">
          <i class="efb  ico-file ${valj_efb[indx].icon_color} text-center fs-2">${svg_file}</i>
          <span class="efb  text-muted">${filed.name}</span>
          </div>
    </div>`;
    if (validExtensions_efb_fun(valj_efb[indx].file, fileType ,indx)) {
      fun_addProgessiveEl_efb(id ,0);
      let fileReader = new FileReader();
      fileReader.onload = () => {
        let fileURL = fileReader.result;
        const box = document.getElementById(`${id}_box`)
        if (valj_efb[indx].file == "image") {
          box.innerHTML = `<div class="efb ">
              <button type="button" class="efb btn btn-delete btn-sm bi-x-lg efb" id="rmvFileEfb" onclick="removeFileEfb('${id}',${indx},${form_id})"
                   aria-label="Close" data-bs-toggle="tooltip" data-bs-placement="top" title=${efb_var.text.removeTheFile}"></button>
              <img src="${fileURL}" alt="image">
              </div>`;
        } else {
          box.innerHTML = box_v;
        }
      }
      fileReader.readAsDataURL(filed);
      document.getElementById(`${id}_-message`).innerHTML = "";
      document.getElementById(`${id}_-message`).style.display='none'

    } else {
      let t_m = valj_efb[indx].file!='customize'? valj_efb[indx].file : valj_efb[indx].file_ctype;
      t_m = t_m.replaceAll(',',` ${efb_var.text.or} `);
      const m  = efb_var.text.pleaseUploadA.replace('NN', t_m);
      document.getElementById(`${id}_-message`).innerHTML = m;
      document.getElementById(`${id}_-message`).style.display='block';
      alert_message_efb('', m, 4, 'danger')
      document.getElementById(`${id}_box`).classList.remove("active");
      fileEfb = [];
    }
  }
function viewfileReplyEfb(id, indx) {
    let fileType = fileEfb.type;
    const filename = fileEfb.name;
    if (validExtensions_efb_fun('allformat',fileType,indx)) {
      fun_addProgessiveEl_efb('resp_file_efb' ,1);
      let fileReader = new FileReader();
      let fileURL = ''
      fileReader.onload = () => {
         fileURL = fileReader.result;
      }
      fileReader.readAsDataURL(fileEfb);
      files_emsFormBuilder.push({ id_: 'resp_file_efb', value: "@file@", state: 0, url: fileURL, type: "file", name: 'file', session: sessionPub_emsFormBuilder , amount:0 });
      fun_upload_file_api_emsFormBuilder('resp_file_efb', 'allformat' ,'resp',fileEfb);
      document.getElementById('name_attach_efb').innerHTML = fileEfb.name.length > 10 ? `${fileEfb.name.slice(0,7)}..` :fileEfb.name;
    } else {
      const m  = efb_var.text.pleaseUploadA.replace('NN', `${efb_var.text['media']} , ${efb_var.text['document']} ${efb_var.text['or']} ${efb_var.text['zip']}`);
      alert_message_efb('', m, 4, 'danger')
      fileEfb = [];
    }
  }
function removeFileEfb(id, indx,form_id) {
    fileEfb = "";
    document.getElementById(`${id}_box`).innerHTML = ui_dadfile_efb(indx,true,form_id)
    setTimeout(() => {
      create_dadfile_efb(id, indx,form_id);
      document.getElementById(`${id}_`).addEventListener('change', () => {
        valid_file_emsFormBuilder(id ,'msg' ,'',form_id);
      })
    }, 500)
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      let inx = get_row_sendback_by_id_efb(id);
      if (inx != -1) {
        sendBack_emsFormBuilder_pub.splice(inx, 1)
        inx = files_emsFormBuilder.findIndex(x => x.id_ == id)
        if (inx != -1) {
          files_emsFormBuilder[inx].url = "";
          files_emsFormBuilder[inx].state = 0;
        }
      }
      else {
        inx = files_emsFormBuilder.findIndex(x => x.id_ == id)
        if (inx != -1) {
          files_emsFormBuilder[inx].url = "";
          files_emsFormBuilder[inx].state = 0;
          setTimeout(() => {
            inx = get_row_sendback_by_id_efb(id);
            if (inx != -1) { sendBack_emsFormBuilder_pub.splice(inx, 1) }
          }, 100);
        }
      }
    }
    let fileIndx = files_emsFormBuilder.findIndex(x => x.id_ == id);
    if (fileIndx === -1) {
      const valj_efb_rm = get_structure_by_form_id_efb(form_id);
      const ob = valj_efb_rm ? valj_efb_rm.find(x => x.id_ === id) : null;
      if (ob) {
        files_emsFormBuilder.push({ id_: id, value: "@file@", state: 0, url: "", type: "file", name: ob.name, session: sessionPub_emsFormBuilder, form_id: form_id });
      }
    }
    fun_removeProgessiveEl_efb(id);
  }
function gm_authFailure() {
    const body = `<p class="efb fs-6 efb">${efb_var.text.aPIkeyGoogleMapsFeild} <a href="https://developers.google.com/maps/documentation/javascript/error-messages" target="blank">${efb_var.text.clickHere}</a> </p>`
    alert_message_efb(efb_var.text.error, body, 15, 'danger')
  }
set_dadfile_fun_efb = (id, indx,form_id=0) => {
    setTimeout(() => {
      create_dadfile_efb(id, indx,form_id)
      document.getElementById(`${id}_`).addEventListener('change', () => {
        valid_file_emsFormBuilder(id ,'msg' ,'',form_id);
      })
     }, 100)
  }
  create_dadfile_efb = (id, indx,form_id) => {
    const valj_efb =get_structure_by_form_id_efb(form_id);
    let dropAreaEfb = document.getElementById(`${id}_box`);
    let dragTextEfb = dropAreaEfb.querySelector("h6");
    let  dragbtntEfb = document.getElementById(`${id}_b`);
    let dragInptEfb = dropAreaEfb.querySelector("input");
    dropAreaEfb.classList.remove("active");
    dragInptEfb.disabled = false;
    dragbtntEfb.onclick = () => {
    }
    dragInptEfb.addEventListener("change", function () {
      fileEfb = this.files[0];
      dropAreaEfb.classList.add("active");
      viewfileEfb(id, indx ,fileEfb,form_id);
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
      viewfileEfb(id, indx ,fileEfb,form_id);
      valid_file_emsFormBuilder(id ,'msg',fileEfb,form_id)
    });
  }
    reply_attach_efb = (id, indx) => {
        const v= reply_upload_efb(id);
        const lenV=(v.length/20)+10;
        let l = document.getElementById('replay_section__emsFormBuilder');
        if(l)l.insertAdjacentHTML('beforeend', v);
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
  function renderCanvas_efb() {
    return;
  }
function fun_clear_esign_efb(id) {
    const canvas = document.getElementById(`${id}_`);
    const sigData = document.getElementById(`${id}-sig-data`);
    if (!canvas || !sigData) return;
    sigData.value = "Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=="
    const c2d = canvas.getContext("2d");
    c2d.clearRect(0, 0, canvas.width, canvas.height);
    var w = canvas.width;
    canvas.width = 1;
    canvas.width = w;
    c2d.lineWidth = 5;
    c2d.strokeStyle = "#000000";
    c2d.save();
    const o = [{ id_: id }];
    const indx = get_row_sendback_by_id_efb(id);
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
      y: mouseEvent.clientY - rct.top ,
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
  async function fun_get_rating_efb(v, no,form_id=0) {
    document.getElementById(`${v}-stared`).value = no;
    document.getElementById(`${v}-star${no}`).checked = true;
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
       if(form_ID_emsFormBuilder!=parseInt(form_id) || valj_efb.length<1)valj_efb = await fun_valj_efb_run(form_id);
      const indx = valj_efb.findIndex(x => x.id_ == v);
      const o = [{ id_: v, name: valj_efb[indx].name, amount: valj_efb[indx].amount, type: "rating", value: no, session: sessionPub_emsFormBuilder,form_id:form_id }];
      fun_sendBack_emsFormBuilder(o[0]);
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
       <button type="button" class="efb btn mt-1 efb ${stock_state_efb ==true ? 'btn-outline-success' :"btn-outline-pink"} fs-6" onclick="closed_resp_emsFormBuilder(${msg_id})" data-state="${stock_state_efb ==true ? 1 :0}" id="respStateEfb" disabled>
           ${stock_state_efb ==true ?  efb_var.text.open : efb_var.text.close}
      </button></div>`
      const attach_efb = document.getElementById('attach_efb');
      if(attach_efb) return '';
      if(setting_emsFormBuilder.hasOwnProperty('dsupfile')  && setting_emsFormBuilder.dsupfile==false && efb_var.hasOwnProperty('setting')==false) return '';
      return  `<div class="efb form-check">
      <div class="efb btn btn-light text-dark fs-6 cursor-hand" id="attach_efb">
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
      <div class="efb btn btn-light text-dark fs-6" id="attach_efb" >
      <i class="bi bi-paperclip"></i><span id="name_attach_efb">${efb_var.text.file}</span>
        ${$pr}
      </div>
       ${btnC}
    </div>`
    }
}
function closed_resp_emsFormBuilder(msg_id){
  const msg = stock_state_efb==false ? efb_var.text.clsdrspnsM : efb_var.text.clsdrspnsMo;
  const body = efb_build_confirm_body('warning', 'bi-exclamation-triangle', efb_var.text.close, msg, '');
  show_modal_efb(body, efb_var.text.close, 'efb bi-exclamation-triangle mx-2', 'deleteBox')
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    close_resp_efb(msg_id,stock_state_efb);
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
close_resp_efb=(id,s)=>{
  const ob = s==1 ? [{id_:'message', name:'message', type:'opened', amount:0, value: efb_var.text.clsdrspo, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}] : [{id_:'message', name:'message', type:'closed', amount:0, value: efb_var.text.clsdrspn, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}]
  sendBack_emsFormBuilder_pub= ob;
  fun_send_replayMessage_ajax_emsFormBuilder(sendBack_emsFormBuilder_pub, id)
}
async function fun_point_rating(el) {
  const id = el.dataset.id;
  for (let l of document.querySelectorAll(`[data-id="${id}"]`)) {
      if (Number(l.dataset.point) <= Number(el.dataset.point)) {
          l.className = btnChangerEfb(l.className, pub_bg_button_color_efb);
      } else {
          l.className = btnChangerEfb(l.className, 'btn-secondary');
      }
  }
  document.getElementById(id + '-point-rating').value = el.dataset.point;
  let form_id =0;
  if(state_efb=='run'){
    if(valj_efb_new){
        form_id = el.dataset.formid;
         if(form_ID_emsFormBuilder!=parseInt(form_id) || valj_efb.length<1) valj_efb= await fun_valj_efb_run(form_id)
    }
      const v = valj_efb.find(x=>x.id_ ==id);
      if(v.type=="r_matrix"){
          const o = [{ id_ob: v.id_, name: v.value,id_:v.parent, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder, form_id: form_id }];
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
      const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder, form_id: form_id }];
      fun_sendBack_emsFormBuilder(o[0]);
      }
  }
}
async function fun_nps_rating(el){
  const id = el.dataset.id;
  el.className = btnChangerEfb(el.className, pub_bg_button_color_efb);
  for (let l of document.querySelectorAll(`[data-id="${id}"]`)) {
      if (Number(l.dataset.point) != Number(el.dataset.point)) {
          l.className = btnChangerEfb(l.className, 'btn-outline-secondary');
      }
  }
  document.getElementById(id + '-nps-rating').value = el.dataset.point;
  if(state_efb=='run'){
      const form_id = el.dataset.formid;
      if(form_ID_emsFormBuilder!=parseInt(form_id) || valj_efb.length<1)valj_efb= await fun_valj_efb_run(form_id)
      const v = valj_efb.find(x=>x.id_ ==id);
      const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: el.dataset.point, session: sessionPub_emsFormBuilder, form_id: form_id }];
      fun_sendBack_emsFormBuilder(o[0]);
  }
}
function fun_switch_efb(el){
  if(state_efb!='run'){ return}
  const v = valj_efb.find(x=>x.id_ ==el.dataset.vid);
  const form_id = el.dataset.formid || 0;
  setTimeout(() => {
          let o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: "1", session: sessionPub_emsFormBuilder, form_id: form_id }];
          if(el.classList.contains('active')==false){
            o[0].value="0";
          }
          fun_sendBack_emsFormBuilder(o[0]);
  }, 100);
}
function create_intlTelInput_efb(rndm,iVJ,previewSate,corner){
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : '';
  load_intlTelInput_efb(rndm,iVJ)
  const formId = valj_efb[iVJ].form_id || 0;
  return `
  <input type="phone" class="efb  input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control ${valj_efb[iVJ].el_border_color}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-formid="${formId}" data-vid='${rndm}' id="${rndm}_" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}"  ${valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : ""}  ${valj_efb[iVJ].value.length > 0 ? value = `"${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled}>
  <input type="phone" class="efb  input-efb intlPhone px-2 mb-0 emsFormBuilder_v form-control ${valj_efb[iVJ].el_border_color}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField d-none efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-formid="${formId}" data-vid='${rndm}' id="${rndm}-code" placeholder="verify"  ${valj_efb[iVJ].value.length > 0 ? value = `"${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled}>
  <button id="${rndm}-btn" type="submit" class="efb d-none">Submit</button>
 `;
}

load_intlTelInput_efb = (rndm, iVJ) => {
  setTimeout(() => {
    const onlyCountries = valj_efb[iVJ].hasOwnProperty("c_c") && valj_efb[iVJ].c_c.length > 0 ? valj_efb[iVJ].c_c : "";
    const forceIntlContainerRtlEfb = (inputEl) => {
      if (!inputEl) return;

      const hasRtlScope = inputEl.closest('[dir="rtl"]') || document.documentElement.getAttribute('dir') === 'rtl';
      if (!hasRtlScope) return;

      const itiContainer = inputEl.closest('.iti');
      if (!itiContainer) return;

      const countryContainer = itiContainer.querySelector('.iti__country-container');
      if (countryContainer) {
        countryContainer.style.setProperty('left', 'auto', 'important');
        countryContainer.style.setProperty('right', '0', 'important');
        countryContainer.style.setProperty('inset-inline-start', 'auto', 'important');
        countryContainer.style.setProperty('inset-inline-end', '0', 'important');
      }

      inputEl.setAttribute('dir', 'ltr');
      inputEl.style.setProperty('direction', 'ltr', 'important');
      inputEl.style.setProperty('text-align', 'left', 'important');
      inputEl.style.setProperty('unicode-bidi', 'plaintext', 'important');
    };
    let iti;
    const el_mobile = document.getElementById(rndm + "_");
    if (!el_mobile) {
      return;
    }
    if(efb_var.length<1) efb_var = ajax_object_efm
    const ulitisJs = efb_var.images.hasOwnProperty('utilsJs') ? efb_var.images.utilsJs  : el_mobile.dataset.utilsjs;

      iti = window.intlTelInput(el_mobile, {
          onlyCountries: onlyCountries,
          nationalMode: true,
          autoPlaceholder: "polite",
          placeholderNumberType: "MOBILE",
          loadUtils: () => import(ulitisJs),
      });
        forceIntlContainerRtlEfb(el_mobile);

      el_mobile.addEventListener('blur', function () {
          const errorMap = [
              efb_var.text.cpnnc,
              efb_var.text.icc,
              efb_var.text.cpnts,
              efb_var.text.cpntl,
              efb_var.text.cpnnc
          ];

          el_mobile.classList.remove("border-danger", "border-success");
          const messageEl = document.getElementById(rndm + "_-message");
          messageEl.innerHTML = "";
          messageEl.classList.remove("d-block");
          messageEl.classList.add("d-none");

          if (el_mobile.value.trim()) {
              const form_id = el_mobile.dataset.formid ?? 0;
              if (iti.isValidNumber()) {
                  el_mobile.classList.add("border-success");

                  const countryData = iti.getSelectedCountryData();
                  const countryCode = countryData.dialCode;
                  const iso2 = countryData.iso2;
                  const countryName = countryData.name;

                  const value = iti.getNumber();
                  const nationalNumber = iti.getNumber(window.intlTelInput?.utils?.numberFormat?.NATIONAL || 1);

                  iVJ = valj_efb.findIndex(x => x.id_ == rndm);
                  fun_sendBack_emsFormBuilder({
                      id_: valj_efb[iVJ].id_,
                      name: valj_efb[iVJ].name,
                      id_ob: valj_efb[iVJ].id_,
                      amount: valj_efb[iVJ].amount,
                      type: valj_efb[iVJ].type,
                      value: value,
                      session: sessionPub_emsFormBuilder,
                      form_id: form_id
                  });
              } else {
                  el_mobile.classList.add("border-danger");

                  let errorCode = iti.getValidationError();
                  errorCode = errorMap[errorCode] ? errorMap[errorCode] : errorMap[0];

                  messageEl.innerHTML = errorCode;
                  messageEl.classList.remove("d-none");
                  messageEl.classList.add("d-block");

                  let inx = get_row_sendback_by_id_efb(rndm);
                  if (inx !== -1) {
                      sendBack_emsFormBuilder_pub.splice(inx, 1);
                  }
              }
          }
      });
  }, 800);
};

add_new_imgRadio_efb=(idin, value, id_ob, tag, parentsID)=>{
 const idx = valj_efb.findIndex(x=>x.id_==id_ob)
 const temp = fun_imgRadio_efb(id_ob,"null",valj_efb[idx]);
  return`<div class="efb  form-check imgRadio col-md-4 mx-0 px-2 efb2" data-parent="${parentsID}" data-id="${id_ob}"  id="${id_ob}-v">
  <input class="efb  form-check-input " type="radio" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
  ${temp}
  </div>`;
}
function terms_el_pro_efb(previewSate, rndm,iVJ){
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : '';
  return `<div class="efb  form-check">
  <textarea class="efb  form-control ${valj_efb[iVJ].el_border_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" ${previewSate != true ? 'readonly' : ''} ${disabled}>${valj_efb[iVJ].value_text}</textarea>
  <input class="efb  form-check-input ${valj_efb[iVJ].el_border_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" type="checkbox" ${valj_efb[iVJ].value.length > 0 ? value = `"${valj_efb[iVJ].value}"` : ''} ${previewSate != true ? 'readonly' : ''} ${disabled}>
  <label class="efb  form-check-label" for="${rndm}_">${valj_efb[iVJ].value}</label>
  </div>`;
}
const fun_check_link_city_efb = async (iso2_country, iso2_statePove, indx, form_id) => {
  if (form_ID_emsFormBuilder !== parseInt(form_id)) {
    valj_efb = await fun_valj_efb_run(form_id);
  }

  if (!Array.isArray(valj_efb) || indx < 0 || indx >= valj_efb.length) return;

  let indx_state = -1;
  let id_city = '';

  const base = valj_efb[indx];
  const amount = Number(base.amount) || 0;

  let i = indx;
  const len = valj_efb.length;
  while (i < len) {
    const item = valj_efb[i];
    if (Number(item.amount) > amount) {
      const t = item.type;
      if (t === 'city' || t === 'cityList') {
        indx_state = i;
        id_city = item.id_;
        break;
      }
    }
    i++;
  }

  if (indx_state === -1) return;
  const row = valj_efb[indx_state];
  const cc = String(iso2_country   || '').toLowerCase();
  const ss = String(iso2_statePove || '').toLowerCase();
  const rc = String(row.country    || '').toLowerCase();
  const rs = String(row.statePov   || '').toLowerCase();
  if (rc === cc && rs === ss) return;
  if (Array.isArray(sendBack_emsFormBuilder_pub) && id_city) {
    sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter(obj => obj.id_ !== id_city);
  }
  row.country = iso2_country;
  row.statePov = iso2_statePove;
  for (let j = valj_efb.length - 1; j >= 0; j--) {
    const it = valj_efb[j];
    if (it && it.parent === row.id_) {
      valj_efb.splice(j, 1);
    }
  }

  callFetchCitiesEfb(`${row.id_}_options`, iso2_country, iso2_statePove, indx_state, 'pubSelect');
};

function clean_options_select_efb(id){
  let el = document.getElementById(id+'_options');
  if(el){
    el.innerHTML = `<option value="">${efb_var.text.nothingSelected}</option>`;
    for(let i =0; i < valj_efb.length; i++){
      if(valj_efb[i].hasOwnProperty('parent') && valj_efb[i].parent==id){
        valj_efb.splice(i,1);
        i--;
      }
    }
  }
}
async function callFetchCitiesEfb(idField,iso2_country,iso2_statePove, indx_state,fieldType,autofilled=false) {
  let state_el= document.getElementById(idField)
  if(state_el!=null){
  state_el.innerHTML = "";
  state_el.innerHTML = `<option value="">${efb_var.text.loading}</option>`;
  state_el.classList.add('is-loading');
  state_el.disabled=true;
  }

  if(autofilled){
    const id_valj = valj_efb[indx_state].id_;
    const s_index =  sendBack_emsFormBuilder_pub.findIndex(x=>x.id_==id_valj);
    if(s_index!=-1){
      const row_sb = sendBack_emsFormBuilder_pub[s_index-1];
        if(s_index-1>1 && row_sb.type==='stateProvince'){
        iso2_country = row_sb.cont_;
        iso2_statePove = row_sb.statePrev_
        }
    }

  }
  let url = efb_var.zone_area ?? ajax_object_efm.zone_area
  url = url+`json/cites/${iso2_country.toLowerCase()}/${iso2_statePove.toLowerCase()}.json`;

  const state_check = fun_state_check_addon_AdnOF_setting_efb();
  if(state_check){
     url =efb_var.images.plugin_url+ `/vendor/offline/json/cites/${iso2_country.toLowerCase()}/${iso2_statePove.toLowerCase()}.json`;
     url =url.replaceAll('//vendor','/vendor');
    }
   clean_options_select_efb(valj_efb[indx_state].id_);
  let result = await  fetch_json_from_url_efb(url)
  if(result.s==false){
    alert_message_efb('',efb_var.text.offlineSend,5,'danger')
    return;
  }
  let opt = `<option selected disabled>${efb_var.text.nothingSelected}</option>`;
  for(let i =0; i < valj_efb.length; i++){
    if(valj_efb[i].hasOwnProperty('parent') && valj_efb[i].parent==valj_efb[indx_state].id_){
      valj_efb.splice(i,1);
      i--;
    }
  }

  if(autofilled){
    const id_valj = valj_efb[indx_state].id_;
    const s_index =  sendBack_emsFormBuilder_pub.findIndex(x=>x.id_==id_valj);
    if(s_index!=-1){
      const row_sb = sendBack_emsFormBuilder_pub[s_index-1];
        if(s_index-1>1 && row_sb.type==='stateProvince'){
        iso2_country = row_sb.cont_;
        iso2_statePove = row_sb.statePrev_
        }
    }

  }
  for (const key in result.r) {
    const n = efb_remove_forbidden_chrs(result.r[key].n);
    const l = efb_remove_forbidden_chrs(result.r[key].l);
    let value = result.r[key].n==result.r[key].l || result.r[key].l.length<1 ? n : `${l} (${n})`;
    let id_op= result.r[key].n.replaceAll(' ','_').toLowerCase();
    id_op = efb_remove_forbidden_chrs(id_op).replaceAll(' ','_');
    const rnd = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 2);
    const id = efb_remove_forbidden_chrs(result.r[key].n.toLowerCase()).replaceAll(' ','_') ;
    if(valj_efb[indx_state].hasOwnProperty('stylish') && Number(valj_efb[indx_state].stylish)>1){
      value =  Number(valj_efb[indx_state].stylish)==2 && l.length>1 ? l : n;
     }
    if(fieldType=="pubSelect"){
      valj_efb.push(
        {
          "id_": id +'-efb'+rnd,
          "dataId": id+'-efb'+rnd+"-id",
          "parent":valj_efb[indx_state].id_,
          "type": "option",
          "value": value,
          's2': id,
          "id_op": id_op,
          "step": valj_efb[indx_state].step,
          "amount": valj_efb[indx_state].amount,
          "n":n,
          "l": l,
      });
    }else if(fieldType=="getCitiesEfb"){
      const selected = id.toLowerCase()==valj_efb[indx_state].statePov.toLowerCase()  ? 'selected' : '';
      opt +=`<option value="${id.toLowerCase()}" ${selected}>${value}</option>`
    }
  }
  if(fieldType=="pubSelect"){

    Object.assign(valj_efb[indx_state],{'linked':true});
     opt += statePrevion_el_pro_efb(valj_efb[indx_state].id_, '', '', '', true,autofilled);
  }
  if(state_el!=null){
    state_el.innerHTML='';
    state_el.innerHTML=opt;
    state_el.classList.remove('is-loading');
  state_el.disabled = false;
  }else{
    setTimeout(() => {
      state_el = document.getElementById(idField);
      state_el.innerHTML=opt;
      state_el.classList.remove('is-loading');
    }, 2000);
  }
  return state_el!=null ? result : opt;
}
fun_check_link_state_efb=async(iso2_country , indx,form_id)=>{
 if(!iso2_country) return;
 let indx_state =-1;
 if(form_ID_emsFormBuilder!=parseInt(form_id) || valj_efb.length<1 )valj_efb= await fun_valj_efb_run(form_id);
  for (let i = indx+1; i < valj_efb.length; i++) {
    if(valj_efb[i].type=='option'){
    }else if((valj_efb[i].type=='statePro' || valj_efb[i].type=='stateProvince') && valj_efb[i].amount>valj_efb[indx].amount){
      indx_state =i;
      break;
    }else{
      return;
    }
  }

  if(indx_state === -1 || !valj_efb[indx_state]) return;
  let state_el = document.getElementById(valj_efb[indx_state].id_+'_options');
   if(valj_efb[indx_state].country==iso2_country) return;
  valj_efb[indx_state].country=iso2_country;
  for(let i =0; i < valj_efb.length; i++){
    if(valj_efb[i].hasOwnProperty('parent') && valj_efb[i].parent==valj_efb[indx_state].id_){
      valj_efb.splice(i,1);
      i--;
    }
  }
   await callFetchStatesPovEfb(valj_efb[indx_state].id_+'_options', iso2_country, indx_state,'pubSelect');
}
async function callFetchStatesPovEfb(idField,iso2_country, indx_state,fieldType,autofilled=false) {
  if(!iso2_country) return;
  let state_el= document.getElementById(idField)
  if(state_el!=null){
    state_el.innerHTML = `<option value="">${efb_var.text.loading}</option>`;
    state_el.classList.add('is-loading');
    state_el.disabled=true;
  }
  iso2_country = iso2_country.toLowerCase()
  if(sessionStorage.getItem('fetech_result_iso2_country')==iso2_country && sessionStorage.getItem('fetech_result_states')!=null){
    const result = JSON.parse(sessionStorage.getItem('fetech_result_states'));
    return parsing_result_json_smart_address_efb(fieldType,indx_state,idField,result, autofilled);
  }
  let url = efb_var.zone_area ?? ajax_object_efm.zone_area
  url =url +`json/states/${iso2_country}.json`
  const state_check = fun_state_check_addon_AdnOF_setting_efb();
  if(state_check){
    url =efb_var.images.plugin_url+ `/vendor/offline/json/states/${iso2_country}.json`;
    url =url.replaceAll('//vendor','/vendor');
   }
   valj_efb[indx_state].hasOwnProperty('country') ? valj_efb[indx_state].country=iso2_country : Object.assign(valj_efb[indx_state], {country:iso2_country});
   clean_options_select_efb(valj_efb[indx_state].id_);

   try {
    let result = await  fetch_json_from_url_efb(url)
    if(result.s==false){
      alert_message_efb('',efb_var.text.offlineSend,5,'danger')
      return;
    }
  const json_result =JSON.stringify(result)
  sessionStorage.setItem('fetech_result_states', json_result);
  sessionStorage.setItem('fetech_result_iso2_country', iso2_country);

  return parsing_result_json_smart_address_efb(fieldType,indx_state,idField,result, autofilled);
} catch (error) {
  alert_message_efb('', efb_var.text.offlineSend, 5, 'warning');
}

return false;
}

async function parsing_result_json_smart_address_efb(fieldType,indx_state,idField,result, autofilled=false) {
let state_el= document.getElementById(idField)

  let opt = `<option selected disabled>${efb_var.text.nothingSelected}</option>`;
  for (const key in result.r) {
      const n = efb_remove_forbidden_chrs(result.r[key].n);
      const l = efb_remove_forbidden_chrs(result.r[key].l);
      let value = result.r[key].n==result.r[key].l || result.r[key].l.length<1 ? n : `${l} (${n})`;
      let id_op= result.r[key].n.replaceAll(' ','_').toLowerCase();
      id_op = efb_remove_forbidden_chrs(id_op).replaceAll(' ','_');
      const rnd = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 2);
      const id = efb_remove_forbidden_chrs(result.r[key].s.toLowerCase()).replaceAll(' ','_');
      if(valj_efb[indx_state].hasOwnProperty('stylish') && Number(valj_efb[indx_state].stylish)>1){
      value =  Number(valj_efb[indx_state].stylish)==2 && l.length>1 ? l : n;
      }
      if(fieldType=="pubSelect"){
        valj_efb.push(
          {
            "id_": id+'-efb'+rnd,
            "dataId":  id+'-efb'+rnd+"-id",
            "parent":valj_efb[indx_state].id_,
            "type": "option",
            "value": value,
            's2': id,
            "id_op": id_op,
            "step": valj_efb[indx_state].step,
            "amount": valj_efb[indx_state].amount,
            "n":n,
            "l": l,
        });
      }else if(fieldType=="getStatesPovEfb"){
        opt +=`<option value="${id.toLowerCase()}" ${ id.toLowerCase()==valj_efb[indx_state].statePov.toLowerCase() ? `selected` : ''}>${value}</option>`
      }
    }
    if(fieldType=="pubSelect"){
      Object.assign(valj_efb[indx_state],{'linked':true});
      opt += statePrevion_el_pro_efb(valj_efb[indx_state].id_, '', '', '', true,autofilled);
    }
    if(state_el!=null){
      state_el.innerHTML=opt;
      state_el.classList.remove('is-loading');
    state_el.disabled = false;
    }else{
      setTimeout(() => {
        state_el = document.getElementById(idField);
        state_el.innerHTML=opt;
        state_el.classList.remove('is-loading');
      }, 2000);
    }

 const f= document.getElementById(idField)
 if(f!=null && f.dataset.hasOwnProperty("vid") && autofilled==false){
  const id = f.dataset.vid;
  fun_remove_row_sendback_efb(id)
  return state_el!=null ? result : opt;
 }
}

function efbLoadLeafletFallback(callback) {
  if (window.efbLeafletLoading) {
    return;
  }
  window.efbLeafletLoading = true;

  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css';
  document.head.appendChild(link);

  var script = document.createElement('script');
  script.src = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js';
  script.onload = function() {
    window.efbLeafletLoading = false;
    if (callback && typeof callback === 'function') {
      setTimeout(callback, 100);
    }
  };
  script.onerror = function() {
    window.efbLeafletLoading = false;
    let containers = document.querySelectorAll('[id$="-f"]');
    containers.forEach(el => {
      if (el) {
        el.innerHTML = '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;"><strong>Map Error:</strong> Cannot load map library. Please check your internet connection and refresh the page.</div>';
      }
    });
  };
  document.head.appendChild(script);
}

function efbCreateMap(id ,r ,viewState) {

  if (typeof L === 'undefined') {
    efbLoadLeafletFallback(() => {
      efbCreateMap(id, r, viewState);
    });
    return;
  }

  var efbInitialLat = viewState==true ? r.value=='' ? r.lat : r.value[0].lat : r.lat;
  var efbInitialLng = viewState==true ? r.value=='' ? r.lng : r.value[0].lng :r.lng;
  var efbInitialZoom = viewState==true ? 12 :r.zoom;
  var efbAllowAddingMarkers = Number(r.mark)>0 ? true :false;
  if(viewState==true && efbAllowAddingMarkers==true)efbAllowAddingMarkers=false;
  const efbLanguage = efb_var.language.length==2 ? efb_var.language : efb_var.language.slice(0,2) ;
  var efbMapContainer = document.createElement('div');
  efbMapContainer.className = 'map-container';
  efbMapContainer.style.cssText = 'width:100%;height:350px;position:relative;';
  var efbMapDiv = document.createElement('div');
  efbMapDiv.dataset.id =id+"-mapsdiv"
  efbMapDiv.className = 'map';
  efbMapDiv.style.cssText = 'width:100%;height:100%;';
  efbMapContainer.appendChild(efbMapDiv);
  let el_maps = document.getElementById(id+'-f');
  const form_id = el_maps.dataset.formid;
  el_maps.appendChild(efbMapContainer);

  efbMapDiv.offsetHeight;
  efbMapContainer.offsetWidth;

  var efbMap = L.map(efbMapDiv).setView([efbInitialLat, efbInitialLng], efbInitialZoom);
  var efbOsmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
  }).addTo(efbMap);

  var efbSatelliteLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
  });

  var efbBaseLayers = {
      "OSM 1": efbOsmLayer,
      "OSM 2": efbSatelliteLayer
  };

  var efbMarkersLayer = L.layerGroup().addTo(efbMap);
  var efbOverlays = {
      "Markers": efbMarkersLayer
  };

  L.control.layers(efbBaseLayers, efbOverlays).addTo(efbMap);

  var efbMap_dv = document.querySelector(`[data-id="${id}-mapsdiv"]`);
  efbMap_dv.dataset.leaflet =efbMap._leaflet_id;

  var efbSearchDiv = L.control({ position: 'bottomleft' });
  efbSearchDiv.onAdd = function (efbMap) {
      var efbDiv = L.DomUtil.create('div', 'custom-control');
      efbDiv.dataset.id = id+'-contorller';
      efbDiv.classList.add('efb-searchbox');
      if (efbAllowAddingMarkers) {
          efbDiv.innerHTML = `
             <div class="efb d-flex justify-content-start align-items-center flex-nowrap flex-row" style="gap: 4px; position: relative;">
              <!-- Locate Me Button -->
              <a ${state_efb == 'view' ? '' : `onclick="efbLocateMe(${efbMap._leaflet_id}, '${id}')"`}
                class="efb btn btn-sm btn-dark text-light d-flex align-items-center justify-content-center flex-shrink-0"
                title="Locate Me"
                style="min-width: 32px; height: 32px; padding: 0;">
                <i class="efb bi-crosshair" style="font-size: 14px;"></i>
              </a>

              <!-- Search Input Field -->
              <input type="text" id="efb-search-${efbMap._leaflet_id}"
                placeholder="${efb_var.text.eln}"
                class="efb form-control flex-grow-1"
                style="min-width: 100px; height: 32px; font-size: 13px; padding: 4px 8px;"
                ${state_efb == 'view' ? 'disabled' : ''}>

              <!-- Search Button -->
              <a ${state_efb == 'view' ? '' : `onclick="efbSearchLocation(${efbMap._leaflet_id})"`}
                class="efb btn btn-sm btn-secondary text-light d-flex align-items-center justify-content-center flex-shrink-0"
                title="${efb_var.text.search}"
                style="min-width: 32px; height: 32px; padding: 0;">
                <i class="efb bi-search d-block d-md-none" style="font-size: 14px;"></i>
                <span class="efb d-none d-md-block" style="font-size: 11px; white-space: nowrap; padding: 0 4px;">${efb_var.text.search}</span>
              </a>

              <!-- Clear Markers Button -->
              <a ${state_efb == 'view' ? '' : `onclick="efbClearMarkers(${efbMap._leaflet_id}, '${id}')"`}
                class="efb btn btn-sm btn-danger text-light d-flex align-items-center justify-content-center flex-shrink-0"
                title="${efb_var.text.deletemarkers}"
                style="min-width: 32px; height: 32px; padding: 0;">
                <i class="efb bi-trash d-block d-md-none" style="font-size: 14px;"></i>
                <span class="efb d-none d-md-block" style="font-size: 11px; white-space: nowrap; padding: 0 4px;">${efb_var.text.deletemarkers}</span>
              </a>

              <!-- Error Message (hidden by default) -->
              <div id="efb-error-message-${efbMap._leaflet_id}"
                class="efb position-absolute error-message text-danger d-none"
                style="font-size: 12px; top: 100%; left: 0; right: 0; background: rgba(248, 249, 250, 0.95); padding: 4px 8px; border-radius: 4px; margin-top: 2px; z-index: 1001;"></div>
          </div>
          `;
          efbDiv.classList.remove('d-none');

      } else {
          efbDiv.innerHTML = `
              <div id="efb-error-message-${efbMap._leaflet_id}"
                class="efb w-100 error-message text-danger d-none"
                style="font-size: 12px; padding: 8px;"></div>
          `;
          efbDiv.classList.add('d-none');
          efbDiv.classList.add('efb');
      }

      L.DomEvent.disableClickPropagation(efbDiv);
      L.DomEvent.disableScrollPropagation(efbDiv);

      return efbDiv;
  };
  efbSearchDiv.addTo(efbMap);

  maps_efb[efbMap._leaflet_id] = {
      map: efbMap,
      markersLayer: efbMarkersLayer,
      markers: [],
      locationList: []
  };

  if(state_efb != 'view' && viewState==false){
      if (efbAllowAddingMarkers) {

        efbMap.on('click', function(e) {
            var efbLatlng = e.latlng;
            efbAddMarker(efbLatlng.lat, efbLatlng.lng, efbMap._leaflet_id , efbAllowAddingMarkers ,r,form_id);
        });
    } else {

        efbAddInitialMarker(efbInitialLat, efbInitialLng, efbMap._leaflet_id);
    }
  }else{
    const len = r.value.length;
    if(len>0){
      Object.assign(r ,{mark:len});
      for (let i = 0; i <len; i++) {
        efbAddMarker(r.value[i].lat, r.value[i].lng, efbMap._leaflet_id, i+1 ,r,form_id);
      }
    }
  }

  if (typeof L.control.fullscreen === 'function') {
    var efbFullscreenControl = L.control.fullscreen({
        title: {
            'false': 'Go Fullscreen',
            'true': 'Exit Fullscreen'
        }
    });
    efbMap.addControl(efbFullscreenControl);

    efbMap.on('enterFullscreen', function(){
    });

    efbMap.on('exitFullscreen', function(){
    });
  } else {
  }

  efbMap.whenReady(function() {
    efbMap.invalidateSize();
  });
  setTimeout(function() { efbMap.invalidateSize(); }, 100);
  setTimeout(function() { efbMap.invalidateSize(); }, 500);
  setTimeout(function() { efbMap.invalidateSize(); }, 1500);
}

function efbSearchLocation(efbMapId) {
  var efbQuery = document.getElementById(`efb-search-${efbMapId}`).value;
  var efbErrorMessageDiv = document.getElementById(`efb-error-message-${efbMapId}`);
  efbErrorMessageDiv.innerHTML = '';
  const efbLanguage = efb_var.language.length==2 ? efb_var.language : efb_var.language.slice(0,2)
  fetch(`https://nominatim.openstreetmap.org/search?q=${efbQuery}&format=json&accept-language=${efbLanguage}`)
      .then(response => {
          if (!response.ok) {
              throw new Error('Network response was not ok');
          }
          return response.json();
      })
      .then(data => {
          if (data.length > 0) {
              var efbLatlng = [data[0].lat, data[0].lon];
              maps_efb[efbMapId].map.setView(efbLatlng, 13);
          } else {
              efbErrorMessageDiv.classList.remove('d-none');
              let val = efb_var.text.slocation.replace('%s', '');
              val = efb_var.text.snotfound.replace('%s', val);
              efbErrorMessageDiv.textContent =  val;
          }
      })
      .catch(error => {
          efbErrorMessageDiv.classList.remove('d-none');
          efbErrorMessageDiv.textContent = 'Error fetching location: ' + error.message;
      });
}

function efbAddMarker(efbLat, efbLng, efbMapId, efbAllowAddingMarkers,r,form_id, efbName = '' ) {
  var efbMarkerNumber ='';
  if(state_efb!='view'){
     efbMarkerNumber = efbAllowAddingMarkers ? maps_efb[efbMapId].markers.length + 1 : '';
     if(Number(r.mark)<efbMarkerNumber) return
  }else{
    efbMarkerNumber = efbAllowAddingMarkers;
  }
  const efbLanguage = efb_var.language.length==2 ? efb_var.language : efb_var.language.slice(0,2);
  var efbErrorMessageDiv = document.getElementById(`efb-error-message-${efbMapId}`);
  var efbMarkerIcon = L.divIcon({
      className: 'custom-div-icon',
      html: map_marker_ui_efb(efbMarkerNumber),
      iconSize: [32, 32],
      iconAnchor: [16, 32]
  });

  var efbMarker = L.marker([efbLat, efbLng], { icon: efbMarkerIcon }).addTo(maps_efb[efbMapId].markersLayer);
  maps_efb[efbMapId].markers.push(efbMarker);
  if (efbName === '') {
      fetch(`https://nominatim.openstreetmap.org/reverse?lat=${efbLat}&lon=${efbLng}&format=json&accept-language=${efbLanguage}`)
          .then(response => {
              if (!response.ok) {
                  throw new Error('Network response was not ok');
              }
              return response.json();
          })
          .then(data => {
              var efbAddress = data.display_name;
              efbMarker.bindPopup(efbAddress);
              maps_efb[efbMapId].locationList.push({
                  lat: efbLat,
                  lng: efbLng,
                  address: efbAddress
              });

              if(state_efb!='view'){
                const o = [{ id_: r.id_, name: r.name, amount: r.amount, type: "maps", value: maps_efb[efbMapId].locationList, session: sessionPub_emsFormBuilder,form_id:form_id }];
                fun_sendBack_emsFormBuilder(o[0])
              }
          })
          .catch(error => {
              efbErrorMessageDiv.classList.remove('d-none');
              document.getElementById(`efb-error-message-${efbMapId}`).textContent = 'Error fetching address: ' + error.message;
          });
  } else {
      efbMarker.bindPopup(efbName);
      maps_efb[efbMapId].locationList.push({
          lat: efbLat,
          lng: efbLng,
          address: efbName
      });
  }

    if(state_efb=='view'){
      efbErrorMessageDiv.classList.remove('d-none');
      let v ='<!--efb-->'
      for (let i = 0; i < r.value.length; i++) {
        v+= `<p>${i+1}- ${r.value[i].address} - <b> <a href="https://www.openstreetmap.org/?mlat=${r.value[i].lat}&mlon=${r.value[i].lng}#map=17/${r.value[i].lat}/${r.value[i].lng}" target="_blank">${r.value[i].lat}, ${r.value[i].lng}</a></b></p>`
      }
      setTimeout(() => {
          if(document.getElementById('os-address-efb')==null){
          document.getElementById(r.id_+`-f`).innerHTML +='<div class="efb fs-6  mx-2" id="os-address-efb">'+ v+'</div>';
          }
        }, 1000);
    }
}

function efbClearMarkers(efbMapId,indx) {
  maps_efb[efbMapId].markersLayer.clearLayers();
  maps_efb[efbMapId].markers = [];
  maps_efb[efbMapId].locationList = [];

  if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
    const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.type == "maps");
    if (indx != -1) { sendBack_emsFormBuilder_pub.splice(indx, 1); }
  }
}

function efbAddInitialMarker(efbLat, efbLng, efbMapId) {
  const efbLanguage = efb_var.language.length==2 ? efb_var.language : efb_var.language.slice(0,2)
  var efbMarkerNumber = maps_efb[efbMapId].markers.length + 1;
  var efbMarkerIcon = L.divIcon({
      className: 'custom-div-icon',
      html: map_marker_ui_efb(''),
      iconSize: [32, 32],
      iconAnchor: [16, 32]
  });

  var efbMarker = L.marker([efbLat, efbLng], { icon: efbMarkerIcon }).addTo(maps_efb[efbMapId].markersLayer);
  fetch(`https://nominatim.openstreetmap.org/reverse?lat=${efbLat}&lon=${efbLng}&format=json&accept-language=${efbLanguage}`)
      .then(response => {
          if (!response.ok) {
              throw new Error('Network response was not ok');
          }
          return response.json();
      })
      .then(data => {
          var efbAddress = data.display_name;
          efbMarker.bindPopup(efbAddress);
          maps_efb[efbMapId].locationList.push({
              lat: efbLat,
              lng: efbLng,
              address: efbAddress
          });
      })
      .catch(error => {
          efbErrorMessageDiv.classList.remove('d-none');
          document.getElementById(`efb-error-message-${efbMapId}`).textContent = 'Error fetching address: ' + error.message;
      });
}

map_marker_ui_efb=(efbMarkerNumber)=>{
  return ` <svg width="50" height="75" xmlns="http://www.w3.org/2000/svg">
    <path id="map-pointer" d="M25,3 C34.3888,3 42,10.6112 42,20 C42,28.5 25,58 25,58 C25,58 8,28.5 8,20 C8,10.6112 15.6112,3 25,3 Z"
          fill="#000000" stroke="#ffffff" stroke-width="3" />
    <circle cx="25" cy="20" r="8" fill="#ffffff" />
    <text id="pointer-number" x="25" y="20" font-size="10" font-weight="bold" fill="#000000" text-anchor="middle" dominant-baseline="middle">${efbMarkerNumber}</text>
  </svg>`
}

function efbLocateMe(efbMapId) {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        var efbLat = position.coords.latitude;
        var efbLng = position.coords.longitude;

        const efbLanguage = efb_var.language.length==2 ? efb_var.language : efb_var.language.slice(0,2);
        var efbMarkerNumber = maps_efb[efbMapId].markers.length + 1;
        var efbMarkerIcon = L.divIcon({
            className: 'custom-div-icon',
            html:  `
            <svg width="32" height="32" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="blue" stroke="white" stroke-width="2"/>
                <circle cx="12" cy="12" r="4" fill="white"/>
            </svg>
        `,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        });

        var efbMarker = L.marker([efbLat, efbLng], { icon: efbMarkerIcon }).addTo(maps_efb[efbMapId].markersLayer);

        var efbLatlng = [efbLat, efbLng];
        maps_efb[efbMapId].map.setView(efbLatlng, 13);

        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${efbLat}&lon=${efbLng}&format=json&accept-language=${efbLanguage}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                var efbAddress = data.display_name;
                efbMarker.bindPopup(efbAddress);
                maps_efb[efbMapId].locationList.push({
                    lat: efbLat,
                    lng: efbLng,
                    address: efbAddress
                });
            })
            .catch(error => {
                efbErrorMessageDiv.classList.remove('d-none');
                document.getElementById(`efb-error-message-${efbMapId}`).textContent = 'Error fetching address: ' + error.message;
            });
    }, function(error) {
        alert('Error: ' + error.message);
    });
} else {
    alert('Geolocation is not supported by this browser.');
}
}

fun_remove_row_sendback_efb=(id)=>{
  if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
    let indx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_==id);
    if(indx!=-1){
      const row = sendBack_emsFormBuilder_pub[indx+1];
      sendBack_emsFormBuilder_pub.splice(indx,1);
      if(row!=null && row.type=='cityList'){
        indx = indx+1;
        const el = document.getElementById(row.id_+'_options');
        if(el!=null){
          el.innerHTML = `<option value="">${efb_var.text.nothingSelected}</option>`;
        }
        fun_remove_row_sendback_efb(row.id_);
      }
    }
  }
}

fun_event_esign_efb=(id,form_id,disabled,v)=>{
  const canvas = document.getElementById(id+'_');
  c2d_contex_efb = canvas.getContext("2d");
  c2d_contex_efb.lineWidth = 5;
  c2d_contex_efb.strokeStyle = "#000000";
  c2d_contex_efb.lineCap = "round";
  c2d_contex_efb.lineJoin = "round";

  if(disabled) return;

  function getCanvasCoordinates(canvas, event) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    let clientX, clientY;
    if (event.touches && event.touches[0]) {
      clientX = event.touches[0].clientX;
      clientY = event.touches[0].clientY;
    } else {
      clientX = event.clientX;
      clientY = event.clientY;
    }

    return {
      x: (clientX - rect.left) * scaleX,
      y: (clientY - rect.top) * scaleY
    };
  }

  canvas.addEventListener("mousedown", (e) => {
    draw_mouse_efb = true;
    canvas_id_efb = id;
    lastMousePostion_efb = getCanvasCoordinates(canvas, e);

    c2d_contex_efb.beginPath();
    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);

    c2d_contex_efb.fillStyle = "#000000";
    c2d_contex_efb.beginPath();
    c2d_contex_efb.arc(lastMousePostion_efb.x, lastMousePostion_efb.y, 2, 0, 2 * Math.PI);
    c2d_contex_efb.fill();
  }, false);

  canvas.addEventListener("mouseup", (e) => {
    if (!draw_mouse_efb) return;
    draw_mouse_efb = false;

    const data = canvas.toDataURL();
    document.getElementById(`${canvas_id_efb}-sig-data`).value = data;

    const el = document.getElementById(`${id}-sig-data`);
    const value = el.value;
    document.getElementById(`${id}_-message`).innerHTML='';
    document.getElementById(`${id}_-message`).style.display='none';
    const o = [{ id_: id, name: v.name, amount: v.amount, type: v.type, value: value, session: sessionPub_emsFormBuilder, form_id: form_id }];
    fun_sendBack_emsFormBuilder(o[0]);
  }, false);

  canvas.addEventListener("mousemove", (e) => {
    if (!draw_mouse_efb) return;

    const currentPos = getCanvasCoordinates(canvas, e);

    c2d_contex_efb.beginPath();
    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);
    c2d_contex_efb.lineTo(currentPos.x, currentPos.y);
    c2d_contex_efb.stroke();

    lastMousePostion_efb = currentPos;
  }, false);

  canvas.addEventListener("touchstart", (e) => {
    e.preventDefault();
    document.body.style.overflow = 'hidden';
    draw_mouse_efb = true;
    canvas_id_efb = id;
    lastMousePostion_efb = getCanvasCoordinates(canvas, e);

    c2d_contex_efb.beginPath();
    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);

    c2d_contex_efb.fillStyle = "#000000";
    c2d_contex_efb.beginPath();
    c2d_contex_efb.arc(lastMousePostion_efb.x, lastMousePostion_efb.y, 2, 0, 2 * Math.PI);
    c2d_contex_efb.fill();
  }, false);

  canvas.addEventListener("touchmove", (e) => {
    e.preventDefault();
    document.body.style.overflow = 'hidden';
    if (!draw_mouse_efb) return;

    const currentPos = getCanvasCoordinates(canvas, e);

    c2d_contex_efb.beginPath();
    c2d_contex_efb.moveTo(lastMousePostion_efb.x, lastMousePostion_efb.y);
    c2d_contex_efb.lineTo(currentPos.x, currentPos.y);
    c2d_contex_efb.stroke();

    lastMousePostion_efb = currentPos;
  }, false);

  canvas.addEventListener("touchend", (e) => {
    e.preventDefault();
    document.body.style.overflow = 'auto';
    if (!draw_mouse_efb) return;
    draw_mouse_efb = false;

    const data = canvas.toDataURL();
    document.getElementById(`${canvas_id_efb}-sig-data`).value = data;

    const value = document.getElementById(`${id}-sig-data`).value;
  }, false);
}

fun_state_check_addon_AdnOF_setting_efb =()=>{
  if(typeof setting_emsFormBuilder == 'undefined') {
    setting_emsFormBuilder = typeof ajax_object_efm.form_setting == 'string' ?JSON.parse(ajax_object_efm.form_setting) : ajax_object_efm.form_setting;
  }
  if(typeof efb_version!='undefined'){
   return  setting_emsFormBuilder.AdnOF==true ? true : false;
  }else{
   return setting_emsFormBuilder.addons.hasOwnProperty('AdnOF')==true && setting_emsFormBuilder.addons.AdnOF==true ? true : false;
  }
}
