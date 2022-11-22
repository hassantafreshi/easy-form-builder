console.log('pay.js');
function fun_total_pay_efb() {
    //console.log('fun_total_pay_efb');
    
    let total = 0;
    updateTotal = (i) => {
     // i = i.toString().replace(/\B(?<!\.\d*)(?=(\d{3})+(?!\d))/g, ",");
      //totalpayEfb
      for (const l of document.querySelectorAll(".totalpayEfb")) {
        l.innerHTML = Number(i).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })
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

  fun_disabled_all_pay_efb = () => {
    let type = '';
    //console.log('fun_disabled_all_pay_efb');
    if(valj_efb[0].getway!="persiaPay")document.getElementById('stripeCardSectionEfb').classList.add('d-none');
    for (let o of valj_efb) {
      //console.log(o.type.includes('pay'),o);
      if (o.hasOwnProperty('price')==true || o.type.includes('pay')>0 && o.type.includes('payment')==false) {
        //console.log(o.hasOwnProperty('parent'));
        if (o.hasOwnProperty('parent')) {
          const p = valj_efb.findIndex(x => x.id_ == o.parent);
          type = valj_efb[p].type;
          //console.log(o.parent,p,type);
          let ov = document.querySelector(`[data-vid="${o.parent}"]`);
          ov.classList.remove('payefb');
          ov.classList.add('disabled');
        
          ov.disabled = true;
          if (type != "multiselect" && type != "select" && type != "payMultiselect" && type != "paySelect") {
            const ob = valj_efb.filter(obj => {
              return obj.parent === o.parent
            })
            //console.log(ob);
            for (let o of ob) {
              ov = document.getElementById(o.id_);
              //console.log(ov);
              ov.classList.add('disabled');
              ov.classList.remove('payefb');
              ov.disabled = true;
            }//end for
  
          }//end if multiselect 
        }else{
          /* console.log(o.id_ , o,document.getElementById('xmnguttql_o'))
          console.log("test",document.getElementById('efbform')) */
          let ov = document.querySelector(`[data-vid="${o.id_}"]`);
          //console.log(ov)
          ov.classList.add('disabled');        
          ov.disabled = true;
          ov.classList.remove('payefb');
        }
  
      }
    }
  }
  