console.log('persia pay loaded persia_pay.js');

fun_total_pay_persiaPay_efn=(total)=>{
    //console.log('fun_total_pay_persiaPay_efn');
    total != 0 ? document.getElementById("persiaPayEfb").classList.remove('disabled') : document.getElementById("persiaPayEfb").classList.add('disabled');
}

pay_persia_efb=()=>{
    //console.log('pay_persia_efb');
    const gateWay = valj_efb[0].persiaPay;
    console.log(gateWay);
    if(gateWay=="payping"){
        fun_pay_pp_efb();
    }else if(gateWay=="efb"){

    }
}
fun_pay_pp_efb=()=>{}

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