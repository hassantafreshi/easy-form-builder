
let state_check_ws_p = 1;
let valueJson_ws_p = [];
let exportJson_ws = [];
let pro_ws_efb = false;
let form_ID_emsFormBuilder = 0;
let form_type_emsFormBuilder = 'form';
const efb_version = 4;
let wpbakery_emsFormBuilder =false;
let pro_price_efb =27;
let heartbeat_efb_active =false;
let state_page_efb='';
var _efb_nonce_ = (typeof efb_var !== 'undefined' && efb_var.nonce) ? efb_var.nonce : '';

if (typeof pro_efb === 'undefined') { var pro_efb = (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1)) ? true : false; }

if (sessionStorage.getItem("valueJson_ws_p")) sessionStorage.removeItem('valueJson_ws_p');
if(sessionStorage.getItem("formId_efb")) sessionStorage.removeItem('formId_efb');

function deepFreeze_efb_admin(obj) {
  if (typeof obj !== "object" || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
      if (typeof obj[key] === "object" && obj[key] !== null) {
          deepFreeze_efb_admin(obj[key]);
      }
  });
  return Object.freeze(obj);
}

jQuery(function () {
  const bodyElement = document.getElementsByTagName('body')[0];
  if (bodyElement) {
    mobile_view_efb = bodyElement.classList.contains("mobile") ? 1 : 0;
  } else {
    mobile_view_efb = window.innerWidth < 768 ? 1 : 0;
  }

  _efb_nonce_ = efb_var.nonce;
  efb_var= deepFreeze_efb_admin(efb_var);
  state_check_ws_p = Number(efb_var.check);
  setting_emsFormBuilder=efb_var.setting;
  pro_ws_efb = (efb_var.pro == '1' || efb_var.pro == true) ? true : false;
  if (typeof pro_whitestudio !== 'undefined') { pro_ws_efb = pro_whitestudio; } else { pro_ws_efb = false; }
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

  let count_show_efb_cache = localStorage.hasOwnProperty('efb_cache') ? Number(localStorage.getItem('efb_cache'))+1 : 0;
  const efb_cache_dismissed = localStorage.getItem('efb_cache_dismissed') === 'true';
  if(efb_var.hasOwnProperty('plugins') && efb_var.plugins.cache != 0 && !efb_cache_dismissed){

    if(efb_var.text.excefb.indexOf('%s')==-1){
      $val_noti = efb_var.text.excefb.replaceAll('XX', `<b>${efb_var.plugins.cache} </b>`);
    }else{
      $val_noti = efb_var.text.excefb.replaceAll('%s', `<b>${efb_var.plugins.cache} </b>`);
      $val_noti += `<br><a class="efb text-danger ec-efb" data-eventform="links" data-linkname="cachePlugin">${efb_var.text.clcdetls}</a>`
    }
    $val_noti += `<div class="efb d-flex gap-2 mt-2">`
      + `<button type="button" class="efb btn btn-sm" style="background:rgba(255,255,255,0.85);color:#333;border:none;border-radius:6px;padding:4px 12px;font-size:0.78rem;cursor:pointer;" onclick="localStorage.setItem('efb_cache',0);close_msg_efb(this.closest('.alert_item_efb')?.id);">${efb_var.text.rmndltr}</button>`
      + `<button type="button" class="efb btn btn-sm" style="background:rgba(0,0,0,0.2);color:#fff;border:none;border-radius:6px;padding:4px 12px;font-size:0.78rem;cursor:pointer;" onclick="localStorage.setItem('efb_cache_dismissed','true');close_msg_efb(this.closest('.alert_item_efb')?.id);">${efb_var.text.gotitdsmss}</button>`
      + `</div>`;
    alert_message_efb('' ,$val_noti,  120 ,'warning' )
    count_show_efb_cache = count_show_efb_cache + 1;
    localStorage.setItem('efb_cache',count_show_efb_cache);
  }

  setTimeout(() => {
    restore_auto_save_efb();
  }, 3000);

})

const wpfooter = document.getElementById('wpfooter');
if(wpfooter)wpfooter.remove();

(function(){
  function efbCheckFooterScroll(){
    var el = document.getElementById('wpfooter');
    if(!el) return;
    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    var windowHeight = window.innerHeight;
    var docHeight = Math.max(
      document.body.scrollHeight, document.documentElement.scrollHeight,
      document.body.offsetHeight, document.documentElement.offsetHeight
    );

    if(docHeight <= windowHeight || (scrollTop + windowHeight >= docHeight - 60)){
      el.classList.add('efb-footer-visible');
    } else {
      el.classList.remove('efb-footer-visible');
    }
  }
  window.addEventListener('scroll', efbCheckFooterScroll, {passive:true});
  window.addEventListener('resize', efbCheckFooterScroll, {passive:true});
  if (document.body) {
    var _efbFooterObserver = new MutationObserver(function(){ efbCheckFooterScroll(); });
    _efbFooterObserver.observe(document.body, {childList:true, subtree:true});
  }
  efbCheckFooterScroll();
})();

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
  return `<div class="efb m-0 p-0 ${color} d-flex justify-content-center"><svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-cloud-arrow-down" viewBox="0 0 16 16" style="width: 60%;">
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
      case 'SMSNoti':
        link += "s/send-sms-after-wordpress-form-submission/";
        break;
      case 'redirectPage':
        link += "s/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder";
      break;
      case 'AdnSPF':
        link += 's/how-to-setup-and-use-the-stripe-on-easy-form-builder/';
        break;
        case 'AdnOF':
          link += "s/offline-forms-addon/";

        break;
        case 'AdnADP':
          link += "s/how-to-install-islamic-date-in-easy-form-builder-plugin/";

        break;
      case 'wpbakery':
        link += 's/wpbakery-easy-form-builder-v34/';
        break;
      case 'AdnPPF':
        link = `https://${lan}whitestudio.team`;
        break;
      case 'AdnATC':
        break;
      case 'AdnSS':
      case 'smsconfig':
        link += "/settingup-sms-notifications-wordpress-easy-form-builder/";
        break;
      case 'AdnCPF':
       break;
      case 'AdnESZ':
       break;
      case 'AdnSE':
        link = 'https://whitestudio.team/addons';
        break;
      case 'wpsmss':
        link ='https://wordpress.org/plugins/wp-sms/';
        break;
      case 'file_size':
        link += "/guide-advanced-file-upload-forms-wordpress/"
        break;
      case 'support':
        link = `https://whitestudio.team/support/`;
        break;
      case 'EmailSpam':
        link += `s/send-email-using-smtp-plugin/`;
        break;
      case 'oslp':
        link += `/how-to-add-location-pickergeolocation-within-your-form/`;
        break;
      case 'cachePlugin':
        link += `/exclude-easy-form-builder-froms-cache/`;
        break;
      case 'translateWP':
        link = 'https://translate.wordpress.org/projects/wp-plugins/easy-form-builder/';
        break;
      case 'paypal':
        link += `s/how-to-setup-paypal-payment-in-easy-form-builder/`;
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
      case 'SMSNoti':
        link = "https://whitestudio.team/documents/send-sms-after-wordpress-form-submission/";
        break;
      case 'redirectPage':
        link += "نحوه-ساخت-یک-صفحه-تشکر-در-افزونه-فرم-ساز/";
      break;
      case 'AdnSPF':
        link = 'https://easyformbuilder.ir/documents/';
        break;
      case 'AdnOF':
        link += '%d9%81%d8%b9%d8%a7%d9%84-%da%a9%d8%b1%d8%af%d9%86-%d8%ad%d8%a7%d9%84%d8%aa-%d8%a2%d9%81%d9%84%d8%a7%db%8c%d9%86-%d9%81%d8%b1%d9%85/';
        break;
      case 'AdnPPF':
        link += "%da%86%da%af%d9%88%d9%86%d9%87-%d8%af%d8%b1%da%af%d8%a7%d9%87-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d8%a7%db%8c%d8%b1%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%a8%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2/";
        break;
        case 'wpbakery':
          link += '%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%db%8c%da%a9%d8%b1%db%8c-%d8%a7%d8%b3%d8%aa%d9%81/';
          break;
      case 'AdnATC':
      case 'AdnSS':
      case 'smsconfig':
        link +=`تنظیم-اطلاع-رسانی-پیامک-وردپرس-فرم-ساز/`;
         break;
      case 'AdnCPF':
      case 'AdnESZ':
      case 'AdnSE':
        link = 'https://easyformbuilder.ir/';
        break;
      case 'file_size':
        link += "/ایجاد-فرم-آپلود-فایل-پیشرفته-وردپرس";
        break;
      case 'support':
        link = `https://easyformbuilder.ir/support/`;
        break;
      case 'EmailSpam':
        link +=`ارسال-ایمیل-بوسیله-افزونه-smtp/`;
        break;
        case 'oslp':
        link += `چگونه-مکانیاب-انتخاب-گر-نقشه-فرم-افزون/`;
        break;
      case 'cachePlugin':
        link + `جلوگیری-از-کش-شدن-فرم-ساخته-توسط-فرم-ساز/`
        break;
      case 'paypal':
        link = `https://whitestudio.team/documents/how-to-setup-paypal-payment-in-easy-form-builder/`;
      break;
      case 'translateWP':
        link = 'https://translate.wordpress.org/projects/wp-plugins/easy-form-builder/';
        break;
    }
  }

  window.open(link, "_blank");
}

function show_message_result_form_set_EFB(state, m) {

  const cet = () => {
    const emailItem = valj_efb.find(item => item.type === 'email');
    return emailItem!=undefined && emailItem.hasOwnProperty('noti')  ? emailItem.noti  : false;
};
  const wpbakery= `<p class="efb m-5 mx-3 fs-4"><a class="efb text-danger ec-efb" data-eventform="links" data-linkname="wpbakery">${efb_var.text.wwpb}</a></p>`
  const title = `
  <h4 class="efb title-holder efb">
     <img src="${efb_var.images.title}" class="efb title efb">
     ${state != 0 ? `<i class="efb  bi-hand-thumbs-up title-icon mx-2"></i>${efb_var.text.done}` : `<i class="efb title-icon mx-2"></i>${efb_var.text.error}`}
  </h4>

  `;
  const e_s = cet();
  let e_m ='<div id="alert"></div>';
  if((efb_var.smtp==false || efb_var.smtp==0 || efb_var.smtp==-1) && (e_s==true || e_s==1)) {
    msg = `<br> <p>${efb_var.text.clickToCheckEmailServer }</p> <p>${efb_var.text.goToEFBAddEmailM }</p> <br>
    <a class="efb btn btn-sm efb btn-danger text-white btn-r d-block ec-efb" data-eventform="links" data-linkname="EmailNoti"><i class="efb bi bi-patch-question  mx-1"></i>${efb_var.text.howActivateAlertEmail}</a>
    `
    e_m = alarm_emsFormBuilder(msg)
  }
  let content = ``

  if (state != 0) {
    content = ` <h3 class="efb"><b>${efb_var.text.goodJob}</b></br> ${state == 1 ? efb_var.text.formIsBuild : efb_var.text.formUpdatedDone}</h3>
    ${wpbakery_emsFormBuilder ? wpbakery :''}
  <h5 class="efb mt-3 efb">${efb_var.text.shortcode}: <strong>${m}</strong></h5>
  <input type="text" class="efb hide-input efb" value="${m}" id="trackingCodeEfb">
  ${e_m}
  <a  class="efb btn-r btn efb btn-primary btn-lg m-3" onclick="copyCodeEfb('trackingCodeEfb','textTractingCode')">
      <i class="efb  bi-clipboard-check mx-1"></i><span id="textTractingCode">${efb_var.text.copyShortcode}</span>
  </a>
  <a  class="efb btn efb btn-outline-pink btn-lg m-3 px-3" data-bs-toggle="modal" data-bs-target="#Output" onclick="open_whiteStudio_efb('publishForm')">
      <i class="efb  bi-question mx-1"></i>${efb_var.text.help}
  </a>
  <a  class="efb btn efb btn-outline-pink btn-lg m-3 px-3" data-bs-toggle="modal" data-bs-target="#close" onclick="state_modal_show_efb(0)">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="mx-1"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>${efb_var.text.close}
  </a>
  `
  } else {
    content = `<h3 class="efb">${m}</h3>`
  }

  document.getElementById('settingModalEfb-body').innerHTML = `<div class="efb card-body text-center efb">${title}${content}</div>`;
}

async function  actionSendData_emsFormBuilder() {
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
  const ls_val=  sessionStorage.getItem('valj_efb').replace(/\\\"/g, '"');
  jQuery(function ($) {

    if (state_check_ws_p == 1) {

      data = {
        action: "add_form_Emsfb",
        value: ls_val,
        name: name,
        type: form_type_emsFormBuilder,
        nonce: _efb_nonce_
      };
    } else {

      data = {
        action: "update_form_Emsfb",
        value: ls_val,
        name: name,
        nonce: _efb_nonce_,
        id: form_ID_emsFormBuilder
      };
    }

    $.post(ajaxurl, data, function (res) {

      if (res.data.r == "insert") {
        if (res.data.value && res.data.success == true) {
          state_check_ws_p = 0;
          form_ID_emsFormBuilder = parseInt(res.data.id)

          show_message_result_form_set_EFB(1, res.data.value);
          localStorage.setItem('efb_auto_save', 0);
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
        nonce: _efb_nonce_
      };

    $.post(ajaxurl, data, function (res) {
      if (res.data.r == "done") {
        if (res.data.value && res.data.success == true) {
          let m = efb_var.text.tshbc;
          m =m.replace('%s', `<b>${efb_var.text.installation}</b>`);
          alert_message_efb(m,'', 40,'info');
          location.reload();
        } else {
          alert(res, "error")
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");

        }
      } else {
        if (res.data.m == null || res.data.m.length > 1) {

         alert_message_efb(efb_var.text.error, res.data.m, 30, "danger");
        } else {
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
        nonce: _efb_nonce_
      };

    $.post(ajaxurl, data, function (res) {
      if (res.data.r == "done") {
        if (res.data.value && res.data.success == true) {
          let m = efb_var.text.tDeleted;
          const ad = (efb_var.text.addon || '').replace('%s1', '').replace(/%\d+\$s/g, '').trim().toLowerCase();
          m =m.replace('%s', ad);
          alert_message_efb(m,'', 30,'success');
          location.reload();
        } else {
          alert(res, "error")
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");

        }
      } else {
        if (res.data.m == null || res.data.m.length > 1) {

         alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");
        } else {
          alert_message_efb(efb_var.text.error, `${efb_var.text.somethingWentWrongPleaseRefresh}, Code:400-1`, 30, "danger");
        }
      }
    })
    return true;
  });

}

function fun_report_error(fun, err) {

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
  const rtl = efb_var.rtl == 1 ? true: false;
  const payment_addon = efb_var.addons.hasOwnProperty('AdnSPF') && efb_var.addons.AdnSPF!=0 || efb_var.addons.hasOwnProperty('AdnPPF') && efb_var.addons.AdnPPF!=0 || efb_var.addons.hasOwnProperty('AdnPAP') && efb_var.addons.AdnPAP!=0 ? true : false;
  const m_space = rtl ? 'ms-1' : 'me-1';
  tag_efb =tag_efb.concat(i.tag.split(' ')).filter((item, i, ar) => ar.indexOf(item) === i);
  const package_type = setting_emsFormBuilder.package_type != undefined ? Number(setting_emsFormBuilder.package_type) : 0;
  let prw = `<a class="efb float-end btn mx-1 efb rounded-pill border-danger text-danger " onclick="fun_preview_before_efb('${i.id}' ,'local' ,${i.pro})"><i class="efb  bi-eye ${m_space}"></i>${efb_var.text.preview}</a>`;
  let btn = `<button type="button" id="${i.id}" class="efb float-end btn mb-1 efb btn-primary btn-lg float-end emsFormBuilder btn-r efbCreateNewForm"><i class="efb  bi-plus-circle ${m_space}"></i>${efb_var.text.create}</b></button>`;
  let btnPro = `<a class="efb float-end btn mb-1 efb btn-warning btn-lg float-end emsFormBuilder btn-r" onclick="pro_show_efb(3)"><i class="efb  bi-gem ${m_space}"></i>${efb_var.text.pro}</b></a>`;
  if (i.id == "form" || i.id == "payment") prw = "<!--not preview-->"
  if(i.tag.search("payment")!=-1 && ( !payment_addon ) ) {
    const fn = `alert_message_efb('${efb_var.text.error}', '${efb_var.text.IMAddonP}', 20 , 'danger')`
    btn = `<a class="efb float-end btn mb-1 efb btn-primary btn-lg float-end  btn-r" onclick="${fn}"><i class="efb  bi-plus-circle ${m_space}"></i>${efb_var.text.create}</b></a>`
  }
  return `
  <div class="efb tag  col ${rtl == 1 ? 'rtl-text' : ''} ${i.tag}" id="${i.id}"> <div class="efb card efb"><div class="efb card-body">
  ${i.pro == true && efb_var.pro != 1 ? funProEfb() : ''}
  <h5 class="efb card-title efb"><i class="efb ${m_space} ${i.icon} "></i>${i.title} </h5>
  <div class="efb row" ><p class="efb card-text efb ${mobile_view_efb ? '' : 'fs-7'} float-start my-3">${i.desc}  <b>${efb_var.text.freefeatureNotiEmail}</b> </p></div>
  ${(i.pro == true && Number(setting_emsFormBuilder.package_type) != 2) || i.pro==false ? btn : btnPro}
  ${prw}
  </div></div></div>`
}
createCardAddoneEfb = (i) => {

  tag_efb =tag_efb.concat(i.tag.split(' ')).filter((item, i, ar) => ar.indexOf(item) === i);;

  let funNtn =   `funBTNAddOnsEFB('${i.name}','${i.v_required}')`;
  let nameNtn = efb_var.text.install;
  let iconNtn = 'bi-download';
  let colorNtn = 'btn-primary';
  if (i.pro == true &&   Number(setting_emsFormBuilder.package_type) === 2) {
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
  <a class="efb float-end btn mx-1 efb rounded-pill border-danger text-danger ec-efb" onClick="Link_emsFormBuilder('${i.name}')" data-eventform="links" data-linkname="${i.name}"><i class="efb  bi-question-circle mx-1"></i>${efb_var.text.help}</a>
  <!-- 3.8.6 end -->
  </div></div></div>`
}
funProEfb=()=>{return `<div class="efb  pro-card"><a type="button" onclick='pro_show_efb(1)' class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="${efb_var.text.fieldAvailableInProversion}" data-original-title="${efb_var.text.fieldAvailableInProversion}"><i class="efb  bi-gem text-light"></i></a></div>`}
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
  { id: 'quoteForm', title: efb_var.text.quoteFormT, desc: efb_var.text.quoteFormD, status: true, icon: 'bi-receipt', tag: 'all contactUs', pro: false },
  { id: 'customOrderForm', title: efb_var.text.customOrderFormT, desc: efb_var.text.customOrderFormD, status: true, icon: 'bi-cart3', tag: 'all payment', pro: false },
  { id: 'jobApplicationForm', title: efb_var.text.jobApplicationFormT, desc: efb_var.text.jobApplicationFormD, status: true, icon: 'bi-person-badge', tag: 'all', pro: false },
  { id: 'rentCarForm', title: efb_var.text.rentCarFormT, desc: efb_var.text.rentCarFormD, status: true, icon: 'bi-car-front', tag: 'all', pro: false },
  { id: 'salonConsultationForm', title: efb_var.text.salonConsultationFormT, desc: efb_var.text.salonConsultationFormD, status: true, icon: 'bi-scissors', tag: 'all', pro: false },
  { id: 'graphicDesignOrderForm', title: efb_var.text.graphicDesignOrderFormT, desc: efb_var.text.graphicDesignOrderFormD, status: true, icon: 'bi-palette', tag: 'all', pro: false },
  { id: 'sampleCvForm', title: efb_var.text.sampleCvFormT, desc: efb_var.text.sampleCvFormD, status: true, icon: 'bi-file-earmark-person', tag: 'all', pro: false },
  { id: 'videographyBriefForm', title: efb_var.text.videographyBriefFormT, desc: efb_var.text.videographyBriefFormD, status: true, icon: 'bi-camera-video', tag: 'all', pro: false },
  { id: 'partyInviteForm', title: efb_var.text.partyInviteFormT, desc: efb_var.text.partyInviteFormD, status: true, icon: 'bi-envelope-open', tag: 'all', pro: false },
  { id: 'eventRegistrationForm', title: efb_var.text.eventRegistrationFormT, desc: efb_var.text.eventRegistrationFormD, status: true, icon: 'bi-calendar-event', tag: 'all', pro: false },
  { id: 'storeSurveyForm', title: efb_var.text.storeSurveyFormT, desc: efb_var.text.storeSurveyFormD, status: true, icon: 'bi-shop', tag: 'all survey', pro: false },
  { id: 'voterSurveyForm', title: efb_var.text.voterSurveyFormT, desc: efb_var.text.voterSurveyFormD, status: true, icon: 'bi-clipboard-data', tag: 'all survey', pro: false },
  { id: 'signupForm', title: efb_var.text.signupFormT, desc: efb_var.text.signupFormD, status: true, icon: 'bi-person-plus', tag: 'all payment', pro: true },
  { id: 'sportsLeagueForm', title: efb_var.text.sportsLeagueFormT, desc: efb_var.text.sportsLeagueFormD, status: true, icon: 'bi-trophy', tag: 'all', pro: false },
  { id: 'summerReadingForm', title: efb_var.text.summerReadingFormT, desc: efb_var.text.summerReadingFormD, status: true, icon: 'bi-book', tag: 'all', pro: false },
  { id: 'childrenLibraryCardForm', title: efb_var.text.childrenLibraryCardFormT, desc: efb_var.text.childrenLibraryCardFormD, status: true, icon: 'bi-journal-bookmark', tag: 'all', pro: false },
  { id: 'employeeSuggestionForm', title: efb_var.text.employeeSuggestionFormT, desc: efb_var.text.employeeSuggestionFormD, status: true, icon: 'bi-lightbulb', tag: 'all', pro: false },
  { id: 'bookClubForm', title: efb_var.text.bookClubFormT, desc: efb_var.text.bookClubFormD, status: true, icon: 'bi-book', tag: 'all', pro: false },
]
let tag_efb=[];
function add_dasboard_emsFormBuilder() {

  let value = `<!-- boxs -->`;
  for (let i of boxs_efb) {

    value += createCardFormEfb(i)
  }
  let cardtitles = `<!-- card titles -->`;
  for (let i of tag_efb) {
    cardtitles += `
    <li class="efb efb-col-3 col-lg-1 col-md-2 col-sm-2 col-sx-3 mb-2 py-2 m-1 p-0 text-center">
      <a class="efb nav-link m-0 p-0 cat fs-6  ${i} ${i=='all' ? 'active' :''}" aria-current="page" onclick="funUpdateLisetcardTitleEfb('${i}')" role="button">${efb_var.text[i]}</a>
    </li>
    `
  }

 cardtitles = `
    <ul class="efb mt-4 mb-3 p-0 justify-content-center row d-none d-md-flex" id="listCardTitleEfb">${cardtitles}
    <hr class="efb hr">
    </ul>
    `

  document.getElementById('tab_container_efb').innerHTML = `

          ${head_introduce_efb('create')}
          <section id="content-efb">
          ${!mobile_view_efb ? `<img src="${efb_var.images.title}" class="efb ${efb_var.rtl == 1 ? "right_circle-efb" : "left_circle-efb"}"><h4 class="efb title-holder efb fs-4"><img src="${efb_var.images.title}" class="efb title efb create"><i class="efb  bi-arrow-down-circle title-icon mx-1 fs-4"></i>${efb_var.text.forms}</h4>` : ''}
          <div class="efb d-flex justify-content-center ">
            <input type="text" placeholder="${efb_var.text.search}" id="findCardFormEFB" class="efb fs-6 search-form-control rounded-4 efb mx-2"> <a class="efb btn efb btn-outline-pink mx-1" onclick="FunfindCardFormEFB()" >${efb_var.text.search}</a>

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

  let value = `<!-- boxs -->`;
  for (let i of addons_efb) {
    let title = i.title;
    let desc = i.desc;
    if(title.trim().split(/\s+/).length === 1) {
      title =efb_var.text[title] ;
      desc =efb_var.text[desc];
    }

   if(i.state==true) {
      const v = {'name':i.name,'id':i.id,'tag':i.tag,'icon':i.icon,
                 'title':title,'desc':desc,'v_required':i.v_required , 'pro':i.pro}
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
  let cards = [];
  const v = document.getElementById('findCardFormEFB').value.toLowerCase();
  document.getElementById('listFormCardsEFB').innerHTML = ''
  for (let row of boxs_efb) {
    if (row["title"].toLowerCase().includes(v) == true || row["desc"].toLowerCase().includes(v) == true) { cards.push(row); }
  }
  let result = '<!--Search-->'
  for (let c of cards) { result += createCardFormEfb(c); }
  if (result == "'<!--Search-->'") result = "NotingFound";
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
  let cards = [];
  const v = document.getElementById('findCardFormEFB').value.toLowerCase();
  document.getElementById('listFormCardsEFB').innerHTML = ''

  for (let row of addons_efb) {

    if (row["title"].toLowerCase().includes(v) == true || row["desc"].toLowerCase().includes(v) == true) { cards.push(row); }
  }
  let result = '<!--Search-->'
  for (let c of cards) {result += createCardAddoneEfb(c); }
  if (result == "'<!--Search-->'") result = "NotingFound";
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

  state_page_efb = 'create';
  localStorage.setItem('efb_auto_save', 0);
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
    form_type_emsFormBuilder = "form";
    const json = [{ "type": "form", "steps": 1, "formName": efb_var.text.contactUs, "email":adminEmail, 'sendEmail': smail, "trackingCode": true, "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": "2jpzt59do", "show_icon": true, "show_pro_bar": true, "captcha": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.contactusForm, "icon": "bi-chat-right-fill", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-muted", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "uoghulv7f", "dataId": "uoghulv7f-id", "type": "text", "placeholder": efb_var.text.firstName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.firstName, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "xzdeosw2q", "dataId": "xzdeosw2q-id", "type": "text", "placeholder": efb_var.text.lastName, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.lastName, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "2jpzt59do", "dataId": "2jpzt59do-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 6, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , "noti":1},
    { "id_": "dvgl7nfn0", "dataId": "dvgl7nfn0-id", "type": "textarea", "placeholder": efb_var.text.enterYourMessage, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.message, "required": true, "amount": 7, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "contactTemplate") {
    form_type_emsFormBuilder = "form";
    const json = contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "multipleStepContactTemplate") {
    form_type_emsFormBuilder = "form";
    const json = multiple_step_ontact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "privateContactTemplate") {
    form_type_emsFormBuilder = "form";
    const json = private_contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "curvedContactTemplate") {
    form_type_emsFormBuilder = "form";
    const json = curved_contact_us_template_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "register") {
    form_type_emsFormBuilder = "register";
    json = [{ "type": "register", "steps": 1, "formName": efb_var.text.register, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.register, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-d-efb", "email_to": "emailRegisterEFB", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message":textThankUEFB('register'), "email_temp": "", "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.registerForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "usernameRegisterEFB", "dataId": "usernameRegisterEFB-id", "type": "text", "placeholder": efb_var.text.username, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.username, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "besie",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordRegisterEFB", "dataId": "passwordRegisterEFB-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "emailRegisterEFB", "dataId": "emailRegisterEFB-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "100", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 9, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , "noti":1 }]
    valj_efb = json;
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "login") {
    form_type_emsFormBuilder = "login";
    json = [{ "type": "login", "steps": 1, "formName": efb_var.text.login, "email": "", "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.login, "button_color": "btn-darkb", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-d-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": efb_var.text.loginForm, "icon": "bi-box-arrow-in-right", "step": 1, "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-dark", "icon_color": "text-danger", "visible": 1 },
    { "id_": "emaillogin", "dataId": "emaillogin-id", "type": "text", "placeholder": efb_var.text.emailOrUsername, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.emailOrUsername, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "passwordlogin", "dataId": "passwordlogin-id", "type": "password", "placeholder": efb_var.text.password, "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": efb_var.text.password, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false }]
    valj_efb = json;

    sessionStorage.setItem('valj_efb', JSON.stringify(json))
  } else if (id === "support") {
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
    form_type_emsFormBuilder = "form";
    const json = support_ticket_form_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "orderForm") {
    form_type_emsFormBuilder = "payment";
    const json = order_payment_form_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "customerFeedback") {
    form_type_emsFormBuilder = "form";
    const json = customer_feedback_efb()
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id === "subscription") {
    form_type_emsFormBuilder = "subscribe";
    const json =
      [{ "type": "subscribe", "steps": 1, "formName": efb_var.text.subscribe, "email":'', 'sendEmail': smail, "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.subscribe, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-d-efb", "email_to": "82i3wedt1", "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB(), "email_temp": "", "stateForm": false, "dShowBg": true },
      { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "", "icon": "bi-check2-square", "step": 1, "amount": 2, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
      { "id_": "janf5eutd", "dataId": "janf5eutd-id", "type": "text", "placeholder": efb_var.text.name, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.name, "required": true, "amount": 3, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
      { "id_": "82i3wedt1", "dataId": "82i3wedt1-id", "type": "email", "placeholder": efb_var.text.email, "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": efb_var.text.email, "required": true, "amount": 5, "step": 1,  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-l-efb", "label_align": "txt-center", "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false , 'noti':1 }]
    sessionStorage.setItem('valj_efb', JSON.stringify(json))
    valj_efb = json;
  } else if (id == "survey") {
    form_type_emsFormBuilder = "survey";
    const json = [{ "type": "survey", "steps": 1, "formName": efb_var.text.survey, "email":'', 'sendEmail': smail, "trackingCode": "", "EfbVersion": 2, "button_single_text": efb_var.text.submit, "button_color": "btn-primary", "icon": "bXXX", "button_Next_text": efb_var.text.next, "button_Previous_text": efb_var.text.previous, "button_Next_icon": "bi-chevron-right", "button_Previous_icon": "bi-chevron-left", "button_state": "single",  "label_text_color": "text-light", "el_text_color": "text-light", "message_text_color": "text-muted", "icon_color": "text-light", "el_height": "h-l-efb", "email_to": false, "show_icon": true, "show_pro_bar": true, "captcha": false, "private": false, "thank_you":"msg", "thank_you_message": textThankUEFB('"survey"'), "email_temp": "", "stateForm": false },
    { "id_": "1", "type": "step", "dataId": "1", "classes": "", "id": "1", "name": "Survey form", "icon": "bi-clipboard-data", "step": "1", "amount": 1, "EfbVersion": 2, "message": "", "label_text_size": "fs-5",  "el_text_size": "fs-5",  "label_text_color": "text-darkb", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "icon_color": "text-danger", "visible": 1 },
    { "id_": "6af03cgwb", "dataId": "6af03cgwb-id", "type": "select", "placeholder": "Select", "value": "", "size": 100, "message": "", "id": "", "classes": "", "name": "what is your favorite food ?", "required": true, "amount": 2, "step": "1",  "label_text_size": "fs-6", "label_position": "up",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
    { "id_": "wxgt1tvri", "dataId": "wxgt1tvri-id", "parent": "6af03cgwb", "type": "option", "value": "Pasta", "id_op": "n9r68xhl1", "step": "1", "amount": 3 },
    { "id_": "wxgt1tvri", "dataId": "wxgt1tvri-id", "parent": "6af03cgwb", "type": "option", "value": "Pizza", "id_op": "khp0a798x", "step": "1", "amount": 4 },
    { "id_": "6x1lv1ufx", "dataId": "6x1lv1ufx-id", "parent": "6af03cgwb", "type": "option", "value": "Fish and seafood", "id_op": "6x1lv1ufx", "step": "1", "amount": 5 },
    { "id_": "yythlx4tt", "dataId": "yythlx4tt-id", "parent": "6af03cgwb", "type": "option", "value": "Vegetables", "id_op": "yythlx4tt", "step": "1", "amount": 6 },
    { "id_": "fe4q562zo", "dataId": "fe4q562zo-id", "type": "checkbox", "placeholder": "Check Box", "value": "", "size": "50", "message": "", "id": "", "classes": "", "name": "Language", "required": 0, "amount": 7, "step": "1",  "label_text_size": "fs-6", "label_position": "beside",  "el_text_size": "fs-6", "label_text_color": "text-labelEfb", "el_border_color": "border-d", "el_text_color": "text-labelEfb", "message_text_color": "text-muted", "el_height": "h-d-efb", "label_align": label_align, "message_align": "justify-content-start", "el_align": "justify-content-start", "pro": false },
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
  } else if (id == "quoteForm") {
    form_type_emsFormBuilder = "form";
    let json = request_a_quote_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "customOrderForm") {
    form_type_emsFormBuilder = "form";
    let json = custom_order_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "jobApplicationForm") {
    form_type_emsFormBuilder = "form";
    let json = job_application_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "rentCarForm") {
    form_type_emsFormBuilder = "form";
    let json = rent_car_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "salonConsultationForm") {
    form_type_emsFormBuilder = "form";
    let json = salon_consultation_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "graphicDesignOrderForm") {
    form_type_emsFormBuilder = "form";
    let json = graphic_design_order_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "sampleCvForm") {
    form_type_emsFormBuilder = "form";
    let json = sample_cv_application_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "videographyBriefForm") {
    form_type_emsFormBuilder = "form";
    let json = videography_creative_brife_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "partyInviteForm") {
    form_type_emsFormBuilder = "form";
    let json = party_invite_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "eventRegistrationForm") {
    form_type_emsFormBuilder = "form";
    let json = sample_event_registration_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "storeSurveyForm") {
    form_type_emsFormBuilder = "survey";
    let json = StoreExperienceSurveyTemplate_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "voterSurveyForm") {
    form_type_emsFormBuilder = "survey";
    let json = VoterBehaviorSurvey_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "signupForm") {
    form_type_emsFormBuilder = "payment";
    let json = signup_form_template_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "sportsLeagueForm") {
    form_type_emsFormBuilder = "form";
    let json = recreational_Sports_league_signup_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "summerReadingForm") {
    form_type_emsFormBuilder = "form";
    let json = summer_reading_program_signup_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "childrenLibraryCardForm") {
    form_type_emsFormBuilder = "form";
    let json = children_library_card_application_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "employeeSuggestionForm") {
    form_type_emsFormBuilder = "form";
    let json = employee_suggestion_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  } else if (id == "bookClubForm") {
    form_type_emsFormBuilder = "form";
    let json = book_club_suggestion_form_efb();
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
  }

  formName_Efb = form_type_emsFormBuilder
  if (s == "npreview") {
    creator_form_builder_Efb();

    if (id != "form" && id != "payment" && id != "smart") { setTimeout(() => { editFormEfb() }, 200) }
  } else if ("pre") {
    previewFormEfb('pre');
  } else {
    previewFormEfb('pc')
  }

}

function head_introduce_efb(state) {

  const link = state == "create" ? '#form' : 'admin.php?page=Emsfb_create'
  let text = `${efb_var.text.efbIsTheUserSentence} ${efb_var.text.efbYouDontNeedAnySentence}`
  let btnSize = mobile_view_efb ? '' : 'btn-lg';
  const domain = efb_var.hasOwnProperty('wsteamDomain') ? 'https://' + efb_var.wsteamDomain +'/pricing' : 'https://whitestudio.team/#pricing';
  let msgpro = efb_var.text.yFreeVEnPro.replace('%2$s', pro_price_efb +'$').replace('%1$s','<span class="efb fw-bold text-pinkEfb">').replace('%3$s','</span>').replace('%4$s',`<br><a href="${domain}" target="_blank" class="efb fw-bold">`).replace('%5$s','</a>');
  let cont = ``;
  let vType = `<div class="efb mx-3 col-lg-4 mt-2 pd-5 col-md-10 col-sm-12 alert alert-light pointer-efb buy-noti ec-efb" data-eventform="links" data-linkname="price">
  <i class="efb bi-diamond text-pinkEfb mx-1"></i>
  <span class="efb text-dark fs-7">${efb_var.text.getPro}</span><br>
  <div class="efb ms-3 fs-7">${msgpro}</div>
  </div>`;
  if(noti_exp_efb!='' && noti_exp_efb!='null'){vType='<div class="efb col-lg-4">'+noti_exp_efb+'</div>';}
  if (state != "create") {
    cont = `
                  <div class="efb clearfix"></div>
                  <p class="efb efb-header-desc efb pb-3 ${mobile_view_efb ? 'fs-7' : 'fs-6'}">${text}</p>

    <div class="efb efb-header-features">
      <span class="efb efb-feature-badge"><i class="efb bi-layers mx-1"></i>Multi-Step</span>
      <span class="efb efb-feature-badge"><i class="efb bi-code-slash mx-1"></i>No Coding</span>
      <span class="efb efb-feature-badge"><i class="efb bi-arrows-move mx-1"></i>Drag & Drop</span>
    </div>

    <div class="efb efb-header-actions">
      <a class="efb btn btn-r btn-primary efb-cta-btn ${btnSize}" href="${link}"><i class="efb bi-plus-circle mx-1"></i>${efb_var.text.createForms}</a>
      <a class="efb btn efb btn-outline-pink efb-cta-btn ${btnSize} ec-efb" data-eventform="links" data-linkname="tutorial"><i class="efb bi-play-circle mx-1"></i>${efb_var.text.tutorial}</a>
    </div>`;
  }
  return `<section id="header-efb" class="efb mx-0 px-0 ${state == "create" ? '' : 'efb-header-card col-12'}">
  <div class="efb row ${mobile_view_efb ? 'mx-2' : 'mx-5'} align-items-center">
              <div class="efb col-lg-7 mt-2 pd-5 col-md-12">
                  <div class="efb efb-header-brand">
                    <img src="${efb_var.images.logo}" class="efb efb-header-logo ${mobile_view_efb ? 'm-1' : ''} efb">
                    <div class="efb efb-header-brand-text">
                      <h1 class="efb pointer-efb mb-0 efb-header-title ${mobile_view_efb ? 'fs-6' : ''} ec-efb" data-eventform="links" data-linkname="efb">${efb_var.text.easyFormBuilder}</h1>
                      <p class="efb pointer-efb efb-header-subtitle ${mobile_view_efb ? 'fs-7' : 'fs-6'} ec-efb" data-eventform="links" data-linkname="ws">${efb_var.text.byWhiteStudioTeam}</p>
                    </div>
                  </div>
                  ${cont}

              </div>
              ${state == "create" && (efb_var.pro==false || efb_var.pro =='false') ? vType : ''}
              ${(state != "create") ? `<div class="efb col-lg-5 col-md-12 efb-header-img-wrap"> <img src="${efb_var.images.head}" class="efb img-fluid efb-header-img"></div>` : ''}
    </div>
  </section> `
}

fun_preview_before_efb = (i, s, pro) => {

  valj_efb = [];
  show_modal_efb("", efb_var.text.preview, "bi-check2-circle", "saveLoadingBox")
  state_modal_show_efb(1);
  if (s == "local") {
    create_form_by_type_emsfb(i, 'pre')
  }
}

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
  side_hide =(el)=>{
    el.classList.remove('show');
    document.getElementById('childsSideMenuConEfb').classList.add('d-none');
    document.getElementById('sideMenuFEfb').classList.add('efbDW-0');
    el.classList.add('efbDW-0');
  }

  side_show =(el)=>{
    el.classList.remove('efbDW-0');
    document.getElementById('sideMenuFEfb').classList.remove('efbDW-0');
    const ch = document.getElementById('childsSideMenuConEfb');
    if(ch)ch.classList.add('d-none');
    el.classList.add('show');
  }
  if (s == 0) {
    side_hide(el)
       setTimeout(() => {
      saveFormEfb(-1);
    }, 2000);
  } else if( s == 1) {
   side_show(el)
  } else if (s == 2) {
    setTimeout(() => {
      saveFormEfb(-1);
    }, 2000);
    const lenV = valj_efb.length
    const timeout = lenV < 100 ? 800 : lenV<500 ? 3000 : 5000;
    let over_page = document.getElementById("overlay_efb");
    over_page.classList.remove("d-none");
    over_page.classList.add("d-block")
    setTimeout(() => {
          side_hide(el)
          over_page.classList.add("d-none")
          over_page.classList.remove("d-block")
    }, timeout);

  }
}

const funSetCornerElEfb = (dataId, co) => {

  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el = document.querySelector(`[data-id='${dataId}-set']`)
  if (el.dataset.side == "undefined" || el.dataset.side == "") {
    valj_efb[indx].corner = co;
    postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `null`
    let cornEl = 'null';

    if(postId!='null'){
      cornEl =document.getElementById(postId)
      if (cornEl==null &&fun_el_select_in_efb(el.dataset.tag)) cornEl = document.getElementById(`${postId}options`)
      if (el.dataset.tag == 'esign') {
        cornEl = document.getElementById(`${valj_efb[indx].id_}_b`)
        let box = document.getElementById(`${valj_efb[indx].id_}_`)
        box.className = cornerChangerEfb(box.className, co)
      }
    }else{

     if (el.dataset.tag == 'dadfile') cornEl = document.getElementById(`${valj_efb[indx].id_}_box`)
    }
    cornEl.className = cornerChangerEfb(cornEl.className, co)

  } else if (el.dataset.side == "yesNo") {
    valj_efb[indx].corner = co;
    document.getElementById(`${valj_efb[indx].id_}_b_1`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_1`).className, co)
    document.getElementById(`${valj_efb[indx].id_}_b_2`).className = cornerChangerEfb(document.getElementById(`${valj_efb[indx].id_}_b_2`).className, co)
  } else {

    valj_efb[0].corner = co;
    postId = document.getElementById('btn_send_efb');
    postId.className = cornerChangerEfb(postId.className, co)
    document.getElementById('next_efb').className = cornerChangerEfb(document.getElementById('next_efb').className, co)
    document.getElementById('prev_efb').className = cornerChangerEfb(document.getElementById('prev_efb').className, co)
  }
}

let change_el_edit_Efb = (el) => {
  let lenV = valj_efb.length
  if (el.value && el.value.length > 0 && (el.value.search(/(")+/g) != -1 || el.value.search(/(>)+/g) != -1 || el.value.search(/(<)+/g) != -1) && el.id !="htmlCodeEl") {
    el.value = el.value.replaceAll(`"`, '');
    alert_message_efb(efb_var.text.error, `Don't use forbidden characters like: ["][<][>]`, 10, "danger");
    return;
  }else if (el.id =="htmlCodeEl" && el.value){
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
  let indx = el.dataset.id != "button_group" && el.dataset.id != "button_group_" && postId != 0 ? valj_efb.findIndex(x => x.dataId == postId || x.dataId==postId+'-id') : 0;
  const len_Valj = valj_efb.length;

  postId = null

  let clss = ''
  let c, color;
  setTimeout(async() => {

    if(el.hasAttribute('value') && el.id!="htmlCodeEl"){

      el.value = el.type!="url" ? sanitize_text_efb(el.value) :el.value.replace(/[<>()[\ ]]/g, '');
    }
      const isToggleButton = el.classList && el.classList.contains('btn-toggle');
      if (el.value==null && !isToggleButton) return  valNotFound_efb()

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
          clss= valj_efb[indx].type=="date" ? 1 :0;
          if(valj_efb[indx].hasOwnProperty('mlen')==false) Object.assign(valj_efb[indx],{mlen:'0'})

          if(clss==1){
            c = /^(0|1|\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])|)$/;
            if (c.test(el.value)) {

              valj_efb[indx].mlen = sanitize_text_efb(el.value);
              c = el.value ==0 ?  0 : el.value !=1 ? el.value : c;

            } else {
              let m = efb_var.text.mnvvXXX_;

              m  = m.replace('%s', "<b>" +  efb_var.text.mxdt + "</b>");
              m += " "+  efb_var.text.ivf.replace('%s', "YYYY-MM-DD, 1");
              alert_message_efb("", m,15,"warning")
              el.value ='';
            }

          }else{
            valj_efb[indx].mlen = el.value;
          }

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
        document.getElementById(c).innerHTML=sanitize_text_efb(el.value);
        break;
      case "miLenEl":
        if( Number(el.value)==0 ||Number(el.value)==-1 ){
          clss = document.getElementById(`${valj_efb[indx].id_}_req`).innerHTML;

          valj_efb[indx].required = clss.length!=0 ? 1 :0;

          valj_efb[indx].milen=0;
        }else if (Number(el.value)>524288 && valj_efb[indx].type!="range" ){
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
            c = /^(0|1|\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])|)$/;

            clss = /^1$/
            if (c.test(el.value)) {

              valj_efb[indx].milen = sanitize_text_efb(el.value);
              c = el.value ==0 ?  0 : el.value !=1 ? el.value : c;

            } else {
              let m = efb_var.text.mnvvXXX_;
              m  = m.replace('%s', "<b>" +  efb_var.text.mindt + "</b>");
              m += " "+  efb_var.text.ivf.replace('%s', "YYYY-MM-DD, 1");
              alert_message_efb("", m,15,"warning")
              el.value ='';
            }

          }
          if(valj_efb[indx].type!="range" || valj_efb[indx].type!="date")valj_efb[indx].required=1;

        }
        break;
      case "adminFormEmailEl":
        if (efb_var.smtp == "1") {
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

              if (el.value.match(/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/))
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
          if(document.getElementById("adminFormEmailEl"))document.getElementById("adminFormEmailEl").value = "";
          alert_message_efb(efb_var.text.error, efb_var.text.sMTPNotWork, 20, "danger")
        }

            clss = false;
            for(let v of valj_efb){
                if(v.hasOwnProperty('noti') && Number(v.noti) ==1){
                  valj_efb[0].sendEmail=true;
                  clss=true
                }
            }
            if (!clss) valj_efb[0].sendEmail=false;

        break;
      case "cardEl":
        indx =  el.classList.contains('active')
        valj_efb[0].hasOwnProperty('dShowBg') ? valj_efb[0].dShowBg =  indx : Object.assign(valj_efb[0], { dShowBg:  indx });
        break;
        case "offLineEl":
          if(efb_var.addons.AdnOF!=0 ){
            indx = el.classList.contains('active')
            valj_efb[0].hasOwnProperty('AfLnFrm') ? valj_efb[0].AfLnFrm = indx : Object.assign(valj_efb[0], { AfLnFrm: indx });
          }else{
            el.checked=false;
            el.classList.remove('active');
            alert_message_efb(efb_var.text.error, `${efb_var.text.IMAddons} ${efb_var.text.offlineTAddon}`, 20, "danger")

          }
        break;
        case 'efbActiveAutoFill':
          if (typeof fun_autofill_event_efb === 'function') fun_autofill_event_efb(el);
        break;
      case "requiredEl":
        valj_efb[indx].required = el.classList.contains('active')==true ? 1 :0;

        const reqEl = document.getElementById(`${valj_efb[indx].id_}_req`);
        if(reqEl) reqEl.innerHTML = valj_efb[indx].required  == true ? '*' : '';
        const aId = {
          email: "_", text: "_", password: "_", tel: "_", url: "_", date: "_", color: "_", range: "_", number: "_", file: "_",
          textarea: "_", dadfile: "_", maps: "-map", checkbox: "_options", radio: "_options", select: "_options",
          multiselect: "_options", esign: "-sig-data", rating: "-stared", yesNo: "_yn"
        }
        postId = aId[valj_efb[indx].type]
        id = valj_efb[indx].id_
        postId = document.getElementById(`${id}${postId}`)
        if(postId) postId.classList.toggle('required');

        // Toggle customRequiredMsgWrapper visibility with fade animation
        const customMsgWrapper = document.querySelector('.customRequiredMsgWrapper');
        if(customMsgWrapper) {
          if(valj_efb[indx].required == 1) {
            // Show with fade in
            customMsgWrapper.style.opacity = '1';
            customMsgWrapper.style.maxHeight = '200px';
            customMsgWrapper.style.padding = '';
            customMsgWrapper.style.margin = '';
          } else {
            // Hide with fade out
            customMsgWrapper.style.opacity = '0';
            customMsgWrapper.style.maxHeight = '0';
            customMsgWrapper.style.padding = '0';
            customMsgWrapper.style.margin = '0';
          }
        }

        break;
      case "hideLabelEl":
        c = el.classList.contains('active')==true ? 1 :0;
        if(valj_efb[indx].hasOwnProperty('hflabel')==false){
          Object.assign(valj_efb[indx],{'hflabel':c})
        }else{
         valj_efb[indx].hflabel = c;
        }

        clss=document.getElementById(`${el.dataset.id}_lab_g`);
        if(c==1){
          document.getElementById(`${valj_efb[indx].id_}_labG`).classList.add('d-none');
          funSetPosElEfb(valj_efb[indx].dataId,'up')

        }else{
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
          break;
        case "showInPublicResultsEl":
          c = el.classList.contains('active') == true ? 1 : 0;
          if (!valj_efb[indx].hasOwnProperty('showInPublicResults')) {
            Object.assign(valj_efb[indx], { 'showInPublicResults': c });
          } else {
            valj_efb[indx].showInPublicResults = c;
          }
          break;
      case "SendemailEl":

        if (efb_var.smtp == "true" || Number(efb_var.smtp) == 1 ) {

          postId= valj_efb[indx].id_;
          valj_efb[0].email_to = el.dataset.vid;
          c= el.classList.contains('active')==true ? 1 :0

          valj_efb[indx].hasOwnProperty('noti')==false?  Object.assign(valj_efb[indx],{'noti':c}) : valj_efb[indx].noti = c;
          clss = false;
          if(valj_efb[0].email.length<2){
            for(let v of valj_efb){
                if(v.hasOwnProperty('noti') && Number(v.noti) ==1){
                  valj_efb[0].sendEmail=true;
                   valj_efb[0].email_to=v.id_;
                  clss=true
                }else{
                  if(valj_efb[0].email_to==v.id_){
                    valj_efb[0].email_to="";
                  }
                }
            }
          }else{
            clss=true;
          }
          if(clss==false) valj_efb[0].sendEmail=false;

        } else {
          el.classList.remove('active');
          const msg =  efb_var.text.sMTPNotWork + '' + `<a class="efb alert-link ec-efb" data-eventform="links" data-linkname="EmailNoti"> ${efb_var.text.orClickHere}</a>`;
          alert_message_efb(efb_var.text.error,msg, 20, "danger")
        }

        break;
      case "smsEnableEl":

       if(pro_efb!=true){
          pro_show_efb(1);
          document.getElementById("smsEnableEl").checked = false;
          document.getElementById("smsEnableEl").classList.remove('active') ;
       }
       if(Number(efb_var.setting.AdnSS)!=1){

        document.getElementById("smsEnableEl").classList.remove('active') ;
        let m = efb_var.text.msg_adons.replace('NN',`<b>${efb_var.text.sms_noti}</b>`);
        alert_message_efb(efb_var.text.error, m, 20, "danger")
        return false;
       }
       if(indx==-1){indx=0};
          c = el.classList.contains('active')==true ? 1 :0

        valj_efb[indx].hasOwnProperty('smsnoti')==false ? Object.assign(valj_efb[indx],{'smsnoti':c}) : valj_efb[indx].smsnoti = c;

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
            const smsEls = document.querySelectorAll('.smsmsg')
            smsEls.forEach((el)=>{

              el.disabled=false;
              el.classList.remove('disabled');
              el.classList.remove('d-none');
            })
          }else{
            const smsEls = document.querySelectorAll('.smsmsg')
            smsEls.forEach((el)=>{

              el.disabled=true;
              el.classList.add('disabled');
              el.classList.add('d-none');
            })
          }

          if(valj_efb[0].hasOwnProperty('smsnoti')!=false){

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
      case "telegramEnableEl":
        if(pro_efb!=true){
          pro_show_efb(1);
          document.getElementById("telegramEnableEl").checked = false;
          document.getElementById("telegramEnableEl").classList.remove('active') ;
        }
        if(Number(efb_var.addons.AdnTLG)!=1){
          document.getElementById("telegramEnableEl").classList.remove('active') ;
          let m = efb_var.text.msg_adons.replace('NN',`<b>${efb_var.text.etelegramno || 'Telegram notifications'}</b>`);
          alert_message_efb(efb_var.text.error, m, 20, "danger")
          return false;
        }
        if(indx==-1){indx=0};
          c = el.classList.contains('active')==true ? 1 :0

        valj_efb[indx].hasOwnProperty('telegramnoti')==false ? Object.assign(valj_efb[indx],{'telegramnoti':c}) : valj_efb[indx].telegramnoti = c;

        if(indx!=0){
          if(c==1){
            indx=0;
          }else{
            clss=-1;
            clss= valj_efb.findIndex(x => x.telegramnoti == 1);
          }
        }
        if(indx==0){
          if (c==1){
            const telegramEls = document.querySelectorAll('.telegrammsg')
            telegramEls.forEach((el)=>{
              el.disabled=false;
              el.classList.remove('disabled');
              el.classList.remove('d-none');
            })
          }else{
            const telegramEls = document.querySelectorAll('.telegrammsg')
            telegramEls.forEach((el)=>{
              el.disabled=true;
              el.classList.add('disabled');
              el.classList.add('d-none');
            })
          }

          if(valj_efb[0].hasOwnProperty('telegramnoti')!=false){
            c= document.querySelector(`.telegram-efb[data-id="newMessageReceived"]`)
            if(c){ c= sanitize_text_efb(c.value ,true); Object.assign(valj_efb[0], { telegram_msg_new_noti: c }); }

            if(valj_efb[0].type!="register" && valj_efb[0].type!="login"){
              c= document.querySelector(`.telegram-efb[data-id="responsedMessage"]`)
              if(c){ c= sanitize_text_efb(c.value ,true); Object.assign(valj_efb[0], { telegram_msg_responsed_noti:c }); }
            }else{
              Object.assign(valj_efb[0], { telegram_msg_responsed_noti:'' });
            }
          }
        }

        break;
      case "smsAdminsPhoneNoEl":

        if(el.value.includes(',')){
          let phones=el.value.split(',');
          let isPhone=true;
            phones.forEach((phone)=>{
              phone=phone.trim();
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
            if (el.value.match(/^\+[0-9]{8,15}$/))
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
          valj_efb[0].captcha = el.classList.contains('active')==true ? true : false
          fun_add_Class_captcha(valj_efb[0].captcha);
          if(document.getElementById('recaptcha_efb')) el.classList.contains('active') == true ? document.getElementById('recaptcha_efb').classList.remove('d-none') : document.getElementById('recaptcha_efb').classList.add('d-none')

        } else if (valj_efb[0].type == "payment") {
          el.classList.remove('active');
          fun_add_Class_captcha(false);
          alert_message_efb(efb_var.text.reCAPTCHA, efb_var.text.paymentNcaptcha, 20, "danger");
          return;
        }
        if (efb_var.captcha !=true && efb_var.captcha !="true" ){
          el.classList.remove('active');
       }
        break;
      case "shieldSilentCaptchaEl":
        const shieldAvailable = efb_var.shield_available === true || efb_var.shield_available === 1 || efb_var.shield_available === '1' || efb_var.shield_available === 'true';
        if (!shieldAvailable) {
          return;
        }
        valj_efb[0].shield_silent_captcha = el.classList.contains('active') == true ? true : false;
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
        if(pro_efb!=true){
          pro_show_efb(3);
          el.value="";
          break;
        }
        if(valj_efb[0].hasOwnProperty('email_noti_type')==false) Object.assign(valj_efb[0],{'email_noti_type':el.options[el.selectedIndex].value})
        valj_efb[0].email_noti_type = el.options[el.selectedIndex].value;

        break;
      case "placeholderEl":
        document.querySelector(`[data-id="${valj_efb[indx].id_}-el"]`).placeholder = sanitize_text_efb(el.value);

        valj_efb[indx].placeholder = sanitize_text_efb(el.value);
        break;
      case "customRequiredMsgEl":
        // Save custom required validation message for the field
        if(valj_efb[indx].hasOwnProperty('customRequiredMsg')==false) Object.assign(valj_efb[indx],{'customRequiredMsg':''})
        valj_efb[indx].customRequiredMsg = sanitize_text_efb(el.value);
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
          c= sanitize_text_efb(el.value);
          document.getElementById(`${valj_efb[indx].id_}_`).value = c;
          valj_efb[indx].value = c;
           if(valj_efb[indx].type=='range'){
            document.getElementById(`${valj_efb[indx].id_}_rv`).innerHTML = c;
           }
        } else if (el.dataset.tag == 'heading' ||el.dataset.tag == 'link' ||el.dataset.tag == 'textarea') {
          c= el.dataset.tag=='textarea' ? sanitize_text_efb(el.value ,true) : sanitize_text_efb(el.value);
          document.getElementById(`${valj_efb[indx].id_}_`).innerHTML = text_nr_efb(c,0);
          valj_efb[indx].value = c;
        } else {
          c= sanitize_text_efb(el.value);
          id = `${valj_efb[indx].id_}_${el.dataset.no}`
          document.getElementById(id).value = c;
          document.getElementById(`${id}_lab`).innerHTML =c;
          el.dataset.no == 1 ? valj_efb[indx].button_1_text = c : valj_efb[indx].button_2_text = c

        }
        break;
        case "classesEl":
        id = valj_efb[indx].id_;
        temp = sanitize_text_efb(el.value.replace(` `, `,`));
        c = temp.split(',');
        const old_class = valj_efb[indx].classes.split(',');

        postId = document.querySelectorAll(`[data-css='${id}']`);
        for (let i = 0; i < postId.length; i++) {
            const d = postId[i];
              let clss = d.classList;
              clss =  Array.from(clss).filter(element => !old_class.includes(element));
              const comp = new Set([...clss,...c]);
              const array = [...comp];
              clss = array.join(' ')

            d.className = `${clss}`.trim();
            valj_efb[indx].classes = temp.replace(`,`, ` `);
        }
        break;
      case "sizeEl":
        postId = document.getElementById(`${valj_efb[indx].id_}_labG`)
        if (valj_efb[indx].hasOwnProperty('size')) Object.assign(valj_efb[indx],{size:100});
        const op = el.options[el.selectedIndex].value;
        valj_efb[indx].size = op;
        get_position_col_el(valj_efb[indx].dataId, true);
        break;
      case "mobileSizeEl":
        if (!valj_efb[indx].hasOwnProperty('mobile_size')) Object.assign(valj_efb[indx],{mobile_size:100});
        clss = el.options[el.selectedIndex].value;
        valj_efb[indx].mobile_size = clss;
        if (typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile') {
          get_position_col_mobile_el(valj_efb[indx].dataId, true);
        }
        break;
      case "cornerEl":

        const co = el.options[el.selectedIndex].value;
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
          valj_efb[indx].corner = co;
          postId = el.dataset.tag != 'dadfile' ? `${valj_efb[indx].id_}_` : `${valj_efb[indx].id_}_box`
          let cornEl = document.getElementById(postId);
          if (fun_el_select_in_efb(el.dataset.tag)) cornEl = el.dataset.tag == 'conturyList' || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'cityList' || el.dataset.tag == 'select' ? document.getElementById(`${postId}options`) : document.getElementById(`${id}ms`)

          cornEl.classList.toggle('efb-square')
          if (el.dataset.tag == 'dadfile' || el.dataset.tag == 'esign') document.getElementById(`${valj_efb[indx].id_}_b`).classList.toggle('efb-square')

        } else {
          valj_efb[0].corner = co;
          postId = document.getElementById('btn_send_efb');

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
      case "mobileLabelFontSizeEl":
        if (!valj_efb[indx].hasOwnProperty('mobile_label_text_size')) Object.assign(valj_efb[indx],{mobile_label_text_size:'fs-6'});
        valj_efb[indx].mobile_label_text_size = el.options[el.selectedIndex].value;
        if (typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile') {
          let mFontLeb = document.getElementById(`${valj_efb[indx].id_}_lab`);
          if (mFontLeb) mFontLeb.className = fontSizeChangerEfb(mFontLeb.className, el.options[el.selectedIndex].value);
        }
        break;
      case "optnsStyleEl":
        valj_efb[indx].op_style = el.options[el.selectedIndex].value;
        c =document.getElementById(`${valj_efb[indx].id_}_options`);
        if(valj_efb[indx].op_style!="1"){
          if(!c.classList.contains('row')) c.className += ' row col-md-12';

          clss = valj_efb[indx].op_style=="2" ? 'col-md-6' : 'col-md-4'
          for (let v of document.querySelectorAll(`[data-parent='${valj_efb[indx].id_}'].form-check`)){
            v.className = colMdRemoveEfb(v.className);
            v.classList.add(clss)

          }

        }else{
          if(c.classList.contains('row')){
             c.classList.remove('row'); c.classList.remove('col-md-12')
             for (let v of document.querySelectorAll(`[data-parent='${valj_efb[indx].id_}'].form-check`)){
              v.className = colMdRemoveEfb(v.className);

            }
            }
        }
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
          valj_efb[0].thank_you ='msg';
          valj_efb[0].rePage = '';
        }
        break;
      case "thankYouredirectEl":

        postId = el.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?!&\/=;',]*)$/gi)
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
        valj_efb[0].payment = el.options[el.selectedIndex].value;

        break;
      case "paymentMethodEl":
        valj_efb[0].paymentmethod = el.options[el.selectedIndex].value;

        el = document.getElementById('chargeEfb')
        if (valj_efb[0].paymentmethod == 'charge') {
          el.innerHTML = efb_var.text.onetime;
          if (el.classList.contains('one') == false) el.classList.add('one')
        } else {
          id = `${valj_efb[0].paymentmethod}ly`;

          el.innerHTML = efb_var.text[id];
          if (el.classList.contains('one') == false) el.classList.remove('one')
        }

        break;
      case "formTypeEl":
        const oldFormType = valj_efb[0].type;
        valj_efb[0].type = el.options[el.selectedIndex].value;
        form_type_emsFormBuilder = valj_efb[0].type;

        // Update thank you messages based on form type if user hasn't customized them
        if (typeof getDefaultThankYouByType === 'function') {
          const newDefaults = getDefaultThankYouByType(valj_efb[0].type);

          // Update thankYou if it's still a default value
          if (typeof isDefaultThankYou === 'function' && isDefaultThankYou(valj_efb[0].thank_you_message.thankYou)) {
            valj_efb[0].thank_you_message.thankYou = newDefaults.thankYou;
            const thankYouInput = document.getElementById('thankYouMessageEl');
            if (thankYouInput) thankYouInput.value = newDefaults.thankYou;
          }

          // Update done if it's still a default value
          if (typeof isDefaultDone === 'function' && isDefaultDone(valj_efb[0].thank_you_message.done)) {
            valj_efb[0].thank_you_message.done = newDefaults.done;
            const doneInput = document.getElementById('thankYouMessageDoneEl');
            if (doneInput) doneInput.value = newDefaults.done;
          }
        }

        const surveyChartWrapper = document.getElementById('surveyChartOptionsWrapper');
        if (surveyChartWrapper) {
          if (valj_efb[0].type === 'survey') {
            surveyChartWrapper.classList.remove('d-none');
          } else {
            surveyChartWrapper.classList.add('d-none');
          }
        }
        const publicResultsToggles = document.querySelectorAll('.survey-public-results-toggle');
        publicResultsToggles.forEach(toggle => {
          if (valj_efb[0].type === 'survey') {
            toggle.classList.remove('d-none');
          } else {
            toggle.classList.add('d-none');
          }
        });
        break;
      case "surveyChartTypeEl":
        if (!valj_efb[0].hasOwnProperty('survey_chart_type')) {
          Object.assign(valj_efb[0], { survey_chart_type: 'none' });
        }
        valj_efb[0].survey_chart_type = el.options[el.selectedIndex].value;
        break;
      case "loadingTypeEl":
        if(pro_efb!=true){
          pro_show_efb(3);
          return;
        }else{
          valj_efb[0].loading_type ='bars';
          if (!valj_efb[0].hasOwnProperty('loading_type')) {
            Object.assign(valj_efb[0], { loading_type: 'bars' });
          }
          valj_efb[0].loading_type = el.options[el.selectedIndex].value;
          updateLoadingPreview();
        }
        break;
      case "loadingColorEl":
        if(pro_efb!=true){
          pro_show_efb(3);
          return;
        }else{
          if (!valj_efb[0].hasOwnProperty('loading_color')) {
            Object.assign(valj_efb[0], { loading_color: '#abb8c3' });
          }
          valj_efb[0].loading_color = el.value;
          const colorTextEl = document.getElementById('loadingColorTextEl');
          if (colorTextEl) colorTextEl.value = el.value;
          updateLoadingPreview();
        }
        break;
      case "currencyTypeEl":
        if(valj_efb[0].hasOwnProperty('currency')==false) Object.assign(valj_efb[0],{'currency':'USD '})
        valj_efb[0].currency = el.options[el.selectedIndex].value.slice(0, 3);
        for (const l of document.querySelectorAll(".totalpayEfb")) {
         if(l.classList.contains('ir')==false) l.innerHTML = Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
        }
        funRefreshPricesEfb();

        break;
      case "fileTypeEl":
        valj_efb[indx].file = el.options[el.selectedIndex].value;

        valj_efb[indx].value = el.options[el.selectedIndex].value;
        let nfile = el.options[el.selectedIndex].value.toLowerCase();
        nfile = efb_var.text[nfile];
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

        valj_efb[indx].hasOwnProperty('file_ctype')==false ? Object.assign(valj_efb[indx],{'file_ctype':c}) : valj_efb[indx].file_ctype = c;

        if (document.getElementById(`${valj_efb[indx].id_}_txt`)) document.getElementById(`${valj_efb[indx].id_}_txt`).innerHTML = `${efb_var.text.dragAndDropA} ${c}`

        break;

      case "btnColorEl":
        color = el.value;

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
        }

        break;
      case "selectColorEl":
        color = el.value;
        c = switch_color_efb(color);
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
          valj_efb[indx].icon_color = "text-" + c;
          postId = '_icon'
        } else if (el.dataset.el == "el") {
          valj_efb[indx].el_text_color = "text-" + c;
          postId = '_'
        }
         else if (el.dataset.el == "clrdoneMessageEfb") {
          valj_efb[0].clrdoneMessageEfb = "text-" + c;
          return;
        }
         else if (el.dataset.el == "clrdoneTitleEfb") {
          valj_efb[0].clrdoneTitleEfb = "text-" + c;
          return;
        }
         else if (el.dataset.el == "clrdoniconEfb") {
          valj_efb[0].clrdoniconEfb = "text-" + c;
          return;
        }
         else if (el.dataset.el == "progessbar") {
          valj_efb[0].hasOwnProperty('prg_bar_color')==false ?  Object.assign(valj_efb[0],{'prg_bar_color':"btn-" + c}) : valj_efb[0].prg_bar_color = "btn-" + c;
          return;
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
              break;
            case 'description':
              valj_efb[indx].style_label_color ? valj_efb[indx].style_message_text_color = color : Object.assign(valj_efb[indx], { style_message_text_color: color });
              break;
            case 'el':
              valj_efb[indx].el_text_color ? valj_efb[indx].style_el_text_color = color : Object.assign(valj_efb[indx], { style_el_text_color: color });
              break;
            case 'icon':
              valj_efb[indx].style_icon_color ? valj_efb[indx].style_icon_color = color : Object.assign(valj_efb[indx], { style_icon_color: color });
              break;

            default:
              break;
          }
        }
        break;
      case "selectCheckedColorEl":
        // Checked color for radio/checkbox elements (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('checked_color') == false ? Object.assign(valj_efb[indx], { 'checked_color': color }) : valj_efb[indx].checked_color = color;
        // Apply the checked color to the form preview
        applyCheckedColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectRangeThumbColorEl":
        // Range thumb color (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('range_thumb_color') == false ? Object.assign(valj_efb[indx], { 'range_thumb_color': color }) : valj_efb[indx].range_thumb_color = color;
        // Apply the range thumb color to the form preview
        applyRangeThumbColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectRangeValueColorEl":
        // Range value text color (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('range_value_color') == false ? Object.assign(valj_efb[indx], { 'range_value_color': color }) : valj_efb[indx].range_value_color = color;
        // Apply the range value color to the form preview
        applyRangeValueColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectSwitchOnColorEl":
        // Switch on color (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('switch_on_color') == false ? Object.assign(valj_efb[indx], { 'switch_on_color': color }) : valj_efb[indx].switch_on_color = color;
        // Apply the switch on color to the form preview
        applySwitchOnColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectSwitchOffColorEl":
        // Switch off color (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('switch_off_color') == false ? Object.assign(valj_efb[indx], { 'switch_off_color': color }) : valj_efb[indx].switch_off_color = color;
        // Apply the switch off color to the form preview
        applySwitchOffColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectSwitchHandleColorEl":
        // Switch handle color (PRO feature)
        color = el.value;
        valj_efb[indx].hasOwnProperty('switch_handle_color') == false ? Object.assign(valj_efb[indx], { 'switch_handle_color': color }) : valj_efb[indx].switch_handle_color = color;
        // Apply the switch handle color to the form preview
        applySwitchHandleColorEfb(valj_efb[indx].id_, color);
        break;
      case "selectBorderColorEl":

        color = el.value;
        c = switch_color_efb(color);

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
        }
        break;
      case "fontSizeEl":

        valj_efb[indx].el_text_size = el.options[el.selectedIndex].value;
        id = `${valj_efb[indx].id_}_`;
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
          postId = `${valj_efb[indx].id_}_options`;
          let msel = document.getElementById(postId);
          msel.className.match(/h-+\w+-efb/g) ? msel.className = inputHeightChangerEfb(msel.className, valj_efb[indx].el_height) : msel.classList.add(valj_efb[indx].el_height)
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
        }else if (c == "range") {
          postId = `${valj_efb[indx].id_}-range`

          document.getElementById(postId).className = fontSizeChangerEfb(document.getElementById(postId).className, fsize);
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
        valj_efb[indx].mark = el.value!='' ? Number(el.value) :1;
        clss=  document.querySelector(`[data-id="${valj_efb[indx].id_}-contorller"]`);
        clss.classList.add('efb');
        c==0 ?  clss.classList.add('d-none')  : clss.classList.remove('d-none') ;

          clss.innerHTML= `
              <a class="efb btn btn-sm btn-dark text-light"><i class="efb bi-crosshair ${efb_var.rtl == 1 ? 'ms-2' : 'me-2'} fs-7"></i></a>
              <input type="text" id="efb-search-${valj_efb[indx].id_}" placeholder="${efb_var.text.eln}" class="efb p-1 border-d efb-square locationpicker fs-6">
              <a class="efb btn btn-sm btn-secondary text-light">${efb_var.text.search}</a>
              <a class="efb btn btn-sm btn-danger text-light">${efb_var.text.deletemarkers}</a>
              <div id="efb-error-message-${valj_efb[indx].id_}" class="error-message d-none"></div>`

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
        const iindx = valj_efb.findIndex(x => x.id_op == el.dataset.id);

        if (iindx != -1) {

          valj_efb[iindx].value = el.value;
          if (el.dataset.tag == "select" || el.dataset.tag == 'stateProvince' || el.dataset.tag == 'conturyList' ||  el.dataset.tag == 'cityList' ) {

            let vl = document.querySelector(`[data-op="${el.dataset.id}"]`);
            if (vl) vl.innerHTML = el.value;
            if (vl) vl.value = el.value;
            c =vl.dataset.id;
            temp = valj_efb.findIndex(x=>x.id_op == c);

            valj_efb[temp].value = el.value;

          }else if(el.dataset.tag == "imgRadio"){

            document.getElementById(`${valj_efb[iindx].id_op}_value`).innerHTML = el.value;
          }else if(el.dataset.tag == "table_matrix"){
            document.getElementById(`${valj_efb[iindx].id_op}_lab`).innerHTML = el.value;
          } else if (el.dataset.tag != "multiselect" && el.dataset.tag != 'payMultiselect') {
             document.querySelector(`[data-op="${el.dataset.id}"]`).value = el.value;
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

        color = valj_efb[c].type.toLowerCase();
        const oi = valj_efb[c].value
        if(color.includes("radio")==true || (color.includes("select")==true &&  color.includes("multi")==false)
        || color.includes("conturylist")==true || color.includes("stateprovince")==true || color.includes("citylist")==true){
          valj_efb[c].value =valj_efb[indx].id_

          if(oi.length>0 && color.includes("radio")==true){

            document.querySelector(`[data-id="${oi}"]`).removeAttribute("checked");
            document.getElementById(oi).removeAttribute("checked");
          }

             clss =valj_efb[indx].id_;
             el.setAttribute("checked",true)

         if(color.includes("radio")==true) {
           document.getElementById(clss).setAttribute("checked",true);
          }else if((color.includes("select")==true || color.includes('citylist') || color.includes("stateprovince") || color.includes("conturylist")) && color.includes("multi")==false){
             const optionEl = document.getElementById(clss);
            if (optionEl) {
              optionEl.selected = true;
              const selectEl = optionEl.parentElement;
              if (selectEl) {
                selectEl.value = optionEl.value;
                selectEl.dispatchEvent(new Event('change'));
              }
            }else{
              const parent = valj_efb[indx].parent;
              const selectEl = document.getElementById(parent+'_options');
              if (selectEl) {
                selectEl.innerHTML = '';
                const option = document.createElement('option');
                const value =valj_efb[indx].value;
                option.value = value;
                option.textContent = value;
                selectEl.appendChild(option);
                selectEl.dispatchEvent(new Event('change'));
              }
            }

          }
        }else{
          clss = valj_efb[indx].id_;
          if(el.checked==false){
            el.removeAttribute("checked")
            if(color.includes("checkbox")==true) {
              document.getElementById(clss).removeAttribute("checked");
              el.removeAttribute("checked");
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

        }
        break;
      case 'paymentOption':
        el.dataset.id;
        const ipndx = valj_efb.findIndex(x => x.id_op == el.dataset.id);

        if (ipndx != -1) {
          valj_efb[ipndx].price = el.value;
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
            <div class="icon-container efb"><i class="efb bi-gear-wide-connected text-success ${efb_var.rtl == 1 ? 'ms-2' : 'me-2'} fs-7" id="efbSetting" ></i></div></button> ${efb_var.text.andAddingHtmlCode}
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
          for (let v of document.querySelectorAll(`[data-id='${valj_efb[indx].id_}']`)){

            v.placeholder = el.value;
          }
        break;
      case "countriesListEl":
        valj_efb[indx].country =  el.options[el.selectedIndex].value
        if(el.dataset.tag =="stateProvince" && document.getElementById('optionListefb')!=null){
          el.classList.add('is-loading');

          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
           let url = efb_var.zone_area ?? ajax_object_efm.zone_area
           url= url +`json/states/${valj_efb[indx].country.toLowerCase()}.json`;
           if(efb_var.setting.hasOwnProperty('AdnOF') && efb_var.setting.AdnOF==true){
            url =efb_var.images.plugin_url+`/vendor/offline/json/states/${valj_efb[indx].country.toLowerCase()}.json`;
             url = url.replaceAll('//vendor','/vendor');
          }
          temp_efb= await fetch_json_from_url_efb_admin(url);
          let  opetions;
          const newRndm = Math.random().toString(36).substr(2, 9);
          setTimeout(() => {
            if(temp_efb.s==false ||temp_efb=="null" ) {
              alert_message_efb(efb_var.text.error, temp_efb.r ,15 , "danger")
              return;
            }

            obj_delete_options(valj_efb[indx].id_);
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

            }
            const objOptions = valj_efb.filter(obj => {
              return obj.parent === valj_efb[indx].id_
            })

            opetions= efb_add_opt_setting(objOptions, el ,false ,newRndm ,"")
            el.classList.remove('is-loading');
            if(document.getElementById('optionListefb')){
              document.getElementById('optionListefb').innerHTML=opetions;
              update_event_elmants_settings('.elEdit.newElOp')
            }
          }, 4000);

          valj_efb[indx].country =  el.options[el.selectedIndex].value
        }else if(el.dataset.tag =="cityList" && document.getElementById('optionListefb')!=null){
          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
            callFetchStatesPovEfb('statePovListEl', valj_efb[indx].country, indx,'getStatesPovEfb');
            valj_efb[indx].country =  el.options[el.selectedIndex].value
        }

        break;
      case "statePovListEl":
        valj_efb[indx].statePov =  el.options[el.selectedIndex].value.toLowerCase();
        temp = valj_efb[indx].country.toLowerCase();
        if( document.getElementById('optionListefb')!=null){
          el.classList.add('is-loading');

          document.getElementById('optionListefb').innerHTML=donwload_event_icon_efb('text-darkb');
          let url = efb_var.zone_area ?? ajax_object_efm.zone_area
          url = url + `json/cites/${temp}/${valj_efb[indx].statePov}.json`;
          if(efb_var.setting.hasOwnProperty('AdnOF') && efb_var.setting.AdnOF==true){
            url =efb_var.images.plugin_url+'/vendor/offline/json/cites/'+temp+'/'+valj_efb[indx].statePov+'.json';
            url = url.replaceAll('//vendor','/vendor');
          }
          temp_efb = await fetch_json_from_url_efb_admin(url);
          let  opetions;
          const newRndm = Math.random().toString(36).substr(2, 9);

            if(temp_efb.s==false ||temp_efb=="null" ) {
              alert_message_efb(efb_var.text.error, temp_efb.r ,15 , "danger")
              return;
            }
            obj_delete_options(valj_efb[indx].id_)
            for (const key in temp_efb.r) {

              const value = temp_efb.r[key];
              const nValue = value.n.trim();
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

            }
            const objOptions = valj_efb.filter(obj => {
              return obj.parent === valj_efb[indx].id_
            })

            opetions= efb_add_opt_setting(objOptions, el ,false ,newRndm ,"")
            el.classList.remove('is-loading');
            if(document.getElementById('optionListefb')) {
              document.getElementById('optionListefb').innerHTML=opetions;
              update_event_elmants_settings('.elEdit.newElOp')
            }

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
          if(n!=-1) c= valj_efb[0].conditions[n].condition.findIndex(x=>x.no ==no);

          if (c!=-1){
            valj_efb[0].conditions[n].condition[c].one = sanitize_text_efb(el.options[el.selectedIndex].value);

            const fid =( el.options[el.selectedIndex].dataset.fid);
            const idset = (el.options[el.selectedIndex].dataset.idset);
            const s_op = sanitize_text_efb(el.options[el.selectedIndex].value);
            valj_efb[0].conditions[n].condition[c].two="";
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
         if(pro_efb!=true){
           pro_show_efb(1);
           return;
         }

         c = sanitize_text_efb(el.value ,true);
         if(el.dataset.id=="WeRecivedUrM"){
          valj_efb[0].hasOwnProperty('sms_msg_recived_usr') ? valj_efb[0].sms_msg_recived_usr = c : Object.assign(valj_efb[0], { sms_msg_recived_usr: c })

         }else if(el.dataset.id=="responsedMessage"){
          valj_efb[0].hasOwnProperty('sms_msg_responsed_noti') ? valj_efb[0].sms_msg_responsed_noti = c : Object.assign(valj_efb[0], { sms_msg_responsed_noti: c })

         }else if(el.dataset.id=="newMessageReceived"){
            valj_efb[0].hasOwnProperty('sms_msg_new_noti') ? valj_efb[0].sms_msg_new_noti = c : Object.assign(valj_efb[0], { sms_msg_new_noti: c })

         }

      break;
      case 'telegramContentEl':
         if(pro_efb!=true){
           pro_show_efb(1);
           return;
         }

         c = sanitize_text_efb(el.value ,true);
         if(el.dataset.id=="responsedMessage"){
          valj_efb[0].hasOwnProperty('telegram_msg_responsed_noti') ? valj_efb[0].telegram_msg_responsed_noti = c : Object.assign(valj_efb[0], { telegram_msg_responsed_noti: c })

         }else if(el.dataset.id=="newMessageReceived"){
          valj_efb[0].hasOwnProperty('telegram_msg_new_noti') ? valj_efb[0].telegram_msg_new_noti = c : Object.assign(valj_efb[0], { telegram_msg_new_noti: c })

         }

      break;
      case 'languageSelectPresentEl':

         temp = el.options[el.selectedIndex].value;
         Object.assign(valj_efb[indx], { stylish: el.options[el.selectedIndex].value })
         c =valj_efb.filter(item => item.parent == valj_efb[indx].id_);

         const newRndm = Math.random().toString(36).substr(2, 9);
         for (const value of valj_efb) {

          if(value.parent == valj_efb[indx].id_){
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

         }
            opetions= efb_add_opt_setting(c, el ,false ,newRndm ,"")

            document.getElementById('optionListefb').innerHTML="";
            document.getElementById('optionListefb').innerHTML=opetions
      break;
      case 'FormEmailSubjectEl':
        if(pro_efb!=true){
          pro_show_efb(3);
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
  const body = efbLoadingCard('',4)
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

async function create_form_efb() {
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
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option') {

        content += addNewElement(value.type, value.id_, true, true);

      }
    })
    step_no += 1;
    content += `
                ${sitekye_emsFormBuilder.length > 1 && valj_efb[0].captcha == true ? `<div class="efb row mx-3"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0" data-sitekey="${sitekye_emsFormBuilder}" style=”transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;”></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                <!-- fieldset formNew 1 --> </fieldset>

                <fieldset data-step="step-${step_no}-efb" class="efb my-5 pb-5 steps-efb efb row d-none text-center" id="efb-final-step">
                ${efbLoadingCard('', 4)}
                <!-- fieldset formNew 2 --> </fieldset>
      `
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb  ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`}" ><i class="efb bi-check-lg ${efb_var.rtl == 1 ? 'ms-2' : 'me-2'} fs-7"></i><strong class="efb  fs-5 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch (error) {
  }

  if (content.length > 10) content += `</div>`

  const bgc = valj_efb[0].hasOwnProperty('prg_bar_color') ?valj_efb[0].prg_bar_color: 'btn-primary'

  head = `${valj_efb[0].show_icon == 0 || valj_efb[0].show_icon == false ? `<ul id="steps-efb" class="efb mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="efb d-flex justify-content-center"><div class="efb progress mx-4"><div class="efb  progress-bar-efb ${bgc} progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div></div> <br> ` : ``}`

  content = `
    <div class="efb px-0 pt-2 pb-0 my-1 col-12" id="view-efb">
    <h4 id="title_efb" class="efb ${valj_efb[1].label_text_color} mt-3 mb-0 text-center efb">${valj_efb[1].name}</h4>
    <p id="desc_efb" class="efb ${valj_efb[1].message_text_color} fs-7 mb-2 text-center efb">${valj_efb[1].message}</p>

     <form id="efbform"> ${head} <div class="efb mt-1 px-2">${content}</div> </form>
    </div>
    `
  return content
}

const saveFormEfb = async (stated) => {

  const isAutoSave = stated === -1;
  const modalElement = document.getElementById('settingModalEfb');
  const isModalOpen = modalElement && modalElement.classList.contains('show');
  if (isAutoSave && isModalOpen) {
    return Promise.resolve(false);
  }

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

      if (stated === 1 && valj_efb[0].type === 'payment') {
        valj_efb[0].captcha = "0";
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
          btn = `<button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3" onclick ="${btnFun}">
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
          state_modal_show_efb(0);
        }else if (stated==-1){
          resolve(returnn);
          return;
        }

        resolve(returnn);
      } catch (error) {
        store_form_efb();
        btnIcon = 'bi-bug';
        body = `
          <div class="efb pro-version-efb-modal efb"></div>
          <h5 class="efb txt-center text-darkb fs-6">${efb_var.text.pleaseReporProblem}</h5>
          <div class="efb text-center">
            <button type="button" class="efb btn efb btn-outline-pink efb-btn-lg mt-3 mb-3" onclick ="fun_report_error('fun_saveFormEfb','${error}')">
              <i class="efb bi-megaphone ${efb_var.rtl == 1 ? 'ms-2' : 'me-2'}"></i> ${efb_var.text.reportProblem} </button>
          </div>
        `;
        show_modal_efb(body, efb_var.text.error, btnIcon, 'error');

        state_modal_show_efb(1);
        reject(error);
      }
    }, 100);
  });
};

let editFormEfb =async () => {
  valueJson_ws_p = 0;
  let dropZoneEFB = document.getElementById('dropZoneEFB');
  dropZoneEFB.innerHTML = efbLoadingCard('',4);
  if (sessionStorage.getItem('valj_efb')) { valj_efb = JSON.parse(sessionStorage.getItem('valj_efb')); }
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

          if (valj_efb[v].hasOwnProperty('type') &&  valj_efb[v].type != "form" && valj_efb[v].type != "step" && valj_efb[v].type != "html" && valj_efb[v].type != "register" && valj_efb[v].type != "login" && valj_efb[v].type != "subscribe" && valj_efb[v].type != "survey" && valj_efb[v].type != "payment" && valj_efb[v].type != "smartForm") {

            funSetPosElEfb(valj_efb[v].dataId, valj_efb[v].label_position)}

          if (type == 'maps') {
            setTimeout(() => {
              efbCreateMap(valj_efb[v].id_ ,valj_efb[v],false)
            }, (len * 2));
          }

        }

      } catch (error) {
      }
    }

    fub_shwBtns_efb()
  }, len);

}

function obj_resort_row(step) {

  for (let v of valj_efb) {
    if (v.step == step) {
      v.step = step;
      if (v.dataId) {

        if (document.getElementById(v.id_)) document.getElementById(v.id_).dataset.step = step;
      }
    }
  }

  fub_shwBtns_efb()
  if (valj_efb[0].steps == 1) fun_handle_buttons_efb(false);
}
let sampleElpush_efb = (rndm, elementId) => {

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
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], {
        icon: 'bi-save', icon_color: "text-white", button_single_text: efb_var.text.clear,
        button_color: pub_bg_button_color_efb
      })
    } else if (elementId == "yesNo") {
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], { button_1_text: efb_var.text.yes, button_2_text: efb_var.text.no, button_color: pub_bg_button_color_efb })
    } else if (elementId == "maps") {
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], { lat: 49.24803870604257, lng: -123.10512829684463, mark: 1, zoom: 12 });

      setTimeout(() => {
        document.getElementById('maps').draggable = false;
        if (document.getElementById('maps_b')) document.getElementById('maps_b').classList.add('disabled')
      }, valj_efb.length * 5);
    } else if (elementId == "multiselect" || elementId == "payMultiselect") {
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], {
        maxSelect: 2,
        minSelect: 0
      })
    }else if (elementId == "chlCheckBox" || elementId == "chlRadio") {
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], {
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
      const indx =(valj_efb.length) - 1;
      Object.assign(valj_efb[indx], { icon: 'bi-cloud-arrow-up-fill', icon_color:pub_icon_color_efb, button_color: pub_bg_button_color_efb })

    }else if(elementId == "file"){
      const indx =(valj_efb.length) - 1;
      valj_efb[indx].value = 'zip';
      valj_efb[indx].file = 'zip';
    }

  }

}
let optionElpush_efb = async (parent, value, rndm, op, tag) => {
  const u = (url)=>{
    url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
    url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
    url = url.replace(/([/])+/g, '@efb@');
    return url;
   }
  if (typeof tag == "undefined" || (typeof tag=="string" && tag.includes("pay")==false) || tag.includes("img")==true ) {
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, amount: amount_el_efb });

    if(typeof tag != "undefined"  && tag.includes("img")==true){
      const ind= (valj_efb.length) - 1;
      Object.assign(valj_efb[ind], {
        sub_value: efb_var.text.sampleDescription,
        src:u(efb_var.images.head)
      })

      if(tag=='imgRadio'){
        valj_efb[ind].value = efb_var.text.newOption;
      }
    }
  } else {
    valj_efb.push({ id_: rndm, dataId: `${rndm}-id`, parent: parent, type: `option`, value: value, id_op: op, step: step_el_efb, price: 0, amount: amount_el_efb });
  }

}

function create_dargAndDrop_el() {

  const dropZoneEFB = document.getElementById("dropZoneEFB");
  let _efbDropTarget = null;
  let _efbDropPos = 'after';

  const _efbClearIndicators = () => {
    document.querySelectorAll('.efb-drop-indicator').forEach(el => el.remove());
    document.querySelectorAll('.efb-drop-above, .efb-drop-below').forEach(el => {
      el.classList.remove('efb-drop-above', 'efb-drop-below');
    });
  };

  const _efbClosestField = (target) => {
    let el = target;
    while (el && el !== dropZoneEFB) {
      if (el.parentNode === dropZoneEFB && (el.classList.contains('efbField') || el.classList.contains('showBtns') || el.dataset.tag === 'buttonNav' || el.id === 'button_group_efb')) {
        return el;
      }
      if (el.parentNode === dropZoneEFB && el.tagName && (el.tagName.toLowerCase() === 'setion' || el.tagName.toLowerCase() === 'section')) {
        return el;
      }
      el = el.parentNode;
    }
    return null;
  };

  dropZoneEFB.addEventListener("dragover", (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';

    const fieldEl = _efbClosestField(event.target);
    if (!fieldEl || fieldEl.id === 'button_group_efb' || fieldEl.dataset.tag === 'buttonNav') {
      _efbClearIndicators();
      _efbDropTarget = null;
      _efbDropPos = 'after';
      return;
    }

    const rect = fieldEl.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    const pos = event.clientY < midY ? 'before' : 'after';

    if (fieldEl.classList.contains('stepNavEfb') && fieldEl.dataset.step === '1') {
      _efbDropTarget = fieldEl;
      _efbDropPos = 'after';
      _efbClearIndicators();
      fieldEl.classList.add('efb-drop-below');
      return;
    }

    if (fieldEl.classList.contains('stepNavEfb') && pos === 'before') {
      const prevSib = fieldEl.previousElementSibling;
      if (!prevSib || prevSib.classList.contains('stepNavEfb') || !prevSib.classList.contains('efbField')) {
        _efbDropTarget = fieldEl;
        _efbDropPos = 'after';
        _efbClearIndicators();
        fieldEl.classList.add('efb-drop-below');
        return;
      }
    }

    if (_efbDropTarget === fieldEl && _efbDropPos === pos) return;

    _efbClearIndicators();
    _efbDropTarget = fieldEl;
    _efbDropPos = pos;

    if (pos === 'before') {
      fieldEl.classList.add('efb-drop-above');
    } else {
      fieldEl.classList.add('efb-drop-below');
    }
  });

  dropZoneEFB.addEventListener("dragleave", (event) => {
    if (!dropZoneEFB.contains(event.relatedTarget)) {
      _efbClearIndicators();
      _efbDropTarget = null;
    }
  });

  for (const el_efb of document.querySelectorAll(".draggable-efb")) {

    el_efb.addEventListener("dragstart", (event) => {
      event.dataTransfer.setData("text/plain", el_efb.id)
      event.dataTransfer.effectAllowed = 'copy';
    });

    el_efb.addEventListener("click", (event) => {
      if( document.body.classList.contains('mobile')==false && (el_efb.getAttribute('draggable')==true ||el_efb.getAttribute('draggable')=="true") ){
        fun_efb_add_el(el_efb.id);}
      });
  }

  dropZoneEFB.addEventListener("drop", (event) => {
    event.preventDefault();
    _efbClearIndicators();

    const t = event.dataTransfer.getData("text/plain");
    if (t !== "step" && t != null && t != "") {
      let insertAfterEl = null;
      if (_efbDropTarget) {
        if (_efbDropPos === 'before') {
          insertAfterEl = _efbDropTarget.previousElementSibling;
          if (_efbDropTarget.dataset && _efbDropTarget.dataset.step) {
            step_el_efb = Number(_efbDropTarget.dataset.step) || step_el_efb;
          }
        } else {
          insertAfterEl = _efbDropTarget;
          if (_efbDropTarget.dataset && _efbDropTarget.dataset.step) {
            step_el_efb = Number(_efbDropTarget.dataset.step) || step_el_efb;
          }
        }
      }
      const step1El = dropZoneEFB.querySelector('.stepNavEfb[data-step="1"]');
      if (step1El && (insertAfterEl === null || insertAfterEl === step1El.previousElementSibling)) {
        insertAfterEl = step1El;
      }
      fun_efb_add_el(t, insertAfterEl);
    }

    _efbDropTarget = null;
    _efbDropPos = 'after';
  });

}

const add_new_option_efb = (parentsID, idin, value, id_ob, tag) => {

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
  const fun_add = tag != 'r_matrix' ? `onclick="add_option_edit_pro_efb('${parentsID.trim()}','${tag.trim()}',${valj_efb.length})"` : `onclick="add_r_matrix_edit_pro_efb(${parentsID.trim()},${tag.trim()},${valj_efb.length})"`
  document.getElementById('optionListefb').innerHTML +=add_option_edit_admin_efb(0,parentsID,t,idin,tag,id_ob,value,col,s,l_b,ftyp,id ,"");
 const indx = valj_efb.findIndex(x => x.id_ == parentsID);
 if (tag == "radio" && valj_efb[indx].hasOwnProperty('addother') == true && valj_efb[indx].addother == true) {
   const els = valj_efb.filter(obj => { return obj.parent === parentsID });
   document.getElementById(`${parentsID}_options`).innerHTML = '<!--efb.app-->';
   els.forEach(l => {
     document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select(l.id_, l.value, l.id_, 'radio', l.parent);
   });
   document.getElementById(`${parentsID}_options`).innerHTML += add_new_option_view_select("random" + parentsID, efb_var.text.otherTxt, "random" + parentsID, 'radio', parentsID);
 } else if (tag == "table_matrix") {
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
  }

  if (len > 20) {
    sort_obj_efb()
    const p = calPLenEfb(len)
    wating_sort_complate_efb((len * (Math.log(len)) * p))
  } else {
    sort_obj_efb()
  }

  if (state) fub_shwBtns_efb();

}

const sort_obj_el_efb = () => {
  let amount = 1;
  let step = 0;
  let state = false;
  for (const el of document.querySelectorAll(".efbField")) {

    if (el.classList.contains('stepNavEfb')) {
      amount = 1;
      step = el.dataset.step;
    } else {
      if (step == 1) {

        const indx = valj_efb.findIndex(x => x.dataId == el.dataset.id)

        const lastIndx = (valj_efb.length) - 1;

        valj_efb[indx].step = valj_efb[lastIndx].step
        valj_efb[indx].amount = !valj_efb[lastIndx].amount ? 1 : Number(valj_efb[lastIndx].amount) + 1;

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

function show_delete_window_efb(idset,iVJ) {

  let itemLabel = valj_efb[iVJ] && valj_efb[iVJ].hasOwnProperty('type') ? `${valj_efb[iVJ].type} &rsaquo; ${valj_efb[iVJ].name ?? valj_efb[iVJ].value}` : '';
  const body = efb_build_confirm_body('danger', 'bi-trash', efb_var.text.delete, efb_var.text.areYouSureYouWantDeleteItem, itemLabel);
  const is_step = document.getElementById(idset) ? document.getElementById(idset).classList.contains('stepNavEfb') : false;
  show_modal_efb(body, efb_var.text.delete, 'efb bi-trash mx-2', 'deleteBox')
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');
  if (is_step == false) {
   state_modal_show_efb(1);
   confirmBtn.dataset.id =idset.slice(0,-3);
    confirmBtn.addEventListener("click", (e) => {
      document.getElementById(confirmBtn.dataset.id).remove();
      obj_delete_row(idset, false, confirmBtn.dataset.id);
      activeEl_efb = 0;
      state_modal_show_efb(0)
      setTimeout(() => { alert_message_efb(efb_var.text.tDeleted.replace('%s', efb_var.text.field?.replace('%s1','').toLowerCase() || 'element'), '', 4, 'success') }, 300);
    })
  } else if (is_step) {
    const el = document.getElementById(idset);
    if (el.dataset.id != 1) {

      state_modal_show_efb(1)
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
        setTimeout(() => { alert_message_efb(efb_var.text.tDeleted.replace('%s', efb_var.text.step?.replace('%s1','').toLowerCase() || 'step'), '', 4, 'success') }, 300);

      })

    }
  }

}

const obj_delete_row = (dataid, is_step) => {

  let step = 0
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.dataId == dataid) : -1;
  const el_type = valj_efb[foundIndex].type;
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

    } else if (fun_el_select_in_efb(el_type) || fun_el_check_radio_in_efb(el_type)) {
      obj_delete_options(valj_efb[foundIndex].id_)
    } else if (valj_efb[foundIndex].type == 'email' && valj_efb[0].email_to == valj_efb[foundIndex].id_) {
     const vnoti = valj_efb.filter(obj => {
      return obj.noti == 1
    })

     let count =0;
     let id = ''
     if (Object.keys(vnoti).length === 0){
      valj_efb[0].email_to = ''
      valj_efb[0].sendEmail =0
     }else{
       for(let i in vnoti){

         if(vnoti[i].hasOwnProperty('id_') && vnoti[i].id_!= valj_efb[foundIndex].id_ && Number(vnoti[i].noti)==1 ){
            count+=1;
            id = vnoti[i].id_;
          }
       }

     }
     valj_efb[0].sendEmail =count>0 ? 1 : 0;
     valj_efb[0].email_to = id

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
  valj_efb_ = valj_efb.filter(item => item.parent !== parentId);
  valj_efb = valj_efb_;
}
const obj_delete_the_option = (id) => {
  let foundIndex = Object.keys(valj_efb).length > 0 ? valj_efb.findIndex(x => x.id_op == id) : -1;
  if (foundIndex != -1) valj_efb.splice(foundIndex, 1);
}

function show_duplicate_fun(id,fild_name) {
  emsFormBuilder_duplicate(id,'input' ,fild_name)

}

let enableDragSort = (listClass) => {
  const sortLists = document.getElementsByClassName(listClass);
  Array.prototype.map.call(sortLists, (lst) => { enableDragList(lst) });
}

let enableDragList = (lst) => {
  Array.prototype.map.call(lst.children, (item) => { enableDragItem(item) });
}

let enableDragItem = (item) => {
  if (!item.classList.contains('stepNavEfb')) {
    item.setAttribute('draggable', true)
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
        document.getElementById(i.id_).classList.add("drophere")
      }
    }
    status_drag_start = true;
  }

  selectedItem.classList.add('drag-sort-active-efb');
  if (lst === swapItem.parentNode) {
    swapItem = swapItem !== selectedItem.nextSibling && swapItem.dataset == "steps" && swapItem.id != "1" ? swapItem : swapItem.nextSibling;
    const step1El = lst.querySelector('.stepNavEfb[data-step="1"]');
    if (step1El && (swapItem === step1El || (!swapItem && step1El === lst.firstElementChild))) {
      return;
    }
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

}

const sort_obj_efb = () => {
  const len = valj_efb.length;

  let p = calPLenEfb(len)
  setTimeout(() => {
   const  valj_efb_ = valj_efb.sort((a, b) => (Number(a.amount) > Number(b.amount)) ? 1 : ((Number(b.amount) > Number(a.amount)) ? -1 : 0))
     valj_efb= valj_efb_;

  }, ((len * p))
  );

}

const delete_option_efb = (id) => {
  document.getElementById(`${id}-gs`).remove();
  if (document.getElementById(`${id}-v`)) document.getElementById(`${id}-v`).remove();
  const indx = valj_efb.findIndex(x => x.id_op == id)
  let ip = valj_efb.findIndex(x => x.id_ == valj_efb[indx].parent)

  if (indx != -1) {
  if (ip!=-1 && typeof valj_efb[ip].value =="string"){
     if(valj_efb[ip].value == valj_efb[indx].id_)valj_efb[ip].value="";
  }else if(ip!=-1){
    const ix = valj_efb[ip].value.findIndex(x=>x == valj_efb[indx].id_);

    if(ix!=-1) valj_efb[ip].value.splice(ix,1);
  }

   valj_efb.splice(indx, 1); }
}

fun_efb_add_el = (t, insertAfterEl) => {

  const rndm = Math.random().toString(36).substr(2, 9);
  const dropZoneEFB = document.getElementById('dropZoneEFB');

  if (t == "steps" && valj_efb.length < 2) { return; }
  if (valj_efb.length < 2) { dropZoneEFB.innerHTML = "", dropZoneEFB.classList.add('pb') }

  const _insertElHtml = (html, afterEl) => {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const nodes = Array.from(temp.children);
    let lastInserted = null;
    const step1El = dropZoneEFB.querySelector('.stepNavEfb[data-step="1"]');
    if (afterEl === null && step1El) {
      afterEl = step1El;
    }
    if (afterEl && afterEl.parentNode === dropZoneEFB) {
      let ref = afterEl.nextSibling;
      nodes.forEach(node => {
        dropZoneEFB.insertBefore(node, ref);
        lastInserted = node;
      });
    } else if (afterEl === null && dropZoneEFB.firstChild) {
      let ref = dropZoneEFB.firstChild;
      nodes.forEach(node => {
        dropZoneEFB.insertBefore(node, ref);
        lastInserted = node;
      });
    } else {
      nodes.forEach(node => {
        dropZoneEFB.appendChild(node);
        lastInserted = node;
      });
    }
    return lastInserted;
  };

  let lastInsertedNode = null;

  if (t == "address" || t == "name") {

    const olist = [
      { n: 'name', t: "firstName" }, { n: 'name', t: "lastName" },
      { n: 'address', t: "conturyList" }, { n: 'address', t: "stateProvince" } , { n: 'address', t: "cityList" }, { n: 'address', t: "address_line" }  ,{ n: 'address', t: "postalcode" }
    ]
    for (const ob of olist) {
      if (ob.n == t) {
        let el = addNewElement(ob.t, Math.random().toString(36).substr(2, 9), false, false);
        if (insertAfterEl !== undefined && insertAfterEl !== 'APPEND') {
          lastInsertedNode = _insertElHtml(el, insertAfterEl);
          insertAfterEl = lastInsertedNode;
        } else {
          dropZoneEFB.innerHTML += el;
        }
      }
    }

  } else {

    let el = addNewElement(t, rndm, false, false);
    if(el!='null'){
      if (insertAfterEl !== undefined && insertAfterEl !== 'APPEND') {
        lastInsertedNode = _insertElHtml(el, insertAfterEl);
      } else {
        dropZoneEFB.innerHTML += el;
      }
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

  if (insertAfterEl !== undefined && insertAfterEl !== 'APPEND') {
    sort_obj_el_efb_();
  }

  fub_shwBtns_efb();

  if (t == 'maps') {
    const indx = valj_efb.findIndex(x => x.id_ == rndm);
      setTimeout(() => {
        if (typeof efbCreateMap === 'function') {
          efbCreateMap(rndm ,valj_efb[indx],false);
        } else {
        }
      }, 800);

  }
  setTimeout(() => {
    let vl = lastInsertedNode || dropZoneEFB.lastElementChild;
    if (vl && typeof active_element_efb === 'function') active_element_efb(vl);
  }, 80);
}

function active_element_efb(el) {

 if (el.id != activeEl_efb ) {

    if (activeEl_efb == 0) {
      activeEl_efb = document.getElementById(el.id).dataset.id;

    } else {

      document.getElementById(`btnSetting-${activeEl_efb}`).classList.toggle('d-none');

    }
    const ac = document.querySelector(`.field-selected-efb`);
    if (ac) {
     ac.classList.remove('field-selected-efb')
    }
    activeEl_efb = el.dataset.id
    const eld = document.getElementById(`btnSetting-${activeEl_efb}`);
    if (eld.classList.contains('d-none')) eld.classList.remove('d-none');

    document.querySelector(`[data-id="${activeEl_efb}"]`).classList.add('field-selected-efb')

  }
}

function deactive_element_efb() {
  const ac = document.querySelector(`.field-selected-efb`);
  if (ac) {
   ac.classList.remove('field-selected-efb')
  }else{
    return;
  }

  const el = ac.querySelector('.btn-edit-holder');
  if (el) {
    el.classList.add('d-none');
    activeEl_efb = 0;
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
const colSmChangerEfb = (classes, value) => { return /\bcol-sm-\d+/.test(classes) ? classes.replace(/\bcol-sm-\d+/, ` ${value} `) : `${classes} ${value} `; }
const iconChangerEfb = (classes, value) => { return classes.replace(/(\bbi-+[\w\-]+|bXXX)/g, `${value}`); }
const isNumericEfb = (value) => { return /^\d+$/.test(value); }

funBTNAddOnsEFB=(val,v_required)=>{
  let check_ar_pr=(val)=>{
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
  get_val=(f,val)=>{
    let r ='null' ;
    val.forEach(element => {
      if(element.hasOwnProperty('checked') && element.checked==true){
        r!='null' ? r+='>'+element.track+'</br>' : r='>'+element.track+'</br>';
      }
    });
   return r;
  }
  let val =id;

  switch (type) {
    case "addon":
      if (typeof addons_efb !== 'undefined') {
        const addonItem = addons_efb.find(a => a.name === id);
        if (addonItem) {
          let atitle = addonItem.title;
          if (atitle && atitle.trim().split(/\s+/).length === 1) {
            atitle = efb_var.text[atitle] || atitle;
          }
          val = atitle || id;
        } else {
          val = id;
        }
      } else {
        val = id;
      }
      break;
    case "form":
      val=value;
      break;
    case "message":
      val=value;
      if (typeof value == "object") {
        val = get_val('message',value);
        type = 'messagelist';
        for(let i in value){
          if(value[i].hasOwnProperty('checked') && value[i].checked==true && value[i].hasOwnProperty('content')){
            value[i].content='';
          }
        }
      }
      break;
    case 'condlogic':
      val =id;
      break;
    case 'dataset_autofilled':
      val =value;
      type = 'datas';
    break
  }
  const f = (efb_var.text[type] || '').replaceAll('%s1','').replace(/%\d+\$s/g, '').trim();
  const m = f ? `${f} &rsaquo; ${val}` : val;
  const body = efb_build_confirm_body('danger', 'bi-trash', efb_var.text.delete, efb_var.text.areYouSureYouWantDeleteItem, m);
  show_modal_efb(body, efb_var.text.delete, 'efb bi-trash mx-2', 'deleteBox')
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    let _deleteTypeLabel = '';
    if(type=='form'){
    fun_confirm_remove_emsFormBuilder(Number(id))
    _deleteTypeLabel = efb_var.text.form?.replace('%s1','') || 'form';
    }else if(type=='message'){
      fun_confirm_remove_message_emsFormBuilder(Number(id))
      _deleteTypeLabel = efb_var.text.message?.replace('%s1','') || 'message';
    }else if (type =='addon'){
      addons_btn_state_efb(id);
      fun_confirm_remove_addon_emsFormBuilder(id);
    }else if (type =="condlogic"){

      fun_remove_condition_efb(id , value);
      _deleteTypeLabel = efb_var.text.condlogic?.replace('%s1','') || 'condition';
    }else if(type=="messagelist"){

      fun_confirm_remove_all_message_emsFormBuilder(value)
      return;
    }else if(type=="datas"){
      if (typeof fun_confirm_remove_dataset_autofilled_emsFormBuilder === 'function') {
        fun_confirm_remove_dataset_autofilled_emsFormBuilder(id, value);
      } else {
      }
      _deleteTypeLabel = efb_var.text.datas?.replace('%s1','') || 'dataset';
    }
    activeEl_efb = 0;
    state_modal_show_efb(0)
    if (type === 'condlogic') {
      setTimeout(() => { alert_message_efb(efb_var.text.tDeleted.replace('%s', _deleteTypeLabel.toLowerCase()), '', 4, 'success') }, 300);
    }
  })
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function emsFormBuilder_duplicate(id, type,value) {
  const local_id = sessionStorage.getItem('efb_duplicate_id') || '';
  const local_type = sessionStorage.getItem('efb_duplicate_type') || '';

  if (local_id === id && local_type === type && type !='form') {
    return;
  }
  sessionStorage.removeItem('efb_duplicate_id');
  sessionStorage.removeItem('efb_duplicate_type');

  sessionStorage.setItem('efb_duplicate_id', id);
  sessionStorage.setItem('efb_duplicate_type', type);
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
    case 'dataset_autofilled':
      val = value;
  }
  const msg = efb_var.text.ausdup_.replaceAll('%s',val);
  const body = efb_build_confirm_body('info', 'bi-clipboard-plus', efb_var.text.duplicate, msg, '');
  show_modal_efb(body, efb_var.text.duplicate, 'efb bi-clipboard-plus mx-2', 'duplicateBox')
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  state_modal_show_efb(1)
  confirmBtn.addEventListener("click", (e) => {
    fun_confirm_dup_emsFormBuilder(id,type)
    activeEl_efb = 0;
    state_modal_show_efb(0)
  })

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

fun_remove_condition_efb = (no , step_id)=>{

  document.getElementById(no+"-logics-gs").remove();
 const  step_no = valj_efb[0].conditions.findIndex(x=>x.id_ == step_id);
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
  const dialogEl = document.getElementById('settingModalEfb_');
   show =()=>{
   let backdrop = document.querySelector('.efb-modal-backdrop');
   if (!backdrop) {
     backdrop = document.createElement('div');
     backdrop.className = 'efb-modal-backdrop';
     document.body.appendChild(backdrop);
   }
   void backdrop.offsetWidth;
   backdrop.classList.add('show');
   backdrop.onclick = () => state_modal_show_efb(0);

   document.body.classList.add("modal-open")
   el.style.cssText='display: block; padding-right: 0.400024px;';
   void el.offsetWidth;
   el.classList.add('show');
   el.removeAttribute("aria-hidden");
   el.setAttribute("aria-modal","true");

  }
   remove =()=>{
   const backdrop = document.querySelector('.efb-modal-backdrop');
   if (backdrop) {
     backdrop.classList.remove('show');
     setTimeout(() => { if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }, 250);
   }

   document.body.classList.remove("modal-open");
   el.classList.remove('show');
   setTimeout(() => { if (!el.classList.contains('show')) el.style.cssText=''; }, 250);
   el.setAttribute("aria-hidden","true");
   el.removeAttribute("aria-modal");

   if (dialogEl && dialogEl.classList.contains('efb-confirm-dialog')) {
     dialogEl.classList.remove('efb-confirm-dialog');
   }

   if(last_show_modal_efb =='duplicateBox'){
      sessionStorage.removeItem('efb_duplicate_id');
      sessionStorage.removeItem('efb_duplicate_type');
   }
   jQuery('#regTitle').empty().append(loadingShow_efb());
    if (jQuery('#settingModalEfb_').hasClass('save-efb')) {
      jQuery('#settingModalEfb_').removeClass('save-efb')

    }
    if (jQuery('#settingModalEfb_').hasClass('pre-efb')) {
      jQuery('#dropZoneEFB').empty().append(editFormEfb());
      jQuery('#settingModalEfb_').removeClass('pre-efb');

    } else if (jQuery('#settingModalEfb_').hasClass('pre-form-efb')) {
      jQuery('#settingModalEfb_').removeClass('pre-form-efb');
    }
    if (jQuery('#modal-footer-efb')) {
      jQuery('#modal-footer-efb').remove()
    }

    var val = efbLoadingCard('',4);
    if (jQuery(`#settingModalEfb-body`)) jQuery(`#settingModalEfb-body`).html(val)

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
  let txt = efb_var.text.alns.replaceAll('%1$s', `<b>${efb_var.text.easyFormBuilder}</b>`).replaceAll('%2$s', `<a href="https://whitestudio.team/contact-us" target="_blank">`).replaceAll('%3$s', `</a>`);
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
  }

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

}

window.addEventListener("popstate",e=>{

  const getUrlparams = new URLSearchParams(location.search);
  let v =g_page =getUrlparams.get('page') ? sanitize_text_efb(getUrlparams.get('page')) :"";
  if (v==null) return  valNotFound_efb();

  switch(e.state){
    case 'templates':
      if(typeof add_dasboard_emsFormBuilder === 'function') add_dasboard_emsFormBuilder();
    break;
    case 'create':
      if(typeof add_dasboard_emsFormBuilder === 'function' ){ add_dasboard_emsFormBuilder();}
    break;
    case 'sms':
      if(typeof add_sms_emsFormBuilder === 'function' ){add_sms_emsFormBuilder();}
    break;
    case 'panel':
      if(typeof fun_emsFormBuilder_render_view === 'function'){
         fun_emsFormBuilder_render_view(25);
         document.getElementById('sideBoxEfb').classList.remove('show');
         fun_hande_active_page_emsFormBuilder(1);
        }

    break;
    case 'setting':
      if(typeof fun_show_setting__emsFormBuilder === 'function'){
      fun_show_setting__emsFormBuilder();
      fun_backButton_efb(0);
      fun_hande_active_page_emsFormBuilder(2);
      }
      break;
    case 'help':
    if(typeof fun_show_help__emsFormBuilder === 'function'){
      fun_show_help__emsFormBuilder();
      fun_hande_active_page_emsFormBuilder(4);
    }
      break;
    case 'search':
      if(typeof search_trackingcode_fun_efb === 'function'){
        v = localStorage.getItem("search_efb") ? sanitize_text_efb(localStorage.getItem("search_efb")) : null;
        if(v==null){

        }
        search_trackingcode_fun_efb(v)
      }
      break;
    case 'show-message':
      if(typeof fun_get_messages_by_id === 'function'){
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;
      g_page = sanitize_text_efb(getUrlparams.get('form_type'));

      efb_var.msg_id =v;
      form_type_emsFormBuilder = g_page;
      fun_get_messages_by_id(Number(v));
      fun_hande_active_page_emsFormBuilder(1);
      }
    break;
    case "edit-form":
      if(typeof fun_get_form_by_id === 'function'){
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;

      fun_get_form_by_id(Number(v));
      fun_backButton_efb();
      fun_hande_active_page_emsFormBuilder(1);
      }
    break;
    case "edit-dataset":
      if(typeof emsFormBuilder_edit_dataset_efb === 'function'){
      v = getUrlparams.get('id') ? sanitize_text_efb(getUrlparams.get('id')) :null;
        if (v==null)
        emsFormBuilder_edit_dataset_efb(Number(v));
      }
    break;
  }
})

function efb_check_el_pro(el){
  f_b=()=>{
    el.classList.contains('active') ? el.classList.remove('active') :  el.classList.add('active');
  }
  if(efb_var.pro==false || efb_var.pro=="false" || efb_var.pro=="") {
    if(el.type=="button" && el.classList.contains('setting')==false){
      f_b();
      pro_show_efb(efb_var.text.youUseProElements)
    }else if(el.type=="button" && el.classList.contains('setting')==true){
      f_b();
      pro_show_efb(3)
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
      type = "text";
      r=  efb_add_costum_color(t, c ,"" , type)
      pub_txt_button_color_efb=r;
      break;
    case 'button':
    case 'buttonColor':
      type = "btn"
      pub_bg_button_color_efb=  efb_add_costum_color(t, c ,"" , type)
        break;
      }

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

            break;

            case 'message_text_color':
             valj_efb[i][k]=pub_message_text_color_efb;
              break;
            case 'icon_color':
              valj_efb[i][k]=pub_icon_color_efb;
              break;
            case 'btntc':

              pub_txt_button_color_efb;
              break;
            case 'button_color':

              valj_efb[i][k]=pub_bg_button_color_efb
                break;
          }
        }

        editFormEfb();

      }

}
function open_setting_colors_efb(alert){

  jQuery(alert).alert('close')
  if(document.getElementById('sideBoxEfb').classList.contains('show')){
    sideMenuEfb(0);
    return};

  state_view_efb=1;
    document.getElementById('sideMenuConEfb').innerHTML=efbLoadingCard('',5);
    sideMenuEfb(1)
  document.getElementById('sideMenuConEfb').innerHTML=body;
}

msg_colors_from_template = ()=>{
  get_colors =()=>{
    let r =`<!--colors-->`
    for(let i of efb_var.colors){
      r +=`<div class="efb coloritem col-1 m-1" data-color="${i}" style="background:${i};width: 30px;height: 30px;border-radius: 20%;cursor: pointer;" onclick="colors_template_picker_efb(this)"></div>`;
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
  newId = Math.random().toString(36).substr(2, 9);
  const row = valj_efb[0].conditions.findIndex(x=>x.id_ == step_id);
  if (row==-1) return;
  valj_efb[0].conditions[row].condition.push({no:newId, term: 'is',one:"",two:""});

      const ones = selectSmartforOptionsEls(newId ,step_id);
      const twos = optionSmartforOptionsEls(newId,step_id , 0);
      const si = `<p class="efb mx-2 px-0  col-form-label fs-6 text-center">${efb_var.text.ise}</p>`
      const del_btn =`
      <button type="button" class="efb zindex-100  btn btn-delete btn-sm m-1" onclick="emsFormBuilder_delete('${newId}','condlogic' ,'${step_id}')" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"><i class="efb  bi-trash"></i></button>
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
           v =sanitize_text_efb(valj_efb[v].name)
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

    document.getElementById('dupElEFb-'+id).innerHTML=svg_loading_efb('text-light')
    let new_id = Math.random().toString(36).substr(2, 9);
    let index = valj_efb.findIndex(x => x.id_ == id);
    let new_el = {...valj_efb[index]};
    const amount = Number(new_el.amount);
    new_el.name = new_el.name + ' - ' + efb_var.text.copy;
    new_el.amount =amount + 1;
    new_el.id_ = new_id;
    new_el.dataId= new_id+'-id';

    const el_options =[ 'select' ,'paySelect', 'radio' , 'checkbox' , 'multiselect' , 'payMultiselect',
     'table_matrix','cityList','city','stateProvince','statePro' , 'country' ,
      'conturyList' ,'imgRadio','chlRadio','chlCheckBox','payRadio','payCheckbox' ];

    if(el_options.includes(new_el.type)){

      let index_ops = valj_efb.filter(x => x.parent == id);
      let new_el_ops = index_ops.map(x => ({...x}));
      let len_ops = new_el_ops.length;
      new_el.amount = amount + 1;

      for(let i in new_el_ops){
        const new_id_op = Math.random().toString(36).substr(2, 9);
        new_el_ops[i].parent = new_id;
        new_el_ops[i].id_ = new_id_op;
        new_el_ops[i].id_op = new_id_op;
        new_el_ops[i].amount = amount + 1;

        new_el_ops[i].dataId= new_id_op+'-id';
      }
      if(valj_efb.length<index+1){
        valj_efb.push(new_el);
        valj_efb.push(...new_el_ops);

      }else{
        valj_efb.splice(index+1, 0, new_el);
        valj_efb.push(...new_el_ops);
      }

    }else{
      valj_efb.splice(index+1, 0, new_el);
    }
    sort_obj_efb()
    sessionStorage.setItem('valj_efb' , JSON.stringify(valj_efb));
    const len =valj_efb.length;
    let p = calPLenEfb(len)
    const td = len < 50 ? 200 : (len + Math.log(len)) * p
    setTimeout(() => {
      editFormEfb()
    }, td)
  }else if (type=="dataset_autofilled"){
    if(typeof fun_dup_dataset_efb ==='function') {fun_dup_dataset_efb(id,type);}
  }

  localStorage.removeItem('efb_duplicate_id');
  localStorage.removeItem('efb_duplicate_type');
}

colors_from_template = ()=>{
  get_colors =()=>{
    let r =`<!--colors-->`
    for(let i of efb_var.colors){
      r +=`<div class="efb coloritem col-1 m-1" data-color="${i}" style="background:${i};width: 30px;height: 30px;border-radius: 20%;cursor: pointer;" onclick="colors_template_picker_efb(this)"></div>`;
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
        nonce: _efb_nonce_
      };

    $.post(ajaxurl, data, function (res) {
      if (res.data.success == true) {
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
      const form_id = sessionStorage.getItem('form_id') ??  form_ID_emsFormBuilder == 0 ?  null :`[EMS_Form_Builder id=${form_ID_emsFormBuilder}]`;
      if(form_id == null ){
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
  const efbErrorMessageDiv = document.getElementById(`efb-error-message-${efbMapId}`);
  if(efbErrorMessageDiv) efbErrorMessageDiv.innerHTML = '';
  if (lat !== null && long !== null) {
    let efbLatlng = [lat, long];
    maps_efb[efbMapId].map.setView(efbLatlng, zoom);
  } else {
    efbErrorMessageDiv.classList.remove('d-none');
    efbErrorMessageDiv.textContent = 'Latitude and Longitude are required';
  }
}

let heartbeat_status_efb = false
let hold_time_last_beat_efb = Date.now();
let hold_time_last_update_form_beat_efb = Date.now();

store_form_efb =()=>{
   localStorage.setItem('efb_auto_save', 1);
          localStorage.setItem('efb_auto_save_form_id', form_ID_emsFormBuilder);
          localStorage.setItem('efb_auto_save_valj_efb', JSON.stringify(valj_efb));
}
async function heartbeat_Emsfb() {

call_beat = async () => {
  return new Promise((resolve) => {
    jQuery(function ($) {
      const data = {
        action: "heartbeat_Emsfb",
        nonce: _efb_nonce_,
      };

      $.post(ajaxurl, data, function (res) {
        if (res.success === true) {
          hold_time_last_beat_efb = Date.now();
          _efb_nonce_ = res.data.newNonce;
          if (typeof _efb_core_nonce_ !== 'undefined') _efb_core_nonce_ = res.data.newNonce;
          heartbeat_efb_active = false;
          resolve(1);
        } else {
          heartbeat_efb_active = false;
          hold_time_last_beat_efb = Date.now();
          resolve(-1);
        }
      }).fail(function (jqXHR, textStatus, errorThrown) {
        heartbeat_efb_active = false;
        let text = efb_var.text.srvnrsp;
        if (( state_page_efb == 'create' || state_page_efb == 'edit') ){
          efb_var.text.srvnsave;
          store_form_efb();
        }
        alert_message_efb(
          '<i class="efb bi-wifi-off mx-1"></i>' + efb_var.text.error,
          `<p class="efb fs-6">${text}</p>`,
          500,
          "danger"
        );
        resolve(0);
      });
    });
  });
};
  if (heartbeat_efb_active) return;
  heartbeat_efb_active = true;

  data = {};
  const len = typeof valj_efb !== "undefined" ? valj_efb.length :0;

   if(( state_page_efb == 'create' || state_page_efb == 'edit') && len>4){
      const currentTime = Date.now();
      const timeDiff = currentTime - hold_time_last_beat_efb;
      if (timeDiff > 3 * 60 * 1000) {
        const r= await call_beat();
        hold_time_last_update_form_beat_efb = Date.now();
        if(r!=1){
          heartbeat_efb_active = false;
          return;
        }else{
            await saveFormEfb(-1);
            heartbeat_efb_active = false;
            return;
        }

    }
   }
    call_beat();
}

function report_problem_efb(state ,value){
  data = {};
  jQuery(function ($) {
    data = {
      action: "report_problem_Emsfb",
      nonce: _efb_nonce_,
      state: state,
      value: value
    };
    $.post(ajaxurl, data, function (res) {
      if (res.success == true) {

      } else {
      }
    })

  });
}

fun_observer_state_efb=(mutation)=>{
  noti_check =()=>{
    for (const el of document.querySelectorAll(".update-nag, .nf-admin-notice, .notice")) {
      if(!el.classList.contains('efb')) el.remove();
    }
  }
  if (mutation.type === 'childList') {
    noti_check();
  }
  if (mutation.type === 'attributes') {
  }

}

const efb_callback_state = function(mutationsList, observer) {
  for(let mutation of mutationsList) {
          fun_observer_state_efb(mutation);
  }
};

function initMutationObserver() {
    const targetNode_efb = document.getElementById('wpbody-content');
    const config_observer_efb = { childList: true, attributes: true, subtree: true };

    if (targetNode_efb) {
        const observer_efb = new MutationObserver(efb_callback_state);
        observer_efb.observe(targetNode_efb, config_observer_efb);
    } else {
        setTimeout(initMutationObserver, 500);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMutationObserver);
} else {
    initMutationObserver();
}

efbLoadingCard = (bgColor,size=0)=>{
  size = size ? size : 3;
  const w = size<4 ? 'w-50' : 'w-25';
  return `<div class='efb row justify-content-center card-body text-center efb mt-5 pt-3'>
  <div class='efb col-12 col-md-4 col-sm-7 mx-0 my-1 d-flex flex-column align-items-center ${bgColor}'>
      <img class='efb ${w}' src='${efb_var.images.logoGif}'>
      <p class='efb fs-${size} text-darkb mb-0'>${efb_var.text.easyFormBuilder}</h4>
      <p class='efb fs-${size+1} text-dark'>${efb_var.text.pleaseWaiting}</h4>
  </div>
</div> `
}

const efb_url_convert_url = (url)=>{
  url = url.replace(/(http:@efb@)+/g, 'http://');
  url = url.replace(/(https:@efb@)+/g, 'https://');
  url = url.replace(/(@efb@)+/g, '/');

  return url;
 }

 let _efb_idle_heartbeat_ = null;
 let _efb_last_user_activity_ = Date.now();
 const _EFB_HEARTBEAT_INTERVAL_ = 5 * 60 * 1000;
 const _EFB_ACTIVITY_THRESHOLD_ = 3 * 60 * 1000;

 function _efb_record_activity_() {
   _efb_last_user_activity_ = Date.now();
   const sinceLastBeat = Date.now() - hold_time_last_beat_efb;
   if (sinceLastBeat > _EFB_ACTIVITY_THRESHOLD_ && !heartbeat_efb_active) {
     heartbeat_Emsfb();
   }
 }

 function _efb_start_idle_heartbeat_() {
   if (_efb_idle_heartbeat_) return;
   document.addEventListener('click', _efb_record_activity_, { passive: true });
   document.addEventListener('keydown', _efb_record_activity_, { passive: true });
   document.addEventListener('mousemove', (function() {
     let _throttle = 0;
     return function() {
       const now = Date.now();
       if (now - _throttle < 30000) return;
       _throttle = now;
       _efb_last_user_activity_ = now;
     };
   })(), { passive: true });

   _efb_idle_heartbeat_ = setInterval(function() {
     if (heartbeat_efb_active) return;
     heartbeat_Emsfb();
   }, _EFB_HEARTBEAT_INTERVAL_);

   heartbeat_Emsfb();
 }

 if (document.readyState === 'loading') {
   document.addEventListener('DOMContentLoaded', _efb_start_idle_heartbeat_);
 } else {
   setTimeout(_efb_start_idle_heartbeat_, 100);
 }

 function  EventClickHeartBeatEFB(element){
 }

function addClickListenerToElementListEFB(element) {
  if (!element.hasClickListener) {
      let _lastClickTs = 0;

      element.addEventListener("click", function (event) {
              const now = event.timeStamp || performance.now();
              if (now - _lastClickTs < 350) return;

              const closestEcEfb = event.target.closest('.ec-efb');
              if (closestEcEfb !== element) return;

const actionEl = event.target.closest('[data-eventform].ec-efb');
              if (!actionEl) return;
              const classes = actionEl.classList;

              if (classes.contains("ec-efb")) {
                _lastClickTs = now;
                const pro = Number(efb_var.pro) === 1;

                  const dataset = actionEl.dataset;

                  const eventform = ('eventform' in dataset) ? sanitize_text_efb(dataset.eventform) : false;
                  let temp ='';
                  let temp2='';
                  state_page_efb = 'nform';
                  if (eventform) {
                      switch (eventform) {
                          case 'message':
                            temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_messages(temp2);
                              break;
                          case 'openMessage':
                            temp = Number(dataset.msgid);
                            temp2 = Number(dataset.msgstate);
                            fun_open_message_emsFormBuilder(temp,temp2);
                            break;
                          case 'edit':
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_get_edit_form(temp2);
                              state_page_efb = 'edit';
                              localStorage.setItem('efb_auto_save', 0);
                              break;
                          case 'delete':
                              temp = sanitize_text_efb(dataset.formname);
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_delete(temp2, 'form', temp);
                              break;
                          case 'duplicate':
                              temp = sanitize_text_efb(dataset.formname);
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_duplicate(temp2, 'form', temp);
                              break;
                          case 'generateCSV':
                              pro ? generat_csv_emsFormBuilder() : pro_show_efb(3);
                              break;
                          case 'generateChart':
                              convert_to_dataset_emsFormBuilder();
                              break;
                          case 'deleteSelectedRow':
                              event_selected_row_emsFormBuilder('delete');
                              break;
                          case 'readSelectedRow':
                              pro ? event_selected_row_emsFormBuilder('read') :pro_show_efb(3);
                              break;
                          case 'setting':
                              fun_show_content_page_emsFormBuilder('setting');
                              const efbNotice = document.getElementById('notice-email-efb');
                              if(efbNotice) {
                                  efbNotice.style.display = 'flex';
                              }
                              break;
                          case 'help':
                              fun_show_content_page_emsFormBuilder('help');
                              break;
                          case 'forms':
                              fun_show_content_page_emsFormBuilder('forms');
                              break;
                          case 'searchCC':
                              fun_find_track_emsFormBuilder();
                              break;
                          case 'sideMenuEfb':
                              sideMenuEfb(0);
                              break;
                          case 'sideMenuEfbSave':
                              break;
                          case 'links':
                            temp = sanitize_text_efb(dataset.linkname);
                              Link_emsFormBuilder(temp);
                          break;
                          case 'cachePlugin':
                          Link_emsFormBuilder('cachePlugin');
                          break;
                          case 'deleteMsg':
                            temp = sanitize_text_efb(dataset.msgid);
                            temp2 = sanitize_text_efb(dataset.trackid);
                            pro ? emsFormBuilder_delete(temp ,'message',temp2) : pro_show_efb(3);

                          break;
                          default:
                      }
                  }
              }
      });

      element.hasClickListener = true;
  }
}

      function observeExistingElementsListEFB() {
        const els = document.querySelectorAll(".ec-efb");
        els.forEach(addClickListenerToElementListEFB);
      }

      const observer_listefb = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {

                    const els = node.querySelectorAll(".ec-efb, .btn, .elEdit, .btn-toggle, .ec-efb ");
                    els.forEach(addClickListenerToElementListEFB);

                    const els_efb = node.querySelectorAll(".efb")
                    els_efb.forEach(EventClickHeartBeatEFB)
                }
            });
        });
      });

      const initListObserver = () => {
          if (document.body) {
              observer_listefb.observe(document.body, {
                childList: true,
                subtree: true
              });
              observeExistingElementsListEFB();
          } else {
              document.addEventListener('DOMContentLoaded', function() {
                  if (document.body) {
                      observer_listefb.observe(document.body, {
                        childList: true,
                        subtree: true
                      });
                      observeExistingElementsListEFB();
                  }
              });
          }
      };

      if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', initListObserver);
      } else {
          setTimeout(initListObserver, 0);
      }

function restore_auto_save_efb(){
  const auto_save = Number(localStorage.getItem('efb_auto_save')) === 1;
  if(auto_save==false) return;

  const valj_efb_str = localStorage.getItem('efb_auto_save_valj_efb');
  if(valj_efb_str!=null && typeof efb_var !== 'undefined' && efb_var.text){
    setTimeout(() => {
      const context =`<div class="text-center text-darkb efb"><div class=" fs-4 efb"></div><p class="fs-4 efb">${efb_var.text.rasfmb}</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
      <a class="btn btn-darkb text-white efb px-4" id="restore_auto_save_efb_btn" onclick="restore_auto_save_efb_btn()">
        ${efb_var.text.yes}
      </a>
      <a class="btn btn-outline-danger efb px-4" id="restore_auto_no_efb_btn" onclick="restore_auto_no_efb_btn()">
        ${efb_var.text.no}
      </a>
    </div>
        </div>`;
      show_modal_efb(context,efb_var.text.warning, ``, 'saveBox');
      state_modal_show_efb(1)
    }, 1000);
  } else {
    localStorage.setItem('efb_auto_save', 0);
  }
}

 async function restore_auto_save_efb_btn(){
    state_page_Efb = 'edit';
   if(window.location.href.includes('page=Emsfb_create')){
    state_page_Efb = 'create';
   }
   localStorage.setItem('efb_auto_save', 0);
    const id = Number(localStorage.getItem('efb_auto_save_form_id'));
    const valj_efb_str = localStorage.getItem('efb_auto_save_valj_efb');
    let valj_efb = [];
    try {
      valj_efb = JSON.parse(valj_efb_str);
      sessionStorage.setItem('valj_efb', valj_efb_str);
      valueJson_ws_p=valj_efb;
      formName_Efb = valj_efb[0].formName;
      form_type_emsFormBuilder=valj_efb[0].type
      form_ID_emsFormBuilder = id;
      if (id !== 0) {state_check_ws_p =0;}

      creator_form_builder_Efb();
      setTimeout(() => { editFormEfb() }, 200)
      state_modal_show_efb(0)
    } catch (error) {
      return;
    }

    localStorage.removeItem('efb_auto_save_valj_efb');
    localStorage.removeItem('efb_auto_save_form_id');

}

  function restore_auto_no_efb_btn(){
    localStorage.setItem('efb_auto_save', 0);
    localStorage.removeItem('efb_auto_save_valj_efb');
    localStorage.removeItem('efb_auto_save_form_id');
    state_modal_show_efb(0)
  }

function fub_shwBtns_efb() {
  for (const el of document.querySelectorAll(".showBtns")) {

    if (!el._efbClickBound) {
      el.addEventListener("click", (e) => {
        active_element_efb(el);
      });
      el._efbClickBound = true;
    }

    if (!el._efbHoverBound) {
      el.addEventListener("mouseenter", (e) => {
        const btnHolder = el.querySelector('.btn-edit-holder');
        if (btnHolder && btnHolder.classList.contains('d-none')) {
          btnHolder.classList.remove('d-none');
          btnHolder.classList.add('efb-hover-visible');
        }
        el.classList.add('efb-field-hover');
      });
      el.addEventListener("mouseleave", (e) => {
        const btnHolder = el.querySelector('.btn-edit-holder');
        if (btnHolder && btnHolder.classList.contains('efb-hover-visible')) {
          const dataId = el.dataset.id || '';
          if (typeof activeEl_efb !== 'undefined' && activeEl_efb !== dataId) {
            btnHolder.classList.add('d-none');
          }
          btnHolder.classList.remove('efb-hover-visible');
        }
        el.classList.remove('efb-field-hover');
      });
      el._efbHoverBound = true;
    }

    if (!el._efbTouchBound) {
      el.addEventListener("touchend", (e) => {
        if (e.cancelable) e.preventDefault();
        active_element_efb(el);
      }, { passive: false });
      el._efbTouchBound = true;
    }
  }
}

function pro_show_efb(state) {
  let message = state;
  let buttons = '';

  if (typeof state != "string") {
    if (state == 1) {
      message = efb_var.text.proUnlockMsg;
    } else if (state == 2) {
      message = efb_var.text.ifYouNeedCreateMoreThan2Steps;
    } else if (state == 3) {
      message = efb_var.text.thisFeatureAvailableFreePlusPro;
    }
  }

  if (state == 3) {
    buttons = `
    <div class="efb row">
      <div class="efb  col-md-6  text-center">
        <button class="efb btn mt-3 efb btn-r h-d-efb btn-outline-info "  onclick ="open_whiteStudio_efb('free_plus_guide')">${efb_var.text.freePlusActivation || 'Free Plus Activation'} </button>
      </div>
      <div class="efb  text-center col-md-6">
        <button type="button" class="efb btn btn-r efb btn-primary efb-btn-lg mt-3 mb-3" onclick ="open_whiteStudio_efb('pro')">
          <i class="efb  bi-gem mx-1 pro"></i>
          ${efb_var.text.activateProVersion}
        </button>
      </div>
    </div>`;
  } else {
    buttons = `
    <div class="efb row">
      <div class="efb  col-md-6  text-center">
        <button class="efb btn mt-3 efb btn-r h-d-efb btn-outline-pink "  onclick ="open_whiteStudio_efb('pro')">${efb_var.text.priceyr.replace('NN',pro_price_efb)} </button>
      </div>
      <div class="efb  text-center col-md-6">
        <button type="button" class="efb btn btn-r efb btn-primary efb-btn-lg mt-3 mb-3" onclick ="open_whiteStudio_efb('pro')">
          <i class="efb  bi-gem mx-1 pro"></i>
          ${efb_var.text.activateProVersion}
        </button>
      </div>
    </div>`;
  }

  const body = `<div class="efb  pro-version-efb-modal"><i class="efb  bi-gem"></i></div>
  <h5 class="efb  txt-center">${message}</h5>
  ${buttons}`

  show_modal_efb(body, efb_var.text.proVersion, '', 'proBpx')
  state_modal_show_efb(1)
}

function move_show_efb() {
  const body = `<div class="efb  pro-version-efb-modal"><i class="efb "></i></div>
  <div class="efb  text-center" dir="rtl">
   <img src="${efb_var.images.movebtn}" class="efb  img-fluid" alt="">
  </div>`
  show_modal_efb(body, '','bi-arrows-move', 'saveBox')
  state_modal_show_efb(1)
}

const add_new_option_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  let op = `<!-- option --> 2`
  let price ="<!--efb.app-->";
  let qst ='<!--efb.app-->';
  let tagtype= tag;
  const $pv = 0;
  const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
  if (tag.includes("pay")!=false){
    tagtype = tag.slice(3);
    price =`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${idin}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>`;
  }
   if(tag.includes("chl")!=false){
    tagtype = tag.slice(3);
    qst =`<input type="text" class="efb ${valj_efb[indxP].el_text_color}  ${valj_efb[indxP].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${idin}" data-vid="" id="${idin}_chl" placeholder="${valj_efb[indxP].pholder_chl_value}"  disabled>`
  }
  if (fun_el_select_in_efb(tag)) {
    op = `<option value="${value}" id="${idin}" data-id="${idin}-id"  data-op="${idin}" class="efb ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height}">${value}</option>`
  } else {
    let col = valj_efb[indxP].hasOwnProperty('op_style') && Number(valj_efb[indxP].op_style )!=1 ? 'col-md-'+(12/Number(valj_efb[indxP].op_style )) :''
    op = `<div class="efb  form-check ${col}" data-parent="${parentsID}"  id="${id_ob}-v">
    <input class="efb  form-check-input ${valj_efb[indxP].el_text_size}" type="${tagtype}" name="${parentsID}"  value="${value}" id="${idin}" data-id="${idin}-id" data-op="${idin}" disabled>
    <label class="efb ${valj_efb[indxP].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[indxP].el_text_color} ${valj_efb[indxP].label_text_size} ${valj_efb[indxP].el_height} hStyleOpEfb " id="${idin}_lab" for="${idin}">${value}</label>
    ${qst}
    ${price}
    </div>`
  }
  return op;
}

function addNewElement(elementId, rndm, editState, previewSate) {
  let pos = [``, ``, ``, ``]
  const shwBtn = previewSate != true ? 'showBtns' : '';
  let indexVJ = editState != false ? valj_efb.findIndex(x => x.id_ == rndm) : 0;
  if (previewSate == true && elementId != "html" && elementId != "register" && elementId != "login" && elementId != "subscribe" && elementId != "survey") pos = get_position_col_el(valj_efb[indexVJ].dataId, false)
  amount_el_efb = editState == false ?  Number(amount_el_efb) + 1 : valj_efb[indexVJ].amount;
  element_name = editState == false ? elementId : valj_efb[indexVJ].name;
  let optn = '<!-- options -->';
  step_el_efb >= 1 && editState == false && elementId == "steps" ? step_el_efb = Number(step_el_efb) + 1 : 0;
  if (editState != false && previewSate != true) {
    step_el_efb = valj_efb[0].steps;
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    add_buttons_zone_efb(t, 'dropZoneEFB')
  }
  let pay = previewSate == true ? 'payefb' : 'pay';
  newElement = ``;
  if (previewSate == false) Object.entries(valj_efb[indexVJ]).forEach(([key, val]) => {
    fun_addStyle_costumize_efb(val.toString(), key, indexVJ); })
  if (step_el_efb == 1) {
    let state = false;
    if (editState == false) {
      state = true;
    }
    if (elementId != 'steps') {
      if (editState == false && valj_efb.length < 2) {
        valj_efb.push({
          id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
          id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: "",
          label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb',
          el_text_color: 'text-labelEfb', message_text_color:pub_message_text_color_efb, icon_color: pub_icon_color_efb, icon: 'bi-ui-checks-grid', visible: 1
        });
        indexVJ = valj_efb.length - 1;
        const sort = indexVJ<=1 ? 'unsortable'  : 'sortable';
        newElement = `
        <section class="efb  ${sort} list   row my-2  efbField stepNavEfb stepNo" data-step="${step_el_efb}" data-amount="${step_el_efb}" data-id="${step_el_efb}" id="${step_el_efb}" data-tag="steps">
            <div class="efb  row my-2  ${shwBtn} efbField stepNavEfb" data-step="${step_el_efb}" data-amount="${step_el_efb}" data-id="${step_el_efb}" id="${step_el_efb}" data-tag="steps">
            <h2 class="efb  col-10 mx-2 my-0"><i class="efb  ${valj_efb[indexVJ].icon} ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'}  ${valj_efb[indexVJ].icon_color}"
                    id="${step_el_efb}_icon"></i> <span id="${step_el_efb}_lab" class="efb   text-darkb  ${valj_efb[indexVJ].label_text_size != "default" ? valj_efb[indexVJ].label_text_size : 'fs-5'} ">${valj_efb[indexVJ].name}</span></span></h2>
            <small id="${step_el_efb}-des" class="efb  form-text ${valj_efb[indexVJ].message_text_color} border-bottom px-4   ">${valj_efb[indexVJ].message}</small>
            <div class="efb  col-sm-10">
                <div class="efb  btn-edit-holder btnSetting d-none " id="btnSetting-${step_el_efb}">
                    <button type="button" class="efb  btn  btn-edit  btn-sm BtnSideEfb" id="settingElEFb"
                        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
                        onclick="show_setting_window_efb('${step_el_efb}')">
                        <div class="icon-container efb"><i class="efb   bi-gear-wide-connected text-success" id="efbSetting"></i></div>
                    </button>
                </div>
            </div>
        </div>
        </section>`;
      }
      const t = valj_efb[0].steps == 1 ? 0 : 1;
      if (previewSate != true) editState == false ? add_buttons_zone_efb(0, 'dropZoneEFB') : add_buttons_zone_efb(t, 'dropZoneEFB')
    } else if (elementId == "steps" && step_el_efb == 1 && state == false && editState == false) {
      valj_efb.push({
        id_: `${step_el_efb}`, type: 'step', dataId: `${step_el_efb}`, classes: '',
        id: `${step_el_efb}`, name: efb_var.text[formName_Efb].toUpperCase(), icon: '', step: step_el_efb, amount: amount_el_efb, EfbVersion: 2, message: "",
        label_text_size: 'fs-5', el_text_size: 'fs-5', file: 'document', label_text_color: 'text-darkb',
        el_text_color: 'text-dark', message_text_color: pub_message_text_color_efb, icon_color: pub_icon_color_efb, icon: 'bi-ui-checks-grid', visible: 1
      });
      editState == false && valj_efb.length > 2 ? step_el_efb= Number(step_el_efb) +1 : 0;
    }
    amount_el_efb =Number(amount_el_efb)+1;
  }
  if (editState == false && ((elementId != "steps" && step_el_efb >= 0) || (elementId == "steps" && step_el_efb >= 0)) && ((pro_efb == false && step_el_efb < 3) || pro_efb == true)) { sampleElpush_efb(rndm, elementId); }
  let iVJ = editState == false ? valj_efb.length - 1 : valj_efb.findIndex(x => x.id_ == rndm);
  let dataTag = 'text'
  const desc = `<small id="${rndm}-des" class="efb  form-text d-flex  fs-7 col-sm-12 efb ${previewSate == true && pos[1] == 'col-md-4' || valj_efb[iVJ].message_align != "justify-content-start" ? `` : `mx-4`}  ${valj_efb[iVJ].message_align}  ${valj_efb[iVJ].message_text_color} ${ valj_efb[iVJ].hasOwnProperty('message_text_size') ? valj_efb[iVJ].message_text_size : ''} ">${valj_efb[iVJ].message} </small> `;
  const  label = `<label for="${rndm}_" class="efb mx-0 px-0 pt-2 pb-1  ${previewSate == true ? pos[2] :"col-md-12"} col-sm-12 col-form-label ${valj_efb[iVJ].hasOwnProperty('hflabel') && Number(valj_efb[iVJ].hflabel)==1 ? 'd-none' :''} ${valj_efb[iVJ].label_text_color} ${valj_efb[iVJ].label_align} ${valj_efb[iVJ].label_text_size != "default" ? valj_efb[iVJ].label_text_size : ''} " id="${rndm}_labG" ><span id="${rndm}_lab" class="efb  ${valj_efb[iVJ].label_text_size}">${valj_efb[iVJ].name}</span><span class="efb  mx-1 text-danger" id="${rndm}_req" role='none'>${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? '*' : ''}</span></label>`
  const ttip = `<small id="${rndm}_-message" class="efb py-1 fs-7 tx ttiptext px-2" style="display:none"> ! </small>`
  const rndm_1 = Math.random().toString(36).substr(2, 9);
  const rndm_2 = Math.random().toString(36).substr(2, 9);
  const op_3 = Math.random().toString(36).substr(2, 9);
  const op_4 = Math.random().toString(36).substr(2, 9);
  const op_5 = Math.random().toString(36).substr(2, 9);
  let ui = ''
  const vtype = (elementId == "payCheckbox" || elementId == "payRadio" || elementId == "paySelect" || elementId == "payMultiselect" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio" || elementId=='trmCheckbox') ? elementId.slice(3).toLowerCase() : elementId;
  let classes = ''
  const corner = valj_efb[iVJ].hasOwnProperty('corner') ? valj_efb[iVJ].corner: 'efb-square';
  let minlen,maxlen,temp,col;
  let hidden =  previewSate == true  && valj_efb[iVJ].hasOwnProperty('hidden') &&  valj_efb[iVJ].hidden==1 ? 'd-none' : ''
  let disabled = valj_efb[iVJ].hasOwnProperty('disabled') &&  valj_efb[iVJ].disabled==1? 'disabled' : ''
  let ps =  elementId == "html" ? 'col-md-12' : 'col-md-12'
  if(pos[3]==""){
     if( elementId=="firstName" || elementId=="lastName" ){ ps = 'col-md-6';
     }
  }
  pos[3] = pos[3]=="" ? 'col-md-12' :  pos[3];
  genertate_ops_select_Efb =async()=>{
    const op_1 = Math.random().toString(36).substr(2, 9);
    const op_2 = Math.random().toString(36).substr(2, 9);
    const pv=0;
    const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
    temp = '1';
    tp = '2';
    if(elementId=="imgRadio"){
     temp = '';
    tp = '';
    }
     optionElpush_efb(rndm, `${efb_var.text.newOption} ${temp}`, op_1, op_1 ,dataTag);
     optionElpush_efb(rndm, `${efb_var.text.newOption} ${tp}`, op_2, op_2 ,dataTag);

    optn = `
   <div class="efb  form-check  ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
   <input class="efb  emsFormBuilder_v form-check-input ${pay} ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
   ${elementId!='imgRadio' ?`<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${op_1}_lab">${efb_var.text.newOption} 1</label>` : fun_imgRadio_efb(op_1,'urlLin',valj_efb[iVJ])}
   ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
   ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_1}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
   </div>
   <div class="efb  form-check ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-parent="${rndm}" data-id="${op_2}" id="${op_2}-v">
       <input class="efb  emsFormBuilder_v form-check-input ${pay}  ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
       ${elementId!='imgRadio' ?  `<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>` : fun_imgRadio_efb(op_2,'urlLin',valj_efb[iVJ])}
       ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
       ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_2}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
   </div>`

  }
  const aire_describedby = valj_efb[iVJ].message!='' ? `aria-describedby="${valj_efb[iVJ].id_}-des"` : "";
  switch (elementId) {
    case 'email':
    case 'text':
    case 'password':
    case 'tel':
    case 'url':
    case "date":
    case 'color':
    case 'number':
    case 'firstName':
    case 'lastName':
    case 'datetime-local':
    case 'postalcode':
    case 'address_line':
      const type = elementId == "firstName" || elementId == "lastName" || elementId == "postalcode" || elementId == "address_line" ? 'text' : elementId;
      const autocomplete = elementId == "email" ? 'email' : elementId == "tel" ? 'tel' : elementId == "url" ? 'url' : elementId == "password" ? 'current-password' : elementId == "firstName" ? 'given-name' : elementId == "lastName" ? 'family-name' : elementId == "postalcode" ? 'postal-code' : elementId == "address_line" ? 'street-address' : 'off';
      const placeholder =  elementId != 'color'  && elementId != 'range' &&  elementId != 'password' &&  elementId != 'date' ? `placeholder="${valj_efb[iVJ].placeholder}"` : '';

      if(elementId != 'date'){
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
        maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :0;
        minlen = Number(minlen)!=0  ? `minlength="${minlen}"`:``;
      }else{
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen')  ? valj_efb[iVJ].mlen :'';
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :'';
          const today = new Date();
          const dd = String(today.getDate()).padStart(2, '0');
          const mm = String(today.getMonth() + 1).padStart(2, '0');
          const yyyy = today.getFullYear();
        if(maxlen==1) {
          maxlen = `${yyyy}-${mm}-${dd}`;
        }
        if (minlen==1) {
          minlen = `${yyyy}-${mm}-${dd}`;
        }

        maxlen = Number(maxlen)!=0 && maxlen!='' ? `max="${maxlen}"`:``;
        minlen = Number(minlen)!=0 && minlen !='' ? `min="${minlen}"`:``;
      }

      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="${type}"   class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-id="${rndm}-el" data-vid='${rndm}' data-css="${rndm}" id="${rndm}_" ${placeholder}  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} autocomplete="${autocomplete}"  ${maxlen} ${minlen} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      break;
    case 'pdate':
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb pdpF2 input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}'  id="${rndm}_"   ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'ardate':
      classes = elementId != 'range' ? `form-control ${valj_efb[iVJ].el_border_color} ` : 'form-range';
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        <input type="text"   class="efb hijri-picker input-efb px-2 mb-0 emsFormBuilder_v w-100 ${classes}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_"   ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
        ${desc}`
      dataTag = elementId;
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      break;
    case 'range':
         maxlen = valj_efb[iVJ].hasOwnProperty('mlen') ? valj_efb[iVJ].mlen :100;
        minlen = valj_efb[iVJ].hasOwnProperty('milen')  ? valj_efb[iVJ].milen :0;
        temp =valj_efb[iVJ].value>0 ? valj_efb[iVJ].value :Math.round((Number(valj_efb[iVJ].mlen)+Number(valj_efb[iVJ].milen))/2) ;
        ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show  "  id='${rndm}-f'>
          ${ttip}
          <div class="efb slider m-0 p-2 ${valj_efb[iVJ].el_height}  ${valj_efb[iVJ].el_text_color} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id='${rndm}-range'>
          <input type="${elementId}"  class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField" data-id="${rndm}-el" data-vid='${rndm}' id="${rndm}_" oninput="fun_show_val_range_efb('${rndm}')"  ${valj_efb[iVJ].value.length > 0 ? `value ="${temp}"` : ''} min="${minlen}" max="${maxlen}" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          <p id="${rndm}_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">${temp||50}</p>
          </div>
          ${desc}`
        dataTag = elementId;
        break;
    case 'maps':
      ui = `
      ${label}
      <!-- ${rndm}-map -->
      ${ttip}
       ${typeof maps_os_pro_efb =="function" ? maps_os_pro_efb(previewSate, pos , rndm,iVJ) : public_pro_message()}
        ${desc}`
      dataTag = elementId;
      break;
    case 'file':
      ui = `
       ${label}
        <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
          ${ttip}
          <input type="${elementId}" class="efb  input-efb px-2 py-1 emsFormBuilder_v w-100  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}    form-control efb efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_"  aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          ${desc}`
      dataTag = elementId;
      break;
    case "textarea":
      minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;
      minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
      ui = `
                ${label}
                <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f' >
                ${ttip}
                <textarea  id="${rndm}_"  placeholder="${valj_efb[iVJ].placeholder}"  class="efb  px-2 input-efb emsFormBuilder_v form-control w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].el_border_color}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-vid='${rndm}' data-id="${rndm}-el"  value="${valj_efb[iVJ].value}" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} rows="5" ${previewSate != true ? 'readonly' : ''} ${disabled} ${minlen}>${text_nr_efb(valj_efb[iVJ].value,0)}</textarea>
                ${desc}
            `
      dataTag = "textarea";
      break;
    case "mobile":
      temp = typeof create_intlTelInput_efb =="function" ?  create_intlTelInput_efb(rndm,iVJ,previewSate,corner) : public_pro_message() ;
      ui = `
                ${label}
                <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
                ${ttip}
                ${temp}
                ${desc}
            `
      dataTag = "textarea";
      break;
    case 'dadfile':
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id='${rndm}-f'>
      ${desc}
      ${ttip}
      ${typeof dadfile_el_pro_efb =="function" ?  dadfile_el_pro_efb(previewSate, rndm,iVJ) : public_pro_message()}
      `
      dataTag = elementId;
      break;
    case 'checkbox':
    case 'radio':
    case 'payCheckbox':
    case 'payRadio':
    case 'chlCheckBox':
    case 'chlRadio':
    case 'imgRadio':
    case 'trmCheckbox':
      dataTag = elementId;
       col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style )!=1 ? 'col-md-'+(12/Number(valj_efb[iVJ].op_style )) :''
      if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox" || elementId == "imgRadio" || elementId == "trmCheckbox") pay = "";
      temp = elementId=="imgRadio" ? 'col-md-4 mx-0 px-2' :'';
      if (editState != false) {
        let tp = dataTag.toLowerCase();
        let parent = valj_efb[iVJ]
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
        const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
        for (const i of optns_obj) {
          let checked ="";
          if((tp.includes("radio")==true ||( tp.includes("select")==true &&  tp.includes("multi")==false))  && ( parent.value == i.id_ || (i.hasOwnProperty("id_old") && parent.value == i.id_old) )  ){ checked="checked";
          }else if((tp.includes("multi")==true || tp.includes("checkbox")==true) &&  typeof parent.value!="string" &&  parent.value.findIndex(x=>x==i.id_ || x==i.id_old)!=-1 ){checked="checked"}
          const prc = i.hasOwnProperty('price') ? Number(i.price):0;
          optn += `<div class="efb  form-check ${col} ${elementId} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)} mt-1" data-css="${rndm}" data-parent="${i.parent}" data-id="${i.id_}" id="${i.id_}-v">
          <input class="efb  form-check-input emsFormBuilder_v ${pay}  ${valj_efb[iVJ].el_text_size} " data-tag="${dataTag}" data-type="${vtype}" data-vid='${rndm}' type="${vtype}" name="${i.parent}" value="${i.value}" id="${i.id_}" data-id="${i.id_}-id" data-op="${i.id_}"${previewSate != true ? 'readonly' : ''} ${disabled} ${checked}>
          ${elementId!='imgRadio'?` <label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${i.id_}_lab" for="${i.id_}">${fun_get_links_from_string_Efb(i.value,true)}</label>`: fun_imgRadio_efb(i.id_,i.src,i)}
          ${elementId.includes('chl')!=false?`<input type="text" class="efb ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${i.id_}" data-type="${dataTag}" data-vid="" id="${i.id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}"   disabled>` :''}
          ${ pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${i.id_}-price" class="efb efb-crrncy">${prc.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
          </div>`
        }
      } else {
       const op_1 = Math.random().toString(36).substr(2, 9);
       const op_2 = Math.random().toString(36).substr(2, 9);
       const pv=0;
       const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';

      let t1= '1';
      let t2 = '2';
      if(elementId=="imgRadio"){
        t1 = '';
        t2 = '';
      }
       let opt_label = `${efb_var.text.newOption} ${t1}`;
       if(elementId!='trmCheckbox'){
        optionElpush_efb(rndm, `${opt_label}`, op_1, op_1 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.newOption} ${t2}`, op_2, op_2 ,dataTag);
      }else{
        opt_label = efb_var.text.trmcn;
        opt_label = opt_label.replace('%1$s', "[");
        opt_label = opt_label.replace('%2$s', "](https://whitestudio.team/privacy-policy-terms)");
        optionElpush_efb(rndm, `${opt_label}`, op_1, op_1 ,dataTag);
        opt_label = fun_get_links_from_string_Efb(opt_label,true);
      }
       optn = `
      <div class="efb  form-check  ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
      <input class="efb  emsFormBuilder_v form-check-input ${pay} ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_1}" data-id="${op_1}-id" data-op="${op_1}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
      ${elementId!='imgRadio' ?`<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb " id="${op_1}_lab">${opt_label}</label>` : fun_imgRadio_efb(op_1,'urlLin',valj_efb[iVJ] ,false)}
      ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
      ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_1}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
      </div>
     `
     if(elementId!="trmCheckbox"){
      optn += ` <div class="efb  form-check ${elementId}  ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-parent="${rndm}" data-id="${op_2}" id="${op_2}-v">
      <input class="efb  emsFormBuilder_v form-check-input ${pay}  ${valj_efb[iVJ].el_text_size} " type="${vtype}" name="${valj_efb[iVJ].id_}" value="${vtype}" id="${op_2}" data-id="${op_2}-id" data-op="${op_2}" ${previewSate != true ? 'readonly' : ''} ${disabled}>
      ${elementId!='imgRadio' ?  `<label class="efb ${valj_efb[iVJ].hasOwnProperty('pholder_chl_value') ? 'col-8' :''}   ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size} hStyleOpEfb "  id="${op_2}_lab">${efb_var.text.newOption} 2</label>` : fun_imgRadio_efb(op_2,'urlLin',valj_efb[iVJ],false)}
      ${elementId.includes('chl')!=false?`<input type="text" class="efb col ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} checklist col-2 hStyleOpEfb emsFormBuilder_v border-d" data-id="${valj_efb[iVJ].id_}" data-vid="" id="${valj_efb[iVJ].id_}_chl" placeholder="${valj_efb[iVJ].pholder_chl_value}" disabled>` :''}
      ${pay.length>2 ?`<span  class="efb col fw-bold  text-labelEfb h-d-efb hStyleOpEfb d-flex justify-content-end"><span id="${op_2}-price" class="efb efb-crrncy">${pv.toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></span>` :''}
      </div> `;
     }

      }
      temp = elementId=="imgRadio" ?  "row  justify-content-center" :"";
      ui = `
      <!-- checkbox -->
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show"   data-id="${rndm}-el" id='${rndm}-f'>
      ${ttip}
      <div class="efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${col!=''? 'row col-md-12' :''} ${temp} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" ${aire_describedby} id="${rndm}_options">
        ${optn}
        </div>
        <div class="efb  mb-3">${desc}</div>
        <!-- end checkbox -->
        `
      break;
    case 'switch':
      valj_efb[iVJ].on =  valj_efb[iVJ].hasOwnProperty('on') ? valj_efb[iVJ].on :efb_var.text.on
      valj_efb[iVJ].off =  valj_efb[iVJ].hasOwnProperty('off') ? valj_efb[iVJ].off :efb_var.text.off
      ui = `
      ${label}
      ${ttip}
      <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f" ${aire_describedby}>
      <label class="efb fs-6" id="${rndm}_off">${valj_efb[iVJ].off}</label>
      <button type="button"  data-state="off" class="efb btn   ${valj_efb[iVJ].el_height}  btn-toggle efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" data-toggle="button" aria-pressed="false" data-vid='${rndm}' onclick="fun_switch_efb(this)" data-id="${rndm}-el" id="${rndm}_" ${previewSate != true ? 'disabled' : ''} ${disabled}>
        <div class="efb handle"></div>
      </button>
      <label class="efb fs-6" id="${rndm}_on">${valj_efb[iVJ].on}</label>
   <!--   <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show" id ="${rndm}-f">
      <div class="efb  form-check form-switch   ${valj_efb[iVJ].el_height} efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}" id="${rndm}-switch">
        <input class="efb d-none emsFormBuilder_v efb-switch form-check-input efbField" type="checkbox" data-vid='${rndm}' data-id="${rndm}-el" id="${rndm}_" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${previewSate != true ? 'disabled' : ''} ${disabled}>
      </div> -->
      <div class="efb  mb-3">${desc}</div>
      `
      typeof rating_el_pro_efb =="function" ? 0 : ui=public_pro_message()
      dataTag = elementId;
      break;
    case 'esign':
      ui = `
      ${label}
      ${ttip}
      ${typeof esign_el_pro_efb =="function" ? esign_el_pro_efb(previewSate, pos , rndm,iVJ,desc) : public_pro_message()}
      `
      dataTag = elementId;
      break;
    case 'rating':
      ui = `
      ${ttip}
      ${label}
      ${typeof rating_el_pro_efb =="function" ? rating_el_pro_efb(previewSate,pos, rndm,iVJ) : public_pro_message()}
      <div class="efb  mb-3">${desc}</div>
        `
      dataTag = elementId;
      break;
    case "steps":
      dataTag = 'step';
      let del = ``;
      if (step_el_efb > 1) {
        del = `
          <button type="button" class="efb  btn btn-edit btn-sm" id="${valj_efb[iVJ].id_}"
          data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.delete}"
          onclick="show_delete_window_efb('${valj_efb[iVJ].id_}' ,${iVJ})">
          <i class="efb bi-trash text-danger"></i>
          </button>`
      }
      if (step_el_efb <= 2 || (step_el_efb > 2 && pro_efb == true)) {
        valj_efb[0].steps = editState == false ? step_el_efb : valj_efb[0].steps;
        const clss = valj_efb[iVJ].classes!="" ? 'efb1 '+valj_efb[iVJ].classes.replace(`,`, ` `) : "";
        const sort = iVJ<3 ? 'unsortable'  : 'sortable';
        newElement += `
        <setion class="efb ${sort}  row my-2  ${shwBtn} efbField stepNavEfb stepNo ${clss}" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}">
       <!-- <div class="efb  row my-2  ${shwBtn} efbField ${valj_efb[iVJ].classes.replace(`,`, ` `)} stepNavEfb" data-step="${valj_efb[iVJ].id_}" id="${valj_efb[iVJ].id_}" data-amount="${step_el_efb}" data-id="${valj_efb[iVJ].id_}" data-tag="${elementId}"> -->
        <h2 class="efb  col-md-10 col-sm-12 mx-2 my-0"><i class="efb  ${valj_efb[iVJ].icon} ${valj_efb[iVJ].label_text_size} ${valj_efb[iVJ].icon_color} "
        id="${valj_efb[iVJ].id_}_icon"></i> <span id="${valj_efb[iVJ].id_}_lab" class="efb  ${valj_efb[iVJ].label_text_size}  ${valj_efb[iVJ].label_text_color}  ">${valj_efb[iVJ].name}</span></span></h2>
        <small id="${valj_efb[iVJ].id_}-des" class="efb  form-text ${valj_efb[iVJ].message_text_color} border-bottom px-4">${valj_efb[iVJ].message}</small>
        <div class="efb  col-md-10 col-sm-12">
        <div class="efb  btn-edit-holder d-none" id="btnSetting-${valj_efb[iVJ].id_}">
        <button type="button" class="efb  btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"
        data-id="id1" data-bs-toggle="tooltip" title="${efb_var.text.edit}"
        onclick="show_setting_window_efb('${valj_efb[iVJ].id_}')">
        <div class="icon-container efb"><i class="efb bi-gear-wide-connected  text-success BtnSideEfb" ></i></div>
        </button>
          ${del}
        </div>
        </div>
        <!--  </div> -->
        </setion>
        `
      } else {
        pro_show_efb(2);
      }
      break;
    case 'select':
    case 'paySelect':
      if(elementId!="paySelect") pay='';
      if(editState!=false){
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
        for (const i of optns_obj) {
          optn += `<option class="efb ${valj_efb[iVJ].el_text_color} emsFormBuilder_v efb" data-id="${i.id_}" data-op="${i.id_}" value="${i.value}" ${valj_efb[iVJ].value==i.id_ || ( i.hasOwnProperty('id_old') && valj_efb[iVJ].value==i.id_old) ? "selected" :''}>${i.value}</option>`
        }
      }else{
        const op_1 = Math.random().toString(36).substr(2, 9);
       const op_2 = Math.random().toString(36).substr(2, 9);
       temp = '1';
        tp = '2';
        optionElpush_efb(rndm, `${efb_var.text.newOption} ${temp}`, op_1, op_1 ,dataTag);
       optionElpush_efb(rndm, `${efb_var.text.newOption} ${tp}`, op_2, op_2 ,dataTag);
      }
      ui = `
      ${label}
      <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1  ${valj_efb[iVJ].classes.replace(`,`, ` `)}"  data-css="${rndm}"   id='${rndm}-f'  data-id="${rndm}-el" >
      ${ttip}
      <select class="efb form-select efb emsFormBuilder_v w-100 ${pay}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  w-100 " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
      <option selected disabled>${efb_var.text.nothingSelected}</option>
      ${optn}
      </select>
      ${desc}
      `
      dataTag = elementId;
      break;
    case 'conturyList':
    case 'country':
       optn= typeof countryList_el_pro_efb =="function"? countryList_el_pro_efb(rndm,rndm_1,op_3,op_4,editState) : "null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 efb  px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="conturyList" class="efb form-select efb w-100 emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
        if (optn=="null") ui = public_pro_message();
      dataTag = elementId;
      break;
    case 'stateProvince':
    case 'statePro':
      temp=false
       optn =valj_efb.findIndex(x=>x.id_=='EC')
    if (editState==false && optn==-1) {
     if(valj_efb[iVJ].hasOwnProperty('country')==false || (valj_efb[iVJ].hasOwnProperty('country') && valj_efb[iVJ].country.length==0 ))  valj_efb[iVJ].country = "GB";
      const uk =  fun_state_of_UK(rndm,iVJ);
      for(u of uk){
      valj_efb.push(
        u
      );}
      temp=true;
    }
      optn = typeof statePrevion_el_pro_efb =="function"? statePrevion_el_pro_efb(rndm, rndm_1, temp, op_4, editState) :"null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="stateProvince" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
      dataTag = elementId;
      if (optn=="null") ui = public_pro_message();
      break;
    case 'city':
    case 'cityList':
       optn =valj_efb.findIndex(x=>x.id_=='Antrim_Newtownabbey');
        temp=false
    if (editState==false && optn==-1) {
      if(valj_efb[iVJ].hasOwnProperty('country')==false || (valj_efb[iVJ].hasOwnProperty('country') && valj_efb[iVJ].country.length==0 ))  valj_efb[iVJ].country = "GB";
      if(valj_efb[iVJ].hasOwnProperty('statePov')==false || (valj_efb[iVJ].hasOwnProperty('statePov') && valj_efb[iVJ].statePov.length==0)) valj_efb[iVJ].statePov = "Antrim_Newtownabbey";
      valj_efb.push(
        {
          "id_": "Antrim_Newtownabbey",
          "dataId": "Antrim_Newtownabbey-id",
          "parent":rndm,
          "type": "option",
          "value": "Antrim and Newtownabbey",
          "id_op": "Antrim_Newtownabbey",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
        "id_": "Ards_and_North_Down",
        "dataId": "Ards_and_North_Down-id",
        "parent":rndm,
        "type": "option",
        "value": "Ards and North Down",
        "id_op": "Ards_and_North_Down",
        "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Armagh_Banbridge_Craigavon",
          "dataId": "Armagh_Banbridge_Craigavon-id",
          "parent":rndm,
          "type": "option",
          "value": "Armagh City, Banbridge and Craigavon",
          "id_op": "Armagh_Banbridge_Craigavon",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Belfast",
          "dataId": "Belfast-id",
          "parent":rndm,
          "type": "option",
          "value": "Belfast",
          "id_op": "Belfast",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Causeway_Coast_Glens",
          "dataId": "Causeway_Coast_Glens-id",
          "parent":rndm,
          "type": "option",
          "value": "Causeway Coast and Glens",
          "id_op": "Causeway_Coast_Glens",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Derry_City_Strabane",
          "dataId": "Derry_City_Strabane-id",
          "parent":rndm,
          "type": "option",
          "value": "Derry City and Strabane",
          "id_op": "Derry_City_Strabane",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Fermanagh_Omagh",
          "dataId": "Fermanagh_Omagh-id",
          "parent":rndm,
          "type": "option",
          "value": "Fermanagh and Omagh",
          "id_op": "Fermanagh_Omagh",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Lisburn_Castlereagh",
          "dataId": "Lisburn_Castlereagh-id",
          "parent":rndm,
          "type": "option",
          "value": "Lisburn and Castlereagh",
          "id_op": "Lisburn_Castlereagh",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Mid_East_Antrim",
          "dataId": "Mid_East_Antrim-id",
          "parent":rndm,
          "type": "option",
          "value": "Mid and East Antrim",
          "id_op": "Mid_East_Antrim",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Mid_Ulster",
          "dataId": "Mid_Ulster-id",
          "parent":rndm,
          "type": "option",
          "value": "Mid Ulster",
          "id_op": "Mid_Ulster",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      {
          "id_": "Newry_Mourne_Down",
          "dataId": "Newry_Mourne_Down-id",
          "parent":rndm,
          "type": "option",
          "value": "Newry, Mourne and Down",
          "id_op": "Newry_Mourne_Down",
          "step": valj_efb[iVJ].step,
          "amount": valj_efb[iVJ].amount
      },
      );
      temp=true;
    }
      optn = typeof cityList_el_pro_efb =="function"? cityList_el_pro_efb(rndm, rndm_1, temp, op_4, editState) :"null";
      ui = `
        ${label}
        <div class="efb ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"  id='${rndm}-f'  data-id="${rndm}-el" >
        ${ttip}
        <select data-type="citylist" class="efb form-select emsFormBuilder_v w-100 ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color}  " data-vid='${rndm}' id="${rndm}_options" aria-required="${valj_efb[iVJ].required==1 ? true : false}" aria-label="${valj_efb[iVJ].name}" ${aire_describedby} ${previewSate != true ? 'readonly' : ''} ${disabled}>
        <option selected disabled>${efb_var.text.nothingSelected}</option>
        ${optn}
        </select>
        ${desc}
        `
      dataTag = elementId;
      if (optn=="null") ui = public_pro_message();
      break;
    case 'multiselect':
    case 'payMultiselect':
      if (elementId == "multiselect") pay = "";
      dataTag = 'multiselect';
      const currency = valj_efb[0].hasOwnProperty('currency') ? valj_efb[0].currency:'USD';
      let va = ``
      let sl =``
      if (editState != false) {
        optn = `<!--opt-->`;
        const optns_obj = valj_efb.filter(obj => { return obj.parent === rndm })
        const indx_parent = valj_efb.findIndex(x => x.id_ == rndm);
        const s = valj_efb[indx_parent].value.length>0 ? true :false
        for (const i of optns_obj) {
          let c = "efb bi-square efb"
          if(s==true && valj_efb[indx_parent].value.findIndex(x=>x==i.id_)!=-1){
             c = "bi-check-square text-info efb"
             va+= i.value+','
             sl +=i.id_ +' @efb!'}
          optn += `<tr class="efb  efblist ${valj_efb[indx_parent].el_text_color}  ${pay}" data-id="${rndm}" data-name="${i.value}" data-row="${i.id_}" data-state="0" data-visible="1">
          <th scope="row" class="${c}"></th><td class="efb  ms col-12">${i.value}</td>
          ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${i.id_}-price" class="efb efb-crrncy">${Number(i.price).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
        </tr>  `
        }
      } else {
        optn = `
        <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.blue}" data-row="${op_3}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.blue}</td>
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_3}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
        </tr>
      <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.Red}" data-row="${op_4}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.Red}</td>
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_4}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
      </tr>
      <tr class="efb list  ${pay}" data-id="menu-${rndm}" data-name="${efb_var.text.yellow}" data-row="${op_5}" data-state="0" data-visible="1">
        <th scope="row" class="efb bi-square efb"></th><td class="efb  ms col-12">${efb_var.text.yellow}</td>
        ${ pay.length>2 ?`<td class="efb ms fw-bold text-center"><span id="${op_5}-price" class="efb efb-crrncy">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></td>` :''}
      </tr>
       `
        const id = `menu-${rndm}`;
        optionElpush_efb(rndm, `${efb_var.text.blue}`, `${op_3}`, op_3 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.Red}`, `${op_4}`, op_4 ,dataTag);
        optionElpush_efb(rndm, `${efb_var.text.yellow}`, `${op_5}`, op_5 ,dataTag);
      }

      $optn = '<!--opt-->';
      ui = `
      ${label}
      <!--multiselect-->
      <div class="efb  ${pos[3]} col-sm-12 listSelect px-0 mx-0 ttEfb show efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-css="${rndm}"   id='${rndm}-f' data-id="${rndm}-el" >
        ${ttip}
        <div class="efb efblist  mx-0  inplist ${pay}  ${previewSate != true ? 'disabled' : ''} ${disabled}  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_border_color} bi-chevron-down" data-id="menu-${rndm}"   data-no="${valj_efb[iVJ].maxSelect}" data-min="${valj_efb[iVJ].minSelect}" data-parent="1" data-icon="1" data-select="${sl}"  data-vid='${rndm}' id="${rndm}_options" > ${va.length==0 ? efb_var.text.selectOption : va}</div>
        <div class="efb efblist mx-0  listContent shadow d-none border rounded-bottom bg-light" data-id="menu-${rndm}" data-list="menu-${rndm}">
        <table class="efb table menu-${rndm}">
         <thead class="efb efblist">
           <tr> <div class="efb searchSection efblist p-2 bg-light">
           <!-- <i class="efb efblist searchIcon  bi-search text-primary "></i> -->
               <input type="text" class="efb efblist search searchBox my-1 col-12 rounded " data-id="menu-${rndm}" data-tag="search" placeholder="🔍 ${efb_var.text.search}" onkeyup="FunSearchTableEfb('menu-${rndm}')" > </div>
         </tr> </thead>
         <tbody class="efb fs-7">
          ${optn}
         </tbody>
       </table>
      </div>
      ${desc}
       `;
      dataTag = elementId;
      break;
    case 'html':
      dataTag = elementId;
      ui = typeof html_el_pro_efb =="function" ? html_el_pro_efb(previewSate, rndm,iVJ) : public_pro_message()
      break;
    case 'yesNo':
      dataTag = elementId;
      ui = `
      ${label}
      ${ttip}
      ${typeof yesNi_el_pro_efb =="function" ? yesNi_el_pro_efb(previewSate,pos, rndm,iVJ) : public_pro_message()}
        ${desc}`;
      break;
    case 'link':
      dataTag = elementId;
      ui =typeof link_el_pro_efb =="function" ?  link_el_pro_efb (previewSate,pos, rndm,iVJ) : public_pro_message();
      break;
    case 'stripe':
      if(addons_emsFormBuilder.AdnSPF ==1){
        let sub = efb_var.text.onetime;
        let cl = `one`;
        if (valj_efb[0].hasOwnProperty('paymentmethod') && valj_efb[0].paymentmethod != 'charge') {
          const n = `${valj_efb[0].paymentmethod}ly`
          sub = efb_var.text[n];
          cl = valj_efb[0].paymentmethod;
        }
        dataTag = elementId;
        ui =typeof add_ui_stripe_efb =="function" ? add_ui_stripe_efb(rndm,cl,sub,0): public_pro_message();
        valj_efb[0].type = "payment";
        form_type_emsFormBuilder=valj_efb[0].type;
      }else{
        alert_message_efb(efb_var.text.error, efb_var.text.IMAddonP, 20 , 'danger');
        const l = valj_efb.length -1;
        valj_efb.splice(l,1);
        ui = show_pro_message_for_elm(1);
      }
      break;
        case 'paypal':
      if(addons_emsFormBuilder.AdnPAP ==1){
        let sub = efb_var.text.onetime;
        let cl = `one`;
       if (valj_efb[0].hasOwnProperty('paymentmethod') && valj_efb[0].paymentmethod != 'charge') {
        const n = `${valj_efb[0].paymentmethod}ly`
        sub = efb_var.text[n];
        cl = valj_efb[0].paymentmethod;
      }
        valj_efb[0].type = "payment";
        valj_efb[0].hasOwnProperty('getway') ?  valj_efb[0].getway='paypal' : Object.assign(valj_efb[0], {getway: 'paypal'});
        form_type_emsFormBuilder=valj_efb[0].type;
        dataTag = elementId;

        ui =typeof add_ui_paypal_efb =="function" ? add_ui_paypal_efb(rndm,cl,sub): public_pro_message();
      }else{
        dataTag = efb_var.text.IMAddonPMsg.replace('%s',`<b>${efb_var.text.paypal}</b>`) + ' '+ efb_var.text.INAddonMsg.replace('%s',`<b>${efb_var.text.paypal}</b>`).toLowerCase()
        alert_message_efb(efb_var.text.iaddon, dataTag, 20 , 'danger');
        const l = valj_efb.length -1;
        valj_efb.splice(l,1);
        ui = show_pro_message_for_elm(1);
      }
    break;
    case "persiaPay":
    case "zarinPal":
        if(  addons_emsFormBuilder.AdnPPF ==1 ){
          valj_efb[0].type = "payment";
          form_type_emsFormBuilder=valj_efb[0].type;
          dataTag = elementId;
          valj_efb[0].paymentmethod="charge"
          ui =typeof add_ui_persiaPay_efb =="function" ? add_ui_persiaPay_efb(rndm): public_pro_message();
        }else{
          alert_message_efb(efb_var.text.error, efb_var.text.IMAddonP, 20 , 'danger');
          const l = valj_efb.length -1;
          valj_efb.splice(l,1);
          ui = show_pro_message_for_elm(1);
        }
      break;
    case 'heading':
      dataTag = elementId;
      ui =typeof headning_el_pro_efb =="function" ?headning_el_pro_efb (rndm,pos,iVJ) :public_pro_message();
      break;
    case 'booking':
      dataTag = elementId;
      break;
    case 'pointr10':
      temp =typeof pointer10_el_pro_efb =="function" ? pointer10_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'pointr5':
      temp =typeof pointer5_el_pro_efb =="function" ? pointer5_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'smartcr':
      temp =typeof smartcr_el_pro_efb =="function" ? smartcr_el_pro_efb(previewSate, classes,iVJ) :public_pro_message();
      ui = `
      ${label}
      <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
        ${ttip}
        ${temp}
        ${desc}`
      dataTag = elementId;
      break;
    case 'table_matrix':
        type_field_efb = elementId;
        dataTag = elementId;
         col = valj_efb[iVJ].hasOwnProperty('op_style') && Number(valj_efb[iVJ].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[iVJ].op_style)) : ''
        if (elementId == "radio" || elementId == "checkbox" || elementId == "chlRadio" || elementId == "chlCheckBox") pay = "";
        if (editState != false) {
          optns_obj = valj_efb.filter(obj => { return obj.parent === rndm });
          for (const i of optns_obj) {
            prc = i.hasOwnProperty('price') ? Number(i.price) : 0;
            optn += `
            <!-- start r_matrix -->
            <div class="efb  col-sm-12 ${col} row my-1   t-matrix" data-id="${i.id_}" data-parent="${i.parent}" id="${i.id_}-v">
              <div class="efb mt-2  col-md-8 fs-6  ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${i.id_}_lab">${i.value}</div>
              <div class="efb col-md-4  d-flex justify-content-${position_l_efb} " ${aire_describedby} id="${i.id_}" >
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${i.id_}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill" data-icon="${i.id_}"></i></div>
                  <input type="hidden" class="efb emsFormBuilder_v" data-vid="${i.id_}" data-parent="${i.parent} data-type="rating"  id="${i.id_}-point-rating" >
              </div>
              <hr class="efb t-matrix my-1">
          </div>
          <!-- end r_matrix -->
            `
          }
        } else {
          const op_1 = Math.random().toString(36).substr(2, 9);
          const op_2 = Math.random().toString(36).substr(2, 9);
          const pv = 0;
          optn = `
          <div class="efb   col-sm-12 row my-1  t-matrix" data-id="${op_1}" data-parent="${rndm}" id="${op_1}-v">
              <div class="efb mt-2 col-md-8 fs-6 ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${op_1}_lab">${efb_var.text.newOption}</div>
              <div class="efb col-md-4 d-flex justify-content-${position_l_efb} "  id="${op_1}" >
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${op_1}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <input type="hidden" data-vid="${op_1}" data-parent="${rndm} data-type="rating"  id="${op_1}-point-rating" >
              </div>
              <hr class="efb t-matrix my-1">
          </div>
          <div class="efb col-md-12    col-sm-12 row my-1  t-matrix" data-id="${op_2}" data-parent="${rndm}" id="${op_2}-v">
              <div class="efb mt-2 col-md-8 fs-6 ${valj_efb[iVJ].el_text_color}  ${valj_efb[iVJ].el_height} ${valj_efb[iVJ].label_text_size}" id="${op_2}_lab">${efb_var.text.newOption}</div>
              <div class="efb col-md-4  d-flex justify-content-${position_l_efb} " id="${op_2}" >
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="1"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="2"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="3"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="4"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <div class="efb btn btn-secondary text-white mx-1 ${previewSate != true ? 'disabled' : ''} ${disabled}"  data-point="5"  data-id="${op_2}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
                  <input type="hidden" data-vid="${op_2}" data-parent="${rndm} data-type="rating"  id="${op_2}-point-rating" >
              </div>
              <hr class="efb t-matrix my-1">
          </div>`
          r_matrix_push_efb(rndm, efb_var.text.newOption, op_1, op_1);
          r_matrix_push_efb(rndm, efb_var.text.newOption, op_2, op_2);
        }
        ui = `
        <!-- table matrix -->
        ${label}
          <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"   data-id="${rndm}-el" id='${rndm}-f'>
            ${ttip}
            <div class="efb  ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''} ${col != '' ? 'row col-md-12' : ''} efb1  ${valj_efb[iVJ].classes.replace(`,`, ` `)}" id="${rndm}_options">
              ${optn}
            </div>
            <div class="efb  mb-3">${desc}</div>
          <!-- end table matrix -->
          `
        break;

    case 'prcfld':
        maxlen = valj_efb[iVJ].hasOwnProperty('mlen') && valj_efb[iVJ].mlen >0 ? valj_efb[iVJ].mlen :0;
        maxlen = Number(maxlen)!=0 ? `maxlength="${maxlen}"`:``;
        minlen = valj_efb[iVJ].hasOwnProperty('milen') && valj_efb[iVJ].milen >0 ? valj_efb[iVJ].milen :0;
        minlen = Number(minlen)!=0 ? `minlength="${minlen}"`:``;
        dataTag = valj_efb[0].hasOwnProperty('currency')==false ? 'usd' : valj_efb[0].currency;
        classes = new Intl.NumberFormat(lan_name_emsFormBuilder, { style: 'currency', currency: dataTag, currencyDisplay: 'narrowSymbol' }).formatToParts(0).find(part => part.type === 'currency').value;
        dataTag = `<span class="efb input-group-text crrncy-clss">${ classes}</span>`
        classes = `form-control ${valj_efb[iVJ].el_border_color} `;
        ui = `
        ${label}
        <div class="efb  ${pos[3]} col-sm-12 px-0 mx-0 ttEfb show"  id='${rndm}-f'>
          ${ttip}
          <div class="efb input-group m-0 p-0">
           ${efb_var.rtl==true ? '' :dataTag}
          <input type="number"   class="efb input-efb px-2 mb-0 payefb emsFormBuilder_v  ${classes} ${valj_efb[iVJ].el_height} ${corner} ${valj_efb[iVJ].el_text_color} ${valj_efb[iVJ].required == 1 || valj_efb[iVJ].required == true ? 'required' : ''}  efbField efb1 ${valj_efb[iVJ].classes.replace(`,`, ` `)}" data-id="${rndm}-el" data-vid='${rndm}' data-css="${rndm}" id="${rndm}_" placeholder="${valj_efb[iVJ].placeholder}"  ${valj_efb[iVJ].value.length > 0 ? `value ="${valj_efb[iVJ].value}"` : ''} ${aire_describedby} ${maxlen} ${minlen} ${previewSate != true ? 'readonly' : ''} ${disabled =="disabled" ? 'readonly' :''}>
          ${efb_var.rtl==true ? dataTag :''}
          </div>
          ${desc}`
        dataTag = elementId;
        break;
    case 'ttlprc':
        dataTag = elementId;
        if(valj_efb[0].hasOwnProperty('currency')==false ) Object.assign(valj_efb[0], {currency: 'USD'});
        ui = `
        ${label}
        <div class="efb  ${pos[3]} col-sm-12  pt-2 pb-1 px-0 mx-0 ttEfb show" id='${rndm}-f'>
        ${typeof add_ui_totalprice_efb =="function" ? add_ui_totalprice_efb(rndm,iVJ): public_pro_message()}
        ${desc}
        `
        form_type_emsFormBuilder=valj_efb[0].type;
      break;
  }
  const addDeleteBtnState = (formName_Efb == "login" && (valj_efb[iVJ].id_ == "emaillogin" || valj_efb[iVJ].id_ == "passwordlogin")) || (formName_Efb == "register" && (valj_efb[iVJ].id_ == "usernameRegisterEFB" || valj_efb[iVJ].id_ == "passwordRegisterEFB" || valj_efb[iVJ].id_ == "emailRegisterEFB")) ? true : false;
  if (elementId != "form" && dataTag != "step" && ((previewSate == true && elementId != 'option') || previewSate != true))
    {
    const pro_el = valj_efb[iVJ].hasOwnProperty('pro') ? valj_efb[iVJ].pro :false ;
    const contorl = ` <div class="efb btn-edit-holder d-none efb" id="btnSetting-${rndm}-id">
    <button type="button" class="efb  btn btn-edit btn-sm BtnSideEfb" id="settingElEFb"  data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.edit}" onclick="show_setting_window_efb('${rndm}-id')">
    <div class="icon-container efb"><i class="efb bi-gear-wide-connected  text-success BtnSideEfb"></i></div>
    </button>
    <button type="button" class="efb  btn btn-edit btn-sm" id="dupElEFb-${rndm}" data-id="${rndm}-id"  data-bs-toggle="tooltip"  title="${efb_var.text.duplicate}" onclick="show_duplicate_fun('${rndm}','${valj_efb[iVJ].name}')">
    <i class="efb  bi-clipboard-plus text-muted"></i>
    </button>
    ${addDeleteBtnState ? '' : `<button type="button" class="efb  btn btn-edit btn-sm" id="deleteElEFb"   data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.delete}" onclick="show_delete_window_efb('${rndm}-id' ,${iVJ})"> <i class="efb  bi-trash text-danger"></i></button>`}
    <span class="efb  btn btn-edit btn-sm "  id="moveElEFb" onclick="move_show_efb()"><i class="efb text-dark bi-arrows-move"></i></span>
    `
    const proActiv = `
    <div class="efb btn-edit-holder efb d-none zindex-10-efb " id="btnSetting-${rndm}-id">
    <button type="button" class="efb btn efb pro-bg btn-pro-efb btn-sm px-2 mx-3" id="pro" data-id="${rndm}-id" data-bs-toggle="tooltip"  title="${efb_var.text.proVersion}" onclick="pro_show_efb(1)">
    <i class="efb  bi-gem pro"> ${efb_var.text.pro}</i>`;
    endTags = previewSate == false ? `</button> </button></div></div>` : `</div></div>`
    const tagId = elementId == "firstName" || elementId == "lastName" || elementId == "address" || elementId == "address_line" || elementId == "postalcode" ? 'text' : elementId;
    const tagT = elementId =="esign" || elementId=="yesNo" || elementId=="rating" ? '' : 'def'
    const mobileColCls = getMobileColClass(valj_efb[iVJ]);
    newElement += `
    ${previewSate == false  ? `<setion class="efb my-1 px-0 mx-0 ttEfb ${previewSate != true ? disabled : ""} ${previewSate == false && valj_efb[iVJ].hidden==1 ? "hidden" : ""} ${previewSate == true && (pos[1] == "col-md-12" || pos[1] == "col-md-10") ? `mx-0 px-0` : 'position-relative'} ${previewSate == true ? `${pos[0]} ${pos[1]}` : `${ps}`} row ${mobileColCls} ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >` : ''}
    ${previewSate == false && valj_efb[iVJ].hidden==1 ? hiddenMarkEl(valj_efb[iVJ].id_) : ''}
    <div class="efb my-1 mx-0  ${elementId} ${tagT} ${hidden} ${previewSate == true ? disabled : ""}  ttEfb ${previewSate == true ? `${pos[0]} ${pos[1]}` : ` row`} ${mobileColCls} ${shwBtn} efbField ${dataTag == "step" ? 'step' : ''}" data-step="${step_el_efb}" data-amount="${amount_el_efb}" data-id="${rndm}-id" id="${rndm}" data-tag="${tagId}"  >
    ${(previewSate == true && elementId != 'option') || previewSate != true ? ui : ''}
    ${previewSate != true && pro_efb == false && pro_el==true ? proActiv : ''}
    ${previewSate != true ? contorl : '<!--efb.app-->'}
    ${previewSate != true && pro_efb == false && pro_el==true  ? '</div>' : ''}
    ${(previewSate == true && elementId != 'option' && elementId != "html" && elementId != "stripe" && elementId != "heading" && elementId != "link") || previewSate != true ? endTags : '</div>'}
    ${previewSate == false  ? ` </setion><!--endTag EFB-->` :''}
     <!--endTag EFB-->
    `;
  } else if (dataTag == 'step' && previewSate != true) {
    if (elementId == "steps" && pro_efb == false && Number(step_el_efb) == 3) {
      amount_el_efb = Number(amount_el_efb) - 1;
      step_el_efb = 2;
      valj_efb[0].steps = 2
    } else {
      valj_efb[0].steps = step_el_efb;
    }
    if (!document.getElementById('button_group')) {
      add_buttons_zone_efb(0, 'dropZoneEFB')
      fub_shwBtns_efb();
    } else if (valj_efb[0].steps > 1) {
      fun_handle_buttons_efb(true)
      fub_shwBtns_efb();
    }
  }
  return newElement;
}

const public_pro_message= ()=>{
return `<div class="efb alert alert-danger d-flex align-items-center gap-2 py-3 px-4 rounded-2 border-0" role="alert">
  <i class="efb bi-exclamation-triangle-fill fs-5"></i>
  <div class="efb flex-grow-1">
    <strong class="efb d-block mb-1">${efb_var.text.error}</strong>
    <small class="efb d-block">${efb_var.text.tfnapca}</small>
  </div>
    </div>`
}

const show_pro_message_for_elm = (i) =>{
  const message = i == 1 ?  efb_var.text.proUnlockMsg: efb_var.text.thisFeatureAvailableFreePlusPro;
  const fun = i == 1 ?`pro_show_efb(${i})` : `showSetupAsOverlayPage()`;
  return `
    <div class="efb alert alert-warning d-flex align-items-center gap-2 py-3 px-4 rounded-2 border-0" role="alert">
      <i class="efb bi-gem fs-5" style="color: #ffc107;"></i>
      <div class="efb flex-grow-1">
        <strong class="efb d-block mb-1">${efb_var.text.proVersion}</strong>
        <small class="efb d-block text-muted">${message}</small>
      </div>
      <button type="button" class="efb btn btn-sm btn-warning text-dark fw-bold px-3" onclick="${fun}">
        <i class="efb bi-unlock-fill me-1"></i>
      </button>
    </div>
  `;
}

const funSetPosElEfb = (dataId, position) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx != -1) {
    valj_efb[indx].label_position = position
  }
  if (valj_efb[indx].type != "stripe"  && valj_efb[indx].type != "html") get_position_col_el(dataId, true)
}

const funSetAlignElEfb = (dataId, align, element) => {
  const indx = dataId!='button_group_' && dataId!='Next_' ? valj_efb.findIndex(x => x.dataId == dataId) :0;
  if (indx == -1) { return }
  switch (element) {
    case 'label':
      document.getElementById(`${valj_efb[indx].id_}_labG`).className = alignChangerEfb(document.getElementById(`${valj_efb[indx].id_}_labG`).className, align)
      valj_efb[indx].label_align = align
      break;
    case 'description':
      const elm = document.getElementById(`${valj_efb[indx].id_}-des`)
      elm.className = alignChangerElEfb(elm.className, align)
      valj_efb[indx].message_align = align
      if (align != 'justify-content-start' && elm.classList.contains('mx-4') == true) { elm.classList.remove('mx-4') }
      else if (align == 'justify-content-start' && elm.classList.contains('mx-4') == false) {
        elm.classList.add('mx-4')
      }
      break;
    case 'buttons':
      const id = valj_efb[0].steps<2 ? 'f_btn_send_efb' : 'f_button_form_np';
      const elm_ = document.getElementById(id)
      elm_.className = alignChangerElEfb(elm_.className, align)
      valj_efb[0].hasOwnProperty('btns_align') ? valj_efb[0].btns_align = align : Object.assign(valj_efb[0], { btns_align: align });
      break;
      default:
        break;
  }
}

const funSetMobilePosElEfb = (dataId, position) => {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx != -1) {
    valj_efb[indx].mobile_label_position = position;
  }
  if (typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile') {
    applyMobileLabelPositionEfb(valj_efb[indx]);
  }
}

const funSetMobileAlignElEfb = (dataId, align, element) => {
  const indx = dataId != 'button_group_' && dataId != 'Next_' ? valj_efb.findIndex(x => x.dataId == dataId) : 0;
  if (indx == -1) { return }
  const propName = element == 'label' ? 'mobile_label_align' : 'mobile_message_align';
  valj_efb[indx][propName] = align;
  if (typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile') {
    switch (element) {
      case 'label':
        let labEl = document.getElementById(`${valj_efb[indx].id_}_labG`);
        if (labEl) labEl.className = alignChangerEfb(labEl.className, align);
        break;
      case 'description':
        let desEl = document.getElementById(`${valj_efb[indx].id_}-des`);
        if (desEl) {
          desEl.className = alignChangerElEfb(desEl.className, align);
          if (align != 'justify-content-start' && desEl.classList.contains('mx-4')) { desEl.classList.remove('mx-4'); }
          else if (align == 'justify-content-start' && !desEl.classList.contains('mx-4')) { desEl.classList.add('mx-4'); }
        }
        break;
    }
  }
}

const loadingShow_efb = (title) => {
  return `<div class="efb modal-dialog modal-dialog-centered efb"  id="settingModalEfb_" >
 <div class="efb modal-content efb " id="settingModalEfb-sections">
     <div class="efb modal-header efb">
         <h5 class="efb modal-title fs-5" ><i class="efb bi-ui-checks mx-2 efb" id="settingModalEfb-icon"></i><span id="settingModalEfb-title">${title ? title : efb_var.text.loading} </span></h5>
     </div>
     <div class="efb modal-body efb" id="settingModalEfb-body">
         ${efbLoadingCard('',4)}
     </div>
 </div>
</div>`
}

let fun_handle_buttons_efb = (state) => {
  setTimeout(() => {
    if (state == true && document.getElementById('f_btn_send_efb').classList.contains('d-block')) {
      document.getElementById('f_btn_send_efb').classList.add('d-none');
      if (document.getElementById('f_button_form_np').classList.contains('d-none')) {
        document.getElementById('f_button_form_np').classList.remove('d-none');
        document.getElementById('f_button_form_np').classList.add('d-block');
      }
    } else if (state == false) {
      if (document.getElementById('f_button_form_np').classList.contains('d-block')) {
        document.getElementById('f_button_form_np').classList.remove('d-block');
      }
      document.getElementById('f_button_form_np').classList.add('d-none');
      if (document.getElementById('f_btn_send_efb').classList.contains('d-none')) {
        document.getElementById('f_btn_send_efb').classList.remove('d-none')
        document.getElementById('f_btn_send_efb').classList.add('d-block')
      }
    }
    valj_efb[0].button_state = state == true ? 'multi' : 'single';
  }, 50)
}

const open_whiteStudio_efb = (state) => {
const sub =lan_subdomain_wsteam_efb();
  let link = `https://${sub}whitestudio.team/document/`;
  if(efb_var.language != "fa_IR"){
  switch (state) {
    case 'mapErorr':
      link += `How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder`
      break;
    case 'pro':
      link = `https://${sub}whitestudio.team/#price`
      break;
    case 'publishForm':
      link = `https://www.youtube.com/watch?v=RJRe7p6yPCI`
      break;
    case 'emptyStep':
      link += `how-to-create-your-first-form-with-easy-form-builder#empty-step-alert`
      break;
    case 'notInput':
      link += `?notInputExists`
      break;
    case 'pickupByUser':
      link += `How-to-Install-and-Use-the-Location-Picker-(geolocation)-with-Easy-Form-Builder#how-to-add-a-location-picker-when-creating-form`
      break;
    case 'paymentform':
      link += `How-to-Create-a-Payment-Form-in-Easy-Form-Builder`
      break;
    case 'free_plus_guide':
      link += 'easy-form-builder-free-plus-activation-guide'
    break;
  }
}else{
  link = `https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/`;
  switch (state) {
    case 'mapErorr':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%d9%86%d8%aa%d8%ae%d8%a7%d8%a8%da%af%d8%b1-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d9%85%da%a9%d8%a7%d9%86%db%8c-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d8%ac%d8%ba/`
      break;
    case 'pro':
      link = `https://easyformbuilder.ir/#price`
      break;
    case 'publishForm':
      case 'notInput':
      link += "%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/";
      break;
    case 'emptyStep':
      link += `%DA%86%DA%AF%D9%88%D9%86%D9%87-%D9%81%D8%B1%D9%85-%D8%AA%D9%88%D8%B3%D8%B7-%D9%81%D8%B1%D9%85-%D8%B3%D8%A7%D8%B2-%D8%A2%D8%B3%D8%A7%D9%86-%D8%AF%D8%B1-%D9%88%D8%B1%D8%AF%D9%BE%D8%B1%D8%B3-%D8%A8%D8%B3/`
      break;
    case 'pickupByUser':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%d9%86%d8%aa%d8%ae%d8%a7%d8%a8%da%af%d8%b1-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d9%85%da%a9%d8%a7%d9%86%db%8c-%d9%85%d9%88%d9%82%d8%b9%db%8c%d8%aa-%d8%ac%d8%ba/`
      break;
    case 'paymentform':
      link += `%da%86%da%af%d9%88%d9%86%d9%87-%d8%af%d8%b1%da%af%d8%a7%d9%87-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d8%a7%db%8c%d8%b1%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%a8%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2/`
      break;

  }
}
  window.open(link, "_blank")
}

function ReadyElForViewEfb(content) {
  setTimeout(() => {
    const t = valj_efb[0].steps == 1 ? 0 : 1;
    const btns = add_buttons_zone_efb(t, 'preview')
    const html = `
    <head>
    <script>
    valj_efb = ${JSON.stringify(valj_efb)};
    </script>
  </head>
    <body>
    ${content}
    ${btns}
    handle_navbtn_efb(${valj_efb[0].steps},'mobile')
    </script>
    </body>
    `
    let iframe = document.createElement('iframe');
    iframe.id = `efbMobileView`;
    iframe.src = 'data:text/html;charset=utf-8,' + encodeURI(html);
    document.getElementById('parentMobileView-efb').innerHTML = "";
    document.getElementById('parentMobileView-efb').appendChild(iframe)
  }, 1000)
}

function timeOutCaptcha() {
  const id = valj_efb[0].steps > 1 ? 'next_efb' : 'btn_send_efb'
  document.getElementById(id).classList.add('disabled');
  alert_message_efb(efb_var.text.error, efb_var.text.errorVerifyingRecaptcha, 7, 'warning');
}

async function fun_validation_efb() {
  let offsetw = offset_view_efb();
  const defaultMsg = efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null";
  for (let row in valj_efb) {
    let s =  get_row_sendback_by_id_efb(valj_efb[row].id_);
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
      let el =document.getElementById(`${valj_efb[row].id_}_-message`);
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        let r=files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_);
        s = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state)==0 || r==-1 ? -1 :1;
      }
      if (s == -1) {
        if (state == true) { state = false; idi = valj_efb[row].id_ }
        const id = fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`;
        if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
          el.classList.add('unpx');
        }
        // Use custom required message if set, otherwise use default
        const fieldMsg = valj_efb[row].hasOwnProperty('customRequiredMsg') && valj_efb[row].customRequiredMsg.length > 0 ? valj_efb[row].customRequiredMsg : defaultMsg;
        const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${fieldMsg}','',10,'danger')"></div>` : fieldMsg;
        el.innerHTML = msg;
        el.style.display='block';
        if (type_validate_efb(valj_efb[row].type) == true) {
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");}
      } else {
        idi = valj_efb[row].id_;
        el.innerHTML = "";
        el.style.display='none';
        if (type_validate_efb(valj_efb[row].type) == true) document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-success");
        const v = sendBack_emsFormBuilder_pub.length>0 && valj_efb[row].type == "multiselect" && sendBack_emsFormBuilder_pub[s].hasOwnProperty('value') ? sendBack_emsFormBuilder_pub[s].value.split("@efb!") :"";
        if ((valj_efb[row].type == "multiselect" || valj_efb[row].type == "payMultiselect") && (v.length - 1) < valj_efb[row].minSelect) {
          document.getElementById(id).className = colorBorderChangerEfb(document.getElementById(id).className, "border-danger");
          el.innerHTML = efb_var.text.minSelect + " " + valj_efb[row].minSelect
          el.style.display='block';
          if (state == true) { state = false; idi = valj_efb[row].id_ }
        }
      }
    }else if (row > 1 && valj_efb[row].type == "chlCheckBox" && current_s_efb == valj_efb[row].step){
      for(let em of sendBack_emsFormBuilder_pub){
        if(em.type=="chlCheckBox" && em.id_==valj_efb[row].id_ && em.qty.length=="")
        {
          const vd = em.id_ob+"_chl";
          document.getElementById(vd).classList.add('bg-danger');
          state = false;
          idi = valj_efb[row].id_;
        }
      }
      if (state == false) {
          // Use custom required message if set, otherwise use default
          const chlFieldMsg = valj_efb[row].hasOwnProperty('customRequiredMsg') && valj_efb[row].customRequiredMsg.length > 0 ? valj_efb[row].customRequiredMsg : defaultMsg;
          noti_message_efb(chlFieldMsg, 'danger' , `step-${current_s_efb}-efb-msg` );
      }
    }
  }

  if (state===false && idi != "null") {
    if(typeof smoothy_scroll_postion_efb === 'function'){
      smoothy_scroll_postion_efb(idi)
    }else{
      document.getElementById(idi).scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
    }
  }
  return state
}

function addStyleColorBodyEfb(t, c, type, id) {
  let ttype = "text";
  if(id==-1){
    ttype =type;
  }else{
    ttype = valj_efb[id].type
  }
  let v = `.${t}{color:${c}!important;}`
  let tag = "";
  switch (ttype) {
    case 'textarea':
      tag = "textarea"
      break;
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
    case 'checkbox':
    case 'radiobutton':
    case 'prcfld':
      tag = "input"
      break;
    case 'btn':
      tag = "btn"
      break;
    default:
      tag = ""
      break;
  }
  c=c[0]!="#" ? "#"+c : c
  efb_add_costum_color(t, c ,v , type)
}

function efb_add_costum_color(t, c, v, type){
  let n =''
  c=c[0]!="#" ? "#"+c : c
  if (type == "text") {       n=`${type}-${t}`;         v = `.${n}{color:${c}!important;}` }
  else if (type == "icon") {  n=`text-${t}`;            v = `.${n}{color:${c}!important;}` }
  else if (type == "border") {n=`${type}-${t}`;         v = `.${n}{border-color:${c}!important;}` }
  else if (type == "bg") {    n=`${type}-${t}`;         v = `.${n}{background-color:${c}!important;}` }
  else if (type == "btn") {   n=`${type}-${t}`;         v = `.${n}{background-color:${c}!important;}` }
  document.body.appendChild(Object.assign(document.createElement("style"), { textContent: `${v}` }))
  return n;
}

/**
 * Apply checked color to radio/checkbox elements
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applyCheckedColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;
  const styleId = `efb-checked-color-${parentId}`;

  // Remove existing style if present
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  // Create CSS with maximum specificity using attribute selectors for IDs (to handle IDs starting with numbers)
  const css = `
    input.efb.form-check-input[data-vid="${parentId}"]:checked,
    input.efb.form-check-input[data-vid="${parentId}"]:checked[type=checkbox],
    input.efb.form-check-input[data-vid="${parentId}"]:checked[type=radio],
    [data-css="${parentId}"] input.efb.form-check-input:checked,
    [data-css="${parentId}"] input.efb.form-check-input:checked[type=checkbox],
    [data-css="${parentId}"] input.efb.form-check-input:checked[type=radio],
    [data-parent="${parentId}"] input.efb.form-check-input:checked,
    [data-parent="${parentId}"] input.efb.form-check-input:checked[type=checkbox],
    [data-parent="${parentId}"] input.efb.form-check-input:checked[type=radio],
    [id="${parentId}_options"] input.efb.form-check-input:checked,
    [id="${parentId}_options"] input.efb.form-check-input:checked[type=checkbox],
    [id="${parentId}_options"] input.efb.form-check-input:checked[type=radio] {
      background-color: ${color} !important;
      border-color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);

  // Also apply directly to elements for immediate effect (with delay for DOM readiness)
  setTimeout(() => {
    applyCheckedColorDirectEfb(parentId, color);
  }, 100);

  // Also try after longer delay for async loaded elements
  setTimeout(() => {
    applyCheckedColorDirectEfb(parentId, color);
  }, 500);
}

/**
 * Apply checked color directly to elements via inline style
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applyCheckedColorDirectEfb(parentId, color) {
  // Find all checkboxes/radios with this parent
  // Use attribute selectors instead of #id to avoid issues with IDs starting with numbers
  const selectors = [
    `input.form-check-input[data-vid="${parentId}"]`,
    `[data-css="${parentId}"] input.form-check-input`,
    `[data-parent="${parentId}"] input.form-check-input`,
    `[id="${parentId}_options"] input.form-check-input`
  ];

  let totalFound = 0;
  selectors.forEach(selector => {
    try {
      const inputs = document.querySelectorAll(selector);
      totalFound += inputs.length;
      inputs.forEach(input => {
        // Store the color in a data attribute
        input.dataset.checkedColor = color;

        // Remove old listener if exists
        if (input._efbCheckedColorHandler) {
          input.removeEventListener('change', input._efbCheckedColorHandler);
        }

        // Add change listener to apply color when checked
        input._efbCheckedColorHandler = function() {
          updateCheckedColorStyleEfb(this);
        };
        input.addEventListener('change', input._efbCheckedColorHandler);

        // Apply immediately if already checked
        updateCheckedColorStyleEfb(input);
      });
    } catch (e) {
      console.log('[EFB DEBUG] Selector error:', selector, e.message);
    }
  });

}

/**
 * Update inline style based on checked state
 * @param {HTMLInputElement} input - The input element
 */
function updateCheckedColorStyleEfb(input) {
  const color = input.dataset.checkedColor;
  if (!color) {
    return;
  }



  if (input.checked) {
    input.style.setProperty('background-color', color, 'important');
    input.style.setProperty('border-color', color, 'important');

  } else {
    input.style.removeProperty('background-color');
    input.style.removeProperty('border-color');
  }
}

/**
 * Apply range thumb color to range slider elements
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applyRangeThumbColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;
  const styleId = `efb-range-thumb-color-${parentId}`;

  // Remove existing style if present
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  // Create CSS for range thumb with vendor prefixes - using multiple selectors for compatibility
  const css = `
    input[type="range"][data-vid="${parentId}"]::-webkit-slider-thumb,
    [data-vid="${parentId}"] input[type="range"]::-webkit-slider-thumb,
    [id="${parentId}-range"] input[type="range"]::-webkit-slider-thumb,
    [data-css="${parentId}"] input[type="range"]::-webkit-slider-thumb,
    [data-css="${parentId}"] .efb.form-range::-webkit-slider-thumb {
      background-color: ${color} !important;
    }
    input[type="range"][data-vid="${parentId}"]::-moz-range-thumb,
    [data-vid="${parentId}"] input[type="range"]::-moz-range-thumb,
    [id="${parentId}-range"] input[type="range"]::-moz-range-thumb,
    [data-css="${parentId}"] input[type="range"]::-moz-range-thumb,
    [data-css="${parentId}"] .efb.form-range::-moz-range-thumb {
      background-color: ${color} !important;
    }
    input[type="range"][data-vid="${parentId}"]::-ms-thumb,
    [data-vid="${parentId}"] input[type="range"]::-ms-thumb,
    [id="${parentId}-range"] input[type="range"]::-ms-thumb,
    [data-css="${parentId}"] input[type="range"]::-ms-thumb,
    [data-css="${parentId}"] .efb.form-range::-ms-thumb {
      background-color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);
}

/**
 * Apply range value text color
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applyRangeValueColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;

  // Find the range value element and apply color directly
  const valueEl = document.getElementById(`${parentId}_rv`);
  if (valueEl) {
    valueEl.style.setProperty('color', color, 'important');
  }

  // Also create a style tag for consistency
  const styleId = `efb-range-value-color-${parentId}`;
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  const css = `
    #${parentId}_rv,
    [id="${parentId}_rv"] {
      color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);
}

/**
 * Apply switch on color
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applySwitchOnColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;

  // Find the switch button element and apply color directly when active
  const switchBtn = document.querySelector(`#${parentId} .btn-toggle, [id="${parentId}"] .btn-toggle`);
  if (switchBtn && switchBtn.classList.contains('active')) {
    switchBtn.style.setProperty('background-color', color, 'important');
    switchBtn.style.setProperty('border-color', color, 'important');
  }

  // Create a style tag for the active state
  const styleId = `efb-switch-on-color-${parentId}`;
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  const css = `
    #${parentId} .efb.btn-toggle.active,
    [id="${parentId}"] .efb.btn-toggle.active {
      background-color: ${color} !important;
      border-color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);
}

/**
 * Apply switch handle color
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applySwitchHandleColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;

  // Find the switch handle element and apply color directly
  const handleEl = document.querySelector(`#${parentId} .btn-toggle > .handle, [id="${parentId}"] .btn-toggle > .handle`);
  if (handleEl) {
    handleEl.style.setProperty('background-color', color, 'important');
  }

  // Create a style tag for the handle
  const styleId = `efb-switch-handle-color-${parentId}`;
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  const css = `
    #${parentId} .efb.btn-toggle > .handle,
    [id="${parentId}"] .efb.btn-toggle > .handle {
      background-color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);
}

/**
 * Apply switch off color
 * @param {string} parentId - The parent element ID
 * @param {string} color - The hex color value
 */
function applySwitchOffColorEfb(parentId, color) {
  color = color[0] !== "#" ? "#" + color : color;

  // Find the switch button element and apply color directly when not active
  const switchBtn = document.querySelector(`#${parentId} .btn-toggle, [id="${parentId}"] .btn-toggle`);
  if (switchBtn && !switchBtn.classList.contains('active')) {
    switchBtn.style.setProperty('background-color', color, 'important');
    switchBtn.style.setProperty('border-color', color, 'important');
  }

  // Create a style tag for the off state
  const styleId = `efb-switch-off-color-${parentId}`;
  const existingStyle = document.getElementById(styleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  const css = `
    #${parentId} .efb.btn-toggle:not(.active),
    [id="${parentId}"] .efb.btn-toggle:not(.active) {
      background-color: ${color} !important;
      border-color: ${color} !important;
    }
  `;

  const styleEl = document.createElement("style");
  styleEl.id = styleId;
  styleEl.textContent = css;
  document.head.appendChild(styleEl);
}

/**
 * Initialize checked colors for all radio/checkbox elements on form load
 */
function initCheckedColorsEfb() {
  if (typeof valj_efb === 'undefined') return;

  const radioCheckboxTypes = ['radio', 'checkbox', 'payRadio', 'payCheckbox', 'chlRadio', 'chlCheckBox', 'trmCheckbox'];

  valj_efb.forEach((item, index) => {
    if (radioCheckboxTypes.includes(item.type) && item.hasOwnProperty('checked_color') && item.checked_color) {
      applyCheckedColorEfb(item.id_, item.checked_color);
    }
  });
}

function fun_addStyle_costumize_efb(val, key, indexVJ) {
  // Handle range_thumb_color for range elements
  if (key === 'range_thumb_color' && val && val.length > 0) {
    applyRangeThumbColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  // Handle range_value_color for range elements
  if (key === 'range_value_color' && val && val.length > 0) {
    applyRangeValueColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  // Handle switch_on_color for switch elements
  if (key === 'switch_on_color' && val && val.length > 0) {
    applySwitchOnColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  // Handle switch_handle_color for switch elements
  if (key === 'switch_handle_color' && val && val.length > 0) {
    applySwitchHandleColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  // Handle switch_off_color for switch elements
  if (key === 'switch_off_color' && val && val.length > 0) {
    applySwitchOffColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  if (key === 'checked_color' && val && val.length > 0) {
    applyCheckedColorEfb(valj_efb[indexVJ].id_, val);
    return;
  }

  if (val.toString().includes('colorDEfb')) {
    let type = ""
    let color = ""
    switch (key.toString()) {
      case 'button_color': type = "btn"; color = valj_efb[indexVJ].style_btn_color ? valj_efb[indexVJ].style_btn_color : ''; break;
      case 'icon_color': type = "icon"; color = valj_efb[indexVJ].style_icon_color ? valj_efb[indexVJ].style_icon_color : ''; break;
      case 'el_text_color': type = "text"; color = valj_efb[indexVJ].style_el_text_color ? valj_efb[indexVJ].style_el_text_color : ''; break;
      case 'label_text_color': type = "text"; color = valj_efb[indexVJ].style_label_color ? valj_efb[indexVJ].style_label_color : ''; break;
      case 'message_text_color': type = "text"; color = valj_efb[indexVJ].style_message_text_color ? valj_efb[indexVJ].style_message_text_color : ''; break;
      case 'el_border_color': type = "border"; color = valj_efb[indexVJ].style_border_color ? valj_efb[indexVJ].style_border_color : ''; break;
      case 'clrdoneTitleEfb': type = "text"; color = valj_efb[indexVJ].clrdoneTitleEfb ? valj_efb[indexVJ].clrdoneTitleEfb.slice(-7) : ''; break;
      case 'clrdoniconEfb': type = "text"; color = valj_efb[indexVJ].clrdoniconEfb ? valj_efb[indexVJ].clrdoniconEfb.slice(-7) : ''; break;
      case 'clrdoneMessageEfb': type = "text"; color = valj_efb[indexVJ].clrdoneMessageEfb ? valj_efb[indexVJ].clrdoneMessageEfb.slice(-7) : ''; break;
      case 'prg_bar_color': type = "btn"; color = valj_efb[0].prg_bar_color ? valj_efb[indexVJ].prg_bar_color.slice(-7) : ''; indexVJ=-1; break;
    }
    if (color != "") addStyleColorBodyEfb((`colorDEfb-${color.slice(1)}`), color.length>6 ? color.slice(-6) : color, type, indexVJ);
  }
}

function send_data_efb() {
  if (state_efb != "run") {
    const cp = funTnxEfb('DemoCode-220201')
    document.getElementById('efb-final-step').innerHTML = cp
  } else {
    endMessage_emsFormBuilder_view()
  }
}

function get_position_col_el(dataId, state) {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  let el_parent = document.getElementById(valj_efb[indx].id_) ?? "null";
  let el_label = document.getElementById(`${valj_efb[indx].id_}_labG`) ?? "null";
  let el_input = document.getElementById(`${valj_efb[indx].id_}-f`) ?? "null";
  let parent_col = ``;
  let label_col = `col-md-12`;
  let input_col = `col-md-12`;
  let parent_row = '';
  const size = valj_efb[indx].hasOwnProperty("size")? Number(valj_efb[indx].size)  : 100
  switch (size) {
    case 100:
      parent_col = 'col-md-12';
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 92:
      parent_col = 'col-md-11'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 80:
    case 83:
      parent_col = 'col-md-10'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 75:
      parent_col = 'col-md-9'
      label_col = `col-md-2`;
      input_col = `col-md-10`;
      break;
    case 67:
      parent_col = 'col-md-8'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 58:
      parent_col = 'col-md-7'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 50:
      parent_col = 'col-md-6'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 42:
      parent_col = 'col-md-5'
      label_col = `col-md-3`;
      input_col = `col-md-9`;
      break;
    case 33:
      parent_col = 'col-md-4'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 25:
      parent_col = 'col-md-3'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 17:
      parent_col = 'col-md-2'
      label_col = `col-md-4`;
      input_col = `col-md-8`;
      break;
    case 8:
      parent_col = 'col-md-1'
      label_col = `col-md-5`;
      input_col = `col-md-5`;
      break;
  }
  if (valj_efb[indx].label_position == "up") {
    label_col = `col-md-12`;
    input_col = `col-md-12`;
    if (state == true) {
    }
  } else {
    parent_row = 'row';
    if (state == true) {
    }
  }
  if (state == true) {
    el_parent.classList = colMdChangerEfb(el_parent.className, parent_col);
   if(el_input!="null") el_input.classList = colMdChangerEfb(el_input.className, input_col);
   if(el_label!="null") el_label.classList = colMdChangerEfb(el_label.className, label_col);
  }
  return [parent_row, parent_col, label_col, input_col]
}

function applyMobileLabelPositionEfb(item) {
  if (!item || !item.id_) return;
  const pos = item.hasOwnProperty('mobile_label_position') ? item.mobile_label_position : 'up';
  const parentEl = document.getElementById(item.id_);
  const labelEl = document.getElementById(`${item.id_}_labG`);
  const inputEl = document.getElementById(`${item.id_}-f`);
  if (pos === 'up') {
    if (parentEl && parentEl.classList.contains('row')) parentEl.classList.remove('row');
    if (labelEl) { labelEl.className = colSmChangerEfb(labelEl.className, 'col-sm-12'); }
    if (inputEl) { inputEl.className = colSmChangerEfb(inputEl.className, 'col-sm-12'); }
  } else {
    if (parentEl && !parentEl.classList.contains('row')) parentEl.classList.add('row');
    if (labelEl) { labelEl.className = colSmChangerEfb(labelEl.className, 'col-sm-4'); }
    if (inputEl) { inputEl.className = colSmChangerEfb(inputEl.className, 'col-sm-8'); }
  }
}

function applyDesktopLabelPositionEfb(item) {
  if (!item || !item.id_) return;
  const parentEl = document.getElementById(item.id_);
  const labelEl = document.getElementById(`${item.id_}_labG`);
  const inputEl = document.getElementById(`${item.id_}-f`);
  const pos = item.hasOwnProperty('label_position') ? item.label_position : 'up';
  if (pos === 'up') {
    if (parentEl && parentEl.classList.contains('row')) parentEl.classList.remove('row');
  } else {
    if (parentEl && !parentEl.classList.contains('row')) parentEl.classList.add('row');
  }
  if (labelEl) { labelEl.className = colSmChangerEfb(labelEl.className, 'col-sm-12'); }
  if (inputEl) { inputEl.className = colSmChangerEfb(inputEl.className, 'col-sm-12'); }
  if (item.type != "stripe" && item.type != "html") get_position_col_el(item.dataId, true);
}

function switchViewEfb(view) {
  currentViewEfb = view;
  const dragBox = document.getElementById('dragBoxWrapperEfb');
  const desktopBtn = document.getElementById('desktopViewBtnEfb');
  const mobileBtn = document.getElementById('mobileViewBtnEfb');
  if (!dragBox || !desktopBtn || !mobileBtn) return;

  if (view === 'mobile') {
    dragBox.classList.add('efb-mobile-view-efb');
    desktopBtn.classList.remove('active');
    mobileBtn.classList.add('active');
    for (let i = 1; i < valj_efb.length; i++) {
      if (valj_efb[i].type !== 'form' && valj_efb[i].type !== 'option' && valj_efb[i].type !== 'steps') {
        get_position_col_mobile_el(valj_efb[i].dataId, true);
        if (valj_efb[i].hasOwnProperty('mobile_label_text_size')) {
          let labSpan = document.getElementById(`${valj_efb[i].id_}_lab`);
          if (labSpan) labSpan.className = fontSizeChangerEfb(labSpan.className, valj_efb[i].mobile_label_text_size);
        }
        if (valj_efb[i].hasOwnProperty('mobile_label_align')) {
          let labG = document.getElementById(`${valj_efb[i].id_}_labG`);
          if (labG) labG.className = alignChangerEfb(labG.className, valj_efb[i].mobile_label_align);
        }
        if (valj_efb[i].hasOwnProperty('mobile_message_align')) {
          let desEl = document.getElementById(`${valj_efb[i].id_}-des`);
          if (desEl) {
            desEl.className = alignChangerElEfb(desEl.className, valj_efb[i].mobile_message_align);
            if (valj_efb[i].mobile_message_align != 'justify-content-start' && desEl.classList.contains('mx-4')) desEl.classList.remove('mx-4');
            else if (valj_efb[i].mobile_message_align == 'justify-content-start' && !desEl.classList.contains('mx-4')) desEl.classList.add('mx-4');
          }
        }
        if (valj_efb[i].hasOwnProperty('mobile_label_position')) {
          applyMobileLabelPositionEfb(valj_efb[i]);
        }
      }
    }
  } else {
    dragBox.classList.remove('efb-mobile-view-efb');
    mobileBtn.classList.remove('active');
    desktopBtn.classList.add('active');
    for (let i = 1; i < valj_efb.length; i++) {
      if (valj_efb[i].type !== 'form' && valj_efb[i].type !== 'option' && valj_efb[i].type !== 'steps') {
        get_position_col_el(valj_efb[i].dataId, true);
        if (valj_efb[i].hasOwnProperty('label_text_size')) {
          let labSpan = document.getElementById(`${valj_efb[i].id_}_lab`);
          if (labSpan) labSpan.className = fontSizeChangerEfb(labSpan.className, valj_efb[i].label_text_size);
        }
        if (valj_efb[i].hasOwnProperty('label_align')) {
          let labG = document.getElementById(`${valj_efb[i].id_}_labG`);
          if (labG) labG.className = alignChangerEfb(labG.className, valj_efb[i].label_align);
        }
        if (valj_efb[i].hasOwnProperty('message_align')) {
          let desEl = document.getElementById(`${valj_efb[i].id_}-des`);
          if (desEl) {
            desEl.className = alignChangerElEfb(desEl.className, valj_efb[i].message_align);
            if (valj_efb[i].message_align != 'justify-content-start' && desEl.classList.contains('mx-4')) desEl.classList.remove('mx-4');
            else if (valj_efb[i].message_align == 'justify-content-start' && !desEl.classList.contains('mx-4')) desEl.classList.add('mx-4');
          }
        }
        applyDesktopLabelPositionEfb(valj_efb[i]);
      }
    }
  }
  updateSideBoxViewEfb(view);
}

function updateSideBoxViewEfb(view) {
  const deskEls = document.querySelectorAll('.efb-desktop-settings-efb');
  const mobEls = document.querySelectorAll('.efb-mobile-settings-efb');
  if (view === 'mobile') {
    deskEls.forEach(el => el.classList.add('d-none'));
    mobEls.forEach(el => el.classList.remove('d-none'));
  } else {
    deskEls.forEach(el => el.classList.remove('d-none'));
    mobEls.forEach(el => el.classList.add('d-none'));
  }
}

function getMobileColClass(item) {
  if (!item || !item.hasOwnProperty('mobile_size')) return 'col-sm-12';
  const ms = Number(item.mobile_size);
  switch(ms) {
    case 8:  return 'col-sm-1';
    case 17: return 'col-sm-2';
    case 25: return 'col-sm-3';
    case 33: return 'col-sm-4';
    case 42: return 'col-sm-5';
    case 50: return 'col-sm-6';
    case 58: return 'col-sm-7';
    case 67: return 'col-sm-8';
    case 75: return 'col-sm-9';
    case 83: return 'col-sm-10';
    case 92: return 'col-sm-11';
    case 100: default: return 'col-sm-12';
  }
}

function get_position_col_mobile_el(dataId, state) {
  const indx = valj_efb.findIndex(x => x.dataId == dataId);
  if (indx === -1) return ['', 'col-sm-12', 'col-sm-12', 'col-sm-12'];
  let el_parent = document.getElementById(valj_efb[indx].id_) ?? "null";
  let el_label = document.getElementById(`${valj_efb[indx].id_}_labG`) ?? "null";
  let el_input = document.getElementById(`${valj_efb[indx].id_}-f`) ?? "null";
  let parent_col = 'col-sm-12';
  let label_col = 'col-sm-12';
  let input_col = 'col-sm-12';
  let parent_row = '';
  const msize = valj_efb[indx].hasOwnProperty("mobile_size") ? Number(valj_efb[indx].mobile_size) : 100;
  switch (msize) {
    case 100: parent_col = 'col-sm-12'; break;
    case 92:  parent_col = 'col-sm-11'; break;
    case 83:  parent_col = 'col-sm-10'; break;
    case 75:  parent_col = 'col-sm-9';  break;
    case 67:  parent_col = 'col-sm-8';  break;
    case 58:  parent_col = 'col-sm-7';  break;
    case 50:  parent_col = 'col-sm-6';  break;
    case 42:  parent_col = 'col-sm-5';  break;
    case 33:  parent_col = 'col-sm-4';  break;
    case 25:  parent_col = 'col-sm-3';  break;
    case 17:  parent_col = 'col-sm-2';  break;
    case 8:   parent_col = 'col-sm-1';  break;
  }
  label_col = 'col-sm-12';
  input_col = 'col-sm-12';
  if (valj_efb[indx].label_position != "up") {
    parent_row = 'row';
  }
  if (state == true) {
    el_parent.className = colSmChangerEfb(el_parent.className, parent_col);
    if (el_input != "null") el_input.className = colSmChangerEfb(el_input.className, input_col);
    if (el_label != "null") el_label.className = colSmChangerEfb(el_label.className, label_col);
  }
  return [parent_row, parent_col, label_col, input_col];
}

fun_captcha_load_efb = ()=>{
  if(valj_efb[0].captcha == true && document.getElementById('dropZoneEFB')) document.getElementById('dropZoneEFB').classList.add('captcha');
  return ` ${sitekye_emsFormBuilder.length > 1 ? `<div class="efb row mx-0"><div id="gRecaptcha" class="efb g-recaptcha my-2 mx-0 px-0" data-sitekey="${sitekye_emsFormBuilder}" data-callback="verifyCaptcha" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
            <!-- fieldset1 -->
            ${state_efb == "view" && valj_efb[0].captcha == true ? `<div class="efb col-12 mb-2 mx-0 mt-3 efb" id="recaptcha_efb"><img src="${efb_var.images.recaptcha}" id="img_recaptcha_perview_efb"></div>` : ''}
            <div id="step-1-efb-msg"></div>`
 }

const add_r_matrix_view_select = (idin, value, id_ob, tag, parentsID) => {
  const indxP = valj_efb.findIndex(x => x.id_ == parentsID);
  let tagtype = tag;
  position_l_efb = efb_var.rtl == 1 ? "end" : "start";
  let col = valj_efb[indxP].hasOwnProperty('op_style') && Number(valj_efb[indxP].op_style) != 1 ? 'col-md-' + (12 / Number(valj_efb[indxP].op_style)) : ''
  return `
    <!---here r_matrix-->
      <div class="efb  col-sm-12 row my-1   t-matrix" data-id="${idin}" data-parent="${parentsID}" id="${idin}-v">
        <div class="efb mt-2 col-md-8 fs-6 ${valj_efb[indxP].el_text_color}  ${valj_efb[indxP].el_height} ${valj_efb[indxP].label_text_size}" id="${idin}_lab">${efb_var.text.newOption}</div>
        <div class="efb col-md-4 d-flex justify-content-${position_l_efb}" id="${idin}" >
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="5"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="4"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="3"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="2"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <div class="efb btn btn-secondary text-white mx-1 disabled"  data-point="1"  data-id="${idin}"  onclick="fun_point_rating(this)"> <i class="efb bi-star-fill"></i></div>
            <input type="hidden" data-vid="${idin}" data-parent="${parentsID} data-type="rating"  id="${idin}-point-rating" >
        </div>
        <hr class="efb t-matrix my-1">
    </div>
    `
}

async function fetch_json_from_url_efb_admin(url) {
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

function valNotFound_efb(){
        alert_message_efb(efb_var.text.error,efb_var.text.empty, 30,'danger');
}

function svg_loading_efb(classes){
return`<span class="efb ${classes}"><svg version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve">
<path  d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50 M30.9,50c0-10.5,8.5-19.1,19.1-19.1S69.1,39.5,69.1,50">
  <animateTransform attributeName="transform" attributeType="XML" type="rotate" dur="1s" from="0 50 50" to="360 50 50" repeatCount="indefinite"></animateTransform>
</path>
</svg><span>`
}

function lan_subdomain_wsteam_efb(){
  let sub ='';
  if(efb_var.language == 'de_DE' || efb_var.language == 'de_AT' ){ sub = 'de.'; }
  else if(efb_var.language == 'ar' || efb_var.language == 'ary' ||  efb_var.language == 'arq'){ sub = 'ar.'; }
  return sub;
}

let currentViewEfb = 'desktop';

const hiddenMarkEl=(id) =>{ return`
<div id="${id}-hidden">
<!--<button type="button" class="efb btn efb pro-bg btn-pro-efb btn-sm px-2 mx-3" id="pro"  data-bs-toggle="tooltip" onclick="pro_show_efb(1)"> -->
<i class="efb  bi-eye-slash pro efb-hidden mx-3" > ${efb_var.text.hField}</i>
</div>
`}

function handle_navbtn_efb(steps, device) {
  var next_s_efb, prev_s_efb;
  var opacity_efb;
  steps_len_efb = Number(steps) + 1;
  current_s_efb = 1;
  setProgressBar_efb(current_s_efb, steps_len_efb);
  if (steps > 1) {
    if (valj_efb[0].type == "payment" && preview_efb != true) {
      let state = valj_efb.findIndex(x => x.type == "stripe");
      state = state == -1 ? valj_efb.findIndex(x => x.type == "persiaPay") : state;
      if (valj_efb[state].step == current_s_efb) {
        document.getElementById("next_efb").classList.add("disabled");
      }
    }
    if (current_s_efb == 1) {
      document.getElementById("prev_efb").classList.toggle("d-none");
    }
    document.getElementById("next_efb").addEventListener("click", function () {
      var cp = current_s_efb + 1;
      var state = true;
      if (preview_efb == false && fun_validation_efb() == false) {
        state = false;
        return false;
      }
      setTimeout(function () {
        var url = new URL(window.location);
        history.pushState("EFBstep-" + cp, null, url);
        if (state = true) {
          if (cp == steps_len_efb) {
            document.getElementById("prev_efb").classList.add("d-none");
            document.getElementById("next_efb").classList.add("d-none");
            send_data_efb();
            document.getElementById('efbform').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
          }
          var current_s = document.querySelector('[data-step="step-' + current_s_efb + '-efb"]');
          current_s.classList.add("d-none");
          next_s_efb = current_s.nextElementSibling;
          const grecaptcha = current_s.querySelector('#gRecaptcha');
          if(grecaptcha && !grecaptcha.classList.contains('d-none') ) { grecaptcha.classList.add('d-none'); }
          var nxt = "" + (current_s_efb + 1) + "";
          if(Number(valj_efb[0].show_icon)!=1){
            document.querySelector('[data-step="icon-s-' + nxt + '-efb"]').classList.add("active");
          }
          document.querySelector('[data-step="step-' + nxt + '-efb"]').classList.toggle("d-none");
          if(next_s_efb)next_s_efb.classList.remove('d-none');
          current_s_efb += 1;
          localStorage.setItem("step", current_s_efb);
          setProgressBar_efb(current_s_efb, steps_len_efb);
          if (current_s_efb <= steps) {
            var val = valj_efb.find(x => x.step == nxt);
            if(Number(valj_efb[0].show_icon)!=1){
              document.getElementById("title_efb").className = val["label_text_color"];
              document.getElementById("desc_efb").className = val["message_text_color"];
              document.getElementById("title_efb").textContent = val["name"];
              document.getElementById("desc_efb").textContent = val["message"]=='' ? val["name"] : val["message"];
              document.getElementById("title_efb").classList.add("text-center", "efb", "mt-1");
              document.getElementById("desc_efb").classList.add("text-center", "efb", "fs-7");
            }
            document.getElementById("prev_efb").classList.remove("d-none");
          }else{
             document.getElementById("title_efb").textContent = efb_var.text.finish;
            document.getElementById("desc_efb").textContent = efb_var.text.finish;

          }
          if (current_s_efb == steps_len_efb - 1) {
            if (sitekye_emsFormBuilder && sitekye_emsFormBuilder.length > 1 && valj_efb[0].captcha == true) {
              document.getElementById("next_efb").classList.toggle("disabled");
              if(grecaptcha) {
                grecaptcha.classList.remove('d-none');
              }
            }
            var val = `<span id="button_group_Next_button_text" class="efb ${valj_efb[0].el_text_color} mx-2">${efb_var.text.send}</span><i class="efb ${valj_efb[0].el_height} ${valj_efb[0].button_Next_icon} ${valj_efb[0].icon_color}" id="button_group_Next_icon"></i>`;
            document.getElementById("next_efb").innerHTML = val;
          }
          if (valj_efb[0].type == "payment" && preview_efb != true) {
            let state = valj_efb.findIndex(x => x.type == "stripe");
            state = state == -1 ? valj_efb.findIndex(x => x.type == "persiaPay") : state;
            if (valj_efb[state].step == current_s_efb && !efb_var.hasOwnProperty("payId") == true) {
              document.getElementById("next_efb").classList.add("disabled");
            }
          }
          if (document.getElementById("body_efb")) {
            document.getElementById("body_efb").scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
          }
        }
      }, 200);
    });
    document.getElementById("prev_efb").addEventListener("click", function () {
      prev_btn_efb();
    });
  } else {
  }
 if(document.querySelector(".submit")){
   document.querySelector(".submit").addEventListener("click", function () {
     return false;
   });
 }
}

function previewFormEfb(state) {
  if (state != "run") {
    state_efb = "view";
    preview_efb = true;
    activeEl_efb = 0;
  }
  let content = `<!--efb.app-->`
  let step_no = 0;
  let head = ``
  let icons = ``
  let pro_bar = ``
  const id = state == "run" ? 'body_efb' : 'settingModalEfb_';
  const len = valj_efb.length;
  const p = calPLenEfb(len)
  let timeout = state == 'run' ? 0 : len * p;
  timeout < 1700 ? timeout = 1700 : 0;
  timeout = state == 'run' ? 0 : timeout;
  if (state != "show" && state != "run") {
    if (valj_efb.length > 2) { sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)) } else {
      show_modal_efb(`<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.formNotFound}</p></div>`, efb_var.text.previewForm, '', 'saveBox');
      state_modal_show_efb(1)
      return;
    }
    if (state == "new") {
      preview_form_new_efb();
      return;
    }else if (state == "pc"){
      show_modal_efb(efbLoadingCard('',4), efb_var.text.previewForm, '', 'saveBox')
      state_modal_show_efb(1)
    }
  }
  try {
    let count =0;
    valj_efb.forEach((value, index) => {
      let t = value.type.toLowerCase();
      if (valj_efb[index].type != "html" && valj_efb[index].type != "link" && valj_efb[index].type != "heading" && valj_efb[index].type != "persiaPay") Object.entries(valj_efb[index]).forEach(([key, val]) => { fun_addStyle_costumize_efb(val.toString(), key, index) });
      if (step_no < value.step && value.type == "step") {
        step_no += 1;
        head += `<li id="${value.id_}" data-step="icon-s-${step_no}-efb"class="efb  ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} ${value.icon_color} ${value.icon}   ${value.step == 1 ? 'active' : ''}" ><strong class="efb  fs-5  ${value.label_text_color} ">${value.name}</strong></li>`
        content += step_no == 1 ? `<fieldset data-step="step-${step_no}-efb" id="step-${step_no}-efb" class="efb my-2 mx-0 px-0 steps-efb efb row">` : `<!-- fieldset!!!? --><div id="step-${Number(step_no)-1}-efb-msg"></div></fieldset><fieldset data-step="step-${step_no}-efb" id="step-${step_no}-efb"  class="efb my-2 mx-0 px-0 steps-efb efb row d-none">`
        if (valj_efb[0].show_icon == false) { }
        if (valj_efb[0].hasOwnProperty('dShowBg') && valj_efb[0].dShowBg == false  && state == "run") {
          document.getElementById('body_efb').classList.add('card')
         }
      }
      if (value.type == 'step' && value.type != 'html') {
        steps_index_efb.push(index)
      } else if (value.type != 'step' && value.type != 'form' && value.type != 'option' && value.type != 'r_matrix' && index>0) {
        content += addNewElement(value.type, value.id_, true, true);
        if (value.type == "html") content += "<!--testHTML-->"
      }
      if((value.hasOwnProperty('disabled') && value.disabled==true && value.hasOwnProperty('hidden')==false)
      || (value.hasOwnProperty('disabled') && value.disabled==true &&
      value.hasOwnProperty('hidden')==true && value.hidden==false)) return;
      if( value.hasOwnProperty('value') && (value.type =='email'|| value.type =='text'|| value.type =='password'|| value.type =='tel'
        || value.type =='number'|| value.type =='url'|| value.type =='textarea'|| value.type =='range' || value.type =='prcfld')){
       if(typeof fun_sendBack_emsFormBuilder=="function" && value.value.length>=1) fun_sendBack_emsFormBuilder({ id_: value.id_, name: value.name, id_ob: value.id_+"_", amount: value.amount, type: value.type, value: value.value, session: sessionPub_emsFormBuilder });
      }else if(typeof fun_sendBack_emsFormBuilder=="function" && value.hasOwnProperty('value') && value.value.length>0 && value.type !='option' ){
        let o=[]
        if(t.includes('radio')==true || value.type=='radio'){
          count+=1;
          let ch = valj_efb.find(x=>x.id_==value.value || x.id_old==value.value);
          o=[{
            id_: value.id_,
            name: value.name,
            id_ob: ch.id_,
            amount: value.amount,
            type: value.type,
            value: ch.value,
            session: sessionPub_emsFormBuilder
        }]
        if(t.includes('pay')){
          Object.assign(o.at(-1),{price:ch.price})
        }
        t=1;
        }else if (t.includes('checkbox')==true){
          count+=1;
          for(let c of value.value){
            let ch = valj_efb.find(x=>x.id_==c);
            o.push({
              id_: value.id_,
              name: value.name,
              id_ob: ch.id_,
              amount: value.amount,
              type: value.type,
              value: ch.value,
              session: sessionPub_emsFormBuilder
              })
            if(t.includes('pay')){
              Object.assign(o.at(-1),{price:ch.price})
            }
          }
          t=1;
        }else if(t.includes('multi')==true){
          count+=1;
          let val='';
          for(let c of value.value){
            let ch = valj_efb.find(x=>x.id_==c);
            val += ch.value+'@efb!'
          }
              o=[{
                id_: value.id_,
                name: value.name,
                amount: value.amount,
                type: value.type,
                value: val,
                session: sessionPub_emsFormBuilder
            }]
            t=1;
        }else if(t.includes('select')==true || t.includes('stateprovince')==true || t.includes('conturylist')==true){
          count+=1;
           let ch = valj_efb.find(x=>x.id_==value.value);
          o=[{
            id_: value.id_,
            name: value.name,
            id_ob: ch.id_,
            amount: value.amount,
            type: value.type,
            value: ch.value,
            session: sessionPub_emsFormBuilder
        }]
        t=1;
        }
        if(t===1){
          for(let i in o){
            fun_sendBack_emsFormBuilder(o[i]);
          }
        }
      }
    })
    step_no += 1;
    const wv = `
  <div class="efb text-center ">
  ${loading_messge_efb()}
  <p class="efb fs-5">${efb_var.text.stf}</p>
  </div>
  `;
    content += `
           ${valj_efb[0].hasOwnProperty('logic')==false ||(valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==false)  ? fun_captcha_load_efb() : '<!--logic efb-->'}
           </fieldset>
          <fieldset data-step="step-${step_no}-efb" class="efb my-5 steps-efb efb row d-none text-center" id="efb-final-step">
            ${valj_efb[0].hasOwnProperty('logic')==true && valj_efb[0].logic==true  ? fun_captcha_load_efb() :wv}
            <!-- fieldset2 -->
            <div id="step-2-efb-msg"></div>
            </fieldset>`
    head += `<li id="f-step-efb"  data-step="icon-s-${step_no}-efb" class="efb  ${valj_efb[1].icon_color} ${valj_efb[0].steps <= 6 ? `step-w-${valj_efb[0].steps}` : `step-w-6`} bi-check-lg mx-0" ><strong class="efb  fs-5 ${valj_efb[1].label_text_color}">${efb_var.text.finish}</strong></li>`
  } catch (error) {
  }
  if (content.length > 10){
    const bgc = valj_efb[0].hasOwnProperty('prg_bar_color') ?valj_efb[0].prg_bar_color: 'btn-primary'
     content += `</div>`
    head = `${Number(valj_efb[0].show_icon)!=1 ? `<ul id="steps-efb" class="efb mb-2 px-2">${head}</ul>` : ''}
    ${valj_efb[0].show_pro_bar == 0 || valj_efb[0].show_pro_bar == false ? `<div class="efb d-flex justify-content-center" id="f-progress-efb"><div class="efb progress mx-3 w-100 ${bgc}"><div class="efb  progress-bar-efb   progress-bar-striped progress-bar-animated" role="progressbar"aria-valuemin="0" aria-valuemax="100"></div></div></div><br> ` : ``}
    `}
  const idn = state == "pre" ? "pre-form-efb" : "pre-efb";
  document.getElementById(id).classList.add(idn)
  content = `
    <div class="efb px-0 pt-2 pb-0 my-1 col-12 mb-2" id="view-efb">
    ${Number(valj_efb[0].show_icon)!=1 ? `<h4 id="title_efb" class="efb fs-3 ${valj_efb[1].label_text_color} text-center mt-3 mb-0">${valj_efb[1].name}</h4><p id="desc_efb" class="efb ${valj_efb[1].message_text_color} text-center  fs-6 mb-2">${valj_efb[1].message}</p>` : ``}
      ${head} <div class="efb mt-1 px-2">${content}</div>
    </div>
    `
  const t = valj_efb[0].steps == 1 ? 0 : 1;
  if (state == 'pc') {
    document.getElementById('dropZoneEFB').innerHTML = '';
    content = `<!-- find xxxx -->` + content;
    show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
    add_buttons_zone_efb(t, 'settingModalEfb-body')
  } else if (state == 'pre') {
    show_modal_efb(content, efb_var.text.pcPreview, 'bi-display', 'saveBox')
    add_buttons_zone_efb(t, 'settingModalEfb-body')
  } else if (state == "mobile") {
    const frame = `
        <div class="efb smartphone-efb">
        <div class="efb content efb" >
            <div id="parentMobileView-efb">
            ${efbLoadingCard('',5)}
            </div>
        </div>
      </div> `
    show_modal_efb(frame, efb_var.text.mobilePreview, 'bi-phone', 'settingBox');
    ReadyElForViewEfb(content)
  } else {
    document.getElementById(id).innerHTML ='<form id="efbform" class="mx-0 px-0 efb">'+ content + add_buttons_zone_efb(t, id) + '</form>';
    if (valj_efb[0].type == "payment") {
     if (efb_var.paymentGateway == "stripe" && typeof post_api_stripe_apay_efb =="function") post_api_stripe_apay_efb();
    }
  }
  let ttype ='text'
  try {
    const len = valj_efb.length;
    valj_efb.forEach((v, i) => {
      let disabled = v.hasOwnProperty('disabled') ? v.disabled : false;
      switch (v.type) {
        case "maps":
          efbCreateMap(v.id_ ,v,false)
          break;
        case "esign":
          const canvas = document.getElementById(`${v.id_}_`);
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
            canvas_id_efb = v.id_;
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

            const el = document.getElementById(`${v.id_}-sig-data`);
            const value = el.value;
            document.getElementById(`${v.id_}_-message`).innerHTML='';
            document.getElementById(`${v.id_}_-message`).style.display='none';
            const o = [{ id_: v.id_, name: v.name, amount: v.amount, type: v.type, value: value, session: sessionPub_emsFormBuilder }];
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
            canvas_id_efb = v.id_;
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

            const value = document.getElementById(`${v.id_}-sig-data`).value;
          }, false);
          break;
        case "multiselect":
        case "payMultiselect":
          let callback = 1;
          function mutlselect(len) {
            setTimeout(() => {
              callback += 1;
              const opd = document.querySelector(`[data-id='${v.id_}_options']`);
              if (opd != null) {
                const corner = v.hasOwnProperty('corner') ? v.corner: 'efb-square';
                opd.className += ` efb emsFormBuilder_v  ${corner} ${v.el_border_color} ${v.el_text_size} ${v.el_height}`;
                opd.onclick = function getMultiSelectvalue() {
                }
                jQuery(function () {
                  jQuery('.selectpicker').selectpicker();
                });
              } else {
                mutlselect(10 * callback);
              }
            }, len);
          }
          mutlselect(len);
          break;
        case "rating":
          break;
        case "dadfile":
          if(disabled)return;
          set_dadfile_fun_efb(v.id_, i,0)
          break;
        case 'ardate':
        case 'pdate':
          ttype= v.type
          break;
      }
    })
  } catch {
  }
  if (state != 'run') (valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic==true) ? logic_handle_navbtn_efb(valj_efb[0].steps, 'pc'):  handle_navbtn_efb(valj_efb[0].steps, 'pc')
  if (state == 'run') {
    sitekye_emsFormBuilder.length > 1 ? loadCaptcha_efb() : '';
    createStepsOfPublic()

  }
  else if(state == 'pc'){
    for (const el of document.querySelectorAll(`.emsFormBuilder_v`)) {
      valueJson_ws = valj_efb;
      el.addEventListener("change", (e) => {
        handle_change_event_efb(el);
      });
    }
  }
  step_el_efb = Number(valj_efb[0].steps);
  if ( state == 'run' &&
  ( (addons_emsFormBuilder.AdnOF==1 && typeof valj_efb[0].AfLnFrm =='string' &&  valj_efb[0].AfLnFrm==1) ) || (valj_efb[0].getway=="persiaPay" && typeof get_authority_efb =="string") ) { fun_offline_Efb()
  }
  if (ttype=='ardate'){
    if(typeof  load_hijir_data_picker_efb=="function"){
      load_hijir_data_picker_efb()
    }else{
      setTimeout(() => {
        alert_message_efb(efb_var.text.iaddon, efb_var.text.IMAddonAD, 20 , 'info')
      }, 1000);
    }
  }else if(ttype=='pdate'){
    if(typeof load_style_persian_data_picker_efb =="function"){
    load_style_persian_data_picker_efb();
  }else{
    setTimeout(() => {
      alert_message_efb(efb_var.text.iaddon, efb_var.text.IMAddonPD, 20 , 'info');
    }, 1000);
  }
  }
}
