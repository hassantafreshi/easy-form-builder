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
const efb_version =3.7;
let wpbakery_emsFormBuilder =false;
let pro_price_efb =19



if (sessionStorage.getItem("valueJson_ws_p")) sessionStorage.removeItem('valueJson_ws_p');
if(sessionStorage.getItem("formId_efb")) sessionStorage.removeItem('formId_efb');



jQuery(function () {
  efb_var= deepFreeze_efb(efb_var);
  state_check_ws_p = Number(efb_var.check);
  setting_emsFormBuilder=efb_var.setting;
  /* if(localStorage.getItem('v_efb')==null ||localStorage.getItem('v_efb')!=efb_var.v_efb ){
    localStorage.setItem('v_efb',efb_var.v_efb);
    location.reload(true);
  } */
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

  //cache message alert section start
  let count_show_efb_cache = localStorage.hasOwnProperty('efb_cache') ? Number(localStorage.getItem('efb_cache'))+1 : 0;
  if(efb_var.hasOwnProperty('plugins') && efb_var.plugins.cache != 0 && count_show_efb_cache<6){
    $val_noti = efb_var.text.excefb.replaceAll('XX', `<b>${efb_var.plugins.cache} </b>`);
    alert_message_efb('' ,$val_noti,  120 ,'warning' )
   
    localStorage.setItem('efb_cache',count_show_efb_cache);
  }
  //cache message alert section end
})




document.getElementById('wpfooter').remove();


function saveLocalStorage_emsFormBuilder() {

  sessionStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
  sessionStorage.setItem('valueJson_ws_p', JSON.stringify(valueJson_ws_p));
}


function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
    <div class="efb emsFormBuilder"><i class="efb bi-exclamation-triangle-fill"></i></div>
    <strong>${efb_var.text.alert} </strong>${val}
  </div>`
}


donwload_event_icon_efb =(color)=>{
  return `<div class="efb m-0 p-0 ${color}"><svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-cloud-arrow-down" viewBox="0 0 16 16" style="width: 100%; height: 100%;">
  <path fill-rule="evenodd" d="M7.646 10.854a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 9.293V5.5a.5.5 0 0 0-1 0v3.793L6.354 8.146a.5.5 0 1 0-.708.708l2 2z"/>
  <path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383zm.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z">
    <animate attributeName="opacity" values="1;0;1" dur="2s" repeatCount="indefinite" />
  </path>
</svg>
</div>
`
}
function Link_emsFormBuilder(state) {
  const lan =lan_subdomain_wsteam_efb();
  let link = `https://${lan}whitestudio.team/document`
  const github = 'https://github.com/hassantafreshi/easy-form-builder/wiki/'
  if(efb_var.language != "fa_IR" ){
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
        link = `https://${lan}whitestudio.team/documents/how-to-setup-and-use-the-stripe-on-easy-form-builder`;
        break;
      case 'ws':
        link = `https://${lan}whitestudio.team/`;
        break;
      case 'price':
        link = `https://${lan}whitestudio.team/#price`;
        break;
      case 'efb':
        link = "https://wordpress.org/plugins/easy-form-builder/";
        break;
      case 'wiki':
        link = `https://${lan}whitestudio.team/documents/`;
        break;
      case 'EmailNoti':
        link += "s/How-to-Set-Up-Form-Notification-Emails-in-Easy-Form-Builder";
        break;
      case 'redirectPage':
        link += "s/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder";
      break;
      case 'AdnSPF':
        //AdnSPF == strip payment
        link += 's/how-to-setup-and-use-the-stripe-on-easy-form-builder/';
        break;
        case 'AdnOF':
          //AdnOF == offline form
          link += "s/offline-forms-addon/";
       
        break;
        case 'AdnADP':
          //AdnADP == Hijiri date
          link += "s/how-to-install-islamic-date-in-easy-form-builder-plugin/";
       
        break;
      case 'wpbakery':
        //AdnOF == offline form
        link += 's/wpbakery-easy-form-builder-v34/';
        //link += "s/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder";
        break;
      case 'AdnPPF':
        //AdnPPF == persia payment
        link = `https://${lan}whitestudio.team`;
        break;
      case 'AdnATC':
        // AdnATC == advance tracking code
        break;
      case 'AdnSS':
      case 'smsconfig':
        link += "/settingup-sms-notifications-wordpress-easy-form-builder/";
        //AdnSS == sms service
        break;
      case 'AdnCPF':
       // AdnCPF == crypto payment
       break;
      case 'AdnESZ':
       //AdnESZ == zone picker
       break;
      case 'AdnSE':
        //AdnSE == email service
        //console.log(state)
        link = 'https://whitestudio.team/addons';
        break;
      case 'wpsmss':
        link ='https://wordpress.org/plugins/wp-sms/';
        break;
      case 'file_size':
        //https://whitestudio.team/document/guide-advanced-file-upload-forms-wordpress/
        link += "/guide-advanced-file-upload-forms-wordpress/"
        break;
      case 'support':
        link = `https://whitestudio.team/support/`;
        break;
      case 'EmailSpam':
        link += `s/send-email-using-smtp-plugin/`;
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
      case 'smsconfig':
        link +=`تنظیم-اطلاع-رسانی-پیامک-وردپرس-فرم-ساز/`;
         break;
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
      case 'file_size':
        //https://whitestudio.team/document/guide-advanced-file-upload-forms-wordpress/
        link += "/ایجاد-فرم-آپلود-فایل-پیشرفته-وردپرس";
        break;
      case 'support':
        link = `https://easyformbuilder.ir/support/`;
        break;
      case 'EmailSpam':
        link +=`ارسال-ایمیل-بوسیله-افزونه-smtp/`;
        break;
    }
  }

  
  window.open(link, "_blank");
}


function show_message_result_form_set_EFB(state, m) { //V2

  const cet = () => {
    const emailItem = valj_efb.find(item => item.type === 'email');
    return emailItem!=undefined && emailItem.hasOwnProperty('noti')  ? emailItem.noti  : false;
};
  // 3.8.6 start
  const wpbakery= `<p class="efb m-5 mx-3 fs-4"><a class="efb text-danger ec-efb" data-eventform="links" data-linkname="wpbakery">${efb_var.text.wwpb}</a></p>`
  // 3.8.6 end
  const title = `
  <h4 class="efb title-holder efb">
     <img src="${efb_var.images.title}" class="efb title efb">
     ${state != 0 ? `<i class="efb  bi-hand-thumbs-up title-icon mx-2"></i>${efb_var.text.done}` : `<i class="efb title-icon mx-2"></i>${efb_var.text.error}`}
  </h4>
  
  `;
  const e_s = cet();
  let e_m ='<div id="alert"></div>';
  if((efb_var.smtp==false || efb_var.smtp==0 || efb_var.smtp==-1) && (e_s==true || e_s==1)) {
    //howActivateAlertEmail
    // 3.8.6 start
    msg = `<br> <p>${efb_var.text.clickToCheckEmailServer }</p> <p>${efb_var.text.goToEFBAddEmailM }</p> <br> 
    <a class="efb btn btn-sm efb btn-danger text-white btn-r d-block ec-efb" data-eventform="links" data-linkname="EmailNoti"><i class="efb bi bi-patch-question  mx-1 ec-efb" data-eventform="links" data-linkname="EmailNoti"></i>${efb_var.text.howActivateAlertEmail}</a>
    `
    // 3.8.6 end
    e_m = alarm_emsFormBuilder(msg)
  }
  let content = ``
   
  if (state != 0) {
    content = ` <h3 class="efb"><b>${efb_var.text.goodJob}</b></br> ${state == 1 ? efb_var.text.formIsBuild : efb_var.text.formUpdatedDone}</h3>
    ${wpbakery_emsFormBuilder ? wpbakery :''}
  <h5 class="efb mt-3 efb">${efb_var.text.shortcode}: <strong>${m}</strong></h5>
  <input type="text" class="efb hide-input efb" value="${m}" id="trackingCodeEfb">
  ${e_m}
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


  document.getElementById('settingModalEfb-body').innerHTML = `<div class="efb card-body text-center efb">${title}${content}</div>`;
  //if(state == 0) state_modal_show_efb(1);
}//END show_message_result_form_set_EFB

console.info('Easy Form Builder WhiteStudio.team');


async function  actionSendData_emsFormBuilder() {
  //console.log('actionSendData_emsFormBuilder')
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }

  fun_pr =(s)=>{
    let cls = document.getElementById('NavBtnEFB-4').classList;
    if(s==1){
      cls.remove('d-none')
    }else if( s==0  && !cls.contains('d-none')){
      cls.add('d-none')
    }
 }
  
  data = {};
  var name = formName_Efb
  //console.log(sessionStorage.getItem('valj_efb'));
  // replace \" to "
  const ls_val=  sessionStorage.getItem('valj_efb').replace(/\\\"/g, '"');
  jQuery(function ($) {

    
    if (state_check_ws_p == 1) {
      
      data = {
        action: "add_form_Emsfb",
        value: ls_val,
        name: name,
        type: form_type_emsFormBuilder,
        nonce: efb_var.nonce
      };
    } else {
    
      data = {
        action: "update_form_Emsfb",
        value: ls_val,
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

          show_message_result_form_set_EFB(1, res.data.value);
          fun_pr(1);
        } else {
          alert(res, "error")
          show_message_result_form_set_EFB(0, res.data.value, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`)
          fun_pr(0);
        }
      } else if (res.data.r == "update" || res.data.r == "updated" && res.data.success == true) {
        show_message_result_form_set_EFB(2, res.data.value);
        
        sessionStorage.setItem('formId_efb', res.data.value);
        fun_pr(1);
      } else {
        
        if (res.data.m == null || res.data.m.length > 1) {          
          show_message_result_form_set_EFB(0, res.data.m, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-400`)
          fun_pr(0);
        } else {
          show_message_result_form_set_EFB(0, res.data.m, `${res.data.m}, Code:400-400`)
          fun_pr(0);
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
  <!-- 3.8.6 start -->
  <a class="efb float-end btn mx-1 efb rounded-pill border-danger text-danger ec-efb" data-eventform="links"  data-linkname="${i.name}"><i class="efb  bi-question-circle mx-1 ec-efb" data-eventform="links"  data-linkname="${i.name}"></i>${efb_var.text.help}</a>
  <!-- 3.8.6 end -->
  </div></div></div>`
}
funProEfb=()=>{return `<div class="efb  pro-card"><a type="button" onClick='pro_show_efb(1)' class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb  bi-gem text-light"></i></a></div>`}
const boxs_efb = [
  { id: 'form', title: efb_var.text.newForm, desc: efb_var.text.createBlankMultistepsForm, status: true, icon: 'bi-check2-square', tag: 'all new', pro: false },
 // { id: 'booking', title: efb_var.text.newbkForm, desc: efb_var.text.createBlankMultistepsForm, status: true, icon: 'bi-check-circle-fill', tag: 'all new', pro: true },
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
    <li class="efb efb-col-3 col-lg-1 col-md-2 col-sm-2 col-sx-3 mb-2  m-1 p-0 text-center">
      <a class="efb nav-link m-0 p-0 cat fs-6  ${i} ${i=='all' ? 'active' :''}" aria-current="page" onclick="funUpdateLisetcardTitleEfb('${i}')" role="button">${efb_var.text[i]}</a>
    </li>
    `
  }

 cardtitles = `
    <ul class="efb mt-4 mb-3 p-0 d-flex justify-content-center row" id="listCardTitleEfb">${cardtitles}
    <hr class="efb hr">
    </ul>
    `

  document.getElementById('tab_container_efb').innerHTML = `

          ${head_introduce_efb('create')}
          <section id="content-efb">
          ${!mobile_view_efb ? `<img src="${efb_var.images.title}" class="efb ${efb_var.rtl == 1 ? "right_circle-efb" : "left_circle-efb"}"><h4 class="efb title-holder efb fs-4"><img src="${efb_var.images.title}" class="efb title efb create"><i class="efb  bi-arrow-down-circle title-icon mx-1 fs-4"></i>${efb_var.text.forms}</h4>` : ''}
          <div class="efb d-flex justify-content-center ">
            <input type="text" placeholder="${efb_var.text.search}" id="findCardFormEFB" class="efb fs-6 search-form-control rounded-4 efb mx-2"> <a class="efb btn efb btn-outline-pink mx-1" onClick="FunfindCardFormEFB()" >${efb_var.text.search}</a>
            
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
      /* setTimeout(() => {
        msg_colors_from_template()
      }, 1000); */
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
     if((efb_var.language!='fa_IR' && (i.name!='AdnPPF') ) || efb_var.language=='fa_IR' ) value += createCardAddoneEfb(v)
    }
  }
  let cardtitles = `<!-- card titles -->`;


 cardtitles = `
    <ul class="efb mt-4 mb-3 p-0 d-flex justify-content-center row" id="listCardTitleEfb">${cardtitles}
    <hr class="efb hr">
    </ul>
    `

  document.getElementById('tab_container_efb').innerHTML = `

          ${head_introduce_efb('create')}
          <section id="content-efb">
          ${!mobile_view_efb ? `<h4 class="efb  mb-0 title-holder fs-4 efb"><img src="${efb_var.images.title}" class="efb title efb create"><i class="efb  bi-plus-circle title-icon fs-4 mx-1"></i>${efb_var.text.addons}</h4>` : ''}
  
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
  
  const adminEmail = efb_var.setting.emailSupporter  ;
  const smail =adminEmail!=''  ? true :false
  sessionStorage.removeItem('valj_efb');
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
    const json = [{ "type": "form", "steps": 1, "formName": efb_var.text.contactUs, "email":adminEmail, 'sendEmail': smail, "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "2jpzt59do", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.contactusForm, "icon": "bi-chat-right-fill", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-muted", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "uoghulv7f", "dataId": "uoghulv7f-id", "type": "text", "placeholder": efb_var.text.firstName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.firstName, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "xzdeosw2q", "dataId": "xzdeosw2q-id", "type": "text", "placeholder": efb_var.text.lastName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.lastName, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "2jpzt59do", "dataId": "2jpzt59do-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 6, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , "noti":1},
    { "id_": "dvgl7nfn0", "dataId": "dvgl7nfn0-id", "type": "textarea", "placeholder": efb_var.text.enterYourMessage, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.message, "required": true, "amount": 7, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    //console.log(JSON.stringify(json));
    valj_efb = json;
  } else if (id === "contactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "multipleStepContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = multiple_step_ontact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "privateContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = private_contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "curvedContactTemplate") {
    //contactUs v2
    form_type_emsFormBuilder = "form";
    const json = curved_contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "register") {
    form_type_emsFormBuilder = "register";
    //register v2
    json = [{ "type": "register", "steps": 1, "formName": efb_var.text.register, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.register, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "emailRegisterEFB", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message":textThankUEFB('register'), "email_temp": "", "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.registerForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "usernameRegisterEFB", "dataId": "usernameRegisterEFB-id", "type": "text", "placeholder": efb_var.text.username, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.username, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "besie",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordRegisterEFB", "dataId": "passwordRegisterEFB-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "emailRegisterEFB", "dataId": "emailRegisterEFB-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 9, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , "noti":1 }]
    valj_efb = json;
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "login") {
    // login v2
    form_type_emsFormBuilder = "login";
    json = [{ "type": "login", "steps": 1, "formName": efb_var.text.login, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.login, "button_color": "btn-darkb", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.loginForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-dark", "icon_color": "text-danger", "visible": 1 },
    { "id_": "emaillogin", "dataId": "emaillogin-id", "type": "text", "placeholder": efb_var.text.emailOrUsername, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.emailOrUsername, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordlogin", "dataId": "passwordlogin-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    valj_efb = json;

    sessionStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "support") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = [{ "type": "form", "steps": 1, "formName": efb_var.text.support, "email":adminEmail, 'sendEmail': smail, "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "qas87uoct", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "Support", "icon": "bi-ui-checks-grid", "step": "1", "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-dark", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "rhglopgi8", "dataId": "rhglopgi8-id", "type": "select", "placeholder": "Select", "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": "How can we help you?", "required": true, "amount": 2, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "b2xssuo2w", "dataId": "b2xssuo2w-id", "parent": "rhglopgi8", "type": "option", "value": "Accounting & Sell question", "id_op": "n470h48lg", "step": "1", "amount": 3 },
    { "id_": "b2xssuo2w", "dataId": "b2xssuo2w-id", "parent": "rhglopgi8", "type": "option", "value": "Technical & support question", "id_op": "zu7f5aeob", "step": "1", "amount": 4 },
    { "id_": "jv1l79ir1", "dataId": "jv1l79ir1-id", "parent": "rhglopgi8", "type": "option", "value": "General question", "id_op": "jv1l79ir1", "step": "1", "amount": 5 },
    { "id_": "59c0hfpyo", "dataId": "59c0hfpyo-id", "type": "text", "placeholder": efb_var.text.subject, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.subject, "required": 0, "amount": 6, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "qas87uoct", "dataId": "qas87uoct-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 10, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false, "noti":1 },
    { "id_": "cqwh8eobv", "dataId": "cqwh8eobv-id", "type": "textarea", "placeholder": efb_var.text.message, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.message, "required": true, "amount": 8, "step": 2,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": pro_efb }]
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "supportTicketForm") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = support_ticket_form_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "orderForm") {
    // support v2
    form_type_emsFormBuilder = "payment";
    const json = order_payment_form_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "customerFeedback") {
    // support v2
    form_type_emsFormBuilder = "form";
    const json = customer_feedback_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "subscription") {
    // if subscription has clicked add Json of contact and go to step 3
    form_type_emsFormBuilder = "subscribe";
    const json =
      [{ "type": "subscribe", "steps": 1, "formName": efb_var.text.subscribe, "email":adminEmail, 'sendEmail': smail, "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.subscribe, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-d-efb", "email_to": "82i3wedt1", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true },
      { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "", "icon": "bi-check2-square", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
      { "id_": "janf5eutd", "dataId": "janf5eutd-id", "type": "text", "placeholder": efb_var.text.name, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.name, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
      { "id_": "82i3wedt1", "dataId": "82i3wedt1-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , 'noti':1 }]
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id == "survey") {
    form_type_emsFormBuilder = "survey";
    const json = [{ "type": "survey", "steps": 1, "formName": efb_var.text.survey, "email":adminEmail, 'sendEmail': smail, "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false },
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
    
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id == "reservation") {

  } else if (id == "payment") {
    
    form_type_emsFormBuilder = "payment";
    valj_efb = [];

  }else if (id == "booking") {
    valj_efb = [{ "type": "form", "steps": 1, "formName":'booking', "email":adminEmail, 'sendEmail': smail, "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "qas87uoct", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true ,"booking":true},
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "booking form", "icon": "bi-check2", "step": "1", "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },];
    form_type_emsFormBuilder = "form";
    valueJson_ws_p=valj_efb;
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


// 3.8.6 start
function head_introduce_efb(state) {
  
  
  const link = state == "create" ? '#form' : 'admin.php?page=Emsfb_create'
  let text = `${efb_var.text.efbIsTheUserSentence} ${efb_var.text.efbYouDontNeedAnySentence}`
  let btnSize = mobile_view_efb ? '' : 'btn-lg';

  let cont = ``;
  let vType = `<div class="efb mx-3 col-lg-4 mt-2 pd-5 col-md-10 col-sm-12 alert alert-light pointer-efb buy-noti ec-efb" data-eventform="links" data-linkname="price">
  <i class="efb bi-diamond text-pinkEfb mx-1 ec-efb" data-eventform="links" data-linkname="price"></i>
  <span class="efb text-dark">${efb_var.text.getPro}</span><br>
  ${efb_var.text.yFreeVEnPro.replace('NN', pro_price_efb)}
  </div>`;
  if (state != "create") {
    cont = `
    
                  <div class="efb clearfix"></div>                 
                  <p class="efb card-text  ${state == "create" ? 'card-text' : 'text-dark'} efb pb-3 ${mobile_view_efb ? 'fs-7' : 'fs-6'}">${text}</p>
                  
    <a class="efb btn btn-r btn-primary ${btnSize}" href="${link}"><i class="efb  bi-plus-circle mx-1"></i>${efb_var.text.createForms}</a>
    <a class="efb btn mt-1 efb btn-outline-pink ${btnSize} ec-efb" data-eventform="links" data-linkname="tutorial"><i class="efb  bi-info-circle mx-1 ec-efb" data-eventform="links" data-linkname="tutorial"></i>${efb_var.text.tutorial}</a>`;
  }
  return `<section id="header-efb" class="efb mx-0 px-0  ${state == "create" ? '' : 'card col-12 bg-color'}">
  <div class="efb row ${mobile_view_efb ? 'mx-2' : 'mx-5'}">
              <div class="efb col-lg-7 mt-2 pd-5 col-md-12">
                  <img src="${efb_var.images.logo}" class="efb description-logo  ${mobile_view_efb ? 'm-1' : ''} efb">
                  <h1 class="efb  pointer-efb mb-0 ${mobile_view_efb ? 'fs-6' : ''} ec-efb" data-eventform="links" data-linkname="efb">${efb_var.text.easyFormBuilder}</h1>
                  <h3 class="efb  pointer-efb  ${state == "create" ? 'card-text ' : 'text-darkb'} ${mobile_view_efb ? 'fs-7' : 'fs-6'} ec-efb" data-eventform="links" data-linkname="ws" >${efb_var.text.byWhiteStudioTeam}</h3>
                  ${cont}
                 
              </div>
              ${state == "create" && (efb_var.pro==false || efb_var.pro =='false') ? vType : ''}
              ${(state != "create" && mobile_view_efb) ? `<div class="efb col-lg-5 col-md-12 "> <img src="${efb_var.images.head}" class="efb img-fluid"></div>` : ''}
              ${(state != "create" && mobile_view_efb == false) ? `<div class="efb col-lg-5 col-md-12 "> <img src="${efb_var.images.head}" class="efb img-fluid"></div>` : ''}  
    </div>  
  </section> `
}
// 3.8.6 end

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
    case '#ffffff': c = "white"; break;
    case '#212529': c = "dark"; break;
    case '#777777': c = "muted "; break;
    default: c = "colorDEfb-" + color.slice(1);
  }
  return c;
}

ColorNameToHexEfbOfElEfb = (v, i, n) => {
  //console.log('ColorNameToHexEfbOfElEfb',v,i,n)
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
    case "white": r = '#ffffff'; break;
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
    document.getElementById('sideMenuConEfb').innerHTML = efbLoadingCard('')
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
    postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `null`
    let cornEl = 'null';
   
    if(postId!='null'){ 
      cornEl =document.getElementById(postId)    
      if (cornEl==null &&fun_el_select_in_efb(el.dataset.tag)) cornEl = document.getElementById(`${postId}options`)
      if (el.dataset.tag == 'esign') cornEl = document.getElementById(`${valj_efb[indx].id_}_b`)     
    }else{


     if (el.dataset.tag == 'dadfile') cornEl = document.getElementById(`${valj_efb[indx].id_}_box`)
    }
    //console.log(postId,cornEl,co ,el);
    cornEl.className = cornerChangerEfb(cornEl.className, co)

  } else if (el.dataset.side == "yesNo") {
    valj_efb[indx].corner = co;
    document.getElementById(`${valj_efb[indx].id_}_b_1`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, co)
    document.getElementById(`${valj_efb[indx].id_}_b_2`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, co)
  } else {

    valj_efb[0].corner = co;
    postId = document.getElementById('btn_send_efb');
    //postId.classList.toggle('efb-square')
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
   //console.log("=================>change_el_edit_Efb",el.id , el.value)
  if (el.value.length > 0 && (el.value.search(/(")+/g) != -1 || el.value.search(/(>)+/g) != -1 || el.value.search(/(<)+/g) != -1) && el.id !="htmlCodeEl") {
    el.value = el.value.replaceAll(`"`, '');   
    alert_message_efb(efb_var.text.error, `Don't use forbidden characters like: ["][<][>]`, 10, "danger");
    return;
  }else if (el.id =="htmlCodeEl"){
    el.value = el.value.replaceAll(`"`, `'`);
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
  let indx = el.dataset.id != "button_group" && el.dataset.id != "button_group_" && postId != 0 ? valj_efb.findIndex(x => x.dataId == postId || x.dataId==postId+'-id') : 0;
  const len_Valj = valj_efb.length;
  
  postId = null

  let clss = ''
  let c, color;
  //console.log('tesssssssssssssssssssssssss',el,el.hasOwnProperty('value'));
  setTimeout(() => {
    
    if(el.hasAttribute('value') && el.id!="htmlCodeEl"){ 
      
      el.value = el.type!="url" ? sanitize_text_efb(el.value) :el.value.replace(/[<>()[\ ]]/g, '');
    }
      if (el.value==null) return  valNotFound_efb()
    //console.log(el.id)
    
    switch (el.id) {
      case "labelEl":
        
        valj_efb[indx].name = el.value;
        document.getElementById(`${valj_efb[indx].id_}_lab`).innerHTML = sanitize_text_efb(el.value)
        
        break;
      case "desEl":
        valj_efb[indx].message = el.value;
        document.getElementById(`${valj_efb[indx].id_}-des`).innerHTML =sanitize_text_efb(el.value)
        break;
      case 'bookDateExpEl':
        if(valj_efb[indx].hasOwnProperty('dateExp')==false) Object.assign( valj_efb[indx] , {dateExp:''})
        valj_efb[indx].dateExp = el.value;
        break;
      case "mLenEl":
        if (Number(el.value)>524288 && valj_efb[indx].type!="range"){
          el.value="";
          alert_message_efb("",efb_var.text.mmlen,15,"warning")
        }else{
          //console.log(valj_efb[indx])
          clss= valj_efb[indx].type=="date" ? 1 :0;
          if(valj_efb[indx].hasOwnProperty('mlen')==false) Object.assign(valj_efb[indx],{mlen:'0'})
         
          if(clss==1){
            c = /^(0|1|\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])|)$/;
            if (c.test(el.value)) {
            
              valj_efb[indx].mlen = sanitize_text_efb(el.value);
              //get current date with this syntax YYYY-MM-DD
            /*   c= Date().toISOString().split('T')[0];
              c.toString().slice(0, 10); */
              c = el.value ==0 ?  0 : el.value !=1 ? el.value : c;
              //check if c is less than milen date
              
            } else {
              //mnvvXXX  XXX
              let m = efb_var.text.mnvvXXX;
              //mxdt
             
              
              m  = m.replace('XXX', "<b>" +  efb_var.text.mxdt + "</b>");
              m += " "+  efb_var.text.ivf.replace('%s', "YYYY-MM-DD, 1");    
              alert_message_efb("", m,15,"warning")        
              el.value ='';
            }

          }else{
            valj_efb[indx].mlen = el.value;
          }

          //console.log(valj_efb[indx])
          if(valj_efb[indx].hasOwnProperty("milen") && 
          Number(valj_efb[indx].mlen)<Number(valj_efb[indx].milen)){
            alert_message_efb("",efb_var.text.mxlmn,15,"warning")
            delete  valj_efb[indx].mlen;
            el.value=0;
            break;
          }
          
        } 
        if(valj_efb[0].hasOwnProperty('booking')== true && valj_efb[indx].hasOwnProperty("registered_count")==false) Object.assign(valj_efb[indx],{"registered_count":0})
        
        break;
      case "textEl":
        
        valj_efb[indx][el.dataset.atr] =sanitize_text_efb(el.value);
        c =  valj_efb[indx].id_ +"_"+el.dataset.atr
        //console.log(el.dataset,c)
        document.getElementById(c).innerHTML=sanitize_text_efb(el.value);
        break;
      case "miLenEl":
        if( Number(el.value)==0 ||Number(el.value)==-1 ){
         // console.log('test3')
          //console.log(Number(el.value),'inside ==',valj_efb[indx].id_ ,valj_efb[indx].type)
          //pflm6h0n7_req
          clss = document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML;
          
          valj_efb[indx].required = clss.length!=0 ? 1 :0;
          
          valj_efb[indx].milen=0;
        }else if (Number(el.value)>524288 && valj_efb[indx].type!="range" ){
        // console.log('test1')
          el.value="";
          alert_message_efb("",efb_var.text.mmlen,15,"warning")
          valj_efb[indx].milen=0;
        }else{
          clss= valj_efb[indx].type!="date" ? 1 :0;
          valj_efb[indx].milen = sanitize_text_efb(el.value);
          if(valj_efb[indx].hasOwnProperty("mlen") && 
          Number(valj_efb[indx].mlen)<Number(valj_efb[indx].milen) && clss==1){
            alert_message_efb("",efb_var.text.mxlmn,15,"warning")
            delete  valj_efb[indx].milen;
            el.value=0;
            break;
          }else if (clss==0 ){
            //check milen date and mlen date if mlen date is less than milen date then alert
            //+date
            c = /^(0|1|\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])|)$/;
            
            
            
            clss = /^1$/
            if (c.test(el.value)) {
              
              valj_efb[indx].milen = sanitize_text_efb(el.value);
              //get current date with this syntax YYYY-MM-DD
             /*  c= Date().toISOString().split('T')[0];
              c.toString().slice(0, 10); */
              c = el.value ==0 ?  0 : el.value !=1 ? el.value : c;
              //check if c is less than milen date
              
            } else {
              let m = efb_var.text.mnvvXXX;
              m  = m.replace('XXX', "<b>" +  efb_var.text.mindt + "</b>");
              m += " "+  efb_var.text.ivf.replace('%s', "YYYY-MM-DD, 1");    
              alert_message_efb("", m,15,"warning")  
              el.value ='';
            }
            
          }
          if(valj_efb[indx].type!="range" || alj_efb[indx].type!="date")valj_efb[indx].required=1;
          
        }     
        break;
      case "adminFormEmailEl":
        
        if (efb_var.smtp == "1") {
          // if el.value have , then split it and check all of them
           if(el.value.includes(',')){
            
            let emails=el.value.split(',');
            let isEmail=true;
              emails.forEach((email)=>{
                email=email.trim();
                if (email.match(/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/)==null) isEmail=false;
                if(isEmail==false) {
                  alert_message_efb(efb_var.text.error, efb_var.text.invalidEmail+ ` (${email})`, 10, "danger");
                  valj_efb[0].email="";
                  return false;
                }
              })
              valj_efb[0].email = el.value.trim();
              valj_efb[0].sendEmail=true;
              break;
            }else{
            
              if (el.value.match(/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/)) // email validation
              {
                valj_efb[0].email = el.value;
                valj_efb[0].sendEmail=true;
                break;
              }
              else {
                if (el.value!="") alert_message_efb(efb_var.text.error, efb_var.text.invalidEmail, 10, "danger");
                document.getElementById("adminFormEmailEl").value = "";
                valj_efb[0].email="";
              
              }
            }
        } else if (efb_var.smtp == '-1') {
          if(document.getElementById("adminFormEmailEl"))document.getElementById("adminFormEmailEl").value = "";
          
          alert_message_efb(efb_var.text.error, efb_var.text.goToEFBAddEmailM, 30, "danger");
        } else {
          // trackingCodeEl.checked=false;
          if(document.getElementById("adminFormEmailEl"))document.getElementById("adminFormEmailEl").value = "";
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
              el.classList.remove('active');         
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
        case "hiddenEl":
          valj_efb[indx].hidden= el.classList.contains('active')==true ? 1 :0;
          if(valj_efb[indx].hidden==1){
           c= document.getElementById(valj_efb[indx].id_);
           clss= document.createElement('div');
           clss.id=valj_efb[indx].id_+"-hidden";
           c.insertBefore(clss, c.firstChild);
           document.getElementById(valj_efb[indx].id_+"-hidden").innerHTML= hiddenMarkEl( valj_efb[indx].id_);
           document.getElementById(valj_efb[indx].id_).classList.add("hidden");
          }else{
            document.getElementById(`${valj_efb[indx].id_}-hidden`).remove();
            document.getElementById(valj_efb[indx].id_).classList.remove("hidden");
          }
          break;
        case "disabledEl":
          postId= valj_efb[indx].id_;
          clss= document.getElementById(postId).classList;
          c= el.classList.contains('active')==true ? 1 :0
          c==1 ? clss.add('disabled') : clss.remove('disabled');
          valj_efb[indx].disabled=c ;
          //console.log(c ,document.getElementById(c).classList )
          break;
      case "SendemailEl":
       
        if (efb_var.smtp == "true" || Number(efb_var.smtp) == 1 ) {
          
          //valj_efb[0].sendEmail = el.checked
          postId= valj_efb[indx].id_;
          valj_efb[0].email_to = el.dataset.vid;
          c= el.classList.contains('active')==true ? 1 :0
          clss= document.getElementById(postId).classList;
          valj_efb[indx].hasOwnProperty('noti')==false?  Object.assign(valj_efb[indx],{'noti':c}) : valj_efb[indx].noti = c;
          //console.error(valj_efb[indx]);
          //valj_efb[0].sendEmail=true;
          if(valj_efb[0].email.length<2){
            for(let v of valj_efb){
                if(v.hasOwnProperty('noti') && Number(v.noti) ==1){
                  valj_efb[0].sendEmail=true;
                  
                }else{
                  if(valj_efb[0].email_to==v.id_){
                    valj_efb[0].email_to="";                    
                  }
                }
            }
          }
          
        } else {
          // trackingCodeEl.checked=false;
          el.classList.remove('active');
          // 3.8.6 start
          const msg =  efb_var.text.sMTPNotWork + '' + `<a class="efb alert-link ec-efb" data-eventform="links" data-linkname="EmailNoti"> ${efb_var.text.orClickHere}</a>`;
          // 3.8.6 end
          alert_message_efb(efb_var.text.error,msg, 20, "danger")
        }

        break;
      case "smsEnableEl":
       
       //check pro version activate
       if(pro_efb!=true){        
          pro_show_efb(1);
          document.getElementById("smsEnableEl").checked = false;
          document.getElementById("smsEnableEl").classList.remove('active') ;
       }
       if(Number(efb_var.setting.AdnSS)!=1){
        
        document.getElementById("smsEnableEl").classList.remove('active') ;
        //alert_message_efb(efb_var.text.error, efb_var.text.goToEFBAddSMSM, 20, "danger")
        let m = efb_var.text.msg_adons.replace('NN',`<b>${efb_var.text.sms_noti}</b>`);
        //console.log(m)
        //noti_message_efb(m, 'danger' , `content-efb` );
        alert_message_efb(efb_var.text.error, m, 20, "danger")
        return false;
       }
       if(indx==-1){indx=0};
          c = el.classList.contains('active')==true ? 1 :0
        
        valj_efb[indx].hasOwnProperty('smsnoti')==false ? Object.assign(valj_efb[indx],{'smsnoti':c}) : valj_efb[indx].smsnoti = c;
        //add sms message to valj_efb
        
        
        if(indx!=0){
          if(c==1){
            indx=0;
          }else{
            clss=-1;
            clss= valj_efb.findIndex(x => x.smsnoti == 1);
          }
         }
        if(indx==0){
          if (c==1){
            //remove disabled class from all input has sms-efb
            //console.log('remove disbaled!')
            const smsEls = document.querySelectorAll('.smsmsg')
            smsEls.forEach((el)=>{
              
              el.disabled=false;
              el.classList.remove('disabled');
              el.classList.remove('d-none');
            })
          }else{
            //add disabled class from all input has sms-efb
            //console.log('add disbaled!')
            const smsEls = document.querySelectorAll('.smsmsg')
            smsEls.forEach((el)=>{
              
              el.disabled=true;
              el.classList.add('disabled');
              el.classList.add('d-none');
            })
          }

          if(valj_efb[0].hasOwnProperty('smsnoti')!=false){
            //get document by dataset.id
           
            c= document.querySelector(`[data-id="newMessageReceived`).value
            c= sanitize_text_efb(c ,true);
            Object.assign(valj_efb[0], { sms_msg_new_noti: c });

            if(valj_efb[0].type!="register" && valj_efb[0].type!="login"){
            c= document.querySelector(`[data-id="WeRecivedUrM`).value
            c= sanitize_text_efb(c ,true);
            Object.assign(valj_efb[0], { sms_msg_recived_usr: c });
            
            c= document.querySelector(`[data-id="responsedMessage`).value
            c= sanitize_text_efb(c ,true);
            Object.assign(valj_efb[0], { sms_msg_responsed_noti:c });
            }else{
              Object.assign(valj_efb[0], { sms_msg_responsed_noti:'' });
              Object.assign(valj_efb[0], { sms_msg_recived_usr: '' });
            }

          }
        }
        
        break;
      case "smsAdminsPhoneNoEl":
        //validate el.value for international phone number and seprate them by comma
        
        if(el.value.includes(',')){
          let phones=el.value.split(',');
          let isPhone=true;
            phones.forEach((phone)=>{
              phone=phone.trim();
              //start with + and have 8 to 15 digit
              if (phone.match(/^\+[0-9]{8,15}$/)==null) isPhone=false;
              if(isPhone==false) {
                alert_message_efb(efb_var.text.error, efb_var.text.pleaseEnterVaildValue + ` (${phone})`, 10, "danger");
                valj_efb[0].sms_admins_phone_no="";
                return false;
              }
            })
            valj_efb[0].sms_admins_phone_no = el.value.trim();
          }
          else{
            if (el.value.match(/^\+[0-9]{8,15}$/)) // phone validation
            {
              valj_efb[0].sms_admins_phone_no = el.value;
              return true;
            }
            else {
              alert_message_efb(efb_var.text.error, efb_var.text.pleaseEnterVaildValue + ` (${phone})`, 10, "danger");             
              valj_efb[0].sms_admins_phone_no="";
              return false;
            }
          }
          
            
        break;
      case "formNameEl":
        valj_efb[0].formName = sanitize_text_efb(el.value)
        break;
      case "trackingCodeEl":
        valj_efb[0].trackingCode =  el.classList.contains('active') ? true : false;

        break;
      case "thankYouMessageDoneEl":
        valj_efb[0].thank_you_message.done = sanitize_text_efb(el.value);
        break;
      case "thankYouMessageEl":
        valj_efb[0].thank_you_message.thankYou = sanitize_text_efb(el.value);
        break;
      case "thankYouMessageConfirmationCodeEl":
        valj_efb[0].thank_you_message.trackingCode = sanitize_text_efb(el.value);
        break;
      case "thankYouMessageErrorEl":
        valj_efb[0].thank_you_message.error = sanitize_text_efb(el.value);
        break;
      case "thankYouMessagepleaseFillInRequiredFieldsEl":
        valj_efb[0].thank_you_message.pleaseFillInRequiredFields = sanitize_text_efb(el.value);
        break;
      case "captchaEl":
       
          fun_add_Class_captcha=(state)=>{
           
           state ?   document.getElementById('dropZoneEFB').classList.add('captcha') : document.getElementById('dropZoneEFB').classList.remove('captcha')
            
            
          }
        if (efb_var.captcha == "true" && valj_efb[0].type != "payment") {
          //console.log(`captcha!!!`,el.classList)
          valj_efb[0].captcha = el.classList.contains('active')==true ? true : false
          fun_add_Class_captcha(valj_efb[0].captcha);
          if(document.getElementById('recaptcha_efb')) el.classList.contains('active') == true ? document.getElementById('recaptcha_efb').classList.remove('d-none') : document.getElementById('recaptcha_efb').classList.add('d-none')

        } else if (valj_efb[0].type == "payment") {
          document.getElementById("captchaEl").checked = false;
          fun_add_Class_captcha(false);
          alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.paymentNcaptcha, 20, "danger")
        } else {
          // trackingCodeEl.checked=false;
          document.getElementById("captchaEl").checked = false;
          fun_add_Class_captcha(false);
          alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.reCAPTCHASetError, 20, "danger")

        }
        //console.log(`[${efb_var.captcha }]`)
        if (efb_var.captcha !=true && efb_var.captcha !="true" ){
          el.classList.remove('active');         
       }
        break;
      case "showSIconsEl":
        valj_efb[0].show_icon =  el.classList.contains('active')==true ? true : false
        break;
      case "showSprosiEl":
        valj_efb[0].show_pro_bar = el.classList.contains('active')==true ? true : false
        break;
      case "showformLoggedEl":
        
        valj_efb[0].stateForm = el.classList.contains('active')==true ? true : false
        break;
      case 'emailNotiContainsEl':
       
        if(valj_efb[0].hasOwnProperty('email_noti_type')==false) Object.assign(valj_efb[0],{'email_noti_type':el.options[el.selectedIndex].value})
        valj_efb[0].email_noti_type = el.options[el.selectedIndex].value;
        
        break;
      case "placeholderEl":
        document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).placeholder = sanitize_text_efb(el.value);

        valj_efb[indx].placeholder = sanitize_text_efb(el.value);
        break;
      case "enableConEl":
         clss=true;
         const show_l_o =()=>{
          document.getElementById('logic_options').classList.remove('d-none');
          document.getElementById('logic_options').classList.add('d-block');
         }
         postId =el.dataset.setid;
          if(valj_efb[0].hasOwnProperty('logic')==false) {
            Object.assign(valj_efb[0],{'logic':true})
            Object.assign(valj_efb[0],{'conditions':[]})
            clss=false;
          }
          c =  el.classList.contains('active')==true ? true : false
          
          if(clss==true){
            clss= valj_efb[0].conditions.findIndex(x=>x.id_==postId)
            if(clss!=-1){
              valj_efb[0].conditions[clss].state=c;
              if (c==false){
                //logic_options
                document.getElementById('logic_options').classList.remove('d-block');
                document.getElementById('logic_options').classList.add('d-none');
              }else{
                show_l_o();
                valj_efb[0].logic =true;
              }
            }else{
              if(c==true){
                show_l_o();
                valj_efb[0].conditions.push({id_:postId, state:c,show:true, condition:[{no:"0" , term:'is' , one:'', two:''}]});
              }
            }
          }else{
            show_l_o();
            valj_efb[0].conditions.push({id_:postId, state:c,show:true, condition:[{no:"0" , term:'is' , one:'', two:''}]});

          }

          if(c==false || c==0){
            
           for(var i=0 ; i<valj_efb[0].conditions.length ; i++){
            
            if(valj_efb[0].conditions[i].state==true) c=true;
           }
           if(c!=true) valj_efb[0].logic=false;
          }
            
          break;
      case "valueEl":
        
        if (el.dataset.tag != 'yesNo' && el.dataset.tag != 'heading' && el.dataset.tag != 'textarea' && el.dataset.tag != 'link') {

          //document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).value = el.value;
          c= sanitize_text_efb(el.value);
          document.getElementById(`${valj_efb[indx].id_}_`).value = c;
          valj_efb[indx].value = c;
        } else if (el.dataset.tag == 'heading' ||el.dataset.tag == 'link' ||el.dataset.tag == 'textarea') {
          //console.log(valj_efb[indx].id_,document.getElementById(`${valj_efb[indx].id_}_`) );
          c= el.dataset.tag=='textarea' ? sanitize_text_efb(el.value ,true) : sanitize_text_efb(el.value);
          document.getElementById(`${valj_efb[indx].id_}_`).innerHTML = efb_text_nr(c,0);
          valj_efb[indx].value = c;
        } else {
          //yesNo
          c= sanitize_text_efb(el.value); 
          id = `${valj_efb[indx].id_}_${el.dataset.no}`
          document.getElementById(id).value = c;
          document.getElementById(`${id}_lab`).innerHTML =c;
          el.dataset.no == 1 ? valj_efb[indx].button_1_text = c : valj_efb[indx].button_2_text = c
        }
        break;
      case "classesEl":
        id = valj_efb[indx].id_;
        for(let d of document.querySelectorAll(`[data-css='${id}']`)){
          const v = el.value.replace(` `, `,`);
          
          clss = d.className;
          //console.log(d.classList.contains('efb1'))
          if(d.classList.contains('efb1')==true){
            c= clss.indexOf('efb1');
    
            clss= clss.slice(0,c);
   
          }
          d.className =clss+" efb1 "+ sanitize_text_efb(el.value.replace(`,`, ` `));
          //console.log(d, id)
          valj_efb[indx].classes = sanitize_text_efb(v);
        }
        break;
      case "sizeEl":
        postId = document.getElementById(`${valj_efb[indx].id_}_labG`)
        if (valj_efb[indx].hasOwnProperty('size')) Object.assign(valj_efb[indx],{size:100});
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
          if (fun_el_select_in_efb(el.dataset.tag)) cornEl = el.dataset.tag == 'conturyList' || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'cityList' || el.dataset.tag == 'select' ? document.getElementById(`${postId}options`) : document.getElementById(`${id}ms`)
          //efb-square

          cornEl.classList.toggle('efb-square')
          if (el.dataset.tag == 'dadfile' || el.dataset.tag == 'esign') document.getElementById(`${valj_efb[indx].id_}_b`).classList.toggle('efb-square')


        } else {
          valj_efb[0].corner = co;
          postId = document.getElementById('btn_send_efb');

          //postId.classList.toggle('efb-square')
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
        ///^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/
        
        
        postId = el.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/gi)
        if(pro_efb!=true){
          pro_show_efb(1);
          valj_efb[0].thank_you ='msg';
          valj_efb[0].rePage = '';
          break;
        }
        const u = (url)=>{
          url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
          url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
          url = url.replace(/([/])+/g, '@efb@');
          return url
         }
       if (postId != null) {      
        valj_efb[0].rePage = u(el.value);
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
      case "formTypeEl":
        valj_efb[0].type = el.options[el.selectedIndex].value;
        form_type_emsFormBuilder = valj_efb[0].type;

        break;
      case "currencyTypeEl":
        if(valj_efb[0].hasOwnProperty('currency')==false) Object.assign(valj_efb[0],{'currency':'USD '})
        valj_efb[0].currency = el.options[el.selectedIndex].value.slice(0, 3);
        //document.getElementById('currencyPayEfb').innerHTML = valj_efb[0].currency.toUpperCase()
        for (const l of document.querySelectorAll(".totalpayEfb")) {
         if(l.classList.contains('ir')==false) l.innerHTML = Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
        }
        funRefreshPricesEfb();
        
        break;
      case "fileTypeEl":
        valj_efb[indx].file = el.options[el.selectedIndex].value;

        //console.log(valj_efb[indx].file)
        valj_efb[indx].value = el.options[el.selectedIndex].value;
        let nfile = el.options[el.selectedIndex].value.toLowerCase();
        nfile = efb_var.text[nfile];
        //console.log(el.options[el.selectedIndex].value)
        if(el.options[el.selectedIndex].value=='customize'){nfile =valj_efb[indx].file_ctype}
          

        if (document.getElementById(`${valj_efb[indx].id_}_txt`)) document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${nfile}`
        const elc = document.getElementById(`fileCustomizeTypleEls`) ? document.getElementById(`fileCustomizeTypleEls`) :null;
        if(valj_efb[indx].file=='customize'){
          document.getElementById(`fileCustomizeTypleEls`).classList.remove('d-none');
          document.getElementById(`fileCustomizeTypleEls`).classList.add('d-block');
          c= document.getElementById(`fileCustomizeTypleEl`).value;
          valj_efb[indx].hasOwnProperty('file_ctype')==false ? Object.assign(valj_efb[indx],{'file_ctype':c}) : valj_efb[indx].file_ctype = c;
          nfile = c.toLowerCase();
        }else if(elc!=null){
          document.getElementById(`fileCustomizeTypleEls`).classList.remove('d-block');
          document.getElementById(`fileCustomizeTypleEls`).classList.add('d-none');
        }

        if (document.getElementById(`${valj_efb[indx].id_}_txt`)) document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${nfile}`
        break;
      case 'fileSizeMaxEl':
        valj_efb[indx].hasOwnProperty('max_fsize')==false ? Object.assign(valj_efb[indx],{'max_fsize':el.value}) : valj_efb[indx].max_fsize = el.value;       
        
        break;
      case'fileCustomizeTypleEl':
        c= el.value.trim();
        if(c.slice(-1)==',') c=c.slice(0,-1);
        for(let v of c.split(',')){
          
          
          v=v.trim();
          if(v.match(/^[a-zA-Z0-9]+$/)==null){
            alert_message_efb(efb_var.text.error,efb_var.text.editField +'>'+efb_var.text.file_cstm +'</br>'+ efb_var.text.pleaseEnterVaildValue + ` (${v})`, 15, "danger");            
            return false;
          }
        }
        
        //check last chracters is comma remove it
        //console.error(c);
        valj_efb[indx].hasOwnProperty('file_ctype')==false ? Object.assign(valj_efb[indx],{'file_ctype':c}) : valj_efb[indx].file_ctype = c;

        if (document.getElementById(`${valj_efb[indx].id_}_txt`)) document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${c}`
        
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
         else if (el.dataset.el == "clrdoneMessageEfb") {
          valj_efb[0].clrdoneMessageEfb = "text-" + c;
          return;
         // postId = '_'
        }
         else if (el.dataset.el == "clrdoneTitleEfb") {
          valj_efb[0].clrdoneTitleEfb = "text-" + c;
          return;
          //postId = '_'
        }
         else if (el.dataset.el == "clrdoniconEfb") {
          valj_efb[0].clrdoniconEfb = "text-" + c;
          return;
          //postId = '_'
        }
         else if (el.dataset.el == "progessbar") {
          valj_efb[0].hasOwnProperty('prg_bar_color')==false ?  Object.assign(valj_efb[0],{'prg_bar_color':"btn-" + c}) : valj_efb[0].prg_bar_color = "btn-" + c;
          //console.log(valj_efb[0].prg_bar_color)
          return;
          //postId = '_'
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
            || (el.dataset.tag == "cityList" && el.dataset.el != "el")
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
        } else if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList' || el.dataset.tag == 'cityList') {
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
      case "hrefEl":
        
        valj_efb[indx].href = el.value;

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

        if (c == "select" || c == 'stateProvince' || c == 'conturyList' || c == 'cityList' ) {
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
        c = document.getElementById('marksEl').value;
        c = parseInt(c);
        valj_efb[indx].mark = c;
        clss=  document.querySelector(`[data-id="${valj_efb[indx].id_}-contorller"]`);
        clss.classList.add('efb'); 
        c==0 ?  clss.classList.add('d-none')  : clss.classList.remove('d-none') ;
          //query data-id="${valj_efb[indx].id_}-control"

          clss.innerHTML= `
              <a  class="efb btn btn-sm btn-dark text-light"><i class=" fs-6   efb bi-crosshair"></i></a>
              <input type="text" id="efb-search-${valj_efb[indx].id_}" placeholder="${efb_var.text.eln}" class="efb p-1 border-d efb-square fs-6">
              <a   class="efb btn btn-sm btn-secondary text-light">${efb_var.text.search}</a>
              <a   class="efb btn btn-sm btn-danger text-light">${efb_var.text.deletemarkers}</a>
              <div id="efb-error-message-${valj_efb[indx].id_}" class="error-message d-none"></div>`
        
        //not clickable clss
      
        
        break;
      case 'letEl':
        const lat = parseFloat(el.value);
        const lon = parseFloat(document.getElementById('lonEl').value)
        c = Number(valj_efb[indx].zoom)

        valj_efb[indx].lat = lat;
        postId = document.querySelector(`[data-id="${valj_efb[indx].id_}-mapsdiv"]`);        
        efbLatLonLocation(postId.dataset.leaflet,lat,lon,c);
        break;
      case 'lonEl':
        const lonLoc = parseFloat(el.value);
        const letLoc = parseFloat(document.getElementById('letEl').value)

          c = Number(valj_efb[indx].zoom)
        valj_efb[indx].lng = lonLoc;
        postId = document.querySelector(`[data-id="${valj_efb[indx].id_}-mapsdiv"]`);
        efbLatLonLocation(postId.dataset.leaflet,letLoc,lonLoc,c);
        

        break;
        case 'zoomMapEl':
          c = Number(el.value)
          valj_efb[indx].zoom = c;
          postId = document.querySelector(`[data-id="${valj_efb[indx].id_}-mapsdiv"]`);
          efbLatLonLocation(postId.dataset.leaflet,valj_efb[indx].lat,valj_efb[indx].lng,c);
          break;
      case 'EditOption':
          //console.log('EditOption',el.dataset)
        const iindx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
        
        if (iindx != -1) {
          
          valj_efb[iindx].value = el.value;
          if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList' ||  el.dataset.tag == 'cityList' ) {

            //Select
            
            let vl = document.querySelector(`[data-op="${el.dataset.id}"]`);
            if (vl) vl.innerHTML = el.value;
            if (vl) vl.value = el.value;
            c =vl.dataset.id;
            temp = valj_efb.findIndex(x=>x.id_op == c);
            
            valj_efb[temp].value = el.value;
            //console.log(c,temp,valj_efb[temp].value)

          }else if(el.dataset.tag == "imgRadio"){
            
            document.getElementById(`${valj_efb[iindx].id_op}_value`).innerHTML = el.value;
          }else if(el.dataset.tag == "table_matrix"){
            //console.log(valj_efb[iindx].id_op ,el.value ,document.getElementById(`${valj_efb[iindx].id_op}_label`));
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
          } else if (el.dataset.tag != "multiselect" && el.dataset.tag != 'payMultiselect') {
            //radio || checkbox       
             document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;   
            //document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
            //document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = fun_get_links_from_string_Efb(el.value,true);
          }
          el.setAttribute('value', valj_efb[iindx].value);
          el.setAttribute('defaultValue', valj_efb[iindx].value);
        }
        break;
      case 'ElvalueOptions':
        
        clss =el.dataset.parent
        c = valj_efb.findIndex(x=>x.id_==clss)     
        indx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
        //console.log(`indx[${indx}]`) ;
       
        color = valj_efb[c].type.toLowerCase();
        const oi = valj_efb[c].value
        //console.log(`type change [${color}]`,color.includes("stateProvince"));
        if(color.includes("radio")==true || (color.includes("select")==true &&  color.includes("multi")==false) 
        || color.includes("conturylist")==true || color.includes("stateprovince")==true ){
          // c = valj_efb.findIndex(x=>x.id_==clss) 
          
          valj_efb[c].value =valj_efb[indx].id_

          //console.error(`selected [${valj_efb[indx].id_}]`)
          //console.log(c ,valj_efb[c].value) ;
          if(oi.length>0 && color.includes("radio")==true){
            
            //document.getElementById(oi+'-g').removeAttribute("checked");
            document.querySelector(`[data-id="${oi}"]`).removeAttribute("checked");
            //console.log(document.querySelector(`[data-id="${oi}"]`))
            document.getElementById(oi).removeAttribute("checked");
          }
               
             clss =valj_efb[indx].id_;
             el.setAttribute("checked",true)
         if(color.includes("radio")==true) {document.getElementById(clss).setAttribute("checked",true);}
        /*  c = valj_efb.findIndex(x=>x.id_==clss) 
         valj_efb[c].value =valj_efb[indx].id_ */
        }else{
          clss = valj_efb[indx].id_;
          if(el.checked==false){
            //console.log(`el =====================> `,el)
            el.removeAttribute("checked")
            if(color.includes("checkbox")==true) {
              document.getElementById(clss).removeAttribute("checked");
              el.removeAttribute("checked");
              //document.getElementById(clss).setAttribute("checked",false);
            }
            clss= valj_efb[c].value.findIndex(x=>x ==clss)
            valj_efb[c].value.splice(clss,1);
            
          }else{
           
           typeof valj_efb[c].value=="string" ? valj_efb[c].value =[clss] : valj_efb[c].value.push(clss);
           
           el.setAttribute("checked",true)
           if(color.includes("checkbox")==true) document.getElementById(clss).setAttribute("checked",true);
          }
        }
      break;
      case 'ElIdOptions':
        if(el.value.length<3){
          alert_message_efb(efb_var.text.error ,efb_var.text.idl5, 25 ,'danger' )
          break;
        }
         c = valj_efb.findIndex(x=>x.id == el.value)
         if(c==-1){
          c = valj_efb.findIndex(x=>x.id_ == el.value)
         
         }

         if(c!=-1){
          el.value="";
          clss = `<div class="efb"> ${efb_var.text.idmu} <br> <b>[${valj_efb[c].value}]<b> </div>`
          alert_message_efb(efb_var.text.error ,clss, 30 ,'danger' )
          break;
         }
        
         c= valj_efb.findIndex(x => x.id_op == el.dataset.id);
         
        if (c != -1) {
          el.value =  el.value.replace(/[^a-zA-Z0-9_-]/g, '')
          el.setAttribute('value', el.value);
          valj_efb[c].hasOwnProperty("id")  ? valj_efb[c].id= el.value : Object.assign(valj_efb[c], { id: el.value })
          
        /*   if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList') {

            //Select
            let vl = document.querySelector(`[data-op="${el.dataset.id}"]`);
            if (vl) vl.innerHTML = el.value;
            if (vl) vl.value = el.value;
          } else if (el.dataset.tag != "multiselect" && el.dataset.tag != 'payMultiselect') {
            //radio || checkbox       
             document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;   
            //document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
          }
          el.setAttribute('value', valj_efb[iindx].value);
          el.setAttribute('defaultValue', valj_efb[iindx].value); */
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
            <div class="icon-container efb"><i class="efb   bi-gear-wide-connected text-success" id="efbSetting" ></i></div></button> ${efb_var.text.andAddingHtmlCode}
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
      case "countriesListEl":
        //console.log("12x countriesListEl",el.options[el.selectedIndex].value ,el)
        valj_efb[indx].country =  el.options[el.selectedIndex].value
        //console.log("countriesListEl",el.options[el.selectedIndex].value ,el)
        if(el.dataset.tag =="stateProvince" && document.getElementById('optionListefb')!=null){
          el.classList.add('is-loading');
          
          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
          
          fetch_json_from_url_efb(`https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/json/states/${valj_efb[indx].country.toLowerCase()}.json`);
          let  opetions;
          const newRndm = Math.random().toString(36).substr(2, 9);
          setTimeout(() => {
              
            if(temp_efb.s==false ||temp_efb=="null" ) {
              alert_message_efb(efb_var.text.error, temp_efb.r ,15 , "danger")
              return;
            }
           
            obj_delete_options(valj_efb[indx].id_);
            //console.error(valj_efb[indx]);
            for (const key in temp_efb.r) {
              const value = temp_efb.r[key];
              const nValue = value.n.trim();
              const lValue =  value.l.length>1 && value.l.trim()!=nValue  ?`${value.l.trim()} (${nValue})`  : nValue;
              const sValue = value.s +newRndm;
              let rowValue =  value.l.length>1 && value.l.trim()!=nValue  ?`${value.l.trim()} (${nValue})`  : nValue;
              if(valj_efb[indx].hasOwnProperty('stylish')){
                if(Number(valj_efb[indx].stylish)==2){
                  rowValue = value.l.trim();
                }else if(Number(valj_efb[indx].stylish)==3){
                  rowValue = value.n.trim();
                }
                
              }
              valj_efb.push({ id_: sValue.replaceAll(' ','_'), dataId: `${sValue}-id`, parent:  valj_efb[indx].id_ , type: `option`, value: rowValue, id_op: nValue.replaceAll(' ','_'), step: step_el_efb, amount: valj_efb[indx].amount ,l:value.l,n:value.n ,s2:value.s});
              //optionElpush_efb(valj_efb[indx].id_, lValue, value.s, nValue.replaceAll('','_'));
              
            
            }
            const objOptions = valj_efb.filter(obj => {
              return obj.parent === valj_efb[indx].id_
            })
            
            opetions= efb_add_opt_setting(objOptions, el ,false ,newRndm ,"")
            el.classList.remove('is-loading');
            if(document.getElementById('optionListefb')) document.getElementById('optionListefb').innerHTML=opetions
          }, 4000);

          valj_efb[indx].country =  el.options[el.selectedIndex].value
        }else if(el.dataset.tag =="cityList" && document.getElementById('optionListefb')!=null){
          //document.getElementById('optionListefb').classList.add('is-loading');
          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
            callFetchStatesPovEfb('statePovListEl', valj_efb[indx].country, indx,'getStatesPovEfb');
            valj_efb[indx].country =  el.options[el.selectedIndex].value
        }
       
        break;
      case "statePovListEl":
        //console.log("12x statePovListEl",el.options[el.selectedIndex].value ,el)
        //console.log("countriesListEl",el.options[el.selectedIndex].value ,el)
        valj_efb[indx].statePov =  el.options[el.selectedIndex].value.toLowerCase();
        temp = valj_efb[indx].country.toLowerCase();
        if( document.getElementById('optionListefb')!=null){
          el.classList.add('is-loading');
          
          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
          fetch_json_from_url_efb(`https://cdn.jsdelivr.net/gh/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/json/cites/${temp}/${valj_efb[indx].statePov}.json`);
          let  opetions;
          const newRndm = Math.random().toString(36).substr(2, 9);

          setTimeout(() => {
              
            if(temp_efb.s==false ||temp_efb=="null" ) {
              alert_message_efb(efb_var.text.error, temp_efb.r ,15 , "danger")
              return;
            }
            obj_delete_options(valj_efb[indx].id_)
            //console.error(indx, valj_efb[indx] ,valj_efb[indx].hasOwnProperty('stylish'))
            for (const key in temp_efb.r) {
              
              const value = temp_efb.r[key];
              const nValue = value.n.trim();
              //const lValue =  value.l.length>1 && value.l.trim()!=nValue  ?`${value.l.trim()} (${nValue})`  : nValue;
              const sValue = nValue.replaceAll(' ','_') +newRndm;
              
              let rowValue =  value.l.length>1 && value.l.trim()!=nValue  ?`${value.l.trim()} (${nValue})`  : nValue;
              if(valj_efb[indx].hasOwnProperty('stylish')){
                if(Number(valj_efb[indx].stylish)==2){
                  rowValue = value.l.trim();
                }else if(Number(valj_efb[indx].stylish)==3){
                  rowValue = value.n.trim();
                }
                
              }
              valj_efb.push({ id_: sValue.replaceAll(' ','_'), dataId: `${sValue}-id`, parent:  valj_efb[indx].id_ , type: `option`, value: rowValue, id_op: nValue, step: step_el_efb, amount: valj_efb[indx].amount ,l:value.l,n:value.n,s2:value.s});
              //optionElpush_efb(valj_efb[indx].id_, lValue, sValue, nValue.replaceAll('','_'));
              
            
            }
            const objOptions = valj_efb.filter(obj => {
              return obj.parent === valj_efb[indx].id_
            })
            
            opetions= efb_add_opt_setting(objOptions, el ,false ,newRndm ,"")
            el.classList.remove('is-loading');
            if(document.getElementById('optionListefb')) document.getElementById('optionListefb').innerHTML=opetions
          }, 4000);
        }
        valj_efb[indx].statePov =  el.options[el.selectedIndex].value
        break;
      case 'imgRadio_url':
        indx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
        
        const ud = (url)=>{
          url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
          url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
          url = url.replace(/([/])+/g, '@efb@');
          return url;
          }
        valj_efb[indx].src = ud(el.value);
        //console.log(document.getElementById(valj_efb[indx].id_+'_img').src);
        document.getElementById(valj_efb[indx].id_+'_img').src = el.value;
        break;
      case 'imgRadio_sub_value':
        indx = valj_efb.findIndex(x => x.id_op == el.dataset.id);
        valj_efb[indx].sub_value = el.value;
        document.getElementById(valj_efb[indx].id_+'_value_sub').innerHTML = el.value;
        break;
      case 'selectSmartforOptionsEls':
        indx = valj_efb.findIndex(x=>x.id_ ==el.options[el.selectedIndex].value);
        

         if(indx!=-1){
          const no = el.options[el.selectedIndex].dataset.idset;
          const step = (el.options[el.selectedIndex].dataset.fid);
          const n = valj_efb[0].conditions.findIndex(x=>x.id_ ==step);
          //console.log(n,valj_efb[0].conditions[n])
          if(n!=-1) c= valj_efb[0].conditions[n].condition.findIndex(x=>x.no ==no);
          
          
          if (c!=-1){
            valj_efb[0].conditions[n].condition[c].one = sanitize_text_efb(el.options[el.selectedIndex].value);
            
            const fid =( el.options[el.selectedIndex].dataset.fid);
            const idset = (el.options[el.selectedIndex].dataset.idset);
            const s_op = sanitize_text_efb(el.options[el.selectedIndex].value);
            valj_efb[0].conditions[n].condition[c].two="";
            //idset ,fid , s_op
            document.querySelector(`[data-id='oso-${idset}'`).innerHTML= optionSmartforOptionsEls(fid , idset ,s_op);
          }
        }        
          break;
      case "optiontSmartforOptionsEls":
          c=-1;
          
          const step = (el.options[el.selectedIndex].dataset.idset);
          let no = (el.options[el.selectedIndex].dataset.fid);
          no = no;
          const n = valj_efb[0].conditions.findIndex(x=>x.id_ ==step);
          if(n!=-1) c= valj_efb[0].conditions[n].condition.findIndex(x=>x.no ==no);
          if(c!=-1)valj_efb[0].conditions[n].condition[c].two = sanitize_text_efb(el.options[el.selectedIndex].value);
          
        break;
      case 'smsContentEl':
         //check pro version
         if(pro_efb!=true){
           pro_show_efb(1);
           return;
         }
         
         c = sanitize_text_efb(el.value ,true);
         if(el.dataset.id=="WeRecivedUrM"){
          //console.log('WeRecivedUrM')
          valj_efb[0].hasOwnProperty('sms_msg_recived_usr') ? valj_efb[0].sms_msg_recived_usr = c : Object.assign(valj_efb[0], { sms_msg_recived_usr: c })
          
         }else if(el.dataset.id=="responsedMessage"){
          //console.log('responsedMessage')
          valj_efb[0].hasOwnProperty('sms_msg_responsed_noti') ? valj_efb[0].sms_msg_responsed_noti = c : Object.assign(valj_efb[0], { sms_msg_responsed_noti: c })
          
         }else if(el.dataset.id=="newMessageReceived"){
           //console.log('newMessageReceived')
            valj_efb[0].hasOwnProperty('sms_msg_new_noti') ? valj_efb[0].sms_msg_new_noti = c : Object.assign(valj_efb[0], { sms_msg_new_noti: c })
            
         }

      break;
      case 'languageSelectPresentEl':
         
         temp = el.options[el.selectedIndex].value;
         Object.assign(valj_efb[indx], { stylish: el.options[el.selectedIndex].value })
         c =valj_efb.filter(item => item.parent == valj_efb[indx].id_);
         
         const newRndm = Math.random().toString(36).substr(2, 9);
         for (const value of valj_efb) {
         
          if(value.parent == valj_efb[indx].id_){
            //console.log(value ,value.n ,value.l)
            let nameofErows = value.value;
            if(value.hasOwnProperty('n')){
              const eng = value.n.trim();
              const  notion = value.l.trim();
              nameofErows =  value.l.length>1 && eng!=notion  ?`${notion} (${eng})`  : notion;   
              if(Number(temp)==2){
                nameofErows =notion
               }else if(Number(temp)==3){
                nameofErows =eng
               }
            }
            value.value = nameofErows;
          }
           
           
          // optionElpush_efb(valj_efb[indx].id_, nameofErows, sValue, nValue.replaceAll('','_'));
           
         
         }
            opetions= efb_add_opt_setting(c, el ,false ,newRndm ,"")
            
            document.getElementById('optionListefb').innerHTML="";
            document.getElementById('optionListefb').innerHTML=opetions
      break;
      case 'FormEmailSubjectEl':
        if(pro_efb!=true){
          pro_show_efb(1);
          valj_efb[0].email_sub ='';
          break;
        }
        c = sanitize_text_efb(el.value ,false);
         valj_efb[0].hasOwnProperty('email_sub') ? valj_efb[0].email_sub = c : Object.assign(valj_efb[0], { email_sub: c })
      break;
    }

  }, len_Valj * 6)

}

function wating_sort_complate_efb(t) {
  if (t > 500) t = 500
  const body = efbLoadingCard()
  show_modal_efb(body, efb_var.text.editField, 'bi-ui-checks mx-2', 'settingBox')
  const el = document.getElementById("settingModalEfb");
  state_modal_show_efb(1);
  setTimeout(() => { state_modal_show_efb(0) }, t)
}

get_list_name_selecting_field_efb=()=>{
  let r =[];
  for(let i in valj_efb){
    if(valj_efb[i].type=='multiselect') continue;
    if(fun_el_select_in_efb(valj_efb[i].type)==true || fun_el_check_radio_in_efb(valj_efb[i].type)==true){
     
      r.push({name:valj_efb[i].name, id_:valj_efb[i].id_});
    }
  }
  
  return r;
}
get_list_name_otions_field_efb=(i_op)=>{
  //i_op parent id  , if i_op ==0 first select;
  let r =[];
  if(i_op==0){
    for(let i in valj_efb){
      if(valj_efb[i].type=='multiselect') continue;
      if(fun_el_select_in_efb(valj_efb[i].type)==true || fun_el_check_radio_in_efb(valj_efb[i].type)==true){
       
       i_op= valj_efb[i].id_;
       break;
      }
    }
  }
  for(let i in valj_efb){
    if(valj_efb[i].parent==i_op){
     
      r.push({name:valj_efb[i].value, id_:valj_efb[i].id_});
    }
  }
  return r;
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
                ${efbLoadingCard()}
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


const saveFormEfb = async (stated) => {
 
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      let proState = true;
      let stepState = true;
      let body = ``;
      let btnText = ``;
      let btnFun = ``;
      let message = ``;
      let state = false;
      let title = efb_var.text.error;
      let icon = `bi-exclamation-triangle-fill`;
      let box = `error`;
      let btnIcon = `bi-question-lg`;
      let returnState = false;
      let returnn = false;
      let gateway = '';

      if (valj_efb[0].type == 'payment') {
        gateway = valj_efb.findIndex(x => x.type == "stripe");
        gateway = gateway == -1 ? valj_efb[0].gateway : gateway;
        if (gateway == 'persiaPay') {
          gateway = valj_efb[0].persiaPay;
        }
      }

      show_modal_efb("", efb_var.text.save, "bi-check2-circle", "saveLoadingBox");

      let timeout = 1000;
      check_show_box = () => {
        setTimeout(() => {
          if (returnState == false) {
            check_show_box();
            timeout = 500;
          } else {
            show_modal_efb(body, title, icon, box);
          }
        }, timeout);
      };

      try {
        if (valj_efb.length < 3) {
          btnText = efb_var.text.help;
          btnFun = `open_whiteStudio_efb('notInput')`;
          message = efb_var.text.youDoNotAddAnyInput;
          icon = "";
        } else {
          if (pro_efb == false) {
            proState = valj_efb.findIndex(x => x.pro == true) != -1 ? false : true;
          }
          for (let s = 1; s <= valj_efb[0].steps; s++) {
            const stp = valj_efb.findIndex(x => x.step == s && x.type != "step");
            if (stp == -1) {
              stepState = false;
              break;
            }
          }
        }

        if (valj_efb.length > 2 && proState == true && stepState == true && (((valj_efb[0].type == "payment" && gateway != -1) || (valj_efb[0].type == "persiaPay" && gateway != -1)) || valj_efb[0].type != "payment")) {
          title = efb_var.text.save;
          box = `saveBox`;
          icon = `bi-check2-circle`;
          state = true;
          let sav = JSON.stringify(valj_efb);
          sessionStorage.setItem('valj_efb', sav);
          sessionStorage.setItem("valueJson_ws_p", sav);
          formName_Efb = valj_efb[0].formName.length > 1 ? valj_efb[0].formName : formName_Efb;
          returnState = true;
          returnn =true;
         
          

          actionSendData_emsFormBuilder();
        } else if (proState == false) {
          btnText = efb_var.text.activateProVersion;
          btnFun = `open_whiteStudio_efb('pro')`;
          message = efb_var.text.youUseProElements;
          title = efb_var.text.proVersion;
          icon = 'bi-gem';
          btnIcon = icon;
          returnState = true;
         
        } else if (stepState == false) {
          btnText = efb_var.text.help;
          btnFun = `open_whiteStudio_efb('emptyStep')`;
          message = efb_var.text.itAppearedStepsEmpty;
          returnState = true;
        } else if (valj_efb[0].type == "payment" && gateway == -1) {
          btnText = efb_var.text.help;
          btnFun = `open_whiteStudio_efb('paymentform')`;
          message = efb_var.text.addPaymentGetway;
          icon = 'bi-exclamation-triangle';
          returnState = true;
        } else if (valj_efb[0].type == "persiaPay" && gateway == -1) {
          btnText = efb_var.text.help;
          btnFun = `open_whiteStudio_efb('persiaPay')`;
          message = efb_var.text.addPaymentGetway;
          icon = 'bi-exclamation-triangle';
          returnState = true;
        } else if ((valj_efb[0].type == "payment" || valj_efb[0].type == "persiaPay") && valj_efb[0].captcha == true) {
          btnText = efb_var.text.help;
          btnFun = `open_whiteStudio_efb('paymentform')`;
          message = efb_var.text.paymentNcaptcha;
          icon = 'bi-exclamation-triangle';
          returnState = true;
        }

        if (state == false) {
          btn = `<button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3" onClick ="${btnFun}">
            <i class="efb ${btnIcon} mx-2"></i> ${btnText} </button>`;
          body = `
            <div class="efb pro-version-efb-modal efb"></div>
            <h5 class="efb txt-center text-darkb fs-6">${message}</h5>
            <div class="efb text-center ">
              ${btn}
            </div>
          `;
          check_show_box();
         
        }
        if(( returnn==false && stated==0) ||  stated==1 ){
           state_modal_show_efb(1);
        } else if(returnn==true && stated==0){
           state_modal_show_efb(0);}
        resolve(returnn);
      } catch (error) {
        btnIcon = 'bi-bug';
        body = `
          <div class="efb pro-version-efb-modal efb"></div>
          <h5 class="efb txt-center text-darkb fs-6">${efb_var.text.pleaseReporProblem}</h5>
          <div class="efb text-center">
            <button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3" onClick ="fun_report_error('fun_saveFormEfb','${error}')">
              <i class="efb bi-megaphone mx-2"></i> ${efb_var.text.reportProblem} </button>
          </div>
        `;
        show_modal_efb(body, efb_var.text.error, btnIcon, 'error');
        
         state_modal_show_efb(1);
        reject(error);
      }
    }, 100);
  });
};//end function

let editFormEfb = () => {
  valueJson_ws_p = 0; // set ajax to edit mode
  let dropZoneEFB = document.getElementById('dropZoneEFB');
  dropZoneEFB.innerHTML = efbLoadingCard();
  if (sessionStorage.getItem('valj_efb')) { valj_efb = JSON.parse(sessionStorage.getItem('valj_efb')); } // test code => replace from value
  let p = calPLenEfb(valj_efb.length)
  const len = (valj_efb.length) * p || 10;


  setTimeout(() => {
    dropZoneEFB.innerHTML = "<!-- edit efb -->"
    for (let v in valj_efb) {
      
      try {
        if (valj_efb[v].type != "option" && valj_efb[v].type != 'r_matrix') {
          const type = valj_efb[v].type == "step" ? "steps" : valj_efb[v].type;
         
          let el = addNewElement(type, valj_efb[v].id_, true, false);
       
          dropZoneEFB.innerHTML += el;
          //console.log(valj_efb[v].type,'!!!!!!')   ;


       
          if (valj_efb[v].hasOwnProperty('type') &&  valj_efb[v].type != "form" && valj_efb[v].type != "step" && valj_efb[v].type != "html" && valj_efb[v].type != "register" && valj_efb[v].type != "login" && valj_efb[v].type != "subscribe" && valj_efb[v].type != "survey" && valj_efb[v].type != "payment" && valj_efb[v].type != "smartForm") {
            
            funSetPosElEfb(valj_efb[v].dataId, valj_efb[v].label_position)}

          if (type == 'maps') {
            setTimeout(() => {
              efbCreateMap(valj_efb[v].id_ ,valj_efb[v],false)
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
  // const newStep = step - 1;
 
  for (let v of valj_efb) {
    if (v.step == step) {
      v.step = step;
      if (v.dataId) {
        //document.querySelector(`[data-id="${v.dataId}"]`).dataset.step = step;

        if (document.getElementById(v.id_)) document.getElementById(v.id_).dataset.step = step;
      }
    }
  }

  fub_shwBtns_efb()
  if (valj_efb[0].steps == 1) fun_handle_buttons_efb(false);
}
let sampleElpush_efb = (rndm, elementId) => {
  //console.log(`sampleElpush_efb ===> rndm[${rndm}], elementId[${elementId}] amount_el_efb[${amount_el_efb}]`)
  
  const testb = valj_efb.length;
  amount_el_efb = amount_el_efb ?amount_el_efb: (valj_efb[testb-1].amount +1);
  step_el_efb = step_el_efb ? step_el_efb: valj_efb[0].steps ;
  const label_align = efb_var.rtl == 1 ? 'txt-right' : 'txt-left'
  const txt_color = elementId != "yesNo" ? pub_el_text_color_efb : pub_txt_button_color_efb
  p=()=>{const l =fields_efb.find(x=>x.id == elementId);  return l && l.hasOwnProperty('pro')? l.pro :0} ;
  let pro = p();
  
  let size = 100;
  
  let type = elementId;
  switch (elementId) {
    case "firstName":
    case "lastName":
      size = "50";
      type = "text";
      break;
    case "country":
      size = "50";
      type = "conturyList";
      break;
      case "statePro":
        size = "50";
        type = "stateProvince";
      break;
      case "city":
        size = "50";
        type = "cityList";
      break;

    default:
      size = 100;
      break;
  }
  
  if (elementId == "dadfile" || elementId == "switch" || elementId == "rating" || elementId == "esign" || elementId == "maps"
    || elementId == "html" || elementId == "stateProvince" || elementId == "conturyList" || elementId == "payMultiselect" || elementId == "cityList"
    || elementId == "paySelect" || elementId == "payRadio" || elementId == "payCheckbox" || elementId == "heading" || elementId == "link" || elementId == "stripe" || elementId == "persiaPay" || elementId == "trmCheckbox") { pro = true }

  if (elementId != "file" && elementId != "dadfile" && elementId != "html" && elementId != "steps" && elementId != "heading" && elementId != "link") {
    
    // console.log(`elementId[${elementId}] ,amount_el_efb[${amount_el_efb}]`)
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: type, placeholder: efb_var.text[elementId], value: '', size: size, message: "",
      id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,  label_text_size: 'fs-6',
      label_position: 'up', el_text_size: 'fs-6', label_text_color: pub_label_text_color_efb, el_border_color: 'border-d',
      el_text_color: txt_color, message_text_color: pub_message_text_color_efb, el_height: 'h-d-efb', label_align: label_align, message_align: 'justify-content-start',
      el_align: 'justify-content-start', pro: pro, icon_input: ''
    })

    if (elementId == "stripe") {
      Object.assign(valj_efb[0], { getway: 'stripe'});
      if(valj_efb[0].hasOwnProperty('currency')==false) Object.assign(valj_efb[0], { currency: 'usd' })
      if(valj_efb[0].hasOwnProperty('paymentmethod')==false) Object.assign(valj_efb[0], { paymentmethod: 'charge' })
      valj_efb[0].type = 'payment';
      form_type_emsFormBuilder = "payment";
      valj_efb[testb].el_text_color="text-white"
    }else if (elementId == "persiaPay") {
      Object.assign(valj_efb[0], { getway: 'persiaPay', currency: 'irr', paymentmethod: 'charge', persiaPay:'zarinPal' });
      valj_efb[0].type = 'payment';
      form_type_emsFormBuilder = "payment";
      valj_efb[testb].el_text_color ="text-white"
    }else if (elementId == "esign") {
      
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        icon: 'bi-save', icon_color: "text-white", button_single_text: efb_var.text.clear,
        button_color: pub_bg_button_color_efb
      })
      //icon: ''
    } else if (elementId == "yesNo") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { button_1_text: efb_var.text.yes, button_2_text: efb_var.text.no, button_color: pub_bg_button_color_efb })
    } else if (elementId == "maps") {
      Object.assign(valj_efb[(valj_efb.length) - 1], { lat: 49.24803870604257, lng: -123.10512829684463, mark: 1, zoom: 12 });
      setTimeout(() => {
        document.getElementById('maps').draggable = false;
        if (document.getElementById('maps_b')) document.getElementById('maps_b').classList.add('disabled')
      }, valj_efb.length * 5);
    } else if (elementId == "multiselect" || elementId == "payMultiselect") {
      // console.log(valj_efb.length)
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        maxSelect: 2,
        minSelect: 0
      })
    }else if (elementId == "chlCheckBox" || elementId == "chlRadio") {
      // console.log(valj_efb.length)
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
      id: `${step_el_efb}`, name: stepName, icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: "",
      label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb',
      el_text_color: 'text-dark', message_text_color: pub_message_text_color_efb, icon_color: pub_icon_color_efb, icon: 'bi-ui-checks-grid', visible: 1
    });

  }else if (elementId == "contury" || elementId == "statePro" ){
    
    
  } else {
    
    valj_efb.push({
      id_: rndm, dataId: `${rndm}-id`, type: elementId, placeholder: elementId, value: 'allformat', size: 100,
      message: "", id: '', classes: '', name: efb_var.text[elementId], required: 0, amount: amount_el_efb, step: step_el_efb,
       label_text_size: 'fs-6', message_text_size: 'fs-7', el_text_size: 'fs-6', file: 'allformat',
      label_text_color: pub_label_text_color_efb, label_position: 'up', el_text_color: 'text-dark', message_text_color: pub_message_text_color_efb, el_height: 'h-d-efb',
      label_align: label_align, message_align: 'justify-content-start', el_border_color: 'border-d',
      el_align: 'justify-content-start', pro: pro
    })
    if (elementId == "dadfile") {
      //console.log (valj_efb[(valj_efb.length) - 1])
      Object.assign(valj_efb[(valj_efb.length) - 1], { icon: 'bi-cloud-arrow-up-fill', icon_color:pub_icon_color_efb, button_color: pub_bg_button_color_efb })
      
    }else if(elementId == "file"){
      valj_efb[(valj_efb.length) - 1].value = 'zip';
      valj_efb[(valj_efb.length) - 1].file = 'zip';
    }

  }
  
}
let optionElpush_efb = (parent, value, rndm, op, tag) => {
  
  if (typeof tag == "undefined" || (typeof tag=="string" && tag.includes("pay")==false) || tag.includes("img")==true ) {
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });

    const u = (url)=>{
      url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
      url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
      url = url.replace(/([/])+/g, '@efb@');
      return url;
     }
    if(typeof tag != "undefined"  && tag.includes("img")==true){
      Object.assign(valj_efb[(valj_efb.length) - 1], {
        sub_value: efb_var.text.sampleDescription,
        src:u(efb_var.images.head)
      })
    }
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
  
  //console.log('====================>add_new_option_efb')
  
  let p = document.getElementById("optionListefb")
  let p_prime = p.cloneNode(true)
  const ftyp = tag.includes("pay") ? 'payment' : '';
  const s=tag.includes("pay");
  let l_b = mobile_view_efb ? 'd-block' : 'd-none';
  let parent = valj_efb.find(x=>x.id_ == parentsID)
  let obd = valj_efb.find(x=>x.id_ == id_ob)
  let id = obd.hasOwnProperty("id") ? obd.id : obd.id_;
  let t = "radio";
  if(parent.type.toLowerCase().indexOf("multi")>-1  || parent.type.toLowerCase().includes("checkbox")==true || parent.type.toLowerCase().includes("multiselect")==true  ) t="checkbox"
  const col = ftyp == "payment" || ftyp == "smart" ? 'col-md-7' : 'col-md-12'
  const fun_add = tag != 'r_matrix' ? `onClick="add_option_edit_pro_efb('${parentsID.trim()}','${tag.trim()}',${valj_efb.length})"` : `onClick="add_r_matrix_edit_pro_efb(${parentsID.trim()},${tag.trim()},${valj_efb.length})"`
/*   document.getElementById('optionListefb').innerHTML += `
  <div id="${id_ob}-v"  class="efb  col-md-12">
  <input type="text"  value='${value}' data-value="${value}" id="EditOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}"  class="efb  ${col} text-muted mb-1 fs-6 border-d efb-rounded elEdit">
  ${ftyp == "payment" ? `<input type="number" placeholder="$"  value='' data-value="${value}" id="paymentOption" data-parent="${parentsID}" data-id="${idin}" data-tag="${tag}-payment"  class="efb  col-md-3 text-muted mb-1 fs-6 border-d efb-rounded elEdit">` : ''}
  <div class="efb  ${ftyp == "payment" || ftyp == "smart" ? 'pay' : 'newop'} btn-edit-holder" id="deleteOption" data-parent_id="${parentsID}">
    <button type="button" id="deleteOption" onClick="delete_option_efb('${idin}')"  data-parent="${parentsID}" data-tag="${tag}"  data-id="${idin}-id"  class="efb  btn btn-edit btn-sm elEdit" data-bs-toggle="tooltip" title="${efb_var.text.delete}" > 
        <i class="efb  bi-x-lg text-danger"></i>
    </button>
   <button type="button" id="addOption" ${fun_add}  data-parent="${parentsID}" data-tag="${tag}" data-id="${idin}-id"   class="efb  btn btn-edit btn-sm elEdit " data-bs-toggle="tooltip"   title="${efb_var.text.add}" > 
        <i class="efb  bi-plus-circle  text-success"></i>
    </button>
  </div>
  </div>`; */
  document.getElementById('optionListefb').innerHTML +=add_option_edit_admin_efb(0,parentsID,t,idin,tag,id_ob,value,col,s,l_b,ftyp,id ,"");
 // if (tag !== "multiselect" && tag !== "payMultiselect") document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(idin, value, id_ob, tag, parentsID);
 const indx = valj_efb.findIndex(x => x.id_ == parentsID);
 if (tag == "radio" && valj_efb[indx].hasOwnProperty('addother') == true && valj_efb[indx].addother == true) {
   const els = valj_efb.filter(obj => { return obj.parent === parentsID });
   //console.log(document.getElementById(`${parentsID}_options`).innerHTML);
   document.getElementById(`${parentsID}_options`).innerHTML = '<!--efb.app-->';
   els.forEach(l => {
     document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(l.id_, l.value, l.id_, 'radio', l.parent);
   });
   //add_new_option_view_select("random"+valj_efb[indx].id_, efb_var.text.otherTxt, "random"+valj_efb[indx].id_, "radio", valj_efb[indx].id_);    
   document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select("random" + parentsID, efb_var.text.otherTxt, "random" + parentsID, 'radio', parentsID);
 } else if (tag == "table_matrix") {
   // /r_matrix_push_efb
   document.getElementById(`${parentsID}_options`).innerHTML += add_r_matrix_view_select(idin, value, id_ob, tag, parentsID);
 } else if(tag !== "multiselect" && tag !== "payMultiselect" &&  tag !== "imgRadio" || ( tag=="radio" &&  valj_efb[indx].hasOwnProperty('addother') == false )){

   document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(idin, value, id_ob, tag, parentsID);

 }else if(tag == "imgRadio"){
 
  document.getElementById(`${parentsID}_options`).innerHTML += add_new_imgRadio_efb(idin, value, id_ob, tag, parentsID);
 }
  for (let el of document.querySelectorAll(`.elEdit`)) {
    
    el.addEventListener("change", (e) => { change_el_edit_Efb(el); })
  }


}

const sort_obj_el_efb_ = () => {
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
          last_setp +=1;
          step = last_setp ;
          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;


        } else {

          valj_efb[indx].amount = amount;
          valj_efb[indx].step = step;

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
    /* if(tag!="imgRadio"){
      add_new_option_efb(parent, id_ob, efb_var.text.newOption, id_ob, tag);
    }else{
      const row = valj_efb[(valj_efb.length-1)];
      add_new_imgRadio_efb(id_ob,'urllink',row)
      
    } */
    
  }, len);

}


function show_delete_window_efb(idset,iVJ) {
  
  // این تابع المان را از صفحه پاک می کند
  let v = valj_efb[iVJ] && valj_efb[iVJ].hasOwnProperty('type') ?`<br> <b>${valj_efb[iVJ].type} > ${valj_efb[iVJ].name ?? valj_efb[iVJ].value }</b>` : ''
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${efb_var.text.areYouSureYouWantDeleteItem} ${v}</div></div>`
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
  if (foundIndex != -1 && is_step == true) {
    step = Number(valj_efb[foundIndex].step)-1 ;
   step_el_efb =step}
  
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
      //valj_efb[0].sendEmail = 0
    // const vnoti = valj_efb.finda(x => x.noti == 1);
     const vnoti = valj_efb.filter(obj => {
      return obj.noti == 1
    })
     
     let count =0;
     if (Object.keys(vnoti).length === 0){
      //console.log('vd',typeof(vnoti),Object.keys(vnoti).length);
      valj_efb[0].email_to = ''
      valj_efb[0].sendEmail =0
     }else{
      //console.log('vd',typeof(vnoti),Object.keys(vnoti).length ,vnoti);
       for(let i in vnoti){
        
        if(vnoti[i].hasOwnProperty('id_') && vnoti[i].id_!= valj_efb[foundIndex].id_ && Number(vnoti[i].noti)==1 )count+=1;
       }

     }
     valj_efb[0].sendEmail =count>0 ? 1 : 0;
     valj_efb[0].email_to = ''
     
    }

    valj_efb.splice(foundIndex, 1);
  }
  if (is_step == true) {
    for (let ob of valj_efb) {
      if (ob.step == step) ob.step = step ;

    }
  }
  obj_resort_row(step_el_efb);
}
const obj_delete_options = (parentId) => {
  /* while (valj_efb.findIndex(x => x.parent == parentId) != -1) {
    let indx = valj_efb.findIndex(x => x.parent == parentId);

    valj_efb.splice(indx, 1);
  } */
  valj_efb_ = valj_efb.filter(item => item.parent !== parentId);
  valj_efb = valj_efb_;
}
const obj_delete_the_option = (id) => {
  //Just Delete the option with ID
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.id_op == id) : -1;
  if (foundIndex != -1) valj_efb.splice(foundIndex, 1);
}

function show_duplicate_fun(id,fild_name) {
  //console.log(id);
 //console.log(fild_name);
  emsFormBuilder_duplicate(id,'input' ,fild_name)
  //از آبجکت خروجی بگیرد و بعد اینجا تولید کند

}


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
    
    for (let i of valj_efb) {

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
    for (let i of valj_efb) {
      if (i.type != "option" && i.type != "form")
        if (document.getElementById(i.id_)) document.getElementById(i.id_).classList.remove("drophere")
    }
    status_drag_start = false;
  }

  // sort_obj_efb();
}






const sort_obj_efb = () => {
  //console.log('=>>>sort_obj_efb');
  const len = valj_efb.length;
  
  let p = calPLenEfb(len)
  //let =valj_efb_
  setTimeout(() => {
   const  valj_efb_ = valj_efb.sort((a, b) => (Number(a.amount) > Number(b.amount)) ? 1 : ((Number(b.amount) > Number(a.amount)) ? -1 : 0))
     valj_efb= valj_efb_;
     
  }, ((len * p))
  );
  
}







const delete_option_efb = (id) => {
  //حذف آپشن ها مولتی سلکت و درایو
  document.getElementById(`${id}-gs`).remove();
  if (document.getElementById(`${id}-v`)) document.getElementById(`${id}-v`).remove();
  const indx = valj_efb.findIndex(x => x.id_op == id)
  let ip = valj_efb.findIndex(x => x.id_ == valj_efb[indx].parent)
  
  if (indx != -1) {
  if (ip!=-1 && typeof valj_efb[ip].value =="string"){
    //console.log(valj_efb[ip].value ,  valj_efb[indx].id_)
     if(valj_efb[ip].value == valj_efb[indx].id_)valj_efb[ip].value="";
  }else if(ip!=-1){
    //console.log(valj_efb[ip].value, valj_efb[indx].id_)
    const ix = valj_efb[ip].value.findIndex(x=>x == valj_efb[indx].id_);
    
    if(ix!=-1) valj_efb[ip].value.splice(ix,1);
  }

   valj_efb.splice(indx, 1); }
}



fun_efb_add_el = (t) => {
  
  const rndm = Math.random().toString(36).substr(2, 9);

  

  if (t == "steps" && valj_efb.length < 2) { return; }
  if (valj_efb.length < 2) { dropZoneEFB.innerHTML = "", dropZoneEFB.classList.add('pb') }

  if (t == "address" || t == "name") {
    
    const olist = [
      { n: 'name', t: "firstName" }, { n: 'name', t: "lastName" },
      { n: 'address', t: "conturyList" }, { n: 'address', t: "stateProvince" } , { n: 'address', t: "cityList" }, { n: 'address', t: "address_line" }  ,{ n: 'address', t: "postalcode" } 
    
    ]
    //if(t=="address") olist = [{ n: 'address', t: "country" }, { n: 'address', t: "statePro" } , { n: 'address', t: "city" }  ]
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

     //find a row from valj_efb by id_
      const indx = valj_efb.findIndex(x => x.id_ == rndm);
      setTimeout(() => {
        efbCreateMap(rndm ,valj_efb[indx],false);
      }, 800);

  }
  setTimeout(() => {
    const vl = dropZoneEFB.lastElementChild;
    //console.log('last child',vl)
    active_element_efb(vl);
  }, 80);
}



function active_element_efb(el) {
  
  
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
  emsFormBuilder_delete(val,'addon','');
}

fun_confirm_remove_addon_emsFormBuilder=(val)=>{
   actionSendAddonsUn_efb(val);
 }

function emsFormBuilder_delete(id, type,value) {
  //console.log(type);
  get_val=(f,val)=>{
    let r ='null' ;
    val.forEach(element => {
      if(element.hasOwnProperty('checked') && element.checked==true){
        //console.log(r.length ,r);
        r!='null' ? r+='>'+element.track+'</br>' : r='>'+element.track+'</br>';
      }
    });
   return r;
  }
  //v2
  let val =id;
  
  
  switch (type) {
    case "addon":
      val = efb_var.text[id];
      break;
    case "form":
      val=value;
      break;
    case "message":
      val=value;
      if (typeof value == "object") {
        // console.log('message list');
        // console.log(val);
        val = get_val('message',value);
        type = 'messagelist';
        // console.log(type);
        for(let i in value){
          if(value[i].hasOwnProperty('checked') && value[i].checked==true && value[i].hasOwnProperty('content')){
            //remove content attrebute
            value[i].content='';
          }
        }
      }
      break;
    case 'condlogic':
      val =id;
      break;
  }
  const m = efb_var.text[type] ? `${efb_var.text[type]}  >>`: '';
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${efb_var.text.areYouSureYouWantDeleteItem}<br><b>${m} ${val} </b></div></div>`
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  //myModal.show_efb();
  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    // console.log(type);
    if(type=='form'){
    fun_confirm_remove_emsFormBuilder(Number(id))
    }else if(type=='message'){
      fun_confirm_remove_message_emsFormBuilder(Number(id))
    }else if (type =='addon'){
      addons_btn_state_efb(id);
      fun_confirm_remove_addon_emsFormBuilder(id);
    }else if (type =="condlogic"){
      
      fun_remove_condition_efb(id , value);
    }else if(type=="messagelist"){
      // console.log(type);
      //+here    
      
      fun_confirm_remove_all_message_emsFormBuilder(value)
      return;
    }
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })
  //myModal.show_efb();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function emsFormBuilder_duplicate(id, type,value) {
  
  //v2
  let val =id;
  
  
  switch (type) {
    case "input":
      val = value;
      break;
    case "form":
      val= value;
      break;
    case "message":
      val= value;
      break;
    case 'condlogic':
      val = id;
      break;
  }
  // console.log(val);
  const msg = efb_var.text.ausdup.replaceAll('XXX',val);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${msg}</div></div>`
  show_modal_efb(body, efb_var.text.duplicate, 'efb bi-clipboard-plus mx-2', 'duplicateBox')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  //myModal.show_efb();
  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    fun_confirm_dup_emsFormBuilder(id,type)
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })
    
  //myModal.show_efb();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

fun_remove_condition_efb = (no , step_id)=>{
  
  document.getElementById(no+"-logics-gs").remove();
 const  step_no = valj_efb[0].conditions.findIndex(x=>x.id_ == step_id);
 //console.log(step_no)
 if(step_no!=-1){
  const no_no = valj_efb[0].conditions[step_no].condition.findIndex(x=>x.no ==no );
  
   if (no_no!=-1){
    if(valj_efb[0].conditions[step_no].condition.length==1){
      valj_efb[0].conditions[step_no].condition[no_no].one ="";
      valj_efb[0].conditions[step_no].condition[no_no].two ="";
    }else{
      valj_efb[0].conditions[step_no].condition.splice(no_no ,1)}
    }
     
 }
}

addons_btn_state_efb=(id)=>{

    for (const el of document.querySelectorAll(".addons")) {
      el.classList.add('disabled')
    }
    document.getElementById(id).innerHTML = `<i class="efb bi-hourglass-split mx-1"></i>`



}

funRefreshPricesEfb=()=>{
  for (const l of document.querySelectorAll(".efb-crrncy")) {
    const id = l.id.replace("-price", "");
    v = valj_efb.find(x => x.id_ == id);
    l.innerHTML = Number(v.price ).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
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

    var val = efbLoadingCard();
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

fun_create_content_nloading_efb = () => {
  let txt = efb_var.text.alns.replaceAll('%s1', `<b>${efb_var.text.easyFormBuilder}</b>`).replaceAll('%s2', `<a href="https://whitestudio.team/contact-us" target="_blank">`).replaceAll('%s3', `</a>`);
  return txt;
}

document.addEventListener('DOMContentLoaded', function() {
  const els = document.getElementById('wpbody-content');
  if(!document.getElementById('alert_efb')){
    const currentUrl = window.location.href;
    const txt = fun_create_content_nloading_efb();
    els.innerHTML='<div class="efb m-5">'+alarm_emsFormBuilder(txt) +'</div>';
    
    report_problem_efb('AdminPagesNotLoaded' ,currentUrl);
    return;
  }
  for (let i = 0; i < els.children.length; i++) {
    if(els.children[i].id=='body_emsFormBuilder' ||els.children[i].id=='sideMenuFEfb'  || els.children[i].id=='tab_container_efb') break;
    if (els.children[i].tagName != 'SCRIPT' && els.children[i].tagName != 'STYLE' && ( els.children[i].id.toLowerCase().indexOf('efb') == -1 && els.children[i].id.indexOf('_emsFormBuilder') == -1)) {
      document.getElementById('wpbody-content').children[i].remove()
    }
    //check if the element have updated wpb-notice class
    if(els.children[i]!=undefined && (els.children[i].hasAttribute('class') && els.children[i].classList.contains('wpb-notice') || els.children[i].classList.contains('updated'))){
      document.getElementById('wpbody-content').children[i].remove()
    }
    
    //setInterval(heartbeat_Emsfb, 100000);
   
  }
  //remove all el included updated 

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
  r= el.id=="hiddenEl" ||  el.id=="disabledEl" ? efb_check_el_pro(el) :true;
  
  if(r==true) change_el_edit_Efb(el) ;
  
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
    case 'sms':
      add_sms_emsFormBuilder();
    break;
    case 'panel':
      document.getElementById('sideBoxEfb').classList.remove('show');
      fun_emsFormBuilder_render_view(25);
      fun_hande_active_page_emsFormBuilder(1);
    break;
    case 'setting':
      fun_show_setting__emsFormBuilder();      
      fun_backButton_efb(0);
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
      //if (v==null) console.error('get[id] not found!');
      g_page = sanitize_text_efb(getUrlparams.get('form_type'));
      
      efb_var.msg_id =v;
      form_type_emsFormBuilder = g_page;
      // history.pushState("show-message",null,`page=Emsfb&state=show-messages&id=${id}&form_type=${row.form_type}`);
      fun_get_messages_by_id(Number(v));
      /* setTimeout(() => {
        emsFormBuilder_waiting_response();
        fun_backButton_efb(0);
      }, 20); */
      
      fun_hande_active_page_emsFormBuilder(1);
    break;
    case "edit-form":
      //console.log('edit-form')
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;
      //if (v==null) console.error('get[id] not found!');
      
      fun_get_form_by_id(Number(v));
      fun_backButton_efb();
      fun_hande_active_page_emsFormBuilder(1);
    break;

  }
})


function efb_check_el_pro(el){
  //console.log(efb_var.pro , pro_ws)
  f_b=()=>{
    el.classList.contains('active') ? el.classList.remove('active') :  el.classList.add('active');
  }
  if(efb_var.pro==false || efb_var.pro=="false" || efb_var.pro=="") {
    if(el.type=="button" && el.classList.contains('setting')==false){
      f_b();
      pro_show_efb(efb_var.text.youUseProElements)  
    }else if(el.type=="button" && el.classList.contains('setting')==true){
      f_b();
      pro_show_efb(efb_var.text.proUnlockMsg)  
    }
    return false ;
  }

  if(el.id=="scaptcha_emsFormBuilder"){
    if (document.getElementById('sitekey_emsFormBuilder').value.length <5) { 
      f_b();
      alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.reCAPTCHASetError, 20, "danger");
    }
  }
  return true;
}

function colors_template_picker_efb(el){
  //console.log("test_efb")
  
  //create for valj_efb and switch label , icon , button ,
  const t = `colorDEfb-${el.dataset.color.slice(1)}`
  const c = el.dataset.color;
  let type = "text"
  let r =""
  Object.assign(valj_efb[0],{customize_color:1});
  switch(el.dataset.id){
    case 'label':
      type = "text"
       r=  efb_add_costum_color(t, c ,"" , type)
       pub_el_text_color_efb = r;
       pub_label_text_color_efb  = r;
    break;
    case 'description':
      type = "text"
      pub_message_text_color_efb=  efb_add_costum_color(t, c ,"" , type)
      break;
    case 'icon':
      type = "text"
      r=  efb_add_costum_color(t, c ,"" , type)
      pub_icon_color_efb = r;
      break;
    case 'btntc':
      //رنگ متن روی دکمه و آیکون روی دکمه
      // ویژگی ها باتن
      type = "text";
      r=  efb_add_costum_color(t, c ,"" , type)
      pub_txt_button_color_efb=r;
      break;
    case 'button':
    case 'buttonColor':
      type = "btn"
      pub_bg_button_color_efb=  efb_add_costum_color(t, c ,"" , type)
        // در همه ردیف ها ویژگی باتن تغییر کند
        // مقدار رنگ در یک متغییر محلی یا مرورگر ذخیره شود
        // از ساختار انتخاب گر رنگ استفاده شود برای اضافه کردن ویژگی جدید به آن اتربیوت
        break;
      }
      
     // if(valj_efb.length<2) return;

      for(let i in valj_efb){
        
        const row = valj_efb[i];
        let type = valj_efb[i].hasOwnProperty('type') ? valj_efb[i].type :"";
        for(const [k , v] of Object.entries(row)){

          
          switch(k){
            case 'el_text_color':
            case 'label_text_color':             
              if(type =="form" || type=="yesNo" || type=="payment"){
                valj_efb[i][k]=pub_txt_button_color_efb;
              }else{
                valj_efb[i][k]=pub_el_text_color_efb 
              }
              //console.error(`${k}=============>${valj_efb[i][k]}` ,pub_el_text_color_efb);
              //console.error(valj_efb[i].type ,valj_efb[i][k]);
            
            break;
          /*   case 'label_text':
              //pub_label_text_color_efb
              valj_efb[i][k]=pub_label_text_color_efb;
            break */

            case 'message_text_color':
             // type = "text"
             valj_efb[i][k]=pub_message_text_color_efb;
              break;
            case 'icon_color':
              valj_efb[i][k]=pub_icon_color_efb;
              break;
            case 'btntc':
              //رنگ متن روی دکمه و آیکون روی دکمه
              // ویژگی ها باتن
            
             
              pub_txt_button_color_efb;
              break;
            case 'button_color':
              
              valj_efb[i][k]=pub_bg_button_color_efb
                // در همه ردیف ها ویژگی باتن تغییر کند
                // مقدار رنگ در یک متغییر محلی یا مرورگر ذخیره شود
                // از ساختار انتخاب گر رنگ استفاده شود برای اضافه کردن ویژگی جدید به آن اتربیوت
                break;
          }
        }

        editFormEfb();
     
        
      }
  //c #000e24
  //v = ""
  //type = text, border , bg , btn
  
/*   for(let i in valj_efb){
    
    switch(el.dataset.id){
      case 'label':
        // ویژگی ها باتن
        break;
      case 'description':
        // ویژگی ها باتن
        break;
      case 'icon':
        // ویژگی ها باتن
        break;
      case 'btntc':
        //رنگ متن روی دکمه و آیکون روی دکمه
        // ویژگی ها باتن
        break;
      case 'button':
      case 'buttonColor':
          // در همه ردیف ها ویژگی باتن تغییر کند
          // مقدار رنگ در یک متغییر محلی یا مرورگر ذخیره شود
          // از ساختار انتخاب گر رنگ استفاده شود برای اضافه کردن ویژگی جدید به آن اتربیوت
          break;
    }
  } */

  // در مرحله بعد در زمان ایجاد ردیف جدید در چک شود رنگ پیش فرض در درون متغییر محلی وجود دارد
  // یا نه اگر نبود از متد سابق و اگر بود رنگ متغییر محلی valj_efb
}
/* 
content_colors_setting_efb=()=>{
  return false;
  get_colors_el =(name)=>{
    let r =`<!--colors-->`
    let d = '';
    switch(name){
      case 'description':
        d = pub_message_text_color_efb.includes('text-colorDEfb-')==true ? "#"+pub_message_text_color_efb.slice(15) :'';
      break;
      case 'label':
        d = pub_label_text_color_efb.includes('text-colorDEfb-')==true ? "#"+pub_label_text_color_efb.slice(15) :'';
      break;
      case 'icon':
        d = pub_icon_color_efb.includes('text-colorDEfb-')==true ? "#"+pub_icon_color_efb.slice(15) :'';
      break;
      case 'buttonColor':
        d= pub_bg_button_color_efb.includes('text-colorDEfb-')==true ? "#"+pub_bg_button_color_efb.slice(15) :'';
      break;
      case 'btntc':
        d= pub_txt_button_color_efb.includes('text-colorDEfb-')==true ? "#"+pub_txt_button_color_efb.slice(15) :'';
      break;
      case 'form':
      break
    }
    
    for(let i of efb_var.colors){
      const c = d==i ? '#c60000' : '#ccc';
      r +=`<p id="${name}" data-id="${name}" title="${i}" class="efb coloritem col-1 m-1" data-color="${i}" style="background:${i};width: 25px;height: 25px;border-radius: 20%;cursor: pointer; border: 1.5px solid ${c};" onClick="colors_template_picker_efb(this)"></p>`;
    }
    //return `<div class="efb row col">${r}</div>`;
    return `<span class="efb ">
    <label for="selectColorEl" class="efb mt-3 bi-paint-bucket mx-1 mt-3 mb-1 efb fs-6">${efb_var.text[name+'s']}</label>
    <div class="efb row col mx-1" id="Listefb-150">
    ${r}
    </div></span>`;
  }
  

  const v= `<div class="efb my-3">     
  <hr>        
  <p class="efb text-darkb fs-7">${efb_var.text.efbmsgctm}</p>
  ${get_colors_el('description')}
  ${get_colors_el('label')}
  ${get_colors_el('icon')}
  ${get_colors_el('buttonColor')}
  ${get_colors_el('btntc')}
  </div><div class="efb  clearfix"></div>`
  return v;
} */
function open_setting_colors_efb(alert){
  
  jQuery(alert).alert('close')


if(document.getElementById('sideBoxEfb').classList.contains('show')){
  sideMenuEfb(0);
  //document.getElementById(`btnSetting-${activeEl_efb}`).classList.toggle('d-none');
  return};

state_view_efb=1;
  document.getElementById('sideMenuConEfb').innerHTML=efbLoadingCard();
  sideMenuEfb(1)


document.getElementById('sideMenuConEfb').innerHTML=body;
}

msg_colors_from_template = ()=>{
  get_colors =()=>{
    let r =`<!--colors-->`
    for(let i of efb_var.colors){
      r +=`<div class="efb coloritem col-1 m-1" data-color="${i}" style="background:${i};width: 30px;height: 30px;border-radius: 20%;cursor: pointer;" onClick="colors_template_picker_efb(this)"></div>`;
    }
    return `<div class="efb row col">${r}</div>`;
  }
  
  let colorsDiv 
  if(efb_var.hasOwnProperty('colors')){
    c= get_colors();
    div = `<div class="efb text-dark"> ${efb_var.text.wylpfucat} </div><a class="btn btn-darkb text-white efb w-100 mt-1" onclick="open_setting_colors_efb(this)">${efb_var.text.yes}</a>`
    alert_message_efb("",div ,35, 'info');
  }








 
}
add_new_logic_efb = (newId , step_id) =>{
  //add_new_logic_efb('${rndm_no}','${fid}')
  newId = Math.random().toString(36).substr(2, 9);
  const row = valj_efb[0].conditions.findIndex(x=>x.id_ == step_id);
  if (row==-1) return;
  valj_efb[0].conditions[row].condition.push({no:newId, term: 'is',one:"",two:""});
  
      const ones = selectSmartforOptionsEls(newId ,step_id);
      const twos = optionSmartforOptionsEls(newId,step_id , 0);
      const si = `<p class="efb mx-2 px-0  col-form-label fs-6 text-center">${efb_var.text.ise}</p>`
      const del_btn =`
      <button type="button" class="efb zindex-100  btn btn-delete btn-sm m-1 " onclick="emsFormBuilder_delete('${newId}','condlogic' ,'${step_id}')" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"><i class="efb  bi-trash"></i></button>
      `
  document.getElementById("list-logics").innerHTML += `
  <div class="efb mx-0 col-sm-12 row opt" id="${newId}-logics-gs">
    <div class="efb mx-0 px-0 col-md-4">  ${ones}</div>
    <div class="efb mx-0 px-0 col-md-2">  ${si}</div>
    <div class="efb mx-0 px-0 col-md-4">  ${twos}</div>
    <div class="efb mx-0 px-0 col-md-2">  ${del_btn}</div>
  </div>`

  
  for (let el of document.querySelectorAll(`.elEdit`)) {
    
    el.addEventListener("change", (e) => { change_el_edit_Efb(el);
    
     })

     if(el.id =="selectSmartforOptionsEls"){
       const row = valj_efb[0].conditions.findIndex(x=>x.id_==el.dataset.fid);
       const no =  valj_efb[0].conditions[row].condition.findIndex(x=>x.no == el.dataset.no)
       const id =  valj_efb[0].conditions[row].condition[no].one;
       
      if(id!=""){
        let v= valj_efb.findIndex(x=>x.id_==id);
        
        if(v!=-1){
           //console.log(valj_efb[v],sanitize_text_efb(valj_efb[v].name))
           v =sanitize_text_efb(valj_efb[v].name)
           //console.error(v ,el);
           const op = document.getElementById("opsso-"+id)
           op.seleced="selected"
           
           el.value = op.value;
          }
      }
      el.value
    }else if (el.id =="optiontSmartforOptionsEls"){
      
      const row = valj_efb[0].conditions.findIndex(x=>x.id_==el.dataset.fid);
      const no=  valj_efb[0].conditions[row].condition.findIndex(x=>x.no == el.dataset.no)
      const id =  valj_efb[0].conditions[row].condition[no].two;
      
      if(id!=""){
        let v= valj_efb.findIndex(x=>x.id_==id);
        
        if(v!=-1){
          v= sanitize_text_efb(valj_efb[v].value);     
          const op = document.getElementById("ocsso-"+id)
          op.seleced="selected"
          
          el.value = op.value;
          
        }
      }
    }
  }
}


function  fun_confirm_dup_emsFormBuilder(id,type) {

  if(type=="form"){
    fun_dup_request_server_efb(parseInt(id),type);
    
  }else if(type=="input"){
    
    //console.log( document.getElementById('dupElEFb-'+id))
    document.getElementById('dupElEFb-'+id).innerHTML=svg_loading_efb('text-light')
    let new_id = Math.random().toString(36).substr(2, 9);
    let index = valj_efb.findIndex(x => x.id_ == id);
    let new_el = {...valj_efb[index]};
    const amount = Number(new_el.amount);
    new_el.name = new_el.name + ' - ' + efb_var.text.copy;
    new_el.amount =amount + 1;
    new_el.id_ = new_id;
    new_el.dataId= new_id+'-id';
    //add after index  don't remove index
   
    
   // sessionStorage.setItem('valj_efb' , JSON.stringify(valj_efb));
    const el_options =[ 'select' ,'paySelect', 'radio' , 'checkbox' , 'multiselect' , 'payMultiselect',
     'table_matrix','cityList','city','stateProvince','statePro' , 'country' ,
      'conturyList' ,'imgRadio','chlRadio','chlCheckBox','payRadio','payCheckbox' ];
    
    //check type of new_el.type == el_options
    if(el_options.includes(new_el.type)){
      
      let index_ops = valj_efb.filter(x => x.parent == id);    
      //console.log(index_ops);
      let new_el_ops = index_ops.map(x => ({...x}));
      let len_ops = new_el_ops.length;
      //console.log(new_el_ops ,len_ops);
      new_el.amount = amount + 1;
      //console.log(new_el);
      
      for(let i in new_el_ops){
        const new_id_op = Math.random().toString(36).substr(2, 9);
        new_el_ops[i].parent = new_id;
        new_el_ops[i].id_ = new_id_op;
        new_el_ops[i].id_op = new_id_op;
        new_el_ops[i].amount = amount + 1;
        
        new_el_ops[i].dataId= new_id_op+'-id';
        //valj_efb.splice(index+1, 0, new_el_ops[i]);
      }
      //console.log('new code!');
      //console.log(new_el_ops);
      if(valj_efb.length<index+1){
        valj_efb.push(new_el);
        valj_efb.push(...new_el_ops);

      }else{
        valj_efb.splice(index+1, 0, new_el);
        valj_efb.push(...new_el_ops);
      }
      
      /* new_el_op.id_ = new_id_op;
      new_el_op.dataId= new_id_op+'-id';
      new_el_op.parent = new_id;
      //valj_efb.splice(index_op+1, 0, new_el_op); */
      //sort_obj_efb()
    }else{
      valj_efb.splice(index+1, 0, new_el);
    }
    //sort valj_efb by amount
    sort_obj_efb()
    sessionStorage.setItem('valj_efb' , JSON.stringify(valj_efb));
    //get duplicated for options like select/radio/checkbox/city/state/country/net /rate/star/NPS
    const len =valj_efb.length;
    let p = calPLenEfb(len)
    const td = len < 50 ? 200 : (len + Math.log(len)) * p
    setTimeout(() => {
      editFormEfb()
    }, td)
  }


}



colors_from_template = ()=>{
  get_colors =()=>{
    let r =`<!--colors-->`
    for(let i of efb_var.colors){
      r +=`<div class="efb coloritem col-1 m-1" data-color="${i}" style="background:${i};width: 30px;height: 30px;border-radius: 20%;cursor: pointer;" onClick="colors_template_picker_efb(this)"></div>`;
    }
    return `<div class="efb row col">${r}</div>`;
  }
  
  let colorsDiv 
  if(efb_var.hasOwnProperty('colors')){
    c= get_colors();
    div = `<div class="efb text-dark"> ${efb_var.text.wylpfucat} </div><a class="btn btn-darkb text-white efb w-100 mt-1" onclick="open_setting_colors_efb(this)">${efb_var.text.yes}</a>`
    alert_message_efb("",div ,35, 'info');
  }
 
}


function form_preview_efb(val) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  data = {};
  jQuery(function ($) {
      data = {
        action: "form_preview_efb",
        id: val,
        nonce: efb_var.nonce
      };

    $.post(ajaxurl, data, function (res) {
      //console.log(res)
      if (res.data.success == true) {
        //console.log(res.data)
         window.open(res.data.data, '_blank');
         sessionStorage.setItem('page_id_wp' , res.data.page_id);
      } else {
        alert_message_efb(efb_var.text.error, efb_var.text.errorMsg, 30, 'danger');
      }
    })
    return true;
  });

}


preview_form_new_efb = async ()=>{
  let form_id = sessionStorage.getItem('form_id') ??  form_ID_emsFormBuilder == 0 ?  null :`[EMS_Form_Builder id=${form_ID_emsFormBuilder}]`;
 
      if(form_id == null ){
        //show message about first save form      
        show_modal_efb(`<div class="text-center text-darkb efb"><div class=" fs-4 efb"></div><p class="fs-4 efb">${efb_var.text.prsm}</p></div>`,efb_var.text.warning, '', 'saveBox');
        state_modal_show_efb(1)
        return;
      }else{
        check = await saveFormEfb(0);
        if(check==false){
          return;
        }
        form_preview_efb(form_id);
      }

      
}


function efbLatLonLocation(efbMapId, lat, long ,zoom) {
  var efbErrorMessageDiv = document.getElementById(`efb-error-message-${efbMapId}`);
  efbErrorMessageDiv.innerHTML = '';

  if (lat !== null && long !== null) {
    // اگر مختصات مستقیم دریافت شده باشند
    var efbLatlng = [lat, long];
    maps_efb[efbMapId].map.setView(efbLatlng, zoom);
  } else {
    efbErrorMessageDiv.classList.remove('d-none');
    efbErrorMessageDiv.textContent = 'Latitude and Longitude are required';
  }
}


function heartbeat_Emsfb() {
  // Your code here
  data = {};
  console.log('Old nonce', efb_var.nonce);
  jQuery(function ($) {
    data = {
      action: "heartbeat_Emsfb",
      nonce: efb_var.nonce,
    };
    $.post(ajaxurl, data, function (res) {
      console.log(res)
      if (res.success == true) {
        
        efb_var.nonce = res.data.newNonce;
        console.log('new nonce', efb_var.nonce);
      } else {
        console.log(res.data);
      }
    })

  });
}


function report_problem_efb(state ,value){
  data = {};
  jQuery(function ($) {
    data = {
      action: "report_problem_Emsfb",
      nonce: efb_var.nonce,
      state: state,
      value: value
    };
    $.post(ajaxurl, data, function (res) {
      if (res.success == true) {
     
      } else {
        console.log(res.data);
      }
    })

  });
}

