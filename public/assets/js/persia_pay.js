console.log('persia pay loaded persia_pay.js');

fun_total_pay_persiaPay_efn=(total)=>{
    //console.log('fun_total_pay_persiaPay_efn');
    total != 0 ? document.getElementById("persiaPayEfb").classList.remove('disabled') : document.getElementById("persiaPayEfb").classList.add('disabled');
}

pay_persia_efb=()=>{
    //console.log('pay_persia_efb');
    const gateWay = valj_efb[0].persiaPay;
    console.log(gateWay);
    if(gateWay=="zarinPal"){
      fun_pay_pp_efb();
    }else if(gateWay=="efb"){

    }
}
fun_pay_pp_efb=()=>{
  btnPersiaPayEfb()
}

add_ui_persiaPay_efb=(rndm)=>{
    return `
    <div class="efb   card w-100 col-sm-12"  id='${rndm}-f'>
      <div class="efb  p-3 d-block" id="beforePay">
        <div class="efb  headpay border-b row col-md-12 mb-3">
          <div class="efb  h3 col-sm-5">
            <div class="efb  col-12 text-dark"> ${efb_var.text.payAmount}:</div>
            <div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check"></i>پرداخت توسط <span Class="efb fs-6" id="efbPayBy">زرین پال</span></div>
          </div>
          <div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb">
            <span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1">0</span>
            <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">تومان</span>
            <!-- <span class="efb  text-labelEfb one text-capitalize" id="chargeEfb">${efb_var.text.onetime}</span>-->
          </div>
        </div>
        <a class="efb btn my-2 efb p-2 efb-square h-l-efb btn-primary text-decoration-none disabled w-100" onClick="pay_persia_efb()" id="persiaPayEfb">${efb_var.text.payment}</a>
      </div>
      <div class="efb p-3 card w-100 d-none" id="afterPayefb">
      </div>
    </div>
    `;
}


function btnPersiaPayEfb(){
  console.log("btnPersiaPayEfb");
  let btnEfb = document.getElementById('persiaPayEfb');
  let PaymentState = document.getElementById('afterPayefb');

  btnEfb.innerHTML="لطفا صبر کنید";
  btnEfb.classList.add('disabled')
  //console.log(ajax_object_efm.ajax_url);
product = localStorage.getItem('pay_efb')==null ? 2 : localStorage.getItem('pay_efb');
console.log(product);
          jQuery(function ($) {
              data = {
                action: "pay_IRBank_payEfb",
                value: JSON.stringify(sendBack_emsFormBuilder_pub),
                id : efb_var.id,                      
                product:product,
                name:formNameEfb,
                nonce: ajax_object_efm.nonce,
                url :window.location.href
              };
              //console.log(res);
              $.ajax({
                type: "POST",
                async: false,
                url: ajax_object_efm.ajax_url,
                data: data,
                success: function (res) {         
                  console.log(res.data) ;    
      
                  if(res.data.success==true){
                       console.log(res.data);
                      document.getElementById('beforePay').classList.add('d-none');
                      window.open(res.data.url ,'_self');
                      console.log(res.data.re);
                      PaymentState.innerHTML = `<div class="my-5"><h2 class="text-center mt-4 text-muted  fs-4">لطفا صبر کنید در حال انتقال به درگاه بانک</h2>
                      <h3 class="efb text-dark p-0 m-0 mt-1 text-center fs-5">برای انتقال سریعتر به درگاه بانک <a href="${res.data.url}">اینجا را کلیک کنید</a> </h3></div>`;
                    
                      console.log("suc",PaymentState.innerHTML)
                      //active next or send button !!
                      //disable button
               
                  //PaymentState.style.display='block'
                  }else{
                    console.log(res.data.re);
                    PaymentState.innerHTML = `
                      <div clss"text-danger"><strong>Error,  </strong> ${res.data.re}</div>
                      `
                       
                      btnEfb.classList.remove('disabled');
                      btnEfb.innerHTML="پرداخت";


                  }
                  
                },
                error: function (res) {
                  console.error(res) ;  
                  btnEfb.classList.remove('disabled'); 
                  PaymentState.innerHTML =`<p class="h4">${res.status}</p> ${res.statusText} </br> ${res.responseText}`
      
                  //noti_message_efb('Stripe', m, 120, 'danger')
                  btnEfb.innerHTML="پرداخت" 
                
                }
              })
            }); //end jquery 
     


   
    
  

}//end  btnStripeEfb