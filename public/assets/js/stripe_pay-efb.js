
function confirm_stripe_payment_efb(paymentIntentId, trackid) {
  const confirmUrl = efb_var.rest_url + 'Emsfb/v1/forms/payment/stripe/confirm';
  const confirmHeaders = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
  });
  fetch(confirmUrl, {
    method: 'POST',
    headers: confirmHeaders,
    body: JSON.stringify({ paymentIntentId: paymentIntentId, trackid: trackid })
  }).then(function(response) {
    return response.json();
  }).then(function(res) {
    if (res && res.data && res.data.success) {
    } else {
    }
  }).catch(function(err) {
  });
}

  post_api_stripe_apay_efb=(form_id)=>{
    if (!navigator.onLine) {
      alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')
      return;
    }

    const url = efb_var.rest_url+'Emsfb/v1/forms/payment/stripe/card/add';
    const headers = new Headers({
      'Content-Type': 'application/json',
      'X-WP-Nonce': efb_var.nonce,
      'form-id': form_id ? form_id : 0,
      'sid':efb_var.sid ? efb_var.sid : '',
      });

        const bdy = document.getElementById('body_efb_'+form_id);
        const cardnoEfb = bdy.querySelector('#cardnoEfb')
        if (typeof cardnoEfb != "object") return;

        if (ajax_object_efm.hasOwnProperty('paymentKey')) {
          if (ajax_object_efm.paymentKey == "null") {
            alert_message_efb(efb_var.text.error, `${efb_var.text.errorCode}: Payment->Stripe`, 100, 'danger');
            return;
          }
          const stripe = Stripe(ajax_object_efm.paymentKey, { locale: 'auto' })

          const elsStripeStyleEfb = {
            base: {
              iconColor: '#6c757d',
              color: '#6c757d',
              fontFamily: 'sans-serif',
              fontSize: '30px',
              '::placeholder': { color: '#757593' }
            },
            complete: { color: 'green' }
          }
          const btntripeStyleEfb = {
            fontFamily: 'sans-serif',
            fontSize: '20px',
            fontWeight: '400',
            complete: { color: 'green' }
          }

          const cardnoEfb = bdy.querySelector('#cardnoEfb')
          const cardexpEfb = bdy.querySelector('#cardexpEfb')
          const cardcvcEfb = bdy.querySelector('#cardcvcEfb')
          const btnStripeEfb = bdy.querySelector('#btnStripeEfb')
          const stsStripeEfb = bdy.querySelector('#statusStripEfb')
          const elements = stripe.elements()
          const numElm = elements.create('cardNumber', { showIcon: true, iconStyle: 'solid', style: elsStripeStyleEfb })
          numElm.mount(cardnoEfb)

          const expElm = elements.create('cardExpiry', { disabled: true, style: elsStripeStyleEfb })
          expElm.mount(cardexpEfb)

          const cvcElm = elements.create('cardCvc', { disabled: true, style: elsStripeStyleEfb })
          cvcElm.mount(cardcvcEfb)

          numElm.on('change', (e) => {
            if (e.complete) {
              expElm.update({ disabled: false })
              expElm.focus()
            }
          })

          expElm.on('change', (e) => {
            if (e.complete) {
              cvcElm.update({ disabled: false })
              cvcElm.focus()
            }
          })

          cvcElm.on('change', (e) => {

            if (e.complete) {

              btnStripeEfb.classList.remove('disabled');

            }
          })

          fun_fetch_api_efb=(data ,transStat)=>{
            const jsonData = JSON.stringify(data);
            const requestOptions = {
            method: 'POST',
            headers,
            body: jsonData,
            };

  fetch(url, requestOptions)
  .then(response => {
    if (!response.ok) {
      throw new Error(`Network response was not ok (HTTP ${response.status})`);
    }
    return response.json();
  })
  .then(res => {
    if (res && res.data && res.data.success === true) {
      if (valj_efb[0].paymentmethod === "charge") {
        stripe.confirmCardPayment(res.data.client_secret, {
          payment_method: { card: numElm }
        }).then(transStat => {
          if (transStat && transStat.paymentIntent && transStat.paymentIntent.status === 'succeeded') {
            confirm_stripe_payment_efb(transStat.paymentIntent.id, res.data.id);
          }
          fun_trans_efb(transStat, res.data.transStat, res.data.id);
        });
      } else {
        fun_trans_efb(transStat, res.data.transStat, res.data.id);
      }
    } else {
      stsStripeEfb.innerHTML = `<div class="text-danger"><strong>${efb_var.text.error}</strong></div>`;
      btnStripeEfb.classList.remove('disabled');
      btnStripeEfb.innerHTML = efb_var.text.payNow;
    }
  })
  .catch(error => {
    btnStripeEfb.classList.remove('disabled');
    const errorMessage = `<p class="efb h4">${efb_var.text.error} ${error.message}</p>`;
    alert_message_efb('Stripe', errorMessage, 120, 'danger');
    btnStripeEfb.innerHTML = efb_var.text.payNow;
  });

}

          btnStripeEfb.addEventListener('click', () => {
            btnStripeEfb.classList.add('disabled');
            btnStripeEfb.innerHTML = efb_var.text.pleaseWaiting;

            const v = fun_pay_valid_price();
            if (v == false) {
              alert_message_efb(efb_var.text.error, efb_var.text.emptyCartM, 10, 'warning')
              btnStripeEfb.innerHTML = efb_var.text.payNow;
              btnStripeEfb.classList.remove('disabled');
              return false;
            } else {

                if(valj_efb[0].paymentmethod != "charge"){
                  stripe.createToken(numElm).then((transStat) => {
                    if (transStat.error) {
                      stsStripeEfb.innerHTML = `<p class="h4">${transStat.status}</p> ${transStat.statusText} </br> ${transStat.responseText}`

                    } else {
                      data = {
                        action: "pay_stripe_sub_efb",
                        value: JSON.stringify(sendBack_emsFormBuilder_pub),
                        name: formNameEfb,
                        id: form_id,
                        nonce: efb_var.nonce,
                        token: transStat.token.id,
                        sid:efb_var.sid
                      };
                      fun_fetch_api_efb(data,transStat);
                    }
                  });
                }else{
                  data = {
                    action: "pay_stripe_sub_efb",
                    value: JSON.stringify(sendBack_emsFormBuilder_pub),
                    name: formNameEfb,
                    id: form_id,
                    nonce: efb_var.nonce,
                    sid:efb_var.sid
                  };
                 const transStat="";
                  fun_fetch_api_efb(data,transStat);
                }

              }
            });
          }else{
            if (efb_var.pro==true || efb_var.pro=="true") alert_message_efb(efb_var.text.error, `${efb_var.text.errorCode}: ${efb_var.text.payment}->${efb_var.text.proVersion}`, 100, 'danger');
          }
          fun_trans_efb = (transStat, data, trackid) => {

            if (transStat.error) {
              stsStripeEfb.innerHTML = `
                  <strong>${efb_var.text.error}  </string> ${transStat.error.message}
                  `
              alert_message_efb(efb_var.text.error, transStat.error.message, 10, 'warning')
              btnStripeEfb.classList.remove('disabled');
              btnStripeEfb.innerHTML = efb_var.text.payNow
            }
            else {
              const id = valj_efb[0].steps == 1 ? 'btn_send_efb' : 'next_efb';

              if (((valueJson_ws[0].captcha == true && sitekye_emsFormBuilder.length > 1 &&
                grecaptcha.getResponse().length > 2) || valueJson_ws[0].captcha == false)) {
                  bdy.querySelector('#' + id).classList.remove('disabled');
                }
                fun_disabled_all_pay_efb()
              val = `

                  <p class="efb  text-muted p-0 m-0"><b>${efb_var.text.transctionId}:</b> ${data.paymentIntent}</p>
                  <!-- <p class="efb  text-muted p-0 m-0 "><b>${efb_var.text.payAmount}</b> : ${data.total} ${data.paymentcurrency.toUpperCase()}</p>-->
                  <p class="efb  text-muted p-0 m-0 "><b>${efb_var.text.payAmount}</b> :
                  ${Number(data.total).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: data.paymentcurrency })}</p>
                  <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.ddate}</b>: ${data.paymentCreated}</p>
                  `;
              if (valj_efb[0].paymentmethod != "charge") {
                val += `
                     <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.interval}</b>: ${data.interval}</p>
                     <p class="efb text-muted p-0 m-0 mb-1"><b>${efb_var.text.nextBillingD}</b> : ${data.nextDate}</p>`
              }

              statusStripEfb.innerHTML = `
                  <h3 class="efb  text-darkb p-0 m-0 mt-1 text-center"><i class="efb bi-check2-circle"></i> ${efb_var.text.successPayment}</h3>
                  <p class="efb  text-muted p-0  m-0 mb-2 text-center">${data.description}</p>
                  <div class="m-3">${val}</div>`;
              const form_id = btnStripeEfb.dataset.formid || 0;
              let o = [{
                amount: 0,
                id_: "payment",
                name: "Payment",
                paymentAmount: data.paymentAmount,
                paymentCreated: data.paymentCreated,
                paymentGateway: data.paymentGateway,
                paymentIntent: data.paymentIntent,
                paymentcurrency: data.paymentcurrency,
                payment_method: 'card',
                type: "payment",
                paymentmethod: data.paymentmethod,
                value: `${data.total}`,
                form_id: form_id
              }];
              localStorage.setItem('PayId',trackid);
              efb_var.payId= trackid;
              sendBack_emsFormBuilder_pub.push(o[0])
              check_form_payment_filled_efb(form_id);
              btnStripeEfb.innerHTML = "Done"
              btnStripeEfb.style.display = "none";
              jQuery("#statusStripEfb").show("slow");
            }
           statusStripEfb.style.display = 'block'
          }

    }
  add_ui_stripe_efb = (rndm ,cl,sub,form_id) => {
    if(!valj_efb[0].hasOwnProperty('currency')){ Object.assign(valj_efb[0], {currency: 'USD'}); }
    return  `
    <!-- stripe -->
    <div class="efb  col-sm-12 stripe"  id='${rndm}-f'>
    <div class="efb  stripe-bg  p-3 card w-100">
    <div class="efb  headpay border-b row col-md-12 mb-3">
      <div class="efb  h3 col-sm-5">
        <div class="efb  col-12 text-dark"> ${efb_var.text.payAmount}:</div>
        <div class="efb  text-labelEfb mx-2 my-1 fs-7"> <i class="efb mx-1 bi-shield-check" id="powerby_icon_${form_id}"></i><span class="efb" id="powerby_label_${form_id}">Powered by Stripe</span></div>
      </div>
      <div class="efb  h3 col-sm-7 d-flex justify-content-end" id="payPriceEfb">
        <span  class="efb  totalpayEfb d-flex justify-content-evenly mx-1" id="totalpayEfb_${rndm}">${Number(0).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: valj_efb[0].currency })}</span>
        <!-- <span class="efb currencyPayEfb fs-5" id="currencyPayEfb">${valj_efb[0].currency.toUpperCase()}</span> -->
        <span class="efb  text-labelEfb ${cl} text-capitalize" id="chargeEfb">${sub}</span>
      </div>
    </div>
    <div id="stripeCardSectionEfb" class="efb ">
      <div class="efb  col-md-12 my-2">
      <label for="cardnoEfb" class="efb fs-6 text-dark priceEfb">${efb_var.text.cardNumber}: </label>
      <div id="cardnoEfb" class="efb form-control h-d-efb text-labelEfb"></div>
      </div>
      <div class="efb  col-sm-12 row my-2">
        <div class="efb  col-sm-6 my-2">
        <label for="cardexpEfb" class="efb  fs-6 text-dark priceEfb">${efb_var.text.cardExpiry}: </label>
        <div id="cardexpEfb" class="efb form-control h-d-efb text-labelEfb"></div>
        </div>
        <div class="efb  col-sm-6 my-2">
        <label for="cardcvcEfb" class="efb  fs-6 text-dark priceEfb">${efb_var.text.cardCVC}: </label>
        <div id="cardcvcEfb" class="efb form-control h-d-efb text-labelEfb"></div>
        </div>
      </div>
    </div>
    <a class="efb  btn my-2 efb p-2 efb-square h-l-efb  efb-btn-lg float-end text-decoration-none disabled ${pub_bg_button_color_efb} text-white" id="btnStripeEfb">${efb_var.text.payNow}</a>
    <div class="efb  bg-light border-d rounded-3 p-2 bg-muted" id="statusStripEfb" style="display: none"></div>
    </div>
    </div>
    <!-- end stripe -->
    `
}

fun_pay_valid_price = () => {
  let s = false;
  let price = 0
  for (let o of sendBack_emsFormBuilder_pub) {
    if (o.hasOwnProperty('price')) price += parseFloat(o.price)
  }
  s = price > 0 ? true : false;

  return s;
}
