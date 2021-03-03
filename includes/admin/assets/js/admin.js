//multi step form wizard builder (core)
//Create by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
// WhiteStudio.team

let  state_check_ws_p = 1;
var currentTab_ws = 0;
let valueJson_ws_p = [];
let exportJson_ws = [];
let tabActive_ws = -1;
const proUrl_ws = `http://whitestudio.team/`
let pro_ws = true;
let stepMax_ws = 1 
let edit_emsFormBuilder = false;
let stepNames_ws = ["Define", "Step Titles", "null"];
let formName_ws = `Emsfb-${Math.random().toString(36).substr(2, 3)}`;
let form_ID_emsFormBuilder =0;
let highestAmount_emsFormBuilder;
let form_type_emsFormBuilder='form';

if (localStorage.getItem("valueJson_ws_p"))localStorage.removeItem('valueJson_ws_p');
jQuery (function() {
  state_check_ws_p =Number(efb_var.check)
  console.log(efb_var);
  pro_ws = (efb_var.pro=='1' || efb_var.pro==true) ? true : false;
  if(typeof pro_whitestudio !== 'undefined'){    
    pro_ws = pro_whitestudio ;
    console.log(`pro is new ${pro_ws}`);
  }else{
    pro_ws= false;
  }
 
  if(state_check_ws_p){
     run_code_ws_1();
     run_code_ws_2();    
  }
})



//remove footer of admin
document.getElementById('wpfooter').remove();

const elements = {
  1: { type: 'button', icon: 'fa-sign-in', pro_ws: false },
  2: { type: 'text', icon: 'fa-text-width', pro_ws: false },
  3: { type: 'password', icon: 'fa-lock', pro_ws: false },
  4: { type: 'email', icon: 'fa-envelope-o', pro_ws: false },
  5: { type: 'number', icon: 'fa-sort-numeric-asc', pro_ws: false },
  6: { type: 'file', icon: 'fa-picture-o', pro_ws: false },
  8: { type: 'date', icon: 'fa-calendar', pro_ws: true },
  9: { type: 'tel', icon: 'fa-phone-square', pro_ws: false },
  10: { type: 'textarea', icon: 'fa-align-justify', pro_ws: false },
  11: { type: 'checkbox', icon: 'fa-check-square-o', pro_ws: false },
  12: { type: 'radiobutton', icon: 'fa-dot-circle-o', pro_ws: false },
  13: { type: 'multiselect', icon: 'fa-check-circle-o', pro_ws: false },
  14: { type: 'url', icon: 'fa-link', pro_ws: true },
  15: { type: 'range', icon: 'fa-arrows-h', pro_ws: true },
  16: { type: 'color', icon: 'fa-paint-brush', pro_ws: true },
}
function run_code_ws_1(){
  if (localStorage.getItem("valueJson_ws_p")) {
    valueJson_ws_p = JSON.parse(localStorage.getItem("valueJson_ws_p"));
    // valueJson_ws_p.reverse((a, b) => b.amount - a.amount)[0]
    let step = 0;
   

    importJson = valueJson_ws_p;
    for (let i of valueJson_ws_p) {
      const r = i.steps;
      step = r > step ? r : step;
    }
    stepMax_ws = Object.keys(importJson).length ? step : stepMax_ws;
    fun_edit_emsFormBuilder();
  } else {
    stepMax_ws = 3;
  }

  
  
 // document.addEventListener("DOMContentLoaded", function (event) {


    ShowTab_emsFormBuilder(currentTab_ws);

 // });

}


function ShowTab_emsFormBuilder(n) {
  var x = document.getElementsByClassName("tab");
  if (x[n]) {
    x[n].style.display = "block";
    x[n].classList.add("fadeIn");
  }

  if (n == 0) {
    document.getElementById("prevBtn").style.display = "none";
  } else {
    document.getElementById("prevBtn").style.display = "inline";
  }
  if (n == (x.length - 1)) {
    document.getElementById("nextBtn").innerHTML = '<i class="fa fa-angle-double-right"></i>';
  } else {
    document.getElementById("nextBtn").innerHTML = '<i class="fa fa-angle-double-right"></i>';
  }
  fixStepIndicator(n)
}

function nextPrev(n) {


  if (n != 0) {
    var x = document.getElementsByClassName("tab");
    if (n == 1 && !validateForm_emsFormBuilder()) return false;
    x[currentTab_ws].style.display = "none";
    currentTab_ws = currentTab_ws + n;
    stepName_emsFormBuilder(currentTab_ws);
  }

  if (n == 0) {

    document.getElementById("nextprevious").style.display = "block";
    document.getElementById("all-steps").style.display = "";
    document.getElementById("emsFormBuilder-form-title").style.display = "block";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "none";
    document.getElementById("firsTab").style.display = "block";
   // document.getElementById("firsTab").classList.add= "step"
    for (el of document.querySelectorAll('.finish')) {
      el.classList.remove("finish");
      el.classList.remove("active");
      el.classList.contains('first')
    }

    // endMessage_emsFormBuilder()
    currentTab_ws = n;
  }
  
  // موقتی تا باگ نمایش بعد از تغییر تعداد صفحات پیدا شود
  if (n==1){
    document.getElementById('steps').disabled=true;
  }

  // این قسمت برای تنظیم که در دراپ زون محتوا قرار دارد یا نه
  // راه حل می توان هر دراپ زون را جدا جدا بررسی کرد یا اینکه قبل از ذخیره سازی دردیتا بیس بررسی شود


  if (x && currentTab_ws >= x.length) {

    document.getElementById("nextprevious").style.display = "none";
    document.getElementById("all-steps").style.display = "none";
    document.getElementById("emsFormBuilder-form-title").style.display = "none";
    document.getElementById("emsFormBuilder-text-message-view").style.display = "block";
    endMessage_emsFormBuilder()


  }


  ShowTab_emsFormBuilder(currentTab_ws);
}



function validateForm_emsFormBuilder() {
  var x, y, i, valid = true;
  x = document.getElementsByClassName("tab");
  y = x[currentTab_ws].getElementsByTagName("input");
  for (const input of x[currentTab_ws].getElementsByTagName("input")) {
    //require
    if (input.classList.contains("require")) {
      if (input.value == "") {
        input.className += " invalid"; valid = false;
        document.getElementById("message-area").innerHTML = alarm_emsFormBuilder(`Please fill in all required fields.`);

    

      } else {

        if (document.getElementById("alarm_emsFormBuilder")) document.getElementById("message-area").innerHTML = ""
      }
    }
  }
  if (valid == true) { document.getElementsByClassName("step")[currentTab_ws].className += " finish"; } return valid;
  /* 
    for (i = 0; i < y.length; i++) {
      if (y[i].value == ""  ) { y[i].className += " invalid"; valid = false; }
    } if (valid) { document.getElementsByClassName("step")[currentTab_ws].className += " finish"; } return valid; */
}
function fixStepIndicator(n) { var i, x = document.getElementsByClassName("step"); for (i = 0; i < x.length; i++) { x[i].className = x[i].className.replace(" active", ""); } x[n].className += " active"; }


function run_code_ws_2(){

document.getElementById("steps").addEventListener("change", (e) => {
  createSteps()

})// end event change creats tabs
document.getElementById("form_name").addEventListener("change", (e) => {
  // createSteps()

  const v = document.getElementById("form_name").value;
  formName_ws = v ? v : `Emsfb-${Math.random().toString(36).substr(2, 3)}`
  for (const val of valueJson_ws_p) {
    if (val.formName) {
      val.formName = formName_ws;
      saveLocalStorage_emsFormBuilder()
     //formName
    }
  }

})// end event change formName_ws

}


function addNewElement_emsFormBuilder(elementId, rndm, value) {
 
  let nameV = "";
  let idV = "";
  let classV = "";
  let tooltipV = "";
  let requiredV = "";
  let allowMultiSelectV = "";
  let clanderV="";
  let fileV=""
  let fileDrogAndDropV="";
  if (value != false) {
    
    nameV = Object.values(value.find(x => x.name))
    idV = Object.values(value.find(x => x.id))
    classV = Object.values(value.find(x => x.class))
    tooltipV = Object.values(value.find(x => x.tooltip))
    requiredV = (Object.values(value.find(x => x.required)))
    if(elementId=='date') clanderV= (Object.values(value.find(x => x.clander)))
    else if(elementId=='file') {fileV= (Object.values(value.find(x => x.file))); fileDrogAndDropV = Object.values(value.find(x => x.fileDrogAndDrop));}
    else if (elementId == "multiselect") allowMultiSelectV = Object.values(value.find(x => x.allowMultiSelect))
    nameV = nameV == "null" ? "" : nameV;
    idV = idV == "null" ? "" : idV;
    classV = classV == "null" ? "" : classV;
    tooltipV = tooltipV == "null" ? "" : tooltipV;
    requiredV = requiredV == "false" ? false : true;
    allowMultiSelectV = allowMultiSelectV == "false" ? false : true;
    fileDrogAndDropV = fileDrogAndDropV == "false" ? false : true;
    
  }
  let atr = {
    1: { id: `${rndm}-name_${elementId}`, value: nameV, placeholder: "Name", label: 'Label:*', id_:rndm },
    2: { id: `${rndm}-id_${elementId}`, value: idV, placeholder: "ID", label: 'ID' },
    3: { id: `${rndm}-class_${elementId}`, value: classV, placeholder: "Class1,Class2", label: 'Class' },
    4: { id: `${rndm}-tooltip_${elementId}`, value: tooltipV, placeholder: "Placeholder or tooltip", label: 'Tooltip' },
    5: { id: `${rndm}-required_${elementId}"`, required: requiredV }

  }
  if (elementId == "multiselect" ) atr = Object.assign(atr, { 6: { id: `${rndm}-allowMultiSelect_${elementId}`, allowMultiSelect: allowMultiSelectV } })
  else if (elementId == "file" ) atr = Object.assign(atr, { 6: { id: `${rndm}-fileDrogAndDrop_${elementId}`, fileDrogAndDrop: fileDrogAndDropV } })
  
  let amount;
  let ll = document.getElementsByClassName(`dropZone`);
  ll = document.getElementsByTagName(`a`);
  for (let l of ll){
    //789
    if (l.dataset.id) amount = Number(l.dataset.id)+1;
  }
  amount = amount ? amount:1;
  let newEl = ""
  for (a in atr) {

    if (a < 5) newEl += ` 
    <div class="form-group row">
      <label for="${atr[a].id}" class="col-sm-2 col-form-label">${atr[a].label}</label>
      <div class="col-sm-10">
          <input type="text" id="${atr[a].id}" class="insertInput ml-1 mr-1 mt-1 mb-1 ${atr[a].placeholder == "Name" ? "require" : ""}" placeholder="${atr[a].placeholder}" ${atr[a].value !== "" ? `value="${atr[a].value}"` : ""}>
      </div>
    </div>
    `;
    if (a == 5) newEl += `<div class="form-check ml-1 mr-1 mt-1 mb-1">
    <input class="insertInput form-check-input" type="checkbox" id="${atr[a].id}" ${atr[a].required ? "checked" : ""}>
    <label class="col-sm-2   form-check-label" for="${atr[a].id}">
      Required
    </label>
  </div>`;
    //edit below code 789 fun_multiselect_button_emsFormBuilder 
    if (a == 6 && elementId=='multiselect') newEl += pro_ws==true ?  fun_multiselect_button_emsFormBuilder(elementId,pro_ws,atr,a): `<div class="form-check ml-1 mr-1 mt-1 mb-1" onClick="unlimted_show_emsFormBuilder('This option is available in Pro version')"><input class="insertInput form-check-input" type="checkbox" id="${atr[a].id}" disabled><label class=" form-check-label" for="${atr[a].id}">Allow multi-select </label><small class=" text-warning"> <b>Click for Active Pro vesrsion<b></small></div>`;
//    if (a == 6 && pro_ws==true && elementId=='multiselect') newEl += fun_multiselect_button_emsFormBuilder(elementId,pro_ws,atr,a);
    if (a == 6 && pro_ws==true &&  elementId=='file') newEl += fun_dragAndDrop_button_emsFormBuilder(elementId,pro_ws,atr,a) || `<div class="form-check ml-1 mr-1 mt-1 mb-1"  onClick="unlimted_show_emsFormBuilder('This option is available in Pro version')"><input class="insertInput form-check-input" type="checkbox" id="${atr[a].id}"  disabled><label class=" form-check-label" for="${atr[a].id}"">Use Drog and Drop UI </label><small class=" text-warning"> <b>Click here for Active Pro vesrsion</b></small></div>`
    if (a == 6 && pro_ws!=true  && elementId=='file' ) newEl += `<div class="form-check ml-1 mr-1 mt-1 mb-1"  onClick="unlimted_show_emsFormBuilder('This option is available in Pro version')"><input class="insertInput form-check-input" type="checkbox" id="${atr[a].id}"  disabled><label class=" form-check-label" for="${atr[a].id}"">Use Drog and Drop UI </label><small class=" text-warning"> <b>Click here for Active Pro vesrsion</b></small></div>`
  }


  const newElement = `
  <div id="${rndm}" class="section border border-primary rounded mb-0 h-30 view overlay ml-3 mr-3 mt-2 mb-1" draggable="true">
    <div class="card-header success-color white-text" > 
      <a data-toggle="collapse" data-target="#${rndm}-c" data-id="${amount}" onClick="funIconArrow_emsFormBuilder('${rndm}')" > <i class="fa fa-caret-right" id="${rndm}-icon"> </i> </a> 
      <a class="mb-0 ml-1 mr-1 mt-1 mb-1"   data-toggle="collapse" data-target="#${rndm}-c" id="${rndm}-b" onClick="funIconArrow_emsFormBuilder('${rndm}')">
        ${elementId.toUpperCase()}
      </a>       
    </div>
    <div id="${rndm}-c" class=" ml-3 mt-2 mb-2 mr-3 collapse show">
    <div id="${rndm}-g">
       ${newEl}
       ${ /*elementId == "date" ? `<div class="form-group row"><label for="${atr[1].id_}-date" class="col-sm-3 col-form-label">Calendar</label><div class="col-sm-9"><select class="insertInput ml-1 mr-1 mt-1 mb-1 " id="${atr[1].id_}-date"><option value="Gregorian" ${clanderV=='Gregorian' ||clanderV=='' ? 'selected':''}>Gregorian</option><option value="Persian" ${clanderV=='Persian' ? 'selected':''}>Persian calendar</option><option value="Arabic" ${clanderV=='Arabic' ? 'selected':''}>Arabic calendar</option></select></div></div>`:`` */ ''}
       ${elementId == "file" ? `<div class="form-group row"><label for="${atr[1].id_}-file" class="col-sm-3 col-form-label">File Type</label><div class="col-sm-9"><select class=" ml-1 mr-1 mt-1 mb-1 insertInput" id="${atr[1].id_}-file"><option value="Document" ${fileV=='Document' ? 'selected':''}>Documents</option><option value="Image" ${fileV=='Image' ||fileV=='' ? 'selected':''}>Image</option><option value="Media" ${fileV=='Media' ||fileV=='' ? 'selected':''}>Media (Video or Audio)</option><option value="Zip" ${fileV=='Zip' ||fileV=='' ? 'selected':''}>Zip</option></select></div></div>`:``}
       
       <input type="hidden" id="${rndm}-amount" value="${amount}">
        ${elementId == "radiobutton" || elementId == "checkbox" || (elementId == "multiselect") ? `<div id="${rndm}-o" class= "border-top">` : ""}
      </div>
      <button id="${rndm}"class="delete btn btn-danger btn-sm btn-rounded waves-effect waves-light ml-1 mr-1 mt-1 mb-1" type="submit">Delete</button>
  ${elementId === "checkbox" || elementId === "radiobutton" || (elementId == "multiselect") ? ` <button id="${rndm}-oc"class="add-option btn btn-primary btn-sm btn-rounded waves-effect waves-light ml-1 mr-1 mt-1 mb-1 " type="submit" disabled>New option</button>` : ""}
    <span id="${rndm}-info" class="text-capitalize font-weight-lighter badge badge-warning text-wrap"> info </span>
    </div>
  </div>`;


  return newElement;
}

//id,id_,value
function addOject_emsFormBuilder(id, id_, value, type, value_of, group, step) {
  step = parseInt(step); 
 
  let highestAmount= group!=="option" ?  Number(document.getElementById(`${id_}-amount`).value) : null ;
  highestAmount_emsFormBuilder=highestAmount;
//valueJson_ws_p.reverse((a, b) => b.amount - a.amount)[0]
  let ob = {};
  /* if (value_of != `allowMultiSelect` && value_of != 'required') value = (value.length > 0 && (value.match(/ /g) || []).length < value.length) ? value : ""
  else if (value_of != `fileDrogAndDrop` && value_of != 'required') value = (value.length > 0 && (value.match(/ /g) || []).length < value.length) ? value : "" */
  
  if (group === "notOption") {
   /*  let o = valueJson_ws_p[(valueJson_ws_p.length)-1]
    
    let highestAmount = 1
    let state =true
    for(v of valueJson_ws_p){
      if(v.amount) highestAmount=v.amount+1;
    } */
    
    
    if (value_of == "name") {
      ob = { id_: id_, name: value, type: type, step: step, amount: highestAmount }
      
      document.getElementById(`${id_}-b`).innerHTML = `${value} [${type.toUpperCase()}]`
    } else if (value_of == "id") {
      ob = { id_: id_, id: value, type: type, step: step, amount: highestAmount }
    } else if (value_of == "class") {
      ob = { id_: id_, class: value, type: type, step: step, amount: highestAmount }
    } else if (value_of == "tooltip") {
      ob = { id_: id_, tooltip: value, type: type, step: step, amount: highestAmount }
    } else if (value_of == "required") {
      value = value === false || value === 'false' ? "false" : true
      ob = { id_: id_, required: value, type: type, step: step, amount: highestAmount }
    } else if (value_of == "allowMultiSelect" && pro_ws) {
      ob =fun_allowMultiSelect_pro_emsFormBuilder(id_,value,type,step)
      
    }else if (value_of =="fileDrogAndDrop"){      
      ob = fun_fileDrogAndDrop_ob_pro_emsFormBuilder(id_,value,type,step);
      
      
    }else if (type=="date"){
      Object.assign(ob,{clander:"Gregorian"});
    }else if (type=="file" ){
      //Object.assign(ob,{file:"Document"}) ;
    }
  } else if (group === "option") {
    
    // add group to array object  789
    //function addOject_emsFormBuilder
    if(type.search("-")!=-1){
      const parents= type.substring(0, type.search("-"));
      id_ = id.substring(0, type.search("-"));
      value_of =type.substring((type.search("-"))+1, type.search("_"));
      
      const test =id.search("date")!=-1;
      
      if(id.search("date")!=-1)  {type ="date"; value_of ="clander";}
      else if(id.search("file")!=-1) {type ="file"; value_of ="file";}
      
    }
    
    if (value_of == "name") {
      
      ob = { id_: id_, name: value, parents: type, type: "option", step: step }
    } else if (value_of == "id") {
      ob = { id_: id_, id: value, parents: type, type: "option", step: step }
    } else if (value_of == "class") {
      ob = { id_: id_, class: value, parents: type, type: "option", step: step }
    } else if (value_of == "tooltip") {
      ob = { id_: id_, tooltip: value, parents: type, type: "option", step: step }
    } else if (value_of == "required") {
      value = value === false || value === 'false' ? "false" : true
      ob = { id_: id_, required: value, parents: type, type: "option", step: step }
    } else if (value_of == "allowMultiSelect" && pro_ws) {
      value = value === false || value === 'false' ? "false" : true
      ob =fun_allowMultiSelect_pro_emsFormBuilder(id_,value,type,step)
    } else if(type ==="date"){
      value==null || value==undefined ? value='Gregorian' :0;
      ob = fun_date_ob_pro_emsFormBuilder(id_,value,type,step,highestAmount)
    }else if(type ==="file"){
      (value==null || value==undefined) && value.length>2 ? value='Document':0;
      ob = { id_: id_, file: value, type: type, step: step , amount: highestAmount }
    }

   
  }
 
  if (Object.keys(valueJson_ws_p).length === 0) {
   if(ob.id_)  valueJson_ws_p.push(ob);
    saveLocalStorage_emsFormBuilder()
  } else {
    let foundIndex = valueJson_ws_p.findIndex(x => x.id_ == id_);
    //items[foundIndex] = item;
   
    let item = valueJson_ws_p[foundIndex];
    if (foundIndex != -1) {
      // if id_ of elemant exists on list
      let item = valueJson_ws_p[foundIndex];
     
      valueJson_ws_p[foundIndex] = item;
      
      switch (value_of) {
        case "name":
      
          const nm = { name: ob.name }
          valueJson_ws_p[foundIndex].name ? valueJson_ws_p[foundIndex].name = ob.name : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], nm)

          break;
        case "class":
          const clss = { class: ob.class };
          valueJson_ws_p[foundIndex].class ? valueJson_ws_p[foundIndex].class = ob.class : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], clss);
         
          break;
        case "id":

          const id = { id: ob.id };
          valueJson_ws_p[foundIndex].id ? valueJson_ws_p[foundIndex].id = ob.id : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], id);

          
          break;
        case "required":
          const required = { required: ob.required };
          valueJson_ws_p[foundIndex].required ? valueJson_ws_p[foundIndex].required = ob.required : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], required);
          break;
        case "tooltip":
          const tooltip = { tooltip: ob.tooltip };
          valueJson_ws_p[foundIndex].tooltip ? valueJson_ws_p[foundIndex].tooltip = ob.tooltip : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], tooltip);
          break;
        case "allowMultiSelect":
          const allowMultiSelect = { allowMultiSelect: ob.allowMultiSelect };
          valueJson_ws_p[foundIndex].allowMultiSelect && pro_ws ? valueJson_ws_p[foundIndex].allowMultiSelect = ob.allowMultiSelect : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], allowMultiSelect);
          break;
        case "clander":
          const clander = { clander: ob.clander };
          valueJson_ws_p[foundIndex].clander ? valueJson_ws_p[foundIndex].clander = ob.clander : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], clander);
          break;
        case "file":
          const file = { file: ob.file };
          valueJson_ws_p[foundIndex].file ? valueJson_ws_p[foundIndex].file = ob.file : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], file);
          
          break;
          case "fileDrogAndDrop":
            let fileDrogAndDrop= {fileDrogAndDrop:ob.fileDrogAndDrop}
            
            if(ob.fileDrogAndDrop!=undefined)  valueJson_ws_p[foundIndex].fileDrogAndDrop ? valueJson_ws_p[foundIndex].fileDrogAndDrop = ob.fileDrogAndDrop : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], fileDrogAndDrop);
          break;

      }
      
      saveLocalStorage_emsFormBuilder();

    } else {
     
      if(ob.id_) {
       
        valueJson_ws_p.push(ob);
        saveLocalStorage_emsFormBuilder();
      }
 
    }
  }
  
}// end function

function fillinput_emsFormBuilder() {
  if (Object.keys(valueJson_ws_p).length !== 0) {

    for (const item of valueJson_ws_p) {
      
      if (item.type !== "option") {
        if (item.name) {
          const id = `${item.id_}-name_${item.type}`
         
          if (document.getElementById(id)) document.getElementById(id).value = item.name;
        }
        if (item.id) {
          const id = `${item.id_}-id_${item.type}`
          
          if (document.getElementById(id)) document.getElementById(id).value = item.id;
        }
        if (item.class) {
          const id = `${item.id_}-class_${item.type}`
          
          if (document.getElementById(id)) document.getElementById(id).value = item.class;
        }
        if (item.tooltip) {
          const id = `${item.id_}-tooltip_${item.type}`
        
          if (item.tooltip != "null" && document.getElementById(id)) document.getElementById(id).value = item.tooltip
        }
        if (item.required == true || item.required == false) {
          const id = `${item.id_}-required_${item.type}`
          item.required = item.required === "false" ? false : true
          if (document.getElementById(id)) document.getElementById(id).checked = item.required;
        }
      }
      else if (item.type === "option") {
       
        if (item.name) {
          const id = `${item.id_}-name_${item.parents}`

          if (document.getElementById(id)) document.getElementById(id).value = item.name;
        }
        if (item.id) {
          const id = `${item.id_}-id_${item.parents}`
         
          if (document.getElementById(id)) document.getElementById(id).value = item.id;
        }
        if (item.class) {
          const id = `${item.id_}-class_${item.parents}`
          
          if (document.getElementById(id)) document.getElementById(id).value = item.class;
        }
        if (item.required == true || item.required == false) {
          const id = `${item.id_}-required_${item.parents}`
          if (document.getElementById(id)) document.getElementById(id).checked = item.required;
        }


      }

    }

  }
}//end function fillinput_emsFormBuilder
//edit_emsFormBuilder content of dropZone
function fun_edit_emsFormBuilder(){
  valueJson_ws_p = importJson;
  document.getElementById('steps').disabled = true;
  let foundIndex = valueJson_ws_p.findIndex(x => x.steps == stepMax_ws);
  for (let v of valueJson_ws_p) {
    
    let ob = [];
    let id_ = ""
    if (v.steps) {
     
      document.getElementById('steps').value = v.steps;
      document.getElementById('form_name').value = v.formName;
      createSteps();
      for (let i = 1; i <= v.steps; i++) {
        //777
        const item = valueJson_ws_p[foundIndex];
        const icon = item[`icon-${i}`] ? item[`icon-${i}`] : `fa-object-group`;
        document.getElementById(`tabName_${i}`).value = item[`name-${i}`];
        
        document.getElementById(`stepIcon-${i - 1}`).innerHTML = `<i class="fa ${icon}"></i>`
       
        const tb = document.getElementById(`tabicon_${i}`)
        tb.placeholder = icon;
        let indexOp = 0
        for (const op in tb.options) {
          if (tb.options[op].value == icon) { indexOp = op };
          tb.selectedIndex = indexOp;
        }

      }
      //تابع سازده استپ ها اینجا صدا زده شود
      //مقدار های عنوان و ایکون بعد از ساخت استپ ها وارد شود
    }
    if (v.type != "option") {
    
      id_ = v.id_
      v.name != undefined && v.name != "" ? ob.push({ name: v.name }) : ob.push({ name: "null" });
     
      v.id != undefined && v.id != "" ? ob.push({ id: v.id }) : ob.push({ id: "null" });
      v.tooltip != undefined && v.tooltip != "" ? ob.push({ tooltip: v.tooltip }) : ob.push({ tooltip: "null" });
      v.class != undefined && v.class != "" ? ob.push({ class: v.class }) : ob.push({ class: "null" });
      v.required != undefined && v.required != "" ? ob.push({ required: v.required }) : ob.push({ required: "false" });
      v.clander != undefined && v.clander != "" ? ob.push({ clander: v.clander }) : ob.push({ clander: "Gregorian" });
      v.fileDrogAndDrop != undefined && v.fileDrogAndDrop != "" ? ob.push({ fileDrogAndDrop: v.fileDrogAndDrop }) : ob.push({ fileDrogAndDrop: "Document" });
      v.file != undefined && v.file != "" ? ob.push({ file: v.file }) : ob.push({ file: "Zip" });
      v.allowMultiSelect != undefined && v.allowMultiSelect != "" && v.allowMultiSelect ? ob.push({ allowMultiSelect: v.allowMultiSelect }) : ob.push({ allowMultiSelect: "false" });
      const dropZone = document.getElementById(`dropZone-${v.step}`)
      if (dropZone) {
        dropZone.innerHTML += addNewElement_emsFormBuilder(v.type, id_, ob);
        document.getElementById(`${id_}-b`).innerHTML = `${v.name != undefined ? v.name : ''} [${v.type.toUpperCase()}]`
        if (v.type == "radiobutton" || v.type == "checkbox") {v.name ? document.getElementById(`${id_}-oc`).disabled = false : document.getElementById(`${id_}-oc`).disabled = true;
        }else if (v.type == "multiselect" && pro_ws) {
          fun_multiselect_pro_emsFormBuilder(id_,v)
        }
      }
    }
    else if (v.type == "option") {
      const id_ = v.id_ + "_" + v.parents
     
      v.name != undefined ? ob.push({ name: v.name }) : ob.push({ name: "null" });
     
      v.id != undefined ? ob.push({ id: v.id }) : ob.push({ id: "null" });
      v.tooltip != undefined ? ob.push({ tooltip: v.tooltip }) : ob.push({ tooltip: "null" });
      v.class != undefined ? ob.push({ class: v.class }) : ob.push({ class: "null" });
      //new code 
      let id = v.parents;
      
        document.getElementById(v.parents + "-o").innerHTML += `<div id="${id_}" class= "border-top">
        <button type="button" class="close remove btn  btn-outline-danger" aria-label="Close" id="remove_${id_}">
          <span aria-hidden="true">×</span>
        </button>
        <input type="text" id="${id_}-name_${v.parents}" class="insertInput ml-1 mr-1 mt-1 mb-1" placeholder="name" ${v.name != undefined ? `value="${v.name}"` : ""}>
        <input type="text" id="${id_}-id_${v.parents}" class="insertInput ml-1 mr-1 mt-1 mb-1" placeholder="ID" ${v.id != undefined ? `value="${v.id}"` : ""}>
        <input type="text" id="${id_}-class_${v.parents}" class="insertInput ml-1 mr-1 mt-1 mb-1" placeholder="Class1,Class2" ${v.class != undefined ? `value="${v.class}"` : ""}>
        <input type="text" id="${id_}-tooltip_${v.parents}" class="insertInput ml-1 mr-1 mt-1 mb-1" placeholder="Placeholder or tooltip" ${v.tooltip != undefined ? `value="${v.tooltip}"` : ""}>`;
      
      document.getElementById(`remove_${id_}`).addEventListener("click", (e) => {
        
        e.preventDefault();        
        let id = id_;
        
        document.getElementById(id).remove();
        id = id_
        
        let foundIndex = Object.keys(valueJson_ws_p).length > 0 ? valueJson_ws_p.findIndex(x => x.id_ == id) : -1


        valueJson_ws_p.splice(foundIndex, 1);
        
        saveLocalStorage_emsFormBuilder();
      });
      //end new code
    }


    //dropZone.innerHTML += addNewElement_emsFormBuilder(el,rndm,false);
    deleteButtonCreator_emsFormBuilder();



  }//end foreach el

  //


  for (const dropZone of document.querySelectorAll(".dropZone")) {
    eventCreatorOfInsertInput_emsFormBuilder(dropZone);
  }
  for (const el of document.querySelectorAll(".add-option")) {
    
    optionCreator_emsFormBuilder(el);
  }
}//end edit_emsFormBuilder 


function deleteButtonCreator_emsFormBuilder() {
  for (const el of document.querySelectorAll(".delete")) {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      const id = el.id;
      


      if (el != null) {

        if (document.getElementById(id)) document.getElementById(id).remove();
      }

      let foundIndex = Object.keys(valueJson_ws_p).length > 0 ? valueJson_ws_p.findIndex(x => x.id_ == el.id) : -1

      let found = Object.keys(valueJson_ws_p).length > 0 ? valueJson_ws_p.find(x => x.id_ == el.id) : -1;

      
      if (foundIndex!=-1)valueJson_ws_p.splice(foundIndex, 1);
     
      if (found && found !== -1 && (found.type === "radiobutton" || found.type === "checkbox" || found.type === "multiselect")) {
        const id = found.id_;

      //  foundIndex = -1;
        while (foundIndex != -1) {
          foundIndex = valueJson_ws_p.findIndex(x => x.parents == id);
          
          if (foundIndex != -1) { valueJson_ws_p.splice(foundIndex, 1) }
        }
        
        saveLocalStorage_emsFormBuilder()
      }
      saveLocalStorage_emsFormBuilder()
    });
  }
}
//777
function optionCreator_emsFormBuilder(el) {
  el.addEventListener("click", (e) => {
    const rndm = Math.random().toString(36).substr(2, 9);
    e.preventDefault();
    
    let id = el.id.substring(0, el.id.search("-"));
    
    const elementId = id;
    id = id + "-o"
    console.log(`id:${id}`,document.getElementById(id));

    document.getElementById(id).innerHTML += `<div id="${rndm}-${elementId}" class= "border-top">
            <button type="button" class="close remove btn  btn-outline-danger" aria-label="Close" id="remove_${rndm}-${elementId}">
              <span aria-hidden="true">×</span>
            </button>
            <input type="text" id="${rndm}-name_${elementId}" class="insertInput-${rndm} ml-1 mr-1 mt-1 mb-1" placeholder="name">
            <input type="text" id="${rndm}-id_${elementId}" class="insertInput-${rndm} ml-1 mr-1 mt-1 mb-1" placeholder="ID">
            <input type="text" id="${rndm}-class_${elementId}" class="insertInput-${rndm} ml-1 mr-1 mt-1 mb-1" placeholder="Class1,Class2">
            <input type="text" id="${rndm}-tooltip_${elementId}" class="insertInput-${rndm} ml-1 mr-1 mt-1 mb-1" placeholder="Placeholder or tooltip">
            </div>
`;
    for (const el of document.querySelectorAll(".remove")) {
      ;
      el.addEventListener("click", (e) => {
        e.preventDefault();

        let id = el.id.substring(el.id.search("_") + 1, el.id.length);
        document.getElementById(id).remove();
        id = id.substring(0, id.search("-"));
        
        let foundIndex = Object.keys(valueJson_ws_p).length > 0 ? valueJson_ws_p.findIndex(x => x.id_ == id) : -1

        //let found = Object.keys(valueJson_ws_p).length > 0 ?  valueJson_ws_p.find(x => x.id_ == id):-1;
        
        valueJson_ws_p.splice(foundIndex, 1);
 

      });
    }


    for (const el of document.querySelectorAll(`.insertInput-${rndm}`)) {

      el.addEventListener("change", (e) => {

        e.preventDefault();
        const id = el.id;
        const id_ = el.id.substring(0, el.id.search("-"));
        const type = el.id.substring((el.id.search("_") + 1), el.id.length);

        const value_of = el.id.substring((el.id.search("-") + 1), el.id.search("_"));
        let value = "";

        group = "option";
        value = document.getElementById(el.id).value;
        addOject_emsFormBuilder(id, id_, value, type, value_of, group);

      });

    }
    fillinput_emsFormBuilder();

    
  });


}//end function optionCreator_emsFormBuilder

function saveLocalStorage_emsFormBuilder() {
 
  localStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
  localStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
}


function alarm_emsFormBuilder(val) {
  return `<div class="alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
    <div class="emsFormBuilder"><i class="fas fa-exclamation-triangle faa-flash animated"></i></div>
    <strong>Alert! </strong>${val}
  </div>`
}

function funIconArrow_emsFormBuilder(id) {
 
  const el = document.getElementById(`${id}-icon`);
  el.className = el.className == "fa fa-caret-right" ? "fa fa-caret-down" : "fa fa-caret-right";
  //${rndm}-icon
  //document.getElementById(`${rndm}-icon`).className
}

function eventCreatorOfInsertInput_emsFormBuilder(dropZone) {


  for (const el of document.querySelectorAll(".insertInput")) {
  
    el.addEventListener("keyup", (e) => {

      e.preventDefault();
      const id = el.id;
      const id_ = el.id.substring(0, el.id.search("-"));
      const type = el.id.substring((el.id.search("_") + 1), el.id.length);
      let group = "notOption";
      let value_of = el.id.substring((el.id.search("-") + 1), el.id.search("_"));
      let value = "";
      if(value_of !='name' || value_of !='id' || value_of !='class' || value_of !='tooltipe'){
      
          if (id.includes('name')===true){
            value_of='name';
          }else if (id.includes('id')===true){
            value_of='id';
          }else if (id.includes('class')===true){
            value_of='class';
          }else if (id.includes('tooltipe')===true){
            value_of='tooltipe';
          }
          
      }

      
      if (type == "text" || type == "password" || type == "button" || type == "number" || type == "tel" || type == "textarea" || type == "image" || type == "email" || type == "date" || type == "url" || type == "color" || type == "range"  || type == "file") {

        value = document.getElementById(el.id).value;
      } else if (type == "radiobutton" || type == "checkbox" || type == "multiselect") {
        value = document.getElementById(el.id).value;
      } else {
        group = "option";
        value = document.getElementById(el.id).value;
      }

      if ((type == "radiobutton" || type == "checkbox" || type == "multiselect") && Object.keys(valueJson_ws_p).length >= 0) {
        let checkState = false;

        //check validiton of new option
        for (const v of valueJson_ws_p) {
          if ((v.type == "radiobutton" || v.type == "checkbox" || v.type == "multiselect") && v.id_ == id_) {
            
            for (const el of document.querySelectorAll(".add-option")) {

              if (el.id.substring(0, el.id.search("-")) == id_) {
                
                v.name && v.name !== "" ? el.disabled = false : el.disabled = true;
              }


            }
          } else {
            for (const el of document.querySelectorAll(".add-option")) {
              
              //   el.classList.contains("disabled")===false  ? el.classList.add("disabled") : "";

            }
          }
        }//end for const v of valueJson_ws_p
        //end check validiton of new option
      }

      if (value_of == "required" || (value_of == "allowMultiSelect" && pro_ws)) {
        
        const id = el.id;
        value = document.getElementById(id).checked;
        
      }

      if ((type == "radiobutton" || type == "checkbox" || type == "multiselect") && (!e.target.id.includes("required") && !e.target.id.includes("allowMultiSelect"))) {
        const r = value.length > 0 ? false : true;
        document.getElementById(`${id_}-oc`).disabled = r
        
        
      }
      
      const step = dropZone.id.substring((dropZone.id.search("-") + 1), dropZone.id.length)
      addOject_emsFormBuilder(id, id_, value, type, value_of, group, step);

    });
  }

  // showloading_emsFormBuilder() //این قسمت دیلی ایجاد می کنه که محتوای که قرار است در دراپ زون قرار بگیرند درست ست بشن
}


function loading_emsFormBuilder() {
  return `<div  id="loading_emsFormBuilder"><div class="spinner-border text-warning" role="status">
    <span class="sr-only">loading_emsFormBuilder...</span>
  </div></div>`

}

function showloading_emsFormBuilder() {
  const time = stepMax_ws < 3 ? 700 : stepMax_ws * 200;
  
  //document.getElementById(`body`).innerHTML+=loading_emsFormBuilder();
  document.getElementById("body").classList.add = "wait";
  if (body.style.pointerEvents == "none") body.style.pointerEvents = "auto";
  else body.style.pointerEvents = "none";
  setTimeout(() => {
    document.getElementById("body").classList.remove = "wait";
    if (body.style.pointerEvents == "none") body.style.pointerEvents = "auto";
    else body.style.pointerEvents = "none";
    
  }, time);
}


function endMessage_emsFormBuilder() {

  let notfilled = []
  for (i = 1; i <= stepMax_ws; i++) {
    if (-1 == (valueJson_ws_p.findIndex(x => x.step == i))) notfilled.push(i);
  }
  
  if (notfilled.length > 0) {
    let str = ""
    for (no of notfilled) {
      str +=` <b> ${stepNames_ws[no+1]} </b> step ,`;
    }
    
    document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3>Form Not builded</h3> <span>Some step not defined , Please check:  ${str}</span>
    <div class="display-btn"> <button type="button" id="prevBtn" onclick="nextPrev(0)" class="p-3" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
   // faild form
  } else {
    document.getElementById('emsFormBuilder-text-message-view').innerHTML =`<h1 class="fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3>Please Waiting<h3>`
    actionSendData_emsFormBuilder()
  //  Ok form

  }



}

function createSteps() {
  
  const addSteps = document.getElementById("addStep");
  const tabList = document.getElementById("tabList");
  const tabInfo = document.getElementById("tabInfo");

  //remove all elements in (start)
  if (addSteps.hasChildNodes()) {
    while (addSteps.hasChildNodes()) {
      addSteps.removeChild(addSteps.childNodes[0]);
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
   //remove all elements in (end)

 // check value of maxstep get from user (Start)
  
  const c = (document.getElementById("steps").value < 3 && document.getElementById("steps").value > 0 && !pro_ws) || (pro_ws && document.getElementById("steps").value <21 && document.getElementById("steps").value > 0 ) ? document.getElementById("steps").value : -1
  if (c != -1) {
    document.getElementById("nextBtn").disabled = false;
    document.getElementById("alarm_emsFormBuilder") ? document.getElementById("alarm_emsFormBuilder").remove() : ""

  } else {
   // document.getElementById("nextBtn").disabled = true;
    document.getElementById("nextBtn").display = "none";
    const  message = !pro_ws ? `You can create minmum 1 and maximum 2 Steps. <br>  If you need create more than 2 Steps, activeate <a href="${proUrl_ws}" target="_blank">Pro version</a>` :`You Could create minmum 1 Step and maximum 20 Step`;
    document.getElementById("wpwrap").innerHTML += unlimted_version_emsFormBuilder(message,1)
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.getElementById("nextBtn").style.display = "none";
  // check value of maxstep get from user (end)
  stepMax_ws = c;


  //create option list of icon (start)
  let optionsOfSelect = null
  for (const n in listIcons) {

    const icon = listIcons[`${n}`];
    const name = n.substring(3, n.length)
    optionsOfSelect == null ? optionsOfSelect = ` <option value="fa ${n}">${name}    ${icon}</option>` : optionsOfSelect += `<option value="fa ${n}" id="${n}">${name}<i>${icon}</i></option>`
  }
  //create option list of icon (end)

  for (let i = 1; i <= c; i++) {

    let tags = ""
    for (const e in elements) {

      if ((pro_ws == elements[e].pro_ws) || (pro_ws == true)) {
        tags += `<div class="el el-${elements[e].type} btn btn-dark btn-m btn-block mat-shadow" id="${elements[e].type}-${i}" draggable="true"><i class="fa ${elements[e].icon} bttn"></i> ${elements[e].type}</div>`
      } else {
        tags += `<div class="el el-${elements[e].type} limited btn btn-warning btn-m btn-block" id="${elements[e].type}-${i}" draggable="false"><i class="fa fa-unlock-alt bttn"></i> ${elements[e].type}</div>`
      }
    }
    document.getElementById("tabInfo").innerHTML += `
             <div class="border-bottom mt-4">
             <h5>${efb_var.text.titleOfStep} ${i}</h5>
             <p><input type="text" class="tabC require emsFormBuilder" name="Tab" placeholder="Tab ${i}"    id="tabName_${i}"></p>
             <h5><i class="fa fa-object-group" id="icon-step-${i}"> </i> Icon of  step ${i}  </h5>
             <select class="selectpicker tabC emsFormBuilder" name="Tab" placeholder="fa-user-circle" data-live-search="true" id="tabicon_${i}" >
             ${optionsOfSelect};
             </select>
             </div>
             `;

    //<span class="step"><i class="fa fa-shopping-bag"></i></span> 
    document.getElementById("addStep").innerHTML += `<span class="step" id="stepIcon-${i - 1}"><i class="fa fa-object-group"></i></span>`
    document.getElementById("tabList").innerHTML += ` <div class="tab" >        
             <div class="container" id="container">
             <div class="container" id="container">
             <div class="row">
               <div class="col-4 ">
                 Elements:
                 <div class="row element-list ml-1 mr-1 mt-1 mb-1" id="elements-${i}">                
                 </div>
               </div>
               <div class="col-8 ">          
                 <div class="row border border-light view overlay list-group h-100 dropZone black_box mat-shadow-dz" id="dropZone-${i}">          
                 </div>
                 <div class="col-1">
                   <input type="hidden" id="export" name="export" value="3487">
                 </input>
               </div>
             </div>          
         </div>`

    document.getElementById(`elements-${i}`).innerHTML = tags;
  }//end for

  for (const el of document.querySelectorAll(`.tabC`)) {

    el.addEventListener("change", (e) => {
      const no = el.id.substring((el.id.search("_") + 1), el.id.length)
      let name = el.id.substring(0, (el.id.search("_")))
      
      name = name == "tabName" ? "name" : "icon";
      
      //emsfb version of form creator emsfb:1 ,
      const ob = {steps: stepMax_ws, [`${name}-${no}`]: el.value, formName: formName_ws,EfbVersion:1.2,type:form_type_emsFormBuilder }
     console.log(ob);
      
      if (name == "icon") {
        document.getElementById(`stepIcon-${no - 1}`).innerHTML = `<i class="fa ${el.value}"></i>`;
        document.getElementById(`icon-step-${no}`).className = el.value;
        
      }
      if (name == "name") {
        const v = stepNames_ws.length >= 4
        const nno = parseInt(no);
        
        v != false && nno + 1 != stepNames_ws.length - 1 ? stepNames_ws[1 + nno] = el.value : stepNames_ws.splice(1 + nno, 0, el.value);
        
      }
      if (Object.keys(valueJson_ws_p).length === 0) {

        valueJson_ws_p.push(ob);
        
      } else {
        let foundIndex = valueJson_ws_p.findIndex(x => x.steps == stepMax_ws);
        if (foundIndex != -1) {
          let item = valueJson_ws_p[foundIndex];
          item[`${name}-${no}`] ? valueJson_ws_p[foundIndex][`${name}-${no}`] = el.value : valueJson_ws_p[foundIndex] = Object.assign(valueJson_ws_p[foundIndex], { [`${name}-${no}`]: el.value })
          
          saveLocalStorage_emsFormBuilder()
        } else {
          valueJson_ws_p[0]=ob;
        }

      }

      console.log(valueJson_ws_p);
    })
  }
  for (const el of document.querySelectorAll(`.limited`)) {

    el.addEventListener("click", (e) => {
      document.getElementById('message-area').innerHTML += unlimted_version_emsFormBuilder('This option is available in Pro version',0);
      window.scrollTo({ top: 0, behavior: 'smooth' });

    })
  }

  for (const dropZone of document.querySelectorAll(`.dropZone`)) {
    

    dropZone.addEventListener("dragover", (e) => {

      e.preventDefault();
    });



    dropZone.addEventListener("drop", (e) => {

      e.preventDefault();
      let el = e.dataTransfer.getData("text/plain");
      
      el = el.substring(0, el.search("-"))
      if (el !== "step" && el != null && el != "") {
        const rndm = Math.random().toString(36).substr(2, 9);
        dropZone.innerHTML += addNewElement_emsFormBuilder(el, rndm, false);

      }

      
      //here
      fillinput_emsFormBuilder();

      
      //here
      eventCreatorOfInsertInput_emsFormBuilder(dropZone);

      for (const el of document.querySelectorAll(".add-option")) {
        
        optionCreator_emsFormBuilder(el);
      }


      deleteButtonCreator_emsFormBuilder();

    }); // end drogZone



    dropZone.addEventListener("click", (e) => {
      for (const el of document.querySelectorAll(".insertInput")) {
        
        el.addEventListener("change", (e) => {
          
          e.preventDefault();
          const id = el.id;
          const id_ = el.id.substring(0, el.id.search("-"));
          const type = el.id.substring((el.id.search("_") + 1), el.id.length);
          let group = "notOption";
          const value_of = el.id.substring((el.id.search("-") + 1), el.id.search("_"));
          let value = "";
          let amount =0;



          if (type == "text" || type == "password" || type == "button" || type == "number" || type == "tel" || type == "textarea" || type == "image" || type == "email" || type == "date" || type == "file") {

            value = document.getElementById(el.id).value;
          } else if (type == "radiobutton" || type == "checkbox") {
            value = document.getElementById(el.id).value;
          } else {
            group = "option";
            value = document.getElementById(el.id).value;
          }

          if ((type == "radiobutton" || type == "checkbox") && Object.keys(valueJson_ws_p).length >= 0) {
            let checkState = false;

            //check validiton of new option
            for (const v of valueJson_ws_p) {
              if (v.type == "radiobutton" && v.id_ == id_) {
                for (const el of document.querySelectorAll(".add-option")) {
                  

                  el.id.substring(0, el.id.search("-")) == id_ && (v.name && v.name !== "") ? el.disabled = false : el.disabled = true;

                }
              } else {
                for (const el of document.querySelectorAll(".add-option")) {
               
                  //   el.classList.contains("disabled")===false  ? el.classList.add("disabled") : "";

                }
              }
            }//end for const v of valueJson_ws_p
            //end check validiton of new option
          }

          
          
          if (value_of == "required" || value_of =='allowMultiSelect' || value_of =='fileDrogAndDrop') {
            
            const id = el.id;
            value = document.getElementById(id).checked;
            
          }
         
          
          
          const step = dropZone.id.substring((dropZone.id.search("-") + 1), dropZone.id.length)
          
          addOject_emsFormBuilder(id, id_, value, type, value_of, group, step);
        });
      }

    });

  }

  for (const el of document.querySelectorAll(`.el`)) {

    el.addEventListener("dragstart", (e) => {
      
      e.dataTransfer.setData("text/plain", el.id)

    });

  }
  if(c!=-1) document.getElementById("nextBtn").style.display = "inline";
  
}

function helpLink_emsFormBuilder() {
  window.open("https://www.youtube.com/watch?v=7jS01CEtbDg", "_blank")
}

function stepName_emsFormBuilder(i) {
  document.getElementById('step-name').innerHTML = stepNames_ws[i] != "null" && stepNames_ws[i] != undefined ? `Step Name: ${stepNames_ws[i]}` : "";
  
}


function actionSendData_emsFormBuilder(){
  data ={};
  ////console.log('test');
  jQuery(function ($) {
    console.log(`formName_ws[${formName_ws}] [${document.getElementById('form_name').value}] [${form_type_emsFormBuilder}]`)
    if (state_check_ws_p==1){
      data={
        action:"add_form_Emsfb",
        value: localStorage.getItem("valueJson_ws_p"),
        name:formName_ws,
        type:form_type_emsFormBuilder,
        nonce:efb_var.nonce
      };
    }else{
      data={
        action:"update_form_Emsfb",
        value: localStorage.getItem("valueJson_ws_p"),
        name:document.getElementById('form_name').value,
        nonce:efb_var.nonce,
        id:form_ID_emsFormBuilder
      };
    }
    
    $.post(ajaxurl,data,function(res){
      
      if(res.data.r=="insert"){
        if(res.data.value && res.data.success==true){
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-thumbs-up faa-bounce animated text-primary""></i></h1><h1 class='emsFormBuilder'>Done</h1></br> <span>Good Job, Your form is builded successfully</span></br></br> <h3>FormCode: <b>${res.data.value}</b><h3></br> <input type="text" class="emsFormBuilder" value="${res.data.value}"> `;
          localStorage.removeItem('valueJson_ws_p');
        }else{
           alert(res , "error")
          
          
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3>WP Error</h3> <span>Some something went wrong please try again,Error Code:400-1</span>
          <div class="display-btn"> <button type="button" id="prevBtn" onclick="nextPrev(0)" class="p-3" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
        
        }
      }else if(res.data.r=="update" && res.data.success==true){
        document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-thumbs-up faa-bounce animated text-primary""></i></h1><h1 class='emsFormBuilder'>Form Update</h1></br> <span>Good Job, Your form updated successfully</span></br></br> <h3>FormCode: <b>${res.data.value}</b><h3></br> <input type="text" class="emsFormBuilder" value="${res.data.value}"> `;
        localStorage.removeItem('valueJson_ws_p');
        document.getElementById('back_emsFormBuilder').removeAttribute("onclick");
        document.getElementById('back_emsFormBuilder').addEventListener("click", (e) => {
          location.reload();
          return false;
        })
      }else{
        if(res.data.m==null || res.data.m.length>1){
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3>WP Error</h3> <span>Some something went wrong please try again,Error Code:400-400 <br> </span>
          <div class="display-btn"> <button type="button" id="prevBtn" onclick="nextPrev(0)" class="p-3" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
        }else{
          document.getElementById('emsFormBuilder-text-message-view').innerHTML = `<h1 class='emsFormBuilder'><i class="fas fa-exclamation-triangle faa-flash animated text-danger""></i></h1><h3>WP Error</h3> <span>${res.data.m}<br> </span>
          <div class="display-btn"> <button type="button" id="prevBtn" onclick="nextPrev(0)" class="p-3" style="display;"><i class="fa fa-angle-double-left"></i></button></div>`;
        }
       
      
      }
    })
  });

}


function unlimted_version_emsFormBuilder(m,s) {
  
  const clickFun = s==1 ? 'window.location.reload();':`close_overpage_emsFormBuilder()`;
  return `<div class=" overpage" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body">
    <h4 class="card-title"><i class="fa fa-unlock-alt"></i> Pro version</h4>
    <h5 class="card-text">${m}</h5>    
   ${(!pro_ws) ?`</br><a href="${proUrl_ws}" class="btn btn-primary" target="_blank">Get Pro version</a>`:'</br>'} 
    <button class="btn btn-danger" onClick="${clickFun}">close</a>
  </div>
  <div>
</div>`;

}

function unlimted_show_emsFormBuilder(m){
  
  document.getElementById('wpwrap').innerHTML += unlimted_version_emsFormBuilder(m,0);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();
 if (i==2) demo_emsFormBuilder=false;
}

function preview_emsFormBuilder(){
  demo_emsFormBuilder =true;
  currentTab_emsFormBuilder = 0;
  let content=`<h5 class="text-white"> Form is not created! </br> Create a form and add elemants after that try again </h5>`;
  if(valueJson_ws_p.length>1 ){
   
    //887799
    // یک شرط که اگر فرم در بادی موجود نبود ساخته شود
   content =  fun_render_view_core_emsFormBuilder(0);
  
  document.getElementById('message-area').innerHTML += `<div class=" overpage preview-overpage ${efb_var.rtl==1 ? 'rtl-text' :''}" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark">
    <h4 class="card-title text-white"><i class="fa fa-eye "></i> Preview</h4>
    </br>
   <div id ="body_emsFormBuilder"> ${content}</div>
    </br>
    <button class="btn btn-danger" onClick=" close_overpage_emsFormBuilder(2)">close</a>    
    </div>
    <div>
  </div>`;
  
  
     ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
    createStepsOfPublic() 
  }else{
    document.getElementById('message-area').innerHTML += `<div class=" overpage" id="overpage">
    <div class="overpage-mbox">
    <div class="card-body m-13 bg-dark">
      <h4 class="card-title text-white"><i class="fa fa-eye "></i> Preview</h4>
      </br>
     <div id ="body_emsFormBuilder"> ${content}</div>
      </br>
      <button class="btn btn-danger" onClick=" close_overpage_emsFormBuilder(1)">close</a>
      
    </div>
    <div>
  </div>`;
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });

}





