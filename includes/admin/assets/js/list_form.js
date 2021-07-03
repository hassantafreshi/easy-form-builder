
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
  fun_emsFormBuilder_render_view(777); //778899
});

let count_row_emsFormBuilder = 0;


// تابع نمایش فرم اصلی
function fun_emsFormBuilder_render_view(x) {
  //console.log("value of valueJson_ws_form",valueJson_ws_form ,x)
  // if(typeof val==="object") valueJson_ws_form=val;
  // مقدار بصورت آبجکت گرفته شود ودر 
  //valueJson_ws_form 
  // نوشته شود

  let rows = ""
  count_row_emsFormBuilder = x;
  let count = 0;
  fun_backButton(2);
  if (valueJson_ws_form.length > 0) {
    //console.log(valueJson_ws_form);
    for (let i of valueJson_ws_form) {

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
        rows += `<tr class="" id="emsFormBuilder-tr-${i.form_id}" >                    
       <th scope="row" class="emsFormBuilder-tr" data-id="${i.form_id}">${Number(i.form_id)}</th>
       <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_name}</td>
       <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_create_date}</td>
       <td > 
       <button type="button" class="btn btn-danger" onClick ="emsFormBuilder_delete(${i.form_id})">X</button>
       <button type="button" class="btn btn-secondary" onClick="emsFormBuilder_get_edit_form(${i.form_id})"  data-id="${i.form_id}"><i class="fa fa-pencil" aria-hidden="true"></i></button>
       <button type="button" class="btn btn-info" onClick="emsFormBuilder_messages(${i.form_id})"><i class=" ${newM == true ? ' fas fa-comment faa-bounce animated ' : 'fa fa-comment-o'}" placeholder="preview"></i></button>
       </td>                               
       </tr>` ;
        count += 1;
      }
    }

    if (valueJson_ws_form.length < x) {
      document.getElementById("more_emsFormBuilder").style.display = "none";
    }


    document.getElementById('emsFormBuilder-content').innerHTML = `<div class="col-md-12  d-flex mat-shadow">
   <table class="table table-hover justify-content-center" id="emsFormBuilder-list">
        <thead>
            <tr >
            <th scope="col">#</th>
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
    document.getElementById('emsFormBuilder-content').innerHTML = `<div class="col-md-12  d-flex mat-shadow">
  <div class="m-5 p-1 col text-center"> <a type="button" class="btn btn-info" href="admin.php?page=Emsfb_create" ><i class="fa fa-plus" placeholder="preview"></i> Create Form</a> </div>
  </div>`
  }



  for (const el of document.querySelectorAll(`.emsFormBuilder-tr`)) {

    el.addEventListener("click", (e) => {

      emsFormBuilder_messages(el.dataset.id)



    });
  }
}


function emsFormBuilder_waiting_response() {
  document.getElementById('emsFormBuilder-list').innerHTML = `<div class=" d-flex justify-content-center align-items-center mt-3" id="emsFormBuilder_waiting_response"><h1 class="fas fa-sync fa-spin text-primary emsFormBuilder "></h1></div>`
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
  //console.l(`show message do you want delete ? ${id}`);
  // پنجره مطمئن هستی می خوای فرم پاک کنی نمایش بده 
  //areYouSureYouWantDeleteItem
  document.getElementById('wpwrap').innerHTML += `
  <div class=" overpage preview-overpage ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
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
</div></div></div>`;
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
  const formType = form_type_emsFormBuilder;
  //console.log(form_type_emsFormBuilder)
  // پنجره نمایش فرم ثبت شده کاربر
  //console.l(`show message`,id ,valueJson_ws_messages);
  const indx = valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const msg_id = valueJson_ws_messages[indx].msg_id;
  //console.l(valueJson_ws_messages[indx],msg_id);

  const userIp = valueJson_ws_messages[indx].ip;
  const track = valueJson_ws_messages[indx].track;
  const date = valueJson_ws_messages[indx].date;

  const content = JSON.parse(valueJson_ws_messages[indx].content.replace(/[\\]/g, ''));

  let by = valueJson_ws_messages[indx].read_by !== null ? valueJson_ws_messages[indx].read_by : "Unkown"
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  const m = fun_emsFormBuilder_show_messages(content, by, userIp, track, date)
  //reply  message ui

 
  form_type_emsFormBuilder = formType;
  console.log(form_type_emsFormBuilder)
  let titleBox = `<i class="fa fa-info-circle"></i> ${efb_var.text.info}`;
  const replayM = function () {
    let r
    if (form_type_emsFormBuilder != 'subscribe' && form_type_emsFormBuilder != 'register' && form_type_emsFormBuilder != 'survey') {
      titleBox = `<i class="fa fa-comments"></i> ${efb_var.text.message}`;
      r = `<div class="mx-2 mt-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"><div class="form-group mb-1" id="replay_section__emsFormBuilder">
     <label for="replayM_emsFormBuilder">${efb_var.text.reply}:</label>
     <textarea class="form-control" id="replayM_emsFormBuilder" rows="3" data-id="${msg_id}"></textarea>
     </div>
     <div class="col text-right row">
     <button type="submit" class="btn btn-info" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">${efb_var.text.reply} </button>
     <p class="mx-2" id="replay_state__emsFormBuilder">  </p>
     </div></div>`;
    } else {
      r = '<!-- comment --!>';
    }

    return r;
  }
  //210407-TD74K

  document.getElementById('wpwrap').innerHTML += `
  <div class=" overpage preview-overpage ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
  <div class="overpage-mbox bg-light">
  <div class="card-body m-13">
    <div class="card-title bg-secondary px-2 py-2 text-white m-0 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"> ${titleBox}</div>
   
    <div class="my-2">
    <div class="my-1 mx-1 border border-secondary rounded-sm pb-3">
    
     <div class="mx-4 my-1 border-bottom border-info pb-1" id="conver_emsFormBuilder">
     
      <div id="loading_message_emsFormBuilder" class="efb-color text-center"><i class="fas fa-spinner fa-pulse"></i> ${efb_var.text.loading}</div>
     </br>
      ${m} 
     </div>
     ${replayM()}
     </div>
      
     </div>
   
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">${efb_var.text.close}</button>
  </div>
  <div>
</div></div></div>`;

  fun_add_event_CloseMenu();

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
  //console.l(id, typeof id);


  // ای دی از جی سون پیدا شود حذف شود و به سمت سرور پیام حذف ارسال شود
  // صفحه رفرش شود
  fun_delete_form_with_id_by_server(parseInt(id));

  //کد زیر حذف نشود
  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => x.form_id == id) : -1
  if (foundIndex != -1) valueJson_ws_form.splice(foundIndex, 1);
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  close_overpage_emsFormBuilder();
  //پایان عدم حذف 


}


// دکمه بازگشت و نمایش لیست اصلی
function fun_emsFormBuilder_back() {
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);


}


function fun_emsFormBuilder_show_messages(content, by, userIp, track, date) {

  //console.l(`by[${by}]userIp[${userIp}] , track[${track}]`)
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = efb_var.text.guest)
  let m = `<Div class="border border-light round  p-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"><div class="border-bottom mb-1 pb-1">
   <span class="small"><b>${efb_var.text.info}</b></span></br>
   <span class="small">${efb_var.text.by}: ${by}</span></br>
   <span class="small">${efb_var.text.ip}: ${userIp}</span></br>
  ${track != 0 ? `<span> ${efb_var.text.trackNo}: ${track} </span></br>` : ''}
  <span> ${efb_var.text.date}: ${date} </span></small>
  </div>
  <div class="mx-1">
  <h6 class="my-3">${efb_var.text.response} </h6>`;
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
  m += '</div></div>';
  //console.l(`m`,m)
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
  trackingcode = 'null';
  document.getElementById('emsFormBuilder-content').innerHTML = `<div class="col-md-12 ">
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
          <div class="form-group mx-3">
          </br>
             <input type="checkbox" class="form-check-input" id="trackingcode_emsFormBuilder" ${formName_ws != "login" && formName_ws != "register" ? `id="trackingcode_emsFormBuilder" ` : `disabled`}>
             <label class="form-check-label" for="trackingcode_emsFormBuilder">${efb_var.text.showTrackingCode}</label>       
           </div>
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
  fun_render_view_core_emsFormBuilder();

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
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i>${efb_var.text.error}${efb_var.text.youCantUseHTMLTagOrBlank}</h6>`;
    return
  }
  fun_send_replayMessage_ajax_emsFormBuilder(ob, id)


}


function fun_ws_show_list_messages(value) {
  // show list of filled out of the form;
  //console.log(form_type_emsFormBuilder)
  let rows = '';
  let no = 1;
  let head = `<!-- rows -->`;
  let iconRead = 'fa-envelope-open-o';
  let iconNotRead = 'fa-envelope faa-bounce animated';
  if (form_type_emsFormBuilder == 'subscribe') {
    head = `<div ><button  class="mx-3 my-2 py-2 px-3 btn btn-warning mat-shadow"  onClick="generat_csv_emsFormBuilder()" title="${efb_var.text.downloadCSVFileSub}" > <h4> <i class="fa fa-download px-1""></i>${efb_var.text.downloadCSVFile}</h4></button ></div>`;
    iconRead = 'fa-user-o';
    iconNotRead = 'fa-user ';
  } else if (form_type_emsFormBuilder == 'register') {
    iconRead = 'fa-user-o';
    iconNotRead = 'fa-user ';
  } else if (form_type_emsFormBuilder == 'survey') {
    //console.log(efb_var.text.availableInProversion)
    const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `unlimted_show_panel_emsFormBuilder('${efb_var.text.availableInProversion}')`;
    head = `<div >
  <button  class="mx-3 my-2 py-2 px-3 btn btn-warning mat-shadow"  onClick="${fun}" title="${efb_var.text.downloadCSVFileSub}" > <h4> <i class="fa fa-download"></i>${efb_var.text.downloadCSVFile}</h4></button >
  <button  class="mx-1 my-2 py-2 px-3 btn btn-warning mat-shadow"  onClick="convert_to_dataset_emsFormBuilder()" title="${efb_var.text.chart}" > <h4> <i class="fa fa-bar-chart px-1"></i>${efb_var.text.chart}</h4></button >
  </div>`;
    iconRead = 'fa-user-o';
    iconNotRead = 'fa-user ';
  }
  /// console.log(value);
  for (const v of value) {
    const state = Number(v.read_);
    rows += `<tr class="emsFormBuilder-tr" id="" onClick="fun_open_message_emsFormBuilder(${v.msg_id} , ${state})">                    
   <th scope="row" class="">${no}</th>
   <td class="" >${v.track}</td>
   <td class="">${v.date}</td>
      <td> 
      <button type="button" class="btn btn-info" ><i id="icon-${v.msg_id}"class="fa ${Number(v.read_) == 0 ? iconNotRead : iconRead} " aria-hidden="true"></i></button>
      </td>                               
      </tr>` ;
    no += 1;
  }





  document.getElementById('emsFormBuilder-content').innerHTML = `${head}<div class="col-md-12  d-flex mat-shadow">
  <table class="table table-hover justify-content-center" id="emsFormBuilder-list">
  <thead>
  <tr>
  <th scope="col">#</th>
  <th scope="col">${efb_var.text.trackNo}</th>
  <th scope="col">${efb_var.text.formDate}</th>
  <th scope="col">${efb_var.text.content}</th>
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

      } else {
        //console.l(res);
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
        localStorage.setItem('valueJson_ws_p', JSON.stringify(value));
        const edit = { id: res.data.id, edit: true };
        localStorage.setItem('Edit_ws_form', JSON.stringify(edit))
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

      } else {
        //console.l(res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {

  //console.l('592',message,`by[${by}]userIp[${userIp}]track[${track}]date[${date}]`);
  document.getElementById('conver_emsFormBuilder').innerHTML += fun_emsFormBuilder_show_messages(message, by, userIp, track, date);
}


function fun_ws_show_response(value) {
  //console.l("598",value)
  for (v of value) {
    //console.log(v.content);
    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'Message', value: 'message not exists' }
    fun_emsFormBuilder__add_a_response_to_messages(content, v.rsp_by, v.ip, 0, v.date);
  }
  document.getElementById('loading_message_emsFormBuilder').remove();
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
    document.getElementById('emsFormBuilder-content').innerHTML = `<h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i>${efb_var.text.loading}</h2>`
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
    2: { title: efb_var.text.howConfigureEFB,       url: 'https://www.youtube.com/embed/DEQNHMPT0rQ' },
    3: { title: efb_var.text.howGetGooglereCAPTCHA, url: 'https://www.youtube.com/embed/a1jbMqunzkQ' },
    4: { title: efb_var.text.howActivateAlertEmail, url: 'https://www.youtube.com/embed/So2RAzu-OHU' },
    5: { title: efb_var.text.howCreateAddForm,      url: 'https://www.youtube.com/embed/7jS01CEtbDg' },
    6: { title: efb_var.text.howActivateTracking,   url: 'https://youtu.be/KGdHoIZsP_U'},
    7: { title: efb_var.text.howWorkWithPanels,     url: 'https://www.youtube.com/embed/7jS01CEtbDg' },
    8: { title: efb_var.text.howAddTrackingForm,    url: 'https://www.youtube.com/embed/c1_gCFihrH8' },
    9: { title: efb_var.text.howFindResponse,       url: 'https://www.youtube.com/embed/vqKi9BJbO7k' },
  }


  let str = "";
  for (const l in listOfHow_emsfb) {
    //console.l(l);
    str += `
  <div class="m-1">
  <div class=" bg-info " >
  <!-- <button id="heading${l}" class=" btn-block card-header btn bg-info text-white" data-toggle="collapse" data-target="#collapse${l}" aria-expanded="true" aria-controls="collapseOne"> --!>
  <a id="heading${l}" class=" btn-block card-header btn bg-info text-white" target="_blank" href="${listOfHow_emsfb[l].url}">
    <h6 class="mb-0 ">
      ${listOfHow_emsfb[l].title}
      </h6>
      </a>
  </div>
  </div>
<!--  <div id="collapse${l}" class="collapse ${l == 0 ? ' show' : ''}" aria-labelledby="heading${l}" data-parent="#accordion">
    <div class="card-body align-self-center">
      <iframe width="560" height="315" src="${listOfHow_emsfb[l].url}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  </div>
  </div> --!>
  `
  }
  document.getElementById('emsFormBuilder-content').innerHTML = `<div id="accordion" class="m-5">${str}
  </div>`;

}
function fun_show_setting__emsFormBuilder() {
  // console.log( 610,ajax_object_efm.setting);
  let activeCode = 'null';
  let sitekey = 'null';
  let secretkey = 'null';
  let email = 'null';
  let trackingcode = 'null';
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
  }


  // console.log(`lengi of valueJson_ws_setting [${valueJson_ws_setting.length}]` ,valueJson_ws_setting);
  //console.l(`activeCode[${activeCode}] sitekey[${sitekey}] secretkey[${secretkey}] email[${email}] trackingcode[${trackingcode}]`);

  document.getElementById('emsFormBuilder-content').innerHTML = `  <div id="setting_emsFormBuilder" class="mx-auto border border-primary ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
 
    <div class="py-2 pb-5 bg-light">
      <h6 class="border-bottom border-info mx-3 mt-2 text-info font-weight-bold" aria-describedby="UnlitedVersionHelp">${efb_var.text.proVersion}<h6>
       <small id="UnlitedVersionHelp" class="form-text text-muted mx-3 mb-3"><a target="_blank" href="${proUrl_ws}">${efb_var.text.clickHereGetActivateCode}</a>  </small>
      <div class="form-group mx-5">
        <label for="activeCode_emsFormBuilder"Activate Code</label>
        <input type="text" class="form-control" id="activeCode_emsFormBuilder" placeholder="${efb_var.text.enterActivateCode}" ${activeCode !== "null" ? `value="${activeCode}"` : ""}>
        <small class="text-danger" id="activeCode_emsFormBuilder-message"></small>
      </div>
    </div>
   <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2" aria-describedby="reCAPTCHAHelp">${efb_var.text.reCAPTCHAv2}   <h6>
       <small id="reCAPTCHAHelp" class="form-text text-muted mx-3 mb-3"><a target="_blank" href="https://www.google.com/recaptcha/about/">${efb_var.text.reCAPTCHA} </a>${efb_var.text.protectsYourWebsiteFromFraud}<a target="_blank" href="https://youtu.be/a1jbMqunzkQ">${efb_var.text.clickHereWatchVideoTutorial}</a></small>
      <div class="form-group mx-5">
        <label for="sitekey_emsFormBuilder">${efb_var.text.siteKey}</label>
        <input type="text" class="form-control ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="sitekey_emsFormBuilder" placeholder="${efb_var.text.enterSITEKEY}" ${sitekey !== "null" ? `value="${sitekey}"` : ""}>
        <small class="text-danger" id="sitekey_emsFormBuilder-message"></small>
      </div>
      <div class="form-group  mx-5">
        <label for="secretkey_emsFormBuilder">${efb_var.text.SecreTKey}</label>
        <input type="text" class="form-control ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="secretkey_emsFormBuilder" placeholder="${efb_var.text.EnterSECRETKEY}" ${secretkey !== "null" ? `value="${secretkey}"` : ""}>
        <small class="text-danger" id="secretkey_emsFormBuilder-message"></small>
      </div>
    </div>

    <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2" aria-describedby="AlertEmailHelp">${efb_var.text.alertEmail}<h6>
       <small id="AlertEmailHelp" class="form-text text-muted mx-3 mb-3">${efb_var.text.whenEasyFormBuilderRecivesNewMessage}</small>
      <div class="form-group mx-5" id="email_emsFormBuilder-row">
        <label for="email_emsFormBuilder">${efb_var.text.email}</label>
        <input type="email" class="form-control ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="${efb_var.text.enterAdminEmail}" ${email !== "null" ? `value="${email}"` : ""}>             
      </div>
    </div>
    <div class="py-2">
    <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="filesCelar">${efb_var.text.clearFiles}<h6>
    <small id="filesCelar" class="form-text text-muted mx-3 mb-3">${efb_var.text.youCanRemoveUnnecessaryFileUploaded}</small>
    <div class="form-group mx-5">
    <a  class="btn btn btn-secondary" OnClick="clear_garbeg_emsFormBuilder()">${efb_var.text.clearUnnecessaryFiles}</a>          
    </div>
   
    <div class="py-2 invisible">
      <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="TrackingCodeHelp">${efb_var.text.trackingCode}<h6>
       <small id="TrackingCodeHelp" class="form-text text-muted mx-3 mb-3">${efb_var.text.ifShowTrackingCodeToUser}</small>
      <div class="form-group mx-5">
       <input type="checkbox" class="form-check-input" id="trackingcode_emsFormBuilder" ${trackingcode !== "null" && (trackingcode == "true" || trackingcode === true) ? `checked` : ""}>
      <label class="form-check-label" for="trackingcode_emsFormBuilder">${efb_var.text.showTrackingCode}</label>       
      </div>     
    </div>
    
    <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="shortCodeHelp">${efb_var.text.trackingCodeFinder}<h6>
       <small id="shortCodeHelp" class="form-text text-muted mx-3 mb-3">${efb_var.text.copyAndPasteBelowShortCodeTrackingCodeFinder}<a target="_blank" href="https://youtu.be/c1_gCFihrH8">${efb_var.text.clickHereWatchVideoTutorial}</a>   </small>
      <div class="form-group mx-5">
      <input type="text" class="form-control" id="shortCode_emsFormBuilder" value="[EMS_Form_Builder_tracking_finder]" readonly>          
      </div>
    </div>

  </div>


<div class="m-2 row">
 <a type="submit" class="btn btn-primary" onClick="fun_set_setting_emsFormBuilder()" id="btn_set_setting_emsFormBuilder"><i class="px-1 fa fa-floppy-o" aria-hidden="true"></i>${efb_var.text.save}</a>
 <div id="loading_message_emsFormBuilder" class="efb-color text-center mx-2 invisible"><i class="fas fa-spinner fa-pulse"></i>${efb_var.text.waiting}...</div>
 </div>

</div>`
}


function fun_set_setting_emsFormBuilder() {
  //console.l("fun_set_setting_emsFormBuilder");
  fun_state_loading_message_emsFormBuilder(1);
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
    console.log(el);
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
  const ids = ['activeCode_emsFormBuilder', 'sitekey_emsFormBuilder', 'secretkey_emsFormBuilder', 'email_emsFormBuilder', 'trackingcode_emsFormBuilder'];
  let state = true
  for (id of ids) {
    if (v(id) === false) {
      state = false;
      fun_state_loading_message_emsFormBuilder(1);
      fun_State_btn_set_setting_emsFormBuilder();
      break;
    }
  }
  if (state == true) {
    const activeCode = f('activeCode_emsFormBuilder');
    const sitekey = f(`sitekey_emsFormBuilder`);
    const secretkey = f(`secretkey_emsFormBuilder`);
    const email = f(`email_emsFormBuilder`);
    let trackingcode = f(`trackingcode_emsFormBuilder`);
    trackingcode = false; //form v1.3
    fun_send_setting_emsFormBuilder({ activeCode: activeCode, siteKey: sitekey, secretKey: secretkey, emailSupporter: email, trackingCode: `${trackingcode}` });
  }
}

function fun_State_btn_set_setting_emsFormBuilder() {
  if (document.getElementById('btn_set_setting_emsFormBuilder').classList.contains('disabled') == true) {
    document.getElementById('btn_set_setting_emsFormBuilder').classList.remove('disabled');
  } else {
    document.getElementById('btn_set_setting_emsFormBuilder').classList.add('disabled');
  }
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
      if (res.success == true) {
        //console.l(`resp`,res);
        valueJson_ws_setting = data.message;
        //console.log(` first lengi of valueJson_ws_setting [${valueJson_ws_setting.length}]` ,valueJson_ws_setting);    
        fun_show_setting__emsFormBuilder();
        if (res.data.success == true) {
          // اگر پاسخ  مست گرفت از سرور

          if (document.getElementById('setting_return_emsFormBuilder') == null) {
            state_seting_emsFormBuilder = true;
            document.getElementById('setting_emsFormBuilder').innerHTML += `<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="px-1 fas fa-thumbs-up faa-bounce animated "></i>${efb_var.text.saved}</div></div>`
          } else {
            state_seting_emsFormBuilder = true;
            document.getElementById('setting_return_emsFormBuilder').innerHTML = `<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="px-1 fas fa-thumbs-up faa-bounce animated "></i>${efb_var.text.saved}</div>`
          }
        } else {
          //console.l(res.data);
          if (document.getElementById('setting_return_emsFormBuilder') == null) {
            document.getElementById('setting_emsFormBuilder').innerHTML += `<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-danger text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> ${efb_var.text.error} ${res.data.m}</div></div>`
          } else {
            document.getElementById('setting_return_emsFormBuilder').innerHTML = `<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> ${efb_var.text.error} ${res.data.m}</div>`
          }
        }
      } else {
        //console.l(res);
        if (document.getElementById('setting_return_emsFormBuilder') == null) {
          document.getElementById('setting_emsFormBuilder').innerHTML += `<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-danger text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> ${res}</div></div>`
        } else {
          document.getElementById('setting_return_emsFormBuilder').innerHTML = `<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i>  ${res}</div>`
        }
      }
    })
  });
}


function fun_find_track_emsFormBuilder() {
  //function find track code
  const el = document.getElementById("track_code_emsFormBuilder").value;
  //console.l(el);
  if (el.length != 12) {
    emsFormBuilder_popUp_message("Error", "Tracking Code is not valid.");

  } else {
    //console.l('fun_find_track_emsFormBuilder',el  )
    document.getElementById('track_code_emsFormBuilder').disabled = true;
    document.getElementById('track_code_btn_emsFormBuilder').disabled = true;
    const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
    document.getElementById('track_code_btn_emsFormBuilder').innerHTML = `<i class="fas fa-spinner fa-pulse"></i>`;
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
          emsFormBuilder_popUp_message("Erorr", res.data.m);
          document.getElementById('track_code_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
          document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue

        }
      })
    });
  }


}//end function 


function clear_garbeg_emsFormBuilder() {
  emsFormBuilder_popUp_loading()
  jQuery(function ($) {
    //console.l('clear_garbeg_emsFormBuilder');  
    data = {
      action: "clear_garbeg_Emsfb",
      nonce: ajax_object_efm_core.nonce
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {
      close_overpage_emsFormBuilder(1)
      if (res.data.success == true) {
        emsFormBuilder_popUp_message("Done", res.data.m);
      } else {
        emsFormBuilder_popUp_message("Error", res.data.m);

      }
    })
  });

}


function fun_export_rows_for_Subscribe_emsFormBuilder(value) {
  //json ready for download 
  //778899
  // let exp =[];
  let head = {};
  let heads = [];
  let ids = [];
  let count = -1;

  //console.log(value);
  let rows = Array.from(Array(value.length + 1), () => Array(100).fill('null@EFB'));
  //console.log(`rows[${rows.length}]`);
  rows[0][0] = value.length;
  let i_count = -1;
  for (v of value) {
    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'not found', value: 'not found' }
    //console.log(content.length);
    // const rows = Array.from(Array(content.length+1), () => Array(100).fill('null@EFB'));

    count += 1;
    i_count += i_count == -1 ? +2 : 1;
/*     console.log(content, "CheckValue");
    console.log(`i_count [${i_count}]`, "CheckValue"); */
    let countMultiNo = [];
    let NoMulti = [];
/*     console.log(`i_count [${i_count}]`);
    console.log(rows); */
    // let rows ={};
    //  console.log(content.length);

    //  const row = new Array(1000).fill('null@EFB');
    for (c in content) {
      // console.log(content[c],"chck");
      // rows = Object.assign(rows, {[c.name]:c.value});
      let value_col_index;
      if (content[c].type != "checkbox") {

        if (rows[i_count][0] == "null@EFB") rows[i_count][0] = i_count;

        value_col_index = rows[0].findIndex(x => x == content[c].name);

        if (value_col_index == -1) {

          value_col_index = rows[0].findIndex(x => x == 'null@EFB');
          rows[0][parseInt(value_col_index)] = content[c].name;

          /* console.log(content[c].name, content[c], c, rows[0][parseInt(value_col_index)]);
          console.log(`rows[parseInt(${i_count})][parseInt(${value_col_index})]`, `rows[0][parseInt(${value_col_index})]`);
          console.log(`row[${[parseInt(i_count)]}][${[parseInt(value_col_index)]}] [${content[c].value}],"NCheck","CheckValue"`);
          //  rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          console.log(`row cel [${rows[parseInt(i_count)][parseInt(value_col_index)]}]`, rows[parseInt(i_count)], content[c].value, "NCheck", "CheckValue"); */
        } 

        rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;

      } else {
        // if checkbox
        if (rows[i_count][0] == "null@EFB") rows[i_count][0] = i_count;
        //new code test
        const name = content[c].name;
        value_col_index = rows[0].findIndex(x => x == name);
        if (value_col_index != -1) {
          //if checkbox title is exists

          if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
            rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          } else {
            const r = rows.length
            const row = Array.from(Array(1), () => Array(100).fill('null@EFB'))
            rows = rows.concat(row);
            rows[parseInt(r)][parseInt(value_col_index)] = content[c].value;
            rows[parseInt(r)][0] = rows.length - 1;
            console.log(rows.length, i_count + 1, 'lenlen', rows[parseInt(r)][parseInt(value_col_index)])
          }
        } else {
          //if checkbox title is Nexists
          value_col_index = rows[0].findIndex(x => x == 'null@EFB');
          rows[0][parseInt(value_col_index)] = name;

        }

        //new code test
  
      }//end else
      
    }
    console.log(rows, "rslt")
    console.log(`i_count [${i_count}]`);
    //  exp.push(rows);
  }
  const col_index = rows[0].findIndex(x => x == 'null@EFB');
  console.log(rows.length);
  const exp = Array.from(Array(rows.length), () => Array(col_index).fill(efb_var.text.noComment));
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
    if (t != 0) {
      chartview += ` </br> <div id="${chartId[t]}"/ class="${t == 0 ? `invisible` : ``}">
  <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
  <h3 class="text-white">${efb_var.text.pleaseWaiting}<h3>
  </div>`

    } else {
      chartview += ` </br> <div id="${chartId[t]}"/ class="${t == 0 ? `invisible` : ``}">
  </div>`
    }



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







