//multi step form wizard builder (core)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
// WhiteStudio.team




let exportView_emsFormBuilder = [];
let stepsCount;
let sendBack_emsFormBuilder = []; // این مقدار برگشت داده می شود به سرور
let sessionPub_emsFormBuilder = "reciveFromServer"
let stepNames_emsFormBuilder = [`t`, `Sync`, `Sync`];
let currentTab_emsFormBuilder = 0;
let language_emsFormBuilder ="ar"
let multiSelectElemnets_emsFormBuilder =[] ;
let check_call_emsFormBuilder = 1//  و در حالت پابلیک 1 شود این مقدار توسط سرور داده شود در حالت فراخونی در سمت ادمین صفر شود
let valueJson_ws = [];
let demo_emsFormBuilder=false;
let validate_edit_mode_emsFormBuilder=false; // اگر حالت ادیت بود این تابع فعال شود کاربرد آن زمانی است که بفهمیم که پیش فرض است یا از سرور آمده و اینکه مد نمایش برای مولتی سلکت چیکار کنیم 
let test_view__emsFormBuilder =true // این مقدار در موقع ادیت به طریقی باید به غلط تغییر کند تا نمایش درست انجام شود

jQuery (function() {
    //ajax_object_efm.ajax_url ایجکس ادمین برای برگرداند مقدار لازم می شود
    //ajax_object_efm.ajax_value مقدار جی سون
    //ajax_object_efm.language زبان بر می گرداند
    //console.log("ajax_object_efm_core",ajax_object_efm_core.nonce);
   
    if(typeof ajax_object_efm_core!=undefined){
      if( Number(ajax_object_efm_core.check)==1) { 
        fun_render_view_core_emsFormBuilder(ajax_object_efm_core.check);
        validate_edit_mode_emsFormBuilder=true;
      }
    }
 

});
function fun_render_view_core_emsFormBuilder(check){
  //v2
  // valueJson_ws ? document.getElementById('button-preview-emsFormBuilder').disabled = false : document.getElementById('button-preview-emsFormBuilder').disabled = true;
  exportView_emsFormBuilder =[];
  valueJson_ws =  JSON.parse(localStorage.getItem("valueJson_ws_p"));
  form_type_emsFormBuilder = valueJson_ws && valueJson_ws[0].type ? valueJson_ws[0].type : 'form';
if(valueJson_ws== undefined) valueJson_ws="N";
for (let v of valueJson_ws) {

  let el="";
  let id;
  let req;
  let classData =``;
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
      //console.log(`v.tooltip ${v.tooltip}`);
      id = v.id ? v.id : v.id_;
      req = v.required ? v.required : false;
      //console.log(v.required , "required");
     
      if (v.type=="date") { 
      
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
      else if (v.type=="email" || v.type=="tel" || v.type === "url" || v.type === "password")  classData ="validation";
      el += `<div class="row emsFormBuilder" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v`} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}>`;

      exportView_emsFormBuilder.push({ id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount })
      break;
    case 'file':
      id = v.id ? v.id : v.id_;
      req = v.required ? v.required : false;
      const drog = v.fileDrogAndDrop ? v.fileDrogAndDrop : false; // برای تشخیص اینکه حالت دراگ اند دراپ هست یا نه
      let acception =`.zip,.rar`;
      let typeFile ="Zip"
      if(v.file=="Image"){
        acception =`.png,.jpg,.jpeg`;  
        typeFile= "Photo"; 
      }else if (v.file=="Media"){
        acception =`.mp3,.mp4,.wav,.wav,.AVI,.WebM,.MKV,.FLV`;
        typeFile= v.file; 
      }else if (v.file =="Document"){
        acception =`.xlsx,.xls,.doc,.docx,.ppt, pptx,.pptm,.txt,.pdf,.dotx,.rtf,.odt,.ods,.odp`;
        typeFile= v.file; 
      }else if (v.file =="Zip"){
        acception =`.zip,.rar`;
        typeFile= v.file; 
      }
      classData = drog==true ? "form-control-file text-secondary " : "" ;
      el = ` <div class="row emsFormBuilder ${drog==true ?`inputDnD` :``}" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} accept="${acception}" onchange="valid_file_emsFormBuilder('${id}')" data-id="${v.id_}" ${v.required == true ? 'required' : ''} ${drog==true ?` data-title="${efb_var.text.DragAndDropA} ${typeFile} ${efb_var.text.orClickHere}"`:``}>`
      exportView_emsFormBuilder.push({ id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount })
      break;
    case 'textarea':
      id = v.id ? v.id : v.id_;
      req = v.required ? v.required : false;
      el = `<div class="row emsFormBuilder" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name}  ${v.required == true ? '*' : ''}</label><textarea id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}></textarea>`
      exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount });
      break
    case 'button':
      id = v.id ? v.id : v.id_;
      el = `<div class="row emsFormBuilder" id="${id}-row"> <button  id='${id}' name="${id}" class="${v.class ? `${v.class}  emsFormBuilder_v` : `btn btn-primary emsFormBuilder_v btn-lg btn-block`}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" value="${v.name}">${v.name}</button>`
      exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, amount:v.amount });
      break
    case 'checkbox':
    case 'radiobutton':
      id = v.id ? v.id : v.id_;
      const typ = v.type == "checkbox" ? "checkbox" : "radio";
      req = v.required ? v.required : false;
      //console.log(v.required , "required");
      el = `<div class=" emsFormBuilder"><div class="row"><label for="${v.id_}" id="${v.id_}" class="emsFormBuilder emsFormBuilder-title ${v.required == true ? 'require' : ''}" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label></div>`
      // el = ` <label for="${v.id_}" class="emsFormBuilder" >${v.name}</label><input type="checkbox"  id='${id}' name="${v.id_}" class="${v.class ? `${v.class}  emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}" ${v.required == true ? 'require' : ''}>`
      exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, parents: v.id_, type: typ, required: req, amount:v.amount });
      break
    case 'multiselect':
      id = v.id ? v.id : v.id_;
      req = v.required ? v.required : false;
      //console.log(v.required , "required");

      if(v.allowMultiSelect==true && test_view__emsFormBuilder==true){
        el += el += `<div class="row emsFormBuilder" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name}(Disabled) ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v`} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} placeholder="${efb_var.text.selectOpetionDisabled}" data-id="${v.id_}" disabled>`;
      }else{
        el += ` <div class=" emsFormBuilder  row" id="emsFormBuilder-${v.id_}"><label for="${v.id_}" class="emsFormBuilder" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label><select id='${id}' name="${v.id_}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${v.allowMultiSelect==true ? `multiple-emsFormBuilder`:``} ${v.required == true ? 'require' : ''}" value="${v.name}"  placeholder='${v.tooltip ? v.tooltip : ' Select'}' data-id="${v.id_}"   ${v.allowMultiSelect == true ? 'multiple="multiple" multiple' : ''}>`;
      }
      
      exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, parents: v.id_, type: 'select', required: req, amount:v.amount });
      break
    case 'option':
      id = v.id ? v.id : v.id_;
      const indx = exportView_emsFormBuilder.findIndex(x => x.parents === v.parents);
      
      //console.log(indx > -1 , indx ,"test" ,v.parents ,exportView_emsFormBuilder);
      if (indx > -1){
        req = (exportView_emsFormBuilder[indx].required && exportView_emsFormBuilder[indx].required != undefined )? exportView_emsFormBuilder[indx].required : false;
        //console.log(`req ${req}`, exportView_emsFormBuilder[indx].required, exportView_emsFormBuilder[indx])
        //console.log(indx, exportView_emsFormBuilder[indx]);
       
        const parent_id = exportView_emsFormBuilder[indx].id_
        
        const row =valueJson_ws.find(x => x.id_ ===parent_id)
        test_view__emsFormBuilder= row.allowMultiSelect ==true ? true : false;
        if (exportView_emsFormBuilder[indx].type == "radio" || exportView_emsFormBuilder[indx].type == "checkbox") exportView_emsFormBuilder[indx].element += `<div class="row emsFormBuilder"><div class="emsFormBuilder_option col-1"><input type="${exportView_emsFormBuilder[indx].type}" id='${id}' name="${v.parents}" class="${v.class ? `${v.class}  emsFormBuilder_v col` : `emsFormBuilder emsFormBuilder_v`} ${req == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder="${v.tooltip}"` : ''} data-id="${v.id_}"}></div> <div class="col-10 emsFormBuilder_option"><label for="${v.parents}" class="emsFormBuilder" >${v.name}</label></div></div>`
        if (exportView_emsFormBuilder[indx].type == "select" && test_view__emsFormBuilder==false) exportView_emsFormBuilder[indx].element += `<option  id='${id}' class="${v.class ? `${v.class}` : `emsFormBuilder `} ${req == true ? 'require' : ''}" value="${v.name}" name="${v.parents}" value="${v.name}" data-id="${v.id_}">${v.name}</option>`
        exportView_emsFormBuilder[indx].required = false;
        test_view__emsFormBuilder=true;
      }
      break
  }
}
//console.log(`form_type_emsFormBuilder [${form_type_emsFormBuilder}]`);
//console.log(form_type_emsFormBuilder,efb_var.text[form_type_emsFormBuilder]  )
const button_name = form_type_emsFormBuilder!="form" ? efb_var.text[form_type_emsFormBuilder] : efb_var.text.send
const content = `<!-- commenet --!><div class="m-2">
<div class="row d-flex justify-content-center align-items-center">
    <div class="col-md-12">
        <div id="emsFormBuilder-form-view" >
        <form id="emsFormBuilder-form-view-id">
            <h1 id="emsFormBuilder-form-view-title" class="emsFormBuilder">${efb_var.text.preview}</h1>                
            <div class="emsFormBuilder-all-steps-view" id="emsFormBuilder-all-steps-view" ${form_type_emsFormBuilder=="form" ? '':'style="display:none;"'}> 
                <span class="emsFormBuilder-step-view" id="emsFormBuilder-firstStepIcon-view"><i class="fa fa-tachometer"></i></span> 
                <div class="emsFormBuilder-addStep-view" id="emsFormBuilder-addStep-view" >
                </div>
                <span class="emsFormBuilder-step-view"><i class="px-1 fa fa-floppy-o"></i></span> 
            </div>
            <div class="emsFormBuilder-all-steps-view" ${form_type_emsFormBuilder=="form" ? '':'style="display:none;"'} > 
                <h5 class="emsFormBuilder-step-name-view f-setp-name" id ="emsFormBuilder-step-name-view">${efb_var.text.preview}</h5> 
            </div>
            <div id="emsFormBuilder-message-area-view"></div>
            <div class="emsFormBuilder-tab-view" id="emsFormBuilder-firstTab-view">
            </div>
            <div  id="emsFormBuilder-tabInfo-view">
            </div>
            <div  id="emsFormBuilder-tabList-view">
            </div>           
            <div class="thanks-message text-center" id="emsFormBuilder-text-message-view"> 
                <h3>${efb_var.text.registered}</h3> <span>${efb_var.text.yourInformationRegistered}</span>
            </div>
            <div style="overflow:auto;" id="emsFormBuilder-text-nextprevious-view">
            
            ${valueJson_ws[0].steps>1 ?` <div style="float:right;"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="emsformbuilder" class="mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(-1)"><i class="${ajax_object_efm.rtl==1 ? 'fa fa-angle-double-right' :'fa fa-angle-double-left'}"></i></button>  <button type="button" id="emsFormBuilder-text-nextBtn-view" class="mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(1)"><i class="${ajax_object_efm.rtl==1 ? 'fa fa-angle-double-left' :'fa fa-angle-double-right'}"></i></button> </div> ` :`<button type="button" id="emsFormBuilder-text-nextBtn-view" class="btn btn-lg btn-block mat-shadow btn-type" onclick="emsFormBuilder_nevButton_view(1)">${button_name} </button> </div> ` }
                              
            </div>
          </form>      
        </div>
    </div>
</div>
</div>`;
if (check ==1) {if (document.getElementById('body_emsFormBuilder')) document.getElementById('body_emsFormBuilder').innerHTML =content}
else {return content}



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
  if(x[n]==undefined) {return};
  if (x[n]) {
    x[n].style.display = "block";
    x[n].classList.add("fadeIn");
  }
  //console.log(`x[${x}] x[${n}] x[n][${x[n]}]`);
  if( document.getElementById("emsFormBuilder-text-prevBtn-view")){
    if (n == 0 &&  (n[0]==undefined || n[0])) {
     document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "none";
    } else {
      //console.log(n, n[0]);
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "inline";
    }
  }
  if (n == (x.length - 1)) {
    //document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="fa fa-angle-double-right"></i>';
  } else {
   // document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="fa fa-angle-double-right"></i>';
  }
  validateForm_fixStepInd_view(n)
}

function emsFormBuilder_nevButton_view(n) {


  if (n != 0) {
    var x = document.getElementsByClassName("emsFormBuilder-tab-view");
    //console.log(n)
    if (n == 1 && !validateForm_emsFormBuilder_view()) return false;
    x[currentTab_emsFormBuilder].style.display = "none";
    currentTab_emsFormBuilder = currentTab_emsFormBuilder + n;
    stepName_emsFormBuilder_view(currentTab_emsFormBuilder);
  }

  if (n == 0) {
    //console.log(document.getElementById("emsFormBuilder-firstTab-view").style.display);
    document.getElementById("emsFormBuilder-firstTab-view").style.display = "block";
    document.getElementById("emsFormBuilder-firstTab-view").classList.add = "step";
    document.getElementById("emsFormBuilder-text-nextprevious-view").style.display = "block";
    document.getElementById("emsFormBuilder-all-steps-view").style.display = "";
    document.getElementById("emsFormBuilder-form-view-title").style.display = "block";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "none";
    for (el of document.querySelectorAll('.finish')) {
      //console.log(el, 88)
      el.classList.remove("finish");
      el.classList.remove("active");
      el.classList.contains('first')
    }

    // endMessage_emsFormBuilder_view()
    currentTab_emsFormBuilder = n;
  }

  // این قسمت برای تنظیم که در دراپ زون محتوا قرار دارد یا نه
  // راه حل می توان هر دراپ زون را جدا جدا بررسی کرد یا اینکه قبل از ذخیره سازی دردیتا بیس بررسی شود


  if (x && currentTab_emsFormBuilder >= x.length) {

    document.getElementById("emsFormBuilder-text-nextprevious-view").style.display = "none";
    document.getElementById("emsFormBuilder-all-steps-view").style.display = "none";
    document.getElementById("emsFormBuilder-form-view-title").style.display = "none";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "block";
    //endMessage_emsFormBuilder_view()
    //console.log(`demo_emsFormBuilder[${demo_emsFormBuilder}]`)
    if(demo_emsFormBuilder==false){
      endMessage_emsFormBuilder_view()
    }else{
      document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class="px-1 fas fa-thumbs-up faa-bounce animated text-primary"></h1> <h3>${efb_var.text.done}!</br><small>(Demo)</smal><h3>`
    }


  }


  ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
}



function validateForm_emsFormBuilder_view() {
  let x, y, i, valid = true , NotValidCount=0;
  //console.log('validateForm_emsFormBuilder_view');
  x = document.getElementsByClassName("emsFormBuilder-tab-view");
  y = x[currentTab_emsFormBuilder].querySelectorAll(".require");
  let value
  try {
    for (const input of x[currentTab_emsFormBuilder].querySelectorAll(".require , .validation")) {
      //require
      const req =input.classList.contains('require');
      if (input.tagName == "INPUT") {
        if (input.value == "" && input.classList.contains('require')) {
          input.className += " invalid"; valid = false;
        } else {
          input.classList.remove('invalid');
          value = input.value
        }
        //console.log(input.type , 789999);
        switch(input.type){
          case 'email':
          req===true ? valid= valid_email_emsFormBuilder(input) : valid=true;
            break;
          case 'tel':
            //console.log('707 tel' , req , req===true ,input.classList)
            req===true ? valid= valid_phone_emsFormBuilder(input) : valid=true;
            break;
          case 'password':
            if(input.value.length<=6){
              valid =  false;
              input.className += ' invalid';
              document.getElementById(`${input.id}-row`).innerHTML +=`<small class="text-danger" id="${input.id}-message">${efb_var.text.password8Chars}</small>`
            }else{
              input.classList.remove('invalid');
              if (document.getElementById(`${input.id}-message`)) document.getElementById(`${input.id}-message`).remove();
            }
            break;
          case 'url':
            const check = input.value.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g);
            if (check===null && input.classList.contains('require')==true){
              //console.log(check ,999)
              valid=false;
              input.className += ' invalid';
              document.getElementById(`${input.id}-row`).innerHTML +=`<small class="text-danger" id="${input.id}-message">${efb_var.text.enterValidURL}</small>`
            }else{
              valid=true;
              if (document.getElementById(`${input.id}-message`)) document.getElementById(`${input.id}-message`).remove();  input.classList.remove('invalid');
            }
            break;
          case 'file':
            const id = input.id;
            valid= input.files[0] ? true : false;
            //console.log ( "324 check" ,valid ,input.type ,input.id)
            break;
        }
        //console.log ("324 out check" ,valid,input.type ,input.id , input.classList);
      } else if (input.tagName == "LABEL") {
        
        const ll = document.getElementsByName(input.dataset.id);
        let state = false;

        for (let l of ll) {
          if (l.checked == true) {
            input.classList.remove('invalid');
            state = true;
            value = l.value;
            //console.log('set true', value)
          }
        }
        if (state == false) {
          value = ""
          input.className += " invalid"; valid = false;
        }
        //console.log(input.dataset.id,value , 240);

      } else if (input.tagName == "SELECT") {
        //console.log(input.value, "<----select vae",240)
        if (input.value == "") {
          input.className += " invalid"; valid = false;
        } else {        
          value = input.value;
          //console.log(value, "value select",240)
        }
        //console.log(input.dataset.id,`[${value}]` , 240);
      }
      if (valid== false){
        NotValidCount +=1;
        //console.log('324 valid comer' ,valid ,NotValidCount);
        document.getElementById("emsFormBuilder-message-area-view").innerHTML = alarm_emsFormBuilder(efb_var.text.pleaseFillInRequiredFields);
      }
      if (valid == true && NotValidCount==0) {
        document.getElementsByClassName("emsFormBuilder-step-view")[currentTab_emsFormBuilder].className += " finish";
        if (document.getElementById("alarm_emsFormBuilder")) document.getElementById("emsFormBuilder-message-area-view").innerHTML = ""
      } 
    }
  }catch(re){
  
  }finally{
    //console.log(' 324' ,valid);
    
  }

  return NotValidCount>0 ?false:true;
  /* 
    for (i = 0; i < y.length; i++) {
      if (y[i].value == ""  ) { y[i].className += " invalid"; valid = false; }
    } if (valid) { document.getElementsByClassName("emsFormBuilder-step-view")[currentTab_emsFormBuilder].className += " finish"; } return valid; */
}
function validateForm_fixStepInd_view(n) { var i, x = document.getElementsByClassName("emsFormBuilder-step-view"); for (i = 0; i < x.length; i++) { x[i].className = x[i].className.replace(" active", ""); } x[n].className += " active"; }




function createStepsOfPublic() {
  
  if (valueJson_ws.length==1 && valueJson_ws=="N" && document.getElementById('emsFormBuilder-form-view')){
    //console.log('not found')
    document.getElementById('emsFormBuilder-form-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3 id="formNotFound">${efb_var.text.formNotFound}</h3> <span>${efb_var.text.errorV01}</span>`;
    return false;
  }
 
  stepsCount = Number(valueJson_ws[0].steps);
  const addStep = document.getElementById("emsFormBuilder-addStep-view");
  const tabList = document.getElementById("emsFormBuilder-tabList-view");
  const tabInfo = document.getElementById("emsFormBuilder-tabInfo-view");
  if(addStep==undefined){return};
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
 

  exportView_emsFormBuilder=  exportView_emsFormBuilder.sort(function(a, b) { 
    return a.amount- b.amount;
    })
  //add icons
  for (let i = 1; i <= stepsCount; i++) {

    //console.log(104, i ,stepsCount)
    tags = "";
    icon = 'icon-' + i;
    icon = valueJson_ws[0][icon] ? valueJson_ws[0]['icon-' + i] : 'fa fa-tachometer';
    stepNames_emsFormBuilder[i - 1] = valueJson_ws[0]['name-' + i]
    //console.log(icon);
    let id ;
    
    for (let v of exportView_emsFormBuilder) {
      
      //console.log(v ,Number(v.step) , Number(v.step) == i , i ,stepsCount ,444)
      if (Number(v.step) == i) {
      id = Number(v.step) ===1 ? "emsFormBuilder-firstTab-view" : "emsFormBuilder-tabList-view";
      //console.log(`type:${v.type} ,step:${v.step} ,${typeof v.step} , i:${i}, amount:${v.amount}`,789)
       tags +=  v.type != 'select' ? `${v.element}</div></br>` : `${v.element}</select></div></br>`;
       //console.log(`id[${id}] i${i} step[${v.step}] s==i[${v.step ===i }]`)
      };
      
    }
   
    if (tags!="")  document.getElementById(id).innerHTML += id=="emsFormBuilder-firstTab-view" ? tags : `<div class="emsFormBuilder-tab-view"> ${tags}</div>`
   


    i == 1 ? document.getElementById('emsFormBuilder-firstStepIcon-view').innerHTML = `<i class="${icon}"></i>` : document.getElementById('emsFormBuilder-addStep-view').innerHTML += `<span class="emsFormBuilder-step-view" id="stepIcon-${i - 1}"><i class="${icon}"></i></span>`

  }//end for

/*   for (let v of exportView_emsFormBuilder) {
    const id = v.step == 1 ? "emsFormBuilder-firstTab-view" : "emsFormBuilder-tabList-view";
    //console.log(v.step, currentTab_emsFormBuilder+1, 281)
      document.getElementById(id).innerHTML += v.type != 'select' ? `${v.element}</div></br>` : `${v.element}</select></div></br>`;
    //    //console.log(v.step,id, document.getElementById(id).innerHTML )
  } */

  for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    //console.log(el.type ,7889 ,el.classList.contains('multiple-emsFormBuilder'))
    if (el.type != "submit" ) {
     
    
      el.addEventListener("change", (e) => {
        e.preventDefault();
        //console.log(el.typ ,7788);
        let value =""
        const id_ = el.dataset.id
        const ob = valueJson_ws.find(x => x.id_ === id_);
        if (el.type == "text" || el.type == 'password' || el.type == "color" || el.type == "number" || el.type == "date"  || el.type == "url" || el.type == "range" || el.type=="textarea" ) { value = el.value; }
        else if (el.type == "radio" || el.type == "checkbox") { value = el.value; ob.name =document.getElementById(ob.parents).innerText  }//ob.name = document.getElementById(ob.parents). 
        else if (el.type == "select-one") {
   
              //console.log(el.value,123)
              value =el.value;
                   
        }else if (el.type =="select-multiple"){
          const parents =el.name ;
         
       /*   for (let i = 0; i < el.children.length; i++) {
           
           fun_multiSelectElemnets_emsFormBuilder({parents:parents ,[`${el.children[i].id}`]:el.children[i].selected})
           if(el.children[i].selected) value += el.children[i].value + ',';           
          } */
          if (el.classList.contains('multiple-emsFormBuilder')==true){
           
            for (let i = 0; i < el.children.length; i++) {
            //console.log(el.children[i].value ,7889);
            value += el.children[i].value + ",";
            }
          }


        }else if (el.type == "email") {
          //console.log('email',789);
            
              const state=valid_email_emsFormBuilder(el);
              value = state==true ? el.value :'';
      
        }else if (el.type == "tel") {
            
         
              //console.log('tel',355);
              const state=valid_phone_emsFormBuilder(el);
              value = state==true ? el.value :'';
              //console.log(value,state,355)
        
        }
        
      
        
        if(value!==""){
          //console.log(el ,ob  ,355)
          const o = [{ id_: id_, name: ob.name, value: value, session: sessionPub_emsFormBuilder }];
          fun_sendBack_emsFormBuilder(o[0] ,355);
          //console.log(sendBack_emsFormBuilder, el.type);
        }
      });
    } else if (el.type == "submit") {

      el.addEventListener("click", (e) => {
        //console.log(el, el.value, el.dataset.id)
        const id_ = el.dataset.id
        const ob = valueJson_ws.find(x => x.id_ === id_);
        const o = [{ id_: id_, name: ob.name, value: el.value, session: sessionPub_emsFormBuilder }];
        fun_sendBack_emsFormBuilder(o[0]);
      });
    } /* else if (el.type == "select-one" || el.type =="select-multiple"){
      //789
      // در اینجا مقدار را می توان گرفت برای مولتی سلکت و غیره
      //console.log('selec one',el.id ,el.type)
      const dv = document.getElementById(`emsFormBuilder-${el.id}`);

    }  */
    

    

  }//end for



}//end function createStepsOfPublic


function fun_sendBack_emsFormBuilder(ob) {
   // این تابع آبجکت ارسال به سرور مدیریت می کند
  //console.log(sendBack_emsFormBuilder.length ,ob, ob.id_)
  if (sendBack_emsFormBuilder.length) {
    const indx = sendBack_emsFormBuilder.findIndex(x => x.id_ === ob.id_);
    //console.log(indx ,"indx")
    indx==-1 ? sendBack_emsFormBuilder.push(ob): sendBack_emsFormBuilder[indx] = ob;
  } else {
    sendBack_emsFormBuilder.push(ob);
  }
  //console.log(sendBack_emsFormBuilder);
}
function fun_multiSelectElemnets_emsFormBuilder(ob) { // این تابع آبجکت ارسال به سرور مدیریت می کند
  //console.log(ob ,2223,"first")
  let r=0
  if (multiSelectElemnets_emsFormBuilder.length>0){
    const indx = multiSelectElemnets_emsFormBuilder.findIndex(x => x.parents === ob.parents);
    if(indx!==-1){ 
      const map = multiSelectElemnets_emsFormBuilder[indx];
    //  const r= Object.keys(map).find(key => map[key] === true);
      //console.log(`map`,map,223);
      const keys = Object.keys(map);
      let check = 0;
      for (const key  of keys){

        if (ob[key]===map[key]){
          check=1;
          // اگر کی ورودی با مقدار مولتی سلکت یکی بود وضعیت تغییر کند اگر نبود اضافه شود به لیست
          // اگر یکی بود اون آپشن آن سلکت بشه
        }
        if(check===1 && key!=='parents' && map[key] !== undefined  && ob[key] !== undefined && map[key]!==ob[key] ){
        
          //console.log( multiSelectElemnets_emsFormBuilder[indx] ,ob ,22233 )
          multiSelectElemnets_emsFormBuilder[indx]=ob;
          //console.log( multiSelectElemnets_emsFormBuilder[indx] ,ob , key ,22233 , "result" );
          document.getElementById(key).selected
          check=2;
          //console.log(multiSelectElemnets_emsFormBuilder,22233)
        //  if (map[key])
        }
        
     //   if (keu)
      }
      if (check==1 )  Object.assign(multiSelectElemnets_emsFormBuilder[indx], ob);
      //console.log(multiSelectElemnets_emsFormBuilder[indx],22233 )
      // بررسی شود اگر مقدار انتخاب شده بود
    }else{
      multiSelectElemnets_emsFormBuilder.push(ob);
    }
  }else{
    multiSelectElemnets_emsFormBuilder.push(ob);
  } 
  //console.log(multiSelectElemnets_emsFormBuilder,223 ,'fine')
  return r;
}




function saveLocalStorage_emsFormBuilder_view() {
  ////console.log('save!')
  localStorage.setItem('valueJson_ws', JSON.stringify(valueJson_ws));
  //valueJson_ws ? document.getElementById('button-preview-emsFormBuilder').disabled = false : document.getElementById('button-preview-emsFormBuilder').disabled = true;
}

function alarm_emsFormBuilder(val) {
  return `<div class="alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
  <div class="emsFormBuilder"><i class="fas fa-exclamation-triangle faa-flash animated"></i></div>
    <strong>${efb_var.text.alert} </strong>${val}
  </div>`
}



function loading_emsFormBuilder() {
  return `<div  id="loading_emsFormBuilder"><div class="spinner-border text-warning" role="status">
    <span class="sr-only">${efb_var.text.loading}...</span>
  </div></div>`

}

function showloading_emsFormBuilder() {
  const stepMax =currentTab_emsFormBuilder+1;
  const time = stepMax < 3 ? 700 : stepMax * 200;
  //console.log(Date(), time);
  //document.getElementById(`body`).innerHTML+=loading_emsFormBuilder();
  document.getElementById("body").classList.add = "wait";
  if (body.style.pointerEvents == "none") body.style.pointerEvents = "auto";
  else body.style.pointerEvents = "none";
  setTimeout(() => {
    document.getElementById("body").classList.remove = "wait";
    if (body.style.pointerEvents == "none") body.style.pointerEvents = "auto";
    else body.style.pointerEvents = "none";
    //console.log(Date());
  }, time);
}


function endMessage_emsFormBuilder_view() {
  const stepMax =currentTab_emsFormBuilder+1;
  let notfilled = []
  //console.log(sendBack_emsFormBuilder)
  for (i = 1; i <= stepMax; i++) {
    if (-1 == (sendBack_emsFormBuilder.findIndex(x => x.step == i))) notfilled.push(i);
  }
  let countRequired =0;
  let valueExistsRequired =0;
  for(let el of exportView_emsFormBuilder){
    if (el.required==true){
      const id = el.id_;
      countRequired +=1;
      if (-1 == (sendBack_emsFormBuilder.findIndex(x => x.id_ == id))) valueExistsRequired+=1;
    }
  }
  //console.log(notfilled.length)
  if (countRequired!=valueExistsRequired && sendBack_emsFormBuilder.length<1 ) {
    //console.log(notfilled ,sendBack_emsFormBuilder,exportView_emsFormBuilder ,countRequired,valueExistsRequired)
    let str = ""

    
    document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3>Failed</h3> <span>${efb_var.text.pleaseMakeSureAllFields}</span>
    <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
    
    // faild form
  } else {
    document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class="fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3> ${efb_var.text.pleaseWaiting}<h3>`
    actionSendData_emsFormBuilder()
    //  Ok form

  }



}



function stepName_emsFormBuilder_view(i) {
  document.getElementById('emsFormBuilder-step-name-view').innerHTML = stepNames_emsFormBuilder[i] != "null" && stepNames_emsFormBuilder[i] != undefined ? ` ${stepNames_emsFormBuilder[i]}` : "";
  //console.log(stepNames_emsFormBuilder[i] != "null", i)
}



function valid_email_emsFormBuilder(el) {
  
  if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove(); 
  let check =0;
  const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    
    check += el.value.match(format) ?0 :1;
    el.value.match(format) ? 0: el.className += " invalid";
    if (check>0){
       document.getElementById(`${el.id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">${efb_var.text.enterTheEmail}</small>`
      }
      else {
        if (document.getElementById("alarm_emsFormBuilder")) {          
          el.classList.remove('invalid');
          if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove();
        }
      }
   // if (check>0) alert("Please enter email address");
  return check>0 ? false : true
}


function valid_phone_emsFormBuilder(el) {
  if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove(); 
  let check =0;
  const format =/^\s*(?:\+?(\d{1,3}))?([-. (]*(\d{3})[-. )]*)?((\d{3})[-. ]*(\d{2,4})(?:[-.x ]*(\d+))?)\s*$/gm;
    //console.log(el)
    check += el.value.match(format) ?0 :1;
    //console.log( 707,el.classList.contains('require'),)
    if (check>0 ){
      el.value.match(format) ? 0: el.className += " invalid";
       document.getElementById(`${el.id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">${efb_var.text.enterThePhone}</small>`
      }
      else {
        if (document.getElementById("alarm_emsFormBuilder")){
          el.classList.remove('invalid');
               
        } 
      }
   // if (check>0) alert("Please enter email address");
   return check>0 ? false : true
}


function valid_file_emsFormBuilder(id){
  //789
  // اینجا ولیدیت کردن فایل های بزرگ مشکل دارد
  // بعد از بارگزاری و تغییر آن به فایل کوجک جواب نمی ده
  // روی تست ولیدت را تست کن ببین مشکل از کجاست
  
  if (document.getElementById(`${id}-message`)) document.getElementById(`${id}-message`).remove(); 
  let file =''
  if( true) {
    const f = valueJson_ws.find(x => x.id_ === id);  
    file = f.file;
  }
  //console.log('file',file)  
  let check =0;
  let rtrn = false;
  let fileName =''
  const el = document.getElementById(id);
  let accept = el.accept.split(",");

if(el.files[0] && el.files[0].size<15000000){
  for(ac of accept){
    //validition of type file
  const r = el.files[0].type.indexOf(ac.slice(1, ac.length))
  if(r!=-1) check =+1;
  
  }
}
 if (check>0){
  let reader = new FileReader();
  
    el.setAttribute("data-title", el.files[0].name);
   el.classList.remove('text-warning');
   el.classList.add('text-secondary');
   if (document.getElementById(`${id}-message`)) document.getElementById(`${id}-message`).remove();     
  
   rtrn=true;
 }else{



   let message = `${efb_var.text.pleaseUploadA} ${file}`;
   el.classList.add('text-warning');
   el.classList.remove('text-secondary');
   if(el.files[0]) message=  el.files[0].size<15000000? `Please upload the ${file} file (${accept.join()})` : `The ${file} size is too large, maximum size of a file is 15MB. Try new ${file} file`;
   el.setAttribute("data-title", message);
   document.getElementById(`${id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">${message}</small>`;
  
   rtrn=false;
 }

 return rtrn;
}



/*  $(document).ready(function() {

}); */


















