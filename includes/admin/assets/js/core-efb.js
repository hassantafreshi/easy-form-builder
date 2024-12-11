//multi step form wizard builder (core)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
// WhiteStudio.team


let exportView_emsFormBuilder = [];
let stepsCountEfb;
let sendBack_emsFormBuilder = [];
let sessionPub_emsFormBuilder = "reciveFromServer"
let stepNames_emsFormBuilder = [`t`, `Sync`, `Sync`];
let currentTab_emsFormBuilder = 0;
let multiSelectElemnets_emsFormBuilder = [];
let valueJson_ws = [];
let demo_emsFormBuilder = false;
let validate_edit_mode_emsFormBuilder = false;
let test_view__emsFormBuilder = true


jQuery(function () {
  if (typeof ajax_object_efm_core != undefined) {
    ajax_object_efm_core = deepFreeze_efb(ajax_object_efm_core);
    if (Number(ajax_object_efm_core.check) == 1) {      
      fun_render_view_core_emsFormBuilder(ajax_object_efm_core.check);
      validate_edit_mode_emsFormBuilder = true;
    }
  }
});
function fun_render_view_core_emsFormBuilder(check) {
  //v2
  // valueJson_ws ? document.getElementById('button-preview-emsFormBuilder').disabled = false : document.getElementById('button-preview-emsFormBuilder').disabled = true;
  exportView_emsFormBuilder = [];
  valueJson_ws = JSON.parse(sessionStorage.getItem("valueJson_ws_p"));
  form_type_emsFormBuilder = valueJson_ws && valueJson_ws[0].type ? valueJson_ws[0].type : 'form';
  if (valueJson_ws == undefined) valueJson_ws = "N";
  for (let v of valueJson_ws) {

    let el = "";
    let id;
    let req;
    let classData = ``;
    switch (v.type) {
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
        id = v.id ? v.id : v.id_;
        req = v.required ? v.required : false;
        if (v.type == "date") {

          /* if (v.clander=="Persian" || v.clander=="Arabic") {
             v.type="text";
            if (v.clander=="Arabic"){
              classData="hijri-picker" 
              el =` <link href="bootstrap-datetimepicker-ar.css" rel="stylesheet" />`
             }else{
                classData="jalali-picker";
             }
           }; */
        }
        else if (v.type == "email" || v.type == "tel" || v.type === "url" || v.type === "password") classData = "validation";

        const vtype = (v.type == "payCheckbox" || v.type == "payRadio" || v.type == "paySelect" || v.type == "payMultiselect") ? v.type.slice(2).toLowerCase() : v.type;
        el += `<div class="efb row emsFormBuilder" id="${id}-row"> <label for="${id}" class="efb emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${vtype}"  id='${id}' name="${id}" class="efb ${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v`} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}>`;

        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount: v.amount })
        break;
      case 'file':
        id = v.id ? v.id : v.id_;
        req = v.required ? v.required : false;
        const drog = v.fileDrogAndDrop ? v.fileDrogAndDrop : false; // برای تشخیص اینکه حالت دراگ اند دراپ هست یا نه
        let acception = `.zip,.rar`;
        let typeFile = "Zip"
        if (v.file == "Image") {
          acception = `.png,.jpg,.jpeg`;
          typeFile = "Photo";
        } else if (v.file == "Media") {
          acception = `.mp3,.mp4,.wav,.wav,.AVI,.WebM,.MKV,.FLV`;
          typeFile = v.file;
        } else if (v.file == "Document") {
          acception = `.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp`;
          typeFile = v.file;
        } else if (v.file == "Zip") {
          acception = `.zip,.rar`;
          typeFile = v.file;
        } else if (v.file == "customize") {
          acception =  `.zip ,.rar`;
          if(v.hasOwnProperty('file_ctype') ){
            //seprate string to array by comma
            const arr = v.file_ctype.split(',');
            for (let i = 0; i < arr.length; i++) {
              acception += `.${arr[i]},`;
            }
          }
          typeFile = acception;
        }
        classData = drog == true ? "form-control-file text-secondary " : "";
        el = ` <div class="efb row emsFormBuilder ${drog == true ? `inputDnD` : ``}" id="${id}-row"> <label for="${id}" class="efb emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="efb ${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} accept="${acception}" onchange="valid_file_emsFormBuilder('${id}' ,'msg','')" data-id="${v.id_}" ${v.required == true ? 'required' : ''} ${drog == true ? ` data-title="${efb_var.text.DragAndDropA} ${typeFile} ${efb_var.text.orClickHere}"` : ``}>`
        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount: v.amount })
        break;
      case 'textarea':
        id = v.id ? v.id : v.id_;
        req = v.required ? v.required : false;
        el = `<div class="efb row emsFormBuilder" id="${id}-row"> <label for="${id}" class="efb emsFormBuilder" >${v.name}  ${v.required == true ? '*' : ''}</label><textarea id='${id}' name="${id}" class="efb ${v.class ? `${v.class} emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}></textarea>`
        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount: v.amount });
        break
      case 'button':
        id = v.id ? v.id : v.id_;
        el = `<div class="efb row emsFormBuilder" id="${id}-row"> <button  id='${id}' name="${id}" class="efb ${v.class ? `${v.class}  emsFormBuilder_v` : `btn btn-primary emsFormBuilder_v btn-lg btn-block`}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" value="${v.name}">${v.name}</button>`
        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, type: v.type, amount: v.amount });
        break
      case 'checkbox':
      case 'radiobutton':
        id = v.id ? v.id : v.id_;
        const typ = v.type == "checkbox" ? "checkbox" : "radio";
        req = v.required ? v.required : false;
        el = `<div class="efb  emsFormBuilder"><div class="efb row"><label for="${v.id_}" id="${v.id_}" class="efb emsFormBuilder emsFormBuilder-title ${v.required == true ? 'require' : ''}" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label></div>`
        // el = ` <label for="${v.id_}" class="efb emsFormBuilder" >${v.name}</label><input type="checkbox"  id='${id}' name="${v.id_}" class="efb ${v.class ? `${v.class}  emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'require' : ''}>`
        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, parents: v.id_, type: typ, required: req, amount: v.amount });
        break
      case 'multiselect':
      case 'payMultiselect':
        id = v.id ? v.id : v.id_;
        req = v.required ? v.required : false;
        if (v.allowMultiSelect == true && test_view__emsFormBuilder == true) {
          el += `<div class="efb row emsFormBuilder" id="${id}-row"> <label for="${id}" class="efb emsFormBuilder" >${v.name}(Disabled) ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="efb ${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v`} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} placeholder="${efb_var.text.selectOpetionDisabled}" data-id="${v.id_}" disabled>`;
        } else {
          el += ` <div class="efb  emsFormBuilder  row" id="emsFormBuilder-${v.id_}"><label for="${v.id_}" class="efb emsFormBuilder" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label><select id='${id}' name="${v.id_}" class="efb ${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${v.allowMultiSelect == true ? `multiple-emsFormBuilder` : ``} ${v.required == true ? 'require' : ''}" value="${v.name}"  placeholder='${v.tooltip ? v.tooltip : ' Select'}' data-id="${v.id_}"   ${v.allowMultiSelect == true ? 'multiple="multiple" multiple' : ''}>`;
        }

        exportView_emsFormBuilder.push({ id_: v.id_, element: el, step: v.step, amount: v.amount, parents: v.id_, type: 'select', required: req, amount: v.amount });
        break
      case 'option':
        id = v.id ? v.id : v.id_;
        const indx = exportView_emsFormBuilder.findIndex(x => x.parents === v.parents);
        if (indx > -1) {
          req = (exportView_emsFormBuilder[indx].required && exportView_emsFormBuilder[indx].required != undefined) ? exportView_emsFormBuilder[indx].required : false;
          const parent_id = exportView_emsFormBuilder[indx].id_

          const row = valueJson_ws.find(x => x.id_ === parent_id)
          test_view__emsFormBuilder = row.allowMultiSelect == true ? true : false;
          if (exportView_emsFormBuilder[indx].type == "radio" || exportView_emsFormBuilder[indx].type == "checkbox") exportView_emsFormBuilder[indx].element += `<div class="efb row emsFormBuilder"><div class="efb emsFormBuilder_option col-1"><input type="${exportView_emsFormBuilder[indx].type}" id='${id}' name="${v.parents}" class="efb ${v.class ? `${v.class}  emsFormBuilder_v col` : `emsFormBuilder emsFormBuilder_v`} ${req == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}"}></div> <div class="efb col-10 efb emsFormBuilder_option"><label for="${v.parents}" class="efb emsFormBuilder" >${v.name}</label></div></div>`
          if (exportView_emsFormBuilder[indx].type == "select" && test_view__emsFormBuilder == false) exportView_emsFormBuilder[indx].element += `<option  id='${id}' class="efb ${v.class ? `${v.class}` : `emsFormBuilder `} ${req == true ? 'require' : ''}" value="${v.name}" name="${v.parents}" value="${v.name}" data-id="${v.id_}">${v.name}</option>`
          exportView_emsFormBuilder[indx].required = false;
          test_view__emsFormBuilder = true;
        }
        break
    }
  }
  const button_name = form_type_emsFormBuilder != "form" ? efb_var.text[form_type_emsFormBuilder] : efb_var.text.send
  const content = `<!-- commenet --!><div class="efb m-2">
<div class="efb  row d-flex justify-content-center align-items-center">
    <div class="efb col-md-12 efb">
        <div id="emsFormBuilder-form-view" >
        <form id="emsFormBuilder-form-view-id">
            <h1 id="emsFormBuilder-form-view-title" class="efb emsFormBuilder">${efb_var.text.preview}</h1>                
            <div class="efb emsFormBuilder-all-steps-view" id="emsFormBuilder-all-steps-view" ${form_type_emsFormBuilder == "form" ? '' : 'style="display:none;"'}> 
                <span class="efb emsFormBuilder-step-view" id="emsFormBuilder-firstStepIcon-view"><i class="efb fa fa-tachometer"></i></span> 
                <div class="efb emsFormBuilder-addStep-view" id="emsFormBuilder-addStep-view" >
                </div>
                <span class="efb emsFormBuilder-step-view"><i class="efb px-1 fa fa-floppy-o"></i></span> 
            </div>
            <div class="efb emsFormBuilder-all-steps-view" ${form_type_emsFormBuilder == "form" ? '' : 'style="display:none;"'} > 
                <h5 class="efb emsFormBuilder-step-name-view f-setp-name" id ="emsFormBuilder-step-name-view">${efb_var.text.preview}</h5> 
            </div>
            <div id="emsFormBuilder-message-area-view"></div>
            <div class="efb emsFormBuilder-tab-view" id="emsFormBuilder-firstTab-view">
            </div>
            <div  id="emsFormBuilder-tabInfo-view">
            </div>
            <div  id="emsFormBuilder-tabList-view">
            </div>           
            <div class="efb thanks-message text-center" id="emsFormBuilder-text-message-view"> 
                <h3>${efb_var.text.registered}</h3> <span>${efb_var.text.yourInformationRegistered}</span>
            </div>
            <div style="overflow:auto;" id="emsFormBuilder-text-nextprevious-view">
            
            ${valueJson_ws[0].steps > 1 ? ` <div style="float:right;"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="efb emsformbuilder" class="efb mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(-1)"><i class="efb ${efb_var.rtl == 1 ? 'fa fa-angle-double-right' : 'fa fa-angle-double-left'}"></i></button>  <button type="button" id="emsFormBuilder-text-nextBtn-view" class="efb mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(1)"><i class="efb ${efb_var.rtl == 1 ? 'fa fa-angle-double-left' : 'fa fa-angle-double-right'}"></i></button> </div> ` : `<button type="button" id="emsFormBuilder-text-nextBtn-view" class="efb btn btn-lg btn-block mat-shadow btn-type" onclick="emsFormBuilder_nevButton_view(1)">${button_name} </button> </div> `}
                              
            </div>
          </form>      
        </div>
    </div>
</div>
</div>`;
  if (check == 1) { if (document.getElementById('body_emsFormBuilder')) document.getElementById('body_emsFormBuilder').innerHTML = content }
  else { return content }
  if (exportView_emsFormBuilder.length > 0) {
    const steps = valueJson_ws[0].steps;
    const fname = valueJson_ws[0].formName;
    document.getElementById('emsFormBuilder-form-view-title').innerHTML = String(valueJson_ws[0].formName);
    document.getElementById('emsFormBuilder-step-name-view').innerHTML = valueJson_ws[0]['name-1'];
  }

  ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
  createStepsOfPublic()


}


function ShowTab_emsFormBuilder_view(n) {
  var x = document.getElementsByClassName("emsFormBuilder-tab-view");
  if (x[n] == undefined) { return };
  if (x[n]) {
    x[n].style.display = "block";
    x[n].classList.add("fadeInEFB");
  }
  if (document.getElementById("emsFormBuilder-text-prevBtn-view")) {
    if (n == 0 && (n[0] == undefined || n[0])) {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "none";
    } else {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "inline";
    }
  }
  if (n == (x.length - 1)) {
    //document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="efb fa fa-angle-double-right"></i>';
  } else {
    // document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="efb fa fa-angle-double-right"></i>';
  }
  validateForm_fixStepInd_view(n)
}

function emsFormBuilder_nevButton_view(n) {


  if (n != 0) {
    var x = document.getElementsByClassName("emsFormBuilder-tab-view");
    if (n == 1 && !validateForm_emsFormBuilder_view()) return false;
    x[currentTab_emsFormBuilder].style.display = "none";
    currentTab_emsFormBuilder = currentTab_emsFormBuilder + n;
    stepName_emsFormBuilder_view(currentTab_emsFormBuilder);
  }

  if (n == 0) {
    document.getElementById("emsFormBuilder-firstTab-view").style.display = "block";
    document.getElementById("emsFormBuilder-firstTab-view").classList.add = "step";
    document.getElementById("emsFormBuilder-text-nextprevious-view").style.display = "block";
    document.getElementById("emsFormBuilder-all-steps-view").style.display = "";
    document.getElementById("emsFormBuilder-form-view-title").style.display = "block";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "none";
    for (let el of document.querySelectorAll('.finish')) {
      el.classList.remove("finish");
      el.classList.remove("active");
      el.classList.contains('first')
    }
    // endMessage_emsFormBuilder_view()
    currentTab_emsFormBuilder = n;
  }



  if (x && currentTab_emsFormBuilder >= x.length) {
    document.getElementById("emsFormBuilder-text-nextprevious-view").style.display = "none";
    document.getElementById("emsFormBuilder-all-steps-view").style.display = "none";
    document.getElementById("emsFormBuilder-form-view-title").style.display = "none";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "block";
    //endMessage_emsFormBuilder_view()
    if (demo_emsFormBuilder == false) {
      endMessage_emsFormBuilder_view()
    } else {
      document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class="efb px-1 fas fa-thumbs-up faa-bounce animated text-primary"></h1> <h3>${efb_var.text.done}!</br><small>(Demo)</smal><h3>`
    }


  }


  ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
}



function validateForm_emsFormBuilder_view() {
  let x, y, i, valid = true, NotValidCount = 0;
  x = document.getElementsByClassName("emsFormBuilder-tab-view");
  y = x[currentTab_emsFormBuilder].querySelectorAll(".require");
  let value
  try {
    for (const input of x[currentTab_emsFormBuilder].querySelectorAll(".require , .validation")) {
      //require
      const req = input.classList.contains('require');
      
      if (input.tagName == "INPUT") {
        if (input.value == "" && input.classList.contains('require')) {
          input.className += " invalid"; valid = false;
        } else {
          input.classList.remove('invalid');
          value = input.value
        }
        switch (input.type) {
          case 'email':
            req === true ? valid = valid_email_emsFormBuilder(input) : valid = true;
            break;
          case 'tel':
            req === true ? valid = valid_phone_emsFormBuilder(input) : valid = true;
            break;
          case 'password':
            if (input.value.length <= 6) {
              valid = false;
              input.className += ' invalid';
              document.getElementById(`${input.id}-row`).innerHTML += `<small class="efb text-danger" id="${input.id}-message">${efb_var.text.password8Chars}</small>`
            } else {
              input.classList.remove('invalid');
              if (document.getElementById(`${input.id}-message`)) document.getElementById(`${input.id}-message`).remove();
            }
            break;
          case 'url':
            const check = input.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/g);
            
            if (check === null && input.classList.contains('require') == true) {
              valid = false;
              input.className += ' invalid';
              document.getElementById(`${input.id}-row`).innerHTML += `<small class="efb text-danger" id="${input.id}-message">${efb_var.text.enterValidURL}</small>`
            } else {
              valid = true;
              if (document.getElementById(`${input.id}-message`)) document.getElementById(`${input.id}-message`).remove(); input.classList.remove('invalid');
            }
            break;
          case 'file':
            const id = input.id;
            valid = input.files[0] ? true : false;
            break;
        }
      } else if (input.tagName == "LABEL") {

        const ll = document.getElementsByName(input.dataset.id);
        let state = false;

        for (let l of ll) {
          if (l.checked == true) {
            input.classList.remove('invalid');
            state = true;
            value = l.value;
          }
        }
        if (state == false) {
          value = ""
          input.className += " invalid"; valid = false;
        }

      } else if (input.tagName == "SELECT") {
        if (input.value == "") {
          input.className += " invalid"; valid = false;
        } else {
          value = input.value;
        }
      } else if (input.tagName == "DIV") {

      }
      if (valid == false) {
        NotValidCount += 1;
        document.getElementById("emsFormBuilder-message-area-view").innerHTML = alarm_emsFormBuilder(efb_var.text.pleaseFillInRequiredFields);
      }
      if (valid == true && NotValidCount == 0) {
        document.getElementsByClassName("emsFormBuilder-step-view")[currentTab_emsFormBuilder].className += " finish";
        if (document.getElementById("alarm_emsFormBuilder")) document.getElementById("emsFormBuilder-message-area-view").innerHTML = ""
      }
    }
  } catch (re) {

  } finally { }

  return NotValidCount > 0 ? false : true;
}
function validateForm_fixStepInd_view(n) { var i, x = document.getElementsByClassName("emsFormBuilder-step-view"); for (i = 0; i < x.length; i++) { x[i].className = x[i].className.replace(" active", ""); } x[n].className += " active"; }




function createStepsOfPublic() {
  if (valueJson_ws.length == 1 && valueJson_ws == "N" && document.getElementById('emsFormBuilder-form-view')) {
    document.getElementById('emsFormBuilder-form-view').innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb bi-exclamation-triangle-fill text-danger""></i></h1><h3 id="formNotFound">${efb_var.text.formNotFound}</h3> <span>${efb_var.text.errorV01}</span>`;
    return false;
  }

  stepsCountEfb = Number(valueJson_ws[0].steps);
  const addStep = document.getElementById("emsFormBuilder-addStep-view");
  const tabList = document.getElementById("emsFormBuilder-tabList-view");
  const tabInfo = document.getElementById("emsFormBuilder-tabInfo-view");
  if (addStep == undefined) { return };
  if (addStep.hasChildNodes()) {
    while (addStep.hasChildNodes()) {
      addStep.removeChild(addStep.childNodes[0]);
    }
  }

  if (tabList.hasChildNodes()) {
    while (tabList.hasChildNodes()) {
      tabList.removeChild(tabList.childNodes[0]);
    }
  }
  if (tabInfo.hasChildNodes()) {
    while (tabInfo.hasChildNodes()) {
      tabInfo.removeChild(tabInfo.childNodes[0]);
    }
  }


  exportView_emsFormBuilder = exportView_emsFormBuilder.sort(function (a, b) {
    return Number(a.amount) - Number(b.amount);
  })
  //add icons
  for (let i = 1; i <= stepsCountEfb; i++) {
    tags = "";
    icon = 'icon-' + i;
    icon = valueJson_ws[0][icon] ? valueJson_ws[0]['icon-' + i] : 'fa fa-tachometer';
    stepNames_emsFormBuilder[i - 1] = valueJson_ws[0]['name-' + i]
    let id;

    for (let v of exportView_emsFormBuilder) {
      if (Number(v.step) == i) {
        id = Number(v.step) === 1 ? "emsFormBuilder-firstTab-view" : "emsFormBuilder-tabList-view";
        tags += v.type != 'select' ? `${v.element}</div></br>` : `${v.element}</select></div></br>`;
      };

    }

    if (tags != "") document.getElementById(id).innerHTML += id == "emsFormBuilder-firstTab-view" ? tags : `<div class="efb emsFormBuilder-tab-view"> ${tags}</div>`



    i == 1 ? document.getElementById('emsFormBuilder-firstStepIcon-view').innerHTML = `<i class="efb ${icon}"></i>` : document.getElementById('emsFormBuilder-addStep-view').innerHTML += `<span class="efb emsFormBuilder-step-view" id="stepIcon-${i - 1}"><i class="efb ${icon}"></i></span>`

  }//end for


  for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    if (el.type != "submit") {
      el.addEventListener("change", (e) => {
        e.preventDefault();
        let value = ""
        const id_ = el.dataset.id
        const ob = valueJson_ws.find(x => x.id_ === id_);
        if (el.type == "text" || el.type == 'password' || el.type == "color" || el.type == "number" || el.type == "date" || el.type == "url" || el.type == "range" || el.type == "textarea") { value = el.value; }
        else if (el.type == "radio" || el.type == "checkbox") { 
          value = el.value; ob.name = document.getElementById(ob.parents).innerText ;
        }//ob.name = document.getElementById(ob.parents). 
        else if (el.type == "select-one") {
          value = el.value;
        } else if (el.type == "select-multiple") {
          const parents = el.name;
          if (el.classList.contains('multiple-emsFormBuilder') == true) {
            for (let i = 0; i < el.children.length; i++) {
              value += el.children[i].value + ",";
            }
          }
     
          el.addEventListener("change", (e) => {
            handle_change_event_efb(el);
          });


        } else if (el.type == "email") {
          const state = valid_email_emsFormBuilder(el);
          value = state == true ? el.value : '';
        } else if (el.type == "tel") {
          const state = valid_phone_emsFormBuilder(el);
          value = state == true ? el.value : '';
        }



        if (value !== "") {
          const o = [{ id_: id_, name: ob.name, value: value, session: sessionPub_emsFormBuilder }];
          fun_sendBack_emsFormBuilder(o[0], 355);
        }
      });
    } else if (el.type == "submit") {

      el.addEventListener("click", (e) => {
        const id_ = el.dataset.id
        const ob = valueJson_ws.find(x => x.id_ === id_);
        const o = [{ id_: id_, name: ob.name, value: el.value, session: sessionPub_emsFormBuilder }];
        fun_sendBack_emsFormBuilder(o[0]);
      });
    }
  }//end for
}//end function createStepsOfPublic


function fun_sendBack_emsFormBuilder(ob) {
  if(ob.hasOwnProperty('value')){
  ob.value= ob.value.replaceAll("'", "@efb@sq#");
  ob.value= ob.value.replaceAll("`", "@efb@vq#");
  ob.value= ob.value.replaceAll(`"`, "@efb@dq#");
  ob.value= ob.value.replaceAll(`\t`, " ");
  ob.value= ob.value.replaceAll(`\b`, " ");
  ob.value= ob.value.replaceAll(`\r`, "@efb@nq#");
  ob.value= ob.value.replaceAll(`\n`, "@efb@nq#");
  ob.value= ob.value.replaceAll(`\r`, " ");
  }
  if(typeof sendBack_emsFormBuilder_pub=="undefined")return;
  if (sendBack_emsFormBuilder_pub.length>0) {
    let indx = get_row_sendback_by_id_efb(ob.id_);
    
    if (indx != -1 && ob.type != "switch" && (sendBack_emsFormBuilder_pub[indx].type == "checkbox" || sendBack_emsFormBuilder_pub[indx].type == "payCheckbox" || sendBack_emsFormBuilder_pub[indx].type == "multiselect" || sendBack_emsFormBuilder_pub[indx].type == "payMultiselect" )) {
      indx = sendBack_emsFormBuilder_pub.findIndex(x =>x!=null && x.hasOwnProperty('id_')==true && x.id_ === ob.id_ && x.value == ob.value);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub.splice(indx, 1);
    }else if(indx != -1 && ob.value == "@file@" ){
      sendBack_emsFormBuilder_pub[indx]=ob;
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
  sendBack_emsFormBuilder=sendBack_emsFormBuilder_pub;
}
function fun_multiSelectElemnets_emsFormBuilder(ob) { 
  let r = 0
  if (multiSelectElemnets_emsFormBuilder.length > 0) {
    const indx = multiSelectElemnets_emsFormBuilder.findIndex(x => x.parents === ob.parents);
    if (indx !== -1) {
      const map = multiSelectElemnets_emsFormBuilder[indx];
      //  const r= Object.keys(map).find(key => map[key] === true);
      const keys = Object.keys(map);
      let check = 0;
      for (const key of keys) {
        if (ob[key] === map[key]) {
          check = 1;        
        }
        if (check === 1 && key !== 'parents' && map[key] !== undefined && ob[key] !== undefined && map[key] !== ob[key]) {
          multiSelectElemnets_emsFormBuilder[indx] = ob;
          document.getElementById(key).selected
          check = 2;
        }
      }
      if (check == 1) Object.assign(multiSelectElemnets_emsFormBuilder[indx], ob);
      
    } else {
      multiSelectElemnets_emsFormBuilder.push(ob);
    }
  } else {
    multiSelectElemnets_emsFormBuilder.push(ob);
  }
  return r;
}




function saveLocalStorage_emsFormBuilder_view() {
  localStorage.setItem('valueJson_ws', JSON.stringify(valueJson_ws));
  //valueJson_ws ? document.getElementById('button-preview-emsFormBuilder').disabled = false : document.getElementById('button-preview-emsFormBuilder').disabled = true;
}

function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
  <div class="efb emsFormBuilder"><i class="efb bi-exclamation-triangle-fill"></i></div>
    <strong>${efb_var.text.alert} </strong>${val}
  </div>`
}



function endMessage_emsFormBuilder_view() {
  //console.log('fun endMessage_emsFormBuilder_view')
  const stepMax = currentTab_emsFormBuilder + 1;
  let notfilled = []
  for (let i = 1; i <= stepMax; i++) {
    if (-1 == (sendBack_emsFormBuilder.findIndex(x => x.step == i))) notfilled.push(i);
  }
  let countRequired = 0;
  let valueExistsRequired = 0;
  for (let el of exportView_emsFormBuilder) {
    if (el.required == true) {
      const id = el.id_;
      countRequired += 1;
      if (-1 == (sendBack_emsFormBuilder.findIndex(x => x.id_ == id))) valueExistsRequired += 1;
    }
  }

 
  if (countRequired != valueExistsRequired && sendBack_emsFormBuilder.length < 1) {
    let str = ""
    document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb bi-exclamation-triangle-fill text-danger""></i></h1><h3>Failed</h3> <span>${efb_var.text.pleaseMakeSureAllFields}</span>
    <div class="efb display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="efb fa fa-angle-double-left"></i></button></div>`;

    // faild form
  } else {
    document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder "></h1> <h3 class="efb fs-3 text-center"> ${efb_var.text.pleaseWaiting}<h3>`
    actionSendData_emsFormBuilder()
  }
}



function stepName_emsFormBuilder_view(i) {
  document.getElementById('emsFormBuilder-step-name-view').innerHTML = stepNames_emsFormBuilder[i] != "null" && stepNames_emsFormBuilder[i] != undefined ? ` ${stepNames_emsFormBuilder[i]}` : "";

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
    const i =get_row_sendback_by_id_efb(el.dataset.vid);
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


function valid_phone_emsFormBuilder(el) {
  if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove();
  let check = 0;
  const format = /^\s*(?:\+?(\d{1,3}))?([-. (]*(\d{3})[-. )]*)?((\d{3})[-. ]*(\d{2,4})(?:[-.x ]*(\d+))?)\s*$/gm;
  check += el.value.match(format) ? 0 : 1;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className += " invalid";
    document.getElementById(`${el.id}-row`).innerHTML += `<small class="efb text-danger" id="${el.id}-message">${efb_var.text.enterThePhone}</small>`
  }
  else {
    if (document.getElementById("alarm_emsFormBuilder")) {
      el.classList.remove('invalid');

    }
  }
  return check > 0 ? false : true
}


function valid_file_emsFormBuilder(id) {
  let msgEl = document.getElementById(`${id}_-message`);
  msgEl.innerHTML = "";
  msgEl.classList.remove('show');
  document.getElementById(`${id}_`).classList.remove('border-danger');
  let rtrn = true;
  
  let file = ''
  if (true) {
    const f = valueJson_ws.find(x => x.id_ === id);
    file = f.file && f.file.length > 3 ? f.file : 'Zip';
    file = file.toLocaleLowerCase();
  }
  let check = 0;
  
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
  setTimeout(() => {
  if (el.files[0] && el.files[0].size < file_size) {
    const filetype = el.files[0].type.length > 1 && file!='customize'  ? el.files[0].type : el.files[0].name.slice(el.files[0].name.lastIndexOf(".") + 1)
    const r = validExtensions_efb_fun(file, filetype,indx)
    if (r == true) {
      check = +1;
    }
  }
  if (check > 0) {
    msgEl.innerHTML = "";   
      const idB =id+'-prB';
      //console.log(idB);
      const elf = document.getElementById(idB);
      document.getElementById(id+'-prA').classList.remove('d-none');
      if(elf==null) return;
      //console.log('test');
      let pp =0;
      elf.style.width = pp+'%';
      elf.textContent = pp+'% = ' + efb_var.text.preview;
      const x = setInterval(() => {
        pp+=5;
        elf.style.width = pp+'%';
        elf.textContent = pp+'% = ' + efb_var.text.preview;
        if(pp>=100){ 
          clearInterval(x);
          document.getElementById(id+'-prA').classList.add('d-none');
        }
      }, 300);
   


    rtrn = true;
  } else {
    document.getElementById(id+'-prA').classList.add('d-none');
    const f_s_l = val_in.hasOwnProperty('max_fsize') && val_in.max_fsize.length>0 ? val_in.max_fsize : 8;
    const m =efb_var.text.pleaseUploadA.replace('NN', efb_var.text[val_in.file]);
    const size_m = efb_var.text.fileSizeIsTooLarge.replace('NN', f_s_l);
    if (el.files[0] && message.length < 2) message = el.files[0].size < file_size ? m : size_m;
    const newClass = colorTextChangerEfb(msgEl.className, "text-danger");
    newClass!=false ? msgEl.className=newClass:0;
    msgEl.innerHTML = message;
    if(!msgEl.classList.contains('show'))msgEl.classList.add('show');
    
    rtrn = false;
  }
}, 800);
  return rtrn;
}

