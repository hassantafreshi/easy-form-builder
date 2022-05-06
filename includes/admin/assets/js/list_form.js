

let valueJson_ws_form = [];
let valueJson_ws_messages = [];
let valueJson_ws_setting = []
let state_seting_emsFormBuilder = false;
let poster_emsFormBuilder = '';
let response_state_efb ;
const colors_efb = ['#0013CB', '#E90056', '#7CEF00', '#FFBA00', '#FF3888', '#526AFF', '#FFC738', '#A6FF38', '#303563', '#7D324E', '#5D8234', '#8F783A', '#FB5D9D', '#FFA938', '#45B2FF', '#A6FF38', '#0011B4', '#8300AD', '#E9FB00', '#FFBA00']

jQuery(function () {
  //ajax_object_efm.ajax_url ایجکس ادمین برای برگرداند مقدار لازم می شود
  //ajax_object_efm.ajax_value مقدار جی سون
  //ajax_object_efm.language زبان بر می گرداند
  //ajax_object_efm.messages_state پیام های خوانده نشده را بر می گرداند
  valueJson_ws_form = ajax_object_efm.ajax_value;
  poster_emsFormBuilder = ajax_object_efm.poster
  response_state_efb=ajax_object_efm.response_state;
  pro_ws = ajax_object_efm.pro =='1' ? true :false;
 
  if(ajax_object_efm.setting,ajax_object_efm.setting.length>0){
    valueJson_ws_setting = (JSON.parse(ajax_object_efm.setting[0].setting.replace(/[\\]/g, '')));
      //console.log(valueJson_ws_setting ,ajax_object_efm ,valueJson_ws_setting.bootstrap==0 &&   ajax_object_efm.bootstrap==1)
    if(valueJson_ws_setting.bootstrap==0 &&   ajax_object_efm.bootstrap==1){
      if(localStorage.getItem('bootstrap_w')=== null) localStorage.setItem('bootstrap_w',0)
      if (localStorage.getItem('bootstrap_w')>=0 && localStorage.getItem('bootstrap_w') <3) {
        localStorage.setItem('bootstrap_w',(parseInt(localStorage.getItem('bootstrap_w'))+1))
        setTimeout(() => { console.log('bootstrap'); noti_message_efb(efb_var.text.warningBootStrap, "", 30 ,"danger")}, 500);
      }
    }
  }
  fun_emsFormBuilder_render_view(25); //778899
});

let count_row_emsFormBuilder = 0;


// تابع نمایش فرم اصلی
function fun_emsFormBuilder_render_view(x) {
  // v2

  let rows = ""
  let o_rows =""
  count_row_emsFormBuilder = x;
  let count = 0;
  fun_backButton(2);

  function creatRow(i,newM){
    return ` <tr class="pointer-efb efb" id="emsFormBuilder-tr-${i.form_id}" >                    
   <th scope="row" class="emsFormBuilder-tr" data-id="${i.form_id}" >
     [EMS_Form_Builder id=${Number(i.form_id)}]  
   </th>
   <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_name}</td>
   <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_create_date}</td>
   <td > 
   <button type="button" class="efb btn btn-delete btn-sm" onClick ="emsFormBuilder_delete(${i.form_id})" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${efb_var.text.delete}"><i class="efb bi-trash"></i></button>
   <button type="button" class="efb btn-action-edit btn-sm" onClick="emsFormBuilder_get_edit_form(${i.form_id})" data-id="${i.form_id}"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${efb_var.text.edit}"><i class="efb bi-pencil"></i></button>
   <button type="button" class="efb btn btn-comment btn-sm" onClick="emsFormBuilder_messages(${i.form_id})" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${newM == true ? efb_var.text.newResponse : efb_var.text.read}">${newM == true ? `<svg xmlns="http://www.w3.org/2000/svg" class="jump" width="14" height="14" fill="currentColor" class="bi bi-chat-fill" viewBox="0 0 16 16"><path d="M8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6-.097 1.016-.417 2.13-.771 2.966-.079.186.074.394.273.362 2.256-.37 3.597-.938 4.18-1.234A9.06 9.06 0 0 0 8 15z"/></svg>` : `<i class="efb bi-chat text-muted"></i>`}</button>
   <input type="text"  class="efb d-none" value='[EMS_Form_Builder id=${Number(i.form_id)}]' id="${i.form_id}-fc">
   <button type="button" class=" d-none efb btn btn-darkb text-white btn-sm bi-clipboard-check" 
     onClick ="copyCodeEfb('${i.form_id}-fc')" 
     data-bs-toggle="tooltip" data-bs-placement="bottom"
     title="${efb_var.text.copy}">
   </button></th>
   </td>                               
  </tr>
  `
   }
  if (valueJson_ws_form.length > 0) {
    for (let i of valueJson_ws_form) {
      if (x > count) {
        let newM = false;
        const d=  ajax_object_efm.messages_state.findIndex(x=>x.form_id==i.form_id)
        if(d!=-1){newM = true;}
        const b=  ajax_object_efm.response_state.findIndex(x=>x.form_id==i.form_id)
        if(b!=-1){newM = true;}
        //response
        newM != true  ? o_rows +=creatRow(i,newM) : rows +=creatRow(i,newM); 
        count += 1;
      }
    }
    rows += o_rows;
    if (valueJson_ws_form.length < x) {
      document.getElementById("more_emsFormBuilder").style.display = "none";
    }


    document.getElementById('content-efb').innerHTML = `
   <h4 class="title-holder efb"> <img src="${efb_var.images.title}" class="title efb">
                <i class="efb bi-archive title-icon  mx-1"></i>${efb_var.text.forms}
            </h4>
    <div class="card efb">
    <table class="table table-striped table-hover mt-3" id="emsFormBuilder-list">
        <thead>
            <tr >
            <th scope="col">${efb_var.text.formCode}</th>
            <th scope="col">${efb_var.text.formName}</th>
            <th scope="col">${efb_var.text.createDate}</th>
            <th scope="col">${efb_var.text.edit}</th>
            </tr>
        </thead>
        <tbody>
        ${rows}
        </tbody>
    </table>
 </div>
 `

  } else {
    fun_backButton(1);
    document.getElementById('content-efb').innerHTML = head_introduce_efb('panel')
    document.getElementById('content-efb').classList.add('m-1');
  }



  for (const el of document.querySelectorAll(`.emsFormBuilder-tr`)) {
    el.addEventListener("click", (e) => {emsFormBuilder_messages(el.dataset.id)});
  }
}

function emsFormBuilder_waiting_response() {
  document.getElementById('emsFormBuilder-list').innerHTML = loading_messge_efb()
}


function emsFormBuilder_get_edit_form(id) {
  //fun_backButton()
  fun_backButton();
  emsFormBuilder_waiting_response();
  fun_get_form_by_id(id);
}


// نمایش پنجره پیغام حذف یک ردیف  فرم
function emsFormBuilder_delete(id) {
  //v2
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill mx-2', 'deleteBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  const confirmBtn = document.getElementById('modalConfirmBtnEfb');

  myModal.show();
  confirmBtn.addEventListener("click", (e) => {
    fun_confirm_remove_emsFormBuilder(Number(id))
    activeEl_efb = 0;
    myModal.hide()
  })
  //myModal.show();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function emsFormBuilder_show_content_message(id) {
  // v2
  const formType = form_type_emsFormBuilder;
  // پنجره نمایش فرم ثبت شده کاربر  
  const indx = valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const objOptions = valueJson_ws_messages.filter(obj => { return obj.msg_id === id.toString()})
  const msg_id = valueJson_ws_messages[indx].msg_id;
  const userIp = valueJson_ws_messages[indx].ip;
  const track = valueJson_ws_messages[indx].track;
  const date = valueJson_ws_messages[indx].date;
  const content = JSON.parse(valueJson_ws_messages[indx].content.replace(/[\\]/g, ''));
  let m = "<--messages-->"
  let by = valueJson_ws_messages[indx].read_by !== null ? valueJson_ws_messages[indx].read_by : "Unkown"
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  m = fun_emsFormBuilder_show_messages(content, by, userIp, track, date)
  //reply  message ui
  form_type_emsFormBuilder = formType;
  const replayM = function () {
    let r
    if (form_type_emsFormBuilder != 'subscribe' && form_type_emsFormBuilder != 'register' && form_type_emsFormBuilder != 'survey') {
      r = `   
      <div class="mb-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"  id="replay_section__emsFormBuilder">
        <label for="replayM_emsFormBuilder" class="form-label m-2">${efb_var.text.reply}:</label>
        <textarea class=" efb form-control efb" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
      </div>
     <div class="col text-right row mx-1">
     <button type="submit" class="btn efb btn-primary btn-sm" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})"><i class="efb bi-reply mx-1"></i> ${efb_var.text.reply} </button>
     <p class="mx-2 my-1 text-pinkEfb efb" id="replay_state__emsFormBuilder"></p>
     </div></div>`;
    } else {r = '<!-- comment --!>';}
    return r;
  }

  const body = `
    <div class="efb modal-body overflow-auto py-0 my-0  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="resp_efb">
      ${m} 
     </div>
     ${replayM()}
     </div></div></div><div></div></div>`;




  show_modal_efb(body, efb_var.text.response, 'efb bi-chat-square-text mx-2', 'saveBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show();

  // fun_add_event_CloseMenu();

  window.scrollTo({ top: 0, behavior: 'smooth' });

}

// نمایش و عدم نمایش دکمه های صفحه اصلی
function fun_backButton(state) {
  if (document.getElementById("more_emsFormBuilder").style.display == "block" && state == 1) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
    (document.getElementById("more_emsFormBuilder").style.display ,255)
  } else {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }


  if (state == 0 || state == null) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
  }else if (state == 2) {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }
}

// تابع بستن پنجره اورپیج
function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();
  // if (i==1) previewemsFormBuilder=false;
}


// حذف یک ردیف از جدول نمایشی
function fun_confirm_remove_emsFormBuilder(id) {
  // ای دی از جی سون پیدا شود حذف شود و به سمت سرور پیام حذف ارسال شود
  // صفحه رفرش شود
  fun_delete_form_with_id_by_server(parseInt(id));

  //کد زیر حذف نشود
  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => x.form_id == id) : -1
  if (foundIndex != -1) valueJson_ws_form.splice(foundIndex, 1);
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  //close_overpage_emsFormBuilder();

}


// دکمه بازگشت و نمایش لیست اصلی
function fun_emsFormBuilder_back() {
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
}


function fun_emsFormBuilder_show_messages(content, by, userIp, track, date) {
//response7788  
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  let m = `<Div class="bg-response efb card-body my-2 py-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
   <p class="small mb-0"><span>${efb_var.text.by}:</span> ${by}</p>
   <p class="small mb-0"><span>${efb_var.text.ip}:</span> ${userIp}</p>
  ${track != 0 ? `<p class="small mb-0"><span> ${efb_var.text.trackNo}:</span> ${track} </p>` : ''}
  <p class="small mb-0"><span>${efb_var.text.ddate}:</span> ${date} </p>  
  <hr>
  <h6 class="efb text-dark my-2">${efb_var.text.response} </h6>`;
  content.sort((a, b) => (a.amount > b.amount) ? 1 : -1);
  let list = []
  let s= false;
  for (const c of content) {
    console.log(c,list);
    s= false;
    let value = `<b>${c.value.toString().replaceAll('@efb!' ,',')}</b>`;    
    if (c.value == "@file@" && list.findIndex(x=>x == c.url)==-1) {
      s=true;
      list.push(c.url);
      $name =c.url.slice((c.url.lastIndexOf("/")+1), (c.url.lastIndexOf("."))) ;
      console.log($name,c.type ,"URL", c.url);
      if (c.type == "Image" ||c.type == "image") {
        value = `</br><img src="${c.url}" alt="${c.name}" class="img-thumbnail m-1">`
      }else if (c.type == "Document" ||c.type == "document") {
        value = `</br><a class="btn btn-primary m-1" href="${c.url}" target="_blank" download="${$name}">${efb_var.text.download}</a>`
      } else if (c.type == "Media" ||c.type == "media") {
        const audios = ['mp3', 'wav', 'ogg'];
        let media = "video";
        audios.forEach(function (aud) {
          if (c.url.indexOf(aud) !== -1) {
            media = 'audio';
          }
        })
        if (media == "video") {
          const len = c.url.length;
          const type = c.url.slice((len - c.url.lastIndexOf(x=>x==".")), len);          
          value = type !== 'avi' ? `</br><div class="px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="text-center" ><a href="${c.url}">${efb_var.text.videoDownloadLink}</a></p>` : `<p class="text-center"><a href="${c.url}">${efb_var.text.downloadViedo}</a></p>`;
        } else {
          value = `<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
      } else {        
        value = c.url.length>1 ? `</br><a class="btn btn-primary" href="${c.url}" target="_blank" download="${$name}">${c.name}</a>` : `<span class="efb fs-5">💤</span>`
      }
    }else if (c.type == "esign") {
      s=true;
      value= `<img src="${c.value}" alt="${c.name}" class="img-thumbnail">`
    }else if(c.type=="maps"){
     
      if(typeof(c.value)=="object"){
        s=true;
        value = `<div id="${c.id_}-map" data-type="maps" class="efb maps-efb h-d-efb  required " data-id="${c.id_}-el" data-name="maps"><h1>maps</h1></div>`;
        valj_efb.push({id_:c.id_ ,mark:-1 ,lat:c.value[0].lat , lng:c.value[0].lng ,zoom:9 , type:"maps" })
        marker_maps_efb= c.value;
        initMap();

      }
    }else if(c.type=="rating"){
      s=true;
      value=`<div class='fs-5 star-checked star-efb mx-1 ${efb_var.rtl == 1 ? 'text-end' : 'text-start'}'>`;      
      for(let i=0 ; i<parseInt(c.value) ; i++){
        value += `<i class="bi bi-star-fill"></i>`
      }
      value+="</div>";
    }
    if (c.id_ == 'passwordRegisterEFB') value = '**********';
    if( (s==true && c.value == "@file@") || (s==false && c.value != "@file@")) m += `<p class="my-0">${c.name}: <span class="mb-1"> ${value !== '<b>@file@</b>' ? value : ''}</span> </p> `
  }
  m += '</div>';  
  return m;
}



// دکمه نمایش بیشتر لیست اصلی
function fun_emsFormBuilder_more() {
  count_row_emsFormBuilder += 5;
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })

}

// تابع نمایش ویرایش فرم
function fun_ws_show_edit_form(id) {
  //valj_efb = JSON.parse(localStorage.getItem("valj_efb"));  
  const len = valj_efb.length;
  creator_form_builder_Efb();
  setTimeout(() => {
    editFormEfb()
  }, 500)
 

}


function fun_send_replayMessage_emsFormBuilder(id) {
  //پاسخ مدیر را ارسال می کند به سرور 


  const message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g, '</br>');  
  document.getElementById('replay_state__emsFormBuilder').innerHTML = `<i class="bi-hourglass-split mx-1"></i> ${efb_var.text.sending}`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  // +='disabled fas fa-spinner fa-pulse';
  const ob = [{ name: 'Message', value: message, by: ajax_object_efm.user_name }];  
  let isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);
  if (message.length < 1 || isHTML(message)) {
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6><i class="bi-exclamation-triangle-fill text-danger"></i>${efb_var.text.error}${efb_var.text.youCantUseHTMLTagOrBlank}</h6>`;
    //noti_message_efb(efb_var.text.error, efb_var.text.youCantUseHTMLTagOrBlank, 5 , 'danger')
    return
  }
  fun_send_replayMessage_ajax_emsFormBuilder(ob, id)


}


function fun_ws_show_list_messages(value) {
  //v2
  // show list of filled out of the form;
  console.log(form_type_emsFormBuilder);
  let rows = '';
  let no = 1;
  let head = `<!-- rows -->`;
  let iconRead = 'bi-envelope-open';
  let iconNotRead = ' <path  d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>';
  if (form_type_emsFormBuilder == 'subscribe') {
    head = `<div ><button class="efb btn efb btn-primary text-white mt-2" onClick="generat_csv_emsFormBuilder()" title="${efb_var.text.downloadCSVFileSub}" >  <i class="efb bi-download mx-2""></i>${efb_var.text.downloadCSVFile}</button ></div>`;
    iconRead = 'bi-person';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'register') {
    const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
    head = `<div > <button  class="efb btn efb btn-primary text-white mt-2"  onClick="${fun}" title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb bi-download mx-2"></i>${efb_var.text.downloadCSVFile}</button ></div>`;
    iconRead = 'bi-person ';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'survey') {    
    const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
    head = `<div >
    <button  class="efb btn efb btn-primary text-white mt-2"  onClick="${fun}" title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb bi-download mx-2"></i>${efb_var.text.downloadCSVFile}</button >
    <button  class="efb btn efb btn-primary text-white mt-2"  onClick="convert_to_dataset_emsFormBuilder()" title="${efb_var.text.chart}" >  <i class="efb bi-bar-chart-line mx-2"></i>${efb_var.text.chart}</button >
    </div>`;
    iconRead = 'bi-chat-square-text';
    iconNotRead = ' <path  d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>';
  }else if (form_type_emsFormBuilder == 'form' || form_type_emsFormBuilder == 'payment'){
    const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
    head = `<div > <button  class="efb btn efb btn-primary text-white mt-2"  onClick="${fun}" title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb bi-download mx-2"></i>${efb_var.text.downloadCSVFile}</button ></div>`;
  }  
  if (value.length > 0) {
    for (const v of value) {
      let state = Number(v.read_);
      if(response_state_efb.findIndex(x=>x.msg_id==v.msg_id)!=-1){state=0}      
      rows += `<tr class="efb pointer-efb" id="" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${Number(state) == 0 ? efb_var.text.newResponse : efb_var.text.read}" onClick="fun_open_message_emsFormBuilder(${v.msg_id} , ${state})">                    
         <th scope="row" class="">${v.track}</th>
         <td class="">${v.date}</td>
            <td> 
            <button type="button" class="efb btn btn-comment btn-sm" id="btn-m-${v.msg_id}" >
             ${Number(state) == 0 ? `<svg xmlns="http://www.w3.org/2000/svg" class="jump" width="14" height="14" fill="currentColor" class="bi bi-chat-fill" viewBox="0 0 16 16">${iconNotRead}</svg>` : `<i id="icon-${v.msg_id}" class="efb ${iconRead} text-muted"></i> `}</button>
            </td>                               
            </tr>` ;
      no += 1;
    }
  } else {
    rows = `<tr class="efb pointer-efb fs-6 efb"><td>${efb_var.text.noResponse}</td><td></td><td></td></tr>`
  }





  document.getElementById('content-efb').innerHTML = `<div class="head-efb">${head}</div>
    <h4 class="title-holder efb"> <img src="${efb_var.images.title}" class="title efb">
    <i class="efb bi-archive title-icon  mx-1"></i>${efb_var.text.messages}
    </h4>
    <div class="card efb">
    <table class="table table-striped table-hover mt-3" id="emsFormBuilder-list">
    <thead>
    <th scope="col">${efb_var.text.trackNo}</th>
    <th scope="col">${efb_var.text.formDate}</th>
    <th scope="col">${efb_var.text.response}</th>
    </tr>
    </thead>
    <tbody>
    ${rows}
    </tbody>
    </table>
    </div>
    `;  
  if (form_type_emsFormBuilder != 'login' ) fun_export_rows_for_Subscribe_emsFormBuilder(value);

}



function fun_delete_form_with_id_by_server(id) {  
  jQuery(function ($) {
    data = {
      action: "remove_id_Emsfb",
      type: "POST",
      id: id,
      nonce: ajax_object_efm_core.nonce,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {      
      if (res.success == true) {
        setTimeout(() => {
          noti_message_efb(efb_var.text.done, '', 3, 'info')
        }, 3)
      } else {        
        setTimeout(() => {
          noti_message_efb(efb_var.text.error, '', 3, 'danger')
        }, 3)
      }
    })
  });

}




function emsFormBuilder_messages(id) {
  const row = ajax_object_efm.ajax_value.find(x => x.form_id == id)
  form_type_emsFormBuilder = row.form_type;
  fun_get_messages_by_id(Number(id));
  emsFormBuilder_waiting_response();
  fun_backButton(0);
}

function fun_open_message_emsFormBuilder(msg_id, state) {
  fun_emsFormBuilder_get_all_response_by_id(Number(msg_id));
  emsFormBuilder_show_content_message(msg_id)
  if (state == 0) {
    fun_update_message_state_by_id(msg_id);
  }
}



function fun_get_form_by_id(id) {
  jQuery(function ($) {
    data = {
      action: "get_form_id_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {        
        try {
          let v = res.data.ajax_value.replace(/[\\]/g, '')          
          const value = JSON.parse(v);
          const len = value.length          
          valj_efb=value;
          setTimeout(() => {
            formName_Efb = valj_efb[0].formName;
            form_ID_emsFormBuilder = id;
            localStorage.setItem('valj_efb', JSON.stringify(value));
            const edit = { id: res.data.id, edit: true };
            localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
            fun_ws_show_edit_form(id);
          }, len * 6)
         }catch(error){
           
           //reportE
         }
      }
    })
  });
}
function fun_update_message_state_by_id(id) {
  jQuery(function ($) {
    data = {
      action: "update_message_state_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        let iconRead = `<i class="efb bi-envelope-open text-muted"></iv>`;
        if (form_type_emsFormBuilder == 'subscribe') {
          iconRead = `<i class="efb bi-person text-muted"></iv>`;
        } else if (form_type_emsFormBuilder == 'register') {
          iconRead = `<i class="efb bi-person text-muted"></iv>`;
        }
        document.getElementById(`btn-m-${id}`).innerHTML = iconRead;
        document.getElementById(`efbCountM`).innerHTML = parseInt(document.getElementById(`efbCountM`).innerHTML) - 1;
        
        if (res.data.ajax_value != undefined) {
          const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
          localStorage.setItem('valueJson_ws_p', JSON.stringify(value));
          const edit = { id: res.data.id, edit: true };
          localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
          fun_ws_show_edit_form(id)
        }
      } 
    })
  });
}
/* function fun_update_message_state_by_id(id) {
  jQuery(function ($) {
    data = {
      action: "update_message_state_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        let iconRead = 'fa fa-envelope-open-o';
        if (form_type_emsFormBuilder == 'subscribe') {
          iconRead = 'fa fa-user-o';
        } else if (form_type_emsFormBuilder == 'register') {
          iconRead = 'fa fa-user-o';
        }
        document.getElementById(`icon-${id}`).className = iconRead;
        document.getElementById(`efbCountM`).innerHTML = parseInt(document.getElementById(`efbCountM`).innerHTML) - 1;
        
        if (res.data.ajax_value != undefined) {
          const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
          const len = value.length
          
          setTimeout(() => {
            formName_Efb = valj_efb[0].formName;
            localStorage.setItem('valj_efb', JSON.stringify(value));
            const edit = { id: res.data.id, edit: true };
            localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
            fun_ws_show_edit_form(id);
          }, len * 6)
        }
      } else {
        
      }
    })
  });
} */
function fun_get_messages_by_id(id) {
  jQuery(function ($) {
    data = {
      action: "get_messages_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      type: "POST",
      form: form_type_emsFormBuilder,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        valueJson_ws_messages =  res.data.ajax_value ;
        localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));  
        fun_ws_show_list_messages(valueJson_ws_messages)
      } else {     
      }
    })
  });
}
function fun_emsFormBuilder_get_all_response_by_id(id) {
  jQuery(function ($) {
    data = {
      action: "get_all_response_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      type: "POST",
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        fun_ws_show_response(res.data.ajax_value);
      } 
    })
  });
}



function fun_send_replayMessage_ajax_emsFormBuilder(message, id) {  
  if (message.length < 1) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    //noti_message_efb(fb_var.text.enterYourMessage, 5 , 'warning')
    document.getElementById('replayM_emsFormBuilder').innerHTML = "";
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    return;
  }

  jQuery(function ($) {
    data = {
      action: "set_replyMessage_id_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      id: id,
      message: JSON.stringify(message)
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {       
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        // noti_message_efb(res.data.m, 7 , 'info')
        document.getElementById('replayM_emsFormBuilder').innerHTML = "";
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');

        // اضافه شدن به سمت یو آی 
        const userIp = ajax_object_efm.user_ip;
        const date = Date();       
        document.getElementById('replayM_emsFormBuilder').value = "";

        fun_emsFormBuilder__add_a_response_to_messages(message, message[0].by, ajax_object_efm.user_ip, 0, date);
        const chatHistory = document.getElementById("resp_efb");
        chatHistory.scrollTop = chatHistory.scrollHeight;
      } else {       
        // noti_message_efb(efb_var.text.error,res.data.m, 7 , 'danger')
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
  //v2
  //respomse
  const resp = fun_emsFormBuilder_show_messages(message, by, userIp, track, date);
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}


function fun_ws_show_response(value) {
  for (v of value) {
    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'Message', value: 'message not exists' }
    fun_emsFormBuilder__add_a_response_to_messages(content, v.rsp_by, v.ip, 0, v.date);
  }
  //document.getElementById('loading_message_emsFormBuilder').remove();
}


function fun_show_content_page_emsFormBuilder(state) {
  if (state == "forms") {
    window.location.reload();
    document.getElementById('content-efb').innerHTML = `<div class="card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb-color text-center"><i class="fas fa-spinner fa-pulse"></i> ${efb_var.text.loading}</div>`
  } else if (state == "setting") {
    fun_show_setting__emsFormBuilder();
    fun_backButton(0);
    state = 2
  } else if (state == "help") {
    fun_show_help__emsFormBuilder();
    state = 4
  }
  fun_hande_active_page_emsFormBuilder(state);
}

function fun_hande_active_page_emsFormBuilder(no) {
  //active
  let count = 0;
  for (const el of document.querySelectorAll(`.nav-link`)) {
    count += 1;
    if (el.classList.contains('active')) el.classList.remove('active');
    if (count == no) el.classList.add('active');
    //active
  }
}

function fun_show_help__emsFormBuilder() {
  document.getElementById("more_emsFormBuilder").style.display = "none";
  listOfHow_emsfb = {
    /*  1:{title:'How to activate pro version of Easy form builder.',url:'https://www.youtube.com/embed/RZTyFcjZTSM'},*/
    2: { title: efb_var.text.howConfigureEFB, url: 'https://youtu.be/dkrAcBGJjLQ' },
    3: { title: efb_var.text.howGetGooglereCAPTCHA, url: 'https://youtu.be/AZ9LPPTPuh0' },
    4: { title: efb_var.text.howActivateAlertEmail, url: 'https://youtu.be/Zz3NV6mA-us' },
    5: { title: efb_var.text.howCreateAddForm, url: 'https://youtu.be/JSI-lFRA_9I' },
    6: { title: efb_var.text.howActivateTracking, url: 'https://youtu.be/q0OTaj0iiGs' },
    7: { title: efb_var.text.howWorkWithPanels, url: 'https://youtu.be/SJh5h89P8UU' },
    8: { title: efb_var.text.howAddTrackingForm, url: 'https://youtu.be/GK99Jcb3_ZY' },
    9: { title: efb_var.text.howFindResponse, url: 'https://youtu.be/X9cW2j-JkS4' },
  }


  let str = "";
  for (const l in listOfHow_emsfb) {
    str += `<a class="btn efb btn-darkb text-white btn-lg d-block mx-3 mt-2" href="${listOfHow_emsfb[l].url}"><i class="efb bi-youtube mx-1"></i>${listOfHow_emsfb[l].title}</a>`
  }
  document.getElementById('content-efb').innerHTML = `
  <img src="${efb_var.images.title}"  class="crcle-footer">
  <div class="container row">
  <h4 class="title-holder efb">
      <img src="${efb_var.images.title}" class="title efb">
      <i class="efb bi-info-circle title-icon mx-2"></i>${efb_var.text.help}
  </h4>
  <div class="crd efb col-md-7"><div class="card-body"> <div class="d-grid gap-2">${str}</div></div></div>
  <div class="col-md-4 mx-1 py-5 crd efb">
                  <img src="${efb_var.images.logo}"  class="description-logo efb">
                  <h1 class="efb pointer-efb" onclick="Link_emsFormBuilder('ws')"><font style="vertical-align: inherit;"><font style="vertical-align: inherit;">${efb_var.text.easyFormBuilder}</font></font></h1>
                  <h3 class="efb pointer-efb  card-text " onclick="Link_emsFormBuilder('ws')">${efb_var.text.byWhiteStudioTeam}</h3>
                  <div class="clearfix"></div>
                  <p class=" card-text efb pb-3 fs-7">
                  ${efb_var.text.youCanFindTutorial} ${efb_var.text.proUnlockMsg}
                  </p>
                  <a class="btn efb btn-warning text-white btn-lg"  onclick="Link_emsFormBuilder('ws')"><i class="efb bi-gem mx-1"></i>${efb_var.text.activateProVersion}</a>
                  <a class="btn mt-1 efb btn-outline-pink btn-lg" onclick="Link_emsFormBuilder('wiki')"><i class="efb bi-info-circle mx-1"></i>${efb_var.text.documents}</a>
              </div>
  </div>
 `;

}
function fun_show_setting__emsFormBuilder() {  
  
  let activeCode = 'null';
  let sitekey = 'null';
  let secretkey = 'null';
  let stripeSKey ='null';
  let stripePKey ='null';
  let email = 'null';
  let trackingcode = 'null';
  let apiKeyMap = 'null';
  let smtp = 'null';
  let text= efb_var.text;
  let textList ="<!--list EFB -->";
  let bootstrap = false ;
  let emailTemp="null"
//JSON.parse(ajax_object_efm.setting[0].text.replace(/[\\]/g, ''))
  if ((ajax_object_efm.setting[0] && ajax_object_efm.setting[0].setting.length > 5) || typeof valueJson_ws_setting == "object" && valueJson_ws_setting.length != 0) {
   // console.log(ajax_object_efm.setting[0].setting);
    if (valueJson_ws_setting.length == 0) {valueJson_ws_setting = (JSON.parse(ajax_object_efm.setting[0].setting.replace(/[\\]/g, '')));
    }else if (typeof valueJson_ws_setting=="string"){
      valueJson_ws_setting = (JSON.parse(valueJson_ws_setting.replace(/[\\]/g, '')));
    }
    const f = (name) => { if (valueJson_ws_setting[name]) {return valueJson_ws_setting[name]} else {return 'null'} }
   if(valueJson_ws_setting.text)text= valueJson_ws_setting.text
  // console.log(valueJson_ws_setting, valueJson_ws_setting.text);
    activeCode = f('activeCode');
    sitekey = f(`siteKey`);
    secretkey = f(`secretKey`);
    email = f(`emailSupporter`);
    trackingcode = f(`trackingCode`);
    apiKeyMap = f(`apiKeyMap`);
    stripeSKey = f(`stripeSKey`);
    stripePKey = f(`stripePKey`);
    smtp = f('smtp');
    bootstrap = f('bootstrap');
    emailTemp = f('emailTemp');
  }

  Object.entries(text).forEach(([key, value]) => {
    state = key=="easyFormBuilder" ? "d-none" : "d-block";
    if(key!="forbiddenChr")  textList+=`<input type="text"  id="${key}"  class="sen sen-validate-efb ${state} form-control text-muted efb  border-d efb-rounded h-d-efb  m-2"  placeholder="${value}" id="labelEl" required value="${value? value : ''}">`
  } );
  const mxCSize = !mobile_view_efb ? 'mx-5'  : 'mx-1';
  const mxCSize4 = !mobile_view_efb ? 'mx-4'  : 'mx-1';
  document.getElementById('content-efb').innerHTML = `
  <div class="container">
            <h4 class="title-holder efb">
                <img src="${efb_var.images.title}" class="title efb">
                <i class="efb bi-gear title-icon mx-1"></i>${efb_var.text.setting}
            </h4>
            <div class="crd efb">
                <div class="card-body">
                        <nav>
                            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="efb nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><i class="efb bi bi-gear mx-2"></i>${efb_var.text.general}</button>
                            <button class="efb nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-google" type="button" role="tab" aria-controls="nav-profile" aria-selected="false"><i class="efb bi bi-google mx-2"></i>${efb_var.text.googleKeys}</button>
                            <button class="efb nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-email" type="button" role="tab" aria-controls="nav-contact" aria-selected="false"><i class="efb bi bi-at mx-2"></i>${efb_var.text.emailSetting}</button>
                            <button class="efb nav-link" id="nav-contact-tab " data-bs-toggle="tab" data-bs-target="#nav-emailtemplate" type="button" role="tab" aria-controls="nav-emailtemplate" aria-selected="false"><i class="efb bi bi-envelope mx-2"></i>${efb_var.text.emailTemplate}</button> 
                            <button class="efb nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-text" type="button" role="tab" aria-controls="nav-text" aria-selected="false"><i class="efb bi bi-fonts mx-2"></i>${efb_var.text.localization}</button>
                           <!-- <button class="efb nav-link" id="nav-stripe-tab" data-bs-toggle="tab" data-bs-target="#nav-stripe" type="button" role="tab" aria-controls="nav-stripe" aria-selected="false"><i class="efb bi bi-credit-card mx-2"></i>${efb_var.text.payment}</button> -->
                        </div>
                        </nav>
                        <div class="tab-content" id="nav-tabContent">
                        <div class="tab-pane fade show active" id="nav-general" role="tabpanel" aria-labelledby="nav-home-tab">
                            <!--General-->
                            <div class="m-3">
                                <h5 class="efb card-title mt-3 mobile-title">
                                    <i class="efb bi-gem m-3"></i>${efb_var.text.proVersion}
                                </h5>
                                <a class="${mxCSize} efb pointer-efb" onClick="Link_emsFormBuilder('ws')">${efb_var.text.clickHereGetActivateCode}</a>
                                <div class="card-body mx- py-1 ${mxCSize4}">
                                    <input type="text" class="form-control efb h-d-efb border-d efb-rounded" id="activeCode_emsFormBuilder" placeholder="${efb_var.text.enterActivateCode}" ${activeCode !== "null" ? `value="${activeCode}"` : ""}>
                                    <span id="activeCode_emsFormBuilder-message" class="text-danger"></span>
                                </div>
                               
                                <h5 class="efb card-title mt-3 mobile-title">
                                    <i class="efb bi-bootstrap m-3 mobile-text"></i>${efb_var.text.bootStrapTemp}
                                </h5>
                                <h6 class="efb ${mxCSize} text-danger mobile-text">${efb_var.text.iUsebootTempW}</h6>
                                <div class="card-body mx- py-1 ${mxCSize4}">
                           
                                <input  class="elEdit form-check-input efb fs-5" type="checkbox" id="bootstrap_emsFormBuilder" ${bootstrap==true ? "checked" :""}>
                                <label class="form-check-label efb" for="bootstrap_emsFormBuilder">${efb_var.text.iUsebootTemp}</label>                                            
                               
                                </div>
                              
                                <h5 class="efb card-title mt-3 mobile-title">
                                    <i class="efb bi-file-earmark-minus m-3"></i>${efb_var.text.clearFiles}
                                </h5>
                                <p class="efb ${mxCSize} mobile-text">${efb_var.text.youCanRemoveUnnecessaryFileUploaded}</p>
                                <div class="card-body text-center py-1">
                                    <button type="button" class="btn efb btn-outline-pink btn-lg " OnClick="clear_garbeg_emsFormBuilder()" id="clrUnfileEfb">
                                        <i class="efb bi-x-lg mx-1 efb mobile-text"></i>${efb_var.text.clearUnnecessaryFiles}
                                    </button>
                                </div>
                           
                                <div class="clearfix"></div>
                                <h5 class="efb card-title mt-3 mobile-title">
                                    <i class="efb bi-search m-3"></i>${efb_var.text.trackingCodeFinder}
                                </h5>
                                <p class="${mxCSize}">${efb_var.text.copyAndPasteBelowShortCodeTrackingCodeFinder}</p>
                                <div class="card-body mx- py-1 ${mxCSize4}">
                                        <div class="row col-12">
                                            <div class="col-md-8">
                                              <input type="text"  class="form-control efb h-d-efb  border-d efb-rounded my-1" id="shortCode_emsFormBuilder" value="[Easy_Form_Builder_confirmation_code_finder]" readonly>
                                              <span id="shortCode_emsFormBuilder-message" class="text-danger"></span>
                                            </div> 
                                              <button type="button" class="btn col-md-4 efb h-d-efb btn-outline-pink my-1" onclick="copyCodeEfb('shortCode_emsFormBuilder')">
                                                  <i class="efb bi-clipboard-check mx-1"></i> ${efb_var.text.copy}
                                              </button>
                                          </div>
                                </div>
                            <!--End General-->
                            </div>  
                        </div>
                        <div class="tab-pane fade" id="nav-google" role="tabpanel" aria-labelledby="nav-profile-tab">
                            <div class="m-3">
                                <div id="message-google-efb"></div>
                                
                             <!--Google-->
                            
                             ${ apiKeyMap=='null' ? `<div class="m-3 p-3 efb alert-info" role=""><h4 class="alert-heading">🎉 ${efb_var.text.SpecialOffer} </h4> <div>${googleCloudOffer()} </div></div>`:`` }
                             <h5 class="efb card-title mt-3 mobile-title">
                                <i class="efb bi-person-check m-3"></i>${efb_var.text.reCAPTCHAv2}
                            </h5>
                            <p class="${mxCSize}"><a target="_blank" href="https://www.google.com/recaptcha/about/">${efb_var.text.reCAPTCHA}</a>  <a target="_blank" href="https://youtu.be/a1jbMqunzkQ">${efb_var.text.clickHereWatchVideoTutorial}</a></p>
                            <div class="card-body mx- py-1 ${mxCSize4}">                                   
                                <label class="form-label mx-2">${efb_var.text.siteKey}</label>
                                <input type="text" class="form-control col-12 efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="sitekey_emsFormBuilder" placeholder="${efb_var.text.enterSITEKEY}" ${sitekey !== "null" ? `value="${sitekey}"` : ""}>
                                <span id="sitekey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                                <label class="form-label mx-2 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                <input type="text" class="form-control col-12 efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="secretkey_emsFormBuilder" placeholder="${efb_var.text.EnterSECRETKEY}" ${secretkey !== "null" ? `value="${secretkey}"` : ""}>
                                <span id="secretkey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                            </div>
                            <h5 class="efb card-title mt-3 mobile-title">
                                <i class="efb bi-geo-alt m-3"></i> ${efb_var.text.maps} 
                            </h5>
                            <!-- <a href="#">${efb_var.text.clickHereWatchVideoTutorial}</a> --!>
                            <p class="${mxCSize}">${efb_var.text.youNeedAPIgMaps}</p>
                            <div class="card-body mx- py-1 ${mxCSize4}">                                   
                                <label class="form-label mx-2 ">${efb_var.text.aPIKey}</label>
                                <input type="text" class="form-control efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="apikey_map_emsFormBuilder" placeholder="${efb_var.text.enterAPIKey}" ${apiKeyMap !== "null" ? `value="${apiKeyMap}"` : ""}>
                                <span id="apikey_map_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                            </div>
                              <!--End Google-->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="nav-email" role="tabpanel" aria-labelledby="nav-contact-tab">
                            <div class="mx-3">
                                <!--Email-->
                                <h5 class="efb card-title mt-3 mobile-title">
                                    <i class="efb bi-at m-3"></i>${efb_var.text.alertEmail}
                                </h5>
                                <p class="${mxCSize}">${efb_var.text.whenEasyFormBuilderRecivesNewMessage}</p>
                                <div class="card-body mx- py-1 ${mxCSize4}">
                                    <label class="form-label mx-2">${efb_var.text.email}</label>
                                    <input type="email" class="form-control efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="${efb_var.text.enterAdminEmail}" ${email !== "null" ? `value="${email}"` : ""}>
                                    <span id="email_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                                </div>
                                
                                <h5 class="card-title mt-3col-12 efb ">
                                    <i class="efb bi-envelope m-3"></i>${efb_var.text.emailServer}
                                </h5>
                                <p class="${mxCSize}">${efb_var.text.beforeUsingYourEmailServers}</p>
                                <div class="card-body mx- py-1 ${mxCSize4}">
                                    <button type="button" class="btn col-md-4 efb btn-outline-pink btn-lg "onClick="clickToCheckEmailServer()" id="clickToCheckEmailServer">
                                        <i class="efb bi-chevron-double-up mx-1 text-center"></i>${efb_var.text.clickToCheckEmailServer}
                                    </button>
                                   <input type="hidden" id="smtp_emsFormBuilder" value="${smtp == "null" ? 'false' : smtp}">
                                </div>
                                <!--End Email-->
                            </div>
                        </div>
              
                      
                        <div class="tab-pane fade" id="nav-text" role="tabpanel" aria-labelledby="nav-text-tab">
                            <div class="mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb card-title mt-3 mobile-title">
                                 <i class="efb bi-fonts m-3"></i>${efb_var.text.localization}
                               </h5>
                               <p class="${mxCSize}">${efb_var.text.translateLocal}</p>
                                <div id="textList-efb"  class="mt-2 py-2 ${mobile_view_efb?'':'px-5'}">${textList} </div>                                
                                <!-- END Text Section -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="nav-stripe" role="tabpanel" aria-labelledby="nav-stripe-tab">
                            <div class="mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb card-title mt-3 mobile-title">
                                 <i class="efb bi-credit-card m-3"></i>${efb_var.text.stripe}
                               </h5>
                               <p class="${mxCSize}">${efb_var.text.stripeMP} <a class="efb pointer-efb" onclick="Link_emsFormBuilder('stripe')" >${efb_var.text.help}</a></p>
                                <div class="card-body mx- py-1 ${mxCSize4}">                                   
                                  <label class="form-label mx-2">${efb_var.text.publicKey}</label>
                                  <input type="text" class="form-control col-12 efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="stripePKey_emsFormBuilder" placeholder="${efb_var.text.publicKey}" ${stripePKey !== "null" ? `value="${stripePKey}"` : ""}>
                                  <span id="stripePKey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                                  <label class="form-label mx-2 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                  <input type="text" class="form-control col-12 efb h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="stripeSKey_emsFormBuilder" placeholder="${efb_var.text.SecreTKey}" ${stripeSKey !== "null" ? `value="${stripeSKey}"` : ""}>
                                  <span id="stripeSKey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                              </div>
                                                           
                                <!-- END Text Section -->
                            </div>
                        </div>


                        <div class="tab-pane fade" id="nav-emailtemplate" role="tabpanel" aria-labelledby="nav-contact-tab">
                        <div class="my-2 mx-1">

                          <nav class="navbar navbar-expand-lg navbar-light bg-light my-2 bg-response efb">
                              <div class="container-fluid">
                                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                                  <div class="collapse navbar-collapse py-1" id="navbarTogglerDemo01">
                                  <ul class="navbar-nav me-auto mb-2 mb-lg-0"><!--efb.app-->
                                    <li class="nav-item"><a class="nav-link efb btn text-capitalize btn-outline-pink " onclick="email_template_efb('p')" >
                                     <i class="bi-play-fill mx-1 "></i>${efb_var.text.preview}</a>
                                    </li>                                  
                                    <li class="nav-item">
                                        <a class="nav-link efb btn text-capitalize  " onclick="email_template_efb('r')" >
                                        <i class="bi-arrow-counterclockwise mx-1 "></i>${efb_var.text.reset}</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link efb btn text-capitalize  " onclick="Link_emsFormBuilder('wiki')" >
                                        <i class="bi-question mx-1 "></i>${efb_var.text.help}</a>
                                    </li>

                                    <li class="dropdown">
                                    <a class="nav-link efb btn text-capitalize dropdown-toggle" data-toggle="dropdown" href="#">${efb_var.text.templates}
                                    <span class="caret"></span></a>
                                    <ul class="dropdown-menu">
                                      <li class="nav-item"><a onClick="fun_add_email_template_efb(1)" class="nav-link efb btn">${efb_var.text.emailTemplate} 1</a></li>
                                     <li class="nav-item"><a onClick="fun_add_email_template_efb(2)"  class="nav-link efb btn">${efb_var.text.emailTemplate} 2</a></li>
                                    </ul>
                                  </li>
                                    
                              </div>      
                          </nav>
                        </div>
                        <div class="mx-3 row col-12 mb-2">
                            <!--EmailTemplate-->
                            <div class="col-md-8 bg-back">
                              <h3 class="efb card-title mt-3 mobile-title">${efb_var.text.editor}</h3>
                              <textarea class="form-control" id="emailTemp_emsFirmBuilder" rows="50" >${emailTemp !== "null" ? emailTemp:''}</textarea>                        
                              <span id="emailTemp_emsFirmBuilder-message" class="text-danger"></span>
                            </div>
                            <div class="col-md-4 mt1 efb guide p-2"> 
                            <h3 class="efb card-title mt-3 mobile-title text-capitalize">${efb_var.text.info}</h3>
                            <p class="efb m-2 fs-8">
                            ${efb_var.text.infoEmailTemplates}
                            </br></br>
                            <span class="efb fs-7"> ${efb_var.text.noticeEmailContent}</span> 
                            </br></br>
                            <span class="efb fs-7">shortcode_title <span class="efb text-danger">*</span> :</span> ${efb_var.text.shortcodeTitleInfo}
                            </br></br>
                            <span class="efb fs-7">shortcode_message <span class="efb text-danger">*</span> :</span> ${efb_var.text.shortcodeMessageInfo}
                            </br></br>
                            <span class="efb fs-7">shortcode_website_name :</span> ${efb_var.text.shortcodeWebsiteNameInfo}
                            </br></br> 
                            <span class="efb fs-7">shortcode_website_url :</span> ${efb_var.text.shortcodeWebsiteUrlInfo}
                             </br></br>
                            <span class="efb fs-7">shortcode_admin_email  :</span> ${efb_var.text.shortcodeAdminEmailInfo}
                            </p>
                            </br></br>
                            <a class="btn mt-1 efb btn-outline-pink btn-lg" onclick="Link_emsFormBuilder('wiki')"><i class="efb bi-info-circle mx-1"></i>${efb_var.text.documents}</a>
                            </div>
                         
                            <!--End EmailTemplate-->
                        </div>
                    </div>
                      
                        <button type="button" id="save-stng-efb" class="btn efb btn-primary btn-lg ${efb_var.rtl == 1 ? 'float-start' : 'float-end '}" mt-2 mx-5"  onClick="fun_set_setting_emsFormBuilder()">
                            <i class="efb bi-save mx-1"></i>${efb_var.text.save}
                        </button>                  
                </div>
            </div>
        </div>
`

  for (const el of document.querySelectorAll(`.sen`)) { el.addEventListener("change", (e) => {
    //forbiddenChr
     if(el.value.match(/["'\\]/)!=null){
       el.className= colorBorderChangerEfb(el.className, "border-danger")
       fun_switch_saveSetting(true,el.id);
      }else{
        text[el.id]=el.value;
        efb_var.text[el.id]=el.value; 
        el.className= colorBorderChangerEfb(el.className, "border-d")
        fun_switch_saveSetting(false,el.id);
      }})}
}

let idOfListsEfb =[];
function fun_switch_saveSetting(i , id){  
  if(i==true){
    idOfListsEfb.push(id);
    document.getElementById("save-stng-efb").classList.contains("disabled")==false ? document.getElementById("save-stng-efb").classList.add("disabled") :"";
    noti_message_efb(`Forbidden characters: " \' \\ `,"",5000,"danger");
  }else{
    const indx =idOfListsEfb.findIndex(x=>x == id);
    if (indx!=-1 )idOfListsEfb.splice(indx,1);    
    idOfListsEfb.length==0 && document.getElementById("save-stng-efb").classList.contains("disabled")==true ? document.getElementById("save-stng-efb").classList.remove("disabled") :"";
  }
}

function fun_set_setting_emsFormBuilder() {
  // fun_state_loading_message_emsFormBuilder(1);
  document.getElementById('save-stng-efb').classList.add('disabled');
  
  const nnrhtml = document.getElementById('save-stng-efb').innerHTML;
  document.getElementById('save-stng-efb').innerHTML = `<i class="bi bi-hourglass-split"></i>`
  fun_State_btn_set_setting_emsFormBuilder();
  const f = (id) => {
    const el = document.getElementById(id)
    let r = "NotFoundEl"
    if (el.type == "text" || el.type == "email" || el.type == "textarea" || el.type == "hidden") {
      if (id=="emailTemp_emsFirmBuilder"){
        el.value= el.value.replace(/(\r\n|\r|\n|\t)+/g, '');
        el.value= el.value.replace(/([/])+/g, '@efb@');
        el.value= el.value.replace(/(["])+/g, `'`);
      }
      return el.value;
    } else if (el.type == "checkbox") {      
      return el.checked;
    }
    return "NotFoundEl"
  }
  const v = (id) => {
    const el = document.getElementById(id);   
    if (id == 'smtp_emsFormBuilder') { return true }
    if (el.type !== "checkbox") {
      if (el.value.length > 0 && el.value.length < 10 && id !== "activeCode_emsFormBuilder" && id !== "email_emsFormBuilder" && id !== "bootstrap_emsFormBuilder") {
        document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
        el.classList.add('invalid');
        window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
        return false;
      }else if(id == "bootstrap_emsFormBuilder"){
      } else if (id == "emailTemp_emsFirmBuilder") {
        let st=1;
        let c=''
        let ti ='';
        if(el.value.length<5 && el.value.length>1){
          st =0;
          c =`<div class="text-center text-darkb efb"><div class="bi-emoji-frown fs-4 efb"></div><p class="fs-5 efb">${efb_var.text.pleaseEnterVaildEtemp}</p></div>`
        }else if(el.value.length>10000){
          st =0;
          c =`<div class="text-center text-darkb efb"><div class="bi-exclamation-triangle fs-3 text-danger efb"></div><p class="fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>` ;
        }else if(el.value.length>1 && el.value.indexOf('shortcode_message')==-1 && el.value.indexOf('shortcode_title')==-1){
          c =`<div class="text-center text-darkb efb"><div class="bi-exclamation-triangle fs-3 text-danger efb"></div><p class="fs-5 efb">${efb_var.text.addSCEmailM}</p></div>` ;
          st =0;
        }

        if(st==0){
          ti =efb_var.text.error
          show_modal_efb(c,ti , '', 'saveBox');
          const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
          myModal.show();
          return false;
        }
      } else if (id == "activeCode_emsFormBuilder") {
        //برای برسی صحیح بودن کد امنیتی وارد شده
        if (el.value.length < 5 && el.value.length != 0) {
          el.classList.add('invalid');

          window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
          return false;
        }
      } else {
        if (el.classList.contains("invalid") == true) {
          el.classList.remove('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = '';
        }
        if (el.type == "email" && el.value.length > 0) {
          return valid_email_emsFormBuilder(el);
        }
      }
    }else{
      if(el.id =="bootstrap_emsFormBuilder"){        
      }
    }
    return true;
  }
  const ids = ['stripeSKey_emsFormBuilder','stripePKey_emsFormBuilder','smtp_emsFormBuilder','bootstrap_emsFormBuilder', 'apikey_map_emsFormBuilder', 'sitekey_emsFormBuilder', 'secretkey_emsFormBuilder', 'email_emsFormBuilder', 'activeCode_emsFormBuilder','emailTemp_emsFirmBuilder'];
  let state = true
 
  for (id of ids) {
   
    if (v(id) === false) {
      state = false;
      // fun_state_loading_message_emsFormBuilder(1);
      fun_State_btn_set_setting_emsFormBuilder();
      break;
    }
  }
  if (state == true) {
    const activeCode = f('activeCode_emsFormBuilder');
    const sitekey = f(`sitekey_emsFormBuilder`);
    const secretkey = f(`secretkey_emsFormBuilder`);
    const stripeSKey = f(`stripeSKey_emsFormBuilder`);
    const stripePKey = f(`stripePKey_emsFormBuilder`);
    const email = f(`email_emsFormBuilder`);
    //  const trackingcode = f(`trackingcode_emsFormBuilder`);
    const apiKeyMap = f(`apikey_map_emsFormBuilder`)
    const smtp = f('smtp_emsFormBuilder')
    const bootstrap = f('bootstrap_emsFormBuilder');
    let emailTemp = f('emailTemp_emsFirmBuilder');
    emailTemp= emailTemp.replace(/([/\r\n|\r|\n/])+/g, ' ')
    let text = efb_var.text;
    fun_send_setting_emsFormBuilder({ activeCode: activeCode, siteKey: sitekey, secretKey: secretkey, emailSupporter: email, apiKeyMap: `${apiKeyMap}`, smtp: smtp,text:text,bootstrap,emailTemp:emailTemp , stripePKey:stripePKey,stripeSKey:stripeSKey});
  }

  document.getElementById('save-stng-efb').innerHTML = nnrhtml
  document.getElementById('save-stng-efb').classList.remove('disabled');
}

function fun_State_btn_set_setting_emsFormBuilder() {
  /*  if (document.getElementById('btn_set_setting_emsFormBuilder').classList.contains('disabled') == true) {
     document.getElementById('btn_set_setting_emsFormBuilder').classList.remove('disabled');
   } else {
     document.getElementById('btn_set_setting_emsFormBuilder').classList.add('disabled');
   } */
}


function fun_state_loading_message_emsFormBuilder(state) {
  //btn_set_setting_emsFormBuilder
  if (state !== 0) {
    if (document.getElementById('loading_message_emsFormBuilder').classList.contains('invisible') == true) {
      document.getElementById('loading_message_emsFormBuilder').classList.remove('invisible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('visible');
    } else {
      document.getElementById('loading_message_emsFormBuilder').classList.remove('visible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('invisible');
    }
  }
}


function fun_send_setting_emsFormBuilder(data) {
  data= JSON.stringify(data);
  //ارسال تنظیمات به ووردپرس
  jQuery(function ($) {
    data = {
      action: "set_setting_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      contentType: "application/x-www-form-urlencoded;charset=utf-8",
      message: data
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      let m = ''
      let t = efb_var.text.done
      let lrt = "info"
      let time = 3.7
      if (res.success == true) {
        valueJson_ws_setting = data.message;
        
        fun_show_setting__emsFormBuilder();
        if (res.data.success == true) {
          // اگر پاسخ  مست گرفت از سرور
          m = efb_var.text.saved;
        } else {
          t = efb_var.text.error
          m = res.data.m;
          lrt = "danger";
          time = 7;
        }
      } else {
        t = '';
        m = res;
        lrt = "danger";
        time = 7;
      }
      //console.log(res)
      noti_message_efb(t, m, time, lrt)
    })
  });
}


function fun_find_track_emsFormBuilder() {
  //function find track code
  const el = document.getElementById("track_code_emsFormBuilder").value;

  if (el.length < -1) {
    noti_message_efb(efb_var.text.error, efb_var.text.trackingCodeIsNotValid, 7, 'warning');

  } else {
    document.getElementById('track_code_emsFormBuilder').disabled = true;
    document.getElementById('track_code_btn_emsFormBuilder').disabled = true;
    const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
    document.getElementById('track_code_btn_emsFormBuilder').innerHTML = `<i class="bi-hourglass-split"></i>`;



    jQuery(function ($) {
      data = {
        action: "get_track_id_Emsfb",
        nonce: ajax_object_efm_core.nonce,
        value: el,
      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {
        if (res.data.success == true) {
          valueJson_ws_messages = res.data.ajax_value;
          localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));
          document.getElementById("more_emsFormBuilder").style.display = "none";
          fun_ws_show_list_messages(valueJson_ws_messages)
          document.getElementById('track_code_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue;
        } else {
          noti_message_efb(efb_var.text.error, res.data.m, 4, 'warning');
          document.getElementById('track_code_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue

        }
      })
    });
  }
}//end function 


function clear_garbeg_emsFormBuilder() {
  //  emsFormBuilder_popUp_loading()
  
  const innrhtm = document.getElementById('clrUnfileEfb').innerHTML;
  document.getElementById('clrUnfileEfb').innerHTML = `<i class="bi bi-hourglass-split"></i>`
  document.getElementById('clrUnfileEfb').classList.add('disabled')
  jQuery(function ($) {
    data = {
      action: "clear_garbeg_Emsfb",
      nonce: ajax_object_efm_core.nonce
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {
        noti_message_efb(efb_var.text.done, res.data.m, 4.7);
      } else {
        noti_message_efb(efb_var.text.error, res.data.m, 4.7, 'danger');

      }
    })
  });
  document.getElementById('clrUnfileEfb').classList.remove('disabled')
  document.getElementById('clrUnfileEfb').innerHTML = innrhtm;
}


 function fun_export_rows_for_Subscribe_emsFormBuilder(value) {
  //json ready for download 
  let head = {};
  let heads = [];
  let ids = [];
  let count = -1;
  let rows = Array.from(Array(value.length + 1), () => Array(100).fill('null@EFB'));
  
  rows[0][0] = 'id';
  let i_count = -1;
  add_multi=(c,content,value_col_index,v)=>{
   
    if(form_type_emsFormBuilder=="survey"){
      if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
        rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
      } else {
        const r = rows.length
        const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
        rows = rows.concat(row);
        rows[parseInt(r)][parseInt(value_col_index)] = content[c].value;
        rows[parseInt(r)][0] = v;
        
      }
    }else{
      rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB" ?  rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value : rows[parseInt(i_count)][parseInt(value_col_index)] += "|| "+ content[c].value
    }
  }
  let county =0
  for (v of value) {
  
    const content = JSON.parse(v.content.replace(/[\\]/g, '')) 
    count += 1;
    i_count += i_count == -1 ? +2 : 1;

    let countMultiNo = [];
    let NoMulti = [];

    
    for (c in content) {    
     
      // rows = Object.assign(rows, {[c.name]:c.value});
      let value_col_index;
      //console.log(c,content[c]);
      if (content[c].type != "checkbox" && content[c].type != 'multiselect' 
      && content[c].type != "payCheckbox" && content[c].type != 'payMultiselect') {

        if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;

        value_col_index = rows[0].findIndex(x => x == content[c].name);

        if (value_col_index == -1) {

          value_col_index = rows[0].findIndex(x => x == 'null@EFB');
          rows[0][parseInt(value_col_index)] = content[c].name;
        } 

        rows[parseInt(i_count)][parseInt(value_col_index)] =   content[c].value ;

      }else if(content[c].type == 'multiselect' || content[c].type == 'payMultiselect'){
        if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;
        value_col_index = rows[0].findIndex(x => x == content[c].name);
        if (value_col_index == -1) {
          value_col_index = rows[0].findIndex(x => x == 'null@EFB');
          rows[0][parseInt(value_col_index)] = content[c].name;
        } 
        if(content[c].value.search(/@efb!+/g)!=-1){
          if(form_type_emsFormBuilder=="survey"){
            const nOb = content[c].value.split("@efb!")
             nOb.forEach(n => {
               if(n!=""){
                 //console.log(`[${n}]`,nOb.length);
                 if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
                   rows[parseInt(i_count)][parseInt(value_col_index)] =n;
                 } else {
                   const r = rows.length
                   const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
                   rows = rows.concat(row);
                   rows[parseInt(r)][parseInt(value_col_index)] = n;
                   rows[parseInt(r)][0] = v.msg_id;                  
                 }
               }
             });
          }else{
            rows[parseInt(i_count)][parseInt(value_col_index)] =   content[c].value.replaceAll('@efb!' , " || ") 
          }
        }else{
          rows[parseInt(i_count)][parseInt(value_col_index)] =   content[c].value.replaceAll('@efb!' , "") ; ;
        }
        //content[c].value.replaceAll('@efb!' , " || ") ;
      } else {
        //console.log('checkbox',c)
        // if checkbox
        if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;
        //new code test
        const name = content[c].name;
        value_col_index = rows[0].findIndex(x => x == name);
        if (value_col_index != -1) {
          //if checkbox title is exists
          add_multi(c,content,value_col_index,v.msg_id)
        } else {
          //if checkbox title is Nexists
          value_col_index = rows[0].findIndex(x => x == 'null@EFB');
          rows[0][parseInt(value_col_index)] = name;
          add_multi(c,content,value_col_index,v.msg_id)
          
        }
        

        //new code test
  
      }//end else
      
    }
    
    
    //  exp.push(rows);
  }
  const col_index = rows[0].findIndex(x => x == 'null@EFB');
  
  const exp = Array.from(Array(rows.length), () => Array(col_index).fill(efb_var.text.noComment));
  
  for (e in exp) {
    for (let i = 0; i < col_index; i++) {
      if (rows[e][i] != "null@EFB") exp[e][i] = rows[e][i];
    }
  }
  
  
  localStorage.setItem('rows_ws_p', JSON.stringify(exp));
  //  localStorage.setItem('head_ws_p', JSON.stringify(head));
}

//end

function exportCSVFile_emsFormBuilder(items, fileTitle) {
  //source code :https://codepen.io/danny_pule/pen/WRgqNx
 items.forEach(item => {for(let i in item ){ if (item[i]=="notCount@EFB")  item[i]="";}});
  var jsonObject = JSON.stringify(items);

  var csv = this.convertToCSV_emsFormBuilder(jsonObject);
  var exportedFilenmae = fileTitle + '.csv' || 'export.csv';
  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  if (navigator.msSaveBlob) { // IE 10+
    navigator.msSaveBlob(blob, exportedFilenmae);
  } else {
    var link = document.createElement("a");
    if (link.download !== undefined) { // feature detection
      // Browsers that support HTML5 download attribute
      var url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute("download", exportedFilenmae);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }
//  localStorage.removeItem("rows_ws_p")
}//end function


function convertToCSV_emsFormBuilder(objArray) {
  //source code :https://codepen.io/danny_pule/pen/WRgqNx
  var array = typeof objArray != 'object' ? JSON.parse(objArray) : objArray;
  var str = '';

  for (var i = 0; i < array.length; i++) {
    var line = '';
    for (var index in array[i]) {
      if (line != '') line += ','

      line += array[i][index];
    }

    str += line + '\r\n';
  }

  return str;
}


function generat_csv_emsFormBuilder() {
  //const head  = JSON.parse(localStorage.getItem("head_ws_p"));
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  
  
  const filename = `EasyFormBuilder-${form_type_emsFormBuilder}-export-${Math.random().toString(36).substr(2, 3)}`
  //040820

  exportCSVFile_emsFormBuilder(exp, filename); // create csv file
  //convert_to_dataset_emsFormBuilder(); //create dataset for chart :D
}


function convert_to_dataset_emsFormBuilder() {

  const head = JSON.parse(localStorage.getItem("head_ws_p"));
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  
  let rows = exp;
  let countEnrty = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let entry = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let titleTable = []; // list name of tables and thier titles
  for (col in rows) {
    if (col != 0) {
      for (let c in rows[col]) {
        if (rows[col][c] != 'null@EFB' && rows[col][c] !='notCount@EFB') {
          const indx = entry[c].findIndex(x => x == rows[col][c]);
          
          if (indx != -1) {
            countEnrty[c][indx] += 1;
          } else {
            countEnrty[c].push(1)
            entry[c].push(rows[col][c]);
          }
        }

      }

    } else {
      
      for (let c of rows[col]) {
       
        titleTable.push(c);
      }
    }
  }
  
  
  
  emsFormBuilder_chart(titleTable, entry, countEnrty);
 
}




function emsFormBuilder_chart(titles, colname, colvalue) {
  //window.scrollTo({ top: 0, behavior: 'smooth' });
  let publicidofchart
  let chartview = "<!-- charts -->";
  let chartId = [];
  let publicRows = [];
  let options = {};
  let body= `
  <div class=" ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
    <div id="overpage-chart">
        ${loading_messge_efb()}
    </div>
  </div>`;
 // window.scrollTo({ top: 0, behavior: 'smooth' });

 show_modal_efb(body, efb_var.text.chart, "bi-pie-chart-fill", 'chart')
 const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
 myModal.show()
  /* Add div of charts */
  setTimeout(()=>{

    
    for (let t in titles) {
      chartId.push(Math.random().toString(36).substring(8));
      if (t != 0) {
        chartview += ` </br> <div id="${chartId[t]}"/ class="${t == 0 ? `hidden` : ``}">
          <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
          <h3 class="text-white">${efb_var.text.pleaseWaiting}<h3> </div>`
      } else {chartview += ` </br> <div id="${chartId[t]}"/ class="${t == 0 ? `hidden` : ``}"></div>`}
    }
 
  /*End Add div of charts */

  document.getElementById('overpage-chart').innerHTML = chartview

  /* convert to dataset */
  let drawPieChartArr = [];
  let rowsOfCharts = [];
  let opetionsOfCharts = [];
  for (let t in titles) {
     
    opetionsOfCharts[t] = {
      'title': titles[t],
      'height': 300,
      colors: colors_efb
    };
    const countCol = colname[t].length;
    const rows = Array.from(Array(countCol), () => Array(2).fill(0));
    const valj_efb_ = JSON.parse(localStorage.getItem("valj_efb"));
    for (let r in colname[t]) {
      
      rows[r][0] = colname[t][r];
      rows[r][1] = colvalue[t][r];
    }//end for 2
    
    rowsOfCharts[t] = rows;
    
    google.charts.load('current', { packages: ['corechart'] });
    publicidofchart = chartId[t];

    /* drawPieChart */
    drawPieChartArr[t] = () => {
      var data = new google.visualization.DataTable();
      data.addColumn('string', 'Element');
      data.addColumn('number', 'integer');
      
      
      data.addRows(rowsOfCharts[t]);

      // Instantiate and draw the chart.
      var chart = new google.visualization.PieChart(document.getElementById(chartId[t]));
      chart.draw(data, opetionsOfCharts[t]);
    }
    /* drawPieChart */

    try {
      
      google.charts.setOnLoadCallback(drawPieChartArr[t]);
    } catch (error) {
      
    }

  }// end for 1
  /*end convert to dataset */

},1000);


}//end function

function googleCloudOffer() {

  return `<p>${efb_var.text.offerGoogleCloud} <a href="https://gcpsignup.page.link/8cwn" target="blank">${efb_var.text.getOfferTextlink}</a> </p> `
}


function clickToCheckEmailServer() {
  document.getElementById('clickToCheckEmailServer').classList.add('disabled')
  const nnrhtml = document.getElementById('clickToCheckEmailServer').innerHTML;
  document.getElementById('clickToCheckEmailServer').innerHTML = `<i class="bi bi-hourglass-split"></i>`;
  const email =document.getElementById('email_emsFormBuilder').value;
  // call and waitning response  
  if(email.length>5){
    jQuery(function ($) { 
      data = {
        action: "check_email_server_efb",
        nonce: ajax_object_efm_core.nonce,
        value: 'testMailServer',
        email: email
      };
  
      $.post(ajax_object_efm.ajax_url, data, function (res) {
  
        if (res.data.success == true) {
          noti_message_efb(efb_var.text.done, efb_var.text.serverEmailAble, 3.7);
        } else {
  
          noti_message_efb(efb_var.text.error, efb_var.text.sMTPNotWork, 7, 'danger');
  
        }
        document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
        document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
      })
    });

  }else{
    noti_message_efb(efb_var.text.error, efb_var.text.enterAdminEmail, 10, 'warning');
    document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
    document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
  }

}

window.onload = (() => {
  // remove all notifications from other plugins or wordpress
  jQuery(document).ready(function () { jQuery("body").addClass("folded") })
  setTimeout(() => {
    for (const el of document.querySelectorAll(".notice")) {
      el.remove()
      
    }
    //folded
  }, 50)
})


function email_template_efb(s){
  //console.log(`email temp ${s}`);
  if(s=='p'){
    //preview
    let c = document.getElementById('emailTemp_emsFirmBuilder').value;
    let ti =efb_var.text.error
    c= c.replace(/(http:@efb@|https:@efb@)+/g, '//')
    c= c.replace(/(@efb@)+/g, '/')
    if(c.match(/(<script+)/gi)){
      //show error message you can't use script code
      c= `<div class="text-center text-darkb efb"><div class="bi-exclamation-triangle fs-3 text-danger efb"></div><p class="fs-5 efb">${efb_var.text.pleaseDoNotAddJsCode}</p></div>`;
      //return 0;
    }else if(c.length>2 && c.length<2000 ){
      ti = efb_var.text.preview;
      if(!c.includes('shortcode_message') && !c.includes('shortcode_title')){ 
        c =`<div class="text-center text-darkb efb"><div class="bi-exclamation-triangle fs-3 text-danger efb"></div><p class="fs-5 efb">${efb_var.text.addSCEmailM}</p></div>` ;
       ti =efb_var.text.error
      }
      else if(!efb_var.pro) {
        
        c+=funNproEmailTemp();
        //console.log("Not Pro");
      }
      // موارد زیر در سمت سرور هم قبل از ذخیره سازی اضافه شود برای  کاربر رایگان
    }else if(c.length>=10000){
      c =`<div class="text-center text-darkb efb"><div class="bi-exclamation-triangle fs-3 text-danger efb"></div><p class="fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>` ;
      //ti =efb_var.text.error
    }else if(c.length<2){
      c =`<div class="text-center text-darkb efb"><div class="bi-emoji-frown fs-4 efb"></div><p class="fs-5 efb">${efb_var.text.notFound}</p></div>`
      //show_modal_efb(``, ti, '', 'saveBox');
    }else{
      ti=efb_var.text.preview
    }
    show_modal_efb(c,ti , '', 'saveBox');
    const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
    myModal.show();
  }else if(s=="h"){
    //show help
    //open link to document how create a email template
  }else if (s=='r'){
    //reset
    document.getElementById('emailTemp_emsFirmBuilder').value='';
  }
}


function EmailTemp1Efb(){
  return `<html xmlns='http://www.w3.org/1999/xhtml'>
  <head>
  <meta http-equiv='content-type' content='text/html; charset=utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0;'>
   <meta name='format-detection' content='telephone=no'/>
  <style>
  body { margin: 0; padding: 0; min-width: 100%; width: 100% !important; height: 100% !important; ${efb_var.rtl==1 ?`direction:rtl;` :''}}
  body, table, td, div, p, a { -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%; }
  table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse !important; border-spacing: 0; }
  img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  #outlook a { padding: 0; }
  .ReadMsgBody { width: 100%; } .ExternalClass { width: 100%; }
  .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }
  @media all and (min-width: 560px) {
  .container { border-radius: 8px; -webkit-border-radius: 8px; -moz-border-radius: 8px; -khtml-border-radius: 8px; }
  }
  a, a:hover {color: #f50565!important;}
  .footer a, .footer a:hover {color: #828999;}
  </style></head>
  <body topmargin='0' rightmargin='0' bottommargin='0' leftmargin='0' marginwidth='0' marginheight='0' width='100%' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%; height: 100%; -webkit-font-smoothing: antialiased; text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; line-height: 100%; background-color: #2D3445; color: #FFFFFF;' bgcolor='#2D3445' text='#FFFFFF'>
  <table width='100%' align='center' border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%;' class='background'><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;' bgcolor='#2D3445'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' width='500' style='border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit; max-width: 500px;' class='wrapper'>
  <tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;
  padding-top: 0px;' class='hero'><a target='_blank' style='text-decoration: none;' href='shortcode_website_url'><img border='0' vspace='0' hspace='0' src='${efb_var.images.emailTemplate1}' alt='Please enable images to view this content' title='Email Notification' width='340' style='width: 87.5%;max-width: 340px;color: #FFFFFF; font-size: 13px; margin: 0; padding: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; border: none; display: block;'/></a></td>
  </tr><tr></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 24px; font-weight: bold; line-height: 130%;padding-top: 5px;color: #FFFFFF;font-family: sans-serif;' class='header'>shortcode_title
  </td></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 17px; font-weight: 400; line-height: 160%;
  padding-top: 15px; color: #FFFFFF; font-family: sans-serif;' class='paragraph'> shortcode_message </td></tr><tr>
  <td align='center' valign='top' style='background:#2D3445; border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%;
  padding-top: 25px; padding-bottom: 5px; border-color: #2d3445;' class='button'><a href='shortcode_website_url' target='_blank' style='text-decoration: none;'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' style='max-width: 240px; min-width: 120px; border-collapse: collapse; border-spacing: 0; padding: 0;'><tr><td align='center' valign='middle' style='padding: 12px 24px; margin: 0; text-decoration: none; border-collapse: collapse; border-spacing: 0; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; -khtml-border-radius: 4px;' bgcolor='#0a016e'><a target='_blank' style='text-decoration: none; color: #FFFFFF; font-family: sans-serif; font-size: 17px; font-weight: 400; line-height: 120%;' href='shortcode_website_url'>
  ${efb_var.text.clickHere}  </a></td></tr></table></a> </td>  </tr>
  <tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; padding-top: 30px;' class='line'><hr color='#565F73' align='center' width='100%' size='1' noshade style='margin: 0; padding: 0;' /></td></tr><tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 13px; font-weight: 400; line-height: 150%; padding-top: 10px; padding-bottom: 20px; color: #828999; font-family: sans-serif;' class='footer'>
  ${efb_var.text.sentBy} <a href='shortcode_website_url' target='_blank' style='text-decoration: none; color: #828999; font-family: sans-serif; font-size: 13px; font-weight: 400; line-height: 150%;'>shortcode_website_name</a>.
  </td></tr></table></td></tr></table>  
  
  </body></html>`
}

function EmailTemp2Efb(){
  return`<html xmlns='http://www.w3.org/1999/xhtml'> <body> <style> body {margin:auto 100px;${efb_var.rtl==1 ?`direction:rtl;` :''}}</style><center>
<table class='body-wrap' style='text-align:center;width:86%;font-family:arial,sans-serif;border:12px solid rgba(126, 122, 122, 0.08);border-spacing:4px 20px;'> <tr>
          <img src='${efb_var.images.emailTemplate1}' style='width:36%;'>
</tr> <tr> <td><center> <table bgcolor='#FFFFFF' width='80%'' border='0'>  <tbody> <tr>
<td style='font-family:Roboto-Regular,Helvetica,Arial,sans-serif;font-size:13px;color:#202020;line-height:1.5'>
<h1 style='color:#575252;text-align:center;'>shortcode_title</h1>
</td></tr><tr style='text-align:center;color:#a2a2a2;font-size:14px;'><td>
            <span>shortcode_message</span>
</td> </tr><tr style='text-align:center;color:#a2a2a2;font-size:14px;height:45px;'><td> <span><b style='color:#575252;'>
            <a href='shortcode_website_url'>shortcode_website_name</a>
</span></td></tr></tbody></center></td> </tr></table></center></body>  </html>`
}


function fun_add_email_template_efb(i){
  switch(i){
    case 1:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp1Efb()
      break;
    
    case 2:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp2Efb()
      break;
    
  }
}

function funNproEmailTemp(){
  return `<table role='presentation' bgcolor='#F5F8FA' width='100%'>
  <a type="button" onclick="pro_show_efb(1)" class="pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="This field available in Pro version" data-original-title="This field available in Pro version"><i class="efb bi-gem text-light"></i></a>
  <tr> <td align='left' style='padding: 30px 30px; font-size:12px; text-align:center'><a class='subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'><img src="https://ps.w.org/easy-form-builder/assets/icon.svg?rev=2618751" style="margin: 5px; width:16px;height:16px" >  ${efb_var.text.easyFormBuilder}</a> 
 <br> <img src="https://whitestudio.team/img/favicon.png" style="margin: 5px"> <a class='subtle-link' target='_blank' href='https://whitestudio.team/'>White Studio Team</a></td></tr>`
}