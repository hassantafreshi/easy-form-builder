console.log('pay.js');
function fun_total_pay_efb() {
    //console.log('fun_total_pay_efb');
    
    let total = 0;
    updateTotal = (i) => {
      i = i.toString().replace(/\B(?<!\.\d*)(?=(\d{3})+(?!\d))/g, ",");
      //totalpayEfb
      for (const l of document.querySelectorAll(".totalpayEfb")) {
        l.innerHTML = i
      }
    }
  
    for (let r of sendBack_emsFormBuilder_pub) {
      if (r.hasOwnProperty('price') ) total += parseFloat(r.price)
    }
    setTimeout(() => { updateTotal(total); }, 800);
    if(valj_efb[0].getway=="persiaPay") fun_total_pay_persiaPay_efn(total)
  }

  fun_currency_no_convert_efb = (currency, number) => {
    return new Intl.NumberFormat('us', { style: 'currency', currency: currency }).format(number)
  }