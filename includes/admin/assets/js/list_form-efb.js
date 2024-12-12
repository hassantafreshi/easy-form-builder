


let valueJson_ws_form = [];
let valueJson_ws_messages = [];
let valueJson_ws_setting = []
let state_seting_emsFormBuilder = false;
let poster_emsFormBuilder = '';
let response_state_efb;
let sms_config_efb ='null'

const colors_efb = ['#0013CB', '#E90056', '#7CEF00', '#FFBA00', '#FF3888', '#526AFF', '#FFC738', '#A6FF38', '#303563', '#7D324E', '#5D8234', '#8F783A', '#FB5D9D', '#FFA938', '#45B2FF', '#A6FF38', '#0011B4', '#8300AD', '#E9FB00', '#FFBA00']

jQuery(function () {
  // ajax_object_efm =deepFreeze_efb(ajax_object_efm);
  valueJson_ws_form = ajax_object_efm.ajax_value;
  poster_emsFormBuilder = ajax_object_efm.poster
  response_state_efb = ajax_object_efm.response_state;
  pro_ws = ajax_object_efm.pro == '1' ? true : false;
  page_state_efb="panel";
  if (ajax_object_efm.setting, ajax_object_efm.setting.length > 0) {
    valueJson_ws_setting = (JSON.parse(ajax_object_efm.setting[0].setting.replace(/[\\]/g, '')));
    if (valueJson_ws_setting.bootstrap == 0 && ajax_object_efm.bootstrap == 1) {
      if (localStorage.getItem('bootstrap_w') === null) localStorage.setItem('bootstrap_w', 0)
      if (localStorage.getItem('bootstrap_w') >= 0 && localStorage.getItem('bootstrap_w') < 3) {
        localStorage.setItem('bootstrap_w', (parseInt(localStorage.getItem('bootstrap_w')) + 1))
        //setTimeout(() => {  alert_message_efb(efb_var.text.warningBootStrap, ``, 30, 'danger') }, 500);
      }
    }
  }
  let g =new URLSearchParams(location.search)
  //console.log("get'state'")
  const state = g.get('state') !=null ? sanitize_text_efb(g.get('state')) : null;
 if(state==null){
   fun_emsFormBuilder_render_view(25); //778899
   history.replaceState("panel",null,'?page=Emsfb'); 
 }else{

  
  fun_show_content_page_emsFormBuilder(state)
 }
});

let count_row_emsFormBuilder = 0;



function fun_emsFormBuilder_render_view(x) {
  // v2

  if(!document.getElementById('alert_efb')){
    const currentUrl = window.location.href;
    const txt = fun_create_content_nloading_efb();
    const txtWithoutHTML = txt.replace(/<[^>]+>/g, '');
    alert(txtWithoutHTML)
    report_problem_efb('AdminPagesNotLoaded' ,currentUrl);
    return;
  }
  
  
  let rows = ""
  let o_rows = ""
  count_row_emsFormBuilder = x;
  let count = 0;
  fun_backButton_efb(2);

  function creatRow(i, newM) {
    // v3.8.6 start
    return ` <tr class="efb pointer-efb efb" id="emsFormBuilder-tr-${Number(i.form_id)}" >                    
   <th scope="row" class="efb emsFormBuilder-tr" data-id="${Number(i.form_id)}" >
     [EMS_Form_Builder id=${Number(i.form_id)}]  
   </th>
   <td class="efb emsFormBuilder-tr" data-id="${Number(i.form_id)}">${sanitize_text_efb(i.form_name)}</td>
   <td class="efb emsFormBuilder-tr" data-id="${Number(i.form_id)}">${sanitize_text_efb(i.form_create_date)}</td>
   <td  class="efb" > 
   <button type="button" class="efb zindex-100  btn btn-comment btn-sm ec-efb" data-id="${Number(i.form_id)}" data-eventform="message"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${newM == true ? sanitize_text_efb(efb_var.text.newResponse) : sanitize_text_efb(efb_var.text.read)}">${newM == true ? `<div class="efb nmsgefb"><i class="efb  bi-chat-dots-fill ec-efb" data-id="${Number(i.form_id)}" data-eventform="message"></i></div>` : `<i class="efb  bi-chat text-muted ec-efb" data-id="${Number(i.form_id)}" data-eventform="message"></i>`}</button>
   <button type="button" class="efb zindex-100  btn btn-delete btn-sm ec-efb" data-id="${Number(i.form_id)}" data-eventform="delete" data-formname="${sanitize_text_efb(i.form_name)}"   data-bs-toggle="tooltip" data-bs-placement="bottom" title="${sanitize_text_efb(efb_var.text.delete)}"><i class="efb  bi-trash ec-efb"  data-id="${Number(i.form_id)}" data-eventform="delete" data-formname="${sanitize_text_efb(i.form_name)}"></i></button>
   <button type="button" class="efb zindex-100  btn btn-delete btn-sm bg-info ec-efb" data-id="${Number(i.form_id)}" data-eventform="duplicate" data-formname="${sanitize_text_efb(i.form_name)}"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${sanitize_text_efb(efb_var.text.duplicate)}" id="${Number(i.form_id)}-dup-efb"><i class="efb  bi-clipboard-plus ec-efb" data-id="${Number(i.form_id)}" data-eventform="duplicate" data-formname="${sanitize_text_efb(i.form_name)}"></i> </button>
   <button type="button" class="efb zindex-100 btn-action-edit btn-sm ec-efb" data-id="${Number(i.form_id)}" data-eventform="edit"  data-id="${Number(i.form_id)}"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${sanitize_text_efb(efb_var.text.edit)}"><i class="efb  bi-pencil ec-efb" data-id="${Number(i.form_id)}" data-eventform="edit"></i></button>
   <button type="button" class="efb btn-r d-none efb btn btn-darkb text-white btn-sm bi-clipboard-check ec-efb" data-id="${Number(i.form_id)}" data-eventform="copy"  data-bs-toggle="tooltip" data-bs-placement="bottom" title="${efb_var.text.copy}"> </button>
   <input type="text"  class="efb  d-none" value='[EMS_Form_Builder id=${Number(i.form_id)}]' id="${Number(i.form_id)}-fc">
   </td>                               
  </tr>
  `
  // v3.8.6 end
  }
  if (valueJson_ws_form.length > 0) {
    //valueJson_ws_form sort desc by id 
    
    for (let i of valueJson_ws_form) {
      if (x > count) {
        let newM = false;
        const d = ajax_object_efm.messages_state.findIndex(x => x.form_id == i.form_id)
        if (d != -1) { newM = true; }
        const b = ajax_object_efm.response_state.findIndex(x => x.form_id == i.form_id)
        if (b != -1) { newM = true; }
        //response
        newM != true ? o_rows += creatRow(i, newM) : rows += creatRow(i, newM);
        count += 1;
      }
    }
    rows += o_rows;
    if (valueJson_ws_form.length <= x) {
      const d = document.getElementById("more_emsFormBuilder");
      if(d) d.style.display = "none";
    }


    document.getElementById('content-efb').innerHTML = `
   <h4 class="efb title-holder efb fs-4"> <img src="${efb_var.images.title}" class="efb title efb">
                <i class="efb  bi-archive title-icon  mx-1 fs-4"></i>${efb_var.text.forms}
            </h4>
    <div class="efb card efb">
    <table class="efb table table-striped table-hover mt-3" id="emsFormBuilder-list">
        <thead class="efb">
            <tr class="efb">            
            <th scope="col" class="efb">${efb_var.text.formCode}</th>
            <th scope="col" class="efb">${efb_var.text.formName}</th>
            <th scope="col" class="efb">${efb_var.text.createDate}</th>
            <th scope="col" class="efb">${efb_var.text.advanced}</th>
            </tr>
        </thead>
        <tbody class="efb">${rows}</tbody>
    </table>
 </div>
 ${efb_powered_by()}
 `

  } else {
    fun_backButton_efb(1);
    document.getElementById('content-efb').innerHTML = head_introduce_efb('panel')
    document.getElementById('content-efb').classList.add('m-1');
  }



  for (const el of document.querySelectorAll(`.emsFormBuilder-tr`)) {
    el.addEventListener("click", (e) => { emsFormBuilder_messages(el.dataset.id) });
  }
}

function emsFormBuilder_waiting_response() {
  document.getElementById('emsFormBuilder-list').innerHTML = efbLoadingCard()
}


function emsFormBuilder_get_edit_form(id) {
  //fun_backButton_efb()
  history.pushState("edit-form",null,`?page=Emsfb&state=edit-form&id=${id}`);
  fun_backButton_efb();
  emsFormBuilder_waiting_response();
  fun_get_form_by_id(id);
}





function emsFormBuilder_show_content_message(id) {
  // v2
  const formType = form_type_emsFormBuilder;
  // پنجره نمایش فرم ثبت شده کاربر  
  const indx = valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const objOptions = valueJson_ws_messages.filter(obj => { return obj.msg_id === id.toString() })
  const msg_id = valueJson_ws_messages[indx].msg_id;
  const userIp = valueJson_ws_messages[indx].ip;
  const track = valueJson_ws_messages[indx].track;
  const date = valueJson_ws_messages[indx].date;
  //valueJson_ws_messages[indx].content = ;
  let content = JSON.parse(replaceContentMessageEfb(valueJson_ws_messages[indx].content));
  
  
  //const content = JSON.parse(valueJson_ws_messages[indx].content.replace(/[\\]/g, ''));
  let m = "<--messages-->"
  
  let by = valueJson_ws_messages[indx].read_by !== null ? valueJson_ws_messages[indx].read_by : "Unkown"
  if (by == 1) { by = 'Admin' } else if (by == 0 || by.length == 0 || by.length == -1) (by = '#first')
  
  
  m = fun_emsFormBuilder_show_messages(content, by, userIp, track, date)
  //reply  message ui
  form_type_emsFormBuilder = formType;
  const replayM = function () {
    let r
    if (form_type_emsFormBuilder != 'subscribe' && form_type_emsFormBuilder != 'register' && form_type_emsFormBuilder != 'survey') {
      r = `   
      <div class="efb mb-2 ${efb_var.rtl == 1 ? 'rtl-text' : ''}"  id="replay_section__emsFormBuilder">
        <label for="replayM_emsFormBuilder" class="efb form-label m-2 fs-7" id="label_replyM_efb">${efb_var.text.reply}:</label>
        <textarea class="efb  form-control efb" id="replayM_emsFormBuilder" rows="5" data-id="${msg_id}"></textarea>
      </div>
     <div class="efb col text-right row mx-1">
     <button type="submit" class="efb btn efb btn-primary btn-sm" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})"><i class="efb  bi-reply mx-1"></i> ${efb_var.text.reply} </button>
     <p class="efb mx-2 my-1 text-pinkEfb fs-7" id="replay_state__emsFormBuilder"></p>
     </div></div>`;
    } else { r = '<!-- comment --!>'; }
    return r;
  }

  const body = `
    <div class="efb  modal-body overflow-auto py-0 my-0  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="resp_efb">${m} </div>
     ${replayM()}
     </div></div></div><!-- content body response-->`;




  show_modal_efb(body, efb_var.text.response, 'efb bi-chat-square-text mx-2', 'saveBox');
  setTimeout(() => { reply_attach_efb(msg_id)}, 10);
  state_modal_show_efb(1)
  window.scrollTo({ top: 0, behavior: 'smooth' });

  jQuery('#track_code_emsFormBuilder').on('keypress', 
  function (event) {
      if (event.which == '13') {
          event.preventDefault();
          
          return;
      }
  });

}





function fun_backButton_efb(state) {
   if(!document.getElementById("more_emsFormBuilder"))return;
  if (document.getElementById("more_emsFormBuilder").style.display == "block" && state == 1) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
    (document.getElementById("more_emsFormBuilder").style.display, 255)
  } else {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }


  if (state == 0 || state == null) {
    document.getElementById("more_emsFormBuilder").style.display = "none";
  } else if (state == 2) {
    document.getElementById("more_emsFormBuilder").style.display = "block";
  }
}


function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();
  // if (i==1) previewemsFormBuilder=false;
}



function fun_confirm_remove_emsFormBuilder(id) {
  // Delete the form from the server
  fun_delete_form_with_id_by_server(parseInt(id));

  if (Object.isFrozen(valueJson_ws_form) || Object.isSealed(valueJson_ws_form)) {
    //refresh the page
    location.reload();  
  }

  // Find the index of the element with the given ID in the array
  const foundIndex = valueJson_ws_form.findIndex(x => parseInt(x.form_id) === parseInt(id));

  // If the element is not found, log a warning and exit
  if (foundIndex === -1) {
  
    return;
  }

  // Remove the element from the array
  valueJson_ws_form.splice(foundIndex, 1);
  // console.log("Element removed successfully:", valueJson_ws_form);

  // Re-render the view
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  //close_overpage_emsFormBuilder(); // Uncomment if needed
}


function fun_confirm_remove_message_emsFormBuilder(id) {

 
  fun_delete_message_with_id_by_server(parseInt(id));

 
  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => x.form_id == id) : -1
  if (foundIndex != -1){ 
   // valueJson_ws_form = [...valueJson_ws_form];
    valueJson_ws_form.splice(foundIndex, 1);
   // valueJson_ws_form =deepFreeze_efb(valueJson_ws_form);
  }
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  //close_overpage_emsFormBuilder();

}
function fun_confirm_remove_all_message_emsFormBuilder(val) {
  // console.log(val)
  fun_delete_all_message_by_server(val);
  
   for (const v of val) {
    const foundIndex = Object.keys(valueJson_ws_messages).length > 0 ? valueJson_ws_messages.findIndex(x => x.msg_id == v.msg_id) : -1
    // console.log(foundIndex);
    if (foundIndex != -1) valueJson_ws_messages.splice(foundIndex, 1);
  }
  fun_ws_show_list_messages(valueJson_ws_messages);
  //close_overpage_emsFormBuilder();

}






function fun_emsFormBuilder_back() {
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
}






function fun_emsFormBuilder_more() {
  count_row_emsFormBuilder += 5;
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })

}


function fun_ws_show_edit_form(id) {
  const len = valj_efb.length;
  
  creator_form_builder_Efb();
  setTimeout(() => {
    editFormEfb()
  }, 500)


}


function fun_send_replayMessage_emsFormBuilder(id) {
  document.getElementById('replay_state__emsFormBuilder').innerHTML = `<i class="efb bi-hourglass-split mx-1"></i> ${efb_var.text.sending}`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  
  let message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g, '@efb@nq#');
  message=message ? sanitize_text_efb(message) : null;
  if (message==null) return  valNotFound_efb()

  const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: ajax_object_efm.user_name , session: sessionPub_emsFormBuilder}];
  fun_sendBack_emsFormBuilder(ob[0])
  if (message.length < 1) {
    check_msg_ext_resp_efb();
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<h6 class="efb fs-7"><i class="efb bi-exclamation-triangle-fill nmsgefb"></i>${efb_var.text.error}: ${efb_var.text.pleaseEnterVaildValue}</h6>`;
    return
  }
  
  fun_send_replayMessage_ajax_emsFormBuilder(sendBack_emsFormBuilder_pub, id)


}

// 3.8.6 start
function fun_ws_show_list_messages(value) {

  let rows = '';
  let no = 1;
  let head = `<!-- rows -->`;
  let iconRead = 'bi-envelope-open';
  let iconNotRead = ' <path  d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>';
  const fun = pro_ws == true ? "generat_csv_emsFormBuilder()" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
  const fun1 = pro_ws == true ? "event_selected_row_emsFormBuilder('read')" : `pro_show_efb('${efb_var.text.availableInProversion}')`;
  

 
  if (form_type_emsFormBuilder == 'subscribe') {
    head = `<div class="efb d-flex"><button class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="subscribe" title="${efb_var.text.downloadCSVFileSub}" >  <i class="efb  bi-download mx-2 ec-efb" data-eventform="generateCSV" data-formtype="subscribe"></i>${efb_var.text.downloadCSVFile}</button >
    `;
    iconRead = 'bi-person';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'register') {
   
    head = `<div class="efb d-flex"> <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="register"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2 ec-efb" data-eventform="generateCSV" data-formtype="register"></i>${efb_var.text.downloadCSVFile}</button >
    `;
    iconRead = 'bi-person ';
    iconNotRead = '<path  d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>';
  } else if (form_type_emsFormBuilder == 'survey') {
    
    head = `<div class="efb d-flex">
    <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb"  data-eventform="generateCSV" data-formtype="survey"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2 ec-efb" data-eventform="generateCSV" data-formtype="survey"></i>${efb_var.text.downloadCSVFile}</button >
    <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb"  data-eventform="generateChart" data-formtype="survey"  onClick="convert_to_dataset_emsFormBuilder()" title="${efb_var.text.chart}" >  <i class="efb  bi-bar-chart-line mx-2 ec-efb" data-eventform="generateChart" data-formtype="survey"></i>${efb_var.text.chart}</button >
    `;
    iconRead = 'bi-chat-square-text';
    iconNotRead = ' <path  d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>';
  } else if (form_type_emsFormBuilder == 'form' || form_type_emsFormBuilder == 'payment') {
   
    head = `<div class="efb d-flex"> <button  class="efb  btn efb btn-primary text-white mt-2 mx-1 ec-efb" data-eventform="generateCSV" data-formtype="payment"   title="${efb_var.text.downloadCSVFileSub}" >   <i class="efb  bi-download mx-2 ec-efb" data-eventform="generateCSV" data-formtype="payment"></i>${efb_var.text.downloadCSVFile}</button >
    `;
  }
   head +=`
  <div class="efb" id="selectedBtnlistEfb">
  <button  class="efb  btn efb btn-danger text-white mt-2 ec-efb" data-eventform="deleteSelectedRow"  title="${efb_var.text.delete}" >  <i class="efb  bi-trash mx-2 ec-efb" data-eventform="deleteSelectedRow" data-pro="${pro_ws}"></i></button >
  <button  class="efb  btn efb btn-secondary text-white mt-2 ec-efb" data-eventform="readSelectedRow" title="${efb_var.text.mread}" >  <i class="efb  mx-2 ${iconRead} ec-efb"  data-eventform="readSelectedRow" data-pro="${pro_ws}"></i></button >
  </div>
  </div>
  `
  if (value.length > 0) {
    let no =1;
    for (const v of value) {
      let state = Number(v.read_);
     
      iconNotRead = `<div class="efb nmsgefb bi-envelope-fill"></div>`;
      if(state==2){
         iconRead = 'bi-bag-x';
         iconNotRead = `<div class="efb nmsgefb bi-bag-x"></div>`;
      }
      $txtColor = state == 2 ? 'text-danger' : '';
      if (response_state_efb.findIndex(x => x.msg_id == v.msg_id) != -1) { state = 0 }
      rows += `<tr class="efb  pointer-efb" id="" data-bs-toggle="tooltip" data-bs-placement="bottom" title="${Number(state) == 0 ? efb_var.text.newResponse : efb_var.text.read}"  >                    
        <th scope="col" class="efb"><input class="efb  emsFormBuilder_v form-check-input   fs-8 onemsg" type="checkbox"  value="checkbox"  data-id="${v.msg_id}"  onclick="fun_select_rows_table(this)"></th>
         <td class="efb ${$txtColor} ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" >${no}</td>
         <th scope="row" class="efb ${$txtColor} ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" >${v.track}</th>
           <td class="efb ${$txtColor} ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}" >${v.date}</td>
            <td class="efb "> 
            <a  class="efb  btn btn-comment btn-sm ec-efb" id="btn-m-${v.msg_id}" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}"  >
             ${Number(state) != 1 && Number(state) != 4 ? iconNotRead : `<i id="icon-${v.msg_id}" class="efb  ${iconRead} text-muted ec-efb" data-eventform="openMessage" data-msgid="${v.msg_id}" data-msgstate="${state}"></i> `}</a>
             <a class="efb zindex-100  btn btn-delete btn-sm  ec-efb" id="btn-m-d-${v.msg_id}" data-eventform="deleteMsg" data-msgid="${v.msg_id}" data-trackid="${v.track}" ><i class="efb  bi-trash ec-efb"  data-eventform="deleteMsg" data-msgid="${v.msg_id}" data-trackid="${v.track}"></i> </a>
            </td>                               
            </tr>` ;
      no += 1;
    }
  } else {
    rows = `<tr class="efb  pointer-efb fs-6 efb"><td>${efb_var.text.noResponse}</td><td></td><td></td></tr>`
  }





  document.getElementById('content-efb').innerHTML = `<div class="efb head-efb">${head}</div>
    <h4 class="efb title-holder efb fs-4"> <img src="${efb_var.images.title}" class="efb title efb">
    <i class="efb  bi-archive title-icon  mx-1 fs-4"></i>${efb_var.text.messages}
    </h4>
    <div class="efb card efb">
    <table class="efb table table-striped table-hover mt-3" id="emsFormBuilder-list">
    <thead>
    <th scope="col" class="efb"><input class="efb  emsFormBuilder_v form-check-input fs-8 allmsg" type="checkbox"  value="checkbox"   onclick="fun_select_rows_table(this)"></th>
    <th scope="col" class="efb">${efb_var.text.number}</th>
    <th scope="col" class="efb">${efb_var.text.trackNo}</th>
    <th scope="col" class="efb">${efb_var.text.ddate}</th>
    <th scope="col" class="efb">${efb_var.text.advanced}</th>
    </tr>
    </thead>
    <tbody class="efb">
    ${rows}
    </tbody>
    </table>
    </div>
     ${efb_powered_by()}
    `;
  if (form_type_emsFormBuilder != 'login') fun_export_rows_for_Subscribe_emsFormBuilder(value);

}
// 3.8.6 end



function fun_delete_form_with_id_by_server(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
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
          alert_message_efb(efb_var.text.done, '', 3, 'info')
        }, 3)
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error, '', 3, 'danger')
        }, 3)
      }
    })
  });

}
function fun_delete_message_with_id_by_server(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  jQuery(function ($) {
    data = {
      action: "remove_message_id_Emsfb",
      type: "POST",
      id: id,
      nonce: ajax_object_efm_core.nonce,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        setTimeout(() => {
          alert_message_efb(efb_var.text.done, '', 3, 'info')
        }, 3)
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error, '', 3, 'danger')
        }, 3)
      }
    })
  });

}
function fun_delete_all_message_by_server(val) {
  // console.log('fun_delete_all_message_by_server',val);

  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  jQuery(function ($) {
    data = {
      action: "remove_messages_Emsfb",
      type: "POST",
      val: JSON.stringify(val),
      state: 'msg',
      nonce: ajax_object_efm_core.nonce,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.data.success == true) {
        setTimeout(() => {
          alert_message_efb(efb_var.text.done, '', 3, 'info')
          
        }, 3)
        location.reload();
      } else {
        setTimeout(() => {
          alert_message_efb(efb_var.text.error,res.data.m, 3, 'danger')
        }, 3)
      }
    })
  });

}




function emsFormBuilder_messages(id) {
  const row = ajax_object_efm.ajax_value.find(x => x.form_id == id)
  efb_var.msg_id =id;
  form_type_emsFormBuilder = row.form_type;
  history.pushState("show-message",null,`?page=Emsfb&state=show-messages&id=${id}&form_type=${row.form_type}`);
  fun_get_messages_by_id(Number(id));
  emsFormBuilder_waiting_response();
  fun_backButton_efb(0);
}

function fun_open_message_emsFormBuilder(msg_id, state) {
  // console.log(`fun_open_message_emsFormBuilder(${msg_id}, ${state})`)
  show_modal_efb(efbLoadingCard(), '', '', 'saveBox');
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  //myModal.show_efb();
  state_modal_show_efb(1)
  
  fun_emsFormBuilder_get_all_response_by_id(Number(msg_id));
  emsFormBuilder_show_content_message(msg_id)
  if (state == 0 || state == 3) {
    fun_update_message_state_by_id(msg_id);
  }
}



function fun_get_form_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  sessionStorage.removeItem('valj_efb');
  sessionStorage.removeItem('Edit_ws_form');
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
          const p = calPLenEfb(len) + 1;
          valj_efb = value;
          setTimeout(() => {
            formName_Efb = valj_efb[0].formName;
            form_type_emsFormBuilder=valj_efb[0].type
            form_ID_emsFormBuilder = id;
            sessionStorage.setItem('valj_efb', JSON.stringify(value));
            const edit = { id: res.data.id, edit: true };
            sessionStorage.setItem('Edit_ws_form', JSON.stringify(edit))
            fun_ws_show_edit_form(id);
          }, len * p)
        } catch (error) {

          //reportE
        }
      }
    })
  });
}
function fun_update_message_state_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  jQuery(function ($) {
    data = {
      action: "update_message_state_Emsfb",
      type: "POST",
      nonce: ajax_object_efm_core.nonce,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      if (res.success == true) {
        let iconRead = `<i class="efb  bi-envelope-open text-muted"></iv>`;
        if (form_type_emsFormBuilder == 'subscribe') {
          iconRead = `<i class="efb  bi-person text-muted"></iv>`;
        } else if (form_type_emsFormBuilder == 'register') {
          iconRead = `<i class="efb  bi-person text-muted"></iv>`;
        }
        document.getElementById(`btn-m-${id}`).innerHTML = iconRead;
        if(document.getElementById(`efbCountM`))document.getElementById(`efbCountM`).innerHTML = parseInt(document.getElementById(`efbCountM`).innerHTML) - 1;

        if (res.data.ajax_value != undefined) {
          const value = JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
          sessionStorage.setItem('valueJson_ws_p', JSON.stringify(value));
          const edit = { id: res.data.id, edit: true };
          sessionStorage.setItem('Edit_ws_form', JSON.stringify(edit))
          fun_ws_show_edit_form(id)
        }
      }
    })
  });
}
function fun_get_messages_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  jQuery(function ($) {
    data = {
      action: "get_messages_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      type: "POST",
      form: form_type_emsFormBuilder,
      id: id
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      // console.log(res)
      if (res.success == true) {
        valueJson_ws_messages = res.data.ajax_value;
        efb_var.nonce_msg = res.data.nonce_msg
          
          efb_var.msg_id = res.data.id
          
        
        // localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));
        fun_ws_show_list_messages(valueJson_ws_messages)
      } else {
      }
    })
  });
}
function fun_emsFormBuilder_get_all_response_by_id(id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
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
      
      state_rply_btn_efb(100)
      //codeHere 778899 
      //create and call a funcation for disable and anabled
    })
  });
}



function fun_send_replayMessage_ajax_emsFormBuilder(message, id) {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  
  if (message.length < 1) {
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    //alert_message_efb(fb_var.text.enterYourMessage, 5 , 'warning')
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
        
        if(document.getElementById('replay_state__emsFormBuilder')){

          document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
          // alert_message_efb(res.data.m, 7 , 'info')
          document.getElementById('replayM_emsFormBuilder').innerHTML = "";
          document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
          const date = Date();
          document.getElementById('replayM_emsFormBuilder').value = "";
          fun_emsFormBuilder__add_a_response_to_messages(message, message[0].by, ajax_object_efm.user_ip, 0, date);
          const chatHistory = document.getElementById("resp_efb");
          chatHistory.scrollTop = chatHistory.scrollHeight;
          sendBack_emsFormBuilder_pub=[];
        }else{
          // res.data.m
          alert_message_efb(res.data.m,'', 7 , 'info')
        }
        
        
      } else {
        // alert_message_efb(efb_var.text.error,res.data.m, 7 , 'danger')
        if(document.getElementById('replay_state__emsFormBuilder')){
        document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
        document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply
        }else{
          alert_message_efb(res.data.m,'', 12 , 'danger')
        }
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
  //v2
  
  const resp = fun_emsFormBuilder_show_messages(message, by, userIp, track, date);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}


function fun_ws_show_response(value) {
  for (let v of value) {

    const content = v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : { name: 'Message', value: 'message not exists' }
    fun_emsFormBuilder__add_a_response_to_messages(content, v.rsp_by, v.ip, 0, v.date);
  }
}


function fun_show_content_page_emsFormBuilder(state) {
  // console.log(state);
  if (state == "forms") {
    document.getElementById('content-efb').innerHTML = `<div class="efb card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb -color text-center"><i class="efb fas fa-spinner fa-pulse"></i> ${efb_var.text.loading}</div>`
    history.pushState("setting",null,'?page=Emsfb');
    window.location.reload();
  } else if (state == "setting" || state == "reload-setting") {
    history.pushState("setting",null,'?page=Emsfb&state=setting');
    fun_show_setting__emsFormBuilder();
    fun_backButton_efb(0);
    state = 2
    const s = sanitize_text_efb(getUrlparams_efb.get('save'));
    if(s=='ok') alert_message_efb("", efb_var.text.saved, 7, "info");  
  } else if (state == "help") {
    history.pushState("help",null,'?page=Emsfb&state=help');
    fun_show_help__emsFormBuilder();
    state = 4
  }else if (state=='search'){
    history.pushState("search",null,'?page=Emsfb&state=search');
    document.getElementById("track_code_emsFormBuilder").value =sanitize_text_efb(localStorage.getItem('search_efb'));
    fun_find_track_emsFormBuilder();
  }else if(state=="show-messages"){
    document.getElementById('content-efb').innerHTML = `<div class="efb card-body text-center my-5"><div id="loading_message_emsFormBuilder" class="efb -color text-center"><i class="efb fas fa-spinner fa-pulse"></i> ${efb_var.text.loading}</div>`
    history.pushState("setting",null,'?page=Emsfb');
    window.location.reload();
  }else if(state=="edit-form"){
   const v =sanitize_text_efb(getUrlparams_efb.get('id'));
      
      fun_get_form_by_id(Number(v));
      fun_backButton_efb();
      fun_hande_active_page_emsFormBuilder(1);
  }
  fun_hande_active_page_emsFormBuilder(state);
}

function fun_hande_active_page_emsFormBuilder(no) {
  let count = 0;
  for (const el of document.querySelectorAll(`.nav-link`)) {
    count += 1;
    if (el.classList.contains('active')) el.classList.remove('active');
    if (count == no) el.classList.add('active');
  }
}

function fun_show_help__emsFormBuilder() {
  document.getElementById("more_emsFormBuilder").style.display = "none";
  let $lan =lan_subdomain_wsteam_efb();
  const ws = `https://${$lan}whitestudio.team/document/`;
  listOfHow_emsfb = {
    1: { title: efb_var.text.howProV, url: `${ws}/how-to-activate-pro-version-easy-form-builder-plugin/` },
    2: { title: efb_var.text.howConfigureEFB, url: `${ws}/how-to-set-up-form-notification-emails-in-easy-form-builder/#settingUp-Notification` },
    3: { title: efb_var.text.howGetGooglereCAPTCHA, url: `${ws}/how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder/` },
    4: { title: efb_var.text.howActivateAlertEmail, url: `${ws}/how-to-set-up-form-notification-emails-in-easy-form-builder/#email-notification` },
    5: { title: efb_var.text.howCreateAddForm, url: `${ws}/how-to-create-your-first-form-with-easy-form-builder/` },
    6: { title: efb_var.text.howActivateTracking, url: `${ws}/how-to-activate-confirmation-code-in-easy-form-builder/` },
    7: { title: efb_var.text.howWorkWithPanels, url: `${ws}/complete-guide-of-form-entries-and-mange-forms/` },
    8: { title: efb_var.text.howAddTrackingForm, url: `${ws}/how-to-add-the-confirmation-code-finder/` },
    9: { title: efb_var.text.howFindResponse, url: `${ws}/how-to-find-a-response-through-a-confirmation-code/` },
  }

  if(efb_var.language == "fa_IR"){
    const ef = `https://easyformbuilder.ir/%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa/`
    listOfHow_emsfb = {
      1: { title: efb_var.text.howProV, url: `${ef}%d9%86%d8%ad%d9%88%d9%87-%d9%81%d8%b9%d8%a7%d9%84-%d8%b3%d8%a7%d8%b2%db%8c-%d9%86%d8%b3%d8%ae%d9%87-%d9%88%db%8c%da%98%d9%87-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      2: { title: efb_var.text.howConfigureEFB, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%db%8c%d9%85%db%8c%d9%84-%d8%a7%d8%b7%d9%84%d8%a7%d8%b9-%d8%b1%d8%b3%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3/` },
      3: { title: efb_var.text.howGetGooglereCAPTCHA, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%da%a9%d9%be%da%86%d8%a7-%da%af%d9%88%da%af%d9%84-%d8%b1%d8%a7-%d8%af%d8%b1%db%8c%d8%a7%d9%81%d8%aa-%d9%88-%d8%af%d8%b1-%d8%a7%d9%81%d8%b2%d9%88%d9%86%d9%87-%d9%81/` },
      4: { title: efb_var.text.howActivateAlertEmail, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d8%a7%db%8c%d9%85%db%8c%d9%84-%d8%a7%d8%b7%d9%84%d8%a7%d8%b9-%d8%b1%d8%b3%d8%a7%d9%86%db%8c-%d8%b1%d8%a7-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3/` },
      5: { title: efb_var.text.howCreateAddForm, url: `${ef}%da%86%da%af%d9%88%d9%86%d9%87-%d9%81%d8%b1%d9%85-%d8%aa%d9%88%d8%b3%d8%b7-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d8%af%d8%b1-%d9%88%d8%b1%d8%af%d9%be%d8%b1%d8%b3-%d8%a8%d8%b3/` },
      6: { title: efb_var.text.howActivateTracking, url: `${ef}%d9%86%d8%ad%d9%88%d9%87-%d9%81%d8%b9%d8%a7%d9%84-%d8%b3%d8%a7%d8%b2%db%8c-%da%a9%d8%af-%d9%be%db%8c%da%af%db%8c%d8%b1%db%8c-%d8%af%d8%b1-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      7: { title: efb_var.text.howWorkWithPanels, url: `${ef}%d8%b1%d9%88%d8%b4-%d9%85%d8%af%db%8c%d8%b1%db%8c%d8%aa-%d9%81%d8%b1%d9%85-%d9%87%d8%a7%db%8c-%d9%be%d8%b1-%d8%b4%d8%af%d9%87%d9%be%d8%a7%d8%b3%d8%ae-%d9%87%d8%a7-%d8%af%d8%b1-%d8%a7%d9%81%d8%b2/` },
      8: { title: efb_var.text.howAddTrackingForm, url: `${ef}%d8%a7%d9%86%d8%aa%d8%b4%d8%a7%d8%b1-%db%8c%d8%a7%d8%a8%d9%86%d8%af%d9%87-%da%a9%d8%af-%d9%be%db%8c%da%af%db%8c%d8%b1%db%8c-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86/` },
      9: { title: efb_var.text.howFindResponse, url: `${ef}%d9%be%db%8c%d8%af%d8%a7-%da%a9%d8%b1%d8%af%d9%86-%d9%be%db%8c%d8%a7%d9%85-%da%a9%d8%af-%d8%b1%d9%87%da%af%db%8c%d8%b1%db%8c-%d9%81%d8%b1%d9%85-%d8%b3%d8%a7%d8%b2-%d8%a2%d8%b3%d8%a7%d9%86-%d9%88%d8%b1/` },
    }
  }


  let str = "";
  for (const l in listOfHow_emsfb) {
    str += `<a class="efb btn efb btn-darkb text-white btn-lg d-block mx-3 mt-2" target="_blank" href="${listOfHow_emsfb[l].url}"><i class="efb  bi-youtube mx-1"></i>${listOfHow_emsfb[l].title}</a>`
  }
  // 3.8.6 stasrt
  document.getElementById('content-efb').innerHTML = `
  <img src="${efb_var.images.title}"  class="efb crcle-footer">
  <div class="efb container row">
  <h4 class="efb title-holder efb fs-4">
      <img src="${efb_var.images.title}" class="efb title efb">
      <i class="efb  bi-info-circle title-icon mx-2"></i>${efb_var.text.help}
  </h4>
  <div class="efb crd efb col-md-7"><div class="efb card-body"> <div class="efb d-grid gap-2">${str}</div></div></div>
  <div class="efb col-md-4 mx-1 py-5 crd efb">
                  <img src="${efb_var.images.logo}"  class="efb description-logo efb">
                  <h1 class="efb  pointer-efb ec-efb" data-eventform="links" data-linkname="ws" ><font style="vertical-align: inherit;"><font style="vertical-align: inherit;">${efb_var.text.easyFormBuilder}</font></font></h1>
                  <h3 class="efb  pointer-efb  card-text ec-efb" data-eventform="links" data-linkname="ws">${efb_var.text.byWhiteStudioTeam}</h3>
                  <div class="efb clearfix"></div>
                  <p class="efb  card-text efb pb-3 fs-6">
                  ${efb_var.text.youCanFindTutorial} ${efb_var.text.proUnlockMsg}
                  </p>
                  ${efb_var.pro == true ||  efb_var.pro == 1 ? '' : `<a class="efb btn text-dark btn-r btn-warning  btn-lg ec-efb"  data-eventform="links" data-linkname="price"><i class="efb  bi-gem mx-1"></i>${efb_var.text.activateProVersion}</a>`}
                  <a class="efb btn mt-1 efb btn-outline-pink btn-lg ec-efb" data-eventform="links" data-linkname="wiki"><i class="efb  bi-info-circle mx-1"></i>${efb_var.text.documents}</a>
              </div>
  </div>
  ${efb_powered_by()}
 `;
   // 3.8.6 end
}



function fun_show_setting__emsFormBuilder() {

  let activeCode = 'null';
  let sitekey = 'null';
  let secretkey = 'null';
  let stripeSKey = 'null';
  let stripePKey = 'null';
  let email = 'null';
  let trackingcode = 'null';
  let apiKeyMap = 'null';
  let smtp = false;
  let text = efb_var.text;
  let textList = "<!--list EFB -->";
  let bootstrap = false;
  let emailTemp = "null"
  let payToken="null";
  let act_local_efb =scaptcha =false;
  let dsupfile= showIp =activeDlBtn =scaptcha=act_local_efb =false;
  let phoneNumbers=sms_method = 'null';
  let femail ='null';
  let demail ='no-reply@'+ window.location.hostname;
  let osLocationPicker = false;
  //check demail is valid email
  demail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(demail)  &&  demail.includes('127.')==false ? demail : 'no-reply@yourDomainName.com';
  if ((ajax_object_efm.setting[0] && ajax_object_efm.setting[0].setting.length > 5) || typeof valueJson_ws_setting == "object" && valueJson_ws_setting.length != 0) {

    if (valueJson_ws_setting.length == 0) {
      valueJson_ws_setting = (JSON.parse(ajax_object_efm.setting[0].setting.replace(/[\\]/g, '')));
    } else if (typeof valueJson_ws_setting == "string") {
      valueJson_ws_setting = (JSON.parse(valueJson_ws_setting.replace(/[\\]/g, '')));
    }
    const f = (name) => { 
      if (valueJson_ws_setting.hasOwnProperty(name)==true) { return valueJson_ws_setting[name] } else { return 'null' } }
    if (valueJson_ws_setting.text) text = valueJson_ws_setting.text
    
    activeCode = f('activeCode');
    sitekey = f(`siteKey`);
    secretkey = f(`secretKey`);
    email = f(`emailSupporter`);
    femail = f('femail');

    femail = femail=='null' ? demail : femail;
    trackingcode = f(`trackingCode`);
    apiKeyMap = f(`apiKeyMap`);
    stripeSKey = f(`stripeSKey`);
    stripePKey = f(`stripePKey`);
    smtp = f('smtp') == 'null' ? false : Boolean(f('smtp'));
    bootstrap = f('bootstrap');
    osLocationPicker = f('osLocationPicker') == 'null' ? false : Boolean(f('osLocationPicker'));
    emailTemp = f('emailTemp');
    sms_config_efb= sms_method = f('sms_config')=='null' ? 'null' :f('sms_config');
    
    scaptcha = f('scaptcha')=='null' ? false :f('scaptcha') ;
    //console.log(f('scaptcha'),scaptcha)
    activeDlBtn = f('activeDlBtn')=='null' ? true :f('activeDlBtn');
    showIp = f('showIp') =='null' ? false :f('showIp');
    dsupfile = f('dsupfile') =='null' ? true :f('dsupfile');
    phoneNumbers = f('phnNo');
    adminSN  = f('adminSN') =='null' ? true :f('adminSN');

    
    //console.log(`dsupfile[${dsupfile}]` ,f('dsupfile'));
    payToken = f('payToken');
    act_local_efb = f('act_local_efb');
    
    
    act_local_efb= act_local_efb =='null'  || act_local_efb==false ? false :true
    //console.log(f('act_local_efb'));
  }

  // 3.8.6 start
  let persianPayToken = () => {
    const visible = efb_var.language == "fa_IR" ? "style='display:block'" : "style='display:none'";
      return `
      <div ${visible}>
      <h5 class="efb  card-title mt-3 mobile-title"> <i class="efb bi-credit-card-2-front m-3"></i>درگاه پرداخت</h5>
      <p class="efb mx-5">توکن: <a class="efb  pointer-efb ec-efb" data-eventform="links" data-linkname="AdnPPF">توکن دریافتی از درگاه پرداخت خود را در زیر وارد کنید</a></p>
      <div class="efb mx-3 my-2">
        <div class="efb card-body mx-0 py-1 ${mxCSize4}">                                   
          <label class="efb form-label mx-2 fs-6">توکن</label>
          <input type="text" class="efb form-control w-75 h-d-efb border-d  efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''} " id="payToken_emsFormBuilder"placeholder="توکن" ${payToken !== "null" ? `value="${payToken}"` : ""} data-tab="${efb_var.text.payment}">
        </div>
      </div>
      </div>
    `
  
  }
  // 3.8.6 end

  Object.entries(text).forEach(([key, value]) => {
    state = key == "easyFormBuilder" ? "d-none" : "d-block";
    if (key != "forbiddenChr") textList += `<input type="text"  id="${key}"  class="efb sen w-75 sen-validate-efb ${state} form-control text-muted efb  border-d efb-rounded h-d-efb  m-2"  placeholder="${value}" id="labelEl" required value="${value ? value : ''}" data-tab="${efb_var.text.localization}">`
  });
  const mxCSize = !mobile_view_efb ? 'mx-5' : 'mx-1';
  const mxCSize4 = !mobile_view_efb ? 'mx-4' : 'mx-1';
  // 3.8.6 start
  let msg_email = efb_var.text.mlntip.replace('%s1', `<a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="EmailSpam" >`).replace('%s2', '</a>').replace('%s3', `<a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="support" >`).replace('%s4', '</a>');
  // 3.8.6 end
  const proChckEvent =efb_var.pro!=true && efb_var.pro!="true" ? `onChange="pro_show_efb('${efb_var.text.proUnlockMsg}')"` :'';
  document.getElementById('content-efb').innerHTML = `
  <div class="efb container">
            <h4 class="efb title-holder efb fs-4">
                <img src="${efb_var.images.title}" class="efb title efb">
                <i class="efb  bi-gear title-icon mx-1"></i>${efb_var.text.setting}
            </h4>
            <div class="efb crd efb">
                <div class="efb card-body">
                        <nav>
                            <div class="efb nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="efb  nav-link active" id="nav-home-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-home" aria-selected="true"><i class="efb  bi bi-gear mx-2"></i>${efb_var.text.general}</button>
                            <button class="efb  nav-link " id="nav-response-tab" data-bs-toggle="tab" data-bs-target="#nav-response" type="button" role="tab" aria-controls="nav-respons" aria-selected="true"><i class="efb  bi bi-chat-left-text mx-2"></i>${efb_var.text.response}</button>
                            <button class="efb  nav-link" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-google" type="button" role="tab" aria-controls="nav-profile" aria-selected="false"><i class="efb  bi bi-google mx-2"></i>${efb_var.text.googleKeys}</button>
                            <button class="efb  nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-email" type="button" role="tab" aria-controls="nav-contact" aria-selected="false"><i class="efb  bi bi-at mx-2"></i>${efb_var.text.emailSetting}</button>
                            <button class="efb  nav-link" id="nav-contact-tab " data-bs-toggle="tab" data-bs-target="#nav-emailtemplate" type="button" role="tab" aria-controls="nav-emailtemplate" aria-selected="false"><i class="efb  bi bi-envelope mx-2"></i>${efb_var.text.emailTemplate}</button> 
                            <button class="efb  nav-link" id="nav-contact-tab" data-bs-toggle="tab" data-bs-target="#nav-text" type="button" role="tab" aria-controls="nav-text" aria-selected="false"><i class="efb  bi bi-fonts mx-2"></i>${efb_var.text.localization}</button>
                            <button class="efb  nav-link" id="nav-stripe-tab" data-bs-toggle="tab" data-bs-target="#nav-stripe" type="button" role="tab" aria-controls="nav-stripe" aria-selected="false"><i class="efb  bi bi-credit-card mx-2"></i>${efb_var.text.payment}</button>
                            <button class="efb  nav-link" id="nav-smsconfig-tab" data-bs-toggle="tab" data-bs-target="#nav-smsconfig" type="button" role="tab" aria-controls="nav-smsconfig" aria-selected="false"><i class="efb  bi bi-chat-left-dots mx-2"></i>${efb_var.text.sms_config}</button>
                        </div>
                        </nav>
                        <div class="efb tab-content" id="nav-tabContent">
                          <div class="efb tab-pane fade show active" id="nav-general" role="tabpanel" aria-labelledby="nav-home-tab">
                            <!--General-->
                            <div class="efb m-3">
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-gem m-3"></i>${efb_var.text.proVersion}
                                </h5>
                                <!-- 3.8.6 start -->
                                ${efb_var.pro == true ||  efb_var.pro == 1 ? '' :`<a class="efb ${mxCSize} efb pointer-efb ec-efb" data-eventform="links" data-linkname="price">${efb_var.text.clickHereGetActivateCode}</a>`}
                                <!-- 3.8.6 end -->
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.pro == true ||  efb_var.pro == 1 ? 'is-valid bg-light' : ''}" id="activeCode_emsFormBuilder" placeholder="${efb_var.text.enterActivateCode}" ${activeCode !== "null" ? `value="${activeCode}"` : ""} data-tab="${efb_var.text.general}">
                                ${efb_var.pro == true ||  efb_var.pro == 1 ? `<p class="efb text-darkb fs-6 mx-1 ">${efb_var.text.actvtcmsg}</p>` : '' }
                                    <span id="activeCode_emsFormBuilder-message" class="efb text-danger"></span>
                                </div>
                               
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-bootstrap m-3 mobile-text"></i>${efb_var.text.bootStrapTemp}
                                </h5>
                                <h6 class="efb  ${mxCSize} text-danger mobile-text">${efb_var.text.iUsebootTempW}</h6>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" id="bootstrap_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${bootstrap == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >       
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="bootstrap_emsFormBuilder">${efb_var.text.iUsebootTemp}</label>                                                                           
                                </div>
                              
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-file-earmark-minus m-3"></i>${efb_var.text.clearFiles}
                                </h5>
                                <p class="efb  ${mxCSize} mobile-text">${efb_var.text.youCanRemoveUnnecessaryFileUploaded}</p>
                                <div class="efb card-body text-center py-1">
                                    <button type="button" class="efb btn efb btn-outline-pink btn-lg " OnClick="clear_garbeg_emsFormBuilder()" id="clrUnfileEfb">
                                        <i class="efb  bi-x-lg mx-1 efb mobile-text"></i><span id="clrUnfileEfbText">${efb_var.text.clearUnnecessaryFiles}</span
                                    </button>
                                </div>

                                 <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  m-3 bi-pin-map-fill mobile-text"></i>${efb_var.text.locationPicker}
                                </h5>
                                <p class="efb  ${mxCSize} mobile-text">${efb_var.text.lpds}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                <button type="button" id="osLocationPicker_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${osLocationPicker == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >       
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="osLocationPicker_emsFormBuilder">${efb_var.text.elpo}</label>                                                                           
                                </div>
                           
                                <div class="efb clearfix"></div>
                                
                               
                            <!--End General-->
                            </div>  
                        </div>
                        <div class="efb tab-pane fade" id="nav-response" role="tabpanel" aria-labelledby="nav-response-tab">
                            <!--response-->
                            <div class="efb m-3">
                                
                                                  
                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-search m-3"></i>${efb_var.text.trackingCodeFinder}
                              </h5>
                              <p class="efb ${mxCSize}">${efb_var.text.copyAndPasteBelowShortCodeTrackingCodeFinder}</p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                      <div class="efb row efb col-12">
                                          <div class="efb  col-md-8">
                                            <input type="text"  class="efb form-control efb h-d-efb  border-d efb-rounded my-1" id="shortCode_emsFormBuilder" value="[Easy_Form_Builder_confirmation_code_finder]" readonly>
                                            <span id="shortCode_emsFormBuilder-message" class="efb text-danger"></span>
                                          </div> 
                                            <button type="button" class="efb btn col-md-4 efb btn-r h-d-efb btn-outline-pink my-1" onclick="copyCodeEfb('shortCode_emsFormBuilder')">
                                                <i class="efb  bi-clipboard-check mx-1"></i> ${efb_var.text.copy}
                                            </button>
                                        </div>
                              </div>
                              <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-chat-left-text m-3"></i>${efb_var.text.rbox}
                              </h5>
                                <div class="efb card-body mx-0 py-0 ${mxCSize4}">
                                  <button type="button" id="scaptcha_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${scaptcha == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   onclick="efb_check_el_pro(this)">       
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="scaptcha_emsFormBuilder">${efb_var.text.scaptcha}</label>                                
                                </div>
            
                                <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="showUpfile_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${dsupfile == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"  onclick="efb_check_el_pro(this)" >       
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="showUpfile_emsFormBuilder">${efb_var.text.dsupfile}</label>                                
                                                                 
                                </div>
                                <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="activeDlBtn_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${activeDlBtn == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   onclick="efb_check_el_pro(this)">       
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="activeDlBtn_emsFormBuilder">${efb_var.text.sdlbtn}</label>
                                </div>
                               <!-- <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="showIp_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${showIp == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"   >       
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="showIp_emsFormBuilder">${efb_var.text.sips}</label>                                
                                </div> -->
                               <div class="efb card-body my-0 py-0 ${mxCSize4}">
                                  <button type="button" id="adminSN_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle  ${adminSN == true ? "active" : ""} setting" data-toggle="button" aria-pressed="false" autocomplete="off"  onclick="efb_check_el_pro(this)" >       
                                  <div class="efb handle"></div>
                                  </button>
                                  <label class="efb form-check-label fs-6 efb mx-2 my-3" for="adminSN_emsFormBuilder">${efb_var.text.admines}</label>                                
                                </div>
                           
                              
                            <!--End General-->
                            </div>  
                        </div>
                        <div class="efb tab-pane fade" id="nav-google" role="tabpanel" aria-labelledby="nav-profile-tab">
                            <div class="efb m-3">
                                <div id="message-google-efb"></div>
                                
                             <!--Google-->
                            
                             ${apiKeyMap == 'null' ? `<div class="efb m-3 p-3 efb alert-info" role=""><h5 class="efb alert-heading">🎉 ${efb_var.text.SpecialOffer} </h5> <div>${googleCloudOffer()} </div></div>` : ``}
                             <h5 class="efb  card-title mt-3 mobile-title">
                                <i class="efb  bi-person-check m-3"></i>${efb_var.text.reCAPTCHAv2}
                            </h5>
                            <p class="efb ${mxCSize}"><a target="_blank" href="https://www.google.com/recaptcha/about/">${efb_var.text.reCAPTCHA}</a>  <a target="_blank" href="https://youtu.be/a1jbMqunzkQ">${efb_var.text.clickHereWatchVideoTutorial}</a></p>
                            <div class="efb card-body mx-0 py-1 ${mxCSize4}">                                   
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.siteKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="sitekey_emsFormBuilder" placeholder="${efb_var.text.enterSITEKEY}" ${sitekey !== "null" ? `value="${sitekey}"` : ""} data-tab="${efb_var.text.googleKeys}">
                                <span id="sitekey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                <label class="efb  form-label mx-2 col-12  mt-4 fs-6">${efb_var.text.SecreTKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="secretkey_emsFormBuilder" placeholder="${efb_var.text.EnterSECRETKEY}" ${secretkey !== "null" ? `value="${secretkey}"` : ""} data-tab="${efb_var.text.googleKeys}">
                                <span id="secretkey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            </div>
                          
                            <h5 class="efb  card-title mt-3 mobile-title d-none">
                                <i class="efb  bi-geo-alt m-3"></i> ${efb_var.text.maps} 
                            </h5>
                             <a href="#" class="efb d-none">${efb_var.text.clickHereWatchVideoTutorial}</a> 
                            <p class="efb ${mxCSize}">${efb_var.text.youNeedAPIgMaps}</p>
                            <div class="efb  d-none card-body mx-0 py-1 ${mxCSize4}">                                   
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.aPIKey}</label>
                                <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="apikey_map_emsFormBuilder" placeholder="${efb_var.text.enterAPIKey}" ${apiKeyMap !== "null" ? `value="${apiKeyMap}"` : ""} ${proChckEvent} data-tab="${efb_var.text.googleKeys}">
                                <span id="apikey_map_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            </div>
                   
                              <!--End Google-->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-email" role="tabpanel" aria-labelledby="nav-contact-tab">
                            <div class="efb mx-3 ">
                                <!--Email-->
                                <h5 class="efb  card-title mt-3 mobile-title">
                                    <i class="efb  bi-at m-3"></i>${efb_var.text.alertEmail}
                                </h5>
                                <p class="efb ${mxCSize}">${efb_var.text.whenEasyFormBuilderRecivesNewMessage}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4} mb-3">
                                    <label class="efb form-label mx-2 fs-6">${efb_var.text.email}</label>
                                    <input type="email" class="efb form-control w-75 h-d-efb border-d efb-rounded mb-1 ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="${efb_var.text.enterAdminEmail}" ${email !== "null" ? `value="${email}"` : ""} data-tab="${efb_var.text.emailSetting}">
                                    <span id="email_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                    <span  class="efb bg-light text-dark form-control border-0  w-75 efb">${msg_email}</span>
                                </div>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4} mb-3">
                                    <label class="efb form-label mx-2 fs-6">${efb_var.text.from}</label>
                                    <input type="email" class="efb form-control w-75 h-d-efb border-d efb-rounded mb-1 ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="femail_emsFormBuilder"  ${efb_var.pro != true  &&  efb_var.pro != 1 ? 'onClick="pro_show_efb(1)"' :''} placeholder="${'no-reply@'+ window.location.hostname}" ${femail !== "null" ? `value="${femail}"` : ""} data-tab="${efb_var.text.emailSetting}">
                                    <span id="femail_emsFormBuilder-message" class="efb  text-danger  w-75 efb"></span>
                                    <span  class="efb  form-control border-0  w-75 efb">${efb_var.text.msgfml}</span>
                                </div>
                                
                                <h5 class="efb card-title mt-3col-12 efb ">
                                    <i class="efb  bi-envelope m-3"></i>${efb_var.text.emailServer}
                                </h5>
                                <p class="efb ${mxCSize}">${efb_var.text.beforeUsingYourEmailServers}</p>
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                                    <button type="button" class="efb btn  efb btn-outline-pink btn-lg "onClick="clickToCheckEmailServer()" id="clickToCheckEmailServer">
                                        <i class="efb  bi-chevron-double-up mx-1 text-center"></i>${efb_var.text.clickToCheckEmailServer}
                                    </button>
                                   <input type="hidden" id="smtp_emsFormBuilder" value="${smtp == "null" ? 'false' : smtp}">
                                </div>
                                <div class="efb card-body mx-0 py-1 mx-4">
                           
                                <button type="button" id="hostSupportSmtp_emsFormBuilder" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${smtp == true ? "active" : ""}" data-toggle="button" aria-pressed="false" autocomplete="off"   >       
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-6 efb mx-2 my-3" for="hostSupportSmtp_emsFormBuilder">${efb_var.text.hostSupportSmtp}</label>                                            
                               
                                </div>
                                <!--End Email-->
                            </div>
                        </div>
              
                      
                        <div class="efb tab-pane fade" id="nav-text" role="tabpanel" aria-labelledby="nav-text-tab">
                            <div class="efb mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb  card-title mt-3 mobile-title">
                                 <i class="efb  bi-fonts m-3"></i>${efb_var.text.localization}
                               </h5>
                               <p class="efb ${mxCSize}">${efb_var.text.translateLocal}</p>
                               <div class="efb card-body mx-0 py-1 mx-4">
                           
                               <button type="button" id="act_local_efb" data-state="off" data-name="disabled" class="efb mx-0 btn h-s-efb  btn-toggle ${act_local_efb == true ? "active" : ""}" onclick="act_local_efb_event(this);"  data-toggle="button" aria-pressed="false" autocomplete="off"   >       
                                <div class="efb handle"></div>
                                </button>
                                <label class="efb form-check-label fs-7 efb m-2 " for="act_local_efb">${efb_var.text.ilclizeFfb}</label>                                            
                               
                                </div>
                                <div id="textList-efb"  class="efb mt-2 py-2 ${mobile_view_efb ? '' : 'px-5'}  ${act_local_efb == false ? "d-none" : ''}">${textList} </div>                                
                                <!-- END Text Section -->
                            </div>
                        </div>
                        <div class="efb tab-pane fade" id="nav-stripe" role="tabpanel" aria-labelledby="nav-stripe-tab">
                            <div class="efb mx-3 my-2">
                            <!-- Text Section -->
                               <h5 class="efb  card-title mt-3 mobile-title">
                                 <i class="efb  bi-stripe m-3"></i>${efb_var.text.stripe}
                               </h5>
                               <!-- 3.8.6 start -->
                               <p class="efb ${mxCSize}">${efb_var.text.stripeMP} <a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="stripe" >${efb_var.text.help}</a></p>
                                <!-- 3.8.6 end -->
                                <div class="efb card-body mx-0 py-1 ${mxCSize4}">                                   
                                  <label class="efb form-label mx-2 fs-6">${efb_var.text.publicKey}</label>
                                  <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="stripePKey_emsFormBuilder" placeholder="${efb_var.text.publicKey}" ${stripePKey !== "null" ? `value="${stripePKey}"` : ""} ${proChckEvent} data-tab="${efb_var.text.payment}">
                                  <span id="stripePKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                  <label class="efb  form-label mx-2 fs-6 col-12  mt-4">${efb_var.text.SecreTKey}</label>
                                  <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="stripeSKey_emsFormBuilder" placeholder="${efb_var.text.SecreTKey}" ${stripeSKey !== "null" ? `value="${stripeSKey}"` : ""} ${proChckEvent} data-tab="${efb_var.text.payment}">
                                  <span id="stripeSKey_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                                                               
                              </div>
                              
                              ${persianPayToken()}
                         
                                                           
                                <!-- END payment Section -->
                            </div>
                        </div>


                        <div class="efb tab-pane fade" id="nav-emailtemplate" role="tabpanel" aria-labelledby="nav-contact-tab">
                        <div class="efb my-2 mx-1">

                          <nav class="efb navbar navbar-expand-lg navbar-light bg-light my-2 bg-response efb">
                              <div class="efb container-fluid">
                                  <button class="efb navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01" aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation"><span class="efb navbar-toggler-icon"></span></button>
                                  <div class="efb collapse navbar-collapse py-1" id="navbarTogglerDemo01">
                                  <ul class="efb navbar-nav me-auto mb-2 mb-lg-0"><!--efb.app-->
                                    <li class="efb nav-item"><a class="efb nav-link efb btn btn-outline-pink " onclick="email_template_efb('p')" >
                                     <i class="efb bi-play-fill mx-1 "></i>${efb_var.text.preview}</a>
                                    </li>                                  
                                    <li class="efb nav-item">
                                        <a class="efb nav-link efb btn text-capitalize  " onclick="email_template_efb('r')" >
                                        <i class="efb bi-arrow-counterclockwise mx-1 "></i>${efb_var.text.reset}</a>
                                    </li>
                                    <li class="efb nav-item">
                                        <!-- 3.8.6 start -->
                                        <a class="efb nav-link efb btn ec-efb " data-eventform="links" data-linkname="wiki">
                                        <!-- 3.8.6 end -->
                                        <i class="efb bi-question mx-1 "></i>${efb_var.text.help}</a>
                                    </li>

                                    <li class="efb dropdown">
                                    <a class="efb nav-link efb btn dropdown-toggle" data-toggle="dropdown" href="#">${efb_var.text.templates}
                                    <span class="efb caret"></span></a>
                                    <ul class="efb dropdown-menu">
                                      <li class="efb nav-item"><a onClick="fun_add_email_template_efb(1)" class="efb nav-link efb btn" >${efb_var.text.emailTemplate} 1</a></li>
                                     <li class="efb nav-item"><a onClick="fun_add_email_template_efb(2)"  class="efb nav-link efb btn">${efb_var.text.emailTemplate} 2</a></li>
                                    </ul>
                                  </li>
                                    
                              </div>      
                          </nav>
                        </div>
                        <div class="efb  mx-3 row col-12 mb-2">
                            <!--EmailTemplate-->
                              <div class="efb  col-md-8 bg-back">
                                <h3 class="efb  card-title mt-3 mobile-title">${efb_var.text.editor}</h3>
                                <textarea class="efb  form-control" id="emailTemp_emsFirmBuilder" rows="50" data-tab="${efb_var.text.emailTemplate}">${emailTemp !== "null" ? emailTemp : ''}</textarea>                        
                                <span id="emailTemp_emsFirmBuilder-message" class="efb text-danger"></span>
                              </div>
                            <div class="efb col-md-4 mt1 efb guide p-2"> 
                              <h3 class="efb  card-title mt-3 mobile-title">${efb_var.text.info}</h3>
                              <p class="efb  m-2 fs-6">
                              ${efb_var.text.infoEmailTemplates}
                              </br></br>
                              <span class="efb  fs-7"> ${efb_var.text.noticeEmailContent}</span> 
                              </br></br>
                              <span class="efb  fs-7">shortcode_title <span class="efb  text-danger">*</span> :</span> ${efb_var.text.shortcodeTitleInfo}
                              </br></br>
                              <span class="efb  fs-7">shortcode_message <span class="efb  text-danger">*</span> :</span> ${efb_var.text.shortcodeMessageInfo}
                              </br></br>
                              <span class="efb  fs-7">shortcode_website_name :</span> ${efb_var.text.shortcodeWebsiteNameInfo}
                              </br></br> 
                              <span class="efb  fs-7">shortcode_website_url :</span> ${efb_var.text.shortcodeWebsiteUrlInfo}
                              </br></br>
                              <span class="efb  fs-7">shortcode_admin_email  :</span> ${efb_var.text.shortcodeAdminEmailInfo}
                              </p>
                              </br></br>
                              <!-- 3.8.6 start -->
                              <a class="efb btn mt-1 efb btn-outline-pink btn-lg ec-efb " data-eventform="links" data-linkname="wiki"><i class="efb  bi-info-circle mx-1 ec-efb " data-eventform="links" data-linkname="wiki"></i>${efb_var.text.documents}</a>
                              <!-- 3.8.6 end -->
                            </div>
                         
                            <!--End EmailTemplate-->
                        </div>
                    </div>
                        <!-- smsconfig Section -->
                        <div class="efb tab-pane fade" id="nav-smsconfig" role="tabpanel" aria-labelledby="nav-smsconfig-tab">
                          <div class="efb mx-3 my-2">
                          
                            <h5 class="efb  card-title mt-3 ">
                              <i class="efb  bi-chat-left-dots m-3"></i>${efb_var.text.sms_config}
                            </h5>
                            <p class="efb ${mxCSize}">${efb_var.text.sms_mp} <a class="efb pointer-efb ec-efb" data-eventform="links" data-linkname="smsconfig" >${efb_var.text.help}</a></p>
                              <div class="efb card-body mx-0 py-1 ${mxCSize4}">                                   
                                <label class="efb form-label mx-2 fs-6">${efb_var.text.sms_ct}</label>      
                                <div class="efb  col-md-12 col-sm-12 px-0 mx-0 py-0 my-0 ttEfb show" data-id="sms_config_select" id="sms_config_select" >
                                  <div class="efb     efb1 " data-css="sms_config_select" id="sms_config_select_options">
                                      <!-- <div class="efb  form-check  radio  efb1 " data-css="sms_config_select" data-parent="sms_config_select" data-id="efb_sms_service" id="efb_sms_service-v" >
                                        <input class="efb  form-check-input emsFormBuilder_v   fs-7 disabled " data-tag="radio" data-type="radio" data-vid="sms_config_select" type="radio" name="sms_config_select" value="efb_sms_service" id="efb_sms_service" data-id="efb_sms_service-id" data-op="efb_sms_service" onchange="check_server_sms_method_efb(this)" data-tab="${efb_var.text.sms_config}">
                                        <label class="efb   text-labelEfb  h-d-efb fs-7 hStyleOpEfb " id="efb_sms_service_lab" for="efb_sms_service">${efb_var.text.sms_efbs}</label>
                                      </div> -->
                                      <div class="efb  form-check  radio  efb1 " data-css="sms_config_select" data-parent="sms_config_select" data-id="wp_sms_plugin" id="wp_sms_plugin-v">
                                        <input class="efb  form-check-input emsFormBuilder_v   fs-7 disabled" data-tag="radio" data-type="radio" data-vid="sms_config_select" type="radio" name="sms_config_select" value="wp_sms_plugin" id="wp_sms_plugin" data-id="wp_sms_plugin-id" data-op="wp_sms_plugin" onchange="check_server_sms_method_efb(this)" data-tab="${efb_var.text.sms_config}" ${sms_method=="wpsms" ? 'checked' :''}>
                                        <label class="efb   text-labelEfb  h-d-efb fs-7 hStyleOpEfb " id="wp_sms_plugin_lab" for="wp_sms_plugin">${efb_var.text.sms_wpsmss}</label>
                                        <i class="mx-1 efb bi-patch-question fs-7 text-success pointer-efb ec-efb" data-eventform="links" data-linkname="wpsmss" > </i>
                                      </div>
                                  </div>
                                </div>                                                                                         
                              </div>
                          </div>   
                          <h5 class="efb  card-title mt-3 ">
                              <i class="efb bi-phone m-3"></i>${efb_var.text.sms_noti}
                            </h5>                                        
                          <p class="efb ${mxCSize}">${efb_var.text.sms_dnoti}</p>
                          <div class="efb card-body mx-0 py-1 ${mxCSize4}">
                          <label class="efb form-label mx-2 fs-6">${efb_var.text.sms_admn_no}</label>
                            <input type="text" class="efb form-control w-75 h-d-efb border-d efb-rounded ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="pno_emsFormBuilder" pattern="^\+\d{11,14}$" placeholder="+11234567890" ${phoneNumbers !== "null" ? `value="${phoneNumbers}"` : ""}  data-tab="${efb_var.text.sms_config}">
                            <span id="pno_emsFormBuilder-message" class="efb text-danger col-12 efb"></span>
                            <p class="efb m-2">${efb_var.text.sms_ndnoti}</p>
                          </div>
                        </div>
                        <!-- smsconfig Section end-->
                        <button type="button" id="save-stng-efb" class="efb btn btn-r btn-primary btn-lg ${efb_var.rtl == 1 ? 'float-start' : 'float-end '}" mt-2 mx-5"  onClick="fun_set_setting_emsFormBuilder(0)">
                            <i class="efb  bi-save mx-1"></i>${efb_var.text.save}
                        </button>                  
                </div>
            </div>
            </div>
            ${efb_powered_by()}
`

  for (const el of document.querySelectorAll(`.sen`)) {
    el.addEventListener("change", (e) => {
      //forbiddenChr
      if (el.value.match(/["'\\]/) != null) {
        el.className = colorBorderChangerEfb(el.className, "border-danger")
        fun_switch_saveSetting(true, el.id);
      } else {
        text[el.id] = el.value;
        efb_var.text[el.id] = el.value;
        el.className = colorBorderChangerEfb(el.className, "border-d")
        fun_switch_saveSetting(false, el.id);
      }
    })
  }
}

let idOfListsEfb = [];
function fun_switch_saveSetting(i, id) {
  if (i == true) {
    idOfListsEfb.push(id);
    document.getElementById("save-stng-efb").classList.contains("disabled") == false ? document.getElementById("save-stng-efb").classList.add("disabled") : "";
    alert_message_efb(`Forbidden characters: " \' \\ `, "", 5000, "danger");
  } else {
    const indx = idOfListsEfb.findIndex(x => x == id);
    if (indx != -1) idOfListsEfb.splice(indx, 1);
    idOfListsEfb.length == 0 && document.getElementById("save-stng-efb").classList.contains("disabled") == true ? document.getElementById("save-stng-efb").classList.remove("disabled") : "";
  }
}

function fun_set_setting_emsFormBuilder(state_auto = 0) {
  // fun_state_loading_message_emsFormBuilder(1);
  if(state_auto==0){
  let btn = document.getElementById('save-stng-efb');
  btn.classList.add('disabled');

  const nnrhtml = btn.innerHTML;
  btn.innerHTML = `<i class="efb  bi-hourglass-split"></i>`
  }
  
  //fun_State_btn_set_setting_emsFormBuilder();

  const returnError=(val)=>{
    if(state_auto==1){return}
    m =efb_var.text.msgchckvt.replace('XXX', val );
    
    noti_message_efb(m, 'danger' , `content-efb` );
    window.scrollTo({
      top: document.body.scrollHeight,
      behavior: 'smooth'
    });
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 20000);
  }
  const f = (id) => {
     const u = (url)=>{
      url = url.replace(/(http:\/\/)+/g, 'http:@efb@');
      url = url.replace(/(https:\/\/)+/g, 'https:@efb@');
      url = url.replace(/([/])+/g, '@efb@');
      return url;
     }
    const el = document.getElementById(id)

    if(el.hasAttribute('value') && el.id!="emailTemp_emsFirmBuilder"){ 
      
      el.value = sanitize_text_efb(el.value);}
      
    let r = "NotFoundEl"
    if (el.type == "text" || el.type == "email" || el.type == "textarea" || el.type == "hidden") {
      if (id == "emailTemp_emsFirmBuilder") {
        el.value = el.value.replace(/(\r\n|\r|\n|\t)+/g, '');
        el.value = u(el.value);
        el.value = el.value.replace(/(["])+/g, `'`);
        return el.value;
      } 
      return el.value;
    } else if (el.type == "checkbox") {
      
      return el.checked;
    }else if (el.type == "button"){
      //console.log(el.classList.contains)
      return el.classList.contains('active')
    }
    return "NotFoundEl"
  }
  const v = (id) => {
    
    let el = document.getElementById(id);
    if(el.hasAttribute('value') && el.id!="emailTemp_emsFirmBuilder"){ 
      if(el.type!='email'){
        el.value = sanitize_text_efb(el.value);

      }else{
        let value = sanitize_text_efb(el.value);
        const vs = value.split(',');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        for(let i=0;i<vs.length;i++){
          //console.log(vs ,regex.test(vs[i]));
          if(!regex.test(vs[i])){
            //console.log(el.value);
            el.className = colorBorderChangerEfb(el.className, "border-danger")
            document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
            returnError(`<b>${el.dataset.tab}</b>`);
           
            value=false;
           break;
          }
        }
        el.value = sanitize_text_efb(el.value);
        //console.log(el.value);
        if(value==false) return false;
              
      }
    }
    //console.log(el.value);
    if (id == 'smtp_emsFormBuilder') { return true }
    if (el.type !== "checkbox") {

      if (el.value.length > 0 && el.value.length < 10 && id !== "activeCode_emsFormBuilder" && id !== "email_emsFormBuilder" && id !== "bootstrap_emsFormBuilder" && id !== "emailTemp_emsFirmBuilder" && id !== "pno_emsFormBuilder") {
        document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue
        el.classList.add('invalid');
        window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
        return false;
      } else if (id == "bootstrap_emsFormBuilder") {
      } else if (id == "emailTemp_emsFirmBuilder") {
        let st = 1;
        let c = ''
        let ti = '';
        if (el.value.length < 5 && el.value.length > 1) {
          st = 0;
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.pleaseEnterVaildEtemp}</p></div>`
        } else if (el.value.length > 10000) {
          st = 0;
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>`;
        } else if (el.value.length > 1 && el.value.indexOf('shortcode_message') == -1 && el.value.indexOf('shortcode_title') == -1) {
          c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.addSCEmailM}</p></div>`;
          st = 0;
        }

        if (st == 0) {
          ti = efb_var.text.error
          show_modal_efb(c, ti, '', 'saveBox');
          state_modal_show_efb(1)
          return false;
        }
      } else if (id == "activeCode_emsFormBuilder") {        
        if (el.value.length < 10 && el.value.length != 0) {
          el.classList.add('invalid');
          returnError(`<b>${el.dataset.tab}</b>`);
          //window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
          return false;
        }
      } else if(id=="pno_emsFormBuilder" && Number(efb_var.pro)==1){
        
        
        if (  el.value.length < 5 && el.value.length == 0) {
          
          if(el.value.length==0){ el.value=""; return true;}
          //console.log('test!')
          el.classList.add('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = efb_var.text.pleaseEnterVaildValue;
          returnError(`<b>${el.dataset.tab}</b>`);
         // window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
          return false;
        }else{
          //validate el.value for multi international phone number
          let phoneNo=el.value;
          let phoneNoArr=phoneNo.split(',');
          let phoneNoArrLen=phoneNoArr.length;
        //write a foreach for check phoneNoArr
          for(let i=0;i<phoneNoArrLen;i++){
            //use regix for validation phone number
            if( !phoneNoArr[i].match(/^\+\d{8,14}$/)){
              returnError(`<b>${el.dataset.tab}</b>`);
              el.classList.add('invalid');
              const msg = efb_var.text.pleaseEnterVaildValue +`(${phoneNoArr[i]})`
              //window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
              document.getElementById(`${el.id}-message`).innerHTML =msg ;
              return false;
            }
          }
          document.getElementById(`${el.id}-message`).innerHTML = '';

        }

      } else {
        
        if (el.classList.contains("invalid") == true) {
          el.classList.remove('invalid');
          document.getElementById(`${el.id}-message`).innerHTML = '';
        }
        if (el.type == "email" && el.value.length > 0) {
          
          const r = valid_email_emsFormBuilder(el);
          if (r == false) {
            returnError(`<b>${el.dataset.tab}</b>`);
            el.classList.add('invalid');
            return false;
          }
        }
      }
    } else {
      if (el.id == "bootstrap_emsFormBuilder") {
      }
    }
    return true;
  }
  const ids = ['stripeSKey_emsFormBuilder', 'stripePKey_emsFormBuilder', 'smtp_emsFormBuilder', 'bootstrap_emsFormBuilder', 'apikey_map_emsFormBuilder', 'sitekey_emsFormBuilder', 'secretkey_emsFormBuilder', 'email_emsFormBuilder', 'activeCode_emsFormBuilder', 'emailTemp_emsFirmBuilder', 'pno_emsFormBuilder','femail_emsFormBuilder','osLocationPicker_emsFormBuilder'];
  let state = true

  for (let id of ids) {

    if (v(id) === false) {
      state = false;
      // fun_state_loading_message_emsFormBuilder(1);
      fun_State_btn_set_setting_emsFormBuilder(true);
      const m = document.getElementById(`${id}-message`).innerHTML;
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
    let femail = f(`femail_emsFormBuilder`);
    if(femail.length<6){ femail = 'no-reply@'+window.location.hostname;}
    //  const trackingcode = f(`trackingcode_emsFormBuilder`);
    const apiKeyMap = f(`apikey_map_emsFormBuilder`)
    //let smtp = f('smtp_emsFormBuilder')
    const bootstrap = f('bootstrap_emsFormBuilder');
    const osLocationPicker = f('osLocationPicker_emsFormBuilder');
    const scaptcha = f('scaptcha_emsFormBuilder');
    
    const activeDlBtn = f('activeDlBtn_emsFormBuilder');
    const showUpfile = f('showUpfile_emsFormBuilder');
    const adminSN  = f('adminSN_emsFormBuilder');
    //const showIp = f('showIp_emsFormBuilder');
    const showIp=false;
    smtp = f('hostSupportSmtp_emsFormBuilder');
    act_local_efb =f('act_local_efb')
    let emailTemp = f('emailTemp_emsFirmBuilder');
    emailTemp = emailTemp.replace(/([/\r\n|\r|\n/])+/g, ' ')
    let text = act_local_efb==true ? efb_var.text :'';
    if(typeof text != 'object' && text!=''){
        noti_message_efb('Localization not found. It seems there may be a conflict with a plugin and Easy Form Builder. Please reach out to the Easy Form Builder support team for assistance', 'danger', 'content-efb');
      return false;
    }else if(typeof text == 'object'){
      for(let i in text){
        text[i] = text[i].replace(/(["])+/g, `̎ᐥ`);
        text[i] = text[i].replace(/(['])+/g, `ᐠ`);
        text[i]= sanitize_text_efb(text[i]);    
      }
   }
    
    const payToken = f('payToken_emsFormBuilder');
    let temp = f('pno_emsFormBuilder');
    //console.log(temp)
    const phoneNumbers = temp.length<5 ? 'null' : temp;
    let AdnSPF=AdnOF=AdnPPF=AdnATC=AdnSS=AdnCPF=AdnESZ=AdnSE=
    AdnWHS=AdnPAP=AdnWSP=AdnSMF=AdnPLF=AdnMSF=AdnBEF=AdnPDP=AdnADP=0
    if(valueJson_ws_setting.hasOwnProperty('AdnSPF')){
      AdnSPF=valueJson_ws_setting.AdnSPF;
      AdnOF=valueJson_ws_setting.AdnOF;
      AdnPPF=valueJson_ws_setting.AdnPPF;
      AdnATC=valueJson_ws_setting.AdnATC;
      AdnSS=valueJson_ws_setting.AdnSS;
      AdnCPF=valueJson_ws_setting.AdnCPF;
      AdnESZ=valueJson_ws_setting.AdnESZ;
      AdnSE=valueJson_ws_setting.AdnSE;
      AdnWHS=valueJson_ws_setting.AdnWHS;
      AdnPAP=valueJson_ws_setting.AdnPAP;
      AdnWSP=valueJson_ws_setting.AdnWSP;
      AdnSMF=valueJson_ws_setting.AdnSMF;
      AdnPLF=valueJson_ws_setting.AdnPLF;
      AdnMSF=valueJson_ws_setting.AdnMSF;
      AdnBEF=valueJson_ws_setting.AdnBEF;
      AdnPDP=valueJson_ws_setting.hasOwnProperty('AdnPDP') ?valueJson_ws_setting.AdnPDP :0;
      AdnADP=valueJson_ws_setting.hasOwnProperty('AdnADP') ? valueJson_ws_setting.AdnADP :0;
    }
    const email_key_efb = valueJson_ws_setting.email_key ??  Math.random().toString(36).substr(2, 10);
    fun_send_setting_emsFormBuilder(
      { activeCode: activeCode, siteKey: sitekey, secretKey: secretkey, emailSupporter: email,
         apiKeyMap: `${apiKeyMap}`, smtp: smtp, text: text, bootstrap, emailTemp: emailTemp, 
         stripePKey: stripePKey, stripeSKey: stripeSKey, payToken: payToken, act_local_efb:act_local_efb,
          scaptcha:scaptcha ,activeDlBtn:activeDlBtn,dsupfile:showUpfile,sms_config:sms_config_efb,
         AdnSPF:AdnSPF,AdnOF:AdnOF,AdnPPF:AdnPPF,AdnATC:AdnATC,AdnSS:AdnSS,AdnCPF:AdnCPF,AdnESZ:AdnESZ, 
         AdnSE:AdnSE,AdnWHS:AdnWHS, AdnPAP:AdnPAP, AdnWSP:AdnWSP,AdnSMF:AdnSMF,AdnPLF:AdnPLF,AdnMSF:AdnMSF,
         AdnBEF:AdnBEF,AdnPDP:AdnPDP,AdnADP:AdnADP,phnNo:phoneNumbers , femail:femail,email_key:email_key_efb,showIp:showIp,adminSN:adminSN,osLocationPicker:osLocationPicker 
        } , state_auto);
  }

  /* document.getElementById('save-stng-efb').innerHTML = nnrhtml
  document.getElementById('save-stng-efb').classList.remove('disabled'); */
}

function fun_State_btn_set_setting_emsFormBuilder($state) {

   let el =  document.getElementById('save-stng-efb');
    if($state==true){
      el.classList.remove('disabled');
      el.innerHTML = `<i class="efb  bi-save mx-1"></i>${efb_var.text.save}`;
    }else{
     el.classList.add('disabled');
      el.innerHTML = `<i class="efb  bi-hourglass-split"></i>`;
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


function fun_send_setting_emsFormBuilder(data , state_auto = 0) {
  
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  data = JSON.stringify(data);
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

        
        if (res.data.success != true) {          
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
      if(state_auto==1){return}
      if(res.data.success == true){
        history.replaceState("panel",null,'?page=Emsfb&state=reload-setting&save=ok');            
        window.location=location.search;          

      }else{
        document.getElementById('save-stng-efb').innerHTML = `<i class="efb  bi-save mx-1"></i>${efb_var.text.save}`;
        document.getElementById('save-stng-efb').classList.remove('disabled');
        alert_message_efb(t, m, time, lrt);        
      }
      

    })
  });
}


function fun_find_track_emsFormBuilder() {
  
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  //function find track code
  const el = document.getElementById("track_code_emsFormBuilder").value;
  localStorage.setItem('search_efb',`${el}`)
  history.pushState("search",null,'?page=Emsfb&state=search');
  if (el.length < -1) {
    alert_message_efb(efb_var.text.error, efb_var.text.trackingCodeIsNotValid, 7, 'warning');

  } else {
    
    search_trackingcode_fun_efb(el)


  }
}//end function 


search_trackingcode_fun_efb =(el)=>{
  document.getElementById('track_code_emsFormBuilder').disabled = true;
    document.getElementById('track_code_btn_emsFormBuilder').disabled = true;
    const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
    document.getElementById('track_code_btn_emsFormBuilder').innerHTML = `<i class="efb bi-hourglass-split"></i>`;
  
  jQuery(function ($) {
    data = {
      action: "get_track_id_Emsfb",
      nonce: ajax_object_efm_core.nonce,
      value: el,
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {
      
      if (res.data.success == true) {
        
        valueJson_ws_messages = res.data.ajax_value;
        efb_var.nonce_msg = res.data.nonce_msg
        
        efb_var.msg_id = res.data.id
        
        // localStorage.setItem('valueJson_ws_messages', JSON.stringify(valueJson_ws_messages));
        document.getElementById("more_emsFormBuilder").style.display = "none";
        fun_ws_show_list_messages(valueJson_ws_messages);
        document.getElementById('track_code_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue;
      } else {
        alert_message_efb(efb_var.text.error, res.data.m, 4, 'warning');
        document.getElementById('track_code_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').disabled = false;
        document.getElementById('track_code_btn_emsFormBuilder').innerHTML = btnValue

      }
    })
  });
}


function clear_garbeg_emsFormBuilder() {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  //  emsFormBuilder_popUp_loading()

  document.getElementById('clrUnfileEfb').classList.add('disabled')
  document.getElementById('clrUnfileEfbText').innerHTML = efb_var.text.pleaseWaiting;

  jQuery(function ($) {
    data = {
      action: "clear_garbeg_Emsfb",
      nonce: ajax_object_efm_core.nonce
    };

    $.post(ajax_object_efm.ajax_url, data, function (res) {

      if (res.data.success == true) {
        alert_message_efb(efb_var.text.done, res.data.m, 4.7, 'info');
        document.getElementById('clrUnfileEfbText').innerHTML = efb_var.text.clearUnnecessaryFiles;
      } else {
        alert_message_efb(efb_var.text.error, res.data.m, 4.7, 'danger');

      }
    })
  });
  document.getElementById('clrUnfileEfb').classList.remove('disabled')
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
  add_multi = (c, content, value_col_index, v) => {
    if (form_type_emsFormBuilder == "survey") {
      if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
        rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
      } else {
        const r = rows.length
        const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
        rows = rows.concat(row);
        rows[parseInt(r)][parseInt(value_col_index)] = content[c].value;
        rows[parseInt(r)][0] = v;
      }
    }else if(content[c].type =="chlCheckBox"){ 
        rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB" ? rows[parseInt(i_count)][parseInt(value_col_index)] = `${content[c].value} : ${content[c].qty}` : rows[parseInt(i_count)][parseInt(value_col_index)] += "|| " + `${content[c].value} : ${content[c].qty}`
    }else {
      //tc rows[0][1] = efb_var.text.trackNo ;
      rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB" ? rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value : rows[parseInt(i_count)][parseInt(value_col_index)] += "|| " + content[c].value
    }
  }
  //let county = 0
  for (let v of value) {
  
    const content = JSON.parse(replaceContentMessageEfb(v.content))
    count += 1;
    i_count += i_count == -1 ? 2 : 1;

    for (let c in content) {
      // rows = Object.assign(rows, {[c.name]:c.value});
      let value_col_index;
      if(content[c]!=null && content[c].hasOwnProperty('id_') && content[c].id_.length>1){
        
        if (content[c].type != "checkbox" && content[c].type != 'multiselect'
          && content[c].type != "payCheckbox" && content[c].type != 'payMultiselect' && content[c].type != 'chlCheckBox'
          ) {
  
          if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;
  
  
          value_col_index = rows[0].findIndex(x => x == content[c].name);
  
          if (value_col_index == -1) {
  
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = content[c].name;
            if (content[c].type == 'payment') rows[0][parseInt(value_col_index) + 1] = "TID";
          }

  
          rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value;
          if (content[c].type == 'payment') {
            const vx = rows[0].findIndex(x => x == "TID");
            rows[parseInt(i_count)][parseInt(vx)] = content[c].paymentIntent;
          }else if(content[c].type == 'file' || content[c].type == 'media' || content[c].type == 'zip'  || content[c].type == 'image' || content[c].type == 'document' || content[c].type == 'allformat'){
            rows[parseInt(i_count)][parseInt(value_col_index)] =content[c].url;
          }
        } else if (content[c].type == 'multiselect' || content[c].type == 'payMultiselect') {
          if (rows[i_count][0] == "null@EFB") rows[i_count][0] = v.msg_id;
          //if (rows[i_count][1] == "null@EFB" &&  rows[0][1] == efb_var.text.trackNo){  rows[i_count][1] = v.track;}
          value_col_index = rows[0].findIndex(x => x == content[c].name);
          if (value_col_index == -1) {
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = content[c].name;
          }
          if (content[c].value.search(/@efb!+/g) != -1) {
            if (form_type_emsFormBuilder == "survey") {
              const nOb = content[c].value.split("@efb!")
              nOb.forEach(n => {
                if (n != "") {
                  
                  if (rows[parseInt(i_count)][parseInt(value_col_index)] == "null@EFB") {
                    rows[parseInt(i_count)][parseInt(value_col_index)] = n;
                  } else {
                    const r = rows.length
                    const row = Array.from(Array(1), () => Array(100).fill('notCount@EFB'))
                    rows = rows.concat(row);
                    rows[parseInt(r)][parseInt(value_col_index)] = n;
                    rows[parseInt(r)][0] = v.msg_id;
                  }
                }
              });
            } else {
              rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value.replaceAll('@efb!', " || ")
            }
          } else {
            rows[parseInt(i_count)][parseInt(value_col_index)] = content[c].value.replaceAll('@efb!', "");;
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
            add_multi(c, content, value_col_index, v.msg_id)
          } else {
            //if checkbox title is Nexists
            value_col_index = rows[0].findIndex(x => x == 'null@EFB');
            rows[0][parseInt(value_col_index)] = name;
            add_multi(c, content, value_col_index, v.msg_id)
  
          }

        
  
  
          //new code test
  
        }//end else
  
      }
   
    }


    //  exp.push(rows);
  }
  const col_index = rows[0].findIndex(x => x == 'null@EFB');

  const exp = Array.from(Array(rows.length), () => Array(col_index).fill(efb_var.text.noComment));

  for (let e in exp) {
    for (let i = 0; i < col_index; i++) {
      if (rows[e][i] != "null@EFB") exp[e][i] = rows[e][i];
    }
  }


  localStorage.setItem('rows_ws_p', JSON.stringify(exp));
  //  localStorage.setItem('head_ws_p', JSON.stringify(head));
}



function exportCSVFile_emsFormBuilder(items, fileTitle) {
  
  //source code :https://codepen.io/danny_pule/pen/WRgqNx
  items.forEach(item => { for (let i in item) { if (item[i] == "notCount@EFB") item[i] = ""; } });
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
  for (const item of array) {
    // console.log(item);
    let line = '';
    
   // for (const key in item) {
    for (var k=0 ; k < item.length ; k++) {
      if (line !== '') line += ',';
      line += item[k];
    }
    //item.pop();
    str += line + '\r\n';
  }  
  return str;

}


function generat_csv_emsFormBuilder() {
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  const filename = `EasyFormBuilder-${form_type_emsFormBuilder}-export-${Math.random().toString(36).substr(2, 3)}`
  exportCSVFile_emsFormBuilder(exp, filename); // create csv file
  //convert_to_dataset_emsFormBuilder(); //create dataset for chart :D
}


function convert_to_dataset_emsFormBuilder() {
  //console.log('convert_to_dataset_emsFormBuilder')
  const head = JSON.parse(localStorage.getItem("head_ws_p"));
  const exp = JSON.parse(localStorage.getItem("rows_ws_p"));
  let rows = exp;
  let countEnrty = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let entry = Array.from(Array(rows[0].length), () => Array(0).fill(0));
  let titleTable = []; // list name of tables and thier titles
  for (let col in rows) {
    if (col != 0) {
      for (let c=0 ; c<rows[col].length ; c++) {
        if (rows[col][c] != 'null@EFB' && rows[col][c] != 'notCount@EFB') {
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

      for (let v of rows[col]) {

        titleTable.push(v);
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
  let body = `
  <div class="efb  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" id="overpage">
    <div id="overpage-chart">
        ${efbLoadingCard()}
    </div>
  </div>`;
  // window.scrollTo({ top: 0, behavior: 'smooth' });

  show_modal_efb(body, efb_var.text.chart, "bi-pie-chart-fill", 'chart')
  //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
  //myModal.show_efb()
  state_modal_show_efb(1)
  
  setTimeout(() => {


    for (let t in titles) {
      chartId.push(Math.random().toString(36).substring(8));
      if (t != 0) {
        chartview += ` </br> <div id="${chartId[t]}"/ class="efb ${t == 0 ? `hidden` : ``}">
          <h1 class="efb fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
          <h3 class="efb text-white  text-center">${efb_var.text.pleaseWaiting}<h3> </div>`
      } else { chartview += ` </br> <div id="${chartId[t]}"/ class="efb ${t == 0 ? `hidden` : ``}"></div>` }
    }

    
    
    
    document.getElementById('overpage-chart').innerHTML = chartview

    
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
      const valj_efb_ = JSON.parse(sessionStorage.getItem("valj_efb"));
      for (let r in colname[t]) {

        rows[r][0] = colname[t][r];
        rows[r][1] = colvalue[t][r];
      }//end for 2

      rowsOfCharts[t] = rows;

      google.charts.load('current', { packages: ['corechart'] });
      publicidofchart = chartId[t];

      
      drawPieChartArr[t] = () => {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Element');
        data.addColumn('number', 'integer');


        data.addRows(rowsOfCharts[t]);

        // Instantiate and draw the chart.
        var chart = new google.visualization.PieChart(document.getElementById(chartId[t]));
        chart.draw(data, opetionsOfCharts[t]);
      }
      

      try {

        google.charts.setOnLoadCallback(drawPieChartArr[t]);
      } catch (error) {

      }

    }// end for 1
    

  }, 1000);


}//end function

function googleCloudOffer() { return `<p>${efb_var.text.offerGoogleCloud} <a href="https://gcpsignup.page.link/8cwn" target="blank">${efb_var.text.getOfferTextlink}</a> </p> ` }


function clickToCheckEmailServer() {
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  document.getElementById('clickToCheckEmailServer').classList.add('disabled')
  const nnrhtml = document.getElementById('clickToCheckEmailServer').innerHTML;
  document.getElementById('clickToCheckEmailServer').innerHTML = `<i class="efb bi bi-hourglass-split"></i>`;
  const email = document.getElementById('email_emsFormBuilder').value;
  // call and waitning response  
  if (email.length > 5) {
    jQuery(function ($) {
      data = {
        action: "check_email_server_efb",
        nonce: ajax_object_efm_core.nonce,
        value: 'testMailServer',
        email: email
      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {
        const el= document.getElementById("hostSupportSmtp_emsFormBuilder");
        if (res.data.success == true) {
          alert_message_efb(efb_var.text.done, efb_var.text.serverEmailAble, 5);
         if(el.classList.contains('active')==false) el.classList.add('active') ;
         //fun_set_setting_emsFormBuilder(1);
        } else {

          alert_message_efb(efb_var.text.alert, efb_var.text.PleaseMTPNotWork, 60, 'warning');
          el.classList.remove('active') ;
        }
        document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
        document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
      })
    });

  } else {
    alert_message_efb(efb_var.text.error, efb_var.text.enterAdminEmail, 10, 'warning');
    document.getElementById('clickToCheckEmailServer').innerHTML = nnrhtml
    document.getElementById('clickToCheckEmailServer').classList.remove('disabled')
  }

}

window.onload = (() => {
  // remove all notifications from other plugins or wordpress
  //jQuery(document).ready(function () { jQuery("body").addClass("folded") })
  setTimeout(() => {
    for (const el of document.querySelectorAll(".notice")) {
      el.remove()

    }
    //folded
  }, 50)
})


function email_template_efb(s) {
  
  if (s == 'p') {
    //preview
    let c = document.getElementById('emailTemp_emsFirmBuilder').value;
    let ti = efb_var.text.error;
    //c = c.replace(/(http:@efb@|https:@efb@)+/g, '//');
    c = c.replace(/(@efb@)+/g, '/');
    if (c.match(/(<script+)/gi)) {
      //show error message you can't use script code
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.pleaseDoNotAddJsCode}</p></div>`;
      //return 0;
    } else if (c.length > 2 && c.length < 2000) {
      ti = efb_var.text.preview;
      if (!c.includes('shortcode_message') && !c.includes('shortcode_title')) {
        c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.addSCEmailM}</p></div>`;
        ti = efb_var.text.error;
      }
      else if (efb_var.pro!="true" && efb_var.pro!=true) {

        c += funNproEmailTemp();
        
      }
    } else if (c.length >= 10000) {
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-exclamation-triangle fs-3 text-danger efb"></div><p class="efb fs-5 efb">${efb_var.text.ChrlimitEmail}</p></div>`;
      //ti =efb_var.text.error
    } else if (c.length < 2) {
      c = `<div class="efb text-center text-darkb efb"><div class="efb bi-emoji-frown fs-4 efb"></div><p class="efb fs-5 efb">${efb_var.text.notFound}</p></div>`
      //show_modal_efb(``, ti, '', 'saveBox');
    } else {
      ti = efb_var.text.preview;
    }
    show_modal_efb(c, ti, '', 'saveBox');
    //const myModal = new bootstrap.Modal(document.getElementById("settingModalEfb"), {});
    //myModal.show_efb();
    state_modal_show_efb(1)
  } else if (s == "h") {
    //show help
    //open link to document how create a email template
  } else if (s == 'r') {
    //reset
    document.getElementById('emailTemp_emsFirmBuilder').value = '';
  }
}


function EmailTemp1Efb() {
  return `<html xmlns='http://www.w3.org/1999/xhtml'>
  <head>
  <meta http-equiv='content-type' content='text/html; charset=utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0;'>
   <meta name='format-detection' content='telephone=no'/>
  <style>
  body { margin: 0; padding: 0; min-width: 100%; width: 100% !important; height: 100% !important; ${efb_var.rtl == 1 ? `direction:rtl;` : ''}}
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
  <table width='100%' align='center' border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; width: 100%;' class='efb background'><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;' bgcolor='#2D3445'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' width='500' style='border-collapse: collapse; border-spacing: 0; padding: 0; width: inherit; max-width: 500px;' class='efb wrapper'>
  <tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;
  padding-top: 0px;' class='efb hero'><a target='_blank' style='text-decoration: none;' href='shortcode_website_url'><img border='0' vspace='0' hspace='0' src='${efb_var.images.emailTemplate1}' alt='Please enable images to view this content' title='Email Notification' width='340' style='width: 87.5%;max-width: 340px;color: #FFFFFF; font-size: 13px; margin: 0; padding: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; border: none; display: block;'/></a></td>
  </tr><tr></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0;padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 24px; font-weight: bold; line-height: 130%;padding-top: 5px;color: #FFFFFF;font-family: sans-serif;' class='efb header'>shortcode_title
  </td></tr><tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 17px; font-weight: 400; line-height: 160%;
  padding-top: 15px; color: #FFFFFF; font-family: sans-serif;' class='efb paragraph'> shortcode_message </td></tr><tr>
  <td align='center' valign='top' style='background:#2D3445; border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%;
  padding-top: 25px; padding-bottom: 5px; border-color: #2d3445;' class='efb button'><a href='shortcode_website_url' target='_blank' style='text-decoration: none;'>
  <table border='0' cellpadding='0' cellspacing='0' align='center' style='max-width: 240px; min-width: 120px; border-collapse: collapse; border-spacing: 0; padding: 0;'><tr><td align='center' valign='middle' style='padding: 12px 24px; margin: 0; text-decoration: none; border-collapse: collapse; border-spacing: 0; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; -khtml-border-radius: 4px;' bgcolor='#0a016e'><a target='_blank' style='text-decoration: none; color: #FFFFFF; font-family: sans-serif; font-size: 17px; font-weight: 400; line-height: 120%;' href='shortcode_website_url'>
  ${efb_var.text.clickHere}  </a></td></tr></table></a> </td>  </tr>
  <tr><td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; padding-top: 30px;' class='efb line'><hr color='#565F73' align='center' width='100%' size='1' noshade style='margin: 0; padding: 0;' /></td></tr><tr>
  <td align='center' valign='top' style='border-collapse: collapse; border-spacing: 0; margin: 0; padding: 0; padding-left: 6.25%; padding-right: 6.25%; width: 87.5%; font-size: 13px; font-weight: 400; line-height: 150%; padding-top: 10px; padding-bottom: 20px; color: #828999; font-family: sans-serif;' class='efb footer'>
  ${efb_var.text.sentBy} <a href='shortcode_website_url' target='_blank' style='text-decoration: none; color: #828999; font-family: sans-serif; font-size: 13px; font-weight: 400; line-height: 150%;'>shortcode_website_name</a>.
  </td></tr></table></td></tr></table>  
  
  </body></html>`
}

function EmailTemp2Efb() {
  return `<html xmlns='http://www.w3.org/1999/xhtml'> <body> <style> body {margin:auto 10px;${efb_var.rtl == 1 ? `direction:rtl;` : ''}}</style><center>
<table class='efb body-wrap' style='text-align:center;width:100%;font-family:arial,sans-serif;border:12px solid rgba(126, 122, 122, 0.08);border-spacing:4px 20px;'> <tr>
          <img src='${efb_var.images.emailTemplate1}' style='width:36%;'>
</tr> <tr> <td><center> <table bgcolor='#FFFFFF' width='80%'' border='0'>  <tbody> <tr>
<td style='font-family:Roboto-Regular,Helvetica,Arial,sans-serif;font-size:13px;color:#202020;line-height:1.5'>
<h1 style='color:#575252;text-align:center;'>shortcode_title</h1>
</td></tr><tr style='text-align:center;color:#000000;font-size:14px;'><td>
            <span>shortcode_message</span>
</td> </tr><tr style='text-align:center;color:#000000;font-size:14px;height:45px;'><td> <span><b style='color:#575252;'>
            <a href='shortcode_website_url'>shortcode_website_name</a>
</span></td></tr></tbody></center></td> </tr></table></center></body>  </html>`
}


function fun_add_email_template_efb(i) {
  switch (i) {
    case 1:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp1Efb();
      break;

    case 2:
      document.getElementById('emailTemp_emsFirmBuilder').value = EmailTemp2Efb();
      break;

  }
}

function funNproEmailTemp() {
 const ws = efb_var.language != "fa_IR" ? "https://whitestudio.team/" : 'https://easyformbuilder.ir';
  
  return `<table role='presentation' bgcolor='#F5F8FA' width='100%'>
  <a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="This field available in Pro version" data-original-title="This field available in Pro version"><i class="efb  bi-gem text-light"></i></a>
  <tr> <td align='left' style='padding: 30px 30px; font-size:12px; text-align:center'><a class='efb subtle-link' target='_blank' href='https://wordpress.org/plugins/easy-form-builder/'><img src="https://ps.w.org/easy-form-builder/assets/icon-256x256.gif" style="margin: 5px; width:16px;height:16px" >  ${efb_var.text.easyFormBuilder}</a> 
 <br> <img src="${ws}img/favicon.png" style="margin: 5px"> <a class='efb subtle-link' target='_blank' href='${ws}'>White Studio Team</a></td></tr>`
}

function act_local_efb_event(t){
  
  setTimeout(() => {
    
    t.classList.contains('active')==true ? document.getElementById('textList-efb').classList.remove('d-none'): document.getElementById('textList-efb').classList.add('d-none')    
  }, 80);
}


function check_server_sms_method_efb(el){
  
  if(Number(efb_var.pro)!=1){
    pro_show_efb(efb_var.text.proUnlockMsg) 
    el.checked = false;
    return;
  } else if(valueJson_ws_setting.AdnSS!=1){
    let m = efb_var.text.msg_adons.replace('NN',`<b>${efb_var.text.sms_noti}</b>`);
    noti_message_efb(m, 'danger' , `content-efb` );
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 20000);
    el.checked = false;
    return;
  }else if( efb_var.plugins.wpsms ==0 && el.id=="wp_sms_plugin"){
   //scroll down and montion
   noti_message_efb(efb_var.text.wpsms_nm, 'danger' , `content-efb` );
   window.scrollTo({
    top: document.body.scrollHeight,
    behavior: 'smooth'
  });
    setTimeout(() => {document.getElementById('noti_content_efb').remove();}, 15000);
    
   
    el.checked = false;
    return;
  }

  if(el.id=="efb_sms_service"){
    sms_config_efb='efb'
  }else if(el.id=="wp_sms_plugin"){
    sms_config_efb='wpsms'
  }
  
}

async function fun_dup_request_server_efb(id ,type){
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  if(type=='form'){
    document.getElementById(id+'-dup-efb').innerHTML=svg_loading_efb('text-light');
    document.getElementById(id+'-dup-efb').disabled=true;
    $result = await fun_dup_form_server_efb(id,type);
  }
  //  emsFormBuilder_popUp_loading()
  //when complated <i class="efb  bi-clipboard-plus"></i>
}

function fun_dup_form_server_efb(id,type){
  return new Promise(resolve => {
    jQuery(function ($) {
      data = {
        action: "dup_efb",
        nonce: ajax_object_efm_core.nonce,
        id: id,
        type: type

      };

      $.post(ajax_object_efm.ajax_url, data, function (res) {
        if (res.data.success == true) {
          emsFormBuilder_waiting_response();
         // valueJson_ws_form = [...valueJson_ws_form];
          valueJson_ws_form.push({form_id:res.data.form_id, form_name:res.data.form_name, form_create_date:res.data.date,form_type:res.data.form_type});
          // valueJson_ws_form = deepFreeze_efb(valueJson_ws_form);
          //console.log(valueJson_ws_form);
          alert_message_efb(efb_var.text.done, res.data.m, 4, 'success');
          //console.log(valueJson_ws_form.length);
          fun_emsFormBuilder_render_view(valueJson_ws_form.length)

          resolve(true);
        } else {
          alert_message_efb(efb_var.text.error, res.data.m, 4, 'danger');
          document.getElementById(id+'-dup-efb').innerHTML='<i class="efb  bi-clipboard-plus"></i>';
          document.getElementById(id+'-dup-efb').disabled=false;
          resolve(false);
        }
      })
    });
  });
}

function fun_select_rows_table(el){
  //valueJson_ws_messages
  //efb  emsFormBuilder_v form-check-input fs-8 allmsg
  if(el.classList.contains('allmsg')){
    let els = document.querySelectorAll(".onemsg")
   let state =true;
    if(el.checked==true){
      for (let i = 0; i < els.length; i++) {
        els[i].checked=true;
      }
    }else{
      state = false;
      for (let i = 0; i < els.length; i++) {
        els[i].checked=false;
      }
     
    }
    for (let i = 0; i < valueJson_ws_messages.length; i++) {
      valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = state : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:state}
    }
  }else if (el.classList.contains('onemsg')){
    const msg_id = el.dataset.id;
    //find in valueJson_ws_messages by msg_id
    const i = valueJson_ws_messages.findIndex(x => x.msg_id == msg_id);
    if(el.checked){
    // add true checked to valueJson_ws_messages
    valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = true : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:true}
    }else{
     // add false checked to valueJson_ws_messages
      valueJson_ws_messages[i].hasOwnProperty('checked') ? valueJson_ws_messages[i].checked = false : valueJson_ws_messages[i] = {...valueJson_ws_messages[i], checked:false}
    }
    // console.log( valueJson_ws_messages[i]);
  }
}

function event_selected_row_emsFormBuilder(state){
  let list_selected = valueJson_ws_messages.filter(x => x.checked == true).map(x => JSON.parse(JSON.stringify(x)));
  // console.log(list_selected,state);
  for(let i in list_selected){
    if(list_selected[i].hasOwnProperty('content')){
      //remove content attrebute
      list_selected[i].content='';
    }
  }
  //check is pro version 
  /* if(Number(efb_var.pro)!=1){
    pro_show_efb(efb_var.text.proUnlockMsg) 
    return;
  } */
  if(list_selected.length==0){
    alert_message_efb(efb_var.text.error, efb_var.text.nsrf, 8, 'warning');
    return;
  }
  if(state=='delete'){
    emsFormBuilder_delete('','message',list_selected);
  }else{
    // console.log(state,list_selected,valueJson_ws_messages);
    emsFormBuilder_read('msg',list_selected);
    for (const v of list_selected) {
      const foundIndex = Object.keys(valueJson_ws_messages).length > 0 ? valueJson_ws_messages.findIndex(x => x.msg_id == v.msg_id) : -1
      // console.log(foundIndex);
      if (foundIndex != -1) valueJson_ws_messages[foundIndex].read_ = "1";
    }   
    console.error(valueJson_ws_messages);
    setTimeout(() => {
      fun_ws_show_list_messages(valueJson_ws_messages)
    }, 1000);
  }
} //End function


function emsFormBuilder_read(state,val){
  if (!navigator.onLine) {
    alert_message_efb('',efb_var.text.offlineSend, 17, 'danger')         
    return;
  }
  // console.log(state ,val);

  jQuery(function ($) {
    data = {
      action: "read_list_Emsfb",
      type: "POST",
      val: JSON.stringify(val),
      state: state,
      nonce: ajax_object_efm_core.nonce,
    };
    $.post(ajax_object_efm.ajax_url, data, function (res) {
      //console.log(res);
      if (res.data.success == true) {
        setTimeout(() => {
          alert_message_efb(efb_var.text.done, '', 3, 'info');
          
        }, 3)
        // location.reload();
      } else {
        
        setTimeout(() => {
          alert_message_efb(efb_var.text.error, res.data.m, 3, 'danger')
        }, 3)
      }
    })
  });
}


// v3.8.6 start
      
function addClickListenerToElement(element) {
  if (!element.hasClickListener) { 
      let state_event = false; 

      element.addEventListener("click", function (event) {
          if (!state_event) { 
              const classes = event.target.classList; 
              setTimeout(() => {
                state_event = false; 
              }, 200);

              if (classes.contains("ec-efb")) { 
                const pro = Number(efb_var.pro) === 1;
                  state_event = true; 

                  const dataset = event.target.dataset; 
                 

                  const eventform = dataset.hasOwnProperty('eventform') ? sanitize_text_efb(dataset.eventform) : false;
                  let temp ='';
                  let temp2='';
                  if (eventform) {
                      
                      switch (eventform) {
                          case 'message':
                            temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_messages(temp2);
                              break;
                          case 'openMessage':
                            temp = Number(dataset.msgid);
                            temp2 = Number(dataset.msgstate);
                            fun_open_message_emsFormBuilder(temp,temp2);
                            break;
                          case 'edit':
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_get_edit_form(temp2);
                              break;
                          case 'delete':
                              temp = sanitize_text_efb(dataset.formname);
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_delete(temp2, 'form', temp);
                              break;
                          case 'duplicate':
                            console.log('duplicate');
                              temp = sanitize_text_efb(dataset.formname);
                              temp2 = sanitize_text_efb(dataset.id);
                              emsFormBuilder_duplicate(temp2, 'form', temp);
                              break;
                          case 'generateCSV':
                              pro ? generat_csv_emsFormBuilder() : pro_show_efb(efb_var.text.proUnlockMsg);
                              break;
                          case 'generateChart':
                              convert_to_dataset_emsFormBuilder();
                              break;
                          case 'deleteSelectedRow':
                              event_selected_row_emsFormBuilder('delete');
                              break;
                          case 'readSelectedRow':
                              pro ? event_selected_row_emsFormBuilder('read') : pro_show_efb(efb_var.text.proUnlockMsg);
                              break;                         
                          case 'setting':
                              fun_show_content_page_emsFormBuilder('setting');
                              break;
                          case 'help':
                              fun_show_content_page_emsFormBuilder('help');
                              break;
                          case 'forms':
                              fun_show_content_page_emsFormBuilder('forms');
                              break;
                          case 'searchCC':
                              fun_find_track_emsFormBuilder();
                              break;
                          case 'sideMenuEfb':
                              sideMenuEfb(0);
                              break;                          
                          case 'links':
                            temp = sanitize_text_efb(dataset.linkname);
                              Link_emsFormBuilder(temp);
                          break;
                          case 'deleteMsg':      
                            temp = sanitize_text_efb(dataset.msgid);
                            temp2 = sanitize_text_efb(dataset.trackid);
                            //`emsFormBuilder_delete(${v.msg_id} ,'message','${v.track}')` : `pro_show_efb('${efb_var.text.availableInProversion}')`}"
                            pro ? emsFormBuilder_delete(temp ,'message',temp2) : pro_show_efb(efb_var.text.proUnlockMsg);


                          break;
                          default:
                              console.log("Unknown eventform action.");
                      }
                  }
              }
          }
      });

      element.hasClickListener = true; // پرچم برای جلوگیری از اضافه شدن چندباره لیسنر
  }
}

      // افزودن Listener کلیک به همه المان‌های موجود
      function observeExistingElements() {
        console.log('observeExistingElements');
        const els = document.querySelectorAll(".ec-efb");
        els.forEach(addClickListenerToElement);
        //document.querySelectorAll("*").forEach(addClickListenerToElement);
      }

      // ایجاد MutationObserver برای نظارت بر اضافه شدن المان‌های جدید
      const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // بررسی اینکه المان یک عنصر DOM است                                    
                    // همچنین به فرزندان این المان نیز نظارت کنیم
                    
                    const els = node.querySelectorAll(".ec-efb");
                    els.forEach(addClickListenerToElement);
                }
            });
        });
      });

      // آغاز نظارت بر تغییرات در کل سند
      observer.observe(document.body, {
        childList: true, // نظارت بر تغییرات در فرزندان مستقیم
        subtree: true    // نظارت بر تغییرات در کل زیرشاخه‌ها
      });

      // نظارت بر المان‌های موجود در لحظه بارگذاری
      observeExistingElements();
// v3.8.6 end
