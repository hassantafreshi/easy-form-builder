
const getUrlback_efb = location.search;

const getUrlparam_efb = new URLSearchParams(getUrlback_efb);
const get_authority_efb = getUrlparam_efb.get('Authority');
const get_Status_efb = getUrlparam_efb.get('Status');

fun_total_pay_persiaPay_efn=(total)=>{
    //console.log('fun_total_pay_persiaPay_efn');
    total != 0 ? document.getElementById("persiaPayEfb").classList.remove('disabled') : document.getElementById("persiaPayEfb").classList.add('disabled');
}

console.log("persia_pay.js 3.5.14");
pay_persia_efb=()=>{
    //console.log('pay_persia_efb');
    const gateWay = valj_efb[0].persiaPay;
    //console.log(gateWay);
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
          <!-- <span class="efb  text-labelEfb one text-capitalize" id="chargeEfb">${efb_var.text.onetime}</span>-->
        </div>
      </div>
      <a class="efb btn my-2 efb p-2 efb-square h-l-efb btn-primary text-decoration-none disabled w-100" onClick="pay_persia_efb()" id="persiaPayEfb">${efb_var.text.payment}</a>
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
  //console.log("btnPersiaPayEfb");
  let btnEfb = document.getElementById('persiaPayEfb');
  btnEfb.innerHTML="لطفا صبر کنید";
  btnEfb.classList.add('disabled')
  let PaymentState = document.getElementById('afterPayefb');

  
  //console.log(ajax_object_efm.ajax_url);
  product = localStorage.getItem('pay_efb')==null ? 2 : localStorage.getItem('pay_efb');
  //console.log(product);
  setTimeout(() => {
    let val=[];
    sendBack_emsFormBuilder_pub.forEach(row => {
      if(row.type.includes('pay')!=false){
        //console.log(row,row.type.includes('pay'));
        val.push(row);
      }
    });
    //console.log('val',val);
              jQuery(function ($) {
                  data = {
                    action: "pay_IRBank_payEfb",
                    value: JSON.stringify(val),
                    id : efb_var.id,                      
                    product:product,
                    name:formNameEfb,
                    nonce: ajax_object_efm.nonce,
                    url :document.URL
                  };
                  //console.log(res);
                  $.ajax({
                    type: "POST",
                    async: false,
                    url: ajax_object_efm.ajax_url,
                    data: data,
                    success: function (res) {         
                      //console.log(res.data) ;    
                      
                      if(res.data.success==true){
                          //console.log(res.data);
                          document.getElementById('beforePay').classList.add('d-none');
                          window.open(res.data.url ,'_self');
                          PaymentState.innerHTML = `<div class="my-5"><h2 class="efb text-center mt-4 text-darkb  fs-4">لطفا صبر کنید در حال انتقال به درگاه بانک</h2>
                          <h3 class="efb text-dark p-0 m-0 mt-1 text-center fs-5">برای انتقال سریعتر به درگاه بانک <a href="${res.data.url}">اینجا را کلیک کنید</a> </h3></div>`;
                          /* console.log("res.data.id,efb_var.id")
                          console.log(res.data.id,efb_var.id) */
                          efb_var.id= res.data.id;
                          localStorage.setItem('PayId',res.data.id);
                      }else{                   
                          PaymentState.innerHTML = `<div class="text-danger efb"> ${res.data.m}</div>`;                                 
                          btnEfb.classList.remove('disabled');
                          btnEfb.innerHTML="پرداخت";
                      }
                      
                    },
                    error: function (res) {
                      console.error(res) ;  
                      btnEfb.classList.remove('disabled'); 
                      PaymentState.innerHTML =`<p class="h4">${res.status}</p> ${res.statusText} </br> ${res.responseText}`
                      btnEfb.innerHTML="پرداخت" 
                    
                    }
                  
                  })
                  PaymentState.classList.remove('d-none');
                }); //end jquery 
  }, 300);
}//end  btnPersiaPayeEfb



fun_after_bankpay_persia_ui =()=>{
  const id = valj_efb[0].steps == 1 ? 'btn_send_efb' : 'next_efb';
  efb_var.id=localStorage.getItem('efbPersiaPayId')
    //console.log(id,document.getElementById(id))
  if ( ((valueJson_ws[0].captcha == true && sitekye_emsFormBuilder.length > 1 && grecaptcha.getResponse().length > 2) || valueJson_ws[0].captcha != true) && document.getElementById(id) || valueJson_ws[0].captcha != true && document.getElementById(id) ) 
    {
      console.log('done!')
      document.getElementById(id).classList.remove('disabled');
     
      //console.log(document.getElementById(id));
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
}