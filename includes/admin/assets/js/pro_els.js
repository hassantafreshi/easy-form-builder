console.log('pro.js')

 maps_el_pro_efb =(previewSate, pos , rndm,iVJ)=>{
    return `
    <div class="efb  ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12 "  id='${rndm}-f'>      
      ${previewSate == true && valj_efb[iVJ].mark != 0 ? `<div id="floating-panel" class="efb "><input id="delete-markers_maps_efb-efb" class="efb  btn btn-danger" type="button" value="${efb_var.text.deletemarkers}" /></div>` : '<!--notPreview-->'}
        <div id="${rndm}-map" data-type="maps" class="efb  maps-efb emsFormBuilder_v ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} " data-id="${rndm}-el" data-name='maps'>
      </div>
    `
 }

 dadfile_el_pro_efb =(previewSate , rndm,iVJ)=>{

    return `<div class="efb  mb-3" id="uploadFilePreEfb">
                <label for="${rndm}_" class="efb  form-label">
                    <div class="efb  dadFile-efb   ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner}   ${valj_efb[iVJ].el_border_color}" id="${rndm}_box">
                    ${ui_dadfile_efb(iVJ, previewSate)}                            
                    </div>
                </label>
            </div>`;
}

esign_el_pro_efb =(previewSate, pos , rndm,iVJ,desc)=>{

    return `<div class="efb  ${previewSate == true ? pos[3] : `col-md-9`} col-sm-12" id ="${rndm}-f">
    <canvas class="efb  sign-efb bg-white ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color} " data-code="${rndm}"  data-id="${rndm}-el" id="${rndm}_" width="600" height="200">
        ${efb_var.text.updateUrbrowser}
    </canvas>
   ${previewSate == true ? `<input type="hidden" data-type="esign" data-vid='${rndm}' class="efb  emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">` : ``}
    <div class="efb  mx-1">${desc}</div>
    <div class="efb  mb-3"><button type="button" class="efb  btn ${valj_efb[iVJ].corner} ${valj_efb[iVJ].button_color} efb-btn-lg mt-1" id="${rndm}_b" onClick="fun_clear_esign_efb('${rndm}')">  <i class="efb  ${valj_efb[iVJ].icon} mx-2 ${valj_efb[iVJ].icon_color != "default" ? valj_efb[iVJ].icon_color : ''} " id="${rndm}_icon"></i><span id="${rndm}_button_single_text" class="efb  text-white efb ">${valj_efb[iVJ].button_single_text}</span></button></div>
      `;
}

rating_el_pro_efb =(previewSate, rndm,iVJ)=>{
    return ` <div class="efb  col-md-10 col-sm-12" id ="${rndm}-f">
      <div class="efb  star-efb d-flex justify-content-center ${valj_efb[iVJ].classes}"> 
                        <input type="radio" id="${rndm}-star5" data-vid='${rndm}' data-type="rating" class="efb "   data-star='star'  name="${rndm}-star-efb" value="5" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star5" for="${rndm}-star5"  ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',5)"` : ''} title="5stars" class="efb  ${valj_efb[iVJ].el_height} star-efb">5 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star4" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" value="4" data-name="star"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star4"  for="${rndm}-star4" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',4)"` : ''} title="4stars" class="efb  ${valj_efb[iVJ].el_height} star-efb">4 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star3" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' name="${rndm}-star-efb" data-name="star" value="3"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star3" for="${rndm}-star3"  ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',3)"` : ''} title="3stars" class="efb  ${valj_efb[iVJ].el_height} star-efb">3 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star2" data-vid='${rndm}' data-type="rating" class="efb "  data-star='star' data-name="star" name="${rndm}-star-efb" value="2"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star2" for="${rndm}-star2" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',2)"` : ''} title="2stars" class="efb  ${valj_efb[iVJ].el_height} star-efb">2 ${efb_var.text.stars}</label>
                        <input type="radio" id="${rndm}-star1" data-vid='${rndm}' data-type="rating" class="efb " data-star='star' data-name="star" name="${rndm}-star-efb" value="1"  data-id="${rndm}-el" ${previewSate != true ? 'disabled' : ''}>
                        <label id="${rndm}_star1" for="${rndm}-star1" ${previewSate == true ? `onClick="fun_get_rating_efb('${rndm}',1)"` : ''} title="1star" class="efb   ${valj_efb[iVJ].el_height} star-efb">1 ${efb_var.text.star}</label>
      </div>  
      <input type="hidden" data-vid="${rndm}" data-type="rating" class="efb emsFormBuilder_v ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" id="${rndm}-stared" >`
}

countryList_el_pro_efb = ( rndm,rndm_1,op_3,op_4,editState)=>{
    let optn ='<!--countreies-->'
    if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {

          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb">${i.value}</option>`
        }//end for 

      } else {

        if (typeof countries_local != 'object') {
          optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="efb text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="efb text-dark efb" >${efb_var.text.newOption} 2</option>
           `
          optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
          optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);

        } else {
          countries_local.sort();
          let optn = '<!-- list of counries -->'
          for (let i = 0; i < countries_local.length; i++) {

            const op_id = countries_local[i].replace(" ", "_");
            optn += `<option value="${countries_local[i]} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_id}" data-op="${op_id}" class="efb text-dark efb" >${countries_local[i]}</option>`

            optionElpush_efb(rndm, countries_local[i], rndm_1, op_id);

            // i+=1;
          }

        }
        // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
      }
      return optn;
}

statePrevion_el_pro_efb = (rndm,rndm_1,op_3,op_4,editState)=>{
    let optn ='<!--states-->'
    if (editState != false) {
        // if edit mode
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        for (const i of optns_obj) {
          optn += `<option value="${i.value}" id="${i.id_}" data-id="${i.id_}" data-op="${i.id_}" class="efb ${valj_efb[indx_parent].el_text_color} emsFormBuilder_v efb">${i.value}</option>`
        }//end for 

      } else {

        if (typeof state_local != 'object') {
          optn = `
            <option value="${efb_var.text.newOption} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${op_3}" data-op="${op_3}" class="efb text-dark efb" >${efb_var.text.newOption} 1</option>
            <option value="${efb_var.text.newOption} 2" id="${rndm_1}" data-vid='${rndm}' data-id="${op_4}" data-op="${op_4}" class="efb text-dark efb" >${efb_var.text.newOption} 2</option>
           `
          optionElpush_efb(rndm, `${efb_var.text.newOption} 1`, rndm_1, op_3);
          optionElpush_efb(rndm, `${efb_var.text.newOption} 2`, rndm_1, op_4);

        } else {
          state_local.sort();
          let optn = '<!-- list of counries -->'
          for (let i = 0; i < state_local.length; i++) {

            optn += `<option value="${state_local[i]} 1" id="${rndm_1}" data-vid='${rndm}' data-id="${state_local[i]}" data-op="${state_local[i]}" class="efb text-dark efb" >${state_local[i]}</option>`
            optionElpush_efb(rndm, state_local[i], rndm_1, state_local[i]);
          }

        }
        // optionElpush_efb(rndm, 'Option Three', rndm_1, op_5);
      }
      return optn;
}


headning_el_pro_efb = (rndm,iVJ)=>{
    return ` <div class="efb  col-md-12 col-sm-12"  id='${rndm}-f'>
    <p  id="${rndm}_"  class="efb  px-2  emsFormBuilder_v  ${valj_efb[iVJ].classes}  ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size}   efbField" data-vid='${rndm}' data-id="${rndm}-el" >${valj_efb[iVJ].value}</p>
    </div>`
}

link_el_pro_efb = (previewSate, rndm,iVJ)=>{
    return`<div class="efb  col-md-12 col-sm-12"  id='${rndm}-f'>
    <a  id="${rndm}_"  target="_blank" class="efb  px-2 btn underline emsFormBuilder_v ${previewSate != true ? 'disabled' : ''} ${valj_efb[iVJ].classes} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_text_size} efbField" data-vid='${rndm}' data-id="${rndm}-el" href="${valj_efb[iVJ].href}">${valj_efb[iVJ].value}</a>
    </div>`
}

yesNi_el_pro_efb = (previewSate, rndm,iVJ)=>{
    return `<div class="efb col-md-9 col-sm-12 efb ${valj_efb[iVJ].classes} "  id='${rndm}-f'>
    <div class="efb  btn-group  btn-group-toggle w-100  col-md-12 col-sm-12  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}" data-toggle="buttons" data-id="${rndm}-id" id="${rndm}_yn">    
    <label for="${rndm}_1" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_1_text}" onClick="yesNoGetEFB('${valj_efb[iVJ].button_1_text}', '${rndm}')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb left-efb ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_1">
      <input type="radio" name="${rndm}" data-type="switch" class="efb opButtonEfb elEdit emsFormBuilder_v efb" data-vid='${rndm}' data-id="${rndm}-id" id="${rndm}_1" value="${valj_efb[iVJ].button_1_text}"><span id="${rndm}_1_lab">${valj_efb[iVJ].button_1_text}</span></label>
    <span class="efb border-right border border-light efb"></span>
    <label for="${rndm}_2" data-lid="${rndm}" data-value="${valj_efb[iVJ].button_2_text}" onClick="yesNoGetEFB('${valj_efb[iVJ].button_2_text}' ,'${rndm}')" class="efb  btn ${valj_efb[iVJ].button_color} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].corner} yesno-efb right-efb ${previewSate != true ? 'disabled' : ''}" id="${rndm}_b_2">
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
              <i class="efb  bi-gear-fill text-success" id="efbSetting"></i></button>${efb_var.text.andAddingHtmlCode}
          </div></div></div>`;
      } else {
        ui = valj_efb[iVJ].value.replace(/@!/g, `"`) + "<!--endhtml first -->";
        ui = `<div ${previewSate == false ? `class="efb bg-light" id="${rndm}_html" ` : ''}> ${ui} </div>`;
      }
      return ui;
}

 function ui_dadfile_efb(indx, previewSate) {
    let n = valj_efb[indx].file;
    n = efb_var.text[n];
    //console.log(n,efb_var.text[n] )
    return `<div class="efb icon efb"><i class="efb  fs-3 ${valj_efb[indx].icon} ${valj_efb[indx].icon_color}" id="${valj_efb[indx].id_}_icon"></i></div>
    <h6 id="${valj_efb[indx].id_}_txt" class="efb text-center m-1">${efb_var.text.dragAndDropA} ${n} </h6> <span>${efb_var.text.or}</span>
    <button type="button" class="efb  btn ${valj_efb[indx].button_color} efb-btn-lg" id="${valj_efb[indx].id_}_b">
        <i class="efb  bi-upload mx-2"></i>${efb_var.text.browseFile}
    </button>
   <input type="file" hidden="" data-type="dadfile" data-vid='${valj_efb[indx].id_}' data-ID='${valj_efb[indx].id_}' class="efb  emsFormBuilder_v   ${valj_efb[indx].required == 1 || valj_efb[indx].required == true ? 'required' : ''}" id="${valj_efb[indx].id_}_" data-id="${valj_efb[indx].id_}-el" ${previewSate != true ? 'disabled' : ''}>`
  
  }


  

function viewfileEfb(id, indx) {
    let fileType = fileEfb.type;
    const filename = fileEfb.name;
    let icon = ``;
    switch (valj_efb[indx].file) {
      case 'document':
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
          <i class="efb  ico-file ${icon} ${valj_efb[indx].icon_color} text-center"></i>
          <span class="efb  text-muted">${fileEfb.name}</span>
          </div>
    </div>`;
  
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
      const m = `${ajax_object_efm.text.pleaseUploadA} ${ajax_object_efm.text[valj_efb[indx].file]}`;
      document.getElementById(`${id}_-message`).innerHTML = m;
      if(document.getElementById(`${id}_-message`).classList.contains('show'))document.getElementById(`${id}_-message`).classList.add('show');
      noti_message_efb('', m, 4, 'danger')
  
      document.getElementById(`${id}_box`).classList.remove("active");
      //  dragTextEfb.textContent = "Drag & Drop to Upload a File";
      fileEfb = [];
    }
  }

  
function removeFileEfb(id, indx) {
    fileEfb = "";
    //dropAreaEfb.classList.add("active");
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
  }//end function

  
function gm_authFailure() {
    const body = `<p class="efb fs-6 efb">${efb_var.text.aPIkeyGoogleMapsFeild} <a href="https://developers.google.com/maps/documentation/javascript/error-messages" target="blank">${efb_var.text.clickHere}</a> </p>`
    noti_message_efb(efb_var.text.error, body, 15, 'danger')
  
  }

  

  
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
  

  
  function yesNoGetEFB(v, id) {
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
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
    const zoom = idx != -1 && valj_efb[idx].zoom && valj_efb[idx].zoom != "" ? valj_efb[idx].zoom : 10;
    const location = { lat: lat, lng: lon };
    if(typeof google == "undefined"){
      noti_message_efb(efb_var.text.error,googleMapsNOkEfb(),20,'danger');
      return 0;
    }
    map = new google.maps.Map(document.getElementById(`${valj_efb[idx].id_}-map`), {
      zoom: zoom,
      center: location,
      mapTypeId: "roadmap",
    });

    // This event listener will call addMarker() when the map is clicked.
    if (mark != 0 && mark != -1) {
      map.addListener("click", (event) => {
        const latlng = event.latLng.toJSON();
        if (mark_maps_efb.length < mark) {
          mark_maps_efb.push(latlng);
          addMarker(event.latLng);
        }
      });
      // add event listeners for the buttons


      if (document.getElementById("delete-markers_maps_efb-efb")) document.getElementById("delete-markers_maps_efb-efb").addEventListener("click", deletemarkers_maps_efb_efb);
      // Adds a marker at the center of the map.
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
  
    if (typeof (sendBack_emsFormBuilder_pub) != "undefined") {
      //mark_maps_efb
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
  /* map section end */