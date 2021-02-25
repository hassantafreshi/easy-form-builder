//console.log("core.js")

let exportView_emsFormBuilder = [];
let stepsCount;
let sendBack_emsFormBuilder_pub = []; // این مقدار برگشت داده می شود به سرور
let sessionPub_emsFormBuilder = "reciveFromServer"
let stepNames_emsFormBuilder = [`t`, `Sync`, `Sync`];
let currentTab_emsFormBuilder = 0;
let language_emsFormBuilder ="ar"
let multiSelectElemnets_emsFormBuilder =[] ;
let check_call_emsFormBuilder = 1//  و در حالت پابلیک 1 شود این مقدار توسط سرور داده شود در حالت فراخونی در سمت ادمین صفر شود
let formName =""
let files_emsFormBuilder =[];
let sitekye_emsFormBuilder =""
let trackingCode_state_emsFormBuilder =""
let recaptcha_emsFormBuilder;
let poster_emsFormBuilder ='';
const fileSizeLimite_emsFormBuilder =8300000;
let form_type_emsFormBuilder='form';
//exportView_emsFormBuilder مقدار المان ها را در خود نگه می دارد
//sendBack_emsFormBuilder_pub مقدار فرم پر شده توسط کاربر در خود نگه می دارد
let valueJson_ws = []

jQuery (function() {
  //789 امنیت باید اضافه شود به این قسمت
   
    //ajax_object_efm.ajax_url ایجکس ادمین برای برگرداند مقدار لازم می شود
    //ajax_object_efm.ajax_value مقدار جی سون
    //ajax_object_efm.language زبان بر می گرداند
    //console.log("ajax_object_efm_state",ajax_object_efm);
    //console.log("ajax_object_efm.ajax_url",ajax_object_efm.ajax_url);
    //console.log("ajax_object_efm.nonce",ajax_object_efm.nonce);
    //console.log("ajax_object_efm_state_2",ajax_object_efm.state);
    poster_emsFormBuilder =ajax_object_efm.poster;
    //console.log("poster_emsFormBuilder",ajax_object_efm);
    console.log(ajax_object_efm,'return');
    if(ajax_object_efm.form_setting && ajax_object_efm.form_setting.length>0 && ajax_object_efm.form_setting!=="setting was not added" ){
      
      const vs=JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, ''));
      form_type_emsFormBuilder=ajax_object_efm.type;
    
      sitekye_emsFormBuilder =vs.siteKey;
      trackingCode_state_emsFormBuilder =vs.trackingCode;
      
    }


    if((sitekye_emsFormBuilder!==null && sitekye_emsFormBuilder.length>0) && ajax_object_efm.state!=='settingError' ){
 
      if(ajax_object_efm.state=='form'){
        //console.log("id",ajax_object_efm.id);
        fun_render_view(ajax_object_efm.ajax_value,1);
      }else if (ajax_object_efm.state=='tracker'){
        //console.log("tracker");
        fun_tracking_show_emsFormBuilder()
      }else if(ajax_object_efm.state=='settingError'){
        //console.log("settingError");
        fun_show_alert_setting_emsFormBuilder()
      }
    }else{
      fun_show_alert_setting_emsFormBuilder()
    }


});

function fun_render_view(val,check){
  
    exportView_emsFormBuilder =[];
    valueJson_ws=JSON.parse(val.replace(/[\\]/g, ''));
  // const vs=ajax_object_efm.form_setting.setting;
   //console.log('ajax_object_efm',sitekye_emsFormBuilder,trackingCode_state_emsFormBuilder)
    //console.log(valueJson_ws);
    if(valueJson_ws== undefined) {valueJson_ws="N"; return 0;}
    formName = valueJson_ws[0].formName
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
          id = v.id ? v.id : v.id_;
          req = v.required ? v.required : false;
          //console.log(v.required , "required");
         
          if (v.type=="date") { 
            //console.log(v ,231)
           if (v.clander=="Persian" || v.clander=="Arabic") {
              v.type="text";
             if (v.clander=="Arabic"){
               classData="hijri-picker" 
               el =` <link href="bootstrap-datetimepicker-ar.css" rel="stylesheet" />`
              }else{
                 classData="jalali-picker";
              }
            };
          }
          else if (v.type=="email" || v.type=="tel" || v.type === "url" || v.type === "password")  classData ="validation";
          el += `<div class="row emsFormBuilder" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v`} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder=${v.tooltip}` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}>`;
          if (v.clander=="Persian" || v.clander=="Arabic") {
            el +=`    
            <script>
            
            $(function () {
    
              initDefault();
    
          });
    
          function initDefault() {
           
              $(".hijri-picker").hijriDatePicker({
                  hijri:true,
                  showSwitcher:false
              });
          }
          </script>`;
          }
    
          exportView_emsFormBuilder.push({ id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount })
          break;
        case 'file':
          id = v.id ? v.id : v.id_;
          req = v.required ? v.required : false;
          const drog = v.fileDrogAndDrop ? v.fileDrogAndDrop : false; // برای تشخیص اینکه حالت دراگ اند دراپ هست یا نه
          let acception =`.zip`;
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
          el = ` <div class="row emsFormBuilder ${drog==true ?`inputDnD` :``}" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name} ${v.required == true ? '*' : ''}</label><input type="${v.type}"  id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${classData} ${v.required == true ? 'require' : ``}"  ${v.required == true ? 'require' : ''} ${v.tooltip ? `placeholder=${v.tooltip}` : ''} accept="${acception}" onchange="valid_file_emsFormBuilder('${id}')" data-id="${v.id_}" ${v.required == true ? 'required' : ''} ${drog==true ?` data-title="Drag and drop a ${typeFile} or click here"`:``}>`
          exportView_emsFormBuilder.push({ id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount })
          break;
        case 'textarea':
          id = v.id ? v.id : v.id_;
          req = v.required ? v.required : false;
          el = `<div class="row emsFormBuilder" id="${id}-row"> <label for="${id}" class="emsFormBuilder" >${v.name}  ${v.required == true ? '*' : ''}</label><textarea id='${id}' name="${id}" class="${v.class ? `${v.class} emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" ${v.tooltip ? `placeholder=${v.tooltip}` : ''} data-id="${v.id_}" ${v.required == true ? 'required' : ''}></textarea>`
          exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, required: req, amount:v.amount });
          break
        case 'button':
          id = v.id ? v.id : v.id_;
          el = `<div class="row emsFormBuilder" id="${id}-row"> <button  id='${id}' name="${id}" class="${v.class ? `${v.class}  emsFormBuilder_v` : `btn btn-primary emsFormBuilder_v btn-lg btn-block`}" ${v.tooltip ? `placeholder=${v.tooltip}` : ''} data-id="${v.id_}" value="${v.name}">${v.name}</button>`
          exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, type: v.type, amount:v.amount });
          break
        case 'checkbox':
        case 'radiobutton':
          id = v.id ? v.id : v.id_;
          const typ = v.type == "checkbox" ? "checkbox" : "radio";
          req = v.required ? v.required : false;
          //console.log(v.required , "required");
          el = `<div class=" emsFormBuilder"><div class="row"><label for="${v.id_}" id="${v.id_}" class="emsFormBuilder emsFormBuilder-title ${v.required == true ? 'require' : ''}" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label></div>`
          // el = ` <label for="${v.id_}" class="emsFormBuilder" >${v.name}</label><input type="checkbox"  id='${id}' name="${v.id_}" class="${v.class ? `${v.class}  emsFormBuilder_v` : `emsFormBuilder emsFormBuilder_v`} ${v.required == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder=${v.tooltip}` : ''} data-id="${v.id_}" ${v.required == true ? 'require' : ''}>`
          exportView_emsFormBuilder.push({id_:v.id_, element: el, step: v.step, amount: v.amount, parents: v.id_, type: typ, required: req, amount:v.amount });
          break
        case 'multiselect':
          id = v.id ? v.id : v.id_;
          req = v.required ? v.required : false;
          //console.log(v.required , "required");
     
          el += ` <div class=" emsFormBuilder  row" id="emsFormBuilder-${v.id_}"><label for="${v.id_}" class="emsFormBuilder" data-id="${v.id_}" >${v.name}  ${v.required == true ? '*' : ''}</label><select id='${id}' name="${v.id_}" class="${v.class ? `${v.class} emsFormBuilder_v ` : `emsFormBuilder emsFormBuilder_v `} ${v.allowMultiSelect==true ? `multiple-emsFormBuilder`:``} ${v.required == true ? 'require' : ''}" value="${v.name}"  placeholder='${v.tooltip ? v.tooltip : ' Select'}' data-id="${v.id_}"   ${v.allowMultiSelect == true ? 'multiple="multiple" multiple' : ''}>`
          
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
            if (exportView_emsFormBuilder[indx].type == "radio" || exportView_emsFormBuilder[indx].type == "checkbox") exportView_emsFormBuilder[indx].element += `<div class="row emsFormBuilder"><div class="emsFormBuilder_option col-1"><input type="${exportView_emsFormBuilder[indx].type}" id='${id}' name="${v.parents}" class="${v.class ? `${v.class}  emsFormBuilder_v col` : `emsFormBuilder emsFormBuilder_v`} ${req == true ? 'require' : ''}" value="${v.name}" ${v.tooltip ? `placeholder=${v.tooltip}` : ''} data-id="${v.id_}"}></div> <div class="col-4 emsFormBuilder_option"><label for="${v.parents}" class="emsFormBuilder" >${v.name}</label></div></div>`
            if (exportView_emsFormBuilder[indx].type == "select") exportView_emsFormBuilder[indx].element += `<option  id='${id}' class="${v.class ? `${v.class}` : `emsFormBuilder `} ${req == true ? 'require' : ''}" value="${v.name}" name="${v.parents}" value="${v.name}" data-id="${v.id_}">${v.name}</option>`
            exportView_emsFormBuilder[indx].required = false;
          }
          break
      }
    }
    const content = `<!-- commenet --!><div class="m-2">
    <div class="row d-flex justify-content-center align-items-center">
        <div class="col-md-12">
            <div id="emsFormBuilder-form-view" >
            <form id="emsFormBuilder-form-view-id">
                <h1 class='emsFormBuilder' id="emsFormBuilder-form-view-title">Form Bulider</h1>                
                <div class="emsFormBuilder-all-steps-view" id="emsFormBuilder-all-steps-view"> 
                    <span class="emsFormBuilder-step-view" id="emsFormBuilder-firstStepIcon-view"><i class="fa fa-tachometer"></i></span> 
                    <div class="emsFormBuilder-addStep-view" id="emsFormBuilder-addStep-view" >
                    </div>
                    <span class="emsFormBuilder-step-view"><i class="fa fa-floppy-o"></i></span> 
                </div>
                <div class="emsFormBuilder-all-steps-view" > 
                    <h5 class="emsFormBuilder-step-name-view f-setp-name" id ="emsFormBuilder-step-name-view">Define</h5> 
                </div>
                <div id="emsFormBuilder-message-area-view"></div>
                <div class="emsFormBuilder-tab-view" id="emsFormBuilder-firstTab-view">
                </div>
                <div  id="emsFormBuilder-tabInfo-view">
                </div>
                <div  id="emsFormBuilder-tabList-view">
                </div>           
                <div class="thanks-message text-center" id="emsFormBuilder-text-message-view"> 
                    <h3>Registered</h3> <span>Great, Your information registered successfully</span>
                </div>
                <div style="overflow:auto;" id="emsFormBuilder-text-nextprevious-view">
                <!-- recaptcha  -->
                <div class="g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}"></div>
                <!-- recaptcha end  -->
                    <div style="float:right;"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(-1)"><i class="fa fa-angle-double-left"></i></button> 
                    <button type="button" id="emsFormBuilder-text-nextBtn-view" class="mat-shadow emsFormBuilder" onclick="emsFormBuilder_nevButton_view(1)"><i class="fa fa-angle-double-right"></i></button> </div>                  
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
      //console.log(steps, fname)
    
      document.getElementById('emsFormBuilder-form-view-title').innerHTML = String(valueJson_ws[0].formName);
      document.getElementById('emsFormBuilder-step-name-view').innerHTML = valueJson_ws[0]['name-1'];
      //emsFormBuilder-all-steps-view
      ////console.log(document.getElementById('emsFormBuilder-form-view-title').value)
    
    
    
    }

    ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
    createStepsOfPublic()
}



/*  */




function ShowTab_emsFormBuilder_view(n) {
    var x = document.getElementsByClassName("emsFormBuilder-tab-view");
    if (x[n]) {
      x[n].style.display = "block";
      x[n].classList.add("fadeIn");
    }
    ////console.log(x,n,x[n],"check")
    if (n == 0) {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "none";
    } else {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "inline";
    }
    if (n == (x.length - 1)) {
      document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="fa fa-angle-double-right"></i>';
    } else {
      document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = '<i class="fa fa-angle-double-right"></i>';
    }
    validateForm_fixStepInd_view(n)
  }
  
  function emsFormBuilder_nevButton_view(n) {
  //recaptcha
  if(currentTab_emsFormBuilder==0){
    var response = grecaptcha.getResponse();
    if(response.length == 0) { 
      //reCaptcha not verified
     // alert("no pass"); 
      return ;
    }
    else { 
      //reCaptch verified
      recaptcha_emsFormBuilder=response;
     // alert("pass"); 
      //console.log(response);
    }
  }
  //recaptcha
  
    if (n != 0) {
      var x = document.getElementsByClassName("emsFormBuilder-tab-view");
      ////console.log(n)
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
      endMessage_emsFormBuilder_view()
  
  
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
                document.getElementById(`${input.id}-row`).innerHTML +=`<small class="text-danger" id="${input.id}-message">Password must be of minimum 8 characters</small>`
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
                document.getElementById(`${input.id}-row`).innerHTML +=`<small class="text-danger" id="${input.id}-message">Please enter a valid URL. Protocol is required (http://, https://)</small>`
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
          document.getElementById("emsFormBuilder-message-area-view").innerHTML = alarm_emsFormBuilder(`Please fill in all required fields..`);
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
      document.getElementById('emsFormBuilder-form-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3 id="formNotFound">Form not found</h3> <span>Error Code:V01</span>`;
      return false;
    }
   
    stepsCount = Number(valueJson_ws[0].steps);
    const addStep = document.getElementById("emsFormBuilder-addStep-view");
    const tabList = document.getElementById("emsFormBuilder-tabList-view");
    const tabInfo = document.getElementById("emsFormBuilder-tabInfo-view");
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
  
  
    for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
      //console.log(el.type ,7889 ,el.classList.contains('multiple-emsFormBuilder'))
      if (el.type != "submit" ) {
       
        if( el.type =="file"){
          const ob = valueJson_ws.find(x => x.id_ ===  el.dataset.id);
          files_emsFormBuilder.push ({id:el.id ,value:"@file@", state:0 , url:"" ,type:"" , name:ob.name, session: sessionPub_emsFormBuilder});
          //console.log(files_emsFormBuilder);
        }
        el.addEventListener("change", (e) => {
          e.preventDefault();
          const ob = valueJson_ws.find(x => x.id_ === el.dataset.id);
          //console.log(el.type ,"form type");
          let value =""
          const id_ = el.dataset.id
          
          if (el.type == "text" || el.type == 'password' || el.type == "color" || el.type == "number" || el.type == "date"  || el.type == "url" || el.type == "range" || el.type=="textarea" ) { value = el.value; }
           else if (el.type == "radio" || el.type == "checkbox") { value = el.value; ob.name =document.getElementById(ob.parents).innerText  }//ob.name = document.getElementById(ob.parents). 
           else if (el.type == "select-one") {
     
                //console.log(el.value,123)
                value =el.value;
                     
          }else if (el.type =="select-multiple"){
            const parents =el.name ;
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
                //console.log(value,state,355);          
          }
          
          
       
          
    
          
        
          
          if(value!==""){
            //console.log(el ,ob  ,355)
            const o = [{ id_: id_, name: ob.name, value: value, session: sessionPub_emsFormBuilder }];
            fun_sendBack_emsFormBuilder(o[0] ,355);
            //console.log(sendBack_emsFormBuilder_pub, el.type);
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
  
  
  function fun_sendBack_emsFormBuilder(ob) { // این تابع آبجکت ارسال به سرور مدیریت می کند
    //console.log(sendBack_emsFormBuilder_pub.length ,ob, ob.id_)
    if (sendBack_emsFormBuilder_pub.length) {
      const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_);
      //console.log(indx ,"indx")
      indx==-1 ? sendBack_emsFormBuilder_pub.push(ob): sendBack_emsFormBuilder_pub[indx] = ob;
    } else {
      sendBack_emsFormBuilder_pub.push(ob);
    }
    //console.log(sendBack_emsFormBuilder_pub);
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
    //////console.log('save!')
    localStorage.setItem('valueJson_ws', JSON.stringify(valueJson_ws));
  }
  
  function alarm_emsFormBuilder(val) {
    return `<div class="alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
      <div><i class="fas fa-exclamation-triangle faa-flash animated"></i></div>
      <strong>Alert! </strong>${val}
    </div>`
  }
  
  

  function showloading_emsFormBuilder() {
    const stepMax =currentTab_emsFormBuilder+1;
    const time = stepMax < 3 ? 700 : stepMax * 200;
    //console.log(Date(), time);
  
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
    let counter =0;
    const stepMax =currentTab_emsFormBuilder+1;
    let notfilled = []
    //console.log(sendBack_emsFormBuilder_pub)
    for (i = 1; i <= stepMax; i++) {
      if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x.step == i))) notfilled.push(i);
    }
    let countRequired =0;
    let valueExistsRequired =0;
    for(let el of exportView_emsFormBuilder){
      if (el.required==true){
        const id = el.id_;
        countRequired +=1;
        if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == id))) valueExistsRequired+=1;
      }
    }
    //console.log(notfilled.length)
    if (countRequired!=valueExistsRequired && sendBack_emsFormBuilder_pub.length<1 ) {
      //console.log(notfilled ,sendBack_emsFormBuilder_pub,exportView_emsFormBuilder ,countRequired,valueExistsRequired)
      let str = ""
  
      
      document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3>Failed</h3> <span>Please check all filled</span>
      <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
      
      // faild form
    } else {
      document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class="fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3>Please Waiting<h3>`
    //setInterval(myFunction, 2000);
   
    let checkFile = 0; 
    for (file of files_emsFormBuilder){
      if (files_emsFormBuilder.length>0 && file.state == 1 )  {checkFile += 1;
      }else if( files_emsFormBuilder.length>0 && file.state == 3 ) { 
        //console.log(`error => ${file.state}`,file)
        checkFile =-100;
        document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3 class="font-weight-bold">File Error</h3> <span class="font-weight-bold">You don't have permission to upload this file:</br>${file.url}</span>
        <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
       return;

      }
     
    }
    if (checkFile==0){
      if(files_emsFormBuilder.length>0){
        //console.log("files upload" , sendBack_emsFormBuilder_pub );
        for(const file of files_emsFormBuilder){ sendBack_emsFormBuilder_pub.push(file);}
        
        //console.log("files upload" , sendBack_emsFormBuilder_pub ,files_emsFormBuilder.length);
      }
      actionSendData_emsFormBuilder()
    }else{
       const timeValue = setInterval(function() {
         //بررسی می کند همه فایل ها آپلود شده اند یا نه اگر آپلود شده باشند دیگه اجرا نمی شود و فایل ها اضافه می  شوند
         let checkFile = 0;
         for (file of files_emsFormBuilder){
           //console.log(`files_emsFormBuilder check ${file.state == 3} `,file)
           if (files_emsFormBuilder.length>0 && file.state == 1 )  {checkFile += 1;
            }else if( files_emsFormBuilder.length>0 && file.state == 3 ) { 
              //console.log(`error => ${file.state}`,file)
              checkFile =-100;
              document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3>File Error</h3> <span>You don't have permission to upload this file </br>${file.url}</span>
              <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
             return;

            }
          }
         //console.log("waiting for files upload",checkFile);
         if (checkFile==0){
           // اگر همه فایل ها آپلود شده بودن
           //intervalFiles
           for(const file of files_emsFormBuilder){ sendBack_emsFormBuilder_pub.push(file);}
           actionSendData_emsFormBuilder()
           clearInterval(timeValue);
         }
      }, 1500);

    }
      //  Ok form
  
    }
  
  
  
  }
  

  
  function stepName_emsFormBuilder_view(i) {
    document.getElementById('emsFormBuilder-step-name-view').innerHTML = stepNames_emsFormBuilder[i] != "null" && stepNames_emsFormBuilder[i] != undefined ? ` ${stepNames_emsFormBuilder[i]}` : "";
    //console.log(stepNames_emsFormBuilder[i] != "null", i)
  }
  
  
  function actionSendData_emsFormBuilder() {
    localStorage.setItem('sendback'  ,JSON.stringify(sendBack_emsFormBuilder_pub));
    $(function () {
     
      data = {
        action: "get_form_Emsfb",
        value: JSON.stringify(sendBack_emsFormBuilder_pub),
        name: formName,
        id:ajax_object_efm.id.id,
        valid:recaptcha_emsFormBuilder,
        type:form_type_emsFormBuilder,
       // type:'loginlogin',
        nonce:ajax_object_efm.nonce       
      };
  
      $.post(ajax_object_efm.ajax_url, data, function (res) {
       
         if (res.data.success==true) {
           //console.log(res,localStorage.getItem("sendback"))
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-thumbs-up faa-bounce animated text-primary""></i></h1><h1 class='emsFormBuilder'>Sent successfully</h1></br> <span>Thanks for filling out our form!</span></br></br></h3> ${trackingCode_state_emsFormBuilder=="true" ? `<h4><span> Tracking number:</span><span><b>${res.data.track}</b></span></h4>` : ""}`;
        } else {
         
          //console.log(`res : error`)
  
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3>Form Error</h3> <span>Something went wrong please try again, <br>Error Code:${res.data.m}</span>
          <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="emsFormBuilder_nevButton_view(0)" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
  
        } 
      })
    });
    scrolldiv_emsFormBuilder('emsFormBuilder-form-view');
  }
  
  
  
  
  function valid_email_emsFormBuilder(el) {
    if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove(); 
    let check =0;
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
      //console.log(el)
      check += el.value.match(format) ?0 :1;
      el.value.match(format) ? 0: el.className += " invalid";
      if (check>0){
         document.getElementById(`${el.id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">Please Enter Email Address</small>`
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
         document.getElementById(`${el.id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">Please Enter Phone Number</small>`
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
      file = f.file && f.file.length>3 ? f.file :'Zip' ;
      //console.log(`file type`,file)
    }
   
    let check =0;
    let rtrn = false;
    let fileName =''
    const el = document.getElementById(id);
    let accept = el.accept.split(",");
    if(file ==="Document"){      
      accept.push('msword');
      accept.push('ms-excel');
      accept.push('ms-powerpoint');
    } else if(file ==="Media"){      
      accept.push(' audio/mpeg');
      accept.push(' audio/');
      accept.push(' video/');
    }
  
  if(el.files[0] && el.files[0].size<fileSizeLimite_emsFormBuilder){
    //console.log('file',`file type[${el.files[0].type}] ,[${el.files[0].size}]`)  
    for(ac of accept){
      //validition of type file
      const r = el.files[0].type.indexOf(ac.slice(1, ac.length))
      //console.log(`r ${r} , file type[${el.files[0].type}]` ,ac);
      
    if(r!=-1) check =+1;
    
    }
   // //console.log(`document.getElementById(\${el.id}-message).innerHTML.length ${document.getElementById(`${el.id}-message`).innerHTML.length}`);
   document.getElementById(`${el.id}-message`) && document.getElementById(`${el.id}-message`).innerHTML.length>5 ? document.getElementById(`${id}-row`).innerHTML="" :0;
  }
    
   if (check>0){
    
    
     el.setAttribute("data-title", el.files[0].name);
     el.classList.remove('text-warning');
     el.classList.add('text-secondary');
     if (document.getElementById(`${id}-message`)) document.getElementById(`${id}-message`).remove();     
   //start
  // const timeValue = setInterval(function() {
    //console.log("waiting for recaptcha",id ,file);
    // if (recaptcha_emsFormBuilder && recaptcha_emsFormBuilder.length>10){
      //console.log(" I'm not robat",id , file);
      fun_upload_file_emsFormBuilder(id ,file);
    //  clearInterval(timeValue);
   // }
// }, 1000);




     //end
    // fun_upload_file_emsFormBuilder(id ,file);
    
     rtrn=true;
   }else{
  
  
  
     let message = `Please upload a ${file}`;
     el.classList.add('text-warning');
     el.classList.remove('text-secondary');
     if(el.files[0]) message=  el.files[0].size<fileSizeLimite_emsFormBuilder? `Please upload a ${file} file (${accept.join()})` : `The ${file} size is too large , Allowed Maximum size is 8MB. Try new ${file} file`;
     el.setAttribute("data-title", message);
     document.getElementById(`${id}-row`).innerHTML +=`<small class="text-danger" id="${el.id}-message">${message}</small>`;
    
     rtrn=false;
   }
  
   return rtrn;
  }



  function scrolldiv_emsFormBuilder(id) { 
    //source https://www.geeksforgeeks.org/how-to-scroll-to-an-element-inside-a-div-using-javascript/
    window.scrollTo(0,  
findPosition(document.getElementById(id))); 
} 
function findPosition(obj) { 
    var currenttop = 0; 
    if (obj.offsetParent) { 
        do { 
            currenttop += obj.offsetTop; 
        } while ((obj = obj.offsetParent)); 
        return [currenttop]; 
    } 
} 




function fun_upload_file_emsFormBuilder(id ,type){
  //این تابع فایل را به سمت سرور ارسال می کند
  const indx = files_emsFormBuilder.findIndex(x => x.id === id);

  //console.log('fun_test_ajax_request', indx , files_emsFormBuilder[indx]);
  files_emsFormBuilder[indx].state =1;
  files_emsFormBuilder[indx].type =type;
  let r=""
  $(function() {
      var fd = new FormData();
      var file = jQuery(document).find('#'+id);
      var caption = jQuery(this).find('#'+id);
      var individual_file = file[0].files[0];
      fd.append("file", individual_file);
      var individual_capt = caption.val();
      fd.append("caption", individual_capt);  
      fd.append('action', 'update_file_Emsfb');  
      fd.append('nonce', ajax_object_efm.nonce );  
      //console.log("log",individual_capt)
      jQuery.ajax({
          type: 'POST',
          url: ajax_object_efm.ajax_url,
          data: fd,
          contentType: false,
          processData: false,

          success: function(response){
            //files_emsFormBuilder
              //console.log(response);
  
              if(response.data.success===true)
              {
              r = response.data.file.url;
              files_emsFormBuilder[indx].url = response.data.file.url;
              files_emsFormBuilder[indx].state = 2;
              //console.log('fun_test_ajax_request', indx , files_emsFormBuilder[indx]);
              }else{
                files_emsFormBuilder[indx].state = 3;
                files_emsFormBuilder[indx].url = individual_file.name;
                //console.log(`files_emsFormBuilder[indx].state [${files_emsFormBuilder[indx].state}] [${individual_file.name}]`);
              }
          }
      });
  }); 
 
  return r;
}


function fun_tracking_show_emsFormBuilder(){
  document.getElementById("body_tracker_emsFormBuilder").innerHTML= ` <div class="row d-flex justify-content-center align-items-center">
  <div class="col-md-12">
      <div id="emsFormBuilder-form-view-track" >
      <form id="emsFormBuilder-form-view-id-track">
         <!-- <h1 id="emsFormBuilder-form-title">Tracking Form</h1> -->
          
  
          <div class="all-steps" > 
              <h6 class="step-name f-setp-name" id ="step-name">Please Enter your tracking Code</h6> 
          </div>
          
          <div id="emsFormBuilder-message-area-track"></div>
          <div class=" mt-2 pb-5 fadeIn" id="firsTab">
              <h5>Tracking Code:*</h5>
              <input placeholder="" type="text"  class="require emsFormBuilder" id="tracking_code_emsFormBuilder" max="20">
              </br>
              <!-- recaptcha  -->
              <div class="g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}"></div>
              <!-- recaptcha end  -->
          </div>
  

          <div style="overflow:auto;" id="emsFormBuilder-text-nextprevious-view">
              <div style="float:right;"> 
              <button type="button" id="vaid_check_emsFormBuilder" class="mat-shadow emsFormBuilder " onclick="fun_vaid_tracker_check_emsFormBuilder()"><i class="fa fa-angle-double-right"></i></button> 
              </div>
          </div>

        </form>      
      </div>
  </div>
</div>`
}

function fun_vaid_tracker_check_emsFormBuilder(){
  el =document.getElementById('tracking_code_emsFormBuilder').value;
  
  if (el.length!=12 ){
    document.getElementById('emsFormBuilder-message-area-track').innerHTML=alarm_emsFormBuilder('Tracking Code is not valid.')
  }else{
    if(currentTab_emsFormBuilder==0){
      var response = grecaptcha.getResponse();
      if(response.length == 0) { 
        //reCaptcha not verified
       // alert("no pass"); 
       document.getElementById('emsFormBuilder-message-area-track').innerHTML=alarm_emsFormBuilder(`Please Checked Box of I'm Not robot`);
        return ;
      }
      else { 
        //reCaptch verified
        recaptcha_emsFormBuilder=response;
       // alert("pass"); 
        //console.log(response);
        document.getElementById('emsFormBuilder-message-area-track') ? document.getElementById('emsFormBuilder-message-area-track').remove() :''
        document.getElementById('emsFormBuilder-form-view-track').innerHTML =`<div class="text-center"><h1 class="fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3>Please Waiting<h3></div>`;
        $(function () {
          
          data = {
            action: "get_track_Emsfb",
            value: el,
            name: formName,
            valid:recaptcha_emsFormBuilder,
            nonce:ajax_object_efm.nonce,
          
           
          };
      
          $.post(ajax_object_efm.ajax_url, data, function (res) {
           
             if (res.data.success==true) {
               //console.log(res.data);
              document.getElementById('emsFormBuilder-form-view-track').innerHTML = emsFormBuilder_show_content_message(res.data.value ,res.data.content)
            } else {             
              //console.log(`res : error`)      
              document.getElementById('emsFormBuilder-form-view-track').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i></h1><h3>Form Error</h3> <span>Something went wrong please try again, <br>Error Code:${res.data.m}</span>
              <div class="display-btn"> <button type="button" id="emsFormBuilder-text-prevBtn-view" onclick="window.location.href=window.location.href" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
      
            } 
          })
        });
      }
    }
  }
}



function emsFormBuilder_show_content_message (value , content){
  //console.log(value)
  
 
  const msg_id =value.msg_id;
  

  const userIp = "XXXXXXXXX";
  const track = value.track;
  const date ="XXXXXXXXXX";
  
  const val =JSON.parse(value.content.replace(/[\\]/g, ''));
  //console.log(content );
  
  let m = fun_emsFormBuilder_show_messages( val, "user" ,track,date)
  for(c of content) {
    //console.log(c);
    const val =JSON.parse(c.content.replace(/[\\]/g, ''));
    m += fun_emsFormBuilder_show_messages( val, c.rsp_by ,track,date);
  }
  //replay message ui
  //console.log(`sitekye_emsFormBuilder[${sitekye_emsFormBuilder}]`);
  let replayM = `<div class="mx-2 mt-2"><div class="form-group mb-1" id="replay_section__emsFormBuilder">
  <label for="replayM_emsFormBuilder">Replay:</label>
  <textarea class="form-control" id="replayM_emsFormBuilder" rows="3" data-id="${msg_id}"></textarea>
  </div>
  <div class="col text-right row">
  <button type="submit" class="btn btn-info" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">Replay</button>
  <!-- recaptcha  -->
              <div class="g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}"></div>
  <!-- recaptcha end  -->
  <p class="mx-2" id="replay_state__emsFormBuilder">  </p>
  </div></div>
  `


  return `

  <div class="">
    <div class="card-title bg-secondary px-2 py-2 text-white m-0"><i class="fa fa-comments"></i> Messages</div>
   
    <div class="my-2">
    <div class="my-1 mx-1 border border-secondary rounded-sm pb-3">
    
     <div class="mx-4 my-1 border-bottom border-info pb-1" id="conver_emsFormBuilder">
     </br>
      ${m} 
     </div>
     ${replayM}
     </div>
      
     </div>
   
  </div>
  <div>
</div>`;


//window.scrollTo({ top: 0, behavior: 'smooth' });

}



function fun_emsFormBuilder_show_messages(content,by,track,date){
  //console.log('by',by);
  if (by ==1) {by='Admin'}else if(by==0 ||by.length==0 || by.length==-1 )(by="visitor")
  let m =`<Div class="border border-light round  p-2"><div class="border-bottom mb-1 pb-1">
  <span class="small"><b>Info:</b></span></br>
  <span class="small">By: ${by}</span></br>
  ${track!=0 ? `<span class="small"> Track No: ${track} </span></br>` :''}
 <!-- <span> Date: ${date} </span></small> -->
  </div>
  <div class="mx-1">
  <h6 class="my-3"> Response: </h6>`;
  for (const c of content){
    //JSON.parse(content.replace(/[\\]/g, ''))
    //console.log("c12",c);
    let value = `<b>${c.value}</b>`;
    //console.log(`value up ${value}`)    ;
    if (c.value =="@file@" && c.state==2){
     if(c.type=="Image"){
      value =`</br><img src="${c.url}" alt="${c.name}" class="img-thumbnail">`
     }else if(c.type=="Document"){
      value =`</br><a class="btn btn-primary" href="${c.url}" >${c.name}</a>`
     }else if(c.type=="Media"){
        const audios = ['mp3','wav','ogg'];
        let media ="video";
        audios.forEach(function(aud){    
          if(c.url.indexOf(aud)!==-1){
            media = 'audio';     
          }
        })
        if(media=="video"){          
          const len =c.url.length;
          const type = c.url.slice((len-3),len);
          value = type !=='avi' ? `</br><div class="px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="text-center" ><a href="${c.url}">Video Download Link</a></p>` :`<p class="text-center"><a href="${c.url}">Download Viedo</a></p>`;
        }else{
          value=`<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
     }else{
      if (c.url.length!=0)value =`</br><a class="btn btn-primary" href="${c.url}" >${c.name}</a>`
      }
    }
    
    m +=`<p class="my-0">${c.name}: <span class="test"> ${value!=='<b>@file@</b>'?value:''}</span> </p> `
  }
  m+= '</div></div>';
//console.log(`m`,m)
  return m;
}


function fun_send_replayMessage_emsFormBuilder(id){
  //پاسخ مدیر را ارسال می کند به سرور 

  //console.log("fun_send_replayMessage_emsFormBuilder","Rmessage");
  const message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g,'</br>');		
  //console.log(message,id)
  document.getElementById('replay_state__emsFormBuilder').innerHTML=`<i class="fas fa-spinner fa-pulse"></i> Sending...`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  // +='disabled fas fa-spinner fa-pulse';
  const by = ajax_object_efm.user_name.length>1? ajax_object_efm.user_name : "Guest";
  const ob = [{name:'Message',value:message ,by:by}];
  //console.log(ob);
  let isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);
  if (message.length<1 || isHTML(message)){
    document.getElementById('replay_state__emsFormBuilder').innerHTML=`<h6><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i> Error , You can't use HTML Tag or send blanket message.</h6>`;
    return ;
  }else{
    fun_send_replayMessage_ajax_emsFormBuilder(ob ,id)
  }


}


function fun_send_replayMessage_ajax_emsFormBuilder(message,id){
  
  //console.log(`fun_send_replayMessage_ajax_emsFormBuilder(${id})` ,message ,ajax_object_efm.ajax_url,"Rmessage")
  if(message.length<1){
    document.getElementById('replay_state__emsFormBuilder').innerHTML="Please Enter message";
    document.getElementById('replayM_emsFormBuilder').innerHTML="";
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    return;
  }
  console.log(`${form_type_emsFormBuilder}`);
  $(function () {
    data = {
      action: "set_rMessage_id_Emsfb",
      type: "POST",
      id:id,
      valid:recaptcha_emsFormBuilder,
      message: JSON.stringify(message),
      nonce:ajax_object_efm.nonce ,
      type:form_type_emsFormBuilder,
      
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success==true) {
        //console.log(`response`,res);
        document.getElementById('replayM_emsFormBuilder').value="";
        document.getElementById('replay_state__emsFormBuilder').innerHTML=res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');

        // اضافه شدن به سمت یو آی 
       // const userIp =ajax_object_efm.user_ip;
        const date = Date();
        //console.log(message);
        fun_emsFormBuilder__add_a_response_to_messages(message,res.data.by,0,date);
   
      }else{
        //console.log(res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML=res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message,userIp,track,date){
  document.getElementById('conver_emsFormBuilder').innerHTML+= fun_emsFormBuilder_show_messages(message,userIp,track,date);
}


function fun_show_alert_setting_emsFormBuilder(){
  document.getElementById('body_emsFormBuilder').innerHTML=`<div class="alert alert-danger" role="alert"> <h2 class="font-weight-bold">Error</br>The form is not show ,Becuase You Have not added Google recaptcha at setting of Easy Form Builder Plugin. </br><a href="https://www.youtube.com/embed/a1jbMqunzkQ"  target="_blank" class="font-weight-normal"> Please Click here to  Watch the tutorial video</a> </h2> </div>`
}


//console.log('test',document.getElementsByClassName("multiple-emsFormBuilder"));
$(document).ready(function(){
  var multipleCancelButton = new Choices('.multiple-emsFormBuilder', {
  maxItemCount:10,
  searchResultLimit:10,
  renderChoiceLimit:10,
  removeItemButton: true
  });
  });





  
  

 