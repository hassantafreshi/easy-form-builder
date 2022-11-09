

let exportView_emsFormBuilder = [];
let stepsCount;
let sendBack_emsFormBuilder_pub = [];
let sessionPub_emsFormBuilder = "reciveFromClient"
let stepNames_emsFormBuilder = [`t`, ``, ``];
let currentTab_emsFormBuilder = 0;
let multiSelectElemnets_emsFormBuilder = [];
let formNameEfb = ""
let files_emsFormBuilder = [];
let addons_emsFormBuilder =""
//let trackingCode_state_emsFormBuilder = ""
let recaptcha_emsFormBuilder = '';
let poster_emsFormBuilder = '';
//let pro_w_emsFormBuilder = false;
const fileSizeLimite_emsFormBuilder = 8300000;
let select_options_emsFormBuilder = [];
let form_type_emsFormBuilder = 'form';
let valueJson_ws = []
let motus_efb = {};
let g_timeout_efb = 100
if (ajax_object_efm.hasOwnProperty('ajax_value') && typeof ajax_object_efm.ajax_value == "string") {
  g_timeout_efb = (g_timeout_efb, ajax_object_efm.ajax_value.match(/id_/g) || []).length;
  g_timeout_efb = g_timeout_efb * calPLenEfb(g_timeout_efb);
}
g_timeout_efb = typeof ajax_object_efm == "object" && typeof ajax_object_efm.ajax_value == "string" ? g_timeout_efb : 1100;
//console.log("Easy Form Builder");
setTimeout(() => {
  (function () {

    jQuery(function () {
      if (typeof ajax_object_efm == 'undefined') return;
      poster_emsFormBuilder = ajax_object_efm.poster;
      //console.log(ajax_object_efm.state);
      efb_var = ajax_object_efm;
      lan_name_emsFormBuilder =efb_var.language.slice(0,2);
      pro_efb = ajax_object_efm.pro == '1' ? true : false;
      localStorage.setItem('form_id', efb_var.id);

      if (ajax_object_efm.state != 'tracker') {
        const ajax_value = typeof (ajax_object_efm.ajax_value) == "string" ? JSON.parse(ajax_object_efm.ajax_value.replace(/[\\]/g, '')) : ajax_object_efm.ajax_value;
        if (ajax_object_efm.form_setting && ajax_object_efm.form_setting.length > 0 && ajax_object_efm.form_setting !== ajax_object_efm.text.settingsNfound) {
          form_type_emsFormBuilder = ajax_object_efm.type;
          const vs = JSON.parse(ajax_object_efm.form_setting.replace(/[\\]/g, ''));
          //console.log(vs);
          addons_emsFormBuilder = vs.addons;
          if (ajax_object_efm.type != "userIsLogin") {

            if (ajax_value[0].captcha == true) {
              sitekye_emsFormBuilder = vs.siteKey;
            } else { sitekye_emsFormBuilder = ""; }
            //trackingCode_state_emsFormBuilder = vs.trackingCode;
          } else {
            //   console.log(ajax_object_efm)
            form_type_emsFormBuilder = ajax_object_efm.type;
          }
        }
      }    
      if (ajax_object_efm.state !== 'settingError') {
        if (ajax_object_efm.state == 'form') {
          fun_render_view_efb(ajax_object_efm.ajax_value, 1);
        } else if (ajax_object_efm.state == 'tracker') {
          //console.log(`ajax_object_efm.state`,ajax_object_efm.state);
          fun_tracking_show_emsFormBuilder()
        } else if (ajax_object_efm.state == 'settingError') {

          fun_show_alert_setting_emsFormBuilder()
        } else if (ajax_object_efm.state == 'userIsLogin') {
          document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(ajax_object_efm.ajax_value);
        }
      } else {
        fun_show_alert_setting_emsFormBuilder()
      }


    });

  })();
  /* new code multiSelect start */

  (function () {
    var exportObj = {
      init: function (element, data, selectCb, options) {
        createMultiselect(element, data, selectCb, options);
      }
    };

    motus_efb.ElementMultiselect = exportObj;

  })();
}, g_timeout_efb)


/* new code multiSelect end */

function fun_render_view_efb(val, check) {

  exportView_emsFormBuilder = [];
  valueJson_ws = JSON.parse(val.replace(/[\\]/g, ''));
  valj_efb = valueJson_ws
  formNameEfb = valj_efb[0].formName;
  state_efb = "run";
  previewFormEfb('run');

}

/* function ShowTab_emsFormBuilder_view(n) {
  var x = document.getElementsByClassName("emsFormBuilder-tab-view");

  if (x[n]) {

    x[n].style.display = "block";
    x[n].classList.add("fadeInEmsFormBuilder");

  }

  if (document.getElementById("emsFormBuilder-text-prevBtn-view")) {
    if (n == 0) {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "none";
    } else {
      document.getElementById("emsFormBuilder-text-prevBtn-view").style.display = "inline";
    }
  }
  if (n == (x.length - 1)) {
    // document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = `<i class="efb ${ajax_object_efm.rtl==1 ? 'fa fa-angle-double-left' :'fa fa-angle-double-right'}"></i>`;
  } else {
    // document.getElementById("emsFormBuilder-text-nextBtn-view").innerHTML = `<i class="efb ${ajax_object_efm.rtl==1 ? 'fa fa-angle-double-left' :'fa fa-angle-double-right'}"></i>`;
  }
  validateForm_fixStepInd_view(n)
} */

/* function emsFormBuilder_nevButton_view(n) {
  //recaptcha
  if (currentTab_emsFormBuilder == 0) {
    const response = sitekye_emsFormBuilder ? grecaptcha.getResponse() || null : 'not';

    if (response != null) {
      //reCaptcha not verified
      // alert("no pass"); 
      recaptcha_emsFormBuilder = response;
      if (document.getElementById('recaptcha-message')) document.getElementById('recaptcha-message').innerHTML = ''

    } else if (sitekye_emsFormBuilder.length > 1 && response == null) {
      document.getElementById('recaptcha-message').innerHTML = ajax_object_efm.text.errorVerifyingRecaptcha;
      return;
    } else {
      //reCaptch verified
      document.getElementById('recaptcha-message').innerHTML = ''
      recaptcha_emsFormBuilder = response;
    }
  }
  //recaptcha
  if (n != 0) {
    var x = document.getElementsByClassName("emsFormBuilder-tab-view");
    if (n == 1 && !validateForm_emsFormBuilder_view()) return false;
    x[currentTab_emsFormBuilder].style.display = "none";
    currentTab_emsFormBuilder = currentTab_emsFormBuilder + n;
    stepName_emsFormBuilder_view(currentTab_emsFormBuilder);
  }

  if (n == 0) {
    document.getElementById("emsFormBuilder-firstTab-view").style.display = "block";
    document.getElementById("emsFormBuilder-firstTab-view").classList.add = "emsFormBuilder-step-view";
    document.getElementById("emsFormBuilder-text-nextprevious-view").style.display = "block";
    if (form_type_emsFormBuilder == "form") document.getElementById("emsFormBuilder-all-steps-view").style.display = "";
    document.getElementById("body_efb-title").style.display = "block";
    document.getElementById("efb-final-step").style.display = "none";
    for (el of document.querySelectorAll('.finish')) {
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
    if (form_type_emsFormBuilder == "form") document.getElementById("emsFormBuilder-all-steps-view").style.display = "none";
    document.getElementById("body_efb-title").style.display = "none";
    document.getElementById("efb-final-step").style.display = "block";
    endMessage_emsFormBuilder_view()
  }
  ShowTab_emsFormBuilder_view(currentTab_emsFormBuilder);
} */

/* function validateForm_emsFormBuilder_view() {
  //console.log("valudation");
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
            req === true ? valid = valid_password_emsFormBuilder(input) : valid = true;
            break;
          case "range":
            value = el.value;
            break;
          case 'url':
            const check = input.value.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g);
            if (check === null && input.classList.contains('require') == true) {
              valid = false;
              input.className += ' invalid';
              document.getElementById(`${id}-message`).innerHTML = ajax_object_efm.text.enterValidURL;
              //document.getElementById(`${input.id}-row`).innerHTML += `<small class="efb text-danger" id="${input.id}-message">${ajax_object_efm.text.enterValidURL}</small>`
            } else {
              valid = true;
              document.getElementById(`${id}-message`).innerHTML = ""
              input.classList.remove('invalid');
            }
            break;
          case 'file':
            const id = input.id;
            valid = input.files[0] ? true : false;
            break;
          case "text":
          case "color":
          case "number":
          case "date":
          case "url":
          case "textarea":
            value = el.value;
            if (value.length < 5) {
              state = false;
              document.getElementById(`${el.id}-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField;

            } else {
              el.classList.remove('invalid');

              document.getElementById(`${el.id}-message`).innerHTML = ""
            }
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
      }
      if (valid == false) {
        NotValidCount += 1;
        document.getElementById("emsFormBuilder-message-area-view").innerHTML = alarm_emsFormBuilder(ajax_object_efm.text.pleaseFillInRequiredFields);
        // window.scrollTo({ top: 0, behavior: 'smooth' });
        if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
      }
      if (valid == true && NotValidCount == 0) {
        document.getElementsByClassName("emsFormBuilder-step-view")[currentTab_emsFormBuilder].className += " finish";
        if (document.getElementById("alarm_emsFormBuilder")) document.getElementById("emsFormBuilder-message-area-view").innerHTML = ""
      }
    }
  } catch (re) { } finally { }
  return NotValidCount > 0 ? false : true;
} */
//function validateForm_fixStepInd_view(n) { var i, x = document.getElementsByClassName("emsFormBuilder-step-view"); for (i = 0; i < x.length; i++) { x[i].className = x[i].className.replace(" active", ""); } x[n].className += " active"; }
function createStepsOfPublic() {
  for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    //validate change
    let price = '';
    if (el.type != "submit") {
      switch (el.type) {
        case "file":
          const ob = valueJson_ws.find(x => x.id_ === el.dataset.vid);
          files_emsFormBuilder.push({ id_: ob.id_, value: "@file@", state: 0, url: "", type: "file", name: ob.name, session: sessionPub_emsFormBuilder });
          break;
        case "hidden":
          break;

      }
      el.addEventListener("change", (e) => {
        // e.preventDefault();
        //console.log(el);
        let ob = valueJson_ws.find(x => x.id_ === el.dataset.vid);
        let value = ""
        const id_ = el.dataset.vid
        let state

        switch (el.type) {
          case "text":
          case "color":
          case "number":
          case "date":
          case "textarea":
            value = el.value;
            const len = el.type == "textarea" ? 5 : el.type == "text" ? 2 : 1;
            if (value.length < len) {
              state = false;
              el.className = colorBorderChangerEfb(el.className, "border-danger");
              document.getElementById(`${el.id}-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField;
              if(document.getElementById(`${el.id}-message`).classList.contains('show'))document.getElementById(`${el.id}-message`).classList.add('show');
              const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == id_);
              if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
            } else {
              //console.log(value.search(`"`));
              if (value.search(`"`) != -1) {
                el.value = value.replaceAll(`"`, '');
                noti_message_efb(efb_var.text.error, `Don't use forbidden Character like: "`, 10, "danger");
              }
              el.className = colorBorderChangerEfb(el.className, "border-success");
             
              document.getElementById(`${el.id}-message`).classList.remove('show');
            }
            break;
          case 'url':
            const che = el.value.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g);
            if (che == null) {
              valid = false;
              el.className = colorBorderChangerEfb(el.className, "border-danger");
              document.getElementById(`${el.id}-message`).innerHTML = ajax_object_efm.text.enterValidURL;
              if(document.getElementById(`${el.id}-message`).classList.contains('show'))document.getElementById(`${el.id}-message`).classList.add('show');
            } else {
              valid = true;
              value = el.value;
             
              document.getElementById(`${el.id}-message`).classList.remove('show');
              el.className = colorBorderChangerEfb(el.className, "border-success");
            }
            break;
          case "checkbox":
          case "radio":
            value = el.value;
            if (ob.type == "switch") value = el.checked == true ? ajax_object_efm.text.on : ajax_object_efm.text.off;
            if (el.value.length > 1 || el.checked == true) {
             
              document.getElementById(`${ob.id_}_-message`).classList.remove('show');
            } else {

              document.getElementById(`${ob.id_}_-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField;
              if(document.getElementById(`${ob.id_}_-message`).classList.contains('show'))document.getElementById(`${ob.id_}_-message`).classList.add('show');

            }
            break;
          case "select-one":
          case "select":
            value = el.value;
            //console.log(el.options[el.selectedIndex].id);
           
            document.getElementById(`${ob.id_}_-message`).classList.remove('show');
            el.className = colorBorderChangerEfb(el.className, "border-success");
            if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
              let v = el.options[el.selectedIndex].id;
              v = valueJson_ws.find(x => x.id_ == v && x.value == el.value);
              //console.log(v);
              if (typeof v.price == "string") price = v.price;
            }
            break;
            case "range":
              value = el.value;
              
            document.getElementById(`${ob.id_}_-message`).classList.remove('show');
           
            
            break;
          case "email":
            state = valid_email_emsFormBuilder(el);
            value = state == true ? el.value : '';
            break;
          case "tel":
            state = valid_phone_emsFormBuilder(el);
            value = state == true ? el.value : '';
            break;
          case "password":
            state = valid_password_emsFormBuilder(el);
            value = state == true ? el.value : '';
            break;
          case "select-multiple":
            const parents = el.name;
            if (el.classList.contains('multiple-emsFormBuilder') == true) {
              //  console.log(el.children);
              for (let i = 0; i < el.children.length; i++) {
                value += el.children[i].value + ",";
              }
            }
            break;
          case "file":
            valid_file_emsFormBuilder(id_);
            //value= value ==true ? "true": "";
            break;
          case "hidden":
            break;
          case undefined:
            //select_options_emsFormBuilder            
            let check = false;
            for (ex of exportView_emsFormBuilder) {
              if (ex.id_ == el.id) {
                check = true;
                break;
              }
            }
            if (check == true) {
              ob = valueJson_ws.find(x => x.id_ === el.id);
              for (o of select_options_emsFormBuilder) {
                value += o + `,`;
              }
            }
            break;
        }






        if (value != "" || value.length > 1) {
          const type = ob.type
          const id_ob = ob.type != "paySelect" ? el.id : el.options[el.selectedIndex].id;
          //console.log(ob,id_ob);
          let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder }];

          if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
            let q = valueJson_ws.find(x => x.id_ === el.id);
            //console.log(el , valueJson_ws);
            const p = price.length > 0 ? { price: price } : { price: q.price }
            Object.assign(o[0], p)
            //console.log(q ,p,o[0]);
            fun_sendBack_emsFormBuilder(o[0]);
            fun_total_pay_efb()
          } else {

            fun_sendBack_emsFormBuilder(o[0]);
          }

        }

      });

    } else if (el.type == "submit") {
      el.addEventListener("click", (e) => {
        //console.log(el);
        const id_ = el.dataset.vid
        const ob = valueJson_ws.find(x => x.id_ === id_);
        let o = [{ id_: id_, name: ob.name, id_ob: el.id, amount: ob.amount, type: el.type, value: el.value, session: sessionPub_emsFormBuilder }];
        if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
          let q = valueJson_ws.find(x => x.id_ === el.id);
          //console.log(el , valueJson_ws);
          const p = price.length > 0 ? { price: price } : { price: q.price }
          Object.assign(o[0], p)
          //console.log(q ,p,o[0]);

          //console.log(ob,id_ob)
          fun_sendBack_emsFormBuilder(o[0]);
          fun_total_pay_efb()
        } else {

          fun_sendBack_emsFormBuilder(o[0]);
        }

      });
    }

  }//end for
} 
//end function createStepsOfPublic


function fun_sendBack_emsFormBuilder(ob) {
  //console.log(ob)
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
  if (sendBack_emsFormBuilder_pub.length>0) {
    let indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_);
    //console.log(`index[${indx}]`);
    if (indx != -1 && ob.type != "switch" && (sendBack_emsFormBuilder_pub[indx].type == "checkbox" || sendBack_emsFormBuilder_pub[indx].type == "payCheckbox" || sendBack_emsFormBuilder_pub[indx].type == "multiselect" || sendBack_emsFormBuilder_pub[indx].type == "payMultiselect" )) {
      indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_ && x.value == ob.value);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub.splice(indx, 1);
    }else if(indx != -1 && ob.value == "@file@" ){
      sendBack_emsFormBuilder_pub[indx]=ob;
    } else {
      if (indx == -1) { sendBack_emsFormBuilder_pub.push(ob) } else {
        //console.log(typeof ob.price);
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
  localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
  localStorage.setItem('formId', localStorage.getItem('form_id'))
  //console.log(sendBack_emsFormBuilder_pub); 
  //console.log(sendBack_emsFormBuilder_pub);
}
/* function fun_multiSelectElemnets_emsFormBuilder(ob) { 
  // این تابع آبجکت ارسال به سرور مدیریت می کند
  let r = 0
  if (multiSelectElemnets_emsFormBuilder.length > 0) {
    const indx = multiSelectElemnets_emsFormBuilder.findIndex(x => x.parents === ob.parents);
    if (indx !== -1) {
      const map = multiSelectElemnets_emsFormBuilder[indx];
      const keys = Object.keys(map);
      let check = 0;
      for (const key of keys) {
        if (ob[key] === map[key]) {
          check = 1;
          // اگر کی ورودی با مقدار مولتی سلکت یکی بود وضعیت تغییر کند اگر نبود اضافه شود به لیست
          // اگر یکی بود اون آپشن آن سلکت بشه
        }
        if (check === 1 && key !== 'parents' && map[key] !== undefined && ob[key] !== undefined && map[key] !== ob[key]) {
          multiSelectElemnets_emsFormBuilder[indx] = ob;
          document.getElementById(key).selected
          check = 2;
        }
      }
      if (check == 1) Object.assign(multiSelectElemnets_emsFormBuilder[indx], ob);
      // بررسی شود اگر مقدار انتخاب شده بود
    } else {
      multiSelectElemnets_emsFormBuilder.push(ob);
    }
  } else {
    multiSelectElemnets_emsFormBuilder.push(ob);
  }
  return r;
} */




/* function saveLocalStorage_emsFormBuilder_view() {
  localStorage.setItem('valueJson_ws', JSON.stringify(valueJson_ws));
} */

function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
      <div><i class="efb jump bi-exclamation-triangle-fill text-center"></i></div>
      <strong>${ajax_object_efm.text.alert} </strong>${val}
    </div>`
}

 function endMessage_emsFormBuilder_view() {
  let counter = 0;
  const stepMax = currentTab_emsFormBuilder + 1;
  let notfilled = []
  for (i = 1; i <= stepMax; i++) {
    if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x.step == i))) notfilled.push(i);
  }
  let countRequired = 0;
  let valueExistsRequired = 0;
  for (let el of exportView_emsFormBuilder) {
    if (el.required == true) {
      const id = el.id_;
      countRequired += 1;
      if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == id))) valueExistsRequired += 1;
    }
  }
  if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  //window.scrollTo({ top: 0, behavior: 'smooth' });
  if (countRequired != valueExistsRequired && sendBack_emsFormBuilder_pub.length < 1) {
    let str = ""
    currentTab_emsFormBuilder = 0;
    document.getElementById('efb-final-step').innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb jump bi-exclamation-triangle-fill text-center text-danger"></i></h1><h3>${ajax_object_efm.text.error}</h3> <span class="efb mb-2">${ajax_object_efm.text.pleaseMakeSureAllFields}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;

    // faild form
  } else {
    let checkFile = 0;
    for (file of files_emsFormBuilder) {
      if (files_emsFormBuilder.length > 0 && file.state == 1) {
        checkFile += 1;
      } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
        checkFile = -100;
        document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb jump bi-exclamation-triangle-fill text-center text-danger"></i></h1><h3 class="efb font-weight-bold">File Error</h3> <span class="efb font-weight-bold">${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
         <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
        return;

      }

    }
    if (checkFile == 0) {
      if (files_emsFormBuilder.length > 0) {
        for (const file of files_emsFormBuilder) {
          if (sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == file.id_) == -1) { sendBack_emsFormBuilder_pub.push(file); localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub)); }
        }
      }
      //final validation
      if (validation_before_send_emsFormBuilder() == true) actionSendData_emsFormBuilder()
    } else {
      const timeValue = setInterval(function () {
        //بررسی می کند همه فایل ها آپلود شده اند یا نه اگر آپلود شده باشند دیگه اجرا نمی شود و فایل ها اضافه می  شوند
        let checkFile = 0;
        for (file of files_emsFormBuilder) {
          if (files_emsFormBuilder.length > 0 && file.state == 1) {
            checkFile += 1;
          } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
            checkFile = -100;
            document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb jump bi-exclamation-triangle-fill text-center text-danger"></i></h1><h3>File Error</h3> <span>${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
               <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
            return;

          }
        }
        if (checkFile == 0) {
          // اگر همه فایل ها آپلود شده بودن
          //intervalFiles
          for (const file of files_emsFormBuilder) {
            sendBack_emsFormBuilder_pub.push(file);
            localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
          }
          if (validation_before_send_emsFormBuilder() == true) actionSendData_emsFormBuilder();
          clearInterval(timeValue);
        }
      }, 1500);
    }
    //  Ok form

  }



}



/* function stepName_emsFormBuilder_view(i) {
  document.getElementById('emsFormBuilder-step-name-view').innerHTML = stepNames_emsFormBuilder[i] != "null" && stepNames_emsFormBuilder[i] != undefined ? ` ${stepNames_emsFormBuilder[i]}` : "";
} */


function actionSendData_emsFormBuilder() {
  if (ajax_object_efm.type == "userIsLogin") return 0;
  if (form_type_emsFormBuilder != 'login') localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub));
  recaptcha_emsFormBuilder = valueJson_ws.length > 1 && valueJson_ws[0].hasOwnProperty('captcha') == true && valueJson_ws[0].captcha == true && typeof grecaptcha == "object" ? grecaptcha.getResponse() : "";
  //console.log(sendBack_emsFormBuilder_pub,recaptcha_emsFormBuilder,efb_var.id);
  //valueJson_ws[0].captcha==true &&  typeof grecaptcha =="object"  ? document.getElementById('gRecaptcha').classList.add('d-none'):'';
  if (!navigator.onLine) {
    response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.offlineMSend } });
    return;
  }
  
  //console.log('test');
  jQuery(function ($) {

    data = {
      action: "get_form_Emsfb",
      value: JSON.stringify(sendBack_emsFormBuilder_pub),
      name: formNameEfb,
      id: localStorage.getItem('form_id'),
      valid: recaptcha_emsFormBuilder,
      type: form_type_emsFormBuilder,
      nonce: efb_var.nonce
    };

    if(valj_efb[0].type=="payment" ){
      if(valj_efb[0].getway=="persiaPay"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendBack_emsFormBuilder_pub),
          name: formNameEfb,
          payid: localStorage.getItem('PayId'),
          id: localStorage.getItem('form_id'),
          valid: recaptcha_emsFormBuilder,
          type: form_type_emsFormBuilder,
          nonce: efb_var.nonce,
          payment: 'persiaPay',
          auth:get_authority_efb
        };
      }else if(valj_efb[0].getway=="stripe"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendBack_emsFormBuilder_pub),
          name: formNameEfb,
          id: localStorage.getItem('form_id'),
          payid: localStorage.getItem('PayId'),
          valid: recaptcha_emsFormBuilder,
          type: form_type_emsFormBuilder,
          payment: 'stripe',
          nonce: efb_var.nonce

        };
      }

      
    }

    console.log(data);

    $.ajax({
      type: "POST",
      async: false,
      url: ajax_object_efm.ajax_url,
      data: data,
      success: function (res) {

        if (localStorage.getItem("formId")) { localStorage.removeItem('formId'); }
        response_fill_form_efb(res)
        //localStorage.removeItem('PayId');
      },
      error: function (res) {
        console.error(res);
        response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 } })
      }

    })

  });



}




function valid_email_emsFormBuilder(el) {
  //if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove(); 
  let check = 0;
  const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
  check += el.value.match(format) ? 0 : 1;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");
    document.getElementById(`${el.id}-message`).innerHTML = ajax_object_efm.text.enterTheEmail;
    if(document.getElementById(`${el.id}-message`).classList.contains('show'))document.getElementById(`${el.id}-message`).classList.add('show');
    const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
   
    document.getElementById(`${el.id}-message`).classList.remove('show');
  }
  // if (check>0) alert("Please enter email address");
  return check > 0 ? false : true
}


function valid_password_emsFormBuilder(el) {
  let check = 0;
  const id = el.id;

  if (el.value.length < 3) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    document.getElementById(`${id}-message`).innerHTML = ajax_object_efm.text.enterThePassword;
    if(document.getElementById(`${el.id}-message`).classList.contains('show'))document.getElementById(`${el.id}-message`).classList.add('show');
    return false;
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    document.getElementById(`${id}-message`).innerHTML = ""
    document.getElementById(`${id}-message`).classList.remove('show');
    // document.getElementById('emsFormBuilder-text-nextBtn-view').disabled = false;    
    return true;
  }

}

function valid_phone_emsFormBuilder(el) {
  // if (document.getElementById(`${el.id}-message`)) document.getElementById(`${el.id}-message`).remove(); 
  let check = 0;
  const format = /^\s*(?:\+?(\d{1,3}))?([-. (]*(\d{3})[-. )]*)?((\d{3})[-. ]*(\d{2,4})(?:[-.x ]*(\d+))?)\s*$/gm;
  const id = el.id;
  check += el.value.match(format) ? 0 : 1;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == el.dataset.vid);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    document.getElementById(`${id}-message`).innerHTML = ajax_object_efm.text.enterThePhones;
    if(document.getElementById(`${el.id}-message`).classList.contains('show'))document.getElementById(`${el.id}-message`).classList.add('show');
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    document.getElementById(`${id}-message`).innerHTML = ""
    document.getElementById(`${id}-message`).classList.remove('show');
  }
  // if (check>0) alert("Please enter email address");
  return check > 0 ? false : true
}


function valid_file_emsFormBuilder(id) {
  //console.log(id);
  let msgEl = document.getElementById(`${id}_-message`);
  //console.log(`id[${id}]`, msgEl);
  msgEl.innerHTML = "";
  msgEl.classList.remove('show');
  let file = ''
  if (true) {
    const f = valueJson_ws.find(x => x.id_ === id);
    file = f.file && f.file.length > 3 ? f.file : 'Zip';
  }
  let check = 0;
  let rtrn = false;
  let fileName = ''
  const i = `${id}_`;
  let message = "";
  const el = document.getElementById(i);
  //console.log(i,el.files)
  if (el.files[0] && el.files[0].size < fileSizeLimite_emsFormBuilder) {
    const filetype = el.files[0].type.length > 1 ? el.files[0].type : el.files[0].name.slice(((el.files[0].name.lastIndexOf(".") - 1) * -1))
    //console.log(file,el.files[0].name,filetype);
    const r = validExtensions_efb_fun(file, filetype)
    if (r == true) {
      check = +1;
      message = `${ajax_object_efm.text.pleaseUploadA} ${ajax_object_efm.text[file]}`;
    }
  }

  if (check > 0) {

    msgEl.innerHTML = "";
    fun_upload_file_emsFormBuilder(id, file);

    rtrn = true;
  } else {
    if (el.files[0] && message.length < 2) message = el.files[0].size < fileSizeLimite_emsFormBuilder ? `${ajax_object_efm.text.pleaseUploadA} ${ajax_object_efm.text[file]}` : ajax_object_efm.text.fileSizeIsTooLarge;
    //console.log('test');  
    const newClass = colorTextChangerEfb(msgEl.className, "text-danger");
    //console.log(newClass,typeof newClass,newClass!=false)
    newClass!=false ? msgEl.className=newClass:0;
    msgEl.innerHTML = message;
    if(!msgEl.classList.contains('show'))msgEl.classList.add('show');
    rtrn = false;
  }

  return rtrn;
}

/* function findPosition(obj) {
  var currenttop = 0;
  if (obj.offsetParent) {
    do {
      currenttop += obj.offsetTop;
    } while ((obj = obj.offsetParent));
    return [currenttop];
  }
} */







function fun_tracking_show_emsFormBuilder() {
  document.getElementById("body_tracker_emsFormBuilder").innerHTML = ` 
  <div class="efb  ${ajax_object_efm.rtl == 1 ? 'rtl-text' : ''}" >
                <div class="efb  card card-public row mb-3 " id="body_efb-track">
                    <h4 class="efb  title-holder  col-12 mt-4"><i class="efb  bi-check2-square title-icon mx-1"></i> ${ajax_object_efm.text.pleaseEnterTheTracking}</h4>
                <div class="efb  mb-5 row col-md-12">
                        <label for="trackingCodeEfb" class="efb form-label mx-2 col-12">
                        ${ajax_object_efm.text.trackingCode}:<span class="efb text-danger mx-1">*</span></label>
                        <div class="efb  col-12 text-center mx-2 row">
                        <input type="text" class="efb  input-efb form-control border-d efb-rounded mb-4 text-labelEfb h-l-efb" placeholder="${ajax_object_efm.text.entrTrkngNo}" id="trackingCodeEfb">
                         <button type="submit" class="efb  btn btn-pinkEfb col-12 text-white"  id="vaid_check_emsFormBuilder" onclick="fun_vaid_tracker_check_emsFormBuilder()">
                        <i class="efb  bi-search"></i> ${ajax_object_efm.text.search}  </button>
                        </div>
                    </div>
                </div>
                <!-- recaptcha  -->
                ${sitekye_emsFormBuilder ? `<div class="efb  row mx-3"><div class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                <!-- recaptcha end  -->
            <!-- efb -->            
        </div>
        <div id="alert_efb" class="efb mx-5"></div>
`
}

function fun_vaid_tracker_check_emsFormBuilder() {
  el = document.getElementById('trackingCodeEfb').value;
  const innrBtn = document.getElementById('vaid_check_emsFormBuilder').innerHTML;
  document.getElementById('vaid_check_emsFormBuilder').innerHTML = `<i class="efb  bi-hourglass-split"></i>`
  document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
  if (el.length < 5) {
    document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
    document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
    noti_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.trackingCodeIsNotValid, 117, 'danger')
    //document.getElementById('emsFormBuilder-message-area-track').innerHTML=alarm_emsFormBuilder(efb_var.text.trackingCodeIsNotValid)
  } else {
    if (currentTab_emsFormBuilder == 0) {
      const response = sitekye_emsFormBuilder ? grecaptcha.getResponse() || null : 'not';
      if (response == null) {
        //reCaptcha not verified
        // alert("no pass"); 
        noti_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.checkedBoxIANotRobot, 117, 'danger')
        //document.getElementById('emsFormBuilder-message-area-track').innerHTML=alarm_emsFormBuilder(efb_var.text.checkedBoxIANotRobot);
        return;
      }
      else {
        //reCaptch verified
        recaptcha_emsFormBuilder = response;
        jQuery(function ($) {
          data = {
            action: "get_track_Emsfb",
            value: el,
            name: formNameEfb,
            valid: recaptcha_emsFormBuilder,
            nonce: ajax_object_efm.nonce,
          };

          $.ajax({
            type: "POST",
            async: false,
            url: ajax_object_efm.ajax_url,
            data: data,
            success: function (res) {
              document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
              document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
              //console.log(res);
              response_Valid_tracker_efb(res)
            },
            error: function () {
              document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
              document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
              response_Valid_tracker_efb({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 } })
            }
          })
        });
      }
    }
  }
}



function emsFormBuilder_show_content_message(value, content) {
  const msg_id = value.msg_id;
  const userIp = "XXXXXXXXX";
  const track = value.track;
  //console.log(value,content,typeof pro_els_efb);
  const date = value.date ;
  const val = JSON.parse(replaceContentMessageEfb(value.content));
  let m = fun_emsFormBuilder_show_messages(val, "user", track, date);
 

  for (c of content) {
    //console.log(c);
    /* 
     let val =replaceContentMessageEfb(c.content);
    console.log(val);
     */
    const val = JSON.parse(c.content.replace(/[\\]/g, ''));
    m += `<div class="efb   mb-3"><div class="efb  clearfix"> ${fun_emsFormBuilder_show_messages(val, c.rsp_by, track, c.date)}</div></div>`
  }
  //reply  message ui
  let replayM = `<div class="efb mx-2 mt-2"><div class="efb form-group mb-3" id="replay_section__emsFormBuilder">
  <label for="replayM_emsFormBuilder" class:'mx-1">${ajax_object_efm.text.reply}:</label>
  <textarea class="efb form-control border-d" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
  
  </div>
  <div class="efb col text-right row my-2 mx-1">
  <button type="submit" class="efb btn btn-r btn-primary btn-lg" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">${ajax_object_efm.text.reply} </button>
  <!-- recaptcha  -->
  ${sitekye_emsFormBuilder ? `<div class="efb row mx-3"><div class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" id="recaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
  <!-- recaptcha end  -->
  <p class="efb mx-2 my-1 text-pinkEfb efb" id="replay_state__emsFormBuilder">  </p>
  </div></div>
  `
  const body = `
  <div class="efb modal-header efb">
  <h5 class="efb modal-title efb"><i class="efb  bi-chat-square-text mx-2 mx-2"></i>
   <span id="settingModalEfb-title">${ajax_object_efm.text.response}</span></h5>
 </div>
  <div class="efb modal-body overflow-auto py-0 my-0  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="resp_efb">
    ${m} 
   </div>
   ${replayM}
   </div>
   </div>
</div>
<div>
</div></div>`;

  return body;


  //window.scrollTo({ top: 0, behavior: 'smooth' });

}



function fun_emsFormBuilder_show_messages(content, by, track, date) {
  //console.log('fun_emsFormBuilder_show_messages');
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = "visitor")
  let m = `<Div class="efb bg-response efb card-body my-2 py-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
  <div class="efb  form-check">
  <div>
      <p class="efb small fs-7 mb-0"><span>${ajax_object_efm.text.by}:</span> ${by}</p>
    ${track != 0 ? `<p class="efb small fs-7 mb-0"><span> ${ajax_object_efm.text.trackingCode}:</span> ${track} </p>` : ''}
    <p class="efb small fs-7 mb-0"><span>${ajax_object_efm.text.ddate}:</span> ${date} </p>  
  </div>
  <!-- <div class="efb col fs-4 h-d-efb pointer-efb text-darkb d-flex justify-content-end bi-download" data-toggle="tooltip" data-placement="bottom" title="${efb_var.text.download}"></div> -->
  </div>
 <hr>
 <h6 class="efb  text-dark my-2">${ajax_object_efm.text.response} </h6>`;;

 
  content.sort((a, b) => (a.amount > b.amount) ? 1 : -1);
  let list = []
  let currency = content[0].hasOwnProperty('paymentcurrency') ? content[0].paymentcurrency :'usd';
  for (const c of content) {

    let value ="<b></b>";
    if(c.hasOwnProperty('value')){
      c.value = replaceContentMessageEfb(c.value);
      //console.log(c.value);
      value = `<b>${c.value.toString().replaceAll('@efb!', ',')}</b>`;
    } 
    s = false;
  

    if (c.value == "@file@" && list.findIndex(x => x == c.url) == -1) {
      s = true;
      list.push(c.url);
      $name = c.url.slice((c.url.lastIndexOf("/") + 1), (c.url.lastIndexOf(".")));
      //console.log($name,c.type ,"URL", c.url);
      if (c.type == "Image" || c.type == "image") {
        value = `</br><img src="${c.url}" alt="${c.name}" class="efb img-thumbnail m-1">`
      } else if (c.type == "Document" || c.type == "document" ||  c.type == "allformat" ) {
        value = `</br><a class="efb btn btn-primary m-1" href="${c.url}" target="_blank" >${ajax_object_efm.text.download}</a>`
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
      //console.log(c.value);
      s = true;
      value = `<img src="${c.value}" alt="${c.name}" class="efb img-thumbnail">`;
      m += value;
      //console.log(value);
    } else if (c.type == "maps") {

      if (typeof (c.value) == "object") {
        s = true;
        value = `<div id="${c.id_}-map" data-type="maps" class="efb  maps-efb h-d-efb  required " data-id="${c.id_}-el" data-name="maps"><h1>maps</h1></div>`;
        valj_efb.push({ id_: c.id_, mark: -1, lat: c.value[0].lat, lng: c.value[0].lng, zoom: 9, type: "maps" })
        marker_maps_efb = c.value;
        initMap();
        m += value;
      }
    } else if (c.type == "rating") {
      s = true;
      value = `<div class='efb fs-4 star-checked star-efb mx-1 ${efb_var.rtl == 1 ? 'text-end' : 'text-start'}'>`;
      for (let i = 0; i < parseInt(c.value); i++) {
        value += `<i class="efb bi bi-star-fill"></i>`
      }
      value += "</div>";
      m += value;
    }
    if (c.id_ == 'passwordRegisterEFB') { m += value; value = '**********' };
    if ((s == true && c.value == "@file@") || (s == false && c.value != "@file@")){
       m += `<p class="efb fs-6 my-0 efb  form-check">${c.name}: <span class="efb mb-1"> ${value !== '<b>@file@</b>' ? value : ''}</span>`
       if(c.type.includes('pay')&& c.id_!="payment") {

        m+=`<span class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end">${Number(c.price).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span>`
      }
       m+=`</p>`;
      }


    if (c.type == "payment") {
      if(c.paymentGateway == "stripe"){
        m += `<p class="efb fs-6 my-0">${efb_var.text.payment} ${efb_var.text.id}:<span class="efb mb-1"> ${c.paymentIntent}</span></p>`
        m += `<div class="efb mx-3 fs7 text-capitalize">
            <p class="efb my-0">${efb_var.text.ddate}:<span class="efb mb-1"> ${c.paymentCreated}</span></p>
            <p class="efb my-0">${efb_var.text.updated}:<span class="efb mb-1"> ${c.updatetime}</span></p>
            <p class="efb  my-0">${efb_var.text.methodPayment}:<span class="efb mb-1"> ${c.paymentmethod}</span></p>
            ${c.paymentmethod != 'charge' ? `<p class="efb fs-6 my-0">${efb_var.text.interval}:<span class="efb mb-1 text-capitalize"> ${c.interval}</span></p>` : ''}
            </div>`
      }else {
        /* 
        'id_' =>"payment",
										'name' => "peyment",
										'amount' => "0",
										'total' => $amount,
										'type' => "peyment",
										"paymentGateway"=>$payment_getWay,
										"paymentCreated"=>date('Y-m-d H'),
										"paymentmethod"=>'کارت',
										"paymentIntent"=>sanitize_text_field($_POST['auth']),
										"paymentCard"=>$result->data->card_pan,
										"refId"=>$result->data->ref_id
        */
        m += `<p class="efb fs-6 my-0">${efb_var.text.payment} ${efb_var.text.id}:<span class="efb mb-1"> ${c.paymentIntent}</span></p>`
        m += `<div class="efb mx-3 fs7 text-capitalize">
            <p class="efb my-0">${efb_var.text.payAmount}:<span class="efb mb-1"> ${c.total} ریال</span></p>
            <p class="efb  my-0">${efb_var.text.methodPayment}:<span class="efb mb-1"> ${c.paymentmethod}</span></p>
            <p class="efb my-0">${efb_var.text.ddate}:<span class="efb mb-1"> ${c.paymentCreated}</span></p>
           <!-- <p class="efb my-0">شماره کارت:<span class="efb mb-1"> ${c.paymentCard}</span></p>
            <p class="efb my-0">کد پیگیری زرین پال<span class="efb mb-1"> ${c.refId}</span></p> -->
            </div>`
      }
      
    }
  }
  m += '</div>';
  //console.log('m',m);
  return m;
}


function fun_send_replayMessage_emsFormBuilder(id) {
  //پاسخ مدیر را ارسال می کند به سرور 
  let message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g, '@efb@nq#');
  document.getElementById('replay_state__emsFormBuilder').innerHTML = `<i class="efb bi-hourglass-split mx-1"></i> ${efb_var.text.sending}`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  // +='disabled fas fa-spinner fa-pulse';
  const by = ajax_object_efm.user_name.length > 1 ? ajax_object_efm.user_name : efb_var.text.guest;
  //const ob = [{ name: 'Message', value: message, by: by }];
 /* new attachment */
 //console.log('message',message);
  const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: by , session: sessionPub_emsFormBuilder}];
  fun_sendBack_emsFormBuilder(ob[0])
  /*end  new attachment */
  let isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);
  if (message.length < 1 || isHTML(message)) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6><i class="efb bi-exclamation-triangle-fill text-danger"></i>${efb_var.text.error}${efb_var.text.youCantUseHTMLTagOrBlank}</h6>`;
    //noti_message_efb(efb_var.text.error, efb_var.text.youCantUseHTMLTagOrBlank, 7 , 'danger')
    return;
  } else {
    fun_send_replayMessage_ajax_emsFormBuilder(sendBack_emsFormBuilder_pub, id)
  }


}


function fun_send_replayMessage_ajax_emsFormBuilder(message, id) {
  if (message.length < 1) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    // noti_message_efb(fb_var.text.enterYourMessage, 5 , 'warning')
    document.getElementById('replayM_emsFormBuilder').innerHTML = "";
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    return;
  }
  jQuery(function ($) {
    data = {
      action: "set_rMessage_id_Emsfb",
      type: "POST",
      id: id,
      valid: recaptcha_emsFormBuilder,
      message: JSON.stringify(message),
      nonce: ajax_object_efm.nonce,
      type: form_type_emsFormBuilder,

    };
    $.ajax({
      type: "POST",
      async: false,
      url: ajax_object_efm.ajax_url,
      data: data,
      success: function (res) { response_rMessage_id(res, message) },
      error: function () { response_rMessage_id({ success: false, data: { success: false, m: ajax_object_efm.text.eJQ500 } }) }

    })

  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
  //message,res.data.by,0,0,date
  const resp = fun_emsFormBuilder_show_messages(message, by, track, date);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}


function fun_show_alert_setting_emsFormBuilder() {
  const m = `<div class="efb alert alert-danger" role="alert"> <h2 class="efb font-weight-bold">
            ${ajax_object_efm.text.error}</br>
            ${ajax_object_efm.text.formIsNotShown}</br>
            <a href="https://www.youtube.com/embed/a1jbMqunzkQ"  target="_blank" class="efb font-weight-normal">${ajax_object_efm.text.pleaseWatchTutorial}</a> </h2> </div>`
  if (document.getElementById('body_emsFormBuilder')) {
    document.getElementById('body_emsFormBuilder').innerHTML = m;
  } else if (document.getElementById('body_tracker_emsFormBuilder')) {
    document.getElementById('body_tracker_emsFormBuilder').innerHTML = m;
  } else {
    window.alert(`${ajax_object_efm.text.error} ${ajax_object_efm.text.formIsNotShown}`)
  }
}




function validation_before_send_emsFormBuilder() {
  // console.log( localStorage.getItem('sendback'),sendBack_emsFormBuilder_pub)
  const count = [0, 0]
  let fill = 0;
  let require = 0;
  for (const v of valueJson_ws) {
    require += v.required == true && v.type !== "file" ? 1 : 0;
    if (v.type == "file") {

      if (document.getElementById(`${v.id_}_`).files[0] == undefined && v.required == true) {
        fill -= 1;
      }
    }
  }
  for (const row of sendBack_emsFormBuilder_pub) {
    //console.log(row);
    count[0] += 1;
    if (row.value == "@file@") {

      const indx = valueJson_ws.findIndex(x => x.id_ == row.id_);
      if (indx != -1) {
        //  fill += valueJson_ws[indx].required == true ? 1 : 0;  
        if (row.url.length > 5) {
          fill += valueJson_ws[indx].required == true ? 1 : 0;
          count[1] += 1;
        }
      }
    } else if (row.type != "@file@" && row.type != "payment") {
      const indx = valueJson_ws.findIndex(x => x.id_ == row.id_);

      if(indx!=-1){
        if ( (valueJson_ws[indx].type == "multiselect" || valueJson_ws[indx].type == "option" || valueJson_ws[indx].type == "Select"
        || valueJson_ws[indx].type == "payMultiselect" || valueJson_ws[indx].type == "paySelect")) {
        const exists = valueJson_ws[indx].type == "multiselect" || valueJson_ws[indx].type == "payMultiselect" ? valueJson_ws.findIndex(x => x.parent == valueJson_ws[indx].id_) : valueJson_ws.findIndex(x => x.parents == valueJson_ws[indx].id_);
        fill += valueJson_ws[indx].required == true && exists > -1 ? 1 : 0;
        // console.log(valueJson_ws[indx].id_ ,exists ,fill)
      } else {
        fill += valueJson_ws[indx].required == true ? 1 : 0;
      }
      if (row.value.length > 0) count[1] += 1;
      }

     
    } else {
      if (row.value.length > 0) count[1] += 1;
    }

  }
  require = require > fill ? 1 : 0;
  if ((count[1] == 0 && count[0] != 0) || (count[0] == 0 && count[1] == 0) || require == 1) {
    document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb jump bi-exclamation-triangle-fill text-center text-danger fs-2 efb"></i></h1><h3 class="efb fs-3 efb text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 fs-5 efb text-muted">${ajax_object_efm.text.error} ${require != 1 ? ajax_object_efm.text.PleaseFillForm : ajax_object_efm.text.pleaseFillInRequiredFields} </br></span>
     <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
    if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
    //   console.log(`sendBack_emsFormBuilder_pub`,sendBack_emsFormBuilder_pub)
    for (const v of valueJson_ws) {
      //dadfile
      if (v.type != 'file' && v.type != 'dadfile' && v.type != 'checkbox' && v.type != 'radiobutton' && v.type != 'option' && v.type != 'multiselect' && v.type != 'select' && v.type != 'payMultiselect' && v.type != 'paySelect' && v.type != 'payRadio' && v.type != 'payCheckbox') {
        // console.log(v);
        (v.id_ && document.getElementById(v.id_).value.length < 5) ? document.getElementById(`${v.id_}-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField : 0
        return false;
      }
    }

  } else {
    return true;
  }
}


function show_user_profile_emsFormBuilder(ob) {
  return `<div class="efb mt-5"><div class="efb card-block text-center text-dark ">
              <div class="efb mb-3 d-flex justify-content-center"> <img src="${ob.user_image}" class="efb userProfileImageEFB" alt="${ob.display_name}"> </div>
              <h6 class="efb  mb-1 d-flex justify-content-center text-dark">${ob.display_name}</h6> <p class="efb ">${ob.user_login}</p>                          
              <button type="button"  class="efb btn btn-lg btn-danger efb mt-1 " onclick="emsFormBuilder_logout()">${ajax_object_efm.text.logout}</button>
          </div> </div>`
}


function emsFormBuilder_logout() {
  document.getElementById('body_efb').innerHTML = loading_messge_efb();

  form_type_emsFormBuilder = "logout";
  formNameEfb = "logout";
  ajax_object_efm.type = "logout";
  sendBack_emsFormBuilder_pub = { logout: true };
  recaptcha_emsFormBuilder = '';
  // console.log('emsFormBuilder_logout');

  actionSendData_emsFormBuilder();
}

/* new Code */


/* password recovery */
function Show_recovery_pass_efb() {
  let el = document.getElementById(`recoverySectionemsFormBuilder`);
  el.style.display = el.style.display == "none" ? "block" : "none";

  document.getElementById('efb-final-step').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});

  //btn_recovery_pass_efb
  //if data-id == 1 
  el = document.getElementById('btn_recovery_pass_efb');
  el.disabled = true;

  if (el.dataset.id == 1) {

    el.dataset.id = 0;
    const us = document.getElementById('username_recovery_pass_efb');
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    el.addEventListener("click", (e) => {
      form_type_emsFormBuilder = "recovery";
      formNameEfb = form_type_emsFormBuilder;
      sendBack_emsFormBuilder_pub = { email: us.value };
      document.getElementById('efb-final-step').innerHTML = `<h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder"></h1> <h3>${ajax_object_efm.text.pleaseWaiting}<h3>`
      actionSendData_emsFormBuilder()
    })

    us.addEventListener("keyup", (e) => {
      const check = us.value.match(format) ? 0 : 1;
      // console.log(check , us.value)
      if (check == 0) { el.classList.remove('disabled') } else { el.classList.contains('disabled') != true ? el.classList.add('disabled') : 0 }
    })

  }

}
/* password recovery */


function response_fill_form_efb(res) {
  if (res.data.success == true) {

    if(valj_efb.length>0 && valj_efb[0].hasOwnProperty('thank_you')==true && valj_efb[0].thank_you=='rdrct'){
      document.getElementById('efb-final-step').innerHTML = `
      <h3 class="efb ">${efb_var.text.sentSuccessfully}</h3>
      <h3 class="efb">Please wait while redirected <a class="efb text-darkb" href="${res.data.m}">${efb_var.text.orClickHere}</a></h3>
      `      
      window.location.href = res.data.m;
      return ;
    }
    //form_type_emsFormBuilder یک پیام مرتبت نشان دهد
    switch (form_type_emsFormBuilder) {
      case 'form':
      case 'survey':
      case 'payment':
        document.getElementById('efb-final-step').innerHTML = funTnxEfb(res.data.track)
        break;
      case 'subscribe':
        document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb fas fa-thumbs-up faa-bounce animated text-primary""></i></h1><h3 class='efb emsFormBuilder'>${ajax_object_efm.text.thankYou}</h1></br> <span>${ajax_object_efm.text.YouSubscribed}</span></br></br></h3>`;
        break;
      case 'recovery':
      case 'register':
        const m = form_type_emsFormBuilder !='recovery' ? valj_efb[0].thank_you_message : ajax_object_efm.text.checkYourEmail;
        document.getElementById('efb-final-step').innerHTML = funTnxEfb('','',m );
        break;
      case 'login':


        if (res.data.m.state == true) {
          document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(res.data.m);

          location.reload();
        } else {
          //pro_w_emsFormBuilder = res.data.m.pro;
          document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder text-center fs-5 efb mb-0 mt-5'><i class="efb fs-2 bi-exclamation-triangle-fill text-danger"></i></h3> <span> <br>${res.data.m.error}</span>
           </br>
           <a href="#" id="btn_Show_recovery_efb" class="efb   emsFormBuilder " onClick="Show_recovery_pass_efb()" >${ajax_object_efm.text.passwordRecovery} </a>
           <div class="efb p-5 " id="recoverySectionemsFormBuilder" style="display: none;">     
              <input type="email" id="username_recovery_pass_efb" class="efb px-2 mb-1 emsFormBuilder_v form-control h-d-efb efb-square" placeholder="Email" >
              <a href="#" id="btn_recovery_pass_efb" class="efb btn btn-lg btn-block btn-pink mb-2 get-emsFormBuilder disabled" data-id="1" >${ajax_object_efm.text.send}</a>
              </div>
              <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].button_color}   ${valj_efb[0].corner}   ${valj_efb[0].el_height}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div>
              `;
        }
        break;
      case "logout":
        location.reload();
        break;

    }
    if (document.getElementById('body_efb')) document.getElementById('body_efb').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
  } else {
    document.getElementById('efb-final-step').innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb jump bi-exclamation-triangle-fill text-center text-danger efb fs-3"></i></h1><h3 class="efb  fs-3 text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5"> ${res.data.m}</span>
    <div class="efb m-1"> <button id="prev_efb_send" type="button" class="efb btn efb ${valj_efb[0].hasOwnProperty('button_color') ? valj_efb[0].button_color : 'btn-darkb'}   ${valj_efb[0].hasOwnProperty('corner') ? valj_efb[0].corner : 'efb-square'}   ${valj_efb[0].hasOwnProperty('el_height') ? valj_efb[0].el_height : 'h-l-efb'}  p-2 text-center  btn-lg  " onClick="fun_prev_send()"><i class="efb  ${valj_efb[0].button_Previous_icon} ${valj_efb[0].button_Previous_icon} ${valj_efb[0].icon_color} mx-2 fs-6 " id="button_group_Previous_icon"></i><span id="button_group_Previous_button_text" class="efb  ${valj_efb[0].el_text_color} ">${valj_efb[0].button_Previous_text}</span></button></div></div>`;
  }

}

function response_Valid_tracker_efb(res) {

  if (res.data.success == true) {
    document.getElementById('body_efb-track').innerHTML = emsFormBuilder_show_content_message(res.data.value, res.data.content)
    /* attachment reply */
    setTimeout(() => {
     if(typeof reply_attach_efb =='function') reply_attach_efb(res.data.value.msg_id)
    }, 50);
     /* end attachment reply */
 /*    jQuery('#replayM_emsFormBuilder').on('keypress', 
    function (event) {
      console.log('replayM_emsFormBuilder',event.which)
        if (event.which == '13') {
            event.preventDefault();
        }
    }); */
  } else {
    noti_message_efb(ajax_object_efm.text.error, res.data.m, 15, 'danger')
    document.getElementById('body_efb-track').innerHTML = `<div class="efb text-center"><h3 class='efb emsFormBuilder mt-3'><i class="efb jump  bi-exclamation-triangle-fill text-center text-danger efb fs-1"></i></h1><h3 class="efb  fs-3 text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5">${ajax_object_efm.text.somethingWentWrongTryAgain} <br>${ajax_object_efm.text.error} ${res.data.m} </br></span>
     <div class="efb display-btn emsFormBuilder"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="efb  btn btn-darkb m-5" onclick="window.location.href=window.location.href" style="display;"><i class="efb ${ajax_object_efm.rtl == 1 ? 'bi-arrow-right' : 'bi-arrow-left'}"></i></button></div></div>`;

  }
}

function response_rMessage_id(res, message) {
  if (res.success == true && res.data.success == true) {
    document.getElementById('replayM_emsFormBuilder').value = "";
    document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
     /* attachment reply */
     if(document.getElementById('name_attach_efb')) document.getElementById('name_attach_efb').innerHTML =ajax_object_efm.text.file
     /* end attachment reply */
    const date = Date();
    fun_emsFormBuilder__add_a_response_to_messages(message, res.data.by, 0, 0, date);
    const chatHistory = document.getElementById("resp_efb");
    chatHistory.scrollTop = chatHistory.scrollHeight;
  } else {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<p class="efb text-danger">${res.data.m}</p>`;
    noti_message_efb(ajax_object_efm.text.error, res.data.m, 15, 'danger')
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
  }
}



function loadCaptcha_efb() {

  if (!window.grecaptcha || !window.grecaptcha.render) {
    setTimeout(() => {
      this.loadCaptcha_efb();
    }, 500);
  } else {
    if (valj_efb[0].steps == 1) { document.getElementById('btn_send_efb').classList.toggle('disabled'); }
    grecaptcha.render('gRecaptcha', {
      'sitekey': sitekye_emsFormBuilder
    });
  }
};




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