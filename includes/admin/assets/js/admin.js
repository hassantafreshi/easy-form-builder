// Multi step form wizard builder (core)
// Created by: Hassan Tafreshi
// Email: hasan.tafreshi@gmail.com
// WhiteStudio.team

let state_check_ws_p = 1;
let valueJson_ws_p = [];
let exportJson_ws = [];
let pro_ws = false;
let form_ID_emsFormBuilder = 0;
let form_type_emsFormBuilder = 'form';
const efb_version =3.6;
let wpbakery_emsFormBuilder =false;
//let state_view_efb = 0;
if (localStorage.getItem("valueJson_ws_p")) localStorage.removeItem('valueJson_ws_p');




jQuery(function () {
  state_check_ws_p = Number(efb_var.check);
  setting_emsFormBuilder=efb_var.setting;
  if(localStorage.getItem('v_efb')==null ||localStorage.getItem('v_efb')!=efb_var.v_efb ){
    localStorage.setItem('v_efb',efb_var.v_efb);
    location.reload(true);
  }
  pro_ws = (efb_var.pro == '1' || efb_var.pro == true) ? true : false;
  if (typeof pro_whitestudio !== 'undefined') { pro_ws = pro_whitestudio; } else { pro_ws = false; }
  //historyload 1
  
  if (state_check_ws_p==1) {
    history.replaceState("templates",null,'?page=Emsfb_create'); 
    add_dasboard_emsFormBuilder(); 
  }else if(state_check_ws_p==2){
    timeout=500;
    
    fun_timeout=()=>{
      setTimeout(() => {        
        if(typeof addons_efb =='undefined'){
          timeout +=100
          
          fun_timeout();
        }else{
          add_addons_emsFormBuilder();}
       }, timeout); 
    }
    fun_timeout();
  }
})



//remove footer of admin
document.getElementById('wpfooter').remove();


function saveLocalStorage_emsFormBuilder() {

  localStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
  localStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
}


function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
    <div class="efb emsFormBuilder"><i class="efb fas fa-exclamation-triangle faa-flash animated"></i></div>
    <strong>${efb_var.text.alert} </strong>${val}
  </div>`
}



function Link_emsFormBuilder(state) {
  let link = 'https://whitestudio.team/document'
  const github = 'https://github.com/hassantafreshi/easy-form-builder/wiki/'
  if(efb_var.language != "fa_IR"){
    switch (state) {
      case 'publishForm':
        link = "https://youtu.be/AnkhmZ5Cz9w";
        break;
      case 'createSampleForm':
      case 'tutorial':
        link += valj_efb.length < 1 || valj_efb[0].type != "s/payment" ? "s/how-to-create-your-first-form-with-easy-form-builde" : "How-to-Create-a-Payment-Form-in-Easy-Form-Builder";
        break;
      case 'stripe':
        //stripe
        link = "https://whitestudio.team/documents/how-to-setup-and-use-the-stripe-on-easy-form-builder";
        break;
      case 'ws':
        link = 'https://whitestudio.team/';
        break;
      case 'price':
        link = 'https://whitestudio.team/#price';
        break;
      case 'efb':
        link = "https://wordpress.org/plugins/easy-form-builder/";
        break;
      case 'wiki':
        link = `https://whitestudio.team/documents/`;
        break;
      case 'EmailNoti':
        link += "s/How-to-Set-Up-Form-Notification-Emails-in-Easy-Form-Builder";
        break;
      case 'redirectPage':
        link += "s/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder";
      break;
      case 'AdnSPF':
        //AdnSPF == strip payment
        link += "s/offline-forms-addon/";
        break;
      case 'AdnOF':
        //AdnOF == offline form
        link += 's/how-to-setup-and-use-the-stripe-on-easy-form-builder/';
       
        break;
      case 'wpbakery':
        //AdnOF == offline form
        link += 's/wpbakery-easy-form-builder-v34/';
        //link += "s/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder";
        break;
      case 'AdnPPF':
        //AdnPPF == persia payment
        link = 'https://whitestudio.team';
        break;
      case 'AdnATC':
        // AdnATC == advance tracking code
      case 'AdnSS':
        //AdnSS == sms service
      case 'AdnCPF':
     // AdnCPF == crypto payment
      case 'AdnESZ':
     //AdnESZ == zone picker
      case 'AdnSE':
        //AdnSE == email service
        //console.log(state)
        link = 'https://whitestudio.team/addons';
        break;
      
    }
  }else{
    link =`https://easyformbuilder.ir/داکیومنت/`;
    switch (state) {
      case 'publishForm':
        link += "%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/";
        break;
      case 'createSampleForm':
      case 'tutorial':
        link += "%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/";
        break;
      case 'stripe':
        //stripe
        link = "https://whitestudio.team/documents/how-to-setup-and-use-the-stripe-on-easy-form-builder";
        break;
      case 'ws':
        link = 'https://easyformbuilder.ir/';
        break;
      case 'price':
        link = 'https://easyformbuilder.ir/#price';
        break;
      case 'efb':
        link = "https://wordpress.org/plugins/easy-form-builder/";
        break;
      case 'wiki':
        link = `https://easyformbuilder.ir/documents/`;
        break;
      case 'EmailNoti':
        link += "چگونه-ایمیل-اطلاع-رسانی-را-در-فرم-ساز-آس/";
        break;
      case 'redirectPage':
        link += "نحوه-ساخت-یک-صفحه-تشکر-در-افزونه-فرم-ساز/";
      break;
      case 'AdnSPF':
        link = 'https://easyformbuilder.ir/documents/';
        break;
        //AdnSPF == strip payment
      case 'AdnOF':
        //AdnOF == offline form
        link += '%d9%81%d8%b9%d8%a7%d9%84-%da%a9%d8%b1%d8%af%d9%86-%d8%ad%d8%a7%d9%84%d8%aa-%d8%a2%d9%81%d9%84%d8%a7%db%8c%d9%86-%d9%81%d8%b1%d9%85/';
        break;
      case 'AdnPPF':
        //AdnPPF == persia payment
        link += "%da%86%da%af%d9%88%d9%86%d9%87-%d8%af%d8%b1%da%af%d8%a7%d9%87-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d8%a7%db%8c%d8%b1%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%a8%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2/";
        break;
        case 'wpbakery':
          //AdnOF == offline form
          link += '%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%db%8c%da%a9%d8%b1%db%8c-%d8%a7%d8%b3%d8%aa%d9%81/';
          break;
      case 'AdnATC':
        // AdnATC == advance tracking code
      case 'AdnSS':
        //AdnSS == sms service
      case 'AdnCPF':
     // AdnCPF == crypto payment
      case 'AdnESZ':
     //AdnESZ == zone picker
      case 'AdnSE':
        //AdnSE == email service
        //console.log(state)
        link = 'https://easyformbuilder.ir/';
        //"AdnWHS","AdnPAP","AdnWSP","AdnSMF","AdnPLF","AdnMSF","AdnBEF"
        break;
    }
  }

  
  window.open(link, "_blank");
}


function show_message_result_form_set_EFB(state, m) { //V2
  const wpbakery= `<p class="efb m-5 mx-3 fs-4"><a class="efb text-danger" onClick="Link_emsFormBuilder('wpbakery')" target="_blank">${efb_var.text.wwpb}</a></p>`
  const title = `
  <h4 class="efb title-holder efb">
     <img src="${efb_var.images.title}" class="efb title efb">
     ${state != 0 ? `<i class="efb  bi-hand-thumbs-up title-icon mx-2"></i>${efb_var.text.done}` : `<i class="efb  bi-hand-thumbs-up title-icon mx-2"></i>${efb_var.text.error}`}
  </h4>
  
  `;
  let content = ``
   
  if (state != 0) {
    content = ` <h3 class="efb"><b>${efb_var.text.goodJob}</b></br> ${state == 1 ? efb_var.text.formIsBuild : efb_var.text.formUpdatedDone}</h3>
    ${wpbakery_emsFormBuilder ? wpbakery :''}
  <h5 class="efb mt-3 efb">${efb_var.text.shortcode}: <strong>${m}</strong></h5>
  <input type="text" class="efb hide-input efb" value="${m}" id="trackingCodeEfb">
  <div id="alert"></div>
  <a  class="efb btn-r btn efb btn-primary btn-lg m-3" onclick="copyCodeEfb('trackingCodeEfb')">
      <i class="efb  bi-clipboard-check mx-1"></i>${efb_var.text.copyShortcode}
  </a>
  <a  class="efb btn efb btn-outline-pink btn-lg m-3 px-3" data-bs-toggle="modal" data-bs-target="#Output" onclick="open_whiteStudio_efb('publishForm')">
      <i class="efb  bi-question mx-1"></i>${efb_var.text.help}
  </a>
  <a  class="efb btn efb btn-outline-pink btn-lg m-3 px-3" data-bs-toggle="modal" data-bs-target="#close" onclick="location.reload(true);">
      <i class="efb  bi-x mx-1"></i>${efb_var.text.close}
  </a>
  `
  } else {
    content = `<h3 class="efb">${m}</h3>`
  }

  document.getElementById('settingModalEfb-body').innerHTML = `<div class="efb card-body text-center efb">${title}${content}</div>`
}//END show_message_result_form_set_EFB

console.info('Easy Form Builder 3.5.18> WhiteStudio.team');


function actionSendData_emsFormBuilder() {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  
  data = {};
  var name = formName_Efb
  //console.log(localStorage.getItem("valj_efb"));
  jQuery(function ($) {


    if (state_check_ws_p == 1) {
      data = {
        action: "add_form_Emsfb",
        value: localStorage.getItem("valj_efb"),
        name: name,
        type: form_type_emsFormBuilder,
        nonce: efb_var.nonce
      };
    } else {
      data = {
        action: "update_form_Emsfb",
        value: localStorage.getItem("valj_efb"),
        name: name,
        nonce: efb_var.nonce,
        id: form_ID_emsFormBuilder
      };
    }

    $.post(ajaxurl, data, function (res) {
      
      if (res.data.r == "insert") {
        if (res.data.value && res.data.success == true) {
          state_check_ws_p = 0;
          form_ID_emsFormBuilder = parseInt(res.data.id)

          show_message_result_form_set_EFB(1, res.data.value)
        } else {
          alert(res, "error")
          show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`)
        }
      } else if (res.data.r == "update" || res.data.r == "updated" && res.data.success == true) {
        show_message_result_form_set_EFB(2, res.data.value)
      } else {
        if (res.data.m == null || res.data.m.length > 1) {

          show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-400`)
        } else {
          show_message_result_form_set_EFB(0, res.data.value, `${res.data.m}, Code:400-400`)
        }
      }
    })
    return true;
  });

}
function actionSendAddons_efb(val) {
  //console.log(val)
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  
  const snd =val
  if (snd==null) return  valNotFound_efb()
  data = {};
  jQuery(function ($) {
      data = {
        action: "add_addons_Emsfb",
        value: snd,
        nonce: efb_var.nonce
      };
      
    $.post(ajaxurl, data, function (res) {      
      if (res.data.r == "done") {
        if (res.data.value && res.data.success == true) {
          //show_message_result_form_set_EFB(1, res.data.value)
          alert_message_efb(efb_var.text.done,'', 30,'info');
          location.reload();
        } else {
          alert(res, "error")
          //show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`)
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");

        }
      } else {
        if (res.data.m == null || res.data.m.length > 1) {
          
         // show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-400`)
         alert_message_efb(efb_var.text.error, res.data.m, 30, "danger");
        } else {
          //show_message_result_form_set_EFB(0, res.data.value, `${res.data.m}, Code:400-400`)
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-2`, 30, "danger");
        }
      }
    })
    return true;
  });

}
function actionSendAddonsUn_efb(val) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  
  data = {};
  jQuery(function ($) {
      data = {
        action: "remove_addons_Emsfb",
        value: val,
        nonce: efb_var.nonce
      };

    $.post(ajaxurl, data, function (res) {
      if (res.data.r == "done") {
        if (res.data.value && res.data.success == true) {
          //show_message_result_form_set_EFB(1, res.data.value)
          alert_message_efb(efb_var.text.done,'', 30,'info');
          location.reload();
        } else {
          alert(res, "error")
          //show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`)
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");

        }
      } else {
        if (res.data.m == null || res.data.m.length > 1) {

         // show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-400`)
         alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");
        } else {
          //show_message_result_form_set_EFB(0, res.data.value, `${res.data.m}, Code:400-400`)
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");
        }
      }
    })
    return true;
  });

}


function fun_report_error(fun, err) {
  //v2

}


function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();

  if (i == 2) demo_emsFormBuilder = false;


}

function getOS_emsFormBuilder() {
  var userAgent = window.navigator.userAgent,
    platform = window.navigator.platform,
    macosPlatforms = ['Macintosh', 'MacIntel', 'MacPPC', 'Mac68K'],
    windowsPlatforms = ['Win32', 'Win64', 'Windows', 'WinCE'],
    iosPlatforms = ['iPhone', 'iPad', 'iPod'],
    os = null;
  valid = false

  if (macosPlatforms.indexOf(platform) !== -1) {
    os = 'Mac OS';
  } else if (iosPlatforms.indexOf(platform) !== -1) {
    os = 'iOS';
  } else if (windowsPlatforms.indexOf(platform) !== -1) {
    os = 'Windows';
    valid = true;
  } else if (/Android/.test(userAgent)) {
    os = 'Android';
  } else if (!os && /Linux/.test(platform)) {
    os = 'Linux';
  }

  return valid;
}

createCardFormEfb = (i) => {
  tag_efb =tag_efb.concat(i.tag.split(' ')).filter((item, i, ar) => ar.indexOf(item) === i);
  let prw = `<a class="efb float-end btn mx-1 efb rounded-pill border-danger text-danger " onclick="fun_preview_before_efb('${i.id}' ,'local' ,${i.pro})"><i class="efb  bi-eye mx-1"></i>${efb_var.text.preview}</a>`;
  let btn = `<button type="button" id="${i.id}" class="efb float-end btn mb-1 efb btn-primary btn-lg float-end emsFormBuilder btn-r efbCreateNewForm"><i class="efb  bi-plus-circle mx-1"></i>${efb_var.text.create}</b></button>`;
  if (i.id == "form" || i.id == "payment") prw = "<!--not preview-->"  
  if(i.tag.search("payment")!=-1 && ( efb_var.addons.AdnSPF==0 && efb_var.addons.AdnPPF==0) ) {
    const fn = `alert_message_efb('${efb_var.text.error}', '${efb_var.text.IMAddonP}', 20 , 'danger')`
    btn = `<a class="efb float-end btn mb-1 efb btn-primary btn-lg float-end  btn-r" onClick="${fn}"><i class="efb  bi-plus-circle mx-1"></i>${efb_var.text.create}</b></a>`
  }
  return `
  <div class="efb tag  col ${efb_var.rtl == 1 ? 'rtl-text' : ''} ${i.tag}" id="${i.id}"> <div class="efb card efb"><div class="efb card-body">
  ${i.pro == true && efb_var.pro != 1 ? funProEfb() : ''}
  <h5 class="efb card-title efb"><i class="efb  ${i.icon} mx-1"></i>${i.title} </h5>
  <div class="efb row" ><p class="efb card-text efb ${mobile_view_efb ? '' : 'fs-7'} float-start my-3">${i.desc}  <b>${efb_var.text.freefeatureNotiEmail}</b> </p></div>
  ${btn}
  ${prw}
  </div></div></div>`
}
createCardAddoneEfb = (i) => {
  
  tag_efb =tag_efb.concat(i.tag.split(' ')).filter((item, i, ar) => ar.indexOf(item) === i);;
  
  let funNtn =   `funBTNAddOnsEFB('${i.name}','${i.v_required}')`;
  let nameNtn = efb_var.text.install;
  let iconNtn = 'bi-download';
  let colorNtn = 'btn-primary';
  if (i.pro == true &&  efb_var.pro != 1) {
    funNtn=`pro_show_efb(1)`;
    nameNtn = efb_var.text.pro;
    iconNtn ='bi-gem';
    colorNtn = 'btn-warning';
  }else if (efb_var.setting[i.name]== 1 ){
    funNtn=`funBTNAddOnsUnEFB('${i.name}')`;
    nameNtn = efb_var.text.remove;
    iconNtn ='';
    colorNtn = 'btn-secondary';
  }

  return `
  <div class="efb tag mt-0 col ${efb_var.rtl == 1 ? 'rtl-text' : ''} ${i.tag}" id="${i.id}"> <div class="efb card efb"><div class="efb card-body">
  ${i.pro == true && efb_var.pro != true ? funProEfb() : ''}
  <h5 class="efb card-title efb"><i class="efb  ${i.icon} mx-1"></i>${i.title} </h5>
  <div class="efb row" ><p class="efb card-text efb ${mobile_view_efb ? '' : 'fs-7'} float-start my-3">${i.desc}  </p></div>
  <a id="${i.name}" data-vrequired="${i.v_required}" class="efb float-end btn addons mb-1 efb ${colorNtn} btn-lg float-end btn-r" onClick="${funNtn}"><i class="efb ${iconNtn} mx-1"></i>${nameNtn}</b></a>
  <a class="efb float-end btn mx-1 efb rounded-pill border-danger text-danger " onclick="Link_emsFormBuilder('${i.name}')"><i class="efb  bi-question-circle mx-1"></i>${efb_var.text.help}</a>
  </div></div></div>`
}
funProEfb=()=>{return `<div class="efb  pro-card"><a type="button" onClick='pro_show_efb(1)' class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb  bi-gem text-light"></i></a></div>`}
const boxs_efb = [
  { id: 'form', title: efb_var.text.newForm, desc: efb_var.text.createBlankMultistepsForm, status: true, icon: 'bi-check2-square', tag: 'all new', pro: false },
  { id: 'contact', title: efb_var.text.contactusForm, desc: efb_var.text.createContactusForm, status: true, icon: 'bi-envelope', tag: 'all contactUs', pro: false },
  { id: 'payment', title: efb_var.text.paymentform, desc: efb_var.text.createPaymentForm, status: true, icon: 'bi-wallet-fill', tag: 'all payment new', pro: true },
  { id: 'support', title: efb_var.text.supportForm, desc: efb_var.text.createSupportForm, status: true, icon: 'bi-shield-check', tag: 'all support', pro: false },
  { id: 'survey', title: efb_var.text.survey, desc: efb_var.text.createsurveyForm, status: true, icon: 'bi-bar-chart-line', tag: 'all survey', pro: false },
  { id: 'contactTemplate', title: efb_var.text.contactusTemplate, desc: efb_var.text.createContactusForm, status: true, icon: 'bi-envelope', tag: 'all contactUs', pro: false },
  { id: 'curvedContactTemplate', title: `${efb_var.text.curved} ${efb_var.text.contactusTemplate}`, desc: efb_var.text.createContactusForm, status: true, icon: 'bi-envelope', tag: 'all contactUs', pro: false },
  { id: 'multipleStepContactTemplate', title: `${efb_var.text.multiStep} ${efb_var.text.contactusTemplate}`, desc: efb_var.text.createContactusForm, status: true, icon: 'bi-envelope', tag: 'all contactUs', pro: false },
  { id: 'privateContactTemplate', title: `${efb_var.text.showTheFormTologgedUsers} ${efb_var.text.contactusTemplate}`, desc: efb_var.text.createContactusForm, status: true, icon: 'bi-envelope', tag: 'all contactUs', pro: false },
  { id: 'customerFeedback', title: efb_var.text.customerFeedback, desc: efb_var.text.createSupportForm, status: true, icon: 'bi-shield-check', tag: 'all support', pro: false },
  { id: 'supportTicketForm', title: efb_var.text.supportTicketF, desc: efb_var.text.createSupportForm, status: true, icon: 'bi-shield-check', tag: 'all support', pro: false },
  { id: 'orderForm', title: `${efb_var.text.purchaseOrder} ${efb_var.text.payment}`, desc: efb_var.text.purchaseOrder, status: true, icon: 'bi-bag', tag: 'all payment', pro: true },
  { id: 'register', title: efb_var.text.registerForm, desc: efb_var.text.createRegistrationForm, status: true, icon: 'bi-person-plus', tag: 'all signInUp', pro: false },
  { id: 'login', title: efb_var.text.loginForm, desc: efb_var.text.createLoginForm, status: true, icon: 'bi-box-arrow-in-right', tag: 'all signInUp', pro: false },
  { id: 'subscription', title: efb_var.text.subscriptionForm, desc: efb_var.text.createnewsletterForm, status: true, icon: 'bi-bell', tag: 'all', pro: false },
  /*  {id:'reservation', title:efb_var.text.reservation, desc:efb_var.text.createReservationyForm, status:false, icon:'bi-calendar-check'}, */
]//supportTicketF
let tag_efb=[];
function add_dasboard_emsFormBuilder() {
  
  //v2
 // history.pushState(null,null,'?page=Emsfb_create&state=forms-template')
  let value = `<!-- boxs -->`;
  for (let i of boxs_efb) {

    value += createCardFormEfb(i)
  }
  let cardtitles = `<!-- card titles -->`;
  for (let i of tag_efb) {
    
    cardtitles += `
    <li class="efb col-3 col-lg-1 col-md-2 col-sm-2 col-sx-3 mb-2  m-1 p-0 text-center">
      <a class="efb nav-link m-0 p-0 cat fs-6 text-capitalize ${i}" aria-current="page" onclick="funUpdateLisetcardTitleEfb('${i}')" role="button">${efb_var.text[i]}</a>
    </li>
    `
  }
//console.log(efb_var.text)
 cardtitles = `
    <ul class="efb mt-4 mb-3 p-0 d-flex justify-content-center row" id="listCardTitleEfb">${cardtitles}
    <hr class="efb hr">
    </ul>
    `

  document.getElementById('tab_container_efb').innerHTML = `

          ${head_introduce_efb('create')}
          <section id="content-efb">
          ${!mobile_view_efb ? `<img src="${efb_var.images.title}" class="efb ${efb_var.rtl == 1 ? "right_circle-efb" : "left_circle-efb"}"><h4 class="efb title-holder efb"><img src="${efb_var.images.title}" class="efb title efb create"><i class="efb  bi-arrow-down-circle title-icon mx-1"></i>${efb_var.text.forms}</h4>` : ''}
          <div class="efb d-flex justify-content-center ">
            <input type="text" placeholder="${efb_var.text.search}" id="findCardFormEFB" class="efb fs-6 search-form-control efb-rounded efb mx-2"> <a class="efb btn efb btn-outline-pink mx-1" onClick="FunfindCardFormEFB()" >${efb_var.text.search}</a>
            
          </div
            <div class="efb row">
            ${cardtitles}
            <div class="efb  row row-cols-1 row-cols-md-3 g-4" id="listFormCardsEFB">${value}</div></div>
            </section>`


  let newform_ = document.getElementsByClassName("efbCreateNewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'npreview');
    })
  }
  newform_ = document.getElementsByClassName("efbPreviewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'preview');
    })
  }

}
function add_addons_emsFormBuilder() {
  
  //v2
  let value = `<!-- boxs -->`;
  for (let i of addons_efb) {
    //778899 addonTest => change below 
   if(i.state==true) {
    //if(i.state==true || i.state==false) {
      const v = {'name':i.name,'id':i.id,'tag':i.tag,'icon':i.icon,
                 'title':efb_var.text[i.title],'desc':efb_var.text[i.desc],'v_required':i.v_required , 'pro':i.pro}
    //AdnSPF
     if((efb_var.language!='fa_IR' && i.name!='AdnPPF') || efb_var.language=='fa_IR' ) value += createCardAddoneEfb(v)
    }
  }
  let cardtitles = `<!-- card titles -->`;

//console.log(efb_var.text)
 cardtitles = `
    <ul class="efb mt-4 mb-3 p-0 d-flex justify-content-center row" id="listCardTitleEfb">${cardtitles}
    <hr class="efb hr">
    </ul>
    `

  document.getElementById('tab_container_efb').innerHTML = `

          ${head_introduce_efb('create')}
          <section id="content-efb">
          ${!mobile_view_efb ? `<h4 class="efb  mb-0 title-holder efb"><img src="${efb_var.images.title}" class="efb title efb create"><i class="efb  bi-plus-circle title-icon mx-1"></i>${efb_var.text.addons}</h4>` : ''}
  
            <div class="efb row">
            ${cardtitles}
            <div class="efb  row row-cols-1 mt-0 row-cols-md-3 g-4" id="listFormCardsEFB">${value}</div></div>
            </section>`


  let newform_ = document.getElementsByClassName("efbCreateNewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'npreview');
    })
  }
  newform_ = document.getElementsByClassName("efbPreviewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'preview');
    })
  }

}



function FunfindCardFormEFB() {
  //console.log('FunfindCardFormEFB')
  let cards = [];
  const v = document.getElementById('findCardFormEFB').value.toLowerCase();
  document.getElementById('listFormCardsEFB').innerHTML = ''
  for (let row of boxs_efb) {
    if (row["title"].toLowerCase().includes(v) == true || row["desc"].toLowerCase().includes(v) == true) { cards.push(row); }
  }
  let result = '<!--Search-->'
  for (let c of cards) { result += createCardFormEfb(c); }
  if (result == "'<!--Search-->'") result = "NotingFound";
  //console.log(document.getElementById("listFormCardsEFB"))
  document.getElementById("listFormCardsEFB").innerHTML = result;

  let newform_ = document.getElementsByClassName("efbCreateNewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'npreview');
    })
  }
}
function FunfindCardAddonEFB() {
  //console.log('FunfindCardFormEFB')
  let cards = [];
  const v = document.getElementById('findCardFormEFB').value.toLowerCase();
  document.getElementById('listFormCardsEFB').innerHTML = ''
 
  for (let row of addons_efb) {
    if (row["title"].toLowerCase().includes(v) == true || row["desc"].toLowerCase().includes(v) == true) { cards.push(row); }
  }
  let result = '<!--Search-->'
  for (let c of cards) {result += createCardAddoneEfb(c); }
  if (result == "'<!--Search-->'") result = "NotingFound";
  //console.log(document.getElementById("listFormCardsEFB"))
  document.getElementById("listFormCardsEFB").innerHTML = result;

  let newform_ = document.getElementsByClassName("efbCreateNewForm")
  for (const n of newform_) {
    n.addEventListener("click", (e) => {
      form_type_emsFormBuilder = n.id;
      create_form_by_type_emsfb(n.id, 'npreview');
    })
  }
}

function create_form_by_type_emsfb(id, s) {
  
  //v2
  
 
  localStorage.removeItem('valj_efb');
  if (s != "pre") {
    document.getElementById('header-efb').innerHTML = ``;
    document.getElementById('content-efb').innerHTML = ``;
  }
  const label_align = efb_var.rtl == 1 ? 'txt-right' : 'txt-left'

  if (id === "form") {
    form_type_emsFormBuilder = "form";
    valj_efb = [];

  } else if (id === "contact") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = [{ "type": "form", "steps": 1, "formName": efb_var.text.contactUs, "email": "", "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "2jpzt59do", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "sendEmail": true, "stateForm": false, "dShowBg": true },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.contactusForm, "icon": "bi-chat-right-fill", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-muted", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "uoghulv7f", "dataId": "uoghulv7f-id", "type": "text", "placeholder": efb_var.text.firstName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.firstName, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "xzdeosw2q", "dataId": "xzdeosw2q-id", "type": "text", "placeholder": efb_var.text.lastName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.lastName, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "2jpzt59do", "dataId": "2jpzt59do-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 6, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "dvgl7nfn0", "dataId": "dvgl7nfn0-id", "type": "textarea", "placeholder": efb_var.text.enterYourMessage, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.message, "required": true, "amount": 7, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    localStorage.setItem('valj_efb', JSON.stringify(json))
    //console.log(JSON.stringify(json));
    valj_efb = json;
  } else if (id === "contactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = contact_us_template_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "multipleStepContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = multiple_step_ontact_us_template_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "privateContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = private_contact_us_template_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "curvedContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = curved_contact_us_template_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "register") {
    form_type_emsFormBuilder = "register";
    //register v2
    json = [{ "type": "register", "steps": 1, "formName": efb_var.text.register, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.register, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "emailRegisterEFB", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message":textThankUEFB('register'), "email_temp": "", "sendEmail": false, "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.registerForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "usernameRegisterEFB", "dataId": "usernameRegisterEFB-id", "type": "text", "placeholder": efb_var.text.username, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.username, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "besie",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordRegisterEFB", "dataId": "passwordRegisterEFB-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "emailRegisterEFB", "dataId": "emailRegisterEFB-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 9, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    valj_efb = json;
    localStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "login") {
    // login v2
    form_type_emsFormBuilder = "login";
    json = [{ "type": "login", "steps": 1, "formName": efb_var.text.login, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.login, "button_color": "btn-darkb", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "sendEmail": false, "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.loginForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-dark", "icon_color": "text-danger", "visible": 1 },
    { "id_": "emaillogin", "dataId": "emaillogin-id", "type": "text", "placeholder": efb_var.text.emailOrUsername, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.emailOrUsername, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordlogin", "dataId": "passwordlogin-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    valj_efb = json;

    localStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "support") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = [{ "type": "form", "steps": 1, "formName": efb_var.text.support, "email": "", "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "qas87uoct", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "sendEmail": true, "stateForm": false, "dShowBg": true },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "Support", "icon": "bi-ui-checks-grid", "step": "1", "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-dark", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "rhglopgi8", "dataId": "rhglopgi8-id", "type": "select", "placeholder": "Select", "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": "How can we help you?", "required": true, "amount": 2, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "b2xssuo2w", "dataId": "b2xssuo2w-id", "parent": "rhglopgi8", "type": "option", "value": "Accounting & Sell question", "id_op": "n470h48lg", "step": "1", "amount": 3 },
    { "id_": "b2xssuo2w", "dataId": "b2xssuo2w-id", "parent": "rhglopgi8", "type": "option", "value": "Technical & support question", "id_op": "zu7f5aeob", "step": "1", "amount": 4 },
    { "id_": "jv1l79ir1", "dataId": "jv1l79ir1-id", "parent": "rhglopgi8", "type": "option", "value": "General question", "id_op": "jv1l79ir1", "step": "1", "amount": 5 },
    { "id_": "59c0hfpyo", "dataId": "59c0hfpyo-id", "type": "text", "placeholder": efb_var.text.subject, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.subject, "required": 0, "amount": 6, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "qas87uoct", "dataId": "qas87uoct-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 10, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "cqwh8eobv", "dataId": "cqwh8eobv-id", "type": "textarea", "placeholder": efb_var.text.message, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.message, "required": true, "amount": 8, "step": 2,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": pro_efb }]
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "supportTicketForm") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = support_ticket_form_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "orderForm") {
    // support v2
    form_type_emsFormBuilder = "payment";
    const json = order_payment_form_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "customerFeedback") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = customer_feedback_efb()
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "subscription") {
    // if subscription has clicked add Json of contact and go to step 3
    form_type_emsFormBuilder = "subscribe";
    const json =
      [{ "type": "subscribe", "steps": 1, "formName": efb_var.text.subscribe, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.subscribe, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-d-efb", "email_to": "82i3wedt1", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "sendEmail": false, "stateForm": false, "dShowBg": true },
      { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "", "icon": "bi-check2-square", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
      { "id_": "janf5eutd", "dataId": "janf5eutd-id", "type": "text", "placeholder": efb_var.text.name, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.name, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
      { "id_": "82i3wedt1", "dataId": "82i3wedt1-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    localStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id == "survey") {
    
    form_type_emsFormBuilder = "survey";
    const json = [{ "type": "survey", "steps": 1, "formName": efb_var.text.survey, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "sendEmail": false, "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "Survey form", "icon": "bi-clipboard-data", "step": "1", "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "6af03cgwb", "dataId": "6af03cgwb-id", "type": "select", "placeholder": "Select", "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": "what is your favorite food ?", "required": true, "amount": 2, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "wxgt1tvri", "dataId": "wxgt1tvri-id", "parent": "6af03cgwb", "type": "option", "value": "Pasta", "id_op": "n9r68xhl1", "step": "1", "amount": 3 },
    { "id_": "wxgt1tvri", "dataId": "wxgt1tvri-id", "parent": "6af03cgwb", "type": "option", "value": "Pizza", "id_op": "khp0a798x", "step": "1", "amount": 4 },
    { "id_": "6x1lv1ufx", "dataId": "6x1lv1ufx-id", "parent": "6af03cgwb", "type": "option", "value": "Fish and seafood", "id_op": "6x1lv1ufx", "step": "1", "amount": 5 },
    { "id_": "yythlx4tt", "dataId": "yythlx4tt-id", "parent": "6af03cgwb", "type": "option", "value": "Vegetables", "id_op": "yythlx4tt", "step": "1", "amount": 6 },
    { "id_": "fe4q562zo", "dataId": "fe4q562zo-id", "type": "checkbox", "placeholder": "Check Box", "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": "Lnaguage", "required": 0, "amount": 7, "step": "1",  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "khd2i7ubz", "dataId": "khd2i7ubz-id", "parent": "fe4q562zo", "type": "option", "value": "English", "id_op": "khd2i7ubz", "step": "1", "amount": 8 }, { "id_": "93hao0zca", "dataId": "93hao0zca-id", "parent": "fe4q562zo", "type": "option", "value": "French", "id_op": "93hao0zca", "step": "1", "amount": 9 }, { "id_": "75bcbj6s1", "dataId": "75bcbj6s1-id", "parent": "fe4q562zo", "type": "option", "value": "German", "id_op": "75bcbj6s1", "step": "1", "amount": 10 }, { "id_": "lh1csq8mw", "dataId": "lh1csq8mw-id", "parent": "fe4q562zo", "type": "option", "value": "Russian", "id_op": "lh1csq8mw", "step": "1", "amount": 11 }, 
    { "id_": "5gopt8r6b", "dataId": "5gopt8r6b-id", "parent": "fe4q562zo", "type": "option", "value": "Portuguese", "id_op": "5gopt8r6b", "step": "1", "amount": 12 }, { "id_": "v57zhziyi", "dataId": "v57zhziyi-id", "parent": "fe4q562zo", "type": "option", "value": "Hindi", "id_op": "v57zhziyi", "step": "1", "amount": 13 }, { "id_": "16suwyx5m", "dataId": "16suwyx5m-id", "type": "radio", "placeholder": "Radio Button", "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": "Gender", "required": 0, "amount": 14, "step": "1",  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }, { "id_": "ha0sfnwbp", "dataId": "ha0sfnwbp-id", "parent": "16suwyx5m", "type": "option", "value": "Male", "id_op": "ha0sfnwbp", "step": "1", "amount": 15 }, { "id_": "w3jpyg24h", "dataId": "w3jpyg24h-id", "parent": "16suwyx5m", "type": "option", "value": "Female", "id_op": "w3jpyg24h", "step": "1", "amount": 16 }, { "id_": "in4xa2y0f", "dataId": "in4xa2y0f-id", "parent": "16suwyx5m", "type": "option", "value": "Non-binary", "id_op": "in4xa2y0f", "step": "1", "amount": 17 }, { "id_": "1028hto5a", "dataId": "1028hto5a-id", "parent": "16suwyx5m", "type": "option", "value": "Transgender", "id_op": "1028hto5a", "step": "1", "amount": 18 }, { "id_": "rz3vkawya", "dataId": "rz3vkawya-id", "parent": "16suwyx5m", "type": "option", "value": "Intersex", "id_op": "rz3vkawya", "step": "1", "amount": 19 }, { "id_": "2oezrrpny", "dataId": "2oezrrpny-id", "parent": "16suwyx5m", "type": "option", "value": "I prefer not to say", "id_op": "2oezrrpny", "step": "1", "amount": 20 }];
    valueJson_ws_p = json;
    valj_efb = json;
    
    localStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id == "reservation") {

  } else if (id == "payment") {
    
    form_type_emsFormBuilder = "payment";
    valj_efb = [];

  }
 
  formName_Efb = form_type_emsFormBuilder
  if (s == "npreview") {
    creator_form_builder_Efb();
    
    if (id != "form" && id != "payment" && id != "smart") { setTimeout(() => { editFormEfb() }, 100) }
  } else if ("pre") {
    //console.log("pre")
    previewFormEfb('pre');
  } else {
    previewFormEfb('pc')
  }
  // add_form_builder_emsFormBuilder();

}



function head_introduce_efb(state) {
  //v2
  
  const link = state == "create" ? '#form' : 'admin.php?page=Emsfb_create'
  let text = `${efb_var.text.efbIsTheUserSentence} ${efb_var.text.efbYouDontNeedAnySentence}`
  let btnSize = mobile_view_efb ? '' : 'btn-lg';

  let cont = ``;
  let vType = `<div class="efb mx-3 col-lg-4 mt-2 pd-5 col-md-10 col-sm-12 alert alert-light pointer-efb" onclick="Link_emsFormBuilder('price')">
  <i class="efb bi-diamond text-pinkEfb mx-1"></i>
  <span class="efb text-dark">${efb_var.text.getPro}</span><br>
  ${efb_var.text.yFreeVEnPro}
  </div>`;
  if (state != "create") {
    cont = `
    
                  <div class="efb clearfix"></div>                 
                  <p class="efb card-text  ${state == "create" ? 'card-text' : 'text-dark'} efb pb-3 ${mobile_view_efb ? 'fs-7' : 'fs-6'}">${text}</p>
                  
    <a class="efb btn btn-r btn-primary ${btnSize}" href="${link}"><i class="efb  bi-plus-circle mx-1"></i>${efb_var.text.createForms}</a>
    <a class="efb btn mt-1 efb btn-outline-pink ${btnSize}" onClick="Link_emsFormBuilder('tutorial')"><i class="efb  bi-info-circle mx-1"></i>${efb_var.text.tutorial}</a>`;
  }
  return `<section id="header-efb" class="efb   ${state == "create" ? '' : 'card col-12 bg-color'}">
  <div class="efb row ${mobile_view_efb ? 'mx-2' : 'mx-5'}">
              <div class="efb col-lg-7 mt-2 pd-5 col-md-12">
                  <img src="${efb_var.images.logo}" class="efb description-logo  ${mobile_view_efb ? 'm-1' : ''} efb">
                  <h1 class="efb  pointer-efb mb-0 ${mobile_view_efb ? 'fs-6' : ''}" onClick="Link_emsFormBuilder('efb')" >${efb_var.text.easyFormBuilder}</h1>
                  <h3 class="efb  pointer-efb  ${state == "create" ? 'card-text ' : 'text-darkb'} ${mobile_view_efb ? 'fs-7' : 'fs-6'}" onClick="Link_emsFormBuilder('ws')" >${efb_var.text.byWhiteStudioTeam}</h3>
                  ${cont}
                 
              </div>
              ${state == "create" && (efb_var.pro==false || efb_var.pro =='false') ? vType : ''}
              ${(state != "create" && mobile_view_efb) ? `<div class="efb col-lg-5 col-md-12 "> <img src="${efb_var.images.head}" class="efb img-fluid"></div>` : ''}
              ${(state != "create" && mobile_view_efb == false) ? `<div class="efb col-lg-5 col-md-12 "> <img src="${efb_var.images.head}" class="efb img-fluid"></div>` : ''}  
    </div>  
  </section> `
}


fun_preview_before_efb = (i, s, pro) => {

  valj_efb = [];
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  show_modal_efb("", efb_var.text.preview, "bi-check2-circle", "saveLoadingBox")
  //myModal.show_efb();
  state_modal_show_efb(1);
  if (s == "local") {
    create_form_by_type_emsfb(i, 'pre')
  }
}

window.onload = (() => {

  setTimeout(() => {
    for (const el of document.querySelectorAll(".notice")) {
      el.remove()
    }
  }, 50)

  document.body.scrollTop;
})


switch_color_efb = (color) => {
  let c;
  switch (color) {
    case '#0d6efd': c = "primary"; break;
    case '#198754': c = "success"; break;
    case '#6c757d': c = "secondary"; break;
    case '#ff455f': c = "danger"; break;
    case '#e9c31a': c = "warning"; break;
    case '#31d2f2': c = "info"; break;
    case '#fbfbfb': c = "light"; break;
    case '#202a8d': c = "darkb"; break;
    case '#898aa9': c = "labelEfb"; break;
    case '#ff4b93': c = "pinkEfb"; break;
    case '#ffff': c = "white"; break;
    case '#212529': c = "dark"; break;
    case '#777777': c = "muted "; break;
    default: c = "colorDEfb-" + color.slice(1);
  }
  return c;
}

ColorNameToHexEfbOfElEfb = (v, i, n) => {
  let r
  let id;
  switch (n) {
    case 'label': id = "style_label_color"; break;
    case 'description': id = "style_message_text_color"; break;
    case 'el': id = "style_el_text_color"; break;
    case 'btn': id = "style_btn_text_color"; break;
    case 'icon': id = "style_icon_color"; break;
    case 'border': id = "style_border_color"; break;
  }
  switch (v) {
    case "primary": r = '#0d6efd'; break;
    case "success": r = '#198754'; break;
    case "secondary": r = '#6c757d'; break;
    case "danger": r = '#ff455f'; break;
    case "warning": r = '#e9c31a'; break;
    case "info": r = '#31d2f2'; break;
    case "light": r = '#fbfbfb'; break;
    case "darkb": r = '#202a8d'; break;
    case "labelEfb": r = '#898aa9'; break;
    case "d": r = '#83859f'; break;
    case "pinkEfb": r = '#ff4b93'; break;
    case "white": r = '#ffff'; break;
    case "dark": r = '#212529'; break;
    case "muted": r = '#777777'; break;
    case "muted": r = '#777777'; break;
    default:
      const len = `colorDEfb-`.length;
      if (v.includes(`colorDEfb`)) r = "#" + v.slice(len);
  }

  return r;
}

addColorTolistEfb = (color) => {
  const ObColors = document.getElementById('color_list_efb');
  const child = ObColors.childNodes;
  let is_color = false;
  child.forEach((element, key) => {
    if (key != 0 && element.value.includes(color)) is_color = true;
  });
  if (!is_color) { ObColors.innerHTML += `<option name="addUser" value="${color}">` }
}

function sideMenuEfb(s) {
  let el = document.getElementById('sideBoxEfb');
  if (s == 0) {
    el.classList.remove('show');
    document.getElementById('sideMenuConEfb').innerHTML = `<div class="efb my-5" id=""><div class="efb  lds-hourglass"></div><h3 class="efb fs-3">${efb_var.text.pleaseWaiting}</h3></div>`
    document.getElementById('sideMenuFEfb').classList.add('efbDW-0');
    el.classList.add('efbDW-0');
    // jQuery("#sideBoxEfb").fadeIn('slow');
  } else {
    document.getElementById('sideBoxEfb').classList.remove('efbDW-0');
    document.getElementById('sideMenuFEfb').classList.remove('efbDW-0');
    el.classList.add('show');
  }
}


const funSetCornerElEfb = (dataId, co) => {
  //efb-square
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el = document.querySelector(`[data-id='${dataId}-set']`)
  if (el.dataset.side == "undefined" || el.dataset.side == "") {
    valj_efb[indx].corner = co;
    postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
    let cornEl = document.getElementById(postId);
    if (fun_el_select_in_efb(el.dataset.tag)) cornEl = document.getElementById(`${postId}options`)
    if (el.dataset.tag == 'esign') cornEl = document.getElementById(`${valj_efb[indx].id_}_b`)
    else if (el.dataset.tag == 'dadfile') cornEl = document.getElementById(`${valj_efb[indx].id_}_box`)
    
    cornEl.className = cornerChangerEfb(cornEl.className, co)

  } else if (el.dataset.side == "yesNo") {
    valj_efb[indx].corner = co;
    document.getElementById(`${valj_efb[indx].id_}_b_1`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, co)
    document.getElementById(`${valj_efb[indx].id_}_b_2`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, co)
  } else {

    valj_efb[0].corner = co;
    postId = document.getElementById('btn_send_efb');
    postId.classList.toggle('efb-square')
    postId.className = cornerChangerEfb(postId.className, co)
    document.getElementById('next_efb').className = cornerChangerEfb(document.getElementById('next_efb').className, co)
    document.getElementById('prev_efb').className = cornerChangerEfb(document.getElementById('prev_efb').className, co)
  }
}



/* jQuery(function (jQuery) {
  jQuery("#settingModalEfb").on('hidden.bs.modal', function () {
    
  });
}); */



let change_el_edit_Efb = (el) => {
  let lenV = valj_efb.length
  //console.log(el.id , el.value)
  if (el.value.length > 0 && el.value.search(/(")+/g) != -1) {
    el.value = el.value.replaceAll(`"`, '');
    alert_message_efb(efb_var.text.error, `Don't use forbidden Character like: ["]`, 10, "danger");
  }

  if (lenV > 20) {
    timeout = 5;
    const p = calPLenEfb(lenV) / 2
    if (el.dataset.tag == "multiselect" || el.dataset.tag == "payMultiselect") timeout = 100;
    lenV = (lenV * (Math.log(lenV)) * p);
    setTimeout(() => {
      document.getElementById("overlay_efb").classList.remove("d-none")
      document.getElementById("overlay_efb").classList.add("d-block")
      setTimeout(() => {
        document.getElementById("overlay_efb").classList.remove("d-block")
      }, lenV);
      clearTimeout(lenV);
    }, timeout);
    clearTimeout(timeout);
  }

  let postId = el.dataset.id.includes('step-') ? el.dataset.id.slice(5) : el.dataset.id
  postId = el.dataset.id.includes('Next_') || el.dataset.id.includes('Previous_') ? 0 : postId;
  //console.log(el.dataset.id != "button_group" || el.dataset.id != "button_group_",el,postId)
  let indx = el.dataset.id != "button_group" && el.dataset.id != "button_group_" && postId != 0 ? valj_efb.findIndex(x => x.dataId == postId) : 0;
  const len_Valj = valj_efb.length;
  
  postId = null

  let clss = ''
  let c, color;
  console.log('tesssssssssssssssssssssssss',el,el.hasOwnProperty('value'));
  setTimeout(() => {
    if(el.hasAttribute('value') && el.id!="htmlCodeEl"){ 
      
      el.value = sanitize_text_efb(el.value);}
      if (el.value==null) return  valNotFound_efb()

    switch (el.id) {
      case "labelEl":
        
        valj_efb[indx].name = el.value;
        document.getElementById(`${valj_efb[indx].id_}_lab`).innerHTML = el.value
        
        break;
      case "desEl":
        valj_efb[indx].message = el.value;
        document.getElementById(`${valj_efb[indx].id_}-des`).innerHTML = el.value
        break;
      case "mLenEl":
        if (Number(el.value)>524288 && valj_efb[indx].type!="range"){
          el.value="";
          alert_message_efb("",efb_var.text.mmlen,15,"warning")
        }else{
          valj_efb[indx].mlen = el.value;
          if(valj_efb[indx].hasOwnProperty("milen") && 
          Number(valj_efb[indx].mlen)<Number(valj_efb[indx].milen)){
            alert_message_efb("",efb_var.text.mxlmn,15,"warning")
            delete  valj_efb[indx].mlen;
            el.value=0;
            break;
          }
          
        }     
        break;
      case "textEl":
        
        valj_efb[indx][el.dataset.atr] =el.value;
        c =  valj_efb[indx].id_ +"_"+el.dataset.atr
        //console.log(el.dataset,c)
        document.getElementById(c).innerHTML=el.value;
        break;
      case "miLenEl":
        if( Number(el.value)==0 ||Number(el.value)==-1 ){
          //console.log(Number(el.value),'inside ==',valj_efb[indx].id_ ,valj_efb[indx].type)
          //pflm6h0n7_req
          clss = document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML;
          
          valj_efb[indx].required = clss.length!=0 ? 1 :0;
          
          valj_efb[indx].milen=0;
        }else if (Number(el.value)>524288 && valj_efb[indx].type!="range" ){
          el.value="";
          alert_message_efb("",efb_var.text.mmlen,15,"warning")
          valj_efb[indx].milen=0;
        }else{
          valj_efb[indx].milen = el.value;
          if(valj_efb[indx].hasOwnProperty("mlen") && 
          Number(valj_efb[indx].mlen)<Number(valj_efb[indx].milen)){
            alert_message_efb("",efb_var.text.mxlmn,15,"warning")
            delete  valj_efb[indx].milen;
            el.value=0;
            break;
          }
          if(valj_efb[indx].type!="range")valj_efb[indx].required=1;
          
        }     
        break;
      case "adminFormEmailEl":
        
        if (efb_var.smtp == "1") {
          if (el.value.match(/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/)) // email validation
          {
            valj_efb[0].email = el.value;
            valj_efb[0].sendEmail=true;
            return true;
          }
          else {
            if (el.value!="") alert_message_efb(efb_var.text.error, efb_var.text.invalidEmail, 10, "danger");
            document.getElementById("adminFormEmailEl").value = "";
            valj_efb[0].email="";
          }
        } else if (efb_var.smtp == '-1') {
          document.getElementById("adminFormEmailEl").value = "";
          alert_message_efb(efb_var.text.error, efb_var.text.goToEFBAddEmailM, 30, "danger");
        } else {
          // trackingCodeEl.checked=false;
          document.getElementById("adminFormEmailEl").value = "";
          alert_message_efb(efb_var.text.error, efb_var.text.sMTPNotWork, 20, "danger")
        }
        valj_efb[0].sendEmail=false;

        break;
      case "cardEl":
        valj_efb[0].dShowBg ? valj_efb[0].dShowBg =  el.classList.contains('active') : Object.assign(valj_efb[0], { dShowBg:  el.classList.contains('active') });
        break;
        case "offLineEl":
          if(efb_var.addons.AdnOF!=0 ){
            valj_efb[0].AfLnFrm ? valj_efb[0].AfLnFrm = el.classList.contains('active') : Object.assign(valj_efb[0], { AfLnFrm: el.classList.contains('active') });
          }else{
            el.checked=false;
            alert_message_efb(efb_var.text.error, `${efb_var.text.IMAddons} ${efb_var.text.offlineTAddon}`, 20, "danger")
            
          }
        break;
      case "requiredEl":
        valj_efb[indx].required = el.classList.contains('active')==true ? 1 :0;

        document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML = valj_efb[indx].required  == true ? '*' : '';
        const aId = {
          email: "_", text: "_", password: "_", tel: "_", url: "_", date: "_", color: "_", range: "_", number: "_", file: "_",
          textarea: "_", dadfile: "_", maps: "-map", checkbox: "_options", radio: "_options", select: "_options",
          multiselect: "_options", esign: "-sig-data", rating: "-stared", yesNo: "_yn"
        }
        postId = aId[valj_efb[indx].type]
        id = valj_efb[indx].id_
        postId = document.getElementById(`${id}${postId}`)
        if(postId) postId.classList.toggle('required');
      
        //state_view_efb=0;
        //postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
        break;
      case "hideLabelEl":
        c = el.classList.contains('active')==true ? 1 :0;
        valj_efb[indx].hflabel = c;
        
        clss=document.getElementById(`${el.dataset.id}_lab_g`);
        //console.log(c,clss)
        if(c==1){
         //if (!clss.classList.contains('d-none'))clss.classList.add('d-none');
          document.getElementById(`${valj_efb[indx].id_}_labG`).classList.add('d-none');
          //console.log(valj_efb[indx].id_)
          funSetPosElEfb(valj_efb[indx].dataId,'up')
         
        }else{
         //if (clss.classList.contains('d-none'))clss.classList.remove('d-none')
          document.getElementById(`${valj_efb[indx].id_}_labG`).classList.remove('d-none');
        }
        
        break;
      case "SendemailEl":
        if (efb_var.smtp == "true" || efb_var.smtp == 1 ) {
          //valj_efb[0].sendEmail = el.checked

          valj_efb[0].email_to = el.dataset.vid
          
        } else {
          // trackingCodeEl.checked=false;
          document.getElementById("SendemailEl").checked = false;
          
          alert_message_efb(efb_var.text.error, efb_var.text.sMTPNotWork, 20, "danger")
        }

        break;
      case "formNameEl":
        valj_efb[0].formName = el.value
        break;
      case "trackingCodeEl":
        valj_efb[0].trackingCode =  el.classList.contains('active') ? true : false;

        break;
      case "thankYouMessageDoneEl":
        valj_efb[0].thank_you_message.done = el.value;
        break;
      case "thankYouMessageEl":
        valj_efb[0].thank_you_message.thankYou = el.value;
        break;
      case "thankYouMessageConfirmationCodeEl":
        valj_efb[0].thank_you_message.trackingCode = el.value;
        break;
      case "thankYouMessageErrorEl":
        valj_efb[0].thank_you_message.error = el.value;
        break;
      case "thankYouMessagepleaseFillInRequiredFieldsEl":
        valj_efb[0].thank_you_message.pleaseFillInRequiredFields = el.value;
        break;
      case "captchaEl":

        if (efb_var.captcha == "true" && valj_efb[0].type != "payment") {
          //console.log(`captcha!!!`,el.classList)
          valj_efb[0].captcha = el.classList.contains('active')==true ? true : false
          if(document.getElementById('recaptcha_efb'))el.classList.contains('active') == true ? document.getElementById('recaptcha_efb').classList.remove('d-none') : document.getElementById('recaptcha_efb').classList.add('d-none')

        } else if (valj_efb[0].type == "payment") {
          document.getElementById("captchaEl").checked = false;
          alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.paymentNcaptcha, 20, "danger")
        } else {
          // trackingCodeEl.checked=false;
          document.getElementById("captchaEl").checked = false;
          alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.reCAPTCHASetError, 20, "danger")

        }
        break;
      case "showSIconsEl":
        valj_efb[0].show_icon =  el.classList.contains('active')==true ? true : false
        break;
      case "showSprosiEl":
        valj_efb[0].show_pro_bar = el.classList.contains('active')==true ? true : false
        break;
      case "showformLoggedEl":
        console.log(`showformLoggedEl[${valj_efb[0].stateForm}]`);
        valj_efb[0].stateForm = el.classList.contains('active')==true ? true : false
        break;
      case "placeholderEl":
        document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).placeholder = el.value;

        valj_efb[indx].placeholder = el.value;
        break;
      case "valueEl":
        
        if (el.dataset.tag != 'yesNo' && el.dataset.tag != 'heading' && el.dataset.tag != 'textarea' && el.dataset.tag != 'link') {

          //document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).value = el.value;
          document.getElementById(`${valj_efb[indx].id_}_`).value = el.value;
          valj_efb[indx].value = el.value;
        } else if (el.dataset.tag == 'heading' || el.dataset.tag == 'textarea' ||el.dataset.tag == 'link' ) {
          //console.log(valj_efb[indx].id_,document.getElementById(`${valj_efb[indx].id_}_`) );
          document.getElementById(`${valj_efb[indx].id_}_`).innerHTML = el.value;
          valj_efb[indx].value = el.value;
        } else {
          //yesNo
          id = `${valj_efb[indx].id_}_${el.dataset.no}`
          document.getElementById(id).value = el.value;
          document.getElementById(`${id}_lab`).innerHTML = el.value;
          el.dataset.no == 1 ? valj_efb[indx].button_1_text = el.value : valj_efb[indx].button_2_text = el.value
        }
        break;
      case "classesEl":
        id = valj_efb[indx].id_;
        const v = el.value.replace(` `, `,`);
        document.getElementById(id).className += el.value.replace(`,`, ` `);
        valj_efb[indx].classes = v;
        break;
      case "sizeEl":
        postId = document.getElementById(`${valj_efb[indx].id_}_labG`)
        
        const op = el.options[el.selectedIndex].value;
        valj_efb[indx].size = op;
        get_position_col_el(valj_efb[indx].dataId, true);
        break;
      case "cornerEl":

        const co = el.options[el.selectedIndex].value;
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
          valj_efb[indx].corner = co;
          postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
          let cornEl = document.getElementById(postId);
          // /
          if (fun_el_select_in_efb(el.dataset.tag)) cornEl = el.dataset.tag == 'conturyList' || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'select' ? document.getElementById(`${postId}options`) : document.getElementById(`${id}ms`)
          //efb-square

          cornEl.classList.toggle('efb-square')
          if (el.dataset.tag == 'dadfile' || el.dataset.tag == 'esign') document.getElementById(`${valj_efb[indx].id_}_b`).classList.toggle('efb-square')


        } else {
          valj_efb[0].corner = co;
          postId = document.getElementById('btn_send_efb');

          postId.classList.toggle('efb-square')
          document.getElementById('next_efb').classList.toggle('efb-square')
          document.getElementById('prev_efb').classList.toggle('efb-square')
        }
        break;
      case "labelFontSizeEl":
        valj_efb[indx].label_text_size = el.options[el.selectedIndex].value;
        let fontleb = document.getElementById(`${valj_efb[indx].id_}_lab`);
        const sizef = el.options[el.selectedIndex].value
        fontleb.className = fontSizeChangerEfb(fontleb.className, sizef)
        if (el.dataset.tag == "step") { let iconTag = document.getElementById(`${valj_efb[indx].id_}_icon`); iconTag.className = fontSizeChangerEfb(iconTag.className, sizef); }
        break;
      case "optnsStyleEl":
        valj_efb[indx].op_style = el.options[el.selectedIndex].value;
        //console.log('optnsStyleEl',valj_efb[indx].id_)
        c =document.getElementById(`${valj_efb[indx].id_}_options`);
        if(valj_efb[indx].op_style!="1"){
          if(!c.classList.contains('row')) c.className += ' row col-md-12';

          //colMReplaceEfb    
          clss = valj_efb[indx].op_style=="2" ? 'col-md-6' : 'col-md-4'
          for (let v of document.querySelectorAll(`[data-parent='${valj_efb[indx].id_}'].form-check`)){            
            v.className = colMdRemoveEfb(v.className);            
            v.classList.add(clss)
            
          }
        
        }else{
          if(c.classList.contains('row')){
            //console.log('classList.contains(row) exitst!');
             c.classList.remove('row'); c.classList.remove('col-md-12')
             for (let v of document.querySelectorAll(`[data-parent='${valj_efb[indx].id_}'].form-check`)){
              v.className = colMdRemoveEfb(v.className);

            }
            }
        }
       /*  let fontleb = document.getElementById(`${valj_efb[indx].id_}_lab`);
        const sizef = el.options[el.selectedIndex].value
        fontleb.className = fontSizeChangerEfb(fontleb.className, sizef)
        if (el.dataset.tag == "step") { let iconTag = document.getElementById(`${valj_efb[indx].id_}_icon`); iconTag.className = fontSizeChangerEfb(iconTag.className, sizef); } */
        break;
      case "thankYouTypeEl":
        valj_efb[0].thank_you = el.options[el.selectedIndex].value;
        const els =document.querySelectorAll(`.efb.tnxmsg`)
        el = document.getElementById('tnxrdrct');

        if(valj_efb[0].thank_you!='msg'){
          for (let i = 0; i < els.length; i++) {
            els[i].classList.remove('d-block');
            els[i].classList.add('d-none');
          }
         el.classList.remove('d-none');
         el.classList.add('d-block');
        }else{
          for (let i = 0; i < els.length; i++) {
            els[i].classList.remove('d-none');
            els[i].classList.add('d-block');
          }
          el.classList.remove('d-block');
          el.classList.add('d-none');
        }
         if(pro_efb!=true){
          //pro_show_efb(1);
          valj_efb[0].thank_you ='msg';
          valj_efb[0].rePage = '';
        }
        //const el_ = document.querySelectorAll(``)
        break;
      case "thankYouredirectEl":
        postId = el.value.match(/(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})/gi)
        if(pro_efb!=true){
          pro_show_efb(1);
          valj_efb[0].thank_you ='msg';
          valj_efb[0].rePage = '';
          break;
        }
       if (postId != null) {      
        valj_efb[0].rePage = el.value.replace(/([/])+/g, '@efb@');;
        valj_efb[0].thank_you ='rdrct';
       }else{
        valj_efb[0].thank_you ='msg';
        valj_efb[0].rePage = '';
        alert_message_efb(efb_var.text.error, efb_var.text.enterValidURL,8,'warning');
       }
        break;
      case "paymentGetWayEl":
        //console.log('paymentGetWayEl')
        valj_efb[0].payment = el.options[el.selectedIndex].value;
        
        break;
      case "paymentMethodEl":
        //console.log('paymentMethodEl')
        valj_efb[0].paymentmethod = el.options[el.selectedIndex].value;
        
        el = document.getElementById('chargeEfb')
        if (valj_efb[0].paymentmethod == 'charge') {
          el.innerHTML = efb_var.text.onetime;
          if (el.classList.contains('one') == false) el.classList.add('one')
          //el.
        } else {
          id = `${valj_efb[0].paymentmethod}ly`;
          
          el.innerHTML = efb_var.text[id];
          if (el.classList.contains('one') == false) el.classList.remove('one')
        }
        
        break;
      //paymentMethodEl
      case "currencyTypeEl":
        //console.log('currencyTypeEl')
        valj_efb[0].currency = el.options[el.selectedIndex].value.slice(0, 3);
        //document.getElementById('currencyPayEfb').innerHTML = valj_efb[0].currency.toUpperCase()
        for (const l of document.querySelectorAll(".totalpayEfb")) {
         if(l.classList.contains('ir')==false) l.innerHTML = Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
        }
        for (const l of document.querySelectorAll(".efb-crrncy")) {
         l.innerHTML = Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
        }
        
        
        break;
      case "fileTypeEl":
        valj_efb[indx].file = el.options[el.selectedIndex].value;

        //console.log(valj_efb[indx].file)
        valj_efb[indx].value = el.options[el.selectedIndex].value;
        let nfile = el.options[el.selectedIndex].value.toLowerCase();
        nfile = efb_var.text[nfile];
        if (document.getElementById(`${valj_efb[indx].id_}_txt`)) document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${nfile}`
        break;
      case "btnColorEl":
        color = el.value;
        //valj_efb[indx].button_color = el.options[el.selectedIndex].value;

        clss = switch_color_efb(color);
        if (clss.includes('colorDEfb')) { addStyleColorBodyEfb(clss, color, "btn", indx); }
        if (indx != 0) {
          if (el.dataset.tag != "yesNo") {
            if ((indx == 0 && valj_efb[indx].step == 1) || indx > 0) {
              document.getElementById(`${valj_efb[indx].id_}_b`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b`).className, "btn-" + clss)
            } else {
              document.getElementById(`prev_efb`).className = colorBtnChangerEfb(document.getElementById(`prev_efb`).className, "btn-" + clss)
              document.getElementById(`next_efb`).className = colorBtnChangerEfb(document.getElementById(`next_efb`).className, "btn-" + clss)
            }
          } else {
            document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, "btn-" + clss)
            document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorBtnChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, "btn-" + clss)
          }
        } else {
          document.getElementById(`btn_send_efb`).className = colorBtnChangerEfb(document.getElementById(`btn_send_efb`).className, "btn-" + clss)
          document.getElementById(`next_efb`).className = colorBtnChangerEfb(document.getElementById(`next_efb`).className, "btn-" + clss)
          document.getElementById(`prev_efb`).className = colorBtnChangerEfb(document.getElementById(`prev_efb`).className, "btn-" + clss)
        }
        valj_efb[indx].button_color = "btn-" + clss;
        
        if (clss.includes('colorDEfb')) {
          valj_efb[indx].style_btn_color ? valj_efb[indx].style_btn_color = color : Object.assign(valj_efb[indx], { style_btn_color: color });
          //addColorTolistEfb(color)
        }

        break;
      case "selectColorEl":
        color = el.value;
        c = switch_color_efb(color);

        //console.log(color, c ,el.dataset,el.dataset.tag)
        if (c.includes('colorDEfb')) {
          addStyleColorBodyEfb(c, color, "text", indx);
        }
        postId = ''
        if (el.dataset.el == "label") {
          valj_efb[indx].label_text_color = "text-" + c;
          postId = valj_efb[indx].type != 'step' ? '_labG' : '_lab'
        }
        else if (el.dataset.el == "description") {
          valj_efb[indx].message_text_color = "text-" + c;
          postId = '-des'
        }
        else if (el.dataset.el == "icon") {
          //console.log(c,indx,valj_efb[indx])
          valj_efb[indx].icon_color = "text-" + c;
          postId = '_icon'
        } else if (el.dataset.el == "el") {
          valj_efb[indx].el_text_color = "text-" + c;
          postId = '_'
        }
        
        if (el.dataset.tag != "form" && el.dataset.tag != "payment" &&
            el.dataset.tag != "login" && el.dataset.tag != "register" &&
            el.dataset.tag != "survey" &&
          ((el.dataset.tag == "select" && el.dataset.el != "el")
            || (el.dataset.tag == "radio" && el.dataset.el != "el")
            || (el.dataset.tag == "checkbox" && el.dataset.el != "el")
            || (el.dataset.tag == "payCheckbox" && el.dataset.el != "el")
            || (el.dataset.tag == "chlCheckBox" && el.dataset.el != "el")
            || (el.dataset.tag == "payRadio" && el.dataset.el != "el")
            || (el.dataset.tag == "yesNo" && el.dataset.el != "el")
            || (el.dataset.tag == "stateProvince" && el.dataset.el != "el")
            || (el.dataset.tag == "conturyList" && el.dataset.el != "el")
            || (el.dataset.tag != "yesNo" && el.dataset.tag != "checkbox" && el.dataset.tag != "payCheckbox" && el.dataset.tag != "payRadio"
                &&  el.dataset.tag != "radio" && el.dataset.tag != "select" && el.dataset.tag != 'stateProvince' && el.dataset.tag != 'conturyList' && el.dataset.tag != 'chlCheckBox'))
        ) {
          
          document.getElementById(`${valj_efb[indx].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}${postId}`).className, "text-" + c)
        } else if (el.dataset.tag == "form"  || el.dataset.tag == "payment" ||
        el.dataset.tag == "login" || el.dataset.tag == "register" || el.dataset.tag == "survey") {
          if (el.dataset.el != "icon" && el.dataset.el != "el") {
            document.getElementById(`${valj_efb[0].id_}${postId}`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[0].id_}${postId}`).className, "text-" + c)
            c == "colorDEf" ? document.getElementById(`${valj_efb[indx].id_}${postId}`).style.color = "#" + color : 0
          } else if (el.dataset.el == "icon") {
            document.getElementById(`button_group_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_icon`).className, "text-" + c)
            document.getElementById(`button_group_Next_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_icon`).className, "text-" + c)
            document.getElementById(`button_group_Previous_icon`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_icon`).className, "text-" + c)

          } else if (el.dataset.el == "el") {
            document.getElementById(`button_group_button_single_text`).className = colorTextChangerEfb(document.getElementById(`button_group_button_single_text`).className, "text-" + c)
            document.getElementById(`button_group_Next_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Next_button_text`).className, "text-" + c)
            document.getElementById(`button_group_Previous_button_text`).className = colorTextChangerEfb(document.getElementById(`button_group_Previous_button_text`).className, "text-" + c)


          }
          //button_group_button_single_text
        } else if (el.dataset.tag == "checkbox" || el.dataset.tag == "radio" || el.dataset.tag == "chlCheckBox") {
          const objOptions = valj_efb.filter(obj => {
            return obj.parent === valj_efb[indx].id_
          })
          for (let obj of objOptions) {
            let optin = document.getElementById(`${obj.id_}_lab`);
            optin.className = colorTextChangerEfb(optin.className, "text-" + c)
          }
        } else if (el.dataset.tag == "payCheckbox" || el.dataset.tag == "payRadio") {
          const objOptions = valj_efb.filter(obj => {
            return obj.parent === valj_efb[indx].id_
          })
          for (let obj of objOptions) {
            let optin = document.getElementById(`${obj.id_}_lab`);
            let price = document.getElementById(`${obj.id_}-price`);
            optin.className = colorTextChangerEfb(optin.className, "text-" + c);
            price.className = colorTextChangerEfb(optin.className, "text-" + c);
          }
        } else if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList') {
          const objOptions = valj_efb.filter(obj => {
            return obj.parent === valj_efb[indx].id_
          })
          for (let obj of objOptions) {
            try {
              let optin = document.querySelector(`[data-op="${obj.id_op}"]`);
              optin.className = colorTextChangerEfb(optin.className, "text-" + c)
            } catch {
            }
          }
        } else if (el.dataset.tag == "yesNo") {
          document.getElementById(`${valj_efb[indx].id_}_b_1`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, "text-" + c)
          document.getElementById(`${valj_efb[indx].id_}_b_2`).className = colorTextChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, "text-" + c)

        }

        if (c.includes('colorDEfb')) {
          switch (el.dataset.el) {
            case 'label':
              valj_efb[indx].style_label_color ? valj_efb[indx].style_label_color = color : Object.assign(valj_efb[indx], { style_label_color: color });
              //console.log('costume color',valj_efb[indx])
              break;
            case 'description':
              valj_efb[indx].style_label_color ? valj_efb[indx].style_message_text_color = color : Object.assign(valj_efb[indx], { style_message_text_color: color });
              //console.log('costume color',valj_efb[indx])
              break;
            case 'el':
              valj_efb[indx].el_text_color ? valj_efb[indx].style_el_text_color = color : Object.assign(valj_efb[indx], { style_el_text_color: color });
              //console.log('costume color',valj_efb[indx])
              break;
            case 'icon':
              valj_efb[indx].style_icon_color ? valj_efb[indx].style_icon_color = color : Object.assign(valj_efb[indx], { style_icon_color: color });
              //console.log('costume color',valj_efb[indx])
              break;

            default:
              break;
          }
          //addColorTolistEfb(color)
        }
        break;
      case "selectBorderColorEl":
        
        color = el.value;
        c = switch_color_efb(color);

        //console.log(color, c ,el.dataset,indx)
        if (c.includes('colorDEfb')) {
          addStyleColorBodyEfb(c, color, "border", indx);
        }
        postId = '_'

        if (el.dataset.tag == "dadfile") { postId = "_box" }
        else if (fun_el_select_in_efb(el.dataset.tag)) { postId = "_options" }

        setTimeout(() => {
          const l = document.getElementById(`${valj_efb[indx].id_}${postId}`);
          l.className = colorBorderChangerEfb(l.className, `border-${c}`);
        }, 100)
        valj_efb[indx].el_border_color = `border-${c}`

        if (c.includes('colorDEfb')) {
          valj_efb[indx].style_border_color ? valj_efb[indx].style_border_color = color : Object.assign(valj_efb[indx], { style_border_color: color });
          //addColorTolistEfb(color)
        }
        break;
      case "fontSizeEl":
        
        valj_efb[indx].el_text_size = el.options[el.selectedIndex].value;
        id = `${valj_efb[indx].id_}_`;
        //console.log(id)
        document.getElementById(id).className = headSizeEfb(document.getElementById(id).className, el.options[el.selectedIndex].value)
        break;
      case "selectHeightEl":
        c= el.dataset.tag ;
        indx = c== 'form' || c == 'survey' || c == 'payment' || c == 'login' || c == 'register' || c == 'subscribe' ? 0 : indx;
        
        valj_efb[indx].el_height = el.options[el.selectedIndex].value
        let fsize = 'fs-6';
        if (valj_efb[indx].el_height == 'h-l-efb') { fsize = 'fs-5'; }
        else if (valj_efb[indx].el_height == 'h-xl-efb') { fsize = 'fs-4'; }
        else if (valj_efb[indx].el_height == 'h-xxl-efb') { fsize = 'fs-3'; }
        else if (valj_efb[indx].el_height == 'h-xxxl-efb') { fsize = 'fs-2'; }
        else if (valj_efb[indx].el_height == 'h-d-efb') { fsize = 'fs-6'; }

        if (c == "select" || c == 'stateProvince' || c == 'conturyList') {
          postId = `${valj_efb[indx].id_}_options`
        } else if (c == "radio" || c == "checkbox") {
          valj_efb[indx].label_text_size = fsize;
          const objOptions = valj_efb.filter(obj => { return obj.parent === valj_efb[indx].id_ })
          setTimeout(() => {
            for (let obj of objOptions) {
              valj_efb[indx].el_text_size = fsize;
              let clslabel = document.getElementById(`${obj.id_}_lab`).className
              clslabel = inputHeightChangerEfb(clslabel, el.options[el.selectedIndex].value)
              clslabel = inputHeightChangerEfb(clslabel, fsize)
              document.getElementById(obj.id_).className = inputHeightChangerEfb(document.getElementById(obj.id_).className, fsize)
              //document.querySelector(`[data-id="${obj.dataId}"]`).className = fontSizeChangerEfb(document.querySelector(`[data-id='${obj.dataId}']`).className, )
            }
          }, objOptions.length * len_Valj);
          break;

        } else if (indx == 0) {
          postId = `btn_send_efb`;
          document.getElementById(`btn_send_efb`).className = inputHeightChangerEfb(document.getElementById(`btn_send_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`next_efb`).className = inputHeightChangerEfb(document.getElementById(`next_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`prev_efb`).className = inputHeightChangerEfb(document.getElementById(`prev_efb`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Previous_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_Previous_icon`).className, el.options[el.selectedIndex].value)
          document.getElementById(`button_group_Next_icon`).className = inputHeightChangerEfb(document.getElementById(`button_group_Next_icon`).className, el.options[el.selectedIndex].value)
          break;
        } else if (c == "maps") {
          postId = `${valj_efb[indx].id_}-map`;
        } else if (c == "dadfile") {

          postId = `${valj_efb[indx].id_}_box`;
        } else if (c == "multiselect" || c == "payMultiselect") {
          //h-xxl-efb
          postId = `${valj_efb[indx].id_}_options`;
          let msel = document.getElementById(postId);
          //const iconDD = document.getElementById(`iconDD-${valj_efb[indx].id_}`)
          msel.className.match(/h-+\w+-efb/g) ? msel.className = inputHeightChangerEfb(msel.className, valj_efb[indx].el_height) : msel.classList.add(valj_efb[indx].el_height)
         // iconDD.className.match(/h-+\w+-efb/g) ? iconDD.className = inputHeightChangerEfb(iconDD.className, valj_efb[indx].el_height) : iconDD.classList.add(valj_efb[indx].el_height)
          msel.className = fontSizeChangerEfb(msel.className, fsize)
          valj_efb[indx].el_text_size = fsize
        } else if (c == "rating") {
          postId = valj_efb[indx].id_;
          setTimeout(() => {
            const newClass = inputHeightChangerEfb(document.getElementById(`${postId}_star1`).className, valj_efb[indx].el_height);

            document.getElementById(`${postId}_star1`).className = newClass;
            document.getElementById(`${postId}_star2`).className = newClass;
            document.getElementById(`${postId}_star3`).className = newClass;
            document.getElementById(`${postId}_star4`).className = newClass;
            document.getElementById(`${postId}_star5`).className = newClass;
          }, 10);
          break;
        } else if (c == "switch") {
          postId = `${valj_efb[indx].id_}_`;
          //fsize
          document.getElementById(`${valj_efb[indx].id_}_off`).className = fontSizeChangerEfb(document.getElementById(`${valj_efb[indx].id_}_off`).className, fsize);
          document.getElementById(`${valj_efb[indx].id_}_on`).className = fontSizeChangerEfb(document.getElementById(`${valj_efb[indx].id_}_on`).className, fsize);
        } else if (c == "yesNo") {
          setTimeout(() => {
            postId = `${valj_efb[indx].id_}_b_1`;
            document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
            postId = `${valj_efb[indx].id_}_b_2`;
            document.getElementById(`${postId}`).className = inputHeightChangerEfb(document.getElementById(`${postId}`).className, valj_efb[indx].el_height)
          }, 10);
          break;
        } else if (c == "link") {

          postId = `${valj_efb[indx].id_}_`

          document.getElementById(postId).className = fontSizeChangerEfb(document.getElementById(postId).className, fsize);
          //console.log(fsize,postId , document.getElementById(postId));
        }else if (c == "range") {
          postId = `${valj_efb[indx].id_}-range`
          

          document.getElementById(postId).className = fontSizeChangerEfb(document.getElementById(postId).className, fsize);
          //console.log(fsize,postId , document.getElementById(postId));
        } else {

          postId = `${valj_efb[indx].id_}_`
        }
        setTimeout(() => {
          c= document.getElementById(`${postId}`);
          
          c.className = inputHeightChangerEfb(c.className, valj_efb[indx].el_height)
        }, 10)


        break;
      case 'SingleTextEl':
        let iidd = ""
        
        if (Number(valj_efb[0].steps)==1) {
          // iidd = indx !=0 ? `${valj_efb[indx].id_}_icon` : `button_group_icon` ;
          iidd = indx != 0 ? `${valj_efb[indx].id_}_button_single_text` : 'button_group_button_single_text';
          valj_efb[indx].button_single_text = el.value;
        } else {
          iidd = el.dataset.side == "Next" ? `button_group_Next_button_text` : `button_group_Previous_button_text`
          el.dataset.side == "Next" ? valj_efb[0].button_Next_text = el.value : valj_efb[0].button_Previous_text = el.value
        }
     
        document.getElementById(iidd).innerHTML = el.value;

        break;
      case 'iconEl':

        break;
      case 'marksEl':
        valj_efb[indx].mark = parseInt(document.getElementById('marksEl').value);

        break;
      case 'letEl':
        const lat = parseFloat(el.value);
        const lon = parseFloat(document.getElementById('lonEl').value)

        map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
          center: { lat: lat, lng: lon },
          zoom: 8,
        })
        valj_efb[indx].lat = lat;

        break;
      case 'lonEl':
        const lonLoc = parseFloat(el.value);
        const letLoc = parseFloat(document.getElementById('letEl').value)
        map = new google.maps.Map(document.getElementById(`${valj_efb[indx].id_}-map`), {
          center: { lat: letLoc, lng: lonLoc },
          zoom: 8,
        })
        valj_efb[indx].lng = lonLoc;

        break;
      case 'EditOption':

        const iindx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
        //console.log(iindx, )
        if (iindx != -1) {
          
          valj_efb[iindx].value = el.value;
          if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList') {

            //Select
            let vl = document.querySelector(`[data-op="${el.dataset.id}"]`);
            if (vl) vl.innerHTML = el.value;
            if (vl) vl.value = el.value;
          } else if (el.dataset.tag != "multiselect" && el.dataset.tag != 'payMultiselect') {
            //radio || checkbox          
            document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
          }
          el.setAttribute('value', valj_efb[iindx].value);
          el.setAttribute('defaultValue', valj_efb[iindx].value);
        }
        break;
      case 'paymentOption':   
        el.dataset.id;
        const ipndx = valj_efb.findIndex(x => x.id_op == el.dataset.id);


        if (ipndx != -1) {
          valj_efb[ipndx].price = el.value;
          //console.log( valj_efb[ipndx])
          el.setAttribute('value', valj_efb[ipndx].price);
          el.setAttribute('defaultValue', valj_efb[ipndx].price);
          const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
          let no = Number(valj_efb[ipndx].price);
          no = no.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })        
          document.getElementById(`${valj_efb[ipndx].id_}-price`).innerHTML=no;
        }
        break;
      case "htmlCodeEl":

        const idhtml = `${el.dataset.id}_html`;
        postId = valj_efb.findIndex(x => x.id_ == el.dataset.id);
        if (el.value.length > 2) {

          document.getElementById(idhtml).innerHTML = el.value;
          document.getElementById(idhtml).classList.remove('sign-efb')
          valj_efb[postId].value = el.value.replace(/\r?\n|\r/g, "@efb@nq#");
          valj_efb[postId].value = valj_efb[postId].value.replace(/"/g, `@!`);

        } else {

          document.getElementById(idhtml).classList.add('sign-efb')
          document.getElementById(idhtml).innerHTML = `
            <div class="efb  noCode-efb m-5 text-center" id="${el.dataset.id}_noCode">
            ${efb_var.text.noCodeAddedYet}  <button type="button" class="efb  btn btn-edit btn-sm" id="settingElEFb" data-id="${el.dataset.id}-id" data-bs-toggle="tooltip" title="Edit" onclick="show_setting_window_efb('${el.dataset.id}-id')">
            <i class="efb  bi-gear-fill text-success" id="efbSetting"></i></button> ${efb_var.text.andAddingHtmlCode}
            </div>`
          valj_efb[postId].value = '';

        }

        break;
      case "selectMultiSelectMaxEl":
        const vms = el.value == "" ? 2 : el.value
        valj_efb[indx].maxSelect = vms;
        document.getElementById(`${valj_efb[indx].id_}_options`).dataset.no = vms

        break;
      case "selectMultiSelectMinEl":
        const vmsn = el.value == "" ? 2 : el.value
        valj_efb[indx].minSelect = vmsn;
        document.getElementById(`${valj_efb[indx].id_}_options`).dataset.min = vmsn

        break;
        case "qtyPlcEl":
          valj_efb[indx].pholder_chl_value = el.value;
          //console.log('qtyPlcEl',el.value, valj_efb[indx].pholder_chl_value,valj_efb[indx].id_)
          for (let v of document.querySelectorAll(`[data-id='${valj_efb[indx].id_}']`)){
            
            v.placeholder = el.value;
          }
        break;
    }

  }, len_Valj * 10)

}

function wating_sort_complate_efb(t) {
  if (t > 500) t = 500
  const body = loading_messge_efb()
  show_modal_efb(body, efb_var.text.editField, 'bi-ui-checks mx-2', 'settingBox')
  const el = document.getElementById("settingModalEfb");
  //const myModal = new bootstrap.Modal(el, {});
  //myModal.backdrop = 'static';
 // myModal.show_efb()
  state_modal_show_efb(1);
  setTimeout(() => { state_modal_show_efb(0) }, t)
}

function create_form_efb() {
  let content = `<!--efb.app-->`
  let step_no = 0;
  let head = ``
  const len = valj_efb.length;
  const p = calPLenEfb(len)
  try {
    valj_efb.forEach((value, index) => {
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb  ${valj_efb[0].steps <= 11 ? `step-w-${valj_efb[0].steps}` : `step-w-11`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb  fs-5 ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" class="efb  mt-1 mb-2 steps-efb row">` : `<!-- fieldsetFOrm!!! --></fieldset><fieldset data-step="step-${step_no}-efb"  class="efb my-2 steps-efb efb row d-none">`

        if (valj_efb[0].show_icon == false) { }

      }
      if (value.type == 'step') {
        steps_index_efb.push(index)
        //steps_index_efb.length<2 ? content =`<div data-step="${step_no}" class="efb m-2 content-efb row">` : content +=`</div><div data-step="${step_no}"  class="efb m-2 content-efb row">` 
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option') {
        // content+='<div class="efb mb-3">'
        content += addNewElement(value.type, value.id_, true, true);
        //  content+=`<div id="${value.id_}_fb" class="efb m-2"></div></div>`

      }
    })
    step_no += 1;
    content += `
                ${sitekye_emsFormBuilder.length > 1 && valj_efb[0].captcha == true ? `<div class="efb row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0" data-sitekey="${sitekye_emsFormBuilder}" style=”transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;”></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                <!-- fieldset formNew 1 --> </fieldset> 
    
                <fieldset data-step="step-${step_no}-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
                ${loading_messge_efb()}
                <!-- fieldset formNew 2 --> </fieldset>
      `
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb  ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg" ><strong class="efb  fs-5 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch (error) {
    console.error(`Preview of Pc Form has an Error`, error)
  }

  if (content.length > 10) content += `</div>`
  head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="efb mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="efb d-flex justify-content-center"><div class="efb progress mx-4"><div class="efb  progress-bar-efb  btn-${RemoveTextOColorEfb(valj_efb[1].label_text_color)} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div></div> <br> ` : ``}`



  // if (document.getElementById(`settingModalEfb_`)) document.getElementById(`settingModalEfb_`).classList.add('pre-efb')
  content = `  
    <div class="efb px-0 pt-2 pb-0 my-1 col-12" id="view-efb">
    <h4 id="title_efb" class="efb ${valj_efb[1].label_text_color} mt-3 mb-0 text-center efb">${valj_efb[1].name}</h4>
    <p id="desc_efb" class="efb ${valj_efb[1].message_text_color} fs-7 mb-2 text-center efb">${valj_efb[1].message}</p>
    
     <form id="efbform"> ${head} <div class="efb mt-1 px-2">${content}</div> </form>
    </div>
    `
  return content
}// end function


const saveFormEfb = () => {


  let proState = true;
  let stepState = true;
  let body = ``;
  let btnText = ``;
  let btnFun = ``
  let message = ``;
  let state = false
  let title = efb_var.text.error;
  let icon = `bi-exclamation-triangle-fill`
  let box = `error`
  let btnIcon = `bi-question-lg`
  let returnState = false;
  let  gateway = '';
  if(valj_efb[0].type == 'payment'){
    gateway = valj_efb.findIndex(x => x.type == "stripe") 
    gateway = gateway == -1 ? valj_efb[0].gateway : gateway;
    if(gateway == 'persiaPay'){
      gateway =  valj_efb[0].persiaPay ;
    }

  }
  setTimeout(() => {


    
    //settingModalEfb-body
    //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
    show_modal_efb("", efb_var.text.save, "bi-check2-circle", "saveLoadingBox")
    //console.log(valj_efb[0].type=="payment" &&  gateway==-1)
    let timeout = 1000;
    check_show_box = () => {

      setTimeout(() => {
        if (returnState == false) {
          check_show_box();
          timeout = 500;
        } else {
          show_modal_efb(body, title, icon, box)
        }
      }, timeout);
    }

    try {
      if (valj_efb.length < 3) {
        btnText = efb_var.text.help
        btnFun = `open_whiteStudio_efb('notInput')`
        message = efb_var.text.youDoNotAddAnyInput
        icon = ""

      } else {

        if (pro_efb == false) { proState = valj_efb.findIndex(x => x.pro == true) != -1 ? false : true }
        for (let s = 1; s <= valj_efb[0].steps; s++) {
          const stp = valj_efb.findIndex(x => x.step == s && x.type != "step");
          if (stp == -1) {
            stepState = false;
            break;
          }
        }
      }
      
      if (valj_efb.length > 2 && proState == true && stepState == true && (((valj_efb[0].type == "payment" && gateway != -1) ||(valj_efb[0].type == "persiaPay" && gateway != -1) ) || valj_efb[0].type != "payment")) {
        title = efb_var.text.save
        box = `saveBox`
        icon = `bi-check2-circle`
        state = true;
        let sav = JSON.stringify(valj_efb);
        
        
        localStorage.setItem('valj_efb', sav);
        localStorage.setItem("valueJson_ws_p", sav)
        formName_Efb = valj_efb[0].formName.length > 1 ? valj_efb[0].formName : formName_Efb
        returnState = actionSendData_emsFormBuilder()
      } else if (proState == false) {
        btnText = efb_var.text.activateProVersion
        btnFun = `open_whiteStudio_efb('pro')`
        message = efb_var.text.youUseProElements
        title = efb_var.text.proVersion
        icon = 'bi-gem'
        btnIcon = icon;
        returnState = true;

      } else if (stepState == false) {

        btnText = efb_var.text.help
        btnFun = `open_whiteStudio_efb('emptyStep')`
        message = efb_var.text.itAppearedStepsEmpty

        returnState = true;

      } else if (valj_efb[0].type == "payment" && gateway == -1) {
        
        btnText = efb_var.text.help
        btnFun = `open_whiteStudio_efb('paymentform')`
        message = efb_var.text.addPaymentGetway;
        icon = 'bi-exclamation-triangle'
        returnState = true;
      
      } else if (valj_efb[0].type == "persiaPay" && gateway == -1) {
        
        btnText = efb_var.text.help
        btnFun = `open_whiteStudio_efb('persiaPay')`
        message = efb_var.text.addPaymentGetway;
        icon = 'bi-exclamation-triangle'
        returnState = true;
      } else if ((valj_efb[0].type == "payment" || valj_efb[0].type == "persiaPay") && valj_efb[0].captcha == true) {
        btnText = efb_var.text.help
        btnFun = `open_whiteStudio_efb('paymentform')`
        message = efb_var.text.paymentNcaptcha;
        icon = 'bi-exclamation-triangle'
        returnState = true;
      }
      if (state == false) {

        btn = `<button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3 text-capitalize" onClick ="${btnFun}">
      <i class="efb  ${btnIcon} mx-2"></i> ${btnText} </button>`
        body = `
      <div class="efb pro-version-efb-modal efb"></div>
        <h5 class="efb  txt-center text-darkb fs-6 text-capitalize">${message}</h5>
        <div class="efb  text-center text-capitalize">
        ${btn}
        </div>
      `
        check_show_box();
      }




      //myModal.show_efb();
      state_modal_show_efb(1);
    } catch (error) {
      
      btnIcon = 'bi-bug'
      body = `
    <div class="efb pro-version-efb-modal efb"></div>
    <h5 class="efb  txt-center text-darkb fs-6 text-capitalize">${efb_var.text.pleaseReporProblem}</h5>
    <div class="efb  text-center text-capitalize">
    <button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3 text-capitalize" onClick ="fun_report_error('fun_saveFormEfb','${error}')">
      <i class="efb  bi-megaphone mx-2"></i> ${efb_var.text.reportProblem} </button>
    </div>
    `
      show_modal_efb(body, efb_var.text.error, btnIcon, 'error');
      state_modal_show_efb(1)
      //myModal.show_efb();
    }
  }, 100)
}//end function

let editFormEfb = () => {
  valueJson_ws_p = 0; // set ajax to edit mode
  let dropZoneEFB = document.getElementById('dropZoneEFB');
  dropZoneEFB.innerHTML = loading_messge_efb();
  if (localStorage.getItem("valj_efb")) { valj_efb = JSON.parse(localStorage.getItem("valj_efb")); } // test code => replace from value
  let p = calPLenEfb(valj_efb.length)
  const len = (valj_efb.length) * p || 10;


  setTimeout(() => {
    dropZoneEFB.innerHTML = "<!-- edit efb -->"
    for (let v in valj_efb) {
      
      try {
        if (valj_efb[v].type != "option") {
          const type = valj_efb[v].type == "step" ? "steps" : valj_efb[v].type;
         
          let el = addNewElement(type, valj_efb[v].id_, true, false);
       
          dropZoneEFB.innerHTML += el;
          //console.log(valj_efb[v].type,'!!!!!!')   ;
          if (valj_efb[v].hasOwnProperty('type') &&  valj_efb[v].type != "form" && valj_efb[v].type != "step" && valj_efb[v].type != "html" && valj_efb[v].type != "register" && valj_efb[v].type != "login" && valj_efb[v].type != "subscribe" && valj_efb[v].type != "survey" && valj_efb[v].type != "payment" && valj_efb[v].type != "smartForm") {
            
            funSetPosElEfb(valj_efb[v].dataId, valj_efb[v].label_position)}

          if (type == 'maps') {
            setTimeout(() => {
              const lat = valj_efb[v].lat;
              const lon = valj_efb[v].lng;
              map = new google.maps.Map(document.getElementById(`${valj_efb[v].id_}-map`), {
                center: { lat: lat, lng: lon },
                zoom: 8,
              })
            }, (len * 2));
          }
        }
      } catch (error) {
        console.error('Error', error);
      }
    }

    fub_shwBtns_efb()
    //enableDragSort('dropZoneEFB');
  }, len);


}//editFormEfb end


function obj_resort_row(step) {
  // ترتیب را مرتب می کند بعد از پاک شدن یک استپ
  // const newStep = step - 1;
 
  for (v of valj_efb) {
    if (v.step == step) {
      v.step = step;
      if (v.dataId) {
        //document.querySelector(`[data-id="${v.dataId}"]`).dataset.step = step;

        if (document.getElementById(v.id_)) document.getElementById(v.id_).dataset.step = step;
      }
    }
  }
  /*   if(pro_efb==false) step_el_efb = step_el_efb-1;
    valj_efb[0].steps = valj_efb[0].steps-1; */
  fub_shwBtns_efb()
  if (valj_efb[0].steps == 1) fun_handle_buttons_efb(false);
}
let sampleElpush_efb = (rndm, elementId) => {
  //console.log(`sampleElpush_efb ===> rndm[${rndm}], elementId[${elementId}] amount_el_efb[${amount_el_efb}]`)
  
  const testb = valj_efb.length;
  amount_el_efb = amount_el_efb ?amount_el_efb: (valj_efb[testb-1].amount +1);
  step_el_efb = step_el_efb ? step_el_efb: valj_efb[0].steps ;
  const label_align = efb_var.rtl == 1 ? 'txt-right' : 'txt-left'
  const txt_color = elementId != "yesNo" ? 'text-labelEfb' : "text-white"
  p=()=>{const l =fields_efb.find(x=>x.id == elementId);  return l && l.hasOwnProperty('pro')? l.pro :0} ;
  let pro = p();
  let size = 100;
  
  let type = elementId;
  switch (elementId) {
    case "firstName":
    case "lastName":
      size = 100;
      type = "text";
      break;

    default:
      size = 100;
      break;
  }
  
  if (elementId == "dadfile" || elementId == "switch" || elementId == "rating" || elementId == "esign" || elementId == "maps"
    || elementId == "html" || elementId == "stateProvince" || elementId == "conturyList" || elementId == "payMultiselect"
    || elementId == "paySelect" || elementId == "payRadio" || elementId == "payCheckbox" || elementId == "heading" || elementId == "link" || elementId == "stripe" || elementId == "persiaPay") { pro = true }

  if (elementId != "file" && elementId != "dadfile" && elementId != "html" && elementId != "steps" && elementId != "heading" && elementId != "link") {
    
   // console.log(`elementId[${elementId}] ,amount_el_efb[${amount_el_efb}]`)
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: type, placeholder: efb_var.text[elementId], value: '', size: size, message: efb_var.text.sampleDescription,
      id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,  label_text_size: 'fs-6',
      label_position: 'beside', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d',
      el_text_color: txt_color, message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: label_align, message_align: 'justify-content-start',
      el_align: 'justify-content-start', pro: pro, icon_input: ''
    })

    if (elementId == "stripe") {
      Object.assign(valj_efb[0], { getway: 'stripe', currency: 'usd', paymentmethod: 'charge' });
      valj_efb[0].type = 'payment';
      form_type_emsFormBuilder = "payment";
    }
    if (elementId == "persiaPay") {
      Object.assign(valj_efb[0], { getway: 'persiaPay', currency: 'irr', paymentmethod: 'charge', persiaPay:'zarinPal' });
      valj_efb[0].type = 'payment';
      form_type_emsFormBuilder = "payment";
    }
    if (elementId == "esign") {

      Object.assign(valj_efb[(valj_efb.length) - 1], {
        icon: 'bi-save', icon_color: "default", button_single_text: efb_var.text.clear,
        button_color: 'btn-danger'
      })
      //icon: ''
    } else if (elementId == "yesNo") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { button_1_text: efb_var.text.yes, button_2_text: efb_var.text.no, button_color: 'btn-primary' })
    } else if (elementId == "maps") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { lat: 49.24803870604257, lng: -123.10512829684463, mark: 1, zoom: 7 });
      setTimeout(() => {
        document.getElementById('maps').draggable = false;
        if (document.getElementById('maps_b')) document.getElementById('maps_b').classList.add('disabled')
      }, valj_efb.length * 5);
    } else if (elementId == "multiselect" || elementId == "payMultiselect") {
      //console.log(valj_efb.length)
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        maxSelect: 2,
        minSelect: 0
      })
    }else if (elementId == "chlCheckBox" || elementId == "chlRadio") {
      //console.log(valj_efb.length)
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        pholder_chl_value: efb_var.text.qty
      })
    }

  } else if (elementId == "html") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, value: '', amount: amount_el_efb, step: step_el_efb, pro: pro
    })
  } else if (elementId == "heading") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, classes: '', value: efb_var.text[elementId], amount: amount_el_efb, step: step_el_efb, el_text_size: 'display-4',
      el_text_color: 'text-dark', el_align: 'justify-content-start', pro: pro
    })
  } else if (elementId == "link") {
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, classes: '', value: efb_var.text[elementId], amount: amount_el_efb, step: step_el_efb, el_text_size: 'fs-3',
      el_text_color: 'text-primary', el_align: 'justify-content-start', href: "https://whitestudio.team", pro: pro
    })
  } else if (elementId == "steps") {
    step_el_efb = step_el_efb == 0 ? 1 : step_el_efb;
    const stepName = efb_var.text[formName_Efb] != undefined ? efb_var.text[formName_Efb].toUpperCase() : efb_var.text.step;
    valj_efb.push({
      id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: 'stepNavEfb',
      id: `${step_el_efb}`, name: stepName, icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: efb_var.text.sampleDescription,
      label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb',
      el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-danger', icon: 'bi-ui-checks-grid', visible: 1
    });

  } else {
    
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, placeholder: elementId, value: 'allformat', size: 100,
      message: efb_var.text.sampleDescription, id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,
       label_text_size: 'fs-6', message_text_size: 'fs-7', el_text_size: 'fs-6', file: 'allformat',
      label_text_color: 'text-labelEfb', label_position: 'beside', el_text_color: 'text-dark', message_text_color: 'text-muted', el_height: 'h-d-efb',
      label_align: label_align, message_align: 'justify-content-start', el_border_color: 'border-d',
      el_align: 'justify-content-start', pro: pro
    })
    if (elementId == "dadfile") {
      //console.log (valj_efb[(valj_efb.length) - 1])
      Object.assign(valj_efb[(valj_efb.length) - 1], { icon: 'bi-cloud-arrow-up-fill', icon_color: "text-pinkEfb", button_color: 'btn-primary' })
      //icon_color: 'default'
    }

  }
  
}
let optionElpush_efb = (parent, value, rndm, op, tag) => {
  if (tag != undefined || (typeof tag=="string" && tag.includes("pay")==-1)) {
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });
  } else {
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, price: 0, amount: amount_el_efb });
  }
  //console.log(valj_efb)
}

function create_dargAndDrop_el() {
  
  const dropZoneEFB = document.getElementById("dropZoneEFB");

  dropZoneEFB.addEventListener("dragover", (event) => {
    event.preventDefault();
  });
  for (const el_efb of document.querySelectorAll(".draggable-efb")) {
    //console.log(`added =>.draggable-efb[el.id]`)

    el_efb.addEventListener("dragstart", (event) => {     
      
      //console.log(`create_dargAndDrop_el[dragstart][${el_efb.id}]`)
      event.dataTransfer.setData("text/plain", el_efb.id)

    });

    el_efb.addEventListener("click", (event) => {   
     
      if( document.body.classList.contains('mobile')==false && (el_efb.getAttribute('draggable')==true ||el_efb.getAttribute('draggable')=="true") ){
        
        fun_efb_add_el(el_efb.id);}
      });
  }
  dropZoneEFB.addEventListener("drop", (event) => {
    // Add new element to dropZoneEFB
    
    event.preventDefault();
    if (event.dataTransfer.getData("text/plain") !== "step" && event.dataTransfer.getData("text/plain") != null && event.dataTransfer.getData("text/plain") != "") {
      const rndm = Math.random().toString(36).substr(2, 9);
      const t = event.dataTransfer.getData("text/plain");
      

      fun_efb_add_el(t);
    }



    //enableDragSort('dropZoneEFB');
  }); // end drogZone

  

}

const add_new_option_efb = (parentsID, idin, value, id_ob, tag) => {
  
  let p = document.getElementById("optionListefb")
  let p_prime = p.cloneNode(true)
  const ftyp = tag.includes("pay") ? 'payment' : '';
  const col = ftyp == "payment" || ftyp == "smart" ? 'col-md-7' : 'col-md-12'
  
  document.getElementById('optionListefb').innerHTML += `
  <div id="${id_ob}-v"  class="efb  col-md-12">
  <input type="text"  value='${value}' data-value="${value}" id="EditOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}"  class="efb  ${col} text-muted mb-1 fs-6 border-d efb-rounded elEdit">
  ${ftyp == "payment" ? `<input type="number" placeholder="$"  value='' data-value="${value}" id="paymentOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}-payment"  class="efb  col-md-3 text-muted mb-1 fs-6 border-d efb-rounded elEdit">` : ''}
  <div class="efb  ${ftyp == "payment" || ftyp == "smart" ? 'pay' : 'newop'} btn-edit-holder" id="deleteOption" data-parent_id="${parentsID}">
    <button type="button" id="deleteOption" onClick="delete_option_efb('${idin}')"  data-parent="${parentsID}" data-tag="${tag}"  data-id="${idin}-id"  class="efb  btn btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}" > 
        <i class="efb  bi-x-lg text-danger"></i>
    </button>
   <button type="button" id="addOption" onClick="add_option_edit_pro_efb('${parentsID.trim()}','${tag.trim()}',${valj_efb.length})" data-parent="${parentsID}" data-tag="${tag}" data-id="${idin}-id"   class="efb  btn btn-edit btn-sm elEdit " data-bs-toggle="tooltip"   title="${efb_var.text.add}" > 
        <i class="efb  bi-plus-circle  text-success"></i>
    </button>
  </div>
  </div>`;
  if (tag !== "multiselect" && tag !== "payMultiselect") document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(idin, value, id_ob, tag, parentsID);

  for (let el of document.querySelectorAll(`.elEdit`)) {
    
    el.addEventListener("change", (e) => { change_el_edit_Efb(el); })
  }


}

const sort_obj_el_efb_ = () => {
  // این تابع  مرتبط سازی المان ها را بر عهده دارد و آی دی و قدم آن را بعد از هر تغییر در ترتیب توسط کاربر مرتبط می کند
  // باید بعد بجز المان ها برای آبجکت هم اینجا را  اضافه کنید
 
  let amount = 0;
  let step = 0;
  let state = false;
  let op_state = false;
  let last_setp =0;
  const len = valj_efb.length;
  for (const el of document.querySelectorAll(".efbField")) {

    amount += 1;
    let indx = valj_efb.findIndex(x => x.id_ === el.id)

    try {
      if (indx != -1) {

        if (el.classList.contains('stepNo')) {
          //اگر استپ بود
          
     
          //step = el.dataset.step;
          last_setp +=1;
          step = last_setp ;
          //el.dataset.amount=amount;
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;


        } else {
         
          // if not a step
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
          //el.dataset.step =step;
          //el.dataset.amount=amount;
        }
        if (op_state == false && (fun_el_select_in_efb(el.dataset.tag) || valj_efb[indx].type == "radio" || valj_efb[indx].type == "checkbox" || valj_efb[indx].type == "payRadio" || valj_efb[indx].type == "payCheckbox")) {

          op_state == true;
          valj_efb.filter(obj => { return obj.parent === valj_efb[indx].id_ }).forEach((value) => {
            amount += 1;
            value.amount = amount;
            value.step = step
          })
        }
      }

    } catch {

    }
    //console.log(`sort_obj_el_efb_ step[${step}]`)
  }

  if (len > 20) {
    //show loading message full screen
    sort_obj_efb()
    const p = calPLenEfb(len)
    wating_sort_complate_efb((len * (Math.log(len)) * p))
  } else {
    sort_obj_efb()
  }
  /* const valj_efb_ = valj_efb.sort((a,b) => (a.amount - b.amount))
  valj_efb=valj_efb_; */

  if (state) fub_shwBtns_efb();
  
}//enf fun obj

const sort_obj_el_efb = () => {
  let amount = 1;
  let step = 0;
  let state = false;
  //console.error('------', valj_efb.length)
  //console.log(`sort_obj_el_efb step[${step}]`)
  for (const el of document.querySelectorAll(".efbField")) {

    if (el.classList.contains('stepNavEfb')) {
      amount = 1;
      step = el.dataset.step;
    } else {
      if (step == 1) {

        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id) // این خط خطا دارد

        const lastIndx = (valj_efb.length) - 1;

        valj_efb[indx].step = valj_efb[lastIndx].step
        valj_efb[indx].amount = !valj_efb[lastIndx].amount ? 1 : Number(valj_efb[lastIndx].amount) + 1;

        //  el.remove();
        state = true;
      } else {
        el.dataset.amount = amount;
        el.dataset.step = step;
        amount = amount + 1;
        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)

        if (indx != -1) {
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;
        }
      }
    }
    const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)

  }

  if (state) fub_shwBtns_efb();
}


function add_option_edit_pro_efb(parent, tag, len) {
  const p = calPLenEfb(len)
  len = len < 50 ? 200 : (len + Math.log(len)) * p
  const id_ob = Math.random().toString(36).substr(2, 9);
  optionElpush_efb(parent, efb_var.text.newOption, id_ob, id_ob, tag);
  setTimeout(() => {
    add_new_option_efb(parent, id_ob, efb_var.text.newOption, id_ob, tag);
  }, len);

}

//delete element
function show_delete_window_efb(idset) {
  
  // این تابع المان را از صفحه پاک می کند
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  const is_step = document.getElementById(idset) ? document.getElementById(idset).classList.contains('stepNavEfb') : false;
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  if (is_step == false) {
   //myModal.show_efb();
   state_modal_show_efb(1);
   confirmBtn.dataset.id =idset.slice(0,-3);
   //console.log(idset ,confirmBtn.dataset.id )
    confirmBtn.addEventListener("click", (e) => {
      document.getElementById(confirmBtn.dataset.id).remove();
      obj_delete_row(idset, false, confirmBtn.dataset.id);
      activeEl_efb = 0;
      state_modal_show_efb(0)
    })
    //myModal.show_efb();
  } else if (is_step) {
    const el = document.getElementById(idset);
    if (el.dataset.id != 1) {
      // اگر استپ اول نباشد باید حذف شود و ردیف های بعد از شماره شان عوض شود به آخرین
      state_modal_show_efb(1)
     // myModal.show_efb();
      confirmBtn.dataset.id = idset;

      confirmBtn.addEventListener("click", () => {

        activeEl_efb = 0;
        if (pro_efb == false) {
          step_el_efb = step_el_efb > 1 ? step_el_efb - 1 : 1;
        }

        valj_efb[0].steps = valj_efb[0].steps - 1
        obj_delete_row(idset, true)
        document.getElementById(confirmBtn.dataset.id).remove();
        state_modal_show_efb(0)

      })


    }
  }

}

const obj_delete_row = (dataid, is_step) => {

  let step = 0
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1

  if (foundIndex != -1 && is_step == true) { step = valj_efb[foundIndex].step }
  if (foundIndex != -1) {
    if (valj_efb[foundIndex].type == "maps") {
      document.getElementById('maps').draggable = true;
      document.getElementById('maps_b').classList.remove('disabled')
    } else if (valj_efb[foundIndex].type == "stripe") {
     const v= valj_efb.findIndex(x => x.type == 'persiaPay');
     if(v==-1){
      valj_efb[0].type = "form";
      form_type_emsFormBuilder = "form";
     }
     
      
    } else if (fun_el_select_in_efb(valj_efb[foundIndex].type) || valj_efb[foundIndex].type == 'radio' || valj_efb[foundIndex].type == 'checkbox') {
      obj_delete_options(valj_efb[foundIndex].id_)
      //  foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1
    } else if (valj_efb[foundIndex].type == 'email' && valj_efb[0].email_to == valj_efb[foundIndex].id_) {
      valj_efb[0].sendEmail = 0
      valj_efb[0].email_to = ''
    }

    valj_efb.splice(foundIndex, 1);
  }
  if (is_step == true) {
    for (let ob of valj_efb) {
      if (ob.step == step) ob.step = step - 1;

    }
  }
  obj_resort_row(step_el_efb);
}
const obj_delete_options = (parentId) => {
  while (valj_efb.findIndex(x => x.parent == parentId) != -1) {
    let indx = valj_efb.findIndex(x => x.parent == parentId);

    valj_efb.splice(indx, 1);
  }

}
const obj_delete_the_option = (id) => {
  //Just Delete the option with ID
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.id_op == id) : -1;
  if (foundIndex != -1) valj_efb.splice(foundIndex, 1);
}

function show_duplicate_fun(idset) {
  //از آبجکت خروجی بگیرد و بعد اینجا تولید کند
}

/* darggable new */
let enableDragSort = (listClass) => {
  const sortLists = document.getElementsByClassName(listClass);
  Array.prototype.map.call(sortLists, (lst) => { enableDragList(lst) });
}

let enableDragList = (lst) => {
  Array.prototype.map.call(lst.children, (item) => { enableDragItem(item) });
}

let enableDragItem = (item) => {
  // این تابع وقتی المان جدیدی اضافه می شود باید فراخوانی شود تا به حالت درگ و دروپ به آن اضافه شود
  if (!item.classList.contains('stepNavEfb')) {
    // if not step
    item.setAttribute('draggable', true)
    //item.ondrag = handleDrag;
   // item.ondragend = handleDrop;
  }
}
let status_drag_start = false;
let handleDrag = (item) => {

  const selectedItem = item.target,
    lst = selectedItem.parentNode,
    x = event.clientX,
    y = event.clientY;

  let swapItem = document.elementFromPoint(x, y) === null ? selectedItem : document.elementFromPoint(x, y);

  if (status_drag_start == false) {
    
    for (i of valj_efb) {

      if (i.type != "option" && i.type != "form" && i.type != "payment" && selectedItem.id != i.id_ && selectedItem.previousElementSibling.id != i.id_) {
        //console.log(i,i.id_,document.getElementById(i.id_));
        document.getElementById(i.id_).classList.add("drophere")
      }
    }
    status_drag_start = true;
  }

  selectedItem.classList.add('drag-sort-active-efb');
  if (lst === swapItem.parentNode) {
    swapItem = swapItem !== selectedItem.nextSibling && swapItem.dataset == "steps" && swapItem.id != "1" ? swapItem : swapItem.nextSibling;
    if (lst.insertBefore(selectedItem, swapItem)) {


    }
  }
}

let handleDrop = (item) => {
  item.target.classList.remove('drag-sort-active-efb');
  sort_obj_el_efb_()
  if (status_drag_start == true) {
    for (i of valj_efb) {
      if (i.type != "option" && i.type != "form")
        if (document.getElementById(i.id_)) document.getElementById(i.id_).classList.remove("drophere")
    }
    status_drag_start = false;
  }

  // sort_obj_efb();
}






const sort_obj_efb = () => {

  const len = valj_efb.length;
  
  let p = calPLenEfb(len)
  //let =valj_efb_
  setTimeout(() => {
   const  valj_efb_ = valj_efb.sort((a, b) => (Number(a.amount) > Number(b.amount)) ? 1 : ((Number(b.amount) > Number(a.amount)) ? -1 : 0))
     valj_efb= valj_efb_;
     
  }, ((len * p))
  );
  
}


/* darggable new */




const delete_option_efb = (id) => {
  //حذف آپشن ها مولتی سلکت و درایو
  document.getElementById(`${id}-v`).remove();
  if (document.getElementById(`${id}-v`)) document.getElementById(`${id}-v`).remove();
  const indx = valj_efb.findIndex(x => x.id_op == id)
  if (indx != -1) { valj_efb.splice(indx, 1); }
}



fun_efb_add_el = (t) => {
  
  const rndm = Math.random().toString(36).substr(2, 9);

  

  if (t == "steps" && valj_efb.length < 2) { return; }
  if (valj_efb.length < 2) { dropZoneEFB.innerHTML = "", dropZoneEFB.classList.add('pb') }

  if (t == "address" || t == "name") {
    const olist = [{ n: 'name', t: "firstName" }, { n: 'name', t: "lastName" }]
    for (const ob of olist) {
      if (ob.n == t) {
        let el = addNewElement(ob.t, Math.random().toString(36).substr(2, 9), false, false);
        dropZoneEFB.innerHTML += el;
      }
    }

  } else {

    let el = addNewElement(t, rndm, false, false);
    if(el!='null'){
      dropZoneEFB.innerHTML += el;
      switch(t){
        case 'mobile':
          break;
        case 'persiaPay':
        case 'stripe':
          funRefreshPricesEfb()
          break
      }
    }
  }

  fub_shwBtns_efb();

  if (t == 'maps') {

    const id = `${rndm}-map`;

    if (typeof google !== "undefined") {
      let map = new google.maps.Map(document.getElementById(`${id}`), {
        center: { lat: 49.24803870604257, lng: -123.10512829684463 },
        zoom: 10,
      })
    } else {
      setTimeout(() => {
        const mp = document.getElementById(`${rndm}-map`)
        mp.innerHTML = googleMapsNOkEfb()
      }, 800);
    }

  }
}

googleMapsNOkEfb =()=>{
 return `<div class="efb  boxHtml-efb sign-efb h-100" >
        <div class="efb  noCode-efb m-5 text-center">
        <button type="button" class="efb  btn btn-edit btn-sm" data-bs-toggle="tooltip" title="${efb_var.text.howToAddGoogleMap}" onclick="open_whiteStudio_efb('mapErorr')">
         <i class="efb  bi-question-lg text-pinkEfb"></i></button> 
         <p class="efb fs-6">${efb_var.text.aPIkeyGoogleMapsError}</p> 
      </div></div>`;
}

function active_element_efb(el) {
  // تابع نمایش دهنده و مخفی کنند کنترل هر المان
  //show config buttons
 if (el.id != activeEl_efb ) {
 
    
    if (activeEl_efb == 0) {
      activeEl_efb = document.getElementById(el.id).dataset.id;

    } else {
      //console.log(activeEl_efb,activeEl_efb.slice(0,-3),document.getElementById(`btnSetting-${activeEl_efb}`).classList.contains('d-none'))

      document.getElementById(`btnSetting-${activeEl_efb}`).classList.toggle('d-none');

    }
   // const ac = document.querySelector(`[data-id="${activeEl_efb}"]`);
    const ac = document.querySelector(`.field-selected-efb`);
    if (ac) {
   // if (ac && state_view_efb==0) {      
      // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-none')
     ac.classList.remove('field-selected-efb')
    }
    //state_view_efb=0
    activeEl_efb = el.dataset.id
    const eld = document.getElementById(`btnSetting-${activeEl_efb}`);
    if (eld.classList.contains('d-none')) eld.classList.remove('d-none');

    // document.getElementById(`btnSetting-${activeEl_efb}`).classList.add('d-block')
    document.querySelector(`[data-id="${activeEl_efb}"]`).classList.add('field-selected-efb')


  }
}

add_element_dpz_efb = (id) => { fun_efb_add_el(id); }



const colorBtnChangerEfb = (classes, color) => { return classes.replace(/\bbtn+-+[\w\-]+/gi, `${color}`); }
const colorBGrChangerEfb = (classes, color) => { return classes.replace(/\bbg+-+[\w\-]+/gi, `${color}`); }
const inputHeightChangerEfb = (classes, value) => { return classes.replace(/(h-d-efb|h-l-efb|h-xl-efb|h-xxl-efb|h-xxxl-efb)/, `${value}`); }
const fontSizeChangerEfb = (classes, value) => { return classes.replace(/\bfs+-\d+/gi, `${value}`); }
const colChangerEfb = (classes, value) => { return classes.replace(/\bcol-\d+|\bcol-\w+-\d+/, `${value}`); }
const colMdRemoveEfb = (classes) => { return classes.replace(/\bcol-md+-\d+/gi, ``); }
const colMReplaceEfb = (classes,value) => { return classes.replace(/\bcol-md+-\d+/gi, value); }
const headSizeEfb = (classes, value) => { return classes.replace(/\bdisplay+-\d+/gi, `${value}`); }
const colSmChangerEfb = (classes, value) => { return classes.replace(/\bcol-sm+-\d+/, `${value}`); }
const iconChangerEfb = (classes, value) => { return classes.replace(/(\bbi-+[\w\-]+|bXXX)/g, `${value}`); }
const isNumericEfb = (value) => { return /^\d+$/.test(value); }

/* move to pro_els.js */

funBTNAddOnsEFB=(val,v_required)=>{
  let check_ar_pr=(val)=>{
    //console.log(val)
    if (val!="AdnPDP" && val!="AdnADP"){ 
        return true;
    }else if((val=="AdnADP" &&  efb_var.setting.hasOwnProperty('AdnPDP')==true && efb_var.setting.AdnPDP==true) 
    || (val=="AdnPDP" && efb_var.setting.hasOwnProperty('AdnADP')==true && efb_var.setting.AdnADP==true)){
      
            return false;
    }
    return true;
  }
  
 if(efb_version>=v_required){
  if(check_ar_pr(val)==true){
    addons_btn_state_efb(val);
    actionSendAddons_efb(val);
  }else{
    
    alert_message_efb(efb_var.text.error, efb_var.text.mPAdateW,45,'warning');
    
    
  }
 }else{
  //efb_var.text.upDMsg
  
  alert_message_efb(efb_var.text.error, efb_var.text.upDMsg,30,'warning');
  setTimeout(() => {
    location.reload();
  }, 3000);
 }
}

funBTNAddOnsUnEFB=(val)=>{
  emsFormBuilder_delete(val,'addon');
}

fun_confirm_remove_addon_emsFormBuilder=(val)=>{
   actionSendAddonsUn_efb(val);
 }

function emsFormBuilder_delete(id, type) {
  //v2
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  //myModal.show_efb();
  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    if(type=='form'){
    fun_confirm_remove_emsFormBuilder(Number(id))
    }else if(type=='message'){
      fun_confirm_remove_message_emsFormBuilder(Number(id))
    }else if (type =='addon'){
      addons_btn_state_efb(id);
      fun_confirm_remove_addon_emsFormBuilder(id);
    }
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })
  //myModal.show_efb();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}


addons_btn_state_efb=(id)=>{

    for (const el of document.querySelectorAll(".addons")) {
      el.classList.add('disabled')
    }
    document.getElementById(id).innerHTML = `<i class="efb bi-hourglass-split mx-1"></i>`



}

funRefreshPricesEfb=()=>{
  for (const l of document.querySelectorAll(".efb-crrncy")) {
    l.innerHTML = Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
   }  
}
state_modal_show_efb=(i)=>{
  
  const el = document.getElementById('settingModalEfb');
  function Respond(e) {if(e.target == el) state_modal_show_efb(0)}
   show =()=>{
   document.body.classList.add("modal-open")
   el.classList.add('show'); 
   el.style.cssText='display: block; padding-right: 0.400024px;';
   el.setAttribute("aria-hidden",!0);
   setTimeout(() => {
    document.body.addEventListener("click",Respond);
   }, 10);
   
  }
   remove =()=>{
   document.body.classList.remove("modal-open");
   el.classList.remove('show');
   el.style.cssText='';
   el.removeAttribute("aria-hidden");
   jQuery('#regTitle').empty().append(loadingShow_efb());
    // document.getElementById(`settingModalEfb`).innerHTML=loadingShow_efb();
    if (jQuery('#settingModalEfb_').hasClass('save-efb')) {
      jQuery('#settingModalEfb_').removeClass('save-efb')


    }
    if (jQuery('#settingModalEfb_').hasClass('pre-efb')) {
      //document.getElementById('dropZoneEFB').innerHTML = editFormEfb()
      jQuery('#dropZoneEFB').empty().append(editFormEfb());
      jQuery('#settingModalEfb_').removeClass('pre-efb');

      //fub_shwBtns_efb()
    } else if (jQuery('#settingModalEfb_').hasClass('pre-form-efb')) {
      jQuery('#settingModalEfb_').removeClass('pre-form-efb');
    }
    if (jQuery('#modal-footer-efb')) {
      jQuery('#modal-footer-efb').remove()
    }

    var val = loading_messge_efb();
    if (jQuery(`#settingModalEfb-body`)) jQuery(`#settingModalEfb-body`).html(val)

   setTimeout(() => {
    document.body.removeEventListener("click",Respond);
   }, 10);
  
  }
   i==1 ? show() : remove();   
}


function add_r_matrix_edit_pro_efb(parent, tag, len) {
  const p = calPLenEfb(len)
  len = len < 50 ? 200 : (len + Math.log(len)) * p
  const id_ob = Math.random().toString(36).substr(2, 9);
  
  r_matrix_push_efb(parent, efb_var.text.newOption, id_ob, id_ob, tag);
  setTimeout(() => {
    add_new_option_efb(parent, id_ob, efb_var.text.newOption, id_ob, tag);
  }, len);

}

let r_matrix_push_efb = (parent, value, rndm, op) => {

  valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `r_matrix`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });
}

document.addEventListener('DOMContentLoaded', function() {
  const els = document.getElementById('wpbody-content');
  for (let i = 0; i < els.children.length; i++) {
    
    if (els.children[i].tagName != 'SCRIPT' && els.children[i].tagName != 'STYLE' && (els.children[i].id.toLowerCase().indexOf('efb') == -1 && els.children[i].id.indexOf('_emsFormBuilder') == -1)) {
      document.getElementById('wpbody-content').children[i].remove()
    }
  }
  /* setTimeout(() => {
    const v = document.getElementById('adminmenuwrap').innerHTML;
    wpbakery_emsFormBuilder = v.includes('admin.php?page=vc-general')!=false ? true :false;
    if(wpbakery_emsFormBuilder && localStorage.getItem('wpbakery_efb') === null){ 
      localStorage.setItem('wpbakery_efb',true);
      alert_message_efb(`<a class="efb text-danger pointer-efb" href="https://whitestudio.team/document/wpbakery-easy-form-builder-v34/" target="_blank">${efb_var.text.wwpb}</a>`,'',15,'warning');
    }
   
  }, 1000); */

  if(document.getElementById('track_code_emsFormBuilder')){
    document.getElementById('track_code_emsFormBuilder').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
          event.preventDefault();
          fun_find_track_emsFormBuilder();
          return false;
        }});
  }
 

}, false);




function fun_switch_form_efb(el){
  change_el_edit_Efb(el);
  
/*   if(state_efb!='run'){ return}
  const v = valj_efb.find(x=>x.id_ ==el.dataset.vid);
  setTimeout(() => {
          
          let o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: "1", session: sessionPub_emsFormBuilder }];
          //console.log(el.classList ,el.classList.contains('active'));
          if(el.classList.contains('active')==false){
            o[0].value="0";
          }
          
          fun_sendBack_emsFormBuilder(o[0]);
  }, 100); */

}

window.addEventListener("popstate",e=>{
  
  const getUrlparams = new URLSearchParams(location.search);
  let v =g_page =getUrlparams.get('page') ? sanitize_text_efb(getUrlparams.get('page')) :"";
  if (v==null) return  valNotFound_efb();
  switch(e.state){
    case 'templates':
      add_dasboard_emsFormBuilder();
    break;
    case 'create':
      add_dasboard_emsFormBuilder();
    break;
    case 'panel':
      fun_emsFormBuilder_render_view(25);
      fun_hande_active_page_emsFormBuilder(1);
    break;
    case 'setting':
      fun_show_setting__emsFormBuilder();      
      fun_backButton(0);
      fun_hande_active_page_emsFormBuilder(2);
      break;
    case 'help':      
      fun_show_help__emsFormBuilder();
      fun_hande_active_page_emsFormBuilder(4);
      break;
    case 'search':
      v = localStorage.getItem("search_efb") ? sanitize_text_efb(localStorage.getItem("search_efb")) : null;
      if(v==null){
        
      }
      //console.log(`searchi =>${v}`)
      search_trackingcode_fun_efb(v)
      break;
    case 'show-message':
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;
      if (v==null) console.error('get[id] not found!');
      g_page = sanitize_text_efb(getUrlparams.get('form_type'));
      
      efb_var.msg_id =v;
      form_type_emsFormBuilder = g_page;
      // history.pushState("show-message",null,`page=Emsfb&state=show-messages&id=${id}&form_type=${row.form_type}`);
      fun_get_messages_by_id(Number(v));
      /* setTimeout(() => {
        emsFormBuilder_waiting_response();
        fun_backButton(0);
      }, 20); */
      
      fun_hande_active_page_emsFormBuilder(1);
    break;
    case "edit-form":
      //console.log('edit-form')
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;
      if (v==null) console.error('get[id] not found!');
      
      fun_get_form_by_id(Number(v));
      fun_backButton();
      fun_hande_active_page_emsFormBuilder(1);
    break;

  }
})

