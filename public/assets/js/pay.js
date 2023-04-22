


function fun_total_pay_efb() {
   
    
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
    if(valj_efb[0].getway=="persiaPay" && typeof fun_total_pay_persiaPay_efn=="function"){ fun_total_pay_persiaPay_efn(total)}
    else if(valj_efb[0].getway=="persiaPay"){
      //console.error('pyament persia not loaded (fun_total_pay_persiaPay_efn)')
    }
  }

  fun_currency_no_convert_efb = (currency, number) => {
    return new Intl.NumberFormat('us', { style: 'currency', currency: currency }).format(number)
  }

  fun_disabled_all_pay_efb = () => {
    let type = '';
    
    if(valj_efb[0].getway!="persiaPay")document.getElementById('stripeCardSectionEfb').classList.add('d-none');
    for (let o of valj_efb) {
      //console.log(o.type.includes('pay'),o);
      if (o.hasOwnProperty('price')==true ) {
        //|| o.type.includes('pay')==true && o.type.includes('payment')==false
        //console.log(o.hasOwnProperty('parent'));
        

       
        if (o.hasOwnProperty('parent')) {
          const p = valj_efb.findIndex(x => x.id_ == o.parent);
          if (p==-1) continue;
          
          if(valj_efb[p].hasOwnProperty('type')==false) continue;
          type = valj_efb[p].type.toLowerCase();
          //if( type != "radio"  && type != "checkbox" && type != "select") continue;
          if(type.includes('pay')==false) continue;
          //console.log(valj_efb[p])
          let ov = document.querySelector(`[data-vid="${o.parent}"]`);
          ov.classList.remove('payefb');
          ov.classList.add('disabled');
        
          ov.disabled = true;
          if (type != "multiselect"  && type != "payMultiselect" && type != "paySelect") {
            const ob = valj_efb.filter(obj => {
              return obj.parent === o.parent
            })
            
            for (let o of ob) {
              ov = document.getElementById(o.id_);
              
              ov.classList.add('disabled');
              ov.classList.remove('payefb');
              ov.disabled = true;
            }//end for
  
          }//end if multiselect 
        }else{
          let ov = document.querySelector(`[data-vid="${o.id_}"]`);
          //console.log(ov)
          ov.classList.add('disabled');        
          ov.disabled = true;
          ov.classList.remove('payefb');
        }
  
      }
    }
  }
  