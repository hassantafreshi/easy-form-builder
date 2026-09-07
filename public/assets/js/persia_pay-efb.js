
const getUrlback_efb = location.search;
let payment_completed_efb = false;
const getUrlparam_efb = new URLSearchParams(getUrlback_efb);
const get_authority_efb = getUrlparam_efb.get('Authority') ?? null;
const get_Status_efb =  getUrlparam_efb.get('Status') ?? null;
fun_total_pay_persiaPay_efn=(total)=>{
    const el = document.getElementById("persiaPayEfb");
    if(el){
      total != 0 ?  el.classList.remove('disabled') : el.classList.add('disabled');
    }
}


pay_persia_efb=()=>{
    const gateWay = valj_efb[0].persiaPay;
    const form_name_session = "efb_form_name_"+efb_var.id;
    const json_send= JSON.stringify(sendBack_emsFormBuilder_pub);
    sessionStorage.setItem(form_name_session, json_send);

    const files_emsFormBuilder_ = JSON.stringify(files_emsFormBuilder);
    sessionStorage.setItem('files_'+form_name_session, files_emsFormBuilder_);
    if(gateWay=="zarinPal"){
      fun_pay_pp_efb();
    }else if(gateWay=="efb"){

    }
}
fun_pay_pp_efb=()=>{
  btnPersiaPayEfb()
}

add_ui_persiaPay_efb=(rndm)=>{
  let r =  `
  <div class="efb   card w-100 col-sm-12"  id='${rndm}-f'>
    <div class="efb  p-3 d-block" id="beforePay">
      <div class="efb  headpay border-b row col-md-12 mb-3">
        <div class="efb  h3 col-sm-5">
          <div class="efb  col-12 text-dark"> ${efb_var.text.payAmount}:</div>
          <div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check"></i>پرداخت توسط <span Class="efb fs-6" id="efbPayBy">زرین پال</span></div>
        </div>
        <div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb">
          <span  class="efb totalpayEfb d-flex justify-content-evenly mx-1 ir">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })}</span>
          <!-- <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">تومان</span> -->
          <!-- <span class="efb  text-labelEfb one" id="chargeEfb">${efb_var.text.onetime}</span>-->
        </div>
      </div>
      <a class="efb btn my-2 efb p-2 rounded-3 h-l-efb btn-primary text-white text-decoration-none disabled w-100" onClick="pay_persia_efb()" id="persiaPayEfb">${efb_var.text.payment}</a>
    </div>
    <div class="efb p-3 card w-100 d-none" id="afterPayefb">
    </div>
  `
  if(get_Status_efb=='OK'){
    r=`
    <div class="efb   card w-100 col-sm-12"  id='${rndm}-f'>
      <div class="efb p-3 card w-100" id="afterPayefb">
      <div class="efb  headpay border-b row col-md-12 mb-3">
          <div class="efb fs-4 text-darkb text-center">پرداخت با موفقیت انجام شد</div>
          <div class="efb fs-5 text-dark text-center">کد پیگیری پرداخت <br> ${get_authority_efb}</div>

        </div>
    `
    change_url_back_persia_pay_efb()
  }
    return r;
}


function btnPersiaPayEfb(){
  if (!navigator.onLine) {

    noti_message_efb(efb_var.text.offlineSend , 'danger' , `beforePay` );
    return;
  }





  product = localStorage.getItem('pay_efb')==null ? 2 : sanitize_text_efb(localStorage.getItem('pay_efb'));

  setTimeout(() => {
    let val=[];
    sendBack_emsFormBuilder_pub.forEach(row => {
      if(row.type.includes('pay')!=false || row.type.includes('prcfld')!=undefined){

        val.push(row);
      }
    });

    data = {
      action: "pay_IRBank_payEfb",
      value: JSON.stringify(val),
      id : efb_var.id,
      product:product,
      name:formNameEfb,
      nonce: ajax_object_efm.nonce,
      url :document.URL,
      sid:efb_var.sid
    };
    sessionStorage.setItem("id", efb_var.id);
    post_api_persiapay_efb(data);
  }, 300);
}

post_api_persiapay_efb=(data)=>{
  let btnEfb = document.getElementById('persiaPayEfb');
  btnEfb.innerHTML="لطفا صبر کنید";
  btnEfb.classList.add('disabled');

  let PaymentState = document.getElementById('afterPayefb');
  const url = efb_var.rest_url+'Emsfb/v1/forms/payment/persia/add';

  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
    });

  const jsonData = JSON.stringify(data);
  const requestOptions = {
  method: 'POST',
  headers,
  body: jsonData,
  };

  fetch(url, requestOptions)
  .then(response => {
    if (!response.ok) {
      throw new Error(`پاسخ نتورک صحیح نیست (HTTP ${response.status})`);
    }
    return response.json();
  })
  .then(res => {
    if (res && res.data && res.data.success === true) {
      document.getElementById('beforePay').classList.add('d-none');
      window.open(res.data.url, '_self');
      PaymentState.innerHTML = `<div class="my-5"><h2 class="efb text-center mt-4 text-darkb fs-4">لطفا صبر کنید در حال انتقال به درگاه بانک</h2>
      <h3 class="efb text-dark p-0 m-0 mt-1 text-center fs-5">برای انتقال سریعتر به درگاه بانک <a href="${res.data.url}">اینجا را کلیک کنید</a> </h3></div>`;
      localStorage.setItem('PayId', res.data.id);
      sessionStorage.setItem("payId", res.data.id);
    } else {
      PaymentState.innerHTML = `<div class="text-danger efb"> ${res.data.m}</div>`;
      btnEfb.classList.remove('disabled');
      btnEfb.innerHTML = "پرداخت";
    }
    PaymentState.classList.remove('d-none');
  })
  .catch(error => {

    console.error(error.message);
    btnEfb.classList.remove('disabled');
    PaymentState.innerHTML = `<p class="h4">${efb_var.text.error}</p> ${error.message}`;
    btnEfb.innerHTML = "پرداخت";
    PaymentState.classList.remove('d-none');
  });


}



fun_after_bankpay_persia_ui =()=>{
  const id = valj_efb[0].steps == 1 ? 'btn_send_efb' : 'next_efb';
  efb_var.id=sanitize_text_efb(efb_var.payId)

  if ( ((valueJson_ws[0].captcha == true && typeof sitekye_emsFormBuilder !== 'undefined' && sitekye_emsFormBuilder.length > 1 && grecaptcha.getResponse().length > 2) || valueJson_ws[0].captcha != true) && document.getElementById(id) || valueJson_ws[0].captcha != true && document.getElementById(id) )
    {

      document.getElementById(id).classList.remove('disabled');


    }
  fun_disabled_all_pay_efb()
      let o = [{
        amount: 0,
        id_: "payment",
        name: "Payment",
        paymentGateway: valj_efb[0].persiaPay,
        paymenauthority: get_authority_efb,
        paymentcurrency: "IRR",
        payment_method: 'card',
        type: "persiapay",
      }];
      sendBack_emsFormBuilder_pub.push(o[0])
}




if(get_Status_efb=="NOK"){
  change_url_back_persia_pay_efb();
  window.alert('پرداخت انجام نشد ، لطفا صفحه را رفرش کنید و دوباره تلاش کنید');
د  }else if(get_Status_efb=="OK"){

  setTimeout(() => {
    if(state_efb!=='run') {

      return;
    }


    if(typeof fun_after_bankpay_persia_ui_efb === 'function') {

      fun_after_bankpay_persia_ui_efb();
    } else {
      console.error('❌ fun_after_bankpay_persia_ui_efb is not defined');
    }


    const steps = valj_efb[0].steps ?? 0;
    if(steps==1){
      const btn_send_efb = document.getElementById('btn_send_efb');
      if(btn_send_efb){
        btn_send_efb.classList.remove('disabled');

      }
    }else{
      const next_efb = document.getElementById('next_efb');
      if(next_efb){
        next_efb.classList.remove('disabled');

      }
    }
  }, 1500);
}


fun_after_bankpay_persia_ui_efb=()=>{

 if(state_efb!=='run') {

   return;
 }

 const last_step = valj_efb[0].steps ?? 0;
 const _index = valj_efb.findIndex(x=>x.type=='persiaPay');

 if(_index==-1){

   return;
 }


 let total_amount = 0;
 valj_efb.forEach(field => {
   if(field.hasOwnProperty('amount') && field.amount) {
     const fieldAmount = Number(field.amount);
     if(!isNaN(fieldAmount)) {
       total_amount += fieldAmount;
     }
   }
 });

 const paymeny_amount = valj_efb[_index].hasOwnProperty('amount') ? Number(valj_efb[_index].amount) : 0;
 current_s_efb = Number(last_step);
 const step_pp = valj_efb[_index].step;



  const showPaymentLoadingAndProceed = (is_multiStep) => {



     const loadingOverlay = document.createElement('div');
     loadingOverlay.id = 'efb-payment-loading';
     loadingOverlay.style.cssText = `
       position: fixed;
       top: 0;
       left: 0;
       width: 100vw;
       height: 100vh;
       background: rgba(0, 0, 0, 0.8);
       z-index: 9999;
       display: flex;
       align-items: center;
       justify-content: center;
       color: white;
       font-family: Arial, sans-serif;
     `;

     loadingOverlay.innerHTML = `
       <div style="text-align: center;">
         <div style="
           width: 50px;
           height: 50px;
           border: 5px solid #f3f3f3;
           border-top: 5px solid #3498db;
           border-radius: 50%;
           animation: spin 1s linear infinite;
           margin: 0 auto 20px;
         "></div>
         <h3 style="margin: 0; font-size: 24px;">پرداخت موفق بود!</h3>
         <p style="margin: 10px 0; font-size: 16px;">در حال انتقال به مرحله آخر&hellip;</p>
         <div id="countdown" style="font-size: 20px; font-weight: bold;">3</div>
       </div>
       <style>
         @keyframes spin {
           0% { transform: rotate(0deg); }
           100% { transform: rotate(360deg); }
         }
       </style>
     `;

     document.body.appendChild(loadingOverlay);



     let countdown = 3;
     const countdownEl = document.getElementById('countdown');
     const countdownTimer = setInterval(() => {
       countdown--;
       if(countdownEl) {
         countdownEl.textContent = countdown;
       }

       if(countdown <= 0) {
         clearInterval(countdownTimer);



         if(loadingOverlay && loadingOverlay.parentNode) {
           loadingOverlay.parentNode.removeChild(loadingOverlay);

         }


         setTimeout(() => {
           if(is_multiStep==true) {


            if (files_emsFormBuilder.length > 0) {
              for (const file of files_emsFormBuilder) {
                if (get_row_sendback_by_id_efb(file.id_) == -1) { sendBack_emsFormBuilder_pub.push(file); localStorage.setItem('sendback', JSON.stringify(sendBack_emsFormBuilder_pub)); }
              }
            }
            if (validation_before_send_emsFormBuilder() == true){ actionSendData_emsFormBuilder(); }
             const nextBtn = document.getElementById('next_efb');
             if(nextBtn) {



               nextBtn.disabled = false;
               nextBtn.classList.remove('disabled');


               const realClickEvent = new MouseEvent('click', {
                 bubbles: true,
                 cancelable: true,
                 view: window
               });

               nextBtn.dispatchEvent(realClickEvent);


             } else {

             }
           } else {

               var state = true;
                if (preview_efb == false && fun_validation_efb() == false) {
                  state = false;
                  return false;
                }
                setTimeout(function () {
                  current_s_efb =2;

                  if (state == true) {
                  if(Number(valj_efb[0].show_icon)!=1)  document.querySelector('[data-step="icon-s-' + (current_s_efb ) + '-efb"]').classList.add("active");
                    document.querySelector('[data-step="step-' + (current_s_efb) + '-efb"]').classList.toggle("d-none");
                    document.getElementById("btn_send_efb").classList.toggle("d-none");
                    var current_s = document.querySelector('[data-step="step-' + (current_s_efb-1) + '-efb"]');
                    next_s_efb = current_s.nextElementSibling;
                    current_s.classList.add('d-none');
                    if(next_s_efb)next_s_efb.classList.remove('d-none');
                    if(document.getElementById('gRecaptcha'))document.getElementById('gRecaptcha').classList.add('d-none');
                    current_s_efb += 1;
                    setProgressBar_efb(current_s_efb, steps_len_efb);
                    send_data_efb();
                  }
                  if (document.getElementById("body_efb")) {
                    document.getElementById("body_efb").scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
                  }
                }, 200);
           }


           const efb_docs = document.getElementById('view-efb');
           const fieldsets = efb_docs ? efb_docs.getElementsByTagName('fieldset') : [];
           const count_filds = fieldsets.length -1;

           for(let i=0; i<count_filds; i++){
             console.error('Hiding fieldset index:', i);
             fieldsets[i].classList.add('d-none');
           }
           if(fieldsets.length > 0) {
             const last_fieldset= fieldsets[count_filds];
             last_fieldset.classList.add('efb-final-step-visible');
             last_fieldset.classList.remove('d-none');
             setTimeout(() => {
               last_fieldset.classList.remove('d-none');

             }, 800);
           }
         }, 100);
       }
     }, 1000);
  };



  if(Number(last_step)==1){

    showPaymentLoadingAndProceed(false);
  }else{

    showPaymentLoadingAndProceed(true);
  }

}