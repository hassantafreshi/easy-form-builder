
let valueJson_ws_form = [];
let valueJson_ws_messages = [];
let valueJson_ws_setting = []
let state_seting_emsFormBuilder = false;
let poster_emsFormBuilder = '';

jQuery(function () {
  //ajax_object_efm.ajax_url ایجکس ادمین برای برگرداند مقدار لازم می شود
  //ajax_object_efm.ajax_value مقدار جی سون
  //ajax_object_efm.language زبان بر می گرداند
  //ajax_object_efm.messages_state پیام های خوانده نشده را بر می گرداند

  // console.log(ajax_object_efm.ajax_value);
  valueJson_ws_form = ajax_object_efm.ajax_value;
  poster_emsFormBuilder = ajax_object_efm.poster
  //console.l(`poster_emsFormBuilder`,poster_emsFormBuilder)
  fun_emsFormBuilder_render_view(10); //778899
});

let count_row_emsFormBuilder = 0;


// تابع نمایش فرم اصلی
function fun_emsFormBuilder_render_view(x) {
  // v2

  let rows = ""
  count_row_emsFormBuilder = x;
  let count = 0;
  fun_backButton(2);
  if (valueJson_ws_form.length > 0) {
    //console.log(valueJson_ws_form);
    for (let i of valueJson_ws_form) {
      console.log(i)
      if (x > count) {
        //console.log(i.form_id)
        let newM = false;
        for (let ims of ajax_object_efm.messages_state) {
          // console.log(`ajax_object_efm return` ,ims)
          if (ims.form_id == i.form_id) {
            newM = true;
          }
          //console.l(`ajax_object_efm return` ,ims , newM , i.form_id)
        }
        rows += `
       <tr class="" id="emsFormBuilder-tr-${i.form_id}" >                    
        <th scope="row" class="emsFormBuilder-tr" data-id="${i.form_id}">[EMS_Form_Builder id=${Number(i.form_id)}]</th>
        <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_name}</td>
        <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_create_date}</td>
        <td > 
        <button type="button" class="efb btn btn-delete btn-sm" onClick ="emsFormBuilder_delete(${i.form_id})" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${efb_var.text.delete}"><i class="efb bi-trash"></i></button>
        <button type="button" class="efb btn-action-edit btn-sm" onClick="emsFormBuilder_get_edit_form(${i.form_id})" data-id="${i.form_id}"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${efb_var.text.edit}"><i class="efb bi-pencil"></i></button>
        <button type="button" class="efb btn btn-comment btn-sm" onClick="emsFormBuilder_messages(${i.form_id})" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${newM == true ? efb_var.text.newResponse : efb_var.text.read}">${newM == true ? `<svg xmlns="http://www.w3.org/2000/svg" class="jump" width="14" height="14" fill="currentColor" class="bi bi-chat-fill" viewBox="0 0 16 16"><path d="M8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6-.097 1.016-.417 2.13-.771 2.966-.079.186.074.394.273.362 2.256-.37 3.597-.938 4.18-1.234A9.06 9.06 0 0 0 8 15z"/></svg>` : `<i class="efb bi-chat text-muted"></i>`}</button>
        </td>                               
       </tr>
       ` ;
        count += 1;
      }
    }

    if (valueJson_ws_form.length < x) {
      document.getElementById("more_emsFormBuilder").style.display = "none";
    }


    document.getElementById('content-efb').innerHTML = `
   <h4 class="title-holder"> <img src="${efb_var.images.title}" class="title">
                <i class="efb bi-archive title-icon me-2"></i>${efb_var.text.forms}
            </h4>
    <div class="card">
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

    el.addEventListener("click", (e) => {

      emsFormBuilder_messages(el.dataset.id)



    });
  }
}

function emsFormBuilder_waiting_response() {
  console.log(108, document.getElementById('emsFormBuilder-list'))
  document.getElementById('emsFormBuilder-list').innerHTML = loading_messge_efb()
}


function emsFormBuilder_get_edit_form(id) {
  //console.l('id' ,id , typeof id)
  //fun_backButton()
  fun_backButton();
  emsFormBuilder_waiting_response();
  fun_get_form_by_id(id);
}


// نمایش پنجره پیغام حذف یک ردیف  فرم
function emsFormBuilder_delete(id) {
  //v2
  // پنجره مطمئن هستی می خوای فرم پاک کنی نمایش بده 
  //areYouSureYouWantDeleteItem
  /*     document.getElementById('wpwrap').innerHTML+=`
    <div class=" overpage preview-overpage ${efb_var.rtl==1 ? 'rtl-text' :''}" id="overpage">
    <div class="overpage-mbox">
    <div class="card-body m-13 bg-dark">
      <h5 class="card-title text-white"><i class="px-2 fas fa-trash"></i>${efb_var.text.remove}</h5>
      <br>
        <h4 class="text-white">${efb_var.text.areYouSureYouWantDeleteItem}</h4>
      <br>
      <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">${efb_var.text.no}</button>
      <button class="btn btn-danger" onclick=" fun_confirm_remove_emsFormBuilder(${Number(id)})">${efb_var.text.yes}</button>
    </div>
    <div>
  </div></div></div>`; */
  console.log('emsFormBuilder_delete')
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${efb_var.text.areYouSureYouWantDeleteItem}</div></div>`
  show_modal_efb(body, efb_var.text.delete, 'efb bi-x-octagon-fill me-2', 'deleteBox')
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

function emsFormBuilder_popUp_message(title, message) {
  // این پنجره برای نمایش پیام های عمومی است
  document.getElementById('wpwrap').innerHTML += `
  <div class=" overpage preview-overpage ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark">
    <h5 class="card-title text-white"><i class="px-2 fa fa-bell-o "></i> ${title}</h5>
    <br>
      <h4 class="text-white">${message}</h4>
    <br>
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">${efb_var.text.close}</button>
  </div>
  <div>
</div></div></div>`;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function emsFormBuilder_popUp_loading() {
  // این پنجره برای نمایش پیام های عمومی است
  document.getElementById('wpwrap').innerHTML += `
  <div class=" overpage preview-overpage ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark text-center">

    <br>
    <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
     <h3 class="text-white">${efb_var.text.pleaseWaiting}<h3>
    <br>
  </div>
  <div>
</div></div></div>`;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function emsFormBuilder_show_content_message(id) {
  // v2
  console.log('emsFormBuilder_show_content_message', id)
  const formType = form_type_emsFormBuilder;
  //console.log(form_type_emsFormBuilder)
  // پنجره نمایش فرم ثبت شده کاربر
  //console.l(`show message`,id ,valueJson_ws_messages);
  const indx = valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const objOptions = valueJson_ws_messages.filter(obj => {
    return obj.msg_id === id.toString()
  })


  console.log(objOptions, valueJson_ws_messages, id);
  const msg_id = valueJson_ws_messages[indx].msg_id;
  //console.l(valueJson_ws_messages[indx],msg_id);

  const userIp = valueJson_ws_messages[indx].ip;
  const track = valueJson_ws_messages[indx].track;
  const date = valueJson_ws_messages[indx].date;

  const content = JSON.parse(valueJson_ws_messages[indx].content.replace(/[\\]/g, ''));
  let m = "<--messages-->"
  let by = valueJson_ws_messages[indx].read_by !== null ? valueJson_ws_messages[indx].read_by : "Unkown"
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  m = fun_emsFormBuilder_show_messages(content, by, userIp, track, date)
  //reply  message ui
  console.log(m)

  form_type_emsFormBuilder = formType;
  console.log(form_type_emsFormBuilder)

  const replayM = function () {
    let r
    if (form_type_emsFormBuilder != 'subscribe' && form_type_emsFormBuilder != 'register' && form_type_emsFormBuilder != 'survey') {
      /* 
      <div class="mb-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"  id="replay_section__emsFormBuilder">
           <label for="replayM_emsFormBuilder" class="form-label m-2">${efb_var.text.reply}:
               textarea</label>
           <textarea class=" efb form-control efb" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}></textarea>
       </div>
      
      */
      r = `   
      <div class="mb-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"  id="replay_section__emsFormBuilder">
        <label for="replayM_emsFormBuilder" class="form-label m-2">${efb_var.text.reply}:</label>
        <textarea class=" efb form-control efb" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
      </div>
     <div class="col text-right row mx-1">
     <button type="submit" class="btn efb btn-primary btn-sm" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})"><i class="efb bi-reply mx-1"></i> ${efb_var.text.reply} </button>
     <p class="mx-2 my-1 text-pinkEfb" id="replay_state__emsFormBuilder"></p>
     </div></div>`;
    } else {
      r = '<!-- comment --!>';
    }

    return r;
  }
  //210407-TD74K

  const body = `
    <div class="efb modal-body overflow-auto py-0 my-0  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="resp_efb">
      ${m} 
     </div>
     ${replayM()}
     </div>
      
     </div>
   
  </div>
  <div>


</div></div>`;




  show_modal_efb(body, efb_var.text.response, 'efb bi-chat-square-text mx-2', 'saveBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show();

  // fun_add_event_CloseMenu();

  window.scrollTo({ top: 0, behavior: 'smooth' });

}

function fun_add_event_CloseMenu() {
  //console.log(`fun_add_event_CloseMenu`)

  /*  $(document).ready(function(){
     $('#action_menu_btn').click(function(){
       $('.action_menu').toggle();
     });
     }); */

  /*   document.getElementById("close-menu").addEventListener("click",event => {
      document.getElementById('action_menu').style.display= "none";
      }); */

}
// نمایش و عدم نمایش دکمه های صفحه اصلی
function fun_backButton(state) {
  //console.l(`fun_backButton` , document.getElementById("more_emsFormBuilder").style.display ,state)


  if (document.getElementById("more_emsFormBuilder").style.display == "block" && state == 1) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
    //console.l(document.getElementById("more_emsFormBuilder").style.display ,255)
  } else {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }


  if (state == 0 || state == null) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
  }

  if (state == 2) {
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
  //پایان عدم حذف 


}


// دکمه بازگشت و نمایش لیست اصلی
function fun_emsFormBuilder_back() {
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);


}


function fun_emsFormBuilder_show_messages(content, by, userIp, track, date) {


  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  let m = `<Div class="bg-response efb card-body my-2 py-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
   <p class="small mb-0"><span>${efb_var.text.by}:</span> ${by}</p>
   <p class="small mb-0"><span>${efb_var.text.ip}:</span> ${userIp}</p>
  ${track != 0 ? `<p class="small mb-0"><span> ${efb_var.text.trackNo}:</span> ${track} </p>` : ''}
  <p class="small mb-0"><span>${efb_var.text.ddate}:</span> ${date} </p>  
  <hr>
  <h6 class="efb ">${efb_var.text.response} </h6>`;
  for (const c of content) {
    let value = `<b>${c.value}</b>`;
    //console.l(`value up ${value}`)    ;
    if (c.value == "@file@" && c.state == 2) {
      if (c.type == "Image") {
        value = `</br><img src="${c.url}" alt="${c.name}" class="img-thumbnail">`
      } else if (c.type == "Document") {
        value = `</br><a class="btn btn-primary" href="${c.url}" >${c.name}</a>`
      } else if (c.type == "Media") {
        const audios = ['mp3', 'wav', 'ogg'];
        let media = "video";
        audios.forEach(function (aud) {
          if (c.url.indexOf(aud) !== -1) {
            media = 'audio';
          }
        })
        if (media == "video") {
          const len = c.url.length;
          const type = c.url.slice((len - 3), len);
          // console.log(`poster_emsFormBuilder [${poster_emsFormBuilder}]`);
          value = type !== 'avi' ? `</br><div class="px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="text-center" ><a href="${c.url}">${efb_var.text.videoDownloadLink}</a></p>` : `<p class="text-center"><a href="${c.url}">${efb_var.text.downloadViedo}</a></p>`;
        } else {
          value = `<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
      } else {
        //console.l(c.url ,c.url.length)
        value = `</br><a class="btn btn-primary" href="${c.url}">${c.name}</a>`
      }
    }
    if (c.id_ == 'passwordRegisterEFB') value = '**********';
    m += `<p class="my-0">${c.name}: <span class="mb-1"> ${value !== '<b>@file@</b>' ? value : ''}</span> </p> `
  }
  m += '</div>';
  //console.l(`m`,m)
  return m;
}


/* function fun_emsFormBuilder_show_messages(content,by,userIp,track,date){
 
  //console.l(`by[${by}]userIp[${userIp}] , track[${track}]`)
  if (by ==1) {by='Admin'}else if(by==0 ||by.length==0 || by.length==-1 )(by=efb_var.text.guest)
  let m =`<Div class="border border-light round  p-2 ${efb_var.rtl==1 ? 'rtl-text' :''}"><div class="border-bottom mb-1 pb-1">
   <span class="small"><b>${efb_var.text.info}</b></span></br>
   <span class="small">${efb_var.text.by}: ${by}</span></br>
   <span class="small">${efb_var.text.ip}: ${userIp}</span></br>
  ${track!=0 ? `<span> ${efb_var.text.trackNo}: ${track} </span></br>` :''}
  <span> ${efb_var.text.date}: ${date} </span></small>
  </div>
  <div class="mx-1">
  <h6 class="my-3">${efb_var.text.response} </h6>`;
  for (const c of content){
    let value = `<b>${c.value}</b>`;
    //console.l(`value up ${value}`)    ;
    if (c.value =="@file@" && c.state==2){
     if(c.type=="Image"){
      value =`</br><img src="${c.url}" alt="${c.name}" class="img-thumbnail">`
     }else if(c.type=="Document"){
      value =`</br><a class="btn btn-primary" href="${c.url}" >${c.name}</a>`
     }else if(c.type=="Media"){
        const audios = ['mp3','wav','ogg'];
        let media ="video";
        audios.forEach(function(aud){    
          if(c.url.indexOf(aud)!==-1){
            media = 'audio';     
          }
        })
        if(media=="video"){          
          const len =c.url.length;
          const type = c.url.slice((len-3),len);
         // console.log(`poster_emsFormBuilder [${poster_emsFormBuilder}]`);
          value = type !=='avi' ? `</br><div class="px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="text-center" ><a href="${c.url}">${efb_var.text.videoDownloadLink}</a></p>` :`<p class="text-center"><a href="${c.url}">${efb_var.text.downloadViedo}</a></p>`;
        }else{
          value=`<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
     }else{
      //console.l(c.url ,c.url.length)
      value =`</br><a class="btn btn-primary" href="${c.url}">${c.name}</a>`
    }
    }
    
    m +=`<p class="my-0">${c.name}: <span class="mb-1"> ${value!=='<b>@file@</b>'?value:''}</span> </p> `
  }
  m+= '</div></div>';
//console.l(`m`,m)
  return m;
} */


// دکمه نمایش بیشتر لیست اصلی
function fun_emsFormBuilder_more() {
  count_row_emsFormBuilder += 5;
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })

}

// تابع نمایش ویرایش فرم
function fun_ws_show_edit_form(id) {
  valj_efb = JSON.parse(localStorage.getItem("valj_efb"));
  console.log(localStorage.getItem('valj_efb'), id, efb_var);
  creator_form_builder_Efb();
  setTimeout(() => {
    editFormEfb()
  }, 100)
  /* document.getElementById('content-efb').innerHTML = `<div class="col-md-12 ">
  <div id="emsFormBuilder-form" >
  <form id="emsFormBuilder-form-id" class="${efb_var.rtl == 1 ? 'rtl-text' : ''}">
      <h1 id="emsFormBuilder-form-title">${efb_var.text.easyFormBuilder}</h1>
      
      <div class="all-steps" id="all-steps"> 
          <span class="step"><i class="fa fa-tachometer"></i></span> 
          <span class="step"><i class="fa fa-briefcase"></i></span> 
          <div class="addStep" id="addStep" >
          </div>
          <span class="step"><i class="px-1 fa fa-floppy-o"></i></span> 
      </div>
      <div class="all-steps" > 
          <h5 class="step-name f-setp-name" id ="step-name">${efb_var.text.define}</h5> 
      </div>
      <div id="message-area"></div>
      <div class="tab" id="firsTab">
          <h5>${efb_var.text.formName}:*</h5>
          <input placeholder="" type="text"  name="setps" class="require emsFormBuilder" id="form_name" max="20">
          </br>
          <h5>${efb_var.text.numberOfSteps}:*</h5>
          <input placeholder="1,2,3.." type="number"  name="setps" class="require emsFormBuilder" id="steps" max="20">
      </div>
      <div class="tab" id="tabInfo">

      </div>
      <div  id="tabList">

      </div>
      <div class="thanks-message text-center" id="emsFormBuilder-text-message-view"> 
          <h3>${efb_var.text.done}</h3> <span>${efb_var.text.formIsBuild}</span>
      </div>
      <div style="overflow:auto;" id="nextprevious">
          <div style="float:right;"> <button type="button" id="prevBtn" class="mat-shadow emsFormBuilder p-3" onclick="nextPrev(-1)"><i class="fa fa-angle-double-left"></i></button> <button type="button" id="nextBtn" class="mat-shadow emsFormBuilder p-3" onclick="nextPrev(1)"><i class="fa fa-angle-double-right"></i></button> </div>
          <div style="float:left;"> 
              <button type="button" class="mat-shadow emsFormBuilder p-3" onClick="helpLink_emsFormBuilder()"><i class="fa fa-question" placeholder="Help"></i></button>
              <button type="button" class="mat-shadow emsFormBuilder p-3" onClick="preview_emsFormBuilder()"><i class="fa fa-eye" placeholder="preview"></i></button>
              
          </div>
      </div>

    </form>      
  </div>
</div>`;
  form_ID_emsFormBuilder = id;
  run_code_ws_1();
  run_code_ws_2();
  fun_render_view_core_emsFormBuilder(); */

}


function fun_send_replayMessage_emsFormBuilder(id) {
  //پاسخ مدیر را ارسال می کند به سرور 


  const message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g, '</br>');
  //console.l(message,id)
  document.getElementById('replay_state__emsFormBuilder').innerHTML = `<i class="fas fa-spinner fa-pulse"></i> Sending...`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  // +='disabled fas fa-spinner fa-pulse';
  const ob = [{ name: 'Message', value: message, by: ajax_object_efm.user_name }];
  //console.l(ob);
  let isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);
  if (message.length < 1 || isHTML(message)) {
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i>${efb_var.text.error}${efb_var.text.youCantUseHTMLTagOrBlank}</h6>`;
    return
  }
  fun_send_replayMessage_ajax_emsFormBuilder(ob, id)


}


function fun_ws_show_list_messages(value) {
  //v2
  // show list of filled out of the form;
  //console.log(form_type_emsFormBuilder)
  let rows = '';
  let no = 1;
  let head = `<!-- rows -->`;
  let iconRead = 'bi-envelope-open';
  let iconNotRead = ' <path  d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>';
  if (form_type_emsFormBuilder == 'subscribe') {
    head = `<div ><button   class="btn efb btn-primary btn-lg mt-2 mb-3"  onClick="generat_csv_emsFormBuilder()" title="${efb_var.text.downloadCSVFileSub}" > <h4> <i class="efb bi-download mx-2""></i>${efb_var.text.downloadCSVFile}</h4></button ></div>`;
    iconRead = 'bi-person';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'register') {
    iconRead = 'bi-person ';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'survey') {
    //console.log(efb_var.text.availableInProversion)
    const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `unlimted_show_panel_emsFormBuilder('${efb_var.text.availableInProversion}')`;
    head = `<div >
    <button  class="btn efb btn-primary btn-lg mt-2 mb-3"  onClick="${fun}" title="${efb_var.text.downloadCSVFileSub}" > <h4>  <i class="efb bi-download mx-2"></i>${efb_var.text.downloadCSVFile}</h4></button >
    <button  class="btn efb btn-primary btn-lg mt-2 mb-3"  onClick="convert_to_dataset_emsFormBuilder()" title="${efb_var.text.chart}" > <h4> <i class="efb bi-bar-chart-line mx-2"></i>${efb_var.text.chart}</h4></button >
    </div>`;
    iconRead = 'bi-chat-square-text';
    iconNotRead = ' <path  d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>';
  }
  /// console.log(value);
  if (value.length > 0) {
    for (const v of value) {
      const state = Number(v.read_);
      console.log(v);
      rows += `<tr class="emsFormBuilder-tr" id="" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${Number(state) == 0 ? efb_var.text.newResponse : efb_var.text.read}" onClick="fun_open_message_emsFormBuilder(${v.msg_id} , ${state})">                    
         <th scope="row" class="">${v.track}</th>
         <td class="">${v.date}</td>
            <td> 
            <button type="button" class="efb btn btn-comment btn-sm" >
             ${Number(state) == 0 ? `<svg xmlns="http://www.w3.org/2000/svg" class="jump" width="14" height="14" fill="currentColor" class="bi bi-chat-fill" viewBox="0 0 16 16">${iconNotRead}</svg>` : `<i id="icon-${v.msg_id}" class="efb ${iconRead} text-muted"></i> `}</button>
            </td>                               
            </tr>` ;
      no += 1;
    }
  } else {
    rows = `<tr class="emsFormBuilder-tr fs-6 efb"><td>${efb_var.text.noResponse}</td><td></td><td></td></tr>`
  }





  document.getElementById('content-efb').innerHTML = `${head}
    <h4 class="title-holder"> <img src="${efb_var.images.title}" class="title">
    <i class="efb bi-archive title-icon me-2"></i>${efb_var.text.messages}
    </h4>
    <div class="card">
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

  if (form_type_emsFormBuilder == 'subscribe' || form_type_emsFormBuilder == 'survey') fun_export_rows_for_Subscribe_emsFormBuilder(value);

}



function fun_delete_form_with_id_by_server(id) {
  //console.l(ajax_object_efm.ajax_url ,id);
  jQuery(function ($) {
    data = {
      action: "remove_id_Emsfb",
      type: "POST",
      id: id,
      nonce: ajax_object_efm_core.nonce,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      //console.l(res);
      if (res.success == true) {
        setTimeout(() => {
          noti_message_efb(efb_var.text.done, '', 3 , 'info')
        }, 3)
      } else {
        //console.l(res);
        setTimeout(() => {
          noti_message_efb(efb_var.text.error, '', 3 ,'danger')
        }, 3)
      }
    })
  });

}




function emsFormBuilder_messages(id) {
  // console.log(`ajax_object_efm.ajax_value[${id}]  $`)
  const row = ajax_object_efm.ajax_value.find(x => x.form_id == id)
  // console.log(ajax_object_efm.ajax_value);
  // console.log(row.form_type,form_type_emsFormBuilder)
  form_type_emsFormBuilder = row.form_type;
  fun_get_messages_by_id(Number(id));
  emsFormBuilder_waiting_response();
  fun_backButton(0);
  //fun_backButton(1);
}

function fun_open_message_emsFormBuilder(msg_id, state) {
  console.log('fun_open_message_emsFormBuilder')
  //console.log(msg_id,state ,valueJson_ws_messages);
  //console.log(form_type_emsFormBuilder)
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
        //console.l(res.data.ajax_value ,res);
        const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
        const len = value.length
        console.log(res);
        setTimeout(() => {
          formName_Efb = valj_efb[0].formName;
          form_ID_emsFormBuilder = id;
          localStorage.setItem('valj_efb', JSON.stringify(value));
          const edit = { id: res.data.id, edit: true };
          localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
          fun_ws_show_edit_form(id);
        }, len * 6)

        fun_ws_show_edit_form(id)
      } else {
        //console.l(res);
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
        let iconRead = 'fa fa-envelope-open-o';
        if (form_type_emsFormBuilder == 'subscribe') {
          iconRead = 'fa fa-user-o';
        } else if (form_type_emsFormBuilder == 'register') {
          iconRead = 'fa fa-user-o';
        }
        document.getElementById(`icon-${id}`).className = iconRead;
        document.getElementById(`efbCountM`).innerHTML = parseInt(document.getElementById(`efbCountM`).innerHTML) - 1;
        // console.log(res.data.ajax_value ,res);
        if (res.data.ajax_value != undefined) {
          const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
          localStorage.setItem('valueJson_ws_p', JSON.stringify(value));
          const edit = { id: res.data.id, edit: true };
          localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
          fun_ws_show_edit_form(id)
        }
      } else {
        // console.log(res);
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
        // console.log(res.data.ajax_value ,res);
        if (res.data.ajax_value != undefined) {
          const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
          const len = value.length
          console.log(res);
          setTimeout(() => {
            formName_Efb = valj_efb[0].formName;
            localStorage.setItem('valj_efb', JSON.stringify(value));
            const edit = { id: res.data.id, edit: true };
            localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
            fun_ws_show_edit_form(id);
          }, len * 6)
        }
      } else {
        // console.log(res);
      }
    })
  });
} */
function fun_get_messages_by_id(id) {
  //console.l(`fun_get_messages_by_id(${id})` ,ajax_object_efm.ajax_url)
  jQuery(function ($) {
    data = {
      action: "get_messages_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      type: "POST",
      form: form_type_emsFormBuilder,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      //console.l(`messages`,res);
      if (res.success == true) {
        valueJson_ws_messages = res.data.ajax_value;
        localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));
        //console.l(`resp`,res);
        fun_ws_show_list_messages(valueJson_ws_messages)
      } else {
        //console.l(res);
      }
    })
  });
}
function fun_emsFormBuilder_get_all_response_by_id(id) {
  //console.l(`fun_emsFormBuilder_get_all_response_by_id(${id})` ,ajax_object_efm.ajax_url)
  jQuery(function ($) {
    data = {
      action: "get_all_response_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      type: "POST",
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      //console.l(`messages`,res);
      if (res.success == true) {

        // localStorage.setItem('valueJson_ws_messages',JSON.stringify(valueJson_ws_messages) );
        //console.l(`get_all_response_id_Emsfb`,res);
        fun_ws_show_response(res.data.ajax_value)
      } else {
        //console.l(res);
      }
    })
  });
}



function fun_send_replayMessage_ajax_emsFormBuilder(message, id) {
  //console.l(`fun_send_replayMessage_ajax_emsFormBuilder(${id})` ,message ,ajax_object_efm.ajax_url)
  if (message.length < 1) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = "Please Enter message";
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
        //console.l(`response`,res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        document.getElementById('replayM_emsFormBuilder').innerHTML = "";
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');

        // اضافه شدن به سمت یو آی 
        const userIp = ajax_object_efm.user_ip;
        const date = Date();
        //console.l(message,"content" ,message.by);
        document.getElementById('replayM_emsFormBuilder').value = "";

        fun_emsFormBuilder__add_a_response_to_messages(message, message[0].by, ajax_object_efm.user_ip, 0, date);
        let chatHistory = document.getElementById("resp_efb");
        chatHistory.scrollTop = chatHistory.scrollHeight;
      } else {
        //console.l(res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
  //v2
  //respomse
  //console.l('592',message,`by[${by}]userIp[${userIp}]track[${track}]date[${date}]`);
  // document.getElementById('conver_emsFormBuilder').innerHTML+= fun_emsFormBuilder_show_messages(message,by,userIp,track,date);
  const resp = fun_emsFormBuilder_show_messages(message, by, userIp, track, date);
  const body = `<div class="efb  mb-3"><div class="efb clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
  /* show_modal_efb(body, efb_var.text.response, 'efb bi-chat-square-text mx-2', 'saveBox')
  const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  myModal.show(); */
}


function fun_ws_show_response(value) {
  //console.l("598",value)
  for (v of value) {
    //console.log(v.content);
    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'Message', value: 'message not exists' }
    fun_emsFormBuilder__add_a_response_to_messages(content, v.rsp_by, v.ip, 0, v.date);
  }
  //document.getElementById('loading_message_emsFormBuilder').remove();
}


function fun_show_content_page_emsFormBuilder(state) {
  //console.l(state);
  if (state == "forms") {
    /*  if( state_seting_emsFormBuilder!=true){
       fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
       if (valueJson_ws_form.length>count_row_emsFormBuilder) fun_backButton(2);
       state=1;
     }else{ */
    window.location.reload();
    document.getElementById('content-efb').innerHTML = `<div class="card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb-color text-center"><i class="fas fa-spinner fa-pulse"></i> ${efb_var.text.loading}</div>`
    //}

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
    //console.l('nav-link',count , no)
    if (el.classList.contains('active')) el.classList.remove('active');
    if (count == no) el.classList.add('active');
    //active
  }
}

function fun_show_help__emsFormBuilder() {
  document.getElementById("more_emsFormBuilder").style.display = "none";
  listOfHow_emsfb = {
    /*  1:{title:'How to activate pro version of Easy form builder.',url:'https://www.youtube.com/embed/RZTyFcjZTSM'},*/
    2: { title: efb_var.text.howConfigureEFB, url: 'https://www.youtube.com/embed/DEQNHMPT0rQ' },
    3: { title: efb_var.text.howGetGooglereCAPTCHA, url: 'https://www.youtube.com/embed/a1jbMqunzkQ' },
    4: { title: efb_var.text.howActivateAlertEmail, url: 'https://www.youtube.com/embed/So2RAzu-OHU' },
    5: { title: efb_var.text.howCreateAddForm, url: 'https://www.youtube.com/embed/7jS01CEtbDg' },
    6: { title: efb_var.text.howActivateTracking, url: 'https://www.youtube.com/embed/im3aKby4E14' },
    7: { title: efb_var.text.howWorkWithPanels, url: 'https://www.youtube.com/embed/7jS01CEtbDg' },
    8: { title: efb_var.text.howAddTrackingForm, url: 'https://www.youtube.com/embed/c1_gCFihrH8' },
    9: { title: efb_var.text.howFindResponse, url: 'https://www.youtube.com/embed/vqKi9BJbO7k' },
  }


  let str = "";
  for (const l in listOfHow_emsfb) {
    //console.l(l);
    str += `

    <a class="btn efb btn-darkb text-white btn-lg d-block mx-3 mt-2" href="${listOfHow_emsfb[l].url}">
        <i class="efb bi-youtube mx-1"></i>${listOfHow_emsfb[l].title}
   </a>

  `
  }
  document.getElementById('content-efb').innerHTML = `
  <img src="${efb_var.images.title}"  class="crcle-footer">
  <div class="container row">
  <h4 class="title-holder">
      <img src="${efb_var.images.title}" class="title">
      <i class="efb bi-info-circle title-icon me-2"></i>${efb_var.text.help}
  </h4>
  <div class="crd efb col-md-7"><div class="card-body"> <div class="d-grid gap-2">
  ${str}
  </div></div></div>
  <div class="col-md-4 mx-1 py-5 crd efb">
                  <img src="${efb_var.images.logo}"  class="description-logo efb">
                  <h1 class="efb" onclick="Link_emsFormBuilder('efb')"><font style="vertical-align: inherit;"><font style="vertical-align: inherit;">${efb_var.text.easyFormBuilder}</font></font></h1>
                  <h3 class="efb  card-text " onclick="Link_emsFormBuilder('ws')">${efb_var.text.byWhiteStudioTeam}</h3>
                  <div class="clearfix"></div>
                  <p class=" card-text efb pb-3 fs-7">
                  ${efb_var.text.youCanFindTutorial} ${efb_var.text.proUnlockMsg}
                  </p>
                  <a class="btn efb btn-danger btn-lg" href="#form"><i class="efb bi-gem me-2"></i>${efb_var.text.getProVersion}</a>
                  <a class="btn mt-1 efb btn-outline-pink btn-lg" onclick="Link_emsFormBuilder('tutorial')"><i class="efb bi-info-circle me-2"></i>${efb_var.text.documents}</a>
              </div>
  </div>

 
  `;

}
function fun_show_setting__emsFormBuilder() {
  // console.log( 610,ajax_object_efm.setting);
  let activeCode = 'null';
  let sitekey = 'null';
  let secretkey = 'null';
  let email = 'null';
  let trackingcode = 'null';
  let apiKeyMap = 'null';
  //console.l(`valueJson_ws_setting ${valueJson_ws_setting.length}`)
  if ((ajax_object_efm.setting[0] && ajax_object_efm.setting[0].setting.length > 5) || typeof valueJson_ws_setting == "object" && valueJson_ws_setting.length != 0) {

    // اضافه کردن تنظیمات

    if (valueJson_ws_setting.length == 0) valueJson_ws_setting = JSON.parse(ajax_object_efm.setting[0].setting.replace(/[\\]/g, ''));
    //console.l(`setting`,valueJson_ws_setting)
    const f = (name) => {
      //console.l('valueJson_ws_setting[name]', valueJson_ws_setting[name])
      if (valueJson_ws_setting[name]) {
        //console.l(name, valueJson_ws_setting[name]);
        return valueJson_ws_setting[name]
      } else {
        //console.l(name, valueJson_ws_setting[name]);
        return 'null'
      }
    }
    activeCode = f('activeCode');
    sitekey = f(`siteKey`);
    secretkey = f(`secretKey`);
    email = f(`emailSupporter`);
    trackingcode = f(`trackingCode`);
    apiKeyMap = f(`apiKeyMap`)
  }


  // console.log(`lengi of valueJson_ws_setting [${valueJson_ws_setting.length}]` ,valueJson_ws_setting);
  //console.l(`activeCode[${activeCode}] sitekey[${sitekey}] secretkey[${secretkey}] email[${email}] trackingcode[${trackingcode}]`);

  document.getElementById('content-efb').innerHTML = `
  <div class="container">
            <h4 class="title-holder">
                <img src="${efb_var.images.title}" class="title">
                <i class="efb bi-gear title-icon me-2"></i>Setting
            </h4>
            <div class="crd efb">
                <div class="card-body">

                        <nav>
                            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="efb nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><i class="efb bi bi-gear mx-2"></i>${efb_var.text.general}</button>
                            <button class="efb nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-google" type="button" role="tab" aria-controls="nav-profile" aria-selected="false"><i class="efb bi bi-google mx-2"></i>${efb_var.text.googleKeys}</button>
                            <button class="efb nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-email" type="button" role="tab" aria-controls="nav-contact" aria-selected="false"><i class="efb bi bi-at mx-2"></i>${efb_var.text.emailSetting}</button>
                        </div>
                        </nav>
                        <div class="tab-content" id="nav-tabContent">
                        <div class="tab-pane fade show active" id="nav-general" role="tabpanel" aria-labelledby="nav-home-tab">
                            <!--General-->
                            <div class="m-3">
                                <h5 class="card-title mt-4 ">
                                    <i class="efb bi-gem m-3"></i>${efb_var.text.proVersion}
                                </h5>
                                <a class="mx-5" href="${proUrl_ws}">${efb_var.text.clickHereGetActivateCode}</a>
                                <div class="card-body mx-4">
                                    <input type="text" class="form-control efb h-d-efb" id="activeCode_emsFormBuilder" placeholder="${efb_var.text.enterActivateCode}" ${activeCode !== "null" ? `value="${activeCode}"` : ""}>
                                    <span id="activeCode_emsFormBuilder-message" class="text-danger"></span>
                                </div>

                                <h5 class="card-title mt-4 ">
                                    <i class="efb bi-file-earmark-minus m-3"></i>${efb_var.text.clearFiles}
                                </h5>
                                <p class="mx-5">${efb_var.text.youCanRemoveUnnecessaryFileUploaded}</p>
                                <div class="card-body text-center">
                                    <button type="button" class="btn efb btn-outline-pink btn-lg " OnClick="clear_garbeg_emsFormBuilder()" id="clrUnfileEfb">
                                        <i class="efb bi-x-lg me-2"></i>${efb_var.text.clearUnnecessaryFiles}
                                    </button>
                                </div>
                                <div class="clearfix"></div>
                                <h5 class="card-title mt-4 ">
                                    <i class="efb bi-search m-3"></i>${efb_var.text.trackingCodeFinder}
                                </h5>
                                <p class="mx-5">${efb_var.text.copyAndPasteBelowShortCodeTrackingCodeFinder}</p>
                                <div class="card-body mx-4">
                                        <div class="row col-12">
                                            <div class="col-md-8">
                                              <input type="text"  class="form-control efb h-d-efb " id="shortCode_emsFormBuilder" value="[EMS_Form_Builder_tracking_finder]" readonly>
                                              <span id="shortCode_emsFormBuilder-message" class="text-danger"></span>
                                            </div> 
                                              <button type="button" class="btn col-md-4 efb h-d-efb btn-outline-pink btn-lg " onclick="copyCodeEfb('shortCode_emsFormBuilder')">
                                                  <i class="efb bi-clipboard-check me-2"></i> ${efb_var.text.copy}
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
                             <div class="m-3 p-3 efb alert-info" role="">
                                <h4 class="alert-heading">🎉 ${efb_var.text.SpecialOffer} </h4>
                                <div>${googleCloudOffer()} </div>
                              </div>
                             <h5 class="card-title mt-4 ">
                                <i class="efb bi-person-check m-3"></i>${efb_var.text.reCAPTCHAv2}
                            </h5>
                            <p class="mx-5"><a target="_blank" href="https://www.google.com/recaptcha/about/">${efb_var.text.reCAPTCHA}</a> ${efb_var.text.protectsYourWebsiteFromFraud} <a target="_blank" href="https://youtu.be/a1jbMqunzkQ">${efb_var.text.clickHereWatchVideoTutorial}</a></p>
                            <div class="card-body mx-4">                                   
                                <label class="form-label mx-2">${efb_var.text.siteKey}</label>
                                <input type="text" class="form-control col-12 efb h-d-efb ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="sitekey_emsFormBuilder" placeholder="${efb_var.text.enterSITEKEY}" ${sitekey !== "null" ? `value="${sitekey}"` : ""}>
                                <span id="sitekey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                                <label class="form-label mx-2 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                <input type="text" class="form-control col-12 efb h-d-efb ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="secretkey_emsFormBuilder" placeholder="${efb_var.text.EnterSECRETKEY}" ${secretkey !== "null" ? `value="${secretkey}"` : ""}>
                                <span id="secretkey_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                            </div>
                            <h5 class="card-title mt-4 ">
                                <i class="efb bi-geo-alt m-3"></i> ${efb_var.text.maps} 
                            </h5>
                            <p class="mx-5">${efb_var.text.youNeedAPIgMaps} <a href="#">${efb_var.text.clickHereWatchVideoTutorial}</a> </p>
                            <div class="card-body mx-4">                                   
                                <label class="form-label mx-2 ">${efb_var.text.aPIKey}</label>
                                <input type="text" class="form-control efb h-d-efb ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="apikey_map_emsFormBuilder" placeholder="${efb_var.text.enterAPIKey}" ${apiKeyMap !== "null" ? `value="${apiKeyMap}"` : ""}>
                                <span id="apikey_map_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                            </div>
                              <!--End Google-->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="nav-email" role="tabpanel" aria-labelledby="nav-contact-tab">
                            <div class="mx-3">
                                <!--Email-->
                                <h5 class="card-title mt-4 ">
                                    <i class="efb bi-at m-3"></i>${efb_var.text.alertEmail}
                                </h5>
                                <p class="mx-5">${efb_var.text.whenEasyFormBuilderRecivesNewMessage}</p>
                                <div class="card-body mx-4">
                                    <label class="form-label mx-2">${efb_var.text.email}</label>
                                    <input type="email" class="form-control efb h-d-efb ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="${efb_var.text.enterAdminEmail}" ${email !== "null" ? `value="${email}"` : ""}>
                                    <span id="email_emsFormBuilder-message" class="text-danger col-12 efb"></span>
                                </div>
                                
                                <h5 class="card-title mt-4 col-12 efb ">
                                    <i class="efb bi-envelope m-3"></i>${efb_var.text.emailServer}
                                </h5>
                                <p class="mx-5">${efb_var.text.beforeUsingYourEmailServers}</p>
                                <div class="card-body mx-4">
                                    <button type="button" class="btn col-md-4 efb btn-outline-pink btn-lg "onClick="clickToCheckEmailServer()" id="clickToCheckEmailServer">
                                        <i class="efb bi-chevron-double-up me-2 text-center"></i>${efb_var.text.clickToCheckEmailServer}
                                    </button>
                                   
                                </div>
                                <!--End Email-->
                            </div>
                        </div>
                      
                        <button type="button" id="save-stng-efb" class="btn efb btn-primary btn-lg ${efb_var.rtl == 1 ? 'float-start' : 'float-end '}" mt-2 mx-5"  onClick="fun_set_setting_emsFormBuilder()">
                            <i class="efb bi-save me-2"></i>${efb_var.text.save}
                        </button>
                  
                </div>
            </div>
        </div>
`

}


function fun_set_setting_emsFormBuilder() {
  //console.l("fun_set_setting_emsFormBuilder");
  // fun_state_loading_message_emsFormBuilder(1);
  const nnrhtml = document.getElementById('save-stng-efb').innerHTML;
  document.getElementById('save-stng-efb').innerHTML = `<i class="bi bi-hourglass-split"></i>`
  fun_State_btn_set_setting_emsFormBuilder();
  const f = (id) => {
    const el = document.getElementById(id)
    let r = "NotFoundEl"
    if (el.type == "text" || el.type == "email") {
      return el.value;
    } else if (el.type == "checkbox") {
      return el.checked;
    }
    return "NotFoundEl"
  }
  const v = (id) => {
    const el = document.getElementById(id);

    if (el.type !== "checkbox") {

      if (el.value.length > 0 && el.value.length < 20 && id !== "activeCode_emsFormBuilder" && id !== "email_emsFormBuilder") {
        document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
        el.classList.add('invalid');
        window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
        return false;
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
    }
    return true;
  }
  const ids = ['apikey_map_emsFormBuilder', 'sitekey_emsFormBuilder', 'secretkey_emsFormBuilder', 'email_emsFormBuilder', 'activeCode_emsFormBuilder'];
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
    const email = f(`email_emsFormBuilder`);
    //  const trackingcode = f(`trackingcode_emsFormBuilder`);
    const apiKeyMap = f(`apikey_map_emsFormBuilder`)
    fun_send_setting_emsFormBuilder({ activeCode: activeCode, siteKey: sitekey, secretKey: secretkey, emailSupporter: email, apiKeyMap: `${apiKeyMap}` });
  }

  document.getElementById('save-stng-efb').innerHTML = nnrhtml
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
  //console.l(data);
  //ارسال تنظیمات به ووردپرس
  jQuery(function ($) {
    data = {
      action: "set_setting_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      message: data
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      //console.l(`messages`,res);
      let m = ''
      let t = efb_var.text.done
      if (res.success == true) {
        //console.l(`resp`,res);
        valueJson_ws_setting = data.message;
        //console.log(` first lengi of valueJson_ws_setting [${valueJson_ws_setting.length}]` ,valueJson_ws_setting);    
        fun_show_setting__emsFormBuilder();
        if (res.data.success == true) {
          // اگر پاسخ  مست گرفت از سرور
          m = efb_var.text.saved;
        } else {
          //console.l(res.data);
          t = efb_var.text.error
          m = res.data.m;
        }
      } else {
        //console.l(res);.
        t = '';
        m = res;
      }
      noti_message_efb(t, m, 3.7)
    })
  });
}


function fun_find_track_emsFormBuilder() {
  //function find track code
  const el = document.getElementById("track_code_emsFormBuilder").value;

  if (el.length < -1) {
    noti_message_efb(efb_var.text.error, efb_var.text.trackingCodeIsNotValid, 7 ,'warning');

  } else {
    //console.l('fun_find_track_emsFormBuilder',el  )
    document.getElementById('track_code_emsFormBuilder').disabled = true;
    document.getElementById('track_code_btn_emsFormBuilder').disabled = true;
    const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
    document.getElementById('track_code_btn_emsFormBuilder').innerHTML = `<i class="bi-hourglass-split"></i>`;
    //console.l(btnValue);



    jQuery(function ($) {

      //console.l('get_track_id_Emsfb');  
      data = {
        action: "get_track_id_Emsfb",
        nonce: ajax_object_efm_core.nonce,
        value: el,
      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {

        if (res.data.success == true) {
          valueJson_ws_messages = res.data.ajax_value;
          // console.l(`res.data`,res.data);
          localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));
          document.getElementById("more_emsFormBuilder").style.display = "none";
          fun_ws_show_list_messages(valueJson_ws_messages)
          document.getElementById('track_code_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue;
        } else {
          noti_message_efb(efb_var.text.error, res.data.m, 4 ,'warning');
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
  console.log(document.getElementById('clrUnfileEfb'))
  const innrhtm = document.getElementById('clrUnfileEfb').innerHTML;
  document.getElementById('clrUnfileEfb').innerHTML = `<i class="bi bi-hourglass-split"></i>`
  document.getElementById('clrUnfileEfb').classList.add('disabled')
  jQuery(function ($) {
    //console.l('clear_garbeg_emsFormBuilder');  
    data = {
      action: "clear_garbeg_Emsfb",
      nonce: ajax_object_efm_core.nonce
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {
        noti_message_efb(efb_var.text.done, res.data.m, 4.7);
      } else {
        noti_message_efb(efb_var.text.error, res.data.m, 4.7 ,'danger');

      }
    })
  });
  document.getElementById('clrUnfileEfb').classList.remove('disabled')
  document.getElementById('clrUnfileEfb').innerHTML = innrhtm;
}


function fun_export_rows_for_Subscribe_emsFormBuilder(value) {
  //json ready for download 
  //778899
  // let exp =[];
  let head = {};
  let heads = [];
  let ids = [];
  let count = -1;
  console.log(value.length);
  console.log(value);
  const rows = Array.from(Array(value.length), () => Array(100).fill('null@EFB'));
  let i_count = -1;
  for (v of value) {
    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'not found', value: 'not found' }
    console.log(content.length);
    // const rows = Array.from(Array(content.length+1), () => Array(100).fill('null@EFB'));
    console.log(content);
    count += 1;
    // let rows =[];
    i_count += 1;
    let countMultiNo = [];
    let NoMulti = [];
    // console.log(v);
    // let rows ={};
    //  console.log(content.length);

    //  const row = new Array(1000).fill('null@EFB');
    for (c in content) {
      // console.log(content[c],"chck");
      // rows = Object.assign(rows, {[c.name]:c.value});
      let value_col_index;
      if (content[c].type != "checkbox") {

        if (c == 0) {
          rows[0][0] = content[c].name;
          value_col_index = 0;
        } else {
          value_col_index = rows[0].findIndex(x => x == content[c].name)
          if (value_col_index == -1) {
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = content[c].name;
          }
          //  rows[parseInt(c)+1][value_col_index]=content[c].value;

          rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          //console.log(rows[parseInt(c)+1][value_col_index],789);
        }
      } else {
        //  console.log(c)
        let name = `${content[c].name} [${content[c].id_}]`;
        let test = -1;
        if (c == 0) {
          rows[0][0] = name;
          value_col_index = 0;
        } else {
          test = 1;
          name = `${content[c].name} [${content[c].id_}]`;
          let value_col_index = rows[0].findIndex(x => x == name);
          console.log(name, value_col_index);
          if (value_col_index != -1) {
            // rows[0][value_col_index]=name;
            rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          } else {
            test = 2;
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            // console.log(content[c] , c );
            rows[0][parseInt(value_col_index)] = name;
            if (name == 'Check box [gv08k9v3p]') {
              console.log(`77778 value_col_index=[${value_col_index}] c=[${c}] row[0]=[${rows[0][parseInt(value_col_index)]}] test[${test}]`);
              console.log(`77778`, rows[0]);
            }
            // rows[parseInt(c)+1][value_col_index]=content[c].value;
          }
          /* test code */

          /* test code */

          // console.log(i_count)

        }
        rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
      }
      //    console.log(rows);
      /*  let r ;
        if (c.type!="checkbox"){
          const id_index= ids.findIndex(x=>x==c.id_);
          if(id_index==-1){
            heads.push(c.name)
            ids.push(c.id_)
            row[(ids.length-1)]=c.value;
          }else{
            row[id_index]=c.value;
          }
          console.log(heads,ids,row)
        }else{
          // it's a checkbox
  
        } */

      /*     if (c.type!="checkbox"){
            r =  {[c.name]:c.value,type:c.type};
          }else{
            console.log(`c.type!="checkbox"`,countMultiNo,length , "chck")
            if(countMultiNo.length==0){
             countMultiNo.push(c.name);
             NoMulti.push(1)
             r =  {[c.name]:c.value,type:c.type,no:1};
            }else{
              const nn = countMultiNo.findIndex(x=> x==c.name)
              console.log(nn , "chck")
               if (nn !=-1)  {
                 NoMulti[nn] += NoMulti[nn]
                 r =  {[c.name]:c.value,type:c.type,no: NoMulti[nn]};
               }else{
                 countMultiNo.push(c.name);
                 NoMulti.push(1)
                 r =  {[c.name]:c.value,type:c.type,no: 1};
               }
            }
            
          } */
      //console.log(r , "chck")
      /*   rows.push(r)
        head.length==undefined ||head.length==0  ||( head.findIndex(x => x== c.name) ==-1 && c.name.length>0)?head = Object.assign(head, {[c.name]:c.name}) :0; */
      // console.log(rows);
    }
    console.log(rows, "rslt")
    //  exp.push(rows);
  }
  const col_index = rows[0].findIndex(x => x == 'null@EFB');
  console.log(efb_var.text.noComment);
  const exp = Array.from(Array(value.length), () => Array(col_index).fill(efb_var.text.noComment));
  for (e in exp) {
    for (let i = 0; i < col_index; i++) {
      if (rows[e][i] != "null@EFB") exp[e][i] = rows[e][i];
    }
  }
  console.log(exp);
  // console.log(exp);
  localStorage.setItem('rows_ws_p', JSON.stringify(exp));
  //  localStorage.setItem('head_ws_p', JSON.stringify(head)); 
}



function exportCSVFile_emsFormBuilder(items, fileTitle) {
  //source code :https://codepen.io/danny_pule/pen/WRgqNx

  /*  if (headers) {
       items.unshift(headers);
   } */
  // Convert Object to JSON
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
  /* console.log(head);
  console.log(exp); */
  const filename = `EasyFormBuilder-${form_type_emsFormBuilder}-export-${Math.random().toString(36).substr(2, 3)}`
  //040820

  exportCSVFile_emsFormBuilder(exp, filename); // create csv file
  //convert_to_dataset_emsFormBuilder(); //create dataset for chart :D
}


function convert_to_dataset_emsFormBuilder() {

  const head = JSON.parse(localStorage.getItem("head_ws_p"));
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  let rows = exp;
  /*  let titles =[];
   let rowNo =0;
   let yy =0
  // for(let h in head){
   for(let h in Exp[0]){
     titles.push(h);
     rowNo +=1;
   }
   for(let value in exp){
     yy+=1;
   }
   let rows = Array.from(Array(yy+1), () => Array(rowNo).fill('null@EFB'));
   //console.log(rows);
   rowNo =0;
   console.log(exp);
     for(let i in titles){
     rows[0][parseInt(i)] =titles[i] 
     }
  
   for(let ex of exp){
     rowNo +=1;
       for(let ee of ex){
         //ex multi rows
           for(let n of ee ){
             //e = {Name: "hassan", type: "text"}
             console.log(ee, n,"life");
           
           }
           if(ee.type !="checkbox"){
 
           }else{
           // if (e==0 && ex[e]!= undefined ) 
           }
        
       }
     for(let i in titles){
         //console.log(rows[0][parseInt(i)] ,titles[i]  )
       if(rowNo==1) rows[0][parseInt(i)] =titles[i] 
 
        ex[titles[i]] != undefined ? rows[rowNo][parseInt(i)] =ex[titles[i]] : '';
      //  ex[titles[i]] != undefined ? rows[rowNo][parseInt(i)] =ex[titles[i]] : '';
     }
   }
   console.log(rows); */
  let countEnrty = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let entry = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let titleTable = []; // list name of tables and thier titles
  for (col in rows) {
    if (col != 0) {
      for (let c in rows[col]) {
        if (rows[col][c] != 'null@EFB') {
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
      //console.log(rows[col]);
      for (let c of rows[col]) {
        // console.log(c);
        titleTable.push(c);
      }
    }
  }
  /* console.log(titleTable);
  console.log(entry);
  console.log(countEnrty );  */
  emsFormBuilder_chart(titleTable, entry, countEnrty);
}


function unlimted_show_panel_emsFormBuilder(m) {

  document.getElementById('body_emsFormBuilder').innerHTML += unlimted_version_emsFormBuilder(m, 0);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function emsFormBuilder_chart(titles, colname, colvalue) {
  //window.scrollTo({ top: 0, behavior: 'smooth' });
  /* console.log(titles);
  console.log(colname); */
  let publicidofchart
  let chartview = "<!-- charts -->";
  let chartId = [];
  let publicRows = [];
  let options = {};
  document.getElementById('wpwrap').innerHTML += `
  <div class=" overpage-chart preview-overpage ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark" >
    <br>
    
    <div id="overpage-chart">
    
    <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
     <h3 class="text-white">${efb_var.text.pleaseWaiting}<h3>
    </div>
    <br>
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">${efb_var.text.close}</button>
  </div>
  <div>
</div></div></div>`;
  window.scrollTo({ top: 0, behavior: 'smooth' });

  /* drawPieChart */
  /* drawPieChart = () =>{
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Element');
    data.addColumn('number', 'integer'); 
    //console.log(publicRows,publicidofchart)
    data.addRows(publicRows);
    
    // Instantiate and draw the chart.
    var chart = new google.visualization.PieChart(document.getElementById(publicidofchart));
    chart.draw(data, options);
  } */
  /* drawPieChart */

  /* Add div of charts */
  for (let t in titles) {
    chartId.push(Math.random().toString(36).substring(8));

    chartview += ` </br> <div id="${chartId[t]}"/>
  <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
  <h3 class="text-white">${efb_var.text.pleaseWaiting}<h3>
  </div>`
  }
  /*End Add div of charts */

  document.getElementById('overpage-chart').innerHTML = chartview

  /* convert to dataset */
  let drawPieChartArr = [];
  let rowsOfCharts = [];
  let opetionsOfCharts = [];
  for (let t in titles) {
    // console.log()
    opetionsOfCharts[t] = {
      'title': titles[t],
      'height': 300
    };
    const countCol = colname[t].length;
    const rows = Array.from(Array(countCol), () => Array(2).fill(0));
    for (let r in colname[t]) {
      rows[r][0] = colname[t][r];
      rows[r][1] = colvalue[t][r];
    }//end for 2
    rowsOfCharts[t] = rows;
    //console.log(publicRows);
    google.charts.load('current', { packages: ['corechart'] });
    publicidofchart = chartId[t];

    /* drawPieChart */
    drawPieChartArr[t] = () => {
      var data = new google.visualization.DataTable();
      data.addColumn('string', 'Element');
      data.addColumn('number', 'integer');
      //console.log(publicRows,publicidofchart)
      //console.log(rowsOfCharts[t])
      data.addRows(rowsOfCharts[t]);

      // Instantiate and draw the chart.
      var chart = new google.visualization.PieChart(document.getElementById(chartId[t]));
      chart.draw(data, opetionsOfCharts[t]);
    }
    /* drawPieChart */

    try {
      // console.log(titles[t]);
      google.charts.setOnLoadCallback(drawPieChartArr[t]);
    } catch (error) {
      // console.log('error');
    }

  }// end for 1
  /*end convert to dataset */




}//end function

function googleCloudOffer() {

  return `<p>${efb_var.text.offerGoogleCloud} <a href="#" target="blank">${efb_var.text.getOfferTextlink}</a> </p> `
}


function copyCodeEfb(id) {
  /* Get the text field */
  var copyText = document.getElementById(id);
  //  var copyText = document.getElementById("myInput");

  /* Select the text field */
  copyText.select();
  copyText.setSelectionRange(0, 99999); /* For mobile devices */

  /* Copy the text inside the text field */
  document.execCommand("copy");

  /* Alert the copied text */
  noti_message_efb(efb_var.text.copiedClipboard, '', 3.7,'info')
}
function clickToCheckEmailServer() {
  document.getElementById('clickToCheckEmailServer').classList.add('disabled')
  const nnrhtml = document.getElementById('clickToCheckEmailServer').innerHTML;
  document.getElementById('clickToCheckEmailServer').innerHTML = `<i class="bi bi-hourglass-split"></i>`
  // call and waitning response

  jQuery(function ($) {
    //console.l('clear_garbeg_emsFormBuilder');  
    data = {
      action: "check_email_server_efb",
      nonce: ajax_object_efm_core.nonce,
      value:'testMailServer'
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {
        noti_message_efb(efb_var.text.done,efb_var.text.serverEmailAble, 3.7);
      } else {
 
        noti_message_efb(efb_var.text.error, efb_var.text.sMTPNotWork, 7 ,'danger');

      }
      document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
     document.getElementById('clickToCheckEmailServer').classList.remove('disabled') 
    })
  });

}




window.onload = (() => {
  // remove all notifications from other plugins or wordpress
  jQuery(document).ready(function () { jQuery("body").addClass("folded") })
  setTimeout(() => {
    for (const el of document.querySelectorAll(".notice")) {
      el.remove()
      //console.log(document.getElementsByTagName("BODY")[0],'test')
    }
    //folded
  }, 50)
})




